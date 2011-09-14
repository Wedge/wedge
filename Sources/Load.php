<?php
/**
 * Wedge
 *
 * This file carries many useful functions for loading various general data from the database, often required on every page.
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
 * Loads the forum-wide settings into $modSettings as an array.
 *
 * - Ensure the database is using the same character set as the application thinks it is.
 * - Attempt to load the settings from cache, failing that from the database, with some fallback/sanity values for a few common settings.
 * - Save the value in cache for next time.
 * - Set the timezone (mandatory in PHP)
 * - Check the load average settings if available.
 * - Check whether post moderation is enabled.
 * - Run any functions specified in the pre_load hook.
 */
function reloadSettings()
{
	global $modSettings, $boarddir, $txt, $context, $sourcedir;

	// Most database systems have not set UTF-8 as their default input charset.
	wesql::query('
		SET NAMES utf8',
		array(
		)
	);

	// Try to load it from the cache first; it'll never get cached if the setting is off.
	if (($modSettings = cache_get_data('modSettings', 90)) == null)
	{
		$request = wesql::query('
			SELECT variable, value
			FROM {db_prefix}settings',
			array(
			)
		);
		$modSettings = array();
		if (!$request)
			show_db_error();
		while ($row = wesql::fetch_row($request))
			$modSettings[$row[0]] = $row[1];
		wesql::free_result($request);

		// Do a few things to protect against missing settings or settings with invalid values...
		if (empty($modSettings['defaultMaxTopics']) || $modSettings['defaultMaxTopics'] <= 0 || $modSettings['defaultMaxTopics'] > 999)
			$modSettings['defaultMaxTopics'] = 20;
		if (empty($modSettings['defaultMaxMessages']) || $modSettings['defaultMaxMessages'] <= 0 || $modSettings['defaultMaxMessages'] > 999)
			$modSettings['defaultMaxMessages'] = 15;
		if (empty($modSettings['defaultMaxMembers']) || $modSettings['defaultMaxMembers'] <= 0 || $modSettings['defaultMaxMembers'] > 999)
			$modSettings['defaultMaxMembers'] = 30;
		$modSettings['registered_hooks'] = empty($modSettings['registered_hooks']) ? array() : unserialize($modSettings['registered_hooks']);
		$modSettings['hooks'] = $modSettings['registered_hooks'];
		$modSettings['pretty_filters'] = unserialize($modSettings['pretty_filters']);

		if (!empty($modSettings['cache_enable']))
			cache_put_data('modSettings', $modSettings, 90);
	}

	loadSource('Class-String');

	// Setting the timezone is a requirement.
	if (isset($modSettings['default_timezone']))
		date_default_timezone_set($modSettings['default_timezone']);
	else
		date_default_timezone_set(@date_default_timezone_get()); // At least attempt to use what the host has to try to prevent lots and lots of errors spewing everywhere.

	// Check the load averages?
	if (!empty($modSettings['loadavg_enable']))
	{
		if (($modSettings['load_average'] = cache_get_data('loadavg', 90)) == null)
		{
			$modSettings['load_average'] = @file_get_contents('/proc/loadavg');
			if (!empty($modSettings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $modSettings['load_average'], $matches) != 0)
				$modSettings['load_average'] = (float) $matches[1];
			elseif (($modSettings['load_average'] = @`uptime`) != null && preg_match('~load average[s]?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $modSettings['load_average'], $matches) != 0)
				$modSettings['load_average'] = (float) $matches[1];
			else
				unset($modSettings['load_average']);

			if (!empty($modSettings['load_average']))
				cache_put_data('loadavg', $modSettings['load_average'], 90);
		}

		if (!empty($modSettings['loadavg_forum']) && !empty($modSettings['load_average']) && $modSettings['load_average'] >= $modSettings['loadavg_forum'])
			show_db_error(true);
	}

	// Is post moderation alive and well?
	$modSettings['postmod_active'] = !empty($modSettings['postmod_enabled']);

	// Call pre-load hook functions.
	call_hook('pre_load');
}

/**
 * Loads the user general details, including username, id, groups, and a few more things.
 *
 * - Firstly, checks the verify_user hook, in case the user id is being supplied from another application.
 * - If it is not, check the cookies to see if a user identity is being provided.
 * - Failing that, investigate the session data (which optionally will be checking the User Agent matches)
 * - Having established the user id, proceed to load the current member's data (from cache as appropriate) and make sure it is cached.
 * - Store the member data in $user_settings, ensure it is cached and verify the password is right.
 * - Check whether the user is attempting to flood the login with requests, and deal with it as appropriate.
 * - Assuming the member is correct, check and update the last-visit information if appropriate.
 * - Ensure the user groups are sanitised; or if not a logged in user, perform 'is this a spider' checks.
 * - Populate $user_info with lots of useful information (id, username, email, password, language, whether the user is a guest or admin, theme information, post count, IP address, time format/offset, avatar, smileys, PM counts, buddy list, ignore user/board preferences, warning level, URL and user groups)
 * - Establish board access rights based as an SQL clause (based on user groups) in $user_info['query_see_board'], and a subset of this to include ignore boards preferences into $user_info['query_wanna_see_board'].
 */
function loadUserSettings()
{
	global $modSettings, $user_settings;
	global $cookiename, $user_info, $language;

	$id_member = 0;

	// Check first the hook, then the cookie, and last the session.
	if (count($hook_ids = call_hook('verify_user')) > 0)
	{
		foreach ($hook_ids as $hook_id)
		{
			$hook_id = (int) $hook_id;
			if ($hook_id > 0)
			{
				$id_member = $hook_id;
				$already_verified = true;
				break;
			}
		}
	}

	if (isset($_REQUEST['upcook']))
		$_COOKIE[$cookiename] = base64_decode(urldecode($_REQUEST['upcook']));

	if (empty($id_member) && isset($_COOKIE[$cookiename]))
	{
		list ($id_member, $password) = @unserialize($_COOKIE[$cookiename]);
		$id_member = !empty($id_member) && strlen($password) > 0 ? (int) $id_member : 0;
	}
	elseif (empty($id_member) && isset($_SESSION['login_' . $cookiename]) && ($_SESSION['USER_AGENT'] == $_SERVER['HTTP_USER_AGENT'] || !empty($modSettings['disableCheckUA'])))
	{
		// !!! Perhaps we can do some more checking on this, such as on the first octet of the IP?
		list ($id_member, $password, $login_span) = @unserialize($_SESSION['login_' . $cookiename]);
		$id_member = !empty($id_member) && strlen($password) == 40 && $login_span > time() ? (int) $id_member : 0;
	}

	// Only load this stuff if the user isn't a guest.
	if ($id_member != 0)
	{
		// Is the member data cached?
		if (empty($modSettings['cache_enable']) || $modSettings['cache_enable'] < 2 || ($user_settings = cache_get_data('user_settings-' . $id_member, 60)) == null)
		{
			$request = wesql::query('
				SELECT mem.*, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = {int:id_member})
				WHERE mem.id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $id_member,
				)
			);
			$user_settings = wesql::fetch_assoc($request);
			wesql::free_result($request);

			if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
				cache_put_data('user_settings-' . $id_member, $user_settings, 60);
		}

		// Did we find 'im? If not, junk it.
		if (!empty($user_settings))
		{
			// As much as the password should be right, we can assume the hook set things up.
			if (!empty($already_verified) && $already_verified === true)
				$check = true;
			// SHA-1 passwords should be 40 characters long.
			elseif (strlen($password) == 40)
				$check = sha1($user_settings['passwd'] . $user_settings['password_salt']) == $password;
			else
				$check = false;

			// Wrong password or not activated - either way, you're going nowhere.
			$id_member = $check && ($user_settings['is_activated'] == 1 || $user_settings['is_activated'] == 11) ? $user_settings['id_member'] : 0;
		}
		else
			$id_member = 0;

		// If we no longer have the member maybe they're being all hackey, stop brute force!
		if (!$id_member)
		{
			loadSource('Subs-Login');
			validatePasswordFlood(!empty($user_settings['id_member']) ? $user_settings['id_member'] : $id_member, !empty($user_settings['passwd_flood']) ? $user_settings['passwd_flood'] : false, $id_member != 0);
		}
	}

	// Found 'im, let's set up the variables.
	if ($id_member != 0)
	{
		// Let's not update the last visit time in these cases...
		// 1. SSI doesn't count as visiting the forum.
		// 2. XML feeds and Ajax requests don't count either.
		// 3. If it was set within this session, no need to set it again.
		// 4. New session, yet updated < five hours ago? Maybe cache can help.
		if (WEDGE != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'feed') && empty($_SESSION['id_msg_last_visit']) && (empty($modSettings['cache_enable']) || ($_SESSION['id_msg_last_visit'] = cache_get_data('user_last_visit-' . $id_member, 5 * 3600)) === null))
		{
			// Do a quick query to make sure this isn't a mistake.
			$result = wesql::query('
				SELECT poster_time
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $user_settings['id_msg_last_visit'],
				)
			);
			list ($visitTime) = wesql::fetch_row($result);
			wesql::free_result($result);

			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

			// If it was *at least* five hours ago...
			if ($visitTime < time() - 5 * 3600)
			{
				updateMemberData($id_member, array('id_msg_last_visit' => (int) $modSettings['maxMsgID'], 'last_login' => time(), 'member_ip' => $_SERVER['REMOTE_ADDR'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']));
				$user_settings['last_login'] = time();

				if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
					cache_put_data('user_settings-' . $id_member, $user_settings, 60);

				if (!empty($modSettings['cache_enable']))
					cache_put_data('user_last_visit-' . $id_member, $_SESSION['id_msg_last_visit'], 5 * 3600);
			}
		}
		elseif (empty($_SESSION['id_msg_last_visit']))
			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

		$username = $user_settings['member_name'];

		if (empty($user_settings['additional_groups']))
			$user_info = array(
				'groups' => array($user_settings['id_group'], $user_settings['id_post_group'])
			);
		else
			$user_info = array(
				'groups' => array_merge(
					array($user_settings['id_group'], $user_settings['id_post_group']),
					explode(',', $user_settings['additional_groups'])
				)
			);

		// Because history has proven that it is possible for groups to go bad - clean up in case.
		foreach ($user_info['groups'] as $k => $v)
			$user_info['groups'][$k] = (int) $v;

		// This is a logged in user, so definitely not a spider.
		$user_info['possibly_robot'] = false;
	}
	// If the user is a guest, initialize all the critical user settings.
	else
	{
		// This is what a guest's variables should be.
		$username = '';
		$user_info = array('groups' => array(-1));
		$user_settings = array();

		if (isset($_COOKIE[$cookiename]))
			$_COOKIE[$cookiename] = '';

		// Do we perhaps think this is a search robot? Check every five minutes just in case...
		if ((!empty($modSettings['spider_mode']) || !empty($modSettings['spider_group'])) && (!isset($_SESSION['robot_check']) || $_SESSION['robot_check'] < time() - 300))
		{
			loadSource('ManageSearchEngines');
			$user_info['possibly_robot'] = SpiderCheck();
		}
		elseif (!empty($modSettings['spider_mode']))
			$user_info['possibly_robot'] = isset($_SESSION['id_robot']) ? $_SESSION['id_robot'] : 0;
		// If we haven't turned on proper spider hunts then have a guess!
		else
			$user_info['possibly_robot'] = (strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') === false && strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') === false) || preg_match('~(?:bot|slurp|crawl|spider)~', strtolower($_SERVER['HTTP_USER_AGENT']));
	}

	// Figure out the new time offset.
	if (!empty($user_settings['timezone']))
	{
		// Get the offsets from UTC for the server, then for the user.+
		$tz_system = new DateTimeZone(@date_default_timezone_get());
		$tz_user = new DateTimeZone($user_settings['timezone']);
		$time_system = new DateTime("now", $tz_system);
		$time_user = new DateTime("now", $tz_user);
		$offset = ($tz_user->getOffset($time_user) - $tz_system->getOffset($time_system)) / 3600; // Convert to hours in the process.
	}

	// Set up the $user_info array.
	$user_info += array(
		'id' => $id_member,
		'username' => $username,
		'name' => isset($user_settings['real_name']) ? $user_settings['real_name'] : '',
		'email' => isset($user_settings['email_address']) ? $user_settings['email_address'] : '',
		'passwd' => isset($user_settings['passwd']) ? $user_settings['passwd'] : '',
		'language' => empty($user_settings['lngfile']) || empty($modSettings['userLanguage']) ? $language : $user_settings['lngfile'],
		'is_guest' => $id_member == 0,
		'is_admin' => in_array(1, $user_info['groups']),
		'theme' => empty($user_settings['id_theme']) ? 0 : $user_settings['id_theme'],
		'skin' => empty($user_settings['id_theme']) ? '' : $user_settings['skin'],
		'last_login' => empty($user_settings['last_login']) ? 0 : $user_settings['last_login'],
		'ip' => $_SERVER['REMOTE_ADDR'],
		'ip2' => $_SERVER['BAN_CHECK_IP'],
		'posts' => empty($user_settings['posts']) ? 0 : $user_settings['posts'],
		'time_format' => empty($user_settings['time_format']) ? $modSettings['time_format'] : $user_settings['time_format'],
		'time_offset' => isset($offset) ? $offset : (empty($user_settings['time_offset']) ? 0 : $user_settings['time_offset']),
		'avatar' => array(
			'url' => isset($user_settings['avatar']) ? $user_settings['avatar'] : '',
			'filename' => empty($user_settings['filename']) ? '' : $user_settings['filename'],
			'custom_dir' => !empty($user_settings['attachment_type']) && $user_settings['attachment_type'] == 1,
			'id_attach' => isset($user_settings['id_attach']) ? $user_settings['id_attach'] : 0
		),
		'smiley_set' => isset($user_settings['smiley_set']) ? $user_settings['smiley_set'] : '',
		'messages' => empty($user_settings['instant_messages']) ? 0 : $user_settings['instant_messages'],
		'unread_messages' => empty($user_settings['unread_messages']) ? 0 : $user_settings['unread_messages'],
		'media_unseen' => empty($user_settings['media_unseen']) ? 0 : $user_settings['media_unseen'],
		'total_time_logged_in' => empty($user_settings['total_time_logged_in']) ? 0 : $user_settings['total_time_logged_in'],
		'buddies' => !empty($modSettings['enable_buddylist']) && !empty($user_settings['buddy_list']) ? explode(',', $user_settings['buddy_list']) : array(),
		'ignoreboards' => !empty($user_settings['ignore_boards']) && !empty($modSettings['allow_ignore_boards']) ? explode(',', $user_settings['ignore_boards']) : array(),
		'ignoreusers' => !empty($user_settings['pm_ignore_list']) ? explode(',', $user_settings['pm_ignore_list']) : array(),
		'warning' => isset($user_settings['warning']) ? $user_settings['warning'] : 0,
		'permissions' => array(),
	);

	// Fill in the server URL for the current user. This is user-specific, as they may be using a different URL than the script's default URL (Pretty URL, secure access...)
	$user_info['host'] = empty($_SERVER['REAL_HTTP_HOST']) ? (empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_X_FORWARDED_SERVER'] : $_SERVER['HTTP_HOST']) : $_SERVER['REAL_HTTP_HOST'];
	$user_info['server'] = 'http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off' ? 's' : '') . '://' . $user_info['host'];

	// Also contains the query string.
	// Do not print this without sanitizing first!
	$user_info['url'] = (empty($_SERVER['REAL_HTTP_HOST']) ? $user_info['server'] : substr($user_info['server'], 0, strpos($user_info['server'], '/')) . '//' . $_SERVER['HTTP_HOST']) . $_SERVER['REQUEST_URI'];

	$user_info['groups'] = array_unique($user_info['groups']);
	// Make sure that the last item in the ignore boards array is valid. If the list was too long it could have an ending comma that could cause problems.
	if (!empty($user_info['ignoreboards']) && empty($user_info['ignoreboards'][$tmp = count($user_info['ignoreboards']) - 1]))
		unset($user_info['ignoreboards'][$tmp]);

	// Do we have any languages to validate this?
	if (!empty($modSettings['userLanguage']) && (!empty($_GET['language']) || !empty($_SESSION['language'])))
		$languages = getLanguages();

	// Allow the user to change their language if it's valid.
	if (!empty($modSettings['userLanguage']) && !empty($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')]))
	{
		$user_info['language'] = strtr($_GET['language'], './\\:', '____');
		$_SESSION['language'] = $user_info['language'];
	}
	elseif (!empty($modSettings['userLanguage']) && !empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')]))
		$user_info['language'] = strtr($_SESSION['language'], './\\:', '____');

	// Just build this here, it makes it easier to change/use - administrators can see all boards.
	if ($user_info['is_admin'])
	{
		$user_info['query_list_board'] = '1=1';
		$user_info['query_see_board'] = '1=1';
	}
	// Otherwise just the groups in $user_info['groups'].
	else
	{
		$cache_groups = $user_info['groups'];
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);

		$temp = cache_get_data('board_access_' . $cache_groups, 300);
		if ($temp === null || time() - 240 > $modSettings['settings_updated'])
		{
			$request = wesql::query('
				SELECT id_board, view_perm, enter_perm
				FROM {db_prefix}board_groups
				WHERE id_group IN ({array_int:groups})',
				array(
					'groups' => $user_info['groups'],
				)
			);
			$access = array(
				'view_allow' => array(),
				'view_deny' => array(),
				'enter_allow' => array(),
				'enter_deny' => array(),
			);
			while ($row = wesql::fetch_assoc($request))
			{
				$access['view_' . $row['view_perm']][] = $row['id_board'];
				$access['enter_' . $row['enter_perm']][] = $row['id_board'];
			}
			$access['view_allow'] = array_diff($access['view_allow'], $access['view_deny']);
			$access['enter_allow'] = array_diff($access['enter_allow'], $access['enter_deny']);
			$user_info['query_list_board'] = empty($access['view_allow']) ? '0=1' : 'b.id_board IN (' . implode(',', $access['view_allow']) . ')';
			$user_info['query_see_board'] = empty($access['enter_allow']) ? '0=1' : 'b.id_board IN (' . implode(',', $access['enter_allow']) . ')';

			$cache = array(
				'query_list_board' => $user_info['query_list_board'],
				'query_see_board' => $user_info['query_see_board'],
				'qlb_boards' => $access['view_allow'],
				'qsb_boards' => $access['enter_allow'],
			);
			cache_put_data('board_access_' . $cache_groups, $cache, 300);
		}
		else
			$user_info += $temp;
	}

	// Build the list of boards they WANT to see.
	// This will take the place of query_see_board in certain spots, so it better include the boards they can see also

	// If they aren't ignoring any boards then they want to see all the boards they can see
	if (empty($user_info['ignoreboards']))
	{
		$user_info['query_wanna_see_board'] = $user_info['query_see_board'];
		$user_info['query_wanna_list_board'] = $user_info['query_list_board'];
	}
	// Ok I guess they don't want to see all the boards
	else
	{
		if ($user_info['is_admin'])
		{
			// Admin can implicitly see and enter every board. If they want to ignore boards, make sure we clear both of the 'wanna see' options.
			$user_info['query_wanna_list_board'] = 'b.id_board NOT IN (' . implode(',', $user_info['ignoreboards']) . ')';
			$user_info['query_wanna_see_board'] = $user_info['query_wanna_list_board'];
		}
		else
		{
			$user_info['query_wanna_see_board'] = 'b.id_board IN (' . implode(',', array_diff($user_info['qsb_boards'], $user_info['ignoreboards'])) . ')';
			$user_info['query_wanna_list_board'] = 'b.id_board IN (' . implode(',', array_diff($user_info['qlb_boards'], $user_info['ignoreboards'])) . ')';
		}
	}

	wesql::register_replacement('query_see_board', $user_info['query_see_board']);
	wesql::register_replacement('query_list_board', $user_info['query_list_board']);
	wesql::register_replacement('query_wanna_see_board', $user_info['query_wanna_see_board']);
	wesql::register_replacement('query_wanna_list_board', $user_info['query_wanna_list_board']);
}

/**
 * Validate whether we are dealing with a board, and whether the current user has access to that board.
 *
 * - Initialize the link tree (and later, populating it).
 * - If only an individual msg is specified in the URL, identify the topic it belongs to, and redirect to that topic normally. (Assuming it exists; if not throw a fatal error that topic does not exist)
 * - If no board or topic is applicable, return (we're not in a board, and there won't be board moderators)
 * - See if we have checked this board or board+topic lately, and if so, grab from cache.
 * - If we don't have this, load the board information into $board_info, including category id and name, board name and other details of this board
 * - See if there are board moderators, and whether the current user is amongst them (which means possibly upgrading access if they did not have so before, as well as adding group id 3 to their groups)
 * - If the user cannot see the topic (and isn't a local moderator), issue a fatal error.
 */
function loadBoard()
{
	global $txt, $scripturl, $context, $modSettings;
	global $board_info, $board, $topic, $user_info, $user_settings;

	// Assume they are not a moderator.
	$user_info['is_mod'] = false;
	$context['user']['is_mod'] = &$user_info['is_mod'];

	// Start the linktree off empty..
	$context['linktree'] = array();

	// Have they by chance specified a message id but nothing else?
	if (empty($_REQUEST['action']) && empty($topic) && empty($board) && !empty($_REQUEST['msg']))
	{
		// Make sure the message id is really an int.
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Looking through the message table can be slow, so try using the cache first.
		if (($topic = cache_get_data('msg_topic-' . $_REQUEST['msg'], 120)) === null)
		{
			$request = wesql::query('
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $_REQUEST['msg'],
				)
			);

			// So did it find anything?
			if (wesql::num_rows($request))
			{
				list ($topic) = wesql::fetch_row($request);
				wesql::free_result($request);
				// Save save save.
				cache_put_data('msg_topic-' . $_REQUEST['msg'], $topic, 120);
			}
		}

		// Remember redirection is the key to avoiding fallout from your bosses.
		if (!empty($topic))
			redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg']);
		else
		{
			loadPermissions();
			loadTheme();
			fatal_lang_error('topic_gone', false);
		}
	}

	// Load this board only if it is specified.
	if (empty($board) && empty($topic))
	{
		$board_info = array(
			'moderators' => array(),
			'skin' => '',
		);
		return;
	}
	// Is this a XML feed requesting a topic?
	elseif (empty($board) && !empty($topic) && isset($_REQUEST['action']) && $_REQUEST['action'] === 'feed')
		return;

	if (!empty($modSettings['cache_enable']) && (empty($topic) || $modSettings['cache_enable'] >= 3))
	{
		// !!! SLOW?
		if (!empty($topic))
			$temp = cache_get_data('topic_board-' . $topic, 120);
		else
			$temp = cache_get_data('board-' . $board, 120);

		if (!empty($temp))
		{
			$board_info = $temp;
			$board = $board_info['id'];
		}
	}

	if (empty($temp))
	{
		$request = wesql::query('
			SELECT
				c.id_cat, b.name AS bname, b.url, b.id_owner, b.description, b.num_topics, b.member_groups,
				b.num_posts, b.id_parent, c.name AS cname, IFNULL(mem.id_member, 0) AS id_moderator,
				mem.real_name' . (!empty($topic) ? ', b.id_board' : '') . ', b.child_level, b.skin,
				b.id_theme, b.override_theme, b.count_posts, b.id_profile, b.redirect, b.language, bm.permission = \'deny\' AS banned,
				bm.permission = \'access\' AS allowed, mco.real_name AS owner_name, mco.buddy_list AS friends, b.board_type, b.sort_method,
				b.sort_override, b.unapproved_topics, b.unapproved_posts' . (!empty($topic) ? ', t.approved, t.id_member_started' : '') . '
			FROM {db_prefix}boards AS b' . (!empty($topic) ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})' : '') . '
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = {raw:board_link})
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
				LEFT JOIN {db_prefix}members AS mco ON (mco.id_member = b.id_owner)
				LEFT JOIN {db_prefix}board_members AS bm ON b.id_board = bm.id_board AND b.id_owner = {int:id_member}
			WHERE b.id_board = {raw:board_link}',
			array(
				'current_topic' => $topic,
				'board_link' => empty($topic) ? wesql::quote('{int:current_board}', array('current_board' => $board)) : 't.id_board',
				'id_member' => $user_info['id'],
			)
		);
		// If there aren't any, skip.
		if (wesql::num_rows($request) > 0)
		{
			$row = wesql::fetch_assoc($request);

			// Set the current board.
			if (!empty($row['id_board']))
				$board = $row['id_board'];

			// Basic operating information. (Globals... :-/)
			$board_info = array(
				'id' => $board,
				'owner_id' => $row['id_owner'],
				'owner_name' => $row['owner_name'],
				'moderators' => array(),
				'cat' => array(
					'id' => $row['id_cat'],
					'name' => $row['cname']
				),
				'name' => $row['bname'],
				'description' => $row['description'],
				'url' => $row['url'],
				'num_posts' => $row['num_posts'],
				'num_topics' => $row['num_topics'],
				'unapproved_topics' => $row['unapproved_topics'],
				'unapproved_posts' => $row['unapproved_posts'],
				'unapproved_user_topics' => 0,
				'parent_boards' => getBoardParents($row['id_parent']),
				'parent' => $row['id_parent'],
				'child_level' => $row['child_level'],
				'skin' => $row['skin'],
				'theme' => $row['id_theme'],
				'override_theme' => !empty($row['override_theme']),
				'profile' => $row['id_profile'],
				'redirect' => $row['redirect'],
				'posts_count' => empty($row['count_posts']),
				'cur_topic_approved' => empty($topic) || $row['approved'],
				'cur_topic_starter' => empty($topic) ? 0 : $row['id_member_started'],
				'allowed_member' => $row['allowed'],
				'banned_member' => $row['banned'],
				'friends' => $row['friends'],
				'language' => $row['language'],
				'type' => $row['board_type'],
				'sort_method' => $row['sort_method'],
				'sort_override' => $row['sort_override'],
			);

			// Load privacy settings.
			if ($row['member_groups'] === '0')
				$board_info['privacy'] = 'members';
			elseif ($row['member_groups'] === '-1,0')
				$board_info['privacy'] = 'everyone';
			elseif ($row['member_groups'] === 'friends')
				$board_info['privacy'] = 'friends';
			elseif ($row['member_groups'] === '')
			{
				$board_info['privacy'] = 'justme';
				$row['member_groups'] = '';
			}
			else
				$board_info['privacy'] = 'everyone';

			// Load the membergroups allowed, and check permissions.
			$board_info['groups'] = $row['member_groups'] == '' ? array() : explode(',', $row['member_groups']);

			if (!empty($row['id_owner']))
				$board_info['moderators'] = array(
					$row['id_owner'] => array(
						'id' => $row['id_owner'],
						'name' => $row['owner_name'],
						'href' => $scripturl . '?action=profile;u=' . $row['id_owner'],
						'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_owner'] . '">' . $row['owner_name'] . '</a>'
					)
				);

			do
			{
				if (!empty($row['id_moderator']) && $row['id_moderator'] != $row['id_owner'])
					$board_info['moderators'][$row['id_moderator']] = array(
						'id' => $row['id_moderator'],
						'name' => $row['real_name'],
						'href' => $scripturl . '?action=profile;u=' . $row['id_moderator'],
						'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
					);
			}
			while ($row = wesql::fetch_assoc($request));

			// If the board only contains unapproved posts and the user isn't an approver then they can't see any topics.
			// If that is the case do an additional check to see if they have any topics waiting to be approved.
			if ($board_info['num_topics'] == 0 && $modSettings['postmod_active'] && !allowedTo('approve_posts'))
			{
				wesql::free_result($request); // Free the previous result

				$request = wesql::query('
					SELECT COUNT(id_topic)
					FROM {db_prefix}topics
					WHERE id_member_started={int:id_member}
						AND approved = {int:unapproved}
						AND id_board = {int:board}',
					array(
						'id_member' => $user_info['id'],
						'unapproved' => 0,
						'board' => $board,
					)
				);

				list ($board_info['unapproved_user_topics']) = wesql::fetch_row($request);
			}

			if (!empty($modSettings['cache_enable']) && (empty($topic) || $modSettings['cache_enable'] >= 3))
			{
				// !!! SLOW?
				if (!empty($topic))
					cache_put_data('topic_board-' . $topic, $board_info, 120);
				cache_put_data('board-' . $board, $board_info, 120);
			}
		}
		else
		{
			// Otherwise the topic is invalid, there are no moderators, etc.
			$board_info = array(
				'moderators' => array(),
				'skin' => '',
				'error' => 'exist',
			);
			$topic = null;
			$board = 0;
		}
		wesql::free_result($request);
	}

	if (!empty($topic))
		$_GET['board'] = (int) $board;

	if (!empty($board))
	{
		// Now check if the user is a moderator.
		$user_info['is_mod'] = isset($board_info['moderators'][$user_info['id']]);

		if ($board_info['banned_member'] && !$board_info['allowed_member'])
			$board_info['error'] = 'access';

		if (count(array_intersect($user_info['groups'], $board_info['groups'])) == 0 && !$user_info['is_admin'])
			if (!$user_info['is_mod'] && ($user_info['id'] != $board_info['owner_id']))
				if ($board_info['privacy'] == 'friends' && !in_array($user_info['id'], explode(',', $board_info['friends'])))
					$board_info['error'] = 'access';

		// Build up the linktree.
		$context['linktree'] = array_merge(
			$context['linktree'],
			array(array(
				'url' => $scripturl . '#category_' . $board_info['cat']['id'],
				'name' => $board_info['cat']['name']
			)),
			array_reverse($board_info['parent_boards']),
			array(array(
				'url' => $scripturl . '?board=' . $board . '.0',
				'name' => $board_info['name']
			))
		);

		// Does this board have its own language setting? If so, does the user have their
		// own personal language set? (User preference beats board, which beats forum default)
		if (!empty($board_info['language']) && empty($user_settings['lngfile']))
		{
			$user_info['language'] = $board_info['language'];
			$user_settings['lngfile'] = $board_info['language'];
		}
	}

	// Set the template contextual information.
	$context['user']['is_mod'] = &$user_info['is_mod'];
	$context['current_topic'] = $topic;
	$context['current_board'] = $board;

	// Hacker... you can't see this topic, I'll tell you that. (but moderators can!)
	if (!empty($board_info['error']) && ($board_info['error'] != 'access' || !$user_info['is_mod']))
	{
		// The permissions and theme need loading, just to make sure everything goes smoothly.
		loadPermissions();
		loadTheme();

		$_GET['board'] = '';
		$_GET['topic'] = '';

		// The linktree should not give the game away mate! However, it WILL be available to admins etc. for Who's Online so they can see what's going on.
		$context['linktree'] = array(
			array(
				'url' => $scripturl,
				'name' => $context['forum_name_html_safe']
			)
		);

		// If it's a prefetching agent or we're requesting an attachment.
		if ((isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') || (!empty($_REQUEST['action']) && $_REQUEST['action'] === 'dlattach'))
		{
			ob_end_clean();
			header('HTTP/1.1 403 Forbidden');
			die;
		}
		elseif ($user_info['is_guest'])
		{
			loadLanguage('Errors');
			is_not_guest($txt['topic_gone']);
		}
		else
			fatal_lang_error('topic_gone', false);
	}

	if ($user_info['is_mod'])
		$user_info['groups'][] = 3;
}

/**
 * Load the current user's permissions, to be stored in $user_info['permissions']
 *
 * - If the user is an admin, simply validate that they have not been banned then return.
 * - Attempt to load from cache (level 2+ caching only); if matched, apply ban restrictions and return.
 * - See if the user is possibly a spider, extend the user's "permissions" appropriately.
 * - If we have not been able to establish permissions thus far (because caching failed us), query the general permissions table for our groups.
 * - Then apply those permissions, both allow and denied.
 * - If inside a board, identify the board profile, and load the permissions from that, following the same process.
 * - If on caching level 2 or up, cache, then apply banned user permissions if banned.
 * - If the user is not a guest, identify what other boards they may have access to through the moderator cache.
 */
function loadPermissions()
{
	global $user_info, $board, $board_info, $modSettings;

	if ($user_info['is_admin'])
	{
		banPermissions();
		return;
	}

	if (!empty($modSettings['cache_enable']))
	{
		$cache_groups = $user_info['groups'];
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);
		// If it's a spider then cache it different.
		if ($user_info['possibly_robot'])
			$cache_groups .= '-spider';

		if ($modSettings['cache_enable'] >= 2 && !empty($board) && ($temp = cache_get_data('permissions:' . $cache_groups . ':' . $board, 240)) != null && time() - 240 > $modSettings['settings_updated'])
		{
			list ($user_info['permissions']) = $temp;
			banPermissions();

			return;
		}
		elseif (($temp = cache_get_data('permissions:' . $cache_groups, 240)) != null && time() - 240 > $modSettings['settings_updated'])
			list ($user_info['permissions'], $removals) = $temp;
	}

	// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
	$spider_restrict = $user_info['possibly_robot'] && !empty($modSettings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

	if (empty($user_info['permissions']))
	{
		// Get the general permissions.
		$request = wesql::query('
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:member_groups})
				' . $spider_restrict,
			array(
				'member_groups' => $user_info['groups'],
				'spider_group' => !empty($modSettings['spider_group']) ? $modSettings['spider_group'] : 0,
			)
		);
		$removals = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		wesql::free_result($request);

		if (isset($cache_groups))
			cache_put_data('permissions:' . $cache_groups, array($user_info['permissions'], $removals), 240);
	}

	// Get the board permissions.
	if (!empty($board))
	{
		// Make sure the board (if any) has been loaded by loadBoard().
		if (!isset($board_info['profile']))
			fatal_lang_error('no_board');

		$request = wesql::query('
			SELECT permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE (id_group IN ({array_int:member_groups})
				' . $spider_restrict . ')
				AND id_profile = {int:id_profile}',
			array(
				'member_groups' => $user_info['groups'],
				'id_profile' => $board_info['profile'],
				'spider_group' => !empty($modSettings['spider_group']) ? $modSettings['spider_group'] : 0,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		wesql::free_result($request);
	}

	// Remove all the permissions they shouldn't have ;).
	if (!empty($modSettings['permission_enable_deny']))
		$user_info['permissions'] = array_diff($user_info['permissions'], $removals);

	if (isset($cache_groups) && !empty($board) && $modSettings['cache_enable'] >= 2)
		cache_put_data('permissions:' . $cache_groups . ':' . $board, array($user_info['permissions'], null), 240);

	// Banned? Watch, don't touch..
	banPermissions();

	// Load the mod cache so we can know what additional boards they should see, but no sense in doing it for guests
	if (!$user_info['is_guest'])
	{
		if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= $modSettings['settings_updated'])
		{
			loadSource('Subs-Auth');
			rebuildModCache();
		}
		else
			$user_info['mod_cache'] = $_SESSION['mc'];
	}
}

