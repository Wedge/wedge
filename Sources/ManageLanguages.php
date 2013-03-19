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
	global $context, $txt, $settings;

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
	global $context, $txt;

	loadTemplate('ManageLanguages');

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
					'link' => '<URL>?action=admin;area=languages;sa=downloadlang;did=' . (string) $this_lang->id . ';' . $context['session_query'],
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
	global $context, $boarddir, $txt, $settings;

	loadTemplate('ManageLanguages');
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

		// Otherwise, go go go!
		if (!empty($install_files))
		{
			// !!! @todo: Update with Wedge language files.
			$archive_content = read_tgz_file('http://wedge.org/files/fetch_language.php?version=' . urlencode(WEDGE_VERSION) . ';fetch=' . urlencode($_GET['did']), $boarddir, false, true, $install_files);
			// Make sure the files aren't stuck in the cache.
			package_flush_cache();
			$context['install_complete'] = sprintf($txt['languages_download_complete_desc'], '<URL>?action=admin;area=languages');

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
				AND variable = {literal:theme_dir}
				AND (' . implode(' OR ', $value_data['query']) . ')',
			array_merge($value_data['params'], array(
				'no_member' => 0,
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
					AND variable = {literal:name}
					AND id_theme IN ({array_int:theme_list})',
				array(
					'theme_list' => array_keys($themes),
					'no_member' => 0,
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
	global $txt, $context, $settings, $boarddir, $cachedir;

	if (isset($_GET['cleancache']))
	{
		checkSession();
		foreach (glob($cachedir . '/lang_*.php') as $filename)
			@unlink($filename);
		$context['cache_cleared'] = true;
	}

	// Setting a new default?
	if (!empty($_POST['set_default']) && !empty($_POST['def_language']))
	{
		checkSession();

		$languages = getLanguages();
		if ($_POST['def_language'] != $settings['language'] && isset($languages[$_POST['def_language']]))
		{
			updateSettings(array('language' => $_POST['def_language']));
			$settings['language'] = $_POST['def_language'];
		}
	}

	$listOptions = array(
		'id' => 'language_list',
		'items_per_page' => 20,
		'base_href' => '<URL>?action=admin;area=languages',
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
						global $context;

						return sprintf(\'<a href="<URL>?action=admin;area=languages;sa=editlang;lid=%1$s">%2$s</a>\', $rowData[\'id\'], $rowData[\'name\']);
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
			'href' => '<URL>?action=admin;area=languages',
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
	highlightSelected("#list_language_list_' . ($settings['language'] == '' ? 'english' : $settings['language']). '");',
	);

	loadSource('Subs-List');
	createList($listOptions);

	loadTemplate('ManageLanguages');

	wetem::load('language_home');
}

// How many languages?
function list_getNumLanguages()
{
	return count(getLanguages());
}

// Fetch the actual language information.
function list_getLanguages()
{
	global $theme, $context, $txt, $settings;

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
			'default' => $settings['language'] == $lang['filename'] || ($settings['language'] == '' && $lang['filename'] == 'english'),
			'locale' => $txt['lang_locale'],
			'name' => '<span class="flag_' . $lang['filename'] . '"></span> ' . $txt['lang_name'],
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
	$txt = $old_txt;

	// Return how many we have.
	return $languages;
}

// Edit language related settings.
function ModifyLanguageSettings($return_config = false)
{
	global $context, $txt, $boarddir, $theme;

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
		'language' => array('select', 'language', array()),
		array('check', 'userLanguage'),
	);

	if ($return_config)
		return $config_vars;

	// Get our languages. No cache.
	getLanguages(false);
	foreach ($context['languages'] as $lang)
		$config_vars['language'][2][$lang['filename']] = '&lt;span class="flag_' . $lang['filename'] . '"&gt;&lt;/span&gt; ' . $lang['name'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		checkSession();
		saveDBSettings($config_vars);
		redirectexit('action=admin;area=languages;sa=settings');
	}

	// Setup the template stuff.
	$context['post_url'] = '<URL>?action=admin;area=languages;sa=settings;save';
	$context['settings_title'] = $txt['language_settings'];
	$context['save_disabled'] = $settings_not_writable;

	if ($settings_not_writable)
		$context['settings_message'] = '<p class="center"><strong>' . $txt['settings_not_writable'] . '</strong></p><br>';
	elseif ($settings_backup_fail)
		$context['settings_message'] = '<p class="center"><strong>' . $txt['admin_backup_fail'] . '</strong></p><br>';

	// Fill the config array.
	prepareDBSettingContext($config_vars);
}

// Edit a particular set of language entries.
function ModifyLanguage()
{
	global $theme, $context, $txt, $settings, $boarddir;

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

	$context['linktree'][] = array(
		'name' => $context['languages'][$context['lang_id']]['name'],
		'url' => '<URL>?action=admin;area=languages;sa=editlang;lid=' . $context['lang_id'],
	);

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
		'themes' => array(),
	);

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

	// Get all the theme data.
	$themes = array(
		1 => array(
			'name' => $txt['dvc_default'],
			'theme_dir' => $theme['default_theme_dir'],
		),
	);
	$request = wesql::query('
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE id_theme != {int:default_theme}
			AND id_member = {int:no_member}
			AND variable IN ({literal:name}, {literal:theme_dir})',
		array(
			'default_theme' => 1,
			'no_member' => 0,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$themes[$row['id_theme']][$row['variable']] = $row['value'];
		$context['language_files']['themes'][$row['id_theme']][$row['variable']] = $row['value'];
	}
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
			$context['language_files']['plugins'][$plugin_id] = array(
				'name' => str_replace(array('-', '_'), ' ', substr(strrchr($plugin_id, ':'), 1)),
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
				unset($lang_dirs[$plugin_id], $themes[$plugin_id], $context['language_files']['plugins'][$plugin_id]);
		}
	}

	// Now for every theme get all the files and stick them in context!
	$context['possible_files'] = array();
	foreach ($lang_dirs as $th => $theme_dirs)
	{
		// Depending on where we came from, we might be looking at a single folder or a plugin's potentially many subfolders.
		// If we're looking at a plugin, the array will be the possible folders for the prefixing as array keys, with the values as full paths
		// and the prefixing keys will contain the | delimiter as needed.
		if (!is_array($theme_dirs))
			$theme_dirs = array('' => $theme_dirs);

		// Sift out now where this is likely to go.
		if ($th == 1)
			$dest = 'default';
		elseif (is_numeric($th))
			$dest = 'themes';
		else
			$dest = 'plugins';

		foreach ($theme_dirs as $path_prefix => $theme_dir)
		{
			// Open it up.
			$dir = dir($theme_dir);
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
						'name' => $themes[$th]['name'],
						'files' => array(),
					);

				if ($th == $theme_id && $matches[1] == $file_id)
					$context['selected_file'] = array(
						'source_id' => $theme_id,
						'lang_id' => $path_prefix . $matches[1],
						'name' => isset($txt['lang_file_desc_' . $matches[1]]) ? $txt['lang_file_desc_' . $matches[1]] : $matches[1],
						'path' => (isset($context['plugins_dir'][$theme_id]) ? $context['plugins_dir'][$theme_id] : $lang_dirs[$theme_id]) . $path . $file_id . '.' . $context['lang_id'] . '.php',
					);

				$langfile = isset($txt['lang_file_desc_' . $matches[1]]) ? $txt['lang_file_desc_' . $matches[1]] : $matches[1];

				switch ($dest)
				{
					case 'default':
						$loc = ($matches[1] == 'Admin' || $matches[1] == 'Modlog' || strpos($matches[1], 'Manage') !== false) ? 'admin' : 'main';
						$context['language_files']['default'][$loc]['files'][$path_prefix . $matches[1]] = $langfile;
						break;
					case 'themes':
						$context['language_files']['themes'][$th]['files'][$path_prefix . $matches[1]] = $langfile;
						break;
					case 'plugins':
						$context['language_files']['plugins'][$th]['files'][$path_prefix . $matches[1]] = $langfile;
						break;
				}
			}
			$dir->close();
		}
	}

	// Let's go clean up.
	foreach (array('plugins', 'themes') as $type)
	{
		foreach ($context['language_files'][$type] as $item => $content)
		{
			if (empty($content['files']))
				unset($context['language_files'][$type][$item]);
		}
		if (empty($context['language_files'][$type]))
			unset($context['language_files'][$type]);
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
		if ($context['lang_id'] == $settings['language'])
		{
			updateSettings(array('language' => 'english'));
		}

		// Eighth, get out of here.
		redirectexit('action=admin;area=languages;sa=edit;' . $context['session_query']);
	}

	// If we are editing something, let's send it off. We still have to do all the preceding stuff anyway
	// because it allows us to completely validate that what we're editing exists.
	if (!empty($context['selected_file']))
	{
		return ModifyLanguageEntries();
	}
	else
	{
		wetem::load('modify_language_list');
	}
}

