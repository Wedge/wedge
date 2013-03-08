<?php
/**
 * Wedge
 *
 * The main administration for managing language files.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Add a new language
function template_add_language()
{
	global $context, $theme, $options, $txt;

	echo '
		<form action="<URL>?action=admin;area=languages;sa=add;', $context['session_query'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['add_language'], '
			</we:cat>
			<div class="windowbg wrc">
				<fieldset>
					<legend>', $txt['add_language_wedge'], '</legend>
					<label class="smalltext">', $txt['add_language_wedge_browse'], '</label>
					<input name="we_add" size="40" value="', !empty($context['we_search_term']) ? $context['we_search_term'] : '', '">';

	if (!empty($context['wedge_error']))
		echo '
					<div class="smalltext error">', $txt['add_language_error_' . $context['wedge_error']], '</div>';

	echo '
				</fieldset>
				<div class="right">', we::is('ie') ? '
					<input name="ie_fix" class="hide"> ' : '', '
					<input type="submit" name="we_add_sub" value="', $txt['search'], '" class="submit">
				</div>
			</div>';

	// Had some results?
	if (!empty($context['wedge_languages']))
	{
		echo '
			<div class="information">', $txt['add_language_wedge_found'], '</div>

			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg">
						<th class="first_th" scope="col">', $txt['name'], '</th>
						<th scope="col">', $txt['add_language_wedge_desc'], '</th>
						<th scope="col">', $txt['add_language_wedge_version'], '</th>
						<th class="last_th" scope="col">', $txt['add_language_wedge_install'], '</th>
					</tr>
				</thead>
				<tbody>';

		foreach ($context['wedge_languages'] as $language)
			echo '
					<tr class="windowbg2 left">
						<td>', $language['name'], '</td>
						<td>', $language['description'], '</td>
						<td>', $language['version'], '</td>
						<td><a href="', $language['link'], '">', $txt['add_language_wedge_install'], '</a></td>
					</tr>';

		echo '
				</tbody>
			</table>';
	}

	echo '
		</form>';
}

// Download a new language file?
function template_download_language()
{
	global $context, $theme, $options, $txt, $settings;

	// Actually finished?
	if (!empty($context['install_complete']))
	{
		echo '
		<we:cat>
			', $txt['languages_download_complete'], '
		</we:cat>
		<div class="windowbg wrc">
			', $context['install_complete'], '
		</div>';
		return;
	}

	// An error?
	if (!empty($context['error_message']))
		echo '
		<div id="errorbox">
			<p>', $context['error_message'], '</p>
		</div>';

	// Provide something of an introduction...
	echo '
		<form action="<URL>?action=admin;area=languages;sa=downloadlang;did=', $context['download_id'], ';', $context['session_query'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['languages_download'], '
			</we:cat>
			<div class="windowbg wrc">
				<p>
					', $txt['languages_download_note'], '
				</p>
				<div class="smalltext">
					', $txt['languages_download_info'], '
				</div>
			</div>';

	// Show the main files.
	template_show_list('lang_main_files_list');

	// Now, all the images and the likes, hidden via javascript 'cause there are so fecking many.
	echo '
			<br>
			<we:title>
				', $txt['languages_download_theme_files'], '
			</we:title>
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg">
						<th class="first_th" scope="col">
							', $txt['languages_download_filename'], '
						</th>
						<th scope="col" style="width: 100px">
							', $txt['languages_download_writable'], '
						</th>
						<th scope="col" style="width: 100px">
							', $txt['languages_download_exists'], '
						</th>
						<th class="last_th" scope="col" style="width: 50px">
							', $txt['languages_download_copy'], '
						</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['files']['images'] as $th => $group)
	{
		$count = 0;
		echo '
				<tr class="titlebg">
					<td colspan="4">
						<div class="sortselect" id="toggle_image_', $th, '"></div>&nbsp;', isset($context['theme_names'][$th]) ? $context['theme_names'][$th] : $th, '
					</td>
				</tr>';

		$alternate = false;
		foreach ($group as $file)
		{
			echo '
				<tr class="windowbg', $alternate ? '2' : '', '" id="', $th, '-', $count++, '">
					<td>
						<strong>', $file['name'], '</strong>
						<div class="smalltext">', $txt['languages_download_dest'], ': ', $file['destination'], '</div>
					</td>
					<td>
						<span style="color: ', $file['writable'] ? 'green' : 'red', '">', $file['writable'] ? $txt['yes'] : $txt['no'], '</span>
					</td>
					<td>
						', $file['exists'] ? ($file['exists'] == 'same' ? $txt['languages_download_exists_same'] : $txt['languages_download_exists_different']) : $txt['no'], '
					</td>
					<td>
						<input type="checkbox" name="copy_file[]" value="', $file['generaldest'], '"', $file['default_copy'] ? ' checked' : '', '>
					</td>
				</tr>';
			$alternate = !$alternate;
		}
	}

	echo '
			</tbody>
			</table>';

	// Do we want some FTP baby?
	if (!empty($context['still_not_writable']))
	{


		echo '
			<we:cat>
				', $txt['package_ftp_necessary'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt
						<label for="ftp_server">', $txt['package_ftp_server'], ':</label>
					</dt>
					<dd>
						<div class="floatright" style="margin-right: 1px"><label style="padding-top: 2px; padding-right: 2ex">', $txt['package_ftp_port'], ':&nbsp;<input size="3" name="ftp_port" value="', isset($context['package_ftp']['port']) ? $context['package_ftp']['port'] : (isset($settings['package_port']) ? $settings['package_port'] : '21'), '"></label></div>
						<input size="30" name="ftp_server" id="ftp_server" value="', isset($context['package_ftp']['server']) ? $context['package_ftp']['server'] : (isset($settings['package_server']) ? $settings['package_server'] : 'localhost'), '" style="width: 70%">
					</dd>

					<dt>
						<label for="ftp_username">', $txt['package_ftp_username'], ':</label>
					</dt>
					<dd>
						<input size="42" name="ftp_username" id="ftp_username" value="', isset($context['package_ftp']['username']) ? $context['package_ftp']['username'] : (isset($settings['package_username']) ? $settings['package_username'] : ''), '" style="width: 99%">
					</dd>

					<dt>
						<label for="ftp_password">', $txt['package_ftp_password'], ':</label>
					</dt>
					<dd>
						<input type="password" size="42" name="ftp_password" id="ftp_password" style="width: 99%">
					</dd>

					<dt>
						<label for="ftp_path">', $txt['package_ftp_path'], ':</label>
					</dt>
					<dd>
						<input size="42" name="ftp_path" id="ftp_path" value="', $context['package_ftp']['path'], '" style="width: 99%">
					</dd>
				</dl>
			</div>';
	}

	// Install?
	echo '
			<div class="right padding">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" name="do_install" value="', $txt['add_language_wedge_install'], '" class="submit">
			</div>
		</form>';

	// The javascript for expanding and collapsing sections.
	// Each theme gets its own handler.
	foreach ($context['files']['images'] as $th => $group)
	{
		$count = 0;

		add_js('
	new weToggle({
		isCollapsed: true,
		aSwapContainers: [');

		foreach ($group as $file)
			add_js('
			', JavaScriptEscape($th . '-' . $count++), ',');

		add_js('
			null
		],
		aSwapImages: [\'toggle_image_', $th, '\']
	});');
	}
}

// Edit some language entries?
function template_modify_language_list()
{
	global $context, $theme, $options, $txt;

	echo '
		<we:cat>
			', sprintf($txt['edit_languages_specific'], $context['languages'][$context['lang_id']]['name']), '
		</we:cat>
		<div class="two-columns">
			<we:block class="windowbg2" header="', $txt['language_edit_default'], '">';

	foreach ($context['language_files']['default'] as $block)
	{
		echo '
				<h6>', $block['name'], '</h6>
				<ul>';
		foreach ($block['files'] as $id => $name)
			echo '
					<li><a href="<URL>?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], ';tfid=', urlencode(1 . '|' . $id), '">', $name, '</a></li>';
		echo '
				</ul>';
	}

	echo '
			</we:block>
		</div>
		<div class="two-columns">
			<we:block class="windowbg" header="', $txt['language_edit_search'], '">
				<form>
					<input type="search" name="search" value="" class="search"><br>';

	foreach (array('plugins', 'themes') as $item)
		if (!empty($context['language_files'][$item]))
			echo '
					<label><input type="checkbox" name="include_', $item, '" value="1" checked> ', $txt['language_edit_search_' . $item], '</input></label><br>';

	echo '
					<select name="search_type">';
	foreach (array('both', 'keys', 'values') as $item)
		echo '
						<option value="', $item, '">', $txt['language_edit_search_' . $item], '</option>';
	echo '
					</select>
					<input type="submit" value="', $txt['search'], '">
				</form>
			</we:block>
			<we:block class="windowbg" header="', $txt['language_edit_other'], '">';

	if (!empty($context['other_files']))
	{
		echo '
				<h6>', $txt['language_edit_elsewhere'], '</h6>
				<ul>';
		foreach ($context['other_files'] as $url => $desc)
			echo '
					<li><a href="', $url, '">', $desc, '</a></li>';
		echo '
				</ul>';
	}

	foreach (array('plugins', 'themes') as $item)
	{
		if (empty($context['language_files'][$item]))
		{
			echo '
				<h6>', $txt['language_edit_no_' . $item], '</h6>
				<p>', $txt['language_edit_no_' . $item . '_desc'], '</p>';
		}
		else
		{
			foreach ($context['language_files'][$item] as $iid => $block)
			{
				echo '
				<h6>', sprintf($txt['language_edit_' . $item . '_title'], $block['name']), '</h6>
				<ul>';
				foreach ($block['files'] as $id => $name)
					echo '
					<li><a href="<URL>?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], ';tfid=', urlencode($iid . '|' . $id), '">', $name, '</a></li>';
				echo '
				</ul>';
			}
		}
	}

	echo '
			</we:block>
		</div>
		<br class="clear">';
}

function template_modify_entries()
{
	global $context, $txt;

	echo '
	<we:cat>
		', sprintf($txt['edit_languages_specific'], $context['selected_file']['name'] . ' (' . $context['languages'][$context['lang_id']]['name'] . ')'), '
	</we:cat>';

	echo '
	<div class="windowbg2 wrc">
		<dl class="settings admin_permissions">';

	foreach ($context['entries'] as $key => $entry)
	{
		// OK, so we squished two things into one to make the $key earlier.
		list($lang_var, $actual_key) = explode('_', $key, 2);

		echo '
			<dt>', $actual_key, '</dt>
			<dd>';

		if (isset($entry['master']))
		{
			if (!is_array($entry['master']))
				echo sprintf($txt['language_edit_master_value'], westr::safe($entry['master'], ENT_QUOTES));
			else
				template_array_langstring($txt['language_edit_master_value_array'], $entry['master']);
		}
		else
			echo sprintf($txt['language_edit_master_value'], $txt['not_applicable']);

		if (isset($entry['current']))
		{
			echo '<br>';
			if (!is_array($entry['current']))
				echo sprintf($txt['language_edit_current_value'], westr::safe($entry['current'], ENT_QUOTES));
			else
				template_array_langstring($txt['language_edit_current_value_array'], $entry['current']);
		}

		echo '
			</dd>';
	}

	echo '
		</dl>
		<input type="submit" name="save_entries" value="', $txt['save'], '" class="save">
	</div>';
}

function template_array_langstring($title, $array)
{
	echo '
				', $title, '
				<dl>';

	foreach ($array as $k => $v)
		echo '
					<dt>', westr::safe($k, ENT_QUOTES), '</dt>
					<dd>', westr::safe($v, ENT_QUOTES), '</dd>';

	echo '
				</dl>';
}