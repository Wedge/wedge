<?php
/**
 * Wedge
 *
 * The interface for creating and editing bans.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_ban_edit()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	echo '
	<div id="manage_bans">

		<we:cat>
			', $context['ban']['is_new'] ? $txt['ban_add_new'] : $txt['ban_edit'] . ' \'' . $context['ban']['name'] . '\'', '
		</we:cat>';

	if ($context['ban']['is_new'])
		echo '
		<div class="information">', $txt['ban_add_notes'], '</div>';

	echo '
		<div class="windowbg wrc">
			<form action="', $scripturl, '?action=admin;area=ban;sa=edit" method="post" accept-charset="UTF-8" onsubmit="if (this.ban_name.value == \'\') { say(', JavaScriptEscape($txt['ban_name_empty']), '); return false; } if (this.partial_ban.checked && !(this.cannot_post.checked || this.cannot_register.checked || this.cannot_login.checked)) { say(', JavaScriptEscape($txt['ban_restriction_empty']), '); return false; }">
				<dl class="settings">
					<dt>
						<strong>', $txt['ban_name'], ':</strong>
					</dt>
					<dd>
						<input type="text" name="ban_name" value="', $context['ban']['name'], '" size="25" maxlength="20">
					</dd>
					<dt>
						<strong>', $txt['ban_reason'], ':</strong>
						<dfn>', $txt['ban_reason_desc'], '</dfn>
					</dt>
					<dd>
						<textarea name="reason" cols="50" rows="3">', $context['ban']['reason'], '</textarea>
					</dd>
					<dt>
						<strong>', $txt['ban_notes'], ':</strong>
						<dfn>', $txt['ban_notes_desc'], '</dfn>
					</dt>
					<dd>
						<textarea name="notes" cols="50" rows="3">', $context['ban']['notes'], '</textarea>
					</dd>
				</dl>
				<fieldset class="ban_settings floatleft">
					<legend>
						', $txt['ban_expiration'], '
					</legend>
					<label><input type="radio" name="expiration" value="never" id="never_expires" onclick="updateFormStatus();"', $context['ban']['expiration']['status'] == 'never' ? ' checked' : '', '> ', $txt['never'], '</label><br>
					<label><input type="radio" name="expiration" value="one_day" id="expires_one_day" onclick="updateFormStatus();"', $context['ban']['expiration']['status'] == 'still_active_but_we_re_counting_the_days' ? ' checked' : '', '> ', $txt['ban_will_expire_within'], '</label>:
					<input type="text" name="expire_date" id="expire_date" size="3" value="', $context['ban']['expiration']['days'], '"> ', $txt['ban_days'], '<br>
					<label><input type="radio" name="expiration" value="expired" id="already_expired" onclick="updateFormStatus();"', $context['ban']['expiration']['status'] == 'expired' ? ' checked' : '', '> ', $txt['ban_expired'], '</label>
				</fieldset>
				<fieldset class="ban_settings floatright">
					<legend>
						', $txt['ban_restriction'], '
					</legend>
					<label><input type="radio" name="full_ban" id="full_ban" value="1" onclick="updateFormStatus();"', $context['ban']['cannot']['access'] ? ' checked' : '', '> ', $txt['ban_full_ban'], '</label><br>
					<label><input type="radio" name="full_ban" id="partial_ban" value="0" onclick="updateFormStatus();"', !$context['ban']['cannot']['access'] ? ' checked' : '', '> ', $txt['ban_partial_ban'], '</label><br>
					<label><input type="checkbox" name="cannot_post" id="cannot_post" value="1"', $context['ban']['cannot']['post'] ? ' checked' : '', ' class="ban_restriction"> ', $txt['ban_cannot_post'], '</label> (<a href="', $scripturl, '?action=help;in=ban_cannot_post" onclick="return reqWin(this);">?</a>)<br>
					<label><input type="checkbox" name="cannot_register" id="cannot_register" value="1"', $context['ban']['cannot']['register'] ? ' checked' : '', ' class="ban_restriction"> ', $txt['ban_cannot_register'], '</label><br>
					<label><input type="checkbox" name="cannot_login" id="cannot_login" value="1"', $context['ban']['cannot']['login'] ? ' checked' : '', ' class="ban_restriction"> ', $txt['ban_cannot_login'], '</label><br>
				</fieldset>
				<br class="clear_right">';

	if (!empty($context['ban_suggestions']))
	{
		echo '
				<fieldset>
					<legend>
						', $txt['ban_triggers'], '
					</legend>
					<dl class="settings">
						<dt>
							<label><input type="checkbox" name="ban_suggestion[]" id="main_ip_check" value="main_ip"> ', $txt['ban_on_ip'], '</label>
						</dt>
						<dd>
							<input type="text" name="main_ip" value="', $context['ban_suggestions']['main_ip'], '" size="50" onfocus="$(\'#main_ip_check\').attr(\'checked\', true);">
						</dd>';

		if (empty($settings['disableHostnameLookup']))
			echo '
						<dt>
							<label><input type="checkbox" name="ban_suggestion[]" id="hostname_check" value="hostname"> ', $txt['ban_on_hostname'], '</label>
						</dt>
						<dd>
							<input type="text" name="hostname" value="', $context['ban_suggestions']['hostname'], '" size="50" onfocus="$(\'#hostname_check\').attr(\'checked\', true);">
						</dd>';

		echo '
						<dt>
							<label><input type="checkbox" name="ban_suggestion[]" id="email_check" value="email" checked> ', $txt['ban_on_email'], '</label>
						</dt>
						<dd>
							<input type="email" name="email" value="', $context['ban_suggestions']['email'], '" size="50" onfocus="$(\'#email_check\').attr(\'checked\', true);">
						</dd>
						<dt>
							<label><input type="checkbox" name="ban_suggestion[]" id="user_check" value="user" checked> ', $txt['ban_on_username'], '</label>:
						</dt>
						<dd>';

		if (empty($context['ban_suggestions']['member']['id']))
			echo '
							<input type="text" name="user" id="user" value="" size="40" onfocus="$(\'#user_check\').attr(\'checked\', true);">';
		else
			echo '
							', $context['ban_suggestions']['member']['link'], '
							<input type="hidden" name="bannedUser" value="', $context['ban_suggestions']['member']['id'], '">';
		echo '
						</dd>';

		if (!empty($context['ban_suggestions']['message_ips']))
		{
			echo '
					</dl>
					<div>', $txt['ips_in_messages'], ':</div>
					<dl class="settings">';

			foreach ($context['ban_suggestions']['message_ips'] as $ip)
				echo '
						<dt>
							<input type="checkbox" name="ban_suggestion[ips][]" value="', $ip, '">
						</dt>
						<dd>
							', $ip, '
						</dd>';
		}

		if (!empty($context['ban_suggestions']['error_ips']))
		{
			echo '
					</dl>
					<div>', $txt['ips_in_errors'], '</div>
					<dl class="settings">';

			foreach ($context['ban_suggestions']['error_ips'] as $ip)
				echo '
						<dt>
							<input type="checkbox" name="ban_suggestion[ips][]" value="', $ip, '">
						</dt>
						<dd>
							', $ip, '
						</dd>';
		}

		echo '
					</dl>
				</fieldset>';
	}

	echo '
				<div class="right">
					<input type="submit" name="', $context['ban']['is_new'] ? 'add_ban' : 'modify_ban', '" value="', $context['ban']['is_new'] ? $txt['ban_add'] . '" class="new"' : $txt['ban_modify'] . '" class="save"', '>
					<input type="hidden" name="old_expire" value="', $context['ban']['expiration']['days'], '">
					<input type="hidden" name="bg" value="', $context['ban']['id'], '">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</form>
		</div>';

	if (!$context['ban']['is_new'] && empty($context['ban_suggestions']))
	{
		echo '
			<br>
			<form action="', $scripturl, '?action=admin;area=ban;sa=edit" method="post" accept-charset="UTF-8" style="padding: 0; margin: 0" onsubmit="return ask(', JavaScriptEscape($txt['ban_remove_selected_triggers_confirm']), ', e);">
				<table class="table_grid w100 cs0">
					<thead>
						<tr class="catbg">
							<th style="width: 65%" class="left">', $txt['ban_banned_entity'], '</td>
							<th style="width: 15%" class="center">', $txt['ban_hits'], '</td>
							<th style="width: 15%" class="center">', $txt['ban_actions'], '</td>
							<th style="width: 5%" class="center"><input type="checkbox" onclick="invertAll(this, this.form, \'ban_items\');"></td>
						</tr>
					</thead>
					<tbody>';
		if (empty($context['ban_items']))
			echo '
						<tr class="windowbg2">
							<td colspan="4">(', $txt['ban_no_triggers'], ')</td>
						</tr>';
		else
		{
			foreach ($context['ban_items'] as $ban_item)
			{
				echo '
						<tr class="windowbg2 center">
							<td class="left">';

				if ($ban_item['type'] == 'ip')
					echo '
								<strong>', $txt['ip'], ':</strong>&nbsp;', $ban_item['ip'];
				elseif ($ban_item['type'] == 'hostname')
					echo '
								<strong>', $txt['hostname'], ':</strong>&nbsp;', $ban_item['hostname'];
				elseif ($ban_item['type'] == 'email')
					echo '
								<strong>', $txt['email'], ':</strong>&nbsp;', $ban_item['email'];
				elseif ($ban_item['type'] == 'user')
					echo '
								<strong>', $txt['username'], ':</strong>&nbsp;', $ban_item['user']['link'];

				echo '
							</td>
							<td class="windowbg">', $ban_item['hits'], '</td>
							<td class="windowbg"><a href="', $scripturl, '?action=admin;area=ban;sa=edittrigger;bg=', $context['ban']['id'], ';bi=', $ban_item['id'], '">', $txt['ban_edit_trigger'], '</a></td>
							<td class="windowbg2"><input type="checkbox" name="ban_items[]" value="', $ban_item['id'], '"></td>
						</tr>';
			}
		}

		echo '
					</tbody>
				</table>
				<div class="additional_rows">
					<div class="floatleft">
						[<a href="', $scripturl, '?action=admin;area=ban;sa=edittrigger;bg=', $context['ban']['id'], '">', $txt['ban_add_trigger'], '</a>]
					</div>
					<div class="floatright">
						<input type="submit" name="remove_selection" value="', $txt['ban_remove_selected_triggers'], '" class="delete">
					</div>
				</div>
				<br class="clear">
				<input type="hidden" name="bg" value="', $context['ban']['id'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>';

	}

	echo '
	</div>
	<br class="clear">';

	add_js_file('scripts/suggest.js');

	add_js_inline('
	function updateFormStatus()
	{
		$("#expire_date").attr("disabled", !$("#expires_one_day").attr("checked"));
		$("#cannot_post,#cannot_register,#cannot_login").attr("disabled", $("#full_ban").attr("checked"));
	}
	updateFormStatus();');

	// Auto suggest only needed for adding new bans, not editing
	if ($context['ban']['is_new'] && empty($_REQUEST['u']))
		add_js('
	new weAutoSuggest({
		sControlId: \'user\',
		sTextDeleteItem: ', JavaScriptEscape($txt['autosuggest_delete_item']), '
	});');
}

function template_ban_edit_trigger()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	echo '
	<div id="manage_bans">
		<form action="', $scripturl, '?action=admin;area=ban;sa=edit" method="post" accept-charset="UTF-8">
			<we:cat>
				', $context['ban_trigger']['is_new'] ? $txt['ban_add_trigger'] : $txt['ban_edit_trigger_title'], '
			</we:cat>
			<div class="windowbg wrc">
				<fieldset>
					<legend>
						', $txt['ban_triggers'], '
					</legend>
					<dl class="settings">
						<dt>
							<input type="radio" name="bantype" value="ip_ban"', $context['ban_trigger']['ip']['selected'] ? ' checked' : '', '>
							', $txt['ban_on_ip'], '
						</dt>
						<dd>
							<input type="text" name="ip" value="', $context['ban_trigger']['ip']['value'], '" size="50" onfocus="$(\':radio[value=ip_ban]\').attr(\'checked\', true);">
						</dd>';

	if (empty($settings['disableHostnameLookup']))
		echo '
						<dt>
							<input type="radio" name="bantype" value="hostname_ban"', $context['ban_trigger']['hostname']['selected'] ? ' checked' : '', '>
							', $txt['ban_on_hostname'], '
						</dt>
						<dd>
							<input type="text" name="hostname" value="', $context['ban_trigger']['hostname']['value'], '" size="50" onfocus="$(\':radio[value=hostname_ban]\').attr(\'checked\', true);">
						</dd>';

	echo '
						<dt>
							<input type="radio" name="bantype" value="email_ban"', $context['ban_trigger']['email']['selected'] ? ' checked' : '', '>
							', $txt['ban_on_email'], '
						</dt>
						<dd>
							<input type="email" name="email" value="', $context['ban_trigger']['email']['value'], '" size="50" onfocus="$(\':radio[value=email_ban]\').attr(\'checked\', true);">
						</dd>
						<dt>
							<input type="radio" name="bantype" value="user_ban"', $context['ban_trigger']['banneduser']['selected'] ? ' checked' : '', '>
							', $txt['ban_on_username'], '
						</dt>
						<dd>
							<input type="text" name="user" id="user" value="', $context['ban_trigger']['banneduser']['value'], '" size="50" onfocus="$(\':radio[value=user_ban]\').attr(\'checked\', true);">
						</dd>
					</dl>
				</fieldset>
				<div class="right">
					<input type="submit" name="', $context['ban_trigger']['is_new'] ? 'add_new_trigger' : 'edit_trigger', '" value="', $context['ban_trigger']['is_new'] ? $txt['ban_add_trigger_submit'] . '" class="new"' : $txt['ban_edit_trigger_submit'] . '" class="save"', '>
				</div>
			</div>
			<input type="hidden" name="bi" value="' . $context['ban_trigger']['id'] . '">
			<input type="hidden" name="bg" value="' . $context['ban_trigger']['group'] . '">
			<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
		</form>
	</div>
	<br class="clear">';

	add_js_file('scripts/suggest.js');

	add_js('
	new weAutoSuggest({
		sControlId: \'user\',
		sTextDeleteItem: ', JavaScriptEscape($txt['autosuggest_delete_item']), '
	});');
}

function template_ban_details()
{
	global $context, $txt;

	echo '
		<form action="<URL>?action=admin;area=ban;sa=edit;save" method="post" accept-charset="UTF-8">';

	if (!empty($context['errors']))
		echo '
			<div class="errorbox" id="errors">
				<h3 id="error_serious">', $txt['error_while_submitting'], '</h3>
				<ul class="error" id="error_list">
					<li>', implode('</li><li>', $context['errors']), '</li>
				</ul>
			</div>';

	echo '
			<div class="windowbg2 wrc">
				<fieldset>
					<legend>', $txt['ban_hardness_header'], '</legend>
					<p>', $txt['ban_hardnesses'], '</p>
					<dl class="settings">
						<dt>', $txt['ban_hardness_title'], '</dt>
						<dd>
							<select name="hardness">
								<option value="soft"', $context['ban_details']['hardness'] == 'soft' ? ' selected' : '', '>&lt;div class="ban_selector ban_soft"&gt;&lt;/div&gt; ', $txt['ban_hardness_soft'], '</option>
								<option value="hard"', $context['ban_details']['hardness'] == 'hard' ? ' selected' : '', '>&lt;div class="ban_selector ban_hard"&gt;&lt;/div&gt; ', $txt['ban_hardness_hard'], '</option>
							</select>
						</dd>
					</dl>
				</fieldset>
				<fieldset>
					<legend>', $txt['ban_type_header'], '</legend>
					<p>', $txt['ban_type_description'], '</p>
					<dl class="settings">
						<dt style="margin-bottom: 40px">', $txt['ban_type_title'], '</dt>
						<dd>
							<select name="ban_type" id="ban_type" onchange="updateForm()">';
	foreach ($context['ban_types'] as $type)
		echo '
								<option value="', $type, '"', $type == $context['ban_details']['ban_type'] ? ' selected' : '', '>&lt;div class="ban_selector ban_items_', $type, '"&gt;&lt;/div&gt; ', $txt['ban_type_title_' . $type], '</option>';
	echo '
							</select>
						</dd>';

	// And now banning an individual mmember
	echo '
						<dt class="ban_criteria_id_member">', $txt['ban_type_id_member_type'], '</dt>
						<dd class="ban_criteria_id_member">
							<input type="text" value="', !empty($context['ban_details']['ban_member']) ? $context['ban_details']['ban_member'] : '', '" id="ban_id_member_content" name="ban_id_member_content" size="15" maxlength="100">
						</dd>';

	// Banning types of names
	echo '
						<dd class="ban_criteria_member_name">', $txt['ban_member_note'], '</dd>
						<dt class="ban_criteria_member_name">', $txt['ban_type_member_name'], '</dt>
						<dd class="ban_criteria_member_name">
							<select name="ban_member_name_select">';
	$types = array('begin' => 'beginning', 'end' => 'ending', 'contain' => 'containing', 'match' => 'matching');
	foreach ($types as $key => $value)
		echo '
								<option value="', $key, '"', !empty($context['ban_details']['name_type']) && $context['ban_details']['name_type'] == $value ? ' selected' : '', '>', $txt['ban_member_names_select_' . $value], '</option>';
	echo '
							</select>
							<input type="text" name="ban_member_name_content" size="20" maxlength="100" value="', !empty($context['ban_details']['ban_name']) ? $context['ban_details']['ban_name'] : '', '">
						</dd>
						<dt class="ban_criteria_member_name">', $txt['ban_member_case_sensitive'], ' <dfn>', $txt['ban_member_case_sensitive_desc'], '</dfn></dt>
						<dd class="ban_criteria_member_name">
							<select name="ban_member_name_case_sens">
								<option value="0"', empty($context['ban_details']['extra']['case_sens']) ? ' selected' : '', '>', $txt['no'], '</option>
								<option value="1"', !empty($context['ban_details']['extra']['case_sens']) ? ' selected' : '', '>', $txt['yes'], '</option>
							</select>
						</dd>';

	// Banning an email address is actually quite complicated
	echo '
						<dt class="ban_criteria_email"><a href="<URL>?action=help;in=ban_email_types" class="help" onclick="return reqWin(this);"></a> ', $txt['ban_type_email_type'], '</dt>
						<dd class="ban_criteria_email">
							<select name="ban_type_email">';
	foreach (array('specific', 'domain', 'tld') as $type)
		echo '
								<option value="', $type, '"', !empty($context['ban_details']['email_type']) && $context['ban_details']['email_type'] == $type ? ' selected' : '', '>', $txt['ban_type_email_type_' . $type], '</option>';

	echo '
							</select>
						</dd>
						<dt class="ban_criteria_email">', $txt['ban_type_email_content'], '</dt>
						<dd class="ban_criteria_email">
							<input type="text" name="ban_email_content" size="30" maxlength="100" value="', !empty($context['ban_details']['ban_email']) ? $context['ban_details']['ban_email'] : '', '">
						</dd>
						<dt class="ban_criteria_email"><a href="<URL>?action=help;in=ban_gmail_style" class="help" onclick="return reqWin(this);"></a> ', $txt['ban_email_gmail_style'], '</dt>
						<dd class="ban_criteria_email">
							<input type="checkbox" value="1"', !empty($context['ban_details']['extra']['gmail_style']) ? ' checked' : '', ' name="ban_gmail_style">
						</dd>';

	// This is a warning that applies to both IP and hostnames. It's carefully worded for that.
	echo '
						<dd class="ban_criteria_ip_address ban_criteria_hostname">', $txt['ban_use_htaccess'], '</dd>';

	// Banning an IP address. Don't need lang strings for IPv4 and IPv6, they're the same in every language.
	echo '
						<dt class="ban_criteria_ip_address">', $txt['ban_type_ip_address'], '</dt>
						<dd class="ban_criteria_ip_address">
							<select name="ban_type_ip" id="ban_type_ip" onchange="updateIP_form();">
								<option value="ipv4"', !empty($context['ban_details']['ip_type']) && $context['ban_details']['ip_type'] == 'ipv4' ? ' selected' : '', '>IPv4 (xxx.yyy.zzz.aaa)</option>
								<option value="ipv6"', !empty($context['ban_details']['ip_type']) && $context['ban_details']['ip_type'] == 'ipv6' ? ' selected' : '', '>IPv6 (xxxx:yyyy:zzzz::aaaa)</option>
							</select>
						</dd>
						<dt class="ban_criteria_ip_address">', $txt['ban_type_ip_range'], '</dt>
						<dd class="ban_criteria_ip_address">
							<select name="ban_ip_range" id="ban_ip_range" onchange="updateIP_form();">
								<option value="0"', empty($context['ban_details']['ip_range']) ? ' selected' : '', '>', $txt['no'], '</option>
								<option value="1"', !empty($context['ban_details']['ip_range']) ? ' selected' : '', '>', $txt['yes'], '</option>
							</select>
						</dd>
						<dt class="ban_criteria_ip_address">', $txt['ban_type_ip_address_details'], '</dt>
						<dd class="ban_criteria_ip_address">';
	foreach (array('start', 'end') as $item)
	{
		echo '
							<div class="ip_', $item, '">
								<div class="ban_width floatleft">', $txt['ban_type_range_' . $item], '</div>
								<span class="ipv4">';
		for ($i = 0; $i <= 3; $i++)
			echo '
								<input type="number" size="3" min="0" max="255" value="', !empty($context['ban_details']['ip_octets'][$item . '_' . $i]) ? $context['ban_details']['ip_octets'][$item . '_' . $i] : 0, '" name="ipv4_', $item, '_', $i, '">', $i < 3 ? '&bull;' : '';
		echo '
								</span>
								<span class="ipv6">
									<input type="text" size="38" maxlength="39" name="ipv6_', $item, '" value="', !empty($context['ban_details']['ipv6'][$item]) ? $context['ban_details']['ipv6'][$item] : '', '">
								</span>
							</div>';
	}

	echo '
						</dd>';

	// Banning a hostname
	echo '
					<dt class="ban_criteria_hostname">', $txt['ban_type_hostname'], ' <dfn>', $txt['ban_type_hostname_wildcard'], '</dfn></dt>
					<dd class="ban_criteria_hostname">
						<input type="text" name="ban_hostname_content" size="30" maxlength="100" value="', !empty($context['ban_details']['hostname']) ? $context['ban_details']['hostname'] : '', '">
					</dd>';

	// All done, back to the rest of the form fun
	echo '
					</dl>
				</fieldset>
				<fieldset>
					<legend>', $txt['ban_information'], '</legend>
					<dl class="settings">
						<dt>', $txt['ban_reason'], ' <dfn>', $txt['ban_reason_subtext'], '</dfn></dt>
						<dd>
							<textarea name="ban_reason" class="ban">', !empty($context['ban_details']['ban_reason']) ? $context['ban_details']['ban_reason'] : '', '</textarea>
						</dd>
						<dt class="ban_message">', $txt['ban_message'], ' <dfn>', $txt['ban_message_subtext'], '</dfn></dt>
						<dd class="ban_message">
							<textarea name="ban_message" class="ban">', !empty($context['ban_details']['extra']['message']) ? $context['ban_details']['extra']['message'] : '', '</textarea>
						</dd>
					</dl>
				</fieldset>
				<div class="pagesection">
					<div class="floatright">
						<div class="additional_row" style="text-align: right;">
							<input type="submit" value="', $txt['save'], '" class="save">
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
							<input type="hidden" name="ban" value="', $context['ban_details']['id_ban'], '">
						</div>
					</div>
				</div>
			</div>
		</form>';

	add_js('
	function updateForm()
	{
		var type = $("#ban_type").val();
		$(\'.ban_criteria_id_member, .ban_criteria_member_name, .ban_criteria_email, .ban_criteria_ip_address, .ban_criteria_hostname\').hide();
		$(\'.ban_criteria_\' + type).show();
	};
	updateForm();

	function updateIP_form()
	{
		var ipv6 = $(\'#ban_type_ip\').val() == \'ipv6\';
		$(\'.ipv4\').toggle(!ipv6);
		$(\'.ipv6\').toggle(ipv6);
		$(\'.ip_end, .ip_start .ban_width\').toggle($(\'#ban_ip_range\').val() != 0);
	};
	updateIP_form();');
}
