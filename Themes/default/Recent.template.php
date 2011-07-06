<?php
/**
 * Wedge
 *
 * Displays the following lists: very recent posts, unread posts and unread replies (unread posts in topics you replied to before.)
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
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
			<span>', $txt['pages'], ': ', $context['page_index'], '</span>
		</div>';

	$remove_confirm = JavaScriptEscape($txt['remove_message_confirm']);

	foreach ($context['posts'] as $post)
	{
		echo '
			<div class="', $post['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' core_posts wrc">
				<div class="counter">', $post['counter'], '</div>
				<div class="topic_details">
					<h5>', $post['board']['link'], ' / ', $post['link'], '</h5>
					<span class="smalltext">&#171;&nbsp;', $txt['last_post'], ': ', $post['time'], ' ', $txt['by'], ' <strong>', $post['poster']['link'], '</strong>&nbsp;&#187;</span>
				</div>
				<div class="list_posts">', $post['message'], '</div>';

		if ($post['can_reply'] || $post['can_mark_notify'] || $post['can_delete'])
			echo '
				<div class="quickbuttons_wrap">
					<ul class="reset smalltext quickbuttons">';

		// If they *can* reply?
		if ($post['can_reply'])
			echo '
						<li class="reply_button"><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], '"><span>', $txt['reply'], '</span></a></li>';

		// If they *can* quote?
		if ($post['can_quote'])
			echo '
						<li class="quote_button"><a href="', $scripturl, '?action=post;topic=', $post['topic'], '.', $post['start'], ';quote=', $post['id'], '"><span>', $txt['quote'], '</span></a></li>';

		// Can we request notification of topics?
		if ($post['can_mark_notify'])
			echo '
						<li class="notify_button"><a href="', $scripturl, '?action=notify;topic=', $post['topic'], '.', $post['start'], '"><span>', $txt['notify'], '</span></a></li>';

		// How about... even... remove it entirely?!
		if ($post['can_delete'])
			echo '
						<li class="remove_button"><a href="', $scripturl, '?action=deletemsg;msg=', $post['id'], ';topic=', $post['topic'], ';recent;', $context['session_query'], '" onclick="return confirm(', $remove_confirm, ');"><span>', $txt['remove'], '</span></a></li>';

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
			<span>', $txt['pages'], ': ', $context['page_index'], '</span>
		</div>
	</div>';
}

function template_unread()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

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
		'markread' => array('text' => !empty($context['no_board_limits']) ? 'mark_as_read' : 'mark_read_short', 'image' => 'markread.gif', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=' . (!empty($context['no_board_limits']) ? 'all' : 'board' . $context['querystring_board_limits']) . ';' . $context['session_query']),
	);

	if ($show_checkboxes)
		$mark_read['markselectread'] = array(
			'text' => 'quick_mod_markread',
			'image' => 'markselectedread.gif',
			'lang' => true,
			'url' => 'javascript:document.quickModForm.submit();',
		);

	if (!empty($context['topics']))
	{
		echo '
			<div class="pagesection">';

		if (!empty($mark_read))
			template_button_strip($mark_read, 'right');

		echo '
				<span>', $txt['pages'], ': ', $context['page_index'], '</span>
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
			// Calculate the color class of the topic.
			if ($topic['is_sticky'] && $topic['is_locked'])
				$color_class = 'stickybg locked_sticky';
			// Sticky topics should get a different color, too.
			elseif ($topic['is_sticky'])
				$color_class = 'stickybg';
			// Locked topics get special treatment as well.
			elseif ($topic['is_locked'])
				$color_class = 'lockedbg';
			// Last, but not least: regular topics.
			else
				$color_class = 'windowbg';

			$color_class2 = !empty($color_class) ? $color_class . '2' : '';

			echo '
						<tr>
							<td class="', $color_class, ' icon windowbg">
								<img src="', $topic['first_post']['icon_url'], '">
							</td>
							<td class="subject windowbg2 ', $color_class2, $topic['is_posted_in'] ? ' my' : '', '">
								<div>
									', $topic['is_sticky'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span>', $topic['is_sticky'] ? '</strong>' : '', '
									<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '"><div class="new_icon" title="', $txt['new'], '"></div></a>
									<p>
										', $txt['started_by'], ' <strong>', $topic['first_post']['member']['link'], '</strong>
										', $txt['in'], ' <em>', $topic['board']['link'], '</em>
										<small id="pages', $topic['first_post']['id'], '">', $topic['pages'], '</small>
									</p>
								</div>
							</td>
							<td class="', $color_class, ' stats windowbg">
								', $topic['replies'], ' ', $txt['replies'], '
								<br>
								', $topic['views'], ' ', $txt['views'], '
							</td>
							<td class="', $color_class2, ' lastpost windowbg2">
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
			$mark_read['readall'] = array('text' => 'unread_topics_all', 'image' => 'markreadall.gif', 'lang' => true, 'url' => $scripturl . '?action=unread;all' . $context['querystring_board_limits'], 'class' => 'active');
		else
			echo '
					<tr style="display: none"><td></td></tr>';

		echo '
					</tbody>
				</table>
			</div>
			<div class="pagesection" id="readbuttons">';

		if (!empty($mark_read))
			template_button_strip($mark_read, 'right');

		echo '
				<span>', $txt['pages'], ': ', $context['page_index'], '</span>
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
		<div class="description" id="topic_icons">
			<p class="smalltext floatleft">
				<img src="' . $settings['images_url'] . '/icons/quick_lock.gif" class="middle"> ' . $txt['locked_topic'] . '<br>
				<img src="' . $settings['images_url'] . '/icons/quick_sticky.gif" class="middle"> ' . $txt['sticky_topic'] . '<br>
			</p>
			<p class="smalltext">', !empty($modSettings['enableParticipation']) ? '
				<img src="' . $settings['images_url'] . '/topic/my_normal_post.gif" class="middle"> ' . $txt['participation_caption'] . '<br>' : '', $modSettings['pollMode'] == '1' ? '
				<img src="' . $settings['images_url'] . '/topic/normal_poll.gif" class="middle"> ' . $txt['poll'] : '', '
			</p>
		</div>
	</div>';
}

function template_replies()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

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
			'markread' => array('text' => 'mark_as_read', 'image' => 'markread.gif', 'lang' => true, 'url' => $scripturl . '?action=markasread;sa=unreadreplies;topics=' . $context['topics_to_mark'] . ';' . $context['session_query']),
		);

		if ($show_checkboxes)
			$mark_read['markselectread'] = array(
				'text' => 'quick_mod_markread',
				'image' => 'markselectedread.gif',
				'lang' => true,
				'url' => 'javascript:document.quickModForm.submit();',
			);
	}

	if (!empty($context['topics']))
	{
		echo '
			<div class="pagesection">';

		if (!empty($mark_read))
			template_button_strip($mark_read, 'right');

		echo '
				<span>', $txt['pages'], ': ', $context['page_index'], '</span>
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
			// Calculate the color class of the topic.
			if ($topic['is_sticky'] && $topic['is_locked'])
				$color_class = 'stickybg locked_sticky';
			// Sticky topics should get a different color, too.
			elseif ($topic['is_sticky'])
				$color_class = 'stickybg';
			// Locked topics get special treatment as well.
			elseif ($topic['is_locked'])
				$color_class = 'lockedbg';
			// Last, but not least: regular topics.
			else
				$color_class = 'windowbg';

			$color_class2 = !empty($color_class) ? $color_class . '2' : '';

			echo '
						<tr>
							<td class="', $color_class, ' icon windowbg">
								<img src="', $topic['first_post']['icon_url'], '">
							</td>
							<td class="subject ', $color_class2, ' windowbg2">
								<div>
									', $topic['is_sticky'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], '</span>', $topic['is_sticky'] ? '</strong>' : '', '
									<a href="', $topic['new_href'], '" id="newicon', $topic['first_post']['id'], '"><div class="new_icon" title="', $txt['new'], '"></div></a>
									<p>
										', $txt['started_by'], ' <strong>', $topic['first_post']['member']['link'], '</strong>
										', $txt['in'], ' <em>', $topic['board']['link'], '</em>
										<small id="pages', $topic['first_post']['id'], '">', $topic['pages'], '</small>
									</p>
								</div>
							</td>
							<td class="', $color_class, ' stats windowbg">
								', $topic['replies'], ' ', $txt['replies'], '
								<br>
								', $topic['views'], ' ', $txt['views'], '
							</td>
							<td class="', $color_class2, ' lastpost windowbg2">
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
			<div class="pagesection">', !empty($mark_read) ? template_button_strip($mark_read, 'right') : '', '
				<span>', $txt['pages'], ': ', $context['page_index'], '</span>
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
		<div class="description flow_auto" id="topic_icons">
			<p class="smalltext floatleft">
				<img src="' . $settings['images_url'] . '/icons/quick_lock.gif" class="middle"> ' . $txt['locked_topic'] . '<br>
				<img src="' . $settings['images_url'] . '/icons/quick_sticky.gif" class="middle"> ' . $txt['sticky_topic'] . '<br>
			</p>
			<p class="smalltext">', !empty($modSettings['enableParticipation']) ? '
				<img src="' . $settings['images_url'] . '/topic/my_normal_post.gif" class="middle"> ' . $txt['participation_caption'] . '<br>' : '', $modSettings['pollMode'] == '1' ? '
				<img src="' . $settings['images_url'] . '/topic/normal_poll.gif" class="middle"> ' . $txt['poll'] : '', '
			</p>
		</div>
	</div>';
}

?>