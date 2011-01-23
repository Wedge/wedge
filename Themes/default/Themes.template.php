<?php
// Version: 2.0 RC4; Themes

// The main sub template - for theme administration.
function template_main()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;sa=admin" method="post" accept-charset="UTF-8">
			<input type="hidden" value="0" name="options[theme_allow]">
			<we:cat>
				<a href="', $scripturl, '?action=helpadmin;help=themes" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '"></a>
				', $txt['themeadmin_title'], '
			</we:cat>
			<div class="information">
				', $txt['themeadmin_explain'], '
			</div>
			<div class="windowbg2 wrc">
				<dl class="settings">
					<dt>
						<label for="options-theme_allow"> ', $txt['theme_allow'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="options[theme_allow]" id="options-theme_allow" value="1"', !empty($modSettings['theme_allow']) ? ' checked' : '', '>
					</dd>
					<dt>
						<label for="known_themes_list">', $txt['themeadmin_selectable'], '</label>:
					</dt>
					<dd>
						<div id="known_themes_list">';

	foreach ($context['themes'] as $theme)
		echo '
							<label for="options-known_themes_', $theme['id'], '"><input type="checkbox" name="options[known_themes][]" id="options-known_themes_', $theme['id'], '" value="', $theme['id'], '"', $theme['known'] ? ' checked' : '', '> ', $theme['name'], '</label><br>';

	echo '
						</div>
						<a href="#" onclick="$(\'#known_themes_list\').show(); $(\'#known_themes_link\').hide(); return false; " id="known_themes_link" style="display: none;">[ ', $txt['themeadmin_themelist_link'], ' ]</a>
					</dd>
					<dt>
						<label for="theme_guests">', $txt['theme_guests'], ':</label>
					</dt>
					<dd>
						<select name="options[theme_guests]" id="theme_guests">
							';

	add_js('
	$("#known_themes_list").hide();
	$("#known_themes_link").show();');

	$styling = empty($modSettings['theme_styling_guests']) ? 'css' : $modSettings['theme_styling_guests'];

	// Put an option for each theme in the select box.
	foreach ($context['themes'] as $theme)
	{
		echo '<option value="', $theme['id'], '"', $modSettings['theme_guests'] == $theme['id'] && $styling == 'css' ? ' selected' : '', '>', $theme['name'], '</option>';
		if (!empty($theme['stylings']))
			wedge_show_stylings($theme, $theme['stylings'], 1, $modSettings['theme_guests'], $styling);
	}

	echo '
						</select>
						<span class="smalltext pick_theme"><a href="', $scripturl, '?action=theme;sa=pick;u=-1;', $context['session_var'], '=', $context['session_id'], '">', $txt['theme_select'], '</a></span>
					</dd>
					<dt>
						<label for="theme_reset">', $txt['theme_reset'], '</label>:
					</dt>
					<dd>
						<select name="theme_reset" id="theme_reset">
							<option value="-1" selected>', $txt['theme_nochange'], '</option>
							<option value="0">', $txt['theme_forum_default'], '</option>';

	// Same thing, this time for changing the theme of everyone.
	foreach ($context['themes'] as $theme)
	{
		echo '
							<option value="', $theme['id'], '">', $theme['name'], '</option>';
		if (!empty($theme['stylings']))
			wedge_show_stylings($theme, $theme['stylings'], 1, '', '');
	}

	echo '
						</select>
						<span class="smalltext pick_theme"><a href="', $scripturl, '?action=theme;sa=pick;u=0;', $context['session_var'], '=', $context['session_id'], '">', $txt['theme_select'], '</a></span>
					</dd>
				</dl>
				<div class="righttext">
					<input type="submit" name="submit" value="' . $txt['save'] . '" class="save">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';

	// Link to simplemachines.org for latest themes and info!
	echo '
		<br>
		<we:cat>
			<a href="', $scripturl, '?action=helpadmin;help=latest_themes" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '"></a> ', $txt['theme_latest'], '
		</we:cat>
		<div class="windowbg wrc">
			<div id="themeLatest">
				', $txt['theme_latest_fetch'], '
			</div>
		</div>
		<br>';

	// Warn them if theme creation isn't possible!
	if (!$context['can_create_new'])
		echo '
		<div class="errorbox">', $txt['theme_install_writable'], '</div>';

	echo '
		<form action="', $scripturl, '?action=admin;area=theme;sa=install" method="post" accept-charset="UTF-8" enctype="multipart/form-data" onsubmit="return confirm(', JavaScriptEscape($txt['theme_install_new_confirm']), ');">
			<we:cat>
				<a href="', $scripturl, '?action=helpadmin;help=theme_install" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '"></a> ', $txt['theme_install'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">';

	// Here's a little box for installing a new theme.
	// !!! Should the value="theme_gz" be there?!
	if ($context['can_create_new'])
		echo '
					<dt>
						<label for="theme_gz">', $txt['theme_install_file'], '</label>:
					</dt>
					<dd>
						<input type="file" name="theme_gz" id="theme_gz" onchange="this.form.copy.disabled = this.value != \'\'; this.form.theme_dir.disabled = this.value != \'\';">
					</dd>';

	echo '
					<dt>
						<label for="theme_dir">', $txt['theme_install_dir'], '</label>:
					</dt>
					<dd>
						<input type="text" name="theme_dir" id="theme_dir" value="', $context['new_theme_dir'], '" size="40" style="width: 70%">
					</dd>';

	if ($context['can_create_new'])
		echo '
					<dt>
						<label for="copy">', $txt['theme_install_new'], ':</label>
					</dt>
					<dd>
						<input type="text" name="copy" id="copy" value="', $context['new_theme_name'], '" size="40">
					</dd>';

	echo '
				</dl>
				<div class="righttext">
					<input type="submit" name="submit" value="', $txt['theme_install_go'], '" class="submit">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';

	add_js('
	window.smfForum_scripturl = "', $scripturl, '";
	window.smfForum_sessionid = "', $context['session_id'], '";
	window.smfForum_sessionvar = "', $context['session_var'], '";
	window.smfThemes_writable = ', $context['can_create_new'] ? 'true' : 'false', ';');

	if (empty($modSettings['disable_smf_js']))
		add_js_file($scripturl . '?action=viewsmfile;filename=latest-themes.js', true);

	add_js('
	if (typeof(window.smfLatestThemes) != "undefined")
		$("#themeLatest").html(window.smfLatestThemes);');
}

function template_list_themes()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<we:cat>
			', $txt['themeadmin_list_heading'], '
		</we:cat>
		<div class="information">
			', $txt['themeadmin_list_tip'], '
		</div>';

	// Show each theme.... with X for delete and a link to settings.
	foreach ($context['themes'] as $theme)
	{
		echo '
		<we:title>';

		// You *cannot* delete the default theme. It's important!
		if ($theme['id'] != 1)
			echo '
			<span class="floatright"><a href="', $scripturl, '?action=admin;area=theme;sa=remove;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(', JavaScriptEscape($txt['theme_remove_confirm']), ');"><img src="', $settings['images_url'], '/icons/delete.gif" alt="', $txt['theme_remove'], '" title="', $txt['theme_remove'], '"></a></span>';

		echo '
			<strong><a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=settings">', $theme['name'], '</a></strong>', !empty($theme['version']) ? ' <em>(' . $theme['version'] . ')</em>' : '', '
		</we:title>
		<div class="windowbg wrc">
			<dl class="settings themes_list">
				<dt>', $txt['themeadmin_list_theme_dir'], ':</dt>
				<dd', $theme['valid_path'] ? '' : ' class="error"', '>', $theme['theme_dir'], $theme['valid_path'] ? '' : ' ' . $txt['themeadmin_list_invalid'], '</dd>
				<dt>', $txt['themeadmin_list_theme_url'], ':</dt>
				<dd>', $theme['theme_url'], '</dd>
				<dt>', $txt['themeadmin_list_images_url'], ':</dt>
				<dd>', $theme['images_url'], '</dd>
			</dl>
		</div>';
	}

	echo '
		<form action="', $scripturl, '?action=admin;area=theme;', $context['session_var'], '=', $context['session_id'], ';sa=list" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['themeadmin_list_reset'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<label for="reset_dir">', $txt['themeadmin_list_reset_dir'], '</label>:
					</dt>
					<dd>
						<input type="text" name="reset_dir" id="reset_dir" value="', $context['reset_dir'], '" size="40" style="width: 80%">
					</dd>
					<dt>
						<label for="reset_url">', $txt['themeadmin_list_reset_url'], '</label>:
					</dt>
					<dd>
						<input type="text" name="reset_url" id="reset_url" value="', $context['reset_url'], '" size="40" style="width: 80%">
					</dd>
				</dl>
				<input type="submit" name="submit" value="', $txt['themeadmin_list_reset_go'], '" class="submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>
	</div>
	<br class="clear">';
}

function template_reset_list()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<we:cat>
			', $txt['themeadmin_reset_title'], '
		</we:cat>
		<div class="information">
			', $txt['themeadmin_reset_tip'], '
		</div>';

	// Show each theme.... with X for delete and a link to settings.
	$alternate = false;

	foreach ($context['themes'] as $theme)
	{
		$alternate = !$alternate;

		echo '
		<we:title>
			', $theme['name'], '
		</we:title>
		<div class="windowbg', $alternate ? '' : '2',' wrc">
			<ul class="reset">
				<li>
					<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset">', $txt['themeadmin_reset_defaults'], '</a> <em class="smalltext">(', $theme['num_default_options'], ' ', $txt['themeadmin_reset_defaults_current'], ')</em>
				</li>
				<li>
					<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset;who=1">', $txt['themeadmin_reset_members'], '</a>
				</li>
				<li>
					<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=reset;who=2" onclick="return confirm(', JavaScriptEscape($txt['themeadmin_reset_remove_confirm']), ');">', $txt['themeadmin_reset_remove'], '</a> <em class="smalltext">(', $theme['num_members'], ' ', $txt['themeadmin_reset_remove_current'], ')</em>
				</li>
			</ul>
		</div>';
	}

	echo '
	</div>
	<br class="clear">';
}

function template_set_options()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';sa=reset" method="post" accept-charset="UTF-8">
			<input type="hidden" name="who" value="', $context['theme_options_reset'] ? 1 : 0, '">
			<we:cat>
				', $txt['theme_options_title'], ' - ', $context['theme_settings']['name'], '
			</we:cat>
			<div class="information">
				', $context['theme_options_reset'] ? $txt['themeadmin_reset_options_info'] : $txt['theme_options_defaults'], '
			</div>
			<div class="windowbg2 wrc">
				<ul class="theme_options">';

	foreach ($context['options'] as $setting)
	{
		echo '
					<li class="theme_option">';

		if ($context['theme_options_reset'])
			echo '
						<select name="', !empty($setting['default']) ? 'default_' : '', 'options_master[', $setting['id'], ']" onchange="this.form.options_', $setting['id'], '.disabled = this.selectedIndex != 1;">
							<option value="0" selected>', $txt['themeadmin_reset_options_none'], '</option>
							<option value="1">', $txt['themeadmin_reset_options_change'], '</option>
							<option value="2">', $txt['themeadmin_reset_options_remove'], '</option>
						</select>';

		if ($setting['type'] == 'checkbox')
			echo '
						<input type="hidden" name="' . (!empty($setting['default']) ? 'default_' : '') . 'options[' . $setting['id'] . ']" value="0">
						<label for="options_', $setting['id'], '"><input type="checkbox" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '"', !empty($setting['value']) ? ' checked' : '', $context['theme_options_reset'] ? ' disabled' : '', ' value="1"> ', $setting['label'], '</label>';

		elseif ($setting['type'] == 'list')
		{
			echo '
						&nbsp;<label for="options_', $setting['id'], '">', $setting['label'], '</label>
						<select name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '"', $context['theme_options_reset'] ? ' disabled' : '', '>';

			foreach ($setting['options'] as $value => $label)
				echo '
							<option value="', $value, '"', $value == $setting['value'] ? ' selected' : '', '>', $label, '</option>';

			echo '
						</select>';
		}
		else
			echo '
						&nbsp;<label for="options_', $setting['id'], '">', $setting['label'], '</label>
						<input type="text" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="options_', $setting['id'], '" value="', $setting['value'], '"', $setting['type'] == 'number' ? ' size="5"' : '', $context['theme_options_reset'] ? ' disabled' : '', '>';

		if (isset($setting['description']))
			echo '
						<div class="smalltext">', $setting['description'], '</div>';

		echo '
					</li>';
	}

	echo '
				</ul>
				<div class="righttext">
					<input type="submit" name="submit" value="', $txt['save'], '" class="save">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</div>
		</form>
	</div>
	<br class="clear">';
}

function template_set_settings()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;sa=settings;th=', $context['theme_settings']['theme_id'], '" method="post" accept-charset="UTF-8">
			<we:title>
				<a href="', $scripturl, '?action=helpadmin;help=theme_settings" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '"></a>', $txt['theme_settings'], ' - ', $context['theme_settings']['name'], '
			</we:title>';

	// !!! Why can't I edit the default theme popup.
	if ($context['theme_settings']['theme_id'] != 1)
		echo '
			<we:cat>
				<img src="', $settings['images_url'], '/icons/config_sm.gif">', $txt['theme_edit'], '
			</we:cat>
			<div class="windowbg wrc">
				<ul class="reset">
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit;filename=index.template.php">', $txt['theme_edit_index'], '</a>
					</li>
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit;directory=css">', $txt['theme_edit_style'], '</a>
					</li>
				</ul>
			</div>';

	echo '
			<we:cat>
				<img src="', $settings['images_url'], '/icons/config_sm.gif">', $txt['theme_url_config'], '
			</we:cat>
			<div class="windowbg2 wrc">
				<dl class="settings">
					<dt>
						<label for="theme_name">', $txt['actual_theme_name'], '</label>
					</dt>
					<dd>
						<input type="text" id="theme_name" name="options[name]" value="', $context['theme_settings']['name'], '" size="32">
					</dd>
					<dt>
						<label for="theme_url">', $txt['actual_theme_url'], '</label>
					</dt>
					<dd>
						<input type="text" id="theme_url" name="options[theme_url]" value="', $context['theme_settings']['actual_theme_url'], '" size="50" style="max-width: 100%; width: 50ex">
					</dd>
					<dt>
						<label for="images_url">', $txt['actual_images_url'], '</label>
					</dt>
					<dd>
						<input type="text" id="images_url" name="options[images_url]" value="', $context['theme_settings']['actual_images_url'], '" size="50" style="max-width: 100%; width: 50ex">
					</dd>
					<dt>
						<label for="theme_dir">', $txt['actual_theme_dir'], '</label>
					</dt>
					<dd>
						<input type="text" id="theme_dir" name="options[theme_dir]" value="', $context['theme_settings']['actual_theme_dir'], '" size="50" style="max-width: 100%; width: 50ex">
					</dd>
				</dl>
			</div>
			<we:cat>
				<img src="', $settings['images_url'], '/icons/config_sm.gif">', $txt['theme_options'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings flow_auto">';

	foreach ($context['settings'] as $setting)
	{
		// Is this a separator?
		if (empty($setting))
			echo '
				</dl>
				<hr>
				<dl class="settings flow_auto">';

		// A checkbox?
		elseif ($setting['type'] == 'checkbox')
		{
			echo '
					<dt>
						<label for="', $setting['id'], '">', $setting['label'], '</label>:';

			if (isset($setting['description']))
				echo '
						<dfn>', $setting['description'], '</dfn>';

			echo '
					</dt>
					<dd>
						<input type="hidden" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" value="0">
						<input type="checkbox" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '"', !empty($setting['value']) ? ' checked' : '', ' value="1">
					</dd>';
		}

		// A list with options?
		elseif ($setting['type'] == 'list')
		{
			echo '
					<dt>
						<label for="', $setting['id'], '">', $setting['label'], '</label>:';

			if (isset($setting['description']))
				echo '
						<dfn>', $setting['description'], '</dfn>';

			echo '
					</dt>
					<dd>
						<select name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '">';

			foreach ($setting['options'] as $value => $label)
				echo '
						<option value="', $value, '"', $value == $setting['value'] ? ' selected' : '', '>', $label, '</option>';

			echo '
						</select>
					</dd>';
		}

		// A regular input box, then?
		else
		{
			echo '
					<dt>
						<label for="', $setting['id'], '">', $setting['label'], '</label>:';

			if (isset($setting['description']))
				echo '
						<dfn>', $setting['description'], '</dfn>';

			echo '
					</dt>
					<dd>
						<input type="text" name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '" value="', $setting['value'], '" size="', $setting['type'] == 'number' ? '5' : (empty($setting['size']) ? '40' : $setting['size']), '">
					</dd>';
		}
	}

	echo '
				</dl>
				<div class="righttext">
					<input type="submit" name="submit" value="', $txt['save'], '" class="save">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';
}

