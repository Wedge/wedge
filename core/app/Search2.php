<?php
/**
 * Receives and processes a search request, and forms the basis of the Search framework.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	These functions are here for searching, and they are:

	void Search2()
		- checks user input and searches the messages table for messages
		  matching the query.
		- requires the search_posts permission.
		- uses the results block of the Search template.
		- uses the Search language file.
		- stores the results into the search cache.
		- show the results of the search query.

	array prepareSearchContext(bool reset = false)
		- callback function for the results block.
		- loads the necessary contextual data to show a search result.

	int searchSort(string $wordA, string $wordB)
		- callback function for usort used to sort the fulltext results.
		- passes sorting duty to the current API.
*/

// Gather the results and show them.
function Search2()
{
	global $settings, $txt, $context;
	global $messages_request, $boards_can;
	global $excludedWords, $participants;

	// Search may be disabled if they're softly banned.
	soft_ban('search');

	if (!empty($context['load_average']) && !empty($settings['loadavg_search']) && $context['load_average'] >= $settings['loadavg_search'])
		fatal_lang_error('loadavg_search_disabled', false);

	preventPrefetch();

	$weight_factors = array(
		'frequency',
		'age',
		'length',
		'subject',
		'first_message',
		'pinned',
	);

	$weight = array();
	$weight_total = 0;
	foreach ($weight_factors as $weight_factor)
	{
		$weight[$weight_factor] = empty($settings['search_weight_' . $weight_factor]) ? 0 : (int) $settings['search_weight_' . $weight_factor];
		$weight_total += $weight[$weight_factor];
	}

	// Zero weight. Weightless. :P
	if (empty($weight_total))
		fatal_lang_error('search_invalid_weights');

	// These vars don't require an interface, they're just here for tweaking.
	$recentPercentage = 0.30;
	$humungousTopicPosts = 200;
	$maxMembersToSearch = 500;
	$maxMessageResults = empty($settings['search_max_results']) ? 0 : $settings['search_max_results'] * 5;

	// Start with no errors.
	$context['search_errors'] = array();

	// Number of pages hard maximum - normally not set at all.
	$settings['search_max_results'] = empty($settings['search_max_results']) ? 200 * $settings['search_results_per_page'] : (int) $settings['search_max_results'];
	// Maximum length of the string.
	$context['search_string_limit'] = 100;

	loadLanguage('Search');

	// Are you allowed?
	isAllowedTo('search_posts');

	loadSource(array('Display', 'Subs-Package'));

	// Load up the search API we are going to use.
	$settings['search_index'] = empty($settings['search_index']) ? 'standard' : $settings['search_index'];
	if (!loadSearchAPI($settings['search_index']))
		fatal_lang_error('search_api_missing');

	// Create an instance of the search API.
	$search_class_name = $settings['search_index'] . '_search';
	$searchAPI = new $search_class_name();
	if (!$searchAPI || !$searchAPI->isValid())
	{
		// Log the error.
		loadLanguage('Errors');
		log_error(sprintf($txt['search_api_not_compatible'], 'SearchAPI-' . ucwords($settings['search_index']) . '.php'), 'critical');

		loadSearchAPI('standard');
		$searchAPI = new standard_search();
	}

	// Did the user provide a scope preference?
	if (isset($_REQUEST['search_type']))
	{
		// Topic: do nothing, board: erase any topic IDs, everywhere: erase any options.
		if ($_REQUEST['search_type'] == 'board')
			unset($_REQUEST['topic']);
		elseif ($_REQUEST['search_type'] == 'tree' && !empty($_REQUEST['brd']))
		{
			unset($_REQUEST['topic']);
			loadSource('Subs-Boards');
			$_REQUEST['brd'] = getBoardChildren($_REQUEST['brd']);
		}
		elseif ($_REQUEST['search_type'] == 'everywhere')
			unset($_REQUEST['topic'], $_REQUEST['brd']);
	}
	else
		unset($_REQUEST['topic'], $_REQUEST['brd']);

	// No scope was picked within the search form, perhaps because of a JS error? Clean it up.
	if (isset($_REQUEST['brd'], $_REQUEST['topic']))
		unset($_REQUEST['brd']);

	// $search_params will carry all settings that differ from the default search parameters.
	// That way, the URLs involved in a search page will be kept as short as possible.
	$search_params = array();

	if (isset($_REQUEST['params']))
	{
		// Due to IE's 2083 character limit, we have to compress long search strings
		$temp_params = base64_decode(str_replace(array('-', '_', '.'), array('+', '/', '='), $_REQUEST['params']));
		// Test for gzuncompress failing
		$temp_params2 = @gzuncompress($temp_params);
		$temp_params = explode('|"|', !empty($temp_params2) ? $temp_params2 : $temp_params);

		foreach ($temp_params as $i => $data)
		{
			@list ($k, $v) = explode('|\'|', $data);
			$search_params[$k] = $v;
		}
		if (isset($search_params['brd']))
			$search_params['brd'] = wedge_ranged_explode(',', $search_params['brd']);
	}

	// 1 => 'allwords' (default, don't set as param) / 2 => 'anywords'.
	if (!empty($search_params['searchtype']) || (!empty($_REQUEST['searchtype']) && $_REQUEST['searchtype'] == 2))
		$search_params['searchtype'] = 2;

	// Minimum age of messages. Default to zero (don't set param in that case).
	if (!empty($search_params['minage']) || (!empty($_REQUEST['minage']) && $_REQUEST['minage'] > 0))
		$search_params['minage'] = !empty($search_params['minage']) ? (int) $search_params['minage'] : (int) $_REQUEST['minage'];

	// Maximum age of messages. Default to infinite (9999 days: param not set).
	if (!empty($search_params['maxage']) || (!empty($_REQUEST['maxage']) && $_REQUEST['maxage'] < 9999))
		$search_params['maxage'] = !empty($search_params['maxage']) ? (int) $search_params['maxage'] : (int) $_REQUEST['maxage'];

	// Searching a specific topic?
	if (!empty($_REQUEST['topic']))
	{
		$search_params['topic'] = (int) $_REQUEST['topic'];
		$search_params['show_complete'] = true;
	}
	elseif (!empty($search_params['topic']))
		$search_params['topic'] = (int) $search_params['topic'];

	if (!empty($search_params['minage']) || !empty($search_params['maxage']))
	{
		$request = wesql::query('
			SELECT ' . (empty($search_params['maxage']) ? '0, ' : 'IFNULL(MIN(id_msg), -1), ') . (empty($search_params['minage']) ? '0' : 'IFNULL(MAX(id_msg), -1)') . '
			FROM {db_prefix}messages
			WHERE 1=1' . ($settings['postmod_active'] ? '
				AND approved = {int:is_approved}' : '') . (empty($search_params['minage']) ? '' : '
				AND poster_time <= {int:timestamp_minimum_age}') . (empty($search_params['maxage']) ? '' : '
				AND poster_time >= {int:timestamp_maximum_age}'),
			array(
				'timestamp_minimum_age' => empty($search_params['minage']) ? 0 : time() - 86400 * $search_params['minage'],
				'timestamp_maximum_age' => empty($search_params['maxage']) ? 0 : time() - 86400 * $search_params['maxage'],
				'is_approved' => 1,
			)
		);
		list ($minMsgID, $maxMsgID) = wesql::fetch_row($request);
		if ($minMsgID < 0 || $maxMsgID < 0)
			$context['search_errors']['no_messages_in_time_frame'] = true;
		wesql::free_result($request);
	}

	// Default the user name to a wildcard matching every user (*).
	if (!empty($search_params['userspec']) || (!empty($_REQUEST['userspec']) && $_REQUEST['userspec'] != '*'))
		$search_params['userspec'] = isset($search_params['userspec']) ? $search_params['userspec'] : $_REQUEST['userspec'];

	// If there's no specific user, then don't mention it in the main query.
	if (empty($search_params['userspec']))
		$userQuery = '';
	else
	{
		$userString = strtr(westr::htmlspecialchars($search_params['userspec'], ENT_QUOTES), array('&quot;' => '"'));
		$userString = strtr($userString, array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_'));

		preg_match_all('~"([^"]+)"~', $userString, $matches);
		$possible_users = array_filter(array_map('trim', array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $userString)))));

		// Create a list of database-escaped search names.
		$realNameMatches = array();
		foreach ($possible_users as $possible_user)
			$realNameMatches[] = wesql::quote(
				'{string:possible_user}',
				array(
					'possible_user' => $possible_user
				)
			);

		// Retrieve a list of possible members.
		if (!empty($realNameMatches))
		{
			$request = wesql::query('
				SELECT id_member
				FROM {db_prefix}members
				WHERE {raw:match_possible_users}',
				array(
					'match_possible_users' => 'real_name LIKE ' . implode(' OR real_name LIKE ', $realNameMatches),
				)
			);
			// Simply do nothing if there're too many members matching the criteria.
			if (wesql::num_rows($request) > $maxMembersToSearch)
				$userQuery = '';
			elseif (wesql::num_rows($request) == 0)
			{
				$userQuery = wesql::quote(
					'm.id_member = {int:id_member_guest} AND ({raw:match_possible_guest_names})',
					array(
						'id_member_guest' => 0,
						'match_possible_guest_names' => 'm.poster_name LIKE ' . implode(' OR m.poster_name LIKE ', $realNameMatches),
					)
				);
			}
			else
			{
				$memberlist = array();
				while ($row = wesql::fetch_assoc($request))
					$memberlist[] = $row['id_member'];
				$userQuery = wesql::quote(
					'(m.id_member IN ({array_int:matched_members}) OR (m.id_member = {int:id_member_guest} AND ({raw:match_possible_guest_names})))',
					array(
						'matched_members' => $memberlist,
						'id_member_guest' => 0,
						'match_possible_guest_names' => 'm.poster_name LIKE ' . implode(' OR m.poster_name LIKE ', $realNameMatches),
					)
				);
			}
			wesql::free_result($request);
		}
	}

	// If the boards were passed by URL (params=), temporarily put them back in $_REQUEST.
	if (!empty($search_params['brd']) && is_array($search_params['brd']))
		$_REQUEST['brd'] = $search_params['brd'];

	// Ensure that brd is an array.
	if (!empty($_REQUEST['brd']) && !is_array($_REQUEST['brd']))
		$_REQUEST['brd'] = strpos($_REQUEST['brd'], ',') !== false ? wedge_ranged_explode(',', $_REQUEST['brd']) : array($_REQUEST['brd']);

	// Make sure all boards are integers.
	if (!empty($_REQUEST['brd']))
		foreach ($_REQUEST['brd'] as $id => $brd)
			$_REQUEST['brd'][$id] = (int) $brd;

	// Special case for boards: searching just one topic?
	if (!empty($search_params['topic']))
	{
		$request = wesql::query('
			SELECT b.id_board
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			WHERE t.id_topic = {int:search_topic_id}
				AND {query_see_board}
				AND {query_see_topic}
			LIMIT 1',
			array(
				'search_topic_id' => $search_params['topic'],
			)
		);

		if (wesql::num_rows($request) == 0)
			fatal_lang_error('topic_gone', false);

		$search_params['brd'] = array();
		list ($search_params['brd'][0]) = wesql::fetch_row($request);
		wesql::free_result($request);
	}
	// Select all boards you've selected AND are allowed to see.
	elseif (we::$is_admin && !empty($_REQUEST['brd']))
		$search_params['brd'] = empty($_REQUEST['brd']) ? array() : $_REQUEST['brd'];
	else
	{
		$request = wesql::query('
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND redirect = {string:empty_string}' . (empty($_REQUEST['brd']) ? (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board_id}' : '') : '
				AND b.id_board IN ({array_int:selected_search_boards})'),
			array(
				'empty_string' => '',
				'selected_search_boards' => empty($_REQUEST['brd']) ? array() : $_REQUEST['brd'],
				'recycle_board_id' => $settings['recycle_board'],
			)
		);
		$search_params['brd'] = array();
		while ($row = wesql::fetch_assoc($request))
			$search_params['brd'][] = $row['id_board'];
		wesql::free_result($request);

		// This error should probably only happen for hackers.
		if (empty($search_params['brd']))
			$context['search_errors']['no_boards_selected'] = true;
	}

	if (count($search_params['brd']) != 0)
	{
		foreach ($search_params['brd'] as $k => $v)
			$search_params['brd'][$k] = (int) $v;

		// If we've selected all boards, this parameter can be left empty.
		$request = wesql::query('
			SELECT COUNT(*)
			FROM {db_prefix}boards
			WHERE redirect = {string:empty_string}',
			array(
				'empty_string' => '',
			)
		);
		list ($num_boards) = wesql::fetch_row($request);
		wesql::free_result($request);

		if (count($search_params['brd']) == $num_boards)
			$boardQuery = '';
		elseif (count($search_params['brd']) == $num_boards - 1 && !empty($settings['recycle_board']) && !in_array($settings['recycle_board'], $search_params['brd']))
			$boardQuery = '!= ' . $settings['recycle_board'];
		else
			$boardQuery = 'IN (' . implode(', ', $search_params['brd']) . ')';
	}
	else
		$boardQuery = '';

	$search_params['show_complete'] = !empty($search_params['show_complete']) || !empty($_REQUEST['show_complete']);
	$search_params['subject_only'] = !empty($search_params['subject_only']) || !empty($_REQUEST['subject_only']);

	$context['compact'] = !$search_params['show_complete'];

	// Get the sorting parameters right. Default to sort by relevance descending.
	$sort_columns = array(
		'relevance',
		'num_replies',
		'id_msg',
	);
	if (empty($search_params['sort']) && !empty($_REQUEST['sort']))
		list ($search_params['sort'], $search_params['sort_dir']) = array_pad(explode('|', $_REQUEST['sort']), 2, '');
	$search_params['sort'] = !empty($search_params['sort']) && in_array($search_params['sort'], $sort_columns) ? $search_params['sort'] : 'relevance';
	if (!empty($search_params['topic']) && $search_params['sort'] === 'num_replies')
		$search_params['sort'] = 'id_msg';

	// Sorting direction: descending unless stated otherwise.
	$search_params['sort_dir'] = !empty($search_params['sort_dir']) && $search_params['sort_dir'] == 'asc' ? 'asc' : 'desc';

	// Determine some values needed to calculate the relevance.
	$minMsg = (int) ((1 - $recentPercentage) * $settings['maxMsgID']);
	$recentMsg = $settings['maxMsgID'] - $minMsg;

	// *** Parse the search query

	// Unfortunately, searching for words like this is going to be slow, so we're blacklisting them.
	// !!! Setting to add more here?
	// !!! Maybe only blacklist if they are the only word, or "any" is used?
	$blacklisted_words = array('img', 'url', 'quote', 'www', 'http', 'the', 'is', 'it', 'are', 'if');

	// What are we searching for?
	if (empty($search_params['search']))
	{
		if (isset($_GET['search']))
			$search_params['search'] = un_htmlspecialchars($_GET['search']);
		elseif (isset($_POST['search']))
			$search_params['search'] = $_POST['search'];
		else
			$search_params['search'] = '';
	}

	// Nothing??
	if (!isset($search_params['search']) || $search_params['search'] == '')
		$context['search_errors']['invalid_search_string'] = true;
	// Too long?
	elseif (westr::strlen($search_params['search']) > $context['search_string_limit'])
	{
		$context['search_errors']['string_too_long'] = true;
		$txt['error_string_too_long'] = sprintf($txt['error_string_too_long'], $context['search_string_limit']);
	}

	// Change non-word characters into spaces.
	$stripped_query = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', $search_params['search']);

	// Make the query lower case. It's gonna be case insensitive anyway.
	$stripped_query = un_htmlspecialchars(westr::strtolower($stripped_query));

	$no_regexp = preg_match('~&#(?:\d{1,7}|x[0-9a-fA-F]{1,6});~', $stripped_query) === 1;

	// Extract phrase parts first (e.g. some words "this is a phrase" some more words.)
	preg_match_all('/(?:^|\s)([-]?)"([^"]+)"(?:$|\s)/', $stripped_query, $matches);
	$phraseArray = $matches[2];

	// Remove the phrase parts and extract the words.
	$wordArray = explode(' ', preg_replace('~(?:^|\s)(?:[-]?)"(?:[^"]+)"(?:$|\s)~u', ' ', $search_params['search']));

	// A minus sign in front of a word excludes the word.... so...
	$excludedWords = array();
	$excludedIndexWords = array();
	$excludedSubjectWords = array();
	$excludedPhrases = array();

	// .. first, we check for things like -"some words", but not "-some words".
	foreach ($matches[1] as $index => $word)
		if ($word === '-')
		{
			if (($word = trim($phraseArray[$index], '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
				$excludedWords[] = $word;
			unset($phraseArray[$index]);
		}

	// Now we look for single-word -word requests.
	foreach ($wordArray as $index => $word)
		if (strpos(trim($word), '-') === 0)
		{
			if (($word = trim($word, '-_\' ')) !== '' && !in_array($word, $blacklisted_words))
				$excludedWords[] = $word;
			unset($wordArray[$index]);
		}

	// The remaining words and phrases are all included.
	$searchArray = array_merge($phraseArray, $wordArray);

	// Trim everything and make sure there are no words that are the same.
	foreach ($searchArray as $index => $value)
	{
		// Skip anything practically empty.
		if (($searchArray[$index] = trim($value, '-_\' ')) === '')
			unset($searchArray[$index]);
		// Skip blacklisted words. Make sure to note we skipped them in case we end up with nothing.
		elseif (in_array($searchArray[$index], $blacklisted_words))
		{
			$foundBlackListedWords = true;
			unset($searchArray[$index]);
		}
		// Don't allow very, very short words.
		elseif (westr::strlen($value) < 2)
		{
			$context['search_errors']['search_string_small_words'] = true;
			unset($searchArray[$index]);
		}
		else
			$searchArray[$index] = $searchArray[$index];
	}
	$searchArray = array_slice(array_unique($searchArray), 0, 10);

	// Create an array of replacements for highlighting.
	$context['mark'] = array();
	foreach ($searchArray as $word)
		$context['mark'][$word] = '<mark>' . $word . '</mark>';

	// Initialize two arrays storing the words that have to be searched for.
	$orParts = array();
	$searchWords = array();

	// Make sure at least one word is being searched for.
	if (empty($searchArray))
		$context['search_errors']['invalid_search_string' . (!empty($foundBlackListedWords) ? '_blacklist' : '')] = true;
	// All words/sentences must match.
	elseif (empty($search_params['searchtype']))
		$orParts[0] = $searchArray;
	// Any word/sentence must match.
	else
		foreach ($searchArray as $index => $value)
			$orParts[$index] = array($value);

	// Don't allow duplicate error messages if one string is too short.
	if (isset($context['search_errors']['search_string_small_words'], $context['search_errors']['invalid_search_string']))
		unset($context['search_errors']['invalid_search_string']);
	// Make sure the excluded words are in all or-branches.
	foreach ($orParts as $orIndex => $andParts)
		foreach ($excludedWords as $word)
			$orParts[$orIndex][] = $word;

	// Determine the or-branches and the fulltext search words.
	foreach ($orParts as $orIndex => $andParts)
	{
		$searchWords[$orIndex] = array(
			'indexed_words' => array(),
			'words' => array(),
			'subject_words' => array(),
			'all_words' => array(),
		);

		// Sort the indexed words (large words -> small words -> excluded words).
		if (method_exists($searchAPI, 'searchSort'))
			usort($orParts[$orIndex], array($searchAPI, 'searchSort'));

		foreach ($orParts[$orIndex] as $word)
		{
			$is_excluded = in_array($word, $excludedWords);

			$searchWords[$orIndex]['all_words'][] = $word;

			$subjectWords = text2words($word);
			if (!$is_excluded || count($subjectWords) === 1)
			{
				$searchWords[$orIndex]['subject_words'] = array_merge($searchWords[$orIndex]['subject_words'], $subjectWords);
				if ($is_excluded)
					$excludedSubjectWords = array_merge($excludedSubjectWords, $subjectWords);
			}
			else
				$excludedPhrases[] = $word;

			// Have we got indexes to prepare?
			if (method_exists($searchAPI, 'prepareIndexes'))
				$searchAPI->prepareIndexes($word, $searchWords[$orIndex], $excludedIndexWords, $is_excluded);
		}

		// Search_force_index requires all AND parts to have at least one fulltext word.
		if (!empty($settings['search_force_index']) && empty($searchWords[$orIndex]['indexed_words']))
		{
			$context['search_errors']['query_not_specific_enough'] = true;
			break;
		}
		elseif ($search_params['subject_only'] && empty($searchWords[$orIndex]['subject_words']) && empty($excludedSubjectWords))
		{
			$context['search_errors']['query_not_specific_enough'] = true;
			break;
		}
		// Make sure we aren't searching for too many indexed words.
		else
		{
			$searchWords[$orIndex]['indexed_words'] = array_slice($searchWords[$orIndex]['indexed_words'], 0, 7);
			$searchWords[$orIndex]['subject_words'] = array_slice($searchWords[$orIndex]['subject_words'], 0, 7);
		}
	}

	// *** Spell checking
	$context['show_spellchecking'] = function_exists('pspell_new');
	if ($context['show_spellchecking'])
	{
		// Windows fix.
		ob_start();
		$old = error_reporting(0);

		pspell_new('en');
		$pspell_link = pspell_new($txt['lang_dictionary'], $txt['lang_spelling'], '', 'utf-8', PSPELL_FAST | PSPELL_RUN_TOGETHER);

		if (!$pspell_link)
			$pspell_link = pspell_new('en', '', '', '', PSPELL_FAST | PSPELL_RUN_TOGETHER);

		error_reporting($old);
		ob_end_clean();

		$did_you_mean = array('search' => array(), 'display' => array());
		$found_misspelling = false;
		foreach ($searchArray as $word)
		{
			if (empty($pspell_link))
				continue;

			// Don't check phrases.
			if (preg_match('~^\w+$~', $word) === 0)
			{
				$did_you_mean['search'][] = '"' . $word . '"';
				$did_you_mean['display'][] = '&quot;' . westr::htmlspecialchars($word) . '&quot;';
				continue;
			}
			// For some strange reason spell check can crash PHP on decimals.
			elseif (preg_match('~\d~', $word) === 1)
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = westr::htmlspecialchars($word);
				continue;
			}
			elseif (pspell_check($pspell_link, $word))
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = westr::htmlspecialchars($word);
				continue;
			}

			$suggestions = pspell_suggest($pspell_link, $word);
			foreach ($suggestions as $i => $s)
			{
				// Search is case insensitive.
				if (westr::strtolower($s) == westr::strtolower($word))
					unset($suggestions[$i]);
				// Plus, don't suggest something the user thinks is rude!
				elseif ($suggestions[$i] != censorText($s))
					unset($suggestions[$i]);
			}

			// Anything found? If so, correct it!
			if (!empty($suggestions))
			{
				$suggestions = array_values($suggestions);
				$did_you_mean['search'][] = $suggestions[0];
				$did_you_mean['display'][] = '<em><strong>' . westr::htmlspecialchars($suggestions[0]) . '</strong></em>';
				$found_misspelling = true;
			}
			else
			{
				$did_you_mean['search'][] = $word;
				$did_you_mean['display'][] = westr::htmlspecialchars($word);
			}
		}

		if ($found_misspelling)
		{
			// Don't spell check excluded words, but add them still...
			$temp_excluded = array('search' => array(), 'display' => array());
			foreach ($excludedWords as $word)
			{
				if (preg_match('~^\w+$~', $word) == 0)
				{
					$temp_excluded['search'][] = '-"' . $word . '"';
					$temp_excluded['display'][] = '-&quot;' . westr::htmlspecialchars($word) . '&quot;';
				}
				else
				{
					$temp_excluded['search'][] = '-' . $word;
					$temp_excluded['display'][] = '-' . westr::htmlspecialchars($word);
				}
			}

			$did_you_mean['search'] = array_merge($did_you_mean['search'], $temp_excluded['search']);
			$did_you_mean['display'] = array_merge($did_you_mean['display'], $temp_excluded['display']);

			$temp_params = $search_params;
			$temp_params['search'] = implode(' ', $did_you_mean['search']);
			if (isset($temp_params['brd']))
				$temp_params['brd'] = wedge_ranged_implode(',', $temp_params['brd']);
			$context['params'] = array();
			foreach ($temp_params as $k => $v)
				$context['did_you_mean_params'][] = $k . '|\'|' . $v;
			$context['did_you_mean_params'] = base64_encode(implode('|"|', $context['did_you_mean_params']));
			$context['did_you_mean'] = implode(' ', $did_you_mean['display']);
		}
	}

	// Let the user adjust the search query, should they wish?
	$context['search_params'] = $search_params;
	if (isset($context['search_params']['search']))
		$context['search_params']['search'] = westr::htmlspecialchars($context['search_params']['search']);
	if (isset($context['search_params']['userspec']))
		$context['search_params']['userspec'] = westr::htmlspecialchars($context['search_params']['userspec']);

	// Do we have captcha enabled?
	if (we::$is_guest && !empty($settings['search_enable_captcha']) && empty($_SESSION['ss_vv_passed']) && (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != $search_params['search']))
	{
		// If we come from another search box tone down the error...
		if (!isset($_REQUEST['search_vv']))
			$context['search_errors']['need_verification_code'] = true;
		else
		{
			loadSource('Subs-Editor');
			$verificationOptions = array(
				'id' => 'search',
			);
			$context['require_verification'] = create_control_verification($verificationOptions, true);

			if (is_array($context['require_verification']))
			{
				foreach ($context['require_verification'] as $error)
					$context['search_errors'][$error] = true;
			}
			// Don't keep asking for it - they've proven themselves worthy.
			else
				$_SESSION['ss_vv_passed'] = true;
		}
	}

	// *** Encode all search params

	// All search params have been checked, let's compile them to a single string...
	$temp_params = $search_params;
	if (isset($temp_params['brd']))
		$temp_params['brd'] = wedge_ranged_implode(',', $temp_params['brd']);
	$context['params'] = array();
	foreach ($temp_params as $k => $v)
		$context['params'][] = $k . '|\'|' . $v;

	if (!empty($context['params']))
	{
		// Due to old IE's 2083 character limit, we have to compress long search strings
		$params = @gzcompress(implode('|"|', $context['params']));
		// Gzcompress failed, use try non-gz
		if (empty($params))
			$params = implode('|"|', $context['params']);
		// Base64 encode, then replace +/= with uri safe ones that can be reverted
		$context['params'] = str_replace(array('+', '/', '='), array('-', '_', '.'), base64_encode($params));
	}

	// ... and add the links to the link tree.
	add_linktree($txt['search'], '<URL>?action=search;params=' . $context['params']);
	add_linktree($txt['search_results'], '<URL>?action=search2;params=' . $context['params']);

	// *** A last error check

	// One or more search errors? Go back to the first search screen.
	if (!empty($context['search_errors']))
	{
		$_REQUEST['params'] = $context['params'];
		loadSource('Search');
		return Search();
	}

	// Spam me not, Spam-a-lot?
	if (empty($_SESSION['last_ss']) || $_SESSION['last_ss'] != $search_params['search'])
		spamProtection('search');
	// Store the last search string to allow pages of results to be browsed.
	$_SESSION['last_ss'] = $search_params['search'];

	// *** Reserve an ID for caching the search results.
	$query_params = array_merge($search_params, array(
		'min_msg_id' => isset($minMsgID) ? (int) $minMsgID : 0,
		'max_msg_id' => isset($maxMsgID) ? (int) $maxMsgID : 0,
		'memberlist' => !empty($memberlist) ? $memberlist : array(),
	));

	// Can this search rely on the API given the parameters?
	if (method_exists($searchAPI, 'searchQuery'))
	{
		$participants = array();
		$searchArray = array();

		$num_results = $searchAPI->searchQuery($query_params, $searchWords, $excludedIndexWords, $participants, $searchArray);
	}

	// Update the cache if the current search term is not yet cached.
	else
	{
		$update_cache = empty($_SESSION['search_cache']) || ($_SESSION['search_cache']['params'] != $context['params']);
		if ($update_cache)
		{
			// Increase the pointer...
			$settings['search_pointer'] = empty($settings['search_pointer']) ? 0 : (int) $settings['search_pointer'];
			// ...and store it right off.
			updateSettings(array('search_pointer' => $settings['search_pointer'] >= 255 ? 0 : $settings['search_pointer'] + 1));
			// As long as you don't change the parameters, the cache result is yours.
			$_SESSION['search_cache'] = array(
				'id_search' => $settings['search_pointer'],
				'num_results' => -1,
				'params' => $context['params'],
			);

			// Clear the previous cache of the final results cache.
			wesql::query('
				DELETE FROM {db_prefix}log_search_results
				WHERE id_search = {int:search_id}',
				array(
					'search_id' => $_SESSION['search_cache']['id_search'],
				)
			);

			if ($search_params['subject_only'])
			{
				// We do this to try and avoid duplicate keys on databases not supporting INSERT IGNORE.
				$inserts = array();
				foreach ($searchWords as $orIndex => $words)
				{
					$subject_query_params = array();
					$subject_query = array(
						'from' => '{db_prefix}topics AS t',
						'inner_join' => array(),
						'left_join' => array(),
						'where' => array('{query_see_topic}'),
					);

					$numTables = 0;
					$prev_join = 0;
					$numSubjectResults = 0;
					foreach ($words['subject_words'] as $subjectWord)
					{
						$numTables++;
						if (in_array($subjectWord, $excludedSubjectWords))
						{
							$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty($settings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';
							$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
						}
						else
						{
							$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';
							$subject_query['where'][] = 'subj' . $numTables . '.word ' . (empty($settings['search_match_words']) ? 'LIKE {string:subject_words_' . $numTables . '_wild}' : '= {string:subject_words_' . $numTables . '}');
							$prev_join = $numTables;
						}
						$subject_query_params['subject_words_' . $numTables] = $subjectWord;
						$subject_query_params['subject_words_' . $numTables . '_wild'] = '%' . $subjectWord . '%';
					}

					if (!empty($userQuery))
					{
						if ($subject_query['from'] != '{db_prefix}messages AS m')
							$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_topic = t.id_topic)';

						$subject_query['where'][] = $userQuery;
					}
					if (!empty($search_params['topic']))
						$subject_query['where'][] = 't.id_topic = ' . $search_params['topic'];
					if (!empty($minMsgID))
						$subject_query['where'][] = 't.id_first_msg >= ' . $minMsgID;
					if (!empty($maxMsgID))
						$subject_query['where'][] = 't.id_last_msg <= ' . $maxMsgID;
					if (!empty($boardQuery))
						$subject_query['where'][] = 't.id_board ' . $boardQuery;
					if (!empty($excludedPhrases))
					{
						if ($subject_query['from'] != '{db_prefix}messages AS m')
							$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';

						$count = 0;
						foreach ($excludedPhrases as $phrase)
						{
							$subject_query['where'][] = 'm.subject NOT ' . (empty($settings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:excluded_phrases_' . $count . '}';
							$subject_query_params['excluded_phrases_' . $count++] = empty($settings['search_match_words']) || $no_regexp ? '%' . strtr($phrase, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $phrase), '\\\'') . '[[:>:]]';
						}
					}

					// Avoid double inner joins, such as when using several negative search terms.
					if (!empty($subject_query['inner_join']))
						$subject_query['inner_join'] = array_flip(array_flip($subject_query['inner_join']));

					$ignoreRequest = wesql::query('
						INSERT IGNORE INTO {db_prefix}log_search_results
							(id_search, id_topic, relevance, id_msg, num_matches)
						SELECT
							{int:id_search},
							t.id_topic,
							1000 * (
								{int:weight_frequency} / (t.num_replies + 1) +
								{int:weight_age} * CASE WHEN t.id_first_msg < {int:min_msg} THEN 0 ELSE (t.id_first_msg - {int:min_msg}) / {int:recent_message} END +
								{int:weight_length} * CASE WHEN t.num_replies < {int:huge_topic_posts} THEN t.num_replies / {int:huge_topic_posts} ELSE 1 END +
								{int:weight_subject} +
								{int:weight_pinned} * t.is_pinned
							) / {int:weight_total} AS relevance,
							' . (empty($userQuery) ? 't.id_first_msg' : 'm.id_msg') . ',
							1
						FROM ' . $subject_query['from'] . (empty($subject_query['inner_join']) ? '' : '
							INNER JOIN ' . implode('
							INNER JOIN ', $subject_query['inner_join'])) . (empty($subject_query['left_join']) ? '' : '
							LEFT JOIN ' . implode('
							LEFT JOIN ', $subject_query['left_join'])) . '
						WHERE ' . implode('
							AND ', $subject_query['where']) . (empty($settings['search_max_results']) ? '' : '
						LIMIT ' . ($settings['search_max_results'] - $numSubjectResults)),
						array_merge($subject_query_params, array(
							'id_search' => $_SESSION['search_cache']['id_search'],
							'weight_age' => $weight['age'],
							'weight_frequency' => $weight['frequency'],
							'weight_length' => $weight['length'],
							'weight_pinned' => $weight['pinned'],
							'weight_subject' => $weight['subject'],
							'weight_total' => $weight_total,
							'min_msg' => $minMsg,
							'recent_message' => $recentMsg,
							'huge_topic_posts' => $humungousTopicPosts,
						))
					);

					$numSubjectResults += wesql::affected_rows();

					if (!empty($settings['search_max_results']) && $numSubjectResults >= $settings['search_max_results'])
						break;
				}

				// If there's data to be inserted for non-IGNORE databases do it here!
				if (!empty($inserts))
				{
					wesql::insert('',
						'{db_prefix}log_search_results',
						array('id_search' => 'int', 'id_topic' => 'int', 'relevance' => 'int', 'id_msg' => 'int', 'num_matches' => 'int'),
						$inserts
					);
				}

				$_SESSION['search_cache']['num_results'] = $numSubjectResults;
			}
			else
			{
				$main_query = array(
					'select' => array(
						'id_search' => $_SESSION['search_cache']['id_search'],
						'relevance' => '0',
					),
					'weights' => array(),
					'from' => '{db_prefix}topics AS t',
					'inner_join' => array(
						'{db_prefix}messages AS m ON (m.id_topic = t.id_topic)'
					),
					'left_join' => array(),
					'where' => array(),
					'group_by' => array(),
					'parameters' => array(
						'min_msg' => $minMsg,
						'recent_message' => $recentMsg,
						'huge_topic_posts' => $humungousTopicPosts,
						'is_approved' => 1,
					),
				);

				if (empty($search_params['topic']) && empty($search_params['show_complete']))
				{
					$main_query['select']['id_topic'] = 't.id_topic';
					$main_query['select']['id_msg'] = 'MAX(m.id_msg) AS id_msg';
					$main_query['select']['num_matches'] = 'COUNT(*) AS num_matches';

					$main_query['weights'] = array(
						'frequency' => 'COUNT(*) / (MAX(t.num_replies) + 1)',
						'age' => 'CASE WHEN MAX(m.id_msg) < {int:min_msg} THEN 0 ELSE (MAX(m.id_msg) - {int:min_msg}) / {int:recent_message} END',
						'length' => 'CASE WHEN MAX(t.num_replies) < {int:huge_topic_posts} THEN MAX(t.num_replies) / {int:huge_topic_posts} ELSE 1 END',
						'subject' => '0',
						'first_message' => 'CASE WHEN MIN(m.id_msg) = MAX(t.id_first_msg) THEN 1 ELSE 0 END',
						'pinned' => 'MAX(t.is_pinned)',
					);

					$main_query['group_by'][] = 't.id_topic';
				}
				else
				{
					// This is outrageous!
					$main_query['select']['id_topic'] = 'm.id_msg AS id_topic';
					$main_query['select']['id_msg'] = 'm.id_msg';
					$main_query['select']['num_matches'] = '1 AS num_matches';

					$main_query['weights'] = array(
						'age' => '((m.id_msg - t.id_first_msg) / CASE WHEN t.id_last_msg = t.id_first_msg THEN 1 ELSE t.id_last_msg - t.id_first_msg END)',
						'first_message' => 'CASE WHEN m.id_msg = t.id_first_msg THEN 1 ELSE 0 END',
					);

					if (!empty($search_params['topic']))
					{
						$main_query['where'][] = 't.id_topic = {int:topic}';
						$main_query['parameters']['topic'] = $search_params['topic'];
					}
					if (!empty($search_params['show_complete']))
						$main_query['group_by'][] = 'm.id_msg, t.id_first_msg, t.id_last_msg';
				}

				// *** Get the subject results.
				$numSubjectResults = 0;
				if (empty($search_params['topic']))
				{
					$inserts = array();
					// Create a temporary table to store some preliminary results in.
					wesql::query('
						DROP TABLE IF EXISTS {db_prefix}tmp_log_search_topics',
						array(
							'db_error_skip' => true,
						)
					);
					$createTemporary = wesql::query('
						CREATE TEMPORARY TABLE {db_prefix}tmp_log_search_topics (
							id_topic mediumint(8) unsigned NOT NULL default {string:string_zero},
							PRIMARY KEY (id_topic)
						) ENGINE=MEMORY',
						array(
							'string_zero' => '0',
							'db_error_skip' => true,
						)
					) !== false;

					// Clean up some previous cache.
					if (!$createTemporary)
						wesql::query('
							DELETE FROM {db_prefix}log_search_topics
							WHERE id_search = {int:search_id}',
							array(
								'search_id' => $_SESSION['search_cache']['id_search'],
							)
						);

					foreach ($searchWords as $orIndex => $words)
					{
						$subject_query = array(
							'from' => '{db_prefix}topics AS t',
							'inner_join' => array(),
							'left_join' => array(),
							'where' => array(),
							'params' => array(),
						);

						$numTables = 0;
						$prev_join = 0;
						$count = 0;
						foreach ($words['subject_words'] as $subjectWord)
						{
							$numTables++;
							if (in_array($subjectWord, $excludedSubjectWords))
							{
								if ($subject_query['from'] != '{db_prefix}messages AS m')
									$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';

								$subject_query['left_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.word ' . (empty($settings['search_match_words']) ? 'LIKE {string:subject_not_' . $count . '}' : '= {string:subject_not_' . $count . '}') . ' AND subj' . $numTables . '.id_topic = t.id_topic)';
								$subject_query['params']['subject_not_' . $count] = empty($settings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;

								$subject_query['where'][] = '(subj' . $numTables . '.word IS NULL)';
								$subject_query['where'][] = 'm.body NOT ' . (empty($settings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:body_not_' . $count . '}';
								$subject_query['params']['body_not_' . $count++] = empty($settings['search_match_words']) || $no_regexp ? '%' . strtr($subjectWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $subjectWord), '\\\'') . '[[:>:]]';
							}
							else
							{
								$subject_query['inner_join'][] = '{db_prefix}log_search_subjects AS subj' . $numTables . ' ON (subj' . $numTables . '.id_topic = ' . ($prev_join === 0 ? 't' : 'subj' . $prev_join) . '.id_topic)';
								$subject_query['where'][] = 'subj' . $numTables . '.word LIKE {string:subject_like_' . $count . '}';
								$subject_query['params']['subject_like_' . $count++] = empty($settings['search_match_words']) ? '%' . $subjectWord . '%' : $subjectWord;
								$prev_join = $numTables;
							}
						}

						if (!empty($userQuery))
						{
							if ($subject_query['from'] != '{db_prefix}messages AS m')
								$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';

							$subject_query['where'][] = '{raw:user_query}';
							$subject_query['params']['user_query'] = $userQuery;
						}
						if (!empty($search_params['topic']))
						{
							$subject_query['where'][] = 't.id_topic = {int:topic}';
							$subject_query['params']['topic'] = $search_params['topic'];
						}
						if (!empty($minMsgID))
						{
							$subject_query['where'][] = 't.id_first_msg >= {int:min_msg_id}';
							$subject_query['params']['min_msg_id'] = $minMsgID;
						}
						if (!empty($maxMsgID))
						{
							$subject_query['where'][] = 't.id_last_msg <= {int:max_msg_id}';
							$subject_query['params']['max_msg_id'] = $maxMsgID;
						}
						if (!empty($boardQuery))
						{
							$subject_query['where'][] = 't.id_board {raw:board_query}';
							$subject_query['params']['board_query'] = $boardQuery;
						}
						if (!empty($excludedPhrases))
						{
							if ($subject_query['from'] != '{db_prefix}messages AS m')
								$subject_query['inner_join'][] = '{db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)';

							$count = 0;
							foreach ($excludedPhrases as $phrase)
							{
								$subject_query['where'][] = 'm.subject NOT ' . (empty($settings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:exclude_phrase_' . $count . '}';
								$subject_query['where'][] = 'm.body NOT ' . (empty($settings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:exclude_phrase_' . $count . '}';
								$subject_query['params']['exclude_phrase_' . $count++] = empty($settings['search_match_words']) || $no_regexp ? '%' . strtr($phrase, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $phrase), '\\\'') . '[[:>:]]';
							}
						}

						// Nothing to search for?
						if (empty($subject_query['where']))
							continue;

						// Avoid double inner joins, such as when using several negative search terms.
						if (!empty($subject_query['inner_join']))
							$subject_query['inner_join'] = array_flip(array_flip($subject_query['inner_join']));

						$ignoreRequest = wesql::query('
							INSERT IGNORE INTO {db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics
								(' . ($createTemporary ? '' : 'id_search, ') . 'id_topic)
							SELECT ' . ($createTemporary ? '' : $_SESSION['search_cache']['id_search'] . ', ') . 't.id_topic
							FROM ' . $subject_query['from'] . (empty($subject_query['inner_join']) ? '' : '
								INNER JOIN ' . implode('
								INNER JOIN ', $subject_query['inner_join'])) . (empty($subject_query['left_join']) ? '' : '
								LEFT JOIN ' . implode('
								LEFT JOIN ', $subject_query['left_join'])) . '
							WHERE ' . implode('
								AND ', $subject_query['where']) . (empty($settings['search_max_results']) ? '' : '
							LIMIT ' . ($settings['search_max_results'] - $numSubjectResults)),
							$subject_query['params']
						);

						$numSubjectResults += wesql::affected_rows();

						if (!empty($settings['search_max_results']) && $numSubjectResults >= $settings['search_max_results'])
							break;
					}

					// Got some non-MySQL data to plonk in?
					if (!empty($inserts))
					{
						wesql::insert('',
							('{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics'),
							$createTemporary ? array('id_topic' => 'int') : array('id_search' => 'int', 'id_topic' => 'int'),
							$inserts
						);
					}

					if ($numSubjectResults !== 0)
					{
						$main_query['weights']['subject'] = 'CASE WHEN MAX(lst.id_topic) IS NULL THEN 0 ELSE 1 END';
						$main_query['left_join'][] = '{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics AS lst ON (' . ($createTemporary ? '' : 'lst.id_search = {int:id_search} AND ') . 'lst.id_topic = t.id_topic)';
						if (!$createTemporary)
							$main_query['parameters']['id_search'] = $_SESSION['search_cache']['id_search'];
					}
				}

				$indexedResults = 0;
				// We building an index?
				if (method_exists($searchAPI, 'indexedWordQuery'))
				{
					$inserts = array();
					wesql::query('
						DROP TABLE IF EXISTS {db_prefix}tmp_log_search_messages',
						array(
							'db_error_skip' => true,
						)
					);

					$createTemporary = wesql::query('
						CREATE TEMPORARY TABLE {db_prefix}tmp_log_search_messages (
							id_msg int(10) unsigned NOT NULL default {string:string_zero},
							PRIMARY KEY (id_msg)
						) ENGINE=MEMORY',
						array(
							'string_zero' => '0',
							'db_error_skip' => true,
						)
					) !== false;

					// Clear, all clear!
					if (!$createTemporary)
						wesql::query('
							DELETE FROM {db_prefix}log_search_messages
							WHERE id_search = {int:id_search}',
							array(
								'id_search' => $_SESSION['search_cache']['id_search'],
							)
						);

					foreach ($searchWords as $orIndex => $words)
					{
						// Search for this word, assuming we have some words!
						if (!empty($words['indexed_words']))
						{
							// Variables required for the search.
							$search_data = array(
								'insert_into' => ($createTemporary ? 'tmp_' : '') . 'log_search_messages',
								'no_regexp' => $no_regexp,
								'max_results' => $maxMessageResults,
								'indexed_results' => $indexedResults,
								'params' => array(
									'id_search' => !$createTemporary ? $_SESSION['search_cache']['id_search'] : 0,
									'excluded_words' => $excludedWords,
									'user_query' => !empty($userQuery) ? $userQuery : '',
									'board_query' => !empty($boardQuery) ? $boardQuery : '',
									'topic' => !empty($search_params['topic']) ? $search_params['topic'] : 0,
									'min_msg_id' => !empty($minMsgID) ? $minMsgID : 0,
									'max_msg_id' => !empty($maxMsgID) ? $maxMsgID : 0,
									'excluded_phrases' => !empty($excludedPhrases) ? $excludedPhrases : array(),
									'excluded_index_words' => !empty($excludedIndexWords) ? $excludedIndexWords : array(),
									'excluded_subject_words' => !empty($excludedSubjectWords) ? $excludedSubjectWords : array(),
								),
							);

							$ignoreRequest = $searchAPI->indexedWordQuery($words, $search_data);

							$indexedResults += wesql::affected_rows();

							if (!empty($maxMessageResults) && $indexedResults >= $maxMessageResults)
								break;
						}
					}

					// More non-MySQL stuff needed?
					if (!empty($inserts))
					{
						wesql::insert('',
							'{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_messages',
							$createTemporary ? array('id_msg' => 'int') : array('id_msg' => 'int', 'id_search' => 'int'),
							$inserts
						);
					}

					if (empty($indexedResults) && empty($numSubjectResults) && !empty($settings['search_force_index']))
					{
						$context['search_errors']['query_not_specific_enough'] = true;
						$_REQUEST['params'] = $context['params'];
						loadSource('Search');
						return Search();
					}
					elseif (!empty($indexedResults))
					{
						$main_query['inner_join'][] = '{db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_messages AS lsm ON (lsm.id_msg = m.id_msg)';
						if (!$createTemporary)
						{
							$main_query['where'][] = 'lsm.id_search = {int:id_search}';
							$main_query['parameters']['id_search'] = $_SESSION['search_cache']['id_search'];
						}
					}
				}

				// Not using an index? All conditions have to be carried over.
				else
				{
					$orWhere = array();
					$count = 0;
					foreach ($searchWords as $orIndex => $words)
					{
						$where = array();
						foreach ($words['all_words'] as $regularWord)
						{
							$where[] = 'm.body' . (in_array($regularWord, $excludedWords) ? ' NOT' : '') . (empty($settings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:all_word_body_' . $count . '}';
							if (in_array($regularWord, $excludedWords))
								$where[] = 'm.subject NOT' . (empty($settings['search_match_words']) || $no_regexp ? ' LIKE ' : ' RLIKE ') . '{string:all_word_body_' . $count . '}';
							$main_query['parameters']['all_word_body_' . $count++] = empty($settings['search_match_words']) || $no_regexp ? '%' . strtr($regularWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $regularWord), '\\\'') . '[[:>:]]';
						}
						if (!empty($where))
							$orWhere[] = count($where) > 1 ? '(' . implode(' AND ', $where) . ')' : $where[0];
					}
					if (!empty($orWhere))
						$main_query['where'][] = count($orWhere) > 1 ? '(' . implode(' OR ', $orWhere) . ')' : $orWhere[0];

					if (!empty($userQuery))
					{
						$main_query['where'][] = '{raw:user_query}';
						$main_query['parameters']['user_query'] = $userQuery;
					}
					if (!empty($search_params['topic']))
					{
						$main_query['where'][] = 'm.id_topic = {int:topic}';
						$main_query['parameters']['topic'] = $search_params['topic'];
					}
					if (!empty($minMsgID))
					{
						$main_query['where'][] = 'm.id_msg >= {int:min_msg_id}';
						$main_query['parameters']['min_msg_id'] = $minMsgID;
					}
					if (!empty($maxMsgID))
					{
						$main_query['where'][] = 'm.id_msg <= {int:max_msg_id}';
						$main_query['parameters']['max_msg_id'] = $maxMsgID;
					}
					if (!empty($boardQuery))
					{
						$main_query['where'][] = 'm.id_board {raw:board_query}';
						$main_query['parameters']['board_query'] = $boardQuery;
					}
				}

				// Did we either get some indexed results, or otherwise did not do an indexed query?
				if (!empty($indexedResults) || !method_exists($searchAPI, 'indexedWordQuery'))
				{
					$relevance = '1000 * (';
					$new_weight_total = 0;
					foreach ($main_query['weights'] as $type => $value)
					{
						$relevance .= $weight[$type] . ' * ' . $value . ' + ';
						$new_weight_total += $weight[$type];
					}
					$main_query['select']['relevance'] = substr($relevance, 0, -3) . ') / ' . $new_weight_total . ' AS relevance';

					$ignoreRequest = wesql::query('
						INSERT IGNORE INTO {db_prefix}log_search_results
							(' . implode(', ', array_keys($main_query['select'])) . ')
						SELECT
							' . implode(',
							', $main_query['select']) . '
						FROM ' . $main_query['from'] . (empty($main_query['inner_join']) ? '' : '
							INNER JOIN ' . implode('
							INNER JOIN ', $main_query['inner_join'])) . (empty($main_query['left_join']) ? '' : '
							LEFT JOIN ' . implode('
							LEFT JOIN ', $main_query['left_join'])) . (!empty($main_query['where']) ? '
						WHERE ' : '') . implode('
							AND ', $main_query['where']) . (empty($main_query['group_by']) ? '' : '
						GROUP BY ' . implode(', ', $main_query['group_by'])) . (empty($settings['search_max_results']) ? '' : '
						LIMIT ' . $settings['search_max_results']),
						$main_query['parameters']
					);

					$_SESSION['search_cache']['num_results'] = wesql::affected_rows();
				}

				// Insert subject-only matches.
				if ($_SESSION['search_cache']['num_results'] < $settings['search_max_results'] && $numSubjectResults !== 0)
				{
					$usedIDs = array_flip(empty($inserts) ? array() : array_keys($inserts));
					$ignoreRequest = wesql::query('
						INSERT IGNORE INTO {db_prefix}log_search_results
							(id_search, id_topic, relevance, id_msg, num_matches)
						SELECT
							{int:id_search},
							t.id_topic,
							1000 * (
								{int:weight_frequency} / (t.num_replies + 1) +
								{int:weight_age} * CASE WHEN t.id_first_msg < {int:min_msg} THEN 0 ELSE (t.id_first_msg - {int:min_msg}) / {int:recent_message} END +
								{int:weight_length} * CASE WHEN t.num_replies < {int:huge_topic_posts} THEN t.num_replies / {int:huge_topic_posts} ELSE 1 END +
								{int:weight_subject} +
								{int:weight_pinned} * t.is_pinned
							) / {int:weight_total} AS relevance,
							t.id_first_msg,
							1
						FROM {db_prefix}topics AS t
							INNER JOIN {db_prefix}' . ($createTemporary ? 'tmp_' : '') . 'log_search_topics AS lst ON (lst.id_topic = t.id_topic)'
						. ($createTemporary ? '' : 'WHERE lst.id_search = {int:id_search}')
						. (empty($settings['search_max_results']) ? '' : '
						LIMIT ' . ($settings['search_max_results'] - $_SESSION['search_cache']['num_results'])),
						array(
							'id_search' => $_SESSION['search_cache']['id_search'],
							'weight_age' => $weight['age'],
							'weight_frequency' => $weight['frequency'],
							'weight_length' => $weight['frequency'],
							'weight_pinned' => $weight['frequency'],
							'weight_subject' => $weight['frequency'],
							'weight_total' => $weight_total,
							'min_msg' => $minMsg,
							'recent_message' => $recentMsg,
							'huge_topic_posts' => $humungousTopicPosts,
						)
					);

					$_SESSION['search_cache']['num_results'] += wesql::affected_rows();
				}
				else
					$_SESSION['search_cache']['num_results'] = 0;
			}
		}

		// *** Retrieve the results to be shown on the page
		$participants = array();
		$request = wesql::query('
			SELECT ' . (empty($search_params['topic']) ? 'lsr.id_topic' : $search_params['topic'] . ' AS id_topic') . ', lsr.id_msg, lsr.relevance, lsr.num_matches
			FROM {db_prefix}log_search_results AS lsr' . ($search_params['sort'] == 'num_replies' ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = lsr.id_topic)' : '') . '
			WHERE lsr.id_search = {int:id_search}
			ORDER BY ' . $search_params['sort'] . ' ' . $search_params['sort_dir'] . '
			LIMIT ' . (int) $_REQUEST['start'] . ', ' . $settings['search_results_per_page'],
			array(
				'id_search' => $_SESSION['search_cache']['id_search'],
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$context['topics'][$row['id_msg']] = array(
				'relevance' => round($row['relevance'] / 10, 1) . '%',
				'num_matches' => $row['num_matches'],
				'matches' => array(),
			);
			// By default they didn't participate in the topic!
			$participants[$row['id_topic']] = false;
		}
		wesql::free_result($request);

		$num_results = $_SESSION['search_cache']['num_results'];
	}

	if (!empty($context['topics']))
	{
		$boards_can = boardsAllowedTo(array('post_reply_own', 'post_reply_any', 'lock_any', 'lock_own', 'pin_topic', 'move_any', 'move_own', 'remove_any', 'remove_own', 'merge_any'));

		$quickmod = array();
		$context['can_lock'] = in_array(0, $boards_can['lock_any']);
		$context['can_pin'] = in_array(0, $boards_can['pin_topic']);
		$context['can_move'] = in_array(0, $boards_can['move_any']);
		$context['can_remove'] = in_array(0, $boards_can['remove_any']);
		$context['can_merge'] = in_array(0, $boards_can['merge_any']);

		foreach (array('remove', 'lock', 'pin', 'move', 'merge', 'markread') as $qmod)
			if (!empty($context['can_' . $qmod]))
				$quickmod[$qmod] = $txt['quick_mod_' . $qmod];

		call_hook('select_quickmod', array(&$quickmod));
		$context['quick_moderation'] = $quickmod;

		// What messages are we using?
		$msg_list = array_keys($context['topics']);

		// Load the posters...
		$request = wesql::query('
			SELECT id_member
			FROM {db_prefix}messages
			WHERE id_member != {int:no_member}
				AND id_msg IN ({array_int:message_list})
			LIMIT ' . count($context['topics']),
			array(
				'message_list' => $msg_list,
				'no_member' => 0,
			)
		);
		$posters = array();
		while ($row = wesql::fetch_assoc($request))
			$posters[] = $row['id_member'];
		wesql::free_result($request);

		if (!empty($posters))
			loadMemberData(array_unique($posters));

		// Get the messages out for the callback - select enough that it can be made to look just like Display.
		$messages_request = wesql::query('
			SELECT
				m.id_msg, m.subject, m.poster_name, m.poster_email, m.poster_time, m.id_member,
				m.icon, m.poster_ip, m.body, m.smileys_enabled, m.modified_time, m.modified_name,
				first_m.id_msg AS first_msg, first_m.subject AS first_subject, first_m.icon AS first_icon, first_m.poster_time AS first_poster_time,
				first_mem.id_member AS first_member_id, IFNULL(first_mem.real_name, first_m.poster_name) AS first_member_name,
				last_m.id_msg AS last_msg, last_m.poster_time AS last_poster_time, last_mem.id_member AS last_member_id,
				IFNULL(last_mem.real_name, last_m.poster_name) AS last_member_name, last_m.icon AS last_icon, last_m.subject AS last_subject,
				t.id_topic, t.is_pinned, t.locked, t.id_poll, t.num_replies, t.num_views,
				b.id_board, b.name AS board_name, c.id_cat, c.name AS cat_name
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				INNER JOIN {db_prefix}messages AS first_m ON (first_m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS last_m ON (last_m.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS first_mem ON (first_mem.id_member = first_m.id_member)
				LEFT JOIN {db_prefix}members AS last_mem ON (last_mem.id_member = first_m.id_member)
			WHERE m.id_msg IN ({array_int:message_list})' . ($settings['postmod_active'] ? '
				AND m.approved = {int:is_approved}' : '') . '
			ORDER BY FIND_IN_SET(m.id_msg, {string:message_list_in_set})
			LIMIT {int:limit}',
			array(
				'message_list' => $msg_list,
				'is_approved' => 1,
				'message_list_in_set' => implode(',', $msg_list),
				'limit' => count($context['topics']),
			)
		);

		// If there are no results that means the things in the cache got deleted, so pretend we have no topics anymore.
		if (wesql::num_rows($messages_request) == 0)
			$context['topics'] = array();

		// If we want to know who participated in what then load this now.
		if (!empty($settings['enableParticipation']) && we::$is_member)
		{
			$result = wesql::query('
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topic_list})
					AND id_member = {int:current_member}
				GROUP BY id_topic
				LIMIT ' . count($participants),
				array(
					'current_member' => MID,
					'topic_list' => array_keys($participants),
				)
			);
			while ($row = wesql::fetch_assoc($result))
				$participants[$row['id_topic']] = true;
			wesql::free_result($result);
		}
	}

	// Now that we know how many results to expect we can start calculating the page numbers.
	$context['page_index'] = template_page_index('<URL>?action=search2;params=' . $context['params'], $_REQUEST['start'], $num_results, $settings['search_results_per_page'], false);

	// Consider the search complete!
	if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2)
		cache_put_data('search_start:' . (we::$is_guest ? we::$user['ip'] : MID), null, 90);

	$context['key_words'] =& $searchArray;

	loadTemplate('Search');
	wetem::load('results');
	$context['page_title'] = $txt['search_results'];
	$context['get_topics'] = 'prepareSearchContext';
	$context['can_send_pm'] = allowedTo('pm_send');
}

// Callback to return messages - saves memory.
// !!! Fix this, update it, whatever... from Display.php mainly.
function prepareSearchContext($reset = false)
{
	global $txt, $settings, $context;
	global $memberContext, $messages_request;
	global $boards_can, $participants;

	// Remember which message this is, e.g. reply #83.
	static $counter = null;
	if ($counter == null || $reset)
		$counter = $_REQUEST['start'] + 1;

	// If the query returned false, bail.
	if ($messages_request == false)
		return false;

	// Start from the beginning...
	if ($reset)
		return @wesql::data_seek($messages_request, 0);

	// Attempt to get the next message.
	$message = wesql::fetch_assoc($messages_request);
	if (!$message)
		return false;

	// Can't have an empty subject can we?
	$message['subject'] = $message['subject'] !== '' ? $message['subject'] : $txt['no_subject'];

	$message['first_subject'] = $message['first_subject'] !== '' ? $message['first_subject'] : $txt['no_subject'];
	$message['last_subject'] = $message['last_subject'] !== '' ? $message['last_subject'] : $txt['no_subject'];

	// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
	if (!loadMemberContext($message['id_member']))
	{
		// Notice this information isn't used anywhere else.... *cough guest table cough*.
		$memberContext[$message['id_member']]['name'] = $message['poster_name'];
		$memberContext[$message['id_member']]['id'] = 0;
		$memberContext[$message['id_member']]['group'] = $txt['guest_title'];
		$memberContext[$message['id_member']]['link'] = $message['poster_name'];
		$memberContext[$message['id_member']]['email'] = $message['poster_email'];
	}
	$memberContext[$message['id_member']]['ip'] = $message['poster_ip'];

	// Do the censor thang...
	censorText($message['body']);
	censorText($message['subject']);

	censorText($message['first_subject']);
	censorText($message['last_subject']);

	// Shorten this message if necessary.
	if ($context['compact'])
	{
		// Set the number of characters before and after the searched keyword.
		$charLimit = 50;

		$message['body'] = strtr($message['body'], array("\n" => ' ', '<br>' => "\n"));
		$message['body'] = parse_bbc($message['body'], 'post-preview', array('smileys' => $message['smileys_enabled'], 'cache' => $message['id_msg'], 'user' => $message['id_member']));
		$message['body'] = strip_tags(strtr($message['body'], array('</div>' => '<br>', '</li>' => '<br>')), '<br>');

		if (westr::strlen($message['body']) > $charLimit)
		{
			if (empty($context['key_words']))
				$message['body'] = westr::substr($message['body'], 0, $charLimit) . '<strong>&hellip;</strong>';
			else
			{
				$matchString = '';
				$force_partial_word = false;
				foreach ($context['key_words'] as $keyword)
				{
					$keyword = westr::entity_clean(strtr($keyword, array('\\\'' => '\'', '&' => '&amp;')));

					if (preg_match('~[\'.,/@%&;:(){}[\]_+\\\\-]$~', $keyword) != 0 || preg_match('~^[\'.,/@%&;:(){}[\]_+\\\\-]~', $keyword) != 0)
						$force_partial_word = true;
					$matchString .= strtr(preg_quote($keyword, '/'), array('\*' => '.+?')) . '|';
				}
				$matchString = substr($matchString, 0, -1);

				$message['body'] = un_htmlspecialchars(strtr($message['body'], array('&nbsp;' => ' ', '<br>' => "\n", '&#91;' => '[', '&#93;' => ']', '&#58;' => ':', '&#64;' => '@')));

				if (empty($settings['search_method']) || $force_partial_word)
					preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?|^)(' . $matchString . ')(.{0,' . $charLimit . '}[\s\W]|[^\s\W]{' . $charLimit . '})/isu', $message['body'], $matches);
				else
					preg_match_all('/([^\s\W]{' . $charLimit . '}[\s\W]|[\s\W].{0,' . $charLimit . '}?[\s\W]|^)(' . $matchString . ')([\s\W].{0,' . $charLimit . '}[\s\W]|[\s\W][^\s\W]{' . $charLimit . '})/isu', $message['body'], $matches);

				$message['body'] = '';
				foreach ($matches[0] as $index => $match)
				{
					$match = strtr(htmlspecialchars($match, ENT_QUOTES), array("\n" => '&nbsp;'));
					$message['body'] .= '<strong>&hellip;&hellip;</strong>&nbsp;' . $match . '&nbsp;<strong>&hellip;&hellip;</strong>';
				}
			}

			// Re-fix the international characters.
			$message['body'] = westr::entity_clean($message['body']);
		}
	}
	// Run BBC interpreter on the message.
	else
		$message['body'] = parse_bbc($message['body'], 'post', array('smileys' => $message['smileys_enabled'], 'cache' => $message['id_msg'], 'user' => $message['id_member']));

	// Make sure we don't end up with a practically empty message body.
	$message['body'] = preg_replace('~^(?:&nbsp;)+$~', '', $message['body']);

	// Do we have quote tag enabled?
	$quote_enabled = empty($settings['disabledBBC']) || !in_array('quote', explode(',', $settings['disabledBBC']));

	$body_highlighted = $message['body'];
	$subject_highlighted = $message['subject'];
	$started = $message['first_member_id'] == MID;
	$id_board = $message['id_board'];

	$output = array_merge($context['topics'][$message['id_msg']], array(
		'id' => $message['id_topic'],
		'is_pinned' => !empty($message['is_pinned']),
		'is_locked' => !empty($message['locked']),
		'is_poll' => $message['id_poll'] > 0,
		'posted_in' => !empty($participants[$message['id_topic']]),
		'views' => $message['num_views'],
		'replies' => $message['num_replies'],
		'can_reply' => in_array($id_board, $boards_can['post_reply_any']) || in_array(0, $boards_can['post_reply_any']),
		'can_quote' => (in_array($id_board, $boards_can['post_reply_any']) || in_array(0, $boards_can['post_reply_any'])) && $quote_enabled,
		'board' => array(
			'id' => $id_board,
			'name' => $message['board_name'],
			'href' => '<URL>?board=' . $id_board . '.0',
			'link' => '<a href="<URL>?board=' . $id_board . '.0">' . $message['board_name'] . '</a>'
		),
		'quick_mod' => array(
			'lock' => in_array(0, $boards_can['lock_any']) || in_array($id_board, $boards_can['lock_any']) || ($started && (in_array(0, $boards_can['lock_own']) || in_array($id_board, $boards_can['lock_own']))),
			'pin' => (in_array(0, $boards_can['pin_topic']) || in_array($id_board, $boards_can['pin_topic'])),
			'move' => in_array(0, $boards_can['move_any']) || in_array($id_board, $boards_can['move_any']) || ($started && (in_array(0, $boards_can['move_own']) || in_array($id_board, $boards_can['move_own']))),
			'remove' => in_array(0, $boards_can['remove_any']) || in_array($id_board, $boards_can['remove_any']) || ($started && (in_array(0, $boards_can['remove_own']) || in_array($id_board, $boards_can['remove_own']))),
		),
	));

	$context['can_lock'] |= $output['quick_mod']['lock'];
	$context['can_pin'] |= $output['quick_mod']['pin'];
	$context['can_move'] |= $output['quick_mod']['move'];
	$context['can_remove'] |= $output['quick_mod']['remove'];
	$context['can_merge'] |= in_array($id_board, $boards_can['merge_any']);

	// If we've found a message we can move, and we don't already have it, load the destinations.
	if (!isset($context['move_to_boards']) && $context['can_move'])
	{
		loadSource('Subs-MessageIndex');
		$boardListOptions = array(
			'use_permissions' => true,
			'not_redirection' => true,
			'selected_board' => empty($_SESSION['move_to_topic']) ? null : $_SESSION['move_to_topic'],
		);
		$context['move_to_boards'] = getBoardList($boardListOptions);
	}

	foreach ($context['key_words'] as $query)
	{
		// Fix the international characters in the keyword too.
		$query = strtr(westr::htmlspecialchars($query), array('\\\'' => '\''));

		$body_highlighted = preg_replace_callback('~((<[^>]*)|' . preg_quote(strtr($query, array('\'' => '&#039;')), '~') . ')~iu', function ($match) { return !empty($match[2]) && $match[2] == $match[1] ? stripslashes($match[1]) : '<mark>' . $match[1] . '</mark>'; }, $body_highlighted);
		$subject_highlighted = preg_replace('~(' . preg_quote($query, '~') . ')~iu', '<mark>$1</mark>', $subject_highlighted);
	}

	$output['matches'][] = array(
		'id' => $message['id_msg'],
		'attachment' => loadAttachmentContext($message['id_msg']),
		'alternate' => $counter % 2,
		'member' => &$memberContext[$message['id_member']],
		'icon' => $message['icon'],
		'icon_url' => ASSETS . '/post/' . $message['icon'] . '.gif',
		'subject' => $message['subject'],
		'subject_highlighted' => $subject_highlighted,
		'on_time' => on_timeformat($message['poster_time']),
		'timestamp' => $message['poster_time'],
		'counter' => $counter,
		'modified' => array(
			'on_time' => on_timeformat($message['modified_time']),
			'timestamp' => $message['modified_time'],
			'name' => $message['modified_name']
		),
		'body' => $message['body'],
		'body_highlighted' => $body_highlighted,
		'start' => 'msg' . $message['id_msg']
	);
	$counter++;

	return $output;
}

function wedge_ranged_implode($separator, $arr)
{
	$temp_arr = array();
	sort($arr);
	$last_val = $last_real = ~PHP_INT_MAX;
	foreach ($arr as $val)
	{
		if ($val != $last_val + 1)
			$last_real = $val;
		$temp_arr[$last_real] = $val;
		$last_val = $val;
	}
	$str = '';
	foreach ($temp_arr as $first => $last)
		$str .= $first == $last ? $first . ',' : $first . '-' . $last . ',';
	return substr($str, 0, -1);
}

function wedge_ranged_explode($separator, $string)
{
	return empty($string) ? array() : explode($separator, preg_replace_callback('~(\d+)-(\d+)~', function ($m) { return implode(',', range($m[1], $m[2])); }, $string));
}
