<?php
/**
 * Wedge
 *
 * This file deals with sending out reminders, and checking the secret answer and question.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	void RemindMe()
		- this is just the controlling delegator.
		- uses the Profile language files and Reminder template.

	void RemindMail()
		// !!!

	void setPassword()
		// !!!

	void setPassword2()
		// !!!

	void SecretAnswerInput()
		// !!!

	void SecretAnswer2()
		// !!!
*/

// Forgot 'yer password?
function RemindMe()
{
	global $txt, $context;

	loadLanguage('Profile');
	loadTemplate('Reminder');

	$context['page_title'] = $txt['authentication_reminder'];
	$context['robot_no_index'] = true;

	// Delegation can be useful sometimes.
	$subActions = array(
		'picktype' => 'RemindPick',
		'secret2' => 'SecretAnswer2',
		'setpassword' =>'setPassword',
		'setpassword2' =>'setPassword2'
	);

	// Any subaction? If none, fall through to the main template, which will ask for one.
	if (isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]))
		$subActions[$_REQUEST['sa']]();
}

// Pick a reminder type.
function RemindPick()
{
	global $context, $txt, $scripturl, $webmaster_email, $language, $settings;

	checkSession();

	// Coming with a known ID?
	if (!empty($_REQUEST['uid']))
	{
		$where = 'id_member = {int:id_member}';
		$where_params['id_member'] = (int) $_REQUEST['uid'];
	}
	elseif (isset($_POST['user']) && $_POST['user'] != '')
	{
		$where = 'member_name = {string:member_name}';
		$where_params['member_name'] = $_POST['user'];
		$where_params['email_address'] = $_POST['user'];
	}

	// You must enter a username/email address.
	if (empty($where))
	{
		loadLanguage('Login');
		fatal_lang_error('username_no_exist', false);
	}

	// Find the user!
	$request = wesql::query('
		SELECT id_member, real_name, member_name, email_address, is_activated, validation_code, lngfile, secret_question
		FROM {db_prefix}members
		WHERE ' . $where . '
		LIMIT 1',
		array_merge($where_params, array(
		))
	);
	// Maybe email?
	if (wesql::num_rows($request) == 0 && empty($_REQUEST['uid']))
	{
		wesql::free_result($request);

		$request = wesql::query('
			SELECT id_member, real_name, member_name, email_address, is_activated, validation_code, lngfile, secret_question
			FROM {db_prefix}members
			WHERE email_address = {string:email_address}
			LIMIT 1',
			array_merge($where_params, array(
			))
		);
		if (wesql::num_rows($request) == 0)
		{
			loadLanguage('Login');
			fatal_lang_error('no_user_with_email', false);
		}
	}

	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// If the user isn't activated/approved, give them some feedback on what to do next.
	if ($row['is_activated'] != 1)
	{
		// Awaiting approval...
		if (trim($row['validation_code']) == '')
			fatal_lang_error('registration_not_approved', false, array($scripturl . '?action=activate;user=' . $_POST['user']));
		else
			fatal_lang_error('registration_not_activated', false, array($scripturl . '?action=activate;user=' . $_POST['user']));
	}

	// You can't get emailed if you have no email address.
	$row['email_address'] = trim($row['email_address']);

	if ($row['email_address'] == '')
		fatal_lang_error('no_reminder_email', 'user', array($webmaster_email));

	// If they have no secret question then they can only get emailed the item, or they are requesting the email, send them an email.
	if (empty($row['secret_question']) || (isset($_POST['reminder_type']) && $_POST['reminder_type'] == 'email'))
	{
		// Randomly generate a new password, with only alpha numeric characters that is a max length of 10 chars.
		loadSource(array('Subs-Members', 'Subs-Post'));
		$password = generateValidationCode();

		$replacements = array(
			'REALNAME' => $row['real_name'],
			'REMINDLINK' => $scripturl . '?action=reminder;sa=setpassword;u=' . $row['id_member'] . ';code=' . $password,
			'IP' => format_ip(we::$user['ip']),
			'USERNAME' => $row['member_name'],
		);

		$emaildata = loadEmailTemplate('forgot_password', $replacements, empty($row['lngfile']) || empty($settings['userLanguage']) ? $language : $row['lngfile']);
		$context['description'] = $txt['reminder_sent'];

		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);

		// Set the password in the database.
		updateMemberData($row['id_member'], array('validation_code' => substr(md5($password), 0, 10)));

		// Set up the template.
		wetem::load('sent');

		// Dont really.
		return;
	}
	// Otherwise are ready to answer the question?
	elseif (isset($_POST['reminder_type']) && $_POST['reminder_type'] == 'secret')
		return SecretAnswerInput();

	// No we're here setup the context for template number 2!
	wetem::load('reminder_pick');
	$context['current_member'] = array(
		'id' => $row['id_member'],
		'name' => $row['member_name'],
	);
}

// Set your new password
function setPassword()
{
	global $txt, $context;

	loadLanguage('Login');

	// You need a code!
	if (!isset($_REQUEST['code']))
		fatal_lang_error('no_access', false);

	// Fill the context array.
	wetem::load('set_password');
	$context += array(
		'page_title' => $txt['reminder_set_password'],
		'code' => $_REQUEST['code'],
		'memID' => (int) $_REQUEST['u']
	);
}

