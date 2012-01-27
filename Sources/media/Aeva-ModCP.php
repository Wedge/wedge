<?php
/**
 * Wedge
 *
 * Moderation control panel for the gallery.
 * Uses portions written by Shitiz Garg.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

/*
	This file handles the moderation panel of the gallery.

	void aeva_modCP()
		- Handles the homepage of modcp

	void aeva_modCP_submissions()
		- Shows the submissions index
		- Shows items, comments as well as albums

	void aeva_modCP_submissions_approve()
		- Approves an submitted item, comment or album

	void aeva_modCP_submissions_delete()
		- Deletes an unapproved item

	void aeva_modCP_reports()
		- Shows the reported items and comments along with their reason

	void aeva_modCP_reports_delete()
		- Deletes a report

	void aeva_modCP_reports_deleteItem()
		- Deletes a reported item

	void aeva_modCP_modLog()
		- Shows the complete moderation log
		- Also gives them an option to delete entries
*/

// Home of our wonderful moderation center
function aeva_modCP()
{
	global $context;

	// Let's repeat the Admin's about page.....
	loadSource('media/ManageMedia');
	loadTemplate('ManageMedia');
	aeva_admin_about();
}

// Handles the submissions area as well as the homepage of it
function aeva_modCP_submissions()
{
	global $txt, $context, $amSettings, $scripturl, $galurl;

	// Handle the subsections
	$do = array(
		'approve' => array('aeva_modCP_submissions_approve', false),
		'delete' => array('aeva_modCP_submissions_delete', false),
	);

	if (isset($_REQUEST['do'], $do[$_REQUEST['do']]))
		if ($do[$_REQUEST['do']][1] === true)
			return $do[$_REQUEST['do']][0]();
		else
			$do[$_REQUEST['do']][0]();

	loadSource('media/Subs-Media');
	aeva_addHeaders();

	loadTemplate('ManageMedia');
	wetem::load('aeva_admin_submissions');

	// Let's get the data we need
	$filter = isset($_REQUEST['filter']) && in_array($_REQUEST['filter'], array('items', 'coms', 'albums')) ? $_REQUEST['filter'] : (isset($_REQUEST['type']) && in_array($_REQUEST['type'], array('items', 'coms', 'albums')) ? $_REQUEST['type'] : 'items');
	$per_page = 20;
	if ($filter == 'items')
	{
		$request = wesql::query('
			SELECT m.id_media AS id, m.title, m.time_added AS posted_on, m.id_member, mem.real_name AS member_name, m.description, m.keywords
			FROM {db_prefix}media_items AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.approved = 0
			LIMIT {int:start}, {int:per_page}', array('start' => (int) $_REQUEST['start'], 'per_page' => $per_page));
		$del_link = $galurl . 'area=moderate;sa=submissions;do=delete;in=%s;type=items;' . $context['session_query'];
		$edit_link = $galurl . 'sa=post;in=%s';
		$view_link = $galurl . 'sa=item;in=%s';
		$total = $amSettings['num_unapproved_items'];
	}
	elseif ($filter == 'albums')
	{
		$request = wesql::query('
			SELECT a.id_album AS id, a.name AS title, a.album_of AS id_member, mem.real_name AS member_name, a.description
			FROM {db_prefix}media_albums AS a
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = a.album_of)
			WHERE a.approved = 0
			LIMIT {int:start}, {int:per_page}', array('start' => (int) $_REQUEST['start'], 'per_page' => $per_page));
		$del_link = $galurl . 'area=moderate;sa=submissions;do=delete;in=%s;type=albums;' . $context['session_query'];
		$edit_link = $galurl . 'area=mya;sa=edit;in=%s';
		$view_link = $galurl . 'sa=album;in=%s';
		$total = $amSettings['num_unapproved_albums'];
	}
	elseif ($filter == 'coms')
	{
		$request = wesql::query('
			SELECT c.id_comment AS id, m.title, c.id_member, mem.real_name AS member_name, c.posted_on, c.id_media AS id2, c.message AS description
			FROM {db_prefix}media_comments AS c
				INNER JOIN {db_prefix}media_items AS m ON (c.id_media = m.id_media)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = c.id_member)
			WHERE c.approved = 0
			LIMIT {int:start}, {int:per_page}', array('start' => (int) $_REQUEST['start'], 'per_page' => $per_page));
		$del_link = $galurl . 'area=moderate;sa=submissions;do=delete;in=%s;type=coms;' . $context['session_query'];
		$edit_link = $galurl . 'sa=edit;type=comment;in=%s';
		$view_link = $galurl . 'sa=item;in=%s#com%s';
		$total = $amSettings['num_unapproved_comments'];
	}

	// Fetch the data
	$context['aeva_items'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$context['aeva_items'][] = array(
			'id' => $row['id'],
			'item_link' => $filter == 'coms' ? sprintf($view_link, $row['id2'], $row['id']) : sprintf($view_link, $row['id']),
			'title' => $row['title'],
			'edit_link' => sprintf($edit_link, $row['id']),
			'del_link' => sprintf($del_link, $row['id']),
			'poster' => aeva_profile($row['id_member'], $row['member_name']),
			'posted_on' => $filter != 'albums' ? timeformat($row['posted_on']) : 0,
			'description' => !empty($row['description']) ? parse_bbc($row['description']) : '',
			'keywords' => !empty($row['keywords']) ? implode(', ', explode(',', $row['keywords'])) : '',
		);
	}
	wesql::free_result($request);

	// Page index
	$_REQUEST['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$context['aeva_page_index'] = template_page_index($scripturl . '?action=media;area=moderate;sa=submissions;filter=' . $filter . ';' . $context['session_query'], $_REQUEST['start'], $total, $per_page);

	// We're done!
	$context['aeva_filter'] = $filter;
	$context['aeva_total'] = $total;

	// Get the subtabs
	$context['aeva_header']['subtabs'] = array(
		'items' => array('title' => 'media_items', 'url' => $scripturl.'?action=media;area=moderate;sa=submissions;filter=items;' . $context['session_query'], 'class' => $filter == 'items' ? 'active' : ''),
		'comments' => array('title' => 'media_comments', 'url' => $scripturl.'?action=media;area=moderate;sa=submissions;filter=coms;' . $context['session_query'], 'class' => $filter == 'coms' ? 'active' : ''),
		'albums' => array('title' => 'media_albums', 'url' => $scripturl.'?action=media;area=moderate;sa=submissions;filter=albums;' . $context['session_query'], 'class' => $filter == 'albums' ? 'active' : ''),
	);

	// HTML headers
	$context['header'] .= '
	<script src="' . add_js_file('scripts/mediadmin.js', false, true) . '"></script>';
}

// Approves an unapproved item
function aeva_modCP_submissions_approve()
{
	global $user_info, $context, $galurl;

	$items = isset($_POST['items']) && isset($_POST['submit_aeva']) && is_array($_POST['items']) ? $_POST['items'] : array((int) @$_REQUEST['in']);
	$type = $_REQUEST['type'];

	if (isset($_REQUEST['xml']))
		header('Content-Type: text/xml; charset=ISO-8859-1');

	if (!in_array($type, array('albums', 'items', 'coms')))
	{
		if (isset($_REQUEST['xml']))
		{
			echo '<?xml version="1.0" encoding="ISO-8859-1"?', '>
<ret>
	<id>', $items[0], '</id>
	<succ>false</succ>
</ret>';
			die;
		}
		return false;
	}

	// Approving an album?
	if ($type == 'albums')
	{
		wesql::query('
			UPDATE {db_prefix}media_albums
			SET approved = 1
			WHERE id_album IN ({array_int:albums})',
			array('albums' => $items)
		);

		// Log it
		$request = wesql::query('
			SELECT id_album, name
			FROM {db_prefix}media_albums
			WHERE id_album IN ({array_int:albums})',
			array('albums' => $items)
		);

		while (list ($id, $name) = wesql::fetch_row($request))
		{
			$opts = array(
				'type' => 'approval',
				'subtype' => 'approved',
				'action_on' => array(
					'id' => $id,
					'name' => $name,
				),
				'action_by' => array(
					'id' => $user_info['id'],
					'name' => $user_info['name'],
				),
				'extra_info' => array(
					'val8' => 'album',
				),
			);
			aeva_logModAction($opts);
			aeva_increaseSettings('total_albums');
			aeva_increaseSettings('num_unapproved_albums', -1);
		}
		wesql::free_result($request);
	}
	// Maybe a item?
	elseif ($type == 'items')
	{
		// Fetch some data
		$request = wesql::query('
			SELECT m.album_id, a.id_last_media, m.id_media, m.title, m.id_member
			FROM {db_prefix}media_items AS m
				INNER JOIN {db_prefix}media_albums AS a ON (a.id_album = m.album_id)
			WHERE m.id_media IN ({array_int:id})
			AND m.approved = 0',
			array('id' => $items)
		);

		while (list ($album, $id_last_media, $id, $name, $id_member) = wesql::fetch_row($request))
		{
			wesql::query('
				UPDATE {db_prefix}media_items
				SET approved = 1
				WHERE id_media = {int:id}',
				array('id' => $id)
			);

			// Update the album's stats
			wesql::query('
				UPDATE {db_prefix}media_albums
				SET num_items = num_items + 1' . ($id_last_media < $id ? ', id_last_media = {int:id}' : '') . '
				WHERE id_album = {int:album}',
				array(
					'album' => $album,
					'id' => $id,
				)
			);

			// Update the uploader's stats
			wesql::query('
				UPDATE {db_prefix}members
				SET media_items = media_items + 1
				WHERE id_member = {int:member}',
				array('member' => $id_member)
			);

			// log it
			$opts = array(
				'type' => 'approval',
				'subtype' => 'approved',
				'action_on' => array(
					'id' => $id,
					'name' => $name,
				),
				'action_by' => array(
					'id' => $user_info['id'],
					'name' => $user_info['name'],
				),
				'extra_info' => array(
					'val8' => 'item',
				),
			);
			aeva_logModAction($opts);
			aeva_increaseSettings('total_items');
			aeva_increaseSettings('num_unapproved_items', -1);
		}
		wesql::free_result($request);
	}
	// It has to be a comment then!
	else
	{
		// Fetch the media id -- we need to check for moderator/admin rights
		$request = wesql::query('
			SELECT c.id_media, m.id_last_comment, c.id_comment, m.title, a.album_of, c.id_member
			FROM {db_prefix}media_comments AS c
				INNER JOIN {db_prefix}media_items AS m ON (m.id_media = c.id_media)
				INNER JOIN {db_prefix}media_albums AS a ON (a.id_album = c.id_album)
			WHERE c.id_comment IN ({array_int:id})
			AND c.approved = 0',
			array('id' => $items)
		);

		while (list ($item, $id_last_comment, $id, $name, $owner, $id_member) = wesql::fetch_row($request))
		{
			// Approve it
			wesql::query('
				UPDATE {db_prefix}media_comments
				SET approved = 1
				WHERE id_comment = {int:id}',
				array('id' => $id)
			);

			// Update the item's stats
			wesql::query('
				UPDATE {db_prefix}media_items
				SET num_comments = num_comments + 1' . ($id_last_comment < $id ? ', id_last_comment = {int:id}' : '') . '
				WHERE id_media = {int:media}',
				array(
					'id' => $id,
					'media' => $item,
				)
			);

			// Update the uploader's stats
			wesql::query('
				UPDATE {db_prefix}members
				SET media_comments = media_comments + 1
				WHERE id_member = {int:member}',
				array('member' => $id_member)
			);

			$opts = array(
				'type' => 'approval',
				'subtype' => 'approved',
				'action_on' => array(
					'id' => $id,
					'name' => $name,
				),
				'action_by' => array(
					'id' => $user_info['id'],
					'name' => $user_info['name'],
				),
				'extra_info' => array(
					'val8' => 'comment',
					'val9' => $item,
				),
			);
			aeva_logModAction($opts);
			aeva_increaseSettings('total_comments');
			aeva_increaseSettings('num_unapproved_comments', -1);
		}
		wesql::free_result($request);
	}

	// Everything done :)
	if (isset($_REQUEST['xml']))
	{
		echo '<?xml version="1.0" encoding="ISO-8859-1"?', '>
<ret>
	<id>', $items[0], '</id>
	<succ>true</succ>
</ret>';
		die;
	}

	redirectexit($galurl . 'area=moderate;sa=submissions;filter=' . $type);
}

// Basically deletes a item or a comment.
function aeva_modCP_submissions_delete()
{
	global $user_info, $context, $galurl;

	$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';

	$request = wesql::query($type == 'albums' ? '
		SELECT id_album, name AS title
		FROM {db_prefix}media_albums
		WHERE id_album IN ({array_int:id})' : '
		SELECT m.id_media, m.title' . ($type == 'coms' ? ', c.id_comment' : '') . '
		FROM {db_prefix}media_items AS m
			' . ($type == 'coms' ? 'INNER JOIN {db_prefix}media_comments AS c ON (c.id_media = m.id_media)' : '') . '
		WHERE ' . ($type == 'coms' ? 'c.id_comment' : 'm.id_media') . ' IN ({array_int:id})',
		array(
			'id' => isset($_POST['items']) && isset($_POST['submit_aeva']) && is_array($_POST['items']) ? $_POST['items'] : array((int) $_REQUEST['in']),
		)
	);

	while ($row = wesql::fetch_assoc($request))
	{
		if ($type == 'coms')
			aeva_deleteComments($row['id_comment'], false);
		elseif ($type == 'items')
			aeva_deleteItems($row['id_media'], true, false);
		elseif ($type == 'albums')
		{
			loadSource('media/Aeva-Gallery2');
			aeva_deleteAlbum($row['id_album'], true);
		}

		$opts = array(
			'type' => 'approval',
			'subtype' => 'delete',
			'action_on' => array(
				'id' => $row['id_' . ($type == 'albums' ? 'album' : ($type == 'coms' ? 'comment' : 'media'))],
				'name' => $row['title'],
			),
			'action_by' => array(
				'id' => $user_info['id'],
				'name' => $user_info['name'],
			),
			'extra_info' => array(
				'val8' => $type == 'albums' ? 'album' : ($type == 'coms' ? 'comment' : 'item'),
			),
		);
		aeva_logModAction($opts);
	}

	if (isset($_REQUEST['xml']))
	{
		header('Content-Type: text/xml; charset=ISO-8859-1');
		echo '<?xml version="1.0" encoding="ISO-8859-1"?', '>
<ret>
	<id>', (int) $_REQUEST['in'], '</id>
	<succ>true</succ>
</ret>';
		die;
	}

	redirectexit($galurl . 'area=moderate;sa=submissions;filter=' . $type);
}

// Handles the reported items page
function aeva_modCP_reports()
{
	global $context, $scripturl, $galurl, $txt, $amSettings;

	// DOs
	$do = array(
		'delete' => array('aeva_modCP_reports_delete', false),
		'deleteitem' => array('aeva_modCP_reports_deleteItem', false),
	);
	if (isset($_REQUEST['do'], $do[$_REQUEST['do']]))
		if ($do[$_REQUEST['do']][1])
			return $do[$_REQUEST['do']][0]();
		else
			$do[$_REQUEST['do']][0]();

	$type = isset($_REQUEST['comments']) ? 'comment' : 'item';

	$txt['aeva2_items'] = $txt['media_items'] . ' (' . $amSettings['num_reported_items'] . ')';
	$txt['aeva2_comments'] = $txt['media_comments'] . ' (' . $amSettings['num_reported_comments'] . ')';

	// Header tabs
	$context['aeva_header']['subtabs'] = array(
		'items' => array('title' => 'aeva2_items', 'url' => $scripturl.'?action=media;area=moderate;sa=reports;items;' . $context['session_query'], 'active' => $type == 'item'),
		'comments' => array('title' => 'aeva2_comments', 'url' => $scripturl.'?action=media;area=moderate;sa=reports;comments;' . $context['session_query'], 'active' => $type == 'comment'),
	);

	// Load all the reports
	$request = wesql::query('
		SELECT v.id, v.val1, v.val2, v.val3, v.val4, mem.real_name, m.title, m.id_member, m.time_added, mem2.real_name AS real_name2, m.id_media
		FROM {db_prefix}media_variables AS v
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = v.val1)' . ($type == 'comment' ? '
			INNER JOIN {db_prefix}media_comments AS c ON (c.id_comment = v.val4)
			INNER JOIN {db_prefix}media_items AS m ON (m.id_media = c.id_media)' : '
			INNER JOIN {db_prefix}media_items AS m ON (m.id_media = v.val4)') . '
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = m.id_member)
		WHERE v.type = {string:type}
		ORDER BY v.val3 ASC
		LIMIT {int:start}, {int:per_page}',
		array(
			'type' => $type.'_report',
			'start' => isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0,
			'per_page' => 20,
		)
	);
	$context['aeva_reports'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$context['aeva_reports'][] = array(
			'id_report' => $row['id'],
			'id' => $row['val4'],
			'id2' => $row['id_media'],
			'name' => $row['title'],
			'reported_on' => timeformat($row['val2']),
			'reason' => parse_bbc($row['val3']),
			'reported_by' => array(
				'id' => $row['val1'],
				'name' => $row['real_name'],
			),
			'posted_by' => array(
				'id' => $row['id_member'],
				'name' => $row['real_name2'],
			),
			'posted_on' => timeformat($row['time_added']),
			'title' => $row['title'],
		);
	}
	wesql::free_result($request);

	// page index
	$_REQUEST['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$context['aeva_page_index'] = template_page_index($scripturl . '?action=media;area=moderate;sa=reports;' . (isset($_REQUEST['comments']) ? 'comments' : '')
		. $context['session_query'], $_REQUEST['start'], $amSettings[$type == 'comment' ? 'num_reported_comments' : 'num_reported_items'], 20);
	$context['aeva_report_type'] = $type;

	loadTemplate('ManageMedia');
	wetem::load('aeva_admin_reports');
	// HTML headers
	$context['header'] .= '
	<script src="' . add_js_file('scripts/mediadmin.js', false, true) . '"></script>';
}

