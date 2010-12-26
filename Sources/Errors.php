<?php
/**********************************************************************************
* Errors.php                                                                      *
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
 * This file provides all of the error handling within the system.
 *
 * @package wedge
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Log an error in the error log (in the database), assuming error logging is on.
 *
 * Logging is disabled if $modSettings['enableErrorLogging'] is unset or 0.
 *
 * @param string $error_message The final error message (not, for example, a key in $txt) to be logged, prior to any entity encoding.
 * @param mixed $error_type A string denoting the type of error being logged for the purposes of filtering: 'general', 'critical', 'database', 'undefined_vars', 'user', 'template', 'debug'. Alternatively can be specified as boolean false to override the error message being logged.
 * @param mixed $file Specify the file path that the error occurred in. If not supplied, no attempt will be made to back-check (it is normally only supplied from the error-handler; workflow instanced errors do not generally record filename.
 * @param mixed $line The line number an error occurred on. Like $file, this is only generally supplied by a PHP error; errors such as permissions or other application type errors do not have this logged.
 */
function log_error($error_message, $error_type = 'general', $file = null, $line = null)
{
	global $txt, $modSettings, $sc, $user_info, $scripturl, $last_error, $context, $full_request;

	// Check if error logging is actually on.
	if (empty($modSettings['enableErrorLogging']))
		return $error_message;

	// Basically, htmlspecialchars it minus &. (for entities!)
	$error_message = strtr($error_message, array('<' => '&lt;', '>' => '&gt;', '"' => '&quot;'));
	$error_message = strtr($error_message, array('&lt;br /&gt;' => '<br />', '&lt;b&gt;' => '<strong>', '&lt;/b&gt;' => '</strong>', "\n" => '<br />'));

	// Add a file and line to the error message?
	// Don't use the actual txt entries for file and line but instead use %1$s for file and %2$s for line
	if ($file == null)
		$file = '';
	else
		// Windows-style slashes don't play well, let's convert them to Unix style.
		$file = str_replace('\\', '/', $file);

	if ($line == null)
		$line = 0;
	else
		$line = (int) $line;

	// Just in case there's no id_member or IP set yet.
	if (empty($user_info['id']))
		$user_info['id'] = 0;
	if (empty($user_info['ip']))
		$user_info['ip'] = '';

	// Find the best query string we can...
	if (!empty($full_request))
	{
		$query_string = substr($scripturl, 0, strpos($scripturl, '://') + 3) . $full_request;
		// Are we using pretty URLs here?
		$is_pretty_url = strpos($query_string, $scripturl) === false;
	}
	else
		$query_string = empty($_SERVER['QUERY_STRING']) ? (empty($_SERVER['REQUEST_URL']) ? '' : str_replace($scripturl, '', $_SERVER['REQUEST_URL'])) : $_SERVER['QUERY_STRING'];

	// Don't log session data in the url twice, it's a waste.
	$query_string = preg_replace(array('~;sesc=[^&;]+~', '~' . session_name() . '=' . session_id() . '[&;]~'), array(';sesc', ''), $query_string);
	$query_string = htmlspecialchars((SMF == 'SSI' || $is_pretty_url ? '' : '?') . $query_string);

	// Just so we know what board error messages are from. If it's a pretty URL, we already know that.
	if (!$is_pretty_url && isset($_POST['board']) && !isset($_GET['board']))
		$query_string .= ($query_string == '' ? 'board=' : ';board=') . $_POST['board'];

	// What types of categories do we have?
	$known_error_types = array(
		'general',
		'critical',
		'database',
		'undefined_vars',
		'user',
		'template',
		'debug',
	);

	// Make sure the category that was specified is a valid one
	$error_type = in_array($error_type, $known_error_types) && $error_type !== true ? $error_type : 'general';

	// Don't log the same error countless times, as we can get in a cycle of depression...
	$error_info = array($user_info['id'], time(), $user_info['ip'], $query_string, $error_message, (string) $sc, $error_type, $file, $line);
	if (empty($last_error) || $last_error != $error_info)
	{
		// Insert the error into the database.
		wesql::insert('',
			'{db_prefix}log_errors',
			array('id_member' => 'int', 'log_time' => 'int', 'ip' => 'string-16', 'url' => 'string-65534', 'message' => 'string-65534', 'session' => 'string', 'error_type' => 'string', 'file' => 'string-255', 'line' => 'int'),
			$error_info,
			array('id_error')
		);
		$last_error = $error_info;

		if (!isset($context['app_error_count']))
			$context['app_error_count'] = 0;
		$context['app_error_count']++;
	}

	// Return the message to make things simpler.
	return $error_message;
}

