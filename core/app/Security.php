<?php
/**
 * Handles various security-related tasks, including permissions and filtering of input based on known malicious behavior.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file has the very important job of insuring forum security. This
	task includes banning and permissions, namely. It does this by providing
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

	void isBannedEmail(string email, string error)
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
			'yes_permission_override': show the full email address, either you
			  are a moderator or it's your own email address.
			'no_through_forum': don't show the email address, but do allow
			  things to be mailed using the built-in forum mailer.
			'no': keep the email address hidden.

	string get_privacy_type(int)
		- returns a string determining the type of privacy associated
		  with the supplied privacy ID. Can be members, contacts, author, etc.
*/

// Check if the user is who he/she says he is.
function validateSession()
{
	global $settings, $sc;

	// We don't care if the option is off, because Guests should NEVER get past here.
	is_not_guest();

	// If we're using Ajax, give an additional ten minutes grace as an admin can't log in.
	$refreshTime = AJAX ? 4200 : 3600;

	// Is the security option off? Or are they already logged in?
	if (!empty($settings['securityDisable']) || (!empty($_SESSION['admin_time']) && $_SESSION['admin_time'] + $refreshTime >= time()))
		return;

	loadSource('Subs-Auth');

	// Hashed password, ahoy!
	if (isset($_POST['admin_hash_pass']) && strlen($_POST['admin_hash_pass']) == 40)
	{
		checkSession();

		$good_password = in_array(true, call_hook('verify_password', array(we::$user['username'], $_POST['admin_hash_pass'], true)), true);

		if ($good_password || $_POST['admin_hash_pass'] == sha1(we::$user['passwd'] . $sc))
		{
			$_SESSION['admin_time'] = time();
			unset($_SESSION['request_referer']);
			return;
		}
	}
	// Posting the password... check it.
	if (isset($_POST['admin_pass']))
	{
		checkSession();

		$good_password = in_array(true, call_hook('verify_password', array(we::$user['username'], $_POST['admin_pass'], false)), true);

		// Password correct?
		if ($good_password || sha1(strtolower(we::$user['username']) . $_POST['admin_pass']) == we::$user['passwd'])
		{
			$_SESSION['admin_time'] = time();
			unset($_SESSION['request_referer']);
			return;
		}
	}

	// Better be sure to remember the real referer
	if (empty($_SESSION['request_referer']))
		$_SESSION['request_referer'] = isset($_SERVER['HTTP_REFERER']) ? @parse_url($_SERVER['HTTP_REFERER']) : array();
	elseif (empty($_POST))
		unset($_SESSION['request_referer']);

	// Need to type in a password for that, man.
	adminLogin();
}

// Require a user who is logged in. (not a guest.)
function is_not_guest($message = '')
{
	global $txt, $context, $settings;

	// Luckily, this person isn't a guest.
	if (we::$is_member)
		return;

	// No need to clear the action they were doing, as it used to be; not only are the odds strong that it wouldn't have been updated,
	// this way you can see what they were trying to do and that it didn't work. But we do need to update the online log.
	if (!empty($settings['who_enabled']))
		$_GET['who_warn'] = 1;
	writeLog(true);

	// Just die.
	if (AJAX)
		obExit(false);

	// Attempt to detect if they came from dlattach.
	if (WEDGE != 'SSI' && empty($context['theme_loaded']))
		loadTheme();

	// Never redirect to an attachment
	if (strpos($_SERVER['REQUEST_URL'], 'dlattach') === false)
		$_SESSION['login_url'] = $_SERVER['REQUEST_URL'];

	// Load the Login template and language file.
	loadLanguage('Login');

	// Apparently we're not in a position to handle this now. Let's go to a safer location for now.
	if (!wetem::has_layer('default'))
	{
		$_SESSION['login_url'] = SCRIPT . '?' . $_SERVER['QUERY_STRING'];
		redirectexit('action=login');
	}
	else
	{
		loadTemplate('Login');
		wetem::load('kick_guest');
		$context['robot_no_index'] = true;
	}

	// Use the kick_guest block...
	$context['kick_message'] = $message;
	$context['page_title'] = $txt['login'];

	obExit();

	// We should never get to this point, but if we did we wouldn't know the user isn't a guest.
	trigger_error('Hacking attempt...', E_USER_ERROR);
}

