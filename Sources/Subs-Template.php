<?php
/**
 * Wedge
 *
 * The templating code, aka rendering process.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
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
	global $context, $settings, $modSettings, $txt;
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
		// Was the page title set last minute? Also update the HTML safe one.
		if (!empty($context['page_title']) && empty($context['page_title_html_safe']))
			$context['page_title_html_safe'] = westr::htmlspecialchars(un_htmlspecialchars($context['page_title']));

		// Start up the session URL fixer.
		ob_start('ob_sessrewrite');

		// Run any possible extra output buffers as provided by mods.
		if (!empty($settings['output_buffers']) && is_string($settings['output_buffers']))
			$buffers = explode(',', $settings['output_buffers']);
		elseif (!empty($settings['output_buffers']))
			$buffers = $settings['output_buffers'];
		else
			$buffers = array();

		if (isset($modSettings['hooks']['buffer']))
			$buffers = array_merge($modSettings['hooks']['buffer'], $buffers);

		if (!empty($buffers))
		{
			foreach ($buffers as $function)
			{
				$fun = explode('|', trim($function));
				$call = strpos($fun[0], '::') !== false ? explode('::', $fun[0]) : $fun[0];

				// We might need to load some stuff here.
				if (!empty($fun[1]))
				{
					if (!empty($fun[2]) && $fun[2] === 'addon')
						require_once($fun[1] . '.php');
					else
						loadSource($fun[1]);
				}
			}
			
		}

		// Display the screen in the logical order.
		start_output();
		$start_done = true;
	}

	if ($do_finish)
	{
		if (WIRELESS && !isset($context['layers']['context']))
			fatal_lang_error('wireless_error_notyet', false);

		if (empty($context['layers']['context']))
			fatal_error('The context layer was removed. You can NOT force me to render something so screwed up.');

		render_skeleton(reset($context['skeleton_array']), key($context['skeleton_array']));
	}

	// Remember this URL in case someone doesn't like sending HTTP_REFERER.
	if (strpos($_SERVER['REQUEST_URL'], 'action=dlattach') === false && strpos($_SERVER['REQUEST_URL'], 'action=viewremote') === false)
		$_SESSION['old_url'] = $_SERVER['REQUEST_URL'];

	// For session check verification.... Don't switch browsers...
	$_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];

	// Hand off the output to the portal, etc. we're integrated with.
	call_hook('exit', array($do_finish && !WIRELESS));

	// Don't exit if we're coming from index.php; that will pass through normally.
	if (!$from_index || WIRELESS)
	{
		if (!isset($modSettings['app_error_count']))
			$modSettings['app_error_count'] = 0;
		if (!empty($context['app_error_count']))
			updateSettings(
				array(
					'app_error_count' => $modSettings['app_error_count'] + $context['app_error_count'],
				)
			);
		exit;
	}
}

/**
 * This is where we render the HTML page!
 */
