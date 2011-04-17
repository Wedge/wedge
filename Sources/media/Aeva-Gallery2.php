<?php
/****************************************************************
* Aeva Media													*
* © Noisen.com & SMF-Media.com									*
*****************************************************************
* Aeva-Gallery2.php - more gallery-related actions				*
*****************************************************************
* Users of this software are bound by the terms of the			*
* Aeva Media license. You can view it in the license_am.txt		*
* file, or online at http://noisen.com/license-am2.php			*
*																*
* For support and updates, go to http://aeva.noisen.com			*
****************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

/*
	This holds the secondary sub-actions in Aeva Media

	void aeva_moveItems()
		- Moves one or several items

	void aeva_unseen()
		- Shows the unseen items page
		- Also handles Mark as Seen

	void aeva_mgSearch()
		- Handles the search/search results pages

	void aeva_listAlbums()
		- Shows all the albums, sorted by owner

	void aeva_mgStats()
		- Shows the statistics page

	void aeva_albumCP()
		- Shows the control panel for one's albums
		- Allows users to add/edit/view their albums

	void aeva_editAlbum()
		- Edits an album...

	void aeva_addAlbum(bool is_admin, bool is_add)
		- A universal function to add/edit albums
		- Used by all the album add/edit functions out there
		- Use featured to specify whether the album is featured or not
		- Use is_add to specify whether we are adding or editing the album

	void aeva_moveAlbum()
		- Oh, come on...

	void aeva_deleteAlbum(int id, bool from_approval)
		- Delete an album, its items and comments. Be careful!
		- If called from the approval area (from_approval = true), an album ID is provided

	void aeva_massUpload()
		- Shows the mass upload page
		- Also handles adding of items sent via the page as well as giving a return

	void aeva_massUploadFinish()
		- Updates titles sent after a mass upload

	void aeva_profileSummary(int memID)
		- Prepares the summary page for gallery user profiles

	void aeva_profileItems(int memID)
		- Retrieves all items for gallery user profiles

	void aeva_profileComments(int memID)
		- Retrieves all comments for gallery user profiles

	void aeva_profileVotes(int memID)
		- Handles viewing the rates for the specific member

	void aeva_whoRatedWhat()
		- Handles the Who Rated What page

	void aeva_tagHelper()
		- Shows the complete popup help page for the [media] BBCode tag

	void aeva_massDownload()
		- Handles the initialisation of multiple downloads, asks for user inputs

	void aeva_massDownloadCreate()
		- Creates the zip file for the supplied items

	void aeva_massDownloadSend()
		- Sends the file to the user once created
*/

function aeva_moveItems()
{
	// Handles moving of items from one album to another
	global $context, $scripturl, $galurl, $txt, $user_info, $amSettings;

	// Load the item's data
	$ids = !empty($_POST['ids']) ? $_POST['ids'] : (array) (!empty($_GET['in']) ? (int) $_GET['in'] : 0);

	$request = wesql::query('
		SELECT
			m.id_media, m.title, m.id_member, IFNULL(mem.real_name, m.member_name) AS member_name, a.name, a.id_album, m.id_file, m.type,
			m.id_thumb, a.directory AS album_dir, f.filename, f.directory AS file_dir, t.filename AS thumb_file,
			t.directory AS thumb_dir, m.id_preview, p.filename AS preview_file, p.directory AS preview_dir,
			(a.icon = m.id_thumb) AS is_album_icon, (a.bigicon = m.id_preview) AS is_album_bigicon, a.master
		FROM {db_prefix}media_items AS m
			INNER JOIN {db_prefix}media_albums AS a ON (a.id_album = m.album_id)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}media_files AS f ON (f.id_file = m.id_file)
			LEFT JOIN {db_prefix}media_files AS t ON (t.id_file = m.id_thumb)
			LEFT JOIN {db_prefix}media_files AS p ON (p.id_file = m.id_preview)
		WHERE m.id_media '. (is_array($ids) ? 'IN ({array_int:media})' : '= {int:media}'),
		array('media' => array_values($ids))
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('media_item_not_found', !empty($amSettings['log_access_errors']));
	$items = array();
	$can_moderate = allowedTo('media_moderate');
	$can_edit_own = allowedTo('media_edit_own_item');
	while ($row = wesql::fetch_assoc($request))
		if ($can_moderate || ($can_edit_own && $row['id_member'] == $user_info['id']))
			$items[] = $row;
	wesql::free_result($request);
	if (empty($items))
		fatal_lang_error('media_edit_denied');

	// Let's see what albums they can move to
	$types = $ids = array();
	$moving_link = '';
	foreach ($items as $item)
	{
		$ids[] = $item['id_media'];
		$types[$item['type']] = 'add_' . $item['type'] . 's';
		$moving_link .= '<a href="' . $galurl . 'sa=item;in=' . $item['id_media'] . '">' . $item['title'] . '</a>, ';
	}
	$moving_link = substr($moving_link, 0, -2);
	$allowed_albums = albumsAllowedTo($types, true);
	aeva_getAlbums(allowedTo('media_moderate') ? '' : (empty($allowed_albums) ? '1=0' : 'a.id_album IN (' . implode(',', array_keys($allowed_albums)) . ')'), 1, false, 'a.album_of, a.child_level, a.a_order');
	$albums = array();
	$sep = $prev_owner = -1;
	foreach ($context['aeva_album_list'] as $k => $list)
	{
		$new_owner = $context['aeva_albums'][$list]['owner']['id'];
		if ($prev_owner != $new_owner)
		{
			if ($prev_owner > -1)
				$albums['sep' . ++$sep] = array('', false, '');
			$albums['sep' . ++$sep] = array($context['aeva_albums'][$list]['owner']['name'], false, 'begin');
			$prev_owner = $new_owner;
		}
		$albums[$list] = array(str_repeat('-', $context['aeva_albums'][$list]['child_level']).' '.$context['aeva_albums'][$list]['name'], false, $list == $items[0]['id_album'] ? ' disabled' : null);
	}
	$albums['sep' . ++$sep] = array('', false, '');

	// Load up the form
	$context['aeva_form_headers'] = array(
		array($txt['media_moving_item'] . ': ' . $moving_link),
		array($txt['media_album'] . ': <a href="' . $galurl . 'sa=album;in=' . $items[0]['id_album'] . '">' . $items[0]['name'] . '</a>'),
	);
	$context['aeva_form_url'] = $galurl . 'sa=move';
	$context['aeva_form'] = array(
		'album_to_move' => array(
			'type' => 'select',
			'fieldname' => 'album',
			'label' => $txt['media_album_to_move'],
			'options' => $albums,
		),
	);
	foreach ($items as $item)
		$context['aeva_form']['ids_' . $item['id_media']] = array(
			'fieldname' => 'ids[' . $item['id_media'] . ']',
			'value' => $item['id_media'],
			'skip' => true,
		);

	// Moving?
	if (isset($_POST['submit_aeva']))
	{
		// Let's make sure it is in a correct album
		$album_id = (int) $_POST['album'];
		if (!isset($albums[$album_id]))
			fatal_lang_error('media_invalid_album');
		$perm = @$allowed_albums[$album_id];

		// Everything verified, let's move them! First we will move the files, then the comments,
		// then we'll update the album's stats and then we'll move the actual items... Phew!
		foreach ($items as $i)
		{
			$main_file = $amSettings['data_dir_path'].'/'.$i['file_dir'].'/'.aeva_getEncryptedFilename($i['filename'], $i['id_file']);

			// If file type is not supported by target album, or it's larger than the allowed quota, skip it!
			// If user is a moderator, they probably know what they're doing -- allow them anything.
			if (!$can_moderate && !isset($perm['perms']['add_' . $i['type'] . 's']))
				continue;
			if (!$can_moderate && isset($perm['quotas'][$i['type']]) && file_exists($main_file) && (filesize($main_file) / 1024) > $perm['quotas'][$i['type']])
				continue;

			// Files
			if ($i['id_thumb'] > 4 && file_exists($amSettings['data_dir_path'].'/'.$i['thumb_dir'].'/'.aeva_getEncryptedFilename($i['thumb_file'], $i['id_thumb'], true)))
			{
				$new_dir = aeva_getSuitableDir($album_id);
				$_new_dir = substr($new_dir, strlen($amSettings['data_dir_path']) + 1);
				$success = rename(
					$amSettings['data_dir_path'].'/'.$i['thumb_dir'].'/'.aeva_getEncryptedFilename($i['thumb_file'], $i['id_thumb'], true),
					$new_dir.'/'.aeva_getEncryptedFilename($i['thumb_file'], $i['id_thumb'], true)
				);
				if (!$success)
					fatal_lang_error('media_filemove_failed');
				wesql::query('
					UPDATE {db_prefix}media_files
					SET id_album = {int:album}, directory = {string:dir}
					WHERE id_file = {int:file}',
					array('file' => $i['id_thumb'], 'dir' => $_new_dir, 'album' => $album_id)
				);
			}
			if ($i['id_preview'] > 4 && file_exists($amSettings['data_dir_path'].'/'.$i['preview_dir'].'/'.aeva_getEncryptedFilename($i['preview_file'], $i['id_preview'])))
			{
				$new_dir = aeva_getSuitableDir($album_id);
				$_new_dir = substr($new_dir, strlen($amSettings['data_dir_path']) + 1);
				$success = rename(
					$amSettings['data_dir_path'].'/'.$i['preview_dir'].'/'.aeva_getEncryptedFilename($i['preview_file'],
					$i['id_preview']), $new_dir.'/'.aeva_getEncryptedFilename($i['preview_file'], $i['id_preview'])
				);
				if (!$success)
					fatal_lang_error('media_filemove_failed');
				wesql::query('
					UPDATE {db_prefix}media_files
					SET id_album = {int:album}, directory = {string:dir}
					WHERE id_file = {int:file}',
					array('file' => $i['id_preview'], 'dir' => $_new_dir, 'album' => $album_id)
				);
			}
			if ($i['id_file'] != 0 && file_exists($main_file))
			{
				$new_dir = aeva_getSuitableDir($album_id);
				$_new_dir = substr($new_dir, strlen($amSettings['data_dir_path']) + 1);
				$success = rename(
					$amSettings['data_dir_path'].'/'.$i['file_dir'].'/'.aeva_getEncryptedFilename($i['filename'],
					$i['id_file']), $new_dir.'/'.aeva_getEncryptedFilename($i['filename'], $i['id_file'])
				);
				if (!$success)
					fatal_lang_error('media_filemove_failed');
				wesql::query('
					UPDATE {db_prefix}media_files
					SET id_album = {int:album}, directory = {string:dir}
					WHERE id_file = {int:file}',
					array('file' => $i['id_file'], 'dir' => $_new_dir, 'album' => $album_id)
				);
			}

			// Update the comments!
			wesql::query('
				UPDATE {db_prefix}media_comments
				SET id_album = {int:album}
				WHERE id_media = {int:media}',
				array('album' => $album_id, 'media' => $i['id_media'])
			);

			// Update the album stats!
			wesql::query('
				UPDATE {db_prefix}media_albums
				SET num_items = num_items - 1' . (!empty($i['is_album_icon']) ? ', icon = 4' : '') . (!empty($i['is_album_bigicon']) ? ', bigicon = 0' : '') . '
				WHERE id_album = {int:album}',
				array('album' => $i['id_album'])
			);
			wesql::query('
				UPDATE {db_prefix}media_albums
				SET num_items = num_items + 1
				WHERE id_album = {int:album}',
				array('album' => $album_id)
			);

			// OK, done with everything, move the actual item!
			wesql::query('
				UPDATE {db_prefix}media_items
				SET album_id = {int:album}
				WHERE id_media = {int:media}',
				array('album' => $album_id, 'media' => $i['id_media'])
			);
		}

		// Log it -- we don't need to log more than ONE item being moved... Do we? :-/
		$opts = array(
			'type' => 'move',
			'action_on' => array(
				'id' => $items[0]['id_media'],
				'name' => $items[0]['name'],
			),
			'action_by' => array(
				'id' => $user_info['id'],
				'name' => $user_info['name'],
			),
			'extra_info' => array(
				'val8' => $album_id.','.$context['aeva_albums'][$album_id]['name'],
				'val9' => $items[0]['id_album'].','.$items[0]['name'],
			),
		);
		aeva_logModAction($opts);

		// Redirect back to previous album
		redirectexit($galurl.'sa=album;in='.$items[0]['id_album']);
	}

	// Linktree
	$albums = array_reverse(aeva_getAlbumParents($items[0]['id_album'], $items[0]['master']));
	foreach ($albums as $a)
		add_linktree($galurl . 'sa=album;in=' . $a['id'], $a['name']);
	add_linktree($galurl . 'sa=item;in=' . $items[0]['id_media'], $items[0]['title']);
	add_linktree($galurl . 'sa=move;in=' . $items[0]['id_media'], $txt['media_moving']);

	loadSubTemplate('aeva_form');
}

