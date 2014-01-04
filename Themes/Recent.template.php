<?php
/**
 * Displays the following lists: very recent posts, unread posts and unread replies (unread posts in topics you replied to before.)
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

function template_main()
{
	global $context, $txt, $settings;

	echo '
	<div class="main_section">
		<we:cat>
			<img src="', ASSETS, '/post/xx.gif" class="middle">
			', $txt['recent_posts'], '
		</we:cat>
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';

	$remove_confirm = JavaScriptEscape($txt['remove_message_confirm']);

	foreach ($context['posts'] as $post)
	{
		echo '
		<div class="windowbg', $post['alternate'] == 0 ? '' : '2', ' wrc core_posts">
			<div class="counter">', $post['counter'], '</div>
			<div class="topic_details">
				<h5>', $post['board']['link'], ' / ', $post['link'], '</h5>
				<span class="smalltext">&#171;&nbsp;', $post['on_time'], ' ', $txt['by'], ' <strong>', $post['poster']['link'], '</strong>&nbsp;&#187;</span>
			</div>
			<div class="list_posts">', $post['message'], '</div>';

		if ($post['can_reply'] || $post['can_delete'] || (!empty($settings['likes_enabled']) && !empty($context['liked_posts'][$post['id']])))
		{
			echo '
			<div class="actionbar">';

			if ($post['can_reply'] || $post['can_delete'])
				echo '
				<ul class="actions">';

			// If they *can* reply?
			if ($post['can_reply'])
				echo '
					<li><a href="<URL>?action=post;topic=', $post['topic'], '.', $post['start'], '" class="reply_button">', $txt['reply'], '</a></li>';

			// If they *can* quote?
			if ($post['can_quote'])
				echo '
					<li><a href="<URL>?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '" class="quote_button">', $txt['quote'], '</a></li>';

			// How about... even... remove it entirely?!
			if ($post['can_delete'])
				echo '
					<li><a href="<URL>?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';recent;', $context['session_query'], '" class="remove_button" onclick="return ask(', $remove_confirm, ', e);">', $txt['remove'], '</a></li>';

			if ($post['can_reply'] || $post['can_delete'])
				echo '
				</ul>';

			if (!empty($settings['likes_enabled']) && !empty($context['liked_posts'][$post['id']]))
				template_show_likes($post['id'], false);

			echo '
			</div>';
		}

		echo '
		</div>';
	}

	echo '
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>
	</div>';
}

function template_unread($replies = false)
{
	global $context, $txt;

	// JavaScript for generating topic status icons.
	add_js_file('scripts/topic.js');

	echo '
	<we:cat>
		', $txt[$replies ? 'show_unread_replies' : 'show_unread'], '
	</we:cat>
	<form action="<URL>?action=quickmod" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		<input type="hidden" name="qaction" value="markread">
		<input type="hidden" name="redirect_url" value="action=unread', $context['querystring_board_limits'], '">';

	// Generate the button strip.
	$mark_read = array(
		'markread' => array('text' => !empty($context['no_board_limits']) ? 'mark_as_read' : 'mark_read_short', 'url' => '<URL>?action=markasread;sa=' . (!empty($context['no_board_limits']) ? 'all' : 'board' . $context['querystring_board_limits']) . ';' . $context['session_query']),
	);

	$mark_read['markselectread'] = array('text' => 'quick_mod_markread', 'url' => 'javascript:document.quickModForm.submit();');

	if (!empty($context['topics']))
	{
		echo '
		<div class="pagesection">',
			template_button_strip($mark_read), '
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';

		echo '
		<div class="topic_table" id="unread">
			<table class="table_grid cs0">
				<thead>
					<tr class="catbg">
						<th style="width: 4%">&nbsp;</th>
						<th>
							<a href="<URL>?action=unread', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <span class="sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
						</th>
						<th style="width: 14%" class="center">
							<a href="<URL>?action=unread', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <span class="sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
						</th>';

		// Show a "select all" box for quick moderation?
		echo '
						<th style="width: 22%">
							<a href="<URL>?action=unread', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <span class="sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
						</th>
						<th>
							<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');">
						</th>';

		echo '
					</tr>
				</thead>
				<tbody>';

		foreach ($context['topics'] as $topic)
		{
			$color_class = '';
			// Is it a pinned, or locked topic? Or both?
			if ($topic['is_pinned'])
				$color_class .= ' pinned';
			if ($topic['is_locked'])
				$color_class .= ' locked';
			if ($topic['is_poll'])
				$color_class .= ' poll';
			if ($topic['is_posted_in'])
				$color_class .= ' my';

			// Some columns require a different shade of the color class.
			$alternate_class = 'windowbg2' . $color_class;
			$color_class = 'windowbg' . $color_class;

			echo '
					<tr>
						<td class="icon ', $color_class, '">
							<img src="', $topic['first_post']['icon_url'], '">
						</td>
						<td class="subject ', $alternate_class, '">
							<div>
								', $topic['is_pinned'] ? '<strong>' : '', '<span id="msg_', $topic['first_post']['id'], '">', $topic['new_link'], '</span>', $topic['is_pinned'] ? '</strong>' : '', '
								<a href="', $topic['new_href'], '" class="note" title="', $txt['new_posts'], '">', $context['nb_new'][$topic['id']], '</a>
								<p>
									', $txt['started_by'], ' <strong>', $topic['first_post']['member']['link'], '</strong>
									', $txt['in'], ' <em>', $topic['board']['link'], '</em>
									<small id="pages', $topic['first_post']['id'], '">', $topic['pages'], '</small>
								</p>
							</div>
						</td>
						<td class="stats ', $color_class, '">
							', $topic['replies'], ' ', $txt['replies'], '
							<br>
							', $topic['views'], ' ', $txt['views'], '
						</td>
						<td class="lastpost ', $alternate_class, '">
							<p><a href="', $topic['last_post']['href'], '"><img src="', ASSETS, '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" class="right"></a>
							', strtr($txt['last_post_time_author'], array(
								'{time}' => $topic['last_post']['time'],
								'{author}' => $topic['last_post']['member']['link']
							)), '</p>
						</td>';

			echo '
						<td class="', $color_class, ' middle center">
							<input type="checkbox" name="topics[]" value="', $topic['id'], '">
						</td>';
			echo '
					</tr>';
		}

		if (empty($context['topics']))
			echo '
					<tr class="hide"><td></td></tr>';

		echo '
				</tbody>
			</table>
		</div>
		<div class="pagesection">',
			template_button_strip($mark_read), '
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';
	}
	else
		echo '
		<div class="center padding">
			', $txt['msg_alert_none'], '
		</div>';

	echo '
	</form>';
}
