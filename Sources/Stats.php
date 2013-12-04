<?php
/**
 * Gathers the statistics from the entire forum.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This function has only one job: providing a display for forum statistics.
	As such, it has only one function:

	void Stats()
		- gets all the statistics in order and puts them in.
		- uses the Stats template and language file. (and main block.)
		- requires the view_stats permission.
		- accessed from ?action=stats.

	void getDailyStats(string $condition)
		- called by DisplayStats().
		- loads the statistics on a daily basis in $context.

*/

// Display some useful/interesting board statistics.
function Stats()
{
	global $txt, $settings, $context;

	isAllowedTo('view_stats');
	$can_view_most_online = allowedTo('moderate_forum');

	if (empty($settings['trackStats']))
		fatal_lang_error('cannot_view_stats', false);

	// Get averages...
	$result = wesql::query('
		SELECT
			SUM(posts) AS posts, SUM(topics) AS topics, SUM(registers) AS registers,
			SUM(most_on) AS most_on, MIN(date) AS date, SUM(hits) AS hits
		FROM {db_prefix}log_activity'
	);
	$row = wesql::fetch_assoc($result);
	wesql::free_result($result);

	// This would be the amount of time the forum has been up... in days...
	$total_days_up = ceil((time() - strtotime($row['date'])) / (60 * 60 * 24));
	$context['first_stats'] = $row['date'];

	loadLanguage('Stats');

	getStats();

	loadTemplate('Stats');

	if (empty($settings['trackStats']))
		fatal_lang_error('stats_not_available', false);

	// Build the link tree......
	add_linktree($txt['stats_center'], '<URL>?action=stats');
	$context['page_title'] = $context['forum_name'] . ' - ' . $txt['stats_center'];

	$context['show_member_list'] = allowedTo('view_mlist');

	$context['average_posts'] = comma_format(round($row['posts'] / $total_days_up, 2));
	$context['average_topics'] = comma_format(round($row['topics'] / $total_days_up, 2));
	$context['average_registers'] = comma_format(round($row['registers'] / $total_days_up, 2));
	$context['average_most_on'] = comma_format(round($row['most_on'] / $total_days_up, 2));
	$context['average_hits'] = comma_format(round($row['hits'] / $total_days_up, 2));

	$context['num_hits'] = comma_format($row['hits'], 0);

	// How many users are online now.
	$result = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}log_online'
	);
	list ($context['users_online']) = wesql::fetch_row($result);
	wesql::free_result($result);

	// Statistics such as number of boards, categories, etc.
	$result = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}boards AS b
		WHERE b.redirect = {string:blank_redirect}',
		array(
			'blank_redirect' => '',
		)
	);
	list ($context['num_boards']) = wesql::fetch_row($result);
	wesql::free_result($result);

	$result = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}categories AS c'
	);
	list ($context['num_categories']) = wesql::fetch_row($result);
	wesql::free_result($result);

	// Format the numbers nicely.
	$context['users_online'] = comma_format($context['users_online']);
	$context['num_boards'] = comma_format($context['num_boards']);
	$context['num_categories'] = comma_format($context['num_categories']);

	$context['num_members'] = comma_format($settings['totalMembers']);
	$context['num_posts'] = comma_format($settings['totalMessages']);
	$context['num_topics'] = comma_format($settings['totalTopics']);
	$context['latest_member'] =& $context['common_stats']['latest_member'];

	if ($can_view_most_online)
		$context['most_online'] = array(
			'number' => comma_format($settings['mostOnline']),
			'date' => timeformat($settings['mostDate'])
		);

	// Male vs. female ratio - let's calculate this only every four minutes.
	if (($context['gender'] = cache_get_data('stats_gender', 240)) == null)
	{
		$result = wesql::query('
			SELECT COUNT(*) AS total_members, gender
			FROM {db_prefix}members
			GROUP BY gender'
		);
		$context['gender'] = array();
		// Assuming we're telling... male or female?
		while ($row = wesql::fetch_assoc($result))
			if (!empty($row['gender']))
				$context['gender'][$row['gender'] == 2 ? 'females' : 'males'] = $row['total_members'];
		wesql::free_result($result);

		// Set these two zero if the didn't get set at all.
		if (empty($context['gender']['males']))
			$context['gender']['males'] = 0;
		if (empty($context['gender']['females']))
			$context['gender']['females'] = 0;

		// Try and come up with some "sensible" default states in case of a non-mixed board.
		if (!$context['gender']['males'])
			$context['gender']['ratio'] = $context['gender']['females'] ? '0:1' : $txt['not_applicable'];
		elseif (!$context['gender']['females'])
			$context['gender']['ratio'] = '1:0';
		elseif ($context['gender']['males'] > $context['gender']['females'])
			$context['gender']['ratio'] = round($context['gender']['males'] / $context['gender']['females'], 1) . ':1';
		else
			$context['gender']['ratio'] = '1:' . round($context['gender']['females'] / $context['gender']['males'], 1);

		cache_put_data('stats_gender', $context['gender'], 240);
	}

	$date = strftime('%Y-%m-%d', forum_time(false));

	// Members online so far today.
	$result = wesql::query('
		SELECT most_on
		FROM {db_prefix}log_activity
		WHERE date = {date:today_date}
		LIMIT 1',
		array(
			'today_date' => $date,
		)
	);
	list ($context['online_today']) = wesql::fetch_row($result);
	wesql::free_result($result);

	$context['online_today'] = comma_format((int) $context['online_today']);

	// Top 10 posters.
	$members_result = wesql::query('
		SELECT id_member, real_name, posts
		FROM {db_prefix}members
		WHERE posts > {literal:0}
		ORDER BY posts DESC
		LIMIT 10'
	);
	$context['top_posters'] = array();
	$max_num_posts = 1;
	while ($row_members = wesql::fetch_assoc($members_result))
	{
		$context['top_posters'][] = array(
			'name' => $row_members['real_name'],
			'id' => $row_members['id_member'],
			'num_posts' => $row_members['posts'],
			'href' => '<URL>?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="<URL>?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		);

		$max_num_posts = max($max_num_posts, $row_members['posts']);
	}
	wesql::free_result($members_result);

	foreach ($context['top_posters'] as $i => $poster)
	{
		$context['top_posters'][$i]['post_percent'] = max(5, round(($poster['num_posts'] * 100) / $max_num_posts));
		$context['top_posters'][$i]['num_posts'] = comma_format($context['top_posters'][$i]['num_posts']);
	}

	// Top 10 active boards.
	$boards_result = wesql::query('
		SELECT id_board, name, num_posts
		FROM {db_prefix}boards AS b
		WHERE {query_see_board}' . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . '
			AND b.redirect = {string:blank_redirect}
		ORDER BY num_posts DESC
		LIMIT 10',
		array(
			'recycle_board' => $settings['recycle_board'],
			'blank_redirect' => '',
		)
	);
	$context['top_boards'] = array();
	$max_num_posts = 1;
	while ($row_board = wesql::fetch_assoc($boards_result))
	{
		$context['top_boards'][] = array(
			'id' => $row_board['id_board'],
			'name' => $row_board['name'],
			'num_posts' => $row_board['num_posts'],
			'href' => '<URL>?board=' . $row_board['id_board'] . '.0',
			'link' => '<a href="<URL>?board=' . $row_board['id_board'] . '.0">' . $row_board['name'] . '</a>'
		);

		$max_num_posts = max($max_num_posts, $row_board['num_posts']);
	}
	wesql::free_result($boards_result);

	foreach ($context['top_boards'] as $i => $board)
	{
		$context['top_boards'][$i]['post_percent'] = max(5, round(($board['num_posts'] * 100) / $max_num_posts));
		$context['top_boards'][$i]['num_posts'] = comma_format($context['top_boards'][$i]['num_posts']);
	}

	// Are you on a larger forum?  If so, let's try to limit the number of topics we search through.
	if ($settings['totalMessages'] > 100000)
	{
		$request = wesql::query('
			SELECT id_topic
			FROM {db_prefix}topics AS t
			WHERE num_replies != {literal:0}
				AND {query_see_topic}
			ORDER BY num_replies DESC
			LIMIT 100'
		);
		$topic_ids = array();
		while ($row = wesql::fetch_assoc($request))
			$topic_ids[] = $row['id_topic'];
		wesql::free_result($request);
	}
	else
		$topic_ids = array();

	// Top 10 active topics.
	$topic_reply_result = wesql::query('
		SELECT m.subject, t.num_replies, t.id_board, t.id_topic, b.name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . ')
		WHERE {query_see_board}' . (!empty($topic_ids) ? '
			AND t.id_topic IN ({array_int:topic_list})' : '
			AND {query_see_topic}') . '
		ORDER BY t.num_replies DESC
		LIMIT 10',
		array(
			'topic_list' => $topic_ids,
			'recycle_board' => $settings['recycle_board'],
		)
	);
	$context['top_topics_replies'] = array();
	$max_num_replies = 1;
	while ($row_topic_reply = wesql::fetch_assoc($topic_reply_result))
	{
		censorText($row_topic_reply['subject']);

		$context['top_topics_replies'][] = array(
			'id' => $row_topic_reply['id_topic'],
			'board' => array(
				'id' => $row_topic_reply['id_board'],
				'name' => $row_topic_reply['name'],
				'href' => '<URL>?board=' . $row_topic_reply['id_board'] . '.0',
				'link' => '<a href="<URL>?board=' . $row_topic_reply['id_board'] . '.0">' . $row_topic_reply['name'] . '</a>'
			),
			'subject' => $row_topic_reply['subject'],
			'num_replies' => $row_topic_reply['num_replies'],
			'href' => '<URL>?topic=' . $row_topic_reply['id_topic'] . '.0',
			'link' => '<a href="<URL>?topic=' . $row_topic_reply['id_topic'] . '.0">' . $row_topic_reply['subject'] . '</a>'
		);

		$max_num_replies = max($max_num_replies, $row_topic_reply['num_replies']);
	}
	wesql::free_result($topic_reply_result);

	foreach ($context['top_topics_replies'] as $i => $topic)
	{
		$context['top_topics_replies'][$i]['post_percent'] = max(5, round(($topic['num_replies'] * 100) / $max_num_replies));
		$context['top_topics_replies'][$i]['num_replies'] = comma_format($context['top_topics_replies'][$i]['num_replies']);
	}

	// Large forums may need a bit more prodding...
	if ($settings['totalMessages'] > 100000)
	{
		$request = wesql::query('
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE num_views != {literal:0}
			ORDER BY num_views DESC
			LIMIT 100'
		);
		$topic_ids = array();
		while ($row = wesql::fetch_assoc($request))
			$topic_ids[] = $row['id_topic'];
		wesql::free_result($request);
	}
	else
		$topic_ids = array();

	// Top 10 viewed topics.
	$topic_view_result = wesql::query('
		SELECT m.subject, t.num_views, t.id_board, t.id_topic, b.name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board' . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
			AND b.id_board != {int:recycle_board}' : '') . ')
		WHERE {query_see_board}' . (!empty($topic_ids) ? '
			AND t.id_topic IN ({array_int:topic_list})' : '
			AND {query_see_topic}') . '
		ORDER BY t.num_views DESC
		LIMIT 10',
		array(
			'topic_list' => $topic_ids,
			'recycle_board' => $settings['recycle_board'],
		)
	);
	$context['top_topics_views'] = array();
	$max_num_views = 1;
	while ($row_topic_views = wesql::fetch_assoc($topic_view_result))
	{
		censorText($row_topic_views['subject']);

		$context['top_topics_views'][] = array(
			'id' => $row_topic_views['id_topic'],
			'board' => array(
				'id' => $row_topic_views['id_board'],
				'name' => $row_topic_views['name'],
				'href' => '<URL>?board=' . $row_topic_views['id_board'] . '.0',
				'link' => '<a href="<URL>?board=' . $row_topic_views['id_board'] . '.0">' . $row_topic_views['name'] . '</a>'
			),
			'subject' => $row_topic_views['subject'],
			'num_views' => $row_topic_views['num_views'],
			'href' => '<URL>?topic=' . $row_topic_views['id_topic'] . '.0',
			'link' => '<a href="<URL>?topic=' . $row_topic_views['id_topic'] . '.0">' . $row_topic_views['subject'] . '</a>'
		);

		$max_num_views = max($max_num_views, $row_topic_views['num_views']);
	}
	wesql::free_result($topic_view_result);

	foreach ($context['top_topics_views'] as $i => $topic)
	{
		$context['top_topics_views'][$i]['post_percent'] = max(5, round(($topic['num_views'] * 100) / $max_num_views));
		$context['top_topics_views'][$i]['num_views'] = comma_format($context['top_topics_views'][$i]['num_views']);
	}

	// Try to cache this when possible, because it's a little unavoidably slow.
	if (($members = cache_get_data('stats_top_starters', 360)) == null)
	{
		$request = wesql::query('
			SELECT id_member_started, COUNT(*) AS hits
			FROM {db_prefix}topics' . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
			WHERE id_board != {int:recycle_board}' : '') . '
			GROUP BY id_member_started
			ORDER BY hits DESC
			LIMIT 20',
			array(
				'recycle_board' => $settings['recycle_board'],
			)
		);
		$members = array();
		while ($row = wesql::fetch_assoc($request))
			$members[$row['id_member_started']] = $row['hits'];
		wesql::free_result($request);

		cache_put_data('stats_top_starters', $members, 360);
	}

	if (empty($members))
		$members = array(0 => 0);

	// Top 10 topic creators.
	$members_result = wesql::query('
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:member_list})
		ORDER BY FIND_IN_SET(id_member, {string:top_topic_posters})
		LIMIT 10',
		array(
			'member_list' => array_keys($members),
			'top_topic_posters' => implode(',', array_keys($members)),
		)
	);
	$context['top_starters'] = array();
	$max_num_topics = 1;
	while ($row_members = wesql::fetch_assoc($members_result))
	{
		$context['top_starters'][] = array(
			'name' => $row_members['real_name'],
			'id' => $row_members['id_member'],
			'num_topics' => $members[$row_members['id_member']],
			'href' => '<URL>?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="<URL>?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		);

		$max_num_topics = max($max_num_topics, $members[$row_members['id_member']]);
	}
	wesql::free_result($members_result);

	foreach ($context['top_starters'] as $i => $topic)
	{
		$context['top_starters'][$i]['post_percent'] = max(5, round(($topic['num_topics'] * 100) / $max_num_topics));
		$context['top_starters'][$i]['num_topics'] = comma_format($context['top_starters'][$i]['num_topics']);
	}

	// Top 10 time spent online.
	$temp = cache_get_data('stats_total_time_members', 600);
	$members_result = wesql::query('
		SELECT id_member, real_name, total_time_logged_in
		FROM {db_prefix}members' . (!empty($temp) ? '
		WHERE id_member IN ({array_int:member_list_cached})' : '') . '
		ORDER BY total_time_logged_in DESC
		LIMIT 20',
		array(
			'member_list_cached' => $temp,
		)
	);
	$context['top_time_online'] = array();
	$temp2 = array();
	$max_time_online = 1;
	while ($row_members = wesql::fetch_assoc($members_result))
	{
		$temp2[] = (int) $row_members['id_member'];
		if (count($context['top_time_online']) >= 10)
			continue;

		// Figure out the days, hours and minutes.
		$timeDays = floor($row_members['total_time_logged_in'] / 86400);
		$timeHours = floor(($row_members['total_time_logged_in'] % 86400) / 3600);

		// Figure out which things to show... (days, hours, minutes, etc.)
		$timelogged = '';
		if ($timeDays > 0)
			$timelogged .= $timeDays . $txt['totalTimeLogged_d_short'];
		// Don't bother to show hours for your forum barflies.
		if (($timeHours > 0 || $timeDays < 30) && $timeDays < 100)
			$timelogged .= $timeHours . $txt['totalTimeLogged_h_short'];
		// And don't show minutes for your... more... humanoid regulars.
		if ($timeDays < 30)
			$timelogged .= floor(($row_members['total_time_logged_in'] % 3600) / 60) . $txt['totalTimeLogged_m_short'];

		$context['top_time_online'][] = array(
			'id' => $row_members['id_member'],
			'name' => $row_members['real_name'],
			'time_online' => $timelogged,
			'seconds_online' => $row_members['total_time_logged_in'],
			'href' => '<URL>?action=profile;u=' . $row_members['id_member'],
			'link' => '<a href="<URL>?action=profile;u=' . $row_members['id_member'] . '">' . $row_members['real_name'] . '</a>'
		);

		$max_time_online = max($max_time_online, $row_members['total_time_logged_in']);
	}
	wesql::free_result($members_result);

	foreach ($context['top_time_online'] as $i => $member)
		$context['top_time_online'][$i]['time_percent'] = max(5, round(($member['seconds_online'] * 100) / $max_time_online));

	// Cache the ones we found for a bit, just so we don't have to look again.
	if ($temp !== $temp2)
		cache_put_data('stats_total_time_members', $temp2, 480);

	// Top 10 liked posts.
	$likes_result = wesql::query('
		SELECT COUNT(k.id_member) AS likes, m.id_member, m.real_name, msg.id_msg, msg.id_topic, msg.subject
		FROM {db_prefix}likes AS k
			INNER JOIN {db_prefix}messages AS msg ON k.id_content = msg.id_msg AND k.content_type = {literal:post}
			LEFT JOIN {db_prefix}members AS m ON msg.id_member = m.id_member
		GROUP BY msg.id_msg
		ORDER BY likes DESC
		LIMIT 10'
	);
	$max_num_likes = 0;
	$context['top_likes'] = array();
	while ($row_likes = wesql::fetch_assoc($likes_result))
	{
		$context['top_likes'][] = array(
			'num_likes' => $row_likes['likes'],
			'subject' => $row_likes['subject'],
			'member_name' => $row_likes['real_name'],
			'id_member' => $row_likes['id_member'],
			'href' => '<URL>?topic=' . $row_likes['id_topic'] . '.msg' . $row_likes['id_msg'] . '#msg' . $row_likes['id_msg'],
			'link' => '<a href="<URL>?topic=' . $row_likes['id_topic'] . '.msg' . $row_likes['id_msg'] . '#msg' . $row_likes['id_msg'] . '">' . $row_likes['subject'] . '</a> (<a href="<URL>?action=profile;u=' . $row_likes['id_member'] . '">' . $row_likes['real_name'] . '</a>)'
		);

		$max_num_likes = max($max_num_likes, $row_likes['likes']);
	}
	wesql::free_result($likes_result);

	foreach ($context['top_likes'] as $i => $like)
	{
		$context['top_likes'][$i]['post_percent'] = max(5, round(($like['num_likes'] * 100) / $max_num_likes));
		$context['top_likes'][$i]['num_likes'] = comma_format($context['top_likes'][$i]['num_likes']);
	}

	// Top 10 liked authors.
	$likes_result = wesql::query('
		SELECT COUNT(k.id_content) AS likes, m.id_member, m.real_name
		FROM {db_prefix}likes AS k
		LEFT JOIN {db_prefix}messages AS msg ON k.id_content = msg.id_msg AND k.content_type = {literal:post}
		LEFT JOIN {db_prefix}thoughts AS th ON k.id_content = th.id_thought AND k.content_type = {literal:think}
		JOIN {db_prefix}members AS m ON m.id_member = IFNULL(msg.id_member, th.id_member)
		GROUP BY m.id_member
		ORDER BY likes DESC
		LIMIT 10'
	);
	$max_num_likes = 0;
	$context['top_author_likes'] = array();
	while ($row_likes = wesql::fetch_assoc($likes_result))
	{
		$context['top_author_likes'][] = array(
			'num_likes' => $row_likes['likes'],
			'member_name' => $row_likes['real_name'],
			'id_member' => $row_likes['id_member']
		);

		$max_num_likes = max($max_num_likes, $row_likes['likes']);
	}
	wesql::free_result($likes_result);

	foreach ($context['top_author_likes'] as $i => $like)
	{
		$context['top_author_likes'][$i]['post_percent'] = max(5, round(($like['num_likes'] * 100) / $max_num_likes));
		$context['top_author_likes'][$i]['num_likes'] = comma_format($context['top_author_likes'][$i]['num_likes']);
	}
}

