<?php
/**
 * Wedge
 *
 * This file provides the primary view for the board index; also known as the list of all boards, and the default home page of the forum.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * This prepares all the data necessary for the board index.
 *
 * Unlike most actions within the forum, this action is explicitly not listed within the action array in index.php, because it is the default action; if no known action, board or topic is specified, this function will be used.
 *
 * - Loads the Boards template.
 * - Defines the canonical URL of the page to be the principal forum URL (from the meta reference <URL>) in case we fell through to here (if action is one the forum is not aware of, and there is no topic or board, this action will be called)
 * - Ordinarily, the board index page will be directed to be indexed, however this is turned off in the event that $_GET is non-empty.
 * - The board list is then loaded from {@link getBoardIndex()} in Subs-BoardIndex.php.
 * - The list of online members is then loaded from {@link getMembersOnlineStats()} in Subs-MembersOnline.php.
 * - If showing the group key/membergroup legend, this will be loaded next. (Either from cache, or {@link cache_getMembergroupList()} in Subs-Membergroups.php)
 * - If we are tracking statistics, see if we are at the point of 'most online' - achieved with {@link trackStatsUsersOnline()} in Subs-MembersOnline.php.
 * - If the configuration asks for the last x latest posts, fetch them. (This is achieved from cache, or {@link cache_getLastPosts()} in Subs-Recent.php, and honors user preference of ignored boards)
 * - Preset some flags for whether to show the member bar, in the information center.
 * - Set up some general permissions checks for the template (i.e. whether to show some of the stats, whether to show the member list link)
 * - Finally, set up the page title to include the board name with the localized ' - Index' string.
 */
function Boards()
{
	global $txt, $settings, $context, $theme, $options;

	loadTemplate('Boards');
	loadTemplate('InfoCenter');
	// We load the info center into our sidebar...
	wetem::add('sidebar', array(
		'info_center' => array(
			'info_center_statistics',
			'info_center_usersonline',
			'info_center_personalmsg',
		),
	));
	// And the rest into our default layer.
	wetem::load(
		array(
			'boards_ministats',
			'boards_newsfader',
			'boards',
			'boards_below',
		)
	);

	// Set a canonical URL for this page.
	$context['canonical_url'] = '<URL>' . ($context['action'] === 'boards' ? '?action=boards' : (isset($_GET['category']) && (int) $_GET['category'] ? '?category=' . $_GET['category'] : ''));

	// Do not let search engines index anything if there is a random thing in $_GET.
	if (!empty($_GET))
		$context['robot_no_index'] = true;

	// Retrieve the categories and boards.
	loadSource('Subs-BoardIndex');
	$boardIndexOptions = array(
		'include_categories' => true,
		'base_level' => 0,
		'parent_id' => 0,
		'category' => isset($_GET['category']) ? (int) $_GET['category'] : 0,
		'set_latest_post' => true,
		'countChildPosts' => !empty($settings['countChildPosts']),
	);
	$context['categories'] = getBoardIndex($boardIndexOptions);

	// Set up the linktree.
	if ($context['action'] === 'boards')
		add_linktree($txt['board_index'], '<URL>?action=boards');

	if (!empty($boardIndexOptions['category']))
		add_linktree($context['categories'][$boardIndexOptions['category']]['name'], '<URL>?category=' . $boardIndexOptions['category']);

	// Get the user online list.
	loadSource('Subs-MembersOnline');
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

	$context['page_title'] = $context['forum_name'] . ' - ' . $txt['board_index'];

	if (empty($settings['display_flags']))
		$settings['display_flags'] = 'none';

	call_hook('info_center');
}
