<?php
/**
 * Wedge
 *
 * This file contains the code necessary to display interesting data feeds, normally XML.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

define('WEDGE_NO_LOG', 1);

/*	void Feed()
		- is called to output xml information.
		- can be passed four subactions which decide what is output: 'recent'
		  for recent posts, 'news' for news topics, 'members' for recently
		  registered members, and 'profile' for a member's profile.
		- To display a member's profile, a user id has to be given. (;u=1)
		- uses the Stats language file.
		- outputs an Atom feed, unless the 'type' get parameter
		  is set to 'rss' or 'rss2'.
		- does not use any templates, sub templates, or template layers.
		- is accessed via ?action=feed.

	void dumpTags(array data, int indentation, string tag = use_array,
			string format)
		- formats data retrieved in other functions into xml format.
		- additionally formats data based on the specific format passed.
		- the data parameter is the array to output as xml data.
		- indentation is the amount of indentation to use.
		- if a tag is specified, it will be used instead of the keys of data.
		- this function is recursively called to handle sub arrays of data.

	array getXmlMembers(string format)
		- is called to retrieve list of members from database.
		- the array will be generated to match the format.
		- returns array of data.

	array getXmlNews(string format)
		- is called to retrieve news topics from database.
		- the array will be generated to match the format.
		- returns array of topics.

	array getXmlRecent(string format)
		- is called to retrieve list of recent topics.
		- the array will be generated to match the format.
		- returns an array of recent posts.

	array getXmlProfile(string format)
		- is called to retrieve profile information for member into array.
		- the array will be generated to match the format.
		- returns an array of data.
*/