function render_skeleton(&$here, $key)
{
	global $context;

	// Show the _above part of the layer.
	execBlock($key . '_above', 'ignore');

	if ($key === 'top' || $key === 'context')
		while_we_re_here();

	foreach ($here as $id => $temp)
	{
		// If the item is an array, then it's a layer. Otherwise, it's a block.
		if (is_array($temp))
			render_skeleton($temp, $id);
		else
			execBlock($id, 'ignore');
	}

	// Show the _below part of the layer
	execBlock($key . '_below', 'ignore');

	// !! We should probably move this directly to template_html_below() and forget the buffering thing...
	if ($key === 'html' && !isset($_REQUEST['xml']) && empty($context['hide_chrome']))
		db_debug_junk();
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
	global $scripturl, $modSettings, $user_info, $context, $db_prefix, $session_var;
	global $txt, $time_start, $db_count, $db_show_debug, $cached_urls, $use_cache;

	// Just quit if $scripturl is set to nothing, or the SID is not defined. (SSI?)
	if ($scripturl == '' || !defined('SID'))
		return $buffer;

	if (!empty($context['show_load_time']))
	{
		$old_db_count = $db_count;
		$old_load_time = microtime(true);
	}

	// Do nothing if the session is cookied, or they are a crawler - guests are caught by redirectexit().
	if (empty($_COOKIE) && SID != '' && empty($context['browser']['possibly_robot']))
		$buffer = preg_replace('/"' . preg_quote($scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', '"' . $scripturl . '?' . SID . '&amp;', $buffer);
	// Debugging templates, are we?
	elseif (isset($_GET['debug']))
		$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote($scripturl, '/') . '\\??/', '"' . $scripturl . '?debug;', $buffer);

	call_hook('dynamic_rewrite', array(&$buffer));

	if (!allowedTo('profile_view_any'))
		$buffer = preg_replace(
			'~<a(?:\s+|\s[^>]*\s)href="' . preg_quote($scripturl, '~') . '\?action=profile' . (!$user_info['is_guest'] && allowedTo('profile_view_own') ? ';(?:[^"]+;)?u=(?!' . $user_info['id'] . ')' : '') . '[^"]*"[^>]*>(.*?)</a>~',
			'$1', $buffer
		);

	if (!empty($context['last_minute_header']))
		$buffer = preg_replace("~\n</head>~", $context['last_minute_header'] . "\n</head>", $buffer, 1);

	// Rewrite the buffer with pretty URLs!
	if (!empty($modSettings['pretty_enable_filters']))
	{
		$insideurl = preg_quote($scripturl, '~');
		$use_cache = !empty($modSettings['pretty_enable_cache']);
		$session_var = $context['session_var'];

		// Remove the script tags
		$context['pretty']['scriptID'] = 0;
		$context['pretty']['scripts'] = array();
		$buffer = preg_replace_callback('~<script.+?</script>~s', 'pretty_scripts_remove', $buffer);

		// Find all URLs in the buffer
		$context['pretty']['search_patterns'][] =  '~(<a[^>]+href=|<link[^>]+href=|<img[^>]+?src=|<form[^>]+?action=)["\']' . $insideurl . '([^"\'#]*?[?;&](board|topic|action)=[^"\'#]+)~';
		$context['pretty']['replace_patterns'][] = '~(<a[^>]+href=|<link[^>]+href=|<img[^>]+?src=|<form[^>]+?action=)["\']' . $insideurl . '([^"\'#]*?[?;&](board|topic|action)=([^"]+"|[^\']+\'))~';
		$urls_query = array();
		$uncached_urls = array();

		// Making sure we don't execute patterns twice.
		$context['pretty']['search_patterns'] = array_flip(array_flip($context['pretty']['search_patterns']));

		foreach ($context['pretty']['search_patterns'] as $pattern)
		{
			preg_match_all($pattern, $buffer, $matches, PREG_PATTERN_ORDER);
			foreach ($matches[2] as $match)
			{
				// Rip out everything that shouldn't be cached
				// !!! base64_encode sometimes finishes strings with '=' gaps IIRC. Should check whether they're not going to be ripped by this.
				// !!! Also, '~=?;+~' (in various places) should probably be rewritten as '~=;+|;{2,}~'
				if ($use_cache)
					$match = preg_replace(array('~^["\']|PHPSESSID=[^&;]+|' . $session_var . '=[^;]+~', '~"~', '~=?;+~', '~\?;|\?&amp;~', '~[?;=]+$~'), array('', '%22', ';', '?', ''), $match);
				else
					// !!! This can easily be optimized into a simple str_replace by adding placeholders for ^ and $.
					$match = preg_replace(array('~^["\']~', '~"~', '~=?;+~', '~\?;~', '~[?;=]+$~'), array('', '%22', ';', '?', ''), $match);
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
				if (!function_exists('pretty_filter_topics'))
					loadSource('PrettyUrls-Filters');
				foreach ($modSettings['pretty_filters'] as $id => $enabled)
					if ($enabled)
						$uncached_urls = call_user_func('pretty_filter_' . $id, $uncached_urls);

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
			foreach ($context['pretty']['replace_patterns'] as $pattern)
				$buffer = preg_replace_callback($pattern, 'pretty_buffer_callback', $buffer);
		}

		// Restore the script tags
		if ($context['pretty']['scriptID'] > 0)
			$buffer = preg_replace_callback('~' . chr(20) . '([0-9]+)' . chr(20) . '~', 'pretty_scripts_restore', $buffer);
	}

	// Moving all inline events (<code onclick="event();">) to the footer, to make
	// sure they're not triggered before jQuery and stuff are loaded. Trick and treats!
	$context['delayed_events'] = array();
	$cut = explode("<!-- JavaScript area -->\n", $buffer);

	// If the placeholder isn't there, it means we're probably not in a default index template,
	// and we probably don't need to postpone any events. Otherwise, go ahead and do the magic!
	if (!empty($cut[1]))
		$buffer = preg_replace_callback('~<[^>]+?\son[a-z]+="[^">]*"[^>]*>~i', 'wedge_event_delayer', $cut[0]) . $cut[1];

	if (!empty($context['delayed_events']))
	{
		$thing = 'var eves = {';
		foreach ($context['delayed_events'] as $eve)
			$thing .= '
		' . $eve[0] . ': ["' . $eve[1] . '", function (e) { ' . $eve[2] . ' }],';
		$thing = substr($thing, 0, -1) . '
	};
	$("*[data-eve]").each(function() {
		for (var eve = 0, elis = $(this).data("eve"), eil = elis.length; eve < eil; eve++)
			$(this).bind(eves[elis[eve]][0], eves[elis[eve]][1]);
	});';
		$buffer = substr_replace($buffer, $thing, strpos($buffer, '<!-- insert inline events here -->'), 34);
	}
	else
		$buffer = str_replace("\n\t<!-- insert inline events here -->", '', $buffer);

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
							$body = str_replace('{' . $param . '}', $params[2][$id], $body);
				}
				$buffer = str_replace(substr($buffer, $p, $end_code + strlen($code) + 6 - $p), $body, $buffer);
			}
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
		if ($user_info['is_admin'])
			$loadTime .= '</li>
			<li class="rd">(' . $txt['dynamic_replacements'] . ': ' . sprintf($txt['seconds_with_' . ($queriesDiff > 1 ? 'queries' : 'query')], $new_load_time - $old_load_time, $queriesDiff) . ')';
		$buffer = str_replace('<!-- insert stats here -->', $loadTime, $buffer);
	}

	// Return the changed buffer.
	return $buffer;
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
	return rtrim($match[0], ' />') . ' data-eve="[' . implode(',', $eve_list) . ']">';
}

