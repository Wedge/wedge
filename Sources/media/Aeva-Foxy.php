<?php
/****************************************************************
* Foxy! extras for Aeva Media									*
* © Nao [noisen.com]											*
*****************************************************************
* Aeva-Foxy.php													*
* A plugin that shows foxy playlists for your media content		*
*****************************************************************
* Users of this software are bound by the terms of the			*
* Aeva Media license. You can view it in the license_am.txt		*
* file, or online at http://noisen.com/license-am2.php			*
*																*
* For support and updates, go to http://aeva.noisen.com			*
****************************************************************/

/*

	Functions found in Foxy!

	aeva_foxy_playlist()
	aeva_foxy_playlists()
	aeva_foxy_my_playlists()
	aeva_foxy_item_page_playlists($item)
	aeva_foxy_show_playlists($id, $pl)
	aeva_foxy_get_board_list($current_board)
	aeva_foxy_latest_topic($id_owner, $current_album = 0)
	aeva_foxy_create_topic($id_album, $album_name, $board, $lock = false, $mark_as_read = false)
	aeva_foxy_notify_items($album, $items)
	aeva_foxy_remote_image($link)
	aeva_foxy_remote_preview(&$my_file, &$local_file, &$dir, &$name, &$width, &$height)
	aeva_foxy_rss()
	aeva_foxy_get_xml_items()
	aeva_foxy_get_xml_comments()
	aeva_foxy_album($id, $type, $wid = 0, $details = '', $sort = 'm.id_media DESC', $field_sort = 0)
	aeva_foxy_fill_player(&$playlist, $swo = 1, $type = 'audio', &$details, $play = 0, $wid = 470, $hei = 430, $thei = 70)

*/

if (!defined('SMF'))
	die('Hacking attempt...');

///////////////////////////////////////////////////////////////////////////////
// USER PLAYLISTS
///////////////////////////////////////////////////////////////////////////////

function aeva_foxy_playlist()
{
	global $context, $scripturl, $txt, $user_info, $galurl, $settings;

	$context['aeva_header']['data']['title'] = $txt['media_playlist'];
	$context['page_title'] = $txt['media_playlist'];
	$id = empty($_GET['in']) ? 0 : (int) $_GET['in'];

	if (!isset($_GET['new']) && !isset($_GET['edit']) && !isset($_GET['delete']) && !isset($_GET['from'], $_GET['to']) && !isset($_GET['des']))
	{
		loadSubTemplate('aeva_playlist');
		$context['aeva_foxy_rendered_playlist'] = $id ? aeva_foxy_album($id, 'playl') : aeva_foxy_playlists();
		return;
	}

	$context['aeva_form_url'] = $scripturl . '?action=media;sa=playlists' . ($id ? ';in=' . $id . ';edit' : ';new') . ';' . $context['session_var'] . '=' . $context['session_id'];
	$pname = $txt['media_new_playlist'];
	$pdesc = '';

	if (isset($_GET['delete']))
	{
		// Check the session
		checkSession('get');

		$request = wesql::query('
			DELETE FROM {db_prefix}media_playlists
			WHERE id_playlist = {int:pl}',
			array('pl' => $id)
		);
		redirectexit($scripturl . '?action=media;sa=playlists');
	}

	loadSource('Class-Editor');

	if ($id)
	{
		$request = wesql::query('
			SELECT name, description
			FROM {db_prefix}media_playlists
			WHERE id_playlist = {int:pl}',
			array('pl' => $id)
		);
		list ($pname, $pdesc) = wesql::fetch_row($request);
		wesql::free_result($request);

		$pname = wedit::un_preparsecode($pname);
		$pdesc = wedit::un_preparsecode($pdesc);

		// My playlist's contents
		$request = wesql::query('
			SELECT p.id_media, p.play_order, p.description, m.title, a.name
			FROM {db_prefix}media_playlist_data AS p
			INNER JOIN {db_prefix}media_playlists AS pl ON (pl.id_playlist = p.id_playlist)
			INNER JOIN {db_prefix}media_items AS m ON (p.id_media = m.id_media)
			INNER JOIN {db_prefix}media_albums AS a ON (m.album_id = a.id_album)
			WHERE (pl.id_member = {int:me} ' . ($user_info['is_admin'] ? 'OR pl.id_member = 0' : 'AND pl.id_member != 0') . ')
			AND p.id_playlist = {int:playlist}
			ORDER BY p.play_order ASC',
			array(
				'me' => $user_info['id'],
				'playlist' => $id,
			)
		);

		$my_playlist_data = $pos = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$my_playlist_data[$row['play_order']] = array(
				'id' => $row['id_media'],
				'title' => westr::htmlspecialchars($row['title']),
				'description' => $row['description'],
				'album_name' => westr::htmlspecialchars($row['name']),
				'play_order' => $row['play_order'],
			);
			$pos[] = $row['play_order'];
		}
		wesql::free_result($request);

		if (isset($_GET['des']))
		{
			checkSession('get');
			foreach ($my_playlist_data as $m)
				if ($m['id'] == $_GET['des'])
					$desid = $m['id'];
			if (isset($desid, $_POST['txt' . $desid]))
			{
				wesql::query('
					UPDATE {db_prefix}media_playlist_data
					SET description = {string:description}
					WHERE id_playlist = {int:pl} AND id_media = {int:media}',
					array(
						'pl' => $id,
						'media' => $desid,
						'description' => westr::htmlspecialchars(aeva_utf2entities($_POST['txt' . $desid], false)),
					)
				);
				redirectexit($context['aeva_form_url']);
			}
		}

		if (isset($_GET['from'], $_GET['to']))
		{
			checkSession('get');
			$from = (int) $_GET['from'];
			$to = (int) $_GET['to'];
			if (isset($my_playlist_data[$from], $my_playlist_data[$to]))
			{
				wesql::query('
					UPDATE {db_prefix}media_playlist_data
					SET id_media = {int:media}, description = {string:description}
					WHERE id_playlist = {int:pl} AND play_order = {int:ord}',
					array(
						'pl' => $id,
						'ord' => $to,
						'media' => $my_playlist_data[$from]['id'],
						'description' => $my_playlist_data[$from]['description'],
					)
				);
				wesql::query('
					UPDATE {db_prefix}media_playlist_data
					SET id_media = {int:media}, description = {string:description}
					WHERE id_playlist = {int:pl} AND play_order = {int:ord}',
					array(
						'pl' => $id,
						'ord' => $from,
						'media' => $my_playlist_data[$to]['id'],
						'description' => $my_playlist_data[$to]['description'],
					)
				);
				redirectexit($context['aeva_form_url']);
			}
		}

		$context['aeva_extra_data'] = '
	<script><!-- // --><![CDATA[
		function foxyComment(id)
		{
			var sh1 = document.getElementById("foxyDescription" + id);
			var sh2 = document.getElementById("foxyComment" + id);
			if (sh1) sh1.style.display = "none";
			if (sh2) sh2.style.display = "block";
			return false;
		}
	// ]]></script>
	<table class="cp4 cs0 center" style="width: 90%; padding-top: 16px">';
		$curpos = $prev = 0;
		foreach ($my_playlist_data as $m)
		{
			$next = $curpos < count($pos)-1 ? $pos[++$curpos] : 0;
			$context['aeva_extra_data'] .= '<tr class="windowbg' . ($curpos % 2 == 0 ? '' : '2') . '"><td class="right">' . $m['play_order'] . '.</td>
			<td><strong><a href="' . $galurl . 'sa=item;in=' . $m['id'] . '">' . $m['title'] . '</a></strong> <a href="#" onclick="return foxyComment(' . $m['id'] . ');"><img src="' . $settings['default_images_url'] . '/aeva/user_comment.png"></a>' . (!empty($m['description']) ? '
			<div style="display: block" id="foxyDescription' . $m['id'] . '">' . parse_bbc($m['description']) . '</div>' : '') . '
			<div style="display: none" id="foxyComment' . $m['id'] . '">
				<form action="' . $galurl . 'sa=playlists;in=' . $id . ';edit;des=' . $m['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" method="post">
					<textarea name="txt' . $m['id'] . '" cols="60" rows="3">' . $m['description'] . '</textarea>
					<input type="submit" value="' . $txt['media_submit'] . '">
				</form>
			</div></td><td class="center">' .
			(empty($prev) ? '' : '<a href="' . $galurl . 'sa=playlists;in=' . $id . ';from=' . $m['play_order'] . ';to=' . $prev . ';' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['default_images_url'] . '/sort_up.gif"></a>') . '</td><td class="center">' .
			(empty($next) ? '' : '<a href="' . $galurl . 'sa=playlists;in=' . $id . ';from=' . $m['play_order'] . ';to=' . $next . ';' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['default_images_url'] . '/sort_down.gif"></a>') . '</td><td class="center">' .
			'<a href="' . $galurl . 'sa=item;in=' . $m['id'] . ';premove=' . $id . ';redirpl;' . $context['session_var'] . '=' . $context['session_id'] . '" style="text-decoration: none"><img src="' . $settings['images_aeva'] . '/delete.png" style="vertical-align: bottom"> ' . $txt['media_delete_this_item'] . '</a>' .
			'</td></tr>';
			$prev = $curpos > 0 ? $pos[$curpos-1] : 0;
		}

		$context['aeva_extra_data'] .= '</table>';
	}

	// Construct the form
	$context['aeva_form'] = array(
		'title' => array(
			'label' => $txt['media_add_title'],
			'fieldname' => 'title',
			'type' => 'text',
			'value' => $pname,
		),
		'desc' => array(
			'label' => $txt['media_add_desc'],
			'subtext' => $txt['media_add_desc_subtxt'],
			'fieldname' => 'desc',
			'type' => 'textbox',
			'custom' => 'cols="50" rows="6"',
			'value' => $pdesc,
		),
	);

	// Submitting?
	if (isset($_POST['submit_aeva']) && allowedTo('media_add_playlists'))
	{
		$name = westr::htmlspecialchars($_POST['title']);
		$desc = westr::htmlspecialchars(aeva_utf2entities($_POST['desc'], false, 0));
		wedit::preparsecode($name);
		wedit::preparsecode($desc);

		if ($id)
		{
			// Check the session
			checkSession('get');

			wesql::query('
				UPDATE {db_prefix}media_playlists
				SET name = {string:name}, description = {string:description}
				WHERE id_playlist = {int:pl}',
				array(
					'pl' => $id,
					'name' => $name,
					'description' => $desc
				)
			);
		}
		else
			$id = wesql::insert('',
				'{db_prefix}media_playlists',
				array('name' => 'string', 'description' => 'string', 'id_member' => 'int'),
				array($name, $desc, $user_info['id'])
			);
		redirectexit($scripturl . '?action=media;sa=playlists' . ($id ? ';done=' . $id : ''));
	}

	loadSubTemplate('aeva_form');
}