function aeva_unseen()
{
	// Handles the viewing of unseen files
	global $galurl, $scripturl, $amSettings, $txt, $user_info, $context;

	is_not_guest();
	$start = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
	$album = isset($_REQUEST['album']) ? 'AND a.id_album = ' . (int) $_REQUEST['album'] : '';
	$per_page = max(1, min(200, (int) $amSettings['num_items_per_page']));

	if (isset($_REQUEST['markseen']))
	{
		checkSession('get');
		aeva_markAllSeen();
		redirectexit($galurl.'sa=unseen');
	}

	if (!empty($_REQUEST['pageseen']))
	{
		checkSession('get');
		$pageseen = explode(',', $_REQUEST['pageseen']);
		foreach ($pageseen as $item)
			if ((int) $item > 0)
				media_markSeen((int) $item, 'force_insert');
		media_resetUnseen($user_info['id']);
	}

	// Get the total items to show
	$request = wesql::query('
		SELECT COUNT(m.id_media)
		FROM {db_prefix}media_items AS m
			INNER JOIN {db_prefix}media_albums AS a ON (m.album_id = a.id_album)
			LEFT JOIN {db_prefix}media_log_media AS lm ON (lm.id_media = m.id_media AND lm.id_member = {int:user})
			LEFT JOIN {db_prefix}media_log_media AS lm_all ON (lm_all.id_media = 0 AND lm_all.id_member = {int:user})
		WHERE {query_see_album}
		AND IFNULL(lm.time, IFNULL(lm_all.time, 0)) < m.log_last_access_time
		{raw:album}' . (!allowedTo('media_moderate') ? '
		AND m.approved = 1' : '') . '
		LIMIT 1', array('album' => $album, 'user' => $user_info['id'])
	);
	list ($total_items) = wesql::fetch_row($request);
	wesql::free_result($request);

	if (empty($total_items) && !$user_info['is_guest'])
	{
		// Quick test to see if we should optimize the log_media table...
		$request = wesql::query('
			SELECT id_media FROM {db_prefix}media_log_media WHERE id_media > 0 AND id_member = {int:user} LIMIT 1',
			array('user' => $user_info['id'])
		);
		list ($remaining) = wesql::fetch_row($request);
		wesql::free_result($request);

		if (empty($remaining))
			aeva_markAllSeen();
	}

	// Now get the items!
	$context['aeva_items'] = aeva_fillMediaArray(wesql::query('
		SELECT
			m.id_media, m.title, m.views, m.num_comments, m.id_member, m.time_added, m.approved, m.views, m.type, m.embed_url,
			IFNULL(mem.real_name, m.member_name) AS member_name, a.id_album, a.name, f.width, f.height, m.voters, m.weighted AS rating,
			m.id_thumb, f.directory AS thumb_dir, f.filename AS thumb_file, f.height AS thumb_height, f.transparency, (pt.width && pt.height) AS has_preview
		FROM {db_prefix}media_items AS m
			INNER JOIN {db_prefix}media_albums AS a ON (m.album_id = a.id_album)
			LEFT JOIN {db_prefix}media_files AS f ON (f.id_file = m.id_thumb)
			LEFT JOIN {db_prefix}media_files AS pt ON (pt.id_file = m.id_preview)
			LEFT JOIN {db_prefix}media_log_media AS lm ON (lm.id_media = m.id_media AND lm.id_member = {int:user})
			LEFT JOIN {db_prefix}media_log_media AS lm_all ON (lm_all.id_media = 0 AND lm_all.id_member = {int:user})
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE {query_see_album}
		AND IFNULL(lm.time, IFNULL(lm_all.time, 0)) < m.log_last_access_time
		{raw:album}' . (!allowedTo('media_moderate') ? '
		AND m.approved = 1' : '') . '
		ORDER BY m.log_last_access_time DESC
		LIMIT {int:start},{int:per_page}',
		array('start' => $start, 'per_page' => $per_page, 'album' => $album, 'user' => $user_info['id'])
	));

	$comment_list = array();
	foreach ($context['aeva_items'] as $id => $dummy)
	{
		$context['aeva_items'][$id]['is_new'] = true;
		$comment_list[] = $id;
	}

	if (!empty($comment_list))
	{
		// Get new comments per item
		$request = wesql::query('
			SELECT COUNT(c.id_comment) AS co, c.id_media
			FROM {db_prefix}media_comments AS c
				LEFT JOIN {db_prefix}media_log_media AS lm ON (lm.id_media = c.id_media AND lm.id_member = {int:user})
				LEFT JOIN {db_prefix}media_log_media AS lm_all ON (lm_all.id_media = 0 AND lm_all.id_member = {int:user})
			WHERE c.id_media IN (' . implode(',', $comment_list) . ')
			AND IFNULL(lm.time, IFNULL(lm_all.time, 0)) < c.posted_on
			GROUP BY c.id_media',
			array('user' => $user_info['id'])
		);
		while ($row = wesql::fetch_assoc($request))
			$context['aeva_items'][$row['id_media']]['new_comments'] = $row['co'];
		wesql::free_result($request);
	}

	$context['aeva_page_index'] = constructPageIndex($galurl . 'sa=unseen' . (isset($_REQUEST['album']) ? ';album=' . (int) $_REQUEST['album'] : ''), $start, $total_items, $per_page);

	// Sub template and page title
	$context['aeva_header']['data']['title'] = $txt['media_viewing_unseen'];
	$context['page_title'] = $txt['media_viewing_unseen'];
	loadSubTemplate('aeva_unseen');
	$context['aeva_current'] = 'unseen';
	add_linktree($galurl.'sa=unseen',$txt['media_viewing_unseen']);
}

// Handles searching of items
function aeva_mgSearch()
{
	global $galurl, $txt, $amSettings, $user_info, $context, $scripturl;

	// Let's see what all albums they can search in
	aeva_getAlbums();
	$albums = array();
	foreach ($context['aeva_album_list'] as $album)
		$albums[$album] = str_repeat('-', $context['aeva_albums'][$album]['child_level']).' '.$context['aeva_albums'][$album]['name'];
	// Search linktree
	add_linktree($galurl.'sa=search', $txt['media_search']);

	// Page title
	$context['page_title'] = $txt['media_search'];

	// Tab data
	$context['aeva_current'] = 'search';

	// Fields
	$context['custom_fields'] = aeva_loadCustomFields(null, array(), 'cf.searchable = 1');

	// Searching?
	if (isset($_REQUEST['search']))
	{
		if (trim($_REQUEST['search']) == '')
			fatal_lang_error('media_search_left_empty', false);

		$joins = array();

		$filters = array(
			'title' => isset($_REQUEST['sch_title']),
			'desc' => isset($_REQUEST['sch_desc']),
			'keywords' => isset($_REQUEST['sch_kw']),
			'alname' => isset($_REQUEST['sch_an']),
			'member' => isset($_REQUEST['sch_mem']) && trim($_REQUEST['sch_mem']) != '' ? westr::htmlspecialchars($_REQUEST['sch_mem']) : false,
			'album' => !empty($_REQUEST['sch_album']) ? (int) $_REQUEST['sch_album'] : false,
			'custom_fields' => !empty($_REQUEST['fields']) && is_array($_REQUEST['fields']) ? $_REQUEST['fields'] : array(),
		);
		$search_query = array();
		$searching_for = '%' . un_htmlspecialchars($_REQUEST['search']) . '%';
		$test = false;

		foreach ($filters as $f)
			if (!empty($f))
				$test = true;

		// Some special stuff for query search via address
		if (isset($_GET['search']) && !isset($_POST['search']) && $test === false)
		{
			$filters['title'] = true;
			$filters['desc'] = true;
			$test = true;
		}

		if ($test === false)
			fatal_lang_error('media_no_search_option_selected', false);

		$enclose = false;
		if ($filters['keywords'])
			$search_query[] = 'm.keywords LIKE {string:search}';
		if ($filters['title'])
			$search_query[] = 'm.title LIKE {string:search}';
		if ($filters['desc'])
			$search_query[] = 'm.description LIKE {string:search}';
		if ($filters['alname'])
			$search_query[] = 'a.name LIKE {string:search}';
		$members_to_filter = array();
		if ($filters['member'])
		{
			$members = explode(',',$filters['member']);
			// Get possible members we're searching for
			$members = strtr(addslashes(westr::htmlspecialchars(stripslashes($filters['member']), ENT_QUOTES)), array('&quot;' => '"'));
			preg_match_all('~"([^"]+)"~', $members, $matches);
			$who = array_merge($matches[1], explode(',', preg_replace('~"([^"]+)"~', '', $members)));
			for ($k = 0, $n = count($who); $k < $n; $k++)
			{
				$who[$k] = trim($who[$k]);
				if (strlen($who[$k]) == 0)
				unset($who[$k]);
			}
			$request = wesql::query('
				SELECT id_member
				FROM {db_prefix}members
				WHERE real_name IN ({string:name}) OR member_name IN ({string:name})',
				array('name' => implode(',',$who))
			);
			while ($row = wesql::fetch_assoc($request))
				$members_to_filter[] = $row['id_member'];

			if (!empty($members_to_filter))
			{
				$enclose = true;
				$search_query_and[] = "m.id_member = {int:mem}";
			}
			else
				fatal_lang_error('media_search_mem_not_found',false);
		}
		if ($filters['album'])
		{
			foreach ($albums as $k => $v)
				if ($k == $filters['album'])
					$found = true;
			if (!isset($found))
				fatal_lang_error('media_invalid_album');

			$enclose = true;
			$search_query_and[] = "m.album_id = {int:album}";
		}

		// Custom fields
		$field_query = array();
		if (!empty($filters['custom_fields']))
		{
			foreach ($filters['custom_fields'] as $k => $v)
			{
				$v = (int) $v;
				if (!in_array($v, array_keys($context['custom_fields'])))
					continue;

				$key = 'cfd' . $v;
				$joins[] = 'LEFT JOIN {db_prefix}media_field_data AS ' . $key . ' ON (' . $key . '.id_field = ' . $v . ' AND ' . $key . '.id_media = m.id_media)';
				$field_query[] = 'fields[]=' . $v;
				$search_query[] = $key . '.value LIKE {string:search}';
			}
		}

		if (empty($search_query))
			fatal_lang_error('media_item_not_found', !empty($amSettings['log_access_errors']));

		// Make the real query
		$query = '';
		if ($enclose)
			$query .= '(';
		$query .= implode(' OR ', $search_query);
		if ($enclose)
			$query .= ') AND ' . implode(' AND ', $search_query_and);

		// Perform the query now
		$context['aeva_items'] = aeva_fillMediaArray(wesql::query('
			SELECT
				m.id_media, m.title, m.id_member, m.views, m.num_comments, m.approved, a.hidden,
				IFNULL(mem.real_name, m.member_name) AS member_name, a.name, a.id_album, m.time_added,
				IFNULL(lm.time, IFNULL(lm_all.time, 0)) < m.log_last_access_time AS is_new, f.width, f.height,
				m.id_thumb, f.directory AS thumb_dir, f.filename AS thumb_file, f.transparency
			FROM {db_prefix}media_items AS m
				INNER JOIN {db_prefix}media_albums AS a ON (m.album_id = a.id_album)
				LEFT JOIN {db_prefix}media_files AS f ON (f.id_file = m.id_thumb)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				LEFT JOIN {db_prefix}media_log_media AS lm ON (lm.id_media = m.id_media AND lm.id_member = {int:user})
				LEFT JOIN {db_prefix}media_log_media AS lm_all ON (lm_all.id_media = 0 AND lm_all.id_member = {int:user})' . (!empty($joins) ? '
				' . implode('
				', $joins) : '') . '
			WHERE {query_see_album_hidden} AND (' . $query . ')' . (!allowedTo('media_moderate') ? '
			AND m.approved = 1' : '') . '
			ORDER BY m.id_media DESC
			LIMIT {int:start}, {int:per_page}',
			array('user' => $user_info['id'], 'mem' => !empty($members_to_filter) ? implode(' OR m.id_member = ',$members_to_filter) : '', 'album' => $filters['album'],
			'search' => $searching_for, 'start' => isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0, 'per_page' => empty($context['current_board']) ? 15 : 30)
		));

		// Pagination query
		$request = wesql::query('
			SELECT COUNT(m.id_media)
			FROM {db_prefix}media_items AS m
				INNER JOIN {db_prefix}media_albums AS a ON (m.album_id = a.id_album)' . (!empty($joins) ? implode('
				', $joins) : '') . '
			WHERE {query_see_album_hidden} AND (' . $query . ')' . (!allowedTo('media_moderate') ? '
			AND m.approved = 1' : ''),
			array(
				'user' => $user_info['id'],
				'mem' => !empty($members_to_filter) ? implode(' OR m.id_member = ',$members_to_filter) : '',
				'album' => $filters['album'],
				'search' => $searching_for
			)
		);
		list ($total_items) = wesql::fetch_row($request);
		$pageindexURL = $scripturl . (!empty($context['current_board']) ? '?board=' . $context['current_board'] . '.0;' : '?') . 'action=media;sa=search;search=' . urlencode($_REQUEST['search']);
		if ($filters['album'])
			$pageindexURL .= ';sch_album=' . $filters['album'];
		if ($filters['title'])
			$pageindexURL .= ';sch_title';
		if ($filters['desc'])
			$pageindexURL .= ';sch_desc';
		if ($filters['keywords'])
			$pageindexURL .= ';sch_kw';
		if ($filters['alname'])
			$pageindexURL .= ';sch_an';
		if ($filters['member'])
			$pageindexURL .= ';sch_mem=' . $filters['member'];
		$pageindexURL .= ';' . implode(';', $field_query);
		$start = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$context['aeva_page_index'] = constructPageIndex($pageindexURL, $start, $total_items, empty($context['current_board']) ? 15 : 30);
		$context['aeva_page_index'] = strpos($context['aeva_page_index'], '<a ') === false ? '' : "\n\t" . '<div class="pagelinks">' . $txt['media_pages'] . ': ' . $context['aeva_page_index'] . '</div>';
		wesql::free_result($request);

		// Sub template
		loadSubTemplate('aeva_search_results');
		$context['aeva_searching_for'] = westr::htmlspecialchars($_REQUEST['search']);
		$context['aeva_total_results'] = $total_items;

		// If we're in a board, means we're viewing a playlist... Then return a playlist.
		if (!empty($context['current_board']))
		{
			$id_list = '';
			foreach ($context['aeva_items'] as $i)
				$id_list .= $i['id'] . ',';

			$context['aeva_foxy_rendered_search'] = aeva_foxy_album(substr($id_list, 0, -1), 'ids');
			if (!empty($context['aeva_page_index']) && strpos($context['aeva_page_index'], '<a') !== false)
				$context['aeva_foxy_rendered_search'] = str_replace('<!-- aeva_page_index -->', '
	<div class="pagelinks page_index clear">
		' . $txt['media_pages'] . ': ' . $context['aeva_page_index'] . '
	</div>', $context['aeva_foxy_rendered_search']);
		}
	}
	else
	{
		loadSubTemplate('aeva_search_searching');
		$context['aeva_albums'] = $albums;
	}
}

function aeva_listAlbums()
{
	// Handles viewing of all the albums out there
	global $txt, $amSettings, $user_info, $scripturl, $galurl, $context;

	// Just load it!
	$start = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

	// We support sorting, but it's not even mentioned on the page... Ain't we smart?
	$sort_by = isset($_REQUEST['name']) ? 'a.name' : 'a.id_album';
	$sort_order = isset($_REQUEST['asc']) ? 'ASC' : 'DESC';
	$per_page = 15;

	// Load all the albums..
	aeva_getAlbums('', 1, true, 'a.album_of ASC, ' . $sort_by . ' ' . $sort_order, $start . ', ' . $per_page, true, true, true);

	$context['aeva_user_albums'] = array();

	foreach ($context['aeva_albums'] as $list)
		$context['aeva_user_albums'][$list['owner']['id']][] = $list;

	// Do another query for pagination
	$request = wesql::query('
		SELECT a.id_album
		FROM {db_prefix}media_albums AS a
		WHERE {query_see_album_nocheck}
			AND a.child_level = 0' . (!allowedTo('media_moderate') ? '
			AND ((a.approved = 1 AND a.hidden = 0) OR a.album_of = ' . $user_info['id'] . ')' : ''),
		array()
	);
	$total_items = wesql::num_rows($request);
	wesql::free_result($request);

	// Construct the page index
	$pageindexURL = $galurl . 'sa=vua';
	if (isset($_REQUEST['name']))
		$pageindexURL .= ';name';
	if (isset($_REQUEST['asc']))
		$pageindexURL .= ';asc';
	$context['aeva_page_index'] = constructPageIndex($pageindexURL, $start, $total_items, $per_page);

	// End this
	loadSubTemplate('aeva_viewUserAlbums');
	add_linktree($galurl . 'sa=vua', $txt['media_albums']);
	$context['aeva_header']['data']['title'] = $txt['media_albums'];
	$context['page_title'] = $txt['media_gallery'];
}

function aeva_mgStats()
{
	// Handles the stat pages
	global $amSettings, $user_info, $context, $txt, $galurl;

	/*******************************************************
	Let's see what all we got at the stats page:
	Normal stats like total comments, total items, total albums, total featured albums, avg items per day, avg comments per day, total item contributors, total comment contributors
	Top 5 uploaders
	Top 5 commentators
	Top 5 albums by num items
	Top 5 albums by num comments
	Top 5 items by views
	Top 5 items by comments
	Top 5 items by rating
	********************************************************/

	// Get the stats
	$days_started = round((time() - $amSettings['installed_on'])/ 86400);
	$context['aeva_stats'] = array();
	$context['aeva_stats'] = array(
		'total_comments' => $amSettings['total_comments'],
		'total_items' => $amSettings['total_items'],
		'total_albums' => $amSettings['total_albums'],
		'avg_items' => $days_started > 0 ? round($amSettings['total_items'] / $days_started, 2) : 0,
		'avg_comments' => $days_started > 0 ? round($amSettings['total_comments'] / $days_started, 2) : 0,
	);

	// Total item contributors
	$request = wesql::query('
		SELECT id_member
		FROM {db_prefix}media_items
		WHERE approved = 1
		GROUP BY id_member',
		array()
	);
	$total_item_contributors = wesql::num_rows($request);
	wesql::free_result($request);

	// Total comment contributors
	$request = wesql::query('
		SELECT id_member
		FROM {db_prefix}media_comments
		WHERE approved = 1
		GROUP BY id_member',
		array()
	);
	$total_comment_contributors = wesql::num_rows($request);
	wesql::free_result($request);

	// Total featured albums
	$request = wesql::query('
		SELECT COUNT(id_album)
		FROM {db_prefix}media_albums
		WHERE approved = 1
		AND featured = 1',
		array()
	);
	list ($total_featured_albums) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Some more stats eh?
	$context['aeva_stats'] += array(
		'total_commentators' => (int) $total_comment_contributors,
		'total_item_posters' => (int) $total_item_contributors,
		'total_featured_albums' => (int) $total_featured_albums,
		'top_uploaders' => aeva_getTopMembers(5),
		'top_commentators' => aeva_getTopMembers(5, 'comments'),
		'top_albums_items' => aeva_getTopAlbumsByItems(5),
		'top_albums_comments' => aeva_getTopAlbumsByComments(5),
		'top_items_views' => aeva_getTopItems(5),
		'top_items_com' => aeva_getTopItems(5, 'num_com'),
		'top_items_rating' => aeva_getTopItems(5, 'rating'),
		'top_items_voters' => aeva_getTopItems(5, 'voters'),
	);

	$context['aeva_header']['data']['title'] = $txt['media_stats'];
	$context['page_title'] = $txt['media_gallery'].' - '.$txt['media_stats'];
	$context['aeva_current'] = 'stats';
	loadSubTemplate('aeva_stats');
	add_linktree($galurl.'sa=stats', $txt['media_stats']);
}

// Manages your albums' control panel
function aeva_albumCP($is_admin = false)
{
	global $user_info, $context, $txt, $galurl, $alburl, $scripturl, $settings;

	$alburl = $is_admin ? $scripturl . '?action=admin;area=aeva_albums;' . $context['session_var'] . '=' . $context['session_id'] . ';' : $galurl . 'area=mya;';
	if (!$is_admin)
	{
		// This file is needed for the toggle
		$context['header'] .= '
	<script src="' . add_js_file('scripts/media-admin.js', false, true) . '"></script>
	<script><!-- // --><![CDATA[
		var galurl = "' . $galurl . '";
	// ]]></script>';

		$context['page_title'] = $txt['media_my_user_albums'];
		$context['aeva_header']['data']['title'] = $txt['media_my_user_albums'];

		add_linktree($galurl . 'area=mya', $txt['media_my_user_albums']);
	}

	$sa = array(
		'add' => array($is_admin ? 'aeva_admin_albums_add' : 'aeva_addAlbum', true),
		'edit' => array($is_admin ? 'aeva_admin_albums_edit' : 'aeva_editAlbum', true),
		'delete' => array($is_admin ? 'aeva_admin_albums_delete' : 'aeva_deleteAlbum', false),
		'move' => array($is_admin ? 'aeva_admin_albums_move' : 'aeva_moveAlbum', false),
	);

	if (isset($_REQUEST['sa'], $sa[$_REQUEST['sa']]))
		if ($sa[$_REQUEST['sa']][1] === true)
			return $sa[$_REQUEST['sa']][0]();
		else
			$sa[$_REQUEST['sa']][0]();

	// Sub-Template
	loadSubTemplate('aeva_album_cp');

	// Load the albums
	if (!$is_admin)
	{
		$quicklist = aeva_getQuickAlbums('a.album_of = ' . $user_info['id'], 'master');
		aeva_getAlbums(empty($quicklist) ? '1=0' : 'a.master IN (' . implode(',', $quicklist) . ')', 1, false, 'a.album_of, a.child_level, a.a_order', '', false, 100);
	}
	else
		aeva_getAlbums(isset($_REQUEST['sa']) ? ($_REQUEST['sa'] == 'normal' ? 'a.featured = 0' : ($_REQUEST['sa'] == 'featured' ? 'a.featured = 1' : '')) : '', 0, false, 'a.album_of, a.child_level, a.a_order', '', false, 100);

	// Are we moving by any chance?
	$context['aeva_moving'] = $moving = isset($_REQUEST['move'], $context['aeva_albums'][$_REQUEST['move']]) ? $_REQUEST['move'] : false;
	$context['aeva_my_albums'] = array();
	foreach ($context['aeva_album_list'] as $list)
	{
		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'normal' && $context['aeva_albums'][$list]['featured'])
			continue;
		if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'featured' && !$context['aeva_albums'][$list]['featured'])
			continue;
		$context['aeva_my_albums'][$list] = $context['aeva_albums'][$list];

		if ($moving !== false && $moving != $list)
		{
			$move_url = '<a href="' . $alburl . ';sa=move;target='.$context['aeva_albums'][$list]['id'].';src='.$moving.';pos=%s"><img src="'.$settings['images_aeva'].'/arrow_%s.png" title="%s" style="vertical-align: bottom"></a>';
			$context['aeva_my_albums'][$list]['move_links'] = array(
				'before' => sprintf($move_url, 'before', 'up', $txt['media_admin_before']),
				'after' => sprintf($move_url, 'after', 'down', $txt['media_admin_after']),
				'child_of' => sprintf($move_url, 'child', 'in', $txt['media_admin_child_of']),
			);
			$context['aeva_my_albums'][$list]['is_getting_moved'] = false;
		}
		else
		{
			$context['aeva_my_albums'][$list]['move_links'] = array();
			$context['aeva_my_albums'][$list]['is_getting_moved'] = true;
		}
	}
}

