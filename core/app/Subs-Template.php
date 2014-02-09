<?php
/**
 * The templating code, aka rendering process.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
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
	global $context, $settings;
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
		if (empty($context['page_title_html_safe']))
			$context['page_title_html_safe'] = empty($context['page_title']) ? '' : westr::htmlspecialchars(un_htmlspecialchars(strip_tags($context['page_title'])), ENT_COMPAT, false, false);

		// Start up the session URL fixer. Don't do it in SSI, as it did it already.
		if (!defined('WEDGE') || WEDGE != 'SSI')
			ob_start('ob_sessrewrite');

		// Run any possible extra output buffers as provided by plugins.
		if (!empty($context['output_buffers']) && is_string($context['output_buffers']))
			$buffers = explode(',', $context['output_buffers']);
		elseif (!empty($context['output_buffers']))
			$buffers = $context['output_buffers'];
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
					if (!empty($fun[2]))
						loadPluginSource($fun[2], $fun[1]);
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
 * - If SCRIPT is empty, or no session id (e.g. SSI), exit.
 * - If ?debug has been specified previously, re-inject it back into the page's canonical reference.
 * - We also do our Pretty URLs voodoo here...
 *
 * @param string $buffer The contents of the output buffer thus far. Managed by PHP during the relevant ob_*() calls.
 * @return string The modified buffer.
 */
