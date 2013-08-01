<?php
/**
 * Wedge
 *
 * Handles board and category creation, and all board configuration.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/* Manage and maintain the boards and categories of the forum.

	void ManageBoards()
		- main entry point for all the manageboards admin screens.
		- called by ?action=admin;area=manageboards.
		- checks the permissions, based on the sub-action.
		- loads the ManageBoards language file.
		- calls a function based on the sub-action.

	void ManageBoardsMain()
		- main screen showing all boards and categories.
		- called by ?action=admin;area=manageboards or ?action=admin;area=manageboards;sa=move.
		- uses the main template of the ManageBoards template.
		- requires manage_boards permission.
		- also handles the interface for moving boards.

	void EditCategory()
		- screen for editing and repositioning a category.
		- called by ?action=admin;area=manageboards;sa=cat
		- uses the modify_category block of the ManageBoards template.
		- requires manage_boards permission.
		- also used to show the confirm deletion of category screen
		  (block confirm_category_delete).

	void EditCategory2()
		- function for handling a submitted form saving the category.
		- called by ?action=admin;area=manageboards;sa=cat2
		- requires manage_boards permission.
		- also handles deletion of a category.
		- redirects to ?action=admin;area=manageboards.

	void EditBoard()
		- screen for editing and repositioning a board.
		- called by ?action=admin;area=manageboards;sa=board
		- uses the modify_board block of the ManageBoards template.
		- requires manage_boards permission.
		- also used to show the confirm deletion of category screen
		  (block confirm_board_delete).

	void EditBoard2()
		- function for handling a submitted form saving the board.
		- called by ?action=admin;area=manageboards;sa=board2
		- requires manage_boards permission.
		- also handles deletion of a board.
		- redirects to ?action=admin;area=manageboards.

	void EditBoardSettings()
		- a screen to set a few general board and category settings.
		- uses the modify_general_settings block.
*/

// The controller; doesn't do anything, just delegates.
function ManageBoards()
{
	global $context, $txt, $boards;

	// Everything's gonna need this.
	loadLanguage('ManageBoards');

	// Format: 'sub-action' => array('function', 'permission')
	$subActions = array(
		'board' => array('EditBoard', 'manage_boards'),
		'board2' => array('EditBoard2', 'manage_boards'),
		'cat' => array('EditCategory', 'manage_boards'),
		'cat2' => array('EditCategory2', 'manage_boards'),
		'main' => array('ManageBoardsMain', 'manage_boards'),
		'move' => array('ManageBoardsMain', 'manage_boards'),
		'newcat' => array('EditCategory', 'manage_boards'),
		'newboard' => array('EditBoard', 'manage_boards'),
		'settings' => array('EditBoardSettings', 'admin_forum'),
	);

	// Default to sub action 'main' or 'settings' depending on permissions.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (allowedTo('manage_boards') ? 'main' : 'settings');

	// Have you got the proper permissions?
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['boards_and_cats'],
		'help' => 'manage_boards',
		'description' => $txt['boards_and_cats_desc'],
		'tabs' => array(
			'main' => array(
			),
			'newcat' => array(
			),
			'settings' => array(
				'description' => $txt['mboards_settings_desc'],
			),
		),
	);

	$subActions[$_REQUEST['sa']][0]();
}

