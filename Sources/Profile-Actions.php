<?php
/**
 * Wedge
 *
 * Handles key account-level actions such as account activations, warnings and deletions.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file contains profile action functions.

	void activateAccount(int id_member)
		// !!!

	void issueWarning(int id_member)
		// !!!

	void deleteAccount(int id_member)
		// !!!

	void deleteAccount2(array profile_variables, array &errors, int id_member)
		// !!!

	void subscriptions(int id_member)
		// !!!
*/

// Activate an account.
function activateAccount($memID)
{
	global $context, $user_profile, $settings;

	isAllowedTo('moderate_forum');

	if (isset($_REQUEST['save'], $user_profile[$memID]['is_activated']) && $user_profile[$memID]['is_activated'] != 1)
	{
		// If we are approving the deletion of an account, we do something special ;)
		if ($user_profile[$memID]['is_activated'] == 4)
		{
			loadSource('Subs-Members');
			deleteMembers($context['id_member']);
			redirectexit();
		}

		// Let the hooks know of the activation.
		call_hook('activate', array($user_profile[$memID]['member_name']));

		// Actually update this member now, as it guarantees the unapproved count can't get corrupted.
		updateMemberData($context['id_member'], array('is_activated' => $user_profile[$memID]['is_activated'] >= 20 ? 21 : 1, 'active_state_change' => time(), 'validation_code' => ''));

		// If we are doing approval, update the stats for the member just in case.
		if (in_array($user_profile[$memID]['is_activated'], array(3, 4, 13, 14, 23, 24)))
			updateSettings(array('unapprovedMembers' => ($settings['unapprovedMembers'] > 1 ? $settings['unapprovedMembers'] - 1 : 0)));

		// Make sure we update the stats too.
		updateStats('member', false);
	}

	// Leave it be...
	redirectexit('action=profile;u=' . $memID . ';area=summary');
}

// Present a screen to make sure the user wants to be deleted
function deleteAccount($memID)
{
	global $txt, $context, $settings, $cur_profile;

	if (!we::$user['is_owner'])
		isAllowedTo('profile_remove_any');
	elseif (!allowedTo('profile_remove_any'))
		isAllowedTo('profile_remove_own');

	// Permissions for removing stuff...
	$context['can_delete_posts'] = !we::$user['is_owner'] && allowedTo('moderate_forum');

	// Can they do this, or will they need approval?
	$context['needs_approval'] = we::$user['is_owner'] && !empty($settings['approveAccountDeletion']) && !allowedTo('moderate_forum');
	$context['page_title'] = $txt['deleteAccount'] . ': ' . $cur_profile['real_name'];
}

function deleteAccount2($profile_vars, $post_errors, $memID)
{
	global $context, $cur_profile, $settings;

	// Try to get more time...
	@set_time_limit(600);

	// !!! Add a way to delete pms as well?

	if (!we::$user['is_owner'])
		isAllowedTo('profile_remove_any');
	elseif (!allowedTo('profile_remove_any'))
		isAllowedTo('profile_remove_own');

	checkSession();

	$old_profile =& $cur_profile;

	// Too often, people remove/delete their own only account.
	if (in_array(1, explode(',', $old_profile['additional_groups'])) || $old_profile['id_group'] == 1)
	{
		// Are you allowed to administrate the forum, as they are?
		isAllowedTo('admin_forum');

		$request = wesql::query('
			SELECT id_member
			FROM {db_prefix}members
			WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
				AND id_member != {int:selected_member}
			LIMIT 1',
			array(
				'admin_group' => 1,
				'selected_member' => $memID,
			)
		);
		list ($another) = wesql::fetch_row($request);
		wesql::free_result($request);

		if (empty($another))
			fatal_lang_error('at_least_one_admin', 'critical');
	}

	// This file is needed for the deleteMembers function.
	loadSource('Subs-Members');

	// Do you have permission to delete others profiles, or is that your profile you wanna delete?
	if ($memID != we::$id)
	{
		isAllowedTo('profile_remove_any');

		// Now, have you been naughty and need your posts deleting?
		// !!! Should this check board permissions?
		if ($_POST['remove_type'] != 'none' && allowedTo('moderate_forum'))
		{
			// Include RemoveTopics - essential for this type of work!
			loadSource('RemoveTopic');

			// First off we delete any topics the member has started - if they wanted topics being done.
			if ($_POST['remove_type'] == 'topics')
			{
				// Fetch all topics started by this user within the time period.
				$request = wesql::query('
					SELECT t.id_topic
					FROM {db_prefix}topics AS t
					WHERE t.id_member_started = {int:selected_member}',
					array(
						'selected_member' => $memID,
					)
				);
				$topicIDs = array();
				while ($row = wesql::fetch_assoc($request))
					$topicIDs[] = $row['id_topic'];
				wesql::free_result($request);

				// Actually remove the topics.
				// !!! This needs to check permissions, but we'll let it slide for now because of moderate_forum already being had.
				removeTopics($topicIDs);
			}

			// Now delete the remaining messages.
			$request = wesql::query('
				SELECT m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic
						AND t.id_first_msg != m.id_msg)
				WHERE m.id_member = {int:selected_member}',
				array(
					'selected_member' => $memID,
				)
			);
			// This could take a while... but ya know it's gonna be worth it in the end.
			while ($row = wesql::fetch_assoc($request))
			{
				// !!! There has to be a better way. What about using the pause/continue templates?
				if (function_exists('apache_reset_timeout'))
					@apache_reset_timeout();

				removeMessage($row['id_msg']);
			}
			wesql::free_result($request);
		}

		// Only delete this poor members account if they are actually being booted out of camp.
		if (isset($_POST['deleteAccount']))
			deleteMembers($memID);
	}
	// Do they need approval to delete?
	elseif (empty($post_errors) && !empty($settings['approveAccountDeletion']) && !allowedTo('moderate_forum'))
	{
		// Setup their account for deletion ;)
		updateMemberData($memID, array('is_activated' => 4));
		// Another account needs approval...
		updateSettings(array('unapprovedMembers' => true), true);
	}
	// Also check if you typed your password correctly.
	elseif (empty($post_errors))
	{
		deleteMembers($memID);

		loadSource('Logout');
		Logout(true);

		redirectExit();
	}
}

