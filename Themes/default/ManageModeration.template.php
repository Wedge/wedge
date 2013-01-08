<?php
/**
 * Wedge
 *
 * Displays the moderation filters system.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_modfilter_home()
{
	global $context, $txt;

	if (!empty($context['approved_all']))
		echo '
	<div class="windowbg" id="profile_success">
		', $txt['modfilter_all_approved'], '
	</div>';

	echo '
	<we:cat>', $txt['admin_mod_filters'], '</we:cat>';

	if (empty($context['rules']))
	{
		echo '
	<p class="description">', $txt['modfilter_norules'], '</p>';
	}

	foreach ($context['rules'] as $type => $rule_block)
	{
		echo '
	<table class="table_grid cs0 w100">
		<thead>
			<tr class="catbg">
				<th scope="col" class="first_th left">', $txt['modfilter_rule_' . $type], '</th>
				<th class="left">', $txt['modfilter_conditions'], '</th>
				<th scope="col" class="last_th"></th>
			</tr>
		</thead>
		<tbody>';

		$use_bg2 = true;
		foreach ($rule_block as $id => $rules)
		{
			$use_bg2 = !$use_bg2;

			$action = $rules['action'];
			$rule_params = $rules['criteria'];

			echo '
			<tr class="windowbg', $use_bg2 ? '2' : '', '">
				<td style="width: 30%">', isset($txt['modfilter_action_' . $action]) ? $txt['modfilter_action_' . $action] : $action, !empty($rules['msg']) ? '<div class="smalltext"><a href="<URL>?action=admin;area=modfilters;sa=msgpopup;ruletype=' . $type . ';rule=' . $rules['msg'] . '" onclick="return reqWin(this);">(' . $txt['modfilter_msg'] . ')</a></div>' : '', '</td>
				<td>';

			$print_criteria = array();
			foreach ($rules['criteria'] as $criteria)
			{
				switch ($criteria['name'])
				{
					case 'boards':
						$str = isset($criteria['id']) ? $txt['modfilter_cond_boards_in'] : $txt['modfilter_cond_boards_ex'];
						$list = isset($criteria['id']) ? $criteria['id'] : $criteria['except-id'];
						foreach ($list as $k => $v)
						{
							if (isset($context['boards'][$v]))
								$list[$k] = '<a href="<URL>?board=' . $v . '">' . $context['boards'][$v]['name'] . '</a>';
							else
								$list[$k] = '<em>???? (#' . $v . ')</em>';
						}
						$print_criteria[] = $str . ' ' . implode(', ', $list);
						break;
					case 'userid':
						$str = isset($criteria['id']) ? $txt['modfilter_cond_userid_in'] : $txt['modfilter_cond_userid_ex'];
						$list = isset($criteria['id']) ? $criteria['id'] : $criteria['except-id'];
						foreach ($list as $k => $v)
						{
							if (isset($context['users'][$v]))
								$list[$k] = '<a href="<URL>?action=profile;u=' . $v . '">' . $context['users'][$v]['member_name'] . '</a>';
							else
								$list[$k] = '<em>???? (#' . $v . ')</em>';
						}
						$print_criteria[] = $str . ' ' . implode(', ', $list);
						break;
					case 'postcount':
					case 'warning':
					case 'links':
						$print_criteria[] = $txt['modfilter_cond_' . $criteria['name']] . ' ' . $txt['modfilter_range_' . $criteria['term']] . ' ' . $criteria['value'];
						break;
					case 'subject':
					case 'body':
						if (empty($criteria['apply']))
							$print_criteria[] = $txt['modfilter_cond_' . $criteria['name'] . '_regex'] . ' ' . htmlspecialchars($criteria['value']);
						else
							$print_criteria[] = $txt['modfilter_cond_' . $criteria['name'] . '_' . $criteria['apply']] . ' ' . htmlspecialchars($criteria['value']) . ' ' . $txt['modfilter_case_' . ($criteria['case-ins'] ? 'insensitive' : 'sensitive')];
						break;
					case 'groups':
						$str = isset($criteria['id']) ? $txt['modfilter_cond_groups_in'] : $txt['modfilter_cond_groups_ex'];
						$list = isset($criteria['id']) ? $criteria['id'] : $criteria['except-id'];
						foreach ($list as $k => $v)
						{
							if (isset($context['membergroups'][$v]))
								$list[$k] = '<a href="<URL>?action=admin;area=membergroups;sa=edit;group=' . $v . '"' . (!empty($context['membergroups'][$v]['online_color']) ? ' style="color: ' . $context['membergroups'][$v]['online_color'] .'"' : '') . '>' . $context['membergroups'][$v]['group_name'] . '</a>';
							else
								$list[$k] = '<em>???? (#' . $v . ')</em>';
						}
						$print_criteria[] = $str . ' ' . implode(', ', $list);
						break;
						break;
					case 'permission':
						$str = isset($criteria['id']) ? $txt['modfilter_cond_permissions_in'] : $txt['modfilter_cond_permissions_ex'];
						$list = isset($criteria['id']) ? $criteria['id'] : $criteria['except-id'];
						$print_criteria[] = $str . ' ' . implode(', ', $list);
						break;
					default:
						if (!empty($criteria['function']))
							$print_criteria[] = $criteria['function']($criteria);
						else
							$print_criteria[] = $txt['modfilter_cond_unknownrule'] . ' ' . $criteria['name'];
				}
			}

			echo '
					<ul><li>', implode('</li><li>', $print_criteria), '</li></ul>';

			echo '</td>
				<td class="center" style="width: 15%"><a href="<URL>?action=admin;area=modfilters;sa=edit;type=', $type, ';rule=', ($id+1), '">', $txt['modify'], '</td>
			</tr>';
		}
		echo '
		</tbody>
	</table>';
	}

	echo '
	<form action="<URL>?action=admin;area=modfilters;sa=add" method="post">
		<div class="pagesection">
			<div class="floatright">
				<div class="additional_row right"><input type="submit" name="new" value="', $txt['modfilter_addrule'], '" class="new"></div>
			</div>
		</div>
	</form>

	<we:cat>', $txt['modfilter_approve_title'], '</we:cat>
	<form action="<URL>?action=admin;area=modfilters;sa=approveall" method="post">
		<div class="windowbg wrc">
			<p>', $txt['modfilter_approve_desc'], '</p>
			<input type="submit" value="', $txt['modfilter_approve_title'], '" class="submit">
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';

	echo '
	<br class="clear">';
}

function template_modfilter_edit()
{
	global $context, $txt;

	if (empty($context['edit_modaction']))
		$context['edit_modaction'] = '';
	if (empty($context['edit_applies']))
		$context['edit_applies'] = '';
	if (empty($context['edit_rules']))
		$context['edit_rules'] = array();

	echo '
	<we:cat>', $context['page_title'], '</we:cat>
	<form action="<URL>?action=admin;area=modfilters;sa=save" method="post">';

	// Are we doing an edit?
	if (!empty($context['prev_type']))
		echo '
		<input type="hidden" name="prev_type" value="', $context['prev_type'], '">
		<input type="hidden" name="prev_id" value="', $context['prev_id'], '">';

	echo '
		<div class="windowbg2 wrc">
			<fieldset>
				<legend>', $txt['modfilter_action_legend'], '</legend>
				<p>', $txt['modfilter_action_desc'], '</p>
				<dl class="settings">
					<dt>', $txt['modfilter_action_rule'], '</dt>
					<select id="action" name="modaction" onchange="updateForm();">
						<option value="" data-hide>', $txt['modfilter_action_selectone'], '</option>';

	foreach ($context['modfilter_action_list'] as $item)
	{
		if (empty($item) || empty($txt['modfilter_actionlist_' . $item]))
			echo '
						<option class="hr"></option>';
		else
			echo '
						<option value="', $item, '"', $context['edit_modaction'] == $item ? ' selected' : '', '> ', $txt['modfilter_actionlist_' . $item], '</option>';
	}

	echo '
					</select>
				</dl>
			</fieldset>
			<fieldset id="fs_applies" class="hide">
				<legend>', $txt['modfilter_applies_legend'], '</legend>
				<p>', $txt['modfilter_applies_desc'], '</p>
				<dl class="settings">
					<dt>', $txt['modfilter_applies_rule'], '</dt>
					<dd>
						<label><input type="radio" name="applies" value="posts"', $context['edit_applies'] == 'posts' ? ' checked' : '', ' onchange="updateForm();"> ', $txt['modfilter_applies_posts'], '</label><br>
						<label><input type="radio" name="applies" value="topics"', $context['edit_applies'] == 'topics' ? ' checked' : '', ' onchange="updateForm();"> ', $txt['modfilter_applies_topics'], '</label><br>
					</dd>
				</dl>
			</fieldset>
			<fieldset id="fs_conds" class="hide">
				<legend>', $txt['modfilter_conds_legend'], '</legend>
				<p>', $txt['modfilter_conds_desc'], '</p>
				<table class="table_grid cs0" style="width: 100%" id="conds">
					<thead>
						<tr class="catbg">
							<th scope="col" class="first_th left" style="width: 40%">', $txt['modfilter_conds_item'], '</th>
							<th scope="col" class="left">', $txt['modfilter_conds_criteria'], '</th>
							<th scope="col" class="last_th" style="width: 10%"></th>
						</tr>
					</thead>
					<tbody id="conds_empty">
						<tr class="windowbg2">
							<td colspan="3" class="center">', $txt['modfilter_conds_no_conditions'], '</td>
						</tr>
					</tbody>
					<tbody id="conds_notempty">';

	if (!empty($context['edit_rules']))
	{
		foreach ($context['edit_rules'] as $id => $rule)
		{
			echo '<tr id="cond_row_', ($id + 1), '" class="windowbg"><td>', $rule['rule'], '</td><td>', $rule['details'];
			if (!empty($rule['rulevalue']))
				echo '<input type="hidden" name="rule[]" value="', $rule['rulevalue'], '">';
			echo '</td><td class="center"><a href="#" onclick="removeRow(', ($id + 1), '); return false;">', $txt['remove'], '</a></td></tr>';
		}
		add_js('
	$("#conds_empty").hide();');
	}

	echo '
					</tbody>
				</table>
				<br>
				<div class="right">
					', $txt['modfilter_conds_new'], '
					<select name="condtype" id="condtype" onchange="setRuleContent();">
						<option value="" data-hide>', $txt['modfilter_conds_select'], '</option>';

	foreach ($context['modfilter_rule_types'] as $type)
		echo '
						<option value="', $type, '">', $txt['modfilter_condtype_' . $type], '</option>';

	echo '
					</select>
				</div>
				<div id="rulecontainer"></div>
			</fieldset>
			<fieldset id="fs_langs" class="hide">
				<legend>', $txt['modfilter_msg_popup_title'], '</legend>
				<p>', $txt['modfilter_lang_msg'], '</p>
				<dl class="settings">';

	foreach ($context['lang_msg'] as $lang => $entry)
		echo '
					<dt><span class="flag_', $lang, '"></span> ', $entry['name'], '</dt>
					<dd><textarea name="msg_', $lang, '">', $entry['msg'], '</textarea></dd>';

	echo '
				</dl>
			</fieldset>
			<div class="pagesection">
				<div class="floatright">
					<div class="additional_row right">
						<input id="btnSave" type="submit" class="save" value="', $txt['modfilter_save_this_rule'], '">';

	if (!empty($context['prev_id']))
		echo '
						<input name="delete" type="submit" class="delete" value="', $txt['modfilter_remove_this_rule'], '">';

	echo '
					</div>
				</div>
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';

	// Just to summarise the code.
	// updateForm is used to work out where the user is in workflow, and only show them the right parts of the form
	// setRuleContent takes the selection of what type of criteria the user wants to add and puts it into the container to work with; note that it clones the existing markup rather naively, so it has to rebind events, remove duplicate markup of selectbox and then create a new selectbox
	// addRow adds a row to the table of rules, which deals with appending to the table, making sure the input is created etc.
	// deleteRow deletes a row from the table of rules, and makes sure we show the 'none added' block if appropriate
	add_js('
	function updateForm()
	{
		if ($("#action").val() != "")
		{
			$("#fs_applies").show();

			var applies = $("input:radio[name=applies]:checked").val(),
				action = $("#action").val();
			$("#fs_conds").toggle(applies == "posts" || applies == "topics");
			$("#btnSave").toggle((applies == "posts" || applies == "topics") && $("#conds_notempty tr").length > 0);
			$("#fs_langs").toggle((applies == "posts" || applies == "topics") && (action == "prevent" || action == "moderate"));
		}
		else
			$("#fs_applies, #fs_conds, #fs_langs, #btnSave").hide();
	};
	updateForm();

	function setRuleContent()
	{
		if ($("#condtype").val() != "")
		{
			$("#rulecontainer").html($("#container_" + $("#condtype").val()).html());
			$("#rulecontainer .ruleSave").hide();

			$("#rulecontainer input[data-eve], #rulecontainer select[data-eve]").each(function () {
				var that = $(this);
				$.each(that.attr("data-eve").split(" "), function () {
					that.bind(eves[this][0], eves[this][1]);
				});
			});

			$("#rulecontainer div.sbox").remove();
			$("#rulecontainer select").sb();
		}
		else
			$("#rulecontainer").empty();
	};

	rows_added = ' . count($context['edit_rules']) . ';
	function addRow(rule, details, ruletype, rulevalue)
	{
		rows_added++;
		$("#conds_empty").hide();
		$("#conds_notempty").append("<tr id=\"cond_row_" + rows_added + "\" class=\"windowbg\"><td>" + rule + "</td><td>" + details + "<input type=\"hidden\" name=\"rule[]\"></td><td class=\"center\"><a href=\"#\" onclick=\"removeRow(" + rows_added + "); return false;\">" + ' . JavaScriptEscape($txt['remove']) . ' + "</a></td></tr>");
		$("#cond_row_" + rows_added + " input").val(ruletype + ";" + rulevalue);

		$("#condtype").val(0).sb();
		$("#rulecontainer").empty();
		$("#btnSave").show();
	};

	function removeRow(id)
	{
		$("#cond_row_" + id).remove();
		if ($("#conds_notempty tr").length == 0)
		{
			$("#conds_empty").show();
			$("#btnSave").hide();
		}

		return false;
	};');

	// Lastly before we go, make sure we dump all the containers for all the magic types.
	// It is not accidental that this is outside the core form.
	foreach ($context['modfilter_rule_types'] as $type)
	{
		$function = 'template_modfilter_' . $type;
		echo '
	<div id="container_', $type, '" class="hide">', $function(), '
	</div>';
	}
}

function template_modfilter_groups()
{
	global $context, $txt;
	echo '
		<br>
		<label><input type="radio" name="appliesgroup" onchange="validateGroups();" value="id"> ', $txt['modfilter_applies_all'], '</label><br>
		<label><input type="radio" name="appliesgroup" onchange="validateGroups();" value="except-id"> ', $txt['modfilter_applies_allexcept'], '</label><br>
		<br>
		<div class="two-columns">
			<we:title>', $txt['membergroups_regular'], '</we:title>';

	foreach ($context['grouplist']['assign'] as $id => $group)
		echo '
			<label><input type="checkbox" class="groups" onchange="validateGroups();" name="groups[', $id, ']" value="', $id, '"> ', $group, '</label><br>';

		echo '
		</div>
		<div class="two-columns">
			<we:title>', $txt['membergroups_post'], '</we:title>';

	foreach ($context['grouplist']['post'] as $id => $group)
		echo '
			<label><input type="checkbox" class="groups" onchange="validateGroups();" name="groups[', $id, ']" value="', $id, '"> ', $group, '</label><br>';

		echo '
		</div>
		<div class="pagesection ruleSave">
			<div class="floatright">
				<input class="new" type="submit" value="', $txt['modfilter_condition_done'], '" onclick="addGroups(e);">
			</div>
		</div>';

	add_js('
	function validateGroups()
	{
		var applies_type = $("#rulecontainer input:radio[name=appliesgroup]:checked").val();
		$("#rulecontainer .ruleSave").toggle((applies_type == "id" || applies_type == "except-id") && $("#rulecontainer input.groups:checked").length != 0);
	};

	function addGroups(e)
	{
		e.preventDefault();
		var
			inGroups = ' . JavaScriptEscape($txt['modfilter_cond_groups_in']) . ',
			exGroups = ' . JavaScriptEscape($txt['modfilter_cond_groups_ex']) . ',
			groupStr = [],
			groupVal = [];

		var applies_type = $("#rulecontainer input:radio[name=appliesgroup]:checked").val();
		var groups = $("#rulecontainer input.groups:checked");
		if ((applies_type == "id" || applies_type == "except-id") && groups.length != 0)
		{
			$(groups).each(function()
			{
				groupVal.push($(this).val());
				var item = $(this).parent().children("span"), itemHtml = item.html();
				if (item.attr("style"))
					itemHtml = "<span style=\"" + item.attr("style") + "\">" + itemHtml + "</span>";
				groupStr.push(itemHtml);
			});
			addRow(applies_type == "id" ? inGroups : exGroups, groupStr.join(", "), "groups", applies_type + ";" + groupVal.join(","));
		}
	}');
}

function template_modfilter_boards()
{
	global $context, $txt;
	echo '
		<br>
		<label><input type="radio" name="appliesboard" onchange="validateBoards();" value="id"> ', $txt['modfilter_applies_all'], '</label><br>
		<label><input type="radio" name="appliesboard" onchange="validateBoards();" value="except-id"> ', $txt['modfilter_applies_allexcept'], '</label><br>';

	foreach ($context['boardlist'] as $id_cat => $cat)
	{
		echo '
		<br>', $cat['name'], '<br>';
		foreach ($cat['boards'] as $id_board => $board)
		{
			echo '
			&nbsp; ', !empty($board['child_level']) ? str_repeat('&nbsp; ', $board['child_level']) : '', '<label><input type="checkbox" class="boards" onchange="validateBoards();" name="boards[', $id_board, ']" value="', $id_board, '"> <span>', $board['board_name'], '</span></label><br>';
		}
	}

	echo '
		<div class="pagesection ruleSave">
			<div class="floatright">
				<input class="new" type="submit" value="', $txt['modfilter_condition_done'], '" onclick="addBoards(e);">
			</div>
		</div>';

	add_js('
	function validateBoards()
	{
		var applies_type = $("#rulecontainer input:radio[name=appliesboard]:checked").val();
		$("#rulecontainer .ruleSave").toggle((applies_type == "id" || applies_type == "except-id") && $("#rulecontainer input.boards:checked").length != 0);
	};

	function addBoards(e)
	{
		e.preventDefault();
		var
			inBoards = ' . JavaScriptEscape($txt['modfilter_cond_boards_in']) . ',
			exBoards = ' . JavaScriptEscape($txt['modfilter_cond_boards_ex']) . ',
			boardStr = [],
			boardVal = [],
			applies_type = $("#rulecontainer input:radio[name=appliesboard]:checked").val(),
			boards = $("#rulecontainer input.boards:checked");

		if ((applies_type == "id" || applies_type == "except-id") && boards.length != 0)
		{
			$(boards).each(function() {
				boardVal.push($(this).val());
				var item = $(this).parent().children("span");
				var itemHtml = item.html();
				if (item.attr("style"))
					itemHtml = "<span style=\"" + item.attr("style") + "\">" + itemHtml + "</span>";
				boardStr.push(itemHtml);
			});
			addRow(applies_type == "id" ? inBoards : exBoards, boardStr.join(", "), "boards", applies_type + ";" + boardVal.join(","));
		}
	};');
}

function template_modfilter_postcount()
{
	template_range_modfilter('postcount');
}

function template_modfilter_links()
{
	template_range_modfilter('links');
}

function template_modfilter_warning()
{
	global $context, $txt;

	$js_conds = array();
	echo '
		<br>', $txt['modfilter_warning_is'], '
		<select name="rangesel" onchange="validateWarning();">';

	foreach (array('lt', 'lte', 'eq', 'gte', 'gt') as $item)
	{
		// Step through the possible ranges - but also store the JS versions away for later.
		echo '
			<option value="', $item, '">', $txt['modfilter_range_' . $item], '</option>';
		$js_conds[] = $item . ': ' . JavaScriptEscape($txt['modfilter_range_' . $item]);
	}

	echo '
		</select>
		<input type="text" size="5" name="warning" style="padding: 3px 5px 5px 5px" onchange="validateWarning();"> %
		<div class="pagesection ruleSave">
			<div class="floatright">
				<input class="new" type="submit" value="', $txt['modfilter_condition_done'], '" onclick="addWarning(e);">
			</div>
		</div>';

	add_js('
	function validateWarning()
	{
		var
			applies_type = $("#rulecontainer select[name=rangesel]").val(),
			warning = $("#rulecontainer input[name=warning]").val(),
			wn_num = parseInt(warning);

		$("#rulecontainer .ruleSave").toggle(in_array(applies_type, ["lt", "lte", "eq", "gte", "gt"]) && warning == wn_num && wn_num >= 0 && wn_num <= 100);
	};

	function addWarning(e)
	{
		e.preventDefault();
		var
			range = {' . implode(',', $js_conds) . '},
			wn = ' . JavaScriptEscape($txt['modfilter_cond_warning']) . ',
			applies_type = $("#rulecontainer select[name=rangesel]").val(),
			warning = $("#rulecontainer input[name=warning]").val(),
			wn_num = parseInt(warning);

		if (in_array(applies_type, ["lt", "lte", "eq", "gte", "gt"]) && warning == wn_num && wn_num >= 0 && wn_num <= 100)
			addRow(wn, range[applies_type] + " " + warning, "warning", applies_type + ";" + warning);
	};');
}

// Most of the others have something unique to them, e.g. warning is different to post count in one way, even though in all other respects they are the same
// These two, however, only differ in the language strings and a parameter, there are no extra or different tests to perform here.
function template_modfilter_body()
{
	template_regex_modfilter('body');
}

function template_modfilter_subject()
{
	template_regex_modfilter('subject');
}

function template_regex_modfilter($type)
{
	global $context, $txt;
	$utype = ucfirst($type);
	$js_conds = array();
	echo '
		<br>', $txt['modfilter_the_post_' . $type], '
		<select name="typesel" onchange="validate' . $utype . '();">';

	foreach (array('begins', 'ends', 'contains', 'matches', 'regex') as $item)
	{
		// Step through the possible ranges - but also store the JS versions away for later.
		echo '
			<option value="', $item, '">', $txt['modfilter_regex_' . $item], '</option>';
		$js_conds[] = $item . ': ' . JavaScriptEscape($txt['modfilter_cond_' . $type . '_' . $item]);
	}

	echo '
		</select>
		<input type="text" size="20" name="criteria" style="padding: 3px 5px 5px 5px" onchange="validate' . $utype . '();"><br>
		<label><input type="checkbox" name="casesens"> ', $txt['modfilter_be_case_sensitive'], '</label>
		<div class="pagesection ruleSave">
			<div class="floatright">
				<input class="new" type="submit" value="', $txt['modfilter_condition_done'], '" onclick="add' . $utype . '(e);">
			</div>
		</div>';

	add_js('
	function validate' . $utype . '()
	{
		var
			applies_type = $("#rulecontainer select[name=typesel]").val(),
			criteria = $("#rulecontainer input[name=criteria]").val();

		$("#rulecontainer .ruleSave").toggle(in_array(applies_type, ["begins", "contains", "ends", "matches", "regex"]) && criteria != "");
	};

	function add' . $utype . '(e)
	{
		e.preventDefault();
		var
			types = {' . implode(',', $js_conds) . '},
			strCaseSens = ' . JavaScriptEscape($txt['modfilter_case_sensitive']) . ',
			strCaseInsens = ' . JavaScriptEscape($txt['modfilter_case_insensitive']) . ',
			applies_type = $("#rulecontainer select[name=typesel]").val(),
			criteria = $("#rulecontainer input[name=criteria]").val(),
			casesens = $("#rulecontainer input[name=casesens]:checked").length != 0;
			criteria = criteria.php_htmlspecialchars();

		if (in_array(applies_type, ["begins", "contains", "ends", "matches", "regex"]) && criteria != "")
			addRow(types[applies_type], criteria + " " + (casesens ? strCaseSens : strCaseInsens), "' . $type . '", applies_type + ";" + (casesens ? "casesens=yes;" : "casesens=no;") + criteria);
	};');
}

// This is very generic for handling numeric range selections
// It requires the number be an integer and non zero. If you want anything more,
// you'll have to roll your own in your plugin, but that's no huge deal, it's not like most of this has to change.
function template_range_modfilter($type)
{
	global $context, $txt;
	$utype = ucfirst($type);

	$js_conds = array();
	echo '
		<br>', $txt['modfilter_' . $type . '_is'], '
		<select name="rangesel" onchange="validate', $utype, '();">';

	foreach (array('lt', 'lte', 'eq', 'gte', 'gt') as $item)
	{
		echo '
			<option value="', $item, '">', $txt['modfilter_range_' . $item], '</option>';
		$js_conds[] = $item . ': ' . JavaScriptEscape($txt['modfilter_range_' . $item]);
	}

	echo '
		</select>
		<input type="text" size="5" name="', $type, '" style="padding: 3px 5px 5px 5px" onchange="validate', $utype, '();">
		<div class="pagesection ruleSave">
			<div class="floatright">
				<input class="new" type="submit" value="', $txt['modfilter_condition_done'], '" onclick="add', $utype, '(e);">
			</div>
		</div>';

	add_js('
	function validate', $utype, '()
	{
		var
			applies_type = $("#rulecontainer select[name=rangesel]").val(),
			' . $type . ' = $("#rulecontainer input[name=' . $type . ']").val(),
			pc_num = parseInt(' . $type . ');

		$("#rulecontainer .ruleSave").toggle(in_array(applies_type, ["lt", "lte", "eq", "gte", "gt"]) && ' . $type . ' == pc_num && pc_num >= 0);
	};

	function add', $utype, '(e)
	{
		e.preventDefault();
		var
			range = {' . implode(',', $js_conds) . '},
			pc = ' . JavaScriptEscape($txt['modfilter_cond_' . $type]) . ',
			applies_type = $("#rulecontainer select[name=rangesel]").val(),
			' . $type . ' = $("#rulecontainer input[name=' . $type . ']").val(),
			pc_num = parseInt(' . $type . ');

		if (in_array(applies_type, ["lt", "lte", "eq", "gte", "gt"]) && ' . $type . ' == pc_num && pc_num >= 0)
			addRow(pc, range[applies_type] + " " + ' . $type . ', "' . $type . '", applies_type + ";" + ' . $type . ');
	};');
}

?>