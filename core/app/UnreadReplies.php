<?php
/**
 * Finds and retrieves information about recently posted topics that we've posted in before.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function UnreadReplies()
{
	global $board, $txt, $context, $settings, $options;

	// Guests can't have unread things, we don't know anything about them.
	is_not_guest();

	// Prefetching + lots of MySQL work = bad mojo.
	preventPrefetch();

	$context['start'] = (int) $_REQUEST['start'];
	$context['topics_per_page'] = empty($settings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $settings['defaultMaxTopics'];
	$context['page_title'] = $txt['unread_replies'];

	if (!empty($context['load_average']) && !empty($settings['loadavg_unreadreplies']) && $context['load_average'] >= $settings['loadavg_unreadreplies'])
		fatal_lang_error('loadavg_unreadreplies_disabled', false);

	// Parameters for the main query.
	$query_parameters = array();

	// Are we specifying any specific board?
	if (isset($_REQUEST['children']) && (!empty($board) || !empty($_REQUEST['boards'])))
	{
		$boards = array();

		if (!empty($_REQUEST['boards']))
		{
			$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
			foreach ($_REQUEST['boards'] as $b)
				$boards[] = (int) $b;
		}

		if (!empty($board))
			$boards[] = (int) $board;

		// The easiest thing is to just get all the boards they can see, but since we've specified the top of tree we ignore some of them
		$request = wesql::query('
			SELECT b.id_board, b.id_parent
			FROM {db_prefix}boards AS b
			WHERE {query_wanna_see_board}
				AND b.child_level > {int:no_child}
				AND b.id_board NOT IN ({array_int:boards})
			ORDER BY child_level ASC',
			array(
				'no_child' => 0,
				'boards' => $boards,
			)
		);

		while ($row = wesql::fetch_assoc($request))
			if (in_array($row['id_parent'], $boards))
				$boards[] = $row['id_board'];

		wesql::free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';boards=' . implode(',', $boards) . ';start=%1$d';
	}
	elseif (!empty($board))
	{
		$query_this_board = 'id_board = {int:board}';
		$query_parameters['board'] = $board;
		$context['querystring_board_limits'] = ';board=' . $board . '.%1$d';
	}
	elseif (!empty($_REQUEST['boards']))
	{
		$_REQUEST['boards'] = explode(',', $_REQUEST['boards']);
		foreach ($_REQUEST['boards'] as $i => $b)
			$_REQUEST['boards'][$i] = (int) $b;

		$request = wesql::query('
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.id_board IN ({array_int:board_list})',
			array(
				'board_list' => $_REQUEST['boards'],
			)
		);
		$boards = array();
		while ($row = wesql::fetch_assoc($request))
			$boards[] = $row['id_board'];
		wesql::free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';boards=' . implode(',', $boards) . ';start=%1$d';
	}
	elseif (!empty($_REQUEST['c']))
	{
		$_REQUEST['c'] = explode(',', $_REQUEST['c']);
		foreach ($_REQUEST['c'] as $i => $c)
			$_REQUEST['c'][$i] = (int) $c;

		$request = wesql::query('
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.id_cat IN ({array_int:id_cat})',
			array(
				'id_cat' => $_REQUEST['c'],
			)
		);
		$boards = array();
		while ($row = wesql::fetch_assoc($request))
			$boards[] = $row['id_board'];
		wesql::free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';c=' . implode(',', $_REQUEST['c']) . ';start=%1$d';
	}
	else
	{
		// Don't bother to show deleted posts!
		$request = wesql::query('
			SELECT b.id_board
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}' . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
				AND b.id_board != {int:recycle_board}' : ''),
			array(
				'recycle_board' => (int) $settings['recycle_board'],
			)
		);
		$boards = array();
		while ($row = wesql::fetch_assoc($request))
			$boards[] = $row['id_board'];
		wesql::free_result($request);

		if (empty($boards))
			fatal_lang_error('error_no_boards_selected');

		$query_this_board = 'id_board IN ({array_int:boards})';
		$query_parameters['boards'] = $boards;
		$context['querystring_board_limits'] = ';start=%1$d';
		$context['no_board_limits'] = true;
	}

	$sort_methods = array(
		'subject' => 'ms.subject',
		'starter' => 'IFNULL(mems.real_name, ms.poster_name)',
		'replies' => 't.num_replies',
		'views' => 't.num_views',
		'first_post' => 't.id_topic',
		'last_post' => 't.id_last_msg'
	);

	// The default is the most logical: newest first.
	if (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
	{
		$context['sort_by'] = 'last_post';
		$_REQUEST['sort'] = 't.id_last_msg';
		$ascending = isset($_REQUEST['asc']);

		$context['querystring_sort_limits'] = $ascending ? ';asc' : '';
	}
	// But, for other methods the default sort is ascending.
	else
	{
		$context['sort_by'] = $_REQUEST['sort'];
		$_REQUEST['sort'] = $sort_methods[$_REQUEST['sort']];
		$ascending = !isset($_REQUEST['desc']);

		$context['querystring_sort_limits'] = ';sort=' . $context['sort_by'] . ($ascending ? '' : ';desc');
	}
	$context['sort_direction'] = $ascending ? 'up' : 'down';

	if (!empty($_REQUEST['c']) && is_array($_REQUEST['c']) && count($_REQUEST['c']) == 1)
	{
		$request = wesql::query('
			SELECT name
			FROM {db_prefix}categories
			WHERE id_cat = {int:id_cat}
			LIMIT 1',
			array(
				'id_cat' => (int) $_REQUEST['c'][0],
			)
		);
		list ($name) = wesql::fetch_row($request);
		wesql::free_result($request);

		add_linktree($name, '<URL>?category=' . (int) $_REQUEST['c'][0]);
	}

	add_linktree($txt['unread_replies'], '<URL>?action=unreadreplies' . sprintf($context['querystring_board_limits'], 0) . $context['querystring_sort_limits']);

	loadTemplate('Recent');
	wetem::load('unread:true');

	// Needs lots of information.
	$select_clause = '
				ms.subject AS first_subject, ms.poster_time AS first_poster_time, ms.id_topic, t.id_board, b.name AS bname,
				t.num_replies, t.num_views, ms.id_member AS id_first_member, ml.id_member AS id_last_member,
				ml.poster_time AS last_poster_time, IFNULL(mems.real_name, ms.poster_name) AS first_poster_name,
				IFNULL(meml.real_name, ml.poster_name) AS last_poster_name, ml.subject AS last_subject,
				ml.icon AS last_icon, ms.icon AS first_icon, t.id_poll, t.is_pinned, t.locked, ml.modified_time AS last_modified_time,
				IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from, SUBSTRING(ml.body, 1, 385) AS last_body,
				SUBSTRING(ms.body, 1, 385) AS first_body, ml.smileys_enabled AS last_smileys, ms.smileys_enabled AS first_smileys, t.id_first_msg, t.id_last_msg';

	if ($settings['totalMessages'] > 100000)
	{
		wesql::query('
			DROP TABLE IF EXISTS {db_prefix}topics_posted_in',
			array(
			)
		);

		wesql::query('
			DROP TABLE IF EXISTS {db_prefix}log_topics_posted_in',
			array(
			)
		);

		$sortKey_joins = array(
			'ms.subject' => '
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)',
			'IFNULL(mems.real_name, ms.poster_name)' => '
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)',
		);

		// The main benefit of this temporary table is not that it's faster; it's that it avoids locks later.
		$have_temp_table = wesql::query('
			CREATE TEMPORARY TABLE {db_prefix}topics_posted_in (
				id_topic mediumint(8) unsigned NOT NULL default {string:zero},
				id_board smallint(5) unsigned NOT NULL default {string:zero},
				id_last_msg int(10) unsigned NOT NULL default {string:zero},
				id_msg int(10) unsigned NOT NULL default {string:zero},
				PRIMARY KEY (id_topic)
			)
			SELECT t.id_topic, t.id_board, t.id_last_msg, IFNULL(lmr.id_msg, 0) AS id_msg' . (!in_array($_REQUEST['sort'], array('t.id_last_msg', 't.id_topic')) ? ', ' . $_REQUEST['sort'] . ' AS sort_key' : '') . '
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})' . (isset($sortKey_joins[$_REQUEST['sort']]) ? $sortKey_joins[$_REQUEST['sort']] : '') . '
			WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
				AND t.id_board = {int:current_board}' : '') . '
				AND {query_see_topic}
			GROUP BY m.id_topic',
			array(
				'current_board' => $board,
				'current_member' => MID,
				'db_error_skip' => true,
				'zero' => '0',
			)
		) !== false;

		// If that worked, create a sample of the log_topics table too.
		if ($have_temp_table)
			$have_temp_table = wesql::query('
				CREATE TEMPORARY TABLE {db_prefix}log_topics_posted_in (
					PRIMARY KEY (id_topic)
				)
				SELECT lt.id_topic, lt.id_msg
				FROM {db_prefix}log_topics AS lt
					INNER JOIN {db_prefix}topics_posted_in AS pi ON (pi.id_topic = lt.id_topic)
				WHERE lt.id_member = {int:current_member}',
				array(
					'current_member' => MID,
					'db_error_skip' => true,
				)
			) !== false;
	}

	if (!empty($have_temp_table))
	{
		$request = wesql::query('
			SELECT COUNT(*)
			FROM {db_prefix}topics_posted_in AS pi
				LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = pi.id_topic)
			WHERE pi.' . $query_this_board . '
				AND IFNULL(lt.id_msg, pi.id_msg) < pi.id_last_msg',
			array_merge($query_parameters, array(
			))
		);
		list ($num_topics) = wesql::fetch_row($request);
		wesql::free_result($request);
	}
	else
	{
		$request = wesql::query('
			SELECT COUNT(DISTINCT t.id_topic), MIN(t.id_last_msg)
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic)
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $query_this_board . '
				AND m.id_member = {int:current_member}
				AND IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0)) < t.id_last_msg
				AND {query_see_topic}',
			array_merge($query_parameters, array(
				'current_member' => MID,
			))
		);
		list ($num_topics, $min_message) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	// Make sure the starting place makes sense and construct the page index.
	$context['page_index'] = template_page_index('<URL>?action=' . $context['action'] . $context['querystring_board_limits'] . $context['querystring_sort_limits'], $_REQUEST['start'], $num_topics, $context['topics_per_page'], true);
	$context['current_page'] = (int) $_REQUEST['start'] / $context['topics_per_page'];

	$context['links'] = array(
		'prev' => $_REQUEST['start'] >= $context['topics_per_page'] ? '<URL>?action=' . $context['action'] . sprintf($context['querystring_board_limits'], $_REQUEST['start'] - $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
		'next' => $_REQUEST['start'] + $context['topics_per_page'] < $num_topics ? '<URL>?action=' . $context['action'] . sprintf($context['querystring_board_limits'], $_REQUEST['start'] + $context['topics_per_page']) . $context['querystring_sort_limits'] : '',
	);

	if ($num_topics == 0)
	{
		$context['topics'] = array();
		if ($context['querystring_board_limits'] == ';start=%1$d')
			$context['querystring_board_limits'] = '';
		else
			$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
		return;
	}

	if (!empty($have_temp_table))
		$request = wesql::query('
			SELECT t.id_topic
			FROM {db_prefix}topics_posted_in AS t
				LEFT JOIN {db_prefix}log_topics_posted_in AS lt ON (lt.id_topic = t.id_topic)
			WHERE t.' . $query_this_board . '
				AND IFNULL(lt.id_msg, t.id_msg) < t.id_last_msg
			ORDER BY {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, array(
				'order' => (in_array($_REQUEST['sort'], array('t.id_last_msg', 't.id_topic')) ? $_REQUEST['sort'] : 't.sort_key') . ($ascending ? '' : ' DESC'),
				'offset' => $_REQUEST['start'],
				'limit' => $context['topics_per_page'],
			))
		);
	else
		$request = wesql::query('
			SELECT DISTINCT t.id_topic, {raw:sort}
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_topic = t.id_topic AND m.id_member = {int:current_member})' . (strpos($_REQUEST['sort'], 'ms.') === false ? '' : '
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)') . (strpos($_REQUEST['sort'], 'mems.') === false ? '' : '
				LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)') . '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
			WHERE t.' . $query_this_board . '
				AND t.id_last_msg >= {int:min_message}
				AND (IFNULL(lt.id_msg, IFNULL(lmr.id_msg, 0))) < t.id_last_msg
				AND {query_see_topic}
			ORDER BY {raw:order}
			LIMIT {int:offset}, {int:limit}',
			array_merge($query_parameters, array(
				'current_member' => MID,
				'min_message' => (int) $min_message,
				'order' => $_REQUEST['sort'] . ($ascending ? '' : ' DESC'),
				'offset' => $_REQUEST['start'],
				'limit' => $context['topics_per_page'],
				'sort' => $_REQUEST['sort'],
			))
		);

	$topics = array();
	while ($row = wesql::fetch_assoc($request))
		$topics[] = $row['id_topic'];
	wesql::free_result($request);

	// Sanity... where have you gone?
	if (empty($topics))
	{
		$context['topics'] = array();
		if ($context['querystring_board_limits'] == ';start=%1$d')
			$context['querystring_board_limits'] = '';
		else
			$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
		return;
	}

	$request = wesql::query('
		SELECT ' . $select_clause . '
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_topic = t.id_topic AND ms.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			LEFT JOIN {db_prefix}members AS mems ON (mems.id_member = ms.id_member)
			LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = t.id_board AND lmr.id_member = {int:current_member})
		WHERE t.id_topic IN ({array_int:topic_list})
		ORDER BY ' . $_REQUEST['sort'] . ($ascending ? '' : ' DESC') . '
		LIMIT ' . count($topics),
		array(
			'current_member' => MID,
			'topic_list' => $topics,
		)
	);

	loadTemplate('MessageIndex');
	wetem::add('sidebar', 'messageindex_legend');

	$context['topics'] = array();
	$topic_ids = array();

	while ($row = wesql::fetch_assoc($request))
	{
		$topic_ids[] = $row['id_topic'];

		if (!empty($context['message_index_preview']))
		{
			// Limit them to 128 characters - do this FIRST because it's a lot of wasted censoring otherwise.
			$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], 'post-preview', array('smileys' => $row['first_smileys'], 'cache' => $row['id_first_msg'], 'user' => $row['id_first_member'])), array('<br>' => '&#10;')));
			if (westr::strlen($row['first_body']) > 128)
				$row['first_body'] = westr::substr($row['first_body'], 0, 128) . '...';

			// Censor the subject and message preview.
			censorText($row['first_subject']);
			censorText($row['first_body']);

			// Don't censor them twice!
			if ($row['id_first_msg'] == $row['id_last_msg'])
			{
				$row['last_subject'] = $row['first_subject'];
				$row['last_body'] = $row['first_body'];
			}
			else
			{
				$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], 'post-preview', array('smileys' => $row['last_smileys'], 'cache' => $row['id_last_msg'], 'user' => $row['id_last_member'])), array('<br>' => '&#10;')));
				if (westr::strlen($row['last_body']) > 128)
					$row['last_body'] = westr::substr($row['last_body'], 0, 128) . '...';

				censorText($row['last_subject']);
				censorText($row['last_body']);
			}
		}
		else
		{
			$row['first_body'] = '';
			$row['last_body'] = '';
			censorText($row['first_subject']);

			if ($row['id_first_msg'] == $row['id_last_msg'])
				$row['last_subject'] = $row['first_subject'];
			else
				censorText($row['last_subject']);
		}

		// Decide how many pages the topic should have.
		// @todo Should this use a variation on template_page_index?
		$topic_length = $row['num_replies'] + 1;
		$messages_per_page = empty($settings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $settings['defaultMaxMessages'];
		if ($topic_length > $messages_per_page)
		{
			$tmppages = array();
			$tmpa = 1;
			for ($tmpb = 0; $tmpb < $topic_length; $tmpb += $messages_per_page)
			{
				$tmppages[] = '<a href="<URL>?topic=' . $row['id_topic'] . '.' . $tmpb . '">' . $tmpa . '</a>';
				$tmpa++;
			}
			// Show links to all the pages?
			if (count($tmppages) <= 5)
				$pages = '« ' . implode(' ', $tmppages);
			// Or skip a few?
			else
				$pages = '« ' . $tmppages[0] . ' ' . $tmppages[1] . ' ... ' . $tmppages[count($tmppages) - 2] . ' ' . $tmppages[count($tmppages) - 1];

			if (!empty($settings['enableAllMessages']) && $topic_length < $settings['enableAllMessages'])
				$pages .= ' &nbsp;<a href="<URL>?topic=' . $row['id_topic'] . '.0;all">' . $txt['all_pages'] . '</a>';
			$pages .= ' »';
		}
		else
			$pages = '';

		$no_replies = $row['num_replies'] == 0;

		// And build the array.
		$context['topics'][$row['id_topic']] = array(
			'id' => $row['id_topic'],
			'first_post' => array(
				'id' => $row['id_first_msg'],
				'member' => array(
					'name' => $row['first_poster_name'],
					'id' => $row['id_first_member'],
					'href' => '<URL>?action=profile;u=' . $row['id_first_member'],
					'link' => !empty($row['id_first_member']) ? '<a href="<URL>?action=profile;u=' . $row['id_first_member'] . '" title="' . $txt['view_profile'] . '">' . $row['first_poster_name'] . '</a>' : $row['first_poster_name']
				),
				'time' => timeformat($row['first_poster_time']),
				'timestamp' => $row['first_poster_time'],
				'subject' => $row['first_subject'],
				'preview' => $row['first_body'],
				'icon' => $row['first_icon'],
				'icon_url' => ASSETS . '/post/' . $row['first_icon'] . '.gif',
				'href' => '<URL>?topic=' . $row['id_topic'] . '.0',
				'link' => '<a href="<URL>?topic=' . $row['id_topic'] . '.0">' . $row['first_subject'] . '</a>'
			),
			'last_post' => array(
				'id' => $row['id_last_msg'],
				'member' => array(
					'name' => $row['last_poster_name'],
					'id' => $row['id_last_member'],
					'href' => '<URL>?action=profile;u=' . $row['id_last_member'],
					'link' => !empty($row['id_last_member']) ? '<a href="<URL>?action=profile;u=' . $row['id_last_member'] . '">' . $row['last_poster_name'] . '</a>' : $row['last_poster_name']
				),
				'time' => timeformat($row['last_poster_time']),
				'timestamp' => $row['last_poster_time'],
				'subject' => $row['last_subject'],
				'preview' => $row['last_body'],
				'icon' => $row['last_icon'],
				'icon_url' => ASSETS . '/post/' . $row['last_icon'] . '.gif',
				'href' => '<URL>?topic=' . $row['id_topic'] . ($no_replies ? '.0' : '.msg' . $row['id_last_msg'] . '#new'),
				'link' => '<a href="<URL>?topic=' . $row['id_topic'] . ($no_replies ? '.0' : '.msg' . $row['id_last_msg'] . '#new') . '">' . $row['last_subject'] . '</a>'
			),
			'new_from' => $row['new_from'],
			'new_href' => '<URL>?topic=' . $row['id_topic'] . ($no_replies ? '.0' : '.msg' . $row['new_from'] . '#new'),
			'new_link' => '<a href="<URL>?topic=' . $row['id_topic'] . ($no_replies ? '.0' : '.msg' . $row['new_from'] . '#new') . '">' . $row['first_subject'] . '</a>',
			'is_pinned' => !empty($row['is_pinned']),
			'is_locked' => !empty($row['locked']),
			'is_poll' => $row['id_poll'] > 0,
			'is_posted_in' => true,
			'icon' => $row['first_icon'],
			'icon_url' => ASSETS . '/post/' . $row['first_icon'] . '.gif',
			'subject' => $row['first_subject'],
			'pages' => $pages,
			'replies' => comma_format($row['num_replies']),
			'views' => comma_format($row['num_views']),
			'board' => array(
				'id' => $row['id_board'],
				'name' => $row['bname'],
				'href' => '<URL>?board=' . $row['id_board'] . '.0',
				'link' => '<a href="<URL>?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>'
			)
		);
	}
	wesql::free_result($request);

	// Count number of new posts per topic.
	if (count($topic_ids) > 0)
		$context['nb_new'] = get_unread_numbers(array_keys($context['topics']), true);

	$context['querystring_board_limits'] = sprintf($context['querystring_board_limits'], $_REQUEST['start']);
	$context['topics_to_mark'] = implode('-', $topic_ids);
}
