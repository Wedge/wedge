<?php
/**
 * Wedge
 *
 * Handles splitting of topics, both the interface and processing.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

/* Original module by Mach8 - We'll never forget you. */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file handles merging and splitting topics... it does this with:

	void SplitTopics()
		- splits a topic into two topics.
		- delegates to the other functions (based on the URL parameter 'sa').
		- loads the Split template.
		- requires the split_any permission.
		- is accessed with ?action=splittopics.

	void SplitIndex()
		- screen shown before the actual split.
		- is accessed with ?action=splittopics;sa=index.
		- default sub action for ?action=splittopics.
		- uses 'ask' block of the Split template.
		- redirects to SplitSelectTopics if the message given turns out to be
		  the first message of a topic.
		- shows the user three ways to split the current topic.

	void SplitExecute()
		- do the actual split.
		- is accessed with ?action=splittopics;sa=execute.
		- uses the main block in the Split template.
		- supports three ways of splitting:
		   (1) only one message is split off.
		   (2) all messages after and including a given message are split off.
		   (3) select topics to split (redirects to SplitSelectTopics()).
		- uses splitTopic function to do the actual splitting.

	void SplitSelectTopics()
		- allows the user to select the messages to be split.
		- is accessed with ?action=splittopics;sa=selectTopics.
		- uses 'select' block of the Split template or, for
		  Ajax, the 'split' block of the Xml template.
		- supports Ajax for adding/removing a message to the selection.
		- uses a session variable to store the selected topics.
		- shows two independent page indexes for both the selected and
		  not-selected messages (;topic=1.x;start2=y).

	void SplitSelectionExecute()
		- do the actual split of a selection of topics.
		- is accessed with ?action=splittopics;sa=splitSelection.
		- uses the main Split template.
		- uses splitTopic function to do the actual splitting.

	int splitTopic(int topicID, array messagesToBeSplit, string newSubject)
		- general function to split off a topic.
		- creates a new topic and moves the messages with the IDs in
		  array messagesToBeSplit to the new topic.
		- the subject of the newly created topic is set to 'newSubject'.
		- marks the newly created message as read for the user splitting it.
		- updates the statistics to reflect a newly created topic.
		- logs the action in the moderation log.
		- a notification is sent to all users monitoring this topic.
		- returns the topic ID of the new split topic.
*/

// Split a topic into two separate topics... in case it got offtopic, etc.
function SplitTopics()
{
	global $topic, $context;

	// And... which topic were you splitting, again?
	if (empty($topic))
		fatal_lang_error('numbers_one_to_nine', false);

	// Are you allowed to split topics?
	isAllowedTo('split_any');

	// Load up the "dependencies" - the template, getMsgMemberID(), and sendNotifications().
	if (!$context['is_ajax'])
		loadTemplate('Split');

	loadSource(array('Subs-Boards', 'Subs-Post'));
	loadLanguage('ManageTopics');

	$subActions = array(
		'selectTopics' => 'SplitSelectTopics',
		'execute' => 'SplitExecute',
		'index' => 'SplitIndex',
		'splitSelection' => 'SplitSelectionExecute',
	);

	// ?action=splittopics;sa=LETSBREAKIT won't work, sorry.
	if (empty($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]))
		SplitIndex();
	else
		$subActions[$_REQUEST['sa']]();
}

