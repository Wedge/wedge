<?php
/**
 * Wedge
 *
 * Handles various security-related tasks, including permissions and filtering of input based on known malicious behavior.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file has the very important job of insuring forum security.  This
	task includes banning and permissions, namely.  It does this by providing
	the following functions:

	void validateSession()
		- makes sure the user is who they claim to be by requiring a
		  password to be typed in every hour.
		- is turned on and off by the securityDisable setting.
		- uses the adminLogin() function of Subs-Auth.php if they need to
		  login, which saves all request (post and get) data.

	void is_not_guest(string message = '')
		- checks if the user is currently a guest, and if so asks them to
		  login with a message telling them why.
		- message is what to tell them when asking them to login.

	void is_not_banned(bool force_check = false)
		- checks if the user is banned, and if so dies with an error.
		- caches this information for optimization purposes.
		- forces a recheck if force_check is true.

	void banPermissions()
		- applies any states of banning by removing permissions the user
		  cannot have.

	void log_ban(array ban_ids = array(), string email = null)
		- log the current user in the ban logs.
		- increment the hit counters for the specified ban ID's (if any.)

	void isBannedEmail(string email, string restriction, string error)
		- check if a given email is banned.
		- performs an immediate ban if the turns turns out positive.

	string checkSession(string type = 'post', string from_action = none,
			is_fatal = true)
		- checks the current session, verifying that the person is who he or
		  she should be.
		- also checks the referrer to make sure they didn't get sent here.
		- depends on the disableCheckUA setting, which is usually missing.
		- will check GET, POST, or REQUEST depending on the passed type.
		- also optionally checks the referring action if passed. (note that
		  the referring action must be by GET.)
		- returns the error message if is_fatal is false.

	bool checkSubmitOnce(string action, bool is_fatal = true)
		- registers a sequence number for a form.
		- checks whether a submitted sequence number is registered in the
		  current session.
		- depending on the value of is_fatal shows an error or returns true or
		  false.
		- frees a sequence number from the stack after it's been checked.
		- frees a sequence number without checking if action == 'free'.

	bool allowedTo(string permission, array boards = current)
		- checks whether the user is allowed to do permission. (ie. post_new.)
		- if boards is specified, checks those boards instead of the current
		  one.
		- always returns true if the user is an administrator.
		- returns true if he or she can do it, false otherwise.

	void isAllowedTo(string permission, array boards = current)
		- uses allowedTo() to check if the user is allowed to do permission.
		- checks the passed boards or current board for the permission.
		- if they are not, it loads the Errors language file and shows an
		  error using $txt['cannot_' . $permission].
		- if they are a guest and cannot do it, this calls is_not_guest().

	array boardsAllowedTo(string permission, bool check_access = false)
		- returns a list of boards on which the user is allowed to do the
		  specified permission.
		- returns an array with only a 0 in it if the user has permission
		  to do this on every board.
		- returns an empty array if he or she cannot do this on any board.
		- if check_access is true will also make sure the group has proper access to that board.

	string showEmailAddress(string userProfile_hideEmail, int userProfile_id)
		- returns whether an email address should be shown and how.
		- possible outcomes are
			'yes': show the full email address
			'yes_permission_override': show the full email address, either you
			  are a moderator or it's your own email address.
			'no_through_forum': don't show the email address, but do allow
			  things to be mailed using the built-in forum mailer.
			'no': keep the email address hidden.
*/

// Check if the user is who he/she says he is
function validateSession()
{
	global $modSettings, $user_info, $sc, $user_settings;

	// We don't care if the option is off, because Guests should NEVER get past here.
	is_not_guest();

	// If we're using XML give an additional ten minutes grace as an admin can't log on in XML mode.
	$refreshTime = isset($_GET['xml']) ? 4200 : 3600;

	// Is the security option off?  Or are they already logged in?
	if (!empty($modSettings['securityDisable']) || (!empty($_SESSION['admin_time']) && $_SESSION['admin_time'] + $refreshTime >= time()))
		return;

	loadSource('Subs-Auth');

	// Hashed password, ahoy!
	if (isset($_POST['admin_hash_pass']) && strlen($_POST['admin_hash_pass']) == 40)
	{
		checkSession();

		$good_password = in_array(true, call_hook('verify_password', array($user_info['username'], $_POST['admin_hash_pass'], true)), true);

		if ($good_password || $_POST['admin_hash_pass'] == sha1($user_info['passwd'] . $sc))
		{
			$_SESSION['admin_time'] = time();
			return;
		}
	}
	// Posting the password... check it.
	if (isset($_POST['admin_pass']))
	{
		checkSession();

		$good_password = in_array(true, call_hook('verify_password', array($user_info['username'], $_POST['admin_pass'], false)), true);

		// Password correct?
		if ($good_password || sha1(strtolower($user_info['username']) . $_POST['admin_pass']) == $user_info['passwd'])
		{
			$_SESSION['admin_time'] = time();
			return;
		}
	}
	// OpenID?
	if (!empty($user_settings['openid_uri']))
	{
		loadSource('Subs-OpenID');
		smf_openID_revalidate();

		$_SESSION['admin_time'] = time();
		return;
	}

	// Need to type in a password for that, man.
	adminLogin();
}

// Require a user who is logged in. (not a guest.)
function is_not_guest($message = '')
{
	global $user_info, $txt, $context, $scripturl, $modSettings;

	// Luckily, this person isn't a guest.
	if (!$user_info['is_guest'])
		return;

	// No need to clear the action they were doing (as it used to be; not only are the odds strong that it wouldn't have been updated, this way you can see what they were trying to do and that it didn't work)
	// But we do need to update the online log.
	if (!empty($modSettings['who_enabled']))
		$_GET['who_warn'] = 1;
	writeLog(true);

	// Just die.
	if (isset($_REQUEST['xml']))
		obExit(false);

	// Attempt to detect if they came from dlattach.
	if (!WIRELESS && SMF != 'SSI' && empty($context['theme_loaded']))
		loadTheme();

	// Never redirect to an attachment
	if (strpos($_SERVER['REQUEST_URL'], 'dlattach') === false)
		$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];

	// Load the Login template and language file.
	loadLanguage('Login');

	// Are we in wireless mode?
	if (WIRELESS)
	{
		$context['login_error'] = $message ? $message : $txt['only_members_can_access'];
		loadSubTemplate(WIRELESS_PROTOCOL . '_login');
	}
	// Apparently we're not in a position to handle this now. Let's go to a safer location for now.
	elseif (empty($context['template_layers']))
	{
		$_SESSION['login_url'] = $scripturl . '?' . $_SERVER['QUERY_STRING'];
		redirectexit('action=login');
	}
	else
	{
		loadTemplate('Login');
		loadSubTemplate('kick_guest');
		$context['robot_no_index'] = true;
	}

	// Use the kick_guest sub template...
	$context['kick_message'] = $message;
	$context['page_title'] = $txt['login'];

	obExit();

	// We should never get to this point, but if we did we wouldn't know the user isn't a guest.
	trigger_error('Hacking attempt...', E_USER_ERROR);
}

