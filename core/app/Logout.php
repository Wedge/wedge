<?php
/**
 * Handles logging out any previously logged-in member.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	void Logout(bool internal = false)
		- logs the current user out of their account.
		- requires that the session hash is sent as well, to prevent automatic
		  logouts by images or javascript.
		- doesn't check the session if internal is true.
		- redirects back to $_SESSION['logout_url'], if it exists.
		- accessed via ?action=logout;session_var=...

*/

// Log the user out.
function Logout($internal = false, $redirect = true)
{
	global $user_settings, $context;

	// Make sure they aren't being auto-logged out.
	if (!$internal)
		checkSession('get');

	loadSource('Subs-Auth');

	if (isset($_SESSION['pack_ftp']))
		$_SESSION['pack_ftp'] = null;

	// It won't be first login anymore.
	unset($_SESSION['first_login']);

	// Just ensure they aren't a guest!
	if (we::$is_member)
	{
		// Pass the logout information to hooks.
		call_hook('logout', array($user_settings['member_name']));

		// If you log out, you aren't online anymore :P.
		wesql::query('
			DELETE FROM {db_prefix}log_online
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => MID,
			)
		);
	}

	$_SESSION['log_time'] = 0;

	// Empty the cookie! (set it in the past, and for id_member = 0)
	setLoginCookie(-3600, 0);

	// And some other housekeeping while we're at it.
	session_destroy();
	if (!empty(we::$id))
		updateMemberData(we::$id, array('password_salt' => substr(md5(mt_rand()), 0, 4)));

	// Off to the merry board index we go!
	if ($redirect)
	{
		if (empty($_SESSION['logout_url']))
			redirectexit('', $context['server']['needs_login_fix']);
		else
		{
			$temp = $_SESSION['logout_url'];
			unset($_SESSION['logout_url']);

			redirectexit($temp, $context['server']['needs_login_fix']);
		}
	}
}
