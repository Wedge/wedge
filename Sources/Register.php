<?php
/**
 * Wedge
 *
 * Registers new members directly, validates existing usernames, and similar processes.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	void Register()
		// !!!

	void Register2()
		// !!!

	void RegisterCheckUsername()
		// !!!
*/

// Begin the registration process.
function Register($reg_errors = array())
{
	global $txt, $boarddir, $context, $theme, $settings, $user_info;
	global $language, $scripturl, $cur_profile;

	// Is this an incoming AJAX check?
	if (isset($_GET['sa']) && $_GET['sa'] == 'usernamecheck')
		return RegisterCheckUsername();

	// Check if the administrator has it disabled.
	if (!empty($settings['registration_method']) && $settings['registration_method'] == 3)
		fatal_lang_error('registration_disabled', false);

	// If this user is an admin - redirect them to the admin registration page.
	if (allowedTo('moderate_forum') && !$user_info['is_guest'])
		redirectexit('action=admin;area=regcenter;sa=register');
	// You are not a guest, so you are a member - and members don't get to register twice!
	elseif (empty($user_info['is_guest']))
		redirectexit();

	loadLanguage('Login');
	loadTemplate('Register');

	// Do we need them to agree to the registration agreement, first?
	$context['require_agreement'] = !empty($settings['requireAgreement']);
	$context['registration_passed_agreement'] = !empty($_SESSION['registration_agreed']);
	$context['show_coppa'] = !empty($settings['coppaAge']);

	// Under age restrictions?
	if ($context['show_coppa'])
	{
		$context['skip_coppa'] = false;
		$context['coppa_agree_above'] = sprintf($txt[($context['require_agreement'] ? 'agreement_agree' : 'noagreement') . '_coppa_above'], $settings['coppaAge']);
		$context['coppa_agree_below'] = sprintf($txt[($context['require_agreement'] ? 'agreement_agree' : 'noagreement') . '_coppa_below'], $settings['coppaAge']);
	}

	// What step are we at?
	$current_step = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : ($context['require_agreement'] ? 1 : 2);

	// Does this user agree to the registation agreement?
	if ($current_step == 1 && (isset($_POST['accept_agreement']) || isset($_POST['accept_agreement_coppa'])))
	{
		$context['registration_passed_agreement'] = $_SESSION['registration_agreed'] = true;
		$current_step = 2;

		// Skip the coppa procedure if the user says he's old enough.
		if ($context['show_coppa'])
		{
			$_SESSION['skip_coppa'] = !empty($_POST['accept_agreement']);

			// Are they saying they're under age, while under age registration is disabled?
			if (empty($settings['coppaType']) && empty($_SESSION['skip_coppa']))
				fatal_lang_error('under_age_registration_prohibited', false, array($settings['coppaAge']));
		}
	}
	// Make sure they don't squeeze through without agreeing.
	elseif ($current_step > 1 && $context['require_agreement'] && !$context['registration_passed_agreement'])
		$current_step = 1;

	// Show the user the right form.
	wetem::load($current_step == 1 ? 'registration_agreement' : 'registration_form');
	$context['page_title'] = $current_step == 1 ? $txt['registration_agreement'] : $txt['registration_form'];

	$context['current_forum_time_js'] = time() + $settings['time_offset'] * 3600;

	// Add the register chain to the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=register',
		'name' => $txt['register'],
	);

	// If you have to agree to the agreement, it needs to be fetched from the file.
	if ($context['require_agreement'])
	{
		// Have we got a localized one?
		if (file_exists($boarddir . '/agreement.' . $user_info['language'] . '.txt'))
			$context['agreement'] = parse_bbc(file_get_contents($boarddir . '/agreement.' . $user_info['language'] . '.txt'), true, 'agreement_' . $user_info['language']);
		elseif (file_exists($boarddir . '/agreement.txt'))
			$context['agreement'] = parse_bbc(file_get_contents($boarddir . '/agreement.txt'), true, 'agreement');
		else
			$context['agreement'] = '';
	}

	// Prepare the time gate! Do it like so, in case later steps want to reset the limit for any reason, but make sure the time is the current one.
	if (!isset($_SESSION['register']))
		$_SESSION['register'] = array(
			'timenow' => time(),
			'limit' => 10, // minimum number of seconds required on this page for registration
		);
	else
		$_SESSION['register']['timenow'] = time();

	if (!empty($settings['userLanguage']))
	{
		$selectedLanguage = empty($_SESSION['language']) ? $language : $_SESSION['language'];
		getLanguages();

		// Try to find our selected language.
		foreach ($context['languages'] as $key => $lang)
		{
			$context['languages'][$key]['name'] = strtr($lang['name'], array('-utf8' => ''));

			// Found it!
			if ($selectedLanguage == $lang['filename'])
				$context['languages'][$key]['selected'] = true;
		}
	}

	// Any custom fields we want filled in?
	loadSource('Profile');
	loadCustomFields(0, 'register');

	// Or any standard ones?
	if (!empty($settings['registration_fields']))
	{
		loadSource('Profile-Modify');

		// Setup some important context.
		loadLanguage('Profile');
		loadTemplate('Profile');

		$context['user']['is_owner'] = true;

		// Here, and here only, emulate the permissions the user would have to do this.
		$user_info['permissions'] = array_merge($user_info['permissions'], array('profile_account_own', 'profile_extra_own'));
		$reg_fields = explode(',', $settings['registration_fields']);

		// We might have had some submissions on this front - go check.
		foreach ($reg_fields as $field)
			if (isset($_POST[$field]))
				$cur_profile[$field] = westr::htmlspecialchars($_POST[$field]);

		// Load all the fields in question.
		setupProfileContext($reg_fields);
	}

	// Generate a visual verification code to make sure the user is no bot.
	if (!empty($settings['reg_verification']))
	{
		loadSource('Subs-Editor');
		$verificationOptions = array(
			'id' => 'register',
		);
		$context['visual_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}
	// Otherwise we have nothing to show.
	else
		$context['visual_verification'] = false;

	$context += array(
		'username' => isset($_POST['user']) ? westr::htmlspecialchars($_POST['user']) : '',
		'email' => isset($_POST['email']) ? westr::htmlspecialchars($_POST['email']) : '',
	);

	// !!! Why isn't this a simple set operation?
	// Were there any errors?
	$context['registration_errors'] = array();
	if (!empty($reg_errors))
		foreach ($reg_errors as $error)
			$context['registration_errors'][] = $error;
}

// Actually register the member.
function Register2()
{
	global $scripturl, $txt, $settings, $context;
	global $user_info, $options, $theme;

	// Start collecting together any errors.
	$reg_errors = array();

	// You can't register if it's disabled.
	if (!empty($settings['registration_method']) && $settings['registration_method'] == 3)
		fatal_lang_error('registration_disabled', false);

	// Well, if you don't agree, you can't register.
	if (!empty($settings['requireAgreement']) && empty($_SESSION['registration_agreed']))
		redirectexit();

	// Make sure they came from *somewhere*, have a session.
	if (!isset($_SESSION['old_url']))
		redirectexit('action=register');

	// If we don't require an agreement, we need a extra check for coppa.
	if (empty($settings['requireAgreement']) && !empty($settings['coppaAge']))
		$_SESSION['skip_coppa'] = !empty($_POST['accept_agreement']);

	// Are they under age, and under age users are banned?
	if (!empty($settings['coppaAge']) && empty($settings['coppaType']) && empty($_SESSION['skip_coppa']))
		fatal_lang_error('under_age_registration_prohibited', false, array($settings['coppaAge']));

	// Check whether the visual verification code was entered correctly.
	if (!empty($settings['reg_verification']))
	{
		loadSource('Subs-Editor');
		$verificationOptions = array(
			'id' => 'register',
		);
		$context['visual_verification'] = create_control_verification($verificationOptions, true);

		if (is_array($context['visual_verification']))
		{
			loadLanguage('Errors');
			foreach ($context['visual_verification'] as $error)
				$reg_errors[] = $txt['error_' . $error];
		}
	}

	// Check the time gate for miscreants. First make sure they came from somewhere that actually set it up.
	if (empty($_SESSION['register']['timenow']) || empty($_SESSION['register']['limit']))
		redirectexit('action=register');
	// Failing that, check the time on it.
	if (time() - $_SESSION['register']['timenow'] < $_SESSION['register']['limit'])
	{
		loadLanguage('Errors');
		$reg_errors[] = $txt['error_too_quickly'];
	}

	foreach ($_POST as $key => $value)
	{
		if (!is_array($_POST[$key]))
			$_POST[$key] = htmltrim__recursive(str_replace(array("\n", "\r"), '', $_POST[$key]));
	}

	// Collect all extra registration fields someone might have filled in.
	$possible_strings = array(
		'website_url', 'website_title',
		'location', 'birthdate',
		'buddy_list', 'pm_ignore_list',
		'signature', 'personal_text', 'avatar',
		'secret_question', 'secret_answer',
		'time_format',
		'smiley_set',
		'lngfile',
		'data',
	);
	$possible_ints = array(
		'pm_email_notify',
		'notify_types',
		'gender',
		'id_theme',
	);
	$possible_floats = array(
		'time_offset',
	);
	$possible_bools = array(
		'notify_announcements', 'notify_regularity', 'notify_send_body',
		'hide_email', 'show_online',
	);

	if (isset($_POST['secret_answer']) && $_POST['secret_answer'] != '')
		$_POST['secret_answer'] = md5($_POST['secret_answer']);

	// Needed for isReservedName() and registerMember().
	loadSource('Subs-Members');

	// Validation... even if we're not a mall.
	if (isset($_POST['real_name']) && (!empty($settings['allow_editDisplayName']) || allowedTo('moderate_forum')))
	{
		$_POST['real_name'] = trim(preg_replace('~[\s]~u', ' ', $_POST['real_name']));
		if (trim($_POST['real_name']) != '' && !isReservedName($_POST['real_name']) && westr::strlen($_POST['real_name']) < 60)
			$possible_strings[] = 'real_name';
	}

	// Handle a string as a birthdate...
	if (isset($_POST['birthdate']) && $_POST['birthdate'] != '')
		$_POST['birthdate'] = strftime('%Y-%m-%d', strtotime($_POST['birthdate']));
	// Or birthdate parts...
	elseif (!empty($_POST['bday1']) && !empty($_POST['bday2']))
		$_POST['birthdate'] = sprintf('%04d-%02d-%02d', empty($_POST['bday3']) ? 0 : (int) $_POST['bday3'], (int) $_POST['bday1'], (int) $_POST['bday2']);

	// By default assume email is hidden, only show it if we tell it to.
	$_POST['hide_email'] = !empty($_POST['allow_email']) ? 0 : 1;

	// Validate the passed language file.
	if (isset($_POST['lngfile']) && !empty($settings['userLanguage']))
	{
		getLanguages();

		// Did we find it?
		if (isset($context['languages'][$_POST['lngfile']]))
			$_SESSION['language'] = $_POST['lngfile'];
		else
			unset($_POST['lngfile']);
	}
	else
		unset($_POST['lngfile']);

	// Set the options needed for registration.
	$regOptions = array(
		'interface' => 'guest',
		'username' => !empty($_POST['user']) ? $_POST['user'] : '',
		'email' => !empty($_POST['email']) ? $_POST['email'] : '',
		'password' => !empty($_POST['passwrd1']) ? $_POST['passwrd1'] : '',
		'password_check' => !empty($_POST['passwrd2']) ? $_POST['passwrd2'] : '',
		'check_reserved_name' => true,
		'check_password_strength' => true,
		'check_email_ban' => true,
		'send_welcome_email' => !empty($settings['send_welcomeEmail']),
		'require' => !empty($settings['coppaAge']) && empty($_SESSION['skip_coppa']) ? 'coppa' : (empty($settings['registration_method']) ? 'nothing' : ($settings['registration_method'] == 1 ? 'activation' : 'approval')),
		'extra_register_vars' => array(),
		'theme_vars' => array(),
	);

	// Include the additional options that might have been filled in.
	foreach ($possible_strings as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = westr::htmlspecialchars($_POST[$var], ENT_QUOTES);
	foreach ($possible_ints as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = (int) $_POST[$var];
	foreach ($possible_floats as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = (float) $_POST[$var];
	foreach ($possible_bools as $var)
		if (isset($_POST[$var]))
			$regOptions['extra_register_vars'][$var] = empty($_POST[$var]) ? 0 : 1;

	// Registration options are always default options...
	if (isset($_POST['default_options']))
		$_POST['options'] = isset($_POST['options']) ? $_POST['options'] + $_POST['default_options'] : $_POST['default_options'];
	$regOptions['theme_vars'] = isset($_POST['options']) && is_array($_POST['options']) ? $_POST['options'] : array();

	// Make sure they are clean, dammit!
	$regOptions['theme_vars'] = htmlspecialchars__recursive($regOptions['theme_vars']);

	// If Quick Reply hasn't been set then set it to be shown but collapsed.
	if (!isset($regOptions['theme_vars']['display_quick_reply']))
		$regOptions['theme_vars']['display_quick_reply'] = 1;

	// Check whether we have fields that simply MUST be displayed?
	$request = wesql::query('
		SELECT col_name, field_name, field_type, field_length, mask, show_reg
		FROM {db_prefix}custom_fields
		WHERE active = {int:is_active}',
		array(
			'is_active' => 1,
		)
	);
	$custom_field_errors = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Don't allow overriding of the theme variables.
		if (isset($regOptions['theme_vars'][$row['col_name']]))
			unset($regOptions['theme_vars'][$row['col_name']]);

		// Not actually showing it then?
		if (!$row['show_reg'])
			continue;

		// Prepare the value!
		$value = isset($_POST['customfield'][$row['col_name']]) ? trim($_POST['customfield'][$row['col_name']]) : '';

		// We only care for text fields as the others are valid to be empty.
		if (!in_array($row['field_type'], array('check', 'select', 'radio')))
		{
			// Is it too long?
			if ($row['field_length'] && $row['field_length'] < westr::strlen($value))
				$custom_field_errors[] = array('custom_field_too_long', array($row['field_name'], $row['field_length']));

			// Any masks to apply?
			if ($row['field_type'] == 'text' && !empty($row['mask']) && $row['mask'] != 'none')
			{
				//!!! We never error on this - just ignore it at the moment...
				if ($row['mask'] == 'email' && (!is_valid_email($value) || strlen($value) > 255))
					$custom_field_errors[] = array('custom_field_invalid_email', array($row['field_name']));
				elseif ($row['mask'] == 'number' && preg_match('~[^\d]~', $value))
					$custom_field_errors[] = array('custom_field_not_number', array($row['field_name']));
				elseif (substr($row['mask'], 0, 5) == 'regex' && preg_match(substr($row['mask'], 5), $value) === 0)
					$custom_field_errors[] = array('custom_field_inproper_format', array($row['field_name']));
			}
		}

		// Is this required but not there?
		if (trim($value) == '' && $row['show_reg'] > 1)
			$custom_field_errors[] = array('custom_field_empty', array($row['field_name']));
	}
	wesql::free_result($request);

	// Process any errors.
	if (!empty($custom_field_errors))
	{
		loadLanguage('Errors');
		foreach ($custom_field_errors as $error)
			$reg_errors[] = vsprintf($txt['error_' . $error[0]], $error[1]);
	}

	// Let's check for other errors before trying to register the member.
	if (!empty($reg_errors))
	{
		$_REQUEST['step'] = 2;
		$_SESSION['register']['limit'] = 5; // If they've filled in some details, they won't need the full 10 seconds of the limit.
		return Register($reg_errors);
	}

	$memberID = registerMember($regOptions, true);

	// What there actually an error of some kind dear boy?
	if (is_array($memberID))
	{
		$reg_errors = array_merge($reg_errors, $memberID);
		$_REQUEST['step'] = 2;
		$_SESSION['register']['limit'] = 5; // If they've filled in some details, they won't need the full 10 seconds of the limit.
		return Register($reg_errors);
	}

	// Do our spam protection now.
	spamProtection('register');

	// We'll do custom fields after as then we get to use the helper function!
	if (!empty($_POST['customfield']))
	{
		loadSource(array('Profile', 'Profile-Modify'));
		makeCustomFieldChanges($memberID, 'register');
	}

	// If COPPA has been selected then things get complicated, setup the template.
	if (!empty($settings['coppaAge']) && empty($_SESSION['skip_coppa']))
		redirectexit('action=coppa;member=' . $memberID);
	// Basic template variable setup.
	elseif (!empty($settings['registration_method']))
	{
		unset($_SESSION['register']); // Don't need the time gate now.
		loadTemplate('Register');
		wetem::load('after');
		$context += array(
			'page_title' => $txt['register'],
			'title' => $txt['registration_successful'],
			'description' => $settings['registration_method'] == 2 ? $txt['approval_after_registration'] : $txt['activate_after_registration']
		);
	}
	else
	{
		call_hook('activate', array($regOptions['username']));

		setLoginCookie(60 * $settings['cookieTime'], $memberID, sha1(sha1(strtolower($regOptions['username']) . $regOptions['password']) . $regOptions['register_vars']['password_salt']));

		redirectexit('action=login2;sa=check;member=' . $memberID, $context['server']['needs_login_fix']);
	}
}

// See if a username already exists.
function RegisterCheckUsername()
{
	global $context, $txt;

	// This is XML!
	loadTemplate('Xml');
	wetem::load('check_username');
	$context['checked_username'] = isset($_GET['username']) ? $_GET['username'] : '';
	$context['valid_username'] = true;

	// Clean it up like mother would.
	$context['checked_username'] = preg_replace('~[\t\n\r\x0B\0\x{A0}]+~u', ' ', $context['checked_username']);
	if (westr::strlen($context['checked_username']) > 25)
		$context['checked_username'] = westr::htmltrim(westr::substr($context['checked_username'], 0, 25));

	// Only these characters are permitted.
	if (preg_match('~[<>&"\'=\\\]~', preg_replace('~&#(?:\\d{1,7}|x[0-9a-fA-F]{1,6});~', '', $context['checked_username'])) != 0 || $context['checked_username'] == '_' || $context['checked_username'] == '|' || stripos($context['checked_username'], '[code') !== false || stripos($context['checked_username'], '[/code') !== false)
		$context['valid_username'] = false;

	if (stristr($context['checked_username'], $txt['guest_title']) !== false)
		$context['valid_username'] = false;

	if (trim($context['checked_username']) == '')
		$context['valid_username'] = false;
	else
	{
		loadSource('Subs-Members');
		$context['valid_username'] &= isReservedName($context['checked_username'], 0, false, false) ? 0 : 1;
	}
}

?>