// Do banning related stuff.  (ie. disallow access....)
function is_not_banned($forceCheck = false)
{
	global $txt, $modSettings, $context, $user_info;
	global $cookiename, $user_settings;

	// You cannot be banned if you are an admin - doesn't help if you log out.
	if ($user_info['is_admin'])
		return;

	// Only check the ban every so often. (to reduce load.)
	if ($forceCheck || !isset($_SESSION['ban']) || empty($modSettings['banLastUpdated']) || ($_SESSION['ban']['last_checked'] < $modSettings['banLastUpdated']) || $_SESSION['ban']['id_member'] != $user_info['id'] || $_SESSION['ban']['ip'] != $user_info['ip'] || $_SESSION['ban']['ip2'] != $user_info['ip2'] || (isset($user_info['email'], $_SESSION['ban']['email']) && $_SESSION['ban']['email'] != $user_info['email']))
	{
		// Innocent until proven guilty.  (but we know you are! :P)
		$_SESSION['ban'] = array(
			'last_checked' => time(),
			'id_member' => $user_info['id'],
			'ip' => $user_info['ip'],
			'ip2' => $user_info['ip2'],
			'email' => $user_info['email'],
		);

		$ban_query = array();
		$ban_query_vars = array('current_time' => time());
		$flag_is_activated = false;

		// Check both IP addresses.
		foreach (array('ip', 'ip2') as $ip_number)
		{
			// Check if we have a valid IP address.
			if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $user_info[$ip_number], $ip_parts) == 1)
			{
				$ban_query[] = '((' . $ip_parts[1] . ' BETWEEN bi.ip_low1 AND bi.ip_high1)
							AND (' . $ip_parts[2] . ' BETWEEN bi.ip_low2 AND bi.ip_high2)
							AND (' . $ip_parts[3] . ' BETWEEN bi.ip_low3 AND bi.ip_high3)
							AND (' . $ip_parts[4] . ' BETWEEN bi.ip_low4 AND bi.ip_high4))';

				// IP was valid, maybe there's also a hostname...
				if (empty($modSettings['disableHostnameLookup']))
				{
					$hostname = host_from_ip($user_info[$ip_number]);
					if (strlen($hostname) > 0)
					{
						$ban_query[] = '({string:hostname} LIKE bi.hostname)';
						$ban_query_vars['hostname'] = $hostname;
					}
				}
			}
			// We use '255.255.255.255' for 'unknown' since it's not valid anyway.
			elseif ($user_info['ip'] == 'unknown')
				$ban_query[] = '(bi.ip_low1 = 255 AND bi.ip_high1 = 255
							AND bi.ip_low2 = 255 AND bi.ip_high2 = 255
							AND bi.ip_low3 = 255 AND bi.ip_high3 = 255
							AND bi.ip_low4 = 255 AND bi.ip_high4 = 255)';
		}

		// Is their email address banned?
		if (strlen($user_info['email']) != 0)
		{
			$ban_query[] = '({string:email} LIKE bi.email_address)';
			$ban_query_vars['email'] = $user_info['email'];
		}

		// How about this user?
		if (!$user_info['is_guest'] && !empty($user_info['id']))
		{
			$ban_query[] = 'bi.id_member = {int:id_member}';
			$ban_query_vars['id_member'] = $user_info['id'];
		}

		// Check the ban, if there's information.
		if (!empty($ban_query))
		{
			$restrictions = array(
				'cannot_access',
				'cannot_login',
				'cannot_post',
				'cannot_register',
			);
			$request = wesql::query('
				SELECT bi.id_ban, bi.email_address, bi.id_member, bg.cannot_access, bg.cannot_register,
					bg.cannot_post, bg.cannot_login, bg.reason, IFNULL(bg.expire_time, 0) AS expire_time
				FROM {db_prefix}ban_items AS bi
					INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time}))
				WHERE
					(' . implode(' OR ', $ban_query) . ')',
				$ban_query_vars
			);
			// Store every type of ban that applies to you in your session.
			while ($row = wesql::fetch_assoc($request))
			{
				foreach ($restrictions as $restriction)
					if (!empty($row[$restriction]))
					{
						$_SESSION['ban'][$restriction]['reason'] = $row['reason'];
						$_SESSION['ban'][$restriction]['ids'][] = $row['id_ban'];
						if (!isset($_SESSION['ban']['expire_time']) || ($_SESSION['ban']['expire_time'] != 0 && ($row['expire_time'] == 0 || $row['expire_time'] > $_SESSION['ban']['expire_time'])))
							$_SESSION['ban']['expire_time'] = $row['expire_time'];

						if (!$user_info['is_guest'] && $restriction == 'cannot_access' && ($row['id_member'] == $user_info['id'] || $row['email_address'] == $user_info['email']))
							$flag_is_activated = true;
					}
			}
			wesql::free_result($request);
		}

		// Mark the cannot_access and cannot_post bans as being 'hit'.
		if (isset($_SESSION['ban']['cannot_access']) || isset($_SESSION['ban']['cannot_post']) || isset($_SESSION['ban']['cannot_login']))
			log_ban(array_merge(isset($_SESSION['ban']['cannot_access']) ? $_SESSION['ban']['cannot_access']['ids'] : array(), isset($_SESSION['ban']['cannot_post']) ? $_SESSION['ban']['cannot_post']['ids'] : array(), isset($_SESSION['ban']['cannot_login']) ? $_SESSION['ban']['cannot_login']['ids'] : array()));

		// If for whatever reason the is_activated flag seems wrong, do a little work to clear it up.
		if ($user_info['id'] && (($user_settings['is_activated'] >= 10 && !$flag_is_activated)
			|| ($user_settings['is_activated'] < 10 && $flag_is_activated)))
		{
			loadSource('ManageBans');
			updateBanMembers();
		}
	}

	// Hey, I know you! You're ehm...
	if (!isset($_SESSION['ban']['cannot_access']) && !empty($_COOKIE[$cookiename . '_']))
	{
		$bans = explode(',', $_COOKIE[$cookiename . '_']);
		foreach ($bans as $key => $value)
			$bans[$key] = (int) $value;
		$request = wesql::query('
			SELECT bi.id_ban, bg.reason
			FROM {db_prefix}ban_items AS bi
				INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
			WHERE bi.id_ban IN ({array_int:ban_list})
				AND (bg.expire_time IS NULL OR bg.expire_time > {int:current_time})
				AND bg.cannot_access = {int:cannot_access}
			LIMIT ' . count($bans),
			array(
				'cannot_access' => 1,
				'ban_list' => $bans,
				'current_time' => time(),
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$_SESSION['ban']['cannot_access']['ids'][] = $row['id_ban'];
			$_SESSION['ban']['cannot_access']['reason'] = $row['reason'];
		}
		wesql::free_result($request);

		// My mistake. Next time better.
		if (!isset($_SESSION['ban']['cannot_access']))
		{
			loadSource('Subs-Auth');
			$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));
			setcookie($cookiename . '_', '', time() - 3600, $cookie_url[1], $cookie_url[0], 0, true);
		}
	}

	// If you're fully banned, it's end of the story for you.
	if (isset($_SESSION['ban']['cannot_access']))
	{
		// We don't wanna see you!
		if (!$user_info['is_guest'])
			wesql::query('
				DELETE FROM {db_prefix}log_online
				WHERE id_member = {int:current_member}',
				array(
					'current_member' => $user_info['id'],
				)
			);

		// 'Log' the user out.  Can't have any funny business... (save the name!)
		$old_name = isset($user_info['name']) && $user_info['name'] != '' ? $user_info['name'] : $txt['guest_title'];
		$user_info['name'] = '';
		$user_info['username'] = '';
		$user_info['is_guest'] = true;
		$user_info['is_admin'] = false;
		$user_info['permissions'] = array();
		$user_info['id'] = 0;
		$context['user'] = array(
			'id' => 0,
			'username' => '',
			'name' => $txt['guest_title'],
			'is_guest' => true,
			'is_logged' => false,
			'is_admin' => false,
			'is_mod' => false,
			'can_mod' => false,
			'language' => $user_info['language'],
		);

		// A goodbye present.
		loadSource('Subs-Auth');
		$cookie_url = url_parts(!empty($modSettings['localCookies']), !empty($modSettings['globalCookies']));
		setcookie($cookiename . '_', implode(',', $_SESSION['ban']['cannot_access']['ids']), time() + 3153600, $cookie_url[1], $cookie_url[0], 0, true);

		// Don't scare anyone, now.
		$_GET['action'] = '';
		$_GET['board'] = '';
		$_GET['topic'] = '';
		writeLog(true);

		// You banned, sucka!
		fatal_error(sprintf($txt['your_ban'], $old_name) . (empty($_SESSION['ban']['cannot_access']['reason']) ? '' : '<br>' . $_SESSION['ban']['cannot_access']['reason']) . '<br>' . (!empty($_SESSION['ban']['expire_time']) ? sprintf($txt['your_ban_expires'], timeformat($_SESSION['ban']['expire_time'], false)) : $txt['your_ban_expires_never']), 'user');

		// If we get here, something's gone wrong.... but let's try anyway.
		trigger_error('Hacking attempt...', E_USER_ERROR);
	}
	// You're not allowed to log in but yet you are. Let's fix that.
	elseif (isset($_SESSION['ban']['cannot_login']) && !$user_info['is_guest'])
	{
		// We don't wanna see you!
		wesql::query('
			DELETE FROM {db_prefix}log_online
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
			)
		);

		// 'Log' the user out.  Can't have any funny business... (save the name!)
		$old_name = isset($user_info['name']) && $user_info['name'] != '' ? $user_info['name'] : $txt['guest_title'];
		$user_info['name'] = '';
		$user_info['username'] = '';
		$user_info['is_guest'] = true;
		$user_info['is_admin'] = false;
		$user_info['permissions'] = array();
		$user_info['id'] = 0;
		$context['user'] = array(
			'id' => 0,
			'username' => '',
			'name' => $txt['guest_title'],
			'is_guest' => true,
			'is_logged' => false,
			'is_admin' => false,
			'is_mod' => false,
			'can_mod' => false,
			'language' => $user_info['language'],
		);

		// SMF's Wipe 'n Clean(r) erases all traces.
		$_GET['action'] = '';
		$_GET['board'] = '';
		$_GET['topic'] = '';
		writeLog(true);

		loadSource('Logout');
		Logout(true, false);

		fatal_error(sprintf($txt['your_ban'], $old_name) . (empty($_SESSION['ban']['cannot_login']['reason']) ? '' : '<br>' . $_SESSION['ban']['cannot_login']['reason']) . '<br>' . (!empty($_SESSION['ban']['expire_time']) ? sprintf($txt['your_ban_expires'], timeformat($_SESSION['ban']['expire_time'], false)) : $txt['your_ban_expires_never']) . '<br>' . $txt['ban_continue_browse'], 'user');
	}

	// Fix up the banning permissions.
	if (isset($user_info['permissions']))
		banPermissions();
}

