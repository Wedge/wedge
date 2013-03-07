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
							<input value="', !empty($context['ban_details']['ban_member']) ? $context['ban_details']['ban_member'] : '', '" id="ban_id_member_content" name="ban_id_member_content" size="15" maxlength="100">
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
							<input name="ban_member_name_content" size="20" maxlength="100" value="', !empty($context['ban_details']['ban_name']) ? $context['ban_details']['ban_name'] : '', '">
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
							<input name="ban_email_content" size="30" maxlength="100" value="', !empty($context['ban_details']['ban_email']) ? $context['ban_details']['ban_email'] : '', '">
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
									<input size="38" maxlength="39" name="ipv6_', $item, '" value="', !empty($context['ban_details']['ipv6'][$item]) ? $context['ban_details']['ipv6'][$item] : '', '">
								</span>
							</div>';
	}

	echo '
						</dd>';

	// Banning a hostname
	echo '
					<dt class="ban_criteria_hostname">', $txt['ban_type_hostname'], ' <dfn>', $txt['ban_type_hostname_wildcard'], '</dfn></dt>
					<dd class="ban_criteria_hostname">
						<input name="ban_hostname_content" size="30" maxlength="100" value="', !empty($context['ban_details']['hostname']) ? $context['ban_details']['hostname'] : '', '">
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