function aeva_foxy_playlists()
{
	global $amSettings, $context, $txt, $scripturl, $user_info, $settings, $galurl;

	$context['page_title'] = $txt['media_playlists'];
	$context['aeva_header']['data']['title'] = $txt['media_playlists'];

	// My playlists -- which I can edit or delete.
	$request = wesql::query('
		SELECT
			pl.id_playlist, pl.name, pl.views, i.title,
			COUNT(pld.id_media) AS items, COUNT(DISTINCT a.id_album) AS albums
		FROM {db_prefix}media_playlists AS pl
		LEFT JOIN {db_prefix}media_playlist_data AS pld ON (pld.id_playlist = pl.id_playlist)
		LEFT JOIN {db_prefix}media_items AS i ON (i.id_media = pld.id_media)
		LEFT JOIN {db_prefix}media_albums AS a ON (i.album_id = a.id_album)
		WHERE pl.id_member = {int:me} ' . ($user_info['is_admin'] ? 'OR pl.id_member = 0' : 'AND pl.id_member != 0') . '
		GROUP BY pl.id_playlist
		ORDER BY pl.id_playlist ASC',
		array('me' => $user_info['id'])
	);

	$my_playlists = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$my_playlists[$row['id_playlist']] = array(
			'id' => $row['id_playlist'],
			'name' => westr::htmlspecialchars($row['name']),
			'views' => $row['views'],
			'num_items' => $row['items'],
			'num_albums' => $row['albums'],
		);
	}
	wesql::free_result($request);

	// Count how many playlists any user can view (based to their permissions)
	$request = wesql::query('
		SELECT COUNT(DISTINCT pld.id_playlist)
		FROM {db_prefix}media_playlist_data AS pld
		INNER JOIN {db_prefix}media_items AS i ON (i.id_media = pld.id_media)
		INNER JOIN {db_prefix}media_albums AS a ON (i.album_id = a.id_album AND {query_see_album_hidden})',
		array()
	);
	list ($num_items) = wesql::fetch_row($request);
	wesql::free_result($request);

	$start = !empty($_GET['start']) ? (int) $_GET['start'] : 0;
	$context['aeva_page_index'] = constructPageIndex($galurl . 'sa=playlists', $start, $num_items, 20);

	// List current page of playlists
	$request = wesql::query('
		SELECT
			pld.id_playlist, pl.name, pl.id_member, m.real_name AS owner_name, pl.description, pl.views, i.title,
			COUNT(pld.id_media) AS items, COUNT(DISTINCT a.id_album) AS albums
		FROM {db_prefix}media_playlist_data AS pld
		INNER JOIN {db_prefix}media_playlists AS pl ON (pl.id_playlist = pld.id_playlist)
		INNER JOIN {db_prefix}media_items AS i ON (i.id_media = pld.id_media)
		INNER JOIN {db_prefix}media_albums AS a ON (i.album_id = a.id_album AND {query_see_album_hidden})
		LEFT JOIN {db_prefix}members AS m ON (m.id_member = pl.id_member)
		GROUP BY pld.id_playlist
		ORDER BY pl.id_playlist ASC
		LIMIT {int:start},20',
		array('start' => $start)
	);

	$playlists = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$playlists[$row['id_playlist']] = array(
			'id' => $row['id_playlist'],
			'name' => westr::htmlspecialchars($row['name']),
			'owner_id' => $row['id_member'],
			'owner_name' => $row['owner_name'],
			'description' => empty($row['description']) ? '' : parse_bbc(westr::cut($row['description'], 150, true, false)),
			'views' => $row['views'],
			'num_items' => $row['items'],
			'num_albums' => $row['albums'],
		);
	}
	wesql::free_result($request);

	// This query could be cached later on...
	$result = wesql::query('
		SELECT id_album
		FROM {db_prefix}media_albums AS a
		WHERE approved = 1
		AND featured = 0
		LIMIT 1',array()
	);
	$context['show_albums_link'] = wesql::num_rows($result) > 0;
	wesql::free_result($result);

	$pi = '
	<div class="pagelinks page_index">
		' . $txt['media_pages'] . ': ' . $context['aeva_page_index'] . '
	</div>';

	$o = '
	<div id="aeva_toplinks">
		<we:cat>
			<img src="' . $settings['images_aeva'] . '/house.png"> <b><a href="' . $galurl . '">' . $txt['media_home'] . '</a></b>' . ($context['show_albums_link'] ? ' -
			<img src="' . $settings['images_aeva'] . '/album.png"> <b><a href="' . $galurl . 'sa=vua">' . $txt['media_albums'] . '</a></b>' : '') . (empty($amSettings['disable_playlists']) ? ' -
			<img src="' . $settings['images_aeva'] . '/playlist.png"> <b>' . $txt['media_playlists'] . '</b>' : '') . '
		</we:cat>
	</div>';

	if (allowedTo('media_add_playlists'))
	{
		if (isset($_GET['done']) && (int) $_GET['done'] > 0)
			$o .= '
	<div class="notice warn_watch">' . $txt['media_playlist_done'] . '</div>';

		$o .= '
	<we:title2>
		' . $txt['media_my_playlists'] . '
	</we:title2>
	<table class="aeva_my_playlists w100 cp4 cs0">';
		$res = 0;
		foreach ($my_playlists as $p)
		{
			if ($res == 0)
				$o .= '
	<tr>';
			$o .= '
		<td>
			<strong><a href="' . $galurl . 'sa=playlists;in=' . $p['id'] . '">' . $p['name'] . '</a></strong>
			<br><span class="smalltext">' . sprintf($txt['media_items_from_album' . ($p['num_albums'] == 1 ? '' : 's')], $p['num_items'], $p['num_albums']) . '<br>
			<a href="' . $galurl . 'sa=playlists;in=' . $p['id'] . ';edit;' . $context['session_var'] . '=' . $context['session_id'] . '" style="text-decoration: none"><img src="' . $settings['images_aeva'] . '/camera_edit.png" style="vertical-align: bottom"> ' . $txt['media_edit_this_item'] . '</a>
			<a href="' . $galurl . 'sa=playlists;in=' . $p['id'] . ';delete;' . $context['session_var'] . '=' . $context['session_id'] . '" style="text-decoration: none" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ');"><img src="' . $settings['images_aeva'] . '/delete.png" style="vertical-align: bottom"> ' . $txt['media_delete_this_item'] . '</a></span>
		</td>';
			if ($res == 3)
				$o .= '
	</tr>';
			$res = ($res + 1) % 4;
		}
		$o .= ($res != 0 ? '
	</tr>' : '') . '
	</table>
	<div style="padding: 8px"><img src="' . $settings['images_aeva'] . '/camera_add.png"> <b><a href="' . $galurl . 'sa=playlists;new">' . $txt['media_new_playlist'] . '</a></b></div>';
	}
	$o .= '
	<h3 class="titlebg"><span class="left"><span></span></span>
		' . $txt['media_playlists'] . '
	</h3>' . $pi;

	if (empty($playlists))
		$o .= $txt['media_tag_no_items'];
	else
	{
		$o .= '<div style="overflow: hidden">';
		foreach ($playlists as $p)
			$o .= '
	<div class="aeva_playlist_list">
		<strong><a href="' . $galurl . 'sa=playlists;in=' . $p['id'] . '">' . $p['name'] . '</a></strong> (' . sprintf($txt['media_items_from_album' . ($p['num_albums'] == 1 ? '' : 's')], $p['num_items'], $p['num_albums']) . ') ' . (empty($p['owner_id']) ? '' : '
		' . $txt['media_by'] . ' <a href="' . $scripturl . '?action=profile;u=' . $p['owner_id'] . ';area=aeva">' . $p['owner_name'] . '</a>') . (empty($p['description']) ? '' : '
		<div class="mg_desc" style="padding-left: 16px">' . $p['description'] . '</div>') . '
	</div>';
		$o .= '</div>';
		$o .= $pi;
	}

	return $o;
}

