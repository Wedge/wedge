<?php
/**
 * Wedge
 *
 * The templating code, aka rendering process.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * This function marks the end of processing, proceeds to close down output buffers, flushing content as it does so, before terminating execution.
 *
 * This function operates in two principle ways - raw content, or forum page mode, depending on the parameters passed; this is so non HTML data can be passed through it, e.g. XML.
 *
 * Several side operations occur alongside principle handling:
 * - Recursive calls to this function will attempt to be blocked.
 * - The stats cache will be flushed to the tables (so instead of issuing queries that potentially update the same values multiple times, they are only updated on closedown)
 * - A call will be put in to work on the mail queue.
 * - Make sure the page title is sanitized.
 * - Begin the session ID injecting output buffer.
 * - Ensure any hooked buffers are called.
 * - Display the headers if needed, then the main page content, then the footer if needed, and lastly the debug data if enabled and available.
 * - Store the user agent string from the browser for security comparisons next page load.
 *
 * @param mixed $start Whether to issue the header templates or not. Normally this will be the case, because normally you will require standard templating (i.e pass null, or true here when calling from elsewhere in the app), or false if you require raw content output.
 * @param mixed $do_finish Nominally this follows $start, with one important difference. Whereas with $start, null means to have headers, with $do_finish, null means to inherit from $start. So to have headers, a null/null combination is usually desirable (as index.php does), or to have raw output, simply pass $start as false and omit this parameter.
 * @param bool $from_index If this function is being called in the normal process of execution, this will be true, which enables this function to return so it can be called again later (so the headers can be issued, followed by normal processing, followed by the footer, which is all driven by this function). Normally there will be no need to change this because when calling from elsewhere, execution is intended to end.
 * @param boom $from_fatal_error If obExit is being called in resolution of a fatal error, this must be set. It is used in ensuring obExit cascades correctly for headers/footer when a fatal error has been encountered. Note that the error handler itself should attend to this (and thus, should be called instead of invoking this with an error message)
 */
function obExit($start = null, $do_finish = null, $from_index = false, $from_fatal_error = false)
{
	global $context, $theme, $settings, $txt;
	static $start_done = false, $level = 0, $has_fatal_error = false;

	// Attempt to prevent a recursive loop.
	if (++$level > 1 && !$from_fatal_error && !$has_fatal_error)
		exit;
	if ($from_fatal_error)
		$has_fatal_error = true;

	// Clear out the stat cache.
	trackStats();

	// If we have mail to send, send it.
	if (!empty($context['flush_mail']))
		AddMailQueue(true);

	$do_start = $start === null ? !$start_done : $start;
	if ($do_finish === null)
		$do_finish = $do_start;

	// Has the template been started yet?
	if ($do_start)
	{
		// Generate a HTML-safe version of the page title: notably, remove any tags and encode entities. What a messy call though...
		if (!empty($context['page_title']) && empty($context['page_title_html_safe']))
			$context['page_title_html_safe'] = westr::htmlspecialchars(un_htmlspecialchars(strip_tags($context['page_title'])), ENT_COMPAT, false, false);

		// Start up the session URL fixer.
		ob_start('ob_sessrewrite');

		// Run any possible extra output buffers as provided by mods.
		if (!empty($theme['output_buffers']) && is_string($theme['output_buffers']))
			$buffers = explode(',', $theme['output_buffers']);
		elseif (!empty($theme['output_buffers']))
			$buffers = $theme['output_buffers'];
		else
			$buffers = array();

		if (isset($settings['hooks']['buffer']))
			$buffers = array_merge($settings['hooks']['buffer'], $buffers);

		if (!empty($buffers))
		{
			foreach ($buffers as $function)
			{
				$fun = explode('|', trim($function));
				$call = strpos($fun[0], '::') !== false ? explode('::', $fun[0]) : $fun[0];

				// We might need to load some stuff here.
				if (!empty($fun[1]))
				{
					if (!empty($fun[2]) && $fun[2] === 'plugin')
						require_once($fun[1] . '.php');
					else
						loadSource($fun[1]);
				}
				ob_start($call);
			}
		}

		// Display the screen in the logical order.
		start_output();
		$start_done = true;
	}

	if ($do_finish)
		wetem::render();

	// Remember this URL in case someone doesn't like sending HTTP_REFERER.
	if (isset($_SERVER['REQUEST_URL']) && strpos($_SERVER['REQUEST_URL'], 'action=dlattach') === false && strpos($_SERVER['REQUEST_URL'], 'action=viewremote') === false)
		$_SESSION['old_url'] = $_SERVER['REQUEST_URL'];

	// For session check verification.... Don't switch browsers...
	$_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];

	// Hand off the output to the portal, etc. we're integrated with.
	call_hook('exit', array($do_finish));

	// Don't exit if we're coming from index.php; that will pass through normally.
	if (!$from_index)
	{
		if (!isset($settings['app_error_count']))
			$settings['app_error_count'] = 0;
		if (!empty($context['app_error_count']))
			updateSettings(array(
				'app_error_count' => $settings['app_error_count'] + $context['app_error_count'],
			));
		exit;
	}
}

/**
 * Rewrites URLs in the page to include the session ID if the user is using a normal browser and is not accepting cookies.
 *
 * - If $scripturl is empty, or no session id (e.g. SSI), exit.
 * - If ?debug has been specified previously, re-inject it back into the page's canonical reference.
 * - We also do our Pretty URLs voodoo here...
 *
 * @param string $buffer The contents of the output buffer thus far. Managed by PHP during the relevant ob_*() calls.
 * @return string The modified buffer.
 */