/**
 * Output a fatal error message, without localization.
 *
 * There are times where the error call will be without language strings, or otherwise the error is non-localized (e.g. specific fatal error calls for debugging or other critical failures)
 *
 * This function will make a call to log the error, prior to handing control to the more generic {@link setup_fatal_error_context()}.
 *
 * @param string $error The error message to output.
 * @param mixed $log The error category. See {@link log_error()} for more details (same specification)
 */
function fatal_error($error, $log = 'general')
{
	global $txt, $context, $modSettings;

	// We don't have $txt yet, but that's okay...
	if (empty($txt))
		die($error);

	updateOnlineWithError($error, false);
	setup_fatal_error_context($log || (!empty($modSettings['enableErrorLogging']) && $modSettings['enableErrorLogging'] == 2) ? log_error($error, $log) : $error);
}

/**
 * Output a fatal error message, with localization.
 *
 * Any fatal error from the application (which includes modifications) should generally call this function.
 *
 * Several operations occur:
 * - If the theme is not loaded (and it is not a fatal error, either here or recursively upwards), attempt to load it.
 * - If the theme is still not loaded, exit and output what we do have, non localized. (Since without the theme we do not have language strings)
 * - Load the language of the forum itself (rather than the user who triggered the error), then pass the error to {@link log_error()} if logging is turned on.
 * - Reload the correct language if we have changed it in the previous step.
 * - Call to ensure the error is appropriately logged with the who's online information.
 * - Pass control over to {@link setup_fatal_error_context()} to manage the actual outputting of error.
 *
 * @param string $error The error message to output, specified as a key in $txt.
 * @param mixed $log The error category. See {@link log_error()} for more details (same specification)
 * @param array $sprintf An array of items to be format-printed into the string once located within $txt. For example, the message might use %1$s to indicate a relevant string; this value would be inserted into the array to be injected into the error message prior to saving to log.
 */
function fatal_lang_error($error, $log = 'general', $sprintf = array())
{
	global $txt, $language, $modSettings, $user_info, $context;
	static $fatal_error_called = false;

	// Try to load a theme if we don't have one.
	if (empty($context['theme_loaded']) && empty($fatal_error_called))
	{
		$fatal_error_called = true;
		loadTheme();
	}

	// If we have no theme stuff we can't have the language file...
	if (empty($context['theme_loaded']))
		die($error);

	$reload_lang_file = true;
	// Log the error in the forum's language, but don't waste the time if we aren't logging
	if ($log || (!empty($modSettings['enableErrorLogging']) && $modSettings['enableErrorLogging'] == 2))
	{
		loadLanguage('Errors', $language);
		$reload_lang_file = $language != $user_info['language'];
		$error_message = empty($sprintf) ? $txt[$error] : vsprintf($txt[$error], $sprintf);
		log_error($error_message, $log);
	}

	// Load the language file, only if it needs to be reloaded
	if ($reload_lang_file)
	{
		loadLanguage('Errors');
		$error_message = empty($sprintf) ? $txt[$error] : vsprintf($txt[$error], $sprintf);
	}

	updateOnlineWithError($error, true, $sprintf);
	setup_fatal_error_context($error_message);
}

/**
 * Handler for regular PHP errors.
 *
 * Elsewhere in workflow, this function is designated the error handler for the remainder of the page; this enables normal PHP errors (such as undefined variables) to be logged into the database rather than anything else.
 *
 * @param int $error_level The level of the current error as a constant, as per http://www.php.net/manual/en/errorfunc.constants.php
 * @param string $error_string The raw error string, from PHP, which should be localized by the server's configuration.
 * @param string $file The filename where the error occurred, which may be incorrect in the event of template eval.
 * @param line $line The line number the error occurred on.
 * @return mixed The function will be a void in the event of a non fatal error, or will terminate execution in the event of a fatal error.
 */
