<?php
/**
 * Interface for the maintenance tasks, to display the different options required to be presented.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

// Template for the routine maintenance tasks.
function template_maintain_routine()
{
	global $context, $txt;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
	<div class="maintenance_finished">
		', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
	</div>';

	// Start off with database optimization, then do general maintenance procedures.
	echo '
	<div id="manage_maintenance" class="splitter">
		<section>
			<we:title style="margin: 0 3px 5px">
				', $txt['maintain_optimize'], '
			</we:title>
			<div class="windowbg2 wrc" id="optimize_tables">
				<form action="<URL>?action=admin;area=maintain;sa=database;activity=optimize" method="post" accept-charset="UTF-8">
					<span><input type="submit" value="', $txt['maintain_run_now'], '" class="submit floatright" style="margin: 5px 0 0 1em"></span>
					<p>', $txt['maintain_optimize_info'], '</p>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" class="clear">
				</form>
			</div>
		</section>';

	$use_bg2 = false;
	foreach ($context['maintenance_tasks'] as $id => $task)
	{
		echo '
		<section>
			<we:title style="margin: 0 3px 5px">
				', $task[0], '
			</we:title>
			<div class="windowbg', $use_bg2 ? '2' : '', ' wrc" id="', $id, '">
				<form action="<URL>?', $task[2], '" method="post" accept-charset="UTF-8">
					<span><input type="submit" value="', $txt['maintain_run_now'], '" class="submit floatright" style="margin: 5px 0 0 1em"></span>
					<p>', $task[1], '</p>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" class="clear">
				</form>
			</div>
		</section>';
		$use_bg2 = !$use_bg2;
	}

	echo '
	</div>
	<br class="clear">';
}

// Template for the member maintenance tasks.
function template_maintain_members()
{
	global $context, $txt;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
	<div class="maintenance_finished">
		', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
	</div>';

	add_js('
	var membersSwap = false;

	function swapMembers()
	{
		membersSwap = !membersSwap;

		$("#membersIcon").toggleClass("fold", membersSwap);
		$("#membersText").html(membersSwap ? ', JavaScriptEscape($txt['maintain_members_choose']), ' : ', JavaScriptEscape($txt['maintain_members_all']), ');
		$("#membersPanel").slideToggle(membersSwap);
		$("#membersForm input[type=checkbox]").prop("checked", !membersSwap);
	}

	var warningMessage = \'\';

	function checkAttributeValidity()
	{
		var
			valid = true,
			origText = ', JavaScriptEscape($txt['reattribute_confirm']), ',
			mergeText = ', JavaScriptEscape($txt['reattribute_confirm_merge']), ';

		if (!$("#to").val())
			valid = false;

		if ($("#type_from")[0].checked)
		{
			if (!$("#from_id").val())
				valid = false;
			warningMessage = mergeText.replace(/%find%/, $("#from_id").val());
		}
		else if ($("#type_email")[0].checked)
		{
			if (!$("#from_email").val())
				valid = false;
			warningMessage = origText.replace(/%type%/, ', JavaScriptEscape($txt['reattribute_confirm_email']), ').replace(/%find%/, $("#from_email").val());
		}
		else
		{
			if (!$("#from_name").val())
				valid = false;
			warningMessage = origText.replace(/%type%/, ', JavaScriptEscape($txt['reattribute_confirm_username']), ').replace(/%find%/, $("#from_name").val());
		}

		warningMessage = warningMessage.replace(/%member_to%/, $("#to").val());

		$("#do_attribute").prop("disabled", !valid);

		setTimeout(checkAttributeValidity, 500);
		return valid;
	}
	setTimeout(checkAttributeValidity, 500);');

	echo '
	<div id="manage_maintenance">
		<we:title>
			', $txt['maintain_reattribute_posts'], '
		</we:title>
		<div class="windowbg2 wrc">
			<form action="<URL>?action=admin;area=maintain;sa=members;activity=reattribute" method="post" accept-charset="UTF-8">
				<p><strong>', $txt['reattribute_guest_posts'], '</strong></p>
				<dl class="settings">
					<dt>
						<label><input type="radio" name="type" id="type_email" value="email" checked>', $txt['reattribute_email'], '</label>
					</dt>
					<dd>
						<input type="email" name="from_email" id="from_email" value="" onclick="$(\'#type_email\').prop(\'checked\', true); $(\'#from_name, #from_id\').val(\'\');">
					</dd>
					<dt>
						<label><input type="radio" name="type" id="type_name" value="name">', $txt['reattribute_username'], '</label>
					</dt>
					<dd>
						<input name="from_name" id="from_name" value="" onclick="$(\'#type_name\').prop(\'checked\', true); $(\'#from_email, #from_id\').val(\'\');">
					</dd>
					<hr style="clear: both">
					<dt>
						<label><input type="radio" name="type" id="type_from" value="from">', $txt['reattribute_user'], '</label>
					</dt>
					<dd>
						<input name="from_id" id="from_id" value="" onclick="$(\'#type_from\').prop(\'checked\', true); $(\'#from_email, #from_name\').val(\'\');">
					</dd>
				</dl>
				<hr>
				<dl class="settings">
					<dt>
						<label for="to"><strong>', $txt['reattribute_current_member'], ':</strong></label>
					</dt>
					<dd>
						<input name="to" id="to" value="">
					</dd>
				</dl>
				<hr>
				<p class="maintain_members">
					<label><input type="checkbox" name="posts" id="posts" checked> ', $txt['reattribute_increase_posts'], '</label>
				</p>
				<span><input type="submit" id="do_attribute" value="', $txt['reattribute'], '" onclick="return checkAttributeValidity() && ask(warningMessage, e);" class="submit floatright"></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<br class="clear">
			</form>
		</div>
		<we:title>
			<a href="<URL>?action=help;in=maintenance_members" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
			', $txt['maintain_members'], '
		</we:title>
		<div class="windowbg wrc">
			<form action="<URL>?action=admin;area=maintain;sa=members;activity=purgeinactive" method="post" accept-charset="UTF-8" id="membersForm">
				<p><a id="membersLink"></a>', $txt['maintain_members_since1'], '
				<select name="del_type">
					<option value="activated" selected>', $txt['maintain_members_activated'], '</option>
					<option value="logged">', $txt['maintain_members_logged_in'], '</option>
				</select> ', $txt['maintain_members_since2'], ' <input name="maxdays" value="30" size="3">', $txt['maintain_members_since3'], '</p>';

	echo '
				<p><a href="#membersLink" onclick="swapMembers(); return false;"><div class="foldable" title="+" id="membersIcon"></div></a> <a href="#membersLink" onclick="swapMembers(); return false;" id="membersText" style="font-weight: bold">', $txt['maintain_members_all'], '</a></p>
				<div style="display: none; padding: 3px" id="membersPanel">';

	foreach ($context['membergroups'] as $group)
		echo '
					<label><input type="checkbox" name="groups[', $group['id'], ']" id="groups', $group['id'], '" checked> ', $group['name'], '</label><br>';

	echo '
				</div>
				<span><input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return ask(', JavaScriptEscape($txt['maintain_members_confirm']), ', e);" class="delete floatright"></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<br class="clear">
			</form>
		</div>
		<we:title>
			', $txt['maintain_recountposts'], '
		</we:title>
		<div class="windowbg2 wrc">
			<form action="<URL>?action=admin;area=maintain;sa=members;activity=recountposts" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_recountposts_desc'], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '" class="submit floatright"></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<br class="clear">
			</form>
		</div>
	</div>
	<br class="clear">';

	add_js_file('suggest.js');
	add_js('
	new weAutoSuggest({
		', min_chars(), ',
		sControlId: \'to\'
	});
	new weAutoSuggest({
		', min_chars(), ',
		sControlId: \'from_id\'
	});');
}

// Template for the topic maintenance tasks.
function template_maintain_topics()
{
	global $txt, $context;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
	<div class="maintenance_finished">
		', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
	</div>';

	// Bit of javascript for showing which boards to prune in an otherwise hidden list.
	add_js('
	var rotSwap = false, motSwap = false;
	function swapRot()
	{
		rotSwap = !rotSwap;

		$("#rotIcon").toggleClass("fold", rotSwap);
		$("#rotText").html(rotSwap ? ', JavaScriptEscape($txt['maintain_old_choose']), ' : ', JavaScriptEscape($txt['maintain_old_all']), ');
		$("#rotPanel").slideToggle(rotSwap);
		$("#rotPanel input[type=checkbox]").prop("checked", !rotSwap);
	}
	function swapMot()
	{
		motSwap = !motSwap;

		$("#motIcon").toggleClass("fold", motSwap);
		$("#motText").html(motSwap ? ', JavaScriptEscape($txt['maintain_old_choose']), ' : ', JavaScriptEscape($txt['maintain_old_all']), ');
		$("#motPanel").slideToggle(motSwap);
		$("#motPanel li input[type=checkbox]").prop("checked", !motSwap);
	}

	function selectCat(obj)
	{
		$(obj).closest("fieldset").find("input").prop("checked", obj.checked);
	}');

	echo '
	<div id="manage_maintenance">
		<we:title>
			', $txt['maintain_old'], '
		</we:title>
		<div class="windowbg wrc">
			<div class="flow_auto">
				<form action="<URL>?action=admin;area=maintain;sa=topics;activity=pruneold" method="post" accept-charset="UTF-8">';

	// The otherwise hidden "choose which boards to prune".
	echo '
					<p>
						<a id="rotLink"></a>', sprintf($txt['maintain_old_since'], '<input type="number" name="maxdays" value="30" min="0" max="9999" size="3">'), '
					</p>
					<p>
						<label><input type="radio" name="delete_type" id="delete_type_nothing" value="nothing"> ', $txt['maintain_old_nothing_else'], '</label><br>
						<label><input type="radio" name="delete_type" id="delete_type_moved" value="moved" checked> ', $txt['maintain_old_are_moved'], '</label><br>
						<label><input type="radio" name="delete_type" id="delete_type_locked" value="locked"> ', $txt['maintain_old_are_locked'], '</label><br>
					</p>
					<p>
						<label><input type="checkbox" name="delete_old_not_pinned" id="delete_old_not_pinned" checked> ', $txt['maintain_old_are_not_pinned'], '</label><br>
					</p>
					<p>
						<a href="#rotLink" onclick="swapRot(); return false;"><div class="foldable" title="+" id="rotIcon"></div></a> <a href="#rotLink" onclick="swapRot(); return false;" id="rotText" style="font-weight: bold">', $txt['maintain_old_all'], '</a>
					</p>
					<div class="flow_hidden hide" id="rotPanel">
						<div class="floatleft" style="width: 49%">';

	// This is the "middle" of the list.
	$middle = ceil(count($context['categories']) / 2);

	$i = 0;
	foreach ($context['categories'] as $category)
	{
		echo '
							<fieldset>
								<legend><label><input type="checkbox" onclick="selectCat(this);"> ', $category['name'], '</label></legend>
								<ul class="reset">';

		// Display a checkbox with every board.
		foreach ($category['boards'] as $board)
			echo '
									<li style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'] * 1.5, 'em">
										<label><input type="checkbox" name="boards[', $board['id'], ']" id="boards_', $board['id'], '" checked>', $board['name'], '</label>
									</li>';

		echo '
								</ul>
							</fieldset>';

		// Increase $i, and check if we're at the middle yet.
		if (++$i == $middle)
			echo '
						</div>
						<div class="floatright" style="width: 49%">';
	}

	echo '
						</div>
					</div>
					<input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return ask(', JavaScriptEscape($txt['maintain_old_confirm']), ', e);" class="delete floatright">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</form>
			</div>
		</div>
		<we:title>
			', $txt['move_topics_maintenance'], '
		</we:title>
		<div class="windowbg2 wrc">
			<div class="flow_auto">
				<form action="<URL>?action=admin;area=maintain;sa=topics;activity=massmove" method="post" accept-charset="UTF-8">
					<p>
						<a id="rotLink"></a>', sprintf($txt['maintain_old_move_since'], '<input type="number" name="maxdays" value="30" min="0" max="9999" size="3">'), '
					</p>
					<p>
						<label><input type="radio" name="move_type" id="move_type_nothing" value="nothing"> ', $txt['maintain_old_nothing_else'], '</label><br>
						<label><input type="radio" name="move_type" id="move_type_moved" value="moved" checked> ', $txt['maintain_old_are_moved'], '</label><br>
						<label><input type="radio" name="move_type" id="move_type_locked" value="locked"> ', $txt['maintain_old_are_locked'], '</label><br>
					</p>
					<p>
						<label><input type="checkbox" name="move_old_not_pinned" id="delete_old_not_pinned" checked> ', $txt['maintain_old_are_not_pinned'], '</label><br>
					</p>
					<p>
						', $txt['move_topics_from'], ':
						<a href="#motLink" onclick="swapMot(); return false;"><span class="foldable" title="+" id="motIcon"></span></a>
						<a href="#motLink" onclick="swapMot(); return false;" id="motText" style="font-weight: bold">', $txt['maintain_old_all'], '</a>
					</p>
					<hr>
					<div class="flow_hidden hide" id="motPanel">
						<div class="floatleft" style="width: 49%">';

	// This is the "middle" of the list.
	$middle = ceil(count($context['categories']) / 2);

	$i = 0;
	foreach ($context['categories'] as $category)
	{
		echo '
							<fieldset>
								<legend><label><input type="checkbox" onclick="selectCat(this);"> ', $category['name'], '</label></legend>
								<ul class="reset">';

		// Display a checkbox with every board.
		foreach ($category['boards'] as $board)
			echo '
									<li style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'] * 1.5, 'em">
										<label><input type="checkbox" name="boards[', $board['id'], ']" id="boards_', $board['id'], '" checked>', $board['name'], '</label>
									</li>';

		echo '
								</ul>
							</fieldset>';

		// Increase $i, and check if we're at the middle yet.
		if (++$i == $middle)
			echo '
						</div>
						<div class="floatright" style="width: 49%">';
	}

	echo '
						</div>
					</div>
					<p>
					<input type="submit" value="', $txt['move_topics_now'], '" onclick="return moveTopicsNow(e);" class="submit floatright">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<label for="id_board_to">', $txt['move_topics_to'], '</label>
					<select name="id_board_to" id="id_board_to">
						<option data-hide>(', $txt['move_topics_select_board'], ')</option>';

	// To board
	foreach ($context['categories'] as $category)
	{
		echo '
						<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
			echo '
							<option value="', $board['id'], '"> ', str_repeat('==', $board['child_level']), '=&gt;&nbsp;', $board['name'], '</option>';
		echo '
						</optgroup>';
	}
	echo '
					</select></p>
				</form>
			</div>
		</div>
	</div>
	<br class="clear">';

	add_js('
	function moveTopicsNow(e)
	{
		if ($("#id_board_from option:selected").is(":disabled") || $("#id_board_to option:selected").is(":disabled"))
			return false;
		var confirmText = ', JavaScriptEscape($txt['move_topics_confirm']), ';
		return ask(confirmText.replace(/%board_from%/, $("#id_board_from").val().replace(/^=+&gt;&nbsp;/, \'\'))
			.replace(/%board_to%/, $("#id_board_to").val().replace(/^=+&gt;&nbsp;/, \'\')), e);
	}');
}

// Simple template for showing results of our optimization...
function template_optimize()
{
	global $context, $txt;

	echo '
	<div id="manage_maintenance">
		<we:title>
			', $txt['maintain_optimize'], '
		</we:title>
		<div class="windowbg wrc">
			<p>
				', $txt['database_numb_tables'], '<br>
				', $txt['database_optimize_attempt'], '<br>';

	// List each table being optimized...
	foreach ($context['optimized_tables'] as $table)
		echo '
				', sprintf($txt['database_optimizing'], $table['name'], $table['data_freed']), '<br>';

	// How did we go?
	echo '
				<br>', number_context('database_optimized', $context['num_tables_optimized']), '
			</p>
			<p><a href="<URL>?action=admin;area=maintain">', $txt['maintain_return'], '</a></p>
		</div>
	</div>
	<br class="clear">';
}
