<?php
// Version: 2.0 RC3; Display

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Show the anchor for the top and for the first message.  If the first message is new, say so.
	echo '
<a id="top"></a>
<a id="msg', $context['first_message'], '"></a>', $context['first_new_message'] ? '<a id="new"></a>' : '';

	// Show the linktree as well as the "Who's Viewing" information.
	echo '
<table width="100%" cellpadding="3" cellspacing="0">
	<tr>';

	if (!empty($settings['display_who_viewing']))
	{
		echo '
		<td align="center" class="smalltext">';

		// Show just numbers...?
		if ($settings['display_who_viewing'] == 1)
			echo count($context['view_members']), ' ', count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members'];
		// Or show the actual people viewing the topic?
		else
			echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . (empty($context['view_num_hidden']) || $context['can_moderate_forum'] ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

		// Now show how many guests are here too.
		echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_topic'], '</td>';
	}

	// Show the previous/next links.
	echo '
		<td valign="bottom" align="right" class="smalltext">
			<span class="nav">', $context['previous_next'], '</span>
		</td>
	</tr>
</table>';

	// Show the page index... "Pages: [1]".
	echo '
<table width="100%" cellpadding="3" cellspacing="0" border="0" class="tborder" style="border-bottom: 0;">
	<tr>
		<td align="left" class="catbg" width="100%" height="35">
			<table cellpadding="3" cellspacing="0" width="100%">
				<tr>
					<td>
						<strong>', $txt['pages'], ':</strong> ', $context['page_index'];

	// Show a "go down" link?
	if (!empty($modSettings['topbottomEnable']))
		echo $context['menu_separator'], '<a href="#bot">', $settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/go_down.gif" alt="' . $txt['go_down'] . '" border="0" align="top" />' : $txt['go_down'], '</a>';

	echo '
					</td>
					<td align="right" style="font-size: smaller;">', theme_show_main_buttons(), '</td>
				</tr>
			</table>
		</td>
	</tr>
</table>';

	// Is this topic also a poll?
	if ($context['is_poll'])
	{
		echo '
<table cellpadding="3" cellspacing="0" border="0" width="100%" class="tborder" style="border-bottom: 0;">
	<tr class="titlebg">
		<td colspan="2" valign="middle" align="left" style="padding-left: 6px;">
			<img src="', $settings['images_url'], '/topic/', $context['poll']['is_locked'] ? 'normal_poll_locked' : 'normal_poll', '.gif" alt="" align="top" /> ', $txt['poll'], '
		</td>
	</tr>
	<tr class="windowbg">
		<td width="5%" valign="top"><strong>', $txt['poll_question'], ':</strong></td>
		<td>
			', $context['poll']['question'];

		// Are they not allowed to vote but allowed to view the options?
		if ($context['poll']['show_results'] || !$context['allow_vote'])
		{
			echo '
			<table>
				<tr>
					<td style="padding-top: 2ex;">
						<table border="0" cellpadding="0" cellspacing="0">';

			// Show each option with its corresponding percentage bar.
			foreach ($context['poll']['options'] as $option)
				echo '
							<tr>
								<td', $option['voted_this'] ? ' style="font-weight: bold;"' : '', '>', $option['option'], '</td>', $context['allow_poll_view'] ? '
								<td width="7">&nbsp;</td>
								<td nowrap="nowrap">' . $option['bar'] . $option['votes'] . ' (' . $option['percent'] . '%)</td>' : '', '
							</tr>';

			echo '
						</table>
					</td>
					<td valign="bottom" style="padding-left: 15px;">';

			// If they are allowed to revote - show them a link!
			if ($context['allow_change_vote'])
				echo '
						<a href="', $scripturl, '?action=vote;topic=', $context['current_topic'], '.', $context['start'], ';poll=', $context['poll']['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['poll_change_vote'], '</a><br />';

			// If we're viewing the results... maybe we want to go back and vote?
			if ($context['allow_return_vote'])
				echo '
						<a href="', $scripturl, '?topic=', $context['current_topic'], '.', $context['start'], '">', $txt['poll_return_vote'], '</a><br />';

			// If they're allowed to lock the poll, show a link!
			if ($context['poll']['lock'])
				echo '
						<a href="', $scripturl, '?action=lockvoting;topic=', $context['current_topic'], '.', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '">', !$context['poll']['is_locked'] ? $txt['poll_lock'] : $txt['poll_unlock'], '</a><br />';

			// If they're allowed to edit the poll... guess what... show a link!
			if ($context['poll']['edit'])
				echo '
						<a href="', $scripturl, '?action=editpoll;topic=', $context['current_topic'], '.', $context['start'], '">', $txt['poll_edit'], '</a><br />';

			if ($context['can_remove_poll'])
				echo '
						<a href="' . $scripturl . '?action=removepoll;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['poll_remove_warn'] . '\');">', $txt['poll_remove'], '</a>';

			echo '
					</td>
				</tr>', $context['allow_poll_view'] ? '
				<tr>
					<td colspan="2"><strong>' . $txt['poll_total_voters'] . ': ' . $context['poll']['total_votes'] . '</strong></td>
				</tr>' : '', '
			</table><br />';
		}
		// They are allowed to vote!  Go to it!
		else
		{
			echo '
			<form action="', $scripturl, '?action=vote;topic=', $context['current_topic'], '.', $context['start'], ';poll=', $context['poll']['id'], '" method="post" accept-charset="', $context['character_set'], '" style="margin: 0px;">
				<table>
					<tr>
						<td colspan="2">';

			// Show a warning if they are allowed more than one option.
			if ($context['poll']['allowed_warning'])
				echo '
							', $context['poll']['allowed_warning'], '
						</td>
					</tr><tr>
						<td>';

			// Show each option with its button - a radio likely.
			foreach ($context['poll']['options'] as $option)
				echo '
							<label for="', $option['id'], '">', $option['vote_button'], ' ', $option['option'], '</label><br />';

			echo '
						</td>
						<td valign="bottom" style="padding-left: 15px;">';

			// Allowed to view the results? (without voting!)
			if ($context['show_view_results_button'])
				echo '
							<a href="', $scripturl, '?topic=', $context['current_topic'], '.', $context['start'], ';viewresults">', $txt['poll_results'], '</a><br />';

			// Show a link for locking the poll as well...
			if ($context['poll']['lock'])
				echo '
							<a href="', $scripturl, '?action=lockvoting;topic=', $context['current_topic'], '.', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '">', (!$context['poll']['is_locked'] ? $txt['poll_lock'] : $txt['poll_unlock']), '</a><br />';

			// Want to edit it?  Click right here......
			if ($context['poll']['edit'])
				echo '
							<a href="', $scripturl, '?action=editpoll;topic=', $context['current_topic'], '.', $context['start'], '">', $txt['poll_edit'], '</a><br />';

			if ($context['can_remove_poll'])
				echo '
						<a href="' . $scripturl . '?action=removepoll;topic=' . $context['current_topic'] . '.' . $context['start'] . '" onclick="return confirm(\'' . $txt['poll_remove_warn'] . '\');">', $txt['poll_remove'], '</a>';

			echo '
						</td>
					</tr><tr>
						<td colspan="2"><input type="submit" value="', $txt['poll_vote'], '" class="button_submit" /></td>
					</tr>
				</table>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>';
		}

		echo '
		</td>
	</tr>
</table>';
	}

	// Does this topic have some events linked to it?
	if (!empty($context['linked_calendar_events']))
	{
		echo '
<table cellpadding="3" cellspacing="0" border="0" width="100%" class="tborder">
	<tr class="titlebg">
		<td colspan="2" valign="middle" align="left" style="padding-left: 6px;">
			', $txt['calendar_linked_events'], '
		</td>
	</tr>
	<tr class="windowbg">
		<td width="5%" valign="top">
			<ul>';
		foreach ($context['linked_calendar_events'] as $event)
		{
			echo '
				<li>
					<strong>', ($event['can_edit'] ? '<a href="' . $event['modify_href'] . '" style="color: red;">*</a> ' : ''), $event['title'], '</strong>: ', $event['start_date'], ($event['start_date'] != $event['end_date'] ? ' - ' . $event['end_date'] : '');
			echo '
				</li>';
		}
		echo '
			</ul>
		</td>
	</tr>
</table>';
	}

	// Show the topic information - icon, subject, etc.
	echo '
<table cellpadding="3" cellspacing="0" border="0" width="100%" align="center" class="tborder" style="border-bottom: 0;">
	<tr class="titlebg">
		<td valign="middle" align="left" width="15%" style="padding-left: 6px;">
			<img src="', $settings['images_url'], '/topic/', $context['class'], '.gif" alt="" />
			', $txt['author'], '
		</td>
		<td valign="middle" align="left" width="85%" style="padding-left: 6px;" id="top_subject">
			', $txt['topic'], ': ', $context['subject'], ' &nbsp;(', $txt['read'], ' ', $context['num_views'], ' ', $txt['times'], ')
		</td>
	</tr>
</table>';

	echo '
<form action="', $scripturl, '?action=quickmod2;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="', $context['character_set'], '" name="quickModForm" id="quickModForm" style="margin: 0;" onsubmit="return oQuickModify.bInEditMode ? oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_id'] . '\') : false;">';

	echo '
<table cellpadding="0" cellspacing="0" border="0" width="100%" class="bordercolor">';

	// Get all the messages...
	$removableMessageIDs = array();
	while ($message = $context['get_message']())
	{
		if ($message['can_remove'])
			$removableMessageIDs[] = $message['id'];

		echo '
	<tr><td style="padding: 1px 1px 0 1px;">';

		// Show the message anchor and a "new" anchor if this message is new.
		if ($message['id'] != $context['first_message'])
			echo '
		<a id="msg', $message['id'], '"></a>', $message['first_new'] ? '<a id="new"></a>' : '';

		echo '
		<table cellpadding="3" cellspacing="0" border="0" width="100%">
			<tr><td class="', $message['approved'] ? ($message['alternate'] == 0 ? 'windowbg' : 'windowbg2') : 'approvebg', '">';

		// Show information about the poster of this message.
		echo '
				<table width="100%" cellpadding="5" cellspacing="0">
					<tr>
						<td valign="top" width="15%" rowspan="2">
							<strong>', $message['member']['link'], '</strong><br />
							<span class="smalltext">';

		// Show the member's custom title, if they have one.
		if (isset($message['member']['title']) && $message['member']['title'] != '')
			echo '
								', $message['member']['title'], '<br />';

		// Show the member's primary group (like 'Administrator') if they have one.
		if (isset($message['member']['group']) && $message['member']['group'] != '')
			echo '
								', $message['member']['group'], '<br />';

		// Don't show these things for guests.
		if (!$message['member']['is_guest'])
		{
			// Show the post group if and only if they have no other group or the option is on, and they are in a post group.
			if ((empty($settings['hide_post_group']) || $message['member']['group'] == '') && $message['member']['post_group'] != '')
				echo '
								', $message['member']['post_group'], '<br />';
			echo '
								', $message['member']['group_stars'], '<br />';

			// Is karma display enabled?  Total or +/-?
			if ($modSettings['karmaMode'] == '1')
				echo '
								<br />
								', $modSettings['karmaLabel'], ' ', $message['member']['karma']['good'] - $message['member']['karma']['bad'], '<br />';
			elseif ($modSettings['karmaMode'] == '2')
				echo '
								<br />
								', $modSettings['karmaLabel'], ' +', $message['member']['karma']['good'], '/-', $message['member']['karma']['bad'], '<br />';

			// Is this user allowed to modify this member's karma?
			if ($message['member']['karma']['allow'])
				echo '
								<a href="', $scripturl, '?action=modifykarma;sa=applaud;uid=', $message['member']['id'], ';topic=', $context['current_topic'], '.' . $context['start'], ';m=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $modSettings['karmaApplaudLabel'], '</a>
								<a href="', $scripturl, '?action=modifykarma;sa=smite;uid=', $message['member']['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';m=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $modSettings['karmaSmiteLabel'], '</a><br />';

			// Show online and offline buttons?
			if (!empty($modSettings['onlineEnable']))
				echo '
								', $context['can_send_pm'] ? '<a href="' . $message['member']['online']['href'] . '" title="' . $message['member']['online']['label'] . '">' : '', $settings['use_image_buttons'] ? '<img src="' . $message['member']['online']['image_href'] . '" alt="' . $message['member']['online']['text'] . '" border="0" align="middle" />' : $message['member']['online']['text'], $context['can_send_pm'] ? '</a>' : '', $settings['use_image_buttons'] ? '<span class="smalltext"> ' . $message['member']['online']['text'] . '</span>' : '', '<br /><br />';

			// Show the member's gender icon?
			if (!empty($settings['show_gender']) && $message['member']['gender']['image'] != '' && !isset($context['disabled_fields']['gender']))
				echo '
								', $txt['gender'], ': ', $message['member']['gender']['image'], '<br />';

			// Show how many posts they have made.
			if (!isset($context['disabled_fields']['posts']))
				echo '
								', $txt['member_postcount'], ': ', $message['member']['posts'], '<br />';

			// Any custom fields for standard placement?
			if (!empty($message['member']['custom_fields']))
			{
				foreach ($message['member']['custom_fields'] as $custom)
					if (empty($custom['placement']) || empty($custom['value']))
						echo '
								', $custom['title'], ': ', $custom['value'], '<br />';
			}

			echo '<br />';

			// Show avatars, images, etc.?
			if (!empty($settings['show_user_images']) && empty($options['show_no_avatars']) && !empty($message['member']['avatar']['image']))
				echo '
								', $message['member']['avatar']['image'], '<br />';

			// Show their personal text?
			if (!empty($settings['show_blurb']) && $message['member']['blurb'] != '')
				echo '
								', $message['member']['blurb'], '<br />
								<br />';

			// Any custom fields to show as icons?
			if (!empty($message['member']['custom_fields']))
			{
				foreach ($message['member']['custom_fields'] as $custom)
					if ($custom['placement'] == 1 || empty($custom['value']))
						echo '
								', $custom['value'];
			}

			// This shows the popular messaging icons.
			if ($message['member']['has_messenger'] && $message['member']['can_view_profile'])
				echo '
								', !isset($context['disabled_fields']['icq']) ? $message['member']['icq']['link'] : '', '
								', !isset($context['disabled_fields']['msn']) ? $message['member']['msn']['link'] : '', '
								', !isset($context['disabled_fields']['aim']) ? $message['member']['aim']['link'] : '', '
								', !isset($context['disabled_fields']['yim']) ? $message['member']['yim']['link'] : '', '
								<br />';

			// Show the profile, website, email address, and personal message buttons.
			if ($settings['show_profile_buttons'])
			{
				// Don't show the profile button if you're not allowed to view the profile.
				if ($message['member']['can_view_profile'])
					echo '
								<a href="', $message['member']['href'], '">', ($settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/icons/profile_sm.gif" alt="' . $txt['view_profile'] . '" title="' . $txt['view_profile'] . '" border="0" />' : $txt['view_profile']), '</a>';

				// Don't show an icon if they haven't specified a website.
				if ($message['member']['website']['url'] != '' && !isset($context['disabled_fields']['website']))
					echo '
								<a href="', $message['member']['website']['url'], '" title="' . $message['member']['website']['title'] . '" target="_blank" class="new_win">', ($settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/www_sm.gif" alt="' . $message['member']['website']['title'] . '" border="0" />' : $txt['www']), '</a>';

				// Don't show the email address if they want it hidden.
				if (in_array($message['member']['show_email'], array('yes', 'yes_permission_override', 'no_through_forum')))
					echo '
								<a href="', $scripturl, '?action=emailuser;sa=email;msg=', $message['id'], '" rel="nofollow">', ($settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/email_sm.gif" alt="' . $txt['email'] . '" title="' . $txt['email'] . '" />' : $txt['email']), '</a>';

				// Since we know this person isn't a guest, you *can* message them.
				if ($context['can_send_pm'])
					echo '
								<a href="', $scripturl, '?action=pm;sa=send;u=', $message['member']['id'], '" title="', $message['member']['online']['is_online'] ? $txt['pm_online'] : $txt['pm_offline'], '">', $settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/im_' . ($message['member']['online']['is_online'] ? 'on' : 'off') . '.gif" alt="' . ($message['member']['online']['is_online'] ? $txt['pm_online'] : $txt['pm_offline']) . '" border="0" />' : ($message['member']['online']['is_online'] ? $txt['pm_online'] : $txt['pm_offline']), '</a>';
			}
		}
		// Otherwise, show the guest's email.
		elseif (in_array($message['member']['show_email'], array('yes', 'yes_permission_override', 'no_through_forum')) && $message['member']['email'])
			echo '
								<br />
								<br />
								<a href="', $scripturl, '?action=emailuser;sa=email;msg=', $message['id'], '" rel="nofollow">', ($settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/email_sm.gif" alt="' . $txt['email'] . '" title="' . $txt['email'] . '" border="0" />' : $txt['email']), '</a>';

		// Done with the information about the poster... on to the post itself.
		echo '
							</span>
						</td>
						<td valign="top" width="85%" height="100%">
							<table width="100%" border="0"><tr>
								<td align="left" valign="middle"><img src="', $message['icon_url'] . '" alt="" border="0"', $message['can_modify'] ? ' id="msg_icon_' . $message['id'] . '"' : '', ' /></td>
								<td align="left" valign="middle">
									<div style="font-weight: bold;" id="subject_', $message['id'], '">
										<a href="', $message['href'], '" rel="nofollow">', $message['subject'], '</a>
									</div>';

		// If this is the first post, (#0) just say when it was posted - otherwise give the reply #.
		echo '
									<span class="smalltext">&#171; <strong>', !empty($message['counter']) ? $txt['reply'] . ' #' . $message['counter'] : '', ' ', $txt['on'], ':</strong> ', $message['time'], ' &#187;</span></td>
								<td align="right" valign="bottom" height="20" nowrap="nowrap" style="font-size: smaller;">';

		// Maybe we can approve it, maybe we should?
		if ($message['can_approve'])
			echo '
									<a href="', $scripturl, '?action=moderate;area=postmod;sa=approve;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '">', ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/approve.gif" alt="' . $txt['approve'] . '" border="0" />' : $txt['approve']), '</a>';

		// Can they reply?  Have they turned on quick reply?
		if ($context['can_reply'] && !empty($options['display_quick_reply']))
			echo '
									<a href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';num_replies=', $context['num_replies'], '" onclick="return oQuickReply.quote(', $message['id'], ');">', ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/quote.gif" alt="' . $txt['reply_quote'] . '" border="0" />' : $txt['reply_quote']), '</a>';
		// So... quick reply is off, but they *can* reply?
		elseif ($context['can_reply'])
			echo '
									<a href="', $scripturl, '?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';num_replies=', $context['num_replies'], '">', ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/quote.gif" alt="' . $txt['reply_quote'] . '" border="0" />' : $txt['reply_quote']), '</a>';

		// Can the user modify the contents of this post?
		if ($message['can_modify'])
			echo '
									<a href="', $scripturl, '?action=post;msg=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], '">', ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/modify.gif" alt="' . $txt['modify_msg'] . '" border="0" />' : $txt['modify']), '</a>';

		// How about... even... remove it entirely?!
		if ($message['can_remove'])
			echo '
									<a href="', $scripturl, '?action=deletemsg;topic=', $context['current_topic'], '.', $context['start'], ';msg=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['remove_message'], '?\');">', ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/delete.gif" alt="' . $txt['remove_message'] . '" border="0" />' : $txt['remove']), '</a>';

		// What about splitting it off the rest of the topic?
		if ($context['can_split'] && !empty($context['num_replies']))
			echo '
									<a href="', $scripturl, '?action=splittopics;topic=', $context['current_topic'], '.0;at=', $message['id'], '">', ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/split.gif" alt="' . $txt['split'] . '" border="0" />' : $txt['split']), '</a>';

		// Show a checkbox for quick moderation?
		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $message['can_remove'])
			echo '
									<div style="display: none;" id="in_topic_mod_check_', $message['id'], '"></div>';

		// Show the post itself, finally!
		echo '
								</td>
							</tr></table>
							<hr width="100%" size="1" class="hrcolor" />
							<div class="post"', $message['can_modify'] ? ' id="msg_' . $message['id'] . '"' : '', '>', $message['body'], '</div>', $message['can_modify'] ? '
							<img src="' . $settings['images_url'] . '/icons/modify_inline.gif" alt="" title="' . $txt['modify_msg'] . '" align="right" id="modify_button_' . $message['id'] . '" style="cursor: pointer;" onclick="oQuickModify.modifyMsg(\'' . $message['id'] . '\')" />' : '', '
						</td>
					</tr>';

		// Now for the attachments, signature, ip logged, etc...
		echo '
					<tr>
						<td valign="bottom" class="smalltext">
							<table width="100%" border="0"><tr>
								<td align="left" colspan="2" class="smalltext">';

		// Assuming there are attachments...
		if (!empty($message['attachment']))
		{
			echo '
									<hr width="100%" size="1" class="hrcolor" />';

			$last_approved_state = 1;
			foreach ($message['attachment'] as $attachment)
			{
				// Show a special box for unpproved attachments...
				if ($attachment['is_approved'] != $last_approved_state)
				{
					$last_approved_state = 0;
					echo '
									<fieldset>
										<legend>', $txt['attach_awaiting_approve'], '&nbsp;[<a href="', $scripturl, '?action=attachapprove;sa=all;mid=', $message['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve_all'], '</a>]</legend>';
				}

				if ($attachment['is_image'])
				{
					if ($attachment['thumbnail']['has_thumb'])
						echo '
									<a href="', $attachment['href'], ';image" id="link_', $attachment['id'], '" onclick="', $attachment['thumbnail']['javascript'], '"><img src="', $attachment['thumbnail']['href'], '" alt="" id="thumb_', $attachment['id'], '" border="0" /></a><br />';
					else
						echo '
									<img src="' . $attachment['href'] . ';image" alt="" width="' . $attachment['width'] . '" height="' . $attachment['height'] . '" border="0" /><br />';
				}
				echo '
										<a href="' . $attachment['href'] . '"><img src="' . $settings['images_url'] . '/icons/clip.gif" align="middle" alt="*" border="0" />&nbsp;' . $attachment['name'] . '</a> ';

				if (!$attachment['is_approved'])
					echo '
										[<a href="', $scripturl, '?action=attachapprove;sa=approve;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['approve'], '</a>]&nbsp;|&nbsp;[<a href="', $scripturl, '?action=attachapprove;sa=reject;aid=', $attachment['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['delete'], '</a>] ';
				echo '
										(', $attachment['size'], ($attachment['is_image'] ? ', ' . $attachment['real_width'] . 'x' . $attachment['real_height'] . ' - ' . $txt['attach_viewed'] : ' - ' . $txt['attach_downloaded']) . ' ' . $attachment['downloads'] . ' ' . $txt['attach_times'] . '.)<br />';
			}

			// If we had unapproved attachments clean up.
			if ($last_approved_state == 0)
				echo '
									</fieldset>';
		}

		echo '
								</td>
							</tr><tr>
								<td align="left" valign="bottom" class="smalltext" id="modified_', $message['id'], '">';

		// Show "� Last Edit: Time by Person �" if this post was edited.
		if ($settings['show_modify'] && !empty($message['modified']['name']))
			echo '
									&#171; <em>', $txt['last_edit'], ': ', $message['modified']['time'], ' ', $txt['by'], ' ', $message['modified']['name'], '</em> &#187;';

		echo '
								</td>
								<td align="right" valign="bottom" class="smalltext">';

		// Maybe they want to report this post to the moderator(s)?
		if ($context['can_report_moderator'])
			echo '
									<a href="', $scripturl, '?action=reporttm;topic=', $context['current_topic'], '.', $message['counter'], ';msg=', $message['id'], '">', $txt['report_to_mod'], '</a> &nbsp;';
		echo '
									<img src="', $settings['images_url'], '/ip.gif" alt="" border="0" />';

		// Show the IP to this user for this post - because you can moderate?
		if ($context['can_moderate_forum'] && !empty($message['member']['ip']))
			echo '
									<a href="', $scripturl, '?action=', !empty($message['member']['is_guest']) ? 'trackip' : 'profile;area=tracking;sa=ip;u=' . $message['member']['id'], ';searchip=', $message['member']['ip'], '">', $message['member']['ip'], '</a> <a href="', $scripturl, '?action=helpadmin;help=see_admin_ip" onclick="return reqWin(this.href);" class="help">(?)</a>';
		// Or, should we show it because this is you?
		elseif ($message['can_see_ip'])
			echo '
									<a href="', $scripturl, '?action=helpadmin;help=see_member_ip" onclick="return reqWin(this.href);" class="help">', $message['member']['ip'], '</a>';
		// Okay, are you at least logged in?  Then we can show something about why IPs are logged...
		elseif (!$context['user']['is_guest'])
			echo '
									<a href="', $scripturl, '?action=helpadmin;help=see_member_ip" onclick="return reqWin(this.href);" class="help">', $txt['logged'], '</a>';
		// Otherwise, you see NOTHING!
		else
			echo '
									', $txt['logged'];

		echo '
								</td>
							</tr></table>';

		// Are there any custom profile fields for above the signature?
		if (!empty($message['member']['custom_fields']))
		{
			$shown = false;
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if ($custom['placement'] != 2 || empty($custom['value']))
					continue;
				if (empty($shown))
				{
					$shown = true;
					echo '
							<div class="custom_fields_above_signature">
								<ul class="reset nolist>';
				}
				echo '
									<li>', $custom['value'], '</li>';
			}
			if ($shown)
				echo '
								</ul>
							</div>';
		}

		// Show the member's signature?
		if (!empty($message['member']['signature']) && empty($options['show_no_signatures']) && $context['signature_enabled'])
			echo '
							<div class="signature">', $message['member']['signature'], '</div>';

		echo '
						</td>
					</tr>
				</table>
			</td></tr>
		</table>
	</td></tr>';
	}
	echo '
