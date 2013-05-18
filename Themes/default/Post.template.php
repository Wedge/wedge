<?php
/**
 * Wedge
 *
 * Displays the main posting area for new topics/the full reply area.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// The main template for the post page.
function template_postform_before()
{
	global $context, $txt;

	// Start the form, the header and the container for all the markup
	echo '
		<form action="<URL>?action=', $context['destination'], ';', empty($context['current_board']) ? '' : 'board=' . $context['current_board'], '" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" class="flow_hidden" onsubmit="', $context['becomes_approved'] ? '' : 'return say(' . JavaScriptEscape($txt['js_post_will_require_approval']) . ', e, function () { ', 'submitonce(); weSaveEntities(\'postmodify\', ', $context['postbox']->saveEntityFields(), ', \'options\');', $context['becomes_approved'] ? '' : ' });', '" enctype="multipart/form-data">
			<we:cat>
				', $context['page_title'], '
			</we:cat>
			<div class="roundframe">';
}

function template_preview()
{
	global $context;

	// If the user wants to see how their message looks - the preview section is where it's at!
	echo '
		<div id="preview_section"', isset($context['preview_message']) ? '' : ' class="hide"', '>
			<we:cat>
				<span id="preview_subject">', empty($context['preview_subject']) ? '' : $context['preview_subject'], '</span>
			</we:cat>
			<div class="postbg wrc core_posts">
				', empty($context['preview_message']) ? '<br>' : $context['preview_message'], '
			</div><br>
		</div>';
}

function template_post_errors()
{
	global $context, $txt;

	if (empty($context['post_error']['messages']))
		return;

	// If an error occurred, explain what happened.
	echo '
				<div class="errorbox" id="errors">', empty($context['error_type']) || $context['error_type'] != 'serious' ? '' : '
					<h3 id="error_serious">' . $txt['error_while_submitting'] . '</h3>', empty($context['post_error']['messages']) ? '' : '
					<ul class="error" id="error_list">
						<li>' . implode('</li><li>', $context['post_error']['messages']) . '</li>
					</ul>', '
				</div>';
}

function template_post_approval()
{
	global $context, $txt;

	// If this won't be approved let them know!
	if (!$context['becomes_approved'])
	{
		echo '
				<p class="information">
					<em>', $txt['wait_for_approval'], '</em>
					<input type="hidden" name="not_approved" value="1">
				</p>';
	}
}

function template_post_locked()
{
	global $context, $txt;

	// If it's locked, show a message to warn the replyer.
	echo '
				<p class="information', $context['locked'] ? '' : ' hide', '" id="lock_warning">
					', $txt['topic_locked_no_reply'], '
				</p>';
}

function template_post_name_email()
{
	global $context, $txt, $settings;

	// Guests have to put in their name and email...
	if (isset($context['name'], $context['email']))
	{
		echo '
					<dl>
						<dt>
							<span', isset($context['post_error']['long_name']) || isset($context['post_error']['no_name']) || isset($context['post_error']['bad_name']) ? ' class="error"' : '', ' id="caption_guestname">', $txt['name'], ':</span>
						</dt>
						<dd>
							<input name="guestname" value="', $context['name'], '" tabindex="', $context['tabindex']++, '" class="w50" required>
						</dd>';

		if (empty($settings['guest_post_no_email']))
			echo '
						<dt>
							<span', isset($context['post_error']['no_email']) || isset($context['post_error']['bad_email']) ? ' class="error"' : '', ' id="caption_email">', $txt['email'], ':</span>
						</dt>
						<dd>
							<input type="email" name="email" value="', $context['email'], '" tabindex="', $context['tabindex']++, '" class="w50" required>
						</dd>';

		echo '
					</dl>';
	}
}

// Now display the editor box and last modified if applicable.
function template_postbox()
{
	global $context, $txt;

	// Show the actual posting area...
	echo $context['postbox']->outputEditor();

	// If this message has been edited in the past - display when it was.
	if (isset($context['last_modified']))
		echo '
				<div class="padding smalltext">
					<strong>', strtr($txt['last_edit_mine'], array('{date}' => $context['last_modified'])), '</strong>
				</div>';
}

function template_post_additional_options()
{
	// !!! This needs to be rewritten to be extensible, declared in Post.php and available as a simple list to be iterated over.
	global $theme, $txt, $options, $context, $settings;

	// If the admin has enabled the hiding of the additional options - show a link and image for it.
	if (!empty($settings['additional_options_collapsable']))
		echo '
				<div id="postOptionsHeader">
					<div id="postMoreExpand"></div> <strong><a href="#" id="postMoreExpandLink">', $txt['post_additionalopt'], '</a></strong>
				</div>';

	// Display the check boxes for all the standard options - if they are available to the user!
	echo '
				<ul id="postOptions" class="smalltext">', $context['can_notify'] ? '
					<li><input type="hidden" name="notify" value="0"><label><input type="checkbox" name="notify" id="check_notify"' . ($context['notify'] || !empty($options['auto_notify']) ? ' checked' : '') . ' value="1"> ' . $txt['notify_replies'] . '</label></li>' : '', $context['can_lock'] ? '
					<li><input type="hidden" name="lock" value="0"><label><input type="checkbox" name="lock" id="check_lock"' . ($context['locked'] ? ' checked' : '') . ' value="1"> ' . $txt['lock_topic'] . '</label></li>' : '', '
					<li><label><input type="checkbox" name="goback" id="check_back"' . ($context['back_to_topic'] || !empty($options['return_to_post']) ? ' checked' : '') . ' value="1"> ' . $txt['back_to_topic'] . '</label></li>', $context['can_pin'] ? '
					<li><input type="hidden" name="pin" value="0"><label><input type="checkbox" name="pin" id="check_pin"' . ($context['pinned'] ? ' checked' : '') . ' value="1"> ' . $txt['pin_after'] . '</label></li>' : '', '
					<li><label><input type="checkbox" name="ns" id="check_smileys"', $context['use_smileys'] ? '' : ' checked', ' value="NS"> ', $txt['dont_use_smileys'], '</label></li>', $context['can_move'] ? '
					<li><input type="hidden" name="move" value="0"><label><input type="checkbox" name="move" id="check_move" value="1"' . (!empty($context['move']) ? ' checked' : '') . '> ' . $txt['move_after2'] . '</label></li>' : '', $context['can_announce'] && $context['is_first_post'] ? '
					<li><label><input type="checkbox" name="announce_topic" id="check_announce" value="1"' . (!empty($context['announce']) ? ' checked' : '') . '> ' . $txt['announce_topic'] . '</label></li>' : '', $context['show_approval'] ? '
					<li><label><input type="checkbox" name="approve" id="approve" value="2"' . ($context['show_approval'] === 2 ? ' checked' : '') . '> ' . $txt['approve_this_post'] . '</label></li>' : '', '
				</ul>';
}

function template_post_attachments()
{
	global $context, $txt, $settings;

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

		foreach ($context['current_attachments'] as $id => $attachment)
			echo '
					<dd class="smalltext">
						<label><input type="checkbox" id="attachment_', $id, '" name="attach_del[]" value="', $id, '"', empty($attachment['unchecked']) ? ' checked' : '', ' onclick="oAttach.checkActive();"> ', $attachment['name'], '</label>
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
							<input type="file" name="attachment[]" id="attachment1" multiple>
						</div>
					</dd>
					<dd class="smalltext">';

		// Show some useful information such as allowed extensions, maximum size and amount of attachments allowed.
		if (!empty($settings['attachmentCheckExtensions']))
			echo '
						', $txt['allowed_types'], ': ', $context['allowed_extensions'], '<br>';

		if (!empty($context['attachment_restrictions']))
			echo '
						', $txt['attach_restrictions'], ' ', implode(', ', $context['attachment_restrictions']), '<br>';

		echo '
					</dd>
				</dl>';

		add_js('
	var oAttach = new wedgeAttachSelect({
		file_item: "attachment1",
		file_container: "attachments_container",
		max: ' . $context['max_allowed_attachments']);

		// This is purely setting it up to be displayed in a JSON friendly fashion without having a JSON function handy.
		// Included here since it seemed almost more related to display than logic.
		if (!empty($settings['attachmentExtensions']) && !empty($settings['attachmentCheckExtensions']))
		{
			$ext = explode(',', $settings['attachmentExtensions']);
			foreach ($ext as $k => $v)
				$ext[$k] = JavaScriptEscape($v);

			add_js(',
		message_ext_error: ', JavaScriptEscape(str_replace('{attach_exts}', $context['allowed_extensions'], $txt['cannot_attach_ext'])), ',
		attachment_ext: [', implode(',', $ext), ']');
		}

		add_js('
	});');
	}
}

function template_post_verification()
{
	global $context, $txt;

	// Is visual verification enabled?
	if ($context['require_verification'])
		echo '
				<div class="post_verification">
					<span', !empty($context['post_error']['need_qr_verification']) ? ' class="error"' : '', '>
						<strong>', $txt['verification'], ':</strong>
					</span>
					', template_control_verification($context['visual_verification_id'], 'all'), '
				</div>';
}

function template_post_buttons()
{
	global $context;

	// And... the submit buttons.
	echo '
				<div class="postbuttons">', $context['postbox']->outputButtons(), '
				</div>';
}

function template_post_shortcuts()
{
	global $context, $txt;

	// List of keyboard shortcuts.
	echo '
				<div id="shortcuts">
					<span class="smalltext">', $txt['shortcuts'], '</span>
				</div>';
}

function template_postform_after()
{
	global $context, $theme, $counter, $txt, $settings;

	// We've finished with the main form elements, so finish the UI for it.
	echo '
			</div>
			<br class="clear">';

	// The stuff we need for later: the topic number if we have one, the last message if we have one, then the general form items.
	if (isset($context['current_topic']))
		echo '
			<input type="hidden" name="topic" value="', $context['current_topic'], '">';

	if (isset($context['topic_last_message']))
		echo '
			<input type="hidden" name="last" value="', $context['topic_last_message'], '">';

	// We always need this stuff.
	echo '
			<input type="hidden" name="additional_options" id="additional_options" value="', $context['show_additional_options'] ? '1' : '0', '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">
			<input type="hidden" name="parent" value="', $context['msg_parent'], '">
		</form>';

	// When using Go Back due to fatal_error, allow the form to be re-submitted with changes, through a non-standard DOM event.
	if (we::is('firefox'))
		add_js('
	addEventListener("pageshow", function () { document.forms.postmodify.message.readOnly = false; }, false);');

	// More general stuff, before diving into the preview functions.
	add_js('
	var postmod = document.forms.postmodify,
		postbox = ' . JavaScriptEscape($context['postbox']->id) . ',
		posthandle = oEditorHandle_' . $context['postbox']->id . ',
		make_poll = ' . ($context['make_poll'] ? 'true' : 'false') . ',
		new_replies = [], reply_counter = ' . (empty($counter) ? 0 : $counter) . ',
		can_quote = ' . ($context['can_quote'] ? 'true' : 'false') . ',
		new_post_tpl = ' . JavaScriptEscape('<div class="windowbg%counter% wrc core_posts"><div id="msg%id%"><div class="floatleft"><h5>' . $txt['posted_by'] . ': %poster%</h5>'
			. '<span class="smalltext">&#171;&nbsp;%date%&nbsp;&#187;</span><div class="note" id="image_new_%id%">%new%</div></div>') . ';');

	// !!! Currently not sending poll options and option checkboxes.
	foreach ($context['form_fields'] as $field_type => $field_items)
	{
		array_walk($field_items, 'JavaScriptEscape');
		add_js('
	var ', $field_type, 'Fields = ["' . implode('","', $field_items) . '"];');
	}

	// Code for showing and hiding additional options.
	if (!empty($settings['additional_options_collapsable']))
	{
		// If we're collapsed, hide everything now and don't trigger the animation.
		if (empty($context['show_additional_options']))
			add_js('
	$("#postOptions, #postAttachment, #postAttachment2").hide();');

		add_js('
	new weToggle({', empty($context['show_additional_options']) ? '
		isCollapsed: true,' : '', '
		onBeforeCollapse: function () { $("#additional_options").val("0"); },
		onBeforeExpand: function () { $("#additional_options").val("1"); },
		aSwapContainers: [
			"postOptions",
			"postAttachment",
			"postAttachment2"
		],
		aSwapImages: ["postMoreExpand"],
		aSwapLinks: ["postMoreExpandLink"]
	});');
	}

	// If the user is replying to a topic show the previous posts.
	// !!! Use the template list system for this.
	if (isset($context['previous_posts']) && count($context['previous_posts']) > 0)
		template_show_previous_posts();
}

function template_post_header_before()
{
	echo '
				<div id="post_header">';
}

function template_post_subject()
{
	global $context, $txt;

	// Now show the subject box for this post.
	echo '
					<strong>', $txt['message_icon'], ' / ', $txt['privacy'], ' / <span', isset($context['post_error']['no_subject']) ? ' class="error"' : '', ' id="caption_subject">', $txt['subject'], ':</span></strong>
					<hr style="height: 0; margin: 4px">
					<div id="subject_line">
						<div>
							<select name="icon" id="icon" tabindex="', $context['tabindex']++, '">';

	// Loop through each message icon allowed, adding it to the drop down list.
	foreach ($context['icons'] as $icon)
		echo '<option value="', $icon['value'], '"', $icon['value'] == $context['icon'] ? ' selected' : '', '>&lt;img src=&quot;', $icon['url'], '&quot;&gt;&nbsp; ', $icon['name'], '</option>';

	echo '
							</select>';

	if ($context['is_first_post'])
	{
		echo '
							<select name="privacy" id="privacy" tabindex="', $context['tabindex']++, '">';

		foreach ($context['privacies'] as $priv)
			echo '<option value="', $priv, '"', $priv == $context['current_privacy'] ? ' selected' : '', '>&lt;span class=&quot;privacy_', $priv, '&quot /&gt;&nbsp;', $txt['privacy_' . $priv], '</option>';

		echo '
							</select>';
	}

	echo '
						</div>
						<div>
							<input name="subject" id="subject"', $context['subject'] == '' ? '' : ' value="' . $context['subject'] . '"', ' tabindex="', $context['tabindex']++, '" maxlength="80" class="w100">
						</div>
					</div>';
}

function template_post_header_after()
{
	echo '
				</div>
				<hr class="clear">';
}

// If this is a poll then display all the poll options!
function template_make_poll()
{
	global $context, $txt;

	add_js('
	var pollOptionTxt = ' . JavaScriptEscape($txt['option']) . ',
		pollOptionTemplate = \'<li><label>%pollOptionTxt% %pollOptionNum%: <input name="options[%pollOptionNum%]" value="" maxlength="255" tabindex="%pollTabIndex%" class="w50"></label></li>\';');

	echo '
				<div id="edit_poll">
					<fieldset id="poll_main">
						<legend><span ', (isset($context['poll_error']['no_question']) ? ' class="error"' : ''), '>', $txt['poll_question'], '</span></legend>
						<input name="question" value="', isset($context['question']) ? $context['question'] : '', '" tabindex="', $context['tabindex']++, '" class="w75">
						<ul class="poll_main" id="pollMoreOptions">';

	// Loop through all the choices and print them out.
	foreach ($context['choices'] as $choice)
		echo '
							<li>
								<label>', $txt['option'], ' ', $choice['number'], ':
								<input name="options[', $choice['id'], ']" id="options-', $choice['id'], '" value="', $choice['label'], '" tabindex="', $context['tabindex']++, '" maxlength="255" class="w50"></label>
							</li>';

	echo '
						</ul>
						<strong style="margin-left: 30px"><a href="#" onclick="return addPollOption();">(', $txt['poll_add_option'], ')</a></strong>
					</fieldset>
					<fieldset id="poll_options">
						<legend>', $txt['poll_options'], '</legend>
						<dl class="settings poll_options">
							<dt>
								<label for="poll_max_votes">', $txt['poll_max_votes'], ':</label>
							</dt>
							<dd>
								<input name="poll_max_votes" id="poll_max_votes" size="2" value="', $context['poll_options']['max_votes'], '">
							</dd>
							<dt>
								<label for="poll_expire">', $txt['poll_run'], ':</label><br>
								<em class="smalltext">', $txt['poll_run_limit'], '</em>
							</dt>
							<dd>
								<input name="poll_expire" id="poll_expire" size="2" value="', $context['poll_options']['expire'], '" onchange="pollOptions();" maxlength="4"> ', $txt['days_word'], '
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
							<dt>
								', $txt['poll_voters_visibility'], ':
								<div class="smalltext">', $txt['poll_voters_no_change_future'], ' <a href="<URL>?action=help;in=cannot_change_voter_visibility" class="help" title="', $txt['help'], '" onclick="return reqWin(this);"></a></div>
							</dt>
							<dd>
								<label><input type="radio" name="poll_voters_visible" id="poll_voters_admin" value="0" checked> ', $txt['poll_voters_visibility_admin'], '</label> <a href="<URL>?action=help;in=admins_see_votes" class="help" title="', $txt['help'], '" onclick="return reqWin(this);"></a><br>
								<label><input type="radio" name="poll_voters_visible" id="poll_voters_creator" value="1"> ', $txt['poll_voters_visibility_creator'], '</label><br>
								<label><input type="radio" name="poll_voters_visible" id="poll_voters_members" value="2"> ', $txt['poll_voters_visibility_members'], '</label><br>
								<label><input type="radio" name="poll_voters_visible" id="poll_voters_anyone" value="3"> ', $txt['poll_voters_visibility_anyone'], '</label>
							</dd>
						</dl>
					</fieldset>
				</div>';
}

// Previous post handling
function template_show_previous_posts()
{
	global $context, $theme, $options, $txt, $settings;

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
			<div class="windowbg', $post['alternate'] ? '2' : '', ' wrc core_posts">
				<div id="msg', $post['id'], '">
					<div class="floatleft">
						<h5>', $txt['posted_by'], ': ', $post['poster'], '</h5>
						<span class="smalltext">&#171;&nbsp;', $post['on_time'], '&nbsp;&#187;</span>
					</div>
					<br class="clear">';

		if ($ignoring)
			echo '
					<div class="ignored">
						', $txt['ignoring_user'], '
					</div>';

		echo '
					<div class="list_posts smalltext">', $post['message'], '</div>';

		if ($context['can_quote'])
			echo '
					<div class="actionbar">
						<ul class="actions">
							<li><a href="#postmodify" class="quote_button" onclick="return insertQuoteFast(', $post['id'], ');">', $txt['bbc_quote'], '</a></li>
						</ul>
					</div>';

		echo '
				</div>
			</div>';
	}

	echo '
		</div>';

	foreach ($ignored_posts as $post_id)
		add_js('
	new weToggle({
		isCollapsed: true,
		aSwapContainers: [
			"msg' . $post_id . ' .list_posts",
			"msg' . $post_id . ' .actions"
		],
		aSwapLinks: ["msg' . $post_id . ' .ignored"]
	});');
}

// For ordering pinned topics. Perhaps should be elsewhere?
function template_order_pinned()
{
	global $context, $txt;

	echo '
		<we:cat>', $txt['order_pinned_topics'], '</we:cat>
		<p class="description">', $txt['order_pinned_topics_desc'], '</p>
		<div class="windowbg2 wrc">
			<form action="', $context['this_url'], '" method="post">
				<ul id="sortable" class="topic_table">';

	foreach ($context['pinned_topics'] as $topic)
		echo '
					<li class="windowbg2 subject pinned">
						<span class="handle"></span>
						<div class="floatleft sortme w50">
							<strong><a href="<URL>?topic=', $topic['id_topic'], '.0" target="_blank">', $topic['subject'], '</a></strong>
							<p>', $txt['started_by'], ' ', !empty($topic['starter_id']) ? '<a href="<URL>?action=profile;u=' . $topic['starter_id'] . '">' . $topic['starter_name'] . '</a>' : $topic['starter_name'], '</p>
						</div>
						<div class="floatleft lastpost">
							<p>', sprintf($txt['order_last_post_by'], !empty($topic['updated_id']) ? '<a href="<URL>?action=profile;u=' . $topic['updated_id'] . '">' . $topic['updated_name'] . '</a>' : $topic['updated_name'], on_timeformat($topic['poster_time'])), '</p>
						</div>
						<br class="clear">
						<input type="hidden" name="order[]" value="', $topic['id_topic'], '">
					</li>';

	echo '
				</ul>
				<br>
				<div class="right">
					<input type="submit" value="', $txt['save'], '" class="submit">
				</div>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</div>';

	add_js('
		$(\'#sortable\').sortable({ handle: \'.handle\' });
		$(\'#sortable\').disableSelection();');
}
