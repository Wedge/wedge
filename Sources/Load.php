<?php
/**
 * Wedge
 *
 * This file carries many useful functions for loading various general data from the database, often required on every page.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Loads the forum-wide settings into $settings as an array.
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
	global $settings, $boarddir, $txt, $context, $sourcedir, $pluginsdir, $pluginsurl;

	// Most database systems have not set UTF-8 as their default input charset.
	wesql::query('
		SET NAMES utf8',
		array(
		)
	);

	// Try to load it from the cache first; it'll never get cached if the setting is off.
	if (($settings = cache_get_data('settings', 90)) == null)
	{
		$request = wesql::query('
			SELECT variable, value
			FROM {db_prefix}settings',
			array(
			)
		);
		$settings = array();
		if (!$request)
			show_db_error();
		while ($row = wesql::fetch_row($request))
			$settings[$row[0]] = $row[1];
		wesql::free_result($request);

		// Do a few things to protect against missing settings or settings with invalid values...
		if (empty($settings['defaultMaxTopics']) || $settings['defaultMaxTopics'] <= 0 || $settings['defaultMaxTopics'] > 999)
			$settings['defaultMaxTopics'] = 20;
		if (empty($settings['defaultMaxMessages']) || $settings['defaultMaxMessages'] <= 0 || $settings['defaultMaxMessages'] > 999)
			$settings['defaultMaxMessages'] = 15;
		if (empty($settings['defaultMaxMembers']) || $settings['defaultMaxMembers'] <= 0 || $settings['defaultMaxMembers'] > 999)
			$settings['defaultMaxMembers'] = 30;
		$settings['registered_hooks'] = empty($settings['registered_hooks']) ? array() : unserialize($settings['registered_hooks']);
		$settings['hooks'] = $settings['registered_hooks'];
		$settings['pretty_filters'] = unserialize($settings['pretty_filters']);

		if (!empty($settings['cache_enable']))
			cache_put_data('settings', $settings, 90);
	}

	// Deal with loading plugins.
	$context['enabled_plugins'] = array();
	$context['extra_actions'] = array();
	if (!empty($settings['enabled_plugins']))
	{
		// Step through the list we think we have enabled.
		$plugins = explode(',', $settings['enabled_plugins']);
		$sane_path = str_replace('\\', '/', $pluginsdir);
		$hook_stack = array();
		foreach ($plugins as $plugin)
		{
			if (!empty($settings['plugin_' . $plugin]) && file_exists($sane_path . '/' . $plugin . '/plugin-info.xml'))
			{
				$plugin_details = @unserialize($settings['plugin_' . $plugin]);
				$context['enabled_plugins'][$plugin_details['id']] = $plugin;
				$this_plugindir = $context['plugins_dir'][$plugin_details['id']] = $sane_path . '/' . $plugin;
				$context['plugins_url'][$plugin_details['id']] = $pluginsurl . '/' . $plugin;
				if (isset($plugin_details['actions']))
					foreach ($plugin_details['actions'] as $action)
						$context['extra_actions'][$action['action']] = array($action['filename'], $action['function'], $plugin_details['id']);
				unset($plugin_details['id'], $plugin_details['provides'], $plugin_details['actions']);

				foreach ($plugin_details as $hook => $functions)
					foreach ($functions as $function)
					{
						$priority = (int) substr(strrchr($function, '|'), 1);
						$hook_stack[$hook][$priority][] = strtr($function, array('$plugindir' => $this_plugindir));
					}
			}
			else
				$reset_plugins = true;
		}

		if (isset($reset_plugins))
			updateSettings(array('enabled_plugins' => implode(',', $context['enabled_plugins'])));

		// Having got all the hooks, figure out the priority ordering and commit to the master list.
		foreach ($hook_stack as $hook => $hooks_by_priority)
		{
			krsort($hooks_by_priority);
			if (!isset($settings['hooks'][$hook]))
				$settings['hooks'][$hook] = array();
			foreach ($hooks_by_priority as $priority => $hooks)
				$settings['hooks'][$hook] = array_merge($settings['hooks'][$hook], $hooks);
		}
	}

	loadSource('Class-String');
	westr::getInstance();

	// Setting the timezone is a requirement.
	if (isset($settings['default_timezone']))
		date_default_timezone_set($settings['default_timezone']);
	else
		date_default_timezone_set(@date_default_timezone_get()); // At least attempt to use what the host has to try to prevent lots and lots of errors spewing everywhere.

	// Check the load averages?
	if (!empty($settings['loadavg_enable']))
	{
		if (($settings['load_average'] = cache_get_data('loadavg', 90)) == null)
		{
			$settings['load_average'] = @file_get_contents('/proc/loadavg');
			if (!empty($settings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $settings['load_average'], $matches) != 0)
				$settings['load_average'] = (float) $matches[1];
			elseif (($settings['load_average'] = @`uptime`) != null && preg_match('~load average[s]?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $settings['load_average'], $matches) != 0)
				$settings['load_average'] = (float) $matches[1];
			else
				unset($settings['load_average']);

			if (!empty($settings['load_average']))
				cache_put_data('loadavg', $settings['load_average'], 90);
		}

		if (!empty($settings['loadavg_forum']) && !empty($settings['load_average']) && $settings['load_average'] >= $settings['loadavg_forum'])
			show_db_error(true);
	}

	// Is post moderation alive and well?
	$settings['postmod_active'] = !empty($settings['postmod_rules']);

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
	global $settings, $user_settings, $cookiename, $user_info, $language;

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
	elseif (empty($id_member) && isset($_SESSION['login_' . $cookiename]) && ($_SESSION['USER_AGENT'] == $_SERVER['HTTP_USER_AGENT'] || !empty($settings['disableCheckUA'])))
	{
		// !!! Perhaps we can do some more checking on this, such as on the first octet of the IP?
		list ($id_member, $password, $login_span) = @unserialize($_SESSION['login_' . $cookiename]);
		$id_member = !empty($id_member) && strlen($password) == 40 && $login_span > time() ? (int) $id_member : 0;
	}

	// Only load this stuff if the user isn't a guest.
	if ($id_member != 0)
	{
		// Is the member data cached?
		if (empty($settings['cache_enable']) || $settings['cache_enable'] < 2 || ($user_settings = cache_get_data('user_settings-' . $id_member, 60)) == null)
		{
			$request = wesql::query('
				SELECT
					mem.*, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, a.id_folder, a.transparency
				FROM {db_prefix}members AS mem
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = {int:id_member})
				WHERE mem.id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $id_member,
				)
			);
			$user_settings = wesql::fetch_assoc($request);
			$user_settings['data'] = unserialize($user_settings['data']);
			wesql::free_result($request);

			if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2)
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
		if (WEDGE != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'feed') && empty($_SESSION['id_msg_last_visit']) && (empty($settings['cache_enable']) || ($_SESSION['id_msg_last_visit'] = cache_get_data('user_last_visit-' . $id_member, 5 * 3600)) === null))
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
				updateMemberData($id_member, array('id_msg_last_visit' => (int) $settings['maxMsgID'], 'last_login' => time(), 'member_ip' => $_SERVER['REMOTE_ADDR'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']));
				$user_settings['last_login'] = time();

				if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2)
					cache_put_data('user_settings-' . $id_member, $user_settings, 60);

				if (!empty($settings['cache_enable']))
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
		if ((!empty($settings['spider_mode']) || !empty($settings['spider_group'])) && (!isset($_SESSION['robot_check']) || $_SESSION['robot_check'] < time() - 300))
		{
			loadSource('ManageSearchEngines');
			$user_info['possibly_robot'] = SpiderCheck();
		}
		elseif (!empty($settings['spider_mode']))
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

	if (!empty($user_settings['id_attach']) && !$user_settings['transparency'])
	{
		$filename = getAttachmentFilename($user_settings['filename'], $user_settings['id_attach'], $user_settings['id_folder']);
		$user_settings['transparency'] = we_resetTransparency($user_settings['id_attach'], $filename, $user_settings['filename']) ? 'transparent' : 'opaque';
	}

	// Set up the $user_info array.
	$user_info += array(
		'id' => $id_member,
		'username' => $username,
		'name' => isset($user_settings['real_name']) ? $user_settings['real_name'] : '',
		'email' => isset($user_settings['email_address']) ? $user_settings['email_address'] : '',
		'passwd' => isset($user_settings['passwd']) ? $user_settings['passwd'] : '',
		'language' => empty($user_settings['lngfile']) || empty($settings['userLanguage']) ? $language : $user_settings['lngfile'],
		'is_guest' => $id_member == 0,
		'is_admin' => in_array(1, $user_info['groups']),
		'theme' => empty($user_settings['id_theme']) ? 0 : $user_settings['id_theme'],
		'skin' => empty($user_settings['id_theme']) ? '' : $user_settings['skin'],
		'last_login' => empty($user_settings['last_login']) ? 0 : $user_settings['last_login'],
		'ip' => $_SERVER['REMOTE_ADDR'],
		'ip2' => $_SERVER['BAN_CHECK_IP'],
		'posts' => empty($user_settings['posts']) ? 0 : $user_settings['posts'],
		'time_format' => empty($user_settings['time_format']) ? '' : $user_settings['time_format'],
		'time_offset' => isset($offset) ? $offset : (empty($user_settings['time_offset']) ? 0 : $user_settings['time_offset']),
		'avatar' => array(
			'url' => isset($user_settings['avatar']) ? $user_settings['avatar'] : '',
			'filename' => empty($user_settings['filename']) ? '' : $user_settings['filename'],
			'custom_dir' => !empty($user_settings['attachment_type']) && $user_settings['attachment_type'] == 1,
			'id_attach' => isset($user_settings['id_attach']) ? $user_settings['id_attach'] : 0,
			'transparent' => !empty($user_settings['transparency']) && $user_settings['transparency'] == 'transparent'
		),
		'data' => isset($user_settings['data']) ? $user_settings['data'] : array(),
		'smiley_set' => isset($user_settings['smiley_set']) ? $user_settings['smiley_set'] : '',
		'messages' => empty($user_settings['instant_messages']) ? 0 : $user_settings['instant_messages'],
		'unread_messages' => empty($user_settings['unread_messages']) ? 0 : $user_settings['unread_messages'],
		'media_unseen' => empty($user_settings['media_unseen']) ? 0 : $user_settings['media_unseen'],
		'total_time_logged_in' => empty($user_settings['total_time_logged_in']) ? 0 : $user_settings['total_time_logged_in'],
		'buddies' => !empty($settings['enable_buddylist']) && !empty($user_settings['buddy_list']) ? explode(',', $user_settings['buddy_list']) : array(),
		'ignoreboards' => !empty($user_settings['ignore_boards']) && !empty($settings['allow_ignore_boards']) ? explode(',', $user_settings['ignore_boards']) : array(),
		'ignoreusers' => !empty($user_settings['pm_ignore_list']) ? explode(',', $user_settings['pm_ignore_list']) : array(),
		'warning' => isset($user_settings['warning']) ? $user_settings['warning'] : 0,
		'permissions' => array(),
	);

	// Fill in the server URL for the current user. This is user-specific, as they may be using a different URL than the script's default URL (Pretty URL, secure access...)
	$user_info['host'] = empty($_SERVER['REAL_HTTP_HOST']) ? (empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_X_FORWARDED_SERVER'] : $_SERVER['HTTP_HOST']) : $_SERVER['REAL_HTTP_HOST'];
	$user_info['server'] = 'http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off' ? 's' : '') . '://' . $user_info['host'];

	// The URL in your address bar. Also contains the query string.
	// Do not print this without sanitizing first!
	$user_info['url'] = (empty($_SERVER['REAL_HTTP_HOST']) ? $user_info['server'] : substr($user_info['server'], 0, strpos($user_info['server'], '/')) . '//' . $_SERVER['HTTP_HOST']) . $_SERVER['REQUEST_URI'];

	$user_info['groups'] = array_unique($user_info['groups']);
	// Make sure that the last item in the ignore boards array is valid. If the list was too long it could have an ending comma that could cause problems.
	if (!empty($user_info['ignoreboards']) && empty($user_info['ignoreboards'][$tmp = count($user_info['ignoreboards']) - 1]))
		unset($user_info['ignoreboards'][$tmp]);

	// Do we have any languages to validate this?
	$languages = getLanguages();

	// Allow the user to change their language if it's valid.
	if (!empty($settings['userLanguage']) && !empty($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')]))
	{
		$user_info['language'] = strtr($_GET['language'], './\\:', '____');
		$_SESSION['language'] = $user_info['language'];
	}
	elseif (!empty($settings['userLanguage']) && !empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')]))
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
		if ($temp === null || time() - 240 > $settings['settings_updated'])
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
			$user_info['qlb_boards'] = array_diff($access['view_allow'], $access['view_deny']);
			$user_info['qsb_boards'] = array_diff($access['enter_allow'], $access['enter_deny']);
			$user_info['query_list_board'] = empty($user_info['qlb_boards']) ? '0=1' : 'b.id_board IN (' . implode(',', $user_info['qlb_boards']) . ')';
			$user_info['query_see_board'] = empty($user_info['qsb_boards']) ? '0=1' : 'b.id_board IN (' . implode(',', $user_info['qsb_boards']) . ')';

			$cache = array(
				'query_list_board' => $user_info['query_list_board'],
				'query_see_board' => $user_info['query_see_board'],
				'qlb_boards' => $user_info['qlb_boards'],
				'qsb_boards' => $user_info['qsb_boards'],
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

	setupTopicPrivacy();

	wesql::register_replacement('query_see_board', $user_info['query_see_board']);
	wesql::register_replacement('query_list_board', $user_info['query_list_board']);
	wesql::register_replacement('query_wanna_see_board', $user_info['query_wanna_see_board']);
	wesql::register_replacement('query_wanna_list_board', $user_info['query_wanna_list_board']);
}

/**
 * {query_see_topic}, which has basic t.approved tests as well
 * as more elaborate topic privacy, is set up here.
 */
