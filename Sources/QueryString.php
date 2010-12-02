<?php
/**********************************************************************************
* QueryString.php                                                                 *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC4                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

/**
 * This file handles functions that manage the output buffer, query string, and incoming sanitation thereof, amongst other things.
 *
 * @package wedge
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Cleans all of the environment variables going into this request.
 *
 * By the time we're done, everything should have slashes (regardless of php.ini).
 *
 * - Defines $scripturl (as $boardurl . '/index.php')
 * - Identifies which function to run to handle magic_quotes.
 * - Removes $HTTP_POST_* if set.
 * - Aborts if someone is trying to set $GLOBALS via $_REQUEST or the cookies (in the case of register_globals being on)
 * - Aborts if someone is trying to use numeric keys (e.g. index.php?1=2) in $_POST, $_GET or $_FILES and dumps them if found in $_COOKIE.
 * - Ensure we have the current querystring, and that it's valid.
 * - Check if the server is using ; as the separator, and parse the URL if not.
 * - Cleans input strings dependent on magic_quotes settings.
 * - Process everything in $_GET to ensure it all has entities.
 * - Rebuild $_REQUEST to be $_POST and $_GET only (never $_COOKIE)
 * - Check if $topic and $board are set and push them into the global space.
 * - Check for other stuff in $_REQUEST like a start, or action and deal with the types appropriately.
 * - Try to get the requester's IP address and make sure we have a USER-AGENT and we have the requested URI.
 */
