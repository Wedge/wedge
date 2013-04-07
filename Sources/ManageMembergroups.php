<?php
/**
 * Wedge
 *
 * General configuration of membergroups, from name to rank images to post counts if appropriate.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/* This file is concerned with anything in the Manage Membergroups screen.

	void ModifyMembergroups()
		- entrance point of the 'Manage Membergroups' center.
		- called by ?action=admin;area=membergroups.
		- loads the ManageMembergroups template.
		- loads the MangeMembers language file.
		- requires the manage_membergroups or the admin_forum permission.
		- calls a function based on the given subaction.
		- defaults to sub action 'index' or without manage_membergroup
		  permissions to 'settings'.

	void MembergroupIndex()
		- shows an overview of the current membergroups.
		- called by ?action=admin;area=membergroups.
		- requires the manage_membergroups permission.
		- uses the main ManageMembergroups template.
		- splits the membergroups in regular ones and post count based groups.
		- also counts the number of members part of each membergroup.

	void AddMembergroup()
		- allows to add a membergroup and set some initial properties.
		- called by ?action=admin;area=membergroups;sa=add.
		- requires the manage_membergroups permission.
		- uses the new_group block of ManageMembergroups.
		- allows to use a predefined permission profile or copy one from
		  another group.
		- redirects to action=admin;area=membergroups;sa=edit;group=x.

	void DeleteMembergroup()
		- deletes a membergroup by URL.
		- called by ?action=admin;area=membergroups;sa=delete;group=x;session_var=y.
		- requires the manage_membergroups permission.
		- redirects to ?action=admin;area=membergroups.

	void EditMembergroup()
		- screen to edit a specific membergroup.
		- called by ?action=admin;area=membergroups;sa=edit;group=x.
		- requires the manage_membergroups permission.
		- uses the edit_group block of ManageMembergroups.
		- also handles the delete button of the edit form.
		- redirects to ?action=admin;area=membergroups.

	void ModifyMembergroupSettings()
		- set some general membergroup settings and permissions.
		- called by ?action=admin;area=membergroups;sa=settings
		- requires the admin_forum permission (and manage_permissions for
		  changing permissions)
		- uses membergroup_settings block of ManageMembergroups.
		- redirects to itself.
*/

// The entrance point for all 'Manage Membergroup' actions.
function ModifyMembergroups()
{
	global $context, $txt;

	$subActions = array(
		'add' => array('AddMembergroup', 'manage_membergroups'),
		'delete' => array('DeleteMembergroup', 'manage_membergroups'),
		'edit' => array('EditMembergroup', 'manage_membergroups'),
		'index' => array('MembergroupIndex', 'manage_membergroups'),
		'members' => array('MembergroupMembers', 'manage_membergroups', 'Groups'),
		'settings' => array('ModifyMembergroupSettings', 'admin_forum'),
	);

	// Default to sub action 'index' or 'settings' depending on permissions.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('manage_membergroups') ? 'index' : 'settings');

	// Is it elsewhere?
	if (isset($subActions[$_REQUEST['sa']][2]))
		loadSource($subActions[$_REQUEST['sa']][2]);

	// Do the permission check, you might not be allowed her.
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	// Language and template stuff, the usual.
	loadLanguage('ManageMembers');
	loadTemplate('ManageMembergroups');

	// Setup the admin tabs.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['membergroups_title'],
		'help' => 'membergroups',
		'description' => $txt['membergroups_description'],
	);

	// Call the right function.
	$subActions[$_REQUEST['sa']][0]();
}

