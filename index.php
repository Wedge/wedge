<?php
/**
 * Welcome to Wedge.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (defined('WEDGE'))
	return;

const WEDGE_VERSION = '0.1';
const WEDGE = 1; // We are go.

// Get everything started up...
if (function_exists('set_magic_quotes_runtime') && version_compare('5.4.0', PHP_VERSION) > 0)
	@set_magic_quotes_runtime(0);

error_reporting(E_ALL | E_STRICT);
$time_start = microtime(true);

// Makes sure that headers can be sent!
ob_start();

// Do some cleaning, just in case.
unset($GLOBALS['cachedir']);

// Is it our first run..?
$here = dirname(__FILE__);
if (!file_exists($here . '/Settings.php'))
{
	require_once($here . '/core/app/OriginalFiles.php');
	create_settings_file($here);
	create_generic_folders($here);
}
// Load the settings...
require_once($here . '/Settings.php');

foreach (array('cache' => 'gz', 'css' => 'gz/css', 'js' => 'gs/jz') as $var => $path)
{
	$dir = $var . 'dir';
	if ((empty($$dir) || ($$dir !== $boarddir . '/' . $path && !file_exists($$dir))) && file_exists($boarddir . '/' . $path))
		$$dir = $boarddir . '/' . $path;
	if (!file_exists($$dir))
		exit('Missing cache folder: $' . $dir . ' (' . $$dir . ')');
}

// And important files.
loadSource(array(
	'Class-System',
	'QueryString',
	'Subs',
	'Errors',
	'Load',
	'Security',
));

// Are we installing, or doing something that needs the forum to be down?
if (!empty($maintenance) && $maintenance > 1)
{
	if ($maintenance == 2) // Installing
		require_once(__DIR__ . '/install/install.php');
	else // Downtime
		show_db_error();
	return;
}

// Initiate the database connection.
loadDatabase();

// Load the settings from the settings table, and perform operations like optimizing.
loadSettings();

/*
	I am the Gatekeeper. Are you the Keymaster?

	Here's the monstrous $action array - $action => array($file, [[$function], $plugin_id]).
	If the function name is the same as the loadSource file name, e.g. Admin.php, to run Admin(), you can declare it as a string.
	Only add $plugin_id if it's for a plugin, otherwise just have (one or) two items in the list.

	Add custom actions to to the $action_list array this way:

	'my-action' => array('MyFile.php', 'MyFunction'),

	Then, the URL index.php?action=my-action will load call MyFile.php and call MyFunction().
*/
$action_list = array(
	'activate' =>		'Activate',
	'admin' =>			'Admin',
	'ajax' =>			'Ajax',
	'announce' =>		'Announce',
	'boards' =>			'Boards',
	'buddy' =>			'Buddy',
	'collapse' =>		'Collapse',
	'coppa' =>			'CoppaForm',
	'credits' =>		'Credits',
	'deletemsg' =>		array('RemoveTopic', 'DeleteMessage'),
	'display' =>		'Display',
	'dlattach' =>		'Dlattach',
	'emailuser' =>		'Mailer',
	'feed' =>			'Feed',
	'groups' =>			'Groups',
	'help' =>			'Help',
	'jseditor' =>		'JSEditor',
	'jsmodify' =>		'JSModify',
	'jsoption' =>		'JSOption',
	'like' =>			'Like',
	'lock' =>			'Lock',
	'login' =>			'Login',
	'login2' =>			'Login2',
	'logout' =>			'Logout',
	'markasread' =>		array('Subs-Boards', 'MarkRead'),
	'media' =>			array('media/Aeva-Gallery', 'aeva_initGallery'),
	'mergeposts' =>		array('Merge', 'MergePosts'),
	'mergetopics' =>	array('Merge', 'MergeTopics'),
	'mlist' =>			'Memberlist',
	'moderate' =>		'ModerationCenter',
	'movetopic' =>		'MoveTopic',
	'movetopic2' =>		array('MoveTopic', 'MoveTopic2'),
	'notify' =>			'Notify',
	'notifyboard' =>	array('Notify', 'BoardNotify'),
	'notification' =>	array('Notifications', 'weNotif::action'),
	'pin' =>			'Pin',
	'pm' =>				'PersonalMessage',
	'poll' =>			'Poll',
	'post' =>			'Post',
	'post2' =>			'Post2',
	'printpage' =>		'PrintPage',
	'profile' =>		array('Profile', 'ModifyProfile'),
	'quotefast' =>		'QuoteFast',
	'quickmod' =>		array('QuickMod', 'QuickModeration'),
	'quickmod2' =>		array('QuickMod', 'QuickInTopicModeration'),
	'recent' =>			'Recent',
	'register' =>		'Register',
	'register2' =>		array('Register', 'Register2'),
	'reminder' =>		array('Reminder', 'RemindMe'),
	'removetopic2' =>	array('RemoveTopic', 'RemoveTopic2'),
	'report' =>			'Report',
	'restoretopic' =>	array('RemoveTopic', 'RestoreTopic'),
	'search' =>			'Search',
	'search2' =>		'Search2',
	'sendtopic' =>		'Mailer',
	'skin' =>			array('Themes', 'PickTheme'),
	'splittopics' =>	array('Split', 'SplitTopics'),
	'stats' =>			'Stats',
	'suggest' =>		'Suggest',
	'theme' =>			'Themes',
	'thoughts' =>		'Thoughts',
	'trackip' =>		array('Profile-View', 'trackIP'),
	'uncache' =>		array('Subs-Cache', 'uncache'),
	'unread' =>			'Unread',
	'unreadreplies' =>	'UnreadReplies',
	'verification' =>	'VerificationCode',
	'viewquery' =>		'ViewQuery',
	'viewremote' =>		'ViewRemote',
	'who' =>			'Who',
);

