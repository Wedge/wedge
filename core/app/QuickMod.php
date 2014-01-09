<?php
/**
 * Dealing with topic moderation-type operations right from the list of topics.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function QuickModeration()
{
	global $board, $settings, $context;

	// Check the session = get or post.
	checkSession('request');

	// Let's go straight to the restore area.
	if (isset($_REQUEST['qaction']) && $_REQUEST['qaction'] == 'restore' && !empty($_REQUEST['topics']))
		redirectexit('action=restoretopic;topics=' . implode(',', $_REQUEST['topics']) . ';' . $context['session_query']);

	if (isset($_SESSION['seen_cache']))
		$_SESSION['seen_cache'] = array();

	// This is going to be needed to send off the notifications and for updateLastMessages().
	loadSource('Subs-Post');

	// Remember the last board they moved things to.
	if (isset($_REQUEST['move_to']))
		$_SESSION['move_to_topic'] = $_REQUEST['move_to'];

	// action => is own/any pair, permission(_own/any) or true for just is-logged-in, function
	$quickMod = array(
		'pin' => array(false, 'pin_topic', 'quickMod_pin'),
		'move' => array(true, 'move', 'quickMod_move'),
		'remove' => array(true, 'remove', 'quickMod_remove'),
		'lock' => array(true, 'lock', 'quickMod_lock'),
		'merge' => array(false, 'merge_any', 'quickMod_merge'),
		'approve' => array(false, 'approve_posts', 'quickMod_approve'),
		'markread' => array(false, true, 'quickMod_markread'),
	);
	if (empty($settings['postmod_enabled']))
		unset($quickMod['approve']);

	call_hook('apply_quickmod', array(&$quickMod));

	// Are we getting out of here?
	if (!empty($board))
		$redirect_url = 'board=' . $board . '.' . $_REQUEST['start'];
	else
		$redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : (isset($_SESSION['old_url']) ? $_SESSION['old_url'] : '');

	if (we::$is_guest || !isset($_REQUEST['topics'], $_REQUEST['qaction'], $quickMod[$_REQUEST['qaction']]) || !is_array($_REQUEST['topics']))
		redirectexit($redirect_url);

	// Now we know that we're at least trying to perform a valid action, can we actually do it?
	$thisAction = $quickMod[$_REQUEST['qaction']];
	$permitted = true;
	$boards_can = array();
	if ($thisAction[1] !== true)
	{
		if (!empty($board))
		{
			if (!$thisAction[0])
			{
				$boards_can[$thisAction[1]] = allowedTo($thisAction[1]) ? array($board) : array();
				if (empty($boards_can[$thisAction[1]]))
					$permitted = false;
			}
			else
			{
				$boards_can[$thisAction[1] . '_own'] = allowedTo($thisAction[1] . '_own') ? array($board) : array();
				$boards_can[$thisAction[1] . '_any'] = allowedTo($thisAction[1] . '_any') ? array($board) : array();
				if (empty($boards_can[$thisAction[1] . '_own']) && empty($boards_can[$thisAction[1] . '_any']))
					$permitted = false;
			}
		}
		else
		{
			$perms = !$thisAction[0] ? array($thisAction[1], 'approve_posts') : array($thisAction[1] . '_own', $thisAction[1] . '_any', 'approve_posts');
			$boards_can = boardsAllowedTo($perms);
			if (!$thisAction[0])
			{
				// This has to be in brackets to avoid PHP getting confused otherwise.
				if (empty($boards_can[$thisAction[1]]))
					$permitted = false;
			}
			elseif (empty($boards_can[$thisAction[1] . '_own']) && empty($boards_can[$thisAction[1] . '_any']))
				$permitted = false;
		}
	}

	if (!$permitted)
		fatal_lang_error('no_board', false);

	// So we've validated that we have relevant permissions somewhere. Now to get the topics and validate the boards we need.
	foreach ($_REQUEST['topics'] as $k => $v)
		$_REQUEST['topics'][$k] = (int) $v;
	$_REQUEST['topics'] = array_diff($_REQUEST['topics'], array(0));
	if (empty($_REQUEST['topics']))
		fatal_lang_error('no_board', false);

	$topic_ids = array();
	$request = wesql::query('
		SELECT id_topic, id_member_started, id_board, locked, approved, unapproved_posts
		FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:topic_ids})
		LIMIT ' . count($_REQUEST['topics']),
		array(
			'topic_ids' => $_REQUEST['topics'],
		)
	);
	$thisAction = $quickMod[$_REQUEST['qaction']];
	while ($row = wesql::fetch_assoc($request))
	{
		$board_intersect = array(0, $row['id_board']);
		// We're in a board, validate that the topic is in the board concerned and that it's approved or that we can see it
		if (!empty($board))
		{
			if ($row['id_board'] != $board || ($settings['postmod_active'] && !$row['approved'] && !allowedTo('approve_posts')))
				continue;
		}
		else
		{
			// We already verified that we have permission in the current board to do what we want to do. But now we're doing it without a current board.
			// First, don't allow them to act on unapproved posts they can't see...
			if ($settings['postmod_active'] && !$row['approved'] && !in_array(0, $boards_can['approve_posts']) && !in_array($row['id_board'], $boards_can['approve_posts']))
				continue;

			// 2a, is it actually a permission?
			if ($thisAction[1] !== true)
			{
				// 2b, verify what they want to do against whether they have permission. Some qmod actions might need to do their own specific checks. That's fine, but they can do them themselves.
				if ($thisAction[0])
				{
					if ((count(array_intersect($board_intersect, $boards_can[$thisAction[1] . '_any'])) == 0) && ($row['id_member_started'] != MID || (count(array_intersect($board_intersect, $boards_can[$thisAction[1] . '_own'])) == 0)))
						continue;
				}
				else
				{
					if (count(array_intersect($board_intersect, $boards_can[$thisAction[1]])) == 0)
						continue;
				}
			}
		}

		// Oh goody, we passed. Store that information for use later on.
		$topic_ids[$row['id_topic']] = $row;
	}

	// If a given operation requires updating the stats, let it do that, it's not something we have to worry about here.
	if (!empty($topic_ids))
		$thisAction[2]($topic_ids, $boards_can);

	if (!empty($board))
		updateLastMessages(array($board));

	redirectexit($redirect_url);
}

// In-topic quick moderation.
function QuickInTopicModeration()
{
	global $topic, $board, $settings, $context;

	// Check the session = get or post.
	checkSession('request');

	loadSource('RemoveTopic');

	if (empty($_REQUEST['msgs']))
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);

	$messages = array();
	foreach ($_REQUEST['msgs'] as $dummy)
		$messages[] = (int) $dummy;

	// We are restoring messages. We handle this in another place.
	if (isset($_REQUEST['restore_selected']))
		redirectexit('action=restoretopic;msgs=' . implode(',', $messages) . ';' . $context['session_query']);

	// Allowed to delete any message?
	if (allowedTo('delete_any'))
		$allowed_all = true;
	// Allowed to delete replies to their messages?
	elseif (allowedTo('delete_replies'))
	{
		$request = wesql::query('
			SELECT id_member_started
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => $topic,
			)
		);
		list ($starter) = wesql::fetch_row($request);
		wesql::free_result($request);

		$allowed_all = $starter == MID;
	}
	else
		$allowed_all = false;

	// Make sure they're allowed to delete their own messages, if not any.
	if (!$allowed_all)
		isAllowedTo('delete_own');

	// Allowed to remove which messages?
	$request = wesql::query('
		SELECT id_msg, subject, id_member, poster_time
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:message_list})
			AND id_topic = {int:current_topic}' . (!$allowed_all ? '
			AND id_member = {int:current_member}' : '') . '
		LIMIT ' . count($messages),
		array(
			'current_member' => MID,
			'current_topic' => $topic,
			'message_list' => $messages,
		)
	);
	$messages = array();
	while ($row = wesql::fetch_assoc($request))
	{
		if (!$allowed_all && !empty($settings['edit_disable_time']) && $row['poster_time'] + $settings['edit_disable_time'] * 60 < time())
			continue;

		$messages[$row['id_msg']] = array($row['subject'], $row['id_member']);
	}
	wesql::free_result($request);

	// Get the first message in the topic - because you can't delete that!
	$request = wesql::query('
		SELECT id_first_msg, id_last_msg
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($first_message, $last_message) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Delete all the messages we know they can delete. ($messages)
	foreach ($messages as $message => $info)
	{
		// Just skip the first message - if it's not the last.
		if ($message == $first_message && $message != $last_message)
			continue;
		// If the first message is going then don't bother going back to the topic as we're effectively deleting it.
		elseif ($message == $first_message)
			$topicGone = true;

		removeMessage($message);

		// Log this moderation action ;).
		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info[1] != MID))
			logAction('delete', array('topic' => $topic, 'subject' => $info[0], 'member' => $info[1], 'board' => $board));
	}

	redirectexit(!empty($topicGone) ? 'board=' . $board : 'topic=' . $topic . '.' . $_REQUEST['start']);
}

function quickMod_pin($topic_data, $boards_can)
{
	// Need to check the permissions, that the board that each topic is in, is in the list of boards we can sticky a topic - assuming we're not an admin
	if (!in_array(0, $boards_can['pin_topic']))
		foreach ($topic_data as $topic => $this_topic)
			if (!in_array($this_topic['id_board'], $boards_can['pin_topic']))
				unset($topic_data[$topic]);

	if (empty($topic_data))
		return;

	$pinCache = array_keys($topic_data);
	wesql::query('
		UPDATE {db_prefix}topics
		SET is_pinned = CASE WHEN is_pinned = {int:is_pinned} THEN 0 ELSE 1 END
		WHERE id_topic IN ({array_int:pinned_topic_ids})',
		array(
			'pinned_topic_ids' => $pinCache,
			'is_pinned' => 1,
		)
	);

	// Get the board IDs and pin status, so we can proceed to log things
	$request = wesql::query('
		SELECT id_topic, id_board, is_pinned
		FROM {db_prefix}topics
		WHERE id_topic IN ({array_int:pinned_topic_ids})
		LIMIT ' . count($pinCache),
		array(
			'pinned_topic_ids' => $pinCache,
		)
	);

	while ($row = wesql::fetch_assoc($request))
	{
		logAction(empty($row['is_pinned']) ? 'unpin' : 'pin', array('topic' => $row['id_topic'], 'board' => $row['id_board']));
		sendNotifications($row['id_topic'], 'pin');
	}
	wesql::free_result($request);
}

function quickMod_move($topic_data, $boards_can)
{
	// If they're not an admin: for each post, figure out what the privileges are for the board it is in, and whether they can actually do that.
	if (!in_array(0, $boards_can['move_any']))
	{
		foreach ($topic_data as $topic => $this_topic)
		{
			if (!in_array($this_topic['id_board'], $boards_can['move_any']))
			{
				// So they can't just (un)lock *any* topic. That makes things more complicated. It needs to be their topic and they have to have permission
				if ($this_topic['id_member_started'] != MID || !in_array($this_topic['id_board'], $boards_can['move_own']))
					unset($topic_data[$topic]);
			}
		}
	}

	// Sanitize the destination.
	$_REQUEST['move_to'] = isset($_REQUEST['move_to']) ? (int) $_REQUEST['move_to'] : 0;
	if (empty($_REQUEST['move_to']) || empty($topic_data))
		return;

	// We need to figure out the boards that count posts. And get some other stuff about the topic while we're there.
	$request = wesql::query('
		SELECT t.id_topic, t.id_board, m.id_msg, t.id_member_started, m.subject, b.count_posts
		FROM {db_prefix}topics AS t
			LEFT JOIN {db_prefix}boards AS b ON (t.id_board = b.id_board)
			INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
		WHERE t.id_topic IN ({array_int:move_topic_ids})
		LIMIT ' . count($topic_data),
		array(
			'move_topic_ids' => array_keys($topic_data),
		)
	);

	$boardMoves = array();
	$countPosts = array();
	$moveCache = array();
	$notifications = array();
	$to = $_REQUEST['move_to'];

	while ($row = wesql::fetch_assoc($request))
	{
		// Does this topic's board count the posts or not?
		$countPosts[$row['id_topic']] = empty($row['count_posts']);

		// For reporting...
		$moveCache[] = array($row['id_topic'], $row['id_board'], $_REQUEST['move_to']);
		if ($row['id_member_started'] != 0 && $row['id_member_started'] != MID)
			$notifications[] = $row;
	}
	wesql::free_result($request);

	loadSource('MoveTopic');

	// Do the actual moves...
	foreach ($topic_data as $topic => $this_topic)
		moveTopics($topic, $_REQUEST['move_to']);

	// Does the user post counts need to be updated?
	$topicRecounts = array();
	$request = wesql::query('
		SELECT id_board, count_posts, name
		FROM {db_prefix}boards
		WHERE id_board = {int:move_board}',
		array(
			'move_board' => $_REQUEST['move_to'],
		)
	);

	while ($row = wesql::fetch_assoc($request))
	{
		$cp = empty($row['count_posts']);
		$board_name = $row['name'];

		// Go through all the topics that are being moved to this board.
		foreach ($topic_data as $topic => $this_topic)
		{
			// If both boards have the same value for post counting then no adjustment needs to be made.
			if ($countPosts[$topic] != $cp)
			{
				// If the board being moved to does count the posts then the other one doesn't so add to their post count.
				$topicRecounts[$topic] = $cp ? '+' : '-';
			}
		}
	}
	wesql::free_result($request);

	if (!empty($board_name) && !empty($notifications))
		foreach ($notifications as $notif)
			Notification::issue('move', $notif['id_member_started'], $notif['id_topic'], array('member' => array('name' => we::$user['name'], 'id' => MID), 'id_msg' => $notif['id_msg'], 'subject' => $notif['subject'], 'id_board' => $_REQUEST['move_to'], 'board' => $board_name));

	if (!empty($topicRecounts))
	{
		$members = array();

		// Get all the members who have posted in the moved topics.
		$request = wesql::query('
			SELECT id_member, id_topic
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:moved_topic_ids})',
			array(
				'moved_topic_ids' => array_keys($topicRecounts),
			)
		);

		while ($row = wesql::fetch_assoc($request))
		{
			if (!isset($members[$row['id_member']]))
				$members[$row['id_member']] = 0;

			$members[$row['id_member']] += ($topicRecounts[$row['id_topic']] === '+') ? 1 : -1;
		}

		wesql::free_result($request);

		// And now update them member's post counts
		foreach ($members as $id_member => $post_adj)
			updateMemberData($id_member, array('posts' => 'posts + ' . $post_adj));
	}

	foreach ($moveCache as $topic)
	{
		logAction('move', array('topic' => $topic[0], 'board_from' => $topic[1], 'board_to' => $topic[2]));
		sendNotifications($topic[0], 'move');
	}
}

function quickMod_remove($topic_data, $boards_can)
{
	global $settings;

	// If they're not an admin: for each post, figure out what the privileges are for the board it is in, and whether they can actually do that.
	if (!in_array(0, $boards_can['remove_any']))
	{
		foreach ($topic_data as $topic => $this_topic)
		{
			if (!in_array($this_topic['id_board'], $boards_can['remove_any']))
			{
				// So they can't just (un)lock *any* topic. That makes things more complicated. It needs to be their topic and they have to have permission
				if ($this_topic['id_member_started'] != MID || !in_array($this_topic['id_board'], $boards_can['remove_own']))
					unset($topic_data[$topic]);
			}
		}
	}

	if (empty($topic_data))
		return;

	$recycle_board = !empty($settings['recycle_enable']) ? $settings['recycle_board'] : -1;
	foreach ($topic_data as $topic => $this_topic)
	{
		logAction('remove', array(($recycle_board != $this_topic['id_board'] ? 'topic' : 'old_topic_id') => $topic, 'board' => $this_topic['id_board']));
		sendNotifications($topic, 'remove');
	}
	loadSource('RemoveTopic');
	removeTopics(array_keys($topic_data));

	updateStats('topic');
	updateStats('message');
}

function quickMod_lock($topic_data, $boards_can)
{
	// If they're not an admin: for each post, figure out what the privileges are for the board it is in, and whether they can actually do that.
	if (!in_array(0, $boards_can['lock_any']))
	{
		foreach ($topic_data as $topic => $this_topic)
		{
			if (!in_array($this_topic['id_board'], $boards_can['lock_any']))
			{
				// So they can't just (un)lock *any* topic. That makes things more complicated. It needs to be their topic, not locked by a moderator and they have to have permission
				if ($this_topic['id_member_started'] != MID || $this_topic['locked'] == 1 || !in_array($this_topic['id_board'], $boards_can['lock_own']))
					unset($topic_data[$topic]);
			}
		}
	}

	if (empty($topic_data))
		return;

	// Alternate the locked value.
	wesql::query('
		UPDATE {db_prefix}topics
		SET locked = CASE WHEN locked = {int:is_locked} THEN ' . (!empty($boards_can['lock_any']) ? '1' : '2') . ' ELSE 0 END
		WHERE id_topic IN ({array_int:locked_topic_ids})',
		array(
			'locked_topic_ids' => array_keys($topic_data),
			'is_locked' => 0,
		)
	);

	foreach ($topic_data as $topic => $this_topic)
	{
		logAction($this_topic['locked'] ? 'lock' : 'unlock', array('topic' => $topic, 'board' => $this_topic['id_board']));
		sendNotifications($topic, $this_topic['locked'] ? 'lock' : 'unlock');
	}
}

function quickMod_merge($topic_data, $boards_can)
{
	// Merge requires all topics as one parameter and can be done at once.
	if (count($topic_data) < 2)
		return;

	// We need to do some overriding.
	unset($_POST['topics']);
	$_REQUEST['sa'] = 'internal';

	loadSource('Merge');
	MergeExecute(array_keys($topic_data));
}

function quickMod_approve($topic_data, $boards_can)
{
	// If they're not an admin: for each post, figure out what the privileges are for the board it is in, and whether they can actually do that.
	if (!in_array(0, $boards_can['move_any']))
	{
		foreach ($topic_data as $topic => $this_topic)
		{
			if (!in_array($this_topic['id_board'], $boards_can['move_any']))
			{
				// So they can't just (un)lock *any* topic. That makes things more complicated. It needs to be their topic and they have to have permission
				if ($this_topic['id_member_started'] != MID || !in_array($this_topic['id_board'], $boards_can['move_own']))
					unset($topic_data[$topic]);
			}
		}
	}

	// Anything already approved, we should get rid of.
	foreach ($topic_data as $topic => $this_topic)
		if (!empty($this_topic['approved']))
			unset($topic_data[$topic]);

	if (empty($topic_data))
		return;

	approveTopics(array_keys($topic_data));
	foreach ($topic_data as $topic => $this_topic)
		logAction('approve_topic', array('topic' => $topic, 'member' => $this_topic['id_member_stated']));

	updateStats('topic');
	updateStats('message');
}

function quickMod_markread($topic_data, $boards_can)
{
	global $settings;

	$markArray = array();
	foreach ($topic_data as $topic => $data)
		$markArray[] = array($settings['maxMsgID'], MID, $topic);

	wesql::insert('replace',
		'{db_prefix}log_topics',
		array('id_msg' => 'int', 'id_member' => 'int', 'id_topic' => 'int'),
		$markArray
	);
}
