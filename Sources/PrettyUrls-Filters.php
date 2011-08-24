<?php
/**
 * Wedge
 *
 * Filters URLs for pretty formatting purposes.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

/*
	Pretty URLs - custom Wedge version.
	Distributed under the New BSD license.
	http://prettyurls.googlecode.com/svn/trunk/LICENCE

	Original developer:						Dannii
	Subdomains and current development:		Nao

	None of this code was written by anyone else, specifically
	not anyone associated with Wedge. JUST SO WE'RE CLEAR! >8D
*/

if (!defined('WEDGE'))
	die('Hacking attempt...');

// Build the table of pretty topic URLs
// This function used to do a lot more, it no longer does, but I still kept the same name.
function pretty_synchronize_topic_urls()
{
	global $modSettings;

	// Clear the URLs cache
	wesql::query('
		TRUNCATE TABLE {db_prefix}pretty_topic_urls',
		array()
	);

	// Get the current database pretty URLs and other stuff
	$query = wesql::query('
		SELECT t.id_topic, t.id_board, m.subject
		FROM {db_prefix}topics AS t
		INNER JOIN {db_prefix}messages AS m ON m.id_msg = t.id_first_msg',
		array()
	);

	$topicData = array();
	$tablePretty = array();

	// Fill the $topicData array
	while ($row = wesql::fetch_assoc($query))
		$topicData[] = array(
			'id_topic' => $row['id_topic'],
			'id_board' => $row['id_board'],
			'subject' => $row['subject']
		);
	wesql::free_result($query);

	loadSource('Subs-PrettyUrls');

	// Go through the $topicData array and fix anything that needs fixing
	foreach ($topicData as $row)
	{
		// A topic in the recycle board deserves only a blank URL
		$pretty_text = $modSettings['recycle_enable'] && $row['id_board'] == $modSettings['recycle_board'] ? '' : trimpercent(substr(pretty_generate_url($row['subject']), 0, 80));
		// Can't be empty, can't be a number and can't be the same as another
		if ($pretty_text == '' || is_numeric($pretty_text))
		{
			// Add suffix '-tID_TOPIC' to the pretty url
			$pretty_text = trimpercent(substr($pretty_text, 0, 70)) . ($pretty_text != '' ? '-t' : 't') . $row['id_topic'];
			$pretty_text = preg_replace('/-+/', '-', $pretty_text);
		}

		// Update the arrays
		$tablePretty[] = '(' . (int) $row['id_topic'] . ", '" . $pretty_text . "')";
	}

	// Update the database
	if (count($tablePretty) > 0)
	{
		wesql::query('
			REPLACE INTO {db_prefix}pretty_topic_urls
				(id_topic, pretty_url)
			VALUES ' . implode(', ', $tablePretty),
			array()
		);
	}
}

// Filter miscellaneous action urls
function pretty_filter_actions($urls)
{
	global $boardurl, $scripturl;

/*
	$pattern = array(
		'~.*[?;&]action=media;sa=media;in=([0-9]+);(thumba?|preview)(.*)~S',
		'~.*[?;&]action=media;sa=(album|item|media);in=([0-9]+)(.*)~S',
		'~.*[?;&]action=(media|pm)(.*)~S',
		// '~.*[?;&]action=helpdesk(.*)~S', // This is just an example for a custom action and a different subdomain name...
	);
	$replacement = array(
		'http://media.wedgeo.com/$2/$1/?$3',
		'http://media.wedgeo.com/$1/$2/?$3',
		'http://$1.wedgeo.com/?$2',
		// 'http://tracker.wedgeo.com/?$1', // See? That's easy.
	);

	// A failed strpos is about twice as fast as a failed preg_match. Of course,
	// the optimization depends on how many action-type layouts can be found on the page...
	foreach ($urls as &$url)
		if (!isset($url['replacement']) && strpos($url['url'], 'action=') !== false && preg_match('~action=(?:media|pm|helpdesk)~', $url['url'])) // |admin
			$url['replacement'] = preg_replace($pattern, $replacement, $url['url']);
	return $urls;
*/

	// A much simpler version that accounts for all actions...
	$pattern = '~(.*)action=([^;]+)~S';
	$replacement = $boardurl . '/do/$2/$1';
	foreach ($urls as &$url)
		if (!isset($url['replacement']) && strpos($url['url'], 'action=') !== false && preg_match($pattern, $url['url']))
			$url['replacement'] = preg_replace($pattern, $replacement, $url['url']);
	return $urls;
}

