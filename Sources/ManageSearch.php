<?php
/**********************************************************************************
* ManageSearch.php                                                                *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC4                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/
if (!defined('SMF'))
	die('Hacking attempt...');

/* The admin screen to change the search settings.

	void ManageSearch()
		- main entry point for the admin search settings screen.
		- called by ?action=admin;area=managesearch.
		- requires the admin_forum permission.
		- loads the ManageSearch template.
		- loads the Search language file.
		- calls a function based on the given sub-action.
		- defaults to sub-action 'settings'.

	void EditSearchSettings()
		- edit some general settings related to the search function.
		- called by ?action=admin;area=managesearch;sa=settings.
		- requires the admin_forum permission.
		- uses the 'modify_settings' sub template of the ManageSearch template.

	void EditWeights()
		- edit the relative weight of the search factors.
		- called by ?action=admin;area=managesearch;sa=weights.
		- requires the admin_forum permission.
		- uses the 'modify_weights' sub template of the ManageSearch template.

	void EditSearchMethod()
		- edit the search method and search index used.
		- called by ?action=admin;area=managesearch;sa=method.
		- requires the admin_forum permission.
		- uses the 'select_search_method' sub template of the ManageSearch
		  template.
		- allows to create and delete a fulltext index on the messages table.
		- allows to delete a custom index (that CreateMessageIndex() created).
		- calculates the size of the current search indexes in use.

	void CreateMessageIndex()
		- create a custom search index for the messages table.
		- called by ?action=admin;area=managesearch;sa=createmsgindex.
		- linked from the EditSearchMethod screen.
		- requires the admin_forum permission.
		- uses the 'create_index', 'create_index_progress', and
		  'create_index_done' sub templates of the ManageSearch template.
		- depending on the size of the message table, the process is divided
		  in steps.

	array loadSearchAPIs()
		- get the installed APIs.

*/