// Part 1: General stuff.
function SplitIndex()
{
	global $txt, $topic, $context, $settings;

	// Validate "at".
	if (empty($_GET['at']))
		fatal_lang_error('numbers_one_to_nine', false);
	$_GET['at'] = (int) $_GET['at'];

	// Retrieve the subject and stuff of the specific topic/message.
	$request = wesql::query('
		SELECT m.subject, t.num_replies, t.unapproved_posts, t.id_first_msg, t.approved
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic} AND {query_see_topic})
		WHERE m.id_msg = {int:split_at}' . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND m.approved = 1') . '
			AND m.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
			'split_at' => $_GET['at'],
		)
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('cant_find_messages');
	list ($_REQUEST['subname'], $num_replies, $unapproved_posts, $id_first_msg, $approved) = wesql::fetch_row($request);
	wesql::free_result($request);

	// If not approved validate they can see it.
	if ($settings['postmod_active'] && !$approved)
		isAllowedTo('approve_posts');

	// If this topic has unapproved posts, we need to count them too...
	if ($settings['postmod_active'] && allowedTo('approve_posts'))
		$num_replies += $unapproved_posts - ($approved ? 0 : 1);

	// Check if there is more than one message in the topic.  (there should be.)
	if ($num_replies < 1)
		fatal_lang_error('topic_one_post', false);

	// Check if this is the first message in the topic (if so, the first and second option won't be available)
	if ($id_first_msg == $_GET['at'])
		return SplitSelectTopics();

	// Basic template information....
	$context['message'] = array(
		'id' => $_GET['at'],
		'subject' => westr::safe($_REQUEST['subname'], ENT_QUOTES),
	);
	wetem::load('ask');
	$context['page_title'] = $txt['split'];
}

