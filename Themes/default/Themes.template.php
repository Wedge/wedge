<?php
/**
 * Wedge
 *
 * Lists available themes, available theme options, and general theme configuration.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// The main block - for theme administration.
function template_main()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	echo '
		<form action="', $scripturl, '?action=admin;area=theme;sa=admin" method="post" accept-charset="UTF-8">
			<input type="hidden" value="0" name="options[theme_allow]">
			<we:cat>
				<a href="', $scripturl, '?action=help;in=themes" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
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
						<input type="checkbox" name="options[theme_allow]" id="options-theme_allow" value="1"', !empty($settings['theme_allow']) ? ' checked' : '', '>
					</dd>
					<dt>
						<label for="known_themes_list">', $txt['themeadmin_selectable'], '</label>:
					</dt>
					<dd>
						<div id="known_themes_list">';

	foreach ($context['themes'] as $th)
		echo '
							<label><input type="checkbox" name="options[known_themes][]" id="options-known_themes_', $th['id'], '" value="', $th['id'], '"', !empty($th['known']) ? ' checked' : '', '> ', $th['name'], '</label><br>';

	echo '
						</div>
						<a href="#" onclick="$(\'#known_themes_list\').show(); $(\'#known_themes_link\').hide(); return false;" id="known_themes_link" class="hide">[ ', $txt['themeadmin_themelist_link'], ' ]</a>
					</dd>';

	template_guest_selector(false);
	template_guest_selector(true);

	echo '
					<dt>
						<label for="theme_reset">', $txt['theme_reset'], '</label>:
					</dt>
					<dd>
						<select name="theme_reset" id="theme_reset">
							<option value="-1" selected>', $txt['theme_nochange'], '</option>
							<option value="0">', $txt['theme_forum_default'], '</option>';

	// Same thing, this time for changing the theme of everyone.
	foreach ($context['themes'] as $th)
	{
		echo '
							<option value="', $th['id'], '">', $th['name'], '</option>';
		if (!empty($th['skins']))
			echo wedge_show_skins($th, $th['skins']);
	}

	echo '
						</select>
						<span class="smalltext pick_theme"><a href="', $scripturl, '?action=theme;sa=pick;u=0;', $context['session_query'], '">', $txt['theme_select'], '</a></span>
					</dd>
				</dl>
				<div class="right">
					<input type="submit" name="save" value="' . $txt['save'] . '" class="save">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';

	// Link to wedge.org for latest themes and info!
	echo '
		<br>
		<we:cat>
			<a href="', $scripturl, '?action=help;in=latest_themes" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
			', $txt['theme_latest'], '
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
		<form action="', $scripturl, '?action=admin;area=theme;sa=install" method="post" accept-charset="UTF-8" enctype="multipart/form-data" onsubmit="return ask(', JavaScriptEscape($txt['theme_install_new_confirm']), ', e);">
			<we:cat>
				<a href="', $scripturl, '?action=help;in=theme_install" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
				', $txt['theme_install'], '
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
						<input name="theme_dir" id="theme_dir" value="', $context['new_theme_dir'], '" size="40" style="width: 70%">
					</dd>';

	if ($context['can_create_new'])
		echo '
					<dt>
						<label for="copy">', $txt['theme_install_new'], ':</label>
					</dt>
					<dd>
						<input name="copy" id="copy" value="', $context['new_theme_name'], '" size="40">
					</dd>';

	echo '
				</dl>
				<div class="right">
					<input type="submit" name="save" value="', $txt['theme_install_go'], '" class="submit">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';

	add_js('
	window.weSessionQuery = "', $context['session_query'], '";
	window.weThemes_writable = ', $context['can_create_new'] ? 'true' : 'false', ';');

	if (empty($settings['disable_wedge_js']))
		add_js_file($scripturl . '?action=viewremote;filename=latest-themes.js', true);

	add_js('
	if (typeof window.weLatestThemes != "undefined")
		$("#themeLatest").html(window.weLatestThemes);');
}

function template_guest_selector($is_mobile = false)
{
	global $context, $settings, $txt;

	$guests = $is_mobile ? 'guests_mobile' : 'guests';

	echo '
					<dt>
						<label for="theme_', $guests, '">', $txt['theme_' . $guests], ':</label>
					</dt>
					<dd>
						<select name="options[theme_', $guests, ']" id="theme_', $guests, '">
							';

	add_js('
	$("#known_themes_list").hide();
	$("#known_themes_link").show();');

	$skin = empty($settings['theme_skin_' . $guests]) ? ($is_mobile ? 'skins/Wireless' : 'skins') : $settings['theme_skin_' . $guests];

	// Put an option for each theme in the select box.
	foreach ($context['themes'] as $th)
	{
		echo '<option value="', $th['id'], '"', $settings['theme_' . $guests] == $th['id'] && $skin == 'skins' ? ' selected' : '', '>', $th['name'], '</option>';
		if (!empty($th['skins']))
			echo wedge_show_skins($th, $th['skins'], $settings['theme_' . $guests], $skin);
	}

	echo '
						</select>
						<span class="smalltext pick_theme"><a href="<URL>?action=theme;sa=pick;u=-1;', $context['session_query'], '">', $txt['theme_select'], '</a></span>
					</dd>';
}

function template_list_themes()
{
	global $context, $theme, $options, $scripturl, $txt;

	echo '
		<we:cat>
			', $txt['themeadmin_list_heading'], '
		</we:cat>
		<div class="information">
			', $txt['themeadmin_list_tip'], '
		</div>';

	// Show each theme.... with X for delete and a link to settings.
	foreach ($context['themes'] as $th)
	{
		echo '
		<we:title>';

		// You *cannot* delete the default theme. It's important!
		if ($th['id'] != 1)
			echo '
			<span class="floatright"><a href="', $scripturl, '?action=admin;area=theme;sa=remove;th=', $th['id'], ';', $context['session_query'], '" onclick="return ask(', JavaScriptEscape($txt['theme_remove_confirm']), ', e);" class="remove_button">', $txt['delete'], '</a></span>';

		echo '
			<strong><a href="', $scripturl, '?action=admin;area=theme;th=', $th['id'], ';', $context['session_query'], ';sa=settings">', $th['name'], '</a></strong>', !empty($th['version']) ? ' <em>(' . $th['version'] . ')</em>' : '', '
		</we:title>
		<div class="windowbg wrc">
			<dl class="settings themes_list">
				<dt>', $txt['themeadmin_list_theme_dir'], ':</dt>
				<dd', $th['valid_path'] ? '' : ' class="error"', '>', $th['theme_dir'], $th['valid_path'] ? '' : ' ' . $txt['themeadmin_list_invalid'], '</dd>
				<dt>', $txt['themeadmin_list_theme_url'], ':</dt>
				<dd>', $th['theme_url'], '</dd>
				<dt>', $txt['themeadmin_list_images_url'], ':</dt>
				<dd>', $th['images_url'], '</dd>
			</dl>
		</div>';
	}

	echo '
		<form action="', $scripturl, '?action=admin;area=theme;', $context['session_query'], ';sa=list" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['themeadmin_list_reset'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<label for="reset_dir">', $txt['themeadmin_list_reset_dir'], '</label>:
					</dt>
					<dd>
						<input name="reset_dir" id="reset_dir" value="', $context['reset_dir'], '" size="40" style="width: 80%">
					</dd>
					<dt>
						<label for="reset_url">', $txt['themeadmin_list_reset_url'], '</label>:
					</dt>
					<dd>
						<input name="reset_url" id="reset_url" value="', $context['reset_url'], '" size="40" style="width: 80%">
					</dd>
				</dl>
				<input type="submit" name="save" value="', $txt['themeadmin_list_reset_go'], '" class="submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>';
}

function template_set_settings()
{
	global $context, $theme, $options, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=admin;area=theme;sa=settings;th=', $context['theme_settings']['theme_id'], '" method="post" accept-charset="UTF-8">
			<we:title>
				<a href="', $scripturl, '?action=help;in=theme_settings" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
				', $txt['theme_settings'], ' - ', $context['theme_settings']['name'], '
			</we:title>';

	// !!! Why can't I edit the default theme popup.
	if ($context['theme_settings']['theme_id'] != 1)
		echo '
			<we:cat>
				<img src="', $theme['images_url'], '/icons/config_sm.gif">
				', $txt['theme_edit'], '
			</we:cat>
			<div class="windowbg wrc">
				<ul class="reset">
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';', $context['session_query'], ';sa=edit;filename=index.template.php">', $txt['theme_edit_index'], '</a>
					</li>
					<li>
						<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_settings']['theme_id'], ';', $context['session_query'], ';sa=edit;directory=skins">', $txt['theme_edit_style'], '</a>
					</li>
				</ul>
			</div>';

	echo '
			<we:cat>
				<img src="', $theme['images_url'], '/icons/config_sm.gif">
				', $txt['theme_url_config'], '
			</we:cat>
			<div class="windowbg2 wrc">
				<dl class="settings">
					<dt>
						<label for="theme_name">', $txt['actual_theme_name'], '</label>
					</dt>
					<dd>
						<input id="theme_name" name="options[name]" value="', $context['theme_settings']['name'], '" size="32">
					</dd>
					<dt>
						<label for="theme_url">', $txt['actual_theme_url'], '</label>
					</dt>
					<dd>
						<input id="theme_url" name="options[theme_url]" value="', $context['theme_settings']['actual_theme_url'], '" size="50" style="max-width: 100%; width: 50ex">
					</dd>
					<dt>
						<label for="images_url">', $txt['actual_images_url'], '</label>
					</dt>
					<dd>
						<input id="images_url" name="options[images_url]" value="', $context['theme_settings']['actual_images_url'], '" size="50" style="max-width: 100%; width: 50ex">
					</dd>
					<dt>
						<label for="theme_dir">', $txt['actual_theme_dir'], '</label>
					</dt>
					<dd>
						<input id="theme_dir" name="options[theme_dir]" value="', $context['theme_settings']['actual_theme_dir'], '" size="50" style="max-width: 100%; width: 50ex">
					</dd>
				</dl>
			</div>
			<we:cat>
				<img src="', $theme['images_url'], '/icons/config_sm.gif">
				', $txt['theme_options'], '
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
						<input name="', !empty($setting['default']) ? 'default_' : '', 'options[', $setting['id'], ']" id="', $setting['id'], '" value="', $setting['value'], '" size="', $setting['type'] == 'number' ? '5' : (empty($setting['size']) ? '40' : $setting['size']), '">
					</dd>';
		}
	}

	echo '
				</dl>
				<div class="right">
					<input type="submit" name="save" value="', $txt['save'], '" class="save">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
}

// This template allows for the selection of different themes ;)
function template_pick()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	echo '
	<div id="pick_theme">
		<we:cat>
			', $txt['change_skin'], '
		</we:cat>
		<form action="', $scripturl, '?action=skin', $context['specify_member'], ';', $context['session_query'], '" method="post" accept-charset="UTF-8">';

	// Just go through each theme and show its information - thumbnail, etc.
	foreach ($context['available_themes'] as $th)
	{
		$id_extra = $settings['theme_guests'] === $th['id'] ? '_' . base64_encode($settings['theme_skin_guests']) : '';
		$thumbnail = '/' . (empty($th['id']) || $id_extra ? $settings['theme_skin_guests'] : 'skins') . '/thumbnail.jpg';
		$thumbnail_href = file_exists($th['theme_dir'] . $thumbnail) ? $th['theme_url'] . $thumbnail : '';

		echo '
			<div style="margin: 8px 0"></div>
			<we:title>
				', $context['current_theme'] == $th['id'] ? '<span style="font-family: sans-serif">&#10004;</span> ' : '', '<a href="', $scripturl, '?action=skin', $context['specify_member'], ';th=', $th['id'], $id_extra, ';', $context['session_query'], '">', $th['name'], '</a>', $context['current_theme'] == $th['id'] ? '
				(' . $txt['current_theme'] . ')' : '', '
			</we:title>
			<div class="', $th['selected'] ? 'windowbg' : 'windowbg2', ' wrc flow_hidden">', $thumbnail_href ? '
				<div class="floatright">
					<a href="' . $scripturl . '?action=skin' . $context['specify_member'] . ';theme=' . $th['id'] . $id_extra . ';' . $context['session_query'] . '" id="theme_thumb_preview_' . $th['id'] . '" title="' . $txt['theme_preview'] . '"><img src="' . $thumbnail_href . '" id="theme_thumb_' . $th['id'] . '" class="padding"></a>
				</div>' : '', '
				<p>
					', $th['description'], '
				</p>
				<p>
					<em class="smalltext">', number_context('theme_users', $th['num_users']), '</em>
				</p>
				<ul style="padding-left: 20px">
					<li><a href="', $scripturl, '?action=skin', $context['specify_member'], ';th=', $th['id'], $id_extra, ';', $context['session_query'], '" id="theme_use_', $th['id'], '">', $txt['theme_set'], '</a></li>
					<li><a href="', $scripturl, '?action=skin', $context['specify_member'], ';theme=', $th['id'], $id_extra, ';', $context['session_query'], '" id="theme_preview_', $th['id'], '">', $txt['theme_preview'], '</a></li>
				</ul>';

		if ($th['id'] !== 0 && !empty($th['skins']))
		{
			echo '
				<div style="margin-top: 8px; clear: right">
					<we:title>
						', $txt['theme_skins'], '
					</we:title>
				</div>';

			template_list_skins($th, $th['id'], '', '', false, $th['selected'] ? '2' : '');
		}

		echo '
			</div>';
	}

	echo '
		</form>
	</div>';
}

function template_list_skins(&$th, $theme_id, $theme_url = '', $theme_dir = '', $is_child = false, $alt_level = '')
{
	global $txt, $context, $scripturl, $settings;

	if (empty($theme_url))
	{
		$theme_dir = $th['theme_dir'];
		$theme_url = $th['theme_url'];
	}

	foreach ($th['skins'] as $sty)
	{
		$target = $theme_id . '_' . base64_encode($sty['dir']);
		$thumbnail_href = file_exists($theme_dir . '/' . $sty['dir'] . '/thumbnail.jpg') ? $theme_url . '/' . $sty['dir'] . '/thumbnail.jpg' : '';
		$is_current_skin = $context['current_skin'] == $sty['dir'] && ($context['current_theme'] == $theme_id || (empty($context['current_theme']) && $settings['theme_guests'] == $theme_id));

		echo '
				<fieldset class="wrc windowbg', $alt_level, ' clear_right', $is_current_skin ? ' current_skin' : '', '" style="margin: 12px 8px 8px">
					<legend>
						', $is_current_skin ? '<span style="font-family: sans-serif">&#10004;</span> ' : '', $sty['name'], '
					</legend>', $thumbnail_href ? '
					<div class="floatright">
						<a href="' . $scripturl . '?action=skin' . $context['specify_member'] . ';theme=' . $target . ';' . $context['session_query'] . '" id="theme_thumb_preview_' . $target . '" title="' . $txt['theme_preview'] . '"><img src="' . $thumbnail_href . '" id="theme_thumb_' . $target . '" class="padding"' . ($is_child ? ' style="max-width: 75px"' : '') . '></a>
					</div>' : '', '
					<p>', $sty['comment'], '</p>';

		if (!empty($sty['num_users']))
			echo '
					<p>
						<em class="smalltext">', number_context('theme_users', $sty['num_users']), '</em>
					</p>';

		if (!$is_current_skin)
			echo '
					<ul style="padding-left: 20px">
						<li><a href="', $scripturl, '?action=skin', $context['specify_member'], ';th=', $target, ';', $context['session_query'], '" id="theme_use_', $target, '_', '">', $txt['theme_skin_set'], '</a></li>
						<li><a href="', $scripturl, '?action=skin', $context['specify_member'], ';theme=', $target, ';', $context['session_query'], '" id="theme_preview_', $target, '_', '">', $txt['theme_skin_preview'], '</a></li>
					</ul>';

		if (!empty($sty['skins']))
			template_list_skins($sty, $theme_id, $theme_url, $theme_dir, true, $alt_level ? '' : '2');

		echo '
				</fieldset>';
	}
}

// Okay, that theme was installed successfully!
function template_installed()
{
	global $context, $theme, $options, $scripturl, $txt;

	// Not much to show except a link back...
	echo '
		<we:cat>
			', $context['page_title'], '
		</we:cat>
		<div class="windowbg wrc">
			<p>
				<a href="', $scripturl, '?action=admin;area=theme;sa=settings;th=', $context['installed_theme']['id'], ';', $context['session_query'], '">', $context['installed_theme']['name'], '</a> ', $txt['theme_installed_message'], '
			</p>
			<p>
				<a href="', $scripturl, '?action=admin;area=theme;sa=admin;', $context['session_query'], '">', $txt['back'], '</a>
			</p>
		</div>';
}

function template_edit_list()
{
	global $context, $theme, $options, $scripturl, $txt;

	echo '
		<we:cat>
			', $txt['themeadmin_edit_title'], '
		</we:cat>';

	$alternate = false;

	foreach ($context['themes'] as $th)
	{
		$alternate = !$alternate;

		echo '
		<we:title>
			<a href="', $scripturl, '?action=admin;area=theme;th=', $th['id'], ';', $context['session_query'], ';sa=edit">', $th['name'], '</a>', !empty($th['version']) ? '
			<em>(' . $th['version'] . ')</em>' : '', '
		</we:title>
		<div class="windowbg', $alternate ? '' : '2', ' wrc">
			<ul class="reset">
				<li><a href="', $scripturl, '?action=admin;area=theme;th=', $th['id'], ';', $context['session_query'], ';sa=edit">', $txt['themeadmin_edit_browse'], '</a></li>', $th['can_edit_style'] ? '
				<li><a href="' . $scripturl . '?action=admin;area=theme;th=' . $th['id'] . ';' . $context['session_query'] . ';sa=edit;directory=skins">' . $txt['themeadmin_edit_style'] . '</a></li>' : '', '
				<li><a href="', $scripturl, '?action=admin;area=theme;th=', $th['id'], ';', $context['session_query'], ';sa=copy">', $txt['themeadmin_edit_copy_template'], '</a></li>
			</ul>
		</div>';
	}
}

function template_copy_template()
{
	global $context, $theme, $options, $scripturl, $txt;

	echo '
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
			echo '<a href="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';', $context['session_query'], ';sa=copy;template=', $template['value'], '" onclick="return ask(', JavaScriptEscape($template['already_exists'] ? $txt['themeadmin_edit_overwrite_confirm'] : $txt['themeadmin_edit_copy_confirm']), ', e);">', $txt['themeadmin_edit_do_copy'], '</a>';
		else
			echo $txt['themeadmin_edit_no_copy'];

		echo '
					</span>
				</li>';
	}

	echo '
			</ul>
		</div>';
}

function template_edit_browse()
{
	global $context, $theme, $options, $scripturl, $txt;

	echo '
		<table class="table_grid w100 cs0">
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
			echo '<a href="', $file['href'], '"', $file['is_template'] ? ' style="font-weight: bold"' : '', '>', $file['filename'], '</a>';

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
		</table>';
}

// Wanna edit the stylesheet?
function template_edit_style()
{
	global $context, $theme, $options, $scripturl, $txt;

	if ($context['session_error'])
		echo '
		<div class="errorbox">
			', $txt['error_session_timeout'], '
		</div>';

	add_js('
	var previewData = "", previewTimeout, refreshPreviewCache;
	var editFilename = ', JavaScriptEscape($context['edit_filename']), ';

	// Load up a page, but apply our stylesheet.
	function navigatePreview(url)
	{
		var anchor = "";
		if (url.indexOf("#") != -1)
		{
			anchor = url.slice(url.indexOf("#"));
			url = url.slice(0, url.indexOf("#"));
		}

		$.get(
			url + (url.indexOf("?") == -1 ? "?" : ";") + "theme=', $context['theme_id'], '_', base64_encode(dirname($context['edit_filename'])), '" + anchor,
			function (response)
			{
				previewData = response;
				$("#css_preview_box").show();

				// Revert to the theme they actually use.
				$.get(weUrl("action=admin;area=theme;sa=edit;theme=', $context['theme_id'], !empty(we::$user['skin']) ? '_' . base64_encode(we::$user['skin']) : '', ';preview;" + $.now()));

				refreshPreviewCache = null;
				refreshPreview(false);
			}
		);
	}
	navigatePreview(we_scripturl);

	function refreshPreview(check)
	{
		var identical = document.forms.stylesheetForm.entire_file.value.replace(/url\([./]+images/gi, "url(" + we_theme_url + "/images") == refreshPreviewCache;

		// Don\'t reflow the whole thing if nothing changed!!
		if (check && identical)
			return;
		refreshPreviewCache = document.forms.stylesheetForm.entire_file.value;

		// Replace the paths for images.
		refreshPreviewCache = refreshPreviewCache.replace(/url\([./]+images/gi, "url(" + we_theme_url + "/images");

		// Try to do it without a complete reparse.
		if (identical)
		{
			try
			{
			');

	if (we::is('ie'))
		add_js('
				for (var j = 0, sheets = frames["css_preview_box"].document.styleSheets; j < sheets.length; j++)
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
			preview_sheet = preview_sheet.replace(/url\([./]+images/gi, "url(" + we_theme_url + "/images");
			data = data.replace(stylesheetMatch, "<style id=\"css_preview_sheet\">" + preview_sheet + "<" + "/style>");

			frames["css_preview_box"].document.open();
			frames["css_preview_box"].document.write(data);
			frames["css_preview_box"].document.close();

			// Next, fix all its links so we can handle them and reapply the new CSS!
			frames["css_preview_box"].onload = function ()
			{
				for (var i = 0, fixLinks = frames["css_preview_box"].document.getElementsByTagName("a"); i < fixLinks.length; i++)
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
	}');

	echo '
		<iframe id="css_preview_box" name="css_preview_box" src="about:blank" style="display: none; margin-bottom: 2ex; border: 1px solid black; width: 99%; height: 400px" seamless></iframe>';

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
				<textarea name="entire_file" cols="80" rows="20" style="', we::is('ie8') ? 'width: 635px; max-width: 96%; min-width: 96%' : 'width: 96%', '; font-family: monospace; margin-top: 1ex; white-space: pre">', $context['entire_file'], '</textarea><br>
				<div class="right" style="margin: 4px 5% 0">
					<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled', ' style="margin-top: 1ex" class="save">
					<input type="button" value="', $txt['themeadmin_edit_preview'], '" onclick="refreshPreview(false);">
				</div>
			</div>
			<input type="hidden" name="filename" value="', $context['edit_filename'], '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
}

// This edits the template...
function template_edit_template()
{
	global $context, $theme, $options, $scripturl, $txt;

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
				<div class="center">
					<textarea id="on_line', $part['line'], '" name="entire_file[]" cols="80" rows="', $part['lines'] > 14 ? '14' : $part['lines'], '" class="edit_file">', $part['data'], '</textarea>
				</div>';

	echo '
				<div class="right" style="margin: 8px 5% 0">
					<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled', ' class="save">
					<input type="hidden" name="filename" value="', $context['edit_filename'], '">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</div>
		</form>';
}

function template_edit_file()
{
	global $context, $theme, $options, $scripturl, $txt;

	if ($context['session_error'])
		echo '
		<div class="errorbox">
			', $txt['error_session_timeout'], '
		</div>';

	// Is this file writeable?
	if (!$context['allow_save'])
		echo '
		<div class="errorbox">
			', $txt['theme_edit_no_save'], ': ', $context['allow_save_filename'], '
		</div>';

	// Just show a big box.... gray out the Save button if it's not saveable... (ie. not 777.)
	echo '
		<form action="', $scripturl, '?action=admin;area=theme;th=', $context['theme_id'], ';sa=edit" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['theme_edit'], ' - ', $context['edit_filename'], '
			</we:cat>
			<div class="windowbg wrc">
				<textarea name="entire_file" id="entire_file" cols="80" rows="20" class="edit_file">', $context['entire_file'], '</textarea><br>
				<div class="right" style="margin: 8px 5% 0">
					<input type="submit" name="save" value="', $txt['theme_edit_save'], '"', $context['allow_save'] ? '' : ' disabled', ' class="save">
					<input type="hidden" name="filename" value="', $context['edit_filename'], '">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</div>
		</form>';
}