function ob_sessrewrite($buffer)
{
	global $scripturl, $settings, $context, $db_prefix, $session_var;
	global $txt, $time_start, $db_count, $db_show_debug, $cached_urls, $use_cache, $member_colors;

	// Just quit if $scripturl is set to nothing, or the SID is not defined. (SSI?)
	if ($scripturl == '' || !defined('SID'))
		return $buffer;

	if (!empty($context['show_load_time']))
	{
		$old_db_count = $db_count;
		$old_load_time = microtime(true);
	}

	// Very fast on-the-fly replacement of <URL>...
	$buffer = str_replace('<URL>', $scripturl, $buffer);

	if (isset($context['meta_description'], $context['meta_description_repl']))
		$buffer = str_replace($context['meta_description'], $context['meta_description_repl'], $buffer);

	// A regex-ready $scripturl, useful later.
	$preg_scripturl = preg_quote($scripturl, '~');

	call_hook('dynamic_rewrite', array(&$buffer));

	// Plugins may add inline CSS with add_css()...
	if (!empty($context['header_css']))
		$context['header'] .= "\n\t<style>" . $context['header_css'] . "\n\t</style>";

	// ...or headers by manipulating $context['header']. They're only added if the page has a 'html' layer (which usually holds the <head> area.)
	if (!empty($context['header']) && wetem::has_layer('html') && ($where = strpos($buffer, "\n</head>")) !== false)
		$buffer = substr_replace($buffer, $context['header'], $where, 0);

	// Moving all inline events (<code onclick="event();">) to the footer, to make
	// sure they're not triggered before jQuery and stuff are loaded. Trick and treats!
	$context['delayed_events'] = array();
	$cut = explode("<!-- JavaScript area -->\n", $buffer);

	// If the placeholder isn't there, it means we're probably not in a default index template,
	// and we probably don't need to postpone any events. Otherwise, go ahead and do the magic!
	if (!empty($cut[1]))
		$buffer = preg_replace_callback('~<[^>]+?\son[a-z]+="[^"]*"[^>]*>~i', 'wedge_event_delayer', $cut[0]) . $cut[1];

	$this_pos = strpos($buffer, empty($settings['minify_html']) ? '<!-- insert inline events here -->' : '<!--insert inline events here-->');
	if ($this_pos !== false)
	{
		if (!empty($context['delayed_events']))
		{
			$thing = 'eves = {';
			foreach ($context['delayed_events'] as $eve)
				$thing .= '
			' . $eve[0] . ': ["' . $eve[1] . '", function (e) { ' . $eve[2] . ' }],';
			$thing = substr($thing, 0, -1) . '
		};';
		}
		else
			$thing = 'eves = 1;';

		$buffer = substr_replace($buffer, $thing, $this_pos, empty($settings['minify_html']) ? 34 : 32);
	}

	// Nerd alert -- the first few lines (tag search process) can be done in a simple regex.
	//	while (preg_match_all('~<we:([^>\s]+)\s*([a-z][^>]+)?\>((?' . '>[^<]+|<(?!/?we:\\1))*?)</we:\\1>~i', $buffer, $matches, PREG_SET_ORDER))
	// It's case-insensitive, but always slower -- noticeably so with hundreds of macros.

	// Don't waste time replacing macros if there are none in the first place.
	if (!empty($context['macros']) && strpos($buffer, '<we:') !== false)
	{
		// Case-sensitive version - you themers please don't use <We> or <WE> tags, or I'll tell your momma.
		while (strpos($buffer, '<we:') !== false)
		{
			$p = 0;
			while (($p = strpos($buffer, '<we:', $p)) !== false)
			{
				$space = strpos($buffer, ' ', $p);
				$gt = strpos($buffer, '>', $p);
				$code = substr($buffer, $p + 4, min($space, $gt) - $p - 4);
				$end_code = strpos($buffer, '</we:' . strtolower($code), $p + 4);
				$next_code = strpos($buffer, '<we:', $p + 4);

				if ($end_code === false)
					$end_code = strlen($buffer);

				// Did we find a macro with no nested macros?
				if ($next_code !== false && $end_code > $next_code)
				{
					$p += 4;
					continue;
				}

				// We don't like unknown macros in this town.
				$macro = isset($context['macros'][$code]) ? $context['macros'][$code] : array('has_if' => false, 'body' => '');
				$body = str_replace('{body}', substr($buffer, $gt + 1, $end_code - $gt - 1), $macro['body']);

				// Has it got an <if:param> section?
				if ($macro['has_if'])
				{
					preg_match_all('~([a-z][^\s="]*)="([^"]+)"~', substr($buffer, $p, $gt - $p), $params);

					// Remove <if> and its contents if the param is not used in the template. Otherwise, clean up the <if> tag...
					while (preg_match_all('~<if:([^>]+)>((?' . '>[^<]+|<(?!/?if:\\1>))*?)</if:\\1>~i', $body, $ifs, PREG_SET_ORDER))
						foreach ($ifs as $ifi)
							$body = str_replace($ifi[0], !empty($params) && in_array($ifi[1], $params[1]) ? $ifi[2] : '', $body);

					// ...And replace with the contents.
					if (!empty($params))
						foreach ($params[1] as $id => $param)
							$body = str_replace('{' . $param . '}', strpos($params[2][$id], 'htmlsafe::') === 0 ? html_entity_decode(substr($params[2][$id], 10)) : $params[2][$id], $body);
				}
				$buffer = str_replace(substr($buffer, $p, $end_code + strlen($code) + 6 - $p), $body, $buffer);
			}
		}
	}

	if (!empty($context['skin_replace']))
	{
		// Don't waste time replacing macros if there are none in the first place.
		foreach ($context['skin_replace'] as $from => $to)
		{
			// Regular expression? Easy as pie...
			if ($to[1])
			{
				$buffer = preg_replace('~' . str_replace('~', '\~', $from) . '~si', $to[0], $buffer);
				continue;
			}
			$to = $to[0];
			preg_match('~<we:nested:(\w+)[ /]*>~i', $from, $nested);

			// Just a simple string, no funny business?
			if (empty($nested))
			{
				$buffer = str_replace($from, $to, $buffer);
				continue;
			}

			// So, we found ourselves a nested area... Gonna be fun. The main reason for the size of this code
			// is that we don't want to use a recursive regex, which would do the bulk to the work in a single line,
			// but would be both slower and more fragile. Never do that on a large string like a web page buffer.
			$nest = $nested[1];
			$nestlen = strlen($nest);
			$split = strpos($from, $nested[0]);
			$from = str_replace($nested[0], '', $from);
			$opener_code = substr($from, 0, $split);
			$closer_code = substr($from, $split);
			$start = 0;

			while ($start !== false)
			{
				$from_start = strpos($buffer, $opener_code, $start);

				// Opening part not found..? Just skip this replacement.
				if ($from_start === false)
					break;

				// Otherwise, start going through the string from our first occurrence.
				$p = $offset = $from_start + $split;
				$nestlevel = 0;

				// First, we need to establish whether there really is a nested item within our queried string.
				while (($test1 = strpos($buffer, '<' . $nest, $p)) !== false && ($buffer[$test1 + $nestlen + 1] !== ' ' && $buffer[$test1 + $nestlen + 1] !== '>'));
				$from_end = strpos($buffer, $closer_code, $p);
				$do_test = $test1 !== false && $from_end !== false && $test1 < $from_end;
				$next_closer = $from_end;

				while ($do_test)
				{
					$next_opener = $p;
					while (($next_opener = strpos($buffer, '<' . $nest, $next_opener)) !== false && ($buffer[$next_opener + $nestlen + 1] !== ' ' && $buffer[$next_opener + $nestlen + 1] !== '>'));
					$next_closer = strpos($buffer, '</' . $nest . '>', $p);
					// Nothing left? Then it's broken HTML... Let's get out of here.
					if ($next_closer === false)
						break;
					// No opener left? Then the closer must be the one we're looking for.
					if ($next_opener === false)
						$next_opener = $next_closer + 1;
					// Otherwise, increase or decrease the nesting level depending on which came first.
					$p = min($next_opener, $next_closer) + 1;
					$nestlevel += $next_opener < $next_closer ? 1 : -1;
					// Have we reached the end of our nested area?
					if ($nestlevel < 0)
						break;
				}

				// Okay, mission accomplished, we found a proper closer.
				if ($next_closer !== false)
				{
					$actual_replace = str_replace($nested[0], substr($buffer, $offset, $next_closer - $offset), $to);
					$buffer = substr_replace(
						$buffer,
						$actual_replace,
						$offset - $split,
						strlen($from) + $next_closer - $offset
					);
				}

				// Let's ensure all subsequent finds are also replaced.
				$start = $offset - $split + strlen($actual_replace);
				unset($actual_replace);
			}
		}
	}

	// And a second replacement, in case macros added <URL> again.
	$buffer = str_replace('<URL>', $scripturl, $buffer);

	if (isset($context['ob_replacements']))
		$buffer = str_replace(array_keys($context['ob_replacements']), array_values($context['ob_replacements']), $buffer);

	// Load cached membergroup colors.
	if (($member_colors = cache_get_data('member-colors', 5000)) === null)
	{
		$member_colors = array('group' => array(), 'color' => array());
		$request = wesql::query('
			SELECT m.id_member, m.id_post_group, g.id_group, g.online_color
			FROM {db_prefix}members AS m
			INNER JOIN {db_prefix}membergroups AS g ON g.online_color != {string:blank}
				AND ((m.id_group = g.id_group) OR (m.id_post_group = g.id_group))',
			array(
				'blank' => '',
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (empty($row['online_color']))
				continue;
			if (empty($member_colors['group'][$row['id_member']]) || $row['id_group'] !== $row['id_post_group'])
				$member_colors['group'][$row['id_member']] = $row['id_group'];
			$member_colors['color'][$row['id_group']] = $row['online_color'];
		}
		wesql::free_result($request);
		cache_put_data('member-colors', $member_colors, 5000);
	}

	// If guests/users can't view user profiles, we might as well unlink them!
	if (!allowedTo('profile_view_any'))
		$buffer = preg_replace(
			'~<a\b[^>]+href="' . $preg_scripturl . '\?(?:[^"]+)?\baction=profile' . (!we::$is_guest && allowedTo('profile_view_own') ? ';(?:[^"]+;)?u=(?!' . we::$id . ')' : '') . '[^"]*"[^>]*>(.*?)</a>~',
			'$1', $buffer
		);
	// Now we'll color profile links based on membergroup.
	else
		$buffer = preg_replace_callback(
			'~<a\b([^>]+href="' . $preg_scripturl . '\?(?:[^"]+)?\baction=profile;(?:[^"]+;)?u=(\d+)"[^>]*)>(.*?)</a>~',
			'wedge_profile_colors', $buffer
		);

	// Separate topic names from topic prefixes.
	$buffer = preg_replace(
		'~(<a\b[^>]+href="' . $preg_scripturl . '\?(?:[^"]+)?\btopic=\d+[^"]*"[^>]*>)(Re:\s)?((?:\[[^]<>]*]\s*)+)(.+?</a>)~',
		'$3$1$2$4', $buffer
	);

	// If the session is not cookied, or they are a crawler, add the session ID to all URLs.
	if (empty($_COOKIE) && SID != '' && empty($context['no_sid_thank_you']) && !we::$browser['possibly_robot'])
	{
		$buffer = preg_replace('~(?<!<link rel="canonical" href=")' . $preg_scripturl . '(?!\?' . preg_quote(SID, '~') . ')(?:\?|(?="))~', $scripturl . '?' . SID . ';', $buffer);
		$buffer = str_replace('"' . $scripturl . '?' . SID . ';"', '"' . $scripturl . '?' . SID . '"', $buffer);
	}
	// Debugging templates, are we?
	elseif (isset($_GET['debug']))
		$buffer = preg_replace('~(?<!<link rel="canonical" href=")"' . $preg_scripturl . '\??~', $scripturl . '?debug;', $buffer);

	// Rewrite the buffer with pretty URLs!
	if (!empty($settings['pretty_enable_filters']))
	{
		$use_cache = !empty($settings['pretty_enable_cache']);
		$session_var = $context['session_query'];

		// Find all URLs in the buffer
		// !! If you want to be stricter, start with this instead: '~(?<=(?:<a[^>]+href=|<link[^>]+href=|<img[^>]+?src=|<form[^>]+?action=)["\'>])'
		$context['pretty']['patterns'][] =  '~(?<=["\'>])' . $preg_scripturl . '([?;&](?:[^"\'#]*?[;&])?(board|topic|action|category)=[^"\'<#]+)~';
		$urls_query = array();
		$uncached_urls = array();

		// Making sure we don't execute patterns twice.
		$context['pretty']['patterns'] = array_flip(array_flip($context['pretty']['patterns']));

		foreach ($context['pretty']['patterns'] as $pattern)
		{
			preg_match_all($pattern, $buffer, $matches);
			foreach ($matches[1] as $match)
			{
				// Rip out everything that shouldn't be cached
				// !!! base64_encode sometimes finishes strings with '=' gaps IIRC. Should check whether they're not going to be ripped by this.
				// !!! Also, '~=?;+~' (in various places) should probably be rewritten as '~=;+|;{2,}~'
				if ($use_cache)
				{
					$match = str_replace(SID ? array(SID, $session_var) : $session_var, '', $match);
					$match = preg_replace(array('~=?;+~', '~\?&amp;~', '~[?;=]+$~'), array(';', '?', ''), $match);
				}
				else
					// !!! This can easily be optimized into a simple str_replace by adding placeholders for ^ and $.
					$match = preg_replace(array('~=?;+~', '~[?;=]+$~'), array(';', ''), $match);
				$match = str_replace(array('"', '?;'), array('%22', '?'), $match);
				$url_id = $match;
				$urls_query[] = $url_id;
				$uncached_urls[$match] = array(
					'url' => $match,
				);
			}
		}

		// Proceed only if there are actually URLs in the page
		if (count($urls_query) != 0)
		{
			// Eliminate duplicate URLs.
			$urls_query = array_keys(array_flip($urls_query));

			// Retrieve cached URLs
			$cached_urls = array();

			if ($use_cache)
			{
				$query = wesql::query('
					SELECT url_id, replacement
					FROM {db_prefix}pretty_urls_cache
					WHERE url_id IN ({array_string:urls})
						AND log_time > ' . (int) (time() - 86400),
					array(
						'urls' => $urls_query
					)
				);
				while ($row = wesql::fetch_assoc($query))
				{
					$cached_urls[$row['url_id']] = $row['replacement'];
					unset($uncached_urls[$row['url_id']]);
				}
				wesql::free_result($query);
			}

			// If there are any uncached URLs, process them
			if (count($uncached_urls) != 0)
			{
				// Run each filter callback function on each URL
				loadSource('PrettyUrls-Filters');

				foreach ($settings['pretty_filters'] as $id => $enabled)
				{
					$func = 'pretty_filter_' . $id;
					if ($enabled)
						$func($uncached_urls);
				}

				// Fill the cached URLs array
				$cache_data = array();
				foreach ($uncached_urls as $url_id => $url)
				{
					if (!isset($url['replacement']))
						$url['replacement'] = $url['url'];
					$url['replacement'] = str_replace(chr(18), "'", $url['replacement']);
					$url['replacement'] = preg_replace(array('~"~', '~=?;+~', '~\?;~', '~[?;=]+$~'), array('%22', ';', '?', ''), $url['replacement']);
					$cached_urls[$url_id] = $url['replacement'];
					if ($use_cache && strlen($url_id) < 256)
						$cache_data[] = '(\'' . $url_id . '\', \'' . addslashes($url['replacement']) . '\')';
				}

				// Cache these URLs in the database (use mysql_query to avoid some issues.)
				if (count($cache_data) > 0)
					mysql_query("REPLACE INTO {$db_prefix}pretty_urls_cache (url_id, replacement) VALUES " . implode(', ', $cache_data));
			}

			// Put the URLs back into the buffer
			foreach ($context['pretty']['patterns'] as $pattern)
				$buffer = preg_replace_callback($pattern, 'pretty_buffer_callback', $buffer);
		}
	}

	if (!empty($context['debugging_info']))
		$buffer = substr_replace($buffer, $context['debugging_info'], strrpos($buffer, '</body>'), 0);

	// Update the load times
	if (!empty($context['show_load_time']))
	{
		$new_load_time = microtime(true);
		$loadTime = $txt['page_created'] . sprintf($txt['seconds_with_' . ($db_count > 1 ? 'queries' : 'query')], $new_load_time - $time_start, $db_count);
		$queriesDiff = $db_count - $old_db_count;
		if (we::$is_admin)
			$loadTime .= '</li>
			<li class="rd">(' . $txt['dynamic_replacements'] . ': ' . sprintf($txt['seconds_with_' . ($queriesDiff > 1 ? 'queries' : 'query')], $new_load_time - $old_load_time, $queriesDiff) . ')';
		$buffer = str_replace('<!-- insert stats here -->', $loadTime, $buffer);
	}

	// Have we got any indentation adjustments to do...?
	$max_loops = 100;
	while (strpos($buffer, '<inden@zi=') !== false && $max_loops-- > 0)
		$buffer = preg_replace_callback('~<inden@zi=([^=>]+)=(-?\d+)>(.*?)</inden@zi=\\1>~s', 'wedge_indenazi', $buffer);

	// The following hidden variable, 'minify_html', will remove tabs and thus please Google PageSpeed. Whatever.
	if (!empty($settings['minify_html']))
		$buffer = preg_replace("~\n\t+~", "\n", $buffer);

	// Return the changed buffer, and make a final optimization.
	return preg_replace("~\n// ]]></script>\n*<script><!-- // --><!\[CDATA\[~", '', $buffer);
}

