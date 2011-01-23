<?php
// Version: 2.0 RC4; ManageSmileys

// Editing the smiley sets.
function template_editsets()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">';

	template_show_list('smiley_set_list');

	echo '
		<br>
		<we:cat>
			', $txt['smiley_sets_latest'], '
		</we:cat>
		<div class="windowbg wrc">
			<div id="smileysLatest">', $txt['smiley_sets_latest_fetch'], '</div>
		</div>
	</div>
	<br class="clear">';

	add_js('
	window.smfForum_scripturl = "', $scripturl, '";
	window.smfForum_sessionid = "', $context['session_id'], '";
	window.smfForum_sessionvar = "', $context['session_var'], '";');

	if (empty($modSettings['disable_smf_js']))
		add_js_file($scripturl . '?action=viewsmfile;filename=latest-smileys.js', true);

	add_js('
	if (typeof(window.smfLatestSmileys) != "undefined")
		$("#smileysLatest").html(window.smfLatestSmileys);', !empty($context['selected_set']) ? '
	changeSet("' . $context['selected_set'] . '");' : '');
}

// Modifying a smiley set.
function template_modifyset()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=smileys;sa=editsets" method="post" accept-charset="UTF-8">
			<we:cat>
				', $context['current_set']['is_new'] ? $txt['smiley_set_new'] : $txt['smiley_set_modify_existing'], '
			</we:cat>';

		// If this is an existing set, and there are still un-added smileys - offer an import opportunity.
		if (!empty($context['current_set']['can_import']))
		{
			echo '
			<div class="information">
				', $context['current_set']['can_import'] == 1 ? $txt['smiley_set_import_single'] : $txt['smiley_set_import_multiple'], ' <a href="', $scripturl, '?action=admin;area=smileys;sa=import;set=', $context['current_set']['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['here'], '</a> ', $context['current_set']['can_import'] == 1 ? $txt['smiley_set_to_import_single'] : $txt['smiley_set_to_import_multiple'], '
			</div>';
		}

		echo '
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<strong><label for="smiley_sets_name">', $txt['smiley_sets_name'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_sets_name" id="smiley_sets_name" value="', $context['current_set']['name'], '">
					</dd>
					<dt>
						<strong><label for="smiley_sets_path">', $txt['smiley_sets_url'], '</label>: </strong>
					</dt>
					<dd>
						', $modSettings['smileys_url'], '/';

		if ($context['current_set']['id'] == 'default')
			echo '<strong>default</strong><input type="hidden" name="smiley_sets_path" id="smiley_sets_path" value="default">';

		elseif (empty($context['smiley_set_dirs']))
			echo '
						<input type="text" name="smiley_sets_path" id="smiley_sets_path" value="', $context['current_set']['path'], '"> ';

		else
		{
			echo '
						<select name="smiley_sets_path" id="smiley_sets_path">';

			foreach ($context['smiley_set_dirs'] as $smiley_set_dir)
				echo '
							<option value="', $smiley_set_dir['id'], '"', $smiley_set_dir['current'] ? ' selected' : '', $smiley_set_dir['selectable'] ? '' : ' disabled', '>', $smiley_set_dir['id'], '</option>';

			echo '
						</select> ';
		}

		echo '
						/..
					</dd>
					<dt>
						<strong><label for="smiley_sets_default">', $txt['smiley_set_select_default'], '</label>: </strong>
					</dt>
					<dd>
						<input type="checkbox" name="smiley_sets_default" id="smiley_sets_default" value="1"', $context['current_set']['selected'] ? ' checked' : '', '>
					</dd>';

		// If this is a new smiley set they have the option to import smileys already in the directory.
		if ($context['current_set']['is_new'] && !empty($modSettings['smiley_enable']))
			echo '
					<dt>
						<strong><label for="smiley_sets_import">', $txt['smiley_set_import_directory'], '</label>: </strong>
					</dt>
					<dd>
						<input type="checkbox" name="smiley_sets_import" id="smiley_sets_import" value="1">
					</dd>';

		echo '
				</dl>
				<input type="submit" value="', $txt['smiley_sets_save'], '" class="save">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="set" value="', $context['current_set']['id'], '">
		</form>
	</div>
	<br class="clear">';
}

