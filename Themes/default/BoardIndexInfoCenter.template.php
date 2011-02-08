<?php
// Version: 2.0 RC5; BoardIndexInfoCenter

function template_info_center_begin()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Here's where the "Info Center" starts...
	echo '
	<we:title>
		<img id="upshrink_ic" src="', $settings['images_url'], '/collapse.gif" title="', $txt['upshrink_description'], '">
		', $txt['info_center_title'], '
	</we:title>
	<div class="roundframe">
		<div id="upshrinkHeaderIC"', empty($options['collapse_header_ic']) ? '' : ' style="display: none"', '>';
}

function template_info_center_recentposts()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// This is the "Recent Posts" bar.
	if (!empty($settings['number_recent_posts']))
	{
		echo '
			<we:title2>
				<a href="', $scripturl, '?action=recent"><img src="', $settings['images_url'], '/post/xx.gif" alt="', $txt['recent_posts'], '"></a>
				', $txt['recent_posts'], '
			</we:title2>
			<div class="hslice" id="recent_posts_content">
				<div class="entry-title" style="display: none;">', $context['forum_name_html_safe'], ' - ', $txt['recent_posts'], '</div>
				<div class="entry-content" style="display: none;">
					<a rel="feedurl" href="', $scripturl, '?action=feed;type=webslice">', $txt['subscribe_webslice'], '</a>
				</div>';

		// Only show one post.
		if ($settings['number_recent_posts'] == 1)
		{
			// latest_post has link, href, time, subject, short_subject (shortened with...), and topic. (its id.)
			echo '
				<strong><a href="', $scripturl, '?action=recent">', $txt['recent_posts'], '</a></strong>
				<p id="infocenter_onepost" class="middletext">
					', $txt['recent_view'], ' &quot;', $context['latest_post']['link'], '&quot; ', $txt['recent_updated'], ' (', $context['latest_post']['time'], ')<br>
				</p>';
		}
		// Show lots of posts.
		elseif (!empty($context['latest_posts']))
		{
			echo '
				<dl id="ic_recentposts" class="middletext">';

			/* Each post in latest_posts has:
					board (with an id, name, and link.), topic (the topic's id.), poster (with id, name, and link.),
					subject, short_subject (shortened with...), time, link, and href. */
			foreach ($context['latest_posts'] as $post)
				echo '
					<dt><strong>', $post['link'], '</strong> ', $txt['by'], ' ', $post['poster']['link'], ' (', $post['board']['link'], ')</dt>
					<dd>', $post['time'], '</dd>';
			echo '
				</dl>';
		}
		echo '
			</div>';
	}
}

function template_info_center_calendar()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Show information about events, birthdays, and holidays on the calendar.
	if ($context['show_calendar'])
	{
		echo '
			<we:title2>
				<a href="', $scripturl, '?action=calendar"><img src="', $settings['images_url'], '/icons/calendar.gif', '" alt="', $context['calendar_only_today'] ? $txt['calendar_today'] : $txt['calendar_upcoming'], '"></a>
				', $context['calendar_only_today'] ? $txt['calendar_today'] : $txt['calendar_upcoming'], '
			</we:title2>
			<p class="smalltext">';

		// Holidays like "Christmas", "Chanukah", and "We Love [Unknown] Day" :P.
		if (!empty($context['calendar_holidays']))
				echo '
				<span class="holiday">', $txt['calendar_prompt'], ' ', implode(', ', $context['calendar_holidays']), '</span><br>';

		// People's birthdays. Like mine. And yours, I guess. Kidding.
		if (!empty($context['calendar_birthdays']))
		{
				echo '
				<span class="birthday">', $context['calendar_only_today'] ? $txt['birthdays'] : $txt['birthdays_upcoming'], '</span> ';
		/* Each member in calendar_birthdays has:
				id, name (person), age (if they have one set?), is_last. (last in list?), and is_today (birthday is today?) */
		foreach ($context['calendar_birthdays'] as $member)
				echo '
				<a href="', $scripturl, '?action=profile;u=', $member['id'], '">', $member['is_today'] ? '<strong>' : '', $member['name'], $member['is_today'] ? '</strong>' : '', isset($member['age']) ? ' (' . $member['age'] . ')' : '', '</a>', $member['is_last'] ? '<br>' : ', ';
		}
		// Events like community get-togethers.
		if (!empty($context['calendar_events']))
		{
			echo '
				<span class="event">', $context['calendar_only_today'] ? $txt['events'] : $txt['events_upcoming'], '</span> ';
			/* Each event in calendar_events should have:
					title, href, is_last, can_edit (are they allowed?), modify_href, and is_today. */
			foreach ($context['calendar_events'] as $event)
				echo '
					', $event['can_edit'] ? '<a href="' . $event['modify_href'] . '" title="' . $txt['calendar_edit'] . '"><img src="' . $settings['images_url'] . '/icons/modify_small.gif"></a> ' : '', $event['href'] == '' ? '' : '<a href="' . $event['href'] . '">', $event['is_today'] ? '<strong>' . $event['title'] . '</strong>' : $event['title'], $event['href'] == '' ? '' : '</a>', $event['is_last'] ? '<br>' : ', ';
		}
		echo '
			</p>';
	}
}

function template_info_center_statistics()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Show statistical style information...
	if ($settings['show_stats_index'])
	{
		echo '
			<we:title2>
				<a href="', $scripturl, '?action=stats"><img src="', $settings['images_url'], '/icons/info.gif" alt="', $txt['forum_stats'], '"></a>
				', $txt['forum_stats'], '
			</we:title2>
			<p>
				', $context['common_stats']['total_posts'], ' ', $txt['posts_made'], ' ', $txt['in'], ' ', $context['common_stats']['total_topics'], ' ', $txt['topics'], ' ', $txt['by'], ' ', $context['common_stats']['total_members'], ' ', $txt['members'], '. ', !empty($settings['show_latest_member']) ? $txt['latest_member'] . ': <strong> ' . $context['common_stats']['latest_member']['link'] . '</strong>' : '', '<br>
				', (!empty($context['latest_post']) ? $txt['latest_post'] . ': <strong>&quot;' . $context['latest_post']['link'] . '&quot;</strong> (' . $context['latest_post']['time'] . ')<br>' : ''), '
				<a href="', $scripturl, '?action=recent">', $txt['recent_view'], '</a>', $context['show_stats'] ? '<br>
				<a href="' . $scripturl . '?action=stats">' . $txt['more_stats'] . '</a>' : '', '
			</p>';
	}
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

function template_info_center_personalmsg()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// If they are logged in, but statistical information is off... show a personal message bar.
	if ($context['user']['is_logged'] && !$settings['show_stats_index'])
	{
		echo '
			<we:title2>
				', $context['allow_pm'] ? '<a href="' . $scripturl . '?action=pm">' : '', '<img src="', $settings['images_url'], '/message_sm.gif" alt="', $txt['personal_message'], '">', $context['allow_pm'] ? '</a>' : '', '
				', $txt['personal_message'], '
			</we:title2>
			<p class="pminfo">
				<strong><a href="', $scripturl, '?action=pm">', $txt['personal_message'], '</a></strong>
				<span class="smalltext">
					', $txt['you_have'], ' ', comma_format($context['user']['messages']), ' ', $context['user']['messages'] == 1 ? $txt['message_lowercase'] : $txt['msg_alert_messages'], '.... ', $txt['click'], ' <a href="', $scripturl, '?action=pm">', $txt['here'], '</a> ', $txt['to_view'], '
				</span>
			</p>';
	}
}

function template_info_center_end()
{

	echo '
		</div>
	</div>';
}

?>