// Remove and save script tags
function pretty_scripts_remove($match)
{
	global $context;

	$context['pretty']['scriptID']++;
	$context['pretty']['scripts'][$context['pretty']['scriptID']] = $match[0];
	return chr(20) . $context['pretty']['scriptID'] . chr(20);
}

// A callback function to replace the buffer's URLs with their cached URLs
function pretty_buffer_callback($matches)
{
	global $cached_urls, $scripturl, $use_cache, $session_var;

	// Is this URL part of a feed?
	$isFeed = strpos($matches[1], '>') === false ? '"' : '';

	// Remove those annoying quotes
	$matches[2] = preg_replace('~^[\"\']|[\"\']$~', '', $matches[2]);

	// Store the parts of the URL that won't be cached so they can be inserted later
	if ($use_cache)
	{
		preg_match('~PHPSESSID=[^;#&]+~', $matches[2], $PHPSESSID);
		preg_match('~' . $session_var . '=[^;#]+~', $matches[2], $sesc);
		preg_match('~#.*~', $matches[2], $fragment);
		$url_id = preg_replace(array('~PHPSESSID=[^;#]+|' . $session_var . '=[^;#]+|#.*$~', '~"~', '~=?;+~', '~\?;~', '~[?;=]+$~'), array('', '%22', ';', '?', ''), $matches[2]);
	}
	else
	{
		preg_match('~#.*~', $matches[2], $fragment);
		// Rip out everything that won't have been cached
		$url_id = preg_replace(array('~#.*$~', '~"~', '~=?;+~', '~\?;~', '~[?;=]+$~'), array('', '%22', ';', '?', ''), $matches[2]);
	}

	// Stitch everything back together, clean it up and return
	$replacement = isset($cached_urls[$url_id]) ? $cached_urls[$url_id] : $url_id;
	$replacement .= (strpos($replacement, '?') === false ? '?' : ';') . (isset($PHPSESSID[0]) ? $PHPSESSID[0] : '') . ';' . (isset($sesc[0]) ? $sesc[0] : '') . (isset($fragment[0]) ? $fragment[0] : '');
	$replacement = preg_replace(array('~=?;+~', '~\?;~', '~[?;=]+#|&amp;#~', '~[?;=#]+$|&amp;$~'), array(';', '?', '#', ''), $replacement);

	if (empty($replacement) || $replacement[0] == '?')
		$replacement = $scripturl . $replacement;
	return $matches[1] . $isFeed . $replacement . $isFeed;
}

// Put the script tags back
function pretty_scripts_restore($match)
{
	global $context;

	return $context['pretty']['scripts'][(int) $match[1]];
}

/**
 * A quick alias to tell Wedge to hide blocks that don't belong to the main flow (context layer).
 *
 * @param array $layer The layers we want to keep, or 'html' for the main html/body layers. Leave empty to just keep the context layer.
 */
function hideChrome($layer = '')
{
	global $context;

	if (empty($context['layers']['context']))
		$context['layers']['context'] = array('main' => true);

	// We only keep the context layer and its content. (e.g. we're inside an Ajax frame)
	if (empty($layer))
		$context['skeleton_array'] = array(
			'dummy' => array(
				'context' => $context['layers']['context']
			)
		);
	// Or we only keep the HTML headers, body definition and content (e.g. we're inside a popup window)
	elseif ($layer === 'html')
		$context['skeleton_array'] = array(
			'html' => array(
				'body' => array(
					'context' => $context['layers']['context']
				)
			)
		);
	// Or finally... Do we want to keep/add a specific layer, like 'print' or wireless maybe?
	else
		$context['skeleton_array'] = array(
			'dummy' => array(
				$layer => array(
					'context' => $context['layers']['context']
				)
			)
		);
	build_skeleton_indexes();

	// Nothing to see here, sir.
	$context['hide_chrome'] = true;
}

