<?php
// Version: 2.0 RC4; ManageMaintenance

// Template for the database maintenance tasks.
function template_maintain_database()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
			<div class="maintenance_finished">
				', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
			</div>';

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3>', $txt['maintain_optimize'], '</h3>
		</div>
		<div class="windowbg wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=optimize" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_optimize_info'], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>
		</div>

		<div class="cat_bar">
			<h3>
				<a href="', $scripturl, '?action=helpadmin;help=maintenance_backup" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" /></a> ', $txt['maintain_backup'], '
			</h3>
		</div>

		<div class="windowbg2 wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=backup" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_backup_info'], '</p>
				<p><label for="struct"><input type="checkbox" name="struct" id="struct" onclick="document.getElementById(\'submitDump\').disabled = !document.getElementById(\'struct\').checked && !document.getElementById(\'data\').checked;" checked> ', $txt['maintain_backup_struct'], '</label><br />
				<label for="data"><input type="checkbox" name="data" id="data" onclick="document.getElementById(\'submitDump\').disabled = !document.getElementById(\'struct\').checked && !document.getElementById(\'data\').checked;" checked> ', $txt['maintain_backup_data'], '</label><br />
				<label for="compress"><input type="checkbox" name="compress" id="compress" value="gzip" checked> ', $txt['maintain_backup_gz'], '</label></p>
				<p><input type="submit" value="', $txt['maintain_backup_save'], '" id="submitDump" onclick="return document.getElementById(\'struct\').checked || document.getElementById(\'data\').checked;" class="button_submit" /></p>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>
		</div>';

	echo '
	</div>
	<br class="clear" />';
}

// Template for the routine maintenance tasks.
function template_maintain_routine()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// If maintenance has finished tell the user.
	if (!empty($context['maintenance_finished']))
		echo '
			<div class="maintenance_finished">
				', sprintf($txt['maintain_done'], $context['maintenance_finished']), '
			</div>';

	// Starts off with general maintenance procedures.
	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3>', $txt['maintain_version'], '</h3>
		</div>
		<div class="windowbg wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=version" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_version_info'], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>
		</div>
		<div class="cat_bar">
			<h3>', $txt['maintain_errors'], '</h3>
		</div>
		<div class="windowbg2 wrc">
			<form action="', $scripturl, '?action=admin;area=repairboards" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_errors_info'], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>
		</div>
		<div class="cat_bar">
			<h3>', $txt['maintain_recount'], '</h3>
		</div>
		<div class="windowbg wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=recount" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_recount_info'], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>
		</div>
		<div class="cat_bar">
			<h3>', $txt['maintain_logs'], '</h3>
		</div>
		<div class="windowbg2 wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=logs" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_logs_info'], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>
		</div>
		<div class="cat_bar">
			<h3>', $txt['maintain_cache'], '</h3>
		</div>
		<div class="windowbg wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=cleancache" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_cache_info'], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit" /></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>
		</div>
	</div>
	<br class="clear" />';
}

