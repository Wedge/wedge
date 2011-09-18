<?php
/**
 * Wedge
 *
 * Bootstrap for Wedge, where all forum access will begin and all go through the same setup and security.
 * This also lists the master set of actions. Just add your custom entries to the $action_list array:
 *
 *    'action-in-url' => array('Source-File.php', 'FunctionToCall'),
 *
 * Then, you can access the FunctionToCall() function from Source-File.php with the URL index.php?action=action-in-url.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
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
error_reporting(defined('E_STRICT') ? E_ALL | E_STRICT : E_ALL);
$time_start = microtime(true);

// Makes sure that headers can be sent!
ob_start();

// Do some cleaning, just in case.
unset($GLOBALS['cachedir']);

// Load the settings...
require_once(dirname(__FILE__) . '/Settings.php');

// Make absolutely sure the cache directory is defined.
if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
	$cachedir = $boarddir . '/cache';

// And important includes.
require_once($sourcedir . '/QueryString.php');
require_once($sourcedir . '/Subs.php');
require_once($sourcedir . '/Errors.php');
require_once($sourcedir . '/Load.php');
require_once($sourcedir . '/Security.php');

// If $maintenance is set specifically to 2, then we're upgrading or something.
if (!empty($maintenance) && $maintenance == 2)
	show_db_error();

// Initate the database connection and define some database functions to use.
loadDatabase();

// Unserialize the array of pretty board URLs
$context = array(
	'pretty' => array('db_count' => 0),
	'app_error_count' => 0,
);

// Load the settings from the settings table, and perform operations like optimizing.
reloadSettings();

// Here's the monstrous $action array - $action => array($file, $function, $addon_id).
// Only add $addon_id if it's for an add-on, otherwise just have two items in the list.
$action_list = array(
	'activate' => array('Activate.php', 'Activate'),
	'admin' => array('Admin.php', 'Admin'),
	'ajax' => array('Ajax.php', 'Ajax'),
	'announce' => array('Announce.php', 'Announce'),
	'attachapprove' => array('ManageAttachments.php', 'ApproveAttach'),
	'buddy' => array('Buddy.php', 'Buddy'),
	'calendar' => array('Calendar.php', 'CalendarMain'),
	'collapse' => array('Collapse.php', 'Collapse'),
	'coppa' => array('CoppaForm.php', 'CoppaForm'),
	'credits' => array('Credits.php', 'Credits'),
	'deletemsg' => array('RemoveTopic.php', 'DeleteMessage'),
	'display' => array('Display.php', 'Display'),
	'dlattach' => array('Dlattach.php', 'Dlattach'),
	'emailuser' => array('SendTopic.php', 'EmailUser'),
	'feed' => array('Feed.php', 'Feed'),
	'findmember' => array('FindMember.php', 'FindMember'),
	'groups' => array('Groups.php', 'Groups'),
	'help' => array('Help.php', 'Help'),
	'im' => array('PersonalMessage.php', 'MessageMain'),
	'jseditor' => array('JSEditor.php', 'JSEditor'),
	'jsmodify' => array('JSModify.php', 'JSModify'),
	'jsoption' => array('JSOption.php', 'JSOption'),
	'lock' => array('Lock.php', 'Lock'),
	'login' => array('Login.php', 'Login'),
	'login2' => array('Login2.php', 'Login2'),
	'logout' => array('Logout.php', 'Logout'),
	'markasread' => array('Subs-Boards.php', 'MarkRead'),
	'media' => array('media/Aeva-Gallery.php', 'aeva_initGallery'),
	'mergeposts' => array('SplitTopics.php', 'MergePosts'),
	'mergetopics' => array('SplitTopics.php', 'MergeTopics'),
	'mlist' => array('Memberlist.php', 'Memberlist'),
	'moderate' => array('ModerationCenter.php', 'ModerationMain'),
	'movetopic' => array('MoveTopic.php', 'MoveTopic'),
	'movetopic2' => array('MoveTopic.php', 'MoveTopic2'),
	'notify' => array('Notify.php', 'Notify'),
	'notifyboard' => array('Notify.php', 'BoardNotify'),
	'openidreturn' => array('Subs-OpenID.php', 'we_openID_return'),
	'pm' => array('PersonalMessage.php', 'MessageMain'),
	'poll' => array('Poll.php', 'Poll'),
	'post' => array('Post.php', 'Post'),
	'post2' => array('Post2.php', 'Post2'),
	'printpage' => array('PrintPage.php', 'PrintPage'),
	'profile' => array('Profile.php', 'ModifyProfile'),
	'quotefast' => array('QuoteFast.php', 'QuoteFast'),
	'quickmod' => array('QuickMod.php', 'QuickModeration'),
	'quickmod2' => array('Display.php', 'QuickInTopicModeration'),
	'recent' => array('Recent.php', 'Recent'),
	'register' => array('Register.php', 'Register'),
	'register2' => array('Register.php', 'Register2'),
	'reminder' => array('Reminder.php', 'RemindMe'),
	'removetopic2' => array('RemoveTopic.php', 'RemoveTopic2'),
	'report' => array('Report.php', 'Report'),
	'restoretopic' => array('RemoveTopic.php', 'RestoreTopic'),
	'search' => array('Search.php', 'Search'),
	'search2' => array('Search2.php', 'Search2'),
	'sendtopic' => array('SendTopic.php', 'EmailUser'),
	'skin' => array('Themes.php', 'PickTheme'),
	'spellcheck' => array('Spellcheck.php', 'Spellcheck'),
	'splittopics' => array('SplitTopics.php', 'SplitTopics'),
	'stats' => array('Stats.php', 'Stats'),
	'sticky' => array('Sticky.php', 'Sticky'),
	'suggest' => array('Suggest.php', 'Suggest'),
	'theme' => array('Themes.php', 'ThemesMain'),
	'trackip' => array('Profile-View.php', 'trackIP'),
	'unread' => array('Unread.php', 'Unread'),
	'unreadreplies' => array('UnreadReplies.php', 'UnreadReplies'),
	'verificationcode' => array('VerificationCode.php', 'VerificationCode'),
	'viewquery' => array('ViewQuery.php', 'ViewQuery'),
	'viewremote' => array('ViewRemote.php', 'ViewRemote'),
	'who' => array('Who.php', 'Who'),
);

// Clean the request variables, add slashes, etc.
cleanRequest();

// Seed the random generator.
if (empty($modSettings['rand_seed']) || mt_rand(1, 250) == 42)
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
if (!empty($modSettings['enableCompressedOutput']) && !headers_sent())
{
	// If zlib is being used, turn off output compression.
	if (@ini_get('zlib.output_compression') == '1' || @ini_get('output_handler') == 'ob_gzhandler')
		$modSettings['enableCompressedOutput'] = '0';
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

// Determine if this is using WAP2.
if (isset($_REQUEST['wap2']))
	unset($_SESSION['nowap']);
elseif (isset($_REQUEST['nowap']))
	$_SESSION['nowap'] = true;
elseif (!isset($_SESSION['nowap']) && isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/vnd.wap.xhtml+xml') !== false)
	$_REQUEST['wap2'] = 1;

if (!defined('WIRELESS'))
	define('WIRELESS', isset($_REQUEST['wap2']));

if (WIRELESS)
{
	// !!! Could simply hardcode wap2 into the files...
	define('WIRELESS_PROTOCOL', 'wap2');

	// Some cellphones can't handle output compression...
	$modSettings['enableCompressedOutput'] = '0';
	// !!! Do we want these hard coded?
	$modSettings['defaultMaxMessages'] = 5;
	$modSettings['defaultMaxTopics'] = 9;
}

// Restore post data if we are revalidating OpenID.
if (isset($_GET['openid_restore_post']) && !empty($_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]['post']) && empty($_POST))
{
	$_POST = $_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]['post'];
	unset($_SESSION['openid']['saved_data'][$_GET['openid_restore_post']]);
}

// What function shall we execute? (done like this for memory's sake.)
$function = wedge_main();

// Do some logging, unless this is an attachment, avatar, toggle of editor buttons, theme option, XML feed etc.
if (empty($_REQUEST['action']) || !defined('WEDGE_NO_LOG'))
{
	// Log this user as online.
	writeLog();

	// Track forum statistics and hits...?
	if (!empty($modSettings['hitStats']))
		trackStats(array('hits' => '+'));
}

// After all this time... after everything we saw, after everything we lost... I have only one thing to say to you... bye!
$function();

// Just quickly sneak the feed stuff in...
if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']) && function_exists('template_sidebar_feed'))
	loadBlock('sidebar_feed', array(':side', 'sidebar'), 'add');

obExit(null, null, true);

// Since we're not leaving obExit the special route, we need to make sure we update the error count.
if (!isset($modSettings['app_error_count']))
	$modSettings['app_error_count'] = 0;
if (!empty($context['app_error_count']))
	updateSettings(
		array(
			'app_error_count' => $modSettings['app_error_count'] + $context['app_error_count'],
		)
	);

// The main controlling function.
function wedge_main()
{
	global $modSettings, $settings, $user_info, $board, $topic, $board_info, $maintenance, $sourcedir, $action_list;

	$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

	// Special case: session keep-alive, output a transparent pixel.
	if ($action === 'keepalive')
		blankGif();

	// Load the user's cookie (or set as guest) and load their settings.
	loadUserSettings();

	// Get rid of ?PHPSESSID for robots.
	if ($user_info['possibly_robot'] && strpos($user_info['url'], 'PHPSESSID=') !== false)
	{
		$correcturl = preg_replace('/([\?&]PHPSESSID=[^&]*)/', '', $user_info['url']);
		$correcturl = str_replace(array('index.php&', 'index.php??'), 'index.php?', $correcturl);
		$correcturl = str_replace(array('/&?', '/??', '/&'), '/?', $correcturl);
		$correcturl = preg_replace('/&$|\?$/', '', $correcturl);

		if ($correcturl != $user_info['url'])
		{
			header('HTTP/1.1 301 Moved Permanently');
			header('Location: ' . $correcturl);
			exit();
		}
	}

	// Check the request for anything hinky.
	checkUserBehavior();
	call_hook('behavior');

	// Load the current board's information.
	loadBoard();

	// Load the current user's permissions.
	loadPermissions();

	// Attachments don't require the entire theme to be loaded.
	if ($action === 'dlattach' && (!empty($modSettings['allow_guestAccess']) && $user_info['is_guest']))
		detectBrowser();
	// Load the current theme.  (note that ?theme=1 will also work, may be used for guest theming.)
	else
		loadTheme();

	// Check if the user should be disallowed access.
	is_not_banned();

	// If we are in a topic and don't have permission to approve it then duck out now.
	if (!empty($topic) && $action !== 'feed' && empty($board_info['cur_topic_approved']) && !allowedTo('approve_posts') && ($user_info['id'] != $board_info['cur_topic_starter'] || $user_info['is_guest']))
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
		// Don't even try it, sonny.
		else
		{
			loadSource('Subs-Auth');
			return 'InMaintenance';
		}
	}
	// If guest access is off, a guest can only do one of the very few following actions.
	elseif (empty($modSettings['allow_guestAccess']) && $user_info['is_guest'] && (empty($action) || !in_array($action, array('coppa', 'login', 'login2', 'register', 'register2', 'reminder', 'activate', 'mailq', 'verificationcode', 'openidreturn'))))
	{
		loadSource('Subs-Auth');
		return 'KickGuest';
	}
	elseif (empty($action))
	{
		// Some add-ons may want to specify default "front page" behavior. If they do, they will return the name of the function they want to call.
		$functions = call_hook('default_action');
		foreach ($functions as $func)
			if (!empty($func))
				return $func;

		// Action and board are both empty... BoardIndex!
		if (empty($board) && empty($topic))
		{
			loadSource('BoardIndex');
			return 'BoardIndex';
		}
		// Topic is empty, and action is empty.... MessageIndex!
		elseif (empty($topic))
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

	// Compatibility with SMF feeds
	if ($action === '.xml')
		$action = 'feed';

	// Allow modifying $action_list easily. (It's a global by now.)
	call_hook('actions');

	// Get the function and file to include - if it's not there, do the board index.
	if (empty($action) || !isset($action_list[$action]))
	{
		// Some add-ons may want to specify default handling behavior - if no known action was used.
		$functions = call_hook('fallback_action');
		foreach ($functions as $func)
			if (!empty($func))
				return $func;
		// Fall through to the board index then...
		loadSource('BoardIndex');
		return 'BoardIndex';
	}

	// Otherwise, it was set - so let's go to that action.
	// !!! Fix this $sourcedir for loadSource
	if (isset($action_list[$action][2]))
		loadAddonSource($action_list[$action][2], $action_list[$action][0]);
	else
		require_once($sourcedir . '/' . $action_list[$action][0]);
	return $action_list[$action][1];
}

?>