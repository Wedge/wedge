<?php
/**
 * Displays the list of topics in a forum board, or snippets of topics in a list for blog boards.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

function template_main_board()
{
	global $context, $theme, $options, $txt;

	echo '
	<a id="top"></a>';

	template_messageindex_childboards();

	if (!empty($options['show_board_desc']) && $context['description'] != '')
		echo '
	<p class="description_board">', $context['description'], '</p>';

	if (!$context['no_topic_listing'])
	{
		echo '
	<div class="pagesection">
		<nav>', $txt['pages'], ': ', $context['page_index'], $context['menu_separator'], '&nbsp;&nbsp;<a href="#bot"><strong>', $txt['go_down'], '</strong></a></nav>',
		empty($context['button_list']) ? '' : template_button_strip($context['button_list']), '
	</div>';

		// If Quick Moderation is enabled, start the form.
		if (!empty($context['quick_moderation']))
			echo '
	<form action="<URL>?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm">';

		echo '
	<div class="topic_table" id="messageindex">
		<table class="table_grid cs0">
			<thead>
				<tr class="catbg">';

		// Are there actually any topics to show?
		if (!empty($context['topics']))
		{
			echo '
					<th style="width: 4%">&nbsp;</th>
					<th class="left', SKIN_MOBILE ? ' w100' : '', '">', template_messageindex_sortlink('subject', $txt['subject']), ' / ', template_messageindex_sortlink('starter', $txt['started_by']), '</th>';

			if (!SKIN_MOBILE)
			{
				echo '
					<th style="width: 14%">', template_messageindex_sortlink('replies', $txt['replies']), ' / ', template_messageindex_sortlink('views', $txt['views']), '</th>';

				// Show a "select all" box for quick moderation?
				if (empty($context['quick_moderation']))
					echo '
					<th class="left" style="width: 22%">', template_messageindex_sortlink('last_post', $txt['last_post']), '</th>';
				else
					echo '
					<th class="left" style="width: 22%">', template_messageindex_sortlink('last_post', $txt['last_post']), '</th>';
			}

			// Show a "select all" box for quick moderation?
			if (!empty($context['quick_moderation']))
				echo '
					<th style="width: 24px"><input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');"></th>';

			// If it's on in "image" mode, don't show anything but the column.
			elseif (!empty($context['quick_moderation']))
				echo '
					<th style="width: 4%">&nbsp;</th>';
		}
		// No topics.... just say, "sorry bub".
		else
			echo '
					<th style="width: 8%">&nbsp;</th>
					<th colspan="3"><strong>', $txt['msg_alert_none'], '</strong></th>
					<th style="width: 8%">&nbsp;</th>';

		echo '
				</tr>
			</thead>
			<tbody>';

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
		{
			echo '
				<tr class="windowbg2">
					<td colspan="', !empty($context['quick_moderation']) ? '5' : '4', '">
						<span class="alert">!</span> ', $context['unapproved_posts_message'], '
					</td>
				</tr>';
		}

		foreach ($context['topics'] as $topic)
		{
			// Some columns require a different shade of the color class.
			$alternate_class = 'windowbg2' . $topic['style'];
			$color_class = 'windowbg' . $topic['style'];

			echo '
				<tr>
					<td class="icon ', $color_class, '">
						<img src="', $topic['first_post']['icon_url'], '">
					</td>
					<td class="subject ', $alternate_class, '">
						<div', (!empty($topic['quick_mod']['modify']) ? ' id="topic_' . $topic['id'] . '" ondblclick="modify_topic(' . $topic['id'] . ', ' . $topic['first_post']['id'] . ');"' : ''), '>
							<span id="msg_' . $topic['first_post']['id'] . '">', $topic['new'] && we::$is_member ? $topic['new_link'] : $topic['first_post']['link'],
							!$context['can_approve_posts'] && !$topic['approved'] ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : '', '</span>';

			// Is this topic new? (assuming they are logged in!)
			if ($topic['new'] && we::$is_member)
					echo '
							<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '" class="note">', $context['nb_new'][$topic['id']], '</a>';

			echo '
							<p>', $txt['started_by'], ' ', $topic['first_post']['member']['link'], '
								<small id="pages' . $topic['first_post']['id'] . '">', $topic['pages'], '</small>
							</p>
						</div>';

			if (!SKIN_MOBILE)
				echo '
					</td>
					<td class="stats ', $color_class, '">
						', number_context('num_replies', $topic['replies']), '
						<br>', number_context('num_views', $topic['views']), '
					</td>
					<td class="lastpost ', $alternate_class, '">';

			echo '
						<p>
							<a href="', $topic['last_post']['href'], '"><img src="', $theme['images_url'], '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '"></a>
							', strtr($txt['last_post_time_author'], array(
								'{time}' => $topic['last_post']['on_time'],
								'{author}' => $topic['last_post']['member']['link']
							)), '
						</p>
					</td>';

			// Show the quick moderation options?
			if (!empty($context['quick_moderation']))
			{
				echo '
					<td class="center moderation ', $color_class, '">
						<input type="checkbox" name="topics[]" value="', $topic['id'], '">
					</td>';
			}
			echo '
				</tr>';
		}

		template_messageindex_quickmod_selection();

		echo '
			</tbody>
		</table>
	</div>
	<a id="bot"></a>';

		// Finish off the form - again.
		if (!empty($context['quick_moderation']))
			echo '
	<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
	</form>';

		echo '
	<div class="pagesection">', empty($context['button_list']) ? '' :
		template_button_strip($context['button_list']), '
		<nav>', $txt['pages'], ': ', $context['page_index'], $context['menu_separator'], '&nbsp;&nbsp;<a href="#top"><strong>', $txt['go_up'], '</strong></a></nav>
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	$context['bottom_linktree'] = true;

	// JavaScript for inline editing.
	add_js_file('scripts/topic.js');

	add_js('
	// Hide certain bits during topic edit.
	hide_prefixes.push("pages", "newicon");');
}

function template_main_blog()
{
	global $context, $options, $txt, $board_info;

	echo '
	<a id="top"></a>';

	template_messageindex_childboards();

	if (!empty($options['show_board_desc']) && $context['description'] != '')
		echo '
	<p class="description_board">', $context['description'], '</p>';

	if (!$context['no_topic_listing'])
	{
		echo '
	<we:cat>
		', $board_info['name'], '
	</we:cat>
	<div class="pagesection">
		<nav>', $txt['pages'], ': ', $context['page_index'], $context['menu_separator'], '&nbsp;&nbsp;<a href="#bot"><strong>', $txt['go_down'], '</strong></a></nav>',
		empty($context['button_list']) ? '' : template_button_strip($context['button_list']), '
	</div>';

		// If Quick Moderation is enabled, start the form.
		if (!empty($context['quick_moderation']))
			echo '
	<form action="<URL>?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm">';

		echo '
	<div class="topic_table" id="messageindex">
		<table class="table_grid cs0">';

		// Are there actually any topics to show?
		if (empty($context['topics']))
			echo '
			<thead>
				<tr class="catbg">
					<th style="width: 8%">&nbsp;</th>
					<th colspan="3"><strong>', $txt['msg_alert_none'], '</strong></th>
					<th style="width: 8%">&nbsp;</th>
				</tr>
			</thead>';

		echo '
			<tbody>';

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
		{
			echo '
				<tr class="windowbg2">
					<td colspan="', !empty($context['quick_moderation']) ? '3' : '2', '">
						<span class="alert">!</span> ', $context['unapproved_posts_message'], '
					</td>
				</tr>';
		}

		$use_bg2 = true;
		foreach ($context['topics'] as $topic)
		{
			$use_bg2 = !$use_bg2;

			echo '
				<tr>
					<td class="subject ', $use_bg2 ? 'windowbg2' : 'windowbg', $topic['style'], '">
						<div', (!empty($topic['quick_mod']['modify']) ? ' id="topic_' . $topic['first_post']['id'] . '" ondblclick="modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : ''), '>
							', $topic['is_pinned'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '" class="blog title">', '<img src="', $topic['first_post']['icon_url'], '" class="middle">&nbsp;',
							$topic['new'] && we::$is_member ? $topic['new_link'] : $topic['first_post']['link'],
							!$context['can_approve_posts'] && !$topic['approved'] ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : '',
							'</span>', $topic['is_pinned'] ? '</strong>' : '';

			// Is this topic new? (assuming they are logged in!)
			if ($topic['new'] && we::$is_member)
					echo '
							<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '" class="note">', $context['nb_new'][$topic['id']], '</a>';

			// Show the quick moderation options?
			if (!empty($context['quick_moderation']))
				echo '
						<input type="checkbox" name="topics[]" class="floatright" value="', $topic['id'], '">';

			echo '
							<p>', $txt['posted_by'], ' ', $topic['first_post']['member']['link'], ', ', $topic['first_post']['on_time'], '
							&nbsp; (', number_context('num_views', $topic['views']), ')
								<small id="pages', $topic['first_post']['id'], '">', $topic['pages'], '</small>
							</p>
						</div>
						<div class="padding">
							', $topic['first_post']['preview'];

			if (!empty($topic['replies']) || $topic['can_reply'])
			{
				echo '
							<br><br>';

				if (!empty($topic['replies']))
					echo '
							<a href="', $topic['new'] && we::$is_member ? $topic['new_href'] : $topic['first_post']['href'], '">', number_context('num_replies', $topic['replies']), '</a>', $topic['can_reply'] ? ' | ' : '';

				if ($topic['can_reply'])
				{
					// If quick reply is open, point directly to it, otherwise use the regular reply page
					if (empty($options['display_quick_reply']) || $options['display_quick_reply'] != 2)
						$reply_url = '<URL>?action=post;topic=' . $topic['id'] . '.0;last=' . $topic['last_post']['id'];
					else
						$reply_url = substr($topic['last_post']['href'], 0, strpos($topic['last_post']['href'], '#')) . '#quickreply';

					echo '
							<a href="', $reply_url, '">', $txt['reply'], '</a>';
				}
			}

			echo '
						</div>
					</td>
				</tr>';
		}

		template_messageindex_quickmod_selection();

		echo '
			</tbody>
		</table>
	</div>
	<a id="bot"></a>';

		// Finish off the form - again.
		if (!empty($context['quick_moderation']))
			echo '
	<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
	</form>';

		echo '
	<div class="pagesection">', empty($context['button_list']) ? '' :
		template_button_strip($context['button_list']), '
		<nav>', $txt['pages'], ': ', $context['page_index'], $context['menu_separator'], '&nbsp;&nbsp;<a href="#top"><strong>', $txt['go_up'], '</strong></a></nav>
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	$context['bottom_linktree'] = true;

	// JavaScript for inline editing.
	add_js_file('scripts/topic.js');

	add_js('
	// Hide certain bits during topic edit.
	hide_prefixes.push("pages", "newicon");');
}

function template_messageindex_childboards()
{
	global $context, $theme, $options, $settings, $txt;

	if (!empty($context['boards']) && (!empty($options['show_children']) || $context['start'] == 0))
	{
		echo '
	<div class="childboards" id="board_', $context['current_board'], '_childboards">
		<we:cat>
			', $txt['sub_boards'], '
		</we:cat>
		<div class="table_frame">
			<table id="board_list" class="table_list">
				<tbody id="board_', $context['current_board'], '_children">';

		foreach ($context['boards'] as $board)
		{
			echo '
				<tr id="board_', $board['id'], '" class="windowbg2">
					<td class="icon windowbg"', !empty($board['children']) ? ' rowspan="2"' : '', '>
						<a', $board['redirect_newtab'] ? ' target="_blank"' : '', ' href="', ($board['is_redirect'] || we::$is_guest ? $board['href'] : '<URL>?action=unread;board=' . $board['id'] . '.0;children'), '">';

			// If this board is told to have a custom icon, use it.
			if (!empty($board['custom_class']))
				echo '
							<div class="boardstatus ', $board['custom_class'], '"', !empty($board['custom_title']) ? ' title="' . $board['custom_title'] . '"' : '', '></div>';
			// If the board has new posts, show an indicator.
			if ($board['new'])
				echo '
							<div class="boardstate_on" title="', $txt['new_posts'], '"></div>';
			// Is it a redirection board?
			elseif ($board['is_redirect'])
				echo '
							<div class="boardstate_redirect" title="', $txt['redirect_board'], '"></div>';
			// No new posts at all! The agony!!
			else
				echo '
							<div class="boardstate_off" title="', $txt['old_posts'], '"></div>';

			echo '
						</a>
					</td>
					<td class="info">
						', $settings['display_flags'] == 'all' || ($settings['display_flags'] == 'specified' && !empty($board['language'])) ? '<img src="' . $theme['default_theme_url'] . '/languages/Flag.' . (empty($board['language']) ? $settings['language'] : $board['language']) . '.png"> ': '', '<a', $board['redirect_newtab'] ? ' target="_blank"' : '', ' class="subject" href="', $board['href'], '" id="b', $board['id'], '">', $board['name'], '</a>';

			// Has it outstanding posts for approval?
			if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
				echo '
						<a href="<URL>?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_query'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

			echo '

						<p>', $board['description'], '</p>';

			// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
			if (!empty($board['moderators']))
				echo '
						<p class="moderators">', count($board['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

			// Show some basic information about the number of posts, etc.
			if (!SKIN_MOBILE)
				echo '
					</td>
					<td class="stats windowbg">
						<p>', number_context($board['is_redirect'] ? 'num_redirects' : 'num_posts', $board['posts']), ' <br>
						', $board['is_redirect'] ? '' : number_context('num_topics', $board['topics']), '
						</p>
					</td>
					<td class="lastpost">';

			/* The board's and children's 'last_post's have:
			time, timestamp (a number that represents the time), id (of the post), topic (topic id),
			link, href, subject, start (where they should go for the first unread post),
			and member (which has id, name, link, href, username in it.) */
			if (!empty($board['last_post']['offlimits']))
				echo '
						<p>', $board['last_post']['offlimits'], '</p>';
			elseif (!empty($board['last_post']['id']))
				echo '
						<p>
							', strtr($txt['last_post_author_link_time'], array(
								'{author}' => $board['last_post']['member']['link'],
								'{link}' => $board['last_post']['link'],
								'{time}' => $board['last_post']['on_time'])
							), '
						</p>';

			echo '
					</td>
				</tr>';

			// Show the "Child Boards: " area. (There's a link_children but we're going to bold the new ones...)
			if (!empty($board['children']))
			{
				// Sort the links into an array with new boards bold so it can be imploded.
				$children = array();
				/* Each child in each board's children has:
						id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
				foreach ($board['children'] as $child)
				{
					if (!$child['is_redirect'])
					{
						$child_title = ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . number_context('num_topics', $child['topics']) . ', ' . number_context('num_posts', $child['posts']) . ')';
						$child['link'] = '<a href="' . $child['href'] . '"' . ($child['new'] ? ' class="new_posts"' : '') . ' title="' . $child_title . '">' . $child['name'] . '</a>' . ($child['new'] ? ' <a href="<URL>?action=unread;board=' . $child['id'] . '" title="' . $child_title . '" class="note new_posts">' . $txt['new'] . '</a>' : '');
					}
					else
						$child['link'] = '<a href="' . $child['href'] . '" title="' . number_context('num_redirects', $child['posts']) . '">' . $child['name'] . '</a>';

					// Has it posts awaiting approval?
					if ($child['can_approve_posts'] && ($child['unapproved_posts'] | $child['unapproved_topics']))
						$child['link'] .= ' <a href="<URL>?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_query'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

					$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
				}
				echo '
				<tr id="board_', $board['id'], '_children"><td colspan="3" class="children windowbg"><strong>', $txt['sub_boards'], '</strong>: ', implode(', ', $children), '</td></tr>';
			}
		}
		echo '
				</tbody>
			</table>
		</div>
	</div>';
	}
}

function template_messageindex_draft()
{
	global $txt;

	echo '
	<div class="windowbg" id="profile_success">
		', str_replace('{draft_link}', '<URL>?action=profile;area=showdrafts', $txt['draft_saved']), '
	</div>';
}

function template_messageindex_sortlink($sort, $caption)
{
	global $context;

	if (empty($context['can_reorder']))
		echo $caption; // !!! If we want the direction indicator: , $context['sort_by'] == $sort ? ' <span class="sort_' . $context['sort_direction'] . '></span>' : '';
	else
		echo '<a href="<URL>?board=', $context['current_board'], '.', $context['start'], ';sort=', $sort, $context['sort_by'] == $sort && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $caption, $context['sort_by'] == $sort ? ' <span class="sort_' . $context['sort_direction'] . '"></span>' : '', '</a>';
}

function template_messageindex_whoviewing()
{
	global $txt, $context, $theme, $settings;

	echo '
	<section>
		<we:title>
			<img src="', $theme['images_url'], '/icons/online.gif" alt="', $txt['online_users'], '">', $txt['who_title'], '
		</we:title>
		<p class="onlineinfo">';

	if ($settings['display_who_viewing'] == 1)
		echo count($context['view_members']), ' ', count($context['view_members']) === 1 ? $txt['who_member'] : $txt['members'];
	else
		echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . (empty($context['view_num_hidden']) || $context['can_moderate_members'] || $context['can_moderate_board'] ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

	echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_board'], '
		</p>
	</section>';
}

function template_messageindex_legend()
{
	global $theme, $txt, $settings;

	echo '
	<section>
		<we:title>
			<img src="', $theme['images_url'], '/icons/field_invalid.gif">
			', $txt['legend'], '
		</we:title>
		<p class="legend">
			<span class="icon_locked"></span> ', $txt['locked_topic'], '<br>
			<span class="icon_pinned"></span> ', $txt['pinned_topic'], '<br>
			<span class="icon_poll"></span> ' . $txt['poll'], '<br>', !empty($settings['enableParticipation']) && we::$is_member ? '
			<span class="icon_my"></span> ' . $txt['participation_caption'] : '', '
		</p>
	</section>';
}

// Show statistical style information...
// !!! Should we show this only in MessageIndex, or in index and show
// !!! it based on !empty($context['current_board']) or something?
function template_messageindex_statistics()
{
	global $context, $theme, $txt, $board_info;

	if (!$theme['show_stats_index'])
		return;

	$type = $board_info['type'] == 'board' ? 'board' : 'blog';

	echo '
	<section>
		<we:title>
			<a href="<URL>?board=', $context['current_board'], ';action=stats"><img src="', $theme['images_url'], '/icons/info.gif" alt="', $txt[$type . '_stats'], '"></a>
			', $txt[$type . '_stats'], '
		</we:title>
		<p>
			', $board_info['num_posts'], ' ', $txt['posts_made'], ' ', $txt['in'], ' ', number_context('num_topics', $board_info['total_topics']), '<br>
		</p>
	</section>';
}

function template_messageindex_quickmod_selection()
{
	global $context, $txt;

	if (!empty($context['quick_moderation']))
	{
		echo '
				<tr class="titlebg">
					<td colspan="5" class="round-bottom right">
						<label><input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');"> ', $txt['check_all'], '</label> &nbsp;
						<select class="qaction fixed" name="qaction"', $context['can_move'] ? ' onchange="$(\'#sbmoveItTo\').toggleClass(\'hide\', $(this).val() != \'move\');"' : '', '>
							<option data-hide>--- ', $txt['moderate'], ' ---</option>';
		foreach ($context['quick_moderation'] as $qmod_id => $qmod_txt)
			echo '
							<option value="', $qmod_id, '">', $qmod_txt, '</option>';

		echo '
						</select>';

		// Show a list of boards they can move the topic to.
		if ($context['can_move'])
		{
			echo '
						<select class="qaction hide" id="moveItTo" name="move_to">';

			foreach ($context['move_to_boards'] as $category)
			{
				echo '
							<optgroup label="', $category['name'], '">';
				foreach ($category['boards'] as $board)
					echo '
								<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '</option>';
				echo '
							</optgroup>';
			}
			echo '
						</select>';
		}

		echo '
						<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return $(\'select[name=qaction]\').val() != \'\' && ask(we_confirm, e);" class="qaction">
					</td>
				</tr>';
	}
}

function template_messageindex_staff()
{
	global $context, $txt;

	echo '
	<section>
		<we:title>
			', count($context['link_moderators']) == 1 ? $txt['moderator'] : $txt['moderators'], '
		</we:title>
		<p>
			', implode('<br>', $context['link_moderators']), '
		</p>
	</section>';
}
