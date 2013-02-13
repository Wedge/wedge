<?php
/**
 * Wedge
 *
 * Handle all key aspects relating to plugins and their management.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
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
	loadSource('Subs-Plugins');
	define('WEDGE_PLUGIN', 1); // Any scripts that are run from here, should *really* test that this is defined and exit if not.

	// Because our good friend the GenericMenu complains otherwise.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['plugin_manager'],
		'description' => $txt['plugin_manager_desc'],
		'tabs' => array(
			'plugins' => array(
			),
			'add' => array(
				'description' => $txt['plugins_add_desc'],
			),
		),
	);

	$subActions = array(
		'list' => 'ListPlugins',
		'readme' => 'PluginReadme',
		'enable' => 'EnablePlugin',
		'disable' => 'DisablePlugin',
		'remove' => 'RemovePlugin',
		'add' => 'AddPlugin',
	);

	// By default do the basic settings.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : array_shift(array_keys($subActions));
	$context['sub_action'] = $_REQUEST['sa'];

	$subActions[$_REQUEST['sa']]();
}

function ListPlugins()
{
	global $scripturl, $txt, $context, $pluginsdir, $maintenance;

	wetem::load('browse');
	$context['page_title'] = $txt['plugin_manager'];
	getLanguages();

	$context['available_plugins'] = array();

	$hooks = knownHooks();

	// 1. Step through the directory, figure out what's there and what's not, and store everything that's valid in $context['available_plugins'].
	// Since we explicitly want folders at this point, we don't want to use scandir.
	if (!empty($pluginsdir))
	{
		// !! libxml_use_internal_errors(true); ??
		if ($handle = opendir($pluginsdir))
		{
			while (($folder = readdir($handle)) !== false)
			{
				if ($folder[0] == '.' || strpos($folder, ',') !== false)
					continue;

				if (filetype($pluginsdir . '/' . $folder) == 'dir' && file_exists($pluginsdir . '/' . $folder . '/plugin-info.xml'))
				{
					$manifest = safe_sxml_load($pluginsdir . '/' . $folder . '/plugin-info.xml');
					if ($manifest === false || empty($manifest['id']) || empty($manifest->name) || empty($manifest->author) || empty($manifest->version))
						continue;

					$plugin = array(
						'folder' => $folder,
						'id' => (string) $manifest['id'],
						'name' => westr::htmlspecialchars((string) $manifest->name),
						'author' => westr::htmlspecialchars($manifest->author),
						'author_url' => (string) $manifest->author['url'],
						'author_email' => (string) $manifest->author['email'],
						'website' => (string) $manifest->website,
						'version' => westr::htmlspecialchars($manifest->version),
						'description' => westr::htmlspecialchars($manifest->description),
						'hooks' => array(),
						'provide_hooks' => array(),
						'optional_hooks' => array(
							'function' => array(),
							'language' => array(),
						),
						'readmes' => array(),
						'acp_url' => '',
						'install_errors' => array(),
						'enabled' => false,
						'maint' => array(),
					);

					// Use the supplied link if, well, given, but failing that check to see if they used an auto admin page.
					if (!empty($manifest->{'acp-url'}))
						$plugin['acp_url'] = (string) $manifest->{'acp-url'};
					elseif (!empty($manifest->{'settings-page'}['area']))
						$plugin['acp_url'] = 'action=admin;area=' . (string) $manifest->{'settings-page'}['area'];

					// Do some sanity checking, to validate that any given URL or email are at least vaguely legal.
					if (empty($plugin['author_url']) || (strpos($plugin['author_url'], 'http://') !== 0 && strpos($plugin['author_url'], 'https://') !== 0))
						$plugin['author_url'] = '';
					if (empty($plugin['website']) || (strpos($plugin['website'], 'http://') !== 0 && strpos($plugin['website'], 'https://') !== 0))
						$plugin['website'] = '';
					if (empty($plugin['author_email']) || !is_valid_email($plugin['author_email']))
						$plugin['author_email'] = '';

					// Test minimum versions of things.
					if (!empty($manifest->{'min-versions'}))
					{
						$min_versions = testRequiredVersions($manifest->{'min-versions'});
						foreach (array('php', 'mysql') as $test)
							if (isset($min_versions[$test]))
								$plugin['install_errors']['min' . $test] = sprintf($txt['install_error_min' . $test], $min_versions[$test][0], $min_versions[$test][1]);
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
									if (!empty($each_hook['optional']) && $each_hook['optional'] == 'yes')
										$plugin['optional_hooks']['function'][] = $each_hook['point'];
									break;
								case 'language':
									if (!empty($each_hook['point']))
										$plugin['hooks']['language'][] = $each_hook['point'];
									if (!empty($each_hook['optional']) && $each_hook['optional'] == 'yes')
										$plugin['optional_hooks']['language'][] = $each_hook['point'];
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
						foreach ($manifest->readmes->readme as $readme)
						{
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

					// Is there anything it has to do in maintenance mode?
					$plugin['maint'] = get_maint_requirements($manifest);

					// OK, add to the list.
					$context['available_plugins'][strtolower($plugin['name'] . $folder)] = $plugin;
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

			$missing_hooks = array_diff($required_hooks, $hooks[$hook_type], $plugin['provide_hooks'][$hook_type], $plugin['optional_hooks'][$hook_type]);
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
				$context['enabled_plugins'][$plugin['id']] = $plugin['folder'];
				$context['available_plugins'][$id]['enabled'] = true;
			}

		updateSettings(array('enabled_plugins' => implode(',', $context['enabled_plugins'])));
	}

	// 4. Go through the remaining disabled plugins and check that they're not trying to activate where there's another plugin with the same id already enabled.
	$by_id = array(
		'enabled' => array(),
		'disabled' => array(),
	);
	foreach ($context['available_plugins'] as $id => $plugin)
		$by_id[$plugin['enabled'] ? 'enabled' : 'disabled'][] = $plugin['id'];
	if (!empty($by_id['enabled']) && !empty($by_id['disabled']))
	{
		foreach ($by_id['disabled'] as $disabled)
			if (in_array($disabled, $by_id['enabled']))
			{
				foreach ($context['available_plugins'] as $id => $plugin)
				{
					if ($plugin['enabled'] || $plugin['id'] != $disabled)
						continue;
					$context['available_plugins'][$id]['install_errors']['duplicate_id'] = $txt['install_error_duplicate_id'] . ' (' . $plugin['id'] . ')';
				}
			}
	}

	// 5. Deal with any plugins that are not currently enabled, but want to be in maintenance mode to do so.
	// If we're not in maintenance, the plugin isn't enabled and it wants maintenance mode to be able to do so.
	if ($maintenance == 0)
		foreach ($context['available_plugins'] as $id => $plugin)
			if (!$plugin['enabled'] && !empty($plugin['maint']) && in_array('enable', $plugin['maint']))
				$context['available_plugins'][$id]['install_errors']['maint_mode'] = $txt['install_error_maint_mode'];

	// 6. Deal with any filtering. We have to do it here, rather than earlier, simply because we need to have processed everything beforehand.
	$context['filter_plugins'] = array(
		'all' => 0,
		'enabled' => 0,
		'disabled' => 0,
		'install_errors' => 0,
	);
	$context['current_filter'] = isset($_GET['filter']) && isset($context['filter_plugins'][$_GET['filter']]) ? $_GET['filter'] : 'all';
	foreach ($context['available_plugins'] as $id => $plugin)
	{
		if (!empty($plugin['install_errors']))
			$type = 'install_errors';
		elseif (!empty($plugin['enabled']))
			$type = 'enabled';
		else
			$type = 'disabled';

		$context['filter_plugins']['all']++;
		$context['filter_plugins'][$type]++;
		if ($context['current_filter'] !== 'all' && $context['current_filter'] !== $type)
			unset($context['available_plugins'][$id]);
	}
}

function PluginReadme()
{
	global $context, $txt, $pluginsdir;

	// Let's start and fire up the template. We're reusing the nice popup, so load templates and set up various things.
	loadTemplate('GenericPopup');
	loadLanguage('Help');
	wetem::hide();
	$context['page_title'] = $txt['plugin_manager'] . ' - ' . $txt['help'];
	wetem::load('popup');

	// Did they specify a plugin, and is it a valid one?
	$valid = isViablePlugin();

	// Did they specify a language, and is it valid?
	if ($valid && isset($_GET['lang']))
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
		$manifest = safe_sxml_load($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml');

		if (!empty($manifest->readmes))
		{
			$readme_found = false;
			foreach ($manifest->readmes->readme as $readme)
			{
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

			$name = '<h6>' . $name . '</h6>';
		}

		$context['popup_contents'] = $name . parse_bbc($contents);
	}
	else
		$context['popup_contents'] = $txt['invalid_plugin_readme'];
}

function EnablePlugin()
{
	global $context, $pluginsdir, $settings, $maintenance;

	checkSession('request');

	// !! libxml_use_internal_errors(true); ??
	if (!isViablePlugin())
		fatal_lang_error('fatal_not_valid_plugin', false);

	$manifest = safe_sxml_load($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml');
	if ($manifest === false || empty($manifest['id']) || empty($manifest->name) || empty($manifest->author) || empty($manifest->version))
		fatal_lang_error('fatal_not_valid_plugin', false);

	// Since we might use this in a few places...
	$manifest_id = (string) $manifest['id'];

	// Already installed? Or another with the same id?
	if (in_array($_GET['plugin'], $context['enabled_plugins']))
		fatal_lang_error('fatal_already_enabled', false);

	if (isset($context['enabled_plugins'][$manifest_id]))
		fatal_lang_error('fatal_duplicate_id', false);

	// OK, so we need to go through and validate that we have everything we need.
	if (!empty($manifest->{'min-versions'}))
	{
		$min_versions = testRequiredVersions($manifest->{'min-versions'});
		foreach (array('php', 'mysql') as $test)
			if (isset($min_versions[$test]))
				fatal_lang_error('fatal_install_error_min' . $test, false, $min_versions[$test]);
	}

	// Required functions?
	if (!empty($manifest->{'required-functions'}))
	{
		$required_functions = testRequiredFunctions($manifest->{'required_functions'});
		if (!empty($required_functions))
			fatal_lang_error('fatal_install_error_reqfunc', false, westr::htmlspecialchars(implode(', ', $required_functions)));
	}

	// Does the plugin require maintenance mode?
	if ($maintenance == 0)
	{
		$maint = get_maint_requirements($manifest);
		if (!empty($maint) && in_array('enable', $maint))
			fatal_lang_error('fatal_install_error_maint_mode', false);
	}

	// Hooks associated with this plugin.
	$hooks_required = array();
	$hook_data = array();
	$optional_hooks = array(
		'function' => array(),
		'language' => array(),
	);
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
					'priority' => $hook == 'language' ? '' : '|' . (!empty($each_hook['priority']) ? min(max((int) $each_hook['priority'], 1), 100) : 50), // Language hooks do not need priority.
				);
				if (!empty($each_hook['optional']) && $each_hook['optional'] == 'yes')
					$optional_hooks[$hook][] = $point;
			}
		}
	}
	// Now we have a list of the hooks this plugin needs. Are they all accounted for?
	$hooks_missing = array();
	$hooks_available = knownHooks();
	$hooks_provided = array();

	// Technically, a plugin can also call its own hooks.
	if (!empty($manifest->hooks) && !empty($manifest->hooks->provides))
		foreach ($manifest->hooks->provides->hook as $provided)
		{
			$attrs = $provided->attributes();
			$hooks_available[(string) $attrs['type']][] = (string) $provided;
			$hooks_provided[(string) $attrs['type']][] = (string) $provided;
		}

	// Add all the other hooks available
	foreach ($context['enabled_plugins'] as $plugin)
	{
		$plugin = unserialize($settings['plugin_' . $plugin]);
		foreach ($plugin['provides'] as $hook_type => $hooks)
			foreach ($hooks as $hook)
				$hooks_available[$hook_type][] = $hook;
	}

	foreach ($hooks_required as $hook_type => $hook_list)
	{
		if (empty($hook_list))
			continue;
		$hooks_missing[$hook_type] = array_diff($hook_list, $hooks_available[$hook_type], $optional_hooks[$hook_type]);
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
		$valid_types = array('tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'real', 'double', 'text', 'mediumtext', 'char', 'varchar', 'set', 'enum', 'date', 'datetime');
		$int_types = array('tinyint', 'smallint', 'mediumint', 'int', 'bigint');
		$float_types = array('float', 'real', 'double');

		loadSource('Class-DBPackages');
		$new_tables = $new_columns = $new_indexes = array();
		$existing_columns = $existing_indexes = array();

		$existing_tables = wedbPackages::list_tables();

		// First, pass through and collate a list of tables, columns and indexes that we are expecting to deal with. That way we know what we're going to have to query for.
		if (!empty($manifest->database->tables))
		{
			foreach ($manifest->database->tables->table as $table)
			{
				$this_table = array(
					'name' => (string) $table['name'],
					'if-exists' => (string) $table['if-exists'],
					'columns' => array(),
					'indexes' => array(),
				);

				// Basic formalities.
				if (empty($this_table['name']) || ($this_table['if-exists'] != 'update' && $this_table['if-exists'] != 'ignore') || empty($table->columns))
					continue;

				foreach ($table->columns->column as $column)
				{
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
					if (empty($this_col['name']) || !in_array($this_col['type'], $valid_types))
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
					elseif ($this_col['type'] == 'date')
					{
						// Date columns can have a default, but can't be auto inc or unsigned.
						unset($this_col['auto'], $this_col['unsigned']);
						// But check the date is meaningful.
						if (empty($this_col['default']) || !preg_match('~^[0-9]{4}\-(0[1-9]|1[0-2])\-(0[1-9]|[12][0-9]|3[0-2])$~', $this_col['default']))
							unset($this_col['default']);
					}
					elseif ($this_col['type'] == 'datetime')
					{
						// Datetime columns can have a default, but can't be auto inc or unsigned.
						unset($this_col['auto'], $this_col['unsigned']);
						// But check the date is meaningful.
						if (empty($this_col['default']) || !preg_match('~^[0-9]{4}\-(0[1-9]|1[0-2])\-(0[1-9]|[12][0-9]|3[0-2]) ([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$~', $this_col['default']))
							unset($this_col['default']);
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

				foreach ($table->index as $index)
				{
					$this_index = array(
						'type' => strtolower((string) $index['type']),
						'name' => (string) $index['name'],
						'columns' => array(),
					);
					foreach ($index->field as $index_field)
						$this_index['columns'][] = (string) $index_field[0];

					if (!empty($this_index['columns']))
						$this_table['indexes'][] = $this_index;
				}

				if (empty($this_table['columns']))
					continue;

				wedbPackages::create_table($this_table['name'], $this_table['columns'], $this_table['indexes'], $this_table['if-exists']);
			}
		}

		// Other database changes: adding new columns
		if (!empty($manifest->database->columns))
		{
			foreach ($manifest->database->columns->column as $column)
			{
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
				$table_name = (string) $column['table'];
				if (empty($this_col['name']) || empty($table_name) || !in_array($this_col['type'], $valid_types))
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
				elseif ($this_col['type'] == 'date')
				{
					// Date columns can have a default, but can't be auto inc or unsigned.
					unset($this_col['auto'], $this_col['unsigned']);
					// But check the date is meaningful.
					if (empty($this_col['default']) || !preg_match('~^[0-9]{4}\-(0[1-9]|1[0-2])\-(0[1-9]|[12][0-9]|3[0-2])$~', $this_col['default']))
						unset($this_col['default']);
				}
				elseif ($this_col['type'] == 'datetime')
				{
					// Datetime columns can have a default, but can't be auto inc or unsigned.
					unset($this_col['auto'], $this_col['unsigned']);
					// But check the date is meaningful.
					if (empty($this_col['default']) || !preg_match('~^[0-9]{4}\-(0[1-9]|1[0-2])\-(0[1-9]|[12][0-9]|3[0-2]) ([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$~', $this_col['default']))
						unset($this_col['default']);
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

				// There's really no point in being clever and trying the work that's in create_table.
				// You can't call create_table on the core tables, and add_column already checks to see if it exists and promptly updates if not.
				wedbPackages::add_column($table_name, $this_col, 'update');
			}
		}
	}

	// Database changes: enable script
	if (!empty($manifest->database->scripts->enable))
		executePluginScript('enable', (string) $manifest->database->scripts->enable);

	// Adding settings
	if (!empty($manifest->settings))
	{
		$new_settings = array();
		foreach ($manifest->settings->setting as $setting)
		{
			$setting_name = (string) $setting['name'];
			$setting_default = (string) $setting['default'];
			// Add it to the list to be updated if we haven't already got this one, and it's not empty, as updateSettings won't set actually-empty ones.
			if (!empty($setting_name) && $setting_default != '' && !isset($settings[$setting_name]))
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
		$valid_freq = array('minute', 'hour', 'day', 'week');
		foreach ($manifest->scheduledtasks->task as $task)
		{
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
				$this_task['file'] = trim('plugin;' . $manifest_id . ';' . $this_task['file']);
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

	// Adding any bbcodes indicated in the plugin manifest.
	if (!empty($manifest->bbcodes))
	{
		$new_bbcode = array();
		$valid_types = array('parsed', 'unparsed_equals', 'parsed_equals', 'unparsed_content', 'closed', 'unparsed_commas', 'unparsed_commas_content', 'unparsed_equals_content');
		$valid_quoted = array('none', 'optional', 'required');
		$valid_trim = array('none', 'inside', 'outside', 'both');
		foreach ($manifest->bbcodes->bbcode as $bbcode)
		{
			// bbcode must declare itself as having a tag and what type of tag it is
			if (empty($bbcode['tag']) || empty($bbcode['type']))
				continue;

			$this_bbcode = array(
				'tag' => (string) $bbcode['tag'],
				'len' => strlen((string) $bbcode['tag']),
				'bbctype' => (string) $bbcode['type'],
				'before_code' => !empty($bbcode->{'before-code'}) ? (string) $bbcode->{'before-code'} : '',
				'after_code' => !empty($bbcode->{'after-code'}) ? (string) $bbcode->{'after-code'} : '',
				'content' => !empty($bbcode->content) ? (string) $bbcode->content : '',
				'disabled_before' => !empty($bbcode->disabled->{'before-code'}) ? (string) $bbcode->disabled->{'before-code'} : '',
				'disabled_after' => !empty($bbcode->disabled->{'after-code'}) ? (string) $bbcode->disabled->{'after-code'} : '',
				'disabled_content' => !empty($bbcode->disabled->content) ? (string) $bbcode->disabled->content : '',
				'block_level' => !empty($bbcode['block-level']) && ((string) $bbcode['block-level'] == 'yes') ? 1 : 0,
				'test' => !empty($bbcode->test) ? (string) $bbcode->test : '',
				'validate_func' => !empty($bbcode->{'validate-func'}) ? (string) $bbcode->{'validate-func'} : '',
				'disallow_children' => '',
				'require_parents' => '',
				'require_children' => '',
				'parsed_tags_allowed' => '',
				'quoted' => !empty($bbcode['quoted']) ? (string) $bbcode['quoted'] : 'none',
				'params' => '',
				'trim_wspace' => !empty($bbcode['trim_wspace']) ? (string) $bbcode['trim_wspace'] : 'none',
				'id_plugin' => $manifest_id,
			);

			// Checking the type and some other stuff. Doing it here after we've typecast them. It won't always work cleanly otherwise.
			if (!in_array($this_bbcode['bbctype'], $valid_types) || !in_array($this_bbcode['quoted'], $valid_quoted) || !in_array($this_bbcode['trim_wspace'], $valid_trim))
				continue;

			// OK, now we need to parse the remaining content that we couldn't have done in the above because of our nice XML structure.
			if (!empty($bbcode->{'disallow-children'}))
			{
				$temp = array();
				foreach ($bbcode->{'disallow-children'}->child as $child)
					$temp[] = (string) $child;
				if (!empty($temp))
					$this_bbcode['disallow_children'] = implode(',', $temp);
			}

			if (!empty($bbcode->{'require-parents'}))
			{
				$temp = array();
				foreach ($bbcode->{'require-parents'}->{'parent-tag'} as $parent)
					$temp[] = (string) $parent;
				if (!empty($temp))
					$this_bbcode['require_parents'] = implode(',', $temp);
			}

			if (!empty($bbcode->{'require-children'}))
			{
				$temp = array();
				foreach ($bbcode->{'require-children'}->child as $child)
					$temp[] = (string) $child;
				if (!empty($temp))
					$this_bbcode['require_children'] = implode(',', $temp);
			}

			if (!empty($bbcode->{'parsed-tags-allowed'}))
			{
				$temp = array();
				foreach ($bbcode->{'parsed-tags-allowed'}->bbc as $tag)
					$temp[] = (string) $tag;
				if (!empty($temp))
					$this_bbcode['parsed_tags_allowed'] = implode(',', $temp);
			}

			// Lastly, parameters
			if (!empty($bbcode->params))
			{
				$params = array();
				foreach ($bbcode->params->param as $param)
				{
					// Collect parameters. Note that we don't need to store stuff if it is default values.
					if (empty($param['name']))
						continue;
					$this_param = array();
					// Typically a parameter will not require quotes, and will not be optional.
					if (!empty($param['quoted']) && (string) $param['quoted'] == 'yes')
						$this_param['quoted'] = true;
					if (!empty($param['optional']) && (string) $param['optional'] == 'yes')
						$this_param['optional'] = true;

					// Now get the other stuff.
					if (!empty($param->match))
						$this_param['match'] = (string) $param->match;
					if (!empty($param->validate))
						$this_param['validate'] = (string) $param->validate;
					if (!empty($param->value) && empty($this_param['validate']))
						$this_param['value'] = (string) $param->value; // Can't use these two together.

					if (!empty($this_param))
						$params[(string) $param['name']] = $this_param;
				}

				if (!empty($params))
					$this_bbcode['params'] = serialize($params);
			}

			// Now, before we commit this one, we need to make some sense of the type of bbcode because different types require different content.
			// A lot of the simplest rules can be dealt with by simply checking for certain content that's specific to each type and if found, excluding it.
			// And checking for things that must be provided. Note that I'm leaving it split in case anything changes in the future.
			$rules = array();
			switch ($this_bbcode['bbctype'])
			{
				case 'parsed':
					$rules = array(
						'require' => array('before_code', 'after_code'),
						'disallow' => array('content', 'validate_func', 'parsed_tags_allowed'),
					);
					break;
				case 'unparsed_equals':
					$rules = array(
						'require' => array('before_code', 'after_code'),
						'disallow' => array('content', 'parsed_tags_allowed'),
					);
					break;
				case 'parsed_equals':
					$rules = array(
						'require' => array('before_code', 'after_code'),
						'disallow' => array('content'),
					);
					break;
				case 'unparsed_content':
					$rules = array(
						'require' => array('content'),
						'disallow' => array('before_code', 'after_code', 'parsed_tags_allowed'),
					);
					break;
				case 'closed':
					if ($this_bbcode['trim_wspace'] == 'inside' || $this_bbcode['trim_wspace'] == 'both')
						continue;
					$rules = array(
						'require' => array('content'),
						'disallow' => array('before_code', 'after_code', 'test', 'params', 'disallow_children', 'require_children', 'require_parents', 'validate_func', 'parsed_tags_allowed'),
					);
					break;
				case 'unparsed_commas':
					$rules = array(
						'require' => array('before_code', 'after_code'),
						'disallow' => array('content', 'parsed_tags_allowed'),
					);
					break;
				case 'unparsed_commas_content':
					$rules = array(
						'require' => array('content'),
						'disallow' => array('before_code', 'after_code', 'parsed_tags_allowed'),
					);
					break;
				case 'unparsed_equals_content':
					$rules = array(
						'require' => array('content'),
						'disallow' => array('before_code', 'after_code', 'parsed_tags_allowed'),
					);
					break;
			}

			if (!empty($rules['disallow']))
			{
				$found = false;
				foreach ($rules['disallow'] as $item)
					if (!empty($this_bbcode[$item]))
					{
						$found = true;
						break;
					}

				if ($found)
					continue;
			}

			if (!empty($rules['require']))
			{
				$found = true;
				foreach ($rules['require'] as $item)
					if (empty($this_bbcode[$item]))
					{
						$found = false;
						break;
					}

				if (!$found)
					continue;
			}

			// Other rules
			// Can't specify a quoting-parameters type for things that don't support quotable parameters.
			if ($this_bbcode['quoted'] != 'none' && $this_bbcode['bbctype'] != 'unparsed_equals' && $this_bbcode['bbctype'] != 'parsed_equals')
				continue;

			$new_bbcode[] = $this_bbcode;
		}

		// Any to do?
		if (!empty($new_bbcode))
		{
			wesql::insert('replace',
				'{db_prefix}bbcode',
				array(
					'tag' => 'string',
					'len' => 'int',
					'bbctype' => 'string',
					'before_code' => 'string',
					'after_code' => 'string',
					'content' => 'string',
					'disabled_before' => 'string',
					'disabled_after' => 'string',
					'disabled_content' => 'string',
					'block_level' => 'string',
					'test' => 'string',
					'validate_func' => 'string',
					'disallow_children' => 'string',
					'require_parents' => 'string',
					'require_children' => 'string',
					'parsed_tags_allowed' => 'string',
					'quoted' => 'string',
					'params' => 'string',
					'trim_wspace' => 'string',
					'id_plugin' => 'string',
				),
				$new_bbcode,
				array('id_bbcode')
			);
		}
	}

	// Lastly, commit the hooks themselves.
	$plugin_details = array(
		'id' => $manifest_id,
		'provides' => $hooks_provided,
	);
	// We make a special exception for last-minute actions.
	if (!empty($manifest->actions))
	{
		$new_actions = array();
		foreach ($manifest->actions->action as $action)
		{
			$this_action = array(
				'action' => (string) $action['action'],
				'function' => (string) $action['function'],
				'filename' => (string) $action['filename'],
			);
			if (!empty($this_action['action']) && !empty($this_action['function']) && !empty($this_action['filename']))
				$new_actions[] = $this_action;
		}
		if (!empty($new_actions))
			$plugin_details['actions'] = $new_actions;
	}

	// Admin settings page?
	if (!empty($manifest->{'settings-page'}))
	{
		if (!empty($settings['plugins_admin']))
		{
			$admin_cache = unserialize($settings['plugins_admin']);
			unset ($admin_cache[$manifest_id]);
		}
		else
			$admin_cache = array();

		$new_item = array();
		$settings_page = $manifest->{'settings-page'};
		if (!empty($settings_page['area']))
		{
			$new_item['area'] = (string) $settings_page['area'];
			$new_item['name'] = (string) $manifest->name;
			foreach (array('icon', 'bigicon', 'permission') as $item)
				if (!empty($settings_page[$item]))
					$new_item[$item] = (string) $settings_page[$item];

			$admin_cache[$manifest_id] = $new_item;
		}

		$admin_cache = !empty($admin_cache) ? serialize($admin_cache) : '';
	}
	else
		$admin_cache = !empty($settings['plugins_admin']) ? $settings['plugins_admin'] : '';

	foreach ($hook_data as $point => $details)
		foreach ($details as $hooked_details)
			$plugin_details[$point][] = (string) $hooked_details['function'] . '|' . (string) $hooked_details['filename'] . '|plugin' . $hooked_details['priority'];

	$enabled_plugins = !empty($settings['enabled_plugins']) ? explode(',', $settings['enabled_plugins']) : array();
	$enabled_plugins[] = $_GET['plugin'];
	updateSettings(
		array(
			'enabled_plugins' => implode(',', $enabled_plugins),
			'plugin_' . $_GET['plugin'] => serialize($plugin_details),
			'settings_updated' => time(),
			'plugins_admin' => $admin_cache,
		)
	);

	redirectexit('action=admin;area=plugins');
}

function DisablePlugin($manifest = null, $plugin = null)
{
	global $context, $pluginsdir, $settings;

	// We might be coming from the user's request or a separate process. If from elsewhere, we don't need to do all the checks.
	if (empty($manifest) || empty($plugin))
	{
		checkSession('request');

		// !! libxml_use_internal_errors(true); ??
		if (!isViablePlugin())
			fatal_lang_error('fatal_not_valid_plugin', false);

		$manifest = safe_sxml_load($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml');
		if ($manifest === false || empty($manifest['id']) || empty($manifest->name) || empty($manifest->author) || empty($manifest->version))
			fatal_lang_error('fatal_not_valid_plugin', false);

		// Already installed?
		if (!in_array($_GET['plugin'], $context['enabled_plugins']))
			fatal_lang_error('fatal_already_disabled', false);
	}
	else
	{
		// $manifest was already set up for us helpfully elsewhere.
		$_GET['plugin'] = $plugin;
	}

	// Disabling is much simpler than enabling.

	$manifest_id = (string) $manifest['id'];

	$test = test_hooks_conflict($manifest);
	if (!empty($test))
	{
		$list = '<ul><li>' . implode('</li><li>', $test) . '</li></ul>';
		fatal_lang_error('fatal_conflicted_plugins', false, array($list));
	}

	// Database changes: disable script
	if (!empty($manifest->database->scripts->disable))
		executePluginScript('disable', (string) $manifest->database->scripts->disable);

	// Any scheduled tasks to disable?
	if (!empty($manifest->scheduledtasks))
	{
		$tasks_to_disable = array();
		foreach ($manifest->scheduledtasks->task as $task)
		{
			$task['name'] = trim((string) $task['name']);
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

	// Any bbcode to disable?
	if (!empty($manifest->bbcodes))
	{
		wesql::query('
			DELETE FROM {db_prefix}bbcode
			WHERE id_plugin = {string:plugin}',
			array(
				'plugin' => $manifest_id,
			)
		);
	}

	// Remove this plugin from the cache of plugins that might be integrating into the admin panel using simple settings pages.
	if (!empty($settings['plugins_admin']))
	{
		$admin_cache = unserialize($settings['plugins_admin']);
		unset ($admin_cache[$manifest_id]);
		$admin_cache = !empty($admin_cache) ? serialize($admin_cache) : '';
	}
	else
		$admin_cache = '';

	// Note that the internal cache of per-plugin hook info is cleared, not removed. When actually removing the plugin, then we'd purge it.
	// It's not like we have to call remove_hook or anything, because the whole point is that we don't 'add' them in the first place...
	$enabled_plugins = array_diff($context['enabled_plugins'], array($_GET['plugin']));
	updateSettings(
		array(
			'enabled_plugins' => implode(',', $enabled_plugins),
			'plugin_' . $_GET['plugin'] => '',
			'settings_updated' => time(),
			'plugins_admin' => $admin_cache,
		)
	);

	if (empty($plugin))
		redirectexit('action=admin;area=plugins');
}

function RemovePlugin()
{
	global $scripturl, $txt, $context, $pluginsdir, $maintenance;

	// !! libxml_use_internal_errors(true); ??
	if (!isViablePlugin())
		fatal_lang_error('fatal_not_valid_plugin', false);

	$manifest = safe_sxml_load($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml');
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
		wetem::load('remove');
		$context['page_title'] = $txt['remove_plugin'];

		// If this requires maintenance mode to clean house, tell the user.
		if ($maintenance == 0)
		{
			$maint = get_maint_requirements($manifest);
			if (!empty($maint) && in_array('remove-clean', $maint))
				$context['requires_maint'] = true;
		}
	}
	else
	{
		checkSession();

		// OK, so we're purging stuff. That means it needs to be deletable. In case we need to call for FTP etc, make sure we know how to get back here.
		$context['remote_callback'] = array(
			'url' => '<URL>?action=admin;area=plugins;sa=remove;plugin=' . $_GET['plugin'] . ';commit;' . $context['session_query'],
			'post_data' => array(),
		);
		if (isset($_POST['nodelete']))
			$context['remote_callback']['post_data']['nodelete'] = 1;
		elseif (isset($_POST['delete']))
		{
			// We need to test whether this thing needs some work.
			if ($maintenance == 0)
			{
				$maint = get_maint_requirements($manifest);
				if (!empty($maint) && in_array('remove-clean', $maint))
					fatal_lang_error('fatal_install_error_maint_mode', false);
			}
		}

		// Check that the entire tree is deletable.
		$all_writable = true;
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginsdir . '/' . $_GET['plugin']), RecursiveIteratorIterator::SELF_FIRST);
		foreach ($iterator as $path)
			if (!$path->isWritable())
			{
				$all_writable = false;
				break;
			}

		// Call for the remote(or local) connector. If we pass this point, we have a class instance that we must pass on to operations.
		$remote_class = getWritableObject();

		if (empty($remote_class))
			fatal_lang_error('remove_plugin_files_pre_still_there', false);

		// See whether we're saving or removing the data
		if (isset($_POST['nodelete']))
			commitRemovePlugin(false, $manifest, $remote_class);
		elseif (isset($_POST['delete']))
			commitRemovePlugin(true, $manifest, $remote_class);
		else
		{
			// Just displaying the form anyway.
			wetem::load('remove');
			$context['page_title'] = $txt['remove_plugin'];
		}
	}
}

// This ugly function deals with actually removing a plugin.
function commitRemovePlugin($fullclean, &$manifest, &$remote_class)
{
	global $scripturl, $txt, $context, $pluginsdir;

	// So, as far as we know, it's valid to remove, because RemovePlugin() should have checked all this for us, even writability.

	// Database changes: remove/remove-clean script
	if (!$fullclean && !empty($manifest->database->scripts->remove))
		$file = (string) $manifest->database->scripts->remove;
	elseif ($fullclean && !empty($manifest->database->scripts->{'remove-clean'}))
		$file = (string) $manifest->database->scripts->{'remove-clean'};

	if (!empty($file))
		executePluginScript('remove', $file);

	if ($fullclean && !empty($manifest->database))
	{
		loadSource('Class-DBPackages');

		// Pass each table we find to the drop-table routine. That already does its own checking as to whether the table exists or not.
		if (!empty($manifest->database->tables))
			foreach ($manifest->database->tables->table as $table)
				if (!empty($table['name']))
					weDBPackages::drop_table((string) $table['name']);

		// And columns.
		if (!empty($manifest->database->columns))
			foreach ($manifest->database->columns->column as $column)
				if (!empty($column['name']) && !empty($column['table']))
					weDBPackages::remove_column((string) $column['table'], (string) $column['name']);
	}

	// Clean up permissions? Only need to do this on a full clean-up.
	if ($fullclean && !empty($manifest->newperms))
	{
		$perms = array(
			'membergroup' => array(),
			'board' => array(),
		);

		if (!empty($manifest->newperms->permissionlist))
			foreach ($manifest->newperms->permissionlist->permission as $permission)
			{
				if (empty($permission['type']) || empty($permission['name']))
					continue;
				$type = (string) $permission['type'];
				if (!isset($perms[$type]))
					continue;
				if (!empty($permission['ownany']) && (string) $permission['ownany'] == 'true')
				{
					$perms[$type][] = ((string) $permission['name']) . '_own';
					$perms[$type][] = ((string) $permission['name']) . '_any';
				}
				else
					$perms[$type][] = (string) $permission['name'];
			}

		if (!empty($perms['membergroup']))
			wesql::query('
				DELETE FROM {db_prefix}permissions
				WHERE permission IN ({array_string:perms})',
				array(
					'perms' => $perms['membergroup'],
				)
			);

		if (!empty($perms['board']))
			wesql::query('
				DELETE FROM {db_prefix}board_permissions
				WHERE permission IN ({array_string:perms})',
				array(
					'perms' => $perms['board'],
				)
			);
	}

	// Need to remove scheduled tasks. We can leave normal settings in, but scheduled tasks need to be removed, because they will mangle if accidentally run otherwise.
	// We also should prune the entries from the task log.
	if (!empty($manifest->scheduledtasks))
	{
		$tasks = array();
		foreach ($manifest->scheduledtasks->task as $task)
		{
			$name = trim((string) $task['name']);
			if (!empty($name))
				$tasks[] = $name;
		}
		if (!empty($tasks))
		{
			$task_ids = array();
			$query = wesql::query('
				SELECT id_task
				FROM {db_prefix}scheduled_tasks
				WHERE task IN ({array_string:tasks})',
				array(
					'tasks' => $tasks,
				)
			);
			while ($row = wesql::fetch_row($query))
				$task_ids[] = $row[0];
			wesql::free_result($query);
			if (!empty($task_ids))
			{
				wesql::query('
					DELETE FROM {db_prefix}scheduled_tasks
					WHERE id_task IN ({array_int:tasks})',
					array(
						'tasks' => $task_ids,
					)
				);
				wesql::query('
					DELETE FROM {db_prefix}log_scheduled_tasks
					WHERE id_task IN ({array_int:tasks})',
					array(
						'tasks' => $task_ids,
					)
				);
			}
		}
	}

	// Clean settings, including the master storage for this plugin's hooks if it was ever used. Make sure to flush caches.
	$clean_settings = array('plugin_' . $_GET['plugin']);
	if ($fullclean && !empty($manifest->settings))
		foreach ($manifest->settings->setting as $setting)
			$clean_settings[] = (string) $setting['name'];

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
	$result = deleteFiletree($remote_class, $pluginsdir . '/' . $_GET['plugin'], true);
	if (empty($result))
		fatal_lang_error('remove_plugin_files_still_there', false, $_GET['plugin']);
	else
		redirectexit('action=admin;area=plugins'); // It was successful, so disappear back to the plugin list.
}

// Handles running any change-state scripts, e.g. on-enable. The type is primarily for the error message if the file couldn't be found.
function executePluginScript($type, $file)
{
	global $pluginsdir;

	if (!empty($file))
	{
		$full_path = strtr($file, array('$plugindir' => $pluginsdir . '/' . $_GET['plugin']));
		if (empty($file) || substr($file, -4) != '.php' || strpos($file, '$plugindir/') !== 0 || !file_exists($full_path))
			fatal_lang_error('fatal_install_' . $type . '_missing', false, empty($file) ? $txt['na'] : htmlspecialchars($file));

		// This is just here as reference for what is available.
		global $txt, $boarddir, $sourcedir, $settings, $context, $theme, $pluginsdir;
		require($full_path);
	}
}

// Identify whether the plugin requested is a viable plugin or not.
function isViablePlugin()
{
	global $pluginsdir;

	return (!empty($_GET['plugin']) && strpos($_GET['plugin'], DIRECTORY_SEPARATOR) === false && $_GET['plugin'][0] != '.' && filetype($pluginsdir . '/' . $_GET['plugin']) == 'dir' && file_exists($pluginsdir . '/' . $_GET['plugin'] . '/plugin-info.xml'));
}

// This is a routing agent to the relevant aspect of adding plugins to the system.
function AddPlugin()
{
	global $txt, $context;

	// Both uploading and downloading require gzinflate. If it isn't there, none of this stuff will work, and there's no point in trying it.
	if (!is_callable('gzinflate'))
		fatal_lang_error('plugins_no_gzinflate', false);

	// We store some indication of each repo's state. It gives us a chance to figure out if sites are broken or not.
	define('REPO_INACTIVE', 0);
	define('REPO_ACTIVE', 1);
	define('REPO_ERROR', 2);

	if (!empty($_GET['browserepo']))
		browsePluginRepo();
	elseif (!empty($_GET['editrepo']))
		editPluginRepo();
	elseif (isset($_GET['upload']))
		uploadPlugin();
	else
	{
		wetem::load('add_plugins');
		$context['page_title'] = $txt['plugins_add_plugins'];

		// OK, so let's get the details of the repos to list.
		$context['plugin_repositories'] = array();
		$query = wesql::query('
			SELECT id_server, name, username, password, status
			FROM {db_prefix}package_servers
			ORDER BY name');
		while ($row = wesql::fetch_assoc($query))
			$context['plugin_repositories'][$row['id_server']] = array(
				'name' => $row['name'],
				'auth' => !empty($row['username']) && !empty($row['password']),
				'status' => $row['status'],
			);
		wesql::free_result($query);
	}
}

function browsePluginRepo()
{
	global $context;

	$repo_id = (int) $_GET['browserepo'];
	if ($repo_id > 0)
	{
		$query = wesql::query('
			SELECT id_server, name, url, username, password
			FROM {db_prefix}package_servers
			WHERE id_server = {int:repo}',
			array(
				'repo' => $repo_id,
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['repository'] = array(
				'name' => $row['name'],
				'url' => $row['url'],
				'username' => $row['username'],
				'password' => $row['password'],
			);
		wesql::free_result($query);
	}
	if (empty($context['repository']))
		fatal_lang_error('plugins_browse_invalid_error', false);

	// So, now we're cooking.
	loadSource('Class-WebGet');
	$weget = new weget($context['repository']['url']);
	// Using auth? Attach the supplied username and password, but be sure to salt and hash it first. (Yes, this is re-hashing an existing hash, however it is resalted.)
	if (!empty($context['repository']['username']) && !empty($context['repository']['password']))
		$weget->addPostVar('auth', $context['repository']['username'] . '=' . sha1(strtolower($context['repository']['url']) . $context['repository']['password']));
}

function editPluginRepo()
{
	global $context, $txt;

	// Are we perhaps deleting? Y U NO LIEK ME?
	if (isset($_POST['delete'], $_GET['editrepo']))
	{
		checkSession();
		$repo_id = (int) $_GET['editrepo'];
		if ($repo_id > 0)
			wesql::query('
				DELETE FROM {db_prefix}package_servers
				WHERE id_server = {int:repo}',
				array(
					'repo' => $repo_id,
				)
			);

		// Whether we deleted it, or if it wasn't valid and it was just nonsense, go back to the repo listing.
		redirectexit('action=admin;area=plugins;sa=add');
	}

	// Are we saving?
	if (isset($_GET['save'], $_GET['editrepo']))
	{
		checkSession();
		// Is it pre-existing? If so, get the details of it.
		$repo_id = (int) $_GET['editrepo'];
		if ($repo_id > 0)
		{
			$query = wesql::query('
				SELECT id_server, name, url, username, password, status
				FROM {db_prefix}package_servers
				WHERE id_server = {int:repo}',
				array(
					'repo' => $repo_id,
				)
			);
			while ($row = wesql::fetch_assoc($query))
				$context['repository'] = array(
					'name' => $row['name'],
					'url' => $row['url'],
					'username' => $row['username'],
					'password' => $row['password'],
					'status' => $row['status'],
				);
			wesql::free_result($query);
			if (!empty($context['repository']))
				$context['repository']['id_server'] = $repo_id;
			else
				fatal_lang_error('plugins_edit_invalid_error', false);
		}
		else
			$context['repository'] = array(
				'name' => '',
				'url' => '',
				'username' => '',
				'password' => '',
				'status' => REPO_INACTIVE,
			);

		// Now, let's see if we have some details.
		if (isset($_POST['name']))
			$context['repository']['name'] = trim(htmlspecialchars($_POST['name']));
		if (empty($context['repository']['name']))
			fatal_lang_error('plugins_auth_no_name', false);

		if (isset($_POST['url']))
			$context['repository']['url'] = trim($_POST['url']);
		if (empty($context['repository']['url']))
			fatal_lang_error('plugins_auth_no_url', false);
		elseif (strpos($context['repository']['url'], 'http://') !== 0 && strpos($context['repository']['url'], 'https://') !== 0)
			fatal_lang_error('plugins_auth_invalid_url', false);

		if (!empty($_POST['active']))
			$context['repository']['status'] = REPO_ACTIVE;

		// Username/password is tricky. If password's given, username should be too.
		if (!empty($_POST['password']))
		{
			if (empty($_POST['username']) || trim($_POST['username']) == '')
				fatal_lang_error('plugins_auth_pwd_nouser', false);
			$context['repository']['username'] = htmlspecialchars($_POST['username']);
			$context['repository']['password'] = sha1(strtolower($context['repository']['username']) . $_POST['password']);
		}
		else
		{
			// We didn't get a password. Did we get a username?
			if (empty($_POST['username']))
			{
				$context['repository']['username'] = '';
				$context['repository']['password'] = '';
			}
			// We didn't get a password, and the username is different. Since the password is hashed using the username... we need a new password too.
			elseif (htmlspecialchars($_POST['username']) != $context['repository']['username'])
				fatal_lang_error('plugins_auth_diffuser', false);
		}

		// OK, time for the final save.
		$columns = array_flip(array_keys($context['repository']));
		foreach ($columns as $k => $v)
			$columns[$k] = $k == 'id_server' || $k == 'status' ? 'int' : 'string';

		wesql::insert(isset($columns['id_server']) ? 'replace' : 'insert',
			'{db_prefix}package_servers',
			$columns,
			$context['repository'],
			array('id_server')
		);

		redirectexit('action=admin;area=plugins;sa=add');
	}

	// We're reusing the same tab but it has a different description now.
	$context[$context['admin_menu_name']]['tab_data']['tabs']['add']['description'] = $txt['plugins_edit_repo_desc'];

	if ($_GET['editrepo'] != 'add')
	{
		$repo_id = (int) $_GET['editrepo'];
		if ($repo_id > 0)
		{
			$query = wesql::query('
				SELECT id_server, name, url, username, password, status
				FROM {db_prefix}package_servers
				WHERE id_server = {int:repo}',
				array(
					'repo' => $repo_id,
				)
			);
			while ($row = wesql::fetch_assoc($query))
				$context['repository'] = array(
					'id' => $row['id_server'],
					'name' => $row['name'],
					'url' => htmlspecialchars($row['url']),
					'username' => $row['username'],
					'password' => !empty($row['password']), // We don't actually need the password, just to know if we have one
					'status' => $row['status'],
				);
			wesql::free_result($query);
			if (empty($context['repository']))
				$context['tried_to_find'] = true;
			else
				$context['page_title'] = $txt['plugins_edit_repo'];
		}
	}

	// Either we're adding or we had a nonsense value given. Either way, display the add dialogue.
	if (empty($context['repository']))
	{
		$context['page_title'] = $txt['plugins_add_repo'];
		$context['repository'] = array(
			'id' => 'new',
			'name' => '',
			'url' => 'http://',
			'username' => '',
			'status' => REPO_ACTIVE,
		);
	}

	wetem::load('edit_repo');
}

function uploadPlugin()
{
	// From the point of view of this function, setting up a plugin is a doddle, we just delegate everything.
	// Subs-Plugin.php on the other hand, not so amused.
	loadSource(array('Subs-Plugins', 'Class-ZipExtract'));
	$subs = array(
		0 => 'uploadedPluginValidate',
		1 => 'uploadedPluginConnection',
		2 => 'uploadedPluginPrune',
		3 => 'uploadedPluginFolders',
		4 => 'uploadedPluginFiles',
	);

	$_REQUEST['stage'] = isset($_GET['stage'], $subs[$_GET['stage']]) ? $_GET['stage'] : 0;

	$subs[$_REQUEST['stage']]();
}

function knownHooks()
{
	return array(
		'language' => array(
			'lang_help',
			'lang_who',
			'lang_modlog',
		),
		'function' => array(
			// Cornerstone items
			'pre_load',
			'determine_location',
			'detect_browser',
			'load_theme',
			'menu_items',
			'action_list',
			'behavior',
			// Threads and posts display
			'post_bbc_parse',
			'display_prepare_post',
			'display_post_done',
			'messageindex_buttons',
			'display_message_list',
			'display_main',
			// Admin
			'admin_areas',
			'admin_search',
			'admin_intro',
			'moderation_rules',
			'output_error',
			'settings_spam',
			'settings_bans',
			'remove_boards',
			'repair_errors_tests',
			'repair_errors_finished',
			'theme_settings',
			'member_prefs',
			'maintenance_routine',
			// User related
			'login',
			'validate_login',
			'logout',
			'change_member_data',
			'verify_password',
			'verify_user',
			'reset_pass',
			'other_passwords',
			'register',
			'register_validate',
			'register_post',
			'activate',
			'delete_member',
			'delete_member_multiple',
			'track_ip',
			// User permissions
			'load_permissions',
			'illegal_perms',
			'illegal_guest_perms',
			'banned_perms',
			// Content creation
			'outgoing_email',
			'personal_message',
			'create_post_before',
			'create_post_after',
			'modify_post_before',
			'modify_post_after',
			'move_topics',
			'remove_topics',
			'merge_topics',
			'post_mod_actions',
			'post_form_pre',
			'post_form',
			'post_form_load_draft',
			'post_pre_validate',
			'post_post_validate',
			'save_post_draft',
			'save_pm_draft',
			// Likes
			'like_handler',
			'liked_content',
			// Thoughts
			'thought_add',
			'thought_update',
			'thought_delete',
			// Process flow and execution
			'default_action',
			'fallback_action',
			'buffer',
			'redirect',
			'exit',
			'dynamic_rewrite',
			// Verification/CAPTCHA points
			'add_captcha',
			'verification_setup',
			'verification_test',
			'verification_refresh',
			'verification_display',
			// Who's Online
			'who_allowed',
			'whos_online',
			'whos_online_complete',
			// Miscellaneous
			'css_color',
			'buddy',
			'bbc_buttons',
			'place_credit',
			'get_boardindex',
			'info_center',
			'media_areas',
			'profile_areas',
			'ssi',
			'suggest',
			'thought',
			'select_quickmod',
			'apply_quickmod',
		),
	);
}
