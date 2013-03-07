<?php
/**
 * Wedge
 *
 * Displays the permission index, plus the full permission configuration for each group.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_permission_index()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	// Not allowed to edit?
	if (!$context['can_modify'])
		echo '
		<div class="errorbox">
			', sprintf($txt['permission_cannot_edit'], $scripturl . '?action=admin;area=permissions;sa=profiles'), '
		</div>';

	echo '
		<form action="', $scripturl, '?action=admin;area=permissions;sa=quick" method="post" accept-charset="UTF-8" name="permissionForm" id="permissionForm">';

	if (!empty($context['profile']))
		echo '
			<we:title>
				', $txt['permissions_for_profile'], ': &quot;', $context['profile']['name'], '&quot;
			</we:title>';

	echo '
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg center">
						<th class="first_th left">', $txt['membergroups_name'], '</th>
						<th style="width: 10%">', $txt['membergroups_members_top'], '</th>';

	if (empty($settings['permission_enable_deny']))
		echo '
						<th style="width: 16%">', $txt['membergroups_permissions'], '</th>';
	else
		echo '
						<th style="width: 8%">', $txt['permissions_allowed'], '</th>
						<th style="width: 8%">', $txt['permissions_denied'], '</th>';

	echo '
						<th style="width: 10%">', $context['can_modify'] ? $txt['permissions_modify'] : $txt['permissions_view'], '</th>
						<th class="last_th" style="width: 4%">
							', $context['can_modify'] ? '<input type="checkbox" onclick="invertAll(this, this.form, \'group\');">' : '', '
						</th>
					</tr>
				</thead>
				<tbody>';

	$alternate = false;
	foreach ($context['groups'] as $group)
	{
		$alternate = !$alternate;
		echo '
					<tr class="windowbg', $alternate ? '2' : '', ' center">
						<td class="left">';
		if ($group['id'] == -1)
			echo '
							<a href="' . $scripturl . '?action=help;in=membergroup_guests" onclick="return reqWin(this);" class="help"></a>';
		elseif ($group['id'] == 0)
			echo '
							<a href="' . $scripturl . '?action=help;in=membergroup_regular_members" onclick="return reqWin(this);" class="help"></a>';
		elseif ($group['id'] == 1)
			echo '
							<a href="' . $scripturl . '?action=help;in=membergroup_administrator" onclick="return reqWin(this);" class="help"></a>';
		elseif ($group['id'] == 3)
			echo '
							<a href="' . $scripturl . '?action=help;in=membergroup_moderator" onclick="return reqWin(this);" class="help"></a>';
		if ($group['color'])
			echo '
							<span style="color: ', $group['color'], '">', $group['name'], '</span>';
		else
			echo '
							', $group['name'];

		if (!empty($group['children']))
			echo '
							<div class="smalltext">', $txt['permissions_includes_inherited'], ': &quot;', implode('&quot;, &quot;', $group['children']), '&quot;</div>';

		echo '
						</td>
						<td>', $group['can_search'] ? $group['link'] : $group['num_members'], '</td>';

		if (empty($settings['permission_enable_deny']))
			echo '
						<td style="width: 16%">', $group['num_permissions']['allowed'], '</td>';
		else
			echo '
						<td style="width: 8%', $group['id'] == 1 ? '; font-style: italic' : '', '">', $group['num_permissions']['allowed'], '</td>
						<td style="width: 8%', $group['id'] == 1 || $group['id'] == -1 ? '; font-style: italic' : (!empty($group['num_permissions']['denied']) ? '; color: red' : ''), '">', $group['num_permissions']['denied'], '</td>';

		echo '
						<td>', $group['allow_modify'] ? '<a href="' . $scripturl . '?action=admin;area=permissions;sa=modify;group=' . $group['id'] . (empty($context['profile']) ? '' : ';pid=' . $context['profile']['id']) . '">' . ($context['can_modify'] ? $txt['permissions_modify'] : $txt['permissions_view']). '</a>' : '', '</td>
						<td>', $group['allow_modify'] && $context['can_modify'] ? '<input type="checkbox" name="group[]" value="' . $group['id'] . '">' : '', '</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<br>';

	// Advanced stuff...
	if ($context['can_modify'])
	{
		echo '
			<we:cat>
				<div class="sortselect', empty($context['show_advanced_options']) ? ' fold' : '', '" id="permissions_panel_toggle"></div> ', $txt['permissions_advanced_options'], '
			</we:cat>
			<div id="permissions_panel_advanced" class="windowbg wrc">
				<fieldset>
					<legend>', $txt['permissions_with_selection'], '</legend>
					<dl class="settings admin_permissions">
						<dt>
							<a href="', $scripturl, '?action=help;in=permissions_quickgroups" onclick="return reqWin(this);" class="help"></a> ', $txt['permissions_apply_pre_defined'], '
						</dt>
						<dd>
							<select name="predefined">
								<option value="">(', $txt['permissions_select_pre_defined'], ')</option>
								<option value="restrict">', $txt['permitgroups_restrict'], '</option>
								<option value="standard">', $txt['permitgroups_standard'], '</option>
								<option value="moderator">', $txt['permitgroups_moderator'], '</option>
								<option value="maintenance">', $txt['permitgroups_maintenance'], '</option>
							</select>
						</dd>
						<dt>
							', $txt['permissions_like_group'], ':
						</dt>
						<dd>
							<select name="copy_from">
								<option value="empty">(', $txt['permissions_select_membergroup'], ')</option>';

		foreach ($context['groups'] as $group)
			if ($group['id'] != 1)
				echo '
								<option value="', $group['id'], '">', $group['name'], '</option>';

		echo '
							</select>
						</dd>
						<dt>
							<select name="add_remove">
								<option value="add">', $txt['permissions_add'], '...</option>
								<option value="clear">', $txt['permissions_remove'], '...</option>';

		if (!empty($settings['permission_enable_deny']))
			echo '
								<option value="deny">', $txt['permissions_deny'], '...</option>';

		echo '
							</select>
						</dt>
						<dd class="flow_auto">
							<select name="permissions">
								<option value="">(', $txt['permissions_select_permission'], ')</option>';

		foreach ($context['permissions'] as $permissionType)
		{
			if ($permissionType['id'] == 'membergroup' && !empty($context['profile']))
				continue;

			foreach ($permissionType['columns'] as $column)
			{
				foreach ($column as $permissionGroup)
				{
					if ($permissionGroup['hidden'])
						continue;

					echo '
								<optgroup label="', westr::safe($permissionGroup['name']), '">';

					foreach ($permissionGroup['permissions'] as $perm)
					{
						if ($perm['hidden'])
							continue;

						if ($perm['has_own_any'])
							echo '
									<option value="', $permissionType['id'], '/', $perm['own']['id'], '">', $perm['name'], ' (', $perm['own']['name'], ')</option>
									<option value="', $permissionType['id'], '/', $perm['any']['id'], '">', $perm['name'], ' (', $perm['any']['name'], ')</option>';
						else
							echo '
									<option value="', $permissionType['id'], '/', $perm['id'], '">', $perm['name'], '</option>';
					}
					echo '
								</optgroup>';
				}
			}
		}
		echo '
							</select>
						</dd>
					</dl>
				</fieldset>
				<div class="right">
					<input type="submit" value="', $txt['permissions_set_permissions'], '" onclick="return checkSubmit(e);" class="submit">
				</div>
			</div>';

		// JavaScript for the advanced stuff.
		add_js('
	new weToggle({', empty($context['show_advanced_options']) ? '
		isCollapsed: true,' : '', '
		aSwapContainers: [\'permissions_panel_advanced\'],
		aSwapImages: [\'permissions_panel_toggle\'],
		sOption: \'admin_preferences\',
		sExtra: \';admin_key=app;th=1\'
	});

	function checkSubmit(e)
	{
		if ((document.forms.permissionForm.predefined.value != "" && (document.forms.permissionForm.copy_from.value != "empty" || document.forms.permissionForm.permissions.value != "")) || (document.forms.permissionForm.copy_from.value != "empty" && document.forms.permissionForm.permissions.value != ""))
		{
			say(', JavaScriptEscape($txt['permissions_only_one_option']), ');
			return false;
		}
		if (document.forms.permissionForm.predefined.value == "" && document.forms.permissionForm.copy_from.value == "" && document.forms.permissionForm.permissions.value == "")
		{
			say(', JavaScriptEscape($txt['permissions_no_action']), ');
			return false;
		}
		if (document.forms.permissionForm.permissions.value != "" && document.forms.permissionForm.add_remove.value == "deny")
			return ask(', JavaScriptEscape($txt['permissions_deny_dangerous']), ', e);

		return true;
	}');

		if (!empty($context['profile']))
			echo '
			<input type="hidden" name="pid" value="', $context['profile']['id'], '">';

		echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';
	}
	else
		echo '
			</table>';

	echo '
		</form>';
}

function template_by_board()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	echo '
		<form action="', $scripturl, '?action=admin;area=permissions;sa=board" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['permissions_boards'], '
			</we:cat>
			<div class="information">
				', $txt['permissions_boards_desc'], '
			</div>
			<we:title>
				<div class="flow_hidden">
					<span class="perm_name floatleft">', $txt['board_name'], '</span>
					<span class="perm_profile floatleft">', $txt['permission_profile'], '</span>
				</div>
			</we:title>';

	if (!$context['edit_all'])
		echo '
			<div class="right">
				<a href="', $scripturl, '?action=admin;area=permissions;sa=board;edit;', $context['session_query'], '">[', $txt['permissions_board_all'], ']</a>
			</div>';

	foreach ($context['categories'] as $category)
	{
		echo '
			<we:cat>
				', $category['name'], '
			</we:cat>';

		if (!empty($category['boards']))
			echo '
			<div class="windowbg wrc">
				<ul class="perm_boards flow_hidden">';

		$alternate = false;

		foreach ($category['boards'] as $board)
		{
			$alternate = !$alternate;

			echo '
					<li class="flow_hidden windowbg', $alternate ? '' : '2', '">
						<span class="perm_board floatleft">
							<a href="', $scripturl, '?action=admin;area=manageboards;sa=board;boardid=', $board['id'], ';rid=permissions;', $context['session_query'], '">', str_repeat('-', $board['child_level']), ' ', $board['name'], '</a>
						</span>
						<span class="perm_boardprofile floatleft">';

			if ($context['edit_all'])
			{
				echo '
							<select name="boardprofile[', $board['id'], ']">';

				foreach ($context['profiles'] as $id => $profile)
					echo '
								<option value="', $id, '"', $id == $board['profile'] ? ' selected' : '', '>', $profile['name'], '</option>';

				echo '
							</select>';
			}
			else
				echo '
							<a href="', $scripturl, '?action=admin;area=permissions;sa=index;pid=', $board['profile'], ';', $context['session_query'], '"> [', $board['profile_name'], ']</a>';

			echo '
						</span>
					</li>';
		}

		if (!empty($category['boards']))
			echo '
				</ul>
			</div>';
	}

	echo '
			<div class="right">';

	if ($context['edit_all'])
		echo '
				<input type="submit" name="save_changes" value="', $txt['save'], '" class="save">';
	else
		echo '
				<a href="', $scripturl, '?action=admin;area=permissions;sa=board;edit;', $context['session_query'], '">[', $txt['permissions_board_all'], ']</a>';

	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>';
}

// Edit permission profiles (predefined).
function template_edit_profiles()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	echo '
		<form action="', $scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="UTF-8">
			<we:title>
				', $txt['permissions_profile_edit'], '
			</we:title>

			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg">
						<th class="first_th">', $txt['permissions_profile_name'], '</th>
						<th>', $txt['permissions_profile_used_by'], '</th>
						<th class="last_th" style="width: 5%">', $txt['delete'], '</th>
					</tr>
				</thead>
				<tbody>';
	$alternate = false;
	foreach ($context['profiles'] as $profile)
	{
		echo '
					<tr class="', $alternate ? 'windowbg' : 'windowbg2', '">
						<td>';

		if (!empty($context['show_rename_boxes']) && $profile['can_edit'])
			echo '
							<input name="rename_profile[', $profile['id'], ']" value="', $profile['name'], '">';
		else
			echo '
							<a href="', $scripturl, '?action=admin;area=permissions;sa=index;pid=', $profile['id'], ';', $context['session_query'], '">', $profile['name'], '</a>';

		echo '
						</td>
						<td>
							', !empty($profile['boards_text']) ? $profile['boards_text'] : $txt['permissions_profile_used_by_none'], '
						</td>
						<td class="center">
							<input type="checkbox" name="delete_profile[]" value="', $profile['id'], '"', $profile['can_delete'] ? '' : ' disabled', '>
						</td>
					</tr>';
		$alternate = !$alternate;
	}

	echo '
				</tbody>
			</table>
			<div class="right padding">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	if ($context['can_edit_something'])
		echo '
				<input type="submit" name="rename" value="', empty($context['show_rename_boxes']) ? $txt['permissions_profile_rename'] : $txt['permissions_commit'], '" class="submit">';

	echo '
				<input type="submit" name="delete" value="', $txt['quickmod_delete_selected'], '" class="delete">
			</div>
		</form>
		<br>
		<form action="', $scripturl, '?action=admin;area=permissions;sa=profiles" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['permissions_profile_new'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<strong>', $txt['permissions_profile_name'], ':</strong>
					</dt>
					<dd>
						<input name="profile_name" value="">
					</dd>
					<dt>
						<strong>', $txt['permissions_profile_copy_from'], ':</strong>
					</dt>
					<dd>
						<select name="copy_from">';

	foreach ($context['profiles'] as $id => $profile)
		echo '
							<option value="', $id, '">', $profile['name'], '</option>';

	echo '
						</select>
					</dd>
				</dl>
				<div class="right">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="submit" name="create" value="', $txt['permissions_profile_new_create'], '" class="new">
				</div>
			</div>
		</form>';
}

function template_modify_group()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	// Cannot be edited?
	if (!$context['profile']['can_modify'])
		echo '
		<div class="errorbox">
			', sprintf($txt['permission_cannot_edit'], $scripturl . '?action=admin;area=permissions;sa=profiles'), '
		</div>';
	else
		add_js('
	window.weUsedDeny = false;');

	echo '
		<form action="', $scripturl, '?action=admin;area=permissions;sa=modify2;group=', $context['group']['id'], ';pid=', $context['profile']['id'],
		'" method="post" accept-charset="UTF-8" name="permissionForm" id="permissionForm" onsubmit="return !window.weUsedDeny || ask(',
		JavaScriptEscape($txt['permissions_deny_dangerous'], true), ', e);">';

	if (!empty($settings['permission_enable_deny']) && $context['group']['id'] != -1)
		echo '
			<div class="information">
				', $txt['permissions_option_desc'], '
			</div>';

	echo '
			<we:cat>';

	if ($context['permission_type'] == 'board')
		echo '
				', $txt['permissions_local_for'], ' &quot;', $context['group']['name'], '&quot; ', $txt['permissions_on'], ' &quot;', $context['profile']['name'], '&quot;';
	else
		echo '
				', $context['permission_type'] == 'membergroup' ? $txt['permissions_general'] : $txt['permissions_board'], ' - &quot;', $context['group']['name'], '&quot;';
	echo '
			</we:cat>
			<div class="windowbg wrc">
				', $txt['permissions_change_view'], ': ', ($context['view_type'] == 'simple' ? '<img src="' . $theme['images_url'] . '/selected.gif">' : ''), '<a href="', $scripturl, '?action=admin;area=permissions;sa=modify;group=', $context['group']['id'], ($context['permission_type'] == 'board' ? ';pid=' . $context['profile']['id'] : ''), ';view=simple">', $txt['permissions_view_simple'], '</a> |
				', ($context['view_type'] == 'classic' ? '<img src="' . $theme['images_url'] . '/selected.gif">' : ''), '<a href="', $scripturl, '?action=admin;area=permissions;sa=modify;group=', $context['group']['id'], ($context['permission_type'] == 'board' ? ';pid=' . $context['profile']['id'] : ''), ';view=classic">', $txt['permissions_view_classic'], '</a>
			</div>
			<div class="flow_hidden">';

	// Draw out the main bits.
	if ($context['view_type'] == 'simple')
		template_modify_group_simple($context['permission_type']);
	else
		template_modify_group_classic($context['permission_type']);

	// If this is general permissions also show the default profile.
	if ($context['permission_type'] == 'membergroup')
	{
		echo '
			</div>
			<br>
			<we:cat>
				', $txt['permissions_board'], '
			</we:cat>
			<div class="information">
				', $txt['permissions_board_desc'], '
			</div>
			<div class="flow_hidden">';

		if ($context['view_type'] == 'simple')
			template_modify_group_simple('board');
		else
			template_modify_group_classic('board');
	}

	echo '
			</div>';

	if ($context['profile']['can_modify'])
		echo '
			<div class="right padding">
				<input type="submit" value="', $txt['permissions_commit'], '" class="submit">
			</div>';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';

}

// A JavaScript-enabled clean permissions view.
function template_modify_group_simple($type)
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	// Simple only has one column so we only need bother ourself with that one.
	$permission_data =& $context['permissions'][$type]['columns'][0];

	// Short cut for disabling fields we can't change.
	$disable_field = $context['profile']['can_modify'] ? '' : ' disabled';

	echo '
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg">';

	if (!empty($settings['permission_enable_deny']) && $context['group']['id'] != -1)
		echo '
						<th class="first_th" colspan="2"></th>
						<th>', $txt['permissions_option_on'], '</th>
						<th>', $txt['permissions_option_off'], '</th>
						<th class="last_th">', $txt['permissions_option_deny'], '</th>';
	echo '
					</tr>
				</thead>
				<tbody>';

	foreach ($permission_data as $id_group => $permissionGroup)
	{
		if (empty($permissionGroup['permissions']))
			continue;

		// Are we likely to have something in this group to display or is it all hidden?
		$has_display_content = false;
		if (!$permissionGroup['hidden'])
		{
			// Before we go any further check we are going to have some data to print otherwise we just have a silly heading.
			foreach ($permissionGroup['permissions'] as $permission)
				if (!$permission['hidden'])
					$has_display_content = true;

			if ($has_display_content)
			{
				echo '
					<tr class="windowbg">
						<td colspan="2" class="w100 left">
							<a href="#" onclick="return toggleBreakdown(\'', $id_group, '\');">
								<div class="sortselect" id="group_toggle_img_', $id_group, '"></div>&nbsp;<strong>', $permissionGroup['name'], '</strong>
							</a>
						</td>';
				if (empty($settings['permission_enable_deny']) || $context['group']['id'] == -1)
					echo '
						<td colspan="3" style="width: 10px">
							<div id="group_select_div_', $id_group, '">
								<input type="checkbox" id="group_select_', $id_group, '" name="group_select_', $id_group, '" onclick="determineGroupState(\'', $id_group, '\', this.checked ? \'on\' : \'off\');" class="hide"', $disable_field, '>
							</div>
						</td>';
				else
					echo '
						<td class="center">
							<div id="group_select_div_on_', $id_group, '">
								<input type="radio" id="group_select_on_', $id_group, '" name="group_select_', $id_group, '" value="on" onclick="determineGroupState(\'', $id_group, '\', \'on\');" class="hide"', $disable_field, '>
							</div>
						</td>
						<td class="center">
							<div id="group_select_div_off_', $id_group, '">
								<input type="radio" id="group_select_off_', $id_group, '" name="group_select_', $id_group, '" value="off" onclick="determineGroupState(\'', $id_group, '\', \'off\');" class="hide"', $disable_field, '>
							</div>
						</td>
						<td class="center">
							<div id="group_select_div_deny_', $id_group, '">
								<input type="radio" id="group_select_deny_', $id_group, '" name="group_select_', $id_group, '" value="deny" onclick="determineGroupState(\'', $id_group, '\', \'deny\');" class="hide"', $disable_field, '>
							</div>
						</td>';
				echo '
					</tr>';
			}
		}

		$alternate = false;
		foreach ($permissionGroup['permissions'] as $permission)
		{
			// If it's hidden keep the last value.
			if ($permission['hidden'] || $permissionGroup['hidden'])
			{
				echo '
					<tr class="hide">
						<td>
							<input type="hidden" name="perm[', $type, '][', $permission['id'], ']" value="', $permission['select'] == 'denied' && !empty($settings['permission_enable_deny']) ? 'deny' : $permission['select'], '">
						</td>
					</tr>';
			}
			else
			{
				echo '
					<tr id="perm_div_', $id_group, '_', $permission['id'], '" class="', $alternate ? 'windowbg' : 'windowbg2', '">
						<td class="top" style="width: 10px; padding-right: 1ex">
							', $permission['help_index'] ? '<a href="' . $scripturl . '?action=help;in=' . $permission['help_index'] . '" onclick="return reqWin(this);" class="help" title="' . $txt['help'] . '"></a>' : '', '
						</td>
						<td class="top w100 left" style="padding-bottom: 2px">', $permission['name'], '</td>';

				if (empty($settings['permission_enable_deny']) || $context['group']['id'] == -1)
					echo '
						<td class="top" style="padding-bottom: 2px"><input type="checkbox" id="select_', $permission['id'], '" name="perm[', $type, '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked' : '', ' onclick="determineGroupState(\'', $id_group, '\');" value="on"', $disable_field, '></td>';
				else
					echo '
						<td class="top" style="width: 10px; padding-bottom: 2px"><input type="radio" id="select_on_', $permission['id'], '" name="perm[', $type, '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked' : '', ' value="on" onclick="determineGroupState(\'', $id_group, '\');"', $disable_field, '></td>
						<td class="top" style="width: 10px; padding-bottom: 2px"><input type="radio" id="select_off_', $permission['id'], '" name="perm[', $type, '][', $permission['id'], ']"', $permission['select'] == 'off' ? ' checked' : '', ' value="off" onclick="determineGroupState(\'', $id_group, '\');"', $disable_field, '></td>
						<td class="top" style="width: 10px; padding-bottom: 2px"><input type="radio" id="select_deny_', $permission['id'], '" name="perm[', $type, '][', $permission['id'], ']"', $permission['select'] == 'denied' ? ' checked' : '', ' value="deny" onclick="window.weUsedDeny = true; determineGroupState(\'', $id_group, '\');"', $disable_field, '></td>';

				echo '
					</tr>';
				$alternate = !$alternate;
			}
		}

		if (!$permissionGroup['hidden'] && $has_display_content)
			echo '
					<tr id="group_hr_div_', $id_group, '" class="windowbg2 perm_groups">
						<td colspan="5" class="w100"></td>
					</tr>';
	}
	echo '
				</tbody>
			</table>';

	if ($context['profile']['can_modify'] && empty($context['simple_javascript_displayed']))
	{
		// Only show this once.
		$context['simple_javascript_displayed'] = true;

		// Manually toggle the breakdown.
		add_js('
	function toggleBreakdown(id_group, forcedisplayType)
	{
		var i, displayType = typeof forcedisplayType != "undefined" ? forcedisplayType : ($("#group_hr_div_" + id_group).is(":visible") ? "none" : "");

		for (i = 0; i < groupPermissions[id_group].length; i++)
				$("#perm_div_" + id_group + "_" + groupPermissions[id_group][i]).toggle(displayType == "");

		$("#group_hr_div_" + id_group).toggle(displayType == "");
		$("#group_toggle_img_" + id_group).toggleClass("fold", displayType != "");

		return false;
	}');

		// This function decides what to do when ANYTHING is touched!
		add_js('
	var groupPermissions = [];
	function determineGroupState(id_group, forceState)
	{
		if (typeof forceState != "undefined")
			thisState = forceState;

		// Cycle through this group\'s elements.
		var i, curState = false, thisState;
		for (i = 0; i < groupPermissions[id_group].length; i++)
		{');

		if (empty($settings['permission_enable_deny']) || $context['group']['id'] == -1)
			add_js('
			if (typeof forceState != "undefined")
				$("#select_" + groupPermissions[id_group][i]).prop("checked", forceState == "on");

			thisState = $("#select_" + groupPermissions[id_group][i])[0].checked ? "on" : "off";');
		else
			add_js('
			if (typeof forceState != "undefined")
			{
				$("#select_on_" + groupPermissions[id_group][i]).prop("checked", forceState == "on");
				$("#select_off_" + groupPermissions[id_group][i]).prop("checked", forceState == "off");
				$("#select_deny_" + groupPermissions[id_group][i]).prop("checked", forceState == "deny");
			}

			if ($("#select_on_" + groupPermissions[id_group][i])[0].checked)
				thisState = "on";
			else if ($("#select_off_" + groupPermissions[id_group][i])[0].checked)
				thisState = "off";
			else
				thisState = "deny";');

		// Unless this is the first element, or it's the same state as the last we're buggered.
		add_js('
			if (curState == false || thisState == curState)
				curState = thisState;
			else
			{
				curState = "fudged";
				i = 999;
			}
		}');

		// First, check the right master is selected!
		if (empty($settings['permission_enable_deny']) || $context['group']['id'] == -1)
			add_js('
		$("#group_select_" + id_group).prop("checked", curState == "on");');
		else
			add_js('
		$("#group_select_on_" + id_group).prop("checked", curState == "on");
		$("#group_select_off_" + id_group).prop("checked", curState == "off");
		$("#group_select_deny_" + id_group).prop("checked", curState == "deny");');

		// Force the display?
		add_js('
		if (curState != "fudged")
			toggleBreakdown(id_group, "none");
	}');
	}

	// Some more javascript to be displayed as long as we are editing.
	if ($context['profile']['can_modify'])
	{
		foreach ($permission_data as $id_group => $permissionGroup)
		{
			if (empty($permissionGroup['permissions']))
				continue;

			// As before...
			$has_display_content = false;
			if (!$permissionGroup['hidden'])
			{
				// Make sure we can show it.
				foreach ($permissionGroup['permissions'] as $permission)
					if (!$permission['hidden'])
						$has_display_content = true;

				// Make all the group indicators visible on JS only.
				if ($has_display_content)
				{
					if (empty($settings['permission_enable_deny']) || $context['group']['id'] == -1)
						add_js('
	$("#group_select_div_', $id_group, '").parent().removeClass().addClass("lockedbg");
	$("#group_select_', $id_group, '").show();');
					else
						add_js('
	$("#group_select_div_on_', $id_group, ', #group_select_div_off_', $id_group, ', #group_select_div_deny_', $id_group, '").parent().removeClass().addClass("lockedbg");
	$("#group_select_on_', $id_group, ', #group_select_off_', $id_group, ', #group_select_deny_', $id_group, '").show();');
				}

				$perm_ids = array();
				$count = 0;
				// Need this to determine what can be tweaked.
				foreach ($permissionGroup['permissions'] as $permission)
					if (!$permission['hidden'])
						$perm_ids[] = "'$permission[id]'";
				// Declare this group's permissions into an array.
				if (!empty($perm_ids))
					add_js('
	groupPermissions["', $id_group, '"] = new Array(', count($perm_ids), ');');
				foreach ($perm_ids as $count => $id)
					add_js('
	groupPermissions["', $id_group, '"][', $count, '] = ', $id, ';');

				// Show the group as required.
				if ($has_display_content)
					add_js('
	determineGroupState("', $id_group, '");');
			}
		}
	}

}

// The SMF 1.x way of looking at permissions.
function template_modify_group_classic($type)
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	$permission_type =& $context['permissions'][$type];
	$disable_field = $context['profile']['can_modify'] ? '' : ' disabled';

	foreach ($permission_type['columns'] as $column)
	{
		echo '
				<table style="width: 49%" class="table_grid perm_classic floatleft cs0">';

		foreach ($column as $permissionGroup)
		{
			if (empty($permissionGroup['permissions']))
				continue;

			// Are we likely to have something in this group to display or is it all hidden?
			$has_display_content = false;
			if (!$permissionGroup['hidden'])
			{
				// Before we go any further check we are going to have some data to print otherwise we just have a silly heading.
				foreach ($permissionGroup['permissions'] as $permission)
					if (!$permission['hidden'])
						$has_display_content = true;

				if ($has_display_content)
				{
					echo '
					<tr class="catbg center">
						<th colspan="2" class="w100">
							', $permissionGroup['name'], '
						</th>';
					if (empty($settings['permission_enable_deny']) || $context['group']['id'] == -1)
						echo '
						<th colspan="3" style="width: 10px"></th>';
					else
						echo '
						<th>', $txt['permissions_option_on'], '</th>
						<th>', $txt['permissions_option_off'], '</th>
						<th>', $txt['permissions_option_deny'], '</th>';
					echo '
					</tr>';
				}
			}

			$alternate = false;
			foreach ($permissionGroup['permissions'] as $permission)
			{
				// If it's hidden keep the last value.
				if ($permission['hidden'] || $permissionGroup['hidden'])
				{
					echo '
					<tr class="hide">
						<td>';

					if ($permission['has_own_any'])
					{
						// Guests can't have own permissions.
						if ($context['group']['id'] != -1)
							echo '
							<input type="hidden" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']" value="', $permission['own']['select'] == 'denied' && !empty($settings['permission_enable_deny']) ? 'deny' : $permission['own']['select'], '">';

						echo '
							<input type="hidden" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']" value="', $permission['any']['select'] == 'denied' && !empty($settings['permission_enable_deny']) ? 'deny' : $permission['any']['select'], '">';
					}
					else
						echo '
							<input type="hidden" name="perm[', $permission_type['id'], '][', $permission['id'], ']" value="', $permission['select'] == 'denied' && !empty($settings['permission_enable_deny']) ? 'deny' : $permission['select'], '">';
					echo '
						</td>
					</tr>';
				}
				else
				{
					echo '
					<tr class="', $alternate ? 'windowbg' : 'windowbg2', '">
						<td style="width: 10px">
							', $permission['show_help'] ? '<a href="' . $scripturl . '?action=help;in=permissionhelp_' . $permission['id'] . '" onclick="return reqWin(this);" class="help" title="' . $txt['help'] . '"></a>' : '', '
						</td>';

					if ($permission['has_own_any'])
					{
						echo '
						<td colspan="4" class="w100 left">', $permission['name'], '</td>
					</tr>
					<tr class="', $alternate ? 'windowbg' : 'windowbg2', '">';

						// Guests can't do their own thing.
						if ($context['group']['id'] != -1)
						{
							echo '
						<td></td>
						<td class="smalltext w100 right"><label for="', $permission['own']['id'], '_on">', $permission['own']['name'], ':</label></td>';

							if (empty($settings['permission_enable_deny']))
								echo '
						<td colspan="3"><input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'on' ? ' checked' : '', ' value="on" id="', $permission['own']['id'], '_on"', $disable_field, '></td>';
							else
								echo '
						<td style="width: 10px"><input type="radio" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'on' ? ' checked' : '', ' value="on" id="', $permission['own']['id'], '_on"', $disable_field, '></td>
						<td style="width: 10px"><input type="radio" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'off' ? ' checked' : '', ' value="off"', $disable_field, '></td>
						<td style="width: 10px"><input type="radio" name="perm[', $permission_type['id'], '][', $permission['own']['id'], ']"', $permission['own']['select'] == 'denied' ? ' checked' : '', ' value="deny"', $disable_field, '></td>';

							echo '
					</tr>
					<tr class="', $alternate ? 'windowbg' : 'windowbg2', '">';
						}

						echo '
						<td></td>
						<td class="smalltext w100 right"><label for="', $permission['any']['id'], '_on">', $permission['any']['name'], ':</label></td>';

						if (empty($settings['permission_enable_deny']) || $context['group']['id'] == -1)
							echo '
						<td colspan="3"><input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'on' ? ' checked' : '', ' value="on" id="', $permission['any']['id'], '_on"', $disable_field, '></td>';
						else
							echo '
						<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'on' ? ' checked' : '', ' value="on" id="', $permission['any']['id'], '_on" onclick="document.forms.permissionForm.', $permission['own']['id'], '_on.checked = true;"', $disable_field, '></td>
						<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select'] == 'off' ? ' checked' : '', ' value="off"', $disable_field, '></td>
						<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['any']['id'], ']"', $permission['any']['select']== 'denied' ? ' checked' : '', ' value="deny" id="', $permission['any']['id'], '_deny" onclick="window.weUsedDeny = true;"', $disable_field, '></td>';

						echo '
					</tr>';
					}
					else
					{
						echo '
						<td class="w100 left">', $permission['name'], '</td>';

						if (empty($settings['permission_enable_deny']) || $context['group']['id'] == -1)
							echo '
						<td><input type="checkbox" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked' : '', ' value="on"', $disable_field, '></td>';
						else
							echo '
						<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'on' ? ' checked' : '', ' value="on"', $disable_field, '></td>
						<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'off' ? ' checked' : '', ' value="off"', $disable_field, '></td>
						<td><input type="radio" name="perm[', $permission_type['id'], '][', $permission['id'], ']"', $permission['select'] == 'denied' ? ' checked' : '', ' value="deny" onclick="window.weUsedDeny = true;"', $disable_field, '></td>';

						echo '
					</tr>';
					}
				}
				$alternate = !$alternate;
			}

			// Separator
			if (!$permissionGroup['hidden'] && $has_display_content)
				echo '
					<tr class="windowbg2">
						<td colspan="5" class="w100"></td>
					</tr>';
		}
	echo '
				</table>';
	}
	echo '
				<br class="clear">';
}

function template_inline_permissions()
{
	global $context, $theme, $options, $txt, $settings;

	echo '
		<fieldset id="', $context['current_permission'], '">
			<legend><a href="#" onclick="$(\'#', $context['current_permission'], '\').hide(); $(\'#', $context['current_permission'], '_groups_link\').show(); return false;">', $txt['avatar_select_permission'], '</a></legend>';
	if (empty($settings['permission_enable_deny']))
		echo '
			<ul class="permission_groups">';
	else
		echo '
			<div class="information">', $txt['permissions_option_desc'], '</div>
			<dl class="settings">
				<dt>
					<span class="perms"><strong>', $txt['permissions_option_on'], '</strong></span>
					<span class="perms"><strong>', $txt['permissions_option_off'], '</strong></span>
					<span class="perms" style="color: red"><strong>', $txt['permissions_option_deny'], '</strong></span>
				</dt>
				<dd>
				</dd>';
	foreach ($context['member_groups'] as $group)
	{
		if (!empty($settings['permission_enable_deny']))
			echo '
				<dt>';
		else
			echo '
				<li>';

		if (empty($settings['permission_enable_deny']))
			echo '
					<input type="checkbox" name="', $context['current_permission'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked' : '', '>';
		else
			echo '
					<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="on"', $group['status'] == 'on' ? ' checked' : '', '></span>
					<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="off"', $group['status'] == 'off' ? ' checked' : '', '></span>
					<span class="perms"><input type="radio" name="', $context['current_permission'], '[', $group['id'], ']" value="deny"', $group['status'] == 'deny' ? ' checked' : '', '></span>';

		if (!empty($settings['permission_enable_deny']))
			echo '
				</dt>
				<dd>
					<span', $group['is_postgroup'] ? ' style="font-style: italic"' : '', '>', $group['name'], '</span>
				</dd>';
		else
			echo '
					<span', $group['is_postgroup'] ? ' style="font-style: italic"' : '', '>', $group['name'], '</span>
				</li>';
	}

	if (empty($settings['permission_enable_deny']))
		echo '
			</ul>';
	else
		echo '
			</dl>';

	echo '
		</fieldset>

		<a href="#" onclick="$(\'#', $context['current_permission'], '\').show(); $(\'#', $context['current_permission'], '_groups_link\').hide(); return false;" id="', $context['current_permission'], '_groups_link" class="hide">[ ', $txt['avatar_select_permission'], ' ]</a>';

	add_js('
	$("#', $context['current_permission'], '").hide();
	$("#', $context['current_permission'], '_groups_link").show();');
}
