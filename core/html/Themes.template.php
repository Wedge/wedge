<?php
/**
 * Lists available themes, available theme options, and general theme configuration.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

// The main block - for theme administration.
function template_main()
{
	global $context, $txt, $settings;

	echo '
		<form action="<URL>?action=admin;area=theme;sa=admin" method="post" accept-charset="UTF-8">
			<input type="hidden" value="0" name="options[theme_allow]">
			<we:cat>
				<a href="<URL>?action=help;in=themes" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
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

	// !!! @todo: fix this, get a flattened list of all skins.
	foreach ($context['themes'][1]['skins']['skins'] as $th)
		echo '
							<label><input type="checkbox" name="options[known_themes][]" id="options-known_themes_', westr::safe($th['name']), '" value="', $th['dir'], '"', !empty($th['known']) ? ' checked' : '', '> ', $th['name'], '</label><br>';

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
							<option value="0" selected>', $txt['theme_nochange'], '</option>
							<option value="-1">', $txt['theme_forum_default'], '</option>';

	// Same thing, this time for changing the theme of everyone.
	foreach ($context['themes'][1] as $th)
		if (!empty($th['skins']))
			echo wedge_show_skins($th['skins']);

	echo '
						</select>
						&nbsp;<span class="smalltext pick_theme"><a href="<URL>?action=theme;sa=pick;u=0;', $context['session_query'], '">', $txt['theme_select'], '</a></span>
					</dd>
				</dl>
				<div class="right">
					<input type="submit" name="save" value="' . $txt['save'] . '" class="save">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';

	// Link to wedge.org for latest themes and info!
	// !! This doesn't work for now. Probably not ever.

/*
	echo '
		<br>
		<we:cat>
			<a href="<URL>?action=help;in=latest_themes" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
			', $txt['theme_latest'], '
		</we:cat>
		<div class="windowbg wrc">
			<div id="themeLatest">
				', $txt['theme_latest_fetch'], '
			</div>
		</div>
		<br>';
*/

	// Warn them if theme creation isn't possible!
	// !! Actually, nope, it's never possible, since themes no longer exist.

/*
	if (!$context['can_create_new'])
		echo '
		<div class="errorbox">', $txt['theme_install_writable'], '</div>';

	echo '
		<form action="<URL>?action=admin;area=theme;sa=install" method="post" accept-charset="UTF-8" enctype="multipart/form-data" onsubmit="return ask(', JavaScriptEscape($txt['theme_install_new_confirm']), ', e);">
			<we:cat>
				<a href="<URL>?action=help;in=theme_install" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
				', $txt['theme_install'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">';

	// Here's a little box for installing a new theme.
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
*/

	add_js('
	window.weSessionQuery = "', $context['session_query'], '";');

	// !! Remove these lines, and associated deprecated code.
//	window.weThemes_writable = ', $context['can_create_new'] ? 'true' : 'false', ';');
//	if (empty($settings['disable_wedge_js']))
//		add_js_file('<URL>?action=viewremote;filename=latest-themes.js', true);
//	add_js('
//	if (typeof window.weLatestThemes != "undefined")
//		$("#themeLatest").html(window.weLatestThemes);');
}

