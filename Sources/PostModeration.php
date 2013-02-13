<?php
/**
 * Wedge
 *
 * Handles various aspects of post moderation.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

// This is a handling function for all things post moderation...
function PostModerationMain()
{
	// !! We'll shift these later bud.
	loadLanguage('ModerationCenter');
	loadTemplate('ModerationCenter');

	// Probably need this...
	loadSource('ModerationCenter');

	// Allowed sub-actions, you know the drill by now!
	$subactions = array(
		'approve' => 'ApproveMessage',
		'replies' => 'UnapprovedPosts',
		'topics' => 'UnapprovedPosts',
	);

	// Pick something valid...
	if (!isset($_REQUEST['sa']) || !isset($subactions[$_REQUEST['sa']]))
		$_REQUEST['sa'] = 'replies';

	$subactions[$_REQUEST['sa']]();
}

// View all unapproved posts.
function UnapprovedPosts()
{
	global $txt, $scripturl, $context;

	$context['current_view'] = isset($_GET['sa']) && $_GET['sa'] == 'topics' ? 'topics' : 'replies';
	$context['page_title'] = $txt['mc_unapproved_posts'];

	// Work out what boards we can work in!
	$approve_boards = boardsAllowedTo('approve_posts');

	// If we filtered by board remove ones outside of this board.
	// !! Put a message saying we're filtered?
	if (isset($_REQUEST['brd']))
	{
		$filter_board = array((int) $_REQUEST['brd']);
		$approve_boards = $approve_boards == array(0) ? $filter_board : array_intersect($approve_boards, $filter_board);
	}

	if ($approve_boards == array(0))
		$approve_query = '';
	elseif (!empty($approve_boards))
		$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
	// Nada, zip, etc...
	else
		$approve_query = ' AND 0';

	// We also need to know where we can delete topics and/or replies to.
	if ($context['current_view'] == 'topics')
	{
		$delete_own_boards = boardsAllowedTo('remove_own');
		$delete_any_boards = boardsAllowedTo('remove_any');
		$delete_own_replies = array();
	}
	else
	{
		$delete_own_boards = boardsAllowedTo('delete_own');
		$delete_any_boards = boardsAllowedTo('delete_any');
		$delete_own_replies = boardsAllowedTo('delete_own_replies');
	}

	$toAction = array();
	// Check if we have something to do?
	if (isset($_GET['approve']))
		$toAction[] = (int) $_GET['approve'];
	// Just a deletion?
	elseif (isset($_GET['delete']))
		$toAction[] = (int) $_GET['delete'];
	// Lots of approvals?
	elseif (isset($_POST['item']))
		foreach ($_POST['item'] as $item)
			$toAction[] = (int) $item;

	// What are we actually doing.
	if (isset($_GET['approve']) || (isset($_POST['do']) && $_POST['do'] == 'approve'))
		$curAction = 'approve';
	elseif (isset($_GET['delete']) || (isset($_POST['do']) && $_POST['do'] == 'delete'))
		$curAction = 'delete';

	// Right, so we have something to do?
	if (!empty($toAction) && isset($curAction))
	{
		checkSession('request');

		// Handy shortcut.
		$any_array = $curAction == 'approve' ? $approve_boards : $delete_any_boards;

		// Now for each message work out whether it's actually a topic, and what board it's on.
		$request = wesql::query('
			SELECT m.id_msg, m.id_member, m.id_board, m.subject, t.id_topic, t.id_first_msg, t.id_member_started
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			WHERE m.id_msg IN ({array_int:message_list})
				AND m.approved = {int:not_approved}
				AND {query_see_board}',
			array(
				'message_list' => $toAction,
				'not_approved' => 0,
			)
		);
		$toAction = array();
		$details = array();
		while ($row = wesql::fetch_assoc($request))
		{
			// If it's not within what our view is ignore it...
			if (($row['id_msg'] == $row['id_first_msg'] && $context['current_view'] != 'topics') || ($row['id_msg'] != $row['id_first_msg'] && $context['current_view'] != 'replies'))
				continue;

			$can_add = false;

			// If we're approving this is simple.
			if ($curAction == 'approve' && ($any_array == array(0) || in_array($row['id_board'], $any_array)))
				$can_add = true;

			// Delete requires more permission checks...
			elseif ($curAction == 'delete')
			{
				// Own post is easy!
				if ($row['id_member'] == we::$id && ($delete_own_boards == array(0) || in_array($row['id_board'], $delete_own_boards)))
					$can_add = true;
				// Is it a reply to their own topic?
				elseif ($row['id_member'] == $row['id_member_started'] && $row['id_msg'] != $row['id_first_msg'] && ($delete_own_replies == array(0) || in_array($row['id_board'], $delete_own_replies)))
					$can_add = true;
				// Someone else's?
				elseif ($row['id_member'] != we::$id && ($delete_any_boards == array(0) || in_array($row['id_board'], $delete_any_boards)))
					$can_add = true;
			}

			if ($can_add)
				$anItem = $row[$context['current_view'] == 'topics' ? 'id_topic' : 'id_msg'];
			$toAction[] = $anItem;

			// All clear. What have we got now, what, what?
			$details[$anItem] = array();
			$details[$anItem]['subject'] = $row['subject'];
			$details[$anItem]['topic'] = $row['id_topic'];
			$details[$anItem]['member'] = $row[$context['current_view'] == 'topics' ? 'id_member_started' : 'id_member'];
			$details[$anItem]['board'] = $row['id_board'];
		}
		wesql::free_result($request);

		// If we have anything left we can actually do the approving (etc.)
		if (!empty($toAction))
		{
			if ($curAction == 'approve')
				approveMessages($toAction, $details, $context['current_view']);
			else
				removeMessages($toAction, $details, $context['current_view']);
		}
	}

	// How many unapproved posts are there?
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_first_msg != m.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE m.approved = {int:not_approved}
			AND {query_see_board}
			' . $approve_query,
		array(
			'not_approved' => 0,
		)
	);
	list ($context['total_unapproved_posts']) = wesql::fetch_row($request);
	wesql::free_result($request);

	// What about topics? Normally we'd use the table alias t for topics but let's use m so we don't have to redo our approve query.
	$request = wesql::query('
		SELECT COUNT(m.id_topic)
		FROM {db_prefix}topics AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.approved = {int:not_approved}
			AND {query_see_board}
			' . $approve_query,
		array(
			'not_approved' => 0,
		)
	);
	list ($context['total_unapproved_topics']) = wesql::fetch_row($request);
	wesql::free_result($request);

	$context['page_index'] = template_page_index($scripturl . '?action=moderate;area=postmod;sa=' . $context['current_view'] . (isset($_REQUEST['brd']) ? ';brd=' . (int) $_REQUEST['brd'] : ''), $_GET['start'], $context['current_view'] == 'topics' ? $context['total_unapproved_topics'] : $context['total_unapproved_posts'], 10);
	$context['start'] = $_GET['start'];

	// We have enough to make some pretty tabs!
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_unapproved_posts'],
		'help' => 'postmod',
		'description' => $txt['mc_unapproved_posts_desc'],
	);

	// Update the tabs with the correct number of posts.
	$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['posts']['label'] .= ' (' . $context['total_unapproved_posts'] . ')';
	$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['topics']['label'] .= ' (' . $context['total_unapproved_topics'] . ')';

	// If we are filtering some boards out then make sure to send that along with the links.
	if (isset($_REQUEST['brd']))
	{
		$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['posts']['url'] = $scripturl . '?action=moderate;area=postmod;sa=posts;brd=' . (int) $_REQUEST['brd'];
		$context['menu_data_' . $context['moderation_menu_id']]['sections']['posts']['areas']['postmod']['subsections']['topics']['url'] = $scripturl . '?action=moderate;area=postmod;sa=topics;brd=' . (int) $_REQUEST['brd'];
	}

	// Get all unapproved posts.
	$request = wesql::query('
		SELECT m.id_msg, m.id_topic, m.id_board, m.subject, m.body, m.id_member,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.smileys_enabled,
			t.id_member_started, t.id_first_msg, b.name AS board_name, c.id_cat, c.name AS cat_name
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE m.approved = {int:not_approved}
			AND t.id_first_msg ' . ($context['current_view'] == 'topics' ? '=' : '!=') . ' m.id_msg
			AND {query_see_board}
			' . $approve_query . '
		LIMIT ' . $context['start'] . ', 10',
		array(
			'not_approved' => 0,
		)
	);
	$context['unapproved_items'] = array();
	for ($i = 1; $row = wesql::fetch_assoc($request); $i++)
	{
		// Can delete is complicated, let's solve it first... is it their own post?
		if ($row['id_member'] == we::$id && ($delete_own_boards == array(0) || in_array($row['id_board'], $delete_own_boards)))
			$can_delete = true;
		// Is it a reply to their own topic?
		elseif ($row['id_member'] == $row['id_member_started'] && $row['id_msg'] != $row['id_first_msg'] && ($delete_own_replies == array(0) || in_array($row['id_board'], $delete_own_replies)))
			$can_delete = true;
		// Someone elses?
		elseif ($row['id_member'] != we::$id && ($delete_any_boards == array(0) || in_array($row['id_board'], $delete_any_boards)))
			$can_delete = true;
		else
			$can_delete = false;

		$context['unapproved_items'][] = array(
			'id' => $row['id_msg'],
			'alternate' => $i % 2,
			'counter' => $context['start'] + $i,
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'subject' => $row['subject'],
			'body' => parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']),
			'on_time' => on_timeformat($row['poster_time']),
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'link' => $row['id_member'] ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name'],
				'href' => $scripturl . '?action=profile;u=' . $row['id_member'],
			),
			'topic' => array(
				'id' => $row['id_topic'],
			),
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
			),
			'category' => array(
				'id' => $row['id_cat'],
				'name' => $row['cat_name'],
			),
			'can_delete' => $can_delete,
		);
	}
	wesql::free_result($request);

	wetem::load('unapproved_posts');
}

// Approve a post, just the one.
function ApproveMessage()
{
	global $topic, $board;

	checkSession('get');

	$_REQUEST['msg'] = (int) $_REQUEST['msg'];

	loadSource('Subs-Post');

	isAllowedTo('approve_posts');

	$request = wesql::query('
		SELECT t.id_member_started, t.id_first_msg, m.id_member, m.subject, m.approved
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
	list ($starter, $first_msg, $poster, $subject, $approved) = wesql::fetch_row($request);
	wesql::free_result($request);

	// If it's the first in a topic then the whole topic gets approved!
	if ($first_msg == $_REQUEST['msg'])
	{
		approveTopics($topic, !$approved);

		if ($starter != we::$id)
			logAction('approve_topic', array('topic' => $topic, 'subject' => $subject, 'member' => $starter, 'board' => $board));
	}
	else
	{
		approvePosts($_REQUEST['msg'], !$approved);

		if ($poster != we::$id)
			logAction('approve', array('topic' => $topic, 'subject' => $subject, 'member' => $poster, 'board' => $board));
	}

	redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg']. '#msg' . $_REQUEST['msg']);
}

// Approve a batch of posts (or topics in their own right)
function approveMessages($messages, $messageDetails, $current_view = 'replies')
{
	loadSource('Subs-Post');
	if ($current_view == 'topics')
	{
		approveTopics($messages);
		// and tell the world about it
		foreach ($messages as $topic)
			logAction('approve_topic', array('topic' => $topic, 'subject' => $messageDetails[$topic]['subject'], 'member' => $messageDetails[$topic]['member'], 'board' => $messageDetails[$topic]['board']));
	}
	else
	{
		approvePosts($messages);
		// and tell the world about it again
		foreach ($messages as $post)
			logAction('approve', array('topic' => $messageDetails[$post]['topic'], 'subject' => $messageDetails[$post]['subject'], 'member' => $messageDetails[$post]['member'], 'board' => $messageDetails[$post]['board']));
	}
}

// remove a batch of messages (or topics)
function removeMessages($messages, $messageDetails, $current_view = 'replies')
{
	global $settings;
	loadSource('RemoveTopic');
	if ($current_view == 'topics')
	{
		removeTopics($messages);
		// and tell the world about it
		foreach ($messages as $topic)
			// Note, only log topic ID in native form if it's not gone forever.
			logAction('remove', array(
				(empty($settings['recycle_enable']) || $settings['recycle_board'] != $messageDetails[$topic]['board'] ? 'topic' : 'old_topic_id') => $topic,
				'subject' => $messageDetails[$topic]['subject'], 'member' => $messageDetails[$topic]['member'], 'board' => $messageDetails[$topic]['board']
			));
	}
	else
	{
		foreach ($messages as $post)
		{
			removeMessage($post);
			logAction('delete', array(
				(empty($settings['recycle_enable']) || $settings['recycle_board'] != $messageDetails[$post]['board'] ? 'topic' : 'old_topic_id') => $messageDetails[$post]['topic'],
				'subject' => $messageDetails[$post]['subject'], 'member' => $messageDetails[$post]['member'], 'board' => $messageDetails[$post]['board']
			));
		}
	}
}
