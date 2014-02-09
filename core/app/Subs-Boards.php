<?php
/**
 * Provides support for minor board-related operations, such as marking them read.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file is mainly concerned with minor tasks relating to boards, such as
	marking them read, collapsing categories, or quick moderation.  It defines
	the following list of functions:

	void markBoardsRead(array boards)
		// !!!

	void MarkRead()
		// !!!

	int getMsgMemberID(int id_msg)
		// !!!

	void modifyBoard(int board_id, array boardOptions)
		- general function to modify the settings and position of a board.
		- used by ManageBoards.php to change the settings of a board.

	int createBoard(array boardOptions)
		- general function to create a new board and set its position.
		- allows (almost) the same options as the modifyBoard() function.
		- with the option inherit_permissions set, the parent board permissions
		  will be inherited.
		- returns the ID of the newly created board.

	void deleteBoards(array boards_to_remove, moveChildrenTo = null)
		- general function to delete one or more boards.
		- allows to move the children of the board before deleting it
		- if moveChildrenTo is set to null, the child boards will be deleted.
		- deletes all topics that are on the given boards.
		- deletes all information that's associated with the given boards.
		- updates the statistics to reflect the new situation.

	void reorderBoards()
		- updates the database to put all boards in the right order.
		- sorts the records of the boards table.
		- used by modifyBoard(), deleteBoards(), modifyCategory(), and
		  deleteCategories() functions.

	void fixChildren(int parent, int newLevel, int newParent)
		- recursively updates the children of parent's child_level and
		  id_parent to newLevel and newParent.
		- used when a board is deleted or moved, to affect its children.

	bool isChildOf(int child, int parent)
		- determines if child is a child of parent.
		- recurses down the tree until there are no more parents.
		- returns true if child is a child of parent.

	void getBoardTree()
		- load information regarding the boards and categories.
		- the information retrieved is stored in globals:
			- $boards		properties of each board.
			- $boardList	a list of boards grouped by category ID.
			- $cat_tree		properties of each category.

	void recursiveBoards()
		- function used by getBoardTree to recursively get a list of boards.

	bool isChildOf(int child, int parent)
		- determine if a certain board id is a child of another board.
		- the parent might be several levels higher than the child.
*/

// Mark a board or multiple boards read.
function markBoardsRead($boards, $unread = false)
{
	global $settings;

	// Force $boards to be an array.
	if (!is_array($boards))
		$boards = array($boards);
	else
		$boards = array_unique($boards);

	// No boards, nothing to mark as read.
	if (empty($boards))
		return;

	// Allow the user to mark a board as unread.
	if ($unread)
	{
		// Clear out all the places where this lovely info is stored.
		// !! Maybe not log_mark_read?
		wesql::query('
			DELETE FROM {db_prefix}log_mark_read
			WHERE id_board IN ({array_int:board_list})
				AND id_member = {int:current_member}',
			array(
				'current_member' => MID,
				'board_list' => $boards,
			)
		);
		wesql::query('
			DELETE FROM {db_prefix}log_boards
			WHERE id_board IN ({array_int:board_list})
				AND id_member = {int:current_member}',
			array(
				'current_member' => MID,
				'board_list' => $boards,
			)
		);
	}
	// Otherwise mark the board as read.
	else
	{
		$markRead = array();
		foreach ($boards as $board)
			$markRead[] = array($settings['maxMsgID'], MID, $board);

		// Update log_mark_read and log_boards.
		wesql::insert('replace',
			'{db_prefix}log_mark_read',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
			$markRead
		);

		wesql::insert('replace',
			'{db_prefix}log_boards',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
			$markRead
		);
	}

	// Get rid of useless log_topics data, because log_mark_read is better for it - even if marking unread - I think so...
	$result = wesql::query('
		SELECT MIN(id_topic)
		FROM {db_prefix}log_topics
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => MID,
		)
	);
	list ($lowest_topic) = wesql::fetch_row($result);
	wesql::free_result($result);

	if (empty($lowest_topic))
		return;

	// !!! SLOW This query seems to eat it sometimes.
	$result = wesql::query('
		SELECT lt.id_topic
		FROM {db_prefix}log_topics AS lt
			INNER JOIN {db_prefix}topics AS t /*!40000 USE INDEX (PRIMARY) */ ON (t.id_topic = lt.id_topic
				AND t.id_board IN ({array_int:board_list}))
		WHERE lt.id_member = {int:current_member}
			AND lt.id_topic >= {int:lowest_topic}',
		array(
			'current_member' => MID,
			'board_list' => $boards,
			'lowest_topic' => $lowest_topic,
		)
	);
	$topics = array();
	while ($row = wesql::fetch_assoc($result))
		$topics[] = $row['id_topic'];
	wesql::free_result($result);

	if (!empty($topics))
		wesql::query('
			DELETE FROM {db_prefix}log_topics
			WHERE id_member = {int:current_member}
				AND id_topic IN ({array_int:topic_list})',
			array(
				'current_member' => MID,
				'topic_list' => $topics,
			)
		);
}

