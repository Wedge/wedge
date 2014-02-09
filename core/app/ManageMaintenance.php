<?php
/**
 * Various maintenance-related tasks, including member re-attribution, cleaning the forum cache and so on.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/* /!!!

	void ManageMaintenance()
		// !!!

	void MaintainMembers()
		// !!!

	void MaintainTopics()
		// !!!

	void MaintainCleanCache()
		// !!!

	void MaintainFindFixErrors()
		// !!!

	void MaintainEmptyUnimportantLogs()
		// !!!

	void OptimizeTables()
		- optimizes all tables in the database and lists how much was saved.
		- requires the admin_forum permission.
		- shows as the maintain_forum admin area.
		- updates the optimize scheduled task such that the tables are not
		  automatically optimized again too soon.
		- accessed from ?action=admin;area=maintain;sa=database;activity=optimize.

	void AdminBoardRecount()
		- recounts many forum totals that can be recounted automatically
		  without harm.
		- requires the admin_forum permission.
		- shows the maintain_forum admin area.
		- fixes topics with wrong num_replies.
		- updates the num_posts and num_topics of all boards.
		- recounts instant_messages but not unread_messages.
		- repairs messages pointing to boards with topics pointing to
		  other boards.
		- updates the last message posted in boards and children.
		- updates member count, latest member, topic count, and message count.
		- redirects back to ?action=admin;area=maintain when complete.
		- accessed via ?action=admin;area=maintain;sa=database;activity=recount.

	void VersionDetail()
		- parses the comment headers in all files for their version information
		  and outputs that for some javascript to check with wedge.org.
		- does not connect directly with wedge.org, instead expects the client to.
		- requires the admin_forum permission.
		- uses the view_versions admin area.
		- loads the view_versions block (in the Admin template.)
		- accessed through ?action=admin;area=maintain;sa=routine;activity=version.

	void MaintainReattributePosts()
		// !!!

	void MaintainPurgeInactiveMembers()
		// !!!

	void MaintainRemoveOldPosts(bool do_action = true)
		// !!!

	mixed MaintainMassMoveTopics()
		- Moves topics from one board to another.
		- User the not_done template to pause the process.
*/

// The maintenance access point.
function ManageMaintenance()
{
	global $txt, $context;

	// You absolutely must be an admin by here!
	isAllowedTo('admin_forum');

	// Need something to talk about?
	loadLanguage('ManageMaintenance');
	loadTemplate('ManageMaintenance');

	// This uses admin tabs - as it should!
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['maintain_title'],
		'description' => $txt['maintain_info'],
		'tabs' => array(
			'routine' => array(),
			'database' => array(),
			'members' => array(),
			'topics' => array(),
		),
	);

	// So many things you can do - but frankly I won't let you - just these!
	$subActions = array(
		'routine' => array(
			'function' => 'MaintainRoutine',
			'template' => 'maintain_routine',
			'activities' => array(
				'version' => 'VersionDetail',
				'repair' => 'MaintainFindFixErrors',
				'recount' => 'AdminBoardRecount',
				'optimize' => 'OptimizeTables',
				'logs' => 'MaintainEmptyUnimportantLogs',
				'cleancache' => 'MaintainCleanCache',
			),
		),
		'members' => array(
			'function' => 'MaintainMembers',
			'template' => 'maintain_members',
			'activities' => array(
				'reattribute' => 'MaintainReattributePosts',
				'purgeinactive' => 'MaintainPurgeInactiveMembers',
				'recountposts' => 'MaintainRecountPosts',
			),
		),
		'topics' => array(
			'function' => 'MaintainTopics',
			'template' => 'maintain_topics',
			'activities' => array(
				'massmove' => 'MaintainMassMoveTopics',
				'pruneold' => 'MaintainRemoveOldPosts',
			),
		),
	);

	// Yep, sub-action time!
	if (isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]))
		$subAction = $_REQUEST['sa'];
	else
		$subAction = 'routine';

	// Doing something special?
	if (isset($_REQUEST['activity'], $subActions[$subAction]['activities'][$_REQUEST['activity']]))
		$activity = $_REQUEST['activity'];

	// Set a few things.
	$context['page_title'] = $txt['maintain_title'];
	$context['sub_action'] = $subAction;
	if (!empty($subActions[$subAction]['template']))
		wetem::load($subActions[$subAction]['template']);

	// Finally fall through to what we are doing.
	$subActions[$subAction]['function']();

	// Any special activity?
	if (isset($activity))
		$subActions[$subAction]['activities'][$activity]();
}

