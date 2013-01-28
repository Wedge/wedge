<?php
/**
 * Wedge
 *
 * Initializes the administration panel area for Wedge and routes the request appropriately.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file is concerned pretty entirely, as you see from its name, with
	matters supporting logging in and out members, and the validation of that. It contains:

	string md5_hmac(string data, string key)
		- old style SMF 1.0.x/YaBB SE 1.5.x hashing.
		- returns the HMAC MD5 of data with key.

	string phpBB3_password_check(string passwd, string passwd_hash)
		- custom encryption for phpBB3 based passwords.

	void validatePasswordFlood(id_member, password_flood_value = false, was_correct = false)
		- this function helps protect against brute force attacks on a member's password.
*/

function checkActivation()
{
	global $context, $txt, $scripturl, $user_settings, $settings;

	if (!isset($context['login_errors']))
		$context['login_errors'] = array();

	// What is the true activation status of this account?
	$activation_status = $user_settings['is_activated'] % 10;

	// Check if the account is activated - COPPA first...
	if ($activation_status == 5)
	{
		$context['login_errors'][] = $txt['coppa_no_concent'] . ' <a href="' . $scripturl . '?action=coppa;member=' . $user_settings['id_member'] . '">' . $txt['coppa_need_more_details'] . '</a>';
		return false;
	}
	// Awaiting approval still?
	elseif ($activation_status == 3)
		fatal_lang_error('still_awaiting_approval', 'user');
	// Awaiting deletion, changed their mind?
	elseif ($activation_status == 4)
	{
		if (isset($_REQUEST['undelete']))
		{
			updateMemberData($user_settings['id_member'], array('is_activated' => 1));
			updateSettings(array('unapprovedMembers' => ($settings['unapprovedMembers'] > 0 ? $settings['unapprovedMembers'] - 1 : 0)));
		}
		else
		{
			$context['disable_login_hashing'] = true;
			$context['login_errors'][] = $txt['awaiting_delete_account'];
			$context['login_show_undelete'] = true;
			return false;
		}
	}
	// Standard activation?
	elseif ($activation_status != 1)
	{
		if (!empty($settings['enableErrorPasswordLogging']))
			log_error($txt['activate_not_completed1'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', false);

		$context['login_errors'][] = $txt['activate_not_completed1'] . ' <a href="' . $scripturl . '?action=activate;sa=resend;u=' . $user_settings['id_member'] . '">' . $txt['activate_not_completed2'] . '</a>';
		return false;
	}
	return true;
}

function DoLogin()
{
	global $txt, $scripturl, $user_settings;
	global $cookiename, $maintenance, $settings, $context;

	// Load cookie authentication stuff.
	loadSource('Subs-Auth');

	// Call login hook functions.
	call_hook('login', array($user_settings['member_name'], isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) == 40 ? $_POST['hash_passwrd'] : null, $settings['cookieTime']));

	// Get ready to set the cookie...
	$username = $user_settings['member_name'];
	we::$id = $user_settings['id_member'];

	// Bam! Cookie set. A session too, just in case.
	setLoginCookie(60 * $settings['cookieTime'], $user_settings['id_member'], sha1($user_settings['passwd'] . $user_settings['password_salt']));

	// Reset the login threshold.
	if (isset($_SESSION['failed_login']))
		unset($_SESSION['failed_login']);

	we::$cache = array();
	we::$is_guest = false;
	$user_settings['additional_groups'] = explode(',', $user_settings['additional_groups']);
	we::$is_admin = $user_settings['id_group'] == 1 || in_array(1, $user_settings['additional_groups']);

	// Are you banned?
	is_not_banned(true);

	// An administrator, set up the login so they don't have to type it again.
	if (we::$is_admin)
	{
		$_SESSION['admin_time'] = time();
		unset($_SESSION['just_registered']);
	}

	// Don't stick the language or theme after this point.
	unset($_SESSION['language'], $_SESSION['id_theme']);

	// First login?
	$request = wesql::query('
		SELECT last_login
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
			AND last_login = 0',
		array(
			'id_member' => we::$id,
		)
	);
	if (wesql::num_rows($request) == 1)
		$_SESSION['first_login'] = true;
	else
		unset($_SESSION['first_login']);
	wesql::free_result($request);

	// You've logged in, haven't you?
	updateMemberData(we::$id, array('last_login' => time(), 'member_ip' => we::$user['ip'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']));

	// Get rid of the online entry for that old guest....
	wesql::query('
		DELETE FROM {db_prefix}log_online
		WHERE session = {string:session}',
		array(
			'session' => 'ip' . we::$user['ip'],
		)
	);
	$_SESSION['log_time'] = 0;

	// Just log you back out if it's in maintenance mode and you AREN'T an admin.
	if (empty($maintenance) || allowedTo('admin_forum'))
		redirectexit('action=login2;sa=check;member=' . we::$id, $context['server']['needs_login_fix']);
	else
		redirectexit('action=logout;' . $context['session_query'], $context['server']['needs_login_fix']);
}

// MD5 Encryption used for older passwords.
function md5_hmac($data, $key)
{
	$key = str_pad(strlen($key) <= 64 ? $key : pack('H*', md5($key)), 64, chr(0x00));
	return md5(($key ^ str_repeat(chr(0x5c), 64)) . pack('H*', md5(($key ^ str_repeat(chr(0x36), 64)) . $data)));
}

// Special encryption used by phpBB3.
function phpBB3_password_check($passwd, $passwd_hash)
{
	// Too long or too short?
	if (strlen($passwd_hash) != 34)
		return;

	// Range of characters allowed.
	$range = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

	// Tests
	$strpos = strpos($range, $passwd_hash[3]);
	$count = 1 << $strpos;
	$count2 = $count;
	$salt = substr($passwd_hash, 4, 8);
	$hash = md5($salt . $passwd, true);

	for (; $count != 0; --$count)
		$hash = md5($hash . $passwd, true);

	$output = substr($passwd_hash, 0, 12);
	$i = 0;
	while ($i < 16)
	{
		$value = ord($hash[$i++]);
		$output .= $range[$value & 0x3f];

		if ($i < 16)
			$value |= ord($hash[$i]) << 8;

		$output .= $range[($value >> 6) & 0x3f];

		if ($i++ >= 16)
			break;

		if ($i < 16)
			$value |= ord($hash[$i]) << 16;

		$output .= $range[($value >> 12) & 0x3f];

		if ($i++ >= 16)
			break;

		$output .= $range[($value >> 18) & 0x3f];
	}

	// Return now.
	return $output;
}

// Define the old SMF sha1 function.
function sha1_smf($str)
{
	// If we have mhash loaded in, use it instead!
	if (function_exists('mhash') && defined('MHASH_SHA1'))
		return bin2hex(mhash(MHASH_SHA1, $str));

	$nblk = (strlen($str) + 8 >> 6) + 1;
	$blks = array_pad(array(), $nblk * 16, 0);

	for ($i = 0; $i < strlen($str); $i++)
		$blks[$i >> 2] |= ord($str{$i}) << (24 - ($i % 4) * 8);

	$blks[$i >> 2] |= 0x80 << (24 - ($i % 4) * 8);

	return sha1_core($blks, strlen($str) * 8);
}

// This is the core SHA-1 calculation routine, used by sha1().
function sha1_core($x, $len)
{
	@$x[$len >> 5] |= 0x80 << (24 - $len % 32);
	$x[(($len + 64 >> 9) << 4) + 15] = $len;

	$w = array();
	$a = 1732584193;
	$b = -271733879;
	$c = -1732584194;
	$d = 271733878;
	$e = -1009589776;

	for ($i = 0, $n = count($x); $i < $n; $i += 16)
	{
		list ($olda, $oldb, $oldc, $oldd, $olde) = array($a, $b, $c, $d, $e);

		for ($j = 0; $j < 80; $j++)
		{
			if ($j < 16)
				$w[$j] = isset($x[$i + $j]) ? $x[$i + $j] : 0;
			else
				$w[$j] = sha1_rol($w[$j - 3] ^ $w[$j - 8] ^ $w[$j - 14] ^ $w[$j - 16], 1);

			$t = sha1_rol($a, 5) + sha1_ft($j, $b, $c, $d) + $e + $w[$j] + sha1_kt($j);
			$e = $d;
			$d = $c;
			$c = sha1_rol($b, 30);
			$b = $a;
			$a = $t;
		}

		$a += $olda;
		$b += $oldb;
		$c += $oldc;
		$d += $oldd;
		$e += $olde;
	}

	return sprintf('%08x%08x%08x%08x%08x', $a, $b, $c, $d, $e);
}

function sha1_ft($t, $b, $c, $d)
{
	if ($t < 20)
		return ($b & $c) | ((~$b) & $d);
	if ($t < 40)
		return $b ^ $c ^ $d;
	if ($t < 60)
		return ($b & $c) | ($b & $d) | ($c & $d);

	return $b ^ $c ^ $d;
}

function sha1_kt($t)
{
	return $t < 20 ? 1518500249 : ($t < 40 ? 1859775393 : ($t < 60 ? -1894007588 : -899497514));
}

function sha1_rol($num, $cnt)
{
	// Unfortunately, PHP uses unsigned 32-bit longs only. So we have to kludge it a bit.
	if ($num & 0x80000000)
		$a = ($num >> 1 & 0x7fffffff) >> (31 - $cnt);
	else
		$a = $num >> (32 - $cnt);

	return ($num << $cnt) | $a;
}

// This protects against brute force attacks on a member's password. Importantly, even if the password was right, we DON'T TELL THEM!
function validatePasswordFlood($id_member, $password_flood_value = false, $was_correct = false)
{
	global $cookiename;

	// As this is only brute protection, we allow 5 attempts every 10 seconds.

	// Destroy any session or cookie data about this member, as they validated wrong.
	loadSource('Subs-Auth');
	setLoginCookie(-3600, 0);
	if (isset($_SESSION['login_' . $cookiename]))
		unset($_SESSION['login_' . $cookiename]);

	// We need a member!
	if (!$id_member)
	{
		// Redirect back!
		redirectexit();

		// Probably not needed, but still make sure...
		fatal_lang_error('no_access', false);
	}

	// Right, have we got a flood value?
	if ($password_flood_value !== false)
		@list ($time_stamp, $number_tries) = explode('|', $password_flood_value);

	// Did we get a timestamp?
	if (empty($number_tries))
	{
		$number_tries = 0;
		$time_stamp = time();
	}

	// Hmm.
	if (!empty($number_tries))
	{
		// If it wasn't *that* long ago, don't give them another five goes.
		$number_tries = time() - $time_stamp < 20 ? 2 : $number_tries;

		// You lot, back here, now.
		if (time() - $time_stamp < 10)
			$time_stamp = time();
	}

	$number_tries++;

	// Broken the law?
	if ($number_tries > 5)
		fatal_lang_error('login_threshold_brute_fail', 'critical');

	// Otherwise set the member's data. If they're correct on their first attempt then we actually clear it, otherwise we set it!
	updateMemberData($id_member, array('passwd_flood' => $was_correct && $number_tries == 1 ? '' : $time_stamp . '|' . $number_tries));
}