// Template for the member maintenance tasks.
function template_maintain_members()
{
	global $context, $settings, $options, $txt, $scripturl;

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

		$("#membersIcon").attr("src", smf_images_url + (membersSwap ? "/collapse.gif" : "/expand.gif"));
		$("#membersText").html(membersSwap ? ', JavaScriptEscape($txt['maintain_members_choose']), ' : ', JavaScriptEscape($txt['maintain_members_all']), ');
		$("#membersPanel").slideToggle(membersSwap);
		$("#membersForm input[type=checkbox]").attr("checked", !membersSwap);
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

		$("#do_attribute").attr("disabled", !valid);

		setTimeout("checkAttributeValidity();", 500);
		return valid;
	}
	setTimeout("checkAttributeValidity();", 500);');

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3>', $txt['maintain_reattribute_posts'], '</h3>
		</div>
		<div class="windowbg2 wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=reattribute" method="post" accept-charset="UTF-8">
				<p><strong>', $txt['reattribute_guest_posts'], '</strong></p>
				<dl class="settings">
					<dt>
						<label for="type_email"><input type="radio" name="type" id="type_email" value="email" checked>', $txt['reattribute_email'], '</label>
					</dt>
					<dd>
						<input type="text" name="from_email" id="from_email" value="" onclick="$(\'#type_email\').attr(\'checked\', true); $(\'#from_name\').val(\'\');" />
					</dd>
					<dt>
						<label for="type_name"><input type="radio" name="type" id="type_name" value="name">', $txt['reattribute_username'], '</label>
					</dt>
					<dd>
						<input type="text" name="from_name" id="from_name" value="" onclick="$(\'#type_name\').attr(\'checked\', true); $(\'#from_email\').val(\'\');">
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
					<input type="checkbox" name="posts" id="posts" checked>
					<label for="posts">', $txt['reattribute_increase_posts'], '</label>
				</p>
				<span><input type="submit" id="do_attribute" value="', $txt['reattribute'], '" onclick="return !checkAttributeValidity() ? false : confirm(warningMessage);" class="button_submit" /></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>
		</div>
		<div class="cat_bar">
			<h3>
				<a href="', $scripturl, '?action=helpadmin;help=maintenance_members" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" /></a> ', $txt['maintain_members'], '
			</h3>
		</div>
		<div class="windowbg wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=purgeinactive" method="post" accept-charset="UTF-8" id="membersForm">
				<p><a id="membersLink"></a>', $txt['maintain_members_since1'], '
				<select name="del_type">
					<option value="activated" selected>', $txt['maintain_members_activated'], '</option>
					<option value="logged">', $txt['maintain_members_logged_in'], '</option>
				</select> ', $txt['maintain_members_since2'], ' <input type="text" name="maxdays" value="30" size="3">', $txt['maintain_members_since3'], '</p>';

	echo '
				<p><a href="#membersLink" onclick="swapMembers(); return false;"><img src="', $settings['images_url'], '/expand.gif" alt="+" id="membersIcon" /></a> <a href="#membersLink" onclick="swapMembers(); return false;" id="membersText" style="font-weight: bold;">', $txt['maintain_members_all'], '</a></p>
				<div style="display: none; padding: 3px" id="membersPanel">';

	foreach ($context['membergroups'] as $group)
		echo '
					<label for="groups', $group['id'], '"><input type="checkbox" name="groups[', $group['id'], ']" id="groups', $group['id'], '" checked> ', $group['name'], '</label><br />';

	echo '
				</div>
				<span><input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return confirm(', JavaScriptEscape($txt['maintain_members_confirm']), ');" class="button_submit" /></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>
		</div>
		<div class="cat_bar">
			<h3>', $txt['maintain_recountposts'], '</h3>
		</div>
		<div class="windowbg2 wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=members;activity=recountposts" method="post" accept-charset="UTF-8">
				<p>', $txt['maintain_recountposts_desc'], '</p>
				<span><input type="submit" value="', $txt['maintain_run_now'], '" class="button_submit"></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>
		</div>
	</div>
	<br class="clear" />';

	add_js_file('scripts/suggest.js');

	add_js('
	var oAttributeMemberSuggest = new smc_AutoSuggest({
		sSelf: \'oAttributeMemberSuggest\',
		sSessionId: \'', $context['session_id'], '\',
		sSessionVar: \'', $context['session_var'], '\',
		sControlId: \'to\',
		sTextDeleteItem: ', JavaScriptEscape($txt['autosuggest_delete_item']), '
	});');
}