// Supporting function for the routine maintenance area.
function MaintainRoutine($return_value = false)
{
	global $context, $txt;

	$context['maintenance_tasks'] = array(
		'maintain_version' => array($txt['maintain_version'], $txt['maintain_version_info'], 'action=admin;area=maintain;sa=routine;activity=version'),
		'maintain_errors' => array($txt['maintain_errors'], $txt['maintain_errors_info'], 'action=admin;area=repairboards'),
		'maintain_recount' => array($txt['maintain_recount'], $txt['maintain_recount_info'], 'action=admin;area=maintain;sa=routine;activity=recount'),
		'maintain_logs' => array($txt['maintain_logs'], $txt['maintain_logs_info'], 'action=admin;area=maintain;sa=routine;activity=logs'),
		'maintain_cache' => array($txt['maintain_cache'], $txt['maintain_cache_info'], 'action=admin;area=maintain;sa=routine;activity=cleancache'),
	);

	call_hook('maintenance_routine', array(&$return_value));

	if ($return_value)
		return $context['maintenance_tasks'];

	if (isset($_GET['done']) && $_GET['done'] == 'recount')
		$context['maintenance_finished'] = $txt['maintain_recount'];
}

// Supporting function for the members maintenance area.
function MaintainMembers()
{
	global $context, $txt;

	// Get membergroups - for deleting members and the like.
	$result = wesql::query('
		SELECT id_group, group_name
		FROM {db_prefix}membergroups',
		array(
		)
	);
	$context['membergroups'] = array(
		array(
			'id' => 0,
			'name' => $txt['maintain_members_ungrouped']
		),
	);
	while ($row = wesql::fetch_assoc($result))
	{
		$context['membergroups'][] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
	}
	wesql::free_result($result);

	if (isset($_GET['done']) && $_GET['done'] == 'recountposts')
		$context['maintenance_finished'] = $txt['maintain_recountposts'];
}

// Supporting function for the topics maintenance area.
function MaintainTopics()
{
	global $context, $txt;

	// Let's load up the boards in case they are useful.
	$result = wesql::query('
		SELECT b.id_board, b.name, b.child_level, c.name AS cat_name, c.id_cat
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:empty}',
		array(
			'empty' => '',
		)
	);
	$context['categories'] = array();
	while ($row = wesql::fetch_assoc($result))
	{
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array(
				'name' => $row['cat_name'],
				'boards' => array()
			);

		$context['categories'][$row['id_cat']]['boards'][] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level']
		);
	}
	wesql::free_result($result);

	if (isset($_GET['done']) && $_GET['done'] == 'purgeold')
		$context['maintenance_finished'] = $txt['maintain_old'];
	elseif (isset($_GET['done']) && $_GET['done'] == 'massmove')
		$context['maintenance_finished'] = $txt['move_topics_maintenance'];
}

// Find and fix all errors.
function MaintainFindFixErrors()
{
	loadSource('RepairBoards');
	RepairBoards();
}

// Wipes the whole cache directory.
function MaintainCleanCache()
{
	global $context, $txt;

	// Just wipe the whole cache directory!
	clean_cache();
	clean_cache('js');
	clean_cache('css');

	$context['maintenance_finished'] = $txt['maintain_cache'];
}

// Empties all uninmportant logs
function MaintainEmptyUnimportantLogs()
{
	global $context, $txt;

	checkSession();

	// No one's online now.... MUHAHAHAHA :P
	// Dump the banning and spam logs.
	wesql::query('TRUNCATE {db_prefix}log_online');
	wesql::query('TRUNCATE {db_prefix}log_floodcontrol');

	// Start id_error back at 0 and dump the error log.
	wesql::query('TRUNCATE {db_prefix}log_errors');

	// Last but not least, the search logs!
	wesql::query('TRUNCATE {db_prefix}log_search_topics');
	wesql::query('TRUNCATE {db_prefix}log_search_messages');
	wesql::query('TRUNCATE {db_prefix}log_search_results');

	updateSettings(array('search_pointer' => 0));

	$context['maintenance_finished'] = $txt['maintain_logs'];
}

