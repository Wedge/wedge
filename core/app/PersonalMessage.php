<?php
/**
 * Handles virtually all aspects of PMs (personal messages):
 * viewing, sending, deleting and marking them.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	For compatibility reasons, PMs may be called "instant messages".
	The following functions are used:

	void PersonalMessage()
		// !!! ?action=pm

	void messageIndexBar(string area)
		// !!!

	void MessageFolder()
		// !!! ?action=pm;sa=folder

	void prepareMessageContext(type reset = 'subject', bool reset = false)
		// !!!

	void MessageSearch()
		// !!!

	void MessageSearch2()
		// !!!

	void MessagePost()
		// !!! ?action=pm;sa=post

	void messagePostError(array error_types, array named_recipients, array recipient_ids)
		// !!!

	void MessagePost2()
		// !!! ?action=pm;sa=post2

	void MessageActionsApply()
		// !!! ?action=pm;sa=pmactions

	void MessageKillAllQuery()
		// !!! ?action=pm;sa=killall

	void MessageKillAll()
		// !!! ?action=pm;sa=killall2

	void MessagePrune()
		// !!! ?action=pm;sa=prune

	void deleteMessages(array personal_messages, string folder,
			int owner = user)
		// !!!

	void markMessages(array personal_messages = all, int label = all,
			int owner = user)
		- marks the specified personal_messages read.
		- if label is set, only marks messages with that label.
		- if owner is set, marks messages owned by that member id.

	void ManageLabels()
		// !!!

	void MessageSettings()
		// !!!

	void ReportMessage()
		- allows the user to report a personal message to an administrator.
		- in the first instance requires that the ID of the message to report
		  is passed through $_GET.
		- allows the user to report to either a particular administrator - or
		  the whole admin team.
		- will forward on a copy of the original message without allowing the
		  reporter to make changes.
		- uses the report_message block.

	void ManageRules()
		// !!!

	void loadRules()
		// !!!

	void applyRules()
		// !!!
*/

// This helps organize things...
function PersonalMessage()
{
	global $txt, $context, $user_settings, $settings;

	// No guests!
	is_not_guest();

	// You're not supposed to be here at all, if you can't even read PMs.
	isAllowedTo('pm_read');

	// This file contains the basic functions for sending a PM.
	loadSource('Subs-Post');

	// Are we saving a draft? Sadly if we are, we have to do it here and refix everything ourselves.
	if (isset($_REQUEST['draft']))
	{
		$session_timeout = checkSession('post', '', false) != '';
		$draft = saveDraft(true, isset($_REQUEST['replied_to']) ? (int) $_REQUEST['replied_to'] : 0);
		if (!empty($draft) && !$session_timeout)
		{
			if (!AJAX)
				redirectexit('action=pm;draftsaved');

			loadLanguage('Post');
			draftXmlReturn($draft, true);
		}
	}

	loadLanguage('PersonalMessage');
	loadTemplate('PersonalMessage');
	loadTemplate('Msg'); // For template_user_status

	// Now we have the labels, and assuming we have unsorted mail, apply our rules!
	if (!empty($user_settings['hey_pm']))
	{
		cache_put_data('labelCounts:' . MID, null, 720);
		$context['labels'] = empty(we::$user['data']['pmlabs']) ? array() : explode(',', we::$user['data']['pmlabs']);
		foreach ($context['labels'] as $id_label => $label_name)
			$context['labels'][(int) $id_label] = array(
				'id' => $id_label,
				'name' => trim($label_name),
				'messages' => 0,
				'unread_messages' => 0,
			);
		$context['labels'][-1] = array(
			'id' => -1,
			'name' => $txt['pm_msg_label_inbox'],
			'messages' => 0,
			'unread_messages' => 0,
		);

		applyRules();
		updateMemberData(MID, array('hey_pm' => 0));
		wesql::query('
			UPDATE {db_prefix}pm_recipients
			SET is_new = 0
			WHERE id_member = {int:current_member}',
			array(
				'current_member' => MID,
			)
		);
	}

	// Are we just getting AJAXy things?
	if (AJAX && isset($_GET['sa']) && $_GET['sa'] == 'ajax')
	{
		if (!isset($_GET['preview']))
		{
			// We're getting the list of unread PMs.
			$context['can_send'] = allowedTo('pm_send');
			$context['show_drafts'] = $context['can_send'] && allowedTo('save_pm_draft') && !empty($settings['masterSavePmDrafts']);
			wetem::hide();
			wetem::load('pm_popup');

			// Now we get the list of PMs to deal with.
			$request = wesql::query('
				SELECT id_pm
				FROM {db_prefix}pm_recipients
				WHERE id_member = {int:current_member}
					AND is_read = {int:new}
				ORDER BY id_pm DESC',
				array(
					'current_member' => MID,
					'new' => 0,
				)
			);
			$context['personal_messages'] = array();
			while ($row = wesql::fetch_row($request))
				$context['personal_messages'][$row[0]] = array();
			wesql::free_result($request);

			// For reasons not entirely clear, this could sometimes get out of sync.
			if (count($context['personal_messages']) != we::$user['unread_messages'])
				updateMemberData(
					MID,
					array(
						'unread_messages' => count($context['personal_messages']),
					)
				);

			// OK so we have some ids, now fetch the message details.
			if (!empty($context['personal_messages']))
			{
				$senders = array();

				$request = wesql::query('
					SELECT pm.id_pm, pm.id_pm_head, IFNULL(mem.id_member, pm.id_member_from) AS id_member_from,
						IFNULL(mem.real_name, pm.from_name) AS member_from, pm.msgtime, pm.subject
					FROM {db_prefix}personal_messages AS pm
						LEFT JOIN {db_prefix}members AS mem ON (pm.id_member_from = mem.id_member)
					WHERE pm.id_pm IN ({array_int:id_pms})',
					array(
						'id_pms' => array_keys($context['personal_messages']),
					)
				);
				while ($row = wesql::fetch_assoc($request))
				{
					if (!empty($row['id_member_from']))
						$senders[] = $row['id_member_from'];
					$row['sprintf'] = $row['id_pm'] == $row['id_pm_head'] ? 'pm_sent_to_you' : 'pm_replied_to_pm';
					$row['member_link'] = !empty($row['id_member_from']) ? '<a href="<URL>?action=profile;u=' . $row['id_member_from'] . '">' . $row['member_from'] . '</a>' : $row['member_from'];
					$row['msg_link'] = '<a href="<URL>?action=pm;f=inbox;pmsg=' . $row['id_pm'] . '">' . $row['subject'] . '</a>';
					$context['personal_messages'][$row['id_pm']] = $row;
				}
				wesql::free_result($request);

				if (!empty($senders))
				{
					$loaded = loadMemberData($senders);
					foreach ($loaded as $member)
						loadMemberAvatar($member, true);
				}
			}

			return;
		}
		else
		{
			// We're getting a specific preview.
			$pmsg = (int) $_GET['preview'];
			if (!isAccessiblePM($pmsg, isset($_REQUEST['f']) && $_REQUEST['f'] == 'sent' ? 'outbox' : 'inbox'))
			{
				mark_ajax_pm_read($pmsg);
				return_raw($context['header'] . $txt['pm_not_found']);
			}
			$request = wesql::query('
				SELECT body
				FROM {db_prefix}personal_messages
				WHERE id_pm = {int:pm}',
				array(
					'pm' => $pmsg,
				)
			);
			list ($body) = wesql::fetch_row($request);
			wesql::free_result($request);

			return_raw($context['header'] . parse_bbc($body, 'pm', array('cache' => 'pm' . $pmsg)));
		}
	}

	// Load up the members maximum message capacity.
	if (we::$is_admin)
		$context['message_limit'] = 0;
	elseif (($context['message_limit'] = cache_get_data('msgLimit:' . MID, 360)) === null)
	{
		// !!! Why do we do this? It seems like if they have any limit we should use it.
		$request = wesql::query('
			SELECT MAX(max_messages) AS top_limit, MIN(max_messages) AS bottom_limit
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:users_groups})',
			array(
				'users_groups' => we::$user['groups'],
			)
		);
		list ($maxMessage, $minMessage) = wesql::fetch_row($request);
		wesql::free_result($request);

		$context['message_limit'] = $minMessage == 0 ? 0 : $maxMessage;

		// Save us doing it again!
		cache_put_data('msgLimit:' . MID, $context['message_limit'], 360);
	}

	// Prepare the context for the capacity bar.
	if (!empty($context['message_limit']))
	{
		$bar = (we::$user['messages'] * 100) / $context['message_limit'];

		$context['limit_bar'] = array(
			'messages' => we::$user['messages'],
			'allowed' => $context['message_limit'],
			'percent' => $bar,
			'bar' => min(100, (int) $bar),
			'text' => sprintf($txt['pm_currently_using'], we::$user['messages'], round($bar, 1)),
		);
	}

	// a previous message was sent successfully? show a small indication.
	if (isset($_GET['done']) && ($_GET['done'] == 'sent'))
		$context['pm_sent'] = true;

	// Did someone save a conventional draft?
	if ($context['draft_saved'] = isset($_GET['draftsaved']))
		loadLanguage('Post');

	// Load the label data.
	$context['labels'] = cache_get_data('labelCounts:' . MID, 720, function ()
	{
		global $txt;

		$labels = empty(we::$user['data']['pmlabs']) ? array() : explode(',', we::$user['data']['pmlabs']);
		foreach ($labels as $id_label => $label_name)
			$labels[(int) $id_label] = array(
				'id' => $id_label,
				'name' => trim($label_name),
				'messages' => 0,
				'unread_messages' => 0,
			);
		$labels[-1] = array(
			'id' => -1,
			'name' => $txt['pm_msg_label_inbox'],
			'messages' => 0,
			'unread_messages' => 0,
		);

		// Looks like we need to reseek!
		$result = wesql::query('
			SELECT labels, is_read, COUNT(*) AS num
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:current_member}
				AND deleted = {int:not_deleted}
			GROUP BY labels, is_read',
			array(
				'current_member' => MID,
				'not_deleted' => 0,
			)
		);
		while ($row = wesql::fetch_assoc($result))
		{
			$this_labels = explode(',', $row['labels']);
			foreach ($this_labels as $this_label)
			{
				$labels[(int) $this_label]['messages'] += $row['num'];
				if (!($row['is_read'] & 1))
					$labels[(int) $this_label]['unread_messages'] += $row['num'];
			}
		}
		wesql::free_result($result);

		// Store it please!
		return $labels;
	});

	// This determines if we have more labels than just the standard inbox.
	$context['currently_using_labels'] = count($context['labels']) > 1 ? 1 : 0;

	// Some stuff for the labels...
	$context['current_label_id'] = isset($_REQUEST['l'], $context['labels'][(int) $_REQUEST['l']]) ? (int) $_REQUEST['l'] : -1;
	$context['current_label'] =& $context['labels'][(int) $context['current_label_id']]['name'];
	$context['folder'] = !isset($_REQUEST['f']) || $_REQUEST['f'] != 'sent' ? 'inbox' : 'sent';

	// This is convenient. Do you know how annoying it is to do this every time?!
	$context['current_label_redirect'] = 'action=pm;f=' . $context['folder'] . (isset($_GET['start']) ? ';start=' . $_GET['start'] : '') . (isset($_REQUEST['l']) ? ';l=' . $_REQUEST['l'] : '');
	$context['can_issue_warning'] = allowedTo('issue_warning');

	// Build the linktree for all the actions...
	add_linktree($txt['personal_messages'], '<URL>?action=pm');

	// Preferences...
	$context['display_mode'] = $user_settings['pm_prefs'] & 3;
	if ($context['folder'] == 'sent')
		$context['display_mode'] = 0;

	$subActions = array(
		'manlabels' => 'ManageLabels',
		'manrules' => 'ManageRules',
		'markunread' => 'MarkUnread',
		'pmactions' => 'MessageActionsApply',
		'prune' => 'MessagePrune',
		'removeall' => 'MessageKillAllQuery',
		'removeall2' => 'MessageKillAll',
		'report' => 'ReportMessage',
		'search' => 'MessageSearch',
		'search2' => 'MessageSearch2',
		'send' => 'MessagePost',
		'send2' => 'MessagePost2',
		'settings' => 'MessageSettings',
	);

	if (allowedTo('save_pm_draft'))
		$subActions['showdrafts'] = 'MessageDrafts';

	if (isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]))
	{
		messageIndexBar($_REQUEST['sa']);
		$subActions[$_REQUEST['sa']]();
	}
	else
	{
		// It didn't exist, or it wasn't valid. Either way is fine with us.
		unset($_REQUEST['sa']);
		MessageFolder();
	}
}

// This is only used on PM preview errors, as PMs normally require an action.
function mark_ajax_pm_read($pmsg)
{
	wesql::query('
		UPDATE {db_prefix}pm_recipients
		SET is_read = 1
		WHERE id_pm = {int:pm}
			AND id_member = {int:member}
			AND is_read = 0',
		array(
			'pm' => $pmsg,
			'member' => MID,
		)
	);
	$request = wesql::query('
		SELECT COUNT(id_pm)
		FROM {db_prefix}pm_recipients
		WHERE id_member = {int:current_member}
			AND is_read = {int:new}
		ORDER BY id_pm',
		array(
			'current_member' => MID,
			'new' => 0,
		)
	);
	list ($count) = wesql::fetch_row($request);
	wesql::free_result($request);

	updateMemberData(MID, array('unread_messages' => $count));

	// And next time we actually enter the inbox, this needs to be recalculated.
	cache_put_data('labelCounts:' . MID, null);
}

// A sidebar to easily access different areas of the section
function messageIndexBar($area)
{
	global $txt, $context;

	$pm_areas = array(
		'folders' => array(
			'title' => $txt['pm_messages'],
			'areas' => array(
				'send' => array(
					'label' => $txt['new_message'],
					'custom_url' => '<URL>?action=pm;sa=send',
					'permission' => allowedTo('pm_send'),
				),
				'',
				'inbox' => array(
					'label' => $txt['inbox'],
					'notice' => '',
					'custom_url' => '<URL>?action=pm',
				),
				'sent' => array(
					'label' => $txt['sent_items'],
					'custom_url' => '<URL>?action=pm;f=sent',
				),
				'',
				'showdrafts' => array(
					'label' => $txt['pm_menu_drafts'],
					'notice' => '',
					'custom_url' => '<URL>?action=pm;sa=showdrafts',
					'permission' => allowedTo('save_pm_draft'),
				),
			),
		),
		'labels' => array(
			'title' => $txt['pm_labels'],
			'areas' => array(),
		),
		'actions' => array(
			'title' => $txt['pm_actions'],
			'areas' => array(
				'search' => array(
					'label' => $txt['pm_search_bar_title'],
					'custom_url' => '<URL>?action=pm;sa=search',
				),
				'prune' => array(
					'label' => $txt['pm_prune'],
					'custom_url' => '<URL>?action=pm;sa=prune'
				),
			),
		),
		'pref' => array(
			'title' => $txt['pm_preferences'],
			'areas' => array(
				'manlabels' => array(
					'label' => $txt['pm_manage_labels'],
					'custom_url' => '<URL>?action=pm;sa=manlabels',
				),
				'manrules' => array(
					'label' => $txt['pm_manage_rules'],
					'custom_url' => '<URL>?action=pm;sa=manrules',
				),
				'',
				'settings' => array(
					'label' => $txt['pm_settings'],
					'custom_url' => '<URL>?action=pm;sa=settings',
				),
			),
		),
	);

	// Handle labels.
	if (empty($context['currently_using_labels']))
		unset($pm_areas['labels']);
	else
	{
		// Note we send labels by id as it will have less problems in the querystring.
		$unread_in_labels = 0;
		foreach ($context['labels'] as $label)
		{
			if ($label['id'] == -1)
				continue;

			// Count the amount of unread items in labels.
			$unread_in_labels += $label['unread_messages'];

			// Add the label to the menu.
			$pm_areas['labels']['areas']['label' . $label['id']] = array(
				'label' => $label['name'] . (!empty($label['unread_messages']) ? ' <span class="note">' . $label['unread_messages'] . '</span>' : ''),
				'custom_url' => '<URL>?action=pm;l=' . $label['id'],
				'unread_messages' => $label['unread_messages'],
				'messages' => $label['messages'],
			);
		}

		if (!empty($unread_in_labels))
			$pm_areas['labels']['title'] .= ' <span class="notewarn">' . $unread_in_labels . '</span>';
	}

	$pm_areas['folders']['areas']['inbox']['unread_messages'] =& $context['labels'][-1]['unread_messages'];
	$pm_areas['folders']['areas']['inbox']['messages'] =& $context['labels'][-1]['messages'];
	if (!empty($context['labels'][-1]['unread_messages']))
	{
		$pm_areas['folders']['areas']['inbox']['label'] .= ' <span class="note">' . $context['labels'][-1]['unread_messages'] . '</span>';
		$pm_areas['folders']['title'] .= ' <span class="notewarn">' . $context['labels'][-1]['unread_messages'] . '</span>';
	}

	// Do we have a limit on the amount of messages we can keep?
	if (!empty($context['message_limit']))
	{
		$bar = round((we::$user['messages'] * 100) / $context['message_limit'], 1);

		$context['limit_bar'] = array(
			'messages' => we::$user['messages'],
			'allowed' => $context['message_limit'],
			'percent' => $bar,
			'bar' => $bar > 100 ? 100 : (int) $bar,
			'text' => sprintf($txt['pm_currently_using'], we::$user['messages'], $bar)
		);
	}

	loadSource('Subs-Menu');

	// Set a few options for the menu.
	$menuOptions = array(
		'current_area' => $area,
		'disable_url_session_check' => true,
	);

	// Actually create the menu!
	$pm_include_data = createMenu($pm_areas, $menuOptions);
	unset($pm_areas);

	// Make a note of the unique ID for this menu.
	$context['pm_menu_id'] = $context['max_menu_id'];
	$context['pm_menu_name'] = 'menu_data_' . $context['pm_menu_id'];

	// Set the selected item.
	$context['menu_item_selected'] = $pm_include_data['current_area'];

	// obExit will know what to do!
	wetem::outer('pm');
}