/**
 * Build the multi-dimensional layout skeleton array from an single-dimension array of tags.
 */
function build_skeleton(&$arr, &$dest, &$pos = 0, $name = '')
{
	global $context;

	for ($c = count($arr); $pos < $c;)
	{
		$tag =& $arr[$pos++];

		// Ending a layer?
		if (!empty($tag[1]))
		{
			$context['layers'][$name] =& $dest;
			return;
		}

		// Starting a layer?
		if (empty($tag[3]))
		{
			$layer = explode(':', $tag[2]);
			$dest[$layer[0]] = array();
			if (isset($layer[1]))
				foreach (explode(',', $layer[1]) as $hint)
					$context['layer_hints'][$hint] = $layer[0];

			build_skeleton($arr, $dest[$layer[0]], $pos, $layer[0]);
		}
		// Then it's a block...
		else
			$dest[$tag[2]] = true;
	}
}

/**
 * Rebuilds $context['layers'] according to current skeleton.
 * The skeleton builder doesn't call this because it does it automatically.
 */
function build_skeleton_indexes()
{
	global $context;

	// We only reset the list of references, it won't impact the skeleton array.
	$context['layers'] = array();

	// !!! Saly, array_walk_recursive() won't trigger on child arrays... :(
	build_skeleton_indexes_recursive($context['skeleton_array']);
}