// Editing an individual smiley
function template_modifysmiley()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=smileys;sa=editsmileys" method="post" accept-charset="UTF-8" name="smileyForm" id="smileyForm">
			<we:cat>
				', $txt['smiley_modify_existing'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<strong>', $txt['smiley_preview'], ': </strong>
					</dt>
					<dd>
						<img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $context['current_smiley']['filename'], '" id="preview"> (', $txt['smiley_preview_using'], ': <select name="set" onchange="updatePreview();">';

	foreach ($context['smiley_sets'] as $smiley_set)
		echo '
						<option value="', $smiley_set['path'], '"', $context['selected_set'] == $smiley_set['path'] ? ' selected' : '', '>', $smiley_set['name'], '</option>';

	echo '
						</select>)
					</dd>
					<dt>
						<strong><label for="smiley_code">', $txt['smileys_code'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_code" id="smiley_code" value="', $context['current_smiley']['code'], '">
					</dd>
					<dt>
						<strong><label for="smiley_filename">', $txt['smileys_filename'], '</label>: </strong>
					</dt>
					<dd>';

	if (empty($context['filenames']))
		echo '
						<input type="text" name="smiley_filename" id="smiley_filename" value="', $context['current_smiley']['filename'], '">';
	else
	{
		echo '
						<select name="smiley_filename" id="smiley_filename" onchange="updatePreview();">';
		foreach ($context['filenames'] as $filename)
			echo '
							<option value="', $filename['id'], '"', $filename['selected'] ? ' selected' : '', '>', $filename['id'], '</option>';
		echo '
						</select>';
	}

	echo '
					</dd>
					<dt>
						<strong><label for="smiley_description">', $txt['smileys_description'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_description" id="smiley_description" value="', $context['current_smiley']['description'], '">
					</dd>
					<dt>
						<strong><label for="smiley_location">', $txt['smileys_location'], '</label>: </strong>
					</dt>
					<dd>
						<select name="smiley_location" id="smiley_location">
							<option value="0"', $context['current_smiley']['location'] == 0 ? ' selected' : '', '>
								', $txt['smileys_location_form'], '
							</option>
							<option value="1"', $context['current_smiley']['location'] == 1 ? ' selected' : '', '>
								', $txt['smileys_location_hidden'], '
							</option>
							<option value="2"', $context['current_smiley']['location'] == 2 ? ' selected' : '', '>
								', $txt['smileys_location_popup'], '
							</option>
						</select>
					</dd>
				</dl>
				<input type="submit" value="', $txt['smileys_save'], '" class="save">
				<input type="submit" name="deletesmiley" value="', $txt['smileys_delete'], '" onclick="return confirm(', JavaScriptEscape($txt['smileys_delete_confirm']), ');" class="delete">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="smiley" value="', $context['current_smiley']['id'], '">
		</form>
	</div>
	<br class="clear">';

	add_js('
	function updatePreview()
	{
		$("#preview").attr("src", "', $modSettings['smileys_url'], '/" + document.forms.smileyForm.set.value + "/" + document.forms.smileyForm.smiley_filename.value);
	}');
}