function profileInfractions($memID)
{
	global $txt, $context, $settings, $cur_profile, $user_profile;

	if (we::$user['is_owner'])
		isAllowedTo('profile_view_own');
	else
		isAllowedTo('issue_warning');

	// Some stuff that's useful.
	$inf_settings = !empty($settings['infraction_settings']) ? unserialize($settings['infraction_settings']) : array();
	$time = time(); // Every second counts.

	loadLanguage('ManageInfractions');
	loadSource('ManageInfractions');
	getInfractionLevels();

	// Can this user actually get a warning?
	$no_warn_groups = isset($inf_settings['no_warn_groups']) ? $inf_settings['no_warn_groups'] : array();
	$no_warn_groups[] = 1; // Can't warn admins, ever. This just in case.
	$user_groups = array_merge(array($context['member']['group_id']), explode(',', $user_profile[$memID]['additional_groups']));
	$context['can_issue_warning'] = !we::$user['is_owner'] && allowedTo('issue_warning') && count(array_intersect($user_groups, $no_warn_groups)) == 0;

	// What about revoking warnings?
	$revoke_any = isset($inf_settings['revoke_any_issued']) ? $inf_settings['revoke_any_issued'] : array();
	$revoke_any[] = 1; // Admins really are special.
	$context['revoke_own'] = !empty($inf_settings['revoke_own_issued']);
	$context['revoke_any'] = count(array_intersect(we::$user['groups'], $revoke_any)) != 0;

	// Viewing a previously issued warning?
	if (!empty($_GET['view']))
	{
		$_GET['view'] = (int) $_GET['view'];

		loadTemplate('GenericPopup');
		loadLanguage('Help');
		wetem::hide();
		wetem::load('popup');

		$request = wesql::query('
			SELECT IFNULL(memi.id_member, 0) AS issued_by, IFNULL(memi.real_name, i.issued_by_name) AS issued_by_name,
				i.issue_date, i.reason, i.duration, i.points, i.sanctions, i.notice_subject,
				i.notice_body, i.inf_state, IFNULL(memr.id_member, 0) AS revoked_by, IFNULL(memr.real_name, i.revoked_by_name) AS revoked_by_name,
				i.revoked_date, i.revoked_reason, i.because_of
			FROM {db_prefix}log_infractions AS i
			LEFT JOIN {db_prefix}members AS memi ON (i.issued_by = memi.id_member)
			LEFT JOIN {db_prefix}members AS memr ON (i.revoked_by = memr.id_member)
			WHERE id_issue = {int:id_issue}
				AND issued_to = {int:memID}',
			array(
				'id_issue' => $_GET['view'],
				'memID' => $memID,
			)
		);

		if (wesql::num_rows($request) == 0)
		{
			wesql::free_result($request);
			$_POST['t'] = $context['popup_contents'] = $txt['cannot_view_warning'];
			return;
		}

		$row = wesql::fetch_assoc($request);
		wesql::free_result($request);

		call_hook('infraction_view', array(&$row, $memID));

		list ($expiry, $expiry_str) = calculate_infraction_expiry($row['issue_date'], $row['duration']);
		// We want slightly different formatting here.
		if ($expiry == 1)
			$expiry_str = $txt['expires_never'];

		$_POST['t'] = $txt['infraction_history'];
		$context['popup_contents'] = '
	<dl class="settings">
		<dt>' . $txt['infraction_issued_by'] . '</dt>
		<dd>' . (empty($row['issued_by']) ? $row['issued_by_name'] : '<a href="<URL>?action=profile;u=' . $row['issued_by'] . '" target="_blank">' . $row['issued_by_name'] . '</a>') . '</dd>
		<dt>' . $txt['infraction_issued_on'] . '</dt>
		<dd>' . timeformat($row['issue_date']) . '</dd>
		<dt>' . ($expiry == 1 || $expiry > $time ? $txt['infraction_expires_on'] : $txt['infraction_has_expired']) . '</dt>
		<dd>' . $expiry_str . '</dd>
		<dt>' . $txt['infraction_reason_given'] . '</dt>
		<dd>' . $row['reason'] . '</dd>';

		if (!empty($row['because_of']))
		{
			// A hook may have unserialized it. It might not have.
			$because = is_array($row['because_of']) ? unserialize($row['because_of']) : $row['because_of'];
			if (!empty($because['desc']))
			{
				$context['popup_contents'] .= '
		<dt>' . (!empty($txt[$because['desc']]) ? $txt[$because['desc']] : $because['desc']) . '</dt>
		<dd>' . $because['link'] . '</dd>';
			}
		}

		// Now we do the punishment bits.
		$this_sanctions = array();
		$sanctions = explode(',', $row['sanctions']);
		foreach ($sanctions as $sanction)
			if (isset($txt['infraction_' . $sanction]))
				$this_sanctions[] = $txt['infraction_' . $sanction];
		$context['popup_contents'] .= '
	</dl>
	<hr>
	<dl class="settings">
		<dt>' . $txt['this_points'] . '</dt>
		<dd>' . (empty($row['points']) ? $txt['no_points'] : comma_format($row['points'])) . '</dd>
		<dt>' . $txt['this_sanctions'] . '</dt>
		<dd>' . (empty($this_sanctions) ? $txt['no_sanctions'] : implode('<br>', $this_sanctions)) . '</dd>
	</dl>';

		if (!empty($row['revoked_date']))
			$context['popup_contents'] .= '
	<hr>
	<dl class="settings">
		<dt>' . $txt['infraction_revoked_by'] . '</dt>
		<dd>' . (empty($row['revoked_by']) ? $row['revoked_by_name'] : '<a href="<URL>?action=profile;u=' . $row['revoked_by'] . '" target="_blank">' . $row['revoked_by_name'] . '</a>') . '</dd>
		<dt>' . $txt['infraction_revoked_on'] . '</dt>
		<dd>' . timeformat($row['revoked_date']) . '</dd>
		<dt>' . $txt['infraction_reason_given'] . '</dt>
		<dd>' . $row['revoked_reason'] . '</dd>
	</dl>';

		// Did the user get a notification?
		if (!empty($row['notice_subject']))
		{
			$context['popup_contents'] .= '
	<hr>
	' . $txt['infraction_notification'] . '<br>
	<div class="bbc_quote">
		<div>
			<blockquote>' . $txt['notification_subject'] . ' ' . $row['notice_subject'] . '<br><br>' . parse_bbc($row['notice_body'], array('smileys' => false, 'parse_type' => 'infraction_notice')) . '
		</div>
	</div>';
		}

		// And we're done. While I know this is unnecessary in a practical sense, it just seems more readable to me to indicate, "you know what, we're done."
		return;
	}
	// Revoking a warning?
	elseif (!empty($_GET['revoke']))
	{
		$_GET['revoke'] = (int) $_GET['revoke'];
		$context['return_to_log'] = isset($_GET['log']);

		// Zerothly, if this is a warning issued to us, we can't go near it. While we::$user['is_owner'] is shorter, it's actually less clear.
		if ($memID == we::$id)
			fatal_lang_error('cannot_revoke_warning_self', false);

		// First, get this one's details.
		$request = wesql::query('
			SELECT IFNULL(memi.id_member, 0) AS issued_by, IFNULL(memi.real_name, i.issued_by_name) AS issued_by_name,
				i.issue_date, i.reason, i.duration, i.points, i.sanctions, i.inf_state, i.imperative
			FROM {db_prefix}log_infractions AS i
			LEFT JOIN {db_prefix}members AS memi ON (i.issued_by = memi.id_member)
			WHERE id_issue = {int:id_issue}
				AND issued_to = {int:memID}',
			array(
				'id_issue' => $_GET['revoke'],
				'memID' => $memID,
			)
		);

		// We did find it, right?
		if (wesql::num_rows($request) == 0)
		{
			wesql::free_result($request);
			fatal_lang_error('warning_not_found', false);
		}

		$row = wesql::fetch_assoc($request);
		wesql::free_result($request);

		// This is a warning that hasn't already expired or anything, right?
		if ($row['inf_state'] == 1)
			fatal_lang_error('cannot_revoke_already_expired', false);
		elseif ($row['inf_state'] == 2)
			fatal_lang_error('cannot_revoke_already_revoked', false);

		// Now check that we have permission to revoke it.
		if (!$context['revoke_any'] && (!$context['revoke_own'] || $row['issued_by'] != we::$id))
			fatal_lang_error('cannot_revoke_warning', false);

		// Setting up for later.
		list ($expiry, $expiry_str) = calculate_infraction_expiry($row['issue_date'], $row['duration']);
		$context['infraction_details'] = array(
			'id_issue' => $_GET['revoke'],
			'issued_by' => $row['issued_by'],
			'issued_by_name' => $row['issued_by_name'],
			'issued_by_format' => empty($row['issued_by']) ? $row['issued_by_name'] : '<a href="<URL>?action=profile;u=' . $row['issued_by'] . '" target="_blank">' . $row['issued_by_name'] . '</a>',
			'issue_date' => $row['issue_date'],
			'issue_date_format' => timeformat($row['issue_date']),
			'expire_date' => $expiry,
			'expire_date_format' => $expiry_str,
			'reason' => $row['reason'],
			'imperative' => $row['imperative'],
		);
		$context['errors'] = array();

		// Are we saving or are we showing the form?
		if (isset($_GET['infsave']))
		{
			checkSession();

			if (empty($_POST['revoke_reason']))
				$context['errors'][] = $txt['error_no_revoke_reason'];

			if (empty($context['errors']))
			{
				// Clean up the imperative event.
				if (!empty($context['infraction_details']['imperative']))
				{
					wesql::query('
						DELETE FROM {db_prefix}scheduled_imperative
						WHERE id_instr = {int:event}',
						array(
							'event' => $context['infraction_details']['imperative'],
						)
					);
					loadSource('Subs-Scheduled');
					recalculateNextImperative();
				}

				wesql::query('
					UPDATE {db_prefix}log_infractions
					SET inf_state = {int:revoked},
						revoked_by = {int:current_user},
						revoked_by_name = {string:current_name},
						revoked_date = {int:time},
						revoked_reason = {string:reason},
						imperative = {int:no_event}
					WHERE id_issue = {int:id_issue}',
					array(
						'id_issue' => $_GET['revoke'],
						'revoked' => 2,
						'current_user' => we::$id,
						'current_name' => we::$user['name'],
						'time' => $time,
						'reason' => westr::safe(westr::cut($_POST['revoke_reason']), ENT_QUOTES),
						'no_event' => 0,
					)
				);

				// The per-user log area actually checks state for everything and as such will clean up after us here. DRY!
				if (!$context['return_to_log'])
					redirectexit('action=profile;u=' . $memID . ';area=infractions');
				else
				{
					// But if we return to the log, we don't.
					get_validated_infraction_log($memID, false);
					redirectexit('action=moderate;area=warnings;sa=log');
				}
			}
		}

		// If we're here, we're showing the form.
		wetem::load('profileInfractions_revoke');

		return;
	}
	// Issuing a warning?
	elseif (isset($_GET['warn']))
	{
		// Users can't issue warnings to themselves, but that's one route to how we got here.
		if (we::$user['is_owner'])
			fatal_lang_error('cannot_issue_warning_self', false);

		// Can this user actually receive a warning?
		if (!$context['can_issue_warning'])
			fatal_lang_error('cannot_issue_warning_these_groups', false);

		$inf_adhoc = isset($settings['infraction_adhoc']) ? unserialize($settings['infraction_adhoc']) : array();
		$context['can_issue_adhoc'] = false;
		$points = array(0);
		$sanctions = array();

		// Can we actually issue an infraction?
		if (!we::$is_admin)
		{
			$per_day = array(0);
			foreach ($inf_adhoc as $group => $details)
				if (in_array($group, $user_groups))
				{
					$context['can_issue_adhoc'] |= !empty($details['allowed']);
					$points[] = $details['points'];

					if (!empty($details['per_days']))
						$per_day[] = $details['per_days'];

					if (!empty($details['sanctions']))
					$sanctions = array_merge($sanctions, $details['sanctions']);
				}
			$max_per_day = max($per_day);
			$context['max_points'] = max($points);

			$request = wesql::query('
				SELECT COUNT(id_issue)
				FROM {db_prefix}log_infractions
				WHERE issued_by = {int:issued_by}
					AND issue_date >= {int:time}',
				array(
					'issued_by' => we::$id,
					'time' => time() - 86400,
				)
			);
			list ($issued) = wesql::fetch_row($request);
			wesql::free_result($request);

			if ($issued >= $max_per_day)
				fatal_lang_error($max_per_day == 0 ? 'cannot_issue_warning_these_groups' : 'cannot_issue_warning_too_many', false);

			// This allows us to get it in the same order as everywhere else. Order is good.
			$context['allowed_sanctions'] = array();
			foreach ($context['infraction_levels'] as $infraction => $dummy)
				if (in_array($infraction, $sanctions))
					$context['allowed_sanctions'][] = $infraction;
		}
		else
		{
			$context['can_issue_adhoc'] = true;
			$context['max_points'] = 1000;
			$context['allowed_sanctions'] = array_keys($context['infraction_levels']);
		}

		// So, we can issue a warning. Let's get the pre-set warnings we can issue.
		$context['preset_infractions'] = array();
		// Because we're matching lots of group combinations, grab everything.
		$request = wesql::query('
			SELECT id_infraction, infraction_name, infraction_msg, points, sanctions, duration, issuing_groups
			FROM {db_prefix}infractions');
		while ($row = wesql::fetch_assoc($request))
		{
			$row['issuing_groups'] = explode(',', $row['issuing_groups']);
			$row['issuing_groups'][] = 1; // Gotta have admins!

			// So we can issue this right?
			if (count(array_intersect($row['issuing_groups'], we::$user['groups'])) == 0)
				continue;

			// Don't need this any more.
			unset ($row['issuing_groups']);

			$context['preset_infractions'][$row['id_infraction']] = $row;
		}
		wesql::free_result($request);

		// Now we need to process what other rules are in terms of options.
		if (!$context['can_issue_adhoc'] && empty($context['preset_infractions']))
			fatal_lang_error('cannot_issue_warning_these_groups', false);

		$context['current_points'] = $cur_profile['warning'];

		// We want to dump some stuff to the template.
		$lang_try = array(we::$user['language'], $settings['language'], 'english');
		foreach ($context['preset_infractions'] as $infraction => $details)
		{
			// If the warning uses one of the preset templates, fetch that for the issuing user's language
			if (strpos($details['infraction_msg'], 'template') === 0)
			{
				$tpl = $txt['tpl_infraction_' . substr($details['infraction_msg'], 9)];
				$context['preset_infractions'][$infraction]['infraction_msg'] = array('subject' => westr::safe($tpl['subject'], ENT_QUOTES), 'body' => westr::safe($tpl['body'], ENT_QUOTES));
			}
			// Otherwise attempt to find it from what messages were defined.
			elseif (!empty($details['infraction_msg']))
			{
				$tpl = unserialize($details['infraction_msg']);
				$found = false;
				foreach ($lang_try as $lang)
					if (isset($tpl[$lang]))
					{
						// This should already have been sanitised when saving.
						$context['preset_infractions'][$infraction]['infraction_msg'] = $tpl[$lang];
						$found = true;
						break;
					}

				if (!$found)
					$context['preset_infractions'][$infraction]['infraction_msg'] = '';
			}
		}

		$js = array();
		foreach ($context['infraction_levels'] as $inf => $details)
			$js[$inf] = array($txt['infraction_' . $inf], $details['points'], !empty($details['enabled']));
		
		$context['current_sanctions'] = !empty($cur_profile['sanctions']) ? $cur_profile['sanctions'] : array();
		// Don't tell the user he is soft banned even if he is.
		if (we::$id == $memID)
			unset ($context['current_sanctions']['soft_ban']);

		if (isset($_REQUEST['for']) && strpos($_REQUEST['for'], ':') !== false)
		{
			list ($type, $item) = explode(':', $_REQUEST['for']);
			if ($type == 'post' && (int) $item > 0)
			{
				$item = (int) $item;
				$request = wesql::query('
					SELECT id_member, subject, id_topic
					FROM {db_prefix}messages
					WHERE id_msg = {int:msg}',
					array(
						'msg' => $item,
					)
				);
				// Find it?
				if (wesql::num_rows($request) != 0)
				{
					// Is it this member's post, right?
					$row = wesql::fetch_assoc($request);
					if ($row['id_member'] == $memID)
						$context['issuing_for'] = array(
							'desc' => 'issuing_for_message',
							'link' => '<a href="<URL>?topic=' . $row['id_topic'] . '.msg' . $item . '#msg' . $item . '" target="_blank">' . $row['subject'] . '</a>',
							'var' => $_REQUEST['for'],
							'note' => array('notification_body_message'),
							'type' => $type,
							'item' => $item,
							'repl' => array(
								'{MESSAGE}' => 'because_message',
							),
						);
				}
				wesql::free_result($request);
			}
			else
				// Whatever this warning is about, this hook should process the type/item combo and populate $context['issuing_for'] appropriately.
				// It should also validate that $type/$item is owned by $memID.
				call_hook('infraction_issue_content', array($type, $item, $memID));
		}

		// More things we might need to display.
		$context['adhoc_stuff'] = array(
			'duration' => array('unit' => 'w', 'number' => 1),
			'points' => 0,
			'current_infraction' => 0,
			'note_subject' => '',
			'note_body' => '',
		);

		// Just before we handle saving, is there anything else we might want to do?
		call_hook('infraction_issue_pre');

		if (isset($_GET['infsave']))
		{
			checkSession();

			$_POST['reason'] = isset($_POST['reason']) ? trim($_POST['reason']) : '';
			if (empty($_POST['reason']))
				$context['errors']['error_infraction_no_issue_reason'] = $txt['error_infraction_no_issue_reason'];

			// They're issuing something magical.
			if ($context['can_issue_adhoc'] && (empty($_POST['infraction']) || $_POST['infraction'] == 'custom'))
			{
				$context['adhoc_stuff']['current_infraction'] = 'custom';

				// Validate the duration
				$duration = '';
				if (!empty($_POST['infraction_duration_unit']))
				{
					if ($_POST['infraction_duration_unit'] == 'i')
						$duration = 'i';
					elseif (!isset($txt['infraction_duration_types'][$_POST['infraction_duration_unit']]) || empty($_POST['infraction_duration_number']) || $_POST['infraction_duration_number'] > 50)
						$context['errors']['error_invalid_duration'] = $txt['error_invalid_duration'];
					else
						$duration = $_POST['infraction_duration_number'] . $_POST['infraction_duration_unit'];
				}
				if ($duration == 'i')
					$context['adhoc_stuff']['duration'] = array('unit' => 'i', 'number' => 1);
				else
					$context['adhoc_stuff']['duration'] = array('unit' => $_POST['infraction_duration_unit'], 'number' => $_POST['infraction_duration_number']);

				// Validate the points amount
				$context['adhoc_stuff']['points'] = isset($_POST['points']) ? min(max($_POST['points'], 0), $context['max_points']) : 0;

				// Validate the sanctions list
				$sanctions = !empty($_POST['sanctions']) && is_array($_POST['sanctions']) ? array_intersect($_POST['sanctions'], $context['allowed_sanctions']) : array();
				$context['adhoc_stuff']['sanctions'] = $sanctions;

				// And notificationy stuff.
				$context['adhoc_stuff']['note_subject'] = $_POST['note_subject'] = isset($_POST['note_subject']) ? westr::safe($_POST['note_subject'], ENT_QUOTES) : '';
				$context['adhoc_stuff']['note_body'] = $_POST['note_body'] = isset($_POST['note_body']) ? westr::safe($_POST['note_body'], ENT_QUOTES) : '';

				// Set the variables up we're going to need here.
				if (empty($context['errors']))
				{
					// We don't need to store everything in issuing_for into the database as well.
					$because_of = !empty($context['issuing_for']) ? $context['issuing_for'] : array();
					unset ($because_of['repl'], $because_of['note'], $because_of['var']);

					$infraction_details = array(
						'issued_by' => we::$id,
						'issued_by_name' => we::$user['name'],
						'issue_date' => time(),
						'issued_to' => $context['member']['id'],
						'issued_to_name' => $context['member']['name'],
						'reason' => westr::safe($_POST['reason'], ENT_QUOTES),
						'duration' => $duration,
						'points' => $context['adhoc_stuff']['points'],
						'sanctions' => implode(',', $sanctions),
						'notice_subject' => '',
						'notice_body' => '',
						'inf_state' => 0, // Active
						'imperative' => 0,
						'because_of' => !empty($because_of) ? serialize($because_of) : '',
					);

					if (trim($_POST['note_subject']) !== '' && trim($_POST['note_body']) !== '')
					{
						$infraction_details['notice_subject'] = trim($_POST['note_subject']);
						$infraction_details['notice_body'] = trim($_POST['note_body']);
					}
				}
			}
			// Nope, boring old preset one.
			else
			{
				// This is fairly straightforward. All we need to do is validate that the user is allowed to issue the infraction selected, then *do* it.
				if (empty($_POST['infraction']) || !isset($context['preset_infractions'][$_POST['infraction']]))
					$context['errors']['error_infraction_not_selected'] = $txt['error_infraction_not_selected'];
				else
					$context['adhoc_stuff']['current_infraction'] = $_POST['infraction'];

				// Set the variables up we're going to need here.
				if (empty($context['errors']))
				{
					$this_inf = $context['preset_infractions'][$_POST['infraction']];

					// We don't need to store everything in issuing_for into the database as well.
					$because_of = !empty($context['issuing_for']) ? $context['issuing_for'] : array();
					unset ($because_of['repl'], $because_of['note'], $because_of['var']);

					$infraction_details = array(
						'issued_by' => we::$id,
						'issued_by_name' => we::$user['name'],
						'issue_date' => time(),
						'issued_to' => $context['member']['id'],
						'issued_to_name' => $context['member']['name'],
						'reason' => westr::safe($_POST['reason'], ENT_QUOTES),
						'duration' => $this_inf['duration'],
						'points' => $this_inf['points'],
						'sanctions' => $this_inf['sanctions'],
						'notice_subject' => !empty($this_inf['infraction_msg']) ? $this_inf['infraction_msg']['subject'] : '',
						'notice_body' => !empty($this_inf['infraction_msg']) ? $this_inf['infraction_msg']['body'] : '',
						'inf_state' => 0, // Active
						'imperative' => 0,
						'because_of' => !empty($because_of) ? serialize($because_of) : '',
					);
				}
			}

			if (empty($context['errors']))
			{
				list ($expiry, $expiry_str) = calculate_infraction_expiry($infraction_details['issue_date'], $infraction_details['duration']);

				// Right, now we get to insert the punishments text. First: no punishment at all, not even points.
				if (!empty($infraction_details['notice_body']))
				{
					$body_replacements = array();
					if (empty($infraction_details['sanctions']) && empty($infraction_details['points']))
						$body_replacements['{PUNISHMENTS}'] = $txt['no_punishment'];
					else
					{
						$punishments = array();
						$pun_count = 0;
						if (!empty($infraction_details['sanctions']))
						{
							$sanctions = explode(',', $infraction_details['sanctions']);
							foreach ($sanctions as $k => $v)
								if (!isset($txt['pun_infraction_' . $v]) || $v == 'soft_ban')
									unset ($sanctions[$k]);

							if (!empty($sanctions))
							{
								$item = number_context('received_punishments', count($sanctions));
								foreach ($sanctions as $sanction)
									$item .= "\n* " . $txt['pun_infraction_' . $sanction];
								$pun_count += count($sanctions);

								$punishments[] = $item;
							}
						}

						if (!empty($infraction_details['points']))
						{
							$punishments[] = str_replace('{POINTS}', comma_format($context['current_points'] + $infraction_details['points']), number_context('pun_points', $infraction_details['points']));
							$pun_count++;
						}

						if (!empty($punishments))
						{
							if ($infraction_details['duration'] == 'i')
								$punishments[] = number_context('punishments_no_expire', $pun_count);
							else
								$punishments[] = str_replace('{EXPIRY}', $expiry_str, number_context('punishments_will_expire', $pun_count));
						}

						$body_replacements['{PUNISHMENTS}'] = implode("\n\n", $punishments);
					}

					call_hook('infraction_pre_notify', array(&$infraction_details, &$body_replacements));

					// Did they get it because of a post?
					if (!empty($context['issuing_for']['repl']))
						foreach ($context['issuing_for']['repl'] as $srch => $repl)
							$body_replaces[$srch] = str_replace('{LINK}', $context['issuing_for']['link'], $repl);

					$infraction_details['notice_body'] = strtr($infraction_details['notice_body'], $body_replacements);
					// And just quickly clean up too many linebreaks.
					$infraction_details['notice_body'] = trim(preg_replace('~\n{3,}~', "\n\n", $infraction_details['notice_body']));

					// Now we send a notification.
					// !!! Do we need to figure out if the user can't see the PM? Should we also send an email if the user prefs otherwise say 'no notifications'?
					$from = array(
						'id' => 0,
						'name' => $context['forum_name'],
						'username' => $context['forum_name'],
					);
					loadSource('Subs-Post');
					sendpm(array('to' => array($memID), 'bcc' => array()), $infraction_details['notice_subject'], $infraction_details['notice_body'], false, $from);
				}

				// Add the imperative task - this is to remove it from the user's account later.
				if ($infraction_details['duration'] != 'i')
				{
					loadSource('Subs-Scheduled');
					$infraction_details['imperative'] = addNextImperative($expiry, array(
						'function' => 'imperative_removeInfraction',
						'parameters' => array(
							'mem' => $memID,
						),
					));
				}

				// Add the infraction to the master table.
				wesql::insert('insert',
					'{db_prefix}log_infractions',
					array(
						'issued_by' => 'int', 'issued_by_name' => 'string', 'issue_date' => 'int',
						'issued_to' => 'int', 'issued_to_name' => 'string', 'reason' => 'string',
						'duration' => 'string', 'points' => 'int', 'sanctions' => 'string',
						'notice_subject' => 'string', 'notice_body' => 'string', 'inf_state' => 'int',
						'imperative' => 'int', 'because_of' => 'string',
					),
					$infraction_details,
					array('id_issue')
				);

				// Bump it back to the list of infractions. This will also update their account with the entire infraction history.
				redirectexit('action=profile;u=' . $memID . ';area=infractions');
			}
		}

		add_js('
	var
		infractions = ' . we_json_encode($context['preset_infractions']) . ',
		current_points = ' . $context['current_points'] . ',
		max_points = ' . $context['max_points'] . ',
		infraction_levels = ' . we_json_encode($js) . ',
		sanction_types = ' . we_json_encode($context['allowed_sanctions']) . ',
		durations = ' . we_json_encode($txt['infraction_duration_types']), ',
		no_punish = ' . we_json_encode($txt['infraction_no_punishments']), ',
		adhoc_stuff = ' . we_json_encode($context['adhoc_stuff']) . ';');
		
		wetem::load('profileInfractions_issue');
	}
	// So, we're getting a list of infractions from the ol' log.
	else
	{
		get_validated_infraction_log($memID, true);
	}
}

function profileBan($memID)
{
	global $txt, $context, $cur_profile, $user_profile;

	isAllowedTo('manage_bans');
	add_css_file('mana', true);
	loadLanguage('ManageBans');

	$context['errors'] = array();

	$context['ban_details'] = array(
		'user' => $memID,
		'ban_email' => westr::safe($cur_profile['email_address'], ENT_QUOTES),
		'email_type' => 'specific',
		'ip' => array(),
		'hardness' => 'soft',
		'ban_acct' => true,
		'ban_on_email' => true,
		'extra' => array(),
	);

	// We're going to be getting 'was it in $_POST' shortly, might as well get it here.
	$_POST['ip'] = isset($_POST['ip']) ? (array) $_POST['ip'] : array();

	// Yay for getting all the IPs a user might have used.
	$request = wesql::query('
		SELECT li.member_ip
		FROM {db_prefix}log_errors AS le
			LEFT JOIN {db_prefix}log_ips AS li ON (le.ip = li.id_ip)
		WHERE le.id_member = {int:memID}',
		array(
			'memID' => $memID,
		)
	);
	while ($row = wesql::fetch_assoc($request))
		if (!isset($context['ban_details']['ip'][$row['member_ip']]))
			$context['ban_details']['ip'][$row['member_ip']] = !empty($_POST['ip'][$row['member_ip']]);
	wesql::free_result($request);

	// More places we can get IP addresses. Many more addresses.
	$request = wesql::query('
		SELECT li.member_ip
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}log_ips AS li ON (m.poster_ip = li.id_ip)
		WHERE m.id_member = {int:memID}',
		array(
			'memID' => $memID,
		)
	);
	while ($row = wesql::fetch_assoc($request))
		if (!isset($context['ban_details']['ip'][$row['member_ip']]))
			$context['ban_details']['ip'][$row['member_ip']] = !empty($_POST['ip'][$row['member_ip']]);
	wesql::free_result($request);

	asort($context['ban_details']['ip']);

	if (isset($_GET['bansave']))
	{
		checkSession();
		$time = time(); // We want all of them to have the same time. There might be many.

		// Time for some checking.
		$context['ban_details']['ban_reason'] = !empty($_POST['ban_reason']) ? westr::safe($_POST['ban_reason'], ENT_QUOTES) : '';
		if (trim($context['ban_details']['ban_reason']) === '')
			$context['errors']['ban_invalid_reason'] = $txt['ban_invalid_reason'];

		if (!empty($_POST['ban_message']) && trim(westr::safe($_POST['ban_message']) !== ''))
			$context['ban_details']['extra']['message'] = westr::safe($_POST['ban_message'], ENT_QUOTES);

		$context['ban_details']['hardness'] = isset($_POST['hardness']) && $_POST['hardness'] == 'hard' ? 'hard' : 'soft';

		$extra_serialized = !empty($context['ban_details']['extra']) ? serialize($context['ban_details']['extra']) : '';

		$hardness_flag = $context['ban_details']['hardness'] == 'hard' ? 1 : 0;
		$ban_criteria = array();

		// Ban on user id?
		if (!empty($_POST['ban_type_acct']))
			$ban_criteria[] = array($hardness_flag, 'id_member', (string) $memID, $context['ban_details']['ban_reason'], $extra_serialized, $time, we::$id);

		// Ban on email?
		$context['ban_details']['ban_on_email'] = !empty($_POST['ban_type_on_email']);
		$context['ban_details']['email_type'] = isset($_POST['ban_type_email']) && in_array($_POST['ban_type_email'], array('specific', 'domain', 'tld')) ? $_POST['ban_type_email'] : 'specific';

		if ($context['ban_details']['ban_on_email'])
		{
			$valid_email = false;
			if (empty($_POST['ban_email_content']))
				$context['errors']['ban_invalid_email'] = $txt['ban_invalid_email'];
			elseif ($context['ban_details']['email_type'] == 'specific')
			{
				// We want to allow wildcards in the username (but not domain), and also use filter_var to make life easy.
				$email = $_POST['ban_email_content'];
				if (strpos($email, '@') !== false)
				{
					list ($user, $domain) = explode('@', $email);
					$user = str_replace('*', '', $user);
					if (filter_var($user . '@' . $domain, FILTER_VALIDATE_EMAIL))
					{
						$context['ban_details']['ban_email'] = $email;
						$valid_email = true;
					}
					else
						$context['ban_details']['ban_email'] = '*@' . westr::safe($email, ENT_QUOTES);
				}
				else
					$context['ban_details']['ban_email'] = '*@' . westr::safe($email, ENT_QUOTES);
			}
			elseif ($context['ban_details']['email_type'] == 'domain')
			{
				// Strip anything before a leading @ just in case
				$email = trim($_POST['ban_email_content']);
				if ($pos = strrpos($email, '@') !== false)
					$email = substr($email, $pos);

				// Now validate the domain and if it is sane, reassemble and reset ready for the template
				if (filter_var('test@' . $email, FILTER_VALIDATE_EMAIL))
				{
					$context['ban_details']['ban_email'] = '*@' . $email;
					$valid_email = true;
				}
				else
					// It wasn't valid. So, enforce that it is safe for redisplay, then flag as error.
					$context['ban_details']['ban_email'] = '*@' . westr::safe(trim($_POST['ban_email_content']), ENT_QUOTES);
			}
			elseif ($context['ban_details']['email_type'] == 'tld')
			{
				// Start by stripping any leading * (e.g. *.tld becomes .tld) and also check that we didn't get *.*.tld nonsense.
				$email = trim($_POST['ban_email_content']);
				if ($pos = strrpos($email, '*') !== false)
					$email = substr($email, $pos);
				$email = preg_replace('~\.+~', '.', $email);
				// Right, so now we should have .tld stuff only. This is not efficient?
				if (preg_match('~(\.?\pL(\pL|\d)*\.)*(\pL{2,})~i', $email))
				{
					$context['ban_details']['ban_email'] = '@*' . ($email[0] != '.' ? '.' : '') . $email;
					$valid_email = true;
				}
				else
					// It wasn't valid. So, enforce that it is safe for redisplay, then flag as error.
					$context['ban_details']['ban_email'] = '@*' . westr::safe($email, ENT_QUOTES);
			}

			if ($valid_email)
			{
				$extra = $context['ban_details']['extra'];
				$extra['gmail_style'] = !empty($_POST['ban_gmail_style']);
				$ban_criteria[] = array($hardness_flag, 'email', $context['ban_details']['ban_email'], $context['ban_details']['ban_reason'], !empty($extra) ? serialize($extra) : '', $time, we::$id);
			}
			else
				$context['errors']['ban_invalid_email'] = 'ban_invalid_email';
		}

		// We did actually get the IP address business earlier.
		foreach ($context['ban_details']['ip'] as $ip => $is_checked)
			if ($is_checked)
				$ban_criteria[] = array($hardness_flag, 'ip_address', $ip, $context['ban_details']['ban_reason'], $extra_serialized, $time, we::$id);

		if (empty($ban_criteria) && empty($context['errors']))
			$context['errors']['ban_nothing_to_ban'] = $txt['ban_nothing_to_ban'];

		// OK, so we've done some checking. If there are no errors, go make some bans.
		if (empty($context['errors']))
		{
			wesql::insert('replace',
				'{db_prefix}bans',
				array(
					'hardness' => 'int', 'ban_type' => 'string', 'ban_content' => 'string',
					'ban_reason' => 'string', 'extra' => 'string', 'added' => 'int', 'member_added' => 'int',
				),
				$ban_criteria,
				array('id_ban')
			);
			loadSource('ManageBans');
			updateBannedMembers();
			redirectexit('action=profile;u=' . $memID);
		}
	}
}

// Function for doing all the paid subscription stuff - kinda.
function subscriptions($memID)
{
	global $context, $txt, $settings, $scripturl, $user_profile;

	// Load the paid template anyway.
	loadTemplate('ManagePaid');
	loadLanguage('ManagePaid');

	// Load all of the subscriptions.
	loadSource('ManagePaid');
	loadSubscriptions();
	$context['member']['id'] = $memID;

	// moderate_forum allows override/granting subscriptions.
	$has_override = allowedTo('moderate_forum');

	// Load the groups.
	$request = wesql::query('
		SELECT id_subscribe, id_group
		FROM {db_prefix}subscriptions_groups');
	while ($row = wesql::fetch_assoc($request))
	{
		if (!isset($context['subscriptions'][$row['id_subscribe']]['allowed_groups']))
			$context['subscriptions'][$row['id_subscribe']]['allowed_groups'] = array();

		$context['subscriptions'][$row['id_subscribe']]['allowed_groups'][] = (int) $row['id_group'];
	}
	// Bizarrely, we do not actually know this user's groups at this point.
	loadMemberData($memID);
	$groups = array($user_profile[$memID]['id_group'], $user_profile[$memID]['id_post_group']);
	if (!empty($user_profile[$memID]['additional_groups']))
	{
		$add_groups = @explode(',', $user_profile[$memID]['additional_groups']);
		foreach ($add_groups as $group)
			$groups[] = (int) $group;
	}

	// Remove any invalid ones.
	foreach ($context['subscriptions'] as $id => $sub)
	{
		// Work out the costs.
		$costs = @unserialize($sub['real_cost']);

		$cost_array = array();
		if ($sub['real_length'] == 'F')
		{
			foreach ($costs as $duration => $cost)
			{
				if ($cost != 0)
					$cost_array[$duration] = $cost;
			}
		}
		else
		{
			$cost_array['fixed'] = $costs['fixed'];
		}

		if (empty($cost_array))
			unset($context['subscriptions'][$id]);
		else
		{
			$context['subscriptions'][$id]['member'] = 0;
			$context['subscriptions'][$id]['subscribed'] = false;
			$context['subscriptions'][$id]['costs'] = $cost_array;
		}

		// If the user's list of groups doesn't include the relevant subscription's
		// groups, kick 'em out if they're not special, or warn them if they are.
		if (empty($sub['allowed_groups']) || !array_intersect($sub['allowed_groups'], $groups))
		{
			if ($has_override)
				$context['subscriptions'][$id]['group_warning'] = true;
			else
				unset($context['subscriptions'][$id]);
		}
	}

	// Work out what gateways are enabled.
	$gateways = loadPaymentGateways();
	foreach ($gateways as $id => $gateway)
	{
		$gateways[$id] = new $gateway['display_class']();
		if (!$gateways[$id]->gatewayEnabled())
			unset($gateways[$id]);
	}

	// No gateways yet?
	if (empty($gateways))
		fatal_lang_error('paid_admin_not_setup_gateway');

	// Get the current subscriptions.
	$request = wesql::query('
		SELECT id_sublog, id_subscribe, start_time, end_time, status, payments_pending, pending_details
		FROM {db_prefix}log_subscribed
		WHERE id_member = {int:selected_member}',
		array(
			'selected_member' => $memID,
		)
	);
	$context['current'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// The subscription must exist!
		if (!isset($context['subscriptions'][$row['id_subscribe']]))
			continue;

		$context['current'][$row['id_subscribe']] = array(
			'id' => $row['id_sublog'],
			'sub_id' => $row['id_subscribe'],
			'hide' => $row['status'] == 0 && $row['end_time'] == 0 && $row['payments_pending'] == 0,
			'name' => $context['subscriptions'][$row['id_subscribe']]['name'],
			'start' => timeformat($row['start_time'], false),
			'end' => $row['end_time'] == 0 ? $txt['not_applicable'] : ($row['end_time'] == 1 ? $txt['paid_mod_span_lifetime_expires'] : timeformat($row['end_time'], false)),
			'pending_details' => $row['pending_details'],
			'status' => $row['status'],
			'status_text' => $row['status'] == 0 ? ($row['payments_pending'] ? $txt['paid_pending'] : $txt['paid_finished']) : $txt['paid_active'],
		);

		if ($row['status'] == 1)
			$context['subscriptions'][$row['id_subscribe']]['subscribed'] = true;
	}
	wesql::free_result($request);

	// Simple "done"?
	if (isset($_GET['done']))
	{
		$_GET['sub_id'] = (int) $_GET['sub_id'];

		// Must exist but let's be sure...
		if (isset($context['current'][$_GET['sub_id']]))
		{
			// What are the details like?
			$current_pending = @unserialize($context['current'][$_GET['sub_id']]['pending_details']);
			if (!empty($current_pending))
			{
				$current_pending = array_reverse($current_pending);
				foreach ($current_pending as $id => $sub)
				{
					// Just find one and change it.
					if ($sub[0] == $_GET['sub_id'] && $sub[3] == 'prepay')
					{
						$current_pending[$id][3] = 'payback';
						break;
					}
				}

				// Save the details back.
				$pending_details = serialize($current_pending);

				wesql::query('
					UPDATE {db_prefix}log_subscribed
					SET payments_pending = payments_pending + 1, pending_details = {string:pending_details}
					WHERE id_sublog = {int:current_subscription_id}
						AND id_member = {int:selected_member}',
					array(
						'current_subscription_id' => $context['current'][$_GET['sub_id']]['id'],
						'selected_member' => $memID,
						'pending_details' => $pending_details,
					)
				);
			}
		}

		wetem::load('paid_done');
		return;
	}
	// If this is confirmation then it's simpler...
	if (isset($_GET['confirm'], $_POST['sub_id']) && is_array($_POST['sub_id']))
	{
		// Hopefully just one.
		foreach ($_POST['sub_id'] as $k => $v)
			$id_sub = (int) $k;

		if (!isset($context['subscriptions'][$id_sub]) || $context['subscriptions'][$id_sub]['active'] == 0)
			fatal_lang_error('paid_sub_not_active');

		// Simplify...
		$context['sub'] = $context['subscriptions'][$id_sub];
		$period = 'xx';
		if ($context['sub']['flexible'])
			$period = isset($_POST['cur'][$id_sub], $context['sub']['costs'][$_POST['cur'][$id_sub]]) ? $_POST['cur'][$id_sub] : 'xx';

		// Check we have a valid cost.
		if ($context['sub']['flexible'] && $period == 'xx')
			fatal_lang_error('paid_sub_not_active');

		// Sort out the cost/currency.
		$context['currency'] = $settings['paid_currency_code'];
		$context['recur'] = $context['sub']['repeatable'];

		if ($context['sub']['flexible'])
		{
			// Real cost...
			$context['value'] = $context['sub']['costs'][$_POST['cur'][$id_sub]];
			$context['cost'] = sprintf($settings['paid_currency_symbol'], $context['value']) . '/' . $txt[$_POST['cur'][$id_sub]];
			// The period value for paypal.
			$context['paypal_period'] = strtoupper(substr($_POST['cur'][$id_sub], 0, 1));
		}
		else
		{
			// Real cost...
			$context['value'] = $context['sub']['costs']['fixed'];
			$context['cost'] = sprintf($settings['paid_currency_symbol'], $context['value']);

			// Recur?
			if ($context['sub']['real_length'] != 'LT')
			{
				preg_match('~(\d*)(\w)~', $context['sub']['real_length'], $match);
				$context['paypal_unit'] = $match[1];
				$context['paypal_period'] = $match[2];
			}
		}

		// Setup the gateway context.
		$context['gateways'] = array();
		foreach ($gateways as $id => $gateway)
		{
			$fields = $gateways[$id]->fetchGatewayFields($context['sub']['id'] . '+' . $memID, $context['sub'], $context['value'], $period, $scripturl . '?action=profile;u=' . $memID . ';area=subscriptions;sub_id=' . $context['sub']['id'] . ';done');
			if (!empty($fields['form']))
				$context['gateways'][] = $fields;
			if (!empty($fields['javascript']))
				add_js($fields['javascript']);
		}

		// Bugger?!
		if (empty($context['gateways']))
			fatal_lang_error('paid_admin_not_setup_gateway');

		// Now we are going to assume they want to take this out ;)
		$new_data = array($context['sub']['id'], $context['value'], $period, 'prepay');
		if (isset($context['current'][$context['sub']['id']]))
		{
			// What are the details like?
			$current_pending = array();
			if ($context['current'][$context['sub']['id']]['pending_details'] != '')
				$current_pending = @unserialize($context['current'][$context['sub']['id']]['pending_details']);
			// Don't get silly.
			if (count($current_pending) > 9)
				$current_pending = array();
			$pending_count = 0;
			// Only record real pending payments as will otherwise confuse the admin!
			foreach ($current_pending as $pending)
				if ($pending[3] == 'payback')
					$pending_count++;

			if (!in_array($new_data, $current_pending))
			{
				$current_pending[] = $new_data;
				$pending_details = serialize($current_pending);

				wesql::query('
					UPDATE {db_prefix}log_subscribed
					SET payments_pending = {int:pending_count}, pending_details = {string:pending_details}
					WHERE id_sublog = {int:current_subscription_item}
						AND id_member = {int:selected_member}',
					array(
						'pending_count' => $pending_count,
						'current_subscription_item' => $context['current'][$context['sub']['id']]['id'],
						'selected_member' => $memID,
						'pending_details' => $pending_details,
					)
				);
			}

		}
		// Never had this before, lovely.
		else
		{
			$pending_details = serialize(array($new_data));
			wesql::insert('',
				'{db_prefix}log_subscribed',
				array(
					'id_subscribe' => 'int', 'id_member' => 'int', 'status' => 'int', 'payments_pending' => 'int', 'pending_details' => 'string-65534',
					'start_time' => 'int', 'vendor_ref' => 'string-255',
				),
				array(
					$context['sub']['id'], $memID, 0, 0, $pending_details,
					time(), '',
				),
				array('id_sublog')
			);
		}

		// Change the template.
		wetem::load('choose_payment');

		// Quit.
		return;
	}
	else
		wetem::load('user_subscription');
}