// A folder, ie. inbox/sent etc.
function MessageFolder()
{
	global $txt, $settings, $context, $subjects_request;
	global $messages_request, $recipients, $options, $user_settings;

	// Changing view? 0 = all at once, 1 = one at a time, 2 = conversation mode
	if (isset($_GET['view']))
	{
		$view = (int) $_GET['view'];
		if ($view >= 0 && $view <= 2)
		{
			$context['display_mode'] = $view;
			updateMemberData(MID, array('pm_prefs' => ($user_settings['pm_prefs'] & 252) | $context['display_mode']));
		}
	}
	$context['view_select_types'] = array(
		0 => $txt['pm_display_mode_all'],
		1 => $txt['pm_display_mode_one'],
		2 => $txt['pm_display_mode_linked'],
	);

	// OK, so can we potentially mark unread?
	$context['can_unread'] = $context['display_mode'] != 0; // can't mark unread in all-at-once mode

	// Make sure the starting location is valid.
	if (isset($_GET['start']) && $_GET['start'] != 'new')
		$_GET['start'] = (int) $_GET['start'];
	elseif (!isset($_GET['start']) && !empty($options['view_newest_pm_first']))
		$_GET['start'] = 0;
	else
		$_GET['start'] = 'new';

	// Set up some basic theme stuff.
	$context['from_or_to'] = $context['folder'] != 'sent' ? 'from' : 'to';
	$context['get_pmessage'] = 'prepareMessageContext';
	$context['signature_enabled'] = $settings['signature_settings'][0] == 1;
	$context['disabled_fields'] = isset($settings['disabled_profile_fields']) ? array_flip(explode(',', $settings['disabled_profile_fields'])) : array();

	$labelQuery = $context['folder'] != 'sent' ? '
			AND FIND_IN_SET(' . $context['current_label_id'] . ', pmr.labels) != 0' : '';

	// Set the index bar correct!
	messageIndexBar($context['current_label_id'] == -1 ? $context['folder'] : 'label' . $context['current_label_id']);

	// Sorting the folder.
	$sort_methods = array(
		'date' => 'pm.id_pm',
		'name' => 'IFNULL(mem.real_name, \'\')',
		'subject' => 'pm.subject',
	);

	// They didn't pick one, use the forum default.
	if (!isset($_GET['sort']) || !isset($sort_methods[$_GET['sort']]))
	{
		$context['sort_by'] = 'date';
		$_GET['sort'] = 'pm.id_pm';
		// An overriding setting?
		$descending = !empty($options['view_newest_pm_first']);
	}
	// Otherwise use the defaults: ascending, by date.
	else
	{
		$context['sort_by'] = $_GET['sort'];
		$_GET['sort'] = $sort_methods[$_GET['sort']];
		$descending = isset($_GET['desc']);
	}

	$context['sort_direction'] = $descending ? 'down' : 'up';

	// Set the text to resemble the current folder.
	$pmbox = $context['folder'] != 'sent' ? $txt['inbox'] : $txt['sent_items'];
	$txt['delete_all'] = str_replace('PMBOX', $pmbox, $txt['delete_all']);

	// Now, build the link tree!
	if ($context['current_label_id'] == -1)
		add_linktree($pmbox, '<URL>?action=pm;f=' . $context['folder']);

	// Build it further for a label.
	if ($context['current_label_id'] != -1)
		add_linktree($txt['pm_current_label'] . ': ' . $context['current_label'], '<URL>?action=pm;f=' . $context['folder'] . ';l=' . $context['current_label_id']);

	// Figure out how many messages there are.
	if ($context['folder'] == 'sent')
		$request = wesql::query('
			SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
			FROM {db_prefix}personal_messages AS pm
			WHERE pm.id_member_from = {int:current_member}
				AND pm.deleted_by_sender = {int:not_deleted}',
			array(
				'current_member' => MID,
				'not_deleted' => 0,
			)
		);
	else
		$request = wesql::query('
			SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
			FROM {db_prefix}pm_recipients AS pmr' . ($context['display_mode'] == 2 ? '
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)' : '') . '
			WHERE pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}' . $labelQuery,
			array(
				'current_member' => MID,
				'not_deleted' => 0,
			)
		);
	list ($max_messages) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Why would you want to access an empty outbox if you're not allowed to send anything anyway?
	if ($context['folder'] == 'sent' && $max_messages == 0)
		isAllowedTo('pm_send');

	// Only show the button if there are messages to delete.
	$context['show_delete'] = $max_messages > 0;

	// Start on the last page.
	if (!is_numeric($_GET['start']) || $_GET['start'] >= $max_messages)
		$_GET['start'] = ($max_messages - 1) - (($max_messages - 1) % $settings['defaultMaxMessages']);
	elseif ($_GET['start'] < 0)
		$_GET['start'] = 0;

	// ... but wait - what if we want to start from a specific message?
	if (isset($_GET['pmid']))
	{
		$pmID = (int) $_GET['pmid'];

		// Make sure you have access to this PM.
		if (!isAccessiblePM($pmID, $context['folder'] == 'sent' ? 'outbox' : 'inbox'))
			fatal_lang_error('no_access', false);

		$context['current_pm'] = $pmID;

		// With only one page of PM's we're gonna want page 1.
		if ($max_messages <= $settings['defaultMaxMessages'])
			$_GET['start'] = 0;

		// If we pass kstart we assume we're in the right place.
		elseif (!isset($_GET['kstart']))
		{
			if ($context['folder'] == 'sent')
				$request = wesql::query('
					SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
					FROM {db_prefix}personal_messages
					WHERE id_member_from = {int:current_member}
						AND deleted_by_sender = {int:not_deleted}
						AND id_pm ' . ($descending ? '>' : '<') . ' {int:id_pm}',
					array(
						'current_member' => MID,
						'not_deleted' => 0,
						'id_pm' => $pmID,
					)
				);
			else
				$request = wesql::query('
					SELECT COUNT(' . ($context['display_mode'] == 2 ? 'DISTINCT pm.id_pm_head' : '*') . ')
					FROM {db_prefix}pm_recipients AS pmr' . ($context['display_mode'] == 2 ? '
						INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)' : '') . '
					WHERE pmr.id_member = {int:current_member}
						AND pmr.deleted = {int:not_deleted}' . $labelQuery . '
						AND pmr.id_pm ' . ($descending ? '>' : '<') . ' {int:id_pm}',
					array(
						'current_member' => MID,
						'not_deleted' => 0,
						'id_pm' => $pmID,
					)
				);

			list ($_GET['start']) = wesql::fetch_row($request);
			wesql::free_result($request);

			// To stop the page index's being abnormal, start the page on the page the message would normally be located on...
			$_GET['start'] = $settings['defaultMaxMessages'] * (int) ($_GET['start'] / $settings['defaultMaxMessages']);
		}
	}

	// Sanitize and validate pmsg variable if set.
	if (isset($_GET['pmsg']))
	{
		$pmsg = (int) $_GET['pmsg'];

		if (!isAccessiblePM($pmsg, $context['folder'] == 'sent' ? 'outbox' : 'inbox'))
			fatal_lang_error('no_access', false);
	}

	// Set up the page index.
	$context['page_index'] = template_page_index('<URL>?action=pm;f=' . $context['folder'] . (isset($_REQUEST['l']) ? ';l=' . (int) $_REQUEST['l'] : '') . ';sort=' . $context['sort_by'] . ($descending ? ';desc' : ''), $_GET['start'], $max_messages, $settings['defaultMaxMessages']);
	$context['start'] = $_GET['start'];

	// Determine the navigation context.
	$context['links'] = array(
		'prev' => $_GET['start'] >= $settings['defaultMaxMessages'] ? '<URL>?action=pm;start=' . ($_GET['start'] - $settings['defaultMaxMessages']) : '',
		'next' => $_GET['start'] + $settings['defaultMaxMessages'] < $max_messages ? '<URL>?action=pm;start=' . ($_GET['start'] + $settings['defaultMaxMessages']) : '',
	);

	// First work out what messages we need to see - if grouped is a little trickier...
	if ($context['display_mode'] == 2)
	{
		$request = wesql::query('
			SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? ($context['sort_by'] == 'name' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
					AND pmr.id_member = {int:current_member}
					AND pmr.deleted = {int:deleted_by}
					' . $labelQuery . ')') . ($context['sort_by'] == 'name' ? ('
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})') : '') . '
			WHERE ' . ($context['folder'] == 'sent' ? 'pm.id_member_from = {int:current_member}
				AND pm.deleted_by_sender = {int:deleted_by}' : '1=1') . (empty($pmsg) ? '' : '
				AND pm.id_pm = {int:pmsg}') . '
			GROUP BY pm.id_pm_head
			ORDER BY ' . ($_GET['sort'] == 'pm.id_pm' && $context['folder'] != 'sent' ? 'id_pm' : '{raw:sort}') . ($descending ? ' DESC' : ' ASC') . (empty($_GET['pmsg']) ? '
			LIMIT ' . $_GET['start'] . ', ' . $settings['defaultMaxMessages'] : ''),
			array(
				'current_member' => MID,
				'deleted_by' => 0,
				'sort' => $_GET['sort'],
				'pm_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
				'pmsg' => isset($pmsg) ? (int) $pmsg : 0,
			)
		);
	}
	// This is kinda simple!
	else
	{
		// !!! SLOW This query uses a filesort. (Inbox only.)
		$request = wesql::query('
			SELECT pm.id_pm, pm.id_pm_head, pm.id_member_from
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '' . ($context['sort_by'] == 'name' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm
					AND pmr.id_member = {int:current_member}
					AND pmr.deleted = {int:is_deleted}
					' . $labelQuery . ')') . ($context['sort_by'] == 'name' ? ('
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:pm_member})') : '') . '
			WHERE ' . ($context['folder'] == 'sent' ? 'pm.id_member_from = {raw:current_member}
				AND pm.deleted_by_sender = {int:is_deleted}' : '1=1') . (empty($pmsg) ? '' : '
				AND pm.id_pm = {int:pmsg}') . '
			ORDER BY ' . ($_GET['sort'] == 'pm.id_pm' && $context['folder'] != 'sent' ? 'pmr.id_pm' : '{raw:sort}') . ($descending ? ' DESC' : ' ASC') . (empty($pmsg) ? '
			LIMIT ' . $_GET['start'] . ', ' . $settings['defaultMaxMessages'] : ''),
			array(
				'current_member' => MID,
				'is_deleted' => 0,
				'sort' => $_GET['sort'],
				'pm_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
				'pmsg' => isset($pmsg) ? (int) $pmsg : 0,
			)
		);
	}
	// Load the id_pms and initialize recipients.
	$pms = array();
	$lastData = array();
	$posters = $context['folder'] == 'sent' ? array(MID) : array();
	$recipients = array();

	while ($row = wesql::fetch_assoc($request))
	{
		if (!isset($recipients[$row['id_pm']]))
		{
			if (isset($row['id_member_from']))
				$posters[$row['id_pm']] = $row['id_member_from'];
			$pms[$row['id_pm']] = $row['id_pm'];
			$recipients[$row['id_pm']] = array(
				'to' => array(),
				'bcc' => array()
			);
		}

		// Keep track of the last message so we know what the head is without another query!
		if ((empty($pmID) && (empty($options['view_newest_pm_first']) || !isset($lastData))) || empty($lastData) || (!empty($pmID) && $pmID == $row['id_pm']))
			$lastData = array(
				'id' => $row['id_pm'],
				'head' => $row['id_pm_head'],
			);
	}
	wesql::free_result($request);

	// Make sure that we have been given a correct head pm id!
	if ($context['display_mode'] == 2 && !empty($pmID) && $pmID != $lastData['id'])
		fatal_lang_error('no_access', false);

	if (!empty($pms))
	{
		// Select the correct current message.
		if (empty($pmID))
			$context['current_pm'] = $lastData['id'];

		// This is a list of the pm's that are used for "full" display.
		if ($context['display_mode'] == 0)
			$display_pms = $pms;
		else
			$display_pms = array($context['current_pm']);

		// At this point we know the main id_pm's. But - if we are looking at conversations we need the others!
		if ($context['display_mode'] == 2)
		{
			$request = wesql::query('
				SELECT pm.id_pm, pm.id_member_from, pm.deleted_by_sender, pmr.id_member, pmr.deleted
				FROM {db_prefix}personal_messages AS pm
					INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				WHERE pm.id_pm_head = {int:id_pm_head}
					AND ((pm.id_member_from = {int:current_member} AND pm.deleted_by_sender = {int:not_deleted})
						OR (pmr.id_member = {int:current_member} AND pmr.deleted = {int:not_deleted}))
				ORDER BY pm.id_pm',
				array(
					'current_member' => MID,
					'id_pm_head' => $lastData['head'],
					'not_deleted' => 0,
				)
			);
			while ($row = wesql::fetch_assoc($request))
			{
				// This is, frankly, a joke. We will put in a workaround for people sending to themselves - yawn!
				if ($context['folder'] == 'sent' && $row['id_member_from'] == MID && $row['deleted_by_sender'] == 1)
					continue;
				elseif ($row['id_member'] == MID & $row['deleted'] == 1)
					continue;

				if (!isset($recipients[$row['id_pm']]))
					$recipients[$row['id_pm']] = array(
						'to' => array(),
						'bcc' => array()
					);
				$display_pms[] = $row['id_pm'];
				$posters[$row['id_pm']] = $row['id_member_from'];
			}
			wesql::free_result($request);
		}

		// This is pretty much EVERY pm!
		$all_pms = array_merge($pms, $display_pms);
		$all_pms = array_unique($all_pms);

		// Get recipients (don't include bcc-recipients for your inbox, you're not supposed to know :P).
		$request = wesql::query('
			SELECT pmr.id_pm, mem_to.id_member AS id_member_to, mem_to.real_name AS to_name, pmr.bcc, pmr.labels, pmr.is_read
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $all_pms,
			)
		);
		$context['message_labels'] = array();
		$context['message_unread'] = array();
		$context['message_replied'] = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$id =& $row['id_pm'];
			if ($context['folder'] == 'sent' || empty($row['bcc']))
				$recipients[$id][empty($row['bcc']) ? 'to' : 'bcc'][] = empty($row['id_member_to']) ? $txt['guest_title'] : '<a href="<URL>?action=profile;u=' . $row['id_member_to'] . '">' . $row['to_name'] . '</a>';

			if ($context['folder'] == 'sent' && isset($posters[$id]) && $posters[$id] == MID)
				$context['message_replied'][$id] = (isset($context['message_replied'][$id]) ? $context['message_replied'][$id] : 0) + (($row['is_read'] & 2) >> 1);
			elseif (MID == $row['id_member_to'])
			{
				$context['message_replied'][$id] = $row['is_read'] & 2;
				$context['message_unread'][$id] = ($row['is_read'] & 1) == 0; // other bits can be used for other stuff but bit 0 (value 1) = message is read.
				$context['message_can_unread'][$id] = $context['can_unread'] && (!$context['message_unread'][$id] || (!empty($pmID) && $pmID == $id)); // so message is unread, or it's the one we're looking at which hasn't been marked read yet

				$row['labels'] = $row['labels'] == '' ? array() : explode(',', $row['labels']);
				foreach ($row['labels'] as $v)
				{
					if (isset($context['labels'][(int) $v]))
						$context['message_labels'][$id][(int) $v] = array('id' => $v, 'name' => $context['labels'][(int) $v]['name']);
				}
			}
		}
		wesql::free_result($request);

		// Make sure we don't load unnecessary data.
		if ($context['display_mode'] == 1)
			foreach ($posters as $k => $v)
				if (!in_array($k, $display_pms))
					unset($posters[$k]);

		// Load any users....
		$posters = array_unique($posters);
		if (!empty($posters))
			loadMemberData($posters, false, 'userbox');

		// If we're on grouped/restricted view get a restricted list of messages.
		if ($context['display_mode'] != 0)
		{
			// Get the order right.
			$orderBy = array();
			foreach (array_reverse($pms) as $pm)
				$orderBy[] = 'pm.id_pm = ' . $pm;

			// Separate query for these bits!
			$subjects_request = wesql::query('
				SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.msgtime, IFNULL(mem.real_name, pm.from_name) AS from_name,
					IFNULL(mem.id_member, 0) AS not_guest
				FROM {db_prefix}personal_messages AS pm
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
				WHERE pm.id_pm IN ({array_int:pm_list})
				ORDER BY ' . implode(', ', $orderBy) . '
				LIMIT ' . count($pms),
				array(
					'pm_list' => $pms,
				)
			);
		}

		// Execute the query!
		$messages_request = wesql::query('
			SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)' : '') . ($context['sort_by'] == 'name' ? '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = {raw:id_member})' : '') . '
			WHERE pm.id_pm IN ({array_int:display_pms})' . ($context['folder'] == 'sent' ? '
			GROUP BY pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name' : '') . '
			ORDER BY ' . ($context['display_mode'] == 2 ? 'pm.id_pm' : $_GET['sort']) . ($descending ? ' DESC' : ' ASC') . '
			LIMIT ' . count($display_pms),
			array(
				'display_pms' => $display_pms,
				'id_member' => $context['folder'] == 'sent' ? 'pmr.id_member' : 'pm.id_member_from',
			)
		);
	}
	else
		$messages_request = false;

	$context['can_send_pm'] = allowedTo('pm_send');
	if ($context['display_mode'] == 0)
		$context['page_title'] = $txt['pm_inbox'];
	else
		$context['page_title'] = '@replace:pm_convo@';

	wetem::load('folder');

	// Finally mark the relevant messages as read.
	if ($context['folder'] != 'sent' && !empty($context['labels'][(int) $context['current_label_id']]['unread_messages']))
	{
		// If the display mode is "old sk00l" do them all...
		if ($context['display_mode'] == 0)
			markMessages(null, $context['current_label_id']);
		// Otherwise do just the current one!
		elseif (!empty($context['current_pm']))
			markMessages($display_pms, $context['current_label_id']);
	}
}