// Move inline events to the end
function wedge_event_delayer($match)
{
	global $context;
	static $eve = 1, $dupes = array();

	$eve_list = array();
	preg_match_all('~\son(\w+)="([^"]+)"~', $match[0], $insides, PREG_SET_ORDER);
	foreach ($insides as $inside)
	{
		$match[0] = str_replace($inside[0], '', $match[0]);
		$dupe = serialize($inside);
		if (!isset($dupes[$dupe]))
		{
			// Build the inline event array. Because inline events are more of a hassle to work with, we replace &quot; with
			// double quotes, because that's how " is shown in an inline event to avoid conflicting with the surrounding quotes.
			// !!! @todo: maybe &amp; should be turned into &, too.
			$context['delayed_events'][$eve] = array($eve, $inside[1], str_replace(array('&quot;', '\\\\n'), array('"', '\\n'), $inside[2]));
			$dupes[$dupe] = $eve;
			$eve_list[] = $eve++;
		}
		else
			$eve_list[] = $dupes[$dupe];
	}
	return rtrim($match[0], ' />') . ' data-eve="' . implode(' ', $eve_list) . '">';
}

function wedge_profile_colors($match)
{
	global $member_colors;

	if (!isset($member_colors['group'][$match[2]]) || strpos($match[1], 'bbc_link') !== false)
		return '<a' . $match[1] . '>' . $match[3] . '</a>';
	return '<a' . $match[1] . ' style="color: ' . $member_colors['color'][$member_colors['group'][$match[2]]] . '">' . $match[3] . '</a>';
}

function wedge_indenazi($match)
{
	if ($match[2] < 0)
		return preg_replace('~(\n\t*)' . str_repeat("\t", -$match[2]) . '(?=<)~', '$1', $match[3]);
	return preg_replace('~(\n\t*)(?=<)~', '$1' . str_repeat("\t", $match[2]), $match[3]);
}

// A callback function to replace the buffer's URLs with their cached URLs
function pretty_buffer_callback($matches)
{
	global $cached_urls, $scripturl, $use_cache, $session_var;
	static $immediate_cache = array();

	if (isset($immediate_cache[$matches[0]]))
		return $immediate_cache[$matches[0]];

	if ($use_cache)
	{
		// Store the parts of the URL that won't be cached so they can be inserted later
		$has_sid = SID && strpos($matches[1], SID) !== false;
		$has_sesc = strpos($matches[1], $session_var) !== false;
		$url_id = rtrim(
			preg_replace(
				'~=?;+~',
				';',
				str_replace(
					array('"', '?;', SID, $session_var),
					array('%22', '?', '', ''),
					$matches[1]
				)
			),
			'&?;='
		);
		// Stitch everything back together
		$replacement = isset($cached_urls[$url_id]) ? $cached_urls[$url_id] : $url_id;
		if ($has_sid)
			$replacement .= (strpos($replacement, '?') === false ? '?' : ';') . SID;
		if ($has_sesc)
			$replacement .= (strpos($replacement, '?') === false ? '?' : ';') . $session_var;
	}
	else
	{
		// Rip out everything that won't have been cached
		$url_id = rtrim(str_replace(array('"', '?;'), array('%22', '?'), preg_replace('~=?;+~', ';', $matches[1])), '&?;=');
		// Stitch everything back together
		$replacement = isset($cached_urls[$url_id]) ? $cached_urls[$url_id] : $url_id;
	}

	$immediate_cache[$matches[0]] = $replacement;
	if (empty($replacement) || $replacement[0] == '?')
		$replacement = $scripturl . $replacement;
	return $replacement;
}

// A helper function for plugins to easily add simple output buffer replacements.
function add_replacement($from, $to)
{
	global $context;
	if (!isset($context['ob_replacements']))
		$context['ob_replacements'] = array();
	$context['ob_replacements'][$from] = $to;
}

/**
 * Cleans (or restarts) the output buffer process.
 * Starts by flushing the buffers, then resets the gzip handler
 * (if enabled), then resets the Pretty URLs handler (if needed).
 */
