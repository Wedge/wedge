<?php
/**********************************************************************************
* Announce.php                                                                    *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC5                                         *
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

/**
 * This file handles the sending of announcements when a topic is being announced.
 *
 * @package wedge
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * The initial handler for announcements, called from the post page (if Announce this topic is set), amongst other places.
 *
 * - Checks the user has the basic permission to announce topics (announce_topic).
 * - Also forces them to revalidate session with a further password entry.
 * - If the topic does not exist in the URL, exit with error.
 * - The Post language and template will also be loaded at this point.
 * - Otherwise, divert control to {@link AnnounceSelectMembergroup()} or {@link AnnouncementSend()} depending on specified subaction.
 */
function Announce()
{
	global $context, $txt, $topic;

	isAllowedTo('announce_topic');

	validateSession();

	if (empty($topic))
		fatal_lang_error('topic_gone', false);

	loadLanguage('Post');
	loadTemplate('Announce');

	$subActions = array(
		'selectgroup' => 'AnnouncementSelectMembergroup',
		'send' => 'AnnouncementSend',
	);

	$context['page_title'] = $txt['announce_topic'];

	// Call the function based on the sub-action.
	$subActions[isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'selectgroup']();
}

/**
 * When sending an announcement of a new topic, this function manages collecting the usergroups to send that announcement to.
 *
 * - Starts by collating the groups that have access to the current board.
 * - Includes regular members separately as they have no strict membergroup.
 * - Collates the number of people in each group that has access to the board.
 * - Then collates the names of those groups.
 * - Collects, then censors, the topic name.
 * - Saves details such as 'go back to topic', before passing control onwards to template generation.
 */