</table>
<a id="lastPost"></a>
<table border="0" width="100%" cellspacing="0" cellpadding="0" class="bordercolor"><tr><td>
	<table width="100%" border="0" cellpadding="3" cellspacing="1" class="bordercolor">
		<tr>
			<td align="left" class="catbg" width="100%" height="30">
				<table cellpadding="3" cellspacing="0" width="100%">
					<tr>
						<td>
							<a id="bot"></a><strong>', $txt['pages'], ':</strong> ', $context['page_index'], (!empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '<a href="#top">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/go_up.gif" alt="' . $txt['go_up'] . '" border="0" align="top" />' : $txt['go_up']) . '</a>' : ''), '
						</td>
						<td align="right" style="font-size: smaller;">
							', theme_show_main_buttons(), '&nbsp;
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</td></tr></table>

<table border="0" width="100%" cellpadding="0" cellspacing="0">
	<tr>';
	if ($settings['linktree_inline'])
		echo '
		<td valign="top" align="left">', theme_linktree(), '</td> ';
	echo '
		<td valign="top" align="right" class="smalltext"> <span class="nav"> ', $context['previous_next'], '</span></td>
	</tr><tr>
		<td align="left" colspan="2" style="padding-top: 4px;" id="moderationbuttons">
			', theme_show_mod_buttons(), '
		</td>
	</tr>
