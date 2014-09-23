<?php
/**
 * Collects the data needed to show a list of boards for the board index (and sub-boards in the message index)
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	array getBoardIndex(array boardIndexOptions)
		- Fetches a list of boards and (optional) categories including
		  statistical information, child boards and moderators.
		- Used by both the board list (main data) and the message index (child boards).
		- Depending on the include_categories setting returns an associative
		  array with categories->boards->child_boards or an associative array
		  with boards->child_boards.
*/

function getBoardIndex($boardIndexOptions)
{
	global $settings, $txt, $context;

	// We'll need this.
	loadLanguage('Boards');

	// For performance, track the latest post while going through the boards.
	if (!empty($boardIndexOptions['set_latest_post']))
		$latest_post = array(
			'timestamp' => 0,
			'ref' => 0,
		);

	// Find all boards and categories, as well as related information. This will be sorted by the natural order of boards and categories, which we control.
	$result_boards = wesql::query('
		SELECT' . ($boardIndexOptions['include_categories'] ? '
			c.id_cat, c.name AS cat_name, c.cat_order,' : '') . '
			b.id_board, b.name AS board_name, b.description,
			CASE WHEN b.redirect != {empty} THEN 1 ELSE 0 END AS is_redirect, b.redirect_newtab,
			b.num_posts, b.num_topics, b.unapproved_posts, b.unapproved_topics, b.id_parent, b.language,
			IFNULL(m.poster_time, 0) AS poster_time, IFNULL(mem.member_name, m.poster_name) AS poster_name,
			m.subject, m.id_topic, IFNULL(mem.real_name, m.poster_name) AS real_name, b.offlimits_msg,' . ($boardIndexOptions['include_categories'] ? '
			c.can_collapse, IFNULL(cc.id_member, 0) AS is_collapsed,' : '') . '
			IFNULL(mem.id_member, 0) AS id_member, m.id_msg, t.id_topic AS topic_exists,
			IFNULL(mods_mem.id_member, 0) AS id_moderator, mods_mem.real_name AS mod_real_name
		FROM {db_prefix}boards AS b' . ($boardIndexOptions['include_categories'] ? '
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' : '') . '
			LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = b.id_last_msg)
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic) AND {query_see_topic}
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . ($boardIndexOptions['include_categories'] ? '
			LEFT JOIN {db_prefix}collapsed_categories AS cc ON (cc.id_cat = c.id_cat AND cc.id_member = {int:current_member})' : '') . '
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board)
			LEFT JOIN {db_prefix}members AS mods_mem ON (mods_mem.id_member = mods.id_member)
		WHERE {query_list_board}' . (empty($boardIndexOptions['category']) ? '' : '
			AND b.id_cat = {int:category}') . (empty($boardIndexOptions['countChildPosts']) ? (empty($boardIndexOptions['base_level']) ? '' : '
			AND b.child_level >= {int:child_level}') : '
			AND b.child_level BETWEEN ' . $boardIndexOptions['base_level'] . ' AND ' . ($boardIndexOptions['base_level'] + 1)) . (empty($boardIndexOptions['category']) ? '' : '
			ORDER BY b.board_order'),
		array(
			'current_member' => MID,
			'child_level' => $boardIndexOptions['base_level'],
			'category' => $boardIndexOptions['category'],
		)
	);

	// Start with an empty array.
	if ($boardIndexOptions['include_categories'])
		$categories = array();
	else
		$this_category = array();

	$current_time = time();
	$context['board_ids'] = array();

	// Run through the categories and boards (or only boards)....
	while ($row_board = wesql::fetch_assoc($result_boards))
	{
		// If our Last Message in this particular board isn't visible to us, we should recalculate it.
		// This has a small cost, but it's better than hurting people by saying they can't read something.
		if (empty($row_board['topic_exists']) && $row_board['num_topics'] > 0)
		{
			$visible_board = wesql::query_get('
				SELECT
					IFNULL(m.poster_time, 0) AS poster_time, IFNULL(mem.member_name, m.poster_name) AS poster_name,
					m.subject, m.id_topic, IFNULL(mem.real_name, m.poster_name) AS real_name,
					IFNULL(mem.id_member, 0) AS id_member, m.id_msg
				FROM {db_prefix}boards AS b
					INNER JOIN {db_prefix}topics AS t ON (t.id_board = b.id_board) AND {query_see_topic}
					LEFT JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_last_msg)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE b.id_board = {int:board}
				ORDER BY m.id_msg DESC
				LIMIT 1',
				array(
					'board' => $row_board['id_board'],
				)
			);
			// If we have no visible topics in this board, it probably means it's a secret board we forgot to hide. Just skip it, then!
			// !! Should we insist on showing it, though..?
			if (empty($visible_board))
				continue;
			$row_board = array_merge($row_board, $visible_board);
		}

		// Perhaps we are ignoring this board?
		$ignoreThisBoard = in_array($row_board['id_board'], we::$user['ignoreboards']);

		$board_time = $row_board['poster_time'];
		$posted_today = $row_board['poster_name'] !== '' && (we::$is_guest ? $current_time - $board_time < 24 * 3600 : $row_board['id_msg'] > $_SESSION['id_msg_last_visit']);
		$context['board_ids'][$row_board['id_board']] = $row_board['id_board'];

		if ($boardIndexOptions['include_categories'])
		{
			// Haven't set this category yet.
			if (empty($categories[$row_board['id_cat']]))
			{
				$categories[$row_board['id_cat']] = array(
					'id' => $row_board['id_cat'],
					'name' => $row_board['cat_name'],
					'cat_order' => $row_board['cat_order'],
					'is_collapsed' => isset($row_board['can_collapse']) && $row_board['can_collapse'] == 1 && $row_board['is_collapsed'] > 0,
					'can_collapse' => (isset($row_board['can_collapse']) && $row_board['can_collapse'] == 1) && empty($_GET['category']),
					'collapse_href' => isset($row_board['can_collapse']) ? '<URL>?action=collapse;c=' . $row_board['id_cat'] . ';sa=' . ($row_board['is_collapsed'] > 0 ? 'expand;' : 'collapse;') . $context['session_query'] . '#category_' . $row_board['id_cat'] : '',
					'collapse_image' => isset($row_board['can_collapse']) ? '<div class="catfold' . ($row_board['is_collapsed'] > 0 ? '' : ' fold') . '"></div>' : '',
					'href' => '<URL>?category=' . $row_board['id_cat'],
					'boards' => array(),
					'new' => false,
				);
				$categories[$row_board['id_cat']]['link'] = $row_board['cat_name'];
			}

			// If this board has new posts in it (and isn't the recycle bin!), then the category is 'new'.
			if (empty($settings['recycle_enable']) || $settings['recycle_board'] != $row_board['id_board'])
				$categories[$row_board['id_cat']]['new'] |= $posted_today;

			// Avoid showing category unread link where it only has redirection boards.
			$categories[$row_board['id_cat']]['show_unread'] = !empty($categories[$row_board['id_cat']]['show_unread']) ? 1 : !$row_board['is_redirect'];

			// Collapsed category - don't do any of this.
			if ($categories[$row_board['id_cat']]['is_collapsed'] && empty($boardIndexOptions['category']))
				continue;
			else
				$categories[$row_board['id_cat']]['is_collapsed'] = false;

			// Let's save some typing. Climbing the array might be slower, anyhow.
			$this_category =& $categories[$row_board['id_cat']]['boards'];
		}

		// This is a parent board.
		if ($row_board['id_parent'] == $boardIndexOptions['parent_id'])
		{
			// Is this a new board, or just another moderator?
			if (!isset($this_category[$row_board['id_board']]))
			{
				// Not a child.
				$isChild = false;

				$this_category[$row_board['id_board']] = array(
					'id' => $row_board['id_board'],
					'name' => $row_board['board_name'],
					'description' => $row_board['description'],
					'moderators' => array(),
					'link_moderators' => array(),
					'children' => array(),
					'link_children' => array(),
					'children_new' => false,
					'topics' => $row_board['num_topics'],
					'posts' => $row_board['num_posts'],
					'is_redirect' => $row_board['is_redirect'],
					'redirect_newtab' => $row_board['is_redirect'] ? $row_board['redirect_newtab'] : 0,
					'unapproved_topics' => $row_board['unapproved_topics'],
					'unapproved_posts' => $row_board['unapproved_posts'] - $row_board['unapproved_topics'],
					'display' => $row_board['is_redirect'] ? array('posts' => 'num_redirects') : array('posts' => 'num_posts', 'topics' => 'num_topics'),
					'can_approve_posts' => !empty(we::$user['mod_cache']['ap']) && (we::$user['mod_cache']['ap'] == array(0) || in_array($row_board['id_board'], we::$user['mod_cache']['ap'])),
					'href' => '<URL>?board=' . $row_board['id_board'] . '.0',
					'link' => '<a href="<URL>?board=' . $row_board['id_board'] . '.0">' . $row_board['board_name'] . '</a>',
					'language' => $row_board['language'],
					'new' => $posted_today,
				);
			}
			if (!empty($row_board['id_moderator']))
			{
				$this_category[$row_board['id_board']]['moderators'][$row_board['id_moderator']] = array(
					'id' => $row_board['id_moderator'],
					'name' => $row_board['mod_real_name'],
					'href' => '<URL>?action=profile;u=' . $row_board['id_moderator'],
					'link' => '<a href="<URL>?action=profile;u=' . $row_board['id_moderator'] . '" title="' . $txt['board_moderator'] . '">' . $row_board['mod_real_name'] . '</a>'
				);
				$this_category[$row_board['id_board']]['link_moderators'][] = '<a href="<URL>?action=profile;u=' . $row_board['id_moderator'] . '" title="' . $txt['board_moderator'] . '">' . $row_board['mod_real_name'] . '</a>';
			}
		}
		// Found a child board.... make sure we've found its parent and the child hasn't been set already.
		elseif (isset($this_category[$row_board['id_parent']]['children']) && !isset($this_category[$row_board['id_parent']]['children'][$row_board['id_board']]))
		{
			// A valid child!
			$isChild = true;

			$this_category[$row_board['id_parent']]['children'][$row_board['id_board']] = array(
				'id' => $row_board['id_board'],
				'name' => $row_board['board_name'],
				'description' => $row_board['description'],
				'topics' => $row_board['num_topics'],
				'posts' => $row_board['num_posts'],
				'is_redirect' => $row_board['is_redirect'],
				'unapproved_topics' => $row_board['unapproved_topics'],
				'unapproved_posts' => $row_board['unapproved_posts'] - $row_board['unapproved_topics'],
				'can_approve_posts' => !empty(we::$user['mod_cache']['ap']) && (we::$user['mod_cache']['ap'] == array(0) || in_array($row_board['id_board'], we::$user['mod_cache']['ap'])),
				'href' => '<URL>?board=' . $row_board['id_board'] . '.0',
				'link' => '<a href="<URL>?board=' . $row_board['id_board'] . '.0">' . $row_board['board_name'] . '</a>',
				'new' => $posted_today,
			);

			// Counting child board posts is... slow. :-/
			if (!empty($boardIndexOptions['countChildPosts']) && !$row_board['is_redirect'])
			{
				$this_category[$row_board['id_parent']]['posts'] += $row_board['num_posts'];
				$this_category[$row_board['id_parent']]['topics'] += $row_board['num_topics'];
			}

			// Does this board contain new boards?
			$this_category[$row_board['id_parent']]['children_new'] |= $posted_today;

			// This is easier to use in many cases for the theme....
			$this_category[$row_board['id_parent']]['link_children'][] =& $this_category[$row_board['id_parent']]['children'][$row_board['id_board']]['link'];
		}
		// Child of a child... just add it on...
		elseif (!empty($boardIndexOptions['countChildPosts']))
		{
			if (!isset($parent_map))
				$parent_map = array();

			if (!isset($parent_map[$row_board['id_parent']]))
				foreach ($this_category as $id => $board)
				{
					if (!isset($board['children'][$row_board['id_parent']]))
						continue;

					$parent_map[$row_board['id_parent']] = array(&$this_category[$id], &$this_category[$id]['children'][$row_board['id_parent']]);
					$parent_map[$row_board['id_board']] = array(&$this_category[$id], &$this_category[$id]['children'][$row_board['id_parent']]);

					break;
				}

			if (isset($parent_map[$row_board['id_parent']]) && !$row_board['is_redirect'])
			{
				$parent_map[$row_board['id_parent']][0]['posts'] += $row_board['num_posts'];
				$parent_map[$row_board['id_parent']][0]['topics'] += $row_board['num_topics'];
				$parent_map[$row_board['id_parent']][1]['posts'] += $row_board['num_posts'];
				$parent_map[$row_board['id_parent']][1]['topics'] += $row_board['num_topics'];

				continue;
			}

			continue;
		}
		// Found a child of a child - skip.
		else
			continue;

		// Prepare the subject, and make sure it's not too long.
		censorText($row_board['subject']);
		$row_board['short_subject'] = shorten_subject($row_board['subject'], 24);
		$this_last_post = array(
			'id' => $row_board['id_msg'],
			'on_time' => $row_board['poster_time'] > 0 ? on_timeformat($row_board['poster_time']) : $txt['not_applicable'],
			'timestamp' => $board_time,
			'subject' => $row_board['short_subject'],
			'member' => array(
				'id' => $row_board['id_member'],
				'username' => $row_board['poster_name'] != '' ? $row_board['poster_name'] : $txt['not_applicable'],
				'name' => $row_board['real_name'],
				'href' => $row_board['poster_name'] != '' && !empty($row_board['id_member']) ? '<URL>?action=profile;u=' . $row_board['id_member'] : '',
				'link' => $row_board['poster_name'] != '' ? (!empty($row_board['id_member']) ? '<a href="<URL>?action=profile;u=' . $row_board['id_member'] . '">' . $row_board['real_name'] . '</a>' : $row_board['real_name']) : $txt['not_applicable'],
			),
			'start' => 'new',
			'topic' => $row_board['id_topic']
		);

		// Provide the href and link. Except if we can't actually enter this board, or it doesn't have a subject.
		if (!we::$is_admin && !in_array($row_board['id_board'], we::$user['qsb_boards']))
		{
			$row_board['poster_time'] = 0; // This should not be considered for 'latest'.
			$this_last_post['offlimits'] = !empty($row_board['offlimits_msg']) ? $row_board['offlimits_msg'] : $txt['board_off_limits'];
		}
		elseif ($row_board['subject'] != '')
		{
			$this_last_post['href'] = '<URL>?topic=' . $row_board['id_topic'] . '.new#new';
			$this_last_post['link'] = '<a href="' . $this_last_post['href'] . '" title="' . $row_board['subject'] . '">' . $row_board['short_subject'] . '</a>';
		}
		else
		{
			$this_last_post['href'] = '';
			$this_last_post['link'] = $txt['not_applicable'];
		}

		// Set the last post in the parent board.
		if ($row_board['id_parent'] == $boardIndexOptions['parent_id'] || ($isChild && !empty($row_board['poster_time']) && $this_category[$row_board['id_parent']]['last_post']['timestamp'] < $board_time))
			$this_category[$isChild ? $row_board['id_parent'] : $row_board['id_board']]['last_post'] = $this_last_post;

		// Just in the child...?
		if ($isChild)
			$this_category[$row_board['id_parent']]['children'][$row_board['id_board']]['last_post'] = $this_last_post;

		// Determine a global most recent topic.
		if (!empty($boardIndexOptions['set_latest_post']) && !empty($row_board['poster_time']) && $row_board['poster_time'] > $latest_post['timestamp'] && !$ignoreThisBoard)
			$latest_post = array(
				'timestamp' => $row_board['poster_time'],
				'ref' => &$this_category[$isChild ? $row_board['id_parent'] : $row_board['id_board']]['last_post'],
			);
	}
	wesql::free_result($result_boards);

	// By now we should know the most recent post... If we wanna know it, that is.
	if (!empty($boardIndexOptions['set_latest_post']) && !empty($latest_post['ref']))
		$context['latest_post'] = $latest_post['ref'];

	// If we're 'including categories', that means we're on the board index.
	// And THAT means Moderation Center goodness.
	if ($boardIndexOptions['include_categories'] && empty($boardIndexOptions['category']))
	{
		$user_group_key = we::$user['groups'];
		sort($user_group_key);
		$user_group_key = implode(',', $user_group_key);

		$thiscat['mod'] = array(
			'id' => 'mod',
			'name' => $txt['moderation_cat'],
			'is_collapsed' => false,
			'can_collapse' => false,
			'href' => '<URL>?action=moderate',
			'link' => $txt['moderate'],
			'new' => false,
			'show_unread' => false,
			'boards' => array(),
			'hide_rss' => true,
		);

		// First, reported posts.
		if (we::$user['mod_cache']['bq'] != '0=1')
		{
			$thiscat['mod']['boards']['reported'] = array(
				'new' => false,
				'id' => 'reported',
				'name' => $txt['reported_posts'],
				'description' => '',
				'open' => $context['open_mod_reports'],
				'closed' => !empty($context['closed_mod_reports']) ? $context['closed_mod_reports'] : 0,
				'display' => array('open' => 'reported_open', 'closed' => 'reported_closed'),
				'is_redirect' => true,
				'redirect_newtab' => false,
				'custom_class' => 'age-old',
				'href' => '<URL>?action=moderate;area=reports',
				'link' => '<a href="<URL>?action=moderate;area=reports">' . $txt['reported_posts'] . '</a>',
				'language' => '',
			);
			// Lastly, we need to set the last post. I realise it's not actually 'off limits' but that's the mechanism to reuse here.
			if (!empty($context['open_mod_reports']))
			{
				$reports = cache_quick_get('reported_boardindex-' . $user_group_key, 'ModerationCenter', 'cache_getBoardIndexReports', array());
				if (!empty($reports['id_report']))
				{
					// Now we *know* we have something to display, update the thing to be 'on' and set up displaying that.
					$repl = array(
						'{author}' => empty($reports['id_author']) ? $reports['author_name'] : '<a href="<URL>?action=profile;u=' . $reports['id_author'] . '">' . $reports['author_name'] . '</a>',
						'{link}' => '<a href="<URL>?action=moderate;area=reports;report=' . $reports['id_report'] . '">' . $reports['subject'] . '</a>',
						'{time}' => on_timeformat($reports['time_updated']),
					);
					$thiscat['mod']['boards']['reported']['last_post']['offlimits'] = strtr($txt['reported_board_desc'], $repl);
					$thiscat['mod']['boards']['reported']['new'] = true;
				}
			}
		}

		// Group requests
		if (!empty($settings['show_group_membership']) && we::$user['mod_cache']['gq'] != '0=1')
		{
			$thiscat['mod']['boards']['groups'] = array(
				'new' => false,
				'id' => 'groups',
				'name' => $txt['group_requests'],
				'description' => '',
				'open' => 0,
				'closed' => 0,
				'display' => array('open' => 'group_req_open', 'closed' => 'group_req_closed'),
				'is_redirect' => true,
				'redirect_newtab' => false,
				'custom_class' => 'age-old',
				'href' => '<URL>?action=moderate;area=groups;sa=requests',
				'link' => '<a href="<URL>?action=moderate;area=groups;sa=requests">' . $txt['group_requests'] . '</a>',
				'language' => '',
			);
			// Lastly, we need to set the last post. I realize it's not actually 'off limits' but that's the mechanism to reuse here.
			$requests = cache_quick_get('groupreq_boardindex-' . $user_group_key, 'ModerationCenter', 'cache_getBoardIndexGroupReq', array());
			if (!empty($requests['id_request']))
			{
				$repl = array(
					'{author}' => '<a href="<URL>?action=profile;u=' . $requests['id_member'] . '">' . $requests['member_name'] . '</a>',
					'{groupname}' => '<a href="<URL>?action=groups;sa=requests;gid=' . $requests['id_group'] . '" class="group' . $requests['id_group'] . '">' . $requests['group_name'] . '</a>',
					'{time}' => on_timeformat($requests['time_applied']),
				);
				$thiscat['mod']['boards']['groups']['last_post']['offlimits'] = strtr($txt['group_request_desc'], $repl);
			}
		}

		// Logs
		if (we::$user['mod_cache']['bq'] != '0=1' || allowedTo('issue_warning') || allowedTo('admin_forum'))
		{
			$thiscat['mod']['boards']['logs'] = array(
				'new' => false,
				'id' => 'logs',
				'name' => $txt['logs_board'],
				'description' => '',
				'open' => 0,
				'closed' => 0,
				'display' => array(),
				'is_redirect' => true,
				'redirect_newtab' => false,
				'custom_class' => 'age-old',
				'href' => '<URL>?action=moderate',
				'link' => '<a href="<URL>?action=moderate">' . $txt['logs_board'] . '</a>',
				'language' => '',
			);

			if (we::$user['mod_cache']['bq'] != '0=1')
				$thiscat['mod']['boards']['logs']['children']['modlog'] = array(
					'new' => false,
					'id' => 'modlog',
					'name' => $txt['logs_moderation'],
					'description' => '',
					'display' => array(),
					'is_redirect' => true,
					'redirect_newtab' => false,
					'custom_class' => 'age-old',
					'href' => '<URL>?action=moderate;area=modlog',
					'link' => '<a href="<URL>?action=moderate">' . $txt['logs_moderation'] . '</a>',
					'language' => '',
				);

			if (allowedTo('issue_warning'))
				$thiscat['mod']['boards']['logs']['children']['inf'] = array(
					'new' => false,
					'id' => 'inflog',
					'name' => $txt['logs_infractions'],
					'description' => '',
					'display' => array(),
					'is_redirect' => true,
					'redirect_newtab' => false,
					'custom_class' => 'age-old',
					'href' => '<URL>?action=moderate;area=warnings;sa=log',
					'link' => '<a href="<URL>?action=moderate;area=warnings;sa=log">' . $txt['logs_infractions'] . '</a>',
					'language' => '',
				);

			if (allowedTo('admin_forum') && !empty($settings['enableErrorLogging']))
			{
				$err = !empty($settings['app_error_count']) ? (int) $settings['app_error_count'] : 0;
				$thiscat['mod']['boards']['logs']['children']['err'] = array(
					'new' => !empty($err),
					'id' => 'errlog',
					'name' => $txt['errlog'],
					'description' => '',
					'err_count' => $err,
					'display' => array('err_count' => 'error_log_count'),
					'is_redirect' => true,
					'redirect_newtab' => false,
					'href' => '<URL>?action=admin;area=logs;sa=errorlog',
					'link' => '<a href="<URL>?action=admin;area=logs;sa=errorlog">' . $txt['errlog'] . '</a>',
					'language' => '',
				);
			}
		}

		// Were there any?
		if (!empty($thiscat['mod']['boards']))
		{
			$key = -1;
			$after = true;

			if (isset($settings['modcenter_category']) && $settings['modcenter_category'] != -1)
				foreach ($categories as $id_cat => $cat)
					if ($cat['cat_order'] >= $settings['modcenter_category'])
					{
						$key = $id_cat;
						break;
					}

			// Ooops, we didn't find it?
			if ($key == -1)
			{
				$cats = array_keys($categories);
				$key = !empty($cats) ? $cats[0] : 0;
				$after = false;
			}

			$categories = array_insert($categories, $key, $thiscat, $after);
		}
	}

	$params = array(&$boardIndexOptions);
	if ($boardIndexOptions['include_categories'])
		$params[] =& $categories;
	else
		$params[] =& $this_category;
	call_hook('get_boardindex', $params);

	return $boardIndexOptions['include_categories'] ? $categories : $this_category;
}