// This template allows for the selection of different themes ;)
function template_pick()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="pick_theme">
		<form action="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">';

	// Just go through each theme and show its information - thumbnail, etc.
	foreach ($context['available_themes'] as $theme)
	{
		echo '
			<div style="margin: 8px 0"></div>
			<we:cat>
				<a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $theme['name'], '</a>
			</we:cat>
			<div class="', $theme['selected'] ? 'windowbg' : 'windowbg2', ' wrc flow_hidden">
				<div class="floatright">
					<a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';theme=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '" id="theme_thumb_preview_', $theme['id'], '" title="', $txt['theme_preview'], '"><img src="', $theme['thumbnail_href'], '" id="theme_thumb_', $theme['id'], '" class="padding"></a>
				</div>
				<p>
					', $theme['description'], '
				</p>
				<br>
				<p>
					<em class="smalltext">', $theme['num_users'], ' ', $theme['num_users'] == 1 ? $txt['theme_user'] : $txt['theme_users'], '</em>
				</p>
				<br>
				<ul class="reset">
					<li><a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '" id="theme_use_', $theme['id'], '">[', $txt['theme_set'], ']</a></li>
					<li><a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';theme=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], '" id="theme_preview_', $theme['id'], '">[', $txt['theme_preview'], ']</a></li>
				</ul>';

		if ($theme['id'] !== 0 && !empty($theme['stylings']))
		{
			echo '
				<div style="margin-top: 8px; clear: right">
					<we:title2>
						', $txt['theme_stylings'], '
					</we:title2>
				</div>';

			template_list_stylings($theme, $theme['id']);
		}

		echo '
			</div>';
	}

	echo '
		</form>
	</div>
	<br class="clear">';
}