// Alright, you've decided what you want to do with it.... now to do it.
function SplitExecute()
{
	global $txt, $board, $topic, $context, $settings;

	// Check the session to make sure they meant to do this.
	checkSession();

	// Clean up the subject.
	if (!isset($_POST['subname']) || $_POST['subname'] == '')
		$_POST['subname'] = $txt['new_topic'];

	// Redirect to the selector if they chose selective.
	if ($_POST['step2'] == 'selective')
	{
		$_REQUEST['subname'] = $_POST['subname'];
		return SplitSelectTopics();
	}

	$_POST['at'] = (int) $_POST['at'];
	$messagesToBeSplit = array();

	if ($_POST['step2'] == 'afterthis')
	{
		// Fetch the message IDs of the topic that are at or after the message.
		$request = wesql::query('
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_msg >= {int:split_at}',
			array(
				'current_topic' => $topic,
				'split_at' => $_POST['at'],
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$messagesToBeSplit[] = $row['id_msg'];
		wesql::free_result($request);
	}
	// Only the selected message has to be split. That should be easy.
	elseif ($_POST['step2'] == 'onlythis')
		$messagesToBeSplit[] = $_POST['at'];
	// There's another action?!
	else
		fatal_lang_error('no_access', false);

	$context['old_topic'] = $topic;
	$context['new_topic'] = splitTopic($topic, $messagesToBeSplit, $_POST['subname']);
	$context['page_title'] = $txt['split'];
}

// Get a selective list of topics...
function SplitSelectTopics()
{
	global $txt, $scripturl, $topic, $context, $settings, $original_msgs, $options;

	$context['page_title'] = $txt['split'] . ' - ' . $txt['select_split_posts'];

	// Haven't selected anything have we?
	$_SESSION['split_selection'][$topic] = empty($_SESSION['split_selection'][$topic]) ? array() : $_SESSION['split_selection'][$topic];

	$context['not_selected'] = array(
		'num_messages' => 0,
		'start' => empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'],
		'messages' => array(),
	);

	$context['selected'] = array(
		'num_messages' => 0,
		'start' => empty($_REQUEST['start2']) ? 0 : (int) $_REQUEST['start2'],
		'messages' => array(),
	);

	$context['topic'] = array(
		'id' => $topic,
		'subject' => urlencode($_REQUEST['subname']),
	);

	// Some stuff for our favorite template.
	$context['new_subject'] = $_REQUEST['subname'];

	// Using the "select" block.
	if (!$context['is_ajax'])
		wetem::load('select');

	// Are we using a custom messages per page?
	$context['messages_per_page'] = empty($settings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $settings['defaultMaxMessages'];

	// Get the message ID's from before the move.
	if ($context['is_ajax'])
	{
		$original_msgs = array(
			'not_selected' => array(),
			'selected' => array(),
		);
		$request = wesql::query('
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}' . (empty($_SESSION['split_selection'][$topic]) ? '' : '
				AND id_msg NOT IN ({array_int:no_split_msgs})') . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND approved = {int:is_approved}') . '
			ORDER BY id_msg DESC
			LIMIT {int:start}, {int:messages_per_page}',
			array(
				'current_topic' => $topic,
				'no_split_msgs' => empty($_SESSION['split_selection'][$topic]) ? array() : $_SESSION['split_selection'][$topic],
				'is_approved' => 1,
				'start' => $context['not_selected']['start'],
				'messages_per_page' => $context['messages_per_page'],
			)
		);
		// You can't split the last message off.
		if (empty($context['not_selected']['start']) && wesql::num_rows($request) <= 1 && $_REQUEST['move'] == 'down')
			$_REQUEST['move'] = '';
		while ($row = wesql::fetch_assoc($request))
			$original_msgs['not_selected'][] = $row['id_msg'];
		wesql::free_result($request);
		if (!empty($_SESSION['split_selection'][$topic]))
		{
			$request = wesql::query('
				SELECT id_msg
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_msg IN ({array_int:split_msgs})' . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND approved = {int:is_approved}') . '
				ORDER BY id_msg DESC
				LIMIT {int:start}, {int:messages_per_page}',
				array(
					'current_topic' => $topic,
					'split_msgs' => $_SESSION['split_selection'][$topic],
					'is_approved' => 1,
					'start' => $context['selected']['start'],
					'messages_per_page' => $context['messages_per_page'],
				)
			);
			while ($row = wesql::fetch_assoc($request))
				$original_msgs['selected'][] = $row['id_msg'];
			wesql::free_result($request);
		}
	}

	// (De)select a message..
	if (!empty($_REQUEST['move']))
	{
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		if ($_REQUEST['move'] == 'reset')
			$_SESSION['split_selection'][$topic] = array();
		elseif ($_REQUEST['move'] == 'up')
			$_SESSION['split_selection'][$topic] = array_diff($_SESSION['split_selection'][$topic], array($_REQUEST['msg']));
		else
			$_SESSION['split_selection'][$topic][] = $_REQUEST['msg'];
	}

	// Make sure the selection is still accurate.
	if (!empty($_SESSION['split_selection'][$topic]))
	{
		$request = wesql::query('
			SELECT id_msg
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_msg IN ({array_int:split_msgs})' . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND approved = {int:is_approved}'),
			array(
				'current_topic' => $topic,
				'split_msgs' => $_SESSION['split_selection'][$topic],
				'is_approved' => 1,
			)
		);
		$_SESSION['split_selection'][$topic] = array();
		while ($row = wesql::fetch_assoc($request))
			$_SESSION['split_selection'][$topic][] = $row['id_msg'];
		wesql::free_result($request);
	}

	// Get the number of messages (not) selected to be split.
	$request = wesql::query('
		SELECT ' . (empty($_SESSION['split_selection'][$topic]) ? '0' : 'm.id_msg IN ({array_int:split_msgs})') . ' AS is_selected, COUNT(*) AS num_messages
		FROM {db_prefix}messages AS m
		WHERE m.id_topic = {int:current_topic}' . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND approved = {int:is_approved}') . (empty($_SESSION['split_selection'][$topic]) ? '' : '
		GROUP BY is_selected'),
		array(
			'current_topic' => $topic,
			'split_msgs' => !empty($_SESSION['split_selection'][$topic]) ? $_SESSION['split_selection'][$topic] : array(),
			'is_approved' => 1,
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$context[empty($row['is_selected']) ? 'not_selected' : 'selected']['num_messages'] = $row['num_messages'];
	wesql::free_result($request);

	// Fix an oversized starting page (to make sure both pageindexes are properly set).
	if ($context['selected']['start'] >= $context['selected']['num_messages'])
		$context['selected']['start'] = $context['selected']['num_messages'] <= $context['messages_per_page'] ? 0 : ($context['selected']['num_messages'] - (($context['selected']['num_messages'] % $context['messages_per_page']) == 0 ? $context['messages_per_page'] : ($context['selected']['num_messages'] % $context['messages_per_page'])));

	// Build a page list of the not-selected topics...
	$context['not_selected']['page_index'] = template_page_index($scripturl . '?action=splittopics;sa=selectTopics;subname=' . strtr(urlencode($_REQUEST['subname']), array('%' => '%%')) . ';topic=' . $topic . '.%1$d;start2=' . $context['selected']['start'], $context['not_selected']['start'], $context['not_selected']['num_messages'], $context['messages_per_page'], true);
	// ...and one of the selected topics.
	$context['selected']['page_index'] = template_page_index($scripturl . '?action=splittopics;sa=selectTopics;subname=' . strtr(urlencode($_REQUEST['subname']), array('%' => '%%')) . ';topic=' . $topic . '.' . $context['not_selected']['start'] . ';start2=%1$d', $context['selected']['start'], $context['selected']['num_messages'], $context['messages_per_page'], true);

	// Get the messages and stick them into an array.
	$request = wesql::query('
		SELECT m.subject, IFNULL(mem.real_name, m.poster_name) AS real_name, m.poster_time, m.body, m.id_msg, m.smileys_enabled
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_topic = {int:current_topic}' . (empty($_SESSION['split_selection'][$topic]) ? '' : '
			AND id_msg NOT IN ({array_int:no_split_msgs})') . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND approved = {int:is_approved}') . '
		ORDER BY m.id_msg DESC
		LIMIT {int:start}, {int:messages_per_page}',
		array(
			'current_topic' => $topic,
			'no_split_msgs' => !empty($_SESSION['split_selection'][$topic]) ? $_SESSION['split_selection'][$topic] : array(),
			'is_approved' => 1,
			'start' => $context['not_selected']['start'],
			'messages_per_page' => $context['messages_per_page'],
		)
	);
	$context['messages'] = array();
	for ($counter = 0; $row = wesql::fetch_assoc($request); $counter++)
	{
		censorText($row['subject']);
		censorText($row['body']);

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		$context['not_selected']['messages'][$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'alternate' => $counter % 2,
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'body' => $row['body'],
			'poster' => $row['real_name'],
		);
	}
	wesql::free_result($request);

	// Now get the selected messages.
	if (!empty($_SESSION['split_selection'][$topic]))
	{
		// Get the messages and stick them into an array.
		$request = wesql::query('
			SELECT m.subject, IFNULL(mem.real_name, m.poster_name) AS real_name, m.poster_time, m.body, m.id_msg, m.smileys_enabled
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_topic = {int:current_topic}
				AND m.id_msg IN ({array_int:split_msgs})' . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND approved = {int:is_approved}') . '
			ORDER BY m.id_msg DESC
			LIMIT {int:start}, {int:messages_per_page}',
			array(
				'current_topic' => $topic,
				'split_msgs' => $_SESSION['split_selection'][$topic],
				'is_approved' => 1,
				'start' => $context['selected']['start'],
				'messages_per_page' => $context['messages_per_page'],
			)
		);
		$context['messages'] = array();
		for ($counter = 0; $row = wesql::fetch_assoc($request); $counter++)
		{
			censorText($row['subject']);
			censorText($row['body']);

			$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

			$context['selected']['messages'][$row['id_msg']] = array(
				'id' => $row['id_msg'],
				'alternate' => $counter % 2,
				'subject' => $row['subject'],
				'time' => timeformat($row['poster_time']),
				'timestamp' => forum_time(true, $row['poster_time']),
				'body' => $row['body'],
				'poster' => $row['real_name']
			);
		}
		wesql::free_result($request);
	}

	// The XMLhttp (Ajax) method only needs the stuff that changed, so let's compare.
	if ($context['is_ajax'])
	{
		$changes = array(
			'remove' => array(
				'not_selected' => array_diff($original_msgs['not_selected'], array_keys($context['not_selected']['messages'])),
				'selected' => array_diff($original_msgs['selected'], array_keys($context['selected']['messages'])),
			),
			'insert' => array(
				'not_selected' => array_diff(array_keys($context['not_selected']['messages']), $original_msgs['not_selected']),
				'selected' => array_diff(array_keys($context['selected']['messages']), $original_msgs['selected']),
			),
		);

		$context['changes'] = array();
		foreach ($changes as $change_type => $change_array)
			foreach ($change_array as $section => $msg_array)
			{
				if (empty($msg_array))
					continue;

				foreach ($msg_array as $id_msg)
				{
					$context['changes'][$change_type . $id_msg] = array(
						'id' => $id_msg,
						'type' => $change_type,
						'section' => $section,
					);
					if ($change_type == 'insert')
						$context['changes']['insert' . $id_msg]['insert_value'] = $context[$section]['messages'][$id_msg];
				}
			}

		header('Content-Type: text/xml; charset=UTF-8');
		echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>
	<pageIndex section="not_selected" startFrom="', $context['not_selected']['start'], '"><![CDATA[', $context['not_selected']['page_index'], ']]></pageIndex>
	<pageIndex section="selected" startFrom="', $context['selected']['start'], '"><![CDATA[', $context['selected']['page_index'], ']]></pageIndex>';
		foreach ($context['changes'] as $change)
		{
			if ($change['type'] == 'remove')
				echo '
	<change id="', $change['id'], '" curAction="remove" section="', $change['section'], '" />';
			else
				echo '
	<change id="', $change['id'], '" curAction="insert" section="', $change['section'], '">
		<subject><![CDATA[', cleanXml($change['insert_value']['subject']), ']]></subject>
		<time><![CDATA[', cleanXml($change['insert_value']['time']), ']]></time>
		<body><![CDATA[', cleanXml($change['insert_value']['body']), ']]></body>
		<poster><![CDATA[', cleanXml($change['insert_value']['poster']), ']]></poster>
	</change>';
		}
		echo '
</we>';
		obExit(false);
	}
}

