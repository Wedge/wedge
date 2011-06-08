<?php
/**
 * Wedge
 *
 * Handles logging out any previously logged-in member.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
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
	global $user_info, $user_settings, $context, $modSettings;

	// Make sure they aren't being auto-logged out.
	if (!$internal)
		checkSession('get');

	loadSource('Subs-Auth');

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
		wesql::query('
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