// Show an xml file representing recent information or a profile.
function Feed()
{
	global $topic, $board, $board_info, $context, $scripturl, $txt;
	global $modSettings, $query_this, $user_info, $domain;

	// If it's not enabled, die.
	if (empty($modSettings['xmlnews_enable']))
		obExit(false);

	loadLanguage('Stats');

	// Default to latest 5. No more than 255, please. Why 255, I don't know. Because it sounds geeky?
	$_GET['limit'] = empty($_GET['limit']) || (int) $_GET['limit'] < 1 ? 5 : min((int) $_GET['limit'], 255);

	$query_this = 1;
	$context['optimize_msg'] = array(
		'highest' => 'm.id_msg <= b.id_last_msg',
	);

	// Handle the cases where a topic, board, boards, or category are asked for.
	if (!empty($_REQUEST['c']) && empty($board))
	{
		$_REQUEST['c'] = explode(',', $_REQUEST['c']);
		foreach ($_REQUEST['c'] as $i => $c)
			$_REQUEST['c'][$i] = (int) $c;

		if (count($_REQUEST['c']) == 1)
		{
			$request = wesql::query('
				SELECT name
				FROM {db_prefix}categories
				WHERE id_cat = {int:current_category}',
				array(
					'current_category' => (int) $_REQUEST['c'][0],
				)
			);
			list ($feed_title) = wesql::fetch_row($request);
			wesql::free_result($request);

			$feed_title = ' - ' . strip_tags($feed_title);
		}

		$request = wesql::query('
			SELECT b.id_board, b.num_posts
			FROM {db_prefix}boards AS b
			WHERE b.id_cat IN ({array_int:current_category_list})
				AND {query_see_board}',
			array(
				'current_category_list' => $_REQUEST['c'],
			)
		);
		$total_cat_posts = 0;
		$boards = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$boards[] = $row['id_board'];
			$total_cat_posts += $row['num_posts'];
		}
		wesql::free_result($request);

		if (!empty($boards))
			$query_this = 'b.id_board IN (' . implode(', ', $boards) . ')';

		// Try to limit the number of messages we look through.
		if ($total_cat_posts > 100 && $total_cat_posts > $modSettings['totalMessages'] / 15)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 400 - $_GET['limit'] * 5);
	}
	elseif (!empty($_REQUEST['boards']))
	{
		$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
		foreach ($_REQUEST['boards'] as $i => $b)
			$_REQUEST['boards'][$i] = (int) $b;

		$request = wesql::query('
			SELECT b.id_board, b.num_posts, b.name
			FROM {db_prefix}boards AS b
			WHERE b.id_board IN ({array_int:board_list})
				AND {query_see_board}
			LIMIT ' . count($_REQUEST['boards']),
			array(
				'board_list' => $_REQUEST['boards'],
			)
		);

		// Either the board specified doesn't exist or you have no access.
		$num_boards = wesql::num_rows($request);
		if ($num_boards == 0)
			fatal_lang_error('no_board');

		$total_posts = 0;
		$boards = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if ($num_boards == 1)
				$feed_title = ' - ' . strip_tags($row['name']);

			$boards[] = $row['id_board'];
			$total_posts += $row['num_posts'];
		}
		wesql::free_result($request);

		if (!empty($boards))
			$query_this = 'b.id_board IN (' . implode(', ', $boards) . ')';

		// The more boards, the more we're going to look through...
		if ($total_posts > 100 && $total_posts > $modSettings['totalMessages'] / 12)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 500 - $_GET['limit'] * 5);
	}
	elseif (!empty($board))
	{
		$request = wesql::query('
			SELECT num_posts
			FROM {db_prefix}boards
			WHERE id_board = {int:current_board}
			LIMIT 1',
			array(
				'current_board' => $board,
			)
		);
		list ($total_posts) = wesql::fetch_row($request);
		wesql::free_result($request);

		$feed_title = ' - ' . strip_tags($board_info['name']);

		// $board is protected, so we don't need to add {query_see_board}
		$query_this = 'b.id_board = ' . (int) $board;

		// Try to look through just a few messages, if at all possible.
		if ($total_posts > 80 && $total_posts > $modSettings['totalMessages'] / 10)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 600 - $_GET['limit'] * 5);
	}
	elseif (!empty($topic))
	{
		$request = wesql::query('
			SELECT t.id_topic, m.subject
			FROM {db_prefix}topics AS t, {db_prefix}messages AS m
			WHERE t.id_topic = {int:current_topic}
				AND m.id_msg = t.id_first_msg
			LIMIT 1',
			array(
				'current_topic' => $topic,
			)
		);
		list ($total_posts, $id_topic, $subject) = wesql::fetch_row($request);
		wesql::free_result($request);

		$feed_title = ' - ' . strip_tags($subject);

		// !!! Needs to be changed to {query_see_topic} once per-topic permissions are implemented.
		$query_this = '{query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != ' . $modSettings['recycle_board'] : '') . ' AND t.id_topic = ' . (int) $id_topic;

		// Try to look through just a few messages, if at all possible.
		if (++$total_posts > 80 && $total_posts > $modSettings['totalMessages'] / 10)
			$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 600 - $_GET['limit'] * 5);
	}
	else
	{
		$query_this = '{query_see_board}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
			AND b.id_board != ' . $modSettings['recycle_board'] : '');
		$context['optimize_msg']['lowest'] = 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 100 - $_GET['limit'] * 5);
	}

	// Show in Atom, or RSS?
	$xml_format = isset($_GET['type']) && in_array($_GET['type'], array('rss', 'rss2', 'atom')) ? $_GET['type'] : 'atom';

	// !!! Birthdays?

	// List all the different types of data they can pull.
	$subActions = array(
		'recent' => array('getXmlRecent', 'recent-post'),
		'news' => array('getXmlNews', 'article'),
		'members' => array('getXmlMembers', 'member'),
		'profile' => array('getXmlProfile', null),
	);
	if (empty($_GET['sa']) || !isset($subActions[$_GET['sa']]))
		$_GET['sa'] = 'recent';

	// We only want some information, not all of it.
	$cachekey = array($xml_format, $_GET['action'], $_GET['limit'], $_GET['sa']);
	foreach (array('board', 'boards', 'c') as $var)
		if (isset($_REQUEST[$var]))
			$cachekey[] = $_REQUEST[$var];
	$cachekey = md5(serialize($cachekey) . (!empty($query_this) ? $query_this : ''));
	$cache_t = microtime(true);

	// Get the associative array representing the xml.
	if (!empty($modSettings['cache_enable']) && (!$user_info['is_guest'] || $modSettings['cache_enable'] >= 3))
		$xml = cache_get_data('xmlfeed-' . $xml_format . ':' . ($user_info['is_guest'] ? '' : $user_info['id'] . '-') . $cachekey, 240);
	if (empty($xml))
	{
		if ($xml_format == 'atom')
		{
			// Get the original $scripturl from the first time you activated this Atom feed.
			if (empty($modSettings['feed_root']))
				updateSettings(array('feed_root' => strtolower($scripturl)));
			preg_match('~[^/]+//([^/]+)/(.*?)(?:/index.php)?~', $modSettings['feed_root'], $domain);
		}
		$xml = $subActions[$_GET['sa']][0]($xml_format);

		if (!empty($modSettings['cache_enable']) && (($user_info['is_guest'] && $modSettings['cache_enable'] >= 3)
		|| (!$user_info['is_guest'] && microtime(true) - $cache_t > 0.2)))
			cache_put_data('xmlfeed-' . $xml_format . ':' . ($user_info['is_guest'] ? '' : $user_info['id'] . '-') . $cachekey, $xml, 240);
	}

	$feed_title = htmlspecialchars(strip_tags($context['forum_name'])) . (isset($feed_title) ? $feed_title : '');

	// This is an xml file....
	ob_end_clean();
	if (!empty($modSettings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');

	// Pretty URLs need to be rewritten
	if (!empty($modSettings['pretty_enable_filters']))
	{
		ob_start('ob_sessrewrite');
		$insideurl = preg_quote($scripturl, '~');
		$context['pretty']['search_patterns'][]  = '~(<link>|<comments>|<guid>|<uri>)' . $insideurl . '([^<"]*?[?;&](board|topic|u)=[^#<"]+)~';
		$context['pretty']['replace_patterns'][] = '~(<link>|<comments>|<guid>|<uri>)' . $insideurl . '([^<"]*?[?;&](board|topic|u)=([^<"]+))~';
		$context['pretty']['search_patterns'][]  = '~(<category scheme=)"' . $insideurl . '([^<"]*?[?;&](board|topic|u)=[^#<"]+)~';
		$context['pretty']['replace_patterns'][] = '~(<category scheme=)"' . $insideurl . '([^<"]*?[?;&](board|topic|u)=([^#<"]+"))~';
	}

	if (isset($_REQUEST['debug']))
		header('Content-Type: text/xml; charset=UTF-8');
	elseif ($xml_format == 'rss' || $xml_format == 'rss2')
		header('Content-Type: application/rss+xml; charset=UTF-8');
	elseif ($xml_format == 'atom')
		header('Content-Type: application/atom+xml; charset=UTF-8');

	// First, output the xml header.
	echo '<?xml version="1.0" encoding="UTF-8"?' . '>';

	// Are we outputting an RSS feed or one with more information?
	if ($xml_format == 'rss' || $xml_format == 'rss2')
	{
		// Start with an RSS 2.0 header.
		echo '
<rss version=', $xml_format == 'rss2' ? '"2.0"' : '"0.92"', '>
	<channel>
		<title>', $feed_title, '</title>
		<link>', $scripturl, '</link>
		<description>', cdata_parse(strip_tags($txt['xml_feed_desc'])), '</description>
		<language>', strtolower(strtr($txt['lang_locale'], '_', '-')), '</language>';

		// Output all of the associative array, start indenting with 2 tabs, and name everything "item".
		dumpTags($xml, 2, 'item', $xml_format);

		// Output the footer of the xml.
		echo '
	</channel>
</rss>';
	}
	// Otherwise this is the default (Atom feed.)
	else
	{
		echo '
<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="', strtr($txt['lang_locale'], '_', '-'), '">
	<title>', $feed_title, '</title>
	<link rel="alternate" type="text/html" href="', $scripturl, '" />
	<updated>', gmstrftime('%Y-%m-%dT%H:%M:%SZ'), '</updated>
	<subtitle type="html">', cdata_parse(strip_tags($txt['xml_feed_desc'])), '</subtitle>
	<generator uri="http://wedge.org" version="', WEDGE_VERSION, '">
		Wedge
	</generator>
	<author>
		<name>', strip_tags($context['forum_name']), '</name>
	</author>';

		dumpTags($xml, 1, 'entry', $xml_format);

		echo '
</feed>';
	}

	obExit(false);
}

function cdata_parse($data)
{
	return strpos($data, '</') === false && strpos($data, '&') === false ? $data : '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $data) . ']]>';
}