// Actually and selectively split the topics out.
function SplitSelectionExecute()
{
	global $txt, $board, $topic, $context;

	// Make sure the session id was passed with post.
	checkSession();

	// Default the subject in case it's blank.
	if (!isset($_POST['subname']) || $_POST['subname'] == '')
		$_POST['subname'] = $txt['new_topic'];

	// You must've selected some messages!  Can't split out none!
	if (empty($_SESSION['split_selection'][$topic]))
		fatal_lang_error('no_posts_selected', false);

	$context['old_topic'] = $topic;
	$context['new_topic'] = splitTopic($topic, $_SESSION['split_selection'][$topic], $_POST['subname']);
	$context['page_title'] = $txt['split'];
}

// Split a topic in two topics.
function splitTopic($split1_id_topic, $split_messages, $new_subject)
{
	global $topic, $board, $settings, $txt;

	// Nothing to split?
	if (empty($split_messages))
		fatal_lang_error('no_posts_selected', false);

	// Get some board info.
	$request = wesql::query('
		SELECT id_board, approved
		FROM {db_prefix}topics
		WHERE id_topic = {int:id_topic}
		LIMIT 1',
		array(
			'id_topic' => $split1_id_topic,
		)
	);
	list ($id_board, $split1_approved) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Find the new first and last not in the list. (old topic)
	$request = wesql::query('
		SELECT
			MIN(m.id_msg) AS myid_first_msg, MAX(m.id_msg) AS myid_last_msg, COUNT(*) AS message_count, m.approved
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:id_topic})
		WHERE m.id_msg NOT IN ({array_int:no_msg_list})
			AND m.id_topic = {int:id_topic}
		GROUP BY m.approved
		ORDER BY m.approved DESC
		LIMIT 2',
		array(
			'id_topic' => $split1_id_topic,
			'no_msg_list' => $split_messages,
		)
	);
	// You can't select ALL the messages!
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('selected_all_posts', false);
	while ($row = wesql::fetch_assoc($request))
	{
		// Get the right first and last message depending on approved state...
		if (empty($split1_first_msg) || $row['myid_first_msg'] < $split1_first_msg)
			$split1_first_msg = $row['myid_first_msg'];
		if (empty($split1_last_msg) || $row['approved'])
			$split1_last_msg = $row['myid_last_msg'];

		// Get the counts correct...
		if ($row['approved'])
		{
			$split1_replies = $row['message_count'] - 1;
			$split1_unapproved_posts = 0;
		}
		else
		{
			if (!isset($split1_replies))
				$split1_replies = 0;
			// If the topic isn't approved then num replies must go up by one... as first post wouldn't be counted.
			elseif (!$split1_approved)
				$split1_replies++;

			$split1_unapproved_posts = $row['message_count'];
		}
	}
	wesql::free_result($request);
	$split1_first_mem = getMsgMemberID($split1_first_msg);
	$split1_last_mem = getMsgMemberID($split1_last_msg);

	// Find the first and last in the list. (new topic)
	$request = wesql::query('
		SELECT MIN(id_msg) AS myid_first_msg, MAX(id_msg) AS myid_last_msg, COUNT(*) AS message_count, approved
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:msg_list})
			AND id_topic = {int:id_topic}
		GROUP BY id_topic, approved
		ORDER BY approved DESC
		LIMIT 2',
		array(
			'msg_list' => $split_messages,
			'id_topic' => $split1_id_topic,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		// As before get the right first and last message depending on approved state...
		if (empty($split2_first_msg) || $row['myid_first_msg'] < $split2_first_msg)
			$split2_first_msg = $row['myid_first_msg'];
		if (empty($split2_last_msg) || $row['approved'])
			$split2_last_msg = $row['myid_last_msg'];

		// Then do the counts again...
		if ($row['approved'])
		{
			$split2_approved = true;
			$split2_replies = $row['message_count'] - 1;
			$split2_unapproved_posts = 0;
		}
		else
		{
			// Should this one be approved??
			if ($split2_first_msg == $row['myid_first_msg'])
				$split2_approved = false;

			if (!isset($split2_replies))
				$split2_replies = 0;
			// As before, fix number of replies.
			elseif (!$split2_approved)
				$split2_replies++;

			$split2_unapproved_posts = $row['message_count'];
		}
	}
	wesql::free_result($request);
	$split2_first_mem = getMsgMemberID($split2_first_msg);
	$split2_last_mem = getMsgMemberID($split2_last_msg);

	// No database changes yet, so let's double check to see if everything makes at least a little sense.
	if ($split1_first_msg <= 0 || $split1_last_msg <= 0 || $split2_first_msg <= 0 || $split2_last_msg <= 0 || $split1_replies < 0 || $split2_replies < 0 || $split1_unapproved_posts < 0 || $split2_unapproved_posts < 0 || !isset($split1_approved) || !isset($split2_approved))
		fatal_lang_error('cant_find_messages');

	// You cannot split off the first message of a topic.
	if ($split1_first_msg > $split2_first_msg)
		fatal_lang_error('split_first_post', false);

	// We're off to insert the new topic!  Use 0 for now to avoid UNIQUE errors.
	wesql::insert('',
			'{db_prefix}topics',
			array(
				'id_board' => 'int',
				'id_member_started' => 'int',
				'id_member_updated' => 'int',
				'id_first_msg' => 'int',
				'id_last_msg' => 'int',
				'num_replies' => 'int',
				'unapproved_posts' => 'int',
				'approved' => 'int',
				'is_pinned' => 'int',
			),
			array(
				(int) $id_board, $split2_first_mem, $split2_last_mem, 0,
				0, $split2_replies, $split2_unapproved_posts, (int) $split2_approved, 0,
			),
			array('id_topic')
		);
	$split2_id_topic = wesql::insert_id();
	if ($split2_id_topic <= 0)
		fatal_lang_error('cant_insert_topic');

	// Move the messages over to the other topic.
	$new_subject = strtr(westr::htmltrim(westr::htmlspecialchars($new_subject)), array("\r" => '', "\n" => '', "\t" => ''));
	// Check the subject length.
	if (westr::strlen($new_subject) > 100)
		$new_subject = westr::substr($new_subject, 0, 100);
	// Valid subject?
	if ($new_subject != '')
	{
		wesql::query('
			UPDATE {db_prefix}messages
			SET
				id_topic = {int:id_topic},
				subject = CASE WHEN id_msg = {int:split_first_msg} THEN {string:new_subject} ELSE {string:new_subject_replies} END
			WHERE id_msg IN ({array_int:split_msgs})',
			array(
				'split_msgs' => $split_messages,
				'id_topic' => $split2_id_topic,
				'new_subject' => $new_subject,
				'split_first_msg' => $split2_first_msg,
				'new_subject_replies' => $txt['response_prefix'] . $new_subject,
			)
		);

		// Cache the new topics subject... we can do it now as all the subjects are the same!
		updateStats('subject', $split2_id_topic, $new_subject);
	}

	// Any associated reported posts better follow...
	wesql::query('
		UPDATE {db_prefix}log_reported
		SET id_topic = {int:id_topic}
		WHERE id_msg IN ({array_int:split_msgs})',
		array(
			'split_msgs' => $split_messages,
			'id_topic' => $split2_id_topic,
		)
	);

	// Mess with the old topic's first, last, and number of messages.
	wesql::query('
		UPDATE {db_prefix}topics
		SET
			num_replies = {int:num_replies},
			id_first_msg = {int:id_first_msg},
			id_last_msg = {int:id_last_msg},
			id_member_started = {int:id_member_started},
			id_member_updated = {int:id_member_updated},
			unapproved_posts = {int:unapproved_posts}
		WHERE id_topic = {int:id_topic}',
		array(
			'num_replies' => $split1_replies,
			'id_first_msg' => $split1_first_msg,
			'id_last_msg' => $split1_last_msg,
			'id_member_started' => $split1_first_mem,
			'id_member_updated' => $split1_last_mem,
			'unapproved_posts' => $split1_unapproved_posts,
			'id_topic' => $split1_id_topic,
		)
	);

	// Now, put the first/last message back to what they should be.
	wesql::query('
		UPDATE {db_prefix}topics
		SET
			id_first_msg = {int:id_first_msg},
			id_last_msg = {int:id_last_msg}
		WHERE id_topic = {int:id_topic}',
		array(
			'id_first_msg' => $split2_first_msg,
			'id_last_msg' => $split2_last_msg,
			'id_topic' => $split2_id_topic,
		)
	);

	// If the new topic isn't approved ensure the first message flags this just in case.
	if (!$split2_approved)
		wesql::query('
			UPDATE {db_prefix}messages
			SET approved = {int:is_unapproved}
			WHERE id_msg = {int:id_msg}
				AND id_topic = {int:id_topic}',
			array(
				'is_unapproved' => 0,
				'id_msg' => $split2_first_msg,
				'id_topic' => $split2_id_topic,
			)
		);

	// The board has more topics now (Or more unapproved ones!).
	wesql::query('
		UPDATE {db_prefix}boards
		SET ' . ($split2_approved ? '
			num_topics = num_topics + 1' : '
			unapproved_topics = unapproved_topics + 1') . '
		WHERE id_board = {int:id_board}',
		array(
			'id_board' => $id_board,
		)
	);

	// Copy log topic entries.
	// !!! This should really be chunked.
	$request = wesql::query('
		SELECT id_member, id_msg
		FROM {db_prefix}log_topics
		WHERE id_topic = {int:id_topic}',
		array(
			'id_topic' => (int) $split1_id_topic,
		)
	);
	if (wesql::num_rows($request) > 0)
	{
		$replaceEntries = array();
		while ($row = wesql::fetch_assoc($request))
			$replaceEntries[] = array($row['id_member'], $split2_id_topic, $row['id_msg']);

		wesql::insert('ignore',
			'{db_prefix}log_topics',
			array('id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int'),
			$replaceEntries,
			array('id_member', 'id_topic')
		);
		unset($replaceEntries);
	}
	wesql::free_result($request);

	// Housekeeping.
	updateStats('topic');
	updateLastMessages($id_board);

	logAction('split', array('topic' => $split1_id_topic, 'new_topic' => $split2_id_topic, 'board' => $id_board));

	// Notify people that this topic has been split?
	sendNotifications($split1_id_topic, 'split');

	// Return the ID of the newly created topic.
	return $split2_id_topic;
}

?>