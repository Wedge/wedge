<?php
/**
 * Wedge
 *
 * Displays the membergroups, plus the add/edit panels for membergroups, all the members in a group, and group request rejection.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_main()
{
	global $context, $theme, $options, $scripturl, $txt;

	template_show_list('regular_membergroups_list');
	echo '<br><br>';
	template_show_list('post_count_membergroups_list');
}

function template_new_group()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	echo '
		<form action="', $scripturl, '?action=admin;area=membergroups;sa=add" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['membergroups_new_group'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<label for="group_name_input"><strong>', $txt['membergroups_group_name'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="group_name" id="group_name_input" size="30">
					</dd>';

	if ($context['undefined_group'])
	{
		echo '
					<dt>
						<label for="group_type"><strong>', $txt['membergroups_edit_group_type'], '</strong>:</label>
					</dt>
					<dd>
						<fieldset id="group_type">
							<legend>', $txt['membergroups_edit_select_group_type'], '</legend>
							<label><input type="radio" name="group_type" id="group_type_private" value="0" checked onclick="swapPostGroup(0);">', $txt['membergroups_group_type_private'], '</label><br>';

		if ($context['allow_protected'])
			echo '
							<label><input type="radio" name="group_type" id="group_type_protected" value="1" onclick="swapPostGroup(0);">', $txt['membergroups_group_type_protected'], '</label><br>';

		echo '
							<label><input type="radio" name="group_type" id="group_type_request" value="2" onclick="swapPostGroup(0);">', $txt['membergroups_group_type_request'], '</label><br>
							<label><input type="radio" name="group_type" id="group_type_free" value="3" onclick="swapPostGroup(0);">', $txt['membergroups_group_type_free'], '</label><br>
							<label><input type="radio" name="group_type" id="group_type_post" value="-1" onclick="swapPostGroup(1);">', $txt['membergroups_group_type_post'], '</label><br>
						</fieldset>
					</dd>';
	}

	if ($context['post_group'] || $context['undefined_group'])
		echo '
					<dt id="min_posts_text">
						<strong>', $txt['membergroups_min_posts'], ':</strong>
					</dt>
					<dd>
						<input type="text" name="min_posts" id="min_posts_input" size="5">
					</dd>';

	if (!$context['post_group'] || !empty($settings['permission_enable_postgroups']))
	{
		echo '
					<dt>
						<label for="permission_base"><strong>', $txt['membergroups_permissions'], ':</strong></label>
						<dfn>', $txt['membergroups_can_edit_later'], '</dfn>
					</dt>
					<dd>
						<fieldset id="permission_base">
							<legend>', $txt['membergroups_select_permission_type'], '</legend>

							<label><input type="radio" name="perm_type" id="perm_type_inherit" value="inherit" checked> ', $txt['membergroups_new_as_inherit'], ':</label>
							<select name="inheritperm" id="inheritperm_select" onclick="$(\'#perm_type_inherit\').attr(\'checked\', true);">
								<option value="-1">', $txt['membergroups_guests'], '</option>
								<option value="0" selected>', $txt['membergroups_members'], '</option>';

		foreach ($context['groups'] as $group)
			echo '
								<option value="', $group['id'], '">', $group['name'], '</option>';

		echo '
							</select><br>

							<label><input type="radio" name="perm_type" id="perm_type_copy" value="copy"> ', $txt['membergroups_new_as_copy'], ':</label>
							<select name="copyperm" id="copyperm_select" onclick="$(\'#perm_type_copy\').attr(\'checked\', true);">
								<option value="-1">', $txt['membergroups_guests'], '</option>
								<option value="0" selected>', $txt['membergroups_members'], '</option>';

		foreach ($context['groups'] as $group)
			echo '
								<option value="', $group['id'], '">', $group['name'], '</option>';

		echo '
							</select><br>

							<label><input type="radio" name="perm_type" id="perm_type_predefined" value="predefined"> ', $txt['membergroups_new_as_type'], ':</label>
							<select name="level" id="level_select" onclick="$(\'#perm_type_predefined\').attr(\'checked\', true);">
								<option value="restrict">', $txt['permitgroups_restrict'], '</option>
								<option value="standard" selected>', $txt['permitgroups_standard'], '</option>
								<option value="moderator">', $txt['permitgroups_moderator'], '</option>
								<option value="maintenance">', $txt['permitgroups_maintenance'], '</option>
							</select>
						</fieldset>
					</dd>';
	}

	if (!empty($context['boards']))
		template_group_board_selection();

	echo '
				</dl>
				<div class="right">
					<input type="submit" value="', $txt['membergroups_add_group'], '" class="new">
				</div>
			</div>';

	if ($context['undefined_group'])
		add_js_inline('
	function swapPostGroup(isChecked)
	{
		var min_posts_text = document.getElementById("min_posts_text");
		document.getElementById("min_posts_input").disabled = !isChecked;
		min_posts_text.style.color = isChecked ? "" : "#888888";
	}
	swapPostGroup(', $context['post_group'] ? 'true' : 'false', ');');

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
}

function template_edit_group()
{
	global $context, $theme, $options, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=admin;area=membergroups;sa=edit;group=', $context['group']['id'], '" method="post" accept-charset="UTF-8" name="groupForm" id="groupForm">
			<we:cat>
				', $txt['membergroups_edit_group'], ' - ', $context['group']['name'], '
			</we:cat>
			<div class="windowbg2 wrc">
				<dl class="settings">
					<dt>
						<label for="group_name_input"><strong>', $txt['membergroups_edit_name'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="group_name" id="group_name_input" value="', $context['group']['editable_name'], '" size="30">
					</dd>';

	if ($context['group']['id'] != 3 && $context['group']['id'] != 4)
		echo '
					<dt id="group_desc_text">
						<label for="group_desc_input"><strong>', $txt['membergroups_edit_desc'], ':</strong></label>
					</dt>
					<dd>
						<textarea name="group_desc" id="group_desc_input" rows="4" cols="40">', $context['group']['description'], '</textarea>
					</dd>';

	// Group type...
	// !! FIX THIS!!!!!!!!!!!
	if ($context['group']['allow_post_group'])
	{
		echo '
					<dt>
						<label for="group_type"><strong>', $txt['membergroups_edit_group_type'], ':</strong></label>
					</dt>
					<dd>
						<fieldset id="group_type">
							<legend>', $txt['membergroups_edit_select_group_type'], '</legend>
							<label><input type="radio" name="group_type" id="group_type_private" value="0"', !$context['group']['is_post_group'] && $context['group']['type'] == 0 ? ' checked' : '', ' onclick="swapPostGroup(0);"> ', $txt['membergroups_group_type_private'], '</label><br>';

		if ($context['group']['allow_protected'])
			echo '
							<label><input type="radio" name="group_type" id="group_type_protected" value="1"', $context['group']['type'] == 1 ? ' checked' : '', ' onclick="swapPostGroup(0);"> ', $txt['membergroups_group_type_protected'], '</label><br>';

		echo '
							<label><input type="radio" name="group_type" id="group_type_request" value="2"', $context['group']['type'] == 2 ? ' checked' : '', ' onclick="swapPostGroup(0);"> ', $txt['membergroups_group_type_request'], '</label><br>
							<label><input type="radio" name="group_type" id="group_type_free" value="3"', $context['group']['type'] == 3 ? ' checked' : '', ' onclick="swapPostGroup(0);"> ', $txt['membergroups_group_type_free'], '</label><br>
							<label><input type="radio" name="group_type" id="group_type_post" value="-1"', $context['group']['is_post_group'] ? ' checked' : '', ' onclick="swapPostGroup(1);"> ', $txt['membergroups_group_type_post'], '</label><br>
						</fieldset>
					</dd>';
	}

	if ($context['group']['id'] != 3 && $context['group']['id'] != 4)
		echo '
					<dt id="group_moderators_text">
						<label for="group_moderators"><strong>', $txt['moderators'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="group_moderators" id="group_moderators" value="', $context['group']['moderator_list'], '" size="30">
					</dd>
					<dt id="group_hidden_text">
						<label for="group_hidden_input"><strong>', $txt['membergroups_edit_hidden'], ':</strong></label>
					</dt>
					<dd>
						<select name="group_hidden" id="group_hidden_input" onchange="if (this.value == 2 && !ask(', JavaScriptEscape($txt['membergroups_edit_hidden_warning']), ', e)) $(this).val(0).sb();">
							<option value="0"', $context['group']['hidden'] ? '' : ' selected', '>', $txt['membergroups_edit_hidden_no'], '</option>
							<option value="1"', $context['group']['hidden'] == 1 ? ' selected' : '', '>', $txt['membergroups_edit_hidden_boardindex'], '</option>
							<option value="2"', $context['group']['hidden'] == 2 ? ' selected' : '', '>', $txt['membergroups_edit_hidden_all'], '</option>
						</select>
					</dd>';

	// Can they inherit permissions?
	if ($context['group']['id'] > 1 && $context['group']['id'] != 3)
	{
		echo '
					<dt id="group_inherit_text">
						<label for="group_inherit_input"><strong>', $txt['membergroups_edit_inherit_permissions'], '</strong></label>:
						<dfn>', $txt['membergroups_edit_inherit_permissions_desc'], '</dfn>
					</dt>
					<dd>
						<select name="group_inherit" id="group_inherit_input">
							<option value="-2">', $txt['membergroups_edit_inherit_permissions_no'], '</option>
							<option value="-1"', $context['group']['inherited_from'] == -1 ? ' selected' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $txt['membergroups_guests'], '</option>
							<option value="0"', $context['group']['inherited_from'] == 0 ? ' selected' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $txt['membergroups_members'], '</option>';

		// For all the inheritable groups show an option.
		foreach ($context['inheritable_groups'] as $id => $group)
			echo '
							<option value="', $id, '"', $context['group']['inherited_from'] == $id ? ' selected' : '', '>', $txt['membergroups_edit_inherit_permissions_from'], ': ', $group, '</option>';

		echo '
						</select>
						<input type="hidden" name="old_inherit" value="', $context['group']['inherited_from'], '">
					</dd>';
	}

	if ($context['group']['allow_post_group'])
		echo '
					<dt id="min_posts_text">
						<label for="min_posts_input"><strong>', $txt['membergroups_min_posts'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="min_posts" id="min_posts_input"', $context['group']['is_post_group'] ? ' value="' . $context['group']['min_posts'] . '"' : '', ' size="6">
					</dd>';

	echo '
					<dt>
						<label for="online_color_input"><strong>', $txt['membergroups_online_color'], ':</strong></label>
						<dfn>', $txt['membergroups_online_color_desc'], '</dfn>
					</dt>
					<dd>
						<input type="text" name="online_color" id="online_color_input" value="', $context['group']['color'], '" size="20">
					</dd>
					<dt>
						<label for="star_count_input"><strong>', $txt['membergroups_star_count'], ':</strong></label>
						<dfn>', $txt['membergroups_star_count_note'], '</dfn>
					</dt>
					<dd>
						<input type="text" name="star_count" id="star_count_input" value="', $context['group']['star_count'], '" size="4" onkeyup="if (this.value.length > 2) this.value = 99;" onkeydown="this.onkeyup();" onchange="if (this.value != 0) this.form.star_image.onchange();">
					</dd>
					<dt>
						<label for="star_image_input"><strong>', $txt['membergroups_star_image'], ':</strong></label>
						<dfn>', $txt['membergroups_star_image_note'], '</dfn>
					</dt>
					<dd>
						', $txt['membergroups_images_url'], '
						<input type="text" name="star_image" id="star_image_input" value="', $context['group']['star_image'], '" onchange="if (this.value && this.form.star_count.value == 0) this.form.star_count.value = 1; else if (!this.value) this.form.star_count.value = 0; $(\'#star_preview\').attr(\'src\', we_theme_url + \'/images/\' + (this.value && this.form.star_count.value > 0 ? this.value.replace(/\$language/g, \'', $context['user']['language'], '\') : \'blank.gif\'));" size="20">
						<img id="star_preview" src="', $theme['images_url'], '/', $context['group']['star_image'] == '' ? 'blank.gif' : $context['group']['star_image'], '">
					</dd>
					<dt>
						<label for="max_messages_input"><strong>', $txt['membergroups_max_messages'], ':</strong></label>
						<dfn>', $txt['membergroups_max_messages_note'], '</dfn>
					</dt>
					<dd>
						<input type="text" name="max_messages" id="max_messages_input" value="', $context['group']['id'] == 1 ? 0 : $context['group']['max_messages'], '" size="6"', $context['group']['id'] == 1 ? ' disabled' : '', '>
					</dd>';

	if (!empty($context['boards']))
		template_group_board_selection();

	echo '
				</dl>
				<div class="right">
					<input type="submit" name="save" value="', $txt['membergroups_edit_save'], '" class="save">', $context['group']['allow_delete'] ? '
					<input type="submit" name="delete" value="' . $txt['membergroups_delete'] . '" onclick="return ask(' . JavaScriptEscape($txt['membergroups_confirm_delete']) . ', e);" class="delete">' : '', '
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';

	if ($context['group']['id'] != 3 && $context['group']['id'] != 4)
	{
		add_js_file('scripts/suggest.js');

		add_js('
	new weAutoSuggest({
		bItemList: true,
		sControlId: "group_moderators",
		sPostName: "moderator_list",
		sTextDeleteItem: ', JavaScriptEscape($txt['autosuggest_delete_item']), ',
		aListItems: {');

		foreach ($context['group']['moderators'] as $id_member => $member_name)
			add_js('
			', (int) $id_member, ': ', JavaScriptEscape($member_name), $id_member == $context['group']['last_moderator_id'] ? '' : ',');

		add_js('
		}
	});');
	}

	if ($context['group']['allow_post_group'])
		add_js_inline('
	function swapPostGroup(isChecked)
	{
		var min_posts_text = document.getElementById("min_posts_text");
		var group_desc_text = document.getElementById("group_desc_text");
		var group_hidden_text = document.getElementById("group_hidden_text");
		var group_moderators_text = document.getElementById("group_moderators_text");
		document.forms.groupForm.min_posts.disabled = !isChecked;
		min_posts_text.style.color = isChecked ? "" : "#888888";
		document.forms.groupForm.group_desc_input.disabled = isChecked;
		group_desc_text.style.color = !isChecked ? "" : "#888888";
		document.forms.groupForm.group_hidden_input.disabled = isChecked;
		group_hidden_text.style.color = !isChecked ? "" : "#888888";
		if (group_moderators_text)
		{
			document.forms.groupForm.group_moderators.disabled = isChecked;
			group_moderators_text.style.color = !isChecked ? "" : "#888888";
		}
	}
	swapPostGroup(', $context['group']['is_post_group'] ? 'true' : 'false', ');');
}

function template_group_board_selection()
{
	global $context, $txt;

	echo '
					<dt>
						<strong>', $txt['membergroups_new_board'], ':</strong>', !empty($context['group']['is_post_group']) ? '
						<dfn>' . $txt['membergroups_new_board_post_groups'] . '</dfn>' : '', '
					</dt>
					<dd>
						<label><input type="checkbox" name="view_enter_same" id="view_enter_same"', !empty($context['view_enter_same']) ? ' checked' : '', ' onclick="$(\'#enter_perm_col\').toggle(!this.checked)"> ', $txt['membergroups_view_enter_same'], '</label><br>
						<label><input type="checkbox" name="need_deny_perm" id="need_deny_perm"', !empty($context['need_deny_perm']) ? ' checked' : '', ' onclick="$(\'.deny_perm\').toggle(this.checked)"> ', $txt['membergroups_need_deny_perm'], '</label> <a href="<URL>?action=help;in=need_deny_perm" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a><br>
						<br>
						<fieldset id="view_perm_col">
							<legend>', $txt['membergroups_board_see'], '</legend>
							<table>
								<tr>
									<th></th>
									<th>', $txt['yes'], '</th>
									<th>', $txt['no'], '</th>
									<th class="deny_perm"', empty($context['need_deny_perm']) ? ' style="display:none"' : '', '>', $txt['group_boards_never'], '</th>
								</tr>
								<tr>
									<td class="smalltext">
										<span class="everything" title="', $txt['group_boards_everything_desc'], '">', $txt['group_boards_everything'], '</span>
									</td>
									<td>
										<input type="radio" name="vieweverything" value="allow" onchange="updateView(\'view\', this)">
									</td>
									<td>
										<input type="radio" name="vieweverything" value="disallow" onchange="updateView(\'view\', this)">
									</td>
									<td class="deny_perm center"', empty($context['need_deny_perm']) ? ' style="display:none"' : '', '>
										<input type="radio" name="vieweverything" value="deny" onchange="updateView(\'view\', this)">
									</td>
								</tr>';

	$last_cat = -1;
	foreach ($context['boards'] as $board)
	{
		if ($board['id_cat'] != $last_cat)
		{
			echo '
								<tr class="div"></tr>
								<tr class="board_cat" data-cathead="', $board['id_cat'], '">
									<td class="smalltext">
										<span style="margin-left:0em">', $board['cat_name'], '</span>
									</td>
									<td>
										<input type="radio" name="cat[', $board['id_cat'], ']" value="allow" onchange="selectCat(\'view\',', $board['id_cat'], ',this);">
									</td>
									<td>
										<input type="radio" name="cat[', $board['id_cat'], ']" value="disallow" onchange="selectCat(\'view\',', $board['id_cat'], ',this);">
									</td>
									<td class="deny_perm center"', empty($context['need_deny_perm']) ? ' style="display:none"' : '', '>
										<input type="radio" name="cat[', $board['id_cat'], ']" value="deny" onchange="selectCat(\'view\',', $board['id_cat'], ',this);">
									</td>
								</tr>';
			$last_cat = $board['id_cat'];
		}

		echo '
								<tr data-cat="', $board['id_cat'], '">
									<td class="smalltext">
										<span style="margin-left:', $board['child_level'], 'em">', $board['name'], '</span>
									</td>
									<td>
										<input type="radio" name="viewboard[', $board['id'], ']" value="allow"', $board['view_perm'] == 'allow' ? ' checked' : '', '>
									</td>
									<td>
										<input type="radio" name="viewboard[', $board['id'], ']" value="disallow"', (empty($context['need_deny_perm']) && $board['view_perm'] == 'deny') || $board['view_perm'] == 'disallow' ? ' checked' : '', '>
									</td>
									<td class="deny_perm center"', empty($context['need_deny_perm']) ? ' style="display:none"' : '', '>
										<input type="radio" name="viewboard[', $board['id'], ']" value="deny"', !empty($context['need_deny_perm']) && $board['view_perm'] == 'deny' ? ' checked' : '', '>
									</td>
								</tr>';
	}

	echo '
							</table>
						</fieldset>
						<fieldset id="enter_perm_col"', !empty($context['view_enter_same']) ? ' style="display:none"' : '', '>
							<legend>', $txt['membergroups_board_enter'], '</legend>
							<table>
								<tr>
									<th></th>
									<th>', $txt['yes'], '</th>
									<th>', $txt['no'], '</th>
									<th class="deny_perm"', empty($context['need_deny_perm']) ? ' style="display:none"' : '', '>', $txt['group_boards_never'], '</th>
								</tr>
								<tr>
									<td class="smalltext">
										<span class="everything" title="', $txt['group_boards_everything_desc'], '">', $txt['group_boards_everything'], '</span>
									</td>
									<td>
										<input type="radio" name="entereverything" value="allow" onchange="updateView(\'enter\', this)">
									</td>
									<td>
										<input type="radio" name="entereverything" value="disallow" onchange="updateView(\'enter\', this)">
									</td>
									<td class="deny_perm center"', empty($context['need_deny_perm']) ? ' style="display:none"' : '', '>
										<input type="radio" name="entereverything" value="deny" onchange="updateView(\'enter\', this)">
									</td>
								</tr>';

	$last_cat = -1;
	foreach ($context['boards'] as $board)
	{
		if ($board['id_cat'] != $last_cat)
		{
			echo '
								<tr class="div"></tr>
								<tr class="board_cat" data-cathead="', $board['id_cat'], '">
									<td class="smalltext">
										<span style="margin-left:0em">', $board['cat_name'], '</span>
									</td>
									<td>
										<input type="radio" name="cat[', $board['id_cat'], ']" value="allow" onchange="selectCat(\'enter\',', $board['id_cat'], ',this);">
									</td>
									<td>
										<input type="radio" name="cat[', $board['id_cat'], ']" value="disallow" onchange="selectCat(\'enter\',', $board['id_cat'], ',this);">
									</td>
									<td class="deny_perm center"', empty($context['need_deny_perm']) ? ' style="display:none"' : '', '>
										<input type="radio" name="cat[', $board['id_cat'], ']" value="deny" onchange="selectCat(\'enter\',', $board['id_cat'], ',this);">
									</td>
								</tr>';
			$last_cat = $board['id_cat'];
		}

		echo '
								<tr data-cat="', $board['id_cat'], '">
									<td class="smalltext">
										<span style="margin-left:', $board['child_level'], 'em">', $board['name'], '</span>
									</td>
									<td>
										<input type="radio" name="enterboard[', $board['id'], ']" value="allow"', $board['enter_perm'] == 'allow' ? ' checked' : '', '>
									</td>
									<td>
										<input type="radio" name="enterboard[', $board['id'], ']" value="disallow"', (empty($context['need_deny_perm']) && $board['enter_perm'] == 'deny') || $board['enter_perm'] == 'disallow' ? ' checked' : '', '>
									</td>
									<td class="deny_perm center"', empty($context['need_deny_perm']) ? ' style="display:none"' : '', '>
										<input type="radio" name="enterboard[', $board['id'], ']" value="deny"', !empty($context['need_deny_perm']) && $board['enter_perm'] == 'deny' ? ' checked' : '', '>
									</td>
								</tr>';
	}

	echo '
							</table>
						</fieldset>
					</dd>';

	add_js('
	function updateView(selection, obj)
	{
		if ((selection == "view" || selection=="enter") && (obj.value == "allow" || obj.value == "disallow" || obj.value == "deny"))
		{
			$(\'#\' + selection + \'_perm_col tr.board_cat input\').attr(\'checked\', false);
			$(\'#\' + selection + \'_perm_col input[name^="\' + selection + \'"]\').filter(\'[value="\' + obj.value + \'"]\').attr(\'checked\', true);
			$(\'input[name="\' + selection + \'everything"]\').attr(\'checked\', false);
		}
	};
	function selectCat(selection, id_cat, obj)
	{
		if (obj.value == "allow" || obj.value == "disallow" || obj.value == "deny")
		{
			$(\'#\' + selection + \'_perm_col tr[data-cat="\' + id_cat + \'"] input\').filter(\'[value="\' + obj.value + \'"]\').attr(\'checked\', true);
			$(\'#\' + selection + \'_perm_col tr.board_cat[data-cathead="\' + id_cat + \'"] input\').attr(\'checked\', false);
		}
	}');
}

// Templating for viewing the members of a group.
function template_group_members()
{
	global $context, $theme, $options, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=', $context['current_action'], isset($context['admin_area']) ? ';area=' . $context['admin_area'] : '', ';sa=members;group=', $context['group']['id'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $context['page_title'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<strong>', $txt['name'], ':</strong>
					</dt>
					<dd>
						<span ', $context['group']['online_color'] ? 'style="color: ' . $context['group']['online_color'] . '"' : '', '>', $context['group']['name'], '</span> ', $context['group']['stars'], '
					</dd>';

	// Any description to show?
	if (!empty($context['group']['description']))
		echo '
					<dt>
						<strong>' . $txt['membergroups_members_description'] . ':</strong>
					</dt>
					<dd>
						', $context['group']['description'], '
					</dd>';

	echo '
					<dt>
						<strong>', $txt['membergroups_members_top'], ':</strong>
					</dt>
					<dd>
						', $context['total_members'], '
					</dd>';

	// Any group moderators to show?
	if (!empty($context['group']['moderators']))
	{
		$moderators = array();
		foreach ($context['group']['moderators'] as $moderator)
			$moderators[] = '<a href="' . $scripturl . '?action=profile;u=' . $moderator['id'] . '">' . $moderator['name'] . '</a>';

		echo '
					<dt>
						<strong>', $txt['membergroups_members_group_moderators'], ':</strong>
					</dt>
					<dd>
						', implode(', ', $moderators), '
					</dd>';
	}

	echo '
				</dl>
			</div>

			<br>
			<we:title2>
				', $txt['membergroups_members_group_members'], '
			</we:title2>
			<br>
			<div class="pagesection">
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
			</div>
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg">
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=name', $context['sort_by'] == 'name' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['name'], $context['sort_by'] == 'name' ? ' <img src="' . $theme['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a></th>
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=email', $context['sort_by'] == 'email' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['email'], $context['sort_by'] == 'email' ? ' <img src="' . $theme['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a></th>
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=active', $context['sort_by'] == 'active' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['membergroups_members_last_active'], $context['sort_by'] == 'active' ? ' <img src="' . $theme['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a></th>
						<th><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=registered', $context['sort_by'] == 'registered' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['date_registered'], $context['sort_by'] == 'registered' ? ' <img src="' . $theme['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a></th>
						<th', empty($context['group']['assignable']) ? ' colspan="2"' : '', '><a href="', $scripturl, '?action=', $context['current_action'], (isset($context['admin_area']) ? ';area=' . $context['admin_area'] : ''), ';sa=members;start=', $context['start'], ';sort=posts', $context['sort_by'] == 'posts' && $context['sort_direction'] == 'up' ? ';desc' : '', ';group=', $context['group']['id'], '">', $txt['posts'], $context['sort_by'] == 'posts' ? ' <img src="' . $theme['images_url'] . '/sort_' . $context['sort_direction'] . '.gif">' : '', '</a></th>';
	if (!empty($context['group']['assignable']))
		echo '
						<td style="width: 4%" class="center"><input type="checkbox" onclick="invertAll(this, this.form);"></td>';
	echo '
					</tr>
				</thead>
				<tbody>';

	if (empty($context['members']))
		echo '
					<tr class="windowbg2 center">
						<td colspan="6">', $txt['membergroups_members_no_members'], '</td>
					</tr>';

	foreach ($context['members'] as $member)
	{
		echo '
					<tr class="windowbg2">
						<td>', $member['name'], '</td>
						<td', $member['show_email'] == 'no_through_forum' && $theme['use_image_buttons'] ? ' class="center"' : '', '>';

		// Is it totally hidden?
		if ($member['show_email'] == 'no')
			echo '
							<em>', $txt['hidden'], '</em>';
		// ... otherwise they want it hidden but it's not to this person?
		elseif ($member['show_email'] == 'yes_permission_override')
			echo '
							<a href="mailto:', $member['email'], '"><em>', $member['email'], '</em></a>';
		// ... otherwise it's visible - but only via an image?
		elseif ($member['show_email'] == 'no_through_forum')
			echo '
							<a href="', $scripturl, '?action=emailuser;sa=email;uid=', $member['id'], '" rel="nofollow">', ($theme['use_image_buttons'] ? '<img src="' . $theme['images_url'] . '/email_sm.gif" alt="' . $txt['email'] . '" title="' . $txt['email'] . '">' : $txt['email']), '</a>';

		echo '
						</td>
						<td class="windowbg">', $member['last_online'], '</td>
						<td class="windowbg">', $member['registered'], '</td>
						<td', empty($context['group']['assignable']) ? ' colspan="2"' : '', '>', $member['posts'], '</td>';
		if (!empty($context['group']['assignable']))
			echo '
						<td class="center" style="width: 4%"><input type="checkbox" name="rem[]" value="', $member['id'], '"', ($context['user']['id'] == $member['id'] && $context['group']['id'] == 1 ? ' onclick="return !this.checked || ask(' . JavaScriptEscape($txt['membergroups_members_deadmin_confirm']) . ', e);"' : ''), '></td>';
		echo '
					</tr>';
	}

	echo '
				</tbody>
			</table>
			<div class="pagesection">
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>';

	if (!empty($context['group']['assignable']))
		echo '
				<div class="floatright"><input type="submit" name="remove" value="', $txt['membergroups_members_remove'], '" class="delete"></div>';
	echo '
			</div>
			<br>';

	if (!empty($context['group']['assignable']))
	{
		echo '
			<we:cat>
				', $txt['membergroups_members_add_title'], '
			</we:cat>
			<div class="windowbg wrc">
				<strong>', $txt['membergroups_members_add_desc'], ':</strong><br>
				<input type="text" name="toAdd" id="toAdd" value="">
				<input type="submit" name="add" value="', $txt['membergroups_members_add'], '" class="new">
			</div>';
	}

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';

	if (!empty($context['group']['assignable']))
	{
		add_js_file('scripts/suggest.js');

		add_js('
	new weAutoSuggest({
		bItemList: true,
		sControlId: "toAdd",
		sPostName: "member_add",
		sTextDeleteItem: ', JavaScriptEscape($txt['autosuggest_delete_item']), '
	});');
	}
}

// Allow the moderator to enter a reason to each user being rejected.
function template_group_request_reason()
{
	global $theme, $options, $context, $txt, $scripturl;

	// Show a welcome message to the user.
	echo '
		<form action="', $scripturl, '?action=groups;sa=requests" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['mc_groups_reason_title'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">';

	// Loop through and print out a reason box for each...
	foreach ($context['group_requests'] as $request)
		echo '
					<dt>
						<strong>', sprintf($txt['mc_groupr_reason_desc'], $request['member_link'], $request['group_link']), ':</strong>
					</dt>
					<dd>
						<input type="hidden" name="groupr[]" value="', $request['id'], '">
						<textarea name="groupreason[', $request['id'], ']" rows="3" cols="40" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 96%; min-width: 96%' : 'width: 96%') . '"></textarea>
					</dd>';

	echo '
				</dl>
				<input type="submit" name="go" value="', $txt['mc_groupr_submit'], '" class="submit">
				<input type="hidden" name="req_action" value="got_reason">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>';
}

?>