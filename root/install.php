<?php
/**
 * Wedge
 *
 * This file handles installing Wedge, and more importantly running all the database creation steps.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

define('WEDGE_INSTALLER', 1);

$GLOBALS['current_wedge_version'] = '0.1';
$GLOBALS['required_php_version'] = '5.2.4';
$GLOBALS['required_pcre_version'] = '7.2';

// Don't have PHP support, do you?
// ><html dir="ltr"><head><title>Error!</title></head><body>Sorry, this installer requires PHP!<div style="display: none">

// Database info.
$db = array(
	'required_server' => '5.0.3',
	'required_client' => '5.0.0',
	'default_user' => 'mysql.default_user',
	'default_password' => 'mysql.default_password',
	'default_host' => 'mysql.default_host',
	'default_port' => 'mysql.default_port',
	'validate_prefix' => create_function('&$value', '
		$value = preg_replace(\'~[^A-Za-z0-9_\$]~\', \'\', $value);
		return true;
	'),
);

// Initialize everything and load the language files.
initialize_inputs();
load_lang_file();

// This is what we are.
$installurl = $_SERVER['PHP_SELF'];
// This is where Wedge is.
$wedgesite = 'http://wedge.org/files';

// All the steps in detail.
// Number, Name, Function, Progress Weight.
$incontext['steps'] = array(
	0 => array(1, $txt['install_step_welcome'], 'Welcome', 0),
	1 => array(2, $txt['install_step_writable'], 'CheckFilesWritable', 10),
	2 => array(3, $txt['install_step_databaseset'], 'DatabaseSettings', 15),
	3 => array(4, $txt['install_step_forum'], 'ForumSettings', 40),
	4 => array(5, $txt['install_step_databasechange'], 'DatabasePopulation', 15),
	5 => array(6, $txt['install_step_admin'], 'AdminAccount', 20),
	6 => array(7, $txt['install_step_delete'], 'DeleteInstall', 0),
);

// Default title...
$incontext['page_title'] = $txt['wedge_installer'];

// What step are we on?
$incontext['current_step'] = isset($_GET['step']) ? (int) $_GET['step'] : 0;

// Loop through all the steps doing each one as required.
$incontext['overall_percent'] = 0;
foreach ($incontext['steps'] as $num => $step)
{
	// We need to declare we're in the installer so that updateSettings doesn't get called.
	// But we need to leave it callable in the final step of the installer for when the admin is created.
	$incontext['enable_update_settings'] = $step[2] == 'DeleteInstall';

	if ($num >= $incontext['current_step'])
	{
		// The current weight of this step in terms of overall progress.
		$incontext['step_weight'] = $step[3];
		// Make sure we reset the skip button.
		$incontext['skip'] = false;

		// Call the step and if it returns false that means pause!
		if (function_exists($step[2]) && $step[2]() === false)
			break;
		elseif (function_exists($step[2]))
			$incontext['current_step']++;

		// No warnings pass on.
		$incontext['warning'] = '';
	}
	$incontext['overall_percent'] += $step[3];
}

// Actually do the template stuff.
installExit();

function initialize_inputs()
{
	global $incontext;

	// Turn off magic quotes runtime and enable error reporting.
	if (function_exists('set_magic_quotes_runtime'))
		@set_magic_quotes_runtime(0);
	error_reporting(E_ALL);

	if (!isset($_GET['obgz']))
	{
		ob_start();

		if (ini_get('session.save_handler') == 'user')
			ini_set('session.save_handler', 'files');
		if (function_exists('session_start'))
			@session_start();
	}
	else
	{
		ob_start('ob_gzhandler');

		if (ini_get('session.save_handler') == 'user')
			ini_set('session.save_handler', 'files');
		session_start();

		if (!headers_sent())
			echo '<div class="windowbg2 wrc" style="text-align: center; font-size: 16pt">
	<strong>', htmlspecialchars($_GET['pass_string']), '</strong>
</div>';
		exit;
	}

	// Are we calling the backup css file?
	if (isset($_GET['infile_css']))
	{
		header('Content-Type: text/css');
		template_css();
		exit;
	}

	// Anybody home?
	if (!isset($_GET['xml']))
	{
		$incontext['remote_files_available'] = false;
		$test = @fsockopen('wedge.org', 80, $errno, $errstr, 1);
		if ($test)
			$incontext['remote_files_available'] = true;
		@fclose($test);
	}

	// Add slashes, as long as they aren't already being added.
	if (!function_exists('get_magic_quotes_gpc') || @get_magic_quotes_gpc() == 0)
		foreach ($_POST as $k => $v)
			$_POST[$k] = addslashes($v);

	// This is really quite simple; if ?delete is on the URL, delete the installer...
	// @todo: do this when first visiting the forum instead. It should be done anyway...
	if (isset($_GET['delete']))
	{
		if (isset($_SESSION['installer_temp_ftp']))
		{
			$ftp = new ftp_connection($_SESSION['installer_temp_ftp']['server'], $_SESSION['installer_temp_ftp']['port'], $_SESSION['installer_temp_ftp']['username'], $_SESSION['installer_temp_ftp']['password']);
			$ftp->chdir($_SESSION['installer_temp_ftp']['path']);

			$ftp->unlink('install.php');
			$ftp->unlink('webinstall.php');
			$ftp->unlink('install.sql');

			// We won't bother with CSS/JS caches here, it poses no security threat. Let the user do the job themselves...
			$ftp->close();

			unset($_SESSION['installer_temp_ftp']);
		}
		else
		{
			@unlink(__FILE__);
			@unlink(dirname(__FILE__) . '/webinstall.php');
			@unlink(dirname(__FILE__) . '/install.sql');

			// Empty CSS and JavaScript caches, in case user chose to enable compression during the install process.
			clean_cache('css');
			clean_cache('js');
		}

		// Now just output a blank GIF... (Same code as in the verification code generator.)
		header('Content-Type: image/gif');
		exit("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
	}

	// Make sure a timezone is set, it isn't always. But let's use the system derived one as much as possible.
	date_default_timezone_set(@date_default_timezone_get());

	// Force an integer step, defaulting to 0.
	$_GET['step'] = (int) @$_GET['step'];
}

// Load the list of language files, and the current language file.
function load_lang_file()
{
	global $txt, $incontext, $settings;

	$incontext['detected_languages'] = array();

	// Set up a default language before we go any further.
	$settings['language'] = 'english';

	$original_txt = $txt;
	// Make sure the languages directory actually exists.
	$folder = dirname(__FILE__) . '/Themes/default/languages';
	if (file_exists($folder))
	{
		// Find all the "Install" language files in the directory.
		// Don't use scandir(), as we're not sure about PHP 5 support for now.
		$dir = dir($folder);
		while ($entry = $dir->read())
			if (substr($entry, 0, 8) == 'Install.' && substr($entry, -4) == '.php')
			{
				$txt = array();
				require_once($folder . '/index.' . substr($entry, 8));
				if (!empty($txt['lang_name']))
					$incontext['detected_languages'][$entry] = '&lt;img src="Themes/default/languages/Flag.' . substr($entry, 8, strlen($entry) - 12) . '.png"&gt; ' . $txt['lang_name'];
			}
		$dir->close();
	}

	$txt = $original_txt;

	// Didn't find any, show an error message!
	if (empty($incontext['detected_languages']))
	{
		// Let's not cache this message, eh?
		header('Expires: Wed, 25 Aug 2010 17:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		echo '<!DOCTYPE html>
<html>
	<head>
		<title>Wedge Installer: Error!</title>
	</head>
	<body style="font-family: sans-serif"><div style="width: 600px">
		<h1 style="font-size: 14pt">A critical error has occurred.</h1>

		<p>This installer was unable to find the installer\'s language file or files. They should be found under:</p>

		<div style="margin: 1ex; font-family: monospace; font-weight: bold">', dirname($_SERVER['PHP_SELF']) != '/' ? dirname($_SERVER['PHP_SELF']) : '', '/Themes/default/languages</div>

		<p>In some cases, FTP clients do not properly upload files with this many folders. Please double check to make sure you <strong>have uploaded all the files in the distribution</strong>.</p>
		<p>If that doesn\'t help, please make sure this install.php file is in the same place as the Themes folder.</p>

		<p>If you continue to get this error message, feel free to <a href="http://wedge.org/">look to us for support</a>.</p>
	</div></body>
</html>';
		exit;
	}

	// Override the language file?
	if (isset($_GET['lang_file']))
		$_SESSION['installer_temp_lang'] = $_GET['lang_file'];
	elseif (isset($GLOBALS['HTTP_GET_VARS']['lang_file']))
		$_SESSION['installer_temp_lang'] = $GLOBALS['HTTP_GET_VARS']['lang_file'];

	// Make sure it exists, if it doesn't reset it.
	if (!isset($_SESSION['installer_temp_lang']) || preg_match('~[^.\w-]~', $_SESSION['installer_temp_lang']) === 1 || !file_exists(dirname(__FILE__) . '/Themes/default/languages/' . $_SESSION['installer_temp_lang']))
	{
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
		{
			// break up string into pieces (languages and q factors)
			preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), $lang_parse);
			if (count($lang_parse[1]))
			{
				// create a list like "en" => 0.8
				$preferred = array_combine($lang_parse[1], $lang_parse[4]);

				// set default to 1 for any without q factor (IE fix)
				foreach ($preferred as $lang => $val)
					if ($val === '')
						$preferred[$lang] = 1;

				// sort list based on value
				arsort($preferred, SORT_NUMERIC);
			}

			// This is the list of known Wedge language packs/mappings as of March 2013.
			$langs = array(
				'en' => 'Install.english.php',
				'en-gb' => 'Install.english-uk.php',
				'fr' => 'Install.french.php',
			);

			foreach ($preferred as $key => $value)
			{
				$lang = isset($langs[$key]) ? $langs[$key] : (isset($langs[substr($key, 0, 2)]) ? $langs[substr($key, 0, 2)] : '');
				if (!empty($lang) && isset($incontext['detected_languages'][$lang]))
				{
					$_SESSION['installer_temp_lang'] = $lang;
					break;
				}
			}
		}

		// Use the first one...
		if (empty($_SESSION['installer_temp_lang']))
			list ($_SESSION['installer_temp_lang']) = array_keys($incontext['detected_languages']);
	}

	// And now include the actual language file itself.
	require_once(dirname(__FILE__) . '/Themes/default/languages/Install.english.php');
	if ($_SESSION['installer_temp_lang'] != 'Install.english.php')
		require_once(dirname(__FILE__) . '/Themes/default/languages/' . $_SESSION['installer_temp_lang']);
}

// This handy function loads some settings and the like.
function load_database()
{
	global $settings, $sourcedir, $db_prefix, $db_connection, $db_name, $db_user;

	if (empty($sourcedir))
		$sourcedir = dirname(__FILE__) . '/Sources';

	// Need this to check whether we need the database password.
	require(dirname(__FILE__) . '/Settings.php');
	if (!defined('WEDGE'))
		define('WEDGE', 1);

	$settings['disableQueryCheck'] = true;

	// Connect the database.
	if (!$db_connection)
	{
		require_once($sourcedir . '/Class-DB.php');
		wesql::getInstance();

		if (!$db_connection)
			$db_connection = wesql::connect($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => $db_persist));
	}
}

// This is called upon exiting the installer, for template etc.
function installExit($fallThrough = false)
{
	global $incontext, $installurl, $txt;

	// Send character set.
	header('Content-Type: text/html; charset=UTF-8');

	// We usually dump our templates out.
	if (!$fallThrough)
	{
		// The top install bit.
		template_install_above();

		// Call the template.
		if (isset($incontext['block']))
		{
			$incontext['form_url'] = $installurl . '?step=' . $incontext['current_step'];

			call_user_func('template_' . $incontext['block']);
		}

		// Show the footer.
		template_install_below();
	}

	// Bang - gone!
	exit;
}

// This checks whether GD2 is available and all the neat little gizmos we want to use, returns true if all good, false if not.
function checkGD2()
{
	$gd2_functions = array(
		'imagecreatetruecolor',
		'imagegif',
		'imagepng',
		'imagejpeg',
		'imagecreatefromstring',
		'imagecreatefrompng',
		'imagecreatefromgif',
	);

	foreach ($gd2_functions as $function)
		if (!is_callable($function))
			return false;

	return true;
}

function Welcome()
{
	global $incontext, $txt, $db, $installurl;

	$incontext['page_title'] = $txt['install_welcome'];
	$incontext['block'] = 'welcome_message';

	// Done the submission?
	if (isset($_POST['contbutt']))
		return true;

	// Check the PHP version.
	if (!function_exists('version_compare') || (version_compare($GLOBALS['required_php_version'], PHP_VERSION) > 0))
		$incontext['warning'] = $txt['error_php_too_low'];
	elseif (version_compare($GLOBALS['required_pcre_version'], PCRE_VERSION) > 0) // PCRE_VERSION was introduced in PHP 5.2.4. Lucky.
		$incontext['warning'] = $txt['error_pcre_too_low'];

	// See if we think they have already installed it?
	if (is_readable(dirname(__FILE__) . '/Settings.php'))
	{
		$probably_installed = 0;
		foreach (file(dirname(__FILE__) . '/Settings.php') as $line)
		{
			if (preg_match('~^\$db_passwd\s=\s\'([^\']+)\';$~', $line))
				$probably_installed++;
			if (preg_match('~^\$boardurl\s=\s\'([^\']+)\';~', $line) && !preg_match('~^\$boardurl\s=\s\'http://127\.0\.0\.1/wedge\';~', $line))
				$probably_installed++;
		}

		if ($probably_installed == 2)
			$incontext['warning'] = $txt['error_already_installed'];
	}

	// Is some database support even compiled in?
	$mysql_supported = false;

	if (function_exists('mysqli_connect'))
	{
		if (!file_exists(dirname(__FILE__) . '/install.sql'))
		{
			$notFoundSQLFile = true;
			$txt['error_db_script_missing'] = sprintf($txt['error_db_script_missing'], 'install.sql');
		}
		else
			$mysql_supported = true;
	}

	if (!$mysql_supported)
		$error = empty($notFoundSQLFile) ? 'error_db_missing' : 'error_db_script_missing';
	// How about session support? Did some crazy sysadmin remove it?
	elseif (!function_exists('session_start'))
		$error = 'error_session_missing';
	// Make sure they uploaded all the files.
	elseif (!file_exists(dirname(__FILE__) . '/index.php'))
		$error = 'error_missing_files';
	// Very simple check on the session.save_path for Windows.
	// !!! Move this down later if they don't use database-driven sessions?
	elseif (ini_get('session.save_path') == '/tmp' && substr(__FILE__, 1, 2) == ':\\')
		$error = 'error_session_save_path';
	// What about GD2 and related functions?
	elseif (!checkGD2())
		$error = 'error_no_gd_library';
	elseif (!function_exists('simplexml_load_string'))
		$error = 'error_no_sxml';

	// Since each of the three messages would look the same, anyway...
	if (isset($error))
		$incontext['error'] = $txt[$error];

	// Mod_security blocks everything that smells funny. Let Wedge handle security.
	if (!fixModSecurity() && !isset($_GET['overmodsecurity']))
		$incontext['error'] = $txt['error_mod_security'] . '<br><br><a href="' . $installurl . '?overmodsecurity=true">' . $txt['error_message_click'] . '</a> ' . $txt['error_message_bad_try_again'];

	return false;
}

function CheckFilesWritable()
{
	global $txt, $incontext;

	$incontext['page_title'] = $txt['ftp_checking_writable'];
	$incontext['block'] = 'chmod_files';

	// This file is special... We only want to be able to read it.
	if (file_exists(dirname(__FILE__) . '/MGalleryItem.php'))
		@chmod(dirname(__FILE__) . '/MGalleryItem.php', 0644);

	// Now for the files and folders we want to make writable.
	$writable_files = array(
		'attachments',
		'avatars',
		'cache',
		'css',
		'js',
		'Smileys',
		'Themes',
		'Settings.php',
		'Settings_bak.php'
	);
	foreach ($incontext['detected_languages'] as $lang => $temp)
		$extra_files[] = 'Themes/default/languages/' . $lang;

	// With mod_security installed, we could attempt to fix it with .htaccess.
	if (function_exists('apache_get_modules') && in_array('mod_security', apache_get_modules()))
		$writable_files[] = file_exists(dirname(__FILE__) . '/.htaccess') ? '.htaccess' : '.';

	$failed_files = array();

	// On linux, it's easy - just use is_writable!
	if (substr(__FILE__, 1, 2) != ':\\')
	{
		foreach ($writable_files as $file)
		{
			if (!is_writable(dirname(__FILE__) . '/' . $file))
			{
				@chmod(dirname(__FILE__) . '/' . $file, 0755);

				// Well, 755 hopefully worked... if not, try 777.
				if (!is_writable(dirname(__FILE__) . '/' . $file) && !@chmod(dirname(__FILE__) . '/' . $file, 0777))
					$failed_files[] = $file;
			}
		}
		foreach ($extra_files as $file)
			@chmod(dirname(__FILE__) . (empty($file) ? '' : '/' . $file), 0777);
	}
	// Windows is trickier. Let's try opening for r+...
	else
	{
		foreach ($writable_files as $file)
		{
			// Folders can't be opened for write... but the index.php in them can ;)
			if (is_dir(dirname(__FILE__) . '/' . $file))
				$file .= '/index.php';

			// Funny enough, chmod actually does do something on windows - it removes the read only attribute.
			@chmod(dirname(__FILE__) . '/' . $file, 0777);
			$fp = @fopen(dirname(__FILE__) . '/' . $file, 'r+');

			// Hmm, okay, try just for write in that case...
			if (!is_resource($fp))
				$fp = @fopen(dirname(__FILE__) . '/' . $file, 'w');

			if (!is_resource($fp))
				$failed_files[] = $file;

			@fclose($fp);
		}
		foreach ($extra_files as $file)
			@chmod(dirname(__FILE__) . (empty($file) ? '' : '/' . $file), 0777);
	}

	$failure = count($failed_files) >= 1;

	if (!isset($_SERVER))
		return !$failure;

	// Put the list into context.
	$incontext['failed_files'] = $failed_files;

	// It's not going to be possible to use FTP on windows to solve the problem...
	if ($failure && substr(__FILE__, 1, 2) == ':\\')
	{
		$incontext['error'] = $txt['error_windows_chmod'] . '
					<ul style="margin: 2.5ex; font-family: monospace">
						<li>' . implode('</li>
						<li>', $failed_files) . '</li>
					</ul>';

		return false;
	}
	// We're going to have to use... FTP!
	elseif ($failure)
	{
		// Load any session data we might have...
		if (!isset($_POST['ftp_username']) && isset($_SESSION['installer_temp_ftp']))
		{
			$_POST['ftp_server'] = $_SESSION['installer_temp_ftp']['server'];
			$_POST['ftp_port'] = $_SESSION['installer_temp_ftp']['port'];
			$_POST['ftp_username'] = $_SESSION['installer_temp_ftp']['username'];
			$_POST['ftp_password'] = $_SESSION['installer_temp_ftp']['password'];
			$_POST['ftp_path'] = $_SESSION['installer_temp_ftp']['path'];
		}

		$incontext['ftp_errors'] = array();

		if (isset($_POST['ftp_username']))
		{
			$ftp = new ftp_connection($_POST['ftp_server'], $_POST['ftp_port'], $_POST['ftp_username'], $_POST['ftp_password']);

			if ($ftp->error === false)
			{
				// Try it without /home/abc just in case they messed up.
				if (!$ftp->chdir($_POST['ftp_path']))
				{
					$incontext['ftp_errors'][] = $ftp->last_message;
					$ftp->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['ftp_path']));
				}
			}
		}

		if (!isset($ftp) || $ftp->error !== false)
		{
			if (!isset($ftp))
				$ftp = new ftp_connection(null);
			// Save the error so we can mess with listing...
			elseif ($ftp->error !== false && empty($incontext['ftp_errors']) && !empty($ftp->last_message))
				$incontext['ftp_errors'][] = $ftp->last_message;

			list ($username, $detect_path, $found_path) = $ftp->detect_path(dirname(__FILE__));

			if (empty($_POST['ftp_path']) && $found_path)
				$_POST['ftp_path'] = $detect_path;

			if (!isset($_POST['ftp_username']))
				$_POST['ftp_username'] = $username;

			// Set the username etc, into context.
			$incontext['ftp'] = array(
				'server' => isset($_POST['ftp_server']) ? $_POST['ftp_server'] : 'localhost',
				'port' => isset($_POST['ftp_port']) ? $_POST['ftp_port'] : '21',
				'username' => isset($_POST['ftp_username']) ? $_POST['ftp_username'] : '',
				'path' => isset($_POST['ftp_path']) ? $_POST['ftp_path'] : '/',
				'path_msg' => !empty($found_path) ? $txt['ftp_path_found_info'] : $txt['ftp_path_info'],
			);

			return false;
		}
		else
		{
			$_SESSION['installer_temp_ftp'] = array(
				'server' => $_POST['ftp_server'],
				'port' => $_POST['ftp_port'],
				'username' => $_POST['ftp_username'],
				'password' => $_POST['ftp_password'],
				'path' => $_POST['ftp_path']
			);

			$failed_files_updated = array();

			foreach ($failed_files as $file)
			{
				if (!is_writable(dirname(__FILE__) . '/' . $file))
					$ftp->chmod($file, 0755);
				if (!is_writable(dirname(__FILE__) . '/' . $file))
					$ftp->chmod($file, 0777);
				if (!is_writable(dirname(__FILE__) . '/' . $file))
				{
					$failed_files_updated[] = $file;
					$incontext['ftp_errors'][] = rtrim($ftp->last_message) . ' -> ' . $file . "\n";
				}
			}

			$ftp->close();

			// Are there any errors left?
			if (count($failed_files_updated) >= 1)
			{
				// Guess there are...
				$incontext['failed_files'] = $failed_files_updated;

				// Set the username etc, into context.
				$incontext['ftp'] = $_SESSION['installer_temp_ftp'] += array(
					'path_msg' => $txt['ftp_path_info'],
				);

				return false;
			}
		}
	}

	return true;
}

function DatabaseSettings()
{
	global $txt, $db, $incontext;

	$incontext['block'] = 'database_settings';
	$incontext['page_title'] = $txt['db_settings'];
	$incontext['continue'] = 1;

	// Set up the defaults.
	$incontext['db']['server'] = 'localhost';
	$incontext['db']['user'] = '';
	$incontext['db']['name'] = '';
	$incontext['db']['pass'] = '';

	if (function_exists('mysqli_connect'))
	{
		if (isset($db['default_host']))
			$incontext['db']['server'] = ini_get($db['default_host']) or $incontext['db']['server'] = 'localhost';
		if (isset($db['default_user']))
		{
			$incontext['db']['user'] = ini_get($db['default_user']);
			$incontext['db']['name'] = ini_get($db['default_user']);
		}
		if (isset($db['default_password']))
			$incontext['db']['pass'] = ini_get($db['default_password']);
		if (isset($db['default_port']))
			$db_port = ini_get($db['default_port']);
	}

	// Override for repost.
	if (isset($_POST['db_user']))
	{
		$incontext['db']['user'] = $_POST['db_user'];
		$incontext['db']['name'] = $_POST['db_name'];
		$incontext['db']['server'] = $_POST['db_server'];
		$incontext['db']['prefix'] = $_POST['db_prefix'];
	}
	else
		$incontext['db']['prefix'] = 'wedge_';

	// Should we use a non standard port?
	if (!empty($db_port))
		$incontext['db']['server'] .= ':' . $db_port;

	// Are we submitting?
	if (isset($_POST['db_name']))
	{
		// What type are they trying?
		$db_prefix = $_POST['db_prefix'];
		// Validate the prefix.
		$valid_prefix = $db['validate_prefix']($db_prefix);

		if ($valid_prefix !== true)
		{
			$incontext['error'] = $valid_prefix;
			return false;
		}

		// Take care of these variables...
		$vars = array(
			'db_name' => $_POST['db_name'],
			'db_user' => $_POST['db_user'],
			'db_passwd' => isset($_POST['db_passwd']) ? $_POST['db_passwd'] : '',
			'db_server' => $_POST['db_server'],
			'db_prefix' => $db_prefix,
			// The cookiename is special; we want it to be the same if it ever needs to be reinstalled with the same info.
			'cookiename' => 'WedgeCookie' . abs(crc32($_POST['db_name'] . preg_replace('~[^A-Za-z0-9_$]~', '', $_POST['db_prefix'])) % 1000),
		);

		// God I hope it saved!
		if (!updateSettingsFile($vars) && substr(__FILE__, 1, 2) == ':\\')
		{
			$incontext['error'] = $txt['error_windows_chmod'];
			return false;
		}

		// Make sure it works.
		require(dirname(__FILE__) . '/Settings.php');

		if (empty($sourcedir))
			$sourcedir = dirname(__FILE__) . '/Sources';

		// Better find the database file!
		if (!file_exists($sourcedir . '/Class-DB.php'))
		{
			$incontext['error'] = sprintf($txt['error_db_file'], 'Class-DB.php');
			return false;
		}

		// Now include it for database functions!
		if (!defined('WEDGE'))
			define('WEDGE', 1);
		$settings['disableQueryCheck'] = true;
		require_once($sourcedir . '/Class-DB.php');

		// Attempt a connection.
		$db_connection = wesql::connect($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('non_fatal' => true, 'dont_select_db' => true));

		// No dice? Let's try adding the prefix they specified, just in case they misread the instructions ;)
		if ($db_connection == null)
		{
			$db_error = @wesql::error();

			$db_connection = wesql::connect($db_server, $db_name, $_POST['db_prefix'] . $db_user, $db_passwd, $db_prefix, array('non_fatal' => true, 'dont_select_db' => true));
			if ($db_connection != null)
			{
				$db_user = $_POST['db_prefix'] . $db_user;
				updateSettingsFile(array('db_user' => $db_user));
			}
		}

		// Still no connection? Big fat error message. :P
		if (!$db_connection)
		{
			$incontext['error'] = $txt['error_db_connect'] . '<div style="margin: 2.5ex; font-family: monospace"><strong>' . $db_error . '</strong></div>';
			return false;
		}

		// Do they meet the install requirements?
		if ((version_compare($db['required_client'], preg_replace('~^\D*|\-.+?$~', '', mysqli_get_client_info())) > 0) || (version_compare($db['required_server'], preg_replace('~^\D*|\-.+?$~', '', mysqli_get_server_info($db_connection))) > 0))
		{
			$incontext['error'] = sprintf($txt['error_db_too_low'], 'Server: ' . mysqli_get_server_info() . ' / Client: ' . mysqli_get_client_info());
			return false;
		}

		// Let's try that database on for size... assuming we haven't already lost the opportunity.
		if ($db_name != '')
		{
			wesql::query("
				CREATE DATABASE IF NOT EXISTS `$db_name`",
				array(
					'security_override' => true,
					'db_error_skip' => true,
				),
				$db_connection
			);

			// Okay, let's try the prefix if it didn't work...
			if (!wesql::select_db($db_name, $db_connection) && $db_name != '')
			{
				wesql::query("
					CREATE DATABASE IF NOT EXISTS `$_POST[db_prefix]$db_name`",
					array(
						'security_override' => true,
						'db_error_skip' => true,
					),
					$db_connection
				);

				if (wesql::select_db($_POST['db_prefix'] . $db_name, $db_connection))
				{
					$db_name = $_POST['db_prefix'] . $db_name;
					updateSettingsFile(array('db_name' => $db_name));
				}
			}

			// Okay, now let's try to connect...
			if (!wesql::select_db($db_name, $db_connection))
			{
				$incontext['error'] = sprintf($txt['error_db_database'], $db_name);
				return false;
			}
		}

		return true;
	}

	return false;
}

// Let's start with basic forum type settings.
function ForumSettings()
{
	global $txt, $incontext, $db;

	$incontext['block'] = 'forum_settings';
	$incontext['page_title'] = $txt['install_settings'];

	// What host and port are we on?
	$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];

	// Now, to put what we've learned together... and add a path.
	$incontext['detected_url'] = 'http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 's' : '') . '://' . $host . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/'));

	// Check if the database sessions will even work.
	$incontext['test_dbsession'] = ini_get('session.auto_start') != 1;

	$incontext['continue'] = 1;

	// Submitting?
	if (isset($_POST['boardurl']))
	{
		if (substr($_POST['boardurl'], -10) == '/index.php')
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -10);
		elseif (substr($_POST['boardurl'], -1) == '/')
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -1);
		if (substr($_POST['boardurl'], 0, 7) != 'http://' && substr($_POST['boardurl'], 0, 7) != 'file://' && substr($_POST['boardurl'], 0, 8) != 'https://')
			$_POST['boardurl'] = 'http://' . $_POST['boardurl'];

		// Save these variables.
		$vars = array(
			'boardurl' => $_POST['boardurl'],
			'boarddir' => addslashes(dirname(__FILE__)),
			'sourcedir' => addslashes(dirname(__FILE__)) . '/Sources',
			'cachedir' => addslashes(dirname(__FILE__)) . '/cache',
			'pluginsdir' => addslashes(dirname(__FILE__)) . '/Plugins',
			'pluginsurl' => $_POST['boardurl'] . '/Plugins',
			'mbname' => strtr($_POST['mbname'], array('\"' => '"')),
		);

		// Must save!
		if (!updateSettingsFile($vars) && substr(__FILE__, 1, 2) == ':\\')
		{
			$incontext['error'] = $txt['error_windows_chmod'];
			return false;
		}

		// Make sure it works.
		require(dirname(__FILE__) . '/Settings.php');

		// Good, skip on.
		return true;
	}

	return false;
}

// Step one: Do the SQL thang.
function DatabasePopulation()
{
	global $txt, $db_connection, $db, $settings, $sourcedir, $db_prefix, $incontext, $db_name, $boardurl;

	$incontext['block'] = 'populate_database';
	$incontext['page_title'] = $txt['db_populate'];
	$incontext['continue'] = 1;

	// Already done?
	if (isset($_POST['pop_done']))
		return true;

	// Reload settings.
	require(dirname(__FILE__) . '/Settings.php');
	load_database();

	// Before running any of the queries, let's make sure another version isn't already installed.
	$result = wesql::query('
		SELECT variable, value
		FROM {db_prefix}settings',
		array(
			'db_error_skip' => true,
		)
	);
	$settings = array();
	if ($result !== false)
	{
		while ($row = wesql::fetch_assoc($result))
			$settings[$row['variable']] = $row['value'];
		wesql::free_result($result);

		// Do they match? If so, this is just a refresh so charge on!
		// !!! @todo: This won't work anyway -- the upgrader. Remove this code.
		if (!isset($settings['weVersion']) || $settings['weVersion'] != $GLOBALS['current_wedge_version'])
		{
			$incontext['error'] = $txt['error_versions_do_not_match'];
			return false;
		}
	}

	// We're doing UTF8, select it.
	wesql::query('
		SET NAMES utf8',
		array(
			'db_error_skip' => true,
		)
	);

	$replaces = array(
		'{$db_prefix}' => $db_prefix,
		'{$boarddir}' => wesql::escape_string(dirname(__FILE__)),
		'{$boardurl}' => $boardurl,
		'{$boarddomain}' => substr($boardurl, strpos($boardurl, '://') !== false ? strpos($boardurl, '://') + 3 : 0),
		'{$enableCompressedOutput}' => isset($_POST['compress']) ? '1' : '0',
		'{$enableCompressedData}' => isset($_POST['compress']) && isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false ? '1' : '0',
		'{$databaseSession_enable}' => isset($_POST['dbsession']) ? '1' : '0',
		'{$wedge_version}' => $GLOBALS['current_wedge_version'],
		'{$current_time}' => time(),
		'{$sched_task_offset}' => 82800 + mt_rand(0, 86399),
		'{$language}' => substr($_SESSION['installer_temp_lang'], 8, -4),
	);

	foreach ($txt as $key => $value)
		if (substr($key, 0, 8) == 'default_')
			$replaces['{$' . $key . '}'] = wesql::escape_string($value);

	// Add UTF-8 to the table definitions. We do this so that if we need to modify the syntax later, we can do it once instead of per table!
	$replaces[') ENGINE=MyISAM;'] = ') ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';

	// Read in the SQL. Turn this on and that off... internationalize... etc.
	$sql_lines = explode("\n", strtr(implode(' ', file(dirname(__FILE__) . '/install.sql')), $replaces));

	// Execute the SQL.
	$current_statement = '';
	$exists = array();
	$incontext['failures'] = array();
	$incontext['sql_results'] = array(
		'tables' => 0,
		'inserts' => 0,
		'table_dups' => 0,
		'insert_dups' => 0,
	);
	foreach ($sql_lines as $count => $line)
	{
		// No comments allowed!
		if (substr(trim($line), 0, 1) != '#')
			$current_statement .= "\n" . rtrim($line);

		// Is this the end of the query string?
		if (empty($current_statement) || (preg_match('~;[\s]*$~s', $line) == 0 && $count != count($sql_lines)))
			continue;

		// Does this table already exist? If so, don't insert more data into it!
		if (preg_match('~^\s*INSERT INTO ([^\s\n\r]+?)~', $current_statement, $match) != 0 && in_array($match[1], $exists))
		{
			$incontext['sql_results']['insert_dups']++;
			$current_statement = '';
			continue;
		}

		if (wesql::query($current_statement, array('security_override' => true, 'db_error_skip' => true), $db_connection) === false)
		{
			// Error 1050: Table already exists!
			// !! Needs to be made better!
			if (mysqli_errno($db_connection) === 1050 && preg_match('~^\s*CREATE TABLE ([^\s\n\r]+?)~', $current_statement, $match) == 1)
			{
				$exists[] = $match[1];
				$incontext['sql_results']['table_dups']++;
			}
			// Don't error on duplicate indexes
			elseif (!preg_match('~^\s*CREATE( UNIQUE)? INDEX ([^\n\r]+?)~', $current_statement, $match))
			{
				$incontext['failures'][$count] = wesql::error();
			}
		}
		else
		{
			if (preg_match('~^\s*CREATE TABLE ([^\s\n\r]+?)~', $current_statement, $match) == 1)
				$incontext['sql_results']['tables']++;
			else
			{
				preg_match_all('~\)[,;]~', $current_statement, $matches);
				if (!empty($matches[0]))
					$incontext['sql_results']['inserts'] += count($matches[0]);
				else
					$incontext['sql_results']['inserts']++;
			}
		}

		$current_statement = '';
	}

	// Sort out the context for the SQL.
	foreach ($incontext['sql_results'] as $key => $number)
	{
		if ($number == 0)
			unset($incontext['sql_results'][$key]);
		else
			$incontext['sql_results'][$key] = sprintf($txt['db_populate_' . $key], $number);
	}

	// Maybe we can auto-detect better cookie settings?
	preg_match('~^http[s]?://([^\.]+?)([^/]*?)(/.*)?$~', $boardurl, $matches);
	if (!empty($matches))
	{
		// Default = both off.
		$localCookies = false;
		$globalCookies = false;

		// Okay... let's see. Using a subdomain other than www? (Not a perfect check.)
		if ($matches[2] != '' && (strpos(substr($matches[2], 1), '.') === false || in_array($matches[1], array('forum', 'board', 'community', 'forums', 'support', 'chat', 'help', 'talk', 'boards', 'www'))))
			$globalCookies = true;
		// If there's a / in the middle of the path, or it starts with ~... we want local.
		if (isset($matches[3]) && strlen($matches[3]) > 3 && (substr($matches[3], 0, 2) == '/~' || strpos(substr($matches[3], 1), '/') !== false))
			$localCookies = true;

		$rows = array();
		if ($globalCookies)
			$rows[] = array('globalCookies', '1');
		if ($localCookies)
			$rows[] = array('localCookies', '1');

		if (!empty($rows))
		{
			wesql::insert('replace',
				$db_prefix . 'settings',
				array('variable' => 'string-255', 'value' => 'string-65534'),
				$rows,
				array('variable')
			);
		}
	}

	// Setting a timezone is required.
	if (!isset($settings['default_timezone']))
	{
		$timezone_id = @date_default_timezone_get();
		if (date_default_timezone_set($timezone_id))
			wesql::insert('',
				$db_prefix . 'settings',
				array(
					'variable' => 'string-255', 'value' => 'string-65534',
				),
				array(
					'default_timezone', $timezone_id,
				),
				array('variable')
			);
	}

	// Now we set up the default non-registrable names.
	$rows = array();
	if (!empty($txt['default_reserved_names']))
	{
		$items = explode('\n', $txt['default_reserved_names']);
		$extra = serialize(array('case_sens' => true, 'type' => 'containing'));
		foreach ($items as $item)
			$rows[] = array(
				'hardness' => 0,
				'ban_type' => 'member_name',
				'ban_content' => $item,
				'ban_reason' => '',
				'extra' => $extra,
				'added' => time(),
				'member_added' => 0,
			);

		wesql::insert('insert',
			'{db_prefix}bans',
			array(
				'hardness' => 'int', 'ban_type' => 'string', 'ban_content' => 'string',
				'ban_reason' => 'string', 'extra' => 'string', 'added' => 'int', 'member_added' => 'int',
			),
			$rows,
			array('id_ban')
		);
	}

	// Let's optimize those new tables.
	require_once($sourcedir . '/Class-DBPackages.php');
	$tables = wedbPackages::list_tables($db_name, $db_prefix . '%');
	foreach ($tables as $table)
	{
		wedbPackages::optimize_table($table) != -1 or $db_messed = true;

		if (!empty($db_messed))
		{
			$incontext['failures'][-1] = wesql::error();
			break;
		}
	}

	// Check for the ALTER privilege.
	if (wesql::query("ALTER TABLE {$db_prefix}boards ORDER BY id_board", array('security_override' => true, 'db_error_skip' => true)) === false)
	{
		$incontext['error'] = $txt['error_db_alter_priv'];
		return false;
	}

	// Default pretty URL filters
	$settings['pretty_filters'] = array(
		'topics' => 0,
		'boards' => 0,
		'profiles' => 0,
		'actions' => 0,
	);

	// Update the settings table
	wesql::insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-65534'),
		array('pretty_filters', serialize($settings['pretty_filters'])),
		array('variable')
	);

	require_once($sourcedir . '/Subs.php');
	require_once($sourcedir . '/Subs-PrettyUrls.php');
	pretty_update_filters();

	// We're done.
	if (!empty($exists))
	{
		$incontext['page_title'] = $txt['user_refresh_install'];
		$incontext['was_refresh'] = true;
	}

	return false;
}

// Ask for the administrator login information.
function AdminAccount()
{
	global $txt, $db_connection, $incontext, $db_passwd, $sourcedir;

	$incontext['block'] = 'admin_account';
	$incontext['page_title'] = $txt['user_settings'];
	$incontext['continue'] = 1;

	// Skipping?
	if (!empty($_POST['skip']))
		return true;

	// Need this to check whether we need the database password.
	require(dirname(__FILE__) . '/Settings.php');

	// We need this for some of the IP stuff.
	if (!defined('WEDGE'))
		define('WEDGE', 1);
	@include(dirname(__FILE__) . '/Sources/QueryString.php');
	if (!defined('INVALID_IP'))
		define('INVALID_IP', '00000000000000000000000000000000');

	load_database();

	if (!isset($_POST['username']))
		$_POST['username'] = '';
	if (!isset($_POST['email']))
		$_POST['email'] = '';

	$incontext['username'] = htmlspecialchars(stripslashes($_POST['username']));
	$incontext['email'] = htmlspecialchars(stripslashes($_POST['email']));

	// Only allow skipping if we think they already have an account setup.
	$request = wesql::query('
		SELECT id_member
		FROM {db_prefix}members
		WHERE id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0
		LIMIT 1',
		array(
			'db_error_skip' => true,
			'admin_group' => 1,
		)
	);
	if (wesql::num_rows($request) != 0)
		$incontext['skip'] = 1;
	wesql::free_result($request);

	// Trying to create an account?
	if (isset($_POST['password1']) && !empty($_POST['contbutt']))
	{
		// Wrong password?
		if ($_POST['password3'] != $db_passwd)
		{
			$incontext['error'] = $txt['error_db_connect'];
			return false;
		}
		// Not matching passwords?
		if ($_POST['password1'] != $_POST['password2'])
		{
			$incontext['error'] = $txt['error_user_settings_again_match'];
			return false;
		}
		// No password?
		if (strlen($_POST['password1']) < 4)
		{
			$incontext['error'] = $txt['error_user_settings_no_password'];
			return false;
		}
		if (!file_exists($sourcedir . '/Subs.php'))
		{
			$incontext['error'] = $txt['error_subs_missing'];
			return false;
		}

		// Update the main contact email?
		if (!empty($_POST['email']) && (empty($webmaster_email) || $webmaster_email == 'noreply@myserver.com'))
			updateSettingsFile(array('webmaster_email' => $_POST['email']));

		// Work out whether we're going to have dodgy characters and remove them.
		$invalid_characters = preg_match('~[<>&"\'=\\\]~', $_POST['username']) != 0;
		$_POST['username'] = preg_replace('~[<>&"\'=\\\]~', '', $_POST['username']);

		$result = wesql::query('
			SELECT id_member, password_salt
			FROM {db_prefix}members
			WHERE member_name = {string:username} OR email_address = {string:email}
			LIMIT 1',
			array(
				'username' => stripslashes($_POST['username']),
				'email' => stripslashes($_POST['email']),
				'db_error_skip' => true,
			)
		);
		if (wesql::num_rows($result) != 0)
		{
			list ($incontext['member_id'], $incontext['member_salt']) = wesql::fetch_row($result);
			wesql::free_result($result);

			$incontext['account_existed'] = $txt['error_user_settings_taken'];
		}
		elseif ($_POST['username'] == '' || strlen($_POST['username']) > 25)
		{
			// Try the previous step again.
			$incontext['error'] = $_POST['username'] == '' ? $txt['error_username_left_empty'] : $txt['error_username_too_long'];
			return false;
		}
		elseif ($invalid_characters || $_POST['username'] == '_' || $_POST['username'] == '|' || strpos($_POST['username'], '[code') !== false || strpos($_POST['username'], '[/code') !== false)
		{
			// Try the previous step again.
			$incontext['error'] = $txt['error_invalid_characters_username'];
			return false;
		}
		elseif (empty($_POST['email']) || !preg_match('~^[\w=+/-][\w=\'+/\.-]*@[\w-]+(\.[\w-]+)*(\.\w{2,6})$~', stripslashes($_POST['email'])) || strlen(stripslashes($_POST['email'])) > 255)
		{
			// One step back, this time fill out a proper email address.
			$incontext['error'] = sprintf($txt['error_valid_email_needed'], $_POST['username']);
			return false;
		}
		elseif ($_POST['username'] !== '')
		{
			$incontext['member_salt'] = substr(md5(mt_rand()), 0, 4);

			// Format the username properly.
			$_POST['username'] = preg_replace('~[\t\n\r\x0B\0\xA0]+~', ' ', $_POST['username']);
			$ip = isset($_SERVER['REMOTE_ADDR']) ? expand_ip($_SERVER['REMOTE_ADDR']) : expand_ip('');

			$request = wesql::insert('',
				'{db_prefix}members',
				array(
					'member_name' => 'string-25', 'real_name' => 'string-25', 'passwd' => 'string', 'email_address' => 'string',
					'id_group' => 'int', 'posts' => 'int', 'date_registered' => 'int', 'hide_email' => 'int',
					'password_salt' => 'string', 'lngfile' => 'string', 'personal_text' => 'string', 'avatar' => 'string',
					'member_ip' => 'string', 'member_ip2' => 'string', 'buddy_list' => 'string', 'pm_ignore_list' => 'string',
					'message_labels' => 'string', 'website_title' => 'string', 'website_url' => 'string', 'location' => 'string',
					'signature' => 'string', 'usertitle' => 'string', 'secret_question' => 'string',
					'additional_groups' => 'string', 'ignore_boards' => 'string', 'data' => 'string',
				),
				array(
					stripslashes($_POST['username']), stripslashes($_POST['username']), sha1(strtolower(stripslashes($_POST['username'])) . stripslashes($_POST['password1'])), stripslashes($_POST['email']),
					1, 0, time(), 0,
					$incontext['member_salt'], '', '', '',
					$ip, $ip, '', '',
					'', '', '', '',
					'', '', '',
					'', '', '',
				),
				array('id_member')
			);

			// Awww, crud!
			if ($request === false)
			{
				$incontext['error'] = $txt['error_user_settings_query'] . '<br>
				<div style="margin: 2ex">' . nl2br(htmlspecialchars(wesql::error($db_connection)), false) . '</div>';
				return false;
			}

			$incontext['member_id'] = wesql::insert_id();

			// If we have a first post that we've inserted ourselves, let's fix that to be our new administrator.
			wesql::query('
				UPDATE {db_prefix}messages
				SET id_member = {int:id_member},
					poster_name = {string:poster_name},
					poster_email = {string:poster_email},
					poster_ip = {int:poster_ip}
				WHERE id_msg = 1
					AND id_topic = 1
					AND id_member = 0
					AND poster_name = {string:wedge}',
				array(
					'id_member' => $incontext['member_id'],
					'poster_name' => stripslashes($_POST['username']),
					'poster_email' => stripslashes($_POST['email']),
					'poster_ip' => get_ip_identifier($ip),
					'wedge' => 'Wedge', // this is actually hard coded in the installer SQL
				)
			);
			// If we updated the messages, we should fix the topic too. And user post count.
			if (wesql::affected_rows() != 0)
			{
				wesql::query('
					UPDATE {db_prefix}topics
					SET id_member_started = {int:id_member},
						id_member_updated = {int:id_member}
					WHERE id_topic = 1
						AND id_member_started = 0
						AND num_replies = 0',
					array(
						'id_member' => $incontext['member_id'],
					)
				);

				wesql::query('
					UPDATE {db_prefix}members
					SET posts = posts + 1
					WHERE id_member = {int:id_member}',
					array(
						'id_member' => $incontext['member_id'],
					)
				);
			}
		}

		// If we're here we're good.
		return true;
	}

	return false;
}

// Final step, clean up and a complete message!
function DeleteInstall()
{
	global $txt, $incontext, $context, $current_wedge_version, $sourcedir, $settings;

	$incontext['page_title'] = $txt['congratulations'];
	$incontext['block'] = 'delete_install';
	$incontext['continue'] = 0;

	require(dirname(__FILE__) . '/Settings.php');
	load_database();

	chdir(dirname(__FILE__));

	require_once($sourcedir . '/Errors.php');
	require_once($sourcedir . '/Subs.php');
	require_once($sourcedir . '/Load.php');
	require_once($sourcedir . '/Security.php');
	require_once($sourcedir . '/Subs-Auth.php');
	require_once($sourcedir . '/Class-String.php');
	westr::getInstance();

	// Bring a warning over.
	if (!empty($incontext['account_existed']))
		$incontext['warning'] = $incontext['account_existed'];

	wesql::query('
		SET NAMES utf8',
		array(
			'db_error_skip' => true,
		)
	);

	// As track stats is by default enabled let's add some activity.
	wesql::insert('ignore',
		'{db_prefix}log_activity',
		array('date' => 'date', 'topics' => 'int', 'posts' => 'int', 'registers' => 'int'),
		array(strftime('%Y-%m-%d', time()), 1, 1, (!empty($incontext['member_id']) ? 1 : 0)),
		array('date')
	);

	// Automatically log them in ;)
	if (isset($incontext['member_id'], $incontext['member_salt']))
		setLoginCookie(3153600 * 60, $incontext['member_id'], sha1(sha1(strtolower($_POST['username']) . $_POST['password1']) . $incontext['member_salt']));

	$result = wesql::query('
		SELECT value
		FROM {db_prefix}settings
		WHERE variable = {string:db_sessions}',
		array(
			'db_sessions' => 'databaseSession_enable',
			'db_error_skip' => true,
		)
	);
	if (wesql::num_rows($result) != 0)
		list ($db_sessions) = wesql::fetch_row($result);
	wesql::free_result($result);

	if (empty($db_sessions))
		$_SESSION['admin_time'] = time();
	else
	{
		$_SERVER['HTTP_USER_AGENT'] = substr($_SERVER['HTTP_USER_AGENT'], 0, 211);

		wesql::insert('replace',
			'{db_prefix}sessions',
			array(
				'session_id' => 'string', 'last_update' => 'int', 'data' => 'string',
			),
			array(
				session_id(), time(), 'USER_AGENT|s:' . strlen($_SERVER['HTTP_USER_AGENT']) . ':"' . $_SERVER['HTTP_USER_AGENT'] . '";admin_time|i:' . time() . ';',
			),
			array('session_id')
		);
	}

	// We're going to want our lovely $settings now.
	$request = wesql::query('
		SELECT variable, value
		FROM {db_prefix}settings',
		array(
			'db_error_skip' => true,
		)
	);
	// Only proceed if we can load the data.
	if ($request)
	{
		while ($row = wesql::fetch_row($request))
			$settings[$row[0]] = $row[1];
		wesql::free_result($request);
	}

	updateStats('member');
	updateStats('message');
	updateStats('topic');
	updateStats('postgroups');

	$request = wesql::query('
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE id_msg = 1
			AND modified_time = 0
		LIMIT 1',
		array(
			'db_error_skip' => true,
		)
	);
	if (wesql::num_rows($request) > 0)
		updateStats('subject', 1, htmlspecialchars($txt['default_topic_subject']));
	wesql::free_result($request);

	// Now is the perfect time to fetch the Wedge files.
	require_once($sourcedir . '/ScheduledTasks.php');
	require_once($sourcedir . '/Class-System.php');
	require_once($sourcedir . '/QueryString.php');
	we::getInstance(false);
	// Sanity check that they loaded earlier!
	if (isset($settings['recycle_board']))
	{
		define('WEDGE_VERSION', $current_wedge_version); // The variable is usually defined in index.php so let's just use our variable to do it for us.
		scheduled_fetchRemoteFiles(); // Now go get those files!

		// We've just installed!
		we::$user['ip'] = $_SERVER['REMOTE_ADDR'];
		we::$id = isset($incontext['member_id']) ? $incontext['member_id'] : 0;
		$_SERVER['BAN_CHECK_IP'] = $_SERVER['REMOTE_ADDR'];
		logAction('install', array('version' => WEDGE_VERSION), 'admin');
	}

	// Some final context for the template.
	$incontext['dir_still_writable'] = is_writable(dirname(__FILE__)) && substr(__FILE__, 1, 2) != ':\\';
	$incontext['probably_delete_install'] = isset($_SESSION['installer_temp_ftp']) || is_writable(dirname(__FILE__)) || is_writable(__FILE__);

	return false;
}

// http://www.faqs.org/rfcs/rfc959.html
class ftp_connection
{
	var $connection = 'no_connection', $error = false, $last_message, $pasv = array();

	// Create a new FTP connection...
	function ftp_connection($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@wedge.org')
	{
		if ($ftp_server !== null)
			$this->connect($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
	}

	function connect($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@wedge.org')
	{
		if (substr($ftp_server, 0, 6) == 'ftp://')
			$ftp_server = substr($ftp_server, 6);
		elseif (substr($ftp_server, 0, 7) == 'ftps://')
			$ftp_server = 'ssl://' . substr($ftp_server, 7);
		if (substr($ftp_server, 0, 7) == 'http://')
			$ftp_server = substr($ftp_server, 7);
		$ftp_server = strtr($ftp_server, array('/' => '', ':' => '', '@' => ''));

		// Connect to the FTP server.
		$this->connection = @fsockopen($ftp_server, $ftp_port, $err, $err, 5);
		if (!$this->connection)
		{
			$this->error = 'bad_server';
			return;
		}

		// Get the welcome message...
		if (!$this->check_response(220))
		{
			$this->error = 'bad_response';
			return;
		}

		// Send the username, it should ask for a password.
		fwrite($this->connection, 'USER ' . $ftp_user . "\r\n");
		if (!$this->check_response(331))
		{
			$this->error = 'bad_username';
			return;
		}

		// Now send the password... and hope it goes okay.
		fwrite($this->connection, 'PASS ' . $ftp_pass . "\r\n");
		if (!$this->check_response(230))
		{
			$this->error = 'bad_password';
			return;
		}
	}

	function chdir($ftp_path)
	{
		if (!is_resource($this->connection))
			return false;

		// No slash on the end, please...
		if (substr($ftp_path, -1) == '/')
			$ftp_path = substr($ftp_path, 0, -1);

		fwrite($this->connection, 'CWD ' . $ftp_path . "\r\n");
		if (!$this->check_response(250))
		{
			$this->error = 'bad_path';
			return false;
		}

		return true;
	}

	function chmod($ftp_file, $chmod)
	{
		if (!is_resource($this->connection))
			return false;

		// Convert the chmod value from octal (0777) to text ("777")
		fwrite($this->connection, 'SITE CHMOD ' . decoct($chmod) . ' ' . $ftp_file . "\r\n");
		if (!$this->check_response(200))
		{
			$this->error = 'bad_file';
			return false;
		}

		return true;
	}

	function unlink($ftp_file)
	{
		// We are actually connected, right?
		if (!is_resource($this->connection))
			return false;

		// Delete file X.
		fwrite($this->connection, 'DELE ' . $ftp_file . "\r\n");
		if (!$this->check_response(250))
		{
			fwrite($this->connection, 'RMD ' . $ftp_file . "\r\n");

			// Still no love?
			if (!$this->check_response(250))
			{
				$this->error = 'bad_file';
				return false;
			}
		}

		return true;
	}

	function check_response($desired)
	{
		// Wait for a response that isn't continued with -, but don't wait too long.
		$time = time();
		do
			$this->last_message = fgets($this->connection, 1024);
		while (substr($this->last_message, 3, 1) != ' ' && time() - $time < 5);

		// Was the desired response returned?
		return is_array($desired) ? in_array(substr($this->last_message, 0, 3), $desired) : substr($this->last_message, 0, 3) == $desired;
	}

	function passive()
	{
		// We can't create a passive data connection without a primary one first being there.
		if (!is_resource($this->connection))
			return false;

		// Request a passive connection - this means, we'll talk to you, you don't talk to us.
		@fwrite($this->connection, "PASV\r\n");
		$time = time();
		do
			$response = fgets($this->connection, 1024);
		while (substr($response, 3, 1) != ' ' && time() - $time < 5);

		// If it's not 227, we weren't given an IP and port, which means it failed.
		if (substr($response, 0, 4) != '227 ')
		{
			$this->error = 'bad_response';
			return false;
		}

		// Snatch the IP and port information, or die horribly trying...
		if (preg_match('~\((\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+))\)~', $response, $match) == 0)
		{
			$this->error = 'bad_response';
			return false;
		}

		// This is pretty simple - store it for later use ;)
		$this->pasv = array('ip' => $match[1] . '.' . $match[2] . '.' . $match[3] . '.' . $match[4], 'port' => $match[5] * 256 + $match[6]);

		return true;
	}

	function create_file($ftp_file)
	{
		// First, we have to be connected... very important.
		if (!is_resource($this->connection))
			return false;

		// I'd like one passive mode, please!
		if (!$this->passive())
			return false;

		// Seems logical enough, so far...
		fwrite($this->connection, 'STOR ' . $ftp_file . "\r\n");

		// Okay, now we connect to the data port. If it doesn't work out, it's probably "file already exists", etc.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(150))
		{
			$this->error = 'bad_file';
			@fclose($fp);
			return false;
		}

		// This may look strange, but we're just closing it to indicate a zero-byte upload.
		fclose($fp);
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return true;
	}

	function list_dir($ftp_path = '', $search = false)
	{
		// Are we even connected...?
		if (!is_resource($this->connection))
			return false;

		// Passive... non-agressive...
		if (!$this->passive())
			return false;

		// Get the listing!
		fwrite($this->connection, 'LIST -1' . ($search ? 'R' : '') . ($ftp_path == '' ? '' : ' ' . $ftp_path) . "\r\n");

		// Connect, assuming we've got a connection.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(array(150, 125)))
		{
			$this->error = 'bad_response';
			@fclose($fp);
			return false;
		}

		// Read in the file listing.
		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// Everything go okay?
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return $data;
	}

	function locate($file, $listing = null)
	{
		if ($listing === null)
			$listing = $this->list_dir('', true);
		$listing = explode("\n", $listing);

		@fwrite($this->connection, "PWD\r\n");
		$time = time();
		do
			$response = fgets($this->connection, 1024);
		while (substr($response, 3, 1) != ' ' && time() - $time < 5);

		// Check for 257!
		if (preg_match('~^257 "(.+?)" ~', $response, $match) != 0)
			$current_dir = strtr($match[1], array('""' => '"'));
		else
			$current_dir = '';

		for ($i = 0, $n = count($listing); $i < $n; $i++)
		{
			if (trim($listing[$i]) == '' && isset($listing[$i + 1]))
			{
				$current_dir = substr(trim($listing[++$i]), 0, -1);
				$i++;
			}

			// Okay, this file's name is:
			$listing[$i] = $current_dir . '/' . trim(strlen($listing[$i]) > 30 ? strrchr($listing[$i], ' ') : $listing[$i]);

			if (substr($file, 0, 1) == '*' && substr($listing[$i], -(strlen($file) - 1)) == substr($file, 1))
				return $listing[$i];
			if (substr($file, -1) == '*' && substr($listing[$i], 0, strlen($file) - 1) == substr($file, 0, -1))
				return $listing[$i];
			if (basename($listing[$i]) == $file || $listing[$i] == $file)
				return $listing[$i];
		}

		return false;
	}

	function create_dir($ftp_dir)
	{
		// We must be connected to the server to do something.
		if (!is_resource($this->connection))
			return false;

		// Make this new beautiful directory!
		fwrite($this->connection, 'MKD ' . $ftp_dir . "\r\n");
		if (!$this->check_response(257))
		{
			$this->error = 'bad_file';
			return false;
		}

		return true;
	}

	function detect_path($filesystem_path, $lookup_file = null)
	{
		$username = '';

		if (isset($_SERVER['DOCUMENT_ROOT']))
		{
			if (preg_match('~^/home[2]?/([^/]+?)/public_html~', $_SERVER['DOCUMENT_ROOT'], $match))
			{
				$username = $match[1];

				$path = strtr($_SERVER['DOCUMENT_ROOT'], array('/home/' . $match[1] . '/' => '', '/home2/' . $match[1] . '/' => ''));

				if (substr($path, -1) == '/')
					$path = substr($path, 0, -1);

				if (strlen(dirname($_SERVER['PHP_SELF'])) > 1)
					$path .= dirname($_SERVER['PHP_SELF']);
			}
			elseif (substr($filesystem_path, 0, 9) == '/var/www/')
				$path = substr($filesystem_path, 8);
			else
				$path = strtr(strtr($filesystem_path, array('\\' => '/')), array($_SERVER['DOCUMENT_ROOT'] => ''));
		}
		else
			$path = '';

		if (is_resource($this->connection) && $this->list_dir($path) == '')
		{
			$data = $this->list_dir('', true);

			if ($lookup_file === null)
				$lookup_file = $_SERVER['PHP_SELF'];

			$found_path = dirname($this->locate('*' . basename(dirname($lookup_file)) . '/' . basename($lookup_file), $data));
			if ($found_path == false)
				$found_path = dirname($this->locate(basename($lookup_file)));
			if ($found_path != false)
				$path = $found_path;
		}
		elseif (is_resource($this->connection))
			$found_path = true;

		return array($username, $path, isset($found_path));
	}

	function close()
	{
		// Goodbye!
		fwrite($this->connection, "QUIT\r\n");
		fclose($this->connection);

		return true;
	}
}

function updateSettingsFile($vars)
{
	// Modify Settings.php.
	$settingsArray = file(dirname(__FILE__) . '/Settings.php');

	// !!! Do we just want to read the file in clean, and split it this way always?
	if (count($settingsArray) == 1)
		$settingsArray = preg_split('~[\r\n]~', $settingsArray[0]);

	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		if (empty($settingsArray[$i]))
			continue;

		// Remove the redirect (normally 5 lines of code)...
		if (strpos($settingsArray[$i], 'file_exists') !== false && trim($settingsArray[$i]) == 'if (file_exists(dirname(__FILE__) . \'/install.php\'))')
		{
			$settingsArray[$i++] = '';
			$tab = substr($settingsArray[$i], 0, strpos($settingsArray[$i], '{')); // It should normally be empty.
			while ($i < $n - 1 && rtrim($settingsArray[$i]) != $tab . '}')
				$settingsArray[$i++] = '';
			$settingsArray[$i] = '';
			continue;
		}

		if (trim($settingsArray[$i]) == '?' . '>')
		{
			$settingsArray[$i] = '';
			continue;
		}

		// Don't trim or bother with it if it's not a variable.
		if ($settingsArray[$i][0] != '$')
			continue;

		$settingsArray[$i] = rtrim($settingsArray[$i]) . "\n";

		foreach ($vars as $var => $val)
		{
			if (strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) != 0)
				continue;

			$comment = strstr($settingsArray[$i], '#');
			$settingsArray[$i] = '$' . $var . ' = \'' . $val . '\';' . ($comment != '' ? "\t\t" . $comment : "\n");
			unset($vars[$var]);
		}
	}

	// Uh oh... the file wasn't empty... was it?
	if (!empty($vars))
	{
		$settingsArray[$i++] = '';
		foreach ($vars as $var => $val)
			$settingsArray[$i++] = '$' . $var . ' = \'' . $val . '\';' . "\n";
	}

	// Blank out the file - done to fix an oddity with some servers.
	$fp = @fopen(dirname(__FILE__) . '/Settings.php', 'w');
	if (!$fp)
		return false;
	fclose($fp);

	$fp = fopen(dirname(__FILE__) . '/Settings.php', 'r+');

	// Gotta have one of these ;)
	if (trim($settingsArray[0]) != '<?php')
		fwrite($fp, "<?php\n");

	$lines = count($settingsArray);
	$last_line = '';
	for ($i = 0; $i < $lines - 1; $i++)
	{
		$line = trim($settingsArray[$i]);
		// Skip multiple blank lines
		if ($line !== '' || $last_line !== '')
			fwrite($fp, strtr($settingsArray[$i], "\r", ''));
		$last_line = $line;
	}

	fwrite($fp, $settingsArray[$i] . '?' . '>');
	fclose($fp);

	return true;
}

// Create an .htaccess file to prevent mod_security. Wedge has filtering built-in.
function fixModSecurity()
{
	$htaccess_addition = '
<IfModule mod_security.c>
	# Turn off mod_security filtering. Wedge is a big boy, it doesn\'t need its hands held.
	SecFilterEngine Off

	# The below probably isn\'t needed, but better safe than sorry.
	SecFilterScanPOST Off
</IfModule>';

	if (!function_exists('apache_get_modules') || !in_array('mod_security', apache_get_modules()))
		return true;
	elseif (file_exists(dirname(__FILE__) . '/.htaccess') && is_writable(dirname(__FILE__) . '/.htaccess'))
	{
		$current_htaccess = implode('', file(dirname(__FILE__) . '/.htaccess'));

		// Only change something if mod_security hasn't been addressed yet.
		if (strpos($current_htaccess, '<IfModule mod_security.c>') === false)
		{
			if ($ht_handle = fopen(dirname(__FILE__) . '/.htaccess', 'a'))
			{
				fwrite($ht_handle, $htaccess_addition);
				fclose($ht_handle);
				return true;
			}
			else
				return false;
		}
		else
			return true;
	}
	elseif (file_exists(dirname(__FILE__) . '/.htaccess'))
		return strpos(implode('', file(dirname(__FILE__) . '/.htaccess')), '<IfModule mod_security.c>') !== false;
	elseif (is_writable(dirname(__FILE__)))
	{
		if ($ht_handle = fopen(dirname(__FILE__) . '/.htaccess', 'w'))
		{
			fwrite($ht_handle, $htaccess_addition);
			fclose($ht_handle);
			return true;
		}
		else
			return false;
	}
	else
		return false;
}

function template_install_above()
{
	global $incontext, $txt, $installurl, $boardurl, $cachedir, $cssdir, $jsdir;
	global $boarddir, $sourcedir, $wedgesite, $theme, $context, $settings;

	// Load Wedge's default paths and pray that it works...
	if (!defined('WEDGE'))
		define('WEDGE', 1);
	$boarddir = dirname(__FILE__);
	$cachedir = $boarddir . '/cache';
	$cssdir = $boarddir . '/css';
	$jsdir = $boarddir . '/js';
	$sourcedir = $boarddir . '/Sources';
	require_once($sourcedir . '/Load.php');
	// !!! Dunno if we need to load all of these. Better safe than sorry.
	loadSource(array(
		'QueryString', 'Subs',
		'Errors', 'Security', 'Subs-Auth',
		'Class-String', 'Class-System',
	));
	westr::getInstance();
	we::getInstance(false);

	// Fill in the server URL for the current user. This is user-specific, as they may be using a different URL than the script's default URL (Pretty URL, secure access...)
	$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_X_FORWARDED_SERVER'] : $_SERVER['HTTP_HOST'];
	$boardurl = 'http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off' ? 's' : '') . '://' . $host;
	$boardurl .= substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/'));

	$theme['theme_dir'] = $boarddir . '/Themes/default';
	$theme['theme_url'] = $boardurl . '/Themes/default';
	$theme['default_theme_dir'] = $boarddir . '/Themes/default';
	$theme['default_theme_url'] = $boardurl . '/Themes/default';
	$theme['images_url'] = $boardurl . '/Themes/default/images';
	$context['css_folders'] = array('skins');
	$context['css_suffixes'] = array(we::$browser['agent']);
	$settings['minify'] = 'packer';

	if (!file_exists($cachedir . '/cache.lock'))
		@fclose(@fopen($cachedir . '/cache.lock', 'w'));

	echo '<!DOCTYPE html>
<html', !empty($txt['lang_rtl']) ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="utf-8">
		<meta name="robots" content="noindex">
		<title>', $txt['wedge_installer'], '</title>
		<link rel="stylesheet" href="',
		add_css_file(
			array('index', 'sections', 'install'),
			false, false,
			array('index', 'sections')
		), '">
		<script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>
		<script src="',
		add_js_file(
			array('scripts/script.js', 'scripts/sbox.js'),
			false, true,
			array('scripts/sbox.js' => 1)
		), '"></script>
	</head>
	<body><div id="wedge">
	<div id="header">
		<img src="http://wedge.org/wedge.png" id="install_logo" />
		<div class="frame" style="margin-left: 140px">
			<div id="upper_section" class="flow_hidden"><div class="frame">
				<h1 class="forumtitle"><a>', $txt['wedge_installer'], '</a></h1>
			</div></div>
		</div>
	</div>
	<div id="content"><div class="frame">
		<div id="main">
			<div id="main_steps">
				<h2>', $txt['upgrade_progress'], '</h2>
				<ul>';

	foreach ($incontext['steps'] as $num => $step)
		echo '
					<li class="', $num < $incontext['current_step'] ? 'stepdone' : ($num == $incontext['current_step'] ? 'stepcurrent' : 'stepwaiting'), '">', $txt['upgrade_step'], ' ', $step[0], ': ', $step[1], '</li>';

	echo '
				</ul>
			</div>
			<div id="install_progress">
				<div id="overall_text" style="padding-top: 8pt; z-index: 2; color: black; margin-left: -4em; position: absolute; text-align: center; font-weight: bold">', $incontext['overall_percent'], '%</div>
				<div id="overall_progress" style="width: ', $incontext['overall_percent'], '%">&nbsp;</div>
				<div id="overall_caption">', $txt['upgrade_overall_progress'], '</div>
			</div>
			<div id="main_screen" class="clear">
				<h2>', $incontext['page_title'], '</h2>
				<div class="panel">
					<div style="max-height: 560px; overflow: auto">';
}

function template_install_below()
{
	global $incontext, $txt;

	if (!empty($incontext['continue']) || !empty($incontext['skip']))
	{
		echo '
		<div class="right" style="margin: 1ex">';

		if (!empty($incontext['continue']))
			echo '
			<input type="submit" id="contbutt" name="contbutt" value="', $txt['upgrade_continue'], '" onclick="return submitThisOnce(this);" class="submit">';
		if (!empty($incontext['skip']))
			echo '
			<input type="submit" id="skip" name="skip" value="', $txt['upgrade_skip'], '" onclick="return submitThisOnce(this);" class="submit">';
		echo '
		</div>';
	}

	// Show the closing form tag and other data only if not in the last step
	if (count($incontext['steps']) - 1 !== (int) $incontext['current_step'])
		echo '
	</form>';

	echo '
	</div></div></div></div></div></div>
	<div id="footer"><div class="frame" style="height: 30px; line-height: 30px">
		<a href="http://wedge.org/" title="Free Forum Software" target="_blank" class="new_win">Wedge &copy; 2010-2013, Wedgeward</a>
	</div></div>
	</div>
	<script><!-- // --><![CDATA[
		$("select").sb();
	// ]]></script>
	</body>
</html>';
}

// Welcome, winners, to the wonderful world of Wedge!
function template_welcome_message()
{
	global $incontext, $installurl, $txt;

	// Have we got a language drop down - if so do it on the first step only.
	if (!empty($incontext['detected_languages']) && count($incontext['detected_languages']) > 1)
	{
		echo '
	<div>
		<form action="', $installurl, '">
			<label>', $txt['installer_language'], ': <select name="lang_file" onchange="location = \'', $installurl, '?lang_file=\' + $(this).val();">';

		foreach ($incontext['detected_languages'] as $lang => $name)
			echo '
				<option', isset($_SESSION['installer_temp_lang']) && $_SESSION['installer_temp_lang'] == $lang ? ' selected' : '', ' value="', $lang, '">', $name, '</option>';

		echo '
			</select></label>
			<noscript><input type="submit" value="', $txt['installer_language_set'], '" class="submit"></noscript>
		</form>
	</div>';
	}

	echo '
	<script src="http://wedge.org/files/current-version.js?version=' . urlencode($GLOBALS['current_wedge_version']) . '"></script>
	<form action="', $incontext['form_url'], '" method="post">
		<p>', sprintf($txt['install_welcome_desc'], 'Wedge ' . $GLOBALS['current_wedge_version']), '</p>
		<div id="version_warning">
			<div style="float: left; width: 2ex; font-size: 2em; color: red">!!</div>
			<strong style="text-decoration: underline">', $txt['error_warning_notice'], '</strong><br>
			<div style="padding-left: 6ex">
				', sprintf($txt['error_script_outdated'], '<em id="wedgeVersion" style="white-space: nowrap">??</em>', '<em id="yourVersion" style="white-space: nowrap">' . $GLOBALS['current_wedge_version'] . '</em>'), '
			</div>
		</div>';

	// Show the warnings, or not.
	if (template_warning_divs())
		echo '
		<h3>', $txt['install_all_lovely'], '</h3>';

	echo '
		<div style="height: 100px"></div>';

	// Say we want the continue button!
	if (empty($incontext['error']))
		$incontext['continue'] = 1;

	// For the latest version stuff.
	echo '
		<script><!-- // --><![CDATA[
			$(window).load(function () {
				if (!("weVersion" in window))
					return;

				$("#wedgeVersion").html(window.weVersion);
				$("#version_warning").toggle($("#yourVersion").text() < window.weVersion);
			});
		// ]]></script>';
}

// A shortcut for any warning stuff.
function template_warning_divs()
{
	global $txt, $incontext;

	// Errors are very serious..
	if (!empty($incontext['error']))
		echo '
		<div style="margin: 2ex; padding: 2ex; border: 2px dashed #cc3344; color: black; background-color: #ffe4e9">
			<div style="float: left; width: 2ex; font-size: 2em; color: red">!!</div>
			<strong style="text-decoration: underline">', $txt['upgrade_critical_error'], '</strong><br>
			<div style="padding-left: 6ex">
				', $incontext['error'], '
			</div>
		</div>';
	// A warning message?
	elseif (!empty($incontext['warning']))
		echo '
		<div style="margin: 2ex; padding: 2ex; border: 2px dashed #cc3344; color: black; background-color: #ffe4e9">
			<div style="float: left; width: 2ex; font-size: 2em; color: red">!!</div>
			<strong style="text-decoration: underline">', $txt['upgrade_warning'], '</strong><br>
			<div style="padding-left: 6ex">
				', $incontext['warning'], '
			</div>
		</div>';

	return empty($incontext['error']) && empty($incontext['warning']);
}

function template_chmod_files()
{
	global $txt, $incontext;

	echo '
		<p>', $txt['ftp_setup_why_info'], '</p>
		<ul style="margin: 2.5ex; font-family: monospace">
			<li>', implode('</li>
			<li>', $incontext['failed_files']), '</li>
		</ul>';

	// This is serious!
	if (!template_warning_divs())
		return;

	echo '
		<hr>
		<p>', $txt['ftp_setup_info'], '</p>';

	if (!empty($incontext['ftp_errors']))
		echo '
		<div class="error_message">
			<div style="color: red">
				', $txt['error_ftp_no_connect'], '<br>
				<br>
				<code>', implode('<br>', $incontext['ftp_errors']), '</code>
			</div>
		</div>
		<br>';

	echo '
		<form action="', $incontext['form_url'], '" method="post">
			<table class="cs0 cp0" style="width: 520px; margin: 1em auto">
				<tr>
					<td style="width: 26%; vertical-align: top" class="textbox"><label for="ftp_server">', $txt['ftp_server'], ':</label></td>
					<td>
						<div style="float: ', empty($txt['lang_rtl']) ? 'right' : 'left', '; margin-', empty($txt['lang_rtl']) ? 'right' : 'left', ': 1px"><label class="textbox"><strong>', $txt['ftp_port'], ':&nbsp;</strong> <input size="3" name="ftp_port" value="', $incontext['ftp']['port'], '"></label></div>
						<input size="30" name="ftp_server" id="ftp_server" value="', $incontext['ftp']['server'], '" style="width: 70%">
						<div class="install_details">', $txt['ftp_server_info'], '</div>
					</td>
				</tr><tr>
					<td style="vertical-align: top" class="textbox"><label for="ftp_username">', $txt['ftp_username'], ':</label></td>
					<td>
						<input size="50" name="ftp_username" id="ftp_username" value="', $incontext['ftp']['username'], '" style="width: 99%">
						<div class="install_details">', $txt['ftp_username_info'], '</div>
					</td>
				</tr><tr>
					<td style="vertical-align: top" class="textbox"><label for="ftp_password">', $txt['ftp_password'], ':</label></td>
					<td>
						<input type="password" size="50" name="ftp_password" id="ftp_password" style="width: 99%">
						<div style="font-size: smaller; margin-bottom: 3ex">', $txt['ftp_password_info'], '</div>
					</td>
				</tr><tr>
					<td style="vertical-align: top" class="textbox"><label for="ftp_path">', $txt['ftp_path'], ':</label></td>
					<td style="padding-bottom: 1ex">
						<input size="50" name="ftp_path" id="ftp_path" value="', $incontext['ftp']['path'], '" style="width: 99%">
						<div class="install_details">', $incontext['ftp']['path_msg'], '</div>
					</td>
				</tr>
			</table>
			<div style="margin: 1ex; margin-top: 1ex; text-align: ', empty($txt['lang_rtl']) ? 'right' : 'left', '"><input type="submit" value="', $txt['ftp_connect'], '" onclick="return submitThisOnce(this);" class="submit"></div>
		</form>
		<a href="', $incontext['form_url'], '">', $txt['error_message_click'], '</a> ', $txt['ftp_setup_again'];
}

// Get the database settings prepared.
function template_database_settings()
{
	global $incontext, $installurl, $txt;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<p>', $txt['db_settings_info'], '</p>';

	template_warning_divs();

	echo '
		<table class="w100 cp0 cs0" style="margin: 1em 0">
			<tr id="db_server_contain">
				<td style="width: 20%; vertical-align: top" class="textbox"><label for="db_server_input">', $txt['db_settings_server'], ':</label></td>
				<td>
					<input name="db_server" id="db_server_input" value="', $incontext['db']['server'], '" size="30"><br>
					<div class="install_details">', $txt['db_settings_server_info'], '</div>
				</td>
			</tr><tr id="db_user_contain">
				<td style="vertical-align: top" class="textbox"><label for="db_user_input">', $txt['db_settings_username'], ':</label></td>
				<td>
					<input name="db_user" id="db_user_input" value="', $incontext['db']['user'], '" size="30"><br>
					<div class="install_details">', $txt['db_settings_username_info'], '</div>
				</td>
			</tr><tr id="db_passwd_contain">
				<td style="vertical-align: top" class="textbox"><label for="db_passwd_input">', $txt['db_settings_password'], ':</label></td>
				<td>
					<input type="password" name="db_passwd" id="db_passwd_input" value="', $incontext['db']['pass'], '" size="30"><br>
					<div class="install_details">', $txt['db_settings_password_info'], '</div>
				</td>
			</tr><tr id="db_name_contain">
				<td style="vertical-align: top" class="textbox"><label for="db_name_input">', $txt['db_settings_database'], ':</label></td>
				<td>
					<input name="db_name" id="db_name_input" value="', empty($incontext['db']['name']) ? 'wedge' : $incontext['db']['name'], '" size="30"><br>
					<div class="install_details">', $txt['db_settings_database_info'], '
					<span id="db_name_info_warning">', $txt['db_settings_database_info_note'], '</span></div>
				</td>
			</tr><tr>
				<td style="vertical-align: top" class="textbox"><label for="db_prefix_input">', $txt['db_settings_prefix'], ':</label></td>
				<td>
					<input name="db_prefix" id="db_prefix_input" value="', $incontext['db']['prefix'], '" size="30"><br>
					<div class="install_details">', $txt['db_settings_prefix_info'], '</div>
				</td>
			</tr>
		</table>';

}

// Stick in their forum settings.
function template_forum_settings()
{
	global $incontext, $installurl, $txt, $boardurl;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<h3>', $txt['install_settings_info'], '</h3>';

	template_warning_divs();

	$default_name = trim(ucwords(preg_replace(array('~^.*://|\.[a-z]{2,4}(?:/|$)|[^a-z]~i', '~ +~'), array(' ', ' '), $boardurl)));

	echo '
		<table class="w100 cp0 cs0" style="margin: 1em 0">
			<tr>
				<td style="width: 20%; vertical-align: top" class="textbox"><label for="mbname_input">', $txt['install_settings_name'], ':</label></td>
				<td>
					<input name="mbname" id="mbname_input" value="', $default_name, '" size="65">
					<div class="install_details">', $txt['install_settings_name_info'], '</div>
				</td>
			</tr><tr>
				<td style="vertical-align: top" class="textbox"><label for="boardurl_input">', $txt['install_settings_url'], ':</label></td>
				<td>
					<input name="boardurl" id="boardurl_input" value="', $incontext['detected_url'], '" size="65"><br>
					<div class="install_details">', $txt['install_settings_url_info'], '</div>
				</td>
			</tr><tr>
				<td style="vertical-align: top" class="textbox">', $txt['install_settings_compress'], ':</td>
				<td>
					<label><input type="checkbox" name="compress" checked> ', $txt['install_settings_compress_title'], '</label><br>
					<div class="install_details">', $txt['install_settings_compress_info'], '</div>
				</td>
			</tr><tr>
				<td style="vertical-align: top" class="textbox">', $txt['install_settings_dbsession'], ':</td>
				<td>
					<label><input type="checkbox" name="dbsession" checked> ', $txt['install_settings_dbsession_title'], '</label><br>
					<div class="install_details">', $incontext['test_dbsession'] ? $txt['install_settings_dbsession_info1'] : $txt['install_settings_dbsession_info2'], '</div>
				</td>
			</tr>
		</table>';
}

// Show results of the database population.
function template_populate_database()
{
	global $incontext, $installurl, $txt;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<p>', !empty($incontext['was_refresh']) ? $txt['user_refresh_install_desc'] : $txt['db_populate_info'], '</p>';

	if (!empty($incontext['sql_results']))
	{
		echo '
		<ul>
			<li>', implode('</li><li>', $incontext['sql_results']), '</li>
		</ul>';
	}

	if (!empty($incontext['failures']))
	{
		echo '
		<div style="color: red">', $txt['error_db_queries'], '</div>
		<ul>';

		foreach ($incontext['failures'] as $line => $fail)
			echo '
			<li><strong>', $txt['error_db_queries_line'], $line + 1, ':</strong> ', nl2br(htmlspecialchars($fail)), '</li>';

		echo '
		</ul>';
	}

	echo '
		<p>', $txt['db_populate_info2'], '</p>';

	template_warning_divs();

	echo '
		<input type="hidden" name="pop_done" value="1">';
}

// Create the admin account.
function template_admin_account()
{
	global $incontext, $installurl, $txt;

	echo '
	<form action="', $incontext['form_url'], '" method="post">
		<p>', $txt['user_settings_info'], '</p>';

	template_warning_divs();

	echo '
		<table class="w100 cs0 cp0" style="margin: 2em 0">
			<tr>
				<td style="width: 18%; vertical-align: top" class="textbox"><label for="username">', $txt['user_settings_username'], ':</label></td>
				<td>
					<input name="username" id="username" value="', $incontext['username'], '" size="40">
					<div class="install_details">', $txt['user_settings_username_info'], '</div>
				</td>
			</tr><tr>
				<td style="vertical-align: top" class="textbox"><label for="password1">', $txt['user_settings_password'], ':</label></td>
				<td>
					<input type="password" name="password1" id="password1" size="40">
					<div class="install_details">', $txt['user_settings_password_info'], '</div>
				</td>
			</tr><tr>
				<td style="vertical-align: top" class="textbox"><label for="password2">', $txt['user_settings_again'], ':</label></td>
				<td>
					<input type="password" name="password2" id="password2" size="40">
					<div class="install_details">', $txt['user_settings_again_info'], '</div>
				</td>
			</tr><tr>
				<td style="vertical-align: top" class="textbox"><label for="email">', $txt['user_settings_email'], ':</label></td>
				<td>
					<input type="email" name="email" id="email" value="', $incontext['email'], '" size="40">
					<div class="install_details">', $txt['user_settings_email_info'], '</div>
				</td>
			</tr>
		</table>

		<h2>', $txt['user_settings_database'], '</h2>
		<p>', $txt['user_settings_database_info'], '</p>

		<div style="margin-bottom: 2ex; padding-', empty($txt['lang_rtl']) ? 'left' : 'right', ': 50px">
			<input type="password" name="password3" size="30">
		</div>';
}

// Tell them it's done, and to delete.
function template_delete_install()
{
	global $incontext, $installurl, $txt, $boardurl;

	echo '
		<p>', $txt['congratulations_help'], '</p>';

	template_warning_divs();

	// Install directory still writable?
	if ($incontext['dir_still_writable'])
		echo '
		<em>', $txt['still_writable'], '</em><br>
		<br>';

	// Don't show the box if it's like 99% sure it won't work :P.
	if ($incontext['probably_delete_install'])
		echo '
		<div style="margin: 1ex; font-weight: bold">
			<label><input type="checkbox" onclick="doTheDelete();"> ', $txt['delete_installer'], !isset($_SESSION['installer_temp_ftp']) ? ' ' . $txt['delete_installer_maybe'] : '', '</label>
		</div>
		<script><!-- // --><![CDATA[
			function doTheDelete()
			{
				$.get("', $installurl, '?delete=1&ts_" + $.now());
				this.disabled = true;
			}
		// ]]></script>
		<br>';

	echo '
		', sprintf($txt['go_to_your_forum'], $boardurl . '/index.php'), '<br>
		<br>
		', $txt['good_luck'];
}