// Optimize the database's tables.
function OptimizeTables()
{
	global $db_prefix, $txt, $context;

	isAllowedTo('admin_forum');

	checkSession('post');

	ignore_user_abort(true);
	loadSource('Class-DBPackages');

	// Start with no tables optimized.
	$opttab = 0;

	$context['page_title'] = $txt['database_optimize'];
	wetem::load('optimize');

	// Only optimize the tables related to this Wedge install, not all the tables in the DB...
	$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

	// Get a list of tables, as well as how many there are.
	$temp_tables = wedbPackages::list_tables(false, $real_prefix . '%');
	$tables = array();
	foreach ($temp_tables as $table)
		$tables[] = array('table_name' => $table);

	// We're getting this for the display later. We could, theoretically, test to make sure it's non-zero, but if it's zero, we shouldn't have made it this far anyway (e.g. the settings table, the members table would have been queried)
	$context['num_tables'] = count($tables);

	// For each table....
	$context['optimized_tables'] = array();
	foreach ($tables as $table)
	{
		// Optimize the table! We use backticks here because it might be a custom table.
		$data_freed = wedbPackages::optimize_table($table['table_name']);

		if ($data_freed > 0)
			$context['optimized_tables'][] = array(
				'name' => $table['table_name'],
				'data_freed' => $data_freed,
			);
	}

	// Number of tables, etc....
	$txt['database_numb_tables'] = sprintf($txt['database_numb_tables'], $context['num_tables']);
	$context['num_tables_optimized'] = count($context['optimized_tables']);

	// Check that we don't auto-optimize again too soon!
	loadSource('ScheduledTasks');
	CalculateNextTrigger('auto_optimize', true);
}

