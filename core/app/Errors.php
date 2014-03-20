<?php
/**
 * This file provides all of the error handling within the system.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Log an error in the error log (in the database), assuming error logging is on.
 *
 * Logging is disabled if $settings['enableErrorLogging'] is unset or 0.
 * To always force logging of fatal errors, you can manually set its value to 2.
 *
 * @param string $error_message The final error message (not, for example, a key in $txt) to be logged, prior to any entity encoding.
 * @param mixed $error_type A string denoting the type of error being logged for the purposes of filtering: 'general', 'critical', 'database', 'undefined_vars', 'mail', 'user', 'template', 'debug'. Alternatively can be specified as boolean false to override the error message being logged.
 * @param mixed $file Specify the file path that the error occurred in. If not supplied, no attempt will be made to back-check (it is normally only supplied from the error-handler; workflow instanced errors do not generally record filename.
 * @param mixed $line The line number an error occurred on. Like $file, this is only generally supplied by a PHP error; errors such as permissions or other application type errors do not have this logged.
 */
function log_error($error_message, $error_type = 'general', $file = null, $line = null, $referrer = null)
{
	global $settings, $last_error, $context;
	static $plugin_dir = null;

	// Check if error logging is actually on.
	if (empty($settings['enableErrorLogging']))
		return $error_message;

	// Windows does funny things. Fix the pathing to make sense on Windows.
	if ($plugin_dir === null)
		$plugin_dir = DIRECTORY_SEPARATOR === '/' ? ROOT_DIR . '/plugins' : str_replace(DIRECTORY_SEPARATOR, '/', ROOT_DIR . '/plugins');

	// Basically, htmlspecialchars it minus & (for entities!); also save simple links.
	$error_message = strtr($error_message, array('<' => '&lt;', '>' => '&gt;', '"' => '&quot;'));
	$error_message = strtr($error_message, array('&lt;br&gt;' => '<br>', '&lt;b&gt;' => '<strong>', '&lt;/b&gt;' => '</strong>', "\n" => '<br>'));
	$error_message = preg_replace('~&lt;a href=&quot;(.*?)&quot;&gt;(.*?)&lt;/a&gt;~', '~<a href="$1">$2</a>~', $error_message);

	// Add a file and line to the error message?
	// Don't use the actual txt entries for file and line but instead use %1$s for file and %2$s for line
	if ($file == null)
	{
		// We weren't given a filename but we need it at least for identifying if this is a plugin or not. We need to find if we came here via the fatal error handlers first.
		$array = debug_backtrace(version_compare(PHP_VERSION, '5.3.6') < 0 ? false : DEBUG_BACKTRACE_IGNORE_ARGS);
		for ($i = 0, $c = count($array); $i < $c; $i++)
			if (!empty($array[$i]['function']) && in_array($array[$i]['function'], array('fatal_error', 'fatal_lang_error')))
			{
				$found_filename = $array[$i]['file'];
				break;
			}

		// Hmm, did we find it? Maybe they called log_error directly?
		if (!isset($found_filename))
			for ($i = 0; $i < $c; $i++)
			{
				if (isset($array[$i]['function']) && $array[$i]['function'] == 'log_error')
				{
					$found_filename = $array[$i]['file'];
					break;
				}
			}

		if (isset($found_filename))
			$file = str_replace('\\', '/', $found_filename);
	}
	else
		// Windows-style slashes don't play well, let's convert them to Unix style.
		$file = str_replace('\\', '/', $file);

	$line = ($line == null) ? 0 : (int) $line;

	// Just in case there's no id_member or IP set yet.
	if (empty(we::$id))
		we::$id = 0;
	if (empty(we::$user['ip']))
		we::$user['ip'] = '';
	if (empty(we::$user['url']))
	{
		$is_secure = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';
		we::$user['url'] = ($is_secure ? 'https://' : 'http://') . (empty($_SERVER['REAL_HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['REAL_HTTP_HOST']) . $_SERVER['REQUEST_URI'];
	}

	// Find the best query string we can...
	$query_string = we::$user['url'];

	// Is this an external 404 error? Then the referrer URL might be of more help.
	if (!empty($referrer))
		$query_string = $referrer;

	// Are we using shortened or pretty URLs here?
	$is_short = strpos($query_string, SCRIPT . '?');
	$has_protocol = strpos($query_string, '://') > 0;

	// Just so we know what board error messages are from. If it's a pretty URL, we already know that.
	if (($is_short === false && $has_protocol) && isset($_POST['board']) && !isset($_GET['board']))
		$query_string .= ($query_string == '' ? 'board=' : ';board=') . $_POST['board'];

	if ($is_short === 0)
		$query_string = substr($query_string, strlen(SCRIPT));
	if ($is_short === false && !$has_protocol)
		$is_short = 0;
	if ($is_short === 0 && !empty($query_string) && $query_string[0] === '?')
		$is_short = false;

	// Don't log session data in the url twice, it's a waste.
	$query_string = preg_replace('~' . session_name() . '=' . session_id() . '[&;]~', '', $query_string);
	$query_string = htmlspecialchars(($is_short === false ? '' : '?') . $query_string);

	// What types of categories do we have?
	$known_error_types = array(
		'general',
		'critical',
		'database',
		'undefined_vars',
		'mail',
		'password',
		'user',
		'template',
		'debug',
		'filenotfound',
	);

	// Make sure the category that was specified is a valid one
	$error_type = in_array($error_type, $known_error_types) && $error_type !== true ? $error_type : 'general';

	// There may be an alternate case of error type: it might be plugin-related.
	if (!empty($plugin_dir) && strpos($file, $plugin_dir) === 0)
		foreach ($context['plugins_dir'] as $plugin_id => $plugin_path)
		{
			if (strpos($file, $plugin_path) === 0)
			{
				$error_type = $plugin_id;
				break;
			}
		}

	// Also, mail related errors may be logged unexpectedly.
	if (strpos($error_message, '2: mail()') === 0)
		$error_type = 'mail';

	// If we found the filename manually, unlog it now.
	if (isset($found_filename))
		$file = '';

	// Don't log the same error countless times, as we can get in a cycle of depression...
	$error_info = array(we::$id, time(), get_ip_identifier(we::$user['ip']), $query_string, $error_message, $error_type, $file, $line);
	if (empty($last_error) || $last_error != $error_info)
	{
		// Insert the error into the database.
		wesql::insert('',
			'{db_prefix}log_errors',
			array('id_member' => 'int', 'log_time' => 'int', 'ip' => 'int', 'url' => 'string-65534', 'message' => 'string-65534', 'error_type' => 'string-255', 'file' => 'string-255', 'line' => 'int'),
			$error_info
		);
		$last_error = $error_info;

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
function fatal_error($error, $log = 'general', $header = 403)
{
	global $txt, $settings;

	issue_http_header($header);

	// We don't have $txt yet, but that's okay...
	if (empty($txt))
		exit($error);

	updateOnlineWithError($error, false);
	setup_fatal_error_context($log || (!empty($settings['enableErrorLogging']) && $settings['enableErrorLogging'] == 2) ? log_error($error, $log) : $error);
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
function fatal_lang_error($error, $log = 'general', $sprintf = array(), $header = 403)
{
	global $txt, $settings, $context;
	static $fatal_error_called = false;

	if (!AJAX)
		issue_http_header($header);

	// Try to load a theme if we don't have one.
	if (empty($context['theme_loaded']) && empty($fatal_error_called))
	{
		$fatal_error_called = true;
		loadTheme();
	}

	// If we have no theme stuff, we can't have the language file...
	if (empty($context['theme_loaded']) && !isset($txt[$error]))
		exit($error);

	$reload_lang_file = true;
	// Log the error in the forum's language, but don't waste the time if we aren't logging.
	if ($log || (!empty($settings['enableErrorLogging']) && $settings['enableErrorLogging'] == 2))
	{
		loadLanguage('Errors', $settings['language']);
		$reload_lang_file = !empty(we::$user['language']) && $settings['language'] != we::$user['language'];
		$error_message = empty($sprintf) ? $txt[$error] : vsprintf($txt[$error], $sprintf);
		log_error($error_message, $log);
	}

	// Load the language file, only if it needs to be reloaded.
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
	global $settings, $db_show_debug;

	// Ignore errors if default reporting behavior was overridden (e.g. through SSI.)
	if (error_reporting() === 0)
		return;

	if (strpos($file, 'eval()') !== false && !empty($settings['current_include_filename']))
	{
		$array = debug_backtrace();
		for ($i = 0; $i < count($array); $i++)
		{
			if ($array[$i]['function'] !== 'execBlock')
				continue;

			// This is a bug in PHP, with eval, it seems!
			if (empty($array[$i]['args']))
				$i++;
			break;
		}

		if (isset($array[$i]) && !empty($array[$i]['args']))
			$file = realpath($settings['current_include_filename']) . ' (' . $array[$i]['args'][0] . ' block - eval?)';
		else
			$file = realpath($settings['current_include_filename']) . ' (eval?)';
	}

	if (!empty($db_show_debug))
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

		// Debugging! This should look like a PHP error message.
		echo '<br>
<strong>', $error_level % 255 == E_ERROR ? 'Error' : ($error_level % 255 == E_WARNING ? 'Warning' : 'Notice'), '</strong>: ', $error_string, ' in <strong>', $file, '</strong> on line <strong>', $line, '</strong><br>';
	}

	$error_type = strpos(strtolower($error_string), 'undefined') !== false ? 'undefined_vars' : 'general';

	$message = log_error($error_level . ': ' . $error_string, $error_type, $file, $line);

	// Let's give hooks a chance to output a bit differently
	call_hook('output_error', array(&$message, $error_type, $error_level, $file, $line));

	// Dying on these errors only causes MORE problems (blank pages!)
	if ($file == 'Unknown')
		return;

	// If this is an E_ERROR or E_USER_ERROR.... die. Violently so.
	if ($error_level % 255 == E_ERROR)
		obExit(false);
	else
		return;

	// If this is an E_ERROR, E_USER_ERROR, E_WARNING, or E_USER_WARNING.... die. Violently so.
	if ($error_level % 255 == E_ERROR || $error_level % 255 == E_WARNING)
		fatal_error(allowedTo('admin_forum') ? $message : $error_string, false);

	die('Hacking attempt...');
}

/**
 * Prepare a fatal error for being displayed.
 *
 * - Attempt to prevent recursively trying to error
 * - Check if the theme is loaded (e.g. from action=dlattach, where the theme is not normally loaded)
 * - Set up general page details - no robots meta tag, the page title (based on the error message if possible, in $context['error_title'] if set)
 * - Load the fatal_error template
 * - Check whether we are using SSI and if so whether SSI-specific fatal error handling is indicated.
 * - Finally, pass control to {@link obExit()} to end execution.
 *
 * IMPORTANT: If you are creating a bridge to Wedge or modifying this function, you MUST make ABSOLUTELY SURE that this function quits and
 * DOES NOT RETURN TO NORMAL PROGRAM FLOW. Otherwise, security error messages will not be shown, and your forum will be in a very easily hackable state.
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
	if (WEDGE != 'SSI' && empty($context['theme_loaded']))
		loadTheme();

	// Don't bother indexing errors mate...
	$context['robot_no_index'] = true;

	if (!isset($context['error_title']))
		$context['error_title'] = $txt['error_occurred'];
	$context['error_message'] = isset($context['error_message']) ? $context['error_message'] : $error_message;

	if (empty($context['page_title']))
		$context['page_title'] = $context['error_title'];

	// Load the template and set the block.
	loadTemplate('Errors');
	wetem::load('fatal_error');

	// If this is SSI, what do they want us to do?
	if (WEDGE == 'SSI')
	{
		if (!empty($ssi_on_error_method) && $ssi_on_error_method !== true && is_callable($ssi_on_error_method))
			$ssi_on_error_method();
		elseif (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
			execBlock('fatal_error');

		// No layers?
		if (empty($ssi_on_error_method) || $ssi_on_error_method !== true)
			exit;
	}

	// We want whatever for the header, and a footer. (footer includes block!)
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
	global $mbname, $maintenance, $mtitle, $mmessage, $settings, $cachedir;
	global $db_connection, $webmaster_email, $db_last_error, $db_error_send;

	// Just check we're not in any buffers, just in case.
	while (@ob_end_clean());

	// Don't cache this page!
	header('Expires: Wed, 25 Aug 2010 17:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache');

	// Send the right error codes.
	header('HTTP/1.1 503 Service Temporarily Unavailable');
	header('Status: 503 Service Temporarily Unavailable');
	header('Retry-After: 3600');

	if ($loadavg == false)
	{
		// For our purposes, we're gonna want this on if at all possible.
		$settings['cache_enable'] = '1';
		$db_last_error = @filemtime($cachedir . '/error.lock');

		if ($db_last_error < time() - 3600 * 24 * 3 && empty($maintenance) && !empty($db_error_send))
		{
			loadSource('Subs-Admin');

			// Log when we last did this.
			@touch($cachedir . '/error.lock');

			// Language files aren't loaded yet :(.
			$db_error = @wesql::error($db_connection);
			@mail($webmaster_email, $mbname . ': Wedge Database Error!', 'There has been a problem with the database!' . ($db_error == '' ? '' : "\nMySQL reported:\n" . $db_error) . "\n\n" . 'This is a notice email to let you know that Wedge could not connect to the database, contact your host if this continues.');
		}
	}

	if (!empty($maintenance))
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="robots" content="noindex">
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
		<meta name="robots" content="noindex">
		<title>Temporarily Unavailable</title>
	</head>
	<body>
		<h3>Temporarily Unavailable</h3>
		Due to high stress on the server the forum is temporarily unavailable. Please try again later.
	</body>
</html>';
	// What to do? Language files haven't and can't be loaded yet...
	else
		echo '<!DOCTYPE html>
<html>
	<head>
		<meta name="robots" content="noindex">
		<title>Connection Problems</title>
	</head>
	<body>
		<h3>Connection Problems</h3>
		Sorry, Wedge was unable to connect to the database. This may be caused by the server being busy. Please try again later.
	</body>
</html>';

	exit;
}

/**
 * Outputs the correct HTTP header, typically to be used in error handling cases.
 *
 * @param int $header The HTTP status code to be used, e.g. 403, 404.
 */
function issue_http_header($header)
{
	// All the codes we might want to send. They should not be translated.
	// There are some extensions such as those supplied by nginx and WebDAV, but only standard HTTP and standards-proposed extensions that are relevant are present here.
	$codes = array(
		200 => 'OK', // supplied in case someone wants to issue an error page for some reason but send it with a 200 OK header
		400 => 'Bad Request',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		429 => 'Too Many Requests', // standards-proposed
		431 => 'Request Header Fields Too Large', // standards-proposed
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
	);

	if (!isset($codes[$header]))
		$header = 403;

	// Certain configurations need one, certain configurations need the other.
	if (!empty($_SERVER['SERVER_PROTOCOL']))
		header($_SERVER['SERVER_PROTOCOL'] . ' ' . $header . ' ' . $codes[$header]);
	header('Status: ' . $header . ' ' . $codes[$header]);
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
	global $settings;

	// Don't bother if Who's Online is disabled.
	if (empty($settings['who_enabled']))
		return;

	$session_id = we::$is_guest ? 'ip' . we::$user['ip'] : session_id();

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
		list ($url) = wesql::fetch_row($query);
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