// Filter topic urls
function pretty_filter_topics($urls)
{
	global $boardurl, $scripturl, $modSettings, $context;

	$pattern = '~(.*[?;&])topic=([.a-zA-Z0-9$%]+)(.*)~S';
	$query_data = array();
	foreach ($urls as &$url)
	{
		// Get the topic data ready to query the database with
		if (!isset($url['replacement']) && strpos($url['url'], 'topic=') !== false && preg_match($pattern, $url['url'], $matches))
		{
			if (strpos($matches[2], '.') !== false)
				list ($url['topic_id'], $url['start']) = explode('.', $matches[2]);
			else
			{
				$url['topic_id'] = $matches[2];
				$url['start'] = '0';
			}
			$url['topic_id'] = (int) $url['topic_id'];
			$url['match1'] = $matches[1];
			$url['match3'] = $matches[3];
			$query_data[] = $url['topic_id'];
		}
	}

	// Query the database with these topic IDs
	if (count($query_data) != 0)
	{
		// Look for existing topic URLs
		$query_data = array_keys(array_flip($query_data));
		$topicData = array();
		$unpretty_topics = array();

		$query = wesql::query('
			SELECT t.id_topic, t.id_board, p.pretty_url, b.url
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}pretty_topic_urls AS p ON (t.id_topic = p.id_topic)
			WHERE t.id_topic IN ({array_int:topic_ids})',
			array('topic_ids' => $query_data));

		while ($row = wesql::fetch_assoc($query))
			if (isset($row['pretty_url']))
				$topicData[$row['id_topic']] = array(
					'pretty_board' => !empty($row['url']) ? 'http://' . $row['url'] : $boardurl,
					'pretty_url' => $row['pretty_url'],
				);
			else
				$unpretty_topics[] = $row['id_topic'];
		wesql::free_result($query);

		// Generate new topic URLs if required
		if (count($unpretty_topics) != 0)
		{
			loadSource('Subs-PrettyUrls');

			// Get the topic subjects
			$new_topics = array();
			$new_urls = array();
			$query_check = array();
			$existing_urls = array();
			$add_new = array();

			$query = wesql::query('
				SELECT t.id_topic, t.id_board, m.subject, b.url
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON m.id_msg = t.id_first_msg
					INNER JOIN {db_prefix}boards AS b ON b.id_board = t.id_board
				WHERE t.id_topic IN ({array_int:topic_ids})',
				array('topic_ids' => $unpretty_topics));

			while ($row = wesql::fetch_assoc($query))
				$new_topics[] = array(
					'id_topic' => $row['id_topic'],
					'id_board' => $row['id_board'],
					'board_url' => $row['url'],
					'subject' => $row['subject'],
				);
			wesql::free_result($query);

			// Generate URLs for each new topic
			foreach ($new_topics as $row)
			{
				$pretty_text = trimpercent(substr(pretty_generate_url($row['subject']), 0, 80));
				// A topic in the recycle board doesn't deserve a proper URL
				if (($modSettings['recycle_enable'] && $row['id_board'] == $modSettings['recycle_board']) || $pretty_text == '')
					// Use 'tID_TOPIC' as a pretty url
					$pretty_text = 't' . $row['id_topic'];
				// No duplicates and no numerical URLs - that would just confuse everyone!
				if (/*in_array($pretty_text, $new_urls) || CYNAMOD */ is_numeric($pretty_text))
				{
					// Add suffix '-tID_TOPIC' to the pretty url
					$pretty_text = trimpercent(substr($pretty_text, 0, 70)) . '-t' . $row['id_topic'];
					$pretty_text = preg_replace('/-+/', '-', $pretty_text);
				}
				$query_check[] = $pretty_text;
				$new_urls[$row['id_topic']] = $pretty_text;
			}

			// Find any duplicates of existing URLs
			$query = wesql::query('
				SELECT pretty_url
				FROM {db_prefix}pretty_topic_urls
				WHERE pretty_url IN ({array_string:new_urls})',
				array('new_urls' => $query_check));
			while ($row = wesql::fetch_assoc($query))
				$existing_urls[] = $row['pretty_url'];
			wesql::free_result($query);

			// Finalise the new URLs ...
			foreach ($new_topics as $row)
			{
				$pretty_text = $new_urls[$row['id_topic']];
				// Check if the new URL is already in use
				/* CYNAMOD
				if (in_array($pretty_text, $existing_urls))
				{
					$pretty_text = trimpercent(substr($pretty_text, 0, 70)) . '-t' . $row['id_topic'];
					$pretty_text = preg_replace('/-+/', '-', $pretty_text);
				}
				*/
				$add_new[] = array($row['id_topic'], $pretty_text);
				// Add to the original array of topic URLs
				$topicData[$row['id_topic']] = array(
					'pretty_board' => 'http://' . (!empty($row['board_url']) ? $row['board_url'] : $row['id_board']),
					'pretty_url' => $pretty_text,
				);
			}
			// ... and add them to the database!
			wesql::insert('',
				'{db_prefix}pretty_topic_urls',
				array('id_topic' => 'int', 'pretty_url' => 'string'),
				$add_new,
				array());
		}

		// Build the replacement URLs
		foreach ($urls as &$url)
			if (isset($url['topic_id']) && isset($topicData[$url['topic_id']]))
			{
				$start = ($url['start'] != '0' && $url['start'] != 'msg0') || is_numeric($topicData[$url['topic_id']]['pretty_url']) ? $url['start'] . '/' : '';
				$url['replacement'] = $topicData[$url['topic_id']]['pretty_board'] . '/' . $url['topic_id'] . '/' . $topicData[$url['topic_id']]['pretty_url'] . '/' . $start . $url['match1'] . $url['match3'];
			}
	}
	return $urls;
}

