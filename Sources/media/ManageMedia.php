<?php
/**
 * Wedge
 *
 * Main admin features for the gallery.
 * Uses portions written by Shitiz Garg.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*
	This file handles Aeva Media's Administration section

	void aeva_admin_init()
		- Initializes the admin page
		- Leads to the appropriate page

	void aeva_admin_settings()
		- Shows the settings page of the gallery

	void aeva_admin_albums()
		- Shows the album admin index.

	void aeva_admin_albums_add()
		- Adds an album

	void aeva_admin_albums_edit()
		- Edits an album

	void aeva_admin_albums_move()
		- Moves an album

	void aeva_admin_albums_delete()
		- Deletes an album, as well as all of the items and comments in it

	void aeva_admin_FTPImport()
		- Handles the FTP importing

	void aeva_admin_perms()
		- Handles the permission profiles area

	void aeva_admin_perms_quick()
		- Handles the quick action on the permission profile's membergroups

	void aeva_admin_perms_view()
		- Handles viewing of a specific profile

	void aeva_admin_perms_edit()
		- Used for editing a specific membergroup's permissions on a specific profile

	void aeva_admin_quotas_albums()
		// !!!

	void aeva_admin_quotas()
		- Handles the quota profiles area

	void aeva_admin_quotas_add()
		- Adds a quota profile

	void aeva_admin_quotas_view()
		- Handles viewing of a specific quota profile

	void aeva_admin_quotas_edit()
		- Used for editing a specific membergroup's quotas on a specific profile

	void aeva_admin_quotas_albums()
		- Handles the AJAX response for getting the profile's albums

	void aeva_admin_fields()
		- Handles the viewing of the custom fields

	void aeva_admin_fields_edit()
		- Handles adding/editing of the custom fields
*/

// Gallery admin initializer
function aeva_admin_init()
{
	global $context, $txt, $theme, $amSettings, $settings;

	// Let's call our friends
	// Admin2 = maintenance & ban, Admin3 = embedder
	loadSource(array('media/Subs-Media', 'media/ManageMedia2', 'media/ManageMedia3'));

	// Load the settings and database
	loadMediaSettings(null, true, true);
	loadLanguage('ManageMedia');
	loadTemplate('ManageMedia');

	// Our sub-actions
	// 'sub-action' => 'Function to call'
	$areas = array(
		'settings' =>'aeva_admin_settings',
		'embed' =>'aeva_admin_embed',
		'albums' => 'aeva_admin_albums',
		'maintenance' => 'aeva_admin_maintenance',
		'bans' => 'aeva_admin_bans',
		'ftp' => 'aeva_admin_FTPImport',
		'perms' => 'aeva_admin_perms',
		'quotas' => 'aeva_admin_quotas',
		'fields' => 'aeva_admin_fields',
	);

	$area = isset($_REQUEST['area']) ? substr($_REQUEST['area'], 5) : 'settings';
	$_REQUEST['area'] = $area;

	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $area == 'fields' ? $txt['media_cf'] : $txt['media_admin_labels_' . $area],
		'description' => $area == 'fields' ? $txt['media_cf_desc'] : $txt['media_admin_' . $area . '_desc'],
		'tabs' => array(),
	);

	// Check the session.
	checkSession('get');

	// Check for permission
	if (!allowedTo('media_manage'))
		fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));

	wetem::outer('aeva_admin');

	$context['page_title'] = $txt['media_title'] . ' - ' . $txt['media_admin_labels_' . $area];

	// OK let's finish this by calling the function
	if (isset($areas[$area]))
		$areas[$area]();

	// Some CSS and JS we'll be using
	add_css_file('media', true);
	add_js_file('scripts/mediadmin.js');
}