function tag_gen($key, $date)
{
	global $domain;

	return 'tag:' . $domain[1] . ',' . gmdate('Y-m-d', $date) . ':' . (empty($domain[2]) ? '' : $domain[2] . ':') . $key;
}

function dumpTags($data, $i, $tag = null, $xml_format = '')
{
	// For every array in the data...
	foreach ($data as $key => $val)
	{
		// Skip it, it's been set to null.
		if ($val === null)
			continue;

		// If a tag was passed, use it instead of the key.
		$key = isset($tag) ? $tag : $key;

		// First let's indent!
		echo "\n", str_repeat("\t", $i);

		// Grr, I hate kludges... almost worth doing it properly, here, but not quite.
		if ($xml_format == 'atom' && ($key == 'link' || $key == 'category'))
		{
			if ($key == 'link')
				echo '<link rel="alternate" type="text/html" href="', $val, '" />';
			else
				echo '<category', empty($val['scheme']) ? '' : ' scheme="' . $val['scheme'] . '"', ' term="', $val['term'], '" label="', str_replace('"', '&quot;', $val['label']), '" />';
			continue;
		}

		// If it's empty/0/nothing simply output an empty tag.
		if ($val == '')
			echo '<', $key, ' />';
		else
		{
			// Beginning tag.
			if ($xml_format == 'atom' && $key == 'content')
				echo '<content type="html">';
			else
				echo '<', $key, '>';

			if (is_array($val))
			{
				// An array. Dump it, and then indent the tag.
				dumpTags($val, $i + 1, null, $xml_format);
				echo "\n", str_repeat("\t", $i), '</', $key, '>';
			}
			// A string with returns in it.... show this as a multiline element.
			elseif (strpos($val, "\n") !== false || strpos($val, '<br />') !== false)
				echo "\n", $val, "\n", str_repeat("\t", $i), '</', $key, '>';
			// A simple string.
			else
				echo $val, '</', $key, '>';
		}
	}
}