function clean_output($skip_full = false)
{
	global $settings;

	ob_end_clean();
	if (!empty($settings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		ob_start();

	// We may also want to trigger our dynamic rewrites...
	if (!$skip_full && !empty($settings['pretty_enable_filters']))
		ob_start('ob_sessrewrite');
}

/**
 * Ensures content above the main page content is loaded, including HTTP page headers.
 *
 * Several things happen here.
 * - {@link setupThemeContext()} is called to get some key values.
 * - Issue HTTP headers that cause browser-side caching to be turned off (old expires and last modified). This is turned off for attachments errors, though.
 * - Issue MIME type header
 * - If the settings dictate it so, update the theme settings to use the default images and path.
 */
function start_output()
{
	global $settings, $context, $theme;

	if (!isset($_REQUEST['xml']))
		setupThemeContext();

	// Print stuff to prevent caching of pages (except on attachment errors, etc.)
	if (empty($context['no_last_modified']))
	{
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

		if (!isset($_REQUEST['xml']))
			header('Content-Type: text/html; charset=UTF-8');
	}

	header('Content-Type: text/' . (isset($_REQUEST['xml']) ? 'xml' : 'html') . '; charset=UTF-8');

	$context['show_load_time'] = !empty($settings['timeLoadPageEnable']);

	if (isset($theme['use_default_images'], $theme['default_template']) && $theme['use_default_images'] == 'defaults')
	{
		$theme['theme_url'] = $theme['default_theme_url'];
		$theme['images_url'] = $theme['default_images_url'];
		$theme['theme_dir'] = $theme['default_theme_dir'];
	}
}

/**
 * Use the opportunity to show some potential errors while we're showing the top or default layer...
 *
 * - If using a conventional theme (with body or main layers), and the user is an admin, check whether certain files are present, and if so give the admin a warning. These include the installer, repair-settings and backups of the Settings files (with php~ extensions)
 * - If the user is post-banned, provide a nice warning for them.
 */
function while_we_re_here()
{
	global $txt, $settings, $context, $boarddir, $cachedir;
	static $checked_security_files = false, $showed_banned = false, $showed_behav_error = false;

	// If this page was loaded through jQuery, it's likely we've already had the warning shown in its container...
	if ($context['is_ajax'])
		return;

	// May seem contrived, but this is done in case the body and default layer aren't there...
	// Was there a security error for the admin?
	if (!$showed_behav_error && $context['user']['is_admin'] && !empty($context['behavior_error']))
	{
		$showed_behav_error = true;
		loadLanguage('Security');

		echo '
			<div class="errorbox">
				<p class="alert">!!</p>
				<h3>', $txt['behavior_admin'], '</h3>
				<p>', $txt[$context['behavior_error'] . '_log'], '</p>
			</div>';
	}
	elseif (!$checked_security_files && !we::$is_guest && allowedTo('admin_forum'))
	{
		$checked_security_files = true;
		$security_files = array('import.php', 'install.php', 'webinstall.php', 'upgrade.php', 'convert.php', 'repair_paths.php', 'repair_settings.php', 'Settings.php~', 'Settings_bak.php~');

		foreach ($security_files as $i => $security_file)
			if (!file_exists($boarddir . '/' . $security_file))
				unset($security_files[$i]);

		if (!empty($security_files) || (!empty($settings['cache_enable']) && !is_writable($cachedir)))
		{
			loadLanguage('Errors');

			echo '
			<div class="errorbox">
				<p class="alert">!!</p>
				<h3>', empty($security_files) ? $txt['cache_writable_head'] : $txt['security_risk'], '</h3>
				<p>';

			foreach ($security_files as $security_file)
			{
				echo '
					', sprintf($txt['not_removed'], $security_file), '<br>';

				if ($security_file == 'Settings.php~' || $security_file == 'Settings_bak.php~')
					echo '
					', sprintf($txt['not_removed_extra'], $security_file, substr($security_file, 0, -1)), '<br>';
			}

			if (!empty($settings['cache_enable']) && !is_writable($cachedir))
				echo '
					<strong>', $txt['cache_writable'], '</strong><br>';

			echo '
				</p>
			</div>';
		}
	}
	// If the user is banned from posting, inform them of it.
	elseif (!$showed_banned && isset($_SESSION['ban']['cannot_post']))
	{
		$showed_banned = true;
		echo '
			<div class="windowbg wrc alert" style="margin: 2ex; padding: 2ex; border: 2px dashed red">
				', sprintf($txt['you_are_post_banned'], we::$is_guest ? $txt['guest_title'] : we::$user['name']);

		if (!empty($_SESSION['ban']['cannot_post']['reason']))
			echo '
				<div style="padding-left: 4ex; padding-top: 1ex">', $_SESSION['ban']['cannot_post']['reason'], '</div>';

		if (!empty($_SESSION['ban']['expire_time']))
			echo '
				<div>', sprintf($txt['your_ban_expires'], timeformat($_SESSION['ban']['expire_time'], false)), '</div>';
		else
			echo '
				<div>', $txt['your_ban_expires_never'], '</div>';

		echo '
			</div>';
	}
}

/**
 * Display the debug data at the foot of the page if debug mode ($db_show_debug) is set to boolean true (only) and not in the query viewer page.
 *
 * Lots of interesting debug information is collated through workflow and displayed in this function, called from the footer.
 * - Check if the current user is on the list of people who can see the debug (and query debug) information, and clear information if not appropriate.
 * - Clean up a list of things that might not have been initialized this page, especially if heavily caching.
 * - Get the list of included files, and strip out the long paths to the board dir, replacing with a . for "current directory"; also collate the size of included files.
 * - Examine the DB query cache, and see if any warnings have been issued from queries.
 * - Grab the page content, and remove the trailing ending of body and html tags, so the footer information can replace them (and still leave legal HTML)
 * - Output the list of included templates, blocks, language files, properly included (through loadTemplate) stylesheets, and master list of files.
 * - If caching is enabled, also include the list of cache items included, how much data was loaded and how long was spent on caching retrieval.
 * - Additionally, if we already have a list of queries in session (i.e. the query list is expanded), display that too, stripping out ones that we can't send for EXPLAIN.
 * - Finally, clear cached language files.
 */
function db_debug_junk()
{
	global $context, $scripturl, $boarddir, $settings, $txt;
	global $db_cache, $db_count, $db_show_debug, $cache_count, $cache_hits;

	// Is debugging on? (i.e. it is set, and it is true, and we're not on action=viewquery or an help popup.
	$show_debug = isset($db_show_debug) && $db_show_debug === true && (!isset($_GET['action']) || ($_GET['action'] != 'viewquery' && $_GET['action'] != 'help'));

	// Check groups
	if (empty($settings['db_show_debug_who']) || $settings['db_show_debug_who'] == 'admin')
		$show_debug &= $context['user']['is_admin'];
	elseif ($settings['db_show_debug_who'] == 'mod')
		$show_debug &= allowedTo('moderate_forum');
	elseif ($settings['db_show_debug_who'] == 'regular')
		$show_debug &= $context['user']['is_logged'];
	else
		$show_debug &= ($settings['db_show_debug_who'] == 'any');

	// Now, who can see the query log? Need to have the ability to see any of this anyway.
	$show_debug_query = $show_debug;
	if (empty($settings['db_show_debug_who_log']) || $settings['db_show_debug_who_log'] == 'admin')
		$show_debug_query &= $context['user']['is_admin'];
	elseif ($settings['db_show_debug_who_log'] == 'mod')
		$show_debug_query &= allowedTo('moderate_forum');
	elseif ($settings['db_show_debug_who_log'] == 'regular')
		$show_debug_query &= $context['user']['is_logged'];
	else
		$show_debug_query &= ($settings['db_show_debug_who_log'] == 'any');

	// Now, let's tidy this up. If we're not showing queries, make sure anything that was logged is gone.
	if (!$show_debug_query)
	{
		unset($_SESSION['debug'], $db_cache);
		$_SESSION['view_queries'] = 0;
	}
	if (!$show_debug)
		return;

	if (empty($_SESSION['view_queries']))
		$_SESSION['view_queries'] = 0;
	if (empty($context['debug']['language_files']))
		$context['debug']['language_files'] = array();
	if (empty($context['debug']['sheets']))
		$context['debug']['sheets'] = array();

	$files = get_included_files();
	$total_size = 0;
	for ($i = 0, $n = count($files); $i < $n; $i++)
	{
		if (file_exists($files[$i]))
			$total_size += filesize($files[$i]);
		$files[$i] = strtr($files[$i], array($boarddir => '.'));
	}

	$warnings = 0;
	if (!empty($db_cache))
	{
		foreach ($db_cache as $q => $qq)
			if (!empty($qq['w']))
				$warnings += count($qq['w']);

		$_SESSION['debug'] =& $db_cache;
	}

	$show_list_js = "$(this).hide().next().show(); return false;";
	$temp = '
<div class="smalltext" style="text-align: left; margin: 1ex">' . sprintf($txt['debug_report'],
		count($context['debug']['templates']),		implode(', ', $context['debug']['templates']),
		count($context['debug']['blocks']),			implode(', ', $context['debug']['blocks']),
		count($context['debug']['language_files']),	implode(', ', $context['debug']['language_files']),
		count($context['debug']['sheets']),			implode(', ', $context['debug']['sheets']),
		count($files), round($total_size / 1024), $show_list_js, implode(', ', $files),
		ceil(memory_get_peak_usage() / 1024)
	);

	if (!empty($settings['cache_enable']) && !empty($cache_hits))
	{
		$entries = array();
		$total_t = 0;
		$total_s = 0;
		foreach ($cache_hits as $cache_hit)
		{
			$entries[] = sprintf($txt['debug_cache_seconds_bytes'], $cache_hit['d'] . ' ' . $cache_hit['k'], comma_format($cache_hit['t'], 5), $cache_hit['s']);
			$total_t += $cache_hit['t'];
			$total_s += $cache_hit['s'];
		}
		$temp .= sprintf($txt['debug_cache_hits'], $cache_count, comma_format($total_t, 5), comma_format($total_s), $show_list_js, implode(', ', $entries));
	}

	if ($show_debug_query)
		$temp .= '<a href="' . $scripturl . '?action=viewquery" target="_blank" class="new_win">' . sprintf($txt['debug_queries_used' . ($warnings == 0 ? '' : '_and_warnings')], $db_count, $warnings) . '</a><br><br>';
	else
		$temp .= sprintf($txt['debug_queries_used'], $db_count) . '<br><br>';

	if ($_SESSION['view_queries'] == 1 && !empty($db_cache))
		foreach ($db_cache as $q => $qq)
		{
			$is_select = substr(trim($qq['q']), 0, 6) == 'SELECT' || preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+SELECT .+$~s', trim($qq['q'])) != 0;
			// Temporary tables created in earlier queries are not explainable.
			if ($is_select)
			{
				foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
					if (strpos(trim($qq['q']), $tmp) !== false)
					{
						$is_select = false;
						break;
					}
			}
			// But actual creation of the temporary tables are.
			elseif (preg_match('~^CREATE TEMPORARY TABLE .+?SELECT .+$~s', trim($qq['q'])) != 0)
				$is_select = true;

			// Make the filenames look a bit better.
			if (isset($qq['f']))
				$qq['f'] = preg_replace('~^' . preg_quote($boarddir, '~') . '~', '...', $qq['f']);

			$temp .= '
	<strong>' . ($is_select ? '<a href="' . $scripturl . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '" target="_blank" class="new_win" style="text-decoration: none">' : '') . westr::nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', htmlspecialchars(ltrim($qq['q'], "\n\r")))) . ($is_select ? '</a></strong>' : '</strong>') . '<br>
	&nbsp;&nbsp;&nbsp;';
			if (!empty($qq['f']) && !empty($qq['l']))
				$temp .= sprintf($txt['debug_query_in_line'], $qq['f'], $qq['l']);

			if (isset($qq['s'], $qq['t'], $txt['debug_query_which_took_at']))
				$temp .= sprintf($txt['debug_query_which_took_at'], round($qq['t'], 8), round($qq['s'], 8)) . '<br>';
			elseif (isset($qq['t']))
				$temp .= sprintf($txt['debug_query_which_took'], round($qq['t'], 8)) . '<br>';
			$temp .= '<br>';
		}

	if ($show_debug_query)
		$temp .= '<a href="' . $scripturl . '?action=viewquery;sa=hide">' . $txt['debug_' . (empty($_SESSION['view_queries']) ? 'show' : 'hide') . '_queries'] . '</a>';

	$context['debugging_info'] = $temp . '
