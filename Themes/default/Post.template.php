<?php
/**
 * Wedge
 *
 * Displays the main posting area for new topics/the full reply area.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// The main template for the post page.
function template_main()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings, $counter;

	// When using Go Back due to fatal_error, allow the form to be
	// re-submitted with changes, through a non-standard DOM event.
	if ($context['browser']['is_firefox'])
		add_js_inline('
	window.addEventListener("pageshow", function () { document.forms.postmodify.message.readOnly = false; }, false);');

	// Start with message icons - and any missing from this theme.
	add_js_inline('
	var icon_urls = {');
	foreach ($context['icons'] as $icon)
		add_js_inline('
		"' . $icon['value'] . '": "' . $icon['url'] . '"' . ($icon['is_last'] ? '' : ','));
	add_js_inline('
	};');

	// The actual message icon selector.
	add_js_inline('
	function showimage()
	{
		document.images.icons.src = icon_urls[document.forms.postmodify.icon.options[document.forms.postmodify.icon.selectedIndex].value];
	}');

	add_js('
	var postmod = document.forms.postmodify;');

	// Start the form and display the link tree.

	echo '
		<form action="', $scripturl, '?action=', $context['destination'], ';', empty($context['current_board']) ? '' : 'board=' . $context['current_board'], '" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" class="flow_hidden" onsubmit="', ($context['becomes_approved'] ? '' : 'alert(' . JavaScriptEscape($txt['js_post_will_require_approval']) . ');'), 'submitonce(); smc_saveEntities(\'postmodify\', [\'subject\', \'', $context['postbox']->id, '\', \'guestname\', \'evtitle\', \'question\'], \'options\');" enctype="multipart/form-data">';

	// If the user wants to see how their message looks - the preview section is where it's at!
	echo '
			<div id="preview_section"', isset($context['preview_message']) ? '' : ' style="display: none;"', '>
				<we:cat>
					<span id="preview_subject">', empty($context['preview_subject']) ? '' : $context['preview_subject'], '</span>
				</we:cat>
				<div class="windowbg wrc">
					<div class="post" id="preview_body">
						', empty($context['preview_message']) ? '<br>' : $context['preview_message'], '
					</div>
				</div><br>
			</div>';

	if ($context['make_event'] && (!$context['event']['new'] || !empty($context['current_board'])))
		echo '
			<input type="hidden" name="eventid" value="', $context['event']['id'], '">';

	// Start the main table.
	echo '
			<we:cat>
				', $context['page_title'], '
			</we:cat>
			<div class="roundframe">', isset($context['current_topic']) ? '
				<input type="hidden" name="topic" value="' . $context['current_topic'] . '">' : '';

	// If an error occurred, explain what happened.
	echo '
				<div class="errorbox"', empty($context['post_error']['messages']) ? ' style="display: none"' : '', ' id="errors">
					<dl>
						<dt>
							<strong style="', empty($context['error_type']) || $context['error_type'] != 'serious' ? 'display: none;' : '', '" id="error_serious">', $txt['error_while_submitting'], '</strong>
						</dt>
						<dd class="error" id="error_list">
							', empty($context['post_error']['messages']) ? '' : implode('<br>', $context['post_error']['messages']), '
						</dd>
					</dl>
				</div>';

	// If this won't be approved let them know!
	if (!$context['becomes_approved'])
	{
		echo '
				<p class="information">
					<em>', $txt['wait_for_approval'], '</em>
					<input type="hidden" name="not_approved" value="1">
				</p>';
	}

	// If it's locked, show a message to warn the replyer.
	echo '
				<p class="information"', $context['locked'] ? '' : ' style="display: none"', ' id="lock_warning">
					', $txt['topic_locked_no_reply'], '
				</p>';

	// The post header... important stuff
	echo '
				<dl id="post_header">';

	// Guests have to put in their name and email...
	if (isset($context['name'], $context['email']))
	{
		echo '
					<dt>
						<span', isset($context['post_error']['long_name']) || isset($context['post_error']['no_name']) || isset($context['post_error']['bad_name']) ? ' class="error"' : '', ' id="caption_guestname">', $txt['name'], ':</span>
					</dt>
					<dd>
						<input type="text" name="guestname" value="', $context['name'], '" tabindex="', $context['tabindex']++, '" class="w50" required>
					</dd>';

		if (empty($modSettings['guest_post_no_email']))
			echo '
					<dt>
						<span', isset($context['post_error']['no_email']) || isset($context['post_error']['bad_email']) ? ' class="error"' : '', ' id="caption_email">', $txt['email'], ':</span>
					</dt>
					<dd>
						<input type="email" name="email" value="', $context['email'], '" tabindex="', $context['tabindex']++, '" class="w50" required>
					</dd>';
	}

	// Now show the subject box for this post.
	echo '
					<dt>
						<span', isset($context['post_error']['no_subject']) ? ' class="error"' : '', ' id="caption_subject">', $txt['subject'], ':</span>
					</dt>
					<dd>
						<input type="text" name="subject"', $context['subject'] == '' ? '' : ' value="' . $context['subject'] . '"', ' tabindex="', $context['tabindex']++, '" maxlength="80" class="w75">
					</dd>
					<dt class="clear_left">
						', $txt['message_icon'], ':
					</dt>
					<dd>
						<select name="icon" id="icon" onchange="showimage();">';

	// Loop through each message icon allowed, adding it to the drop down list.
	foreach ($context['icons'] as $icon)
		echo '
							<option value="', $icon['value'], '"', $icon['value'] == $context['icon'] ? ' selected' : '', '>', $icon['name'], '</option>';

	echo '
						</select>
						<img src="', $context['icon_url'], '" id="icons" style="padding-left: 8px">
					</dd>
				</dl>
				<hr class="clear">';

	// Are you posting a calendar event?
	// !!! Use the template list system for this.
	if ($context['make_event'])
		template_make_event();

	// If this is a poll then display all the poll options!
	// !!! Use the template list system for this.
	if ($context['make_poll'])
		template_make_poll();

	// Show the actual posting area...
	echo "\n", $context['postbox']->outputEditor(), "\n";

	// If this message has been edited in the past - display when it was.
	if (isset($context['last_modified']))
		echo '
				<div class="padding smalltext">
					<strong>', $txt['last_edit'], ':</strong>
					', $context['last_modified'], '
				</div>';

	// If the admin has enabled the hiding of the additional options - show a link and image for it.
	if (!empty($settings['additional_options_collapsable']))
		echo '
				<div id="postAdditionalOptionsHeader">
					<div id="postMoreExpand"></div> <strong><a href="#" id="postMoreExpandLink">', $txt['post_additionalopt'], '</a></strong>
				</div>';

	// Display the check boxes for all the standard options - if they are available to the user!
	echo '
				<div id="postMoreOptions" class="smalltext">
					<ul class="post_options">', $context['can_notify'] ? '
						<li><input type="hidden" name="notify" value="0"><label><input type="checkbox" name="notify" id="check_notify"' . ($context['notify'] || !empty($options['auto_notify']) ? ' checked' : '') . ' value="1"> ' . $txt['notify_replies'] . '</label></li>' : '', $context['can_lock'] ? '
						<li><input type="hidden" name="lock" value="0"><label><input type="checkbox" name="lock" id="check_lock"' . ($context['locked'] ? ' checked' : '') . ' value="1"> ' . $txt['lock_topic'] . '</label></li>' : '', '
						<li><label><input type="checkbox" name="goback" id="check_back"' . ($context['back_to_topic'] || !empty($options['return_to_post']) ? ' checked' : '') . ' value="1"> ' . $txt['back_to_topic'] . '</label></li>', $context['can_sticky'] ? '
						<li><input type="hidden" name="sticky" value="0"><label><input type="checkbox" name="sticky" id="check_sticky"' . ($context['sticky'] ? ' checked' : '') . ' value="1"> ' . $txt['sticky_after'] . '</label></li>' : '', '
						<li><label><input type="checkbox" name="ns" id="check_smileys"', $context['use_smileys'] ? '' : ' checked', ' value="NS"> ', $txt['dont_use_smileys'], '</label></li>', $context['can_move'] ? '
						<li><input type="hidden" name="move" value="0"><label><input type="checkbox" name="move" id="check_move" value="1"' . (!empty($context['move']) ? ' checked' : '') . '> ' . $txt['move_after2'] . '</label></li>' : '', $context['can_announce'] && $context['is_first_post'] ? '
						<li><label><input type="checkbox" name="announce_topic" id="check_announce" value="1"' . (!empty($context['announce']) ? ' checked' : '') . '> ' . $txt['announce_topic'] . '</label></li>' : '', $context['show_approval'] ? '
						<li><label><input type="checkbox" name="approve" id="approve" value="2"' . ($context['show_approval'] === 2 ? ' checked' : '') . '> ' . $txt['approve_this_post'] . '</label></li>' : '', '
					</ul>
				</div>';

	// If this post already has attachments on it - give information about them.
	if (!empty($context['current_attachments']))
	{
		echo '
				<dl id="postAttachment">
					<dt>
						', $txt['attached'], ':
					</dt>
					<dd class="smalltext">
						<input type="hidden" name="attach_del[]" value="0">
						', $txt['uncheck_unwatchd_attach'], ':
					</dd>';
		foreach ($context['current_attachments'] as $attachment)
			echo '
					<dd class="smalltext">
						<label><input type="checkbox" id="attachment_', $attachment['id'], '" name="attach_del[]" value="', $attachment['id'], '"', empty($attachment['unchecked']) ? ' checked' : '', ' onclick="oAttach.checkActive();"> ', $attachment['name'], (empty($attachment['approved']) ? ' (' . $txt['awaiting_approval'] . ')' : ''), '</label>
					</dd>';
		echo '
				</dl>';
	}

	// Is the user allowed to post any additional ones? If so give them the boxes to do it!
	if ($context['can_post_attachment'])
	{
		echo '
				<dl id="postAttachment2">
					<dt>
						', $txt['attach'], ':
					</dt>
					<dd class="smalltext">
						<div id="attachments_container">
							<input type="file" name="attachment[]" id="attachment1">
						</div>
					</dd>
					<dd class="smalltext">';

		// Show some useful information such as allowed extensions, maximum size and amount of attachments allowed.
		if (!empty($modSettings['attachmentCheckExtensions']))
			echo '
						', $txt['allowed_types'], ': ', $context['allowed_extensions'], '<br>';

		if (!empty($context['attachment_restrictions']))
			echo '
						', $txt['attach_restrictions'], ' ', implode(', ', $context['attachment_restrictions']), '<br>';

		if (!$context['can_post_attachment_unapproved'])
			echo '
						<span class="alert">', $txt['attachment_requires_approval'], '</span>', '<br>';

		echo '
					</dd>
				</dl>';

		add_js('
	var oAttach = new wedgeAttachSelect({
		file_item: "attachment1",
		file_container: "attachments_container",
		max: ' . $context['max_allowed_attachments'] . ',
		message_txt_delete: ' . JavaScriptEscape($txt['remove']));

		// This is purely setting it up to be displayed in a JSON friendly fashion without having a JSON function handy.
		// Included here since it seemed almost more related to display than logic.
		if (!empty($modSettings['attachmentExtensions']) && !empty($modSettings['attachmentCheckExtensions']))
		{
			$ext = explode(',', $modSettings['attachmentExtensions']);
			foreach ($ext as $k => $v)
				$ext[$k] = JavaScriptEscape($v);

			add_js(',
		message_ext_error: ', JavaScriptEscape(str_replace('{attach_exts}', $context['allowed_extensions'], $txt['cannot_attach_ext'])), ',
		attachment_ext: [', implode(',', $ext), ']');
		}

		add_js('
	});');
	}

	// Is visual verification enabled?
	if ($context['require_verification'])
		echo '
				<div class="post_verification">
					<span', !empty($context['post_error']['need_qr_verification']) ? ' class="error"' : '', '>
						<strong>', $txt['verification'], ':</strong>
					</span>
					', template_control_verification($context['visual_verification_id'], 'all'), '
				</div>';

	// Finally, the submit buttons.
	echo '
				<p class="smalltext" id="shortcuts">
					', $context['browser']['is_firefox'] ? $txt['shortcuts_firefox'] : $txt['shortcuts'], '
				</p>
				<p id="post_confirm_buttons">
					', $context['postbox']->outputButtons();

	// Option to delete an event if user is editing one.
	if ($context['make_event'] && !$context['event']['new'])
		echo '
					<input type="submit" name="deleteevent" value="', $txt['event_delete'], '" onclick="return confirm(', JavaScriptEscape($txt['event_delete_confirm']), ');">';

	echo '
				</p>
			</div>
			<br class="clear">';

	// Assuming this isn't a new topic pass across the last message id.
	if (isset($context['topic_last_message']))
		echo '
			<input type="hidden" name="last_msg" value="', $context['topic_last_message'], '">';

	echo '
			<input type="hidden" name="additional_options" id="additional_options" value="', $context['show_additional_options'] ? '1' : '0', '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">
		</form>';

	// The functions used to preview posts without loading a new page.
	add_js('
	var current_board = ' . (empty($context['current_board']) ? 'null' : $context['current_board']) . ';
	var make_poll = ' . ($context['make_poll'] ? 'true' : 'false') . ';
	var txt_preview_title = "' . $txt['preview_title'] . '";
	var txt_preview_fetch = "' . $txt['preview_fetch'] . '";
	var new_replies = [], reply_counter = ' . (empty($counter) ? 0 : $counter) . ';
	function previewPost()
	{');

	// !!! Currently not sending poll options and option checkboxes.
	add_js('
		var x = [];
		var textFields = ["subject", ' . JavaScriptEscape($context['postbox']->id) . ', "icon", "guestname", "email", "evtitle", "question", "topic"];
		var numericFields = [
			"board", "topic", "last_msg",
			"eventid", "calendar", "year", "month", "day",
			"poll_max_votes", "poll_expire", "poll_change_vote", "poll_hide"
		];
		var checkboxFields = ["ns"];

		for (var i = 0, n = textFields.length; i < n; i++)
			if (textFields[i] in postmod)
			{
				// Handle the WYSIWYG editor.
				if (textFields[i] == ' . JavaScriptEscape($context['postbox']->id) . ' && ' . JavaScriptEscape('oEditorHandle_' . $context['postbox']->id) . ' && oEditorHandle_' . $context['postbox']->id . '.bRichTextEnabled)
					x.push("message_mode=1&" + textFields[i] + "=" + oEditorHandle_' . $context['postbox']->id . '.getText(false).replace(/&#/g, "&#38;#").php_to8bit().php_urlencode());
				else
					x.push(textFields[i] + "=" + postmod[textFields[i]].value.replace(/&#/g, "&#38;#").php_to8bit().php_urlencode());
			}
		for (var i = 0, n = numericFields.length; i < n; i++)
			if (numericFields[i] in postmod && "value" in postmod[numericFields[i]])
				x.push(numericFields[i] + "=" + parseInt(postmod.elements[numericFields[i]].value));
		for (var i = 0, n = checkboxFields.length; i < n; i++)
			if (checkboxFields[i] in postmod && postmod.elements[checkboxFields[i]].checked)
				x.push(checkboxFields[i] + "=" + postmod.elements[checkboxFields[i]].value);

		sendXMLDocument(smf_prepareScriptUrl(smf_scripturl) + "action=post2" + (current_board ? ";board=" + current_board : "") + (make_poll ? ";poll" : "") + ";preview;xml", x.join("&"), onDocSent);

		$("#preview_section").show();
		$("#preview_subject").html(txt_preview_title);
		$("#preview_body").html(txt_preview_fetch);

		return false;
	}

	function onDocSent(XMLDoc)
	{
		if (!XMLDoc)
			$(postmod.preview).click(function () { return true; }).click();

		// Show the preview section.
		$("#preview_subject").html($("smf preview subject", XMLDoc).text());
		$("#preview_body").html($("smf preview body", XMLDoc).text()).attr("class", "post");

		// Show a list of errors (if any).
		var errors = $("smf errors", XMLDoc), errorList = [];
		$("error", errors).each(function () {
			errorList.push($(this).text());
		});
		$("#errors").toggle(errorList.length > 0);
		$("#error_serious").toggle(errors.attr("serious") == 1);
		$("#error_list").html(errorList.length > 0 ? errorList.join("<br>") : "");

		// Show a warning if the topic has been locked.
		$("#lock_warning").toggle(errors.attr("topic_locked") == 1);

		// Adjust the color of captions if the given data is erroneous.
		$("caption", errors).each(function () {
			$("#caption_" + $(this).attr("name")).attr("class", $(this).attr("class"));
		});

		if ($("post_error", errors).length)
			postmod.' . $context['postbox']->id . '.style.border = "1px solid red";
		else if (postmod.' . $context['postbox']->id . '.style.borderColor == "red" || postmod.' . $context['postbox']->id . '.style.borderColor == "red red red red")
		{
			if ("runtimeStyle" in postmod.' . $context['postbox']->id . ')
				postmod.' . $context['postbox']->id . '.style.borderColor = "";
			else
				postmod.' . $context['postbox']->id . '.style.border = null;
		}

		// Set the new last message id.
		if ("last_msg" in postmod)
			postmod.last_msg.value = $("smf last_msg", XMLDoc).text();

		// Remove the new image from old-new replies!
		for (i = 0; i < new_replies.length; i++)
			$("#image_new_" + new_replies[i]).hide();

		new_replies = [];
		var ignored_replies = [], ignoring;
		var newPostsHTML = "", id;

		$("smf new_posts post", XMLDoc).each(function () {
			id = $(this).attr("id");
			new_replies.push(id);

			ignoring = false;
			if ($("is_ignored", this).text() != 0)
				ignored_replies.push(ignoring = id);

			newPostsHTML += \'<div class="windowbg\' + (++reply_counter % 2 == 0 ? \'2\' : \'\') + \' wrc core_posts"><div id="msg\' + id + \'"><div class="floatleft"><h5>' . $txt['posted_by'] . ': \' + $("poster", this).text() + \'</h5><span class="smalltext">&#171;&nbsp;<strong>' . $txt['on'] . ':</strong> \' + $("time", this).text() + \'&nbsp;&#187;</span> <div class="new_icon" title="' . $txt['preview_new'] . '" id="image_new_\' + id + \'"></div></div>\';');

	if ($context['can_quote'])
		add_js('
			newPostsHTML += \'<ul class="reset smalltext quickbuttons" id="msg_\' + id + \'_quote"><li class="quote_button"><a href="#postmodify" onclick="return insertQuoteFast(\\\'\' + id + \'\\\');"><span>' . $txt['bbc_quote'] . '</span><\' + \'/a></li></ul>\';');

	add_js('
			newPostsHTML += \'<br class="clear">\';

			if (ignoring)
				newPostsHTML += \'<div id="msg_\' + id + \'_ignored_prompt" class="smalltext">' . $txt['ignoring_user'] . '<a href="#" id="msg_\' + id + \'_ignored_link" style="display: none;">' . $txt['show_ignore_user_post'] . '</a></div>\';

			newPostsHTML += \'<div class="list_posts smalltext" id="msg_\' + id + \'_body">\' + $("message", this).text() + \'<\' + \'/div></div></div>\';
		});
		if (newPostsHTML != "")
			$("#new_replies").append(newPostsHTML);

		var numIgnoredReplies = ignored_replies.length;
		if (numIgnoredReplies != 0)
		{
			for (var i = 0; i < numIgnoredReplies; i++)
			{
				aIgnoreToggles[ignored_replies[i]] = new smc_Toggle({
					bCurrentlyCollapsed: true,
					aSwappableContainers: [
						"msg_" + ignored_replies[i] + "_body",
						"msg_" + ignored_replies[i] + "_quote",
					],
					aSwapLinks: [
						{
							sId: "msg_" + ignored_replies[i] + "_ignored_link",
							msgExpanded: "",
							msgCollapsed: ' . JavaScriptEscape($txt['show_ignore_user_post']) . '
						}
					]
				});
			}
		}

		if (typeof(smf_codeFix) != "undefined")
			smf_codeFix();
	}');

	// Code for showing and hiding additional options.
	if (!empty($settings['additional_options_collapsable']))
	{
		// If we're collapsed, hide everything now and don't trigger the animation.
		$fold = empty($context['show_additional_options']);
		if ($fold)
			add_js('
	$("#postMoreOptions").hide();
	$("#postAttachment").hide();
	$("#postAttachment2").hide();');

		add_js('
	var oSwapAdditionalOptions = new smc_Toggle({
		bCurrentlyCollapsed: ', $fold ? 'true' : 'false', ',
		funcOnBeforeCollapse: function () { $("#additional_options").val("0"); },
		funcOnBeforeExpand: function () { $("#additional_options").val("1"); },
		aSwappableContainers: [
			"postMoreOptions",
			"postAttachment",
			"postAttachment2"
		],
		aSwapImages: [
			{
				sId: "postMoreExpand",
				altExpanded: "-",
				altCollapsed: "+"
			}
		],
		aSwapLinks: [
			{
				sId: "postMoreExpandLink",
				msgExpanded: ' . JavaScriptEscape($txt['post_additionalopt']) . '
			}
		]
	});');
	}

	// If the user is replying to a topic show the previous posts.
	// !!! Use the template list system for this.
	if (isset($context['previous_posts']) && count($context['previous_posts']) > 0)
		template_show_previous_posts();
}

// Poll making
function template_make_poll()
{
	global $context, $txt;

	// This is a poll - use some JavaScript to ensure the user doesn't create a poll with illegal option combinations.
	add_js('
	function pollOptions()
	{
		var expire_time = $("#poll_expire")[0];

		if (isEmptyText(expire_time) || expire_time.value == 0)
		{
			postmod.poll_hide[2].disabled = true;
			if (postmod.poll_hide[2].checked)
				postmod.poll_hide[1].checked = true;
		}
		else
			postmod.poll_hide[2].disabled = false;
	}

	var pollOptionNum = 0, pollTabIndex;
	function addPollOption()
	{
		if (pollOptionNum == 0)
		{
			for (var i = 0, n = postmod.elements.length; i < n; i++)
				if (postmod.elements[i].id.substr(0, 8) == "options-")
				{
					pollOptionNum++;
					pollTabIndex = postmod.elements[i].tabIndex;
				}
		}
		pollOptionNum++;

		$("#pollMoreOptions").append(' . JavaScriptEscape('<li><label>' . $txt['option'] . ' ') . ' + pollOptionNum + ' . JavaScriptEscape(': <input type="text" name="options[') . ' + pollOptionNum + ' . JavaScriptEscape(']" value="" maxlength="255" tabindex="') . ' + pollTabIndex + ' . JavaScriptEscape('" class="w50"></label></li>') . ');
		return false;
	}');

	echo '
				<div id="edit_poll">
					<fieldset id="poll_main">
						<legend><span ', (isset($context['poll_error']['no_question']) ? ' class="error"' : ''), '>', $txt['poll_question'], '</span></legend>
						<input type="text" name="question" value="', isset($context['question']) ? $context['question'] : '', '" tabindex="', $context['tabindex']++, '" class="w75">
						<ul class="poll_main" id="pollMoreOptions">';

	// Loop through all the choices and print them out.
	foreach ($context['choices'] as $choice)
		echo '
							<li>
								<label>', $txt['option'], ' ', $choice['number'], ':
								<input type="text" name="options[', $choice['id'], ']" id="options-', $choice['id'], '" value="', $choice['label'], '" tabindex="', $context['tabindex']++, '" maxlength="255" class="w50"></label>
							</li>';

	echo '
						</ul>
						<strong><a href="#" onclick="return addPollOption();">(', $txt['poll_add_option'], ')</a></strong>
					</fieldset>
					<fieldset id="poll_options">
						<legend>', $txt['poll_options'], '</legend>
						<dl class="settings poll_options">
							<dt>
								<label for="poll_max_votes">', $txt['poll_max_votes'], ':</label>
							</dt>
							<dd>
								<input type="text" name="poll_max_votes" id="poll_max_votes" size="2" value="', $context['poll_options']['max_votes'], '">
							</dd>
							<dt>
								<label for="poll_expire">', $txt['poll_run'], ':</label><br>
								<em class="smalltext">', $txt['poll_run_limit'], '</em>
							</dt>
							<dd>
								<input type="text" name="poll_expire" id="poll_expire" size="2" value="', $context['poll_options']['expire'], '" onchange="pollOptions();" maxlength="4"> ', $txt['days_word'], '
							</dd>
							<dt>
								<label for="poll_change_vote">', $txt['poll_do_change_vote'], ':</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_change_vote" name="poll_change_vote"', !empty($context['poll']['change_vote']) ? ' checked' : '', '>
							</dd>';

	if ($context['poll_options']['guest_vote_enabled'])
		echo '
							<dt>
								<label for="poll_guest_vote">', $txt['poll_guest_vote'], ':</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_guest_vote" name="poll_guest_vote"', !empty($context['poll_options']['guest_vote']) ? ' checked' : '', '>
							</dd>';

	echo '
							<dt>
								', $txt['poll_results_visibility'], ':
							</dt>
							<dd>
								<label><input type="radio" name="poll_hide" id="poll_results_anyone" value="0"', $context['poll_options']['hide'] == 0 ? ' checked' : '', '> ', $txt['poll_results_anyone'], '</label><br>
								<label><input type="radio" name="poll_hide" id="poll_results_voted" value="1"', $context['poll_options']['hide'] == 1 ? ' checked' : '', '> ', $txt['poll_results_voted'], '</label><br>
								<label><input type="radio" name="poll_hide" id="poll_results_expire" value="2"', $context['poll_options']['hide'] == 2 ? ' checked' : '', empty($context['poll_options']['expire']) ? ' disabled' : '', '> ', $txt['poll_results_after'], '</label>
							</dd>
						</dl>
					</fieldset>
				</div>';
}

// Event making
function template_make_event()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings, $counter;

	// We want to ensure we show the current days in a month etc... This is done here.
	if ($context['make_event'])
		add_js('
	var monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

	function generateDays()
	{
		var dayElement = $("#day")[0], year = $("#year").val(), monthElement = ("#month")[0];
		var days, selected = dayElement.selectedIndex;

		monthLength[1] = (year % 4 == 0 && (year % 100 != 0 || year % 400 == 0)) ? 29 : 28;

		days = monthLength[monthElement.value - 1];
		while (dayElement.options.length)
			dayElement.options[0] = null;

		for (i = 1; i <= days; i++)
			dayElement.options.push(new Option(i, i));

		if (selected < days)
			dayElement.selectedIndex = selected;
	}');

	echo '
				<div id="post_event">
					<fieldset id="event_main">
						<legend><span', isset($context['post_error']['no_event']) ? ' class="error"' : '', ' id="caption_evtitle">', $txt['calendar_event_title'], '</span></legend>
						<input type="text" name="evtitle" maxlength="80" value="', $context['event']['title'], '" tabindex="', $context['tabindex']++, '" class="w75">
						<div class="smalltext nowrap">
							<input type="hidden" name="calendar" value="1">', $txt['calendar_year'], '
							<select name="year" id="year" tabindex="', $context['tabindex']++, '" onchange="generateDays();">';

	// Show a list of all the years we allow...
	for ($year = $modSettings['cal_minyear']; $year <= $modSettings['cal_maxyear']; $year++)
		echo '
								<option value="', $year, '"', $year == $context['event']['year'] ? ' selected' : '', '>', $year, '&nbsp;</option>';

	echo '
							</select>
							', $txt['calendar_month'], '
							<select name="month" id="month" onchange="generateDays();">';

	// There are 12 months per year - ensure that they all get listed.
	for ($month = 1; $month <= 12; $month++)
		echo '
								<option value="', $month, '"', $month == $context['event']['month'] ? ' selected' : '', '>', $txt['months'][$month], '&nbsp;</option>';

	echo '
							</select>
							', $txt['calendar_day'], '
							<select name="day" id="day">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
	for ($day = 1; $day <= $context['event']['last_day']; $day++)
		echo '
								<option value="', $day, '"', $day == $context['event']['day'] ? ' selected' : '', '>', $day, '&nbsp;</option>';

	echo '
							</select>
						</div>
					</fieldset>';

	if (!empty($modSettings['cal_allowspan']) || ($context['event']['new'] && $context['is_new_post']))
	{
		echo '
					<fieldset id="event_options">
						<legend>', $txt['calendar_event_options'], '</legend>
						<div class="event_options smalltext">
							<ul class="event_options">';

		// If events can span more than one day then allow the user to select how long it should last.
		if (!empty($modSettings['cal_allowspan']))
		{
			echo '
								<li>
									', $txt['calendar_numb_days'], '
									<select name="span">';

			for ($days = 1; $days <= $modSettings['cal_maxspan']; $days++)
				echo '
										<option value="', $days, '"', $days == $context['event']['span'] ? ' selected' : '', '>', $days, '&nbsp;</option>';

			echo '
									</select>
								</li>';
		}

		// If this is a new event let the user specify which board they want the linked post to be put into.
		if ($context['event']['new'] && $context['is_new_post'])
		{
			echo '
								<li>
									', $txt['calendar_post_in'], '
									<select name="board">';
			foreach ($context['event']['categories'] as $category)
			{
				echo '
										<optgroup label="', $category['name'], '">';
				foreach ($category['boards'] as $board)
					echo '
											<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '&nbsp;</option>';
				echo '
										</optgroup>';
			}
			echo '
									</select>
								</li>';
		}

		echo '
							</ul>
						</div>
					</fieldset>';
	}

	echo '
				</div>';
}

// Previous post handling
function template_show_previous_posts()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings, $counter;

	echo '
		<div id="recent" class="flow_hidden main_section">
			<we:cat>
				', $txt['topic_summary'], '
			</we:cat>
			<div id="new_replies"></div>';

	$ignored_posts = array();
	foreach ($context['previous_posts'] as $post)
	{
		$ignoring = false;
		if (!empty($post['is_ignored']))
			$ignored_posts[] = $ignoring = $post['id'];

		echo '
			<div class="', $post['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' wrc core_posts">
				<div id="msg', $post['id'], '">
					<div class="floatleft">
						<h5>', $txt['posted_by'], ': ', $post['poster'], '</h5>
						<span class="smalltext">&#171;&nbsp;<strong>', $txt['on'], ':</strong> ', $post['time'], '&nbsp;&#187;</span>
					</div>';

		if ($context['can_quote'])
			echo '
					<ul class="reset smalltext quickbuttons" id="msg_', $post['id'], '_quote">
						<li class="quote_button"><a href="#postmodify" onclick="return insertQuoteFast(', $post['id'], ');"><span>', $txt['bbc_quote'], '</span></a></li>
					</ul>';

		echo '
					<br class="clear">';

		if ($ignoring)
			echo '
					<div id="msg_', $post['id'], '_ignored_prompt" class="smalltext">
						', $txt['ignoring_user'], '
						<a href="#" id="msg_', $post['id'], '_ignored_link" style="display: none;">', $txt['show_ignore_user_post'], '</a>
					</div>';

		echo '
					<div class="list_posts smalltext" id="msg_', $post['id'], '_body">', $post['message'], '</div>
				</div>
			</div>';
	}

	echo '
		</div>';

	add_js('
	var aIgnoreToggles = [];');

	foreach ($ignored_posts as $post_id)
		add_js('
	aIgnoreToggles[' . $post_id . '] = new smc_Toggle({
		bCurrentlyCollapsed: true,
		aSwappableContainers: [
			"msg_' . $post_id . '_body",
			"msg_' . $post_id . '_quote",
		],
		aSwapLinks: [
			{
				sId: "msg_' . $post_id . '_ignored_link",
				msgExpanded: "",
				msgCollapsed: ' . JavaScriptEscape($txt['show_ignore_user_post']) . '
			}
		]
	});');

	add_js('
	function insertQuoteFast(messageid)
	{
		getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + "action=quotefast;quote=" + messageid + ";xml;mode=" + (oEditorHandle_' . $context['postbox']->id . '.bRichTextEnabled ? 1 : 0), onDocReceived);
		return true;
	}
	function onDocReceived(XMLDoc)
	{
		oEditorHandle_' . $context['postbox']->id . '.insertText($("quote", XMLDoc).text(), false, true);
	}');
}

// The template for the spellchecker.
function template_spellcheck()
{
	global $context, $options, $txt;

	// The style information that makes the spellchecker look... like the forum hopefully!
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
<head>
	<meta charset="utf-8">
	<title>', $txt['spell_check'], '</title>',
	theme_base_css(), '
	<style>
		body, td
		{
			font-size: small;
			margin: 0;
			background: #f0f0f0;
			color: #000;
			padding: 10px;
		}
		.highlight
		{
			color: red;
			font-weight: bold;
		}
		#spellview
		{
			border-style: outset;
			border: 1px solid black;
			padding: 5px;
			width: 95%;
			height: 314px;
			overflow: auto;
			background: #ffffff;
		}
	</style>';

	// As you may expect - we need a lot of JavaScript for this... Load it from the separate files.
	echo theme_base_js(1), '
	<script src="', add_js_file('scripts/spellcheck.js', false, true), '"></script>
	<script><!-- // --><![CDATA[
		var spell_formname = window.opener.spell_formname;
		var spell_fieldname = window.opener.spell_fieldname;', $context['spell_js'], '
	// ]]></script>';

	echo '
</head>
<body onload="nextWord(false);">
	<form action="#" method="post" accept-charset="UTF-8" name="spellingForm" id="spellingForm" onsubmit="return false;" style="margin: 0">
		<div id="spellview">&nbsp;</div>
		<table class="w100 cp4 cs0">
			<tr class="windowbg">
				<td class="top w50">
					', $txt['spellcheck_change_to'], '<br>
					<input type="text" name="changeto" style="width: 98%">
				</td>
				<td class="w50">
					', $txt['spellcheck_suggest'], '<br>
					<select name="suggestions" style="width: 98%" size="5" onclick="if (this.selectedIndex != -1) this.form.changeto.value = this.options[this.selectedIndex].text;" ondblclick="replaceWord();">
					</select>
				</td>
			</tr>
		</table>
		<div class="righttext" style="padding: 4px;">
			<input type="button" name="change" value="', $txt['spellcheck_change'], '" onclick="replaceWord();">
			<input type="button" name="changeall" value="', $txt['spellcheck_change_all'], '" onclick="replaceAll();">
			<input type="button" name="ignore" value="', $txt['spellcheck_ignore'], '" onclick="nextWord(false);">
			<input type="button" name="ignoreall" value="', $txt['spellcheck_ignore_all'], '" onclick="nextWord(true);">
		</div>
	</form>
</body>
</html>';
}

?>