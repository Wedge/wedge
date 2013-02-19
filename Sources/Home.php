<?php
/**
 * Wedge
 *
 * This file is a sample homepage that can be used in place of your board index.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	It contains only the following function:

	void Home()
		- prepares the homepage data.
		- uses the Home template (main block) and language file.
		- is accessed via index.php, if $settings['default_index'] == 'Home'.
		- to create your own custom entry point, just set $settings['default_index'] to 'Homepage'
		  and create your own Homepage.php file so it won't be overwritten by Wedge updates!
*/

// Welcome to the show.
function Home()
{
	global $context, $theme, $txt, $scripturl, $settings;

	// Here are a few variables to make it easy to enable or disable a feature on the default homepage...
	$context['home_show']['topics'] = true;
	$context['home_show']['thoughts'] = true;
	$context['home_show']['boards'] = true;
	$context['home_show']['info'] = true;

	// Load the 'Home' template.
	loadTemplate('Home');
	loadLanguage('Home');

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl;

	// Do not let search engines index anything if there is a random thing in $_GET.
	if (!empty($_GET))
		$context['robot_no_index'] = true;

	$context['page_title'] = isset($txt['homepage_title']) ? $txt['homepage_title'] : $context['forum_name'] . ' - ' . $txt['home'];

	/* Share any thoughts...?
	------------------------- */

	if ($context['home_show']['thoughts'])
	{
		wetem::add('thoughts');

		$request = wesql::query('
			SELECT
				h.updated, h.thought, h.id_thought, h.id_parent, h.privacy,
				h.id_member, h.id_master, h2.id_member AS id_parent_owner,
				m.real_name AS owner_name, mp.real_name AS parent_name, m.posts
			FROM {db_prefix}thoughts AS h
			LEFT JOIN {db_prefix}thoughts AS h2 ON (h.id_parent = h2.id_thought)
			LEFT JOIN {db_prefix}members AS m ON (h.id_member = m.id_member)
			LEFT JOIN {db_prefix}members AS mp ON (h2.id_member = mp.id_member)
			WHERE h.id_member = {int:me}
				OR h.privacy = {int:everyone}' . (we::$is_guest ? '' : '
				OR h.privacy = {int:members}
				OR FIND_IN_SET(' . implode(', h.privacy)
				OR FIND_IN_SET(', we::$user['groups']) . ', h.privacy)') . '
			ORDER BY h.id_thought DESC
			LIMIT {int:per_page}',
			array(
				'me' => we::$id,
				'everyone' => -3,
				'members' => 0,
				'per_page' => 10,
			)
		);

		$thoughts = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$id = $row['id_thought'];
			$mid = $row['id_master'];
			$thoughts[$row['id_thought']] = array(
				'id' => $row['id_thought'],
				'id_member' => $row['id_member'],
				'id_parent' => $row['id_parent'],
				'id_master' => $mid,
				'id_parent_owner' => $row['id_parent_owner'],
				'owner_name' => $row['owner_name'],
				'privacy' => $row['privacy'],
				'updated' => timeformat($row['updated']),
				'text' => $row['posts'] < 10 ? preg_replace('~\</?a(?:\s[^>]+)?\>(?:https?://)?~', '', parse_bbc_inline($row['thought'])) : parse_bbc_inline($row['thought']),
				'can_like' => !we::$is_guest && !empty($settings['likes_enabled']) && (!empty($settings['likes_own_posts']) || $row['id_member'] != we::$id),
			);

			$thought =& $thoughts[$row['id_thought']];
			$thought['text'] = '<span class="thought" id="thought_update' . $id . '" data-oid="' . $id . '" data-prv="' . $thought['privacy'] . '"><span>' . $thought['text'] . '</span></span>';

			if (!empty($row['id_parent_owner']))
			{
				if (empty($row['parent_name']) && !isset($txt['deleted_thought']))
					loadLanguage('Post');
				$thought['text'] = '@<a href="<URL>?action=profile;u=' . $row['id_parent_owner'] . '">' . (empty($row['parent_name']) ? $txt['deleted_thought'] : $row['parent_name']) . '</a>&gt; ' . $thought['text'];
			}
		}
		wesql::free_result($request);

		$context['thoughts'] =& $thoughts;

		if (!empty($settings['likes_enabled']) && !empty($context['thoughts']))
		{
			$ids = array_keys($context['thoughts']);
			loadSource('Display'); // Might as well reuse this, but of course no doubt we'll hive this off somewhere else in the future.
			prepareLikeContext($ids, 'think');
		}
	}

	/* Retrieve the categories and boards.
	-------------------------------------- */

	if ($context['home_show']['boards'])
	{
		// Load our block.
		loadTemplate('Boards');
		wetem::add('boards');

		loadSource('Subs-BoardIndex');
		$context['categories'] = getBoardIndex(array(
			'include_categories' => true,
			'base_level' => 0,
			'parent_id' => 0,
			'category' => 0,
			'set_latest_post' => true,
			'countChildPosts' => !empty($settings['countChildPosts']),
		));
	}

	/* Taken directly from Boards.php, used to generate the info center.
	-------------------------------------------------------------------- */

	if ($context['home_show']['info'])
	{
		// In this case, we want to load the info center -- but not in the sidebar.
		// We will simply create the info_center layer at the end of the main block and inject the blocks into it.
		wetem::add(array(
			'info_center' => array(
				'info_center_statistics',
				'info_center_usersonline',
				'info_center_personalmsg',
			),
		));

		loadTemplate('InfoCenter');
		loadSource('Subs-MembersOnline');

		// Get the user online list.
		$membersOnlineOptions = array(
			'show_hidden' => allowedTo('moderate_forum'),
			'sort' => 'log_time',
			'reverse_sort' => true,
		);
		$context += getMembersOnlineStats($membersOnlineOptions);

		$context['show_buddies'] = !empty(we::$user['buddies']);

		// Are we showing all membergroups on the board index?
		if (!empty($settings['show_group_key']))
			$context['membergroups'] = cache_quick_get('membergroup_list', 'Subs-Membergroups', 'cache_getMembergroupList', array());

		// Track most online statistics? (Subs-MembersOnline.php)
		if (!empty($settings['trackStats']))
			trackStatsUsersOnline($context['num_guests'] + $context['num_spiders'] + $context['num_users_online']);

		$theme['show_member_bar'] &= allowedTo('view_mlist');
		$context['show_stats'] = allowedTo('view_stats') && !empty($settings['trackStats']);
		$context['show_member_list'] = allowedTo('view_mlist');
		$context['show_who'] = allowedTo('who_view') && !empty($settings['who_enabled']);

		call_hook('info_center');
	}

	/* Mini-menu for thoughts.
	-------------------------- */

	if (we::$is_guest)
		return;

	$context['mini_menu']['thought'] = array();
	$context['mini_menu_items_show']['thought'] = array();
	$context['mini_menu_items']['thought'] = array(
		'lk' => array(
			'caption' => 'acme_like',
			'action' => '<URL>?action=like;thought;msg=%1%;' . $context['session_query'],
			'class' => 'like_button',
		),
		'uk' => array(
			'caption' => 'acme_unlike',
			'action' => '<URL>?action=like;thought;msg=%1%;' . $context['session_query'],
			'class' => 'unlike_button',
		),
		'cx' => array(
			'caption' => 'thome_context',
			'action' => '<URL>?action=thoughts;in=%2%#t%1%',
			'class' => 'context_button',
		),
		're' => array(
			'caption' => 'thome_reply',
			'action' => '',
			'class' => 'quote_button',
			'click' => 'return oThought.edit(%1%, %2%, true)',
		),
		'mo' => array(
			'caption' => 'thome_edit',
			'action' => '',
			'class' => 'edit_button',
			'click' => 'return oThought.edit(%1%, %2%)',
		),
		'de' => array(
			'caption' => 'thome_remove',
			'action' => '',
			'class' => 'remove_button',
			'click' => 'return ask(we_confirm, e, function (go) { if (go) oThought.remove(%1%); return false; })',
		),
	);

	foreach ($thoughts as $tho)
	{
		$menu = array();

		if ($tho['can_like'])
			$menu[] = empty($context['liked_posts'][$tho['id']]['you']) ? 'lk' : 'uk';

		$menu[] = 'cx/' . ($tho['id_master'] ? $tho['id_master'] : $tho['id']);

		if (!we::$is_guest)
			$menu[] = 're/' . $tho['id_master'];

		// Can we delete?
		if ($tho['id_member'] == we::$id || we::$is_admin)
		{
			$menu[] = 'mo/' . $tho['id_master'];
			$menu[] = 'de';
		}

		// If we can't do anything, it's not even worth recording the last message ID...
		if (!empty($menu))
		{
			$context['mini_menu']['thought'][$tho['id']] = $menu;
			$amenu = array();
			foreach ($menu as $mid => $name)
				$amenu[substr($name, 0, 2)] = true;
			$context['mini_menu_items_show']['thought'] += $amenu;
		}
	}
}