</div>';
}

/**
 * Manage the process of loading a template file. This should not normally be called directly (instead, use {@link loadTemplate()} which invokes this function)
 *
 * This function ultimately handles the physical loading of a template or language file, and if $settings['disableTemplateEval'] is off, it also loads it in such a way as to parse it first - to be able to produce a different output to highlight where the error is (which is also not cached)
 *
 * @param string $filename The full path of the template to be loaded.
 * @param bool $once Whether to check that this template is uniquely loaded (for some templates, workflow dictates that it can be loaded only once, so passing it to require_once is an unnecessary performance hurt)
 */
function template_include($filename, $once = false)
{
	global $context, $theme, $txt, $helptxt, $scripturl, $settings;
	global $boardurl, $boarddir, $maintenance, $mtitle, $mmessage;
	static $templates = array();

	// We want to be able to figure out any errors...
	@ini_set('track_errors', '1');

	// Don't include the file more than once, if $once is true.
	if ($once && in_array($filename, $templates))
		return;
	// Add this file to the include list, whether $once is true or not.
	else
		$templates[] = $filename;

	// Are we going to use eval?
	if (empty($settings['disableTemplateEval']))
	{
		$file_found = file_exists($filename) && eval('?' . '>' . rtrim(file_get_contents($filename))) !== false;
		$theme['current_include_filename'] = $filename;
	}
	else
	{
		$file_found = file_exists($filename);

		if ($once && $file_found)
			require_once($filename);
		elseif ($file_found)
			require($filename);
	}

	if ($file_found !== true)
	{
		clean_output();

		// Don't cache error pages!!
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		if (!isset($txt['template_parse_error']))
			loadLanguage('Errors', '', false);

		if (!isset($txt['template_parse_error']))
		{
			$txt['template_parse_error'] = 'Template Parse Error!';
			$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system. This problem should only be temporary, so please come back later and try again. If you continue to see this message, please contact the administrator.<br><br>You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
			$txt['template_parse_error_details'] = 'There was a problem loading the <tt><strong>%1$s</strong></tt> template or language file. Please check the syntax and try again - remember, single quotes (<tt>\'</tt>) often have to be escaped with a slash (<tt>\\</tt>). To see more specific error information from PHP, try <a href="{board_url}%1$s" class="extern">accessing the file directly</a>.<br><br>You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="<URL>?theme=1">use the default theme</a>.';
		}

		$txt['template_parse_error_details'] = str_replace('{board_url}', $boardurl, $txt['template_parse_error_details']);

		// First, let's get the doctype and language information out of the way.
		echo '<!DOCTYPE html>
<html', !empty($context['right_to_left']) ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="utf-8">';

		if (!empty($maintenance) && !allowedTo('admin_forum'))
			echo '
		<title>', $mtitle, '</title>
	</head>
	<body>
		<h3>', $mtitle, '</h3>
		', $mmessage, '
	</body>
</html>';
		elseif (!allowedTo('admin_forum'))
			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', $txt['template_parse_error_message'], '
	</body>
</html>';
		else
		{
			loadSource('Class-WebGet');
			$weget = new weget($boardurl . strtr($filename, array($boarddir => '', strtr($boarddir, '\\', '/') => '')));
			$error = $weget->get();

			if (empty($error))
				$error = isset($php_errormsg) ? $php_errormsg : '';

			$error = strtr($error, array('<b>' => '<strong>', '</b>' => '</strong>'));

			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', sprintf($txt['template_parse_error_details'], strtr($filename, array($boarddir => '', strtr($boarddir, '\\', '/') => '')));

			if (!empty($error))
				echo '
		<hr>
		<div style="margin: 0 20px"><tt>', strtr(strtr($error, array('<strong>' . $boarddir => '<strong>...', '<strong>' . strtr($boarddir, '\\', '/') => '<strong>...')), '\\', '/'), '</tt></div>';

			// Yes, this is VERY complicated... Still, it's good.
			if (preg_match('~ <strong>(\d+)</strong><br\s*/?\>$~i', $error, $match) != 0)
			{
				$data = file($filename);
				$data2 = highlight_php_code(implode('', $data));
				$data2 = preg_split('~\<br\s*/?\>~', $data2);

				// Fix the PHP code stuff...
				$data2 = str_replace('<span class="bbc_pre">' . "\t" . '</span>', "\t", $data2);

				// Now we get to work around a bug in PHP where it doesn't escape <br>s!
				$j = -1;
				foreach ($data as $line)
				{
					$j++;

					if (substr_count($line, '<br>') == 0)
						continue;

					$n = substr_count($line, '<br>');
					for ($i = 0; $i < $n; $i++)
					{
						$data2[$j] .= '&lt;br&gt;' . $data2[$j + $i + 1];
						unset($data2[$j + $i + 1]);
					}
					$j += $n;
				}
				$data2 = array_values($data2);
				array_unshift($data2, '');

				echo '
		<div style="margin: 2ex 20px"><div style="width: 100%; overflow: auto"><pre style="margin: 0">';

				// Figure out what the color coding was before...
				$line = max($match[1] - 9, 1);
				$last_line = '';
				for ($line2 = $line - 1; $line2 > 1; $line2--)
					if (strpos($data2[$line2], '<') !== false)
					{
						if (preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line2], $color_match) != 0)
							$last_line = $color_match[1];
						break;
					}

				// Show the relevant lines...
				for ($n = min($match[1] + 4, count($data2) + 1); $line <= $n; $line++)
				{
					if ($line == $match[1])
						echo '</pre><div style="background-color: #ffb0b5"><pre style="margin: 0">';

					echo '<span style="color: black">', sprintf('%' . strlen($n) . 's', $line), ':</span> ';
					if (isset($data2[$line]) && $data2[$line] != '')
						echo substr($data2[$line], 0, 2) == '</' ? preg_replace('~^</[^>]+>~', '', $data2[$line]) : $last_line . $data2[$line];

					if (isset($data2[$line]) && preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line], $color_match) != 0)
					{
						$last_line = $color_match[1];
						echo '</', substr($last_line, 1, 4), '>';
					}
					elseif ($last_line != '' && strpos($data2[$line], '<') !== false)
						$last_line = '';
					elseif ($last_line != '' && $data2[$line] != '')
						echo '</', substr($last_line, 1, 4), '>';

					if ($line == $match[1])
						echo '</pre></div><pre style="margin: 0">';
					else
						echo "\n";
				}

				echo '</pre></div></div>';
			}

			echo '
	</body>
</html>';
		}

		exit;
	}
}

