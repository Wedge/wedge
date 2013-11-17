<?php
/**
 * Handles all aspects of the moderation center display for moderators.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

// Entry point for the moderation center.
function ModerationCenter($dont_call = false)
{
	global $txt, $context, $settings, $theme, $options;

	// Don't run this twice... and don't conflict with the admin bar.
	if (isset($context['admin_area']))
		return;

	$context['can_moderate_boards'] = we::$user['mod_cache']['bq'] != '0=1';
	$context['can_moderate_groups'] = we::$user['mod_cache']['gq'] != '0=1';
	$context['can_moderate_approvals'] = $settings['postmod_active'] && !empty(we::$user['mod_cache']['ap']);

	// Everyone using this area must be allowed here!
	if (!$context['can_moderate_boards'] && !$context['can_moderate_groups'] && !$context['can_moderate_approvals'])
		isAllowedTo('access_mod_center');

	// We're gonna want a menu of some kind.
	loadSource('Subs-Menu');

	// Load the language, and the template.
	loadLanguage('ModerationCenter');
	add_css_file('mana', true);

	$context['robot_no_index'] = true;

	// This is the menu structure - refer to Subs-Menu.php for the details.
	$moderation_areas = array(
		'main' => array(
			'title' => $txt['mc_main'],
			'areas' => array(
				'index' => array(
					'label' => $txt['moderation_center'],
					'function' => 'ModerationHome',
				),
				'modlog' => array(
					'label' => $txt['modlog_view'],
					'enabled' => !empty($settings['log_enabled_moderate']) && $context['can_moderate_boards'],
					'file' => 'Modlog',
					'function' => 'ViewModlog',
				),
				'warnings' => array(
					'label' => $txt['mc_warned_users_title'],
					'enabled' => $context['can_moderate_boards'],
					'function' => 'ViewWatchedUsers',
					'subsections' => array(
						'member' => array($txt['mc_warned_users_member']),
						'post' => array($txt['mc_warned_users_post']),
						'log' => array($txt['mc_warning_log'], 'enabled' => allowedTo('issue_warning')),
					),
				),
			),
		),
		'posts' => array(
			'title' => $txt['mc_posts'],
			'enabled' => $context['can_moderate_boards'] || $context['can_moderate_approvals'],
			'areas' => array(
				'postmod' => array(
					'label' => $txt['mc_unapproved_posts'],
					'enabled' => $context['can_moderate_approvals'],
					'file' => 'PostModeration',
					'function' => 'PostModeration',
					'custom_url' => '<URL>?action=moderate;area=postmod',
					'subsections' => array(
						'posts' => array($txt['mc_unapproved_replies']),
						'topics' => array($txt['mc_unapproved_topics']),
					),
				),
				'reports' => array(
					'label' => $txt['mc_reported_posts'],
					'enabled' => $context['can_moderate_boards'],
					'file' => 'ModerationCenter',
					'function' => 'ReportedPosts',
					'subsections' => array(
						'open' => array($txt['mc_reportedp_active']),
						'closed' => array($txt['mc_reportedp_closed']),
					),
				),
			),
		),
		'groups' => array(
			'title' => $txt['mc_groups'],
			'enabled' => $context['can_moderate_groups'],
			'areas' => array(
				'groups' => array(
					'label' => $txt['mc_group_requests'],
					'file' => 'Groups',
					'function' => 'Groups',
					'custom_url' => '<URL>?action=moderate;area=groups;sa=requests',
					'enabled' => !empty($settings['show_group_membership']),
				),
				'viewgroups' => array(
					'label' => $txt['mc_view_groups'],
					'file' => 'Groups',
					'function' => 'Groups',
				),
			),
		),
	);

	// I don't know where we're going - I don't know where we've been...
	$menuOptions = array(
		'action' => 'moderate',
		'disable_url_session_check' => true,
	);
	$mod_include_data = createMenu($moderation_areas, $menuOptions);
	unset($moderation_areas);

	// We got something - didn't we? DIDN'T WE!
	if ($mod_include_data == false)
		fatal_lang_error('no_access', false);

	// Retain the ID information in case required by a subaction.
	$context['moderation_menu_id'] = $context['max_menu_id'];
	$context['moderation_menu_name'] = 'menu_data_' . $context['moderation_menu_id'];

	// What a pleasant shortcut - even tho we're not *really* on the admin screen who cares...
	$context['admin_area'] = $mod_include_data['current_area'];

	// Build the link tree.
	add_linktree($txt['moderation_center'], '<URL>?action=moderate');

	if (isset($mod_include_data['current_area']) && $mod_include_data['current_area'] != 'index')
		add_linktree($mod_include_data['label'], '<URL>?action=moderate;area=' . $mod_include_data['current_area']);
	if (!empty($mod_include_data['current_subsection']) && $mod_include_data['subsections'][$mod_include_data['current_subsection']][0] != $mod_include_data['label'])
		add_linktree($mod_include_data['subsections'][$mod_include_data['current_subsection']][0], '<URL>?action=moderate;area=' . $mod_include_data['current_area'] . ';sa=' . $mod_include_data['current_subsection']);

	// Now - finally - the bit before the encore - the main performance of course!
	if (!$dont_call)
	{
		if (isset($mod_include_data['file']))
			loadSource($mod_include_data['file']);

		$mod_include_data['function']();
	}
}

// This function basically is the home page of the moderation center.
function ModerationHome()
{
	global $txt, $context, $user_settings;

	loadTemplate('ModerationCenter');

	$context['page_title'] = $txt['moderation_center'];
	wetem::load('moderation_center');

	// We always do the Notes block.
	ModBlockNotes();

	// Then load what blocks the user actually can see...
	if ($context['can_moderate_groups'])
		$valid_blocks['g'] = 'GroupRequests';
	if ($context['can_moderate_boards'])
	{
		$valid_blocks['r'] = 'ReportedPosts';
		$valid_blocks['w'] = 'WatchedUsers';
	}

	// You need one or other of these to have some preferences.
	if ($context['can_moderate_groups'] || $context['can_moderate_boards'])
		$valid_blocks['p'] = 'Prefs';

	$context['mod_blocks'] = array();
	foreach ($valid_blocks as $k => $block)
	{
		$block = 'ModBlock' . $block;
		if (function_exists($block))
			$context['mod_blocks'][] = $block();
	}
}

// Show a list of the most active watched users.
function ModBlockWatchedUsers()
{
	global $context;

	if (($watched_users = cache_get_data('recent_user_watches', 240)) === null)
	{
		$request = wesql::query('
			SELECT id_member, real_name, last_login
			FROM {db_prefix}members
			WHERE warning >= 1
			ORDER BY last_login DESC
			LIMIT 10');
		$watched_users = array();
		while ($row = wesql::fetch_assoc($request))
			$watched_users[] = $row;
		wesql::free_result($request);

		cache_put_data('recent_user_watches', $watched_users, 240);
	}

	$context['watched_users'] = array();
	foreach ($watched_users as $user)
	{
		$context['watched_users'][] = array(
			'id' => $user['id_member'],
			'name' => $user['real_name'],
			'link' => '<a href="<URL>?action=profile;u=' . $user['id_member'] . '">' . $user['real_name'] . '</a>',
			'href' => '<URL>?action=profile;u=' . $user['id_member'],
			'last_login' => !empty($user['last_login']) ? timeformat($user['last_login']) : '',
		);
	}

	return 'watched_users';
}

// Show an area for the moderator to type into.
function ModBlockNotes()
{
	global $context, $txt;

	// Are we saving a note?
	if (isset($_POST['makenote'], $_POST['new_note']))
	{
		checkSession();

		$_POST['new_note'] = westr::htmlspecialchars(trim($_POST['new_note']));
		// Make sure they actually entered something.
		if (!empty($_POST['new_note']) && $_POST['new_note'] !== $txt['mc_click_add_note'])
		{
			// Insert it into the database then!
			wesql::insert('',
				'{db_prefix}log_comments',
				array(
					'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'recipient_name' => 'string',
					'body' => 'string', 'log_time' => 'int',
				),
				array(
					we::$id, we::$user['name'], 'modnote', '', $_POST['new_note'], time(),
				)
			);

			// Clear the cache.
			cache_put_data('moderator_notes', null, 240);
			cache_put_data('moderator_notes_total', null, 240);
		}

		// Redirect otherwise people can resubmit.
		redirectexit('action=moderate');
	}

	// Bye... bye...
	if (isset($_GET['notes'], $_GET['delete']) && is_numeric($_GET['delete']))
	{
		checkSession('get');

		// Let's delete it.
		wesql::query('
			DELETE FROM {db_prefix}log_comments
			WHERE id_comment = {int:note}
				AND comment_type = {literal:modnote}',
			array(
				'note' => $_GET['delete'],
			)
		);

		// Clear the cache.
		cache_put_data('moderator_notes', null, 240);
		cache_put_data('moderator_notes_total', null, 240);

		redirectexit('action=moderate');
	}

	// How many notes in total?
	if (($moderator_notes_total = cache_get_data('moderator_notes_total', 240)) === null)
	{
		$request = wesql::query('
			SELECT COUNT(*)
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.comment_type = {literal:modnote}'
		);
		list ($moderator_notes_total) = wesql::fetch_row($request);
		wesql::free_result($request);

		cache_put_data('moderator_notes_total', $moderator_notes_total, 240);
	}

	// Grab the current notes. We can only use the cache for the first page of notes.
	$offset = isset($_GET['notes'], $_GET['start']) ? $_GET['start'] : 0;
	if ($offset != 0 || ($moderator_notes = cache_get_data('moderator_notes', 240)) === null)
	{
		$request = wesql::query('
			SELECT IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lc.member_name) AS member_name,
				lc.log_time, lc.body, lc.id_comment AS id_note
			FROM {db_prefix}log_comments AS lc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
			WHERE lc.comment_type = {literal:modnote}
			ORDER BY id_comment DESC
			LIMIT {int:offset}, 10',
			array(
				'offset' => $offset,
			)
		);
		$moderator_notes = array();
		while ($row = wesql::fetch_assoc($request))
			$moderator_notes[] = $row;
		wesql::free_result($request);

		if ($offset == 0)
			cache_put_data('moderator_notes', $moderator_notes, 240);
	}

	// Let's construct a page index.
	$context['page_index'] = template_page_index('<URL>?action=moderate;area=index;notes', $_GET['start'], $moderator_notes_total, 10);
	$context['start'] = $_GET['start'];

	$context['notes'] = array();
	foreach ($moderator_notes as $note)
	{
		$context['notes'][] = array(
			'author' => array(
				'id' => $note['id_member'],
				'link' => $note['id_member'] ? ('<a href="<URL>?action=profile;u=' . $note['id_member'] . '" title="' . strip_tags(on_timeformat($note['log_time'])) . '">' . $note['member_name'] . '</a>') : $note['member_name'],
			),
			'time' => timeformat($note['log_time']),
			'text' => parse_bbc($note['body'], 'mod-note', array('user' => $note['id_member'])),
			'delete_href' => '<URL>?action=moderate;area=index;notes;delete=' . $note['id_note'] . ';' . $context['session_query'],
		);
	}

	return 'notes';
}

// Show a list of the most recent reported posts.
function ModBlockReportedPosts()
{
	global $context;

	// Got the info already?
	$cachekey = md5(serialize(we::$user['mod_cache']['bq']));
	$context['reported_posts'] = array();
	if (we::$user['mod_cache']['bq'] == '0=1')
		return 'reported_posts_block';

	if (($reported_posts = cache_get_data('reported_posts_' . $cachekey, 90)) === null)
	{
		// By Jove, that means we're in a position to get the reports, jolly good.
		$request = wesql::query('
			SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject,
				lr.num_reports, IFNULL(mem.real_name, lr.membername) AS author_name,
				IFNULL(mem.id_member, 0) AS id_author
			FROM {db_prefix}log_reported AS lr
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
			WHERE' . (we::$user['mod_cache']['bq'] == '1=1' ? '' : ' lr.' . we::$user['mod_cache']['bq'] . ' AND ') . '
				lr.closed = {int:not_closed}
				AND lr.ignore_all = {int:not_ignored}
			ORDER BY lr.time_updated DESC
			LIMIT 10',
			array(
				'not_closed' => 0,
				'not_ignored' => 0,
			)
		);
		$reported_posts = array();
		while ($row = wesql::fetch_assoc($request))
			$reported_posts[] = $row;
		wesql::free_result($request);

		// Cache it.
		cache_put_data('reported_posts_' . $cachekey, $reported_posts, 90);
	}

	$context['reported_posts'] = array();
	foreach ($reported_posts as $i => $row)
	{
		$context['reported_posts'][] = array(
			'id' => $row['id_report'],
			'alternate' => $i % 2,
			'topic_href' => '<URL>?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'report_href' => '<URL>?action=moderate;area=reports;report=' . $row['id_report'],
			'author' => array(
				'id' => $row['id_author'],
				'name' => $row['author_name'],
				'link' => $row['id_author'] ? '<a href="<URL>?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
				'href' => '<URL>?action=profile;u=' . $row['id_author'],
			),
			'comments' => array(),
			'subject' => $row['subject'],
			'num_reports' => $row['num_reports'],
		);
	}

	return 'reported_posts_block';
}

// Show a list of all the group requests they can see.
function ModBlockGroupRequests()
{
	global $context;

	$context['group_requests'] = array();
	// Make sure they can even moderate someone!
	if (we::$user['mod_cache']['gq'] == '0=1')
		return 'group_requests_block';

	// What requests are outstanding?
	$request = wesql::query('
		SELECT lgr.id_request, lgr.id_member, lgr.id_group, lgr.time_applied, mem.member_name, mg.group_name, mem.real_name
		FROM {db_prefix}log_group_requests AS lgr
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
			INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
		WHERE ' . (we::$user['mod_cache']['gq'] == '1=1' || we::$user['mod_cache']['gq'] == '0=1' ? we::$user['mod_cache']['gq'] : 'lgr.' . we::$user['mod_cache']['gq']) . '
		ORDER BY lgr.id_request DESC
		LIMIT 10',
		array(
		)
	);
	for ($i = 0; $row = wesql::fetch_assoc($request); $i++)
	{
		$context['group_requests'][] = array(
			'id' => $row['id_request'],
			'alternate' => $i % 2,
			'request_href' => '<URL>?action=groups;sa=requests;gid=' . $row['id_group'],
			'member' => array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'link' => '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
				'href' => '<URL>?action=profile;u=' . $row['id_member'],
			),
			'group' => array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
			),
			'time_submitted' => timeformat($row['time_applied']),
		);
	}
	wesql::free_result($request);

	return 'group_requests_block';
}

//!!! This needs to be given its own file.
// Browse all the reported posts...
function ReportedPosts()
{
	global $txt, $context;

	loadTemplate('ModerationCenter');

	// Put the open and closed options into tabs, because we can...
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_reported_posts'],
		'description' => $txt['mc_reported_posts_desc'],
	);

	// This comes under the umbrella of moderating posts.
	if (we::$user['mod_cache']['bq'] == '0=1')
		isAllowedTo('moderate_forum');

	// Are they wanting to view a particular report?
	if (!empty($_REQUEST['report']))
		return ModReport();

	// Set up the comforting bits...
	$context['page_title'] = $txt['mc_reported_posts'];
	wetem::load('reported_posts');

	// Are we viewing open or closed reports?
	$context['view_closed'] = isset($_GET['sa']) && $_GET['sa'] == 'closed' ? 1 : 0;

	// Are we doing any work?
	if ((isset($_GET['ignore']) || isset($_GET['close'])) && isset($_GET['rid']))
	{
		checkSession('get');
		$_GET['rid'] = (int) $_GET['rid'];

		// Update the report...
		wesql::query('
			UPDATE {db_prefix}log_reported
			SET ' . (isset($_GET['ignore']) ? 'ignore_all = {int:ignore_all}' : 'closed = {int:closed}') . '
			WHERE id_report = {int:id_report}
				AND ' . we::$user['mod_cache']['bq'],
			array(
				'ignore_all' => isset($_GET['ignore']) ? (int) $_GET['ignore'] : 0,
				'closed' => isset($_GET['close']) ? (int) $_GET['close'] : 0,
				'id_report' => $_GET['rid'],
			)
		);

		// Time to update.
		updateSettings(array('last_mod_report_action' => time()));
		recountOpenReports();
	}
	elseif (isset($_POST['close'], $_POST['close_selected']))
	{
		checkSession('post');

		// All the ones to update...
		$toClose = array();
		foreach ($_POST['close'] as $rid)
			$toClose[] = (int) $rid;

		if (!empty($toClose))
		{
			wesql::query('
				UPDATE {db_prefix}log_reported
				SET closed = {int:is_closed}
				WHERE id_report IN ({array_int:report_list})
					AND ' . we::$user['mod_cache']['bq'],
				array(
					'report_list' => $toClose,
					'is_closed' => 1,
				)
			);

			// Time to update.
			updateSettings(array('last_mod_report_action' => time()));
			recountOpenReports();
		}
	}

	// How many entries are we viewing?
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}log_reported AS lr
		WHERE lr.closed = {int:view_closed}
			AND ' . (we::$user['mod_cache']['bq'] == '1=1' || we::$user['mod_cache']['bq'] == '0=1' ? we::$user['mod_cache']['bq'] : 'lr.' . we::$user['mod_cache']['bq']),
		array(
			'view_closed' => $context['view_closed'],
		)
	);
	list ($context['total_reports']) = wesql::fetch_row($request);
	wesql::free_result($request);

	// So, that means we can page index, yes?
	$context['page_index'] = template_page_index('<URL>?action=moderate;area=reports' . ($context['view_closed'] ? ';sa=closed' : ''), $_GET['start'], $context['total_reports'], 10);
	$context['start'] = $_GET['start'];

	// By George, that means we in a position to get the reports, golly good.
	$request = wesql::query('
		SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject, lr.body,
			lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
			IFNULL(mem.real_name, lr.membername) AS author_name, IFNULL(mem.id_member, 0) AS id_author, b.name AS board_name
		FROM {db_prefix}log_reported AS lr
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
			INNER JOIN {db_prefix}boards AS b ON (lr.id_board = b.id_board)
		WHERE lr.closed = {int:view_closed}
			AND ' . (we::$user['mod_cache']['bq'] == '1=1' || we::$user['mod_cache']['bq'] == '0=1' ? we::$user['mod_cache']['bq'] : 'lr.' . we::$user['mod_cache']['bq']) . '
		ORDER BY lr.time_updated DESC
		LIMIT ' . $context['start'] . ', 10',
		array(
			'view_closed' => $context['view_closed'],
		)
	);
	$context['reports'] = array();
	$report_ids = array();
	for ($i = 0; $row = wesql::fetch_assoc($request); $i++)
	{
		$report_ids[] = $row['id_report'];
		$context['reports'][$row['id_report']] = array(
			'id' => $row['id_report'],
			'alternate' => $i % 2,
			'topic_href' => '<URL>?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			'report_href' => '<URL>?action=moderate;area=reports;report=' . $row['id_report'],
			'board_link' => '<a href="<URL>?board=' . $row['id_board'] . '.0">' . $row['board_name'] . '</a>',
			'author' => array(
				'id' => $row['id_author'],
				'name' => $row['author_name'],
				'link' => $row['id_author'] ? '<a href="<URL>?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
				'href' => '<URL>?action=profile;u=' . $row['id_author'],
			),
			'comments' => array(),
			'time_started' => timeformat($row['time_started']),
			'last_updated' => timeformat($row['time_updated']),
			'subject' => $row['subject'],
			'body' => parse_bbc($row['body'], 'report-post', array('user' => $row['id_author'])),
			'num_reports' => $row['num_reports'],
			'closed' => $row['closed'],
			'ignore' => $row['ignore_all']
		);
	}
	wesql::free_result($request);

	// Now get all the people who reported it.
	if (!empty($report_ids))
	{
		$request = wesql::query('
			SELECT lrc.id_comment, lrc.id_report, lrc.time_sent, lrc.comment,
				IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lrc.membername) AS reporter
			FROM {db_prefix}log_reported_comments AS lrc
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lrc.id_member)
			WHERE lrc.id_report IN ({array_int:report_list})',
			array(
				'report_list' => $report_ids,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$context['reports'][$row['id_report']]['comments'][] = array(
				'id' => $row['id_comment'],
				'message' => $row['comment'],
				'time' => timeformat($row['time_sent']),
				'member' => array(
					'id' => $row['id_member'],
					'name' => empty($row['reporter']) ? $txt['guest'] : $row['reporter'],
					'link' => $row['id_member'] ? '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['reporter'] . '</a>' : (empty($row['reporter']) ? $txt['guest'] : $row['reporter']),
					'href' => $row['id_member'] ? '<URL>?action=profile;u=' . $row['id_member'] : '',
				),
			);
		}
		wesql::free_result($request);
	}
}

// Act as an entrace for all group related activity.
//!!! As for most things in this file, this needs to be moved somewhere appropriate.
function ModerateGroups()
{
	global $txt, $context;

	// You need to be allowed to moderate groups...
	if (we::$user['mod_cache']['gq'] == '0=1')
		isAllowedTo('manage_membergroups');

	// Load the group templates.
	loadTemplate('ModerationCenter');

	// Setup the subactions...
	$subactions = array(
		'requests' => 'GroupRequests',
		'view' => 'ViewGroups',
	);

	if (!isset($_GET['sa']) || !isset($subactions[$_GET['sa']]))
		$_GET['sa'] = 'view';
	$context['sub_action'] = $_GET['sa'];

	// Call the relevant function.
	$subactions[$context['sub_action']]();
}

// How many open reports do we have?
function recountOpenReports()
{
	global $context;

	if (empty(we::$user['mod_cache']))
	{
		loadSource('Subs-Auth');
		rebuildModCache();
	}

	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}log_reported
		WHERE ' . we::$user['mod_cache']['bq'] . '
			AND closed = {int:not_closed}
			AND ignore_all = {int:not_ignored}',
		array(
			'not_closed' => 0,
			'not_ignored' => 0,
		)
	);
	list ($open_reports) = wesql::fetch_row($request);
	wesql::free_result($request);

	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}log_reported
		WHERE ' . we::$user['mod_cache']['bq'] . '
			AND closed = {int:closed}',
		array(
			'closed' => 1,
		)
	);
	list ($closed_reports) = wesql::fetch_row($request);
	wesql::free_result($request);

	$_SESSION['rc'] = array(
		'id' => we::$id,
		'time' => time(),
		'reports' => $open_reports,
		'closed' => $closed_reports,
	);

	$context['open_mod_reports'] = $open_reports;
}

function ModReport()
{
	global $context, $txt;

	// Have to at least give us something
	if (empty($_REQUEST['report']))
		fatal_lang_error('mc_no_modreport_specified');

	// Integers only please
	$_REQUEST['report'] = (int) $_REQUEST['report'];

	// Get the report details, need this so we can limit access to a particular board
	$request = wesql::query('
		SELECT lr.id_report, lr.id_msg, lr.id_topic, lr.id_board, lr.id_member, lr.subject, lr.body,
			lr.time_started, lr.time_updated, lr.num_reports, lr.closed, lr.ignore_all,
			IFNULL(mem.real_name, lr.membername) AS author_name, IFNULL(mem.id_member, 0) AS id_author
		FROM {db_prefix}log_reported AS lr
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
		WHERE lr.id_report = {int:id_report}
			AND ' . (we::$user['mod_cache']['bq'] == '1=1' || we::$user['mod_cache']['bq'] == '0=1' ? we::$user['mod_cache']['bq'] : 'lr.' . we::$user['mod_cache']['bq']) . '
		LIMIT 1',
		array(
			'id_report' => $_REQUEST['report'],
		)
	);

	// So did we find anything?
	if (!wesql::num_rows($request))
		fatal_lang_error('mc_no_modreport_found');

	// Woohoo we found a report and they can see it!  Bad news is we have more work to do
	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// If they are adding a comment then... add a comment.
	if (isset($_POST['add_comment']) && !empty($_POST['mod_comment']))
	{
		checkSession();

		$newComment = trim(westr::htmlspecialchars($_POST['mod_comment']));

		// In it goes.
		if (!empty($newComment))
		{
			wesql::insert('',
				'{db_prefix}log_comments',
				array(
					'id_member' => 'int', 'member_name' => 'string', 'comment_type' => 'string', 'recipient_name' => 'string',
					'id_notice' => 'int', 'body' => 'string', 'log_time' => 'int',
				),
				array(
					we::$id, we::$user['name'], 'reportc', '',
					$_REQUEST['report'], $newComment, time(),
				)
			);

			// Redirect to prevent double submittion.
			redirectexit('action=moderate;area=reports;report=' . $_REQUEST['report']);
		}
	}

	$context['report'] = array(
		'id' => $row['id_report'],
		'topic_id' => $row['id_topic'],
		'board_id' => $row['id_board'],
		'message_id' => $row['id_msg'],
		'message_href' => '<URL>?msg=' . $row['id_msg'],
		'message_link' => '<a href="<URL>?msg=' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
		'report_href' => '<URL>?action=moderate;area=reports;report=' . $row['id_report'],
		'author' => array(
			'id' => $row['id_author'],
			'name' => $row['author_name'],
			'link' => $row['id_author'] ? '<a href="<URL>?action=profile;u=' . $row['id_author'] . '">' . $row['author_name'] . '</a>' : $row['author_name'],
			'href' => '<URL>?action=profile;u=' . $row['id_author'],
		),
		'comments' => array(),
		'mod_comments' => array(),
		'time_started' => timeformat($row['time_started']),
		'last_updated' => timeformat($row['time_updated']),
		'subject' => $row['subject'],
		'body' => parse_bbc($row['body'], 'report-post', array('user' => $row['id_author'])),
		'num_reports' => $row['num_reports'],
		'closed' => $row['closed'],
		'ignore' => $row['ignore_all']
	);

	// So what bad things do the reporters have to say about it?
	$request = wesql::query('
		SELECT lrc.id_comment, lrc.id_report, lrc.time_sent, lrc.comment, li.member_ip,
			IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lrc.membername) AS reporter
		FROM {db_prefix}log_reported_comments AS lrc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lrc.id_member)
			LEFT JOIN {db_prefix}log_ips AS li ON (lrc.member_ip = li.id_ip)
		WHERE lrc.id_report = {int:id_report}',
		array(
			'id_report' => $context['report']['id'],
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$context['report']['comments'][] = array(
			'id' => $row['id_comment'],
			'message' => $row['comment'],
			'time' => timeformat($row['time_sent']),
			'member' => array(
				'id' => $row['id_member'],
				'name' => empty($row['reporter']) ? $txt['guest'] : $row['reporter'],
				'link' => $row['id_member'] ? '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['reporter'] . '</a>' : (empty($row['reporter']) ? $txt['guest'] : $row['reporter']),
				'href' => $row['id_member'] ? '<URL>?action=profile;u=' . $row['id_member'] : '',
				'ip' => !empty($row['member_ip']) && allowedTo('moderate_forum') ? '<a href="<URL>?action=trackip;searchip=' . format_ip($row['member_ip']) . '">' . format_ip($row['member_ip']) . '</a>' : '',
			),
		);
	}
	wesql::free_result($request);

	// Hang about old chap, any comments from moderators on this one?
	$request = wesql::query('
		SELECT lc.id_comment, lc.id_notice, lc.log_time, lc.body,
			IFNULL(mem.id_member, 0) AS id_member, IFNULL(mem.real_name, lc.member_name) AS moderator
		FROM {db_prefix}log_comments AS lc
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lc.id_member)
		WHERE lc.id_notice = {int:id_report}
			AND lc.comment_type = {literal:reportc}',
		array(
			'id_report' => $context['report']['id'],
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$context['report']['mod_comments'][] = array(
			'id' => $row['id_comment'],
			'message' => parse_bbc($row['body'], 'mod-comment', array('user' => $row['id_member'])),
			'time' => timeformat($row['log_time']),
			'member' => array(
				'id' => $row['id_member'],
				'name' => $row['moderator'],
				'link' => $row['id_member'] ? '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['moderator'] . '</a>' : $row['moderator'],
				'href' => '<URL>?action=profile;u=' . $row['id_member'],
			),
		);
	}
	wesql::free_result($request);

	// What have the other moderators done to this message?
	loadSource(array('Modlog', 'Subs-List'));
	loadLanguage('Modlog');

	// This is all the information from the moderation log.
	$listOptions = array(
		'id' => 'moderation_actions_list',
		'title' => $txt['mc_modreport_modactions'],
		'items_per_page' => 15,
		'no_items_label' => $txt['modlog_no_entries_found'],
		'base_href' => '<URL>?action=moderate;area=reports;report=' . $context['report']['id'],
		'default_sort_col' => 'time',
		'get_items' => array(
			'function' => 'list_getModLogEntries',
			'params' => array(
				'lm.id_topic = {int:id_topic}',
				array('id_topic' => $context['report']['topic_id']),
				1,
			),
		),
		'get_count' => array(
			'function' => 'list_getModLogEntryCount',
			'params' => array(
				'lm.id_topic = {int:id_topic}',
				array('id_topic' => $context['report']['topic_id']),
				1,
			),
		),
		// This assumes we are viewing by user.
		'columns' => array(
			'action' => array(
				'header' => array(
					'value' => $txt['modlog_action'],
				),
				'data' => array(
					'db' => 'action_text',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.action',
					'reverse' => 'lm.action DESC',
				),
			),
			'time' => array(
				'header' => array(
					'value' => $txt['modlog_date'],
				),
				'data' => array(
					'db' => 'time',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.log_time',
					'reverse' => 'lm.log_time DESC',
				),
			),
			'moderator' => array(
				'header' => array(
					'value' => $txt['modlog_member'],
				),
				'data' => array(
					'db' => 'moderator_link',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				),
			),
			'position' => array(
				'header' => array(
					'value' => $txt['modlog_position'],
				),
				'data' => array(
					'db' => 'position',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'mg.group_name',
					'reverse' => 'mg.group_name DESC',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => $txt['modlog_ip'],
				),
				'data' => array(
					'db' => 'ip',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.ip',
					'reverse' => 'lm.ip DESC',
				),
			),
		),
	);

	// Create the watched user list.
	createList($listOptions);

	// Make sure to get the correct tab selected.
	if ($context['report']['closed'])
		$context[$context['moderation_menu_name']]['current_subsection'] = 'closed';

	// Finally we are done :P
	loadTemplate('ModerationCenter');
	$context['page_title'] = sprintf($txt['mc_viewmodreport'], $context['report']['subject'], $context['report']['author']['name']);
	wetem::load('viewmodreport');
}

// View watched users.
function ViewWatchedUsers()
{
	global $settings, $context, $txt;

	// If we're viewing the whole log, we need to get out of Dodge.
	if (isset($_GET['sa']) && $_GET['sa'] == 'log')
		return ViewInfractionLog();

	// Some important context!
	$context['page_title'] = $txt['mc_warned_users_title'];
	$context['view_posts'] = isset($_GET['sa']) && $_GET['sa'] == 'post';
	$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

	loadTemplate('ModerationCenter');

	// Put some pretty tabs on cause we're gonna be doing hot stuff here...
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_warned_users_title'],
		'description' => $txt['mc_warned_users_desc'],
	);

	// First off - are we deleting?
	if (!empty($_REQUEST['delete']))
	{
		checkSession(!is_array($_REQUEST['delete']) ? 'get' : 'post');

		$toDelete = array();
		if (!is_array($_REQUEST['delete']))
			$toDelete[] = (int) $_REQUEST['delete'];
		else
			foreach ($_REQUEST['delete'] as $did)
				$toDelete[] = (int) $did;

		if (!empty($toDelete))
		{
			loadSource('RemoveTopic');
			// If they don't have permission we'll let it error - either way no chance of a security slip here!
			foreach ($toDelete as $did)
				removeMessage($did);
		}
	}

	// Start preparing the list by grabbing relevant permissions.
	if (!$context['view_posts'])
	{
		$approve_query = '';
		$delete_boards = array();
	}
	else
	{
		// Still obey permissions!
		$approve_boards = boardsAllowedTo('approve_posts');
		$delete_boards = boardsAllowedTo('delete_any');

		if ($approve_boards == array(0))
			$approve_query = '';
		elseif (!empty($approve_boards))
			$approve_query = ' AND m.id_board IN (' . implode(',', $approve_boards) . ')';
		// Nada, zip, etc...
		else
			$approve_query = ' AND 0';
	}

	loadSource('Subs-List');

	// This is all the information required for a watched user listing.
	$listOptions = array(
		'id' => 'watch_user_list',
		'title' => $txt['mc_warned_users_title'] . ' - ' . ($context['view_posts'] ? $txt['mc_warned_users_post'] : $txt['mc_warned_users_member']),
		'width' => '100%',
		'items_per_page' => $settings['defaultMaxMessages'],
		'no_items_label' => $context['view_posts'] ? $txt['mc_warned_users_no_posts'] : $txt['mc_warned_users_none'],
		'base_href' => '<URL>?action=moderate;area=warnings;sa=' . ($context['view_posts'] ? 'post' : 'member'),
		'default_sort_col' => $context['view_posts'] ? '' : 'member',
		'get_items' => array(
			'function' => $context['view_posts'] ? 'list_getWatchedUserPosts' : 'list_getWatchedUsers',
			'params' => array(
				$approve_query,
				$delete_boards,
			),
		),
		'get_count' => array(
			'function' => $context['view_posts'] ? 'list_getWatchedUserPostsCount' : 'list_getWatchedUserCount',
			'params' => array(
				$approve_query,
			),
		),
		// This assumes we are viewing by user.
		'columns' => array(
			'member' => array(
				'header' => array(
					'value' => $txt['mc_warned_users_member'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'id' => false,
							'name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'real_name',
					'reverse' => 'real_name DESC',
				),
			),
			'warning' => array(
				'header' => array(
					'value' => $txt['mc_warned_users_points'],
				),
				'data' => array(
					'function' => create_function('$member', '
						return allowedTo(\'issue_warning\') ? \'<a href="<URL>?action=profile;u=\' . $member[\'id\'] . \';area=infractions">\' . $member[\'warning\'] . \'</a>\' : $member[\'warning\'];
					'),
				),
				'sort' => array(
					'default' => 'warning',
					'reverse' => 'warning DESC',
				),
			),
			'posts' => array(
				'header' => array(
					'value' => $txt['posts'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=profile;u=%1$d;area=showposts;sa=messages">%2$s</a>',
						'params' => array(
							'id' => false,
							'posts' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'posts',
					'reverse' => 'posts DESC',
				),
			),
			'last_login' => array(
				'header' => array(
					'value' => $txt['mc_warned_users_last_login'],
				),
				'data' => array(
					'db' => 'last_login',
				),
				'sort' => array(
					'default' => 'last_login',
					'reverse' => 'last_login DESC',
				),
			),
			'last_post' => array(
				'header' => array(
					'value' => $txt['mc_warned_users_last_post'],
				),
				'data' => array(
					'function' => create_function('$member', '
						if ($member[\'last_post_id\'])
							return \'<a href="<URL>?msg=\' . $member[\'last_post_id\'] . \'">\' . $member[\'last_post\'] . \'</a>\';
						else
							return $member[\'last_post\'];
					'),
				),
			),
		),
		'form' => array(
			'href' => '<URL>?action=moderate;area=warnings;sa=post',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			$context['view_posts'] ?
			array(
				'position' => 'bottom_of_list',
				'value' => '
					<input type="submit" name="delete_selected" value="' . $txt['quickmod_delete_selected'] . '" class="delete">',
				'align' => 'right',
			) : array(),
		),
	);

	// If this is being viewed by posts we actually change the columns to call a template each time.
	if ($context['view_posts'])
	{
		$listOptions['columns'] = array(
			'posts' => array(
				'data' => array(
					'function' => create_function('$post', '
						return template_user_watch_post_callback($post);
					'),
				),
			),
		);
	}

	// Create the watched user list.
	createList($listOptions);

	wetem::load('show_list');
	$context['default_list'] = 'watch_user_list';
}

function list_getWatchedUserCount($approve_query)
{
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE warning > 0'
	);
	list ($totalMembers) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $totalMembers;
}

function list_getWatchedUsers($start, $items_per_page, $sort, $approve_query, $dummy)
{
	global $txt, $settings, $context;

	$request = wesql::query('
		SELECT id_member, real_name, last_login, posts, warning
		FROM {db_prefix}members
		WHERE warning > 0
		ORDER BY {raw:sort}
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'sort' => $sort,
		)
	);
	$watched_users = array();
	$members = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$watched_users[$row['id_member']] = array(
			'id' => $row['id_member'],
			'name' => $row['real_name'],
			'last_login' => $row['last_login'] ? timeformat($row['last_login']) : $txt['never'],
			'last_post' => $txt['not_applicable'],
			'last_post_id' => 0,
			'warning' => $row['warning'],
			'posts' => $row['posts'],
		);
		$members[] = $row['id_member'];
	}
	wesql::free_result($request);

	if (!empty($members))
	{
		// First get the latest messages from these users.
		$request = wesql::query('
			SELECT m.id_member, MAX(m.id_msg) AS last_post_id
			FROM {db_prefix}messages AS m' . (we::$user['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member IN ({array_int:member_list})' . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND m.approved = {int:is_approved}') . '
			GROUP BY m.id_member',
			array(
				'member_list' => $members,
				'is_approved' => 1,
			)
		);
		$latest_posts = array();
		while ($row = wesql::fetch_assoc($request))
			$latest_posts[$row['id_member']] = $row['last_post_id'];

		if (!empty($latest_posts))
		{
			// Now get the time those messages were posted.
			$request = wesql::query('
				SELECT id_member, poster_time
				FROM {db_prefix}messages
				WHERE id_msg IN ({array_int:message_list})',
				array(
					'message_list' => $latest_posts,
				)
			);
			while ($row = wesql::fetch_assoc($request))
			{
				$watched_users[$row['id_member']]['last_post'] = timeformat($row['poster_time']);
				$watched_users[$row['id_member']]['last_post_id'] = $latest_posts[$row['id_member']];
			}

			wesql::free_result($request);
		}

		$request = wesql::query('
			SELECT MAX(m.poster_time) AS last_post, MAX(m.id_msg) AS last_post_id, m.id_member
			FROM {db_prefix}messages AS m' . (we::$user['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member IN ({array_int:member_list})' . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
				AND m.approved = {int:is_approved}') . '
			GROUP BY m.id_member',
			array(
				'member_list' => $members,
				'is_approved' => 1,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$watched_users[$row['id_member']]['last_post'] = timeformat($row['last_post']);
			$watched_users[$row['id_member']]['last_post_id'] = $row['last_post_id'];
		}
		wesql::free_result($request);
	}

	return $watched_users;
}

function list_getWatchedUserPostsCount($approve_query)
{
	$request = wesql::query('
		SELECT COUNT(*)
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE mem.warning > 0
				AND {query_see_board}
				' . $approve_query
	);
	list ($totalMemberPosts) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $totalMemberPosts;
}

function list_getWatchedUserPosts($start, $items_per_page, $sort, $approve_query, $delete_boards)
{
	global $txt;

	$request = wesql::query('
		SELECT m.id_msg, m.id_topic, m.id_board, m.id_member, m.subject, m.body, m.poster_time,
			m.approved, mem.real_name, m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE mem.warning > 0
			AND {query_see_board}
			' . $approve_query . '
		ORDER BY m.id_msg DESC
		LIMIT ' . $start . ', ' . $items_per_page
	);
	$member_posts = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$row['subject'] = censorText($row['subject']);
		$row['body'] = censorText($row['body']);

		$member_posts[$row['id_msg']] = array(
			'id' => $row['id_msg'],
			'id_topic' => $row['id_topic'],
			'author_link' => '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>',
			'subject' => $row['subject'],
			'body' => parse_bbc($row['body'], 'post', array('smileys' => $row['smileys_enabled'], 'cache' => $row['id_msg'], 'user' => $row['id_member'])),
			'poster_time' => timeformat($row['poster_time']),
			'approved' => $row['approved'],
			'can_delete' => $delete_boards == array(0) || in_array($row['id_board'], $delete_boards),
		);
	}
	wesql::free_result($request);

	return $member_posts;
}

function ViewInfractionLog()
{
	global $context, $txt, $settings, $theme;

	loadTemplate('ModerationCenter');
	loadLanguage('Profile');

	loadSource('Subs-List');

	$context['page_title'] = $txt['mc_warned_users_title'] . ' - ' . $txt['mc_warning_log'];
	$context[$context['moderation_menu_name']]['tab_data'] = array(
		'title' => $txt['mc_warning_log'],
		'description' => $txt['mc_warning_log_desc'],
	);

	// This is all the information required for a watched user listing.
	$listOptions = array(
		'id' => 'infraction_log',
		'title' => $txt['mc_warned_users_title'] . ' - ' . $txt['mc_warning_log'],
		'width' => '100%',
		'items_per_page' => $settings['defaultMaxMessages'],
		'no_items_label' => $txt['mc_warnings_none'],
		'base_href' => '<URL>?action=moderate;area=warnings;sa=log',
		'default_sort_col' => 'issue_date',
		'default_sort_dir' => 'desc',
		'get_items' => array(
			'function' => 'list_getInfractionLog',
		),
		'get_count' => array(
			'function' => 'list_getInfractionLogCount',
		),
		// This assumes we are viewing by user.
		'columns' => array(
			'issued_by' => array(
				'header' => array(
					'value' => $txt['mc_warning_by'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'issued_by' => false,
							'issued_by_name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'issued_by_name, id_issue DESC',
					'reverse' => 'issued_by_name DESC, id_issue DESC',
				),
			),
			'issued_to' => array(
				'header' => array(
					'value' => $txt['mc_warning_to'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'issued_to' => false,
							'issued_to_name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'issued_to_name, id_issue DESC',
					'reverse' => 'issued_to_name DESC, id_issue DESC',
				),
			),
			'issue_date' => array(
				'header' => array(
					'value' => $txt['mc_warning_on'],
				),
				'data' => array(
					'timeformat' => 'issue_date',
				),
				'sort' => array(
					'default' => 'issue_date',
					'reverse' => 'issue_date DESC',
				),
			),
			'points' => array(
				'header' => array(
					'value' => $txt['mc_warned_users_points'],
				),
				'data' => array(
					'comma_format' => 'points',
					'class' => 'center',
				),
				'sort' => array(
					'default' => 'points',
					'reverse' => 'points DESC',
				),
			),
			'status' => array(
				'header' => array(
					'value' => $txt['mc_warning_status'],
				),
				'data' => array(
					'db' => 'status',
					'class' => 'center',
				),
				'sort' => array(
					'default' => 'inf_state',
					'reverse' => 'inf_state DESC',
				),
			),
			'view' => array(
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=profile;u=%1$d;area=infractions;view=%2$d" onclick="return reqWin(this);"><img src="' . $theme['images_url'] . '/filter.gif"></a>',
						'params' => array(
							'issued_to' => false,
							'id_issue' => false,
						),
					),
					'class' => 'center',
				),
			),
			'revoke' => array(
				'data' => array(
					'db' => 'revoke',
					'class' => 'center',
				),
			),
		),
		'row_class' => 'class',
	);

	createList($listOptions);
	wetem::load('show_list');
	$context['default_list'] = 'infraction_log';
}

function list_getInfractionLog($start, $items_per_page, $sort)
{
	global $txt, $settings, $context;

	// Things we need. To make us go.
	$classes = array(
		0 => 'active',
		1 => 'expired',
		2 => 'revoked',
	);

	$inf_settings = !empty($settings['infraction_settings']) ? unserialize($settings['infraction_settings']) : array();
	$revoke_any = isset($inf_settings['revoke_any_issued']) ? $inf_settings['revoke_any_issued'] : array();
	$revoke_any[] = 1; // Admins really are special.
	$context['revoke_own'] = !empty($inf_settings['revoke_own_issued']);
	$context['revoke_any'] = count(array_intersect(we::$user['groups'], $revoke_any)) != 0;

	$request = wesql::query('
		SELECT i.id_issue, IFNULL(memi.id_member, 0) AS issued_by, IFNULL(memi.real_name, i.issued_by_name) AS issued_by_name,
			IFNULL(memt.id_member, 0) AS issued_to, IFNULL(memt.real_name, i.issued_to_name) AS issued_to_name,
			i.issue_date, i.points, i.inf_state
		FROM {db_prefix}log_infractions AS i
		LEFT JOIN {db_prefix}members AS memi ON (i.issued_by = memi.id_member)
		LEFT JOIN {db_prefix}members AS memt ON (i.issued_to = memt.id_member)
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items_per_page}',
		array(
			'start' => $start,
			'items_per_page' => $items_per_page,
			'sort' => $sort,
		)
	);

	$items = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$row['class'] = 'inf_' . $classes[$row['inf_state']];
		$row['status'] = $txt['infraction_state_' . $classes[$row['inf_state']]];

		$row['revoke'] = $row['inf_state'] == 0 && ($context['revoke_any'] || ($context['revoke_own'] && $row['issued_by'] == we::$id)) ? '<a href="<URL>?action=profile;u=' . $row['issued_to'] . ';area=infractions;revoke=' . $row['id_issue'] . ';log">' . $txt['revoke'] . '</a>' : '';
		$items[$row['id_issue']] = $row;
	}

	return $items;
}

function list_getInfractionLogCount()
{
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}log_infractions');
	list ($totalInfractions) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $totalInfractions;
}

function ModBlockPrefs()
{
	global $context, $txt, $user_settings;

	// Does the user have any settings yet?
	if (empty($user_settings['mod_prefs']))
		$pref_binary = 5;
	else
		list (, $pref_binary) = explode('|', $user_settings['mod_prefs']);

	// Are we saving?
	if (isset($_POST['save']))
	{
		checkSession('post');
		/* Current format of mod_prefs is:
			|yyy

			WHERE:
				yyy = Integer with the following bit status:
					- yyy & 1 = Always notify on reports.
					- yyy & 2 = Notify on reports for moderators only.
					- yyy & 4 = Notify about posts awaiting approval.
		*/

		// Now check other options!
		$pref_binary = 0;

		if ($context['can_moderate_approvals'] && !empty($_POST['mod_notify_approval']))
			$pref_binary |= 4;

		if ($context['can_moderate_boards'] && !empty($_POST['mod_notify_report']))
			$pref_binary |= ($_POST['mod_notify_report'] == 2 ? 1 : 2);

		updateMemberData(we::$id, array('mod_prefs' => '|' . $pref_binary));
	}

	// What blocks does the user currently have selected?
	$context['mod_settings'] = array(
		'notify_report' => $pref_binary & 2 ? 1 : ($pref_binary & 1 ? 2 : 0),
		'notify_approval' => $pref_binary & 4,
	);

	return 'prefs';
}