</table>';

	// This button has an identity crisis: it wishes it were an image. (kinda like the characters in The Wizard of Oz.)
	echo '
</form>';

	// Show the jumpto box, or actually...let Javascript do it.
	echo '
<table border="0" width="100%">
	<tr>
		<td align="right" colspan="2" id="display_jump_to">&nbsp;</td>
	</tr>
</table>';

	if ($context['can_reply'] && !empty($options['display_quick_reply']))
	{
		echo '
<a id="quickreply"></a>
<table border="0" cellspacing="1" cellpadding="3" class="clear bordercolor" width="100%">
	<tr>
		<td colspan="2" class="catbg"><a href="javascript:oQuickReply.swap();"><img src="', $settings['images_url'], '/', $options['display_quick_reply'] == 2 ? 'collapse' : 'expand', '.gif" alt="+" border="0" id="quickReplyExpand" /></a> <a href="javascript:oQuickReply.swap();">', $txt['quick_reply'], '</a></td>
	</tr>
	<tr id="quickReplyOptions"', $options['display_quick_reply'] == 2 ? '' : ' style="display: none"', '>
		<td class="windowbg" width="25%" valign="top">', $txt['quick_reply_desc'], $context['is_locked'] ? '<br /><br /><strong>' . $txt['quick_reply_warning'] . '</strong>' : '', '</td>
		<td class="windowbg" width="75%" align="center">
			', $context['can_reply_approved'] ? '' : '<em>' . $txt['wait_for_approval'] . '</em>', '
			', !$context['can_reply_approved'] && $context['require_verification'] ? '<br />' : '', '
			<form action="', $scripturl, '?action=post2', empty($context['current_board']) ? '' : ';board=' . $context['current_board'], '" method="post" accept-charset="', $context['character_set'], '" name="postmodify" id="postmodify" style="margin:0px;">
				<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />
				<input type="hidden" name="subject" value="' . $context['response_prefix'] . $context['subject'] . '" />
				<input type="hidden" name="icon" value="xx" />
				<input type="hidden" name="from_qr" value="1" />
				<input type="hidden" name="notify" value="', $context['is_marked_notify'] || !empty($options['auto_notify']) ? '1' : '0', '" />
				<input type="hidden" name="not_approved" value="', !$context['can_reply_approved'], '" />
				<input type="hidden" name="goback" value="', empty($options['return_to_post']) ? '0' : '1', '" />
				<input type="hidden" name="num_replies" value="', $context['num_replies'], '" />';

		// Guests just need more.
		if ($context['user']['is_guest'])
			echo '
				<strong>', $txt['name'], ':</strong> <input type="text" name="guestname" value="', $context['name'], '" size="25" class="input_text" tabindex="', $context['tabindex']++, '" />
				<strong>', $txt['email'], ':</strong> <input type="text" name="email" value="', $context['email'], '" size="25" class="input_text" tabindex="', $context['tabindex']++, '" /><br />';

		// Is visual verification enabled?
		if ($context['require_verification'])
			echo '
				<strong>', $txt['verification'], ':</strong>', template_control_verification($context['visual_verification_id'], 'quick_reply'), '<br />';

		echo '
				<textarea cols="75" rows="7" style="width: 95%; height: 100px;" name="message" tabindex="', $context['tabindex']++, '"></textarea><br />
				<input type="submit" name="post" value="' . $txt['post'] . '" accesskey="s" tabindex="', $context['tabindex']++, '" class="button_submit" />
				<input type="submit" name="preview" value="' . $txt['preview'] . '" accesskey="p" tabindex="', $context['tabindex']++, '" class="button_submit" />';
		if ($context['show_spellchecking'])
			echo '
				<input type="button" value="', $txt['spell_check'], '" onclick="spellCheck(\'postmodify\', \'message\');" class="button_submit" />';
		echo '
				<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
			</form>
		</td>
	</tr>
</table>';
	}

	if ($context['show_spellchecking'])
		echo '