// Fix permissions according to ban status.
function banPermissions()
{
	global $user_info, $modSettings, $context;

	// Somehow they got here, at least take away all permissions...
	if (isset($_SESSION['ban']['cannot_access']))
		$user_info['permissions'] = array();
	// Okay, well, you can watch, but don't touch a thing.
	elseif (isset($_SESSION['ban']['cannot_post']) || (!empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $user_info['warning']))
	{
		$denied_permissions = array(
			'pm_send',
			'calendar_post', 'calendar_edit_own', 'calendar_edit_any',
			'poll_post',
			'poll_add_own', 'poll_add_any',
			'poll_edit_own', 'poll_edit_any',
			'poll_lock_own', 'poll_lock_any',
			'poll_remove_own', 'poll_remove_any',
			'manage_attachments', 'manage_smileys', 'manage_boards', 'admin_forum', 'manage_permissions',
			'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news',
			'profile_identity_any', 'profile_extra_any', 'profile_title_any',
			'post_new', 'post_reply_own', 'post_reply_any',
			'delete_own', 'delete_any', 'delete_replies',
			'make_sticky',
			'merge_any', 'split_any',
			'modify_own', 'modify_any', 'modify_replies',
			'move_any',
			'send_topic',
			'lock_own', 'lock_any',
			'remove_own', 'remove_any',
			'post_unapproved_topics', 'post_unapproved_replies_own', 'post_unapproved_replies_any',
		);
		$user_info['permissions'] = array_diff($user_info['permissions'], $denied_permissions);
	}
	// Are they absolutely under moderation?
	elseif (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $user_info['warning'])
	{
		// Work out what permissions should change...
		$permission_change = array(
			'post_new' => 'post_unapproved_topics',
			'post_reply_own' => 'post_unapproved_replies_own',
			'post_reply_any' => 'post_unapproved_replies_any',
			'post_attachment' => 'post_unapproved_attachments',
		);
		foreach ($permission_change as $old => $new)
		{
			if (!in_array($old, $user_info['permissions']))
				unset($permission_change[$old]);
			else
				$user_info['permissions'][] = $new;
		}
		$user_info['permissions'] = array_diff($user_info['permissions'], array_keys($permission_change));
	}

	//!!! Find a better place to call this? Needs to be after permissions loaded!
	// Finally, some bits we cache in the session because it saves queries.
	if (isset($_SESSION['mc']) && $_SESSION['mc']['time'] > $modSettings['settings_updated'] && $_SESSION['mc']['id'] == $user_info['id'])
		$user_info['mod_cache'] = $_SESSION['mc'];
	else
	{
		loadSource('Subs-Auth');
		rebuildModCache();
	}

	// Now that we have the mod cache taken care of, let's setup a cache for the number of mod reports still open
	if (isset($_SESSION['rc']) && $_SESSION['rc']['time'] > $modSettings['last_mod_report_action'] && $_SESSION['rc']['id'] == $user_info['id'])
		$context['open_mod_reports'] = $_SESSION['rc']['reports'];
	elseif ($_SESSION['mc']['bq'] != '0=1')
	{
		loadSource('ModerationCenter');
		recountOpenReports();
	}
	else
		$context['open_mod_reports'] = 0;
}

// Log a ban in the database.
function log_ban($ban_ids = array(), $email = null)
{
	global $user_info;

	// Don't log web accelerators, it's very confusing...
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
		return;

	wesql::insert('',
		'{db_prefix}log_banned',
		array('id_member' => 'int', 'ip' => 'int', 'email' => 'string', 'log_time' => 'int'),
		array($user_info['id'], get_ip_identifier($user_info['ip']), ($email === null ? ($user_info['is_guest'] ? '' : $user_info['email']) : $email), time()),
		array('id_ban_log')
	);

	// One extra point for these bans.
	if (!empty($ban_ids))
		wesql::query('
			UPDATE {db_prefix}ban_items
			SET hits = hits + 1
			WHERE id_ban IN ({array_int:ban_ids})',
			array(
				'ban_ids' => $ban_ids,
			)
		);
}