// Template for the topic maintenance tasks.
function template_maintain_topics()
{
	global $scripturl, $txt, $context, $settings, $modSettings;

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

		$("#rotIcon").attr("src", smf_images_url + (rotSwap ? "/collapse.gif" : "/expand.gif"));
		$("#rotText").html(rotSwap ? ', JavaScriptEscape($txt['maintain_old_choose']), ' : ', JavaScriptEscape($txt['maintain_old_all']), ');
		$("#rotPanel").toggle(rotSwap);
		$("#rotPanel input").each(function () {
			if (this.type.toLowerCase() == "checkbox")
				this.checked = !rotSwap;
		});
	}');

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3>', $txt['maintain_old'], '</h3>
		</div>
		<div class="windowbg wrc">
			<div class="flow_auto">
				<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=pruneold" method="post" accept-charset="UTF-8">';

	// The otherwise hidden "choose which boards to prune".
	echo '
					<p>
						<a id="rotLink"></a>', $txt['maintain_old_since_days1'], '<input type="text" name="maxdays" value="30" size="3" />', $txt['maintain_old_since_days2'], '
					</p>
					<p>
						<label for="delete_type_nothing"><input type="radio" name="delete_type" id="delete_type_nothing" value="nothing" checked> ', $txt['maintain_old_nothing_else'], '</label><br />
						<label for="delete_type_moved"><input type="radio" name="delete_type" id="delete_type_moved" value="moved"> ', $txt['maintain_old_are_moved'], '</label><br />
						<label for="delete_type_locked"><input type="radio" name="delete_type" id="delete_type_locked" value="locked"> ', $txt['maintain_old_are_locked'], '</label><br />
					</p>';

	if (!empty($modSettings['enableStickyTopics']))
		echo '
					<p>
						<label for="delete_old_not_sticky"><input type="checkbox" name="delete_old_not_sticky" id="delete_old_not_sticky" checked> ', $txt['maintain_old_are_not_stickied'], '</label><br />
					</p>';

		echo '
					<p>
						<a href="#rotLink" onclick="swapRot();"><img src="', $settings['images_url'], '/expand.gif" alt="+" id="rotIcon" /></a> <a href="#rotLink" onclick="swapRot();" id="rotText" style="font-weight: bold;">', $txt['maintain_old_all'], '</a>
					</p>
					<div style="display: none;" id="rotPanel" class="flow_hidden">
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
									<li style="margin-', $context['right_to_left'] ? 'right' : 'left', ': ', $board['child_level'] * 1.5, 'em;"><label for="boards_', $board['id'], '"><input type="checkbox" name="boards[', $board['id'], ']" id="boards_', $board['id'], '" checked>', $board['name'], '</label></li>';

		echo '
								</ul>
							</fieldset>';

		// Increase $i, and check if we're at the middle yet.
		if (++$i == $middle)
			echo '
						</div>
						<div class="floatright" style="width: 49%;">';
	}

	echo '
						</div>
					</div>
					<span><input type="submit" value="', $txt['maintain_old_remove'], '" onclick="return confirm(', JavaScriptEscape($txt['maintain_old_confirm']), ');" class="button_submit" /></span>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				</form>
			</div>
		</div>
		<div class="cat_bar">
			<h3>', $txt['move_topics_maintenance'], '</h3>
		</div>
		<div class="windowbg2 wrc">
			<form action="', $scripturl, '?action=admin;area=maintain;sa=topics;activity=massmove" method="post" accept-charset="UTF-8">
				<p><label for="id_board_from">', $txt['move_topics_from'], ' </label>
				<select name="id_board_from" id="id_board_from">
					<option disabled>(', $txt['move_topics_select_board'], ')</option>';

	// From board
	foreach ($context['categories'] as $category)
	{
		echo '
					<option disabled>--------------------------------------</option>
					<option disabled>', $category['name'], '</option>
					<option disabled>--------------------------------------</option>';

		foreach ($category['boards'] as $board)
			echo '
					<option value="', $board['id'], '"> ', str_repeat('==', $board['child_level']), '=&gt;&nbsp;', $board['name'], '</option>';
	}

	echo '
				</select>
				<label for="id_board_to">', $txt['move_topics_to'], '</label>
				<select name="id_board_to" id="id_board_to">
					<option disabled>(', $txt['move_topics_select_board'], ')</option>';

	// To board
	foreach ($context['categories'] as $category)
	{
		echo '
					<option disabled>--------------------------------------</option>
					<option disabled>', $category['name'], '</option>
					<option disabled>--------------------------------------</option>';

		foreach ($category['boards'] as $board)
			echo '
					<option value="', $board['id'], '"> ', str_repeat('==', $board['child_level']), '=&gt;&nbsp;', $board['name'], '</option>';
	}
	echo '
				</select></p>
				<span><input type="submit" value="', $txt['move_topics_now'], '" onclick="return moveTopicsNow();" class="button_submit" /></span>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			</form>
		</div>
	</div>
	<br class="clear" />';

	add_js('
	function moveTopicsNow()
	{
		if ($("#id_board_from option:selected").attr("disabled") || $("#id_board_to option:selected").attr("disabled"))
			return false;
		var confirmText = ', JavaScriptEscape($txt['move_topics_confirm']), ';
		return confirm(confirmText.replace(/%board_from%/, $("#id_board_from").val().replace(/^=+&gt;&nbsp;/, \'\'))
			.replace(/%board_to%/, $("#id_board_to").val().replace(/^=+&gt;&nbsp;/, \'\')));
	}');
}

// Simple template for showing results of our optimization...
function template_optimize()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3>', $txt['maintain_optimize'], '</h3>
		</div>
		<div class="windowbg wrc">
			<p>
				', $txt['database_numb_tables'], '<br />
				', $txt['database_optimize_attempt'], '<br />';

	// List each table being optimized...
	foreach ($context['optimized_tables'] as $table)
		echo '
				', sprintf($txt['database_optimizing'], $table['name'], $table['data_freed']), '<br />';

	// How did we go?
	echo '
				<br />', $context['num_tables_optimized'] == 0 ? $txt['database_already_optimized'] : $context['num_tables_optimized'] . ' ' . $txt['database_optimized'];

	echo '
			</p>
			<p><a href="', $scripturl, '?action=admin;area=maintain">', $txt['maintain_return'], '</a></p>
		</div>
	</div>
	<br class="clear" />';
}

function template_convert_utf8()
{
	global $context, $txt, $settings, $scripturl;

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3>', $txt['utf8_title'], '</h3>
		</div>
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
				<input type="submit" value="', $txt['utf8_proceed'], '" class="button_submit" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" name="proceed" value="1" />
			</form>
		</div>
	</div>
	<br class="clear" />';
}

function template_convert_entities()
{
	global $context, $txt, $settings, $scripturl;

	echo '
	<div id="manage_maintenance">
		<div class="cat_bar">
			<h3>', $txt['entity_convert_title'], '</h3>
		</div>
		<div class="windowbg wrc">
			<p>', $txt['entity_convert_introduction'], '</p>
			<form action="', $scripturl, '?action=admin;area=maintain;sa=database;activity=convertentities;start=0;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
				<input type="submit" value="', $txt['entity_convert_proceed'], '" class="button_submit" />
			</form>
		</div>
	</div>
	<br class="clear" />';
}

?>