/**
 * Loads user data, either by id or member_name, and can load one or many users' data together.
 *
 * User data, where successful, is loaded into the global $user_profiles array, keyed by user id. The exact data set is dependent on $set.
 *
 * @param mixed $users This can be either a single value or an array, representing a single user or multiple users.
 * @param bool $is_name If this parameter is true, treat the value(s) in $users as denoting user names, otherwise they are numeric user ids.
 * @param string $set Complexity of data to load, from 'minimal', 'normal', 'profile', each successively increasing in complexity.
 * @return mixed Returns either an array of users whose data was matched, or false if no matches were made.
 */
function loadMemberData($users, $is_name = false, $set = 'normal')
{
	global $user_profile, $modSettings, $board_info;

	// Can't just look for no users :P.
	if (empty($users))
		return false;

	// Make sure it's an array.
	$users = !is_array($users) ? array($users) : array_unique($users);
	$loaded_ids = array();

	if (!$is_name && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
	{
		$users = array_values($users);
		for ($i = 0, $n = count($users); $i < $n; $i++)
		{
			$data = cache_get_data('member_data-' . $set . '-' . $users[$i], 240);
			if ($data == null)
				continue;

			$loaded_ids[] = $data['id_member'];
			$user_profile[$data['id_member']] = $data;
			unset($users[$i]);
		}
	}

	if ($set == 'normal')
	{
		$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.signature, mem.personal_text, mem.location, mem.gender, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.birthdate, mem.member_ip, mem.member_ip2, mem.posts, mem.last_login,
			mem.id_post_group, mem.lngfile, mem.id_group, mem.time_offset, mem.show_online, mem.media_items, mem.media_comments,
			mem.buddy_list, mg.online_color AS member_group_color, IFNULL(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, IFNULL(pg.group_name, {string:blank_string}) AS post_group, mem.is_activated, mem.warning,
			CASE WHEN mem.id_group = 0 OR mg.stars = {string:blank_string} THEN pg.stars ELSE mg.stars END AS stars' . (!empty($modSettings['titlesEnable']) ? ',
			mem.usertitle' : '');
		$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';
	}
	elseif ($set == 'profile')
	{
		$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type,
			mem.signature, mem.personal_text, mem.location, mem.gender, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.openid_uri, mem.birthdate, mem.posts, mem.last_login, mem.media_items,
			mem.media_comments, mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group, mem.id_theme, mem.buddy_list,
			mem.pm_ignore_list, mem.pm_email_notify, mem.pm_receive_from, mem.time_offset' . (!empty($modSettings['titlesEnable']) ? ', mem.usertitle' : '') . ',
			mem.time_format, mem.secret_question, mem.is_activated, mem.additional_groups, mem.smiley_set, mem.show_online,
			mem.total_time_logged_in, mem.id_post_group, mem.notify_announcements, mem.notify_regularity, mem.notify_send_body,
			mem.notify_types, lo.url, mg.online_color AS member_group_color, IFNULL(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, IFNULL(pg.group_name, {string:blank_string}) AS post_group, mem.ignore_boards, mem.warning,
			CASE WHEN mem.id_group = 0 OR mg.stars = {string:blank_string} THEN pg.stars ELSE mg.stars END AS stars, mem.password_salt, mem.pm_prefs';
		$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';
	}
	elseif ($set == 'minimal')
	{
		$select_columns = '
			mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.hide_email, mem.date_registered,
			mem.posts, mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group';
		$select_tables = '';
	}
	else
		trigger_error('loadMemberData(): Invalid member data set \'' . $set . '\'', E_USER_WARNING);

	if (!empty($users))
	{
		// Load the member's data.
		$request = wesql::query('
			SELECT' . $select_columns . '
			FROM {db_prefix}members AS mem' . $select_tables . '
			WHERE mem.' . ($is_name ? 'member_name' : 'id_member') . (count($users) == 1 ? ' = {' . ($is_name ? 'string' : 'int') . ':users}' : ' IN ({' . ($is_name ? 'array_string' : 'array_int') . ':users})'),
			array(
				'blank_string' => '',
				'users' => count($users) == 1 ? current($users) : $users,
			)
		);
		$new_loaded_ids = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$new_loaded_ids[] = $row['id_member'];
			$loaded_ids[] = $row['id_member'];
			$row['options'] = array();
			$row['member_ip'] = format_ip($row['member_ip']);
			$row['member_ip2'] = format_ip($row['member_ip2']);
			$user_profile[$row['id_member']] = $row;
		}
		wesql::free_result($request);
	}

	if (!empty($new_loaded_ids) && $set !== 'minimal')
	{
		$request = wesql::query('
			SELECT *
			FROM {db_prefix}themes
			WHERE id_member' . (count($new_loaded_ids) == 1 ? ' = {int:loaded_ids}' : ' IN ({array_int:loaded_ids})'),
			array(
				'loaded_ids' => count($new_loaded_ids) == 1 ? $new_loaded_ids[0] : $new_loaded_ids,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$user_profile[$row['id_member']]['options'][$row['variable']] = $row['value'];
		wesql::free_result($request);
	}

	if (!empty($new_loaded_ids) && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
	{
		for ($i = 0, $n = count($new_loaded_ids); $i < $n; $i++)
			cache_put_data('member_data-' . $set . '-' . $new_loaded_ids[$i], $user_profile[$new_loaded_ids[$i]], 240);
	}

	// Are we loading any moderators? If so, fix their group data...
	if (!empty($loaded_ids) && !empty($board_info['moderators']) && $set === 'normal' && count($temp_mods = array_intersect($loaded_ids, array_keys($board_info['moderators']))) !== 0)
	{
		if (($row = cache_get_data('moderator_group_info', 480)) == null)
		{
			$request = wesql::query('
				SELECT group_name AS member_group, online_color AS member_group_color, stars
				FROM {db_prefix}membergroups
				WHERE id_group = {int:moderator_group}
				LIMIT 1',
				array(
					'moderator_group' => 3,
				)
			);
			$row = wesql::fetch_assoc($request);
			wesql::free_result($request);

			cache_put_data('moderator_group_info', $row, 480);
		}

		foreach ($temp_mods as $id)
		{
			// By popular demand, don't show admins or global moderators as moderators.
			if ($user_profile[$id]['id_group'] != 1 && $user_profile[$id]['id_group'] != 2)
				$user_profile[$id]['member_group'] = $row['member_group'];

			// If the Moderator group has no color or stars, but their group does... don't overwrite.
			if (!empty($row['stars']))
				$user_profile[$id]['stars'] = $row['stars'];
			if (!empty($row['member_group_color']))
				$user_profile[$id]['member_group_color'] = $row['member_group_color'];
		}
	}

	return empty($loaded_ids) ? false : $loaded_ids;
}

/**
 * Processes all the data previously loaded by {@link loadMemberData()} into a form more readily usable by the rest of the application.
 *
 * {@link loadMemberData()} issues the query and stores the result as-is, this function deals with formatting and setting up the results that make it much easier to use the data as-is, for example taking the raw data as issued about avatars, and creating a single unified array that can be used throughout the application.
 *
 * The following items are prepared by this function:
 * - Identity: username (raw username), name (display name), id (user id)
 * - Buddies: is_buddy (whether the user loaded is a buddy of the current user), is_reverse_buddy (whether the loaded user has the current user as a buddy), buddies (comma separated list of the user's buddies)
 * - User details: title (custom title), href (URL of user profile), link (a full HTML link to the user profile), email (user email address), show_email (whether to show the loaded user's address to the current user), blurb (personal text, censored)
 * - Dates: registered (formatted time/date of registration), registered_timestamp (timestap of registration), birth_date (regular date, showing the user's birth date)
 * - Gender: gender (array; contains name, text string of male/female; image, the HTML img link to the relevant image)
 * - Website: website (array; contains title, the title of the website; url, the bare URL of the given website)
 * - Profile fields: signature (signature, censored), location (user location, censored)
 * - Post counts: real_posts (bare integer of post count), posts (a formatted version of the post count, additionally using a fun comment if the user has more than half a million posts)
 * - Avatar: avatar (array; contains name, string for non uploaded avatar; image, HTML containing the final image link; href, basic href to uploaded avatar; url, URL to non uploaded avatar)
 * - Last login: last_login (formatted string denoting the time of last login), last_login_timestamp (timestamp of last login)
 * - IP address: ip and ip2 - the two user IP addresses held by a user.
 * - Online details: online (array; is_online, boolean whether the user is online or not; text, localized string for 'online' or 'offline'; href, URL to send this user a PM; link, the HTML for a link to send this user a PM; image_href, the HTML to send this user a PM, but with the online/offline indicator; label, same as 'text')
 * - User's language: language, the language name, capitalized
 * - Account status: is_activated (boolean for whether account is active), is_banned (boolean for whether account is currently banned), is_guest (true - user is not a guest), warning (user's warning level), warning_status (level of warn status: '', watch, moderate, mute)
 * - Groups: group (string, the user's primary group), group_color (string, color for user's primary group), group_id (integer, user's primary group id), post_group (string, the user's post group), post_group_color (string, color for user's post group), group_stars (HTML markup for displaying the user's badge)
 * - Other: options (array of user's options), local_time (user's local time, using their offset), custom_fields (if $display_custom_fields is true, but content depends on custom fields)
 *
 * The results are stored in the global $memberContext array, keyed by user id.
 *
 * @param int $user The user id to process for.
 * @param bool $display_custom_fields Whether to load and process custom fields.
 * @return bool Return true if user's data was able to be loaded, false if not. (Error will be thrown if the user id is non-zero but the user was not passed through {@link loadMemberData()} first.
 */
function loadMemberContext($user, $display_custom_fields = false)
{
	global $memberContext, $user_profile, $txt, $scripturl, $user_info;
	global $context, $modSettings, $board_info, $settings;
	static $dataLoaded = array();

	// If this person's data is already loaded, skip it.
	if (isset($dataLoaded[$user]))
		return true;

	// We can't load guests or members not loaded by loadMemberData()!
	if ($user == 0)
		return false;
	if (!isset($user_profile[$user]))
	{
		trigger_error('loadMemberContext(): member id ' . $user . ' not previously loaded by loadMemberData()', E_USER_WARNING);
		return false;
	}

	// Well, it's loaded now anyhow.
	$dataLoaded[$user] = true;
	$profile = $user_profile[$user];

	// Censor everything.
	censorText($profile['signature']);
	censorText($profile['personal_text']);
	censorText($profile['location']);

	// Set things up to be used before hand.
	$gendertxt = $profile['gender'] == 2 ? $txt['female'] : ($profile['gender'] == 1 ? $txt['male'] : '');
	$profile['signature'] = str_replace(array("\n", "\r"), array('<br>', ''), $profile['signature']);
	$profile['signature'] = parse_bbc($profile['signature'], true, 'sig' . $profile['id_member']);

	$profile['is_online'] = (!empty($profile['show_online']) || allowedTo('moderate_forum')) && $profile['is_online'] > 0;
	$profile['stars'] = empty($profile['stars']) ? array('', '') : explode('#', $profile['stars']);
	// Setup the buddy status here (One whole in_array call saved :P)
	$profile['buddy'] = in_array($profile['id_member'], $user_info['buddies']);
	$buddy_list = !empty($profile['buddy_list']) ? explode(',', $profile['buddy_list']) : array();

	// If we're always html resizing, assume it's too large.
	if ($modSettings['avatar_action_too_large'] == 'option_html_resize' || $modSettings['avatar_action_too_large'] == 'option_js_resize')
	{
		$avatar_width = !empty($modSettings['avatar_max_width_external']) ? ' width="' . $modSettings['avatar_max_width_external'] . '"' : '';
		$avatar_height = !empty($modSettings['avatar_max_height_external']) ? ' height="' . $modSettings['avatar_max_height_external'] . '"' : '';
	}
	else
	{
		$avatar_width = '';
		$avatar_height = '';
	}

	// What a monstrous array...
	$memberContext[$user] = array(
		'username' => $profile['member_name'],
		'name' => $profile['real_name'],
		'id' => $profile['id_member'],
		'is_buddy' => $profile['buddy'],
		'is_reverse_buddy' => in_array($user_info['id'], $buddy_list),
		'buddies' => $buddy_list,
		'title' => !empty($modSettings['titlesEnable']) ? $profile['usertitle'] : '',
		'href' => $scripturl . '?action=profile;u=' . $profile['id_member'],
		'link' => '<a href="' . $scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['profile_of'] . ' ' . $profile['real_name'] . '">' . $profile['real_name'] . '</a>',
		'email' => $profile['email_address'],
		'show_email' => showEmailAddress(!empty($profile['hide_email']), $profile['id_member']),
		'registered' => empty($profile['date_registered']) ? $txt['not_applicable'] : timeformat($profile['date_registered']),
		'registered_timestamp' => empty($profile['date_registered']) ? 0 : forum_time(true, $profile['date_registered']),
		'blurb' => $profile['personal_text'],
		'gender' => array(
			'name' => $gendertxt,
			'image' => !empty($profile['gender']) ? '<img class="gender" src="' . $settings['images_url'] . '/' . ($profile['gender'] == 1 ? 'Male' : 'Female') . '.gif" alt="' . $gendertxt . '">' : ''
		),
		'website' => array(
			'title' => $profile['website_title'],
			'url' => $profile['website_url'],
		),
		'birth_date' => empty($profile['birthdate']) || $profile['birthdate'] === '0001-01-01' ? '0000-00-00' : (substr($profile['birthdate'], 0, 4) === '0004' ? '0000' . substr($profile['birthdate'], 4) : $profile['birthdate']),
		'signature' => $profile['signature'],
		'location' => $profile['location'],
		'real_posts' => $profile['posts'],
		'posts' => $profile['posts'] > 500000 ? $txt['geek'] : comma_format($profile['posts']),
		'last_login' => empty($profile['last_login']) ? $txt['never'] : timeformat($profile['last_login']),
		'last_login_timestamp' => empty($profile['last_login']) ? 0 : forum_time(0, $profile['last_login']),
		'ip' => htmlspecialchars($profile['member_ip']),
		'ip2' => htmlspecialchars($profile['member_ip2']),
		'online' => array(
			'is_online' => $profile['is_online'],
			'text' => $txt[$profile['is_online'] ? 'online' : 'offline'],
			'href' => $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'],
			'link' => '<a href="' . $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'] . '">' . $txt[$profile['is_online'] ? 'online' : 'offline'] . '</a>',
			'image_href' => $settings['images_url'] . '/' . ($profile['buddy'] ? 'buddy_' : '') . ($profile['is_online'] ? 'useron' : 'useroff') . '.gif',
			'label' => $txt[$profile['is_online'] ? 'online' : 'offline']
		),
		'language' => westr::ucwords(strtr($profile['lngfile'], array('_' => ' ', '-utf8' => ''))),
		'is_activated' => isset($profile['is_activated']) ? $profile['is_activated'] : 1,
		'is_banned' => isset($profile['is_activated']) ? $profile['is_activated'] >= 10 : 0,
		'options' => $profile['options'],
		'is_guest' => false,
		'group' => $profile['member_group'],
		'group_color' => $profile['member_group_color'],
		'group_id' => $profile['id_group'],
		'post_group' => $profile['post_group'],
		'post_group_color' => $profile['post_group_color'],
		'group_stars' => str_repeat('<img src="' . str_replace('$language', $context['user']['language'], isset($profile['stars'][1]) ? $settings['images_url'] . '/' . $profile['stars'][1] : '') . '">', empty($profile['stars'][0]) || empty($profile['stars'][1]) ? 0 : $profile['stars'][0]),
		'warning' => $profile['warning'],
		'warning_status' => empty($modSettings['warning_mute']) ? '' : (isset($profile['is_activated']) && $profile['is_activated'] >= 10 ? 'ban' : ($modSettings['warning_mute'] <= $profile['warning'] ? 'mute' : (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $profile['warning'] ? 'moderate' : (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $profile['warning'] ? 'watch' : '')))),
		'local_time' => timeformat(time() + ($profile['time_offset'] - $user_info['time_offset']) * 3600, false),
		'media' => array(
			'total_items' => $profile['media_items'],
			'total_comments' => $profile['media_comments'],
		),
		'avatar' => array(
			'name' => '',
			'image' => '',
			'href' => '',
			'url' => '',
		),
	);

	// Avatars are tricky, so let's do them next.
	// So, they're not banned, or if they are, we're not hiding their avatar.
	if (!$memberContext[$user]['is_banned'] || empty($modSettings['avatar_banned_hide']))
	{
		// So it's stored in members/avatar?
		if (!empty($profile['avatar']))
		{
			if (stristr($profile['avatar'], 'gravatar://'))
			{
				if ($profile['avatar'] === 'gravatar://' || empty($modSettings['gravatarAllowExtraEmail']))
					$image = get_gravatar_url($profile['email_address']);
				else
					$image = get_gravatar_url(substr($profile['avatar'], 11));

				$memberContext[$user]['avatar'] = array(
					'name' => $profile['avatar'],
					'image' => '<img class="avatar" src="' . $image . '"' . $avatar_width . $avatar_height . '>',
					'href' => $image,
					'url' => $image,
				);
			}
			else
				$memberContext[$user]['avatar'] = array(
					'name' => $profile['avatar'],
					'image' => stristr($profile['avatar'], 'http://') ? '<img class="avatar" src="' . $profile['avatar'] . '"' . $avatar_width . $avatar_height . '>' : '<img class="avatar" src="' . $modSettings['avatar_url'] . '/' . htmlspecialchars($profile['avatar']) . '">',
					'href' => stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $modSettings['avatar_url'] . '/' . $profile['avatar'],
					'url' => stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $modSettings['avatar_url'] . '/' . $profile['avatar'],
				);
		}
		// It's an attachment?
		elseif (!empty($profile['id_attach']))
		{
			$memberContext[$user]['avatar'] = array(
				'name' => $profile['avatar'],
				'image' => $profile['id_attach'] > 0 ? '<img class="avatar" src="' . (empty($profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $profile['filename']) . '">' : '',
				'href' => $profile['id_attach'] > 0 ? (empty($profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $modSettings['custom_avatar_url'] . '/' . $profile['filename']) : '',
				'url' => '',
			);
		}
		// Default avatar?
		elseif (false)
		{
			// !!! Finish this.
		}
	}

	// Are we also loading the members custom fields into context?
	if ($display_custom_fields && !empty($modSettings['displayFields']))
	{
		$memberContext[$user]['custom_fields'] = array();
		if (!isset($context['display_fields']))
			$context['display_fields'] = unserialize($modSettings['displayFields']);

		foreach ($context['display_fields'] as $custom)
		{
			if (empty($custom['title']) || empty($profile['options'][$custom['colname']]))
				continue;
			elseif ($user_info['is_guest'] && empty($custom['show_guest']))
				continue;

			$value = $profile['options'][$custom['colname']];

			// BBC?
			if ($custom['bbc'])
				$value = parse_bbc($value);
			// ... or checkbox?
			elseif (isset($custom['type']) && $custom['type'] == 'check')
				$value = $value ? $txt['yes'] : $txt['no'];

			// Enclosing the user input within some other text?
			if (!empty($custom['enclose']))
				$value = strtr($custom['enclose'], array(
					'{SCRIPTURL}' => $scripturl,
					'{IMAGES_URL}' => $settings['images_url'],
					'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
					'{INPUT}' => $value,
				));

			$memberContext[$user]['custom_fields'][] = array(
				'title' => $custom['title'],
				'colname' => $custom['colname'],
				'value' => $value,
				'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
			);
		}
	}

	return true;
}

/**
 * Attempts to detect the browser, including version, needed for browser specific fixes and behaviours, and populates $context['browser'] with the findings.
 *
 * In all cases, general branch as well as major version is detected for, meaning that not only would Internet Explorer 8 be detected, so would Internet Explorer generically. This also sets flags for general emulation behavior later on, plus handling some types of robot.
 *
 * Current detection:
 * - Opera
 * - Firefox
 * - Chrome
 * - Safari
 * - Webkit (used in Safari, Chrome, Android...)
 * - iPhone/iPod Touch (Safari Mobile)
 * - Android
 * - Tablets (only iPad for now)
 * - Gecko engine (used in Firefox and compatible)
 * - Internet Explorer (plus tests for 6, 7, 8, 9)
 */
function detectBrowser()
{
	global $context, $browser, $user_info;

	// The following determines the user agent (browser) as best it can.
	$browser = array(
		'ua' => $ua = $_SERVER['HTTP_USER_AGENT'],
		'is_opera' => strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== false
	);

	// Detect Webkit and related
	$browser['is_webkit'] = $is_webkit = strpos($ua, 'AppleWebKit') !== false;
	$browser['is_chrome'] = $is_webkit && strpos($ua, 'Chrome') !== false;
	$browser['is_safari'] = $is_webkit && !$browser['is_chrome'] && strpos($ua, 'Safari') !== false;
	$browser['is_iphone'] = $is_webkit && (strpos($ua, 'iPhone') !== false || strpos($ua, 'iPod') !== false);
	$browser['is_android'] = $is_webkit && strpos($ua, 'Android') !== false;

	// We're only detecting the iPad for now. Well it's a start...
	$browser['is_tablet'] = $is_webkit && strpos($ua, 'iPad') !== false;

	// Detect Firefox versions
	$browser['is_gecko'] = !$is_webkit && strpos($ua, 'Gecko') !== false;	// Mozilla and compatible
	$browser['is_firefox'] = strpos($ua, 'Gecko/') !== false;				// Firefox says "Gecko/20xx", not "like Gecko"

	// Internet Explorer is often "emulated".
	$browser['is_ie'] = $is_ie = !$browser['is_opera'] && !$browser['is_gecko'] && strpos($ua, 'MSIE') !== false;

	// Retrieve the version number, as a floating point.
	preg_match('~' . ($browser['is_opera'] || $browser['is_safari'] ?
		'version[/ ]' : ($browser['is_firefox'] ?
		'firefox/' : ($browser['is_ie'] ?
		'msie ' : ($browser['is_chrome'] ?
		'chrom(?:e|ium)/' : 'applewebkit/')))) . '([\d.]+)~i', $ua, $ver)
	|| preg_match('~' . ($browser['is_opera'] ? 'opera[/ ]' : 'version[/ ]') . '([\d.]+)~i', $ua, $ver);
	$browser['version'] = $ver = isset($ver[1]) ? (float) $ver[1] : 0;

	$browser['is_ie8down'] = $is_ie && $ver <= 8;
	for ($i = 6; $i <= 10; $i++)
		$browser['is_ie' . $i] = $is_ie && $ver == $i;

	// Store our browser name...
	$browser['agent'] = '';
	foreach (array('opera', 'chrome', 'iphone', 'tablet', 'android', 'safari', 'webkit', 'firefox', 'gecko', 'ie6', 'ie7', 'ie8', 'ie9', 'ie10') as $agent)
	{
		if ($browser['is_' . $agent])
		{
			$browser['agent'] = $agent;
			break;
		}
	}

	// This isn't meant to be reliable, it's just meant to catch most bots to prevent PHPSESSID from showing up.
	$browser['possibly_robot'] = !empty($user_info['possibly_robot']);

	// Robots shouldn't be logging in or registering. So, they aren't a bot. Better to be wrong than sorry (or people won't be able to log in!), anyway.
	if ((isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('login', 'login2', 'register'))) || empty($user_info['is_guest']))
		$browser['possibly_robot'] = false;

	// A small reference to the usual place...
	$context['browser'] =& $browser;

	call_hook('detect_browser');
}

/**
 * Load all the details of a theme, given its ID.
 *
 * - Identify the theme to be loaded, from parameter or an external source: theme parameter in the URL, previously theme parameter in the URL and now in session, the user's preference, a board specific theme, and lastly the forum's default theme.
 * - Validate that the supplied theme is a valid id and that permission to use such theme (e.g. admin allows users to choose own theme, etc) is available.
 * - Load data from the themes table for this theme, both the user's preferences for this theme, plus the global settings for it, and load into $settings and $options respectively ($settings for theme settings/global settings, $options for user's specific settings within this theme)
 * - Save details to cache as appropriate.
 * - Prepare the list of folders to examine in priority for template loading (i.e. this theme's folder first, then default, but can include others)
 * - Identify if the user has come to the board from the wrong place (e.g. a www in the URL that shouldn't be there) so it can be fixed.
 * - Push common details into $context['user'] for the current user.
 * - Identify what smiley set should be used.
 * - Initialize $context['header'] and $context['footer'] for later use, as well as some $settings paths, some global $context values, $txt initially.
 * - Set up common server-side settings for later reference (in case of server configuration specific tweaks)
 * - Ensure the forum name is the first item in the link tree.
 * - Load the wireless template or the XML template if that is what we are going to use, otherwise load the index template (plus any templates the theme has specified it uses), and do not initialise template layers if we are using a 'simple' action that does not need them.
 * - Initialize the theme by calling the init block.
 * - Load any theme specific language files.
 * - See if scheduled tasks need to be loaded, if so add the call into the HTML header so they will be triggered next page load.
 * - Call the load_theme hook.
 */
function loadTheme($id_theme = 0, $initialize = true)
{
	global $user_info, $user_settings, $board_info, $sc, $boarddir, $footer_coding;
	global $txt, $boardurl, $scripturl, $mbname, $modSettings, $language;
	global $context, $settings, $options, $ssi_theme;

	// The theme was specified by parameter.
	if (!empty($id_theme))
		$id_theme = (int) $id_theme;
	// The theme was specified by REQUEST.
	elseif (!empty($_REQUEST['theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
	{
		$th = explode('_', $_REQUEST['theme']);
		$id_theme = (int) $th[0];
		$skin = isset($th[1]) ? base64_decode($th[1]) : '';
		$_SESSION['id_theme'] = $id_theme;
		$_SESSION['skin'] = $skin;
	}
	// The theme was specified by REQUEST... previously.
	elseif (!empty($_SESSION['id_theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
	{
		$id_theme = (int) $_SESSION['id_theme'];
		$skin = !empty($_SESSION['skin']) ? $_SESSION['skin'] : '';
	}
	// The theme is just the user's choice. (Might use ?board=1;theme=0 to force board theme.)
	elseif (!empty($user_info['theme']) && !isset($_REQUEST['theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
	{
		$id_theme = $user_info['theme'];
		$skin = $user_info['skin'];
	}
	// The theme was specified by the board.
	elseif (!empty($board_info['theme']))
	{
		$id_theme = $board_info['theme'];
		$skin = isset($board_info['skin']) ? $board_info['skin'] : '';
	}
	// The theme is the forum's default.
	else
	{
		$id_theme = $modSettings['theme_guests'];
		$skin = $modSettings['theme_skin_guests'];
	}

	// Verify the id_theme... no foul play.
	// Always allow the board specific theme, if they are overriding.
	if (!empty($board_info['theme']) && $board_info['override_theme'])
	{
		$id_theme = $board_info['theme'];
		$skin = isset($board_info['skin']) ? $board_info['skin'] : '';
	}
	// If they have specified a particular theme to use with SSI allow it to be used.
	elseif (!empty($ssi_theme) && $id_theme == $ssi_theme)
		$id_theme = (int) $id_theme;
	elseif (!empty($modSettings['knownThemes']) && !allowedTo('admin_forum'))
	{
		$themes = explode(',', $modSettings['knownThemes']);
		$id_theme = in_array($id_theme, $themes) ? (int) $id_theme : $modSettings['theme_guests'];
	}
	else
		$id_theme = (int) $id_theme;

	// Time to determine our CSS list...
	// First, load our requested skin folder.
	$context['skin'] = empty($skin) ?
		(empty($id_theme) ? $modSettings['theme_skin_guests'] : 'skins') :
		($skin === 'skins' || strpos($skin, 'skins/') === 0 ? '' : 'skins/') . $skin;
	$folders = explode('/', $context['skin']);
	$context['css_folders'] = array();
	$current_folder = '';
	foreach ($folders as $folder)
	{
		$current_folder .= '/' . $folder;
		$context['css_folders'][] = substr($current_folder, 1);
	}

	// Then, we need to list the CSS files that will be part of our main CSS file.
	$context['css_main_files'] = array('index', 'sections');
	$context['css_suffixes'] = array();

	$member = empty($user_info['id']) ? -1 : $user_info['id'];

	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2 && ($temp = cache_get_data('theme_settings-' . $id_theme . ':' . $member, 60)) != null && time() - 60 > $modSettings['settings_updated'])
	{
		$themeData = $temp;
		$flag = true;
	}
	elseif (($temp = cache_get_data('theme_settings-' . $id_theme, 90)) != null && time() - 60 > $modSettings['settings_updated'])
		$themeData = $temp + array($member => array());
	else
		$themeData = array(-1 => array(), 0 => array(), $member => array());

	if (empty($flag))
	{
		// Load variables from the current or default theme, global or this user's.
		$result = wesql::query('
			SELECT variable, value, id_member, id_theme
			FROM {db_prefix}themes
			WHERE id_member' . (empty($themeData[0]) ? ' IN (-1, 0, {int:id_member})' : ' = {int:id_member}') . '
				AND id_theme' . ($id_theme == 1 ? ' = {int:id_theme}' : ' IN ({int:id_theme}, 1)'),
			array(
				'id_theme' => $id_theme,
				'id_member' => $member,
			)
		);
		// Pick between $settings and $options depending on whose data it is.
		while ($row = wesql::fetch_assoc($result))
		{
			// There are just things we shouldn't be able to change as members.
			if ($row['id_member'] != 0 && in_array($row['variable'], array('actual_theme_url', 'actual_images_url', 'base_theme_dir', 'base_theme_url', 'default_images_url', 'default_theme_dir', 'default_theme_url', 'default_template', 'images_url', 'number_recent_posts', 'smiley_sets_default', 'theme_dir', 'theme_id', 'theme_templates', 'theme_url')))
				continue;

			// If this is the theme_dir of the default theme, store it.
			if (in_array($row['variable'], array('theme_dir', 'theme_url', 'images_url')) && $row['id_theme'] == '1' && empty($row['id_member']))
				$themeData[0]['default_' . $row['variable']] = $row['value'];

			// If this isn't set yet, is a theme option, or is not the default theme..
			if (!isset($themeData[$row['id_member']][$row['variable']]) || $row['id_theme'] != '1')
				$themeData[$row['id_member']][$row['variable']] = substr($row['variable'], 0, 5) == 'show_' ? $row['value'] == '1' : $row['value'];
		}
		wesql::free_result($result);

		if (!empty($themeData[-1]))
			foreach ($themeData[-1] as $k => $v)
				if (!isset($themeData[$member][$k]))
					$themeData[$member][$k] = $v;

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('theme_settings-' . $id_theme . ':' . $member, $themeData, 60);
		// Only if we didn't already load that part of the cache...
		elseif (!isset($temp))
			cache_put_data('theme_settings-' . $id_theme, array(-1 => $themeData[-1], 0 => $themeData[0]), 90);
	}

	$settings = $themeData[0];
	$options = $themeData[$member];

	$settings['theme_id'] = $id_theme;

	$settings['actual_theme_url'] = $settings['theme_url'];
	$settings['actual_images_url'] = $settings['images_url'];
	$settings['actual_theme_dir'] = $settings['theme_dir'];

	$settings['template_dirs'] = array();
	// This theme first.
	$settings['template_dirs'][] = $settings['theme_dir'];

	// Based on theme (if there is one).
	if (!empty($settings['base_theme_dir']))
		$settings['template_dirs'][] = $settings['base_theme_dir'];

	// Lastly the default theme.
	if ($settings['theme_dir'] != $settings['default_theme_dir'])
		$settings['template_dirs'][] = $settings['default_theme_dir'];

	if (!$initialize)
		return;

	// Check to see if they're accessing it from the wrong place.
	if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME']))
	{
		$detected_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 'https://' : 'http://';
		$detected_url .= empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
		$temp = preg_replace('~/' . basename($scripturl) . '(/.+)?$~', '', strtr(dirname($_SERVER['PHP_SELF']), '\\', '/'));
		if ($temp != '/')
			$detected_url .= $temp;
	}
	if (isset($detected_url) && $detected_url != $boardurl)
	{
		// Try #1 - check if it's in a list of alias addresses
		if (!empty($modSettings['forum_alias_urls']))
		{
			$aliases = explode(',', $modSettings['forum_alias_urls']);

			foreach ($aliases as $alias)
			{
				// Rip off all the boring parts, spaces, etc.
				if ($detected_url == trim($alias) || strtr($detected_url, array('http://' => '', 'https://' => '')) == trim($alias))
					$do_fix = true;
			}
		}

		// Hmm... check #2 - is it just different by a www? Send them to the correct place!!
		if (empty($do_fix) && strtr($detected_url, array('://' => '://www.')) == $boardurl && (empty($_GET) || count($_GET) == 1) && WEDGE != 'SSI')
		{
			// Okay, this seems weird, but we don't want an endless loop - this will make $_GET not empty ;)
			if (empty($_GET))
				redirectexit('wwwRedirect');
			else
			{
				list ($k, $v) = each($_GET);

				if ($k != 'wwwRedirect')
					redirectexit('wwwRedirect;' . $k . '=' . $v);
			}
		}

		// #3 is just a check for SSL...
		if (strtr($detected_url, array('https://' => 'http://')) == $boardurl)
			$do_fix = true;

		// Okay, #4 - perhaps it's an IP address? We're gonna want to use that one, then. (assuming it's the IP or something...)
		if (!empty($do_fix) || preg_match('~^http[s]?://(?:[\d.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~', $detected_url) == 1)
		{
			// Caching is good ;)
			$oldurl = $boardurl;

			// Fix $boardurl and $scripturl
			$boardurl = $detected_url;
			$scripturl = strtr($scripturl, array($oldurl => $boardurl));
			$_SERVER['REQUEST_URL'] = strtr($_SERVER['REQUEST_URL'], array($oldurl => $boardurl));

			// Fix the theme urls...
			$settings['theme_url'] = strtr($settings['theme_url'], array($oldurl => $boardurl));
			$settings['default_theme_url'] = strtr($settings['default_theme_url'], array($oldurl => $boardurl));
			$settings['actual_theme_url'] = strtr($settings['actual_theme_url'], array($oldurl => $boardurl));
			$settings['images_url'] = strtr($settings['images_url'], array($oldurl => $boardurl));
			$settings['default_images_url'] = strtr($settings['default_images_url'], array($oldurl => $boardurl));
			$settings['actual_images_url'] = strtr($settings['actual_images_url'], array($oldurl => $boardurl));

			// And just a few mod settings :)
			$modSettings['smileys_url'] = strtr($modSettings['smileys_url'], array($oldurl => $boardurl));
			$modSettings['avatar_url'] = strtr($modSettings['avatar_url'], array($oldurl => $boardurl));

			// Clean up after loadBoard()
			if (isset($board_info['moderators']))
			{
				foreach ($board_info['moderators'] as $k => $dummy)
				{
					$board_info['moderators'][$k]['href'] = strtr($dummy['href'], array($oldurl => $boardurl));
					$board_info['moderators'][$k]['link'] = strtr($dummy['link'], array('"' . $oldurl => '"' . $boardurl));
				}
			}
			foreach ($context['linktree'] as $k => $dummy)
				$context['linktree'][$k]['url'] = strtr($dummy['url'], array($oldurl => $boardurl));
		}
	}
	// Set up the contextual user array
	$context['user'] = array(
		'id' => $user_info['id'],
		'is_logged' => !$user_info['is_guest'],
		'is_guest' => &$user_info['is_guest'],
		'is_admin' => &$user_info['is_admin'],
		'is_mod' => &$user_info['is_mod'],
		// A user can mod if they have permission to see the mod center, or they are a board/group/approval moderator.
		'can_mod' => allowedTo('access_mod_center') || (!$user_info['is_guest'] && ($user_info['mod_cache']['gq'] != '0=1' || $user_info['mod_cache']['bq'] != '0=1' || ($modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap'])))),
		'username' => $user_info['username'],
		'language' => $user_info['language'],
		'email' => $user_info['email'],
		'ignoreusers' => $user_info['ignoreusers'],
	);
	if (!$context['user']['is_guest'])
		$context['user']['name'] = $user_info['name'];
	elseif ($context['user']['is_guest'] && !empty($txt['guest_title']))
		$context['user']['name'] = $txt['guest_title'];

	// Determine the current smiley set
	$user_info['smiley_set'] = (!in_array($user_info['smiley_set'], explode(',', $modSettings['smiley_sets_known'])) && $user_info['smiley_set'] != 'none') || empty($modSettings['smiley_sets_enable']) ? (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default']) : $user_info['smiley_set'];
	$context['user']['smiley_set'] = $user_info['smiley_set'];

	// Some basic information...
	if (!isset($context['header']))
		$context['header'] = '';
	if (!isset($context['footer']))
		$context['footer'] = '';
	if (!isset($context['footer_js']))
		$context['footer_js'] = '';
	if (!isset($context['footer_js_inline']))
		$context['footer_js_inline'] = '';

	// Specifies that the JavaScript footer section is currently
	// open for sending JS code without <script> tags.
	$footer_coding = true;

	$context['menu_separator'] = !empty($settings['use_image_buttons']) ? ' ' : ' | ';
	$context['session_var'] = $_SESSION['session_var'];
	$context['session_id'] = $_SESSION['session_value'];
	$context['session_query'] = $context['session_var'] . '=' . $context['session_id'];
	$context['forum_name'] = $mbname;
	$context['forum_name_html_safe'] = westr::htmlspecialchars($context['forum_name']);
	$context['header_logo_url_html_safe'] = empty($settings['header_logo_url']) ? $context['forum_name_html_safe']
		: '<img src="' . westr::htmlspecialchars($settings['header_logo_url']) . '" alt="' . $context['forum_name'] . '">';
	$context['site_slogan'] = empty($settings['site_slogan']) ? '<div id="wedgelogo"></div>' : '<div id="siteslogan">' . $settings['site_slogan'] . '</div>';
	$context['current_action'] = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
	$context['current_subaction'] = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : null;
	if (isset($modSettings['load_average']))
		$context['load_average'] = $modSettings['load_average'];

	// Set some permission related settings
	$context['show_login_bar'] = $user_info['is_guest'] && !empty($modSettings['enableVBStyleLogin']);

	// This determines the server... not used in many places, except for login fixing.
	$context['server'] = array(
		'is_iis' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false,
		'is_apache' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false,
		'is_litespeed' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false,
		'is_lighttpd' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false,
		'is_nginx' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false,
		'is_cgi' => isset($_SERVER['SERVER_SOFTWARE']) && strpos(php_sapi_name(), 'cgi') !== false,
		'is_windows' => strpos(PHP_OS, 'WIN') === 0,
		'iso_case_folding' => ord(strtolower(chr(138))) === 154,
	);
	// A bug in some versions of IIS under CGI (older ones) makes cookie setting not work with Location: headers.
	$context['server']['needs_login_fix'] = $context['server']['is_cgi'] && $context['server']['is_iis'];

	// Detect the browser. This is separated out because it's also used in attachment downloads.
	detectBrowser();

	// Set the top level linktree up
	array_unshift($context['linktree'], array(
		'url' => $scripturl,
		'name' => $context['forum_name_html_safe']
	));

	if (!isset($txt))
		$txt = array();

	// These simple actions will skip the index template entirely. Don't use macros in their templates!
	$simpleActions = array(
		'findmember',
		'printpage',
		'spellcheck',
	);

	// Initializing the Wedge templating magic.
	$context['macros'] = array();
	$context['skeleton'] = '';
	$context['skeleton_array'] = array();
	$context['layer_hints'] = array();
	$context['layers'] = array();

	// Wireless mode? Load up the wireless stuff.
	if (WIRELESS)
	{
		loadTemplate('Wireless');
		loadLanguage('Wireless+index+Modifications');
		hideChrome(WIRELESS_PROTOCOL);
	}
	// Output is fully XML or a simple action?
	elseif (isset($_REQUEST['xml']) || !empty($_REQUEST['action']) && in_array($_REQUEST['action'], $simpleActions))
	{
		if (isset($_REQUEST['xml']))
			loadTemplate('Xml');
		loadLanguage('index+Modifications');
		hideChrome();
	}
	else
	{
		// Custom templates to load, or just default?
		if (isset($settings['theme_templates']))
			$templates = explode(',', $settings['theme_templates']);
		else
			$templates = array('index');

		// Load each template...
		foreach ($templates as $template)
			loadTemplate($template);

		// ...and attempt to load their associated language files.
		$required_files = implode('+', array_merge($templates, array('Modifications')));
		loadLanguage($required_files, '', false);

		// Initialize our JS files to cache right before we run template_init().
		if (empty($modSettings['jquery_origin']) || $modSettings['jquery_origin'] === 'local')
			$context['javascript_files'] = array('scripts/jquery-1.5.2.js', 'scripts/script.js');
		else
		{
			$remote = array(
				'google' =>		'http://ajax.googleapis.com/ajax/libs/jquery/1.5.2/jquery.min.js',
				'jquery' =>		'http://code.jquery.com/jquery-1.5.2.min.js',
				'microsoft' =>	'http://ajax.aspnetcdn.com/ajax/jQuery/jquery-1.5.2.min.js',
			);
			$context['remote_javascript_files'] = array($remote[$modSettings['jquery_origin']]);
			$context['javascript_files'] = array('scripts/script.js');
		}

		// Initialize the theme and load the default macros.
		execBlock('init', 'ignore');

		// Now we initialize the search/replace pairs for macros.
		// They can be overloaded in a skin's skin.xml file.
		if (!empty($settings['macros']))
		{
			foreach ($settings['macros'] as $name => $contents)
			{
				if (is_array($contents))
					$contents = isset($contents[$context['browser']['agent']]) ? $contents[$context['browser']['agent']] :
							(isset($contents['else']) ? $contents['else'] : '{body}');

				$context['macros'][$name] = array(
					'has_if' => strpos($contents, '<if:') !== false,
					'body' => str_replace(array('{scripturl}'), array($scripturl), trim($contents)),
				);
			}
		}

		// Now we'll override all of these...
		loadSource('Subs-Cache');
		wedge_get_skin_options();

		// Did we find an override for the skeleton? If not, load the default one.
		if (empty($context['skeleton']))
			execBlock('skeleton', 'ignore');

		// Now we have a $context['skeleton'] (original or overridden), we can turn it into an array.
		preg_match_all('~<(?!!)(/)?([\w:,]+)(\s*/)?\>~', $context['skeleton'], $match, PREG_SET_ORDER);
		build_skeleton($match, $context['skeleton_array']);
	}

	// Guests may still need a name
	if ($context['user']['is_guest'] && empty($context['user']['name']))
		$context['user']['name'] = $txt['guest_title'];

	// Any theme-related strings that need to be loaded?
	if (!empty($settings['require_theme_strings']))
		loadLanguage('ThemeStrings', '', false);

	// Allow overriding the board wide time/number formats.
	if (empty($user_settings['time_format']) && !empty($txt['time_format']))
		$user_info['time_format'] = $txt['time_format'];

	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'always')
	{
		$settings['theme_url'] = $settings['default_theme_url'];
		$settings['images_url'] = $settings['default_images_url'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}
	// Make a special URL for the language.
	// !!! $txt['image_lang'] isn't defined anywhere...
	$settings['lang_images_url'] = $settings['images_url'] . '/' . (!empty($txt['image_lang']) ? $txt['image_lang'] : $user_info['language']);

	// Set the character set from the template.
	$context['right_to_left'] = !empty($txt['lang_rtl']);

	// Add Webkit fixes -- there are so many popular browsers based on it.
	if ($context['browser']['is_webkit'] && !empty($context['browser']['agent']) && $context['browser']['agent'] !== 'webkit')
		$context['css_suffixes'][] = 'webkit';

	// Add any potential browser-based fixes.
	if (!empty($context['browser']['agent']))
		$context['css_suffixes'][] = $context['browser']['agent'];

	// RTL languages require an additional stylesheet.
	if ($context['right_to_left'])
		$context['css_suffixes'][] = 'rtl';

	// We also have a special stylesheet for guests/members. May become useful.
	$context['css_suffixes'][] = $user_info['is_guest'] ? 'guest' : 'member';

	$context['tabindex'] = 1;

	// If we think we have mail to send, let's offer up some possibilities... robots get pain (Now with scheduled task support!)
	if ((!empty($modSettings['mail_next_send']) && $modSettings['mail_next_send'] < time() && empty($modSettings['mail_queue_use_cron'])) || empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
	{
		if ($context['browser']['possibly_robot'])
		{
			//!!! Maybe move this somewhere better?!
			loadSource('ScheduledTasks');

			// What to do, what to do?!
			if (empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
				AutoTask();
			else
				ReduceMailQueue();
		}
		else
		{
			$type = empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time() ? 'task' : 'mailq';
			$ts = $type == 'mailq' ? $modSettings['mail_next_send'] : $modSettings['next_task_time'];

			add_js('
	function weAutoTask()
	{
		var tempImage = new Image();
		tempImage.src = "' . $scripturl . '?scheduled=' . $type . ';ts=' . $ts . '";
	}
	setTimeout(weAutoTask, 1);');
		}
	}

	// What about any straggling imperative tasks?
	if (empty($modSettings['next_imperative']))
	{
		loadSource('Subs-Scheduled');
		recalculateNextImperative();
	}

	if ($modSettings['next_imperative'] < time())
		add_js('
	function weImperativeTask()
	{
		var tempImage = new Image();
		tempImage.src = "' . $scripturl . '?imperative;ts=' . time() . '";
	}
	setTimeout(weImperativeTask, 1);');

	// Any files to include at this point?
	if (!empty($modSettings['integrate_theme_include']))
	{
		$theme_includes = explode(',', $modSettings['integrate_theme_include']);
		foreach ($theme_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Call load theme hook.
	call_hook('load_theme');

	// We are ready to go.
	$context['theme_loaded'] = true;
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
 * Loads a named source file for later use.
 *
 * This function does not do any error handling as if this breaks, something is usually seriously wrong that error catching isn't going to solve.
 *
 * @param mixed $source_name Either a string holding the name of a file in the source directory, or an array of the same, without .php extension to load.
 */
function loadSource($source_name)
{
	global $sourcedir;

	foreach ((array) $source_name as $file)
		require_once($sourcedir . '/' . $file . '.php');
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
	foreach ((array) $target as $layer)
	{
		// Is the target layer wishful thinking?
		if ($layer[0] === ':' && isset($context['layer_hints'][substr($layer, 1)]))
			$to = $context['layer_hints'][substr($layer, 1)];
		elseif (isset($context['layers'][$layer]))
			$to = $layer;
		if (isset($to))
			break;
	}
	// Don't bother with non-main elements in Wireless and XML modes.
	if (empty($to) && (WIRELESS || isset($_REQUEST['xml'])))
		return;

	$target = empty($to) ? ($where === 'before' || $where === 'after' ? (is_array($target) ? reset($target) : $target) : 'context') : $to;

	// If a mod requests to replace the contents of the sidebar, just smile politely.
	if (($where === 'replace' || $where === 'erase') && $target === (isset($context['layer_hints']['side']) ? $context['layer_hints']['side'] : 'sidebar'))
		$where = 'add';

	if ($where === 'replace' || $where === 'erase')
	{
		$has_arrays = false;
		if ($where === 'replace' && isset($context['layers'][$target]))
			foreach ($context['layers'][$target] as $item)
				$has_arrays |= is_array($item);

		// Most likely case: no child layers (or erase all). Replace away!
		if (!$has_arrays)
		{
			$context['layers'][$target] = $blocks;
			// If we erase, we might have to deleted layer entries.
			if ($where === 'erase')
				build_skeleton_indexes();
			return;
		}
		// Otherwise, we're in for some fun... :-/
		$keys = array_keys($context['layers'][$target]);
		foreach ($keys as $id)
		{
			$item =& $context['layers'][$target][$id];
			if (!is_array($item))
			{
				// We're going to insert our block(s) right before the first block we find...
				if (!isset($offset))
				{
					$val = array_values($context['layers'][$target]);
					$offset = array_search($id, $keys, true);
					array_splice($keys, $offset, 0, array_keys($blocks));
					array_splice($val, $offset, 0, array_fill(0, count($blocks), true));
					$context['layers'][$target] = array_combine($keys, $val);
				}
				// ...And then we delete the other block(s) and leave the layers where they are.
				unset($context['layers'][$target][$id]);
			}
		}

		// So, we found a layer but no blocks..? Add our blocks at the end.
		if (!isset($offset))
			$context['layers'][$target] += $blocks;
		build_skeleton_indexes();
	}
	elseif ($where === 'add')
		$context['layers'][$target] = array_merge($blocks, $context['layers'][$target]);
	elseif ($where === 'first')
		$context['layers'][$target] = array_merge(array_reverse($blocks), $context['layers'][$target]);
	elseif ($where === 'before' || $where === 'after')
	{
		foreach ($context['layers'] as &$layer)
		{
			if (isset($layer[$target]))
			{
				$keys = array_keys($layer);
				$val = array_values($layer);
				$offset = array_search($target, $keys) + ($where === 'after' ? 1 : 0);
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

	if ($target === 'context' || $where === 'parent' || $where === 'before' || $where === 'after')
	{
		skeleton_insert_layer($layer, $target, $target === 'context' ? 'parent' : $where);
		return;
	}
	elseif ($where === 'child')
	{
		$context['layers'][$target] = array($layer => $context['layers'][$target]);
		$context['layers'][$layer] =& $context['layers'][$target][$layer];
		return;
	}
	elseif ($where === 'replace' || $where === 'erase')
	{
		skeleton_insert_layer($layer, $target, $where);
		unset($context['layers'][$target]);
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

/**
 * Attempt to load a language file.
 * Tries the current and default themes as well as the user and global languages.
 *
 * If full debugging is enabled, loads of language files will be logged too.
 *
 * @param string $template_name The name of the language file to load, without any .{language}.php prefix, e.g. 'Errors' or 'Who'.
 * @param string $lang Specifies the language to attempt to load; if not specified (or empty), load it in the current user's default language.
 * @param bool $fatal Whether to issue a fatal error in the event the language file could not be loaded.
 * @param bool $force_reload Whether to reload the language file even if previously loaded before.
 * @return string The name of the language from which the loaded language file was taken.
 */
function loadLanguage($template_name, $lang = '', $fatal = true, $force_reload = false)
{
	global $user_info, $language, $settings, $context, $modSettings;
	global $cachedir, $db_show_debug, $txt;
	static $already_loaded = array();

	// Default to the user's language.
	if ($lang == '')
		$lang = isset($user_info['language']) ? $user_info['language'] : $language;

	// Do we want the English version of language file as fallback?
	if (empty($modSettings['disable_language_fallback']) && $lang !== 'english')
		loadLanguage($template_name, 'english', false);

	if (!$force_reload && isset($already_loaded[$template_name]) && $already_loaded[$template_name] == $lang)
		return $lang;

	// Make sure we have $settings - if not we're in trouble and need to find it!
	if (empty($settings['default_theme_dir']))
	{
		loadSource('ScheduledTasks');
		loadEssentialThemeData();
	}

	// What theme are we in?
	$theme_name = basename($settings['theme_url']);
	if (empty($theme_name))
		$theme_name = 'unknown';

	// For each file open it up and write it out!
	foreach (explode('+', $template_name) as $template)
	{
		// Obviously, the current theme is most important to check.
		$attempts = array(
			array($settings['theme_dir'], $template, $lang, $settings['theme_url']),
			array($settings['theme_dir'], $template, $language, $settings['theme_url']),
		);

		// Do we have a base theme to worry about?
		if (isset($settings['base_theme_dir']))
		{
			$attempts[] = array($settings['base_theme_dir'], $template, $lang, $settings['base_theme_url']);
			$attempts[] = array($settings['base_theme_dir'], $template, $language, $settings['base_theme_url']);
		}

		// Fall back on the default theme if necessary.
		$attempts[] = array($settings['default_theme_dir'], $template, $lang, $settings['default_theme_url']);
		$attempts[] = array($settings['default_theme_dir'], $template, $language, $settings['default_theme_url']);

		// Fall back on the English language if none of the preferred languages can be found.
		if (!in_array('english', array($lang, $language)))
		{
			$attempts[] = array($settings['theme_dir'], $template, 'english', $settings['theme_url']);
			$attempts[] = array($settings['default_theme_dir'], $template, 'english', $settings['default_theme_url']);
		}

		// Try to find the language file.
		$found = false;
		foreach ($attempts as $k => $file)
		{
			if (file_exists($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php'))
			{
				// Include it!
				template_include($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php');

				// Note that we found it.
				$found = true;

				break;
			}
		}

		// That couldn't be found! Log the error, but *try* to continue normally.
		if (!$found && $fatal)
		{
			log_error(sprintf($txt['theme_language_error'], $template_name . '.' . $lang, 'template'));
			break;
		}

		// The index language file contains the locale. If that's what we're loading, we're changing time locales, so reload that.
		if ($found && $template === 'index')
			$user_info['setlocale'] = setlocale(LC_TIME, $txt['lang_locale'] . '.utf-8', $txt['lang_locale'] . '.utf8');
	}

	// Keep track of what we're up to soldier.
	if ($db_show_debug === true)
		$context['debug']['language_files'][] = $template_name . '.' . $lang . ' (' . $theme_name . ')';

	// Remember what we have loaded, and in which language.
	$already_loaded[$template_name] = $lang;

	// Return the language actually loaded.
	return $lang;
}

/**
 * Get all parent boards (requires first parent as parameter)
 * From a given board, iterate up through the board hierarchy to find all of the parents back to forum root.
 *
 * Upon iterating up through the board hierarchy, the board's URL, name, depth and list of moderators will be provided upon return.
 *
 * @param int $id_parent The id of a board; this should only be called with the current board's id, the function will iterate itself until reaching the top level and does not require support with a list of boards to step through.
 * @return array The result of iterating through the board hierarchy; the order of boards should be deepest first.
 */
function getBoardParents($id_parent)
{
	global $scripturl;

	$boards = array();

	// First check if we have this cached already.
	if (($boards = cache_get_data('board_parents-' . $id_parent, 480)) === null)
	{
		$boards = array();
		$original_parent = $id_parent;

		// Loop while the parent is non-zero.
		while ($id_parent != 0)
		{
			$result = wesql::query('
				SELECT
					b.id_parent, b.name, {int:board_parent} AS id_board, IFNULL(mem.id_member, 0) AS id_moderator,
					mem.real_name, b.child_level
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
				WHERE b.id_board = {int:board_parent}',
				array(
					'board_parent' => $id_parent,
				)
			);
			// In the EXTREMELY unlikely event this happens, give an error message.
			if (wesql::num_rows($result) == 0)
				fatal_lang_error('parent_not_found', 'critical');
			while ($row = wesql::fetch_assoc($result))
			{
				if (!isset($boards[$row['id_board']]))
				{
					$id_parent = $row['id_parent'];
					$boards[$row['id_board']] = array(
						'url' => $scripturl . '?board=' . $row['id_board'] . '.0',
						'name' => $row['name'],
						'level' => $row['child_level'],
						'moderators' => array()
					);
				}
				// If a moderator exists for this board, add that moderator for all children too.
				if (!empty($row['id_moderator']))
					foreach ($boards as $id => $dummy)
					{
						$boards[$id]['moderators'][$row['id_moderator']] = array(
							'id' => $row['id_moderator'],
							'name' => $row['real_name'],
							'href' => $scripturl . '?action=profile;u=' . $row['id_moderator'],
							'link' => '<a href="' . $scripturl . '?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
						);
					}
			}
			wesql::free_result($result);
		}

		cache_put_data('board_parents-' . $original_parent, $boards, 480);
	}

	return $boards;
}

/**
 * Attempt to (re)load the list of known language packs.
 *
 * @param bool $use_cache Whether to cache the results of searching the language folders for index.{language}.php files.
 * @return array Returns an array, one element per known language pack, with: name (capitalized name of language pack), selected (bool whether this is the current language), filename (the raw language code, e.g. english_british-utf8), location (full system path to the index.{language}.php file) - this is all part of $context['languages'] too.
 */
function getLanguages($use_cache = true)
{
	global $context, $settings, $modSettings;

	// Either we don't use the cache, or it's expired.
	if (!$use_cache || ($context['languages'] = cache_get_data('known_languages', !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600)) == null)
	{
		// If we don't have our theme information yet, let's get it.
		if (empty($settings['default_theme_dir']))
			loadTheme(0, false);

		// Default language directories to try.
		$language_directories = array(
			$settings['default_theme_dir'] . '/languages',
			$settings['actual_theme_dir'] . '/languages',
		);

		// We possibly have a base theme directory.
		if (!empty($settings['base_theme_dir']))
			$language_directories[] = $settings['base_theme_dir'] . '/languages';

		// Remove any duplicates.
		$language_directories = array_unique($language_directories);

		foreach ($language_directories as $language_dir)
		{
			// Can't look in here... doesn't exist!
			if (!file_exists($language_dir))
				continue;

			$dir = dir($language_dir);
			while ($entry = $dir->read())
			{
				// Look for the index language file....
				if (!preg_match('~^index\.(.+)\.php$~', $entry, $matches))
					continue;

				$context['languages'][$matches[1]] = array(
					'name' => westr::ucwords(strtr($matches[1], array('_' => ' '))),
					'selected' => false,
					'filename' => $matches[1],
					'location' => $language_dir . '/index.' . $matches[1] . '.php',
				);

			}
			$dir->close();
		}

		// Let's cash in on this deal.
		if (!empty($modSettings['cache_enable']))
			cache_put_data('known_languages', $context['languages'], !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);
	}

	return $context['languages'];
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
 * Attempt to start the session.
 *
 * There are multiple other parts here too.
 * - Attempt to change some PHP settings (ensure cookies are enabled, but that cookies are not the only access method; disables PHP's auto tag rewriter to include sessions; turn off transparent session support; ensure normal ampersand separators for URL components)
 * - Set cookies to be global if that's what configuration dictates.
 * - Check if the session was started (e.g. session.auto_start) and attempt to close it if possible.
 * - Check for people using invalid PHPSESSIDs
 * - Enable database-based sessions and override PHP's own handler.
 * - Set the session code randomly.
 */
function loadSession()
{
	global $HTTP_SESSION_VARS, $modSettings, $boardurl, $sc;

	// Attempt to change a few PHP settings.
	@ini_set('session.use_cookies', true);
	@ini_set('session.use_only_cookies', false);
	@ini_set('url_rewriter.tags', '');
	@ini_set('session.use_trans_sid', false);
	@ini_set('arg_separator.output', '&amp;');

	if (!empty($modSettings['globalCookies']))
	{
		$parsed_url = parse_url($boardurl);

		if (preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) == 0 && preg_match('~(?:[^.]+\.)?([^.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
			@ini_set('session.cookie_domain', '.' . $parts[1]);
	}
	// !!! Set the session cookie path?

	// If it's already been started... probably best to skip this.
	if ((@ini_get('session.auto_start') == 1 && !empty($modSettings['databaseSession_enable'])) || session_id() == '')
	{
		// Attempt to end the already-started session.
		if (@ini_get('session.auto_start') == 1)
			@session_write_close();

		// This is here to stop people from using bad junky PHPSESSIDs.
		if (isset($_REQUEST[session_name()]) && preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $_REQUEST[session_name()]) == 0 && !isset($_COOKIE[session_name()]))
		{
			$session_id = md5(md5('we_sess_' . time()) . mt_rand());
			$_REQUEST[session_name()] = $session_id;
			$_GET[session_name()] = $session_id;
			$_POST[session_name()] = $session_id;
		}

		// Use database sessions?
		if (!empty($modSettings['databaseSession_enable']))
		{
			session_set_save_handler('sessionOpen', 'sessionClose', 'sessionRead', 'sessionWrite', 'sessionDestroy', 'sessionGC');
			@ini_set('session.gc_probability', '1');
		}
		elseif (@ini_get('session.gc_maxlifetime') <= 1440 && !empty($modSettings['databaseSession_lifetime']))
			@ini_set('session.gc_maxlifetime', max($modSettings['databaseSession_lifetime'], 60));

		// Use cache setting sessions?
		if (empty($modSettings['databaseSession_enable']) && !empty($modSettings['cache_enable']) && php_sapi_name() != 'cli')
		{
			if (function_exists('eaccelerator_set_session_handlers'))
				eaccelerator_set_session_handlers();
		}

		session_start();

		// Change it so the cache settings are a little looser than default.
		if (!empty($modSettings['databaseSession_loose']))
			header('Cache-Control: private');
	}

	// Set the randomly generated code.
	if (!isset($_SESSION['session_var']))
	{
		$_SESSION['session_value'] = md5(session_id() . mt_rand());
		$_SESSION['session_var'] = substr(preg_replace('~^\d+~', '', sha1(mt_rand() . session_id() . mt_rand())), 0, rand(7, 12));
	}
	$sc = $_SESSION['session_value'];
}

/**
 * Part of the PHP Session API, this function is intended to apply when creating a session.
 *
 * @param string $save_path Normally the path that would be used in creating a session. Not applicable in the database replacement.
 * @param string $session_name Normally the name that would be used for the session. Not applicable in the database replacement.
 * @return bool Returns whether the session could be opened; in the database replacement this is always true.
 */
function sessionOpen($save_path, $session_name)
{
	return true;
}

/**
 * Part of the PHP Session API, this function is intended to apply when session closure is required, as part of shutdown.
 *
 * @return bool Returns whether the session was successfully closed (typically a file handle). In the database this is not applicable so always returns true.
 */
function sessionClose()
{
	return true;
}

/**
 * Part of the PHP Session API, this function retrieves the session data from the storage, as part of generally loading the session, for the database replacement.
 *
 * @param string $session_id The session's identifier, required.
 * @return string The session data, as a serialized array.
 */
function sessionRead($session_id)
{
	if (preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $session_id) == 0)
		return false;

	// Look for it in the database.
	$result = wesql::query('
		SELECT data
		FROM {db_prefix}sessions
		WHERE session_id = {string:session_id}
		LIMIT 1',
		array(
			'session_id' => $session_id,
		)
	);
	list ($sess_data) = wesql::fetch_row($result);
	wesql::free_result($result);

	return $sess_data;
}

/**
 * Part of the PHP Session API, this function manages the saving of session data, for the database replacement.
 *
 * @param string $session_id The session's identification, required. Note that the name is checked to ensure it is a valid formatted string from the application.
 * @param string $data A string, containing the serialized data normally held in the $_SESSION array.
 * @return bool Returns true on successful write, false on not.
 */
function sessionWrite($session_id, $data)
{
	if (preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $session_id) == 0)
		return false;

	// First try to update an existing row...
	$result = wesql::query('
		UPDATE {db_prefix}sessions
		SET data = {string:data}, last_update = {int:last_update}
		WHERE session_id = {string:session_id}',
		array(
			'last_update' => time(),
			'data' => $data,
			'session_id' => $session_id,
		)
	);

	// If that didn't work, try inserting a new one.
	if (wesql::affected_rows() == 0)
		$result = wesql::insert('ignore',
			'{db_prefix}sessions',
			array('session_id' => 'string', 'data' => 'string', 'last_update' => 'int'),
			array($session_id, $data, time()),
			array('session_id')
		);

	return $result;
}

/**
 * Part of the PHP Session API, this function is for when the application terminates a session, typically on user actively logging out.
 *
 * @param string $session_id The id of the session to be removed.
 * @return bool Returns true if the session was able to be removed, false if not.
 */
function sessionDestroy($session_id)
{
	if (preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $session_id) == 0)
		return false;

	// Just delete the row...
	return wesql::query('
		DELETE FROM {db_prefix}sessions
		WHERE session_id = {string:session_id}',
		array(
			'session_id' => $session_id,
		)
	);
}

/**
 * Part of the PHP Session API, this function manages 'garbage collection', i.e. pruning session data older than the current needs.
 *
 * @param int $max_lifetime The maximum time in seconds that a session should persist for without actively being updated. It is compared to the default value specified by the administrator (stored in $modSettings['databaseSession_lifetime'])
 */
function sessionGC($max_lifetime)
{
	global $modSettings;

	// Just set to the default or lower? Ignore it for a higher value. (hopefully)
	if (!empty($modSettings['databaseSession_lifetime']) && ($max_lifetime <= 1440 || $modSettings['databaseSession_lifetime'] > $max_lifetime))
		$max_lifetime = max($modSettings['databaseSession_lifetime'], 60);

	// Clean up ;).
	return wesql::query('
		DELETE FROM {db_prefix}sessions
		WHERE last_update < {int:last_update}',
		array(
			'last_update' => time() - $max_lifetime,
		)
	);
}

/**
 * Initialize the database connection to be used.
 *
 * - Begin by loading the relevant function set (currently the MySQL driver)
 * - Initiate the database connection through the wesql object.
 * - If the connection fails, revert to a fatal error to the user.
 * - If in SSI mode, ensure the database prefix is attended to.
 * - The global variable $db_connection will hold the connection data.
 */
function loadDatabase()
{
	global $db_persist, $db_server, $db_user, $db_passwd;
	global $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix;

	// Load the database.
	loadSource('Class-DB');
	wesql::getInstance();

	// If we are in SSI try them first, but don't worry if it doesn't work, we have the normal username and password we can use.
	if (WEDGE == 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
		$con = wesql::connect($db_server, $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix, array('persist' => $db_persist, 'non_fatal' => true, 'dont_select_db' => true));

	// Either we aren't in SSI mode, or it failed.
	if (empty($con))
		$con = wesql::connect($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => $db_persist, 'dont_select_db' => WEDGE == 'SSI'));

	// Safe guard here, if there isn't a valid connection let's put a stop to it.
	if (!$con)
		show_db_error();

	// If in SSI mode fix up the prefix.
	if (WEDGE == 'SSI')
		wesql::fix_prefix($db_prefix, $db_name);
}

?>