function aeva_editAlbum()
{
	aeva_addAlbum(false, false);
}

// We could do all lists at the same time instead of separately, but who cares...
function aeva_getFromMemberlist($lis, $owner = 1)
{
	$lis = aeva_getTags($lis);
	$request = wesql::query('
		SELECT
			id_member, ' . ($owner ? 'member' : 'real') . '_name
		FROM {db_prefix}members' . (is_numeric($lis[0]) ? '
		WHERE id_member IN ({array_int:names})' : '
		WHERE real_name IN ({array_string:names}) OR member_name IN ({array_string:names})') . '
		AND id_member != {int:owner}',
		array(
			'names' => $lis,
			'owner' => $owner
		)
	);
	$lis = array();
	while ($row = wesql::fetch_row($request))
		$lis[$row[0]] = $row[1];
	wesql::free_result($request);
	return $lis;
}

function aeva_addAlbum($is_admin = false, $is_add = true)
{
	global $context, $scripturl, $galurl, $amSettings, $txt, $user_info, $settings;

	$is_edit = !$is_add;

	// Sub-Template
	loadSubTemplate('aeva_form');
	$albums = array();
	$primary_groups = array(0 => 0);

	// Retrieve all non-admin primary groups used by members...
	$request = wesql::query('SELECT id_group FROM {db_prefix}members GROUP BY id_group ORDER BY id_group', array());
	while ($row = wesql::fetch_row($request))
		$primary_groups[(int) $row[0]] = (int) $row[0];
	wesql::free_result($request);
	unset($primary_groups[1]);

	if ($is_add)
	{
		// Access
		if (!allowedTo('media_add_user_album'))
			fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));

		if ($is_admin)
			aeva_getAlbums('', 1, false, 'a.album_of, a.child_level, a.a_order');
		else
		{
			// Get our album tree
			$quicklist = aeva_getQuickAlbums('a.album_of = ' . $user_info['id'] . ' AND a.master = a.id_album');
			// Add all albums in our tree
			aeva_getAlbums('a.album_of = ' . $user_info['id'] . (empty($quicklist) ? '' : ' OR a.master IN (' . implode(',', $quicklist) . ')'), 1, false, 'a.album_of, a.child_level, a.a_order');
		}

		$_albums = array();
		foreach ($context['aeva_album_list'] as $list)
		{
			$albums[$list] = str_repeat('-', $context['aeva_albums'][$list]['child_level']) . ($context['aeva_albums'][$list]['owner']['id'] == $user_info['id'] && !$is_admin ? ' '
								: ' [' . $context['aeva_albums'][$list]['owner']['name'] . '] ') . $context['aeva_albums'][$list]['name'] . '&nbsp;';
			$_albums[$list] = $context['aeva_albums'][$list];
		}

		$access = array_merge((array) -1, array_keys($primary_groups));
		$access_write = array();
		$still_unapproved = true;
		$description = '';
		$passwd = '';
		$profile = 0;
		$quotaProfile = 0;
		$hidden = 0;
		$allowed_members = '';
		$allowed_write = '';
		$denied_members = '';
		$denied_write = '';
		$owner = $user_info['id'];
		$owner_display_name = $user_info['name'];
		$id_topic = 0;
		$id_album = 0;
		$big_icon = 0;
		$topic_subject = '';
		$title = '';
		$featured = $is_admin;
	}
	else
	{
		// Get the album's data
		$request = wesql::query('
			SELECT
				a.id_album, a.name, a.description, a.passwd, a.icon, a.bigicon, f.filename, bf.filename AS big_filename, a.options, NOT a.approved,
				a.id_perm_profile, a.id_quota_profile, a.featured, a.hidden, a.album_of, a.id_topic, t.id_topic AS id_topic2, m.subject,
				a.access, a.access_write, a.allowed_members, a.allowed_write, a.denied_members, a.denied_write, mem.member_name, mem.real_name
			FROM {db_prefix}media_albums AS a
				LEFT JOIN {db_prefix}media_files AS f ON (f.id_file = a.icon)
				LEFT JOIN {db_prefix}media_files AS bf ON (bf.id_file = a.bigicon)
				LEFT JOIN {db_prefix}topics AS t ON (a.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mem ON (a.album_of = mem.id_member)
			WHERE a.id_album = {int:album}
			LIMIT 1',
			array('album' => (int) $_GET['in'])
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('media_album_not_found');
		list (
			$id_album, $title, $description, $passwd, $icon, $big_icon, $filename, $big_filename, $peralbum_options, $still_unapproved,
			$profile, $quotaProfile, $featured, $hidden, $owner, $id_topic, $id_topic2, $topic_subject, $access,
			$access_write, $allowed_members, $allowed_write, $denied_members, $denied_write, $owner_name, $owner_display_name
		) = wesql::fetch_row($request);
		wesql::free_result($request);

		$access = $access === '' ? array() : explode(',', $access);
		$access_write = $access_write === '' ? array() : explode(',', $access_write);

		// If user removed the linked topic manually, we should reset the topic ID.
		if ($id_topic != $id_topic2)
		{
			$id_topic = 0;
			wesql::query('UPDATE {db_prefix}media_albums SET id_topic = 0 WHERE id_album = {int:album}', array('album' => (int) $_GET['in']));
		}
	}

	loadSource('Class-Editor');
	$title = wedit::un_preparsecode($title);
	$description = wedit::un_preparsecode($description);

	$members_allowed = !empty($allowed_members) ? aeva_getFromMemberlist($allowed_members, $owner) : array();
	$members_denied = !empty($denied_members) ? aeva_getFromMemberlist($denied_members, $owner) : array();
	$members_allowed_write = !empty($allowed_write) ? aeva_getFromMemberlist($allowed_write, $owner) : array();
	$members_denied_write = !empty($denied_write) ? aeva_getFromMemberlist($denied_write, $owner) : array();

	$can_auto_approve = allowedTo('media_auto_approve_albums') || allowedTo('media_moderate');
	$will_be_unapproved = $is_admin ? false : ($is_edit ? (!empty($amSettings['album_edit_unapprove']) || $still_unapproved) && !$can_auto_approve : !$can_auto_approve);

	$peralbum = $is_add ? array() : unserialize($peralbum_options);
	$peralbum['lightbox'] = isset($peralbum['lightbox']) && $peralbum['lightbox'] == 'no' ? 'no' : 'yes';
	$peralbum['outline'] = isset($peralbum['outline']) && in_array($peralbum['outline'], array('drop-shadow', 'rounded-white')) ? $peralbum['outline'] : 'drop-shadow';
	$peralbum['autosize'] = isset($peralbum['autosize']) && $peralbum['autosize'] == 'no' ? 'no' : 'yes';
	$peralbum['expand'] = isset($peralbum['expand']) && is_numeric($peralbum['expand']) ? (int) $peralbum['expand'] : 250;
	$peralbum['fadeinout'] = empty($peralbum['fadeinout']) ? 0 : 1;
	$peralbum['sort'] = empty($peralbum['sort']) ? 'm.id_media DESC' : $peralbum['sort'];

	// Load the member groups
	$simple_groups = !allowedTo('media_moderate');
	$groups = array();
	$groups[-1] = array($txt['media_membergroups_guests'], in_array(-1, $access));
	$groups[0] = array('<span' . (isset($primary_groups[0]) && !$simple_groups ? ' style="font-weight: bold" title="' . $txt['media_admin_membergroups_primary'] . '"' : '') . '>' . $txt['media_membergroups_members'] . '</span>', in_array(0, $access), in_array(0, $access_write));
	$groups['sep1'] = 'sep';

	if ($simple_groups)
	{
		$first_group = reset($primary_groups);
		$last_group = end($primary_groups);
		if ($last_group)
		{
			$groups[0][1] = in_array($last_group, $access);
			$groups[0][2] = in_array($last_group, $access_write);
		}
	}
	else
	{
		// Retrieve membergroups
		loadLanguage('ManageBoards');
		$request = wesql::query('
			SELECT g.id_group AS id, g.group_name AS name, g.min_posts, g.min_posts != -1 AS is_post_group
			FROM {db_prefix}membergroups AS g
			WHERE (g.id_group > 3 OR g.id_group = 2)
			ORDER BY g.min_posts, g.id_group ASC',
			array('user_id' => $user_info['id'])
		);
		$separated = false;
		while ($row = wesql::fetch_assoc($request))
		{
			if ($row['is_post_group'] && !$separated)
			{
				if (array_pop(array_keys($groups)) != 'sep1')
					$groups['sep2'] = 'sep';
				$separated = true;
			}
			$groups[$row['id']] = array(
				'<span' . ($row['is_post_group'] ? ' style="border-bottom: 1px dotted;" title="' . $txt['mboards_groups_post_group'] . '"' :
					(isset($primary_groups[$row['id']]) ? ' style="font-weight: bold" title="' . $txt['media_admin_membergroups_primary'] . '"' : ''))
					. '>' . $row['name'] . '</span>' . ($row['is_post_group'] ? ' (' . $row['min_posts'] . ')' : ''),
				in_array($row['id'], $access),
				in_array($row['id'], $access_write)
			);
		}
		wesql::free_result($request);

		$groups['sep3'] = 'sep';
		$groups['check_all'] = array('<i>' . $txt['check_all'] . '</i>', 'custom' => 'onclick="invertAll(this, this.form, \'$1[]\');"');
	}

	$sort_list = array('m.id_media', 'm.time_added', 'm.title', 'm.views', 'm.weighted');
	$sort_help = array('#3, #2, #1', '#1, #2, #3', '2008, 2007', '2007, 2008', 'D, C, B, A', 'A, B, C, D', '42, 32, 21', '21, 32, 42', '5/5, 4/5, 3/5', '3/5, 4/5, 5/5');
	$sort_fields = array();
	$counter = 0;

	foreach ($sort_list as $counter => $tmp)
	{
		$sort_fields[$tmp.' DESC'] = array($txt['media_sort_by_'.$counter] . ' (' . $txt['media_sort_order_desc'] . ') - ' . $sort_help[$counter*2] . '...', $peralbum['sort'] == $tmp.' DESC');
		$sort_fields[$tmp.' ASC'] = array($txt['media_sort_by_'.$counter] . ' (' . $txt['media_sort_order_asc'] . ') - ' . $sort_help[$counter*2+1] . '...', $peralbum['sort'] == $tmp.' ASC');
	}

	// Create the text editor
	aeva_createTextEditor('desc', 'aeva_form', false, $description);

	// Load the permission profiles
	$profs = array(
		1 => array(
			$txt['media_default_perm_profile'],
			$profile == $row['id']
		),
	);
	$request = wesql::query('
		SELECT id, val1
		FROM {db_prefix}media_variables
		WHERE type = {string:type}',
		array('type' => 'perm_profile')
	);
	while ($row = wesql::fetch_assoc($request))
		$profs[$row['id']] = array(
			censorText($row['val1']),
			$profile == $row['id'],
		);
	wesql::free_result($request);

	// Load the quota profiles
	$quota_profs = array(
		1 => array(
			$txt['media_default_perm_profile'],
			$profile == $row['id']
		),
	);
	$request = wesql::query('
		SELECT id, val1
		FROM {db_prefix}media_variables
		WHERE type = {string:type}',
		array('type' => 'quota_prof')
	);
	while ($row = wesql::fetch_assoc($request))
		$quota_profs[$row['id']] = array(
			censorText($row['val1']),
			$quotaProfile == $row['id'],
		);
	wesql::free_result($request);

	list ($ex_board, $ex_locked) = aeva_foxy_latest_topic($user_info['id'], $id_album);
	$topic_boards = aeva_foxy_get_board_list($ex_board);

	// Build the form
	if (empty($_GET['action']) || $_GET['action'] != 'admin')
		$context['aeva_form_url'] = $galurl . 'area=mya;sa=' . ($is_add ? 'add' : 'edit;in=' . $id_album);
	else
		$context['aeva_form_url'] = $scripturl.'?action=admin;area=aeva_albums;sa=' . ($is_add ? 'add' : 'edit;in=' . $id_album) . ';' . $context['session_var'] . '=' . $context['session_id'];

	$context['aeva_form'] = array(
		'note' => array(
			'label' => $txt['media_album_will_be_approved'],
			'class' => 'windowbg',
			'perm' => $will_be_unapproved,
			'type' => 'title',
		),
		'main' => array(
			'label' => $txt['media_album_mainarea'],
			'type' => 'title',
		),
		'name' => array(
			'label' => $txt['media_name'],
			'type' => 'text',
			'fieldname' => 'name',
			'value' => $title,
			'custom' => 'maxlength="52"',
		),
		'owner_name' => array(
			'label' => $txt['media_owner'],
			'type' => 'info',
			'value' => $is_add ? $user_info['name'] : $owner_display_name,
		),
		'owner_change' => array(
			'label' => $txt['media_owner'],
			'type' => 'text',
			'fieldname' => 'change_owner',
			'value' => $is_add ? $user_info['username'] : $owner_name,
			'custom' => 'maxlength="30" id="change_owner"',
			'next' => '<a href="' . $scripturl . '?' . ($is_admin ? '' : 'action=media;') . 'action=findmember;input=change_owner;delim=null;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return reqWin(this.href, 350, 400);"><img src="' . $settings['images_url'] . '/icons/assist.gif"></a>',
		),
		'featured' => array(
			'label' => $txt['media_featured_album'],
			'subtext' => '', // Temp
			'fieldname' => 'featured',
			'type' => 'yesno',
			'value' => $featured,
			'perm' => allowedTo('media_moderate'),
		),
		'desc' => array(
			'label' => $txt['media_add_desc'],
			'subtext' => $txt['media_add_desc_subtxt'],
			'type' => 'textbox',
			'fieldname' => 'desc',
			'value' => empty($description) ? '' : $description,
		),
		'icon' => array(
			'label' => $txt['media_icon'],
			'type' => 'file',
			'fieldname' => 'icon',
			'subtext' => $is_edit ? $txt['media_admin_icon_edit_subtext'] : '',
			'add_text' => $is_edit ? '<p><img src="'.$galurl.'sa=media;in='.$id_album.';icon" style="padding-left: 4px"></p>' : '',
		),
		'profile' => array(
			'label' => $txt['media_perm_profile'],
			'fieldname' => 'profile',
			'options' => $profs,
			'type' => 'select',
		),
		'quotaProfile' => array(
			'label' => $txt['media_quota_profile'],
			'fieldname' => 'quota_profile',
			'options' => $quota_profs,
			'type' => 'select',
			'perm' => allowedTo('media_manage'),
		),
		'position' => array(
			'label' => $txt['media_admin_position'],
			'type' => 'select',
			'fieldname' => 'pos',
			'options' => array(
				'before' => $txt['media_admin_before'],
				'after' => $txt['media_admin_after'],
				'child' => $txt['media_admin_child_of'],
			),
		),
		'target' => array(
			'label' => $txt['media_admin_target'],
			'fieldname' => 'target',
			'options' => $albums,
			'type' => 'select',
		),
		'topic_board' => array(
			'label' => $txt['media_linked_topic_board'],
			'type' => 'select',
			'fieldname' => 'topic_board',
			'options' => $topic_boards,
		),
		'linked_topic' => array(
			'label' => $txt['media_linked_topic'],
			'type' => 'link',
			'text' => $topic_subject,
			'link' => $scripturl . '?topic=' . $id_topic . '.0',
		),
		'sort_order' => array(
			'label' => $txt['media_default_sort_order'],
			'type' => 'select',
			'fieldname' => 'sort_order',
			'options' => $sort_fields,
		),
		'default_view' => array(
			'label' => $txt['media_items_view'],
			'type' => 'select',
			'fieldname' => 'default_view',
			'options' => array(
				'normal' => array($txt['media_view_normal'], !empty($peralbum['view']) && $peralbum['view'] == 'normal'),
				'filestack' => array($txt['media_view_filestack'], !empty($peralbum['view']) && $peralbum['view'] == 'filestack'),
			),
		),
		'privacy' => array(
			'label' => $txt['media_album_privacy'],
			'type' => 'title',
		),
		'groups' => array(
			'label' => $txt['media_admin_membergroups'],
			'subtext' => $simple_groups ? preg_replace('~<li>.*?</li>~', '', $txt['media_admin_membergroups_subtxt'], 1) : $txt['media_admin_membergroups_subtxt'],
			'fieldname' => array('groups', 'groups_write'),
			'type' => 'checkbox_dual',
			'multi' => true,
			'options' => $groups,
		),
		'allowed_members' => array(
			'label' => $txt['media_allowed_members'],
			'subtext' => $txt['media_allowed_members_subtxt'],
			'type' => 'text',
			'fieldname' => 'allowed_members',
			'value' => empty($members_allowed) ? '' : implode(', ', $members_allowed),
			'custom' => 'maxlength="52" id="allowed_members"',
			'next' => '<a href="' . $scripturl . '?' . ($is_admin ? '' : 'action=media;') . 'action=findmember;input=allowed_members;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return reqWin(this.href, 350, 400);"><img src="' . $settings['images_url'] . '/icons/assist.gif"></a>',
		),
		'allowed_write' => array(
			'label' => $txt['media_allowed_write'],
			'subtext' => $txt['media_allowed_write_subtxt'],
			'type' => 'text',
			'fieldname' => 'allowed_write',
			'value' => empty($members_allowed_write) ? '' : implode(', ', $members_allowed_write),
			'custom' => 'maxlength="52" id="allowed_write"',
			'next' => '<a href="' . $scripturl . '?' . ($is_admin ? '' : 'action=media;') . 'action=findmember;input=allowed_write;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return reqWin(this.href, 350, 400);"><img src="' . $settings['images_url'] . '/icons/assist.gif"></a>',
		),
		'denied_members' => array(
			'label' => $txt['media_denied_members'],
			'subtext' => $txt['media_denied_members_subtxt'],
			'type' => 'text',
			'fieldname' => 'denied_members',
			'value' => empty($members_denied) ? '' : implode(', ', $members_denied),
			'custom' => 'maxlength="52" id="denied_members"',
			'next' => '<a href="' . $scripturl . '?' . ($is_admin ? '' : 'action=media;') . 'action=findmember;input=denied_members;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return reqWin(this.href, 350, 400);"><img src="' . $settings['images_url'] . '/icons/assist.gif"></a>',
		),
		'denied_write' => array(
			'label' => $txt['media_denied_write'],
			'subtext' => $txt['media_denied_write_subtxt'],
			'type' => 'text',
			'fieldname' => 'denied_write',
			'value' => empty($members_denied_write) ? '' : implode(', ', $members_denied_write),
			'custom' => 'maxlength="52" id="denied_write"',
			'next' => '<a href="' . $scripturl . '?' . ($is_admin ? '' : 'action=media;') . 'action=findmember;input=denied_write;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return reqWin(this.href, 350, 400);"><img src="' . $settings['images_url'] . '/icons/assist.gif"></a>',
		),
		'hidden' => array(
			'label' => $txt['media_album_hidden'],
			'subtext' => $txt['media_album_hidden_subtxt'],
			'fieldname' => 'hidden',
			'type' => 'yesno',
			'value' => $hidden,
		),
		'passwd' => array(
			'label' => $txt['media_admin_passwd'],
			'subtext' => $txt['media_admin_passwd_subtxt'],
			'fieldname' => 'passwd',
			'type' => 'text',
			'value' => preg_match('/^[a-f0-9]{40}$/', $passwd) ? '' : $passwd,
		),
		'lightbox_section' => array(
			'label' => $txt['media_lightbox_section'],
			'type' => 'title',
		),
		'lightbox_enable' => array(
			'label' => $txt['media_lightbox_enable'],
			'subtext' => $txt['media_lightbox_enable_info'],
			'fieldname' => 'lightbox',
			'type' => 'yesno',
			'value' => $peralbum['lightbox'] == 'yes',
		),
		'lightbox_outline' => array(
			'label' => $txt['media_lightbox_outline'],
			'subtext' => $txt['media_lightbox_outline_info'],
			'fieldname' => 'lightbox_outline',
			'type' => 'select',
			'options' => array(
				'drop-shadow' => array('drop-shadow', $peralbum['outline'] == 'drop-shadow'),
				'rounded-white' => array('rounded-white', $peralbum['outline'] == 'rounded-white'),
			),
		),
		'lightbox_expand' => array(
			'label' => $txt['media_lightbox_expand'],
			'subtext' => $txt['media_lightbox_expand_info'],
			'fieldname' => 'lightbox_expand',
			'value' => $peralbum['expand'],
			'type' => 'text',
		),
		'lightbox_autosize' => array(
			'label' => $txt['media_lightbox_autosize'],
			'subtext' => $txt['media_lightbox_autosize_info'],
			'fieldname' => 'lightbox_autosize',
			'type' => 'yesno',
			'value' => $peralbum['autosize'] == 'yes',
		),
		'lightbox_fadeinout' => array(
			'label' => $txt['media_lightbox_fadeinout'],
			'subtext' => $txt['media_lightbox_fadeinout_info'],
			'fieldname' => 'lightbox_fadeinout',
			'type' => 'yesno',
			'value' => !empty($peralbum['fadeinout']),
		),
	);

	if ($is_edit)
	{
		unset($context['aeva_form']['position']);
		unset($context['aeva_form']['target']);
	}
	unset($context['aeva_form'][allowedTo('media_manage') ? 'owner_name' : 'owner_change']);

	// No need to show profile select boxes if there's only a default profile to choose from...
	if (empty($profs) || count($profs) == 1)
		$context['aeva_form']['profile'] = array(
			'fieldname' => 'profile',
			'value' => 1,
			'type' => 'hidden',
			'skip' => true,
		);
	if (empty($quota_profs) || count($quota_profs) == 1)
		$context['aeva_form']['quotaProfile'] = array(
			'fieldname' => 'quota_profile',
			'value' => 1,
			'type' => 'hidden',
			'skip' => true,
		);

	unset($context['aeva_form'][$id_topic ? 'topic_board' : 'linked_topic']);

	if (empty($amSettings['use_lightbox']))
		unset(
			$context['aeva_form']['lightbox_section'],
			$context['aeva_form']['lightbox_enable'],
			$context['aeva_form']['lightbox_outline'],
			$context['aeva_form']['lightbox_expand'],
			$context['aeva_form']['lightbox_autosize'],
			$context['aeva_form']['lightbox_fadeinout']
		);

	// Submitting?
	if (isset($_POST['submit_aeva']))
	{
		$peralbum['lightbox'] = isset($_POST['lightbox']) && $_POST['lightbox'] == 0 ? 'no' : 'yes';
		$peralbum['outline'] = isset($_POST['lightbox_outline']) && in_array($_POST['lightbox_outline'], array('drop-shadow', 'rounded-white')) ? $_POST['lightbox_outline'] : 'drop-shadow';
		$peralbum['autosize'] = isset($_POST['lightbox_autosize']) && $_POST['lightbox_autosize'] == 0 ? 'no' : 'yes';
		$peralbum['expand'] = isset($_POST['lightbox_expand']) && is_numeric($_POST['lightbox_expand']) && $_POST['lightbox_expand'] >= 0 ? (int) $_POST['lightbox_expand'] : 250;
		$peralbum['expand'] = max(1, min(5000, $peralbum['expand']));
		$peralbum['fadeinout'] = empty($_POST['lightbox_fadeinout']) ? 0 : 1;
		$peralbum['view'] = !empty($_POST['default_view']) && in_array($_POST['default_view'], array('normal', 'filestack')) ? $_POST['default_view'] : 'normal';

		$hidden = empty($_POST['hidden']) ? 0 : 1;
		$featured = !allowedTo('media_moderate') ? $featured : (empty($_POST['featured']) ? 0 : 1);

		if (!empty($_POST['sort_order']))
			if (preg_match('~^m\.[a-z_]+ (A|DE)SC$~', $_POST['sort_order']))
				$peralbum['sort'] = $_POST['sort_order'];

		// WYSIWYG?
		if (!empty($_REQUEST['desc_mode']) && isset($_REQUEST['desc']))
		{
			loadSource('Subs-Editor');

			$_REQUEST['desc'] = html_to_bbc($_REQUEST['desc']);

			// We need to unhtml it now as it gets done shortly.
			$_REQUEST['desc'] = un_htmlspecialchars($_REQUEST['desc']);

			// We need this for everything else.
			$_POST['desc'] = $_REQUEST['desc'];
		}

		// Get their name (limit to 80 chars), description, groups and password
		$name = westr::htmlspecialchars($_POST['name']);
		$name = aeva_utf2entities($name, false, 80 + strlen($name) - strlen($_POST['name']), false, false, true, 20, 255);
		if (empty($name))
			fatal_lang_error('media_admin_name_left_empty', false);
		$desc = westr::htmlspecialchars(aeva_utf2entities($_POST['desc'], false, 0));
		wedit::preparsecode($name);
		wedit::preparsecode($desc);
		$passwd = !empty($_POST['passwd']) ? $_POST['passwd'] : '';

		$id_profile = (int) $_REQUEST['profile'];
		$id_quota_profile = allowedTo('media_moderate') ? (int) $_REQUEST['quota_profile'] : 1;

		$mgroups = $mwgroups = array();
		if (isset($_POST['groups']))
			foreach ($_POST['groups'] as $id => $group)
				if (is_numeric($id))
					$mgroups[] = $group;
		if ($simple_groups && in_array($first_group, $mgroups))
			$mgroups = array_unique(array_merge($mgroups, array_keys($primary_groups)));
		$mgroups = implode(',', $mgroups);
		if (isset($_POST['groups_write']))
			foreach ($_POST['groups_write'] as $id => $group)
				if (is_numeric($id))
					$mwgroups[] = $group;
		if ($simple_groups && in_array($first_group, $mwgroups))
			$mwgroups = array_unique(array_merge($mwgroups, array_keys($primary_groups)));
		$mwgroups = implode(',', $mwgroups);

		// Load new owner
		if (!empty($_POST['change_owner']) && allowedTo('media_manage'))
		{
			$new_owner = aeva_getFromMemberlist($_POST['change_owner'], 0);
			$owner = !empty($new_owner) && is_array($new_owner) ? key($new_owner) : $owner;
		}

		// Load member lists
		if (!empty($_POST['allowed_members']))
			$list_allowed = implode(',', array_keys(aeva_getFromMemberlist($_POST['allowed_members'], $owner)));
		if (!empty($_POST['allowed_write']))
			$list_allowed_write = implode(',', array_keys(aeva_getFromMemberlist($_POST['allowed_write'], $owner)));
		if (!empty($_POST['denied_members']))
			$list_denied = implode(',', array_keys(aeva_getFromMemberlist($_POST['denied_members'], $owner)));
		if (!empty($_POST['denied_write']))
			$list_denied_write = implode(',', array_keys(aeva_getFromMemberlist($_POST['denied_write'], $owner)));

		if ($is_add)
		{
			// Now get their target and position (which also gets their order, child level and parent)
			if (!empty($_albums) && !isset($_POST['target'], $_albums[$_POST['target']]))
				fatal_lang_error('media_admin_invalid_target');
			if (!empty($_albums))
				$target = $_albums[$_POST['target']];
			else
			{
				// This is a fix for no albums
				$target = array(
					'order' => 0,
					'parent' => 0,
					'child_level' => 0,
				);
			}
			if (!in_array($_POST['pos'], array('before', 'after', 'child')))
				fatal_lang_error('media_admin_invalid_position');
			$pos = !empty($_albums) ? $_POST['pos'] : 'after';
			if (in_array($pos, array('after', 'before')))
			{
				$order = $target['order'] - ($pos == 'before' ? 1 : 0);
				$parent = $target['parent'];
				$child_level = $target['child_level'];
			}
			else
			{
				$order = $target['order'];
				$parent = $target['id'];
				$child_level = $target['child_level'] + 1;
			}
		}

		// Process the icon
		if (isset($_FILES['icon']) && !empty($_FILES['icon']['name']))
		{
			// First we delete the old one...
			if ($is_edit && !empty($icon) && $icon > 4)
			{
				// Is the album icon still being used by an item...?
				$request = wesql::query('
					SELECT f.id_file
					FROM {db_prefix}media_files AS f
						INNER JOIN {db_prefix}media_items AS m ON (f.id_file = m.id_thumb)
					WHERE f.id_file = {int:file}
					LIMIT 1',
					array('file' => $icon)
				);
				$can_delete_icon = wesql::num_rows($request) == 0;
				wesql::free_result($request);

				if ($can_delete_icon)
				{
					@unlink($amSettings['data_dir_path'] . '/album_icons/' . aeva_getEncryptedFilename($filename, $icon, true));
					wesql::query('
						DELETE FROM {db_prefix}media_files
						WHERE id_file = {int:file}',
						array('file' => $icon)
					);
				}
			}
			if ($is_edit && !empty($big_icon) && $big_icon > 0)
			{
				// Is the album big icon still being used by an item...?
				$request = wesql::query('
					SELECT m.id_preview IS NULL, f.directory
					FROM {db_prefix}media_files AS f
						LEFT JOIN {db_prefix}media_items AS m ON (f.id_file = m.id_preview) OR (f.id_file = m.id_file)
					WHERE f.id_file = {int:file}
					LIMIT 1',
					array('file' => $big_icon)
				);
				list ($can_delete_icon, $big_directory) = wesql::fetch_row($request);
				wesql::free_result($request);

				if ($can_delete_icon)
				{
					@unlink($amSettings['data_dir_path'].'/'.$big_directory.'/'.aeva_getEncryptedFilename($big_filename, $big_icon));
					wesql::query('
						DELETE FROM {db_prefix}media_files
						WHERE id_file = {int:file}',
						array('file' => $big_icon)
					);
				}
			}

			// Now we process the new one
			$big_icon = 0;
			$filename = basename($_FILES['icon']['name']);
			$encfile = $filename . '_tmp';
			$dir = $amSettings['data_dir_path'].'/album_icons';
			if (!file_exists($dir))
				@mkdir($dir, 0777);
			$dir2 = 'album_icons';

			$icon_file = new media_handler;
			$icon_file->init($_FILES['icon']['tmp_name']);
			$icon_file->securityCheck($_FILES['icon']['name']);
			$icon_file->force_mime = $icon_file->getMimeFromExt($_FILES['icon']['name']);
			list ($width, $height) = $icon_file->getSize();
			$size = $icon_file->getFileSize();

			$thumbnailed = false;
			if (($width > $amSettings['max_thumb_width']) || ($height > $amSettings['max_thumb_height']))
			{
				if ($resizedpic = $icon_file->createThumbnail($dir . '/' . $encfile, min($width, $amSettings['max_thumb_width']), min($height, $amSettings['max_thumb_height'])))
				{
					$orig_width = $width;
					$orig_height = $height;
					$resizedpic->force_mime = $resizedpic->getMimeFromExt($_FILES['icon']['name']);
					list ($width, $height) = $resizedpic->getSize();
					$size = $resizedpic->getFileSize();
					$resizedpic->close();
					$thumbnailed = true;
					$bigiconed = false;

					// Create a preview as well.
					if (($orig_width > $amSettings['max_bigicon_width']) || ($height > $amSettings['max_bigicon_height']))
					{
						if ($resizedpic = $icon_file->createThumbnail($dir . '/big_' . $filename, min($orig_width, $amSettings['max_bigicon_width']), min($orig_height, $amSettings['max_bigicon_height'])))
						{
							$resizedpic->force_mime = $resizedpic->getMimeFromExt($_FILES['icon']['name']);
							list ($preview_width, $preview_height) = $resizedpic->getSize();
							$preview_size = $resizedpic->getFileSize();
							$resizedpic->close();
							$bigiconed = true;
						}
					}
					else
					{
						$preview_width = $orig_width;
						$preview_height = $orig_height;
						$preview_size = $size;
					}
				}
			}
			$icon_file->close();

			if ($is_add)
			{
				$ins_var = array('filename', 'filesize', 'width', 'height', 'directory');
				$ins_val = array($filename, $size, (int) $width, (int) $height, $dir2);
			}
			else
			{
				$ins_var = array('filename', 'filesize', 'width', 'height', 'directory', 'id_album');
				$ins_val = array($filename, $size, (int) $width, (int) $height, $dir2, $id_album);
			}

			// Insert it to the DB now
			wesql::insert('',
				'{db_prefix}media_files',
				$ins_var,
				$ins_val
			);
			$icon = wesql::insert_id();

			if ($thumbnailed)
			{
				if ($bigiconed)
					@unlink($_FILES['icon']['tmp_name']);
				@rename($dir . '/' . $encfile, $dir . '/' . aeva_getEncryptedFilename($filename, $icon, true));

				if (!empty($preview_width))
				{
					$ins_val[0] = 'big_' . $filename;
					$ins_val[1] = $preview_size;
					$ins_val[2] = $preview_width;
					$ins_val[3] = $preview_height;

					// Insert big icon into the database
					wesql::insert('',
						'{db_prefix}media_files',
						$ins_var,
						$ins_val
					);
					$big_icon = wesql::insert_id();

					if ($bigiconed)
						@rename($dir.'/big_'.$filename, $dir.'/'.aeva_getEncryptedFilename('big_'.$filename, $big_icon));
					else
						move_uploaded_file($_FILES['icon']['tmp_name'], $dir.'/'.aeva_getEncryptedFilename('big_'.$filename, $big_icon));
				}
			}
			else
				move_uploaded_file($_FILES['icon']['tmp_name'], $dir.'/'.aeva_getEncryptedFilename($filename, $icon, true));
		}
		elseif ($is_add)
			$icon = 4;

		if ($is_add)
		{
			$ins_var = array(
				'name', 'description', 'passwd', 'featured', 'parent', 'child_level', 'a_order', 'icon', 'bigicon', 'approved', 'directory',
				'options', 'album_of', 'id_perm_profile', 'id_quota_profile', 'hidden', 'access', 'access_write',
				'allowed_members', 'allowed_write', 'denied_members', 'denied_write'
			);
			$ins_val = array(
				$name, $desc, $passwd, $featured ? 1 : 0, $parent, $child_level, $order, $icon, $big_icon, !$featured && $will_be_unapproved ?
				0 : 1, '', serialize($peralbum), $owner, $id_profile, $id_quota_profile, $hidden, $mgroups, $mwgroups,
				empty($list_allowed) ? '' : $list_allowed, empty($list_allowed_write) ? '' : $list_allowed_write,
				empty($list_denied) ? '' : $list_denied, empty($list_denied_write) ? '' : $list_denied_write
			);

			// Insert the album
			wesql::insert('',
				'{db_prefix}media_albums',
				$ins_var,
				$ins_val
			);
			$id_album = wesql::insert_id();

			// Insert master ID (i.e. topmost album in the tree)
			if (!empty($parent))
			{
				$request = wesql::query('SELECT master FROM {db_prefix}media_albums WHERE id_album = {int:parent}', array('parent' => $parent));
				list ($master) = wesql::fetch_row($request);
				wesql::free_result($request);
				if (empty($master))
					$master = $parent;
			}
			else
				$master = $id_album;
			wesql::query('UPDATE {db_prefix}media_albums SET master = {int:master} WHERE id_album = {int:album}', array('master' => $master, 'album' => $id_album));

			// Create its directory
			$is_dir = aeva_foolProof();
			if ($is_dir !== 1 || !aeva_createAlbumDir($id_album))
			{
				// We are sooo messed up....
				wesql::query('
					DELETE FROM {db_prefix}media_albums
					WHERE id_album = {int:id}',
					array('id' => $id_album)
				);

				fatal_lang_error($is_dir ? 'media_dir_failed' : 'media_not_a_dir');
			}

			// Re-order the albums
			aeva_reorderAlbums();

			// Fix up the icon
			if ($icon > 4)
				wesql::query('
					UPDATE {db_prefix}media_files
					SET id_album = {int:album}
					WHERE id_file IN ({int:file}, {int:bigicon})',
					array('album' => $id_album, 'file' => $icon, 'bigicon' => (int) $big_icon)
				);

			// Update the stats if needed
			aeva_increaseSettings($will_be_unapproved ? 'num_unapproved_albums' : 'total_albums');
		}
		else
		{
			// Update the album and fly!
			wesql::query('
				UPDATE {db_prefix}media_albums
				SET
					name = {string:name}, description = {string:desc}, icon = {int:icon}, bigicon = {int:bigicon}, passwd = {string:passwd}, options = {string:options}, approved = {int:approved},
					id_perm_profile = {int:profile}, id_quota_profile = {int:qprofile}, featured = {int:featured}, hidden = {int:hidden}, access = {string:access}, access_write = {string:access_write},
					allowed_members = {string:allow}, allowed_write = {string:allow_write}, denied_members = {string:deny}, denied_write = {string:deny_write}, album_of = {int:owner_id}
				WHERE id_album = {int:album}',
				array(
					'name' => $name, 'description' => $desc, 'icon' => $icon, 'bigicon' => $big_icon, 'passwd' => $passwd,
					'access' => $mgroups, 'access_write' => $mwgroups, 'desc' => $desc, 'album' => $id_album,
					'options' => serialize($peralbum),
					'approved' => $will_be_unapproved ? 0 : 1,
					'profile' => $id_profile,
					'qprofile' => $id_quota_profile,
					'featured' => $featured,
					'hidden' => $hidden,
					'allow' => !empty($list_allowed) ? $list_allowed : '',
					'allow_write' => !empty($list_allowed_write) ? $list_allowed_write : '',
					'deny' => !empty($list_denied) ? $list_denied : '',
					'deny_write' => !empty($list_denied_write) ? $list_denied_write : '',
					'owner_id' => $owner,
				)
			);

			// Has the approved flag changed? Then update the stats...
			if ($will_be_unapproved != $still_unapproved)
			{
				aeva_increaseSettings($will_be_unapproved ? 'num_unapproved_albums' : 'total_albums');
				aeva_increaseSettings($will_be_unapproved ? 'total_albums' : 'num_unapproved_albums', -1);
			}
		}

		if (!empty($_POST['topic_board']))
		{
			$topic_board = (int) $_POST['topic_board'];
			aeva_foxy_create_topic($id_album, $name, $topic_board, $ex_locked);
		}

		// We're done! Redirect to the existing/newly created album.
		redirectexit($galurl . 'sa=album;in=' . $id_album);
	}
}

// Moves a album
function aeva_moveAlbum()
{
	global $context, $user_info;

	// Load all the current albums
	aeva_getAlbums('', 0, false);

	// Get the data
	if (!isset($context['aeva_albums'][$_REQUEST['target']]) || !isset($context['aeva_albums'][$_REQUEST['src']]) || !in_array($_REQUEST['pos'], array('after', 'before', 'child')))
		return false;

	$target = $context['aeva_albums'][$_REQUEST['target']];
	$pos = $_REQUEST['pos'];
	$src = $context['aeva_albums'][$_REQUEST['src']];

	if ($src['owner']['id'] != $user_info['id'] && !allowedTo('media_moderate'))
		fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));

	// Get their new order, child level and parent
	if ($pos == 'after' || $pos == 'before')
	{
		$order = $target['order'] - ($pos == 'before' ? 1 : -1);
		$child_level = $target['child_level'];
		$parent = $target['parent'];
		$master = $target['master'] == $target['id'] ? $src['id'] : $target['master'];
	}
	else
	{
		$order = $target['order'];
		$child_level = $target['child_level'] + 1;
		$parent = $target['id'];
		$master = $target['master'];
	}
	$child_level_diff = $child_level - $src['child_level'];

	// Update the data
	wesql::query('
		UPDATE {db_prefix}media_albums
		SET a_order = {int:order}, child_level = {int:child_level}, parent = {int:parent}, master = {int:master}
		WHERE id_album = {int:album}',
		array('order' => $order, 'child_level' => $child_level, 'parent' => $parent, 'master' => $master, 'album' => $src['id'])
	);

	// I never dealt with this in SMG -- it is time! Move children albums along with their parents!
	$children = aeva_getAlbumChildren($src['id']);
	if (!empty($children) && is_array($children))
		wesql::query('
			UPDATE {db_prefix}media_albums
			SET a_order = {int:order}, master = {int:master}, child_level = child_level' . ($child_level_diff == 0 ? '' : ($child_level_diff > 0 ? ' +' : ' -') . '{int:child_level}') . '
			WHERE id_album IN ({array_int:albums})',
			array('order' => $order, 'child_level' => abs($child_level_diff), 'master' => $master, 'albums' => $children)
		);

	// Fix things up, and we're done!
	aeva_reorderAlbums();
}