function ModifyLanguageEntries()
{
	global $context, $txt, $helptxt, $cachedir;

	$context['linktree'][] = array(
		'url' => '<URL>?action=admin;area=languages;sa=editlang;lid=' . $context['lang_id'] . ';tfid=' . urlencode($context['selected_file']['source_id'] . '|' . $context['selected_file']['lang_id']),
		'name' => $context['selected_file']['name'],
	);

	$context['entries'] = array();

	// Now we load the existing content.
	$oldtxt = $txt;
	$oldhelptxt = $helptxt;
	$txt = array();
	$helptxt = array();

	// Start by straight up loading the file.
	@include($context['selected_file']['path']);
	foreach ($txt as $k => $v)
		$context['entries']['txt_' . $k] = array(
			'master' => $v,
		);
	foreach ($helptxt as $k => $v)
		$context['entries']['helptxt_' . $k] = array(
			'master' => $v,
		);
	// We can put the original files back now, we care not about the actual information.
	$txt = $oldtxt;
	$helptxt = $oldhelptxt;

	// Now we load everything else. Is this a conventional 'theme' string?
	if (!isset($context['plugins_dir'][$context['selected_file']['source_id']]))
	{
		$request = wesql::query('
			SELECT lang_var, lang_key, lang_string, serial
			FROM {db_prefix}language_changes
			WHERE id_theme = {int:id_theme}
				AND id_lang = {string:lang}
				AND lang_file = {string:lang_file}',
			array(
				'id_theme' => (int) $context['selected_file']['source_id'],
				'lang' => $context['lang_id'],
				'lang_file' => $context['selected_file']['lang_id'],
			)
		);
		while ($row = wesql::fetch_assoc($request))
			if ($row['lang_var'] == 'txt' || $row['lang_var'] == 'helptxt')
				$context['entries'][$row['lang_var'] . '_' . $row['lang_key']]['current'] = $row['serial'] ? @unserialize($row['lang_string']) : $row['lang_string'];
		wesql::free_result($request);
	}
	else
	{
		// !!! We don't know how to handle plugins yet :(
	}

	// There are certain entries we do not allow touching from here. Declared once, but restricted on both loading and saving.
	$restricted_entries = array('txt_lang_name', 'txt_lang_locale', 'txt_lang_dictionary', 'txt_lang_spelling', 'txt_lang_rtl');
	foreach ($restricted_entries as $item)
		unset($context['entries'][$item]);

	if (isset($_GET['eid'], $context['entries'][$_GET['eid']]))
	{
		// So either we're displaying what we're doing, or we're making some changes.
		if (isset($_POST['delete']))
		{
			checkSession();
			if (!isset($context['plugins_dir'][$context['selected_file']['source_id']]))
			{
				$context['selected_file']['source_id'] = (int) $context['selected_file']['source_id'];
				list($lang_var, $actual_key) = explode('_', $_GET['eid'], 2);
				$request = wesql::query('
					DELETE FROM {db_prefix}language_changes
					WHERE id_theme = {int:id_theme}
						AND id_lang = {string:lang}
						AND lang_file = {string:lang_file}
						AND lang_var = {string:lang_var}
						AND lang_key = {string:lang_key}',
					array(
						'id_theme' => $context['selected_file']['source_id'],
						'lang' => $context['lang_id'],
						'lang_file' => $context['selected_file']['lang_id'],
						'lang_var' => $lang_var,
						'lang_key' => $actual_key,
					)
				);

				// Figure out what we're flushing. We don't need to do the *entire* cache, but we do need to do anything that could
				// have been affected by this file. There are some awesome potential cross-contamination possibilities, so be safe.
				foreach (glob($cachedir . '/lang_*_*_' . $context['selected_file']['lang_id'] . '.php') as $filename)
					@unlink($filename);

				// Sorry in advance. This is not a fun process.
				clean_cache('js');

				// OK, so we've removed this one, we can clear the current entry of it then let it fall back to original procedure.
				unset ($context['entries'][$_GET['eid']]['current']);
				wetem::load('modify_entries');
				return;
			}
			else
			{
				// !!! We still don't know how to handle plugins yet! :'(
			}
		}
		elseif (isset($_POST['save']))
		{
			checkSession();

			$id_theme = (int) $context['selected_file']['source_id'];
			$id_lang = $context['lang_id'];
			$lang_file = $context['selected_file']['lang_id'];
			list($lang_var, $lang_key) = explode('_', $_GET['eid'], 2);

			if (!empty($_POST['entry']))
			{
				$lang_string = $_POST['entry']; // I only wish I could sanitize this, but there's a ton of strings that can't be sanitized. :(
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
				array('id_theme' => 'int', 'id_lang' => 'string', 'lang_file' => 'string', 'lang_var' => 'string', 'lang_key' => 'string', 'lang_string' => 'string', 'serial' => 'int'),
				array($id_theme, $id_lang, $lang_file, $lang_var, $lang_key, $lang_string, $serial),
				array('id_theme', 'id_lang', 'lang_file', 'lang_var', 'lang_key')
			);

			// Figure out what we're flushing. We don't need to do the *entire* cache, but we do need to do anything that could have been affected by this file. There are some awesome potential cross-contamination possibilities, so be safe.
			foreach (glob($cachedir . '/lang_*_*_' . $context['selected_file']['lang_id'] . '.php') as $filename)
				@unlink($filename);

			// Sorry in advance. This is not a fun process.
			clean_cache('js');

			// Just in case it makes any difference.
			if ($lang_var == 'txt')
				$txt[$lang_key] = $context['entries'][$_GET['eid']]['current'];
			elseif ($lang_var == 'helptxt')
				$helptxt[$lang_key] = $context['entries'][$_GET['eid']]['current'];

			wetem::load('modify_entries');
			return;
		}

		$context['entry'] = $context['entries'][$_GET['eid']];
		$context['entry']['id'] = $_GET['eid'];

		unset ($context['entries']);
		wetem::load('modify_individual_entry');
	}
	else
		wetem::load('modify_entries');
}