function ManageSearch()
{
	global $context, $txt, $scripturl;

	isAllowedTo('admin_forum');

	loadLanguage('Search');
	loadTemplate('ManageSearch');

	weDB::extend('search');

	$subActions = array(
		'settings' => 'EditSearchSettings',
		'weights' => 'EditWeights',
		'method' => 'EditSearchMethod',
		'createfulltext' => 'EditSearchMethod',
		'removecustom' => 'EditSearchMethod',
		'removefulltext' => 'EditSearchMethod',
		'createmsgindex' => 'CreateMessageIndex',
	);

	// Default the sub-action to 'edit search settings'.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'weights';

	$context['sub_action'] = $_REQUEST['sa'];

	// Create the tabs for the template.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['manage_search'],
		'help' => 'search',
		'description' => $txt['search_settings_desc'],
		'tabs' => array(
			'weights' => array(
				'description' => $txt['search_weights_desc'],
			),
			'method' => array(
				'description' => $txt['search_method_desc'],
			),
			'settings' => array(
				'description' => $txt['search_settings_desc'],
			),
		),
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

function EditSearchSettings($return_config = false)
{
	global $txt, $context, $scripturl;

	// What are we editing anyway?
	$config_vars = array(
			// Permission...
			array('permissions', 'search_posts'),
			// Some simple settings.
			array('check', 'simpleSearch'),
			array('int', 'search_results_per_page'),
			array('int', 'search_max_results', 'subtext' => $txt['search_max_results_disable']),
		'',
			// Some limitations.
			array('int', 'search_floodcontrol_time', 'subtext' => $txt['search_floodcontrol_time_desc']),
	);

	if ($return_config)
		return $config_vars;

	$context['page_title'] = $txt['search_settings_title'];
	$context['sub_template'] = 'show_settings';

	// We'll need this for the settings.
	loadSource('ManageServer');

	// A form was submitted.
	if (isset($_REQUEST['save']))
	{
		checkSession();

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=managesearch;sa=settings;' . $context['session_var'] . '=' . $context['session_id']);
	}

	// Prep the template!
	$context['post_url'] = $scripturl . '?action=admin;area=managesearch;save;sa=settings';
	$context['settings_title'] = $txt['search_settings_title'];

	prepareDBSettingContext($config_vars);
}

function EditWeights()
{
	global $txt, $context, $modSettings;

	$context['page_title'] = $txt['search_weights_title'];
	$context['sub_template'] = 'modify_weights';

	$factors = array(
		'search_weight_frequency',
		'search_weight_age',
		'search_weight_length',
		'search_weight_subject',
		'search_weight_first_message',
		'search_weight_sticky',
	);

	// A form was submitted.
	if (isset($_POST['save']))
	{
		checkSession();

		$changes = array();
		foreach ($factors as $factor)
			$changes[$factor] = (int) $_POST[$factor];
		updateSettings($changes);
	}

	$context['relative_weights'] = array('total' => 0);
	foreach ($factors as $factor)
		$context['relative_weights']['total'] += isset($modSettings[$factor]) ? $modSettings[$factor] : 0;

	foreach ($factors as $factor)
		$context['relative_weights'][$factor] = round(100 * (isset($modSettings[$factor]) ? $modSettings[$factor] : 0) / $context['relative_weights']['total'], 1);
}

function EditSearchMethod()
{
	global $txt, $context, $modSettings, $smcFunc, $db_prefix;

	$context[$context['admin_menu_name']]['current_subsection'] = 'method';
	$context['page_title'] = $txt['search_method_title'];
	$context['sub_template'] = 'select_search_method';
	$context['supports_fulltext'] = weDBSearch::supports('fulltext');

	// Load any apis.
	$context['search_apis'] = loadSearchAPIs();

	// Detect whether a fulltext index is set.
	if ($context['supports_fulltext'])
	{
		$request = weDB::query('
			SHOW INDEX
			FROM {db_prefix}messages',
			array(
			)
		);
		$context['fulltext_index'] = '';
		if ($request !== false || weDB::num_rows($request) != 0)
		{
			while ($row = weDB::fetch_assoc($request))
				if ($row['Column_name'] == 'body' && (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT' || isset($row['Comment']) && $row['Comment'] == 'FULLTEXT'))
					$context['fulltext_index'][] = $row['Key_name'];
			weDB::free_result($request);

			if (is_array($context['fulltext_index']))
				$context['fulltext_index'] = array_unique($context['fulltext_index']);
		}

		$request = weDB::query('
			SHOW COLUMNS
			FROM {db_prefix}messages',
			array(
			)
		);
		if ($request !== false)
		{
			while ($row = weDB::fetch_assoc($request))
				if ($row['Field'] == 'body' && $row['Type'] == 'mediumtext')
					$context['cannot_create_fulltext'] = true;
			weDB::free_result($request);
		}

		if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
			$request = weDB::query('
				SHOW TABLE STATUS
				FROM {string:database_name}
				LIKE {string:table_name}',
				array(
					'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
					'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
				)
			);
		else
			$request = weDB::query('
				SHOW TABLE STATUS
				LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
				)
			);

		if ($request !== false)
		{
			while ($row = weDB::fetch_assoc($request))
				if ((isset($row['Type']) && strtolower($row['Type']) != 'myisam') || (isset($row['Engine']) && strtolower($row['Engine']) != 'myisam'))
					$context['cannot_create_fulltext'] = true;
			weDB::free_result($request);
		}
	}

	if (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'createfulltext')
	{
		checkSession('get');

		// Make sure it's gone before creating it.
		weDB::query('
			ALTER TABLE {db_prefix}messages
			DROP INDEX body',
			array(
				'db_error_skip' => true,
			)
		);

		weDB::query('
			ALTER TABLE {db_prefix}messages
			ADD FULLTEXT body (body)',
			array(
			)
		);

		$context['fulltext_index'] = 'body';
	}
	elseif (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'removefulltext' && !empty($context['fulltext_index']))
	{
		checkSession('get');

		weDB::query('
			ALTER TABLE {db_prefix}messages
			DROP INDEX ' . implode(',
			DROP INDEX ', $context['fulltext_index']),
			array(
				'db_error_skip' => true,
			)
		);

		$context['fulltext_index'] = '';

		// Go back to the default search method.
		if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'fulltext')
			updateSettings(array(
				'search_index' => '',
			));
	}
	elseif (!empty($_REQUEST['sa']) && $_REQUEST['sa'] == 'removecustom')
	{
		checkSession('get');

		weDB::extend();
		$tables = weDBExtra::list_tables(false, $db_prefix . 'log_search_words');
		if (!empty($tables))
		{
			weDB::query('
				DROP TABLE {db_prefix}log_search_words',
				array(
				)
			);
		}

		updateSettings(array(
			'search_custom_index_config' => '',
			'search_custom_index_resume' => '',
		));

		// Go back to the default search method.
		if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'custom')
			updateSettings(array(
				'search_index' => '',
			));
	}
	elseif (isset($_POST['save']))
	{
		checkSession();
		updateSettings(array(
			'search_index' => empty($_POST['search_index']) || (!in_array($_POST['search_index'], array('fulltext', 'custom')) && !isset($context['search_apis'][$_POST['search_index']])) ? '' : $_POST['search_index'],
			'search_force_index' => isset($_POST['search_force_index']) ? '1' : '0',
			'search_match_words' => isset($_POST['search_match_words']) ? '1' : '0',
		));
	}

	$context['table_info'] = array(
		'data_length' => 0,
		'index_length' => 0,
		'fulltext_length' => 0,
		'custom_index_length' => 0,
	);

	// Get some info about the messages table, to show its size and index size.
	if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
		$request = weDB::query('
			SHOW TABLE STATUS
			FROM {string:database_name}
			LIKE {string:table_name}',
			array(
				'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
				'table_name' => str_replace('_', '\_', $match[2]) . 'messages',
			)
		);
	else
		$request = weDB::query('
			SHOW TABLE STATUS
			LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $db_prefix) . 'messages',
			)
		);
	if ($request !== false && weDB::num_rows($request) == 1)
	{
		// Only do this if the user has permission to execute this query.
		$row = weDB::fetch_assoc($request);
		$context['table_info']['data_length'] = $row['Data_length'];
		$context['table_info']['index_length'] = $row['Index_length'];
		$context['table_info']['fulltext_length'] = $row['Index_length'];
		weDB::free_result($request);
	}

	// Now check the custom index table, if it exists at all.
	if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
		$request = weDB::query('
			SHOW TABLE STATUS
			FROM {string:database_name}
			LIKE {string:table_name}',
			array(
				'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
				'table_name' => str_replace('_', '\_', $match[2]) . 'log_search_words',
			)
		);
	else
		$request = weDB::query('
			SHOW TABLE STATUS
			LIKE {string:table_name}',
			array(
				'table_name' => str_replace('_', '\_', $db_prefix) . 'log_search_words',
			)
		);
	if ($request !== false && weDB::num_rows($request) == 1)
	{
		// Only do this if the user has permission to execute this query.
		$row = weDB::fetch_assoc($request);
		$context['table_info']['index_length'] += $row['Data_length'] + $row['Index_length'];
		$context['table_info']['custom_index_length'] = $row['Data_length'] + $row['Index_length'];
		weDB::free_result($request);
	}

	// Format the data and index length in kilobytes.
	foreach ($context['table_info'] as $type => $size)
	{
		// If it's not numeric then just break.  This database engine doesn't support size.
		if (!is_numeric($size))
			break;

		$context['table_info'][$type] = comma_format($context['table_info'][$type] / 1024) . ' ' . $txt['search_method_kilobytes'];
	}

	$context['custom_index'] = !empty($modSettings['search_custom_index_config']);
	$context['partial_custom_index'] = !empty($modSettings['search_custom_index_resume']) && empty($modSettings['search_custom_index_config']);
	$context['double_index'] = !empty($context['fulltext_index']) && $context['custom_index'];
}