function template_list_stylings(&$theme, $theme_id)
{
	global $txt, $context, $scripturl;

	foreach ($theme['stylings'] as $sty)
	{
		$target = $theme_id . '_' . base64_encode($sty['dir']);
		echo '
				<div class="roundframe" style="margin: 8px">
					<p><strong>', $sty['name'], '</strong></p>
					<p>', $sty['comment'], '</p>
					<ul class="reset">
						<li><a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';th=', $target, ';', $context['session_var'], '=', $context['session_id'], '" id="theme_use_', $target, '_', '">[', $txt['theme_styling_set'], ']</a></li>
						<li><a href="', $scripturl, '?action=theme;sa=pick;u=', $context['current_member'], ';theme=', $target, ';', $context['session_var'], '=', $context['session_id'], '" id="theme_preview_', $target, '_', '">[', $txt['theme_styling_preview'], ']</a></li>
					</ul>';

		if (!empty($sty['stylings']))
			template_list_stylings($sty, $theme_id);

		echo '
				</div>';
	}
}

// Okay, that theme was installed successfully!
function template_installed()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Not much to show except a link back...
	echo '
	<div id="admincenter">
		<we:cat>
			', $context['page_title'], '
		</we:cat>
		<div class="windowbg wrc">
			<p>
				<a href="', $scripturl, '?action=admin;area=theme;sa=settings;th=', $context['installed_theme']['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $context['installed_theme']['name'], '</a> ', $txt['theme_installed_message'], '
			</p>
			<p>
				<a href="', $scripturl, '?action=admin;area=theme;sa=admin;', $context['session_var'], '=', $context['session_id'], '">', $txt['back'], '</a>
			</p>
		</div>
	</div>
	<br class="clear">';
}

