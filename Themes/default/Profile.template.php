<?php
/**
 * Wedge
 *
 * Displays all profile-related information.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Template for the profile header - goes before any other profile template.
function template_profile_top()
{
	global $context;

	// Prevent Chrome from auto completing fields when viewing/editing other members' profiles
	if (we::is('chrome') && !we::$user['is_owner'])
		add_js('
	$(\'input:not([type]), input[type="password"]\').attr("autocomplete", "off");'); //

	// If an error occurred while trying to save previously, give the user a clue!
	if (!empty($context['post_error']))
		echo '
					', template_error_message();

	// If the profile was update successfully, let the user know this.
	if (!empty($context['profile_updated']))
		echo '
					<div class="windowbg" id="profile_success">
						', $context['profile_updated'], '
					</div>';
}

// This template displays users details without any option to edit them.
function template_summary()
{
	global $context, $theme, $settings, $txt;

	$group = !empty($context['member']['group']) ? 'group' : 'post_group';

	// Display the basic information about the user
	echo '
	<we:cat>
		<img src="', $theme['images_url'], '/icons/profile_sm.gif">
		', $txt['summary'], '
	</we:cat>
	<div id="basicinfo">
		<div class="windowbg wrc">
			<div class="flow_auto">
				<div class="username">
					<h4>', $context['member']['link'], '</h4>
					<span class="position">', $context['member'][$group], '</span>
				</div>
				', $context['member']['avatar']['image'], '
				<ul class="reset">';

	// What about if we allow email only via the forum?
	if ($context['member']['show_email'] === 'no_through_forum' || $context['member']['show_email'] === 'yes_permission_override')
		echo '
					<li><a href="<URL>?action=emailuser;sa=email;uid=', $context['member']['id'], '" title="', $context['member']['show_email'] == 'yes_permission_override' ? $context['member']['email'] : '', '" rel="nofollow"><img src="', $theme['images_url'], '/email_sm.gif" alt="', $txt['email'], '"></a></li>';

	// Don't show an icon if they haven't specified a website.
	if ($context['member']['website']['url'] !== '' && !isset($context['disabled_fields']['website']))
		echo '
					<li><a href="', $context['member']['website']['url'], '" title="' . $context['member']['website']['title'] . '" target="_blank" class="new_win">', ($theme['use_image_buttons'] ? '<img src="' . $theme['images_url'] . '/www_sm.gif" alt="' . $context['member']['website']['title'] . '">' : $txt['website']), '</a></li>';

	// Are there any custom profile fields for the summary?
	if (!empty($context['custom_fields']))
	{
		foreach ($context['custom_fields'] as $field)
			if (($field['placement'] == 1 || empty($field['output_html'])) && !empty($field['value']))
				echo '
					<li class="custom_field">', $field['output_html'], '</li>';
	}

	echo '
				</ul>
				<span id="userstatus">', $context['can_send_pm'] ? '<a href="' . $context['member']['online']['href'] . '" title="' . $context['member']['online']['label'] . '" rel="nofollow">' : '', $theme['use_image_buttons'] ? '<img src="' . $context['member']['online']['image_href'] . '" alt="' . $context['member']['online']['text'] . '" class="middle">' : $context['member']['online']['text'], $context['can_send_pm'] ? '</a>' : '', $theme['use_image_buttons'] ? '<span class="smalltext"> ' . $context['member']['online']['text'] . '</span>' : '';

	// Can they add this member as a buddy?
	if (!empty($context['can_have_buddy']) && !we::$user['is_owner'])
		echo '
					<br><a href="<URL>?action=buddy;u=', $context['id_member'], ';', $context['session_query'], '">[', $txt['buddy_' . ($context['member']['is_buddy'] ? 'remove' : 'add')], ']</a>';

	echo '
				</span>';

	echo '
				<p id="infolinks">';

	if (!we::$user['is_owner'] && $context['can_send_pm'])
		echo '
					<a href="<URL>?action=pm;sa=send;u=', $context['id_member'], '">', $txt['profile_sendpm_short'], '</a><br>';
	echo '
					<a href="<URL>?action=profile;u=', $context['id_member'], ';area=showposts">', $txt['showPosts'], '</a><br>
					<a href="<URL>?action=profile;u=', $context['id_member'], ';area=statistics">', $txt['statPanel'], '</a>
				</p>';

	echo '
			</div>
		</div>
	</div>
	<div id="detailedinfo">
		<div class="windowbg2 wrc">
			<dl>';

	if (we::$user['is_owner'] || we::$is_admin)
		echo '
				<dt>', $txt['username'], ': </dt>
				<dd>', $context['member']['username'], '</dd>';

	if (!isset($context['disabled_fields']['posts']))
		echo '
				<dt>', $txt['profile_posts'], ': </dt>
				<dd>', $context['member']['posts'], ' (', $context['member']['posts_per_day'], ' ', $txt['posts_per_day'], ')</dd>';

	// Only show the email address fully if the one looking at the profile is an admin they can see it anyway.
	if ($context['member']['show_email'] == 'yes_permission_override')
		echo '
				<dt>', $txt['email'], ': </dt>
				<dd><em><a href="<URL>?action=emailuser;sa=email;uid=', $context['member']['id'], '">', $context['member']['email'], '</a></em></dd>';

	if (!empty($settings['titlesEnable']) && !empty($context['member']['title']))
		echo '
				<dt>', $txt['custom_title'], ': </dt>
				<dd>', $context['member']['title'], '</dd>';

	if (!empty($context['member']['blurb']))
		echo '
				<dt>', $txt['personal_text'], ': </dt>
				<dd>', $context['member']['blurb'], '</dd>';

	if (!isset($context['disabled_fields']['gender']) && !empty($context['member']['gender']))
		echo '
				<dt>', $txt['gender'], ': </dt>
				<dd>', $txt[$context['member']['gender']], '</dd>';

	if ($context['member']['age'] !== $txt['not_applicable'])
		echo '
				<dt>', $txt['age'], ':</dt>
				<dd>', $context['member']['age'] . ($context['member']['today_is_birthday'] ? '<br><img src="' . $theme['images_url'] . '/cake.png">' : ''), '</dd>';

	if (!empty($context['member']['action']))
		echo '
				<dt>', $txt['current_action'], ':</dt>
				<dd>', $context['member']['action'], '</dd>';

	if (!isset($context['disabled_fields']['location']) && !empty($context['member']['location']))
		echo '
				<dt>', $txt['location'], ':</dt>
				<dd>', $context['member']['location'], '</dd>';

	echo '
			</dl>';

	// Any custom fields for standard placement?
	if (!empty($context['custom_fields']))
	{
		$shown = false;
		foreach ($context['custom_fields'] as $field)
		{
			if ($field['placement'] != 0 || empty($field['output_html']))
				continue;

			if (!$shown)
			{
				echo '
			<dl>';
				$shown = true;
			}

			echo '
				<dt>', $field['name'], ':</dt>
				<dd>', $field['output_html'], '</dd>';
		}

		if ($shown)
			echo '
			</dl>';
	}

	echo '
			<dl>';

	// Can they view/issue a warning?
	if ($context['can_view_warning'] && $context['member']['warning'])
	{
		echo '
				<dt>', $txt['profile_warning_level'], ': </dt>
				<dd>
					<a href="<URL>?action=profile;u=', $context['id_member'], ';area=', $context['can_issue_warning'] ? 'issuewarning' : 'viewwarning', '">', $context['member']['warning'], '%</a>';

		// Can we provide information on what this means?
		if (!empty($context['warning_status']))
			echo '
					<span class="smalltext">(', $context['warning_status'], ')</span>';

		echo '
				</dd>';
	}

	// Is this member requiring activation and/or banned?
	if (!empty($context['activate_message']) || !empty($context['member']['bans']))
	{

		// If the person looking at the summary has permission, and the account isn't activated, give the viewer the ability to do it themselves.
		if (!empty($context['activate_message']))
			echo '
				<dt class="clear"><span class="alert">', $context['activate_message'], '</span>&nbsp;(<a href="<URL>?action=profile;u=' . $context['id_member'] . ';save;area=activateaccount;' . $context['session_query'] . '"', ($context['activate_type'] == 4 ? ' onclick="return ask(' . JavaScriptEscape($txt['profileConfirm']) . ', e);"' : ''), '>', $context['activate_link_text'], '</a>)</dt>';

		// If the current member is banned, show a message and possibly a link to the ban.
		if (!empty($context['member']['bans']))
		{
			echo '
				<dt class="clear"><span class="alert">', $txt['user_is_banned'], '</span>&nbsp;[<a href="#" onclick="$(\'#ban_info\').toggle(); return false;">' . $txt['view_ban'] . '</a>]</dt>
				<dt class="clear hide" id="ban_info">
					<strong>', $txt['user_banned_by_following'], ':</strong>';

			foreach ($context['member']['bans'] as $ban)
				echo '
					<dfn>', $ban['ban_reason'], ' (<a href="<URL>?action=admin;area=ban;sa=edit;ban=', $ban['id_ban'], '">', $txt['modify'], '</a>)</dfn>';

			echo '
				</dt>';
		}
	}

	echo '
				<dt>', $txt['date_registered'], ': </dt>
				<dd>', $context['member']['registered'], '</dd>';

	// If the person looking is allowed, they can check the members IP address and hostname.
	if ($context['can_see_ip'])
	{
		if (!empty($context['member']['ip']))
		echo '
				<dt>', $txt['ip'], ': </dt>
				<dd><a href="<URL>?action=profile;u=', $context['member']['id'], ';area=tracking;sa=ip;searchip=', $context['member']['ip'], '">', $context['member']['ip'], '</a></dd>';

		if (empty($settings['disableHostnameLookup']) && !empty($context['member']['ip']))
			echo '
				<dt>', $txt['hostname'], ': </dt>
				<dd>', $context['member']['hostname'], '</dd>';
	}

	echo '
				<dt>', $txt['local_time'], ':</dt>
				<dd>', $context['member']['local_time'], '</dd>';

	if (!empty($settings['userLanguage']) && !empty($context['member']['language']))
		echo '
				<dt>', $txt['language'], ':</dt>
				<dd>', $context['member']['language'], '</dd>';

	echo '
				<dt>', $txt['lastLoggedIn'], ': </dt>
				<dd>', $context['member']['last_login'], '</dd>
			</dl>';

	// Are there any custom profile fields for the summary?
	if (!empty($context['custom_fields']))
	{
		$shown = false;
		foreach ($context['custom_fields'] as $field)
		{
			if ($field['placement'] != 2 || empty($field['output_html']))
				continue;
			if (!$shown)
			{
				$shown = true;
				echo '
			<div class="custom_fields_above_signature">
				<ul class="reset nolist">';
			}
			echo '
					<li>', $field['output_html'], '</li>';
		}
		if ($shown)
				echo '
				</ul>
			</div>';
	}

	// Show the users signature.
	if ($context['signature_enabled'] && !empty($context['member']['signature']))
		echo '
			<div class="signature" style="padding: 0; border: 0">
				<h5>', $txt['signature'], ':</h5>
				', $context['member']['signature'], '
			</div>';

	echo '
		</div>
	</div>
	<div class="clear"></div>';
}

// Template for showing all the user's drafts.
function template_showDrafts()
{
	global $context, $theme, $settings, $txt;

	echo '
		<we:cat>
			<img src="', $theme['images_url'], '/icons/im_newmsg.gif">
			', $txt['showDrafts'], ' - ', $context['member']['name'], '
		</we:cat>
		<p class="description">
			', $txt['showDrafts_desc'];

	if (!empty($settings['pruneSaveDrafts']))
		echo '
			<br><br>', number_context('draftAutoPurge', $settings['pruneSaveDrafts']);

	echo '
		</p>

		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';

	$remove_confirm = JavaScriptEscape($txt['remove_message_confirm']);

	// For every post to be displayed, give it its own subtable, and show the important details of the post.
	foreach ($context['posts'] as $post)
	{
		echo '
		<div class="topic">
			<div class="windowbg', $post['alternate'] == 0 ? '2' : '', ' wrc core_posts">
				<div class="counter">', $post['counter'], '</div>
				<div class="topic_details">
					<h5><strong>', $post['board']['link'], ' / ', $post['topic']['link'], '</strong></h5>
					<span class="smalltext">&#171;&nbsp;', $post['on_time'], '&nbsp;&#187;</span>
				</div>
				<div class="list_posts">
					', $post['body'], '
				</div>';

		if ($post['topic']['no_edit'])
			echo '
				<div class="smalltext">', $post['topic']['locked'] ? $txt['topic_is_locked'] : $txt['topic_no_longer_available'], '</div>';

		echo '
				<div class="actionbar">
					<ul class="actions">
						<li><a href="<URL>?action=post;', ($post['topic']['no_edit'] || empty($post['topic']['id'])) ? 'board=' . $post['board']['id'] : 'topic=' . $post['topic']['original_topic'], '.0;draft_id=', $post['id'], '" class="reply_button">', $txt['edit_draft'], '</a></li>
						<li><a href="<URL>?action=profile;u=', $context['member']['id'], ';area=showdrafts;delete=', $post['id'], ';', $context['session_query'], '" class="remove_button" onclick="return ask(', $remove_confirm, ', e);">', $txt['remove_draft'], '</a></li>
					</ul>
				</div>
			</div>
		</div>';
	}

	// No drafts? Just end the table with an informative message.
	if (empty($context['posts']))
		echo '
		<div class="windowbg2 padding center">
			', $txt['show_drafts_none'], '
		</div>';

	// Show more page numbers.
	echo '
		<div class="pagesection" style="margin-bottom: 0">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';

	// A great, big, threatening button which must not be pressed under any circumstances, am I right?
	if (!empty($context['posts']))
		echo '
		<div class="right padding">
			<form action="<URL>?action=profile;u=', $context['member']['id'], ';area=showdrafts;deleteall" method="post" onsubmit="return ask(', JavaScriptEscape($txt['remove_all_drafts_confirm']), ', e);">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" value="', $txt['remove_all_drafts'], '" class="delete">
			</form>
		</div>';
}

// Template for showing all the posts of the user, in chronological order.
function template_showPosts()
{
	global $context, $theme, $txt;

	echo '
		<we:cat>
			<img src="', $theme['images_url'], '/icons/profile_sm.gif">
			', !isset($context['attachments']) && empty($context['is_topics']) ? $txt['showMessages'] : (!empty($context['is_topics']) ? $txt['showTopics'] : $txt['showAttachments']), ' - ', !empty($_GET['guest']) ? base64_decode($_GET['guest']) : $context['member']['name'], '
		</we:cat>
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';

	$remove_confirm = JavaScriptEscape($txt['remove_message_confirm']);

	// Are we displaying posts or attachments?
	if (!isset($context['attachments']))
	{
		// For every post to be displayed, give it its own div, and show the important details of the post.
		foreach ($context['posts'] as $post)
		{
			echo '
		<div class="topic">
			<div class="windowbg', $post['alternate'] == 0 ? '2' : '', ' wrc core_posts">
				<div class="counter">', $post['counter'], '</div>
				<div class="topic_details">
					<h5><strong><a href="<URL>?board=', $post['board']['id'], '.0">', $post['board']['name'], '</a> / <a href="<URL>?topic=', $post['topic'], '.', $post['start'], '#msg', $post['id'], '">', $post['subject'], '</a></strong></h5>
					<span class="smalltext">&#171;&nbsp;', $post['on_time'], '&nbsp;&#187;</span>
				</div>
				<div class="list_posts">';

			if (!$post['approved'])
				echo '
					<div class="approve_post">
						<em>', $txt['post_awaiting_approval'], '</em>
					</div>';

			echo '
					', $post['body'], '
				</div>';

			if ($post['can_reply'] || $post['can_quote'] || $post['can_mark_notify'] || $post['can_delete'])
			{
				echo '
				<div class="actionbar">
					<ul class="actions">';

				// If they *can* reply?
				if ($post['can_reply'])
					echo '
						<li><a href="<URL>?action=post;topic=', $post['topic'], '.', $post['start'], '" class="reply_button">', $txt['reply'], '</a></li>';

				// If they *can* quote?
				if ($post['can_quote'])
					echo '
						<li><a href="<URL>?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '" class="quote_button">', $txt['quote'], '</a></li>';

				// Can we request notification of topics?
				if ($post['can_mark_notify'])
					echo '
						<li><a href="<URL>?action=notify;topic=', $post['topic'], '.', $post['start'], '" class="notify_button">', $txt['notify'], '</a></li>';

				// How about... even... remove it entirely?!
				if ($post['can_delete'])
					echo '
						<li><a href="<URL>?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';profile;u=', $context['member']['id'], ';start=', $context['start'], ';', $context['session_query'], '" class="remove_button" onclick="return ask(', $remove_confirm, ', e);">', $txt['remove'], '</a></li>';

				echo '
					</ul>
				</div>';
			}

			echo '
			</div>
		</div>';
		}
	}
	else
	{
		echo '
		<table class="table_grid w100 cs1 cp2 center">
			<thead>
				<tr class="titlebg">
					<th class="first_th left w25" scope="col">
						<a href="<URL>?action=profile;u=', $context['current_member'], ';area=showposts;sa=attach;sort=filename', ($context['sort_direction'] == 'down' && $context['sort_order'] == 'filename' ? ';asc' : ''), '">
							', $txt['show_attach_filename'], '
							', ($context['sort_order'] == 'filename' ? '<span class="sort_' . ($context['sort_direction'] == 'down' ? 'down' : 'up') . '"></span>' : ''), '
						</a>
					</th>
					<th scope="col" style="width: 12%">
						<a href="<URL>?action=profile;u=', $context['current_member'], ';area=showposts;sa=attach;sort=downloads', ($context['sort_direction'] == 'down' && $context['sort_order'] == 'downloads' ? ';asc' : ''), '">
							', $txt['show_attach_downloads'], '
							', ($context['sort_order'] == 'downloads' ? '<span class="sort_' . ($context['sort_direction'] == 'down' ? 'down' : 'up') . '"></span>' : ''), '
						</a>
					</th>
					<th class="left" scope="col" style="width: 30%">
						<a href="<URL>?action=profile;u=', $context['current_member'], ';area=showposts;sa=attach;sort=subject', ($context['sort_direction'] == 'down' && $context['sort_order'] == 'subject' ? ';asc' : ''), '">
							', $txt['message'], '
							', ($context['sort_order'] == 'subject' ? '<span class="sort_' . ($context['sort_direction'] == 'down' ? 'down' : 'up') . '"></span>' : ''), '
						</a>
					</th>
					<th class="last_th left" scope="col">
						<a href="<URL>?action=profile;u=', $context['current_member'], ';area=showposts;sa=attach;sort=posted', ($context['sort_direction'] == 'down' && $context['sort_order'] == 'posted' ? ';asc' : ''), '">
						', $txt['show_attach_posted'], '
						', ($context['sort_order'] == 'posted' ? '<span class="sort_' . ($context['sort_direction'] == 'down' ? 'down' : 'up') . '"></span>' : ''), '
						</a>
					</th>
				</tr>
			</thead>
			<tbody>';

		// Looks like we need to do all the attachments instead!
		$alternate = false;
		foreach ($context['attachments'] as $attachment)
		{
			echo '
				<tr class="windowbg', $alternate ? '' : '2', '">
					<td><a href="<URL>?action=dlattach;topic=', $attachment['topic'], '.0;attach=', $attachment['id'], '">', $attachment['filename'], '</a>', '</td>
					<td>', $attachment['downloads'], '</td>
					<td><a href="<URL>?topic=', $attachment['topic'], '.msg', $attachment['msg'], '#msg', $attachment['msg'], '" rel="nofollow">', $attachment['subject'], '</a></td>
					<td>', $attachment['posted'], '</td>
				</tr>';
			$alternate = !$alternate;
		}

		// No posts? Just end the table with an informative message.
		if ((isset($context['attachments']) && empty($context['attachments'])) || (!isset($context['attachments']) && empty($context['posts'])))
			echo '
				<tr>
					<td class="windowbg2 padding center" colspan="4">
						', isset($context['attachments']) ? $txt['show_attachments_none'] : ($context['is_topics'] ? $txt['show_topics_none'] : $txt['show_posts_none']), '
					</td>
				</tr>';

		echo '
			</tbody>
		</table>';
	}

	// Show more page numbers.
	echo '
		<div class="pagesection" style="margin-bottom: 0">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';
}

// Template for showing all the buddies of the current user.
function template_editBuddies()
{
	global $context, $theme, $txt;

	echo '
		<we:cat>
			<img src="', $theme['images_url'], '/icons/profile_sm.gif">', $txt['editBuddies'], '
		</we:cat>

		<table class="table_grid w100 cs1 cp4 center">
			<tr class="catbg">
				<th class="first_th left" scope="col" style="width: 20%">', $txt['name'], '</th>
				<th scope="col">', $txt['online_status'], '</th>
				<th scope="col">', $txt['email'], '</th>
				<th class="last_th" scope="col">', $txt['buddy_remove'], '</th>
			</tr>';

	// If they don't have any buddies don't list them!
	if (empty($context['buddies']))
		echo '
			<tr class="windowbg2">
				<td colspan="8" class="center"><strong>', $txt['no_buddies'], '</strong></td>
			</tr>';

	// Now loop through each buddy showing info on each.
	$alternate = false;
	foreach ($context['buddies'] as $buddy)
	{
		echo '
			<tr class="', $alternate ? 'windowbg' : 'windowbg2', ' center">
				<td class="left">', $buddy['link'], '</td>
				<td><a href="', $buddy['online']['href'], '"><img src="', $buddy['online']['image_href'], '" alt="', $buddy['online']['label'], '" title="', $buddy['online']['label'], '"></a></td>
				<td>', ($buddy['show_email'] == 'no' ? '' : '<a href="<URL>?action=emailuser;sa=email;uid=' . $buddy['id'] . '" rel="nofollow"><img src="' . $theme['images_url'] . '/email_sm.gif" alt="' . $txt['email'] . '" title="' . $txt['email'] . ' ' . $buddy['name'] . '"></a>'), '</td>
				<td><a href="<URL>?action=profile;u=', $context['id_member'], ';area=lists;sa=buddies;remove=', $buddy['id'], ';', $context['session_query'], '"><span class="remove_button" style="display: inline-block"></span></a></td>
			</tr>';

		$alternate = !$alternate;
	}

	echo '
		</table>';

	// Add a new buddy?
	echo '
	<br>
	<form action="<URL>?action=profile;u=', $context['id_member'], ';area=lists;sa=buddies" method="post" accept-charset="UTF-8">
		<div class="add_buddy">
			<we:title>
				', $txt['buddy_add'], '
			</we:title>
			<div class="roundframe">
				<label>
					<strong>', $txt['who_member'], ':</strong>
					<input name="new_buddy" id="new_buddy" size="25">
				</label>
				<input type="submit" value="', $txt['buddy_add_button'], '" class="new">
			</div>
		</div>
	</form>';

	add_js_file('scripts/suggest.js');
	add_js('
	new weAutoSuggest({
		sControlId: \'new_buddy\'
	});');
}

// Template for showing the ignore list of the current user.
function template_editIgnoreList()
{
	global $context, $theme, $txt;

	echo '
		<we:cat>
			<img src="', $theme['images_url'], '/icons/profile_sm.gif">', $txt['editIgnoreList'], '
		</we:cat>
		<table class="table_grid w100 cs1 cp4 center">
			<tr class="catbg">
				<th class="first_th" scope="col" style="width: 20%">', $txt['name'], '</th>
				<th scope="col">', $txt['online_status'], '</th>
				<th scope="col">', $txt['email'], '</th>
				<th class="last_th" scope="col">', $txt['ignore_remove'], '</th>
			</tr>';

	// If they don't have anyone on their ignore list, don't list it!
	if (empty($context['ignore_list']))
		echo '
			<tr class="windowbg2">
				<td colspan="8" class="center"><strong>', $txt['no_ignore'], '</strong></td>
			</tr>';

	// Now loop through each buddy showing info on each.
	$alternate = false;
	foreach ($context['ignore_list'] as $member)
	{
		echo '
			<tr class="windowbg', $alternate ? '' : '2', '">
				<td class="left">', $member['link'], '</td>
				<td><a href="', $member['online']['href'], '"><img src="', $member['online']['image_href'], '" alt="', $member['online']['label'], '" title="', $member['online']['label'], '"></a></td>
				<td>', ($member['show_email'] == 'no' ? '' : '<a href="<URL>?action=emailuser;sa=email;uid=' . $member['id'] . '" rel="nofollow"><img src="' . $theme['images_url'] . '/email_sm.gif" alt="' . $txt['email'] . '" title="' . $txt['email'] . ' ' . $member['name'] . '"></a>'), '</td>
				<td><a href="<URL>?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore;remove=', $member['id'], ';', $context['session_query'], '"><span class="remove_button" style="display: inline-block"></span></a></td>
			</tr>';

		$alternate = !$alternate;
	}

	echo '
		</table>';

	// Add a new buddy?
	echo '
	<br>
	<form action="<URL>?action=profile;u=', $context['id_member'], ';area=lists;sa=ignore" method="post" accept-charset="UTF-8">
		<div class="add_buddy">
			<we:title>
				', $txt['ignore_add'], '
			</we:title>
			<div class="roundframe">
				<label>
					<strong>', $txt['who_member'], ':</strong>
					<input name="new_ignore" id="new_ignore" size="25">
				</label>
				<input type="submit" value="', $txt['ignore_add_button'], '" class="new">
			</div>
		</div>
	</form>';

	add_js_file('scripts/suggest.js');
	add_js('
	new weAutoSuggest({
		sControlId: \'new_ignore\'
	});');
}

// This template shows an admin information on a users IP addresses used and errors attributed to them.
function template_trackActivity()
{
	global $context, $txt;

	// The first table shows IP information about the user.
	echo '
			<we:title>
				', $txt['view_ips_by'], ' <strong>', $context['member']['name'], '</strong>
			</we:title>';

	// The last IP the user used.
	echo '
			<div id="tracking" class="windowbg2 wrc">
				<dl class="noborder">
					<dt>
						', $txt['most_recent_ip'], ':', (empty($context['last_ip2']) ? '' : '
						<dfn>(<a href="<URL>?action=help;in=whytwoip" onclick="return reqWin(this);">' . $txt['why_two_ip_address'] . '</a>)</dfn>'), '
					</dt>
					<dd>
						<a href="<URL>?action=profile;u=', $context['member']['id'], ';area=tracking;sa=ip;searchip=', $context['last_ip'], '">', $context['last_ip'], '</a>';

	// Second address detected?
	if (!empty($context['last_ip2']))
		echo ',
						<a href="<URL>?action=profile;u=', $context['member']['id'], ';area=tracking;sa=ip;searchip=', $context['last_ip2'], '">', $context['last_ip2'], '</a>';

	echo '
					</dd>';

	// Lists of IP addresses used in messages / error messages.
	echo '
					<dt>', $txt['ips_in_messages'], ':</dt>
					<dd>
						', count($context['ips']) > 0 ? implode(', ', $context['ips']) : '(' . $txt['none'] . ')', '
					</dd>
					<dt>', $txt['ips_in_errors'], ':</dt>
					<dd>
						', count($context['ips']) > 0 ? implode(', ', $context['error_ips']) : '(' . $txt['none'] . ')', '
					</dd>';

	// List any members that have used the same IP addresses as the current member.
	echo '
					<dt>', $txt['members_in_range'], ':</dt>
					<dd>
						', count($context['members_in_range']) > 0 ? implode(', ', $context['members_in_range']) : '(' . $txt['none'] . ')', '
					</dd>
				</dl>
			</div>
			<br>';

	// Show the track user list.
	template_show_list('track_user_list');
}

// The template for trackIP, allowing the admin to see where/who a certain IP has been used.
function template_trackIP()
{
	global $context, $txt;

	// This function always defaults to the last IP used by a member but can be set to track any IP.
	// The first table in the template gives an input box to allow the admin to enter another IP to track.
	echo '
		<we:cat>
			', $txt['trackIP'], '
		</we:cat>
		<div class="windowbg2 wrc">
			<form action="', $context['base_url'], '" method="post" accept-charset="UTF-8">
				', $txt['enter_ip'], ':&nbsp;&nbsp;<input type="search" name="searchip" value="', $context['ip'], '" class="search">&nbsp;&nbsp;<input type="submit" value="', $txt['trackIP'], '" class="submit">
			</form>
		</div>
		<br>';

	// The table inbetween the first and second table shows links to the whois server for every region.
	if ($context['single_ip'])
	{
		echo '
		<we:title>
			', sprintf($txt['whois_title'], $context['ip']), '
		</we:title>
		<div class="windowbg2 wrc">';

		foreach ($context['whois_servers'] as $server)
			echo '
			<a href="', $server['url'], '" target="_blank" class="new_win"', isset($context['auto_whois_server']) && $context['auto_whois_server']['name'] == $server['name'] ? ' style="font-weight: bold"' : '', '>', $server['name'], '</a><br>';

		echo '
		</div>
		<br>';
	}

	// The second table lists all the members who have been logged as using this IP address.
	echo '
		<we:title>
			', sprintf($txt['members_from_ip'], $context['ip']), '
		</we:title>';
	if (empty($context['ips']))
		echo '
		<p class="description"><em>', $txt['no_members_from_ip'], '</em></p>';
	else
	{
		echo '
		<table class="table_grid w100 cs0">
			<thead>
				<tr class="catbg">
					<th class="first_th" scope="col">', $txt['ip_address'], '</th>
					<th class="last_th" scope="col">', $txt['display_name'], '</th>
				</tr>
			</thead>
			<tbody>';

		// Loop through each of the members and display them.
		foreach ($context['ips'] as $ip => $memberlist)
			echo '
				<tr>
					<td class="windowbg2"><a href="', $context['base_url'], ';searchip=', $ip, '">', $ip, '</a></td>
					<td class="windowbg2">', implode(', ', $memberlist), '</td>
				</tr>';

		echo '
			</tbody>
		</table>
		<br>';
	}

	template_show_list('track_message_list');

	echo '<br>';

	template_show_list('track_user_list');
}

function template_showPermissions()
{
	global $context, $theme, $txt;

	echo '
		<we:cat>
			<img src="', $theme['images_url'], '/icons/profile_sm.gif">
			', $txt['showPermissions'], '
		</we:cat>';

	if ($context['member']['has_all_permissions'])
		echo '
		<p class="description">', $txt['showPermissions_all'], '</p>';
	else
	{
		echo '
		<p class="description">', $txt['showPermissions_help'], '</p>
		<div id="permissions" class="flow_hidden">';

		if (!empty($context['no_access_boards']))
		{
			echo '
			<we:cat>
				', $txt['showPermissions_restricted_boards'], '
			</we:cat>
			<div class="windowbg wrc smalltext">
				', $txt['showPermissions_restricted_boards_desc'], ':<br>';

			foreach ($context['no_access_boards'] as $no_access_board)
				echo '
				<a href="<URL>?board=', $no_access_board['id'], '.0">', $no_access_board['name'], '</a>', $no_access_board['is_last'] ? '' : ',';

			echo '
			</div>';
		}

		// General Permissions section.
		echo '
			<we:cat>
				', $txt['showPermissions_general'], '
			</we:cat>';

		if (!empty($context['member']['permissions']['general']))
		{
			echo '
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="titlebg left">
						<th class="first_th w50" scope="col">', $txt['showPermissions_permission'], '</th>
						<th class="last_th w50" scope="col">', $txt['showPermissions_status'], '</th>
					</tr>
				</thead>
				<tbody>';

			foreach ($context['member']['permissions']['general'] as $permission)
			{
				echo '
					<tr>
						<td class="windowbg" title="', $permission['id'], '">
							', $permission['is_denied'] ? '<del>' . $permission['name'] . '</del>' : $permission['name'], '
						</td>
						<td class="windowbg2 smalltext">';

				if ($permission['is_denied'])
					echo '
							<span class="alert">', $txt['showPermissions_denied'], ':&nbsp;', implode(', ', $permission['groups']['denied']), '</span>';
				else
					echo '
							', $txt['showPermissions_given'], ':&nbsp;', implode(', ', $permission['groups']['allowed']);

					echo '
						</td>
					</tr>';
			}
			echo '
				</tbody>
			</table>
			<br>';
		}
		else
			echo '
			<p class="description">', $txt['showPermissions_none_general'], '</p>';

		// Board permission section.
		echo '
			<form action="<URL>?action=profile;u=', $context['id_member'], ';area=permissions#board_permissions" method="post" accept-charset="UTF-8">
				<we:cat>
					<a id="board_permissions"></a>', $txt['showPermissions_select'], ':
					<select name="board" onchange="if ($(this).val()) this.form.submit();">
						<option value="0"', $context['board'] == 0 ? ' selected' : '', ' data-hide>', $txt['showPermissions_global'], '</option>';

		// Fill the box with any local permission boards.
		foreach ($context['boards'] as $board)
			echo '
						<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['name'], ' (', $board['profile_name'], ')</option>';

		echo '
					</select>
				</we:cat>
			</form>';

		if (!empty($context['member']['permissions']['board']))
		{
			echo '
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="titlebg left">
						<th class="first_th w50" scope="col">', $txt['showPermissions_permission'], '</th>
						<th class="last_th w50" scope="col">', $txt['showPermissions_status'], '</th>
					</tr>
				</thead>
				<tbody>';

			foreach ($context['member']['permissions']['board'] as $permission)
			{
				echo '
					<tr>
						<td class="windowbg" title="', $permission['id'], '">
							', $permission['is_denied'] ? '<del>' . $permission['name'] . '</del>' : $permission['name'], '
						</td>
						<td class="windowbg2 smalltext">';

				if ($permission['is_denied'])
					echo '
							<span class="alert">', $txt['showPermissions_denied'], ':&nbsp;', implode(', ', $permission['groups']['denied']), '</span>';
				else
					echo '
							', $txt['showPermissions_given'], ': &nbsp;', implode(', ', $permission['groups']['allowed']);

				echo '
						</td>
					</tr>';
			}
			echo '
				</tbody>
			</table>';
		}
		else
			echo '
			<p class="description">', $txt['showPermissions_none_board'], '</p>';

	echo '
		</div>';
	}
}

// Template for user statistics, showing graphs and the like.
function template_statPanel()
{
	global $context, $theme, $txt;

	// First, show a few text statistics such as post/topic count.
	echo '
		<div id="generalstats">
			<we:cat>
				<img src="', $theme['images_url'], '/stats_info.gif">
				', $txt['statPanel_generalStats'], ' - ', $context['member']['name'], '
			</we:cat>
			<div class="windowbg2 wrc">
				<dl>
					<dt>', $txt['statPanel_total_time_online'], ':</dt>
					<dd>', $context['time_logged_in'], '</dd>
					<dt>', $txt['statPanel_total_posts'], ':</dt>
					<dd>', $context['num_posts'], ' ', $txt['statPanel_posts'], '</dd>
					<dt>', $txt['statPanel_total_topics'], ':</dt>
					<dd>', $context['num_topics'], ' ', $txt['statPanel_topics'], '</dd>
					<dt>', $txt['statPanel_users_polls'], ':</dt>
					<dd>', $context['num_polls'], ' ', $txt['statPanel_polls'], '</dd>
					<dt>', $txt['statPanel_users_votes'], ':</dt>
					<dd>', $context['num_votes'], ' ', $txt['statPanel_votes'], '</dd>
				</dl>
			</div>
		</div>';

	// This next section draws a graph showing what times of day they post the most.
	echo '
		<div id="activitytime" class="flow_hidden">
			<we:cat>
				<img src="', $theme['images_url'], '/stats_history.gif">
				', $txt['statPanel_activityTime'], '
			</we:cat>
			<div class="windowbg2 wrc">';

	// If they haven't posted at all, don't draw the graph.
	if (empty($context['posts_by_time']))
		echo '
				<span>', $txt['statPanel_noPosts'], '</span>';

	// Otherwise do!
	else
	{
		echo '
				<ul class="activity_stats flow_hidden">';

		// The labels.
		foreach ($context['posts_by_time'] as $time_of_day)
		{
			echo '
					<li', $time_of_day['is_last'] ? ' class="last"' : '', '>
						<div class="bar" style="height: ', (int) $time_of_day['relative_percent'], 'px; margin-top: ', (int) (100 - $time_of_day['relative_percent']), 'px" title="', sprintf($txt['statPanel_activityTime_posts'], $time_of_day['posts'], $time_of_day['posts_percent']), '"></div>
						<span class="stats_hour">', $time_of_day['hour_format'], '</span>
					</li>';
		}

		echo '

				</ul>';
	}

	echo '
				<div class="clear"></div>
			</div>
		</div>';

	// Two columns with the most popular boards by posts and activity (activity = users posts / total posts).
	echo '
		<div class="flow_hidden">
			<div id="popularposts">
				<we:cat>
					<img src="', $theme['images_url'], '/stats_replies.gif">
					', $txt['statPanel_topBoards'], '
				</we:cat>
				<div class="windowbg2 wrc">';

	if (empty($context['popular_boards']))
		echo '
					<span>', $txt['statPanel_noPosts'], '</span>';

	else
	{
		echo '
					<dl>';

		// Draw a bar for every board.
		foreach ($context['popular_boards'] as $board)
		{
			echo '
						<dt>', $board['link'], '</dt>
						<dd>
							<div class="profile_pie" style="background-position: -', ((int) ($board['posts_percent'] / 5) * 20), 'px 0" title="', sprintf($txt['statPanel_topBoards_memberposts'], $board['posts'], $board['total_posts_member'], $board['posts_percent']), '">
								', sprintf($txt['statPanel_topBoards_memberposts'], $board['posts'], $board['total_posts_member'], $board['posts_percent']), '
							</div>
							<span>', empty($context['hide_num_posts']) ? $board['posts'] : '', '</span>
						</dd>';
		}

		echo '
					</dl>';
	}
	echo '
				</div>
			</div>';
	echo '
			<div id="popularactivity">
				<we:cat>
					<img src="', $theme['images_url'], '/stats_replies.gif">
					', $txt['statPanel_topBoardsActivity'], '
				</we:cat>
				<div class="windowbg2 wrc">';

	if (empty($context['board_activity']))
		echo '
					<span>', $txt['statPanel_noPosts'], '</span>';
	else
	{
		echo '
					<dl>';

		// Draw a bar for every board.
		foreach ($context['board_activity'] as $activity)
		{
			echo '
						<dt>', $activity['link'], '</dt>
						<dd>
							<div class="profile_pie" style="background-position: -', ((int) ($activity['percent'] / 5) * 20), 'px 0" title="', sprintf($txt['statPanel_topBoards_posts'], $activity['posts'], $activity['total_posts'], $activity['posts_percent']), '">
								', sprintf($txt['statPanel_topBoards_posts'], $activity['posts'], $activity['total_posts'], $activity['posts_percent']), '
							</div>
							<span>', $activity['percent'], '%</span>
						</dd>';
		}

		echo '
					</dl>';
	}
	echo '
				</div>
			</div>
		</div>
		<div class="clear"></div>';
}

// Template for editing profile options.
function template_edit_options()
{
	global $context, $theme, $txt;

	// The main header!
	echo '
		<form action="', (!empty($context['profile_custom_submit_url']) ? $context['profile_custom_submit_url'] : '<URL>?action=profile;u=' . $context['id_member'] . ';area=' . $context['menu_item_selected'] . ';save'), '" method="post" accept-charset="UTF-8" name="creator" id="creator" enctype="multipart/form-data" onsubmit="return checkProfileSubmit();">
			<we:cat>
				<img src="', $theme['images_url'], '/icons/profile_sm.gif">';

		// Don't say "Profile" if this isn't the profile...
		if (!empty($context['profile_header_text']))
			echo '
				', $context['profile_header_text'];
		else
			echo '
				', $txt['profile'];

		echo '
			</we:cat>';

	// Have we some description?
	if ($context['page_desc'])
		echo '
			<p class="description">', $context['page_desc'], '</p>';

	echo '
			<div class="windowbg2 wrc">';

	// Any bits at the start?
	if (!empty($context['profile_prehtml']))
		echo '
				<div>', $context['profile_prehtml'], '</div>';

	if (!empty($context['profile_fields']))
		echo '
				<dl>';

	// Start the big old loop 'of love.
	$lastItem = 'hr';
	foreach ($context['profile_fields'] as $key => $field)
	{
		// We add a little hack to be sure we never get more than one hr in a row!
		if ($lastItem == 'hr' && $field['type'] == 'hr')
			continue;

		$lastItem = $field['type'];
		if ($field['type'] == 'hr')
		{
			echo '
				</dl>
				<hr>
				<dl>';
		}
		elseif ($field['type'] == 'callback')
		{
			if (isset($field['callback_func']) && function_exists('template_profile_' . $field['callback_func']))
			{
				$callback_func = 'template_profile_' . $field['callback_func'];
				$callback_func();
			}
		}
		else
		{
			echo '
					<dt>
						<strong', !empty($field['is_error']) ? ' class="error"' : '', '>', $field['label'], '</strong>';

			// Does it have any subtext to show?
			if (!empty($field['subtext']))
				echo '
						<dfn>', $field['subtext'], '</dfn>';

			echo '
					</dt>
					<dd>';

			// Want to put something infront of the box?
			if (!empty($field['preinput']))
				echo '
						', $field['preinput'];

			// What type of data are we showing?
			if ($field['type'] == 'label')
				echo '
						', $field['value'];

			// Maybe it's a text box - very likely!
			elseif (in_array($field['type'], array('int', 'float', 'text', 'password', 'url', 'email')))
				echo '
						<input', $field['type'] == 'text' || $field['type'] == 'int' || $field['type'] == 'float' ? '' : ' type="' . $field['type'] . '"', ' name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '"', $field['input_attr'], '>';

			// You "checking" me out? ;)
			elseif ($field['type'] == 'check')
				echo '
						<input type="hidden" name="', $key, '" value="0"><input type="checkbox" name="', $key, '" id="', $key, '"', !empty($field['value']) ? ' checked' : '', ' value="1"', $field['input_attr'], '>';

			// Always fun - select boxes!
			elseif ($field['type'] == 'select')
			{
				echo '
						<select name="', $key, '" id="', $key, '"', !empty($field['class']) ? ' class="' . $field['class'] . '"' : '', '>';

				if (isset($field['options']))
				{
					// Is this some code to generate the options?
					if (!is_array($field['options']))
						$field['options'] = eval($field['options']);
					// Assuming we now have some!
					if (is_array($field['options']))
						foreach ($field['options'] as $value => $name)
							echo '
							<option value="', $value, '"', $value == $field['value'] ? ' selected' : '', '>', $name, '</option>';
				}

				echo '
						</select>';
			}

			// Something to end with?
			if (!empty($field['postinput']))
				echo '
						', $field['postinput'];

			echo '
					</dd>';
		}
	}

	if (!empty($context['profile_fields']))
		echo '
				</dl>';

	// Are there any custom profile fields - if so print them!
	if (!empty($context['custom_fields']))
	{
		if ($lastItem != 'hr')
			echo '
				<hr>';

		echo '
				<dl>';

		foreach ($context['custom_fields'] as $field)
		{
			echo '
					<dt>
						<strong>', $field['name'], ': </strong>
						<dfn>', $field['desc'], '</dfn>
					</dt>
					<dd>
						', $field['input_html'], '
					</dd>';
		}

		echo '
				</dl>';

	}

	// Any closing HTML?
	if (!empty($context['profile_posthtml']))
		echo '
				<div>', $context['profile_posthtml'], '</div>';
	elseif ($lastItem != 'hr')
		echo '
				<hr>';

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
				<dl>
					<dt>
						<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '>', $txt['current_password'], ': </strong>
						<dfn>', $txt['required_security_reasons'], '</dfn>
					</dt>
					<dd>
						<input type="password" name="oldpasswrd" size="20" style="margin-right: 4ex">
					</dd>
				</dl>';

	echo '
				<div class="right">';

	// The button shouldn't say "Change profile" unless we're changing the profile...
	if (!empty($context['submit_button_text']))
		echo '
					<input type="submit" value="', $context['submit_button_text'], '" class="submit">';
	else
		echo '
					<input type="submit" value="', $txt['change_profile'], '" class="save">';

	echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="u" value="', $context['id_member'], '">
					<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
				</div>
			</div>
			<br>
		</form>';

	// Some JavaScript!
	add_js_inline('
	function checkProfileSubmit()
	{');

	// If this part requires a password, make sure to give a warning.
	if ($context['require_password'])
		add_js_inline('
		if (document.forms.creator.oldpasswrd.value == "")
		{
			say(', JavaScriptEscape($txt['required_security_reasons']), ');
			return false;
		}');

	add_js_inline('
	}');
}

// Personal Message settings.
function template_profile_pm_settings()
{
	global $context, $settings, $txt;

	echo '
								<dt>
										<label for="pm_prefs">', $txt['pm_display_mode'], ':</label>
								</dt>
								<dd>
										<select name="pm_prefs" id="pm_prefs">
											<option value="0"', $context['display_mode'] == 0 ? ' selected' : '', '>', $txt['pm_display_mode_all'], '</option>
											<option value="1"', $context['display_mode'] == 1 ? ' selected' : '', '>', $txt['pm_display_mode_one'], '</option>
											<option value="2"', $context['display_mode'] == 2 ? ' selected' : '', '>', $txt['pm_display_mode_linked'], '</option>
										</select>
								</dd>
								<dt>
										<label for="view_newest_pm_first">', $txt['view_newest_pm_first'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[view_newest_pm_first]" value="0">
										<input type="checkbox" name="default_options[view_newest_pm_first]" id="view_newest_pm_first" value="1"', !empty($context['member']['options']['view_newest_pm_first']) ? ' checked' : '', '>
								</dd>
						</dl>
						<hr>
						<dl>
								<dt>
										<label for="pm_receive_from">', $txt['pm_receive_from'], '</label>
								</dt>
								<dd>
										<select name="pm_receive_from" id="pm_receive_from">
												<option value="0"', empty($context['receive_from']) || (empty($settings['enable_buddylist']) && $context['receive_from'] < 3) ? ' selected' : '', '>', $txt['pm_receive_from_everyone'], '</option>';

	if (!empty($settings['enable_buddylist']))
		echo '
												<option value="1"', !empty($context['receive_from']) && $context['receive_from'] == 1 ? ' selected' : '', '>', $txt['pm_receive_from_ignore'], '</option>
												<option value="2"', !empty($context['receive_from']) && $context['receive_from'] == 2 ? ' selected' : '', '>', $txt['pm_receive_from_buddies'], '</option>';

	echo '
												<option value="3"', !empty($context['receive_from']) && $context['receive_from'] > 2 ? ' selected' : '', '>', $txt['pm_receive_from_admins'], '</option>
										</select>
								</dd>
								<dt>
										<label for="pm_email_notify">', $txt['email_notify'], '</label>
								</dt>
								<dd>
										<select name="pm_email_notify" id="pm_email_notify">
												<option value="0"', empty($context['send_email']) ? ' selected' : '', '>', $txt['email_notify_never'], '</option>
												<option value="1"', !empty($context['send_email']) && ($context['send_email'] == 1 || (empty($settings['enable_buddylist']) && $context['send_email'] > 1)) ? ' selected' : '', '>', $txt['email_notify_always'], '</option>';

	if (!empty($settings['enable_buddylist']))
		echo '
												<option value="2"', !empty($context['send_email']) && $context['send_email'] > 1 ? ' selected' : '', '>', $txt['email_notify_buddies'], '</option>';

	echo '
										</select>
								</dd>
								<dt>
										<label for="popup_messages">', $txt['popup_messages'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[popup_messages]" value="0">
										<input type="checkbox" name="default_options[popup_messages]" id="popup_messages" value="1"', !empty($context['member']['options']['popup_messages']) ? ' checked' : '', '>
								</dd>
						</dl>
						<hr>
						<dl>
								<dt>
										<label for="pm_remove_inbox_label">', $txt['pm_remove_inbox_label'], '</label>
								</dt>
								<dd>
										<input type="hidden" name="default_options[pm_remove_inbox_label]" value="0">
										<input type="checkbox" name="default_options[pm_remove_inbox_label]" id="pm_remove_inbox_label" value="1"', !empty($context['member']['options']['pm_remove_inbox_label']) ? ' checked' : '', '>
								</dd>';

}

function template_profile_display_prefslist()
{
	global $context, $settings, $txt;

	// Finish the previous item.
	echo '
					<dd style="margin: 0"></dd>
				</dl>
				<ul id="theme_settings">';

	foreach ($context['member_options'] as $key => $var)
	{
		if (!is_array($var))
			continue;

		if ($var[0] == 'check')
			echo '
					<li>
						<input type="hidden" name="default_options[', $var[1], ']" value="0">
						<label><input type="checkbox" name="default_options[', $var[1], ']" id="', $var[1], '" value="1"', !empty($context['member']['options'][$var[1]]) ? ' checked' : '', '> ', $txt[$var[1]], '</label>
					</li>';
		elseif ($var[0] == 'select')
		{
			echo '
					<li>
						<label>', $txt[$var[1]], '
						<select name="default_options[', $var[1], ']" id="', $var[1], '">';

			$current = !empty($context['member']['options'][$var[1]]) ? $context['member']['options'][$var[1]] : 0;
			foreach ($var[2] as $optk => $optv)
				echo '
							<option value="', $optk, '"', $current == $optk ? ' selected' : '', '>', $optv, '</option>';

			echo '
						</select></label>
					</li>';
		}
	}

	// End this list and prepare for the end of form.
	echo '
				</ul>
				<dl>
					<dd style="margin: 0"></dd>';
}

function template_notification()
{
	global $context, $theme, $txt, $settings;

	// The main containing header.
	echo '
			<we:cat>
				<img src="', $theme['images_url'], '/icons/profile_sm.gif">
				', $txt['profile'], '
			</we:cat>
			<p class="description">', $txt['notification_info'], '</p>
			<div class="windowbg2 wrc">
				<form action="<URL>?action=profile;area=notification;save" method="post" accept-charset="UTF-8" id="notify_options" class="flow_hidden">';

	// Allow notification on announcements to be disabled?
	if (!empty($settings['allow_disableAnnounce']))
		echo '
					<input type="hidden" name="notify_announcements" value="0">
					<label><input type="checkbox" id="notify_announcements" name="notify_announcements"', !empty($context['member']['notify_announcements']) ? ' checked' : '', '> ', $txt['notify_important_email'], '</label><br>';

	// More notification options.
	echo '
					<input type="hidden" name="default_options[auto_notify]" value="0">
					<label><input type="checkbox" id="auto_notify" name="default_options[auto_notify]" value="1"', !empty($context['member']['options']['auto_notify']) ? ' checked' : '', '> ', $txt['auto_notify'], '</label><br>';

	if (empty($settings['disallow_sendBody']))
		echo '
					<input type="hidden" name="notify_send_body" value="0">
					<label><input type="checkbox" id="notify_send_body" name="notify_send_body"', !empty($context['member']['notify_send_body']) ? ' checked' : '', '> ', $txt['notify_send_body'], '</label><br>';

	echo '
					<br>
					<label>', $txt['notify_regularity'], ':
					<select name="notify_regularity" id="notify_regularity">
						<option value="0"', $context['member']['notify_regularity'] == 0 ? ' selected' : '', '>', $txt['notify_regularity_instant'], '</option>
						<option value="1"', $context['member']['notify_regularity'] == 1 ? ' selected' : '', '>', $txt['notify_regularity_first_only'], '</option>
						<option value="2"', $context['member']['notify_regularity'] == 2 ? ' selected' : '', '>', $txt['notify_regularity_daily'], '</option>
						<option value="3"', $context['member']['notify_regularity'] == 3 ? ' selected' : '', '>', $txt['notify_regularity_weekly'], '</option>
					</select></label>
					<br><br>
					<label>', $txt['notify_send_types'], ':
					<select name="notify_types" id="notify_types">
						<option value="1"', $context['member']['notify_types'] == 1 ? ' selected' : '', '>', $txt['notify_send_type_everything'], '</option>
						<option value="2"', $context['member']['notify_types'] == 2 ? ' selected' : '', '>', $txt['notify_send_type_everything_own'], '</option>
						<option value="3"', $context['member']['notify_types'] == 3 ? ' selected' : '', '>', $txt['notify_send_type_only_replies'], '</option>
						<option value="4"', $context['member']['notify_types'] == 4 ? ' selected' : '', '>', $txt['notify_send_type_nothing'], '</option>
					</select></label>
					<br class="clear">
					<div>
						<input id="notify_submit" type="submit" value="', $txt['notify_save'], '" class="submit floatright">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="hidden" name="u" value="', $context['id_member'], '">
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
					</div>
					<br class="clear">
				</form>
			</div>
			<br>';

	template_show_list('topic_notification_list');

	echo '
		<br>';

	template_show_list('board_notification_list');
}

// Template for choosing group membership.
function template_groupMembership()
{
	global $context, $theme, $settings, $txt;

	// The main containing header.
	echo '
		<form action="<URL>?action=profile;area=groupmembership;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
			<we:cat>
				<img src="', $theme['images_url'], '/icons/profile_sm.gif">
				', $txt['profile'], '
			</we:cat>
			<p class="description">', $txt['groupMembership_info'], '</p>';

	// Do we have an update message?
	if (!empty($context['update_message']))
		echo '
			<div id="profile_success">
				', $context['update_message'], '.
			</div>';

	// Requesting membership to a group?
	if (!empty($context['group_request']))
	{
		echo '
			<div class="groupmembership">
				<we:cat>
					', $txt['request_group_membership'], '
				</we:cat>
				<div class="roundframe">
					', $txt['request_group_membership_desc'], ':
					<textarea name="reason" rows="4" style="', we::is('ie8') ? 'width: 635px; max-width: 99%; min-width: 99%' : 'width: 99%', '"></textarea>
					<div class="right" style="margin: 0.5em 0.5% 0">
						<input type="hidden" name="gid" value="', $context['group_request']['id'], '">
						<input type="submit" name="req" value="', $txt['submit_request'], '" class="submit">
					</div>
				</div>
			</div>';
	}
	else
	{
		echo '
			<table class="table_grid w100 cs0 cp4">
				<thead>
					<tr class="catbg">
						<th class="first_th" scope="col" ', $context['can_edit_primary'] ? ' colspan="2"' : '', '>', $txt['current_membergroups'], '</th>
						<th class="last_th" scope="col"></th>
					</tr>
				</thead>
				<tbody>';

		$alternate = true;
		foreach ($context['groups']['member'] as $group)
		{
			echo '
					<tr class="windowbg', $alternate ? '' : '2', '" id="primdiv_', $group['id'], '">';

				if ($context['can_edit_primary'])
					echo '
						<td style="width: 4%">
							<input type="radio" name="primary" id="primary_', $group['id'], '" value="', $group['id'], '"', $group['is_primary'] ? ' checked' : '', ' onclick="highlightSelected(\'primdiv_' . $group['id'] . '\');"', $group['can_be_primary'] ? '' : ' disabled', '>
						</td>';

				echo '
						<td>
							<label for="primary_', $group['id'], '"><strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '
							<dfn>' . $group['desc'] . '</dfn>' : ''), '</label>
						</td>
						<td style="width: 15%" class="right">';

				// Can they leave their group?
				if ($group['can_leave'])
					echo '
							<a href="<URL>?action=profile;u=' . $context['id_member'] . ';save;area=groupmembership;' . $context['session_query'] . ';gid=' . $group['id'] . '">' . $txt['leave_group'] . '</a>';
				echo '
						</td>
					</tr>';
			$alternate = !$alternate;
		}

		echo '
				</tbody>
			</table>';

		if ($context['can_edit_primary'])
			echo '
			<div class="padding right">
				<input type="submit" value="', $txt['make_primary'], '" class="submit">
			</div>';

		// Any groups they can join?
		if (!empty($context['groups']['available']))
		{
			echo '
			<br>
			<table class="table_grid w100 cs0 cp4">
				<thead>
					<tr class="catbg">
						<th class="first_th" scope="col">
							', $txt['available_groups'], '
						</th>
						<th class="last_th" scope="col"></th>
					</tr>
				</thead>
				<tbody>';

			$alternate = true;
			foreach ($context['groups']['available'] as $group)
			{
				echo '
					<tr class="windowbg', $alternate ? '' : '2', '">
						<td>
							<strong>', (empty($group['color']) ? $group['name'] : '<span style="color: ' . $group['color'] . '">' . $group['name'] . '</span>'), '</strong>', (!empty($group['desc']) ? '
							<dfn>' . $group['desc'] . '</dfn>' : ''), '
						</td>
						<td style="width: 15%" class="left">';

				if ($group['type'] == 3)
					echo '
							<a href="<URL>?action=profile;u=', $context['id_member'], ';save;area=groupmembership;', $context['session_query'], ';gid=', $group['id'], '">', $txt['join_group'], '</a>';
				elseif ($group['type'] == 2 && $group['pending'])
					echo '
							', $txt['approval_pending'];
				elseif ($group['type'] == 2)
					echo '
							<a href="<URL>?action=profile;u=', $context['id_member'], ';area=groupmembership;request=', $group['id'], '">', $txt['request_group'], '</a>';

				echo '
						</td>
					</tr>';
				$alternate = !$alternate;
			}

			echo '
				</tbody>
			</table>';
		}

		// JavaScript for the selector stuff.
		add_js_inline('
	var prevClass = "", prevDiv = "";
	function highlightSelected(box)
	{
		if (prevClass != "")
			prevDiv.className = prevClass;

		prevDiv = document.getElementById(box);
		prevClass = prevDiv.className;

		prevDiv.className = "highlight";
	}');

		if (isset($context['groups']['member'][$context['primary_group']]))
			add_js_inline('
	highlightSelected("primdiv_' . $context['primary_group'] . '");');
	}

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="u" value="', $context['id_member'], '">
		</form>';
}

function template_ignoreboards()
{
	global $context, $txt, $theme;

	add_js('
	function selectBoards(ids)
	{
		var toggle = true;

		for (i = 0; i < ids.length; i++)
			toggle = toggle & document.forms.creator["ignore_brd" + ids[i]].checked;

		for (i = 0; i < ids.length; i++)
			document.forms.creator["ignore_brd" + ids[i]].checked = !toggle;
	}');

	// The main containing header.
	echo '
	<form action="<URL>?action=profile;area=ignoreboards;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
		<we:cat>
			<img src="', $theme['images_url'], '/icons/profile_sm.gif">
			', $txt['profile'], '
		</we:cat>
		<p class="description">', $txt['ignoreboards_info'], '</p>
		<div class="windowbg2 wrc">
			<div class="flow_hidden">
				<ul class="ignoreboards floatleft">';

	$i = 0;
	$limit = ceil(($context['num_boards'] + count($context['categories'])) / 2);
	foreach ($context['categories'] as $category)
	{
		if ($i++ == $limit)
			echo '
				</ul>
				<ul class="ignoreboards floatright">';

		echo '
					<li class="category">
						<a href="#" onclick="selectBoards([', implode(', ', $category['child_ids']), ']); return false;">', $category['name'], '</a>
						<ul>';

		foreach ($category['boards'] as $board)
		{
			if ($i++ == $limit)
				echo '
						</ul>
					</li>
				</ul>
				<ul class="ignoreboards floatright">
					<li class="category">
						<ul>';

			echo '
							<li class="board" style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'], 'em">
								<label><input type="checkbox" id="ignore_brd', $board['id'], '" name="ignore_brd[', $board['id'], ']" value="', $board['id'], '"', $board['selected'] ? ' checked' : '', '> ', $board['name'], '</label>
							</li>';
		}

		echo '
						</ul>
					</li>';
	}

	echo '
				</ul>
			</div>
			<br class="clear">';

	// Show the standard "Save Settings" profile button.
	template_profile_save();

	echo '
		</div>
	</form>
	<br>';
}

// Simple load some theme variables common to several warning templates.
function template_load_warning_variables()
{
	global $settings, $context;

	$context['warningBarWidth'] = 200;
	// Setup the colors - this is a little messy for theming.
	$context['colors'] = array(
		0 => 'lime',
		$settings['warning_watch'] => 'green',
		$settings['warning_moderate'] => 'orange',
		$settings['warning_mute'] => 'red',
	);

	// Work out the starting color.
	$context['current_color'] = $context['colors'][0];
	foreach ($context['colors'] as $limit => $color)
		if ($context['member']['warning'] >= $limit)
			$context['current_color'] = $color;
}

// Show all warnings of a user?
function template_viewWarning()
{
	global $context, $txt, $theme;

	template_load_warning_variables();

	echo '
		<we:title>
			<img src="', $theme['images_url'], '/icons/profile_sm.gif">
			', sprintf($txt['profile_viewwarning_for_user'], $context['member']['name']), '
		</we:title>
		<div class="windowbg wrc">
			<dl class="settings">
				<dt>
					<strong>', $txt['profile_warning_name'], ':</strong>
				</dt>
				<dd>
					', $context['member']['name'], '
				</dd>
				<dt>
					<strong>', $txt['profile_warning_level'], ':</strong>
				</dt>
				<dd>
					<div>
						<div>
							<div style="font-size: 8pt; height: 12pt; width: ', $context['warningBarWidth'], 'px; border: 1px solid black; background-color: white; padding: 1px; position: relative">
								<div id="warning_text" style="padding-top: 1pt; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold">', $context['member']['warning'], '%</div>
								<div id="warning_progress" style="width: ', $context['member']['warning'], '%; height: 12pt; z-index: 1; background-color: ', $context['current_color'], '">&nbsp;</div>
							</div>
						</div>
					</div>
				</dd>';

		// There's some impact of this?
		if (!empty($context['level_effects'][$context['current_level']]))
			echo '
				<dt>
					<strong>', $txt['profile_viewwarning_impact'], ':</strong>
				</dt>
				<dd>
					', $context['level_effects'][$context['current_level']], '
				</dd>';

		echo '
			</dl>
		</div>';

	template_show_list('view_warnings');
}

// Show a lovely interface for issuing warnings.
function template_issueWarning()
{
	global $context, $theme, $txt;

	template_load_warning_variables();

	// !!! Should we really calculate these? jQuery should have done most of it already...
	// !!! This is also reused in the admin settings now. If this gets rewritten, fix that too please.
	add_js('
	var isMoving;
	function setWarningBarPos(e, changeAmount)
	{
		var
			barWidth = ', $context['warningBarWidth'], ', mouse = e.pageX,
			percent, size, color = "white", effectText = "";

		// Are we passing the amount to change it by?
		if (changeAmount)
			percent = $("#warning_level").val() == "SAME" ?
				', $context['member']['warning'], ' + changeAmount :
				parseInt($("#warning_level").val(), 10) + changeAmount;
		// If not then it\'s a mouse thing.
		else
		{
			if (e.type == "mousedown" && e.which == 1)
				isMoving = true;
			if (e.type == "mouseup")
				isMoving = false;
			if (!isMoving)
				return false;

			// Get the position of the container.
			var position = $("#warning_contain").offset().left;
			percent = Math.round(Math.round(((mouse - position) / barWidth) * 100) / 5) * 5;
		}

		percent = Math.min(Math.max(percent, ', $context['min_allowed'], '), ', $context['max_allowed'], ');
		size = barWidth * (percent/100);');

	// Get the right color.
	foreach ($context['colors'] as $limit => $color)
		add_js('
		if (percent >= ', $limit, ')
			color = "', $color, '";');

	add_js('
		$("#warning_progress").css({ width: size + "px", backgroundColor: color });
		$("#warning_text").css("color", percent < 50 ? "black" : (percent < 60 ? (color == "green" ? "#ccc" : "black") : "white")).html(percent + "%");
		$("#warning_level").val(percent);');

	// Also set the right effect.
	foreach ($context['level_effects'] as $limit => $text)
		add_js('
		if (percent >= ', $limit, ')
			effectText = "', $text, '";');

	add_js('
		$("#cur_level_div").html(effectText);
	}

	// Disable notification boxes as required.
	function modifyWarnNotify()
	{
		var enable = $("#warn_notify").is(":checked");
		$("#warn_sub, #warn_body, #warn_temp").prop("disabled", !enable);
		$("#warn_temp").sb();
		$("#new_template_link").toggle(enable);
	}

	function changeWarnLevel(amount)
	{
		setWarningBarPos(false, amount);
	}

	// Warn template.
	function populateNotifyTemplate()
	{
		index = $("#warn_temp").val();
		if (index == -1)
			return false;

		// Otherwise see what we can do...');

	foreach ($context['notification_templates'] as $k => $type)
		add_js('
		if (index == ', $k, ')
			$("#warn_body").val("', strtr($type['body'], array('"' => "'", "\n" => '\\n', "\r" => '')), '");');

	add_js('
	}');

	echo '
	<form action="<URL>?action=profile;u=', $context['id_member'], ';area=issuewarning" method="post" class="flow_hidden" accept-charset="UTF-8">
		<we:cat>
			<img src="', $theme['images_url'], '/icons/profile_sm.gif">
			', we::$user['is_owner'] ? $txt['profile_warning_level'] : $txt['profile_issue_warning'], '
		</we:cat>';

	if (!we::$user['is_owner'])
		echo '
		<p class="description">', $txt['profile_warning_desc'], '</p>';

	echo '
		<div class="windowbg wrc">
			<dl class="settings">';

	if (!we::$user['is_owner'])
		echo '
				<dt>
					<strong>', $txt['profile_warning_name'], ':</strong>
				</dt>
				<dd>
					<strong>', $context['member']['name'], '</strong>
				</dd>';

	echo '
				<dt>
					<strong>', $txt['profile_warning_level'], ':</strong>';

	// Is there only so much they can apply?
	if ($context['warning_limit'])
		echo '
					<dfn>', sprintf($txt['profile_warning_limit_attribute'], $context['warning_limit']), '</dfn>';

	echo '
				</dt>
				<dd>
					<div id="warndiv1" class="hide">
						<div>
							<span class="floatleft" style="padding: 0 .5em"><a href="#" onclick="changeWarnLevel(-5); return false;" onmousedown="return false;">[-]</a></span>
							<div class="floatleft" id="warning_contain" style="font-size: 8pt; height: 12pt; width: ', $context['warningBarWidth'], 'px; border: 1px solid black; background-color: white; padding: 1px; position: relative">
								<div id="warning_text" style="padding-top: 1pt; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold" onmousedown="e.preventDefault();">', $context['member']['warning'], '%</div>
								<div id="warning_progress" style="width: ', $context['member']['warning'], '%; height: 12pt; z-index: 1; background-color: ', $context['current_color'], '">&nbsp;</div>
							</div>
							<span class="floatleft" style="padding: 0 .5em"><a href="#" onclick="changeWarnLevel(5); return false;" onmousedown="return false;">[+]</a></span>
							<div class="clear_left smalltext">', $txt['profile_warning_impact'], ': <span id="cur_level_div">', $context['level_effects'][$context['current_level']], '</span></div>
						</div>
						<input type="hidden" name="warning_level" id="warning_level" value="SAME">
					</div>
					<div id="warndiv2">
						<input name="warning_level_nojs" size="6" maxlength="4" value="', $context['member']['warning'], '">&nbsp;', $txt['profile_warning_max'], '
						<div class="smalltext">', $txt['profile_warning_impact'], ':<br>';

	add_js('
	$("#warning_contain").on("mousedown mousemove mouseup", setWarningBarPos).mouseleave(function () { isMoving = false; });
	$("#warndiv1").show();
	$("#warndiv2").hide();');

	// For non-JavaScript give a better list.
	foreach ($context['level_effects'] as $limit => $effect)
		echo '
							', sprintf($txt['profile_warning_effect_text'], $limit, $effect), '<br>';

	echo '
						</div>
					</div>
				</dd>';

	if (!we::$user['is_owner'])
	{
		echo '
				<dt>
					<strong>', $txt['profile_warning_reason'], ':</strong>
					<dfn>', $txt['profile_warning_reason_desc'], '</dfn>
				</dt>
				<dd>
					<input name="warn_reason" id="warn_reason" value="', $context['warning_data']['reason'], '" size="50" style="width: 80%">
				</dd>
			</dl>
			<hr>
			<dl class="settings">
				<dt>
					<strong>', $txt['profile_warning_notify'], ':</strong>
				</dt>
				<dd>
					<input type="checkbox" name="warn_notify" id="warn_notify" onclick="modifyWarnNotify();"', $context['warning_data']['notify'] ? ' checked' : '', '>
				</dd>
				<dt>
					<strong>', $txt['profile_warning_notify_subject'], ':</strong>
				</dt>
				<dd>
					<input name="warn_sub" id="warn_sub" value="', empty($context['warning_data']['notify_subject']) ? $txt['profile_warning_notify_template_subject'] : $context['warning_data']['notify_subject'], '" size="50" style="width: 80%">
				</dd>
				<dt>
					<strong>', $txt['profile_warning_notify_body'], ':</strong>
				</dt>
				<dd>
					<select name="warn_temp" id="warn_temp" disabled onchange="populateNotifyTemplate();" style="font-size: x-small">
						<option value="-1" data-hide>', $txt['profile_warning_notify_template'], '</option>';

		foreach ($context['notification_templates'] as $id_template => $template)
			echo '
						<option value="', $id_template, '">', $template['title'], '</option>';

		echo '
					</select>
					<span class="smalltext hide" id="new_template_link">[<a href="<URL>?action=moderate;area=warnings;sa=templateedit;tid=0" target="_blank" class="new_win">', $txt['profile_warning_new_template'], '</a>]</span><br>
					<textarea name="warn_body" id="warn_body" cols="40" rows="8">', $context['warning_data']['notify_body'], '</textarea>
				</dd>';
	}
	echo '
			</dl>
			<div class="right">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" name="save" value="', we::$user['is_owner'] ? $txt['change_profile'] : $txt['profile_warning_issue'], '" class="submit">
			</div>
		</div>
	</form>';

	// Previous warnings?
	echo '
	<br>
	<we:cat>
		', $txt['profile_warning_previous'], '
	</we:cat>
	<table class="table_grid w100 cs0 cp4">
		<thead>
			<tr class="titlebg left">
				<th class="first_th" scope="col" style="width: 20%">', $txt['profile_warning_previous_issued'], '</th>
				<th scope="col" style="width: 30%">', $txt['profile_warning_previous_time'], '</th>
				<th scope="col">', $txt['profile_warning_previous_reason'], '</th>
				<th class="last_th" scope="col" style="width: 6%">', $txt['profile_warning_previous_level'], '</th>
			</tr>
		</thead>
		<tbody>';

	// Print the warnings.
	$alternate = 0;
	foreach ($context['previous_warnings'] as $warning)
	{
		$alternate = !$alternate;
		echo '
			<tr class="windowbg', $alternate ? '' : '2', '">
				<td class="smalltext">', $warning['issuer']['link'], '</td>
				<td class="smalltext">', $warning['time'], '</td>
				<td class="smalltext">
					<div class="floatleft">
						', $warning['reason'], '
					</div>';

		if (!empty($warning['id_notice']))
			echo '
					<div class="floatright">
						<a href="<URL>?action=moderate;area=notice;nid=', $warning['id_notice'], '" onclick="window.open(this.href, \'\', \'scrollbars=yes,resizable=yes,width=400,height=250\');return false;" target="_blank" class="new_win" title="', $txt['profile_warning_previous_notice'], '"><img src="', $theme['images_url'], '/filter.gif"></a>
					</div>';
		echo '
				</td>
				<td class="smalltext">', $warning['counter'], '</td>
			</tr>';
	}

	if (empty($context['previous_warnings']))
		echo '
			<tr class="windowbg2">
				<td class="center" colspan="4">
					', $txt['profile_warning_previous_none'], '
				</td>
			</tr>';

	echo '
		</tbody>
	</table>
	<div class="pagesection">
		<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
	</div>';

	if (!we::$user['is_owner'])
		add_js('
	modifyWarnNotify();');
}

// Template to show for deleting a users account - now with added delete post capability!
function template_deleteAccount()
{
	global $context, $theme, $txt;

	// The main containing header.
	echo '
		<form action="<URL>?action=profile;area=deleteaccount;save" method="post" accept-charset="UTF-8" name="creator" id="creator">
			<we:cat>
				<img src="', $theme['images_url'], '/icons/profile_sm.gif">', $txt['deleteAccount'], '
			</we:cat>';
	// If deleting another account give them a lovely info box.
	if (!we::$user['is_owner'])
		echo '
			<p class="description">', $txt['deleteAccount_desc'], '</p>';
	echo '
			<div class="windowbg2 wrc">';

	// If they are deleting their account AND the admin needs to approve it - give them another piece of info ;)
	if ($context['needs_approval'])
		echo '
				<div id ="profile_error" class="alert">', $txt['deleteAccount_approval'], '</div>';

	// If the user is deleting their own account warn them first - and require a password!
	if (we::$user['is_owner'])
	{
		echo '
				<div class="alert">', $txt['own_profile_confirm'], '</div>
				<div style="margin: 15px 0 0">
					<strong', (isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : ''), '>', $txt['current_password'], ': </strong>
					<input type="password" name="oldpasswrd" size="20">&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="submit" value="', $txt['yes'], '" class="delete">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="u" value="', $context['id_member'], '">
					<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
				</div>';
	}
	// Otherwise an admin doesn't need to enter a password - but they still get a warning - plus the option to delete lovely posts!
	else
	{
		echo '
				<div class="alert">', $txt['deleteAccount_warning'], '</div>';

		// Only actually give these options if they are kind of important.
		if ($context['can_delete_posts'])
			echo '
				<div>
					', $txt['deleteAccount_posts'], ':
					<select name="remove_type">
						<option value="none">', $txt['deleteAccount_none'], '</option>
						<option value="posts">', $txt['deleteAccount_all_posts'], '</option>
						<option value="topics">', $txt['deleteAccount_topics'], '</option>
					</select>
				</div>';

		echo '
				<div>
					<label><input type="checkbox" name="deleteAccount" id="deleteAccount" value="1" onclick="return !this.checked || ask(', JavaScriptEscape($txt['deleteAccount_confirm']), ', e);"> ', $txt['deleteAccount_member'], '.</label>
				</div>
				<div>
					<input type="submit" value="', $txt['delete'], '" class="delete">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="u" value="', $context['id_member'], '">
					<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
				</div>';
	}
	echo '
			</div>
			<br>
		</form>';
}

// Template for the password box/save button stuck at the bottom of every profile page.
function template_profile_save()
{
	global $context, $txt;

	echo '
					<hr>';

	// Only show the password box if it's actually needed.
	if ($context['require_password'])
		echo '
					<dl>
						<dt>
							<strong', isset($context['modify_error']['bad_password']) || isset($context['modify_error']['no_password']) ? ' class="error"' : '', '>', $txt['current_password'], ': </strong>
							<dfn>', $txt['required_security_reasons'], '</dfn>
						</dt>
						<dd>
							<input type="password" name="oldpasswrd" size="20" style="margin-right: 4ex">
						</dd>
					</dl>';

	echo '
					<div class="right">
						<input type="submit" value="', $txt['change_profile'], '" class="save">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="hidden" name="u" value="', $context['id_member'], '">
						<input type="hidden" name="sa" value="', $context['menu_item_selected'], '">
					</div>';
}

// Small template for showing an error message upon a save problem in the profile.
function template_error_message()
{
	global $context, $txt;

	echo '
		<div class="windowbg" id="profile_error">
			<span>', !empty($context['custom_error_title']) ? $context['custom_error_title'] : $txt['profile_errors_occurred'], ':</span>
			<ul class="reset">';

		// Cycle through each error and display an error message.
		foreach ($context['post_error'] as $error)
			echo '
				<li>', isset($txt['profile_error_' . $error]) ? $txt['profile_error_' . $error] : $error, '.</li>';

		echo '
			</ul>
		</div>';
}

// Display a load of drop down selectors for allowing the user to change group.
function template_profile_group_manage()
{
	global $context, $txt;

	echo '
					<dt>
						<a href="<URL>?action=help;in=primary_membergroup" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
						<strong>', $txt['primary_membergroup'], ': </strong>
						<dfn>', $txt['primary_membergroup_subtext'], '</dfn>
					</dt>
					<dd>
						<select name="id_group" ', (we::$user['is_owner'] && $context['member']['group_id'] == 1 ? 'onchange="if (this.value != 1) ask(' . JavaScriptEscape($txt['deadmin_confirm']) . ', e, function (go) { if (!go) $(this).val(1).sb(); });"' : ''), '>';

	// Fill the select box with all primary member groups that can be assigned to a member.
	foreach ($context['member_groups'] as $member_group)
		if (!empty($member_group['can_be_primary']))
			echo '
							<option value="', $member_group['id'], '"', $member_group['is_primary'] ? ' selected' : '', '>
								', $member_group['name'], !empty($member_group['badge']) ? '|' . westr::htmlspecialchars($member_group['badge']) : '', '
							</option>';

	echo '
						</select>
					</dd>
					<dt>
						<strong>', $txt['additional_membergroups'], ':</strong>
					</dt>
					<dd>
						<span id="additional_groupsList">
							<input type="hidden" name="additional_groups[]" value="0">';

	// For each membergroup show a checkbox so members can be assigned to more than one group.
	foreach ($context['member_groups'] as $member_group)
	{
		if ($member_group['can_be_additional'])
		{
			// Only show a badge if: there's one available, it's set to be shown, and it's not set to be hidden for additional groups. (value = 2)
			$show_badge = !empty($member_group['badge']) && !empty($member_group['show_when']) && $member_group['show_when'] != 2;

			echo '
							<label style="margin: .5em 0; display: block"><input type="checkbox" name="additional_groups[]" value="', $member_group['id'], '" id="additional_groups-', $member_group['id'], '"', $member_group['is_additional'] ? ' checked' : '', ' style="vertical-align: top"> ';

			if ($show_badge)
				echo '<div style="display: inline-block">', $member_group['name'], '<dfn>', $member_group['badge'], '</dfn></div></label>';
			else
				echo $member_group['name'], '</label>';
		}
	}

	echo '
						</span>
						<a href="#" onclick="$(\'#additional_groupsList\').show(); $(\'#additional_groupsLink\').hide(); return false;" id="additional_groupsLink" class="hide">', $txt['additional_membergroups_show'], '</a>
					</dd>';

	// No need to hide the additional group list if it's short enough...
	if (count($context['member_groups']) > 4)
		add_js('
	$("#additional_groupsList, #additional_groupsLink").toggle();');
}

// Callback function for entering a birthdate!
function template_profile_birthdate()
{
	global $txt, $context;

	// Just show the pretty box!
	echo '
					<dt>
						<strong>', $txt['dob'], ':</strong>
						<dfn>', $txt['dob_year'], ' - ', $txt['dob_month'], ' - ', $txt['dob_day'], '</dfn>
					</dt>
					<dd>
						<input name="bday3" size="4" maxlength="4" value="', $context['member']['birth_date']['year'], '"> -
						<input name="bday1" size="2" maxlength="2" value="', $context['member']['birth_date']['month'], '"> -
						<input name="bday2" size="2" maxlength="2" value="', $context['member']['birth_date']['day'], '">
					</dd>';
}

// Show the signature editing box?
function template_profile_signature_modify()
{
	global $txt, $context;

	echo '
					<dt>
						<strong>', $txt['signature'], ':</strong>
						<dfn>', $txt['sig_info'], '</dfn>';
	if (!empty($context['signature_minposts']))
		echo '
						<dfn>', $context['signature_minposts'], '</dfn>';

	echo '
						<br>
					</dt>
					<dd>
						<textarea class="editor" onkeyup="calcCharLeft();" name="signature" rows="5" cols="50">', $context['member']['signature'], '</textarea><br>';

	// If there is a limit at all!
	if (!empty($context['signature_limits']['max_length']))
		echo '
						<div class="smalltext">', sprintf($txt['max_sig_characters'], $context['signature_limits']['max_length']), ' <span id="signatureLeft">', $context['signature_limits']['max_length'], '</span></div>';

	if ($context['signature_warning'])
		echo '
						<div class="smalltext">', $context['signature_warning'], '</div>';

	echo '
					</dd>';

	// Some JavaScript used to count how many characters have been used so far in the signature.
	add_js('
	function tick()
	{
		if ("creator" in document.forms)
		{
			calcCharLeft();
			setTimeout(tick, 1000);
		}
		else
			setTimeout(tick, 800);
	}
	function calcCharLeft()
	{
		var maxLength = ', $context['signature_limits']['max_length'], ';
		var oldSignature = "", currentSignature = document.forms.creator.signature.value;

		if (oldSignature != currentSignature)
		{
			oldSignature = currentSignature;

			if (currentSignature.replace(/\r/g, "").length > maxLength)
				document.forms.creator.signature.value = currentSignature.replace(/\r/g, "").slice(0, maxLength);
			currentSignature = document.forms.creator.signature.value.replace(/\r/g, "");
		}

		$("#signatureLeft").html(maxLength - currentSignature.length);
	}
	tick();');
}

function template_profile_avatar_select()
{
	global $context, $txt, $settings;

	// Start with the upper menu
	echo '
					<dt>
						<strong id="personal_picture">', $txt['personal_picture'], '</strong>
						<label' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '><input type="radio" onclick="return swap_avatar(this.id);" name="avatar_choice" id="avatar_choice_none" value="none"' . ($context['member']['avatar']['choice'] == 'none' ? ' checked' : '') . '> ' . $txt['no_avatar'] . '</label><br>
						<label' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '>', !empty($context['member']['avatar']['allow_server_stored']) ? '<input type="radio" onclick="return swap_avatar(this.id);" name="avatar_choice" id="avatar_choice_server_stored" value="server_stored"' . ($context['member']['avatar']['choice'] == 'server_stored' ? ' checked' : '') . '> ' . $txt['choose_avatar_gallery'] . '</label><br>' : '', '
						', !empty($context['member']['avatar']['allow_external']) ? '<label' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '><input type="radio" onclick="return swap_avatar(this.id);" name="avatar_choice" id="avatar_choice_external" value="external"' . ($context['member']['avatar']['choice'] == 'external' ? ' checked' : '') . '> ' . $txt['my_own_pic'] . '</label><br>' : '', '
						', !empty($context['member']['avatar']['allow_upload']) ? '<label' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '><input type="radio" onclick="return swap_avatar(this.id);" name="avatar_choice" id="avatar_choice_upload" value="upload"' . ($context['member']['avatar']['choice'] == 'upload' ? ' checked' : '') . '> ' . $txt['avatar_will_upload'] . '</label><br>' : '', '
						', !empty($context['member']['avatar']['allow_gravatar']) ? '<label' . (isset($context['modify_error']['bad_avatar']) ? ' class="error"' : '') . '><input type="radio" onclick="return swap_avatar(this.id);" name="avatar_choice" id="avatar_choice_gravatar" value="gravatar"' . ($context['member']['avatar']['choice'] == 'gravatar' ? ' checked' : '') . '> ' . $txt['use_gravatar'] . '</label>' : '', '
					</dt>
					<dd>';

	// If users are allowed to choose avatars stored on the server show selection boxes to choose them from.
	if (!empty($context['member']['avatar']['allow_server_stored']))
	{
		echo '
						<div id="avatar_server_stored">
							<div>
								<select name="cat" id="cat" size="6" onchange="changeSel(\'\');">';

		// This lists all the file categories.
		foreach ($context['avatars'] as $avatar)
			echo '
									<option value="', $avatar['filename'] . ($avatar['is_dir'] ? '/' : ''), '"', ($avatar['checked'] ? ' selected' : ''), '>', $avatar['name'], '</option>';

		echo '
								</select>
							</div>
							<div>
								<select name="file" id="file" size="6" class="hide" onchange="showAvatar();" disabled><option></option></select>
							</div>
							<div><img id="avatar" src="', !empty($context['member']['avatar']['allow_external']) && $context['member']['avatar']['choice'] == 'external' ? $context['member']['avatar']['external'] : $settings['avatar_url'] . '/blank.gif', '"></div>
						</div>';

		add_js('
	var
		files = ["' . implode('", "', $context['avatar_list']) . '"],
		avatar = $("#avatar")[0],
		cat = $("#cat")[0],
		file = $("#file")[0],
		selavatar = "' . $context['avatar_selected'] . '",
		avatardir = "' . $settings['avatar_url'] . '/";

	if (avatar.src.indexOf("blank.gif") > -1)
		changeSel(selavatar);
	else
		previewExternalAvatar(avatar.src);

	function changeSel(selected)
	{
		if (cat.selectedIndex == -1)
			return;

		var val = $(cat).val(), i, count = 0;
		if (val.indexOf("/") > 0)
		{
			$(file).css("display", "inline").prop("disabled", false);

			for (i = file.length; i >= 0; i--)
				file.options[i] = null;

			for (i = 0; i < files.length; i++)
				if (files[i].indexOf(val) == 0)
				{
					var filename = files[i].slice(files[i].indexOf("/") + 1);
					var showFilename = filename.slice(0, filename.lastIndexOf("."));
					showFilename = showFilename.replace(/[_]/g, " ");

					file.options[count] = new Option(showFilename, files[i]);

					if (filename == selected)
					{
						if (file.options.defaultSelected)
							file.options[count].defaultSelected = true;
						else
							file.options[count].selected = true;
					}
					count++;
				}

			if (file.selectedIndex == -1 && file.options[0])
				file.options[0].selected = true;

			showAvatar();
		}
		else
		{
			$(file).hide().prop("disabled", true);
			$("#avatar").attr("src", avatardir + val).css({ width: "", height: "" });
		}
	}

	function showAvatar()
	{
		if (file.selectedIndex == -1)
			return;

		$("#avatar").attr({
			src: avatardir + $(file).val(),
			alt: file.options[file.selectedIndex].text
		}).css({
			width: "",
			height: ""
		});
	}

	function previewExternalAvatar(src)
	{
		$("#external_avatar").load(function () {
			var	maxHeight = ', !empty($settings['avatar_max_height_external']) ? $settings['avatar_max_height_external'] : 0, ',
				maxWidth = ', !empty($settings['avatar_max_width_external']) ? $settings['avatar_max_width_external'] : 0, ';
			if (maxWidth != 0 && $(this).width() > maxWidth)
				$(this).height((maxWidth * $(this).height()) / $(this).width()).width(maxWidth);
			else if (maxHeight != 0 && $(this).height() > maxHeight)
				$(this).width((maxHeight * $(this).width()) / $(this).height()).height(maxHeight);
		}).attr("src", src);
	}');
	}

	// If the user can link to an off server avatar, show them a box to input the address. But don't put in the Gravatar email if it is currently that...
	if (!empty($context['member']['avatar']['allow_external']))
	{
		echo '
						<div id="avatar_external">
							<div class="smalltext">', $txt['avatar_by_url'], '</div>
							<br>
							<input name="userpicpersonal" size="45" value="', $context['member']['avatar']['choice'] != 'gravatar' ? $context['member']['avatar']['external'] : 'http://', '" onchange="previewExternalAvatar(this.value);">';

		if (!empty($settings['avatar_max_width_external']) && !empty($settings['avatar_max_height_external']))
			echo '
							<dfn>', sprintf($txt['avatar_resize_' . ($settings['avatar_action_too_large'] != 'option_refuse' ? 'warning' : 'forbid')], $settings['avatar_max_width_external'], $settings['avatar_max_height_external']), '</dfn>';
		elseif (!empty($settings['avatar_max_width_external']))
			echo '
							<dfn>', sprintf($txt['avatar_resize_' . ($settings['avatar_action_too_large'] != 'option_refuse' ? 'warning' : 'forbid') . '_width'], $settings['avatar_max_width_external']), '</dfn>';
		elseif (!empty($settings['avatar_max_height_external']))
			echo '
							<dfn>', sprintf($txt['avatar_resize_' . ($settings['avatar_action_too_large'] != 'option_refuse' ? 'warning' : 'forbid') . '_height'], $settings['avatar_max_height_external']), '</dfn>';

		echo '
							<div><img id="external_avatar" src="', !empty($context['member']['avatar']['allow_external']) && $context['member']['avatar']['choice'] == 'external' ? $context['member']['avatar']['external'] : $settings['avatar_url'] . '/blank.gif', '"></div>
						</div>';
	}

	// If the user is able to upload avatars to the server show them an upload box.
	if (!empty($context['member']['avatar']['allow_upload']))
	{
		echo '
						<div id="avatar_upload">
							<input type="file" name="attachment">
							', ($context['member']['avatar']['id_attach'] > 0 ? '<br><br><img src="' . $context['member']['avatar']['href'] . (strpos($context['member']['avatar']['href'], '?') === false ? '?' : '&amp;') . 'time=' . time() . '"><input type="hidden" name="id_attach" value="' . $context['member']['avatar']['id_attach'] . '">' : '');

		if (!empty($settings['avatar_max_width_upload']) && !empty($settings['avatar_max_height_upload']))
			echo '
							<dfn>', sprintf($txt['avatar_resize_' . (!empty($settings['avatar_resize_upload']) ? 'warning' : 'forbid')], $settings['avatar_max_width_upload'], $settings['avatar_max_height_upload']), '</dfn>';
		elseif (!empty($settings['avatar_max_width_upload']))
			echo '
							<dfn>', sprintf($txt['avatar_resize_' . (!empty($settings['avatar_resize_upload']) ? 'warning' : 'forbid') . '_width'], $settings['avatar_max_width_upload']), '</dfn>';
		elseif (!empty($settings['avatar_max_height_upload']))
			echo '
							<dfn>', sprintf($txt['avatar_resize_' . (!empty($settings['avatar_resize_upload']) ? 'warning' : 'forbid') . '_height'], $settings['avatar_max_height_upload']), '</dfn>';

		echo '
						</div>';
	}

	// Using a Gravatar? Well, maybe there is an option for you, maybe there isn't...
	if (!empty($context['member']['avatar']['allow_gravatar']))
	{
		if (empty($settings['gravatarAllowExtraEmail']))
			echo '
						<div id="avatar_gravatar">
							<div class="smalltext">', $txt['gravatar_noAlternateEmail'], '</div>
						</div>';
		else
		{
			// Depending on other stuff, the stored value here might have some odd things in it from other areas.
			if ($context['member']['avatar']['external'] == $context['member']['email'] || strpos($context['member']['avatar']['name'], 'gravatar://') === false)
				$textbox_value = '';
			else
				$textbox_value = $context['member']['avatar']['external'];

			echo '
						<div id="avatar_gravatar">
							<div class="smalltext">', $txt['gravatar_alternateEmail'], '</div>
							<br>
							<input type="email" name="gravatarEmail" size="45" value="', $textbox_value, '">
						</div>';
		}
	}

	echo '
					</dd>';

	add_js('
	swap_avatar("avatar_choice_', $context['member']['avatar']['choice'], '");

	function swap_avatar(id)
	{', !empty($context['member']['avatar']['allow_server_stored']) ? '
		$("#avatar_server_stored").toggle(id == "avatar_choice_server_stored");' : '', !empty($context['member']['avatar']['allow_external']) ? '
		$("#avatar_external").toggle(id == "avatar_choice_external");' : '', !empty($context['member']['avatar']['allow_upload']) ? '
		$("#avatar_upload").toggle(id == "avatar_choice_upload");' : '', !empty($context['member']['avatar']['allow_gravatar']) ? '
		$("#avatar_gravatar").toggle(id == "avatar_choice_gravatar");' : '', '
		return true;
	}');
}

// Select the time format!
function template_profile_timeformat_modify()
{
	global $context, $txt;

	echo '
					<dt>
						<a href="<URL>?action=help;in=time_format" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
						<strong>', $txt['choose_time_format'], '</strong>
						<dfn>', $txt['date_format'], '</dfn>
					</dt>
					<dd>
						<select name="easyformat" onchange="document.forms.creator.time_format.value = $(this).val();" style="margin-bottom: 4px">';

	// Help the user by showing a list of common time formats.
	foreach ($context['easy_timeformats'] as $time_format)
		echo '
							<option value="', $time_format['format'], '"', $time_format['format'] == $context['member']['time_format'] ? ' selected' : '', '>', $time_format['title'], '</option>';
	echo '
						</select><br>
						<input name="time_format" value="', $context['member']['time_format'], '" size="30">
					</dd>';
}

// Time offset?
function template_profile_timeoffset_modify()
{
	global $txt, $context;

	// Get the difference between the two, set it up so that the sign will tell us who is ahead of whom.
	// Currently only supports timezones in hourly increments. Our apologies to India.
	add_js('
	function autoDetectTimeOffset(serverTime)
	{
		return serverTime ? Math.round(($.now() - serverTime) / 3600000) % 24 : 0;
	}');

	echo '
					<dt>
						<strong', (isset($context['modify_error']['bad_offset']) ? ' class="error"' : ''), '>', $txt['time_offset'], ':</strong>
						<dfn>', $txt['personal_time_offset'], '</dfn>
					</dt>
					<dd>
						<input name="time_offset" id="time_offset" size="5" maxlength="5" value="', $context['member']['time_offset'], '"> <a href="#" onclick="$(this).val(autoDetectTimeOffset(+new Date(', $context['current_forum_time_js'], '000))); return false;">', $txt['timeoffset_autodetect'], '</a><br>', $txt['current_time'], ': <em>', $context['current_forum_time'], '</em>
					</dd>';
}

// Smiley set picker.
function template_profile_smiley_pick()
{
	global $txt, $context, $settings, $theme;

	echo '
					<dt>
						<strong>', $txt['smileys_current'], ':</strong>
					</dt>
					<dd>
						<select name="smiley_set" onchange="$(\'#smileypr\').attr(\'src\', this.selectedIndex == 0 ? \'', $theme['images_url'], '/blank.gif\' : \'', $settings['smileys_url'], '/\' + (this.selectedIndex != 1 ? $(this).val() : \'', !empty($theme['smiley_sets_default']) ? $theme['smiley_sets_default'] : $settings['smiley_sets_default'], '\') + \'/smiley.gif\');">';

	foreach ($context['smiley_sets'] as $set)
		echo '
							<option value="', $set['id'], '"', $set['selected'] ? ' selected' : '', '>', $set['name'], '</option>';

	echo '
						</select> <img id="smileypr" src="', $context['member']['smiley_set']['id'] != 'none' ? $settings['smileys_url'] . '/' . ($context['member']['smiley_set']['id'] != '' ? $context['member']['smiley_set']['id'] : (!empty($theme['smiley_sets_default']) ? $theme['smiley_sets_default'] : $settings['smiley_sets_default'])) . '/smiley.gif' : $theme['images_url'] . '/blank.gif', '" alt=":)" class="top" style="padding-left: 20px">
					</dd>';
}