<form action="', $scripturl, '?action=spellcheck" method="post" accept-charset="', $context['character_set'], '" name="spell_form" id="spell_form" target="spellWindow"><input type="hidden" name="spellstring" value="" /></form>
<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/spellcheck.js"></script>';

	echo '
<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/topic.js"></script>
<script type="text/javascript"><!-- // --><![CDATA[';

	if (!empty($options['display_quick_reply']))
		echo '
	var oQuickReply = new QuickReply({
		bDefaultCollapsed: ', !empty($options['display_quick_reply']) && $options['display_quick_reply'] == 2 ? 'false' : 'true', ',
		iTopicId: ', $context['current_topic'], ',
		iStart: ', $context['start'], ',
		sScriptUrl: smf_scripturl,
		sImagesUrl: "', $settings['images_url'], '",
		sContainerId: "quickReplyOptions",
		sImageId: "quickReplyExpand",
		sImageCollapsed: "collapse.gif",
		sImageExpanded: "expand.gif",
		sJumpAnchor: "quickreply"
	});';

	if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && $context['can_remove_post'])
		echo '
	var oInTopicModeration = new InTopicModeration({
		sSelf: \'oInTopicModeration\',
		sCheckboxContainerMask: \'in_topic_mod_check_\',
		aMessageIds: [\'', implode('\', \'', $removableMessageIDs), '\'],
		sSessionId: \'', $context['session_id'], '\',
		sSessionVar: \'', $context['session_var'], '\',
		sButtonStrip: \'moderationbuttons\',
		bUseImageButton: true,
		bCanRemove: ', $context['can_remove_post'] ? 'true' : 'false', ',
		sRemoveButtonLabel: \'', $txt['quickmod_delete_selected'], '\',
		sRemoveButtonImage: \'', $settings['lang_images_url'], '/delete_selected.gif\',
		sRemoveButtonConfirm: \'', $txt['quickmod_confirm'], '\',
		bCanRestore: ', $context['can_restore_msg'] ? 'true' : 'false', ',
		sRestoreButtonLabel: \'', $txt['quick_mod_restore'], '\',
		sRestoreButtonImage: \'', $settings['lang_images_url'], '/restore_selected.gif\',
		sRestoreButtonConfirm: \'', $txt['quickmod_confirm'], '\',
		sFormId: \'quickModForm\'
	});';

	echo '
	if (\'XMLHttpRequest\' in window)
	{
		var oQuickModify = new QuickModify({
			sScriptUrl: smf_scripturl,
			bShowModify: ', $settings['show_modify'] ? 'true' : 'false', ',
			iTopicId: ', $context['current_topic'], ',
			sTemplateBodyEdit: ', JavaScriptEscape('
				<div id="quick_edit_body_container" style="width: 90%">
					<div id="error_box" style="padding: 4px;" class="error"></div>
					<textarea class="editor" name="message" rows="12" style="' . ($context['browser']['is_ie8'] ? 'max-width: 100%; min-width: 100%' : 'width: 100%') . '; margin-bottom: 10px;" tabindex="' . $context['tabindex']++ . '">%body%</textarea><br />
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
					<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />
					<input type="hidden" name="msg" value="%msg_id%" />
					<div class="righttext">
						<input type="submit" name="post" value="' . $txt['save'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifySave(\'' . $context['session_id'] . '\', \'' . $context['session_var'] . '\');" accesskey="s" class="button_submit" />&nbsp;&nbsp;' . ($context['show_spellchecking'] ? '<input type="button" value="' . $txt['spell_check'] . '" tabindex="' . $context['tabindex']++ . '" onclick="spellCheck(\'quickModForm\', \'message\');" class="button_submit" />&nbsp;&nbsp;' : '') . '<input type="submit" name="cancel" value="' . $txt['modify_cancel'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifyCancel();" class="button_submit" />
					</div>
				</div>'), ',
			sTemplateSubjectEdit: ', JavaScriptEscape('<input type="text" style="width: 90%" name="subject" value="%subject%" size="80" maxlength="80" tabindex="' . $context['tabindex']++ . '" class="input_text" />'), ',
			sTemplateBodyNormal: ', JavaScriptEscape('%body%'), ',
			sTemplateSubjectNormal: ', JavaScriptEscape('<a href="' . $scripturl . '?topic=' . $context['current_topic'] . '.msg%msg_id%#msg%msg_id%" rel="nofollow">%subject%</a>'), ',
			sTemplateTopSubject: ', JavaScriptEscape($txt['topic'] . ': %subject% &nbsp;(' . $txt['read'] . ' ' . $context['num_views'] . ' ' . $txt['times'] . ')'), ',
			sErrorBorderStyle: ', JavaScriptEscape('1px solid red'), '
		});

		aJumpTo[aJumpTo.length] = new JumpTo({
			sContainerId: "display_jump_to",
			sJumpToTemplate: "<label class=\"smalltext\" for=\"%select_id%\">', $context['jump_to']['label'], ':<" + "/label> %dropdown_list%",
			iCurBoardId: ', $context['current_board'], ',
			iCurBoardChildLevel: ', $context['jump_to']['child_level'], ',
			sCurBoardName: "', $context['jump_to']['board_name'], '",
			sBoardChildLevelIndicator: "==",
			sBoardPrefix: "=> ",
			sCatSeparator: "-----------------------------",
			sCatPrefix: "",
			sGoButtonLabel: "', $txt['go'], '"
		});

		aIconLists[aIconLists.length] = new IconList({
			sBackReference: "aIconLists[" + aIconLists.length + "]",
			sIconIdPrefix: "msg_icon_",
			sScriptUrl: smf_scripturl,
			bShowModify: ', $settings['show_modify'] ? 'true' : 'false', ',
			iBoardId: ', $context['current_board'], ',
			iTopicId: ', $context['current_topic'], ',
			sSessionId: "', $context['session_id'], '",
			sSessionVar: "', $context['session_var'], '",
			sLabelIconList: "', $txt['message_icon'], '",
			sBoxBackground: "transparent",
			sBoxBackgroundHover: "#ffffff",
			iBoxBorderWidthHover: 1,
			sBoxBorderColorHover: "#adadad" ,
			sContainerBackground: "#ffffff",
			sContainerBorder: "1px solid #adadad",
			sItemBorder: "1px solid #ffffff",
			sItemBorderHover: "1px dotted gray",
			sItemBackground: "transparent",
			sItemBackgroundHover: "#e0e0f0"
		});
	}
// ]]></script>';

}

function theme_show_main_buttons()
{
	global $context, $settings, $options, $txt, $scripturl;

	$buttonArray = array();
	if ($context['can_reply'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=post;topic=' . $context['current_topic'] . '.' . $context['start'] . ';num_replies=' . $context['num_replies'] . '">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/reply.gif" alt="' . $txt['reply'] . '" border="0" />' : $txt['reply']) . '</a>';
	if ($context['can_add_poll'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=editpoll;add;topic=' . $context['current_topic'] . '.' . $context['start'] . '">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/addpoll.gif" alt="' . $txt['add_poll'] . '" border="0" />' : $txt['add_poll']) . '</a>';
	if ($context['can_mark_notify'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=notify;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . ($context['is_marked_notify'] ? $txt['notification_disable_topic'] : $txt['notification_enable_topic']) . '\');">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/' . ($context['is_marked_notify'] ? 'un' : '') . 'notify.gif" alt="' . $txt[$context['is_marked_notify'] ? 'unnotify' : 'notify'] . '" border="0" />' : $txt[$context['is_marked_notify'] ? 'unnotify' : 'notify']) . '</a>';
	if ($context['can_send_topic'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=emailuser;sa=sendtopic;topic=' . $context['current_topic'] . '.0">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/sendtopic.gif" alt="' . $txt['send_topic'] . '" border="0" />' : $txt['send_topic']) . '</a>';
	$buttonArray[] = '<a href="' . $scripturl . '?action=printpage;topic=' . $context['current_topic'] . '.0" rel="new_win nofollow">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/print.gif" alt="' . $txt['print'] . '" border="0" />' : $txt['print']) . '</a>';

	return implode($context['menu_separator'], $buttonArray);
}

function theme_show_mod_buttons()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	$moderationButtons = array();

	if ($context['can_move'])
		$moderationButtons[] = '<a href="' . $scripturl . '?action=movetopic;topic=' . $context['current_topic'] . '.0">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/admin_move.gif" alt="' . $txt['move_topic'] . '" border="0" />' : $txt['move_topic']) . '</a>';
	if ($context['can_delete'])
		$moderationButtons[] = '<a href="' . $scripturl . '?action=removetopic2;topic=' . $context['current_topic'] . '.0;' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . $txt['are_sure_remove_topic'] . '\');">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/admin_rem.gif" alt="' . $txt['remove_topic'] . '" border="0" />' : $txt['remove_topic']) . '</a>';
	if ($context['can_lock'])
		$moderationButtons[] = '<a href="' . $scripturl . '?action=lock;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/admin_lock.gif" alt="' . (empty($context['is_locked']) ? $txt['set_lock'] : $txt['set_unlock']) . '" border="0" />' : (empty($context['is_locked']) ? $txt['set_lock'] : $txt['set_unlock'])) . '</a>';
	if ($context['can_sticky'])
		$moderationButtons[] = '<a href="' . $scripturl . '?action=sticky;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/admin_sticky.gif" alt="' . (empty($context['is_sticky']) ? $txt['set_sticky'] : $txt['set_nonsticky']) . '" border="0" />' : (empty($context['is_sticky']) ? $txt['set_sticky'] : $txt['set_nonsticky'])) . '</a>';
	if ($context['can_merge'])
		$moderationButtons[] = '<a href="' . $scripturl . '?action=mergetopics;board=' . $context['current_board'] . '.0;from=' . $context['current_topic'] . '">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/merge.gif" alt="' . $txt['merge'] . '" border="0" />' : $txt['merge']) . '</a>';

	if ($context['calendar_post'])
		$moderationButtons[] = '<a href="' . $scripturl . '?action=post;calendar;msg=' . $context['topic_first_message'] . ';topic=' . $context['current_topic'] . '.0">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/linktocal.gif" alt="' . $txt['calendar_link'] . '" border="0" />' : $txt['calendar_link']) . '</a>';

	return implode($context['menu_separator'], $moderationButtons);
}

?>