function template_edit_list()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<we:cat>
			', $txt['themeadmin_edit_title'], '
		</we:cat>';

	$alternate = false;

	foreach ($context['themes'] as $theme)
	{
		$alternate = !$alternate;

		echo '
		<we:title>
			<a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit">', $theme['name'], '</a>', !empty($theme['version']) ? '
			<em>(' . $theme['version'] . ')</em>' : '', '
		</we:title>
		<div class="windowbg', $alternate ? '' : '2', ' wrc">
			<ul class="reset">
				<li><a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=edit">', $txt['themeadmin_edit_browse'], '</a></li>', $theme['can_edit_style'] ? '
				<li><a href="' . $scripturl . '?action=admin;area=theme;th=' . $theme['id'] . ';' . $context['session_var'] . '=' . $context['session_id'] . ';sa=edit;directory=css">' . $txt['themeadmin_edit_style'] . '</a></li>' : '', '
				<li><a href="', $scripturl, '?action=admin;area=theme;th=', $theme['id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=copy">', $txt['themeadmin_edit_copy_template'], '</a></li>
			</ul>
		</div>';
	}

	echo '
	</div>
	<br class="clear">';
}

function template_copy_template()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<we:cat>
			', $txt['themeadmin_edit_filename'], '
		</we:cat>
		<div class="information">
			', $txt['themeadmin_edit_copy_warning'], '
		</div>
		<div class="windowbg wrc">
			<ul class="theme_options">';

	$alternate = false;
	foreach ($context['available_templates'] as $template)
	{
		$alternate = !$alternate;

		echo '
				<li class="reset flow_hidden windowbg', $alternate ? '2' : '', '">
					<span class="floatleft">', $template['filename'], $template['already_exists'] ? ' <span class="error">(' . $txt['themeadmin_edit_exists'] . ')</span>' : '', '</span>
					<span class="floatright">';

		if ($template['can_copy'])
			echo '<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';', $context['session_var'], '=', $context['session_id'], ';sa=copy;template=', $template['value'], '" onclick="return confirm(', JavaScriptEscape($template['already_exists'] ? $txt['themeadmin_edit_overwrite_confirm'] : $txt['themeadmin_edit_copy_confirm']), ');">', $txt['themeadmin_edit_do_copy'], '</a>';
		else
			echo $txt['themeadmin_edit_no_copy'];

		echo '
					</span>
				</li>';
	}

	echo '
			</ul>
		</div>
	</div>
	<br class="clear">';
}