// Get a personal message for the theme. (Used to save memory.)
function prepareMessageContext($type = 'subject', $reset = false)
{
	global $txt, $settings, $context, $messages_request, $memberContext, $recipients;
	global $subjects_request;

	// Count the current message number....
	static $counter = null, $last_subject = '', $temp_pm_selected = null;
	if ($counter === null || $reset)
		$counter = $context['start'];

	if ($temp_pm_selected === null)
	{
		$temp_pm_selected = isset($_SESSION['pm_selected']) ? $_SESSION['pm_selected'] : array();
		$_SESSION['pm_selected'] = array();
	}

	// Is this for the inbox list of subjects?
	if ($context['display_mode'] != 0 && $subjects_request && $type == 'subject')
	{
		$subject = wesql::fetch_assoc($subjects_request);
		if (!$subject)
		{
			wesql::free_result($subjects_request);
			return false;
		}

		$subject['subject'] = $subject['subject'] === '' ? $txt['no_subject'] : $subject['subject'];
		censorText($subject['subject']);

		$output = array(
			'id' => $subject['id_pm'],
			'member' => array(
				'id' => $subject['id_member_from'],
				'name' => $subject['from_name'],
				'link' => $subject['not_guest'] ? '<a href="<URL>?action=profile;u=' . $subject['id_member_from'] . '">' . $subject['from_name'] . '</a>' : $subject['from_name'],
			),
			'recipients' => &$recipients[$subject['id_pm']],
			'subject' => $subject['subject'],
			'on_time' => on_timeformat($subject['msgtime']),
			'timestamp' => $subject['msgtime'],
			'number_recipients' => count($recipients[$subject['id_pm']]['to']),
			'labels' => &$context['message_labels'][$subject['id_pm']],
			'fully_labeled' => count($context['message_labels'][$subject['id_pm']]) == count($context['labels']),
			'is_replied_to' => &$context['message_replied'][$subject['id_pm']],
			'is_unread' => &$context['message_unread'][$subject['id_pm']],
			'is_selected' => !empty($temp_pm_selected) && in_array($subject['id_pm'], $temp_pm_selected),
		);
		if ($output['is_replied_to'])
			$output['replied_msg'] = number_context('pm_is_replied_to_sent', $output['is_replied_to'], false);

		return $output;
	}

	// Bail if it's false, ie. no messages.
	if ($messages_request == false)
	{
		if ($context['display_mode'] != 0)
			add_replacement('@replace:pm_convo@', $txt['conversation']);
		return false;
	}

	// Reset the data?
	if ($reset == true)
		return @wesql::data_seek($messages_request, 0);

	// Get the next one... bail if anything goes wrong.
	$message = wesql::fetch_assoc($messages_request);
	if (!$message)
	{
		if ($type != 'subject')
			wesql::free_result($messages_request);

		if ($context['display_mode'] != 0)
			add_replacement('@replace:pm_convo@', $txt['conversation'] . ' - ' . $last_subject);
		return false;
	}

	// Use '(no subject)' if none was specified.
	$message['subject'] = $message['subject'] === '' ? $txt['no_subject'] : $message['subject'];

	// Load the message's information - if it's not there, load the guest information.
	if (!loadMemberContext($message['id_member_from'], true))
	{
		$memberContext[$message['id_member_from']]['name'] = $message['from_name'];
		$memberContext[$message['id_member_from']]['id'] = 0;
		// Sometimes the forum sends messages itself (Warnings are an example) - in this case don't label it from a guest.
		$memberContext[$message['id_member_from']]['group'] = $message['from_name'] == $context['forum_name'] ? '' : $txt['guest_title'];
		$memberContext[$message['id_member_from']]['link'] = $message['from_name'];
		$memberContext[$message['id_member_from']]['email'] = '';
		$memberContext[$message['id_member_from']]['show_email'] = showEmailAddress(true, 0);
		$memberContext[$message['id_member_from']]['is_guest'] = true;
	}
	else
	{
		$memberContext[$message['id_member_from']]['can_view_profile'] = allowedTo('profile_view_any') || ($message['id_member_from'] == MID && allowedTo('profile_view_own'));
		$memberContext[$message['id_member_from']]['can_see_warning'] = !empty($settings['warning_show']) && $memberContext[$message['id_member_from']]['warning_status'] && ($settings['warning_show'] == 3 || allowedTo('issue_warning') || ($settings['warning_show'] == 2 && $message['id_member_from'] == MID));
	}

	// Censor all the important text...
	censorText($message['body']);
	censorText($message['subject']);
	if (!empty($message['subject']))
		$last_subject = $message['subject'];

	// Run BBC interpreter on the message.
	$message['body'] = parse_bbc($message['body'], 'pm', array('cache' => 'pm' . $message['id_pm']));

	// Send the array.
	$output = array(
		'alternate' => $counter % 2,
		'id' => $message['id_pm'],
		'member' => &$memberContext[$message['id_member_from']],
		'subject' => $message['subject'],
		'on_time' => on_timeformat($message['msgtime']),
		'timestamp' => $message['msgtime'],
		'counter' => $counter,
		'body' => $message['body'],
		'recipients' => &$recipients[$message['id_pm']],
		'number_recipients' => count($recipients[$message['id_pm']]['to']),
		'labels' => &$context['message_labels'][$message['id_pm']],
		'fully_labeled' => count($context['message_labels'][$message['id_pm']]) == count($context['labels']),
		'is_replied_to' => &$context['message_replied'][$message['id_pm']],
		'is_unread' => &$context['message_unread'][$message['id_pm']],
		'is_selected' => !empty($temp_pm_selected) && in_array($message['id_pm'], $temp_pm_selected),
	);
	if ($output['is_replied_to'])
		$output['replied_msg'] = number_context('pm_is_replied_to_sent', $output['is_replied_to'], false);

	$counter++;

	return $output;
}

function MarkUnread()
{
	checkSession('get');

	$id_pm = isset($_GET['pmid']) ? (int) $_GET['pmid'] : 0;
	if (empty($id_pm))
		redirectexit('action=pm');

	// is_read does multiple things, one per bit. Whether something is read is bit 0, i.e. value 1.
	// We need to clear that bit and only that bit, AND only if it's set already.
	wesql::query('
		UPDATE {db_prefix}pm_recipients
		SET is_read = is_read & 254
		WHERE id_member = {int:id_member}
			AND id_pm = {int:id_pm}
			AND is_read & 1 = 1',
		array(
			'id_pm' => $id_pm,
			'id_member' => MID,
		)
	);

	// If something wasn't marked as read, get the number of unread messages remaining.
	if (wesql::affected_rows() > 0)
		recalculateUnread(MID);

	redirectexit('action=pm');
}

function MessageSearch()
{
	global $context, $txt;

	loadLanguage('Search');

	if (isset($_REQUEST['params']))
	{
		$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], array(' ' => '+'))));
		$context['search_params'] = array();
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$context['search_params'][$k] = $v;
		}
	}
	if (isset($_REQUEST['search']))
		$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);

	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = htmlspecialchars($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec']);

	if (!empty($context['search_params']['searchtype']))
		$context['search_params']['searchtype'] = 2;

	if (!empty($context['search_params']['minage']))
		$context['search_params']['minage'] = (int) $context['search_params']['minage'];

	if (!empty($context['search_params']['maxage']))
		$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];

	$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);
	$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);

	// Create the array of labels to be searched.
	$context['search_labels'] = array();
	$searchedLabels = isset($context['search_params']['labels']) && $context['search_params']['labels'] != '' ? explode(',', $context['search_params']['labels']) : array();
	foreach ($context['labels'] as $label)
	{
		$context['search_labels'][] = array(
			'id' => $label['id'],
			'name' => $label['name'],
			'checked' => !empty($searchedLabels) ? in_array($label['id'], $searchedLabels) : true,
		);
	}

	// Are all the labels checked?
	$context['check_all'] = empty($searchedLabels) || count($context['search_labels']) == count($searchedLabels);

	// Load the error text strings if there were errors in the search.
	if (!empty($context['search_errors']))
	{
		loadLanguage('Errors');
		$context['search_errors']['messages'] = array();
		foreach ($context['search_errors'] as $search_error => $dummy)
		{
			if ($search_error == 'messages')
				continue;

			$context['search_errors']['messages'][] = $txt['error_' . $search_error];
		}
	}

	$context['page_title'] = $txt['pm_search_title'];
	wetem::load('search');
	add_linktree($txt['pm_search_bar_title'], '<URL>?action=pm;sa=search');
}

