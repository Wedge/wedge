<?php
/**
 * Displays the interface for editing news items as well as composing and sending the newsletter.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

// Form for editing current news on the site.
function template_edit_news()
{
	global $context, $txt;

	echo '
		<div class="windowbg2 wrc">
			<form action="<URL>?action=admin;area=news;sa=editnews" method="post">';

	// Reuse the privacy keys from thoughts but some don't quite tie up with the meanings here.
	$display = array(
		'e' => 'everyone',
		'm' => 'members',
		's' => 'friends',
		'a' => 'justme',
	);
	if (empty($context['admin_current_news']))
		echo '
				<div class="information">', $txt['editnews_no_news'], '</div>';
	else
	{
		echo '
				<ul id="sortable">';

		foreach ($context['admin_current_news'] as $admin_news)
		{
			if (!isset($display[$admin_news['privacy']]))
				$admin_news['privacy'] = 'a';

			// Order can be passed through as-is, but the others have to be incremented so that they're never 0.
			echo '
					<li class="windowbg">
						<span class="handle"></span>
						<div class="floatright">
							<input type="submit" name="modify[', ($admin_news['id'] + 1), ']" value="', $txt['modify'], '" class="submit">
							<input type="submit" name="delete[', ($admin_news['id'] + 1), ']" value="', $txt['delete'], '" class="delete" onclick="return ask(', JavaScriptEscape($txt['editnews_remove_confirm']), ', e);">
							<input type="hidden" name="order[]" value="', $admin_news['id'], '">
							<br>
							<div style="margin-top:0.5em"><div class="privacy_', $display[$admin_news['privacy']], '"></div> ', $txt['editnews_privacy_' . $admin_news['privacy']], '</div>
						</div>
						', $admin_news['parsed'], '
						<br class="clear">
					</li>';
		}
		echo '
				</ul>';
	}

	echo '
				<br class="clear">
				<div class="right">
					<input type="submit" name="add" value="', $txt['editnews_add'], '" class="new">
					<input type="submit" name="saveorder" value="', $txt['editnews_saveorder'], '" class="save" id="saveorder">
				</div>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</div>';

	add_js('
		$(\'#sortable\').sortable({ handle: \'.handle\', update: function (event, ui) { $(\'#saveorder\').show(); } });
		$(\'#sortable\').disableSelection();
		$(\'#saveorder\').hide();');
}

function template_edit_news_item()
{
	global $context, $txt;

	echo '
		<we:cat>', $context['page_title'], '</we:cat>';

	$display = array(
		'e' => 'everyone',
		'm' => 'members',
		's' => 'friends',
		'a' => 'justme',
	);

	echo '
		<form action="<URL>?action=admin;area=news;sa=editnews" method="post">
			<div class="windowbg2 wrc">
				<div class="buttons">',
					$context['postbox']->outputEditor(), '
				</div>
				<br>
				<div class="floatright">',
					$context['postbox']->outputButtons(), '
				</div>
				<div class="privacy">
					', $txt['editnews_visible'], '
					<select name="privacy">';
	foreach ($display as $id => $item)
		echo '
						<option value="', $id, '"', $context['editnews']['privacy'] == $id ? ' selected' : '', '>&lt;div class="privacy_', $item, '"&gt;&lt;/div&gt; ', $txt['editnews_privacy_' . $id], '</option>';
	echo '
					</select>
				</div>

				<br class="clear">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="newsid" value="', $context['editnews']['id'], '">
		</form>';
}

function template_email_members()
{
	global $context, $txt;

	// This is some javascript for the simple/advanced toggling stuff.
	add_js('
	function toggleAdvanced()
	{
		$("#goadvanced div").toggleClass("fold").hasClass("fold") ? $("#advanced_settings_div").slideDown(150) : $("#advanced_settings_div").slideUp(200);
		return false;
	}');

	echo '
		<form action="<URL>?action=admin;area=news;sa=mailingcompose" class="flow_hidden" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['admin_newsletters'], '
			</we:cat>
			<div class="information">
				', $txt['admin_news_select_recipients'], '
			</div>
			<div class="windowbg wrc">
				<dl class="settings newsletter">
					<dt>
						<strong>', $txt['admin_news_select_group'], ':</strong>
						<dfn>', $txt['admin_news_select_group_desc'], '</dfn>
					</dt>
					<dd>';

	foreach ($context['groups'] as $group)
		if (!$group['is_post'])
			echo '
						<label><input type="checkbox" data-type="normal-include" name="groups[', $group['id'], ']" id="groups_', $group['id'], '" value="', $group['id'], '" checked> <span class="group', $group['id'], '">', $group['name'], '</span></label> &ndash; ', $group['member_count'], ' <span class="people"></span><br>';

	echo '
						<br>
						<label><input type="checkbox" checked onclick="$(\'input[data-type=normal-include]\').prop(\'checked\', this.checked);"> <em>', $txt['check_all'], '</em></label>
					</dd>
					<dt>
						<strong>', $txt['admin_news_select_postgroup'], ':</strong>
						<dfn>', $txt['admin_news_select_group_desc'], '</dfn>
					</dt>
					<dd>';

	foreach ($context['groups'] as $group)
		if ($group['is_post'])
			echo '
						<label><input type="checkbox" data-type="post-include" name="groups[', $group['id'], ']" id="groups_', $group['id'], '" value="', $group['id'], '" checked> <span class="group', $group['id'], '">', $group['name'], '</span></label> &ndash; ', $group['member_count'], ' <span class="people"></span>, ', $group['min_posts'], ' <span class="posts"></span><br>';

	echo '
						<br>
						<label><input type="checkbox" checked onclick="$(\'input[data-type=post-include]\').prop(\'checked\', this.checked);"> <em>', $txt['check_all'], '</em></label>
					</dd>
				</dl><br class="clear">
			</div>
			<br>

			<div class="hide" id="advanced_select_div">
				<we:cat>
					<a href="#" onclick="return toggleAdvanced();" id="goadvanced"><div class="foldable"></div> ', $txt['advanced'], '</a>
				</we:cat>
			</div>

			<div class="windowbg2 wrc hide" id="advanced_settings_div">
				<dl class="settings">
					<dt>
						<strong>', $txt['admin_news_select_email'], ':</strong>
						<dfn>', $txt['admin_news_select_email_desc'], '</dfn>
					</dt>
					<dd>
						<textarea name="emails" rows="5" style="', we::is('ie8') ? 'width: 635px; max-width: 98%; min-width: 98%' : 'width: 98%', '"></textarea>
					</dd>
					<dt>
						<strong>', $txt['admin_news_select_members'], ':</strong>
						<dfn>', $txt['admin_news_select_members_desc'], '</dfn>
					</dt>
					<dd>
						<input name="members" id="members" value="" size="30">
					</dd>
				</dl>
				<hr>
				<dl class="settings newsletter">
					<dt>
						<strong>', $txt['admin_news_select_excluded_groups'], ':</strong>
						<dfn>', $txt['admin_news_select_excluded_groups_desc'], '</dfn>
					</dt>
					<dd>';

	foreach ($context['groups'] as $group)
		if (!$group['is_post'])
			echo '
						<label><input type="checkbox" data-type="normal-exclude" name="exclude_groups[', $group['id'], ']" id="exclude_groups_', $group['id'], '" value="', $group['id'], '"> <span class="group', $group['id'], '">', $group['name'], '</span></label> &ndash; ', $group['member_count'], ' <span class="people"></span><br>';

	echo '
						<br>
						<label><input type="checkbox" onclick="$(\'input[data-type=normal-exclude]\').prop(\'checked\', this.checked);"> <em>', $txt['check_all'], '</em></label><br>
					</dd>
					<dt>
						<strong>', $txt['admin_news_select_excluded_postgroups'], ':</strong>
						<dfn>', $txt['admin_news_select_excluded_groups_desc'], '</dfn>
					</dt>
					<dd>';

	foreach ($context['groups'] as $group)
		if ($group['is_post'])
			echo '
						<label><input type="checkbox" data-type="post-exclude" name="exclude_groups[', $group['id'], ']" id="exclude_groups_', $group['id'], '" value="', $group['id'], '"> <span class="group', $group['id'], '">', $group['name'], '</span></label> &ndash; ', $group['member_count'], ' <span class="people"></span>, ', $group['min_posts'], ' <span class="posts"></span><br>';

	echo '
						<br>
						<label><input type="checkbox" onclick="$(\'input[data-type=post-exclude]\').prop(\'checked\', this.checked);"> <em>', $txt['check_all'], '</em></label><br>
					</dd>
					<dt>
						<strong>', $txt['admin_news_select_excluded_members'], ':</strong>
						<dfn>', $txt['admin_news_select_excluded_members_desc'], '</dfn>
					</dt>
					<dd>
						<input name="exclude_members" id="exclude_members" value="" size="30">
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						<label for="email_force"><strong>', $txt['admin_news_select_override_notify'], ':</strong></label>
						<dfn>', $txt['email_force'], '</dfn>
					</dt>
					<dd>
						<input type="checkbox" name="email_force" id="email_force" value="1">
					</dd>
				</dl><br class="clear">
			</div>
			<div class="right">
				<input type="submit" value="', $txt['admin_next'], '" class="submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>';

	// Make the javascript stuff visible.
	add_js_file('suggest.js');
	add_js('
	$("#advanced_select_div").show();
	new weAutoSuggest({
		', min_chars(), ',
		bItemList: true,
		sControlId: \'members\',
		sPostName: \'member_list\',
		aListItems: {}
	});
	new weAutoSuggest({
		', min_chars(), ',
		bItemList: true,
		sControlId: \'exclude_members\',
		sPostName: \'exclude_member_list\',
		aListItems: {}
	});');
}

function template_email_members_compose()
{
	global $context, $txt;

	echo '
		<form action="<URL>?action=admin;area=news;sa=mailingsend" method="post" accept-charset="UTF-8">
			<we:cat>
				<a href="<URL>?action=help;in=email_members" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
				', $txt['admin_newsletters'], '
			</we:cat>
			<div class="information">
				', $txt['email_variables'], '
			</div>
			<div class="windowbg wrc">
				<p>
					<input name="subject" size="60" value="', $context['default_subject'], '">
				</p>
				<p>
					<textarea cols="70" rows="9" name="message" class="editor">', $context['default_message'], '</textarea>
				</p>
				<ul class="reset">
					<li><label><input type="checkbox" name="send_pm" id="send_pm" onclick="if (this.checked && ', $context['total_emails'], ' != 0 && !ask(', JavaScriptEscape($txt['admin_news_cannot_pm_emails_js']), ', e)) return false; this.form.parse_html.disabled = this.checked; this.form.send_html.disabled = this.checked;"> ', $txt['email_as_pms'], '</label></li>
					<li><label><input type="checkbox" name="send_html" id="send_html" onclick="this.form.parse_html.disabled = !this.checked;"> ', $txt['email_as_html'], '</label></li>
					<li><label><input type="checkbox" name="parse_html" id="parse_html" checked disabled> ', $txt['email_parsed_html'], '</label></li>
				</ul>
				<p>
					<input type="submit" value="', $txt['sendtopic_send'], '" class="submit">
				</p>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="email_force" value="', $context['email_force'], '">
			<input type="hidden" name="total_emails" value="', $context['total_emails'], '">
			<input type="hidden" name="max_id_member" value="', $context['max_id_member'], '">';

	foreach ($context['recipients'] as $key => $values)
		echo '
			<input type="hidden" name="', $key, '" value="', implode(($key == 'emails' ? ';' : ','), $values), '">';

	echo '
		</form>';
}

function template_email_members_send()
{
	global $context, $txt;

	echo '
		<form action="<URL>?action=admin;area=news;sa=mailingsend" method="post" accept-charset="UTF-8" name="autoSubmit" id="autoSubmit">
			<we:cat>
				<a href="<URL>?action=help;in=email_members" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
				', $txt['admin_newsletters'], '
			</we:cat>
			<div class="windowbg wrc">
				<p>
					<strong>', $context['percentage_done'], '% ', $txt['email_done'], '</strong>
				</p>
				<input type="submit" name="b" value="', westr::htmlspecialchars($txt['email_continue']), '" class="submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="subject" value="', $context['subject'], '">
				<input type="hidden" name="message" value="', $context['message'], '">
				<input type="hidden" name="start" value="', $context['start'], '">
				<input type="hidden" name="total_emails" value="', $context['total_emails'], '">
				<input type="hidden" name="max_id_member" value="', $context['max_id_member'], '">
				<input type="hidden" name="send_pm" value="', $context['send_pm'], '">
				<input type="hidden" name="send_html" value="', $context['send_html'], '">
				<input type="hidden" name="parse_html" value="', $context['parse_html'], '">';

	// All the things we must remember!
	foreach ($context['recipients'] as $key => $values)
		echo '
				<input type="hidden" name="', $key, '" value="', implode(($key == 'emails' ? ';' : ','), $values), '">';

	echo '
			</div>
		</form>';

	add_js_inline('
	var countdown = 2;
	doAutoSubmit();

	function doAutoSubmit()
	{
		if (countdown == 0)
			document.forms.autoSubmit.submit();
		else if (countdown == -1)
			return;

		document.forms.autoSubmit.b.value = ', JavaScriptEscape($txt['email_continue']), ' + " (" + countdown + ")";
		countdown--;

		setTimeout(doAutoSubmit, 1000);
	}');
}
