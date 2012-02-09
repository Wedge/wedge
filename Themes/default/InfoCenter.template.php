<?php
/**
 * Wedge
 *
 * Outputs the "info center" information suitable for the sidebar.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Show statistical style information...
function template_info_center_statistics()
{
	global $context, $theme, $options, $txt, $scripturl, $settings;

	if ($theme['show_stats_index'])
		echo '
	<section class="ic">
		<we:title>
			<a href="', $scripturl, '?action=stats"><img src="', $theme['images_url'], '/icons/info.gif" alt="', $txt['forum_stats'], '"></a>
			', $txt['forum_stats'], '
		</we:title>
		<ul class="stats">
			<li>', $context['common_stats']['total_posts'], ' ', $txt['posts_made'], ' ', $txt['in'], ' ', $context['common_stats']['total_topics'], ' ', $txt['topics'], ' ', $txt['by'], ' ', $context['common_stats']['total_members'], ' ', $txt['members'], '.</li>', !empty($theme['show_latest_member']) ? '
			<li>' . $txt['latest_member'] . ': <strong> ' . $context['common_stats']['latest_member']['link'] . '</strong></li>' : '', !empty($context['latest_post']) ? '
			<li>' . $txt['latest_post'] . ': <strong>&quot;' . $context['latest_post']['link'] . '&quot;</strong> (' . $context['latest_post']['time'] . ')</li>' : '', '
			<li><a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a></li>', $context['show_stats'] ? '
			<li><a href="' . $scripturl . '?action=stats">' . $txt['more_stats'] . '</a></li>' : '', '
		</ul>
	</section>';
}

function template_info_center_usersonline()
{
	global $context, $theme, $options, $txt, $scripturl, $settings;

	// "Users online" - in order of activity.
	echo '
	<section class="ic">
		<we:title>
			', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', '<img src="', $theme['images_url'], '/icons/online.gif', '" alt="', $txt['online_users'], '">', $context['show_who'] ? '</a>' : '', '
			', $txt['online_users'], '
		</we:title>
		<p class="inline stats">
			', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', comma_format($context['num_guests']), ' ', $context['num_guests'] == 1 ? $txt['guest'] : $txt['guests'], ', ' . comma_format($context['num_users_online']), ' ', $context['num_users_online'] == 1 ? $txt['user'] : $txt['users'];

	// Handle hidden users and buddies.
	$bracketList = array();
	if ($context['show_buddies'])
		$bracketList[] = comma_format($context['num_buddies']) . ' ' . ($context['num_buddies'] == 1 ? $txt['buddy'] : $txt['buddies']);
	if (!empty($context['num_spiders']))
		$bracketList[] = comma_format($context['num_spiders']) . ' ' . ($context['num_spiders'] == 1 ? $txt['spider'] : $txt['spiders']);
	if (!empty($context['num_users_hidden']))
		$bracketList[] = comma_format($context['num_users_hidden']) . ' ' . $txt['hidden'];

	if (!empty($bracketList))
		echo ' (' . implode(', ', $bracketList) . ')';

	echo $context['show_who'] ? '</a>' : '', '
		</p>
		<p class="inline">';

	// Assuming there ARE users online... each user in users_online has an id, username, name, group, href, and link.
	if (!empty($context['users_online']))
	{
		echo '
			', sprintf($txt['users_active'], $settings['lastActive']), ':<br>', implode(', ', $context['list_users_online']);

		// Showing membergroups?
		if (!empty($theme['show_group_key']) && !empty($context['membergroups']))
			echo '
			<br>[' . implode(']&nbsp;&nbsp;[', $context['membergroups']) . ']';
	}

	echo '
		</p>
		<p class="last">
			', $txt['most_online_today'], ': <strong>', comma_format($settings['mostOnlineToday']), '</strong>.
			', $txt['most_online_ever'], ': ', comma_format($settings['mostOnline']), ' (', timeformat($settings['mostDate']), ')
		</p>
	</section>';
}

// If user is logged in but stats are off, show them a PM bar.
function template_info_center_personalmsg()
{
	global $context, $theme, $options, $txt, $scripturl, $settings;

	if ($context['user']['is_guest'] || $theme['show_stats_index'])
		return;

	echo '
	<section class="ic">
		<we:title>
			', $context['allow_pm'] ? '<a href="' . $scripturl . '?action=pm">' : '', '<img src="', $theme['images_url'], '/message_sm.gif" alt="', $txt['personal_message'], '">', $context['allow_pm'] ? '</a>' : '', '
			', $txt['personal_messages'], '
		</we:title>
		<p class="pminfo">
			', number_context('youve_got_pms', $context['user']['messages']), '
			', sprintf($txt['click_to_view_them'], $scripturl . '?action=pm'), '
		</p>
	</section>';
}

?>