// Filter board urls
function pretty_filter_boards($urls)
{
	global $boardurl, $scripturl, $modSettings, $context;

	$pattern = '~(.*[?;&])board=([.0-9$%]+)(?:;(cat|tag)=([^;&]+))?(?:;month=(\d{6,8}))?(.*)~S';
	$bo_list = array();
	foreach ($urls as &$url)
	{
		// Split out the board URLs and replace them
		if (!isset($url['replacement']) && strpos($url['url'], 'board=') !== false && preg_match($pattern, $url['url'], $matches))
		{
			if (strpos($matches[2], '.') !== false)
				list ($board_id, $start) = explode('.', $matches[2]);
			else
			{
				$board_id = $matches[2];
				$start = '0';
			}
			$board_id = (int) $board_id;
			$bo_list[] = $board_id;
			$ere = $matches[5];
			$url['board_id'] = $board_id;
			$url['start'] = $start != '0' ? 'p' . $start . '/' : '';
			$url['match1'] = $matches[1];
			$url['cattag'] = !empty($matches[3]) ? $matches[3] . '/' . $matches[4] . '/' : '';
			$url['epoch'] = !empty($ere) ? substr($ere, 0, 4) . '/' : '';
			$url['epoch'] .= substr($ere, 4, 2) != '' ? substr($ere, 4, 2) . '/' : '';
			$url['epoch'] .= substr($ere, 6, 2) != '' ? substr($ere, 6, 2) . '/' : '';
			$url['match6'] = $matches[6];
		}
	}

	$url_list = array();
	if (count($bo_list) > 0)
	{
		$query = wesql::query('
			SELECT id_board, url
			FROM {db_prefix}boards
			WHERE id_board IN (' . implode(', ', array_keys(array_flip($bo_list))) . ')');
		while ($row = wesql::fetch_assoc($query))
			$url_list[$row['id_board']] = $row['url'];
		wesql::free_result($query);

		foreach ($urls as &$url)
			if (!isset($url['replacement']) && isset($url['board_id']))
			{
				$board_id = $url['board_id'];
				$url['replacement'] = (!empty($url_list[$board_id]) ? 'http://' . $url_list[$board_id] : $boardurl) . '/' . $url['cattag'] . $url['epoch'] . $url['start'] . $url['match1'] . $url['match6'];
			}
	}

	return $urls;
}

// Filter profiles
function pretty_filter_profiles($urls)
{
	global $boardurl, $scripturl, $modSettings;

	$pattern = '~(.*)action=profile(;u=([0-9]+))?(.*)~S';
	$query_data = array();
	foreach ($urls as &$url)
	{
		// Get the profile data ready to query the database with
		if (!isset($url['replacement']) && strpos($url['url'], 'action=profile') !== false && preg_match($pattern, $url['url'], $matches))
		{
			$url['this_is_me'] = empty($matches[2]);
			$url['profile_id'] = (int) $matches[3];
			$url['match1'] = $matches[1];
			$url['match3'] = $matches[4];
			if ($url['profile_id'] > 0)
				$query_data[$url['profile_id']] = true;
			else
				$url['replacement'] = $boardurl . '/~' . ($url['this_is_me'] ? '/' : 'guest/') . $url['match1'] . $url['match3'];
		}
	}

	// Query the database with these profile IDs
	if (count($query_data) != 0)
	{
		$memberNames = array();
		$query = wesql::query('
			SELECT id_member, member_name
			FROM {db_prefix}members
			WHERE id_member IN (' . implode(', ', array_keys($query_data)) . ')');
		while ($row = wesql::fetch_assoc($query))
		{
			$memberNames[$row['id_member']] = urlencode($row['member_name']);
			if (strpos($memberNames[$row['id_member']], '%2B') !== false) // Stupid mod_rewrite bug!
				$memberNames[$row['id_member']] = urlencode(str_replace('+', ' ', $memberNames[$row['id_member']]));
			// !!! Try this!!!
			//	$memberNames[$row['id_member']] = urlencode(stripslashes(str_replace('+', ' ', $memberNames[$row['id_member']])));
		}
		wesql::free_result($query);

		// Build the replacement URLs
		foreach ($urls as &$url)
			if (isset($url['profile_id']))
				$url['replacement'] = $boardurl . '/~' . (!empty($memberNames[$url['profile_id']]) ? $memberNames[$url['profile_id']] . '/' : ($url['this_is_me'] ? '/' : 'guest/')) . $url['match1'] . $url['match3'];
	}
	return $urls;
}

?>