// Deletes an album and the items/comments in it
function aeva_deleteAlbum($id = 0, $from_approval = false)
{
	global $context, $amSettings, $user_info, $scripturl;

	$id = empty($id) ? (int) $_GET['in'] : $id;

	// Does the album exist at all?
	$request = wesql::query('
		SELECT a.id_album, a.directory, a.icon, a.bigicon, a.album_of, f.filename, bf.filename, bf.directory, approved, name, parent, master
		FROM {db_prefix}media_albums AS a
			LEFT JOIN {db_prefix}media_files AS f ON (f.id_file = a.icon)
			LEFT JOIN {db_prefix}media_files AS bf ON (bf.id_file = a.bigicon)
		WHERE a.id_album = {int:album}',
		array('album' => $id)
	);
	if (wesql::num_rows($request) == 0)
		return false;
	list ($id_album, $dir, $icon, $big_icon, $owner, $file, $big_file, $big_dir, $approved, $name, $parent, $master) = wesql::fetch_row($request);
	wesql::free_result($request);

	if (($owner != $user_info['id'] || !allowedTo('media_moderate_own_albums')) && !allowedTo('media_moderate'))
		fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));

	// Are we messed up with the directories?
	if (empty($dir))
		return false;

	// Start deleting stuff

	// Let's first delete the items, and their comments go with them...
	$request = wesql::query('
		SELECT id_media
		FROM {db_prefix}media_items
		WHERE album_id = {int:album}',
		array('album' => $id_album)
	);
	$items = array();
	while ($row = wesql::fetch_assoc($request))
		$items[] = $row['id_media'];
	wesql::free_result($request);
	if (!empty($items))
		aeva_deleteItems($items, true, false);

	// Remove the directory
	loadSource('Subs-Package');
	deltree($amSettings['data_dir_path'] . '/' . $dir);

	// Remove the icon
	if ($icon > 4)
	{
		@unlink($amSettings['data_dir_path'].'/album_icons/'.aeva_getEncryptedFilename($file, $icon, true));
		wesql::query('
			DELETE FROM {db_prefix}media_files
			WHERE id_file = {int:file}',
			array('file' => $icon)
		);
	}

	// Remove the big icon
	if ($big_icon > 0)
	{
		@unlink($amSettings['data_dir_path'].'/'.$big_dir.'/'.aeva_getEncryptedFilename($big_file, $big_icon));
		wesql::query('
			DELETE FROM {db_prefix}media_files
			WHERE id_file = {int:file}',
			array('file' => $big_icon)
		);
	}

	// Remove album ID from all custom fields. I'm not getting paid for this job.
	wesql::query('
		UPDATE {db_prefix}media_fields AS cf
		SET cf.albums = TRIM(BOTH \',\' FROM REPLACE(CONCAT(\',\', cf.albums, \',\'), \',{int:id_album},\', \',\'))
		WHERE FIND_IN_SET({int:id_album}, cf.albums)',
		array('id_album' => $id_album)
	);

	// A variation that may be useful. I'm still not getting paid for this job.
	wesql::query('
		UPDATE {db_prefix}media_fields AS cf
		SET cf.albums = TRIM(LEADING \', \' FROM TRIM(TRAILING \',\' FROM REPLACE(CONCAT(\', \', cf.albums, \',\'), \', {int:id_album},\', \',\')))
		WHERE FIND_IN_SET({int:id_album}, cf.albums)',
		array('id_album' => $id_album)
	);

	// Remove the album itself
	wesql::query('
		DELETE FROM {db_prefix}media_albums
		WHERE id_album = {int:album}',
		array('album' => $id_album)
	);

	// Update the stats
	aeva_increaseSettings($approved == 1 ? 'total_albums' : 'num_unapproved_albums', -1);

	// Fix the sub-albums
	wesql::query('
		UPDATE {db_prefix}media_albums
		SET parent = {int:parent}, child_level = child_level - 1
		WHERE parent = {int:album}',
		array('album' => $id_album, 'parent' => $parent)
	);

	// Fix master ID if needed
	if ($master == $id_album)
	{
		wesql::query('
			UPDATE {db_prefix}media_albums
			SET master = 0
			WHERE master = {int:master}',
			array('master' => $id_album)
		);

		wesql::query('UPDATE {db_prefix}media_albums SET master = id_album WHERE parent = 0', array());
		$alb = array();
		$continue = true;
		$unstick = 0;
		while ($continue)
		{
			wesql::query('
				UPDATE {db_prefix}media_albums AS a1, {db_prefix}media_albums AS a2
				SET a1.master = a2.master
				WHERE (a1.parent = a2.id_album) AND (a1.master = 0) AND (a2.master != 0)',
				array()
			);
			$continue = (wesql::affected_rows() > 0) && ($unstick++ < 100);
		}
	}

	// Delete the directories from DB
	wesql::query('
		DELETE FROM {db_prefix}media_variables
		WHERE val1 = {int:album}',
		array('album' => $id_album)
	);

	// Log it
	if (!$from_approval)
	{
		$opts = array(
			'type' => 'delete',
			'subtype' => 'album',
			'action_on' => array(
				'name' => $name,
				'id' => $id_album,
			),
			'action_by' => array(
				'id' => $user_info['id'],
				'name' => $user_info['name'],
			),
		);
		aeva_logModAction($opts);
	}
}

// Handles the Mass Upload page
function aeva_massUpload()
{
	global $amSettings, $txt, $context, $user_info, $settings, $galurl, $modSettings, $cookiename;

	// Modifying item's title?
	if (isset($_POST['submit_title_update']))
		return aeva_massUploadFinish();

	// No album? :'(
	if (!isset($_REQUEST['album']))
		fatal_lang_error('media_album_not_specified');

	$_REQUEST['album'] = (int) $_REQUEST['album'];
	$context['page_title'] = $txt['media_multi_upload'];

	// Load the limits
	aeva_loadQuotas();

	// Load this album's data
	$request = wesql::query('
		SELECT id_album, name, album_of
		FROM {db_prefix}media_albums
		WHERE id_album = {int:album}',
		array('album' => (int) $_REQUEST['album'])
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('media_album_not_found');
	list ($id_album, $album_name, $id_member_album) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Allowed to access or not?
	if (!$context['aeva_album']['can_upload'] && !allowedToAccessAlbum($id_album))
		fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));

	if (empty($_REQUEST['upcook']))
	{
		loadSubTemplate('aeva_multiUpload');
		$max_php_size = (int) min(aeva_getPHPSize('upload_max_filesize'), aeva_getPHPSize('post_max_size'));

		// Get the allowed type
		$context['allowed_types'] = aeva_allowed_types();

		foreach ($context['allowed_types'] as $ext_type => $ext)
			foreach ($ext as $k => $v)
				$context['allowed_types'][$ext_type][$k] = '*.' . $v;

		$allowed_exts = array();
		foreach ($context['allowed_types'] as $filetype => $exts)
			$allowed_exts[] = '{description: "' . $txt['media_filetype_' . $filetype] . '", extensions: "' . implode(';', $exts) . '"}';

		// HTML Headers
		$context['aeva_submit_url'] = $galurl . 'sa=mass;album=' . $id_album . ';xml;upcook=' . urlencode(base64_encode($_COOKIE[$cookiename]));
		add_js_file('http://yui.yahooapis.com/combo?2.8.2r1/build/yahoo-dom-event/yahoo-dom-event.js&amp;2.8.2r1/build/element/element-min.js', true);
		add_js_file(array('scripts/uploader.js', 'scripts/up.js'));
		add_js('
	var galurl = "', $galurl, '";
	Yup.init({
		swfurl: "', aeva_theme_url('uploader.swf'), '",
		postURL: ', JavaScriptEscape($context['aeva_submit_url']), ',
		filters: [ ', implode(', ', $allowed_exts), ' ],
		php_limit: ', $max_php_size, ',
		text: {
			bytes: ', JavaScriptEscape($txt['media_bytes']), ',
			kb: ', JavaScriptEscape($txt['media_kb']), ',
			mb: ', JavaScriptEscape($txt['media_mb']), ',
			cancel: ', JavaScriptEscape($txt['media_mass_cancel']), ',
			tl_php: ', JavaScriptEscape(sprintf($txt['media_file_too_large_php'], round($max_php_size/1048576, 1))), ',
			tl_quota: ', JavaScriptEscape($txt['media_file_too_large_quota']), ',
			tl_img: ', JavaScriptEscape($txt['media_file_too_large_img']), '
		},
		quotas: {');

		$filetypes = array('im' => 'image', 'au' => 'audio', 'vi' => 'video', 'do' => 'doc');
		if (!empty($context['allowed_types']))
		{
			foreach ($context['allowed_types'] as $filetype => $exts)
				if (isset($filetypes[$filetype]))
					foreach ($exts as $ext)
						$context['footer_js'] .= ' "' . substr(strrchr($ext, '.'), 1) . '": ' . (int) $context['aeva_max_file_size'][$filetypes[$filetype]] . ',';
			$context['footer_js'] = substr($context['footer_js'], 0, -1) . ' ';
		}

		add_js('}
	});');

		return;
	}

	// Are we submitting?
	loadSubTemplate('aeva_multiUpload_xml');
	$context['errors'] = array();
	$context['items'] = array();
	$context['aeva_mu_id'] = mt_rand(1,10000000);

	// No files?
	if (empty($_FILES['Filedata']['name']))
		fatal_lang_error('media_file_not_specified');

	// This is single-file upload, since YUI sends each file 1 by 1 (gotta love it.)
	// But we add support for multiple files anyway, because of zip...

	$files = array();

	// Is it zip?
	$file = new media_handler;
	$file->init($_FILES['Filedata']['name'], null, null, false);
	$zip = aeva_getExt($file->src) == 'zipm';
	$file->close();

	if ($zip)
	{
		// Call Subs-Package.php for extraction
		loadSource('Subs-Package');

		// Extract it
		read_tgz_file($_FILES['Filedata']['tmp_name'], $amSettings['data_dir_path'] . '/tmp/mu_' . $context['aeva_mu_id']);

		// Stack the files
		$dir = opendir($amSettings['data_dir_path'] . '/tmp/mu_' . $context['aeva_mu_id']);
		while ($file = readdir($dir))
			if (file_exists($amSettings['data_dir_path'] . '/tmp/mu_' . $context['aeva_mu_id'] . '/' . $file) && $file != '.' && $file != '..')
				$files[] = array(
					'filename' => basename($file),
					'filepath' => $amSettings['data_dir_path'] . '/tmp/mu_' . $context['aeva_mu_id'] . '/' . $file,
				);
		// Close the directory
		closedir($dir);
	}
	// Otherwise, just stack it
	else
	{
		$files[] = array(
			'filename' => $_FILES['Filedata']['name'],
			'filepath' => $_FILES['Filedata']['tmp_name'],
		);
	}

	// We got the stack, hit it!
	foreach ($files as $file)
	{
		$fame = $file['filename'];
		$name = $title = preg_replace('/[;|\s\._-]+/', ' ', substr($fame, 0, strlen($fame) - strlen(aeva_getExt($fame)) - 1));

		$fame = aeva_utf2entities($fame);
		$name = aeva_utf2entities($name);

		// Create the item
		$fOpts = array(
			'filename' => $fame,
			'filepath' => $file['filepath'],
			'destination' => aeva_getSuitableDir($_REQUEST['album']),
			'album' => (int) $_REQUEST['album'],
			'is_uploading' => $zip ? false : true,
		);
		$ret = aeva_createFile($fOpts);

		// Error?
		if (!empty($ret['error']))
		{
			$context['errors'][] = array(
				'code' => $ret['error'],
				'context' => isset($ret['error_context']) ? $ret['error_context'] : '',
				'fname' => $fOpts['filename'],
			);

			// Skip this
			continue;
		}
		else
		{
			$id_file = $ret['file'];
			$id_thumb = empty($ret['thumb']) ? 0 : $ret['thumb'];
			$id_preview = empty($ret['preview']) ? 0 : $ret['preview'];
			$time = empty($ret['time']) ? 0 : $ret['time'];
		}

		// Create the item
		$iOpts = array(
			'title' => $name,
			'id_file' => $id_file,
			'id_thumb' => $id_thumb,
			'id_preview' => $id_preview,
			'time' => $time,
			'album' => (int) $_REQUEST['album'],
			'id_member' => $user_info['id'],
			'mem_name' => $user_info['name'],
			'approved' => allowedTo('media_auto_approve_item') || allowedTo('media_moderate') ? 1 : 0,
		);

		$id_item = aeva_createItem($iOpts);

		// Add it to the list of created items
		$context['items'][] = array(
			'title' => $title,
			'id' => $id_item,
		);

		media_markSeen($id_item, 'force_insert');
	}

	media_resetUnseen();

	// Delete temporary data
	if ($zip)
		deltree($amSettings['data_dir_path'] . '/tmp/mu_' . $context['aeva_mu_id']);
}

// Modifying item's title?
function aeva_massUploadFinish()
{
	global $context, $amSettings, $user_info, $galurl, $sourcedir;

	// Unset it
	unset($_POST['submit_title_update']);

	// Nothing set?
	if (!isset($_POST['aeva_submit']))
		return aeva_massUpload();

	// Get the IDs of item
	$items = array();
	foreach ($_POST as $p => $v)
	{
		if (empty($v))
			continue;
		if (substr($p, 0, 11) == 'item_title_')
			$items[] = substr($p, 11);
		elseif (substr($p, 0, 10) == 'item_desc_')
			$items[] = substr($p, 10);
	}

	// Nothing?
	if (empty($items) && empty($desc))
		return aeva_massUpload();

	// Get their data
	$request = wesql::query('
		SELECT id_media, album_id, id_member
		FROM {db_prefix}media_items
		WHERE id_media IN ({array_int:items})',
		array('items' => array_flip(array_flip($items)))
	);
	$album = 0;
	$act_items = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$album = $row['album_id'];
		if ($row['id_member'] == $user_info['id'] || allowedTo('media_moderate'))
			$act_items[] = $row['id_media'];
	}
	wesql::free_result($request);

	if (empty($act_items))
		return aeva_massUpload();

	// All tests passed, we update them
	foreach ($act_items as $item)
	{
		$new_title = $_POST['item_title_' . $item];
		$new_desc = $_POST['item_title_' . $item];

		// Update it
		$iOpts = array(
			'id' => $item,
			'skip_log' => true,
		);
		if (isset($_POST['item_title_' . $item]))
			$iOpts['title'] = $_POST['item_title_' . $item];
		if (isset($_POST['item_desc_' . $item]))
			$iOpts['description'] = $_POST['item_desc_' . $item];

		aeva_modifyItem($iOpts);
	}

	if (file_exists($sourcedir . '/media/Aeva-Foxy.php'))
	{
		loadSource('media/Aeva-Foxy');
		aeva_foxy_notify_items($album, $act_items);
	}

	// Bye Bye
	redirectexit($galurl . 'sa=album;in=' . $album);
}

// Profile area for our gallery
function aeva_profileSummary($memID)
{
	global $amSettings, $context, $txt, $settings, $galurl, $scripturl;

	loadSource('media/Subs-Media');

	// Some CSS and JS we'll be using
	add_css_file('media', true);
	add_js('
	var galurl = "'.$galurl.'";');
	add_js_file('media.js');

	// Load the gallery template's profile summary section
	loadMediaSettings(null, true, true);

	// Load the user's stats
	$days_started = round((time() - $amSettings['installed_on'])/ 86400);

	$context['aeva_member'] = array(
		'id' => $memID,
		'items' => $context['member']['media']['total_items'],
		'coms' => $context['member']['media']['total_comments'],
		'avg_coms' => $days_started > 0 ? round(($context['member']['media']['total_comments'] / $days_started), 2) : 0,
		'avg_items' => $days_started > 0 ? round(($context['member']['media']['total_items'] / $days_started), 2) : 0,
		'recent_items' => array(),
		'user_albums' => array(),
		'top_albums' => array(),
	);

	// Load recent items
	$context['aeva_member']['recent_items'] = aeva_getMediaItems(0, $amSettings['recent_item_limit'], 'm.time_added DESC', true, array(), 'm.id_member = '. $memID);

	// Load the albums
	aeva_getAlbums('a.album_of = ' . $memID, 1, true, 'a.child_level, a.a_order', '', true, true, true);
	$context['aeva_member']['user_albums'] = &$context['aeva_albums'];

	// Load top albums
	$request = wesql::query('
		SELECT a.id_album, a.name, COUNT(m.id_media) AS total_items
		FROM {db_prefix}media_albums AS a
			LEFT JOIN {db_prefix}media_items AS m ON (m.album_id = a.id_album)
		WHERE m.approved = {int:approved}
		AND m.id_member = {int:member}
		AND {query_see_album}
		GROUP BY m.album_id
		HAVING total_items > 0
		ORDER BY total_items DESC
		LIMIT 10',
		array(
			'approved' => 1,
			'member' => $memID,
		)
	);
	$max = 0;
	while ($row = wesql::fetch_assoc($request))
	{
		$context['aeva_member']['top_albums'][] = array(
			'id' => $row['id_album'],
			'name' => $row['name'],
			'total_items' => $row['total_items'],
		);

		if ($max < $row['total_items'])
			$max = $row['total_items'];
	}
	wesql::free_result($request);

	// Get the percentage
	foreach ($context['aeva_member']['top_albums'] as $k => $v)
		$context['aeva_member']['top_albums'][$k]['percent'] = round(($v['total_items'] * 100) / $max);

	// Sub template
	loadSubTemplate('aeva_profile_summary');
	$context['page_title'] = $txt['media_profile_sum_pt'];
}

// Viewing all items
function aeva_profileItems($memID)
{
	global $amSettings, $context, $txt, $settings, $galurl, $scripturl;

	loadSource('media/Subs-Media');

	// Some CSS and JS we'll be using
	add_css_file('media', true);
	add_js('
	var galurl = "'.$galurl.'";');
	add_js_file('media.js');

	// Load the gallery template's profile items section
	loadMediaSettings(null, true, true);

	// The page index
	$_REQUEST['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $memID . ';area=aevaitems', $_REQUEST['start'], $context['member']['media']['total_items'], 20);

	// Load the items
	$context['aeva_items'] = aeva_getMediaItems((int) $_REQUEST['start'], 20, 'm.id_media DESC', true, array(), 'm.id_member = '.$memID);

	// Sub template
	loadSubTemplate('aeva_profile_viewitems');
	$context['page_title'] = $txt['media_profile_viewitems'];
}

// Viewing all comments
function aeva_profileComments($memID)
{
	global $amSettings, $context, $txt, $settings, $galurl, $scripturl;

	loadSource('media/Subs-Media');

	add_css_file('media', true);

	// Load the gallery template's profile comments section
	loadMediaSettings(null, true, true);

	// The page index
	$_REQUEST['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $memID . ';area=aevacoms', $_REQUEST['start'], $context['member']['media']['total_comments'], 20);

	// Load the items
	$context['aeva_coms'] = aeva_getMediaComments((int) $_REQUEST['start'], 20, false, array(), 'com.id_member = '.$memID);

	// Sub template
	loadSubTemplate('aeva_profile_viewcoms');
	$context['page_title'] = $txt['media_profile_viewcoms'];
}

// Viewing all comments
function aeva_profileVotes($memID)
{
	global $amSettings, $context, $txt, $settings, $galurl, $scripturl;

	loadSource('media/Subs-Media');

	add_css_file('media', true);

	// Load the gallery template's profile votes section
	loadMediaSettings(null, true, true);

	// The page index
	$_REQUEST['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$request = wesql::query('SELECT COUNT(rating) FROM {db_prefix}media_log_ratings WHERE id_member = {int:member}', array('member' => $memID));
	list ($co) = wesql::fetch_row($request);
	wesql::free_result($request);
	$context['page_index'] = constructPageIndex($scripturl . '?action=profile;u=' . $memID . ';area=aevavotes', $_REQUEST['start'], $co, 50);

	// Any messages in the associated topics?
	$request = wesql::query('
		SELECT DISTINCT COUNT(*) AS co, id_topic FROM {db_prefix}messages
		WHERE id_member = {int:id}
		GROUP BY id_topic',
		array('id' => $memID)
	);
	$nmsg = '';
	while ($row = wesql::fetch_assoc($request))
		$nmsg[$row['id_topic']] = $row['co'];
	wesql::free_result($request);

	// Load the ratings
	$req = '
		SELECT p.id_media, p.time, a.name, d.title, p.id_member, p.rating, m.real_name AS member_name, d.album_id, a.id_topic
		FROM {db_prefix}media_log_ratings AS p
		INNER JOIN {db_prefix}members AS m ON m.id_member = p.id_member
		INNER JOIN {db_prefix}media_items AS d ON d.id_media = p.id_media
		INNER JOIN {db_prefix}media_albums AS a ON a.id_album = d.album_id
		WHERE p.id_member = {int:member}
		ORDER BY p.rating DESC
		LIMIT {int:start}, {int:limit}';

	$request = wesql::query(
		$req,
		array(
			'member' => $memID,
			'start' => (int) $_REQUEST['start'],
			'limit' => 50,
		)
	);
	$context['aeva_ratingLogs'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		if (!isset($context['aeva_voter_name']))
		{
			$context['aeva_voter_name'] = $row['member_name'];
			$context['aeva_voter_id'] = $memID;
		}
		$context['aeva_ratingLogs'][] = array(
			'star' => (int) $row['rating'],
			'id_media' => $row['id_media'],
			'id_topic' => $row['id_topic'],
			'title' => $row['title'],
			'album_id' => $row['album_id'],
			'name' => $row['name'],
			'time' => timeformat($row['time']),
			'messages' => !empty($row['id_topic']) && !empty($nmsg[$row['id_topic']]) ? $nmsg[$row['id_topic']] : 0,
		);
	}
	wesql::free_result($request);

	$context['aeva_otherVoters'] = array();
	$request = wesql::query('
		SELECT COUNT(p.id_member) AS co, p.id_member, m.real_name
		FROM {db_prefix}media_log_ratings AS p
		INNER JOIN {db_prefix}members AS m ON m.id_member = p.id_member
		GROUP BY p.id_member
		ORDER BY co DESC', array()
	);
	while ($row = wesql::fetch_assoc($request))
		$context['aeva_otherVoters'][] = $row;
	wesql::free_result($request);

	// Sub template
	loadSubTemplate('aeva_profile_viewvotes');
	$context['page_title'] = $txt['media_profile_viewvotes'];
}

// Who rated what?
function aeva_whoRatedWhat()
{
	global $amSettings, $context, $scripturl, $galurl, $txt;

	// Allowed to view or not?
	if (!allowedTo('media_whoratedwhat'))
		fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));

	// Load the item
	$context['item'] = aeva_getItemData((int) $_REQUEST['in']);

	// Allowed to access item?
	if (!allowedToAccessItem($context['item']['id_media']))
		fatal_lang_error('media_item_access_denied');

	// Load the rating log
	$request = wesql::query('
		SELECT lr.id_member, lr.rating, lr.time, mem.real_name AS member_name
		FROM {db_prefix}media_log_ratings AS lr
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = lr.id_member)
		WHERE lr.id_media = {int:media}
		LIMIT {int:start}, {int:limit}',
		array(
			'media' => $context['item']['id_media'],
			'start' => (int) $_REQUEST['start'],
			'limit' => 20,
		)
	);
	$context['item']['rating_logs'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$context['item']['rating_logs'][] = array(
			'member_link' => $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=aevavotes',
			'member_name' => $row['member_name'],
			'rating' => $row['rating'],
			'time' => timeformat($row['time']),
		);
	}
	wesql::free_result($request);

	// Page index
	$_REQUEST['start'] = empty($_REQUEST['start']) ? 0 : (int) $_REQUEST['start'];
	$context['page_index'] = constructPageIndex($galurl . 'sa=whoratedwhat;in=' . $context['item']['id_media'], $_REQUEST['start'], $context['item']['voters'], 20);

	// Sub template
	loadSubTemplate('aeva_whoRatedWhat');

	add_linktree($galurl . 'sa=item;in=' . $context['item']['id_media'], $context['item']['title']);
	add_linktree($galurl . 'sa=whoratedwhat;in=' . $context['item']['id_media'], $txt['media_who_rated_what']);
	$context['page_title'] = $txt['media_who_rated_what'];
}

// Multi download
function aeva_massDownload()
{
	global $txt, $context, $amSettings, $scripturl, $user_info, $galurl;

	// This function is for taking the user's input on what to download

	// Album not loaded?
	if (empty($context['aeva_album']))
		fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));

	// Load the items...
	$items = array();
	$request = wesql::query('
		SELECT id_media, title
		FROM {db_prefix}media_items
		WHERE approved = 1
		AND album_id = {int:id}
		AND type != {string:embed}',
		array(
			'id' => $context['aeva_album']['id'],
			'embed' => 'embed',
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$items[$row['id_media']] = array('&nbsp;' . $row['title'] . '&nbsp;', true);
	wesql::free_result($request);

	// Load up the form
	$context['aeva_form_url'] = $galurl . 'sa=massdown;do=create';
	$context['aeva_form'] = array(
		'title' => array(
			'label' => '<input type="hidden" name="album" value="' . $context['aeva_album']['id'] . '">' . $txt['media_multi_download'] . ' - <a href="' . $galurl . 'sa=album;in=' . $context['aeva_album']['id'] . '">' . $context['aeva_album']['name'] . '</a>',
			'type' => 'title',
		),
		'title2' => array(
			'label' => $txt['media_multi_download_desc'],
			'type' => 'title',
			'class' => 'windowbg2',
		),
		'items' => array(
			'label' => $txt['media_items'],
			'type' => 'select',
			'multi' => true,
			'size' => min(15, count($items)),
			'options' => $items,
			'fieldname' => 'items',
		),
	);

	$context['page_title'] = $txt['media_multi_download'];
	loadSubTemplate('aeva_form');
	add_linktree(
		$galurl . 'sa=album;in=' . $context['aeva_album']['id'],
		$context['aeva_album']['name']
	);
	add_linktree(
		$galurl . 'sa=massdown;album=' . $context['aeva_album']['id'],
		$txt['media_multi_download']
	);
}

// Creating an archive..the bad guy
function aeva_massDownloadCreate()
{
	global $context, $scripturl, $txt, $user_info, $galurl, $amSettings, $time_start;

	if (empty($context['aeva_album']))
		fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));

	// Load the items...
	$album_items = array();
	$request = wesql::query('
		SELECT m.id_media, m.title, f.directory, f.id_file, f.filename, f.filesize, a.name
		FROM {db_prefix}media_items AS m
			INNER JOIN {db_prefix}media_files AS f ON (m.id_file = f.id_file)
			INNER JOIN {db_prefix}media_albums AS a ON (m.album_id = a.id_album)
		WHERE m.approved = 1
		AND m.album_id = {int:id}
		AND m.type != {string:embed}',
		array(
			'id' => $context['aeva_album']['id'],
			'embed' => 'embed',
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$album_items[$row['id_media']] = array(
			'id' => $row['id_media'],
			'title' => $row['title'],
			'id_file' => $row['id_file'],
			'filename' => $row['filename'],
			'directory' => $row['directory'],
			'filesize' => $row['filesize'],
		);
		$album_name = isset($row['name']) ? $row['name'] : 'Album';
	}
	wesql::free_result($request);

	// We got the data?
	if (isset($_POST['submit_aeva']))
	{
		if (empty($_POST['items']))
			fatal_lang_error('media_item_not_found', !empty($amSettings['log_access_errors']));

		$total_filesize = 0;
		foreach ($_POST['items'] as $k => $v)
		{
			if (!isset($album_items[$v]))
				unset($_POST['items'][$k]);

			$total_filesize += $album_items[$v]['filesize'];
		}

		if (isset($_SESSION['aeva_mdl']))
			unset($_SESSION['aeva_mdl']);

		$_SESSION['aeva_mdl'] = array(
			'items' => $_POST['items'],
			'album' => $context['aeva_album']['id'],
			'album_name' => $album_name,
			'time' => time(),
			'num_done' => 0,
		);
	}
	// We won't accept stuff which are older than 15 minutes
	elseif (!isset($_SESSION['aeva_mdl']))
		fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));

	// Try gathering the memory we need...
	@ini_set('memory_limit', '128M');

	// Let's see if we're starting afresh
	if (!empty($_SESSION['aeva_mdl']['num_done']) && !file_exists($amSettings['data_dir_path'] . '/tmp/' . $user_info['id'] . '_' . $_SESSION['aeva_mdl']['album'] . '_data'))
		$_SESSION['aeva_mdl']['num_done'] = 0;
	elseif (empty($_SESSION['aeva_mdl']['num_done']) && file_exists($amSettings['data_dir_path'] . '/tmp/' . $user_info['id'] . '_' . $_SESSION['aeva_mdl']['album'] . '_data'))
	{
		@unlink($amSettings['data_dir_path'] . '/tmp/' . $user_info['id'] . '_' . $_SESSION['aeva_mdl']['album'] . '_data');
		@unlink($amSettings['data_dir_path'] . '/tmp/' . $user_info['id'] . '_' . $_SESSION['aeva_mdl']['album'] . '_other');
	}

	// Setup the zip handler
	loadSource('media/Class-Zip');
	$zip = new aeva_zipper;

	$start = $_SESSION['aeva_mdl']['num_done'];
	$compte = count($_SESSION['aeva_mdl']['items']);
	for ($i = $start; $i < $compte; $i++)
	{
		// We need to stop?
		if (round(array_sum(explode(' ', microtime())) - array_sum(explode(' ', $time_start)), 3) > 10)
			break;

		$item = $album_items[$_SESSION['aeva_mdl']['items'][$i]];

		// Add this to the archive.
		$zip->addFileDataToCache(file_get_contents($amSettings['data_dir_path'] . '/' . $item['directory'] . '/' . aeva_getEncryptedFilename($item['filename'], $item['id_file'])), $item['filename'], $amSettings['data_dir_path'] . '/tmp/' . $user_info['id'] . '_' . $_SESSION['aeva_mdl']['album']);

		$_SESSION['aeva_mdl']['num_done']++;
	}

	// We're not done?
	if ($_SESSION['aeva_mdl']['num_done'] < $compte)
	{
		// Save it...
		$zip->saveFile($amSettings['data_dir_path'] . '/tmp/' . $user_info['id'] . '_' . $_SESSION['aeva_mdl']['album']);

		loadSubTemplate('aeva_done');
		$context['aeva_done_txt'] = sprintf($txt['media_multi_dl_wait'], $_SESSION['aeva_mdl']['num_done'], $compte);
		$context['header'] .= '
	<meta http-equiv="refresh" content="2"; url=' . $galurl . 'sa=massdown;do=create">';
	}
	else
	{
		// Archive it properly...
		$zip->saveAsZip($amSettings['data_dir_path'] . '/tmp/' . $user_info['id'] . '_' . $_SESSION['aeva_mdl']['album']);

		aeva_massDownloadSend();
	}
}

// Downloading the archive...
function aeva_massDownloadSend()
{
	global $context, $amSettings, $user_info;

	if (!file_exists($amSettings['data_dir_path'] . '/tmp/' . $user_info['id'] . '_' . $_SESSION['aeva_mdl']['album'] . '_data'))
		die('Hacking attempt');

	$path = $amSettings['data_dir_path'] . '/tmp/' . $user_info['id'] . '_' . $_SESSION['aeva_mdl']['album'] . '_data';
	$filename = '[' . date('Y-m-d', $_SESSION['aeva_mdl']['time']) . '] ' . $_SESSION['aeva_mdl']['album_name'] . '.zip';

	if (ini_get('zlib.output_compression'))
		ini_set('zlib.output_compression', 'Off');

	while (@ob_end_clean());

	// Get the headers....
	header('Pragma: ');
	if (!$context['browser']['is_gecko'])
		header('Content-Transfer-Encoding: binary');
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT');
	header('Accept-Ranges: bytes');
	header('Content-Length: ' . filesize($path));
	header('Content-Encoding: none');
	header('Connection: close');
	header('ETag: ' . md5_file($path));
	header('Content-Type: application/octet' . ($context['browser']['is_ie'] || $context['browser']['is_opera'] ? '' : '-') . 'stream');

	$is_chrome = $context['browser']['is_safari'] && stripos($_SERVER['HTTP_USER_AGENT'], 'chrome') !== false;
	$filename = aeva_entities2utf($filename);

	// Stupid Safari doesn't support UTF-8 filenames...
	if ($context['browser']['is_safari'] && !$is_chrome)
		$filename = utf8_decode($filename);

	$att = 'Content-Disposition: attachment; filename';

	if ($context['browser']['is_firefox'])
		header($att . '*="UTF-8\'\'' . $filename . '"');
	elseif ($context['browser']['is_ie'] || $is_chrome)
		header($att . '="' . rawurlencode($filename) . '"');
	else
		header($att . '="' . $filename . '"');

	// If the file is over 1.5MB, readfile() may have some issues.
	if (filesize($path) > 1572864 || @readfile($path) == null)
	{
		if ($file = fopen($path, 'rb'))
		{
			while (!feof($file))
			{
				echo @fread($file, 8192);
				flush();
			}
			@fclose($file);
		}
		else
			die('Something went wrong... ' . $path);
	}

	@unlink($path);

	// DIE!
	die;
}

?>