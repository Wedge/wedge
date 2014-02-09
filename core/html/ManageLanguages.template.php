<?php
/**
 * The main administration for managing language files.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

// Display the list of languages, as well as the clear-cache button.
function template_language_home()
{
	global $context, $txt;

	template_show_list('language_list');

	echo '
	<we:cat>', $txt['language_clear_cache'], '</we:cat>';

	if (!empty($context['cache_cleared']))
		echo '
		<div class="windowbg" id="profile_success">
			', $txt['language_cache_cleared'], '
		</div>';

	echo '
	<div class="windowbg wrc">
		<form action="<URL>?action=admin;area=languages;cleancache" method="post" accept-charset="UTF-8">
			<p>', $txt['language_clear_cache_desc'], '</p>
			<input type="submit" value="', $txt['language_clear_cache_btn'], '" class="floatright">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<br class="clear">
		</form>
	</div>
	<br>';
}

// Edit some language entries?
function template_modify_language_list()
{
	global $context, $txt;

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
					<li>', template_lang_file_link('<URL>?action=admin;area=languages;sa=editlang;lid=' . $context['lang_id'] . ';tfid=' . urlencode($id), $name), '</li>';
		echo '
				</ul>';
	}

	echo '
			</we:block>
		</div>
		<div class="two-columns">
			<we:block class="windowbg" header="', $txt['language_edit_search'], '">
				<form action="<URL>?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], '" method="post" accept-charset="UTF-8">
					<input type="search" name="search" value="" class="search">';

	if (!empty($context['language_files']['plugins']))
		echo '
					<label style="height: 25px; vertical-align: middle"><input type="checkbox" name="include_plugins" value="1" checked> ', $txt['language_edit_search_plugins'], '</input></label>';

	echo '
					<div style="margin: 6px"></div>
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

	if (empty($context['language_files']['plugins']))
	{
		echo '
				<h6>', $txt['language_edit_no_plugins'], '</h6>
				<p>', $txt['language_edit_no_plugins_desc'], '</p>';
	}
	else
	{
		foreach ($context['language_files']['plugins'] as $iid => $block)
		{
			echo '
				<h6>', sprintf($txt['language_edit_plugins_title'], $block['name']), '</h6>
				<ul>';

			foreach ($block['files'] as $id => $name)
				echo '
					<li>', template_lang_file_link('<URL>?action=admin;area=languages;sa=editlang;lid=' . $context['lang_id'] . ';tfid=' . urlencode($iid . '|' . $id), $name), '</li>';

			echo '
				</ul>';
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

	if (empty($context['entries']))
	{
		echo '
			<div class="windowbg2 wrc">', sprintf($txt['language_no_entries'], '<URL>?action=admin;area=languages;sa=editlang;lid=english;tfid=' . urlencode(($context['selected_file']['source_id'] ? $context['selected_file']['source_id'] . '|' : '') . $context['selected_file']['lang_id'])), '</div>';
		return;
	}

	echo '
	<div class="windowbg2 wrc">
		<dl class="settings admin_permissions">';

	$lang_url = 'lid=' . $context['lang_id'] . ';tfid=' . urlencode(($context['selected_file']['source_id'] ? $context['selected_file']['source_id'] . '|' : '') . $context['selected_file']['lang_id']);

	foreach ($context['entries'] as $key => $entry)
	{
		echo '
			<dt><a href="<URL>?action=admin;area=languages;sa=editlang;', $lang_url, ';eid=', $key, '">', $key, '</a></dt>
			<dd>';

		if (isset($entry['master']))
		{
			if (!is_array($entry['master']))
				echo sprintf($txt['language_edit_master_value'], htmlspecialchars($entry['master'], ENT_QUOTES));
			else
				template_array_langstring($txt['language_edit_master_value_array'], $entry['master']);
		}
		else
			echo sprintf($txt['language_edit_master_value'], $txt['not_applicable']);

		if (isset($entry['current']))
		{
			echo '<br>';
			if (!is_array($entry['current']))
				echo sprintf($txt['language_edit_current_value'], htmlspecialchars($entry['current'], ENT_QUOTES));
			else
				template_array_langstring($txt['language_edit_current_value_array'], $entry['current']);
		}

		echo '
			</dd>';
	}

	echo '
		</dl>
	</div>';
}

function template_modify_individual_entry()
{
	global $context, $txt;

	echo '
	<we:cat>
		', sprintf($txt['edit_languages_specific'], $context['selected_file']['name'] . ($context['selected_file']['desc'] ? ' (' . $context['selected_file']['name'] . ')' : '') . ' (' . $context['languages'][$context['lang_id']]['name'] . ')'), '
	</we:cat>';

	$lang_url = 'lid=' . $context['lang_id'] . ';tfid=' . urlencode(($context['selected_file']['source_id'] ? $context['selected_file']['source_id'] . '|' : '') . $context['selected_file']['lang_id']);

	echo '
	<form action="<URL>?action=admin;area=languages;sa=editlang;', $lang_url, ';eid=', $context['entry']['id'], '" method="post" accept-charset="UTF-8">
		<div class="windowbg2 wrc">
			<dl class="settings admin_permissions">';

	echo '
				<dt>', $context['entry']['id'], '</dt>
				<dd>';

	if (isset($context['entry']['master']))
	{
		if (!is_array($context['entry']['master']))
			echo sprintf($txt['language_edit_master_value'], htmlspecialchars($context['entry']['master'], ENT_QUOTES));
		else
			template_array_langstring($txt['language_edit_master_value_array'], $context['entry']['master']);
	}
	else
		echo sprintf($txt['language_edit_master_value'], $txt['not_applicable']);

	echo '
				</dd>
				<dt></dt>
				<dd>';

	$editing_value = isset($context['entry']['current']) ? $context['entry']['current'] : $context['entry']['master'];
	if (!is_array($editing_value))
	{
		$rows = max(2, (int) (strlen($editing_value) / 48) + substr_count($editing_value, "\n") + 1);
		echo '
					', $txt['language_edit_new_value'], '
					<textarea name="entry" cols="50" rows="', $rows, '" class="w100">', htmlspecialchars($editing_value, ENT_QUOTES), '</textarea>';
	}
	else
	{
		echo '
					', $txt['language_edit_new_value_array'], '
					<dl id="multilang">';

		foreach ($editing_value as $k => $v)
		{
			$rows = max(2, (int) (strlen($v) / 48) + substr_count($v, "\n") + 1);
			echo '
						<dt>
							<input type="submit" value="', $txt['delete'], '" class="delete" onclick="return removeRow(this);">
							&nbsp; <input name="entry_key[]" value="', htmlspecialchars($k, ENT_QUOTES), '" class="w25">
						</dt>
						<dd>
							<textarea name="entry_value[]" cols="30" rows="', $rows, '">', htmlspecialchars($v, ENT_QUOTES), '</textarea>
						</dd>';
		}

		echo '
					</dl>';

		add_js('
	function addRow()
	{
		$(\'<dt><input type="submit" value="\' + we_delete + \'" class="delete" onclick="return removeRow(this);"> &nbsp; <input name="entry_key[]" value="" class="w25"></textarea></dt><dd><textarea name="entry_value[]" cols="30" rows="1"></textarea></dd>\').appendTo(\'#multilang\');
		return false;
	};

	function removeRow(obj)
	{
		$(obj).parent().next(\'dd\').remove();
		$(obj).parent().remove();
		return false;
	};');
	}

	echo '
				</dd>
			</dl>
			<br class="clear">
			<div class="floatright">';

	if (is_array($editing_value))
		echo '
				<input type="submit" value="', $txt['language_edit_add_entry'], '" onclick="return addRow();" class="new">';

	echo '
				<input type="submit" name="save" value="', $txt['save'], '" class="save">';

	if (isset($context['entry']['current']))
		echo '
				&nbsp; <input type="submit" name="delete" value="', isset($context['entry']['master']) ? $txt['language_revert_value'] : $txt['language_delete_value'], '" onclick="return ask(we_confirm, e);" class="delete">';

	echo '
			</div>
			<br class="clear">
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

function template_search_entries()
{
	global $context, $txt;

	echo '
	<we:cat>
		', sprintf($txt['language_search_results'], $context['languages'][$context['lang_id']]['name']), ' - "', westr::safe($_POST['search'], ENT_QUOTES), '"
	</we:cat>';

	echo '
	<we:title>', $txt['language_search_default'], '</we:title>';

	if (empty($context['results']['default']))
	{
		echo '
	<div class="windowbg2 wrc">', $txt['language_no_result_results'], '</div>';
	}
	else
	{
		$use_bg2 = false;

		foreach ($context['results']['default'] as $file_id => $file_entries)
		{
			echo '
	<div class="windowbg', $use_bg2 ? '2' : '', ' wrc">';
			$use_bg2 = !$use_bg2;

			$lang_url = 'lid=' . $context['lang_id'] . ';tfid=' . urlencode($file_id);
			$title = $file_id;
			// But try and find a better one.
			foreach ($context['language_files']['default'] as $section)
			{
				if (isset($section['files'][$file_id]))
				{
					$title = $section['files'][$file_id];
					break;
				}
			}
			echo '
			<h5>', template_lang_file_link('<URL>?action=admin;area=languages;sa=editlang;' . $lang_url, $title), '</h5>
			<hr>
			<dl class="settings admin_permissions">';

			foreach ($file_entries as $key => $entry)
			{
				echo '
			<dt><a href="<URL>?action=admin;area=languages;sa=editlang;', $lang_url, ';eid=', $key, '">', $key, '</a></dt>
			<dd>';

				if (isset($entry['master']))
				{
					if (!is_array($entry['master']))
						echo sprintf($txt['language_edit_master_value'], htmlspecialchars($entry['master'], ENT_QUOTES));
					else
						template_array_langstring($txt['language_edit_master_value_array'], $entry['master']);
				}
				else
					echo sprintf($txt['language_edit_master_value'], $txt['not_applicable']);

				if (isset($entry['current']))
				{
					echo '<br>';
					if (!is_array($entry['current']))
						echo sprintf($txt['language_edit_current_value'], htmlspecialchars($entry['current'], ENT_QUOTES));
					else
						template_array_langstring($txt['language_edit_current_value_array'], $entry['current']);
				}

				echo '
			</dd>';
			}

			echo '
		</dl>
	</div>';
		}
	}

	if (isset($context['results']['plugins']))
	{
		echo '
	<we:title>', $txt['language_search_plugins'], '</we:title>';
		if (empty($context['results']['plugins']))
			echo '
	<div class="windowbg2 wrc">', $txt['language_no_result_results'], '</div>';
		else
		{
			$use_bg2 = false;
			foreach ($context['results']['plugins'] as $plugin_id => $plugin_entries)
			{
				foreach ($plugin_entries as $file_id => $file_entries)
				{
					echo '
	<div class="windowbg', $use_bg2 ? '2' : '', ' wrc">
		<dl class="settings admin_permissions">';
					$use_bg2 = !$use_bg2;

					$title = $context['language_files']['plugins'][$plugin_id]['name'] . ' - ' . $file_id;
					$lang_url = 'lid=' . $context['lang_id'] . ';tfid=' . urlencode($plugin_id . '|' . $file_id);
					echo '
			<dt><strong><a href="<URL>?action=admin;area=languages;sa=editlang;', $lang_url, '">', $title, '</a></strong></dt>';

					foreach ($file_entries as $key => $entry)
					{
						echo '
			<dt><a href="<URL>?action=admin;area=languages;sa=editlang;', $lang_url, ';eid=', $key, '">', $key, '</a></dt>
			<dd>';

						if (isset($entry['master']))
						{
							if (!is_array($entry['master']))
								echo sprintf($txt['language_edit_master_value'], htmlspecialchars($entry['master'], ENT_QUOTES));
							else
								template_array_langstring($txt['language_edit_master_value_array'], $entry['master']);
						}
						else
							echo sprintf($txt['language_edit_master_value'], $txt['not_applicable']);

						if (isset($entry['current']))
						{
							echo '<br>';
							if (!is_array($entry['current']))
								echo sprintf($txt['language_edit_current_value'], htmlspecialchars($entry['current'], ENT_QUOTES));
							else
								template_array_langstring($txt['language_edit_current_value_array'], $entry['current']);
						}

						echo '
			</dd>';
					}
					echo '
		</dl>
	</div>';
				}
			}
		}
	}
}

function template_array_langstring($title, $array)
{
	echo '
				', $title, '
				<dl>';

	foreach ($array as $k => $v)
		echo '
					<dt>', htmlspecialchars($k, ENT_QUOTES), '</dt>
					<dd>', htmlspecialchars($v, ENT_QUOTES), '</dd>';

	echo '
				</dl>';
}

function template_lang_file_link($link, $name)
{
	$desc = '';
	if (strpos($name, '~') !== false)
		list ($name, $desc) = explode('~', $name);
	echo '<a href="', $link, '">', $name, '</a>', $desc ? ' (' . $desc . ')' : '';
}