function MessageSearch2()
{
	global $settings, $context, $txt, $memberContext;

	if (!empty($context['load_average']) && !empty($settings['loadavg_search']) && $context['load_average'] >= $settings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	loadLanguage('Search');

	// !!! For the moment force the folder to the inbox.
	$context['folder'] = 'inbox';

	// Some useful general permissions.
	$context['can_send_pm'] = allowedTo('pm_send');

	// Some hardcoded veriables that can be tweaked if required.
	$maxMembersToSearch = 500;

	// Extract all the search parameters.
	$search_params = array();
	if (isset($_REQUEST['params']))
	{
		$temp_params = explode('|"|', base64_decode(strtr($_REQUEST['params'], array(' ' => '+'))));
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$search_params[$k] = $v;
		}
	}

	$context['start'] = isset($_GET['start']) ? (int) $_GET['start'] : 0;

	// 1 => 'allwords' (default, don't set as param) / 2 => 'anywords'.
	if (!empty($search_params['searchtype']) || (!empty($_REQUEST['searchtype']) && $_REQUEST['searchtype'] == 2))
		$search_params['searchtype'] = 2;

	// Minimum age of messages. Default to zero (don't set param in that case).
	if (!empty($search_params['minage']) || (!empty($_REQUEST['minage']) && $_REQUEST['minage'] > 0))
		$search_params['minage'] = !empty($search_params['minage']) ? (int) $search_params['minage'] : (int) $_REQUEST['minage'];

	// Maximum age of messages. Default to infinite (9999 days: param not set).
	if (!empty($search_params['maxage']) || (!empty($_REQUEST['maxage']) && $_REQUEST['maxage'] != 9999))
		$search_params['maxage'] = !empty($search_params['maxage']) ? (int) $search_params['maxage'] : (int) $_REQUEST['maxage'];

	$search_params['subject_only'] = !empty($search_params['subject_only']) || !empty($_REQUEST['subject_only']);
	$search_params['show_complete'] = !empty($search_params['show_complete']) || !empty($_REQUEST['show_complete']);

	// Default the user name to a wildcard matching every user (*).
	if (!empty($search_params['user_spec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*'))
		$search_params['userspec'] = isset($search_params['userspec']) ? $search_params['userspec'] : $_REQUEST['userspec'];

	// This will be full of all kinds of parameters!
	$searchq_parameters = array();

	// If there's no specific user, then don't mention it in the main query.
	if (empty($search_params['userspec']))
		$userQuery = '';
	else
	{
		$userString = strtr(westr::htmlspecialchars($search_params['userspec'], ENT_QUOTES), array('&quot;' => '"'));
		$userString = strtr($userString, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

		preg_match_all('~"([^"]+)"~', $userString, $matches);
		$possible_users = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)));

		for ($k = 0, $n = count($possible_users); $k < $n; $k++)
		{
			$possible_users[$k] = trim($possible_users[$k]);

			if (strlen($possible_users[$k]) == 0)
				unset($possible_users[$k]);
		}

		// Who matches those criteria?
		// !!! This doesn't support sent item searching.
		$request = wesql::query('
			SELECT id_member
			FROM {db_prefix}members
			WHERE real_name LIKE {raw:real_name_implode}',
			array(
				'real_name_implode' => '\'' . implode('\' OR real_name LIKE \'', $possible_users) . '\'',
			)
		);
		// Simply do nothing if there're too many members matching the criteria.
		if (wesql::num_rows($request) > $maxMembersToSearch)
			$userQuery = '';
		elseif (wesql::num_rows($request) == 0)
		{
			$userQuery = 'AND pm.id_member_from = 0 AND (pm.from_name LIKE {raw:guest_user_name_implode})';
			$searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR pm.from_name LIKE \'', $possible_users) . '\'';
		}
		else
		{
			$memberlist = array();
			while ($row = wesql::fetch_assoc($request))
				$memberlist[] = $row['id_member'];
			$userQuery = 'AND (pm.id_member_from IN ({array_int:member_list}) OR (pm.id_member_from = 0 AND (pm.from_name LIKE {raw:guest_user_name_implode})))';
			$searchq_parameters['guest_user_name_implode'] = '\'' . implode('\' OR pm.from_name LIKE \'', $possible_users) . '\'';
			$searchq_parameters['member_list'] = $memberlist;
		}
		wesql::free_result($request);
	}

	// Setup the sorting variables...
	// !!! Add more in here!
	$sort_columns = array(
		'pm.id_pm',
	);
	if (empty($search_params['sort']) && !empty($_REQUEST['sort']))
		list ($search_params['sort'], $search_params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');
	$search_params['sort'] = !empty($search_params['sort']) && in_array($search_params['sort'], $sort_columns) ? $search_params['sort'] : 'pm.id_pm';
	$search_params['sort_dir'] = !empty($search_params['sort_dir']) && $search_params['sort_dir'] == 'asc' ? 'asc' : 'desc';

	// Sort out any labels we may be searching by.
	$labelQuery = '';
	if ($context['folder'] == 'inbox' && $context['currently_using_labels'])
	{
		// Came here from pagination? Put them back into $_REQUEST for sanitization.
		if (isset($search_params['labels']))
			$_REQUEST['searchlabel'] = explode(',', $search_params['labels']);

		// Assuming we have some labels - make them all integers.
		if (!empty($_REQUEST['searchlabel']) && is_array($_REQUEST['searchlabel']))
		{
			foreach ($_REQUEST['searchlabel'] as $key => $id)
				$_REQUEST['searchlabel'][$key] = (int) $id;
		}
		else
			$_REQUEST['searchlabel'] = array();

		// Now that everything is cleaned up a bit, make the labels a param.
		$search_params['labels'] = implode(',', $_REQUEST['searchlabel']);

		// No labels selected? That must be an error!
		if (empty($_REQUEST['searchlabel']))
			$context['search_errors']['no_labels_selected'] = true;
		// Otherwise prepare the query!
		elseif (count($_REQUEST['searchlabel']) != count($context['labels']))
		{
			$labelQuery = '
			AND {raw:label_implode}';

			$labelStatements = array();
			foreach ($_REQUEST['searchlabel'] as $label)
				$labelStatements[] = wesql::quote('FIND_IN_SET({string:label}, pmr.labels) != 0', array(
					'label' => $label,
				));

			$searchq_parameters['label_implode'] = '(' . implode(' OR ', $labelStatements) . ')';
		}
	}

	// What are we actually searching for?
	$search_params['search'] = !empty($search_params['search']) ? $search_params['search'] : (isset($_REQUEST['search']) ? $_REQUEST['search'] : '');
	// If we ain't got nothing - we should error!
	if (!isset($search_params['search']) || $search_params['search'] == '')
		$context['search_errors']['invalid_search_string'] = true;

	// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
	preg_match_all('~(?:^|\s)([-]?)"([^"]+)"(?:$|\s)~u', $search_params['search'], $matches);
	$searchArray = $matches[2];

	// Remove the phrase parts and extract the words.
	$tempSearch = explode(' ', preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $search_params['search']));

	// A minus sign in front of a word excludes the word.... so...
	$excludedWords = array();

	// .. first, we check for things like -"some words", but not "-some words".
	foreach ($matches[1] as $index => $word)
		if ($word == '-')
		{
			$word = westr::strtolower(trim($searchArray[$index]));
			if (strlen($word) > 0)
				$excludedWords[] = $word;
			unset($searchArray[$index]);
		}

	// Now we look for -test, etc.... normaller.
	foreach ($tempSearch as $index => $word)
		if (strpos(trim($word), '-') === 0)
		{
			$word = substr(westr::strtolower(trim($word)), 1);
			if (strlen($word) > 0)
				$excludedWords[] = $word;
			unset($tempSearch[$index]);
		}

	$searchArray = array_merge($searchArray, $tempSearch);

	// Trim everything and make sure there are no words that are the same.
	foreach ($searchArray as $index => $value)
	{
		$searchArray[$index] = westr::strtolower(trim($value));
		if ($searchArray[$index] == '')
			unset($searchArray[$index]);
		else
		{
			// Sort out entities first.
			$searchArray[$index] = westr::htmlspecialchars($searchArray[$index]);
		}
	}
	$searchArray = array_unique($searchArray);

	// Create an array of replacements for highlighting.
	$context['mark'] = array();
	foreach ($searchArray as $word)
		$context['mark'][$word] = '<mark>' . $word . '</mark>';

	// This contains *everything*
	$searchWords = array_merge($searchArray, $excludedWords);

	// Make sure at least one word is being searched for.
	if (empty($searchArray))
		$context['search_errors']['invalid_search_string'] = true;

	// Sort out the search query so the user can edit it - if they want.
	$context['search_params'] = $search_params;
	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = htmlspecialchars($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec']);

	// Now we have all the parameters, combine them together for pagination and the like...
	$context['params'] = array();
	foreach ($search_params as $k => $v)
		$context['params'][] = $k . '|\'|' . $v;
	$context['params'] = base64_encode(implode('|"|', $context['params']));

	// Compile the subject query part.
	$andQueryParts = array();

	foreach ($searchWords as $index => $word)
	{
		if ($word == '')
			continue;

		if ($search_params['subject_only'])
			$andQueryParts[] = 'pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '}';
		else
			$andQueryParts[] = '(pm.subject' . (in_array($word, $excludedWords) ? ' NOT' : '') . ' LIKE {string:search_' . $index . '} ' . (in_array($word, $excludedWords) ? 'AND pm.body NOT' : 'OR pm.body') . ' LIKE {string:search_' . $index . '})';
		$searchq_parameters['search_' . $index] = '%' . strtr($word, array('_' => '\\_', '%' => '\\%')) . '%';
	}

	$searchQuery = ' 1=1';
	if (!empty($andQueryParts))
		$searchQuery = implode(!empty($search_params['searchtype']) && $search_params['searchtype'] == 2 ? ' OR ' : ' AND ', $andQueryParts);

	// Age limits?
	$timeQuery = '';
	if (!empty($search_params['minage']))
		$timeQuery .= ' AND pm.msgtime < ' . (time() - $search_params['minage'] * 86400);
	if (!empty($search_params['maxage']))
		$timeQuery .= ' AND pm.msgtime > ' . (time() - $search_params['maxage'] * 86400);

	// If we have errors - return back to the first screen...
	if (!empty($context['search_errors']))
	{
		$_REQUEST['params'] = $context['params'];
		return MessageSearch();
	}

	// Get the amount of results.
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
		WHERE ' . ($context['folder'] == 'inbox' ? '
			pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}' : '
			pm.id_member_from = {int:current_member}
			AND pm.deleted_by_sender = {int:not_deleted}') . '
			' . $userQuery . $labelQuery . $timeQuery . '
			AND (' . $searchQuery . ')',
		array_merge($searchq_parameters, array(
			'current_member' => MID,
			'not_deleted' => 0,
		))
	);
	list ($numResults) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Get all the matching messages... using standard search only (No caching and the like!)
	// !!! This doesn't support sent item searching yet.
	$request = wesql::query('
		SELECT pm.id_pm, pm.id_pm_head, pm.id_member_from
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
		WHERE ' . ($context['folder'] == 'inbox' ? '
			pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}' : '
			pm.id_member_from = {int:current_member}
			AND pm.deleted_by_sender = {int:not_deleted}') . '
			' . $userQuery . $labelQuery . $timeQuery . '
			AND (' . $searchQuery . ')
		ORDER BY ' . $search_params['sort'] . ' ' . $search_params['sort_dir'] . '
		LIMIT ' . $context['start'] . ', ' . $settings['search_results_per_page'],
		array_merge($searchq_parameters, array(
			'current_member' => MID,
			'not_deleted' => 0,
		))
	);
	$foundMessages = array();
	$posters = array();
	$head_pms = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$foundMessages[] = $row['id_pm'];
		$posters[] = $row['id_member_from'];
		$head_pms[$row['id_pm']] = $row['id_pm_head'];
	}
	wesql::free_result($request);

	// Find the real head pms!
	if ($context['display_mode'] == 2 && !empty($head_pms))
	{
		$request = wesql::query('
			SELECT MAX(pm.id_pm) AS id_pm, pm.id_pm_head
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
			WHERE pm.id_pm_head IN ({array_int:head_pms})
				AND pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}
			GROUP BY pm.id_pm_head
			LIMIT {int:limit}',
			array(
				'head_pms' => array_unique($head_pms),
				'current_member' => MID,
				'not_deleted' => 0,
				'limit' => count($head_pms),
			)
		);
		$real_pm_ids = array();
		while ($row = wesql::fetch_assoc($request))
			$real_pm_ids[$row['id_pm_head']] = $row['id_pm'];
		wesql::free_result($request);
	}

	// Load the users...
	$posters = array_unique($posters);
	if (!empty($posters))
		loadMemberData($posters);

	// Sort out the page index.
	$context['page_index'] = template_page_index('<URL>?action=pm;sa=search2;params=' . $context['params'], $_GET['start'], $numResults, $settings['search_results_per_page'], false);

	$context['message_labels'] = array();
	$context['message_replied'] = array();
	$context['personal_messages'] = array();

	if (!empty($foundMessages))
	{
		// Now get recipients (but don't include bcc-recipients for your inbox, you're not supposed to know :P!)
		$request = wesql::query('
			SELECT
				pmr.id_pm, mem_to.id_member AS id_member_to, mem_to.real_name AS to_name,
				pmr.bcc, pmr.labels, pmr.is_read
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm IN ({array_int:message_list})',
			array(
				'message_list' => $foundMessages,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if ($context['folder'] == 'sent' || empty($row['bcc']))
				$recipients[$row['id_pm']][empty($row['bcc']) ? 'to' : 'bcc'][] = empty($row['id_member_to']) ? $txt['guest_title'] : '<a href="<URL>?action=profile;u=' . $row['id_member_to'] . '">' . $row['to_name'] . '</a>';

			if ($row['id_member_to'] == MID && $context['folder'] != 'sent')
			{
				$context['message_replied'][$row['id_pm']] = $row['is_read'] & 2;

				$row['labels'] = $row['labels'] == '' ? array() : explode(',', $row['labels']);
				// This is a special need for linking to messages.
				foreach ($row['labels'] as $v)
				{
					if (isset($context['labels'][(int) $v]))
						$context['message_labels'][$row['id_pm']][(int) $v] = array('id' => $v, 'name' => $context['labels'][(int) $v]['name']);

					// Here we find the first label on a message - for linking to posts in results
					if (!isset($context['first_label'][$row['id_pm']]) && !in_array('-1', $row['labels']))
						$context['first_label'][$row['id_pm']] = (int) $v;
				}
			}
		}

		// Prepare the query for the callback!
		$request = wesql::query('
			SELECT pm.id_pm, pm.subject, pm.id_member_from, pm.body, pm.msgtime, pm.from_name
			FROM {db_prefix}personal_messages AS pm
			WHERE pm.id_pm IN ({array_int:message_list})
			ORDER BY ' . $search_params['sort'] . ' ' . $search_params['sort_dir'] . '
			LIMIT ' . count($foundMessages),
			array(
				'message_list' => $foundMessages,
			)
		);
		$counter = 0;
		while ($row = wesql::fetch_assoc($request))
		{
			// If there's no message subject, use the default.
			$row['subject'] = $row['subject'] === '' ? $txt['no_subject'] : $row['subject'];

			// Load this posters context info, if it ain't there then fill in the essentials...
			if (!loadMemberContext($row['id_member_from'], true))
			{
				$memberContext[$row['id_member_from']]['name'] = $row['from_name'];
				$memberContext[$row['id_member_from']]['id'] = 0;
				$memberContext[$row['id_member_from']]['group'] = $txt['guest_title'];
				$memberContext[$row['id_member_from']]['link'] = $row['from_name'];
				$memberContext[$row['id_member_from']]['email'] = '';
				$memberContext[$row['id_member_from']]['show_email'] = showEmailAddress(true, 0);
				$memberContext[$row['id_member_from']]['is_guest'] = true;
			}

			// Censor anything we don't want to see...
			censorText($row['body']);
			censorText($row['subject']);

			// Parse out any BBC...
			$row['body'] = parse_bbc($row['body'], 'pm', array('cache' => 'pm' . $row['id_pm']));

			$href = '<URL>?action=pm;f=' . $context['folder'] . (isset($context['first_label'][$row['id_pm']]) ? ';l=' . $context['first_label'][$row['id_pm']] : '') . ';pmid=' . ($context['display_mode'] == 2 && isset($real_pm_ids[$head_pms[$row['id_pm']]]) ? $real_pm_ids[$head_pms[$row['id_pm']]] : $row['id_pm']) . '#msg' . $row['id_pm'];
			$is_replied_to =& $context['message_replied'][$row['id_pm']];
			$context['personal_messages'][$row['id_pm']] = array(
				'id' => $row['id_pm'],
				'member' => &$memberContext[$row['id_member_from']],
				'subject' => $row['subject'],
				'body' => $row['body'],
				'on_time' => on_timeformat($row['msgtime']),
				'recipients' => &$recipients[$row['id_pm']],
				'labels' => &$context['message_labels'][$row['id_pm']],
				'fully_labeled' => count($context['message_labels'][$row['id_pm']]) == count($context['labels']),
				'replied_msg' => $is_replied_to ? number_context('pm_is_replied_to_sent', $is_replied_to, false) : 0,
				'is_replied_to' => $is_replied_to,
				'href' => $href,
				'link' => '<a href="' . $href . '">' . $row['subject'] . '</a>',
				'counter' => ++$counter,
			);
		}
		wesql::free_result($request);
	}

	// Finish off the context.
	$context['page_title'] = $txt['pm_search_title'];
	wetem::load('search_results');
	$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'search';
	add_linktree($txt['pm_search_bar_title'], '<URL>?action=pm;sa=search');
}