function error_handler($error_level, $error_string, $file, $line)
{
	global $settings, $modSettings, $db_show_debug;

	// Ignore errors if we're ignoring them or they are strict notices from PHP 5 (which cannot be solved without breaking PHP 4.)
	if (error_reporting() == 0 || (defined('E_STRICT') && $error_level == E_STRICT && (empty($modSettings['enableErrorLogging']) || $modSettings['enableErrorLogging'] != 2)))
		return;

	if (strpos($file, 'eval()') !== false && !empty($settings['current_include_filename']))
	{
		$array = debug_backtrace();
		for ($i = 0; $i < count($array); $i++)
		{
			if ($array[$i]['function'] != 'loadSubTemplate')
				continue;

			// This is a bug in PHP, with eval, it seems!
			if (empty($array[$i]['args']))
				$i++;
			break;
		}

		if (isset($array[$i]) && !empty($array[$i]['args']))
			$file = realpath($settings['current_include_filename']) . ' (' . $array[$i]['args'][0] . ' sub template - eval?)';
		else
			$file = realpath($settings['current_include_filename']) . ' (eval?)';
	}

	if (isset($db_show_debug) && $db_show_debug === true)
	{
		// Commonly, undefined indexes will occur inside attributes; try to show them anyway!
		if ($error_level % 255 != E_ERROR)
		{
			$temporary = ob_get_contents();
			if (substr($temporary, -2) == '="')
				echo '"';
			// If we're inside a tag, might as well try closing it first...
			if (strrpos($temporary, '>') < strrpos($temporary, '<'))
				echo '>';
		}

		// Debugging!  This should look like a PHP error message.
		echo '<br />
<strong>', $error_level % 255 == E_ERROR ? 'Error' : ($error_level % 255 == E_WARNING ? 'Warning' : 'Notice'), '</strong>: ', $error_string, ' in <strong>', $file, '</strong> on line <strong>', $line, '</strong><br />';
	}

	$error_type = strpos(strtolower($error_string), 'undefined') !== false ? 'undefined_vars' : 'general';

	$message = log_error($error_level . ': ' . $error_string, $error_type, $file, $line);

	// Let's give integrations a chance to output a bit differently
	call_hook('output_error', array(&$message, $error_type, $error_level, $file, $line));

	// Dying on these errors only causes MORE problems (blank pages!)
	if ($file == 'Unknown')
		return;

	// If this is an E_ERROR or E_USER_ERROR.... die.  Violently so.
	if ($error_level % 255 == E_ERROR)
		obExit(false);
	else
		return;

	// If this is an E_ERROR, E_USER_ERROR, E_WARNING, or E_USER_WARNING.... die.  Violently so.
	if ($error_level % 255 == E_ERROR || $error_level % 255 == E_WARNING)
		fatal_error(allowedTo('admin_forum') ? $message : $error_string, false);

	// We should NEVER get to this point.  Any fatal error MUST quit, or very bad things can happen.
	if ($error_level % 255 == E_ERROR)
		die('Hacking attempt...');
}

/**
 * Prepare a fatal error for being displayed.
 *
 * - Attempt to prevent recursively trying to error
 * - Check if the theme is loaded (e.g. from action=dlattach, where the theme is not normally loaded)
 * - Set up general page details - no robots meta tag, the page title (based on the error message if possible, in $context['error_title'] if set)
 * - Determine the appropriate template (either the normal fatal_error template, or the wireless template)
 * - Check whether we are using SSI and if so whether SSI-specific fatal error handling is indicated.
 * - Finally, pass control to {@link obExit()} to end execution.
 *
 * IMPORTANT: If you are creating a bridge to SMF or modifying this function, you MUST make ABSOLUTELY SURE that this function quits and DOES NOT RETURN TO NORMAL PROGRAM FLOW.  Otherwise, security error messages will not be shown, and your forum will be in a very easily hackable state.
 *
 * @param string $error_message The error message to be displayed.
 */
