<?php
/**
 * Wedge
 *
 * Handles the language configuration.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file contains all the functionality required to be able to edit the
	core server settings. This includes anything from which an error may result
	in the forum destroying itself in a firey fury.

	void AddLanguage()
		// !!!

	void DownloadLanguage()
		- Uses the ManageSettings template and the download_language block.
		- Requires a valid download ID ("did") in the URL.
		- Also handles installing language files.
		- Attempts to chmod things as needed.
		- Uses a standard list to display information about all the files and where they'll be put.

	void ManageLanguages()
		// !!!

	void ModifyLanguages()
		// !!!

	int list_getNumLanguages()
		// !!!

	array list_getLanguages()
		- Callback for $listOptions['get_items']['function'] in ManageLanguageSettings.
		- Determines which languages are available by looking for the "index.{language}.php" file.
		- Also figures out how many users are using a particular language.

	void ModifyLanguageSettings()
		// !!!

	void ModifyLanguage()
		// !!!

*/

// This is the main function for the language area.
function ManageLanguages()
{
	global $context, $txt, $scripturl, $settings;

	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['edit_languages'];
	wetem::load('show_settings');

	$subActions = array(
		'edit' => 'ModifyLanguages',
		'add' => 'AddLanguage',
		'settings' => 'ModifyLanguageSettings',
		'downloadlang' => 'DownloadLanguage',
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

// Interface for adding a new language
function AddLanguage()
{
	global $context, $txt, $scripturl;

	// Are we searching for new languages courtesy of Wedge?
	if (!empty($_POST['we_add_sub']))
	{
		loadSource('Class-WebGet');

		$context['we_search_term'] = htmlspecialchars(trim($_POST['we_add']));

		// We're going to use this URL.
		// !!! @todo: Update with Wedge language files.
		$url = 'http://wedge.org/files/fetch_language.php?version=' . urlencode(WEDGE_VERSION);

		// Load the data and stick it into an array.
		$weget = new weget($url);
		$data = $weget->get();
		$language_list = simplexml_load_string($data);

		// Check it exists.
		if (empty($language_list->language))
			$context['wedge_error'] = 'no_response';
		else
		{
			$context['wedge_languages'] = array();
			foreach ($language_list->language as $this_lang)
			{
				$lang_name = (string) $this_lang->name;
				if (!empty($context['we_search_term']) && strpos($lang_name, westr::strtolower($context['we_search_term'])) === false)
					continue;

				$context['wedge_languages'][] = array(
					'id' => (string) $this_lang->id,
					'name' => westr::ucwords($lang_name),
					'version' => (string) $this_lang->version,
					'description' => (string) $this_lang->description,
					'link' => $scripturl . '?action=admin;area=languages;sa=downloadlang;did=' . (string) $this_lang->id . ';' . $context['session_query'],
				);
			}

			if (empty($context['wedge_languages']))
				$context['wedge_error'] = 'no_files';
		}
	}

	wetem::load('add_language');
}

// Download a language file from the Wedge website.
function DownloadLanguage()
{
	global $context, $boarddir, $txt, $scripturl, $settings;

	loadLanguage('ManageSettings');
	loadSource('Subs-Package');

	// Clearly we need to know what to request.
	if (!isset($_GET['did']))
		fatal_lang_error('no_access', false);

	// Some lovely context.
	$context['download_id'] = $_GET['did'];
	wetem::load('download_language');
	$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'add';

	// Can we actually do the installation - and do they want to?
	if (!empty($_POST['do_install']) && !empty($_POST['copy_file']))
	{
		checkSession('get');

		$chmod_files = array();
		$install_files = array();
		// Check writable status.
		foreach ($_POST['copy_file'] as $file)
		{
			// Check it's not very bad.
			if (strpos($file, '..') !== false || (substr($file, 0, 6) != 'Themes' && !preg_match('~agreement\.[A-Za-z-_0-9]+\.txt$~', $file)))
				fatal_lang_error('languages_download_illegal_paths');

			$chmod_files[] = $boarddir . '/' . $file;
			$install_files[] = $file;
		}

		// Call this in case we have work to do.
		$file_status = create_chmod_control($chmod_files);
		$files_left = $file_status['files']['notwritable'];

		// Something not writable?
		if (!empty($files_left))
			$context['error_message'] = $txt['languages_download_not_chmod'];
		// Otherwise, go go go!
		elseif (!empty($install_files))
		{
			// !!! @todo: Update with Wedge language files.
			$archive_content = read_tgz_file('http://wedge.org/files/fetch_language.php?version=' . urlencode(WEDGE_VERSION) . ';fetch=' . urlencode($_GET['did']), $boarddir, false, true, $install_files);
			// Make sure the files aren't stuck in the cache.
			package_flush_cache();
			$context['install_complete'] = sprintf($txt['languages_download_complete_desc'], $scripturl . '?action=admin;area=languages');

			return;
		}
	}

	// Open up the old china.
	// !!! @todo: Update with Wedge language files.
	if (!isset($archive_content))
		$archive_content = read_tgz_file('http://wedge.org/files/fetch_language.php?version=' . urlencode(WEDGE_VERSION) . ';fetch=' . urlencode($_GET['did']), null);

	if (empty($archive_content))
		fatal_lang_error('add_language_error_no_response');

	// Now for each of the files, let's do some *stuff*
	$context['files'] = array(
		'lang' => array(),
		'other' => array(),
	);
	$context['make_writable'] = array();
	foreach ($archive_content as $file)
	{
		$dirname = dirname($file['filename']);
		$filename = basename($file['filename']);
		$extension = substr($filename, strrpos($filename, '.') + 1);

		// Don't do anything with files we don't understand.
		if (!in_array($extension, array('php', 'jpg', 'gif', 'jpeg', 'png', 'txt')))
			continue;

		// Basic data.
		$context_data = array(
			'name' => $filename,
			'destination' => $boarddir . '/' . $file['filename'],
			'generaldest' => $file['filename'],
			'size' => $file['size'],
			// Does chmod status allow the copy?
			'writable' => false,
			// Should we suggest they copy this file?
			'default_copy' => true,
			// Does the file already exist, if so is it same or different?
			'exists' => false,
		);

		// Does the file exist, is it different and can we overwrite?
		if (file_exists($boarddir . '/' . $file['filename']))
		{
			if (is_writable($boarddir . '/' . $file['filename']))
				$context_data['writable'] = true;

			// Finally, do we actually think the content has changed?
			if ($file['size'] == filesize($boarddir . '/' . $file['filename']) && $file['md5'] === md5_file($boarddir . '/' . $file['filename']))
			{
				$context_data['exists'] = 'same';
				$context_data['default_copy'] = false;
			}
			// Attempt to discover newline character differences.
			elseif ($file['md5'] === md5(preg_replace("~[\r]?\n~", "\r\n", file_get_contents($boarddir . '/' . $file['filename']))))
			{
				$context_data['exists'] = 'same';
				$context_data['default_copy'] = false;
			}
			else
				$context_data['exists'] = 'different';
		}
		// No overwrite?
		else
		{
			// Can we at least stick it in the directory...
			if (is_writable($boarddir . '/' . $dirname))
				$context_data['writable'] = true;
		}

		// I love PHP files, that's why I'm a developer and not an artistic type spending my time drinking absinth and living a life of sin...
		if ($extension == 'php' && preg_match('~\w+\.\w+?\.php~', $filename))
		{
			$context_data += array(
				'version' => '??',
				'cur_version' => false,
				'version_compare' => 'newer',
			);

			list ($name, $language) = explode('.', $filename);

			// Let's get the new version, I like versions, they tell me that I'm up to date.
			if (preg_match('~\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '~i', $file['preview'], $match) == 1)
				$context_data['version'] = $match[1];

			// Now does the old file exist - if so what is it's version?
			if (file_exists($boarddir . '/' . $file['filename']))
			{
				// OK - what is the current version?
				$fp = fopen($boarddir . '/' . $file['filename'], 'rb');
				$header = fread($fp, 768);
				fclose($fp);

				// Find the version.
				if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1)
				{
					$context_data['cur_version'] = $match[1];

					// How does this compare?
					if ($context_data['cur_version'] == $context_data['version'])
						$context_data['version_compare'] = 'same';
					elseif ($context_data['cur_version'] > $context_data['version'])
						$context_data['version_compare'] = 'older';

					// Don't recommend copying if the version is the same.
					if ($context_data['version_compare'] != 'newer')
						$context_data['default_copy'] = false;
				}
			}

			// Add the context data to the main set.
			$context['files']['lang'][] = $context_data;
		}
		else
		{
			// If we think it's a theme thing, work out what the theme is.
			if (substr($dirname, 0, 6) == 'Themes' && preg_match('~Themes[\\/]([^\\/]+)[\\/]~', $dirname, $match))
				$theme_name = $match[1];
			else
				$theme_name = 'misc';

			// Assume it's an image, could be an acceptance note etc but rare.
			$context['files']['images'][$theme_name][] = $context_data;
		}

		// Collect together all non-writable areas.
		if (!$context_data['writable'])
			$context['make_writable'][] = $context_data['destination'];
	}

	// So, I'm a perfectionist - let's get the theme names.
	$theme_indexes = array();
	foreach ($context['files']['images'] as $k => $dummy)
		$indexes[] = $k;

	$context['theme_names'] = array();
	if (!empty($indexes))
	{
		$value_data = array(
			'query' => array(),
			'params' => array(),
		);

		foreach ($indexes as $k => $index)
		{
			$value_data['query'][] = 'value LIKE {string:value_' . $k . '}';
			$value_data['params']['value_' . $k] = '%' . $index;
		}

		$request = wesql::query('
			SELECT id_theme, value
			FROM {db_prefix}themes
			WHERE id_member = {int:no_member}
				AND variable = {string:theme_dir}
				AND (' . implode(' OR ', $value_data['query']) . ')',
			array_merge($value_data['params'], array(
				'no_member' => 0,
				'theme_dir' => 'theme_dir',
				'index_compare_explode' => 'value LIKE \'%' . implode('\' OR value LIKE \'%', $indexes) . '\'',
			))
		);
		$themes = array();
		while ($row = wesql::fetch_assoc($request))
		{
			// Find the right one.
			foreach ($indexes as $index)
				if (strpos($row['value'], $index) !== false)
					$themes[$row['id_theme']] = $index;
		}
		wesql::free_result($request);

		if (!empty($themes))
		{
			// Now we have the id_theme we can get the pretty description.
			$request = wesql::query('
				SELECT id_theme, value
				FROM {db_prefix}themes
				WHERE id_member = {int:no_member}
					AND variable = {string:name}
					AND id_theme IN ({array_int:theme_list})',
				array(
					'theme_list' => array_keys($themes),
					'no_member' => 0,
					'name' => 'name',
				)
			);
			while ($row = wesql::fetch_assoc($request))
			{
				// Now we have it...
				$context['theme_names'][$themes[$row['id_theme']]] = $row['value'];
			}
			wesql::free_result($request);
		}
	}

	// Before we go to far can we make anything writable, eh, eh?
	if (!empty($context['make_writable']))
	{
		// What is left to be made writable?
		$file_status = create_chmod_control($context['make_writable']);
		$context['still_not_writable'] = $file_status['files']['notwritable'];

		// Mark those which are now writable as such.
		foreach ($context['files'] as $type => $data)
		{
			if ($type == 'lang')
			{
				foreach ($data as $k => $file)
					if (!$file['writable'] && !in_array($file['destination'], $context['still_not_writable']))
						$context['files'][$type][$k]['writable'] = true;
			}
			else
			{
				foreach ($data as $th => $files)
					foreach ($files as $k => $file)
						if (!$file['writable'] && !in_array($file['destination'], $context['still_not_writable']))
							$context['files'][$type][$th][$k]['writable'] = true;
			}
		}

		// Are we going to need more language stuff?
		if (!empty($context['still_not_writable']))
			loadLanguage('Packages');
	}

	// This is the list for the main files.
	$listOptions = array(
		'id' => 'lang_main_files_list',
		'title' => $txt['languages_download_main_files'],
		'get_items' => array(
			'function' => create_function('', '
				global $context;
				return $context[\'files\'][\'lang\'];
			'),
		),
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['languages_download_filename'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $context, $txt;

						return \'<strong>\' . $rowData[\'name\'] . \'</strong><div class="smalltext">\' . $txt[\'languages_download_dest\'] . \': \' . $rowData[\'destination\'] . \'</div>\' . ($rowData[\'version_compare\'] == \'older\' ? \'<br>\' . $txt[\'languages_download_older\'] : \'\');
					'),
				),
			),
			'writable' => array(
				'header' => array(
					'value' => $txt['languages_download_writable'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						return \'<span style="color: \' . ($rowData[\'writable\'] ? \'green\' : \'red\') . \'">\' . ($rowData[\'writable\'] ? $txt[\'yes\'] : $txt[\'no\']) . \'</span>\';
					'),
					'style' => 'text-align: center',
				),
			),
			'version' => array(
				'header' => array(
					'value' => $txt['languages_download_version'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						return \'<span style="color: \' . ($rowData[\'version_compare\'] == \'older\' ? \'red\' : ($rowData[\'version_compare\'] == \'same\' ? \'orange\' : \'green\')) . \'">\' . $rowData[\'version\'] . \'</span>\';
					'),
				),
			),
			'exists' => array(
				'header' => array(
					'value' => $txt['languages_download_exists'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						return $rowData[\'exists\'] ? ($rowData[\'exists\'] == \'same\' ? $txt[\'languages_download_exists_same\'] : $txt[\'languages_download_exists_different\']) : $txt[\'no\'];
					'),
				),
			),
			'copy' => array(
				'header' => array(
					'value' => $txt['languages_download_copy'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return \'<input type="checkbox" name="copy_file[]" value="\' . $rowData[\'generaldest\'] . \'"\' . ($rowData[\'default_copy\'] ? \' checked\' : \'\') . \'>\';
					'),
					'style' => 'text-align: center; width: 4%',
				),
			),
		),
	);

	// Kill the cache, as it is now invalid..
	if (!empty($settings['cache_enable']))
	{
		cache_put_data('known_languages', null, !empty($settings['cache_enable']) && $settings['cache_enable'] < 1 ? 86400 : 3600);
		// Delete all cached CSS files.
		clean_cache('css');
	}

	loadSource('Subs-List');
	createList($listOptions);

	$context['default_list'] = 'lang_main_files_list';
}

// This lists all the current languages and allows editing of them.
function ModifyLanguages()
{
	global $txt, $context, $scripturl, $language, $boarddir;

	// Setting a new default?
	if (!empty($_POST['set_default']) && !empty($_POST['def_language']))
	{
		checkSession();

		$languages = getLanguages();
		if ($_POST['def_language'] != $language && isset($languages[$_POST['def_language']]))
		{
			loadSource('Subs-Admin');
			updateSettingsFile(array('language' => '\'' . $_POST['def_language'] . '\''));
			$language = $_POST['def_language'];
		}
	}

	$listOptions = array(
		'id' => 'language_list',
		'items_per_page' => 20,
		'base_href' => $scripturl . '?action=admin;area=languages',
		'cat' => $txt['edit_languages'],
		'get_items' => array(
			'function' => 'list_getLanguages',
		),
		'get_count' => array(
			'function' => 'list_getNumLanguages',
		),
		'columns' => array(
			'default' => array(
				'header' => array(
					'value' => $txt['languages_default'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return \'<input type="radio" name="def_language" value="\' . $rowData[\'id\'] . \'" \' . ($rowData[\'default\'] ? \'checked\' : \'\') . \' onclick="highlightSelected(\\\'#list_language_list_\' . $rowData[\'id\'] . \'\\\');">\';
					'),
					'style' => 'text-align: center; width: 8%',
				),
			),
			'name' => array(
				'header' => array(
					'value' => $txt['languages_lang_name'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $scripturl, $context;

						return sprintf(\'<a href="%1$s?action=admin;area=languages;sa=editlang;lid=%2$s">%3$s</a>\', $scripturl, $rowData[\'id\'], $rowData[\'name\']);
					'),
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
					'function' => create_function('$rowData', '
						global $txt;

						return $rowData[\'rtl\'] ? $txt[\'languages_orients_rtl\'] : $txt[\'languages_orients_ltr\'];
					'),
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=admin;area=languages',
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '"><input type="submit" name="set_default" value="' . $txt['save'] . '"' . (is_writable($boarddir . '/Settings.php') ? '' : ' disabled') . ' class="save">',
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
	highlightSelected("#list_language_list_' . ($language == '' ? 'english' : $language). '");',
	);

	// Display a warning if we cannot edit the default setting.
	if (!is_writable($boarddir . '/Settings.php'))
		$listOptions['additional_rows'][] = array(
				'position' => 'after_title',
				'value' => $txt['language_settings_writable'],
				'class' => 'smalltext alert',
			);

	loadSource('Subs-List');
	createList($listOptions);

	wetem::load('show_list');
	$context['default_list'] = 'language_list';
}

// How many languages?
function list_getNumLanguages()
{
	return count(getLanguages());
}

// Fetch the actual language information.
function list_getLanguages()
{
	global $theme, $language, $context, $txt;

	$languages = array();
	// Keep our old entries.
	$old_txt = $txt;
	$backup_actual_theme_dir = $theme['actual_theme_dir'];

	// Override these for now.
	$theme['actual_theme_dir'] = $theme['default_theme_dir'];
	getLanguages();

	// Put them back.
	$theme['actual_theme_dir'] = $backup_actual_theme_dir;

	// Get the language files and data...
	foreach ($context['languages'] as $lang)
	{
		// Load the file to get the character set.
		require($theme['default_theme_dir'] . '/languages/index.' . $lang['filename'] . '.php');

		$languages[$lang['filename']] = array(
			'id' => $lang['filename'],
			'count' => 0,
			'default' => $language == $lang['filename'] || ($language == '' && $lang['filename'] == 'english'),
			'locale' => $txt['lang_locale'],
			'name' => '<span class="flag_' . $lang['filename'] . '"></span> ' . $txt['lang_name'],
			'dictionary' => $txt['lang_dictionary'],
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
			$row['lngfile'] = $language;

		if (!isset($languages[$row['lngfile']]) && isset($languages['english']))
			$languages['english']['count'] += $row['num_users'];
		elseif (isset($languages[$row['lngfile']]))
			$languages[$row['lngfile']]['count'] += $row['num_users'];
	}
	wesql::free_result($request);

	// Restore the current users language.
	$txt = $old_txt;

	// Return how many we have.
	return $languages;
}

// Edit language related settings.
function ModifyLanguageSettings($return_config = false)
{
	global $scripturl, $context, $txt, $boarddir, $theme;

	loadSource('ManageServer');

	// Warn the user if the backup of Settings.php failed.
	$settings_not_writable = !is_writable($boarddir . '/Settings.php');
	$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');

	/* If you're writing a mod, it's a bad idea to add things here....
	For each option:
		variable name, description, type (constant), size/possible values, helptext.
	OR	an empty string for a horizontal rule.
	OR	a string for a titled section. */
	$config_vars = array(
		'language' => array('language', $txt['setting_language'], 'file', 'select', array(), null, 'disabled' => $settings_not_writable),
		array('userLanguage', $txt['userLanguage'], 'db', 'check', null, 'userLanguage'),
	);

	if ($return_config)
		return $config_vars;

	// Get our languages. No cache.
	getLanguages(false);
	foreach ($context['languages'] as $lang)
		$config_vars['language'][4][$lang['filename']] = array($lang['filename'], $lang['name']);

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		checkSession();
		saveSettings($config_vars);
		redirectexit('action=admin;area=languages;sa=settings');
	}

	// Setup the template stuff.
	$context['post_url'] = $scripturl . '?action=admin;area=languages;sa=settings;save';
	$context['settings_title'] = $txt['language_settings'];
	$context['save_disabled'] = $settings_not_writable;

	if ($settings_not_writable)
		$context['settings_message'] = '<p class="center"><strong>' . $txt['settings_not_writable'] . '</strong></p><br>';
	elseif ($settings_backup_fail)
		$context['settings_message'] = '<p class="center"><strong>' . $txt['admin_backup_fail'] . '</strong></p><br>';

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

// Edit a particular set of language entries.
function ModifyLanguage()
{
	global $theme, $context, $txt, $settings, $boarddir, $language;

	loadLanguage('ManageSettings');

	// Select the languages tab.
	$context['menu_data_' . $context['admin_menu_id']]['current_subsection'] = 'edit';
	$context['page_title'] = $txt['edit_languages'];
	wetem::load('modify_language_entries');

	$context['lang_id'] = $_GET['lid'];
	if (empty($_REQUEST['tfid']) || strpos($_REQUEST['tfid'], '|') === false)
		list ($theme_id, $file_id) = array(1, '');
	else
	{
		$parts = explode('|', $_REQUEST['tfid']);
		if (count($parts) == 2)
			list ($theme_id, $file_id) = $parts;
		else
		{
			// In plugins, the entry supplied is not theme_id|lang file, but plugin_id|path|lang
			$theme_id = array_shift($parts);
			$file_id = array_pop($parts);
			$path = implode('/', $parts);
		}
	}
	if (!isset($path))
		$path = '';
	$path .= '/';

	// Clean the ID - just in case.
	preg_match('~([A-Za-z0-9_-]+)~', $context['lang_id'], $matches);
	$context['lang_id'] = $matches[1];

	// Get all the theme data.
	$request = wesql::query('
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
			AND id_member = {int:no_member}
			AND variable IN ({string:name}, {string:theme_dir})',
		array(
			'default_theme' => 1,
			'no_member' => 0,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
		)
	);
	$themes = array(
		1 => array(
			'name' => $txt['dvc_default'],
			'theme_dir' => $theme['default_theme_dir'],
		),
	);
	while ($row = wesql::fetch_assoc($request))
		$themes[$row['id_theme']][$row['variable']] = $row['value'];
	wesql::free_result($request);

	// This will be where we look
	$lang_dirs = array();
	// Check we have themes with a path and a name - just in case - and add the path.
	foreach ($themes as $id => $data)
	{
		if (count($data) != 2)
			unset($themes[$id]);
		elseif (is_dir($data['theme_dir'] . '/languages'))
			$lang_dirs[$id] = $data['theme_dir'] . '/languages';

		// How about image directories?
		if (is_dir($data['theme_dir'] . '/images/' . $context['lang_id']))
			$images_dirs[$id] = $data['theme_dir'] . '/images/' . $context['lang_id'];
	}

	// Now add the possible permutations for plugins. This is pretty hairy stuff.
	// To avoid lots of rewriting that really isn't that necessary, we can reuse the code given here, just with a little crafty manipulation.
	if (!empty($context['plugins_dir']))
	{
		foreach ($context['plugins_dir'] as $plugin_id => $plugin_path)
		{
			$themes[$plugin_id] = array(
				'name' => substr(strrchr($plugin_id, ':'), 1),
				'theme_dir' => $plugin_path,
			);
			$lang_dirs[$plugin_id] = array('' => $plugin_path);
			// We really might as well use SPL for this. I mean, we could do it otherwise but this is almost certainly faster.
			$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_path), RecursiveIteratorIterator::SELF_FIRST);
			foreach ($objects as $name => $object)
				if (is_dir($name))
				{
					$local_path = str_replace($plugin_path, '', $name) . '|';
					$lang_dirs[$plugin_id][$local_path] = $name;
				}

			if (empty($lang_dirs[$plugin_id]))
				unset($lang_dirs[$plugin_id], $themes[$plugin_id]);
		}
	}

	if (isset($context['plugins_dir'][$theme_id]))
		$current_file = $file_id ? $context['plugins_dir'][$theme_id] . $path . $file_id . '.' . $context['lang_id'] . '.php' : '';
	else
		$current_file = $file_id ? $lang_dirs[$theme_id] . $path . $file_id . '.' . $context['lang_id'] . '.php' : '';

	// Now for every theme get all the files and stick them in context!
	$context['possible_files'] = array();
	foreach ($lang_dirs as $th => $theme_dirs)
	{
		// Depending on where we came from, we might be looking at a single folder or a plugin's potentially many subfolders.
		// If we're looking at a plugin, the array will be the possible folders for the prefixing as array keys, with the values as full paths
		// and the prefixing keys will contain the | delimiter as needed.
		if (!is_array($theme_dirs))
			$theme_dirs = array('' => $theme_dirs);

		foreach ($theme_dirs as $path_prefix => $theme_dir)
		{
			// Open it up.
			$dir = dir($theme_dir);
			while ($entry = $dir->read())
			{
				// We're only after the files for this language.
				if (preg_match('~^([A-Za-z0-9_-]+)\.' . $context['lang_id'] . '\.php$~', $entry, $matches) == 0)
					continue;

				// !! Temp!
				if ($matches[1] == 'EmailTemplates' || $matches[1] == 'Agreement')
					continue;

				if (!isset($context['possible_files'][$th]))
					$context['possible_files'][$th] = array(
						'id' => $th,
						'name' => $themes[$th]['name'],
						'files' => array(),
					);

				$context['possible_files'][$th]['files'][] = array(
					'id' => $path_prefix . $matches[1],
					'name' => isset($txt['lang_file_desc_' . $matches[1]]) ? $txt['lang_file_desc_' . $matches[1]] : $matches[1],
					'selected' => $theme_id == $th && $file_id == $matches[1],
				);
			}
			$dir->close();
		}
	}

	// We no longer wish to speak this language.
	if (!empty($_POST['delete_main']) && $context['lang_id'] != 'english')
	{
		checkSession();

		// !!! Todo: FTP Controls?
		loadSource('Subs-Package');

		// Second, loop through the array to remove the files.
		foreach ($lang_dirs as $curPath)
		{
			foreach ($context['possible_files'][1]['files'] as $lang)
				if (file_exists($curPath . '/' . $lang['id'] . '.' . $context['lang_id'] . '.php'))
					unlink($curPath . '/' . $lang['id'] . '.' . $context['lang_id'] . '.php');

			// Check for the email template.
			if (file_exists($curPath . '/EmailTemplates.' . $context['lang_id'] . '.php'))
				unlink($curPath . '/EmailTemplates.' . $context['lang_id'] . '.php');
		}

		// Third, the agreement file.
		if (file_exists($boarddir . '/agreement.' . $context['lang_id'] . '.txt'))
			unlink($boarddir . '/agreement.' . $context['lang_id'] . '.txt');

		// Fourth, a related images folder?
		foreach ($images_dirs as $curPath)
			if (is_dir($curPath))
				deltree($curPath);

		// Fifth, members can no longer use this language.
		wesql::query('
			UPDATE {db_prefix}members
			SET lngfile = {string:empty_string}
			WHERE lngfile = {string:current_language}',
			array(
				'empty_string' => '',
				'current_language' => $context['lang_id'],
			)
		);

		// Sixth, update getLanguages() cache.
		if (!empty($settings['cache_enable']))
		{
			cache_put_data('known_languages', null, !empty($settings['cache_enable']) && $settings['cache_enable'] < 1 ? 86400 : 3600);
			// Delete all cached CSS files.
			clean_cache('css');
		}

		// Seventh, if we deleted the default language, set us back to English?
		if ($context['lang_id'] == $language)
		{
			loadSource('Subs-Admin');
			$language = 'english';
			updateSettingsFile(array('language' => '\'' . $language . '\''));
		}

		// Eighth, get out of here.
		redirectexit('action=admin;area=languages;sa=edit;' . $context['session_query']);
	}

	// Saving primary settings?
	$madeSave = false;
	if (!empty($_POST['save_main']) && !$current_file)
	{
		checkSession();

		// Read in the current file.
		$current_data = implode('', file($theme['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php'));
		// These are the replacements. old => new
		$replace_array = array(
			'~\$txt\[\'lang_locale\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_locale\'] = \'' . addslashes($_POST['locale']) . '\';',
			'~\$txt\[\'lang_dictionary\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_dictionary\'] = \'' . addslashes($_POST['dictionary']) . '\';',
			'~\$txt\[\'lang_spelling\'\]\s=\s(\'|")[^\r\n]+~' => '$txt[\'lang_spelling\'] = \'' . addslashes($_POST['spelling']) . '\';',
			'~\$txt\[\'lang_rtl\'\]\s=\s[A-Za-z0-9]+;~' => '$txt[\'lang_rtl\'] = ' . (!empty($_POST['rtl']) ? 'true' : 'false') . ';',
		);
		$current_data = preg_replace(array_keys($replace_array), array_values($replace_array), $current_data);
		file_put_contents($theme['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php', $current_data);

		$madeSave = true;
	}

	// Quickly load index language entries.
	$old_txt = $txt;
	require($theme['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php');
	$context['lang_file_not_writable_message'] = is_writable($theme['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php') ? '' : sprintf($txt['lang_file_not_writable'], $theme['default_theme_dir'] . '/languages/index.' . $context['lang_id'] . '.php');
	// Setup the primary settings context.
	$context['primary_settings'] = array(
		'name' => westr::ucwords(strtr($context['lang_id'], array('_' => ' ', '-utf8' => ''))),
		'locale' => $txt['lang_locale'],
		'dictionary' => $txt['lang_dictionary'],
		'spelling' => $txt['lang_spelling'],
		'rtl' => $txt['lang_rtl'],
	);

	// Restore normal service.
	$txt = $old_txt;

	// Are we saving?
	$save_strings = array();
	if (isset($_POST['save_entries']) && !empty($_POST['entry']))
	{
		checkSession();

		// Clean each entry!
		foreach ($_POST['entry'] as $k => $v)
		{
			// Only try to save if it's changed!
			if ($_POST['entry'][$k] != $_POST['comp'][$k])
				$save_strings[$k] = cleanLangString($v, false);
		}
	}

	// If we are editing a file work away at that.
	if ($current_file)
	{
		$context['entries_not_writable_message'] = is_writable($current_file) ? '' : sprintf($txt['lang_entries_not_writable'], $current_file);

		$entries = array();
		// We can't just require it I'm afraid - otherwise we pass in all kinds of variables!
		$multiline_cache = '';
		foreach (file($current_file) as $line)
		{
			// Got a new entry?
			if ($line[0] == '$' && !empty($multiline_cache))
			{
				preg_match('~\$(helptxt|txt)\[\'(.+)\'\]\s=\s(.+);~', strtr($multiline_cache, array("\n" => '', "\t" => '')), $matches);
				if (!empty($matches[3]))
				{
					$entries[$matches[2]] = array(
						'type' => $matches[1],
						'full' => $matches[0],
						'entry' => $matches[3],
					);
					$multiline_cache = '';
				}
			}
			$multiline_cache .= $line . "\n";
		}
		// Last entry to add?
		if ($multiline_cache)
		{
			preg_match('~\$(helptxt|txt)\[\'(.+)\'\]\s=\s(.+);~', strtr($multiline_cache, array("\n" => '', "\t" => '')), $matches);
			if (!empty($matches[3]))
				$entries[$matches[2]] = array(
					'type' => $matches[1],
					'full' => $matches[0],
					'entry' => $matches[3],
				);
		}

		// These are the entries we can definitely save.
		$final_saves = array();

		$context['file_entries'] = array();
		foreach ($entries as $entryKey => $entryValue)
		{
			// Ignore some things we set separately.
			$ignore_files = array('lang_locale', 'lang_dictionary', 'lang_spelling', 'lang_rtl');
			if (in_array($entryKey, $ignore_files))
				continue;

			// These are arrays that need breaking out.
			$arrays = array('days', 'days_short', 'months', 'months_titles', 'months_short');
			if (in_array($entryKey, $arrays))
			{
				// Get off the first bits.
				$entryValue['entry'] = substr($entryValue['entry'], strpos($entryValue['entry'], '(') + 1, strrpos($entryValue['entry'], ')') - strpos($entryValue['entry'], '('));
				$entryValue['entry'] = explode(',', strtr($entryValue['entry'], array(' ' => '')));

				// Now create an entry for each item.
				$cur_index = 0;
				$save_cache = array(
					'enabled' => false,
					'entries' => array(),
				);
				foreach ($entryValue['entry'] as $id => $subValue)
				{
					// Is this a new index?
					if (preg_match('~^(\d+)~', $subValue, $matches))
					{
						$cur_index = $matches[1];
						$subValue = substr($subValue, strpos($subValue, '\''));
					}

					// Clean up some bits.
					$subValue = strtr($subValue, array('"' => '', '\'' => '', ')' => ''));

					// Can we save?
					if (isset($save_strings[$entryKey . '-+- ' . $cur_index]))
					{
						$save_cache['entries'][$cur_index] = strtr($save_strings[$entryKey . '-+- ' . $cur_index], array('\'' => ''));
						$save_cache['enabled'] = true;
					}
					else
						$save_cache['entries'][$cur_index] = $subValue;

					$context['file_entries'][] = array(
						'key' => $entryKey . '-+- ' . $cur_index,
						'value' => $subValue,
						'rows' => 1,
					);
					$cur_index++;
				}

				// Do we need to save?
				if ($save_cache['enabled'])
				{
					// Format the string, checking the indexes first.
					$items = array();
					$cur_index = 0;
					foreach ($save_cache['entries'] as $k2 => $v2)
					{
						// Manually show the custom index.
						if ($k2 != $cur_index)
						{
							$items[] = $k2 . ' => \'' . $v2 . '\'';
							$cur_index = $k2;
						}
						else
							$items[] = '\'' . $v2 . '\'';

						$cur_index++;
					}
					// Now create the string!
					$final_saves[$entryKey] = array(
						'find' => $entryValue['full'],
						'replace' => '$' . $entryValue['type'] . '[\'' . $entryKey . '\'] = array(' . implode(', ', $items) . ');',
					);
				}
			}
			else
			{
				// Saving?
				if (isset($save_strings[$entryKey]) && $save_strings[$entryKey] != $entryValue['entry'])
				{
					// !!! Fix this properly.
					if ($save_strings[$entryKey] == '')
						$save_strings[$entryKey] = '\'\'';

					// Set the new value.
					$entryValue['entry'] = $save_strings[$entryKey];
					// And we know what to save now!
					$final_saves[$entryKey] = array(
						'find' => $entryValue['full'],
						'replace' => '$' . $entryValue['type'] . '[\'' . $entryKey . '\'] = ' . $save_strings[$entryKey] . ';',
					);
				}

				$editing_string = cleanLangString($entryValue['entry'], true);
				$context['file_entries'][] = array(
					'key' => $entryKey,
					'value' => $editing_string,
					'rows' => (int) (strlen($editing_string) / 38) + substr_count($editing_string, "\n") + 1,
				);
			}
		}

		// Any saves to make?
		if (!empty($final_saves))
		{
			checkSession();

			$file_contents = implode('', file($current_file));
			foreach ($final_saves as $save)
				$file_contents = strtr($file_contents, array($save['find'] => $save['replace']));

			// Save the actual changes.
			file_put_contents($current_file, $file_contents);

			$madeSave = true;
		}

		// Another restore.
		$txt = $old_txt;
	}

	// If we saved, redirect.
	if ($madeSave)
		redirectexit('action=admin;area=languages;sa=editlang;lid=' . $context['lang_id']);
}

// This function could be two functions - either way it cleans language entries to/from display.
function cleanLangString($string, $to_display = true)
{
	// If going to display we make sure it doesn't have any HTML in it - etc.
	$new_string = '';
	if ($to_display)
	{
		// Are we in a string (0 = no, 1 = single quote, 2 = parsed)
		$in_string = 0;
		$is_escape = false;
		for ($i = 0; $i < strlen($string); $i++)
		{
			// Handle ecapes first.
			if ($string{$i} == '\\')
			{
				// Toggle the escape.
				$is_escape = !$is_escape;
				// If we're now escaped don't add this string.
				if ($is_escape)
					continue;
			}
			// Special case - parsed string with line break etc?
			elseif (($string{$i} == 'n' || $string{$i} == 't') && $in_string == 2 && $is_escape)
			{
				// Put the escape back...
				$new_string .= $string{$i} == 'n' ? "\n" : "\t";
				$is_escape = false;
				continue;
			}
			// Have we got a single quote?
			elseif ($string{$i} == '\'')
			{
				// Already in a parsed string, or escaped in a linear string, means we print it - otherwise something special.
				if ($in_string != 2 && ($in_string != 1 || !$is_escape))
				{
					// Is it the end of a single quote string?
					if ($in_string == 1)
						$in_string = 0;
					// Otherwise it's the start!
					else
						$in_string = 1;

					// Don't actually include this character!
					continue;
				}
			}
			// Otherwise a double quote?
			elseif ($string{$i} == '"')
			{
				// Already in a single quote string, or escaped in a parsed string, means we print it - otherwise something special.
				if ($in_string != 1 && ($in_string != 2 || !$is_escape))
				{
					// Is it the end of a double quote string?
					if ($in_string == 2)
						$in_string = 0;
					// Otherwise it's the start!
					else
						$in_string = 2;

					// Don't actually include this character!
					continue;
				}
			}
			// A join/space outside of a string is simply removed.
			elseif ($in_string == 0 && (empty($string{$i}) || $string{$i} == '.'))
				continue;
			// Start of a variable?
			elseif ($in_string == 0 && $string{$i} == '$')
			{
				// Find the whole of it!
				preg_match('~([$\w\'[\]-]+)~', substr($string, $i), $matches);
				if (!empty($matches[1]))
				{
					// Come up with some pseudo thing to indicate this is a var.
					// !! Do better than this, please!
					$new_string .= '{%' . $matches[1] . '%}';

					// We're not going to reparse this.
					$i += strlen($matches[1]) - 1;
				}

				continue;
			}
			// Right, if we're outside of a string we have DANGER, DANGER!
			elseif ($in_string == 0)
			{
				continue;
			}

			// Actually add the character to the string!
			$new_string .= $string{$i};
			// If anything was escaped it ain't any longer!
			$is_escape = false;
		}

		// Unhtml then rehtml the whole thing!
		$new_string = htmlspecialchars(un_htmlspecialchars($new_string));
	}
	else
	{
		// Keep track of what we're doing...
		$in_string = 0;
		// This is for deciding whether to HTML a quote.
		$in_html = false;
		for ($i = 0; $i < strlen($string); $i++)
		{
			// Handle line breaks!
			if ($string{$i} == "\n" || $string{$i} == "\t")
			{
				// Are we in a string? Is it the right type?
				if ($in_string == 1)
				{
					// Change type!
					$new_string .= '\' . "\\' . ($string{$i} == "\n" ? 'n' : 't');
					$in_string = 2;
				}
				elseif ($in_string == 2)
					$new_string .= '\\' . ($string{$i} == "\n" ? 'n' : 't');
				// Otherwise start one off - joining if required.
				else
					$new_string .= ($new_string ? ' . ' : '') . '"\\' . ($string{$i} == "\n" ? 'n' : 't');

				continue;
			}
			// We don't do parsed strings apart from for breaks.
			elseif ($in_string == 2)
			{
				$in_string = 0;
				$new_string .= '"';
			}

			// Not in a string yet?
			if ($in_string != 1)
			{
				$in_string = 1;
				$new_string .= ($new_string ? ' . ' : '') . '\'';
			}

			// Is this a variable?
			if ($string{$i} == '{' && $string{$i + 1} == '%' && $string{$i + 2} == '$')
			{
				// Grab the variable.
				preg_match('~\{%([$\'[\]-]+)%\}~', substr($string, $i), $matches);
				if (!empty($matches[1]))
				{
					if ($in_string == 1)
						$new_string .= '\' . ';
					elseif ($new_string)
						$new_string .= ' . ';

					$new_string .= $matches[1];
					$i += strlen($matches[1]) + 3;
					$in_string = 0;
				}

				continue;
			}
			// Is this a lt sign?
			elseif ($string{$i} == '<')
			{
				// Probably HTML?
				if ($string{$i + 1} != ' ')
					$in_html = true;
				// Assume we need an entity...
				else
				{
					$new_string .= '&lt;';
					continue;
				}
			}
			// What about gt?
			elseif ($string{$i} == '>')
			{
				// Will it be HTML?
				if ($in_html)
					$in_html = false;
				// Otherwise we need an entity...
				else
				{
					$new_string .= '&gt;';
					continue;
				}
			}
			// Is it a slash? If so escape it...
			if ($string{$i} == '\\')
				$new_string .= '\\';
			// The infamous double quote?
			elseif ($string{$i} == '"')
			{
				// If we're in HTML we leave it as a quote - otherwise we entity it.
				if (!$in_html)
				{
					$new_string .= '&quot;';
					continue;
				}
			}
			// A single quote?
			elseif ($string{$i} == '\'')
			{
				// Must be in a string so escape it.
				$new_string .= '\\';
			}

			// Finally add the character to the string!
			$new_string .= $string{$i};
		}

		// If we ended as a string then close it off.
		if ($in_string == 1)
			$new_string .= '\'';
		elseif ($in_string == 2)
			$new_string .= '"';
	}

	return $new_string;
}
