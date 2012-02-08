<?php
/**
 * Wedge
 *
 * Displays the interface for editing news items as well as composing and sending the newsletter.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Form for editing current news on the site.
function template_edit_news()
{
	global $context, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=news;sa=editnews" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify">
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg">
						<th class="first_th w50">', $txt['admin_edit_news'], '</th>
						<th class="left" style="width: 45%">', $txt['preview'], '</th>
						<th class="last_th center" style="width: 5%"><input type="checkbox" onclick="invertAll(this, this.form);"></th>
					</tr>
				</thead>
				<tbody>';

	// Loop through all the current news items so you can edit/remove them.
	foreach ($context['admin_current_news'] as $admin_news)
		echo '
					<tr class="windowbg2 center">
						<td>
							<div style="margin-bottom: 2ex;"><textarea rows="3" cols="65" name="news[]" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 85%; min-width: 85%' : 'width: 85%') . ';">', $admin_news['unparsed'], '</textarea></div>
						</td>
						<td class="left top">
							<div style="overflow: auto; width: 100%; height: 10ex;">', $admin_news['parsed'], '</div>
						</td>
						<td>
							<input type="checkbox" name="remove[]" value="', $admin_news['id'], '">
						</td>
					</tr>';

	// This provides an empty text box to add a news item to the site.
	echo '
					<tr id="moreNews" class="windowbg2 center hide">
						<td><div id="moreNewsItems"></div></td>
						<td></td>
						<td></td>
					</tr>
				</tbody>
			</table>
			<div class="floatleft padding">
				<div id="moreNewsItems_link" class="hide"><a href="#" onclick="addNewsItem(); return false;">', $txt['editnews_clickadd'], '</a></div>';

	add_js('
	$("#moreNewsItems_link").show();
	function addNewsItem()
	{
		$("#moreNews").show();
		$("#moreNewsItems").append(\'<div style="margin-bottom: 2ex;"><textarea rows="3" cols="65" name="news[]" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 85%; min-width: 85%' : 'width: 85%') . ';"><\' + \'/textarea><\' + \'/div>\');
	}');

	echo '
				<noscript>
					<div style="margin-bottom: 2ex;"><textarea rows="3" cols="65" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 85%; min-width: 85%' : 'width: 85%') . ';" name="news[]"></textarea></div>
				</noscript>
			</div>
			<div class="floatright padding">
				<input type="submit" name="save_items" value="', $txt['save'], '" class="save"> <input type="submit" name="delete_selection" value="', $txt['editnews_remove_selected'], '" onclick="return confirm(', JavaScriptEscape($txt['editnews_remove_confirm']), ');" class="delete">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';
}

function template_email_members()
{
	global $context, $settings, $txt, $scripturl;

	// This is some javascript for the simple/advanced toggling stuff.
	add_js('
	function toggleAdvanced()
	{
		$("#advanced_settings_div, #gosimple, #goadvanced").toggle();
		return false;
	}');

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=news;sa=mailingcompose" class="flow_hidden" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['admin_newsletters'], '
			</we:cat>
			<div class="information">
				', $txt['admin_news_select_recipients'], '
			</div>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<strong>', $txt['admin_news_select_group'], ':</strong>
						<dfn>', $txt['admin_news_select_group_desc'], '</dfn>
					</dt>
					<dd>';

	foreach ($context['groups'] as $group)
				echo '
						<label><input type="checkbox" name="groups[', $group['id'], ']" id="groups_', $group['id'], '" value="', $group['id'], '" checked> ', $group['name'], '</label> <em>(', $group['member_count'], ')</em><br>';

	echo '
						<br>
						<label><input type="checkbox" id="checkAllGroups" checked onclick="invertAll(this, this.form, \'groups\');"> <em>', $txt['check_all'], '</em></label>
					</dd>
				</dl><br class="clear">
			</div>
			<br>

			<div class="hide" id="advanced_select_div">
				<we:cat>
					<a href="#" onclick="return toggleAdvanced();" id="goadvanced"><img src="', $settings['images_url'], '/selected.gif" alt="', $txt['advanced'], '" style="vertical-align: 0">&nbsp;', $txt['advanced'], '</a>
					<a href="#" onclick="return toggleAdvanced();" id="gosimple" class="hide"><img src="', $settings['images_url'], '/sort_down.gif" alt="', $txt['simple'], '" style="vertical-align: 0">&nbsp;<strong>', $txt['simple'], '</strong></a>
				</we:cat>
			</div>

			<div class="windowbg2 wrc hide" id="advanced_settings_div">
				<dl class="settings">
					<dt>
						<strong>', $txt['admin_news_select_email'], ':</strong>
						<dfn>', $txt['admin_news_select_email_desc'], '</dfn>
					</dt>
					<dd>
						<textarea name="emails" rows="5" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 98%; min-width: 98%' : 'width: 98%') . '"></textarea>
					</dd>
					<dt>
						<strong>', $txt['admin_news_select_members'], ':</strong>
						<dfn>', $txt['admin_news_select_members_desc'], '</dfn>
					</dt>
					<dd>
						<span id="members_container"></span>
						<input type="text" name="members" id="members" value="" size="30">
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						<strong>', $txt['admin_news_select_excluded_groups'], ':</strong>
						<dfn>', $txt['admin_news_select_excluded_groups_desc'], '</dfn>
					</dt>
					<dd>';

	foreach ($context['groups'] as $group)
				echo '
						<label><input type="checkbox" name="exclude_groups[', $group['id'], ']" id="exclude_groups_', $group['id'], '" value="', $group['id'], '"> ', $group['name'], '</label> <em>(', $group['member_count'], ')</em><br>';

	echo '
						<br>
						<label><input type="checkbox" id="checkAllGroupsExclude" onclick="invertAll(this, this.form, \'exclude_groups\');"> <em>', $txt['check_all'], '</em></label><br>
					</dd>
					<dt>
						<strong>', $txt['admin_news_select_excluded_members'], ':</strong>
						<dfn>', $txt['admin_news_select_excluded_members_desc'], '</dfn>
					</dt>
					<dd>
						<span id="exclude_members_container"></span>
						<input type="text" name="exclude_members" id="exclude_members" value="" size="30">
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
			<div class="righttext">
				<input type="submit" value="', $txt['admin_next'], '" class="submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>
	</div>
	<br class="clear">';

	// Make the javascript stuff visible.
	add_js_file('scripts/suggest.js');

	add_js('
	$("#advanced_select_div").show();
	new weAutoSuggest({
		sControlId: \'members\',
		bItemList: true,
		sPostName: \'member_list\',
		sURLMask: \'action=profile;u=%item_id%\',
		sTextDeleteItem: ', JavaScriptEscape($txt['autosuggest_delete_item']), ',
		sItemListContainerId: \'members_container\',
		aListItems: []
	});
	new weAutoSuggest({
		sControlId: \'exclude_members\',
		bItemList: true,
		sPostName: \'exclude_member_list\',
		sURLMask: \'action=profile;u=%item_id%\',
		sTextDeleteItem: ', JavaScriptEscape($txt['autosuggest_delete_item']), ',
		sItemListContainerId: \'exclude_members_container\',
		aListItems: []
	});');
}

function template_email_members_compose()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=news;sa=mailingsend" method="post" accept-charset="UTF-8">
			<we:cat>
				<a href="', $scripturl, '?action=help;in=email_members" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
				', $txt['admin_newsletters'], '
			</we:cat>
			<div class="information">
				', $txt['email_variables'], '
			</div>
			<div class="windowbg wrc">
				<p>
					<input type="text" name="subject" size="60" value="', $context['default_subject'], '">
				</p>
				<p>
					<textarea cols="70" rows="9" name="message" class="editor">', $context['default_message'], '</textarea>
				</p>
				<ul class="reset">
					<li><label><input type="checkbox" name="send_pm" id="send_pm" onclick="if (this.checked && ', $context['total_emails'], ' != 0 && !confirm(', JavaScriptEscape($txt['admin_news_cannot_pm_emails_js']), ')) return false; this.form.parse_html.disabled = this.checked; this.form.send_html.disabled = this.checked;"> ', $txt['email_as_pms'], '</label></li>
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
		</form>
	</div>
	<br class="clear">';
}

function template_email_members_send()
{
	global $context, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=news;sa=mailingsend" method="post" accept-charset="UTF-8" name="autoSubmit" id="autoSubmit">
			<we:cat>
				<a href="', $scripturl, '?action=help;in=email_members" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
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
		</form>
	</div>
	<br class="clear">';

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

?>