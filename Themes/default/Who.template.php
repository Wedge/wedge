<?php
// Version: 2.0 RC5; Who

// The only template in the file.
function template_main()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Display the table header and linktree.
	echo '
	<div class="main_section" id="whos_online">
		<form action="', $scripturl, '?action=who" method="post" id="whoFilter" accept-charset="UTF-8">
			<we:title2>
				', $txt['who_title'], '
			</we:title2>
			<div class="topic_table" id="mlist">
				<div class="pagesection">
					<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'], '</div>';
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
				<table class="table_grid cs0">
					<thead>
						<tr class="catbg left">
							<th scope="col" class="first_th" style="width: 40%"><a href="', $scripturl, '?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=user', $context['sort_direction'] != 'down' && $context['sort_by'] == 'user' ? '' : ';asc', '" rel="nofollow">', $txt['who_user'], ' ', $context['sort_by'] == 'user' ? '<img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a></th>
							<th scope="col" style="width: 10%"><a href="', $scripturl, '?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=time', $context['sort_direction'] == 'down' && $context['sort_by'] == 'time' ? ';asc' : '', '" rel="nofollow">', $txt['who_time'], ' ', $context['sort_by'] == 'time' ? '<img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a></th>
							<th scope="col" class="last_th w50">', $txt['who_action'], '</th>
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

		// Guests don't have information like icq, msn, y!, and aim... and they can't be messaged.
		if (!$member['is_guest'])
		{
			echo '
								<span class="contact_info floatright">
									', $context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['label'] . '">' : '', $settings['use_image_buttons'] ? '<img src="' . $member['online']['image_href'] . '" alt="' . $member['online']['text'] . '" class="bottom">' : $member['online']['text'], $context['can_send_pm'] ? '</a>' : '', '
									', isset($context['disabled_fields']['icq']) ? '' : $member['icq']['link'], ' ', isset($context['disabled_fields']['msn']) ? '' : $member['msn']['link'], ' ', isset($context['disabled_fields']['yim']) ? '' : $member['yim']['link'], ' ', isset($context['disabled_fields']['aim']) ? '' : $member['aim']['link'], '
								</span>';
		}

		echo '
								<span class="member', $member['is_hidden'] ? ' hidden' : '', '">
									', $member['is_guest'] ? $member['name'] : '<a href="' . $member['href'] . '" title="' . $txt['profile_of'] . ' ' . $member['name'] . '"' . (empty($member['color']) ? '' : ' style="color: ' . $member['color'] . '"') . '>' . $member['name'] . '</a>', '
								</span>';

		if (!empty($member['ip']))
			echo '
								(<a href="' . $scripturl . '?action=', $member['is_guest'] ? 'trackip' : 'profile;u=' . $member['id'], ';area=tracking;sa=ip;searchip=' . $member['ip'] . '">' . $member['ip'] . '</a>)';

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
				<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'], '</div>';

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
		</form>
	</div>';
}

function template_credits()
{
	global $context, $txt;

	// The most important part - the credits :P
	echo '
	<div class="main_section" id="credits">
		<we:cat>
			', $txt['credits'], '
		</we:cat>';

	foreach ($context['credits'] as $section)
	{
		if (isset($section['pretext']))
			echo '
		<div class="windowbg wrc intro">
			', $section['pretext'], '
		</div>';

		echo '
		<div style="width: 49%; float: left; margin: 0 .5%">';

		if (isset($section['title']))
			echo '
			<we:title>
				', $section['title'], '
			</we:title>';

		echo '
			<div class="windowbg2 wrc">';

		$top = true;
		foreach ($section['groups'] as $group)
		{
			if (empty($group['members']))
				continue;

			if (isset($group['title']))
				echo '
				<h6', $top ? ' class="top"' : '', '>', $group['title'], '</h6>
				<ul>
					<li>', implode('</li>
					<li>', $group['members']), '</li>
				</ul>';

			$top = false;
		}

		if (isset($section['posttext']))
			echo '
				<p class="posttext">', $section['posttext'], '</p>';

		echo '
			</div>
		</div>';
	}

	echo '
		<div style="width: 49%; float: left; margin: 0 .5%">
			<we:cat>
				', $txt['credits_copyright'], '
			</we:cat>
			<div class="windowbg wrc">
				<h6 class="top">', $txt['credits_forum'], '</h6>
				<div class="list">', $context['copyrights']['smf'], '</dd>';

	if (!empty($context['copyrights']['mods']))
		echo '
				<h6>', $txt['credits_modifications'], '</h6>
				<div class="list">', implode('</div><div class="list">', $context['copyrights']['mods']), '</div>';

	echo '
			</div>
		</div>
	</div>
	<br class="clear">';
}
?>