function aeva_foxy_my_playlists()
{
	global $user_info;

	$request = wesql::query('
		SELECT
			pl.id_playlist, pl.name, pl.views, i.title,
			COUNT(pld.id_media) AS items, COUNT(DISTINCT a.id_album) AS albums
		FROM {db_prefix}media_playlists AS pl
		LEFT JOIN {db_prefix}media_playlist_data AS pld ON (pld.id_playlist = pl.id_playlist)
		LEFT JOIN {db_prefix}media_items AS i ON (i.id_media = pld.id_media)
		LEFT JOIN {db_prefix}media_albums AS a ON (i.album_id = a.id_album)
		WHERE pl.id_member = {int:me} ' . ($user_info['is_admin'] ? 'OR pl.id_member = 0' : 'AND pl.id_member != 0') . '
		GROUP BY pl.id_playlist
		ORDER BY pl.id_playlist ASC',
		array('me' => $user_info['id'])
	);

	$my_playlists = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$my_playlists[$row['id_playlist']] = array(
			'id' => $row['id_playlist'],
			'name' => westr::htmlspecialchars($row['name']),
			'views' => $row['views'],
			'num_items' => $row['items'],
			'num_albums' => $row['albums'],
		);
	}
	wesql::free_result($request);
	return $my_playlists;
}