// Handles the settings page
function aeva_admin_settings()
{
	global $context, $amSettings, $settings, $txt;

	wetem::load('aeva_form');
	wetem::outer('aeva_admin_enclose_table');

	$context['current_area'] = isset($_REQUEST['sa']) && in_array($_REQUEST['sa'], array('meta', 'layout')) ? $_REQUEST['sa'] : 'config';

	$theme = array(
		'media_enabled' => array('yesno', 'config'),

		'title_main' => array('title', 'config'),
		'welcome' => array('textbox', 'config'),
		'data_dir_path' => array('text', 'config'),
		'data_dir_url' => array('text', 'config'),
		'max_dir_files' => array('small_text', 'config'),
		'enable_re-rating' => array('yesno', 'config'),
		'use_metadata_date' => array('yesno', 'config'),
		'enable_cache' => array('yesno', 'config'),
		'image_handler' => array('radio', 'config'),

		'title_security' => array('title', 'config'),
		'item_edit_unapprove' => array('yesno', 'config'),
		'album_edit_unapprove' => array('yesno', 'config'),
		'upload_security_check' => array('yesno', 'config'),
		'clear_thumbnames' => array('yesno', 'config'),
		'log_access_errors' => array('yesno', 'config'),
		'ftp_file' => array('text', 'config'),

		'title_files' => array('title', 'config'),
		'my_docs' => array('textbox', 'config'),
		'max_file_size' => array('small_text', 'config', null, null, $txt['media_kb']),
		'max_width' => array('small_text', 'config', null, null, $txt['media_pixels']),
		'max_height' => array('small_text', 'config', null, null, $txt['media_pixels']),
		'allow_over_max' => array('yesno', 'config'),
		'jpeg_compression' => array('small_text', 'config', null, null, '%'),

		'title_previews' => array('title', 'config'),

		'max_thumb_width' => array('small_text', 'config', null, null, $txt['media_pixels']),
		'max_thumb_height' => array('small_text', 'config', null, null, $txt['media_pixels']),
		'max_preview_width' => array('small_text', 'config', null, null, $txt['media_pixels']),
		'max_preview_height' => array('small_text', 'config', null, null, $txt['media_pixels']),
		'max_bigicon_width' => array('small_text', 'config', null, null, $txt['media_pixels']),
		'max_bigicon_height' => array('small_text', 'config', null, null, $txt['media_pixels']),
		'show_extra_info' => array('yesno', 'meta'),

		'title_limits' => array('title', 'layout'),
		'num_items_per_page' => array('small_text', 'layout'),
		'icons_only' => array('yesno', 'layout'),
		'album_columns' => array('small_text', 'layout'),
		'recent_item_limit' => array('small_text', 'layout'),
		'random_item_limit' => array('small_text', 'layout'),
		'recent_comments_limit' => array('small_text', 'layout'),
		'recent_albums_limit' => array('small_text', 'layout'),
		'prev_next' => array('select', 'layout'),

		'title_tag' => array('title', 'layout'),
		'show_linking_code' => array('yesno', 'layout'),
		'max_title_length' => array('small_text', 'layout'),
		'default_tag_type' => array('select', 'layout'),
		'max_thumbs_per_page' => array('small_text', 'layout'),

		'title_misc' => array('title', 'layout'),
		'show_sub_albums_on_index' => array('yesno', 'layout'),
		'player_color' => array('small_text', 'layout'),
		'player_bcolor' => array('small_text', 'layout'),
		'audio_player_width' => array('small_text', 'layout', null, null, $txt['media_pixels']),
		'use_zoom' => array('yesno', 'layout'),
		'disable_playlists' => array('yesno', 'layout'),
		'disable_comments' => array('yesno', 'layout'),
		'disable_ratings' => array('yesno', 'layout'),
	);

	if (empty($amSettings['data_dir_url']) && !empty($amSettings['data_dir_path']))
	{
		global $boardurl, $boarddir;
		$amSettings['data_dir_url'] = $boardurl . str_replace($boarddir, '', $amSettings['data_dir_path']);
	}

	$info = array('datetime', 'copyright', 'xposuretime', 'flash', 'duration', 'make', 'model', 'xres', 'yres', 'resunit', 'focal_length', 'orientation', 'iso', 'meteringMode', 'digitalZoom', 'exifVersion', 'contrast', 'sharpness', 'focusType', 'fnumber','frame_count', 'bit_rate', 'audio_codec', 'video_codec');
	$theme['show_info'] = array('checkbox', 'meta', array());
	foreach ($info as $in)
		$theme['show_info'][2]['show_info_'.$in] = array($txt['media_meta_'.$in], !empty($amSettings['show_info_'.$in]), 'force_name' => 'show_info_'.$in);

	$sho = isset($_POST['prev_next']) ? $_POST['prev_next'] : (empty($amSettings['prev_next']) ? 0 : $amSettings['prev_next']);
	$theme['prev_next'][2][0] = array($txt['media_prevnext_small'], $sho == 0);
	$theme['prev_next'][2][1] = array($txt['media_prevnext_big'], $sho == 1);
	$theme['prev_next'][2][2] = array($txt['media_prevnext_text'], $sho == 2);
	$theme['prev_next'][2][3] = array($txt['media_prevnext_none'], $sho == 3);

	$sho = isset($_POST['default_tag_type']) ? $_POST['default_tag_type'] : (empty($amSettings['default_tag_type']) ? 'normal' : $amSettings['default_tag_type']);
	$theme['default_tag_type'][2]['normal'] = array($txt['media_default_tag_normal'], $sho == 'normal');
	$theme['default_tag_type'][2]['preview'] = array($txt['media_default_tag_preview'], $sho == 'preview');
	$theme['default_tag_type'][2]['full'] = array($txt['media_default_tag_full'], $sho == 'full');

	if (!ini_get('safe_mode'))
		unset($theme['ftp_file']);
	elseif (empty($amSettings['ftp_file']))
		$amSettings['ftp_file'] = dirname(dirname(__FILE__)) . '/MGallerySafeMode.php';

	if (media_handler::testGD2() === true)
		$theme['image_handler'][2][1] = $txt['media_gd2'];
	if (media_handler::testIMagick() === true)
		$theme['image_handler'][2][2] = $txt['media_imagick'];
	if (media_handler::testMW() === true)
		$theme['image_handler'][2][3] = $txt['media_MW'];
	if (media_handler::testImageMagick() !== false)
		$theme['image_handler'][2][4] = $txt['media_imagemagick'];
	if (media_handler::testFFmpeg() === true)
		$context['aeva_extra_data'] = '<div style="padding: 8px 8px 0 8px">' . $txt['media_admin_settings_ffmpeg_installed'] . '</div>';

	if (count($theme['image_handler'][2]) < 2)
		unset($theme['image_handler']);

	// Doc types...
	$default_docs = 'txt,rtf,pdf,xls,doc,ppt,docx,xlsx,pptx,xml,html,htm,php,css,js,zip,rar,ace,arj,7z,gz,tar,tgz,bz,bzip2,sit';
	if (!isset($amSettings['my_docs']))
		$amSettings['my_docs'] = $default_docs;
	$my_docs = array_map('trim', explode(',', $amSettings['my_docs']));
	$amSettings['my_docs'] = trim(implode(', ', $my_docs), ', ');

	$txt['media_admin_settings_my_docs_desc'] = sprintf($txt['media_admin_settings_my_docs_desc'], implode(', ', explode(',', $default_docs)));

	// We need to hide all options except the master setting if:
	// - visiting the page while the master setting is off,
	// - loading the page while the master setting is on but we just requested it to be disabled.
	// Please note that the area's other tabs (Metadata and Layout) won't be shown or hidden in the process,
	// because this would need to be set at the admin menu level, which was earlier. We can all live with that.

	if ($context['current_area'] === 'config' && empty($_POST['media_enabled']) && (isset($_POST['submit_aeva']) || empty($settings['media_enabled'])))
		$theme = array('media_enabled' => array('yesno', 'config'));

	// Submitting?
	if (isset($_POST['submit_aeva']))
	{
		if (isset($_POST['welcome']))
			$_POST['welcome'] = aeva_string($_POST['welcome'], false, 0);
		if (isset($_POST['my_docs']) && (empty($amSettings['my_docs']) || $amSettings['my_docs'] != $_POST['my_docs']))
		{
			$new_docs = array_map('trim', explode(',', strtolower($_POST['my_docs'])));
			$exts = aeva_allowed_types(false, true);
			$exts = array_merge($exts['im'], $exts['vi'], $exts['au'], $exts['zi']);
			foreach ($new_docs as $i => $ext)
				if (in_array($ext, $exts))
					unset($new_docs[$i]);
			$_POST['my_docs'] = trim(implode(', ', $new_docs), ', ');
		}

		foreach ($theme as $setting => $options)
		{
			if ($options[1] !== $context['current_area'])
				continue;
			if ($options[0] !== 'title' && isset($_POST[$setting]))
				$new_value = westr::htmlspecialchars($_POST[$setting]);
			elseif ($options[0] === 'checkbox' && !isset($_POST[$setting]) && !isset($options['skip_check_null']))
				$new_value = 0;
			else
				continue;

			if ($setting == 'clear_thumbnames' && (int) @$amSettings[$setting] !== (int) $new_value)
				$update_thumbnames = true;

			if (!empty($options[2]) && is_array($options[2]) && !in_array($options[0], array('radio', 'select')))
			{
				foreach ($options[2] as $sub_setting => $dummy)
				{
					aeva_updateSettings($sub_setting, isset($_POST[$sub_setting]) ? 1 : 0, true);
					if ($setting === 'show_info')
						$theme['show_info'][2][$sub_setting][1] = !empty($amSettings[$sub_setting]);
				}
			}
			else
			{
				aeva_updateSettings($setting, $new_value, true);
				if ($setting === 'media_enabled')
					updateSettings(array($setting => $new_value));
			}
		}
		if ($amSettings['enable_cache'])
			cache_put_data('aeva_settings', $amSettings, 60);

		// If the Clear Thumbnails setting was changed, we redirect to the hidden maintenance area that renames all thumbnails.
		if (!empty($update_thumbnames))
			redirectexit('action=admin;area=aeva_maintenance;sa=clear;'.$context['session_query']);
	}

	// Render the form
	$context['aeva_form_url'] = '<URL>?action=admin;area=aeva_settings;sa='.$context['current_area'].';'.$context['session_query'];

	foreach ($theme as $setting => $options)
	{
		if ($options[1] != $context['current_area'])
			continue;

		// Options
		if (!empty($options[2]) && $options[0] != 'select')
		{
			foreach ($options[2] as $k => $v)
				if (isset($amSettings[$setting]) && $amSettings[$setting] == $k)
					$options[2][$k] = array($v, true);
		}

		$context['aeva_form'][$setting] = array(
			'type' => $options[0],
			'label' => !isset($options['force_title']) ? $txt['media_admin_settings_' . $setting] : $options['force_title'],
			'fieldname' => $setting,
			'value' => isset($amSettings[$setting]) ? $amSettings[$setting] : '',
			'options' => !empty($options[2]) ? $options[2] : array(),
			'multi' => !empty($options[3]) && $options[3] == true,
			'next' => !empty($options[4]) ? ' ' . $options[4] : null,
			'subtext' => isset($txt['media_admin_settings_' . $setting . '_desc']) ? $txt['media_admin_settings_' . $setting . '_desc'] : '',
		);
		if ($options[0] == 'textbox')
			$context['aeva_form'][$setting]['custom'] = 'rows="6" cols="60"';
		if ($setting == 'max_file_size')
		{
			$context['aeva_form']['php_ini'] = array(
				'type' => 'link',
				'label' => 'upload_max_filesize',
				'subtext' => $txt['media_admin_settings_phpini_desc'],
				'text' => round(aeva_getPHPSize('upload_max_filesize')/1048576, 1) . ' ' . $txt['media_mb'],
				'link' => 'http://php.net/manual/en/ini.core.php#ini.upload-max-filesize',
			);
			$context['aeva_form']['php_ini2'] = array(
				'type' => 'link',
				'label' => 'post_max_size',
				'subtext' => $txt['media_admin_settings_phpini_desc'],
				'text' => round(aeva_getPHPSize('post_max_size')/1048576, 1) . ' ' . $txt['media_mb'],
				'link' => 'http://php.net/manual/en/ini.core.php#ini.post-max-size',
			);
		}
	}
}