function build_skeleton_indexes_recursive(&$here)
{
	global $context;

	foreach ($here as $id => &$item)
	{
		if (is_array($item))
		{
			$context['layers'][$id] =& $item;
			build_skeleton_indexes_recursive($item);
		}
	}
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
	global $modSettings, $context, $settings;

	if (!isset($_REQUEST['xml']))
		setupThemeContext();

	// Print stuff to prevent caching of pages (except on attachment errors, etc.)
	if (empty($context['no_last_modified']))
	{
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

		if (!isset($_REQUEST['xml']) && !WIRELESS)
			header('Content-Type: text/html; charset=UTF-8');
	}

	header('Content-Type: text/' . (isset($_REQUEST['xml']) ? 'xml' : 'html') . '; charset=UTF-8');

	$context['show_load_time'] = !empty($modSettings['timeLoadPageEnable']);

	if (isset($settings['use_default_images'], $settings['default_template']) && $settings['use_default_images'] == 'defaults')
	{
		$settings['theme_url'] = $settings['default_theme_url'];
		$settings['images_url'] = $settings['default_images_url'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}
}

/**
 * Use the opportunity to show some potential errors while we're showing the top or context layer...
 *
 * - If using a conventional theme (with body or main layers), and the user is an admin, check whether certain files are present, and if so give the admin a warning. These include the installer, repair-settings and backups of the Settings files (with php~ extensions)
 * - If the user is post-banned, provide a nice warning for them.
 */
function while_we_re_here()
{
	global $txt, $modSettings, $context, $user_info, $boarddir, $cachedir;

	static $checked_securityFiles = false, $showed_banned = false, $showed_behav_error = false;

	// May seem contrived, but this is done in case the body and context layer aren't there...
	// Was there a security error for the admin?
	if ($context['user']['is_admin'] && !empty($context['behavior_error']) && !$showed_behav_error)
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
	elseif (allowedTo('admin_forum') && !$user_info['is_guest'] && !$checked_securityFiles)
	{
		$checked_securityFiles = true;
		$securityFiles = array('import.php', 'install.php', 'webinstall.php', 'upgrade.php', 'convert.php', 'repair_paths.php', 'repair_settings.php', 'Settings.php~', 'Settings_bak.php~');

		foreach ($securityFiles as $i => $securityFile)
			if (!file_exists($boarddir . '/' . $securityFile))
				unset($securityFiles[$i]);

		if (!empty($securityFiles) || (!empty($modSettings['cache_enable']) && !is_writable($cachedir)))
		{
				echo '
		<div class="errorbox">
			<p class="alert">!!</p>
			<h3>', empty($securityFiles) ? $txt['cache_writable_head'] : $txt['security_risk'], '</h3>
			<p>';

			foreach ($securityFiles as $securityFile)
			{
				echo '
				', $txt['not_removed'], '<strong>', $securityFile, '</strong>!<br>';

				if ($securityFile == 'Settings.php~' || $securityFile == 'Settings_bak.php~')
					echo '
				', sprintf($txt['not_removed_extra'], $securityFile, substr($securityFile, 0, -1)), '<br>';
			}

			if (!empty($modSettings['cache_enable']) && !is_writable($cachedir))
				echo '
				<strong>', $txt['cache_writable'], '</strong><br>';

			echo '
			</p>
		</div>';
		}
	}
	// If the user is banned from posting, inform them of it.
	elseif (isset($_SESSION['ban']['cannot_post']) && !$showed_banned)
	{
		$showed_banned = true;
		echo '
				<div class="windowbg wrc alert" style="margin: 2ex; padding: 2ex; border: 2px dashed red">
					', sprintf($txt['you_are_post_banned'], $user_info['is_guest'] ? $txt['guest_title'] : $user_info['name']);

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
 * Display the debug data at the foot of the page if debug mode ($db_show_debug) is set to boolean true (only) and not in wireless or the query viewer page.
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
	global $context, $scripturl, $boarddir, $modSettings, $txt;
	global $db_cache, $db_count, $db_show_debug, $cache_count, $cache_hits;

	// Is debugging on? (i.e. it is set, and it is true, and we're not on action=viewquery or an help popup.
	$show_debug = (isset($db_show_debug) && $db_show_debug === true && (!isset($_GET['action']) || ($_GET['action'] != 'viewquery' && $_GET['action'] != 'help')) && !WIRELESS);
	// Check groups
	if (empty($modSettings['db_show_debug_who']) || $modSettings['db_show_debug_who'] == 'admin')
		$show_debug &= $context['user']['is_admin'];
	elseif ($modSettings['db_show_debug_who'] == 'mod')
		$show_debug &= allowedTo('moderate_forum');
	elseif ($modSettings['db_show_debug_who'] == 'regular')
		$show_debug &= $context['user']['is_logged'];
	else
		$show_debug &= ($modSettings['db_show_debug_who'] == 'any');

	// Now, who can see the query log? Need to have the ability to see any of this anyway.
	$show_debug_query = $show_debug;
	if (empty($modSettings['db_show_debug_who_log']) || $modSettings['db_show_debug_who_log'] == 'admin')
		$show_debug_query &= $context['user']['is_admin'];
	elseif ($modSettings['db_show_debug_who_log'] == 'mod')
		$show_debug_query &= allowedTo('moderate_forum');
	elseif ($modSettings['db_show_debug_who_log'] == 'regular')
		$show_debug_query &= $context['user']['is_logged'];
	else
		$show_debug_query &= ($modSettings['db_show_debug_who_log'] == 'any');

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

		$_SESSION['debug'] = &$db_cache;
	}

	$temp = '
<div class="smalltext" style="text-align: left; margin: 1ex">
	' . $txt['debug_templates'] . count($context['debug']['templates']) . ': <em>' . implode(', ', $context['debug']['templates']) . '</em>.<br>
	' . $txt['debug_blocks'] . count($context['debug']['blocks']) . ': <em>' . implode(', ', $context['debug']['blocks']) . '</em>.<br>
	' . $txt['debug_language_files'] . count($context['debug']['language_files']) . ': <em>' . implode(', ', $context['debug']['language_files']) . '</em>.<br>
	' . $txt['debug_stylesheets'] . count($context['debug']['sheets']) . ': <em>' . implode(', ', $context['debug']['sheets']) . '</em>.<br>
	' . $txt['debug_files_included'] . count($files) . ' - ' . round($total_size / 1024) . $txt['debug_kb'] . ' (<a href="javascript:void(0)" onclick="$(\'#debug_include_info\').css(\'display\', \'inline\'); this.style.display = \'none\';">' . $txt['debug_show'] . '</a><span id="debug_include_info" class="hide"><em>' . implode(', ', $files) . '</em></span>)<br>';

	if (!empty($modSettings['cache_enable']) && !empty($cache_hits))
	{
		$entries = array();
		$total_t = 0;
		$total_s = 0;
		foreach ($cache_hits as $cache_hit)
		{
			$entries[] = $cache_hit['d'] . ' ' . $cache_hit['k'] . ': ' . sprintf($txt['debug_cache_seconds_bytes'], comma_format($cache_hit['t'], 5), $cache_hit['s']);
			$total_t += $cache_hit['t'];
			$total_s += $cache_hit['s'];
		}

		$temp .= '
	' . $txt['debug_cache_hits'] . $cache_count . ': ' . sprintf($txt['debug_cache_seconds_bytes_total'], comma_format($total_t, 5), comma_format($total_s)) . ' (<a href="javascript:void(0)" onclick="$(\'#debug_cache_info\').css(\'display\', \'inline\'); $(this).hide();">' . $txt['debug_show'] . '</a><span id="debug_cache_info" class="hide"><em>' . implode(', ', $entries) . '</em></span>)<br>';
	}

	if ($show_debug_query)
		$temp .= '
	<a href="' . $scripturl . '?action=viewquery" target="_blank" class="new_win">' . ($warnings == 0 ? sprintf($txt['debug_queries_used'], (int) $db_count) : sprintf($txt['debug_queries_used_and_warnings'], (int) $db_count, $warnings)) . '</a><br>
	<br>';
	else
		$temp .= '
	' . sprintf($txt['debug_queries_used'], (int) $db_count) . '<br>
	<br>';

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
			$temp .= '
	<br>';
		}

	if ($show_debug_query)
		$temp .= '
	<a href="' . $scripturl . '?action=viewquery;sa=hide">' . $txt['debug_' . (empty($_SESSION['view_queries']) ? 'show' : 'hide') . '_queries'] . '</a>';

	$context['debugging_info'] = $temp . '
</div>';
}

