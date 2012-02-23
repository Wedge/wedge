<?php
/**
 * Wedge
 *
 * Gathers all relevant information for the purpose of displaying a search form to the user.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	These functions are here for searching, and they are:

	void Search()
		- shows the screen to search forum posts (action=search), and uses the
		  simple version if the simpleSearch setting is enabled.
		- uses the main block of the Search template.
		- uses the Search language file.
		- requires the search_posts permission.
		- decodes and loads search parameters given in the URL (if any).
		- the form redirects to index.php?action=search2.

*/

// Ask the user what they want to search for.
function Search()
{
	global $txt, $scripturl, $settings, $user_info, $context;

	// Is the load average too high to allow searching just now?
	if (!empty($context['load_average']) && !empty($settings['loadavg_search']) && $context['load_average'] >= $settings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	loadLanguage('Search');
	// Don't load this in XML mode.
	if (!isset($_REQUEST['xml']))
		loadTemplate('Search');

	// Check the user's permissions.
	isAllowedTo('search_posts');

	// Link tree....
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=search',
		'name' => $txt['search']
	);

	// This is hard coded maximum string length.
	$context['search_string_limit'] = 100;

	$context['require_verification'] = $user_info['is_guest'] && !empty($settings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']);
	if ($context['require_verification'])
	{
		loadSource('Subs-Editor');
		$verificationOptions = array(
			'id' => 'search',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// If you got back from search2 by using the linktree, you get your original search parameters back.
	if (isset($_REQUEST['params']))
	{
		// Due to IE's 2083 character limit, we have to compress long search strings
		$temp_params = base64_decode(str_replace(array('-', '_', '.'), array('+', '/', '='), $_REQUEST['params']));
		// Test for gzuncompress failing
		$temp_params2 = @gzuncompress($temp_params);
		$temp_params = explode('|"|', !empty($temp_params2) ? $temp_params2 : $temp_params);

		$context['search_params'] = array();
		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$context['search_params'][$k] = $v;
		}
		if (isset($context['search_params']['brd']))
			$context['search_params']['brd'] = $context['search_params']['brd'] == '' ? array() : explode(',', $context['search_params']['brd']);
	}

	if (isset($_REQUEST['search']))
		$context['search_params']['search'] = un_htmlspecialchars($_REQUEST['search']);

	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = westr::htmlspecialchars($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = htmlspecialchars($context['search_params']['userspec']);
	if (!empty($context['search_params']['searchtype']))
		$context['search_params']['searchtype'] = 2;
	if (!empty($context['search_params']['minage']))
		$context['search_params']['minage'] = (int) $context['search_params']['minage'];
	if (!empty($context['search_params']['maxage']))
		$context['search_params']['maxage'] = (int) $context['search_params']['maxage'];

	$context['search_params']['show_complete'] = !empty($context['search_params']['show_complete']);
	$context['search_params']['subject_only'] = !empty($context['search_params']['subject_only']);

	// Load the error text strings if there were errors in the search.
	if (!empty($context['search_errors']))
	{
		loadLanguage('Errors');
		$context['search_errors']['messages'] = array();
		foreach ($context['search_errors'] as $search_error => $dummy)
		{
			if ($search_error === 'messages')
				continue;

			$context['search_errors']['messages'][] = $txt['error_' . $search_error];
		}
	}

	// Find all the boards this user is allowed to see.
	$request = wesql::query('
		SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:empty_string}',
		array(
			'empty_string' => '',
		)
	);
	$context['num_boards'] = wesql::num_rows($request);
	$context['boards_check_all'] = true;
	$context['categories'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// This category hasn't been set up yet...
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array(
				'id' => $row['id_cat'],
				'name' => $row['cat_name'],
				'boards' => array()
			);

		// Set this board up, and let the template know when it's a child, so it can indent them.
		$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level'],
			'selected' => (empty($context['search_params']['brd']) && (empty($settings['recycle_enable']) || $row['id_board'] != $settings['recycle_board']) && !in_array($row['id_board'], $user_info['ignoreboards'])) || (!empty($context['search_params']['brd']) && in_array($row['id_board'], $context['search_params']['brd']))
		);

		// If a board wasn't checked that probably should have been, ensure the board selection is selected!
		if (!$context['categories'][$row['id_cat']]['boards'][$row['id_board']]['selected'] && (empty($settings['recycle_enable']) || $row['id_board'] != $settings['recycle_board']))
			$context['boards_check_all'] = false;
	}
	wesql::free_result($request);

	// Now, let's sort the list of categories into the boards for templates that like that.
	$temp_boards = array();
	foreach ($context['categories'] as $category)
	{
		$temp_boards[] = array(
			'name' => $category['name'],
			'child_ids' => array_keys($category['boards'])
		);
		$temp_boards = array_merge($temp_boards, array_values($category['boards']));

		// Include a list of boards per category for easy toggling.
		$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);
	}

	$max_boards = ceil(count($temp_boards) / 2);
	if ($max_boards == 1)
		$max_boards = 2;

	// Now, alternate them so they can be shown left and right ;).
	$context['board_columns'] = array();
	for ($i = 0; $i < $max_boards; $i++)
	{
		$context['board_columns'][] = $temp_boards[$i];
		if (isset($temp_boards[$i + $max_boards]))
			$context['board_columns'][] = $temp_boards[$i + $max_boards];
		else
			$context['board_columns'][] = array();
	}

	if (!empty($_REQUEST['topic']))
	{
		$context['search_params']['topic'] = (int) $_REQUEST['topic'];
		$context['search_params']['show_complete'] = true;
	}
	if (!empty($context['search_params']['topic']))
	{
		$context['search_params']['topic'] = (int) $context['search_params']['topic'];

		$context['search_topic'] = array(
			'id' => $context['search_params']['topic'],
			'href' => $scripturl . '?topic=' . $context['search_params']['topic'] . '.0',
		);

		$request = wesql::query('
			SELECT ms.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:search_topic_id}
				AND {query_see_board}
				AND {query_see_topic}
			LIMIT 1',
			array(
				'search_topic_id' => $context['search_params']['topic'],
			)
		);

		if (wesql::num_rows($request) == 0)
			fatal_lang_error('topic_gone', false);

		list ($context['search_topic']['subject']) = wesql::fetch_row($request);
		wesql::free_result($request);

		$context['search_topic']['link'] = '<a href="' . $context['search_topic']['href'] . '">' . $context['search_topic']['subject'] . '</a>';
	}

	// Simple or not?
	$context['simple_search'] = isset($context['search_params']['advanced']) ? empty($context['search_params']['advanced']) : !empty($settings['simpleSearch']) && !isset($_REQUEST['advanced']);
	$context['page_title'] = $txt['set_parameters'];
}

?>