// Checks if a given email address might be banned.
function isBannedEmail($email, $restriction, $error)
{
	global $txt;

	// Can't ban an empty email
	if (empty($email) || trim($email) == '')
		return;

	// Let's start with the bans based on your IP/hostname/memberID...
	$ban_ids = isset($_SESSION['ban'][$restriction]) ? $_SESSION['ban'][$restriction]['ids'] : array();
	$ban_reason = isset($_SESSION['ban'][$restriction]) ? $_SESSION['ban'][$restriction]['reason'] : '';

	// ...and add to that the email address you're trying to register.
	$request = wesql::query('
		SELECT bi.id_ban, bg.' . $restriction . ', bg.cannot_access, bg.reason
		FROM {db_prefix}ban_items AS bi
			INNER JOIN {db_prefix}ban_groups AS bg ON (bg.id_ban_group = bi.id_ban_group)
		WHERE {string:email} LIKE bi.email_address
			AND (bg.' . $restriction . ' = {int:cannot_access} OR bg.cannot_access = {int:cannot_access})
			AND (bg.expire_time IS NULL OR bg.expire_time >= {int:now})',
		array(
			'email' => $email,
			'cannot_access' => 1,
			'now' => time(),
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		if (!empty($row['cannot_access']))
		{
			$_SESSION['ban']['cannot_access']['ids'][] = $row['id_ban'];
			$_SESSION['ban']['cannot_access']['reason'] = $row['reason'];
		}
		if (!empty($row[$restriction]))
		{
			$ban_ids[] = $row['id_ban'];
			$ban_reason = $row['reason'];
		}
	}
	wesql::free_result($request);

	// You're in biiig trouble.  Banned for the rest of this session!
	if (isset($_SESSION['ban']['cannot_access']))
	{
		log_ban($_SESSION['ban']['cannot_access']['ids']);
		$_SESSION['ban']['last_checked'] = time();

		fatal_error(sprintf($txt['your_ban'], $txt['guest_title']) . $_SESSION['ban']['cannot_access']['reason'], false);
	}

	if (!empty($ban_ids))
	{
		// Log this ban for future reference.
		log_ban($ban_ids, $email);
		fatal_error($error . $ban_reason, false);
	}
}

// Make sure the user's correct session was passed, and they came from here. (type can be post, get, or request.)
function checkSession($type = 'post', $from_action = '', $is_fatal = true)
{
	global $sc, $modSettings, $boardurl;

	// Is it in as $_POST['sc']?
	if ($type == 'post')
	{
		$check = isset($_POST[$_SESSION['session_var']]) ? $_POST[$_SESSION['session_var']] : (empty($modSettings['strictSessionCheck']) && isset($_POST['sc']) ? $_POST['sc'] : null);
		if ($check !== $sc)
			$error = 'session_timeout';
	}

	// How about $_GET['sesc']?
	elseif ($type == 'get')
	{
		$check = isset($_GET[$_SESSION['session_var']]) ? $_GET[$_SESSION['session_var']] : (empty($modSettings['strictSessionCheck']) && isset($_GET['sesc']) ? $_GET['sesc'] : null);
		if ($check !== $sc)
			$error = 'session_verify_fail';
	}

	// Or can it be in either?
	elseif ($type == 'request')
	{
		$check = isset($_GET[$_SESSION['session_var']]) ? $_GET[$_SESSION['session_var']] : (empty($modSettings['strictSessionCheck']) && isset($_GET['sesc']) ? $_GET['sesc'] : (isset($_POST[$_SESSION['session_var']]) ? $_POST[$_SESSION['session_var']] : (empty($modSettings['strictSessionCheck']) && isset($_POST['sc']) ? $_POST['sc'] : null)));

		if ($check !== $sc)
			$error = 'session_verify_fail';
	}

	// Verify that they aren't changing user agents on us - that could be bad.
	if ((!isset($_SESSION['USER_AGENT']) || $_SESSION['USER_AGENT'] != $_SERVER['HTTP_USER_AGENT']) && empty($modSettings['disableCheckUA']))
		$error = 'session_verify_fail';

	// Make sure a page with session check requirement is not being prefetched.
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		header('HTTP/1.1 403 Forbidden');
		die;
	}

	// Check the referring site - it should be the same server at least!
	$referrer = isset($_SERVER['HTTP_REFERER']) ? @parse_url($_SERVER['HTTP_REFERER']) : array();
	if (!empty($referrer['host']))
	{
		if (strpos($_SERVER['HTTP_HOST'], ':') !== false)
			$real_host = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
		else
			$real_host = $_SERVER['HTTP_HOST'];

		$parsed_url = parse_url($boardurl);

		// Are global cookies on?  If so, let's check them ;).
		if (!empty($modSettings['globalCookies']))
		{
			if (preg_match('~(?:[^.]+\.)?([^.]{3,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
				$parsed_url['host'] = $parts[1];

			if (preg_match('~(?:[^.]+\.)?([^.]{3,}\..+)\z~i', $referrer['host'], $parts) == 1)
				$referrer['host'] = $parts[1];

			if (preg_match('~(?:[^.]+\.)?([^.]{3,}\..+)\z~i', $real_host, $parts) == 1)
				$real_host = $parts[1];
		}

		// Okay: referrer must either match parsed_url or real_host.
		if (isset($parsed_url['host']) && strtolower($referrer['host']) != strtolower($parsed_url['host']) && strtolower($referrer['host']) != strtolower($real_host))
		{
			$error = 'verify_url_fail';
			$log_error = true;
		}
	}

	// Well, first of all, if a from_action is specified you'd better have an old_url.
	if (!empty($from_action) && (!isset($_SESSION['old_url']) || preg_match('~[?;&]action=' . $from_action . '([;&]|$)~', $_SESSION['old_url']) == 0))
	{
		$error = 'verify_url_fail';
		$log_error = true;
	}

	if (strtolower($_SERVER['HTTP_USER_AGENT']) == 'hacker')
		fatal_error('Sound the alarm!  It\'s a hacker!  Close the castle gates!!', false);

	// Everything is ok, return an empty string.
	if (!isset($error))
		return '';
	// A session error occurred, show the error.
	elseif ($is_fatal)
	{
		if (isset($_GET['xml']))
		{
			ob_end_clean();
			header('HTTP/1.1 403 Forbidden - Session timeout');
			die;
		}
		else
			fatal_lang_error($error, isset($log_error) ? 'user' : false);
	}
	// A session error occurred, return the error to the calling function.
	else
		return $error;

	// We really should never fall through here, for very important reasons.  Let's make sure.
	trigger_error('Hacking attempt...', E_USER_ERROR);
}

// Check if a specific confirm parameter was given.
function checkConfirm($action)
{
	global $modSettings;

	if (isset($_GET['confirm'], $_SESSION['confirm_' . $action]) && md5($_GET['confirm'] . $_SERVER['HTTP_USER_AGENT']) == $_SESSION['confirm_' . $action])
		return true;

	else
	{
		$token = md5(mt_rand() . session_id() . (string) microtime() . $modSettings['rand_seed']);
		$_SESSION['confirm_' . $action] = md5($token . $_SERVER['HTTP_USER_AGENT']);

		return $token;
	}
}

// Check whether a form has been submitted twice.
function checkSubmitOnce($action, $is_fatal = true)
{
	global $context;

	if (!isset($_SESSION['forms']))
		$_SESSION['forms'] = array();

	// Register a form number and store it in the session stack. (use this on the page that has the form.)
	if ($action == 'register')
	{
		$context['form_sequence_number'] = 0;
		while (empty($context['form_sequence_number']) || in_array($context['form_sequence_number'], $_SESSION['forms']))
			$context['form_sequence_number'] = mt_rand(1, 16000000);
	}
	// Check whether the submitted number can be found in the session.
	elseif ($action == 'check')
	{
		if (!isset($_REQUEST['seqnum']))
			return true;
		elseif (!in_array($_REQUEST['seqnum'], $_SESSION['forms']))
		{
			$_SESSION['forms'][] = (int) $_REQUEST['seqnum'];
			return true;
		}
		elseif ($is_fatal)
			fatal_lang_error('error_form_already_submitted', false);
		else
			return false;
	}
	// Don't check, just free the stack number.
	elseif ($action == 'free' && isset($_REQUEST['seqnum']) && in_array($_REQUEST['seqnum'], $_SESSION['forms']))
		$_SESSION['forms'] = array_diff($_SESSION['forms'], array($_REQUEST['seqnum']));
	elseif ($action != 'free')
		trigger_error('checkSubmitOnce(): Invalid action \'' . $action . '\'', E_USER_WARNING);
}

// Check the user's permissions.
function allowedTo($permission, $boards = null)
{
	global $user_info, $modSettings;

	// You're always allowed to do nothing. (unless you're a working man, MR. LAZY :P!)
	if (empty($permission))
		return true;

	// You're never allowed to do something if your data hasn't been loaded yet!
	if (empty($user_info))
		return false;

	// Administrators are supermen :P.
	if ($user_info['is_admin'])
		return true;

	// Are we checking the _current_ board, or some other boards?
	if ($boards === null)
	{
		// Check if they can do it.
		if (!is_array($permission) && in_array($permission, $user_info['permissions']))
			return true;
		// Search for any of a list of permissions.
		elseif (is_array($permission) && count(array_intersect($permission, $user_info['permissions'])) != 0)
			return true;
		// You aren't allowed, by default.
		else
			return false;
	}
	elseif (!is_array($boards))
		$boards = array($boards);

	$request = wesql::query('
		SELECT MIN(bp.add_deny) AS add_deny
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}board_permissions AS bp ON (bp.id_profile = b.id_profile)
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
		WHERE b.id_board IN ({array_int:board_list})
			AND bp.id_group IN ({array_int:group_list}, {int:moderator_group})
			AND bp.permission {raw:permission_list}
			AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})
		GROUP BY b.id_board',
		array(
			'current_member' => $user_info['id'],
			'board_list' => $boards,
			'group_list' => $user_info['groups'],
			'moderator_group' => 3,
			'permission_list' => is_array($permission) ? 'IN (\'' . implode('\', \'', $permission) . '\')' : ' = \'' . $permission . '\'',
		)
	);

	// Make sure they can do it on all of the boards.
	if (wesql::num_rows($request) != count($boards))
		return false;

	$result = true;
	while ($row = wesql::fetch_assoc($request))
		$result &= !empty($row['add_deny']);
	wesql::free_result($request);

	// If the query returned 1, they can do it... otherwise, they can't.
	return $result;
}

