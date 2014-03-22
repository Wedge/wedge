<?php
/**
 * Prepares the listing of topics in a given forum or blog board, as well as any sub-boards.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function MessageIndex()
{
	global $context, $txt, $board, $settings, $options, $board_info;

	// If this is a redirection board head off.
	if ($board_info['redirect'])
	{
		wesql::query('
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + 1
			WHERE id_board = {int:current_board}',
			array(
				'current_board' => $board,
			)
		);

		redirectexit($board_info['redirect']);
	}

	loadTemplate('MessageIndex');

	// Did someone save a conventional draft of a new topic? If so, add it as a block but make sure we don't screw other things up.
	$templates = array();
	if (isset($_GET['draftsaved']))
	{
		loadLanguage('Post');
		$templates[] = 'messageindex_draft';
	}
	$templates[] = $board_info['type'] == 'blog' ? 'main_blog' : 'main_board';
	wetem::load($templates);
	wetem::add('sidebar', 'messageindex_statistics');

	$context['name'] = $board_info['name'];
	$context['description'] = $board_info['description'];
	if (!empty($context['description']))
		$context['meta_description'] = westr::cut(strip_tags($context['description']), 160); // The description allows for raw HTML.

	// How many topics do we have in total?
	$board_info['total_topics'] = allowedTo('approve_posts') ? $board_info['num_topics'] + $board_info['unapproved_topics'] : $board_info['num_topics'] + $board_info['unapproved_user_topics'];

	// View all the topics, or just a few?
	if ($board_info['type'] == 'blog')
		$context['topics_per_page'] = 8;
	else
		$context['topics_per_page'] = empty($settings['disableCustomPerPage']) && !empty($options['topics_per_page']) ? $options['topics_per_page'] : $settings['defaultMaxTopics'];
	$context['messages_per_page'] = empty($settings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $settings['defaultMaxMessages'];
	$maxindex = isset($_REQUEST['all']) && !empty($settings['enableAllMessages']) ? $board_info['total_topics'] : $context['topics_per_page'];

	// Right, let's only index normal stuff!
	if (count($_GET) > 1)
		foreach ($_GET as $k => $v)
			if (!in_array($k, array('board', 'start', session_name())))
				$context['robot_no_index'] = true;

	if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % $context['messages_per_page'] != 0))
		$context['robot_no_index'] = true;

	// If we can view unapproved messages and there are some build up a list.
	if (allowedTo('approve_posts') && ($board_info['unapproved_topics'] || $board_info['unapproved_posts']))
	{
		$untopics = $board_info['unapproved_topics'] ? '<a href="<URL>?action=moderate;area=postmod;sa=topics;brd=' . $board . '">' . $board_info['unapproved_topics'] . '</a>' : 0;
		$unposts = $board_info['unapproved_posts'] ? '<a href="<URL>?action=moderate;area=postmod;sa=posts;brd=' . $board . '">' . ($board_info['unapproved_posts'] - $board_info['unapproved_topics']) . '</a>' : 0;
		$context['unapproved_posts_message'] = sprintf($txt['there_are_unapproved_topics'], $untopics, $unposts, '<URL>?action=moderate;area=postmod;sa=' . ($board_info['unapproved_topics'] ? 'topics' : 'posts') . ';brd=' . $board);
	}

	// Make sure the starting place makes sense and construct the page index.
	$context['start'] = (int) $_REQUEST['start'];
	$context['page_index'] = template_page_index('<URL>?board=' . $board . '.%1$d' . (isset($_REQUEST['sort']) ? ';sort=' . $_REQUEST['sort'] : '') . (isset($_REQUEST['desc']) ? ';desc' : ''), $context['start'], $board_info['total_topics'], $maxindex, true);

	// Set a canonical URL for this page.
	$context['canonical_url'] = '<URL>?board=' . $board . '.' . $context['start'];

	$context['links'] = array(
		'prev' => $context['start'] >= $context['topics_per_page'] ? '<URL>?board=' . $board . '.' . ($context['start'] - $context['topics_per_page']) : '',
		'next' => $context['start'] + $context['topics_per_page'] < $board_info['total_topics'] ? '<URL>?board=' . $board . '.' . ($context['start'] + $context['topics_per_page']) : '',
	);

	if (isset($_REQUEST['all']) && !empty($settings['enableAllMessages']) && $maxindex > $settings['enableAllMessages'])
	{
		$maxindex = $settings['enableAllMessages'];
		$context['start'] = $_REQUEST['start'] = 0;
	}

	// Build a list of the board's moderators.
	$context['moderators'] =& $board_info['moderators'];
	$context['link_moderators'] = array();
	if (!empty($board_info['moderators']))
	{
		wetem::add('sidebar', 'messageindex_staff');
		foreach ($board_info['moderators'] as $mod)
			$context['link_moderators'][] = '<a href="<URL>?action=profile;u=' . $mod['id'] . '" title="' . $txt['board_moderator'] . '">' . $mod['name'] . '</a>';
	}

	// Mark current and parent boards as seen.
	if (we::$is_member)
	{
		// We can't know they read it if we allow prefetches.
		preventPrefetch();

		wesql::insert('replace',
			'{db_prefix}log_boards',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
			array($settings['maxMsgID'], MID, $board)
		);

		if (!empty($board_info['parent_boards']))
		{
			wesql::query('
				UPDATE {db_prefix}log_boards
				SET id_msg = {int:id_msg}
				WHERE id_member = {int:current_member}
					AND id_board IN ({array_int:board_list})',
				array(
					'current_member' => MID,
					'board_list' => array_keys($board_info['parent_boards']),
					'id_msg' => $settings['maxMsgID'],
				)
			);

			// We've seen all these boards now!
			foreach ($board_info['parent_boards'] as $k => $dummy)
				if (isset($_SESSION['seen_cache'][$k]))
					unset($_SESSION['seen_cache'][$k]);
		}

		if (isset($_SESSION['seen_cache'][$board]))
			unset($_SESSION['seen_cache'][$board]);

		$request = wesql::query('
			SELECT sent
			FROM {db_prefix}log_notify
			WHERE id_board = {int:current_board}
				AND id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_board' => $board,
				'current_member' => MID,
			)
		);
		$context['is_marked_notify'] = wesql::num_rows($request) != 0;
		if ($context['is_marked_notify'])
		{
			list ($sent) = wesql::fetch_row($request);
			if (!empty($sent))
			{
				wesql::query('
					UPDATE {db_prefix}log_notify
					SET sent = {int:is_sent}
					WHERE id_board = {int:current_board}
						AND id_member = {int:current_member}',
					array(
						'current_board' => $board,
						'current_member' => MID,
						'is_sent' => 0,
					)
				);
			}
		}
		wesql::free_result($request);
	}
	else
		$context['is_marked_notify'] = false;

	// 'Print' the header and board info.
	$context['page_title'] = strip_tags($board_info['name']);

	// Set the variables up for the template.
	$context['owns_board'] = we::$is_admin || (we::$is_member && $board_info['owner_id'] == MID);
	$context['can_mark_notify'] = allowedTo('mark_notify') && we::$is_member;
	$context['can_post_new'] = allowedTo('post_new');
	$context['can_post_poll'] = allowedTo('poll_post') && $context['can_post_new'];
	$context['can_moderate_members'] = allowedTo('moderate_forum');
	$context['can_moderate_board'] = allowedTo('moderate_board');
	$context['can_approve_posts'] = allowedTo('approve_posts');

	loadSource('Subs-BoardIndex');
	$boardIndexOptions = array(
		'include_categories' => false,
		'base_level' => $board_info['child_level'] + 1,
		'parent_id' => $board_info['id'],
		'category' => 0,
		'set_latest_post' => false,
		'countChildPosts' => !empty($settings['countChildPosts']),
	);
	$context['boards'] = getBoardIndex($boardIndexOptions);

	// Nosey, nosey - who's viewing this topic?
	if (!empty($settings['display_who_viewing']))
	{
		loadSource('Subs-MembersOnline');
		getMembersOnlineDetails('board');
		wetem::add('sidebar', 'messageindex_whoviewing');
	}

	// Default sort methods.
	$sort_methods = array(
		'subject' => 'mf.subject',
		'starter' => 'IFNULL(memf.real_name, mf.poster_name)',
		'last_poster' => 'IFNULL(meml.real_name, ml.poster_name)',
		'replies' => 't.num_replies',
		'views' => 't.num_views',
		'first_post' => 't.id_topic',
		'last_post' => 't.id_last_msg'
	);

	// So, what ordering are we going with? Are we, first of all, forcing the board to bend to our will?
	if ($board_info['sort_override'] == 'force_desc' || $board_info['sort_override'] == 'force_asc')
	{
		$context['sort_by'] = $board_info['sort_method'];
		$_REQUEST['sort'] = $sort_methods[$board_info['sort_method']];
		$ascending = $board_info['sort_override'] === 'force_asc';
		$context['can_reorder'] = false;
	}
	// So the user *could*, but they didn't this time around. Or they were naughty.
	elseif (!isset($_REQUEST['sort']) || !isset($sort_methods[$_REQUEST['sort']]))
	{
		$context['sort_by'] = $board_info['sort_method'];
		$_REQUEST['sort'] = $sort_methods[$board_info['sort_method']];
		$ascending = $board_info['sort_override'] === 'natural_asc' || isset($_REQUEST['asc']);
		$context['can_reorder'] = true;
	}
	// The user did pick one, and we're cool with that.
	else
	{
		$context['sort_by'] = $_REQUEST['sort'];
		$_REQUEST['sort'] = $sort_methods[$_REQUEST['sort']];
		$ascending = !isset($_REQUEST['desc']);
		$context['can_reorder'] = true;
	}

	$context['sort_direction'] = $ascending ? 'up' : 'down';

	// Calculate the fastest way to get the topics.
	$start = $context['start'];
	if ($start > ($board_info['total_topics'] - 1) / 2)
	{
		$ascending = !$ascending;
		$fake_ascending = true;
		$maxindex = $board_info['total_topics'] < $start + $maxindex + 1 ? $board_info['total_topics'] - $start : $maxindex;
		$start = $board_info['total_topics'] < $start + $maxindex + 1 ? 0 : $board_info['total_topics'] - $start - $maxindex;
	}
	else
		$fake_ascending = false;

	$topic_ids = array();
	$context['topics'] = array();

	// Sequential pages are often not optimized, so we add an additional query.
	$pre_query = $start > 0;
	if ($pre_query && $maxindex > 0)
	{
		$request = wesql::query('
			SELECT t.id_topic
			FROM {db_prefix}topics AS t' . ($context['sort_by'] === 'last_poster' ? '
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)' : (in_array($context['sort_by'], array('starter', 'subject')) ? '
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)' : '')) . ($context['sort_by'] === 'starter' ? '
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)' : '') . ($context['sort_by'] === 'last_poster' ? '
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)' : '') . '
			WHERE t.id_board = {int:current_board}
				AND {query_see_topic}
			ORDER BY is_pinned' . ($fake_ascending ? '' : ' DESC') . ', ' . $_REQUEST['sort'] . ($ascending ? '' : ' DESC') . '
			LIMIT {int:start}, {int:maxindex}',
			array(
				'current_board' => $board,
				'current_member' => MID,
				'id_member_guest' => 0,
				'start' => $start,
				'maxindex' => $maxindex,
			)
		);
		$topic_ids = array();
		while ($row = wesql::fetch_assoc($request))
			$topic_ids[] = $row['id_topic'];
	}

	$can_reply_own = allowedTo('post_reply_own');
	$can_reply_any = allowedTo('post_reply_any');

	// Grab the appropriate topic information...
	if (!$pre_query || !empty($topic_ids))
	{
		// For search engine effectiveness we'll link guests differently.
		$context['pageindex_multiplier'] = empty($settings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $settings['defaultMaxMessages'];

		$result = wesql::query('
			SELECT
				t.id_topic, t.num_replies, t.locked, t.num_views, t.is_pinned, t.id_poll, t.id_previous_board,
				' . (we::$is_guest ? '0' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from,
				t.id_last_msg, t.approved, t.unapproved_posts, ml.poster_time AS last_poster_time,
				ml.id_msg_modified, ml.subject AS last_subject, ml.icon AS last_icon,
				ml.poster_name AS last_member_name, ml.id_member AS last_id_member,
				IFNULL(meml.real_name, ml.poster_name) AS last_display_name, t.id_first_msg,
				mf.poster_time AS first_poster_time, mf.subject AS first_subject, mf.icon AS first_icon,
				mf.poster_name AS first_member_name, mf.id_member AS first_id_member,
				IFNULL(memf.real_name, mf.poster_name) AS first_display_name, SUBSTRING(ml.body, 1, 385) AS last_body,
				{raw:first_body}, ml.smileys_enabled AS last_smileys, mf.smileys_enabled AS first_smileys
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)' . (we::$is_guest ? '' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})'). '
			WHERE ' . ($pre_query ? 't.id_topic IN ({array_int:topic_list})' : 't.id_board = {int:current_board}') . '
				AND {query_see_topic}
			ORDER BY ' . ($pre_query ? 'FIND_IN_SET(t.id_topic, {string:find_set_topics})' : ('is_pinned' . ($fake_ascending ? '' : ' DESC') . ', ') . $_REQUEST['sort'] . ($ascending ? '' : ' DESC')) . '
			LIMIT ' . ($pre_query ? '' : '{int:start}, ') . '{int:maxindex}',
			array(
				'first_body' => $board_info['type'] == 'blog' ? 'mf.body AS first_body' : 'SUBSTRING(mf.body, 1, 385) AS first_body',
				'current_board' => $board,
				'current_member' => MID,
				'topic_list' => $topic_ids,
				'find_set_topics' => implode(',', $topic_ids),
				'start' => $start,
				'maxindex' => $maxindex,
			)
		);

		// Begin 'printing' the message index for current board.
		$has_unread = array();
		while ($row = wesql::fetch_assoc($result))
		{
			if (!$pre_query)
				$topic_ids[] = $row['id_topic'];

			// If it's a blog board, we need to parse the first post fully for bbcode
			if ($board_info['type'] == 'blog')
			{
				// Censor the subject and message. Unlike elsewhere, here they are implicitly different (by design)
				censorText($row['first_subject']);
				censorText($row['first_body']);
				$row['first_body'] = parse_bbc($row['first_body'], 'post-preview', array('smileys' => $row['first_smileys'], 'cache' => $row['id_first_msg']));

				// Is the theme requesting previews? Better set up the last post for them too. Not likely, but hey.
				if (!empty($context['message_index_preview']))
				{
					censorText($row['last_subject']);
					censorText($row['last_body']);

					$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], 'post-preview', array('smileys' => $row['last_smileys'], 'cache' => $row['id_last_msg'])), array('<br>' => '&#10;')));
					if (westr::strlen($row['last_body']) > 128)
						$row['last_body'] = westr::substr($row['last_body'], 0, 128) . '...';
				}
				else
					$row['last_body'] = '';
			}
			// So it's a forum board, do they still want previews?
			elseif (!empty($context['message_index_preview']))
			{
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
					censorText($row['last_subject']);
					censorText($row['last_body']);
				}

				// Limit them to 128 characters
				if (westr::strlen($row['first_body']) > 128)
					$row['first_body'] = westr::substr($row['first_body'], 0, 128) . '...';
				$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], 'post-preview', array('smileys' => $row['first_smileys'], 'cache' => $row['id_first_msg'])), array('<br>' => '&#10;')));

				if (westr::strlen($row['last_body']) > 128)
					$row['last_body'] = westr::substr($row['last_body'], 0, 128) . '...';
				$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], 'post-preview', array('smileys' => $row['last_smileys'], 'cache' => $row['id_last_msg'])), array('<br>' => '&#10;')));
			}
			// Huh, guess not.
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
			if ($row['num_replies'] + 1 > $context['messages_per_page'])
			{
				$pages = '« ';

				// We can't pass start by reference.
				$start = -1;
				$pages .= template_page_index('<URL>?topic=' . $row['id_topic'] . '.%1$d', $start, $row['num_replies'] + 1, $context['messages_per_page'], true, false);

				// If we can use all, show all.
				if (!empty($settings['enableAllMessages']) && $row['num_replies'] + 1 < $settings['enableAllMessages'])
					$pages .= ' &nbsp;<a href="<URL>?topic=' . $row['id_topic'] . '.0;all">' . $txt['all_pages'] . '</a>';
				$pages .= ' »';
			}
			else
				$pages = '';

			$color_class = '';
			// Is this topic pending approval, or does it have any posts pending approval?
			if ($context['can_approve_posts'] && $row['unapproved_posts'])
				$color_class .= !$row['approved'] ? ' approvet' : ' approve';
			// Pinned topics should get a different color, too.
			if (!empty($row['is_pinned']))
				$color_class .= ' pinned';
			// Locked topics get special treatment as well.
			if (!empty($row['locked']))
				$color_class .= ' locked';
			// Does it have a poll..?
			if ($row['id_poll'] > 0)
				$color_class .= ' poll';

			// 'Print' the topic info.
			$context['topics'][$row['id_topic']] = array(
				'id' => $row['id_topic'],
				'first_post' => array(
					'id' => $row['id_first_msg'],
					'member' => array(
						'username' => $row['first_member_name'],
						'name' => $row['first_display_name'],
						'id' => $row['first_id_member'],
						'href' => !empty($row['first_id_member']) ? '<URL>?action=profile;u=' . $row['first_id_member'] : '',
						'link' => !empty($row['first_id_member']) ? '<a href="<URL>?action=profile;u=' . $row['first_id_member'] . '" title="' . $txt['view_profile'] . '">' . $row['first_display_name'] . '</a>' : $row['first_display_name']
					),
					'on_time' => on_timeformat($row['first_poster_time']),
					'timestamp' => forum_time(true, $row['first_poster_time']),
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
						'username' => $row['last_member_name'],
						'name' => $row['last_display_name'],
						'id' => $row['last_id_member'],
						'href' => !empty($row['last_id_member']) ? '<URL>?action=profile;u=' . $row['last_id_member'] : '',
						'link' => !empty($row['last_id_member']) ? '<a href="<URL>?action=profile;u=' . $row['last_id_member'] . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name']
					),
					'on_time' => on_timeformat($row['last_poster_time']),
					'timestamp' => forum_time(true, $row['last_poster_time']),
					'subject' => $row['last_subject'],
					'preview' => $row['last_body'],
					'icon' => $row['last_icon'],
					'icon_url' => ASSETS . '/post/' . $row['last_icon'] . '.gif',
					'href' => '<URL>?topic=' . $row['id_topic'] . (we::$is_guest ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg'] . '#new'))),
					'link' => '<a href="<URL>?topic=' . $row['id_topic'] . (we::$is_guest ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg'] . '#new'))) . '" ' . ($row['num_replies'] == 0 ? '' : 'rel="nofollow"') . '>' . $row['last_subject'] . '</a>'
				),
				'is_pinned' => !empty($row['is_pinned']),
				'is_locked' => !empty($row['locked']),
				'is_poll' => $row['id_poll'] > 0,
				'is_posted_in' => false,
				'icon' => $row['first_icon'],
				'icon_url' => ASSETS . '/post/' . $row['first_icon'] . '.gif',
				'subject' => $row['first_subject'],
				'new' => $row['new_from'] <= $row['id_msg_modified'],
				'new_from' => $row['new_from'],
				'new_href' => '<URL>?topic=' . $row['id_topic'] . ($row['new_from'] ? '.msg' . $row['new_from'] . '#new' : '.0'),
				'new_link' => '<a href="<URL>?topic=' . $row['id_topic'] . ($row['new_from'] ? '.msg' . $row['new_from'] . '#new' : '.0') . '">' . $row['first_subject'] . '</a>',
				'pages' => $pages,
				'replies' => $row['num_replies'],
				'views' => $row['num_views'],
				'approved' => $row['approved'],
				'unapproved_posts' => $row['unapproved_posts'],
				'can_reply' => !empty($row['locked']) ? $context['can_moderate_board'] : $can_reply_any || ($can_reply_own && $row['first_id_member'] == MID),
				'style' => $color_class,
			);

			if ($context['topics'][$row['id_topic']]['new'])
				$has_unread[] = $row['id_topic'];
		}
		wesql::free_result($result);

		$context['nb_new'] = get_unread_numbers($has_unread, true);

		// Fix the sequence of topics if they were retrieved in the wrong order. (for speed reasons...)
		if ($fake_ascending)
			$context['topics'] = array_reverse($context['topics'], true);

		if (!empty($settings['enableParticipation']) && we::$is_member && !empty($topic_ids))
		{
			$result = wesql::query('
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topic_list})
					AND id_member = {int:current_member}
				GROUP BY id_topic
				LIMIT ' . count($topic_ids),
				array(
					'current_member' => MID,
					'topic_list' => $topic_ids,
				)
			);
			while ($row = wesql::fetch_assoc($result))
			{
				$context['topics'][$row['id_topic']]['is_posted_in'] = true;
				$context['topics'][$row['id_topic']]['style'] .= ' my';
			}
			wesql::free_result($result);
		}
	}

	// Deal with quick moderation.
	if (!empty($context['topics']))
	{
		$quickmod = array();

		$context['can_lock'] = allowedTo('lock_any');
		$context['can_pin'] = allowedTo('pin_topic');
		$context['can_move'] = allowedTo('move_any');
		$context['can_remove'] = allowedTo('remove_any');
		$context['can_merge'] = allowedTo('merge_any');
		// Ignore approving own topics as it's unlikely to come up...
		$context['can_approve'] = $settings['postmod_active'] && allowedTo('approve_posts') && !empty($board_info['unapproved_topics']);
		// Can we restore topics?
		$context['can_restore'] = allowedTo('move_any') && !empty($settings['recycle_enable']) && $settings['recycle_board'] == $board;

		// Set permissions for all the topics.
		foreach ($context['topics'] as $t => $topic)
		{
			$started = $topic['first_post']['member']['id'] == MID;
			$context['topics'][$t]['quick_mod'] = array(
				'lock' => allowedTo('lock_any') || ($started && allowedTo('lock_own')),
				'pin' => allowedTo('pin_topic'),
				'move' => allowedTo('move_any') || ($started && allowedTo('move_own')),
				'modify' => allowedTo('modify_any') || ($started && allowedTo('modify_own')),
				'remove' => allowedTo('remove_any') || ($started && allowedTo('remove_own')),
				'approve' => $context['can_approve'] && $topic['unapproved_posts']
			);
			$context['can_lock'] |= ($started && allowedTo('lock_own'));
			$context['can_move'] |= ($started && allowedTo('move_own'));
			$context['can_remove'] |= ($started && allowedTo('remove_own'));
		}

		$context['can_markread'] = we::$is_member;
		foreach (array('remove', 'lock', 'pin', 'move', 'merge', 'restore', 'approve', 'markread') as $qmod)
			if (!empty($context['can_' . $qmod]))
				$quickmod[$qmod] = $txt['quick_mod_' . $qmod];

		call_hook('select_quickmod', array(&$quickmod));
		$context['quick_moderation'] = $quickmod;

		// Find the boards/categories they can move their topic to.
		if ($context['can_move'])
		{
			loadSource('Subs-MessageIndex');
			$boardListOptions = array(
				'excluded_boards' => array($board),
				'not_redirection' => true,
				'use_permissions' => true,
				'selected_board' => empty($_SESSION['move_to_topic']) ? null : $_SESSION['move_to_topic'],
			);
			$context['move_to_boards'] = getBoardList($boardListOptions);

			// Make the boards safe for display.
			foreach ($context['move_to_boards'] as $id_cat => $cat)
			{
				$context['move_to_boards'][$id_cat]['name'] = strip_tags($cat['name']);
				foreach ($cat['boards'] as $id_board => $brd)
					$context['move_to_boards'][$id_cat]['boards'][$id_board]['name'] = strip_tags($brd['name']);
			}

			// With no other boards to see, it's useless to move.
			if (empty($context['move_to_boards']))
				$context['can_move'] = false;
		}
	}

	// If there are children, but no topics and no ability to post topics...
	$context['no_topic_listing'] = !empty($context['boards']) && empty($context['topics']) && !$context['can_post_new'];
	if (!$context['no_topic_listing'])
		wetem::add('sidebar', 'messageindex_legend');

	// They can only mark read if they are logged in!
	$context['can_mark_read'] = we::$is_member;
	$context['can_order_pinned'] = allowedTo('pin_topic');
	if ($context['can_order_pinned'])
	{
		// We should check that we're on the first page and that there's something to reorder.
		// Fortunately this is the same test - we look at what we're displaying and check for pinnedness.
		$is_pinned = 0;
		if (!empty($context['topics']))
			foreach ($context['topics'] as $t => $topic)
				if ($topic['is_pinned'])
					$is_pinned++;
		$context['can_order_pinned'] = $is_pinned > 1;
	}

	// Create the button set...
	$context['button_list'] = array(
		'new_topic' => array('test' => 'can_post_new', 'text' => 'new_topic', 'url' => '<URL>?action=post;board=' . $context['current_board'] . '.0', 'class' => 'active'),
		'post_poll' => array('test' => 'can_post_poll', 'text' => 'new_poll', 'url' => '<URL>?action=post;board=' . $context['current_board'] . '.0;poll'),
		($context['is_marked_notify'] ? 'unnotify' : 'notify') => array('test' => 'can_mark_notify', 'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify', 'custom' => 'onclick="return ask(' . JavaScriptEscape($txt['notification_' . ($context['is_marked_notify'] ? 'disable_board' : 'enable_board')]) . ', e);"', 'url' => '<URL>?action=notifyboard;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';board=' . $context['current_board'] . '.' . $context['start'] . ';' . $context['session_query']),
		'markread' => array('test' => 'can_mark_read', 'text' => 'mark_read_short', 'url' => '<URL>?action=markasread;sa=board;board=' . $context['current_board'] . '.0;' . $context['session_query']),
		'order' => array('test' => 'can_order_pinned', 'text' => 'order_pinned_topic', 'url' => '<URL>?action=pin;sa=order;board=' . $context['current_board'] . '.0', 'class' => 'pin'),
		'modify' => array('test' => 'owns_board', 'text' => 'modify', 'url' => '<URL>?action=admin;area=manageboards;sa=board;boardid=' . $context['current_board'], 'class' => 'edit'),
	);

	// Allow adding new buttons easily.
	call_hook('messageindex_buttons');

	if (empty($settings['display_flags']))
		$settings['display_flags'] = 'none';
}