function getXmlMembers($xml_format)
{
	global $scripturl;

	if (!allowedTo('view_mlist'))
		return array();

	// Find the most recent members.
	$request = wesql::query('
		SELECT id_member, member_name, real_name, date_registered, last_login
		FROM {db_prefix}members
		ORDER BY id_member DESC
		LIMIT {int:limit}',
		array(
			'limit' => $_GET['limit'],
		)
	);
	$data = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Make the data look RSS-ish.
		if ($xml_format == 'rss' || $xml_format == 'rss2')
			$data[] = array(
				'title' => cdata_parse($row['real_name']),
				'link' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'comments' => $scripturl . '?action=pm;sa=send;u=' . $row['id_member'],
				'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['date_registered']),
				'guid' => $scripturl . '?action=profile;u=' . $row['id_member'],
			);
		// Atom?
		else
			$data[] = array(
				'title' => cdata_parse($row['real_name']),
				'link' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['date_registered']),
				'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['last_login']),
				'id' => tag_gen('member-' . $row['id_member'], $row['date_registered']),
			);
	}
	wesql::free_result($request);

	return $data;
}

function getXmlNews($xml_format)
{
	global $user_info, $scripturl, $modSettings;
	global $board, $query_this, $settings, $context;

	/* Find the latest posts that:
		- are the first post in their topic.
		- are on an any board OR in a specified board.
		- can be seen by this user.
		- are actually the latest posts. */

	$done = false;
	$loops = 0;
	while (!$done)
	{
		$optimize_msg = implode(' AND ', $context['optimize_msg']);
		$request = wesql::query('
			SELECT
				m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.modified_time,
				m.icon, t.id_topic, t.id_board, b.name AS bname, mem.hide_email,
				IFNULL(mem.id_member, 0) AS id_member,
				IFNULL(mem.email_address, m.poster_email) AS poster_email,
				IFNULL(mem.real_name, m.poster_name) AS poster_name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE ' . $query_this . (empty($optimize_msg) ? '' : '
				AND {raw:optimize_msg}') . ($modSettings['postmod_active'] ? '
				AND t.approved = {int:is_approved}' : '') . '
			ORDER BY t.id_first_msg DESC
			LIMIT {int:limit}',
			array(
				'is_approved' => 1,
				'limit' => $_GET['limit'],
				'optimize_msg' => $optimize_msg,
			)
		);
		// If we don't have $_GET['limit'] results, try again with an unoptimized version covering all rows.
		if ($loops < 2 && wesql::num_rows($request) < $_GET['limit'])
		{
			wesql::free_result($request);
			if (empty($_REQUEST['boards']) && empty($board))
				unset($context['optimize_msg']['lowest']);
			else
				$context['optimize_msg']['lowest'] = 'm.id_msg >= t.id_first_msg';
			$context['optimize_msg']['highest'] = 'm.id_msg <= t.id_last_msg';
			$loops++;
		}
		else
			$done = true;
	}
	$data = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Limit the length of the message, if the option is set.
		if (!empty($modSettings['xmlnews_maxlen']) && westr::strlen(str_replace('<br />', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
		{
			// Is there a [more]? If there is, check to see how long the truncated part is. If it's too long, we'll use the admin settings anyway.
			// !!! Is 20% variance too much?
			$body = str_replace('<br />', "\n", $row['body']);
			$more_pos = stripos($body, '[more');
			if ($more_pos === false || $more_pos > $modSettings['xmlnews_maxlen'] * 1.2)
				$row['body'] = strtr(westr::substr($body, 0, $modSettings['xmlnews_maxlen'] - 3), array("\n" => '<br />')) . '...';
		}

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		censorText($row['body']);
		censorText($row['subject']);

		// Being news, this actually makes sense in RSS format.
		// Note that pubDate for items was introduced in RSS 0.93, so technically
		// it doesn't conform to RSS 0.92. I'll tell you what, just use Atom, 'kay?
		if ($xml_format == 'rss' || $xml_format == 'rss2')
			$data[] = array(
				'title' => cdata_parse($row['subject']),
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'description' => cdata_parse($row['body']),
				'author' => showEmailAddress(!empty($row['hide_email']), $row['id_member']) === 'yes_permission_override' ? $row['poster_email'] : null,
				'comments' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
				'category' => cdata_parse($row['bname']),
				'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
				'guid' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			);
		else
			$data[] = array(
				'title' => cdata_parse($row['subject']),
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
				'content' => cdata_parse($row['body']),
				'category' => array(
					'term' => $row['id_board'],
					'label' => $row['bname'],
				),
				'author' => array(
					'name' => cdata_parse($row['poster_name']),
					'email' => showEmailAddress(!empty($row['hide_email']), $row['id_member']) === 'yes_permission_override' ? $row['poster_email'] : null,
					'uri' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				),
				'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
				'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
				'id' => tag_gen('topic-' . $row['id_topic'], $row['poster_time']),
				'icon' => $settings['images_url'] . '/icons/' . $row['icon'] . '.gif',
			);
	}
	wesql::free_result($request);

	return $data;
}

function getXmlRecent($xml_format)
{
	global $user_info, $scripturl, $modSettings;
	global $board, $query_this, $settings, $context;

	$done = false;
	$loops = 0;
	while (!$done)
	{
		$optimize_msg = implode(' AND ', $context['optimize_msg']);
		$request = wesql::query('
			SELECT m.id_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			WHERE ' . $query_this . (empty($optimize_msg) ? '' : '
				AND {raw:optimize_msg}') . ($modSettings['postmod_active'] ? '
				AND m.approved = {int:is_approved}' : '') . '
			ORDER BY m.id_msg DESC
			LIMIT {int:limit}',
			array(
				'limit' => $_GET['limit'],
				'is_approved' => 1,
				'optimize_msg' => $optimize_msg,
			)
		);
		// If we don't have $_GET['limit'] results, try again with an unoptimized version covering all rows.
		if ($loops < 2 && wesql::num_rows($request) < $_GET['limit'])
		{
			wesql::free_result($request);
			if (empty($_REQUEST['boards']) && empty($board))
				unset($context['optimize_msg']['lowest']);
			else
				$context['optimize_msg']['lowest'] = $loops ? 'm.id_msg >= t.id_first_msg' : 'm.id_msg >= (t.id_last_msg - t.id_first_msg) / 2';
			$loops++;
		}
		else
			$done = true;
	}
	$messages = array();
	while ($row = wesql::fetch_assoc($request))
		$messages[] = $row['id_msg'];
	wesql::free_result($request);

	if (empty($messages))
		return array();

	// Find the most recent posts this user can see.
	$request = wesql::query('
		SELECT
			m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.id_topic, t.id_board,
			b.name AS bname, m.id_member, m.icon, m.modified_time, mem.hide_email,
			IFNULL(mem.email_address, m.poster_email) AS poster_email,
			IFNULL(mem.real_name, m.poster_name) AS poster_name
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
			' . (empty($board) ? '' : 'AND t.id_board = {int:current_board}') . '
		ORDER BY m.id_msg DESC
		LIMIT {int:limit}',
		array(
			'limit' => $_GET['limit'],
			'current_board' => $board,
			'message_list' => $messages,
		)
	);
	$data = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Limit the length of the message, if the option is set.
		if (!empty($modSettings['xmlnews_maxlen']) && westr::strlen(str_replace('<br />', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
			$row['body'] = strtr(westr::substr(str_replace('<br />', "\n", $row['body']), 0, $modSettings['xmlnews_maxlen'] - 3), array("\n" => '<br />')) . '...';

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		censorText($row['body']);
		censorText($row['subject']);

		// Doesn't work as well as news, but it kinda does..
		if ($xml_format == 'rss' || $xml_format == 'rss2')
			$data[] = array(
				'title' => cdata_parse($row['subject']),
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'description' => cdata_parse($row['body']),
				'author' => showEmailAddress(!empty($row['hide_email']), $row['id_member']) === 'yes_permission_override' ? $row['poster_email'] : null,
				'category' => cdata_parse($row['bname']),
				'comments' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
				'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
				'guid' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg']
			);
		elseif ($xml_format == 'atom')
			$data[] = array(
				'title' => cdata_parse($row['subject']),
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'content' => cdata_parse($row['body']),
				'category' => array(
					'term' => $row['id_board'], // !!! Could also store id_topic?
					'label' => $row['bname'],
				),
				'author' => array(
					'name' => cdata_parse($row['poster_name']),
					'email' => showEmailAddress(!empty($row['hide_email']), $row['id_member']) === 'yes_permission_override' ? $row['poster_email'] : null,
					'uri' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : ''
				),
				'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
				'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
				'id' => tag_gen('msg-' . $row['id_msg'], $row['poster_time']),
				'icon' => $settings['images_url'] . '/icons/' . $row['icon'] . '.gif',
			);
	}
	wesql::free_result($request);

	return $data;
}

function getXmlProfile($xml_format)
{
	global $scripturl, $memberContext, $user_profile, $modSettings, $user_info;

	// You must input a valid user....
	if (empty($_GET['u']) || loadMemberData((int) $_GET['u']) === false)
		return array();

	// Make sure the id is a number and not "I like trying to hack the database".
	$_GET['u'] = (int) $_GET['u'];
	// Load the member's contextual information!
	if (!loadMemberContext($_GET['u']) || !allowedTo('profile_view_any'))
		return array();

	// Okay, I admit it, I'm lazy. Stupid $_GET['u'] is long and hard to type.
	$profile = &$memberContext[$_GET['u']];
	$data = array();

	if ($xml_format == 'rss' || $xml_format == 'rss2')
		$data[] = array(
			'title' => cdata_parse($profile['name']),
			'link' => $scripturl . '?action=profile;u=' . $profile['id'],
			'description' => cdata_parse(isset($profile['group']) ? $profile['group'] : $profile['post_group']),
			'comments' => $scripturl . '?action=pm;sa=send;u=' . $profile['id'],
			'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $user_profile[$profile['id']]['date_registered']),
			'guid' => $scripturl . '?action=profile;u=' . $profile['id'],
		);
	elseif ($xml_format == 'atom')
		$data[] = array(
			'title' => cdata_parse($profile['name']),
			'link' => $scripturl . '?action=profile;u=' . $profile['id'],
			'content' => cdata_parse(isset($profile['group']) ? $profile['group'] : $profile['post_group']),
			'author' => array(
				'name' => $profile['real_name'],
				'email' => showEmailAddress(!empty($profile['hide_email']), $profile['id']) === 'yes_permission_override' ? $profile['email'] : null,
				'uri' => !empty($profile['website']) ? $profile['website']['url'] : ''
			),
			'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $user_profile[$profile['id']]['date_registered']),
			'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $user_profile[$profile['id']]['last_login']),
			'id' => tag_gen('member-' . $profile['id'], $user_profile[$profile['id']]['date_registered']),
			'logo' => !empty($profile['avatar']) ? $profile['avatar']['url'] : '',
		);

	// Save some memory.
	unset($profile, $memberContext[$_GET['u']]);

	return $data;
}

?>