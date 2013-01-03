<?php
/**
 * Wedge
 *
 * Handles all aspects of managing permissions.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	ManagePermissions handles all possible permission stuff. The following
	functions are used:

	void ModifyPermissions()
		- calls the right function based on the given subaction.
		- checks the permissions, based on the sub-action.
		- called by ?action=managepermissions.
		- loads the ManagePermissions language file.

	void PermissionIndex()
		- sets up the permissions by membergroup index page.
		- called by ?action=managepermissions
		- uses the permission_index template of the ManageBoards template.
		- loads the ManagePermissions language and template.
		- creates an array of all the groups with the number of members and permissions.

	void SetQuickGroups()
		- handles permission modification actions from the upper part of the
		  permission manager index.
		// !!!

	void ModifyMembergroup()
		// !!!

	void ModifyMembergroup2()
		// !!!

	void GeneralPermissionSettings()
		- a screen to set some general settings for permissions.

	void setPermissionLevel(string level, int group, int profile = 'null')
		- internal function to modify permissions to a pre-defined profile.
		// !!!

	void loadAllPermissions()
		- internal function to load permissions into $context['permissions'].
		// !!!

	void loadPermissionProfiles()
		// !!!

	void EditPermissionProfiles()
		// !!!

	void init_inline_permissions(array permissions)
		- internal function to initialise the inline permission settings.
		- loads the ManagePermissions language and template.
		- loads a context variables for each permission.
		- used by several settings screens to set specific permissions.

	void theme_inline_permissions(string permission)
		- function called by templates to show a list of permissions settings.
		- calls the template function template_inline_permissions().

	save_inline_permissions(array permissions)
		- general function to save the inline permissions sent by a form.
		- does no session check.

	void updateChildPermissions(array parent, int profile = null)
		// !!!

	void loadIllegalPermissions()
		// !!!

	void loadIllegalGuestPermissions()
		- loads the permissions that can not be given to guests.
		- stores the permissions in $context['non_guest_permissions'].

	void ModifyPostModeration()
		// !!!
*/

function ModifyPermissions()
{
	global $txt, $scripturl, $context;

	loadLanguage(array('ManagePermissions', 'ManageMembers'));
	loadTemplate('ManagePermissions');

	// Format: 'sub-action' => array('function_to_call', 'permission_needed'),
	$subActions = array(
		'board' => array('PermissionByBoard', 'manage_permissions'),
		'index' => array('PermissionIndex', 'manage_permissions'),
		'modify' => array('ModifyMembergroup', 'manage_permissions'),
		'modify2' => array('ModifyMembergroup2', 'manage_permissions'),
		'quick' => array('SetQuickGroups', 'manage_permissions'),
		'quickboard' => array('SetQuickBoards', 'manage_permissions'),
		'profiles' => array('EditPermissionProfiles', 'manage_permissions'),
		'settings' => array('GeneralPermissionSettings', 'admin_forum'),
	);

	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('manage_permissions') ? 'index' : 'settings');
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['permissions_title'],
		'help' => 'permissions',
		'tabs' => array(
			'index' => array(
				'description' => $txt['permissions_groups'],
			),
			'board' => array(
				'description' => $txt['permission_by_board_desc'],
			),
			'profiles' => array(
				'description' => $txt['permissions_profiles_desc'],
			),
			'settings' => array(
				'description' => $txt['permission_settings_desc'],
			),
		),
	);

	$subActions[$_REQUEST['sa']][0]();
}

