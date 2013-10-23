<?php
/**
 * Displaying the list of users that are online and what they are doing, as well as credits.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

// The only template in the file.
function template_main()
{
	global $context, $theme, $txt;

	// Display the table header and linktree.
	echo '
		<form action="<URL>?action=who" method="post" id="whoFilter" accept-charset="UTF-8">
			<we:cat>
				', $txt['who_title'], '
			</we:cat>
			<div class="topic_table" id="mlist">
				<div class="pagesection">
					<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>';
		echo '
					<div class="selectbox floatright">', $txt['who_show1'], '
						<select name="showtop" onchange="document.forms.whoFilter.show.value=this.value; document.forms.whoFilter.submit();">';

		foreach ($context['show_methods'] as $value => $label)
			echo '
							<option value="', $value, '" ', $value == $context['show_by'] ? ' selected' : '', '>', $label, '</option>';
		echo '
						</select>
						<noscript>
							<input type="submit" name="btnTop" value="', $txt['go'], '">
						</noscript>
					</div>
				</div>
				<table class="table_grid cs0 onlineinfo">
					<thead>
						<tr class="catbg left">
							<th style="width: 40%"><a href="<URL>?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=user', $context['sort_direction'] != 'down' && $context['sort_by'] == 'user' ? '' : ';asc', '" rel="nofollow">', $txt['who_user'], ' ', $context['sort_by'] == 'user' ? '<span class="sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>
							<th style="width: 10%"><a href="<URL>?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=time', $context['sort_direction'] == 'down' && $context['sort_by'] == 'time' ? ';asc' : '', '" rel="nofollow">', $txt['who_time'], ' ', $context['sort_by'] == 'time' ? '<span class="sort_' . $context['sort_direction'] . '"></span>' : '', '</a></th>
							<th class="w50">', $txt['who_action'], '</th>
						</tr>
					</thead>
					<tbody>';

	// For every member display their name, time and action (and more for admin).
	$alternate = 0;

	foreach ($context['members'] as $member)
	{
		// $alternate will either be true or false. If it's true, use "windowbg2" and otherwise use "windowbg".
		echo '
						<tr class="windowbg', $alternate ? '2' : '', '">
							<td>';

		// Guests can't be messaged.
		if (!$member['is_guest'])
			echo '
								<span class="contact_info floatright">
									', $context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['label'] . '">' : '', $theme['use_image_buttons'] ? '<img src="' . $member['online']['image_href'] . '" alt="' . $member['online']['text'] . '">' : $member['online']['text'], $context['can_send_pm'] ? '</a>' : '', '
								</span>';

		echo '
								<span class="member">
									', $member['is_guest'] ? $member['name'] : '<a href="' . $member['href'] . '" title="' . $txt['view_profile'] . '">' . $member['name'] . '</a>' . ($member['is_hidden'] ? ' <span class="notonline" title="' . $txt['hidden'] . '"></span>' : ''), '
								</span>';

		if (!empty($member['ip']))
			echo '
								(<a href="<URL>?action=', $member['is_guest'] ? 'trackip' : 'profile;u=' . $member['id'], ';area=tracking;sa=ip;searchip=' . $member['ip'] . '">' . $member['ip'] . '</a>)';

		echo '
							</td>
							<td class="nowrap">', $member['time'], '</td>
							<td>', $member['action'], '</td>
						</tr>';

		// Switch alternate to whatever it wasn't this time. (true -> false -> true -> false, etc.)
		$alternate = !$alternate;
	}

	// No members?
	if (empty($context['members']))
	{
		echo '
						<tr class="windowbg2">
							<td colspan="3" class="center">
								', $txt['who_no_online_' . ($context['show_by'] == 'guests' || $context['show_by'] == 'spiders' ? $context['show_by'] : 'members')], '
							</td>
						</tr>';
	}

	echo '
					</tbody>
				</table>
			</div>
			<div class="pagesection">
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>';

	echo '
				<div class="selectbox floatright">', $txt['who_show1'], '
					<select name="show" onchange="document.forms.whoFilter.submit();">';

	foreach ($context['show_methods'] as $value => $label)
		echo '
						<option value="', $value, '" ', $value == $context['show_by'] ? ' selected' : '', '>', $label, '</option>';
	echo '
					</select>
					<noscript>
						<input type="submit" value="', $txt['go'], '">
					</noscript>
				</div>
			</div>
		</form>';
}

function template_credits()
{
	global $context, $txt;

	// The most important part - the credits :P
	if (!empty($context['site_credits']))
	{
		echo '
		<we:cat>
			', $txt['credits_site'], '
		</we:cat>';

		if (!empty($context['site_credits']['admins']))
		{
			echo '
		<div', empty($context['site_credits']['mods']) ? '' : ' class="two-columns"', '>
			<div class="windowbg2 wrc">
				<h6>', number_context('credits_admins', count($context['site_credits']['admins'])), '</h6>
				<ul class="last">';

			foreach ($context['site_credits']['admins'] as $admin)
				echo '
					<li><a href="<URL>?action=profile;u=', $admin['id_member'], '">', $admin['real_name'], '</a></li>';

			echo '
				</ul>
			</div>
		</div>';
		}

		if (!empty($context['site_credits']['mods']))
		{
			echo '
		<div', empty($context['site_credits']['admins']) ? '' : ' class="two-columns"', '>
			<div class="windowbg2 wrc">
				<h6>', number_context('credits_moderators', count($context['site_credits']['mods'])), '</h6>
				<ul class="last">';

			foreach ($context['site_credits']['mods'] as $mod)
				echo '
					<li><a href="<URL>?action=profile;u=', $mod['id_member'], '">', $mod['real_name'], '</a></li>';

			echo '
				</ul>
			</div>
		</div>';
		}
	}

	echo '
		<we:cat>
			', $txt['credits_software'], '
		</we:cat>

		<we:block class="windowbg2 splitter">';

	$i = 1;
	$max = count($context['credits']);

	foreach ($context['credits'] as $group)
		echo '
			<section>
				<h6>', $group['title'], '</h6>
				<ul', $i === $max ? ' class="last"' : '', '>
					<li', $i++ == 1 ? ' style="list-style-type: none"' : '', '>', implode('</li>
					<li>', $group['members']), '</li>
				</ul>
			</section>';

	echo '
		</we:block>';
}