function template_guest_selector($is_mobile = false)
{
	global $context, $txt;

	$guests = $is_mobile ? 'theme_guests_mobile' : 'theme_guests';

	echo '
					<dt>
						<label for="', $guests, '">', $txt[$guests], ':</label>
					</dt>
					<dd>
						<select name="options[', $guests, ']" id="', $guests, '">';

	add_js('
	$("#known_themes_list").hide();
	$("#known_themes_link").show();');

	// Put an option for each theme in the select box.
	foreach ($context['themes'][1] as $th)
		if (!empty($th['skins']))
			echo wedge_show_skins($th['skins']);

	echo '
						</select>
						&nbsp;<span class="smalltext pick_theme"><a href="<URL>?action=theme;sa=pick;u=-1;', $context['session_query'], '">', $txt['theme_select'], '</a></span>
					</dd>';
}

function template_list_themes()
{
	global $context, $txt;

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
			<span class="floatright"><a href="<URL>?action=admin;area=theme;sa=remove;th=', $th['id'], ';', $context['session_query'], '" onclick="return ask(', JavaScriptEscape($txt['theme_remove_confirm']), ', e);" class="remove_button">', $txt['delete'], '</a></span>';

		echo '
			<strong><a href="<URL>?action=admin;area=theme;th=', $th['id'], ';', $context['session_query'], ';sa=settings">', $th['name'], '</a></strong>', !empty($th['version']) ? ' <em>(' . $th['version'] . ')</em>' : '', '
		</we:title>
		<div class="windowbg wrc">
			<dl class="settings themes_list">
				<dt>', $txt['themeadmin_list_theme_dir'], ':</dt>
				<dd', $th['valid_path'] ? '' : ' class="error"', '>', $th['theme_dir'], $th['valid_path'] ? '' : ' ' . $txt['themeadmin_list_invalid'], '</dd>
				<dt>', $txt['themeadmin_list_theme_url'], ':</dt>
				<dd>', $th['theme_url'], '</dd>
			</dl>
		</div>';
	}

	echo '
		<form action="<URL>?action=admin;area=theme;', $context['session_query'], ';sa=list" method="post" accept-charset="UTF-8">
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

// This template allows for the selection of different themes ;)
function template_pick()
{
	global $context, $txt;

	echo '
	<div id="pick_theme">
		<we:cat>
			', $txt['change_skin'], '
		</we:cat>
		<form action="<URL>?action=skin', $context['specify_member'], ';', $context['session_query'], '" method="post" accept-charset="UTF-8">';

	// Just go through each theme and show its information - thumbnail, etc.
	$th = $context['available_themes'];
	$default_skin = get_default_skin();
	$is_default_skin = $context['skin_actual'] == '';
	$thumbnail = ($default_skin === '/' ? '' : '/' . $default_skin) . '/thumbnail.jpg';
	$thumbnail_href = file_exists(SKINS_DIR . $thumbnail) ? SKINS . $thumbnail : '';

	echo '
			<div style="margin: 8px 0"></div>
			<we:title>
				', $is_default_skin ? '<span style="font-family: sans-serif">&#10004;</span> ' : '', '<a href="<URL>?action=skin', $context['specify_member'], ';skin=;', $context['session_query'], '">', $th['name'], '</a>', $is_default_skin ? ' (' . $txt['current_theme'] . ')' : '', '
			</we:title>
			<div class="', $th['selected'] ? 'windowbg' : 'windowbg2', ' wrc flow_hidden">', $thumbnail_href ? '
				<div class="floatright">
					<a href="<URL>?action=skin' . $context['specify_member'] . ';skin=;' . $context['session_query'] . '" id="theme_thumb_preview_default" title="' . $txt['theme_skin_preview'] . '"><img src="' . $thumbnail_href . '" id="theme_thumb_default" class="padding"></a>
				</div>' : '', '
				<p>
					', $th['description'], '
				</p>
				<p>
					<em class="smalltext">', number_context('skin_users', $th['num_users']), '</em>
				</p>
				<ul style="padding-left: 20px">
					<li><a href="<URL>?action=skin', $context['specify_member'], ';skin;', $context['session_query'], '" id="theme_use_default">', $txt['theme_set'], '</a></li>
					<li><a href="<URL>?action=skin', $context['specify_member'], ';presk;', $context['session_query'], '" id="theme_preview_default">', $txt['theme_skin_preview'], '</a></li>
				</ul>
			</div>';

	if (!empty($th['skins']))
	{
		echo '
			<div style="margin-top: 8px; clear: right">
				<we:title>
					', $txt['theme_skins'], '
				</we:title>';

		template_list_skins($th, false, $th['selected'] ? '2' : '');

		echo '
			</div>';
	}

	echo '
		</form>
	</div>';
}

function template_list_skins(&$th, $is_child = false, $alt_level = '')
{
	global $txt, $context;

	foreach ($th['skins'] as $sty)
	{
		$target = westr::safe($sty['dir']);
		$dir = $sty['dir'] === '/' ? '' : '/' . $sty['dir'];
		$thumbnail_href = file_exists(SKINS_DIR . $dir . '/thumbnail.jpg') ? SKINS . $dir . '/thumbnail.jpg' : '';
		$is_current_skin = $context['skin_actual'] == $sty['dir'];

		echo '
				<fieldset class="wrc windowbg', $alt_level, ' clear_right', $is_current_skin ? ' current_skin' : '', '" style="margin: 12px 8px 8px">
					<legend>
						', $is_current_skin ? '<span style="font-family: sans-serif">&#10004;</span> ' : '', $sty['name'], '
					</legend>', $thumbnail_href ? '
					<div class="floatright">
						<a href="<URL>?action=skin' . $context['specify_member'] . ';skin=' . $target . ';' . $context['session_query'] . '" id="theme_thumb_preview_' . $target . '" title="' . $txt['theme_skin_preview'] . '"><img src="' . $thumbnail_href . '" id="theme_thumb_' . $target . '" class="padding"' . ($is_child ? ' style="max-width: 75px"' : '') . '></a>
					</div>' : '', '
					<p>', $sty['comment'], '</p>';

		if (!empty($sty['num_users']))
			echo '
					<p>
						<em class="smalltext">', number_context('skin_users', $sty['num_users']), '</em>
					</p>';

		if (!$is_current_skin)
			echo '
					<ul style="padding-left: 20px">
						<li><a href="<URL>?action=skin', $context['specify_member'], ';skin=', $target, ';', $context['session_query'], '" id="theme_use_', $target, '_', '">', $txt['theme_skin_set'], '</a></li>
						<li><a href="<URL>?action=skin', $context['specify_member'], ';presk=', $target, ';', $context['session_query'], '" id="theme_preview_', $target, '_', '">', $txt['theme_skin_preview'], '</a></li>
					</ul>';

		if (!empty($sty['skins']))
			template_list_skins($sty, true, $alt_level ? '' : '2');

		echo '
				</fieldset>';
	}
}

// Okay, that theme was installed successfully!
function template_installed()
{
	global $context, $txt;

	// Not much to show except a link back...
	echo '
		<we:cat>
			', $context['page_title'], '
		</we:cat>
		<div class="windowbg wrc">
			<p>
				', $context['installed_theme']['name'], ' ', $txt['theme_installed_message'], '
			</p>
			<p>
				<a href="<URL>?action=admin;area=theme;sa=admin;', $context['session_query'], '">', $txt['back'], '</a>
			</p>
		</div>';
}

function template_edit_list()
{
	global $context, $txt;

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
			<a href="<URL>?action=admin;area=theme;th=', $th['id'], ';', $context['session_query'], ';sa=edit">', $th['name'], '</a>', !empty($th['version']) ? '
			<em>(' . $th['version'] . ')</em>' : '', '
		</we:title>
		<div class="windowbg', $alternate ? '' : '2', ' wrc">
			<ul class="reset">
				<li><a href="<URL>?action=admin;area=theme;th=', $th['id'], ';', $context['session_query'], ';sa=edit">', $txt['themeadmin_edit_browse'], '</a></li>', $th['can_edit_style'] ? '
				<li><a href="<URL>?action=admin;area=theme;th=' . $th['id'] . ';' . $context['session_query'] . ';sa=edit;directory=skins">' . $txt['themeadmin_edit_style'] . '</a></li>' : '', '
				<li><a href="<URL>?action=admin;area=theme;th=', $th['id'], ';', $context['session_query'], ';sa=copy">', $txt['themeadmin_edit_copy_template'], '</a></li>
			</ul>
		</div>';
	}
}

