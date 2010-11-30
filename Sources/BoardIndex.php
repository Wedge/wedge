<?php
/**********************************************************************************
* BoardIndex.php                                                                  *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC4                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

/**
 * This file provides the primary view, and a minor action, for the board index; also known as the list of all boards, and the default initial of the forum.
 *
 * @package wedge
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * This prepares all the data necessary for the board index.
 *
 * Unlike most actions within the forum, this action is explicitly not listed within the action array in index.php, because it is the default action; if no known action, board or topic is specified, this function will be used.
 *
 * - Loads the boardindex template, or alternatively uses the wireless version
 * - Defines the canonical URL of the page to be the principle forum URL (as $scripturl) in case we fell through to here (if action is one the forum is not aware of, and there is no topic or board, and no wrapaction caught by the theme, this action will be called)
 * - Ordinarily, the board index page will be directed to be indexed, however this is turned off in the event that $_GET is non-empty.
 * - The board list is then loaded from {@link getBoardIndex()} in Subs-BoardIndex.php.
 * - The list of online members is then loaded from {@link getMembersOnlineStats()} in Subs-MembersOnline.php.
 * - If showing the group key/membergroup legend, this will be loaded next. (Either from cache, or {@link cache_getMembergroupList()} in Subs-Membergroups.php)
 * - If we are tracking statistics, see if we are at the point of 'most online' - achieved with {@link trackStatsUsersOnline()} in Subs-MembersOnline.php.
 * - If the configuration asks for the last x latest posts, fetch them. (This is achieved from cache, or {@link cache_getLastPosts()} in Subs-Recent.php, and honors user preference of ignored boards)
 * - Preset some flags for the template (whether to show a bar above the most recent posts), and whether to show the member bar; both in the information center.
 * - Set up some general permissions checks for the template (i.e. whether to show some of the stats, whether to show the member list link)
 * - If the calendar is enabled, load the events as directed by the options (holidays, birthdays, events, all based on number of days) - this is managed from cache, or {@link cache_getRecentEvents()} in Subs-Calendar.php.
 * - Finally, set up the page title to include the board name with the localized ' - Index' string.
 */
function BoardIndex()
{
	global $txt, $user_info, $sourcedir, $modSettings, $context, $settings, $scripturl, $options;

	// For wireless, we use the Wireless template...
	if (WIRELESS)
		$context['sub_template'] = WIRELESS_PROTOCOL . '_boardindex';
	else
	{
		loadTemplate('BoardIndex');
		loadTemplate('BoardIndexInfoCenter');
		$context['sub_template'] = array(
			'boardindex_ministats',
			'boardindex_newsfader',
			'boardindex',
			'boardindex_below',
			'info_center_begin',
			'info_center_recentposts',
			'info_center_calendar',
			'info_center_statistics',
			'info_center_usersonline',
			'info_center_personalmsg',
			'info_center_end',
		);
	}

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl;

	// Do not let search engines index anything if there is a random thing in $_GET.
	if (!empty($_GET))
		$context['robot_no_index'] = true;

	// Retrieve the categories and boards.
	require_once($sourcedir . '/Subs-BoardIndex.php');
	$boardIndexOptions = array(
		'include_categories' => true,
		'base_level' => 0,
		'parent_id' => 0,
		'set_latest_post' => true,
		'countChildPosts' => !empty($modSettings['countChildPosts']),
	);
	$context['categories'] = getBoardIndex($boardIndexOptions);

	// Get the user online list.
	require_once($sourcedir . '/Subs-MembersOnline.php');
	$membersOnlineOptions = array(
		'show_hidden' => allowedTo('moderate_forum'),
		'sort' => 'log_time',
		'reverse_sort' => true,
	);
	$context += getMembersOnlineStats($membersOnlineOptions);

	$context['show_buddies'] = !empty($user_info['buddies']);

	// Are we showing all membergroups on the board index?
	if (!empty($settings['show_group_key']))
		$context['membergroups'] = cache_quick_get('membergroup_list', 'Subs-Membergroups.php', 'cache_getMembergroupList', array());

	// Track most online statistics? (Subs-MembersOnline.php)
	if (!empty($modSettings['trackStats']))
		trackStatsUsersOnline($context['num_guests'] + $context['num_spiders'] + $context['num_users_online']);

	// Retrieve the latest posts if the theme settings require it.
	if (isset($settings['number_recent_posts']) && $settings['number_recent_posts'] > 1)
	{
		$latestPostOptions = array(
			'number_posts' => $settings['number_recent_posts'],
		);
		$context['latest_posts'] = cache_quick_get('boardindex-latest_posts:' . md5($user_info['query_wanna_see_board'] . $user_info['language']), 'Subs-Recent.php', 'cache_getLastPosts', array($latestPostOptions));
	}

	$settings['display_recent_bar'] = !empty($settings['number_recent_posts']) ? $settings['number_recent_posts'] : 0;
	$settings['show_member_bar'] &= allowedTo('view_mlist');
	$context['show_stats'] = allowedTo('view_stats') && !empty($modSettings['trackStats']);
	$context['show_member_list'] = allowedTo('view_mlist');
	$context['show_who'] = allowedTo('who_view') && !empty($modSettings['who_enabled']);

	// Load the calendar?
	if (!empty($modSettings['cal_enabled']) && allowedTo('calendar_view'))
	{
		// Retrieve the calendar data (events, birthdays, holidays).
		$eventOptions = array(
			'include_holidays' => $modSettings['cal_showholidays'] > 1,
			'include_birthdays' => $modSettings['cal_showbdays'] > 1,
			'include_events' => $modSettings['cal_showevents'] > 1,
			'num_days_shown' => empty($modSettings['cal_days_for_index']) || $modSettings['cal_days_for_index'] < 1 ? 1 : $modSettings['cal_days_for_index'],
		);
		$context += cache_quick_get('calendar_index_offset_' . ($user_info['time_offset'] + $modSettings['time_offset']), 'Subs-Calendar.php', 'cache_getRecentEvents', array($eventOptions));

		// Whether one or multiple days are shown on the board index.
		$context['calendar_only_today'] = $modSettings['cal_days_for_index'] == 1;

		// This is used to show the "how-do-I-edit" help.
		$context['calendar_can_edit'] = allowedTo('calendar_edit_any');
	}
	else
		$context['show_calendar'] = false;

	$context['page_title'] = sprintf($txt['forum_index'], $context['forum_name']);

	add_js('
	var oInfoCenterToggle = new smc_Toggle({
		bToggleEnabled: true,
		bCurrentlyCollapsed: ', empty($options['collapse_header_ic']) ? 'false' : 'true', ',
		aSwappableContainers: [
			\'upshrinkHeaderIC\'
		],
		aSwapImages: [
			{
				sId: \'upshrink_ic\',
				srcExpanded: smf_images_url + \'/collapse.gif\',
				altExpanded: ' . JavaScriptEscape($txt['upshrink_description']) . ',
				srcCollapsed: smf_images_url + \'/expand.gif\',
				altCollapsed: ' . JavaScriptEscape($txt['upshrink_description']) . '
			}
		],
		oThemeOptions: {
			bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ',
			sOptionName: \'collapse_header_ic\',
			sSessionVar: ' . JavaScriptEscape($context['session_var']) . ',
			sSessionId: ' . JavaScriptEscape($context['session_id']) . '
		},
		oCookieOptions: {
			bUseCookie: ' . ($context['user']['is_guest'] ? 'true' : 'false') . ',
			sCookieName: \'upshrinkIC\'
		}
	});');
}

?>