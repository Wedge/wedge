<?php
/**
 * Provides information and support functions for custom index searching, for the Search framework.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*
	int searchSort(string $wordA, string $wordB)
		- callback function for usort used to sort the fulltext results.
		- the order of sorting is: large words, small words, large words that
		  are excluded from the search, small words that are excluded.
*/

class custom_search
{
	// Is it supported?
	public $is_supported = true;

	protected $indexSettings = array();
	// What words are banned?
	protected $bannedWords = array();
	// What is the minimum word length?
	protected $min_word_length = null;

	public function __construct()
	{
		global $settings;

		if (empty($settings['search_custom_index_config']))
			return;

		$this->indexSettings = unserialize($settings['search_custom_index_config']);

		$this->bannedWords = empty($settings['search_stopwords']) ? array() : explode(',', $settings['search_stopwords']);
		$this->min_word_length = $this->indexSettings['bytes_per_word'];
	}

	// If the settings don't exist we can't continue.
	public function isValid()
	{
		return !empty($this->indexSettings);
	}

	public function getInfo()
	{
		global $txt, $settings, $db_prefix;

		if (!empty($settings['search_custom_index_resume']) && empty($settings['search_custom_index_config']))
			$state = 'partial';
		elseif (!empty($settings['search_custom_index_config']))
			$state = 'complete';
		else
			$state = 'none';

		if ($state != 'none')
		{
			// Now check the custom index table, if it exists at all.
			if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) !== 0)
				$request = wesql::query('
					SHOW TABLE STATUS
					FROM {string:database_name}
					LIKE {string:table_name}',
					array(
						'database_name' => '`' . strtr($match[1], array('`' => '')) . '`',
						'table_name' => str_replace('_', '\_', $match[2]) . 'log_search_words',
					)
				);
			else
				$request = wesql::query('
					SHOW TABLE STATUS
					LIKE {string:table_name}',
					array(
						'table_name' => str_replace('_', '\_', $db_prefix) . 'log_search_words',
					)
				);

