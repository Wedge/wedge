<?php
/**
 * Wedge
 *
 * This file provides the handling for Admin / Members / Registration, which encompasses all the available options to alter registration and its behaviors.
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
 * This file begins processing for the registration center, all actions via ?action=admin;area=regcenter are routed through this function first.
 *
 * - Each subaction available in the registration center is listed here, including its permission requirements (mostly admin_forum, register new user requires only moderate_forum)
 * - The Login language file and Register templates are loaded here as multiple uses of their resources are made.
 * - Transfers to the appropriate function elsewhere in this file.
 *
 * @todo Remove the handling for sa=browse (which is now contained within action=admin;area=viewmembers as this is only from old templates.
 */
function RegCenter()
{
	global $settings, $context, $txt, $scripturl;

	// Old templates might still request this.
	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'browse')
		redirectexit('action=admin;area=viewmembers;sa=browse' . (isset($_REQUEST['type']) ? ';type=' . $_REQUEST['type'] : ''));

	$subActions = array(
		'register' => array('AdminRegister', 'moderate_forum'),
		'agreement' => array('EditAgreement', 'admin_forum'),
		'reservednames' => array('SetReserve', 'admin_forum'),
		'settings' => array('ModifyRegistrationSettings', 'admin_forum'),
	);

	// Work out which to call...
	$context['sub_action'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('moderate_forum') ? 'register' : 'settings');

	// Must have sufficient permissions.
	isAllowedTo($subActions[$context['sub_action']][1]);

	// Loading, always loading.
	loadLanguage('Login');
	loadLanguage('ManageSettings'); // Login is used outside the admin panel too, no point bulking that up.
	loadTemplate('Register');

	// Next create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['registration_center'],
		'help' => 'registrations',
		'description' => $txt['admin_settings_desc'],
		'tabs' => array(
			'register' => array(
				'description' => $txt['admin_register_desc'],
			),
			'agreement' => array(
				'description' => $txt['registration_agreement_desc'],
			),
			'reservednames' => array(
				'description' => $txt['admin_reserved_desc'],
			),
			'settings' => array(
				'description' => $txt['admin_settings_desc'],
			)
		)
	);

	// Finally, get around to calling the function...
	$subActions[$context['sub_action']][0]();
}

/**
 * Handles manual registration of users by the administrator.
 *
 * - Accessed by ?action=admin;area=regcenter;sa=register
 * - Unlike most of the administration panel, this option merely requires moderate_forum rather than admin_forum permission.
 * - Uses the admin_register block within the Register template.
 * - Also allows the administrator to set the primary group for the user, and whether to email them of the new details, and/or whether to make them confirm receipt of the email notification (as per Email Activation)
 */
function AdminRegister()
{
	global $txt, $context, $scripturl;

	if (!empty($_POST['regSubmit']))
	{
		checkSession();

		foreach ($_POST as $key => $value)
			if (!is_array($_POST[$key]))
				$_POST[$key] = htmltrim__recursive(str_replace(array("\n", "\r"), '', $_POST[$key]));

		$regOptions = array(
			'interface' => 'admin',
			'username' => $_POST['user'],
			'email' => $_POST['email'],
			'password' => $_POST['password'],
			'password_check' => $_POST['password'],
			'check_reserved_name' => true,
			'check_password_strength' => false,
			'check_email_ban' => false,
			'send_welcome_email' => isset($_POST['emailPassword']) || empty($_POST['password']),
			'require' => isset($_POST['emailActivate']) ? 'activation' : 'nothing',
			'memberGroup' => empty($_POST['group']) || !allowedTo('manage_membergroups') ? 0 : (int) $_POST['group'],
		);

		loadSource('Subs-Members');
		$memberID = registerMember($regOptions);
		if (!empty($memberID))
		{
			$context['new_member'] = array(
				'id' => $memberID,
				'name' => $_POST['user'],
				'href' => $scripturl . '?action=profile;u=' . $memberID,
				'link' => '<a href="' . $scripturl . '?action=profile;u=' . $memberID . '">' . $_POST['user'] . '</a>',
			);
			$context['registration_done'] = sprintf($txt['admin_register_done'], $context['new_member']['link']);
		}
	}

	// Basic stuff.
	wetem::load('admin_register');
	$context['page_title'] = $txt['registration_center'];

	// Load the assignable member groups.
	if (allowedTo('manage_membergroups'))
	{
		$request = wesql::query('
			SELECT group_name, id_group
			FROM {db_prefix}membergroups
			WHERE id_group != {int:moderator_group}
				AND min_posts = {int:min_posts}' . (allowedTo('admin_forum') ? '' : '
				AND id_group != {int:admin_group}
				AND group_type != {int:is_protected}') . '
				AND hidden != {int:hidden_group}
			ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
			array(
				'moderator_group' => 3,
				'min_posts' => -1,
				'admin_group' => 1,
				'is_protected' => 1,
				'hidden_group' => 2,
				'newbie_group' => 4,
			)
		);
		$context['member_groups'] = array(0 => $txt['admin_register_group_none']);
		while ($row = wesql::fetch_assoc($request))
			$context['member_groups'][$row['id_group']] = $row['group_name'];
		wesql::free_result($request);
	}
	else
		$context['member_groups'] = array();
}