function template_copy_template()
{
	global $context, $txt;

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
			echo '<a href="<URL>?action=admin;area=theme;', $context['session_query'], ';sa=copy;template=', $template['value'], '" onclick="return ask(', JavaScriptEscape($template['already_exists'] ? $txt['themeadmin_edit_overwrite_confirm'] : $txt['themeadmin_edit_copy_confirm']), ', e);">', $txt['themeadmin_edit_do_copy'], '</a>';
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
	global $context, $txt;

	echo '
		<table class="table_grid w100 cs0">
		<thead>
			<tr class="catbg">
				<th class="left w50">', $txt['themeadmin_edit_filename'], '</th>
				<th style="width: 35%">', $txt['themeadmin_edit_modified'], '</th>
				<th style="width: 15%">', $txt['themeadmin_edit_size'], '</th>
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
	global $context, $txt;

	if ($context['session_error'])
		echo '
		<div class="errorbox">
			', $txt['error_session_timeout'], '
		</div>';

	add_js('
	var we_assets_url = ', JavaScriptEscape(ASSETS_URL), ';
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
			url + (url.indexOf("?") == -1 ? "?" : ";") + "theme=', dirname($context['edit_filename']), '" + anchor,
			function (response)
			{
				previewData = response;
				$("#css_preview_box").show();

				// Revert to the theme they actually use.
				$.get(weUrl("action=admin;area=theme;sa=edit;theme=', we::$user['skin'], ';preview;" + $.now()));

				refreshPreviewCache = null;
				refreshPreview(false);
			}
		);
	}
	navigatePreview(we_scripturl);

	function refreshPreview(check)
	{
		var identical = document.forms.stylesheetForm.entire_file.value.replace(/url\([./]+images/gi, "url(" + we_assets_url) == refreshPreviewCache;

		// Don\'t reflow the whole thing if nothing changed!!
		if (check && identical)
			return;
		refreshPreviewCache = document.forms.stylesheetForm.entire_file.value;

		// Replace the paths for images.
		refreshPreviewCache = refreshPreviewCache.replace(/url\([./]+images/gi, "url(" + we_assets_url);

		// Try to do it without a complete reparse.
		if (identical)
		{
			try
			{
			');

	if (we::is('ie[-10]'))
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
			preview_sheet = preview_sheet.replace(/url\([./]+images/gi, "url(" + we_assets_url);
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
		<form action="<URL>?action=admin;area=theme;sa=edit" method="post" accept-charset="UTF-8" name="stylesheetForm" id="stylesheetForm">
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
	global $context, $txt;

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
		<form action="<URL>?action=admin;area=theme;sa=edit" method="post" accept-charset="UTF-8">
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
	global $context, $txt;

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
		<form action="<URL>?action=admin;area=theme;sa=edit" method="post" accept-charset="UTF-8">
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