// Mark one or more boards as read.
function MarkRead()
{
	global $board, $topic, $board_info, $settings;

	// No Guests allowed!
	is_not_guest();

	checkSession('get');

	$sa = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : '';
	if ($sa == 'all')
	{
		// Find all the boards this user can see.
		$result = wesql::query('
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}',
			array(
			)
		);
		$boards = array();
		while ($row = wesql::fetch_assoc($result))
			$boards[] = $row['id_board'];
		wesql::free_result($result);

		if (!empty($boards))
			markBoardsRead($boards, isset($_REQUEST['unread']));

		$_SESSION['id_msg_last_visit'] = $settings['maxMsgID'];
		if (!empty($_SESSION['old_url']) && strpos($_SESSION['old_url'], 'action=unread') !== false)
			redirectexit('action=unread');

		if (isset($_SESSION['seen_cache']))
			$_SESSION['seen_cache'] = array();

		redirectexit();
	}
	elseif ($sa == 'unreadreplies')
	{
		// Make sure all the topics are integers!
		$topics = explode('-', $_REQUEST['topics']);

		$markRead = array();
		foreach ($topics as $id_topic)
			$markRead[] = array($settings['maxMsgID'], MID, (int) $id_topic);

		wesql::insert('replace',
			'{db_prefix}log_topics',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_topic' => 'int'),
			$markRead
		);

		if (isset($_SESSION['seen_cache']))
			$_SESSION['seen_cache'] = array();

		redirectexit('action=unreadreplies');
	}

	// Special case: mark a topic unread!
	elseif ($sa == 'topic')
	{
		// First, let's figure out what the latest message is.
		$result = wesql::query('
			SELECT id_first_msg, id_last_msg
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}',
			array(
				'current_topic' => $topic,
			)
		);
		$topicinfo = wesql::fetch_assoc($result);
		wesql::free_result($result);

		// Default behavior: Wedge always provides a 't' variable.
		if (!empty($_GET['t']))
		{
			// Ensure for realistic boundaries.
			if ($_GET['t'] <= $topicinfo['id_first_msg'])
				$earlyMsg = 0;
			elseif ($_GET['t'] >= $topicinfo['id_last_msg'])
				$earlyMsg = $settings['maxMsgID'];
			// If it's anywhere inside the topic, get the latest post before the named one.
			else
			{
				$result = wesql::query('
					SELECT MAX(id_msg)
					FROM {db_prefix}messages
					WHERE id_topic = {int:current_topic}
						AND id_msg >= {int:id_first_msg}
						AND id_msg < {int:topic_msg_id}',
					array(
						'current_topic' => $topic,
						'id_first_msg' => $topicinfo['id_first_msg'],
						'topic_msg_id' => (int) $_GET['t'],
					)
				);
				list ($earlyMsg) = wesql::fetch_row($result);
				wesql::free_result($result);
			}
		}
		// Marking read from first page? That's the whole topic.
		elseif (empty($_REQUEST['start']))
			$earlyMsg = 0;
		else
		{
			$result = wesql::query('
				SELECT id_msg
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
				ORDER BY id_msg
				LIMIT ' . (int) $_REQUEST['start'] . ', 1',
				array(
					'current_topic' => $topic,
				)
			);
			list ($earlyMsg) = wesql::fetch_row($result);
			wesql::free_result($result);

			$earlyMsg--;
		}

		// Blam, unread!
		wesql::insert('replace',
			'{db_prefix}log_topics',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_topic' => 'int'),
			array($earlyMsg, MID, $topic)
		);

		if (AJAX)
			exit;

		redirectexit('board=' . $board . '.0');
	}
	else
	{
		$categories = array();
		$boards = array();

		if (isset($_REQUEST['c']))
		{
			$_REQUEST['c'] = explode(',', $_REQUEST['c']);
			foreach ($_REQUEST['c'] as $c)
				$categories[] = (int) $c;
		}
		if (isset($_REQUEST['boards']))
		{
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
			foreach ($_REQUEST['boards'] as $b)
				$boards[] = (int) $b;
		}
		if (!empty($board))
			$boards[] = (int) $board;

		if (isset($_REQUEST['children']) && !empty($boards))
		{
			// They want to mark the entire tree starting with the boards specified
			// The easist thing is to just get all the boards they can see, but since we've specified the top of tree we ignore some of them

			$request = wesql::query('
				SELECT b.id_board, b.id_parent
				FROM {db_prefix}boards AS b
				WHERE {query_see_board}
					AND b.child_level > {int:no_parents}
					AND b.id_board NOT IN ({array_int:board_list})
				ORDER BY child_level ASC
				',
				array(
					'no_parents' => 0,
					'board_list' => $boards,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				if (in_array($row['id_parent'], $boards))
					$boards[] = $row['id_board'];
			wesql::free_result($request);
		}

		$clauses = array();
		$clauseParameters = array();
		if (!empty($categories))
		{
			$clauses[] = 'id_cat IN ({array_int:category_list})';
			$clauseParameters['category_list'] = $categories;
		}
		if (!empty($boards))
		{
			$clauses[] = 'id_board IN ({array_int:board_list})';
			$clauseParameters['board_list'] = $boards;
		}

		if (empty($clauses))
			redirectexit();

		$request = wesql::query('
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.' . implode(' OR b.', $clauses),
			array_merge($clauseParameters, array(
			))
		);
		$boards = array();
		while ($row = wesql::fetch_assoc($request))
			$boards[] = $row['id_board'];
		wesql::free_result($request);

		if (empty($boards))
			redirectexit();

		markBoardsRead($boards, isset($_REQUEST['unread']));

		foreach ($boards as $b)
			if (isset($_SESSION['seen_cache'][$b]))
				$_SESSION['seen_cache'][$b] = array();

		if (isset($_REQUEST['unread']))
			redirectexit(empty($board_info['parent']) ? '' : 'board=' . $board_info['parent'] . '.0');

		// Find all the boards this user can see.
		$result = wesql::query('
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE b.id_parent IN ({array_int:parent_list})
				AND {query_see_board}',
			array(
				'parent_list' => $boards,
			)
		);
		if (wesql::num_rows($result) > 0)
		{
			$logBoardInserts = '';
			while ($row = wesql::fetch_assoc($result))
				$logBoardInserts[] = array($settings['maxMsgID'], MID, $row['id_board']);

			wesql::insert('replace',
				'{db_prefix}log_boards',
				array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
				$logBoardInserts
			);
		}
		wesql::free_result($result);

		redirectexit(empty($board) ? '' : 'board=' . $board . '.0');
	}
}

// Get the id_member associated with the specified message.
function getMsgMemberID($messageID)
{
	// Find the topic and make sure the member still exists.
	$result = wesql::query('
		SELECT IFNULL(mem.id_member, 0)
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg = {int:selected_message}
		LIMIT 1',
		array(
			'selected_message' => (int) $messageID,
		)
	);
	if (wesql::num_rows($result) > 0)
		list ($memberID) = wesql::fetch_row($result);
	// The message doesn't even exist.
	else
		$memberID = 0;
	wesql::free_result($result);

	return (int) $memberID;
}

// Modify the settings and position of a board.
function modifyBoard($board_id, &$boardOptions)
{
	global $cat_tree, $boards, $context;

	// Get some basic information about all boards and categories.
	getBoardTree();

	// Make sure given boards and categories exist.
	if (!isset($boards[$board_id]) || (isset($boardOptions['target_board']) && !isset($boards[$boardOptions['target_board']])) || (isset($boardOptions['target_category']) && !isset($cat_tree[$boardOptions['target_category']])))
		fatal_lang_error('no_board');

	// All things that will be updated in the database will be in $boardUpdates.
	$boardUpdates = array();
	$boardUpdateParameters = array();

	// In case the board has to be moved
	if (isset($boardOptions['move_to']))
	{
		// Move the board to the top of a given category.
		if ($boardOptions['move_to'] == 'top')
		{
			$id_cat = $boardOptions['target_category'];
			$child_level = 0;
			$id_parent = 0;
			$after = $cat_tree[$id_cat]['last_board_order'];
		}

		// Move the board to the bottom of a given category.
		elseif ($boardOptions['move_to'] == 'bottom')
		{
			$id_cat = $boardOptions['target_category'];
			$child_level = 0;
			$id_parent = 0;
			$after = 0;
			foreach ($cat_tree[$id_cat]['children'] as $id_board => $dummy)
				$after = max($after, $boards[$id_board]['order']);
		}

		// Make the board a child of a given board.
		elseif ($boardOptions['move_to'] == 'child')
		{
			$id_cat = $boards[$boardOptions['target_board']]['category'];
			$child_level = $boards[$boardOptions['target_board']]['level'] + 1;
			$id_parent = $boardOptions['target_board'];

			// People can be creative, in many ways...
			if (isChildOf($id_parent, $board_id))
				fatal_lang_error('mboards_parent_own_child_error', false);
			elseif ($id_parent == $board_id)
				fatal_lang_error('mboards_board_own_child_error', false);

			$after = $boards[$boardOptions['target_board']]['order'];

			// Check if there are already children and (if so) get the max board order.
			if (!empty($boards[$id_parent]['tree']['children']) && empty($boardOptions['move_first_child']))
				foreach ($boards[$id_parent]['tree']['children'] as $childBoard_id => $dummy)
					$after = max($after, $boards[$childBoard_id]['order']);
		}

		// Place a board before or after another board, on the same child level.
		elseif (in_array($boardOptions['move_to'], array('before', 'after')))
		{
			$id_cat = $boards[$boardOptions['target_board']]['category'];
			$child_level = $boards[$boardOptions['target_board']]['level'];
			$id_parent = $boards[$boardOptions['target_board']]['parent'];
			$after = $boards[$boardOptions['target_board']]['order'] - ($boardOptions['move_to'] == 'before' ? 1 : 0);
		}

		// Oops...?
		else
			trigger_error('modifyBoard(): The move_to value \'' . $boardOptions['move_to'] . '\' is incorrect', E_USER_ERROR);

		// Get a list of children of this board.
		$childList = array();
		recursiveBoards($childList, $boards[$board_id]['tree']);

		// See if there are changes that affect children.
		$childUpdates = array();
		$levelDiff = $child_level - $boards[$board_id]['level'];
		if ($levelDiff != 0)
			$childUpdates[] = 'child_level = child_level ' . ($levelDiff > 0 ? '+ ' : '') . '{int:level_diff}';
		if ($id_cat != $boards[$board_id]['category'])
			$childUpdates[] = 'id_cat = {int:category}';

		// Fix the children of this board.
		if (!empty($childList) && !empty($childUpdates))
			wesql::query('
				UPDATE {db_prefix}boards
				SET ' . implode(',
					', $childUpdates) . '
				WHERE id_board IN ({array_int:board_list})',
				array(
					'board_list' => $childList,
					'category' => $id_cat,
					'level_diff' => $levelDiff,
				)
			);

		// Make some room for this spot.
		wesql::query('
			UPDATE {db_prefix}boards
			SET board_order = board_order + {int:new_order}
			WHERE board_order > {int:insert_after}
				AND id_board != {int:selected_board}',
			array(
				'insert_after' => $after,
				'selected_board' => $board_id,
				'new_order' => 1 + count($childList),
			)
		);

		$boardUpdates[] = 'id_cat = {int:id_cat}';
		$boardUpdates[] = 'id_parent = {int:id_parent}';
		$boardUpdates[] = 'child_level = {int:child_level}';
		$boardUpdates[] = 'board_order = {int:board_order}';
		$boardUpdateParameters += array(
			'id_cat' => $id_cat,
			'id_parent' => $id_parent,
			'child_level' => $child_level,
			'board_order' => $after + 1,
		);
	}

	// This setting is a little twisted in the database...
	if (isset($boardOptions['posts_count']))
	{
		$boardUpdates[] = 'count_posts = {int:count_posts}';
		$boardUpdateParameters['count_posts'] = $boardOptions['posts_count'] ? 0 : 1;
	}

	// Set the skin for this board.
	if (isset($boardOptions['board_skin']))
	{
		$boardUpdates[] = 'skin = {string:skin}';
		$boardUpdateParameters['skin'] = $boardOptions['board_skin'];
	}

	// And the mobile version.
	if (isset($boardOptions['board_skin_mobile']))
	{
		$boardUpdates[] = 'skin_mobile = {string:skin_mobile}';
		$boardUpdateParameters['skin_mobile'] = $boardOptions['board_skin_mobile'];
	}

	// Should the board skin override the user's preferred skin?
	if (isset($boardOptions['override_skin']))
	{
		$boardUpdates[] = 'override_skin = {int:override_skin}';
		$boardUpdateParameters['override_skin'] = $boardOptions['override_skin'] ? 1 : 0;
	}

	if (isset($boardOptions['board_name']))
	{
		$boardUpdates[] = 'name = {string:board_name}';
		$boardUpdateParameters['board_name'] = $boardOptions['board_name'];
	}

	if (isset($boardOptions['board_description']))
	{
		$boardUpdates[] = 'description = {string:board_description}';
		$boardUpdateParameters['board_description'] = $boardOptions['board_description'];
	}

	if (isset($boardOptions['board_type']))
	{
		$boardUpdates[] = 'board_type = {string:board_type}';
		$boardUpdateParameters['board_type'] = $boardOptions['board_type'];
	}

	if (isset($boardOptions['profile']))
	{
		$boardUpdates[] = 'id_profile = {int:profile}';
		$boardUpdateParameters['profile'] = (int) $boardOptions['profile'];
	}

	if (isset($boardOptions['redirect']))
	{
		$boardUpdates[] = 'redirect = {string:redirect}';
		$boardUpdateParameters['redirect'] = $boardOptions['redirect'];
	}

	if (isset($boardOptions['redirect_newtab']))
	{
		$boardUpdates[] = 'redirect_newtab = {int:redirect_newtab}';
		$boardUpdateParameters['redirect_newtab'] = (int) $boardOptions['redirect_newtab'];
	}

	if (isset($boardOptions['num_posts']))
	{
		$boardUpdates[] = 'num_posts = {int:num_posts}';
		$boardUpdateParameters['num_posts'] = (int) $boardOptions['num_posts'];
	}

	if (isset($boardOptions['language']))
	{
		$boardUpdates[] = 'language = {string:language}';
		$boardUpdateParameters['language'] = $boardOptions['language'];
	}

	if (isset($boardOptions['offlimits_msg']))
	{
		$boardUpdates[] = 'offlimits_msg = {string:offlimits_msg}';
		$boardUpdateParameters['offlimits_msg'] = $boardOptions['offlimits_msg'];
	}

	// Do the updates (if any).
	if (!empty($boardUpdates))
		wesql::query('
			UPDATE {db_prefix}boards
			SET
				' . implode(',
				', $boardUpdates) . '
			WHERE id_board = {int:selected_board}',
			array_merge(
				$boardUpdateParameters,
				array(
					'selected_board' => $board_id
				)
			)
		);

	// Who's allowed to access this board.
	if (isset($boardOptions['access']))
	{
		// Remove all the old ones.
		wesql::query('
			DELETE FROM {db_prefix}board_groups
			WHERE id_board = {int:board}',
			array(
				'board' => $board_id,
			)
		);
		$rows = array();
		foreach ($boardOptions['access'] as $id_group => $row)
		{
			// Skip empty rows
			if ($row['view_perm'] == 'disallow' && $row['enter_perm'] == 'disallow')
				continue;
			$rows[] = array($board_id, $id_group, $row['view_perm'], $row['enter_perm']);
		}

		wesql::insert('',
			'{db_prefix}board_groups',
			array('id_board' => 'int', 'id_group' => 'int', 'view_perm' => 'string', 'enter_perm' => 'string'),
			$rows
		);
	}

	// Set moderators of this board.
	if (isset($boardOptions['moderators']) || isset($boardOptions['moderator_string']))
	{
		// Reset current moderators for this board - if there are any!
		wesql::query('
			DELETE FROM {db_prefix}moderators
			WHERE id_board = {int:board_list}',
			array(
				'board_list' => $board_id,
			)
		);

		// Validate and get the IDs of the new moderators.
		if (isset($boardOptions['moderator_string']) && trim($boardOptions['moderator_string']) != '')
		{
			// Divvy out the usernames, remove extra space.
			$moderator_string = strtr(westr::htmlspecialchars($boardOptions['moderator_string'], ENT_QUOTES), array('&quot;' => '"'));
			preg_match_all('~"([^"]+)"~', $moderator_string, $matches);
			$moderators = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_string)));
			for ($k = 0, $n = count($moderators); $k < $n; $k++)
			{
				$moderators[$k] = trim($moderators[$k]);

				if (strlen($moderators[$k]) == 0)
					unset($moderators[$k]);
			}

			// Find all the id_member's for the member_name's in the list.
			if (empty($boardOptions['moderators']))
				$boardOptions['moderators'] = array();
			if (!empty($moderators))
			{
				$request = wesql::query('
					SELECT id_member
					FROM {db_prefix}members
					WHERE member_name IN ({array_string:moderator_list}) OR real_name IN ({array_string:moderator_list})
					LIMIT ' . count($moderators),
					array(
						'moderator_list' => $moderators,
					)
				);
				while ($row = wesql::fetch_assoc($request))
					$boardOptions['moderators'][] = $row['id_member'];
				wesql::free_result($request);
			}
		}

		// Add the moderators to the board.
		if (!empty($boardOptions['moderators']))
		{
			$inserts = array();
			foreach ($boardOptions['moderators'] as $moderator)
				$inserts[] = array($board_id, $moderator);

			wesql::insert('',
				'{db_prefix}moderators',
				array('id_board' => 'int', 'id_member' => 'int'),
				$inserts
			);
		}

		// Note that caches can now be wrong!
		updateSettings(array('settings_updated' => time()));
	}

	if (isset($boardOptions['move_to']))
		reorderBoards();

	clean_cache();

	// Update the pretty board URLs
	if (isset($boardOptions['pretty_url']) || isset($boardOptions['pretty_url_dom']))
	{
		$dom = isset($boardOptions['pretty_url_dom']) ? strtolower($boardOptions['pretty_url_dom']) : '';
		$purl = isset($boardOptions['pretty_url']) ? strtolower($boardOptions['pretty_url']) : '';

		// Get ex-name...
		$result = wesql::query('
			SELECT url, id_owner
			FROM {db_prefix}boards
			WHERE id_board = {int:id_board}', array(
				'id_board' => $board_id
			));

		list ($ex_name, $id_owner) = wesql::fetch_row($result);
		wesql::free_result($result);

		loadSource('Subs-PrettyUrls');

		// Everything was empty? Generate a default name
		if (empty($purl))
			$purl = $boardOptions['board_name'];

		// Generate a new one. If it's empty or a number, use b1234 (where id_board = 1234) as the new name.
		$pretty_url = pretty_generate_url($purl, true, true);
		$new_name = $dom . '/' . ($pretty_url !== '' && !is_numeric($pretty_url) ? $pretty_url : 'b' . $board_id);

		// Can't be already in use
		$in_use = is_already_taken($new_name, $board_id, $id_owner);
		if ($in_use !== false && $in_use != $board_id)
			fatal_lang_error('pretty_duplicateboard', false);

		// Save to the database
		wesql::query('
			UPDATE {db_prefix}boards
			SET url = {string:url}, urllen = {int:len}
			WHERE id_board = {int:id_board}', array(
				'url' => $new_name,
				'id_board' => $board_id,
				'len' => strlen($new_name)
			));

		// Mass-replace board name in cache
		if (!empty($ex_name) && !preg_match('~^dummy[0-9]+$~', $ex_name))
			wesql::query('
				DELETE FROM {db_prefix}pretty_urls_cache
				WHERE replacement LIKE \'http://' . $ex_name . '%\'');

		// Count that query!
		$context['pretty']['db_count']++;
	}

	if (empty($boardOptions['dont_log']))
		logAction('edit_board', array('board' => $board_id), 'admin');
}

