<?php
/**
 * Wedge
 *
 * This file deals with activating accounts of newly registered/created users, or reactivating users who have changed their email address.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Handles activation of a newly created user, or an existing but unactivated user.
 *
 * - Requires the Login language and template files.
 * - Expects a user id in $_REQUEST['u'] or a user name or email address in $_POST['user']. (Only one of those is ultimately required)
 * - Queries the members table to ensure the user does exist.
 * - If the user has changed their email address, verify their old password, check the new address isn't banned, that it is potentially valid, if all good, update the members table and leave the function.
 * - If the user account is not yet activated and they're asking for a resend, generate and send the relevant email, and return.
 * - If already active, or the code supplied is wrong, throw an appropriate error.
 * - Otherwise, we're good to go, so: call the activate hook, update the user's record (is now activated, remove the old code), update the member stats, send an email to the admin if they want that, then set up to show the user a thank you/welcome page.
 */

function Activate()
{
	global $context, $txt, $settings, $scripturl, $language;

	loadLanguage('Login');
	loadTemplate('Login');

	if (empty($_REQUEST['u']) && empty($_POST['user']))
	{
		if (empty($settings['registration_method']) || $settings['registration_method'] == 3)
			fatal_lang_error('no_access', false);

		$context['member_id'] = 0;
		wetem::load('resend');
		$context['page_title'] = $txt['invalid_activation_resend'];
		$context['can_activate'] = empty($settings['registration_method']) || $settings['registration_method'] == 1;
		$context['default_username'] = isset($_GET['user']) ? $_GET['user'] : '';

		return;
	}

	// Get the code from the database...
	$request = wesql::query('
		SELECT id_member, validation_code, member_name, real_name, email_address, is_activated, passwd, lngfile
		FROM {db_prefix}members' . (empty($_REQUEST['u']) ? '
		WHERE member_name = {string:email_address} OR email_address = {string:email_address}' : '
		WHERE id_member = {int:id_member}') . '
		LIMIT 1',
		array(
			'id_member' => isset($_REQUEST['u']) ? (int) $_REQUEST['u'] : 0,
			'email_address' => isset($_POST['user']) ? $_POST['user'] : '',
		)
	);

	// Does this user exist at all?
	if (wesql::num_rows($request) == 0)
	{
		wetem::load('retry_activate');
		$context['page_title'] = $txt['invalid_userid'];
		$context['member_id'] = 0;

		return;
	}

	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// Change their email address? (they probably tried a fake one first :P.)
	if (isset($_POST['new_email'], $_REQUEST['passwd']) && sha1(strtolower($row['member_name']) . $_REQUEST['passwd']) == $row['passwd'])
	{
		if (empty($settings['registration_method']) || $settings['registration_method'] == 3)
			fatal_lang_error('no_access', false);

		// !!! Separate the sprintf?
		if (!is_valid_email($_POST['new_email']))
			fatal_lang_error('valid_email_needed', false, array(htmlspecialchars($_POST['new_email'])));

		// Make sure their email isn't banned.
		isBannedEmail($_POST['new_email'], 'cannot_register', $txt['ban_register_prohibited']);

		// Ummm... don't even dare try to take someone else's email!!
		$request = wesql::query('
			SELECT id_member
			FROM {db_prefix}members
			WHERE email_address = {string:email_address}
			LIMIT 1',
			array(
				'email_address' => $_POST['new_email'],
			)
		);
		// !!! Separate the sprintf?
		if (wesql::num_rows($request) != 0)
			fatal_lang_error('email_in_use', false, array(htmlspecialchars($_POST['new_email'])));
		wesql::free_result($request);

		updateMemberData($row['id_member'], array('email_address' => $_POST['new_email']));
		$row['email_address'] = $_POST['new_email'];

		$email_change = true;
	}

	// Resend the password, but only if the account wasn't activated yet.
	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'resend' && ($row['is_activated'] == 0 || $row['is_activated'] == 2) && (!isset($_REQUEST['code']) || $_REQUEST['code'] == ''))
	{
		loadSource('Subs-Post');

		$replacements = array(
			'REALNAME' => $row['real_name'],
			'USERNAME' => $row['member_name'],
			'ACTIVATIONLINK' => $scripturl . '?action=activate;u=' . $row['id_member'] . ';code=' . $row['validation_code'],
			'ACTIVATIONLINKWITHOUTCODE' => $scripturl . '?action=activate;u=' . $row['id_member'],
			'ACTIVATIONCODE' => $row['validation_code'],
			'FORGOTPASSWORDLINK' => $scripturl . '?action=reminder',
		);

		$emaildata = loadEmailTemplate('resend_activate_message', $replacements, empty($row['lngfile']) || empty($settings['userLanguage']) ? $language : $row['lngfile']);

		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);

		$context['page_title'] = $txt['invalid_activation_resend'];

		// This will ensure we don't actually get an error message if it works!
		$context['error_title'] = '';

		fatal_lang_error(!empty($email_change) ? 'change_email_success' : 'resend_email_success', false);
	}

	// Quit if this code is not right.
	if (empty($_REQUEST['code']) || $row['validation_code'] != $_REQUEST['code'])
	{
		if (!empty($row['is_activated']))
			fatal_lang_error('already_activated', false);
		elseif ($row['validation_code'] == '')
		{
			loadLanguage('Profile');
			fatal_lang_error('registration_not_approved', false, array($scripturl . '?action=activate;user=' . $_row['member_name']));
		}

		wetem::load('retry_activate');
		$context['page_title'] = $txt['invalid_activation_code'];
		$context['member_id'] = $row['id_member'];

		return;
	}

	// OK, so at this point in theory we're all good. Except maybe we're not. They've activated their email, but maybe the admin wants to approve it too?
	if (!empty($settings['registration_method']) && $settings['registration_method'] == 4)
	{
		loadTemplate('Register');
		wetem::load('after');
		$context += array(
			'page_title' => $txt['register'],
			'title' => $txt['registration_successful'],
			'description' => $txt['approval_after_registration'],
		);
		updateMemberData($row['id_member'], array('is_activated' => 3, 'validation_code' => ''));
		return;
	}

	// Let the hook know that they've been activated!
	call_hook('activate', array($row['member_name']));

	// Validation complete - update the database!
	updateMemberData($row['id_member'], array('is_activated' => 1, 'validation_code' => ''));

	// Also do a proper member stat re-evaluation.
	updateStats('member', false);

	if (!isset($_POST['new_email']))
	{
		loadSource('Subs-Post');

		adminNotify('activation', $row['id_member'], $row['member_name']);
	}

	wetem::load('login');
	$context += array(
		'page_title' => $txt['registration_successful'],
		'default_username' => $row['member_name'],
		'default_password' => '',
		'never_expire' => false,
		'description' => $txt['activate_success']
	);
}

?>