// Adding a new smiley.
function template_addsmiley()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	add_js('
	function switchType()
	{
		$("#ul_settings").slideToggle(!$("#method-existing").attr("checked"));
		$("#ex_settings").slideToggle(!$("#method-upload").attr("checked"));
	}

	function swapUploads()
	{
		var enabled = !$("#uploadSmiley").attr("disabled");
		$("#uploadMore").slideToggle(enabled);
		$("#uploadSmiley").attr("disabled", enabled);
	}

	function selectMethod(element)
	{
		$("#method-existing").attr("checked", element != "upload");
		$("#method-upload").attr("checked", element == "upload");
	}');

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=smileys;sa=addsmiley" method="post" accept-charset="UTF-8" name="smileyForm" id="smileyForm" enctype="multipart/form-data">
			<we:cat>
				', $txt['smileys_add_method'], '
			</we:cat>
			<div class="windowbg wrc">
				<ul class="reset">
					<li>
						<label for="method-existing"><input type="radio" onclick="switchType();" name="method" id="method-existing" value="existing" checked> ', $txt['smileys_add_existing'], '</label>
					</li>
					<li>
						<label for="method-upload"><input type="radio" onclick="switchType();" name="method" id="method-upload" value="upload"> ', $txt['smileys_add_upload'], '</label>
					</li>
				</ul>
				<br>
				<fieldset id="ex_settings">
					<dl class="settings">
						<dt>
							<img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $context['filenames'][0]['id'], '" id="preview">
						</dt>
						<dd>
							', $txt['smiley_preview_using'], ': <select name="set" onchange="updatePreview(); selectMethod(\'existing\');">';

	foreach ($context['smiley_sets'] as $smiley_set)
		echo '
							<option value="', $smiley_set['path'], '"', $context['selected_set'] == $smiley_set['path'] ? ' selected' : '', '>', $smiley_set['name'], '</option>';

	echo '
							</select>
						</dd>
						<dt>
							<strong><label for="smiley_filename">', $txt['smileys_filename'], '</label>: </strong>
						</dt>
						<dd>';

	if (empty($context['filenames']))
		echo '
							<input type="text" name="smiley_filename" id="smiley_filename" value="', $context['current_smiley']['filename'], '" onchange="selectMethod(\'existing\');">';
	else
	{
		echo '
							<select name="smiley_filename" id="smiley_filename" onchange="updatePreview(); selectMethod(\'existing\');">';

		foreach ($context['filenames'] as $filename)
			echo '
								<option value="', $filename['id'], '"', $filename['selected'] ? ' selected' : '', '>', $filename['id'], '</option>';

		echo '
							</select>';
	}

	echo '
						</dd>
					</dl>
				</fieldset>
				<fieldset id="ul_settings" style="display: none">
					<dl class="settings">
						<dt>
							<strong>', $txt['smileys_add_upload_choose'], ':</strong>
							<dfn>', $txt['smileys_add_upload_choose_desc'], '</dfn>
						</dt>
						<dd>
							<input type="file" name="uploadSmiley" id="uploadSmiley" onchange="selectMethod(\'upload\');">
						</dd>
						<dt>
							<strong><label for="sameall">', $txt['smileys_add_upload_all'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="sameall" id="sameall" checked onclick="swapUploads(); selectMethod(\'upload\');">
						</dd>
					</dl>
					<dl id="uploadMore" class="settings" style="display: none">';

	foreach ($context['smiley_sets'] as $smiley_set)
		echo '
						<dt>
							', $txt['smileys_add_upload_for1'], ' <strong>', $smiley_set['name'], '</strong> ', $txt['smileys_add_upload_for2'], ':
						</dt>
						<dd>
							<input type="file" name="individual_', $smiley_set['name'], '" onchange="selectMethod(\'upload\');">
						</dd>';

	echo '
					</dl>
				</fieldset>
			</div>
			<br>
			<we:cat>
				', $txt['smiley_new'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<strong><label for="smiley_code">', $txt['smileys_code'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_code" id="smiley_code" value="">
					</dd>
					<dt>
						<strong><label for="smiley_description">', $txt['smileys_description'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="smiley_description" id="smiley_description" value="">
					</dd>
					<dt>
						<strong><label for="smiley_location">', $txt['smileys_location'], '</label>: </strong>
					</dt>
					<dd>
						<select name="smiley_location" id="smiley_location">
							<option value="0" selected>
								', $txt['smileys_location_form'], '
							</option>
							<option value="1">
								', $txt['smileys_location_hidden'], '
							</option>
							<option value="2">
								', $txt['smileys_location_popup'], '
							</option>
						</select>
					</dd>
				</dl>
				<input type="submit" value="', $txt['smileys_save'], '" class="save">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';

	add_js('
	function updatePreview()
	{
		$("#preview").attr("src", "', $modSettings['smileys_url'], '/" + document.forms.smileyForm.set.value + "/" + document.forms.smileyForm.smiley_filename.value);
	}');
}

// Ordering smileys.
function template_setorder()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">';

	foreach ($context['smileys'] as $location)
	{
		echo '
		<form action="', $scripturl, '?action=admin;area=smileys;sa=editsmileys" method="post" accept-charset="UTF-8">
			<we:cat>
				', $location['title'], '
			</we:cat>
			<div class="information">
				', $location['description'], '
			</div>
			<div class="windowbg wrc">
				<strong>', empty($context['move_smiley']) ? $txt['smileys_move_select_smiley'] : $txt['smileys_move_select_destination'], '...</strong><br>';

		foreach ($location['rows'] as $row)
		{
			if (!empty($context['move_smiley']))
				echo '
				<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';row=', $row[0]['row'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '"><img src="', $settings['images_url'], '/smiley_select_spot.gif" alt="', $txt['smileys_move_here'], '"></a>';

			foreach ($row as $smiley)
			{
				if (empty($context['move_smiley']))
					echo '<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;move=', $smiley['id'], '"><img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $smiley['filename'], '" style="padding: 2px; border: 0px solid black;" alt="', $smiley['description'], '"></a>';
				else
					echo '<img src="', $modSettings['smileys_url'], '/', $modSettings['smiley_sets_default'], '/', $smiley['filename'], '" style="padding: 2px; border: ', $smiley['selected'] ? '2px solid red' : '0px solid black', ';" alt="', $smiley['description'], '"><a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';after=', $smiley['id'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '" title="', $txt['smileys_move_here'], '"><img src="', $settings['images_url'], '/smiley_select_spot.gif" alt="', $txt['smileys_move_here'], '"></a>';
			}

			echo '
				<br>';
		}
		if (!empty($context['move_smiley']))
			echo '
				<a href="', $scripturl, '?action=admin;area=smileys;sa=setorder;location=', $location['id'], ';source=', $context['move_smiley'], ';row=', $location['last_row'], ';reorder=1;', $context['session_var'], '=', $context['session_id'], '"><img src="', $settings['images_url'], '/smiley_select_spot.gif" alt="', $txt['smileys_move_here'], '"></a>';
		echo '
			</div>
			<input type="hidden" name="reorder" value="1">
		</form>
		<br>';
	}
	echo '
	</div>
	<br class="clear">';
}

// Editing Message Icons
function template_editicons()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	template_show_list('message_icon_list');
}

// Editing an individual message icon
function template_editicon()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=smileys;sa=editicon;icon=', $context['new_icon'] ? '0' : $context['icon']['id'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $context['new_icon'] ? $txt['icons_new_icon'] : $txt['icons_edit_icon'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">';

	if (!$context['new_icon'])
		echo '
					<dt>
						<strong>', $txt['smiley_preview'], ': </strong>
					</dt>
					<dd>
						<img src="', $context['icon']['image_url'], '" alt="', $context['icon']['title'], '">
					</dd>';

	echo '
					<dt>
						<strong><label for="icon_filename">', $txt['smileys_filename'], '</label>: </strong>
						<dfn>', $txt['icons_filename_all_gif'], '</dfn>
					</dt>
					<dd>
						<input type="text" name="icon_filename" id="icon_filename" value="', !empty($context['icon']['filename']) ? $context['icon']['filename'] . '.gif' : '', '">
					</dd>
					<dt>
						<strong><label for="icon_description">', $txt['smileys_description'], '</label>: </strong>
					</dt>
					<dd>
						<input type="text" name="icon_description" id="icon_description" value="', !empty($context['icon']['title']) ? $context['icon']['title'] : '', '">
					</dd>
					<dt>
						<strong><label for="icon_board_select">', $txt['icons_board'], '</label>: </strong>
					</dt>
					<dd>
						<select name="icon_board" id="icon_board_select">
							<option value="0"', empty($context['icon']['board_id']) ? ' selected' : '', '>', $txt['icons_edit_icons_all_boards'], '</option>';

	foreach ($context['categories'] as $category)
	{
		echo '
							<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
			echo '
								<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level'] - 1) . '=&gt;' : '', ' ', $board['name'], '</option>';

		echo '
							</optgroup>';
	}

	echo '
						</select>
					</dd>
					<dt>
						<strong><label for="icon_location">', $txt['smileys_location'], '</label>: </strong>
					</dt>
					<dd>
						<select name="icon_location" id="icon_location">
							<option value="0"', empty($context['icon']['after']) ? ' selected' : '', '>', $txt['icons_location_first_icon'], '</option>';

	// Print the list of all the icons it can be put after...
	foreach ($context['icons'] as $id => $data)
		if (empty($context['icon']['id']) || $id != $context['icon']['id'])
			echo '
							<option value="', $id, '"', !empty($context['icon']['after']) && $id == $context['icon']['after'] ? ' selected' : '', '>', $txt['icons_location_after'], ': ', $data['title'], '</option>';

	echo '
						</select>
					</dd>
				</dl>';

	if (!$context['new_icon'])
		echo '
				<input type="hidden" name="icon" value="', $context['icon']['id'], '">';

	echo '

				<input type="submit" value="', $txt['smileys_save'], '" class="save">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>
	</div>
	<br class="clear">';
}

?>