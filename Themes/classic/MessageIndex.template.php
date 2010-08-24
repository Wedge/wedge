<?php
// Version: 2.0 RC3; MessageIndex

function template_main()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	echo '
<table width="100%" cellpadding="3" cellspacing="0">
	<tr>
		<td><a id="top"></a>', '</td>';
	if (!empty($settings['display_who_viewing']))
	{
		echo '
		<td class="smalltext" align="right">';
		if ($settings['display_who_viewing'] == 1)
			echo count($context['view_members']), ' ', count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members'];
		else
			echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . (empty($context['view_num_hidden']) || $context['can_moderate_forum'] ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');
		echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_board'], '</td>';
	}
	echo '
	</tr>
</table>';

	if (!empty($context['boards']) && (!empty($options['show_children']) || $context['start'] == 0))
	{
		echo '
<table border="0" width="100%" cellspacing="1" cellpadding="5" class="bordercolor">
	<tr class="titlebg">
		<td colspan="2">', $txt['board_name'], '</td>
		<td width="6%" align="center">', $txt['board_topics'], '</td>
		<td width="6%" align="center">', $txt['posts'], '</td>
		<td width="22%" align="center">', $txt['last_post'], '</td>
	</tr>';
		foreach ($context['boards'] as $board)
		{
			echo '
	<tr>
		<td class="windowbg" width="6%" align="center" valign="top">', $board['new'] ? '<img src="' . $settings['images_url'] . '/on.gif" alt="' . $txt['new_posts'] . '" title="' . $txt['new_posts'] . '" border="0" />' : '<img src="' . $settings['images_url'] . '/off.gif" alt="' . $txt['old_posts'] . '" title="' . $txt['old_posts'] . '" border="0" />', '</td>
		<td class="windowbg2" align="left" width="60%">
			<a id="b' . $board['id'] . '"></a>
			<strong>' . $board['link'] . '</strong><br />
			' . $board['description'];

			if (!empty($board['moderators']))
				echo '<br />
			<em class="smalltext">', count($board['moderators']) == 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</em>';

			if (!empty($board['children']))
			{
				$children = array();
				foreach ($board['children'] as $child)
				{
					if ($child['new'])
						$children[] = '<strong>' . $child['link'] . '</strong>';
					else
						$children[] = $child['link'];
				}

				echo '
			<br />
			<br /><em class="smalltext">', $txt['parent_boards'], ': ', implode(', ', $children), '</em>';
			}

			echo '
		</td>';

	if (!$board['is_redirect'])
		echo '
		<td class="windowbg" valign="middle" align="center" width="6%">
			', $board['topics'], '
		</td>
		<td class="windowbg" valign="middle" align="center" width="6%">
			', $board['posts'], '
		</td>';
	else
		echo '
		<td class="windowbg" valign="middle" align="center" colspan="2" width="12%">
			', $board['posts'], ' ', $txt['redirects'], '
		</td>';

	echo '
		<td class="windowbg2" valign="middle" width="22%">
			<span class="smalltext">
				', $board['last_post']['time'], '<br />
				', $txt['in'], ' ', $board['last_post']['link'], '<br />
				', $txt['by'], ' ', $board['last_post']['member']['link'], '
			</span>
		</td>
	</tr>';
		}
		echo '
</table>
<br />';
	}

	if (!empty($options['show_board_desc']) && $context['description'] != '')
	{
		echo '
<table width="100%" cellpadding="6" cellspacing="0" border="0" class="tborder" style="border-width: 1px 1px 0 1px;">
	<tr>
		<td align="left" class="catbg" width="100%" height="24">
			<span class="smalltext">', $context['description'], '</span>
		</td>
	</tr>
</table>';
	}

	if (!$context['no_topic_listing'])
	{
		echo '
<table width="100%" cellpadding="3" cellspacing="0" border="0" class="tborder" style="border-width: 1px 1px 0 1px;">
	<tr>
		<td align="left" class="catbg" width="100%" height="30">
			<table cellpadding="3" cellspacing="0" width="100%">
				<tr>
					<td><strong>', $txt['pages'], ':</strong> ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '<a href="#bot">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/go_down.gif" alt="' . $txt['go_down'] . '" border="0" align="top" />' : $txt['go_down']) . '</a>' : '', '</td>
					<td align="right" nowrap="nowrap" style="font-size: smaller;">', theme_show_buttons(), '</td>
				</tr>
			</table>
		</td>
	</tr>
</table>';

		// If Quick Moderation is enabled (and set to checkboxes - 1) start the form.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
			echo '
<form action="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="', $context['character_set'], '" style="margin: 0;">';

		echo '
<table border="0" width="100%" cellspacing="1" cellpadding="4" class="bordercolor">
	<tr class="titlebg">';
		if (!empty($context['topics']))
		{
			echo '
		<td width="9%" colspan="2">&nbsp;</td>
		<td width="42%"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a></td>
		<td width="14%"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=starter', $context['sort_by'] == 'starter' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['started_by'], $context['sort_by'] == 'starter' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a></td>
		<td width="4%" align="center"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a></td>
		<td width="4%" align="center"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=views', $context['sort_by'] == 'views' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['views'], $context['sort_by'] == 'views' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a></td>
		<td width="22%"><a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif" alt="" border="0" />' : '', '</a></td>';
			if (!empty($context['can_quick_mod']))
				echo '
		<td width="8%" valign="middle" align="center">', $options['display_quick_mod'] != 1 ? '&nbsp;' : '
			<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />
		', '</td>';
		}
		else
			echo '
		<td width="100%" colspan="7"><strong>', $txt['msg_alert_none'], '</strong></td>';
		echo '
	</tr>';

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
		{
			echo '
	<tr class="windowbg2">
		<td colspan="', !empty($context['can_quick_mod']) ? '8' : '7', '">
			<small>
				<span class="alert">!</span> ', $context['unapproved_posts_message'], '
			</small>
		</td>
	</tr>';
		}

		foreach ($context['topics'] as $topic)
		{
			// Calculate the color class of the topic.
			if ($context['can_approve_posts'] && $topic['unapproved_posts'])
				$color_class = $topic['approved'] ? 'approvebg' : 'approvetbg';
			else
				$color_class = 'windowbg';

			echo '
	<tr>
		<td class="windowbg2" valign="middle" align="center" width="5%">
			<img src="', $settings['images_url'], '/topic/', $topic['class'], '.gif" alt="" /></td>
		<td class="windowbg2" valign="middle" align="center" width="4%">
			<img src="', $settings[$context['icon_sources'][$topic['first_post']['icon']]], '/post/', $topic['first_post']['icon'], '.gif" alt="" border="0" align="middle" /></td>
		<td class="', $color_class, '" valign="middle" width="42%">
			', $topic['first_post']['link'], (!$context['can_approve_posts'] && !$topic['approved'] ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : ''), ' ', $topic['new'] && $context['user']['is_logged'] ? '<a href="' . $scripturl . '?topic=' . $topic['id'] . '.msg' . $topic['new_from'] . '#new"><img src="' . $settings['lang_images_url'] . '/new.gif" alt="' . $txt['new'] . '" border="0" /></a>' : '', ' <span class="smalltext">', $topic['pages'], '</span></td>
		<td class="windowbg2" valign="middle" width="14%">
			', $topic['first_post']['member']['link'], '</td>
		<td class="windowbg" valign="middle" width="4%" align="center">
			', $topic['replies'], '</td>
		<td class="windowbg" valign="middle" width="4%" align="center">
			', $topic['views'], '</td>
		<td class="windowbg2" valign="middle" width="22%">
			<span class="smalltext">', $topic['last_post']['time'], '<br />', $txt['by'], ' ', $topic['last_post']['member']['link'], '</span></td>';

			// Show the quick moderation options?
			if (!empty($context['can_quick_mod']))
			{
				echo '
		<td class="windowbg" valign="middle" align="center" width="8%">';
				if ($options['display_quick_mod'] == 1)
					echo '
			<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />';
				else
				{
					if ($topic['quick_mod']['remove'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=remove;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_remove.gif" width="16" alt="', $txt['remove_topic'], '" title="', $txt['remove_topic'], '" border="0" /></a>';
					if ($topic['quick_mod']['lock'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=lock;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_lock.gif" width="16" alt="', $txt['set_lock'], '" title="', $txt['set_lock'], '" border="0" /></a>';
					if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
						echo '<br />';
					if ($topic['quick_mod']['sticky'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=sticky;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_sticky.gif" width="16" alt="', $txt['set_sticky'], '" title="', $txt['set_sticky'], '" border="0" /></a>';
					if ($topic['quick_mod']['move'])
						echo '<a href="', $scripturl, '?action=movetopic;board=', $context['current_board'], '.', $context['start'], ';topic=', $topic['id'], '.0"><img src="', $settings['images_url'], '/icons/quick_move.gif" width="16" alt="', $txt['move_topic'], '" title="', $txt['move_topic'], '" border="0" /></a>';
				}
				echo '</td>';
			}
			echo '
	</tr>';
		}

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
		{
			echo '
	<tr class="titlebg">
		<td colspan="8" align="right">
			<select name="qaction"', $context['can_move'] ? ' onchange="this.form.moveItTo.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
				<option value="">--------</option>
				', $context['can_approve'] ? '<option value="approve">' . $txt['quick_mod_approve'] . '</option>' : '', '
				', $context['can_remove'] ? '<option value="remove">' . $txt['quick_mod_remove'] . '</option>' : '', '
				', $context['can_lock'] ? '<option value="lock">' . $txt['quick_mod_lock'] . '</option>' : '', '
				', $context['can_sticky'] ? '<option value="sticky">' . $txt['quick_mod_sticky'] . '</option>' : '', '
				', $context['can_move'] ? '<option value="move">' . $txt['quick_mod_move'] . ': </option>' : '', '
				', $context['can_merge'] ? '<option value="merge">' . $txt['quick_mod_merge'] . '</option>' : '', '
				', $context['can_restore'] ? '<option value="restore">' . $txt['quick_mod_restore'] . '</option>' : '', '
				', $context['user']['is_logged'] ? '<option value="markread">' . $txt['quick_mod_markread'] . '</option>' : '', '
			</select>';

			// Show a list of boards they can move the topic to.
			if ($context['can_move'])
			{
					echo '
			<select id="moveItTo" name="move_to" disabled="disabled">';

					foreach ($context['move_to_boards'] as $category)
					{
						echo '
				<optgroup label="', $category['name'], '">';
						foreach ($category['boards'] as $board)
								echo '
					<option value="', $board['id'], '"', $board['selected'] ? ' selected="selected"' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '</option>';
						echo '
				</optgroup>';
					}
					echo '
			</select>';
			}

			echo '
			<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return this.form.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="button_submit" />
		</td>
	</tr>';
		}

		echo '
</table>';

		// Finish off the form - again, if Quick Moderation is being done with checkboxes. (1)
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
			echo '
	<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
</form>';

		echo '
<table width="100%" cellpadding="3" cellspacing="0" border="0" class="tborder" style="border-width: 0 1px 1px 1px;">
	<tr>
		<td align="left" class="catbg" width="100%" height="30">
			<table cellpadding="3" cellspacing="0" width="100%">
				<tr>
					<td><a id="bot"></a><strong>', $txt['pages'], ':</strong> ', $context['page_index'], !empty($modSettings['topbottomEnable']) ? $context['menu_separator'] . '<a href="#top">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/go_up.gif" alt="' . $txt['go_up'] . '" border="0" align="top" />' : $txt['go_up']) . '</a>' : '', '</td>
					<td align="right" nowrap="nowrap" style="font-size: smaller;">', theme_show_buttons(), '</td>
				</tr>
			</table>
		</td>
	</tr>
</table>';
	}

	echo '
<table cellpadding="0" cellspacing="0" width="100%">';
	if ($settings['linktree_inline'])
		echo '
	<tr>
		<td colspan="3" valign="bottom">', theme_linktree(), '<br /><br /></td>
	</tr>';

	echo '
	<tr>';

	if (!$context['no_topic_listing'])
	{
		echo '
		<td class="smalltext" align="left" style="padding-top: 1ex;">', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
			<img src="' . $settings['images_url'] . '/topic/my_normal_post.gif" alt="" align="middle" /> ' . $txt['participation_caption'] . '<br />' : '', '
			<img src="' . $settings['images_url'] . '/topic/normal_post.gif" alt="" align="middle" /> ' . $txt['normal_topic'] . '<br />
			<img src="' . $settings['images_url'] . '/topic/hot_post.gif" alt="" align="middle" /> ' . sprintf($txt['hot_topics'], $modSettings['hotTopicPosts']) . '<br />
			<img src="' . $settings['images_url'] . '/topic/veryhot_post.gif" alt="" align="middle" /> ' . sprintf($txt['very_hot_topics'], $modSettings['hotTopicVeryPosts']) . '
		</td>
		<td class="smalltext" align="left" valign="top" style="padding-top: 1ex;">
			<img src="' . $settings['images_url'] . '/topic/normal_post_locked.gif" alt="" align="middle" /> ' . $txt['locked_topic'] . '<br />' . ($modSettings['enableStickyTopics'] == '1' ? '
			<img src="' . $settings['images_url'] . '/topic/normal_post_sticky.gif" alt="" align="middle" /> ' . $txt['sticky_topic'] . '<br />' : '') . ($modSettings['pollMode'] == '1' ? '
			<img src="' . $settings['images_url'] . '/topic/normal_poll.gif" alt="" align="middle" /> ' . $txt['poll'] : '') . '
		</td>';
	}

	echo '
		<td class="smalltext" align="right" valign="middle" id="message_index_jump_to">&nbsp;</td>
	</tr>
</table>
<script type="text/javascript"><!-- // --><![CDATA[
	if (\'XMLHttpRequest\' in window)
		aJumpTo[aJumpTo.length] = new JumpTo({
			sContainerId: "message_index_jump_to",
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
// ]]></script>';
}

function theme_show_buttons()
{
	global $context, $settings, $options, $txt, $scripturl;

	$buttonArray = array();

	// If they are logged in, and the mark read buttons are enabled..
	if ($context['user']['is_logged'] && $settings['show_mark_read'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=markasread;sa=board;board=' . $context['current_board'] . '.0;' . $context['session_var'] . '=' . $context['session_id'] . '">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/markread.gif" alt="' . $txt['mark_board_read'] . '" border="0" />' : $txt['mark_board_read']) . '</a>';

	// If the user has permission to show the notification button... ask them if they're sure, though.
	if ($context['can_mark_notify'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=notifyboard;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';board=' . $context['current_board'] . '.' . $context['start'] . ';' . $context['session_var'] . '=' . $context['session_id'] . '" onclick="return confirm(\'' . ($context['is_marked_notify'] ? $txt['notification_disable_board'] : $txt['notification_enable_board']) . '\');">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/' . ($context['is_marked_notify'] ? 'un' : '') . 'notify.gif" alt="' . $txt[$context['is_marked_notify'] ? 'unnotify' : 'notify'] . '" border="0" />' : $txt[$context['is_marked_notify'] ? 'unnotify' : 'notify']) . '</a>';

	// Are they allowed to post new topics?
	if ($context['can_post_new'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=post;board=' . $context['current_board'] . '.0">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/new_topic.gif" alt="' . $txt['start_new_topic'] . '" border="0" />' : $txt['start_new_topic']) . '</a>';

	// How about new polls, can the user post those?
	if ($context['can_post_poll'])
		$buttonArray[] = '<a href="' . $scripturl . '?action=post;board=' . $context['current_board'] . '.0;poll">' . ($settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/new_poll.gif" alt="' . $txt['new_poll'] . '" border="0" />' : $txt['new_poll']) . '</a>';

	return implode($context['menu_separator'], $buttonArray);
}

?>