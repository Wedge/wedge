<?php
/**
 * Wedge
 *
 * Displays the list of topics in a forum board, or snippets of topics in a list for blog boards.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_main_board()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt, $language;

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
		<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'], $context['menu_separator'],
		'&nbsp;&nbsp;<a href="#bot"><strong>', $txt['go_down'], '</strong></a></div>', empty($context['button_list']) ? '' : template_button_strip($context['button_list'], 'right'), '
	</div>';

		// If Quick Moderation is enabled start the form.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<form action="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="UTF-8" class="clear" name="quickModForm" id="quickModForm">';

		echo '
	<div class="topic_table" id="messageindex">
		<table class="table_grid cs0">
			<thead>
				<tr class="catbg">';

		// Are there actually any topics to show?
		if (!empty($context['topics']))
		{
			echo '
					<th scope="col" class="first_th" style="width: 4%">&nbsp;</th>
					<th scope="col" class="left">', template_messageindex_sortlink('subject', $txt['subject']), ' / ', template_messageindex_sortlink('starter', $txt['started_by']), '</th>
					<th scope="col" style="width: 14%">', template_messageindex_sortlink('replies', $txt['replies']), ' / ', template_messageindex_sortlink('views', $txt['views']), '</th>';

			// Show a "select all" box for quick moderation?
			if (empty($context['can_quick_mod']))
				echo '
					<th scope="col" class="left last_th" style="width: 22%">', template_messageindex_sortlink('last_post', $txt['last_post']), '</th>';
			else
				echo '
					<th scope="col" class="left" style="width: 22%">', template_messageindex_sortlink('last_post', $txt['last_post']), '</th>';

			// Show a "select all" box for quick moderation?
			if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
				echo '
					<th scope="col" class="last_th" style="width: 24px"><input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');"></th>';

			// If it's on in "image" mode, don't show anything but the column.
			elseif (!empty($context['can_quick_mod']))
				echo '
					<th class="last_th" style="width: 4%">&nbsp;</th>';
		}
		// No topics.... just say, "sorry bub".
		else
			echo '
					<th scope="col" class="first_th" style="width: 8%">&nbsp;</th>
					<th colspan="3"><strong>', $txt['msg_alert_none'], '</strong></th>
					<th scope="col" class="last_th" style="width: 8%">&nbsp;</th>';

		echo '
				</tr>
			</thead>
			<tbody>';

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
		{
			echo '
				<tr class="windowbg2">
					<td colspan="', !empty($context['can_quick_mod']) ? '5' : '4', '">
						<span class="alert">!</span> ', $context['unapproved_posts_message'], '
					</td>
				</tr>';
		}

		foreach ($context['topics'] as $topic)
		{
			// Is this topic pending approval, or does it have any posts pending approval?
			if ($context['can_approve_posts'] && $topic['unapproved_posts'])
				$color_class = !$topic['approved'] ? 'approvetbg' : 'approvebg';
			// We start with locked and sticky topics.
			elseif ($topic['is_sticky'] && $topic['is_locked'])
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

			// Some columns require a different shade of the color class.
			$alternate_class = $color_class . '2';

			echo '
				<tr>
					<td class="icon ', $color_class, '">
						<img src="', $topic['first_post']['icon_url'], '">
					</td>
					<td class="subject ', $alternate_class, $topic['is_posted_in'] ? ' my' : '', '">
						<div ', (!empty($topic['quick_mod']['modify']) ? 'id="topic_' . $topic['first_post']['id'] . '" onmouseout="mouse_on_div = 0;" onmouseover="mouse_on_div = 1;" ondblclick="modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : ''), '>
							', $topic['is_sticky'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], (!$context['can_approve_posts'] && !$topic['approved'] ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : ''), '</span>', $topic['is_sticky'] ? '</strong>' : '';

			// Is this topic new? (assuming they are logged in!)
			if ($topic['new'] && $context['user']['is_logged'])
					echo '
							<a href="', $topic['new_href'], '" id="newicon' . $topic['first_post']['id'] . '"><div class="new_icon" title="', $txt['new'], '"></div></a>';

			echo '
							<p>', $txt['started_by'], ' ', $topic['first_post']['member']['link'], '
								<small id="pages' . $topic['first_post']['id'] . '">', $topic['pages'], '</small>
							</p>
						</div>
					</td>
					<td class="stats ', $color_class, '">
						', $topic['replies'], ' ', $txt['replies'], '
						<br>', $topic['views'], ' ', $txt['views'], '
					</td>
					<td class="lastpost ', $alternate_class, '">
						<a href="', $topic['last_post']['href'], '"><img src="', $settings['images_url'], '/icons/last_post.gif" alt="', $txt['last_post'], '" title="', $txt['last_post'], '"></a>
						', $topic['last_post']['time'], '
						<br>', $txt['by'], ' ', $topic['last_post']['member']['link'], '
					</td>';

			// Show the quick moderation options?
			if (!empty($context['can_quick_mod']))
			{
				echo '
					<td class="center moderation ', $color_class, '">';
				if ($options['display_quick_mod'] == 1)
					echo '
						<input type="checkbox" name="topics[]" value="', $topic['id'], '">';
				else
				{
					// Check permissions on each and show only the ones they are allowed to use.
					$confirm = JavaScriptEscape($txt['quickmod_confirm']);

					if ($topic['quick_mod']['remove'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=remove;', $context['session_query'], '" onclick="return confirm(', $confirm, ');"><img src="', $settings['images_url'], '/icons/quick_remove.gif" width="16" alt="', $txt['remove_topic'], '" title="', $txt['remove_topic'], '"></a>';

					if ($topic['quick_mod']['lock'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=lock;', $context['session_query'], '" onclick="return confirm(', $confirm, ');"><img src="', $settings['images_url'], '/icons/quick_lock.gif" width="16" alt="', $txt['set_lock'], '" title="', $txt['set_lock'], '"></a>';

					if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
						echo '<br>';

					if ($topic['quick_mod']['sticky'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=sticky;', $context['session_query'], '" onclick="return confirm(', $confirm, ');"><img src="', $settings['images_url'], '/icons/quick_sticky.gif" width="16" alt="', $txt['set_sticky'], '" title="', $txt['set_sticky'], '"></a>';

					if ($topic['quick_mod']['move'])
						echo '<a href="', $scripturl, '?action=movetopic;board=', $context['current_board'], '.', $context['start'], ';topic=', $topic['id'], '.0"><img src="', $settings['images_url'], '/icons/quick_move.gif" width="16" alt="', $txt['move_topic'], '" title="', $txt['move_topic'], '"></a>';
				}
				echo '
					</td>';
			}
			echo '
				</tr>';
		}

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
		{
			echo '
				<tr class="titlebg">
					<td colspan="5" class="round-bottom right">
						<select class="qaction" name="qaction"', $context['can_move'] ? ' onchange="this.form.moveItTo.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
							<option value="">--------</option>', $context['can_remove'] ? '
							<option value="remove">' . $txt['quick_mod_remove'] . '</option>' : '', $context['can_lock'] ? '
							<option value="lock">' . $txt['quick_mod_lock'] . '</option>' : '', $context['can_sticky'] ? '
							<option value="sticky">' . $txt['quick_mod_sticky'] . '</option>' : '', $context['can_move'] ? '
							<option value="move">' . $txt['quick_mod_move'] . ': </option>' : '', $context['can_merge'] ? '
							<option value="merge">' . $txt['quick_mod_merge'] . '</option>' : '', $context['can_restore'] ? '
							<option value="restore">' . $txt['quick_mod_restore'] . '</option>' : '', $context['can_approve'] ? '
							<option value="approve">' . $txt['quick_mod_approve'] . '</option>' : '', $context['user']['is_logged'] ? '
							<option value="markread">' . $txt['quick_mod_markread'] . '</option>' : '', '
						</select>';

			// Show a list of boards they can move the topic to.
			if ($context['can_move'])
			{
				echo '
						<select class="qaction" id="moveItTo" name="move_to" disabled>';

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
						<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return document.forms.quickModForm.qaction.value != \'\' && confirm(', JavaScriptEscape($txt['quickmod_confirm']), ');" class="qaction">
					</td>
				</tr>';
		}

		echo '
			</tbody>
		</table>
	</div>
	<a id="bot"></a>';

		// Finish off the form - again.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] > 0 && !empty($context['topics']))
			echo '
	<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
	</form>';

		echo '
	<div class="pagesection">', empty($context['button_list']) ? '' : template_button_strip($context['button_list'], 'right'), '
		<p class="floatright" id="message_index_jump_to">&nbsp;</p>
		<div class="pagelinks">', $txt['pages'], ': ', $context['page_index'], $context['menu_separator'], '&nbsp;&nbsp;<a href="#top"><strong>', $txt['go_up'], '</strong></a></div>
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	$context['bottom_linktree'] = true;

	add_js('
	if (can_ajax)
		aJumpTo.push(new JumpTo({
			iBoardId: ' . $context['current_board'] . ',
			sContainerId: "message_index_jump_to",
			sJumpToTemplate: "<label for=\"%select_id%\">' . $txt['jump_to'] . ':<\/label> %dropdown_list%",
			sPlaceholder: ' . JavaScriptEscape($txt['select_destination']) . '
		}));');

	// JavaScript for inline editing.
	add_js_file('scripts/topic.js');

	add_js('
	// Hide certain bits during topic edit.
	hide_prefixes.push("lockicon", "stickyicon", "pages", "newicon");

	// Detect when we\'ve stopped editing.
	var mouse_on_div;
	$(document).click(function () {
		if (is_editing() && mouse_on_div == 0)
			modify_topic_save("' . $context['session_id'] . '", "' . $context['session_var'] . '");
	});

	function modify_topic_keypress(e)
	{
		if (e.which == 13)
		{
			modify_topic_save("' . $context['session_id'] . '", "' . $context['session_var'] . '");
			e.preventDefault();
		}
	}');
}

function template_main_blog()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt, $language, $board_info;

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
		<div class="pagelinks floatleft">', $txt['pages'], ': ', $context['page_index'], $context['menu_separator'],
		'&nbsp;&nbsp;<a href="#bot"><strong>' . $txt['go_down'] . '</strong></a></div>', empty($context['button_list']) ? '' : template_button_strip($context['button_list'], 'right'), '
	</div>';

		// If Quick Moderation is enabled start the form.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
			echo '
	<form action="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], '" method="post" accept-charset="UTF-8" class="clear" name="quickModForm" id="quickModForm">';

		echo '
	<div class="topic_table" id="messageindex">
		<table class="table_grid cs0">
			<thead>
				<tr class="catbg">';

		// Are there actually any topics to show?
		if (!empty($context['topics']))
		{
			echo '
					<th scope="col" class="first_th" style="width: 4%">&nbsp;</th>
					<th scope="col" class="left">', template_messageindex_sortlink('subject', $txt['subject']), ' / ', template_messageindex_sortlink('starter', $txt['started_by']), '</th>';

			// Show a "select all" box for quick moderation?
			if (empty($context['can_quick_mod']))
				echo '
					<th scope="col" class="left last_th" style="width: 22%">', template_messageindex_sortlink('replies', $txt['replies']), ' / ', template_messageindex_sortlink('views', $txt['views']), '</th>';
			else
				echo '
					<th scope="col" class="left" style="width: 22%">', template_messageindex_sortlink('replies', $txt['replies']), ' / ', template_messageindex_sortlink('views', $txt['views']), '</th>';

			// Show a "select all" box for quick moderation?
			if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1)
				echo '
					<th scope="col" class="last_th" style="width: 24px"><input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');"></th>';

			// If it's on in "image" mode, don't show anything but the column.
			elseif (!empty($context['can_quick_mod']))
				echo '
					<th class="last_th" style="width: 4%">&nbsp;</th>';
		}
		// No topics.... just say, "sorry bub".
		else
			echo '
					<th scope="col" class="first_th" style="width: 8%">&nbsp;</th>
					<th colspan="3"><strong>', $txt['msg_alert_none'], '</strong></th>
					<th scope="col" class="last_th" style="width: 8%">&nbsp;</th>';

		echo '
				</tr>
			</thead>
			<tbody>';

		// If this person can approve items and we have some awaiting approval tell them.
		if (!empty($context['unapproved_posts_message']))
		{
			echo '
				<tr class="windowbg2">
					<td colspan="', !empty($context['can_quick_mod']) ? '4' : '3', '">
						<span class="alert">!</span> ', $context['unapproved_posts_message'], '
					</td>
				</tr>';
		}

		foreach ($context['topics'] as $topic)
		{
			// Is this topic pending approval, or does it have any posts pending approval?
			if ($context['can_approve_posts'] && $topic['unapproved_posts'])
				$color_class = !$topic['approved'] ? 'approvetbg' : 'approvebg';
			// We start with locked and sticky topics.
			elseif ($topic['is_sticky'] && $topic['is_locked'])
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

			// Some columns require a different shade of the color class.
			$alternate_class = $color_class . '2';

			echo '
				<tr>
					<td class="icon ', $color_class, '">
						<img src="', $topic['first_post']['icon_url'], '">
					</td>
					<td class="subject ', $alternate_class, $topic['is_posted_in'] ? ' my' : '', '">
						<div ', (!empty($topic['quick_mod']['modify']) ? 'id="topic_' . $topic['first_post']['id'] . '" onmouseout="mouse_on_div = 0;" onmouseover="mouse_on_div = 1;" ondblclick="modify_topic(\'' . $topic['id'] . '\', \'' . $topic['first_post']['id'] . '\');"' : ''), '>
							', $topic['is_sticky'] ? '<strong>' : '', '<span id="msg_' . $topic['first_post']['id'] . '">', $topic['first_post']['link'], (!$context['can_approve_posts'] && !$topic['approved'] ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : ''), '</span>', $topic['is_sticky'] ? '</strong>' : '';

			// Is this topic new? (assuming they are logged in!)
			if ($topic['new'] && $context['user']['is_logged'])
					echo '
							<a href="', $topic['new_href'], '" id="newicon' . $topic['first_post']['id'] . '"><div class="new_icon" title="', $txt['new'], '"></div></a>';

			echo '
							<p>', $txt['posted_by'], ' ', $topic['first_post']['member']['link'], ', ', $topic['last_post']['time'], '
								<small id="pages' . $topic['first_post']['id'] . '">', $topic['pages'], '</small>
							</p>
							', $topic['first_post']['preview'], '
						</div>
					</td>
					<td class="stats ', $color_class, '">
						', $topic['replies'], ' ', $txt['replies'], '
						<br>', $topic['views'], ' ', $txt['views'], '
					</td>';

			// Show the quick moderation options?
			if (!empty($context['can_quick_mod']))
			{
				echo '
					<td class="center moderation ', $color_class, '">';
				if ($options['display_quick_mod'] == 1)
					echo '
						<input type="checkbox" name="topics[]" value="', $topic['id'], '">';
				else
				{
					// Check permissions on each and show only the ones they are allowed to use.
					$confirm = JavaScriptEscape($txt['quickmod_confirm']);

					if ($topic['quick_mod']['remove'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=remove;', $context['session_query'], '" onclick="return confirm(', $confirm, ');"><img src="', $settings['images_url'], '/icons/quick_remove.gif" width="16" alt="', $txt['remove_topic'], '" title="', $txt['remove_topic'], '"></a>';

					if ($topic['quick_mod']['lock'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=lock;', $context['session_query'], '" onclick="return confirm(', $confirm, ');"><img src="', $settings['images_url'], '/icons/quick_lock.gif" width="16" alt="', $txt['set_lock'], '" title="', $txt['set_lock'], '"></a>';

					if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
						echo '<br>';

					if ($topic['quick_mod']['sticky'])
						echo '<a href="', $scripturl, '?action=quickmod;board=', $context['current_board'], '.', $context['start'], ';actions[', $topic['id'], ']=sticky;', $context['session_query'], '" onclick="return confirm(', $confirm, ');"><img src="', $settings['images_url'], '/icons/quick_sticky.gif" width="16" alt="', $txt['set_sticky'], '" title="', $txt['set_sticky'], '"></a>';

					if ($topic['quick_mod']['move'])
						echo '<a href="', $scripturl, '?action=movetopic;board=', $context['current_board'], '.', $context['start'], ';topic=', $topic['id'], '.0"><img src="', $settings['images_url'], '/icons/quick_move.gif" width="16" alt="', $txt['move_topic'], '" title="', $txt['move_topic'], '"></a>';
				}
				echo '
					</td>';
			}
			echo '
				</tr>';
		}

		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
		{
			echo '
				<tr class="titlebg">
					<td colspan="5" class="right">
						<select class="qaction" name="qaction"', $context['can_move'] ? ' onchange="this.form.moveItTo.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
							<option value="">--------</option>', $context['can_remove'] ? '
							<option value="remove">' . $txt['quick_mod_remove'] . '</option>' : '', $context['can_lock'] ? '
							<option value="lock">' . $txt['quick_mod_lock'] . '</option>' : '', $context['can_sticky'] ? '
							<option value="sticky">' . $txt['quick_mod_sticky'] . '</option>' : '', $context['can_move'] ? '
							<option value="move">' . $txt['quick_mod_move'] . ': </option>' : '', $context['can_merge'] ? '
							<option value="merge">' . $txt['quick_mod_merge'] . '</option>' : '', $context['can_restore'] ? '
							<option value="restore">' . $txt['quick_mod_restore'] . '</option>' : '', $context['can_approve'] ? '
							<option value="approve">' . $txt['quick_mod_approve'] . '</option>' : '', $context['user']['is_logged'] ? '
							<option value="markread">' . $txt['quick_mod_markread'] . '</option>' : '', '
						</select>';

			// Show a list of boards they can move the topic to.
			if ($context['can_move'])
			{
				echo '
						<select class="qaction" id="moveItTo" name="move_to" disabled>';

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
						<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return document.forms.quickModForm.qaction.value != \'\' && confirm(', JavaScriptEscape($txt['quickmod_confirm']), ');" class="qaction">
					</td>
				</tr>';
		}

		echo '
			</tbody>
		</table>
	</div>
	<a id="bot"></a>';

		// Finish off the form - again.
		if (!empty($context['can_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
			echo '
	<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
	</form>';

		echo '
	<div class="pagesection">', empty($context['button_list']) ? '' : template_button_strip($context['button_list'], 'right'), '
		<p class="floatright" id="message_index_jump_to">&nbsp;</p>
		<div class="pagelinks">', $txt['pages'], ': ', $context['page_index'], $context['menu_separator'], '&nbsp;&nbsp;<a href="#top"><strong>', $txt['go_up'], '</strong></a></div>
	</div>';
	}

	// Show breadcrumbs at the bottom too.
	$context['bottom_linktree'] = true;

	add_js('
	if (can_ajax)
		aJumpTo.push(new JumpTo({
			iBoardId: ' . $context['current_board'] . ',
			sContainerId: "message_index_jump_to",
			sJumpToTemplate: "<label for=\"%select_id%\">' . $txt['jump_to'] . ':<\/label> %dropdown_list%",
			sPlaceholder: ' . JavaScriptEscape($txt['select_destination']) . '
		}));');

	// JavaScript for inline editing.
	add_js_file('scripts/topic.js');

	add_js('
	// Hide certain bits during topic edit.
	hide_prefixes.push("lockicon", "stickyicon", "pages", "newicon");

	// Use it to detect when we\'ve stopped editing.
	document.onclick = modify_topic_click;

	var mouse_on_div;
	function modify_topic_click()
	{
		if (is_editing() && mouse_on_div == 0)
			modify_topic_save("' . $context['session_id'] . '", "' . $context['session_var'] . '");
	}

	function modify_topic_keypress(e)
	{
		if (e.which == 13)
		{
			modify_topic_save("' . $context['session_id'] . '", "' . $context['session_var'] . '");
			e.preventDefault();
		}
	}');
}

function template_messageindex_childboards()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt, $language;

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
						<a', $board['redirect_newtab'] ? ' target="_blank"' : '', ' href="', ($board['is_redirect'] || $context['user']['is_guest'] ? $board['href'] : $scripturl . '?action=unread;board=' . $board['id'] . '.0;children'), '">';

			// If the board or children is new, show an indicator.
			if ($board['new'] || $board['children_new'])
				echo '
							<div class="boardstate_', $board['new'] ? 'new' : 'on', '" title="', $txt['new_posts'], '"></div>';
			// Is it a redirection board?
			elseif ($board['is_redirect'])
				echo '
							<div class="boardstate_redirect"></div>';
			// No new posts at all! The agony!!
			else
				echo '
							<div class="boardstate_off" title="', $txt['old_posts'], '"></div>';

			echo '
						</a>
					</td>
					<td class="info">
						', $modSettings['display_flags'] == 'all' || ($modSettings['display_flags'] == 'specified' && !empty($board['language'])) ? '<img src="' . $settings['default_theme_url'] . '/languages/Flag.' . (empty($board['language']) ? $language : $board['language']) . '.png"> ': '', '<a', $board['redirect_newtab'] ? ' target="_blank"' : '', ' class="subject" href="', $board['href'], '" name="b', $board['id'], '">', $board['name'], '</a>';

			// Has it outstanding posts for approval?
			if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
				echo '
						<a href="', $scripturl, '?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_query'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

			echo '

						<p>', $board['description'], '</p>';

			// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
			if (!empty($board['moderators']))
				echo '
						<p class="moderators">', count($board['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

			// Show some basic information about the number of posts, etc.
			echo '
					</td>
					<td class="stats windowbg">
						<p>', number_context($board['is_redirect'] ? 'redirects' : 'posts', $board['posts']), ' <br>
						', $board['is_redirect'] ? '' : number_context('board_topics', $board['topics']), '
						</p>
					</td>
					<td class="lastpost">';

			/* The board's and children's 'last_post's have:
			time, timestamp (a number that represents the time), id (of the post), topic (topic id),
			link, href, subject, start (where they should go for the first unread post),
			and member (which has id, name, link, href, username in it.) */
			if (!empty($board['last_post']['id']))
				echo '
						<p><strong>', $txt['last_post'], '</strong> ', $txt['by'], ' ', $board['last_post']['member']['link'], '
						<br>', $txt['in'], ' ', $board['last_post']['link'], '
						<br>', $txt['on'], ' ', $board['last_post']['time'], '</p>';

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
						$child['link'] = '<a href="' . $child['href'] . '" ' . ($child['new'] ? 'class="new_posts" ' : '') . 'title="' . ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')">' . $child['name'] . ($child['new'] ? '</a> <a href="' . $scripturl . '?action=unread;board=' . $child['id'] . '" title="' . $txt['new_posts'] . ' (' . $txt['board_topics'] . ': ' . comma_format($child['topics']) . ', ' . $txt['posts'] . ': ' . comma_format($child['posts']) . ')"><div class="new_icon new_posts"></div>' : '') . '</a>';
					else
						$child['link'] = '<a href="' . $child['href'] . '" title="' . comma_format($child['posts']) . ' ' . $txt['redirects'] . '">' . $child['name'] . '</a>';

					// Has it posts awaiting approval?
					if ($child['can_approve_posts'] && ($child['unapproved_posts'] | $child['unapproved_topics']))
						$child['link'] .= ' <a href="' . $scripturl . '?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_query'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

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

