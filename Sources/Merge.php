<?php
/**
 * Wedge
 *
 * Handles merging of topics and posts, both the interface and processing.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

/* Original module by Mach8 - We'll never forget you. */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file handles merging and splitting topics... it does this with:

	void MergeTopics()
		- merges two or more topics into one topic.
		- delegates to the other functions (based on the URL parameter sa).
		- loads the Merge template.
		- requires the merge_any permission.
		- is accessed with ?action=mergetopics.

	void MergeIndex()
		- allows to pick a topic to merge the current topic with.
		- is accessed with ?action=mergetopics;sa=index
		- default sub action for ?action=mergetopics.
		- uses 'merge' block of the Merge template.
		- allows to set a different target board.

	void MergeExecute(array topics = request)
		- set merge options and do the actual merge of two or more topics.
		- the merge options screen:
			- shows topics to be merged and allows to set some merge options.
			- is accessed by ?action=mergetopics;sa=options.and can also
			  internally be called by QuickModeration().
			- uses 'merge_extra_options' block of the Merge template.
		- the actual merge:
			- is accessed with ?action=mergetopics;sa=execute.
			- updates the statistics to reflect the merge.
			- logs the action in the moderation log.
			- sends a notification is sent to all users monitoring this topic.
			- redirects to ?action=mergetopics;sa=done.

	void MergeDone()
		- shows a 'merge completed' screen.
		- is accessed with ?action=mergetopics;sa=done.
		- uses 'merge_done' block of the Merge template.

	void MergePosts()
		- merges two posts together, as long as they follow each
		  other in the topic, and they're from the same author.
*/

// Merge two topics into one topic... useful if they have the same basic subject.
function MergeTopics()
{
	// Load the template....
	loadTemplate('Merge');
	loadLanguage('ManageTopics');

	$subActions = array(
		'done' => 'MergeDone',
		'execute' => 'MergeExecute',
		'index' => 'MergeIndex',
		'options' => 'MergeExecute',
	);

	// ?action=mergetopics;sa=LETSBREAKIT won't work, sorry.
	if (empty($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]))
		MergeIndex();
	else
		$subActions[$_REQUEST['sa']]();
}

// Merge two topics together.
function MergeIndex()
{
	global $txt, $board, $topic, $context, $scripturl, $settings;

	if (!isset($topic))
		fatal_lang_error('no_access', false);

	$_REQUEST['targetboard'] = isset($_REQUEST['targetboard']) ? (int) $_REQUEST['targetboard'] : $board;
	$context['target_board'] = $_REQUEST['targetboard'];

	// Prepare a handy query bit for approval...
	if ($settings['postmod_active'])
	{
		$can_approve_boards = boardsAllowedTo('approve_posts');
		$onlyApproved = $can_approve_boards !== array(0) && !in_array($_REQUEST['targetboard'], $can_approve_boards);
	}
	else
		$onlyApproved = false;

	// How many topics are on this board? (Used for paging.)
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}topics AS t
		WHERE t.id_board = {int:id_board}
			AND t.id_topic != {int:id_topic}' . ($onlyApproved ? '
			AND t.approved = {int:is_approved}' : ''),
		array(
			'id_board' => $_REQUEST['targetboard'],
			'id_topic' => $topic,
			'is_approved' => 1,
		)
	);
	list ($topiccount) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Make the page list.
	$_REQUEST['page'] = empty($_REQUEST['page']) ? 0 : (int) $_REQUEST['page'];
	$context['page_index'] = template_page_index($scripturl . '?action=mergetopics;topic=' . $topic . ';targetboard=' . $_REQUEST['targetboard'] . ';page=%1$d', $_REQUEST['page'], $topiccount, $settings['defaultMaxTopics'], true);

	// Get the topic's subject.
	$request = wesql::query('
		SELECT m.subject, m.icon
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:id_topic}
			AND t.id_board = {int:current_board}' . ($onlyApproved ? '
			AND t.approved = {int:is_approved}' : '') . '
		LIMIT 1',
		array(
			'current_board' => $board,
			'id_topic' => $topic,
			'is_approved' => 1,
		)
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('no_board');
	list ($subject, $icon) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Before we begin, is this a moved notice? We can't merge those with things.
	if ($icon == 'moved')
		fatal_lang_error('cannot_merge_moved', 'user');

	// Tell the template a few things..
	$context['origin_topic'] = $topic;
	$context['origin_subject'] = $subject;
	$context['origin_js_subject'] = addcslashes(addslashes($subject), '/');
	$context['page_title'] = $txt['merge'];

	// Check which boards you have merge permissions on.
	$merge_boards = boardsAllowedTo('merge_any');

	if (empty($merge_boards))
		fatal_lang_error('cannot_merge_any', 'user');

	// Get a list of boards they can navigate to to merge.
	$request = wesql::query('
		SELECT b.id_board, b.name AS board_name, b.child_level, c.name AS cat_name
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}' . (!in_array(0, $merge_boards) ? '
			AND b.id_board IN ({array_int:merge_boards})' : ''),
		array(
			'merge_boards' => $merge_boards,
		)
	);
	$context['boards'] = array();
	while ($row = wesql::fetch_assoc($request))
		$context['boards'][] = array(
			'id' => $row['id_board'],
			'name' => $row['board_name'],
			'category' => $row['cat_name'],
			'child_level' => $row['child_level'],
		);
	wesql::free_result($request);

	// Get some topics to merge it with.
	$request = wesql::query('
		SELECT t.id_topic, m.subject, m.id_member, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.icon
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE t.id_board = {int:id_board}
			AND t.id_topic != {int:id_topic}' . ($onlyApproved ? '
			AND t.approved = {int:is_approved}' : '') . '
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'id_board' => $_REQUEST['targetboard'],
			'id_topic' => $topic,
			'sort' => 't.is_pinned DESC, t.id_last_msg DESC',
			'offset' => $_REQUEST['page'],
			'limit' => $settings['defaultMaxTopics'],
			'is_approved' => 1,
		)
	);
	$context['topics'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		if ($row['icon'] == 'moved')
			continue; // We should ignore moved topics, but it's cheaper to do it here.

		censorText($row['subject']);

		$context['topics'][] = array(
			'id' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '" target="_blank" class="new_win">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'js_subject' => addcslashes(addslashes($row['subject']), '/')
		);
	}
	wesql::free_result($request);

	if (empty($context['topics']) && count($context['boards']) <= 1)
		fatal_lang_error('merge_need_more_topics');

	wetem::load('merge');
}

