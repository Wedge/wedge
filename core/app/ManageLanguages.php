<?php
/**
 * Handles the language configuration.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*
	This file contains all the functionality required to be able to edit the
	core server settings. This includes anything from which an error may result
	in the forum destroying itself in a firey fury.
*/

// This is the main function for the language area.
function ManageLanguages()
{
	global $context, $txt;

	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['edit_languages'];
	wetem::load('show_settings');

	$subActions = array(
		'edit' => 'ModifyLanguages',
		'editlang' => 'ModifyLanguage',
	);

	// By default we're managing languages.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'edit';
	$context['sub_action'] = $_REQUEST['sa'];

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['language_configuration'],
		'description' => $txt['language_description'],
	);

	// Call the right function for this sub-action.
	$subActions[$_REQUEST['sa']]();
}

// This lists all the current languages and allows editing of them.
function ModifyLanguages()
{
	global $txt, $context, $settings, $cachedir;

	if (isset($_GET['cleancache']))
	{
		checkSession();
		foreach (glob($cachedir . '/lang_*.php') as $filename)
			@unlink($filename);
		$context['cache_cleared'] = true;

		// The rest also needs to be cleaned up. For instance,
		// most JS files hold hard-coded copies of text strings.
		clean_cache('css');
		clean_cache('js');
	}

	// Whatever we're doing, we want this and we want it uncached.
	getLanguages(false);

	// Setting a new default?
	if (!empty($_POST['set_default']) && !empty($_POST['def_language']))
	{
		checkSession();

		$new_settings = array();
		if ($_POST['def_language'] != $settings['language'] && isset($context['languages'][$_POST['def_language']]))
			$new_settings['language'] = $_POST['def_language'];
		if (!empty($_POST['userLanguage']))
		{
			$new_settings['userLanguage'] = 1;
			$langs = array();
			$_POST['available'] = isset($_POST['available']) ? (array) $_POST['available'] : array();
			foreach ($_POST['available'] as $lang => $dummy)
				if (isset($context['languages'][$lang]))
					$langs[] = $lang;
			if (empty($langs))
				$langs[] = !empty($new_settings['language']) ? $new_settings : (!empty($settings['language']) ? $settings['language'] : 'english');
			$new_settings['langsAvailable'] = implode(',', $langs);
		}
		else
		{
			$new_settings['userLanguage'] = 0;
			$new_settings['langsAvailable'] = !empty($new_settings['language']) ? $new_settings : (!empty($settings['language']) ? $settings['language'] : 'english');
		}

		if (!empty($new_settings))
		{
			updateSettings($new_settings);
			$settings = array_merge($settings, $new_settings);
			// And just because we cache things in the CSS and JS...
			clean_cache('css');
			clean_cache('js');
		}
	}

	$listOptions = array(
		'id' => 'language_list',
		'base_href' => '<URL>?action=admin;area=languages',
		'cat' => $txt['edit_languages'],
		'get_items' => array(
			'function' => 'list_getLanguages',
		),
		'get_count' => array(
			'function' => function () {
				return count(getLanguages(false));
			},
		),
		'columns' => array(
			'available' => array(
				'header' => array(
					'value' => $txt['languages_available'] . ' <a href="<URL>?action=help;in=availableLanguage" class="help" onclick="return reqWin(this);"></a>',
				),
				'data' => array(
					'function' => function ($rowData) {
						return '<input type="checkbox" name="available[' . $rowData['id'] . ']" value="1"' . (!empty($rowData['available']) ? ' checked' : '') . '>';
					},
					'style' => 'text-align: center; width: 8%',
				),
			),
			'default' => array(
				'header' => array(
					'value' => $txt['languages_default'],
				),
				'data' => array(
					'function' => function ($rowData) {
						return '<input type="radio" name="def_language" value="' . $rowData['id'] . '" ' . ($rowData['default'] ? 'checked' : '') . ' onclick="highlightSelected(\'#list_language_list_' . $rowData['id'] . '\');">';
					},
					'style' => 'text-align: center; width: 8%',
				),
			),
			'name' => array(
				'header' => array(
					'value' => $txt['languages_lang_name'],
				),
				'data' => array(
					'function' => function ($rowData) {
						return sprintf('<a href="<URL>?action=admin;area=languages;sa=editlang;lid=%1$s">%2$s</a>', $rowData['id'], $rowData['name']);
					},
				),
			),
			'count' => array(
				'header' => array(
					'value' => $txt['languages_users'],
				),
				'data' => array(
					'db_htmlsafe' => 'count',
					'style' => 'text-align: center',
				),
			),
			'locale' => array(
				'header' => array(
					'value' => $txt['languages_locale'],
				),
				'data' => array(
					'db_htmlsafe' => 'locale',
				),
			),
			'dictionary' => array(
				'header' => array(
					'value' => $txt['languages_dictionary'],
				),
				'data' => array(
					'db_htmlsafe' => 'dictionary',
				),
			),
			'rtl' => array(
				'header' => array(
					'value' => $txt['languages_orientation'],
				),
				'data' => array(
					'function' => function ($rowData) {
						global $txt;

						return $rowData['rtl'] ? $txt['languages_orients_rtl'] : $txt['languages_orients_ltr'];
					},
				),
			),
		),
		'form' => array(
			'href' => '<URL>?action=admin;area=languages',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<label><input type="checkbox" name="userLanguage"' . (!empty($settings['userLanguage']) ? ' checked' : '') . '> ' . $txt['userLanguage'] . '</label>',
			),
			array(
				'position' => 'below_table_data',
				'value' => '<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '"><input type="submit" name="set_default" value="' . $txt['save'] . '" class="save">',
				'style' => 'text-align: right',
			),
		),
		// For highlighting the default.
		'javascript' => '
	var prevDiv = "";
	function highlightSelected(box)
	{
		$(prevDiv).removeClass("highlight");
		prevDiv = $(box).addClass("highlight");
	}
	highlightSelected("#list_language_list_' . ($settings['language'] == '' ? 'english' : $settings['language']). '");',
	);

	loadSource('Subs-List');
	createList($listOptions);

	loadTemplate('ManageLanguages');

	wetem::load('language_home');

	// Oh, and we're done with the proper magical copy of the language list, resume normal services for things like the language selector
	getLanguages();
}

