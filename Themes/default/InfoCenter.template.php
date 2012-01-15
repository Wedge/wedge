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

function template_info_center_before()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Here's where the "Info Center" starts...
	echo '
		<we:title>
			<div id="upshrink_ic" title="', $txt['upshrink_description'], '"', empty($options['collapse_header_ic']) ? ' class="fold"' : '', '></div>
			', $txt['info_center_title'], '
		</we:title>
		<div id="upshrinkHeaderIC"', empty($options['collapse_header_ic']) ? '' : ' class="hide"', '>';
}

// Show statistical style information...
function template_info_center_statistics()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	if ($settings['show_stats_index'])
		echo '
			<we:title2>
				<a href="', $scripturl, '?action=stats"><img src="', $settings['images_url'], '/icons/info.gif" alt="', $txt['forum_stats'], '"></a>
				', $txt['forum_stats'], '
			</we:title2>
			<ul class="stats">
				<li>', $context['common_stats']['total_posts'], ' ', $txt['posts_made'], ' ', $txt['in'], ' ', $context['common_stats']['total_topics'], ' ', $txt['topics'], ' ', $txt['by'], ' ', $context['common_stats']['total_members'], ' ', $txt['members'], '.</li>', !empty($settings['show_latest_member']) ? '
				<li>' . $txt['latest_member'] . ': <strong> ' . $context['common_stats']['latest_member']['link'] . '</strong></li>' : '', !empty($context['latest_post']) ? '
				<li>' . $txt['latest_post'] . ': <strong>&quot;' . $context['latest_post']['link'] . '&quot;</strong> (' . $context['latest_post']['time'] . ')</li>' : '', '
				<li><a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a></li>', $context['show_stats'] ? '
				<li><a href="' . $scripturl . '?action=stats">' . $txt['more_stats'] . '</a></li>' : '', '
			</ul>';
}

function template_info_center_usersonline()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// "Users online" - in order of activity.
	echo '
			<we:title2>
				', $context['show_who'] ? '<a href="' . $scripturl . '?action=who">' : '', '<img src="', $settings['images_url'], '/icons/online.gif', '" alt="', $txt['online_users'], '">', $context['show_who'] ? '</a>' : '', '
				', $txt['online_users'], '
			</we:title2>
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
			<p class="inline smalltext">';

	// Assuming there ARE users online... each user in users_online has an id, username, name, group, href, and link.
	if (!empty($context['users_online']))
	{
		echo '
				', sprintf($txt['users_active'], $modSettings['lastActive']), ':<br>', implode(', ', $context['list_users_online']);

		// Showing membergroups?
		if (!empty($settings['show_group_key']) && !empty($context['membergroups']))
			echo '
				<br>[' . implode(']&nbsp;&nbsp;[', $context['membergroups']) . ']';
	}

	echo '
			</p>
			<p class="last smalltext">
				', $txt['most_online_today'], ': <strong>', comma_format($modSettings['mostOnlineToday']), '</strong>.
				', $txt['most_online_ever'], ': ', comma_format($modSettings['mostOnline']), ' (', timeformat($modSettings['mostDate']), ')
			</p>';
}

// If user is logged in but stats are off, show them a PM bar.
function template_info_center_personalmsg()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	if ($context['user']['is_guest'] || $settings['show_stats_index'])
		return;

	echo '
			<we:title2>
				', $context['allow_pm'] ? '<a href="' . $scripturl . '?action=pm">' : '', '<img src="', $settings['images_url'], '/message_sm.gif" alt="', $txt['personal_message'], '">', $context['allow_pm'] ? '</a>' : '', '
				', $txt['personal_messages'], '
			</we:title2>
			<p class="pminfo">
				', number_context('youve_got_pms', $context['user']['messages']), '
				', sprintf($txt['click_to_view_them'], $scripturl . '?action=pm'), '
			</p>';
}

function template_info_center_after()
{
	echo '
		</div>';
}

?>