/**
 * Loads a named template file for later use, and/or one or more stylesheets to be used with that template.
 *
 * The function can be used to load just stylesheets as well as loading templates; neither is required for the other to operate. Both templates and stylesheets loaded here will be logged if full debugging is on.
 *
 * @param mixed $template_name Name of a template file to load from the current theme's directory (with .template.php suffix), falling back to locating it in the default theme directory. Alternatively if loading stylesheets only, supply boolean false instead.
 * @param bool $fatal Whether to exit execution with a fatal error if the template file could not be loaded. (Note: this is never used in the Wedge code base.)
 * @return bool Returns true on success, false on failure (assuming $fatal is false; otherwise the fatal error will suspend execution)
 */
function loadTemplate($template_name, $fatal = true)
{
	global $context, $theme, $txt, $scripturl, $boarddir, $db_show_debug;

	// No template to load?
	if ($template_name === false)
		return true;

	$loaded = false;
	foreach ($theme['template_dirs'] as $template_dir)
	{
		if (file_exists($template_dir . '/' . $template_name . '.template.php'))
		{
			$loaded = true;
			template_include($template_dir . '/' . $template_name . '.template.php', true);
			break;
		}
	}

	if ($loaded)
	{
		if ($db_show_debug === true)
			$context['debug']['templates'][] = $template_name . ' (' . basename($template_dir) . ')';

		// If they have specified an initialization function for this template, go ahead and call it now.
		if (function_exists('template_' . $template_name . '_init'))
			call_user_func('template_' . $template_name . '_init');
	}
	// Hmmm... doesn't exist?! I don't suppose the directory is wrong, is it?
	elseif (!file_exists($theme['default_theme_dir']) && file_exists($boarddir . '/Themes/default'))
	{
		$theme['default_theme_dir'] = $boarddir . '/Themes/default';
		$theme['template_dirs'][] = $theme['default_theme_dir'];

		if (!empty($context['user']['is_admin']) && !isset($_GET['th']))
		{
			loadLanguage('Errors');
			echo '
<div class="alert errorbox">
	<a href="', $scripturl . '?action=admin;area=theme;sa=settings;th=1;' . $context['session_query'], '" class="alert">', $txt['theme_dir_wrong'], '</a>
</div>';
		}

		loadTemplate($template_name);
	}
	// Cause an error otherwise.
	elseif ($fatal)
	{
		if ($template_name != 'Errors' && $template_name != 'index')
			fatal_lang_error('theme_template_error', 'template', array((string) $template_name));
		else
			exit(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load Themes/default/%s.template.php!', (string) $template_name), 'template'));
	}
	else
		return false;
}

/**
 * Actually display a template block.
 *
 * This is called by the wetem object to actually have content output to the buffer; this directs which template_ functions are called, including logging them for debugging purposes.
 *
 * Additionally, if debug is part of the URL (?debug or ;debug), there will be divs added for administrators to mark where template layers begin and end, with orange background and red borders.
 *
 * @param string $block_name The name of the function (without template_ prefix) to be called.
 * @param mixed $fatal Whether to exit fatally on a template not being available; if passed as boolean false, it is a fatal error through the usual template layers and including forum header. Also accepted is the string 'ignore' which means to skip the error; otherwise end execution with a basic text error message.
 */
function execBlock($block_name, $fatal = false)
{
	global $context, $theme, $txt, $db_show_debug;

	if (empty($block_name))
		return;

	if ($db_show_debug === true)
		$context['debug']['blocks'][] = $block_name;

	if (strpos($block_name, ':') !== false)
	{
		list ($block_name, $vars) = explode(':', $block_name, 2);
		$vars = array_map('trim', explode(',', $vars));
	}
	else
		$vars = array();

	// Figure out what the template function is named.
	$theme_function = 'template_' . $block_name;

	// !!! Doing these tests is relatively slow, but there aren't that many. In case performance worsens,
	// !!! we should cache the function list (get_defined_functions()) and isset() against the cache.
	if (function_exists($theme_function_before = $theme_function . '_before'))
		call_user_func_array($theme_function_before, $vars);

	if (function_exists($theme_function_override = $theme_function . '_override'))
		call_user_func_array($theme_function_override, $vars);
	elseif (function_exists($theme_function))
		call_user_func_array($theme_function, $vars);
	elseif ($fatal === false)
		fatal_lang_error('template_block_error', 'template', array((string) $block_name));
	elseif ($fatal !== 'ignore')
		exit(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['template_block_error'] : 'Unable to load the "%s" template block!', (string) $block_name), 'template'));

	if (function_exists($theme_function_after = $theme_function . '_after'))
		call_user_func_array($theme_function_after, $vars);

	// Are we showing debugging for templates? Just make sure not to do it before the doctype...
	if (allowedTo('admin_forum') && isset($_REQUEST['debug']) && $block_name !== 'init' && ob_get_length() > 0 && !isset($_REQUEST['xml']))
		echo '
<div style="font-size: 8pt; border: 1px dashed red; background: orange; text-align: center; font-weight: bold">---- ', $block_name, ' ends ----</div>';
}


/*************
 *
 * This is a helper class that holds a template layer or block.
 *
 * It is provided to allow plugins to use method chaining on a single item. If you don't need
 * to chain calls, then use the static methods in the wetem object.
 *
 * You can access an item by using wetem::get('item'), and then you would apply calls to it.
 * For instance, if you wanted to get block sidebar_dummy's parent, rename it to sidebar2
 * and insert a new layer into it, you may want to do it this way:
 *
 * wetem::get('sidebar_dummy')->parent()->rename('sidebar2')->inner('inside_sidebar');
 *
 *************/

final class wetemItem
{
	private $target;

	function __construct($to = '')
	{
		if (!$to)
			$to = 'default';
		$this->target = $to;
	}

	// Remove specified layer/block from the skeleton. (Non-chainable)
	function remove()
	{
		wetem::remove($this->target);
	}

	// The following are chainable aliases to the equivalent wetem:: functions.
	function load($items)		{ wetem::load($this->target, $items); return $this; }
	function replace($items)	{ wetem::replace($this->target, $items); return $this; }
	function add($items)		{ wetem::add($this->target, $items); return $this; }
	function first($items)		{ wetem::first($this->target, $items); return $this; }
	function before($items)		{ wetem::before($this->target, $items); return $this; }
	function after($items)		{ wetem::after($this->target, $items); return $this; }
	function move($layer, $p)	{ wetem::move($this->target, $layer, $p); return $this; }
	function rename($layer)		{ wetem::rename($this->target, $layer); return $this; }
	function outer($layer)		{ wetem::outer($this->target, $layer); return $this; }
	function inner($layer)		{ wetem::inner($this->target, $layer); return $this; }
	function parent()			{ return wetem::get(wetem::parent($this->target)); }
}

/*************
 *
 * This is the template object.
 *
 * It is used to manage the skeleton array that holds
 * all of the layers and blocks in the template.
 *
 * The skeleton itself can't be accessed from outside the object.
 * Thus, you'll have to rely on the public functions to manipulate it.
 *
 * wetem::load()	- load a block ('block_name'), layer (array('layer_name' => array())) or array of these *into* a given layer
 * wetem::replace()	- same, but deletes existing sub-layers in the process
 * wetem::add()		- same, but adds data to given layer
 * wetem::first()	- same, but prepends data to given layer
 * wetem::before()	- same, but inserts data *before* given layer or block
 * wetem::after()	- same, but inserts data *after* given layer or block
 * wetem::move()	- moves an existing block or layer to another position in the skeleton
 *
 * wetem::layer()	- various layer creation functions (see documentation for the function)
 * wetem::rename()	- rename an existing layer
 * wetem::outer()	- wrap a new outer layer around the given layer
 * wetem::inner()	- inject a new inner layer directly below the given layer
 * wetem::remove()	- remove a block or layer from the skeleton
 *
 * wetem::hide()	- erase the skeleton and replace it with a simple structure (template-less pages)
 *
 * wetem::parent()	- return the name of the block/layer's parent layer
 * wetem::get()		- see wetemItem description. If you only have one action to apply, avoid using it.
 * wetem::has()		- does the skeleton have this block or layer in it?
 *					  - ::has_block($block) forces a test for blocks only
 *					  - ::has_layer($layer) forces a test for layers only
 *
 *************/

final class wetem
{
	private static $instance;				// container for self
	private static $skeleton = array();		// store the full skeleton array
	private static $layers = array();		// store shortcuts to individual layers
	private static $opt = array();			// options for individual layers/block
	private static $obj = array();			// store shortcuts to individual layer/block objects
	private static $hidden = false;			// did we call hide()?

	// What kind of class are you, anyway? One of a kind!
	private function __clone()
	{
		return false;
	}