// If an action should not influence the who's online list, please add it here. (Hookable as global.)
$action_no_log = array(
	'ajax', 'dlattach', 'feed', 'jseditor', 'jsoption', 'like', 'notification', 'verification', 'viewquery', 'viewremote',
);

if (empty($settings['pm_enabled']))
	unset($action_list['pm']);

if (!empty($context['extra_actions']))
	$action_list = array_merge($action_list, $context['extra_actions']);
if (!empty($context['nolog_actions']))
	$action_no_log = array_merge($action_no_log, $context['nolog_actions']);

// Clean the request variables, add slashes, etc.
cleanRequest();

// Seed the random generator.
if (empty($settings['rand_seed']) || mt_rand(1, 250) == 42)
	we_seed_generator();

// Before we get carried away, are we doing a scheduled task? If so save CPU cycles by jumping out!
if (isset($_GET['scheduled']))
{
	loadSource('ScheduledTasks');
	AutoTask();
}
elseif (isset($_GET['imperative']))
{
	loadSource('Subs-Scheduled');
	ImperativeTask();
}

if (!headers_sent())
{
	// Check if compressed output is enabled, supported, and not already being done.
	if (!empty($settings['enableCompressedOutput']))
	{
		// If zlib is being used, turn off output compression.
		if (ini_get('zlib.output_compression') >= 1 || ini_get('output_handler') == 'ob_gzhandler')
			$settings['enableCompressedOutput'] = '0';
		else
		{
			ob_end_clean();
			ob_start('ob_gzhandler');
		}
	}

	// Basic protection against XSS.
	header('X-Frame-Options: SAMEORIGIN');
	header('X-XSS-Protection: 1; mode=block');
	header('X-Content-Type-Options: nosniff');
}

// Register an error handler.
set_error_handler('error_handler');

// Start the session, if it hasn't already been.
loadSession();

// What function shall we execute? (Done this way for memory's sake.)
$function = wedge_main();

// Do some logging, unless this is an attachment, avatar, toggle of editor buttons, theme option, XML feed etc.
if (empty($_REQUEST['action']) || !in_array($_REQUEST['action'], $action_no_log))
{
	// Log this user as online.
	writeLog();

	// Track forum statistics and hits...?
	if (!empty($settings['hitStats']))
		trackStats(array('hits' => '+'));
}

// After all this time... After everything we saw, after everything we lost... I have only one thing to say to you... Bye!
call_user_func($function);

wetem::add('sidebar', 'sidebar_quick_access');

// Just quickly sneak the feed stuff in...
if (!empty($settings['xmlnews_enable']) && !empty($settings['xmlnews_sidebar']) && (!empty($settings['allow_guestAccess']) || we::$is_member) && function_exists('template_sidebar_feed'))
	wetem::add('sidebar', 'sidebar_feed');

obExit(null, null, true);

// Since we're not leaving obExit the special route, we need to make sure we update the error count.
if (!isset($settings['app_error_count']))
	$settings['app_error_count'] = 0;
if (!empty($context['app_error_count']))
	updateSettings(array('app_error_count' => $settings['app_error_count'] + $context['app_error_count']));

// Loads a named file from the app folder. Uses cache if possible.
// $source_name can be a string or an array of strings.
function loadSource($source_name)
{
	global $sourcedir, $cachedir, $db_show_debug;
	static $done = array();

	foreach ((array) $source_name as $file)
	{
		if (isset($done[$file]))
			continue;
		$done[$file] = true;
		if (strpos($file, 'getid3') !== false)
			$cache = $sourcedir . '/' . $file . '.php';
		else
		{
			$cache = $cachedir . '/app/' . str_replace(array('/', '..'), array('_', 'UP'), $file) . '.php';
			if (!file_exists($cache) || filemtime($cache) < filemtime($sourcedir . '/' . $file . '.php'))
			{
				copy($sourcedir . '/' . $file . '.php', $cache);
				// !! Disabling this temporarily (until I add a setting for it), to get proper line numbers when debugging.
				if (false && empty($db_show_debug))
					minify_php($cache);
			}
		}
		require_once($cache);
	}
}