function aeva_admin_albums()
{
	loadSource('media/Aeva-Gallery2');
	aeva_albumCP(true);
}

// Handles adding a album
function aeva_admin_albums_add()
{
	loadSource('media/Aeva-Gallery2');
	aeva_addAlbum(true);
}

// Handles the album editing page
function aeva_admin_albums_edit()
{
	loadSource('media/Aeva-Gallery2');
	aeva_addAlbum(true, false);
}

// Moves a album
function aeva_admin_albums_move()
{
	loadSource('media/Aeva-Gallery2');
	aeva_moveAlbum();
}

// Deletes a album and the items/comments in it
function aeva_admin_albums_delete()
{
	loadSource('media/Aeva-Gallery2');
	aeva_deleteAlbum();
}

// Handles the FTP import area
function aeva_admin_FTPImport()
{
	global $amSettings, $context, $txt, $scripturl, $time_start;

	// Load the map
	list ($context['ftp_map'], $context['ftp_folder_list']) = aeva_get_dir_map($amSettings['data_dir_path'] . '/ftp');

	// Filter out unneeded files
	foreach ($context['ftp_map'] as $idMap => $map)
	{
		// Empty folder?
		if (empty($map['files']) && empty($map['folders']))
		{
			@rmdir($map['dirname']);
			unset($context['ftp_map'][$idMap]);
			foreach ($context['ftp_folder_list'] as $id => $id2)
				if ($id2 == $idMap)
					unset($context['ftp_folder_list'][$id]);
			continue;
		}

		foreach ($map['files'] as $id => $file)
		{
			$f = new media_handler;
			$f->init($file[0]);
			if ($f->media_type() == 'unknown' || $file[0] == 'index.php')
				unset($context['ftp_map'][$idMap]['files'][$id]);
			$f->close();
		}
	}

	// Albums
	aeva_getAlbums('', 0, false, 'a.album_of, a.child_level, a.a_order');

	// Build the file cache
	$files = array();
	foreach ($context['ftp_map'] as $id => $map)
		foreach ($map['files'] as $idFile => $file)
			$files[] = array($file, $id, $idFile);

	if (!isset($amSettings['tmp_ftp_num_files']) || empty($_REQUEST['start']))
		aeva_updateSettings('tmp_ftp_num_files', count($files), true);

	wetem::load('aeva_admin_ftpimport');
	$context['is_halted'] = false;
	$context['ftp_done'] = (int) $_REQUEST['start'];

	// Sending the albums and thus... starting the import?
	if (isset($_POST['aeva_submit']) || !empty($_REQUEST['start']))
	{
		// Grab the memory
		ini_set('memory_limit', '128M');

		// Albums set?
		if (isset($_POST['aeva_folder']))
		{
			$context['ftp_folder_albums'] = array();

			foreach ($context['ftp_folder_list'] as $id)
			{
				if ((empty($_POST['aeva_folder_' . $id]) || !isset($context['aeva_albums'][$_POST['aeva_folder_' . $id]])) && !empty($context['ftp_map'][$id]['files']))
					fatal_lang_error('media_album_not_found');
				$context['ftp_folder_albums'][$id] = $_POST['aeva_folder_' . $id];
			}

			aeva_updateSettings('tmp_ftp_album', serialize($context['ftp_folder_albums']), true);
		}
		// Maybe cached
		elseif (isset($amSettings['tmp_ftp_album']))
			$context['ftp_folder_albums'] = unserialize($amSettings['tmp_ftp_album']);
		// None?! Police!
		else
			fatal_lang_error('media_album_not_found');

		// Start the import
		foreach ($files as $id => $file)
		{
			if (microtime(true) - $time_start > 10)
				break;

			$context['ftp_done']++;

			$fame = $file[0][0];
			$name = $title = preg_replace('/[;|\s\._-]+/', ' ', substr($fame, 0, strlen($fame) - strlen(aeva_getExt($fame)) - 1));

			$fame = aeva_string($fame);
			$name = aeva_string($name);

			// Create the file
			$fOpts = array(
				'filename' => $fame,
				'filepath' => $file[0][2],
				'destination' => aeva_getSuitableDir($context['ftp_folder_albums'][$file[1]]),
				'album' => $context['ftp_folder_albums'][$file[1]],
				'is_uploading' => false,
				'security_override' => true,
			);
			$ret = aeva_createFile($fOpts);
			if (!empty($ret['error']))
				continue;

			$id_file = $ret['file'];
			$id_thumb = $ret['thumb'];
			$id_preview = $ret['preview'];
			$time = empty($ret['time']) ? 0 : $ret['time'];

			// Create the item
			$iOpts = array(
				'id_file' => $id_file,
				'id_thumb' => $id_thumb,
				'id_preview' => $id_preview,
				'title' => $name,
				'time' => $time,
				'album' => $context['ftp_folder_albums'][$file[1]],
				'id_member' => we::$id,
				'approved' => 1,
				'mem_name' => we::$user['name'],
			);
			$id_item = aeva_createItem($iOpts);

			// Get rid of the file
			@unlink($file[0][2]);
			unset($context['ftp_map'][$file[1]]['files'][$file[2]]);

			media_markSeen($id_item, 'force_insert');
		}

		if ($amSettings['tmp_ftp_num_files'] > $context['ftp_done'])
		{
			media_resetUnseen();
			$context['is_halted'] = true;
			$context['total_files'] = $amSettings['tmp_ftp_num_files'];
			aeva_refreshPage($scripturl . '?action=admin;area=aeva_ftp;start=' . $context['ftp_done'] . ';' . $context['session_query']);
		}
		else
			wesql::query('
				DELETE FROM {db_prefix}media_settings
				WHERE name IN ({array_string:names})',
				array('names' => array('tmp_ftp_num_files', 'tmp_ftp_album'))
			);
	}
}

