<?php
/**
 * Wedge
 *
 * This file provides the primary view for the board index; also known as the list of all boards, and the default home page of the forum.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
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
 * - Loads the Boards template, or alternatively uses the wireless version
 * - Defines the canonical URL of the page to be the principal forum URL (as $scripturl) in case we fell through to here (if action is one the forum is not aware of, and there is no topic or board, this action will be called)
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
	global $txt, $user_info, $modSettings, $context, $settings, $scripturl, $options;

	// For wireless, we use the Wireless template...
	if (WIRELESS)
		wetem::load('wap2_boards');
	else
	{
		loadTemplate('Boards');
		loadTemplate('InfoCenter');
		// We load the info center into our sidebar...
		wetem::load(
			array(
				'info_center' => array(
					'info_center_statistics',
					'info_center_usersonline',
					'info_center_personalmsg',
				),
			),
			'sidebar'
		);
		// And the rest into our default layer.
		wetem::load(
			array(
				'boards_ministats',
				'boards_newsfader',
				'boards',
				'boards_below',
			)
		);
	}

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl . (isset($_GET['action']) && $_GET['action'] === 'boards' ? '?action=boards' : (isset($_GET['category']) && is_integer($_GET['category']) ? '?category=' . $_GET['category'] : ''));

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
		'countChildPosts' => !empty($modSettings['countChildPosts']),
	);
	$context['categories'] = getBoardIndex($boardIndexOptions);

	// Get the user online list.
	loadSource('Subs-MembersOnline');
	$membersOnlineOptions = array(
		'show_hidden' => allowedTo('moderate_forum'),
		'sort' => 'log_time',
		'reverse_sort' => true,
	);
	$context += getMembersOnlineStats($membersOnlineOptions);

	$context['show_buddies'] = !empty($user_info['buddies']);

	// Are we showing all membergroups on the board index?
	if (!empty($settings['show_group_key']))
		$context['membergroups'] = cache_quick_get('membergroup_list', 'Subs-Membergroups', 'cache_getMembergroupList', array());

	// Track most online statistics? (Subs-MembersOnline.php)
	if (!empty($modSettings['trackStats']))
		trackStatsUsersOnline($context['num_guests'] + $context['num_spiders'] + $context['num_users_online']);

	$settings['show_member_bar'] &= allowedTo('view_mlist');
	$context['show_stats'] = allowedTo('view_stats') && !empty($modSettings['trackStats']);
	$context['show_member_list'] = allowedTo('view_mlist');
	$context['show_who'] = allowedTo('who_view') && !empty($modSettings['who_enabled']);

	$context['page_title'] = sprintf($txt['forum_index'], $context['forum_name']);

	add_js('
	var oInfoCenterToggle = new weToggle({
		bCurrentlyCollapsed: ', empty($options['collapse_header_ic']) ? 'false' : 'true', ',
		aSwappableContainers: [\'upshrinkHeaderIC\'],
		aSwapImages: [{ sId: \'upshrink_ic\', altExpanded: ' . JavaScriptEscape($txt['upshrink_description']) . ' }],
		oThemeOptions: { bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ', sOptionName: \'collapse_header_ic\' },
		oCookieOptions: { bUseCookie: ' . ($context['user']['is_guest'] ? 'true' : 'false') . ', sCookieName: \'upshrinkIC\' }
	});');

	if (empty($modSettings['display_flags']))
		$modSettings['display_flags'] = 'none';

	call_hook('info_center');
}

?>