/**
 * Manage the process of loading a template file. This should not normally be called directly (instead, use {@link loadTemplate()} which invokes this function)
 *
 * This function ultimately handles the physical loading of a template or language file, and if $modSettings['disableTemplateEval'] is off, it also loads it in such a way as to parse it first - to be able to produce a different output to highlight where the error is (which is also not cached)
 *
 * @param string $filename The full path of the template to be loaded.
 * @param bool $once Whether to check that this template is uniquely loaded (for some templates, workflow dictates that it can be loaded only once, so passing it to require_once is an unnecessary performance hurt)
 */
function template_include($filename, $once = false)
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;
	global $user_info, $boardurl, $boarddir;
	global $maintenance, $mtitle, $mmessage;
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
	if (empty($modSettings['disableTemplateEval']))
	{
		$file_found = file_exists($filename) && eval('?' . '>' . rtrim(file_get_contents($filename))) !== false;
		$settings['current_include_filename'] = $filename;
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
		ob_end_clean();
		if (!empty($modSettings['enableCompressedOutput']))
			@ob_start('ob_gzhandler');
		else
			ob_start();

		// Don't cache error pages!!
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		if (!isset($txt['template_parse_error']))
		{
			$txt['template_parse_error'] = 'Template Parse Error!';
			$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system. This problem should only be temporary, so please come back later and try again. If you continue to see this message, please contact the administrator.<br><br>You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
			$txt['template_parse_error_details'] = 'There was a problem loading the <tt><strong>%1$s</strong></tt> template or language file. Please check the syntax and try again - remember, single quotes (<tt>\'</tt>) often have to be escaped with a slash (<tt>\\</tt>). To see more specific error information from PHP, try <a href="' . $boardurl . '%1$s" class="extern">accessing the file directly</a>.<br><br>You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="' . $scripturl . '?theme=1">use the default theme</a>.';
		}

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
			loadSource('Subs-Package');

			$error = fetch_web_data($boardurl . strtr($filename, array($boarddir => '', strtr($boarddir, '\\', '/') => '')));
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

		die;
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
	global $context, $settings, $txt, $scripturl, $boarddir, $db_show_debug;

	// No template to load?
	if ($template_name === false)
		return true;

	$loaded = false;
	foreach ($settings['template_dirs'] as $template_dir)
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
	elseif (!file_exists($settings['default_theme_dir']) && file_exists($boarddir . '/Themes/default'))
	{
		$settings['default_theme_dir'] = $boarddir . '/Themes/default';
		$settings['template_dirs'][] = $settings['default_theme_dir'];

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
	elseif ($template_name != 'Errors' && $template_name != 'index' && $fatal)
		fatal_lang_error('theme_template_error', 'template', array((string) $template_name));
	elseif ($fatal)
		die(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load Themes/default/%s.template.php!', (string) $template_name), 'template'));
	else
		return false;
}

/**
 * Actually display a template block.
 *
 * This is called by the header and footer templates to actually have content output to the buffer; this directs which template_ functions are called, including logging them for debugging purposes.
 *
 * Additionally, if debug is part of the URL (?debug or ;debug), there will be divs added for administrators to mark where template layers begin and end, with orange background and red borders.
 *
 * @param string $block_name The name of the function (without template_ prefix) to be called.
 * @param mixed $fatal Whether to die fatally on a template not being available; if passed as boolean false, it is a fatal error through the usual template layers and including forum header. Also accepted is the string 'ignore' which means to skip the error; otherwise end execution with a basic text error message.
 */
function execBlock($block_name, $fatal = false)
{
	global $context, $settings, $options, $txt, $db_show_debug;

	if (empty($block_name))
		return;

	if ($db_show_debug === true)
		$context['debug']['blocks'][] = $block_name;

	// Figure out what the template function is named.
	$theme_function = 'template_' . $block_name;

	// !!! Doing these tests is relatively slow, but there aren't that many. In case performance worsens,
	// !!! we should cache the function list (get_defined_functions()) and isset() against the cache.
	if (function_exists($theme_function_before = $theme_function . '_before'))
		$theme_function_before();

	if (function_exists($theme_function_override = $theme_function . '_override'))
		$theme_function_override();
	elseif (function_exists($theme_function))
		$theme_function();
	elseif ($fatal === false)
		fatal_lang_error('theme_template_error', 'template', array((string) $block_name));
	elseif ($fatal !== 'ignore')
		die(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load the "%s" template block!', (string) $block_name), 'template'));

	if (function_exists($theme_function_after = $theme_function . '_after'))
		$theme_function_after();

	// Are we showing debugging for templates? Just make sure not to do it before the doctype...
	if (allowedTo('admin_forum') && isset($_REQUEST['debug']) && $block_name !== 'init' && ob_get_length() > 0 && !isset($_REQUEST['xml']))
		echo '
<div style="font-size: 8pt; border: 1px dashed red; background: orange; text-align: center; font-weight: bold;">---- ', $block_name, ' ends ----</div>';
}

/**
 * Build a list of template blocks.
 *
 * @param string $blocks The name of the function(s) (without template_ prefix) to be called.
 * @param string $target Which layer to load this function in, e.g. 'context' (main contents), 'top' (above the main area), 'sidebar' (sidebar area), etc.
 * @param boolean $where Where should we add the layer? Check the comments inside the function for a fully documented list of positions.
 */
function loadBlock($blocks, $target = 'context', $where = 'replace')
{
	global $context;

	/*
		This is the full list of $where possibilities. 'replace' is the default, meant for use in the main layer.
		<blocks> is our source block(s), <layer> is our $target layer, and <other> is anything already inside <layer>, block or layer.

		replace		replace existing blocks with this, leave layers in		<layer> <blocks /> <other /> </layer>
		erase		replace existing blocks AND layers with this			<layer>       <blocks />     </layer>

		add			add block(s) at the end of the layer					<layer> <other /> <blocks /> </layer>
		first		add block(s) at the beginning of the layer				<layer> <blocks /> <other /> </layer>

		before		add block(s) before the specified layer or block		    <blocks /> <layer-or-block />
		after		add block(s) after the specified layer or block			    <layer-or-block /> <blocks />
	*/

	$blocks = array_flip((array) $blocks);
	$hints =& $context['layer_hints'];
	foreach ((array) $target as $layer)
	{
		// Is the target layer wishful thinking?
		if ($layer[0] === ':' && ($hint = substr($layer, 1)) && isset($hints[$hint], $context['layers'][$hints[$hint]]))
			$to = $hints[$hint];
		elseif (isset($context['layers'][$layer]))
			$to = $layer;
		if (isset($to))
			break;
	}
	// If we try to insert a sideback block in minimal (hide_chrome), Wireless or XML, it will fail.
	// The add-on should provide a 'context' fallback if it considers it vital to show the block, e.g. array('sidebar', 'context').
	if (empty($to))
		return;

	// If a mod requests to replace the contents of the sidebar, just smile politely.
	if (($where === 'replace' || $where === 'erase') && $to === 'sidebar')
		$where = 'add';

	if ($where === 'replace' || $where === 'erase')
	{
		$has_arrays = false;
		if ($where === 'replace' && isset($context['layers'][$to]))
			foreach ($context['layers'][$to] as $item)
				$has_arrays |= is_array($item);

		// Most likely case: no child layers (or erase all). Replace away!
		if (!$has_arrays)
		{
			$context['layers'][$to] = $blocks;
			// If we erase, we might have to deleted layer entries.
			if ($where === 'erase')
				build_skeleton_indexes();
			return;
		}

		// Otherwise, we're in for some fun... :-/
		$keys = array_keys($context['layers'][$to]);
		foreach ($keys as $id)
		{
			$item =& $context['layers'][$to][$id];
			if (!is_array($item))
			{
				// We're going to insert our block(s) right before the first block we find...
				if (!isset($offset))
				{
					$val = array_values($context['layers'][$to]);
					$offset = array_search($id, $keys, true);
					array_splice($keys, $offset, 0, array_keys($blocks));
					array_splice($val, $offset, 0, array_fill(0, count($blocks), true));
					$context['layers'][$to] = array_combine($keys, $val);
				}
				// ...And then we delete the other block(s) and leave the layers where they are.
				unset($context['layers'][$to][$id]);
			}
		}

		// So, we found a layer but no blocks..? Add our blocks at the end.
		if (!isset($offset))
			$context['layers'][$to] += $blocks;
		build_skeleton_indexes();
	}
	elseif ($where === 'add')
		$context['layers'][$to] = array_merge($context['layers'][$to], $blocks);
	elseif ($where === 'first')
		$context['layers'][$to] = array_merge(array_reverse($blocks), $context['layers'][$to]);
	elseif ($where === 'before' || $where === 'after')
	{
		foreach ($context['layers'] as &$layer)
		{
			if (isset($layer[$to]))
			{
				$keys = array_keys($layer);
				$val = array_values($layer);
				$offset = array_search($to, $keys) + ($where === 'after' ? 1 : 0);
				array_splice($keys, $offset, 0, array_keys($blocks));
				array_splice($val, $offset, 0, array_fill(0, count($blocks), true));
				$layer = array_combine($keys, $val);
				build_skeleton_indexes();
				break;
			}
		}
	}
}

/**
 * Add a layer dynamically.
 *
 * @param string $layer The name of the layer to be called. e.g. 'layer' will attempt to load 'template_layer_above' and 'template_layer_below' functions.
 * @param string $target Which layer to add it relative to, e.g. 'body' (overall page, outside the wrapper divs), etc. Leave empty to wrap around the 'context' layer (which doesn't accept any positioning, either.)
 * @param string $where Where should we add the layer? Check the comments inside the function for a fully documented list of positions.
 */
function loadLayer($layer, $target = 'context', $where = 'parent')
{
	global $context;

	/*
		This is the full list of $where possibilities.
		<layer> is $layer, <target> is $target, and <sub> is anything already inside <target>, block or layer.

		parent		wrap around the target (default)						<layer> <target> <sub /> </target> </layer>
		child		insert between the target and its current children		<target> <layer> <sub /> </layer> </target>

		replace		replace the layer but not its current contents			<layer>          <sub />           </layer>
		erase		replace the layer and empty its contents				<layer>                            </layer>

		before		add before the item										<layer> </layer> <target> <sub /> </target>
		after		add after the item										<target> <sub /> </target> <layer> </layer>

		firstchild	add as a child to the target, in first position			<target> <layer> </layer> <sub /> </target>
		lastchild	add as a child to the target, in last position			<target> <sub /> <layer> </layer> </target>
	*/

	// Not a valid layer..? Enter brooding mode.
	if (!isset($context['layers'][$target]) || !is_array($context['layers'][$target]))
		return;

	if ($where === 'parent' || $where === 'before' || $where === 'after' || $where === 'replace' || $where === 'erase')
	{
		skeleton_insert_layer($layer, $target, $where);
		if ($where === 'replace' || $where === 'erase')
			unset($context['layers'][$target]);
		return;
	}
	elseif ($where === 'child')
	{
		$context['layers'][$target] = array($layer => $context['layers'][$target]);
		$context['layers'][$layer] =& $context['layers'][$target][$layer];
		return;
	}
	elseif ($where === 'firstchild' || $where === 'lastchild')
	{
		if ($where === 'firstchild')
			$context['layers'][$target] = array_merge(array($layer => array()), $context['layers'][$target]);
		else
			$context['layers'][$target][$layer] = array();
		$context['layers'][$layer] =& $context['layers'][$target][$layer];
	}
}

function skeleton_insert_layer(&$source, $target = 'context', $where = 'parent')
{
	global $context;

	foreach ($context['layers'] as $id => &$lay)
	{
		if (isset($lay[$target]) && is_array($lay[$target]))
		{
			$dest =& $lay;
			break;
		}
	}
	if (!isset($dest) && isset($context['layers']['context']))
		$dest =& $context['layers']['context'];
	if (!isset($dest))
		return;

	$temp = array();
	foreach ($dest as $key => $value)
	{
		if ($key === $target)
		{
			if ($where === 'after')
				$temp[$key] = $value;
			$temp[$source] = $where === 'parent' ? array($key => $value) : ($where === 'erase' ? array() : ($where === 'replace' ? $value : array()));
			if ($where === 'before')
				$temp[$key] = $value;
		}
		else
			$temp[$key] = $value;
	}

	$dest = $temp;
	build_skeleton_indexes();
}

// Helper function to remove a block from the page. Works on any layer.
function removeBlock($block)
{
	global $context;

	foreach ($context['layers'] as $id => &$layer)
	{
		if (isset($layer[$block]))
		{
			unset($context['layers'][$id][$block]);
			break;
		}
	}
}

// Helper function to remove a layer from the page.
function removeLayer($layer)
{
	global $context;

	// Determine whether removing this layer would also remove the context layer. Which you may not.
	$current = 'context';
	$loop = true;
	while ($loop)
	{
		$loop = false;
		foreach ($context['layers'] as $id => &$curlay)
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

	// This isn't a direct parent of 'context', so we can safely remove it.
	unset($context['layers'][$layer]);
	return true;
}

?>