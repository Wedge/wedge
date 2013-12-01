<?php
/**
 * The interface for creating and editing infractions.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

function template_infractions()
{
	global $txt, $context;

	echo '
		<we:cat>', $txt['preset_infractions'], '</we:cat>
		<p class="information">', $txt['preset_infractions_desc'], '</p>';

	echo '
		<table class="w100 cs0">
			<thead>
				<tr class="catbg">
					<th>', $txt['infraction_name'], '</th>
					<th>', $txt['infraction_points'], '</th>
					<th>', $txt['infraction_duration'], '</th>
					<th>', $txt['infraction_sanctions'], '</th>
					<th>', $txt['infraction_issuers'], '</th>
					<th></th>
				</tr>
			</thead>
			<tbody>';

	if (empty($context['infractions']))
		echo '
				<tr class="windowbg">
					<td colspan="5" class="center">', $txt['no_infractions'], '</td>
				</tr>';
	else
	{
		$use_bg2 = false;
		foreach ($context['infractions'] as $id => $infraction)
		{
			echo '
				<tr class="windowbg', $use_bg2 ? '2' : '', '">
					<td>', $infraction['infraction_name'], '</td>
					<td class="center">', $infraction['points'], '</td>
					<td class="center">', $infraction['duration'], '</td>
					<td><ul class="smalltext"><li>', empty($infraction['sanctions']) ? $txt['infraction_no_punishments'] : implode('</li><li>', $infraction['sanctions']), '</li></ul></td>
					<td>', implode(', ', $infraction['issuing_groups']), '</td>
					<td><a href="<URL>?action=admin;area=infractions;sa=infractions;edit=', $id, '">', $txt['modify'], '</a></td>
				</tr>';
			$use_bg2 = !$use_bg2;
		}
	}

	echo '
			</tbody>
		</table>';

	echo '
		<br>
		<div class="right">
			<form action="<URL>?action=admin;area=infractions;sa=infractions;edit" method="post">
				<input type="submit" class="new" value="', $txt['add_infraction'], '">
			</form>
		</div>';

	echo '
		<we:cat>', $txt['adhoc_infractions'], '</we:cat>
		<p class="information">', $txt['adhoc_infractions_desc'], '</p>
		<form action="<URL>?action=admin;area=infractions;sa=infractions;save" method="post">
			<div class="windowbg wrc">
				<dl class="settings">';

	foreach ($context['issuer_groups'] as $group)
	{
		echo '
					<dt>
						<span class="group', $group, '">', $context['group_list'][$group], '</span>
					</dt>';

		// Admins are special. Consistent UI FTW.
		if ($group == 1)
		{
			echo '
					<dd>
						<label>', $txt['max_infractions_day'], ' <input type="number" min="0" max="100" value="100" disabled></label>
						<fieldset>
							<legend><input type="checkbox" checked disabled> ', $txt['can_issue_adhoc'], '</legend>
							<div>', $txt['max_points'], ' <input type="number" min="0" max="1000" value="1000" disabled></div>
							<div>
								<strong>', $txt['punishments_issuable'], '</strong>';
			foreach ($context['infraction_levels'] as $infraction => $dummy)
				echo '
								<br><input type="checkbox" disabled checked> ', $txt['infraction_' . $infraction];

			echo '
							</div>
						</fieldset>
					</dd>';
		}
		else
		{
			echo '
					<dd>
						<label>', $txt['max_infractions_day'], ' <input type="number" min="0" max="100" name="per_day[', $group, ']" value="', $context['infractions_adhoc'][$group]['per_day'], '"></label>
						<div', !empty($context['infractions_adhoc'][$group]['allowed']) ? ' class="hide"' : '', '><label><input type="checkbox" name="adhoc[', $group, ']" value="', $group, '" onclick="showgroup(this);"', !empty($context['infractions_adhoc'][$group]['allowed']) ? ' checked' : '', '> ', $txt['can_issue_adhoc'], '</label></div>
						<fieldset', empty($context['infractions_adhoc'][$group]['allowed']) ? ' class="hide"' : '', '>
							<legend><label><input type="checkbox" checked onclick="hidegroup(this)"> ', $txt['can_issue_adhoc'], '</label></legend>
							<div>
								<label>', $txt['max_points'], ' <input type="number" min="0" max="1000" name="points[', $group, ']" value="', $context['infractions_adhoc'][$group]['points'], '"></label>
							</div>
							<div>
								<strong>', $txt['punishments_issuable'], '</strong>';
			foreach ($context['infraction_levels'] as $infraction => $dummy)
				echo '
								<br><label><input type="checkbox" name="sanctions[', $group, '][]" value="', $infraction, '"', in_array($infraction, $context['infractions_adhoc'][$group]['sanctions']) ? ' checked' : '', '> ', $txt['infraction_' . $infraction], '</label>';

			echo '
							</div>
						</fieldset>
					</dd>';
		}
	}

	echo '
				</dl>
				<div class="right">
					<input type="submit" class="submit" value="', $txt['save'], '">
				</div>
			</div>
		</form>';

	add_js('
	function showgroup(obj)
	{
		var $obj = $(obj), $fs = $obj.closest(\'dd\').find(\'fieldset\');
		$obj.closest(\'div\').hide();

		$fs.show();
		$fs.find(\'legend input\').prop("checked", true);
	};

	function hidegroup(obj)
	{
		var $obj = $(obj), $fs = $obj.closest(\'fieldset\'), $dd = $obj.closest(\'dd\');

		$fs.hide();

		$dd.find(\'div label input[name^="adhoc"]\').prop("checked", false);
		$dd.find(\'div:first\').show();
	};');
}

function template_edit_infraction()
{
	global $txt, $context;

	echo '
		<we:cat>', $context['page_title'], '</we:cat>';

	if (!empty($context['errors']))
		echo '
			<div class="errorbox" id="errors">
				<h3 id="error_serious">', $txt['error_while_submitting'], '</h3>
				<ul class="error" id="error_list">
					<li>', implode('</li><li>', $context['errors']), '</li>
				</ul>
			</div>';

	echo '
		<form action="<URL>?action=admin;area=infractions;sa=infractions;save', !empty($context['infraction_details']['id']) ? ';edit=' . $context['infraction_details']['id'] : ';edit', '" method="post" accept-charset="UTF-8">
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						', $txt['infraction_name'], ': <dfn>', $txt['infraction_name_desc'], '</dfn>
					</dt>
					<dd>
						<input name="infraction_name" value="', $context['infraction_details']['infraction_name'], '" required>
					</dd>
					<dt>
						', $txt['infraction_points'], ':
					</dt>
					<dd>
						<input type="number" name="infraction_points" value="', $context['infraction_details']['points'], '" min="0" max="1000" required>
					</dd>
					<dt>
						', $txt['infraction_duration'], ':
					</dt>
					<dd>
						<input type="number" id="infraction_duration_number" name="infraction_duration_number" value="', $context['infraction_details']['duration']['number'], '" min="0" max="50" required', $context['infraction_details']['duration']['unit'] == 'i' ? ' class="hide"' : '', '>
						<select name="infraction_duration_unit" onchange="$(\'#infraction_duration_number\').toggle(this.value != \'i\');">';
	foreach ($txt['infraction_duration_types'] as $k => $v)
		echo '
							<option value="', $k, '"', $context['infraction_details']['duration']['unit'] == $k ? ' selected' : '', '>', $v, '</option>';
	echo '
						</select>
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						', $txt['notification_text'], ': <dfn>', $txt['notification_text_desc'], '</dfn>
					</dt>
					<dd>
						<select name="infraction_notification" id="infraction_notification" onchange="updateView()">
							<option value="">', $txt['notification_use_none'], '</option>';
	foreach ($context['infractions_templates'] as $tpl)
		echo '
							<option value="', $tpl, '"', $tpl == $context['infraction_details']['template'] ? ' selected' : '', '>', $txt['tpl_infraction_' . $tpl]['desc'], '</option>';

	echo '
							<option value="custom"', $context['infraction_details']['template'] == 'custom' ? ' selected' : '', '>', $txt['notification_use_custom'], '</option>
						</select>
					</dd>
				</dl>
				<dl id="infraction_template" class="settings">
					<dt>', $txt['notification_subject'], '</dt>
					<dd id="infraction_subject"></dd>
					<dt>', $txt['notification_body'], ' <dfn>', $txt['notification_body_note'], '</dfn></dt>
					<dd id="infraction_body"></dd>
				</dl>';

	// Now the real fun begins.
	foreach ($context['languages'] as $id_lang => $lang)
	{
		echo '
						<fieldset class="custom_note hide" id="fs_', $id_lang, '">
							<legend><span class="flag_', $id_lang, '"><a href="#" onclick="$(\'#fs_', $id_lang, '\').hide(); $(\'#fs_', $id_lang, '_link\').show(); return false;">', $lang['name'], '</a></span> <div class="foldable fold"></div></legend>
							<dl class="settings">
								<dt>', $txt['notification_subject'], '</dt>
								<dd><input class="w100" name="subject[', $id_lang, ']" value="', !empty($context['infraction_details']['template_msg'][$id_lang]['subject']) ? $context['infraction_details']['template_msg'][$id_lang]['subject'] : '', '"></dd>
								<dt>', $txt['notification_body'], ' <dfn>', $txt['notification_body_note'], '</dfn></dt>
								<dd>
									<textarea class="w100" name="body[', $id_lang, ']" rows="8">', !empty($context['infraction_details']['template_msg'][$id_lang]['body']) ? $context['infraction_details']['template_msg'][$id_lang]['body'] : '', '</textarea>
								</dd>
							</dl>
						</fieldset>
						<div id="fs_', $id_lang, '_link" class="custom_note hide"><span class="flag_', $id_lang, '"><a href="#" onclick="$(\'#fs_', $id_lang, '\').show(); $(\'#fs_', $id_lang, '_link\').hide(); return false;">', $lang['name'], '</a></span> <div class="foldable"></div></div>';
	}

	// Back to your regularly scheduled template.
	echo '
				<hr>
				<dl class="settings">
					<dt>
						', $txt['infraction_sanctions'], ':
					</dt>
					<dd>
						<fieldset>
							<legend>', $txt['for_the_duration'], '</legend>
							<ul class="permission_groups">';

	foreach ($context['infraction_levels'] as $infraction => $dummy)
		echo '
								<li><label><input type="checkbox" name="sanction[', $infraction, ']" value="1"', in_array($infraction, $context['infraction_details']['sanctions']) ? ' checked' : '', '> ', $txt['infraction_' . $infraction], '</label>';

	echo '
							</ul>
						</fieldset>
					</dd>
					<dt>
						', $txt['infraction_issuers'], ': <dfn>', $txt['issued_by_adhoc'], '</dfn>
					</dt>
					<dd>
						<fieldset>
							<legend>', $txt['select_from_list'], '</legend>
							<ul class="permission_groups">';
	foreach ($context['issuer_groups'] as $option)
		echo '
								<li>
									<label>
										<input type="checkbox" name="group[', $option, ']" value="', $option, '"', $option == 1 ? ' checked disabled' : (in_array($option, $context['infraction_details']['issuing_groups']) ? ' checked' : ''), '>
										<span class="group', $option, '">', $context['group_list'][$option], '</span>', in_array($option, $context['infractions_adhoc']) ? '<dfn>' . $txt['can_issue_adhoc'] . '</dfn>' : '', '
									</label>
								</li>';
	echo '
							</ul>
						</fieldset>
					</dd>
				</dl>
				<div class="right">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="submit" class="submit" value="', $txt['save'], '">', empty($context['infraction_details']['id']) ? '' : '
					<input type="submit" class="delete" name="delete" value="' . $txt['delete'] . '" onclick="return ask(' . JavaScriptEscape($txt['delete_infraction_confirm']) . ', e);">', '
				</div>
			</div>
		</form>';

	// Now we need to get our stock wording in to the template.
	add_js('
	function updateView()
	{
		var warnings = {');

	$i = 0;
	$c = count($context['infractions_templates']);
	foreach ($context['infractions_templates'] as $tpl)
	{
		$is_last = ++$i == $c;
		add_js('
			', $tpl, ': {
				subject: ', JavaScriptEscape($txt['tpl_infraction_' . $tpl]['subject']), ',
				body: ', JavaScriptEscape(westr::nl2br($txt['tpl_infraction_' . $tpl]['body'])), '
			}', !$is_last ? ',' : '');
	}

	add_js('
		};
		var index = $(\'#infraction_notification\').val();
		if (index == \'\')
		{
			$(\'#infraction_template, .custom_note\').hide();
		}
		else if (index == \'custom\')
		{
			$(\'#infraction_template\').hide();
			$(\'div.custom_note\').show();
		}
		else
		{
			$(\'#infraction_template\').show();
			$(\'.custom_note\').hide();
			$(\'#infraction_subject\').html(warnings[index].subject);
			$(\'#infraction_body\').html(warnings[index].body);
		}
	};
	updateView();');
}

function template_infraction_levels()
{
	global $txt, $context;

	if (isset($_GET['save']))
		echo '
		<div class="windowbg" id="profile_success">
			', $txt['changes_saved'], '
		</div>';

	echo '
		<we:cat>', $txt['infraction_levels'], '</we:cat>
		<form action="<URL>?action=admin;area=infractions;sa=infractionlevels;save" method="post">
			<div class="windowbg2 wrc">
				<p class="information">', $txt['infractionlevels_extra'], '</p>
				<dl class="settings">
					<dt><strong>', $txt['enact_infraction'], '</strong></dt>
					<dd><strong>', $txt['points_infraction'], '</strong></dd>';

	foreach ($context['infraction_levels'] as $infraction => $details)
		echo '
					<dt>
						', $txt['infraction_' . $infraction], !empty($txt['infraction_' . $infraction . '_help']) ? '<dfn>' . $txt['infraction_' . $infraction . '_help'] . '</dfn>' : '', '
					</dt>
					<dd>
						<input name="', $infraction, '" type="number" min="0" max="1000" value="', $details['points'], '"', empty($details['enabled']) ? ' disabled' : '', '>
						&nbsp; &nbsp;
						<label><input name="enabled[', $infraction, ']" type="checkbox" onclick="updateEnabled(this)" ', !empty($details['enabled']) ? ' checked' : '', '> ', $txt['enabled_infraction'], '</label>
					</dd>';

	echo '
				</dl>
				<div class="right">
					<input type="submit" value="', $txt['save'], '" class="submit">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</div>
		</form>';

	add_js('
	function updateEnabled(obj)
	{
		$(obj).parent().prev(\'input\').prop(\'disabled\', !obj.checked);
	};');
}

function template_group_list($id, $full = false)
{
	global $txt, $context;

	$group_list = $full ? array_keys($context['group_list']) : $context['issuer_groups'];

	echo '
					<dt>
						<label for="', $id, '"><a id="setting_', $id, '"></a> <span id="span_', $id, '">', isset($txt[$id]) ? $txt[$id] : $id, '</span></label>
					</dt>';

					echo '
					<dd>
						<fieldset id="fs_', $id, '">
							<legend><div class="foldable fold"></div> <a href="#" onclick="$(\'#fs_', $id, '\').hide(); $(\'#fs_', $id, '_link\').show(); return false;">', $txt['select_from_list'], '</a></legend>
							<ul class="permission_groups">';
					foreach ($group_list as $option)
						echo '
								<li>
									<label>
										<input type="checkbox" name="', $id, '[', $option, ']" value="', $option, '"', $option == 1 ? ' checked disabled' : (in_array($option, $context['infraction_settings'][$id]) ? ' checked' : ''), '>
										<span class="group', $option, '">', $context['group_list'][$option], '</span>
									</label>
								</li>';
					echo '
							</ul>
						</fieldset>
						<div id="fs_', $id, '_link" class="hide"><div class="foldable"></div> <a href="#" onclick="$(\'#fs_', $id, '\').show(); $(\'#fs_', $id, '_link\').hide(); return false;">[ ', $txt['click_to_see_more'], ' ]</a></div>
					</dd>';

					add_js('$("#fs_', $id, '").hide(); $("#fs_', $id, '_link").show();');
}

function template_callback_revoke_any_issued()
{
	template_group_list('revoke_any_issued', false);
}

function template_callback_no_warn_groups()
{
	template_group_list('no_warn_groups', true);
}
