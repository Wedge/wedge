<?php
/**
 * Displays the list of topics in a forum board, or snippets of topics in a list for blog boards.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

function template_main_board()
{
	global $context, $settings, $txt;

	if (!empty($settings['show_board_desc']) && $context['description'] != '')
		echo '
	<p class="description_board">', $context['description'], '</p>';

	template_messageindex_childboards();

	if (!$context['no_topic_listing'])
	{
		echo '
	<div class="pagesection">', empty($context['button_list']) ? '' : template_button_strip($context['button_list']), '
		<nav>', $txt['pages'], ': ', $context['page_index'], $context['page_separator'], '<a href="#" class="updown">', $txt['go_down'], '</a></nav>
	</div>';

		// If Quick Moderation is enabled, start the form.
		if (!empty($context['quick_moderation']))
			echo '
	<form action="<URL>?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm">';

		echo '
	<div class="wide"><div class="topic_table" id="messageindex">
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
			echo '
				<tr class="windowbg2">
					<td colspan="', !empty($context['quick_moderation']) ? '5' : '4', '">
						<span class="alert">!</span> ', $context['unapproved_posts_message'], '
					</td>
				</tr>';

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

			// Are there unread messages in this topic?
			if (isset($context['nb_new'][$topic['id']]))
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
							<a href="', $topic['last_post']['href'], '"><img src="', ASSETS, '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '"></a>
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
	</div></div>';

		// Finish off the form - again.
		if (!empty($context['quick_moderation']))
			echo '
	<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
	</form>';

		echo '
	<div class="pagesection">', empty($context['button_list']) ? '' : template_button_strip($context['button_list']), '
		<nav>', $txt['pages'], ': ', $context['page_index'], $context['page_separator'], '<a href="#" class="updown">', $txt['go_up'], '</a></nav>
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	$context['bottom_linktree'] = true;

	// JavaScript for inline editing.
	add_js_file('topic.js');

	add_js('
	// Hide certain bits during topic edit.
	hide_prefixes.push("pages", "newicon");');
}

function template_main_blog()
{
	global $context, $settings, $txt, $board_info;

	if (!empty($settings['show_board_desc']) && $context['description'] != '')
		echo '
	<p class="description_board">', $context['description'], '</p>';

	template_messageindex_childboards();

	if (!$context['no_topic_listing'])
	{
		echo '
	<we:cat>
		', $board_info['name'], '
	</we:cat>
	<div class="pagesection">', empty($context['button_list']) ? '' : template_button_strip($context['button_list']), '
		<nav>', $txt['pages'], ': ', $context['page_index'], $context['page_separator'], '<a href="#" class="updown">', $txt['go_down'], '</a></nav>
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
					<th><strong>', $txt['msg_alert_none'], '</strong></th>
				</tr>
			</thead>';

		echo '
			<tbody>';

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
			echo '
				<tr class="windowbg2">
					<td>
						<span class="alert">!</span> ', $context['unapproved_posts_message'], '
					</td>
				</tr>';

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

			// Show the quick moderation options?
			if (!empty($context['quick_moderation']))
				echo '
							<input type="checkbox" name="topics[]" class="floatright" value="', $topic['id'], '">';

			echo '
							<p>', $txt['posted_by'], ' ', $topic['first_post']['member']['link'], ', ', $topic['first_post']['on_time'], '
							&nbsp; (', number_context('num_views', $topic['views']), ')
								<small id="pages', $topic['first_post']['id'], '">', $topic['pages'], '</small>';

			// Are there unread messages in this topic?
			if (isset($context['nb_new'][$topic['id']]))
					echo '
							<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '" class="note">', $context['nb_new'][$topic['id']], '</a>';

			echo '
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
					echo '
							<a href="<URL>?action=post;topic=' . $topic['id'] . '.0;last=' . $topic['last_post']['id'], '">', $txt['reply'], '</a>';
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
	</div>';

		// Finish off the form - again.
		if (!empty($context['quick_moderation']))
			echo '
	<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
	</form>';

		echo '
	<div class="pagesection">', empty($context['button_list']) ? '' : template_button_strip($context['button_list']), '
		<nav>', $txt['pages'], ': ', $context['page_index'], $context['page_separator'], '<a href="#" class="updown">', $txt['go_up'], '</a></nav>
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	$context['bottom_linktree'] = true;

	// JavaScript for inline editing.
	add_js_file('topic.js');

	add_js('
	// Hide certain bits during topic edit.
	hide_prefixes.push("pages", "newicon");');
}

function template_messageindex_childboards()
{
	global $context, $options, $settings, $txt;

	if (empty($context['boards']) || (empty($options['show_children']) && $context['start'] != 0))
		return;

	$alt = false;
	$nb_new = get_unread_numbers($context['board_ids'], false, true);

	echo '
	<div class="childboards" id="board_', $context['current_board'], '_childboards">
		<we:cat>
			', $txt['sub_boards'], '
		</we:cat>
		<div class="wide">
			<table class="table_list board_list">
				<tbody id="board_', $context['current_board'], '_children">';

	foreach ($context['boards'] as $board)
	{
		$alt = !$alt;
		$nb = !empty($nb_new[$board['id']]) ? $nb_new[$board['id']] : '';
		$boardstate = 'boardstate' . (SKIN_MOBILE ? ' mobile' : '');

		echo '
				<tr id="board_', $board['id'], '" class="windowbg', $alt ? '2' : '', '">
					<td class="icon">
						<a href="<URL>?action=unread;board=' . $board['id'] . ';children" title="' . $txt['show_unread'] . '">';

		// If this board is told to have a custom icon, use it.
		if (!empty($board['custom_class']))
			echo '<div class="', $boardstate, ' ', $board['custom_class'], '"', !empty($board['custom_title']) ? ' title="' . $board['custom_title'] . '"' : '', '>';
		// Is it a redirection board?
		elseif ($board['is_redirect'])
			echo '<div class="', $boardstate, ' link" title="', $txt['redirect_board'], '">';
		// Show an indicator of the board's recent activity.
		else
			echo '<div class="', $boardstate, empty($board['new']) ? '' : ' unread', '">';

		echo '</div></a>
					</td>
					<td class="info">
						', $settings['display_flags'] == 'all' || ($settings['display_flags'] == 'specified' && !empty($board['language'])) ? '<img src="' . LANGUAGES . $context['languages'][$board['language']]['folder'] . '/Flag.' . $board['language'] . '.gif">&nbsp; ': '', '<a', $board['redirect_newtab'] ? ' target="_blank"' : '', ' class="subject" href="', $board['href'], '" id="b', $board['id'], '">', $board['name'], '</a>';

		// Has it outstanding posts for approval?
		if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
			echo '
						<a href="<URL>?action=moderate;area=postmod;sa=', $board['unapproved_topics'] > 0 ? 'topics' : 'posts', ';brd=', $board['id'], ';', $context['session_query'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

		if ($nb && empty($board['redirect_newtab']))
			echo '
						<a href="<URL>?action=unread;board=', $board['id'], '.0;children', '" title="', $txt['show_unread'], '" class="note">', $nb, '</a>';

		if (!empty($board['description']))
			echo '
						<p>', $board['description'], '</p>';

		// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
		if (!empty($board['moderators']))
			echo '
						<p class="moderators">', count($board['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

		// Show the "Child Boards: " area. (There's a link_children but we're going to bold the new ones...)
		if (!empty($board['children']))
		{
			// Sort the links into an array with new boards bold so it can be imploded.
			$children = array();
			/* Each child in each board's children has:
					id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
			foreach ($board['children'] as $child)
			{
				if ($child['is_redirect'])
					$child['link'] = '<a href="' . $child['href'] . '" title="' . number_context('num_redirects', $child['posts']) . '">' . $child['name'] . '</a>';
				else
				{
					$nb = !empty($nb_new[$child['id']]) ? $nb_new[$child['id']] : 0;
					$child['link'] = '<a href="' . $child['href'] . '">' . $child['name'] . '</a>' . ($nb ? ' <a href="<URL>?action=unread;board=' . $child['id'] . ';children" title="' . $txt['show_unread'] . '" class="notevoid">' . $nb . '</a>' : '');
				}

				// Has it posts awaiting approval?
				if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics']))
					$child['link'] .= ' <a href="<URL>?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_query'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

				$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
			}
			echo '
						<div class="children windowbg', $alt ? '2' : '', '" id="board_', $board['id'], '_children">
							<p><strong>', $txt['sub_boards'], '</strong>: ', implode(', ', $children), '</p>
						</div>';
		}

		echo '
					</td>';

		// Show some basic information about the number of posts, etc.
		if (!SKIN_MOBILE)
			echo '
					<td class="stats">
						<div>', number_context($board['is_redirect'] ? 'num_redirects' : 'num_posts', $board['posts']), '</div>', $board['is_redirect'] ? '' : '
						<div>' . number_context('num_topics', $board['topics']) . '</div>', '
					</td>';

		echo '
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
	}

	echo '
			</table>
		</div>
	</div>';
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
		return $caption; // !!! If we want the direction indicator: . ($context['sort_by'] == $sort ? ' <span class="sort_' . $context['sort_direction'] . '></span>' : '');
	else
		return '<a href="<URL>?board=' . $context['current_board'] . '.' . $context['start'] . ';sort=' . $sort . ($context['sort_by'] == $sort && $context['sort_direction'] == 'up' ? ';desc' : '') . '">' . $caption . ($context['sort_by'] == $sort ? ' <span class="sort_' . $context['sort_direction'] . '"></span>' : '') . '</a>';
}

function template_messageindex_whoviewing()
{
	global $txt, $context, $settings;

	echo '
	<section>
		<we:title>
			<img src="', ASSETS, '/icons/online.gif" alt="', $txt['online_users'], '">', $txt['who_title'], '
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
	global $txt, $settings;

	echo '
	<section>
		<we:title>
			<img src="', ASSETS, '/icons/field_invalid.gif">
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
	global $context, $settings, $txt, $board_info;

	if (empty($settings['show_stats_index']))
		return;

	$type = $board_info['type'] == 'forum' ? 'forum' : 'blog';

	echo '
	<section>
		<we:title>
			<a href="<URL>?board=', $context['current_board'], ';action=stats"><img src="', ASSETS, '/icons/info.gif" alt="', $txt[$type . '_stats'], '"></a>
			', $txt[$type . '_stats'], '
		</we:title>
		<p>
			', $board_info['num_posts'], ' ', $txt['posts_made'], ' ', $txt['in'], ' ', number_context('num_topics', $board_info['total_topics']), '<br>
		</p>
	</section>';
}

function template_messageindex_quickmod_selection()
{
	global $context, $txt, $board_info;

	if (!empty($context['quick_moderation']))
	{
		echo '
				<tr class="titlebg">
					<td', $board_info['type'] == 'forum' ? ' colspan="5"' : '', ' class="round-bottom right">
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
