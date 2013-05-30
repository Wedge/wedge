<?php
/**
 * Wedge
 *
 * Bootstrap for Wedge, where all forum access will begin and all go through the same setup and security.
 * This also lists the master set of actions. Just add your custom entries to the $action_list array:
 *
 *	'action-in-url' => array('Source-File.php', 'FunctionToCall'),
 *
 * Then, you can access the FunctionToCall() function from Source-File.php with the URL index.php?action=action-in-url.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

define('WEDGE_VERSION', '0.1');

// Knock knock! We're entering through the front door.
define('WEDGE', 1);

// Get everything started up...
if (function_exists('set_magic_quotes_runtime'))
	@set_magic_quotes_runtime(0);
error_reporting(E_ALL | E_STRICT);
$time_start = microtime(true);

// Makes sure that headers can be sent!
ob_start();

// Do some cleaning, just in case.
unset($GLOBALS['cachedir']);

// Load the settings...
require_once(dirname(__FILE__) . '/Settings.php');

// And important includes.
require_once($sourcedir . '/Class-System.php');
require_once($sourcedir . '/QueryString.php');
require_once($sourcedir . '/Subs.php');
require_once($sourcedir . '/Errors.php');
require_once($sourcedir . '/Load.php');
require_once($sourcedir . '/Security.php');

// If $maintenance is set specifically to 2, then we're upgrading or something.
if (!empty($maintenance) && $maintenance == 2)
	show_db_error();

// Initiate the database connection and define some database functions to use.
loadDatabase();

// Unserialize the array of pretty board URLs
$context = array(
	'pretty' => array('db_count' => 0),
	'app_error_count' => 0,
);

// Load the settings from the settings table, and perform operations like optimizing.
reloadSettings();

// Here's the monstrous $action array - $action => array($file, [[$function], $plugin_id]).
// If the function name is the same as the loadSource file name, e.g. Admin.php, to run Admin(), you can declare it as a string.
// Only add $plugin_id if it's for a plugin, otherwise just have (one or) two items in the list.
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
	'quickmod2' =>		array('Display', 'QuickInTopicModeration'),
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
	'unread' =>			'Unread',
	'unreadreplies' =>	'UnreadReplies',
	'verification' =>	'VerificationCode',
	'viewquery' =>		'ViewQuery',
	'viewremote' =>		'ViewRemote',
	'who' =>			'Who',
);

// If an action should not influence the who's online list, please add it here. (Hookable as global)
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

// Check if compressed output is enabled, supported, and not already being done.
if (!empty($settings['enableCompressedOutput']) && !headers_sent())
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

// Register an error handler.
set_error_handler('error_handler');

// Start the session. (assuming it hasn't already been.)
loadSession();

// What function shall we execute? (done like this for memory's sake.)
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

// The main controlling function.
function wedge_main()
{
	global $context, $settings, $theme, $board, $topic, $board_info, $maintenance, $action_list;

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
		$correcturl = preg_replace('/([\?&]PHPSESSID=[^&]*)/', '', we::$user['url']);
		$correcturl = str_replace(array('index.php&', 'index.php??'), 'index.php?', $correcturl);
		$correcturl = str_replace(array('/&?', '/??', '/&'), '/?', $correcturl);
		$correcturl = preg_replace('/&$|\?$/', '', $correcturl);

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
		if (we::$id != $board_info['cur_topic_starter'] || we::$is_guest)
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
