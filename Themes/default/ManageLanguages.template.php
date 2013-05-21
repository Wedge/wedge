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

	$lang_url = 'lid=' . $context['lang_id'] . ';tfid=' . urlencode($context['selected_file']['source_id'] . '|' . $context['selected_file']['lang_id']);

	foreach ($context['entries'] as $key => $entry)
	{
		// OK, so we squished two things into one to make the $key earlier.
		list ($lang_var, $actual_key) = explode('_', $key, 2);

		echo '
			<dt><a href="<URL>?action=admin;area=languages;sa=editlang;', $lang_url, ';eid=', $key, '">', $actual_key, '</a></dt>
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
	</div>';
}

function template_modify_individual_entry()
{
	global $context, $txt;

	echo '
	<we:cat>
		', sprintf($txt['edit_languages_specific'], $context['selected_file']['name'] . ' (' . $context['languages'][$context['lang_id']]['name'] . ')'), '
	</we:cat>';

	$lang_url = 'lid=' . $context['lang_id'] . ';tfid=' . urlencode($context['selected_file']['source_id'] . '|' . $context['selected_file']['lang_id']);

	// OK, so we squished two things into one to make the $key earlier.
	list ($lang_var, $actual_key) = explode('_', $context['entry']['id'], 2);

	echo '
	<form action="<URL>?action=admin;area=languages;sa=editlang;', $lang_url, ';eid=', $context['entry']['id'], '" method="post" accept-charset="UTF-8">
		<div class="windowbg2 wrc">
			<dl class="settings admin_permissions">';

	echo '
				<dt>', $actual_key, '</dt>
				<dd>';

	if (isset($context['entry']['master']))
	{
		if (!is_array($context['entry']['master']))
			echo sprintf($txt['language_edit_master_value'], westr::safe($context['entry']['master'], ENT_QUOTES));
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
		$rows = (int) (strlen($editing_value) / 48) + substr_count($editing_value, "\n") + 1;
		echo '
					', $txt['language_edit_new_value'], '
					<textarea name="entry" cols="50" rows="', $rows, '" class="w100">', westr::safe($editing_value, ENT_QUOTES), '</textarea>';
	}
	else
	{
		echo '
					', $txt['language_edit_new_value_array'], '
					<dl id="multilang">';
		foreach ($editing_value as $k => $v)
		{
			$rows = (int) (strlen($v) / 48) + substr_count($v, "\n") + 1;
			echo '
						<dt>
							<input type="submit" value="', $txt['delete'], '" class="delete" onclick="return removeRow(this);">
							&nbsp; <input name="entry_key[]" value="', westr::safe($k, ENT_QUOTES), '" class="w25">
						</dt>
						<dd>
							<textarea name="entry_value[]" cols="30" rows="', $rows, '">', westr::safe($v, ENT_QUOTES), '</textarea>
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