function cleanRequest()
{
	global $board, $topic, $boardurl, $scripturl, $modSettings, $smcFunc, $context, $pretty_request, $pretty_board;

/*	// Makes it easier to refer to things this way.
	if (!empty($modSettings['pretty_enable_filters']))
	{
		$boardurl = 'http://' . $_SERVER['HTTP_HOST'];
		// !!! WIP
/*		if (strpos($boardurl, '.noisen.com') !== false)
			$boardurl = 'http://noisen.com';
		elseif (strpos($boardurl, '.geeld.org') !== false)
			$boardurl = 'http://geeld.org'; */
/*		$scripturl = $boardurl . (isset($_COOKIE[session_name()]) ? '/' : '/index.php');
	}
	else */
	$scripturl = $boardurl . '/index.php';

	// What function to use to reverse magic quotes - if sybase is on we assume that the database sensibly has the right unescape function!
	$removeMagicQuoteFunction = @ini_get('magic_quotes_sybase') || strtolower(@ini_get('magic_quotes_sybase')) == 'on' ? 'unescapestring__recursive' : 'stripslashes__recursive';

	// Save some memory.. (since we don't use these anyway.)
	unset($GLOBALS['HTTP_POST_VARS'], $GLOBALS['HTTP_POST_VARS']);
	unset($GLOBALS['HTTP_POST_FILES'], $GLOBALS['HTTP_POST_FILES']);

	// These keys shouldn't be set...ever.
	if (isset($_REQUEST['GLOBALS']) || isset($_COOKIE['GLOBALS']))
		die('Invalid request variable.');

	// Same goes for numeric keys.
	foreach (array_merge(array_keys($_POST), array_keys($_GET), array_keys($_FILES)) as $key)
		if (is_numeric($key))
			die('Numeric request keys are invalid.');

	// Numeric keys in cookies are less of a problem. Just unset those.
	foreach ($_COOKIE as $key => $value)
		if (is_numeric($key))
			unset($_COOKIE[$key]);

	// Get the correct query string.  It may be in an environment variable...
	if (!isset($_SERVER['QUERY_STRING']))
		$_SERVER['QUERY_STRING'] = getenv('QUERY_STRING');

	// It seems that sticking a URL after the query string is mighty common, well, it's evil - don't.
	if (strpos($_SERVER['QUERY_STRING'], 'http') === 0)
	{
		header('HTTP/1.1 400 Bad Request');
		die;
	}

	// Are we going to need to parse the ; out?
	if (strpos(@ini_get('arg_separator.input'), ';') === false && !empty($_SERVER['QUERY_STRING']))
	{
		// Get rid of the old one! You don't know where it's been!
		$_GET = array();

		// Was this redirected? If so, get the REDIRECT_QUERY_STRING.
		$_SERVER['QUERY_STRING'] = urldecode(substr($_SERVER['QUERY_STRING'], 0, 5) === 'url=/' ? $_SERVER['REDIRECT_QUERY_STRING'] : $_SERVER['QUERY_STRING']);

		// Replace ';' with '&' and '&something&' with '&something=&'. (This is done for compatibility...)
		// !!! smflib
		parse_str(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr($_SERVER['QUERY_STRING'], array(';?' => '&', ';' => '&', '%00' => '', "\0" => ''))), $_GET);

		// Magic quotes still applies with parse_str - so clean it up.
		if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0 && empty($modSettings['integrate_magic_quotes']))
			$_GET = $removeMagicQuoteFunction($_GET);
	}
	elseif (strpos(@ini_get('arg_separator.input'), ';') !== false)
	{
		if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0 && empty($modSettings['integrate_magic_quotes']))
			$_GET = $removeMagicQuoteFunction($_GET);

		// Search engines will send action=profile%3Bu=1, which confuses PHP.
		foreach ($_GET as $k => $v)
		{
			if (is_string($v) && strpos($k, ';') !== false)
			{
				$temp = explode(';', $v);
				$_GET[$k] = $temp[0];

				for ($i = 1, $n = count($temp); $i < $n; $i++)
				{
					@list ($key, $val) = @explode('=', $temp[$i], 2);
					if (!isset($_GET[$key]))
						$_GET[$key] = $val;
				}
			}

			// This helps a lot with integration!
			if (strpos($k, '?') === 0)
			{
				$_GET[substr($k, 1)] = $v;
				unset($_GET[$k]);
			}
		}
	}

	// There's no query string, but there is a URL... try to get the data from there.
	if (!empty($_SERVER['REQUEST_URI']))
	{
		// Remove the .html, assuming there is one.
		if (substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '.'), 4) == '.htm')
			$request = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '.'));
		else
			$request = $_SERVER['REQUEST_URI'];

		// !!! smflib.
		// Replace 'index.php/a,b,c/d/e,f' with 'a=b,c&d=&e=f' and parse it into $_GET.
		if (strpos($request, basename($scripturl) . '/') !== false)
		{
			parse_str(substr(preg_replace('/&(\w+)(?=&|$)/', '&$1=', strtr(preg_replace('~/([^,/]+),~', '/$1=', substr($request, strpos($request, basename($scripturl)) + strlen(basename($scripturl)))), '/', '&')), 1), $temp);
			if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0 && empty($modSettings['integrate_magic_quotes']))
				$temp = $removeMagicQuoteFunction($temp);
			$_GET += $temp;
		}
	}

	if (!empty($modSettings['pretty_enable_filters']))
	{
		// !!! Authorize URLs like noisen.com:80
		//	$_SERVER['HTTP_HOST'] = strpos($_SERVER['HTTP_HOST'], ':') === false ? $_SERVER['HTTP_HOST'] : substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
		$pretty_request = $_SERVER['HTTP_HOST'] . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/EMPTY');
		$ph = strpos($_SERVER['HTTP_HOST'], '.noisen.com'); // !!! WIP
		$hh = substr($_SERVER['HTTP_HOST'], 0, $ph > 0 ? $ph : strlen($_SERVER['HTTP_HOST']));

		$query = wedb::query('
			SELECT id_board, url
			FROM {db_prefix}boards AS b
			WHERE urllen >= {int:len}
			AND url = SUBSTRING({string:url}, 1, urllen)
			ORDER BY urllen DESC LIMIT 1',
			array(
				'url' => rtrim($pretty_request, '/'),
				'len' => ($len = strpos($pretty_request, '/')) !== false ? $len : strlen($pretty_request),
			)
		);
		if (wedb::num_rows($query) == 0)
			$_GET['board'] = $board = 0;
		else
		{
			$pretty_board = wedb::fetch_assoc($query);

			// The happy place where boards are identified.
			$_GET['board'] = $board = $pretty_board['id_board'];
			$_SERVER['HTTP_HOST'] = $pretty_board['url'];
			$_SERVER['REQUEST_URI'] = $ru = str_replace($pretty_board['url'], '', $pretty_request);

			// We will now be analyzing the request URI to find our topic ID and various options...

			if (isset($_GET['topic']))
			{
				// If we've got a topic ID, we can recover the board ID easily!
			}
			// URL: /2010/12/25/?something or /2010/p15/ (get all topics from Christmas 2010, or page 2 of all topics from 2010)
			elseif (preg_match('~^/(2\d{3}(?:/\d{2}(?:/[0-3]\d)?)?)(?:/p(\d+))?~', $ru, $m))
			{
				$_GET['mois'] = str_replace('/', '', $m[1]);
				$_GET['start'] = empty($m[2]) ? 0 : $m[2];
				$_GET['pretty'] = 1;
			}
			// URL: /1234/topic/new/?something or /1234/topic/2/?something (get topic ID 1234, named 'topic')
			elseif (preg_match('~^/(\d+)/(?:[^/]+)/(\d+|msg\d+|from\d+|new)?~u', $ru, $m))
			{
				$_GET['topic'] = $m[1];
				$_GET['start'] = empty($m[2]) ? 0 : $m[2];
				$_GET['pretty'] = 1;
			}
			// URL: /cat/hello/?something or /tag/me/p15/ (get all topics from category 'hello', or page 2 of all topics with tag 'me')
			elseif (preg_match('~^/(cat|tag)/([^/]+)(?:/p(\d+))?~u', $ru, $m))
			{
				$_GET[$m[1]] = $m[2];
				$_GET['start'] = empty($m[3]) ? 0 : $m[3];
				$_GET['pretty'] = 1;
			}
			// URL: /p15/ (board index, page 2)
			elseif (preg_match('~^/p(\d+)~', $ru, $m))
			{
				$_GET[$m[1]] = $m[2];
				$_GET['start'] = empty($m[3]) ? 0 : $m[3];
				$_GET['pretty'] = 1;
			}
			elseif ($hh == 'my')
			{
				if (empty($_GET['user']))
					unset($_GET['user']);
				else
					$_GET['user'] = rtrim($_GET['user'], '/');
			}
			elseif ($hh == 'media' || $hh == 'admin' || $hh == 'pm')
			{
				// !!! WIP (supporting gallery, admin and pm areas.)
			}

			// Plug-ins may want to play with their own URL system.
			call_hook('determine_location', array($pretty_board));
		}
		wedb::free_result($query);
	}

	// If magic quotes are on, we have some work to do...
	if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0)
	{
		$_ENV = $removeMagicQuoteFunction($_ENV);
		$_POST = $removeMagicQuoteFunction($_POST);
		$_COOKIE = $removeMagicQuoteFunction($_COOKIE);
		foreach ($_FILES as $k => $dummy)
			if (isset($_FILES[$k]['name']))
				$_FILES[$k]['name'] = $removeMagicQuoteFunction($_FILES[$k]['name']);
	}

	// Add entities to GET.  This is kinda like the slashes on everything else.
	$_GET = htmlspecialchars__recursive($_GET);

	// Let's not depend on the ini settings... why even have COOKIE in there, anyway?
	$_REQUEST = $_POST + $_GET;

	// Make sure $board and $topic are numbers.
	if (isset($_REQUEST['board']))
	{
		// Make sure it's a string and not something else like an array
		$_REQUEST['board'] = (string) $_REQUEST['board'];

		// If there's a slash in it, we've got a start value!
		if (strpos($_REQUEST['board'], '/') !== false && strpos($_REQUEST['board'], $_SERVER['HTTP_HOST']) === false)
			list ($_REQUEST['board'], $_REQUEST['start']) = explode('/', $_REQUEST['board']);
		// Same idea, but dots.  This is the currently used format - ?board=1.0...
		elseif (strpos($_REQUEST['board'], '.') !== false)
		{
			list ($reqboard, $reqstart) = explode('.', $_REQUEST['board']);
			if (is_numeric($reqboard) && is_numeric($reqstart))
			{
				$_REQUEST['board'] = $reqboard;
				$_REQUEST['start'] = $reqstart;
			}
		}
		// Now make absolutely sure it's a number.
		// Check for pretty board URLs too, and possibly redirect if oldschool queries were used.
		if (is_numeric($_REQUEST['board']))
		{
			$board = (int) $_REQUEST['board'];
			if (!isset($_REQUEST['pretty']))
				$context['pretty']['oldschoolquery'] = true;
		}
		else
			$board = 0;

		// This is for "Who's Online" because it might come via POST - and it should be an int here.
		$_GET['board'] = $board;
	}
	// Well, $board is going to be a number no matter what.
	else
		$board = 0;

	// If there's a threadid, it's probably an old YaBB SE link.  Flow with it.
	if (isset($_REQUEST['threadid']) && !isset($_REQUEST['topic']))
		$_REQUEST['topic'] = $_REQUEST['threadid'];

	// We've got topic!
	if (isset($_REQUEST['topic']))
	{
		// Make sure it's a string and not something else like an array
		$_REQUEST['topic'] = (string) $_REQUEST['topic'];

		// Slash means old, beta style, formatting.  That's okay though, the link should still work.
		if (strpos($_REQUEST['topic'], '/') !== false)
			list ($_REQUEST['topic'], $_REQUEST['start']) = explode('/', $_REQUEST['topic']);
		// Dots are useful and fun ;).  This is ?topic=1.15.
		elseif (strpos($_REQUEST['topic'], '.') !== false)
			list ($_REQUEST['topic'], $_REQUEST['start']) = explode('.', $_REQUEST['topic']);

		// Check for pretty topic URLs, and possibly redirect if oldschool queries were used.
		if (is_numeric($_REQUEST['topic']))
		{
			$topic = (int) $_REQUEST['topic'];
			if (!isset($_REQUEST['pretty']))
				$context['pretty']['oldschoolquery'] = true;
		}
		else
		{
			$_REQUEST['topic'] = str_replace(array('&#039;', '&#39;', '\\'), array(chr(18), chr(18), ''), $_REQUEST['topic']);
			$_REQUEST['topic'] = preg_replace('`([\x80-\xff])`e', 'sprintf(\'%%%x\', ord(\'$1\'))', $_REQUEST['topic']);
			// Are we feeling lucky?
			$query = wedb::query('
				SELECT p.id_topic, t.id_board
				FROM {db_prefix}pretty_topic_urls AS p
				INNER JOIN {db_prefix}topics AS t ON p.id_topic = t.id_topic
				INNER JOIN {db_prefix}boards AS b ON b.id_board = t.id_board
				WHERE p.pretty_url = {string:pretty}
				AND b.url = {string:url}
				LIMIT 1', array(
					'pretty' => $_REQUEST['topic'],
					'url' => $_SERVER['HTTP_HOST']
				));
			// No? No topic?!
			if (wedb::num_rows($query) == 0)
				$topic = 0;
			else
				list ($topic, $board) = wedb::fetch_row($query);
			wedb::free_result($query);

			// That query should be counted separately
			$context['pretty']['db_count']++;
		}

		// Now make sure the online log gets the right number.
		$_GET['topic'] = $topic;
	}
	else
		$topic = 0;

	unset($_REQUEST['pretty'], $_GET['pretty']);

	// There should be a $_REQUEST['start'], some at least.  If you need to default to other than 0, use $_GET['start'].
	if (empty($_REQUEST['start']) || $_REQUEST['start'] < 0)
		$_REQUEST['start'] = 0;

	// The action needs to be a string and not an array or anything else
	if (isset($_REQUEST['action']))
		$_REQUEST['action'] = (string) $_REQUEST['action'];
	if (isset($_GET['action']))
		$_GET['action'] = (string) $_GET['action'];

	// Make sure we have a valid REMOTE_ADDR.
	if (!isset($_SERVER['REMOTE_ADDR']))
	{
		$_SERVER['REMOTE_ADDR'] = '';
		// A new magic variable to indicate we think this is command line.
		$_SERVER['is_cli'] = true;
	}
	elseif (preg_match('~^((([1]?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $_SERVER['REMOTE_ADDR']) === 0)
		$_SERVER['REMOTE_ADDR'] = 'unknown';

	// If they're coming through CloudFlare, the REMOTE_ADDR will be CloudFlare's, and a different value is sent by CloudFlare, so use that instead.
	if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']))
	{
		$_SERVER['HTTP_CF_IP'] = $_SERVER['REMOTE_ADDR'];
		$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
	}

	// Try to calculate their most likely IP for those people behind proxies (And the like).
	$_SERVER['BAN_CHECK_IP'] = $_SERVER['REMOTE_ADDR'];

	// Find the user's IP address. (but don't let it give you 'unknown'!)
	if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_CLIENT_IP']) && (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['HTTP_CLIENT_IP']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['REMOTE_ADDR']) != 0))
	{
		// We have both forwarded for AND client IP... check the first forwarded for as the block - only switch if it's better that way.
		if (strtok($_SERVER['HTTP_X_FORWARDED_FOR'], '.') != strtok($_SERVER['HTTP_CLIENT_IP'], '.') && '.' . strtok($_SERVER['HTTP_X_FORWARDED_FOR'], '.') == strrchr($_SERVER['HTTP_CLIENT_IP'], '.') && (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['HTTP_X_FORWARDED_FOR']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['REMOTE_ADDR']) != 0))
			$_SERVER['BAN_CHECK_IP'] = implode('.', array_reverse(explode('.', $_SERVER['HTTP_CLIENT_IP'])));
		else
			$_SERVER['BAN_CHECK_IP'] = $_SERVER['HTTP_CLIENT_IP'];
	}
	if (!empty($_SERVER['HTTP_CLIENT_IP']) && (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['HTTP_CLIENT_IP']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['REMOTE_ADDR']) != 0))
	{
		// Since they are in different blocks, it's probably reversed.
		if (strtok($_SERVER['REMOTE_ADDR'], '.') != strtok($_SERVER['HTTP_CLIENT_IP'], '.'))
			$_SERVER['BAN_CHECK_IP'] = implode('.', array_reverse(explode('.', $_SERVER['HTTP_CLIENT_IP'])));
		else
			$_SERVER['BAN_CHECK_IP'] = $_SERVER['HTTP_CLIENT_IP'];
	}
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
	{
		// If there are commas, get the last one.. probably.
		if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',') !== false)
		{
			$ips = array_reverse(explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']));

			// Go through each IP...
			foreach ($ips as $i => $ip)
			{
				// Make sure it's in a valid range...
				if (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $ip) != 0 && preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['REMOTE_ADDR']) == 0)
					continue;

				// Otherwise, we've got an IP!
				$_SERVER['BAN_CHECK_IP'] = trim($ip);
				break;
			}
		}
		// Otherwise just use the only one.
		elseif (preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['HTTP_X_FORWARDED_FOR']) == 0 || preg_match('~^((0|10|172\.(1[6-9]|2[0-9]|3[01])|192\.168|255|127)\.|unknown)~', $_SERVER['REMOTE_ADDR']) != 0)
			$_SERVER['BAN_CHECK_IP'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
	}

	// Make sure we know the URL of the current request.
	if (empty($_SERVER['REQUEST_URI']))
		$_SERVER['REQUEST_URL'] = $scripturl . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
	else
		$_SERVER['REQUEST_URL'] = 'http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

	// And make sure HTTP_USER_AGENT is set.
	$_SERVER['HTTP_USER_AGENT'] = isset($_SERVER['HTTP_USER_AGENT']) ? htmlspecialchars(wedb::unescape_string($_SERVER['HTTP_USER_AGENT']), ENT_QUOTES) : '';

	// Some final checking.
	if (preg_match('~^((([1]?\d)?\d|2[0-4]\d|25[0-5])\.){3}(([1]?\d)?\d|2[0-4]\d|25[0-5])$~', $_SERVER['BAN_CHECK_IP']) === 0)
		$_SERVER['BAN_CHECK_IP'] = '';
	if ($_SERVER['REMOTE_ADDR'] == 'unknown')
		$_SERVER['REMOTE_ADDR'] = '';
}