// The main control panel thing.
function ManageBoardsMain()
{
	global $txt, $context, $cat_tree, $boards, $boardList, $txt;

	loadTemplate('ManageBoards');

	loadSource('Subs-Boards');

	if (isset($_REQUEST['saveorder']))
	{
		checkSession();
		if (empty($_POST['cat']) || empty($_POST['board']) || !is_array($_POST['cat']) || !is_array($_POST['board']))
			obExit(false);

		// First get all the categories and all the boards. We want to try to minimise some of the work if we can help it.
		$current_cats = array();
		$current_boards = array();
		$request = wesql::query('
			SELECT id_cat, cat_order
			FROM {db_prefix}categories');
		while ($row = wesql::fetch_assoc($request))
			$current_cats[$row['id_cat']] = array('current' => $row['cat_order']);
		wesql::free_result($request);

		$request = wesql::query('
			SELECT id_board, id_cat, child_level, board_order, id_parent
			FROM {db_prefix}boards');
		while ($row = wesql::fetch_assoc($request))
			$current_boards[$row['id_board']] = $row;
		wesql::free_result($request);

		// Now crunch the categories
		$success = false;
		if (count($current_cats) == count($_POST['cat']))
		{
			$success = true;
			$current_pos = 1;
			// Incoming, cat will be cat[c1] = null because they don't have parents of any kind. So we don't care about the value.
			foreach ($_POST['cat'] as $cat_id => $dummy)
			{
				$id_cat = (int) substr($cat_id, 1);
				if ($id_cat < 1)
				{
					$success = false;
					break;
				}
				else
					$current_cats[$id_cat]['new'] = $current_pos++;
			}
		}

		// Now we do the heavy lifting of boards. This won't be pretty.
		if ($success && count($current_boards) == count($_POST['board']))
		{
			$current_pos = 1;
			$this_cat = 0;
			foreach ($_POST['board'] as $board_id => $board_parent)
			{
				$id_board = (int) substr($board_id, 1);
				if ($id_board < 1)
				{
					$success = false;
					break;
				}

				if (strpos($board_parent, 'c') === 0)
				{
					// So the parent is a category. That helps us a lot.
					$this_cat = (int) substr($board_parent, 1);
					$current_boards[$id_board]['new_id_cat'] = $this_cat;
					$current_boards[$id_board]['new_child_level'] = 0;
					$current_boards[$id_board]['new_board_order'] = $current_pos++;
					$current_boards[$id_board]['new_id_parent'] = 0;
				}
				else
				{
					$parent_board = (int) substr($board_parent, 1);

					// Since this is a child, by definition we must have hit the parent already.
					if (!isset($current_boards[$parent_board]['new_id_cat']))
					{
						$success = false;
						break;
					}
					else
						$parent_board_data = $current_boards[$parent_board];

					$current_boards[$id_board]['new_id_cat'] = $this_cat;
					$current_boards[$id_board]['new_child_level'] = $parent_board_data['new_child_level'] + 1;
					$current_boards[$id_board]['new_board_order'] = $current_pos++;
					$current_boards[$id_board]['new_id_parent'] = $parent_board;
				}
			}
		}

		// All done?
		if ($success)
		{
			// First go through and find (and update) any categories that are now different.
			foreach ($current_cats as $id_cat => $cat_details)
				if ($cat_details['current'] != $cat_details['new'])
					wesql::query('
						UPDATE {db_prefix}categories
						SET cat_order = {int:cat_order}
						WHERE id_cat = {int:id_cat}',
						array(
							'cat_order' => $cat_details['new'],
							'id_cat' => $id_cat,
						)
					);

			$items = array('id_cat', 'child_level', 'board_order', 'id_parent');
			foreach ($current_boards as $id_board => $board_details)
			{
				// For each board, figure out if any one of the four columns has changed.
				$changed = false;
				foreach ($items as $item)
					if ($board_details[$item] != $board_details['new_' . $item])
						$changed = true;

				if ($changed)
					wesql::query('
						UPDATE {db_prefix}boards
						SET
							id_cat = {int:new_id_cat},
							child_level = {int:new_child_level},
							board_order = {int:new_board_order},
							id_parent = {int:new_id_parent}
						WHERE id_board = {int:id_board}',
						$board_details
					);
			}
		}

		obExit(false);
	}

	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'move' && in_array($_REQUEST['move_to'], array('child', 'before', 'after', 'top')))
	{
		checkSession('get');
		if ($_REQUEST['move_to'] === 'top')
			$boardOptions = array(
				'move_to' => $_REQUEST['move_to'],
				'target_category' => (int) $_REQUEST['target_cat'],
				'move_first_child' => true,
			);
		else
			$boardOptions = array(
				'move_to' => $_REQUEST['move_to'],
				'target_board' => (int) $_REQUEST['target_board'],
				'move_first_child' => true,
			);
		modifyBoard((int) $_REQUEST['src_board'], $boardOptions);
	}

	getBoardTree();

	$context['move_board'] = !empty($_REQUEST['move']) && isset($boards[(int) $_REQUEST['move']]) ? (int) $_REQUEST['move'] : 0;

	$context['categories'] = array();
	foreach ($cat_tree as $catid => $tree)
	{
		$context['categories'][$catid] = array(
			'name' => &$tree['node']['name'],
			'id' => &$tree['node']['id'],
			'boards' => array()
		);
		$move_cat = !empty($context['move_board']) && $boards[$context['move_board']]['category'] == $catid;
		foreach ($boardList[$catid] as $boardid)
		{
			$context['categories'][$catid]['boards'][$boardid] = array(
				'id' => &$boards[$boardid]['id'],
				'name' => &$boards[$boardid]['name'],
				'description' => &$boards[$boardid]['description'],
				'child_level' => &$boards[$boardid]['level'],
				'move' => $move_cat && ($boardid == $context['move_board'] || isChildOf($boardid, $context['move_board'])),
				'permission_profile' => &$boards[$boardid]['profile'],
				'language' => &$boards[$boardid]['language'],
			);
		}
	}

	if (!empty($context['move_board']))
	{
		$context['move_title'] = sprintf($txt['mboards_select_destination'], htmlspecialchars($boards[$context['move_board']]['name']));
		foreach ($cat_tree as $catid => $tree)
		{
			$prev_child_level = 0;
			$prev_board = 0;
			$stack = array();
			foreach ($boardList[$catid] as $boardid)
			{
				if (!isset($context['categories'][$catid]['move_link']))
					$context['categories'][$catid]['move_link'] = array(
						'child_level' => 0,
						'label' => $txt['mboards_order_before'] . ' \'' . htmlspecialchars($boards[$boardid]['name']) . '\'',
						'href' => '<URL>?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_board=' . $boardid . ';move_to=before;' . $context['session_query'],
					);

				if (!$context['categories'][$catid]['boards'][$boardid]['move'])
				$context['categories'][$catid]['boards'][$boardid]['move_links'] = array(
					array(
						'child_level' => $boards[$boardid]['level'],
						'label' => $txt['mboards_order_after'] . '\'' . htmlspecialchars($boards[$boardid]['name']) . '\'',
						'href' => '<URL>?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_board=' . $boardid . ';move_to=after;' . $context['session_query'],
					),
					array(
						'child_level' => $boards[$boardid]['level'] + 1,
						'label' => $txt['mboards_order_child_of'] . ' \'' . htmlspecialchars($boards[$boardid]['name']) . '\'',
						'href' => '<URL>?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_board=' . $boardid . ';move_to=child;' . $context['session_query'],
					),
				);

				$difference = $boards[$boardid]['level'] - $prev_child_level;
				if ($difference == 1)
					array_push($stack, !empty($context['categories'][$catid]['boards'][$prev_board]['move_links']) ? array_shift($context['categories'][$catid]['boards'][$prev_board]['move_links']) : null);
				elseif ($difference < 0)
				{
					if (empty($context['categories'][$catid]['boards'][$prev_board]['move_links']))
						$context['categories'][$catid]['boards'][$prev_board]['move_links'] = array();
					for ($i = 0; $i < -$difference; $i++)
						if (($temp = array_pop($stack)) != null)
							array_unshift($context['categories'][$catid]['boards'][$prev_board]['move_links'], $temp);
				}

				$prev_board = $boardid;
				$prev_child_level = $boards[$boardid]['level'];

			}
			if (!empty($stack) && !empty($context['categories'][$catid]['boards'][$prev_board]['move_links']))
				$context['categories'][$catid]['boards'][$prev_board]['move_links'] = array_merge($stack, $context['categories'][$catid]['boards'][$prev_board]['move_links']);
			elseif (!empty($stack))
				$context['categories'][$catid]['boards'][$prev_board]['move_links'] = $stack;

			if (empty($boardList[$catid]))
				$context['categories'][$catid]['move_link'] = array(
					'child_level' => 0,
					'label' => $txt['mboards_order_before'] . ' \'' . htmlspecialchars($tree['node']['name']) . '\'',
					'href' => '<URL>?action=admin;area=manageboards;sa=move;src_board=' . $context['move_board'] . ';target_cat=' . $catid . ';move_to=top;' . $context['session_query'],
				);
		}
	}

	$context['page_title'] = $txt['boards_and_cats'];
	$context['can_manage_permissions'] = allowedTo('manage_permissions');

	if (!$context['move_board'])
	{
		// getBoardTree makes $cat_tree with all that hierarchy. But it's a pain to get rid of what we don't want, bah.
		$context['board_list'] = &$cat_tree;

		wetem::load('board_list');
		add_jquery_ui();
		add_js_file('scripts/jquery.mjs.nestedSortable.js');
	}
}

// Modify a specific category.
function EditCategory()
{
	global $txt, $context, $cat_tree, $boardList, $boards;

	loadTemplate('ManageBoards');
	loadSource('Subs-Boards');
	getBoardTree();

	// id_cat must be a number.... if it exists.
	$_REQUEST['cat'] = isset($_REQUEST['cat']) ? (int) $_REQUEST['cat'] : 0;

	// Start with one - "In first place".
	$context['category_order'] = array(
		array(
			'id' => 0,
			'name' => $txt['mboards_order_first'],
			'selected' => !empty($_REQUEST['cat']) ? $cat_tree[$_REQUEST['cat']]['is_first'] : false,
			'true_name' => ''
		)
	);

	// If this is a new category set up some defaults.
	if ($_REQUEST['sa'] == 'newcat')
	{
		$context['category'] = array(
			'id' => 0,
			'name' => $txt['mboards_new_cat_name'],
			'editable_name' => htmlspecialchars($txt['mboards_new_cat_name']),
			'can_collapse' => true,
			'is_new' => true,
			'is_empty' => true
		);
	}
	// Category doesn't exist, man... sorry.
	elseif (!isset($cat_tree[$_REQUEST['cat']]))
		redirectexit('action=admin;area=manageboards');
	else
	{
		$context['category'] = array(
			'id' => $_REQUEST['cat'],
			'name' => $cat_tree[$_REQUEST['cat']]['node']['name'],
			'editable_name' => htmlspecialchars($cat_tree[$_REQUEST['cat']]['node']['name']),
			'can_collapse' => !empty($cat_tree[$_REQUEST['cat']]['node']['can_collapse']),
			'children' => array(),
			'is_empty' => empty($cat_tree[$_REQUEST['cat']]['children'])
		);

		foreach ($boardList[$_REQUEST['cat']] as $child_board)
			$context['category']['children'][] = str_repeat('-', $boards[$child_board]['level']) . ' ' . $boards[$child_board]['name'];
	}

	$prevCat = 0;
	foreach ($cat_tree as $catid => $tree)
	{
		if ($catid == $_REQUEST['cat'] && $prevCat > 0)
			$context['category_order'][$prevCat]['selected'] = true;
		elseif ($catid != $_REQUEST['cat'])
			$context['category_order'][$catid] = array(
				'id' => $catid,
				'name' => $txt['mboards_order_after'] . $tree['node']['name'],
				'selected' => false,
				'true_name' => $tree['node']['name']
			);
		$prevCat = $catid;
	}
	if (!isset($_REQUEST['delete']))
	{
		wetem::load('modify_category');
		$context['page_title'] = $_REQUEST['sa'] == 'newcat' ? $txt['mboards_new_cat_name'] : $txt['catEdit'];
	}
	else
	{
		wetem::load('confirm_category_delete');
		$context['page_title'] = $txt['mboards_delete_cat'];
	}
}

// Complete the modifications to a specific category.
function EditCategory2()
{
	checkSession();

	loadSource('Subs-Categories');

	$_POST['cat'] = (int) $_POST['cat'];

	// Add a new category or modify an existing one..
	if (isset($_POST['edit']) || isset($_POST['add']))
	{
		$catOptions = array();

		if (isset($_POST['cat_order']))
			$catOptions['move_after'] = (int) $_POST['cat_order'];

		// Change "This & That" to "This &amp; That" but don't change "&cent" to "&amp;cent;"...
		$catOptions['cat_name'] = preg_replace('~[&]([^;]{8}|[^;]{0,8}$)~', '&amp;$1', $_POST['cat_name']);

		$catOptions['is_collapsible'] = isset($_POST['collapse']);

		if (isset($_POST['add']))
			createCategory($catOptions);
		else
			modifyCategory($_POST['cat'], $catOptions);
	}
	// If they want to delete - first give them confirmation.
	elseif (isset($_POST['delete']) && !isset($_POST['confirmation']) && !isset($_POST['empty']))
	{
		EditCategory();
		return;
	}
	// Delete the category!
	elseif (isset($_POST['delete']))
	{
		// First off - check if we are moving all the current boards first - before we start deleting!
		if (isset($_POST['delete_action']) && $_POST['delete_action'] == 1)
		{
			if (empty($_POST['cat_to']))
				fatal_lang_error('mboards_delete_error');

			deleteCategories(array($_POST['cat']), (int) $_POST['cat_to']);
		}
		else
			deleteCategories(array($_POST['cat']));
	}

	redirectexit('action=admin;area=manageboards');
}

// Modify a specific board...
// !!! To do:
//		- Make sure no one uses the default pretty URL...
//		- Silently make a default pretty board URL if the feature is disabled (we might need it later)
function EditBoard()
{
	global $txt, $context, $cat_tree, $boards, $boardList, $settings;

	loadTemplate('ManageBoards');
	loadSource('Subs-Boards');
	getBoardTree(true);

	// For editing the profile we'll need this.
	loadLanguage('ManagePermissions');
	loadSource('ManagePermissions');
	loadPermissionProfiles();

	// Load available subdomains
	$request = wesql::query('
		SELECT url
		FROM {db_prefix}boards
		WHERE id_owner = {int:user_id}',
		array('user_id' => (int) we::$id)
	);
	$subdomains = array(0 => $_SERVER['HTTP_HOST']);
	while ($row = wesql::fetch_row($request))
		$subdomains[] = ($subdo = substr($row[0], 0, strpos($row[0], '/'))) ? $subdo : $row[0];
	wesql::free_result($request);
	// !!! @todo: Should we allow users to create boards using their profile URL as root?
	//	$subdomains[] = 'my.' . $_SERVER['HTTP_HOST'] . '/' . we::$user['username'];
	$subdomains = array_unique($subdomains);

	// id_board must be a number....
	$_REQUEST['boardid'] = isset($_REQUEST['boardid']) ? (int) $_REQUEST['boardid'] : 0;
	if (!isset($boards[$_REQUEST['boardid']]))
	{
		$_REQUEST['boardid'] = 0;
		$_REQUEST['sa'] = 'newboard';
	}

	if ($_REQUEST['sa'] == 'newboard')
	{
		// Category doesn't exist, man... Sorry.
		if (empty($_REQUEST['cat']))
			redirectexit('action=admin;area=manageboards');

		// Some things that need to be setup for a new board.
		$curBoard = array(
			'category' => (int) $_REQUEST['cat']
		);
		$context['board_order'] = array();
		$context['board'] = array(
			'is_new' => true,
			'id' => 0,
			'name' => $txt['mboards_new_board_name'],
			'description' => '',
			'count_posts' => 1,
			'posts' => 0,
			'topics' => 0,
			'theme' => 0,
			'skin' => '',
			'profile' => 1,
			'override_theme' => 0,
			'redirect' => '',
			'redirect_newtab' => 0,
			'url' => $_SERVER['HTTP_HOST'] . '/enter-a-name',
			'category' => (int) $_REQUEST['cat'],
			'no_children' => true,
			'language' => '',
			'offlimits_msg' => '',
		);
	}
	else
	{
		// Just some easy shortcuts.
		$curBoard =& $boards[$_REQUEST['boardid']];
		$context['board'] = $boards[$_REQUEST['boardid']];
		$context['board']['name'] = westr::safe($context['board']['name'], ENT_COMPAT, false);
		$context['board']['description'] = westr::safe($context['board']['description'], ENT_COMPAT, false);
		$context['board']['offlimits_msg'] = westr::safe($context['board']['offlimits_msg'], ENT_COMPAT, false);
		$context['board']['no_children'] = empty($boards[$_REQUEST['boardid']]['tree']['children']);
		$context['board']['is_recycle'] = !empty($settings['recycle_enable']) && !empty($settings['recycle_board']) && $settings['recycle_board'] == $context['board']['id'];
	}
	$context['board']['subdomains'] = $subdomains;

	// As we may have come from the permissions screen keep track of where we should go on save.
	$context['redirect_location'] = isset($_GET['rid']) && $_GET['rid'] == 'permissions' ? 'permissions' : 'boards';

	// We might need this to hide links to certain areas.
	$context['can_manage_permissions'] = allowedTo('manage_permissions');

	// Default membergroups.
	$context['groups'] = array(
		-1 => array(
			'id' => '-1',
			'name' => $txt['parent_guests_only'],
			'is_post_group' => false,
		),
		0 => array(
			'id' => '0',
			'name' => $txt['parent_members_only'],
			'is_post_group' => false,
		)
	);

	// Load membergroups.
	$request = wesql::query('
		SELECT group_name, id_group, min_posts, online_color
		FROM {db_prefix}membergroups
		WHERE id_group > {int:moderator_group} OR id_group = {int:global_moderator}
		ORDER BY min_posts, id_group != {int:global_moderator}, group_name',
		array(
			'moderator_group' => 3,
			'global_moderator' => 2,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$context['groups'][(int) $row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => !empty($row['online_color']) ? '<span style="color:' . $row['online_color'] . '">' . trim($row['group_name']) . '</span>' : trim($row['group_name']),
			'is_post_group' => $row['min_posts'] != -1,
		);
	}
	wesql::free_result($request);

	// For new boards, we need to simply set up defaults for each of the groups
	$context['view_enter_same'] = true;
	$context['need_deny_perm'] = false;
	if ($_REQUEST['sa'] == 'newboard')
	{
		foreach ($context['groups'] as $id_group => $details)
			$context['groups'][$id_group] += array(
				'view_perm' => !$details['is_post_group'] ? 'allow' : 'disallow',
				'enter_perm' => !$details['is_post_group'] ? 'allow' : 'disallow',
			);
	}
	else
	{
		$query = wesql::query('
			SELECT id_group, view_perm, enter_perm
			FROM {db_prefix}board_groups
			WHERE id_board = {int:board}',
			array(
				'board' => $_REQUEST['boardid'],
			)
		);
		while ($row = wesql::fetch_assoc($query))
		{
			$context['groups'][(int) $row['id_group']]['view_perm'] = $row['view_perm'];
			$context['groups'][(int) $row['id_group']]['enter_perm'] = $row['enter_perm'];
			if ($row['view_perm'] != $row['enter_perm'])
				$context['view_enter_same'] = false;
			if ($row['view_perm'] == 'deny' || $row['enter_perm'] == 'deny')
				$context['need_deny_perm'] = true;
		}
		wesql::free_result($query);

		// Go through and fix up any missing items
		foreach ($context['groups'] as $id_group => $group)
		{
			if (!isset($group['view_perm']))
				$context['groups'][$id_group]['view_perm'] = 'disallow';
			if (!isset($group['enter_perm']))
				$context['groups'][$id_group]['enter_perm'] = 'disallow';
		}
	}

	if (empty($settings['allow_guestAccess']))
		unset($context['groups'][-1]);

	// Category doesn't exist, man... sorry.
	if (!isset($boardList[$curBoard['category']]))
		redirectexit('action=admin;area=manageboards');

	foreach ($boardList[$curBoard['category']] as $boardid)
	{
		if ($boardid == $_REQUEST['boardid'])
		{
			$context['board_order'][] = array(
				'id' => $boardid,
				'name' => str_repeat('-', $boards[$boardid]['level']) . ' (' . $txt['mboards_current_position'] . ')',
				'children' => $boards[$boardid]['tree']['children'],
				'no_children' => empty($boards[$boardid]['tree']['children']),
				'is_child' => false,
				'selected' => true
			);
		}
		else
		{
			$context['board_order'][] = array(
				'id' => $boardid,
				'name' => str_repeat('-', $boards[$boardid]['level']) . ' ' . $boards[$boardid]['name'],
				'is_child' => empty($_REQUEST['boardid']) ? false : isChildOf($boardid, $_REQUEST['boardid']),
				'selected' => false
			);
		}
	}

	// Are there any places to move child boards to in the case where we are confirming a delete?
	if (!empty($_REQUEST['boardid']))
	{
		$context['can_move_children'] = false;
		$context['children'] = $boards[$_REQUEST['boardid']]['tree']['children'];
		foreach ($context['board_order'] as $board)
			if ($board['is_child'] == false && $board['selected'] == false)
				$context['can_move_children'] = true;
	}

	// Get other available categories.
	$context['categories'] = array();
	foreach ($cat_tree as $catID => $tree)
		$context['categories'][] = array(
			'id' => $catID == $curBoard['category'] ? 0 : $catID,
			'name' => $tree['node']['name'],
			'selected' => $catID == $curBoard['category']
		);

	$request = wesql::query('
		SELECT mem.id_member, mem.real_name
		FROM {db_prefix}moderators AS mods
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
		WHERE mods.id_board = {int:current_board}',
		array(
			'current_board' => $_REQUEST['boardid'],
		)
	);
	$context['board']['moderators'] = array();
	while ($row = wesql::fetch_assoc($request))
		$context['board']['moderators'][$row['id_member']] = $row['real_name'];
	wesql::free_result($request);

	$context['board']['moderator_list'] = empty($context['board']['moderators']) ? '' : '&quot;' . implode('&quot;, &quot;', $context['board']['moderators']) . '&quot;';

	if (!empty($context['board']['moderators']))
		list ($context['board']['last_moderator_id']) = array_slice(array_keys($context['board']['moderators']), -1);

	// Get all the languages
	getLanguages();

	// Get all the themes...
	$request = wesql::query('
		SELECT id_theme AS id, value AS name
		FROM {db_prefix}themes
		WHERE variable = {literal:name}'
	);
	$context['themes'] = array();
	while ($row = wesql::fetch_assoc($request))
		$context['themes'][$row['id']] = $row;
	wesql::free_result($request);

	// Get theme dir for all themes
	loadSource('Themes');

	$request = wesql::query('
		SELECT id_theme AS id, value AS dir
		FROM {db_prefix}themes
		WHERE variable = {literal:theme_dir}'
	);
	while ($row = wesql::fetch_assoc($request))
		$context['themes'][$row['id']]['skins'] = wedge_get_skin_list($row['dir'] . '/skins');
	wesql::free_result($request);

	if (!isset($_REQUEST['delete']))
	{
		wetem::load('modify_board');
		$context['page_title'] = $txt['boardsEdit'];
	}
	else
	{
		wetem::load('confirm_board_delete');
		$context['page_title'] = $txt['mboards_delete_board'];
	}
}

// Make changes to/delete a board.
function EditBoard2()
{
	global $txt, $settings, $context;

	checkSession();

	loadSource('Subs-Boards');

	$_POST['boardid'] = (int) $_POST['boardid'];

	// Mode: modify aka. don't delete.
	if (isset($_POST['edit']) || isset($_POST['add']))
	{
		$boardOptions = array();

		// Move this board to a new category?
		if (!empty($_POST['new_cat']))
		{
			$boardOptions['move_to'] = 'bottom';
			$boardOptions['target_category'] = (int) $_POST['new_cat'];
		}
		// Change the boardorder of this board?
		elseif (!empty($_POST['placement']) && !empty($_POST['board_order']))
		{
			if (!in_array($_POST['placement'], array('before', 'after', 'child')))
				fatal_lang_error('mangled_post', false);

			$boardOptions['move_to'] = $_POST['placement'];
			$boardOptions['target_board'] = (int) $_POST['board_order'];
		}

		$theme_array = explode('_', $_POST['boardtheme']);
		$boardOptions['board_theme'] = (int) $theme_array[0];
		$boardOptions['board_skin'] = empty($theme_array[1]) ? 'skins' : base64_decode($theme_array[1]);

		// Checkboxes....
		$boardOptions['posts_count'] = isset($_POST['count']);
		$boardOptions['override_theme'] = isset($_POST['override_theme']);

		// We're going to need the list of groups for this.
		$boardOptions['access'] = array(
			-1 => array('id_group' => -1, 'view_perm' => 'disallow', 'enter_perm' => 'disallow'),
			0 => array('id_group' => 0, 'view_perm' => 'disallow', 'enter_perm' => 'disallow'),
		);
		$request = wesql::query('
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE id_group > {int:admin_group} AND id_group != {int:moderator}',
			array(
				'moderator' => 3,
				'admin_group' => 1,
			)
		);

		while ($row = wesql::fetch_assoc($request))
			$boardOptions['access'][$row['id_group']] = array('id_group' => $row['id_group'], 'view_perm' => 'disallow', 'enter_perm' => 'disallow');
		wesql::free_result($request);

		if (!empty($_POST['viewgroup']) && is_array($_POST['viewgroup']))
		{
			foreach ($_POST['viewgroup'] as $id_group => $access)
			{
				if (!isset($boardOptions['access'][$id_group]))
					continue;
				if ((empty($_POST['need_deny_perm']) && $access == 'deny') || ($access != 'deny' && $access != 'allow'))
					$access = 'disallow';

				$boardOptions['access'][$id_group]['view_perm'] = $access;
			}
		}

		// If the enter rules are the same as the view rules, we do not care what $_POST has.
		if (!empty($_POST['view_enter_same']))
		{
			foreach ($boardOptions['access'] as $id_group => $access)
				$boardOptions['access'][$id_group]['enter_perm'] = $access['view_perm'];
			// If they're the same, there's no boards to be showing the 'this is off limits to you' message on, so clear it out to save space
			$boardOptions['offlimits_msg'] = '';
		}
		elseif (!empty($_POST['entergroup']) && is_array($_POST['entergroup']))
		{
			foreach ($_POST['entergroup'] as $id_group => $access)
			{
				if (!isset($boardOptions['access'][$id_group]))
					continue;

				if ((empty($_POST['need_deny_perm']) && $access == 'deny') || ($access != 'deny' && $access != 'allow'))
					$access = 'disallow';

				$boardOptions['access'][$id_group]['enter_perm'] = $access;
			}

			$boardOptions['offlimits_msg'] = !empty($_POST['offlimits_msg']) ? preg_replace('~&(?!amp;)~', '&amp;', $_POST['offlimits_msg']) : '';
		}

		if (empty($settings['allow_guestAccess']))
			unset($boardOptions['access'][-1]);

		// Change '1 & 2' to '1 &amp; 2', but not '&amp;' to '&amp;amp;'...
		$boardOptions['board_name'] = preg_replace('~&(?!amp;)~', '&amp;', $_POST['board_name']);
		$boardOptions['board_description'] = preg_replace('~&(?!amp;)~', '&amp;', $_POST['desc']);
		if (!empty($settings['pretty_filters']['boards']))
		{
			$boardOptions['pretty_url'] = $_POST['pretty_url'];
			$boardOptions['pretty_url_dom'] = $_POST['pretty_url_dom'];
		}

		$boardOptions['moderator_string'] = !empty($_POST['moderators']) ? $_POST['moderators'] : '';

		if (isset($_POST['moderator_list']) && is_array($_POST['moderator_list']))
		{
			$moderators = array();
			foreach ($_POST['moderator_list'] as $moderator)
				$moderators[(int) $moderator] = (int) $moderator;
			$boardOptions['moderators'] = $moderators;
		}

		// Are they doing redirection?
		$boardOptions['redirect'] = !empty($_POST['redirect_enable']) && isset($_POST['redirect_address']) && trim($_POST['redirect_address']) != '' ? trim($_POST['redirect_address']) : '';
		// If they are, do they want it in a new tab?
		$boardOptions['redirect_newtab'] = $boardOptions['redirect'] && isset($_POST['redirect_newtab']);

		// What about a language?
		$boardOptions['language'] = !empty($_POST['language']) ? preg_replace('~[^a-z0-9\-\_]~i', '', $_POST['language']) : '';

		// Profiles...
		$boardOptions['profile'] = $_POST['profile'];
		$boardOptions['inherit_permissions'] = $_POST['profile'] == -1;

		// We need to know what used to be case in terms of redirection.
		if (!empty($_POST['boardid']))
		{
			$request = wesql::query('
				SELECT redirect, num_posts
				FROM {db_prefix}boards
				WHERE id_board = {int:current_board}',
				array(
					'current_board' => $_POST['boardid'],
				)
			);
			list ($oldRedirect, $numPosts) = wesql::fetch_row($request);
			wesql::free_result($request);

			// If we're turning redirection on check the board doesn't have posts in it - if it does don't make it a redirection board.
			if ($boardOptions['redirect'] && empty($oldRedirect) && $numPosts)
				unset($boardOptions['redirect']);
			// Reset the redirection count when switching on/off.
			elseif (empty($boardOptions['redirect']) != empty($oldRedirect))
				$boardOptions['num_posts'] = 0;
			// Resetting the count?
			elseif ($boardOptions['redirect'] && !empty($_POST['reset_redirect']))
				$boardOptions['num_posts'] = 0;

		}

		// Create a new board...
		if (isset($_POST['add']))
		{
			// New boards by default go to the bottom of the category.
			if (empty($_POST['new_cat']))
				$boardOptions['target_category'] = (int) $_POST['cur_cat'];
			if (!isset($boardOptions['move_to']))
				$boardOptions['move_to'] = 'bottom';

			createBoard($boardOptions);
		}

		// ...or update an existing board.
		else
			modifyBoard($_POST['boardid'], $boardOptions);
	}
	elseif (isset($_POST['delete']) && !isset($_POST['confirmation']) && !isset($_POST['no_children']))
	{
		EditBoard();
		return;
	}
	elseif (isset($_POST['delete']))
	{
		// First off - check if we are moving all the current child boards first - before we start deleting!
		if (isset($_POST['delete_action']) && $_POST['delete_action'] == 1)
		{
			if (empty($_POST['board_to']))
				fatal_lang_error('mboards_delete_board_error');

			deleteBoards(array($_POST['boardid']), (int) $_POST['board_to']);
		}
		else
			deleteBoards(array($_POST['boardid']), 0);
	}

	if (isset($_REQUEST['rid']) && $_REQUEST['rid'] == 'permissions')
		redirectexit('action=admin;area=permissions;sa=board;' . $context['session_query']);
	else
		redirectexit('action=admin;area=manageboards');
}

function EditBoardSettings($return_config = false)
{
	global $context, $txt, $settings;

	// Load the boards list - for the recycle bin!
	$recycle_boards = array('');
	$request = wesql::query('
		SELECT b.id_board, b.name AS board_name, c.name AS cat_name
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE redirect = {string:empty}',
		array(
			'empty' => '',
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$recycle_boards[$row['id_board']] = $row['cat_name'] . ' - ' . $row['board_name'];
	wesql::free_result($request);

	// Now the category list for the moderation area.
	$cat_list = array(
		-1 => $txt['modcenter_category_first'],
	);
	$request = wesql::query('
		SELECT cat_order, name
		FROM {db_prefix}categories
		ORDER BY cat_order');
	while ($row = wesql::fetch_assoc($request))
		$cat_list[$row['cat_order']] = sprintf($txt['modcenter_category_after'], $row['name']);
	wesql::free_result($request);
	if (!isset($settings['modcenter_category']))
		$settings['modcenter_category'] = -1;

	$context['page_title'] = $txt['boards_and_cats'] . ' - ' . $txt['settings'];

	// Here and the board settings...
	$config_vars = array(
		array('title', 'settings'),
			// Inline permissions.
			array('permissions', 'manage_boards', 'exclude' => array(-1)),
		'',
			// Other board settings.
			array('check', 'countChildPosts'),
			array('check', 'recycle_enable', 'onclick' => '$(\'#recycle_board\').prop(\'disabled\', !this.checked);'),
			array('select', 'recycle_board', $recycle_boards),
			array('boards', 'ignorable_boards'),
		'',
			array('select', 'display_flags', array('none' => $txt['flags_none'], 'specified' => $txt['flags_specified'], 'all' => $txt['flags_all'])),
		'',
			array('select', 'modcenter_category', $cat_list),
	);

	if ($return_config)
		return $config_vars;

	// Needed for the settings template and inline permission functions.
	loadSource(array('ManagePermissions', 'ManageServer'));

	// Don't let guests have these permissions.
	$context['post_url'] = '<URL>?action=admin;area=manageboards;save;sa=settings';

	loadTemplate('ManageBoards');
	wetem::load('show_settings');

	// Add some JavaScript stuff for the recycle box.
	add_js('
	$("#recycle_board").prop("disabled", !$("#recycle_enable").is(":checked"));');

	// Warn the admin against selecting the recycle topic without selecting a board.
	$context['force_form_onsubmit'] = 'if ($(\'#recycle_enable\').is(\':checked\') && $(\'#recycle_board\').val() == 0) { return ask(' . JavaScriptEscape($txt['recycle_board_unselected_notice']) . ', e); } return true;';

	// Doing a save?
	if (isset($_GET['save']))
	{
		checkSession();

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=manageboards;sa=settings');
	}

	// Prepare the settings...
	prepareDBSettingContext($config_vars);
}
