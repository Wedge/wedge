<?php
/**
 * Wedge
 *
 * Provides information and support functions for custom index searching, for the Search framework.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
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
		global $modSettings;

		if (empty($modSettings['search_custom_index_config']))
			return;

		$this->indexSettings = unserialize($modSettings['search_custom_index_config']);

		$this->bannedWords = empty($modSettings['search_stopwords']) ? array() : explode(',', $modSettings['search_stopwords']);
		$this->min_word_length = $this->indexSettings['bytes_per_word'];
	}

	// Check whether the search can be performed by this API.
	public function supportsMethod($methodName, $query_params = null)
	{
		switch ($methodName)
		{
			case 'isValid':
			case 'searchSort':
			case 'prepareIndexes':
			case 'indexedWordQuery':
				return true;
			break;

			default:

				// All other methods, too bad dunno you.
				return false;
			return;
		}
	}

	// If the settings don't exist we can't continue.
	public function isValid()
	{
		global $modSettings;

		return !empty($modSettings['search_custom_index_config']);
	}

	// This function compares the length of two strings plus a little.
	public function searchSort($a, $b)
	{
		global $modSettings, $excludedWords;

		$x = strlen($a) - (in_array($a, $excludedWords) ? 1000 : 0);
		$y = strlen($b) - (in_array($b, $excludedWords) ? 1000 : 0);

		return $y < $x ? 1 : ($y > $x ? -1 : 0);
	}

	// Do we have to do some work with the words we are searching for to prepare them?
	public function prepareIndexes($word, &$wordsSearch, &$wordsExclude, $isExcluded)
	{
		global $modSettings;

		$subwords = text2words($word, $this->min_word_length, true);

		if (empty($modSettings['search_force_index']))
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
		global $modSettings;

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
			$query_where[] = 'm.body' . (in_array($regularWord, $query_params['excluded_words']) ? ' NOT' : '') . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : ' RLIKE ') . '{string:complex_body_' . $count . '}';
			$query_params['complex_body_' . $count++] = empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($regularWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $regularWord), '\\\'') . '[[:>:]]';
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
		if (!empty($query_params['excluded_phrases']) && empty($modSettings['search_force_index']))
			foreach ($query_params['excluded_phrases'] as $phrase)
			{
				$query_where[] = 'subject NOT ' . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : ' RLIKE ') . '{string:exclude_subject_phrase_' . $count . '}';
				$query_params['exclude_subject_phrase_' . $count++] = empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($phrase, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $phrase), '\\\'') . '[[:>:]]';
			}
		$count = 0;
		if (!empty($query_params['excluded_subject_words']) && empty($modSettings['search_force_index']))
			foreach ($query_params['excluded_subject_words'] as $excludedWord)
			{
				$query_where[] = 'subject NOT ' . (empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? ' LIKE ' : ' RLIKE ') . '{string:exclude_subject_words_' . $count . '}';
				$query_params['exclude_subject_words_' . $count++] = empty($modSettings['search_match_words']) || $search_data['no_regexp'] ? '%' . strtr($excludedWord, array('_' => '\\_', '%' => '\\%')) . '%' : '[[:<:]]' . addcslashes(preg_replace(array('/([\[\]$.+*?|{}()])/'), array('[$1]'), $excludedWord), '\\\'') . '[[:>:]]';
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
}

?>