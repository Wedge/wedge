<?php
/**********************************************************************************
* Login2.php                                                                      *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC4                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file is concerned pretty entirely, as you see from its name, with
	logging in and out members, and the validation of that.  It contains:

	void Login2()
		- actually logs you in and checks that login was successful.
		- employs protection against a specific IP or user trying to brute
		  force a login to an account.
		- on error, uses the same templates Login() uses.
		- upgrades password encryption on login, if necessary.
		- after successful login, redirects you to $_SESSION['login_url'].
		- accessed from ?action=login2, by forms.

*/

// Perform the actual logging-in.
function Login2()
{
	global $txt, $scripturl, $user_info, $user_settings;
	global $cookiename, $maintenance, $modSettings, $context, $sc;

	// Load cookie authentication stuff and subsidiary login stuff.
	loadSource(array('Subs-Auth', 'Subs-Login'));

	if (isset($_GET['sa']) && $_GET['sa'] == 'salt' && !$user_info['is_guest'])
	{
		if (isset($_COOKIE[$cookiename]) && preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~', $_COOKIE[$cookiename]) === 1)
			list (,, $timeout) = @unserialize($_COOKIE[$cookiename]);
		elseif (isset($_SESSION['login_' . $cookiename]))
			list (,, $timeout) = @unserialize($_SESSION['login_' . $cookiename]);
		else
			trigger_error('Login2(): Cannot be logged in without a session or cookie', E_USER_ERROR);

		$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);
		updateMemberData($user_info['id'], array('password_salt' => $user_settings['password_salt']));

		setLoginCookie($timeout - time(), $user_info['id'], sha1($user_settings['passwd'] . $user_settings['password_salt']));

		redirectexit('action=login2;sa=check;member=' . $user_info['id'], $context['server']['needs_login_fix']);
	}
	// Double check the cookie...
	elseif (isset($_GET['sa']) && $_GET['sa'] == 'check')
	{
		// Strike!  You're outta there!
		if ($_GET['member'] != $user_info['id'])
			fatal_lang_error('login_cookie_error', false);

		// Some whitelisting for login_url...
		if (empty($_SESSION['login_url']))
			redirectexit();
		else
		{
			// Best not to clutter the session data too much...
			$temp = $_SESSION['login_url'];
			unset($_SESSION['login_url']);

			redirectexit($temp);
		}
	}

	// Beyond this point you are assumed to be a guest trying to login.
	if (!$user_info['is_guest'])
		redirectexit();

	// Are you guessing with a script that doesn't keep the session id?
	spamProtection('login');

	// Set the login_url if it's not already set (but careful not to send us to an attachment).
	if (empty($_SESSION['login_url']) && isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0)
		$_SESSION['login_url'] = $_SESSION['old_url'];

	// Been guessing a lot, haven't we?
	if (isset($_SESSION['failed_login']) && $_SESSION['failed_login'] >= $modSettings['failed_login_threshold'] * 3)
		fatal_lang_error('login_threshold_fail', 'critical');

	// Set up the cookie length.  (if it's invalid, just fall through and use the default.)
	if (isset($_POST['cookieneverexp']) || (!empty($_POST['cookielength']) && $_POST['cookielength'] == -1))
		$modSettings['cookieTime'] = 3153600;
	elseif (!empty($_POST['cookielength']) && ($_POST['cookielength'] >= 1 || $_POST['cookielength'] <= 525600))
		$modSettings['cookieTime'] = (int) $_POST['cookielength'];

	loadLanguage('Login');
	// Load the template stuff - wireless or normal.
	if (WIRELESS)
		$context['sub_template'] = WIRELESS_PROTOCOL . '_login';
	else
	{
		loadTemplate('Login');
		$context['sub_template'] = 'login';
	}

	// Set up the default/fallback stuff.
	$context['default_username'] = isset($_REQUEST['user']) ? preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($_REQUEST['user'])) : '';
	$context['default_password'] = '';
	$context['never_expire'] = $modSettings['cookieTime'] == 525600 || $modSettings['cookieTime'] == 3153600;
	$context['login_errors'] = array($txt['error_occured']);
	$context['page_title'] = $txt['login'];

	// Add the login chain to the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=login',
		'name' => $txt['login'],
	);

	if (!empty($_REQUEST['openid_identifier']) && !empty($modSettings['enableOpenID']))
	{
		loadSource('Subs-OpenID');
		if (($open_id = smf_openID_validate($_REQUEST['openid_identifier'])) !== 'no_data')
			return $open_id;
	}

	// You forgot to type your username, dummy!
	if (!isset($_REQUEST['user']) || $_REQUEST['user'] == '')
	{
		$context['login_errors'] = array($txt['need_username']);
		return;
	}

	// Hmm... maybe 'admin' will login with no password. Uhh... NO!
	if ((!isset($_POST['passwrd']) || $_POST['passwrd'] == '') && (!isset($_REQUEST['hash_passwrd']) || strlen($_REQUEST['hash_passwrd']) != 40))
	{
		$context['login_errors'] = array($txt['no_password']);
		return;
	}

	// No funky symbols either.
	if (preg_match('~[<>&"\'=\\\]~', preg_replace('~(&#(\\d{1,7}|x[0-9a-fA-F]{1,6});)~', '', $_REQUEST['user'])) != 0)
	{
		$context['login_errors'] = array($txt['error_invalid_characters_username']);
		return;
	}

	// Are we using any sort of integration to validate the login?
	if (in_array('retry', call_hook('validate_login', array($_REQUEST['user'], isset($_REQUEST['hash_passwrd']) && strlen($_REQUEST['hash_passwrd']) == 40 ? $_REQUEST['hash_passwrd'] : null, $modSettings['cookieTime'])), true))
	{
		$context['login_errors'] = array($txt['login_hash_error']);
		$context['disable_login_hashing'] = true;
		return;
	}

	// Load the data up!
	$request = wesql::query('
		SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt,
			openid_uri, passwd_flood
		FROM {db_prefix}members
		WHERE member_name = {string:user_name}
		LIMIT 1',
		array(
			'user_name' => $_REQUEST['user'],
		)
	);
	// Probably mistyped or their email, try it as an email address. (member_name first, though!)
	if (wesql::num_rows($request) == 0)
	{
		wesql::free_result($request);

		$request = wesql::query('
			SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt, openid_uri,
			passwd_flood
			FROM {db_prefix}members
			WHERE email_address = {string:user_name}
			LIMIT 1',
			array(
				'user_name' => $_REQUEST['user'],
			)
		);
		// Let them try again, it didn't match anything...
		if (wesql::num_rows($request) == 0)
		{
			$context['login_errors'] = array($txt['username_no_exist']);
			return;
		}
	}

	$user_settings = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// Figure out the password using SMF's encryption - if what they typed is right.
	if (isset($_REQUEST['hash_passwrd']) && strlen($_REQUEST['hash_passwrd']) == 40)
	{
		// Needs upgrading?
		if (strlen($user_settings['passwd']) != 40)
		{
			$context['login_errors'] = array($txt['login_hash_error']);
			$context['disable_login_hashing'] = true;
			unset($user_settings);
			return;
		}
		// Challenge passed.
		elseif ($_REQUEST['hash_passwrd'] == sha1($user_settings['passwd'] . $sc))
			$sha_passwd = $user_settings['passwd'];
		else
		{
			// Don't allow this!
			validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood']);

			$_SESSION['failed_login'] = @$_SESSION['failed_login'] + 1;

			if ($_SESSION['failed_login'] >= $modSettings['failed_login_threshold'])
				redirectexit('action=reminder');
			else
			{
				log_error($txt['incorrect_password'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', 'user');

				$context['disable_login_hashing'] = true;
				$context['login_errors'] = array($txt['incorrect_password']);
				unset($user_settings);
				return;
			}
		}
	}
	else
		$sha_passwd = sha1(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));

	// Bad password!  Thought you could fool the database?!
	if ($user_settings['passwd'] != $sha_passwd)
	{
		// Let's be cautious, no hacking please. thanx.
		validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood']);

		// Maybe we were too hasty... let's try some other authentication methods.
		$other_passwords = array();

		// None of the below cases will be used most of the time (because the salt is normally set.)
		if ($user_settings['password_salt'] == '')
		{
			// YaBB SE, Discus, MD5 (used a lot), SHA-1 (used some), SMF 1.0.x, IkonBoard, and none at all.
			$other_passwords[] = crypt($_POST['passwrd'], substr($_POST['passwrd'], 0, 2));
			$other_passwords[] = crypt($_POST['passwrd'], substr($user_settings['passwd'], 0, 2));
			$other_passwords[] = md5($_POST['passwrd']);
			$other_passwords[] = sha1($_POST['passwrd']);
			$other_passwords[] = md5_hmac($_POST['passwrd'], strtolower($user_settings['member_name']));
			$other_passwords[] = md5($_POST['passwrd'] . strtolower($user_settings['member_name']));
			$other_passwords[] = $_POST['passwrd'];

			// This one is a strange one... MyPHP, crypt() on the MD5 hash.
			$other_passwords[] = crypt(md5($_POST['passwrd']), md5($_POST['passwrd']));

			// Snitz style - SHA-256.  Technically, this is a downgrade, but most PHP configurations don't support sha256 anyway.
			if (strlen($user_settings['passwd']) == 64 && function_exists('mhash') && defined('MHASH_SHA256'))
				$other_passwords[] = bin2hex(mhash(MHASH_SHA256, $_POST['passwrd']));

			// phpBB3 users new hashing.  We now support it as well ;).
			$other_passwords[] = phpBB3_password_check($_POST['passwrd'], $user_settings['passwd']);

			// APBoard 2 Login Method.
			$other_passwords[] = md5(crypt($_REQUEST['passwrd'], 'CRYPT_MD5'));
		}
		// The hash should be 40 if it's SHA-1, so we're safe with more here too.
		elseif (strlen($user_settings['passwd']) == 32)
		{
			// vBulletin 3 style hashing?  Let's welcome them with open arms \o/.
			$other_passwords[] = md5(md5($_POST['passwrd']) . $user_settings['password_salt']);

			// Hmm.. p'raps it's Invision 2 style?
			$other_passwords[] = md5(md5($user_settings['password_salt']) . md5($_POST['passwrd']));

			// Some common md5 ones.
			$other_passwords[] = md5($user_settings['password_salt'] . $_POST['passwrd']);
			$other_passwords[] = md5($_POST['passwrd'] . $user_settings['password_salt']);
			$other_passwords[] = md5($_POST['passwrd']);
			$other_passwords[] = md5(md5($_POST['passwrd']));
		}
		elseif (strlen($user_settings['passwd']) == 40)
		{
			// Maybe they are using a hash from before the password fix.
			$other_passwords[] = sha1(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));

			// BurningBoard3 style of hashing.
			$other_passwords[] = sha1($user_settings['password_salt'] . sha1($user_settings['password_salt'] . sha1($_REQUEST['passwrd'])));

			// Perhaps we converted to UTF-8 and have a valid password being hashed differently.
			if (!empty($modSettings['previousCharacterSet']) && $modSettings['previousCharacterSet'] != 'utf8')
			{
				// Try iconv first, for no particular reason.
				if (function_exists('iconv'))
					$other_passwords['iconv'] = sha1(strtolower(iconv('UTF-8', $modSettings['previousCharacterSet'], $user_settings['member_name'])) . un_htmlspecialchars(iconv('UTF-8', $modSettings['previousCharacterSet'], $_POST['passwrd'])));

				// Say it aint so, iconv failed!
				if (empty($other_passwords['iconv']) && function_exists('mb_convert_encoding'))
					$other_passwords[] = sha1(strtolower(mb_convert_encoding($user_settings['member_name'], 'UTF-8', $modSettings['previousCharacterSet'])) . un_htmlspecialchars(mb_convert_encoding($_POST['passwrd'], 'UTF-8', $modSettings['previousCharacterSet'])));
			}
		}

		// SMF's sha1 function can give a funny result on Linux (Not our fault!). If we've now got the real one let the old one be valid!
		if (strpos(strtolower(PHP_OS), 'win') !== 0)
			$other_passwords[] = sha1_smf(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));

		// Whichever encryption it was using, let's make it use SMF's now ;).
		if (in_array($user_settings['passwd'], $other_passwords))
		{
			$user_settings['passwd'] = $sha_passwd;
			$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);

			// Update the password and set up the hash.
			updateMemberData($user_settings['id_member'], array('passwd' => $user_settings['passwd'], 'password_salt' => $user_settings['password_salt'], 'passwd_flood' => ''));
		}
		// Okay, they for sure didn't enter the password!
		else
		{
			// They've messed up again - keep a count to see if they need a hand.
			$_SESSION['failed_login'] = @$_SESSION['failed_login'] + 1;

			// Hmm... don't remember it, do you?  Here, try the password reminder ;).
			if ($_SESSION['failed_login'] >= $modSettings['failed_login_threshold'])
				redirectexit('action=reminder');
			// We'll give you another chance...
			else
			{
				// Log an error so we know that it didn't go well in the error log.
				log_error($txt['incorrect_password'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', 'user');

				$context['login_errors'] = array($txt['incorrect_password']);
				return;
			}
		}
	}
	elseif (!empty($user_settings['passwd_flood']))
	{
		// Let's be sure they weren't a little hacker.
		validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood'], true);

		// If we got here then we can reset the flood counter.
		updateMemberData($user_settings['id_member'], array('passwd_flood' => ''));
	}

	// Correct password, but they've got no salt; fix it!
	if ($user_settings['password_salt'] == '')
	{
		$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);
		updateMemberData($user_settings['id_member'], array('password_salt' => $user_settings['password_salt']));
	}

	// Check their activation status.
	if (!checkActivation())
		return;

	DoLogin();
}

?>