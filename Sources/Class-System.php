<?php
/**
 * Wedge
 *
 * System class: user agent analyzer, user information...
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

class we
{
	static $ua, $browser;			// User agent string (we::$ua) and subsequent browser array.
	static $user, $id;				// All user information, plus their ID
	static $is_admin, $is_guest;	// we::$is_admin/$is_guest -- or use the slower we::is('admin')/is('guest')
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
	 * - Ensure the user groups are sanitised; or if not a logged in user, perform 'is this a spider' checks.
	 * - Populate we::$user with lots of useful information (id, username, email, password, language, whether the user is a guest or admin, theme information, post count, IP address, time format/offset, avatar, smileys, PM counts, buddy list, ignore user/board preferences, warning level, URL and user groups)
	 * - Establish board access rights based as an SQL clause (based on user groups) in we::$user['query_see_board'], and a subset of this to include ignore boards preferences into we::$user['query_wanna_see_board'].
	 */
	protected static function init_user()
	{
		global $settings, $user_settings, $cookiename, $language, $db_prefix, $boardurl;

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
				$id_member = $check && ($user_settings['is_activated'] % 10 == 1) ? $user_settings['id_member'] : 0;
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
			if (WEDGE != 'SSI' && !AJAX && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'feed') && empty($_SESSION['id_msg_last_visit']) && (empty($settings['cache_enable']) || ($_SESSION['id_msg_last_visit'] = cache_get_data('user_last_visit-' . $id_member, 5 * 3600)) === null))
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

			// Do we perhaps think this is a search robot? Check every five minutes just in case...
			if ((!empty($settings['spider_mode']) || !empty($settings['spider_group'])) && (!isset($_SESSION['robot_check']) || $_SESSION['robot_check'] < time() - 300))
			{
				loadSource('ManageSearchEngines');
				$user['possibly_robot'] = SpiderCheck();
			}
			elseif (!empty($settings['spider_mode']))
				$user['possibly_robot'] = isset($_SESSION['id_robot']) ? $_SESSION['id_robot'] : 0;
			// If we haven't turned on proper spider hunts then have a guess!
			else
				$user['possibly_robot'] = (strpos(self::$ua, 'Mozilla') === false && strpos(self::$ua, 'Opera') === false) || preg_match('~(?:bot|slurp|crawl|spider)~', strtolower(self::$ua));
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
			$user_settings['transparency'] = we_resetTransparency($user_settings['id_attach'], $filename, $user_settings['filename']) ? 'transparent' : 'opaque';
		}

		// Get mobile status.
		if (!isset($_SESSION['is_mobile']))
			$_SESSION['is_mobile'] = self::is_mobile();

		// Set up the we::$user array.
		$user += array(
			'username' => $username,
			'name' => $id_member == 0 ? (!empty($txt['guest_title']) ? $txt['guest_title'] : 'Guest') : (isset($user_settings['real_name']) ? $user_settings['real_name'] : ''),
			'email' => isset($user_settings['email_address']) ? $user_settings['email_address'] : '',
			'passwd' => isset($user_settings['passwd']) ? $user_settings['passwd'] : '',
			'language' => empty($user_settings['lngfile']) || empty($settings['userLanguage']) ? $language : $user_settings['lngfile'],
			'is_guest' => $id_member == 0,
			'is_admin' => in_array(1, $user['groups']),
			'is_mod' => false,
			'is_mobile' => $_SESSION['is_mobile'],
			'theme' => $_SESSION['is_mobile'] ? (empty($user_settings['id_theme_mobile']) ? 0 : $user_settings['id_theme_mobile']) : (empty($user_settings['id_theme']) ? 0 : $user_settings['id_theme']),
			'skin' => $_SESSION['is_mobile'] ? (empty($user_settings['id_theme_mobile']) ? '' : $user_settings['skin_mobile']) : (empty($user_settings['id_theme']) ? '' : $user_settings['skin']),
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
			'messages' => empty($settings['pm_enabled']) || empty($user_settings['instant_messages']) ? 0 : $user_settings['instant_messages'],
			'unread_messages' => empty($settings['pm_enabled']) || empty($user_settings['unread_messages']) ? 0 : $user_settings['unread_messages'],
			'media_unseen' => empty($user_settings['media_unseen']) ? 0 : $user_settings['media_unseen'],
			'total_time_logged_in' => empty($user_settings['total_time_logged_in']) ? 0 : $user_settings['total_time_logged_in'],
			'buddies' => !empty($settings['enable_buddylist']) && !empty($user_settings['buddy_list']) ? explode(',', $user_settings['buddy_list']) : array(),
			'ignoreboards' => !empty($user_settings['ignore_boards']) && !empty($settings['allow_ignore_boards']) ? explode(',', $user_settings['ignore_boards']) : array(),
			'ignoreusers' => !empty($user_settings['pm_ignore_list']) ? explode(',', $user_settings['pm_ignore_list']) : array(),
			'warning' => isset($user_settings['warning']) ? $user_settings['warning'] : 0,
			'permissions' => array(),
		);

		// Fill in the server URL for the current user. This is user-specific, as they may be using a different URL than the script's default URL (Pretty URL, secure access...)
		// Note that HTTP_X_FORWARDED_SERVER is mostly used by proxy servers. If the client doesn't provide anything, it's probably a bot.
		$user['host'] = empty($_SERVER['REAL_HTTP_HOST']) ? (empty($_SERVER['HTTP_HOST']) ? (empty($_SERVER['HTTP_X_FORWARDED_SERVER']) ? substr(strrchr($boardurl, ':'), 3) : $_SERVER['HTTP_X_FORWARDED_SERVER']) : $_SERVER['HTTP_HOST']) : $_SERVER['REAL_HTTP_HOST'];
		$user['server'] = 'http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off' ? 's' : '') . '://' . $user['host'];

		// The URL in your address bar. Also contains the query string.
		// Do not print this without sanitizing first!
		$user['url'] = (empty($_SERVER['REAL_HTTP_HOST']) ? $user['server'] : substr($user['server'], 0, strpos($user['server'], '/')) . '//' . $_SERVER['HTTP_HOST']) . $_SERVER['REQUEST_URI'];

		// All users (including guests) also belong to the -3 (aka everyone) virtual membergroup.
		$user['groups'] = array_unique(array_merge((array) -3, $user['groups']));

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

		// Just build this here, it makes it easier to change/use - administrators can see all boards.
		if ($user['is_admin'])
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
			if ($user['is_admin'])
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

		// {query_see_topic}, which has basic t.approved tests as well
		// as more elaborate topic privacy, is set up here.
		if ($user['is_admin'])
			$user['query_see_topic'] = '1=1';

		elseif ($user['is_guest'])
			$user['query_see_topic'] = empty($settings['postmod_active']) ? 't.privacy = \'default\'' : '(t.approved = 1 AND t.privacy = \'default\')';

		// If we're in a board, the approve_posts permission may be set for the current topic.
		// If not in a board, rely on mod_cache to see if you can approve this specific topic.
		else
		{
			$user['can_skip_approval'] = empty($settings['postmod_active']) || allowedTo(array('moderate_forum', 'moderate_board', 'approve_posts'));
			$user['query_see_topic'] = '
			(
				t.id_member_started = ' . $id_member . ' OR (' . ($user['can_skip_approval'] ? '' : (empty($user['mod_cache']['ap']) ? '
					t.approved = 1' : '
					(t.approved = 1 OR t.id_board IN (' . implode(', ', $user['mod_cache']['ap']) . '))') . '
					AND') . '
					(
						t.privacy = \'default\'
						OR (t.privacy = \'members\')
						OR (
							t.privacy = \'contacts\'
							AND t.id_member_started != 0
							AND FIND_IN_SET(' . $id_member . ', (SELECT buddy_list FROM ' . $db_prefix . 'members WHERE id_member = t.id_member_started))
						)
					)
				)
			)';
		}

		wesql::register_replacement('query_see_topic', $user['query_see_topic']);
		wesql::register_replacement('query_see_board', $user['query_see_board']);
		wesql::register_replacement('query_list_board', $user['query_list_board']);
		wesql::register_replacement('query_wanna_see_board', $user['query_wanna_see_board']);
		wesql::register_replacement('query_wanna_list_board', $user['query_wanna_list_board']);

		self::$id = $id_member;
		self::$user =& $user;
		self::$is_admin =& $user['is_admin'];
		self::$is_guest =& $user['is_guest'];
	}

	/**
	 * Attempts to detect the browser, including version, needed for browser specific fixes and behaviours, and populates we::$browser with the findings.
	 *
	 * In all cases, general branch as well as major version is detected for, meaning that not only would Internet Explorer 8 be detected, so would Internet Explorer generically. This also sets flags for general emulation behavior later on, plus handling some types of robot.
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
	 * Current OSes detected via self::$browser['os']:
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
		$browser['is_opera'] = strpos($ua, 'Opera') !== false;

		// Detect Webkit and related
		$browser['is_webkit'] = $is_webkit = strpos($ua, 'AppleWebKit') !== false;
		$browser['is_chrome'] = $is_webkit && (strpos($ua, 'Chrome') !== false || strpos($ua, 'CriOS') !== false);
		$browser['is_safari'] = $is_webkit && !$browser['is_chrome'] && strpos($ua, 'Safari') !== false;

		// Detecting broader mobile browsers. Make sure you rely on skin.xml's <mobile> setting in priority.
		$browser['is_mobile'] = !empty(self::$user['is_mobile']);

		// Detect Firefox versions
		$browser['is_gecko'] = !$is_webkit && strpos($ua, 'Gecko') !== false;	// Mozilla and compatible
		$browser['is_firefox'] = strpos($ua, 'Gecko/') !== false;				// Firefox says "Gecko/20xx", not "like Gecko"

		$browser['is_ie'] = $is_ie = strpos($ua, 'MSIE') !== false;

		// Retrieve the version number, as a floating point.
		// Chrome for iOS uses the Safari Mobile string and replaces Version with CriOS.
		preg_match('~' . (
				$browser['is_opera'] || $browser['is_safari'] ? 'version[/ ]' :
				($browser['is_firefox'] ? 'firefox/' :
				($browser['is_ie'] ? 'msie ' :
				($browser['is_chrome'] ? 'c(?:hrome|rios)/' :
				'applewebkit/')))
			) . '([\d.]+)~i', $ua, $ver)
		|| preg_match('~(?:version|opera)[/ ]([\d.]+)~i', $ua, $ver);
		$ver = isset($ver[1]) ? (float) $ver[1] : 0;

		// No need to store version numbers for outdated versions.
		if ($browser['is_opera'])		$ver = max(11, $ver);
		elseif ($browser['is_chrome'])	$ver = max(20, $ver);
		elseif ($browser['is_firefox'])	$ver = $ver < 5 ? max(3, $ver) : max(15, $ver); // Pre-v5 Firefox remains popular.
		elseif ($browser['is_safari'])	$ver = max(4, $ver);
		elseif ($browser['is_ie'])		$ver = max(6, $ver);

		// Reduce to first significant sub-version (if any), e.g. v2.01 => 2, v2.50.3 => 2.5
		$browser['version'] = floor($ver * 10) / 10;

		$browser['is_ie8down'] = $is_ie && $ver <= 8;
		for ($i = 6; $i <= 10; $i++)
			$browser['is_ie' . $i] = $is_ie && $ver == $i;

		// Store our browser name... Start with specific browsers, end with generic engines.
		foreach (array('opera', 'chrome', 'firefox', 'ie', 'safari', 'webkit', 'gecko', '') as $agent)
		{
			$browser['agent'] = $agent;
			if (!$agent || $browser['is_' . $agent])
				break;
		}

		// Determine current OS and version if it can turn out to be useful; currently
		// Windows XP and above, or iOS 4 and above, or Android 2 and above.
		// !! Should we add BlackBerry, Firefox OS and others..?
		$browser['is_windows'] = strpos($ua, 'Windows ') !== false;
		$browser['is_android'] = strpos($ua, 'Android') !== false;
		$browser['is_ios'] = $is_webkit && strpos($ua, '(iP') !== false;
		if ($browser['is_windows'])
		{
			if (preg_match('~Windows(?: NT)? (\d+\.\d+)~', $ua, $ver))
				$os_ver = max(5.1, (float) $ver[1]);
			// Fallback, just to be sure.
			else
				foreach (array('8' => 6.2, '7' => 6.1, 'Vista' => 6, 'XP' => 5.1) as $key => $os_ver)
					if (strpos($ua, 'Windows ' . $key) !== false)
						break;
		}
		elseif ($browser['is_android'] && preg_match('~Android (\d+\.\d)~', $ua, $ver))
			$os_ver = max(2, (float) $ver[1]);
		elseif ($browser['is_ios'] && preg_match('~ OS (\d+(?:_\d))~', $ua, $ver))
			$os_ver = max(3, (float) str_replace('_', '.', $ver[1]));

		$browser['os'] = '';
		foreach (array('windows', 'android', 'ios') as $os)
			if ($browser['is_' . $os])
				$browser['os'] = $os;

		// !! Note that rounding to an integer (instead of the first significant sub-version)
		// could probably help reduce the number of cached files by a large margin. Opinions?
		$browser['os_version'] = isset($os_ver) ? floor($os_ver * 10) / 10 : '';

		// This isn't meant to be reliable, it's just meant to catch most bots to prevent PHPSESSID from showing up.
		$browser['possibly_robot'] = !empty(self::$user['possibly_robot']);

		// Robots shouldn't be logging in or registering. So, they aren't a bot. Better to be wrong than sorry (or people won't be able to log in!), anyway.
		if ((isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('login', 'login2', 'register'))) || !self::$is_guest)
			$browser['possibly_robot'] = false;

		// Save the results...
		self::$browser = $browser;

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

		if (isset($_SERVER['HTTP_ACCEPT']) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/vnd.wap.wml') !== false || strpos($_SERVER['HTTP_ACCEPT'], 'application/vnd.wap.xhtml+xml') !== false))
			return true;

		// Note: Google recommends that Android smartphones indicate the 'Mobile' keyword. Tablets shouldn't have it.
		foreach (explode('|', implode('|', array(
			'Generic' => 'mobile', // Mainly for Android smartphones -- excludes tablets.
			'iOS' => 'iphone|ipod', // iPad tablets can perfectly handle non-mobile layouts...
			'BlackBerry' => 'blackberry',
			'Symbian' => 'symbian',
			'Windows' => 'windows ce|windows phone', // Surface tablets omit the Phone keyword. This should be good enough.
			'PalmOS' => 'palm|avantgo|plucker|xiino',
			'Others' => 'samsung|htc|playstation|nintendo|wap|up.|bolt|opera mobi'
		))) as $device)
			if (strpos($ua, $device) !== false)
				return true;

		return false;
	}

	/**
	 * Alias to the analyzer. Send in a short, simple string.
	 */
	public static function is($string)
	{
		if ($string === (array) $string)
			return self::analyze($string);
		if (isset(self::$cache[$string]))
			return self::$cache[$string];

		if (isset(self::$user['is_' . $string]))
			return self::$cache[$string] = !empty(self::$user['is_' . $string]);
		if (isset(self::$browser['is_' . $string]))
			return self::$cache[$string] = !empty(self::$browser['is_' . $string]);

		return self::$cache[$string] = self::analyze($string);
	}

	/**
	 * Analyzes the given array keys (or comma-separated list), and tries to determine if it encompasses the current browser version.
	 * Can deal with relatively complex strings. e.g., "firefox, !mobile && ie[-7]" means "if browser is Firefox, or is a desktop version of IE 6 or IE 7".
	 * Returns the string that was recognized as the browser, or false if nothing was found.
	 */
	public static function analyze($strings)
	{
		if (!is_array($strings))
			$strings = array_flip(array_map('trim', explode(',', $strings)));

		$browser = self::$browser;
		$a = $browser['agent'];
		$o = $browser['os'];
		$bv = $browser['version'];
		$ov = $browser['os_version'];

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
			$and = strpos($string, '&'); // Is there a && or & in the query? Meaning all parts of this one should return true.
			if ($and !== false)
			{
				$test_all = true;
				foreach (array_map('trim', preg_split('~&+~', $string)) as $finger)
					$test_all &= self::is($finger) !== false;
				if ($test_all)
					return $string;
				continue;
			}

			$bracket = strpos($string, '['); // Is there a version request?
			$real_browser = $bracket === false ? $string : substr($string, 0, $bracket);

			// First, negative tests.
			if ($string[0] === '!')
			{
				$is_os_test = $browser['os'] == substr($real_browser, 1);
				if (empty($browser['is_' . substr($real_browser, 1)]))
					return $string;
				if ($bracket === false)
					continue;
				$split = explode('-', trim(substr($string, $is_os_test ? $olength : $alength, -1), ' ]'));
				$v = $is_os_test ? $ov : $bv;
				if (isset($split[1]))
				{
					if (empty($split[0]) && $v <= $split[1]) continue;	// !ie[-8] (isn't version 8 or earlier)
					if (empty($split[1]) && $v >= $split[0]) continue;	// !ie[6-] (isn't version 6 or later)
					if ($v >= $split[0] && $v <= $split[1]) continue;	// !ie[6-8] (isn't version 6, 7 or 8)
				}
				elseif ($v == $split[0]) continue;						// !ie[8] or !ie[8.0], FWIW...
				return $string;
			}

			// And now, positive tests.
			if (empty($browser['is_' . $real_browser]))
				continue;
			if ($bracket === false)
				return $string;
			$is_os_test = $browser['os'] == $real_browser;
			$split = explode('-', trim(substr($string, $is_os_test ? $olength : $alength, -1), ' ]'));
			$v = $is_os_test ? $ov : $bv;
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
}