// Deletes a report
function aeva_modCP_reports_delete()
{
	global $user_info;

	// Fetch the report
	$request = wesql::query('
		SELECT id, val4, val5, type
		FROM {db_prefix}media_variables
		WHERE id = {int:report}
		AND (type = {string:item} OR type = {string:comment})',
		array('report' => (int) $_GET['in'], 'item' => 'item_report', 'comment' => 'comment_report')
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('media_report_not_found');
	$report = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// Let's remove it!
	wesql::query('
		DELETE FROM {db_prefix}media_variables
		WHERE id = {int:id}',
		array('id' => $report['id'])
	);

	// Update the settings
	aeva_increaseSettings($report['type'] == 'comment_report' ? 'num_reported_comments' : 'num_reported_items', -1);

	// Log the action
	$opts = array(
		'type' => 'report',
		'subtype' => 'delete_report',
		'action_on' => array(
			'id' => $report['val4'],
			'name' => $report['val5'],
		),
		'action_by' => array(
			'id' => $user_info['id'],
			'name' => $user_info['name'],
		),
		'extra_info' => array(
			'val8' => $report['type'],
		),
	);
	aeva_logModAction($opts);
}

// Deletes a reported item
function aeva_modCP_reports_deleteItem()
{
	global $user_info;

	// Get the item which we need to delete
	$request = wesql::query('
		SELECT id, val4, val5, type
		FROM {db_prefix}media_variables
		WHERE id = {int:report}
		AND (type = {string:item} OR type = {string:comment})',
		array('report' => (int) $_GET['in'], 'item' => 'item_report', 'comment' => 'comment_report')
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('media_report_not_found');
	$report = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// Delete the item
	if ($report['type'] == 'comment_report')
		aeva_deleteComments($report['val5'], false);
	else
		aeva_deleteItems($report['val4'], true, false);

	// Log it
	$opts = array(
		'type' => 'report',
		'subtype' => 'delete_item',
		'action_on' => array(
			'id' => $report['val4'],
			'name' => $report['val5'],
		),
		'action_by' => array(
			'id' => $user_info['id'],
			'name' => $user_info['name'],
		),
		'extra_info' => array(
			'val8' => $report['type'],
		),
	);
	aeva_logModAction($opts);
}

// Handles the moderation log
function aeva_modCP_modLog()
{
	global $context, $scripturl, $galurl, $txt, $amSettings;

	// Deleting something?
	if (isset($_POST['delete']) && !empty($_POST['delete']))
	{
		$logs = is_array($_POST['to_delete']) ? $_POST['to_delete'] : array($_POST['to_delete']);
		if (empty($logs))
			break;
		wesql::query('
			DELETE FROM {db_prefix}media_variables
			WHERE id IN ({array_int:logs})
			AND type = {string:type}', array('type' => 'mod_log', 'logs' => $logs)
		);
	}
	if (isset($_POST['delete_all']))
		wesql::query('
			DELETE FROM {db_prefix}media_variables
			WHERE type = {string:type}', array('type' => 'mod_log')
		);

	// Quick search by member?
	if (isset($_POST['qsearch_mem']) && !empty($_POST['qsearch_mem']))
	{
		$request = wesql::query('
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE member_name = {string:name} OR real_name = {string:name}
			LIMIT 1', array('name' => $_POST['qsearch_mem'])
		);
		if (wesql::num_rows($request) > 0)
			list ($id_member, $member_name) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	if (isset($_REQUEST['qsearch']) && !isset($id_member))
	{
		$request = wesql::query('
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}
			LIMIT 1', array('id_member' => (int) $_REQUEST['qsearch']));
		if (wesql::num_rows($request) > 0)
			list ($id_member, $member_name) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	$id_member = empty($id_member) ? 0 : $id_member;
	$member_name = empty($member_name) ? '' : $member_name;

	// Get the total logs
	$request = wesql::query('
		SELECT COUNT(id)
		FROM {db_prefix}media_variables
		WHERE type = {string:type}' . (!empty($id_member) ? '
		AND val5 = {int:id_member}' : ''),
		array(
			'type' => 'mod_log',
			'id_member' => (int) $id_member,
		)
	);
	list ($total_logs) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Get the logs now
	$request = wesql::query('
		SELECT *
		FROM {db_prefix}media_variables
		WHERE type = {string:type}' . (!empty($id_member) ? '
		AND val5 = {int:id_member}' : '') . '
		ORDER BY id DESC
		LIMIT {int:start}, {int:limit}',
		array(
			'type' => 'mod_log',
			'id_member' => (int) $id_member,
			'start' => isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0,
			'limit' => 30
		)
	);
	$context['aeva_logs'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Get their action href and type
		$href = '';
		$type = '';
		switch ($row['val1'])
		{
			case 'approval':
				switch ($row['val8'])
				{
					case 'item':
						if ($row['val2'] == 'approved')
							$text = sprintf($txt['media_admin_modlog_approval_item'], $galurl.'sa=item;in='.$row['val3'], $row['val4']);
						elseif ($row['val2'] == 'unapproved')
							$text = sprintf($txt['media_admin_modlog_approval_ua_item'], $galurl . 'sa=item;in='.$row['val3'], $row['val4']);
						else
							$text = sprintf($txt['media_admin_modlog_approval_del_item'], $row['val4']);
					break;
					case 'comment':
						if ($row['val2'] == 'approved')
							$text = sprintf($txt['media_admin_modlog_approval_com'], $galurl.'sa=item;in='.$row['val9'].'#com'.$row['val3'], $row['val4']);
						else
							$text = sprintf($txt['media_admin_modlog_approval_del_com'], $row['val4']);
					break;
					case 'album':
						if ($row['val2'] == 'approved')
							$text = sprintf($txt['media_admin_modlog_approval_album'], $galurl.'sa=album;in='.$row['val3'], $row['val4']);
						else
							$text = sprintf($txt['media_admin_modlog_approval_del_album'], $row['val4']);
					break;
				}
			break;
			case 'delete':
				$text = sprintf($txt['media_admin_modlog_delete_'.$row['val2']], $row['val4']);
			break;
			case 'report':
				$text = sprintf($txt['media_admin_modlog_'.$row['val2'].'_'.$row['val8']], $row['val3']);
			break;
			case 'ban':
				$text = sprintf($txt['media_admin_modlog_ban_'.$row['val2']], $scripturl . '?action=profile;u=' . $row['val3'], $row['val4']);
			break;
			case 'prune':
				$text = sprintf($txt['media_admin_modlog_prune_'.$row['val2']], $row['val8']);
			break;
			case 'move';
				$album1 = explode(',', $row['val8']);
				$album2 = explode(',', $row['val9']);
				$text = sprintf($txt['media_admin_modlog_move'], $galurl.'sa=item;in='.$row['val3'], $row['val4'], $galurl.'sa=album;in='.$album2[0], $album2[1], $galurl.'sa=album;in='.$album1[0], $album1[1]);
			break;
		}
		$context['aeva_logs'][] = array(
			'id' => $row['id'],
			'text' => $text,
			'action_by_href' => $scripturl.'?action=profile;u='.$row['val5'],
			'action_by_name' => $row['val6'],
			'time' => timeformat($row['val7']),
		);
	}
	wesql::free_result($request);
	$total_logs = (int) $total_logs;

	// Page index
	$_REQUEST['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$context['aeva_page_index'] = template_page_index($scripturl . '?action=media;area=moderate;sa=modlog;' . (!empty($id_member) ? 'qsearch=' . $id_member . ';' : '')
		. $context['session_query'], $_REQUEST['start'], $total_logs, 30);

	if (!empty($id_member))
		$context['aeva_filter'] = sprintf($txt['media_admin_modlog_filter'], $scripturl . '?action=profile;u=' . $id_member, $member_name);

	loadTemplate('ManageMedia');
	wetem::load('aeva_admin_modlog');

	// HTML headers
	$context['header'] .= '
	<script src="' . add_js_file('scripts/mediadmin.js', false, true) . '"></script>';
}

?>