/**
 * Display and save the registration agreement to be shown to users.
 *
 * - Allows the administrator to disable it, and/or provide editing facilities.
 * - Accessed by ?action=admin;area=regcenter;sa=agreement
 * - Calls upon the edit_agreement block of the Admin template.
 * - Requires the admin_forum permission.
 * - If multiple languages are provided, it will attempt to edit agreement.language.txt in the top level board directory, or failing that, agreement.txt.
 */
function EditAgreement()
{
	global $txt, $boarddir, $context, $settings, $theme;

	// By default we look at agreement.txt.
	$context['current_agreement'] = '';

	// Is there more than one to edit?
	$context['editable_agreements'] = array(
		'' => $txt['admin_agreement_default'],
	);

	// Get our languages.
	getLanguages();

	// Try to figure out if we have more agreements.
	foreach ($context['languages'] as $lang)
	{
		if (file_exists($boarddir . '/agreement.' . $lang['filename'] . '.txt'))
		{
			$context['editable_agreements']['.' . $lang['filename']] = $lang['name'];
			// Are we editing this?
			if (isset($_POST['agree_lang']) && $_POST['agree_lang'] == '.' . $lang['filename'])
				$context['current_agreement'] = '.' . $lang['filename'];
		}
	}

	if (isset($_POST['agreement']))
	{
		checkSession();

		// Off it goes to the agreement file.
		$fp = fopen($boarddir . '/agreement' . $context['current_agreement'] . '.txt', 'w');
		fwrite($fp, str_replace("\r", '', $_POST['agreement']));
		fclose($fp);

		updateSettings(array('requireAgreement' => !empty($_POST['requireAgreement'])));
	}

	$context['agreement'] = file_exists($boarddir . '/agreement' . $context['current_agreement'] . '.txt') ? htmlspecialchars(file_get_contents($boarddir . '/agreement' . $context['current_agreement'] . '.txt')) : '';
	$context['warning'] = is_writable($boarddir . '/agreement' . $context['current_agreement'] . '.txt') ? '' : $txt['agreement_not_writable'];
	$context['require_agreement'] = !empty($settings['requireAgreement']);

	wetem::load('edit_agreement');
	$context['page_title'] = $txt['registration_agreement'];
}

/**
 * Display (and save) the options for usernames that are not allowed within the forum registration (and that users cannot change to later)
 *
 * - Accessed via ?action=admin;area=regcenter;sa=reservednames (within the registration center in the admin panel)
 * - Unlike most of the smaller admin functions, this relies on its own template (reserved_words block of Register template), to manage the large textarea that forms the entry point for reserved names.
 * - Provides access to the options: reserved words to be checked as whole words (as opposed to matching anywhere), matching case too, and whether it is set on username and/or display name.
 * - It should be noted that the method used to display the textarea's contents (i.e. one name per line) is the way it is internally stored too; it is important to note there is no XSS protection here (intentionally)
 * - Requires admin_forum permission.
 */