// Send a new message?
function MessagePost()
{
	global $txt, $settings, $user_profile, $context, $options;

	isAllowedTo('pm_send');

	loadLanguage('PersonalMessage');

	// Just in case it was loaded from somewhere else.
	loadTemplate('PersonalMessage');
	wetem::load('send');

	// Needed for the WYSIWYG editor.
	loadSource(array('Subs-Editor', 'Class-Editor'));

	// Extract out the spam settings - cause it's neat.
	list ($settings['max_pm_recipients'], $settings['pm_posts_verification'], $settings['pm_posts_per_hour']) = explode(',', $settings['pm_spam_settings']);

	// Set the title...
	$context['page_title'] = $txt['new_message'];

	$context['reply'] = isset($_REQUEST['pmsg']) || isset($_REQUEST['quote']);
	$_REQUEST['draft_id'] = isset($_REQUEST['draft_id']) ? (int) $_REQUEST['draft_id'] : 0;

	// Check whether we've gone over the limit of messages we can send per hour.
	if (!empty($settings['pm_posts_per_hour']) && !allowedTo(array('admin_forum', 'moderate_forum', 'send_mail')) && we::$user['mod_cache']['bq'] == '0=1' && we::$user['mod_cache']['gq'] == '0=1')
	{
		// How many messages have they sent this last hour?
		$request = wesql::query('
			SELECT COUNT(pr.id_pm) AS post_count
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pm.id_pm)
			WHERE pm.id_member_from = {int:current_member}
				AND pm.msgtime > {int:msgtime}',
			array(
				'current_member' => MID,
				'msgtime' => time() - 3600,
			)
		);
		list ($postCount) = wesql::fetch_row($request);
		wesql::free_result($request);

		if (!empty($postCount) && $postCount >= $settings['pm_posts_per_hour'])
			fatal_lang_error('pm_too_many_per_hour', true, array($settings['pm_posts_per_hour']));
	}

	// Quoting/Replying to a message?
	if (!empty($_REQUEST['pmsg']))
	{
		$pmsg = (int) $_REQUEST['pmsg'];

		// Make sure this is yours.
		if (!isAccessiblePM($pmsg))
			fatal_lang_error('pm_not_yours', false);

		// Work out whether this is one you've received?
		$request = wesql::query('
			SELECT
				id_pm
			FROM {db_prefix}pm_recipients
			WHERE id_pm = {int:id_pm}
				AND id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => MID,
				'id_pm' => $pmsg,
			)
		);
		$isReceived = wesql::num_rows($request) != 0;
		wesql::free_result($request);

		// Get the quoted message (and make sure you're allowed to see this quote!).
		$request = wesql::query('
			SELECT
				pm.id_pm, CASE WHEN pm.id_pm_head = {int:id_pm_head_empty} THEN pm.id_pm ELSE pm.id_pm_head END AS pm_head,
				pm.body, pm.subject, pm.msgtime, mem.member_name, IFNULL(mem.id_member, 0) AS id_member,
				IFNULL(mem.real_name, pm.from_name) AS real_name
			FROM {db_prefix}personal_messages AS pm' . (!$isReceived ? '' : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = {int:id_pm})') . '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:id_pm}' . (!$isReceived ? '
				AND pm.id_member_from = {int:current_member}' : '
				AND pmr.id_member = {int:current_member}') . '
			LIMIT 1',
			array(
				'current_member' => MID,
				'id_pm_head_empty' => 0,
				'id_pm' => $pmsg,
			)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('pm_not_yours', false);
		$row_quoted = wesql::fetch_assoc($request);
		wesql::free_result($request);

		// Censor the message.
		censorText($row_quoted['subject']);
		censorText($row_quoted['body']);

		// Add 'Re: ' to it....
		getRePrefix();

		$form_subject = $row_quoted['subject'];
		if ($context['reply'] && trim($context['response_prefix']) != '' && westr::strpos($form_subject, trim($context['response_prefix'])) !== 0)
			$form_subject = $context['response_prefix'] . $form_subject;

		if (isset($_REQUEST['quote']))
		{
			// Remove any nested quotes and <br>...
			$form_message = preg_replace('~<br\s*/?\>~i', "\n", $row_quoted['body']);
			if (!empty($settings['removeNestedQuotes']))
				$form_message = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $form_message);
			if (empty($row_quoted['id_member']))
				$form_message = '[quote author=&quot;' . $row_quoted['real_name'] . '&quot;]' . "\n" . $form_message . "\n" . '[/quote]';
			else
				$form_message = '[quote author=' . $row_quoted['real_name'] . ' link=action=profile;u=' . $row_quoted['id_member'] . ' date=' . $row_quoted['msgtime'] . ']' . "\n" . $form_message . "\n" . '[/quote]';
		}
		else
			$form_message = '';

		// Do the BBC thang on the message.
		$row_quoted['body'] = parse_bbc($row_quoted['body'], 'pm', array('cache' => 'pm' . $row_quoted['id_pm']));

		// Set up the quoted message array.
		$context['quoted_message'] = array(
			'id' => $row_quoted['id_pm'],
			'pm_head' => $row_quoted['pm_head'],
			'member' => array(
				'name' => $row_quoted['real_name'],
				'username' => $row_quoted['member_name'],
				'id' => $row_quoted['id_member'],
				'href' => !empty($row_quoted['id_member']) ? '<URL>?action=profile;u=' . $row_quoted['id_member'] : '',
				'link' => !empty($row_quoted['id_member']) ? '<a href="<URL>?action=profile;u=' . $row_quoted['id_member'] . '">' . $row_quoted['real_name'] . '</a>' : $row_quoted['real_name'],
			),
			'subject' => $row_quoted['subject'],
			'on_time' => on_timeformat($row_quoted['msgtime']),
			'timestamp' => $row_quoted['msgtime'],
			'body' => $row_quoted['body']
		);
	}
	else
	{
		$context['quoted_message'] = false;
		$form_subject = '';
		$form_message = '';
	}

	$context['recipients'] = array(
		'to' => array(),
		'bcc' => array(),
	);

	// Sending by ID? Replying to all? Fetch the real_name(s).
	if (isset($_REQUEST['u']))
	{
		// If the user is replying to all, get all the other members this was sent to..
		if ($_REQUEST['u'] == 'all' && isset($row_quoted))
		{
			// Firstly, to reply to all we clearly already have $row_quoted - so have the original member from.
			if ($row_quoted['id_member'] != MID)
				$context['recipients']['to'][] = array(
					'id' => $row_quoted['id_member'],
					'name' => htmlspecialchars($row_quoted['real_name']),
				);

			// Now to get the others.
			$request = wesql::query('
				SELECT mem.id_member, mem.real_name
				FROM {db_prefix}pm_recipients AS pmr
					INNER JOIN {db_prefix}members AS mem ON (mem.id_member = pmr.id_member)
				WHERE pmr.id_pm = {int:id_pm}
					AND pmr.id_member != {int:current_member}
					AND pmr.bcc = {int:not_bcc}',
				array(
					'current_member' => MID,
					'id_pm' => $pmsg,
					'not_bcc' => 0,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				$context['recipients']['to'][] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				);
			wesql::free_result($request);
		}
		else
		{
			$_REQUEST['u'] = explode(',', $_REQUEST['u']);
			foreach ($_REQUEST['u'] as $key => $uID)
				$_REQUEST['u'][$key] = (int) $uID;

			$_REQUEST['u'] = array_unique($_REQUEST['u']);

			$request = wesql::query('
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:member_list})
				LIMIT ' . count($_REQUEST['u']),
				array(
					'member_list' => $_REQUEST['u'],
				)
			);
			while ($row = wesql::fetch_assoc($request))
				$context['recipients']['to'][] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
				);
			wesql::free_result($request);
		}

		// Get a literal name list in case the user has JavaScript disabled.
		$names = array();
		foreach ($context['recipients']['to'] as $to)
			$names[] = $to['name'];
		$context['to_value'] = empty($names) ? '' : '&quot;' . implode('&quot;, &quot;', $names) . '&quot;';
	}
	else
		$context['to_value'] = '';

	// Loading a draft?
	if (!empty($_REQUEST['draft_id']) && allowedTo('save_pm_draft') && empty($_POST['subject']) && empty($_POST['message']))
	{
		$query = wesql::query('
			SELECT subject, body, extra
			FROM {db_prefix}drafts
			WHERE id_draft = {int:draft}
				AND id_member = {int:member}
				AND is_pm = {int:is_pm}
			LIMIT 1',
			array(
				'draft' => $_REQUEST['draft_id'],
				'member' => MID,
				'is_pm' => 1,
			)
		);

		if ($row = wesql::fetch_assoc($query))
		{
			// OK, we have a draft in storage for this message.	Let's get down and dirty with it.
			$form_subject = $row['subject'];
			$form_message = wedit::un_preparsecode($row['body']);
			$row['extra'] = empty($row['extra']) ? array() : unserialize($row['extra']);

			// Get a list of all the users we're sending to, so we can load their data.
			$row['extra']['recipients']['to'] = isset($row['extra']['recipients']['to']) ? $row['extra']['recipients']['to'] : array();
			$row['extra']['recipients']['bcc'] = isset($row['extra']['recipients']['bcc']) ? $row['extra']['recipients']['bcc'] : array();
			$users = array_merge($row['extra']['recipients']['to'], $row['extra']['recipients']['bcc']);
			$users = loadMemberData($users);

			$context['recipients'] = array(
				'to' => array(),
				'bcc' => array(),
			);
			// Just in case any disappeared, anything that is in the original to/bcc lists that isn't in the result new list, remove it.
			if (!empty($users))
			{
				$row['extra']['recipients']['to'] = array_intersect($row['extra']['recipients']['to'], $users);
				$row['extra']['recipients']['bcc'] = array_intersect($row['extra']['recipients']['bcc'], $users);
			}

			$names = array();
			foreach ($row['extra']['recipients'] as $recType => $recList)
			{
				foreach ($recList as $recipient)
				{
					$context['recipients'][$recType][] = array(
						'id' => $recipient,
						'name' => $user_profile[$recipient]['real_name'],
					);
					$names[$recType][] = $user_profile[$recipient]['real_name'];
				}
				$context[$recType . '_value'] = empty($names[$recType]) ? '' : '&quot;' . implode('&quot;, &quot;', $names[$recType]) . '&quot;';
			}
		}
		wesql::free_result($query);
	}

	// Set the defaults...
	$context['subject'] = $form_subject;
	$context['message'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_message);
	$context['post_error'] = array();

	$context['buddy_list'] = getContactList();

	// And build the link tree.
	add_linktree($txt['new_message'], '<URL>?action=pm;sa=send');

	// Now create the editor.
	$context['postbox'] = new wedit(
		array(
			'id' => 'message',
			'value' => $context['message'],
			'height' => '250px',
			'width' => '100%',
			'buttons' => array(
				array(
					'name' => 'post_button',
					'button_text' => $txt['send_message'],
					'onclick' => 'return submitThisOnce(this);',
					'accesskey' => 's',
				),
				array(
					'name' => 'preview',
					'button_text' => $txt['preview'],
					'onclick' => 'return submitThisOnce(this);',
					'accesskey' => 'p',
				),
			),
			'drafts' => !allowedTo('save_pm_draft') || empty($settings['masterSavePmDrafts']) ? 'none' : (!allowedTo('auto_save_pm_draft') || empty($settings['masterAutoSavePmDrafts']) || !empty($options['disable_auto_save']) ? 'basic_pm' : 'auto_pm'),
		)
	);
	$context['postbox']->addEntityField('subject');

	$context['bcc_value'] = '';

	$context['require_verification'] = !we::$is_admin && !empty($settings['pm_posts_verification']) && we::$user['posts'] < $settings['pm_posts_verification'];
	if ($context['require_verification'])
	{
		$verificationOptions = array(
			'id' => 'pm',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// Register this form and get a sequence number in $context.
	checkSubmitOnce('register');
}

// An error in the message...
function messagePostError($error_types, $named_recipients, $recipient_ids = array())
{
	global $txt, $context, $settings, $options;

	$context['menu_data_' . $context['pm_menu_id']]['current_area'] = 'send';

	wetem::load('send');

	$context['page_title'] = $txt['send_message'];

	// Got some known members?
	$context['recipients'] = array(
		'to' => array(),
		'bcc' => array(),
	);
	if (!empty($recipient_ids['to']) || !empty($recipient_ids['bcc']))
	{
		$allRecipients = array_merge($recipient_ids['to'], $recipient_ids['bcc']);

		$request = wesql::query('
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})',
			array(
				'member_list' => $allRecipients,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$recipientType = in_array($row['id_member'], $recipient_ids['bcc']) ? 'bcc' : 'to';
			$context['recipients'][$recipientType][] = array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
			);
		}
		wesql::free_result($request);
	}

	// Set everything up like before....
	$context['subject'] = isset($_REQUEST['subject']) ? westr::htmlspecialchars($_REQUEST['subject']) : '';
	$context['message'] = isset($_REQUEST['message']) ? str_replace(array('  '), array('&nbsp; '), westr::htmlspecialchars($_REQUEST['message'])) : '';
	$context['reply'] = !empty($_REQUEST['replied_to']);

	$context['buddy_list'] = getContactList();

	if ($context['reply'])
	{
		$_REQUEST['replied_to'] = (int) $_REQUEST['replied_to'];

		$request = wesql::query('
			SELECT
				pm.id_pm, CASE WHEN pm.id_pm_head = {int:no_id_pm_head} THEN pm.id_pm ELSE pm.id_pm_head END AS pm_head,
				pm.body, pm.subject, pm.msgtime, mem.member_name, IFNULL(mem.id_member, 0) AS id_member,
				IFNULL(mem.real_name, pm.from_name) AS real_name
			FROM {db_prefix}personal_messages AS pm' . ($context['folder'] == 'sent' ? '' : '
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = {int:replied_to})') . '
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:replied_to}' . ($context['folder'] == 'sent' ? '
				AND pm.id_member_from = {int:current_member}' : '
				AND pmr.id_member = {int:current_member}') . '
			LIMIT 1',
			array(
				'current_member' => MID,
				'no_id_pm_head' => 0,
				'replied_to' => $_REQUEST['replied_to'],
			)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('pm_not_yours', false);
		$row_quoted = wesql::fetch_assoc($request);
		wesql::free_result($request);

		censorText($row_quoted['subject']);
		censorText($row_quoted['body']);

		$context['quoted_message'] = array(
			'id' => $row_quoted['id_pm'],
			'pm_head' => $row_quoted['pm_head'],
			'member' => array(
				'name' => $row_quoted['real_name'],
				'username' => $row_quoted['member_name'],
				'id' => $row_quoted['id_member'],
				'href' => !empty($row_quoted['id_member']) ? '<URL>?action=profile;u=' . $row_quoted['id_member'] : '',
				'link' => !empty($row_quoted['id_member']) ? '<a href="<URL>?action=profile;u=' . $row_quoted['id_member'] . '">' . $row_quoted['real_name'] . '</a>' : $row_quoted['real_name'],
			),
			'subject' => $row_quoted['subject'],
			'on_time' => on_timeformat($row_quoted['msgtime']),
			'timestamp' => $row_quoted['msgtime'],
			'body' => parse_bbc($row_quoted['body'], 'pm', array('cache' => 'pm' . $row_quoted['id_pm'])),
		);
	}

	// Build the link tree....
	add_linktree($txt['new_message'], '<URL>?action=pm;sa=send');

	// Set each of the errors for the template.
	loadLanguage('Errors');
	$context['post_error'] = array(
		'messages' => array(),
	);
	foreach ($error_types as $error)
	{
		if (is_array($error))
		{
			$context['post_error'][$error[0]] = true;
			if (isset($txt['error_' . $error[0]]))
				$context['post_error']['messages'][] = sprintf($txt['error_' . $error[0]], $error[1]);
		}
		else
		{
			$context['post_error'][$error] = true;
			if (isset($txt['error_' . $error]))
				$context['post_error']['messages'][] = $txt['error_' . $error];
		}
	}

	// We need to load the editor once more.
	loadSource(array('Subs-Editor', 'Class-Editor'));

	// Create it...
	$context['postbox'] = new wedit(
		array(
			'id' => 'message',
			'value' => $context['message'],
			'width' => '90%',
			'buttons' => array(
				array(
					'name' => 'post_button',
					'button_text' => $txt['send_message'],
					'onclick' => 'return submitThisOnce(this);',
					'accesskey' => 's',
				),
				array(
					'name' => 'preview',
					'button_text' => $txt['preview'],
					'onclick' => 'return submitThisOnce(this);',
					'accesskey' => 'p',
				),
			),
			'drafts' => !allowedTo('save_pm_draft') || empty($settings['masterSavePmDrafts']) ? 'none' : (!allowedTo('auto_save_pm_draft') || empty($settings['masterAutoSavePmDrafts']) || !empty($options['disable_auto_save']) ? 'basic_pm' : 'auto_pm'),
		)
	);
	$context['postbox']->addEntityField('subject');

	// Check whether we need to show the code again.
	$context['require_verification'] = !we::$is_admin && !empty($settings['pm_posts_verification']) && we::$user['posts'] < $settings['pm_posts_verification'];
	if ($context['require_verification'])
	{
		$verificationOptions = array(
			'id' => 'pm',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	$context['to_value'] = empty($named_recipients['to']) ? '' : '&quot;' . implode('&quot;, &quot;', $named_recipients['to']) . '&quot;';
	$context['bcc_value'] = empty($named_recipients['bcc']) ? '' : '&quot;' . implode('&quot;, &quot;', $named_recipients['bcc']) . '&quot;';

	// No check for the previous submission is needed.
	checkSubmitOnce('free');

	// Acquire a new form sequence number.
	checkSubmitOnce('register');
}

function getContactList()
{
	global $settings, $user_profile;

	$buddies = array();

	if (empty($settings['enable_buddylist']) || empty(we::$user['buddies']))
		return $buddies;

	loadMemberData(we::$user['buddies']);
	foreach (we::$user['buddies'] as $buddy)
		if (isset($user_profile[$buddy]))
			$buddies[$buddy] = $user_profile[$buddy]['real_name'];
	asort($buddies);
	return $buddies;
}

// Send it!
function MessagePost2()
{
	global $txt, $context, $settings;

	isAllowedTo('pm_send');
	loadSource(array('Subs-Auth', 'Class-Editor'));

	loadLanguage('PersonalMessage', '', false);

	$session_timeout = checkSession('post', '', false) != '';

	// Extract out the spam settings - it saves database space!
	list ($settings['max_pm_recipients'], $settings['pm_posts_verification'], $settings['pm_posts_per_hour']) = explode(',', $settings['pm_spam_settings']);

	// Check whether we've gone over the limit of messages we can send per hour - fatal error if fails!
	if (!empty($settings['pm_posts_per_hour']) && !allowedTo(array('admin_forum', 'moderate_forum', 'send_mail')) && we::$user['mod_cache']['bq'] == '0=1' && we::$user['mod_cache']['gq'] == '0=1')
	{
		// How many have they sent this last hour?
		$request = wesql::query('
			SELECT COUNT(pr.id_pm) AS post_count
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pr ON (pr.id_pm = pm.id_pm)
			WHERE pm.id_member_from = {int:current_member}
				AND pm.msgtime > {int:msgtime}',
			array(
				'current_member' => MID,
				'msgtime' => time() - 3600,
			)
		);
		list ($postCount) = wesql::fetch_row($request);
		wesql::free_result($request);

		if (!empty($postCount) && $postCount >= $settings['pm_posts_per_hour'])
			fatal_lang_error('pm_too_many_per_hour', true, array($settings['pm_posts_per_hour']));
	}

	// If we came from WYSIWYG then turn it back into BBC regardless. Make sure we tell it what item we're expecting to use.
	wedit::preparseWYSIWYG('message');

	// Initialize the errors we're about to make.
	$post_errors = array();

	// If your session timed out, show an error, but do allow to re-submit.
	if ($session_timeout)
		$post_errors[] = 'session_timeout';

	$_REQUEST['subject'] = isset($_REQUEST['subject']) ? trim($_REQUEST['subject']) : '';
	$_REQUEST['to'] = empty($_POST['to']) ? (empty($_GET['to']) ? '' : $_GET['to']) : $_POST['to'];
	$_REQUEST['bcc'] = empty($_POST['bcc']) ? (empty($_GET['bcc']) ? '' : $_GET['bcc']) : $_POST['bcc'];

	// Route the input from the 'u' parameter to the 'to'-list.
	if (!empty($_POST['u']))
		$_POST['recipient_to'] = explode(',', $_POST['u']);

	// Construct the list of recipients.
	$recipientList = array();
	$namedRecipientList = array();
	$namesNotFound = array();
	getPmRecipients($recipientList, $namedRecipientList, $namesNotFound);

	// Are we changing the recipients some how?
	$is_recipient_change = !empty($_POST['delete_recipient']) || !empty($_POST['to_submit']) || !empty($_POST['bcc_submit']);

	// Check if there's at least one recipient.
	if (empty($recipientList['to']) && empty($recipientList['bcc']))
		$post_errors[] = 'no_to';

	// Make sure that we remove the members who did get it from the screen.
	if (!$is_recipient_change)
	{
		foreach ($recipientList as $recipientType => $dummy)
		{
			if (!empty($namesNotFound[$recipientType]))
			{
				$post_errors[] = 'bad_' . $recipientType;

				// Since we already have a post error, remove the previous one.
				$post_errors = array_diff($post_errors, array('no_to'));

				foreach ($namesNotFound[$recipientType] as $name)
					$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
			}
		}
	}

	// Did they make any mistakes?
	if ($_REQUEST['subject'] === '')
		$post_errors[] = 'no_subject';
	if (!isset($_REQUEST['message']) || $_REQUEST['message'] === '')
		$post_errors[] = 'no_message';
	elseif (!empty($settings['max_messageLength']) && westr::strlen($_REQUEST['message']) > $settings['max_messageLength'])
		$post_errors[] = array('long_message', $settings['max_messageLength']);
	else
	{
		// Preparse the message.
		$message = $_REQUEST['message'];
		wedit::preparsecode($message);

		// Make sure there's still some content left without the tags.
		if (westr::htmltrim(strip_tags(parse_bbc(westr::htmlspecialchars($message, ENT_QUOTES), 'empty-test', array('smileys' => false)), '<img><object><embed><iframe><video><audio>')) === '' && (!allowedTo('admin_forum') || strpos($message, '[html]') === false))
			$post_errors[] = 'no_message';
	}

	// Wrong verification code?
	if (!we::$is_admin && !empty($settings['pm_posts_verification']) && we::$user['posts'] < $settings['pm_posts_verification'])
	{
		loadSource('Subs-Editor');
		$verificationOptions = array(
			'id' => 'pm',
		);
		$context['require_verification'] = create_control_verification($verificationOptions, true);

		if (is_array($context['require_verification']))
			$post_errors = array_merge($post_errors, $context['require_verification']);
	}

	// If they did, give a chance to make ammends.
	if (!empty($post_errors) && !$is_recipient_change && !isset($_REQUEST['preview']))
		return messagePostError($post_errors, $namedRecipientList, $recipientList);

	// Want to take a second glance before you send?
	if (isset($_REQUEST['preview']))
	{
		// Set everything up to be displayed.
		$context['preview_subject'] = westr::htmlspecialchars($_REQUEST['subject']);
		$context['preview_message'] = westr::htmlspecialchars($_REQUEST['message'], ENT_QUOTES);
		wedit::preparsecode($context['preview_message'], true);

		// Parse out the BBC if it is enabled.
		$context['preview_message'] = parse_bbc($context['preview_message'], 'preview-pm');

		// Censor, as always.
		censorText($context['preview_subject']);
		censorText($context['preview_message']);

		// Set a descriptive title.
		$context['page_title'] = $txt['preview'] . ' - ' . $context['preview_subject'];

		// Pretend they messed up but don't ignore if they really did :P.
		return messagePostError($post_errors, $namedRecipientList, $recipientList);
	}

	// Adding a recipient cause javascript ain't working?
	elseif ($is_recipient_change)
	{
		// Maybe we couldn't find one?
		foreach ($namesNotFound as $recipientType => $names)
		{
			$post_errors[] = 'bad_' . $recipientType;
			foreach ($names as $name)
				$context['send_log']['failed'][] = sprintf($txt['pm_error_user_not_found'], $name);
		}

		return messagePostError(array(), $namedRecipientList, $recipientList);
	}

	// Before we send the PM, let's make sure we don't have an abuse of numbers.
	elseif (!empty($settings['max_pm_recipients']) && count($recipientList['to']) + count($recipientList['bcc']) > $settings['max_pm_recipients'] && !allowedTo(array('moderate_forum', 'send_mail', 'admin_forum')))
	{
		$context['send_log'] = array(
			'sent' => array(),
			'failed' => array(number_context('pm_too_many_recipients', $settings['max_pm_recipients'])),
		);
		return messagePostError($post_errors, $namedRecipientList, $recipientList);
	}

	// Protect from message spamming.
	spamProtection('pm');

	// Prevent double submission of this form.
	checkSubmitOnce('check');

	// Do the actual sending of the PM.
	if (!empty($recipientList['to']) || !empty($recipientList['bcc']))
		$context['send_log'] = sendpm($recipientList, $_REQUEST['subject'], $_REQUEST['message'], true, null, !empty($_REQUEST['pm_head']) ? (int) $_REQUEST['pm_head'] : 0);
	else
		$context['send_log'] = array(
			'sent' => array(),
			'failed' => array()
		);

	// Mark the message as "replied to".
	if (!empty($context['send_log']['sent']) && !empty($_REQUEST['replied_to']) && isset($_REQUEST['f']) && $_REQUEST['f'] == 'inbox')
	{
		wesql::query('
			UPDATE {db_prefix}pm_recipients
			SET is_read = is_read | 2
			WHERE id_pm = {int:replied_to}
				AND id_member = {int:current_member}',
			array(
				'current_member' => MID,
				'replied_to' => (int) $_REQUEST['replied_to'],
			)
		);
	}

	// Um, did this come from a draft? If so, bye bye.
	if (!empty($_POST['draft_id']) && MID)
		wesql::query('
			DELETE FROM {db_prefix}drafts
			WHERE id_draft = {int:draft}
				AND id_member = {int:member}
			LIMIT 1',
			array(
				'draft' => (int) $_POST['draft_id'],
				'member' => MID,
			)
		);

	// If one or more of the recipient were invalid, go back to the post screen with the failed usernames.
	if (!empty($context['send_log']['failed']))
		return messagePostError($post_errors, $namesNotFound, array(
			'to' => array_intersect($recipientList['to'], $context['send_log']['failed']),
			'bcc' => array_intersect($recipientList['bcc'], $context['send_log']['failed'])
		));

	// Message sent successfully?
	if (!empty($context['send_log']) && empty($context['send_log']['failed']))
		$context['current_label_redirect'] = $context['current_label_redirect'] . ';done=sent';

	// Go back to the where they sent from, if possible...
	redirectexit($context['current_label_redirect']);
}

// Examines $_POST for auto-suggest user ids, as well as given names in quotes to see if it can match up user ids
// All three inputs are empty arrays and made available back to the caller with references, yay :P
function getPmRecipients(&$recipientList, &$namedRecipientList, &$namesNotFound)
{
	foreach (array('to', 'bcc') as $recipientType)
	{
		// First, let's see if there's user ID's given.
		$recipientList[$recipientType] = array();
		if (!empty($_POST['recipient_' . $recipientType]) && is_array($_POST['recipient_' . $recipientType]))
		{
			foreach ($_POST['recipient_' . $recipientType] as $recipient)
				$recipientList[$recipientType][] = (int) $recipient;
		}

		// Are there also literal names set?
		if (!empty($_REQUEST[$recipientType]))
		{
			// We're going to take out the "s anyway ;).
			$recipientString = strtr($_REQUEST[$recipientType], array('\\"' => '"'));

			preg_match_all('~"([^"]+)"~', $recipientString, $matches);
			$namedRecipientList[$recipientType] = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $recipientString))));

			foreach ($namedRecipientList[$recipientType] as $index => $recipient)
			{
				if (strlen(trim($recipient)) > 0)
					$namedRecipientList[$recipientType][$index] = westr::htmlspecialchars(westr::strtolower(trim($recipient)));
				else
					unset($namedRecipientList[$recipientType][$index]);
			}

			if (!empty($namedRecipientList[$recipientType]))
			{
				loadSource('Subs-Auth');
				$foundMembers = findMembers($namedRecipientList[$recipientType]);

				// Assume all are not found, until proven otherwise.
				$namesNotFound[$recipientType] = $namedRecipientList[$recipientType];

				foreach ($foundMembers as $member)
				{
					$testNames = array(
						westr::strtolower($member['username']),
						westr::strtolower($member['name']),
						westr::strtolower($member['email']),
					);

					if (count(array_intersect($testNames, $namedRecipientList[$recipientType])) !== 0)
					{
						$recipientList[$recipientType][] = $member['id'];

						// Get rid of this username, since we found it.
						$namesNotFound[$recipientType] = array_diff($namesNotFound[$recipientType], $testNames);
					}
				}
			}
		}

		// Selected a recipient to be deleted? Remove them now.
		if (!empty($_POST['delete_recipient']))
			$recipientList[$recipientType] = array_diff($recipientList[$recipientType], array((int) $_POST['delete_recipient']));

		// Make sure we don't include the same name twice
		$recipientList[$recipientType] = array_unique($recipientList[$recipientType]);
	}
}