function setupTopicPrivacy()
{
	global $db_prefix, $user_info, $settings;

	if (isset($user_info['query_see_topic']))
		return;

	if ($user_info['is_admin'])
		$user_info['query_see_topic'] = '1=1';

	elseif ($user_info['is_guest'])
		$user_info['query_see_topic'] = empty($settings['postmod_active']) ? 't.privacy = \'default\'' : '(t.approved = 1 AND t.privacy = \'default\')';

	// If we're in a board, the approve_posts permission may be set for the current topic.
	// If not in a board, rely on mod_cache to see if you can approve this specific topic.
	else
	{
		$user_info['can_skip_approval'] = empty($settings['postmod_active']) || allowedTo(array('moderate_forum', 'moderate_board', 'approve_posts'));
		$user_info['query_see_topic'] = '
		(
			t.id_member_started = ' . $uid . ' OR (' . ($user_info['can_skip_approval'] ? '' : (empty($user_info['mod_cache']['ap']) ? '
				t.approved = 1' : '
				(t.approved = 1 OR t.id_board IN (' . implode(', ', $user_info['mod_cache']['ap']) . '))') . '
				AND') . '
				(
					t.privacy = \'default\'
					OR (t.privacy = \'members\')
					OR (
						t.privacy = \'contacts\'
						AND t.id_member_started != 0
						AND FIND_IN_SET(' . $uid . ', (SELECT buddy_list FROM ' . $db_prefix . 'members WHERE id_member = t.id_member_started))
					)
				)
			)
		)';
	}

	wesql::register_replacement('query_see_topic', $user_info['query_see_topic']);
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
	global $txt, $scripturl, $context, $settings;
	global $board_info, $board, $topic, $user_info, $user_settings;

	// Assume they are not a moderator.
	$user_info['is_mod'] = false;
	$context['user']['is_mod'] =& $user_info['is_mod'];

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

	if (!empty($settings['cache_enable']) && (empty($topic) || $settings['cache_enable'] >= 3))
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
				bm.permission = \'access\' AS allowed, mco.real_name AS owner_name, mco.buddy_list AS contacts, b.board_type, b.sort_method,
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
				'contacts' => $row['contacts'],
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
			elseif ($row['member_groups'] === 'contacts')
				$board_info['privacy'] = 'contacts';
			elseif ($row['member_groups'] === '')
			{
				$board_info['privacy'] = 'author';
				$row['member_groups'] = '';
			}
			else
				$board_info['privacy'] = 'everyone';

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
			if ($board_info['num_topics'] == 0 && $settings['postmod_active'] && !allowedTo('approve_posts'))
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

			if (!empty($settings['cache_enable']) && (empty($topic) || $settings['cache_enable'] >= 3))
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

		if (!$user_info['is_admin'] && !in_array($board_info['id'], $user_info['qsb_boards']))
		{
			if (!$user_info['is_mod'] && (!empty($board_info['owner_id']) && $user_info['id'] != $board_info['owner_id']))
			{
				switch ($board_info['privacy'])
				{
					case 'contacts':
						if (!in_array($user_info['id'], explode(',', $board_info['contacts'])))
							$board_info['error'] = 'access';
						break;
					case 'members':
						if ($user_info['is_guest'])
							$board_info['error'] = 'access';
						break;
					case 'author':
						$board_info['error'] = 'access'; // We've already established that the user is not the owner
						break;
					case 'everyone':
						$board_info['error'] = 'access'; // The fact we're here means there are some groups denying/not granting access which must be adhered to
						break;
				}
			}
			else
				$board_info['error'] = 'access'; // You're not permitted here, not an admin or mod and there's no owner rights to allow you in either.
		}

		// Build up the linktree.
		$context['linktree'] = array_merge(
			$context['linktree'],
			array(array(
				'url' => $scripturl . '?action=boards;c=' . $board_info['cat']['id'],
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
	$context['user']['is_mod'] =& $user_info['is_mod'];
	$context['current_topic'] = $topic;
	$context['current_board'] = $board;

	// Hacker... you can't see this topic, I'll tell you that. (But moderators can!)
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
	global $user_info, $board, $board_info, $settings;

	if ($user_info['is_admin'])
	{
		banPermissions();
		return;
	}

	if (!empty($settings['cache_enable']))
	{
		$cache_groups = $user_info['groups'];
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);
		// If it's a spider then cache it different.
		if ($user_info['possibly_robot'])
			$cache_groups .= '-spider';

		if ($settings['cache_enable'] >= 2 && !empty($board) && ($temp = cache_get_data('permissions:' . $cache_groups . ':' . $board, 240)) != null && time() - 240 > $settings['settings_updated'])
		{
			list ($user_info['permissions']) = $temp;
			banPermissions();

			return;
		}
		elseif (($temp = cache_get_data('permissions:' . $cache_groups, 240)) != null && time() - 240 > $settings['settings_updated'])
			list ($user_info['permissions'], $removals) = $temp;
	}

	// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
	$spider_restrict = $user_info['possibly_robot'] && !empty($settings['spider_mode']) && !empty($settings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

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
				'spider_group' => !empty($settings['spider_group']) ? $settings['spider_group'] : 0,
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
				'spider_group' => !empty($settings['spider_mode']) && !empty($settings['spider_group']) ? $settings['spider_group'] : 0,
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
	if (!empty($settings['permission_enable_deny']))
		$user_info['permissions'] = array_diff($user_info['permissions'], $removals);

	if (isset($cache_groups) && !empty($board) && $settings['cache_enable'] >= 2)
		cache_put_data('permissions:' . $cache_groups . ':' . $board, array($user_info['permissions'], null), 240);

	// Banned? Watch, don't touch..
	banPermissions();

	// Load the mod cache so we can know what additional boards they should see, but no sense in doing it for guests
	if (!$user_info['is_guest'])
	{
		if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= $settings['settings_updated'])
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
	global $user_profile, $settings, $board_info;

	// Can't just look for no users :P.
	if (empty($users))
		return false;

	// Make sure it's an array.
	$users = !is_array($users) ? array($users) : array_unique($users);
	$loaded_ids = array();

	if (!$is_name && !empty($settings['cache_enable']) && $settings['cache_enable'] >= 3)
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
			IFNULL(lo.log_time, 0) AS is_online,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, a.transparency, a.id_folder,
			mem.signature, mem.personal_text, mem.location, mem.gender, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.birthdate, mem.member_ip, mem.member_ip2, mem.posts, mem.last_login, mem.id_post_group, mem.lngfile,
			mem.id_group, mem.time_offset, mem.show_online, mem.media_items, mem.media_comments, mem.buddy_list, mem.warning,
			mg.online_color AS member_group_color, IFNULL(mg.group_name, {string:blank}) AS member_group,
			pg.online_color AS post_group_color, IFNULL(pg.group_name, {string:blank}) AS post_group, mem.is_activated,
			CASE WHEN mem.id_group = 0 OR mg.stars = {string:blank} THEN pg.stars ELSE mg.stars END AS stars'
			. (!empty($settings['titlesEnable']) ? ', mem.usertitle' : '');
		$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';
	}
	elseif ($set == 'profile')
	{
		$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, a.transparency, a.id_folder,
			mem.signature, mem.personal_text, mem.location, mem.gender, mem.avatar, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.birthdate, mem.posts, mem.last_login, mem.media_items, mem.media_comments, mem.member_ip, mem.member_ip2,
			mem.lngfile, mem.id_group, mem.id_theme, mem.buddy_list, mem.pm_ignore_list, mem.pm_email_notify, mem.pm_receive_from,
			mem.time_offset, mem.time_format, mem.secret_question, mem.is_activated, mem.additional_groups, mem.smiley_set, mem.show_online,
			mem.total_time_logged_in, mem.id_post_group, mem.notify_announcements, mem.notify_regularity, mem.notify_send_body, mem.warning,
			mem.notify_types, lo.url, mg.online_color AS member_group_color, IFNULL(mg.group_name, {string:blank}) AS member_group,
			pg.online_color AS post_group_color, IFNULL(pg.group_name, {string:blank}) AS post_group, mem.ignore_boards,
			CASE WHEN mem.id_group = 0 OR mg.stars = {string:blank} THEN pg.stars ELSE mg.stars END AS stars, mem.password_salt, mem.pm_prefs'
			. (!empty($settings['titlesEnable']) ? ', mem.usertitle' : '');
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
				'blank' => '',
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

	if (!empty($new_loaded_ids) && !empty($settings['cache_enable']) && $settings['cache_enable'] >= 3)
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
	global $context, $settings, $board_info, $theme;
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
	if ($settings['avatar_action_too_large'] == 'option_html_resize' || $settings['avatar_action_too_large'] == 'option_js_resize')
	{
		$avatar_width = !empty($settings['avatar_max_width_external']) ? ' width="' . $settings['avatar_max_width_external'] . '"' : '';
		$avatar_height = !empty($settings['avatar_max_height_external']) ? ' height="' . $settings['avatar_max_height_external'] . '"' : '';
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
		'title' => !empty($settings['titlesEnable']) ? $profile['usertitle'] : '',
		'href' => $scripturl . '?action=profile;u=' . $profile['id_member'],
		'link' => '<a href="' . $scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['view_profile'] . '"' . (!empty($profile['member_group_color']) ? ' style="color: ' . $profile['member_group_color'] . '"' : '') . '>' . $profile['real_name'] . '</a>',
		'email' => $profile['email_address'],
		'show_email' => showEmailAddress(!empty($profile['hide_email']), $profile['id_member']),
		'registered' => empty($profile['date_registered']) ? $txt['not_applicable'] : timeformat($profile['date_registered']),
		'registered_timestamp' => empty($profile['date_registered']) ? 0 : forum_time(true, $profile['date_registered']),
		'blurb' => $profile['personal_text'],
		'gender' => array(
			'name' => $gendertxt,
			'image' => !empty($profile['gender']) ? '<img class="gender" src="' . $theme['images_url'] . '/' . ($profile['gender'] == 1 ? 'Male' : 'Female') . '.gif" alt="' . $gendertxt . '">' : ''
		),
		'website' => array(
			'title' => $profile['website_title'],
			'url' => $profile['website_url'],
		),
		'birth_date' => empty($profile['birthdate']) || $profile['birthdate'] === '0001-01-01' ? '0000-00-00' : (substr($profile['birthdate'], 0, 4) === '0004' ? '0000' . substr($profile['birthdate'], 4) : $profile['birthdate']),
		'signature' => $profile['signature'],
		'location' => $profile['location'],
		'real_posts' => $profile['posts'],
		'posts' => comma_format($profile['posts']),
		'last_login' => empty($profile['last_login']) ? $txt['never'] : timeformat($profile['last_login']),
		'last_login_timestamp' => empty($profile['last_login']) ? 0 : forum_time(0, $profile['last_login']),
		'ip' => htmlspecialchars($profile['member_ip']),
		'ip2' => htmlspecialchars($profile['member_ip2']),
		'online' => array(
			'is_online' => $profile['is_online'],
			'text' => $txt[$profile['is_online'] ? 'online' : 'offline'],
			'href' => $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'],
			'link' => '<a href="' . $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'] . '">' . $txt[$profile['is_online'] ? 'online' : 'offline'] . '</a>',
			'image_href' => $theme['images_url'] . '/' . ($profile['buddy'] ? 'buddy_' : '') . ($profile['is_online'] ? 'useron' : 'useroff') . '.gif',
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
		'group_stars' => str_repeat('<img src="' . str_replace('$language', $context['user']['language'], isset($profile['stars'][1]) ? $theme['images_url'] . '/' . $profile['stars'][1] : '') . '">', empty($profile['stars'][0]) || empty($profile['stars'][1]) ? 0 : $profile['stars'][0]),
		'warning' => $profile['warning'],
		'warning_status' => empty($settings['warning_mute']) ? '' : (isset($profile['is_activated']) && $profile['is_activated'] >= 10 ? 'ban' : ($settings['warning_mute'] <= $profile['warning'] ? 'mute' : (!empty($settings['warning_moderate']) && $settings['warning_moderate'] <= $profile['warning'] ? 'moderate' : (!empty($settings['warning_watch']) && $settings['warning_watch'] <= $profile['warning'] ? 'watch' : '')))),
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
	if (!$memberContext[$user]['is_banned'] || empty($settings['avatar_banned_hide']))
	{
		// So it's stored in members/avatar?
		if (!empty($profile['avatar']))
		{
			if (stristr($profile['avatar'], 'gravatar://'))
			{
				if ($profile['avatar'] === 'gravatar://' || empty($settings['gravatarAllowExtraEmail']))
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
					'image' => stristr($profile['avatar'], 'http://') ? '<img class="avatar" src="' . $profile['avatar'] . '"' . $avatar_width . $avatar_height . '>' : '<img class="avatar" src="' . $settings['avatar_url'] . '/' . htmlspecialchars($profile['avatar']) . '">',
					'href' => stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $settings['avatar_url'] . '/' . $profile['avatar'],
					'url' => stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $settings['avatar_url'] . '/' . $profile['avatar'],
				);
		}
		// It's an attachment?
		elseif (!empty($profile['id_attach']))
		{
			if (!$profile['transparency'])
			{
				$filename = getAttachmentFilename($profile['filename'], $profile['id_attach'], $profile['id_folder']);
				$profile['transparency'] = we_resetTransparency($profile['id_attach'], $filename, $profile['filename']) ? 'transparent' : 'opaque';
			}
			$memberContext[$user]['avatar'] = array(
				'name' => $profile['avatar'],
				'image' => $profile['id_attach'] > 0 ? '<img class="' . ($profile['transparency'] == 'transparent' ? '' : 'opaque ') . 'avatar" src="' . (empty($profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $settings['custom_avatar_url'] . '/' . $profile['filename']) . '">' : '',
				'href' => $profile['id_attach'] > 0 ? (empty($profile['attachment_type']) ? $scripturl . '?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $settings['custom_avatar_url'] . '/' . $profile['filename']) : '',
				'url' => '',
			);
		}
		// Default avatar?
		elseif (false)
		{
			// !!! @todo: Finish this.
		}
	}

	// Are we also loading the members custom fields into context?
	if ($display_custom_fields && !empty($settings['displayFields']))
	{
		$memberContext[$user]['custom_fields'] = array();
		if (!isset($context['display_fields']))
			$context['display_fields'] = unserialize($settings['displayFields']);

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
					'{IMAGES_URL}' => $theme['images_url'],
					'{DEFAULT_IMAGES_URL}' => $theme['default_images_url'],
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
 * Sets the transparency flag on attachments if not already set.
 * This is mainly useful to determine whether you can add a box-shadow
 * around an attachment thumbnail, or something.
 */
function we_resetTransparency($id_attach, $path, $real_name)
{
	loadSource('media/Subs-Media');
	$is_transparent = aeva_isTransparent($path, $real_name);
	wesql::query('
		UPDATE {db_prefix}attachments
		SET transparency = {string:transparency}
		WHERE id_attach = {int:id_attach}',
		array(
			'id_attach' => $id_attach,
			'transparency' => $is_transparent ? 'transparent' : 'opaque',
		)
	);
	return $is_transparent;
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
 * - Load data from the themes table for this theme, both the user's preferences for this theme, plus the global settings for it, and load into $theme and $options respectively ($theme for theme settings/global settings, $options for user's specific settings within this theme)
 * - Save details to cache as appropriate.
 * - Prepare the list of folders to examine in priority for template loading (i.e. this theme's folder first, then default, but can include others)
 * - Identify if the user has come to the board from the wrong place (e.g. a www in the URL that shouldn't be there) so it can be fixed.
 * - Push common details into $context['user'] for the current user.
 * - Identify what smiley set should be used.
 * - Initialize $context['header'] and $context['footer'] for later use, as well as some $theme paths, some global $context values, $txt initially.
 * - Set up common server-side settings for later reference (in case of server configuration specific tweaks)
 * - Ensure the forum name is the first item in the link tree.
 * - Load the XML template if that is what we are going to use, otherwise load the index template (plus any templates the theme has specified it uses), and do not initialise template layers if we are using a 'simple' action that does not need them.
 * - Initialize the theme by calling the init block.
 * - Load any theme specific language files.
 * - See if scheduled tasks need to be loaded, if so add the call into the HTML header so they will be triggered next page load.
 * - Call the load_theme hook.
 */
function loadTheme($id_theme = 0, $initialize = true)
{
	global $user_info, $user_settings, $board_info, $boarddir, $footer_coding;
	global $txt, $boardurl, $scripturl, $mbname, $settings, $language;
	global $context, $theme, $options, $ssi_theme;

	// The theme was specified by parameter.
	if (!empty($id_theme))
		$id_theme = (int) $id_theme;
	// The theme was specified by REQUEST.
	elseif (!empty($_REQUEST['theme']) && (!empty($settings['theme_allow']) || allowedTo('admin_forum')))
	{
		$th = explode('_', $_REQUEST['theme']);
		$id_theme = (int) $th[0];
		$skin = isset($th[1]) ? base64_decode($th[1]) : '';
		$_SESSION['id_theme'] = $id_theme;
		$_SESSION['skin'] = $skin;
	}
	// The theme was specified by REQUEST... previously.
	elseif (!empty($_SESSION['id_theme']) && (!empty($settings['theme_allow']) || allowedTo('admin_forum')))
	{
		$id_theme = (int) $_SESSION['id_theme'];
		$skin = !empty($_SESSION['skin']) ? $_SESSION['skin'] : '';
	}
	// The theme is just the user's choice. (Might use ?board=1;theme=0 to force board theme.)
	elseif (!empty($user_info['theme']) && !isset($_REQUEST['theme']) && (!empty($settings['theme_allow']) || allowedTo('admin_forum')))
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
		$id_theme = $settings['theme_guests'];
		$skin = $settings['theme_skin_guests'];
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
	elseif (!empty($settings['knownThemes']) && !allowedTo('admin_forum'))
	{
		$themes = explode(',', $settings['knownThemes']);
		$id_theme = in_array($id_theme, $themes) ? (int) $id_theme : $settings['theme_guests'];
	}
	else
		$id_theme = (int) $id_theme;

	// Time to determine our CSS list...
	// First, load our requested skin folder.
	$context['skin'] = empty($skin) ?
		(empty($id_theme) ? $settings['theme_skin_guests'] : 'skins') :
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

	if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2 && ($temp = cache_get_data('theme_settings-' . $id_theme . ':' . $member, 60)) != null && time() - 60 > $settings['settings_updated'])
	{
		$themeData = $temp;
		$flag = true;
	}
	elseif (($temp = cache_get_data('theme_settings-' . $id_theme, 90)) != null && time() - 60 > $settings['settings_updated'])
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
		// Pick between $theme and $options depending on whose data it is.
		while ($row = wesql::fetch_assoc($result))
		{
			// There are just things we shouldn't be able to change as members.
			if ($row['id_member'] != 0 && in_array($row['variable'], array('actual_theme_url', 'actual_images_url', 'base_theme_dir', 'base_theme_url', 'default_images_url', 'default_theme_dir', 'default_theme_url', 'default_template', 'images_url', 'smiley_sets_default', 'theme_dir', 'theme_id', 'theme_templates', 'theme_url')))
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

		if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2)
			cache_put_data('theme_settings-' . $id_theme . ':' . $member, $themeData, 60);
		// Only if we didn't already load that part of the cache...
		elseif (!isset($temp))
			cache_put_data('theme_settings-' . $id_theme, array(-1 => $themeData[-1], 0 => $themeData[0]), 90);
	}

	$theme = $themeData[0];
	$options = $themeData[$member];

	$theme['theme_id'] = $id_theme;

	$theme['actual_theme_url'] = $theme['theme_url'];
	$theme['actual_images_url'] = $theme['images_url'];
	$theme['actual_theme_dir'] = $theme['theme_dir'];

	$theme['template_dirs'] = array();
	// This theme first.
	$theme['template_dirs'][] = $theme['theme_dir'];

	// Based on theme (if there is one.)
	if (!empty($theme['base_theme_dir']))
		$theme['template_dirs'][] = $theme['base_theme_dir'];

	// Lastly the default theme.
	if ($theme['theme_dir'] != $theme['default_theme_dir'])
		$theme['template_dirs'][] = $theme['default_theme_dir'];

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
		if (!empty($settings['forum_alias_urls']))
		{
			$aliases = explode(',', $settings['forum_alias_urls']);

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
			$theme['theme_url'] = strtr($theme['theme_url'], array($oldurl => $boardurl));
			$theme['default_theme_url'] = strtr($theme['default_theme_url'], array($oldurl => $boardurl));
			$theme['actual_theme_url'] = strtr($theme['actual_theme_url'], array($oldurl => $boardurl));
			$theme['images_url'] = strtr($theme['images_url'], array($oldurl => $boardurl));
			$theme['default_images_url'] = strtr($theme['default_images_url'], array($oldurl => $boardurl));
			$theme['actual_images_url'] = strtr($theme['actual_images_url'], array($oldurl => $boardurl));

			// And just a few mod settings :)
			$settings['smileys_url'] = strtr($settings['smileys_url'], array($oldurl => $boardurl));
			$settings['avatar_url'] = strtr($settings['avatar_url'], array($oldurl => $boardurl));

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
		'username' => &$user_info['username'],
		'language' => &$user_info['language'],
		'email' => &$user_info['email'],
		'ignoreusers' => &$user_info['ignoreusers'],
		'data' => &$user_info['data'],
	);
	if (!$context['user']['is_guest'])
		$context['user']['name'] = $user_info['name'];
	elseif ($context['user']['is_guest'] && !empty($txt['guest_title']))
		$context['user']['name'] = $txt['guest_title'];

	// Determine the current smiley set
	$user_info['smiley_set'] = (!in_array($user_info['smiley_set'], explode(',', $settings['smiley_sets_known'])) && $user_info['smiley_set'] != 'none') || empty($settings['smiley_sets_enable']) ? (!empty($theme['smiley_sets_default']) ? $theme['smiley_sets_default'] : $settings['smiley_sets_default']) : $user_info['smiley_set'];
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

	$context['menu_separator'] = !empty($theme['use_image_buttons']) ? ' ' : ' | ';
	$context['session_var'] = $_SESSION['session_var'];
	$context['session_id'] = $_SESSION['session_value'];
	$context['session_query'] = $context['session_var'] . '=' . $context['session_id'];
	$context['forum_name'] = $mbname;
	$context['forum_name_html_safe'] = westr::htmlspecialchars($context['forum_name']);
	$context['header_logo_url_html_safe'] = empty($theme['header_logo_url']) ? $context['forum_name_html_safe']
		: '<img src="' . westr::htmlspecialchars($theme['header_logo_url']) . '" alt="' . $context['forum_name'] . '">';
	$context['site_slogan'] = empty($theme['site_slogan']) ? '<div id="wedgelogo"></div>' : '<div id="siteslogan">' . $theme['site_slogan'] . '</div>';
	$context['current_action'] = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
	$context['current_subaction'] = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : null;
	if (isset($settings['load_average']))
		$context['load_average'] = $settings['load_average'];

	// Set some permission related settings
	$context['show_login_bar'] = $user_info['is_guest'] && !empty($settings['enable_quick_login']);

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

	// Initializing the Wedge templating magic.
	$context['macros'] = array();
	$context['skeleton'] = '';
	$context['sidebar_position'] = 'right';
	wetem::getInstance();

	// If output is fully XML, or the print-friendly version, or the spellchecking page,
	// skip the index template entirely. Don't use macros in their templates!
	if (isset($_REQUEST['xml']) || !empty($_REQUEST['action']) && ($_REQUEST['action'] == 'printpage' || $_REQUEST['action'] == 'spellcheck'))
	{
		if (isset($_REQUEST['xml']))
			loadTemplate('Xml');
		loadLanguage('index');
		wetem::hide();
	}
	else
	{
		// Custom templates to load, or just default?
		if (isset($theme['theme_templates']))
			$templates = explode(',', $theme['theme_templates']);
		else
			$templates = array('index');

		// Load each template...
		foreach ($templates as $template)
			loadTemplate($template);

		// Users may also add a Custom.template.php file to their theme folder, to help them override
		// or add code before or after a specific function, e.g. in the index template, without
		// having to create a new theme. If it's not there, we'll just ignore that.
		loadTemplate('Custom', false);

		// ...and attempt to load their associated language files.
		$required_files = implode('+', $templates);
		loadLanguage($required_files, '', false);

		// Initialize our JS files to cache right before we run template_init().
		if (empty($settings['jquery_origin']) || $settings['jquery_origin'] === 'local')
			$context['javascript_files'] = array('scripts/jquery-1.5.2.js', 'scripts/script.js', 'scripts/sbox.js');
		else
		{
			$remote = array(
				'google' =>		'http://ajax.googleapis.com/ajax/libs/jquery/1.5.2/jquery.min.js',
				'jquery' =>		'http://code.jquery.com/jquery-1.5.2.min.js',
				'microsoft' =>	'http://ajax.aspnetcdn.com/ajax/jQuery/jquery-1.5.2.min.js',
			);
			$context['remote_javascript_files'] = array($remote[$settings['jquery_origin']]);
			$context['javascript_files'] = array('scripts/script.js', 'scripts/sbox.js');
		}

		// Initialize the theme and load the default macros.
		execBlock('init', 'ignore');

		// Now we initialize the search/replace pairs for macros.
		// They can be overloaded in a skin's skin.xml file.
		if (!empty($theme['macros']))
		{
			foreach ($theme['macros'] as $name => $contents)
			{
				if (is_array($contents))
					$contents = isset($contents[$context['browser']['agent']]) ? $contents[$context['browser']['agent']] :
							(isset($contents['else']) ? $contents['else'] : '{body}');

				$context['macros'][$name] = array(
					'has_if' => strpos($contents, '<if:') !== false,
					'body' => $contents,
				);
			}
		}

		// Now we'll override all of these...
		loadSource('Subs-Cache');
		wedge_get_skin_options();

		// Did we find an override for the skeleton? If not, load the default one.
		if (empty($context['skeleton']))
			execBlock('skeleton', 'ignore');

		// Now we have a $context['skeleton'] (original or overridden), we can feed it to the template object.
		preg_match_all('~<(?!!)(/)?(\w+)\s*([^>]*?)(/?)\>~', $context['skeleton'], $match, PREG_SET_ORDER);
		wetem::build($match);
		unset($context['skeleton']);
	}

	// Guests may still need a name
	if ($context['user']['is_guest'] && empty($context['user']['name']))
		$context['user']['name'] = $txt['guest_title'];

	// Any theme-related strings that need to be loaded?
	if (!empty($theme['require_theme_strings']))
		loadLanguage('ThemeStrings', '', false);

	// Allow overriding the board wide time/number formats.
	if (empty($user_settings['time_format']) && !empty($txt['time_format']))
		$user_info['time_format'] = $txt['time_format'];

	if (isset($theme['use_default_images']) && $theme['use_default_images'] == 'always')
	{
		$theme['theme_url'] = $theme['default_theme_url'];
		$theme['images_url'] = $theme['default_images_url'];
		$theme['theme_dir'] = $theme['default_theme_dir'];
	}
	// Make a special URL for the language.
	// !!! $txt['image_lang'] isn't defined anywhere...
	$theme['lang_images_url'] = $theme['images_url'] . '/' . (!empty($txt['image_lang']) ? $txt['image_lang'] : $user_info['language']);

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
	if ((!empty($settings['mail_next_send']) && $settings['mail_next_send'] < time() && empty($settings['mail_queue_use_cron'])) || empty($settings['next_task_time']) || $settings['next_task_time'] < time())
	{
		if ($context['browser']['possibly_robot'])
		{
			//!!! Maybe move this somewhere better?!
			loadSource('ScheduledTasks');

			// What to do, what to do?!
			if (empty($settings['next_task_time']) || $settings['next_task_time'] < time())
				AutoTask();
			else
				ReduceMailQueue();
		}
		else
		{
			$type = empty($settings['next_task_time']) || $settings['next_task_time'] < time() ? 'task' : 'mailq';
			$ts = $type == 'mailq' ? $settings['mail_next_send'] : $settings['next_task_time'];

			add_js('
	function weAutoTask()
	{
		new Image().src = "' . $scripturl . '?scheduled=' . $type . ';ts=' . $ts . '";
	}
	setTimeout(weAutoTask, 1);');
		}
	}

	// What about any straggling imperative tasks?
	if (empty($settings['next_imperative']))
	{
		loadSource('Subs-Scheduled');
		recalculateNextImperative();
	}

	if ($settings['next_imperative'] < time())
		add_js('
	function weImperativeTask()
	{
		new Image().src = "' . $scripturl . '?imperative;ts=' . time() . '";
	}
	setTimeout(weImperativeTask, 1);');

	// Any files to include at this point?
	if (!empty($settings['integrate_theme_include']))
	{
		$theme_includes = explode(',', $settings['integrate_theme_include']);
		foreach ($theme_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $theme['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Call load theme hook.
	call_hook('load_theme');

	// We are ready to go.
	$context['theme_loaded'] = true;
}

function loadPluginSource($plugin_name, $source_name)
{
	global $context;
	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	foreach ((array) $source_name as $file)
		require_once($context['plugins_dir'][$plugin_name] . '/' . $file . '.php');
}

function loadPluginTemplate($plugin_name, $template_name, $fatal = true)
{
	global $context, $theme;
	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	// We may as well reuse the normal template loader. Might rewrite this later, however.
	$old_templates = $theme['template_dirs'];
	$theme['template_dirs'] = array($context['plugins_dir'][$plugin_name]);
	loadTemplate($template_name, $fatal);
	$theme['template_dirs'] = $old_templates;
}

function loadPluginLanguage($plugin_name, $template_name, $lang = '', $fatal = true, $force_reload = false)
{
	global $context, $settings, $user_info, $language, $txt, $db_show_debug;
	static $already_loaded = array();
	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	// Default to the user's language.
	if ($lang == '')
		$lang = isset($user_info['language']) ? $user_info['language'] : $language;

	if (!$force_reload && isset($already_loaded[$template_name]) && $already_loaded[$template_name] == $lang)
		return $lang;

	$attempts = array();
	// If true, pass through to the next language attempt even if it's a match. But if it's not English, see about loading that *first*.
	if (empty($settings['disable_language_fallback']) && $lang !== 'english')
		$attempts['english'] = true;

	// Then go with user preference, followed by forum default (assuming it isn't already one of the previous)
	$attempts[$lang] = false;
	$attempts[$language] = false;

	$found = false;
	foreach ($attempts as $load_lang => $continue)
	{
		$file = $context['plugins_dir'][$plugin_name] . '/' . $template_name . '.' . $load_lang . '.php';
		if (file_exists($file))
		{
			template_include($file);
			$found = true;
		}
		if ($found && !$continue)
			break;
	}

	// Oops, didn't find it. Log it.
	if (!$found)
		log_error(sprintf($txt['theme_language_error'], '(' . $plugin_name . ') ' . $template_name . '.' . $lang, 'template'));

	// Keep track of what we're up to soldier.
	if ($db_show_debug === true)
		$context['debug']['language_files'][] = $template_name . '.' . $lang . ' (' . $plugin_name . ')';

	// Remember what we have loaded, and in which language.
	$already_loaded[$plugin_name . ':' . $template_name] = $lang;

	// Return the language actually loaded.
	return $lang;
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
	global $user_info, $language, $theme, $context, $settings;
	global $cachedir, $db_show_debug, $txt;
	static $already_loaded = array();

	// Default to the user's language.
	if ($lang == '')
		$lang = isset($user_info['language']) ? $user_info['language'] : $language;

	// Do we want the English version of language file as fallback?
	if (empty($settings['disable_language_fallback']) && $lang !== 'english')
		loadLanguage($template_name, 'english', false);

	if (!$force_reload && isset($already_loaded[$template_name]) && $already_loaded[$template_name] == $lang)
		return $lang;

	// Make sure we have $theme - if not we're in trouble and need to find it!
	if (empty($theme['default_theme_dir']))
	{
		loadSource('ScheduledTasks');
		loadEssentialThemeData();
	}

	// What theme are we in?
	$theme_name = basename($theme['theme_url']);
	if (empty($theme_name))
		$theme_name = 'unknown';

	// For each file open it up and write it out!
	foreach (explode('+', $template_name) as $template)
	{
		// Obviously, the current theme is most important to check.
		$attempts = array(
			array($theme['theme_dir'], $template, $lang, $theme['theme_url']),
			array($theme['theme_dir'], $template, $language, $theme['theme_url']),
		);

		// Do we have a base theme to worry about?
		if (isset($theme['base_theme_dir']))
		{
			$attempts[] = array($theme['base_theme_dir'], $template, $lang, $theme['base_theme_url']);
			$attempts[] = array($theme['base_theme_dir'], $template, $language, $theme['base_theme_url']);
		}

		// Fall back on the default theme if necessary.
		$attempts[] = array($theme['default_theme_dir'], $template, $lang, $theme['default_theme_url']);
		$attempts[] = array($theme['default_theme_dir'], $template, $language, $theme['default_theme_url']);

		// Fall back on the English language if none of the preferred languages can be found.
		if (!in_array('english', array($lang, $language)))
		{
			$attempts[] = array($theme['theme_dir'], $template, 'english', $theme['theme_url']);
			$attempts[] = array($theme['default_theme_dir'], $template, 'english', $theme['default_theme_url']);
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
		{
			$user_info['setlocale'] = setlocale(LC_TIME, $txt['lang_locale'] . '.utf-8', $txt['lang_locale'] . '.utf8');
			if (empty($user_info['time_format']))
				$user_info['time_format'] = $txt['time_format'];
		}
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
	global $context, $theme, $settings;

	// If the language array is already filled, or we wanna use the cache and it's not expired...
	if ($use_cache && (isset($context['languages']) || ($context['languages'] = cache_get_data('known_languages', !empty($settings['cache_enable']) && $settings['cache_enable'] < 1 ? 86400 : 3600)) !== null))
		return $context['languages'];

	// If we don't have our theme information yet, let's get it.
	if (empty($theme['default_theme_dir']))
		loadTheme(0, false);

	// Default language directories to try.
	$language_directories = array(
		$theme['default_theme_dir'] . '/languages',
		$theme['actual_theme_dir'] . '/languages',
	);

	// We possibly have a base theme directory.
	if (!empty($theme['base_theme_dir']))
		$language_directories[] = $theme['base_theme_dir'] . '/languages';

	// Initialize the array, otherwise if it's empty, Wedge won't cache it.
	$context['languages'] = array();

	// Go through all unique directories.
	foreach (array_unique($language_directories) as $language_dir)
	{
		// Can't look in here... doesn't exist!
		if (!file_exists($language_dir))
			continue;

		$dir = glob($language_dir . '/index.*.php');
		foreach ($dir as $entry)
		{
			if (!preg_match('~/index\.([^.]+)\.php$~', $entry, $matches))
				continue;
			$context['languages'][$matches[1]] = array(
				'name' => westr::ucwords(strtr($matches[1], array('_' => ' '))),
				'filename' => $matches[1],
				'location' => $entry,
			);
		}
	}

	// Let's cash in on this deal.
	if (!empty($settings['cache_enable']))
		cache_put_data('known_languages', $context['languages'], !empty($settings['cache_enable']) && $settings['cache_enable'] < 1 ? 86400 : 3600);

	return $context['languages'];
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
	global $HTTP_SESSION_VARS, $settings, $boardurl, $sc;

	// Attempt to change a few PHP settings.
	@ini_set('session.use_cookies', true);
	@ini_set('session.use_only_cookies', false);
	@ini_set('url_rewriter.tags', '');
	@ini_set('session.use_trans_sid', false);
	@ini_set('arg_separator.output', '&amp;');

	if (!empty($settings['globalCookies']))
	{
		$parsed_url = parse_url($boardurl);

		if (preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) == 0 && preg_match('~(?:[^.]+\.)?([^.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
			@ini_set('session.cookie_domain', '.' . $parts[1]);
	}
	// !!! Set the session cookie path?

	// If it's already been started... probably best to skip this.
	if ((@ini_get('session.auto_start') == 1 && !empty($settings['databaseSession_enable'])) || session_id() == '')
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
		if (!empty($settings['databaseSession_enable']))
		{
			session_set_save_handler('sessionOpen', 'sessionClose', 'sessionRead', 'sessionWrite', 'sessionDestroy', 'sessionGC');
			@ini_set('session.gc_probability', '1');
		}
		elseif (@ini_get('session.gc_maxlifetime') <= 1440 && !empty($settings['databaseSession_lifetime']))
			@ini_set('session.gc_maxlifetime', max($settings['databaseSession_lifetime'], 60));

		// Use cache setting sessions?
		if (empty($settings['databaseSession_enable']) && !empty($settings['cache_enable']) && php_sapi_name() != 'cli')
		{
			if (function_exists('eaccelerator_set_session_handlers'))
				eaccelerator_set_session_handlers();
		}

		session_start();

		// Change it so the cache settings are a little looser than default.
		if (!empty($settings['databaseSession_loose']))
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
 * @param int $max_lifetime The maximum time in seconds that a session should persist for without actively being updated. It is compared to the default value specified by the administrator (stored in $settings['databaseSession_lifetime'])
 */
function sessionGC($max_lifetime)
{
	global $settings;

	// Just set to the default or lower? Ignore it for a higher value. (hopefully)
	if (!empty($settings['databaseSession_lifetime']) && ($max_lifetime <= 1440 || $settings['databaseSession_lifetime'] > $max_lifetime))
		$max_lifetime = max($settings['databaseSession_lifetime'], 60);

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