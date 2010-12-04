<?php
// Version: 2.0 RC4; ModerationCenter

function template_moderation_center()
{
	global $settings, $options, $context, $txt, $scripturl;

	// Show a welcome message to the user.
	echo '
	<div id="modcenter">
		<div class="cat_bar">
			<h3>', $txt['moderation_center'], '</h3>
		</div>
		<div class="information">
			<strong>', $txt['hello_guest'], ' ', $context['user']['name'], '!</strong>
			<p>
				', $txt['mc_description'], '
			</p>

		</div>';

	$alternate = true;
	// Show all the blocks they want to see.
	foreach ($context['mod_blocks'] as $block)
	{
		$block_function = 'template_' . $block;

		echo '
		<div class="modblock_', $alternate ? 'left' : 'right', '">', function_exists($block_function) ? $block_function() : '', '</div>';

		if (!$alternate)
			echo '
		<br class="clear" />';

		$alternate = !$alternate;
	}

	echo '
	</div>
	<br class="clear" />';
}

function template_latest_news()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3>
				<a href="', $scripturl, '?action=helpadmin;help=live_news" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" /></a>', $txt['mc_latest_news'], '
			</h3>
		</div>
		<div class="windowbg wrc">
			<div id="smfAnnouncements" class="smalltext">', $txt['mc_cannot_connect_sm'], '</div>
		</div>';

	// This requires a lot of javascript...
	//!!! Put this in it's own file!!
	add_js_file(
		$scripturl . '?action=viewsmfile;filename=current-version.js',
		$scripturl . '?action=viewsmfile;filename=latest-news.js',
		$settings['default_theme_url'] . '/scripts/admin.js?rc3'
	);

	add_js('
	var oAdminIndex = new smf_AdminIndex({
		sSelf: \'oAdminCenter\',

		bLoadAnnouncements: true,
		sAnnouncementTemplate: ', JavaScriptEscape('
			<dl>
				%content%
			</dl>'), ',
		sAnnouncementMessageTemplate: ', JavaScriptEscape('
			<dt><a href="%href%">%subject%</a> ' . $txt['on'] . ' %time%</dt>
			<dd>
				%message%
			</dd>'), ',
		sAnnouncementContainerId: \'smfAnnouncements\'
	});');
}

// Show all the group requests the user can see.
function template_group_requests_block()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3>
				<a href="', $scripturl, '?action=groups;sa=requests">', $txt['mc_group_requests'], '</a>
			</h3>
		</div>
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
	global $settings, $options, $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3>
				<a href="', $scripturl, '?action=moderate;area=reports">', $txt['mc_recent_reports'], '</a>
			</h3>
		</div>
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
	global $settings, $options, $context, $txt, $scripturl;

	echo '
		<div class="cat_bar">
			<h3>
				<a href="', $scripturl, '?action=moderate;area=userwatch">', $txt['mc_watched_users'], '</a>
			</h3>
		</div>
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
						<strong class="smalltext">', $txt['mc_watched_users_none'], '</strong>
					</li>';

		echo '
				</ul>
			</div>
		</div>';
}

// Little section for making... notes.
function template_notes()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
		<form action="', $scripturl, '?action=moderate;area=index" method="post">
			<div class="cat_bar">
				<h3>', $txt['mc_notes'], '</h3>
			</div>
			<div class="windowbg wrc">
				<div class="modbox">';

		if (!empty($context['notes']))
		{
			echo '
					<ul class="reset moderation_notes">';

			// Cycle through the notes.
			foreach ($context['notes'] as $note)
				echo '
						<li class="smalltext"><a href="', $note['delete_href'], '"><img src="', $settings['images_url'], '/pm_recipient_delete.gif" /></a> <strong>', $note['author']['link'], ':</strong> ', $note['text'], '</li>';

			echo '
					</ul>
					<div class="pagesection notes">
						<span class="smalltext">', $txt['pages'], ': ', $context['page_index'], '</span>
					</div>';
		}

		echo '
					<div class="floatleft post_note">
						<input type="text" name="new_note" value="', $txt['mc_click_add_note'], '" style="width: 95%;" onclick="if (this.value == \'', $txt['mc_click_add_note'], '\') this.value = \'\';" class="input_text" />
					</div>
					<div class="floatright">
						<input type="submit" name="makenote" value="', $txt['mc_add_note'], '" class="button_submit" />
					</div>
					<br class="clear" />
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>';
}