function getStats()
{
	global $context, $txt, $settings;

	$where = '1=1';
	$range = isset($_REQUEST['range']) ? $_REQUEST['range'] : (isset($_SESSION['stat_charts'], $_SESSION['stat_charts']['range']) ? $_SESSION['stat_charts']['range'] : 'last_month');
	// Hits, posts and topics are sorted in decreasing order of magniture, to ensure the biggest stat gets a 'filled' area.
	$possible_names = array('hits', 'posts', 'topics', 'registers', 'most_on');
	if (empty($settings['hitStats']))
		array_shift($possible_names);
	list ($starting_year, $starting_month, $starting_day) = explode('-', $context['first_stats']);
	$available_months = date('m') + (date('m') >= $starting_month ? 0 : 12) - $starting_month + (date('Y') - $starting_year) * 12;

	$filter = isset($_REQUEST['filter']) ? $_REQUEST['filter'] : (isset($_SESSION['stat_charts'], $_SESSION['stat_charts']['filter']) ? $_SESSION['stat_charts']['filter'] : 'posts');
	$varnames = array_intersect($possible_names, explode(',', $filter));
	if (empty($varnames))
		$varnames = $possible_names;
	$_SESSION['stat_charts'] = array(
		'range' => $range,
		'filter' => $filter
	);

	if (strpos($range, '-') !== false)
		list ($month, $year) = explode('-', $range);
	$month = isset($month) ? max(0, min((int) $month, 12)) : 0;
	$year = isset($year) ? max(1900, min((int) $year, date('Y'))) : 0;

	if (!$year && !$month)
	{
		if ($range == 'last_decade')
		{
			if ($available_months < 2)
				$stats = getDailyStats($varnames);
			elseif ($available_months < 50)
				$stats = getMonthlyStats($varnames);
			elseif ($available_months < 150) // Okay, so that's ~12 years, let's not fight about it...
				$stats = getQuarterlyStats($varnames);
			else
				$stats = getQuarterlyStats($varnames, 'date >= {string:date}', array('date' => (date('Y') - 12) . '-01-01'));
		}
		elseif ($range == 'last_year')
		{
			$yearago = time() - 60 * 60 * 24 * 365;
			$year = date('Y', $yearago);
			$month = date('n', $yearago) + 1;
			if ($month == 13)
			{
				$month = 1;
				$year++;
			}
			$stats = getMonthlyStats($varnames, 'date >= {string:date}', array('date' => $year . '-' . substr(100 + $month, 1) . '-01'));
		}
		else // last_month, i.e. the default?
		{
			$_SESSION['stat_charts']['range'] = $range = 'last_month';
			$monthago = time() - 60 * 60 * 24 * 30;
			$stats = getDailyStats(
				$varnames,
				'date >= {string:date}',
				array('date' => date('Y', $monthago) . '-' . date('m', $monthago) . '-' . date('d', $monthago))
			);
		}
	}
	else
	{
		$params = array('year' => $year, 'month' => $month);
		$where = $year ? 'YEAR(date) = {int:year}' : '1=1';
		if ($month)
			$where .= ' AND MONTH(date) = {int:month}';
		$stats = $month ? getDailyStats($varnames, $where, $params) : getMonthlyStats($varnames, $where, $params);
	}

	// Handle the Ajax request.
	if (AJAX)
		return_json($stats);

	add_js('
	first_stats = "', $context['first_stats'], '";
	current_range = "', $range, '";');
	// !! Could also add: current_filter = "', $filter, '";

	// And, all the data the template needs to deal with.
	$context['full_chart'] = $stats;
	$context['available_filters'] = $possible_names;
}

function getQuarterlyStats($what, $condition_string = '1=1', $condition_parameters = array())
{
	global $txt;

	// Activity by quarter.
	$result = wesql::query('
		SELECT
			YEAR(date) AS stats_year,
			CEIL(MONTH(date) / 3) AS stats_quarter,
			COUNT(*) AS num_days,
			SUM(hits) AS hits,
			SUM(posts) AS posts,
			SUM(topics) AS topics,
			MAX(most_on) AS most_on,
			SUM(registers) AS registers
		FROM {db_prefix}log_activity
		WHERE ' . $condition_string . '
		GROUP BY stats_year, stats_quarter',
		$condition_parameters
	);

	$quarterly = array();
	while ($row = wesql::fetch_assoc($result))
	{
		$quarterly['labels'][] = sprintf('%04d (%d)', $row['stats_year'], $row['stats_quarter']);
		$quarterly['long_labels'][] = sprintf('%s-%s %04d', $txt['months'][$row['stats_quarter'] * 3 - 2], $txt['months'][$row['stats_quarter'] * 3], $row['stats_year']);
		foreach ($what as $type)
			$quarterly[$type][] = (float) $row[$type];
	}
	wesql::free_result($result);

	return $quarterly;
}

function getMonthlyStats($what, $condition_string = '1=1', $condition_parameters = array())
{
	global $txt;

	// Activity by month.
	$result = wesql::query('
		SELECT
			YEAR(date) AS stats_year,
			MONTH(date) AS stats_month,
			COUNT(*) AS num_days,
			SUM(hits) AS hits,
			SUM(posts) AS posts,
			SUM(topics) AS topics,
			MAX(most_on) AS most_on,
			SUM(registers) AS registers
		FROM {db_prefix}log_activity
		WHERE ' . $condition_string . '
		GROUP BY stats_year, stats_month',
		$condition_parameters
	);

	$monthly = array();
	while ($row = wesql::fetch_assoc($result))
	{
		$monthly['labels'][] = sprintf('%s %04d', $txt['months_short'][(int) $row['stats_month']], $row['stats_year']);
		$monthly['long_labels'][] = sprintf('%s %04d', $txt['months'][(int) $row['stats_month']], $row['stats_year']);
		foreach ($what as $type)
			$monthly[$type][] = (float) $row[$type];
	}
	wesql::free_result($result);

	return $monthly;
}

function getDailyStats($what, $condition_string = '1=1', $condition_parameters = array())
{
	global $txt;

	// Activity by day.
	$result = wesql::query('
		SELECT
			YEAR(date) AS stats_year, MONTH(date) AS stats_month, DAYOFMONTH(date) AS stats_day,
			hits, posts, topics, most_on, registers
		FROM {db_prefix}log_activity
		WHERE ' . $condition_string . '
		ORDER BY date',
		$condition_parameters
	);

	$daily = array();
	$this_year = date('Y');
	while ($row = wesql::fetch_assoc($result))
	{
		$daily['labels'][] = $row['stats_day'] == 1 ? $txt['months_short'][$row['stats_month']] : (int) $row['stats_day'];
		$daily['long_labels'][] = timeformat(mktime(0, 0, 0, $row['stats_month'], $row['stats_day'], $row['stats_year']), $txt[$row['stats_year'] == $this_year ? 'date_format_this_year' : 'date_format']);
		foreach ($what as $type)
			$daily[$type][] = (float) $row[$type];
	}
	wesql::free_result($result);

	return $daily;
}