/**
 * Given an array, this function ensures every key and value is escaped for database purposes.
 *
 * This function traverses an array - including nested arrays as appropriate (calling itself recursively as necessary), and runs every array value through the string escaper as used by the database library (typically mysql_real_escape_string()). Uses two underscores to guard against overloading.
 *
 * @param mixed $var Either an array or string; if a string, simply return the string escaped, otherwise traverse the array and calls itself on each element of the array (and calls the DB escape string on the array keys) - typically the contents will be non-array, so will be escaped, and the routine will bubble back up through the recursive layers.
 * @return mixed The effective return is a string or array whose entire key/value pair set has been escaped; as a recursive function some calls will only be returning strings back to itself.
 */
function escapestring__recursive($var)
{
	global $smcFunc;

	if (!is_array($var))
		return wedb::escape_string($var);

	// Reindex the array with slashes.
	$new_var = array();

	// Add slashes to every element, even the indexes!
	foreach ($var as $k => $v)
		$new_var[wedb::escape_string($k)] = escapestring__recursive($v);

	return $new_var;
}

/**
 * Given an array, this function ensures that every value has all HTML special characters converted to entities.
 *
 * - Uses two underscores to guard against overloading.
 * - Unlike most of the other __recursive sanitation functions, this function only applies to array values, not to keys.
 * - Attempts to use the UTF-8-ified function if available (for dealing with other special entities)
 *
 * @param mixed $var Either an array or string; if a string, simply return the string having entity-converted, otherwise traverse the array and calls itself on each element of the array - typically the contents will be non-array, so will be converted, and the routine will bubble back up through the recursive layers.
 * @return mixed The effective return is a string or array whose value set will have had HTML control characters converted to entities; as a recursive function some calls will only be returning strings back to itself.
 */