function AnnouncementSelectMembergroup()
{
	global $txt, $context, $topic, $board, $board_info;

	$groups = array_merge($board_info['groups'], array(1));
	foreach ($groups as $id => $group)
		$groups[$id] = (int) $group;

	$context['groups'] = array();
	if (in_array(0, $groups))
	{
		$context['groups'][0] = array(
			'id' => 0,
			'name' => $txt['announce_regular_members'],
			'member_count' => 'n/a',
		);
	}

	// Get all membergroups that have access to the board the announcement was made on.
	$request = wesql::query('
		SELECT mg.id_group, COUNT(mem.id_member) AS num_members
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_group = mg.id_group OR FIND_IN_SET(mg.id_group, mem.additional_groups) != 0 OR mg.id_group = mem.id_post_group)
		WHERE mg.id_group IN ({array_int:group_list})
		GROUP BY mg.id_group',
		array(
			'group_list' => $groups,
			'newbie_id_group' => 4,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$context['groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => '',
			'member_count' => $row['num_members'],
		);
	}
	wesql::free_result($request);

	// Now get the membergroup names.
	$request = wesql::query('
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({array_int:group_list})',
		array(
			'group_list' => $groups,
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$context['groups'][$row['id_group']]['name'] = $row['group_name'];
	wesql::free_result($request);

	// Get the subject of the topic we're about to announce.
	$request = wesql::query('
		SELECT m.subject
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
		)
	);
	list ($context['topic_subject']) = wesql::fetch_row($request);
	wesql::free_result($request);

	censorText($context['announce_topic']['subject']);

	$context['move'] = isset($_REQUEST['move']) ? 1 : 0;
	$context['go_back'] = isset($_REQUEST['goback']) ? 1 : 0;

	loadSubTemplate('announce');
}

/**
 * Handles sending announcements in chunks rather than pushing what could be a very, very large number of emails - or database rows - at once.
 *
 * - Session check via $_REQUEST.
 * - The chunks are blocks of 50 if not using the mail queue, or 500 if using it.
 * - Get the groups as set up by AnnouncementSelectMembergroup (or otherwise passed between requests) and validate that all the groups requested do have access to the board where the announcement was made.
 * - Fail with error if there are no groups, or none valid, selected.
 * - Load the message (and its subject), censor it, then parse the message (without smileys), replacing certain tags with line breaks, stripping the rest, and removing HTML special characters.
 * - Load the email addresses of the current batch of announcements.
 * - If all done, head on to the move dialog, or back to the topic or board, depending on user options.
 * - For each email we're going to send: check if we have loaded the language - if not, load the right language file, then the email templates for that language. Then add this recipient to the list for their language.
 * - Then send out emails by language, having replaced the text into the email template, low priority.
 * - Update the sender with an approximate percentage of completion, and make sure we have the right language loaded for the user when we tell them.
 */
function AnnouncementSend()
{
	global $topic, $board, $board_info, $context, $modSettings;
	global $language, $scripturl, $txt, $user_info;

	checkSession();

	// !!! Might need an interface?
	$chunkSize = empty($modSettings['mail_queue']) ? 50 : 500;

	$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$groups = array_merge($board_info['groups'], array(1));

	if (isset($_POST['membergroups']))
		$_POST['who'] = explode(',', $_POST['membergroups']);

	// Check whether at least one membergroup was selected.
	if (empty($_POST['who']))
		fatal_lang_error('no_membergroup_selected');

	// Make sure all membergroups are integers and can access the board of the announcement.
	foreach ($_POST['who'] as $id => $mg)
		$_POST['who'][$id] = in_array((int) $mg, $groups) ? (int) $mg : 0;

	// Get the topic subject and censor it.
	$request = wesql::query('
		SELECT m.id_msg, m.subject, m.body
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
		)
	);
	list ($id_msg, $context['topic_subject'], $message) = wesql::fetch_row($request);
	wesql::free_result($request);

	censorText($context['topic_subject']);
	censorText($message);

	$message = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($message, false, $id_msg), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

	// We need this in order to be able send emails.
	loadSource('Subs-Post');

	// Select the email addresses for this batch.
	$request = wesql::query('
		SELECT mem.id_member, mem.email_address, mem.lngfile
		FROM {db_prefix}members AS mem
		WHERE mem.id_member != {int:current_member}' . (!empty($modSettings['allow_disableAnnounce']) ? '
			AND mem.notify_announcements = {int:notify_announcements}' : '') . '
			AND mem.is_activated = {int:is_activated}
			AND (mem.id_group IN ({array_int:group_list}) OR mem.id_post_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:additional_group_list}, mem.additional_groups) != 0)
			AND mem.id_member > {int:start}
		ORDER BY mem.id_member
		LIMIT ' . $chunkSize,
		array(
			'current_member' => $user_info['id'],
			'group_list' => $_POST['who'],
			'notify_announcements' => 1,
			'is_activated' => 1,
			'start' => $context['start'],
			'additional_group_list' => implode(', mem.additional_groups) != 0 OR FIND_IN_SET(', $_POST['who']),
		)
	);

	// All members have received a mail. Go to the next screen.
	if (wesql::num_rows($request) == 0)
	{
		if (!empty($_REQUEST['move']) && allowedTo('move_any'))
			redirectexit('action=movetopic;topic=' . $topic . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));
		elseif (!empty($_REQUEST['goback']))
			redirectexit('topic=' . $topic . '.new;boardseen#new', $context['browser']['is_ie']);
		else
			redirectexit('board=' . $board . '.0');
	}

	// Loop through all members that'll receive an announcement in this batch.
	while ($row = wesql::fetch_assoc($request))
	{
		$cur_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];

		// If the language wasn't defined yet, load it and compose a notification message.
		if (!isset($announcements[$cur_language]))
		{
			$replacements = array(
				'TOPICSUBJECT' => $context['topic_subject'],
				'MESSAGE' => $message,
				'TOPICLINK' => $scripturl . '?topic=' . $topic . '.0',
			);

			$emaildata = loadEmailTemplate('new_announcement', $replacements, $cur_language);

			$announcements[$cur_language] = array(
				'subject' => $emaildata['subject'],
				'body' => $emaildata['body'],
				'recipients' => array(),
			);
		}

		$announcements[$cur_language]['recipients'][$row['id_member']] = $row['email_address'];
		$context['start'] = $row['id_member'];
	}
	wesql::free_result($request);

	// For each language send a different mail - low priority...
	foreach ($announcements as $lang => $mail)
		sendmail($mail['recipients'], $mail['subject'], $mail['body'], null, null, false, 5);

	$context['percentage_done'] = round(100 * $context['start'] / $modSettings['latestMember'], 1);

	$context['move'] = empty($_REQUEST['move']) ? 0 : 1;
	$context['go_back'] = empty($_REQUEST['goback']) ? 0 : 1;
	$context['membergroups'] = implode(',', $_POST['who']);
	loadSubTemplate('announcement_send');

	// Go back to the correct language for the user ;).
	if (!empty($modSettings['userLanguage']))
		loadLanguage('Post');
}

?>