function ob_sessrewrite($buffer)
{
	global $settings, $context, $session_var, $board_info, $is_output_buffer;
	global $txt, $time_start, $db_count, $cached_urls, $use_cache, $members_groups;

	// Just quit if SCRIPT is set to nothing, or the SID is not defined. (SSI?)
	if (SCRIPT == '' || !defined('SID'))
		return $buffer;

	$is_output_buffer = true;
	if (!empty($context['show_load_time']))
	{
		$old_db_count = $db_count;
		$old_load_time = microtime(true);
	}

	// Very fast on-the-fly replacement of <URL>...
	$buffer = str_replace('<URL>', SCRIPT, $buffer);

	if (isset($context['meta_description'], $context['meta_description_repl']))
		$buffer = str_replace($context['meta_description'], $context['meta_description_repl'], $buffer);

	// A regex-ready SCRIPT, useful later.
	$preg_scripturl = preg_quote(SCRIPT, '~');

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

	$this_pos = strpos($buffer, '<!-- insert inline events here -->');
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

		$buffer = substr_replace($buffer, $thing, $this_pos, 34);
	}

	$buffer = strtr($buffer, "\x0f\x10", '"\'');

	/*
		Soft-merging forum posts.
		This relies on the msg mini-skeleton having a similar structure to the default's.
	*/
	if ((!defined('SKIN_MOBILE') || !SKIN_MOBILE) && strpos($buffer, '<we:msg_') !== false)
	{
		$ex_uid = $ex_area = $area = $one_removed = '';
		$is_forum = isset($board_info['type']) && $board_info['type'] == 'forum';

		// First, find all potential messages in this page...
		preg_match_all('~<we:msg [^>]*id="([^"]+)" class="([^"]+)"[^>]*>(.*?)</we:msg>~s', $buffer, $messages, PREG_SET_ORDER);
		foreach ($messages as $msg)
		{
			// Blog posts aren't soft-mergeable.
			if (!$is_forum && strpos($msg[2], 'first-post') !== false)
				continue;

			// Find the author ID for the current post, and isolate the post's content.
			preg_match('~data-id="(\d+)" class="[^"]*umme~', $msg[3], $uid);
			preg_match('~<we:msg_entry>(.*?)</we:msg_entry>~s', $msg[3], $area);

			// Do we need soft merging?
			if ($ex_uid == $uid)
			{
				// Remove colored backgrounds and signature, keep the ID and classes (for JS mostly), and move the post area to the previous area, in a special div.
				$area[0] = str_replace('<we:msg_entry>', '<we:msg_entry class="merged">', $ex_area[0])
					. '<we:msg_entry class="merged' . (empty($msg[2]) ? '' : ' ' . $msg[2]) . '"' . (empty($msg[1]) ? '' : ' id="' . $msg[1] . '"') . '>' . $area[1] . '</we:msg_entry>';

				$buffer = str_replace(array($msg[0], $ex_area[0]), array('<!REMOVED>', $area[0]), $buffer);
				$one_removed = true;
			}
			else
				$ex_uid = $uid;

			$ex_area = $area;
		}
		// Remove any extra separators.
		if ($one_removed)
			$buffer = preg_replace('~\s*<hr[^>]*>\s*<!REMOVED>~', '', $buffer);
	}

	/*
		Macro magic!
	*/

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

				// Have we got any parameters?
				if (strpos($body, '{') !== false)
				{
					preg_match_all('~([a-z][^\s="]*)="([^"]+)"~', substr($buffer, $p, $gt - $p), $params);

					// ...And replace with the contents.
					if (!empty($params))
						foreach ($params[1] as $id => $param)
							$body = str_replace('{' . $param . '}', strpos($params[2][$id], 'htmlsafe::') === 0 ? html_entity_decode(substr($params[2][$id], 10)) : $params[2][$id], $body);
				}

				// Remove <if> and its contents if the param is not used in the template. Otherwise, clean up the <if> tag...
				if ($macro['has_if'])
					while (preg_match_all('~<if:([^>]+)>((?' . '>[^<]+|<(?!/?if:\\1>))*?)</if:\\1>~i', $body, $ifs, PREG_SET_ORDER))
						foreach ($ifs as $ifi)
							$body = str_replace($ifi[0], !empty($params) && in_array($ifi[1], $params[1]) ? $ifi[2] : '', $body);

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
	$buffer = str_replace('<URL>', SCRIPT, $buffer);

	if (isset($context['ob_replacements']))
		$buffer = str_replace(array_keys($context['ob_replacements']), array_values($context['ob_replacements']), $buffer);

	// Load cached membergroup ids.
	if (($members_groups = cache_get_data('member-groups', 5000)) === null)
	{
		// First get the possible groups we could be working with. Saves us doing a join and potentially getting a result of members size * 2...
		$possible_groups = array();
		$request = wesql::query('
			SELECT g.id_group
			FROM {db_prefix}membergroups AS g
			WHERE g.online_color != {string:blank} OR g.format != {string:blank}',
			array(
				'blank' => '',
			)
		);
		while ($row = wesql::fetch_row($request))
			$possible_groups[$row[0]] = true;
		wesql::free_result($request);

		// This is a SOFT change. We do NOT modify their account with it. There are good reasons for this.
		$ban_group = !empty($settings['ban_group']) && isset($possible_groups[$settings['ban_group']]) ? $settings['ban_group'] : 0;
		$ban_level = 100000; // can't be achieved.
		if ($ban_group)
		{
			$inf_levels = !empty($settings['infraction_levels']) ? unserialize($settings['infraction_levels']) : array();
			if (!empty($inf_levels['hard_ban']['enabled']))
				$ban_level = $inf_levels['hard_ban']['points'];
		}

		// Now get all the members.
		$members_groups = array();
		$request = wesql::query('
			SELECT m.id_member, ' . (!empty($ban_group) ? 'm.is_activated, m.warning, m.data, ' : '') . 'm.id_post_group, m.id_group
			FROM {db_prefix}members AS m',
			array(
				'blank' => '',
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if ($ban_group)
			{
				if ($row['is_activated'] >= 20 || $row['warning'] >= $ban_level)
				{
					$members_groups[$row['id_member']] = $ban_group;
					continue;
				}
				// Hmm, we couldn't do it quickly. Time to parse their data in the hopes of finding something applicable.
				if ($row['warning'] > 0)
				{
					$data = !empty($row['data']) ? unserialize($row['data']) : array();
					if (!empty($data['sanctions']['hard_ban']) && ($data['sanctions']['hard_ban'] == 1 || $data['sanctions']['hard_ban'] > time()))
					{
						$members_groups[$row['id_member']] = $ban_group;
						continue;
					}
				}
			}

			if (isset($possible_groups[$row['id_group']]))
				$members_groups[$row['id_member']] = $row['id_group'];
			elseif (isset($possible_groups[$row['id_post_group']]))
				$members_groups[$row['id_post_group']] = $row['id_post_group'];
		}
		wesql::free_result($request);
		cache_put_data('member-groups', $members_groups, 5000);
	}

	// If guests/users can't view user profiles, we might as well unlink them!
	if (!allowedTo('profile_view_any'))
		$buffer = preg_replace(
			'~<a\b[^>]+href="' . $preg_scripturl . '\?(?:[^"]+)?\baction=profile' . (we::$is_member && allowedTo('profile_view_own') ? ';(?:[^"]+;)?u=(?!' . we::$id . ')' : '') . '[^"]*"[^>]*>(.*?)</a>~',
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
		$buffer = preg_replace('~(?<!<link rel="canonical" href=")' . $preg_scripturl . '(?!\?' . preg_quote(SID, '~') . ')(?:\?|(?="))~', SCRIPT . '?' . SID . ';', $buffer);
		$buffer = str_replace('"' . SCRIPT . '?' . SID . ';"', '"' . SCRIPT . '?' . SID . '"', $buffer);
	}
	// Debugging templates, are we?
	elseif (isset($_GET['debug']))
		$buffer = preg_replace('~(?<!<link rel="canonical" href=")"' . $preg_scripturl . '\??~', SCRIPT . '?debug;', $buffer);

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
					$match = preg_replace(array('~=?;+~', '~\?&amp;~'), array(';', '?'), rtrim($match, '&?;'));
				}
				else
					$match = preg_replace('~=?;+~', ';', rtrim($match, '&?;'));
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
			$urls_query = array_flip(array_flip($urls_query));

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

			// If there are any uncached URLs, process them.
			if (count($uncached_urls) != 0)
			{
				// Run each filter callback function on each URL
				loadSource('PrettyUrls-Filters');

				foreach (array_filter($settings['pretty_filters']) as $id => $dummy)
				{
					$func = 'pretty_filter_' . $id;
					$func($uncached_urls);
				}

				// Fill the cached URLs array
				$cache_data = array();
				foreach ($uncached_urls as $url_id => $url)
				{
					if (!isset($url['replacement']))
						$url['replacement'] = $url['url'];
					$url['replacement'] = str_replace("\x12", "'", $url['replacement']);
					$url['replacement'] = preg_replace(array('~"~', '~=?;+~', '~\?;~'), array('%22', ';', '?'), rtrim($url['replacement'], '&?;'));
					$cached_urls[$url_id] = $url['replacement'];
					if ($use_cache && strlen($url_id) < 256)
						$cache_data[] = array($url_id, $url['replacement']);
				}

				// Cache these URLs in the database
				if ($use_cache && count($cache_data) > 0)
					wesql::insert('replace',
						'{db_prefix}pretty_urls_cache',
						array('url_id' => 'string', 'replacement' => 'string'),
						$cache_data
					);
			}

			// And finally, put them back into the buffer.
			foreach ($context['pretty']['patterns'] as $pattern)
				$buffer = preg_replace_callback($pattern, 'pretty_buffer_callback', $buffer);
		}
	}

	if (!empty($context['debugging_info']))
		$buffer = substr_replace($buffer, $context['debugging_info'], strrpos($buffer, '</ul>') + 5, 0);

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
	if (strpos($buffer, '<inden@zi=') !== false)
	{
		// We'll need to protect textareas and pre tags first, as these don't like inden@zi changes.
		preg_match_all('~(?:<textarea\b.*?</textarea>|<pre\b.*?</pre>)~s', $buffer, $protect);
		if (!empty($protect))
			$buffer = str_replace($protect[0], "\x18", $buffer);

		$max_loops = 100;
		while (strpos($buffer, '<inden@zi=') !== false && $max_loops-- > 0)
			$buffer = preg_replace_callback('~<inden@zi=([^=>]+)=(-?\d+)>(.*?)</inden@zi=\\1>~s', 'wedge_indenazi', $buffer);

		if (!empty($protect))
			foreach ($protect[0] as $item)
				$buffer = preg_replace("~\x18~", $item, $buffer, 1);
	}

	// The following hidden variable, 'minify_html', will remove tabs and thus please Google PageSpeed. Whatever.
	if (!empty($settings['minify_html']))
		$buffer = preg_replace("~\n\t+~", "\n", $buffer);

	// Can't be sure about mobile devices, but IE in WinXP doesn't
	// support Unicode 3.0 and its non-breakable half-spaces. Yay.
	if (we::$user['language'] != 'english' && we::is('ie && windows[-5.2],mobile'))
		$buffer = str_replace("\xe2\x80\xaf", "\xc2\xa0", $buffer);

	// Strip domain name out of internal links.
	if (empty($context['no_strip_domain']))
	{
		$buffer = preg_replace('~(<[^>]+\s(?:href|src|action)=")' . preg_quote(we::$user['server'], '~') . '/(?!/)~', '$1/', $buffer);

		// Strip protocol out of links that share it with the current page's URL. Makes oldIE go crazy, so no cookie for him.
		$strip_protocol = '(<[^>]+\s(?:href|src|action)=")' . preg_quote(substr(we::$user['server'], 0, strpos(we::$user['server'], '://')), '~') . '://';
		if (we::$browser['ie8down'])
			$buffer = preg_replace('~' . $strip_protocol . '((?:[^.]|\.(?!css))*?")~', '$1//$2', $buffer);
		else
			$buffer = preg_replace('~' . $strip_protocol . '~', '$1//', $buffer);
	}

	// The lesser of two evils. Add empty alt params to img tags that don't have them.
	// Takes bandwidth, but only does it for validator bots. They started the war.
	if (isset(we::$ua) && strpos(strtolower(we::$ua), 'validator') !== false)
		$buffer = preg_replace('~<img\s((?:[^a>]|a(?!lt\b))+)>~', '<img alt $1>', $buffer);

	// Return the changed buffer, and make a final optimization.
	return preg_replace("~\s</script>\s*<script>|\s<script>\s*</script>~", '', $buffer);
}

