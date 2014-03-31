<?php
/**
 * System class: user agent analyzer, user information...
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

define('PRIVACY_DEFAULT', 0);
define('PRIVACY_MEMBERS', 1);
define('PRIVACY_AUTHOR', 99);

class we
{
	static $ua, $browser, $os;		// User agent string (we::$ua) and subsequent browser array, and OS.
	static $user, $id, $is;			// All user information, plus their ID, and an array of variables defining the environment.
	static $is_admin;				// we::$is_admin -- or use the slower we::$is['admin'] or we::is('admin')
	static $is_guest, $is_member;	// we::$is_guest/we::$is_member -- or use the slower is('guest')/is('member')
	static $cache;					// Cache of parsed strings

	// What kind of class are you, anyway? One of a kind!
	private function __clone() {}

	public static function &getInstance($load_user = true)
	{
		static $instance = null;

		// Generate one and only one instance.
		if ($instance == null)
		{
			$instance = new self();
			self::$ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
			if ($load_user)
				self::init_user();
			self::init_browser();
		}

		return $instance;
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
	 * - Ensure the user groups are sanitized; or if not a logged in user, perform 'is this a spider' checks.
	 * - Populate we::$user with lots of useful information (id, username, email, password, language, whether the user is a guest or admin, skin information, post count, IP address, time format/offset, avatar, smileys, PM counts, buddy list, ignore user/board preferences, warning level, URL and user groups)
	 * - Establish board access rights based as an SQL clause (based on user groups) in we::$user['query_see_board'], and a subset of this to include ignore boards preferences into we::$user['query_wanna_see_board'].
	 */
	protected static function init_user()
	{
		global $context, $settings, $user_settings, $cookiename;

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

		// Aeva Media's Flash-based mass-upload feature doesn't carry the cookie with it.
		if (isset($_REQUEST['upcook']))
			$_COOKIE[$cookiename] = base64_decode(urldecode($_REQUEST['upcook']));

		if (empty($id_member) && isset($_COOKIE[$cookiename]))
		{
			list ($id_member, $password) = @unserialize($_COOKIE[$cookiename]);
			$id_member = !empty($id_member) && strlen($password) > 0 ? (int) $id_member : 0;
		}
		elseif (empty($id_member) && isset($_SESSION['login_' . $cookiename]) && ($_SESSION['USER_AGENT'] == self::$ua || !empty($settings['disableCheckUA'])))
		{
			// !!! Perhaps we can do some more checking on this, such as on the first octet of the IP?
			list ($id_member, $password, $login_span) = @unserialize($_SESSION['login_' . $cookiename]);
			$id_member = !empty($id_member) && strlen($password) == 40 && $login_span > time() ? (int) $id_member : 0;
		}
		self::$id = $id_member;

		// Only load this stuff if the user isn't a guest.
		if ($id_member != 0)
		{
			// Is the member data cached?
			if (empty($settings['cache_enable']) || $settings['cache_enable'] < 2 || ($user_settings = cache_get_data('user_settings-' . $id_member, 60)) === null)
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
				$user_settings['data'] = $user_settings['data'] !== '' ? unserialize($user_settings['data']) : array();
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
				$real_status = $user_settings['is_activated'] % 10;
				$id_member = $check && ($real_status == 1 || $real_status == 6) ? $user_settings['id_member'] : 0;
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
		if ($id_member)
		{
			// Let's not update the last visit time in these cases...
			// 1. Pages called by SSI, XML feeds and Ajax requests don't count as visiting the forum.
			// 2. If it was set within this session, no need to set it again.
			// 3. New session, yet updated less than 5 hours ago? Maybe cache can help.
			if (WEDGE != 'SSI' && !AJAX && ($context['action'] !== 'feed') && empty($_SESSION['id_msg_last_visit']) && (empty($settings['cache_enable']) || ($_SESSION['id_msg_last_visit'] = cache_get_data('user_last_visit-' . $id_member, 18000)) === null))
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

				// If it was *at least* 5 hours ago...
				if ($visitTime < time() - 18000)
				{
					updateMemberData($id_member, array('id_msg_last_visit' => (int) $settings['maxMsgID'], 'last_login' => time(), 'member_ip' => $_SERVER['REMOTE_ADDR'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']));
					$user_settings['last_login'] = time();

					if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2)
						cache_put_data('user_settings-' . $id_member, $user_settings, 60);

					if (!empty($settings['cache_enable']))
						cache_put_data('user_last_visit-' . $id_member, $_SESSION['id_msg_last_visit'], 18000);
				}
			}
			elseif (empty($_SESSION['id_msg_last_visit']))
				$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

			$username = $user_settings['member_name'];

			if (empty($user_settings['additional_groups']))
				$user = array(
					'groups' => array($user_settings['id_group'], $user_settings['id_post_group'])
				);
			else
				$user = array(
					'groups' => array_merge(
						array($user_settings['id_group'], $user_settings['id_post_group']),
						explode(',', $user_settings['additional_groups'])
					)
				);

			// Because history has proven that it is possible for groups to go bad - clean up in case.
			foreach ($user['groups'] as $k => $v)
				$user['groups'][$k] = (int) $v;

			// This is a logged in user, so definitely not a spider.
			$user['possibly_robot'] = false;
		}
		// If the user is a guest, initialize all the critical user settings.
		else
		{
			// This is what a guest's variables should be.
			$username = '';
			$user = array('groups' => array(-1));
			$user_settings = array();

			if (isset($_COOKIE[$cookiename]))
				$_COOKIE[$cookiename] = '';

			// At the very least, try to guess whether this user agent is unlikely to be human!
			$wild_guess = !strhas(self::$ua, array('Mozilla', 'Opera')) || strhas(strtolower(self::$ua), array('bot', 'slurp', 'crawl', 'spider'));

			// Do we perhaps think this is a search robot? Check every five minutes just in case...
			if ((!empty($settings['spider_mode']) || !empty($settings['spider_group'])) && (!isset($_SESSION['robot_check']) || $_SESSION['robot_check'] < time() - 300))
			{
				loadSource('ManageSearchEngines');
				$user['possibly_robot'] = SpiderCheck() || $wild_guess;
			}
			elseif (!empty($settings['spider_mode']))
				$user['possibly_robot'] = isset($_SESSION['id_robot']) ? $_SESSION['id_robot'] : false;
			else
				$user['possibly_robot'] = $wild_guess;
		}

		// Figure out the new time offset.
		if (!empty($user_settings['timezone']))
		{
			// Get the offsets from UTC for the server, then for the user.
			$tz_system = new DateTimeZone(@date_default_timezone_get());
			$tz_user = new DateTimeZone($user_settings['timezone']);
			$time_system = new DateTime("now", $tz_system);
			$time_user = new DateTime("now", $tz_user);
			$offset = ($tz_user->getOffset($time_user) - $tz_system->getOffset($time_system)) / 3600; // Convert to hours in the process.
		}

		if (!empty($user_settings['id_attach']) && !$user_settings['transparency'])
		{
			$filename = getAttachmentFilename($user_settings['filename'], $user_settings['id_attach'], $user_settings['id_folder']);
			$user_settings['transparency'] =
				we_resetTransparency(
					$user_settings['id_attach'],
					empty($user_settings['attachment_type']) ? $filename : $settings['custom_avatar_dir'] . '/' . $user_settings['filename'],
					$user_settings['filename']
				) ? 'transparent' : 'opaque';
		}

		// Get mobile status.
		if (!isset($_SESSION['is_mobile']))
			$_SESSION['is_mobile'] = self::is_mobile();

		if (isset($_COOKIE['guest_skin']))
		{
			if ($id_member === 0)
				$user_settings['skin'] = $_COOKIE['guest_skin'];
			else
			{
				loadSource('Subs-Auth');
				$cookie_url = url_parts(!empty($settings['localCookies']), !empty($settings['globalCookies']));
				setcookie('guest_skin', '', time() - 3600, $cookie_url[1], $cookie_url[0], 0);
			}
		}

		// Set up the we::$user array.
		$user += array(
			'username' => $username,
			'name' => $id_member == 0 ? '' : (isset($user_settings['real_name']) ? $user_settings['real_name'] : ''),
			'email' => isset($user_settings['email_address']) ? $user_settings['email_address'] : '',
			'activated' => !empty($user_settings['is_activated']) ? $user_settings['is_activated'] : 0,
			'passwd' => isset($user_settings['passwd']) ? $user_settings['passwd'] : '',
			'language' => empty($user_settings['lngfile']) || empty($settings['userLanguage']) ? '' : $user_settings['lngfile'],
			'skin' => $_SESSION['is_mobile'] ? (empty($user_settings['skin_mobile']) ? '' : $user_settings['skin_mobile']) : (empty($user_settings['skin']) ? '' : $user_settings['skin']),
			'last_login' => empty($user_settings['last_login']) ? 0 : $user_settings['last_login'],
			'ip' => $_SERVER['REMOTE_ADDR'],
			'ip2' => $_SERVER['BAN_CHECK_IP'],
			'posts' => empty($user_settings['posts']) ? 0 : $user_settings['posts'],
			'time_format' => empty($user_settings['time_format']) ? '' : $user_settings['time_format'],
			'time_offset' => isset($offset) ? $offset : (empty($user_settings['time_offset']) ? 0 : $user_settings['time_offset']),
			'avatar' => array(
				'href' => '',
				'url' => isset($user_settings['avatar']) ? $user_settings['avatar'] : '',
				'filename' => empty($user_settings['filename']) ? '' : $user_settings['filename'],
				'custom_dir' => !empty($user_settings['attachment_type']) && $user_settings['attachment_type'] == 1,
				'id_attach' => isset($user_settings['id_attach']) ? $user_settings['id_attach'] : 0,
				'transparent' => !empty($user_settings['transparency']) && $user_settings['transparency'] == 'transparent'
			),
			'data' => isset($user_settings['data']) && $user_settings['data'] !== '' ? $user_settings['data'] : array(),
			'smiley_set' => isset($user_settings['smiley_set']) ? $user_settings['smiley_set'] : '',
			'messages' => empty($settings['pm_enabled']) || empty($user_settings['instant_messages']) ? 0 : $user_settings['instant_messages'],
			'unread_messages' => empty($settings['pm_enabled']) || empty($user_settings['unread_messages']) ? 0 : $user_settings['unread_messages'],
			'unread_notifications' => !empty($user_settings['unread_notifications']) ? $user_settings['unread_notifications'] : 0,
			'media_unseen' => empty($user_settings['media_unseen']) ? 0 : $user_settings['media_unseen'],
			'total_time_logged_in' => empty($user_settings['total_time_logged_in']) ? 0 : $user_settings['total_time_logged_in'],
			'contacts' => array(),
			'buddies' => !empty($settings['enable_buddylist']) && !empty($user_settings['buddy_list']) ? explode(',', $user_settings['buddy_list']) : array(),
			'ignoreboards' => !empty($user_settings['ignore_boards']) && !empty($settings['ignorable_boards']) ? explode(',', $user_settings['ignore_boards']) : array(),
			'ignoreusers' => !empty($user_settings['pm_ignore_list']) ? explode(',', $user_settings['pm_ignore_list']) : array(),
			'warning' => isset($user_settings['warning']) ? $user_settings['warning'] : 0,
			'permissions' => array(),
			'post_moderated' => false,
			'sanctions' => !empty($user_settings['data']['sanctions']) ? $user_settings['data']['sanctions'] : array(),
		);

		$temp = array(
			'lists' => array(),
			'users' => array(),
			'groups' => array(),
			'ignored' => array(),
			'privacy' => PRIVACY_DEFAULT,
		);

		if ($id_member)
		{
			$cached = cache_get_data('contacts_' . $id_member, 3000);
			if ($cached === null)
			{
				// Get the member IDs in each of your contact lists.
				$request = wesql::query('
					SELECT id_list, id_member, list_type
					FROM {db_prefix}contacts
					WHERE id_owner = {int:user}',
					array(
						'user' => $id_member,
					)
				);
				while ($row = wesql::fetch_assoc($request))
				{
					// A list of ignored users can always be useful, can't it..?
					if ($row['list_type'] == 'restrict')
						$temp['ignored'][$row['id_member']] = 1;
					$temp['users'][$row['id_list']][$row['id_member']] = 1;
				}
				wesql::free_result($request);

				// Get the list IDs of your contact lists.
				$request = wesql::query('
					SELECT id_list, list_type, name
					FROM {db_prefix}contact_lists
					WHERE id_owner = {int:me}
					ORDER BY position, id_list',
					array(
						'me' => $id_member,
					)
				);
				while ($row = wesql::fetch_assoc($request))
					$temp['lists'][$row['id_list']] = array($row['name'], $row['list_type']);
				wesql::free_result($request);

				// Admins can set privacy to any membergroup, and regular members are limited
				// to groups they belong to, and post-based groups they went through.
				$request = wesql::query('
					SELECT id_group, group_name, min_posts
					FROM {db_prefix}membergroups' . (in_array(1, $user['groups']) ? '' : '
					WHERE (' . (empty($user['groups']) ? '0=1' : 'id_group IN ({array_int:my_groups})') . ')
						OR (min_posts != {int:non_postbased} AND min_posts <= {int:num_posts})') . '
					ORDER BY min_posts, id_group',
					array(
						'my_groups' => $user['groups'],
						'num_posts' => $user['posts'],
						'non_postbased' => -1,
					)
				);
				while ($row = wesql::fetch_assoc($request))
					$temp['groups'][$row['id_group']] = array($row['group_name'], $row['min_posts']);
				wesql::free_result($request);

				// Get the list IDs of the contact lists you're in.
				$request = wesql::query('
					SELECT id_list, id_owner, list_type
					FROM {db_prefix}contacts
					WHERE id_member = {int:me}',
					array(
						'me' => $id_member,
					)
				);
				$restrict = array();
				$in_lists = count($temp['lists']) ? array_combine(array_keys($temp['lists']), array_fill(0, count($temp['lists']), $id_member)) : array();
				while ($row = wesql::fetch_assoc($request))
				{
					if ($row['list_type'] === 'restrict')
						$restrict[$row['id_owner']] = $row['id_owner'];
					else
						$in_lists[$row['id_list']] = $row['id_owner'];
				}
				wesql::free_result($request);

				// We're relying on PHP preserving keys from $in_lists for this to work.
				$in_lists = array_keys(array_diff($in_lists, $restrict));

				// Finally, build a list of privacy IDs the user can see. Yayz.
				$privacy_list = array_merge(array_map('we::negate', array_keys($temp['groups'])), array(PRIVACY_DEFAULT, PRIVACY_MEMBERS), $in_lists);
				sort($privacy_list, SORT_NUMERIC);
				$temp['privacy'] = implode(',', array_flip(array_flip($privacy_list)));

				cache_put_data('contacts_' . $id_member, $temp, 3000);
			}
			else
				$temp = $cached;
		}
		$user['contacts'] = $temp;
		$user['groups'] = array_flip(array_flip($user['groups']));
		$user['privacy_list'] =& $temp['privacy'];

		$is = array(
			'guest' => $id_member == 0,
			'member' => $id_member > 0,
			'm' . $id_member => true,
			'admin' => in_array(1, $user['groups']),
			'mod' => false,
			'mobile' => $_SESSION['is_mobile'],
			'true' => true,
			'false' => false,
		);

		// Some clean-up.
		if ($is['admin'])
			$user['sanctions'] = array();
		else
			if (!empty($user['sanctions']))
				foreach ($user['sanctions'] as $infraction => $expiry)
					if ($expiry != 1 && $expiry < time())
						unset($user['sanctions'][$infraction]);

		// Some more data for we::is test flexibility...
		if (!empty($_GET['category']) && (int) $_GET['category'])
			$is['c' . (int) $_GET['category']] = true;

		// Fill in the server URL for the current user. This is user-specific, as they may be using a different URL than the script's default URL (Pretty URL, secure access...)
		// Note that HTTP_X_FORWARDED_SERVER is mostly used by proxy servers. If the client doesn't provide anything, it's probably a bot.
		$user['host'] = empty($_SERVER['REAL_HTTP_HOST']) ? (empty($_SERVER['HTTP_HOST']) ? (empty($_SERVER['HTTP_X_FORWARDED_SERVER']) ? substr(strrchr(ROOT, ':'), 3) : $_SERVER['HTTP_X_FORWARDED_SERVER']) : $_SERVER['HTTP_HOST']) : $_SERVER['REAL_HTTP_HOST'];
		$user['server'] = PROTOCOL . $user['host'];

		// The URL in your address bar. Also contains the query string.
		// Do not print this without sanitizing first!
		$user['url'] = (empty($_SERVER['REAL_HTTP_HOST']) ? $user['server'] : substr($user['server'], 0, strpos($user['server'], '/')) . '//' . $_SERVER['HTTP_HOST']) . $_SERVER['REQUEST_URI'];

		// Make sure that the last item in the ignore boards array is valid. If the list was too long it could have an ending comma that could cause problems.
		if (!empty($user['ignoreboards']) && empty($user['ignoreboards'][$tmp = count($user['ignoreboards']) - 1]))
			unset($user['ignoreboards'][$tmp]);

		// Do we have any languages to validate this?
		$languages = getLanguages();

		// Allow the user to change their language if it's valid.
		if (!empty($settings['userLanguage']) && !empty($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')]))
		{
			$user['language'] = strtr($_GET['language'], './\\:', '____');
			$_SESSION['language'] = $user['language'];
		}
		elseif (!empty($settings['userLanguage']) && !empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')]))
			$user['language'] = strtr($_SESSION['language'], './\\:', '____');
		elseif ($user['language'] === '')
			$user['language'] = get_preferred_language($settings['language']);

		// Just build this here, it makes it easier to change/use - administrators can see all boards.
		if ($is['admin'])
		{
			$user['query_list_board'] = '1=1';
			$user['query_see_board'] = '1=1';
		}
		// Otherwise just the groups in $user['groups'].
		else
		{
			$cache_groups = $user['groups'];
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
						'groups' => $user['groups'],
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
					if ($row['view_perm'] != 'disallow')
						$access['view_' . $row['view_perm']][] = $row['id_board'];
					if ($row['enter_perm'] != 'disallow')
						$access['enter_' . $row['enter_perm']][] = $row['id_board'];
				}
				$user['qlb_boards'] = array_diff($access['view_allow'], $access['view_deny']);
				$user['qsb_boards'] = array_diff($access['enter_allow'], $access['enter_deny']);
				$user['query_list_board'] = empty($user['qlb_boards']) ? '0=1' : 'b.id_board IN (' . implode(',', $user['qlb_boards']) . ')';
				$user['query_see_board'] = empty($user['qsb_boards']) ? '0=1' : 'b.id_board IN (' . implode(',', $user['qsb_boards']) . ')';

				$cache = array(
					'query_list_board' => $user['query_list_board'],
					'query_see_board' => $user['query_see_board'],
					'qlb_boards' => $user['qlb_boards'],
					'qsb_boards' => $user['qsb_boards'],
				);
				cache_put_data('board_access_' . $cache_groups, $cache, 300);
			}
			else
				$user += $temp;
		}

		// Build the list of boards they WANT to see.
		// This will take the place of query_see_board in certain spots, so it better include the boards they can see also

		// If they aren't ignoring any boards then they want to see all the boards they can see
		if (empty($user['ignoreboards']))
		{
			$user['query_wanna_see_board'] = $user['query_see_board'];
			$user['query_wanna_list_board'] = $user['query_list_board'];
		}
		// Ok, guess they don't want to see all the boards.
		else
		{
			if ($is['admin'])
			{
				// Admin can implicitly see and enter every board. If they want to ignore boards, make sure we clear both of the 'wanna see' options.
				$user['query_wanna_list_board'] = 'b.id_board NOT IN (' . implode(',', $user['ignoreboards']) . ')';
				$user['query_wanna_see_board'] = $user['query_wanna_list_board'];
			}
			else
			{
				$user['query_wanna_see_board'] = 'b.id_board IN (' . implode(',', array_diff($user['qsb_boards'], $user['ignoreboards'])) . ')';
				$user['query_wanna_list_board'] = 'b.id_board IN (' . implode(',', array_diff($user['qlb_boards'], $user['ignoreboards'])) . ')';
			}
		}

		$user['query_see_thought'] = ($is['guest'] ? '
			(' : '
			(
				h.id_member = ' . $id_member . ' OR ') . '
				h.privacy IN (' . $user['privacy_list'] . ')
			)';

		// {query_see_topic}, which has basic t.approved tests as well
		// as more elaborate topic privacy, is set up here.
		if ($is['admin'])
			$user['query_see_topic'] = '1=1';

		elseif ($is['guest'])
			$user['query_see_topic'] = empty($settings['postmod_active']) ? 't.privacy = ' . PRIVACY_DEFAULT : '(t.approved = 1 AND t.privacy = ' . PRIVACY_DEFAULT . ')';

		// If we're in a board, the approve_posts permission may be set for the current topic.
		// If not in a board, rely on mod_cache to see if you can approve this specific topic.
		else
		{
			$user['can_skip_approval'] = empty($settings['postmod_active']) || allowedTo(array('moderate_forum', 'moderate_board', 'approve_posts'));
			$user['query_see_topic'] = '
			(
				t.id_member_started = ' . MID . ' OR (' . ($user['can_skip_approval'] ? '' : (empty($user['mod_cache']['ap']) ? '
					t.approved = 1' : '
					(t.approved = 1 OR t.id_board IN (' . implode(', ', $user['mod_cache']['ap']) . '))') . '
					AND ') . 't.privacy IN (' . $user['privacy_list'] . ')
				)
			)';
		}

		wesql::register_replacement('query_see_board', $user['query_see_board']);
		wesql::register_replacement('query_list_board', $user['query_list_board']);
		wesql::register_replacement('query_wanna_see_board', $user['query_wanna_see_board']);
		wesql::register_replacement('query_wanna_list_board', $user['query_wanna_list_board']);
		wesql::register_replacement('query_see_thought', $user['query_see_thought']);
		wesql::register_replacement('query_see_topic', $user['query_see_topic']);
		wesql::register_replacement('empty', "''");

		self::$is =& $is;
		self::$user =& $user;
		self::$id = $id_member;
		self::$is_admin =& $is['admin'];
		self::$is_guest =& $is['guest'];
		self::$is_member =& $is['member'];

		define('MID', $id_member);
	}

	private static function negate($arr)
	{
		return -$arr;
	}

	/**
	 * Attempts to detect the browser, including version, needed for browser specific fixes and behaviours, and populates we::$browser with the findings.
	 *
	 * In all cases, general branch as well as major version is detected for, meaning that not only would Internet Explorer 8 be detected,
	 * so would Internet Explorer generically. This also sets flags for general emulation behavior later on, plus handling some types of robot.
	 *
	 * Current browsers detected via self::$browser['agent']:
	 * - Opera
	 * - Firefox
	 * - Chrome
	 * - Safari
	 * - Webkit (used in Safari, Chrome, Android stock browser...)
	 * - Gecko engine (used in Firefox and compatible)
	 * - Internet Explorer (plus tests for IE6 and above)
	 *
	 * Current OSes detected via self::$os['os']:
	 * - iOS (Safari Mobile is the only browser engine allowed on iPhone, iPod, iPad etc.)
	 * - Android
	 * - Windows (and versions equal to or above XP)
	 * - More generic mobile devices also available through we::is_mobile()
	 */
	protected static function init_browser()
	{
		global $context;

		// The following determines the user agent (browser) as best it can.
		$ua = self::$ua;
		$browser['opera'] = strpos($ua, 'Opera') !== false;

		// Detect Webkit and related
		$browser['webkit'] = $is_webkit = strpos($ua, 'AppleWebKit') !== false;
		$browser['chrome'] = $is_webkit && strhas($ua, array('Chrome', 'CriOS'));
		$browser['safari'] = $is_webkit && !$browser['chrome'] && strpos($ua, 'Safari') !== false;

		// Detect Firefox versions
		$browser['gecko'] = !$is_webkit && strpos($ua, 'Gecko') !== false;	// Mozilla and compatible
		$browser['firefox'] = strpos($ua, 'Gecko/') !== false;				// Firefox says "Gecko/20xx", not "like Gecko"

		$browser['ie'] = $is_ie = strhas($ua, array('MSIE', 'Trident/')); // MSIE was removed from IE 11.

		// Retrieve the version number, as a floating point.
		// Chrome for iOS uses the Safari Mobile string and replaces Version with CriOS.
		preg_match('~' . (
				$browser['opera'] || $browser['safari'] ? 'version[/ ]' :
				($browser['firefox'] ? 'firefox/' :
				($browser['ie'] ? '(?:msie |rv[: ])' :
				($browser['chrome'] ? 'c(?:hrome|rios)/' :
				'applewebkit/')))
			) . '([\d.]+)~i', $ua, $ver)
		|| preg_match('~(?:version|opera)[/ ]([\d.]+)~i', $ua, $ver);

		$ver = isset($ver[1]) ? (float) $ver[1] : 0;

		// A WebKit thing, with no version...? Set the equivalent Safari version.
		if ($browser['safari'] && !$ver)
		{
			preg_match('~applewebkit/([\d.]+)~i', $ua, $ver);
			$ver = isset($ver[1]) ? (float) $ver[1] : 0;
			$ver = $ver >= 536 ? 6 : ($ver >= 534.48 ? 5.1 : ($ver >= 533.16 ? 5 : 4));
		}

		// No need to store version numbers for outdated versions.
		if ($browser['opera'])			$ver = max(11, $ver);
		elseif ($browser['chrome'])		$ver = max(20, $ver);
		elseif ($browser['firefox'])	$ver = $ver < 5 ? max(3, $ver) : max(15, $ver); // Pre-v5 Firefox remains popular.
		elseif ($browser['safari'])		$ver = max(4, $ver);
		elseif ($browser['ie'])			$ver = max(6, $ver);

		// Reduce to first significant sub-version (if any), e.g. v2.01 => 2, v2.50.3 => 2.5
		$browser['version'] = floor($ver * 10) / 10;

		$browser['ie8down'] = $is_ie && $ver <= 8;
		for ($i = 6; $i <= 11; $i++)
			$browser['ie' . $i] = $is_ie && $ver == $i;

		// Store our browser name... Start with specific browsers, end with generic engines.
		foreach (array('opera', 'chrome', 'firefox', 'ie', 'safari', 'webkit', 'gecko', '') as $agent)
		{
			$browser['agent'] = $agent;
			if (!$agent || $browser[$agent])
				break;
		}

		// @if mobile -> returns true if user is browsing using a mobile device.
		// @if SKIN_MOBILE -> returns true if user's current skin is mobile-oriented, such as the Wireless skin.
		$os['mobile'] = !empty(self::$is['mobile']);

		// Determine current OS and version if it can turn out to be useful; currently
		// Windows XP and above, or iOS 4 and above, or Android 2 and above.
		// !! Should we add BlackBerry, Firefox OS and others..?
		$os['windows'] = strpos($ua, 'Windows ') !== false;
		$os['android'] = strpos($ua, 'Android') !== false;
		$os['ios'] = $is_webkit && strpos($ua, '(iP') !== false;
		if ($os['windows'])
		{
			if (preg_match('~Windows(?: NT)? (\d+\.\d+)~', $ua, $ver))
				$os_ver = max(5.1, (float) $ver[1]);
			// Fallback, just to be sure.
			else
				foreach (array('8' => 6.2, '7' => 6.1, 'Vista' => 6, 'XP' => 5.1) as $key => $os_ver)
					if (strpos($ua, 'Windows ' . $key) !== false)
						break;
		}
		elseif ($os['android'] && preg_match('~Android (\d+\.\d)~', $ua, $ver))
			$os_ver = max(2, (float) $ver[1]);
		elseif ($os['ios'] && preg_match('~ OS (\d+(?:_\d))~', $ua, $ver))
			$os_ver = max(3, (float) str_replace('_', '.', $ver[1]));

		$os['os'] = '';
		foreach (array('windows', 'android', 'ios') as $this_os)
			if ($os[$this_os])
				$os['os'] = $this_os;

		// Firefox OS doesn't advertise itself, but can officially be detected this way.
		if (empty($os['os']) && $browser['firefox'] && strpos($ua, '(Mobile;') !== false)
		{
			$os['os'] = 'ffos';
			$os['mobile'] = self::$is['mobile'] = true;
			$os_ver = '';
		}

		// !! Note that rounding to an integer (instead of the first significant sub-version)
		// could probably help reduce the number of cached files by a large margin. Opinions?
		$os['version'] = !empty($os_ver) ? floor($os_ver * 10) / 10 : '';

		// This isn't meant to be reliable, it's just meant to catch most bots to prevent PHPSESSID from showing up.
		$browser['possibly_robot'] = !empty(self::$user['possibly_robot']);

		// Robots shouldn't be logging in or registering. So, they aren't a bot. Better to be wrong than sorry (or people won't be able to log in!), anyway.
		if (!self::$is_guest || in_array($context['action'], array('login', 'login2', 'register')))
			$browser['possibly_robot'] = false;

		$browser[$browser['agent'] . $browser['version']] = true;
		$os[$os['os'] . $os['version']] = true;

		// Save the results...
		self::$browser = $browser;
		self::$os = $os;

		// And we'll also let you modify the browser array ASAP.
		call_hook('detect_browser');
	}

	// Mobile detection code is based on an early version of Mobile_Detect (MIT license).
	protected static function is_mobile()
	{
		if (empty(self::$ua))
			return false;

		$ua = strtolower(self::$ua);

		if (isset($_SERVER['HTTP_PROFILE']) || isset($_SERVER['HTTP_X_WAP_PROFILE']) || isset($_SERVER['HTTP_X_OPERAMINI_PHONE_UA']))
			return true;

		if (isset($_SERVER['HTTP_ACCEPT']) && strhas($_SERVER['HTTP_ACCEPT'], array('text/vnd.wap.wml', 'application/vnd.wap.xhtml+xml')))
			return true;

		$is_mobile = false;

		// Note: Google recommends that Android smartphones indicate the 'Mobile' keyword. Tablets shouldn't have it.
		foreach (explode('|', implode('|', array(
			'Generic' => 'mobile', // Mainly for Android smartphones -- excludes tablets.
			'Android' => 'android', // In some weird situations, such as Chrome desktop emulation of Android, it doesn't have the 'mobile' string.
			'iOS' => 'iphone|ipod', // iPad tablets can perfectly handle non-mobile layouts...
			'BlackBerry' => 'blackberry',
			'Symbian' => 'symbian',
			'Windows' => 'windows ce|windows phone', // Surface tablets omit the Phone keyword. This should be good enough.
			'PalmOS' => 'palm|avantgo|plucker|xiino',
			'Others' => 'samsung|htc|playstation|nintendo|opera mobi'
		))) as $device)
			if (strpos($ua, $device) !== false)
			{
				$is_mobile = true;
				break;
			}

		// We'll take everything, except HTC and Samsung's Android tablets, and tablets adding 'Mobile' but not fooled by Chrome.
		if ($is_mobile)
		{
			$is_mobile &= strpos($ua, 'flyer') === false; // HTC Flyer may have 'mobile' in its UA...
			$is_mobile &= strpos($ua, 'samsung') === false || strpos($ua, 'mobile') !== false || strpos($ua, 'android') === false; // Samsung tablets
			$is_mobile &= strpos($ua, 'chrome') === false || preg_match('~chrome/[.0-9]* mobile~', $ua); // Chrome helps us here.
		}

		return $is_mobile;
	}

	/**
	 * This is run once permissions are available for the user.
	 */
	public static function permissions()
	{
		global $settings;

		if (allowedTo('bypass_edit_disable'))
		{
			$settings['real_edit_disable_time'] = $settings['edit_disable_time'];
			$settings['edit_disable_time'] = 0;
		}
	}

	/**
	 * Alias to the analyzer, with a cache to speed it up. Optimized for simple is_* variable tests.
	 */
	public static function is($string)
	{
		if ($string === (array) $string)
			return self::analyze($string);
		if (isset(self::$cache[$string]))
			return self::$cache[$string];

		if (isset(self::$is[$string]))
			return self::$cache[$string] = empty(self::$is[$string]) ? false : $string;
		if (isset(self::$browser[$string]))
			return self::$cache[$string] = empty(self::$browser[$string]) ? false : $string;
		if (isset(self::$os[$string]))
			return self::$cache[$string] = empty(self::$os[$string]) ? false : $string;

		return self::$cache[$string] = self::analyze($string);
	}

	/**
	 * Analyzes the given array keys (or comma-separated list), and tries to determine if it encompasses the current browser version.
	 * Can deal with relatively complex strings. e.g., "firefox, !mobile && ie[-7]" means "if browser is Firefox, or is a desktop version of IE 6 or IE 7".
	 * Returns the string that was recognized as the browser, or false if nothing was found.
	 */
	public static function analyze($strings)
	{
		if ($brackets_parsed = !is_array($strings))
		{
			self::parse_brackets($strings); // Best deal with these right now, frankly.
			$strings = array_flip(array_map('trim', preg_split('~[,|]+~', $strings)));
		}

		foreach (self::$is as $key => $val)
			if (isset($strings[$key]) && !empty($val))
				return $key;

		$browser = self::$browser;
		$a = $browser['agent'];
		$bv = $browser['version'];
		$o = self::$os['os'];
		$ov = self::$os['version'];

		// A quick browser test.
		if (isset($strings[$a])) return $a;											// Example match: ie (any version of the browser.)
		if (isset($strings[$a . $bv])) return $a . $bv;								// ie7 (only)
		if (isset($strings[$a . '[' . $bv . ']'])) return $a . '[' . $bv . ']';		// ie[7] (same as above)
		if (isset($strings[$a . '[-' . $bv . ']'])) return $a . '[-' . $bv . ']';	// ie[-7] (up to version 7)
		if (isset($strings[$a . '[' . $bv . '-]'])) return $a . '[' . $bv . '-]';	// ie[7-] (version 7 and above)

		// A quick OS test.
		if (isset($strings[$o])) return $o;											// Example match: windows (any version of the OS.)
		if (isset($strings[$o . $ov])) return $o . $ov;								// windows6.1 (only Windows 7)
		if (isset($strings[$o . '[' . $ov . ']'])) return $o . '[' . $ov . ']';		// windows[6.1] (same as above)
		if (isset($strings[$o . '[-' . $ov . ']'])) return $o . '[-' . $ov . ']';	// windows[-6.1] (up to Windows 7)
		if (isset($strings[$o . '[' . $ov . '-]'])) return $o . '[' . $ov . '-]';	// windows[6.1-] (Windows 7 and above)

		$alength = strlen($a) + 1;
		$olength = strlen($o) + 1;

		// Okay, so maybe we're looking for a wider range?
		foreach ($strings as $string => $dummy)
		{
			if (empty($string))
				continue;

			if (!$brackets_parsed)
				self::parse_brackets($string);

			// Is there a && or & in the query? Meaning all parts of this one should return true.
			$and = strpos($string, '&');
			if ($and !== false)
			{
				// If nothing returned false, then go for it.
				if (!in_array(false, array_map('we::is', array_map('trim', preg_split('~&+~', $string)))))
					return $string;
				continue;
			}

			// A boolean test, which ::is() understands, for a variable comparison.
			if (strhas($string, array('=', '<', '>')))
				$string = preg_replace_callback('~(!?[^!=<>\h]*)\h*(=+|!=+|<>|[<>]=?)\h*(!?[^!=<>\h]*)~', 'we::evaluate', $string);

			// Negative tests.
			while (strpos($string, '!') !== false)
				$string = preg_replace_callback('~!([^\s]*)~', 'we::parse_negative', $string);

			// A boolean test for a stand-alone variable, i.e. != "" is implied.
			while (strpos($string, '"') !== false)
				$string = preg_replace_callback('~"([^"]*)"?+~', 'we::loose', $string);

			// If we forgot/ignored quotes on numbers, we'll still try to detect them.
			if (strpos($string, ' ') === false && preg_match('~^[-.]*\d~', $string))
				$string = preg_replace_callback('~(.+)~', 'we::loose', $string);

			if (!empty(self::$is[$string]))
				return $string;

			$bracket = strpos($string, '['); // Is there a version request?
			$request = $bracket === false ? $string : substr($string, 0, $bracket);

			if (empty($browser[$request]) && empty(self::$os[$request]))
				continue;
			if ($bracket === false)
				return $string;

			$split = explode('-', trim(substr($string, isset(self::$os[$request]) ? $olength : $alength, -1), ' ]'));
			$v = isset(self::$os[$request]) ? $ov : $bv;
			if (isset($split[1]))
			{
				if (empty($split[0]) && $v <= $split[1]) return $string;	// ie[-8] (version 8 or earlier)
				if (empty($split[1]) && $v >= $split[0]) return $string;	// ie[6-] (version 6 or later)
				if ($v >= $split[0] && $v <= $split[1]) return $string;		// ie[6-8] (version 6, 7 or 8)
			}
			elseif ($v == $split[0]) return $string;						// ie[8] or ie[8.0], FWIW...
		}

		return false;
	}

	private static function parse_positive($match)
	{
		return self::is($match[1]) ? 'true' : 'false';
	}

	private static function parse_negative($match)
	{
		return self::is($match[1]) ? 'false' : 'true';
	}

	private static function parse_brackets(&$string)
	{
		$protect = 0;
		$original_string = $string;
		while (strpos($string, '(') !== false)
		{
			$string = preg_replace_callback('~\(([^()]*)\)~', 'we::parse_positive', $string);
			if ($protect++ > 100)
			{
				// Admin only, so I'm not in a hurry to add a language string for that one.
				log_error('Can\'t parse string, due to mismatched brackets.<br><br>' . $original_string, false);
				return 'false';
			}
		}
	}

	private static function no_operator_vars($var)
	{
		return $var[1] != '' && $var[1] != '0' && $var[1] != 'false' ? 'true' : 'false';
	}

	private static function loose($var)
	{
		if (is_array($var))
			return $var[1] == '' || $var[1] == '0' ? 'false' : 'true';
		return $var == '' || $var == '0' ? 'false' : ($var == '1' ? 'true' : (string) $var);
	}

	// This is the variable test parser. Do not use () inside the variables to be tested. Some valid examples:
	// $settings['my_color'] == 'red' / $color != "#000" / $color != "red" / ($number > 0 && $number < $other)
	private static function evaluate($ops)
	{
		// $ops[1] and $ops[3] contain the variables to test, $ops[2] is the operator.
		$ops[1] = trim($ops[1]);
		$ops[3] = trim($ops[3]);

		$ops[1] = isset(self::$is[$ops[1]]) ? self::$is[$ops[1]] : $ops[1];
		$ops[3] = isset(self::$is[$ops[3]]) ? self::$is[$ops[3]] : $ops[3];

		// Now that we've converted system variables (::is), we can unprotect quoted literals.
		$ops[1] = trim($ops[1], "'\"");
		$ops[3] = trim($ops[3], "'\"");

		$op1 = intval($ops[1]);
		$op3 = intval($ops[3]);
		if (($op1 != 0 || is_numeric($ops[1])) && ($op3 != 0 || is_numeric($ops[3])))
		{
			$ops[1] = $op1;
			$ops[3] = $op3;
		}

		if (($ops[2] == '==' || $ops[2] == '===') && self::loose($ops[1]) == self::loose($ops[3]))
			return 'true';
		if (($ops[2] == '!=' || $ops[2] == '!==' || $ops[2] == '<>') && self::loose($ops[1]) != self::loose($ops[3]))
			return 'true';
		if ($ops[2] == '>' && (int) $ops[1] > (int) $ops[3])
			return 'true';
		if ($ops[2] == '>=' && (int) $ops[1] >= (int) $ops[3])
			return 'true';
		if ($ops[2] == '<' && (int) $ops[1] < (int) $ops[3])
			return 'true';
		if ($ops[2] == '<=' && (int) $ops[1] <= (int) $ops[3])
			return 'true';

		return 'false';
	}
}
