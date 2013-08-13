<?php
/**
 * Wedge
 *
 * Displays the configuration options as well as profile area for paid subscriptions.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// The template for adding or editing a subscription.
function template_modify_subscription()
{
	global $context, $theme, $options, $txt, $settings;

	// JavaScript for the duration stuff.
	add_js('
	function toggleDuration(toChange)
	{
		switch (toChange)
		{
			case \'fixed\':
				$("#fixed_area, #repeatable_check_dt, #repeatable_check_dd").slideDown();
				$("#flexible_area, #lifetime_area").slideUp();
				break;
			case \'flexible\':
				$("#flexible_area, #repeatable_check_dt, #repeatable_check_dd").slideDown();
				$("#fixed_area, #lifetime_area").slideUp();
				break;
			case \'lifetime\':
				$("#lifetime_area").slideDown();
				$("#fixed_area, #repeatable_check_dt, #repeatable_check_dd, #flexible_area").slideUp();
				break;
		}
	}');

	echo '
		<form action="<URL>?action=admin;area=paidsubscribe;sa=modify;sid=', $context['sub_id'], '" method="post">
			<we:cat>
				', $txt['paid_' . $context['action_type'] . '_subscription'], '
			</we:cat>';

	if (!empty($context['disable_groups']))
		echo '
			<div class="information">
				<span class="alert">', $txt['paid_mod_edit_note'], '</span>
			</div>';
	echo '

			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						', $txt['paid_mod_name'], ':
					</dt>
					<dd>
						<input name="name" value="', $context['sub']['name'], '" size="30">
					</dd>
					<dt>
						', $txt['paid_mod_desc'], ':
					</dt>
					<dd>
						<textarea name="desc" rows="3" cols="40">', $context['sub']['desc'], '</textarea>
					</dd>
					<dt id="repeatable_check_dt"', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'lifetime' ? ' class="hide"' : '', '>
						<label for="repeatable_check">', $txt['paid_mod_repeatable'], '</label>:
					</dt>
					<dd id="repeatable_check_dd"', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'lifetime' ? ' class="hide"' : '', '>
						<input type="checkbox" name="repeatable" id="repeatable_check"', empty($context['sub']['repeatable']) ? '' : ' checked', '>
					</dd>
					<dt>
						<label for="activated_check">', $txt['paid_mod_active'], '</label>:
						<dfn>', $txt['paid_mod_active_desc'], '</dfn>
					</dt>
					<dd>
						<input type="checkbox" name="active" id="activated_check"', empty($context['sub']['active']) ? '' : ' checked', '>
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						', $txt['paid_mod_prim_group'], ':
						<dfn>', $txt['paid_mod_prim_group_desc'], '</dfn>
					</dt>
					<dd>
						<select name="prim_group"', !empty($context['disable_groups']) ? ' disabled' : '', '>
							<option value="0"', $context['sub']['prim_group'] == 0 ? ' selected' : '', '>', $txt['paid_mod_no_group'], '</option>';

	// Put each group into the box.
	foreach ($context['groups'] as $id => $name)
		echo '
							<option value="', $id, '"', $context['sub']['prim_group'] == $id ? ' selected' : '', '>', $name, '</option>';

	echo '
						</select>
					</dd>
					<dt>
						', $txt['paid_mod_add_groups'], ':
						<dfn>', $txt['paid_mod_add_groups_desc'], '</dfn>
					</dt>
					<dd>';

	// Put a checkbox in for each group
	foreach ($context['groups'] as $id => $name)
		echo '
						<label><input type="checkbox" name="addgroup[', $id, ']"', in_array($id, $context['sub']['add_groups']) ? ' checked' : '', !empty($context['disable_groups']) ? ' disabled' : '', '>&nbsp;<span class="smalltext">', $name, '</span></label><br>';

	echo '
					</dd>
					<dt>
						', $txt['paid_mod_reminder'], ':
						<dfn>', $txt['paid_mod_reminder_desc'], '</dfn>
					</dt>
					<dd>
						<input name="reminder" value="', $context['sub']['reminder'], '" size="6">
					</dd>
					<dt>
						', $txt['paid_mod_email'], ':
						<dfn>', $txt['paid_mod_email_desc'], '</dfn>
					</dt>
					<dd>
						<textarea name="emailcomplete" rows="6" cols="40">', $context['sub']['email_complete'], '</textarea>
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						', $txt['paid_allowed_groups'], ':
						<dfn>', $txt['paid_allowed_groups_desc'], '</dfn>
					</dt>
					<dd>';

	$groups = array_merge(array(0 => $txt['membergroups_members']),	$context['groups']);

	// Put a checkbox in for each group
	foreach ($groups as $id => $name)
		echo '
						<label><input type="checkbox" name="allowed_groups[', $id, ']"', in_array($id, $context['sub']['allowed_groups']) ? ' checked' : '', !empty($context['disable_groups']) ? ' disabled' : '', ' class="allowed_groups">&nbsp;<span class="smalltext">', $name, '</span></label><br>';

	echo '
						<div class="right">
							<label><input type="checkbox" id="allowed_groups_check_all" onclick="$(\'.allowed_groups\').prop(\'checked\', this.checked);"> ', $txt['check_all'], '</label>
						</div>
					</dd>
				</dl>
				<hr>
				<label><input type="radio" name="duration_type" id="duration_type_fixed" value="fixed"', empty($context['sub']['duration']) || $context['sub']['duration'] == 'fixed' ? ' checked' : '', ' onclick="toggleDuration(\'fixed\');">
				<strong>', $txt['paid_mod_fixed_price'], '</strong></label>
				<br>
				<div id="fixed_area"', empty($context['sub']['duration']) || $context['sub']['duration'] == 'fixed' ? '' : ' class="hide"', '>
					<fieldset>
						<dl class="settings">
							<dt>
								', $txt['paid_cost'], ' (', str_replace('%1.2f', '', $settings['paid_currency_symbol']), '):
							</dt>
							<dd>
								<input name="fixed_cost" value="', empty($context['sub']['cost']['fixed']) || empty($context['sub']['duration']) || $context['sub']['duration'] != 'fixed' ? '0' : $context['sub']['cost']['fixed'], '" size="4">
							</dd>
							<dt>
								', $txt['paid_mod_span'], ':
							</dt>
							<dd>
								<input name="span_value" value="', $context['sub']['span']['value'], '" size="4">
								<select name="span_unit">
									<option value="D"', $context['sub']['span']['unit'] == 'D' ? ' selected' : '', '>', $txt['paid_mod_span_days'], '</option>
									<option value="W"', $context['sub']['span']['unit'] == 'W' ? ' selected' : '', '>', $txt['paid_mod_span_weeks'], '</option>
									<option value="M"', $context['sub']['span']['unit'] == 'M' ? ' selected' : '', '>', $txt['paid_mod_span_months'], '</option>
									<option value="Y"', $context['sub']['span']['unit'] == 'Y' ? ' selected' : '', '>', $txt['paid_mod_span_years'], '</option>
								</select>
							</dd>
						</dl>
					</fieldset>
				</div>
				<label><input type="radio" name="duration_type" id="duration_type_flexible" value="flexible"', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'flexible' ? ' checked' : '', ' onclick="toggleDuration(\'flexible\');">
				<strong>', $txt['paid_mod_flexible_price'], '</strong></label>
				<br>
				<div id="flexible_area"', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'flexible' ? '' : ' class="hide"', '>
					<fieldset>';

	// !!! Removed until implemented
	if (false)
		echo '
						<dl class="settings">
							<dt>
								<label for="allow_partial_check">', $txt['paid_mod_allow_partial'], '</label>:
								<dfn>', $txt['paid_mod_allow_partial_desc'], '</dfn>
							</dt>
							<dd>
								<input type="checkbox" name="allow_partial" id="allow_partial_check"', empty($context['sub']['allow_partial']) ? '' : ' checked', '>
							</dd>
						</dl>';

	echo '
						<div class="information">
							<strong>', $txt['paid_mod_price_breakdown'], '</strong><br>
							', $txt['paid_mod_price_breakdown_desc'], '
						</div>
						<dl class="settings">
							<dt>
								<strong>', $txt['paid_duration'], '</strong>
							</dt>
							<dd>
								<strong>', $txt['paid_cost'], ' (', preg_replace('~%[df.\d]+~', '', $settings['paid_currency_symbol']), ')</strong>
							</dd>
							<dt>
								', $txt['paid_per_day'], ':
							</dt>
							<dd>
								<input name="cost_day" value="', empty($context['sub']['cost']['day']) ? '0' : $context['sub']['cost']['day'], '" size="5">
							</dd>
							<dt>
								', $txt['paid_per_week'], ':
							</dt>
							<dd>
								<input name="cost_week" value="', empty($context['sub']['cost']['week']) ? '0' : $context['sub']['cost']['week'], '" size="5">
							</dd>
							<dt>
								', $txt['paid_per_month'], ':
							</dt>
							<dd>
								<input name="cost_month" value="', empty($context['sub']['cost']['month']) ? '0' : $context['sub']['cost']['month'], '" size="5">
							</dd>
							<dt>
								', $txt['paid_per_year'], ':
							</dt>
							<dd>
								<input name="cost_year" value="', empty($context['sub']['cost']['year']) ? '0' : $context['sub']['cost']['year'], '" size="5">
							</dd>
						</dl>
					</fieldset>
				</div>
				<label><input type="radio" name="duration_type" id="duration_type_lifetime" value="lifetime"', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'lifetime' ? ' checked' : '', ' onclick="toggleDuration(\'lifetime\');">
				<strong>', $txt['paid_mod_lifetime_price'], '</strong></label>
				<br>
				<div id="lifetime_area"', !empty($context['sub']['duration']) && $context['sub']['duration'] == 'lifetime' ? '' : ' class="hide"', '>
					<fieldset>
						<dl class="settings">
							<dt>
								', $txt['paid_cost'], ' (', str_replace('%1.2f', '', $settings['paid_currency_symbol']), '):
							</dt>
							<dd>
								<input name="life_cost" value="', empty($context['sub']['cost']['fixed']) || empty($context['sub']['duration']) || $context['sub']['duration'] != 'lifetime' ? '0' : $context['sub']['cost']['fixed'], '" size="4">
							</dd>
						</dl>
					</fieldset>
				</div>
				<div class="right">
					<input type="submit" name="save" value="', $txt['paid_settings_save'], '" class="save">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</div>
		</form>';
}

function template_delete_subscription()
{
	global $context, $txt;

	echo '
		<form action="<URL>?action=admin;area=paidsubscribe;sa=modify;sid=', $context['sub_id'], ';delete" method="post">
			<we:cat>
				', $txt['paid_delete_subscription'], '
			</we:cat>
			<div class="windowbg wrc">
				<p>', $txt['paid_mod_delete_warning'], '</p>
				<input type="submit" name="delete_confirm" value="', $txt['paid_delete_subscription'], '" class="delete">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>';
}

// Add or edit an existing subscriber.
function template_modify_user_subscription()
{
	global $context, $theme, $options, $txt;

	// Some quickly stolen javascript from Post, could do with being more efficient :)
	if (!$context['current_subscription']['lifetime'])
		add_js('
	var monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

	function generateDays(offset)
	{
		var days = 0, selected = 0;
		var dayElement = $("#day" + offset)[0], year = $("#year" + offset).val(), monthElement = ("#month" + offset)[0];

		monthLength[1] = (year % 4 == 0 && (year % 100 != 0 || year % 400 == 0)) ? 29 : 28;

		selected = dayElement.selectedIndex;
		while (dayElement.options.length)
			dayElement.options[0] = null;

		days = monthLength[monthElement.value - 1];

		for (i = 1; i <= days; i++)
			dayElement.options.push(new Option(i, i));

		if (selected < days)
			dayElement.selectedIndex = selected;
	}');

	echo '
		<form action="<URL>?action=admin;area=paidsubscribe;sa=modifyuser;sid=', $context['sub_id'], ';lid=', $context['log_id'], '" method="post">
			<we:cat>
				', $txt[$context['action_type'] . '_subscriber'], ' - ', $context['current_subscription']['name'], '
				', empty($context['sub']['username']) ? '' : ' (' . $txt['user'] . ': ' . $context['sub']['username'] . ')', '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">';

	// Do we need a username?
	if ($context['action_type'] == 'add')
		echo '
					<dt>
						<strong>', $txt['paid_username'], ':</strong>
						<dfn>', $txt['one_username'], '</dfn>
					</dt>
					<dd>
						<input name="name" id="name_control" value="', $context['sub']['username'], '" size="30">
					</dd>';

	echo '
					<dt>
						<strong>', $txt['paid_status'], ':</strong>
					</dt>
					<dd>
						<select name="status">
							<option value="0"', $context['sub']['status'] == 0 ? ' selected' : '', '>', $txt['paid_finished'], '</option>
							<option value="1"', $context['sub']['status'] == 1 ? ' selected' : '', '>', $txt['paid_active'], '</option>
						</select>
					</dd>
				</dl>';

	if (!$context['current_subscription']['lifetime'])
	{
		echo '
				<fieldset>
					<legend>', $txt['start_date_and_time'], '</legend>
					<select name="year" id="year" onchange="generateDays(\'\');">';

		// Show a list of all the years we allow...
		for ($year = 2005; $year <= 2030; $year++)
			echo '
						<option value="', $year, '"', $year == $context['sub']['start']['year'] ? ' selected' : '', '>', $year, '</option>';

		echo '
					</select>&nbsp;
					', (isset($txt['month']) ? $txt['month'] : ''), ':&nbsp;
					<select name="month" id="month" onchange="generateDays(\'\');">';

		// There are 12 months per year - ensure that they all get listed.
		for ($month = 1; $month <= 12; $month++)
			echo '
						<option value="', $month, '"', $month == $context['sub']['start']['month'] ? ' selected' : '', '>', $txt['months'][$month], '</option>';

		echo '
					</select>&nbsp;
					', (isset($txt['day']) ? $txt['day'] : ''), ':&nbsp;
					<select name="day" id="day">';

		// This prints out all the days in the current month - this changes dynamically as we switch months.
		for ($day = 1; $day <= $context['sub']['start']['last_day']; $day++)
			echo '
						<option value="', $day, '"', $day == $context['sub']['start']['day'] ? ' selected' : '', '>', $day, '</option>';

		echo '
					</select>
					', $txt['hour'], ': <input name="hour" value="', $context['sub']['start']['hour'], '" size="2">
					', $txt['minute'], ': <input name="minute" value="', $context['sub']['start']['min'], '" size="2">
				</fieldset>
				<fieldset>
					<legend>', $txt['end_date_and_time'], '</legend>
					<select name="yearend" id="yearend" onchange="generateDays(\'end\');">';

		// Show a list of all the years we allow...
		for ($year = 2005; $year <= 2030; $year++)
			echo '
						<option value="', $year, '"', $year == $context['sub']['end']['year'] ? ' selected' : '', '>', $year, '</option>';

		echo '
					</select>&nbsp;
					', (isset($txt['month']) ? $txt['month'] : ''), ':&nbsp;
					<select name="monthend" id="monthend" onchange="generateDays(\'end\');">';

		// There are 12 months per year - ensure that they all get listed.
		for ($month = 1; $month <= 12; $month++)
			echo '
						<option value="', $month, '"', $month == $context['sub']['end']['month'] ? ' selected' : '', '>', $txt['months'][$month], '</option>';

		echo '
					</select>&nbsp;
					', (isset($txt['day']) ? $txt['day'] : ''), ':&nbsp;
					<select name="dayend" id="dayend">';

	// This prints out all the days in the current month - this changes dynamically as we switch months.
		for ($day = 1; $day <= $context['sub']['end']['last_day']; $day++)
			echo '
						<option value="', $day, '"', $day == $context['sub']['end']['day'] ? ' selected' : '', '>', $day, '</option>';

		echo '
					</select>
					', $txt['hour'], ': <input name="hourend" value="', $context['sub']['end']['hour'], '" size="2">
					', $txt['minute'], ': <input name="minuteend" value="', $context['sub']['end']['min'], '" size="2">
				</fieldset>';
	}

	echo '
				<input type="submit" name="save_sub" value="', $txt['paid_settings_save'], '" class="save">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';

	add_js_file('scripts/suggest.js');

	add_js('
	new weAutoSuggest({
		', min_chars(), ',
		sControlId: \'name_control\'
	});');

	if (!empty($context['pending_payments']))
	{
		echo '
		<we:cat>
			', $txt['pending_payments'], '
		</we:cat>
		<div class="information">
			', $txt['pending_payments_desc'], '
		</div>
		<we:cat>
			', $txt['pending_payments_value'], '
		</we:cat>
		<div class="windowbg wrc">
			<ul class="pending_payments">';

		foreach ($context['pending_payments'] as $id => $payment)
		{
			echo '
				<li class="reset">
					', $payment['desc'], '
					<span class="floatleft"><a href="<URL>?action=admin;area=paidsubscribe;sa=modifyuser;lid=', $context['log_id'], ';pending=', $id, ';accept">', $txt['pending_payments_accept'], '</a></span>
					<span class="floatright"><a href="<URL>?action=admin;area=paidsubscribe;sa=modifyuser;lid=', $context['log_id'], ';pending=', $id, ';remove">', $txt['remove'], '</a></span>
				</li>';
		}

		echo '
			</ul>
		</div>';
	}
}

// Template for a user to edit/pick their subscriptions.
function template_user_subscription()
{
	global $context, $txt, $settings;

	echo '
		<form action="<URL>?action=profile;u=', $context['id_member'], ';area=subscriptions;confirm" method="post">
			<we:cat>
				', $txt['subscriptions'], '
			</we:cat>';

	if (empty($context['subscriptions']))
	{
		echo '
			<div class="information">
				', $txt['paid_subs_none'], '
			</div>';
	}
	else
	{
		echo '
			<div class="information">
				', $txt['paid_subs_desc'], '
			</div>';

		// Print out all the subscriptions.
		$alternate = false;
		foreach ($context['subscriptions'] as $id => $subscription)
		{
			$alternate = !$alternate;

			// Ignore the inactive ones...
			if (empty($subscription['active']))
				continue;

			echo '
			<we:title>
				', $subscription['name'], '
			</we:title>
			<div class="windowbg', $alternate ? '' : '2', ' wrc">
				<p class="smalltext">', $subscription['desc'], '</p>';

			if (!$subscription['flexible'])
				echo '
				<div><strong>', $txt['paid_duration'], ':</strong> ', $subscription['length'], '</div>';

			if (we::$user['is_owner'])
			{
				echo '
				<strong>', $txt['paid_cost'], ':</strong>';

				if ($subscription['flexible'])
				{
					echo '
				<select name="cur[', $subscription['id'], ']">';

					// Print out the costs for this one.
					foreach ($subscription['costs'] as $duration => $value)
						echo '
					<option value="', $duration, '">', sprintf($settings['paid_currency_symbol'], $value), '/', $txt[$duration], '</option>';

					echo '
				</select>';
				}
				else
					echo '
				', sprintf($settings['paid_currency_symbol'], $subscription['costs']['fixed']), '<br>';

				echo '
				<input type="submit" name="sub_id[', $subscription['id'], ']" value="', $txt['paid_order'], '" class="submit" style="margin-top: 8px">', !empty($subscription['group_warning']) ? '
				<br><br><div class="errorbox">' . $txt['paid_subs_admin_override'] . '</div>' : '';
			}
			else
				echo '
				<a href="<URL>?action=admin;area=paidsubscribe;sa=modifyuser;sid=', $subscription['id'], ';uid=', $context['member']['id'], (empty($context['current'][$subscription['id']]) ? '' : ';lid=' . $context['current'][$subscription['id']]['id']), '">', empty($context['current'][$subscription['id']]) ? $txt['paid_admin_add'] : $txt['paid_edit_subscription'], '</a>', !empty($subscription['group_warning']) ? '
				<br><br><div class="errorbox">' . $txt['paid_subs_admin_override'] . '</div>' : '';

			echo '
			</div>';
		}
	}

	echo '
		</form>
		<br>
		<we:cat>
			', $txt['paid_current'], '
		</we:cat>
		<div class="information">
			', $txt['paid_current_desc'], '
		</div>
		<table class="table_grid w100 cs0">
			<thead>
				<tr class="catbg center">
					<th class="left" style="width: 30%">', $txt['paid_name'], '</th>
					<th>', $txt['paid_status'], '</th>
					<th>', $txt['start_date'], '</th>
					<th>', $txt['end_date'], '</th>
				</tr>
			</thead>
			<tbody>';

	if (empty($context['current']))
		echo '
				<tr class="windowbg">
					<td class="center" colspan="4">
						', $txt['paid_none_yet'], '
					</td>
				</tr>';

	$alternate = false;
	foreach ($context['current'] as $sub)
	{
		$alternate = !$alternate;

		if (!$sub['hide'])
			echo '
				<tr class="windowbg', $alternate ? '' : '2', '">
					<td>
						', (allowedTo('admin_forum') ? '<a href="<URL>?action=admin;area=paidsubscribe;sa=modifyuser;lid=' . $sub['id'] . '">' . $sub['name'] . '</a>' : $sub['name']), '
					</td><td>
						<span style="color: ', ($sub['status'] == 2 ? 'green' : ($sub['status'] == 1 ? 'red' : 'orange')), '"><strong>', $sub['status_text'], '</strong></span>
					</td><td>
						', $sub['start'], '
					</td><td>
						', $sub['end'], '
					</td>
				</tr>';
	}
	echo '
			</tbody>
		</table>';
}

// The "choose payment" dialog.
function template_choose_payment()
{
	global $context, $txt;

	echo '
		<we:cat>
			', $txt['paid_confirm_payment'], '
		</we:cat>
		<div class="information">
			', $txt['paid_confirm_desc'], '
		</div>
		<div class="windowbg wrc">
			<dl class="settings">
				<dt>
					<strong>', $txt['subscription'], ':</strong>
				</dt>
				<dd>
					', $context['sub']['name'], '
				</dd>
				<dt>
					<strong>', $txt['paid_cost'], ':</strong>
				</dt>
				<dd>
					', $context['cost'], '
				</dd>
			</dl>
		</div>';

	// Do all the gateway options.
	foreach ($context['gateways'] as $gateway)
	{
		echo '
		<we:cat>
			', $gateway['title'], '
		</we:cat>
		<div class="windowbg wrc">
			<div id="gateway_desc">', $gateway['desc'], '</div>
			<form action="', $gateway['form'], '" method="post" id="gateway_form">';

		foreach ($gateway['hidden'] as $name => $value)
			echo '
				<input type="hidden" id="', $gateway['id'], '_', $name, '" name="', $name, '" value="', $value, '">';

		echo '
				<br><input type="submit" value="', $gateway['submit'], '" class="submit">
			</form>
		</div>';
	}
}

// The "thank you" bit...
function template_paid_done()
{
	global $context, $txt;

	echo '
		<we:title>
			', $txt['paid_done'], '
		</we:title>
		<div class="windowbg2 wrc">
			<p>', $txt['paid_done_desc'], '</p>
			<br>
			<a href="<URL>?action=profile;u=', $context['member']['id'], ';area=subscriptions">', $txt['paid_sub_return'], '</a>
		</div>';
}