function htmlspecialchars__recursive($var, $level = 0)
{
	global $smcFunc;

	if (!is_array($var))
		return isset($smcFunc['htmlspecialchars']) ? $smcFunc['htmlspecialchars']($var, ENT_QUOTES) : htmlspecialchars($var, ENT_QUOTES);

	// Add the htmlspecialchars to every element.
	foreach ($var as $k => $v)
		$var[$k] = $level > 25 ? null : htmlspecialchars__recursive($v, $level + 1);

	return $var;
}

/**
 * Given an array, this function ensures that every key and value has all %xx URL encoding converted to its regular characters.
 *
 * Uses two underscores to guard against overloading.
 *
 * @param mixed $var Either an array or string; if a string, simply return the string without %xx URL encoding, otherwise traverse the array and calls itself on each element of the array (and decodes any %xx URL encoding in the array keys) - typically the contents will be non-array, so will be slash-stripped, and the routine will bubble back up through the recursive layers.
 * @return mixed The effective return is a string or array whose entire key/value pair set have had %xx URL encoding decoded; as a recursive function some calls will only be returning strings back to itself.
 */
function urldecode__recursive($var, $level = 0)
{
	if (!is_array($var))
		return urldecode($var);

	// Reindex the array...
	$new_var = array();

	// Add the htmlspecialchars to every element.
	foreach ($var as $k => $v)
		$new_var[urldecode($k)] = $level > 25 ? null : urldecode__recursive($v, $level + 1);

	return $new_var;
}