function PermissionIndex()
{
	global $txt, $scripturl, $context, $theme, $settings;

	$context['page_title'] = $txt['permissions_title'];

	// Load all the permissions. We'll need them in the template.
	loadAllPermissions();

	// Also load profiles, we may want to reset.
	loadPermissionProfiles();

	// Are we going to show the advanced options?
	$context['show_advanced_options'] = empty($context['admin_preferences']['app']);

	// Determine the number of ungrouped members.
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}members
		WHERE id_group = {int:regular_group}',
		array(
			'regular_group' => 0,
		)
	);
	list ($num_members) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Fill the context variable with 'Guests' and 'Regular Members'.
	$context['groups'] = array(
		-1 => array(
			'id' => -1,
			'name' => $txt['membergroups_guests'],
			'num_members' => $txt['membergroups_guests_na'],
			'allow_delete' => false,
			'allow_modify' => true,
			'can_search' => false,
			'href' => '',
			'link' => '',
			'is_post_group' => false,
			'color' => '',
			'stars' => '',
			'children' => array(),
			'num_permissions' => array(
				'allowed' => 0,
				// Can't deny guest permissions!
				'denied' => '(' . $txt['permissions_none'] . ')'
			),
			'access' => false
		),
		0 => array(
			'id' => 0,
			'name' => $txt['membergroups_members'],
			'num_members' => $num_members,
			'allow_delete' => false,
			'allow_modify' => true,
			'can_search' => false,
			'href' => $scripturl . '?action=moderate;area=viewgroups;sa=members;group=0',
			'is_post_group' => false,
			'color' => '',
			'stars' => '',
			'children' => array(),
			'num_permissions' => array(
				'allowed' => 0,
				'denied' => 0
			),
			'access' => false
		),
	);

	$postGroups = array();
	$normalGroups = array();

	// Query the database defined membergroups.
	$query = wesql::query('
		SELECT id_group, id_parent, group_name, min_posts, online_color, stars
		FROM {db_prefix}membergroups' . (empty($settings['permission_enable_postgroups']) ? '
		WHERE min_posts = {int:min_posts}' : '') . '
		ORDER BY id_parent = {int:not_inherited} DESC, min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'min_posts' => -1,
			'not_inherited' => -2,
			'newbie_group' => 4,
		)
	);
	while ($row = wesql::fetch_assoc($query))
	{
		// If it's inherited, just add it as a child.
		if ($row['id_parent'] != -2)
		{
			if (isset($context['groups'][$row['id_parent']]))
				$context['groups'][$row['id_parent']]['children'][$row['id_group']] = $row['group_name'];
			continue;
		}

		$row['stars'] = explode('#', $row['stars']);
		$context['groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'num_members' => $row['id_group'] != 3 ? 0 : $txt['membergroups_guests_na'],
			'allow_delete' => $row['id_group'] > 4,
			'allow_modify' => $row['id_group'] > 1,
			'can_search' => $row['id_group'] != 3,
			'href' => $scripturl . '?action=moderate;area=viewgroups;sa=members;group=' . $row['id_group'],
			'is_post_group' => $row['min_posts'] != -1,
			'color' => empty($row['online_color']) ? '' : $row['online_color'],
			'stars' => !empty($row['stars'][0]) && !empty($row['stars'][1]) ? str_repeat('<img src="' . $theme['images_url'] . '/' . $row['stars'][1] . '">', $row['stars'][0]) : '',
			'children' => array(),
			'num_permissions' => array(
				'allowed' => $row['id_group'] == 1 ? '(' . $txt['permissions_all'] . ')' : 0,
				'denied' => $row['id_group'] == 1 ? '(' . $txt['permissions_none'] . ')' : 0
			),
			'access' => false,
		);

		if ($row['min_posts'] == -1)
			$normalGroups[$row['id_group']] = $row['id_group'];
		else
			$postGroups[$row['id_group']] = $row['id_group'];
	}
	wesql::free_result($query);

	// Get the number of members in this post group.
	if (!empty($postGroups))
	{
		$query = wesql::query('
			SELECT id_post_group AS id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_post_group IN ({array_int:post_group_list})
			GROUP BY id_post_group',
			array(
				'post_group_list' => $postGroups,
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		wesql::free_result($query);
	}

	if (!empty($normalGroups))
	{
		// First, the easy one!
		$query = wesql::query('
			SELECT id_group, COUNT(*) AS num_members
			FROM {db_prefix}members
			WHERE id_group IN ({array_int:normal_group_list})
			GROUP BY id_group',
			array(
				'normal_group_list' => $normalGroups,
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		wesql::free_result($query);

		// This one is slower, but it's okay... careful not to count twice!
		$query = wesql::query('
			SELECT mg.id_group, COUNT(*) AS num_members
			FROM {db_prefix}membergroups AS mg
				INNER JOIN {db_prefix}members AS mem ON (mem.additional_groups != {string:blank_string}
					AND mem.id_group != mg.id_group
					AND FIND_IN_SET(mg.id_group, mem.additional_groups) != 0)
			WHERE mg.id_group IN ({array_int:normal_group_list})
			GROUP BY mg.id_group',
			array(
				'normal_group_list' => $normalGroups,
				'blank_string' => '',
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['groups'][$row['id_group']]['num_members'] += $row['num_members'];
		wesql::free_result($query);
	}

	foreach ($context['groups'] as $id => $data)
	{
		if ($data['href'] != '')
			$context['groups'][$id]['link'] = '<a href="' . $data['href'] . '">' . $data['num_members'] . '</a>';
	}

	if (empty($_REQUEST['pid']))
	{
		$request = wesql::query('
			SELECT id_group, COUNT(*) AS num_permissions, add_deny
			FROM {db_prefix}permissions
			' . (empty($context['hidden_permissions']) ? '' : ' WHERE permission NOT IN ({array_string:hidden_permissions})') . '
			GROUP BY id_group, add_deny',
			array(
				'hidden_permissions' => !empty($context['hidden_permissions']) ? $context['hidden_permissions'] : array(),
			)
		);
		while ($row = wesql::fetch_assoc($request))
			if (isset($context['groups'][(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
				$context['groups'][(int) $row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] = $row['num_permissions'];
		wesql::free_result($request);

		// Get the "default" profile permissions too.
		$request = wesql::query('
			SELECT id_profile, id_group, COUNT(*) AS num_permissions, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = {int:default_profile}
			' . (empty($context['hidden_permissions']) ? '' : ' AND permission NOT IN ({array_string:hidden_permissions})') . '
			GROUP BY id_profile, id_group, add_deny',
			array(
				'default_profile' => 1,
				'hidden_permissions' => !empty($context['hidden_permissions']) ? $context['hidden_permissions'] : array(),
			)
		);
		while ($row = wesql::fetch_assoc($request))
			if (isset($context['groups'][(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
				$context['groups'][(int) $row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] += $row['num_permissions'];
		wesql::free_result($request);
	}
	else
	{
		$_REQUEST['pid'] = (int) $_REQUEST['pid'];

		if (!isset($context['profiles'][$_REQUEST['pid']]))
			fatal_lang_error('no_access', false);

		// Change the selected tab to better reflect that this really is a board profile.
		$context[$context['admin_menu_name']]['current_subsection'] = 'profiles';

		$request = wesql::query('
			SELECT id_profile, id_group, COUNT(*) AS num_permissions, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = {int:current_profile}
			GROUP BY id_profile, id_group, add_deny',
			array(
				'current_profile' => $_REQUEST['pid'],
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (isset($context['groups'][(int) $row['id_group']]) && (!empty($row['add_deny']) || $row['id_group'] != -1))
				$context['groups'][(int) $row['id_group']]['num_permissions'][empty($row['add_deny']) ? 'denied' : 'allowed'] += $row['num_permissions'];
		}
		wesql::free_result($request);

		$context['profile'] = array(
			'id' => $_REQUEST['pid'],
			'name' => $context['profiles'][$_REQUEST['pid']]['name'],
		);
	}

	// If guests can't browse the forum, no point giving them a UI for it.
	if (empty($settings['allow_guestAccess']))
		unset($context['groups'][-1]);

	// We can modify any permission set apart from the read only, reply only and no polls ones as they are redefined.
	$context['can_modify'] = empty($_REQUEST['pid']) || $_REQUEST['pid'] == 1 || $_REQUEST['pid'] > 4;

	// Load the proper template.
	wetem::load('permission_index');
}

function PermissionByBoard()
{
	global $context, $settings, $txt, $cat_tree, $boardList, $boards;

	$context['page_title'] = $txt['permissions_boards'];
	$context['edit_all'] = isset($_GET['edit']);

	// Saving?
	if (!empty($_POST['save_changes']) && !empty($_POST['boardprofile']))
	{
		checkSession('request');

		$changes = array();
		foreach ($_POST['boardprofile'] as $board => $profile)
		{
			$changes[(int) $profile][] = (int) $board;
		}

		if (!empty($changes))
		{
			foreach ($changes as $profile => $boards)
				wesql::query('
					UPDATE {db_prefix}boards
					SET id_profile = {int:current_profile}
					WHERE id_board IN ({array_int:board_list})',
					array(
						'board_list' => $boards,
						'current_profile' => $profile,
					)
				);
		}

		$context['edit_all'] = false;
	}

	// Load all permission profiles.
	loadPermissionProfiles();

	// Get the board tree.
	loadSource('Subs-Boards');

	getBoardTree();

	// Build the list of the boards.
	$context['categories'] = array();
	foreach ($cat_tree as $catid => $tree)
	{
		$context['categories'][$catid] = array(
			'name' => &$tree['node']['name'],
			'id' => &$tree['node']['id'],
			'boards' => array()
		);
		foreach ($boardList[$catid] as $boardid)
		{
			if (!isset($context['profiles'][$boards[$boardid]['profile']]))
				$boards[$boardid]['profile'] = 1;

			$context['categories'][$catid]['boards'][$boardid] = array(
				'id' => &$boards[$boardid]['id'],
				'name' => &$boards[$boardid]['name'],
				'description' => &$boards[$boardid]['description'],
				'child_level' => &$boards[$boardid]['level'],
				'profile' => &$boards[$boardid]['profile'],
				'profile_name' => $context['profiles'][$boards[$boardid]['profile']]['name'],
			);
		}
	}

	wetem::load('by_board');
}

function SetQuickGroups()
{
	global $context, $settings;

	checkSession();

	loadIllegalPermissions();
	loadIllegalGuestPermissions();

	// Make sure only one of the quick options was selected.
	if ((!empty($_POST['predefined']) && ((isset($_POST['copy_from']) && $_POST['copy_from'] != 'empty') || !empty($_POST['permissions']))) || (!empty($_POST['copy_from']) && $_POST['copy_from'] != 'empty' && !empty($_POST['permissions'])))
		fatal_lang_error('permissions_only_one_option', false);

	if (empty($_POST['group']) || !is_array($_POST['group']))
		$_POST['group'] = array();

	// Only accept numeric values for selected membergroups.
	foreach ($_POST['group'] as $id => $group_id)
		$_POST['group'][$id] = (int) $group_id;
	$_POST['group'] = array_unique($_POST['group']);

	// And if by any chance, guests can't browse the forum, make sure that wasn't selected.
	if (empty($settings['allow_guestAccess']))
		$_POST['group'] = array_diff($_POST['group'], array(-1));

	if (empty($_REQUEST['pid']))
		$_REQUEST['pid'] = 0;
	else
		$_REQUEST['pid'] = (int) $_REQUEST['pid'];

	// Fix up the old global to the new default!
	$bid = max(1, $_REQUEST['pid']);

	// No modifying the predefined profiles.
	if ($_REQUEST['pid'] > 1 && $_REQUEST['pid'] < 5)
		fatal_lang_error('no_access', false);

	// Clear out any cached authority.
	updateSettings(array('settings_updated' => time()));

	// No groups where selected.
	if (empty($_POST['group']))
		redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

	// Set a predefined permission profile.
	if (!empty($_POST['predefined']))
	{
		// Make sure it's a predefined permission set we expect.
		if (!in_array($_POST['predefined'], array('restrict', 'standard', 'moderator', 'maintenance')))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		foreach ($_POST['group'] as $group_id)
		{
			if (!empty($_REQUEST['pid']))
				setPermissionLevel($_POST['predefined'], $group_id, $_REQUEST['pid']);
			else
				setPermissionLevel($_POST['predefined'], $group_id);
		}
	}
	// Set a permission profile based on the permissions of a selected group.
	elseif ($_POST['copy_from'] != 'empty')
	{
		// Just checking the input.
		if (!is_numeric($_POST['copy_from']))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		// Make sure the group we're copying to is never included.
		$_POST['group'] = array_diff($_POST['group'], array($_POST['copy_from']));

		// No groups left? Too bad.
		if (empty($_POST['group']))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		if (empty($_REQUEST['pid']))
		{
			// Retrieve current permissions of group.
			$request = wesql::query('
				SELECT permission, add_deny
				FROM {db_prefix}permissions
				WHERE id_group = {int:copy_from}',
				array(
					'copy_from' => $_POST['copy_from'],
				)
			);
			$target_perm = array();
			while ($row = wesql::fetch_assoc($request))
				$target_perm[$row['permission']] = $row['add_deny'];
			wesql::free_result($request);

			$inserts = array();
			foreach ($_POST['group'] as $group_id)
				foreach ($target_perm as $perm => $add_deny)
				{
					// No dodgy permissions please!
					if (!empty($context['illegal_permissions']) && in_array($perm, $context['illegal_permissions']))
						continue;
					if ($group_id == -1 && in_array($perm, $context['non_guest_permissions']))
						continue;

					if ($group_id != 1 && $group_id != 3)
						$inserts[] = array($perm, $group_id, $add_deny);
				}

			// Delete the previous permissions...
			wesql::query('
				DELETE FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:group_list})' . (empty($context['illegal_permissions']) ? '' : '
					AND permission NOT IN ({array_string:illegal_permissions})'),
				array(
					'group_list' => $_POST['group'],
					'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
				)
			);

			if (!empty($inserts))
			{
				// ..and insert the new ones.
				wesql::insert('',
					'{db_prefix}permissions',
					array(
						'permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int',
					),
					$inserts,
					array('permission', 'id_group')
				);
			}
		}

		// Now do the same for the board permissions.
		$request = wesql::query('
			SELECT permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_group = {int:copy_from}
				AND id_profile = {int:current_profile}',
			array(
				'copy_from' => $_POST['copy_from'],
				'current_profile' => $bid,
			)
		);
		$target_perm = array();
		while ($row = wesql::fetch_assoc($request))
			$target_perm[$row['permission']] = $row['add_deny'];
		wesql::free_result($request);

		$inserts = array();
		foreach ($_POST['group'] as $group_id)
			foreach ($target_perm as $perm => $add_deny)
			{
				// Are these for guests?
				if ($group_id == -1 && in_array($perm, $context['non_guest_permissions']))
					continue;

				$inserts[] = array($perm, $group_id, $bid, $add_deny);
			}

		// Delete the previous global board permissions...
		wesql::query('
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:current_group_list})
				AND id_profile = {int:current_profile}',
			array(
				'current_group_list' => $_POST['group'],
				'current_profile' => $bid,
			)
		);

		// And insert the copied permissions.
		if (!empty($inserts))
		{
			// ..and insert the new ones.
			wesql::insert('',
				'{db_prefix}board_permissions',
				array('permission' => 'string', 'id_group' => 'int', 'id_profile' => 'int', 'add_deny' => 'int'),
				$inserts,
				array('permission', 'id_group', 'id_profile')
			);
		}

		// Update any children out there!
		updateChildPermissions($_POST['group'], $_REQUEST['pid']);
	}
	// Set or unset a certain permission for the selected groups.
	elseif (!empty($_POST['permissions']))
	{
		// Unpack two variables that were transported.
		list ($permissionType, $permission) = explode('/', $_POST['permissions']);

		// Check whether our input is within expected range.
		if (!in_array($_POST['add_remove'], array('add', 'clear', 'deny')) || !in_array($permissionType, array('membergroup', 'board')))
			redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);

		if ($_POST['add_remove'] == 'clear')
		{
			if ($permissionType == 'membergroup')
				wesql::query('
					DELETE FROM {db_prefix}permissions
					WHERE id_group IN ({array_int:current_group_list})
						AND permission = {string:current_permission}' . (empty($context['illegal_permissions']) ? '' : '
						AND permission NOT IN ({array_string:illegal_permissions})'),
					array(
						'current_group_list' => $_POST['group'],
						'current_permission' => $permission,
						'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
					)
				);
			else
				wesql::query('
					DELETE FROM {db_prefix}board_permissions
					WHERE id_group IN ({array_int:current_group_list})
						AND id_profile = {int:current_profile}
						AND permission = {string:current_permission}',
					array(
						'current_group_list' => $_POST['group'],
						'current_profile' => $bid,
						'current_permission' => $permission,
					)
				);
		}
		// Add a permission (either 'set' or 'deny').
		else
		{
			$add_deny = $_POST['add_remove'] == 'add' ? '1' : '0';
			$permChange = array();
			foreach ($_POST['group'] as $groupID)
			{
				if ($groupID == -1 && in_array($permission, $context['non_guest_permissions']))
					continue;

				if ($permissionType == 'membergroup' && $groupID != 1 && $groupID != 3 && (empty($context['illegal_permissions']) || !in_array($permission, $context['illegal_permissions'])))
					$permChange[] = array($permission, $groupID, $add_deny);
				elseif ($permissionType != 'membergroup')
					$permChange[] = array($permission, $groupID, $bid, $add_deny);
			}

			if (!empty($permChange))
			{
				if ($permissionType == 'membergroup')
					wesql::insert('replace',
						'{db_prefix}permissions',
						array('permission' => 'string', 'id_group' => 'int', 'add_deny' => 'int'),
						$permChange,
						array('permission', 'id_group')
					);
				// Board permissions go into the other table.
				else
					wesql::insert('replace',
						'{db_prefix}board_permissions',
						array('permission' => 'string', 'id_group' => 'int', 'id_profile' => 'int', 'add_deny' => 'int'),
						$permChange,
						array('permission', 'id_group', 'id_profile')
					);
			}
		}

		// Another child update!
		updateChildPermissions($_POST['group'], $_REQUEST['pid']);
	}

	redirectexit('action=admin;area=permissions;pid=' . $_REQUEST['pid']);
}

function ModifyMembergroup()
{
	global $context, $txt, $settings;

	if (!isset($_GET['group']) || (empty($settings['allow_guestAccess']) && $_GET['group'] == -1))
		fatal_lang_error('no_access', false);

	$context['group']['id'] = (int) $_GET['group'];

	// Are they toggling the view?
	if (isset($_GET['view']))
	{
		$context['admin_preferences']['pv'] = $_GET['view'] == 'classic' ? 'classic' : 'simple';

		// Update the users preferences.
		loadSource('Subs-Admin');
		updateAdminPreferences();
	}

	$context['view_type'] = !empty($context['admin_preferences']['pv']) && $context['admin_preferences']['pv'] == 'classic' ? 'classic' : 'simple';

	// It's not likely you'd end up here with this setting disabled.
	if ($_GET['group'] == 1)
		redirectexit('action=admin;area=permissions');

	loadAllPermissions($context['view_type']);
	loadPermissionProfiles();

	if ($context['group']['id'] > 0)
	{
		$result = wesql::query('
			SELECT group_name, id_parent
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT 1',
			array(
				'current_group' => $context['group']['id'],
			)
		);
		list ($context['group']['name'], $parent) = wesql::fetch_row($result);
		wesql::free_result($result);

		// Cannot edit an inherited group!
		if ($parent != -2)
			fatal_lang_error('cannot_edit_permissions_inherited');
	}
	elseif ($context['group']['id'] == -1)
		$context['group']['name'] = $txt['membergroups_guests'];
	else
		$context['group']['name'] = $txt['membergroups_members'];

	$context['profile']['id'] = empty($_GET['pid']) ? 0 : (int) $_GET['pid'];

	// If this is a moderator and they are editing "no profile" then we only do boards.
	if ($context['group']['id'] == 3 && empty($context['profile']['id']))
	{
		// For sanity just check they have no general permissions.
		wesql::query('
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:moderator_group}',
			array(
				'moderator_group' => 3,
			)
		);

		$context['profile']['id'] = 1;
	}

	$context['permission_type'] = empty($context['profile']['id']) ? 'membergroup' : 'board';
	$context['profile']['can_modify'] = !$context['profile']['id'] || $context['profiles'][$context['profile']['id']]['can_modify'];

	// Set up things a little nicer for board related stuff...
	if ($context['permission_type'] == 'board')
	{
		$context['profile']['name'] = $context['profiles'][$context['profile']['id']]['name'];
		$context[$context['admin_menu_name']]['current_subsection'] = 'profiles';
	}

	// Fetch the current permissions.
	$permissions = array(
		'membergroup' => array('allowed' => array(), 'denied' => array()),
		'board' => array('allowed' => array(), 'denied' => array())
	);

	// General permissions?
	if ($context['permission_type'] == 'membergroup')
	{
		$result = wesql::query('
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}',
			array(
				'current_group' => $_GET['group'],
			)
		);
		while ($row = wesql::fetch_assoc($result))
			$permissions['membergroup'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
		wesql::free_result($result);
	}

	// Fetch current board permissions...
	$result = wesql::query('
		SELECT permission, add_deny
		FROM {db_prefix}board_permissions
		WHERE id_group = {int:current_group}
			AND id_profile = {int:current_profile}',
		array(
			'current_group' => $context['group']['id'],
			'current_profile' => $context['permission_type'] == 'membergroup' ? 1 : $context['profile']['id'],
		)
	);
	while ($row = wesql::fetch_assoc($result))
		$permissions['board'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['permission'];
	wesql::free_result($result);

	// Loop through each permission and set whether it's checked.
	foreach ($context['permissions'] as $permissionType => $tmp)
	{
		foreach ($tmp['columns'] as $position => $permissionGroups)
		{
			foreach ($permissionGroups as $permissionGroup => $permissionArray)
			{
				foreach ($permissionArray['permissions'] as $perm)
				{
					// Create a shortcut for the current permission.
					$curPerm =& $context['permissions'][$permissionType]['columns'][$position][$permissionGroup]['permissions'][$perm['id']];
					if ($tmp['view'] == 'classic')
					{
						if ($perm['has_own_any'])
						{
							$curPerm['any']['select'] = in_array($perm['id'] . '_any', $permissions[$permissionType]['allowed']) ? 'on' : (in_array($perm['id'] . '_any', $permissions[$permissionType]['denied']) ? 'denied' : 'off');
							$curPerm['own']['select'] = in_array($perm['id'] . '_own', $permissions[$permissionType]['allowed']) ? 'on' : (in_array($perm['id'] . '_own', $permissions[$permissionType]['denied']) ? 'denied' : 'off');
						}
						else
							$curPerm['select'] = in_array($perm['id'], $permissions[$permissionType]['denied']) ? 'denied' : (in_array($perm['id'], $permissions[$permissionType]['allowed']) ? 'on' : 'off');
					}
					else
					{
						$curPerm['select'] = in_array($perm['id'], $permissions[$permissionType]['denied']) ? 'denied' : (in_array($perm['id'], $permissions[$permissionType]['allowed']) ? 'on' : 'off');
					}
				}
			}
		}
	}
	wetem::load('modify_group');
	$context['page_title'] = $txt['permissions_modify_group'];
}

function ModifyMembergroup2()
{
	global $settings, $context;

	checkSession();

	loadIllegalPermissions();

	$_GET['group'] = (int) $_GET['group'];
	$_GET['pid'] = (int) $_GET['pid'];

	// Disallow saving guest permissions if they're not even allowed in the forum.
	if (empty($settings['allow_guestAccess']) && $_GET['group'] == -1)
		fatal_lang_error('no_access', false);

	// Cannot modify predefined profiles.
	if ($_GET['pid'] > 1 && $_GET['pid'] < 5)
		fatal_lang_error('no_access', false);

	// Verify this isn't inherited.
	if ($_GET['group'] == -1 || $_GET['group'] == 0)
		$parent = -2;
	else
	{
		$result = wesql::query('
			SELECT id_parent
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT 1',
			array(
				'current_group' => $_GET['group'],
			)
		);
		list ($parent) = wesql::fetch_row($result);
		wesql::free_result($result);
	}

	if ($parent != -2)
		fatal_lang_error('cannot_edit_permissions_inherited');

	$givePerms = array('membergroup' => array(), 'board' => array());

	// Guest group, we need illegal, guest permissions.
	if ($_GET['group'] == -1)
	{
		loadIllegalGuestPermissions();
		$context['illegal_permissions'] = array_merge($context['illegal_permissions'], $context['non_guest_permissions']);
	}

	// Prepare all permissions that were set or denied for addition to the DB.
	if (isset($_POST['perm']) && is_array($_POST['perm']))
	{
		foreach ($_POST['perm'] as $perm_type => $perm_array)
		{
			if (is_array($perm_array))
			{
				foreach ($perm_array as $permission => $value)
					if ($value == 'on' || $value == 'deny')
					{
						// Don't allow people to escalate themselves!
						if (!empty($context['illegal_permissions']) && in_array($permission, $context['illegal_permissions']))
							continue;

						$givePerms[$perm_type][] = array($_GET['group'], $permission, $value == 'deny' ? 0 : 1);
					}
			}
		}
	}

	// Insert the general permissions.
	if ($_GET['group'] != 3 && empty($_GET['pid']))
	{
		wesql::query('
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}' . (empty($context['illegal_permissions']) ? '' : '
				AND permission NOT IN ({array_string:illegal_permissions})'),
			array(
				'current_group' => $_GET['group'],
				'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
			)
		);

		if (!empty($givePerms['membergroup']))
		{
			wesql::insert('replace',
				'{db_prefix}permissions',
				array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$givePerms['membergroup'],
				array('id_group', 'permission')
			);
		}
	}

	// Insert the boardpermissions.
	$profileid = max(1, $_GET['pid']);
	wesql::query('
		DELETE FROM {db_prefix}board_permissions
		WHERE id_group = {int:current_group}
			AND id_profile = {int:current_profile}',
		array(
			'current_group' => $_GET['group'],
			'current_profile' => $profileid,
		)
	);
	if (!empty($givePerms['board']))
	{
		foreach ($givePerms['board'] as $k => $v)
			$givePerms['board'][$k][] = $profileid;
		wesql::insert('replace',
			'{db_prefix}board_permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int', 'id_profile' => 'int'),
			$givePerms['board'],
			array('id_group', 'permission', 'id_profile')
		);
	}

	// Update any inherited permissions as required.
	updateChildPermissions($_GET['group'], $_GET['pid']);

	// Clear cached privs.
	updateSettings(array('settings_updated' => time()));

	redirectexit('action=admin;area=permissions;pid=' . $_GET['pid']);
}

// Screen for modifying general permission settings.
function GeneralPermissionSettings($return_config = false)
{
	global $context, $settings, $txt, $scripturl;

	// All the setting variables
	$config_vars = array(
		array('title', 'settings'),
			// Inline permissions.
			array('permissions', 'manage_permissions', 'exclude' => array(-1, 0)),
		'',
			// A few useful settings
			array('check', 'permission_enable_deny', 0, $txt['permission_settings_enable_deny'], 'help' => 'permissions_deny'),
			array('check', 'permission_enable_postgroups', 0, $txt['permission_settings_enable_postgroups'], 'help' => 'permissions_postgroups'),
	);

	if ($return_config)
		return $config_vars;

	$context['page_title'] = $txt['permission_settings_title'];
	wetem::load('show_settings');

	// Needed for the inline permission functions, and the settings template.
	loadSource('ManageServer');

	// Don't let guests have these permissions.
	$context['post_url'] = $scripturl . '?action=admin;area=permissions;save;sa=settings';

	// Saving the settings?
	if (isset($_GET['save']))
	{
		checkSession('post');
		saveDBSettings($config_vars);

		// Clear all deny permissions...if we want that.
		if (empty($settings['permission_enable_deny']))
			wesql::query('
				DELETE FROM {db_prefix}permissions, {db_prefix}board_permissions
				WHERE add_deny = {int:denied}',
				array(
					'denied' => 0,
				)
			);

		// Make sure there are no postgroup based permissions left.
		if (empty($settings['permission_enable_postgroups']))
		{
			// Get a list of postgroups.
			$post_groups = array();
			$request = wesql::query('
				SELECT id_group
				FROM {db_prefix}membergroups
				WHERE min_posts != {int:min_posts}',
				array(
					'min_posts' => -1,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				$post_groups[] = $row['id_group'];
			wesql::free_result($request);

			// Remove'em.
			wesql::query('
				DELETE FROM {db_prefix}permissions
				WHERE id_group IN ({array_int:post_group_list})',
				array(
					'post_group_list' => $post_groups,
				)
			);
			wesql::query('
				DELETE FROM {db_prefix}board_permissions
				WHERE id_group IN ({array_int:post_group_list})',
				array(
					'post_group_list' => $post_groups,
				)
			);
			wesql::query('
				UPDATE {db_prefix}membergroups
				SET id_parent = {int:not_inherited}
				WHERE id_parent IN ({array_int:post_group_list})',
				array(
					'post_group_list' => $post_groups,
					'not_inherited' => -2,
				)
			);
		}

		redirectexit('action=admin;area=permissions;sa=settings');
	}

	prepareDBSettingContext($config_vars);
}

// Set the permission level for a specific profile, group, or group for a profile.
function setPermissionLevel($level, $group, $profile = 'null')
{
	global $context;

	loadIllegalPermissions();
	loadIllegalGuestPermissions();

	// Levels by group... restrict, standard, moderator, maintenance.
	$groupLevels = array(
		'board' => array('inherit' => array()),
		'group' => array('inherit' => array())
	);
	// Levels by board... standard, publish, free.
	$boardLevels = array('inherit' => array());

	// Restrictive - ie. guests.
	$groupLevels['global']['restrict'] = array(
		'search_posts',
		'view_stats',
		'who_view',
		'profile_view_own',
		'profile_identity_own',
	);
	$groupLevels['board']['restrict'] = array(
		'poll_view',
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'delete_own',
		'modify_own',
		'mark_any_notify',
		'mark_notify',
		'report_any',
		'send_topic',
	);

	// Standard - ie. members.  They can do anything Restrictive can.
	$groupLevels['global']['standard'] = array_merge($groupLevels['global']['restrict'], array(
		'view_mlist',
		'pm_read',
		'pm_send',
		'save_pm_draft',
		'auto_save_pm_draft',
		'post_thought',
		'profile_view_any',
		'profile_extra_own',
		'profile_signature_own',
		'profile_website_own',
		'profile_server_avatar',
		'profile_upload_avatar',
		'profile_remote_avatar',
		'profile_remove_own',
	));
	$groupLevels['board']['standard'] = array_merge($groupLevels['board']['restrict'], array(
		'save_post_draft',
		'auto_save_post_draft',
		'poll_vote',
		'poll_edit_own',
		'poll_post',
		'poll_add_own',
		'post_attachment',
		'lock_own',
		'remove_own',
		'view_attachments',
	));

	// Moderator - ie. moderators :P. They can do what standard can, and more.
	$groupLevels['global']['moderator'] = array_merge($groupLevels['global']['standard'], array(
		'access_mod_center',
		'issue_warning',
	));
	$groupLevels['board']['moderator'] = array_merge($groupLevels['board']['standard'], array(
		'pin_topic',
		'poll_edit_any',
		'delete_any',
		'modify_any',
		'lock_any',
		'remove_any',
		'move_any',
		'merge_any',
		'split_any',
		'poll_lock_any',
		'poll_remove_any',
		'poll_add_any',
		'approve_posts',
	));

	// Maintenance - wannabe admins.  They can do almost everything.
	$groupLevels['global']['maintenance'] = array_merge($groupLevels['global']['moderator'], array(
		'manage_attachments',
		'manage_smileys',
		'manage_boards',
		'moderate_forum',
		'manage_membergroups',
		'manage_bans',
		'admin_forum',
		'manage_permissions',
		'edit_news',
		'profile_identity_any',
		'profile_extra_any',
		'profile_signature_any',
		'profile_website_any',
		'profile_title_any',
	));
	$groupLevels['board']['maintenance'] = array_merge($groupLevels['board']['moderator'], array(
	));

	// Standard - nothing above the group permissions. (this SHOULD be empty.)
	$boardLevels['standard'] = array(
	);

	// Locked - just that, you can't post here.
	$boardLevels['locked'] = array(
		'poll_view',
		'mark_notify',
		'report_any',
		'send_topic',
		'view_attachments',
	);

	// Publisher - just a little more...
	$boardLevels['publish'] = array_merge($boardLevels['locked'], array(
		'post_new',
		'post_reply_own',
		'post_reply_any',
		'delete_own',
		'modify_own',
		'mark_any_notify',
		'delete_replies',
		'modify_replies',
		'poll_vote',
		'poll_edit_own',
		'poll_post',
		'poll_add_own',
		'poll_remove_own',
		'post_attachment',
		'lock_own',
		'remove_own',
	));

	// Free for All - Scary. Just scary.
	$boardLevels['free'] = array_merge($boardLevels['publish'], array(
		'poll_lock_any',
		'poll_edit_any',
		'poll_add_any',
		'poll_remove_any',
		'pin_topic',
		'lock_any',
		'remove_any',
		'delete_any',
		'split_any',
		'merge_any',
		'modify_any',
		'approve_posts',
	));

	// Make sure we're not granting someone too many permissions!
	foreach ($groupLevels['global'][$level] as $k => $permission)
	{
		if (!empty($context['illegal_permissions']) && in_array($permission, $context['illegal_permissions']))
			unset($groupLevels['global'][$level][$k]);

		if ($group == -1 && in_array($permission, $context['non_guest_permissions']))
			unset($groupLevels['global'][$level][$k]);
	}
	if ($group == -1)
		foreach ($groupLevels['board'][$level] as $k => $permission)
			if (in_array($permission, $context['non_guest_permissions']))
				unset($groupLevels['board'][$level][$k]);

	// Reset all cached permissions.
	updateSettings(array('settings_updated' => time()));

	// Setting group permissions.
	if ($profile === 'null' && $group !== 'null')
	{
		$group = (int) $group;

		if (empty($groupLevels['global'][$level]))
			return;

		wesql::query('
			DELETE FROM {db_prefix}permissions
			WHERE id_group = {int:current_group}' . (empty($context['illegal_permissions']) ? '' : '
				AND permission NOT IN ({array_string:illegal_permissions})'),
			array(
				'current_group' => $group,
				'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
			)
		);
		wesql::query('
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group = {int:current_group}
				AND id_profile = {int:default_profile}',
			array(
				'current_group' => $group,
				'default_profile' => 1,
			)
		);

		$groupInserts = array();
		foreach ($groupLevels['global'][$level] as $permission)
			$groupInserts[] = array($group, $permission);

		wesql::insert('',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string'),
			$groupInserts,
			array('id_group')
		);

		$boardInserts = array();
		foreach ($groupLevels['board'][$level] as $permission)
			$boardInserts[] = array(1, $group, $permission);

		wesql::insert('',
			'{db_prefix}board_permissions',
			array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
			$boardInserts,
			array('id_profile', 'id_group')
		);
	}
	// Setting profile permissions for a specific group.
	elseif ($profile !== 'null' && $group !== 'null' && ($profile == 1 || $profile > 4))
	{
		$group = (int) $group;
		$profile = (int) $profile;

		if (!empty($groupLevels['global'][$level]))
		{
			wesql::query('
				DELETE FROM {db_prefix}board_permissions
				WHERE id_group = {int:current_group}
					AND id_profile = {int:current_profile}',
				array(
					'current_group' => $group,
					'current_profile' => $profile,
				)
			);
		}

		if (!empty($groupLevels['board'][$level]))
		{
			$boardInserts = array();
			foreach ($groupLevels['board'][$level] as $permission)
				$boardInserts[] = array($profile, $group, $permission);

			wesql::insert('',
				'{db_prefix}board_permissions',
				array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
				$boardInserts,
				array('id_profile', 'id_group')
			);
		}
	}
	// Setting profile permissions for all groups.
	elseif ($profile !== 'null' && $group === 'null' && ($profile == 1 || $profile > 4))
	{
		$profile = (int) $profile;

		wesql::query('
			DELETE FROM {db_prefix}board_permissions
			WHERE id_profile = {int:current_profile}',
			array(
				'current_profile' => $profile,
			)
		);

		if (empty($boardLevels[$level]))
			return;

		// Get all the groups...
		$query = wesql::query('
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE id_group > {int:moderator_group}
			ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
			array(
				'moderator_group' => 3,
				'newbie_group' => 4,
			)
		);
		while ($row = wesql::fetch_row($query))
		{
			$group = $row[0];

			$boardInserts = array();
			foreach ($boardLevels[$level] as $permission)
				$boardInserts[] = array($profile, $group, $permission);

			wesql::insert('',
				'{db_prefix}board_permissions',
				array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
				$boardInserts,
				array('id_profile', 'id_group')
			);
		}
		wesql::free_result($query);

		// Add permissions for ungrouped members.
		$boardInserts = array();
		foreach ($boardLevels[$level] as $permission)
			$boardInserts[] = array($profile, 0, $permission);

		wesql::insert('',
				'{db_prefix}board_permissions',
				array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string'),
				$boardInserts,
				array('id_profile', 'id_group')
			);
	}
	// $profile and $group are both null!
	else
		fatal_lang_error('no_access', false);
}

function loadAllPermissions($loadType = 'classic')
{
	global $context, $txt, $settings;

	// List of all the groups dependent on the currently selected view - for the order so it looks pretty, yeah?
	$permissionGroups = array(
		'membergroup' => array(
			'simple' => array(
				'view_basic_info',
				'use_pm_system',
				'edit_profile',
				'delete_account',
				'use_avatar',
				'moderate_general',
				'administrate',
				'media',
			),
			'classic' => array(
				'general',
				'pm',
				'maintenance',
				'member_admin',
				'profile',
				'media',
			),
		),
		'board' => array(
			'simple' => array(
				'make_posts',
				'make_unapproved_posts',
				'post_polls',
				'participate',
				'modify',
				'notification',
				'attach',
				'moderate',
			),
			'classic' => array(
				'general_board',
				'topic',
				'post',
				'poll',
				'notification',
				'attachment',
			),
		),
	);

	/*
		The format of this list is as follows:

		'membergroup' => array(
			'permissions_inside' => array(has_multiple_options, classic_view_group, simple_view_group(_own)*, simple_view_group_any*),
		),
		'board' => array(
			'permissions_inside' => array(has_multiple_options, classic_view_group, simple_view_group(_own)*, simple_view_group_any*),
		);
	*/
	$permissionList = array(
		'membergroup' => array(
			'view_stats' => array(false, 'general', 'view_basic_info'),
			'view_mlist' => array(false, 'general', 'view_basic_info'),
			'who_view' => array(false, 'general', 'view_basic_info'),
			'search_posts' => array(false, 'general', 'view_basic_info'),
			'post_thought' => array(false, 'general', 'view_basic_info'),
			'pm_read' => array(false, 'pm', 'use_pm_system'),
			'pm_send' => array(false, 'pm', 'use_pm_system'),
			'save_pm_draft' => array(false, 'pm', 'use_pm_system'),
			'auto_save_pm_draft' => array(false, 'pm', 'use_pm_system'),
			'admin_forum' => array(false, 'maintenance', 'administrate'),
			'manage_boards' => array(false, 'maintenance', 'administrate'),
			'manage_attachments' => array(false, 'maintenance', 'administrate'),
			'manage_smileys' => array(false, 'maintenance', 'administrate'),
			'edit_news' => array(false, 'maintenance', 'administrate'),
			'access_mod_center' => array(false, 'maintenance', 'moderate_general'),
			'moderate_forum' => array(false, 'member_admin', 'moderate_general'),
			'manage_membergroups' => array(false, 'member_admin', 'administrate'),
			'manage_permissions' => array(false, 'member_admin', 'administrate'),
			'manage_bans' => array(false, 'member_admin', 'administrate'),
			'send_mail' => array(false, 'member_admin', 'administrate'),
			'issue_warning' => array(false, 'member_admin', 'moderate_general'),

			'profile_view' => array(true, 'profile', 'view_basic_info', 'view_basic_info'),
			'profile_identity' => array(true, 'profile', 'edit_profile', 'moderate_general'),
			'profile_extra' => array(true, 'profile', 'edit_profile', 'moderate_general'),
			'profile_signature' => array(true, 'profile', 'edit_profile', 'moderate_general'),
			'profile_website' => array(true, 'profile', 'edit_profile', 'moderate_general'),
			'profile_title' => array(true, 'profile', 'edit_profile', 'moderate_general'),
			'profile_remove' => array(true, 'profile', 'delete_account', 'moderate_general'),
			'profile_server_avatar' => array(false, 'profile', 'use_avatar'),
			'profile_upload_avatar' => array(false, 'profile', 'use_avatar'),
			'profile_remote_avatar' => array(false, 'profile', 'use_avatar'),

			'media_access' => array(false, 'media', 'media'),
			'media_access_unseen' => array(false, 'media', 'media'),
			'media_viewprofile' => array(false, 'media', 'media'),
			'media_search' => array(false, 'media', 'media'),
			'media_add_playlists' => array(false, 'media', 'media'),
			'media_add_user_album' => array(false, 'media', 'media'),
			'media_auto_approve_albums' => array(false, 'media', 'media'),
			'media_moderate_own_albums' => array(false, 'media', 'media'),
			'media_moderate' => array(false, 'media', 'media'),
			'media_manage' => array(false, 'media', 'media'),
		),
		'board' => array(
			'moderate_board' => array(false, 'general_board', 'moderate'),
			'approve_posts' => array(false, 'general_board', 'moderate'),
			'post_new' => array(false, 'topic', 'make_posts'),
			'post_unapproved_topics' => array(false, 'topic', 'make_unapproved_posts'),
			'post_unapproved_replies' => array(true, 'topic', 'make_unapproved_posts', 'make_unapproved_posts'),
			'post_reply' => array(true, 'topic', 'make_posts', 'make_posts'),
			'save_post_draft' => array(false, 'topic', 'make_posts'),
			'auto_save_post_draft' => array(false, 'topic', 'make_posts'),
			'merge_any' => array(false, 'topic', 'moderate'),
			'split_any' => array(false, 'topic', 'moderate'),
			'send_topic' => array(false, 'topic', 'moderate'),
			'pin_topic' => array(false, 'topic', 'moderate'),
			'move' => array(true, 'topic', 'moderate', 'moderate'),
			'lock' => array(true, 'topic', 'moderate', 'moderate'),
			'remove' => array(true, 'topic', 'modify', 'moderate'),
			'modify_replies' => array(false, 'topic', 'moderate'),
			'delete_replies' => array(false, 'topic', 'moderate'),
			'announce_topic' => array(false, 'topic', 'moderate'),
			'delete' => array(true, 'post', 'modify', 'moderate'),
			'modify' => array(true, 'post', 'modify', 'moderate'),
			'report_any' => array(false, 'post', 'participate'),

			'poll_view' => array(false, 'poll', 'participate'),
			'poll_vote' => array(false, 'poll', 'participate'),
			'poll_post' => array(false, 'poll', 'post_polls'),
			'poll_add' => array(true, 'poll', 'post_polls', 'moderate'),
			'poll_edit' => array(true, 'poll', 'modify', 'moderate'),
			'poll_lock' => array(true, 'poll', 'moderate', 'moderate'),
			'poll_remove' => array(true, 'poll', 'modify', 'moderate'),

			'mark_any_notify' => array(false, 'notification', 'notification'),
			'mark_notify' => array(false, 'notification', 'notification'),
			'view_attachments' => array(false, 'attachment', 'participate'),
			'post_attachment' => array(false, 'attachment', 'attach'),
		),
	);

	// All permission groups that will be shown in the left column on classic view.
	$leftPermissionGroups = array(
		'general',
		'maintenance',
		'member_admin',
		'topic',
		'post',
	);

	// We need to know what permissions we can't give to guests.
	loadIllegalGuestPermissions();

	// Some permissions are hidden if features are off.
	$hiddenPermissions = array();
	$relabelPermissions = array(); // Permissions to apply a different label to.
	$relabelGroups = array(); // As above but for groups.

	// Post moderation?
	if (!$settings['postmod_active'])
	{
		$hiddenPermissions[] = 'approve_posts';
		$hiddenPermissions[] = 'post_unapproved_topics';
		$hiddenPermissions[] = 'post_unapproved_replies';
	}
	// If we show them on classic view we change the name.
	else
	{
		// Relabel the topics permissions
		$relabelPermissions['post_new'] = 'auto_approve_topics';

		// Relabel the reply permissions
		$relabelPermissions['post_reply'] = 'auto_approve_replies';
	}

	if (empty($settings['pm_enabled']))
	{
		$hiddenPermissions[] = 'pm_read';
		$hiddenPermissions[] = 'pm_send';
		$hiddenPermissions[] = 'save_pm_draft';
		$hiddenPermissions[] = 'auto_save_pm_draft';
	}

	// Load up any permissions from plugin-info.xml files.
	//libxml_use_internal_errors(true);
	if (!empty($context['plugins_dir']))
		foreach ($context['plugins_dir'] as $id => $path)
		{
			$manifest = safe_sxml_load($path . '/plugin-info.xml');
			if ($manifest === false || empty($manifest->name) || empty($manifest->version) || empty($manifest->newperms))
				continue;
			if (!empty($manifest->newperms['filename']))
				loadPluginLanguage($id, (string) $manifest->newperms['filename']);
			if (!empty($manifest->newperms->groups))
				foreach ($manifest->newperms->groups->group as $group)
				{
					if (empty($group['name']) || empty($group['type']) || empty($group['classic']) || empty($group['simple']))
						continue;
					$type = (string) $group['type'];
					if (!isset($permissionGroups[$type]))
						continue;
					$permissionGroups[$type]['simple'][] = (string) $group['simple'];
					$permissionGroups[$type]['classic'][] = (string) $group['classic'];
					if (!empty($group['column']) && (string) $group['column'] == 'left')
						$leftPermission['groups'][] = (string) $group['classic'];
				}
			if (!empty($manifest->newperms->permissionlist))
				foreach ($manifest->newperms->permissionlist->permission as $perm)
				{
					if (empty($perm['type']) || empty($perm['name']) || empty($perm['classic']) || empty($perm['simple']))
						continue;
					$type = (string) $perm['type'];
					if (!isset($permissionList[$type]))
						continue;
					$name = (string) $perm['name'];
					$is_ownany = !empty($perm['ownany']) && ((string) $perm['ownany'] == 'true');
					$permissionList[$type][$name] = array($is_ownany, (string) $perm['classic'], (string) $perm['simple']);
					if ($is_ownany && !empty($perm['simpleany']))
						$permissionList[$type][$name][] = (string) $perm['simpleany'];
				}
			unset($manifest);
		}

	// Provide a practical way to modify permissions.
	call_hook('load_permissions', array(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions));

	$context['permissions'] = array();
	$context['hidden_permissions'] = array();
	foreach ($permissionList as $permissionType => $permissionList)
	{
		$context['permissions'][$permissionType] = array(
			'id' => $permissionType,
			'view' => $loadType,
			'columns' => array()
		);
		foreach ($permissionList as $permission => $permissionArray)
		{
			// If this is a guest permission we don't do it if it's the guest group.
			if (isset($context['group']['id']) && $context['group']['id'] == -1 && in_array($permission, $context['non_guest_permissions']))
				continue;

			// What groups will this permission be in?
			$own_group = $permissionArray[($loadType == 'classic' ? 1 : 2)];
			$any_group = $loadType == 'simple' && !empty($permissionArray[3]) ? $permissionArray[3] : ($loadType == 'simple' && $permissionArray[0] ? $permissionArray[2] : '');

			// First, Do these groups actually exist - if not add them.
			if (!isset($permissionGroups[$permissionType][$loadType][$own_group]))
				$permissionGroups[$permissionType][$loadType][$own_group] = true;
			if (!empty($any_group) && !isset($permissionGroups[$permissionType][$loadType][$any_group]))
				$permissionGroups[$permissionType][$loadType][$any_group] = true;

			// What column should this be located into?
			$position = $loadType == 'classic' && !in_array($own_group, $leftPermissionGroups) ? 1 : 0;

			// If the groups have not yet been created be sure to create them.
			$bothGroups = array('own' => $own_group);
			$bothGroups = array();

			// For guests, just reset the array.
			if (!isset($context['group']['id']) || !($context['group']['id'] == -1 && $any_group))
				$bothGroups['own'] = $own_group;

			if ($any_group)
			{
				$bothGroups['any'] = $any_group;

			}

			foreach ($bothGroups as $group)
				if (!isset($context['permissions'][$permissionType]['columns'][$position][$group]))
					$context['permissions'][$permissionType]['columns'][$position][$group] = array(
						'type' => $permissionType,
						'id' => $group,
						'name' => $loadType == 'simple' ? (isset($txt['permissiongroup_simple_' . $group]) ? $txt['permissiongroup_simple_' . $group] : '') : $txt['permissiongroup_' . $group],
						'icon' => isset($txt['permissionicon_' . $group]) ? $txt['permissionicon_' . $group] : $txt['permissionicon'],
						'help' => isset($txt['permissionhelp_' . $group]) ? $txt['permissionhelp_' . $group] : '',
						'hidden' => false,
						'permissions' => array()
					);

			// This is where we set up the permission dependent on the view.
			if ($loadType == 'classic')
			{
				$context['permissions'][$permissionType]['columns'][$position][$own_group]['permissions'][$permission] = array(
					'id' => $permission,
					'name' => !isset($relabelPermissions[$permission]) ? $txt['permissionname_' . $permission] : $txt[$relabelPermissions[$permission]],
					'show_help' => isset($txt['permissionhelp_' . $permission]),
					'note' => isset($txt['permissionnote_' . $permission]) ? $txt['permissionnote_' . $permission] : '',
					'has_own_any' => $permissionArray[0],
					'own' => array(
						'id' => $permission . '_own',
						'name' => $permissionArray[0] ? $txt['permissionname_' . $permission . '_own'] : ''
					),
					'any' => array(
						'id' => $permission . '_any',
						'name' => $permissionArray[0] ? $txt['permissionname_' . $permission . '_any'] : ''
					),
					'hidden' => in_array($permission, $hiddenPermissions),
				);
			}
			else
			{
				foreach ($bothGroups as $group_type => $group)
				{
					$context['permissions'][$permissionType]['columns'][$position][$group]['permissions'][$permission . ($permissionArray[0] ? '_' . $group_type : '')] = array(
						'id' => $permission . ($permissionArray[0] ? '_' . $group_type : ''),
						'name' => isset($txt['permissionname_simple_' . $permission . ($permissionArray[0] ? '_' . $group_type : '')]) ? $txt['permissionname_simple_' . $permission . ($permissionArray[0] ? '_' . $group_type : '')] : $txt['permissionname_' . $permission],
						'help_index' => isset($txt['permissionhelp_' . $permission]) ? 'permissionhelp_' . $permission : '',
						'hidden' => in_array($permission, $hiddenPermissions),
					);
				}
			}

			if (in_array($permission, $hiddenPermissions))
			{
				if ($permissionArray[0])
				{
					$context['hidden_permissions'][] = $permission . '_own';
					$context['hidden_permissions'][] = $permission . '_any';
				}
				else
					$context['hidden_permissions'][] = $permission;
			}
		}
		ksort($context['permissions'][$permissionType]['columns']);
	}

	// Check we don't leave any empty groups - and mark hidden ones as such.
	foreach ($context['permissions'][$permissionType]['columns'] as $column => $groups)
		foreach ($groups as $id => $group)
		{
			if (empty($group['permissions']))
				unset($context['permissions'][$permissionType]['columns'][$column][$id]);
			else
			{
				$foundNonHidden = false;
				foreach ($group['permissions'] as $permission)
					if (empty($permission['hidden']))
						$foundNonHidden = true;
				if (!$foundNonHidden)
					$context['permissions'][$permissionType]['columns'][$column][$id]['hidden'] = true;
			}
		}
}

// Initialize a form with inline permissions.
function init_inline_permissions($permission_details)
{
	global $context, $txt, $settings;

	loadLanguage('ManagePermissions');
	loadTemplate('ManagePermissions');
	$context['can_change_permissions'] = allowedTo('manage_permissions');

	// Nothing to initialize here.
	if (!$context['can_change_permissions'])
		return;

	// Permissions itself is an array of permission-name to groups that can't have it. We only actually need the list of permissions for most things here.
	$permissions = array_keys($permission_details);

	// Load the permission settings for guests
	foreach ($permissions as $permission)
		$context[$permission] = array(
			-1 => array(
				'id' => -1,
				'name' => $txt['membergroups_guests'],
				'is_postgroup' => false,
				'status' => 'off',
			),
			0 => array(
				'id' => 0,
				'name' => $txt['membergroups_members'],
				'is_postgroup' => false,
				'status' => 'off',
			),
		);

	$request = wesql::query('
		SELECT id_group, CASE WHEN add_deny = {int:denied} THEN {string:deny} ELSE {string:on} END AS status, permission
		FROM {db_prefix}permissions
		WHERE id_group IN (-1, 0)
			AND permission IN ({array_string:permissions})',
		array(
			'denied' => 0,
			'permissions' => $permissions,
			'deny' => 'deny',
			'on' => 'on',
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$context[$row['permission']][$row['id_group']]['status'] = $row['status'];
	wesql::free_result($request);

	$request = wesql::query('
		SELECT mg.id_group, mg.group_name, mg.min_posts, IFNULL(p.add_deny, -1) AS status, p.permission
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}permissions AS p ON (p.id_group = mg.id_group AND p.permission IN ({array_string:permissions}))
		WHERE mg.id_group NOT IN (1, 3)
			AND mg.id_parent = {int:not_inherited}' . (empty($settings['permission_enable_postgroups']) ? '
			AND mg.min_posts = {int:min_posts}' : '') . '
		ORDER BY mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'not_inherited' => -2,
			'min_posts' => -1,
			'newbie_group' => 4,
			'permissions' => $permissions,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		// Initialize each permission as being 'off' until proven otherwise.
		foreach ($permissions as $permission)
			if (!isset($context[$permission][$row['id_group']]))
				$context[$permission][$row['id_group']] = array(
					'id' => $row['id_group'],
					'name' => $row['group_name'],
					'is_postgroup' => $row['min_posts'] != -1,
					'status' => 'off',
				);

		$context[$row['permission']][$row['id_group']]['status'] = empty($row['status']) ? 'deny' : ($row['status'] == 1 ? 'on' : 'off');
	}
	wesql::free_result($request);

	// Firstly, deal with the case that guests do not have access.
	if (empty($settings['allow_guestAccess']))
	{
		foreach ($permissions as $permission)
			unset($context[$permission][-1]);
	}

	// Some permissions cannot be given to certain groups. Remove the groups.
	foreach ($permission_details as $permission => $excluded_groups)
	{
		if (empty($excluded_groups))
			continue;
		foreach ($excluded_groups as $group)
			unset($context[$permission][$group]);
	}
}

// Show a collapsible box to set a specific permission.
function theme_inline_permissions($permission)
{
	global $context;

	$context['current_permission'] = $permission;
	$context['member_groups'] = $context[$permission];

	template_inline_permissions();
}

// Save the permissions of a form containing inline permissions.
function save_inline_permissions($permission_details)
{
	global $context;

	// No permissions? Not a great deal to do here.
	if (!allowedTo('manage_permissions'))
		return;

	// Almighty session check, verify our ways.
	checkSession();

	// Check they can't do certain things.
	loadIllegalPermissions();

	// Now set up much as we do in the other functions.
	$permissions = array_keys($permission_details);

	$insertRows = array();
	foreach ($permissions as $permission)
	{
		if (!isset($_POST[$permission]))
			continue;

		foreach ($_POST[$permission] as $id_group => $value)
		{
			$id_group = (int) $id_group;
			if (in_array($value, array('on', 'deny')) && (empty($context['illegal_permissions']) || !in_array($permission, $context['illegal_permissions'])) && !in_array($id_group, $permission_details[$permission]))
				$insertRows[] = array($id_group, $permission, $value == 'on' ? 1 : 0);
		}
	}

	// Remove the old permissions...
	wesql::query('
		DELETE FROM {db_prefix}permissions
		WHERE permission IN ({array_string:permissions})' . (empty($context['illegal_permissions']) ? '' : '
			AND permission NOT IN ({array_string:illegal_permissions})'),
		array(
			'illegal_permissions' => !empty($context['illegal_permissions']) ? $context['illegal_permissions'] : array(),
			'permissions' => $permissions,
		)
	);

	// ...and replace them with new ones.
	if (!empty($insertRows))
		wesql::insert('',
			'{db_prefix}permissions',
			array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
			$insertRows,
			array('id_group', 'permission')
		);

	// Do a full child update.
	updateChildPermissions(array(), -1);

	// Just in case we cached this.
	updateSettings(array('settings_updated' => time()));
}

function loadPermissionProfiles()
{
	global $context, $txt;

	$request = wesql::query('
		SELECT id_profile, profile_name
		FROM {db_prefix}permission_profiles
		ORDER BY id_profile',
		array(
		)
	);
	$context['profiles'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Format the label nicely.
		if (isset($txt['permissions_profile_' . $row['profile_name']]))
			$name = $txt['permissions_profile_' . $row['profile_name']];
		else
			$name = $row['profile_name'];

		$context['profiles'][$row['id_profile']] = array(
			'id' => $row['id_profile'],
			'name' => $name,
			'can_modify' => $row['id_profile'] == 1 || $row['id_profile'] > 4,
			'unformatted_name' => $row['profile_name'],
		);
	}
	wesql::free_result($request);
}

// Add/Edit/Delete profiles.
function EditPermissionProfiles()
{
	global $context, $txt;

	// Setup the template, first for fun.
	$context['page_title'] = $txt['permissions_profile_edit'];
	wetem::load('edit_profiles');

	// If we're creating a new one do it first.
	if (isset($_POST['create']) && trim($_POST['profile_name']) != '')
	{
		checkSession();

		$_POST['copy_from'] = (int) $_POST['copy_from'];
		$_POST['profile_name'] = westr::htmlspecialchars($_POST['profile_name']);

		// Insert the profile itself.
		wesql::insert('',
			'{db_prefix}permission_profiles',
			array(
				'profile_name' => 'string',
			),
			array(
				$_POST['profile_name'],
			),
			array('id_profile')
		);
		$profile_id = wesql::insert_id();

		// Load the permissions from the one it's being copied from.
		$request = wesql::query('
			SELECT id_group, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_profile = {int:copy_from}',
			array(
				'copy_from' => $_POST['copy_from'],
			)
		);
		$inserts = array();
		while ($row = wesql::fetch_assoc($request))
			$inserts[] = array($profile_id, $row['id_group'], $row['permission'], $row['add_deny']);
		wesql::free_result($request);

		if (!empty($inserts))
			wesql::insert('',
				'{db_prefix}board_permissions',
				array('id_profile' => 'int', 'id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$inserts,
				array('id_profile', 'id_group', 'permission')
			);
	}
	// Renaming?
	elseif (isset($_POST['rename']))
	{
		checkSession();

		// Just showing the boxes?
		if (!isset($_POST['rename_profile']))
			$context['show_rename_boxes'] = true;
		else
		{
			foreach ($_POST['rename_profile'] as $id => $value)
			{
				$value = westr::htmlspecialchars($value);

				if (trim($value) != '' && $id > 4)
					wesql::query('
						UPDATE {db_prefix}permission_profiles
						SET profile_name = {string:profile_name}
						WHERE id_profile = {int:current_profile}',
						array(
							'current_profile' => (int) $id,
							'profile_name' => $value,
						)
					);
			}
		}
	}
	// Deleting?
	elseif (isset($_POST['delete']) && !empty($_POST['delete_profile']))
	{
		checkSession('post');

		$profiles = array();
		foreach ($_POST['delete_profile'] as $profile)
			if ($profile > 4)
				$profiles[] = (int) $profile;

		// Verify it's not in use...
		$request = wesql::query('
			SELECT id_board
			FROM {db_prefix}boards
			WHERE id_profile IN ({array_int:profile_list})
			LIMIT 1',
			array(
				'profile_list' => $profiles,
			)
		);
		if (wesql::num_rows($request) != 0)
			fatal_lang_error('no_access', false);
		wesql::free_result($request);

		// Oh well, delete.
		wesql::query('
			DELETE FROM {db_prefix}permission_profiles
			WHERE id_profile IN ({array_int:profile_list})',
			array(
				'profile_list' => $profiles,
			)
		);
	}

	// Clearly, we'll need this!
	loadPermissionProfiles();

	// Work out what ones are in use.
	$request = wesql::query('
		SELECT id_profile, COUNT(id_board) AS board_count
		FROM {db_prefix}boards
		GROUP BY id_profile',
		array(
		)
	);
	while ($row = wesql::fetch_assoc($request))
		if (isset($context['profiles'][$row['id_profile']]))
		{
			$context['profiles'][$row['id_profile']]['in_use'] = true;
			$context['profiles'][$row['id_profile']]['boards'] = $row['board_count'];
			$context['profiles'][$row['id_profile']]['boards_text'] = $row['board_count'] > 1 ? sprintf($txt['permissions_profile_used_by_many'], $row['board_count']) : $txt['permissions_profile_used_by_' . ($row['board_count'] ? 'one' : 'none')];
		}
	wesql::free_result($request);

	// What can we do with these?
	$context['can_edit_something'] = false;
	foreach ($context['profiles'] as $id => $profile)
	{
		// Can't delete special ones.
		$context['profiles'][$id]['can_edit'] = isset($txt['permissions_profile_' . $profile['unformatted_name']]) ? false : true;
		if ($context['profiles'][$id]['can_edit'])
			$context['can_edit_something'] = true;

		// You can only delete it if you can edit it AND it's not in use.
		$context['profiles'][$id]['can_delete'] = $context['profiles'][$id]['can_edit'] && empty($profile['in_use']) ? true : false;
	}
}

// This function updates the permissions of any groups based off this group.
function updateChildPermissions($parents, $profile = null)
{
	// All the parent groups to sort out.
	if (!is_array($parents))
		$parents = array($parents);

	// Find all the children of this group.
	$request = wesql::query('
		SELECT id_parent, id_group
		FROM {db_prefix}membergroups
		WHERE id_parent != {int:not_inherited}
			' . (empty($parents) ? '' : 'AND id_parent IN ({array_int:parent_list})'),
		array(
			'parent_list' => $parents,
			'not_inherited' => -2,
		)
	);
	$children = array();
	$parents = array();
	$child_groups = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$children[$row['id_parent']][] = $row['id_group'];
		$child_groups[] = $row['id_group'];
		$parents[] = $row['id_parent'];
	}
	wesql::free_result($request);

	$parents = array_unique($parents);

	// Not a sausage, or a child?
	if (empty($children))
		return false;

	// First off, are we doing general permissions?
	if ($profile < 1 || $profile === null)
	{
		// Fetch all the parent permissions.
		$request = wesql::query('
			SELECT id_group, permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:parent_list})',
			array(
				'parent_list' => $parents,
			)
		);
		$permissions = array();
		while ($row = wesql::fetch_assoc($request))
			foreach ($children[$row['id_group']] as $child)
				$permissions[] = array($child, $row['permission'], $row['add_deny']);
		wesql::free_result($request);

		wesql::query('
			DELETE FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:child_groups})',
			array(
				'child_groups' => $child_groups,
			)
		);

		// Finally insert.
		if (!empty($permissions))
		{
			wesql::insert('',
				'{db_prefix}permissions',
				array('id_group' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$permissions,
				array('id_group', 'permission')
			);
		}
	}

	// Then, what about board profiles?
	if ($profile != -1)
	{
		$profileQuery = $profile === null ? '' : ' AND id_profile = {int:current_profile}';

		// Again, get all the parent permissions.
		$request = wesql::query('
			SELECT id_profile, id_group, permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:parent_groups})
				' . $profileQuery,
			array(
				'parent_groups' => $parents,
				'current_profile' => $profile !== null && $profile ? $profile : 1,
			)
		);
		$permissions = array();
		while ($row = wesql::fetch_assoc($request))
			foreach ($children[$row['id_group']] as $child)
				$permissions[] = array($child, $row['id_profile'], $row['permission'], $row['add_deny']);
		wesql::free_result($request);

		wesql::query('
			DELETE FROM {db_prefix}board_permissions
			WHERE id_group IN ({array_int:child_groups})
				' . $profileQuery,
			array(
				'child_groups' => $child_groups,
				'current_profile' => $profile !== null && $profile ? $profile : 1,
			)
		);

		// Do the insert.
		if (!empty($permissions))
		{
			wesql::insert('',
				'{db_prefix}board_permissions',
				array('id_group' => 'int', 'id_profile' => 'int', 'permission' => 'string', 'add_deny' => 'int'),
				$permissions,
				array('id_group', 'id_profile', 'permission')
			);
		}
	}
}

// Load permissions someone cannot grant.
function loadIllegalPermissions()
{
	global $context;

	$context['illegal_permissions'] = array();
	if (!allowedTo('admin_forum'))
		$context['illegal_permissions'][] = 'admin_forum';
	if (!allowedTo('manage_membergroups'))
		$context['illegal_permissions'][] = 'manage_membergroups';
	if (!allowedTo('manage_permissions'))
		$context['illegal_permissions'][] = 'manage_permissions';

	call_hook('illegal_perms');
}

// Load all the permissions that can not be given to guests.
function loadIllegalGuestPermissions()
{
	global $context;

	$context['non_guest_permissions'] = array(
		'delete_replies',
		'poll_add_own',
		'pm_read',
		'pm_send',
		'save_pm_draft',
		'auto_save_pm_draft',
		'save_post_draft',
		'auto_save_post_draft',
		'post_thought',
		'profile_identity',
		'profile_extra',
		'profile_signature',
		'profile_website',
		'profile_title',
		'profile_remove',
		'profile_server_avatar',
		'profile_upload_avatar',
		'profile_remote_avatar',
		'profile_view_own',
		'mark_any_notify',
		'mark_notify',
		'admin_forum',
		'manage_boards',
		'manage_attachments',
		'manage_smileys',
		'edit_news',
		'access_mod_center',
		'moderate_forum',
		'issue_warning',
		'manage_membergroups',
		'manage_permissions',
		'manage_bans',
		'move_own',
		'modify_replies',
		'send_mail',
		'approve_posts',
		'media_access_unseen',
		'media_add_playlists',
		'media_add_user_album',
		'media_auto_approve_albums',
		'media_moderate_own_albums',
		'media_moderate',
		'media_manage',
	);

	call_hook('illegal_guest_perms');
}

?>