<?php
/**
 * This file provides the handling for Admin / Members / Registration, which encompasses all the available options to alter registration and its behaviors.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
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
	global $context, $txt;

	// Old templates might still request this.
	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'browse')
		redirectexit('action=admin;area=viewmembers;sa=browse' . (isset($_REQUEST['type']) ? ';type=' . $_REQUEST['type'] : ''));

	$subActions = array(
		'register' => array('AdminRegister', 'moderate_forum'),
		'agreement' => array('EditAgreement', 'admin_forum'),
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
	global $txt, $context;

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
				'href' => '<URL>?action=profile;u=' . $memberID,
				'link' => '<a href="<URL>?action=profile;u=' . $memberID . '">' . $_POST['user'] . '</a>',
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
 * - Uses the language changes system to track the current version of each language's registration agreement.
 */
function EditAgreement()
{
	global $txt, $context, $settings, $cachedir;

	// Get all the agreements.
	getLanguages();

	if (isset($_GET['agreelang'], $context['languages'][$_GET['agreelang']]))
	{
		loadSource('Class-Editor');
		$context['agreelang'] = $_GET['agreelang'];
		loadLanguage('Agreement', $context['agreelang'], false, true);

		$context['postbox'] = new wedit(
			array(
				'id' => 'message',
				'value' => wedit::un_preparsecode($txt['registration_agreement_body']),
				'labels' => array(
					'post_button' => $txt['save'],
				),
				'buttons' => array(
					array(
						'name' => 'post_button',
						'button_text' => $txt['save'],
						'onclick' => 'return submitThisOnce(this);',
						'accesskey' => 's',
					),
				),
				'height' => '250px',
				'width' => '100%',
				'drafts' => 'none',
			)
		);
	}
	elseif (isset($_POST['message'], $_POST['agreelang'], $context['languages'][$_POST['agreelang']]))
	{
		checkSession();
		loadSource('Class-Editor');
		wedit::preparseWYSIWYG('message');
		$_POST['message'] = westr::htmlspecialchars($_POST['message'], ENT_QUOTES);
		wedit::preparsecode($_POST['message']);

		wesql::insert('replace',
			'{db_prefix}language_changes',
			array('id_lang' => 'string', 'lang_file' => 'string', 'lang_var' => 'string', 'lang_key' => 'string', 'lang_string' => 'string', 'serial' => 'int'),
			array($_POST['agreelang'], 'Agreement', 'txt', 'registration_agreement_body', $_POST['message'], 0)
		);

		// And forcibly clean the cache...
		foreach (glob($cachedir . '/lang_*_*_Agreement.php') as $filename)
			@unlink($filename);

		$context['was_saved'] = true;
		updateSettings(array('agreementUpdated' => time()));
	}
	elseif (isset($_POST['updatelang']))
	{
		checkSession();
		updateSettings(array('requireAgreement' => !empty($_POST['requireAgreement'])));
		$context['was_saved'] = true;
	}
	elseif (isset($_POST['setreagree']))
	{
		checkSession();

		// So, did we want to exclude anyone from this fun and games?
		$exclude = array(MID);
		$groups = array();
		if (!empty($_POST['exclude']) && is_array($_POST['exclude']))
			foreach ($_POST['exclude'] as $k => $v)
				$groups[] = (int) $v;
		// Find the members in those groups if applicable. In theory this should be a small list.
		if (!empty($groups))
		{
			$clauses = array(
				'id_group IN ({array_int:groups})',
				'id_post_group IN ({array_int:groups})',
			);
			$vals = array(
				'groups' => $groups,
			);
			foreach ($groups as $k => $v)
			{
				$clauses[] = 'FIND_IN_SET({int:group' . $v . '}, additional_groups)';
				$vals['group' . $v] = $v;
			}
			$request = wesql::query('
				SELECT id_member
				FROM {db_prefix}members
				WHERE ' . implode(' OR ', $clauses),
				$vals
			);
			while ($row = wesql::fetch_row($request))
				$exclude[] = (int) $row[0];
			wesql::free_result($request);
		}

		// In theory we only need to touch people already activated, because everything else should fall through the usual channels.
		wesql::query('
			UPDATE {db_prefix}members
			SET is_activated = is_activated + {int:change}
			WHERE is_activated IN ({array_int:states})
				AND id_member NOT IN ({array_int:exclude})',
			array(
				'change' => 5, // state n1 = activated, state n6 = pending reagree
				'states' => array(1, 11, 21),
				'exclude' => $exclude,
			)
		);

		// Now just a few things
		updateSettings(array(
			'agreement_force' => !empty($_POST['force']),
			'lastResetAgree' => time(),
		));
		$context['was_saved'] = true;
	}

	$context['require_agreement'] = !empty($settings['requireAgreement']);
	$context['last_changed'] = !empty($settings['agreementUpdated']) ? timeformat($settings['agreementUpdated']) : $txt['never'];
	$context['last_reagree'] = !empty($settings['lastResetAgree']) ? timeformat($settings['lastResetAgree']) : $txt['never'];
	$context['agreement_force'] = !empty($settings['agreement_force']) ? 1 : 0;

	$request = wesql::query('
		SELECT mg.id_group, mg.group_name, mg.min_posts
		FROM {db_prefix}membergroups AS mg
		WHERE id_group != {int:moderator_group}
		GROUP BY mg.id_group, mg.min_posts, mg.group_name
		ORDER BY mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'moderator_group' => 3,
			'newbie_group' => 4,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$group_type = $row['min_posts'] == -1 ? 'normal' : 'post';
		$context['groups'][$group_type][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'min_posts' => $row['min_posts'],
			'member_count' => 0,
		);
	}
	wesql::free_result($request);

	wetem::load('edit_agreement');
	$context['page_title'] = $txt['registration_agreement'];
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
	global $txt, $context, $settings, $user_profile;

	// This is really quite wanting.
	loadSource(array('ManageServer', 'Subs-Members'));

	// Get a list of everyone who can approve posts.
	$members_approve = membersAllowedTo('moderate_forum');
	$members_approve = loadMemberData($members_approve, false, 'minimal');
	$members_approve = array_flip($members_approve);
	foreach ($members_approve as $id => $name)
		if (!empty($user_profile[$id]))
			$members_approve[$id] = '<a href="<URL>?action=profile;u=' . $id . '">' . $user_profile[$id]['real_name'] . '</a>';
	asort($members_approve);

	$context['page_title'] = $txt['registration_center'];

	$config_vars = array(
			array('select', 'registration_method', array(0 => $txt['setting_registration_standard'], 1 => $txt['setting_registration_activate'], 2 => $txt['setting_registration_approval'], 4 => $txt['setting_registration_both'], 3=> $txt['setting_registration_disabled'])),
			array('int', 'purge_unactivated_days', 'max' => 30, 'subtext' => $txt['purge_unactivated_days_subtext'], 'postinput' => $txt['purge_unactivated_days_postinput']),
			array('multi_select', 'notify_new_registration', $members_approve),
			array('check', 'send_welcomeEmail'),
			array('check', 'send_validation_onChange'),
		'',
			array('select', 'login_type', array($txt['login_username_or_email'], $txt['login_username_only'], $txt['login_email_only'])),
			array('select', 'password_strength', array($txt['setting_password_strength_low'], $txt['setting_password_strength_medium'], $txt['setting_password_strength_high'])),
			array('int', 'failed_login_threshold'),
			array('check', 'enable_quick_login'),
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

	$context['post_url'] = '<URL>?action=admin;area=regcenter;save;sa=settings';
	$context['settings_title'] = $txt['settings'];

	// Define some javascript for COPPA.
	add_js('
	function checkCoppa()
	{
		var coppaDisabled = $(\'#coppaAge\').val() == 0;
		$(\'#coppaType\').prop(\'disabled\', coppaDisabled);
		$(\'#coppaPost, #coppaFax, #coppaPhone\').prop(\'disabled\', coppaDisabled || $(\'#coppaType\').val() != 1);
	}
	checkCoppa();');

	// Turn the postal address into something suitable for a textbox.
	$settings['coppaPost'] = !empty($settings['coppaPost']) ? preg_replace('~<br\s*/?\>~', "\n", $settings['coppaPost']) : '';

	prepareDBSettingContext($config_vars);
}