function aeva_foxy_item_page_playlists($item)
{
	global $context, $txt, $scripturl, $user_info, $settings, $galurl;

	// Any playlist being deleted?
	if (isset($_GET['premove']))
	{
		checkSession('get');

		wesql::query('
			DELETE FROM {db_prefix}media_playlist_data
			WHERE id_playlist = {int:playlist} AND id_media = {int:media}',
			array(
				'playlist' => (int) $_GET['premove'],
				'media' => $item
			)
		);
		if (isset($_GET['redirpl']))
			redirectexit($galurl . 'sa=playlists;in=' . $_GET['premove'] . ';edit;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Any playlist being added?
	if (isset($_POST['add_to_playlist']))
	{
		$pl = (int) $_POST['add_to_playlist'];
		$items = (array) $item;
		if (!allowedTo('media_add_playlists') || $pl <= 0)
			fatal_lang_error('media_edit_denied');

		// Make sure this playlist belongs to self...
		if (!$user_info['is_admin'])
		{
			$request = wesql::query('
				SELECT id_member
				FROM {db_prefix}media_playlists
				WHERE id_playlist = {int:pl}',
				array('pl' => $pl)
			);
			list ($owner) = wesql::fetch_row($request);
			wesql::free_result($request);
		}

		if ($user_info['is_admin'] || $owner == $user_info['id'])
			foreach ($items as $it)
				wesql::insert('ignore',
					'{db_prefix}media_playlist_data',
					array('id_playlist' => 'int', 'id_media' => 'int'),
					array($pl, $it)
				);
		if (is_array($item))
			return;
	}

	// My playlists -- which I can edit or delete.
	$my_playlists = aeva_foxy_my_playlists();

	// All playlists that contain the current item
	$request = wesql::query('
		SELECT pld.id_playlist, pl.name, pl.id_member, m.real_name AS owner_name, COUNT(pld2.id_media) AS items
		FROM {db_prefix}media_playlist_data AS pld
		INNER JOIN {db_prefix}media_playlists AS pl ON (pl.id_playlist = pld.id_playlist)
		INNER JOIN {db_prefix}media_playlist_data AS pld2 ON (pld2.id_playlist = pld.id_playlist)
		INNER JOIN {db_prefix}media_items AS i ON (i.id_media = pld.id_media)
		LEFT JOIN {db_prefix}members AS m ON (m.id_member = pl.id_member)
		WHERE pld.id_media = {int:media}
		GROUP BY pld.id_playlist
		ORDER BY pl.id_playlist ASC',
		array('media' => $item)
	);

	$playlists = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$playlists[$row['id_playlist']] = array(
			'id' => $row['id_playlist'],
			'name' => westr::htmlspecialchars($row['name']),
			'owner_id' => $row['id_member'],
			'owner_name' => $row['owner_name'],
			'num_items' => $row['items'],
		);
		unset($my_playlists[$row['id_playlist']]);
	}
	wesql::free_result($request);

	return array('mine' => $my_playlists, 'current' => $playlists);
}

///////////////////////////////////////////////////////////////////////////////
// LINKED TOPICS / NOTIFICATIONS
///////////////////////////////////////////////////////////////////////////////

function aeva_foxy_get_board_list($current_board)
{
	global $user_info, $txt;

	$topic_boards = $topic_cats = array();
	$write_boards = boardsAllowedTo('post_new');
	if (!empty($write_boards))
	{
		$request = wesql::query('
			SELECT b.id_board, b.name, b.child_level, c.name AS cat_name, c.id_cat
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
			WHERE {query_see_board}' . (!in_array(0, $write_boards) ? '
				AND b.id_board IN ({array_int:write_boards})' : '') . '
			ORDER BY b.board_order',
			array('write_boards' => $write_boards)
		);

		while ($row = wesql::fetch_assoc($request))
		{
			if (!isset($topic_cats[$row['id_cat']]))
				$topic_cats[$row['id_cat']] = array (
					'name' => strip_tags($row['cat_name']),
					'boards' => array(),
				);

			$topic_cats[$row['id_cat']]['boards'][] = array(
				'id' => $row['id_board'],
				'name' => westr::cut(strip_tags($row['name']), 50) . '&nbsp;',
				'category' => strip_tags($row['cat_name']),
				'child_level' => $row['child_level'],
				'selected' => !empty($current_board) && $current_board == $row['id_board'],
			);
		}
		wesql::free_result($request);

		if (empty($txt['media']))
			loadLanguage('Media');

		$topic_boards[0] = array($txt['media_no_topic_board'], $current_board === 0);
		foreach ($topic_cats as $c => $category)
		{
			$topic_boards['begin_' . $c] = array($category['name'], false, 'begin');
			foreach ($category['boards'] as $board)
				$topic_boards[$board['id']] = array(($board['child_level'] > 0 ? str_repeat('==', $board['child_level']-1) . '=&gt; ' : '') . $board['name'], $board['selected']);
			$topic_boards['end_' . $c] = array($category['name'], false, '');
		}
	}

	unset($topic_cats);
	return $topic_boards;
}

// Get the board ID and locked status for the last linked topic created by Foxy!
// If we're editing the last created album, use linked topic from the album before.
function aeva_foxy_latest_topic($id_owner, $current_album = 0)
{
	$request = wesql::query('
		SELECT t.id_board, t.locked
		FROM {db_prefix}topics AS t
		RIGHT JOIN {db_prefix}media_albums AS a ON a.id_topic = t.id_topic
		WHERE a.album_of = {int:member}' . ($current_album > 0 ? ' AND a.id_album != {int:album}' : '') . '
		ORDER BY a.id_album DESC
		LIMIT 1',
		array(
			'member' => (int) $id_owner,
			'album' => (int) $current_album,
		)
	);

	if (wesql::num_rows($request) == 0)
		return array(0, 0);

	list ($id_board, $locked) = wesql::fetch_row($request);
	wesql::free_result($request);

	return array($id_board, $locked);
}

// Create a linked topic based on requested details. Neat thing: the locked status
// is inherited from your previous linked topic's, if any.
function aeva_foxy_create_topic($id_album, $album_name, $board, $lock = false, $mark_as_read = false)
{
	global $txt, $user_info;

	loadSource('Subs-Post');

	if (empty($txt['media']))
		loadLanguage('Media');

	// Show an album playlist, with all details except its name (because it's already in the subject.)
	$msgOptions = array(
		'subject' => $txt['media_topic'] . ': ' . $album_name,
		'body' => '[media id=' . $id_album . ' type=media_album details=no_name]',
		'icon' => 'xx',
		'smileys_enabled' => 1,
	);
	$topicOptions = array(
		'board' => $board,
		'lock_mode' => $lock,
		'mark_as_read' => $mark_as_read,
	);
	$posterOptions = array(
		'id' => $user_info['id'],
		'update_post_count' => true,
	);

	createPost($msgOptions, $topicOptions, $posterOptions);

	$id_topic = isset($topicOptions['id']) ? $topicOptions['id'] : 0;

	wesql::query('
		UPDATE {db_prefix}media_albums
		SET id_topic = {int:topic}
		WHERE id_album = {int:album}',
		array(
			'topic' => (int) $id_topic,
			'album' => (int) $id_album,
		)
	);

	return $id_topic;
}

function aeva_foxy_notify_items($album, $items)
{
	global $user_info, $txt;

	$request = wesql::query('
		SELECT a.name, a.id_topic, t.id_board
		FROM {db_prefix}media_albums AS a
		INNER JOIN {db_prefix}topics AS t ON (t.id_topic = a.id_topic)
		WHERE a.id_album = {int:album}',
		array('album' => (int) $album)
	);
	list ($name, $linked_topic, $linked_board) = wesql::fetch_row($request);
	wesql::free_result($request);

	if (empty($linked_topic) || empty($linked_board))
		return;

	loadSource('Subs-Post');

	if (empty($txt['media']))
		loadLanguage('Media');

	$msgOptions = array(
		'subject' => $txt['media_topic'] . ': ' . westr::htmlspecialchars(aeva_utf2entities($name, false)),
		'body' => '[media id=' . implode(',', $items) . ' type=box]',
		'icon' => 'xx',
		'smileys_enabled' => 1,
	);
	$topicOptions = array(
		'id' => $linked_topic,
		'board' => $linked_board,
	);
	$posterOptions = array(
		'id' => $user_info['id'],
		'update_post_count' => true,
	);

	createPost($msgOptions, $topicOptions, $posterOptions);
}

///////////////////////////////////////////////////////////////////////////////
// EMBEDDING REMOTELY HOSTED PICTURES
///////////////////////////////////////////////////////////////////////////////

function aeva_foxy_remote_image($link)
{
	global $force_id, $embed_folder, $embed_album;

	$force_id = false;
	$embed_folder = '';
	$embed_album = isset($_REQUEST['album']) ? (int) $_REQUEST['album'] : 0;

	$id = aeva_download_thumb($link, basename(urldecode(rtrim($link, '/'))), true);
	return is_array($id) ? $id : false;
}

function aeva_foxy_remote_preview(&$my_file, &$local_file, &$dir, &$name, &$width, &$height)
{
	global $amSettings, $embed_album;

	if (!($resizedpic = $my_file->createThumbnail($local_file . '1.jpg', min($width, $amSettings['max_preview_width']), min($height, $amSettings['max_preview_height']))))
		return 0;

	list ($pwidth, $pheight) = $resizedpic->getSize();
	$fsize = $resizedpic->getFileSize();
	$resizedpic->close();

	$pwidth = empty($pwidth) ? $amSettings['max_preview_width'] : $pwidth;
	$pheight = empty($pheight) ? $amSettings['max_preview_height'] : $pheight;

	$id_preview = aeva_insertFileID(
		0, $fsize, 'preview_' . $name . '.jpg', $pwidth, $pheight,
		substr($dir, strlen($amSettings['data_dir_path']) + 1), $embed_album
	);

	@rename($local_file . '1.jpg', $dir . '/' . aeva_getEncryptedFilename('preview_' . $name . '.jpg', $id_preview));

	return $id_preview;
}

///////////////////////////////////////////////////////////////////////////////
// MEDIA RSS FEEDS
///////////////////////////////////////////////////////////////////////////////

function aeva_foxy_rss()
{
	global $context, $scripturl, $txt, $modSettings, $user_info, $amSettings;
	global $query_this, $forum_version, $cdata_override, $db_show_debug;

	$amSettings['max_rss_items'] = !isset($amSettings['max_rss_items']) ? 10 : $amSettings['max_rss_items'];
	if (empty($amSettings['max_rss_items']))
		return;

	loadSource('Feed');

	// Default to latest 10. No more than 255, please.
	$_GET['limit'] = empty($_GET['limit']) || (int) $_GET['limit'] < 1 ? 10 : min((int) $_GET['limit'], $amSettings['max_rss_items']);
	$type = !isset($_GET['type']) || $_GET['type'] != 'comments' ? 'items' : 'comments';

	// Handle the cases where an album, albums, or other things are asked for.
	$query_this = 1;
	if (isset($_REQUEST['user']))
	{
		$_REQUEST['user'] = explode(',', $_REQUEST['user']);
		foreach ($_REQUEST['user'] as $i => $c)
			$_REQUEST['user'][$i] = (int) $c;

		if (count($_REQUEST['user']) == 1 && !empty($_REQUEST['user'][0]))
		{
			$request = wesql::query('
				SELECT real_name
				FROM {db_prefix}members
				WHERE id_member = {int:mem}
				LIMIT 1',
				array('mem' => (int) $_REQUEST['user'][0])
			);
			list ($feed_title) = wesql::fetch_row($request);
			wesql::free_result($request);

			$feed_title = ' - ' . strip_tags($feed_title);
		}

		$query_this = $type == 'items' ? (isset($_REQUEST['albums']) ? 'a.album_of IN ({array_int:memlist})' : 'm.id_member IN ({array_int:memlist})') : 'c.id_member IN ({array_int:memlist})';
	}
	elseif (!empty($_REQUEST['item']) && $type == 'comments')
	{
		$_REQUEST['item'] = explode(',', $_REQUEST['item']);
		foreach ($_REQUEST['item'] as $i => $b)
			$_REQUEST['item'][$i] = (int) $b;

		$siz = count($_REQUEST['item']);
		$request = wesql::query('
			SELECT m.id_media, m.title
			FROM {db_prefix}media_items AS m
			INNER JOIN {db_prefix}media_albums AS a ON (a.id_album = m.album_id)
			WHERE (m.id_media ' . ($siz == 1 ? '= {int:media}' : 'IN ({array_int:media})') . ')
				AND {query_see_album}
			LIMIT ' . $siz,
			array('media' => $siz == 1 ? $_REQUEST['item'][0] : $_REQUEST['item'])
		);

		// Either the item specified doesn't exist or you have no access.
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('media_accessDenied', false);

		$items = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if ($siz == 1)
				$feed_title = ' - ' . strip_tags($row['title']);
			$items[] = $row['id_media'];
		}
		wesql::free_result($request);

		if (!empty($items))
			$query_this = count($items) == 1 ? 'c.id_media = ' . $items[0] : 'c.id_media IN (' . implode(', ', $items) . ')';
	}
	elseif (!empty($_REQUEST['album']))
	{
		$_REQUEST['album'] = explode(',', $_REQUEST['album']);
		foreach ($_REQUEST['album'] as $i => $b)
			$_REQUEST['album'][$i] = (int) $b;

		$siz = count($_REQUEST['album']);
		$request = wesql::query('
			SELECT a.id_album, a.name
			FROM {db_prefix}media_albums AS a
			WHERE (a.id_album ' . ($siz == 1 ? '= {int:album}' : 'IN ({array_int:album})') . (isset($_REQUEST['children']) ? '
				OR a.parent ' . ($siz == 1 ? '= {int:album}' : 'IN ({array_int:album})') : '') . ')
				AND {query_see_album}' . (isset($_REQUEST['children']) ? '' : '
			LIMIT ' . $siz),
			array('album' => $siz == 1 ? $_REQUEST['album'][0] : $_REQUEST['album'])
		);

		// Either the album specified doesn't exist or you have no access.
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('media_accessDenied', false);

		$albums = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if ($siz == 1 && (!isset($_REQUEST['children']) || $row['id_album'] == $_REQUEST['album'][0]))
				$feed_title = ' - ' . strip_tags($row['name']) . (isset($_REQUEST['children']) ? ' ' . $txt['media_foxy_and_children'] : '');
			$albums[] = $row['id_album'];
		}
		wesql::free_result($request);

		if (!empty($albums))
		{
			if ($type == 'items')
				$query_this = count($albums) == 1 ? 'a.id_album = ' . $albums[0] : 'a.id_album IN (' . implode(', ', $albums) . ')';
			else
				$query_this = count($albums) == 1 ? 'c.id_album = ' . $albums[0] : 'c.id_album IN (' . implode(', ', $albums) . ')';
		}
	}
	else
		$query_this = '{query_see_album}';

	// We only want some information, not all of it.
	$cachekey = array($_GET['limit']);
	foreach (array('album', 'albums', 'user') as $var)
		if (isset($_REQUEST[$var]))
			$cachekey[] = $_REQUEST[$var];
	$cachekey = md5(serialize($cachekey) . (!empty($query_this) ? $query_this : ''));

	// Get the associative array representing the xml.
	if ($cache_it = $user_info['is_guest'] && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
		$xml = cache_get_data('aevafeed:' . $cachekey, 240);
	if (empty($xml))
	{
		$xml = call_user_func('aeva_foxy_get_xml_' . $type);
		if ($cache_it)
			cache_put_data('aevafeed:' . $cachekey, $xml, 240);
	}

	$feed_title = westr::htmlspecialchars(strip_tags($context['forum_name'])) . ' - ' . $txt['media_gallery'] . (isset($feed_title) ? $feed_title : '');

	// Support for PrettyURLs rewriting
	if (!empty($modSettings['pretty_enable_filters']))
	{
		$insideurl = preg_quote($scripturl, '~');
		$context['pretty']['search_patterns'][]  = '~(<link>|<guid>)' . $insideurl . '([^<]*?[?;&](action)=[^#<]+)~';
		$context['pretty']['replace_patterns'][] = '~(<link>|<guid>)' . $insideurl . '([^<]*?[?;&](action)=([^<]+))~';
	}

	header('Content-Type: application/rss+xml; charset=UTF-8');

	// First, output the xml header.
	echo '<?xml version="1.0" encoding="UTF-8"?' . '>
<rss version="2.0" xml:lang="', strtr($txt['lang_locale'], '_', '-'), '">
	<channel>
		<title><![CDATA[', $feed_title, ']]></title>
		<link>', $scripturl . '?action=media</link>
		<description><![CDATA[', !empty($txt['media_rss_desc']) ? $txt['media_rss_desc'] : '', ']]></description>';

	// Output all of the associative array, start indenting with 2 tabs, and name everything "item".
	dumpTags($xml, 2, 'item', 'rss2');

	// Output the footer of the xml.
	echo '
	</channel>
</rss>';

	$db_show_debug = false;
	obExit(false);
}

