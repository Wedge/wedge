<?php
/**
 * Wedge
 *
 * Handles displaying the general login form to users.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	void Login()
		- shows a page for the user to type in their username and password.
		- caches the referring URL in $_SESSION['login_url'].
		- uses the Login template and language file with the login sub
		  template.
		- if you are using a wireless device, uses the protocol_login sub
		  template in the Wireless template.
		- accessed from ?action=login.
*/

function Login()
{
	global $txt, $context, $scripturl;

	// You're not a guest, why are you here?
	if (!$context['user']['is_guest'])
		redirectexit();

	// In wireless?  If so, use the correct block.
	if (WIRELESS)
		wetem::load('wap2_login');
	// Otherwise, we need to load the Login template/language file.
	else
	{
		loadLanguage('Login');
		loadTemplate('Login');
		wetem::load('login');
	}

	// Get the template ready.... not really much else to do.
	$context['page_title'] = $txt['login'];
	$context['default_username'] =& $_REQUEST['u'];
	$context['default_password'] = '';
	$context['never_expire'] = false;

	// Add the login chain to the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=login',
		'name' => $txt['login'],
	);

	// Set the login URL - will be used when the login process is done (but careful not to send us to an attachment).
	if (isset($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'dlattach') === false && preg_match('~(board|topic)[=,]~', $_SESSION['old_url']) != 0)
		$_SESSION['login_url'] = $_SESSION['old_url'];
	else
		unset($_SESSION['login_url']);
}

?>