// Do banning related stuff. (ie. disallow access....)
function is_not_banned($forceCheck = false)
{
	global $txt, $settings, $cookiename, $user_settings;

	// You cannot be banned if you are an admin - doesn't help if you log out.
	if (we::$is_admin)
		return;

	// Only check the ban every so often, to reduce load.
	if ($forceCheck || !isset($_SESSION['ban']) || empty($settings['banLastUpdated']) || ($_SESSION['ban']['last_checked'] < $settings['banLastUpdated']) || $_SESSION['ban']['id_member'] != we::$id || $_SESSION['ban']['ip'] != we::$user['ip'] || $_SESSION['ban']['ip2'] != we::$user['ip2'] || (isset(we::$user['email'], $_SESSION['ban']['email']) && $_SESSION['ban']['email'] != we::$user['email']))
	{
		// Innocent until proven guilty. (But we know you are! :P)
		$_SESSION['ban'] = array(
			'last_checked' => time(),
			'id_member' => we::$id,
			'ip' => we::$user['ip'],
			'ip2' => we::$user['ip2'],
			'email' => we::$user['email'],
		);

		$flag_is_activated = false;

		$ban_list = array();
		// Check the user id first of all.
		if (we::$id)
		{
			$member_check = check_banned_member(we::$id);
			if (!empty($member_check))
				$ban_list = array_merge($ban_list, $member_check);
		}

		// Is their email address banned?
		if (strlen(we::$user['email']) != 0)
		{
			$email_check = isBannedEmail(we::$user['email'], '', true);
			if (!empty($email_check))
				$ban_list = array_merge($ban_list, $email_check);
		}

		// Check both IP addresses.
		foreach (array('ip', 'ip2') as $ip_number)
		{
			$bans = check_banned_ip(we::$user[$ip_number]);
			if (is_array($bans))
				$ban_list = array_merge($ban_list, $bans);
		}

		foreach ($ban_list as $ban)
			if ($ban['hard'])
			{
				$_SESSION['ban']['cannot_access']['ids'][] = $ban['id'];
				if (!empty($ban['msg']))
					$_SESSION['ban']['cannot_access']['reason'] = $ban['msg'];
				$flag_is_activated = 'hard';
			}
			elseif ($flag_is_activated == false)
				$flag_is_activated = 'soft';

		if (!empty($_SESSION['ban']['cannot_access']['ids']))
		{
			$_SESSION['ban']['cannot_access']['ids'] = array_unique($_SESSION['ban']['cannot_access']['ids']);
			if (!isset($_SESSION['ban']['cannot_access']['reason']))
				$_SESSION['ban']['cannot_access']['reason'] = '';
		}

		// If for whatever reason the is_activated flag seems wrong, do a little work to clear it up.
		// But we're not going to go mad and re-evaluate all the bans - just the ones for this person.
		if (we::$id)
		{
			$update = 0;
			if ($user_settings['is_activated'] >= 20)
			{
				if ($flag_is_activated != 'hard')
					$update = $flag_is_activated == 'soft' ? -10 : -20; // Shouldn't be hard banned, drop them down to soft banned or unbanned entirely as appropriate.
			}
			elseif ($user_settings['is_activated'] >= 10)
			{
				if ($flag_is_activated == 'hard')
					$update = 10; // They're already softbanned (10-19), update them to hard banned (20-29)
				elseif (!$flag_is_activated)
					$update = -10; // They're softbanned with no reason to be, drop them down to 0-9
			}
			elseif ($flag_is_activated)
			{
				// So, not banned but should be.
				$update = $flag_is_activated == 'soft' ? 10 : 20;
			}
			if (!empty($update))
			{
				updateMemberData(we::$id, array('is_activated' => $user_settings['is_activated'] + $update));
				updateStats('member');
			}
		}

		// Are they soft banned? Doesn't have to be a specific member. Could be an IP soft ban.
		if ($flag_is_activated == 'soft')
			$_SESSION['ban']['soft'] = true;
	}

	// Hey, I know you! You're ehm...
	if (!isset($_SESSION['ban']['cannot_access']) && !empty($_COOKIE[$cookiename . '_']))
	{
		$bans = explode(',', $_COOKIE[$cookiename . '_']);
		foreach ($bans as $key => $value)
			$bans[$key] = (int) $value;
		$request = wesql::query('
			SELECT id_ban, extra
			FROM {db_prefix}bans
			WHERE id_ban IN ({array_int:bans})',
			array(
				'bans' => $bans,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$extra = !empty($row['extra']) ? @unserialize($row['extra']) : array();
			$_SESSION['ban']['cannot_access']['ids'][] = $row['id_ban'];
			$_SESSION['ban']['cannot_access']['reason'] = !empty($extra['message']) ? $extra['message'] : '';
		}
		wesql::free_result($request);

		// My mistake. Next time better.
		if (!isset($_SESSION['ban']['cannot_access']))
		{
			loadSource('Subs-Auth');
			$cookie_url = url_parts(!empty($settings['localCookies']), !empty($settings['globalCookies']));
			setcookie($cookiename . '_', '', time() - 3600, $cookie_url[1], $cookie_url[0], 0, true);
		}
	}

	// Hmm, maybe, just maybe, they got banned through sanctions. It wouldn't show up in the ban area.
	if (empty($_SESSION['ban']['cannot_access']))
	{
		// They're not hard-banned thus far. Maybe they soon will be.
		if (!empty(we::$user['sanctions']['hard_ban']))
		{
			$_SESSION['ban']['cannot_access'] = array(
				'ids' => array('sanctions'),
			);
			if (we::$user['sanctions']['hard_ban'] != 1)
				$_SESSION['ban']['cannot_access']['expire_time'] = we::$user['sanctions']['hard_ban'];
		}
		// They're not soft-banned either? Maybe they soon will be.
		elseif (empty($_SESSION['ban']['soft']) && !empty(we::$user['sanctions']['hard_ban']))
			$_SESSION['ban']['soft'] = true;
	}

	// If you're fully banned, it's end of the story for you.
	if (isset($_SESSION['ban']['cannot_access']))
	{
		// We don't wanna see you!
		if (we::$is_member)
			wesql::query('DELETE FROM {db_prefix}log_online WHERE id_member = {int:current_member}', array('current_member' => we::$id));

		// 'Log' the user out. Can't have any funny business... (save the name!)
		$old_name = isset(we::$user['name']) && we::$user['name'] != '' ? we::$user['name'] : $txt['guest_title'];
		we::$user['name'] = '';
		we::$user['username'] = '';
		we::$is_guest = true;
		we::$is_admin = false;
		we::$cache = array();
		we::$id = 0;
		we::$user['permissions'] = array();

		// A goodbye present.
		loadSource('Subs-Auth');
		$cookie_url = url_parts(!empty($settings['localCookies']), !empty($settings['globalCookies']));
		setcookie($cookiename . '_', implode(',', $_SESSION['ban']['cannot_access']['ids']), time() + 3153600, $cookie_url[1], $cookie_url[0], 0, true);

		// Don't scare anyone, now.
		$_GET['action'] = $_GET['board'] = $_GET['topic'] = '';
		writeLog(true);

		// You banned, sucka!
		fatal_lang_error('your_ban', false, array($old_name, (empty($_SESSION['ban']['cannot_access']['reason']) ? '' : '<br>' . $_SESSION['ban']['cannot_access']['reason']) . '<br>' . (!empty($_SESSION['ban']['expire_time']) ? sprintf($txt['your_ban_expires'], timeformat($_SESSION['ban']['expire_time'], false)) : $txt['your_ban_expires_never'])));

		// If we get here, something's gone wrong.... but let's try anyway.
		trigger_error('Hacking attempt...', E_USER_ERROR);
	}

	// Fix up the banning permissions.
	if (isset(we::$user['permissions']))
		banPermissions();

	// Soft bans in play?
	if (!empty($_SESSION['ban']['soft']))
	{
		if (!empty($settings['softban_blankpage']) && mt_rand(0, 100) < (int) $settings['softban_blankpage'])
			die; // No Prayer For The Dying

		if (!empty($settings['softban_redirect']) && !empty($settings['softban_redirect_url']) && mt_rand(0, 100) < (int) $settings['softban_redirect'])
			redirectexit($settings['softban_redirect_url']); // Run To The Hills

		if (!empty($settings['softban_delay_max']))
			usleep(mt_rand(!empty($settings['softban_delay_min']) ? $settings['softban_delay_min'] * 1000000 : 0, $settings['softban_delay_max'] * 1000000));

		if (!empty($settings['softban_disableregistration']))
			$settings['registration_method'] = 3; // Stranger In A Strange Land
	}
}

// Fix permissions according to ban status.
function banPermissions()
{
	global $settings, $context;

	$denied_permissions = array();

	// Somehow they got here, at least take away all permissions...
	if (isset($_SESSION['ban']['cannot_access']))
		we::$user['permissions'] = array();
	// What about actual sanctions?
	elseif (!empty(we::$user['sanctions']))
	{
		if (!empty(we::$user['sanctions']['pm_ban']))
		{
			$denied_permissions[] = 'pm_send';
			we::$user['pm_banned'] = true;
		}
		if (!empty(we::$user['sanctions']['post_ban']))
		{
			$denied_permissions = array_merge($denied_permissions, array(
				'post_new', 'post_reply_own', 'post_reply_any',
				'post_thought',
				'poll_post',
				'poll_add_own', 'poll_add_any',
				'poll_edit_own', 'poll_edit_any',
				'poll_lock_own', 'poll_lock_any',
				'poll_remove_own', 'poll_remove_any',
			));
			we::$user['post_banned'] = true;
		}

		// If they had a sanction against something else, we might want to obliterate more permissions.
		if (!empty($denied_permissions))
			$denied_permissions = array_merge($denied_permissions, array(
				'manage_attachments', 'manage_smileys', 'manage_boards', 'admin_forum', 'manage_permissions',
				'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news',
				'profile_identity_any', 'profile_extra_any', 'profile_title_any',
				'delete_own', 'delete_any', 'delete_replies',
				'pin_topic',
				'merge_any', 'split_any',
				'modify_own', 'modify_any', 'modify_replies',
				'bypass_edit_disable',
				'move_any',
				'send_topic',
				'lock_own', 'lock_any',
				'remove_own', 'remove_any',
			));

		if (!empty(we::$user['sanctions']['moderate']))
			we::$user['post_moderated'] = true;
	}

	call_hook('banned_perms', array(&$denied_permissions));
	if (!empty($denied_permissions))
		we::$user['permissions'] = array_diff(we::$user['permissions'], $denied_permissions);

	// !! Find a better place to call this? Needs to be after permissions loaded!
	// Finally, some bits we cache in the session because it saves queries.
	if (isset($_SESSION['mc']) && $_SESSION['mc']['time'] > $settings['settings_updated'] && $_SESSION['mc']['id'] == MID)
		we::$user['mod_cache'] = $_SESSION['mc'];
	else
	{
		loadSource('Subs-Auth');
		rebuildModCache();
	}

	// Now that we have the mod cache taken care of, let's setup a cache for the number of mod reports still open
	if (isset($_SESSION['rc']) && $_SESSION['rc']['time'] > $settings['last_mod_report_action'] && $_SESSION['rc']['id'] == MID)
	{
		$context['open_mod_reports'] = $_SESSION['rc']['reports'];
		$context['closed_mod_reports'] = $_SESSION['rc']['closed'];
	}
	elseif ($_SESSION['mc']['bq'] != '0=1')
	{
		loadSource('ModerationCenter');
		recountOpenReports();
	}
	else
		$context['open_mod_reports'] = 0;
}

// Disable a feature if they are soft-banned and their luck has run out for now.
function soft_ban($feature)
{
	global $settings;

	if (empty($_SESSION['ban']['soft']))
		return;

	if (!empty($settings['softban_no' . $feature]))
	{
		$chance = (int) $settings['softban_no' . $feature];
		if (mt_rand(0, 100) <= $chance)
			fatal_lang_error('loadavg_' . $feature . '_disabled', false);
	}
}

function check_banned_member($id_member)
{
	// Guests will never trip this.
	if (empty($id_member))
		return array();

	$return_value = array();
	$bans = cache_get_data('bans_id_member', 600);

	if ($bans === null)
	{
		$bans = array();
		$request = query_for_bans('id_member');

		while ($row = wesql::fetch_assoc($request))
		{
			$extra = !empty($row['extra']) ? @unserialize($row['extra']) : array();
			$ban = array(
				'id' => $row['id_ban'],
				'hard' => $row['hardness'] == 1,
				'message' => !empty($extra['message']) ? $extra['message'] : '',
			);

			if (!empty($row['ban_content']) && (int) $row['ban_content'] > 0)
			{
				$ban['member'] = (int) $row['ban_content'];
				$bans[] = $ban;
			}
		}

		cache_put_data('bans_id_member', $bans, 600);
		wesql::free_result($request);
	}

	// And so it begins.
	foreach ($bans as $ban)
	{
		if ($id_member == $ban['member'])
			$return_value[] = array(
				'id' => $ban['id'],
				'msg' => $ban['message'],
				'hard' => $ban['hard'],
			);
	}

	return $return_value;
}

function check_banned_ip($ip)
{
	global $settings;
	static $ips = null, $hostnames = null;

	if ($ip == INVALID_IP)
		return false;

	$return_value = array();

	// We call this multiple times, so we store it statically as well as caching. If it's null, it's the first time we're here, so try to pull from cache.
	// If it's null the second time, we still don't have it from cache and need to get it from there. IOW, it isn't a typo, heh. Same for $hostnames, too.
	if ($ips === null)
		$ips = cache_get_data('bans_ip', 300);
	if ($ips === null)
	{
		$ips = array();
		$request = query_for_bans('ip_address');
		while ($row = wesql::fetch_assoc($request))
		{
			$extra = !empty($row['extra']) ? @unserialize($row['extra']) : array();
			$ban = array(
				'id' => $row['id_ban'],
				'hard' => $row['hardness'] == 1,
				'message' => !empty($extra['message']) ? $extra['message'] : '',
			);
			if (strpos($row['ban_content'], '-') !== false)
				$ban['range'] = explode('-', $row['ban_content']);
			else
				$ban['ip'] = $row['ban_content'];

			$ips[] = $ban;
		}
		cache_put_data('bans_ip', $ips, 300);
		wesql::free_result($request);
	}

	foreach ($ips as $ban)
	{
		if (isset($ban['ip']))
		{
			if ($ban['ip'] != $ip)
				continue; // Single address not matched, leave.
		}
		elseif ($ip < $ban['range'][0] || $ip > $ban['range'][1])
			continue; // The address is either before or after the range we've set, thus not in it.

		$return_value[] = array(
			'id' => $ban['id'],
			'msg' => $ban['message'],
			'hard' => $ban['hard'],
		);
	}

	// Hang on, though. What about hosts?
	if (empty($settings['disableHostnameLookup']))
	{
		$this_hostname = strtolower(host_from_ip(format_ip($ip)));
		if (strlen($this_hostname) > 0)
		{
			// No point trying to actually get the data if there is nothing valid to check against! See above for why this is checked twice.
			if ($hostnames === null)
				$hostnames = cache_get_data('bans_hostname', 480);
			if ($hostnames === null)
			{
				$hostnames = array();
				$request = query_for_bans('hostname');
				while ($row = wesql::fetch_assoc($request))
				{
					$extra = !empty($row['extra']) ? @unserialize($row['extra']) : array();
					$ban = array(
						'id' => $row['id_ban'],
						'hard' => $row['hardness'] == 1,
						'message' => !empty($extra['message']) ? $extra['message'] : '',
					);
					if (strpos($row['ban_content'], '*.') === 0)
						$ban['match'] = '~' . strtolower(preg_quote(substr($row['ban_content'], 2), '~')) . '$~';
					else
						$ban['content'] = strtolower($row['ban_content']);
					$hostnames[] = $ban;
				}
				cache_put_data('bans_hostname', $hostnames, 480);
				wesql::free_result($request);
			}

			foreach ($hostnames as $ban)
			{
				if (isset($ban['content']))
				{
					if ($ban['content'] != $this_hostname)
						continue;
				}
				elseif (!preg_match($ban['match'], $this_hostname))
					continue;

				$return_value[] = array(
					'id' => $ban['id'],
					'msg' => $ban['message'],
					'hard' => $ban['hard'],
				);
			}
		}
	}

	return $return_value;
}

// Checks if a given email address might be banned.
function isBannedEmail($email, $error, $return = false)
{
	global $txt;

	// Can't ban an empty email
	if (empty($email) || trim($email) == '')
		return;

	$return_value = array();

	// So now we find all the banned emails. If it's a soft ban, we just throw an error message. If it's a hard ban, we lock them out too.
	$bans = cache_get_data('bans_email', 240);
	if ($bans === null)
	{
		$bans = array();
		$request = query_for_bans('email');

		while ($row = wesql::fetch_assoc($request))
		{
			$extra = !empty($row['extra']) ? @unserialize($row['extra']) : array();
			$ban = array(
				'id' => $row['id_ban'],
				'hard' => $row['hardness'] == 1,
				'message' => !empty($extra['message']) ? $extra['message'] : '',
			);

			// So, split up the user and domain parts of the email, and lower-case everything. The specification actually spells out case insensitivity.
			list ($user, $domain) = explode('@', strtolower($row['ban_content']));

			// GMail style ignores dots and +labels
			if (!empty($extra['gmail_style']))
			{
				$ban['gmail'] = true;
				if (strpos($user, '+') !== false)
					list ($user) = explode('+', $user);
				if ($domain == 'gmail.com' || $domain == 'googlemail.com')
					$user = str_replace('.', '', $user);
			}

			if ($user === '*')
				$ban['domain'] = $domain;
			elseif ($domain[0] === '*')
				$ban['tld'] = '~' . preg_quote(substr($domain, 1), '~') . '$~';
			elseif (strpos($user, '*') !== false)
			{
				list ($b, $a) = explode('*', $user);
				$ban['match'] = '~' . preg_quote($b, '~') . '.*' . preg_quote($a, '~') . '~';
			}
			else
				$ban['content'] = $user . '@' . $domain;
			$bans[] = $ban;
		}
		cache_put_data('bans_email', $bans, 240);
		wesql::free_result($request);
	}

	// Now we're operating on the real details.
	$email = strtolower($email);
	list ($user, $domain) = explode('@', $email);
	// To avoid recalculating this every time, let's get a few things sorted out.
	list ($gmail_user) = explode('+', $user);
	$gmail_user_strict = str_replace('.', '', $gmail_user) . '@' . $domain;
	$gmail_user .= '@' . $domain;

	// And so it begins.
	foreach ($bans as $ban)
	{
		// First, we test for exact match. And if not specified, move on to the next type of test.
		// We keep it separated otherwise things fall through when they're not supposed to.
		if (isset($ban['content']))
		{
			$content = !empty($ban['gmail']) ? ($domain == 'gmail.com' || $domain == 'googlemail.com' ? $gmail_user_strict : $gmail_user) : $email;
			if ($ban['content'] != $content)
				continue;
		}
		elseif (isset($ban['domain'])) // Then we test for matching domain
		{
			if ($ban['domain'] != $domain)
				continue;
		}
		elseif (isset($ban['tld'])) // Then we test for TLD matches
		{
			if (!preg_match($ban['tld'], $domain))
				continue;
		}
		else // And lastly, wildcard match on address.
		{
			if (!preg_match($ban['match'], $email))
				continue;
		}

		if ($return)
			$return_value[] = array(
				'id' => $ban['id'],
				'msg' => $ban['message'],
				'hard' => $ban['hard'],
			);
		else
		{
			// OK, if we're still here, this ban matched. Hard bans mean good bye. Go and boil your bottoms, you sons of a silly person.
			if ($ban['hard'])
			{
				$_SESSION['ban']['cannot_access']['reason'] = $ban['message'];
				$_SESSION['ban']['last_checked'] = time();
				$_SESSION['ban']['cannot_access']['ids'][] = $ban['id'];
				fatal_lang_error('your_ban', false, array($txt['guest_title'], $_SESSION['ban']['cannot_access']['reason']));
			}
			else
				fatal_error($error . $ban['message'], false);
		}
	}

	return $return_value;
}

function query_for_bans($type)
{
	return wesql::query('
		SELECT id_ban, hardness, ban_content, extra
		FROM {db_prefix}bans
		WHERE ban_type = {string:type}',
		array(
			'type' => $type,
		)
	);
}

// Make sure the user's correct session was passed, and they came from here. (type can be post, get, or request.)
function checkSession($type = 'post', $from_action = '', $is_fatal = true)
{
	global $sc, $settings;

	// Is it in as $_POST?
	if ($type == 'post')
	{
		$check = isset($_POST[$_SESSION['session_var']]) ? $_POST[$_SESSION['session_var']] : null;
		if ($check !== $sc)
			$error = 'session_timeout';
	}

	// How about $_GET?
	elseif ($type == 'get')
	{
		$check = isset($_GET[$_SESSION['session_var']]) ? $_GET[$_SESSION['session_var']] : null;
		if ($check !== $sc)
			$error = 'session_verify_fail';
	}

	// Or can it be in either?
	elseif ($type == 'request')
	{
		$check = isset($_GET[$_SESSION['session_var']]) ? $_GET[$_SESSION['session_var']] : (isset($_POST[$_SESSION['session_var']]) ? $_POST[$_SESSION['session_var']] : null);

		if ($check !== $sc)
			$error = 'session_verify_fail';
	}

	// Verify that they aren't changing user agents on us - that could be bad.
	if ((!isset($_SESSION['USER_AGENT']) || $_SESSION['USER_AGENT'] != $_SERVER['HTTP_USER_AGENT']) && empty($settings['disableCheckUA']))
		$error = 'session_verify_fail';

	// Make sure a page with session check requirement is not being prefetched.
	preventPrefetch();

	// Check the referring site - it should be the same server at least!
	if (isset($_SESSION['request_referer']))
		$referrer = $_SESSION['request_referer'];
	else
		$referrer = isset($_SERVER['HTTP_REFERER']) ? @parse_url($_SERVER['HTTP_REFERER']) : array();

	if (!empty($referrer['host']))
	{
		if (strpos($_SERVER['HTTP_HOST'], ':') !== false)
			$real_host = substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'], ':'));
		else
			$real_host = $_SERVER['HTTP_HOST'];

		$parsed_url = parse_url(ROOT);

		// Are global cookies on? If so, let's check them ;)
		if (!empty($settings['globalCookies']))
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
		fatal_lang_error('no_access', false);

	// Everything is ok, return an empty string.
	if (!isset($error))
		return '';
	// A session error occurred, show the error.
	elseif ($is_fatal)
	{
		if (AJAX)
		{
			while (@ob_end_clean());
			header('HTTP/1.1 403 Forbidden - Session timeout');
			exit;
		}
		else
			fatal_lang_error($error, isset($log_error) ? 'user' : false);
	}
	// A session error occurred, return the error to the calling function.
	else
		return $error;

	// We really should never fall through here, for very important reasons. Let's make sure.
	trigger_error('Hacking attempt...', E_USER_ERROR);
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

// Check the user's permissions. Accepts a permission name, or an array of them.
function allowedTo($permissions, $boards = null)
{
	// You're always allowed to do nothing. (Unless you're a working man, MR. LAZY :P!)
	if (empty($permissions))
		return true;

	// Administrators are supermen :P
	if (we::$is_admin)
		return true;

	// Are we checking the _current_ board, or some other boards?
	if ($boards === null)
	{
		// Check if they can do it.
		$perms = isset(we::$user['permissions']) ? array_flip(we::$user['permissions']) : array();

		// Search for any of a list of permissions.
		if (!is_array($permissions))
			return isset($perms[$permissions]);

		// array_intersect would do this in one line, but up to hundreds of times slower...
		$can_do = false;
		// We only need ONE permission to be there.
		foreach ($permissions as $perm)
		{
			$can_do |= isset($perms[$perm]);
			if ($can_do)
				break;
		}
		return $can_do;
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
			'current_member' => MID,
			'board_list' => $boards,
			'group_list' => we::$user['groups'],
			'moderator_group' => 3,
			'permission_list' => is_array($permissions) ? 'IN (\'' . implode('\', \'', $permissions) . '\')' : ' = \'' . $permissions . '\'',
		)
	);

	// Make sure they can do it on all of the boards.
	if (wesql::num_rows($request) != count($boards))
		return false;

	$can_do = true;
	while ($row = wesql::fetch_assoc($request))
	{
		$can_do &= !empty($row['add_deny']);
		// Did this board just say it can't..? Then give up.
		if (!$can_do)
			break;
	}
	wesql::free_result($request);

	return $can_do;
}

// Fatal error if they cannot... Note that errors sent to the user will be for
// the last entry in $permissions, so make sure the hardest one to get is last.
function isAllowedTo($permissions, $boards = null)
{
	global $txt;

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

	$permissions = (array) $permissions;

	// Check the permission and return an error...
	if (!allowedTo($permissions, $boards))
	{
		// Pick the last array entry as the permission shown as the error.
		$error_permission = array_shift($permissions);

		// If they are a guest, show a login. (Because the error might be gone if they do!)
		if (we::$is_guest)
		{
			loadLanguage('Errors');
			is_not_guest($txt['cannot_' . $error_permission]);
		}

		// No need to clear the action they were doing (as it used to be; not only are the odds strong that it
		// wouldn't have been updated, this way you can see what they were trying to do and that it didn't work.)
		writeLog(true);

		fatal_lang_error('cannot_' . $error_permission, false);

		// Getting this far is a really big problem, but let's try our best to prevent any cases...
		trigger_error('Hacking attempt...', E_USER_ERROR);
	}

	// If you're doing something on behalf of some "heavy" permissions, validate your session.
	// (Take out the heavy permissions, and if you can't do anything but those, you need a validated session.)
	if (!allowedTo(array_diff($permissions, $heavy_permissions), $boards))
		validateSession();
}

// Return the boards a user has a certain (board) permission on. (array(0) if all.)
function boardsAllowedTo($permissions, $check_access = true)
{
	global $settings;

	// All groups the user is in except 'moderator'.
	$groups = array_diff(we::$user['groups'], array(3));

	if (!is_array($permissions))
	{
		// Administrators are all powerful, sorry.
		if (we::$is_admin)
			return array(0);

		// Guest and guest access disabled?
		if (we::$is_guest && empty($settings['allow_guestAccess']))
			return array();

		$request = wesql::query('
			SELECT b.id_board, bp.add_deny
			FROM {db_prefix}board_permissions AS bp
				INNER JOIN {db_prefix}boards AS b ON (b.id_profile = bp.id_profile)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
			WHERE bp.id_group IN ({array_int:group_list}, {int:moderator_group})
				AND bp.permission IN ({string:permissions})
				AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})' .
				($check_access ? ' AND {query_see_board}' : ''),
			array(
				'current_member' => MID,
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
	else
	{
		// Administrators are all powerful, sorry.
		if (we::$is_admin)
		{
			$final_list = array();
			foreach ($permissions as $perm)
				$final_list[$perm] = array(0);
			return $final_list;
		}

		// So we begin generally looking things up.
		$final_list = array();
		foreach ($permissions as $perm)
			$final_list[$perm] = array();

		// Guest and guest access disabled? No point doing anything else, they won't have permission.
		if (we::$is_guest && empty($settings['allow_guestAccess']))
			return $final_list;

		$boards = array();
		$deny_boards = array();
		$request = wesql::query('
			SELECT b.id_board, bp.permission, bp.add_deny
			FROM {db_prefix}board_permissions AS bp
				INNER JOIN {db_prefix}boards AS b ON (b.id_profile = bp.id_profile)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
			WHERE bp.id_group IN ({array_int:group_list}, {int:moderator_group})
				AND bp.permission IN ({array_string:permissions})
				AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})' .
				($check_access ? ' AND {query_see_board}' : ''),
			array(
				'current_member' => MID,
				'group_list' => $groups,
				'moderator_group' => 3,
				'permissions' => $permissions,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$deny_boards[$row['permission']][] = $row['id_board'];
			else
				$boards[$row['permission']][] = $row['id_board'];
		}
		wesql::free_result($request);

		foreach ($permissions as $perm)
		{
			if (empty($boards[$perm]))
				$boards[$perm] = array();
			if (empty($deny_boards[$perm]))
				$deny_boards[$perm] = array();

			$final_list[$perm] = array_unique(array_values(array_diff($boards[$perm], $deny_boards[$perm])));
		}

		return $final_list;
	}
}

/**
 * Should this user's email address be shown?
 *
 * If you're guest and the forum is set to hide email for guests: no.
 * If the user is post-banned: no.
 * If it's your own profile and you've set your address hidden: yes_permission_override.
 * If you're a moderator with sufficient permissions: yes_permission_override.
 * If the user has set their email address to be hidden: no.
 * Otherwise: no_through_forum for forum-based send email.
 *
 * @return string Returns either 'no', 'no_through_forum' or 'yes_permission_override'.
 */
function showEmailAddress($userProfile_hideEmail, $userProfile_id)
{
	return we::$is_guest || we::$user['post_moderated'] ? 'no' : ((we::$is_member && MID == $userProfile_id && !$userProfile_hideEmail) || allowedTo('moderate_forum') ? 'yes_permission_override' : ($userProfile_hideEmail ? 'no' : 'no_through_forum'));
}

/**
 * Attempt to validate the request against defined white- and black-lists.
 *
 * Part of the anti-spam measures include whitelist and blacklist items to be screened against. This is where such are processed.
 *
 * Much of the functionality is derived from Bad Behavior (http://bad-behavior.ioerror.us/), which is licensed under the GNU LGPL 3.0 and used herein under clause 4.
 * As such, we are required to make reference to the GNU GPL 3.0 - http://www.gnu.org/licenses/gpl-3.0.html - and its child licence GNU LGPL 3.0 - http://www.gnu.org/licenses/lgpl-3.0.html - it is acknowledged that we have not included the full licence text with the package, because the core package is not itself GPL/LGPL licensed and we believe it would lead to confusion if multiple licence documents were provided. We will seek to provide clarification where this is necessary.
 *
 * @return mixed Returns true if whitelisted, or not confirmed as spammer in any way; false if matching any rules but the current user is an administrator (so they receive an alternate warning), otherwise execution will be suspended and the request directed to an appropriate error page.
 */
function checkUserBehavior()
{
	global $context, $txt, $webmaster_email, $board, $board_info;

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

	if (!empty($context['http_headers']['user-agent']))
	{
		foreach ($whitelist['user-agent'] as $item)
			if ($context['http_headers']['user-agent'] === $item)
				return true;
	}
	else
		$context['http_headers']['user-agent'] = ''; // Just in case we didn't actually get one.

	if (!empty($_GET['action']))
		foreach ($whitelist['action'] as $item)
			if ($_GET['action'] === $item)
				return true;

	// So they didn't get whitelisted, eh? Well, are they blacklisted?
	$context['behavior_error'] = '';

	if (checkUserRequest_blacklist() || checkUserRequest_request() || checkUserRequest_useragent() || checkUserRequest_post())
	{
		// Process the headers into a nice string then log the intrusion.
		$headers = '';
		$entity = '';

		foreach ($context['http_headers'] as $k => $v)
			if ($k != 'user-agent')
				$headers .= ($headers != '' ? '<br>' : '') . htmlspecialchars($k . '=' . ($k != 'x-forwarded-for' ? $v : format_ip($v)));

		foreach ($_POST as $k => $v)
			$entity .= ($entity != '' ? '<br>' : '') . htmlspecialchars($k . '=' . $v);

		wesql::insert('',
			'{db_prefix}log_intrusion',
			array(
				'id_member' => 'int', 'error_type' => 'string', 'ip' => 'int', 'event_time' => 'int', 'http_method' => 'string',
				'request_uri' => 'string-255', 'protocol' => 'string', 'user_agent' => 'string-255', 'headers' => 'string', 'request_entity' => 'string',
			),
			array(
				MID, substr($context['behavior_error'], 6), get_ip_identifier($_SERVER['REMOTE_ADDR']), time(), $_SERVER['REQUEST_METHOD'],
				isset($_SERVER['REAL_REQUEST_URI']) ? $_SERVER['REAL_REQUEST_URI'] : $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL'], $context['http_headers']['user-agent'], $headers, $entity,
			)
		);
		$error_id = wesql::insert_id();

		if (we::$is_admin)
			return false;
		else
		{
			list ($error, $error_blocks) = userBehaviorResponse();
			header('HTTP/1.1 ' . $error . ' Wedge Defenses');
			header('Status: ' . $error . ' Wedge Defenses');

			// OK, let's start up enough of Wedge to display this error nicely.
			$context['linktree'] = array();
			$context['open_mod_reports'] = $board = 0;
			$_GET['board'] = $_GET['topic'] = '';
			$board_info = array(
				'moderators' => array(),
				'skin' => '',
			);

			loadPermissions();
			loadTheme();

			// Set the page up
			loadTemplate('Errors');
			loadLanguage('Security');

			// Figure out what we're going to tell the user
			wetem::load('fatal_error');
			$context['no_back_link'] = true;
			$context['robot_no_index'] = true;
			$context['page_title'] = $txt['http_error'] . ' ' . $error;
			$context['error_title'] = $txt['behav_' . $error];
			$context['error_message'] = $txt['behavior_header'] . '<br><br>' . $txt[$context['behavior_error'] . '_desc'];
			foreach ($error_blocks as $block)
				$context['error_message'] .= '<br><br>' . $txt[$block];
			$context['error_message'] .= '<br><br>' . $txt['behavior_footer'];

			$context['error_message'] = str_replace('{email_address}', str_replace('@', '+REMOVEME@REMOVEME.', $webmaster_email), $context['error_message']);
			$context['error_message'] = str_replace('{incident}', '#' . $error_id, $context['error_message']);

			// And we're done. Spit out header if appropriate, footer and blocks.
			obExit(null, true, false, true);
		}
	}
	else
		return true;
}

/**
 * Checks the blacklist for known user agents that have undesirable behaviors.
 *
 * Detection data courtesy of Bad Behavior (http://bad-behavior.ioerror.us/)
 *
 * @return mixed Returns false if valid, returns the id of the error (and stores it in $context['behavior_error']) if an error condition was hit.
 */
function checkUserRequest_blacklist()
{
	global $context, $settings;

	// If the user agent begins with any of these, fail it.
	$rules = array(
		'begins_with' => array(
			// harvesters
			'autoemailspider',
			'BrowserEmulator/',
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
			'core-project/',
			'Internet Explorer',
			'Winnie Poh',
			// malicious
			'Diamond',
			'MJ12bot',
			'Mozilla/0',
			'Mozilla/1',
			'Mozilla/2',
			'Mozilla/3',
			'MSIE',
			'sqlmap/',
			'Wordpress',
			'"',
			'-',
			// supplied by honeypot
			'blogsearchbot-martin',
			'Mozilla/4.0(',
			// spammers / robots
			'8484 Boston Project',
			'adwords',
			'ArchiveTeam',
			'Forum Poster',
			'grub crawler',
			'HttpProxy',
			'Jakarta Commons',
			'Java 1.',
			'Java/1.',
			'libwww-perl',
			'LWP',
			'lwp',
			'Movable Type',
			'NutchCVS',
			'Nutscrape/',
			'Opera/9.64(',
			'PussyCat ',
			'PycURL',
			'Python-urllib',
			'TrackBack/',
			'WebSite-X Suite',
			'xpymep',
			// vulnerability scanners
			'Morfeus',
			'Mozilla/4.0 (Hydra)',
			'Nessus',
			'PMAFind',
			'revolt',
			'w3af',
		),
		'contains' => array(
			// harvesters
			'Email Extractor',
			'EMail Exractor',
			'.NET CLR1',
			'Perman Surfer',
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
			'ArchiveBot',
			'hanzoweb',
			'Havij',
			'MSIE 7.0;  Windows NT 5.2',
			'Turing Machine',
			// spammers
			'; Widows ',
			'a href=',
			'compatible ; MSIE',
			'compatible-',
			'DTS Agent',
			'Gecko/2525',
			'Indy Library',
			'MVAClient',
			'Murzillo compatible',
			'.NET CLR 1)',
			'POE-Component-Client',
			'Ubuntu/9.25',
			'Windows NT 5.0;)',
			'Windows NT 5.1;)',
			'WordPress/4.01',
			'Xedant Human Emulator',
			// exploit attempts
			'<sc',
			'ZmEu',
			': ;',
			':;',
			// vulnerability scanners
			'Forest Lobster',
			'Ming Mong',
			'Netsparker',
			'Nikto/',
		),
		'contains_regex' => array(
			// spammers
			'~^[A-Z]{10}$~',
			'~[bcdfghjklmnpqrstvwxz ]{8,}~',
			'~MSIE.*Windows XP~',
			'~MSIE [2345]~',
		),
	);

	if (empty($settings['allow_jurassic_crap']))
	{
		$rules['begins_with'][] = 'Microsoft Internet Explorer/';
		$rules['contains'] += array(
			'Firebird/',
			'Win95',
			'Win98',
			'WinME',
			'Win 9x 4.90',
			'Windows 3',
			'Windows 95',
			'Windows 98',
			'Windows NT 4',
			'Windows NT;',
		);
	}

	foreach ($rules['begins_with'] as $test)
		if (strpos($context['http_headers']['user-agent'], $test) === 0)
			return $context['behavior_error'] = 'behav_blacklist';

	foreach ($rules['contains'] as $test)
		if (strpos($context['http_headers']['user-agent'], $test) !== false)
			return $context['behavior_error'] = 'behav_blacklist';

	foreach ($rules['contains_regex'] as $test)
		if (preg_match($test, $context['http_headers']['user-agent']))
			return $context['behavior_error'] = 'behav_blacklist';

	return false;
}

/**
 * Checks the request headers for known issues with respect to invalid HTTP or known spammer behavior.
 *
 * Detection data courtesy of Bad Behavior (http://bad-behavior.ioerror.us/)
 *
 * @return mixed Returns false if valid, returns the id of the error (and stores it in $context['behavior_error']) if an error condition was hit.
 */
function checkUserRequest_request()
{
	global $context, $settings;

	// Is this from CloudFlare? (requires hostname lookups enabled)
	if (isset($context['http_headers']['cf-connecting-ip'], $context['http_headers']['x-detected-remote-address']) && empty($settings['disableHostnameLookup']))
	{
		// Remember, we did some work on this back in QueryString.php, so we should have the value for CloudFlare. Let's see if it is.
		if (!test_ip_host($context['http_headers']['x-detected-remote-address'], 'cloudflare.com'))
			return $context['behavior_error'] = 'behav_not_cloudflare';
	}

	// Should never see the Expect header in HTTP/1.0. It's generally a weird proxy setup.
	if (isset($context['http_headers']['expect']) && stripos($context['http_headers']['expect'], '100-continue') !== false && stripos($_SERVER['SERVER_PROTOCOL'], 'HTTP/1.0') !== false)
		return $context['behavior_error'] = 'behav_expect_header';

	// If it's a POST, there should be a user agent specified.
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($context['http_headers']['user-agent']))
		return $context['behavior_error'] = 'behav_no_ua_in_post';

	// Content-Range denotes the size of content being *sent* not requested...
	if (isset($context['http_headers']['content-range']))
		return $context['behavior_error'] = 'behav_content_range';

	// Referer is an optional header. If it's given it must be non-blank. Additionally, all legit
	// agents should be sending absolute URIs even though the spec says relative ones are fine.
	if (isset($context['http_headers']['referer']))
	{
		if (empty($context['http_headers']['referer']))
			return $context['behavior_error'] = 'behav_empty_refer';
		elseif (strpos($context['http_headers']['referer'], '://') === false)
			return $context['behavior_error'] = 'behav_invalid_refer';
	}

	// Check for oddities in the Connection header. Various bad things happen here; mostly stupid bots.
	if (isset($context['http_headers']['connection']))
	{
		// Can't have both Connection: keep-alive and Connection: close, or more than one of either at a time. No being greedy.
		$ka = preg_match_all('~\bkeep-alive\b~i', $context['http_headers']['connection'], $dummy);
		$c = preg_match_all('~\bclose\b~i', $context['http_headers']['connection'], $dummy);
		if (($ka > 0 && $c > 0) || $ka > 1 || $c > 1)
			return $context['behavior_error'] = 'behav_alive_close';

		// Does Keep-Alive conform to spec?
		if (stripos($context['http_headers']['connection'], 'Keep-Alive: ') !== false)
			return $context['behavior_error'] = 'behav_wrong_keep_alive';
	}

	// Check for weirdness in the requested URI
	if (!empty($_SERVER['REQUEST_URI']))
	{
		$rogue_chars = array(
			'exact_contains' => array(
				'#', // should not be in the URL that hits the site, it's not a URL component
				';DECLARE%20@', // IIS injection attempt, even if we're not on IIS, this is still someone bad we don't want
				'../', // avoiding path traversal vulnerabilities
				'..\\', // again...
			),
			'insens_contains' => array(
				// SQL injection probes
				'%60information_schema%60',
				'+%2F*%21',
				'+and+%',
				'+and+1%',
				'+and+if',
				'%27--',
				'%27 --',
				'%27%23',
				'%27 %23',
				'benchmark%28',
				'insert+into+',
				'r3dm0v3',
				'select+1+from',
				'union+all+select',
				'union+select',
				'waitfor+delay+',
				'0x31303235343830303536', // specific to Havij
				// vulnerability scanner
				'w00tw00t',
			),
		);
		foreach ($rogue_chars['exact_contains'] as $str)
			if (strpos($_SERVER['REQUEST_URI'], $str) !== false)
				return $context['behavior_error'] = 'behav_rogue_chars';

		$insens = strtolower($_SERVER['REQUEST_URI']);
		foreach ($rogue_chars['insens_contains'] as $str)
			if (strpos($insens, $str) !== false)
				return $context['behavior_error'] = 'behav_rogue_chars';
	}

	// A known referrer spammer
	if (isset($context['http_headers']['via']) && (stripos($context['http_headers']['via'], 'pinappleproxy') !== false || stripos($context['http_headers']['via'], 'PCNETSERVER') !== false || stripos($context['http_headers']['via'], 'Invisiware') !== false))
		return $context['behavior_error'] = 'behav_banned_via_proxy';

	// Known spambot headers
	if (isset($context['http_headers']['x-aaaaaaaaaaaa']) || isset($context['http_headers']['x-aaaaaaaaaa']))
		return $context['behavior_error'] = 'behav_banned_xaa_proxy';

	// Are we in compliance with RFC 2965 sections 3.3.5 and 9.1? Specifically, bots wanting new-style cookies should send Cookie2 as a header.
	// Apparently this does not work on the first edition Kindle, but really... though it's not a forum platform, it's not like it's hard to override.
	if (isset($context['http_headers']['cookie']) && strpos($context['http_headers']['cookie'], '$Version=0') !== false && !isset($context['http_headers']['cookie2']) && strpos($context['http_headers']['user-agent'], 'Kindle/') === false)
		return $context['behavior_error'] = 'behav_bot_rfc2965';

	// OK, are we doing the big scary strict tests? If not, bail. Some of these tests will fail on weird things like some corporate proxy servers so we don't do them by default.
	if (empty($settings['performStrictBehavior']))
		return false;

	// Proxy-Connection isn't a real header.
	// !! Doesn't this get thrown on Firefox < v18 when a proxy is enabled?
	// https://developer.mozilla.org/en-US/docs/Site_Compatibility_for_Firefox_18
	if (isset($context['http_headers']['proxy-connection']))
		return $context['behavior_error'] = 'behav_proxy_connection';

	// Connections claiming HTTP/1.1 should not use HTTP/1.0 caching instructions
	if ($_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1' && isset($context['http_headers']['pragma']) && strpos($context['http_headers']['pragma'], 'no-cache') !== false && !isset($context['http_headers']['cache-control']))
		return $context['behavior_error'] = 'behav_pragma';

	// RFC 2616 14.39 states that if TE is specified as a header, Connection: TE must also be specified.
	// Microsoft ISA Server 2004 does not play nicely however this is a minor case for us.
	if (isset($context['http_headers']['te']) && !preg_match('~\bTE\b~', $context['http_headers']['connection']))
		return $context['behavior_error'] = 'behav_te_error';

	// When specified, Range should exist and not begin with a 0 for requesting (since it's requesting a follow-on partial dataset)
	// whois.sc is broken by this. So are Facebook, LiveJournal and MovableType (for which exceptions have been made, for FB and OpenID in particular)
	if (isset($context['http_headers']['range']) && strpos($context['http_headers']['range'], '=0-') !== false && (strpos($context['http_headers']['user-agent'], 'MovableType') !== 0 && strpos($context['http_headers']['user-agent'], 'URI::Fetch') !== 0 && strpos($context['http_headers']['user-agent'], 'php-openid/') !== 0 && strpos($context['http_headers']['user-agent'], 'facebookexternalhit') !== 0))
		return $context['behavior_error'] = 'behav_invalid_range';

	return false;
}

/**
 * Performs acceptance checks that are based primarily on the user agent for the current request.
 *
 * Detection data courtesy of Bad Behavior (http://bad-behavior.ioerror.us/)
 *
 * @return mixed Returns false if valid, returns the id of the error (and stores it in $context['behavior_error']) if an error condition was hit.
 */
function checkUserRequest_useragent()
{
	global $context, $settings;

	$ua = $context['http_headers']['user-agent'];
	$lua = strtolower($ua);

	// For most browsers, there's only one test to do, to make sure they send an Accept header. Naughty bots pretending to be these folks don't normally.
	if (strhas($ua, array('Opera', 'Lynx', 'Safari')) && !we::$is_member)
	{
		if (!isset($context['http_headers']['accept']))
			return $context['behavior_error'] = 'behav_no_accept';
	}
	// Some browsers are just special however. Konqueror is on the surface, straightforward, but there's a Yahoo dev project that isn't a real browser but calls itself Konqueror, so we have to do the normal browser test but exclude if it's this.
	elseif (strhas($lua, 'konqueror') && !we::$is_member)
	{
		if (!isset($context['http_headers']['accept']) && (!strhas($lua, 'yahooseeker/cafekelsa') || !match_cidr($_SERVER['REMOTE_ADDR'], '209.73.160.0/19')))
			return $context['behavior_error'] = 'behav_no_accept';
	}
	// Ah, Internet Explorer. We already got rid of Opera, which sometimes sends MSIE in the headers.
	elseif (strhas($ua, '; MSIE') && !we::$is_member)
	{
		// Silly bots think IE sends "Windows XP" or similar in the 'what browser we're using' area. Except, it doesn't.
		if (strhas($ua, array('Windows ME', 'Windows XP', 'Windows 2000', 'Win32')))
			return $context['behavior_error'] = 'behav_invalid_win';
		// Connection: TE again. IE doesn't use it, Akamai and IE for WinCE does.
		elseif (!isset($context['http_headers']['akamai-origin-hop']) && !strhas($ua, 'IEMobile') && @preg_match('~\bTE\b~i', $context['http_headers']['connection']))
			return $context['behavior_error'] = 'behav_te_not_msie';
	}
	// Is it claiming to be Yahoo's bot?
	elseif (strhas($lua, array('yahoo! slurp', 'yahoo! searchmonkey')))
	{
		if (we::$is_member || !match_cidr($_SERVER['REMOTE_ADDR'], array('202.160.176.0/20', '67.195.0.0/16', '203.209.252.0/24', '72.30.0.0/16', '98.136.0.0/14', '74.6.0.0/16')) || (empty($settings['disableHostnameLookup']) && !test_ip_host($_SERVER['REMOTE_ADDR'], 'crawl.yahoo.net')))
			return $context['behavior_error'] = 'behav_not_yahoobot';
	}
	// Is it claiming to be MSN's bot?
	elseif (strhas($lua, array('bingbot', 'msnbot', 'ms search')))
	{
		if (we::$is_member || (empty($settings['disableHostnameLookup']) && !test_ip_host($_SERVER['REMOTE_ADDR'], 'msn.com')))
			return $context['behavior_error'] = 'behav_not_msnbot';
	}
	// Is it claiming to be Googlebot, even?
	elseif (strhas($lua, array('googlebot', 'mediapartners-google', 'google web preview')))
	{
		if (we::$is_member || !match_cidr($_SERVER['REMOTE_ADDR'], array('66.249.64.0/19', '64.233.160.0/19', '72.14.192.0/18', '203.208.32.0/19', '74.125.0.0/16', '216.239.32.0/19', '209.85.128.0/17')) || (empty($settings['disableHostnameLookup']) && !test_ip_host($_SERVER['REMOTE_ADDR'], 'googlebot.com')))
			return $context['behavior_error'] = 'behav_not_googlebot';
	}
	// What about Baidu? I know we don't really like Baidu, but it's even generating fake bots now.
	elseif (strhas($lua, 'baidu'))
	{
		if (we::$is_member || !match_cidr($_SERVER['REMOTE_ADDR'], array('119.63.192.0/21', '123.125.71.0/24', '180.76.0.0/16', '220.181.0.0/16')))
			return $context['behavior_error'] = 'behav_not_baidu';
	}
	// OK, so presumably this is some kind of Mozilla derivative? No guarantee it's actually Firefox, though. All main browsers claim to be Mozilla.
	elseif (strpos($lua, 'mozilla') === 0)
	{
		// The main test for Mozilla is the same as the standard needing Accept header. But Google Desktop didn't previously support it, and since there's some legacy stuff, we accept it for now.
		if (!isset($context['http_headers']['accept']) && !strhas($ua, array('Google Desktop', 'PLAYSTATION 3')))
			return $context['behavior_error'] = 'behav_no_accept';
	}

	return false;
}

/**
 * Performs acceptance checks that are based primarily on the details of the request if it is a POST request.
 *
 * Detection data courtesy of Bad Behavior (http://bad-behavior.ioerror.us/)
 *
 * @return mixed Returns false if valid, returns the id of the error (and stores it in $context['behavior_error']) if an error condition was hit.
 */
function checkUserRequest_post()
{
	global $context, $settings;

	if ($_SERVER['REQUEST_METHOD'] != 'POST')
		return false;

	// Catch a few completely broken spambots
	foreach ($_POST as $key => $value) {
		if (strpos($key, '	document.write') !== false)
			return $context['behavior_error'] = 'behav_rogue_chars';
	}

	// What about forms? Any form posting into our forum should really be inside our forum. Providing an option to disable (hidden for now)
	// !!! Check whether this is relevant in subdomain boards or not.
	if (empty($settings['allow_external_forms']) && isset($context['http_headers']['referer']) && stripos($context['http_headers']['referer'], $context['http_headers']['host']) === false)
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
		case 'behav_not_baidu':
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

/**
 * Compares a given IP address and a domain to validate that the IP address belongs to that domain.
 *
 * Given an IP address, look up the associated fully-qualified domain, validate the supplied domain contains the FQDN, then request a list of IPs that belong to that domain to validate they tie up. (It is a method to validate that an IP address belongs to a given parent domain)
 *
 * @param string $ip An IPv4 dotted-format IP address.
 * @param string $domain A top level domain name to validate relationship to IP address (e.g. domain.com)
 * @return bool Whether the IP address could be validated as being related to that domain.
 * @todo DNS failure causes a general failure in this check. Fix this!
 */
function test_ip_host($ip, $domain)
{
	// !!! DNS failure cannot be adequately detected due to a PHP bug. Until a solution is found, forcibly override this check.
	return true;

	$host = host_from_ip($ip);
	$host_result = strpos(strrev($host), strrev($domain));
	if ($host_result === false || $host_result > 0)
		return false; // either the (reversed) FQDN didn't match the (reversed) supplied parent domain, or it didn't match at the end of the name.
	$addrs = gethostbynamel($host);
	return in_array($ip, $addrs);
}

function get_privacy_type($privacy)
{
	// If privacy is one of your contact lists, you'll see the icon for its list type.
	// If it's someone else's contact list, you'll see a generic icon.
	return $privacy == PRIVACY_DEFAULT ? 'public' :
		($privacy == PRIVACY_MEMBERS ? 'members' :
		($privacy < 0 ? 'group' :
		($privacy == PRIVACY_AUTHOR ? 'author' :
		($privacy > 99 ? (isset(we::$user['contacts']['lists'][$privacy][1]) ? 'list_' . we::$user['contacts']['lists'][$privacy][1] : 'list') : ''))));
}

function get_privacy_icon($privacy)
{
	global $txt;

	if (!($type = get_privacy_type($privacy)) || $type === 'public')
		return '';

	return '<div class="privacy_' . $type . '" title="' . (strpos($type, 'list') === 0 ? $txt['privacy_list'] : $txt['privacy_' . $type]) . '"></div>';
}

// This is currently tailored for profile privacy... Then again, it's what matters the most.
function get_privacy_widget($privacy, $can_edit = false, $text = '', $area = '')
{
	global $txt;

	if (we::$is_guest)
		return $text;

	$list = array(
		PRIVACY_DEFAULT => 'public',
		PRIVACY_MEMBERS => 'members',
		PRIVACY_AUTHOR => 'author',
	);
	$privacy = explode(',', $privacy);
	$shown_privacy = min($privacy);
	foreach ($privacy as $this_privacy)
	{
		if (isset(we::$user['contacts']['lists'][$this_privacy]))
			$list[$this_privacy] = 'list';
		elseif (isset(we::$user['contacts']['groups'][-$this_privacy]))
			$list[$this_privacy] = 'group';
	}

	$prvlist = '';
	foreach (array('default', 'public', 'members', 'group', 'list', 'author') as $prv)
		$prvlist .= '
		"' . $prv . '": ' . JavaScriptEscape($txt['privacy_' . $prv]) . ',';

	add_js_unique('
	prv_opt = {' . substr($prvlist, 0, -1) . '
	};
	$(".privacy").each(function () {
		$(this).title(
			' . JavaScriptEscape($txt['privacy_bubble']) . '.replace("{PRIVACY}", prv_opt[$(this).find("div").first().attr("class").replace("privacy_", "")])' . ($can_edit ? '
			+ ' . JavaScriptEscape('<br>' . $txt['privacy_can_edit']) : '') . '
		);
	});');

	if ($can_edit)
		add_js_unique('
	$(".prv_sel").change(function (e) {
		show_ajax();
		var that = $(this), v = that.val(), prv, tmp;
		$.get(weUrl("action=profile;u=' . we::$id . ';prv=" + v + ";pa=" + that.parent().find(".privacy").attr("id").slice(3) + ";" + we_sessvar + "=" + we_sessid), function (ret) {
			hide_ajax();
			$.each(ret.split(","), function (index, val) {
				tmp = "";
				if (val == ' . PRIVACY_DEFAULT . ')
					tmp = "public";
				else if (val == ' . PRIVACY_MEMBERS . ')
					tmp = "members";
				else if (val == ' . PRIVACY_AUTHOR . ')
					tmp = "author";
				if (tmp)
				{
					prv = tmp;
					return false;
				}
				prv = val > 0 ? "list" : "group";
			});
			that.siblings(".mime").find(".privacy>div").attr("class", "privacy_" + prv);
		});
	});
	$(".privacy").click(function (e) {
		var p = $(this).parent().siblings("select").data("sb");
		p && p.open();
		return false;
	});');

	return '
		<span class="privacy"' . ($area ? ' id="pa_' . $area . '"' : '') . '><div class="privacy_' . $list[$shown_privacy] . '"></div>' . $text . '</span>' . ($can_edit ? '
		<select class="prv_sel" multiple>' . get_privacy_options(array_flip($privacy)) . '</select>' : '');
}

function get_privacy_options($privacy = array())
{
	global $txt;

	$pr = '<option value="' . PRIVACY_DEFAULT . '"' . (isset($privacy[PRIVACY_DEFAULT]) ? ' selected' : '') . ' class="single"">&lt;div class="privacy_public"&gt;&lt;/div&gt;' . $txt['privacy_public'] . '</option>';
	$pr .= '<option value="' . PRIVACY_MEMBERS . '"' . (isset($privacy[PRIVACY_MEMBERS]) ? ' selected' : '') . ' class="single">&lt;div class="privacy_members"&gt;&lt;/div&gt;' . $txt['privacy_members'] . '</option>';
	$pr .= '<option value="' . PRIVACY_AUTHOR . '"' . (isset($privacy[PRIVACY_AUTHOR]) ? ' selected' : '') . ' class="single">&lt;div class="privacy_author"&gt;&lt;/div&gt;' . $txt['privacy_author'] . '</option>';
	if (!empty(we::$user['contacts']['lists']))
	{
		$pr .= '<optgroup label="' . $txt['privacy_list'] . '">';
		foreach (we::$user['contacts']['lists'] as $id => $p)
			$pr .= '<option value="' . $id . '"' . (isset($privacy[$id]) ? ' selected' : '') . '>&lt;div class="privacy_list_' . $p[1] . '"&gt;&lt;/div&gt;' . generic_contacts($p[0]) . '</option>';
		$pr .= '</optgroup>';
	}
	if (!empty(we::$user['contacts']['groups']))
	{
		$pr .= '<optgroup label="' . $txt['privacy_group'] . '">';
		foreach (we::$user['contacts']['groups'] as $id => $p)
			$pr .= '<option value="-' . $id . '"' . (isset($privacy[-$id]) ? ' selected' : '') . '>&lt;div class="privacy_group"&gt;&lt;/div&gt;' . ($p[1] >= 0 ? '&lt;em&gt;' . $p[0] . '&lt;/em&gt; &lt;small&gt;' . $p[1] . '&lt;/small&gt;' : $p[0]) . '</option>';
		$pr .= '</optgroup>';
	}
	return $pr;
}