function aeva_foxy_get_xml_items()
{
	global $user_info, $scripturl, $modSettings, $galurl, $amSettings;
	global $query_this, $settings, $context, $txt;

	$postmod = isset($modSettings['postmod_active']) ? $modSettings['postmod_active'] : false;
	$request = wesql::query('
		SELECT
			m.id_media, m.title, m.description, m.type, m.id_member, m.member_name, m.time_added,
			m.album_id, a.name, a.hidden, m.id_thumb, f.filename, f.directory
		FROM {db_prefix}media_items AS m
			INNER JOIN {db_prefix}media_albums AS a ON (a.id_album = m.album_id)
			LEFT JOIN {db_prefix}media_files AS f ON (f.id_file = m.id_thumb)
		WHERE ' . $query_this . ($postmod ? '
			AND m.approved = 1' : '') . '
		ORDER BY m.id_media DESC
		LIMIT {int:limit}',
		array(
			'limit' => $_GET['limit'],
			'memlist' => isset($_REQUEST['user']) ? $_REQUEST['user'] : '',
		)
	);

	$data = array();
	$clearurl = $amSettings['data_dir_url'];

	while ($row = wesql::fetch_assoc($request))
	{
		$thumb_url = isset($row['directory']) && !empty($amSettings['clear_thumbnames']) ? $clearurl . '/' . str_replace('%2F', '/', urlencode($row['directory'])) . '/' . aeva_getEncryptedFilename($row['filename'], $row['id_thumb'], true) : $galurl . 'sa=media;in=' . $row['id_media'] . ';thumb';
		$item = '<p><a href="' . $galurl . 'sa=item;in=' . $row['id_media'] . '"><img src="' . $thumb_url . '" style="padding: 3px; margin: 3px"></a></p>'
			. "\n" . '<p>' . $txt['media_by'] . ' <a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=aeva">' . $row['member_name'] . '</a>'
			. ($row['hidden'] ? '' : ' ' . $txt['media_in_album'] . ' <a href="' . $galurl . 'sa=album;in=' . $row['album_id'] . '">' . $row['name'] . '</a>') . '</p>';
		$data[] = array(
			'title' => cdata_parse($row['title']),
			'link' => $scripturl . '?action=media;sa=item;in=' . $row['id_media'],
			'description' => cdata_parse($item),
			'author' => cdata_parse($row['member_name']),
			'category' => cdata_parse($row['name']),
			'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['time_added']),
			'guid' => $scripturl . '?action=media;sa=item;in=' . $row['id_media'],
		);
	}

	wesql::free_result($request);

	return $data;
}

function aeva_foxy_get_xml_comments()
{
	global $user_info, $scripturl, $modSettings, $galurl, $amSettings;
	global $query_this, $settings, $context, $txt;

	$postmod = isset($modSettings['postmod_active']) ? $modSettings['postmod_active'] : false;
	$request = wesql::query('
		SELECT
			c.id_comment, c.id_member, c.id_media, c.id_album, c.message, c.posted_on,
			m.title, mem.member_name, a.name, a.hidden
		FROM {db_prefix}media_comments AS c
			INNER JOIN {db_prefix}media_items AS m ON (m.id_media = c.id_media)
			INNER JOIN {db_prefix}media_albums AS a ON (a.id_album = m.album_id)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = c.id_member)
			LEFT JOIN {db_prefix}media_files AS f ON (f.id_file = m.id_thumb)
		WHERE ' . $query_this . ($postmod ? '
			AND c.approved = 1' : '') . '
		ORDER BY c.id_comment DESC
		LIMIT {int:limit}',
		array(
			'limit' => $_GET['limit'],
			'memlist' => isset($_REQUEST['user']) ? $_REQUEST['user'] : '',
		)
	);

	$data = array();
	$clearurl = $amSettings['data_dir_url'];
	while ($row = wesql::fetch_assoc($request))
	{
		$item = '<p>' . ($row['hidden'] ? '' : $txt['media_comment_in'] . ' <a href="' . $galurl . 'sa=album;in=' . $row['id_album'] . '">' . $row['name'] . '</a> ') . $txt['media_by']
			. ' <a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=aeva">' . $row['member_name'] . '</a></p>' . "\n" . '<p>'
			. westr::cut($row['message'], 300, true, false, true, true) . '</p>';

		$data[] = array(
			'title' => cdata_parse($row['title']),
			'link' => $scripturl . '?action=media;sa=item;in=' . $row['id_media'] . '#com' . $row['id_comment'],
			'description' => cdata_parse($item),
			'author' => cdata_parse($row['member_name']),
			'category' => cdata_parse($row['name']),
			'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['posted_on']),
			'guid' => $scripturl . '?action=media;sa=item;in=' . $row['id_media'] . '#com' . $row['id_comment'],
		);
	}

	wesql::free_result($request);

	return $data;
}

///////////////////////////////////////////////////////////////////////////////
// FLASH PLAYLISTS
///////////////////////////////////////////////////////////////////////////////