// Fatal error if they cannot...
function isAllowedTo($permission, $boards = null)
{
	global $user_info, $txt;

	static $heavy_permissions = array(
		'admin_forum',
		'manage_attachments',
		'manage_smileys',
		'manage_boards',
		'edit_news',
		'moderate_forum',
		'manage_bans',
		'manage_membergroups',
		'manage_permissions',
	);

	// Make it an array, even if a string was passed.
	$permission = is_array($permission) ? $permission : array($permission);

	// Check the permission and return an error...
	if (!allowedTo($permission, $boards))
	{
		// Pick the last array entry as the permission shown as the error.
		$error_permission = array_shift($permission);

		// If they are a guest, show a login. (because the error might be gone if they do!)
		if ($user_info['is_guest'])
		{
			loadLanguage('Errors');
			is_not_guest($txt['cannot_' . $error_permission]);
		}

		// No need to clear the action they were doing (as it used to be; not only are the odds strong that it wouldn't have been updated, this way you can see what they were trying to do and that it didn't work)
		writeLog(true);

		fatal_lang_error('cannot_' . $error_permission, false);

		// Getting this far is a really big problem, but let's try our best to prevent any cases...
		trigger_error('Hacking attempt...', E_USER_ERROR);
	}

	// If you're doing something on behalf of some "heavy" permissions, validate your session.
	// (take out the heavy permissions, and if you can't do anything but those, you need a validated session.)
	if (!allowedTo(array_diff($permission, $heavy_permissions), $boards))
		validateSession();
}

// Return the boards a user has a certain (board) permission on. (array(0) if all.)
function boardsAllowedTo($permissions, $check_access = true)
{
	global $user_info, $modSettings;

	// Administrators are all powerful, sorry.
	if ($user_info['is_admin'])
		return array(0);

	// Arrays are nice, most of the time.
	if (!is_array($permissions))
		$permissions = array($permissions);

	// All groups the user is in except 'moderator'.
	$groups = array_diff($user_info['groups'], array(3));

	// Guest and guest access disabled?
	if ($user_info['is_guest'] && empty($modSettings['allow_guestAccess']))
		return array();

	$request = wesql::query('
		SELECT b.id_board, bp.add_deny
		FROM {db_prefix}board_permissions AS bp
			INNER JOIN {db_prefix}boards AS b ON (b.id_profile = bp.id_profile)
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
		WHERE bp.id_group IN ({array_int:group_list}, {int:moderator_group})
			AND bp.permission IN ({array_string:permissions})
			AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})' .
			($check_access ? ' AND {query_see_board}' : ''),
		array(
			'current_member' => $user_info['id'],
			'group_list' => $groups,
			'moderator_group' => 3,
			'permissions' => $permissions,
		)
	);
	$boards = array();
	$deny_boards = array();
	while ($row = wesql::fetch_assoc($request))
	{
		if (empty($row['add_deny']))
			$deny_boards[] = $row['id_board'];
		else
			$boards[] = $row['id_board'];
	}
	wesql::free_result($request);

	$boards = array_unique(array_values(array_diff($boards, $deny_boards)));

	return $boards;
}

function showEmailAddress($userProfile_hideEmail, $userProfile_id)
{
	global $modSettings, $user_info;

	// Should this users email address be shown?
	// If you're guest and the forum is set to hide email for guests: no.
	// If the user is post-banned: no.
	// If it's your own profile and you've set your address hidden: yes_permission_override.
	// If you're a moderator with sufficient permissions: yes_permission_override.
	// If the user has set their email address to be hidden: no.
	// If the forum is set to show full email addresses: yes.
	// Otherwise: no_through_forum.

	return (!empty($modSettings['guest_hideContacts']) && $user_info['is_guest']) || isset($_SESSION['ban']['cannot_post']) ? 'no' : ((!$user_info['is_guest'] && $user_info['id'] == $userProfile_id && !$userProfile_hideEmail) || allowedTo('moderate_forum') ? 'yes_permission_override' : ($userProfile_hideEmail ? 'no' : (!empty($modSettings['make_email_viewable']) ? 'yes' : 'no_through_forum')));
}

/**
 * Attempt to validate the request against defined white- and black-lists.
 *
 * Part of the anti-spam measures include whitelist and blacklist items to be screened against. This is where such are processed.
 *
 * Much of the functionality is derived from Bad Behavior, http://www.bad-behavior.ioerror.us/ which is licensed under the GNU LGPL 3.0 and used herein under clause 4.
 * As such, we are required to make reference to the GNU GPL 3.0 - http://www.gnu.org/licenses/gpl-3.0.html - and its child licence GNU LGPL 3.0 - http://www.gnu.org/licenses/lgpl-3.0.html - it is acknowledged that we have not included the full licence text with the package, because the core package is not itself GPL/LGPL licensed and we believe it would lead to confusion if multiple licence documents were provided. We will seek to provide clarification where this is necessary.
 *
 * @return mixed Returns true if whitelisted, or not confirmed as spammer in any way; false if matching any rules but the current user is an administrator (so they receive an alternate warning), otherwise execution will be suspended and the request directed to an appropriate error page.
 */