/* Fetch the actual language information.
	- Callback for $listOptions['get_items']['function'] in ManageLanguageSettings.
	- Determines which languages are available by looking for the "index.{language}.php" file.
	- Also figures out how many users are using a particular language. */
function list_getLanguages()
{
	global $context, $txt, $settings;

	$langsAvailable = isset($settings['langsAvailable']) ? explode(',', $settings['langsAvailable']) : array();
	if (empty($langsAvailable))
		$langsAvailable[] = !empty($settings['language']) ? $settings['language'] : 'english';

	// Keep our old entries.
	$oldtxt = $txt;
	$languages = array();

	// Get the language files and data...
	foreach ($context['languages'] as $lang)
	{
		// Load the file to get the character set.
		require(LANGUAGES_DIR . $lang['folder'] . '/index.' . $lang['filename'] . '.php');

		$languages[$lang['filename']] = array(
			'id' => $lang['filename'],
			'count' => 0,
			'available' => $settings['language'] == $lang['filename'] || in_array($lang['filename'], $langsAvailable),
			'default' => $settings['language'] == $lang['filename'] || ($settings['language'] == '' && $lang['filename'] == 'english'),
			'locale' => $txt['lang_locale'],
			'name' => '<img src="' . LANGUAGES . $lang['folder'] . '/Flag.' . $lang['filename'] . '.png" style="margin-right: 8px">' . $txt['lang_name'],
			'dictionary' => $txt['lang_dictionary'] . ' (' . $txt['lang_spelling'] . ')',
			'rtl' => $txt['lang_rtl'],
		);
	}

	// Work out how many people are using each language.
	$request = wesql::query('
		SELECT lngfile, COUNT(*) AS num_users
		FROM {db_prefix}members
		GROUP BY lngfile',
		array(
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		// Default?
		if (empty($row['lngfile']) || !isset($languages[$row['lngfile']]))
			$row['lngfile'] = $settings['language'];

		if (!isset($languages[$row['lngfile']]) && isset($languages['english']))
			$languages['english']['count'] += $row['num_users'];
		elseif (isset($languages[$row['lngfile']]))
			$languages[$row['lngfile']]['count'] += $row['num_users'];
	}
	wesql::free_result($request);

	// Restore the current users language.
	$txt = $oldtxt;

	// Return how many we have.
	return $languages;
}

// Edit a particular set of language entries.
function ModifyLanguage()
{
	global $context, $txt;

	// First up, validate the language selected.
	getLanguages(false);
	if (!isset($_GET['lid'], $context['languages'][$_GET['lid']]))
		redirectexit('action=admin;area=languages;sa=edit');

	$context['lang_id'] = $_GET['lid'];

	loadTemplate('ManageLanguages');
	loadLanguage('ManageSettings');

	// Select the languages tab.
	$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'edit';
	$context['page_title'] = $txt['edit_languages'];
	$context[$context['admin_menu_name']]['tab_data']['tabs']['edit']['description'] = $txt['languages_area_edit_desc'];

	add_linktree($context['languages'][$context['lang_id']]['name'], '<URL>?action=admin;area=languages;sa=editlang;lid=' . $context['lang_id']);

	$context['lang_id'] = $_GET['lid'];

	// Some stuff we do is set entirely outside of this area. But let's make it easy to get to, eh?
	$context['other_files'] = array(
		'<URL>?action=admin;area=mailqueue;sa=templates' => $txt['language_edit_email_templates'],
		'<URL>?action=admin;area=regcenter;sa=agreement' => $txt['language_edit_reg_agreement'],
	);

	// For everything else there's Mast... Darn advertising in my brain again.
	$context['language_files'] = array(
		'default' => array(
			'main' => array(
				'name' => $txt['language_edit_main'],
				'files' => array(
					'index' => 'index', // this is purely to preempt what happens later!
				),
			),
			'admin' => array(
				'name' => $txt['language_edit_admin'],
				'files' => array(),
			),
		),
		'plugins' => array(),
	);

	if (empty($_REQUEST['tfid']))
		list ($source_id, $file_id, $path) = array(1, '', '/');
	else
	{
		$parts = explode('|', $_REQUEST['tfid']);
		if (!isset($parts[1]))
			list ($source_id, $file_id, $path) = array(1, $parts[0], '/');
		else
		{
			// In plugins, the entry supplied is plugin_id|path|lang
			$source_id = array_shift($parts);
			$file_id = array_pop($parts);
			$path = implode('/', $parts) . '/';
		}
	}

	// Before doing plugins, we'll get the regular languages folder.
	$names = array(
		1 => $txt['dvc_default'],
	);

	// This will be where we look. (Right now we only have one folder to retrieve, so... Do that.)
	$lang_dirs = array();

	// Check our languages folder exists, and add the path.
	if (is_dir(LANGUAGES_DIR))
		$lang_dirs[1] = LANGUAGES_DIR . $context['languages'][$context['lang_id']]['folder'] . '/';

	// Now add the possible permutations for plugins.
	if (!empty($context['plugins_dir']))
	{
		foreach ($context['plugins_dir'] as $plugin_id => $plugin_path)
		{
			$names[$plugin_id] = substr(strrchr($plugin_id, ':'), 1);
			$context['language_files']['plugins'][$plugin_id] = array(
				'name' => str_replace(array('-', '_'), ' ', substr(strrchr($plugin_id, ':'), 1)),
			);
			$lang_dirs[$plugin_id] = array('' => $plugin_path);
			// This is a bit faster than other folder scanning processes.
			$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_path), RecursiveIteratorIterator::SELF_FIRST);
			foreach ($objects as $name => $object)
			{
				$filename = basename($name);
				if ($filename !== '.' && $filename !== '..' && is_dir($name))
					$lang_dirs[$plugin_id][str_replace($plugin_path, '', $name) . '/'] = $name;
			}

			if (empty($lang_dirs[$plugin_id]))
				unset($lang_dirs[$plugin_id], $names[$plugin_id], $context['language_files']['plugins'][$plugin_id]);
		}
	}

	// Now for every theme get all the files and stick them in context!
	$context['possible_files'] = array();
	foreach ($lang_dirs as $th => $source_dirs)
	{
		// Depending on where we came from, we might be looking at a single folder or a plugin's potentially many subfolders.
		// If we're looking at a plugin, the array will be the possible folders for the prefixing as array keys, with
		// the values as full paths, and the prefixing keys will contain the | delimiter as needed.
		if (!is_array($source_dirs))
			$source_dirs = array('' => $source_dirs);

		// Sift out now where this is likely to go.
		$dest = $th == 1 ? 'default' : 'plugins';

		foreach ($source_dirs as $path_prefix => $source_dir)
		{
			// Open it up.
			$dir = dir($source_dir);
			while ($entry = $dir->read())
			{
				// We're only after the files for this language.
				if (preg_match('~^([A-Za-z0-9_-]+)\.' . $context['lang_id'] . '\.php$~', $entry, $matches) == 0)
					continue;

				// We don't do the email templates or the registration agreement here.
				if ($dest != 'plugins' && ($matches[1] == 'EmailTemplates' || $matches[1] == 'Agreement' || $matches[1] == 'Install'))
					continue;

				if (!isset($context['possible_files'][$th]))
					$context['possible_files'][$th] = array(
						'id' => $th,
						'name' => $names[$th],
						'files' => array(),
					);

				if ($th == $source_id && $matches[1] == $file_id)
					$context['selected_file'] = array(
						'source_id' => $source_id,
						'lang_id' => $path_prefix . $matches[1],
						'name' => $matches[1],
						'desc' => isset($txt['lang_file_desc_' . $matches[1]]) ? $txt['lang_file_desc_' . $matches[1]] : '',
						'path' => (isset($context['plugins_dir'][$source_id]) ? $context['plugins_dir'][$source_id] : $lang_dirs[$source_id]) . $path . $file_id . '.' . $context['lang_id'] . '.php',
					);

				$langfile = $matches[1] . (isset($txt['lang_file_desc_' . $matches[1]]) ? '~' . $txt['lang_file_desc_' . $matches[1]] : '');

				if ($dest == 'default')
				{
					$loc = ($matches[1] == 'Admin' || $matches[1] == 'Modlog' || $matches[1] == 'ModerationCenter' || strpos($matches[1], 'Manage') !== false) ? 'admin' : 'main';
					$context['language_files']['default'][$loc]['files'][$path_prefix . $matches[1]] = $langfile;
				}
				else
					$context['language_files']['plugins'][$th]['files'][$path_prefix . $matches[1]] = $langfile;
			}
			$dir->close();
		}
	}

	// Let's go clean up.
	foreach ($context['language_files']['plugins'] as $item => $content)
		if (empty($content['files']))
			unset($context['language_files']['plugins'][$item]);

	if (empty($context['language_files']['plugins']))
		unset($context['language_files']['plugins']);

	// If we are editing something, let's send it off. We still have to do all the preceding stuff anyway
	// because it allows us to completely validate that what we're editing exists.
	if (!empty($context['selected_file']))
		return ModifyLanguageEntries();

	elseif (isset($_POST['search']) && trim($_POST['search']) !== '')
		return SearchLanguageEntries($lang_dirs);
	else
		wetem::load('modify_language_list');
}