// This function performs all additional stuff...
function MessageActionsApply()
{
	global $context, $options;

	checkSession('request');

	if (isset($_REQUEST['del_selected']))
		$_REQUEST['pm_action'] = 'delete';

	if (isset($_REQUEST['pm_action']) && $_REQUEST['pm_action'] != '' && !empty($_REQUEST['pms']) && is_array($_REQUEST['pms']))
	{
		foreach ($_REQUEST['pms'] as $pm)
			$_REQUEST['pm_actions'][(int) $pm] = $_REQUEST['pm_action'];
	}

	if (empty($_REQUEST['pm_actions']))
		redirectexit($context['current_label_redirect']);

	// If we are in conversation, we may need to apply this to every message in the conversation.
	if ($context['display_mode'] == 2 && isset($_REQUEST['conversation']))
	{
		$id_pms = array();
		foreach ($_REQUEST['pm_actions'] as $pm => $dummy)
			$id_pms[] = (int) $pm;

		$request = wesql::query('
			SELECT id_pm_head, id_pm
			FROM {db_prefix}personal_messages
			WHERE id_pm IN ({array_int:id_pms})',
			array(
				'id_pms' => $id_pms,
			)
		);
		$pm_heads = array();
		while ($row = wesql::fetch_assoc($request))
			$pm_heads[$row['id_pm_head']] = $row['id_pm'];
		wesql::free_result($request);

		$request = wesql::query('
			SELECT id_pm, id_pm_head
			FROM {db_prefix}personal_messages
			WHERE id_pm_head IN ({array_int:pm_heads})',
			array(
				'pm_heads' => array_keys($pm_heads),
			)
		);
		// Copy the action from the single to PM to the others.
		while ($row = wesql::fetch_assoc($request))
		{
			if (isset($pm_heads[$row['id_pm_head']], $_REQUEST['pm_actions'][$pm_heads[$row['id_pm_head']]]))
				$_REQUEST['pm_actions'][$row['id_pm']] = $_REQUEST['pm_actions'][$pm_heads[$row['id_pm_head']]];
		}
		wesql::free_result($request);
	}

	$to_delete = array();
	$to_label = array();
	$label_type = array();
	foreach ($_REQUEST['pm_actions'] as $pm => $action)
	{
		if ($action === 'delete')
			$to_delete[] = (int) $pm;
		else
		{
			if (substr($action, 0, 4) == 'add_')
			{
				$type = 'add';
				$action = substr($action, 4);
			}
			elseif (substr($action, 0, 4) == 'rem_')
			{
				$type = 'rem';
				$action = substr($action, 4);
			}
			else
				$type = 'unk';

			if ($action == '-1' || $action == '0' || (int) $action > 0)
			{
				$to_label[(int) $pm] = (int) $action;
				$label_type[(int) $pm] = $type;
			}
		}
	}

	// Deleting, it looks like?
	if (!empty($to_delete))
		deleteMessages($to_delete, $context['display_mode'] == 2 ? null : $context['folder']);

	// Are we labeling anything?
	if (!empty($to_label) && $context['folder'] == 'inbox')
	{
		$updateErrors = 0;

		// Get information about each message...
		$request = wesql::query('
			SELECT id_pm, labels
			FROM {db_prefix}pm_recipients
			WHERE id_member = {int:current_member}
				AND id_pm IN ({array_int:to_label})
			LIMIT ' . count($to_label),
			array(
				'current_member' => MID,
				'to_label' => array_keys($to_label),
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$labels = $row['labels'] == '' ? array('-1') : explode(',', trim($row['labels']));

			// Already exists? Then... unset it!
			$id_label = array_search($to_label[$row['id_pm']], $labels);
			if ($id_label !== false && $label_type[$row['id_pm']] !== 'add')
				unset($labels[$id_label]);
			elseif ($label_type[$row['id_pm']] !== 'rem')
				$labels[] = $to_label[$row['id_pm']];

			if (!empty($options['pm_remove_inbox_label']) && $to_label[$row['id_pm']] != '-1' && ($key = array_search('-1', $labels)) !== false)
				unset($labels[$key]);

			$set = implode(',', array_unique($labels));
			if ($set == '')
				$set = '-1';

			// Check that this string isn't going to be too large for the database.
			if ($set > 60)
				$updateErrors++;
			else
			{
				wesql::query('
					UPDATE {db_prefix}pm_recipients
					SET labels = {string:labels}
					WHERE id_pm = {int:id_pm}
						AND id_member = {int:current_member}',
					array(
						'current_member' => MID,
						'id_pm' => $row['id_pm'],
						'labels' => $set,
					)
				);
			}
		}
		wesql::free_result($request);

		// Any errors?
		// !!! Separate the sprintf?
		if (!empty($updateErrors))
			fatal_lang_error('labels_too_many', true, array($updateErrors));
	}

	// Back to the folder.
	$_SESSION['pm_selected'] = array_keys($to_label);
	redirectexit($context['current_label_redirect'] . (count($to_label) == 1 ? '#msg' . $_SESSION['pm_selected'][0] : ''), count($to_label) == 1 && we::is('ie'));
}

// Are you sure you want to PERMANENTLY (mostly) delete ALL your messages?
function MessageKillAllQuery()
{
	global $txt, $context;

	// Only have to set up the template....
	wetem::load('ask_delete');
	$context['page_title'] = $txt['delete_all'];
	$context['delete_all'] = $_REQUEST['f'] == 'all';

	// And set the folder name...
	$txt['delete_all'] = str_replace('PMBOX', $context['folder'] != 'sent' ? $txt['inbox'] : $txt['sent_items'], $txt['delete_all']);
}

// Delete ALL the messages!
function MessageKillAll()
{
	global $context;

	checkSession('get');

	// If all then delete all messages the user has.
	if ($_REQUEST['f'] == 'all')
		deleteMessages(null, null);
	// Otherwise just the selected folder.
	else
		deleteMessages(null, $_REQUEST['f'] != 'sent' ? 'inbox' : 'sent');

	// Done... all gone.
	redirectexit($context['current_label_redirect']);
}

// This function allows the user to delete all messages older than so many days.
function MessagePrune()
{
	global $txt, $context;

	// Actually delete the messages.
	if (isset($_REQUEST['age']))
	{
		checkSession();

		// Calculate the time to delete before.
		$deleteTime = max(0, time() - (86400 * (int) $_REQUEST['age']));

		// Array to store the IDs in.
		$toDelete = array();

		// Select all the messages they have sent older than $deleteTime.
		$request = wesql::query('
			SELECT id_pm
			FROM {db_prefix}personal_messages
			WHERE deleted_by_sender = {int:not_deleted}
				AND id_member_from = {int:current_member}
				AND msgtime < {int:msgtime}',
			array(
				'current_member' => MID,
				'not_deleted' => 0,
				'msgtime' => $deleteTime,
			)
		);
		while ($row = wesql::fetch_row($request))
			$toDelete[] = $row[0];
		wesql::free_result($request);

		// Select all messages in their inbox older than $deleteTime.
		$request = wesql::query('
			SELECT pmr.id_pm
			FROM {db_prefix}pm_recipients AS pmr
				INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			WHERE pmr.deleted = {int:not_deleted}
				AND pmr.id_member = {int:current_member}
				AND pm.msgtime < {int:msgtime}',
			array(
				'current_member' => MID,
				'not_deleted' => 0,
				'msgtime' => $deleteTime,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$toDelete[] = $row['id_pm'];
		wesql::free_result($request);

		// Delete the actual messages.
		deleteMessages($toDelete);

		// Go back to their inbox.
		redirectexit($context['current_label_redirect']);
	}

	// Build the link tree elements.
	add_linktree($txt['pm_prune'], '<URL>?action=pm;sa=prune');

	wetem::load('prune');
	$context['page_title'] = $txt['pm_prune'];
}

// Delete the specified personal messages.
function deleteMessages($personal_messages, $folder = null, $owner = null)
{
	if ($owner === null)
		$owner = array(MID);
	elseif (empty($owner))
		return;
	elseif (!is_array($owner))
		$owner = array($owner);

	if ($personal_messages !== null)
	{
		if (empty($personal_messages) || !is_array($personal_messages))
			return;

		foreach ($personal_messages as $index => $delete_id)
			$personal_messages[$index] = (int) $delete_id;

		$where = '
				AND id_pm IN ({array_int:pm_list})';
	}
	else
		$where = '';

	if ($folder == 'sent' || $folder === null)
	{
		wesql::query('
			UPDATE {db_prefix}personal_messages
			SET deleted_by_sender = {int:is_deleted}
			WHERE id_member_from IN ({array_int:member_list})
				AND deleted_by_sender = {int:not_deleted}' . $where,
			array(
				'member_list' => $owner,
				'is_deleted' => 1,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);
	}
	if ($folder != 'sent' || $folder === null)
	{
		// Calculate the number of messages each member's gonna lose...
		$request = wesql::query('
			SELECT id_member, COUNT(*) AS num_deleted_messages, CASE WHEN is_read & 1 >= 1 THEN 1 ELSE 0 END AS is_read
			FROM {db_prefix}pm_recipients
			WHERE id_member IN ({array_int:member_list})
				AND deleted = {int:not_deleted}' . $where . '
			GROUP BY id_member, is_read',
			array(
				'member_list' => $owner,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);
		// ...And update the statistics accordingly - now including unread messages!.
		while ($row = wesql::fetch_assoc($request))
		{
			if ($row['is_read'])
				updateMemberData($row['id_member'], array('instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages']));
			else
				updateMemberData($row['id_member'], array('instant_messages' => $where == '' ? 0 : 'instant_messages - ' . $row['num_deleted_messages'], 'unread_messages' => $where == '' ? 0 : 'unread_messages - ' . $row['num_deleted_messages']));

			// If this is the current member we need to make their message count correct.
			if (MID == $row['id_member'])
			{
				we::$user['messages'] -= $row['num_deleted_messages'];
				if (!($row['is_read']))
					we::$user['unread_messages'] -= $row['num_deleted_messages'];
			}
		}
		wesql::free_result($request);

		// Do the actual deletion.
		wesql::query('
			UPDATE {db_prefix}pm_recipients
			SET deleted = {int:is_deleted}
			WHERE id_member IN ({array_int:member_list})
				AND deleted = {int:not_deleted}' . $where,
			array(
				'member_list' => $owner,
				'is_deleted' => 1,
				'not_deleted' => 0,
				'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
			)
		);
	}

	// If sender and recipients all have deleted their message, it can be removed.
	$request = wesql::query('
		SELECT pm.id_pm AS sender, pmr.id_pm
		FROM {db_prefix}personal_messages AS pm
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm AND pmr.deleted = {int:not_deleted})
		WHERE pm.deleted_by_sender = {int:is_deleted}
			' . str_replace('id_pm', 'pm.id_pm', $where) . '
		GROUP BY sender, pmr.id_pm
		HAVING pmr.id_pm IS null',
		array(
			'not_deleted' => 0,
			'is_deleted' => 1,
			'pm_list' => $personal_messages !== null ? array_unique($personal_messages) : array(),
		)
	);
	$remove_pms = array();
	while ($row = wesql::fetch_assoc($request))
		$remove_pms[] = $row['sender'];
	wesql::free_result($request);

	if (!empty($remove_pms))
	{
		wesql::query('
			DELETE FROM {db_prefix}personal_messages
			WHERE id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $remove_pms,
			)
		);

		wesql::query('
			DELETE FROM {db_prefix}pm_recipients
			WHERE id_pm IN ({array_int:pm_list})',
			array(
				'pm_list' => $remove_pms,
			)
		);
	}

	// Any cached numbers may be wrong now.
	cache_put_data('labelCounts:' . MID, null, 720);
}

// Mark personal messages read.
function markMessages($personal_messages = null, $label = null, $owner = null)
{
	if ($owner === null)
		$owner = MID;

	wesql::query('
		UPDATE {db_prefix}pm_recipients
		SET is_read = is_read | 1
		WHERE id_member = {int:id_member}
			AND NOT (is_read & 1 >= 1)' . ($label === null ? '' : '
			AND FIND_IN_SET({string:label}, labels) != 0') . ($personal_messages !== null ? '
			AND id_pm IN ({array_int:personal_messages})' : ''),
		array(
			'personal_messages' => $personal_messages,
			'id_member' => $owner,
			'label' => $label,
		)
	);

	// If something wasn't marked as read, get the number of unread messages remaining.
	if (wesql::affected_rows() > 0)
		recalculateUnread($owner);
}

function recalculateUnread($owner)
{
	global $context;

	if ($owner == MID)
		foreach ($context['labels'] as $label)
			$context['labels'][(int) $label['id']]['unread_messages'] = 0;

	$result = wesql::query('
		SELECT labels, COUNT(*) AS num
		FROM {db_prefix}pm_recipients
		WHERE id_member = {int:id_member}
			AND NOT (is_read & 1 >= 1)
			AND deleted = {int:is_not_deleted}
		GROUP BY labels',
		array(
			'id_member' => $owner,
			'is_not_deleted' => 0,
		)
	);
	$total_unread = 0;
	while ($row = wesql::fetch_assoc($result))
	{
		$total_unread += $row['num'];

		if ($owner != MID)
			continue;

		$this_labels = explode(',', $row['labels']);
		foreach ($this_labels as $this_label)
			$context['labels'][(int) $this_label]['unread_messages'] += $row['num'];
	}
	wesql::free_result($result);

	// Need to store all this.
	cache_put_data('labelCounts:' . $owner, $context['labels'], 720);
	updateMemberData($owner, array('unread_messages' => $total_unread));

	// If it was for the current member, reflect this in the we::$user array too.
	if ($owner == MID)
		we::$user['unread_messages'] = $total_unread;
}

// This function handles adding, deleting and editing labels on messages.
function ManageLabels()
{
	global $txt, $context;

	// Build the link tree elements...
	$context['linktree'][] = array(
		'url' => '<URL>?action=pm;sa=manlabels',
		'name' => $txt['pm_manage_labels']
	);

	$context['page_title'] = $txt['pm_manage_labels'];
	wetem::load('labels');

	$the_labels = array();
	// Add all existing labels to the array to save, slashing them as necessary...
	foreach ($context['labels'] as $label)
	{
		if ($label['id'] != -1)
			$the_labels[$label['id']] = $label['name'];
	}

	if (isset($_POST[$context['session_var']]))
	{
		checkSession('post');

		// This will be for updating messages.
		$message_changes = array();
		$new_labels = array();
		$rule_changes = array();

		// Will most likely need this.
		loadRules();

		// Adding a new label?
		if (isset($_POST['add']))
		{
			$_POST['label'] = strtr(westr::htmlspecialchars(trim($_POST['label'])), array(',' => '&#044;'));

			if (westr::strlen($_POST['label']) > 30)
				$_POST['label'] = westr::substr($_POST['label'], 0, 30);
			if ($_POST['label'] != '')
				$the_labels[] = $_POST['label'];
		}
		// Deleting an existing label?
		elseif (isset($_POST['delete'], $_POST['delete_label']))
		{
			$i = 0;
			foreach ($the_labels as $id => $name)
			{
				if (isset($_POST['delete_label'][$id]))
				{
					unset($the_labels[$id]);
					$message_changes[$id] = true;
				}
				else
					$new_labels[$id] = $i++;
			}
		}
		// The hardest one to deal with... changes.
		elseif (isset($_POST['save']) && !empty($_POST['label_name']))
		{
			$i = 0;
			foreach ($the_labels as $id => $name)
			{
				if ($id == -1)
					continue;
				elseif (isset($_POST['label_name'][$id]))
				{
					$_POST['label_name'][$id] = trim(strtr(westr::htmlspecialchars($_POST['label_name'][$id]), array(',' => '&#044;')));

					if (westr::strlen($_POST['label_name'][$id]) > 30)
						$_POST['label_name'][$id] = westr::substr($_POST['label_name'][$id], 0, 30);
					if ($_POST['label_name'][$id] != '')
					{
						$the_labels[(int) $id] = $_POST['label_name'][$id];
						$new_labels[$id] = $i++;
					}
					else
					{
						unset($the_labels[(int) $id]);
						$message_changes[(int) $id] = true;
					}
				}
				else
					$new_labels[$id] = $i++;
			}
		}

		// Save the label status.
		updateMyData(array('pmlabs' => implode(',', $the_labels)));

		// Update all the messages currently with any label changes in them!
		if (!empty($message_changes))
		{
			$searchArray = array_keys($message_changes);

			if (!empty($new_labels))
			{
				for ($i = max($searchArray) + 1, $n = max(array_keys($new_labels)); $i <= $n; $i++)
					$searchArray[] = $i;
			}

			// Now find the messages to change.
			$request = wesql::query('
				SELECT id_pm, labels
				FROM {db_prefix}pm_recipients
				WHERE FIND_IN_SET({raw:find_label_implode}, labels) != 0
					AND id_member = {int:current_member}',
				array(
					'current_member' => MID,
					'find_label_implode' => '\'' . implode('\', labels) != 0 OR FIND_IN_SET(\'', $searchArray) . '\'',
				)
			);
			while ($row = wesql::fetch_assoc($request))
			{
				// Do the long task of updating them...
				$toChange = explode(',', $row['labels']);

				foreach ($toChange as $key => $value)
					if (in_array($value, $searchArray))
					{
						if (isset($new_labels[$value]))
							$toChange[$key] = $new_labels[$value];
						else
							unset($toChange[$key]);
					}

				if (empty($toChange))
					$toChange[] = '-1';

				// Update the message.
				wesql::query('
					UPDATE {db_prefix}pm_recipients
					SET labels = {string:new_labels}
					WHERE id_pm = {int:id_pm}
						AND id_member = {int:current_member}',
					array(
						'current_member' => MID,
						'id_pm' => $row['id_pm'],
						'new_labels' => implode(',', array_unique($toChange)),
					)
				);
			}
			wesql::free_result($request);

			// Now do the same the rules - check through each rule.
			foreach ($context['rules'] as $k => $rule)
			{
				// Each action...
				foreach ($rule['actions'] as $k2 => $action)
				{
					if ($action['t'] != 'lab' || !in_array($action['v'], $searchArray))
						continue;

					$rule_changes[] = $rule['id'];
					// If we're here we have a label which is either changed or gone...
					if (isset($new_labels[$action['v']]))
						$context['rules'][$k]['actions'][$k2]['v'] = $new_labels[$action['v']];
					else
						unset($context['rules'][$k]['actions'][$k2]);
				}
			}
		}

		// If we have rules to change do so now.
		if (!empty($rule_changes))
		{
			$rule_changes = array_unique($rule_changes);
			// Update/delete as appropriate.
			foreach ($rule_changes as $k => $id)
				if (!empty($context['rules'][$id]['actions']))
				{
					wesql::query('
						UPDATE {db_prefix}pm_rules
						SET actions = {string:actions}
						WHERE id_rule = {int:id_rule}
							AND id_member = {int:current_member}',
						array(
							'current_member' => MID,
							'id_rule' => $id,
							'actions' => serialize($context['rules'][$id]['actions']),
						)
					);
					unset($rule_changes[$k]);
				}

			// Anything left here means it's lost all actions...
			if (!empty($rule_changes))
				wesql::query('
					DELETE FROM {db_prefix}pm_rules
					WHERE id_rule IN ({array_int:rule_list})
							AND id_member = {int:current_member}',
					array(
						'current_member' => MID,
						'rule_list' => $rule_changes,
					)
				);
		}

		// Make sure we're not caching this!
		cache_put_data('labelCounts:' . MID, null, 720);

		// To make the changes appear right away, redirect.
		redirectexit('action=pm;sa=manlabels');
	}
}

// Edit Personal Message Settings
function MessageSettings()
{
	global $txt, $context, $profile_vars, $cur_profile, $user_profile;

	// Need this for the display.
	loadSource(array('Profile', 'Profile-Modify'));

	// We want them to submit back to here.
	$context['profile_custom_submit_url'] = '<URL>?action=pm;sa=settings;save';

	loadMemberData(MID, false, 'profile');
	$cur_profile = $user_profile[MID];

	loadLanguage('Profile');
	loadTemplate('Profile');

	we::$user['is_owner'] = true;
	$context['page_title'] = $txt['pm_settings'];
	$context['id_member'] = MID;
	$context['require_password'] = false;
	$context['menu_item_selected'] = 'settings';
	$context['submit_button_text'] = $txt['pm_settings'];
	$context['profile_header_text'] = $txt['personal_messages'];

	// Add our position to the linktree.
	add_linktree($txt['pm_settings'], '<URL>?action=pm;sa=settings');

	// Are they saving?
	if (isset($_REQUEST['save']))
	{
		checkSession('post');

		// Mimic what profile would do.
		$_POST = htmltrim__recursive($_POST);
		$_POST = htmlspecialchars__recursive($_POST);

		// Save the fields.
		saveProfileFields();

		if (!empty($profile_vars))
			updateMemberData(MID, $profile_vars);
	}

	// Load up the fields.
	pmprefs(MID);
}

// Allows a user to report a personal message they receive to the administrator.
function ReportMessage()
{
	global $txt, $context, $settings;

	// Check that this feature is even enabled!
	if (empty($_REQUEST['pmsg']))
		fatal_lang_error('no_access', false);

	$pmsg = (int) $_REQUEST['pmsg'];

	if (!isAccessiblePM($pmsg, 'inbox'))
		fatal_lang_error('no_access', false);

	$context['pm_id'] = $pmsg;
	$context['page_title'] = $txt['pm_report_title'];

	// If we're here, just send the user to the template, with a few useful context bits.
	if (!isset($_POST['report']))
	{
		wetem::load('report_message');

		// !!! I don't like being able to pick who to send it to. Favoritism, etc. sucks.
		// Now, get all the administrators.
		$request = wesql::query('
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0
			ORDER BY real_name',
			array(
				'admin_group' => 1,
			)
		);
		$context['admins'] = array();
		while ($row = wesql::fetch_assoc($request))
			$context['admins'][$row['id_member']] = $row['real_name'];
		wesql::free_result($request);

		// How many admins in total?
		$context['admin_count'] = count($context['admins']);
	}
	// Otherwise, let's get down to the sending stuff.
	else
	{
		// Check the session before proceeding any further!
		checkSession('post');

		// First, pull out the message contents, and verify it actually went to them!
		$request = wesql::query('
			SELECT pm.subject, pm.body, pm.msgtime, pm.id_member_from, IFNULL(m.real_name, pm.from_name) AS sender_name
			FROM {db_prefix}personal_messages AS pm
				INNER JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm)
				LEFT JOIN {db_prefix}members AS m ON (m.id_member = pm.id_member_from)
			WHERE pm.id_pm = {int:id_pm}
				AND pmr.id_member = {int:current_member}
				AND pmr.deleted = {int:not_deleted}
			LIMIT 1',
			array(
				'current_member' => MID,
				'id_pm' => $context['pm_id'],
				'not_deleted' => 0,
			)
		);
		// Can only be a hacker here!
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('no_access', false);
		list ($subject, $body, $time, $memberFromID, $memberFromName) = wesql::fetch_row($request);
		wesql::free_result($request);

		// Remove the line breaks...
		$body = preg_replace('~<br\s*/?\>~i', "\n", $body);

		// Get any other recipients of the email.
		$request = wesql::query('
			SELECT mem_to.id_member AS id_member_to, mem_to.real_name AS to_name, pmr.bcc
			FROM {db_prefix}pm_recipients AS pmr
				LEFT JOIN {db_prefix}members AS mem_to ON (mem_to.id_member = pmr.id_member)
			WHERE pmr.id_pm = {int:id_pm}
				AND pmr.id_member != {int:current_member}',
			array(
				'current_member' => MID,
				'id_pm' => $context['pm_id'],
			)
		);
		$recipients = array();
		$hidden_recipients = 0;
		while ($row = wesql::fetch_assoc($request))
		{
			// If it's hidden still don't reveal their names - privacy after all ;)
			if ($row['bcc'])
				$hidden_recipients++;
			else
				$recipients[] = '[url=' . SCRIPT . '?action=profile;u=' . $row['id_member_to'] . ']' . $row['to_name'] . '[/url]';
		}
		wesql::free_result($request);

		if ($hidden_recipients)
			$recipients[] = number_context('pm_report_pm_hidden', $hidden_recipients);

		// Now let's get out and loop through the admins.
		$request = wesql::query('
			SELECT id_member, real_name, lngfile
			FROM {db_prefix}members
			WHERE (id_group = {int:admin_id} OR FIND_IN_SET({int:admin_id}, additional_groups) != 0)
				' . (empty($_POST['id_admin']) ? '' : 'AND id_member = {int:specific_admin}') . '
			ORDER BY lngfile',
			array(
				'admin_id' => 1,
				'specific_admin' => isset($_POST['id_admin']) ? (int) $_POST['id_admin'] : 0,
			)
		);

		// Maybe we shouldn't advertise this?
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('no_access', false);

		$memberFromName = un_htmlspecialchars($memberFromName);

		// Prepare the message storage array.
		$messagesToSend = array();
		// Loop through each admin, and add them to the right language pile...
		while ($row = wesql::fetch_assoc($request))
		{
			// Need to send in the correct language!
			$cur_language = empty($row['lngfile']) || empty($settings['userLanguage']) ? $settings['language'] : $row['lngfile'];

			if (!isset($messagesToSend[$cur_language]))
			{
				loadLanguage('PersonalMessage', $cur_language, false);

				// Make the body.
				$report_body = str_replace(array('{REPORTER}', '{SENDER}'), array(un_htmlspecialchars(we::$user['name']), $memberFromName), $txt['pm_report_pm_user_sent']);
				$report_body .= "\n" . '[b]' . $_POST['reason'] . '[/b]' . "\n\n";
				if (!empty($recipients))
					$report_body .= $txt['pm_report_pm_other_recipients'] . ' ' . implode(', ', $recipients) . "\n\n";
				$report_body .= $txt['pm_report_pm_unedited_below'] . "\n" . '[quote author=' . (empty($memberFromID) ? '&quot;' . $memberFromName . '&quot;' : $memberFromName . ' link=action=profile;u=' . $memberFromID . ' date=' . $time) . ']' . "\n" . un_htmlspecialchars($body) . '[/quote]';

				// Plonk it in the array ;)
				$messagesToSend[$cur_language] = array(
					'subject' => (westr::strpos($subject, $txt['pm_report_pm_subject']) === false ? $txt['pm_report_pm_subject'] : '') . un_htmlspecialchars($subject),
					'body' => $report_body,
					'recipients' => array(
						'to' => array(),
						'bcc' => array()
					),
				);
			}

			// Add them to the list.
			$messagesToSend[$cur_language]['recipients']['to'][$row['id_member']] = $row['id_member'];
		}
		wesql::free_result($request);

		// Send a different email for each language.
		foreach ($messagesToSend as $lang => $message)
			sendpm($message['recipients'], $message['subject'], $message['body'], false);

		// Give the user their own language back!
		if (!empty($settings['userLanguage']))
			loadLanguage('PersonalMessage', '', false);

		// Leave them with a template.
		wetem::load('report_message_complete');
	}
}

// List all rules, and allow adding/entering etc....
function ManageRules()
{
	global $txt, $context;

	// The link tree - gotta have this :o
	add_linktree($txt['pm_manage_rules'], '<URL>?action=pm;sa=manrules');

	$context['page_title'] = $txt['pm_manage_rules'];
	wetem::load('rules');

	// Load them... load them!!
	loadRules();

	// Likely to need all the groups!
	$request = wesql::query('
		SELECT mg.id_group, mg.group_name, IFNULL(gm.id_member, 0) AS can_moderate, mg.hidden
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}group_moderators AS gm ON (gm.id_group = mg.id_group AND gm.id_member = {int:current_member})
		WHERE mg.min_posts = {int:min_posts}
			AND mg.id_group != {int:moderator_group}
			AND mg.hidden = {int:not_hidden}
		ORDER BY mg.group_name',
		array(
			'current_member' => MID,
			'min_posts' => -1,
			'moderator_group' => 3,
			'not_hidden' => 0,
		)
	);
	$context['groups'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Hide hidden groups!
		if ($row['hidden'] && !$row['can_moderate'] && !allowedTo('manage_membergroups'))
			continue;

		$context['groups'][$row['id_group']] = $row['group_name'];
	}
	wesql::free_result($request);

	// Applying all rules?
	if (isset($_REQUEST['apply']))
	{
		checkSession();

		applyRules(true);
		redirectexit('action=pm;sa=manrules');
	}
	// Editing a specific one?
	if (isset($_REQUEST['add']))
	{
		$context['rid'] = isset($_GET['rid'], $context['rules'][$_GET['rid']]) ? (int) $_GET['rid'] : 0;
		wetem::load('add_rule');

		// Current rule information...
		if ($context['rid'])
		{
			$context['rule'] = $context['rules'][$context['rid']];
			$members = array();
			// Need to get member names!
			foreach ($context['rule']['criteria'] as $k => $criteria)
				if ($criteria['t'] == 'mid' && !empty($criteria['v']))
					$members[(int) $criteria['v']] = $k;

			if (!empty($members))
			{
				$request = wesql::query('
					SELECT id_member, member_name
					FROM {db_prefix}members
					WHERE id_member IN ({array_int:member_list})',
					array(
						'member_list' => array_keys($members),
					)
				);
				while ($row = wesql::fetch_assoc($request))
					$context['rule']['criteria'][$members[$row['id_member']]]['v'] = $row['member_name'];
				wesql::free_result($request);
			}
		}
		else
			$context['rule'] = array(
				'id' => '',
				'name' => '',
				'criteria' => array(),
				'actions' => array(),
				'logic' => 'and',
			);
	}
	// Saving?
	elseif (isset($_GET['save']))
	{
		checkSession('post');
		$context['rid'] = isset($_GET['rid'], $context['rules'][$_GET['rid']]) ? (int) $_GET['rid'] : 0;

		// Name is easy!
		$ruleName = westr::htmlspecialchars(trim($_POST['rule_name']));
		if (empty($ruleName))
			fatal_lang_error('pm_rule_no_name', false);

		// Sanity check...
		if (empty($_POST['ruletype']) || empty($_POST['acttype']))
			fatal_lang_error('pm_rule_no_criteria', false);

		// Let's do the criteria first - it's also hardest!
		$criteria = array();
		foreach ($_POST['ruletype'] as $ind => $type)
		{
			// Check everything is here...
			if ($type == 'gid' && (!isset($_POST['ruledefgroup'][$ind]) || !isset($context['groups'][$_POST['ruledefgroup'][$ind]])))
				continue;
			elseif ($type != 'bud' && !isset($_POST['ruledef'][$ind]))
				continue;

			// Members need to be found.
			if ($type == 'mid')
			{
				$name = trim($_POST['ruledef'][$ind]);
				$request = wesql::query('
					SELECT id_member
					FROM {db_prefix}members
					WHERE real_name = {string:member_name}
						OR member_name = {string:member_name}',
					array(
						'member_name' => $name,
					)
				);
				if (wesql::num_rows($request) == 0)
					continue;
				list ($memID) = wesql::fetch_row($request);
				wesql::free_result($request);

				$criteria[] = array('t' => 'mid', 'v' => $memID);
			}
			elseif ($type == 'bud')
				$criteria[] = array('t' => 'bud', 'v' => 1);
			elseif ($type == 'gid')
				$criteria[] = array('t' => 'gid', 'v' => (int) $_POST['ruledefgroup'][$ind]);
			elseif (in_array($type, array('sub', 'msg')) && trim($_POST['ruledef'][$ind]) != '')
				$criteria[] = array('t' => $type, 'v' => westr::htmlspecialchars(trim($_POST['ruledef'][$ind])));
		}

		// Also do the actions!
		$actions = array();
		$doDelete = 0;
		$isOr = $_POST['rule_logic'] == 'or' ? 1 : 0;
		foreach ($_POST['acttype'] as $ind => $type)
		{
			// Picking a valid label?
			if ($type == 'lab' && (!isset($_POST['labdef'][$ind]) || !isset($context['labels'][$_POST['labdef'][$ind] - 1])))
				continue;

			// Record what we're doing.
			if ($type == 'del')
				$doDelete = 1;
			elseif ($type == 'lab')
				$actions[] = array('t' => 'lab', 'v' => (int) $_POST['labdef'][$ind] - 1);
		}

		if (empty($criteria) || (empty($actions) && !$doDelete))
			fatal_lang_error('pm_rule_no_criteria', false);

		// What are we storing?
		$criteria = serialize($criteria);
		$actions = serialize($actions);

		// Create the rule?
		if (empty($context['rid']))
			wesql::insert('',
				'{db_prefix}pm_rules',
				array(
					'id_member' => 'int', 'rule_name' => 'string', 'criteria' => 'string', 'actions' => 'string',
					'delete_pm' => 'int', 'is_or' => 'int',
				),
				array(
					MID, $ruleName, $criteria, $actions, $doDelete, $isOr,
				)
			);
		else
			wesql::query('
				UPDATE {db_prefix}pm_rules
				SET rule_name = {string:rule_name}, criteria = {string:criteria}, actions = {string:actions},
					delete_pm = {int:delete_pm}, is_or = {int:is_or}
				WHERE id_rule = {int:id_rule}
					AND id_member = {int:current_member}',
				array(
					'current_member' => MID,
					'delete_pm' => $doDelete,
					'is_or' => $isOr,
					'id_rule' => $context['rid'],
					'rule_name' => $ruleName,
					'criteria' => $criteria,
					'actions' => $actions,
				)
			);

		redirectexit('action=pm;sa=manrules');
	}
	// Deleting?
	elseif (isset($_POST['delselected']) && !empty($_POST['delrule']))
	{
		checkSession('post');
		$toDelete = array();
		foreach ($_POST['delrule'] as $k => $v)
			$toDelete[] = (int) $k;

		if (!empty($toDelete))
			wesql::query('
				DELETE FROM {db_prefix}pm_rules
				WHERE id_rule IN ({array_int:delete_list})
					AND id_member = {int:current_member}',
				array(
					'current_member' => MID,
					'delete_list' => $toDelete,
				)
			);

		redirectexit('action=pm;sa=manrules');
	}
}