/**
 * Given an array, this function ensures every value is unescaped for database purposes - counterpart to {@link escapestring__recursive()}
 *
 * This function traverses an array - including nested arrays as appropriate (calling itself recursively as necessary), and runs every array value through the string un-escaper as used by the database library (custom function to strip the escaping). Uses two underscores to guard against overloading.
 *
 * @param mixed $var Either an array or string; if a string, simply return the string unescaped, otherwise traverse the array and calls itself on each element of the array (and calls the DB unescape string on the array keys) - typically the contents will be non-array, so will be unescaped, and the routine will bubble back up through the recursive layers.
 * @return mixed The effective return is a string or array whose entire key/value pair set has been unescaped; as a recursive function some calls will only be returning strings back to itself.
 */
function unescapestring__recursive($var)
{
	global $smcFunc;

	if (!is_array($var))
		return wedb::unescape_string($var);

	// Reindex the array without slashes, this time.
	$new_var = array();

	// Strip the slashes from every element.
	foreach ($var as $k => $v)
		$new_var[wedb::unescape_string($k)] = unescapestring__recursive($v);

	return $new_var;
}

/**
 * Given an array, this function ensures that every key and value has all backslashes removed.
 *
 * Uses two underscores to guard against overloading.
 *
 * @param mixed $var Either an array or string; if a string, simply return the string without slashes, otherwise traverse the array and calls itself on each element of the array (and removes the slashes on the array keys) - typically the contents will be non-array, so will be slash-stripped, and the routine will bubble back up through the recursive layers.
 * @return mixed The effective return is a string or array whose entire key/value pair set have had slashes removed; as a recursive function some calls will only be returning strings back to itself.
 */
function stripslashes__recursive($var, $level = 0)
{
	if (!is_array($var))
		return stripslashes($var);

	// Reindex the array without slashes, this time.
	$new_var = array();

	// Strip the slashes from every element.
	foreach ($var as $k => $v)
		$new_var[stripslashes($k)] = $level > 25 ? null : stripslashes__recursive($v, $level + 1);

	return $new_var;
}

/**
 * Given an array, this function ensures that every value will have had whitespace removed from either end.
 *
 * - Uses two underscores to guard against overloading.
 * - Unlike most of the other __recursive sanitation functions, this function only applies to array values, not to keys.
 * - Attempts to use the UTF-8-ified function if available (for dealing with other special entities)
 * - Remove spaces (32), tabs (9), returns (13, 10, and 11), nulls (0), and hard spaces. (160)
 *
 * @param mixed $var Either an array or string; if a string, simply return the string having entity-converted, otherwise traverse the array and calls itself on each element of the array - typically the contents will be non-array, so will be converted, and the routine will bubble back up through the recursive layers.
 * @return mixed The effective return is a string or array whose value set will have had HTML control characters converted to entities; as a recursive function some calls will only be returning strings back to itself.
 */