function template_edit_browse()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<table class="table_grid tborder w100 cs0">
		<thead>
			<tr class="catbg">
				<th class="left first_th w50" scope="col">', $txt['themeadmin_edit_filename'], '</th>
				<th scope="col" style="width: 35%">', $txt['themeadmin_edit_modified'], '</th>
				<th class="last_th" scope="col" style="width: 15%">', $txt['themeadmin_edit_size'], '</th>
			</tr>
		</thead>
		<tbody>';

	$alternate = false;

	foreach ($context['theme_files'] as $file)
	{
		$alternate = !$alternate;

		echo '
			<tr class="windowbg', $alternate ? '2' : '', '">
				<td>';

		if ($file['is_editable'])
			echo '<a href="', $file['href'], '"', $file['is_template'] ? ' style="font-weight: bold;"' : '', '>', $file['filename'], '</a>';

		elseif ($file['is_directory'])
			echo '<a href="', $file['href'], '" class="is_directory">', $file['filename'], '</a>';

		else
			echo $file['filename'];

		echo '
				</td>
				<td class="right">', !empty($file['last_modified']) ? $file['last_modified'] : '', '</td>
				<td class="right">', $file['size'], '</td>
			</tr>';
	}

	echo '
		</tbody>
		</table>
	</div>
	<br class="clear">';
}