// Move inline events to the end
function wedge_event_delayer($match)
{
	global $context;
	static $eve = 1, $dupes = array();

	if ($eve == 1 && INFINITE)
		$eve = 100 * (isset($_GET['start']) ? $_GET['start'] / 15 : 0) + 1;

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
	global $members_groups;

	if (!isset($members_groups[$match[2]]) || strpos($match[1], 'bbc_link') !== false)
		return '<a' . $match[1] . '>' . $match[3] . '</a>';

	$pos = strpos($match[1], 'class="');
	if ($pos > 0)
		return '<a' . substr($match[1], 0, $pos + 7) . 'group' . $members_groups[$match[2]] . ' ' . substr($match[1], $pos + 7) . '>' . $match[3] . '</a>';
	else
		return '<a' . $match[1] . ' class="group' . $members_groups[$match[2]] . '">' . $match[3] . '</a>';
}

function wedge_indenazi($match)
{
	if ($match[2] < 0)
		return preg_replace('~(\n\t*?)\t' . ($match[2] < -1 ? '{1,' . -$match[2] . '}' : '') . '(?=[<a-zA-Z0-9])~', '$1', $match[3]);
	return preg_replace('~(\n\t*)(?=[<a-zA-Z0-9])~', '$1' . str_repeat("\t", $match[2]), $match[3]);
}

