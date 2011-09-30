<?php
/**
 * Wedge
 *
 * Handle all key aspects relating to plugins and their management.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function PluginsHome()
{
	global $scripturl, $txt, $context;

	// General stuff
	loadLanguage('ManagePlugins');
	loadTemplate('ManagePlugins');
	define('WEDGE_PLUGIN', 1); // Any scripts that are run from here, should *really* test that this is defined and exit if not.

	// Because our good friend the GenericMenu complains otherwise.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['plugin_manager'],
		'description' => $txt['plugin_manager_desc'],
		'tabs' => array(
			'plugins' => array(
			),
			'find' => array(
			),
		),
	);

	$subActions = array(
		'list' => 'ListPlugins',
		'readme' => 'PluginReadme',
		'enable' => 'EnablePlugin',
		'disable' => 'DisablePlugin',
		'remove' => 'RemovePlugin',
	);

	// By default do the basic settings.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : array_shift(array_keys($subActions));
	$context['sub_action'] = $_REQUEST['sa'];

	$subActions[$_REQUEST['sa']]();
}

function ListPlugins()
{
	global $scripturl, $txt, $context, $pluginsdir;

	loadBlock('browse');
	$context['page_title'] = $txt['plugin_manager'];
	getLanguages();

	$context['available_plugins'] = array();

	$hooks = knownHooks();
	$mysql_version = null;

	$check_for = array('php', 'mysql');

	// 1. Step through the directory, figure out what's there and what's not, and store everything that's valid in $context['available_plugins'].
	// Since we explicitly want folders at this point, we don't want to use scandir.
	if (!empty($pluginsdir))
	{
		//libxml_use_internal_errors(true);
		if ($handle = opendir($pluginsdir))
		{
			while (($folder = readdir($handle)) !== false)
			{
				if ($folder[0] == '.' || strpos($folder, ',') !== false)
					continue;

				if (filetype($pluginsdir . '/' . $folder) == 'dir' && file_exists($pluginsdir . '/' . $folder . '/plugin-info.xml'))
				{
					$manifest = simplexml_load_file($pluginsdir . '/' . $folder . '/plugin-info.xml');
					if ($manifest === false || empty($manifest->name) || empty($manifest->author) || empty($manifest->version))
						continue;

					$plugin = array(
						'folder' => $folder,
						'name' => westr::htmlspecialchars((string) $manifest->name),
						'author' => westr::htmlspecialchars($manifest->author),
						'author_url' => (string) $manifest->author['url'],
						'author_email' => (string) $manifest->author['email'],
						'website' => (string) $manifest->website,
						'version' => westr::htmlspecialchars($manifest->version),
						'description' => westr::htmlspecialchars($manifest->description),
						'hooks' => array(),
						'provide_hooks' => array(),
						'readmes' => array(),
						'acp_url' => $manifest->{'acp-url'},
						'install_errors' => array(),
						'enabled' => false,
					);

					// Do some sanity checking, to validate that any given URL or email are at least vaguely legal.
					if (empty($plugin['author_url']) || (strpos($plugin['author_url'], 'http://') !== 0 && strpos($plugin['author_url'], 'https://') !== 0))
						$plugin['author_url'] = '';
					if (empty($plugin['website']) || (strpos($plugin['website'], 'http://') !== 0 && strpos($plugin['website'], 'https://') !== 0))
						$plugin['website'] = '';
					if (empty($plugin['author_email']) || !is_valid_email($plugin['author_email']))
						$plugin['author_email'] = '';

					$min_versions = array();
					if (!empty($manifest->{'min-versions'}))
					{
						$versions = $manifest->{'min-versions'}->children();
						foreach ($versions as $version)
							if (in_array($version->getName(), $check_for))
								$min_versions[$version->getName()] = (string) $version;
					}

					// So, minimum versions? PHP?
					if (!empty($min_versions['php']))
					{
						// Users might insert 5 or 5.3 or 5.3.0. version_compare considers 5.3 to be less than 5.3.0. So we have to normalize it.
						preg_match('~^\d(\.\d){2}~', $min_versions['php'] . '.0.0', $matches);
						if (!empty($matches[0]) && version_compare($matches[0], PHP_VERSION, '>='))
							$plugin['install_errors']['minphp'] = sprintf($txt['install_error_minphp'], $matches[0], PHP_VERSION);
					}

					// MySQL?
					if (!empty($min_versions['mysql']))
					{
						if (is_null($mysql_version))
						{
							// Only get this once, and then if we have to. Save the query-whales!
							wesql::extend('extra');
							$mysql_version = wedbExtra::get_version();
						}

						preg_match('~^\d(\.\d){2}~', $min_versions['mysql'] . '.0.0', $matches);
						if (!empty($matches[0]) && version_compare($matches[0], $mysql_version, '>='))
							$plugin['install_errors']['minmysql'] = sprintf($txt['install_error_minmysql'], $matches[0], $mysql_version);
					}

					// Required functions?
					if (!empty($manifest->{'required-functions'}))
					{
						$required_functions = testRequiredFunctions($manifest->{'required_functions'});
						if (!empty($required_functions))
							$plugin['install_errors']['reqfunc'] = sprintf($txt['install_error_reqfunc'], implode(', ', $required_functions));
					}

					// Hooks associated with this plugin.
					if (!empty($manifest->hooks))
					{
						$hooks_listed = $manifest->hooks->children();
						foreach ($hooks_listed as $each_hook)
						{
							switch ($each_hook->getName())
							{
								case 'function':
									if (!empty($each_hook['point']))
										$plugin['hooks']['function'][] = $each_hook['point'];
									break;
								case 'language':
									if (!empty($each_hook['point']))
										$plugin['hooks']['language'][] = $each_hook['point'];
									break;
								case 'provides':
									$provided_hooks = $each_hook->children();

									foreach ($provided_hooks as $hook)
										if (!empty($hook['type']) && ((string) $hook['type'] == 'function' || (string) $hook['type'] == 'language'))
										{
											// Only deal with hooks provided by a plugin if it's enabled.
											if (in_array($folder, $context['enabled_plugins']))
												$hooks[(string) $hook['type']][] = (string) $hook;
											
											if (empty($plugin['provide_hooks'][(string) $hook['type']]))
												$plugin['provide_hooks'][(string) $hook['type']] = array();
											$plugin['provide_hooks'][(string) $hook['type']][] = (string) $hook;
										}
									break;
							}
						}
					}

					// Readme files. $context['languages'] contains all the languages we have - no sense offering readmes we don't have installed languages for.
					if (!empty($manifest->readmes))
					{
						$readmes = $manifest->readmes->children();
						foreach ($readmes as $readme)
						{
							if ($readme->getName() !== 'readme')
								continue;
							$lang = (string) $readme['lang'];
							if (!empty($lang) && isset($context['languages'][$lang]))
								$plugin['readmes'][$lang] = true;
						}

						// We'll put them in alphabetic order, but English first because that's guaranteed to be available as an installed language.
						ksort($plugin['readmes']);
						if (isset($plugin['readmes']['english']))
						{
							unset($plugin['readmes']['english']);
							$plugin['readmes'] = array_merge(array('english' => true), $plugin['readmes']);
						}
					}

					// OK, add to the list.
					$context['available_plugins'][$plugin['name'] . $folder] = $plugin;
				}
			}
			closedir($handle);
		}
	}

	// 1a. We want the packages in a nice order. The array key is the name of the plugin, then with the folder appended in the case of duplicates.
	ksort($context['available_plugins']);

	// 2. Having passed through everything once, we will have figured out what dependencies and provisions there are. Apply that too.
	foreach ($context['available_plugins'] as $id => $plugin)
	{
		foreach ($plugin['hooks'] as $hook_type => $required_hooks)
		{
			if (!empty($context['available_plugins'][$id]['install_errors']['missinghook']))
				break;

			// Hmm, just make sure there's actually something available for what we're doing to do in a minute.
			if (empty($plugin['provide_hooks'][$hook_type]))
				$plugin['provide_hooks'][$hook_type] = array();

			$missing_hooks = array_diff($required_hooks, $hooks[$hook_type], $plugin['provide_hooks'][$hook_type]);
			if (count($missing_hooks) > 0)
			{
				$context['available_plugins'][$id]['install_errors']['missinghook'] = $txt['install_error_missinghook'] . ' (' . implode(', ', $missing_hooks) . ')';
				break 2;
			}
		}
	}

	// 3. Make sure to deactivate any plugins that were activated but now shouldn't be. This includes general cleanup too.
	if (!empty($context['enabled_plugins']))
	{
		$original = $context['enabled_plugins'];
		$context['enabled_plugins'] = array();
		foreach ($context['available_plugins'] as $id => $plugin)
			if (empty($plugin['install_errors']) && in_array($plugin['folder'], $original))
			{
				$context['enabled_plugins'][] = $plugin['folder'];
				$context['available_plugins'][$id]['enabled'] = true;
			}

		updateSettings(array('enabled_plugins' => implode(',', $context['enabled_plugins'])));
	}
}

function PluginReadme()
{
	global $context, $txt, $pluginsdir;

	// Let's start and fire up the template. We're reusing the nice popup, so load templates and set up various things.
	loadTemplate('Help');
	loadLanguage('Help');
	hideChrome();
	$context['page_title'] = $txt['plugin_manager'] . ' - ' . $txt['help'];
	loadBlock('popup');

	// Did they specify a plugin, and is it a valid one?
	$valid = true;

	if (isset($_GET['plugin']))
	{
		// So, one's specified. Make sure it doesn't start with a . (which rules out ., .. and 'hidden' files) and also that it doesn't contain directory separators. No sneaky trying to get out the box.
		if (strpos($_GET['plugin'], DIRECTORY_SEPARATOR) !== false || $_GET['plugin'][0] == '.' || !file_exists($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml'))
			$valid = false;
	}
	else
		$valid = false;

	// Did they specify a language, and is it valid?
	if (isset($_GET['lang']))
	{
		getLanguages();
		if (!isset($context['languages'][$_GET['lang']]))
			$valid = false;
	}
	else
		$valid = false;

	// Lastly, does the package know this readme?
	if ($valid)
	{
		$manifest = simplexml_load_file($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml');

		if (!empty($manifest->readmes))
		{
			$readmes = $manifest->readmes->children();
			$readme_found = false;
			foreach ($readmes as $readme)
			{
				if ($readme->getName() !== 'readme')
					continue;
				$lang = (string) $readme['lang'];
				if ($_GET['lang'] == $lang)
				{
					$readme_found = (string) $readme;
					break;
				}
			}

			if (!$readme_found)
				$valid = false;
		}
		else
			$valid = false;
	}

	// So, the readme is valid. Read it, it's probably bbcode, so preparse and parse it.
	if ($valid)
	{
		$path = strtr($readme, array('$plugindir' => $pluginsdir . '/' . $_GET['plugin']));
		$contents = file_get_contents($path);
		loadSource('Class-Editor');

		// Preparse, then remove any instances of the html bbcode. There is no reason to allow *raw* HTML here.
		$contents = westr::htmlspecialchars($contents, ENT_QUOTES);
		$contents = preg_replace('~\[[/]?html\]~i', '', $contents);
		wedit::preparsecode($contents);

		$name = '';
		if (!empty($manifest->name))
		{
			$name = (string) $manifest->name;
			if (!empty($manifest->version))
				$name .= ' ' . westr::htmlspecialchars((string) $manifest->version);

			$name = '<h6 class="top">' . $name . '</h6>';
		}

		$context['help_text'] = $name . parse_bbc($contents);
	}
	else
		$context['help_text'] = $txt['invalid_plugin_readme'];
}

function EnablePlugin()
{
	global $context, $pluginsdir, $modSettings;

	checkSession('request');

	// Did they specify a plugin, and is it a valid one?
	$valid = true;

	if (isset($_GET['plugin']))
	{
		// So, one's specified. Make sure it doesn't start with a . (which rules out ., .. and 'hidden' files) and also that it doesn't contain directory separators. No sneaky trying to get out the box.
		if (strpos($_GET['plugin'], DIRECTORY_SEPARATOR) !== false || $_GET['plugin'][0] == '.' || !file_exists($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml'))
			$valid = false;
	}
	else
		$valid = false;

	//libxml_use_internal_errors(true);
	if (filetype($pluginsdir . '/' . $_GET['plugin']) != 'dir' || !file_exists($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml'))
		fatal_lang_error('fatal_not_valid_plugin', false);

	$manifest = simplexml_load_file($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml');
	if ($manifest === false || empty($manifest->name) || empty($manifest->author) || empty($manifest->version))
		fatal_lang_error('fatal_not_valid_plugin', false);

	// Already installed?
	if (in_array($_GET['plugin'], $context['enabled_plugins']))
		fatal_lang_error('fatal_already_enabled', false);

	// OK, so we need to go through and validate that we have everything we need.
	$min_versions = array();
	$check_for = array('php', 'mysql');
	if (!empty($manifest->{'min-versions'}))
	{
		$versions = $manifest->{'min-versions'}->children();
		foreach ($versions as $version)
			if (in_array($version->getName(), $check_for))
				$min_versions[$version->getName()] = (string) $version;
	}

	// So, minimum versions? PHP?
	if (!empty($min_versions['php']))
	{
		// Users might insert 5 or 5.3 or 5.3.0. version_compare considers 5.3 to be less than 5.3.0. So we have to normalize it.
		preg_match('~^\d(\.\d){2}~', $min_versions['php'] . '.0.0', $matches);
		if (!empty($matches[0]) && version_compare($matches[0], PHP_VERSION, '>='))
			fatal_lang_error('fatal_install_error_minphp', false, array($matches[0], PHP_VERSION));
	}

	// MySQL?
	if (!empty($min_versions['mysql']))
	{
		wesql::extend('extra');
		$mysql_version = wedbExtra::get_version();

		preg_match('~^\d(\.\d){2}~', $min_versions['mysql'] . '.0.0', $matches);
		if (!empty($matches[0]) && version_compare($matches[0], $mysql_version, '>='))
			fatal_lang_error('fatal_install_error_minmysql', false, array($matches[0], $mysql_version));
	}

	// Required functions?
	if (!empty($manifest->{'required-functions'}))
	{
		$required_functions = testRequiredFunctions($manifest->{'required_functions'});
		if (!empty($required_functions))
			fatal_lang_error('fatal_install_error_reqfunc', false, westr::htmlspecialchars(implode(', ', $required_functions)));
	}

	// Hooks associated with this plugin.
	$hooks_required = array();
	$hook_data = array();
	if (!empty($manifest->hooks))
	{
		$hooks_listed = $manifest->hooks->children();
		foreach ($hooks_listed as $each_hook)
		{
			$hook = $each_hook->getName();
			$point = (string) $each_hook['point'];
			if (($hook == 'function' || $hook == 'language') && !empty($point))
			{
				$hooks_required[$hook][] = $point;
				$hook_data[$point][] = array(
					'function' => $each_hook['function'],
					'filename' => $each_hook['filename'],
				);
			}
		}
	}
	// Now we have a list of the hooks this plugin needs. Are they all accounted for?
	$hooks_missing = array();
	$hooks_available = knownHooks();
	$hooks_provided = array();

	// Technically, a plugin can also call its own hooks.
	if (!empty($manifest->hooks) && !empty($manifest->hooks->provides))
	{
		$provides = $manifest->hooks->provides->children();
		foreach ($provides as $provided)
		{
			$attrs = $provided->attributes();
			$hooks_available[(string) $attrs['type']][] = (string) $provided;
			$hooks_provided[(string) $attrs['type']][] = (string) $provided;
		}
	}

	// Add all the other hooks available
	foreach ($context['enabled_plugins'] as $plugin)
	{
		$plugin = unserialize($modSettings['plugin_' . $plugin]);
		foreach ($plugin['provides'] as $hook_type => $hooks)
			foreach ($hooks as $hook)
				$hooks_available[$hook_type][] = $hook;
	}

	foreach ($hooks_required as $hook_type => $hook_list)
	{
		if (empty($hook_list))
			continue;
		$hooks_missing[$hook_type] = array_diff($hook_list, $hooks_available[$hook_type]);
		if (empty($hooks_missing[$hook_type]))
			unset($hooks_missing[$hook_type]);
	}

	// !!! Check other plugins.

	// So, any missing hooks?
	$missing_hooks_flatten = array();
	foreach ($hooks_missing as $hook_type => $hook_list)
		$missing_hooks_flatten = array_merge($missing_hooks_flatten, $hook_list);
	if (!empty($missing_hooks_flatten))
		fatal_lang_error('fatal_install_error_missinghook', false, westr::htmlspecialchars(implode(', ', $missing_hooks_flatten)));

	// Add this point, we appear to have everything we need, so let's start committing things.

	// Database changes
	if (!empty($manifest->database))
	{
		wesql::extend('packages');
		$new_tables = $new_columns = $new_indexes = array();
		$existing_columns = $existing_indexes = array();

		$existing_tables = wedbExtra::list_tables();

		// First, pass through and collate a list of tables, columns and indexes that we are expecting to deal with. That way we know what we're going to have to query for.
		if (!empty($manifest->database->tables))
		{
			$valid_types = array('tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'real', 'double', 'text', 'mediumtext', 'char', 'varchar', 'set', 'enum');
			$int_types = array('tinyint', 'smallint', 'mediumint', 'int', 'bigint');
			$float_types = array('float', 'real', 'double');
			$tables = $manifest->database->tables->children();
			foreach ($tables as $table)
			{
				if ($table->getName() != 'table')
					continue;

				$this_table = array(
					'name' => (string) $table['name'],
					'if-exists' => (string) $table['if-exists'],
					'columns' => array(),
					'indexes' => array(),
				);

				// Basic formalities.
				if (empty($this_table['name']) || ($this_table['if-exists'] != 'update' && $this_table['if-exists'] != 'ignore') || empty($table->columns))
					continue;

				$columns = $table->columns->children();
				foreach ($columns as $column)
				{
					if ($column->getName() != 'column')
						continue;
					// Like most things with SimpleXML, we just try to get everything, then make sense of it after. Note that you can call for an attribute even if it isn't stated, and no error will result.
					$this_col = array(
						'name' => (string) $column['name'],
						'type' => (string) $column['type'],
						'size' => (string) $column['size'],
						'null' => (string) $column['null'],
						'values' => (string) $column['values'], // For SET and ENUM types.
						'auto' => (string) $column['autoincrement'],
						'unsigned' => (string) $column['unsigned'],
						'default' => (string) $column['default'],
					);
					if (empty($this_col['name']))
						continue;

					if (!in_array($this_col['type'], $valid_types))
						continue;

					$this_col['null'] = $this_col['null'] == 'yes'; // Columns should be NOT NULL unless specifically needed as such.
					$this_col['auto'] = $this_col['auto'] == 'yes'; // The column should not be auto-increment unless specifically needed as such.
					$this_col['unsigned'] = $this_col['unsigned'] != 'no'; // Columns should be unsigned where possible.

					// Apply some type-specific rules before doing something clever.
					if (in_array($this_col['type'], $int_types))
					{
						// Int columns can be auto inc, can be unsigned etc. Just needs to have an integer default, or expressly not specify one.
						if ($this_col['default'] == '')
							unset($this_col['default']);
						else
							$this_col['default'] = (int) $this_col['default'];
					}
					elseif (in_array($this_col['type'], $float_types))
					{
						// Float columns, like int columns, have to have a meaningful default. But they can't be auto inc.
						if ($this_col['default'] == '')
							unset($this_col['default']);
						else
							$this_col['default'] = (float) $this_col['default'];
						$this_col['auto'] = false;
					}
					elseif ($this_col['type'] == 'text' || $this_col['type'] == 'mediumtext')
					{
						// Block text columns can't have a default, can't be auto inc and can't be unsigned.
						unset($this_col['auto'], $this_col['unsigned'], $this_col['default']);
					}

					// This applies to everything but set and enum: get rid of the values. If it IS set or enum, check it does have values.
					if ($this_col['type'] != 'set' && $this_col['type'] != 'enum')
						unset($this_col['values']);
					else
					{
						unset($this_col['auto'], $this_col['unsigned']);
						if (empty($this_col['default']) && !isset($column['default']))
							unset($this_col['default']);
						if (strpos($this_col['values'], ',') === false)
							continue;
					}

					$this_table['columns'][] = $this_col;
				}

				$table_children = $table->children();
				foreach ($table_children as $index)
				{
					if ($index->getName() != 'index')
						continue;

					$this_index = array(
						'type' => strtolower((string) $index['type']),
						'name' => (string) $index['name'],
						'columns' => array(),
					);
					$index_children = $index->children();
					foreach ($index_children as $index_field)
						if ($index_field->getName() == 'field')
							$this_index['columns'][] = (string) $index_field[0];

					if (!empty($this_index['columns']))
						$this_table['indexes'][] = $this_index;
				}

				if (empty($this_table['columns']))
					continue;

				wedbPackages::create_table($this_table['name'], $this_table['columns'], $this_table['indexes'], $this_table['if-exists']);
			}
		}
	}

	// Database changes: enable script
	if (!empty($manifest->database->scripts->enable))
	{
		$file = (string) $manifest->database->scripts->enable;
		$full_path = strtr($file, array('$plugindir' => $pluginsdir . '/' . $_GET['plugin']));
		if (empty($file) || substr($file, -4) != '.php' || strpos($file, '$plugindir/') !== 0 || !file_exists($full_path))
			fatal_lang_error('fatal_install_enable_missing', false, empty($file) ? $txt['na'] : htmlspecialchars($file));

		// This is just here as reference for what is available.
		global $txt, $boarddir, $sourcedir, $modSettings, $context, $settings, $pluginsdir;
		require($full_path);
	}

	// Adding settings
	if (!empty($manifest->settings))
	{
		$new_settings = array();
		$settings_listed = $manifest->settings->children();
		foreach ($settings_listed as $setting)
		{
			// Validate the setting is potentially valid.
			if ($setting->getName() != 'setting')
				continue;
			$setting_name = (string) $setting['name'];
			$setting_default = (string) $setting['default'];
			if (empty($setting_name) || $setting_default == '')
				continue;
			// Add it to the list to be updated if we haven't already got this one.
			if (!isset($modSettings[$setting_name]))
				$new_settings[$setting_name] = $setting_default;
		}

		if (!empty($new_settings))
			updateSettings($new_settings);
	}

	// Adding scheduled tasks
	$this_plugindir = $pluginsdir . '/' . $_GET['plugin'];
	if (!empty($manifest->scheduledtasks))
	{
		$new_tasks = array();
		$tasks_listed = $manifest->scheduledtasks->children();
		$valid_freq = array('minute', 'hour', 'day', 'week');
		foreach ($tasks_listed as $task)
		{
			if ($task->getName() != 'task')
				continue;

			// <task runevery="1" runfreq="day" name="shd_scheduled" file="$plugindir/src/SimpleDesk-Scheduled" />
			$this_task = array(
				'runevery' => (int) $task['runevery'],
				'runfreq' => (string) $task['runfreq'],
				'name' => (string) $task['name'],
				'file' => (string) $task['file'],
			);
			// Validate the task options. Note that we don't have to have a file.
			if ($this_task['runevery'] < 1 || !in_array($this_task['runfreq'], $valid_freq) || empty($this_task['name']))
				continue;

			// If there's a filename, we have to fix its path
			if (!empty($this_task['file']))
				$this_task['file'] = trim('plugin;' . str_replace('$plugindir', $this_plugindir, $this_task['file']));
			$new_tasks[] = $this_task;
		}

		// Any to do?
		if (!empty($new_tasks))
		{
			$inserts = array();
			$next = strtotime('tomorrow');
			foreach ($new_tasks as $task)
				$inserts[] = array(
					$next, // next_time
					0, // time_offset
					$task['runevery'], // time_regularity
					$task['runfreq'][0], // time_unit; it's only m, h, d, w for the table
					0, // disabled
					$task['name'], // task
					$task['file'], // sourcefile
				);
			wesql::insert('replace',
				'{db_prefix}scheduled_tasks',
				array(
					'next_time' => 'int',
					'time_offset' => 'int',
					'time_regularity' => 'int',
					'time_unit' => 'string',
					'disabled' => 'int',
					'task' => 'string',
					'sourcefile' => 'string',
				),
				$inserts,
				array('task')
			);
		}
	}

	// Lastly, commit the hooks themselves.
	$plugin_details = array(
		'id' => (string) $manifest['id'],
		'provides' => $hooks_provided,
	);
	foreach ($hook_data as $point => $details)
		foreach ($details as $hooked_details)
			$plugin_details[$point][] = (string) $hooked_details['function'] . '|' . (string) $hooked_details['filename'] . '|plugin';

	if (!empty($modSettings['enabled_plugins']))
		$enabled_plugins = explode(',', $modSettings['enabled_plugins']);
	else
		$enabled_plugins = array();
	$enabled_plugins[] = $_GET['plugin'];
	updateSettings(
		array(
			'enabled_plugins' => implode(',', $enabled_plugins),
			'plugin_' . $_GET['plugin'] => serialize($plugin_details),
			'settings_updated' => time(),
		)
	);

	redirectexit('action=admin;area=plugins');
}

function DisablePlugin()
{
	global $context, $pluginsdir, $modSettings;

	checkSession('request');

	// Did they specify a plugin, and is it a valid one?
	$valid = true;

	if (isset($_GET['plugin']))
	{
		// So, one's specified. Make sure it doesn't start with a . (which rules out ., .. and 'hidden' files) and also that it doesn't contain directory separators. No sneaky trying to get out the box.
		if (strpos($_GET['plugin'], DIRECTORY_SEPARATOR) !== false || $_GET['plugin'][0] == '.' || !file_exists($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml'))
			$valid = false;
	}
	else
		$valid = false;

	//libxml_use_internal_errors(true);
	if (filetype($pluginsdir . '/' . $_GET['plugin']) != 'dir' || !file_exists($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml'))
		fatal_lang_error('fatal_not_valid_plugin', false);

	$manifest = simplexml_load_file($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml');
	if ($manifest === false || empty($manifest->name) || empty($manifest->author) || empty($manifest->version))
		fatal_lang_error('fatal_not_valid_plugin', false);

	// Already installed?
	if (!in_array($_GET['plugin'], $context['enabled_plugins']))
		fatal_lang_error('fatal_already_disabled', false);

	// Disabling is much simpler than enabling.

	// Database changes: disable script
	if (!empty($manifest->database->scripts->disable))
	{
		$file = (string) $manifest->database->scripts->disable;
		$full_path = strtr($file, array('$plugindir' => $pluginsdir . '/' . $_GET['plugin']));
		if (empty($file) || substr($file, -4) != '.php' || strpos($file, '$plugindir/') !== 0 || !file_exists($full_path))
			fatal_lang_error('fatal_install_disable_missing', false, empty($file) ? $txt['na'] : htmlspecialchars($file));

		// This is just here as reference for what is available.
		global $txt, $boarddir, $sourcedir, $modSettings, $context, $settings, $pluginsdir;
		require($full_path);
	}

	// Any scheduled tasks to disable?
	if (!empty($manifest->scheduledtasks))
	{
		$tasks_listed = $manifest->scheduledtasks->children();
		$tasks_to_disable = array();
		foreach ($tasks_listed as $task)
		{
			if ($task->getName() != 'task')
				continue;

			$task['name'] = (string) $task['name'];
			if (!empty($task['name']))
				$tasks_to_disable[] = $task['name'];
		}

		if (!empty($tasks_to_disable))
			wesql::query('
				UPDATE {db_prefix}scheduled_tasks
				SET disabled = 1
				WHERE task IN ({array_string:tasks})',
				array(
					'tasks' => $tasks_to_disable,
				)
			);
	}

	// Note that the internal cache of per-plugin hook info is cleared, not removed. When actually removing the plugin, then we'd purge it.
	// It's not like we have to call remove_hook or anything, because the whole point is that we don't 'add' them in the first place...
	$enabled_plugins = array_diff($context['enabled_plugins'], array($_GET['plugin']));
	updateSettings(
		array(
			'enabled_plugins' => implode(',', $enabled_plugins),
			'plugin_' . $_GET['plugin'] => '',
			'settings_updated' => time(),
		)
	);

	redirectexit('action=admin;area=plugins');
}

function RemovePlugin()
{
	global $scripturl, $txt, $context, $pluginsdir;

	// Did they specify a plugin, and is it a valid one?
	$valid = true;

	if (isset($_GET['plugin']))
	{
		// So, one's specified. Make sure it doesn't start with a . (which rules out ., .. and 'hidden' files) and also that it doesn't contain directory separators. No sneaky trying to get out the box.
		if (strpos($_GET['plugin'], DIRECTORY_SEPARATOR) !== false || $_GET['plugin'][0] == '.' || !file_exists($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml'))
			$valid = false;
	}
	else
		$valid = false;

	//libxml_use_internal_errors(true);
	if (filetype($pluginsdir . '/' . $_GET['plugin']) != 'dir' || !file_exists($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml'))
		fatal_lang_error('fatal_not_valid_plugin', false);

	$manifest = simplexml_load_file($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml');
	if ($manifest === false || empty($manifest->name) || empty($manifest->version))
		fatal_lang_error('fatal_not_valid_plugin_remove', false);

	// Already installed?
	if (in_array($_GET['plugin'], $context['enabled_plugins']))
		fatal_lang_error('remove_plugin_already_enabled', false);

	$context['plugin_name'] = westr::htmlspecialchars((string) $manifest->name . ' ' . (string) $manifest->version);

	// Now then, what are we doing here?
	if (!isset($_GET['commit']))
	{
		// Just displaying the form.
		loadBlock('remove');
		$context['page_title'] = $txt['remove_plugin'];
	}
	else
	{
		checkSession();
		// !!! Check that the folder is deleteable, and if not, exit gracefully to screen where we can collect the relevant details.
		$all_writable = true;
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginsdir . '/' . $_GET['plugin']), RecursiveIteratorIterator::SELF_FIRST);
		foreach ($iterator as $path)
			if (!$path->isWritable())
			{
				if (@chmod($path->__toString(), $path->isDir() ? 0777 : 0666) == false)
					$all_writable = false;
				break;
			}

		if (!$all_writable)
			fatal_lang_error('remove_plugin_files_pre_still_there', false);

		// See whether we're saving or removing the data
		if (isset($_POST['nodelete']))
			commitRemovePlugin(false);
		elseif (isset($_POST['delete']))
			commitRemovePlugin(true);
		else
		{
			// Just displaying the form anyway.
			loadBlock('remove');
			$context['page_title'] = $txt['remove_plugin'];
		}
	}
}

// This ugly function detals with actually removing a plugin.
function commitRemovePlugin($fullclean = false)
{
	global $scripturl, $txt, $context, $pluginsdir;

	// So, as far as we know, it's valid to remove, because RemovePlugin() should have checked all this for us, even writability.

	// Database changes: remove/remove-clean script
	if (!$fullclean && !empty($manifest->database->scripts->remove))
		$file = (string) $manifest->database->scripts->remove;
	elseif ($fullclean && !empty($manifest->database->scripts->{'remove-clean'}))
		$file = (string) $manifest->database->scripts->{'remove-clean'};

	if (!empty($file))
	{
		$full_path = strtr($file, array('$plugindir' => $pluginsdir . '/' . $_GET['plugin']));
		if (empty($file) || substr($file, -4) != '.php' || strpos($file, '$plugindir/') !== 0 || !file_exists($full_path))
			fatal_lang_error('fatal_install_remove_missing', false, empty($file) ? $txt['na'] : htmlspecialchars($file));

		// This is just here as reference for what is available.
		global $txt, $boarddir, $sourcedir, $modSettings, $context, $settings, $pluginsdir;
		require($full_path);
	}

	if ($fullclean && !empty($manifest->database))
	{
		// Pass each table we find to the drop-table routine. That already does its own checking as to whether the table exists or not.
		if (!empty($manifest->database->tables))
		{
			$tables = $manifest->database->tables->children();
			foreach ($tables as $table)
				if ($table->getName() == 'table')
					weDBPackages::drop_table((string) $table['name']);
		}
	}

	// Need to remove scheduled tasks. We can leave normal settings in, but scheduled tasks need to be removed, because they will mangle if accidentally run otherwise.
	if (!empty($manifest->scheduledtasks))
	{
		$tasks = array();
		$tasks_listed = $manifest->scheduledtasks->children();
		foreach ($tasks_listed as $task)
		{
			if ($task->getName() != 'task')
				continue;
			$name = (string) $task['name'];
			if (!empty($name))
				$tasks[] = $name;
		}
		if (!empty($tasks))
			wesql::query('
				DELETE FROM {db_prefix}scheduled_tasks
				WHERE task IN ({array_string:tasks})',
				array(
					'tasks' => $tasks,
				)
			);
	}

	// Clean settings, including the master storage for this plugin's hooks if it was ever used. Make sure to flush caches.
	$clean_settings = array('plugin_' . $_GET['plugin']);
	if ($fullclean && !empty($manifest->settings))
	{
		$settings_listed = $manifest->settings->children();
		foreach ($settings_listed as $setting)
			if ($setting->getName() == 'setting')
				$clean_settings[] = (string) $setting['name'];
	}
	wesql::query('
		DELETE FROM {db_prefix}settings
		WHERE variable IN ({array_string:clean_settings})',
		array(
			'clean_settings' => $clean_settings,
		)
	);

	updateSettings(
		array(
			'settings_updated' => time(),
		)
	);

	// Lastly, actually do the delete.
	$result = deleteFiletree($pluginsdir . '/' . $_GET['plugin']);
	if (empty($result))
		fatal_lang_error('remove_plugin_files_still_there', false, $_GET['plugin']);
	else
		redirectexit('action=admin;area=plugins'); // It was successful, so disappear back to the plugin list.
}

// Return true on success.
function deleteFiletree($rootpath)
{
	$rootpath = realpath($rootpath);
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootpath), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iterator as $path)
		if ($path->isDir())
			@rmdir($path->__toString());
		else
			@unlink($path->__toString());

	@rmdir($rootpath);

	return (!file_exists($rootpath));
}

// Accepts the <required-functions> element and returns an array of functions that aren't available.
function testRequiredFunctions($manifest_element)
{
	$required_functions = array();
	$functions = $manifest_element->children();
	foreach ($functions as $function)
	{
		if ($function->getName() != 'php-function')
			continue;
		$function = trim((string) $function[0]);
		if (!empty($function))
			$required_functions[$function] = true;
	}
	foreach ($required_functions as $function => $dummy)
		if (is_callable($function))
			unset($required_functions[$function]);

	if (empty($required_functions))
		return array();
	else
		return array_keys($required_functions); // Can't array-flip because we will end up overwriting our values.
}

function knownHooks()
{
	return array(
		'language' => array(
			'lang_help',
			'lang_who',
		),
		'function' => array(
			// Cornerstone items
			'pre_load',
			'determine_location',
			'detect_browser',
			'load_theme',
			'menu_items',
			'actions',
			'behavior',
			// Threads and posts display
			'post_bbc_parse',
			'display_prepare_post',
			'display_post_done',
			'messageindex_buttons',
			'display_buttons',
			'mod_buttons',
			// Admin
			'admin_areas',
			'admin_search',
			'modify_modifications',
			'core_features',
			'plugin_settings',
			'output_error',
			// User related
			'login',
			'validate_login',
			'logout',
			'change_member_data',
			'verify_password',
			'reset_pass',
			'activate',
			'delete_member',
			'track_ip',
			// User permissions
			'load_permissions',
			'illegal_perms',
			'illegal_guest_perms',
			// Content creation
			'personal_message',
			'create_post_before',
			'create_post_after',
			'modify_post_before',
			'modify_post_after',
			// Process flow and execution
			'default_action',
			'fallback_action',
			'buffer',
			'redirect',
			'exit',
			'dynamic_rewrite',
			// Miscellaneous
			'css_color',
			'buddy',
			'bbc_buttons',
			'place_credit',
			'get_boardindex',
			'media_areas',
			'profile_areas',
			'ssi',
			'whos_online',
		),
	);
}

?>