// Recount all the important board totals.
function AdminBoardRecount()
{
	global $txt, $context, $settings;
	global $time_start;

	isAllowedTo('admin_forum');

	checkSession('request');

	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '3';
	wetem::load('not_done');

	// Try for as much time as possible.
	@set_time_limit(600);

	// Step the number of topics at a time so things don't time out...
	$request = wesql::query('
		SELECT MAX(id_topic)
		FROM {db_prefix}topics',
		array(
		)
	);
	list ($max_topics) = wesql::fetch_row($request);
	wesql::free_result($request);

	$increment = min(max(50, ceil($max_topics / 4)), 2000);
	if (empty($_REQUEST['start']))
		$_REQUEST['start'] = 0;

	$total_steps = 8;

	// Get each topic with a wrong reply count and fix it - let's just do some at a time, though.
	if (empty($_REQUEST['step']))
	{
		$_REQUEST['step'] = 0;

		while ($_REQUEST['start'] < $max_topics)
		{
			// Recount approved messages
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.num_replies) AS num_replies,
					CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END AS real_num_replies
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS ma ON (ma.id_topic = t.id_topic AND ma.approved = {int:is_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING CASE WHEN COUNT(ma.id_msg) >= 1 THEN COUNT(ma.id_msg) - 1 ELSE 0 END != MAX(t.num_replies)',
				array(
					'is_approved' => 1,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}topics
					SET num_replies = {int:num_replies}
					WHERE id_topic = {int:id_topic}',
					array(
						'num_replies' => $row['real_num_replies'],
						'id_topic' => $row['id_topic'],
					)
				);
			wesql::free_result($request);

			// Recount unapproved messages
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ t.id_topic, MAX(t.unapproved_posts) AS unapproved_posts,
					COUNT(mu.id_msg) AS real_unapproved_posts
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}messages AS mu ON (mu.id_topic = t.id_topic AND mu.approved = {int:not_approved})
				WHERE t.id_topic > {int:start}
					AND t.id_topic <= {int:max_id}
				GROUP BY t.id_topic
				HAVING COUNT(mu.id_msg) != MAX(t.unapproved_posts)',
				array(
					'not_approved' => 0,
					'start' => $_REQUEST['start'],
					'max_id' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}topics
					SET unapproved_posts = {int:unapproved_posts}
					WHERE id_topic = {int:id_topic}',
					array(
						'unapproved_posts' => $row['real_unapproved_posts'],
						'id_topic' => $row['id_topic'],
					)
				);
			wesql::free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=0;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the post count of each board.
	if ($_REQUEST['step'] <= 1)
	{
		if (empty($_REQUEST['start']))
			wesql::query('
				UPDATE {db_prefix}boards
				SET num_posts = {int:num_posts}
				WHERE redirect = {string:empty}',
				array(
					'num_posts' => 0,
					'empty' => '',
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_num_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 1,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}boards
					SET num_posts = num_posts + {int:real_num_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_posts' => $row['real_num_posts'],
					)
				);
			wesql::free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=1;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((200 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the topic count of each board.
	if ($_REQUEST['step'] <= 2)
	{
		if (empty($_REQUEST['start']))
			wesql::query('
				UPDATE {db_prefix}boards
				SET num_topics = {int:num_topics}',
				array(
					'num_topics' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_num_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_approved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_approved' => 1,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}boards
					SET num_topics = num_topics + {int:real_num_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_num_topics' => $row['real_num_topics'],
					)
				);
			wesql::free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=2;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((300 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the unapproved post count of each board.
	if ($_REQUEST['step'] <= 3)
	{
		if (empty($_REQUEST['start']))
			wesql::query('
				UPDATE {db_prefix}boards
				SET unapproved_posts = {int:unapproved_posts}',
				array(
					'unapproved_posts' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ m.id_board, COUNT(*) AS real_unapproved_posts
				FROM {db_prefix}messages AS m
				WHERE m.id_topic > {int:id_topic_min}
					AND m.id_topic <= {int:id_topic_max}
					AND m.approved = {int:is_approved}
				GROUP BY m.id_board',
				array(
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
					'is_approved' => 0,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}boards
					SET unapproved_posts = unapproved_posts + {int:unapproved_posts}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'unapproved_posts' => $row['real_unapproved_posts'],
					)
				);
			wesql::free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=3;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((400 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the unapproved topic count of each board.
	if ($_REQUEST['step'] <= 4)
	{
		if (empty($_REQUEST['start']))
			wesql::query('
				UPDATE {db_prefix}boards
				SET unapproved_topics = {int:unapproved_topics}',
				array(
					'unapproved_topics' => 0,
				)
			);

		while ($_REQUEST['start'] < $max_topics)
		{
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, COUNT(*) AS real_unapproved_topics
				FROM {db_prefix}topics AS t
				WHERE t.approved = {int:is_unapproved}
					AND t.id_topic > {int:id_topic_min}
					AND t.id_topic <= {int:id_topic_max}
				GROUP BY t.id_board',
				array(
					'is_unapproved' => 0,
					'id_topic_min' => $_REQUEST['start'],
					'id_topic_max' => $_REQUEST['start'] + $increment,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}boards
					SET unapproved_topics = unapproved_topics + {int:real_unapproved_topics}
					WHERE id_board = {int:id_board}',
					array(
						'id_board' => $row['id_board'],
						'real_unapproved_topics' => $row['real_unapproved_topics'],
					)
				);
			wesql::free_result($request);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=4;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((500 + 100 * $_REQUEST['start'] / $max_topics) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Get all members with wrong number of personal messages.
	if ($_REQUEST['step'] <= 5)
	{
		$request = wesql::query('
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.instant_messages) AS instant_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.instant_messages)',
			array(
				'is_not_deleted' => 0,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			updateMemberData($row['id_member'], array('instant_messages' => $row['real_num']));
		wesql::free_result($request);

		$request = wesql::query('
			SELECT /*!40001 SQL_NO_CACHE */ mem.id_member, COUNT(pmr.id_pm) AS real_num,
				MAX(mem.unread_messages) AS unread_messages
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = {int:is_not_deleted} AND pmr.is_read = {int:is_not_read})
			GROUP BY mem.id_member
			HAVING COUNT(pmr.id_pm) != MAX(mem.unread_messages)',
			array(
				'is_not_deleted' => 0,
				'is_not_read' => 0,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			updateMemberData($row['id_member'], array('unread_messages' => $row['real_num']));
		wesql::free_result($request);

		if (microtime(true) - $time_start > 3)
		{
			$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=0;' . $context['session_query'];
			$context['continue_percent'] = round(700 / $total_steps);

			return;
		}
	}

	// Any messages pointing to the wrong board?
	if ($_REQUEST['step'] <= 6)
	{
		while ($_REQUEST['start'] < $settings['maxMsgID'])
		{
			$request = wesql::query('
				SELECT /*!40001 SQL_NO_CACHE */ t.id_board, m.id_msg
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND t.id_board != m.id_board)
				WHERE m.id_msg > {int:id_msg_min}
					AND m.id_msg <= {int:id_msg_max}',
				array(
					'id_msg_min' => $_REQUEST['start'],
					'id_msg_max' => $_REQUEST['start'] + $increment,
				)
			);
			$boards = array();
			while ($row = wesql::fetch_assoc($request))
				$boards[$row['id_board']][] = $row['id_msg'];
			wesql::free_result($request);

			foreach ($boards as $board_id => $messages)
				wesql::query('
					UPDATE {db_prefix}messages
					SET id_board = {int:id_board}
					WHERE id_msg IN ({array_int:id_msg_array})',
					array(
						'id_msg_array' => $messages,
						'id_board' => $board_id,
					)
				);

			$_REQUEST['start'] += $increment;

			if (microtime(true) - $time_start > 3)
			{
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=routine;activity=recount;step=6;start=' . $_REQUEST['start'] . ';' . $context['session_query'];
				$context['continue_percent'] = round((700 + 100 * $_REQUEST['start'] / $settings['maxMsgID']) / $total_steps);

				return;
			}
		}

		$_REQUEST['start'] = 0;
	}

	// Update the latest message of each board.
	$request = wesql::query('
		SELECT m.id_board, MAX(m.id_msg) AS local_last_msg
		FROM {db_prefix}messages AS m
		WHERE m.approved = {int:is_approved}
		GROUP BY m.id_board',
		array(
			'is_approved' => 1,
		)
	);
	$realBoardCounts = array();
	while ($row = wesql::fetch_assoc($request))
		$realBoardCounts[$row['id_board']] = $row['local_last_msg'];
	wesql::free_result($request);

	$request = wesql::query('
		SELECT /*!40001 SQL_NO_CACHE */ id_board, id_parent, id_last_msg, child_level, id_msg_updated
		FROM {db_prefix}boards',
		array(
		)
	);
	$resort_me = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$row['local_last_msg'] = isset($realBoardCounts[$row['id_board']]) ? $realBoardCounts[$row['id_board']] : 0;
		$resort_me[$row['child_level']][] = $row;
	}
	wesql::free_result($request);

	krsort($resort_me);

	$lastModifiedMsg = array();
	foreach ($resort_me as $rows)
		foreach ($rows as $row)
		{
			// The latest message is the latest of the current board and its children.
			if (isset($lastModifiedMsg[$row['id_board']]))
				$curLastModifiedMsg = max($row['local_last_msg'], $lastModifiedMsg[$row['id_board']]);
			else
				$curLastModifiedMsg = $row['local_last_msg'];

			// If what is and what should be the latest message differ, an update is necessary.
			if ($row['local_last_msg'] != $row['id_last_msg'] || $curLastModifiedMsg != $row['id_msg_updated'])
				wesql::query('
					UPDATE {db_prefix}boards
					SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
					WHERE id_board = {int:id_board}',
					array(
						'id_last_msg' => $row['local_last_msg'],
						'id_msg_updated' => $curLastModifiedMsg,
						'id_board' => $row['id_board'],
					)
				);

			// Parent boards inherit the latest modified message of their children.
			if (isset($lastModifiedMsg[$row['id_parent']]))
				$lastModifiedMsg[$row['id_parent']] = max($row['local_last_msg'], $lastModifiedMsg[$row['id_parent']]);
			else
				$lastModifiedMsg[$row['id_parent']] = $row['local_last_msg'];
		}

	// Update all the basic statistics.
	updateStats('member');
	updateStats('message');
	updateStats('topic');

	// Finally, update the latest event times.
	loadSource('ScheduledTasks');
	CalculateNextTrigger();

	redirectexit('action=admin;area=maintain;sa=routine;done=recount');
}

// Perform a detailed version check. A very good thing ;)
function VersionDetail()
{
	global $txt, $context;

	isAllowedTo('admin_forum');

	// Call the function that'll get all the version info we need.
	loadSource('Subs-Admin');

	// Get a list of current server versions.
	$checkFor = array(
		'left' => array(
			'server',
			'php',
			'db_server',
		),
		'right_top' => array(
			'safe_mode',
			'gd',
			'ffmpeg',
			'imagick',
		),
		'right_bot' => array(
			'phpa',
			'apc',
			'memcache',
			'xcache',
		),
	);
	foreach ($checkFor as $key => $list)
		$context['current_versions'][$key] = getServerVersions($list);

	// Then we need to prepend some stuff into the left column - Wedge versions etc.
	$context['current_versions']['left'] = array(
		'yourVersion' => array(
			'title' => $txt['support_versions_forum'],
			'version' => WEDGE_VERSION,
		),
		'wedgeVersion' => array(
			'title' => $txt['support_versions_current'],
			'version' => '??',
		),
		'sep1' => '',
	) + $context['current_versions']['left'];

	// And combine the right
	$context['current_versions']['right'] = $context['current_versions']['right_top'] + array('sep2' => '') + $context['current_versions']['right_bot'];
	unset($context['current_versions']['right_top'], $context['current_versions']['right_bot']);

	// Now the file versions.
	$versionOptions = array(
		'include_ssi' => true,
		'include_subscriptions' => true,
		'sort_results' => true,
	);
	$version_info = getFileVersions($versionOptions);

	// Add the new info to the template context.
	$context += array(
		'file_versions' => $version_info['file_versions'],
		'default_template_versions' => $version_info['default_template_versions'],
		'template_versions' => $version_info['template_versions'],
		'default_language_versions' => $version_info['default_language_versions'],
		'default_known_languages' => array_keys($version_info['default_language_versions']),
	);

	// Make it easier to manage for the template.
	$context['forum_version'] = WEDGE_VERSION;

	wetem::load('view_versions');
	$context['page_title'] = $txt['admin_version_check'];
}

// Removing old posts doesn't take much as we really pass through.
function MaintainReattributePosts()
{
	global $context, $txt;

	checkSession();

	// Find the member.
	loadSource('Subs-Auth');
	$members = !empty($_POST['to']) ? findMembers($_POST['to']) : 0;

	if (empty($members))
		fatal_lang_error('reattribute_cannot_find_member');

	$memID = array_shift($members);
	$memID = $memID['id'];

	$email = $_POST['type'] == 'email' ? $_POST['from_email'] : '';
	$membername = $_POST['type'] == 'name' ? $_POST['from_name'] : '';

	if ($_POST['type'] == 'from')
	{
		$members = !empty($_POST['from_id']) ? findMembers($_POST['from_id']) : 0;
		if (empty($members))
			fatal_lang_error('reattribute_cannot_find_member_from');
		$from_id = array_shift($members);
		$from_id = $from_id['id'];
		// We want to get the destination details. Let reattributePosts do that for us.
		$email = $membername = false;
	}
	else
		$from_id = 0;

	// Now call the reattribute function.
	loadSource('Subs-Members');
	reattributePosts($memID, $from_id, $email, $membername, !empty($_POST['posts']));

	// If we're merging, now we need to clean out that old account.
	if (!empty($from_id))
		deleteMembers($from_id, false, $memID);

	$context['maintenance_finished'] = $txt['maintain_reattribute_posts'];
}

// Removing old members?
function MaintainPurgeInactiveMembers()
{
	global $context, $txt;

	$_POST['maxdays'] = empty($_POST['maxdays']) ? 0 : (int) $_POST['maxdays'];
	if (!empty($_POST['groups']) && $_POST['maxdays'] > 0)
	{
		checkSession();

		$groups = array();
		foreach ($_POST['groups'] as $id => $dummy)
			$groups[] = (int) $id;
		$time_limit = (time() - ($_POST['maxdays'] * 24 * 3600));
		$where_vars = array(
			'time_limit' => $time_limit,
		);

		if ($_POST['del_type'] == 'activated')
		{
			$where = 'mem.date_registered < {int:time_limit} AND mem.is_activated = {int:is_activated}';
			$where_vars['is_activated'] = 0;
		}
		else
			$where = 'mem.last_login < {int:time_limit}';

		// Need to get *all* groups then work out which (if any) we avoid.
		$request = wesql::query('
			SELECT id_group, group_name, min_posts
			FROM {db_prefix}membergroups',
			array(
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			// Avoid this one?
			if (!in_array($row['id_group'], $groups))
			{
				// Post group?
				if ($row['min_posts'] != -1)
				{
					$where .= ' AND mem.id_post_group != {int:id_post_group_' . $row['id_group'] . '}';
					$where_vars['id_post_group_' . $row['id_group']] = $row['id_group'];
				}
				else
				{
					$where .= ' AND mem.id_group != {int:id_group_' . $row['id_group'] . '} AND FIND_IN_SET({int:id_group_' . $row['id_group'] . '}, mem.additional_groups) = 0';
					$where_vars['id_group_' . $row['id_group']] = $row['id_group'];
				}
			}
		}
		wesql::free_result($request);

		// If we have ungrouped unselected we need to avoid those guys.
		if (!in_array(0, $groups))
		{
			$where .= ' AND (mem.id_group != 0 OR mem.additional_groups != {string:blank_add_groups})';
			$where_vars['blank_add_groups'] = '';
		}

		// Select all the members we're about to murder/remove...
		$request = wesql::query('
			SELECT mem.id_member, IFNULL(m.id_member, 0) AS is_mod
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}moderators AS m ON (m.id_member = mem.id_member)
			WHERE ' . $where,
			$where_vars
		);
		$members = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if (!$row['is_mod'] || !in_array(3, $groups))
				$members[] = $row['id_member'];
		}
		wesql::free_result($request);

		loadSource('Subs-Members');
		deleteMembers($members);
	}

	$context['maintenance_finished'] = $txt['maintain_members'];
}

function MaintainRecountPosts()
{
	global $txt, $context, $settings;

	isAllowedTo('admin_forum');
	checkSession('request');

	// Throttling attempt. There will be this many + 2-4 queries per run of this function.
	$items_per_request = 100;

	// Set up to the context.
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_countdown'] = '3';
	$context['continue_post_data'] = '';
	$context['continue_get_data'] = '';
	wetem::load('not_done');
	$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
	$context['start_time'] = time();

	// This might take a while. Let's give ourselves 5 minutes this run (hopefully we won't trip the webserver timeout)
	@set_time_limit(300);

	if (($temp = cache_get_data('recount_boards_info', 600)) !== null)
		list ($boards, $member_count) = $temp;
	else
	{
		// What boards are we interested in?
		$boards = array();
		$request = wesql::query('
			SELECT id_board
			FROM {db_prefix}boards AS b
			WHERE count_posts = {int:post_count_enabled}',
			array(
				'post_count_enabled' => 0,
			)
		);
		while ($row = wesql::fetch_row($request))
			$boards[] = (int) $row[0];
		wesql::free_result($request);

		if (!empty($settings['recycle_enable']))
			$boards = array_diff($boards, (array) $settings['recycle_board']);

		$request = wesql::query('
			SELECT COUNT(DISTINCT id_member)
			FROM {db_prefix}messages
			WHERE id_member != 0
				AND id_board IN ({array_int:boards})',
			array(
				'boards' => $boards,
			)
		);
		list ($member_count) = wesql::fetch_row($request);
		wesql::free_result($request);

		// Interesting. There are no boards set to count posts, fine let's do this the quick way.
		if (empty($boards))
		{
			wesql::query('
				UPDATE {db_prefix}members
				SET posts = 0');
			clean_cache();
			updateStats('postgroups');
			redirectexit('action=admin;area=maintain;sa=members;done=recountposts');
		}

		if (!empty($settings['cache_enable']))
			cache_put_data('recount_boards_info', array($boards, $member_count), 600);
	}

	// Get the people we want. No sense calling upon the entire posts table every single time, eh?
	// If we were to select member+post count from messages, we'd be making much bigger queries.
	$request = wesql::query('
		SELECT id_member, posts
		FROM {db_prefix}members
		ORDER BY id_member
		LIMIT {int:start}, {int:max}',
		array(
			'start' => $_REQUEST['start'],
			'max' => $items_per_request,
		));

	$old = array();
	while ($row = wesql::fetch_row($request))
		$old[(int) $row[0]] = (int) $row[1];
	wesql::free_result($request);

	$request = wesql::query('
		SELECT id_member, COUNT(id_msg) AS posts
		FROM {db_prefix}messages
		WHERE id_member IN ({array_int:members})
			AND id_board IN ({array_int:boards})
			AND icon != {literal:moved}
		GROUP BY id_member',
		array(
			'members' => array_keys($old),
			'boards' => $boards,
		)
	);

	$new = array();
	while ($row = wesql::fetch_assoc($request))
		$new[$row['id_member']] = $row['posts'];

	foreach ($old as $id => $postcount)
	{
		// Has the member disappeared from the messages table..? Reset their post count...
		if (!isset($new[$id]))
			$new[$id] = 0;

		// Was the original number incorrect..?
		if ($new[$id] !== $postcount)
		{
			// Update the post count.
			wesql::query('
				UPDATE {db_prefix}members
				SET posts = {int:posts}
				WHERE id_member = {int:id_member}',
				array(
					'posts' => $new[$id],
					'id_member' => $id,
				)
			);
		}
	}
	wesql::free_result($request);

	$context['start'] += $items_per_request;

	// Continue?
	if ($context['start'] < $member_count)
	{
		$context['continue_get_data'] = '?action=admin;area=maintain;sa=members;activity=recountposts;start=' . $context['start'] . ';' . $context['session_query'];
		$context['continue_percent'] = round(100 * $context['start'] / $member_count);

		return;
	}

	// We've not only stored stuff in the cache, we've also updated things that may need to be uncached.
	clean_cache();
	updateStats('postgroups');
	redirectexit('action=admin;area=maintain;sa=members;done=recountposts');
}

// Removing old posts doesn't take much as we really pass through.
function MaintainRemoveOldPosts()
{
	// Actually do what we're told!
	loadSource('RemoveTopic');
	RemoveOldTopics2();
}

function MaintainMassMoveTopics()
{
	global $context, $txt;

	// Only admins.
	isAllowedTo('admin_forum');

	checkSession('request');

	// Set up to the context.
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_countdown'] = '3';
	$context['continue_get_data'] = '';
	$context['continue_post_data'] = '';
	wetem::load('not_done');
	$context['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$context['start_time'] = time();

	// First time we do this?
	$id_board_from = array();
	$id_board_to = isset($_POST['id_board_to']) ? (int) $_POST['id_board_to'] : (int) $_REQUEST['id_board_to'];

	if (isset($_POST['boards']) && is_array($_POST['boards']))
		$boards = array_keys($_POST['boards']);
	elseif (isset($_REQUEST['id_board_from']))
		$boards = explode(',', $_REQUEST['id_board_from']);
	else
		$boards = array();

	foreach ($boards as $k => $v)
	{
		$v = (int) $v;
		if ($v > 0 && $v != $id_board_to)
			$id_board_from[] = $v;
	}

	// No boards then this is your stop.
	if (empty($id_board_from) || empty($id_board_to))
		return;

	// Custom conditions.
	$condition = '';
	$condition_params = array(
		'boards' => $id_board_from,
		'poster_time' => time() - 3600 * 24 * (isset($_POST['maxdays']) ? (int) $_POST['maxdays'] : 999),
		'num_topics' => 10,
	);

	// Just moved notice topics?
	$_POST['move_type'] = isset($_POST['move_type']) ? $_POST['move_type'] : 'nothing';
	if ($_POST['move_type'] == 'moved')
	{
		$condition .= '
			AND m.icon = {string:icon}
			AND t.locked = {int:locked}';
		$condition_params['icon'] = 'moved';
		$condition_params['locked'] = 1;
	}
	// Otherwise, maybe locked topics only?
	elseif ($_POST['move_type'] == 'locked')
	{
		$condition .= '
			AND t.locked != {int:unlocked}';
		$condition_params['unlocked'] = 0; // There are two kinds of locked topics.
	}
	else
		$_POST['move_type'] = 'nothing';
	$context['continue_post_data'] = '<input type="hidden" name="move_type" value="' . $_POST['move_type'] . '">';

	// Exclude pinned?
	if (isset($_POST['move_old_not_pinned']))
	{
		$condition .= '
			AND t.is_pinned = {int:is_pinned}';
		$condition_params['is_pinned'] = 0;
		$context['continue_post_data'] .= '<input type="hidden" name="move_old_not_pinned" value="1">';
	}

	// How many topics are we converting?
	if (!isset($_REQUEST['totaltopics']))
	{
		$request = wesql::query('
			SELECT COUNT(*)
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
			WHERE
				m.poster_time < {int:poster_time}' . $condition . '
				AND t.id_board IN ({array_int:boards})',
			$condition_params
		);
		list($total_topics) = wesql::fetch_row($request);
		wesql::free_result($request);
	}
	else
		$total_topics = (int) $_REQUEST['totaltopics'];

	// Seems like we need this here.
	$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . implode(',', $id_board_from) . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';start=' . $context['start'] . ';' . $context['session_query'];

	// We have topics to move so start the process.
	if (!empty($total_topics))
	{
		while ($context['start'] <= $total_topics)
		{
			// Let's get the topics.
			$request = wesql::query('
				SELECT t.id_topic
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
				WHERE
					m.poster_time < {int:poster_time}' . $condition . '
					AND t.id_board IN ({array_int:boards})
				LIMIT {int:num_topics}',
				$condition_params
			);
			$topics = array();
			while ($row = wesql::fetch_assoc($request))
				$topics[] = $row['id_topic'];
			wesql::free_result($request);

			// Just return if we don't have any topics left to move.
			if (empty($topics))
			{
				foreach ($id_board_from as $id_board)
					cache_put_data('board-' . $id_board, null, 120);
				cache_put_data('board-' . $id_board_to, null, 120);
				redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
			}

			// Let's move them.
			loadSource('MoveTopic');
			moveTopics($topics, $id_board_to);

			// We've done at least ten more topics.
			$context['start'] += 10;

			// Let's wait a while.
			if (time() - $context['start_time'] > 3)
			{
				// What's the percent?
				$context['continue_percent'] = round(100 * ($context['start'] / $total_topics), 1);
				$context['continue_get_data'] = '?action=admin;area=maintain;sa=topics;activity=massmove;id_board_from=' . implode(',', $id_board_from) . ';id_board_to=' . $id_board_to . ';totaltopics=' . $total_topics . ';start=' . $context['start'] . ';' . $context['session_query'];

				// Let the template system do it's thang.
				return;
			}
		}
	}

	// Don't confuse admins by having an out of date cache.
	foreach ($id_board_from as $id_board)
		cache_put_data('board-' . $id_board, null, 120);
	cache_put_data('board-' . $id_board_to, null, 120);

	redirectexit('action=admin;area=maintain;sa=topics;done=massmove');
}