function setup_fatal_error_context($error_message)
{
	global $context, $txt, $ssi_on_error_method;
	static $level = 0;

	// Attempt to prevent a recursive loop.
	if (++$level > 1)
		return false;

	// Maybe they came from dlattach or similar?
	if (SMF != 'SSI' && empty($context['theme_loaded']))
		loadTheme();

	// Don't bother indexing errors mate...
	$context['robot_no_index'] = true;

	if (!isset($context['error_title']))
		$context['error_title'] = $txt['error_occured'];
	$context['error_message'] = isset($context['error_message']) ? $context['error_message'] : $error_message;

	if (empty($context['page_title']))
		$context['page_title'] = $context['error_title'];

	// Display the error message - wireless?
	if (defined('WIRELESS') && WIRELESS)
		$context['sub_template'] = WIRELESS_PROTOCOL . '_error';
	// Load the template and set the sub template.
	else
	{
		loadTemplate('Errors');
		$context['sub_template'] = 'fatal_error';
	}

	// If this is SSI, what do they want us to do?
	if (SMF == 'SSI')
	{
		if (!empty($ssi_on_error_method) && $ssi_on_error_method !== true && is_callable($ssi_on_error_method))
			$ssi_on_error_method();
		elseif (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
			loadSubTemplate('fatal_error');

		// No layers?
		if (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
			exit;
	}

	// We want whatever for the header, and a footer. (footer includes sub template!)
	obExit(null, true, false, true);

	trigger_error('Hacking attempt...', E_USER_ERROR);
}

/**
 * Shows an error message for the connection problems, and stops further execution of the script.
 *
 * Used only if there's no way to connect to the database or the load averages are too high to do so.
 *
 * @param bool $loadavg If set to true, this is a load average problem, not a database error.
 */
function show_db_error($loadavg = false)
{
	global $mbname, $maintenance, $mtitle, $mmessage, $modSettings;
	global $db_connection, $webmaster_email, $db_last_error, $db_error_send;

	// Don't cache this page!
	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache');

	// Send the right error codes.
	header('HTTP/1.1 503 Service Temporarily Unavailable');
	header('Status: 503 Service Temporarily Unavailable');
	header('Retry-After: 3600');

	if ($loadavg == false)
	{
		// For our purposes, we're gonna want this on if at all possible.
		$modSettings['cache_enable'] = '1';
		if (($temp = cache_get_data('db_last_error', 600)) !== null)
			$db_last_error = max($db_last_error, $temp);

		if ($db_last_error < time() - 3600 * 24 * 3 && empty($maintenance) && !empty($db_error_send))
		{
			loadSource('Subs-Admin');

			// Avoid writing to the Settings.php file if at all possible; use shared memory instead.
			cache_put_data('db_last_error', time(), 600);
			if (($temp = cache_get_data('db_last_error', 600)) == null)
				updateLastDatabaseError();

			// Language files aren't loaded yet :(.
			$db_error = @wesql::error($db_connection);
			@mail($webmaster_email, $mbname . ': SMF Database Error!', 'There has been a problem with the database!' . ($db_error == '' ? '' : "\nMySQL reported:\n" . $db_error) . "\n\n" . 'This is a notice email to let you know that SMF could not connect to the database, contact your host if this continues.');
		}
	}

	if (!empty($maintenance))
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta name="robots" content="noindex" />
		<title>', $mtitle, '</title>
	</head>
	<body>
		<h3>', $mtitle, '</h3>
		', $mmessage, '
	</body>
</html>';
	// If this is a load average problem, display an appropriate message (but we still don't have language files!)
	elseif ($loadavg)
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta name="robots" content="noindex" />
		<title>Temporarily Unavailable</title>
	</head>
	<body>
		<h3>Temporarily Unavailable</h3>
		Due to high stress on the server the forum is temporarily unavailable.  Please try again later.
	</body>
</html>';
	// What to do?  Language files haven't and can't be loaded yet...
	else
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta name="robots" content="noindex" />
		<title>Connection Problems</title>
	</head>
	<body>
		<h3>Connection Problems</h3>
		Sorry, SMF was unable to connect to the database.  This may be caused by the server being busy.  Please try again later.
	</body>
</html>';

	die;
}

/**
 * Update the user online log if there has been an error.
 *
 * The function will abort early if Who's Online is not enabled, since this operation becomes redundant.
 *
 * @param string $error Either the language string array key, or actual language string relating to the error.
 * @param bool $is_lang If the value passed through $error is the language string key (from fatal_lang_error), this should be true.
 * @param array $sprintf Any additional parameters needed that may be injected into the language string.
 */
function updateOnlineWithError($error, $is_lang, $sprintf = array())
{
	global $user_info, $modSettings;

	// Don't bother if Who's Online is disabled.
	if (empty($modSettings['who_enabled']))
		return;

	$session_id = $user_info['is_guest'] ? 'ip' . $user_info['ip'] : session_id();

	// First, we have to get the online log, because we need to break apart the serialized string.
	$query = wesql::query('
		SELECT url
		FROM {db_prefix}log_online
		WHERE session = {string:session}',
		array(
			'session' => $session_id,
		)
	);
	if (wesql::num_rows($query) != 0)
	{
		list($url) = wesql::fetch_row($query);
		$url = unserialize($url);

		if ($is_lang)
			$url += array(
				'who_error_lang' => $error,
				'who_error_params' => $sprintf,
			);
		else
			$url += array(
				'who_error_raw' => $error,
			);

		$url = serialize($url);
		wesql::query('
			UPDATE {db_prefix}log_online
			SET url = {string:url}
			WHERE session = {string:session}',
			array(
				'url' => $url,
				'session' => $session_id,
			)
		);
	}
	wesql::free_result($query);
}

?>