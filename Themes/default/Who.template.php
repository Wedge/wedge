<?php
/**
 * Wedge
 *
 * Displaying the list of users that are online and what they are doing, as well as credits.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// The only template in the file.
function template_main()
{
	global $context, $settings, $options, $txt;

	// Display the table header and linktree.
	echo '
	<div class="main_section" id="whos_online">
		<form action="<URL>?action=who" method="post" id="whoFilter" accept-charset="UTF-8">
			<we:title2>
				', $txt['who_title'], '
			</we:title2>
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
				<table class="table_grid cs0">
					<thead>
						<tr class="catbg left">
							<th scope="col" class="first_th" style="width: 40%"><a href="<URL>?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=user', $context['sort_direction'] != 'down' && $context['sort_by'] == 'user' ? '' : ';asc', '" rel="nofollow">', $txt['who_user'], ' ', $context['sort_by'] == 'user' ? '<img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a></th>
							<th scope="col" style="width: 10%"><a href="<URL>?action=who;start=', $context['start'], ';show=', $context['show_by'], ';sort=time', $context['sort_direction'] == 'down' && $context['sort_by'] == 'time' ? ';asc' : '', '" rel="nofollow">', $txt['who_time'], ' ', $context['sort_by'] == 'time' ? '<img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a></th>
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

		// Guests can't be messaged.
		if (!$member['is_guest'])
			echo '
								<span class="contact_info floatright">
									', $context['can_send_pm'] ? '<a href="' . $member['online']['href'] . '" title="' . $member['online']['label'] . '">' : '', $settings['use_image_buttons'] ? '<img src="' . $member['online']['image_href'] . '" alt="' . $member['online']['text'] . '">' : $member['online']['text'], $context['can_send_pm'] ? '</a>' : '', '
								</span>';

		echo '
								<span class="member', $member['is_hidden'] ? ' hidden' : '', '">
									', $member['is_guest'] ? $member['name'] : '<a href="' . $member['href'] . '" title="' . $txt['view_profile'] . '"' . (empty($member['color']) ? '' : ' style="color: ' . $member['color'] . '"') . '>' . $member['name'] . '</a>', '
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
		</form>
	</div>';
}

function template_credits()
{
	global $context, $txt;

	// The most important part - the credits :P
	echo '
	<div class="main_section" id="credits">';

 	if (!empty($context['site_credits']))
 	{
		echo '
		<we:cat>
			', $txt['credits_site'], '
		</we:cat>';

		if (!empty($context['site_credits']['admins']))
		{
			echo '
		<div', empty($context['site_credits']['mods']) ? '' : ' style="width: 49%; float: left; margin: 0 .5%"', '>
			<div class="windowbg2 wrc">
				<h6 class="top">', number_context('credits_admins', count($context['site_credits']['admins'])), '</h6>
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
		<div', empty($context['site_credits']['admins']) ? '' : ' style="width: 49%; float: left; margin: 0 .5%"', '>
			<div class="windowbg2 wrc">
				<h6 class="top">', number_context('credits_moderators', count($context['site_credits']['mods'])), '</h6>
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

		<div style="width: 49%; float: left; margin: 0 .5%">';

	foreach ($context['credits'] as $section)
	{
		echo '
			<we:block class="windowbg2 wrc" header="', westr::safe($section['title']), '">';

		$i = 0;
		$max = count($section['groups']);
		foreach ($section['groups'] as $group)
			echo '
				<h6', $i === 1 ? ' class="top"' : '', '>', $group['title'], '</h6>
				<ul', $i === $max ? ' class="last"' : '', '>
					<li>', implode('</li>
					<li>', $group['members']), '</li>
				</ul>';

		if (isset($section['posttext']))
			echo '
				<p class="posttext">', $section['posttext'], '</p>';

		echo '
			</we:block>';
	}

	echo '
		</div>
		<div style="width: 49%; float: left; margin: 0 .5%">
			<we:block class="windowbg wrc" header="', westr::safe($txt['credits_copyright']), '">
				<h6 class="top">', $txt['credits_forum'], '</h6>
				<ul>
					<li>', implode('</li>
					<li style="padding-top: 8px">', $context['copyrights']['software']), '</li>
				</ul>';

	if (!empty($context['copyrights']['mods']))
		echo '
				<h6>', $txt['credits_modifications'], '</h6>
				<ul>
					<li>', implode('</li>
					<li>', $context['copyrights']['mods']), '</li>
				</ul>';

	echo '
				<h6>', $txt['credits_tools'], '</h6>
				<ul>';

	foreach ($context['copyrights']['tools'] as $name => $str)
		echo '
					<li>', $str, isset($txt['credits_tools_' . $name]) ? ' &ndash; ' . $txt['credits_tools_' . $name] : '', '</li>';

	echo '
				</ul>
				<h6>', $txt['credits_images'], '</h6>
				<ul class="last">
					<li>', implode('</li>
					<li>', $context['copyrights']['images']), '</li>
				</ul>';

	echo '
			</we:block>
		</div>
		<div class="clear"></div>
	</div>';
}
?>