function aeva_foxy_album($id, $type, $wid = 0, $details = '', $sort = 'm.id_media DESC', $field_sort = 0)
{
	global $context, $amSettings, $scripturl, $boarddir, $txt, $user_info, $settings, $galurl, $boardurl;
	static $swfobjects = 1;

	$det = empty($details) || $details[0] == 'all' ? 'all' : ($details[0] == 'no_name' ? 'no_name' : '');
	if ($det == 'all' || $det == 'no_name')
		$details = $det == 'all' ? array('name', 'description', 'playlists', 'votes') : array('description', 'playlists', 'votes');

	if (empty($txt['media']))
		loadLanguage('Media');

	if (!isset($txt['by']))
	{
		$txt['by'] = $txt[525];
		$txt['modify'] = $txt[17];
	}

	$box = $exts = '';
	$pwid = !empty($wid) ? $wid : (!empty($amSettings['audio_player_width']) ? min($amSettings['max_preview_width'], max(100, (int) $amSettings['audio_player_width'])) : 500);

	// All extensions supported by both Aeva Media and JW Player. I've commented out all file formats that JW claims to support but I couldn't get to work.
	// You may want to add m4a support for audio files, but it didn't work out for me (probably due to JW Player not supporting AM file names for this specific type)
	// $exts .= "'3gp', 'm4a', ";

	$all_types = $type == 'media' || $type == 'playl' || $type == 'ids';
	if ($type == 'audio' || $all_types)
		$exts .= "'mp3', ";
	if ($type == 'video' || $all_types)
		$exts .= "'mp4', 'm4v', 'f4v', 'flv', '3g2', ";
	if ($type == 'photo' || $all_types)
		$exts .= "'jpg', 'jpe', 'peg', 'png', 'gif', ";

	if (empty($exts))
		return;

	if ($type == 'playl')
	{
		wesql::query('
			UPDATE {db_prefix}media_playlists
			SET views = views + 1
			WHERE id_playlist = {int:playlist}',
			array('playlist' => (int) $id)
		);

		$request = wesql::query('
			SELECT
				m.id_media, m.title, m.type, f.meta, m.description, m.album_id, a.name AS album_name, a.hidden, a.id_album,
				rating, voters, m.id_member, f.height, f.filename, i.width AS icon_width, p.height AS preview_height,
				t.filename AS tf, t.id_file AS id_thumb, t.directory AS td, i.transparency
			FROM {db_prefix}media_items AS m
			INNER JOIN {db_prefix}media_playlist_data AS pl ON (pl.id_media = m.id_media AND pl.id_playlist = {int:playlist})
			INNER JOIN {db_prefix}media_albums AS a ON (m.album_id = a.id_album)
			INNER JOIN {db_prefix}media_files AS f ON (f.id_file = m.id_file)
			LEFT JOIN {db_prefix}media_files AS p ON (p.id_file = IF(m.id_preview = 0, IF(m.id_thumb < 5, a.icon, m.id_thumb), m.id_preview))
			LEFT JOIN {db_prefix}media_files AS t ON (t.id_file = IF(m.id_thumb < 5 AND a.icon > 4, a.icon, m.id_thumb))
			LEFT JOIN {db_prefix}media_files AS i ON (i.id_file = a.icon)
			WHERE LOWER(RIGHT(f.filename, 3)) IN ({raw:extensions}) AND {query_see_album_hidden}
			ORDER BY pl.play_order ASC',
			array(
				'playlist' => (int) $id,
				'extensions' => substr($exts, 0, -2),
			)
		);
	}
	elseif ($type == 'ids')
	{
		$request = wesql::query('
			SELECT
				m.id_media, m.title, m.type, f.meta, m.description, m.album_id, a.name AS album_name, a.hidden, a.id_album,
				rating, voters, m.id_member, f.height, a.description AS album_description, f.filename, i.width AS icon_width, p.height AS preview_height,
				t.filename AS tf, t.id_file AS id_thumb, t.directory AS td, i.transparency
			FROM {db_prefix}media_items AS m
			INNER JOIN {db_prefix}media_albums AS a ON (m.album_id = a.id_album)
			INNER JOIN {db_prefix}media_files AS f ON (f.id_file = m.id_file)
			LEFT JOIN {db_prefix}media_files AS p ON (p.id_file = IF(m.id_preview = 0, IF(m.id_thumb < 5, a.icon, m.id_thumb), m.id_preview))
			LEFT JOIN {db_prefix}media_files AS t ON (t.id_file = IF(m.id_thumb < 5 AND a.icon > 4, a.icon, m.id_thumb))
			LEFT JOIN {db_prefix}media_files AS i ON (i.id_file = a.icon)
			WHERE m.id_media IN ({raw:ids}) AND {query_see_album_hidden}
			ORDER BY m.id_media DESC',
			array('ids' => preg_match('~\d+(?:,\d+)*~', $id) ? $id : '0')
		);
	}
	else
	{
		if (strpos($id, ',') === false)
		{
			$request = wesql::query('
				SELECT options FROM {db_prefix}media_albums WHERE id_album = {int:album}',
				array('album' => (int) $id)
			);
			list ($opti) = wesql::fetch_row($request);
			wesql::free_result($request);
			$optio = unserialize($opti);
			if (isset($optio['sort']))
				$sort = $optio['sort'];
		}
		$request = wesql::query('
			SELECT
				m.id_media, m.title, m.type, f.meta, m.description, m.album_id, a.name AS album_name, a.hidden, a.id_album,
				rating, voters, m.id_member, f.height, a.description AS album_description, f.filename, i.width AS icon_width, p.height AS preview_height,
				t.filename AS tf, t.id_file AS id_thumb, t.directory AS td, i.transparency
			FROM {db_prefix}media_items AS m
			INNER JOIN {db_prefix}media_albums AS a ON (m.album_id = a.id_album)
			INNER JOIN {db_prefix}media_files AS f ON (f.id_file = m.id_file)
			LEFT JOIN {db_prefix}media_files AS p ON (p.id_file = IF(m.id_preview = 0, IF(m.id_thumb < 5, a.icon, m.id_thumb), m.id_preview))
			LEFT JOIN {db_prefix}media_files AS t ON (t.id_file = IF(m.id_thumb < 5 AND a.icon > 4, a.icon, m.id_thumb))
			LEFT JOIN {db_prefix}media_files AS i ON (i.id_file = a.icon)
			WHERE m.album_id IN ({raw:album}) AND LOWER(RIGHT(f.filename, 3)) IN ({raw:extensions}) AND {query_see_album_hidden}
			ORDER BY ' . $sort,
			array(
				'album' => preg_match('~\d+(?:,\d+)*~', $id) ? $id : '0',
				'extensions' => substr($exts, 0, -2),
			)
		);
	}

	if (wesql::num_rows($request) == 0)
		return $txt['media_tag_no_items'];

	$total_rating = $nvotes = 0;
	$thei = $amSettings['max_thumb_height'];
	$playlist = array();
	$has_album = array();
	$has_type = array('audio' => 0, 'video' => 0, 'image' => 0);
	$clearurl = $boardurl . str_replace($boarddir, '', $amSettings['data_dir_path']);
	while ($row = wesql::fetch_assoc($request))
	{
		if (in_array($type, array('audio', 'video', 'media')) && empty($playlist_description))
		{
			$playlist_name = $row['album_name'];
			$playlist_description = $row['album_description'];
		}
		$has_type[$row['type']]++;
		$has_album[$row['album_id']] = isset($has_album[$row['album_id']]) ? $has_album[$row['album_id']] + 1 : 1;
		$filename = $scripturl . '?action=media;sa=media;in=' . $row['id_media'];
		$titre = $row['title'];
		$ext = strtolower(substr(strrchr($row['filename'], '.'), 1));
		$artist = $row['album_name'];
		$thumb = isset($row['td']) && !empty($amSettings['clear_thumbnames']) ? $clearurl . '/' . str_replace('%2F', '/', urlencode($row['td'])) . '/' . aeva_getEncryptedFilename($row['tf'], $row['id_thumb'], true) : $galurl . 'sa=media;in=' . $row['id_media'] . ';thumba';
		$meta = unserialize($row['meta']);
		$total_rating += (int) $row['rating'];
		$nvotes += (int) $row['voters'];
		$thei = min(400, max($thei, ($row['type'] == 'image' && $row['height'] > $amSettings['max_preview_height']) || empty($row['height']) ? $row['preview_height'] : $row['height']));
		$playlist[$row['id_media']] = array(
			'title' => $titre,
			'id' => $row['id_media'],
			'file' => $filename,
			'thumb' => $thumb,
			'duration' => round(!empty($meta['duration']) ? $meta['duration'] : 5),
			'description' => empty($row['description']) ? '' : parse_bbc($row['description']),
			'lister_description' => empty($row['pl_description']) ? '' : parse_bbc($row['pl_description']),
			'album' => $row['album_name'],
			'album_id' => $row['id_album'],
			'album_hidden' => $row['hidden'],
			'owner' => $row['id_member'],
			'type' => $row['type'] == 'audio' ? 'sound' : $row['type'],
			'icon_width' => $row['icon_width'],
			'icon_transparent' => $row['transparency'] == 'transparent',
			'rating' => (int) $row['rating'],
			'voters' => (int) $row['voters'],
			'ext' => $ext,
			'link' => '',
		);
	}
	wesql::free_result($request);
	$album_id = array_search(max($has_album), $has_album);

	$req = 'SELECT d.id_media, d.id_field, d.value, f.name
		FROM {db_prefix}media_field_data AS d
		INNER JOIN {db_prefix}media_fields AS f ON f.id_field = d.id_field
		WHERE d.id_media IN ({array_int:id})';

	$all_fields = $order_fields = array();
	$request = wesql::query($req, array('id' => array_keys($playlist)));
	while ($row = wesql::fetch_assoc($request))
	{
		$playlist[$row['id_media']]['custom_fields'][$row['name']] = $row;
		$all_fields[$row['name']][$row['value']] = $row['value'];
		if ($field_sort == $row['id_field'])
			$order_fields[$row['value']][] = $row['id_media'];
	}
	wesql::free_result($request);

	// Do we need to make a manual sort by custom field?
	if (count($order_fields) > 0)
	{
		ksort($order_fields);
		$fields = $ordered_playlist = array();
		foreach ($order_fields as $in_order)
			foreach ($in_order as $pl)
				$ordered_playlist[$pl] = $playlist[$pl];
		$playlist = $ordered_playlist;
		unset($ordered_playlist);
	}

	$req = '
		SELECT
			d.id_media, d.id_playlist, p.description, p.name, p.id_member, m.real_name, d.description AS lister_description
		FROM {db_prefix}media_playlist_data AS d
		INNER JOIN {db_prefix}media_playlists AS p ON p.id_playlist = d.id_playlist
		LEFT JOIN {db_prefix}members AS m ON m.id_member = p.id_member
		WHERE d.id_media IN ({array_int:id})';

	$all_playlists = array();
	$request = wesql::query($req, array('id' => array_keys($playlist)));
	while ($row = wesql::fetch_assoc($request))
	{
		$playlist[$row['id_media']]['playlists'][$row['name']] = $row;
		$all_playlists[$row['id_playlist']] = $row;
		if (empty($playlist_name) && $type == 'playl' && $row['id_playlist'] == $id)
		{
			$playlist_name = $row['name'];
			$playlist_description = $row['description'];
			$playlist_owner_id = empty($row['id_member']) ? 0 : $row['id_member'];
			$playlist_owner_name = empty($row['real_name']) ? '' : $row['real_name'];
			$current_url = $scripturl . '?' . (!empty($context['current_board']) ? 'board=' . $context['current_board'] . ';' : '') . 'action=media;sa=playlists;in=' . $id; // $_SERVER['REQUEST_URL']
			add_linktree($scripturl . '?action=media;sa=playlists', $txt['media_playlists']);
			add_linktree($current_url, $playlist_name);
		}
	}
	wesql::free_result($request);

	if (in_array('playlists', $details))
	{
		foreach ($playlist as $myp => $p)
		{
			$gn = '';
			if (!empty($p['playlists']))
			{
				foreach ($p['playlists'] as $pp)
					$gn .= $type == 'playl' && $pp['id_playlist'] == $id ? $pp['name'] . ', ' : '<a href="' . $scripturl . '?' . (!empty($context['current_board']) ?
						'board=' . $context['current_board'] . ';' : '') . 'action=media;sa=playlists;in=' . $pp['id_playlist'] . '" onclick="lnFlag=1;">' . $pp['name'] . '</a>, ';
				$playlist[$myp]['plists'] = substr($gn, 0, -2);
			}
		}
	}

	if (!in_array('none', $details))
	{
		$first_p = reset($playlist);
		$box .= '<table class="foxy_side cp0 cs0 floatright" style="width: ' . max(100, $first_p['icon_width'] + 10) . 'px">';

		if (!empty($all_fields))
		{
			$box .= '<tr><td class="top">';
			foreach ($all_fields as $name => $field)
			{
				$box .= '<b>' . $name . '</b>: ';
				$max_3 = 0;
				foreach ($field as $sf)
				{
					if ($max_3++ < 3)
						$box .= (substr($sf, 0, 7) == 'http://' ? '<a href="' . $sf . '">www</a>' : $sf) . ', ';
					else
					{
						$box = substr($box, 0, -2) . '&hellip;, ';
						break;
					}
				}
				$box = substr($box, 0, -2) . '<br>';
			}
			$box .= '</td></tr>';
		}

		$box .= '<tr>' . ($album_id > 0 ? '<td class="top">
	<img class="aep' . ($first_p['icon_transparent'] ? ' ping' : '') . '" src="' . $scripturl . '?action=media;sa=media;in=' . $album_id . ';bigicon"></td>' : '');

		if ($nvotes != 0 && in_array('votes', $details))
		{
			$nrating = sprintf('%.2f', $total_rating / $nvotes);

			$rating = substr($nrating, 0, 1);
			$finr = substr($nrating, 2, 2);
			if ($finr < 25) $star = $rating;
			elseif ($finr < 75) $star = $rating . '5';
			else $star = $rating + 1;
			$altstar = $star;
			if (strlen($altstar) > 1) $altstar = $rating . '.5';

			$box .= '</tr><tr><td><div class="vote"><div class="vote_header"><b>' . $txt['media_rating'] . ': <span style="color: red">' . $nrating . '/5</span></b> (' . $nvotes . ' ' . $txt['media_vote' . ($nvotes > 1 ? 's' : '') . '_noun'] . ')';
			$box .= '<br><img src="' . $settings['images_aeva'] . '/star' . $star . '.gif" class="aevera" alt="' . $altstar . '">';

			$box .= ' <a href="javascript:void(0)" onclick="n = this.parentNode.parentNode.lastChild; if(n.style.display == \'none\') { n.style.display = \'block\'; } else { n.style.display = \'none\'; } return false;"><img src="' . $settings['images_aeva'] . '/magnifier.png" width="16" height="16" alt="' . $txt['media_who_rated_what'] . '" title="' . $txt['media_who_rated_what'] . '" class="aevera"></a></div>
			<div class="vote_details" style="padding: 12px 0 0 12px; display: none">';

			// All votes
			$req = 'SELECT p.id_member, p.rating, m.real_name
				FROM {db_prefix}media_log_ratings AS p
				INNER JOIN {db_prefix}members AS m ON m.id_member = p.id_member
				WHERE p.id_media IN ({array_int:id})';

			$request = wesql::query($req, array('id' => array_keys($playlist)));
			while ($row = wesql::fetch_assoc($request))
			{
				$mystar = (int) $row['rating'];
				$box .= '<img src="' . $settings['images_aeva'] . '/star' . $mystar . '.gif" class="aevera" alt="' . $mystar . '">';
				$box .= ' ' . $txt['by'] . ' <a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=aevavotes">' . $row['real_name'] . '</a><br>';
			}
			$box .= '</div></div></td>';
			wesql::free_result($request);
		}
		else
			$nrating = '';

		$box .= '</tr></table>' . (!empty($playlist_name) && in_array('name', $details) ? '
<h1 class="foxy_playlist_name">' . $playlist_name . '</h1>' : '');

		$box .= '<div class="foxy_stats">';
		if ($has_type['audio'] && !$has_type['video'] && !$has_type['image'])
			$box .= $txt['media_foxy_audio_list'];
		elseif (!$has_type['audio'] && $has_type['video'] && !$has_type['image'])
			$box .= $txt['media_foxy_video_list'];
		elseif (!$has_type['audio'] && !$has_type['video'] && $has_type['image'])
			$box .= $txt['media_foxy_image_list'];
		else
			$box .= $txt['media_foxy_media_list'];
		$box .= ' &mdash; '
			. ($has_type['audio'] ? $has_type['audio'] . ' ' . $txt['media_foxy_stats_audio' . ($has_type['audio'] > 1 ? 's' : '')] . ($has_type['image'] != $has_type['video'] && ($has_type['image'] == 0 || $has_type['video'] == 0) ? ' ' . $txt['media_and'] . ' ' : ', ') : '')
			. ($has_type['video'] ? $has_type['video'] . ' ' . $txt['media_foxy_stats_video' . ($has_type['video'] > 1 ? 's' : '')] . ($has_type['image'] ? ' ' . $txt['media_and'] . ' ' : ', ') : '')
			. ($has_type['image'] ? $has_type['image'] . ' ' . $txt['media_foxy_stats_image' . ($has_type['image'] > 1 ? 's' : '')] . ', ' : '');
		$box = substr($box, 0, -2) . ' ' . sprintf($txt['media_from_album' . (count($has_album) > 1 ? 's' : '')], count($has_album))
			. ($type == 'playl' && ($user_info['is_admin'] || ($playlist_owner_id == $user_info['id'] && allowedTo('media_add_playlists'))) ? ' - <a href="' . $scripturl . '?action=media;sa=playlists;in=' . $id . ';edit;' . $context['session_var'] . '=' . $context['session_id'] . '"><img src="' . $settings['images_aeva'] . '/camera_edit.png" class="bottom"> ' . $txt['media_edit_this_item'] . '</a>' : '') . '</div>';

		$box .= !empty($playlist_description) && in_array('description', $details) ? parse_bbc($playlist_description) : '';

		if (!empty($all_playlists) && in_array('playlists', $details))
		{
			$box .= '<br><br>' . $txt['media_related_playlists'] . ': ';
			foreach ($all_playlists as $idi => $list)
				$box .= '<a href="' . $scripturl . '?' . (!empty($context['current_board']) ? 'board=' . $context['current_board'] . ';' : '') . 'action=media;sa=playlists;in=' . $idi . '">' . $list['name'] . '</a>, ';
			$box = substr($box, 0, -2);
		}
	}

	// On a list of messages, you don't want to show several players at once... Here's a setting to disable them.
	if (!empty($context['aeva_disable_player']))
		return $box;

	if (!in_array('none', $details))
		$box .= '<br><br>';
	list ($tx, $scr_player) = aeva_foxy_fill_player($playlist, $swfobjects++, $type, $details, 0, $pwid, 430, $thei + 20);

	if ($swfobjects == 2)
	{
		$scr_object = "\n\t" . '<script src="http://ajax.googleapis.com/ajax/libs/swfobject/2.1/swfobject.js"></script>';
		if (ob_get_length() === 0)
		{
			$scr = '';
			if (strpos($context['header'], '2.1/swfobject.js"') === false)
				$scr .= $scr_object . $scr_player;
			elseif (strpos($context['header'], '#foxlist') === false)
				$scr .= $scr_player;
			$context['header'] .= $scr;
		}
		else
		{
			$temp = ob_get_contents();
			ob_clean();

			$scr = '';
			if (strpos($temp, '2.1/swfobject.js"') === false)
				$scr .= $scr_object . $scr_player;
			elseif (strpos($temp, '#foxlist') === false)
				$scr .= $scr_player;
			echo substr_replace($temp, $scr . "\n" . '</head>', stripos($temp, '</head>'), 7);

			unset($temp);
		}
	}

	$box .= $tx;

	return $box;
}

function aeva_foxy_fill_player(&$playlist, $swo = 1, $type = 'audio', &$details, $play = 0, $wid = 470, $hei = 430, $thei = 70)
{
	global $user_info, $scripturl, $boardurl, $amSettings, $context, $settings, $txt;

	$swo = (int) $swo;
	$player = '
<style>
	.foxy_playlist {
		text-align: left;
		width: 100%;
		height: auto !important;
		height: '. $hei . 'px;
		max-height: '. $hei . 'px;
		overflow-x: hidden !important;
		overflow-y: auto !important;
	}
	.foxy_album {
		clear: both;
		border: 1px solid #888;
	}
	.foxy_stats {
		font: 0.85em/1.25em "Trebuchet MS", Trebuchet, Arial, Verdana, helvetica, sans-serif;
		text-transform: uppercase;
		padding: 0 0 8px 0;
	}

	.playinglo, .playinghi, .playlistlo, .playlisthi { font: 12px verdana, arial, helvetica, sans-serif; color: #000; cursor: pointer; }
	.playinghi, .playlisthi { background: transparent url(/Themes/default/images/white-op40.png) !important; background: none }
	.foxy_small { font-size: 0.85em; padding: 2px 0 0 0 }
</style>' . /*

<!--[if IE 6]>
	<style>
	#foxlist { height: expression( Math.min(parseInt(this.offsetHeight), ' . ($hei-70) . ') ); }
	</style>
<![endif]--> */ '

<script><!-- // --><![CDATA[

	myfile = "' . $scripturl . '?action%3Dmedia;sa%3Dmedia;in%3D";
	player = [], myplaylist = [], ply = [], plyHeight = [], plyTotalHeight = [], lnFlag = 0;
	currentPlayer = 1, currentItem = [], previousItem = [], targetScrollTop = [];
	currentState = [], previousState = [], foxp = [];

function playerReady(thePlayer)
{
	thisPlayer = thePlayer.id.substring(6);
	if (player[thisPlayer])
		return;
	player[thisPlayer] = window.document[thePlayer.id];
	ply[thisPlayer] = document.getElementById("foxlist" + thisPlayer);
	plyHeight[thisPlayer] = ply[thisPlayer].clientHeight;
	previousItem[thisPlayer] = -1;
	currentItem[thisPlayer] = -1;
	addListeners();
}

function addListeners()
{
	if (player[thisPlayer])
	{
		player[thisPlayer].addControllerListener("ITEM", "itemListener");
		player[thisPlayer].addModelListener("STATE", "stateListener");
		player[thisPlayer].sendEvent("LOAD", myplaylist[thisPlayer]);
		document.getElementById("foxlist" + thisPlayer).onselectstart = function() { return false; };
	}
	else
		setTimeout(addListeners, 100);
}

function itemListener(obj)
{
	if (obj.index != currentItem[currentPlayer])
	{
		previousItem[currentPlayer] = currentItem[currentPlayer];
		currentItem[currentPlayer] = obj.index;
		setItemStyle(currentItem[currentPlayer]);
	}
}

function stateListener(obj) //IDLE, BUFFERING, PLAYING, PAUSED, COMPLETED
{
	if (obj.newstate == "PAUSED" || (currentState[currentPlayer] == "PAUSED" && obj.newstate == "PLAYING"))
		return;
	currentState[currentPlayer] = obj.newstate;

	if (currentState[currentPlayer] != previousState[currentPlayer])
	{
		setItemStyle(currentItem[currentPlayer]);
		previousState[currentPlayer] = currentState[currentPlayer];
	}
}

function mover(obj, idx)
{
	obj.className = idx == currentItem[currentPlayer] ? "playinghi" : "playlisthi";
}

function mout(obj, idx)
{
	lnFlag = 0;
	obj.className = idx == currentItem[currentPlayer] ? "playinglo" : "playlistlo";
}

function scrollMe()
{
	var cur = ply[currentPlayer].scrollTop;
	if (cur < targetScrollTop[currentPlayer])
		ply[currentPlayer].scrollTop += Math.max(1, Math.round((targetScrollTop[currentPlayer]-cur)/40));
	else if (cur > targetScrollTop[currentPlayer])
		ply[currentPlayer].scrollTop -= Math.max(1, Math.round((cur-targetScrollTop[currentPlayer])/40));
	else
		return;
	setTimeout(scrollMe, 20);
}

function setItemStyle(idx)
{
	if (typeof(idx) == "undefined" || (currentState[currentPlayer] != "PLAYING" && currentState[currentPlayer] != "IDLE"))
		return;

	var foxLength = foxp[currentPlayer].length;
	var posTop = 0, posList = [], heiList = [];
	for (var i = 0; i < foxLength; i++)
	{
		var tmp = document.getElementById("fxm" + foxp[currentPlayer][i][0]);
		var giveClass = i == currentItem[currentPlayer] && currentState[currentPlayer] == "PLAYING" ? "windowbg3" : "";
		if (tmp.className != giveClass)
			tmp.className = giveClass;

		posList[i] = posTop;
		heiList[i] = tmp.clientHeight + 4;
		posTop += heiList[i];
	}
	if (currentItem[currentPlayer] == previousItem[currentPlayer] || plyTotalHeight[currentPlayer]-plyHeight[currentPlayer] < 2)
		return;
	if (!plyTotalHeight[currentPlayer])
		plyTotalHeight[currentPlayer] = ply[currentPlayer].scrollHeight;
	if (plyTotalHeight[currentPlayer]-plyHeight[currentPlayer] < 2)
		return;

	var offs = Math.round((plyHeight[currentPlayer] - heiList[idx])/2);
	targetScrollTop[currentPlayer] = Math.min(plyTotalHeight[currentPlayer]-plyHeight[currentPlayer], Math.max(0, posList[idx] - Math.max(0, offs)));
	setTimeout(scrollMe, 20);
}

// ]]></script>';

	$pcol = !empty($amSettings['player_color']) ? ($amSettings['player_color'][0] == '#' ? substr($amSettings['player_color'], 1) : $amSettings['player_color']) : '';
	$bcol = !empty($context['aeva_override_bcolor']) ? $context['aeva_override_bcolor'] : (!empty($amSettings['player_bcolor']) ? ($amSettings['player_bcolor'][0] == '#' ? substr($amSettings['player_bcolor'], 1) : $amSettings['player_bcolor']) : '');

	$tx = (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'search' ? '<!-- aeva_page_index -->' : '') . '
<table class="foxy_album w100 center">
<tr><td style="height: ' . $thei . 'px"><div id="aefoxy' . $swo . '" style="overflow: auto; height: ' . $thei . 'px">&nbsp;</div></td></tr>
<tr><td><div id="foxlist' . $swo . '" class="foxy_playlist" onmousedown="return false;">
	<table class="w100 cp4 cs0">';
	$c = '';
	$num = 0;
	foreach ($playlist as $idi => $i)
	{
		$c = $c == '' ? '2' : '';
		$tx .= '<tr><td ' . (isset($context['aeva_override_altcolor']) ? 'style="background: #' . $context['aeva_override_altcolor' . $c] : 'class="windowbg' . $c) . '">';
		$tx .= '<table class="w100 cp0" id="fxm' . $idi . '" onclick="recreatePlayer' . $swo . '(' . $num++ . ');">';
		$tx .= '<tr><td class="top" style="width: 55px"><img src="' . $i['thumb'] . '" width="55" height="55" title="Click to Play"></td>';
		$tx .= '<td class="playlistlo middle" onmouseover="mover(this, ' . $idi . ');" onmouseout="mout(this, ' . $idi . ');" style="padding: 4px">';

		if (in_array('votes', $details) || in_array('none', $details))
		{
			$nrating = $i['voters'] == 0 ? 0 : sprintf('%.2f', $i['rating'] / $i['voters']);
			$rating = substr($nrating, 0, 1);
			$finr = substr($nrating, 2, 2);
			$star = $finr < 25 ? $rating : ($finr < 75 ? $rating . '5' : $rating + 1);
			$altstar = strlen($star) > 1 ? $rating . '.5' : $star;
		}

		$tx .= $i['title'] . ' <a href="' . $scripturl . '?action=media;sa=item;in=' . $i['id'] . '" target="_blank" title="" onclick="lnFlag=1;"><img src="' . $settings['images_aeva'] . '/magnifier.png" width="16" height="16" style="vertical-align: text-bottom"></a>';
		$tx .= ' (' . floor($i['duration'] / 60) . ':' . ($i['duration'] % 60 < 10 ? '0' : '') . ($i['duration'] % 60) . ')';

		$tx .= '<div style="float: right; text-align: right">';
		if (allowedTo('media_moderate') || $user_info['id'] == $i['owner'])
			$tx .= '<a href="' . $scripturl . '?action=media;sa=post;in=' . $i['id'] . '" onclick="lnFlag=1;">' . $txt['modify'] . '</a><div class="foxy_small">';
		$tx .= (in_array('votes', $details) || in_array('none', $details) ? '<img src="' . $settings['images_aeva'] . '/star' . $star . '.gif" class="aevera" alt="' . $altstar . '">'
				. ($i['voters'] > 0 ? '<br>' . $nrating . '/5 (' . $i['voters'] . ' ' . $txt['media_vote' . ($i['voters'] > 1 ? 's' : '') . '_noun'] . ')' : '') : '') . '</div></div>';

		$tx .= '<br>';
		$tx .= $i['album_hidden'] ? '<b>' . $i['album'] . '</b>' : '<b><a href="' . $scripturl . '?action=media;sa=album;in=' . $i['album_id'] . '" target="_blank" onclick="lnFlag=1;">' . $i['album'] . '</a></b>' ;

		if (!empty($i['custom_fields']))
		{
			foreach ($i['custom_fields'] as $name => $field)
			{
				$tx .= ' - ' . $name . ': ';
				if (substr($field['value'], 0, 7) == 'http://')
					$tx .= '<a href="' . $field['value'] . '" target="_blank" onclick="lnFlag=1;">' . $field['value'] . '</a>';
				else
					$tx .= '<a href="' . $scripturl . '?' . (!empty($context['current_board']) ? 'board=' . $context['current_board'] . ';' : '')
					. 'action=media;sa=search;search=' . urlencode($field['value']) . ';fields[]=' . $field['id_field'] . '" onclick="lnFlag=1;">' . $field['value'] . '</a>';
			}
		}

		if (!empty($i['plists']))
			$tx .= '<div class="foxy_small">' . $i['plists'] . '</div>';
		if ($i['link'])
			$tx .= '<div><a href="' . $i['link'] . '" target="_blank" title="' . $i['link'] . '" onclick="return false;">details</a></div>';
		$tx .= '</td></tr>';
		if ($i['description'])
			$tx .= '<tr><td colspan="2" class="smalltext playlistlo">' . $i['description'] . '</td></tr>';
		if ($i['lister_description'])
			$tx .= '<tr><td colspan="2" class="smalltext playlistlo"><img src="' . $settings['default_images_url'] . '/aeva/user_comment.png" class="left"> ' . $i['lister_description'] . '</td></tr>';
		$tx .= '</table></td></tr>';
	}
	$tx .= '
	</table>
</div>
<div id="info"></div>
</td></tr></table>' . (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'search' ? '<!-- aeva_page_index -->' : '');

	$tx .= '
	<script><!-- // --><![CDATA[
		foxLength = ' . count($playlist) . ';
		function recreatePlayer' . $swo . '(fid)
		{
			if (currentPlayer != ' . $swo . ')
				player[currentPlayer].sendEvent("STOP");
			currentPlayer = ' . $swo . ';
			if (!lnFlag && player[' . $swo . '])
				player[' . $swo . '].sendEvent("ITEM", fid);
		}
		foxp[' . $swo . '] = [[';
	$arrtypes = array('image' => 0, 'video' => 1, 'sound' => 2);
	foreach ($playlist as $i)
		$tx .= $i['id'] . ',' . $i['duration'] . ',' . $arrtypes[$i['type']] . ',"' . $i['ext'] . '"], [';
	$first = reset($playlist);
	$tx = substr($tx, 0, -3) . '];
		myplaylist[' . $swo . '] = [];
		for (var k = 0; k < ' . count($playlist) . '; k++)
		{
			myid = foxp[' . $swo . '][k][0];
			myext = foxp[' . $swo . '][k][3];
			myduration = foxp[' . $swo . '][k][1];
			mytype = ["image", "video", "sound"][foxp[' . $swo . '][k][2]];
			myplaylist[' . $swo . '][k] = { file: myfile + myid + (mytype == "image" ? ";preview" : "") + ";." + myext, image: myfile + myid + (mytype == "image" ? ";preview" : ";thumba"), type: mytype, duration: myduration };
		}
		var fvars = { file: myfile + "' . $first['id'] . '", ' . (!empty($pcol) ? 'backcolor: "' . $pcol . '", ' : '') . (!empty($bcol) ? 'screencolor: "' . $bcol . '", ' : '')
		. 'image: myfile + "' . $first['id'] . ($first['type'] == 'image' ? ';preview' : ';thumba') . '", plugins: "' . aeva_theme_url('eq.swf') . '", showdigits: "true", repeat: "always", type: "' . $first['type'] . '", duration: "' . floor($first['duration']) . '" };
		swfobject.embedSWF("' . aeva_theme_url('player.swf') . '", "aefoxy' . $swo . '", "100%", "' . $thei . '", "9", "' . $boardurl . '/expressInstall.swf", '
		. 'fvars, { allowFullscreen: "true", allowScriptAccess: "always" }, { id: "player' . $swo . '", name: "player' . $swo . '" });
	// ]]></script>';

// screencolor: "E7E4D9"

	return array($tx, $player);
}

?>