// A callback function to replace the buffer's URLs with their cached URLs
function pretty_buffer_callback($matches)
{
	global $cached_urls, $use_cache, $session_var;
	static $immediate_cache = array();

	if (isset($immediate_cache[$matches[0]]))
		return $immediate_cache[$matches[0]];

	if ($use_cache)
	{
		// Store the parts of the URL that won't be cached so they can be inserted later
		$has_sid = SID && strpos($matches[1], SID) !== false;
		$has_sesc = strpos($matches[1], $session_var) !== false;
		$url_id = preg_replace(
			'~=?;+~',
			';',
			str_replace(
				array('"', '?;', SID, $session_var),
				array('%22', '?', '', ''),
				rtrim($matches[1], '&?;')
			)
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
		$url_id = str_replace(array('"', '?;'), array('%22', '?'), preg_replace('~=?;+~', ';', rtrim($matches[1], '&?;')));
		// Stitch everything back together
		$replacement = isset($cached_urls[$url_id]) ? $cached_urls[$url_id] : $url_id;
	}

	$immediate_cache[$matches[0]] = $replacement;
	if (empty($replacement) || $replacement[0] == '?')
		$replacement = SCRIPT . $replacement;
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
	if (!$skip_full || !empty($settings['pretty_enable_filters']))
		ob_start('ob_sessrewrite');
}

/**
 * Ensures content above the main page content is loaded, including HTTP page headers.
 *
 * Several things happen here.
 * - {@link setupThemeContext()} is called to get some key values.
 * - Issue HTTP headers that cause browser-side caching to be turned off (old expires and last modified). This is turned off for attachments errors, though.
 * - Issue MIME type header
 */
function start_output()
{
	global $settings, $context;

	if (!AJAX)
		setupThemeContext();

	// Print stuff to prevent caching of pages (except on attachment errors, etc.)
	if (empty($context['no_last_modified']))
	{
		header('Expires: Wed, 25 Aug 2010 17:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

		if (!AJAX)
			header('Content-Type: text/html; charset=UTF-8');
	}

	header('Content-Type: text/' . (AJAX ? 'xml' : 'html') . '; charset=UTF-8');

	$context['show_load_time'] = !empty($settings['timeLoadPageEnable']);
}

/**
 * Use the opportunity to show some potential errors while we're showing the top or default layer...
 *
 * - If using a conventional template (with body or main layers), and the user is an admin, check whether certain files are present, and if so give the admin a warning. These include the installer, repair-settings and backups of the Settings files (with php~ extensions)
 * - If the user is post-banned, provide a nice warning for them.
 */
function while_we_re_here()
{
	global $txt, $settings, $context, $boarddir, $cachedir;
	static $checked_security_files = false, $showed_banned = false, $showed_behav_error = false;

	// If this page was loaded through jQuery, it's likely we've already had the warning shown in its container...
	if (AJAX)
		return;

	// May seem contrived, but this is done in case the body and default layer aren't there...
	// Was there a security error for the admin?
	if (!$showed_behav_error && we::$is_admin && !empty($context['behavior_error']))
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
	elseif (!$checked_security_files && we::$is_member && allowedTo('admin_forum'))
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
	elseif (!$showed_banned && (!empty(we::$user['post_banned']) || !empty(we::$user['pm_banned'])))
	{
		$showed_banned = true;
		$str = !empty(we::$user['post_banned']) ? (!empty(we::$user['pm_banned']) ? $txt['you_are_post_pm_banned'] : $txt['you_are_post_banned']) : $txt['you_are_pm_banned'];
		echo '
			<div class="windowbg wrc alert" style="margin: 2ex; padding: 2ex; border: 2px dashed red">
				', sprintf($str, we::$is_guest ? $txt['guest_title'] : we::$user['name']);

		if (!empty(we::$user['data']['ban_reason']))
			echo '
				<div style="padding-left: 4ex; padding-top: 1ex">', we::$user['data']['ban_reason'], '</div>';

		$expiry = array();
		foreach (array('post_ban', 'pm_ban') as $item)
			if (!empty(we::$user['sanctions'][$item]))
				$expiry[] = we::$user['sanctions'][$item];
		$expiry_time = min($expiry);
		if ($expiry_time != 1)
			echo '
				<div>', sprintf($txt['your_ban_expires'], timeformat($expiry_time, false)), '</div>';
		else
			echo '
				<div>', $txt['your_ban_expires_never'], '</div>';

		echo '
			</div>';
	}
}

/**
 * Display the debug data at the foot of the page if debug mode ($db_show_debug) is set, and not in the query viewer page.
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
	global $context, $boarddir, $settings, $txt;
	global $db_cache, $db_count, $db_show_debug, $cache_count, $cache_hits;

	// Is debugging on? (i.e. it is set, and it is true, and we're not on action=viewquery or an help popup.
	$show_debug = $show_debug_query = !empty($db_show_debug) && $context['action'] !== 'viewquery' && $context['action'] !== 'help';

	// Check groups
	if (empty($settings['db_show_debug_who']) || $settings['db_show_debug_who'] == 'admin')
		$show_debug &= we::$is_admin;
	elseif ($settings['db_show_debug_who'] == 'mod')
		$show_debug &= allowedTo('moderate_forum');
	elseif ($settings['db_show_debug_who'] == 'regular')
		$show_debug &= we::$is_member;
	else
		$show_debug &= $settings['db_show_debug_who'] == 'any';

	// Now, who can see the query log?
	if (empty($settings['db_show_debug_who_log']) || $settings['db_show_debug_who_log'] == 'admin')
		$show_debug_query &= we::$is_admin;
	elseif ($settings['db_show_debug_who_log'] == 'mod')
		$show_debug_query &= allowedTo('moderate_forum');
	elseif ($settings['db_show_debug_who_log'] == 'regular')
		$show_debug_query &= we::$is_member;
	else
		$show_debug_query &= $settings['db_show_debug_who_log'] == 'any';

	// Now, let's tidy this up. If we're not showing queries, make sure anything that was logged is gone.
	if (!$show_debug_query)
	{
		unset($_SESSION['debug'], $db_cache);
		$_SESSION['view_queries'] = 0;
		if (!$show_debug)
			return;
	}

	loadLanguage('Stats');

	if (empty($_SESSION['view_queries']))
		$_SESSION['view_queries'] = 0;

	$temp = '
	<div id="junk">';

	if ($show_debug)
	{
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

		// A small trick to avoid repeating block names ad nauseam...
		foreach ($context['debug']['blocks'] as $name => $count)
			$context['debug']['blocks'][$name] = $count > 1 ? $name . ' (' . $count . 'x)' : $name;

		$show_list_js = "$(this).hide().next().show(); return false;";
		$temp .= sprintf(
			$txt['debug_report'],
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
		$temp .= '<br>';
	}

	$warnings = 0;
	if (!empty($db_cache))
	{
		foreach ($db_cache as $q => $qq)
			if (!empty($qq['w']))
				$warnings += count($qq['w']);

		$_SESSION['debug'] =& $db_cache;
	}

	if ($show_debug_query)
		$temp .= '<a href="' . SCRIPT . '?action=viewquery" target="_blank" class="new_win">' . sprintf($txt['debug_queries_used' . ($warnings == 0 ? '' : '_and_warnings')], $db_count, $warnings) . '</a> - <a href="' . SCRIPT . '?action=viewquery;sa=hide">' . $txt['debug_' . (empty($_SESSION['view_queries']) ? 'show' : 'hide') . '_queries'] . '</a>';
	else
		$temp .= sprintf($txt['debug_queries_used'], $db_count);

	if ($_SESSION['view_queries'] == 1 && !empty($db_cache))
	{
		$temp .= '<br><br>';

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
	<strong>' . ($is_select ? '<a href="' . SCRIPT . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '" target="_blank" class="new_win">' : '') . westr::nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', htmlspecialchars(ltrim($qq['q'], "\n\r")))) . ($is_select ? '</a></strong>' : '</strong>') . '<br>
	&nbsp;&nbsp;&nbsp;';
			if (!empty($qq['f']) && !empty($qq['l']))
				$temp .= sprintf($txt['debug_query_in_line'], $qq['f'], $qq['l']);

			if (isset($qq['s'], $qq['t'], $txt['debug_query_which_took_at']))
				$temp .= sprintf($txt['debug_query_which_took_at'], round($qq['t'], 8), round($qq['s'], 8)) . '<br>';
			elseif (isset($qq['t']))
				$temp .= sprintf($txt['debug_query_which_took'], round($qq['t'], 8)) . '<br>';
			$temp .= '<br>';
		}
	}

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
	global $context, $txt, $settings, $boarddir, $boardurl;
	global $maintenance, $mtitle, $mmessage;
	static $templates = array();

	// We want to be able to figure out any errors...
	ini_set('track_errors', '1');

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
		clean_output();

		// Don't cache error pages!!
		header('Expires: Wed, 25 Aug 2010 17:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		if (!isset($txt['template_parse_error']))
			loadLanguage('Errors', '', false);

		if (!isset($txt['template_parse_error']))
		{
			$txt['template_parse_error'] = 'Template Parse Error!';
			$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system. This problem should only be temporary, so please come back later and try again. If you continue to see this message, please contact the administrator.<br><br>You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
			$txt['template_parse_error_details'] = 'There was a problem loading the <tt><strong>%1$s</strong></tt> template or language file. Please check the syntax and try again - remember, single quotes (<tt>\'</tt>) often have to be escaped with a slash (<tt>\\</tt>). To see more specific error information from PHP, try <a href="{board_url}%1$s" class="extern">accessing the file directly</a>.<br><br>You may want to try to <a href="javascript:location.reload();">refresh this page</a>.';
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

					if (strpos($line, '<br>') === false)
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
 * @param mixed $template_name Name of a template file to load from the current template folder (with .template.php suffix), falling back to locating it in the default template directory. Alternatively if loading stylesheets only, supply boolean false instead.
 * @param bool $fatal Whether to exit execution with a fatal error if the template file could not be loaded. (Note: this is never used in the Wedge code base.)
 * @return bool Returns true on success, false on failure (assuming $fatal is false; otherwise the fatal error will suspend execution)
 */
function loadTemplate($template_name, $fatal = true)
{
	global $context, $settings, $txt, $db_show_debug;

	// No template to load?
	if ($template_name === false)
		return true;

	$loaded = false;
	foreach ($context['template_folders'] as $template_dir)
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
		if (!empty($db_show_debug))
			$context['debug']['templates'][] = $template_name . ' (' . basename($template_dir) . ')';

		// If they have specified an initialization function for this template, go ahead and call it now.
		if (function_exists('template_' . $template_name . '_init'))
			call_user_func('template_' . $template_name . '_init');
	}
	// Hmmm... doesn't exist?! I don't suppose the directory is wrong, is it?
	// !! @todo: remove this..?
	elseif (!file_exists(TEMPLATES_DIR) && file_exists(CORE_DIR . '/html'))
	{
		$context['template_folders'][] = $settings['theme_dir'] = CORE_DIR . '/html';

		if (we::$is_admin)
		{
			loadLanguage('Errors');
			echo '
<div class="alert errorbox">
	<a href="', SCRIPT, '?action=admin;area=featuresettings;sa=paths;', $context['session_query'], '" class="alert">', $txt['theme_dir_wrong'], '</a>
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
			exit(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load core/html/%s.template.php!', (string) $template_name), 'template'));
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
	global $context, $txt, $db_show_debug;

	if (empty($block_name))
		return;

	if (!empty($db_show_debug))
		$context['debug']['blocks'][$block_name] = isset($context['debug']['blocks'][$block_name]) ? $context['debug']['blocks'][$block_name] + 1 : 1;

	if (strpos($block_name, ':') !== false)
	{
		list ($block_name, $vars) = explode(':', $block_name, 2);
		$vars = array_map('trim', explode(',', $vars));
	}
	else
		$vars = array();

	// Figure out what the template function is named.
	$theme_function = 'template_' . $block_name;

	if (isset($context['template_befores'][$theme_function]))
	{
		$func =& $context['template_befores'][$theme_function];
		if (is_array($func))
			$func = create_function($func[0], $func[1]);
		call_user_func_array($func, $vars);
	}
	// !!! Doing these tests is relatively slow, but there aren't that many. In case performance worsens,
	// !!! we should cache the function list (get_defined_functions()) and isset() against the cache.
	elseif (function_exists($theme_function_before = $theme_function . '_before'))
		call_user_func_array($theme_function_before, $vars);

	if (isset($context['template_overrides'][$theme_function]))
	{
		$func =& $context['template_overrides'][$theme_function];
		if (is_array($func))
			$func = create_function($func[0], $func[1]);
		call_user_func_array($func, $vars);
	}
	elseif (function_exists($theme_function_override = $theme_function . '_override'))
		call_user_func_array($theme_function_override, $vars);
	elseif (function_exists($theme_function))
		call_user_func_array($theme_function, $vars);
	elseif ($fatal === false)
		fatal_lang_error('template_block_error', 'template', array((string) $block_name));
	elseif ($fatal !== 'ignore')
		exit(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['template_block_error'] : 'Unable to load the "%s" template block!', (string) $block_name), 'template'));

	if (isset($context['template_afters'][$theme_function]))
	{
		$func =& $context['template_afters'][$theme_function];
		if (is_array($func))
			$func = create_function($func[0], $func[1]);
		call_user_func_array($func, $vars);
	}
	elseif (function_exists($theme_function_after = $theme_function . '_after'))
		call_user_func_array($theme_function_after, $vars);

	// Are we showing debugging for templates? Just make sure not to do it before the doctype...
	if (allowedTo('admin_forum') && isset($_REQUEST['debug']) && $block_name !== 'init' && ob_get_length() > 0 && !AJAX)
		echo '
<div style="font-size: 8pt; border: 1px dashed red; background: orange; text-align: center; font-weight: bold">---- ', $block_name, ' ends ----</div>';
}