function htmltrim__recursive($var, $level = 0)
{
	global $smcFunc;

	if (!is_array($var))
		return isset($smcFunc) ? $smcFunc['htmltrim']($var) : trim($var, ' ' . "\t\n\r\x0B" . '\0' . "\xA0");

	// Go through all the elements and remove the whitespace.
	foreach ($var as $k => $v)
		$var[$k] = $level > 25 ? null : htmltrim__recursive($v, $level + 1);

	return $var;
}

/**
 * Collects the headers for this page request.
 *
 * - Uses apache_request_headers() if running on Apache as a CGI module (recommended).
 * - If this is not available, $_SERVER will be examined for HTTP_ variables which should translate to headers. (This process works on PHP-CLI, IIS and lighttpd at least)
 *
 * @return array A key/value pair of the HTTP headers for this request.
 */
function get_http_headers()
{
	if (is_callable('apache_request_headers'))
		return apache_request_headers();

	$headers = array();
	foreach ($_SERVER as $key => $value)
		if (strpos($key, 'HTTP_') === 0)
			$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))))] = $value;
	return $headers;
}

/**
 * Prunes non valid XML/XHTML characters from a string intended for XML/XHTML transport use.
 *
 * Primarily this function removes non-printable control codes from an XML output (tab, CR, LF are preserved), including non valid UTF-8 character signatures if appropriate.
 *
 * @param string $string A string of potential output.
 * @return string The sanitized string.
 */
function cleanXml($string)
{
	global $context;

	// http://www.w3.org/TR/2000/REC-xml-20001006#NT-Char
	return preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x19\x{D800}-\x{DFFF}\x{FFFE}\x{FFFF}]~u', '', $string);
}

/**
 * Sanitize strings that might be passed through to Javascript.
 *
 * Multiple instances of scripts will need to be adjusted through the codebase if passed to Javascript through the template. This function will handle quoting of the string's contents, including providing the encapsulating quotes (so no need to echo '"', JavaScriptEscape($var), '"'; but simply echo JavaScriptEscape($var); instead)
 *
 * Other protections include dealing with newlines, carriage returns (through suppression), single quotes, links, inline script tags, and $scripturl.
 *
 * @param string $string A string whose contents to be quoted.
 * @return string A transformed string with contents suitably single quoted for use in Javascript.
 */
function JavaScriptEscape($string)
{
	global $scripturl;

	return '\'' . strtr($string, array(
		"\r" => '',
		"\n" => '\\n',
		"\t" => '\\t',
		'\\' => '\\\\',
		'\'' => '\\\'',
		'</' => '<\' + \'/',
		'script' => 'scri\'+\'pt',
		'<a href' => '<a hr\'+\'ef',
		$scripturl => $scripturl . '\'+\'',
	)) . '\'';
}

// Add a string to the footer Javascript. Several strings can be passed as parameters, allowing for easier conversion.
// !!! Document this properly. I've named it the simplest possible way but it'll probably need to be renamed later.
// Can be done with a site-wide search & replace, as "add_js" is only used in "wedge_add_js" (which, if add_js
// is not renamed, will need to be renamed itself... You get the point. Hopefully.)
function add_js()
{
	global $context, $footer_coding;

	if (empty($footer_coding))
	{
		$context['footer_js'] .= '
<script><!-- // --><[!CDATA[';
		$footer_coding = true;
	}
	$args = func_get_args();
	$context['footer_js'] .= implode('', $args);
}

// Same as earlier, but can be shown BEFORE all scripts are loaded,
// because the code doesn't rely on script.js or other externals.
function add_js_inline()
{
	global $context;

	$args = func_get_args();
	$context['footer_js_inline'] .= implode('', $args);
}