// Now for some functions used by the board index - but they're moderation center related.
function cache_getBoardIndexReports()
{
	$data = array();

	$request = wesql::query('
		SELECT lr.id_report, lr.id_member, lr.subject, lr.time_updated,
			IFNULL(mem.real_name, lr.membername) AS author_name, IFNULL(mem.id_member, 0) AS id_author
		FROM {db_prefix}log_reported AS lr
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
		WHERE lr.closed = {int:view_closed}
			AND ' . (we::$user['mod_cache']['bq'] == '1=1' || we::$user['mod_cache']['bq'] == '0=1' ? we::$user['mod_cache']['bq'] : 'lr.' . we::$user['mod_cache']['bq']) . '
		ORDER BY lr.time_updated DESC
		LIMIT 1',
		array(
			'view_closed' => 0,
		)
	);
	if ($row = wesql::fetch_assoc($request))
		$data = $row;
	wesql::free_result($request);

	return array(
		'data' => $data,
		'expires' => time() + 240,
	);
}

function cache_getBoardIndexGroupReq()
{
	$data = array();

	$request = wesql::query('
		SELECT lgr.id_request, lgr.id_member, lgr.id_group, lgr.time_applied, mem.member_name, mg.group_name, mem.real_name
		FROM {db_prefix}log_group_requests AS lgr
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lgr.id_member)
			INNER JOIN {db_prefix}membergroups AS mg ON (mg.id_group = lgr.id_group)
		WHERE ' . (we::$user['mod_cache']['gq'] == '1=1' || we::$user['mod_cache']['gq'] == '0=1' ? we::$user['mod_cache']['gq'] : 'lgr.' . we::$user['mod_cache']['gq']) . '
		ORDER BY lgr.id_request DESC
		LIMIT 1',
		array(
		)
	);
	if ($row = wesql::fetch_assoc($request))
		$data = $row;
	wesql::free_result($request);

	return array(
		'data' => $data,
		'expires' => time() + 240,
	);
}