// Create a new board and set its properties and position.
function createBoard($boardOptions)
{
	global $boards;

	// Trigger an error if one of the required values is not set.
	if (!isset($boardOptions['board_name']) || trim($boardOptions['board_name']) == '' || !isset($boardOptions['move_to']) || !isset($boardOptions['target_category']))
		trigger_error('createBoard(): One or more of the required options is not set', E_USER_ERROR);

	if (in_array($boardOptions['move_to'], array('child', 'before', 'after')) && !isset($boardOptions['target_board']))
		trigger_error('createBoard(): Target board is not set', E_USER_ERROR);

	// Set every optional value to its default value.
	$boardOptions += array(
		'posts_count' => true,
		'override_skin' => false,
		'board_skin' => '',
		'board_skin_mobile' => '',
		'board_description' => '',
		'board_type' => 'forum',
		'profile' => 1,
		'moderators' => '',
		'inherit_permissions' => true,
		'dont_log' => true,
	);

	// Insert a board, the settings are dealt with later.
	wesql::insert('',
		'{db_prefix}boards',
		array(
			'id_cat' => 'int', 'name' => 'string-255', 'description' => 'string', 'board_order' => 'int',
			'member_groups' => 'string', 'redirect' => 'string', 'url' => 'string', 'urllen' => 'int', 'offlimits_msg' => 'string',
		),
		array(
			$boardOptions['target_category'], $boardOptions['board_name'], '', 0,
			'-1,0', '', 'dummy' . rand(100000, 999999), 11, '',
		)
	);
	$board_id = wesql::insert_id();

	if (empty($board_id))
		return 0;

	if (empty($boardOptions['pretty_url']))
		$boardOptions['pretty_url'] = $boardOptions['board_name'];

	// Change the board according to the given specifications.
	modifyBoard($board_id, $boardOptions);

	// Do we want the parent permissions to be inherited?
	if ($boardOptions['inherit_permissions'])
	{
		getBoardTree();

		if (!empty($boards[$board_id]['parent']))
		{
			$request = wesql::query('
				SELECT id_profile
				FROM {db_prefix}boards
				WHERE id_board = {int:board_parent}
				LIMIT 1',
				array(
					'board_parent' => (int) $boards[$board_id]['parent'],
				)
			);
			list ($boardOptions['profile']) = wesql::fetch_row($request);
			wesql::free_result($request);

			wesql::query('
				UPDATE {db_prefix}boards
				SET id_profile = {int:new_profile}
				WHERE id_board = {int:current_board}',
				array(
					'new_profile' => $boardOptions['profile'],
					'current_board' => $board_id,
				)
			);
		}
	}

	clean_cache();

	// Created it.
	logAction('add_board', array('board' => $board_id), 'admin');

	// Here you are, a new board, ready to be spammed.
	return $board_id;
}