			if ($request !== false && wesql::num_rows($request) == 1)
			{
				// Only do this if the user has permission to execute this query.
				$row = wesql::fetch_assoc($request);
				$size = $row['Data_length'] + $row['Index_length'];
				wesql::free_result($request);
			}
		}

		return array(
			'filename' => basename(__FILE__),
			'setting_index' => 'standard',
			'has_template' => true,
			'label' => $txt['search_index_custom'],
			'desc' => '',
			'state' => $state,
			'size' => isset($size) ? $size : 0,
			'can_create' => true,
		);
	}

	// This function compares the length of two strings plus a little.
	public function searchSort($a, $b)
	{
		global $excludedWords;

		$x = strlen($a) - (in_array($a, $excludedWords) ? 1000 : 0);
		$y = strlen($b) - (in_array($b, $excludedWords) ? 1000 : 0);

		return $y < $x ? 1 : ($y > $x ? -1 : 0);
	}

	// Do we have to do some work with the words we are searching for to prepare them?
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded)
	{
		global $settings;

		$subwords = text2words($word, $this->min_word_length, true);

		if (empty($settings['search_force_index']))
			$wordsSearch['words'][] = $word;

		// Excluded phrases don't benefit from being split into subwords.
		if (count($subwords) > 1 && $isExcluded)
			continue;
		else
		{
			foreach ($subwords as $subword)
			{
				if (westr::strlen($subword) >= $this->min_word_length && !in_array($subword, $this->bannedWords))
				{
					$wordsSearch['indexed_words'][] = $subword;
					if ($isExcluded)
						$wordsExclude[] = $subword;
				}
			}
		}
	}

	// Search for indexed words.
	public function indexedWordQuery($words, $search_data)
	{
		global $settings;

		$query_select = array(
			'id_msg' => 'm.id_msg',
		);
		$query_inner_join = array();
		$query_left_join = array();
		$query_where = array();
		$query_params = $search_data['params'];

		if ($query_params['id_search'])
			$query_select['id_search'] = '{int:id_search}';

		$count = 0;
		foreach ($words['words'] as $regularWord)
		{
			$query_where[] = 'm.body' . (in_array($regularWord, $query_params['excluded_words']) ? ' NOT' : '') . (empty($settings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : ' RLIKE ') . '{string:complex_body_' . $count . '}';
			$query_params['complex_body_' . $count++] = empty($settings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($regularWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $regularWord), '\\\'') . '[[:>:]]';
		}

		if ($query_params['user_query'])
			$query_where[] = '{raw:user_query}';
		if ($query_params['board_query'])
			$query_where[] = 'm.id_board {raw:board_query}';

		if ($query_params['topic'])
			$query_where[] = 'm.id_topic = {int:topic}';
		if ($query_params['min_msg_id'])
			$query_where[] = 'm.id_msg >= {int:min_msg_id}';
		if ($query_params['max_msg_id'])
			$query_where[] = 'm.id_msg <= {int:max_msg_id}';

		$count = 0;
		if (!empty($query_params['excluded_phrases']) && empty($settings['search_force_index']))
			foreach ($query_params['excluded_phrases'] as $phrase)
			{
				$query_where[] = 'subject NOT ' . (empty($settings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : ' RLIKE ') . '{string:exclude_subject_phrase_' . $count . '}';
				$query_params['exclude_subject_phrase_' . $count++] = empty($settings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($phrase, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $phrase), '\\\'') . '[[:>:]]';
			}
		$count = 0;
		if (!empty($query_params['excluded_subject_words']) && empty($settings['search_force_index']))
			foreach ($query_params['excluded_subject_words'] as $excludedWord)
			{
				$query_where[] = 'subject NOT ' . (empty($settings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : ' RLIKE ') . '{string:exclude_subject_words_' . $count . '}';
				$query_params['exclude_subject_words_' . $count++] = empty($settings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($excludedWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $excludedWord), '\\\'') . '[[:>:]]';
			}

		$numTables = 0;
		$prev_join = 0;
		foreach ($words['indexed_words'] as $indexedWord)
		{
			$numTables++;
			if (in_array($indexedWord, $query_params['excluded_index_words']))
			{
				$query_left_join[] = '{db_prefix}log_search_words AS lsw' . $numTables . ' ON (lsw' . $numTables . '.id_word = ' . $indexedWord . ' AND lsw' . $numTables . '.id_msg = m.id_msg)';
				$query_where[] = '(lsw' . $numTables . '.id_word IS NULL)';
			}
			else
			{
				$query_inner_join[] = '{db_prefix}log_search_words AS lsw' . $numTables . ' ON (lsw' . $numTables . '.id_msg = ' . ($prev_join === 0 ? 'm' : 'lsw' . $prev_join) . '.id_msg)';
				$query_where[] = 'lsw' . $numTables . '.id_word = ' . $indexedWord;
				$prev_join = $numTables;
			}
		}

		$ignoreRequest = wesql::query('
			INSERT IGNORE INTO {db_prefix}' . $search_data['insert_into'] . '
				(' . implode(', ', array_keys($query_select)) . ')
			SELECT ' . implode(', ', $query_select) . '
			FROM {db_prefix}messages AS m' . (empty($query_inner_join) ? '' : '
				INNER JOIN ' . implode('
				INNER JOIN ', $query_inner_join)) . (empty($query_left_join) ? '' : '
				LEFT JOIN ' . implode('
				LEFT JOIN ', $query_left_join)) . '
			WHERE ' . implode('
				AND ', $query_where) . (empty($search_data['max_results']) ? '' : '
			LIMIT ' . ($search_data['max_results'] - $search_data['indexed_results'])),
			$query_params
		);

		return $ignoreRequest;
	}

	public function createIndex($params = array())
	{
	}

	public function dropIndex($params = array())
	{
		global $db_prefix;

		loadSource('Class-DBPackages');
		$tables = wedbPackages::list_tables(false, $db_prefix . 'log_search_words');
		if (!empty($tables))
		{
			wesql::query('
				DROP TABLE {db_prefix}log_search_words',
				array(
				)
			);
		}

		updateSettings(array(
			'search_custom_index_config' => '',
			'search_custom_index_resume' => '',
		));
	}

	public function putDocuments($doc_type, $documents, $params = array())
	{
		// !!! This will, in time, need to do something more useful than merely 'post' indexing.
		if ($doc_type != 'post')
			return false;

		$inserts = array();
		foreach ($documents as $doc_id => $doc_content)
			foreach (text2words($doc_content, $this->indexSettings['bytes_per_word'], true) as $word)
				$inserts[] = array($word, $doc_id);

		if (!empty($inserts))
			wesql::insert('ignore',
				'{db_prefix}log_search_words',
				array('id_word' => 'int', 'id_msg' => 'int'),
				$inserts
			);
	}

	public function updateDocument($doc_type, $doc_id, $old_document, $new_document, $params = array())
	{
		global $settings;

		$stopwords = empty($settings['search_stopwords']) ? array() : explode(',', $settings['search_stopwords']);
		$old_index = text2words($old_document, $this->indexSettings['bytes_per_word'], true);
		$new_index = text2words($new_document, $this->indexSettings['bytes_per_word'], true);

		// Calculate the words to be added and removed from the index.
		$removed_words = array_diff(array_diff($old_index, $new_index), $stopwords);
		$inserted_words = array_diff(array_diff($new_index, $old_index), $stopwords);
		// Delete the removed words AND the added ones to avoid key constraints.
		if (!empty($removed_words))
		{
			$removed_words = array_merge($removed_words, $inserted_words);
			wesql::query('
				DELETE FROM {db_prefix}log_search_words
				WHERE id_msg = {int:id_msg}
					AND id_word IN ({array_int:removed_words})',
				array(
					'removed_words' => $removed_words,
					'id_msg' => $doc_id,
				)
			);
		}

		// Add the new words to be indexed.
		if (!empty($inserted_words))
		{
			$inserts = array();
			foreach ($inserted_words as $word)
				$inserts[] = array($word, $doc_id);
			wesql::insert('',
				'{db_prefix}log_search_words',
				array('id_word' => 'string', 'id_msg' => 'int'),
				$inserts
			);
		}
	}

	public function removeDocuments($doc_type, $documents, $params = array())
	{
		// !!! This will, in time, need to do something more useful than merely 'post' indexing.
		if ($doc_type != 'post')
			return false;

		$messages = array();
		$words = array();
		foreach ($documents as $id => $body)
		{
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();

			$words = array_merge($words, text2words($body, $this->indexSettings['bytes_per_word'], true));
			$messages[] = $id;
		}

		$words = array_unique($words);

		if (!empty($words) && !empty($messages))
			wesql::query('
				DELETE FROM {db_prefix}log_search_words
				WHERE id_word IN ({array_int:word_list})
					AND id_msg IN ({array_int:message_list})',
				array(
					'word_list' => $words,
					'message_list' => $messages,
				)
			);
	}
}