function template_reported_posts()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=reports', $context['view_closed'] ? ';sa=closed' : '', ';start=', $context['start'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3>
					', $context['view_closed'] ? $txt['mc_reportedp_closed'] : $txt['mc_reportedp_active'], '
				</h3>
			</div>
			<div class="pagesection">
				<div class="pages">', $txt['pages'], ': ', $context['page_index'], '</div>
			</div>';

	// Make the buttons.
	$close_button = create_button('close.gif', $context['view_closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close', $context['view_closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close', 'class="middle"');
	$details_button = create_button('details.gif', 'mc_reportedp_details', 'mc_reportedp_details', 'class="middle"');
	$ignore_button = create_button('ignore.gif', 'mc_reportedp_ignore', 'mc_reportedp_ignore', 'class="middle"');
	$unignore_button = create_button('ignore.gif', 'mc_reportedp_unignore', 'mc_reportedp_unignore', 'class="middle"');

	foreach ($context['reports'] as $report)
	{
		echo '
			<div class="', $report['alternate'] ? 'windowbg' : 'windowbg2', ' wrc">
				<div>
					<div class="floatright">
						<a href="', $report['report_href'], '">', $details_button, '</a>
						<a href="', $scripturl, '?action=moderate;area=reports', $context['view_closed'] ? ';sa=closed' : '', ';ignore=', (int) !$report['ignore'], ';rid=', $report['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '" ', !$report['ignore'] ? 'onclick="return confirm(' . JavaScriptEscape($txt['mc_reportedp_ignore_confirm']) . ');"' : '', '>', $report['ignore'] ? $unignore_button : $ignore_button, '</a>
						<a href="', $scripturl, '?action=moderate;area=reports', $context['view_closed'] ? ';sa=closed' : '', ';close=', (int) !$report['closed'], ';rid=', $report['id'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '">', $close_button, '</a>
						', !$context['view_closed'] ? '<input type="checkbox" name="close[]" value="' . $report['id'] . '" class="input_check" />' : '', '
					</div>
					<strong><a href="', $report['topic_href'], '">', $report['subject'], '</a></strong> ', $txt['mc_reportedp_by'], ' <strong>', $report['author']['link'], '</strong>
				</div>
				<br />
				<div class="smalltext">
					&#171; ', $txt['mc_reportedp_last_reported'], ': ', $report['last_updated'], ' &#187;<br />';

		// Prepare the comments...
		$comments = array();
		foreach ($report['comments'] as $comment)
			$comments[$comment['member']['id']] = $comment['member']['link'];

		echo '
					&#171; ', $txt['mc_reportedp_reported_by'], ': ', implode(', ', $comments), ' &#187;
				</div>
				<hr />
				', $report['body'], '
			</div>';
	}

	// Were none found?
	if (empty($context['reports']))
		echo '
			<div class="windowbg2 wrc">
				<p class="centertext">', $txt['mc_reportedp_none_found'], '</p>
			</div>';

	echo '
			<div class="pagesection">
				<div class="floatleft">
					', $txt['pages'], ': ', $context['page_index'], '
				</div>
				<div class="floatright">
					', !$context['view_closed'] ? '<input type="submit" name="close_selected" value="' . $txt['mc_reportedp_close_selected'] . '" class="button_submit" />' : '', '
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';
}

// Show a list of all the unapproved posts
function template_unapproved_posts()
{
	global $settings, $options, $context, $txt, $scripturl;

	// Just a big table of it all really...
	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=postmod;start=', $context['start'], ';sa=', $context['current_view'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3>', $txt['mc_unapproved_posts'], '</h3>
			</div>';

	// Make up some buttons
	$approve_button = create_button('approve.gif', 'approve', 'approve', 'class="middle"');
	$remove_button = create_button('delete.gif', 'remove_message', 'remove', 'class="middle"');

	// No posts?
	if (empty($context['unapproved_items']))
		echo '
			<div class="windowbg2 wrc">
				<p class="centertext">', $txt['mc_unapproved_' . $context['current_view'] . '_none_found'], '</p>
			</div>';
	else
		echo '
				<div class="pagesection">
					<div class="pagelinks">', $txt['pages'], ': ', $context['page_index'], '</div>
				</div>';

	foreach ($context['unapproved_items'] as $item)
	{
		echo '
			<div class="cat_bar">
				<h3>
					<span class="smalltext floatleft">', $item['counter'], '</span>
					<span class="smalltext floatleft"><a href="', $scripturl, '#c', $item['category']['id'], '">', $item['category']['name'], '</a> / <a href="', $scripturl, '?board=', $item['board']['id'], '.0">', $item['board']['name'], '</a> / <a href="', $scripturl, '?topic=', $item['topic']['id'], '.msg', $item['id'], '#msg', $item['id'], '">', $item['subject'], '</a></span>
					<span class="smalltext floatright">', $txt['mc_unapproved_by'], ' ', $item['poster']['link'], ' ', $txt['on'], ': ', $item['time'], '</span>
				</h3>
			</div>
			<div class="', $item['alternate'] ? 'windowbg' : 'windowbg2', ' wrc">
				<div class="post">', $item['body'], '</div>
				<span class="floatright">
					<a href="', $scripturl, '?action=moderate;area=postmod;sa=', $context['current_view'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], ';approve=', $item['id'], '">', $approve_button, '</a>';

				if ($item['can_delete'])
					echo '
					', $context['menu_separator'], '
					<a href="', $scripturl, '?action=moderate;area=postmod;sa=', $context['current_view'], ';start=', $context['start'], ';', $context['session_var'], '=', $context['session_id'], ';delete=', $item['id'], '">', $remove_button, '</a>';

				echo '
					<input type="checkbox" name="item[]" value="', $item['id'], '" checked="checked" class="input_check" /> ';

				echo '
				</span>
				<br class="clear" />
			</div>';
	}

	echo '
			<div class="pagesection">
				<div class="floatright">
					<select name="do" onchange="if (this.value != 0 && confirm(', JavaScriptEscape($txt['mc_unapproved_sure']), ')) submit();">
						<option value="0">', $txt['with_selected'], ':</option>
						<option value="0">-------------------</option>
						<option value="approve">&nbsp;--&nbsp;', $txt['approve'], '</option>
						<option value="delete">&nbsp;--&nbsp;', $txt['delete'], '</option>
					</select>
					<noscript><input type="submit" name="submit" value="', $txt['go'], '" class="button_submit" /></noscript>
				</div>';

	if (!empty($context['unapproved_items']))
		echo '
				<div class="floatleft">
					<div class="pagelinks">', $txt['pages'], ': ', $context['page_index'], '</div>
				</div>';

	echo '
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';
}

// List all attachments awaiting approval.
function template_unapproved_attachments()
{
	global $settings, $options, $context, $txt, $scripturl;

	// Show all the attachments still oustanding.
	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=attachmod;sa=attachments;start=', $context['start'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3>', $txt['mc_unapproved_attachments'], '</h3>
			</div>';

	// The ever popular approve button, with the massively unpopular delete.
	$approve_button = create_button('approve.gif', 'approve', 'approve', 'class="middle"');
	$remove_button = create_button('delete.gif', 'remove_message', 'remove', 'class="middle"');

	// None awaiting?
	if (empty($context['unapproved_items']))
		echo '
			<div class="windowbg wrc">
				<p class="centertext">', $txt['mc_unapproved_attachments_none_found'], '</p>
			</div>';
	else
		echo '
			<div class="pagesection">
				<div class="pagelinks">', $txt['pages'], ': ', $context['page_index'], '</div>
			</div>
			<table class="table_grid w100 cs0">
			<thead>
				<tr class="catbg">
					<th>', $txt['mc_unapproved_attach_name'], '</th>
					<th>', $txt['mc_unapproved_attach_size'], '</th>
					<th>', $txt['mc_unapproved_attach_poster'], '</th>
					<th>', $txt['date'], '</th>
					<th class="nowrap center"><input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" checked="checked" /></th>
				</tr>
			</thead>
			<tbody>';

	foreach ($context['unapproved_items'] as $item)
	{
		echo '
				<tr class="', $item['alternate'] ? 'windowbg' : 'windowbg2', '">
					<td>
						', $item['filename'], '
					</td>
					<td class="right">
						', $item['size'], $txt['kilobyte'], '
					</td>
					<td>
						', $item['poster']['link'], '
					</td>
					<td class="smalltext">
						', $item['time'], '<br />', $txt['in'], ' <a href="', $item['message']['href'], '">', $item['message']['subject'], '</a>
					</td>
					<td style="width: 4%" class="center">
						<input type="checkbox" name="item[]" value="', $item['id'], '" checked="checked" class="input_check" />
					</td>
				</tr>';
	}

	echo '
			</tbody>
			</table>
			<div class="pagesection">
				<div class="floatright">
					<select name="do" onchange="if (this.value != 0 && confirm(', JavaScriptEscape($txt['mc_unapproved_sure']), ')) submit();">
						<option value="0">', $txt['with_selected'], ':</option>
						<option value="0">-------------------</option>
						<option value="approve">&nbsp;--&nbsp;', $txt['approve'], '</option>
						<option value="delete">&nbsp;--&nbsp;', $txt['delete'], '</option>
					</select>
					<noscript><input type="submit" name="submit" value="', $txt['go'], '" class="button_submit" /></noscript>
				</div>';

	if (!empty($context['unapproved_items']))
		echo '
				<div class="floatleft">
					<div class="pagelinks">', $txt['pages'], ': ', $context['page_index'], '</div>
				</div>';

	echo '
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';
}

function template_viewmodreport()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=reports;report=', $context['report']['id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3>
					', sprintf($txt['mc_viewmodreport'], $context['report']['message_link'], $context['report']['author']['link']), '
				</h3>
			</div>
			<div class="title_bar">
				<h3>
					<span class="floatright">';

		// Make the buttons.
		$close_button = create_button('close.gif', $context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close', $context['report']['closed'] ? 'mc_reportedp_open' : 'mc_reportedp_close', 'class="middle"');
		$ignore_button = create_button('ignore.gif', 'mc_reportedp_ignore', 'mc_reportedp_ignore', 'class="middle"');
		$unignore_button = create_button('ignore.gif', 'mc_reportedp_unignore', 'mc_reportedp_unignore', 'class="middle"');

		echo '
						<a href="', $scripturl, '?action=moderate;area=reports;ignore=', (int) !$context['report']['ignore'], ';rid=', $context['report']['id'], ';', $context['session_var'], '=', $context['session_id'], '" ', !$context['report']['ignore'] ? 'onclick="return confirm(' . JavaScriptEscape($txt['mc_reportedp_ignore_confirm']) . ');"' : '', '>', $context['report']['ignore'] ? $unignore_button : $ignore_button, '</a>
						<a href="', $scripturl, '?action=moderate;area=reports;close=', (int) !$context['report']['closed'], ';rid=', $context['report']['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $close_button, '</a>
					</span>
					', sprintf($txt['mc_modreport_summary'], $context['report']['num_reports'], $context['report']['last_updated']), '
				</h3>
			</div>
			<div class="windowbg2 wrc">
				', $context['report']['body'], '
			</div>
			<br />
			<div class="cat_bar">
				<h3>', $txt['mc_modreport_whoreported_title'], '</h3>
			</div>';

	foreach ($context['report']['comments'] as $comment)
		echo '
			<div class="windowbg wrc">
				<p class="smalltext">', sprintf($txt['mc_modreport_whoreported_data'], $comment['member']['link'] . (empty($comment['member']['id']) && !empty($comment['member']['ip']) ? ' (' . $comment['member']['ip'] . ')' : ''), $comment['time']), '</p>
				<p>', $comment['message'], '</p>
			</div>';

	echo '
			<br />
			<div class="cat_bar">
				<h3>', $txt['mc_modreport_mod_comments'], '</h3>
			</div>
			<div class="windowbg2 wrc">';

	if (empty($context['report']['mod_comments']))
		echo '
				<p class="centertext">', $txt['mc_modreport_no_mod_comment'], '</p>';

	foreach ($context['report']['mod_comments'] as $comment)
		echo '
				<p>', $comment['member']['link'], ': ', $comment['message'], ' <em class="smalltext">(', $comment['time'], ')</em></p>';

	echo '
				<textarea rows="2" cols="60" style="width: 60%;" name="mod_comment"></textarea>
				<div>
					<input type="submit" name="add_comment" value="', $txt['mc_modreport_add_mod_comment'], '" class="button_submit" />
				</div>
			</div>
			<br />';

	$alt = false;

	template_show_list('moderation_actions_list');

	if (!empty($context['entries']))
	{
		echo '
			<div class="cat_bar">
				<h3>', $txt['mc_modreport_modactions'], '</h3>
			</div>
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
					<tr class="', $alt ? 'windowbg2' : 'windowbg', '">
						<td>', $entry['action'], '</td>
						<td>', $entry['time'], '</td>
						<td>', $entry['moderator']['link'], '</td>
						<td>', $entry['position'], '</td>
						<td>', $entry['ip'], '</td>
					</tr>
					<tr>
						<td colspan="5" class="', $alt ? 'windowbg2' : 'windowbg', '">';

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
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';
}

// Callback function for showing a watched users post in the table.
function template_user_watch_post_callback($post)
{
	global $scripturl, $context, $txt, $delete_button;

	// We'll have a delete please bob.
	if (empty($delete_button))
		$delete_button = create_button('delete.gif', 'remove_message', 'remove', 'class="middle"');

	$output_html = '
					<div>
						<div class="floatleft">
							<strong><a href="' . $scripturl . '?topic=' . $post['id_topic'] . '.' . $post['id'] . '#msg' . $post['id'] . '">' . $post['subject'] . '</a></strong> ' . $txt['mc_reportedp_by'] . ' <strong>' . $post['author_link'] . '</strong>
						</div>
						<div class="floatright">';

	if ($post['can_delete'])
		$output_html .= '
							<a href="' . $scripturl . '?action=moderate;area=userwatch;sa=post;delete=' . $post['id'] . ';start=' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(' . JavaScriptEscape($txt['mc_watched_users_delete_post']) . ');">' . $delete_button . '</a>
							<input type="checkbox" name="delete[]" value="' . $post['id'] . '" class="input_check" />';

	$output_html .= '
						</div>
					</div><br />
					<div class="smalltext">
						&#171; ' . $txt['mc_watched_users_posted'] . ': ' . $post['poster_time'] . ' &#187;
					</div>
					<hr />
					' . $post['body'];

	return $output_html;
}

// Moderation settings
function template_moderation_settings()
{
	global $settings, $options, $context, $txt, $scripturl;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=settings" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3>', $txt['mc_prefs_title'], '</h3>
			</div>
			<div class="information">
				', $txt['mc_prefs_desc'], '
			</div>
			<div class="windowbg2 wrc">
				<dl class="settings">
					<dt>
						<strong>', $txt['mc_prefs_homepage'], ':</strong>
					</dt>
					<dd>';

	foreach ($context['homepage_blocks'] as $k => $v)
		echo '
						<label for="mod_homepage_', $k, '"><input type="checkbox" id="mod_homepage_', $k, '" name="mod_homepage[', $k, ']"', in_array($k, $context['mod_settings']['user_blocks']) ? ' checked="checked"' : '', ' class="input_check" /> ', $v, '</label><br />';

	echo '
					</dd>';

	// If they can moderate boards they have more options!
	if ($context['can_moderate_boards'])
	{
		echo '
					<dt>
						<strong><label for="mod_show_reports">', $txt['mc_prefs_show_reports'], '</label>:</strong>
					</dt>
					<dd>
						<input type="checkbox" id="mod_show_reports" name="mod_show_reports" ', $context['mod_settings']['show_reports'] ? 'checked="checked"' : '', ' class="input_check" />
					</dd>
					<dt>
						<strong><label for="mod_notify_report">', $txt['mc_prefs_notify_report'], '</label>:</strong>
					</dt>
					<dd>
						<select id="mod_notify_report" name="mod_notify_report">
							<option value="0" ', $context['mod_settings']['notify_report'] == 0 ? 'selected="selected"' : '', '>', $txt['mc_prefs_notify_report_never'], '</option>
							<option value="1" ', $context['mod_settings']['notify_report'] == 1 ? 'selected="selected"' : '', '>', $txt['mc_prefs_notify_report_moderator'], '</option>
							<option value="2" ', $context['mod_settings']['notify_report'] == 2 ? 'selected="selected"' : '', '>', $txt['mc_prefs_notify_report_always'], '</option>
						</select>
					</dd>';
	}

	if ($context['can_moderate_approvals'])
	{
		echo '
					<dt>
						<strong><label for="mod_notify_approval">', $txt['mc_prefs_notify_approval'], '</label>:</strong>
					</dt>
					<dd>
						<input type="checkbox" id="mod_notify_approval" name="mod_notify_approval" ', $context['mod_settings']['notify_approval'] ? 'checked="checked"' : '', ' class="input_check" />
					</dd>';
	}

	echo '
				</dl>
				<div class="righttext">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="submit" name="save" value="', $txt['save'], '" class="button_submit" />
				</div>
			</div>
		</form>
	</div>
	<br class="clear" />';
}

// Show a notice sent to a user.
function template_show_notice()
{
	global $txt, $settings, $options, $context;

	// We do all the HTML for this one!
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
<head>
	<meta charset="utf-8" />
	<title>', $context['page_title'], '</title>
	<link rel="stylesheet" href="', $settings['theme_url'], '/css/index.css" />
</head>
<body>
	<div class="cat_bar">
		<h3>', $txt['show_notice'], '</h3>
	</div>
	<div class="title_bar">
		<h3>', $txt['show_notice_subject'], ': ', $context['notice_subject'], '</h3>
	</div>
	<div class="windowbg wrc">
		<dl>
			<dt>
				<strong>', $txt['show_notice_text'], ':</strong>
			</dt>
			<dd>
				', $context['notice_body'], '
			</dd>
		</dl>
	</div>
</body>
</html>';

}

// Add or edit a warning template.
function template_warn_template()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="modcenter">
		<form action="', $scripturl, '?action=moderate;area=warnings;sa=templateedit;tid=', $context['id_template'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3>', $context['page_title'], '</h3>
			</div>
			<div class="information">
				', $txt['mc_warning_template_desc'], '
			</div>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<strong><label for="template_title">', $txt['mc_warning_template_title'], '</label>:</strong>
					</dt>
					<dd>
						<input type="text" id="template_title" name="template_title" value="', $context['template_data']['title'], '" size="30" class="input_text" />
					</dd>
					<dt>
						<strong><label for="template_body">', $txt['profile_warning_notify_body'], '</label>:</strong>
						<div class="smalltext">', $txt['mc_warning_template_body_desc'], '</div>
					</dt>
					<dd>
						<textarea id="template_body" name="template_body" rows="10" cols="45" class="smalltext">', $context['template_data']['body'], '</textarea>
					</dd>
				</dl>';

	if ($context['template_data']['can_edit_personal'])
		echo '
				<input type="checkbox" name="make_personal" id="make_personal" ', $context['template_data']['personal'] ? 'checked="checked"' : '', ' class="input_check" />
				<label for="make_personal">
					<strong>', $txt['mc_warning_template_personal'], '</strong>
				</label>
				<div class="smalltext">', $txt['mc_warning_template_personal_desc'], '</div>
				<br />';

	echo '
				<input type="submit" name="save" value="', $context['page_title'], '" class="button_submit" />
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';
}

?>