// An overview of the current membergroups.
function MembergroupIndex()
{
	global $txt, $context, $theme;

	$context['page_title'] = $txt['membergroups_title'];

	// The first list shows the regular membergroups.
	$listOptions = array(
		'id' => 'regular_membergroups_list',
		'title' => $txt['membergroups_regular'],
		'base_href' => '<URL>?action=admin;area=membergroups' . (isset($_REQUEST['sort2']) ? ';sort2=' . urlencode($_REQUEST['sort2']) : ''),
		'default_sort_col' => 'name',
		'get_items' => array(
			'file' => 'Subs-Membergroups',
			'function' => 'list_getMembergroups',
			'params' => array(
				'regular',
			),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['membergroups_name'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						// Since the moderator group has no explicit members, no link is needed.
						if ($rowData[\'id_group\'] == 3)
							$group_name = $rowData[\'group_name\'];
						else
							$group_name = sprintf(\'<a href="<URL>?action=admin;area=membergroups;sa=members;group=%1$d" class="group%1$s">%2$s</a>\', $rowData[\'id_group\'], $rowData[\'group_name\']);

						// Add a help option for moderator and administrator.
						if ($rowData[\'id_group\'] == 1)
							$group_name .= \' (<a href="<URL>?action=help;in=membergroup_administrator" onclick="return reqWin(this);">?</a>)\';
						elseif ($rowData[\'id_group\'] == 3)
							$group_name .= \' (<a href="<URL>?action=help;in=membergroup_moderator" onclick="return reqWin(this);">?</a>)\';

						return $group_name;
					'),
				),
				'sort' => array(
					'default' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, group_name',
					'reverse' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, group_name DESC',
				),
			),
			'stars' => array(
				'header' => array(
					'value' => $txt['membergroups_stars'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $theme;

						$stars = explode(\'#\', $rowData[\'stars\']);

						// In case no stars are setup, return with nothing
						if (empty($stars[0]) || empty($stars[1]))
							return \'\';

						// Otherwise repeat the image a given number of times.
						else
						{
							$image = sprintf(\'<img src="%1$s/%2$s">\', $theme[\'images_url\'], $stars[1]);
							return str_repeat($image, $stars[0]);
						}
					'),

				),
				'sort' => array(
					'default' => 'stars',
					'reverse' => 'stars DESC',
				)
			),
			'members' => array(
				'header' => array(
					'value' => $txt['membergroups_members_top'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						// No explicit members for the moderator group.
						return $rowData[\'id_group\'] == 3 ? $txt[\'membergroups_guests_na\'] : $rowData[\'num_members\'];
					'),
					'style' => 'text-align: center',
				),
				'sort' => array(
					'default' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, 1',
					'reverse' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, 1 DESC',
				),
			),
			'modify' => array(
				'header' => array(
					'value' => $txt['modify'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
						'params' => array(
							'id_group' => false,
						),
					),
					'style' => 'text-align: center',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<form action="<URL>?action=admin;area=membergroups;sa=add;generalgroup" method="post"><input type="submit" class="new" value="' . $txt['membergroups_add_group'] . '"></form>',
			),
		),
	);

	loadSource('Subs-List');
	createList($listOptions);

	// The second list shows the post count based groups.
	$listOptions = array(
		'id' => 'post_count_membergroups_list',
		'title' => $txt['membergroups_post'],
		'base_href' => '<URL>?action=admin;area=membergroups' . (isset($_REQUEST['sort']) ? ';sort=' . urlencode($_REQUEST['sort']) : ''),
		'default_sort_col' => 'required_posts',
		'request_vars' => array(
			'sort' => 'sort2',
			'desc' => 'desc2',
		),
		'get_items' => array(
			'file' => 'Subs-Membergroups',
			'function' => 'list_getMembergroups',
			'params' => array(
				'post_count',
			),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['membergroups_name'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return sprintf(\'<a href="<URL>?action=moderate;area=viewgroups;sa=members;group=%1$d" class="group%1$d">%2$s</a>\', $rowData[\'id_group\'], $rowData[\'group_name\']);
					'),
				),
				'sort' => array(
					'default' => 'group_name',
					'reverse' => 'group_name DESC',
				),
			),
			'stars' => array(
				'header' => array(
					'value' => $txt['membergroups_stars'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $theme;

						$stars = explode(\'#\', $rowData[\'stars\']);

						if (empty($stars[0]) || empty($stars[1]))
							return \'\';
						else
						{
							$star_image = sprintf(\'<img src="%1$s/%2$s">\', $theme[\'images_url\'], $stars[1]);
							return str_repeat($star_image, $stars[0]);
						}
					'),
				),
				'sort' => array(
					'default' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, stars',
					'reverse' => 'CASE WHEN id_group < 4 THEN id_group ELSE 4 END, stars DESC',
				)
			),
			'members' => array(
				'header' => array(
					'value' => $txt['membergroups_members_top'],
				),
				'data' => array(
					'db' => 'num_members',
					'style' => 'text-align: center',
				),
				'sort' => array(
					'default' => '1 DESC',
					'reverse' => '1',
				),
			),
			'required_posts' => array(
				'header' => array(
					'value' => $txt['membergroups_min_posts'],
				),
				'data' => array(
					'db' => 'min_posts',
					'style' => 'text-align: center',
				),
				'sort' => array(
					'default' => 'min_posts',
					'reverse' => 'min_posts DESC',
				),
			),
			'modify' => array(
				'header' => array(
					'value' => $txt['modify'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=admin;area=membergroups;sa=edit;group=%1$d">' . $txt['membergroups_modify'] . '</a>',
						'params' => array(
							'id_group' => false,
						),
					),
					'style' => 'text-align: center',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<form action="<URL>?action=admin;area=membergroups;sa=add;postgroup" method="post"><input type="submit" class="new" value="' . $txt['membergroups_add_group'] . '"></form>',
			),
		),
	);

	createList($listOptions);
}

// Add a membergroup.
function AddMembergroup()
{
	global $context, $txt, $settings;

	// A form was submitted, we can start adding.
	if (!empty($_POST['group_name']))
	{
		checkSession();

		$postCountBasedGroup = isset($_POST['min_posts']) && (!isset($_POST['postgroup_based']) || !empty($_POST['postgroup_based']));
		$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];

		// !!! Check for members with same name too?

		$request = wesql::query('
			SELECT MAX(id_group)
			FROM {db_prefix}membergroups',
			array(
			)
		);
		list ($id_group) = wesql::fetch_row($request);
		wesql::free_result($request);
		$id_group++;

		wesql::insert('',
			'{db_prefix}membergroups',
			array(
				'id_group' => 'int', 'description' => 'string', 'group_name' => 'string-80', 'min_posts' => 'int',
				'stars' => 'string', 'online_color' => 'string', 'group_type' => 'int',
			),
			array(
				$id_group, '', $_POST['group_name'], ($postCountBasedGroup ? (int) $_POST['min_posts'] : '-1'),
				'1#rank.gif', '', $_POST['group_type'],
			),
			array('id_group')
		);

		// Update the post groups now, if this is a post group!
		if (isset($_POST['min_posts']))
		{
			// But make sure we flush the cache before we use it. (We want the cache the rest of the time for posting, just flushed here.)
			cache_put_data('updateStats:postgroups', null);
			updateStats('postgroups');
		}

		// You cannot set permissions for post groups if they are disabled.
		if ($postCountBasedGroup && empty($settings['permission_enable_postgroups']))
			$_POST['perm_type'] = '';

		if ($_POST['perm_type'] == 'predefined')
		{
			// Set default permission level.
			loadSource('ManagePermissions');
			setPermissionLevel($_POST['level'], $id_group, 'null');
		}
		// Copy or inherit the permissions!
		elseif ($_POST['perm_type'] == 'copy' || $_POST['perm_type'] == 'inherit')
		{
			$copy_id = $_POST['perm_type'] == 'copy' ? (int) $_POST['copyperm'] : (int) $_POST['inheritperm'];

			// Are you a powerful admin?
			if (!allowedTo('admin_forum'))
			{
				$request = wesql::query('
					SELECT group_type
					FROM {db_prefix}membergroups
					WHERE id_group = {int:copy_from}
					LIMIT {int:limit}',
					array(
						'copy_from' => $copy_id,
						'limit' => 1,
					)
				);
				list ($copy_type) = wesql::fetch_row($request);
				wesql::free_result($request);

				// Protected groups are, well... Protected!
				if ($copy_type == 1)
					fatal_lang_error('membergroup_does_not_exist');
			}

			// Don't allow copying of a real privileged person!
			loadSource('ManagePermissions');
			loadIllegalPermissions();

			$request = wesql::query('
				SELECT permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group = {int:copy_from}',
				array(
					'copy_from' => $copy_id,
				)
			);
			$inserts = array();
			while ($row = wesql::fetch_assoc($request))
			{
				if (empty($context['illegal_permissions']) || !in_array($row['permission'], $context['illegal_permissions']))
					$inserts[] = array($id_group, $row['permission'], $row['add_deny']);
			}
			wesql::free_result($request);

			if (!empty($inserts))
				wesql::insert('',
					'{db_prefix}permissions',
					array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
					$inserts,
					array('id_group', 'permission')
				);

			$request = wesql::query('
				SELECT id_profile, permission, add_deny
				FROM {db_prefix}board_permissions
				WHERE id_group = {int:copy_from}',
				array(
					'copy_from' => $copy_id,
				)
			);
			$inserts = array();
			while ($row = wesql::fetch_assoc($request))
				$inserts[] = array($id_group, $row['id_profile'], $row['permission'], $row['add_deny']);
			wesql::free_result($request);

			if (!empty($inserts))
				wesql::insert('',
					'{db_prefix}board_permissions',
					array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
					$inserts,
					array('id_group', 'id_profile', 'permission')
				);

			// Also get some membergroup information if we're copying and not copying from guests...
			if ($copy_id > 0 && $_POST['perm_type'] == 'copy')
			{
				$request = wesql::query('
					SELECT online_color, max_messages, stars
					FROM {db_prefix}membergroups
					WHERE id_group = {int:copy_from}
					LIMIT 1',
					array(
						'copy_from' => $copy_id,
					)
				);
				$group_info = wesql::fetch_assoc($request);
				wesql::free_result($request);

				// ...and update the new membergroup with it.
				wesql::query('
					UPDATE {db_prefix}membergroups
					SET
						online_color = {string:online_color},
						max_messages = {int:max_messages},
						stars = {string:stars}
					WHERE id_group = {int:current_group}',
					array(
						'max_messages' => $group_info['max_messages'],
						'current_group' => $id_group,
						'online_color' => $group_info['online_color'],
						'stars' => $group_info['stars'],
					)
				);
			}
			// If inheriting say so...
			elseif ($_POST['perm_type'] == 'inherit')
			{
				wesql::query('
					UPDATE {db_prefix}membergroups
					SET id_parent = {int:copy_from}
					WHERE id_group = {int:current_group}',
					array(
						'copy_from' => $copy_id,
						'current_group' => $id_group,
					)
				);
			}
		}

		// Add the board visibility.
		$board_access = array();
		if (!empty($_POST['viewboard']) && is_array($_POST['viewboard']))
		{
			foreach ($_POST['viewboard'] as $id_board => $access)
			{
				$id_board = (int) $id_board;
				if ($id_board < 0)
					continue;

				if ((empty($_POST['need_deny_perm']) && $access == 'deny') || ($access != 'deny' && $access != 'allow'))
					$access = 'disallow';

				$board_access[$id_board]['view_perm'] = $access;
			}
		}

		// If the enter rules are the same as the view rules, we do not care what $_POST has.
		if (!empty($_POST['view_enter_same']))
		{
			foreach ($board_access as $id_board => $access)
				$board_access[$id_board]['enter_perm'] = $access['view_perm'];
		}
		elseif (!empty($_POST['enterboard']))
		{
			foreach ($_POST['enterboard'] as $id_group => $access)
			{
				$id_board = (int) $id_board;
				if ($id_board < 0)
					continue;

				if ((empty($_POST['need_deny_perm']) && $access == 'deny') || ($access != 'deny' && $access != 'allow'))
					$access = 'disallow';

				$board_access[$id_board]['enter_perm'] = $access;
			}
		}

		// A bit of clean up before we insert DB rows
		$insert_rows = array();
		foreach ($board_access as $id_board => $access)
		{
			if (empty($access['view_perm']))
				unset($board_access[$id_board]);
			elseif (empty($access['enter_perm']))
				$access['enter_perm'] = $access['view_perm'];

			$insert_rows[] = array($id_board, $id_group, $access['view_perm'], $access['enter_perm']);
		}
		if (!empty($insert_rows))
			wesql::insert('replace',
				'{db_prefix}board_groups',
				array(
					'id_board' => 'int', 'id_group' => 'int', 'view_perm' => 'string', 'enter_perm' => 'string',
				),
				$insert_rows,
				array('id_board', 'id_group')
			);

		// If this is joinable then set it to show group membership in people's profiles.
		if (empty($settings['show_group_membership']) && $_POST['group_type'] > 1)
			updateSettings(array('show_group_membership' => 1));

		// Rebuild the group cache.
		updateSettings(array(
			'settings_updated' => time(),
		));

		// We did it.
		logAction('add_group', array('group' => $_POST['group_name']), 'admin');

		// Go change some more settings.
		redirectexit('action=admin;area=membergroups;sa=edit;group=' . $id_group);
	}

	// Just show the 'add membergroup' screen.
	$context['page_title'] = $txt['membergroups_new_group'];
	wetem::load('new_group');
	$context['post_group'] = isset($_REQUEST['postgroup']);
	$context['undefined_group'] = !isset($_REQUEST['postgroup']) && !isset($_REQUEST['generalgroup']);
	$context['allow_protected'] = allowedTo('admin_forum');

	$result = wesql::query('
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE (id_group > {int:moderator_group} OR id_group = {int:global_mod_group})' . (empty($settings['permission_enable_postgroups']) ? '
			AND min_posts = {int:min_posts}' : '') . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
		ORDER BY min_posts, id_group != {int:global_mod_group}, group_name',
		array(
			'moderator_group' => 3,
			'global_mod_group' => 2,
			'min_posts' => -1,
			'is_protected' => 1,
		)
	);
	$context['groups'] = array();
	while ($row = wesql::fetch_assoc($result))
		$context['groups'][] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name']
		);
	wesql::free_result($result);

	$result = wesql::query('
		SELECT b.id_board, b.name, child_level, c.name AS cat_name, c.id_cat
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}categories AS c ON (b.id_cat = c.id_cat)
		ORDER BY board_order',
		array(
		)
	);
	$context['boards'] = array();
	while ($row = wesql::fetch_assoc($result))
		$context['boards'][] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'id_cat' => $row['id_cat'],
			'cat_name' => $row['cat_name'],
			'child_level' => $row['child_level'],
			'view_perm' => 'disallow',
			'enter_perm' => 'disallow',
		);
	wesql::free_result($result);
	$context['view_enter_same'] = true;
	$context['need_deny_perm'] = false;
}

// Deleting a membergroup by URL (not implemented).
function DeleteMembergroup()
{
	checkSession('get');

	loadSource('Subs-Membergroups');
	deleteMembergroups((int) $_REQUEST['group']);

	// Go back to the membergroup index.
	redirectexit('action=admin;area=membergroups;');
}

// Editing a membergroup.
function EditMembergroup()
{
	global $context, $txt, $settings;

	$_REQUEST['group'] = isset($_REQUEST['group']) && $_REQUEST['group'] > 0 ? (int) $_REQUEST['group'] : 0;

	// Make sure this group is editable.
	if (!empty($_REQUEST['group']))
	{
		$request = wesql::query('
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}' . (allowedTo('admin_forum') ? '' : '
				AND group_type != {int:is_protected}') . '
			LIMIT {int:limit}',
			array(
				'current_group' => $_REQUEST['group'],
				'is_protected' => 1,
				'limit' => 1,
			)
		);
		list ($_REQUEST['group']) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	// Now, do we have a valid id?
	if (empty($_REQUEST['group']))
		fatal_lang_error('membergroup_does_not_exist', false);

	// The delete this membergroup button was pressed.
	if (isset($_POST['delete']))
	{
		checkSession();

		loadSource('Subs-Membergroups');
		deleteMembergroups($_REQUEST['group']);

		// There are several things that might need dumping at this point.
		cache_put_data('member-groups', null);
		clean_cache('css');

		redirectexit('action=admin;area=membergroups;');
	}
	// A form was submitted with the new membergroup settings.
	elseif (isset($_POST['save']))
	{
		// Validate the session.
		checkSession();

		$_REQUEST['group'] = (int) $_REQUEST['group'];

		// Can they really inherit from this group?
		if (isset($_POST['group_inherit']) && $_POST['group_inherit'] != -2 && !allowedTo('admin_forum'))
		{
			$request = wesql::query('
				SELECT group_type
				FROM {db_prefix}membergroups
				WHERE id_group = {int:inherit_from}
				LIMIT {int:limit}',
				array(
					'inherit_from' => $_POST['group_inherit'],
					'limit' => 1,
				)
			);
			list ($inherit_type) = wesql::fetch_row($request);
			wesql::free_result($request);
		}

		// Set variables to their proper value.
		$_POST['max_messages'] = isset($_POST['max_messages']) ? (int) $_POST['max_messages'] : 0;
		$_POST['min_posts'] = isset($_POST['min_posts'], $_POST['group_type']) && $_POST['group_type'] == -1 && $_REQUEST['group'] > 3 ? abs($_POST['min_posts']) : ($_REQUEST['group'] == 4 ? 0 : -1);
		$_POST['stars'] = (empty($_POST['star_count']) || $_POST['star_count'] < 0) ? '' : min((int) $_POST['star_count'], 99) . '#' . $_POST['star_image'];
		$_POST['group_desc'] = isset($_POST['group_desc']) && ($_REQUEST['group'] == 1 || (isset($_POST['group_type']) && $_POST['group_type'] != -1)) ? trim($_POST['group_desc']) : '';
		$_POST['group_type'] = !isset($_POST['group_type']) || $_POST['group_type'] < 0 || $_POST['group_type'] > 3 || ($_POST['group_type'] == 1 && !allowedTo('admin_forum')) ? 0 : (int) $_POST['group_type'];
		$_POST['group_hidden'] = empty($_POST['group_hidden']) || $_POST['min_posts'] != -1 || $_REQUEST['group'] == 3 ? 0 : (int) $_POST['group_hidden'];
		$_POST['group_inherit'] = $_REQUEST['group'] > 1 && $_REQUEST['group'] != 3 && (empty($inherit_type) || $inherit_type != 1) ? (int) $_POST['group_inherit'] : -2;

		// !!! Don't set online_color for the Moderators group?

		// Do the update of the membergroup settings.
		wesql::query('
			UPDATE {db_prefix}membergroups
			SET group_name = {string:group_name}, online_color = {string:online_color},
				max_messages = {int:max_messages}, min_posts = {int:min_posts}, stars = {string:stars},
				description = {string:group_desc}, group_type = {int:group_type}, hidden = {int:group_hidden},
				id_parent = {int:group_inherit}
			WHERE id_group = {int:current_group}',
			array(
				'max_messages' => $_POST['max_messages'],
				'min_posts' => $_POST['min_posts'],
				'group_type' => $_POST['group_type'],
				'group_hidden' => $_POST['group_hidden'],
				'group_inherit' => $_POST['group_inherit'],
				'current_group' => $_REQUEST['group'],
				'group_name' => $_POST['group_name'],
				'online_color' => $_POST['online_color'],
				'stars' => $_POST['stars'],
				'group_desc' => $_POST['group_desc'],
			)
		);

		// Time to update the boards this membergroup has access to.
		if ($_REQUEST['group'] == 2 || $_REQUEST['group'] > 3)
		{
			// Prune the old permissions.
			wesql::query('
				DELETE FROM {db_prefix}board_groups
				WHERE id_group = {int:group}',
				array(
					'group' => $_REQUEST['group'],
				)
			);

			// Add the real visibility.
			$board_access = array();
			if (!empty($_POST['viewboard']) && is_array($_POST['viewboard']))
			{
				foreach ($_POST['viewboard'] as $id_board => $access)
				{
					$id_board = (int) $id_board;
					if ($id_board < 0)
						continue;

					if ((empty($_POST['need_deny_perm']) && $access == 'deny') || ($access != 'deny' && $access != 'allow'))
						$access = 'disallow';

					$board_access[$id_board]['view_perm'] = $access;
				}
			}

			// If the enter rules are the same as the view rules, we do not care what $_POST has.
			if (!empty($_POST['view_enter_same']))
			{
				foreach ($board_access as $id_board => $access)
					$board_access[$id_board]['enter_perm'] = $access['view_perm'];
			}
			elseif (!empty($_POST['enterboard']))
			{
				foreach ($_POST['enterboard'] as $id_group => $access)
				{
					$id_board = (int) $id_board;
					if ($id_board < 0)
						continue;

					if ((empty($_POST['need_deny_perm']) && $access == 'deny') || ($access != 'deny' && $access != 'allow'))
						$access = 'disallow';

					$board_access[$id_board]['enter_perm'] = $access;
				}
			}

			// A bit of clean up before we insert DB rows
			$insert_rows = array();
			foreach ($board_access as $id_board => $access)
			{
				if (empty($access['view_perm']))
					unset($board_access[$id_board]);
				elseif (empty($access['enter_perm']))
					$access['enter_perm'] = $access['view_perm'];

				$insert_rows[] = array($id_board, $_REQUEST['group'], $access['view_perm'], $access['enter_perm']);
			}
			if (!empty($insert_rows))
				wesql::insert('replace',
					'{db_prefix}board_groups',
					array(
						'id_board' => 'int', 'id_group' => 'int', 'view_perm' => 'string', 'enter_perm' => 'string',
					),
					$insert_rows,
					array('id_board', 'id_group')
				);

			$_POST['boardaccess'] = empty($_POST['boardaccess']) || !is_array($_POST['boardaccess']) ? array() : $_POST['boardaccess'];
			foreach ($_POST['boardaccess'] as $key => $value)
				$_POST['boardaccess'][$key] = (int) $value;

			// Find all board this group is in, but shouldn't be in.
			$request = wesql::query('
				SELECT id_board, member_groups
				FROM {db_prefix}boards
				WHERE FIND_IN_SET({string:current_group}, member_groups) != 0' . (empty($_POST['boardaccess']) ? '' : '
					AND id_board NOT IN ({array_int:board_access_list})'),
				array(
					'current_group' => (int) $_REQUEST['group'],
					'board_access_list' => $_POST['boardaccess'],
				)
			);
			while ($row = wesql::fetch_assoc($request))
				wesql::query('
					UPDATE {db_prefix}boards
					SET member_groups = {string:member_group_access}
					WHERE id_board = {int:current_board}',
					array(
						'current_board' => $row['id_board'],
						'member_group_access' => implode(',', array_diff(explode(',', $row['member_groups']), array($_REQUEST['group']))),
					)
				);
			wesql::free_result($request);

			// Add the membergroup to all boards that hadn't been set yet.
			if (!empty($_POST['boardaccess']))
				wesql::query('
					UPDATE {db_prefix}boards
					SET member_groups = CASE WHEN member_groups = {string:blank_string} THEN {string:group_id_string} ELSE CONCAT(member_groups, {string:comma_group}) END
					WHERE id_board IN ({array_int:board_list})
						AND FIND_IN_SET({int:current_group}, member_groups) = 0',
					array(
						'board_list' => $_POST['boardaccess'],
						'blank_string' => '',
						'current_group' => (int) $_REQUEST['group'],
						'group_id_string' => (string) (int) $_REQUEST['group'],
						'comma_group' => ',' . $_REQUEST['group'],
					)
				);
		}

		// Remove everyone from this group!
		if ($_POST['min_posts'] != -1)
		{
			wesql::query('
				UPDATE {db_prefix}members
				SET id_group = {int:regular_member}
				WHERE id_group = {int:current_group}',
				array(
					'regular_member' => 0,
					'current_group' => (int) $_REQUEST['group'],
				)
			);

			$request = wesql::query('
				SELECT id_member, additional_groups
				FROM {db_prefix}members
				WHERE FIND_IN_SET({string:current_group}, additional_groups) != 0',
				array(
					'current_group' => (int) $_REQUEST['group'],
				)
			);
			$updates = array();
			while ($row = wesql::fetch_assoc($request))
				$updates[$row['additional_groups']][] = $row['id_member'];
			wesql::free_result($request);

			foreach ($updates as $additional_groups => $memberArray)
				updateMemberData($memberArray, array('additional_groups' => implode(',', array_diff(explode(',', $additional_groups), array((int) $_REQUEST['group'])))));
		}
		elseif ($_REQUEST['group'] != 3)
		{
			// Making it a hidden group? If so remove everyone with it as primary group (Actually, just make them additional).
			if ($_POST['group_hidden'] == 2)
			{
				$request = wesql::query('
					SELECT id_member, additional_groups
					FROM {db_prefix}members
					WHERE id_group = {int:current_group}
						AND FIND_IN_SET({int:current_group}, additional_groups) = 0',
					array(
						'current_group' => (int) $_REQUEST['group'],
					)
				);
				$updates = array();
				while ($row = wesql::fetch_assoc($request))
					$updates[$row['additional_groups']][] = $row['id_member'];
				wesql::free_result($request);

				foreach ($updates as $additional_groups => $memberArray)
					updateMemberData($memberArray, array('additional_groups' => implode(',', array_merge(explode(',', $additional_groups), array((int) $_REQUEST['group'])))));

				wesql::query('
					UPDATE {db_prefix}members
					SET id_group = {int:regular_member}
					WHERE id_group = {int:current_group}',
					array(
						'regular_member' => 0,
						'current_group' => $_REQUEST['group'],
					)
				);
			}

			// Either way, let's check our "show group membership" setting is correct.
			$request = wesql::query('
				SELECT COUNT(*)
				FROM {db_prefix}membergroups
				WHERE group_type > {int:non_joinable}',
				array(
					'non_joinable' => 1,
				)
			);
			list ($have_joinable) = wesql::fetch_row($request);
			wesql::free_result($request);

			// Do we need to update the setting?
			if ((empty($settings['show_group_membership']) && $have_joinable) || (!empty($settings['show_group_membership']) && !$have_joinable))
				updateSettings(array('show_group_membership' => $have_joinable ? 1 : 0));
		}

		// Do we need to set inherited permissions?
		if ($_POST['group_inherit'] != -2 && $_POST['group_inherit'] != $_POST['old_inherit'])
		{
			loadSource('ManagePermissions');
			updateChildPermissions($_POST['group_inherit']);
		}

		// Finally, moderators!
		$moderator_string = isset($_POST['group_moderators']) ? trim($_POST['group_moderators']) : '';
		wesql::query('
			DELETE FROM {db_prefix}group_moderators
			WHERE id_group = {int:current_group}',
			array(
				'current_group' => $_REQUEST['group'],
			)
		);
		if ((!empty($moderator_string) || !empty($_POST['moderator_list'])) && $_POST['min_posts'] == -1 && $_REQUEST['group'] != 3)
		{
			// Get all the usernames from the string
			if (!empty($moderator_string))
			{
				$moderator_string = strtr(preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', htmlspecialchars($moderator_string), ENT_QUOTES), array('&quot;' => '"'));
				preg_match_all('~"([^"]+)"~', $moderator_string, $matches);
				$moderators = array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $moderator_string)));
				for ($k = 0, $n = count($moderators); $k < $n; $k++)
				{
					$moderators[$k] = trim($moderators[$k]);

					if (strlen($moderators[$k]) == 0)
						unset($moderators[$k]);
				}

				// Find all the id_member's for the member_name's in the list.
				$group_moderators = array();
				if (!empty($moderators))
				{
					$request = wesql::query('
						SELECT id_member
						FROM {db_prefix}members
						WHERE member_name IN ({array_string:moderators}) OR real_name IN ({array_string:moderators})
						LIMIT ' . count($moderators),
						array(
							'moderators' => $moderators,
						)
					);
					while ($row = wesql::fetch_assoc($request))
						$group_moderators[] = $row['id_member'];
					wesql::free_result($request);
				}
			}
			else
			{
				$moderators = array();
				foreach ($_POST['moderator_list'] as $moderator)
					$moderators[] = (int) $moderator;

				$group_moderators = array();
				if (!empty($moderators))
				{
					$request = wesql::query('
						SELECT id_member
						FROM {db_prefix}members
						WHERE id_member IN ({array_int:moderators})
						LIMIT {int:num_moderators}',
						array(
							'moderators' => $moderators,
							'num_moderators' => count($moderators),
						)
					);
					while ($row = wesql::fetch_assoc($request))
						$group_moderators[] = $row['id_member'];
					wesql::free_result($request);
				}
			}

			// Found some?
			if (!empty($group_moderators))
			{
				$mod_insert = array();
				foreach ($group_moderators as $moderator)
					$mod_insert[] = array($_REQUEST['group'], $moderator);

				wesql::insert('',
					'{db_prefix}group_moderators',
					array('id_group' => 'int', 'id_member' => 'int'),
					$mod_insert,
					array('id_group', 'id_member')
				);
			}
		}

		// There are several things that might need dumping at this point.
		cache_put_data('member-groups', null);
		clean_cache('css');

		// There might have been some post group changes.
		updateStats('postgroups');
		// We've definetely changed some group stuff.
		updateSettings(array(
			'settings_updated' => time(),
		));

		// Log the edit.
		logAction('edited_group', array('group' => $_POST['group_name']), 'admin');

		redirectexit('action=admin;area=membergroups');
	}

	// Fetch the current group information.
	$request = wesql::query('
		SELECT group_name, description, min_posts, online_color, max_messages, stars, group_type, hidden, id_parent
		FROM {db_prefix}membergroups
		WHERE id_group = {int:current_group}
		LIMIT 1',
		array(
			'current_group' => (int) $_REQUEST['group'],
		)
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('membergroup_does_not_exist', false);
	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	$row['stars'] = explode('#', $row['stars']);

	$context['group'] = array(
		'id' => $_REQUEST['group'],
		'name' => $row['group_name'],
		'description' => htmlspecialchars($row['description']),
		'editable_name' => htmlspecialchars($row['group_name']),
		'color' => $row['online_color'],
		'min_posts' => $row['min_posts'],
		'max_messages' => $row['max_messages'],
		'star_count' => (int) $row['stars'][0],
		'star_image' => isset($row['stars'][1]) ? $row['stars'][1] : '',
		'is_post_group' => $row['min_posts'] != -1,
		'type' => $row['min_posts'] != -1 ? 0 : $row['group_type'],
		'hidden' => $row['min_posts'] == -1 ? $row['hidden'] : 0,
		'inherited_from' => $row['id_parent'],
		'allow_post_group' => $_REQUEST['group'] == 2 || $_REQUEST['group'] > 4,
		'allow_delete' => $_REQUEST['group'] == 2 || $_REQUEST['group'] > 4,
		'allow_protected' => allowedTo('admin_forum'),
	);

	// Get any moderators for this group
	$request = wesql::query('
		SELECT mem.id_member, mem.real_name
		FROM {db_prefix}group_moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
		WHERE mods.id_group = {int:current_group}',
		array(
			'current_group' => $_REQUEST['group'],
		)
	);
	$context['group']['moderators'] = array();
	while ($row = wesql::fetch_assoc($request))
		$context['group']['moderators'][$row['id_member']] = $row['real_name'];
	wesql::free_result($request);

	$context['group']['moderator_list'] = empty($context['group']['moderators']) ? '' : '&quot;' . implode('&quot;, &quot;', $context['group']['moderators']) . '&quot;';

	if (!empty($context['group']['moderators']))
		list ($context['group']['last_moderator_id']) = array_slice(array_keys($context['group']['moderators']), -1);

	// Get a list of boards this membergroup is allowed to see.
	$context['boards'] = array();
	if ($_REQUEST['group'] == 2 || $_REQUEST['group'] > 3)
	{
		$context['view_enter_same'] = true;
		$context['need_deny_perm'] = false;

		$result = wesql::query('
			SELECT b.id_board, b.name, child_level, IFNULL(view_perm, {literal:disallow}) AS view_perm, IFNULL(enter_perm, {literal:disallow}) AS enter_perm,
				c.name AS cat_name, c.id_cat
			FROM {db_prefix}boards AS b
				LEFT JOIN {db_prefix}board_groups AS bg ON (b.id_board = bg.id_board AND bg.id_group = {int:current_group})
				INNER JOIN {db_prefix}categories AS c ON (b.id_cat = c.id_cat)
			ORDER BY board_order',
			array(
				'current_group' => (int) $_REQUEST['group'],
			)
		);
		while ($row = wesql::fetch_assoc($result))
		{
			$context['boards'][] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'child_level' => $row['child_level'],
				'view_perm' => $row['view_perm'],
				'enter_perm' => $row['enter_perm'],
				'id_cat' => $row['id_cat'],
				'cat_name' => $row['cat_name'],
			);
			if ($row['view_perm'] != $row['enter_perm'])
				$context['view_enter_same'] = false;
			if ($row['view_perm'] == 'deny' || $row['enter_perm'] == 'deny')
				$context['need_deny_perm'] = true;
		}

		wesql::free_result($result);
	}

	// Finally, get all the groups this could be inherited off.
	$request = wesql::query('
		SELECT id_group, group_name
		FROM {db_prefix}membergroups
		WHERE id_group != {int:current_group}' .
			(empty($settings['permission_enable_postgroups']) ? '
			AND min_posts = {int:min_posts}' : '') . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
			AND id_group NOT IN (1, 3)
			AND id_parent = {int:not_inherited}',
		array(
			'current_group' => (int) $_REQUEST['group'],
			'min_posts' => -1,
			'not_inherited' => -2,
			'is_protected' => 1,
		)
	);
	$context['inheritable_groups'] = array();
	while ($row = wesql::fetch_assoc($request))
		$context['inheritable_groups'][$row['id_group']] = $row['group_name'];
	wesql::free_result($request);

	wetem::load('edit_group');
	$context['page_title'] = $txt['membergroups_edit_group'];
}

// Set general membergroup settings.
function ModifyMembergroupSettings($return_config = false)
{
	global $context, $settings, $txt, $theme;

	// !! Show we add a hook for plugins to add to these options...?
	$which_groups = array(
		'none' => $txt['group_show_none'],
		'all' => $txt['group_show_all'],
		'normal' => $txt['group_show_normal'],
		'post' => $txt['group_show_post'],
		'cond' => $txt['group_show_cond']
	);

	$config_vars = array(
		array('permissions', 'manage_membergroups', 'exclude' => array(-1, 0)),
		array('select', 'group_text_show', $which_groups),
		array('check', 'show_group_key'),
		array('title', 'membergroup_badges'),
		array('desc', 'membergroup_badges_desc'),
		array('callback', 'badge_order'),
	);

	if ($return_config)
		return $config_vars;

	// Needed for the settings functions.
	loadSource('ManageServer');
	wetem::load('show_settings');
	$context['page_title'] = $txt['membergroups_settings'];

	// Doing badges is complicated.
	add_jquery_ui();
	$context['badges'] = array();
	$request = wesql::query('
		SELECT id_group, group_name, min_posts, online_color, show_when, display_order, stars
		FROM {db_prefix}membergroups
		ORDER BY display_order');
	while ($row = wesql::fetch_assoc($request))
	{
		$stars = explode('#', $row['stars']);
		if (!empty($stars[0]) && !empty($stars[1]))
			$row['badge'] = str_repeat('<img src="' . str_replace('$language', we::$user['language'], $theme['images_url'] . '/' . $stars[1]) . '">', $stars[0]);
		$context['badges'][$row['id_group']] = $row;
	}
	wesql::free_result($request);

	if (isset($_REQUEST['save']))
	{
		checkSession();

		// Validate the group select box.
		$_POST['group_text_show'] = isset($_POST['group_text_show'], $which_groups[$_POST['group_text_show']]) ? $_POST['group_text_show'] : 'cond';

		// Yeppers, saving this...
		saveDBSettings($config_vars);

		// Now we need to handle the groups. We already got the current groups, so this should be fairly simple.
		$collected = 0;
		if (!empty($_POST['group']) && is_array($_POST['group']))
		{
			foreach ($_POST['group'] as $group)
			{
				$collected++;
				$group = (int) $group;
				if (isset($context['badges'][$group]))
					$context['badges'][$group]['new_order'] = $collected;
			}
			// Did we get all the groups?
			if ($collected < count($context['badges']))
				foreach ($context['badges'] as $k => $v)
					if (!isset($v['new_order']))
						$context['badges'][$k]['new_order'] = $collected++;
		}
		if (!empty($_POST['show_when']) && is_array($_POST['show_when']))
			foreach ($_POST['show_when'] as $k => $v)
				if (isset($context['badges'][$k]))
					$context['badges'][$k]['new_show'] = (int) $v;

		foreach ($context['badges'] as $gid => $details)
		{
			$array = array();
			if (isset($details['new_order']) && $details['display_order'] != $details['new_order'])
				$array['display_order'] = $details['new_order'];
			if (isset($details['new_show']) && $details['show_when'] != $details['new_show'])
				$array['show_when'] = $details['new_show'];

			if (!empty($array))
			{
				$clauses = array();
				foreach ($array as $k => $v)
					$clauses[] = $k . ' = {int:' . $k . '}';

				$array['id_group'] = $gid;
				wesql::query('
					UPDATE {db_prefix}membergroups
					SET ' . implode(', ', $clauses) . '
					WHERE id_group = {int:id_group}',
					$array);
			}
		}
		cache_put_data('member-badges', null);

		redirectexit('action=admin;area=membergroups;sa=settings');
	}

	// Some simple context.
	$context['post_url'] = '<URL>?action=admin;area=membergroups;save;sa=settings';
	$context['settings_title'] = $txt['membergroups_settings'];

	prepareDBSettingContext($config_vars);
}
