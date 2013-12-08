<?php
/**
 * Displays the different areas of the moderation center.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

function template_moderation_center()
{
	global $context, $txt;

	// Show a welcome message to the user.
	echo '
	<div id="modcenter">
		<we:cat>
			', $txt['moderation_center'], '
		</we:cat>
		<div class="information">
			<strong>', $txt['hello_guest'], ' ', we::$user['name'], '!</strong>
			<p>
				', $txt['mc_description'], '
			</p>

		</div>';

	// First, the notes block.
	template_notes();
	echo '
		<br>';

	$alternate = true;
	// Show all the blocks they want to see.
	foreach ($context['mod_blocks'] as $block)
	{
		$block_function = 'template_' . $block;

		echo '
		<div class="modblock_', $alternate ? 'left' : 'right', '">', function_exists($block_function) ? $block_function() : '', '</div>';

		if (!$alternate)
			echo '
		<br class="clear">';

		$alternate = !$alternate;
	}

	echo '
	</div>
	<br class="clear">';
}

// Show all the group requests the user can see.
function template_group_requests_block()
{
	global $context, $txt;

	echo '
		<we:cat>
			<a href="<URL>?action=groups;sa=requests">', $txt['mc_group_requests'], '</a>
		</we:cat>
		<div class="windowbg wrc">
			<div class="modbox">
				<ul class="reset">';

		foreach ($context['group_requests'] as $request)
			echo '
				<li class="smalltext">
					<a href="', $request['request_href'], '">', $request['group']['name'], '</a> ', $txt['mc_groupr_by'], ' ', $request['member']['link'], '
				</li>';

		// Don't have any watched users right now?
		if (empty($context['group_requests']))
			echo '
				<li>
					<strong class="smalltext">', $txt['mc_group_requests_none'], '</strong>
				</li>';

		echo '
				</ul>
			</div>
		</div>';
}

// A block to show the current top reported posts.
function template_reported_posts_block()
{
	global $context, $txt;

	echo '
		<we:cat>
			<a href="<URL>?action=moderate;area=reports">', $txt['mc_recent_reports'], '</a>
		</we:cat>
		<div class="windowbg wrc">
			<div class="modbox">
				<ul class="reset">';

		foreach ($context['reported_posts'] as $report)
			echo '
					<li class="smalltext">
						<a href="', $report['report_href'], '">', $report['subject'], '</a> ', $txt['mc_reportedp_by'], ' ', $report['author']['link'], '
					</li>';

		// Don't have any watched users right now?
		if (empty($context['reported_posts']))
			echo '
					<li>
						<strong class="smalltext">', $txt['mc_recent_reports_none'], '</strong>
					</li>';

		echo '
				</ul>
			</div>
		</div>';
}

function template_watched_users()
{
	global $context, $txt;

	echo '
		<we:cat>
			<a href="<URL>?action=moderate;area=warnings">', $txt['mc_warned_users'], '</a>
		</we:cat>
		<div class="windowbg wrc">
			<div class="modbox">
				<ul class="reset">';

		foreach ($context['watched_users'] as $user)
			echo '
					<li>
						<span class="smalltext">', sprintf(!empty($user['last_login']) ? $txt['mc_seen'] : $txt['mc_seen_never'], $user['link'], $user['last_login']), '</span>
					</li>';

		// Don't have any watched users right now?
		if (empty($context['watched_users']))
			echo '
					<li>
						<strong class="smalltext">', $txt['mc_warned_users_none'], '</strong>
					</li>';

		echo '
				</ul>
			</div>
		</div>';
}

// Little section for making... notes.
function template_notes()
{
	global $theme, $context, $txt;

	echo '
		<form action="<URL>?action=moderate;area=index" method="post">
			<we:cat>
				', $txt['mc_notes'], '
			</we:cat>
			<div class="windowbg wrc">
				<div class="modbox">';

		if (!empty($context['notes']))
		{
			echo '
					<ul class="reset moderation_notes">';

			// Cycle through the notes.
			foreach ($context['notes'] as $note)
				echo '
						<li class="smalltext"><a href="', $note['delete_href'], '"><img src="', $theme['images_url'], '/pm_recipient_delete.gif"></a> <strong>', $note['author']['link'], ':</strong> ', $note['text'], '</li>';

			echo '
					</ul>
					<div class="pagesection notes">
						<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
					</div>';
		}

		echo '
					<div class="floatleft post_note">
						<input name="new_note" value="', $txt['mc_click_add_note'], '" style="width: 95%" onclick="if (this.value == \'', $txt['mc_click_add_note'], '\') this.value = \'\';">
					</div>
					<div class="floatright">
						<input type="submit" name="makenote" value="', $txt['mc_add_note'], '" class="new">
					</div>
					<br class="clear">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
}

function template_reported_posts()
{
	global $context, $txt;

	echo '
	<div id="modcenter">
		<form action="<URL>?action=moderate;area=reports', $context['view_closed'] ? ';sa=closed' : '', ';start=', $context['start'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $context['view_closed'] ? $txt['mc_reportedp_closed'] : $txt['mc_reportedp_active'], '
			</we:cat>
			<div class="pagesection">
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
			</div>';

	// Make the buttons.
	$close_button = create_button('close.gif', $context['view_closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close', $context['view_closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close');
	$details_button = create_button('details.gif', 'mc_reportedp_details', 'mc_reportedp_details');
	$ignore_button = create_button('ignore.gif', 'mc_reportedp_ignore', 'mc_reportedp_ignore');
	$unignore_button = create_button('ignore.gif', 'mc_reportedp_unignore', 'mc_reportedp_unignore');

	foreach ($context['reports'] as $report)
	{
		echo '
			<div class="windowbg', $report['alternate'] ? '' : '2', ' wrc core_posts">
				<div>
					<div class="floatright">
						<a href="', $report['report_href'], '">', $details_button, '</a>
						<a href="<URL>?action=moderate;area=reports', $context['view_closed'] ? ';sa=closed' : '', ';ignore=', (int) !$report['ignore'], ';rid=', $report['id'], ';start=', $context['start'], ';', $context['session_query'], '" ', !$report['ignore'] ? 'onclick="return ask(' . JavaScriptEscape($txt['mc_reportedp_ignore_confirm']) . ', e);"' : '', '>', $report['ignore'] ? $unignore_button : $ignore_button, '</a>
						<a href="<URL>?action=moderate;area=reports', $context['view_closed'] ? ';sa=closed' : '', ';close=', (int) !$report['closed'], ';rid=', $report['id'], ';start=', $context['start'], ';', $context['session_query'], '">', $close_button, '</a>
						', !$context['view_closed'] ? '<input type="checkbox" name="close[]" value="' . $report['id'] . '">' : '', '
					</div>
					<strong>', $report['board_link'], ' / <a href="', $report['topic_href'], '">', $report['subject'], '</a></strong> ', $txt['mc_reportedp_by'], ' <strong>', $report['author']['link'], '</strong> (', number_context('mc_reportedp_count', $report['num_reports']), ')
				</div>
				<div class="clear smalltext">
					&#171; ', $txt['mc_reportedp_last_reported'], ': ', $report['last_updated'], ' &#187;<br>';

		// Prepare the comments...
		$comments = array();
		foreach ($report['comments'] as $comment)
			$comments[$comment['member']['id']] = $comment['member']['link'];

		echo '
					&#171; ', $txt['mc_reportedp_reported_by'], ': ', implode(', ', $comments), ' &#187;
				</div>
				<hr>
				', $report['body'], '
			</div>';
	}

	// Were none found?
	if (empty($context['reports']))
		echo '
			<div class="windowbg2 wrc">
				<p class="center">', $txt['mc_reportedp_none_found'], '</p>
			</div>';

	echo '
			<div class="pagesection">
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
				<div class="floatright">
					', !$context['view_closed'] ? '<input type="submit" name="close_selected" value="' . $txt['mc_reportedp_close_selected'] . '" class="delete">' : '', '
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';
}

// Show a list of all the unapproved posts
function template_unapproved_posts()
{
	global $context, $txt;

	// Just a big table of it all really...
	echo '
	<div id="modcenter">
		<form action="<URL>?action=moderate;area=postmod;start=', $context['start'], ';sa=', $context['current_view'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['mc_unapproved_posts'], '
			</we:cat>';

	// No posts?
	if (empty($context['unapproved_items']))
		echo '
			<div class="windowbg2 wrc">
				<p class="center">', $txt['mc_unapproved_' . $context['current_view'] . '_none_found'], '</p>
			</div>';
	else
		echo '
			<div class="pagesection">
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
			</div>';

	foreach ($context['unapproved_items'] as $item)
	{
		echo '
			<we:title>
				<span class="smalltext floatleft">', $item['counter'], '&nbsp;</span>
				<span class="smalltext floatleft"><a href="<URL>?category=', $item['category']['id'], '">', $item['category']['name'], '</a> / <a href="<URL>?board=', $item['board']['id'], '.0">', $item['board']['name'], '</a> / <a href="<URL>?topic=', $item['topic']['id'], '.msg', $item['id'], '#msg', $item['id'], '">', $item['subject'], '</a></span>
				<span class="smalltext floatright">', $txt['mc_unapproved_by'], ' ', $item['poster']['link'], ' ', $item['on_time'], '</span>
			</we:title>
			<div class="windowbg', $item['alternate'] ? '' : '2', ' wrc core_posts">
				<div class="post">', $item['body'], '</div>
				<span class="floatright">
					<a href="<URL>?action=moderate;area=postmod;sa=', $context['current_view'], ';start=', $context['start'], ';', $context['session_query'], ';approve=', $item['id'], '" class="approve_button">', $txt['approve'], '</a>';

				if ($item['can_delete'])
					echo '
					<a href="<URL>?action=moderate;area=postmod;sa=', $context['current_view'], ';start=', $context['start'], ';', $context['session_query'], ';delete=', $item['id'], '" class="remove_button">', $txt['remove_message'], '</a>';

				echo '
					<input type="checkbox" name="item[]" value="', $item['id'], '" checked>
				</span>
				<br class="clear">
			</div>';
	}

	echo '
			<div class="pagesection">
				<div class="floatright">
					<select name="do" onchange="if (this.value != 0 && ask(', JavaScriptEscape($txt['mc_unapproved_sure']), ', e)) submit();">
						<option value="0" data-hide>', $txt['with_selected'], ':</option>
						<option value="approve">&nbsp;--&nbsp;', $txt['approve'], '</option>
						<option value="delete">&nbsp;--&nbsp;', $txt['delete'], '</option>
					</select>
					<noscript><input type="submit" value="', $txt['go'], '"></noscript>
				</div>';

	if (!empty($context['unapproved_items']))
		echo '
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>';

	echo '
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';
}

function template_viewmodreport()
{
	global $context, $txt;

	echo '
	<div id="modcenter">
		<form action="<URL>?action=moderate;area=reports;report=', $context['report']['id'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', sprintf($txt['mc_viewmodreport'], $context['report']['message_link'], $context['report']['author']['link']), '
			</we:cat>
			<div class="windowbg2 wrc">';

	// Make the buttons.
	$close_button = create_button('close.gif', $context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close', $context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close');
	$ignore_button = create_button('ignore.gif', 'mc_reportedp_ignore', 'mc_reportedp_ignore');
	$unignore_button = create_button('ignore.gif', 'mc_reportedp_unignore', 'mc_reportedp_unignore');

	echo '
				<span class="floatright"><a href="<URL>?action=moderate;area=reports;ignore=', (int) !$context['report']['ignore'], ';rid=', $context['report']['id'], ';', $context['session_query'], '"', !$context['report']['ignore'] ? ' onclick="return ask(' . JavaScriptEscape($txt['mc_reportedp_ignore_confirm']) . ', e);"' : '', '>', $context['report']['ignore'] ? $unignore_button : $ignore_button, '</a></span>
				<span class="floatright"><a href="<URL>?action=moderate;area=reports;close=', (int) !$context['report']['closed'], ';rid=', $context['report']['id'], ';', $context['session_query'], '">', $close_button, '</a>&nbsp;&nbsp;</span>
				', number_context('mc_modreport_summary', $context['report']['num_reports']), ' ', sprintf($txt['mc_modreport_lastreport'], $context['report']['last_updated']), '
			</div>
			<div class="windowbg wrc">
				', $context['report']['body'], '
			</div>
			<br>
			<we:title>
				', $txt['mc_modreport_whoreported_title'], '
			</we:title>';

	foreach ($context['report']['comments'] as $comment)
		echo '
			<div class="windowbg wrc">
				<p class="smalltext">', sprintf($txt['mc_modreport_whoreported_data'], $comment['member']['link'] . (empty($comment['member']['id']) && !empty($comment['member']['ip']) ? ' (' . $comment['member']['ip'] . ')' : ''), $comment['time']), '</p>
				<p>', $comment['message'], '</p>
			</div>';

	echo '
			<br>
			<we:title>
				', $txt['mc_modreport_mod_comments'], '
			</we:title>
			<div class="windowbg2 wrc">';

	if (empty($context['report']['mod_comments']))
		echo '
				<p class="center">', $txt['mc_modreport_no_mod_comment'], '</p>';

	foreach ($context['report']['mod_comments'] as $comment)
		echo '
				<p>', $comment['member']['link'], ': ', $comment['message'], ' <em class="smalltext">(', $comment['time'], ')</em></p>';

	echo '
				<textarea rows="3" style="', we::is('ie8') ? 'width: 635px; max-width: 60%; min-width: 60%' : 'width: 60%', '" name="mod_comment"></textarea>
				<div style="padding: 8px 7% 0 0; text-align: right">
					<input type="submit" name="add_comment" value="', $txt['mc_modreport_add_mod_comment'], '" class="new">
				</div>
			</div>
			<br>';

	$alt = false;

	template_show_list('moderation_actions_list');

	if (!empty($context['entries']))
	{
		echo '
			<we:cat>
				', $txt['mc_modreport_modactions'], '
			</we:cat>
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg">
						<th>', $txt['modlog_action'], '</th>
						<th>', $txt['modlog_date'], '</th>
						<th>', $txt['modlog_member'], '</th>
						<th>', $txt['modlog_position'], '</th>
						<th>', $txt['modlog_ip'], '</th>
					</tr>
				</thead>
				<tbody>';

		foreach ($context['entries'] as $entry)
		{
			echo '
					<tr class="windowbg', $alt ? '2' : '', '">
						<td>', $entry['action'], '</td>
						<td>', $entry['time'], '</td>
						<td>', $entry['moderator']['link'], '</td>
						<td>', $entry['position'], '</td>
						<td>', $entry['ip'], '</td>
					</tr>
					<tr>
						<td colspan="5" class="windowbg', $alt ? '2' : '', '">';

			foreach ($entry['extra'] as $key => $value)
				echo '
							<em>', $key, '</em>: ', $value;
			echo '
						</td>
					</tr>';
		}
		echo '
				</tbody>
			</table>';
	}

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';
}

// Callback function for showing a watched users post in the table.
function template_user_watch_post_callback($post)
{
	global $context, $txt;

	$output_html = '
					<div>
						<div class="floatleft">
							<strong><a href="<URL>?topic=' . $post['id_topic'] . '.' . $post['id'] . '#msg' . $post['id'] . '">' . $post['subject'] . '</a></strong> ' . $txt['mc_reportedp_by'] . ' <strong>' . $post['author_link'] . '</strong>
						</div>
						<div class="floatright">';

	if ($post['can_delete'])
		$output_html .= '
							<a href="<URL>?action=moderate;area=warnings;sa=post;delete=' . $post['id'] . ';start=' . $context['start'] . ';' . $context['session_query'] . '" onclick="return ask(' . JavaScriptEscape($txt['mc_warned_users_delete_post']) . ', e);" class="remove_button">' . $txt['remove_message'] . '</a>
							<input type="checkbox" name="delete[]" value="' . $post['id'] . '">';

	$output_html .= '
						</div>
					</div>
					<br>
					<div class="smalltext">
						&#171; ' . $txt['mc_warned_users_posted'] . ': ' . $post['poster_time'] . ' &#187;
					</div>
					<hr>
					' . $post['body'];

	return $output_html;
}

function template_prefs()
{
	global $context, $txt;

	echo '
	<form action="<URL>?action=moderate;area=settings" method="post" accept-charset="UTF-8">
		<we:cat>
			', $txt['mc_prefs_title'], '
		</we:cat>
		<div class="windowbg wrc">
			<p>', $txt['mc_prefs_desc'], '</p>
			<dl class="settings">';

	// If they can moderate boards they have more options!
	if ($context['can_moderate_boards'])
		echo '
				<dt>
					<label for="mod_notify_report">', $txt['mc_prefs_notify_report'], '</label>:
				</dt>
				<dd>
					<select id="mod_notify_report" name="mod_notify_report">
						<option value="0"', $context['mod_settings']['notify_report'] == 0 ? ' selected' : '', '>', $txt['mc_prefs_notify_report_never'], '</option>
						<option value="1"', $context['mod_settings']['notify_report'] == 1 ? ' selected' : '', '>', $txt['mc_prefs_notify_report_moderator'], '</option>
						<option value="2"', $context['mod_settings']['notify_report'] == 2 ? ' selected' : '', '>', $txt['mc_prefs_notify_report_always'], '</option>
					</select>
				</dd>';

	if ($context['can_moderate_approvals'])
		echo '
				<dt>
					<label for="mod_notify_approval">', $txt['mc_prefs_notify_approval'], '</label>:
				</dt>
				<dd>
					<input type="checkbox" id="mod_notify_approval" name="mod_notify_approval"', $context['mod_settings']['notify_approval'] ? ' checked' : '', '>
				</dd>';

	echo '
			</dl>
			<div class="right">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" name="save" value="', $txt['save'], '" class="save">
			</div>
		</div>
	</form>';
}

/**
 * Create a 'button', comprised of an icon and a text string, subject to theme settings.
 *
 * This function first looks to see if the theme specifies its own button system, and if it does not (or, $force_use is true), this function manages the button generation.
 *
 * If the theme directs that image buttons should not be used, the button will simply be the text string dictated by $alt. If the theme does use image buttons, it looks to see if it uses full images, or image+text, and generates the appropriate HTML.
 *
 * @param string $name Name of the button, which is also the base of the filename of the image to be used.
 * @param string $alt The key within $txt to use as the alt-text of the image, or the textual caption if there is no image.
 * @param string $label The key within $txt to use in the event of image/text composite buttons.
 * @return string The HTML for the given button.
 */
function create_button($name, $alt, $label)
{
	global $theme, $txt;

	return '<img src="' . $theme['images_url'] . '/buttons/' . $name . '" alt="' . $txt[$alt] . '" class="middle">' . (isset($txt[$label]) ? '&nbsp;' . $txt[$label] : '');
}