// This will apply rules to all unread messages. If all_messages is set will, clearly, do it to all!
function applyRules($all_messages = false)
{
	global $context, $options;

	// Want this - duh!
	loadRules();

	// No rules?
	if (empty($context['rules']))
		return;

	// Just unread ones?
	$ruleQuery = $all_messages ? '' : ' AND pmr.is_new = 1';

	// !! Apply all should have timeout protection!
	// Get all the messages that match this.
	$request = wesql::query('
		SELECT
			pmr.id_pm, pm.id_member_from, pm.subject, pm.body, mem.id_group, pmr.labels
		FROM {db_prefix}pm_recipients AS pmr
			INNER JOIN {db_prefix}personal_messages AS pm ON (pm.id_pm = pmr.id_pm)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = pm.id_member_from)
		WHERE pmr.id_member = {int:current_member}
			AND pmr.deleted = {int:not_deleted}
			' . $ruleQuery,
		array(
			'current_member' => MID,
			'not_deleted' => 0,
		)
	);
	$actions = array();
	while ($row = wesql::fetch_assoc($request))
	{
		foreach ($context['rules'] as $rule)
		{
			$match = false;
			// Loop through all the criteria hoping to make a match.
			foreach ($rule['criteria'] as $criterium)
			{
				if (($criterium['t'] == 'mid' && $criterium['v'] == $row['id_member_from']) || ($criterium['t'] == 'gid' && $criterium['v'] == $row['id_group']) || ($criterium['t'] == 'sub' && strpos($row['subject'], $criterium['v']) !== false) || ($criterium['t'] == 'msg' && strpos($row['body'], $criterium['v']) !== false))
					$match = true;
				// If we're adding and one criteria don't match then we stop!
				elseif ($rule['logic'] == 'and')
				{
					$match = false;
					break;
				}
			}

			// If we have a match the rule must be true - act!
			if ($match)
			{
				if ($rule['delete'])
					$actions['deletes'][] = $row['id_pm'];
				else
				{
					foreach ($rule['actions'] as $ruleAction)
					{
						if ($ruleAction['t'] == 'lab')
						{
							// Get a basic pot started!
							if (!isset($actions['labels'][$row['id_pm']]))
								$actions['labels'][$row['id_pm']] = empty($row['labels']) ? array() : explode(',', $row['labels']);
							$actions['labels'][$row['id_pm']][] = $ruleAction['v'];
						}
					}
				}
			}
		}
	}
	wesql::free_result($request);

	// Deletes are easy!
	if (!empty($actions['deletes']))
		deleteMessages($actions['deletes']);

	// Relabel?
	if (!empty($actions['labels']))
	{
		foreach ($actions['labels'] as $pm => $labels)
		{
			// Quickly check each label is valid!
			$realLabels = array();
			foreach ($context['labels'] as $label)
				if (in_array($label['id'], $labels) && ($label['id'] != -1 || empty($options['pm_remove_inbox_label'])))
					$realLabels[] = $label['id'];

			wesql::query('
				UPDATE {db_prefix}pm_recipients
				SET labels = {string:new_labels}
				WHERE id_pm = {int:id_pm}
					AND id_member = {int:current_member}',
				array(
					'current_member' => MID,
					'id_pm' => $pm,
					'new_labels' => empty($realLabels) ? '' : implode(',', $realLabels),
				)
			);
		}
	}
}