function CreateMessageIndex()
{
	global $modSettings, $context, $smcFunc, $db_prefix, $txt;

	// Scotty, we need more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	$context[$context['admin_menu_name']]['current_subsection'] = 'method';
	$context['page_title'] = $txt['search_index_custom'];

	$messages_per_batch = 50;

	$index_properties = array(
		2 => array(
			'column_definition' => 'small',
			'step_size' => 1000000,
		),
		4 => array(
			'column_definition' => 'medium',
			'step_size' => 1000000,
			'max_size' => 16777215,
		),
		5 => array(
			'column_definition' => 'large',
			'step_size' => 100000000,
			'max_size' => 2000000000,
		),
	);

	if (isset($_REQUEST['resume']) && !empty($modSettings['search_custom_index_resume']))
	{
		$context['index_settings'] = unserialize($modSettings['search_custom_index_resume']);
		$context['start'] = (int) $context['index_settings']['resume_at'];
		unset($context['index_settings']['resume_at']);
		$context['step'] = 1;
	}
	else
	{
		$context['index_settings'] = array(
			'bytes_per_word' => isset($_REQUEST['bytes_per_word'], $index_properties[$_REQUEST['bytes_per_word']]) ? (int) $_REQUEST['bytes_per_word'] : 2,
		);
		$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$context['step'] = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : 0;
	}

	if ($context['step'] !== 0)
		checkSession('request');

	// Step 0: let the user determine how they like their index.
	if ($context['step'] === 0)
	{
		$context['sub_template'] = 'create_index';
	}

	// Step 1: insert all the words.
	if ($context['step'] === 1)
	{
		$context['sub_template'] = 'create_index_progress';

		if ($context['start'] === 0)
		{
			weDB::extend();
			$tables = weDBExtra::list_tables(false, $db_prefix . 'log_search_words');
			if (!empty($tables))
			{
				weDB::query('
					DROP TABLE {db_prefix}log_search_words',
					array(
					)
				);
			}

			weDBSearch::create_word_search($index_properties[$context['index_settings']['bytes_per_word']]['column_definition']);

			// Temporarily switch back to not using a search index.
			if (!empty($modSettings['search_index']) && $modSettings['search_index'] == 'custom')
				updateSettings(array('search_index' => ''));

			// Don't let simultanious processes be updating the search index.
			if (!empty($modSettings['search_custom_index_config']))
				updateSettings(array('search_custom_index_config' => ''));
		}

		$num_messages = array(
			'done' => 0,
			'todo' => 0,
		);

		$request = weDB::query('
			SELECT id_msg >= {int:starting_id} AS todo, COUNT(*) AS num_messages
			FROM {db_prefix}messages
			GROUP BY todo',
			array(
				'starting_id' => $context['start'],
			)
		);
		while ($row = weDB::fetch_assoc($request))
			$num_messages[empty($row['todo']) ? 'done' : 'todo'] = $row['num_messages'];

		if (empty($num_messages['todo']))
		{
			$context['step'] = 2;
			$context['percentage'] = 80;
			$context['start'] = 0;
		}
		else
		{
			// Number of seconds before the next step.
			$stop = time() + 3;
			while (time() < $stop)
			{
				$inserts = array();
				$request = weDB::query('
					SELECT id_msg, body
					FROM {db_prefix}messages
					WHERE id_msg BETWEEN {int:starting_id} AND {int:ending_id}
					LIMIT {int:limit}',
					array(
						'starting_id' => $context['start'],
						'ending_id' => $context['start'] + $messages_per_batch - 1,
						'limit' => $messages_per_batch,
					)
				);
				$forced_break = false;
				$number_processed = 0;
				while ($row = weDB::fetch_assoc($request))
				{
					// In theory it's possible for one of these to take friggin ages so add more timeout protection.
					if ($stop < time())
					{
						$forced_break = true;
						break;
					}

					$number_processed++;
					foreach (text2words($row['body'], $context['index_settings']['bytes_per_word'], true) as $id_word)
					{
						$inserts[] = array($id_word, $row['id_msg']);
					}
				}
				$num_messages['done'] += $number_processed;
				$num_messages['todo'] -= $number_processed;
				weDB::free_result($request);

				$context['start'] += $forced_break ? $number_processed : $messages_per_batch;

				if (!empty($inserts))
					weDB::insert('ignore',
						'{db_prefix}log_search_words',
						array('id_word' => 'int', 'id_msg' => 'int'),
						$inserts,
						array('id_word', 'id_msg')
					);
				if ($num_messages['todo'] === 0)
				{
					$context['step'] = 2;
					$context['start'] = 0;
					break;
				}
				else
					updateSettings(array('search_custom_index_resume' => serialize(array_merge($context['index_settings'], array('resume_at' => $context['start'])))));
			}

			// Since there are still two steps to go, 90% is the maximum here.
			$context['percentage'] = round($num_messages['done'] / ($num_messages['done'] + $num_messages['todo']), 3) * 80;
		}
	}

	// Step 2: removing the words that occur too often and are of no use.
	elseif ($context['step'] === 2)
	{
		if ($context['index_settings']['bytes_per_word'] < 4)
			$context['step'] = 3;
		else
		{
			$stop_words = $context['start'] === 0 || empty($modSettings['search_stopwords']) ? array() : explode(',', $modSettings['search_stopwords']);
			$stop = time() + 3;
			$context['sub_template'] = 'create_index_progress';
			$max_messages = ceil(60 * $modSettings['totalMessages'] / 100);

			while (time() < $stop)
			{
				$request = weDB::query('
					SELECT id_word, COUNT(id_word) AS num_words
					FROM {db_prefix}log_search_words
					WHERE id_word BETWEEN {int:starting_id} AND {int:ending_id}
					GROUP BY id_word
					HAVING COUNT(id_word) > {int:minimum_messages}',
					array(
						'starting_id' => $context['start'],
						'ending_id' => $context['start'] + $index_properties[$context['index_settings']['bytes_per_word']]['step_size'] - 1,
						'minimum_messages' => $max_messages,
					)
				);
				while ($row = weDB::fetch_assoc($request))
					$stop_words[] = $row['id_word'];
				weDB::free_result($request);

				updateSettings(array('search_stopwords' => implode(',', $stop_words)));

				if (!empty($stop_words))
					weDB::query('
						DELETE FROM {db_prefix}log_search_words
						WHERE id_word in ({array_int:stop_words})',
						array(
							'stop_words' => $stop_words,
						)
					);

				$context['start'] += $index_properties[$context['index_settings']['bytes_per_word']]['step_size'];
				if ($context['start'] > $index_properties[$context['index_settings']['bytes_per_word']]['max_size'])
				{
					$context['step'] = 3;
					break;
				}
			}
			$context['percentage'] = 80 + round($context['start'] / $index_properties[$context['index_settings']['bytes_per_word']]['max_size'], 3) * 20;
		}
	}

	// Step 3: remove words not distinctive enough.
	if ($context['step'] === 3)
	{
		$context['sub_template'] = 'create_index_done';

		updateSettings(array('search_index' => 'custom', 'search_custom_index_config' => serialize($context['index_settings'])));
		weDB::query('
			DELETE FROM {db_prefix}settings
			WHERE variable = {string:search_custom_index_resume}',
			array(
				'search_custom_index_resume' => 'search_custom_index_resume',
			)
		);
	}
}