	// Bootstrap's bootstraps
	static function getInstance()
	{
		// Squeletto ergo sum
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	// Does the skeleton hold a specific layer or block?
	static function has($item)
	{
		return (bool) self::parent($item);
	}

	// Does the skeleton hold a specific block?
	static function has_block($block)
	{
		return !isset(self::$layers[$block]) && self::parent($block) !== false;
	}

	// Does the skeleton hold a specific layer?
	static function has_layer($layer)
	{
		return isset(self::$layers[$layer]);
	}

	/**
	 * Build the multi-dimensional layout skeleton array from an single-dimension array of tags.
	 */
	static function build(&$arr)
	{
		// Unset any pending layer objects.
		if (!empty(self::$obj))
			foreach (self::$obj as &$layer)
				$layer = null;

		self::parse($arr, self::$skeleton);
	}

	/**
	 * This is where we render the HTML page!
	 */
	static function render()
	{
		if (empty(self::$layers['default']))
			fatal_lang_error('default_layer_missing');

		self::render_recursive(reset(self::$skeleton), key(self::$skeleton));
	}

	/**
	 * Returns a wetemItem object representing the first layer or block we need.
	 *
	 * @param string $targets A layer or block, or array of layers or blocks to look for.
	 */
	static function get($targets = '')
	{
		$to = self::find($targets);
		// Not a valid block/layer? Return the default layer.
		// @todo: add a proper error message for this... Like, 'Not a valid layer or block!'
		if ($to === false)
			$to = 'default';
		if (!isset(self::$obj[$to]))
			self::$obj[$to] = new wetemItem($to);
		return self::$obj[$to];
	}

	/***********************************************************************************
	 *
	 * The following functions are available for plugins to manipulate LAYERS or BLOCKS.
	 *
	 ***********************************************************************************/

	// Add contents before the specified layer or block.
	static function before($target, $contents = '')
	{
		return wetem::op($contents, $target, 'before');
	}

	// Add contents after the specified layer or block.
	static function after($target, $contents = '')
	{
		return wetem::op($contents, $target, 'after');
	}

	/**
	 * Remove a block or layer from the skeleton.
	 * If done on a layer, it will only be removed if it doesn't contain the default layer.
	 * @todo: add option to remove only the layer, not its contents.
	 *
	 * @param string $item The name of the block or layer to remove.
	 */
	static function remove($target)
	{
		$layer = self::parent($target);
		// If it's a valid block, just remove it.
		if ($layer && !is_array(self::$layers[$layer][$target]))
			unset(self::$layers[$layer][$target]);
		// Otherwise it's a layer, make sure it's removable.
		elseif (isset(self::$layers[$layer]))
			self::remove_layer($target);
	}

	/**
	 * Move an existing block or layer to somewhere else in the skeleton.
	 *
	 * @param string $item The name of the block or layer to move.
	 * @param string $target The target block or layer.
	 * @param string $where The new position relative to the target: before, after, anything accepted by wetem::op.
	 */
	static function move($item, $target, $where)
	{
		if (!self::has($item) || !self::has($target))
			return false;

		if (isset(self::$layers[$item]))
		{
			$to_move = self::$layers[$item];
			unset(self::$layers[$item]);
		}
		else
		{
			$parent = self::parent($item);
			if (!$parent)
				return false;
			$to_move = self::$layers[$parent][$item];
			unset(self::$layers[$parent][$item]);
		}
		self::op(array($item => $to_move), $target, $where, true);
	}

	/**
	 * Find a block or layer's parent layer.
	 *
	 * @param string $child The name of the block or layer. Really.
	 * @return mixed Returns either the name of the parent layer, or FALSE if not found.
	 */
	static function parent($child)
	{
		foreach (self::$layers as $id => &$layer)
			if (isset($layer[$child]))
				return $id;

		return false;
	}

	/*************************************************************************
	 *
	 * The following functions are available for plugins to manipulate LAYERS.
	 *
	 *************************************************************************/

	// Replace specified layer's contents with our new contents. Leave its existing layers alone.
	static function load($target, $contents = '')
	{
		return wetem::op($contents, $target, 'load');
	}

	// Add contents inside specified layer, at the end. (jQuery equivalent: .append())
	static function add($target, $contents = '')
	{
		return wetem::op($contents, $target, 'add');
	}

	// Add contents inside specified layer, at the beginning. (jQuery equivalent: .prepend())
	static function first($target, $contents = '')
	{
		return wetem::op($contents, $target, 'first');
	}

	// Replace specified layer's contents with our new contents.
	static function replace($target, $contents = '')
	{
		return wetem::op($contents, $target, 'replace');
	}

	// Rename the current layer to $target.
	static function rename($target, $new_name)
	{
		if (empty($target) || empty($new_name) || $target == 'default' || !isset(self::$layers[$target]))
			return false;
		$result = self::insert_layer($new_name, $target, 'rename');
		$result &= self::remove_layer($target);
		return $result ? $new_name : false;
	}

	// Wrap a new layer around the current one. (Equivalent to jQuery's wrap)
	// @todo: accept blocks, as we should be able to add layers around them.
	static function outer($target, $new_layer = '')
	{
		if (empty($new_layer))
			list ($target, $new_layer) = array('default', $target);
		if (!isset(self::$layers[$target]))
			return false;
		return self::insert_layer($new_layer, $target, 'outer');
	}

	// Wrap a new layer around the current one's contents. (Equivalent to jQuery's wrapInner)
	static function inner($target, $new_layer = '')
	{
		if (empty($new_layer))
			list ($target, $new_layer) = array('default', $target);
		if (!isset(self::$layers[$target]))
			return false;
		self::$layers[$target] = array($new_layer => self::$layers[$target]);
		self::$layers[$new_layer] =& self::$layers[$target][$new_layer];
		return $new_layer;
	}

	/**
	 * A quick alias to tell Wedge to hide blocks that don't belong to the main flow (default layer).
	 *
	 * @param array $layer The layers we want to keep, or 'html' for the main html/body layers. Leave empty to just keep the default layer.
	 */
	static function hide($layer = '')
	{
		global $context;

		if (empty(self::$layers['default']))
			self::$layers['default'] = array('main' => true);

		// We only keep the default layer and its content. (e.g. we're inside an Ajax frame)
		if (empty($layer))
			self::$skeleton = array(
				'dummy' => array(
					'default' => self::$layers['default']
				)
			);
		// Or we only keep the HTML headers, body definition and content (e.g. we're inside a popup window)
		elseif ($layer === 'html')
			self::$skeleton = array(
				'html' => array(
					'body' => array(
						'default' => self::$layers['default']
					)
				)
			);
		// Or finally... Do we want to keep/add a specific layer, like 'print' maybe?
		else
			self::$skeleton = array(
				'dummy' => array(
					$layer => array(
						'default' => self::$layers['default']
					)
				)
			);
		self::reindex();

		// Give plugins/themes a simple way to know we're hiding it all.
		$context['hide_chrome'] = self::$hidden = true;
	}

	/**
	 * Add a layer dynamically.
	 *
	 * A layer is a special block that contains other blocks/layers instead of a dedicated template function.
	 * These can also be done through the equivalent wetem:: functions, by specifying array('layer' => array()) as the contents.
	 *
	 * @param string $layer The name of the layer to be called. e.g. 'layer' will attempt to load 'template_layer_before' and 'template_layer_after' functions.
	 * @param string $target Which layer to add it relative to, e.g. 'body' (overall page, outside the wrapper divs), etc. Leave empty to wrap around the default layer (which doesn't accept any positioning, either.)
	 * @param string $where Where should we add the layer? Check the comments inside the function for a fully documented list of positions.
	 * @return mixed Returns false if a problem occurred, otherwise the name of the inserted layer.
	 */
	static function layer($layer, $target = '', $where = 'replace')
	{
		/*
			This is the full list of $where possibilities.
			<layer> is $layer, <target> is $target, and <sub> is anything already inside <target>, block or layer.

			add			add as a child to the target, in last position			<target> <sub /> <layer> </layer> </target>
			first		add as a child to the target, in first position			<target> <layer> </layer> <sub /> </target>
			before		add before the item										<layer> </layer> <target> <sub /> </target>
			after		add after the item										<target> <sub /> </target> <layer> </layer>
			replace		replace the target layer and empty its contents			<layer>                            </layer>
		*/

		if (empty($target))
			$target = 'default';

		// Target layer doesn't exist..? Enter brooding mode.
		if (!isset(self::$layers[$target]))
			return false;

		if ($where === 'before' || $where === 'after')
			self::insert_layer($layer, $target, $where);
		elseif ($where === 'replace')
		{
			self::insert_layer($layer, $target, $where);
			self::remove_layer($target);
		}
		elseif ($where === 'first' || $where === 'add')
		{
			if ($where === 'first')
				self::$layers[$target] = array_merge(array($layer => array()), self::$layers[$target]);
			else
				self::$layers[$target][$layer] = array();
			self::$layers[$layer] =& self::$layers[$target][$layer];
		}
		else
			return false;
		return $layer;
	}

	/**********************************************************************
	 *
	 * All functions below are private, plugins shouldn't bother with them.
	 *
	 **********************************************************************/

	/**
	 * Builds the skeleton array (self::$skeleton) and the layers array (self::$layers)
	 * based on the contents of $context['skeleton'].
	 */
	private static function parse(&$arr, &$dest, &$pos = 0, $name = '')
	{
		for ($c = count($arr); $pos < $c;)
		{
			$tag =& $arr[$pos++];

			// Ending a layer?
			if (!empty($tag[1]))
			{
				self::$layers[$name] =& $dest;
				return;
			}

			// Starting a layer?
			if (empty($tag[4]))
			{
				$dest[$tag[2]] = array();
				self::parse($arr, $dest[$tag[2]], $pos, $tag[2]);
			}
			// Then it's a block...
			else
				$dest[$tag[2]] = true;

			// Has this layer/block got any options? (Wedge only accepts indent="x" as of now.)
			if (!empty($tag[3]))
			{
				preg_match_all('~(\w+)="([^"]+)"?~', $tag[3], $options, PREG_SET_ORDER);
				foreach ($options as $option)
					self::$opt[$option[1]][$tag[2]] = $option[2];
			}
		}
	}

	/**
	 * Rebuilds $layers according to the current skeleton.
	 * The skeleton builder doesn't call this because it does it automatically.
	 */
	private static function reindex()
	{
		// Save $skeleton as raw data to ensure it doesn't get erased next.
		$transit = unserialize(serialize(self::$skeleton));
		self::$layers = array();
		self::$skeleton = $transit;

		// Sadly, array_walk_recursive() won't trigger on child arrays... :(
		self::reindex_recursive(self::$skeleton);
	}

	private static function reindex_recursive(&$here)
	{
		foreach ($here as $id => &$item)
		{
			if (is_array($item))
			{
				self::$layers[$id] =& $item;
				self::reindex_recursive($item);
			}
		}
	}

	private static function render_recursive(&$here, $key)
	{
		if (isset(self::$opt['indent'][$key]))
			echo '<inden@zi=', $key, '=', self::$opt['indent'][$key], '>';

		// Show the _before part of the layer.
		execBlock($key . '_before', 'ignore');

		if ($key === 'top' || $key === 'default')
			while_we_re_here();

		foreach ($here as $id => $temp)
		{
			// If the item is an array, then it's a layer. Otherwise, it's a block.
			if (is_array($temp))
				self::render_recursive($temp, $id);
			elseif (isset(self::$opt['indent'][$id]))
			{
				echo '<inden@zi=', $id, '=', self::$opt['indent'][$id], '>';
				execBlock($id);
				echo '</inden@zi=', $id, '>';
			}
			else
				execBlock($id);
		}

		// Show the _after part of the layer
		execBlock($key . '_after', 'ignore');

		if (isset(self::$opt['indent'][$key]))
			echo '</inden@zi=', $key, '>';

		// !! We should probably move this directly to template_html_after() and forget the buffering thing...
		if ($key === 'html' && !isset($_REQUEST['xml']) && !self::$hidden)
			db_debug_junk();
	}

	/**
	 * Returns the name of the first valid layer/block in the list, or false if nothing was found.
	 *
	 * @param string $targets A layer or block, or array of layers or blocks to look for. Leave empty to use the default layer.
	 * @param string $where The magic keyword. See definition for ::op().
	 */
	private static function find($targets = '', $where = '')
	{
		// Find the first target layer that isn't wishful thinking.
		foreach ((array) $targets as $layer)
		{
			if (empty($layer))
				$layer = 'default';
			if (isset(self::$layers[$layer]))
			{
				$to = $layer;
				break;
			}
		}

		// No valid layer found.
		if (empty($to))
		{
			// If we try to insert a sideback block in XML or minimal mode (hide_chrome), it will fail.
			// Plugins should provide a 'default' fallback if they consider it vital to show the block, e.g. array('sidebar', 'default').
			if (!empty($where) && $where !== 'before' && $where !== 'after')
				return false;

			// Or maybe we're looking for a block..?
			$all_blocks = iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator(self::$skeleton)));
			foreach ((array) $targets as $block)
			{
				if (isset($all_blocks[$block]))
				{
					$to = $block;
					break;
				}
			}
			unset($all_blocks);
		}
		return $to;
	}

	private static function list_blocks($items)
	{
		$blocks = array();
		foreach ($items as $key => $val)
		{
			if (is_array($val))
				$blocks[$key] = self::list_blocks($val);
			else
				$blocks[$val] = true;
		}
		return $blocks;
	}

	/**
	 * Insert a layer to the skeleton.
	 *
	 * @param string $source Name of the layer to insert.
	 * @param string $target Name of the parent layer to target.
	 * @param string $where Determines where to position the source layer relative to the target.
	 */
	private static function insert_layer($source, $target = 'default', $where = 'outer')
	{
		$lay = self::parent($target);
		$lay = $lay ? $lay : 'default';
		if (!isset(self::$layers[$lay]))
			return false;
		$dest =& self::$layers[$lay];

		$temp = array();
		foreach ($dest as $key => &$value)
		{
			if ($key === $target)
			{
				if ($where === 'after')
					$temp[$key] = $value;
				$temp[$source] = $where === 'outer' ? array($key => $value) : ($where === 'replace' ? array() : ($where === 'rename' ? $value : array()));
				if ($where === 'before')
					$temp[$key] = $value;
			}
			else
				$temp[$key] = $value;
		}

		$dest = $temp;
		// We need to reindex, in case the layer had child layers.
		if ($where !== 'after' && $where !== 'before')
			self::reindex();
		return true;
	}

	// Helper function to remove a layer from the page.
	private static function remove_layer($layer)
	{
		// Does the layer at least exist...?
		if (!isset(self::$layers[$layer]) || $layer === 'default')
			return false;

		// Determine whether removing this layer would also remove the default layer. Which you may not.
		$current = 'default';
		$loop = true;
		while ($loop)
		{
			$loop = false;
			foreach (self::$layers as $id => &$curlay)
			{
				if (isset($curlay[$current]))
				{
					// There there! Go away now, we won't tell your parents.
					if ($id === $layer)
						return false;
					$current = $id;
					$loop = true;
				}
			}
		}

		// This isn't a direct parent of 'default', so we can safely remove it.
		self::$skeleton = self::remove_item($layer);
		self::reindex();
		return true;
	}

	private static function remove_item($item, $from = array(), $level = 0)
	{
		if (empty($from))
			$from = self::$skeleton;

		$ret = array();
		foreach ($from as $key => $val)
			if ($key !== $item)
				$ret[$key] = is_array($val) && !empty($val) ? self::remove_item($item, $val, $level + 1) : $val;

		return $ret;
	}

	/**
	 * Add blocks or layers to the skeleton.
	 *
	 * @param string $blocks The name of the blocks or layers to be added.
	 * @param string $target Which layer to load this function in, e.g. 'default' (main contents), 'top' (above the main area), 'sidebar' (sidebar area), etc. If using 'before' or 'after', you may instead specify a block name.
	 * @param string $where Where should we add the item? Check the comments inside the function for a fully documented list of positions. Non-default layers should use wetem::add() rather than wetem::load().
	 * @param bool $force Only used when $blocks shouldn't be tempered with.
	 */
	private static function op($blocks, $target, $where, $force = false)
	{
		/*
			This is the full list of $where possibilities.
			<blocks> is our source block(s), <layer> is our $target layer, and <other> is anything already inside <layer>, block or layer.

			load		replace existing blocks with this, leave layers in		<layer> <ITEMS /> <other /> </layer>
			replace		replace existing blocks AND layers with this			<layer>      <ITEMS />      </layer>

			add			add block(s) at the end of the layer					<layer> <other /> <ITEMS /> </layer>
			first		add block(s) at the beginning of the layer				<layer> <ITEMS /> <other /> </layer>

			before		add block(s) before the specified layer or block		    <ITEMS /> <layer-or-block />
			after		add block(s) after the specified layer or block			    <layer-or-block /> <ITEMS />
		*/

		// If we only have one parameter, this means we only provided a list of blocks/layer,
		// and expect to use them relative to the default layer, so we'll swap the variables.
		if (empty($blocks))
			list ($target, $blocks) = array('default', $target);

		if (!$force)
			$blocks = self::list_blocks((array) $blocks);
		$has_layer = (bool) count(array_filter($blocks, 'is_array'));
		$to = self::find($target, $where);
		if (empty($to))
			return false;

		// If a mod requests to replace the contents of the sidebar, just smile politely.
		if (($where === 'load' || $where === 'replace') && $to === 'sidebar')
			$where = 'add';

		if ($where === 'load' || $where === 'replace')
		{
			// Most likely case: no child layers (or erase all). Replace away!
			if ($where === 'replace' || !isset(self::$layers[$to]) || count(self::$layers[$to]) === count(self::$layers[$to], COUNT_RECURSIVE))
			{
				self::$layers[$to] = $blocks;
				// If we erase, we might have to delete layer entries.
				if ($where === 'replace' || $has_layer)
					self::reindex();
				return $to;
			}

			// Otherwise, we're in for some fun... :-/
			$keys = array_keys(self::$layers[$to]);
			foreach ($keys as $id)
			{
				if (!is_array(self::$layers[$to][$id]))
				{
					// We're going to insert our item(s) right before the first block we find...
					if (!isset($offset))
					{
						$offset = array_search($id, $keys, true);
						self::$layers[$to] = array_merge(array_slice(self::$layers[$to], 0, $offset, true), $blocks, array_slice(self::$layers[$to], $offset, null, true));
					}
					// ...And then we delete the other block(s) and leave the layers where they are.
					unset(self::$layers[$to][$id]);
				}
			}

			// So, we found a layer but no blocks..? Add our blocks at the end.
			if (!isset($offset))
				self::$layers[$to] += $blocks;

			self::reindex();
			return $to;
		}

		elseif ($where === 'add')
			self::$layers[$to] += $blocks;

		elseif ($where === 'first')
			self::$layers[$to] = array_merge(array_reverse($blocks), self::$layers[$to]);

		elseif ($where === 'before' || $where === 'after')
		{
			foreach (self::$layers as &$layer)
			{
				if (!isset($layer[$to]))
					continue;

				$layer = array_insert($layer, $to, $blocks, $where === 'after');
				self::reindex();
				return $to;
			}
		}
		else
			return false;

		if ($has_layer)
			self::reindex();

		return $to;
	}
}

?>