<?php
/**********************************************************************************
* SendTopic.php                                                                   *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC4                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

/*	The functions in this file deal with sending topics to a moderator. */

/**
 * Creates a form for the user to report a message to moderators.
 *
 * - uses the ReportToModerator template, main sub template.
 * - requires the report_any permission.
 * - uses ReportToModerator2() if post data was sent.
 * - accessed through ?action=reporttm.
 */
function Reporttm()
{
	global $txt, $topic, $modSettings, $user_info, $context;

	$context['robot_no_index'] = true;

	// You can't use this if it's off or you are not allowed to do it.
	isAllowedTo('report_any');

	// If they're posting, it should be processed by ReportToModerator2.
	if ((isset($_POST[$context['session_var']]) || isset($_POST['submit'])) && empty($context['post_errors']))
		ReportToModerator2();

	// We need a message ID to check!
	if (empty($_REQUEST['msg']) && empty($_REQUEST['mid']))
		fatal_lang_error('no_access', false);

	// For compatibility, accept mid, but we should be using msg. (not the flavor kind!)
	$_REQUEST['msg'] = empty($_REQUEST['msg']) ? (int) $_REQUEST['mid'] : (int) $_REQUEST['msg'];

	// Check the message's ID - don't want anyone reporting a post they can't even see!
	$result = wesql::query('
		SELECT m.id_msg, m.id_member, t.id_member_started
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
		WHERE m.id_msg = {int:id_msg}
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'id_msg' => $_REQUEST['msg'],
		)
	);
	if (wesql::num_rows($result) == 0)
		fatal_lang_error('no_board', false);
	list ($_REQUEST['msg'], $member, $starter) = wesql::fetch_row($result);
	wesql::free_result($result);

	// Do we need to show the visual verification image?
	$context['require_verification'] = $user_info['is_guest'] && !empty($modSettings['guests_report_require_captcha']);
	if ($context['require_verification'])
	{
		loadSource('Subs-Editor');
		$verificationOptions = array(
			'id' => 'report',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// Show the inputs for the comment, etc.
	loadLanguage('Post');
	loadTemplate('SendTopic');

	$context['comment_body'] = !isset($_POST['comment']) ? '' : trim($_POST['comment']);
	$context['email_address'] = !isset($_POST['email']) ? '' : trim($_POST['email']);

	// This is here so that the user could, in theory, be redirected back to the topic.
	$context['message_id'] = $_REQUEST['msg'];

	$context['page_title'] = $txt['report_to_mod'];
	$context['sub_template'] = 'report';
}

/**
 * Actions sending reports of posts to moderators.
 *
 * - sends off emails to all the moderators.
 * - sends to administrators and global moderators. (1 and 2)
 * - called by Reporttm(), and thus has the same permission and setting requirements as it does.
 * - accessed through ?action=reporttm when posting.
 */
function ReportToModerator2()
{
	global $txt, $scripturl, $topic, $board, $user_info, $modSettings, $language, $context;

	// Make sure they aren't spamming.
	spamProtection('reporttm');

	loadSource('Subs-Post');

	// No errors, yet.
	$post_errors = array();

	// Check their session.
	if (checkSession('post', '', false) != '')
		$post_errors[] = 'session_timeout';

	// Make sure we have a comment and it's clean.
	if (!isset($_POST['comment']) || westr::htmltrim($_POST['comment']) === '')
		$post_errors[] = 'no_comment';
	$poster_comment = strtr(westr::htmlspecialchars($_POST['comment']), array("\r" => '', "\n" => '', "\t" => ''));

	// Guests need to provide their address!
	if ($user_info['is_guest'])
	{
		$_POST['email'] = !isset($_POST['email']) ? '' : trim($_POST['email']);
		if ($_POST['email'] === '')
			$post_errors[] = 'no_email';
		elseif (!is_valid_email($_POST['email']))
			$post_errors[] = 'bad_email';

		isBannedEmail($_POST['email'], 'cannot_post', sprintf($txt['you_are_post_banned'], $txt['guest_title']));

		$user_info['email'] = htmlspecialchars($_POST['email']);
	}

	// Could they get the right verification code?
	if ($user_info['is_guest'] && !empty($modSettings['guests_report_require_captcha']))
	{
		loadSource('Subs-Editor');
		$verificationOptions = array(
			'id' => 'report',
		);
		$context['require_verification'] = create_control_verification($verificationOptions, true);
		if (is_array($context['require_verification']))
			$post_errors = array_merge($post_errors, $context['require_verification']);
	}

	// Any errors?
	if (!empty($post_errors))
	{
		loadLanguage('Errors');

		$context['post_errors'] = array();
		foreach ($post_errors as $post_error)
			$context['post_errors'][] = $txt['error_' . $post_error];

		return Reporttm();
	}

	// Get the basic topic information, and make sure they can see it.
	$_POST['msg'] = (int) $_POST['msg'];

	$request = wesql::query('
		SELECT m.id_topic, m.id_board, m.subject, m.body, m.id_member AS id_poster, m.poster_name, mem.real_name
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (m.id_member = mem.id_member)
		WHERE m.id_msg = {int:id_msg}
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'id_msg' => $_POST['msg'],
		)
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('no_board', false);
	$message = wesql::fetch_assoc($request);
	wesql::free_result($request);

	$poster_name = un_htmlspecialchars($message['real_name']) . ($message['real_name'] != $message['poster_name'] ? ' (' . $message['poster_name'] . ')' : '');
	$reporterName = un_htmlspecialchars($user_info['name']) . ($user_info['name'] != $user_info['username'] && $user_info['username'] != '' ? ' (' . $user_info['username'] . ')' : '');
	$subject = un_htmlspecialchars($message['subject']);

	// Get a list of members with the moderate_board permission.
	loadSource('Subs-Members');
	$moderators = membersAllowedTo('moderate_board', $board);

	$request = wesql::query('
		SELECT id_member, email_address, lngfile, mod_prefs
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:moderator_list})
			AND notify_types != {int:notify_types}
		ORDER BY lngfile',
		array(
			'moderator_list' => $moderators,
			'notify_types' => 4,
		)
	);

	// Check that moderators do exist!
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('no_mods', false);

	// If we get here, I believe we should make a record of this, for historical significance, yabber.
	if (empty($modSettings['disable_log_report']))
	{
		$request2 = wesql::query('
			SELECT id_report, ignore_all
			FROM {db_prefix}log_reported
			WHERE id_msg = {int:id_msg}
				AND (closed = {int:not_closed} OR ignore_all = {int:ignored})
			ORDER BY ignore_all DESC',
			array(
				'id_msg' => $_POST['msg'],
				'not_closed' => 0,
				'ignored' => 1,
			)
		);
		if (wesql::num_rows($request2) != 0)
			list ($id_report, $ignore) = wesql::fetch_row($request2);
		wesql::free_result($request2);

		// If we're just going to ignore these, then who gives a monkeys...
		if (!empty($ignore))
			redirectexit('topic=' . $topic . '.msg' . $_POST['msg'] . '#msg' . $_POST['msg']);

		// Already reported? My god, we could be dealing with a real rogue here...
		if (!empty($id_report))
			wesql::query('
				UPDATE {db_prefix}log_reported
				SET num_reports = num_reports + 1, time_updated = {int:current_time}
				WHERE id_report = {int:id_report}',
				array(
					'current_time' => time(),
					'id_report' => $id_report,
				)
			);
		// Otherwise, we shall make one!
		else
		{
			if (empty($message['real_name']))
				$message['real_name'] = $message['poster_name'];

			wesql::insert('',
				'{db_prefix}log_reported',
				array(
					'id_msg' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'id_member' => 'int', 'membername' => 'string',
					'subject' => 'string', 'body' => 'string', 'time_started' => 'int', 'time_updated' => 'int',
					'num_reports' => 'int', 'closed' => 'int',
				),
				array(
					$_POST['msg'], $message['id_topic'], $message['id_board'], $message['id_poster'], $message['real_name'],
					$message['subject'], $message['body'], time(), time(), 1, 0,
				),
				array('id_report')
			);
			$id_report = wesql::insert_id();
		}

		// Now just add our report...
		if ($id_report)
		{
			wesql::insert('',
				'{db_prefix}log_reported_comments',
				array(
					'id_report' => 'int', 'id_member' => 'int', 'membername' => 'string', 'email_address' => 'string',
					'member_ip' => 'string', 'comment' => 'string', 'time_sent' => 'int',
				),
				array(
					$id_report, $user_info['id'], $user_info['name'], $user_info['email'],
					$user_info['ip'], $poster_comment, time(),
				),
				array('id_comment')
			);
		}
	}

	// Find out who the real moderators are - for mod preferences.
	$request2 = wesql::query('
		SELECT id_member
		FROM {db_prefix}moderators
		WHERE id_board = {int:current_board}',
		array(
			'current_board' => $board,
		)
	);
	$real_mods = array();
	while ($row = wesql::fetch_assoc($request2))
		$real_mods[] = $row['id_member'];
	wesql::free_result($request2);

	// Send every moderator an email.
	while ($row = wesql::fetch_assoc($request))
	{
		// Maybe they don't want to know?!
		if (!empty($row['mod_prefs']))
		{
			list (,, $pref_binary) = explode('|', $row['mod_prefs']);
			if (!($pref_binary & 1) && (!($pref_binary & 2) || !in_array($row['id_member'], $real_mods)))
				continue;
		}

		$replacements = array(
			'TOPICSUBJECT' => $subject,
			'POSTERNAME' => $poster_name,
			'REPORTERNAME' => $reporterName,
			'TOPICLINK' => $scripturl . '?topic=' . $topic . '.msg' . $_POST['msg'] . '#msg' . $_POST['msg'],
			'REPORTLINK' => !empty($id_report) ? $scripturl . '?action=moderate;area=reports;report=' . $id_report : '',
			'COMMENT' => $_POST['comment'],
		);

		$emaildata = loadEmailTemplate('report_to_moderator', $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		// Send it to the moderator.
		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], $user_info['email'], null, false, 2);
	}
	wesql::free_result($request);

	// Keep track of when the mod reports get updated, that way we know when we need to look again.
	updateSettings(array('last_mod_report_action' => time()));

	// Back to the post we reported!
	redirectexit('reportsent;topic=' . $topic . '.msg' . $_POST['msg'] . '#msg' . $_POST['msg']);
}

?>