// Add a file to the footer Javascript. This takes care of adding the CDATA separators around it.
// !!! Document this properly, and maybe add support for adding ?nocache automatically, and check for the .js extension.
// !!! For later: we may use CACHE_SUFFIX on file names, i.e. adding ?alpha to the end of every URL. Clean up first!
function add_js_file()
{
	global $context, $footer_coding;

	$files = func_get_args();
	$code = '
<script src="' . implode('"></script>
<script src="', $files) . '"></script>';

	if (!empty($footer_coding))
	{
		$context['footer_js'] .= '
// ]]></script>';
		$footer_coding = false;
	}
	$context['footer_js'] .= $code;
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
	global $scripturl, $modSettings, $user_info, $context, $db_prefix;
	global $txt, $time_start, $db_count, $db_show_debug, $smcFunc, $cached_urls, $use_cache;
	static $second_time_debugging = false;

	// If $scripturl is set to nothing, or the SID is not defined (SSI?) just quit.
	if ($scripturl == '' || !defined('SID'))
		return $buffer;

	// Do nothing if the session is cookied, or they are a crawler - guests are caught by redirectexit().
	if (empty($_COOKIE) && SID != '' && empty($context['browser']['possibly_robot']))
		$buffer = preg_replace('/"' . preg_quote($scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', '"' . $scripturl . '?' . SID . '&amp;', $buffer);
	// Debugging templates, are we?
	elseif (isset($_GET['debug']))
		$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote($scripturl, '/') . '\\??/', '"' . $scripturl . '?debug;', $buffer);

	// Rewrite the buffer with Pretty URLs!
	if (!empty($modSettings['pretty_enable_filters']) && !empty($modSettings['pretty_filters']))
	{
		if (!empty($db_show_debug) && !$second_time_debugging && !WIRELESS && substr($buffer, 0, 5) != '<?xml')
		{
			// We're debugging, so ob_sessrewrite will be called again. Don't replace URLs this time.
			$second_time_debugging = true;
			return $buffer;
		}

//		$insideurl = str_replace(array('.','/',':','?'), array('\.','\/','\:','\?'), $scripturl);
		$insideurl = preg_quote($scripturl, '~');
		$use_cache = !empty($modSettings['pretty_enable_cache']);

		// Remove the script tags now
		$context['pretty']['scriptID'] = 0;
		$context['pretty']['scripts'] = array();
		$buffer = preg_replace_callback('~<script.+?</script>~s', 'pretty_scripts_remove', $buffer);

		// Find all URLs in the buffer
		$context['pretty']['search_patterns'][] = '~(<a[^>]+href=|<link[^>]+href=|<img[^>]+?src=|<form[^>]+?action=)[\"\']'.$insideurl.'([^\"\'#]*?[?;&](board|topic|action)=[^\"\'#]+)~';
		$urls_query = array();
		$uncached_urls = array();
		foreach ($context['pretty']['search_patterns'] as $pattern)
		{
			preg_match_all($pattern, $buffer, $matches, PREG_PATTERN_ORDER);
			foreach ($matches[2] as $match)
			{
				// Rip out everything that shouldn't be cached
				if ($use_cache)
					$match = preg_replace(array('~^[\"\']|PHPSESSID=[^&;]+|s(es)?c=[^;]+~', '~\"~', '~;+|=;~', '~\?;|\?&amp;~', '~\?$|;$|=$~'), array('', '%22', ';', '?', ''), $match);
				else
					$match = preg_replace(array('~^[\"\']~', '~\"~', '~;+|=;~', '~\?;~', '~\?$|;$|=$~'), array('', '%22', ';', '?', ''), $match);
				$url_id = $match;
				$urls_query[] = $url_id;
				$uncached_urls[$match] = array(
					'url' => $match,
					'url_id' => $url_id
				);
			}
		}

		// Proceed only if there are actually URLs in the page
		if (count($urls_query) != 0)
		{
			$urls_query = array_keys(array_flip($urls_query));
			// Retrieve cached URLs
			$cached_urls = array();

			if ($use_cache)
			{
				$query = wedb::query('
					SELECT url_id, replacement
					FROM {db_prefix}pretty_urls_cache
					WHERE url_id IN ({array_string:urls})
						AND log_time > ' . (int) (time() - 86400),
					array(
						'urls' => $urls_query
					)
				);
				while ($row = wedb::fetch_assoc($query))
				{
					$cached_urls[$row['url_id']] = $row['replacement'];
					unset($uncached_urls[$row['url_id']]);
				}
				wedb::free_result($query);
			}

			// If there are any uncached URLs, process them
			if (count($uncached_urls) != 0)
			{
				// Run each filter callback function on each URL
				if (!function_exists('pretty_urls_topic_filter'))
					loadSource('PrettyUrls-Filters');
				$filter_callbacks = unserialize($modSettings['pretty_filter_callbacks']);
				foreach ($filter_callbacks as $callback)
				{
					$uncached_urls = call_user_func($callback, $uncached_urls);
					if ($db_show_debug && isset($_REQUEST['watch']))
						$buffer .= '<pre>' . obsafe_print_r($uncached_urls, true, true) . '</pre><br /><br />';
				}

				// Fill the cached URLs array
				$cache_data = array();
				foreach ($uncached_urls as $url_id => $url)
				{
					if (!isset($url['replacement']))
						$url['replacement'] = $url['url'];
					$url['replacement'] = str_replace(chr(18), "'", $url['replacement']);
					$url['replacement'] = preg_replace(array('~\"~', '~;+|=;~', '~\?;~', '~\?$|;$|=$~'), array('%22', ';', '?', ''), $url['replacement']);
					$cached_urls[$url_id] = $url['replacement'];
					if ($use_cache)
						if (strlen($url_id) < 256)
							$cache_data[] = '(\'' . $url_id . '\', \'' . addslashes($url['replacement']) . '\')';
				}

				// Cache these URLs in the database (use mysql_query to avoid some issues.)
				if (count($cache_data) > 0)
					mysql_query("REPLACE INTO {$db_prefix}pretty_urls_cache (url_id, replacement) VALUES " . implode(', ', $cache_data));
			}

			// Put the URLs back into the buffer
			$context['pretty']['replace_patterns'][] = '~(<a[^>]+href=|<link[^>]+href=|<img[^>]+?src=|<form[^>]+?action=)[\"\']'.$insideurl.'([^\"\'#]*?[?;&](board|topic|action)=([^\"]+\"|[^\']+\'))~';
			foreach ($context['pretty']['replace_patterns'] as $pattern)
				$buffer = preg_replace_callback($pattern, 'pretty_buffer_callback', $buffer);
		}

		// Restore the script tags
		if ($context['pretty']['scriptID'] > 0)
			$buffer = preg_replace_callback('~' . chr(20) . '([0-9]+)' . chr(20) . '~', 'pretty_scripts_restore', $buffer);
	}

	// Update the load times
	$pattern = '~<span class="smalltext">' . $txt['page_created'] . '([.0-9]+)' . $txt['seconds_with'] . '([0-9]+)' . $txt['queries'] . '</span>~';
	if ($user_info['is_admin'] && preg_match($pattern, $buffer, $matches))
	{
		$newTime = round(array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)), 3);
		$timeDiff = round($newTime - (float) $matches[1], 3);
		$queriesDiff = $db_count + $context['pretty']['db_count'] - (int) $matches[2];

		// !!! Hardcoded stuff. Bad! Should we remove this entirely..?
		$newLoadTime = '<span class="smalltext">' . $txt['page_created'] . $newTime . $txt['seconds_with'] . $db_count . $txt['queries'] . ' (Pretty URLs add ' . $timeDiff . 's, ' . $queriesDiff . 'q)</span>';
		$buffer = str_replace($matches[0], $newLoadTime, $buffer);
 	}

	// Moving all inline events (<code onclick="event();">) to the footer, to make
	// sure they're not triggered before jQuery and stuff are loaded. Trick and treats!
	$context['delayed_events'] = $context['delayed_dupes'] = array();
	$buffer = preg_replace_callback('~<[^>]+?\son\w+="[^">]*"[^>]*>~', 'wedge_event_delayer', $buffer);

	if (!empty($context['delayed_events']))
	{
		$thing = 'var eves = {';
		foreach ($context['delayed_events'] as $eve)
			$thing .= '
		' . $eve[0] . ': ["' . $eve[1] . '", "' . $eve[2] . '"],';
		$thing = substr($thing, 0, -1) . '
	};
	$("*[data-eve]").each(function() {
		var elis = $(this).data("eve");
		for (var eve in elis)
			$(this).bind(eves[elis[eve]][0], new Function(eves[elis[eve]][1]));
	});';
		$buffer = substr_replace($buffer, $thing, strpos($buffer, '<!-- insert inline events here -->'), 34);
	}

	// Return the changed buffer.
	return $buffer;
}