// The main controlling function.
function wedge_main()
{
	global $context, $settings, $board, $topic, $board_info, $maintenance, $action_list;

	$action = isset($_GET['action']) ? $_GET['action'] : '';

	// Special case: session keep-alive, do nothing.
	if ($action === 'keepalive')
		exit;

	// Allow modifying $action_list easily. (It's a global by now.)
	call_hook('action_list');

	$context['action'] = $action = isset($action_list[$action]) ? $action : (isset($settings['default_action'], $action_list[$settings['default_action']]) ? $settings['default_action'] : '');
	$context['subaction'] = isset($_GET['sa']) ? $_GET['sa'] : null;

	// Load the user's cookie (or set as guest) and load their settings.
	we::getInstance();

	// Get rid of ?PHPSESSID for robots.
	if (we::$user['possibly_robot'] && strpos(we::$user['url'], 'PHPSESSID=') !== false)
	{
		$correcturl = preg_replace('~([?&]PHPSESSID=[^&]*)~', '', we::$user['url']);
		$correcturl = str_replace(array('index.php&', 'index.php??'), 'index.php?', $correcturl);
		$correcturl = str_replace(array('/&?', '/??', '/&'), '/?', $correcturl);
		$correcturl = preg_replace('~&$|\?$~', '', $correcturl);

		if ($correcturl != we::$user['url'])
		{
			header('HTTP/1.1 301 Moved Permanently');
			header('Location: ' . $correcturl);
			exit;
		}
	}

	// Check the request for anything hinky.
	checkUserBehavior();

	// Allow plugins to check for the request as well, and manipulate $action.
	call_hook('behavior', array(&$action));

	// Last chance to get the board ID if we have a default one. Use the 'behavior' hook to force it.
	if (empty($action) && empty($board) && empty($topic))
	{
		if (isset($_GET['category']) && is_numeric($_GET['category']))
			$action = 'boards';
		elseif (isset($settings['default_index']) && strpos($settings['default_index'], 'board') === 0)
			$board = (int) substr($settings['default_index'], 5);
	}

	// Load the current board's information.
	loadBoard();

	// Load the current user's permissions.
	loadPermissions();

	// Load the current theme. Note that ?theme=1 will also work, may be used for guest theming.
	// Attachments don't require the entire theme to be loaded.
	if ($action !== 'dlattach' || empty($settings['allow_guestAccess']) || we::$is_member)
		loadTheme();

	// Check if the user should be disallowed access.
	is_not_banned();

	// If we are in a topic and don't have permission to approve it then duck out now.
	if (!empty($topic) && $action !== 'feed' && empty($board_info['cur_topic_approved']) && !allowedTo('approve_posts'))
		if (MID != $board_info['cur_topic_starter'] || we::$is_guest)
			fatal_lang_error('not_a_topic', false);

	// Is the forum in maintenance mode? (doesn't apply to administrators.)
	if (!empty($maintenance) && !allowedTo('admin_forum'))
	{
		// You can only login.... otherwise, you're getting the "maintenance mode" display.
		if ($action === 'login2' || $action === 'logout')
		{
			$action = ucfirst($action);
			loadSource($action);
			return $action;
		}
		// Welcome. You are unauthorized. Your death will now be implemented.
		else
		{
			loadSource('Subs-Auth');
			return 'InMaintenance';
		}
	}
	// If guest access is off, a guest can only do one of the very few following actions.
	elseif (empty($settings['allow_guestAccess']) && we::$is_guest && (empty($action) || !in_array($action, array('coppa', 'login', 'login2', 'register', 'register2', 'reminder', 'activate', 'mailq', 'verification'))))
	{
		loadSource('Subs-Auth');
		return 'KickGuest';
	}
	// The user might need to reagree to the agreement; post2 is here so we don't break drafts for posts
	// because that would really suck otherwise and PMs are allowed in case someone wants to discuss it.
	elseif (!empty($settings['agreement_force']) && (we::$user['activated'] == 6 && !we::$is_admin) && (empty($action) || !in_array($action, array('login', 'login2', 'logout', 'reminder', 'activate', 'mailq', 'post2', 'pm'))))
	{
		loadSource('Subs-Auth');
		return 'Reagree';
	}
	// Or not...
	elseif (empty($action))
	{
		// Action and board are both empty... Go home!
		if (empty($board) && empty($topic))
			return index_action();

		// Topic is empty, and action is empty.... MessageIndex!
		if (empty($topic))
		{
			loadSource('MessageIndex');
			return 'MessageIndex';
		}
		// Board is not empty... topic is not empty... action is empty.. Display!
		else
		{
			loadSource('Display');
			return 'Display';
		}
	}

	// Get the function and file to include - if it's not there, do the board index.
	if (!isset($action_list[$action]))
		return index_action('fallback_action');

	// Otherwise, it was set - so let's go to that action.
	$target = (array) $action_list[$action];
	if (isset($target[2]))
		loadPluginSource($target[2], $target[0]);
	else
		loadSource($target[0]);

	// Remember, if the function is the same as the filename, you may declare it just once.
	return isset($target[1]) ? $target[1] : $target[0];
}