function checkUserBehavior()
{
	global $context, $modSettings, $user_info, $txt, $webmaster_email;

	$context['http_headers'] = get_http_headers();
	// Did we get any additional headers that wouldn't normally be picked up for any reason?
	if (isset($context['additional_headers']))
		$context['http_headers'] = array_merge($context['http_headers'], $context['additional_headers']);

	// Some administrators may wish to whitelist specific IPs (like their own), specific user-agents or specific actions from being processed.
	// Use with caution. A spurious whitelist match will override any other measure.
	$whitelist = array(
		'ip' => array(
			'10.0.0.0/8',
			'172.16.0.0/12',
			'192.168.0.0/16',
		),
		'user-agent' => array(
		),
		'action' => array(
		),
	);

	if (!empty($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != 'unknown')
		foreach ($whitelist['ip'] as $item)
		{
			if (strpos($item, '/') === false)
			{
				if ($_SERVER['REMOTE_ADDR'] === $item)
					return true;
			}
			// This is intentional. We only want to end up here if the URL contains a /, rather than if it contains a / or it doesn't but didn't match the user.
			elseif (match_cidr($_SERVER['REMOTE_ADDR'], $item))
				return true;
		}

	if (!empty($context['http_headers']['User-Agent']))
	{
		foreach ($whitelist['user-agent'] as $item)
			if ($context['http_headers']['User-Agent'] === $item)
				return true;
	}
	else
		$context['http_headers']['User-Agent'] = ''; // Just in case we didn't actually get one.

	if (!empty($_GET['action']))
		foreach ($whitelist['action'] as $item)
			if ($_GET['action'] === $item)
				return true;

	// So they didn't get whitelisted, eh? Well, are they blacklisted?
	$context['behavior_error'] = '';

	if (checkUserRequest_blacklist() || checkUserRequest_request() || checkUserRequest_useragent() || checkUserRequest_post())
	{
		if ($user_info['is_admin'])
			return false;
		else
		{
			list ($error, $error_blocks) = userBehaviorResponse();
			header('HTTP/1.1 ' . $error . ' Wedge Defenses');
			header('Status: ' . $error . ' Wedge Defenses');

			// OK, let's start up enough of Wedge to display this error nicely.
			$context['linktree'] = array();
			loadPermissions();
			loadTheme();

			// Process the headers into a nice string then log the intrusion.
			$headers = '';
			$entity = '';

			foreach ($context['http_headers'] as $k => $v)
				if ($k != 'User-Agent')
					$headers .= ($headers != '' ? '<br>' : '') . htmlspecialchars($k . '=' . $v);

			$entity = htmlspecialchars(implode("\n", $_POST));
			foreach ($_POST as $k => $v)
				$entity .= ($entity != '' ? '<br>' : '') . htmlspecialchars($k . '=' . $v);

			wesql::insert('insert',
				'{db_prefix}log_intrusion',
				array(
					'id_member' => 'int', 'error_type' => 'string', 'ip' => 'int', 'event_time' => 'int', 'http_method' => 'string',
					'request_uri' => 'string', 'protocol' => 'string', 'user_agent' => 'string', 'headers' => 'string', 'request_entity' => 'string',
				),
				array(
					$user_info['id'], substr($context['behavior_error'], 6), get_ip_identifier($_SERVER['REMOTE_ADDR']), time(), $_SERVER['REQUEST_METHOD'],
					$_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL'], $context['http_headers']['User-Agent'], $headers, $entity,
				),
				array('id_event')
			);
			$error_id = wesql::insert_id();

			// Set the page up
			loadTemplate('Errors');
			loadLanguage('Security');

			// Figure out what we're going to tell the user
			loadSubTemplate('fatal_error');
			$context['no_back_link'] = true;
			$context['robot_no_index'] = true;
			$context['page_title'] = $txt['http_error'] . ' ' . $error;
			$context['error_title'] = $txt['behav_' . $error];
			$context['error_message'] = $txt['behavior_header'] . '<br><br>' . $txt[$context['behavior_error'] . '_desc'];
			foreach ($error_blocks as $block)
				$context['error_message'] .= '<br><br>' . $txt[$block];
			$context['error_message'] .= '<br><br>' . $txt['behavior_footer'];

			$context['error_message'] = str_replace('{email_address}', str_replace("@", "+REMOVEME@REMOVEME.", $webmaster_email), $context['error_message']);
			$context['error_message'] = str_replace('{incident}', '#' . $error_id, $context['error_message']);

			// And we're done. Spit out header if appropriate, footer+subtemplate.htmlspecialchars(str_replace("@", "+nospam@nospam.", bb2_email()))
			obExit(null, true, false, true);
		}
	}
	else
		return true;
}

/**
 * Checks the blacklist for known user agents that have undesirable behaviors.
 *
 * Detection data courtesy of Bad Behavior (http://www.bad-behavior.ioerror.us/)
 *
 * @return mixed Returns false if valid, returns the id of the error (and stores it in $context['behavior_error']) if an error condition was hit.
 */
function checkUserRequest_blacklist()
{
	global $context;

	// If the user agent begins with any of these, fail it.
	$rules = array(
		'begins_with' => array(
			// harvesters
			'autoemailspider',
			'CherryPicker',
			'Digger',
			'ecollector',
			'EmailCollector',
			'Email Siphon',
			'EmailSiphon',
			'ISC Systems iRc',
			'Microsoft URL',
			'Missigua',
			'Mozilla/4.0+(compatible;+',
			'OmniExplorer',
			'psycheclone',
			'Super Happy Fun ',
			'user',
			'User Agent: ',
			'User-Agent: ',
			// exploit attempts
			'<sc',
			'core-project/',
			'Internet Explorer',
			'Winnie Poh',
			// malicious
			'Diamond',
			'MJ12bot/v1.0.8',
			'Mozilla ',
			'Mozilla/2',
			'MSIE',
			'Wordpress',
			'"',
			// supplied by honeypot
			'blogsearchbot-martin',
			'Mozilla/4.0(',
			// spammers
			'8484 Boston Project',
			'adwords',
			'Forum Poster',
			'grub crawler',
			'HttpProxy',
			'Jakarta Commons',
			'Java 1.',
			'Java/1.',
			'libwww-perl',
			'LWP',
			'Movable Type',
			'NutchCVS',
			'Nutscrape/',
			'PussyCat ',
			'PycURL',
			'Python-urllib',
			'TrackBack/',
			'WebSite-X Suite',
		),
		'contains' => array(
			// harvesters
			'Email Extractor',
			'unspecified.mail',
			'User-agent: ',
			'WebaltBot',
			'Windows XP 5',
			'WISEbot',
			'WISEnutbot',
			'\\)',
			// bad bots
			"\r",
			'grub-client',
			'hanzoweb',
			'MSIE 7.0;  Windows NT 5.2',
			'Turing Machine',
			// spammers
			'; Widows ',
			'a href=',
			'compatible ; MSIE',
			'compatible-',
			'DTS Agent',
			'Gecko/25',
			'Indy Library',
			'Murzillo compatible',
			'.NET CLR 1)',
			'POE-Component-Client',
			'Windows NT 4.0;)',
			'Windows NT 5.0;)',
			'Windows NT 5.1;)',
			'WordPress/4.01',
			'Xedant Human Emulator',
		),
		'contains_regex' => array(
			// spammers
			'/^[A-Z]{10}$/',
			'/[bcdfghjklmnpqrstvwxz ]{8,}/',
		),
	);

	foreach ($rules['begins_with'] as $test)
		if (strpos($context['http_headers']['User-Agent'], $test) === 0)
			return $context['behavior_error'] = 'behav_blacklist';

	foreach ($rules['contains'] as $test)
		if (strpos($context['http_headers']['User-Agent'], $test) !== false)
			return $context['behavior_error'] = 'behav_blacklist';

	foreach ($rules['contains_regex'] as $test)
		if (preg_match($test, $context['http_headers']['User-Agent']))
			return $context['behavior_error'] = 'behav_blacklist';

	return false;
}

/**
 * Checks the request headers for known issues with respect to invalid HTTP or known spammer behavior.
 *
 * Detection data courtesy of Bad Behavior (http://www.bad-behavior.ioerror.us/)
 *
 * @return mixed Returns false if valid, returns the id of the error (and stores it in $context['behavior_error']) if an error condition was hit.
 */
function checkUserRequest_request()
{
	global $context, $modSettings;

	// Is this from CloudFlare? (requires hostname lookups enabled)
	if (isset($context['http_headers']['Cf-Connecting-Ip'], $context['http_headers']['X-Detected-Remote-Address']) && empty($modSettings['disableHostnameLookup']))
	{
		// Remember, we did some work on this back in QueryString.php, so we should have the value for CloudFlare. Let's see if it is.
		if (!test_ip_host($context['http_headers']['X-Detected-Remote-Address'], 'cloudflare.com'))
			return $context['behavior_error'] = 'behav_not_cloudflare';
	}

	// Should never see the Expect header in HTTP/1.0. It's generally a weird proxy setup.
	if (isset($context['http_headers']['Expect']) && stripos($context['http_headers']['Expect'], '100-continue') !== false && stripos($_SERVER['SERVER_PROTOCOL'], 'HTTP/1.0') !== false)
		return $context['behavior_error'] = 'behav_expect_header';

	// If it's a POST, there should be a user agent specified.
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($context['http_headers']['User-Agent']))
		return $context['behavior_error'] = 'behav_no_ua_in_post';

	// Content-Range denotes the size of content being *sent* not requested...
	if (isset($context['http_headers']['Content-Range']))
		return $context['behavior_error'] = 'behav_content_range';

	// Referer is an optional header. If it's given it must be non-blank. Additionally all legit agents should be sending absolute URIs even though the spec says relative ones are fine.
	if (isset($context['http_headers']['Referer']))
	{
		if (empty($context['http_headers']['Referer']))
			return $context['behavior_error'] = 'behav_empty_refer';
		elseif (strpos($context['http_headers']['Referer'], '://') === false)
			return $context['behavior_error'] = 'behav_invalid_refer';
	}

	// Check for oddities in the Connection header. Various bad things happen here; mostly stupid bots.
	if (isset($context['http_headers']['Connection']))
	{
		// Can't have both Connection: keep-alive and Connection: close, or more than one of either at a time. No being greedy.
		$ka = preg_match_all('~\bkeep-alive\b~i', $context['http_headers']['Connection'], $dummy);
		$c = preg_match_all('~\bclose\b~i', $context['http_headers']['Connection'], $dummy);
		if (($ka > 0 && $c > 0) || $ka > 1 || $c > 1)
			return $context['behavior_error'] = 'behav_alive_close';

		// Does Keep-Alive conform to spec?
		if (stripos($context['http_headers']['Connection'], 'Keep-Alive: ') !== false)
			return $context['behavior_error'] = 'behav_wrong_keep_alive';
	}

	// Check for weirdness in the requested URI
	if (!empty($_SERVER['REQUEST_URI']) && (strpos($_SERVER['REQUEST_URI'], '#') !== false || strpos($_SERVER['REQUEST_URI'], ';DECLARE%20@') !== false))
		return $context['behavior_error'] = 'behav_rogue_chars';

	// Should not use lowercase 'via' header (only known if on Apache). Clearswift does, though they shouldn't.
	if (isset($context['http_headers']['via']) && strpos($context['http_headers']['via'], 'Clearswift') === false && strpos($context['http_headers']['User-Agent'], 'CoralWebPrx') === false)
		return $context['behavior_error'] = 'behav_invalid_via';

	// A known referrer spammer
	if (isset($context['http_headers']['Via']) && (stripos($context['http_headers']['Via'], "pinappleproxy") !== false || stripos($context['http_headers']['Via'], "PCNETSERVER") !== false || stripos($context['http_headers']['Via'], "Invisiware") !== false))
		return $context['behavior_error'] = 'behav_banned_via_proxy';

	// Known spambot headers
	if (isset($context['http_headers']['X-Aaaaaaaaaaaa']) || isset($context['http_headers']['X-Aaaaaaaaaa']))
		return $context['behavior_error'] = 'behav_banned_xaa_proxy';

	// Are we in compliance with RFC 2965 sections 3.3.5 and 9.1? Specifically, bots wanting new-style cookies should send Cookie2 as a header.
	// Apparently this does not work on the first edition Kindle, but really... though it's not a forum platform, it's not like it's hard to override.
	if (isset($context['http_headers']['Cookie']) && strpos($context['http_headers']['Cookie'], '$Version=0') !== false && !isset($context['http_headers']['Cookie2']) && strpos($context['http_headers']['User-Agent'], "Kindle/") === false)
		return $context['behavior_error'] = 'behav_bot_rfc2965';

	// OK, are we doing the big scary strict tests? If not, bail. Some of these tests will fail on weird things like some corporate proxy servers so we don't do them by default.
	if (empty($modSettings['performStrictBehavior']))
		return false;

	// Proxy-Connection isn't a real header.
	if (isset($context['http_headers']['Proxy-Connection']))
		return $context['behavior_error'] = 'behav_proxy_connection';

	// Connections claiming HTTP/1.1 should not use HTTP/1.0 caching instructions
	if ($_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1' && isset($context['http_headers']['Pragma']) && strpos($context['http_headers']['Pragma'], "no-cache") !== false && !isset($context['http_headers']['Cache-Control']))
		return $context['behavior_error'] = 'behav_pragma';

	// RFC 2616 14.39 states that if TE is specified as a header, Connection: TE must also be specified.
	// Microsoft ISA Server 2004 does not play nicely however this is a minor case for us.
	if (isset($context['http_headers']['Te']) && !preg_match('~\bTE\b~', $context['http_headers']['Connection']))
		return $context['behavior_error'] = 'behav_te_error';

	// When specified, Range should exist and not begin with a 0 for requesting (since it's requesting a follow-on partial dataset)
	// whois.sc is broken by this. So are Facebook, LiveJournal and MovableType (for which exceptions have been made, for FB and OpenID in particular)
	if (isset($context['http_headers']['Range']) && strpos($context['http_headers']['Range'], "=0-") !== false && (strpos($context['http_headers']['User-Agent'], "MovableType") !== 0 && strpos($context['http_headers']['User-Agent'], "URI::Fetch") !== 0 && strpos($context['http_headers']['User-Agent'], "php-openid/") !== 0 && strpos($context['http_headers']['User-Agent'], "facebookexternalhit") !== 0))
		return $context['behavior_error'] = 'behav_invalid_range';

	return false;
}

/**
 * Performs acceptance checks that are based primarily on the user agent for the current request.
 *
 * Detection data courtesy of Bad Behavior (http://www.bad-behavior.ioerror.us/)
 *
 * @return mixed Returns false if valid, returns the id of the error (and stores it in $context['behavior_error']) if an error condition was hit.
 */
function checkUserRequest_useragent()
{
	global $context, $modSettings;

	// For most browsers, there's only one test to do, to make sure they send an Accept header. Naughty bots pretending to be these folks don't normally.
	if (preg_match('~Opera|Lynx|Safari~', $context['http_headers']['User-Agent']))
	{
		if (!isset($context['http_headers']['Accept']))
			return $context['behavior_error'] = 'behav_no_accept';
	}
	// Ah, Internet Explorer. We already got rid of Opera, which sometimes sends MSIE in the headers.
	elseif (strpos($context['http_headers']['User-Agent'], '; MSIE') !== false)
	{
		// Silly bots think IE sends "Windows XP" or similar in the 'what browser we're using' area. Except it doesn't.
		if (strpos($context['http_headers']['User-Agent'], 'Windows ME') !== false || strpos($context['http_headers']['User-Agent'], 'Windows XP') !== false || strpos($context['http_headers']['User-Agent'], 'Windows 2000') !== false || strpos($context['http_headers']['User-Agent'], 'Win32') !== false)
			return $context['behavior_error'] = 'behav_invalid_win';
		// Connection: TE again. IE doesn't use it, Akamai and IE for WinCE does.
		elseif (!isset($context['http_headers']['Akamai-Origin-Hop']) && strpos($context['http_headers']['User-Agent'], 'IEMobile') === false && @preg_match('/\bTE\b/i', $context['http_headers']['Connection']))
			return $context['behavior_error'] = 'behav_te_not_msie';
	}
	// Some browsers are just special however. Konqueror is on the surface, straightforward, but there's a Yahoo dev project that isn't a real browser but calls itself Konqueror, so we have to do the normal browser test but exclude if it's this.
	elseif (stripos($context['http_headers']['User-Agent'], 'Konqueror') !== false)
	{
		if (!isset($context['http_headers']['Accept']) && (stripos($context['http_headers']['User-Agent'], 'YahooSeeker/CafeKelsa') === false || match_cidr($_SERVER['REMOTE_ADDR'], '209.73.160.0/19') === false))
			return $context['behavior_error'] = 'behav_no_accept';
	}
	// Is it claiming to be Yahoo's bot?
	elseif (stripos($context['http_headers']['User-Agent'], 'Yahoo! Slurp') !== false || stripos($context['http_headers']['User-Agent'], 'Yahoo! SearchMonkey') !== false)
	{
		if ((!match_cidr($_SERVER['REMOTE_ADDR'], array('202.160.176.0/20', '67.195.0.0/16', '203.209.252.0/24', '72.30.0.0/16', '98.136.0.0/14'))) || (empty($modSettings['disableHostnameLookup']) && !test_ip_host($_SERVER['REMOTE_ADDR'], 'crawl.yahoo.net')))
			return $context['behavior_error'] = 'behav_not_yahoobot';
	}
	// Is it claiming to be MSN's bot?
	elseif (stripos($context['http_headers']['User-Agent'], 'bingbot') !== false || stripos($context['http_headers']['User-Agent'], 'msnbot') !== false || stripos($context['http_headers']['User-Agent'], 'MS Search') !== false)
	{
		if ((!match_cidr($_SERVER['REMOTE_ADDR'], array('207.46.0.0/16', '65.52.0.0/14', '207.68.128.0/18', '207.68.192.0/20', '64.4.0.0/18', '157.54.0.0/15', '157.60.0.0/16', '157.56.0.0/14'))) || (empty($modSettings['disableHostnameLookup']) && !test_ip_host($_SERVER['REMOTE_ADDR'], 'msn.com')))
			return $context['behavior_error'] = 'behav_not_msnbot';
	}
	// Is it claiming to be Googlebot, even?
	elseif (stripos($context['http_headers']['User-Agent'], 'Googlebot') !== FALSE || stripos($context['http_headers']['User-Agent'], 'Mediapartners-Google') !== false)
	{
		if ((!match_cidr($_SERVER['REMOTE_ADDR'], array('66.249.64.0/19', '64.233.160.0/19', '72.14.192.0/18', '203.208.32.0/19', '74.125.0.0/16', '216.239.32.0/19'))) || (empty($modSettings['disableHostnameLookup']) && !test_ip_host($_SERVER['REMOTE_ADDR'], 'googlebot.com')))
			return $context['behavior_error'] = 'behav_not_googlebot';
	}
	// OK, so presumably this is some kind of Mozilla derivative? (No guarantee it's actually Firefox, mind. All main browsers cite Mozilla. :/)
	elseif (stripos($context['http_headers']['User-Agent'], 'Mozilla') === 0)
	{
		// The main test for Mozilla is the same as the standard needing Accept header. But Google Desktop didn't previously support it, and since there's some legacy stuff, we except it for now.
		if (strpos($context['http_headers']['User-Agent'], "Google Desktop") === false && strpos($context['http_headers']['User-Agent'], "PLAYSTATION 3") === false && !isset($context['http_headers']['Accept']))
			return $context['behavior_error'] = 'behav_no_accept';
	}

	return false;
}

/**
 * Performs acceptance checks that are based primarily on the details of the request if it is a POST request.
 *
 * Detection data courtesy of Bad Behavior (http://www.bad-behavior.ioerror.us/)
 *
 * @return mixed Returns false if valid, returns the id of the error (and stores it in $context['behavior_error']) if an error condition was hit.
 */
function checkUserRequest_post()
{
	global $context, $modSettings;

	if ($_SERVER['SERVER_PROTOCOL'] != 'POST')
		return false;

	// Catch a few completely broken spambots
	foreach ($_POST as $key => $value) {
		if (strpos($key, "	document.write") !== false)
			return $context['behavior_error'] = 'behav_rogue_chars';
	}

	// What about forms? Any form posting into our forum should really be inside our forum. Providing an option to disable (hidden for now)
	// !!! Check whether this is relevant in subdomain boards or not.
	if (empty($modSettings['allow_external_forms']) && isset($context['http_headers']['Referer']) && stripos($context['http_headers']['Referer'], $context['http_headers']['Host']) === false)
		return $context['behavior_error'] = 'behav_offsite_form';

	return false;
}

/**
 * Identifies the HTTP error code and message components to send to the user.
 *
 * @return array Indexed array: [0] = HTTP error code, [1] = the error code blocks other than the error message itself to display
 */
function userBehaviorResponse()
{
	global $context;
	$error_blocks = array();

	// Send an appropriate HTTP error, hopefully they'll get the hint. Which one, though?
	switch ($context['behavior_error'])
	{
		case 'behav_pragma':
		case 'behav_empty_refer':
		case 'behav_invalid_refer':
		case 'behav_invalid_range':
		case 'behav_te_error':
		case 'behav_alive_close':
		case 'behav_wrong_keep_alive':
		case 'behav_proxy_connection':
			$error = 400;
			break;
		case 'behav_pomme':
			$error = 0x29A;
			break;
		case 'behav_expect_header':
			$error = 417;
			break;
		default:
			$error = 403;
	}

	// Now, what chunks of info to give the user? Not all errors get an extra chunk of info.
	switch ($context['behavior_error'])
	{
		case 'behav_expect_header':
		case 'behav_wrong_keep_alive':
		case 'behav_rogue_chars':
			$error_blocks = array('behavior_malware');
			break;
		case 'behav_te_not_msie':
		case 'behav_not_msnbot':
		case 'behav_not_yahoobot':
		case 'behav_not_googlebot':
			$error_blocks = array('behavior_false_ua', 'behavior_misconfigured_privacy');
			break;
		case 'behav_no_ua_in_post':
		case 'behav_invalid_win':
		case 'behav_blacklist':
		case 'behav_not_cloudflare':
			$error_blocks = array('behavior_false_ua', 'behavior_misconfigured_proxy', 'behavior_misconfigured_privacy', 'behavior_malware');
			break;
		case 'behav_pragma':
		case 'behav_empty_refer':
		case 'behav_invalid_refer':
		case 'behav_proxy_connection':
		case 'behav_banned_via_proxy':
		case 'behav_banned_xaa_proxy':
		case 'behav_alive_close':
		case 'behav_no_accept':
		case 'behav_content_range':
		case 'behav_invalid_via':
			$error_blocks = array('behavior_misconfigured_proxy', 'behavior_misconfigured_privacy', 'behavior_malware');
			break;
		case 'behav_offsite_form':
			$error_blocks = array('behavior_misconfigured_privacy', 'behavior_malware');
			break;
		case 'behav_te_error':
			$error_blocks = array('behavior_misconfigured_proxy', 'behavior_misconfigured_privacy', 'behavior_malware', 'behavior_opera_bug');
			break;
		case 'behav_invalid_range':
			$error_blocks = array('behavior_malware', 'behavior_chrome_bug');
			break;
	}

	return array($error, $error_blocks);
}

?>