function setPassword2()
{
	global $context, $txt, $settings;

	checkSession();

	if (empty($_POST['u']) || !isset($_POST['passwrd1']) || !isset($_POST['passwrd2']))
		fatal_lang_error('no_access', false);

	$_POST['u'] = (int) $_POST['u'];

	if ($_POST['passwrd1'] != $_POST['passwrd2'])
		fatal_lang_error('passwords_dont_match', false);

	if ($_POST['passwrd1'] == '')
		fatal_lang_error('no_password', false);

	loadLanguage('Login');

	// Get the code as it should be from the database.
	$request = wesql::query('
		SELECT validation_code, member_name, email_address, passwd_flood
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
			AND is_activated = {int:is_activated}
			AND validation_code != {string:blank_string}
		LIMIT 1',
		array(
			'id_member' => $_POST['u'],
			'is_activated' => 1,
			'blank_string' => '',
		)
	);

	// Does this user exist at all?
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('invalid_userid', false);

	list ($realCode, $username, $email, $flood_value) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Is the password actually valid?
	loadSource(array('Subs-Auth', 'Subs-Login'));
	$passwordError = validatePassword($_POST['passwrd1'], $username, array($email));

	// What - it's not?
	if ($passwordError != null)
	{
		loadLanguage('Errors');
		if ($passwordError == 'short')
			$txt['profile_error_password_short'] = sprintf($txt['profile_error_password_short'], empty($settings['password_strength']) ? 4 : 8);

		fatal_lang_error('profile_error_password_' . $passwordError, false);
	}

	// Quit if this code is not right.
	if (empty($_POST['code']) || substr($realCode, 0, 10) !== substr(md5($_POST['code']), 0, 10))
	{
		// Stop brute force attacks like this.
		validatePasswordFlood($_POST['u'], $flood_value, false);

		fatal_lang_error('invalid_activation_code', false);
	}

	// Just in case, flood control.
	validatePasswordFlood($_POST['u'], $flood_value, true);

	// User validated.  Update the database!
	updateMemberData($_POST['u'], array('validation_code' => '', 'passwd' => sha1(strtolower($username) . $_POST['passwrd1'])));

	call_hook('reset_pass', array($username, $username, $_POST['passwrd1']));

	loadTemplate('Login');
	wetem::load('login');
	$context += array(
		'page_title' => $txt['reminder_password_set'],
		'default_username' => $username,
		'default_password' => $_POST['passwrd1'],
		'never_expire' => false,
		'description' => $txt['reminder_password_set']
	);
}

// Get the secret answer.
function SecretAnswerInput()
{
	global $txt, $context;

	checkSession();

	// Strings for the register auto javascript clever stuffy wuffy.
	loadLanguage('Login');

	// Check they entered something...
	if (empty($_REQUEST['uid']))
		fatal_lang_error('username_no_exist', false);

	// Get the stuff....
	$request = wesql::query('
		SELECT id_member, real_name, member_name, secret_question
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
		LIMIT 1',
		array(
			'id_member' => (int) $_REQUEST['uid'],
		)
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('username_no_exist', false);

	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// If there is NO secret question - then throw an error.
	if (trim($row['secret_question']) == '')
		fatal_lang_error('registration_no_secret_question', false);

	// Ask for the answer...
	$context['remind_user'] = $row['id_member'];
	$context['remind_type'] = '';
	$context['secret_question'] = $row['secret_question'];

	wetem::load('ask');
}

function SecretAnswer2()
{
	global $txt, $context, $settings;

	checkSession();
	loadLanguage('Login');

	// Hacker? How did you get this far without an email or username?
	if (empty($_REQUEST['uid']))
		fatal_lang_error('username_no_exist', false);

	// Get the information from the database.
	$request = wesql::query('
		SELECT id_member, real_name, member_name, secret_answer, secret_question, email_address
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
		LIMIT 1',
		array(
			'id_member' => $_REQUEST['uid'],
		)
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('username_no_exist', false);

	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// Check if the secret answer is correct.
	if ($row['secret_question'] == '' || $row['secret_answer'] == '' || md5($_POST['secret_answer']) !== $row['secret_answer'])
	{
		log_error(sprintf($txt['reminder_error'], $row['member_name']), 'user');
		fatal_lang_error('incorrect_answer', false);
	}

	// You can't use a blank one!
	if (strlen(trim($_POST['passwrd1'])) === 0)
		fatal_lang_error('no_password', false);

	// They have to be the same too.
	if ($_POST['passwrd1'] != $_POST['passwrd2'])
		fatal_lang_error('passwords_dont_match', false);

	// Make sure they have a strong enough password.
	loadSource('Subs-Auth');
	$passwordError = validatePassword($_POST['passwrd1'], $row['member_name'], array($row['email_address']));

	// Invalid?
	if ($passwordError != null)
		fatal_lang_error('profile_error_password_' . $passwordError, false);

	// Alright, so long as 'yer sure.
	updateMemberData($row['id_member'], array('passwd' => sha1(strtolower($row['member_name']) . $_POST['passwrd1'])));

	call_hook('reset_pass', array($row['member_name'], $row['member_name'], $_POST['passwrd1']));

	// Tell them it went fine.
	loadTemplate('Login');
	wetem::load('login');
	$context += array(
		'page_title' => $txt['reminder_password_set'],
		'default_username' => $row['member_name'],
		'default_password' => $_POST['passwrd1'],
		'never_expire' => false,
		'description' => $txt['reminder_password_set']
	);
}