function ModifyLanguageEntries()
{
	global $context, $txt, $cachedir;

	add_linktree($context['selected_file']['name'], '<URL>?action=admin;area=languages;sa=editlang;lid=' . $context['lang_id'] . ';tfid=' . urlencode(($context['selected_file']['source_id'] ? $context['selected_file']['source_id'] . '|' : '') . $context['selected_file']['lang_id']));

	$context['entries'] = array();

	// Now we load the existing content.
	$oldtxt = $txt;
	$txt = array();

	// Start by loading the file.
	@include($context['selected_file']['path']);
	foreach ($txt as $k => $v)
		$context['entries'][$k] = array(
			'master' => $v,
		);
	// We can put the original entries back now.
	$txt = $oldtxt;

	// Now we load everything else. Is this a conventional 'theme' string?
	$is_plugin = isset($context['plugins_dir'][$context['selected_file']['source_id']]);

	$request = wesql::query('
		SELECT lang_key, lang_string, serial
		FROM {db_prefix}language_changes
		WHERE is_plugin = {int:is_plugin}
			AND id_lang = {string:lang}
			AND lang_file = {string:lang_file}',
		array(
			'is_plugin' => $is_plugin ? 1 : 0,
			'lang' => $context['lang_id'],
			'lang_file' => $is_plugin ? $context['selected_file']['source_id'] . ':' . $context['selected_file']['lang_id'] : $context['selected_file']['lang_id'],
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$context['entries'][$row['lang_key']]['current'] = $row['serial'] ? @unserialize($row['lang_string']) : $row['lang_string'];
	wesql::free_result($request);

	// Some entries shouldn't be modified. Declared once, but restricted on both loading and saving.
	$restricted_entries = array('txt_lang_name', 'txt_lang_locale', 'txt_lang_dictionary', 'txt_lang_spelling', 'txt_lang_rtl', 'txt_lang_paypal');
	foreach ($restricted_entries as $item)
		unset($context['entries'][$item]);

	if (isset($_GET['eid'], $context['entries'][$_GET['eid']]))
	{
		// So either we're displaying what we're doing, or we're making some changes.
		if (isset($_POST['delete']))
		{
			checkSession();
			if (!$is_plugin)
			{
				$context['selected_file']['source_id'] = (int) $context['selected_file']['source_id'];
				$request = wesql::query('
					DELETE FROM {db_prefix}language_changes
					WHERE is_plugin = 0
						AND id_lang = {string:lang}
						AND lang_file = {string:lang_file}
						AND lang_key = {string:lang_key}',
					array(
						'lang' => $context['lang_id'],
						'lang_file' => $context['selected_file']['lang_id'],
						'lang_key' => $_GET['eid'],
					)
				);
				$glob = 'lang_*_' . $context['selected_file']['lang_id'] . '.php';
			}
			else
			{
				$key = $context['selected_file']['source_id'] . ':' . $context['selected_file']['lang_id'];
				$request = wesql::query('
					DELETE FROM {db_prefix}language_changes
					WHERE is_plugin = 1
						AND id_lang = {string:lang}
						AND lang_file = {string:lang_file}
						AND lang_key = {string:lang_key}',
					array(
						'lang' => $context['lang_id'],
						'lang_file' => $key,
						'lang_key' => $_GET['eid'],
					)
				);
				$glob = 'lang_*_' . valid_filename($key) . '.php';
			}

			// Figure out what we're flushing. We don't need to do the *entire* cache, but we do need to do anything that could
			// have been affected by this file. There are some awesome potential cross-contamination possibilities, so be safe.
			foreach (glob($cachedir . '/' . $glob) as $filename)
				unlink($filename);

			// Sorry in advance. This is not a fun process.
			clean_cache('js');

			// OK, so we've removed this one, we can clear the current entry of it then let it fall back to original procedure.
			unset($context['entries'][$_GET['eid']]['current']);
			wetem::load('modify_entries');
			return;
		}

		if (isset($_POST['save']))
		{
			checkSession();

			$lang_file = ($is_plugin ? $context['selected_file']['source_id'] . ':' : '') . $context['selected_file']['lang_id'];
			$id_lang = $context['lang_id'];

			if (!empty($_POST['entry']))
			{
				$lang_string = $_POST['entry']; // Unfortunately, there are a ton of strings that can't be sanitized. :(
				$serial = 0;
			}
			elseif (!empty($_POST['entry_key']) && !empty($_POST['entry_value']) && is_array($_POST['entry_key']) && is_array($_POST['entry_value']))
			{
				$entry = array();
				foreach ($_POST['entry_key'] as $k => $v)
				{
					if (empty($v) || empty($_POST['entry_value'][$k]))
						continue;
					$entry[$v] = $_POST['entry_value'][$k];
				}

				$serial = 1;

				if (!empty($entry))
					$lang_string = serialize($entry);
			}

			if (!isset($lang_string))
			{
				wetem::load('modify_entries');
				return;
			}

			$context['entries'][$_GET['eid']]['current'] = $serial ? unserialize($lang_string) : $lang_string;

			wesql::insert('replace',
				'{db_prefix}language_changes',
				array('id_lang' => 'string', 'lang_file' => 'string', 'lang_key' => 'string', 'lang_string' => 'string', 'serial' => 'int', 'is_plugin' => 'int'),
				array($id_lang, $lang_file, $_GET['eid'], $lang_string, $serial, $is_plugin ? 1 : 0)
			);

			// Figure out what we're flushing. We don't need to do the *entire* cache, but we do need to do anything that could
			// have been affected by this file. There are some awesome potential cross-contamination possibilities, so be safe.
			if ($is_plugin)
				$glob = 'lang_*_' . valid_filename($lang_file) . '.php';
			else
				$glob = 'lang_*_' . $context['selected_file']['lang_id'] . '.php';

			foreach (glob($cachedir . '/' . $glob) as $filename)
				@unlink($filename);

			// Sorry in advance. This is not a fun process.
			clean_cache('js');

			// Just in case it makes any difference.
			$txt[$_GET['eid']] = $context['entries'][$_GET['eid']]['current'];

			wetem::load('modify_entries');
			return;
		}

		$context['entry'] = $context['entries'][$_GET['eid']];
		$context['entry']['id'] = $_GET['eid'];

		unset($context['entries']);
		wetem::load('modify_individual_entry');
	}
	else
		wetem::load('modify_entries');
}

function SearchLanguageEntries($lang_dirs)
{
	global $context, $txt;

	// Just remember, whatever happens, this is going to suck in performance.
	// First, remember to push the old $txt elsewhere. We need it later.
	$oldtxt = $txt;

	$context['results'] = array(
		'default' => array(),
	);

	$search_type = isset($_POST['search_type']) && in_array($_POST['search_type'], array('keys', 'values')) ? $_POST['search_type'] : 'both';

	// Second, sort through the default files, we know they're going to exist.
	foreach ($context['language_files']['default'] as $section)
		foreach ($section['files'] as $file_id => $title)
		{
			$txt = array();
			include($lang_dirs[1] . '/' . $file_id . '.' . $context['lang_id'] . '.php');
			foreach ($txt as $key => $value)
			{
				if ($search_type == 'keys' || $search_type == 'both')
				{
					if (stripos($key, $_POST['search']) !== false)
					{
						$context['results']['default'][$file_id][$key] = array('master' => $value);
						continue;
					}
				}

				if ($search_type == 'values' || $search_type == 'both')
				{
					if (is_array($value))
					{
						foreach ($value as $k => $v)
							if (stripos($v, $_POST['search']) !== false)
							{
								$context['results']['default'][$file_id][$key] = array('master' => $value);
								break;
							}
					}
					elseif (stripos($value, $_POST['search']) !== false)
						$context['results']['default'][$file_id][$key] = array('master' => $value);
				}
			}
		}

	// Third, get all the entries for this language in the database and apply much the same tests.
	$request = wesql::query('
		SELECT lang_file, lang_key, lang_string, serial
		FROM {db_prefix}language_changes
		WHERE is_plugin = 0
			AND id_lang = {string:lang}',
		array(
			'lang' => $context['lang_id'],
		)
	);

	while ($row = wesql::fetch_assoc($request))
	{
		$lang_string = !empty($row['serial']) ? unserialize($row['lang_string']) : $row['lang_string'];
		if ($search_type == 'keys' || $search_type == 'both')
		{
			if (stripos($row['lang_key'], $_POST['search']) !== false)
			{
				$context['results']['default'][$row['lang_file']][$row['lang_key']]['current'] = $lang_string;
				continue;
			}
		}

		if ($search_type == 'values' || $search_type == 'both')
		{
			if (is_array($lang_string))
			{
				foreach ($lang_string as $k => $v)
					if (stripos($v, $_POST['search']) !== false)
					{
						$context['results']['default'][$row['lang_file']][$row['lang_key']]['current'] = $lang_string;
						break;
					}
			}
			elseif (stripos($lang_string, $_POST['search']) !== false)
				$context['results']['default'][$row['lang_file']][$row['lang_key']]['current'] = $lang_string;
		}
	}
	wesql::free_result($request);

	// Fourth, plugins.
	if (!empty($_POST['include_plugins']))
	{
		$context['results']['plugins'] = array();
		$plugin_files = array();

		foreach ($context['language_files']['plugins'] as $plugin_id => $section)
			foreach ($section['files'] as $file_id => $title)
			{
				$plugin_files[$plugin_id . ':' . $file_id] = array($plugin_id, $file_id);
				$txt = array();
				include($context['plugins_dir'][$plugin_id] . '/' . $file_id . '.' . $context['lang_id'] . '.php');
				foreach ($txt as $key => $value)
				{
					if ($search_type == 'keys' || $search_type == 'both')
					{
						if (stripos($key, $_POST['search']) !== false)
						{
							$context['results']['plugins'][$plugin_id][$file_id][$key] = array('master' => $value);
							continue;
						}
					}

					if ($search_type == 'values' || $search_type == 'both')
					{
						if (is_array($value))
						{
							foreach ($value as $k => $v)
								if (stripos($v, $_POST['search']) !== false)
								{
									$context['results']['plugins'][$plugin_id][$file_id][$key] = array('master' => $value);
									break;
								}
						}
						elseif (stripos($value, $_POST['search']) !== false)
							$context['results']['plugins'][$plugin_id][$file_id][$key] = array('master' => $value);
					}
				}
			}

		// Fourth and a bit... plugin changes in the DB.
		if (!empty($plugin_files))
		{
			$request = wesql::query('
				SELECT lang_file, lang_key, lang_string, serial
				FROM {db_prefix}language_changes
				WHERE is_plugin = 1
					AND id_lang = {string:lang}
					AND lang_file IN ({array_string:plugin_files})',
				array(
					'lang' => $context['lang_id'],
					'plugin_files' => array_keys($plugin_files),
				)
			);

			while ($row = wesql::fetch_assoc($request))
			{
				list($plugin_id, $file_id) = $plugin_files[$row['lang_file']];
				$lang_string = !empty($row['serial']) ? unserialize($row['lang_string']) : $row['lang_string'];
				if ($search_type == 'keys' || $search_type == 'both')
				{
					if (stripos($row['lang_key'], $_POST['search']) !== false)
					{
						$context['results']['plugins'][$plugin_id][$file_id][$row['lang_key']]['current'] = $lang_string;
						continue;
					}
				}

				if ($search_type == 'values' || $search_type == 'both')
				{
					if (is_array($lang_string))
					{
						foreach ($lang_string as $k => $v)
							if (stripos($v, $_POST['search']) !== false)
							{
								$context['results']['plugins'][$plugin_id][$file_id][$row['lang_key']]['current'] = $lang_string;
								break;
							}
					}
					elseif (stripos($lang_string, $_POST['search']) !== false)
						$context['results']['plugins'][$plugin_id][$file_id][$row['lang_key']]['current'] = $lang_string;
				}
			}
			wesql::free_result($request);
		}
	}

	$txt = $oldtxt;
	wetem::load('search_entries');
}