// Handles the permission area...
function aeva_admin_perms()
{
	global $context, $txt, $amSettings;

	$context['base_url'] = '<URL>?action=admin;area=aeva_perms;' . $context['session_query'];

	// Sub-actions...
	$sa = array(
		'view' => 'aeva_admin_perms_view',
		'edit' => 'aeva_admin_perms_edit',
		'add' => 'aeva_admin_perms_add',
		'albums' => 'aeva_admin_perms_albums',
		'quick' => 'aeva_admin_perms_quick',
	);

	if (isset($_REQUEST['sa'], $sa[$_REQUEST['sa']]))
		return $sa[$_REQUEST['sa']]();

	// Deleting something?
	if (isset($_POST['aeva_delete_profs']))
	{
		// Get the ones to delete
		$to_delete = array();
		foreach ($_POST as $k => $v)
			if (substr($k, 0, 12) == 'delete_prof_' && substr($k, 12) > 1)
				$to_delete[] = substr($k, 12);

		if (empty($to_delete))
			fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));

		// Profile to switch to..
		$id_profile = (int) $_POST['del_prof'];
		if (in_array($id_profile, $to_delete))
			fatal_lang_error('media_albumSwitchError', false);

		// If no target profile is specified, make sure deleted profiles aren't in use.
		if (empty($id_profile))
		{
			$request = wesql::query('
				SELECT id_album
				FROM {db_prefix}media_albums
				WHERE id_perm_profile IN ({array_int:ids})
				LIMIT 1',
				array('ids' => $to_delete)
			);
			if (wesql::num_rows($request) > 0)
				fatal_lang_error('media_albumSwitchError', false);
			wesql::free_result($request);
		}

		if ($id_profile != 1)
		{
			$request = wesql::query('
				SELECT id
				FROM {db_prefix}media_variables
				WHERE id = {int:id}',
				array('id' => $id_profile)
			);
			if (wesql::num_rows($request) == 0)
				fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));
			wesql::free_result($request);
		}

		wesql::query('
			DELETE FROM {db_prefix}media_variables
			WHERE id IN ({array_int:id})
				AND type = {literal:perm_profile}',
			array(
				'id' => $to_delete,
			)
		);

		if (wesql::affected_rows() > 0)
			wesql::query('
				UPDATE {db_prefix}media_albums
				SET id_perm_profile = {int:id_profile}
				WHERE id_perm_profile IN ({array_int:profiles})',
				array(
					'id_profile' => $id_profile,
					'profiles' => $to_delete,
				)
			);

		wesql::query('
			DELETE FROM {db_prefix}media_perms
			WHERE id_profile IN ({array_int:id})',
			array('id' => $to_delete)
		);
	}

	// Get the profiles then...
	$context['aeva_profiles'] = array(
		1 => array(
			'name' => $txt['media_default_perm_profile'],
			'id' => 1,
			'undeletable' => true,
			'albums' => 0,
		),
	);
	$request = wesql::query('
		SELECT id, val1
		FROM {db_prefix}media_variables
		WHERE type = {literal:perm_profile}'
	);
	while ($row = wesql::fetch_assoc($request))
		$context['aeva_profiles'][$row['id']] = array(
			'name' => censorText($row['val1']),
			'id' => $row['id'],
			'albums' => 0,
			'member' => array(
				'id' => !empty($row['id_member']) ? $row['id_member'] : 0,
				'name' => !empty($row['real_name']) ? $row['real_name'] : '',
				'href' => !empty($row['id_member']) ? '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>' : '',
			),
		);
	wesql::free_result($request);

	// Get the no. of albums for each profile
	$request = wesql::query('
		SELECT COUNT(*) AS total, id_perm_profile
		FROM {db_prefix}media_albums
		GROUP BY id_perm_profile',
		array()
	);
	while ($row = wesql::fetch_assoc($request))
		if (isset($context['aeva_profiles'][$row['id_perm_profile']]))
			$context['aeva_profiles'][$row['id_perm_profile']]['albums'] = $row['total'];
	wesql::free_result($request);

	wetem::load('aeva_admin_perms');
	// Page title
	$context['page_title'] = $txt['media_admin_labels_perms'];
}

// Sets permissions quickly
function aeva_admin_perms_quick()
{
	global $context, $txt;

	// Load the permission profle.
	if ($_REQUEST['profile'] == 1)
		$profile = array(
			'id' => 1,
			'name' => $txt['media_default_perm_profile']
		);
	else
	{
		$request = wesql::query('
			SELECT id, val1
			FROM {db_prefix}media_variables
			WHERE id = {int:id} AND type = {literal:perm_profile}',
			array(
				'id' => (int) $_REQUEST['profile'],
			)
		);
		if (wesql::num_rows($request) == 0)
			redirectexit($context['base_url']);

		$profile = array();
		list ($profile['id'], $profile['name']) = wesql::fetch_row($request);

		wesql::free_result($request);
	}

	// The permissions...
	$groups = array(-1, 0);
	$request = wesql::query('
		SELECT g.id_group AS id
		FROM {db_prefix}membergroups AS g
		WHERE (g.id_group > 3 OR g.id_group = 2)
		ORDER BY g.min_posts, g.id_group ASC',
		array('user_id' => we::$id)
	);
	while ($row = wesql::fetch_assoc($request))
		$groups[] = $row['id'];
	wesql::free_result($request);

	// What is the type? And it has to be unique!
	if (trim($_POST['copy_membergroup']) !== '' && !empty($_POST['with_selected']) && trim($_POST['selected_perm'], "\x00..\x1f\xa0\xc2") !== '')
		fatal_lang_error('media_admin_unique_permission', false);
	// There has to be at least one thing selected. Actually there has to be only one thing selected...
	elseif (trim($_POST['copy_membergroup']) === '' && (empty($_POST['with_selected']) || empty($_POST['selected_perm']) || !in_array($_POST['with_selected'], array('apply', 'clear')) || !in_array($_POST['selected_perm'], $context['aeva_album_permissions'])))
		fatal_lang_error('media_admin_quick_none', false);
	// We're good! :)
	else
		$type = trim($_POST['copy_membergroup']) === '' ? $_POST['with_selected'] : 'copy';

	// Invalid groups?
	if (empty($_POST['groups']) || !is_array($_POST['groups']))
		fatal_lang_error('media_admin_invalid_groups', false);
	elseif ($type == 'copy' && (!in_array($_POST['copy_membergroup'], $groups) || in_array($_POST['copy_membergroup'], $_POST['groups'])))
		fatal_lang_error('media_admin_invalid_groups');
	elseif ($type == 'apply' || $type == 'clear')
		foreach ($_POST['groups'] as $group)
			if (!in_array($group, $groups))
				fatal_lang_error('media_admin_invalid_groups');

	// OK roll out the actions, is it copying?
	if ($type == 'copy')
	{
		// Let's dump the permissions of the groups
		wesql::query('
			DELETE FROM {db_prefix}media_perms
			WHERE id_group IN ({array_int:groups})
				AND id_profile = {int:profile}',
			array(
				'groups' => $_POST['groups'],
				'profile' => $profile['id'],
			)
		);

		// Get the permissions from this group..
		$request = wesql::query('
			SELECT permission
			FROM {db_prefix}media_perms
			WHERE id_group = {int:group}
				AND id_profile = {int:profile}',
			array(
				'group' => (int) $_POST['copy_membergroup'],
				'profile' => $profile['id'],
			)
		);
		$permissions = array();
		while ($row = wesql::fetch_assoc($request))
			$permissions[] = $row['permission'];
		wesql::free_result($request);

		// Insert the permissions now
		foreach ($_POST['groups'] as $group)
			foreach ($permissions as $permission)
				if (in_array($permission, $context['aeva_album_permissions']))
					wesql::insert('',
						'{db_prefix}media_perms',
						array('id_profile', 'id_group', 'permission'),
						array($profile['id'], $group, $permission)
					);
	}
	// Then we're definitely applying a permission....
	elseif ($type == 'apply')
	{
		// Insert it...
		foreach ($_POST['groups'] as $group)
			wesql::insert('ignore',
				'{db_prefix}media_perms',
				array('id_profile', 'id_group', 'permission'),
				array($profile['id'], $group, $_POST['selected_perm'])
			);
	}
	// Oh I know I know! we're clearing them!
	else
	{
		wesql::query('
			DELETE FROM {db_prefix}media_perms
			WHERE id_profile = {int:profile}
				AND permission = {string:permission}
				AND id_group IN ({array_int:groups})',
			array(
				'profile' => $profile['id'],
				'permission' => $_POST['selected_perm'],
				'groups' => $_POST['groups'],
			)
		);
	}

	redirectexit($context['base_url'] . ';sa=view;in=' . $profile['id']);
}

