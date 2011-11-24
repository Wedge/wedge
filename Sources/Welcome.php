<?php
/**
 * Wedge
 *
 * This file is a sample homepage that can be used in place of your board index.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	It contains only the following function:

	void Welcome()
		- prepares the homepage data.
		- uses the Welcome template (main block) and language file.
		- is accessed via index.php, if $modSettings['default_index'] == 'Welcome'.
		- to create your own custom entry point, just set $modSettings['default_index'] to 'Homepage'
		  and create your own Homepage.php file so it won't be overwritten by Wedge updates!
*/

// Welcome to the show.
function Welcome()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings, $language, $user_info;

	// Load the 'Welcome' template.
	loadTemplate('Welcome');
	// We don't have language files for now, but in case we add them...
	// loadLanguage('Welcome');

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl;

	// Do not let search engines index anything if there is a random thing in $_GET.
	if (!empty($_GET))
		$context['robot_no_index'] = true;

	// In this case, we want to load the info center as well -- but not in the sidebar.
	// We will simply create the info_center layer at the end of the main block and inject the blocks into it.
	loadTemplate('InfoCenter');
	wetem::add(
		array(
			'info' => array(
				'info_center' => array(
					'info_center_statistics',
					'info_center_usersonline',
					'info_center_personalmsg',
				),
			),
		)
	);

	/*
		Except for $context['page_title'], the rest is taken directly
		from Boards.php and used to generate the info center.
	*/

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

	$context['page_title'] = isset($txt['homepage_title']) ? $txt['homepage_title'] : sprintf($txt['forum_index'], $context['forum_name']);

	add_js('
	var oInfoCenterToggle = new weToggle({
		bCurrentlyCollapsed: ', empty($options['collapse_header_ic']) ? 'false' : 'true', ',
		aSwappableContainers: [\'upshrinkHeaderIC\'],
		aSwapImages: [{ sId: \'upshrink_ic\', altExpanded: ' . JavaScriptEscape($txt['upshrink_description']) . ' }],
		oThemeOptions: { bUseThemeSettings: ' . ($context['user']['is_guest'] ? 'false' : 'true') . ', sOptionName: \'collapse_header_ic\' },
		oCookieOptions: { bUseCookie: ' . ($context['user']['is_guest'] ? 'true' : 'false') . ', sCookieName: \'upshrinkIC\' }
	});');

	call_hook('info_center');
}

?>