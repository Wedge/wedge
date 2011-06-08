<?php
/**
 * Wedge
 *
 * Displays the search and browse features for the admin-only member list.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_search_members()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
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
							<dt class="righttext">
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
							<dt class="righttext">
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
							<dt class="righttext">
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
							<dt class="righttext">
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
							<dt class="righttext">
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
							<dt class="righttext">
								<strong>', $txt['username'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="membername" value="">
							</dd>
							<dt class="righttext">
								<strong>', $txt['email_address'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="email" value="">
							</dd>
							<dt class="righttext">
								<strong>', $txt['website'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="website" value="">
							</dd>
							<dt class="righttext">
								<strong>', $txt['location'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="location" value="">
							</dd>
							<dt class="righttext">
								<strong>', $txt['ip_address'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="ip" value=""', allowedTo('view_ip_address_any') ? '' : ' disabled', '>
							</dd>
							<dt class="righttext">
								<strong>', $txt['messenger_address'], ':</strong>
							</dt>
							<dd>
								<input type="text" name="messenger" value="">
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
			<div class="righttext">
				<input type="submit" value="', $txt['search'], '" class="submit">
			</div>
		</form>
	</div>
	<br class="clear">';
}

function template_admin_browse()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">';

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

	echo '
	</div>
	<br class="clear">';
}

?>