// Now that the topic IDs are known, do the proper merging.
function MergeExecute($topics = array())
{
	global $txt, $context, $scripturl, $settings, $topic;

	// Check the session.
	checkSession('request');

	// Handle URLs from MergeIndex.
	if (!empty($topic) && !empty($_GET['to']))
		$topics = array((int) $topic, (int) $_GET['to']);

	// If we came from a form, the topic IDs came by post.
	if (!empty($_POST['topics']) && is_array($_POST['topics']))
		$topics = $_POST['topics'];

	// There's nothing to merge with just one topic...
	if (empty($topics) || !is_array($topics) || count($topics) == 1)
		fatal_lang_error('merge_need_more_topics');

	// Make sure every topic is numeric, or some nasty things could be done with the DB.
	foreach ($topics as $id => $topic)
		$topics[$id] = (int) $topic;

	// Joy of all joys, make sure they're not pi**ing about with unapproved topics they can't see :P
	if ($settings['postmod_active'])
		$can_approve_boards = boardsAllowedTo('approve_posts');

	// Get info about the topics and polls that will be merged.
	$request = wesql::query('
		SELECT
			t.id_topic, t.id_board, t.id_poll, t.num_views, t.is_pinned, t.approved, t.num_replies, t.unapproved_posts, m1.icon,
			m1.subject, m1.poster_time AS time_started, IFNULL(mem1.id_member, 0) AS id_member_started, IFNULL(mem1.real_name, m1.poster_name) AS name_started,
			m2.poster_time AS time_updated, IFNULL(mem2.id_member, 0) AS id_member_updated, IFNULL(mem2.real_name, m2.poster_name) AS name_updated
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m1 ON (m1.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS m2 ON (m2.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem1 ON (mem1.id_member = m1.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m2.id_member)
		WHERE t.id_topic IN ({array_int:topic_list})
		ORDER BY t.id_first_msg
		LIMIT ' . count($topics),
		array(
			'topic_list' => $topics,
		)
	);
	if (wesql::num_rows($request) < 2)
		fatal_lang_error('no_topic_id');
	$num_views = 0;
	$is_pinned = 0;
	$boardTotals = array();
	$boards = array();
	$polls = array();
	while ($row = wesql::fetch_assoc($request))
	{
		if ($row['icon'] == 'moved')
			fatal_lang_error('cannot_merge_moved', 'user');

		// Make a note for the board counts...
		if (!isset($boardTotals[$row['id_board']]))
			$boardTotals[$row['id_board']] = array(
				'posts' => 0,
				'topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0
			);

		// We can't see unapproved topics here?
		if ($settings['postmod_active'] && !$row['approved'] && $can_approve_boards != array(0) && in_array($row['id_board'], $can_approve_boards))
			continue;
		elseif (!$row['approved'])
			$boardTotals[$row['id_board']]['unapproved_topics']++;
		else
			$boardTotals[$row['id_board']]['topics']++;

		$boardTotals[$row['id_board']]['unapproved_posts'] += $row['unapproved_posts'];
		$boardTotals[$row['id_board']]['posts'] += $row['num_replies'] + ($row['approved'] ? 1 : 0);

		$topic_data[$row['id_topic']] = array(
			'id' => $row['id_topic'],
			'board' => $row['id_board'],
			'poll' => $row['id_poll'],
			'num_views' => $row['num_views'],
			'subject' => $row['subject'],
			'started' => array(
				'time' => timeformat($row['time_started']),
				'timestamp' => forum_time(true, $row['time_started']),
				'href' => empty($row['id_member_started']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member_started'],
				'link' => empty($row['id_member_started']) ? $row['name_started'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_started'] . '">' . $row['name_started'] . '</a>'
			),
			'updated' => array(
				'time' => timeformat($row['time_updated']),
				'timestamp' => forum_time(true, $row['time_updated']),
				'href' => empty($row['id_member_updated']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member_updated'],
				'link' => empty($row['id_member_updated']) ? $row['name_updated'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member_updated'] . '">' . $row['name_updated'] . '</a>'
			)
		);
		$num_views += $row['num_views'];
		$boards[] = $row['id_board'];

		// If there's no poll, id_poll == 0...
		if ($row['id_poll'] > 0)
			$polls[] = $row['id_poll'];
		// Store the id_topic with the lowest id_first_msg.
		if (empty($firstTopic))
			$firstTopic = $row['id_topic'];

		$is_pinned = max($is_pinned, $row['is_pinned']);
	}
	wesql::free_result($request);

	// If we didn't get any topics then they've been messing with unapproved stuff.
	if (empty($topic_data))
		fatal_lang_error('no_topic_id');

	$boards = array_values(array_unique($boards));

	// The parameters of MergeExecute were set, so this must've been an internal call.
	if (!empty($topics))
	{
		isAllowedTo('merge_any', $boards);
		loadTemplate('Merge');
	}

	// Get the boards a user is allowed to merge in.
	$merge_boards = boardsAllowedTo('merge_any');
	if (empty($merge_boards))
		fatal_lang_error('cannot_merge_any', 'user');

	// Make sure they can see all boards....
	$request = wesql::query('
		SELECT b.id_board
		FROM {db_prefix}boards AS b
		WHERE b.id_board IN ({array_int:boards})
			AND {query_see_board}' . (!in_array(0, $merge_boards) ? '
			AND b.id_board IN ({array_int:merge_boards})' : '') . '
		LIMIT ' . count($boards),
		array(
			'boards' => $boards,
			'merge_boards' => $merge_boards,
		)
	);
	// If the number of boards that's in the output isn't exactly the same as we've put in there, you're in trouble.
	if (wesql::num_rows($request) != count($boards))
		fatal_lang_error('no_board');
	wesql::free_result($request);

	if (empty($_REQUEST['sa']) || $_REQUEST['sa'] == 'options' || $_REQUEST['sa'] == 'internal')
	{
		if (count($polls) > 1)
		{
			$request = wesql::query('
				SELECT t.id_topic, t.id_poll, m.subject, p.question
				FROM {db_prefix}polls AS p
					INNER JOIN {db_prefix}topics AS t ON (t.id_poll = p.id_poll)
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE p.id_poll IN ({array_int:polls})
				LIMIT ' . count($polls),
				array(
					'polls' => $polls,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				$context['polls'][] = array(
					'id' => $row['id_poll'],
					'topic' => array(
						'id' => $row['id_topic'],
						'subject' => $row['subject']
					),
					'question' => $row['question'],
					'selected' => $row['id_topic'] == $firstTopic
				);
			wesql::free_result($request);
		}
		if (count($boards) > 1)
		{
			$request = wesql::query('
				SELECT id_board, name
				FROM {db_prefix}boards
				WHERE id_board IN ({array_int:boards})
				ORDER BY name
				LIMIT ' . count($boards),
				array(
					'boards' => $boards,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				$context['boards'][] = array(
					'id' => $row['id_board'],
					'name' => $row['name'],
					'selected' => $row['id_board'] == $topic_data[$firstTopic]['board']
				);
			wesql::free_result($request);
		}

		$context['topics'] = $topic_data;
		foreach ($topic_data as $id => $topic)
			$context['topics'][$id]['selected'] = $topic['id'] == $firstTopic;

		$context['page_title'] = $txt['merge'];
		wetem::load('merge_extra_options');

		if (empty($_REQUEST['sa']) || $_REQUEST['sa'] == 'options')
			return;
	}

	// Determine target board.
	$target_board = count($boards) > 1 ? (int) $_REQUEST['board'] : $boards[0];
	if (!in_array($target_board, $boards))
		fatal_lang_error('no_board');

	// Determine which poll will survive and which polls won't.
	$target_poll = count($polls) > 1 ? (int) $_POST['poll'] : (count($polls) == 1 ? $polls[0] : 0);
	if ($target_poll > 0 && !in_array($target_poll, $polls))
		fatal_lang_error('no_access', false);
	$deleted_polls = empty($target_poll) ? $polls : array_diff($polls, array($target_poll));

	// Determine the subject of the newly merged topic - was a custom subject specified?
	if (empty($_POST['subject']) && isset($_POST['custom_subject']) && $_POST['custom_subject'] != '')
	{
		$target_subject = strtr(westr::htmltrim(westr::htmlspecialchars($_POST['custom_subject'])), array("\r" => '', "\n" => '', "\t" => ''));
		// Keep checking the length.
		if (westr::strlen($target_subject) > 100)
			$target_subject = westr::substr($target_subject, 0, 100);

		// Nothing left - odd but pick the first topics subject.
		if ($target_subject == '')
			$target_subject = $topic_data[$firstTopic]['subject'];
	}
	// A subject was selected from the list.
	elseif (isset($_POST['subject']) && !empty($topic_data[(int) $_POST['subject']]['subject']))
		$target_subject = $topic_data[(int) $_POST['subject']]['subject'];
	// Nothing worked? Just take the subject of the first message.
	else
		$target_subject = $topic_data[$firstTopic]['subject'];

	// Get the first and last message and the number of messages....
	$request = wesql::query('
		SELECT approved, MIN(id_msg) AS first_msg, MAX(id_msg) AS last_msg, COUNT(*) AS message_count
		FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topics})
		GROUP BY approved
		ORDER BY approved DESC',
		array(
			'topics' => $topics,
		)
	);
	$topic_approved = 1;
	while ($row = wesql::fetch_assoc($request))
	{
		// If this is approved, or is fully unapproved.
		if ($row['approved'] || !isset($first_msg))
		{
			$first_msg = $row['first_msg'];
			$last_msg = $row['last_msg'];
			if ($row['approved'])
			{
				$num_replies = $row['message_count'] - 1;
				$num_unapproved = 0;
			}
			else
			{
				$topic_approved = 0;
				$num_replies = 0;
				$num_unapproved = $row['message_count'];
			}
		}
		else
		{
			// If this has a lower first_msg then the first post is not approved and hence the number of replies was wrong!
			if ($first_msg > $row['first_msg'])
			{
				$first_msg = $row['first_msg'];
				$num_replies++;
				$topic_approved = 0;
			}
			$num_unapproved = $row['message_count'];
		}
	}
	wesql::free_result($request);

	// Ensure we have a board stat for the target board.
	if (!isset($boardTotals[$target_board]))
	{
		$boardTotals[$target_board] = array(
			'posts' => 0,
			'topics' => 0,
			'unapproved_posts' => 0,
			'unapproved_topics' => 0
		);
	}

	// Fix the topic count stuff depending on what the new one counts as.
	if ($topic_approved)
		$boardTotals[$target_board]['topics']--;
	else
		$boardTotals[$target_board]['unapproved_topics']--;

	$boardTotals[$target_board]['unapproved_posts'] -= $num_unapproved;
	$boardTotals[$target_board]['posts'] -= $topic_approved ? $num_replies + 1 : $num_replies;

	// Get the member ID of the first and last message.
	$request = wesql::query('
		SELECT id_member
		FROM {db_prefix}messages
		WHERE id_msg IN ({int:first_msg}, {int:last_msg})
		ORDER BY id_msg
		LIMIT 2',
		array(
			'first_msg' => $first_msg,
			'last_msg' => $last_msg,
		)
	);
	list ($member_started) = wesql::fetch_row($request);
	list ($member_updated) = wesql::fetch_row($request);
	// First and last message are the same, so only row was returned.
	if ($member_updated === null)
		$member_updated = $member_started;

	wesql::free_result($request);

	if (!empty($settings['pretty_enable_cache']))
	{
		wesql::query('
			DELETE FROM {db_prefix}pretty_topic_urls
			WHERE id_topic IN ({array_int:deleted_topics})',
			array(
				'deleted_topics' => $deleted_topics,
			)
		);
		wesql::query('
			DELETE FROM {db_prefix}pretty_urls_cache
			WHERE (url_id LIKE "%' . implode('%") OR (url_id LIKE "%', $deleted_topics) . '%")',
			array()
		);
	}

	// Assign the first topic ID to be the merged topic.
	$id_topic = min($topics);

	// Delete the remaining topics.
	$deleted_topics = array_diff($topics, array($id_topic));
	wesql::query('
		DELETE FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:deleted_topics})',
		array(
			'deleted_topics' => $deleted_topics,
		)
	);
	wesql::query('
		DELETE FROM {db_prefix}log_search_subjects
		WHERE id_topic IN ({array_int:deleted_topics})',
		array(
			'deleted_topics' => $deleted_topics,
		)
	);

	// Asssign the properties of the newly merged topic.
	wesql::query('
		UPDATE {db_prefix}topics
		SET
			id_board = {int:id_board},
			id_member_started = {int:id_member_started},
			id_member_updated = {int:id_member_updated},
			id_first_msg = {int:id_first_msg},
			id_last_msg = {int:id_last_msg},
			id_poll = {int:id_poll},
			num_replies = {int:num_replies},
			unapproved_posts = {int:unapproved_posts},
			num_views = {int:num_views},
			is_pinned = {int:is_pinned},
			approved = {int:approved_state}
		WHERE id_topic = {int:id_topic}',
		array(
			'id_board' => $target_board,
			'is_pinned' => $is_pinned,
			'approved_state' => $topic_approved,
			'id_topic' => $id_topic,
			'id_member_started' => $member_started,
			'id_member_updated' => $member_updated,
			'id_first_msg' => $first_msg,
			'id_last_msg' => $last_msg,
			'id_poll' => $target_poll,
			'num_replies' => $num_replies,
			'unapproved_posts' => $num_unapproved,
			'num_views' => $num_views,
		)
	);

	// Grab the response prefix (like 'Re: ') in the default forum language.
	getRePrefix();

	// Change the topic IDs of all messages that will be merged.  Also adjust subjects if 'enforce subject' was checked.
	wesql::query('
		UPDATE {db_prefix}messages
		SET
			id_topic = {int:id_topic},
			id_board = {int:target_board}' . (empty($_POST['enforce_subject']) ? '' : ',
			subject = {string:subject}') . '
		WHERE id_topic IN ({array_int:topic_list})',
		array(
			'topic_list' => $topics,
			'id_topic' => $id_topic,
			'target_board' => $target_board,
			'subject' => $context['response_prefix'] . $target_subject,
		)
	);

	// Any reported posts should reflect the new board.
	wesql::query('
		UPDATE {db_prefix}log_reported
		SET
			id_topic = {int:id_topic},
			id_board = {int:target_board}
		WHERE id_topic IN ({array_int:topics_list})',
		array(
			'topics_list' => $topics,
			'id_topic' => $id_topic,
			'target_board' => $target_board,
		)
	);

	// Change the subject of the first message...
	wesql::query('
		UPDATE {db_prefix}messages
		SET subject = {string:target_subject}
		WHERE id_msg = {int:first_msg}',
		array(
			'first_msg' => $first_msg,
			'target_subject' => $target_subject,
		)
	);

	// Do anything else that we might want to do.
	call_hook('merge_topics', array(&$topics, &$id_topic, &$deleted_topics, &$target_board, &$first_msg, &$target_subject));

	// Merge log topic entries.
	$request = wesql::query('
		SELECT id_member, MIN(id_msg) AS new_id_msg
		FROM {db_prefix}log_topics
		WHERE id_topic IN ({array_int:topics})
		GROUP BY id_member',
		array(
			'topics' => $topics,
		)
	);
	if (wesql::num_rows($request) > 0)
	{
		$replaceEntries = array();
		while ($row = wesql::fetch_assoc($request))
			$replaceEntries[] = array($row['id_member'], $id_topic, $row['new_id_msg']);

		wesql::insert('replace',
			'{db_prefix}log_topics',
			array('id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int'),
			$replaceEntries,
			array('id_member', 'id_topic')
		);
		unset($replaceEntries);

		// Get rid of the old log entries.
		wesql::query('
			DELETE FROM {db_prefix}log_topics
			WHERE id_topic IN ({array_int:deleted_topics})',
			array(
				'deleted_topics' => $deleted_topics,
			)
		);
	}
	wesql::free_result($request);

	// Merge topic notifications.
	$notifications = isset($_POST['notifications']) && is_array($_POST['notifications']) ? array_intersect($topics, $_POST['notifications']) : array();
	if (!empty($notifications))
	{
		$request = wesql::query('
			SELECT id_member, MAX(sent) AS sent
			FROM {db_prefix}log_notify
			WHERE id_topic IN ({array_int:topics_list})
			GROUP BY id_member',
			array(
				'topics_list' => $notifications,
			)
		);
		if (wesql::num_rows($request) > 0)
		{
			$replaceEntries = array();
			while ($row = wesql::fetch_assoc($request))
				$replaceEntries[] = array($row['id_member'], $id_topic, 0, $row['sent']);

			wesql::insert('replace',
					'{db_prefix}log_notify',
					array('id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int', 'sent' => 'int'),
					$replaceEntries,
					array('id_member', 'id_topic', 'id_board')
				);
			unset($replaceEntries);

			wesql::query('
				DELETE FROM {db_prefix}log_topics
				WHERE id_topic IN ({array_int:deleted_topics})',
				array(
					'deleted_topics' => $deleted_topics,
				)
			);
		}
		wesql::free_result($request);
	}

	// Get rid of the redundant polls.
	if (!empty($deleted_polls))
	{
		wesql::query('
			DELETE FROM {db_prefix}polls
			WHERE id_poll IN ({array_int:deleted_polls})',
			array(
				'deleted_polls' => $deleted_polls,
			)
		);
		wesql::query('
			DELETE FROM {db_prefix}poll_choices
			WHERE id_poll IN ({array_int:deleted_polls})',
			array(
				'deleted_polls' => $deleted_polls,
			)
		);
		wesql::query('
			DELETE FROM {db_prefix}log_polls
			WHERE id_poll IN ({array_int:deleted_polls})',
			array(
				'deleted_polls' => $deleted_polls,
			)
		);
	}

	// Cycle through each board...
	foreach ($boardTotals as $id_board => $stats)
	{
		wesql::query('
			UPDATE {db_prefix}boards
			SET
				num_topics = CASE WHEN {int:topics} > num_topics THEN 0 ELSE num_topics - {int:topics} END,
				unapproved_topics = CASE WHEN {int:unapproved_topics} > unapproved_topics THEN 0 ELSE unapproved_topics - {int:unapproved_topics} END,
				num_posts = CASE WHEN {int:posts} > num_posts THEN 0 ELSE num_posts - {int:posts} END,
				unapproved_posts = CASE WHEN {int:unapproved_posts} > unapproved_posts THEN 0 ELSE unapproved_posts - {int:unapproved_posts} END
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $id_board,
				'topics' => $stats['topics'],
				'unapproved_topics' => $stats['unapproved_topics'],
				'posts' => $stats['posts'],
				'unapproved_posts' => $stats['unapproved_posts'],
			)
		);
	}

	// Determine the board the final topic resides in
	$request = wesql::query('
		SELECT id_board
		FROM {db_prefix}topics
		WHERE id_topic = {int:id_topic}
		LIMIT 1',
		array(
			'id_topic' => $id_topic,
		)
	);
	list ($id_board) = wesql::fetch_row($request);
	wesql::free_result($request);

	loadSource('Subs-Post');

	// Update all the statistics.
	updateStats('topic');
	updateStats('subject', $id_topic, $target_subject);
	updateLastMessages($boards);

	logAction('merge', array('topic' => $id_topic, 'board' => $id_board));

	// Notify people that these topics have been merged?
	sendNotifications($id_topic, 'merge');

	// Send them to the all done page.
	redirectexit('action=mergetopics;sa=done;to=' . $id_topic . ';targetboard=' . $target_board);
}

// Tell the user the move was done properly.
function MergeDone()
{
	global $txt, $context;

	// Make sure the template knows everything...
	$context['target_board'] = (int) $_GET['targetboard'];
	$context['target_topic'] = (int) $_GET['to'];

	$context['page_title'] = $txt['merge'];
	wetem::load('merge_done');
}

function MergePosts($error_report = true)
{
	global $settings, $txt, $theme;

	loadLanguage('Errors');
	if (!is_bool($error_report))
		$error_report = true;

	if (empty($_REQUEST['msgid']) || !is_numeric($_REQUEST['msgid']) || $_REQUEST['msgid'] < 1 || empty($_REQUEST['pid']) || !is_numeric($_REQUEST['pid']) || $_REQUEST['pid'] < 1 || empty($_REQUEST['topic']) || !is_numeric($_REQUEST['topic']) || $_REQUEST['topic'] < 1)
		if ($error_report)
			fatal_lang_error('merge_error_noid', false);
		else
			return;

	$topic = $_REQUEST['topic'];
	$msg_id = min($_REQUEST['msgid'], $_REQUEST['pid']);
	$qc = $_REQUEST['msgid'] == $_REQUEST['pid'];

	// Can the user actually merge posts?
	$request = wesql::query('
		SELECT
			m.id_msg, m.id_member, m.body, b.count_posts, m.id_board,
			t.id_first_msg, m.subject, m.poster_time, m.poster_email, m.poster_name
		FROM
			{db_prefix}messages AS m,
			{db_prefix}boards AS b,
			{db_prefix}topics AS t
		WHERE
			{query_see_topic}
			AND m.id_topic = {int:id_topic}
			AND id_msg ' . ($qc ? '<' : '>') . '= {int:id_msg}
			AND b.id_board = m.id_board
			AND t.id_topic = m.id_topic
		ORDER BY id_msg' . ($qc ? ' DESC' : '') . '
		LIMIT 2',
		array(
			'id_topic' => $topic,
			'id_msg' => $msg_id,
		)
	);

	while ($row = wesql::fetch_assoc($request))
		$msn[] = array(
			'id_member' => $row['id_member'],
			'common_id' => empty($row['id_member']) ? (empty($row['poster_email']) ? $row['poster_name'] : $row['poster_email']) : $row['id_member'],
			'subject' => $row['subject'],
			'body' => $row['body'],
			'id_msg' => $row['id_msg'],
			'count_posts' => $row['count_posts'],
			'id_board' => $row['id_board'],
			'id_first_msg' => $row['id_first_msg'],
			'timestamp' => $row['poster_time'],
		);
	wesql::free_result($request);

	// Reverse the order
	if ($qc)
	{
		$msn = array(
			'0' => $msn['1'],
			'1' => $msn['0'],
		);

		// Automatic merge time
		if (!empty($settings['merge_post_auto_time']) && $settings['merge_post_auto_time'] > 0 && ($msn['1']['timestamp'] - $msn['0']['timestamp']) > $settings['merge_post_auto_time'])
			return;
	}

	if (((!empty($msn['0']['id_member']) && !empty($msn['1']['id_member'])) || we::$id == 1) && $msn['0']['id_board'] == $msn['1']['id_board'])
	{
		if ($msn['0']['common_id'] == $msn['1']['common_id'] && (allowedTo('modify_any') || (allowedTo('modify_own') && $msn['0']['id_member'] == we::$id)))
		{
			// Let's merge it and use a separator
			if (!empty($settings['merge_post_custom_separator']))
			{
				if (empty($settings['merge_post_separator']))
					$settings['merge_post_separator'] = '[br]';
				else
				{
					$settings['merge_post_separator'] = westr::htmlspecialchars($settings['merge_post_separator'], ENT_QUOTES);
					$date = '[mergedate]' . $msn['0']['timestamp'] . '[/mergedate]';
					$settings['merge_post_separator'] = str_replace('$date', $date, $settings['merge_post_separator']);
				}
				$newbody = $msn['0']['body'] . $settings['merge_post_separator'] . $msn['1']['body'];
			}
			else
				$newbody = $msn['0']['body'] . (empty($settings['merge_post_no_sep']) ? (empty($settings['merge_post_no_time']) ?
							'<br>[mergedate]' . $msn['0']['timestamp'] . '[/mergedate]' : '') . '<br>' : '<br>') . $msn['1']['body'];

			$memberid = $msn['0']['id_member'];
			$postcount = $msn['0']['count_posts'];
			$oldpostid = $msn['0']['id_msg'];
			$newpostid = $msn['1']['id_msg'];
			$idboard = $msn['0']['id_board'];
			$newpostlength = (empty($settings['merge_post_ignore_length']) && $settings['max_messageLength'] > 0) ? strlen(un_htmlspecialchars($newbody)) : 0;
			$oldsubject = '';
			$replacefirstid = '';

			// First check the length of the post, if the limit is reached don't merge it! Also, the Automatic Merge will not work!
			if (empty($modSetting['merge_post_ignore_length']) && $settings['max_messageLength'] < $newpostlength)
				if ($error_report)
					fatal_lang_error('merge_error_length', false);
				else
					return;

			// Removing the first message in the topic?
			if ($oldpostid == $msn['0']['id_first_msg'])
			{
				$replacefirstid = ', id_first_msg = ' . (int) $newpostid;

				// Keep the first post's title as topic title.
				$msn['0']['subject'] = str_replace("'", "&#039;", $msn['0']['subject']);
				$oldsubject = ', subject = {string:subject}';
			}

			// Uhh the old post can have attachments
			// If SQL finds some attachments, it should replace them with the new id
			wesql::query('
				UPDATE {db_prefix}attachments
				SET id_msg = {int:new}
				WHERE id_msg = {int:old}',
				array(
					'new' => $newpostid,
					'old' => $oldpostid
				)
			);

			// Fix some statistics stuff
			wesql::query('
				UPDATE {db_prefix}topics
				SET num_replies = num_replies - 1' . $replacefirstid . '
				WHERE id_topic = {int:id_topic}
				LIMIT 1',
				array(
					'id_topic' => $topic
				)
			);

			wesql::query('
				UPDATE {db_prefix}boards
				SET num_posts = num_posts - 1
				WHERE id_board = {int:id_board}
				LIMIT 1',
				array(
					'id_board' => $idboard
				)
			);

			// If the poster was registered and the board this message was on incremented
			// the member's posts when it was posted, decrease his or her post count.
			if (!empty($memberid) && empty($postcount))
				updateMemberData($memberid, array('posts' => '-'));

			// Update likes on the old post to the new post.
			// Unfortunately we can't just update one to the other, in case people individually liked both posts.
			$likes = array(
				$oldpostid => array(),
				$newpostid => array(),
			);
			$query = wesql::query('
				SELECT id_content, id_member
				FROM {db_prefix}likes
				WHERE id_content IN ({array_int:ids}) AND content_type = {literal:post}',
				array(
					'ids' => array($newpostid, $oldpostid),
				)
			);
			while ($row = wesql::fetch_assoc($query))
				$likes[$row['id_content']][] = $row['id_member'];
			wesql::free_result($query);

			if (!empty($likes[$oldpostid]))
			{
				// OK, so someone actually liked the old post. Have they liked the new post too?
				$liked_both = array_intersect($likes[$oldpostid], $likes[$newpostid]);
				if (!empty($liked_both))
					wesql::query('
						DELETE FROM {db_prefix}likes
						WHERE id_content = {int:oldpostid}
							AND content_type = {literal:post}
							AND id_member IN ({array_int:likesboth})',
						array(
							'oldpostid' => $oldpostid,
							'likesboth' => $liked_both,
						)
					);

				// Anyone left who liked the old post on its own?
				$liked_old = array_diff($likes[$oldpostid], $liked_both);
				if (!empty($liked_old))
					wesql::query('
						UPDATE {db_prefix}likes
						SET id_content = {int:newpostid}
						WHERE id_content = {int:oldpostid}
							AND content_type = {literal:post}
							AND id_member IN ({array_int:likesold})',
						array(
							'newpostid' => $newpostid,
							'oldpostid' => $oldpostid,
							'likesold' => $liked_old,
						)
					);
			}

			// Merge the post
			wesql::query('
				UPDATE {db_prefix}messages
				SET body = {string:newbody}' . $oldsubject . '
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'newbody' => $newbody,
					'id_msg' => $newpostid,
					'subject' => $msn['0']['subject']
				)
			);

			// Remove the old message!
			wesql::query('
				DELETE FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $oldpostid
				)
			);

			// Now go back to the topic
			if ($error_report)
				redirectexit('topic=' . $topic . '.msg' . $newpostid . '#msg' . $newpostid);
			else
				return;
		}
		else
			if ($error_report)
				fatal_lang_error('merge_error_dbpo', false);
			else
				return;
	}
	elseif ($error_report)
		fatal_lang_error('merge_error_notf', false);
	else
		return;
}