// Remove one or more boards.
function deleteBoards($boards_to_remove, $moveChildrenTo = null)
{
	global $boards;

	// No boards to delete? Return!
	if (empty($boards_to_remove))
		return;

	getBoardTree();

	// If $moveChildrenTo is set to null, include the children in the removal.
	if ($moveChildrenTo === null)
	{
		// Get a list of the child boards that will also be removed.
		$child_boards_to_remove = array();
		foreach ($boards_to_remove as $board_to_remove)
			recursiveBoards($child_boards_to_remove, $boards[$board_to_remove]['tree']);

		// Merge the children with their parents.
		if (!empty($child_boards_to_remove))
			$boards_to_remove = array_unique(array_merge($boards_to_remove, $child_boards_to_remove));
	}
	// Move the children to a safe home.
	else
	{
		foreach ($boards_to_remove as $id_board)
		{
			// !!! Separate category?
			if ($moveChildrenTo === 0)
				fixChildren($id_board, 0, 0);
			else
				fixChildren($id_board, $boards[$moveChildrenTo]['level'] + 1, $moveChildrenTo);
		}
	}

	// Delete ALL topics in the selected boards (done first so topics can't be marooned.)
	$request = wesql::query('
		SELECT id_topic
		FROM {db_prefix}topics
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);
	$topics = array();
	while ($row = wesql::fetch_assoc($request))
		$topics[] = $row['id_topic'];
	wesql::free_result($request);

	loadSource('RemoveTopic');
	removeTopics($topics, false);

	// Delete the board's logs.
	wesql::query('
		DELETE FROM {db_prefix}log_mark_read
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);
	wesql::query('
		DELETE FROM {db_prefix}log_boards
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);
	wesql::query('
		DELETE FROM {db_prefix}log_notify
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete this board's moderators.
	wesql::query('
		DELETE FROM {db_prefix}moderators
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete any message icons that only appear on these boards.
	wesql::query('
		DELETE FROM {db_prefix}message_icons
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	// Delete the boards.
	wesql::query('
		DELETE FROM {db_prefix}boards
		WHERE id_board IN ({array_int:boards_to_remove})',
		array(
			'boards_to_remove' => $boards_to_remove,
		)
	);

	call_hook('remove_boards', array(&$boards_to_remove));

	// Latest message/topic might not be there anymore.
	updateStats('message');
	updateStats('topic');

	// Plus reset the cache to stop people getting odd results.
	updateSettings(array('settings_updated' => time()));

	clean_cache();

	// Let's do some serious logging.
	foreach ($boards_to_remove as $id_board)
		logAction('delete_board', array('boardname' => $boards[$id_board]['name']), 'admin');

	reorderBoards();
}

// Put all boards in the right order.
function reorderBoards()
{
	global $cat_tree, $boardList, $boards;

	getBoardTree();

	// Set the board order for each category.
	$board_order = 0;
	foreach ($cat_tree as $catID => $dummy)
	{
		foreach ($boardList[$catID] as $boardID)
			if ($boards[$boardID]['order'] != ++$board_order)
				wesql::query('
					UPDATE {db_prefix}boards
					SET board_order = {int:new_order}
					WHERE id_board = {int:selected_board}',
					array(
						'new_order' => $board_order,
						'selected_board' => $boardID,
					)
				);
	}

	// Sort the records of the boards table on the board_order value.
	wesql::query('
		ALTER TABLE {db_prefix}boards
		ORDER BY board_order',
		array(
			'db_error_skip' => true,
		)
	);
}

// Fixes the children of a board by setting their child_levels to new values.
function fixChildren($parent, $newLevel, $newParent)
{
	// Grab all children of $parent...
	$result = wesql::query('
		SELECT id_board
		FROM {db_prefix}boards
		WHERE id_parent = {int:parent_board}',
		array(
			'parent_board' => $parent,
		)
	);
	$children = array();
	while ($row = wesql::fetch_assoc($result))
		$children[] = $row['id_board'];
	wesql::free_result($result);

	// ...and set it to a new parent and child_level.
	wesql::query('
		UPDATE {db_prefix}boards
		SET id_parent = {int:new_parent}, child_level = {int:new_child_level}
		WHERE id_parent = {int:parent_board}',
		array(
			'new_parent' => $newParent,
			'new_child_level' => $newLevel,
			'parent_board' => $parent,
		)
	);

	// Recursively fix the children of the children.
	foreach ($children as $child)
		fixChildren($child, $newLevel + 1, $child);
}

// Load a lot of useful information regarding the boards and categories.
// Restrict to their own boards anyone who's not an admin.
function getBoardTree($restrict = false)
{
	global $cat_tree, $boards, $boardList;

	$restriction = we::$is_admin || !$restrict ? '' : '
				AND b.id_owner = ' . (int) MID;

	// Getting all the board and category information you'd ever wanted.
	$request = wesql::query('
		SELECT
			IFNULL(b.id_board, 0) AS id_board, b.id_parent, b.name AS board_name, b.board_type, b.description, b.child_level,
			b.url, b.board_order, b.count_posts, b.member_groups, b.skin, b.skin_mobile, b.override_skin, b.id_profile, b.redirect,
			b.redirect_newtab, b.num_posts, b.language, b.num_topics, b.offlimits_msg, c.id_cat, c.name AS cat_name, c.cat_order, c.can_collapse
		FROM {db_prefix}categories AS c
			LEFT JOIN {db_prefix}boards AS b ON (b.id_cat = c.id_cat)' . $restriction . '
		ORDER BY c.cat_order, b.child_level, b.board_order',
		array()
	);
	$cat_tree = array();
	$boards = array();
	$last_board_order = 0;
	while ($row = wesql::fetch_assoc($request))
	{
		if (!isset($cat_tree[$row['id_cat']]))
		{
			$cat_tree[$row['id_cat']] = array(
				'node' => array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'order' => $row['cat_order'],
					'can_collapse' => $row['can_collapse']
				),
				'is_first' => empty($cat_tree),
				'last_board_order' => $last_board_order,
				'children' => array()
			);
			$prevBoard = 0;
			$curLevel = 0;
		}

		if (!empty($row['id_board']))
		{
			if ($row['child_level'] != $curLevel)
				$prevBoard = 0;

			$boards[$row['id_board']] = array(
				'id' => $row['id_board'],
				'category' => $row['id_cat'],
				'parent' => $row['id_parent'],
				'level' => $row['child_level'],
				'order' => $row['board_order'],
				'url' => $row['url'],
				'name' => $row['board_name'],
				'type' => $row['board_type'],
				'member_groups' => explode(',', $row['member_groups']),
				'description' => $row['description'],
				'count_posts' => empty($row['count_posts']),
				'posts' => $row['num_posts'],
				'topics' => $row['num_topics'],
				'skin' => $row['skin'],
				'skin_mobile' => $row['skin_mobile'],
				'override_skin' => $row['override_skin'],
				'profile' => $row['id_profile'],
				'redirect' => $row['redirect'],
				'redirect_newtab' => $row['redirect_newtab'],
				'prev_board' => $prevBoard,
				'language' => $row['language'],
				'offlimits_msg' => $row['offlimits_msg'],
			);
			$prevBoard = $row['id_board'];
			$last_board_order = $row['board_order'];

			if (empty($row['child_level']))
			{
				$cat_tree[$row['id_cat']]['children'][$row['id_board']] = array(
					'node' => &$boards[$row['id_board']],
					'is_first' => empty($cat_tree[$row['id_cat']]['children']),
					'children' => array()
				);
				$boards[$row['id_board']]['tree'] =& $cat_tree[$row['id_cat']]['children'][$row['id_board']];
			}
			else
			{
				// Parent doesn't exist!
				if (!isset($boards[$row['id_parent']]['tree']))
					fatal_lang_error('no_valid_parent', false, array($row['board_name']));

				// Wrong child level...
				if ($boards[$row['id_parent']]['tree']['node']['level'] != $row['child_level'] - 1)
					wesql::query('
						UPDATE {db_prefix}boards
						SET child_level = {int:new_child_level}
						WHERE id_board = {int:selected_board}',
						array(
							'new_child_level' => $boards[$row['id_parent']]['tree']['node']['level'] + 1,
							'selected_board' => $row['id_board'],
						)
					);

				$boards[$row['id_parent']]['tree']['children'][$row['id_board']] = array(
					'node' => &$boards[$row['id_board']],
					'is_first' => empty($boards[$row['id_parent']]['tree']['children']),
					'children' => array()
				);
				$boards[$row['id_board']]['tree'] =& $boards[$row['id_parent']]['tree']['children'][$row['id_board']];
			}
		}
	}
	wesql::free_result($request);

	// Get a list of all the boards in each category (using recursion).
	$boardList = array();
	foreach ($cat_tree as $catID => $node)
	{
		$boardList[$catID] = array();
		recursiveBoards($boardList[$catID], $node);
	}
}

// Recursively get a list of boards.
function recursiveBoards(&$_boardList, &$_tree)
{
	if (empty($_tree['children']))
		return;

	foreach ($_tree['children'] as $id => $node)
	{
		$_boardList[] = $id;
		recursiveBoards($_boardList, $node);
	}
}

// Returns whether the child board id is actually a child of the parent (recursive).
function isChildOf($child, $parent)
{
	global $boards;

	if (empty($boards[$child]['parent']))
		return false;

	if ($boards[$child]['parent'] == $parent)
		return true;

	return isChildOf($boards[$child]['parent'], $parent);
}

function getBoardChildren($boards)
{
	if (!is_array($boards))
		$boards = (array) $boards;
	foreach ($boards as $k => $v)
		$boards[$k] = (int) $v;

	return cache_get_data('board_children-' . implode(',', $boards), 480, function () use ($boards)
	{
		$complete_boards = $this_iteration = $boards;
		while (!empty($this_iteration))
		{
			$request = wesql::query('
				SELECT id_board
				FROM {db_prefix}boards
				WHERE id_parent IN ({array_int:boards})',
				array(
					'boards' => $this_iteration,
				)
			);
			$this_iteration = array();
			while ($row = wesql::fetch_row($request))
				$this_iteration[] = $row[0];
			wesql::free_result($request);

			$complete_boards = array_merge($complete_boards, $this_iteration);
		}
		return $complete_boards;
	});
}
