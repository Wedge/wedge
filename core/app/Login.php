<?php
/**
 * Handles displaying the general login form to users.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	void Login()
		- shows a page for the user to type in their username and password.
		- caches the referring URL in $_SESSION['login_url'].
		- uses the Login template and language file with the login sub
		  template.
		- accessed from ?action=login.
*/

function Login()
{
	global $txt, $context;

	// You're not a guest, why are you here?
	if (we::$is_member)
		redirectexit();

	// We need to load the Login template/language file.
	loadLanguage('Login');
	loadTemplate('Login');
	wetem::load('login');

	// Get the template ready.... not really much else to do.
	$context['page_title'] = $txt['login'];
	$context['default_username'] =& $_REQUEST['u'];
	$context['default_password'] = '';
	$context['never_expire'] = false;
	$context['robot_no_index'] = true;

	// Add the login chain to the link tree.
	add_linktree($txt['login'], '<URL>?action=login');

	// Set the login URL - will be used when the login process is done (but careful not to send us to an attachment).
	if (isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && strhas($_SESSION['old_url'], array('board=', 'topic=', 'board,', 'topic,')))
		$_SESSION['login_url'] = $_SESSION['old_url'];
	else
		unset($_SESSION['login_url']);
}