// Move inline events to the end
function wedge_event_delayer($match)
{
	global $context;
	static $eve = 1;

	$eve_list = array();
	preg_match_all('~\son(\w+)="([^"]+)"~', $match[0], $insides, PREG_SET_ORDER);
	foreach ($insides as $inside)
	{
		$match[0] = str_replace($inside[0], '', $match[0]);
		$dupe = serialize($inside);
		if (!isset($context['delayed_dupes'][$dupe]))
		{
			$context['delayed_events'][$eve] = array($eve, $inside[1], $inside[2]);
			$context['delayed_dupes'][$dupe] = $eve;
			$eve_list[] = $eve++;
		}
		else
			$eve_list[] = $context['delayed_dupes'][$dupe];
	}
	return substr($match[0], 0, -1) . ' data-eve="[' . implode(',', $eve_list) . ']">';
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
	global $cached_urls, $scripturl, $use_cache;

	// Is this URL part of a feed?
	$isFeed = strpos($matches[1], '>') === false ? '"' : '';

	// Remove those annoying quotes
	$matches[2] = preg_replace('~^[\"\']|[\"\']$~', '', $matches[2]);

	// Store the parts of the URL that won't be cached so they can be inserted later
	if ($use_cache)
	{
		preg_match('~PHPSESSID=[^;#&]+~', $matches[2], $PHPSESSID);
		preg_match('~s(es)?c=[^;#]+~', $matches[2], $sesc);
		preg_match('~#.*~', $matches[2], $fragment);
		$url_id = preg_replace(array('~PHPSESSID=[^;#]+|s(es)?c=[^;#]+|#.*$~', '~\"~', '~;+|=;~', '~\?;~', '~\?$|;$|=$~'), array('', '%22', ';', '?', ''), $matches[2]);
	}
	else
	{
		preg_match('~#.*~', $matches[2], $fragment);
		// Rip out everything that won't have been cached
		$url_id = preg_replace(array('~#.*$~', '~\"~', '~;+|=;~', '~\?;~', '~\?$|;$|=$~'), array('', '%22', ';', '?', ''), $matches[2]);
	}

	// Stitch everything back together, clean it up and return
	$replacement = isset($cached_urls[$url_id]) ? $cached_urls[$url_id] : $url_id;
	$replacement .= (strpos($replacement, '?') === false ? '?' : ';') . (isset($PHPSESSID[0]) ? $PHPSESSID[0] : '') . ';' . (isset($sesc[0]) ? $sesc[0] : '') . (isset($fragment[0]) ? $fragment[0] : '');
	$replacement = preg_replace(array('~;+|=;~', '~\?;~', '~\?#|(&amp)?;#|=#~', '~\?$|(&amp)?;$|#$|=$~'), array(';', '?', '#', ''), $replacement);

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

?>