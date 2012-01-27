<?php
/**
 * Wedge
 *
 * Displays the following lists: very recent posts, unread posts and unread replies (unread posts in topics you replied to before.)
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="recent" class="main_section">
		<we:cat>
			<img src="', $settings['images_url'], '/post/xx.gif">
			', $txt['recent_posts'], '
		</we:cat>
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';

	$remove_confirm = JavaScriptEscape($txt['remove_message_confirm']);

	foreach ($context['posts'] as $post)
	{
		echo '
			<div class="windowbg', $post['alternate'] == 0 ? '' : '2', ' core_posts wrc">
				<div class="counter">', $post['counter'], '</div>
				<div class="topic_details">
					<h5>', $post['board']['link'], ' / ', $post['link'], '</h5>
					<span class="smalltext">&#171;&nbsp;', $post['time'], ' ', $txt['by'], ' <strong>', $post['poster']['link'], '</strong>&nbsp;&#187;</span>
				</div>
				<div class="list_posts">', $post['message'], '</div>';

		if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
			echo '
				<div class="quickbuttons_wrap">
					<ul class="quickbuttons">';

		// If they *can* reply?
		if ($post['can_reply'])
			echo '
						<li><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], '" class="reply_button">', $txt['reply'], '</a></li>';

		// If they *can* quote?
		if ($post['can_quote'])
			echo '
						<li><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '" class="quote_button">', $txt['quote'], '</a></li>';

		// Can we request notification of topics?
		if ($post['can_mark_notify'])
			echo '
						<li><a href="', $scripturl, '?action=notify;topic=', $post['topic'], '.', $post['start'], '" class="notify_button">', $txt['notify'], '</a></li>';

		// How about... even... remove it entirely?!
		if ($post['can_delete'])
			echo '
						<li><a href="', $scripturl, '?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';recent;', $context['session_query'], '" class="remove_button" onclick="return confirm(', $remove_confirm, ');">', $txt['remove'], '</a></li>';

		if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
			echo '
					</ul>
				</div>';

		echo '
				<div class="clear"></div>
			</div>';

	}

	echo '
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>
	</div>';
}

function template_unread()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="recent">';

	$show_checkboxes = !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1;

	if ($show_checkboxes)
		echo '
		<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm" style="margin: 0">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="qaction" value="markread">
			<input type="hidden" name="redirect_url" value="action=unread', $context['querystring_board_limits'], '">';

	// Generate the button strip.
	$mark_read = array(
		'markread' => array('text' => !empty($context['no_board_limits']) ? 'mark_as_read' : 'mark_read_short', 'url' => $scripturl . '?action=markasread;sa=' . (!empty($context['no_board_limits']) ? 'all' : 'board' . $context['querystring_board_limits']) . ';' . $context['session_query']),
	);

	if ($show_checkboxes)
		$mark_read['markselectread'] = array('text' => 'quick_mod_markread', 'url' => 'javascript:document.quickModForm.submit();');

	if (!empty($context['topics']))
	{
		echo '
			<div class="pagesection">';

		if (!empty($mark_read))
			template_button_strip($mark_read);

		echo '
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
			</div>';

		echo '
			<div class="topic_table" id="unread">
				<table class="table_grid cs0">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th" style="width: 4%">&nbsp;</th>
							<th scope="col">
								<a href="', $scripturl, '?action=unread', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a>
							</th>
							<th scope="col" style="width: 14%" class="center">
								<a href="', $scripturl, '?action=unread', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] == 'replies' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] == 'replies' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a>
							</th>';

		// Show a "select all" box for quick moderation?
		if ($show_checkboxes)
			echo '
							<th scope="col" style="width: 22%">
								<a href="', $scripturl, '?action=unread', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a>
							</th>
							<th class="last_th">
								<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');">
							</th>';
		else
			echo '
							<th scope="col" class="smalltext last_th" style="width: 22%">
								<a href="', $scripturl, '?action=unread', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] == 'last_post' && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] == 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a>
							</th>';
		echo '
						</tr>
					</thead>
					<tbody>';

		foreach ($context['topics'] as $topic)
		{
			$color_class = '';
			// Is it a sticky, or locked topic? Or both?
			if ($topic['is_sticky'])
				$color_class .= ' sticky';
			if ($topic['is_locked'])
				$color_class .= ' locked';

			// Some columns require a different shade of the color class.
			$alternate_class = 'windowbg2' . $color_class;
			$color_class = 'windowbg' . $color_class;

			echo '
						<tr>
							<td class="', $color_class, ' icon">
								<img src="', $topic['first_post']['icon_url'], '">
							</td>
							<td class="subject ', $alternate_class, $topic['is_posted_in'] ? ' my' : '', '">
								<div>
									', $topic['is_sticky'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '">', $topic['new_link'], '</span>', $topic['is_sticky'] ? '</strong>' : '', '
									<a href="', $topic['new_href'], '"><div class="new_icon" title="', $txt['new'], '"></div></a> ', $context['nb_new'][$topic['id']], '
									<p>
										', $txt['started_by'], ' <strong>', $topic['first_post']['member']['link'], '</strong>
										', $txt['in'], ' <em>', $topic['board']['link'], '</em>
										<small id="pages', $topic['first_post']['id'], '">', $topic['pages'], '</small>
									</p>
								</div>
							</td>
							<td class="', $color_class, ' stats">
								', $topic['replies'], ' ', $txt['replies'], '
								<br>
								', $topic['views'], ' ', $txt['views'], '
							</td>
							<td class="', $alternate_class, ' lastpost">
								<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" class="right"></a>
								', $topic['last_post']['time'], '<br>
								', $txt['by'], ' ', $topic['last_post']['member']['link'], '
							</td>';

			if ($show_checkboxes)
				echo '
							<td class="windowbg2 middle center">
								<input type="checkbox" name="topics[]" value="', $topic['id'], '">
							</td>';
			echo '
						</tr>';
		}

		if (!empty($context['topics']))
			$mark_read['readall'] = array('text' => 'unread_topics', 'url' => $scripturl . '?action=unread' . $context['querystring_board_limits'], 'class' => 'active');
		else
			echo '
					<tr class="hide"><td></td></tr>';

		echo '
					</tbody>
				</table>
			</div>
			<div class="pagesection" id="readbuttons">';

		if (!empty($mark_read))
			template_button_strip($mark_read);

		echo '
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
			</div>';
	}
	else
		echo '
		<we:cat>
			<div class="centertext">
				', $txt['msg_alert_none'], '
			</div>
		</we:cat>';

	if ($show_checkboxes)
		echo '
		</form>';

	echo '
	</div>';
}

function template_replies()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="recent">';

	$show_checkboxes = !empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1;

	if ($show_checkboxes)
		echo '
		<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm" style="margin: 0">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="qaction" value="markread">
			<input type="hidden" name="redirect_url" value="action=unreadreplies', $context['querystring_board_limits'], '">';

	if (isset($context['topics_to_mark']))
	{
		// Generate the button strip.
		$mark_read = array(
			'markread' => array('text' => 'mark_as_read', 'url' => $scripturl . '?action=markasread;sa=unreadreplies;topics=' . $context['topics_to_mark'] . ';' . $context['session_query']),
		);

		if ($show_checkboxes)
			$mark_read['markselectread'] = array('text' => 'quick_mod_markread', 'url' => 'javascript:document.quickModForm.submit();');
	}

	if (!empty($context['topics']))
	{
		echo '
			<div class="pagesection">';

		if (!empty($mark_read))
			template_button_strip($mark_read);

		echo '
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
			</div>';

		echo '
			<div class="topic_table" id="unreadreplies">
				<table class="table_grid cs0">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th" style="width: 4%">&nbsp;</th>
							<th scope="col">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=subject', $context['sort_by'] === 'subject' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['subject'], $context['sort_by'] === 'subject' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a>
							</th>
							<th scope="col" style="width: 14%" class="center">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=replies', $context['sort_by'] === 'replies' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['replies'], $context['sort_by'] === 'replies' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a>
							</th>';

		// Show a "select all" box for quick moderation?
		if ($show_checkboxes)
				echo '
							<th scope="col" style="width: 22%">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] === 'last_post' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] === 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a>
							</th>
							<th class="last_th">
								<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');">
							</th>';
		else
			echo '
							<th scope="col" class="last_th" style="width: 22%">
								<a href="', $scripturl, '?action=unreadreplies', $context['querystring_board_limits'], ';sort=last_post', $context['sort_by'] === 'last_post' && $context['sort_direction'] === 'up' ? ';desc' : '', '">', $txt['last_post'], $context['sort_by'] === 'last_post' ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a>
							</th>';
		echo '
						</tr>
					</thead>
					<tbody>';

		foreach ($context['topics'] as $topic)
		{
			$color_class = '';
			// Is it a sticky, or locked topic? Or both?
			if ($topic['is_sticky'])
				$color_class .= ' sticky';
			if ($topic['is_locked'])
				$color_class .= ' locked';

			// Some columns require a different shade of the color class.
			$alternate_class = 'windowbg2' . $color_class;
			$color_class = 'windowbg' . $color_class;

			echo '
						<tr>
							<td class="', $color_class, ' icon">
								<img src="', $topic['first_post']['icon_url'], '">
							</td>
							<td class="subject ', $alternate_class, '">
								<div>
									', $topic['is_sticky'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '">', $topic['new_link'], '</span>', $topic['is_sticky'] ? '</strong>' : '', '
									<a href="', $topic['new_href'], '"><div class="new_icon" title="', $txt['new'], '"></div></a> ', $context['nb_new'][$topic['id']], '
									<p>
										', $txt['started_by'], ' <strong>', $topic['first_post']['member']['link'], '</strong>
										', $txt['in'], ' <em>', $topic['board']['link'], '</em>
										<small id="pages', $topic['first_post']['id'], '">', $topic['pages'], '</small>
									</p>
								</div>
							</td>
							<td class="', $color_class, ' stats">
								', $topic['replies'], ' ', $txt['replies'], '
								<br>
								', $topic['views'], ' ', $txt['views'], '
							</td>
							<td class="', $alternate_class, ' lastpost">
								<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '" class="right"></a>
								', $topic['last_post']['time'], '<br>
								', $txt['by'], ' ', $topic['last_post']['member']['link'], '
							</td>';

			if ($show_checkboxes)
				echo '
							<td class="windowbg2 middle center">
								<input type="checkbox" name="topics[]" value="', $topic['id'], '">
							</td>';
			echo '
						</tr>';
		}

		echo '
					</tbody>
				</table>
			</div>
			<div class="pagesection">', !empty($mark_read) ?
				template_button_strip($mark_read) : '', '
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
			</div>';
	}
	else
		echo '
			<we:cat>
				<div class="centertext">
					', $txt['msg_alert_none'], '
				</div>
			</we:cat>';

	if ($show_checkboxes)
		echo '
		</form>';

	echo '
	</div>';
}

?>