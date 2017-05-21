<?php
/**
 * Handles the receipt of the login form, password validation and so on.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	void Login2()
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
	global $txt, $user_settings, $cookiename, $settings, $context, $sc;

	// Load cookie authentication stuff and subsidiary login stuff.
	loadSource(array('Subs-Auth', 'Subs-Login'));

	if (isset($_GET['sa']) && $_GET['sa'] == 'salt' && we::$is_member)
	{
		if (isset($_COOKIE[$cookiename]) && preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~', $_COOKIE[$cookiename]) === 1)
			list (,, $timeout) = @unserialize($_COOKIE[$cookiename]);
		elseif (isset($_SESSION['login_' . $cookiename]))
			list (,, $timeout) = @unserialize($_SESSION['login_' . $cookiename]);
		else
			trigger_error('Login2(): Cannot be logged in without a session or cookie', E_USER_ERROR);

		$user_settings['password_salt'] = substr(md5(mt_rand()), 0, 4);
		updateMemberData(MID, array('password_salt' => $user_settings['password_salt']));

		setLoginCookie($timeout - time(), MID, sha1($user_settings['passwd'] . $user_settings['password_salt']));

		redirectexit('action=login2;sa=check;member=' . MID, $context['server']['needs_login_fix']);
	}
	// Double check the cookie...
	elseif (isset($_GET['sa']) && $_GET['sa'] == 'check')
	{
		// Strike! You're outta there!
		if ($_GET['member'] != MID)
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

	// Beyond this point, you are assumed to be a guest trying to login.
	if (we::$is_member)
		redirectexit();

	// Are you guessing with a script that doesn't keep the session ID?
	spamProtection('login');

	// Set the login_url if it's not already set (but careful not to send us to an attachment.)
	if (empty($_SESSION['login_url']) && isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && strhas($_SESSION['old_url'], array('board=', 'topic=', 'board,', 'topic,')))
		$_SESSION['login_url'] = $_SESSION['old_url'];

	// Been guessing a lot, haven't we?
	if (isset($_SESSION['failed_login']) && $_SESSION['failed_login'] >= $settings['failed_login_threshold'] * 3)
		fatal_lang_error('login_threshold_fail', 'critical');

	// Set up the cookie length. (If it's invalid, just fall through and use the default.)
	if (isset($_POST['cookieneverexp']) || (!empty($_POST['cookielength']) && $_POST['cookielength'] == -1))
		$settings['cookieTime'] = 3153600;
	elseif (!empty($_POST['cookielength']) && ($_POST['cookielength'] >= 1 || $_POST['cookielength'] <= 525600))
		$settings['cookieTime'] = (int) $_POST['cookielength'];

	// Load the template stuff
	loadLanguage('Login');
	loadTemplate('Login');
	wetem::load('login');

	// Set up the default/fallback stuff.
	$context['default_username'] = isset($_POST['user']) ? westr::htmlspecialchars($_POST['user']) : '';
	$context['default_password'] = '';
	$context['never_expire'] = $settings['cookieTime'] == 525600 || $settings['cookieTime'] == 3153600;
	$context['login_errors'] = array($txt['error_occurred']);
	$context['page_title'] = $txt['login'];

	// Add the login chain to the link tree.
	add_linktree($txt['login'], '<URL>?action=login');

	// You forgot to type your username, dummy!
	if (!isset($_POST['user']) || $_POST['user'] == '')
	{
		$context['login_errors'] = array($txt['need_username']);
		return;
	}

	// Hmm... maybe 'admin' will login with no password. Uhh... NO!
	if ((!isset($_POST['passwrd']) || $_POST['passwrd'] == '') && (!isset($_POST['hash_passwrd']) || strlen($_POST['hash_passwrd']) != 40))
	{
		$context['login_errors'] = array($txt['no_password']);
		return;
	}

	// No funky symbols either.
	if (preg_match('~[<>&"\'=\\\]~', preg_replace('~(&#(\\d{1,7}|x[0-9a-fA-F]{1,6});)~', '', $_POST['user'])) != 0)
	{
		$context['login_errors'] = array($txt['error_invalid_characters_username']);
		return;
	}

	// And if it's too long, trim it back.
	if (westr::strlen($_POST['user']) > 80)
	{
		$_POST['user'] = westr::substr($_POST['user'], 0, 79);
		$context['default_username'] = westr::safe($_POST['user']);
	}


	// Are we using any sort of hook to validate the login?
	if (in_array('retry', call_hook('validate_login', array($_POST['user'], isset($_POST['hash_passwrd']) && strlen($_POST['hash_passwrd']) == 40 ? $_POST['hash_passwrd'] : null, $settings['cookieTime'])), true))
	{
		$context['login_errors'] = array($txt['login_hash_error']);
		$context['disable_login_hashing'] = true;
		return;
	}
	// Only check this if we allow for username logins.
	if (empty($settings['login_type']) || $settings['login_type'] == 1)
	{
		// Load the data up!
		$request = wesql::query('
			SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt, passwd_flood
			FROM {db_prefix}members
			WHERE member_name = {string:user_name}
			LIMIT 1',
			array(
				'user_name' => $_REQUEST['user'],
			)
		);
	}

	// Probably mistyped or their email, try it as an email address. (member_name first, though! Assuming we allow for such things.)
	if ((empty($settings['login_type']) || $settings['login_type'] == 2) && (empty($request) || (wesql::num_rows($request) == 0 && strpos($_POST['user'], '@') !== false)))
	{
		if (!empty($request))
			wesql::free_result($request);

		$request = wesql::query('
			SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt, passwd_flood
			FROM {db_prefix}members
			WHERE email_address = {string:user_name}
			LIMIT 1',
			array(
				'user_name' => $_POST['user'],
			)
		);
	}

	// It didn't match anything, so send them on their way.
	if (empty($request) || wesql::num_rows($request) == 0)
	{
		wesql::free_result($request);
		$context['login_errors'] = array($txt['username_no_exist']);
		return;
	}

	$user_settings = wesql::fetch_assoc($request);
	wesql::free_result($request);

    // password options passed to password_hash()
    $password_hash_options = ['cost' => 10];
    // set to true if you want to force rehash
    $force_rehash = false;
    // is the password fine or not?!
    $password_verify = false;

    // First check if password in db is still an old wedge styled sha1 password
    if(strlen($user_settings['passwd']) == 40) {

        // ok, it's an old one. Now we have to generate an old styled
        // wedge password.
        $sha_passwd = sha1(strtolower($user_settings['member_name']) . un_htmlspecialchars($_POST['passwrd']));

        // check if password is correct
        if($sha_passwd == $user_settings['passwd']) {
            // password is fine
            $password_verify = true;
            // make sure that we rehash later to set update the password in db
            $force_rehash = true;
        }

    } else {
        // Not an old password, so we can just use password_verify
        $password_verify = password_verify($_POST['passwrd'], $user_settings['passwd']);
    }

    if ($password_verify === true) {
        // password seems fine

        // It's possible that we need to rehash because PASSWORD_DEFAULT Alogrithm changed or $force_rehash is true
        if ($force_rehash === true || password_needs_rehash($user_settings['passwd'], PASSWORD_DEFAULT, $password_hash_options) === true) {
            // hash the passed password with the new algorithm
            $password_hash = password_hash($_POST['passwrd'], PASSWORD_DEFAULT, $password_hash_options);

            // make sure hashing worked as it should
            if($password_hash === false || $password_hash == null) {
                log_error('Couldn\'t hash password.');
                $context['login_errors'] = array('Internal error. Sorry. Tell admin.');
                return;
            }

            // and update the database
            $user_settings['passwd'] = $password_hash;
			updateMemberData($user_settings['id_member'], array('passwd' => $user_settings['passwd']));
		}

        // now proceed with login
        DoLogin();
    } else {
        // password is not fine
        validatePasswordFlood($user_settings['id_member'], $user_settings['passwd_flood']);

        $_SESSION['failed_login'] = isset($_SESSION['failed_login']) ? $_SESSION['failed_login'] + 1 : 1;
        if ($_SESSION['failed_login'] >= $settings['failed_login_threshold'])
            redirectexit('action=reminder');
        else
        {
            if (!empty($settings['enableErrorPasswordLogging']))
                log_error($txt['incorrect_password'] . ' - <span class="remove">' . $user_settings['member_name'] . '</span>', 'password');
            $context['disable_login_hashing'] = true;
            $context['login_errors'] = array($txt['incorrect_password']);
            unset($user_settings);
        }
    }
}