// Wanna edit the stylesheet?
function template_edit_style()
{
	global $context, $settings, $options, $scripturl, $txt;

	if ($context['session_error'])
		echo '
	<div class="errorbox">
		', $txt['error_session_timeout'], '
	</div>';

	// From now on no one can complain that editing css is difficult. If you disagree, go to www.w3schools.com.
	echo '
	<div id="admincenter">';

	add_js('
	var previewData = "";
	var previewTimeout;
	var editFilename = ', JavaScriptEscape($context['edit_filename']), ';
	var refreshPreviewCache;

	function navigateCallback(response)
	{
		previewData = response;
		$("#css_preview_box").show();

		// Revert to the theme they actually use ;)
		var tempImage = new Image();
		tempImage.src = smf_prepareScriptUrl(smf_scripturl) + "action=admin;area=theme;sa=edit;theme=', $context['theme_id'], !empty($user_info['styling']) ? '_' . base64_encode($user_info['styling']) : '', ';preview;" + (new Date().getTime());

		refreshPreviewCache = null;
		refreshPreview(false);
	}

	// Load up a page, but apply our stylesheet.
	function navigatePreview(url)
	{
		var anchor = "";
		if (url.indexOf("#") != -1)
		{
			anchor = url.substr(url.indexOf("#"));
			url = url.substr(0, url.indexOf("#"));
		}

		getXMLDocument(url + "theme=', $context['theme_id'], '_', base64_encode(dirname($context['edit_filename'])), ';nocsscache" + anchor, navigateCallback);
	}
	navigatePreview(smf_prepareScriptUrl(smf_scripturl));

	function refreshPreview(check)
	{
		var identical = document.forms.stylesheetForm.entire_file.value.replace(/url\([\./]+images/gi, "url(" + smf_images_url) == refreshPreviewCache;

		// Don\'t reflow the whole thing if nothing changed!!
		if (check && identical)
			return;
		refreshPreviewCache = document.forms.stylesheetForm.entire_file.value;

		// Replace the paths for images.
		refreshPreviewCache = refreshPreviewCache.replace(/url\([\./]+images/gi, "url(" + smf_images_url);

		// Try to do it without a complete reparse.
		if (identical)
		{
			try
			{
			');

	if ($context['browser']['is_ie'])
		add_js('
				var sheets = frames["css_preview_box"].document.styleSheets;
				for (var j = 0; j < sheets.length; j++)
				{
					if (sheets[j].id == "css_preview_box")
						sheets[j].cssText = document.forms.stylesheetForm.entire_file.value;
				}');
	else
		add_js('
				$("css_preview_sheet", frames["css_preview_box"].document).html(document.forms.stylesheetForm.entire_file.value);');

	add_js('
			}
			catch (e)
			{
				identical = false;
			}
		}

		// This will work most of the time... could be done with an after-apply, maybe.
		if (!identical)
		{
			var data = previewData + "";
			var preview_sheet = document.forms.stylesheetForm.entire_file.value;
			var stylesheetMatch = new RegExp(\'<link rel="stylesheet"[^>]+href="[^"]+\' + editFilename + \'[^>]*>\');

			// Replace the paths for images.
			preview_sheet = preview_sheet.replace(/url\([\./]+images/gi, "url(" + smf_images_url);
			data = data.replace(stylesheetMatch, "<style id=\"css_preview_sheet\">" + preview_sheet + "<" + "/style>");

			frames["css_preview_box"].document.open();
			frames["css_preview_box"].document.write(data);
			frames["css_preview_box"].document.close();

			// Next, fix all its links so we can handle them and reapply the new css!
			frames["css_preview_box"].onload = function ()
			{
				var fixLinks = frames["css_preview_box"].document.getElementsByTagName("a");
				for (var i = 0; i < fixLinks.length; i++)
				{
					if (fixLinks[i].onclick)
						continue;
					fixLinks[i].onclick = function ()
					{
						window.parent.navigatePreview(this.href);
						return false;
					};
				}
			};
		}
	}

	// The idea here is simple: don\'t refresh the preview on every keypress, but do refresh after they type.
	function setPreviewTimeout()
	{
		if (previewTimeout)
		{
			clearTimeout(previewTimeout);
			previewTimeout = null;
		}
		previewTimeout = setTimeout(function () {
				refreshPreview(true);
				previewTimeout = null;
			}, 500);
	}');

	echo '
		<iframe id="css_preview_box" name="css_preview_box" src="about:blank" style="display: none; margin-bottom: 2ex; border: 1px solid black; width: 99%; height: 400px" seamless="seamless"></iframe>';

	// Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.)
	echo '
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';sa=edit" method="post" accept-charset="UTF-8" name="stylesheetForm" id="stylesheetForm">
			<we:cat>
				', $txt['theme_edit'], ' - ', $context['edit_filename'], '
			</we:cat>
			<div class="windowbg wrc">';

	if (!$context['allow_save'])
		echo '
				', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '<br>';

	echo '
				<textarea name="entire_file" cols="80" rows="20" style="width: 97%; font: 110% monospace; margin-top: 1ex; white-space: pre;" onkeyup="setPreviewTimeout();" onchange="refreshPreview(true);">', $context['entire_file'], '</textarea><br>
				<div class="padding righttext">
					<input type="submit" name="submit" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled', ' style="margin-top: 1ex" class="save">
					<input type="button" value="', $txt['themeadmin_edit_preview'], '" onclick="refreshPreview(false);">
				</div>
			</div>
			<input type="hidden" name="filename" value="', $context['edit_filename'], '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';
}

// This edits the template...
function template_edit_template()
{
	global $context, $settings, $options, $scripturl, $txt;

	if ($context['session_error'])
		echo '
	<div class="errorbox">
		', $txt['error_session_timeout'], '
	</div>';

	if (isset($context['parse_error']))
		echo '
	<div class="errorbox">
		', $txt['themeadmin_edit_error'], '
			<div><tt>', $context['parse_error'], '</tt></div>
	</div>';

	// Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.)
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';sa=edit" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['theme_edit'], ' - ', $context['edit_filename'], '
			</we:cat>
			<div class="windowbg wrc">';

	if (!$context['allow_save'])
		echo '
				', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '<br>';

	foreach ($context['file_parts'] as $part)
		echo '
				<label for="on_line', $part['line'], '">', $txt['themeadmin_edit_on_line'], ' ', $part['line'], '</label>:<br>
				<div class="centertext">
					<textarea id="on_line', $part['line'], '" name="entire_file[]" cols="80" rows="', $part['lines'] > 14 ? '14' : $part['lines'], '" class="edit_file">', $part['data'], '</textarea>
				</div>';

	echo '
				<input type="submit" name="submit" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled', ' class="save">
				<input type="hidden" name="filename" value="', $context['edit_filename'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>
	</div>';
}

function template_edit_file()
{
	global $context, $settings, $options, $scripturl, $txt;

	if ($context['session_error'])
		echo '
	<div class="errorbox">
		', $txt['error_session_timeout'], '
	</div>';

	//Is this file writeable?
	if (!$context['allow_save'])
		echo '
	<div class="errorbox">
		', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '
	</div>';

	// Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.)
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';sa=edit" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['theme_edit'], ' - ', $context['edit_filename'], '
			</we:cat>
			<div class="windowbg wrc">
				<textarea name="entire_file" id="entire_file" cols="80" rows="20" class="edit_file">', $context['entire_file'], '</textarea><br>
				<input type="submit" name="submit" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled', ' class="save">
				<input type="hidden" name="filename" value="', $context['edit_filename'], '">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>
	</div>
	<br class="clear">';
}

?>