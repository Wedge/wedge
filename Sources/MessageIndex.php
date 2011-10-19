<?php
/**
 * Wedge
 *
 * Prepares the listing of topics in a given forum or blog board, as well as any sub-boards.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function MessageIndex()
{
	global $txt, $scripturl, $board, $modSettings, $context;
	global $options, $settings, $board_info, $user_info;

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

	if (WIRELESS)
		wetem::load('wap2_messageindex');
	else
	{
		loadTemplate('MessageIndex');

		// Did someone save a conventional draft of a new topic? If so, add it as a block but make sure we don't screw other things up.
		$templates = array();
		if (isset($_GET['draftsaved']))
			$templates[] = 'messageindex_draft';
		$templates[] = $board_info['type'] == 'blog' ? 'main_blog' : 'main_board';
		wetem::load($templates);
		wetem::load('messageindex_statistics', 'sidebar');
	}

	$context['name'] = $board_info['name'];
	$context['description'] = $board_info['description'];
	if (!empty($context['description']))
		$context['meta_description'] = strip_tags($context['description']); // The description allows for raw HTML.

	// How many topics do we have in total?
	$board_info['total_topics'] = allowedTo('approve_posts') ? $board_info['num_topics'] + $board_info['unapproved_topics'] : $board_info['num_topics'] + $board_info['unapproved_user_topics'];

	// View all the topics, or just a few?
	if ($board_info['type'] == 'blog')
		$context['topics_per_page'] = 8;
	else
		$context['topics_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['topics_per_page']) && !WIRELESS ? $options['topics_per_page'] : $modSettings['defaultMaxTopics'];
	$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) && !WIRELESS ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];
	$maxindex = isset($_REQUEST['all']) && !empty($modSettings['enableAllMessages']) ? $board_info['total_topics'] : $context['topics_per_page'];

	// Right, let's only index normal stuff!
	if (count($_GET) > 1)
	{
		$session_name = session_name();
		foreach ($_GET as $k => $v)
			if (!in_array($k, array('board', 'start', $session_name)))
				$context['robot_no_index'] = true;
	}
	if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % $context['messages_per_page'] != 0))
		$context['robot_no_index'] = true;

	// If we can view unapproved messages and there are some build up a list.
	if (allowedTo('approve_posts') && ($board_info['unapproved_topics'] || $board_info['unapproved_posts']))
	{
		$untopics = $board_info['unapproved_topics'] ? '<a href="' . $scripturl . '?action=moderate;area=postmod;sa=topics;brd=' . $board . '">' . $board_info['unapproved_topics'] . '</a>' : 0;
		$unposts = $board_info['unapproved_posts'] ? '<a href="' . $scripturl . '?action=moderate;area=postmod;sa=posts;brd=' . $board . '">' . ($board_info['unapproved_posts'] - $board_info['unapproved_topics']) . '</a>' : 0;
		$context['unapproved_posts_message'] = sprintf($txt['there_are_unapproved_topics'], $untopics, $unposts, $scripturl . '?action=moderate;area=postmod;sa=' . ($board_info['unapproved_topics'] ? 'topics' : 'posts') . ';brd=' . $board);
	}

	// Make sure the starting place makes sense and construct the page index.
	$context['start'] = (int) $_REQUEST['start'];
	$context['page_index'] = constructPageIndex($scripturl . '?board=' . $board . '.%1$d' . (isset($_REQUEST['sort']) ? ';sort=' . $_REQUEST['sort'] : '') . (isset($_REQUEST['desc']) ? ';desc' : ''), $context['start'], $board_info['total_topics'], $maxindex, true);

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl . '?board=' . $board . '.' . $context['start'];

	$context['links'] = array(
		'first' => $context['start'] >= $context['topics_per_page'] ? $scripturl . '?board=' . $board . '.0' : '',
		'prev' => $context['start'] >= $context['topics_per_page'] ? $scripturl . '?board=' . $board . '.' . ($context['start'] - $context['topics_per_page']) : '',
		'next' => $context['start'] + $context['topics_per_page'] < $board_info['total_topics'] ? $scripturl . '?board=' . $board . '.' . ($context['start'] + $context['topics_per_page']) : '',
		'last' => $context['start'] + $context['topics_per_page'] < $board_info['total_topics'] ? $scripturl . '?board=' . $board . '.' . (floor(($board_info['total_topics'] - 1) / $context['topics_per_page']) * $context['topics_per_page']) : '',
		'up' => $board_info['parent'] == 0 ? $scripturl . '?' : $scripturl . '?board=' . $board_info['parent'] . '.0'
	);

	$context['page_info'] = array(
		'current_page' => $context['start'] / $context['topics_per_page'] + 1,
		'num_pages' => floor(($board_info['total_topics'] - 1) / $context['topics_per_page']) + 1
	);

	if (isset($_REQUEST['all']) && !empty($modSettings['enableAllMessages']) && $maxindex > $modSettings['enableAllMessages'])
	{
		$maxindex = $modSettings['enableAllMessages'];
		$context['start'] = $_REQUEST['start'] = 0;
	}

	// Build a list of the board's moderators.
	$context['moderators'] =& $board_info['moderators'];
	$context['link_moderators'] = array();
	if (!empty($board_info['moderators']))
	{
		foreach ($board_info['moderators'] as $mod)
			$context['link_moderators'][] ='<a href="' . $scripturl . '?action=profile;u=' . $mod['id'] . '" title="' . $txt['board_moderator'] . '">' . $mod['name'] . '</a>';

		$context['linktree'][count($context['linktree']) - 1]['extra_after'] = ' (' . (count($context['link_moderators']) == 1 ? $txt['moderator'] : $txt['moderators']) . ': ' . implode(', ', $context['link_moderators']) . ')';
	}

	// Mark current and parent boards as seen.
	if (!$user_info['is_guest'])
	{
		// We can't know they read it if we allow prefetches.
		if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
		{
			ob_end_clean();
			header('HTTP/1.1 403 Prefetch Forbidden');
			die;
		}

		wesql::insert('replace',
			'{db_prefix}log_boards',
			array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
			array($modSettings['maxMsgID'], $user_info['id'], $board),
			array('id_member', 'id_board')
		);

		if (!empty($board_info['parent_boards']))
		{
			wesql::query('
				UPDATE {db_prefix}log_boards
				SET id_msg = {int:id_msg}
				WHERE id_member = {int:current_member}
					AND id_board IN ({array_int:board_list})',
				array(
					'current_member' => $user_info['id'],
					'board_list' => array_keys($board_info['parent_boards']),
					'id_msg' => $modSettings['maxMsgID'],
				)
			);

			// We've seen all these boards now!
			foreach ($board_info['parent_boards'] as $k => $dummy)
				if (isset($_SESSION['topicseen_cache'][$k]))
					unset($_SESSION['topicseen_cache'][$k]);
		}

		if (isset($_SESSION['topicseen_cache'][$board]))
			unset($_SESSION['topicseen_cache'][$board]);

		$request = wesql::query('
			SELECT sent
			FROM {db_prefix}log_notify
			WHERE id_board = {int:current_board}
				AND id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_board' => $board,
				'current_member' => $user_info['id'],
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
						'current_member' => $user_info['id'],
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
	$context['can_mark_notify'] = allowedTo('mark_notify') && !$user_info['is_guest'];
	$context['can_post_new'] = allowedTo('post_new') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_topics'));
	$context['can_post_poll'] = $modSettings['pollMode'] == '1' && allowedTo('poll_post') && $context['can_post_new'];
	$context['can_moderate_forum'] = allowedTo('moderate_forum');
	$context['can_approve_posts'] = allowedTo('approve_posts');

	loadSource('Subs-BoardIndex');
	$boardIndexOptions = array(
		'include_categories' => false,
		'base_level' => $board_info['child_level'] + 1,
		'parent_id' => $board_info['id'],
		'category' => 0,
		'set_latest_post' => false,
		'countChildPosts' => !empty($modSettings['countChildPosts']),
	);
	$context['boards'] = getBoardIndex($boardIndexOptions);

	// Nosey, nosey - who's viewing this topic?
	if (!empty($settings['display_who_viewing']) && !WIRELESS)
	{
		loadSource('Subs-MembersOnline');
		getMembersOnlineDetails('board');
		wetem::load('messageindex_whoviewing', 'sidebar');
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

	// Setup the default topic icons...
	$stable_icons = stable_icons();
	$context['icon_sources'] = array();
	foreach ($stable_icons as $icon)
		$context['icon_sources'][$icon] = 'images_url';

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
			WHERE t.id_board = {int:current_board}' . (!$modSettings['postmod_active'] || $context['can_approve_posts'] ? '' : '
				AND (t.approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:current_member}') . ')') . '
			ORDER BY is_sticky' . ($fake_ascending ? '' : ' DESC') . ', ' . $_REQUEST['sort'] . ($ascending ? '' : ' DESC') . '
			LIMIT {int:start}, {int:maxindex}',
			array(
				'current_board' => $board,
				'current_member' => $user_info['id'],
				'is_approved' => 1,
				'id_member_guest' => 0,
				'start' => $start,
				'maxindex' => $maxindex,
			)
		);
		$topic_ids = array();
		while ($row = wesql::fetch_assoc($request))
			$topic_ids[] = $row['id_topic'];
	}

	// Grab the appropriate topic information...
	if (!$pre_query || !empty($topic_ids))
	{
		// For search engine effectiveness we'll link guests differently.
		$context['pageindex_multiplier'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) && !WIRELESS ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

		$result = wesql::query('
			SELECT
				t.id_topic, t.num_replies, t.locked, t.num_views, t.is_sticky, t.id_poll, t.id_previous_board,
				' . ($user_info['is_guest'] ? '0' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from,
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
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)' . ($user_info['is_guest'] ? '' : '
				LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})'). '
			WHERE ' . ($pre_query ? 't.id_topic IN ({array_int:topic_list})' : 't.id_board = {int:current_board}') . (!$modSettings['postmod_active'] || $context['can_approve_posts'] ? '' : '
				AND (t.approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR t.id_member_started = {int:current_member}') . ')') . '
			ORDER BY ' . ($pre_query ? 'FIND_IN_SET(t.id_topic, {string:find_set_topics})' : ('is_sticky' . ($fake_ascending ? '' : ' DESC') . ', ') . $_REQUEST['sort'] . ($ascending ? '' : ' DESC')) . '
			LIMIT ' . ($pre_query ? '' : '{int:start}, ') . '{int:maxindex}',
			array(
				'first_body' => $board_info['type'] == 'blog' ? 'mf.body AS first_body' : 'SUBSTRING(mf.body, 1, 385) AS first_body',
				'current_board' => $board,
				'current_member' => $user_info['id'],
				'topic_list' => $topic_ids,
				'is_approved' => 1,
				'find_set_topics' => implode(',', $topic_ids),
				'start' => $start,
				'maxindex' => $maxindex,
			)
		);

		// Begin 'printing' the message index for current board.
		while ($row = wesql::fetch_assoc($result))
		{
			if ($row['id_poll'] > 0 && $modSettings['pollMode'] == '0')
				continue;

			if (!$pre_query)
				$topic_ids[] = $row['id_topic'];

			// If it's a blog board, we need to parse the first post fully for bbcode
			if ($board_info['type'] == 'blog')
			{
				// Censor the subject and message. Unlike elsewhere, here they are implicitly different (by design)
				censorText($row['first_subject']);
				censorText($row['first_body']);
				$row['first_body'] = parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']);

				// Is the theme requesting previews? Better set up the last post for them too. Not likely, but hey.
				if (!empty($settings['message_index_preview']))
				{
					censorText($row['last_subject']);
					censorText($row['last_body']);

					$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br>' => '&#10;')));
					if (westr::strlen($row['last_body']) > 128)
						$row['last_body'] = westr::substr($row['last_body'], 0, 128) . '...';
				}
				else
					$row['last_body'] = '';
			}
			// So it's a forum board, do they still want previews?
			elseif (!empty($settings['message_index_preview']))
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
				$row['first_body'] = strip_tags(strtr(parse_bbc($row['first_body'], $row['first_smileys'], $row['id_first_msg']), array('<br>' => '&#10;')));

				if (westr::strlen($row['last_body']) > 128)
					$row['last_body'] = westr::substr($row['last_body'], 0, 128) . '...';
				$row['last_body'] = strip_tags(strtr(parse_bbc($row['last_body'], $row['last_smileys'], $row['id_last_msg']), array('<br>' => '&#10;')));
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
				$pages = '&#171; ';

				// We can't pass start by reference.
				$start = -1;
				$pages .= constructPageIndex($scripturl . '?topic=' . $row['id_topic'] . '.%1$d', $start, $row['num_replies'] + 1, $context['messages_per_page'], true, false);

				// If we can use all, show all.
				if (!empty($modSettings['enableAllMessages']) && $row['num_replies'] + 1 < $modSettings['enableAllMessages'])
					$pages .= ' &nbsp;<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0;all">' . $txt['all_pages'] . '</a>';
				$pages .= ' &#187;';
			}
			else
				$pages = '';

			// We need to check the topic icons exist...
			if (!empty($modSettings['messageIconChecks_enable']))
			{
				if (!isset($context['icon_sources'][$row['first_icon']]))
					$context['icon_sources'][$row['first_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['first_icon'] . '.gif') ? 'images_url' : 'default_images_url';
				if (!isset($context['icon_sources'][$row['last_icon']]))
					$context['icon_sources'][$row['last_icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['last_icon'] . '.gif') ? 'images_url' : 'default_images_url';
			}
			else
			{
				if (!isset($context['icon_sources'][$row['first_icon']]))
					$context['icon_sources'][$row['first_icon']] = 'images_url';
				if (!isset($context['icon_sources'][$row['last_icon']]))
					$context['icon_sources'][$row['last_icon']] = 'images_url';
			}

			// 'Print' the topic info.
			$context['topics'][$row['id_topic']] = array(
				'id' => $row['id_topic'],
				'first_post' => array(
					'id' => $row['id_first_msg'],
					'member' => array(
						'username' => $row['first_member_name'],
						'name' => $row['first_display_name'],
						'id' => $row['first_id_member'],
						'href' => !empty($row['first_id_member']) ? $scripturl . '?action=profile;u=' . $row['first_id_member'] : '',
						'link' => !empty($row['first_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['first_id_member'] . '" title="' . $txt['view_profile'] . '">' . $row['first_display_name'] . '</a>' : $row['first_display_name']
					),
					'time' => timeformat($row['first_poster_time']),
					'timestamp' => forum_time(true, $row['first_poster_time']),
					'subject' => $row['first_subject'],
					'preview' => $row['first_body'],
					'icon' => $row['first_icon'],
					'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.gif',
					'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['first_subject'] . '</a>'
				),
				'last_post' => array(
					'id' => $row['id_last_msg'],
					'member' => array(
						'username' => $row['last_member_name'],
						'name' => $row['last_display_name'],
						'id' => $row['last_id_member'],
						'href' => !empty($row['last_id_member']) ? $scripturl . '?action=profile;u=' . $row['last_id_member'] : '',
						'link' => !empty($row['last_id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['last_id_member'] . '">' . $row['last_display_name'] . '</a>' : $row['last_display_name']
					),
					'time' => on_timeformat($row['last_poster_time']),
					'timestamp' => forum_time(true, $row['last_poster_time']),
					'subject' => $row['last_subject'],
					'preview' => $row['last_body'],
					'icon' => $row['last_icon'],
					'icon_url' => $settings[$context['icon_sources'][$row['last_icon']]] . '/post/' . $row['last_icon'] . '.gif',
					'href' => $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')),
					'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . ($user_info['is_guest'] ? ('.' . (!empty($options['view_newest_first']) ? 0 : ((int) (($row['num_replies']) / $context['pageindex_multiplier'])) * $context['pageindex_multiplier']) . '#msg' . $row['id_last_msg']) : (($row['num_replies'] == 0 ? '.0' : '.msg' . $row['id_last_msg']) . '#new')) . '" ' . ($row['num_replies'] == 0 ? '' : 'rel="nofollow"') . '>' . $row['last_subject'] . '</a>'
				),
				'is_sticky' => !empty($row['is_sticky']),
				'is_locked' => !empty($row['locked']),
				'is_poll' => $modSettings['pollMode'] == '1' && $row['id_poll'] > 0,
				'is_posted_in' => false,
				'icon' => $row['first_icon'],
				'icon_url' => $settings[$context['icon_sources'][$row['first_icon']]] . '/post/' . $row['first_icon'] . '.gif',
				'subject' => $row['first_subject'],
				'new' => $row['new_from'] <= $row['id_msg_modified'],
				'new_from' => $row['new_from'],
				'newtime' => $row['new_from'],
				'new_href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
				'new_link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new">' . $row['first_subject'] . '</a>',
				'pages' => $pages,
				'replies' => comma_format($row['num_replies']),
				'views' => comma_format($row['num_views']),
				'approved' => $row['approved'],
				'unapproved_posts' => $row['unapproved_posts'],
			);
		}
		wesql::free_result($result);

		// Fix the sequence of topics if they were retrieved in the wrong order. (for speed reasons...)
		if ($fake_ascending)
			$context['topics'] = array_reverse($context['topics'], true);

		if (!empty($modSettings['enableParticipation']) && !$user_info['is_guest'] && !empty($topic_ids))
		{
			$result = wesql::query('
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_topic IN ({array_int:topic_list})
					AND id_member = {int:current_member}
				GROUP BY id_topic
				LIMIT ' . count($topic_ids),
				array(
					'current_member' => $user_info['id'],
					'topic_list' => $topic_ids,
				)
			);
			while ($row = wesql::fetch_assoc($result))
				$context['topics'][$row['id_topic']]['is_posted_in'] = true;
			wesql::free_result($result);
		}
	}

	// Is Quick Moderation active/needed?
	if (!empty($options['display_quick_mod']) && !empty($context['topics']))
	{
		$context['can_lock'] = allowedTo('lock_any');
		$context['can_sticky'] = allowedTo('make_sticky');
		$context['can_move'] = allowedTo('move_any');
		$context['can_remove'] = allowedTo('remove_any');
		$context['can_merge'] = allowedTo('merge_any');
		// Ignore approving own topics as it's unlikely to come up...
		$context['can_approve'] = $modSettings['postmod_active'] && allowedTo('approve_posts') && !empty($board_info['unapproved_topics']);
		// Can we restore topics?
		$context['can_restore'] = allowedTo('move_any') && !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board;

		// Set permissions for all the topics.
		foreach ($context['topics'] as $t => $topic)
		{
			$started = $topic['first_post']['member']['id'] == $user_info['id'];
			$context['topics'][$t]['quick_mod'] = array(
				'lock' => allowedTo('lock_any') || ($started && allowedTo('lock_own')),
				'sticky' => allowedTo('make_sticky'),
				'move' => allowedTo('move_any') || ($started && allowedTo('move_own')),
				'modify' => allowedTo('modify_any') || ($started && allowedTo('modify_own')),
				'remove' => allowedTo('remove_any') || ($started && allowedTo('remove_own')),
				'approve' => $context['can_approve'] && $topic['unapproved_posts']
			);
			$context['can_lock'] |= ($started && allowedTo('lock_own'));
			$context['can_move'] |= ($started && allowedTo('move_own'));
			$context['can_remove'] |= ($started && allowedTo('remove_own'));
		}

		// Find the boards/cateogories they can move their topic to.
		if ($options['display_quick_mod'] == 1 && $context['can_move'] && !empty($context['topics']))
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
				foreach ($cat['boards'] as $id_board => $board)
					$context['move_to_boards'][$id_cat]['boards'][$id_board]['name'] = strip_tags($board['name']);
			}

			// With no other boards to see, it's useless to move.
			if (empty($context['move_to_boards']))
				$context['can_move'] = false;
		}
		// Can we use quick moderation checkboxes?
		if ($options['display_quick_mod'] == 1)
			$context['can_quick_mod'] = $context['user']['is_logged'] || $context['can_approve'] || $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'] || $context['can_merge'] || $context['can_restore'];
		// Or the icons?
		else
			$context['can_quick_mod'] = $context['can_remove'] || $context['can_lock'] || $context['can_sticky'] || $context['can_move'];
	}

	// If there are children, but no topics and no ability to post topics...
	$context['no_topic_listing'] = !empty($context['boards']) && empty($context['topics']) && !$context['can_post_new'];
	if (!$context['no_topic_listing'])
		wetem::load('messageindex_legend', 'sidebar');

	// Create the button set...
	$context['button_list'] = array(
		'new_topic' => array('test' => 'can_post_new', 'text' => 'new_topic', 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0', 'class' => 'active'),
		'post_poll' => array('test' => 'can_post_poll', 'text' => 'new_poll', 'url' => $scripturl . '?action=post;board=' . $context['current_board'] . '.0;poll'),
		($context['is_marked_notify'] ? 'unnotify' : 'notify') => array('test' => 'can_mark_notify', 'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify', 'custom' => 'onclick="return confirm(' . JavaScriptEscape($txt['notification_' . ($context['is_marked_notify'] ? 'disable_board' : 'enable_board')]) . ');"', 'url' => $scripturl . '?action=notifyboard;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';board=' . $context['current_board'] . '.' . $context['start'] . ';' . $context['session_query']),
		'markread' => array('text' => 'mark_read_short', 'url' => $scripturl . '?action=markasread;sa=board;board=' . $context['current_board'] . '.0;' . $context['session_query']),
	);

	// They can only mark read if they are logged in!
	if (!$context['user']['is_logged'])
		unset($context['button_list']);

	// Allow adding new buttons easily.
	call_hook('messageindex_buttons');

	if (empty($modSettings['display_flags']))
		$modSettings['display_flags'] = 'none';
}

?>