function SetReserve()
{
	global $txt, $context, $settings;

	// Submitting new reserved words.
	if (!empty($_POST['save_reserved_names']))
	{
		checkSession();

		// Set all the options....
		updateSettings(array(
			'reserveWord' => (isset($_POST['matchword']) ? '1' : '0'),
			'reserveCase' => (isset($_POST['matchcase']) ? '1' : '0'),
			'reserveUser' => (isset($_POST['matchuser']) ? '1' : '0'),
			'reserveName' => (isset($_POST['matchname']) ? '1' : '0'),
			'reserveNames' => str_replace("\r", '', $_POST['reserved'])
		));
	}

	// Get the reserved word options and words.
	$settings['reserveNames'] = str_replace('\n', "\n", $settings['reserveNames']);
	$context['reserved_words'] = explode("\n", $settings['reserveNames']);
	$context['reserved_word_options'] = array();
	$context['reserved_word_options']['match_word'] = $settings['reserveWord'] == '1';
	$context['reserved_word_options']['match_case'] = $settings['reserveCase'] == '1';
	$context['reserved_word_options']['match_user'] = $settings['reserveUser'] == '1';
	$context['reserved_word_options']['match_name'] = $settings['reserveName'] == '1';

	// Ready the template......
	wetem::load('edit_reserved_words');
	$context['page_title'] = $txt['admin_reserved_set'];
}

/**
 * Allows the admin to modify some generation registration settings, such as the method of account creation (instant, member email confirmation, admin approval per account, disabled), sending emails on registration and so on.
 *
 * - COPPA compliance settings are managed here too.
 * - Accessed by ?action=admin;area=regcenter;sa=settings.
 * - Requires admin_forum permission.
 * - Uses the standard settings template.
 * - Adds some JavaScript to the page to hide the COPPA options if COPPA is not in effect.
 */
function ModifyRegistrationSettings($return_config = false)
{
	global $txt, $context, $scripturl, $settings;

	// This is really quite wanting.
	loadSource('ManageServer');

	$config_vars = array(
			array('select', 'registration_method', array($txt['setting_registration_standard'], $txt['setting_registration_activate'], $txt['setting_registration_approval'], $txt['setting_registration_disabled'])),
			array('check', 'notify_new_registration'),
			array('check', 'send_welcomeEmail'),
			array('check', 'send_validation_onChange'),
		'',
			array('select', 'password_strength', array($txt['setting_password_strength_low'], $txt['setting_password_strength_medium'], $txt['setting_password_strength_high'])),
			array('int', 'failed_login_threshold'),
			array('title', 'age_restrictions'),
			array('int', 'coppaAge', 'subtext' => $txt['setting_coppaAge_desc'], 'onchange' => 'checkCoppa();'),
			array('select', 'coppaType', array($txt['setting_coppaType_reject'], $txt['setting_coppaType_approval']), 'onchange' => 'checkCoppa();'),
			array('large_text', 'coppaPost', 'subtext' => $txt['setting_coppaPost_desc']),
			array('text', 'coppaFax'),
			array('text', 'coppaPhone'),
	);

	if ($return_config)
		return $config_vars;

	// Setup the template
	wetem::load('show_settings');
	$context['page_title'] = $txt['registration_center'];

	if (isset($_GET['save']))
	{
		checkSession();

		// Are there some contacts missing?
		if (!empty($_POST['coppaAge']) && !empty($_POST['coppaType']) && empty($_POST['coppaPost']) && empty($_POST['coppaFax']))
			fatal_lang_error('admin_setting_coppa_require_contact');

		// Post needs to take into account line breaks.
		$_POST['coppaPost'] = str_replace("\n", '<br>', empty($_POST['coppaPost']) ? '' : $_POST['coppaPost']);

		saveDBSettings($config_vars);

		redirectexit('action=admin;area=regcenter;sa=settings');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=regcenter;save;sa=settings';
	$context['settings_title'] = $txt['settings'];

	// Define some javascript for COPPA.
	add_js('
	function checkCoppa()
	{
		var coppaDisabled = $(\'#coppaAge\').val() == 0;
		$(\'#coppaType\').attr(\'disabled\', coppaDisabled);
		$(\'#coppaPost, #coppaFax, #coppaPhone\').attr(\'disabled\', coppaDisabled || $(\'#coppaType\').val() != 1);
	}
	checkCoppa();');

	// Turn the postal address into something suitable for a textbox.
	$settings['coppaPost'] = !empty($settings['coppaPost']) ? preg_replace('~<br\s*/?\>~', "\n", $settings['coppaPost']) : '';

	prepareDBSettingContext($config_vars);
}

?>