// A not so hefty function to add permission profiles
function aeva_admin_perms_add()
{
	global $context, $txt;

	if (empty($_POST['name']) || empty($_POST['submit_aeva']))
		fatal_lang_error('media_name_empty');

	wesql::insert('',
		'{db_prefix}media_variables',
		array('type', 'val1'),
		array('perm_profile', westr::htmlspecialchars($_POST['name']))
	);

	redirectexit($context['base_url']);
}

// Used for viewing membergroups in the permission area..
function aeva_admin_perms_view()
{
	global $context, $txt, $settings;

	// Load the profile
	if (!isset($_REQUEST['in']))
		fatal_lang_error('media_admin_perm_invalid');

	if ($_REQUEST['in'] == 1)
		$context['aeva_profile'] = array(
			'name' => $txt['media_default_perm_profile'],
			'id' => 1,
		);
	else
	{
		$request = wesql::query('
			SELECT id, val1
			FROM {db_prefix}media_variables
			WHERE id = {int:id}
				AND type = {literal:perm_profile}',
			array(
				'id' => (int) $_REQUEST['in'],
			)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('media_admin_perm_invalid');
		$row = wesql::fetch_assoc($request);
		wesql::free_result($request);

		$context['aeva_profile'] = array(
			'id' => $row['id'],
			'name' => censorText($row['val1']),
		);
	}

	// Load membergroups
	$groups = aeva_getMembergroups(true); // true = permissions, not quotas

	// Get membergroup permission count
	$request = wesql::query('
		SELECT COUNT(*) AS total, id_group
		FROM {db_prefix}media_perms
		WHERE id_profile = {int:id}
		GROUP BY id_group',
		array('id' => (int) $_REQUEST['in'])
	);
	while ($row = wesql::fetch_assoc($request))
		$groups[$row['id_group']]['perms'] = $row['total'];
	wesql::free_result($request);

	$context['membergroups'] = $groups;
	wetem::load('aeva_admin_perms_view');
}

// Editing one membergroup?
function aeva_admin_perms_edit()
{
	global $context, $txt;

	// Load the profile
	if (!isset($_REQUEST['in']))
		fatal_lang_error('media_admin_perm_invalid');
	if (!isset($_REQUEST['group']))
		fatal_lang_error('media_admin_invalid_mg');

	$rgroup = (int) $_REQUEST['group'];
	$rid = (int) $_REQUEST['in'];

	if ($rid == 1)
		$context['aeva_profile'] = array(
			'name' => $txt['media_default_perm_profile'],
			'id' => 1,
		);
	else
	{
		$request = wesql::query('
			SELECT id, val1
			FROM {db_prefix}media_variables
			WHERE id = {int:id}
				AND type = {literal:perm_profile}',
			array(
				'id' => $rid,
			)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('media_admin_perm_invalid');
		$row = wesql::fetch_assoc($request);
		wesql::free_result($request);

		$context['aeva_profile'] = array(
			'id' => $row['id'],
			'name' => censorText($row['val1']),
		);
	}

	// Membergroup...
	if ($rgroup == -1 || $rgroup == 0)
	{
		$context['aeva_group'] = array(
			'id' => $rgroup,
			'name' => $txt['media_membergroups_' . ($rgroup == -1 ? 'guests' : 'members')],
		);
	}
	else
	{
		$request = wesql::query('
			SELECT id_group, group_name AS name
			FROM {db_prefix}membergroups
			WHERE id_group = {int:group}',
			array('group' => (int) $rgroup)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('media_admin_invalid_mg');
		$context['aeva_group'] = array();
		$row = wesql::fetch_assoc($request);
		$context['aeva_group'] = array(
			'id' => $row['id_group'],
			'name' => $row['name'],
		);
		wesql::free_result($request);
	}

	// Now get what all is checked...
	$request = wesql::query('
		SELECT permission
		FROM {db_prefix}media_perms
		WHERE id_group = {int:group}
			AND id_profile = {int:profile}',
		array(
			'group' => $rgroup,
			'profile' => $rid,
		)
	);
	$context['aeva_perm'] = array();
	while ($row = wesql::fetch_assoc($request))
		$context['aeva_perm'][] = $row['permission'];
	wesql::free_result($request);

	// The form....
	$context['aeva_form_url'] = $context['base_url'] . ';sa=edit;group=' . $rgroup . ';in=' . $rid;
	$context['aeva_form'] = array(
		'title' => array(
			'label' => $context['aeva_profile']['name'] . ' - ' . $context['aeva_group']['name'],
			'type' => 'title',
		),
	);

	foreach ($context['aeva_album_permissions'] as $perm)
	{
		$context['aeva_form'][$perm] = array(
			'label' => $txt['permissionname_media_' . $perm],
			'type' => 'checkbox',
			'options' => array(
				0 => array(
					0 => '',
					1 => in_array($perm, $context['aeva_perm']),
				)
			),
			'fieldname' => $perm,
		);
	}

	// Submitting?
	if (isset($_POST['submit_aeva']))
	{
		// Flush the current perm
		wesql::query('
			DELETE FROM {db_prefix}media_perms
			WHERE id_group = {int:group}
				AND id_profile = {int:profile}',
			array(
				'group' => $rgroup,
				'profile' => $rid,
			)
		);

		// Insert it
		foreach ($context['aeva_album_permissions'] as $perm)
		{
			if (!isset($_POST[$perm]))
				continue;

			wesql::insert('',
				'{db_prefix}media_perms',
				array('id_group', 'id_profile', 'permission'),
				array($rgroup, $rid, $perm)
			);
		}
		redirectexit($context['base_url'] . ';sa=view;in=' . $rid);
	}

	wetem::load('aeva_form');
}

function aeva_admin_perms_albums()
{
	global $context, $txt, $settings, $galurl;

	// Ensure we can access this profile...
	if ($_POST['prof'] != 1)
	{
		$request = wesql::query('
			SELECT id
			FROM {db_prefix}media_variables
			WHERE id = {int:id}
				AND type = {literal:perm_profile}',
			array(
				'id' => (int) $_POST['prof'],
			)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('media_admin_perm_invalid');
		$row = wesql::fetch_assoc($request);
		wesql::free_result($request);
	}
	else
		$row['id'] = 1;

	// We can we can!
	$request = wesql::query('
		SELECT a.id_album, a.name
		FROM {db_prefix}media_albums AS a
		WHERE a.id_perm_profile = {int:prof}
		ORDER BY name ASC',
		array('prof' => (int) $_POST['prof'])
	);
	$string_parts = array();
	while ($album_row = wesql::fetch_assoc($request))
		$string_parts[] = '<a href="' . $galurl . 'sa=album;in=' . $album_row['id_album'] . '">' . $album_row['name'] . '</a>';
	wesql::free_result($request);

	if (empty($string_parts))
		$string_parts[] = '<span style="font-style: italic">None</span>';

	return_xml('
<albums>
	<id_profile>' . $row['id'] . '</id_profile>
	<album_string><![CDATA[' . $txt['media_albums'] . ': ' . implode(', ', $string_parts) . ']]></album_string>
</albums>');
}

// Membergroup quota's main function. This is soooo similar to permission profiles...
function aeva_admin_quotas()
{
	global $txt, $context, $amSettings;

	// Doing any do-da-do?
	$sa = array(
		'view' => 'aeva_admin_quotas_view',
		'edit' => 'aeva_admin_quotas_edit',
		'add' => 'aeva_admin_quotas_add',
		'albums' => 'aeva_admin_quotas_albums',
	);

	if (isset($_REQUEST['sa'], $sa[$_REQUEST['sa']]))
		return $sa[$_REQUEST['sa']]();

	// Homepage then...

	// Maybe deleting something?
	if (isset($_POST['aeva_delete_profs']) && !empty($_POST['del_prof']))
	{
		// Get the ones to delete
		$to_delete = array();
		foreach ($_POST as $k => $v)
			if (substr($k, 0, 12) == 'delete_prof_' && substr($k, 12) > 1)
				$to_delete[] = substr($k, 12);

		if (empty($to_delete))
			fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));

		// Profile to switch to..
		$id_profile = (int) $_POST['del_prof'];
		if (empty($id_profile) || in_array($id_profile, $to_delete))
			fatal_lang_error('media_albumSwitchError', false);

		if ($id_profile != 1)
		{
			$request = wesql::query('
				SELECT id
				FROM {db_prefix}media_variables
				WHERE id = {int:id}',
				array('id' => $id_profile)
			);
			if (wesql::num_rows($request) == 0)
				fatal_lang_error('media_accessDenied', !empty($amSettings['log_access_errors']));
			wesql::free_result($request);
		}

		wesql::query('
			DELETE FROM {db_prefix}media_variables
			WHERE id IN ({array_int:id})
				AND type = {literal:quota_prof}',
			array(
				'id' => $to_delete,
			)
		);

		if (wesql::affected_rows() > 0)
			wesql::query('
				UPDATE {db_prefix}media_albums
				SET id_quota_profile = {int:id_profile}
				WHERE id_quota_profile IN ({array_int:profiles})',
				array(
					'id_profile' => $id_profile,
					'profiles' => $to_delete,
				)
			);

		wesql::query('
			DELETE FROM {db_prefix}media_quotas
			WHERE id_profile IN ({array_int:id})',
			array('id' => $to_delete)
		);
	}

	// Load the profiles
	$request = wesql::query('
		SELECT id, val1
		FROM {db_prefix}media_variables
		WHERE type = {literal:quota_prof}'
	);
	$context['aeva_profiles'] = array(
		1 => array(
			'name' => $txt['media_default_perm_profile'],
			'id' => 1,
			'undeletable' => true,
			'albums' => 0,
		),
	);
	while ($row = wesql::fetch_assoc($request))
		$context['aeva_profiles'][$row['id']] = array(
			'id' => $row['id'],
			'name' => $row['val1'],
			'albums' => 0,
		);
	wesql::free_result($request);

	// Load the album count..
	$request = wesql::query('
		SELECT id_quota_profile, COUNT(*) AS total
		FROM {db_prefix}media_albums
		GROUP BY id_quota_profile',
		array()
	);
	while ($row = wesql::fetch_assoc($request))
		if (isset($context['aeva_profiles'][$row['id_quota_profile']]))
			$context['aeva_profiles'][$row['id_quota_profile']]['albums'] = $row['total'];
	wesql::free_result($request);

	wetem::load('aeva_admin_quotas');
	$context['page_title'] = $txt['media_admin_labels_quotas'];
}

// Adding a profile?
function aeva_admin_quotas_add()
{
	global $context, $txt;

	// Name not being submitted?
	if (empty($_POST['name']))
		redirectexit('action=admin;area=aeva_quotas;' . $context['session_query']);

	// Insert it!
	wesql::insert('',
		'{db_prefix}media_variables',
		array('type', 'val1'),
		array('quota_prof', westr::htmlspecialchars($_POST['name']))
	);

	redirectexit('action=admin;area=aeva_quotas;' . $context['session_query']);
}

// Viewing a single group?
function aeva_admin_quotas_view()
{
	global $txt, $context;

	// Not set?
	if (!isset($_REQUEST['in']))
		fatal_lang_error('media_admin_prof_not_found');

	if ($_REQUEST['in'] == 1)
		$context['aeva_profile'] = array(
			'name' => $txt['media_default_perm_profile'],
			'id' => 1,
		);
	else
	{
		$request = wesql::query('
			SELECT id, val1
			FROM {db_prefix}media_variables
			WHERE id = {int:id}
				AND type = {literal:quota_prof}',
			array(
				'id' => (int) $_REQUEST['in'],
			)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('media_admin_prof_not_found');
		$row = wesql::fetch_assoc($request);
		wesql::free_result($request);

		$context['aeva_profile'] = array(
			'id' => $row['id'],
			'name' => censorText($row['val1']),
		);
	}

	$context['membergroups'] = aeva_getMembergroups();
	wetem::load('aeva_admin_quota_view');
}

// Editing a single membergroup?
function aeva_admin_quotas_edit()
{
	global $context, $txt, $amSettings;

	// Not set?
	if (!isset($_REQUEST['in']) || !isset($_REQUEST['group']))
		fatal_lang_error('media_admin_prof_not_found');

	// Load this
	if ($_REQUEST['in'] == 1)
		$context['aeva_profile'] = array(
			'name' => $txt['media_default_perm_profile'],
			'id' => 1,
		);
	else
	{
		$request = wesql::query('
			SELECT id, val1
			FROM {db_prefix}media_variables
			WHERE id = {int:id}
				AND type = {literal:quota_prof}',
			array(
				'id' => (int) $_REQUEST['in'],
			)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('media_admin_prof_not_found');
		$row = wesql::fetch_assoc($request);
		$context['aeva_profile'] = array(
			'id' => $row['id'],
			'name' => $row['val1'],
		);
		wesql::free_result($request);
	}

	// Membergroup...
	if (!isset($_REQUEST['group']))
		fatal_lang_error('media_admin_invalid_mg');
	if ($_REQUEST['group'] == -1 || $_REQUEST['group'] == 0)
	{
		$context['aeva_group'] = array(
			'id' => (int) $_REQUEST['group'],
			'name' => $txt['media_membergroups_' . ($_REQUEST['group'] == -1 ? 'guests' : 'members')],
		);
	}
	else
	{
		$request = wesql::query('
			SELECT id_group, group_name AS name
			FROM {db_prefix}membergroups
			WHERE id_group = {int:group}',
			array('group' => (int) $_REQUEST['group'])
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('media_admin_invalid_mg');
		$context['aeva_group'] = array();
		$row = wesql::fetch_assoc($request);
		$context['aeva_group'] = array(
			'id' => $row['id_group'],
			'name' => $row['name'],
		);
		wesql::free_result($request);
	}

	// The types
	$types = array('image', 'audio', 'video', 'doc');

	// Load the limits
	$request = wesql::query('
		SELECT quota, type
		FROM {db_prefix}media_quotas
		WHERE id_profile = {int:profile}
			AND id_group = {int:group}',
		array(
			'profile' => $context['aeva_profile']['id'],
			'group' => $context['aeva_group']['id'],
		)
	);
	$limits = array();
	while ($row = wesql::fetch_assoc($request))
		$limits[$row['type']] = $row['quota'];
	wesql::free_result($request);

	foreach ($types as $type)
		if (!isset($limits[$type]))
			$limits[$type] = $amSettings['max_file_size'];

	// Set the form
	$context['aeva_form_url'] = '<URL>?action=admin;area=aeva_quotas;sa=edit;in=' . $context['aeva_profile']['id'] . ';group=' . $context['aeva_group']['id'] . ';' . $context['session_query'];

	$context['aeva_form'] = array(
		'title' => array(
			'label' => $context['aeva_profile']['name'] . ' - ' . $context['aeva_group']['name'],
			'type' => 'title',
		),
	);
	foreach ($types as $type)
		$context['aeva_form'][$type] = array(
			'label' => $txt['media_' . $type],
			'type' => 'text',
			'fieldname' => $type,
			'value' => $limits[$type],
			'size' => 10,
			'next' => ' ' . $txt['media_kb'],
		);
	wetem::load('aeva_form');

	// Submitting?
	if (isset($_POST['submit_aeva']))
	{
		foreach ($types as $type)
		{
			$_POST[$type] = max(0, !isset($_POST[$type]) ? $limits[$type] : (int) $_POST[$type]);

			wesql::query('
				UPDATE {db_prefix}media_quotas
				SET quota = {int:quota}
				WHERE id_group = {int:group}
					AND id_profile = {int:profile}
					AND type = {string:type}',
				array(
					'quota' => $_POST[$type],
					'group' => $context['aeva_group']['id'],
					'profile' => $context['aeva_profile']['id'],
					'type' => $type,
				)
			);
			// !! Temporary fix. Don't know why mysql_affected_rows() was returning 0.
			wesql::insert('ignore',
				'{db_prefix}media_quotas',
				array('id_group', 'id_profile', 'type', 'quota'),
				array($context['aeva_group']['id'], $context['aeva_profile']['id'], $type, $_POST[$type])
			);
		}

		redirectexit('action=admin;area=aeva_quotas;sa=view;in=' . $context['aeva_profile']['id'] . ';' . $context['session_query']);
	}
}

function aeva_admin_quotas_albums()
{
	global $context, $txt, $galurl;

	// Ensure we can access this profile...
	if ($_POST['prof'] != 1)
	{
		$request = wesql::query('
			SELECT id
			FROM {db_prefix}media_variables
			WHERE id = {int:id}
				AND type = {literal:quota_prof}',
			array(
				'id' => (int) $_POST['prof'],
			)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('media_admin_perm_invalid');
		$row = wesql::fetch_assoc($request);
		wesql::free_result($request);
	}
	else
		$row['id'] = 1;

	// Yes we can!
	$request = wesql::query('
		SELECT a.id_album, a.name
		FROM {db_prefix}media_albums AS a
		WHERE a.id_quota_profile = {int:prof}
		ORDER BY name ASC',
		array('prof' => (int) $_POST['prof'])
	);
	$string_parts = array();
	while ($album_row = wesql::fetch_assoc($request))
		$string_parts[] = '<a href="' . $galurl . 'sa=album;in=' . $album_row['id_album'] . '">' . $album_row['name'] . '</a>';
	wesql::free_result($request);

	if (empty($string_parts))
		$string_parts[] = '<span style="font-style: italic">None</span>';

	return_xml('
<albums>
	<id_profile>' . $row['id'] . '</id_profile>
	<album_string><![CDATA[' . $txt['media_albums'] . ': ' . implode(', ', $string_parts) . ']]></album_string>
</albums>');
}

// Custom fields main area
function aeva_admin_fields()
{
	global $context, $txt;

	$sa = array(
		'edit' => 'aeva_admin_fields_edit',
	);

	if (isset($_REQUEST['sa'], $sa[$_REQUEST['sa']]))
		return $sa[$_REQUEST['sa']]();

	// Deleting a field?
	if (isset($_REQUEST['delete']) && !empty($_REQUEST['delete']))
	{
		wesql::query('
			DELETE FROM {db_prefix}media_fields
			WHERE id_field = {int:field}',
			array('field' => (int) $_REQUEST['delete'])
		);
		wesql::query('
			DELETE FROM {db_prefix}media_field_data
			WHERE id_field = {int:field}',
			array('field' => (int) $_REQUEST['delete'])
		);
	}

	// Load the fields :D
	$context['custom_fields'] = aeva_loadCustomFields();

	wetem::load('aeva_admin_fields');
}

// Editing/adding a field?
function aeva_admin_fields_edit()
{
	global $context, $txt;

	loadSource('Class-Editor');

	// Editing?
	if (!empty($_REQUEST['in']))
	{
		$field = aeva_loadCustomFields(null, array(), 'cf.id_field = ' . (int) $_REQUEST['in']);
		if (empty($field[$_REQUEST['in']]))
			fatal_lang_error('media_cf_invalid');
		$field = $field[$_REQUEST['in']];
		$field['name'] = wedit::un_preparsecode($field['name']);
		$field['raw_desc'] = wedit::un_preparsecode($field['raw_desc']);
	}
	else
	{
		$field = array(
			'id' => 0,
			'raw_desc' => '',
			'options' => array(),
			'name' => '',
			'bbc' => false,
			'albums' => 'all_albums',
			'type' => 'text',
			'required' => false,
			'searchable' => false,
		);
	}

	// Load the albums
	aeva_getAlbums('', 0, false);

	$album_opts = array(
		'all_albums' => array($txt['media_all_albums'], $field['albums'] == 'all_albums'),
	);
	foreach ($context['aeva_albums'] as $album)
		$album_opts[$album['id']] = array($album['name'], is_array($field['albums']) ? in_array($album['id'], $field['albums']) : false);

	aeva_createTextEditor('desc', 'aeva_form', false, $field['raw_desc']);

	$context['aeva_form'] = array(
		'title' => array(
			'type' => 'title',
			'label' => $txt['media_cf_editing'],
		),
		'name' => array(
			'fieldname' => 'name',
			'type' => 'text',
			'value' => $field['name'],
			'label' => $txt['media_name'],
		),
		'desc' => array(
			'fieldname' => 'desc',
			'type' => 'textbox',
			'value' => $field['raw_desc'],
			'label' => $txt['media_add_desc'],
		),
		'type' => array(
			'fieldname' => 'type',
			'type' => 'select',
			'label' => $txt['media_cf_type'],
			'options' => array(
				'text' => array($txt['media_cf_text'], $field['type'] == 'text'),
				'textbox' => array($txt['media_cf_textbox'], $field['type'] == 'textbox'),
				'radio' => array($txt['media_cf_radio'], $field['type'] == 'radio'),
				'checkbox' => array($txt['media_cf_checkbox'], $field['type'] == 'checkbox'),
				'select' => array($txt['media_cf_select'], $field['type'] == 'select'),
			),
		),
		'albums' => array(
			'fieldname' => 'albums',
			'type' => 'select',
			'multi' => true,
			'options' => $album_opts,
			'label' => $txt['media_albums'],
		),
		'options' => array(
			'fieldname' => 'options',
			'type' => 'text',
			'value' => implode(',', $field['options']),
			'subtext' => $txt['media_cf_options_desc'],
			'label' => $txt['media_cf_options'],
		),
		'bbc' => array(
			'fieldname' => 'bbc',
			'type' => 'yesno',
			'label' => $txt['media_cf_bbcode'],
			'value' => $field['bbc'],
		),
		'req' => array(
			'fieldname' => 'required',
			'type' => 'yesno',
			'label' => $txt['media_cf_req'],
			'value' => $field['required'],
		),
		'search' => array(
			'fieldname' => 'searchable',
			'type' => 'yesno',
			'label' => $txt['media_cf_searchable'],
			'value' => $field['searchable'],
		),
	);
	$context['aeva_form_url'] = '<URL>?action=admin;area=aeva_fields;sa=edit;in=' . $field['id'] . ';' . $context['session_query'];

	wetem::load('aeva_form');

	// Submitting?
	if (isset($_POST['submit_aeva']))
	{
		$field_name = westr::htmlspecialchars($_POST['name']);
		$field_desc = westr::htmlspecialchars($_POST['desc']);
		wedit::preparsecode($field_name);
		wedit::preparsecode($field_desc);

		$field_type = in_array($_POST['type'], array('text', 'textbox', 'checkbox', 'radio', 'select')) ? $_POST['type'] : 'text';

		if (empty($field_name))
			fatal_lang_error('media_name_left_empty');

		// Options?
		$options = array();
		if (in_array($field_type, array('checkbox', 'radio', 'select')))
		{
			$options = explode(',', $_POST['options']);
			foreach ($options as $k => $v)
			{
				if (trim($v) == '')
					unset($options[$k]);

				$options[$k] = westr::htmlspecialchars($options[$k]);
			}

			if (empty($options))
				fatal_lang_error('media_cf_options_empty');
		}
		$options = implode(',', $options);

		// Albums
		$albums = in_array('all_albums', $_POST['albums']) ? 'all_albums' : $_POST['albums'];
		if (is_array($albums))
			foreach ($albums as $k => $v)
				if (!in_array($v, array_keys($context['aeva_albums'])))
					unset($albums[$k]);
		if (empty($albums))
			fatal_lang_error('media_cf_albums_empty');
		if (is_array($albums))
			$albums = implode(',', $albums);

		// Misc. options
		$field_bbc = (int) $_POST['bbc'];
		$field_req = (int) $_POST['required'];
		$field_searchable = (int) $_POST['searchable'];

		// Insert/update it
		if (!empty($field['id']))
			wesql::query('
				UPDATE {db_prefix}media_fields
				SET name = {string:name},
					description = {string:desc},
					albums = {string:albums},
					options = {string:options},
					type = {string:type},
					bbc = {int:bbc},
					required = {int:required},
					searchable = {int:searchable}
				WHERE id_field = {int:field}',
				array(
					'name' => $field_name,
					'desc' => $field_desc,
					'albums' => $albums,
					'options' => $options,
					'type' => $field_type,
					'bbc' => $field_bbc,
					'required' => $field_req,
					'searchable' => $field_searchable,
					'field' => $field['id'],
				)
			);
		else
			wesql::insert('',
				'{db_prefix}media_fields',
				array('name', 'description', 'albums', 'options', 'type', 'bbc', 'required', 'searchable'),
				array($field_name, $field_desc, $albums, $options, $field_type, $field_bbc, $field_req, $field_searchable)
			);

		redirectexit('action=admin;area=aeva_fields;' . $context['session_query']);
	}
}

function aeva_getMembergroups($perms = false)
{
	global $txt;

	$request = wesql::query('
		SELECT COUNT(*) AS total
		FROM {db_prefix}members
		WHERE id_group = {string:regular} AND is_activated = 1',
		array('regular' => '')
	);
	list ($regular_members) = wesql::fetch_row($request);
	wesql::free_result($request);

	$groups = array();
	if ($perms)
		$groups[-1] = array('name' => $txt['media_membergroups_guests'], 'num_members' => '');
	$groups[0] = array('name' => $txt['media_membergroups_members'], 'num_members' => $regular_members); // $settings['totalMembers']
	$request = wesql::query('
		SELECT g.id_group AS id, g.group_name AS name, g.min_posts, g.min_posts != -1 AS is_post_group
		FROM {db_prefix}membergroups AS g
		WHERE ' . ($perms ? 'g.id_group > 3 OR g.id_group = 2' : 'g.id_group != 3') . '
		ORDER BY g.min_posts, g.id_group ASC',
		array('user_id' => we::$id)
	);
	$separated = false;
	$normalGroups = array();
	$postGroups = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$groups[$row['id']] = array('name' => $row['min_posts'] > -1 ? '<span style="font-style: italic">' . $row['name'] . '</span>' : $row['name'], 'num_members' => 0);
		if ($row['min_posts'] == -1)
			$normalGroups[$row['id']] = $row['id'];
		else
			$postGroups[$row['id']] = $row['id'];
	}
	wesql::free_result($request);

	// Get the number of members in this post group
	if (!empty($postGroups))
	{
		$query = wesql::query('
			SELECT id_post_group AS id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_post_group IN ({array_int:post_group_list}) AND is_activated = 1
			GROUP BY id_post_group',
			array('post_group_list' => $postGroups)
		);
		while ($row = wesql::fetch_assoc($query))
			$groups[$row['id_group']]['num_members'] += $row['num_members'];
		wesql::free_result($query);
	}

	// Taken from ManagePermissions.php
	if (!empty($normalGroups))
	{
		// First, the easy one!
		$query = wesql::query('
			SELECT id_group AS id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:normal_group_list}) AND is_activated = 1
			GROUP BY id_group',
			array('normal_group_list' => $normalGroups)
		);
		while ($row = wesql::fetch_assoc($query))
			$groups[$row['id_group']]['num_members'] += $row['num_members'];
		wesql::free_result($query);

		// This one is slower, but it's okay... careful not to count twice!
		$query = wesql::query('
			SELECT mg.id_group, COUNT(*) AS num_members
			FROM {db_prefix}membergroups AS mg
				INNER JOIN {db_prefix}members AS mem ON (
					mem.additional_groups != {string:blank_string}
					AND mem.id_group != mg.id_group
					AND FIND_IN_SET(mg.id_group, mem.additional_groups)
				)
			WHERE mg.id_group IN ({array_int:normal_group_list})
			GROUP BY mg.id_group',
			array(
				'normal_group_list' => $normalGroups,
				'blank_string' => '',
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$groups[$row['id_group']]['num_members'] += $row['num_members'];
		wesql::free_result($query);
	}

	return $groups;
}

function aeva_refreshPage($next)
{
	global $context;

	// Stupid IE doesn't refresh correctly to an URL with semicolons in it...
	if (we::is('ie'))
		$next = str_replace(';', '&', $next);
	$context['header'] .= '
	<meta http-equiv="refresh" content="1; url=' . $next . '">';
}
