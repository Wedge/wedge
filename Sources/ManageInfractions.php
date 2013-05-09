<?php
/**
 * Wedge
 *
 * All functionality related to managing members receiving warnings.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function ManageInfractions()
{
	global $context, $txt;

	isAllowedTo('admin_forum');

	loadTemplate('ManageInfractions');
	loadLanguage('ManageInfractions');

	$subactions = array(
		'infractions' => 'InfractionsHome',
		'infractionlevels' => 'InfractionLevels',
		'settings' => 'ModifyInfractionSettings',
	);

	$context['sub_action'] = isset($_REQUEST['sa'], $subactions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'infractions';

	$context['page_title'] = $txt['infractions_title'];

	// Tabs for browsing the different ban functions.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['infractions_title'],
		'description' => $txt['infractions_desc'],
		'tabs' => array(
			'infractions' => array(
				'description' => $txt['infractions_desc'],
			),
			'infractionlevels' => array(
				'description' => $txt['infractionlevels_desc'],
			),
			'settings' => array(
				'description' => $txt['infractionsettings_desc'],
			),
		),
	);

	$subactions[$context['sub_action']]();
}

function InfractionsHome()
{
	global $txt, $context, $settings;

	getIssuerGroups();
	getInfractionLevels();

	if (isset($_GET['edit']))
		return EditInfractions();

	$context['infractions'] = array();

	// Let's get all the pre-set stuff.
	$request = wesql::query('
		SELECT id_infraction, infraction_name, points, sanctions, duration, issuing_groups
		FROM {db_prefix}infractions
		ORDER BY id_infraction');
	while ($row = wesql::fetch_assoc($request))
	{
		$groups = !empty($row['issuing_groups']) ? explode(',', $row['issuing_groups']) : array();
		if (!in_array(1, $groups))
			array_unshift($groups, 1);

		$row['issuing_groups'] = array();
		foreach ($groups as $group)
			if (!empty($context['group_list'][$group]))
				$row['issuing_groups'][] = '<span class="group' . $group . '">' . $context['group_list'][$group] . '</span>';

		if ($row['duration'] == 'i')
			$row['duration'] = $txt['infraction_i'];
		else
		{
			$unit = substr($row['duration'], -1);
			$number = (int) substr($row['duration'], 0, -1);
			$row['duration'] = number_context('infraction_' . $unit, $number);
		}

		$row['sanctions'] = explode(',', $row['sanctions']);
		foreach ($row['sanctions'] as $k => $v)
			if (isset($txt['infraction_' . $v]))
				$row['sanctions'][$k] = $txt['infraction_' . $v];
			else
				unset ($row['sanctions'][$k]);

		$context['infractions'][$row['id_infraction']] = $row;
	}

	// Now for rules around adhoc ones.
	$context['infractions_adhoc'] = array();
	foreach ($context['issuer_groups'] as $group)
		$context['infractions_adhoc'][$group] = array(
			'allowed' => false,
			'points' => 5,
			'sanctions' => array(),
			'per_day' => 1,
		);
	if (!empty($settings['infraction_adhoc']))
	{
		// We deliberately don't just grab the list because it's possible there are old groups and stuff here.
		$data = unserialize($settings['infraction_adhoc']);
		foreach ($data as $group => $rules)
			if (isset($context['infractions_adhoc'][$group]))
				$context['infractions_adhoc'][$group] = $rules;
	}

	if (isset($_GET['save']))
	{
		$possible_sanctions = array_keys($context['infraction_levels']);

		foreach (array('adhoc', 'per_day', 'points', 'sanctions') as $item)
			$_POST[$item] = isset($_POST[$item]) ? (array) $_POST[$item] : array();

		foreach ($context['issuer_groups'] as $group)
		{
			$context['infractions_adhoc'][$group]['allowed'] = isset($_POST['adhoc'], $_POST['adhoc'][$group]);
			if (isset($_POST['points'][$group]))
				$context['infractions_adhoc'][$group]['points'] = min(max((int) $_POST['points'][$group], 0), 1000);
			if (isset($_POST['per_day'][$group]))
				$context['infractions_adhoc'][$group]['per_day'] = min(max((int) $_POST['per_day'][$group], 0), 100);

			$sanctions = array();
			if (isset($_POST['sanctions'][$group]) && is_array($_POST['sanctions'][$group]))
				$sanctions = array_intersect($_POST['sanctions'][$group], $possible_sanctions);

			$context['infractions_adhoc'][$group]['sanctions'] = $sanctions;
		}

		// We want to save but to save space we don't need to note that group 1 is magic. Admins are all powerful, always.
		$temp = $context['infractions_adhoc'];
		unset ($temp[1]);
		updateSettings(array('infraction_adhoc' => serialize($temp)));
	}

	wetem::load('infractions');
}

function EditInfractions()
{
	global $txt, $context, $settings;

	getLanguages();

	// Start with some general stuff.
	$edit_id = empty($_GET['edit']) ? 0 : (int) $_GET['edit'];
	$context['infraction_details'] = array(
		'infraction_name' => '',
		'infraction_msg' => '',
		'template' => '',
		'points' => 0,
		'sanctions' => array(),
		'duration' => '1w',
		'issuing_groups' => array(),
	);
	$context['page_title'] = $txt['add_preset_infraction'];

	// Are we editing one we already had?
	if ($edit_id != 0)
	{
		// If we're deleting, do it and scoot.
		if (isset($_POST['delete']))
		{
			wesql::query('
				DELETE FROM {db_prefix}infractions
				WHERE id_infraction = {int:infraction}',
				array(
					'infraction' => $edit_id,
				)
			);
			redirectexit('action=admin;area=infractions;sa=infractions');
		}

		$request = wesql::query('
			SELECT infraction_name, infraction_msg, points, sanctions, duration, issuing_groups
			FROM {db_prefix}infractions
			WHERE id_infraction = {int:infraction}',
			array(
				'infraction' => $edit_id,
			)
		);
		if (wesql::num_rows($request) == 1)
		{
			$context['infraction_details'] = wesql::fetch_assoc($request);
			$context['infraction_details']['sanctions'] = explode(',', $context['infraction_details']['sanctions']);
			$context['infraction_details']['issuing_groups'] = explode(',', $context['infraction_details']['issuing_groups']);

			$context['page_title'] = $txt['edit_preset_infraction'];
		}
		else
			$edit_id = 0;
		wesql::free_result($request);

		if (!empty($context['infraction_details']['infraction_msg']))
		{
			if (substr($context['infraction_details']['infraction_msg'], 0, 8) == 'template')
				$context['infraction_details']['template'] = substr($context['infraction_details']['infraction_msg'], 9);
			else
			{
				$context['infraction_details']['template'] = 'custom';
				$context['infraction_details']['template_msg'] = unserialize($context['infraction_details']['infraction_msg']);
			}
		}
		else
			$context['infraction_details']['template'] = '';
	}

	// Now some clean-up ready for the template.
	$context['infraction_details']['id'] = $edit_id;
	$context['infraction_details']['infraction_name'] = westr::safe($context['infraction_details']['infraction_name'], ENT_QUOTES);
	if ($context['infraction_details']['duration'] == 'i')
	{
		$context['infraction_details']['duration'] = array(
			'unit' => 'i',
			'number' => 0,
		);
	}
	else
	{
		$unit = substr($context['infraction_details']['duration'], -1);
		$number = (int) substr($context['infraction_details']['duration'], 0, -1);
		$context['infraction_details']['duration'] = array(
			'unit' => $unit,
			'number' => $number,
		);
	}

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Getting the name
		$_POST['infraction_name'] = isset($_POST['infraction_name']) ? trim($_POST['infraction_name']) : '';
		$context['infraction_details']['infraction_name'] = !empty($_POST['infraction_name']) ? westr::safe($_POST['infraction_name'], ENT_QUOTES) : '';
		if (empty($context['infraction_details']['infraction_name']))
			$context['errors'][] = 'error_no_name_given';

		// Points
		$context['infraction_details']['points'] = !empty($_POST['infraction_points']) ? min(max($_POST['infraction_points'], 0), 1000) : 0;

		// Duration
		$context['infraction_details']['duration'] = array(
			'unit' => !empty($_POST['infraction_duration_unit']) && isset($txt['infraction_duration_types'][$_POST['infraction_duration_unit']]) ? $_POST['infraction_duration_unit'] : 'i',
			'number' => !empty($_POST['infraction_duration_number']) ? min(max($_POST['infraction_duration_number'], 0), 50) : 0,
		);
		if ($context['infraction_details']['duration']['unit'] != 'i' && $context['infraction_details']['duration']['number'] == 0)
			$context['errors'][] = 'error_invalid_duration';

		// Notifications
		if (empty($_POST['infraction_notification']))
			$context['infraction_details']['template'] = '';
		elseif ($_POST['infraction_notification'] == 'custom')
		{
			$context['infraction_details']['template'] = 'custom';
			$context['infraction_details']['template_msg'] = array();
			$_POST['subject'] = isset($_POST['subject']) ? (array) $_POST['subject'] : array();
			$_POST['body'] = isset($_POST['body']) ? (array) $_POST['body'] : array();
			foreach ($context['languages'] as $id_lang => $lang)
			{
				if (!empty($_POST['subject'][$id_lang]) && trim($_POST['subject'][$id_lang]) !== '')
					$context['infraction_details']['template_msg'][$id_lang]['subject'] = westr::safe($_POST['subject'][$id_lang], ENT_QUOTES);
				if (!empty($_POST['body'][$id_lang]) && trim($_POST['body'][$id_lang]) !== '')
					$context['infraction_details']['template_msg'][$id_lang]['body'] = westr::safe($_POST['body'][$id_lang], ENT_QUOTES);
			}

			if (empty($context['infraction_details']['template_msg']))
				$context['errors'][] = 'error_no_text';
			else
			{
				$temp = $context['infraction_details']['template_msg'];
				foreach ($temp as $lang => $content)
				{
					if (count($content) != 2)
						$context['errors']['error_no_text'] = 'error_no_text';
				}
			}
		}
		elseif (isset($txt['tpl_infraction_' . $_POST['infraction_notification']]))
			$context['infraction_details']['template'] = $_POST['infraction_notification'];

		// Sanctions
		$_POST['sanction'] = isset($_POST['sanction']) ? (array) $_POST['sanction'] : array();
		foreach ($context['infraction_levels'] as $infraction => $dummy)
			if (!empty($_POST['sanction'][$infraction]))
				$context['infraction_details']['sanctions'][] = $infraction;
		$context['infraction_details']['sanctions'] = array_unique($context['infraction_details']['sanctions']);

		// Issuing groups
		$_POST['group'] = isset($_POST['group']) ? (array) $_POST['group'] : array();
		$context['infraction_details']['issuing_groups'] = array_diff(array_intersect($_POST['group'], $context['issuer_groups']), array(1));

		if (empty($context['errors']))
		{
			if ($context['infraction_details']['template'] == 'custom')
				$template_msg = serialize($context['infraction_details']['template_msg']);
			else
				$template_msg = !empty($context['infraction_details']['template']) ? 'template:' . $context['infraction_details']['template'] : '';

			if (empty($context['infraction_details']['id']))
				wesql::insert('replace',
					'{db_prefix}infractions',
					array(
						'infraction_name' => 'string',
						'infraction_msg' => 'string',
						'points' => 'int',
						'sanctions' => 'string',
						'duration' => 'string',
						'issuing_groups' => 'string',
					),
					array(
						$context['infraction_details']['infraction_name'],
						$template_msg,
						$context['infraction_details']['points'],
						implode(',', $context['infraction_details']['sanctions']),
						$context['infraction_details']['duration']['unit'] == 'i' ? 'i' : $context['infraction_details']['duration']['number'] . $context['infraction_details']['duration']['unit'],
						implode(',', $context['infraction_details']['issuing_groups']),
					),
					array('id_infraction')
				);
			else
				wesql::query('
					UPDATE {db_prefix}infractions
					SET infraction_name = {string:infraction_name},
						infraction_msg = {string:infraction_msg},
						points = {int:points},
						sanctions = {string:sanctions},
						duration = {string:duration},
						issuing_groups = {string:issuing_groups}
					WHERE id_infraction = {int:infraction}',
					array(
						'infraction_name' => $context['infraction_details']['infraction_name'],
						'infraction_msg' => $template_msg,
						'points' => $context['infraction_details']['points'],
						'sanctions' => implode(',', $context['infraction_details']['sanctions']),
						'duration' => $context['infraction_details']['duration']['unit'] == 'i' ? 'i' : $context['infraction_details']['duration']['number'] . $context['infraction_details']['duration']['unit'],
						'issuing_groups' => implode(',', $context['infraction_details']['issuing_groups']),
						'infraction' => $context['infraction_details']['id'],
					)
				);

			redirectexit('action=admin;area=infractions;sa=infractions');
		}
		else
			foreach ($context['errors'] as $k => $v)
				if (isset($txt[$v]))
					$context['errors'][$k] = $txt[$v];
	}

	// We might be allowing some ad-hoc stuff. We don't need to treat it quite as nicely as we did elsewhere, though.
	// This is, in fact, purely for display purposes.
	$context['infractions_adhoc'] = array(1);
	if (!empty($settings['infraction_adhoc']))
	{
		// We deliberately don't just grab the list because it's possible there are old groups and stuff here.
		$data = unserialize($settings['infraction_adhoc']);
		foreach ($data as $group => $rules)
			if (!empty($rules['enabled']))
				$context['infractions_adhoc'][] = $group;
	}

	$context['infractions_templates'] = array('bad_avatar', 'bad_sig', 'bad_language', 'spam');

	// And away we go.
	wetem::load('edit_infraction');
}

function InfractionLevels()
{
	global $txt, $context, $settings;

	getInfractionLevels();

	if (isset($_GET['save']))
	{
		checkSession();

		// magicalness
		$_POST['enabled'] = isset($_POST['enabled']) ? (array) $_POST['enabled'] : array();
		foreach ($context['infraction_levels'] as $infraction => $details)
		{
			if (!isset($_POST[$infraction]))
				continue;

			$context['infraction_levels'][$infraction] = array(
				'points' => min(max((int) $_POST[$infraction], 0), 1000),
				'enabled' => !empty($_POST['enabled'][$infraction]),
			);
		}

		if (!empty($context['infraction_levels']))
			updateSettings(array('infraction_levels' => serialize($context['infraction_levels'])));
	}

	wetem::load('infraction_levels');
}

function ModifyInfractionSettings($return_config = false)
{
	global $context, $txt, $settings;

	loadSource('ManageServer');

	$context['page_title'] = $txt['infractions_title'] . ' - ' . $txt['settings'];

	$config_vars = array(
		array('yesno', 'revoke_own_issued'),
		array('callback', 'revoke_any_issued'),
		array('callback', 'no_warn_groups'),
		array('select', 'warning_show', array($txt['setting_warning_show_none'], $txt['setting_warning_show_mods'], $txt['setting_warning_show_user'], $txt['setting_warning_show_all']), 'subtext' => $txt['setting_warning_show_subtext']),
	);

	if ($return_config)
		return $config_vars;

	getIssuerGroups();

	// Now, we do various nasty things with this.
	$context['infraction_settings'] = array(
		'revoke_own_issued' => true,
		'revoke_any_issued' => array(1),
		'no_warn_groups' => array(1),
	);
	if (!empty($settings['infraction_settings']))
	{
		$s = @unserialize($settings['infraction_settings']);
		foreach ($config_vars as $var)
			if (isset($s[$var[1]]))
				$context['infraction_settings'][$var[1]] = $s[$var[1]];
	}

	if (isset($_GET['save']))
	{
		checkSession();
		$context['infraction_settings']['revoke_own_issued'] = !empty($_POST['revoke_own_issued']);
		foreach (array('revoke_any_issued', 'no_warn_groups') as $item)
		{
			$list = array(1);
			if (isset($_POST[$item]) && is_array($_POST[$item]))
				foreach ($_POST[$item] as $group)
					if (isset($context['group_list'][$group]) && $group != 1)
						$list[] = (int) $group;
			$context['infraction_settings'][$item] = $list;
		}

		$show = isset($_POST['warning_show']) ? min(max($_POST['warning_show'], 0), 3) : 0;

		updateSettings(array('infraction_settings' => serialize($context['infraction_settings']), 'warning_show' => $show));
	}

	// Unfortunately we have to make some pretence for the template.
	$settings['revoke_own_issued'] = !empty($context['infraction_settings']['revoke_own_issued']);

	$context['post_url'] = '<URL>?action=admin;area=infractions;sa=settings;save';
	$context['settings_title'] = $txt['settings'];
	wetem::load('show_settings');
	prepareDBSettingContext($config_vars);
}

function getInfractionLevels()
{
	global $context, $txt, $settings;

	// Just in case.
	loadLanguage('ManageInfractions');

	$context['infraction_levels'] = array(
		'no_avatar' => array('points' => 20, 'enabled' => false),
		'no_sig' => array('points' => 20, 'enabled' => false),
		'disemvowel' => array('points' => 40, 'enabled' => false),
		'moderate' => array('points' => 45, 'enabled' => false),
		'post_ban' => array('points' => 70, 'enabled' => false),
		'pm_ban' => array('points' => 80, 'enabled' => false),
		'soft_ban' => array('points' => 100, 'enabled' => false),
		'hard_ban' => array('points' => 150, 'enabled' => false),
	);

	call_hook('settings_infraction_levels');

	if (!empty($settings['infraction_levels']))
	{
		$levels = unserialize($settings['infraction_levels']);
		foreach ($levels as $infraction => $details)
			if (isset($context['infraction_levels']))
				$context['infraction_levels'][$infraction] = $details;
	}

	foreach ($context['infraction_levels'] as $infraction => $details)
		if (!isset($txt['infraction_' . $infraction]))
			unset ($context['infraction_levels'][$infraction]);
}

function getIssuerGroups()
{
	global $context;

	loadSource('Subs-Members');

	$groups = groupsAllowedTo('issue_warning');
	$context['issuer_groups'] = $groups['allowed'];
	sort($context['issuer_groups']);

	getAllGroups();
}

function getAllGroups()
{
	global $context;

	$request = wesql::query('
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		ORDER BY id_group');

	while ($row = wesql::fetch_assoc($request))
		$context['group_list'][$row['id_group']] = $row['group_name'];
	wesql::free_result($request);
}

function calculate_infraction_expiry($start, $duration)
{
	global $txt;
	static $timeunits = null;

	if ($timeunits === null)
		$timeunits = array(
			'd' => 'days',
			'w' => 'weeks',
			'm' => 'months',
			'y' => 'years',
		);

	if ($duration == 'i')
		return array(1, $txt['never']); // Magic number for non-expiring infractions.
	else
	{
		$unit = substr($duration, -1);
		$number = (int) substr($duration, 0, -1);
		$expiry = strtotime('+' . $number . ' ' . $timeunits[$unit], $start);
		$expiry_str = timeformat($expiry);
		return array($expiry, $expiry_str);
	}
}

// This nasty function gets the log, but while we're at it, also ensures what's in the log is what's applied to the user's account.
function get_validated_infraction_log($memID, $keep_all = true)
{
	global $context, $txt;
	static $classes = null, $time = null;

	if ($classes === null)
	{
		$classes = array(
			0 => 'active',
			1 => 'expired',
			2 => 'revoked',
		);
		$time = time();

		if (!isset($context['revoke_any']))
			$context['revoke_any'] = false;
		if (!isset($context['revoke_own']))
			$context['revoke_own'] = false;
	}

	$context['infraction_log'] = array();

	$request = wesql::query('
		SELECT i.id_issue, IFNULL(memi.id_member, 0) AS issued_by, IFNULL(memi.real_name, i.issued_by_name) AS issued_by_name,
			i.issue_date, i.reason, i.duration, i.points, i.sanctions, i.inf_state, IFNULL(memr.id_member, 0) AS revoked_by
		FROM {db_prefix}log_infractions AS i
		LEFT JOIN {db_prefix}members AS memi ON (i.issued_by = memi.id_member)
		LEFT JOIN {db_prefix}members AS memr ON (i.revoked_by = memr.id_member)
		WHERE i.issued_to = {int:memID}
		ORDER BY issue_date DESC',
		array(
			'memID' => $memID,
		)
	);

	$context['current_points'] = 0;
	$context['current_sanctions'] = array();
	$context['current_sanctions_levels'] = array();

	while ($row = wesql::fetch_assoc($request))
	{
		list ($expiry, $expiry_str) = calculate_infraction_expiry($row['issue_date'], $row['duration']);
		$context['infraction_log'][$row['id_issue']] = array(
			'issued_by' => $row['issued_by'],
			'issued_by_name' => $row['issued_by_name'],
			'issue_date' => $row['issue_date'],
			'issue_date_format' => timeformat($row['issue_date']),
			'expire_date' => $expiry,
			'expire_date_format' => $expiry_str,
			'reason' => $row['reason'],
			'points' => (int) $row['points'],
			'sanctions' => !empty($row['sanctions']) ? explode(',', $row['sanctions']) : array(),
			'inf_state' => $row['inf_state'],
			'row_class' => $classes[$row['inf_state']],
		);

		// Is this still active? While we're going through everything, look at what's still active and ensure it is correct.
		if ($row['inf_state'] == 0)
		{
			$new_state = 0;
			// Oh dear. This one is marked as active but it seems to have expired.
			if ($context['infraction_log'][$row['id_issue']]['expire_date'] < $time)
				$new_state = 1;
			// Oops. This was one that got revoked but for some reason we're not showing as such.
			elseif (!empty($row['revoked_by']))
				$new_state = 2;

			// So something went wrong and we need to update things.
			if ($new_state != 0)
			{
				$context['infraction_log'][$row['id_issue']]['inf_state'] = $new_state;
				$context['infraction_log'][$row['id_issue']]['row_class'] = $classes[$new_state];
				wesql::query('
					UPDATE {db_prefix}log_infractions
					SET inf_state = {int:new_state}
					WHERE inf_state = {int:old_state}
						AND id_issue = {int:infraction}',
					array(
						'old_state' => 0,
						'new_state' => $new_state,
						'infraction' => $row['id_issue'],
					)
				);

				continue;
			}

			$context['current_points'] += (int) $row['points'];
			foreach ($context['infraction_log'][$row['id_issue']]['sanctions'] as $sanction)
			{
				if (!isset($context['current_sanctions'][$sanction]))
					$context['current_sanctions'][$sanction] = $context['infraction_log'][$row['id_issue']]['expire_date'];
				elseif ($context['infraction_log'][$row['id_issue']]['expire_date'] == 1)
					$context['current_sanctions'][$sanction] = 1; // Indefinite.
				elseif ($context['current_sanctions'][$sanction] != 1 && $context['current_sanctions'][$sanction] < $context['infraction_log'][$row['id_issue']]['expire_date'])
					$context['current_sanctions'][$sanction] = $context['infraction_log'][$row['id_issue']]['expire_date'];
			}

			$context['infraction_log'][$row['id_issue']]['can_revoke'] = $context['revoke_any'] || ($context['revoke_own'] && $issued_by == we::$id);
		}

		// We might only want active warnings.
		if ($context['infraction_log'][$row['id_issue']]['inf_state'] != 0 && !$keep_all)
			unset ($context['infraction_log'][$row['id_issue']]);
	}

	// Now, we've ensured we have the correct state of play. Let's make sure the user has that too.
	$data = !empty($user_profile[$memID]['data']) ? $user_profile[$memID]['data'] : array();
	if (empty($context['current_sanctions']) && !empty($data['sanctions']))
		unset ($data['sanctions']);
	else
	{
		$data['sanctions'] = $context['current_sanctions'];
		ksort($data['sanctions']);
	}
	$user_profile[$memID]['data'] = $data;
	updateMemberData($memID, array('warning' => $context['current_points'], 'data' => serialize($data)));

	// Just see if there are any infractions set from levels. This should not be stored in the DB for them.
	if ($context['current_points'] > 0)
		foreach ($context['infraction_levels'] as $infraction => $details)
			if (!empty($details['enabled']) && $context['current_points'] >= $details['points'])
				$context['current_sanctions_levels'][$infraction] = true;

	// There are certain things we do not wish to... let slip.
	if ($memID == we::$id)
	{
		unset ($context['current_sanctions']['soft_ban'], $context['current_sanctions_levels']['soft_ban']);
		$context['can_issue_warning'] = false;
	}
}