// Load up all the rules for the current user.
function loadRules($reload = false)
{
	global $context;

	if (isset($context['rules']) && !$reload)
		return;

	$request = wesql::query('
		SELECT
			id_rule, rule_name, criteria, actions, delete_pm, is_or
		FROM {db_prefix}pm_rules
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => MID,
		)
	);
	$context['rules'] = array();
	// Simply fill in the data!
	while ($row = wesql::fetch_assoc($request))
	{
		$context['rules'][$row['id_rule']] = array(
			'id' => $row['id_rule'],
			'name' => $row['rule_name'],
			'criteria' => unserialize($row['criteria']),
			'actions' => unserialize($row['actions']),
			'delete' => $row['delete_pm'],
			'logic' => $row['is_or'] ? 'or' : 'and',
		);

		if ($row['delete_pm'])
			$context['rules'][$row['id_rule']]['actions'][] = array('t' => 'del', 'v' => 1);
	}
	wesql::free_result($request);
}

// Check if the PM is available to the current user.
function isAccessiblePM($pmID, $validFor = 'in_or_outbox')
{
	$request = wesql::query('
		SELECT
			pm.id_member_from = {int:id_current_member} AND pm.deleted_by_sender = {int:not_deleted} AS valid_for_outbox,
			pmr.id_pm IS NOT NULL AS valid_for_inbox
		FROM {db_prefix}personal_messages AS pm
			LEFT JOIN {db_prefix}pm_recipients AS pmr ON (pmr.id_pm = pm.id_pm AND pmr.id_member = {int:id_current_member} AND pmr.deleted = {int:not_deleted})
		WHERE pm.id_pm = {int:id_pm}
			AND ((pm.id_member_from = {int:id_current_member} AND pm.deleted_by_sender = {int:not_deleted}) OR pmr.id_pm IS NOT NULL)',
		array(
			'id_pm' => $pmID,
			'id_current_member' => MID,
			'not_deleted' => 0,
		)
	);

	if (wesql::num_rows($request) === 0)
	{
		wesql::free_result($request);
		return false;
	}

	$validationResult = wesql::fetch_assoc($request);
	wesql::free_result($request);

	switch ($validFor)
	{
		case 'inbox':
			return !empty($validationResult['valid_for_inbox']);
		break;

		case 'outbox':
			return !empty($validationResult['valid_for_outbox']);
		break;

		case 'in_or_outbox':
			return !empty($validationResult['valid_for_inbox']) || !empty($validationResult['valid_for_outbox']);
		break;

		default:
			trigger_error('Undefined validation type given', E_USER_ERROR);
		break;
	}
}

function MessageDrafts()
{
	global $context, $txt, $settings, $user_profile;

	loadLanguage('PersonalMessage');

	// Are we deleting any drafts here?
	if (isset($_GET['deleteall']))
	{
		checkSession('post');
		wesql::query('
			DELETE FROM {db_prefix}drafts
			WHERE is_pm = {int:is_pm}
				AND id_member = {int:member}',
			array(
				'is_pm' => 1,
				'member' => MID,
			)
		);

		redirectexit('action=pm;sa=showdrafts');
	}
	elseif (!empty($_GET['delete']))
	{
		$draft_id = (int) $_GET['delete'];
		checkSession('get');

		wesql::query('
			DELETE FROM {db_prefix}drafts
			WHERE id_draft = {int:draft}
				AND id_member = {int:member}
			LIMIT 1',
			array(
				'draft' => $draft_id,
				'member' => MID,
			)
		);

		if (AJAX)
			obExit(false);
		else
			redirectexit('action=pm;sa=showdrafts');
	}

	// Some initial context.
	$context['start'] = (int) $_REQUEST['start'];
	wetem::load('pm_drafts');
	$context['page_title'] = $txt['showDrafts'];

	if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		$_REQUEST['viewscount'] = 10;

	// Get the count of applicable drafts
	$request = wesql::query('
		SELECT COUNT(id_draft)
		FROM {db_prefix}drafts AS d
		WHERE id_member = {int:member}
			AND is_pm = {int:is_pm}',
		array(
			'member' => MID,
			'is_pm' => 1,
		)
	);
	list ($msgCount) = wesql::fetch_row($request);
	wesql::free_result($request);

	$reverse = false;
	$maxIndex = (int) $settings['defaultMaxMessages'];

	// Make sure the starting place makes sense and construct our friend the page index.
	$context['page_index'] = template_page_index('<URL>?action=pm;sa=showdrafts', $context['start'], $msgCount, $maxIndex);
	$context['current_page'] = $context['start'] / $maxIndex;

	// Reverse the query if we're past 50% of the pages for better performance.
	$start = $context['start'];
	$reverse = $_REQUEST['start'] > $msgCount / 2;
	if ($reverse)
	{
		$maxIndex = $msgCount < $context['start'] + $settings['defaultMaxMessages'] + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : (int) $settings['defaultMaxMessages'];
		$start = $msgCount < $context['start'] + $settings['defaultMaxMessages'] + 1 || $msgCount < $context['start'] + $settings['defaultMaxMessages'] ? 0 : $msgCount - $context['start'] - $settings['defaultMaxMessages'];
	}

	// Find this user's drafts.
	$request = wesql::query('
		SELECT
			d.id_draft, d.subject, d.body, d.post_time, d.id_context, d.extra
		FROM {db_prefix}drafts AS d
		WHERE d.id_member = {int:current_member}
			AND d.is_pm = {int:is_pm}
		ORDER BY d.post_time ' . ($reverse ? 'ASC' : 'DESC') . '
		LIMIT ' . $start . ', ' . $maxIndex,
		array(
			'current_member' => MID,
			'is_pm' => 1,
		)
	);

	// Start counting at the number of the first message displayed.
	$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
	$context['posts'] = array();
	$users = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$row['subject'] = westr::htmltrim($row['subject']);
		if ($row['subject'] === '')
			$row['subject'] = $txt['no_subject'];

		$row['extra'] = empty($row['extra']) ? array() : unserialize($row['extra']);
		$row['extra']['recipients']['to'] = isset($row['extra']['recipients']['to']) ? $row['extra']['recipients']['to'] : array();
		$row['extra']['recipients']['bcc'] = isset($row['extra']['recipients']['bcc']) ? $row['extra']['recipients']['bcc'] : array();
		$users = array_merge($users, $row['extra']['recipients']['to'], $row['extra']['recipients']['bcc']);

		censorText($row['body']);
		censorText($row['subject']);

		// Do the code.
		$row['body'] = parse_bbc($row['body'], 'pm-draft', array('smileys' => !empty($row['extra']['smileys_enabled']), 'cache' => 'pmdraft' . $row['id_draft']));

		// And the array...
		$context['posts'][$counter += $reverse ? -1 : 1] = array(
			'id' => $row['id_draft'],
			'subject' => $row['subject'],
			'body' => $row['body'],
			'counter' => $counter,
			'alternate' => $counter % 2,
			'on_time' => on_timeformat($row['post_time']),
			'timestamp' => $row['post_time'],
			'recipients' => $row['extra']['recipients'],
			'pmsg' => $row['id_context'],
		);
	}
	wesql::free_result($request);

	// Now get all the users that we want to be sending to.
	if (!empty($users))
	{
		$users = loadMemberData(array_unique($users));
		foreach ($context['posts'] as $id => $post)
		{
			$recipients = $post['recipients'];
			foreach ($recipients as $recType => $recList)
			{
				$context['posts'][$id]['recipients'][$recType] = array();
				foreach ($recList as $recipient)
					$context['posts'][$id]['recipients'][$recType][] = '<a href="<URL>?action=profile;u=' . $recipient . '" target="_blank">' . $user_profile[$recipient]['real_name'] . '</a>';
			}
		}
	}

	// All posts were retrieved in reverse order, get them right again.
	if ($reverse)
		$context['posts'] = array_reverse($context['posts'], true);
}