function index_action($hook_action = 'default_action')
{
	global $settings, $sourcedir;

	// Some plugins may want to specify default "front page" behavior through the 'default_action' hook, and/or a
	// last-minute fallback ('fallback_action'). If they do, they shall return the name of the function they want to call.
	foreach (call_hook($hook_action) as $func)
		if (!empty($func) && is_callable($func))
			return $func;

	// Otherwise, if the admin specified a custom homepage, fall back to it.
	if (isset($settings['default_index']) && file_exists($sourcedir . '/' . $settings['default_index'] . '.php'))
	{
		loadSource($settings['default_index']);
		return $settings['default_index'];
	}

	loadSource('Boards');
	return 'Boards';
}

// Cache a minified PHP file.
function minify_php($file)
{
	global $save_strings;

	$php = preg_replace('~\s+~', ' ', clean_me_up($file));
	$php = preg_replace('~(?<=[^a-zA-Z0-9_.])\s+|\s+(?=[^$a-zA-Z0-9_.])~', '', $php);
	$php = preg_replace('~(?<=[^0-9.])\s+\.|\.\s+(?=[^0-9.])~', '.', $php); // 2 . 1 != 2.1
	$php = str_replace(',)', ')', $php);
	$pos = 0;

	foreach ($save_strings as $str)
		if (($pos = strpos($php, "\x0f", $pos)) !== false)
			$php = substr_replace($php, $str, $pos, 1);

	file_put_contents($file, $php);
}

// Remove comments and protect strings.
function clean_me_up($file, $remove_comments = false)
{
	global $save_strings, $is_output_buffer;

	// Set this to true if calling loadSource within an output buffer handler.
	if (empty($is_output_buffer))
	{
		$php = php_strip_whitespace($file);
		$search_for = array("'", '"');
	}
	else
	{
		$php = file_get_contents($file);
		$search_for = array('/*', '//', "'", '"');
	}

	$save_strings = array();
	$pos = 0;

	while (true)
	{
		$pos = find_next($php, $pos, $search_for);
		if ($pos === false)
			return $php;

		$look_for = $php[$pos];
		if ($look_for === '/')
		{
			if ($php[$pos + 1] === '/') // Remove //
				$look_for = array("\r", "\n", "\r\n");
			else // Remove /* ... */
				$look_for = '*/';
		}
		else
		{
			$next = find_next($php, $pos + 1, $look_for);
			if ($next === false) // Shouldn't be happening.
				return $php;
			if ($php[$next] === "\r" && $php[$next + 1] === "\n")
				$next++;
			$save_strings[] = substr($php, $pos, $next + 1 - $pos);
			$php = substr_replace($php, "\x0f", $pos, $next + 1 - $pos);
			continue;
		}

		$end = find_next($php, $pos + 1, $look_for);
		if ($end === false)
			return $php;
		if (!is_array($look_for))
			$end += strlen($look_for);
		$temp = substr($php, $pos, $end - $pos);

		$breaks = substr_count($temp, "\n") + substr_count($temp, "\r") - substr_count($temp, "\r\n");
		$php = substr_replace($php, str_pad(str_repeat("\n", $breaks), $end - $pos), $pos, $end - $pos);
		$pos = $end + 1;
	}
}

function find_next(&$php, $pos, $search_for)
{
	if (is_array($search_for))
	{
		$positions = array();
		foreach ((array) $search_for as $item)
		{
			$position = strpos($php, $item, $pos);
			if ($position !== false)
				$positions[] = $position;
		}
		if (empty($positions))
			return false;
		$next = min($positions);
	}
	else
	{
		$next = strpos($php, $search_for, $pos);
		if ($next === false)
			return false;
	}

	$check_before = $next;
	$escaped = false;
	while (--$check_before >= 0 && $php[$check_before] == '\\')
		$escaped = !$escaped;
	if ($escaped)
		return find_next($php, ++$next, $search_for);
	return $next;
}
