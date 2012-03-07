<?php
/**
 * Wedge
 *
 * Displays the search and browse features for the admin-only member list as well as handling member preferences.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_search_members()
{
	global $context, $theme, $options, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=admin;area=viewmembers" method="post" accept-charset="UTF-8">
			<we:cat>
				<span class="smalltext floatright">', $txt['wild_cards_allowed'], '</span>
				', $txt['search_for'], '
			</we:cat>
			<input type="hidden" name="sa" value="query">
			<div class="windowbg wrc">
				<div class="flow_hidden">
					<div class="msearch_details floatleft">
						<dl class="settings right">
							<dt class="right">
								<strong>', $txt['member_id'], ':</strong>
								<select name="types[mem_id]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="text" name="mem_id" value="" size="6">
							</dd>
							<dt class="right">
								<strong>', $txt['age'], ':</strong>
								<select name="types[age]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="text" name="age" value="" size="6">
							</dd>
							<dt class="right">
								<strong>', $txt['member_postcount'], ':</strong>
								<select name="types[posts]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="text" name="posts" value="" size="6">
							</dd>
							<dt class="right">
								<strong>', $txt['date_registered'], ':</strong>
								<select name="types[reg_date]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="text" name="reg_date" value="" size="10"><span class="smalltext">', $txt['date_format'], '</span>
							</dd>
							<dt class="right">
								<strong>', $txt['viewmembers_online'], ':</strong>
								<select name="types[last_online]">
									<option value="--">&lt;</option>
									<option value="-">&lt;=</option>
									<option value="=" selected>=</option>
									<option value="+">&gt;=</option>
									<option value="++">&gt;</option>
								</select>
							</dt>
							<dd>
								<input type="text" name="last_online" value="" size="10"><span class="smalltext">', $txt['date_format'], '</span>
							</dd>
						</dl>
					</div>
					<div class="msearch_details floatright">
						<dl class="settings right">
							<dt class="right">
								<strong>', $txt['username'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="membername" value="">
							</dd>
							<dt class="right">
								<strong>', $txt['email_address'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="email" value="">
							</dd>
							<dt class="right">
								<strong>', $txt['website'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="website" value="">
							</dd>
							<dt class="right">
								<strong>', $txt['location'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="location" value="">
							</dd>
							<dt class="right">
								<strong>', $txt['ip_address'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="ip" value=""', allowedTo('view_ip_address_any') ? '' : ' disabled', '>
							</dd>
						</dl>
					</div>
				</div>
				<div class="flow_hidden">
					<div class="msearch_details floatleft">
						<fieldset>
							<legend>', $txt['gender'], '</legend>
							<label><input type="checkbox" name="gender[]" value="0" id="gender-0" checked> ', $txt['undefined_gender'], '</label>&nbsp;&nbsp;
							<label><input type="checkbox" name="gender[]" value="1" id="gender-1" checked> ', $txt['male'], '</label>&nbsp;&nbsp;
							<label><input type="checkbox" name="gender[]" value="2" id="gender-2" checked> ', $txt['female'], '</label>
						</fieldset>
					</div>
					<div class="msearch_details floatright">
						<fieldset>
							<legend>', $txt['activation_status'], '</legend>
							<label><input type="checkbox" name="activated[]" value="1" id="activated-0" checked> ', $txt['activated'], '</label>&nbsp;&nbsp;
							<label><input type="checkbox" name="activated[]" value="0" id="activated-1" checked> ', $txt['not_activated'], '</label>
						</fieldset>
					</div>
				</div>
				<div class="clear_right"></div>
			</div>
			<br>
			<we:title>
				', $txt['member_part_of_these_membergroups'], '
			</we:title>
			<div class="flow_hidden">
				<table style="width: 49%" class="table_grid floatleft cs0">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th">', $txt['membergroups'], '</th>
							<th scope="col">', $txt['primary'], '</th>
							<th scope="col" class="last_th">', $txt['additional'], '</th>
						</tr>
					</thead>
					<tbody>';

	foreach ($context['membergroups'] as $membergroup)
		echo '
						<tr class="windowbg2">
							<td>', $membergroup['name'], '</td>
							<td class="center">
								<input type="checkbox" name="membergroups[1][]" value="', $membergroup['id'], '" checked>
							</td>
							<td class="center">
								', $membergroup['can_be_additional'] ? '<input type="checkbox" name="membergroups[2][]" value="' . $membergroup['id'] . '" checked>' : '', '
							</td>
						</tr>';

	echo '
						<tr class="windowbg2">
							<td>
								<em>', $txt['check_all'], '</em>
							</td>
							<td class="center">
								<input type="checkbox" onclick="invertAll(this, this.form, \'membergroups[1]\');" checked>
							</td>
							<td class="center">
								<input type="checkbox" onclick="invertAll(this, this.form, \'membergroups[2]\');" checked>
							</td>
						</tr>
					</tbody>
				</table>

				<table style="width: 49%" class="table_grid floatright cs0">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th">
								', $txt['membergroups_postgroups'], '
							</th>
							<th scope="col" class="last_th">&nbsp;</th>
						</tr>
					</thead>
					</tbody>';

	foreach ($context['postgroups'] as $postgroup)
		echo '
						<tr class="windowbg2">
							<td>
								', $postgroup['name'], '
							</td>
							<td style="width: 40px" class="center">
								<input type="checkbox" name="postgroups[]" value="', $postgroup['id'], '" checked>
							</td>
						</tr>';

	echo '
						<tr class="windowbg2">
							<td>
								<em>', $txt['check_all'], '</em>
							</td>
							<td class="center">
								<input type="checkbox" onclick="invertAll(this, this.form, \'postgroups[]\');" checked>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<br>
			<div class="right">
				<input type="submit" value="', $txt['search'], '" class="submit">
			</div>
		</form>';
}

function template_admin_browse()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	template_show_list('approve_list');

	// If we have lots of outstanding members, try and make the admin's life easier.
	if ($context['approve_list']['total_num_items'] > 20)
	{
		add_js_inline('
	function onOutstandingSubmit()
	{
		if (document.forms.postFormOutstanding.todo.value == "")
			return;

		var message = "";
		if (document.forms.postFormOutstanding.todo.value.indexOf("delete") != -1)
			message = ', JavaScriptEscape($txt['admin_browse_w_delete']), ';
		else if (document.forms.postFormOutstanding.todo.value.indexOf("reject") != -1)
			message = ', JavaScriptEscape($txt['admin_browse_w_reject']), ';
		else if (document.forms.postFormOutstanding.todo.value == "remind")
			message = ', JavaScriptEscape($txt['admin_browse_w_remind']), ';
		else
			message = ', JavaScriptEscape($context['browse_type'] == 'approve' ? $txt['admin_browse_w_approve'] : $txt['admin_browse_w_activate']), ';

		return confirm(message + ', JavaScriptEscape(' ' . $txt['admin_browse_outstanding_warn']), ');
	}');

		echo '
		<br>
		<form action="', $scripturl, '?action=admin;area=viewmembers" method="post" accept-charset="UTF-8" name="postFormOutstanding" id="postFormOutstanding" onsubmit="return onOutstandingSubmit();">
			<we:cat>
				', $txt['admin_browse_outstanding'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						', $txt['admin_browse_outstanding_days_1'], ':
					</dt>
					<dd>
						<input type="text" name="time_passed" value="14" maxlength="4" size="3"> ', $txt['admin_browse_outstanding_days_2'], '.
					</dd>
					<dt>
						', $txt['admin_browse_outstanding_perform'], ':
					</dt>
					<dd>
						<select name="todo">
							', $context['browse_type'] == 'activate' ? '
							<option value="ok">' . $txt['admin_browse_w_activate'] . '</option>' : '', '
							<option value="okemail">', $context['browse_type'] == 'approve' ? $txt['admin_browse_w_approve'] : $txt['admin_browse_w_activate'], ' ', $txt['admin_browse_w_email'], '</option>', $context['browse_type'] == 'activate' ? '' : '
							<option value="require_activation">' . $txt['admin_browse_w_approve_require_activate'] . '</option>', '
							<option value="reject">', $txt['admin_browse_w_reject'], '</option>
							<option value="rejectemail">', $txt['admin_browse_w_reject'], ' ', $txt['admin_browse_w_email'], '</option>
							<option value="delete">', $txt['admin_browse_w_delete'], '</option>
							<option value="deleteemail">', $txt['admin_browse_w_delete'], ' ', $txt['admin_browse_w_email'], '</option>', $context['browse_type'] == 'activate' ? '
							<option value="remind">' . $txt['admin_browse_w_remind'] . '</option>' : '', '
						</select>
					</dd>
				</dl>
				<input type="submit" value="', $txt['admin_browse_outstanding_go'], '">
				<input type="hidden" name="type" value="', $context['browse_type'], '">
				<input type="hidden" name="sort" value="', $context['approve_list']['sort']['id'], '">
				<input type="hidden" name="start" value="', $context['approve_list']['start'], '">
				<input type="hidden" name="orig_filter" value="', $context['current_filter'], '">
				<input type="hidden" name="sa" value="approve">', !empty($context['approve_list']['sort']['desc']) ? '
				<input type="hidden" name="desc" value="1">' : '', '
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
	}
}

function template_admin_member_prefs()
{
	global $context, $txt;

	if ($context['was_saved'])
		echo '
		<div class="windowbg" id="profile_success">
			', $txt['changes_saved'], '
		</div>';

	echo '
		<we:cat>', $txt['admin_member_prefs'], '</we:cat>
		<form action="<URL>?action=admin;area=memberoptions;sa=prefs;save" method="post">
			<div class="windowbg2 wrc">
				<dl class="settings">';

	$in_dl = true;
	foreach ($context['member_options'] as $key => $config_var)
	{
		if (is_array($config_var))
		{
			if (!$in_dl)
				echo '
				<dl class="settings">';
			$in_dl = true;

			echo '
					<dt id="dt_', $key, '">', isset($txt[$key]) ? $txt[$key] : $key, '</dt>
					<dd id="dd_', $key, '" class="memberopt">', $txt['member_prefs_default'], ' 
						';
			if ($config_var[0] == 'check')
				echo '<strong>', !empty($config_var['current']) ? $txt['yes'] : $txt['no'], '</strong>';
			elseif ($config_var[0] == 'select')
			{
				// Do we have a current value that we know about? If not, use the first in the list.
				if (!isset($config_var['current'], $config_var[2][$config_var['current']]))
				{
					$keys = array_keys($config_var[2]);
					$config_var['current'] = $keys[0];
				}
				echo '<strong>', $config_var[2][$config_var['current']], '</strong>';
			}

			echo '
					</dd>';
		}
		else
		{
			if ($in_dl)
				echo '
				</dl>';
			$in_dl = false;
			echo '
				<hr>';
		}
	}

	echo '
				</dl>
				<hr>
				<div class="right">
					<input type="submit" value="', $txt['save'], '" class="submit">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';

	add_js('
	str_default = ' . JavaScriptEscape($txt['member_prefs_default']) . ';
	str_change = ' . JavaScriptEscape($txt['member_prefs_change']) . ';
	str_guests = ' . JavaScriptEscape($txt['member_prefs_guest']) . ';
	str_members = ' . JavaScriptEscape($txt['member_prefs_members']) . ';
	str_override = ' . JavaScriptEscape($txt['member_prefs_override']) . ';
	str_nochange = ' . JavaScriptEscape($txt['no_change']) . ';
	str_leavealone = ' . JavaScriptEscape($txt['leave_alone']) . ';
	str_yes = ' . JavaScriptEscape($txt['yes']) . ';
	str_no = ' . JavaScriptEscape($txt['no']) . ';
	items = {' . implode(',', $context['js_opts']) . '};
	$.each(items, function (idx, val) {
		$("#dd_" + idx).append(\'<input type="button" class="modify membopt" value="\' + str_change + \'" onclick="modifyItem(\\\'\' + idx + \'\\\');">\');
	});

	function modifyItem(index)
	{
		var this_item = items[index], this_html = "";
		if (this_item[0] == "check")
		{
			this_html = str_guests + " <select name=\"guests[" + index + "]\">";
			var choices = { 0: str_no, 1: str_yes };
			$.each(choices, function (idx, val) {
				this_html += "<option value=\"" + idx + "\"" + (this_item[1] == idx ? " selected>" + str_nochange.replace("%s", val) : ">" + val) + "</option>";
			});
			this_html += "</select><br>";
			this_html += str_members + " <select name=\"members[" + index + "]\">";
			this_html += "<option value=\"leavealone\" selected>" + str_leavealone + "</option>";
			this_html += "<option value=\"0\">" + (str_override.replace("%s", str_no)) + "</option>";
			this_html += "<option value=\"1\">" + (str_override.replace("%s", str_yes)) + "</option>";
			this_html += "</select>";

			$("#dd_" + index).html(this_html);
			$("#dd_" + index + " select").sb();
		}
		else if (this_item[0] == "select")
		{
			this_html = str_guests + " <select name=\"guests[" + index + "]\">";
			$.each(this_item[2], function (idx, val) {
				this_html += "<option value=\"" + idx + "\"" + (this_item[1] == idx ? " selected>" + str_nochange.replace("%s", val) : ">" + val) + "</option>";
			});
			this_html += "</select><br>";
			this_html += str_members + " <select name=\"members[" + index + "]\">";
			this_html += "<option value=\"leavealone\" selected>" + str_leavealone + "</option>";
			$.each(this_item[2], function (idx, val) {
				this_html += "<option value=\"" + idx + "\">" + (str_override.replace("%s", val)) + "</option>";
			});
			this_html += "</select>";

			$("#dd_" + index).html(this_html);
			$("#dd_" + index + " select").sb();
		}
	};');
}

?>