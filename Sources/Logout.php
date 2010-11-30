<?php
/**********************************************************************************
* Logout.php                                                                      *
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

	void Logout(bool internal = false)
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
	global $sourcedir, $user_info, $user_settings, $context, $modSettings, $smcFunc;

	// Make sure they aren't being auto-logged out.
	if (!$internal)
		checkSession('get');

	require_once($sourcedir . '/Subs-Auth.php');

	if (isset($_SESSION['pack_ftp']))
		$_SESSION['pack_ftp'] = null;

	// They cannot be open ID verified any longer.
	if (isset($_SESSION['openid']))
		unset($_SESSION['openid']);

	// It won't be first login anymore.
	unset($_SESSION['first_login']);

	// Just ensure they aren't a guest!
	if (!$user_info['is_guest'])
	{
		// Pass the logout information to hooks.
		call_hook('logout', array($user_settings['member_name']));

		// If you log out, you aren't online anymore :P.
		$smcFunc['db_query']('', '
			DELETE FROM {db_prefix}log_online
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
			)
		);
	}

	$_SESSION['log_time'] = 0;

	// Empty the cookie! (set it in the past, and for id_member = 0)
	setLoginCookie(-3600, 0);

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

?>