function template_messageindex_whoviewing()
{
	global $txt, $context, $settings;

	echo '
		<we:title2>
			<img src="', $settings['images_url'], '/icons/online.gif" alt="', $txt['online_users'], '">', $txt['who_title'], '
		</we:title>
		<p>';

	if ($settings['display_who_viewing'] == 1)
		echo count($context['view_members']), ' ', count($context['view_members']) === 1 ? $txt['who_member'] : $txt['members'];
	else
		echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . ((empty($context['view_num_hidden']) or $context['can_moderate_forum']) ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

	echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_board'], '
		</p>';
}

function template_messageindex_draft()
{
	global $context, $txt, $scripturl;

	if ($context['draft_saved'])
		echo '
	<div class="windowbg" id="profile_success">
		', str_replace('{draft_link}', $scripturl . '?action=profile;area=showdrafts', $txt['draft_saved']), '
	</div>';
}

function template_messageindex_sortlink($sort, $caption)
{
	global $context, $settings, $scripturl;

	if (empty($context['can_reorder']))
		echo $caption; // !!! If we want the direction indicator: , $context['sort_by'] == $sort ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '';
	else
		echo '<a href="', $scripturl, '?board=', $context['current_board'], '.', $context['start'], ';sort=', $sort, $context['sort_by'] == $sort && $context['sort_direction'] == 'up' ? ';desc' : '', '">', $caption, $context['sort_by'] == $sort ? ' <img src="' . $settings['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a>';
}

function template_messageindex_legend()
{
	global $settings, $txt, $context, $modSettings;

	echo '
		<we:title2>
			<img src="', $settings['images_url'], '/icons/assist.gif">
			', $txt['legend'], '
		</we:title2>
		<p>
			<img src="' . $settings['images_url'] . '/icons/quick_lock.gif" class="middle"> ', $txt['locked_topic'], '<br>
			<img src="' . $settings['images_url'] . '/icons/quick_sticky.gif" class="middle"> ', $txt['sticky_topic'], '<br>', $modSettings['pollMode'] == '1' ? '
			<img src="' . $settings['images_url'] . '/topic/normal_poll.gif" class="middle"> ' . $txt['poll'] : '', '<br>', !empty($modSettings['enableParticipation']) && $context['user']['is_logged'] ? '
			<img src="' . $settings['images_url'] . '/topic/my_normal_post.gif" class="middle"> ' . $txt['participation_caption'] : '', '
		</p>';
}

// Show statistical style information...
// !!! Should we show this only in MessageIndex, or in index and show
// !!! it based on !empty($context['current_board']) or something?
function template_messageindex_statistics()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings, $board_info;

	if (!$settings['show_stats_index'])
		return;

	$type = $board_info['type'] == 'board' ? 'board' : 'blog';

	echo '
				<we:title2>
					<a href="', $scripturl, '?board=', $context['current_board'], ';action=stats"><img src="', $settings['images_url'], '/icons/info.gif" alt="', $txt[$type . '_stats'], '"></a>
					', $txt[$type . '_stats'], '
				</we:title2>
				<p>
					', $board_info['num_posts'], ' ', $txt['posts_made'], ' ', $txt['in'], ' ', $board_info['total_topics'], ' ', $txt['topics'], '<br>
				</p>';
}

?>