// Get the installed APIs.
function loadSearchAPIs()
{
	global $sourcedir, $txt;

	$apis = array();
	if ($dh = scandir($sourcedir))
	{
		foreach ($dh as $file)
		{
			if (!is_dir($file) && preg_match('~SearchAPI-([A-Za-z\d_]+)\.php~', $file, $matches))
			{
				// Check this is definitely a valid API!
				$fp = fopen($sourcedir . '/' . $file, 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				if (strpos($header, '* SearchAPI-' . $matches[1] . '.php') !== false)
				{
					require_once($sourcedir . '/' . $file);

					$index_name = strtolower($matches[1]);
					$search_class_name = $index_name . '_search';
					$searchAPI = new $search_class_name();

					// No Support? NEXT!
					if (!$searchAPI->is_supported)
						continue;

					$apis[$index_name] = array(
						'filename' => $file,
						'setting_index' => $index_name,
						'has_template' => in_array($index_name, array('custom', 'fulltext', 'standard')),
						'label' => $index_name && isset($txt['search_index_' . $index_name]) ? $txt['search_index_' . $index_name] : '',
						'desc' => $index_name && isset($txt['search_index_' . $index_name . '_desc']) ? $txt['search_index_' . $index_name . '_desc'] : '',
					);
				}
			}
		}
	}

	return $apis;
}

?>