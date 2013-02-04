<?php
/**
 * Wedge
 *
 * Interface for the maintenance tasks, to display the different options required to be presented.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Template for the database maintenance tasks.
function template_maintain_database()
{
	global $context, $theme, $options, $txt, $scripturl, $settings;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
	<div class="maintenance_finished">
		', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
	</div>';

	echo '
	<div id="manage_maintenance">
		<we:title>
			', $txt['maintain_optimize'], '
		</we:title>
		<div class="windowbg wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=optimize" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_optimize_info'], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '" class="submit"></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</div>';

	echo '
	</div>
	<br class="clear">';
}

// Template for the routine maintenance tasks.
function template_maintain_routine()
{
	global $context, $theme, $options, $txt, $scripturl, $settings;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
	<div class="maintenance_finished">
		', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
	</div>';

	// Starts off with general maintenance procedures.
	echo '
	<div id="manage_maintenance">';

	$use_bg2 = false;
	foreach ($context['maintenance_tasks'] as $id => $task)
	{
		echo '
		<we:title>
			', $task[0], '
		</we:title>
		<div class="windowbg', $use_bg2 ? '2' : '', ' wrc" id="', $id, '">
			<form action="', $scripturl, '?', $task[2], '" method="post" accept-charset="UTF-8">
				<p>', $task[1], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '"></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</div>';
		$use_bg2 = !$use_bg2;
	}

	echo '
	</div>
	<br class="clear">';
}

// Template for the member maintenance tasks.
function template_maintain_members()
{
	global $context, $theme, $options, $txt, $scripturl;

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
		var valid = true, origText = ', JavaScriptEscape($txt['reattribute_confirm']), ';

		if (!$("#to").val())
			valid = false;
		warningMessage = origText.replace(/%member_to%/, $("#to").val());

		if ($("#type_email")[0].checked)
		{
			if (!$("#from_email").val())
				valid = false;
			warningMessage = warningMessage.replace(/%type%/, ', JavaScriptEscape($txt['reattribute_confirm_email']), ').replace(/%find%/, $("#from_email").val());
		}
		else
		{
			if (!$("#from_name").val())
				valid = false;
			warningMessage = warningMessage.replace(/%type%/, ', JavaScriptEscape($txt['reattribute_confirm_username']), ').replace(/%find%/, $("#from_name").val());
		}

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
			<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=reattribute" method="post" accept-charset="UTF-8">
				<p><strong>', $txt['reattribute_guest_posts'], '</strong></p>
				<dl class="settings">
					<dt>
						<label><input type="radio" name="type" id="type_email" value="email" checked>', $txt['reattribute_email'], '</label>
					</dt>
					<dd>
						<input type="text" name="from_email" id="from_email" value="" onclick="$(\'#type_email\').prop(\'checked\', true); $(\'#from_name\').val(\'\');">
					</dd>
					<dt>
						<label><input type="radio" name="type" id="type_name" value="name">', $txt['reattribute_username'], '</label>
					</dt>
					<dd>
						<input type="text" name="from_name" id="from_name" value="" onclick="$(\'#type_name\').prop(\'checked\', true); $(\'#from_email\').val(\'\');">
					</dd>
				</dl>
				<dl class="settings">
					<dt>
						<label for="to"><strong>', $txt['reattribute_current_member'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" name="to" id="to" value="">
					</dd>
				</dl>
				<p class="maintain_members">
					<label><input type="checkbox" name="posts" id="posts" checked> ', $txt['reattribute_increase_posts'], '</label>
				</p>
				<span><input type="submit" id="do_attribute" value="', $txt['reattribute'], '" onclick="return checkAttributeValidity() && ask(warningMessage, e);" class="submit"></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</div>
		<we:title>
			<a href="', $scripturl, '?action=help;in=maintenance_members" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
			', $txt['maintain_members'], '
		</we:title>
		<div class="windowbg wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=purgeinactive" method="post" accept-charset="UTF-8" id="membersForm">
				<p><a id="membersLink"></a>', $txt['maintain_members_since1'], '
				<select name="del_type">
					<option value="activated" selected>', $txt['maintain_members_activated'], '</option>
					<option value="logged">', $txt['maintain_members_logged_in'], '</option>
				</select> ', $txt['maintain_members_since2'], ' <input type="text" name="maxdays" value="30" size="3">', $txt['maintain_members_since3'], '</p>';

	echo '
				<p><a href="#membersLink" onclick="swapMembers(); return false;"><div class="foldable" title="+" id="membersIcon"></div></a> <a href="#membersLink" onclick="swapMembers(); return false;" id="membersText" style="font-weight: bold">', $txt['maintain_members_all'], '</a></p>
				<div style="display: none; padding: 3px" id="membersPanel">';

	foreach ($context['membergroups'] as $group)
		echo '
					<label><input type="checkbox" name="groups[', $group['id'], ']" id="groups', $group['id'], '" checked> ', $group['name'], '</label><br>';

	echo '
				</div>
				<span><input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return ask(', JavaScriptEscape($txt['maintain_members_confirm']), ', e);" class="delete"></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</div>
		<we:title>
			', $txt['maintain_recountposts'], '
		</we:title>
		<div class="windowbg2 wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=recountposts" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_recountposts_desc'], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '"></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
		</div>
	</div>
	<br class="clear">';

	add_js_file('scripts/suggest.js');
	add_js('
	new weAutoSuggest({
		sControlId: \'to\'
	});');
}

// Template for the topic maintenance tasks.
function template_maintain_topics()
{
	global $scripturl, $txt, $context, $theme, $settings;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
	<div class="maintenance_finished">
		', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
	</div>';

	// Bit of javascript for showing which boards to prune in an otherwise hidden list.
	add_js('
	var rotSwap = false;
	function swapRot()
	{
		rotSwap = !rotSwap;

		$("#rotIcon").toggleClass("fold", rotSwap);
		$("#rotText").html(rotSwap ? ', JavaScriptEscape($txt['maintain_old_choose']), ' : ', JavaScriptEscape($txt['maintain_old_all']), ');
		$("#rotPanel").slideToggle(rotSwap);
		$("#rotPanel input[type=checkbox]").prop("checked", !rotSwap);
	}');

	echo '
	<div id="manage_maintenance">
		<we:title>
			', $txt['maintain_old'], '
		</we:title>
		<div class="windowbg wrc">
			<div class="flow_auto">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=pruneold" method="post" accept-charset="UTF-8">';

	// The otherwise hidden "choose which boards to prune".
	echo '
					<p>
						<a id="rotLink"></a>', $txt['maintain_old_since_days1'], '<input type="number" name="maxdays" value="30" min="0" max="9999" size="3">', $txt['maintain_old_since_days2'], '
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
								<legend>', $category['name'], '</legend>
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
					<input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return ask(', JavaScriptEscape($txt['maintain_old_confirm']), ', e);" class="delete">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</form>
			</div>
		</div>
		<we:title>
			', $txt['move_topics_maintenance'], '
		</we:title>
		<div class="windowbg2 wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=massmove" method="post" accept-charset="UTF-8">
				<p><label>', $txt['move_topics_from'], '
				<select name="id_board_from" id="id_board_from">
					<option data-hide>(', $txt['move_topics_select_board'], ')</option>';

	// From board
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
				</select></label>
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
				<input type="submit" value="', $txt['move_topics_now'], '" onclick="return moveTopicsNow(e);" class="submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</form>
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
	global $context, $theme, $options, $txt, $scripturl;

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
			<p><a href="', $scripturl, '?action=admin;area=maintain">', $txt['maintain_return'], '</a></p>
		</div>
	</div>
	<br class="clear">';
}

function template_convert_utf8()
{
	global $context, $txt, $theme, $scripturl;

	echo '
	<div id="manage_maintenance">
		<we:title>
			', $txt['utf8_title'], '
		</we:title>
		<div class="windowbg wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=convertutf8" method="post" accept-charset="UTF-8">
				<p>', $txt['utf8_introduction'], '</p>
				<div>', $txt['utf8_warning'], '</div>

				<dl class="settings">
					<dt><strong>', $txt['utf8_source_charset'], ':</strong></dt>
					<dd>
						<select name="src_charset">';

	foreach ($context['charset_list'] as $charset)
		echo '
							<option value="', $charset, '"', $charset === $context['charset_detected'] ? ' selected' : '', '>', $charset, '</option>';

	echo '
						</select>
					</dd>
					<dt><strong>', $txt['utf8_database_charset'], ':</strong></dt>
					<dd>', $context['database_charset'], '</dd>
					<dt><strong>', $txt['utf8_target_charset'], ': </strong></dt>
					<dd>', $txt['utf8_utf8'], '</dd>
				</dl>
				<input type="submit" value="', $txt['utf8_proceed'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="proceed" value="1">
			</form>
		</div>
	</div>
	<br class="clear">';
}

function template_convert_entities()
{
	global $context, $txt, $theme, $scripturl;

	echo '
	<div id="manage_maintenance">
		<we:title>
			', $txt['entity_convert_title'], '
		</we:title>
		<div class="windowbg wrc">
			<p>', $txt['entity_convert_introduction'], '</p>
			<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=convertentities;start=0;', $context['session_query'], '" method="post" accept-charset="UTF-8">
				<input type="submit" value="', $txt['entity_convert_proceed'], '">
			</form>
		</div>
	</div>
	<br class="clear">';
}
