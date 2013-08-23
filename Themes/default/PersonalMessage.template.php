<?php
/**
 * Wedge
 *
 * Displays your personal messages, as well as all related information.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// This is the main sidebar for the personal messages section.
function template_pm_before()
{
	global $context, $theme, $options, $txt;

	if ($context['page_title'] === $txt['showDrafts'])
		echo '
	<div id="personal_drafts">';

	// Show the capacity bar, if available.
	if (!empty($context['limit_bar']))
		echo '
		<we:title>
			<span class="floatleft">', $txt['pm_capacity'], ':</span>
			<span class="floatleft capacity_bar">
				<span class="', $context['limit_bar']['percent'] > 85 ? 'full' : ($context['limit_bar']['percent'] > 40 ? 'filled' : 'empty'), '" style="width: ', $context['limit_bar']['percent'] / 10, 'em"></span>
			</span>
			<span class="floatright', $context['limit_bar']['percent'] > 90 ? ' alert' : '', '">', $context['limit_bar']['text'], '</span>
		</we:title>';

	// Message sent? Show a small indication.
	if (isset($context['pm_sent']))
		echo '
		<div class="windowbg" id="profile_success">
			', $txt['pm_sent'], '
		</div>';
	elseif ($context['draft_saved'])
		echo '
		<div class="windowbg" id="profile_success">
			', str_replace('{draft_link}', '<URL>?action=pm;sa=showdrafts', $txt['pm_draft_saved']), '
		</div>';
}

// Just the end of the index bar, nothing special.
function template_pm_after()
{
	global $context, $txt;

	if ($context['page_title'] === $txt['showDrafts'])
		echo '
	</div>';
}

function template_pm_popup()
{
	global $txt, $context, $memberContext;

	echo '
		<ul id="pmlist"><li>
			<span class="floatright">', $context['can_send'] ? '
				<a href="<URL>?action=pm;sa=send" style="color: #888">' . $txt['notifications_short_write_pm'] . '</a> |' : '', $context['show_drafts'] ? '
				<a href="<URL>?action=pm;sa=showdrafts" style="color: #888">' . $txt['notifications_short_drafts'] . '</a> |' : '', '
				<span style="display: inline-block"><a href="<URL>?action=pm;sa=settings" style="color: #666"><span id="m_admin" style="margin-top: 1px"></span>
				', $txt['notifications_short_settings'], '</a></span>&nbsp;
			</span>
			<span class="floatleft" style="margin-bottom: 4px">
				&nbsp;<strong>', $txt['notifications_short_unread_pms'], '</strong> |
				<a href="<URL>?action=pm" style="color: #888">', $txt['notifications_short_inbox'], '</a> |
				<a href="<URL>?action=pm;f=sent" style="color: #888">', $txt['notifications_short_sent'], '</a>
			</span>
			<div id="pm_container">';

	if (empty($context['personal_messages']))
		echo '
			<div class="center padding">', $txt['no_pms'], '</div>';
	else
		foreach ($context['personal_messages'] as $pm)
			echo '
				<div class="n_item n_new" id="pm', $pm['id_pm'], '">
					<div class="n_time">', timeformat($pm['msgtime']), '</div>
					<div class="n_icon">', !empty($memberContext[$pm['id_member_from']]['avatar']) ? $memberContext[$pm['id_member_from']]['avatar']['image'] : '', '</div>
					<div class="n_text"> ', sprintf($txt[$pm['sprintf']], $pm['member_link'], $pm['msg_link']), '</div>
				</div>';

	if (AJAX)
		echo '
			</div>
		</li></ul>';
}

function template_folder()
{
	global $context, $theme, $options, $settings, $txt;

	$is_mobile = SKIN_MOBILE;

	// The ever helpful JavaScript!
	add_js('
	var currentLabels = [], allLabels = { ');

	$js = '';

	foreach ($context['labels'] as $label)
		$js .= '\'' . $label['id'] . '\': ' . JavaScriptEscape($label['name']) . ', ';

	if (!empty($context['labels']))
		$js = substr($js, 0, -2);

	add_js($js . ' };
	function loadLabelChoices()
	{
		var
			listing = $("input[name=\'pms\[\]\']:checked", document.forms.pmFolder),
			theSelect = $(document.forms.pmFolder.pm_action),
			toAdd = "", toRemove = "", i, o, x;

		for (i = 0; i < listing.length; i++)
		{
			var alreadyThere = [];
			for (x in currentLabels[listing[i].value])
			{
				if (!toRemove.match("<option value=\'rem_" + x + "\'"))
					toRemove += "<option value=\'rem_" + x + "\'>" + allLabels[x] + "</option>";
				alreadyThere[x] = allLabels[x];
			}
			for (x in allLabels)
				if (!(x in alreadyThere) && !toAdd.match("<option value=\'add_" + x + "\'"))
					toAdd += "<option value=\'add_" + x + "\'>" + allLabels[x] + "</option>";
		}

		$(theSelect).children("optgroup").remove();
		if (toAdd)
			theSelect.append($("<optgroup></optgroup>").attr("label", ', JavaScriptEscape($txt['pm_msg_label_apply']), ').append(toAdd));
		if (toRemove)
			theSelect.append($("<optgroup></optgroup>").attr("label", ', JavaScriptEscape($txt['pm_msg_label_remove']), ').append(toRemove));

		$(theSelect).sb();
	}');

	echo '
<form class="flow_hidden" action="<URL>?action=pm;sa=pmactions;', $context['display_mode'] == 2 ? 'conversation;' : '', 'f=', $context['folder'], ';start=', $context['start'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', '" method="post" accept-charset="UTF-8" name="pmFolder">';

	$remove_confirm = JavaScriptEscape($txt['remove_message_confirm']);

	// If we are not in single display mode show the subjects on the top!
	if ($context['display_mode'] != 1)
	{
		template_subject_list();
		echo '
	<div class="clear_right"><br></div>';
	}

	// Got some messages to display?
	if ($context['get_pmessage']('message', true))
	{
		// Show a few buttons if we are in conversation mode and outputting the first message.
		if ($context['display_mode'] == 2)
		{
			// Build the normal button array.
			$conversation_buttons = array(
				'reply' => array('text' => 'reply_to_all', 'url' => '<URL>?action=pm;sa=send;f=' . $context['folder'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '') . ';pmsg=' . $context['current_pm'] . ';u=all', 'class' => 'active'),
				'delete' => array('text' => 'delete_conversation', 'url' => '<URL>?action=pm;sa=pmactions;pm_actions%5B' . $context['current_pm'] . '%5D=delete;conversation;f=' . $context['folder'] . ';start=' . $context['start'] . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '') . ';' . $context['session_query'], 'custom' => 'onclick="return ask(' . $remove_confirm . ', e);"'),
			);

			// Show the conversation buttons.
			echo '
	<div class="pagesection">',
		template_button_strip($conversation_buttons), '
	</div>';
		}

		// Show the helpful titlebar - generally.
		if ($context['display_mode'] != 1)
			echo '
	<we:cat>
		<span id="author">', $txt['author'], '</span>
		<span id="topic_title">', $txt[$context['display_mode'] == 0 ? 'messages' : 'conversation'], '</span>
	</we:cat>';

		$gts = !empty($settings['group_text_show']) ? $settings['group_text_show'] : 'cond';

		while ($message = $context['get_pmessage']('message'))
		{
			$window_class = $message['alternate'] == 0 ? '' : '2';

			echo '
	<div class="msg', $window_class, $message['member']['id'] === we::$id ? ' self' : '', ' pm"><div class="post_wrapper">
		<div class="poster">
			<a id="msg', $message['id'], '"></a>
			<h4>';

			// Show user statuses
			if (!$message['member']['is_guest'])
				template_user_status($message['member']);

			echo '
				', $message['member']['link'], '
			</h4>
			<ul class="info" id="msg_', $message['id'], '_extra_info">';

			// Show the member's custom title, if they have one.
			if (!empty($message['member']['title']) && !$is_mobile)
				echo '
				<li class="mtitle">', $message['member']['title'], '</li>';

			// Show the member's primary group (like 'Administrator') if they have one.
			if (!empty($message['member']['group']) && ($gts === 'all' || $gts === 'normal' || $gts === 'cond'))
				echo '
				<li class="membergroup">', $message['member']['group'], '</li>';

			// Don't show these things for guests or mobile skins.
			if (!$message['member']['is_guest'] && !$is_mobile)
			{
				// Show the post-based group if allowed by $settings['group_text_show'].
				if (!empty($message['member']['post_group']) && ($gts === 'all' || $gts === 'post' || ($gts === 'cond' && empty($message['member']['group']))))
					echo '
				<li class="postgroup">', $message['member']['post_group'], '</li>';

				if (!empty($message['member']['group_badges']))
					echo '
				<li class="stars">
					<div>', implode('</div>
					<div>', $message['member']['group_badges']), '</div>
				</li>';

				// Show avatars, images, etc.?
				if (!empty($theme['show_user_images']) && !empty($options['show_avatars']) && !empty($message['member']['avatar']['image']))
					echo '
				<li class="avatar">
					<a href="<URL>?action=profile;u=', $message['member']['id'], '">
						', $message['member']['avatar']['image'], '
					</a>
				</li>';

				// Show how many posts they have made.
				if (!isset($context['disabled_fields']['posts']))
					echo '
				<li class="postcount">', $txt['member_postcount'], ': ', $message['member']['posts'], '</li>';

				// Show their personal text?
				if (!empty($theme['show_blurb']) && $message['member']['blurb'] !== '')
					echo '
				<li class="blurb">', $message['member']['blurb'], '</li>';

				// Any custom fields to show as icons?
				if (!empty($message['member']['custom_fields']))
				{
					$shown = false;
					foreach ($message['member']['custom_fields'] as $custom)
					{
						if ($custom['placement'] != 1 || empty($custom['value']))
							continue;
						if (empty($shown))
						{
							$shown = true;
							echo '
				<li class="im_icons">
					<ul>';
						}
						echo '
						<li>', $custom['value'], '</li>';
					}
					if ($shown)
					echo '
					</ul>
				</li>';
				}

				// Any custom fields for standard placement?
				if (!empty($message['member']['custom_fields']))
					foreach ($message['member']['custom_fields'] as $custom)
						if (empty($custom['placement']) || empty($custom['value']))
							echo '
				<li class="custom">', $custom['title'], ': ', $custom['value'], '</li>';

				// Are we showing the warning status?
				if ($message['member']['can_see_warning'])
				echo '
				<li class="warning">', $context['can_issue_warning'] && $message['member']['warning_status'] != 'hard_ban' ? '<a href="<URL>?action=profile;u=' . $message['member']['id'] . ';area=issuewarning">' : '', '<img src="', $theme['images_url'], '/warning_', $message['member']['warning_status'], '.gif" alt="', $txt['user_warn_' . $message['member']['warning_status']], '">', $context['can_issue_warning'] && $message['member']['warning_status'] != 'hard_ban' ? '</a>' : '', '<span class="warn_', $message['member']['warning_status'], '">', $txt['warn_' . $message['member']['warning_status']], '</span></li>';
			}

			// Done with the information about the poster... on to the post itself.
			echo '
			</ul>
		</div>
		<div class="postarea">
			<div class="postheader">
				<div class="keyinfo">
					<h5 id="subject_', $message['id'], '">
						', $message['subject'], '
					</h5>';

			// Show who the message was sent to.
			echo '
					<span class="smalltext"><strong> ', $txt['sent_to'], ':</strong> ';

			// People it was sent directly to....
			if (!empty($message['recipients']['to']))
				echo implode(', ', $message['recipients']['to']);
			// Otherwise, we're just going to say "some people"...
			elseif ($context['folder'] != 'sent')
				echo '(', $txt['pm_undisclosed_recipients'], ')';

			echo '
						', $message['on_time'], '
					</span>';

			// If we're in the sent items, show who it was sent to besides the "To:" people.
			if (!empty($message['recipients']['bcc']))
				echo '
					<div class="smalltext">&#171;&nbsp;<strong> ', $txt['pm_bcc'], ':</strong> ', implode(', ', $message['recipients']['bcc']), '&nbsp;&#187;</div>';

			if (!empty($message['is_replied_to']))
				echo '
					<div class="smalltext">&#171;&nbsp;', $message['replied_msg'], '&nbsp;&#187;</div>';

			echo '
				</div>
				<div class="actionbar">
					<ul class="actions">';

			// Show reply buttons if you have the permission to send PMs.
			if ($context['can_send_pm'])
			{
				// You can't really reply if the member is gone.
				if (!$message['member']['is_guest'])
				{
					// Is there than more than one recipient you can reply to?
					if ($message['number_recipients'] > 1)
						echo '
						<li><a href="<URL>?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';quote;u=all"class="reply_button">', $txt['reply_to_all'], '</a></li>';

					echo '
						<li><a href="<URL>?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';u=', $message['member']['id'], '" class="reply_button">', $txt['reply'], '</a></li>
						<li><a href="<URL>?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';quote', $context['folder'] == 'sent' ? '' : ';u=' . $message['member']['id'], '" class="quote_button">', $txt['quote'], '</a></li>';
				}
				// This is for "forwarding" - even if the member is gone.
				else
					echo '
						<li><a href="<URL>?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';quote" class="forward_button">', $txt['quote'], '</a></li>';
			}
			echo '
						<li><a href="<URL>?action=pm;sa=pmactions;pm_actions%5B', $message['id'], '%5D=delete;f=', $context['folder'], ';start=', $context['start'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';', $context['session_query'], '" onclick="return ask(', $remove_confirm, ', e);" class="remove_button">', $txt['delete'], '</a></li>';

			if (empty($context['display_mode']))
				echo '
						<li class="inline_mod_check"><input type="checkbox" name="pms[]" id="deletedisplay', $message['id'], '" value="', $message['id'], '" onclick="$(\'#deletelisting', $message['id'], '\').prop(\'checked\', this.checked);"></li>';

			echo '
					</ul>
				</div>
			</div>
			<div class="post">
				<div class="inner" id="msg_', $message['id'], '"', '>', $message['body'], '</div>';

			if ($context['folder'] != 'sent' || !empty($context['message_can_unread'][$message['id']]))
			{
				echo '
				<div class="reportlinks right">';
				if ($context['folder'] != 'sent')
					echo '
					<a href="<URL>?action=pm;sa=report;l=', $context['current_label_id'], ';pmsg=', $message['id'], '">', $txt['pm_report_to_admin'], '</a>&nbsp;';
				if (!empty($context['message_can_unread'][$message['id']]))
					echo '
					&nbsp;<a href="<URL>?action=pm;sa=markunread;pmid=', $message['id'], ';', $context['session_query'], '">', $txt['mark_unread'], '</a>';
				echo '
				</div>';
			}

			// Are there any custom profile fields for above the signature?
			if (!empty($message['member']['custom_fields']))
			{
				$shown = false;
				foreach ($message['member']['custom_fields'] as $custom)
				{
					if ($custom['placement'] != 2 || empty($custom['value']))
						continue;
					if (!$shown)
					{
						$shown = true;
						echo '
				<div class="custom_fields">
					<ul class="reset nolist">';
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
			if (!empty($message['member']['signature']) && !empty($options['show_signatures']) && $context['signature_enabled'])
				echo '
				<div class="signature">', $message['member']['signature'], '</div>';

			// Add an extra line at the bottom if we have labels enabled.
			if ($context['folder'] != 'sent' && !empty($context['currently_using_labels']) && $context['display_mode'] != 2)
			{
				echo '
				<div class="labels right">';

				// Add the label drop down box.
				if (!empty($context['currently_using_labels']))
				{
					echo '
					<select name="pm_actions[', $message['id'], ']" onchange="if ($(this).val()) this.form.submit();">
						<option data-hide>', $txt['pm_msg_label_title'], '</option>';

					// Are there any labels which can be added to this?
					if (!$message['fully_labeled'])
					{
						echo '
						<optgroup label="', westr::safe($txt['pm_msg_label_apply']), '">';

						foreach ($context['labels'] as $label)
							if (!isset($message['labels'][$label['id']]))
								echo '
							<option value="', $label['id'], '">', $label['name'], '</option>';
						echo '
						</optgroup>';
					}
					// ... and are there any that can be removed?
					if (!empty($message['labels']) && (count($message['labels']) > 1 || !isset($message['labels'][-1])))
					{
						echo '
						<optgroup label="', westr::safe($txt['pm_msg_label_remove']), '">';

						foreach ($message['labels'] as $label)
							echo '
							<option value="', $label['id'], '">', $label['name'], '</option>';
						echo '
						</optgroup>';
					}
					echo '
					</select>';
				}

				echo '
				</div>';
			}

			echo '
			</div>
			<br class="clear">
		</div>
	</div></div>';
		}

		if (empty($context['display_mode']))
			echo '
	<div class="pagesection">
		<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		<div class="floatright"><input type="submit" name="del_selected" value="', $txt['quickmod_delete_selected'], '" style="font-weight: normal" onclick="return ask(', JavaScriptEscape($txt['delete_selected_confirm']), ', e);" class="delete"></div>
	</div>';

		// Show a few buttons if we are in conversation mode and outputting the first message.
		elseif ($context['display_mode'] == 2 && isset($conversation_buttons))
			echo '
	<div class="pagesection">',
		template_button_strip($conversation_buttons), '
	</div>';

		echo '
	<br>';
	}

	// Individual messages = bottom list!
	if ($context['display_mode'] == 1)
	{
		template_subject_list();
		echo '<br>';
	}

	echo '
	<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
</form>';
}

// Just list all the personal message subjects - to make templates easier.
function template_subject_list()
{
	global $context, $options, $theme, $txt;

	echo '
	<table class="table_grid w100 cs0">
	<thead>
		<tr class="catbg">
			<th class="center" style="width: 4%">
			</th>
			<th class="left" style="width: 22%">
				<a href="<URL>?action=pm;f=', $context['folder'], ';start=', $context['start'], ';sort=date', $context['sort_by'] == 'date' && $context['sort_direction'] == 'up' ? ';desc' : '', $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', '">', $txt['date'], $context['sort_by'] == 'date' ? ' <span class="sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
			</th>
			<th class="left">
				<span class="floatright">', $txt['pm_view'], ': <select name="view" id="selPMView" onchange="location = \'<URL>?action=pm;f=', $context['folder'], ';start=', $context['start'], ';sort=', $context['sort_by'], $context['sort_direction'] == 'up' ? '' : ';desc', $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';view=\' + $(this).val();">';

	foreach ($context['view_select_types'] as $display_mode => $display_desc)
		echo '
					<option value="', $display_mode, '"', ($context['display_mode'] == $display_mode ? ' selected' : ''), '>', $display_desc, '</option>';

	echo '
				</select></span>
				<a href="<URL>?action=pm;f=', $context['folder'], ';start=', $context['start'], ';sort=subject', $context['sort_by'] == 'subject' && $context['sort_direction'] == 'up' ? ';desc' : '', $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', '">', $txt['subject'], $context['sort_by'] == 'subject' ? ' <span class="sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
			</th>
			<th class="left" style="width: 15%">
				<a href="<URL>?action=pm;f=', $context['folder'], ';start=', $context['start'], ';sort=name', $context['sort_by'] == 'name' && $context['sort_direction'] == 'up' ? ';desc' : '', $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', '">', ($context['from_or_to'] == 'from' ? $txt['from'] : $txt['to']), $context['sort_by'] == 'name' ? ' <span class="sort_' . $context['sort_direction'] . '"></span>' : '', '</a>
			</th>
			<th style="text-align: center; width: 4%">
				<input type="checkbox" onclick="invertAll(this, this.form);">
			</th>
		</tr>
	</thead>
	<tbody>';

	if (!$context['show_delete'])
		echo '
		<tr class="windowbg2">
			<td colspan="5">', $txt['msg_alert_none'], '</td>
		</tr>';
	$next_alternate = false;

	while ($message = $context['get_pmessage']('subject'))
	{
		add_js('
	currentLabels[', $message['id'], '] = {');

		if (!empty($message['labels']))
		{
			$first = true;
			foreach ($message['labels'] as $label)
			{
				add_js($first ? '' : ',', '
		"', $label['id'], '": "', $label['name'], '"');
				$first = false;
			}
		}

		add_js('
	};');

		echo '
		<tr class="', $next_alternate ? 'windowbg' : 'windowbg2', '">
			<td class="center" style="width: 4%">
				', $message['is_replied_to'] ? '<img src="' . $theme['images_url'] . '/icons/pm_replied.gif" style="margin-right: 4px" alt="' . $txt['pm_replied'] . '">' : '<img src="' . $theme['images_url'] . '/icons/pm_read.gif" style="margin-right: 4px" alt="' . $txt['pm_read'] . '">', '</td>
			<td>', $message['on_time'], '</td>
			<td>', ($context['display_mode'] != 0 && $context['current_pm'] == $message['id'] ? '<img src="' . $theme['images_url'] . '/selected.gif">' : ''), '<a href="', ($context['display_mode'] == 0 || $context['current_pm'] == $message['id'] ? '' : ('<URL>?action=pm;pmid=' . $message['id'] . ';kstart;f=' . $context['folder'] . ';start=' . $context['start'] . ';sort=' . $context['sort_by'] . ($context['sort_direction'] == 'up' ? ';' : ';desc') . ($context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : ''))), '#msg', $message['id'], '">', $message['subject'], '</a>', $message['is_unread'] ? '&nbsp;<div class="note">' . $txt['new'] . '</div>' : '', '</td>
			<td>', ($context['from_or_to'] == 'from' ? $message['member']['link'] : (empty($message['recipients']['to']) ? '' : implode(', ', $message['recipients']['to']))), '</td>
			<td class="center" style="width: 4%"><input type="checkbox" name="pms[]" id="deletelisting', $message['id'], '" value="', $message['id'], '"', $message['is_selected'] ? ' checked' : '', ' onclick="$(\'#deletedisplay', $message['id'], '\').prop(\'checked\', this.checked);"></td>
		</tr>';
		$next_alternate = !$next_alternate;
	}

	echo '
	</tbody>
	</table>
	<div class="pagesection">
		<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		<div class="floatright">&nbsp;';

	if ($context['show_delete'])
	{
		if (!empty($context['currently_using_labels']) && $context['folder'] != 'sent')
			echo '
				<select name="pm_action" onchange="if ($(this).val()) this.form.submit();" onfocus="loadLabelChoices();">
					<option data-hide>', $txt['pm_sel_label_title'], '</option>
				</select>';

		echo '
				<input type="submit" name="del_selected" value="', $txt['quickmod_delete_selected'], '" onclick="return ask(', JavaScriptEscape($txt['delete_selected_confirm']), ', e);" class="delete">';
	}

	echo '
				</div>
	</div>';
}

function template_search()
{
	global $context, $theme, $options, $txt;

	add_js_file('scripts/pm.js');

	if (!empty($context['search_errors']))
		echo '
	<div class="errorbox">
		', implode('<br>', $context['search_errors']['messages']), '
	</div>';

	echo '
	<form action="<URL>?action=pm;sa=search2" method="post" accept-charset="UTF-8" name="searchform" id="searchform">
		<we:cat>
			', $txt['pm_search_title'], '
		</we:cat>
		<div class="windowbg wrc">
			<fieldset id="simple_search">
				<div id="search_term_input">
					<strong>', $txt['search_for'], ':</strong>
					<input type="search" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' size="40" class="search">
					<select name="searchtype">
						<option value="1"', empty($context['search_params']['searchtype']) ? ' selected' : '', '>', $txt['all_words'], '</option>
						<option value="2"', !empty($context['search_params']['searchtype']) ? ' selected' : '', '>', $txt['any_words'], '</option>
					</select>
				</div>
			</fieldset>
		</div>
		<div class="windowbg2 wrc">
			<h6>
				', $txt['set_parameters'], '
			</h6>
			<fieldset id="advanced_search">
				<dl id="search_options">
					<dt>', $txt['by_user'], ':</dt>
					<dd><input name="userspec" value="', empty($context['search_params']['userspec']) ? '*' : $context['search_params']['userspec'], '" size="40"></dd>
					<dt>', $txt['search_order'], ':</dt>
					<dd>
						<select name="sort">
							<option value="relevance|desc">', $txt['search_orderby_relevant_first'], '</option>
							<option value="id_pm|desc">', $txt['search_orderby_recent_first'], '</option>
							<option value="id_pm|asc">', $txt['search_orderby_old_first'], '</option>
						</select>
					</dd>
					<dt class="options">', $txt['search_options'], ':</dt>
					<dd class="options">
						<label><input type="checkbox" name="show_complete" id="show_complete" value="1"', !empty($context['search_params']['show_complete']) ? ' checked' : '', '> ', $txt['pm_search_show_complete'], '</label><br>
						<label><input type="checkbox" name="subject_only" id="subject_only" value="1"', !empty($context['search_params']['subject_only']) ? ' checked' : '', '> ', $txt['pm_search_subject_only'], '</label>
					</dd>
					<dt class="between">', $txt['pm_search_post_age'], ':</dt>
					<dd>', $txt['pm_search_between'], ' <input type="number" name="minage" value="', empty($context['search_params']['minage']) ? '0' : $context['search_params']['minage'], '" size="5" maxlength="4" min="0" max="9999">&nbsp;', $txt['pm_search_between_and'], '&nbsp;<input type="number" name="maxage" value="', empty($context['search_params']['maxage']) ? '9999' : $context['search_params']['maxage'], '" size="5" maxlength="4" min="0" max="9999"> ', $txt['pm_search_between_days'], '</dd>
				</dl>', !$context['currently_using_labels'] ? '
				<hr>
				<input type="submit" value="' . $txt['pm_search_go'] . '" class="submit floatright">' : '', '
			</fieldset>
		</div>';

		// Do we have some labels setup? If so offer to search by them!
	if ($context['currently_using_labels'])
	{
		echo '
		<fieldset class="labels">
			<div class="roundframe">
				<we:title2>
					<a href="#" onclick="expandCollapseLabels(); return false;"><div class="foldable" id="expandLabelsIcon"></div></a> <a href="#" onclick="expandCollapseLabels(); return false;">', $txt['pm_search_choose_label'], '</a>
				</we:title2>
				<ul id="searchLabelsExpand" class="reset', $context['check_all'] ? ' hide' : '', '">';

		foreach ($context['search_labels'] as $label)
			echo '
					<li>
						<label><input type="checkbox" id="searchlabel_', $label['id'], '" name="searchlabel[', $label['id'], ']" value="', $label['id'], '"', $label['checked'] ? ' checked' : '', '>
						', $label['name'], '</label>
					</li>';

		echo '
				</ul>
				<p>
					<span class="floatleft"><label><input type="checkbox" name="all" id="check_all" value=""', $context['check_all'] ? ' checked' : '', ' onclick="invertAll(this, this.form, \'searchlabel\');"> <em>', $txt['check_all'], '</em></label></span>
					<input type="submit" value="', $txt['pm_search_go'], '" class="submit floatright">
				</p>
			</div>
		</fieldset>';
	}

	echo '
	</form>';
}

function template_search_results()
{
	global $context, $theme, $options, $txt;

	echo '
		<we:cat>
			', $txt['pm_search_results'], '
		</we:cat>
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';

	// complete results ?
	if (empty($context['search_params']['show_complete']) && !empty($context['personal_messages']))
		echo '
	<table class="table_grid w100 cs0">
	<thead>
		<tr class="catbg left">
			<th style="width: 30%">', $txt['date'], '</th>
			<th class="w50">', $txt['subject'], '</th>
			<th style="width: 20%">', $txt['from'], '</th>
		</tr>
	</thead>
	<tbody>';

	$alternate = true;
	// Print each message out...
	foreach ($context['personal_messages'] as $message)
	{
		// We showing it all?
		if (!empty($context['search_params']['show_complete']))
		{
			echo '
		<div class="topic">
			<div class="windowbg', $alternate ? '2' : '', ' wrc core_posts pm">
				<div class="counter">', $message['counter'], '</div>
				<div class="topic_details">
					<span class="floatright">', $message['on_time'], '</span>
					<h3><a href="', $message['href'], '">', $message['subject'], '</a></h3>
					', $txt['from'], ': <strong>', $message['member']['link'], '</strong>, ', $txt['to'], ': <strong>';

			// Show the recipients.
			// !!! This doesn't deal with the sent item searching quite right for bcc.
			if (!empty($message['recipients']['to']))
				echo implode(', ', $message['recipients']['to']);
			// Otherwise, we're just going to say "some people"...
			elseif ($context['folder'] != 'sent')
				echo '(', $txt['pm_undisclosed_recipients'], ')';

			echo '</strong>
				</div>
				<div class="list_posts">
					', $message['body'], '
					<p class="pm_reply right smalltext">';

			if ($context['can_send_pm'])
			{
				// You can only reply if they are not a guest...
				if (!$message['member']['is_guest'])
					echo '
						<a href="<URL>?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';quote;u=', $context['folder'] == 'sent' ? '' : $message['member']['id'], '" class="quote_button">', $txt['quote'], '</a>', $context['menu_separator'], '
						<a href="<URL>?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';u=', $message['member']['id'], '" class="reply_button">', $txt['reply'], '</a>', $context['menu_separator'];
				// This is for "forwarding" - even if the member is gone.
				else
					echo '
						<a href="<URL>?action=pm;sa=send;f=', $context['folder'], $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';pmsg=', $message['id'], ';quote" class="quote_button">', $txt['quote'], '</a>', $context['menu_separator'];
			}

			echo '
					</p>
				</div>
			</div>';
		}
		// Otherwise just a simple list!
		else
		{
			// !!! No context at all of the search?
			echo '
			<tr class="windowbg', $alternate ? '2' : '', ' top">
				<td>', $message['on_time'], '</td>
				<td>', $message['link'], '</td>
				<td>', $message['member']['link'], '</td>
			</tr>';
		}

		$alternate = !$alternate;
	}

	// Finish off the page...
	if (empty($context['search_params']['show_complete']) && !empty($context['personal_messages']))
		echo '
		</tbody>
		</table>';

	// No results?
	if (empty($context['personal_messages']))
		echo '
		<div class="windowbg wrc">
			<p class="center">', $txt['pm_search_none_found'], '</p>
		</div>';

	echo '
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';
}

function template_send()
{
	global $context, $theme, $options, $txt;

	// Show which messages were sent successfully and which failed.
	if (!empty($context['send_log']))
	{
		echo '
	<we:cat>
		', $txt['pm_send_report'], '
	</we:cat>
	<div class="windowbg wrc">';

		if (!empty($context['send_log']['sent']))
			foreach ($context['send_log']['sent'] as $log_entry)
				echo '
		<span class="error">', $log_entry, '</span><br>';

		if (!empty($context['send_log']['failed']))
			foreach ($context['send_log']['failed'] as $log_entry)
				echo '
		<span class="error">', $log_entry, '</span><br>';

		echo '
	</div>
	<br>';
	}

	// Show the preview of the personal message.
	if (isset($context['preview_message']))
		echo '
	<we:cat>
		', $context['preview_subject'], '
	</we:cat>
	<div class="windowbg wrc">
		', $context['preview_message'], '
	</div>
	<br>';

	// Main message editing box.
	echo '
	<we:cat>
		<img src="', $theme['images_url'], '/icons/im_newmsg.gif" alt="', $txt['new_message'], '" title="', $txt['new_message'], '">
		', $txt['new_message'], '
	</we:cat>';

	echo '
	<form action="<URL>?action=pm;sa=send2" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" class="flow_hidden" onsubmit="submitonce(); weSaveEntities(\'postmodify\', ', $context['postbox']->saveEntityFields(), ');">
		<div class="roundframe clear">';

	// If there were errors for sending the PM, show them.
	if (!empty($context['post_error']['messages']))
	{
		echo '
			<div class="errorbox">
				<h3>', $txt['error_while_submitting'], '</h3>
				<ul class="error">';

		foreach ($context['post_error']['messages'] as $error)
			echo '
					<li>', $error, '</li>';

		echo '
				</ul>
			</div>';
	}

	if (!empty($context['buddy_list']))
		template_send_contacts();

	echo '
			<dl id="post_header">';

	// To and bcc. Include a button to search for members.
	echo '
				<dt>
					<span', (isset($context['post_error']['no_to']) || isset($context['post_error']['bad_to']) ? ' class="error"' : ''), '>', $txt['pm_to'], ':</span>
				</dt>';

	// Autosuggest will be added by the JavaScript later on.
	echo '
				<dd id="pm_to" class="clear_right">
					<input name="to" id="to_control" value="', $context['to_value'], '" tabindex="', $context['tabindex']++, '" size="40" style="width: 130px">';

	// A link to add BCC
	echo '
					<span class="smalltext hide" id="bcc_link_container">&nbsp;<a href="#" id="bcc_link">', $txt['make_bcc'], '</a> <a href="<URL>?action=help;in=pm_bcc" onclick="return reqWin(this);">(?)</a></span>
				</dd>';

	// This BCC row will be hidden by default if JavaScript is enabled.
	echo '
				<dt class="clear_left" id="bcc_div">
					<span', (isset($context['post_error']['no_to']) || isset($context['post_error']['bad_bcc']) ? ' class="error"' : ''), '>', $txt['pm_bcc'], ':</span>
				</dt>
				<dd id="bcc_div2">
					<input name="bcc" id="bcc_control" value="', $context['bcc_value'], '" tabindex="', $context['tabindex']++, '" size="40" style="width: 130px">
				</dd>';

	// The subject of the PM.
	echo '
				<dt class="clear_left">
					<span', (isset($context['post_error']['no_subject']) ? ' class="error"' : ''), '>', $txt['subject'], ':</span>
				</dt>
				<dd id="pm_subject">
					<input name="subject" value="', $context['subject'], '" tabindex="', $context['tabindex']++, '" size="60" maxlength="60">
				</dd>
			</dl>
			<hr class="clearleft">';

	// Show BBC buttons, smileys and textbox.
	echo $context['postbox']->outputEditor();

	// Require an image to be typed to save spamming?
	if ($context['require_verification'])
		echo '
			<div class="post_verification">
				<strong>', $txt['pm_visual_verification_label'], ':</strong>
				', template_control_verification($context['visual_verification_id'], 'all'), '
			</div>';

	// Send, Preview buttons.
	echo '
			<div class="postbuttons">', $context['postbox']->outputButtons(), '
			</div>
			<div id="shortcuts">
				<span class="smalltext">', $txt['shortcuts'], '</span>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">
			<input type="hidden" name="replied_to" value="', !empty($context['quoted_message']['id']) ? $context['quoted_message']['id'] : 0, '">
			<input type="hidden" name="pm_head" value="', !empty($context['quoted_message']['pm_head']) ? $context['quoted_message']['pm_head'] : 0, '">
			<input type="hidden" name="f" value="', isset($context['folder']) ? $context['folder'] : '', '">
			<input type="hidden" name="l" value="', isset($context['current_label_id']) ? $context['current_label_id'] : -1, '">
			<br class="clear">
		</div>
	</form>';

	// Show the message you're replying to.
	if ($context['reply'])
		echo '
	<br><br>
	<we:title>
		', $txt['subject'], ': ', $context['quoted_message']['subject'], '
	</we:title>
	<div class="windowbg2 wrc core_posts pm clear clearfix">
		<span class="smalltext floatright">', $context['quoted_message']['on_time'], '</span>
		<strong>', $txt['from'], ': ', $context['quoted_message']['member']['name'], '</strong>
		<hr>
		', $context['quoted_message']['body'], '
	</div>';

	add_js_file(array(
		'scripts/pm.js',
		'scripts/suggest.js'
	));

	add_js('
	new weSendPM({
		', min_chars(), ',
		sToControlId: \'to_control\',
		aToRecipients: {');

	$j = count($context['recipients']['to']) - 1;
	foreach ($context['recipients']['to'] as $i => $member)
		add_js('
			', (int) $member['id'], ': ', JavaScriptEscape($member['name']), $i == $j ? '' : ',');

	add_js('
		},
		aBccRecipients: {');

	$j = count($context['recipients']['bcc']) - 1;
	foreach ($context['recipients']['bcc'] as $i => $member)
		add_js('
			', (int) $member['id'], ': ', JavaScriptEscape($member['name']), $i == $j ? '' : ',');

	add_js('
		},
		sBccControlId: \'bcc_control\',
		sBccDivId: \'bcc_div\',
		sBccDivId2: \'bcc_div2\',
		sBccLinkId: \'bcc_link\',
		sBccLinkContainerId: \'bcc_link_container\',
		bBccShowByDefault: ', empty($context['recipients']['bcc']) && empty($context['bcc_value']) ? 'false' : 'true', ',
		sContactList: \'', !empty($context['buddy_list']) ? 'contactlist' : '', '\'
	});');
}

function template_send_contacts()
{
	global $context, $txt;

	echo '
		<we:block class="windowbg floatright" header="', westr::safe($txt['pm_contact_list']), '" id="contactlist">
			<div>
				<table>';

	foreach ($context['buddy_list'] as $id => $name)
	{
		echo '
				<tr data-uid="', $id, '" data-name="', $name, '">
					<td><a href="<URL>?action=profile;u=', $id, '">', $name, '</a></td>
				</tr>';
	}

	echo '
				</table>
			</div>
		</we:block>';
}

// This template asks the user whether they wish to empty out their folder/messages.
function template_ask_delete()
{
	global $context, $theme, $options, $txt;

	echo '
		<we:cat>
			', ($context['delete_all'] ? $txt['delete_message'] : $txt['delete_all']), '
		</we:cat>
		<div class="windowbg wrc">
			<p>', $txt['delete_all_confirm'], '</p><br>
			<strong><a href="<URL>?action=pm;sa=removeall2;f=', $context['folder'], ';', $context['current_label_id'] != -1 ? ';l=' . $context['current_label_id'] : '', ';', $context['session_query'], '">', $txt['yes'], '</a> - <a href="javascript:history.go(-1);">', $txt['no'], '</a></strong>
		</div>';
}

// This template asks the user what messages they want to prune.
function template_prune()
{
	global $context, $theme, $options, $txt;

	echo '
	<form action="<URL>?action=pm;sa=prune" method="post" accept-charset="UTF-8" onsubmit="return ask(', JavaScriptEscape($txt['pm_prune_warning']), ', e);">
		<we:cat>
			', $txt['pm_prune'], '
		</we:cat>
		<div class="windowbg wrc">
			<p>', $txt['pm_prune_desc1'], ' <input name="age" size="3" value="14"> ', $txt['pm_prune_desc2'], '</p>
			<div class="right">
				<input type="submit" value="', $txt['delete'], '" class="delete">
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

// Here we allow the user to setup labels, remove labels and change rules for labels (i.e, do quite a bit)
function template_labels()
{
	global $context, $theme, $options, $txt;

	echo '
	<form action="<URL>?action=pm;sa=manlabels" method="post" accept-charset="UTF-8">
		<we:cat>
			', $txt['pm_manage_labels'], '
		</we:cat>
		<p class="description">
			', $txt['pm_labels_desc'], '
		</p>

		<table class="table_grid w100 cs0">
		<thead>
			<tr class="catbg">
				<th class="left">
					', $txt['pm_label_name'], '
				</th>
				<th class="center" style="width: 4%">';

	if (count($context['labels']) > 2)
		echo '
					<input type="checkbox" onclick="invertAll(this, this.form);">';

	echo '
				</th>
			</tr>
		</thead>
		<tbody>';

	if (count($context['labels']) < 2)
		echo '
			<tr class="windowbg2 center">
				<td colspan="2">', $txt['pm_labels_no_exist'], '</td>
			</tr>';
	else
	{
		$alternate = true;
		foreach ($context['labels'] as $label)
		{
			if ($label['id'] == -1)
				continue;

			echo '
			<tr class="', $alternate ? 'windowbg2' : 'windowbg', '">
				<td>
					<input name="label_name[', $label['id'], ']" value="', $label['name'], '" size="30" maxlength="30">
				</td>
				<td style="width: 4%" class="center"><input type="checkbox" name="delete_label[', $label['id'], ']"></td>
			</tr>';

			$alternate = !$alternate;
		}
	}
	echo '
		</tbody>
		</table>';

	if (!count($context['labels']) < 2)
		echo '
		<div class="padding right">
			<input type="submit" name="save" value="', $txt['save'], '" class="save">
			<input type="submit" name="delete" value="', $txt['quickmod_delete_selected'], '" onclick="return ask(', JavaScriptEscape($txt['pm_labels_delete']), ', e);" class="delete">
		</div>';

	echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>

	<form action="<URL>?action=pm;sa=manlabels" method="post" accept-charset="UTF-8" style="margin-top: 1ex">
		<we:cat>
			', $txt['pm_label_add_new'], '
		</we:cat>
		<div class="windowbg wrc">
			<dl class="settings">
				<dt>
					<strong><label for="add_label">', $txt['pm_label_name'], '</label>:</strong>
				</dt>
				<dd>
					<input id="add_label" name="label" value="" size="30" maxlength="30">
				</dd>
			</dl>
			<div class="right">
				<input type="submit" name="add" value="', $txt['pm_label_add_new'], '" class="new">
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

// Template for reporting a personal message.
function template_report_message()
{
	global $context, $theme, $options, $txt;

	echo '
	<form action="<URL>?action=pm;sa=report;l=', $context['current_label_id'], '" method="post" accept-charset="UTF-8">
		<input type="hidden" name="pmsg" value="', $context['pm_id'], '">
		<we:cat>
			', $txt['pm_report_title'], '
		</we:cat>
		<p class="description">
			', $txt['pm_report_desc'], '
		</p>

		<div class="windowbg wrc">
			<dl class="settings">';

	// If there is more than one admin on the forum, allow the user to choose the one they want to direct to.
	// !!! Why?
	// !!! - Because!!
	if ($context['admin_count'] > 1)
	{
		echo '
				<dt>
					<strong>', $txt['pm_report_admins'], ':</strong>
				</dt>
				<dd>
					<select name="id_admin">
						<option value="0">', $txt['pm_report_all_admins'], '</option>';

		foreach ($context['admins'] as $id => $name)
			echo '
						<option value="', $id, '">', $name, '</option>';

		echo '
					</select>
				</dd>';
	}

	echo '
				<dt>
					<strong>', $txt['pm_report_reason'], ':</strong>
				</dt>
				<dd>
					<textarea name="reason" rows="4" cols="70" style="', we::is('ie8') ? 'width: 635px; max-width: 80%; min-width: 80%' : 'width: 80%', '"></textarea>
				</dd>
			</dl>
			<div class="right">
				<input type="submit" name="report" value="', $txt['pm_report_message'], '" class="submit">
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

// Little template just to say "Yep, it's been submitted"
function template_report_message_complete()
{
	global $context, $theme, $options, $txt;

	echo '
		<we:cat>
			', $txt['pm_report_title'], '
		</we:cat>
		<div class="windowbg wrc">
			<p>', $txt['pm_report_done'], '</p>
			<a href="<URL>?action=pm;l=', $context['current_label_id'], '">', $txt['pm_report_return'], '</a>
		</div>';
}

// Manage rules.
function template_rules()
{
	global $context, $theme, $options, $txt;

	echo '
	<form action="<URL>?action=pm;sa=manrules" method="post" accept-charset="UTF-8" name="manRules" id="manrules">
		<we:cat>
			', $txt['pm_manage_rules'], '
		</we:cat>
		<p class="description">
			', $txt['pm_manage_rules_desc'], '
		</p>

		<table class="table_grid w100 cs0">
		<thead>
			<tr class="catbg">
				<th class="left">
					', $txt['pm_rule_title'], '
				</th>
				<th style="width: 4%" class="center">';

	if (!empty($context['rules']))
		echo '
					<input type="checkbox" onclick="invertAll(this, this.form);">';

	echo '
				</th>
			</tr>
		</thead>
		<tbody>';

	if (empty($context['rules']))
		echo '
			<tr class="windowbg2">
				<td colspan="2" class="center">
					', $txt['pm_rules_none'], '
				</td>
			</tr>';

	$alternate = false;
	foreach ($context['rules'] as $rule)
	{
		echo '
			<tr class="', $alternate ? 'windowbg' : 'windowbg2', '">
				<td>
					<a href="<URL>?action=pm;sa=manrules;add;rid=', $rule['id'], '">', $rule['name'], '</a>
				</td>
				<td style="width: 4%" class="center">
					<input type="checkbox" name="delrule[', $rule['id'], ']">
				</td>
			</tr>';
		$alternate = !$alternate;
	}

	echo '
		</tbody>
		</table>
		<div class="right">
			<input type="submit" name="add" value="', $txt['pm_add_rule'], '" class="new">';

	if (!empty($context['rules']))
		echo '
			<input type="submit" name="apply" value="', $txt['pm_apply_rules'], '" onclick="return ask(', JavaScriptEscape($txt['pm_js_apply_rules_confirm']), ', e);" class="submit">';

	if (!empty($context['rules']))
		echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="submit" name="delselected" value="', $txt['pm_delete_selected_rule'], '" onclick="return ask(', JavaScriptEscape($txt['pm_js_delete_rule_confirm']), ', e);" class="delete">';

	echo '
		</div>
	</form>';

}

// Template for adding/editing a rule.
function template_add_rule()
{
	global $context, $theme, $options, $txt;

	add_js('
	var criteriaNum = 0, actionNum = 0, groups = [], labels = [];');

	foreach ($context['groups'] as $id => $title)
		add_js('
	groups[', $id, '] = "', addslashes($title), '";');

	foreach ($context['labels'] as $label)
		if ($label['id'] != -1)
			add_js('
	labels[', ($label['id'] + 1), '] = "', addslashes($label['name']), '";');

	add_js('
	function addCriteriaOption()
	{
		if (criteriaNum == 0)
			for (var i = 0; i < document.forms.addrule.elements.length; i++)
				if (document.forms.addrule.elements[i].id.slice(0, 8) == "ruletype")
					criteriaNum++;

		criteriaNum++;

		$("#criteriaAddHere").append(\'<br><select name="ruletype[\' + criteriaNum + \']" id="ruletype\' + criteriaNum + \'" onchange="updateRuleDef(\' + criteriaNum + \'); rebuildRuleDesc();"><option data-hide>', addslashes($txt['pm_rule_criteria_pick']), ':<\' + \'/option><option value="mid">', addslashes($txt['pm_rule_mid']), '<\' + \'/option><option value="gid">', addslashes($txt['pm_rule_gid']), '<\' + \'/option><option value="sub">', addslashes($txt['pm_rule_sub']), '<\' + \'/option><option value="msg">', addslashes($txt['pm_rule_msg']), '<\' + \'/option><option value="bud">', addslashes($txt['pm_rule_bud']), '<\' + \'/option><\' + \'/select>&nbsp;<span id="defdiv\' + criteriaNum + \'" class="hide"><input name="ruledef[\' + criteriaNum + \']" id="ruledef\' + criteriaNum + \'" onkeyup="rebuildRuleDesc();" value=""><\' + \'/span><span id="defseldiv\' + criteriaNum + \'" class="hide"><select name="ruledefgroup[\' + criteriaNum + \']" id="ruledefgroup\' + criteriaNum + \'" onchange="rebuildRuleDesc();"><option value="">', addslashes($txt['pm_rule_sel_group']), '<\' + \'/option>');

	foreach ($context['groups'] as $id => $group)
		add_js('<option value="' . $id . '">' . strtr($group, array("'" => "\'")) . '<\' + \'/option>');

	add_js('<\' + \'/select><\' + \'/span>\').find("select").sb();
	}

	function addActionOption()
	{
		if (actionNum == 0)
			for (var i = 0; i < document.forms.addrule.elements.length; i++)
				if (document.forms.addrule.elements[i].id.slice(0, 7) == "acttype")
					actionNum++;

		actionNum++;

		$("#actionAddHere").append(\'<br><select name="acttype[\' + actionNum + \']" id="acttype\' + actionNum + \'" onchange="updateActionDef(\' + actionNum + \'); rebuildRuleDesc();"><option data-hide>', addslashes($txt['pm_rule_sel_action']), ':<\' + \'/option><option value="lab">', addslashes($txt['pm_rule_label']), '<\' + \'/option><option value="del">', addslashes($txt['pm_rule_delete']), '<\' + \'/option><\' + \'/select>&nbsp;<span id="labdiv\' + actionNum + \'" class="hide"><select name="labdef[\' + actionNum + \']" id="labdef\' + actionNum + \'" onchange="rebuildRuleDesc();"><option data-hide>', addslashes($txt['pm_rule_sel_label']), '<\' + \'/option>');

	foreach ($context['labels'] as $label)
		if ($label['id'] != -1)
			add_js('<option value="' . ($label['id'] + 1) . '">' . addslashes($label['name']) . '<\' + \'/option>');

	add_js('<\' + \'/select><\' + \'/span>\').find("select").sb();
	}

	function updateRuleDef(optNum)
	{
		var va = $("#ruletype" + optNum).val();
		if (va == "gid")
		{
			$("#defdiv" + optNum).hide();
			$("#defseldiv" + optNum).show();
		}
		else if (va == "bud" || va == "")
		{
			$("#defdiv" + optNum).hide();
			$("#defseldiv" + optNum).hide();
		}
		else
		{
			$("#defdiv" + optNum).show();
			$("#defseldiv" + optNum).hide();
		}
	}

	function updateActionDef(optNum)
	{
		$("#labdiv" + optNum).toggle($("#acttype" + optNum).val() == "lab");
	}

	// Rebuild the rule description!
	function rebuildRuleDesc()
	{
		// Start with nothing.
		var text = "";
		var joinText = "";
		var actionText = "";
		var hadBuddy = false;
		var foundCriteria = false;
		var foundAction = false;
		var curNum, curVal, curDef;

		for (var i = 0; i < document.forms.addrule.elements.length; i++)
		{
			if (document.forms.addrule.elements[i].id.slice(0, 8) == "ruletype")
			{
				if (foundCriteria)
					joinText = $("#logic").val() == \'and\' ? ', JavaScriptEscape(' ' . $txt['pm_readable_and'] . ' '), ' : ', JavaScriptEscape(' ' . $txt['pm_readable_or'] . ' '), ';
				else
					joinText = \'\';
				foundCriteria = true;

				curNum = document.forms.addrule.elements[i].id.match(/\d+/);
				curVal = document.forms.addrule.elements[i].value;
				if (curVal == "gid")
					curDef = $("#ruledefgroup" + curNum).val().php_htmlspecialchars();
				else if (curVal != "bud")
					curDef = $("#ruledef" + curNum).val().php_htmlspecialchars();
				else
					curDef = "";

				// What type of test is this?
				if (curVal == "mid" && curDef)
					text += joinText + ', JavaScriptEscape($txt['pm_readable_member']), '.replace("{MEMBER}", curDef);
				else if (curVal == "gid" && curDef && groups[curDef])
					text += joinText + ', JavaScriptEscape($txt['pm_readable_group']), '.replace("{GROUP}", groups[curDef]);
				else if (curVal == "sub" && curDef)
					text += joinText + ', JavaScriptEscape($txt['pm_readable_subject']), '.replace("{SUBJECT}", curDef);
				else if (curVal == "msg" && curDef)
					text += joinText + ', JavaScriptEscape($txt['pm_readable_body']), '.replace("{BODY}", curDef);
				else if (curVal == "bud" && !hadBuddy)
				{
					text += joinText + ', JavaScriptEscape($txt['pm_readable_buddy']), ';
					hadBuddy = true;
				}
			}
			if (document.forms.addrule.elements[i].id.slice(0, 7) == "acttype")
			{
				if (foundAction)
					joinText = ', JavaScriptEscape(' ' . $txt['pm_readable_and'] . ' '), ';
				else
					joinText = "";
				foundAction = true;

				curNum = document.forms.addrule.elements[i].id.match(/\d+/);
				curVal = document.forms.addrule.elements[i].value;
				if (curVal == "lab")
					curDef = $("#labdef" + curNum).val().php_htmlspecialchars();
				else
					curDef = "";

				// Now pick the actions.
				if (curVal == "lab" && curDef && labels[curDef])
					actionText += joinText + ', JavaScriptEscape($txt['pm_readable_label']), '.replace("{LABEL}", labels[curDef]);
				else if (curVal == "del")
					actionText += joinText + ', JavaScriptEscape($txt['pm_readable_delete']), ';
			}
		}

		// If still nothing make it default!
		if (text == "" || !foundCriteria)
			text = "', $txt['pm_rule_not_defined'], '";
		else
		{
			if (actionText != "")
				text += ', JavaScriptEscape(' ' . $txt['pm_readable_then'] . ' '), ' + actionText;
			text = ', JavaScriptEscape($txt['pm_readable_start']), ' + text + ', JavaScriptEscape($txt['pm_readable_end']), ';
		}

		// Set the actual HTML!
		$("#ruletext").html(text);
	}');

	echo '
	<form action="<URL>?action=pm;sa=manrules;save;rid=', $context['rid'], '" method="post" accept-charset="UTF-8" name="addrule" id="addrule" class="flow_hidden">
		<we:cat>
			', $context['rid'] == 0 ? $txt['pm_add_rule'] : $txt['pm_edit_rule'], '
		</we:cat>
		<div class="windowbg wrc">
			<dl class="addrules">
				<dt class="floatleft">
					<strong>', $txt['pm_rule_name'], ':</strong>
					<dfn>', $txt['pm_rule_name_desc'], '</dfn>
				</dt>
				<dd class="floatleft">
					<input name="rule_name" value="', empty($context['rule']['name']) ? $txt['pm_rule_name_default'] : $context['rule']['name'], '" size="50">
				</dd>
			</dl>
			<fieldset>
				<legend>', $txt['pm_rule_criteria'], '</legend>';

	// Add a dummy criteria to allow expansion for none js users.
	$context['rule']['criteria'][] = array('t' => '', 'v' => '');

	// Print each criteria.
	$isFirst = true;
	foreach ($context['rule']['criteria'] as $k => $criteria)
	{
		if (!$isFirst && $criteria['t'] == '')
			echo '<div id="removejs1">';
		elseif (!$isFirst)
			echo '<br>';

		echo '
				<select name="ruletype[', $k, ']" id="ruletype', $k, '" onchange="updateRuleDef(', $k, '); rebuildRuleDesc();">
					<option data-hide>', $txt['pm_rule_criteria_pick'], ':</option>
					<option value="mid"', $criteria['t'] == 'mid' ? ' selected' : '', '>', $txt['pm_rule_mid'], '</option>
					<option value="gid"', $criteria['t'] == 'gid' ? ' selected' : '', '>', $txt['pm_rule_gid'], '</option>
					<option value="sub"', $criteria['t'] == 'sub' ? ' selected' : '', '>', $txt['pm_rule_sub'], '</option>
					<option value="msg"', $criteria['t'] == 'msg' ? ' selected' : '', '>', $txt['pm_rule_msg'], '</option>
					<option value="bud"', $criteria['t'] == 'bud' ? ' selected' : '', '>', $txt['pm_rule_bud'], '</option>
				</select>
				<span id="defdiv', $k, '"', $criteria['t'] != 'gid' && $criteria['t'] != 'bud' ? '' : ' class="hide"', '>
					<input name="ruledef[', $k, ']" id="ruledef', $k, '" onkeyup="rebuildRuleDesc();" value="', in_array($criteria['t'], array('mid', 'sub', 'msg')) ? $criteria['v'] : '', '">
				</span>
				<span id="defseldiv', $k, '"', $criteria['t'] == 'gid' ? '' : ' class="hide"', '>
					<select name="ruledefgroup[', $k, ']" id="ruledefgroup', $k, '" onchange="rebuildRuleDesc();">
						<option data-hide>', $txt['pm_rule_sel_group'], '</option>';

		foreach ($context['groups'] as $id => $group)
			echo '
						<option value="', $id, '"', $criteria['t'] == 'gid' && $criteria['v'] == $id ? ' selected' : '', '>', $group, '</option>';
		echo '
					</select>
				</span>';

		// If this is the dummy we add a means to hide for non js users.
		if ($isFirst)
			$isFirst = false;
		elseif ($criteria['t'] == '')
			echo '</div>';
	}

	echo '
				<span id="criteriaAddHere"></span><br>
				<a href="#" onclick="addCriteriaOption(); return false;" id="addjs1" class="hide">(', $txt['pm_rule_criteria_add'], ')</a>
				<br><br>
				', $txt['pm_rule_logic'], ':
				<select name="rule_logic" id="logic" onchange="rebuildRuleDesc();">
					<option value="and"', $context['rule']['logic'] == 'and' ? ' selected' : '', '>', $txt['pm_rule_logic_and'], '</option>
					<option value="or"', $context['rule']['logic'] == 'or' ? ' selected' : '', '>', $txt['pm_rule_logic_or'], '</option>
				</select>
			</fieldset>
			<fieldset>
				<legend>', $txt['pm_rule_actions'], '</legend>';

	// As with criteria - add a dummy action for "expansion".
	$context['rule']['actions'][] = array('t' => '', 'v' => '');

	// Print each action.
	$isFirst = true;
	foreach ($context['rule']['actions'] as $k => $action)
	{
		if (!$isFirst && $action['t'] == '')
			echo '<div id="removejs2">';
		elseif (!$isFirst)
			echo '<br>';

		echo '
				<select name="acttype[', $k, ']" id="acttype', $k, '" onchange="updateActionDef(', $k, '); rebuildRuleDesc();">
					<option data-hide>', $txt['pm_rule_sel_action'], ':</option>
					<option value="lab"', $action['t'] == 'lab' ? ' selected' : '', '>', $txt['pm_rule_label'], '</option>
					<option value="del"', $action['t'] == 'del' ? ' selected' : '', '>', $txt['pm_rule_delete'], '</option>
				</select>
				<span id="labdiv', $k, '">
					<select name="labdef[', $k, ']" id="labdef', $k, '" onchange="rebuildRuleDesc();">
						<option data-hide>', $txt['pm_rule_sel_label'], '</option>';

		foreach ($context['labels'] as $label)
			if ($label['id'] != -1)
				echo '
						<option value="', ($label['id'] + 1), '"', $action['t'] == 'lab' && $action['v'] == $label['id'] ? ' selected' : '', '>', $label['name'], '</option>';

		echo '
					</select>
				</span>';

		if ($isFirst)
			$isFirst = false;
		elseif ($action['t'] == '')
			echo '</div>';
	}

	echo '
				<span id="actionAddHere"></span><br>
				<a href="#" onclick="addActionOption(); return false;" id="addjs2" class="hide">(', $txt['pm_rule_add_action'], ')</a>
			</fieldset>
		</div>
		<br class="clear">

		<we:title>
			', $txt['pm_rule_description'], '
		</we:title>
		<div class="information">
			<div id="ruletext">', $txt['pm_rule_js_disabled'], '</div>
		</div>
		<div class="right">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="submit" name="save" value="', $txt['pm_rule_save'], '" class="save">
		</div>
	</form>';

	// Now setup all the bits!
	foreach ($context['rule']['criteria'] as $k => $c)
		add_js('
	updateRuleDef(', $k, ');');

	foreach ($context['rule']['actions'] as $k => $c)
		add_js('
	updateActionDef(', $k, ');');

	add_js('
	rebuildRuleDesc();');

	// If this isn't a new rule and we have JS enabled remove the JS compatibility stuff.
	if ($context['rid'])
		add_js('
	$("#removejs1").hide();
	$("#removejs2").hide();');

	add_js('
	$("#addjs1").show();
	$("#addjs2").show();');
}

// For displaying the saved drafts.
function template_pm_drafts()
{
	global $context, $theme, $options, $settings, $txt;

	echo '
		<we:cat>
			<img src="', $theme['images_url'], '/icons/im_newmsg.gif">
			', $txt['showDrafts'], '
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
			<div class="windowbg', $post['alternate'] == 0 ? '2' : '', ' wrc core_posts pm">
				<div class="counter">', $post['counter'], '</div>
				<div class="topic_details">
					<h5><strong>', $post['subject'], '</strong></h5>
					<div class="smalltext"><strong>', $txt['pm_to'], ':</strong> ', empty($post['recipients']['to']) ? '<span class="alert">' . $txt['no_recipients'] . '</span>' : implode(', ', $post['recipients']['to']), (empty($post['recipients']['bcc']) ? '' : ', <strong>' . $txt['pm_bcc'] . ':</strong> ' . implode(', ', $post['recipients']['bcc'])), '
					', $post['on_time'], '</div>
				</div>
				<div class="list_posts">
					', $post['body'], '
				</div>';

		echo '
				<div class="actionbar">
					<ul class="actions">
						<li><a href="<URL>?action=pm;sa=send;draft_id=', $post['id'], empty($post['pmsg']) ? '' : ';pmsg=' . $post['pmsg'], '" class="edit_button">', $txt['edit_draft'], '</a></li>
						<li><a href="<URL>?action=pm;sa=showdrafts;delete=', $post['id'], ';', $context['session_query'], '" class="remove_button" onclick="return ask(', $remove_confirm, ', e);">', $txt['remove_draft'], '</a></li>
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
			<form action="<URL>?action=pm;sa=showdrafts;deleteall" method="post" onclick="return ask(', JavaScriptEscape($txt['remove_all_drafts_confirm']), ', e);">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" value="', $txt['remove_all_drafts'], '" class="delete">
			</form>
		</div>';
}
