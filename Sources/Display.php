<?php
/**
 * Wedge
 *
 * Displays a single topic and paginates the posts within.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This is perhaps the most important and probably most accessed files
	in all of Wedge. This file controls topic and message display.
	It does so with the following functions:

	void Display()
		- loads the posts in a topic up so they can be displayed.
		- supports wireless, using wap2 and the Wireless templates.
		- uses the main sub template of the Display template.
		- requires a topic, and can go to the previous or next topic from it.
		- jumps to the correct post depending on a number/time/IS_MSG passed.
		- depends on the messages_per_page, defaultMaxMessages and enableAllMessages settings.
		- is accessed by ?topic=id_topic.START.

	array prepareDisplayContext(bool reset = false)
		- actually gets and prepares the message context.
		- starts over from the beginning if reset is set to true, which is
		  useful for showing an index before or after the posts.

	array loadAttachmentContext(int id_msg)
		- loads an attachment's contextual data including, most importantly,
		  its size if it is an image.
		- expects the $attachments array to have been filled with the proper
		  attachment data, as Display() does.
		- requires the view_attachments permission to calculate image size.
		- attempts to keep the "aspect ratio" of the posted image in line,
		  even if it has to be resized by the max_image_width and
		  max_image_height settings.

	int approved_attach_sort(array a, array b)
		- a sort function for putting unapproved attachments first.

	void QuickInTopicModeration()
		- in-topic quick moderation.

*/

// The central part of the board - topic display.
function Display()
{
	global $scripturl, $txt, $modSettings, $context, $settings;
	global $options, $user_info, $board_info, $topic, $board, $boardurl;
	global $attachments, $messages_request, $topicinfo, $language;

	// What are you gonna display if these are empty?!
	if (empty($topic))
		fatal_lang_error('no_board', false);

	// 301 redirects on old-school queries like "?topic=242.0"
	// !!! Should we just be taking the original HTTP var and redirect to it?
	if ((isset($context['pretty']['oldschoolquery']) || $_SERVER['HTTP_HOST'] != $board_info['url']) && !empty($modSettings['pretty_filters']['topics']))
	{
		$url = 'topic=' . $topic . '.' . (isset($_REQUEST['start']) ? $_REQUEST['start'] : '0') . (isset($_REQUEST['topicseen']) ? ';topicseen' : '') . (isset($_REQUEST['all']) ? ';all' : '') . (isset($_REQUEST['viewResults']) ? ';viewResults' : '');
		header('HTTP/1.1 301 Moved Permanently');
		redirectexit($url, false);
	}

	// Load the proper template and/or sub template.
	if (WIRELESS)
		loadBlock('wap2_display');
	else
		loadTemplate('Display');

	// Not only does a prefetch make things slower for the server, but it makes it impossible to know if they read it.
	if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch')
	{
		ob_end_clean();
		header('HTTP/1.1 403 Prefetch Forbidden');
		die;
	}

	// How much are we sticking on each page?
	$context['messages_per_page'] = empty($modSettings['disableCustomPerPage']) && !empty($options['messages_per_page']) && !WIRELESS ? $options['messages_per_page'] : $modSettings['defaultMaxMessages'];

	// Let's do some work on what to search index.
	if (count($_GET) > 2)
		foreach ($_GET as $k => $v)
			if (!in_array($k, array('topic', 'board', 'start', session_name())))
				$context['robot_no_index'] = true;

	if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % $context['messages_per_page'] != 0))
		$context['robot_no_index'] = true;

	// Add 1 to the number of views of this topic.
	if (!$user_info['possibly_robot'] && (empty($_SESSION['last_read_topic']) || $_SESSION['last_read_topic'] != $topic))
	{
		wesql::query('
			UPDATE {db_prefix}topics
			SET num_views = num_views + 1
			WHERE id_topic = {int:current_topic}',
			array(
				'current_topic' => $topic,
			)
		);

		$_SESSION['last_read_topic'] = $topic;
	}

	// Get all the important topic info.
	$request = wesql::query('
		SELECT
			t.num_replies, t.num_views, t.locked, ms.subject, t.is_sticky, t.id_poll,
			t.id_member_started, t.id_first_msg, t.id_last_msg, t.approved, t.unapproved_posts, ms.poster_time,
			' . ($user_info['is_guest'] ? 't.id_last_msg + 1' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from
			' . (!empty($modSettings['recycle_board']) && $modSettings['recycle_board'] == $board ? ', id_previous_board, id_previous_topic' : '') . '
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)' . ($user_info['is_guest'] ? '' : '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:current_topic} AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})') . '
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_member' => $user_info['id'],
			'current_topic' => $topic,
			'current_board' => $board,
		)
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('not_a_topic', false);
	$topicinfo = wesql::fetch_assoc($request);
	wesql::free_result($request);

	$context['real_num_replies'] = $context['num_replies'] = $topicinfo['num_replies'];
	$context['topic_first_message'] = $topicinfo['id_first_msg'];
	$context['topic_last_message'] = $topicinfo['id_last_msg'];

	// Add up unapproved replies to get real number of replies...
	if ($modSettings['postmod_active'] && allowedTo('approve_posts'))
		$context['real_num_replies'] += $topicinfo['unapproved_posts'] - ($topicinfo['approved'] ? 0 : 1);

	// If this topic has unapproved posts, we need to work out how many posts the user can see, for page indexing.
	// We also need to discount the first post if this is a blog board.
	$including_first = $topicinfo['approved'] && $board_info['type'] == 'board';
	if ($modSettings['postmod_active'] && $topicinfo['unapproved_posts'] && !$user_info['is_guest'] && !allowedTo('approve_posts'))
	{
		$request = wesql::query('
			SELECT COUNT(id_member) AS my_unapproved_posts
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_member = {int:current_member}
				AND approved = 0',
			array(
				'current_topic' => $topic,
				'current_member' => $user_info['id'],
			)
		);
		list ($myUnapprovedPosts) = wesql::fetch_row($request);
		wesql::free_result($request);

		$context['total_visible_posts'] = $context['num_replies'] + $myUnapprovedPosts + ($including_first ? 1 : 0);
	}
	else
		$context['total_visible_posts'] = $context['num_replies'] + $topicinfo['unapproved_posts'] + ($including_first ? 1 : 0);

	// The start isn't a number; it's information about what to do, where to go.
	if (!is_numeric($_REQUEST['start']))
	{
		// Redirect to the page and post with new messages, originally by Omar Bazavilvazo.
		if ($_REQUEST['start'] == 'new')
		{
			// Guests automatically go to the last post.
			if ($user_info['is_guest'])
			{
				$context['start_from'] = $context['total_visible_posts'] - 1;
				$_REQUEST['start'] = empty($options['view_newest_first']) ? $context['start_from'] : 0;
			}
			else
			{
				// Find the earliest unread message in the topic. The use of topics here is just for both tables.
				$request = wesql::query('
					SELECT IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from
					FROM {db_prefix}topics AS t
						LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:current_topic} AND lt.id_member = {int:current_member})
						LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})
					WHERE t.id_topic = {int:current_topic}
					LIMIT 1',
					array(
						'current_board' => $board,
						'current_member' => $user_info['id'],
						'current_topic' => $topic,
					)
				);
				list ($new_from) = wesql::fetch_row($request);
				wesql::free_result($request);

				// Fall through to the next if statement.
				$_REQUEST['start'] = 'msg' . $new_from;
			}
		}

		// Start from a certain time index, not a message.
		if (strpos($_REQUEST['start'], 'from') === 0)
		{
			$timestamp = (int) substr($_REQUEST['start'], 4);
			if ($timestamp === 0)
				$_REQUEST['start'] = 0;
			else
			{
				// Find the number of messages posted before said time...
				$request = wesql::query('
					SELECT COUNT(*)
					FROM {db_prefix}messages
					WHERE poster_time < {int:timestamp}
						AND id_topic = {int:current_topic}' . ($modSettings['postmod_active'] && $topicinfo['unapproved_posts'] && !allowedTo('approve_posts') ? '
						AND (approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
					array(
						'current_topic' => $topic,
						'current_member' => $user_info['id'],
						'is_approved' => 1,
						'timestamp' => $timestamp,
					)
				);
				list ($context['start_from']) = wesql::fetch_row($request);
				wesql::free_result($request);

				// Handle view_newest_first options, and get the correct start value.
				$_REQUEST['start'] = empty($options['view_newest_first']) ? $context['start_from'] : $context['total_visible_posts'] - $context['start_from'] - 1;
			}
		}

		// Link to a message...
		elseif (strpos($_REQUEST['start'], 'msg') === 0)
		{
			$virtual_msg = (int) substr($_REQUEST['start'], 3);
			if (!$topicinfo['unapproved_posts'] && $virtual_msg >= $topicinfo['id_last_msg'])
				$context['start_from'] = $context['total_visible_posts'] - 1;
			elseif (!$topicinfo['unapproved_posts'] && $virtual_msg <= $topicinfo['id_first_msg'])
				$context['start_from'] = 0;
			else
			{
				// Find the start value for that message......
				$request = wesql::query('
					SELECT COUNT(*)
					FROM {db_prefix}messages
					WHERE id_msg < {int:virtual_msg}
						AND id_topic = {int:current_topic}' . ($modSettings['postmod_active'] && $topicinfo['unapproved_posts'] && !allowedTo('approve_posts') ? '
						AND (approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
					array(
						'current_member' => $user_info['id'],
						'current_topic' => $topic,
						'virtual_msg' => $virtual_msg,
						'is_approved' => 1,
						'no_member' => 0,
					)
				);
				list ($context['start_from']) = wesql::fetch_row($request);
				wesql::free_result($request);
			}

			// We need to reverse the start as well in this case.
			$_REQUEST['start'] = empty($options['view_newest_first']) ? $context['start_from'] : $context['total_visible_posts'] - $context['start_from'] - 1;
		}
	}

	// No use in calculating the next topic if there's only one.
	if (!empty($modSettings['enablePreviousNext']) && $board_info['num_topics'] > 1)
	{
		$sort_methods = array(
			'subject' => array(
				'sort' => 'mf.subject',
				'join' => '
						INNER JOIN {db_prefix}messages AS mf ON (t.id_first_msg = mf.id_msg)',
				'cmp' => '> {string:current_subject}',
			),
			// These two are going to hurt but there's not really any better way to do it *sad face* - fortunately they're not very common.
			'starter' => array(
				'sort' => 'poster',
				'join' => '
						INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
						LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
						INNER JOIN {db_prefix}messages AS md ON (md.id_msg = {int:current_first_msg})
						LEFT JOIN {db_prefix}members AS memd ON (memd.id_member = md.id_member)',
				'select' => ', IFNULL(memf.real_name, mf.poster_name) AS poster, IFNULL(memd.real_name, md.poster_name) AS this_poster',
				'cmp' => '> this_poster',
			),
			'last_poster' => array(
				'sort' => 'poster',
				'join' => '
						INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
						LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
						INNER JOIN {db_prefix}messages AS md ON (md.id_msg = {int:current_last_msg})
						LEFT JOIN {db_prefix}members AS memd ON (memd.id_member = md.id_member)',
				'select' => ', IFNULL(meml.real_name, ml.poster_name) AS poster, IFNULL(memd.real_name, md.poster_name) AS this_poster',
				'cmp' => '> this_poster',
			),
			'replies' => array(
				'sort' => 't.num_replies',
				'cmp' => '> {int:current_replies}',
			),
			'views' => array(
				'sort' => 't.num_views',
				'cmp' => '> {int:current_views}',
			),
			'first_post' => array(
				'sort' => 't.id_topic',
				'cmp' => '> {int:current_topic}',
			),
			'last_post' => array(
				'sort' => 't.id_last_msg',
				'cmp' => '> {int:current_last_msg}',
			),
		);

		$sort_by = $board_info['sort_method'];
		$sort = $sort_methods[$sort_by]['sort'];
		$ascending = $board_info['sort_override'] === 'force_asc' || $board_info['sort_override'] === 'natural_asc';

		// !!! @todo: {query_see_topic}
		$request = wesql::query('
			(
				SELECT t.id_topic, m.subject, 1
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
				WHERE t.id_topic = (
					SELECT t.id_topic' . (isset($sort_methods[$sort_by]['select']) ? $sort_methods[$sort_by]['select'] : '') . '
					FROM {db_prefix}topics AS t' . (isset($sort_methods[$sort_by]['join']) ? $sort_methods[$sort_by]['join'] : '') . '
					WHERE t.id_board = {int:current_board}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
						AND (t.approved = 1 OR (t.id_member_started != 0 AND t.id_member_started = {int:current_member}))') . '
						AND ' . $sort . ' ' . $sort_methods[$sort_by]['cmp'] . '
					ORDER BY t.is_sticky' . ($ascending ? ' DESC' : '') . ', ' . $sort . ($ascending ? ' DESC' : '') . '
					LIMIT 1
				)
			)
			UNION ALL
			(
				SELECT t.id_topic, m.subject, 2
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
				WHERE t.id_topic = (
					SELECT t.id_topic' . (isset($sort_methods[$sort_by]['select']) ? $sort_methods[$sort_by]['select'] : '') . '
					FROM {db_prefix}topics AS t' . (isset($sort_methods[$sort_by]['join']) ? $sort_methods[$sort_by]['join'] : '') . '
					WHERE t.id_board = {int:current_board}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
						AND (t.approved = 1 OR (t.id_member_started != 0 AND t.id_member_started = {int:current_member}))') . '
						AND ' . $sort . ' ' . str_replace('>', '<', $sort_methods[$sort_by]['cmp']) . '
					ORDER BY t.is_sticky' . (!$ascending ? ' DESC' : '') . ', ' . $sort . (!$ascending ? ' DESC' : '') . '
					LIMIT 1
				)
			)',
			array(
				'current_board' => $board,
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
				'current_subject' => $topicinfo['subject'],
				'current_replies' => $topicinfo['num_replies'],
				'current_views' => $topicinfo['num_views'],
				'current_first_msg' => $topicinfo['id_first_msg'],
				'current_last_msg' => $topicinfo['id_last_msg'],
			)
		);

		list ($prev_topic, $prev_title, $prev_pos) = wesql::fetch_row($request);
		list ($next_topic, $next_title) = wesql::fetch_row($request);
		wesql::free_result($request);

		if (empty($next_topic) && !empty($prev_topic) && $prev_pos == 2)
		{
			list ($next_topic, $next_title) = array($prev_topic, $prev_title);
			$prev_topic = $prev_title = '';
		}
		$context['prev_topic'] = $prev_topic;
		$context['next_topic'] = $next_topic;
	}

	// Create a previous/next string if the selected theme has it as a selected option.
	$short_prev = empty($prev_title) ? '' : westr::cut($prev_title, 60);
	$short_next = empty($next_title) ? '' : westr::cut($next_title, 60);
	$context['prevnext_prev'] = '
				<div class="prevnext_prev">' . (empty($prev_topic) ? '' : '&laquo;&nbsp;<a href="' . $scripturl . '?topic=' . $prev_topic . '.0#new"' . ($prev_title != $short_prev ? ' title="' . $prev_title . '"' : '') . '>' . $short_prev . '</a>') . '</div>';
	$context['prevnext_next'] = '
				<div class="prevnext_next">' . (empty($next_topic) ? '' : '<a href="' . $scripturl . '?topic=' . $next_topic . '.0#new"' . ($next_title != $short_next ? ' title="' . $next_title . '"' : '') . '>' . $short_next . '</a>&nbsp;&raquo;') . '</div>';

	// Check if spellchecking is both enabled and actually working. (for quick reply.)
	$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && function_exists('pspell_new');

	// Do we need to show the visual verification image?
	$context['require_verification'] = !$user_info['is_mod'] && !$user_info['is_admin'] && !empty($modSettings['posts_require_captcha']) && ($user_info['posts'] < $modSettings['posts_require_captcha'] || ($user_info['is_guest'] && $modSettings['posts_require_captcha'] == -1));
	if ($context['require_verification'])
	{
		loadSource('Subs-Editor');
		$verificationOptions = array(
			'id' => 'post',
		);
		$context['require_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}

	// Are we showing signatures - or disabled fields?
	$context['signature_enabled'] = $modSettings['signature_settings'][0] == 1;
	$context['disabled_fields'] = isset($modSettings['disabled_profile_fields']) ? array_flip(explode(',', $modSettings['disabled_profile_fields'])) : array();

	// Censor the title...
	censorText($topicinfo['subject']);
	$context['page_title'] = $topicinfo['subject'];

	// Set the userbox position to the right. Later on, allow users to determine position of sidebar & userbox.
	$context['post_position'] = 'right';

	loadBlock('display_statistics', 'sidebar', 'add');

	// Default this topic to not marked for notifications... of course...
	$context['is_marked_notify'] = false;

	// Did we report a post to a moderator just now?
	$context['report_sent'] = isset($_GET['reportsent']);

	// Did someone save a conventional draft?
	$context['draft_saved'] = isset($_GET['draftsaved']);

	// Let's get nosey, who is viewing this topic?
	if (!empty($settings['display_who_viewing']) && !WIRELESS)
	{
		loadSource('Subs-MembersOnline');
		getMembersOnlineDetails('topic');
		loadBlock('display_whoviewing', 'sidebar', 'add');
	}

	// If all is set, but not allowed... just unset it.
	$can_show_all = !empty($modSettings['enableAllMessages']) && $context['total_visible_posts'] > $context['messages_per_page'] && $context['total_visible_posts'] < $modSettings['enableAllMessages'];
	if (isset($_REQUEST['all']) && !$can_show_all)
		unset($_REQUEST['all']);
	// Otherwise, it must be allowed... so pretend start was -1.
	elseif (isset($_REQUEST['all']))
		$_REQUEST['start'] = -1;

	// Construct the page index, allowing for the .START method...
	$context['page_index'] = constructPageIndex($scripturl . '?topic=' . $topic . '.%1$d', $_REQUEST['start'], $context['total_visible_posts'], $context['messages_per_page'], true);
	$context['start'] = $_REQUEST['start'];

	// This is information about which page is current, and which page we're on - in case you don't like the constructed page index. (again, wireles..)
	$context['page_info'] = array(
		'current_page' => $_REQUEST['start'] / $context['messages_per_page'] + 1,
		'num_pages' => floor(($context['total_visible_posts'] - 1) / $context['messages_per_page']) + 1,
	);

	// Figure out all the link to the next/prev/first/last/etc. for wireless mainly.
	$context['links'] = array(
		'first' => $_REQUEST['start'] >= $context['messages_per_page'] ? $scripturl . '?topic=' . $topic . '.0' : '',
		'prev' => $_REQUEST['start'] >= $context['messages_per_page'] ? $scripturl . '?topic=' . $topic . '.' . ($_REQUEST['start'] - $context['messages_per_page']) : '',
		'next' => $_REQUEST['start'] + $context['messages_per_page'] < $context['total_visible_posts'] ? $scripturl . '?topic=' . $topic. '.' . ($_REQUEST['start'] + $context['messages_per_page']) : '',
		'last' => $_REQUEST['start'] + $context['messages_per_page'] < $context['total_visible_posts'] ? $scripturl . '?topic=' . $topic. '.' . (floor($context['total_visible_posts'] / $context['messages_per_page']) * $context['messages_per_page']) : '',
		'up' => $scripturl . '?board=' . $board . '.0'
	);

	// If they are viewing all the posts, show all the posts, otherwise limit the number.
	if ($can_show_all)
	{
		if (isset($_REQUEST['all']))
		{
			// No limit! (actually, there is a limit, but...)
			$context['messages_per_page'] = -1;
			$context['page_index'] .= '[<strong>' . $txt['all'] . '</strong>] ';

			// Set start back to 0...
			$_REQUEST['start'] = 0;
		}
		// They aren't using it, but the *option* is there, at least.
		else
			$context['page_index'] .= '&nbsp;<a href="' . $scripturl . '?topic=' . $topic . '.0;all">' . $txt['all'] . '</a> ';
	}

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?topic=' . $topic . '.0',
		'name' => $topicinfo['subject'],
		'extra_before' => $settings['linktree_inline'] ? $txt['topic'] . ': ' : ''
	);

	// Build a list of this board's moderators.
	$context['moderators'] = &$board_info['moderators'];
	$context['link_moderators'] = array();
	if (!empty($board_info['moderators']))
	{
		// Add a link for each moderator...
		foreach ($board_info['moderators'] as $mod)
			$context['link_moderators'][] = '<a href="' . $scripturl . '?action=profile;u=' . $mod['id'] . '" title="' . $txt['board_moderator'] . '">' . $mod['name'] . '</a>';

		// And show it after the board's name.
		$context['linktree'][count($context['linktree']) - 2]['extra_after'] = ' (' . (count($context['link_moderators']) == 1 ? $txt['moderator'] : $txt['moderators']) . ': ' . implode(', ', $context['link_moderators']) . ')';
	}

	// Information about the current topic...
	$context['is_locked'] = $topicinfo['locked'];
	$context['is_sticky'] = $topicinfo['is_sticky'];
	$context['is_approved'] = $topicinfo['approved'];

	$context['is_poll'] = $topicinfo['id_poll'] > 0 && $modSettings['pollMode'] == '1' && allowedTo('poll_view');

	// Did this user start the topic or not?
	$context['user']['started'] = $user_info['id'] == $topicinfo['id_member_started'] && !$user_info['is_guest'];
	$context['topic_starter_id'] = $topicinfo['id_member_started'];

	// Set the topic's information for the template.
	$context['subject'] = $topicinfo['subject'];
	$context['num_views'] = $topicinfo['num_views'];
	$context['mark_unread_time'] = $topicinfo['new_from'];

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl . '?topic=' . $topic . '.' . $context['start'];

	// For quick reply we need a response prefix in the default forum language.
	if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix', 600)))
	{
		if ($language === $user_info['language'])
			$context['response_prefix'] = $txt['response_prefix'];
		else
		{
			loadLanguage('index', $language, false);
			$context['response_prefix'] = $txt['response_prefix'];
			loadLanguage('index');
		}
		cache_put_data('response_prefix', $context['response_prefix'], 600);
	}

	// If we want to show event information in the topic, prepare the data.
	if (allowedTo('calendar_view') && !empty($modSettings['cal_showInTopic']) && !empty($modSettings['cal_enabled']))
	{
		// First, try create a better time format, ignoring the "time" elements.
		if (preg_match('~%[AaBbCcDdeGghjmuYy](?:[^%]*%[AaBbCcDdeGghjmuYy])*~', $user_info['time_format'], $matches) == 0 || empty($matches[0]))
			$date_string = $user_info['time_format'];
		else
			$date_string = $matches[0];

		// Any calendar information for this topic?
		$request = wesql::query('
			SELECT cal.id_event, cal.start_date, cal.end_date, cal.title, cal.id_member, mem.real_name
			FROM {db_prefix}calendar AS cal
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = cal.id_member)
			WHERE cal.id_topic = {int:current_topic}
			ORDER BY start_date',
			array(
				'current_topic' => $topic,
			)
		);
		$context['linked_calendar_events'] = array();
		while ($row = wesql::fetch_assoc($request))
		{
			// Prepare the dates for being formatted.
			$start_date = sscanf($row['start_date'], '%04d-%02d-%02d');
			$start_date = mktime(12, 0, 0, $start_date[1], $start_date[2], $start_date[0]);
			$end_date = sscanf($row['end_date'], '%04d-%02d-%02d');
			$end_date = mktime(12, 0, 0, $end_date[1], $end_date[2], $end_date[0]);

			$context['linked_calendar_events'][] = array(
				'id' => $row['id_event'],
				'title' => $row['title'],
				'can_edit' => allowedTo('calendar_edit_any') || ($row['id_member'] == $user_info['id'] && allowedTo('calendar_edit_own')),
				'modify_href' => $scripturl . '?action=post;msg=' . $topicinfo['id_first_msg'] . ';topic=' . $topic . '.0;calendar;eventid=' . $row['id_event'] . ';' . $context['session_query'],
				'start_date' => timeformat($start_date, $date_string, 'none'),
				'start_timestamp' => $start_date,
				'end_date' => timeformat($end_date, $date_string, 'none'),
				'end_timestamp' => $end_date,
				'is_last' => false
			);
		}
		wesql::free_result($request);

		if (!empty($context['linked_calendar_events']))
			$context['linked_calendar_events'][count($context['linked_calendar_events']) - 1]['is_last'] = true;
	}

	// Create the poll info if it exists.
	if ($context['is_poll'])
	{
		// Get the question and if it's locked.
		$request = wesql::query('
			SELECT
				p.question, p.voting_locked, p.hide_results, p.expire_time, p.max_votes, p.change_vote,
				p.guest_vote, p.id_member, IFNULL(mem.real_name, p.poster_name) AS poster_name, p.num_guest_voters, p.reset_poll
			FROM {db_prefix}polls AS p
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = p.id_member)
			WHERE p.id_poll = {int:id_poll}
			LIMIT 1',
			array(
				'id_poll' => $topicinfo['id_poll'],
			)
		);
		$pollinfo = wesql::fetch_assoc($request);
		wesql::free_result($request);

		$request = wesql::query('
			SELECT COUNT(DISTINCT id_member) AS total
			FROM {db_prefix}log_polls
			WHERE id_poll = {int:id_poll}
				AND id_member != {int:not_guest}',
			array(
				'id_poll' => $topicinfo['id_poll'],
				'not_guest' => 0,
			)
		);
		list ($pollinfo['total']) = wesql::fetch_row($request);
		wesql::free_result($request);

		// Total voters needs to include guest voters
		$pollinfo['total'] += $pollinfo['num_guest_voters'];

		// Get all the options, and calculate the total votes.
		$request = wesql::query('
			SELECT pc.id_choice, pc.label, pc.votes, IFNULL(lp.id_choice, -1) AS voted_this
			FROM {db_prefix}poll_choices AS pc
				LEFT JOIN {db_prefix}log_polls AS lp ON (lp.id_choice = pc.id_choice AND lp.id_poll = {int:id_poll} AND lp.id_member = {int:current_member} AND lp.id_member != {int:not_guest})
			WHERE pc.id_poll = {int:id_poll}',
			array(
				'current_member' => $user_info['id'],
				'id_poll' => $topicinfo['id_poll'],
				'not_guest' => 0,
			)
		);
		$pollOptions = array();
		$realtotal = 0;
		$pollinfo['has_voted'] = false;
		while ($row = wesql::fetch_assoc($request))
		{
			censorText($row['label']);
			$pollOptions[$row['id_choice']] = $row;
			$realtotal += $row['votes'];
			$pollinfo['has_voted'] |= $row['voted_this'] != -1;
		}
		wesql::free_result($request);

		// If this is a guest we need to do our best to work out if they have voted, and what they voted for.
		if ($user_info['is_guest'] && $pollinfo['guest_vote'] && allowedTo('poll_vote'))
		{
			if (!empty($_COOKIE['guest_poll_vote']) && preg_match('~^[0-9,;]+$~', $_COOKIE['guest_poll_vote']) && strpos($_COOKIE['guest_poll_vote'], ';' . $topicinfo['id_poll'] . ',') !== false)
			{
				// ;id,timestamp,[vote,vote...]; etc
				$guestinfo = explode(';', $_COOKIE['guest_poll_vote']);
				// Find the poll we're after.
				foreach ($guestinfo as $i => $guestvoted)
				{
					$guestvoted = explode(',', $guestvoted);
					if ($guestvoted[0] == $topicinfo['id_poll'])
						break;
				}
				// Has the poll been reset since guest voted?
				if ($pollinfo['reset_poll'] > $guestvoted[1])
				{
					// Remove the poll info from the cookie to allow guest to vote again
					unset($guestinfo[$i]);
					if (!empty($guestinfo))
						$_COOKIE['guest_poll_vote'] = ';' . implode(';', $guestinfo);
					else
						unset($_COOKIE['guest_poll_vote']);
				}
				else
				{
					// What did they vote for?
					unset($guestvoted[0], $guestvoted[1]);
					foreach ($pollOptions as $choice => $details)
					{
						$pollOptions[$choice]['voted_this'] = in_array($choice, $guestvoted) ? 1 : -1;
						$pollinfo['has_voted'] |= $pollOptions[$choice]['voted_this'] != -1;
					}
					unset($choice, $details, $guestvoted);
				}
				unset($guestinfo, $guestvoted, $i);
			}
		}

		// Set up the basic poll information.
		$context['poll'] = array(
			'id' => $topicinfo['id_poll'],
			'image' => 'normal_' . (empty($pollinfo['voting_locked']) ? 'poll' : 'locked_poll'),
			'question' => parse_bbc($pollinfo['question']),
			'total_votes' => $pollinfo['total'],
			'change_vote' => !empty($pollinfo['change_vote']),
			'is_locked' => !empty($pollinfo['voting_locked']),
			'options' => array(),
			'lock' => allowedTo('poll_lock_any') || ($context['user']['started'] && allowedTo('poll_lock_own')),
			'edit' => allowedTo('poll_edit_any') || ($context['user']['started'] && allowedTo('poll_edit_own')),
			'allowed_warning' => $pollinfo['max_votes'] > 1 ? sprintf($txt['poll_options6'], min(count($pollOptions), $pollinfo['max_votes'])) : '',
			'is_expired' => !empty($pollinfo['expire_time']) && $pollinfo['expire_time'] < time(),
			'expire_time' => !empty($pollinfo['expire_time']) ? timeformat($pollinfo['expire_time']) : 0,
			'has_voted' => !empty($pollinfo['has_voted']),
			'starter' => array(
				'id' => $pollinfo['id_member'],
				'name' => $row['poster_name'],
				'href' => $pollinfo['id_member'] == 0 ? '' : $scripturl . '?action=profile;u=' . $pollinfo['id_member'],
				'link' => $pollinfo['id_member'] == 0 ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $pollinfo['id_member'] . '">' . $row['poster_name'] . '</a>'
			)
		);

		// Make the lock and edit permissions defined above more directly accessible.
		$context['allow_lock_poll'] = $context['poll']['lock'];
		$context['allow_edit_poll'] = $context['poll']['edit'];

		// You're allowed to vote if:
		// 1. the poll did not expire, and
		// 2. you're either not a guest OR guest voting is enabled... and
		// 3. you're not trying to view the results, and
		// 4. the poll is not locked, and
		// 5. you have the proper permissions, and
		// 6. you haven't already voted before.
		$context['allow_vote'] = !$context['poll']['is_expired'] && (!$user_info['is_guest'] || ($pollinfo['guest_vote'] && allowedTo('poll_vote'))) && empty($pollinfo['voting_locked']) && allowedTo('poll_vote') && !$context['poll']['has_voted'];

		// You're allowed to view the results if:
		// 1. you're just a super-nice-guy, or
		// 2. anyone can see them (hide_results == 0), or
		// 3. you can see them after you voted (hide_results == 1), or
		// 4. you've waited long enough for the poll to expire. (whether hide_results is 1 or 2.)
		$context['allow_poll_view'] = allowedTo('moderate_board') || $pollinfo['hide_results'] == 0 || ($pollinfo['hide_results'] == 1 && $context['poll']['has_voted']) || $context['poll']['is_expired'];
		$context['poll']['show_results'] = $context['allow_poll_view'] && (isset($_REQUEST['viewresults']) || isset($_REQUEST['viewResults']));
		$context['show_view_results_button'] = $context['allow_vote'] && (!$context['allow_poll_view'] || !$context['poll']['show_results'] || !$context['poll']['has_voted']);

		// You're allowed to change your vote if:
		// 1. the poll did not expire, and
		// 2. you're not a guest... and
		// 3. the poll is not locked, and
		// 4. you have the proper permissions, and
		// 5. you have already voted, and
		// 6. the poll creator has said you can!
		$context['allow_change_vote'] = !$context['poll']['is_expired'] && !$user_info['is_guest'] && empty($pollinfo['voting_locked']) && allowedTo('poll_vote') && $context['poll']['has_voted'] && $context['poll']['change_vote'];

		// You're allowed to return to voting options if:
		// 1. you are (still) allowed to vote.
		// 2. you are currently seeing the results.
		$context['allow_return_vote'] = $context['allow_vote'] && $context['poll']['show_results'];

		// Calculate the percentages and bar lengths...
		$divisor = $realtotal == 0 ? 1 : $realtotal;

		// Determine if a decimal point is needed in order for the options to add to 100%.
		$precision = $realtotal == 100 ? 0 : 1;

		// Now look through each option, and...
		foreach ($pollOptions as $i => $option)
		{
			// First calculate the percentage, and then the width of the bar...
			$bar = round(($option['votes'] * 100) / $divisor, $precision);
			$barWide = $bar == 0 ? 1 : floor(($bar * 8) / 3);

			// Now add it to the poll's contextual theme data.
			$context['poll']['options'][$i] = array(
				'percent' => $bar,
				'votes' => $option['votes'],
				'voted_this' => $option['voted_this'] != -1,
				'bar' => '<span class="nowrap"><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'right' : 'left') . '.gif"><img src="' . $settings['images_url'] . '/poll_middle.gif" width="' . $barWide . '" height="12"><img src="' . $settings['images_url'] . '/poll_' . ($context['right_to_left'] ? 'left' : 'right') . '.gif"></span>',
				// Note: IE < 8 requires us to set a width on the container, too.
				'bar_ndt' => $bar > 0 ? '<div class="bar" style="width: ' . ($bar * 3.5 + 4) . 'px"><div style="width: ' . $bar * 3.5 . 'px"></div></div>' : '',
				'bar_width' => $barWide,
				'option' => parse_bbc($option['label']),
				'vote_button' => '<input type="' . ($pollinfo['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" value="' . $i . '">'
			);
		}
	}

	// Calculate the fastest way to get the messages!
	$ascending = empty($options['view_newest_first']);
	$start = $_REQUEST['start'] + ($board_info['type'] != 'board' ? 1 : 0);
	$limit = $context['messages_per_page'];
	$firstIndex = 0;
	if ($_REQUEST['start'] >= $context['total_visible_posts'] / 2 && $context['messages_per_page'] != -1)
	{
		$ascending = !$ascending;
		$limit = $context['total_visible_posts'] <= $_REQUEST['start'] + $limit ? $context['total_visible_posts'] - $_REQUEST['start'] : $limit;
		$start = $context['total_visible_posts'] <= $_REQUEST['start'] + $limit ? 0 : $context['total_visible_posts'] - $_REQUEST['start'] - $limit;
		$firstIndex = $limit - 1;
	}

	// Find out if there is a double post...
	$context['last_user_id'] = 0;
	$context['last_msg_id'] = 0;

	if ($_REQUEST['start'] != 0 && $context['messages_per_page'] != -1)
	{
		$request = wesql::query('
			SELECT id_member, id_msg, body, poster_email
			FROM {db_prefix}messages
			WHERE id_topic = {int:id_topic}
			ORDER BY id_msg
			LIMIT {int:postbefore}, 1',
			array(
				'id_topic' => $topic,
				'postbefore' => $_REQUEST['start'] - 1,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (!empty($row['id_member']))
				$context['last_user_id'] = $row['id_member'];
			// If you're the admin, you can merge guest posts
			elseif ($user_info['is_admin'])
				$context['last_user_id'] = $row['poster_email'];
			if (!empty($row['id_msg']))
				$context['last_msg_id'] = $row['id_msg'];
			if (!empty($row['body']))
				$context['last_post_length'] = strlen(un_htmlspecialchars($row['body']));
			else
				$context['last_post_length'] = 0;
		}
	}
	else
		$context['last_post_length'] = 0;

	// Get each post and poster in this topic.
	$request = wesql::query('
		SELECT id_msg, id_member, approved, poster_time
		FROM {db_prefix}messages
		WHERE id_topic = {int:current_topic}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : (!empty($modSettings['db_mysql_group_by_fix']) ? '' : '
		GROUP BY id_msg') . '
		HAVING (approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR id_member = {int:current_member}') . ')') . '
		ORDER BY id_msg ' . ($ascending ? '' : 'DESC') . ($context['messages_per_page'] == -1 ? '' : '
		LIMIT ' . $start . ', ' . $limit),
		array(
			'current_member' => $user_info['id'],
			'current_topic' => $topic,
			'is_approved' => 1,
			'blank_id_member' => 0,
		)
	);

	$all_posters = array();
	if ($board_info['type'] != 'board')
	{
		// Always get the first poster and message.
		$messages = array($topicinfo['id_first_msg']);
		if (!empty($topicinfo['id_member_started']))
			$all_posters = array($topicinfo['id_member_started']);
		$times = array($topicinfo['poster_time']);
	}
	else
	{
		$messages = array();
		$times = array();
	}

	while ($row = wesql::fetch_assoc($request))
	{
		if (!empty($row['id_member']))
			$all_posters[$row['id_msg']] = $row['id_member'];
		$messages[] = $row['id_msg'];
		$times[$row['id_msg']] = $row['poster_time'];
	}
	wesql::free_result($request);
	$posters = array_unique($all_posters);

	// When was the last time this topic was replied to? Should we warn them about it?
	if (!empty($modSettings['oldTopicDays']))
	{
		// Did we already get the last message? If so, we already have the last poster message.
		if (isset($times[$topicinfo['id_last_msg']]))
			$lastPostTime = $times[$topicinfo['id_last_msg']];
		else
		{
			$request = wesql::query('
				SELECT poster_time
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_last_msg}
				LIMIT 1',
				array(
					'id_last_msg' => $topicinfo['id_last_msg'],
				)
			);

			list ($lastPostTime) = wesql::fetch_row($request);
			wesql::free_result($request);
		}

		$context['oldTopicError'] = $lastPostTime + $modSettings['oldTopicDays'] * 86400 < time() && empty($sticky);
	}

	// Guests can't mark topics read or for notifications, just can't sorry.
	if (!$user_info['is_guest'])
	{
		$mark_at_msg = max($messages);
		if ($mark_at_msg >= $topicinfo['id_last_msg'])
			$mark_at_msg = $modSettings['maxMsgID'];
		if ($mark_at_msg >= $topicinfo['new_from'])
		{
			wesql::insert($topicinfo['new_from'] == 0 ? 'ignore' : 'replace',
				'{db_prefix}log_topics',
				array(
					'id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int',
				),
				array(
					$user_info['id'], $topic, $mark_at_msg,
				),
				array('id_member', 'id_topic')
			);
		}

		// Check for notifications on this topic OR board.
		$request = wesql::query('
			SELECT sent, id_topic
			FROM {db_prefix}log_notify
			WHERE (id_topic = {int:current_topic} OR id_board = {int:current_board})
				AND id_member = {int:current_member}
			LIMIT 2',
			array(
				'current_board' => $board,
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
			)
		);
		$do_once = true;
		while ($row = wesql::fetch_assoc($request))
		{
			// Find if this topic is marked for notification...
			if (!empty($row['id_topic']))
				$context['is_marked_notify'] = true;

			// Only do this once, but mark the notifications as "not sent yet" for next time.
			if (!empty($row['sent']) && $do_once)
			{
				wesql::query('
					UPDATE {db_prefix}log_notify
					SET sent = {int:is_not_sent}
					WHERE (id_topic = {int:current_topic} OR id_board = {int:current_board})
						AND id_member = {int:current_member}',
					array(
						'current_board' => $board,
						'current_member' => $user_info['id'],
						'current_topic' => $topic,
						'is_not_sent' => 0,
					)
				);
				$do_once = false;
			}
		}

		// Have we recently cached the number of new topics in this board, and it's still a lot?
		if (isset($_REQUEST['topicseen'], $_SESSION['topicseen_cache'][$board]) && $_SESSION['topicseen_cache'][$board] > 5)
			$_SESSION['topicseen_cache'][$board]--;
		// Mark board as seen if this is the only new topic.
		elseif (isset($_REQUEST['topicseen']))
		{
			// Use the mark read tables... and the last visit to figure out if this should be read or not.
			$request = wesql::query('
				SELECT COUNT(*)
				FROM {db_prefix}topics AS t
					LEFT JOIN {db_prefix}log_boards AS lb ON (lb.id_board = {int:current_board} AND lb.id_member = {int:current_member})
					LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
				WHERE t.id_board = {int:current_board}
					AND t.id_last_msg > IFNULL(lb.id_msg, 0)
					AND t.id_last_msg > IFNULL(lt.id_msg, 0)' . (empty($_SESSION['id_msg_last_visit']) ? '' : '
					AND t.id_last_msg > {int:id_msg_last_visit}'),
				array(
					'current_board' => $board,
					'current_member' => $user_info['id'],
					'id_msg_last_visit' => (int) $_SESSION['id_msg_last_visit'],
				)
			);
			list ($numNewTopics) = wesql::fetch_row($request);
			wesql::free_result($request);

			// If there're no real new topics in this board, mark the board as seen.
			if (empty($numNewTopics))
				$_REQUEST['boardseen'] = true;
			else
				$_SESSION['topicseen_cache'][$board] = $numNewTopics;
		}
		// Probably one less topic - maybe not, but even if we decrease this too fast it will only make us look more often.
		elseif (isset($_SESSION['topicseen_cache'][$board]))
			$_SESSION['topicseen_cache'][$board]--;

		// Mark board as seen if we came using the last post link from the board list or other places.
		if (isset($_REQUEST['boardseen']))
		{
			wesql::insert('replace',
				'{db_prefix}log_boards',
				array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
				array($modSettings['maxMsgID'], $user_info['id'], $board),
				array('id_member', 'id_board')
			);
		}
	}

	$attachments = array();

	// If there _are_ messages here... (probably an error otherwise :!)
	if (!empty($messages))
	{
		// Fetch attachments.
		if (!empty($modSettings['attachmentEnable']) && allowedTo('view_attachments'))
		{
			$request = wesql::query('
				SELECT
					a.id_attach, a.id_folder, a.id_msg, a.filename, a.file_hash, IFNULL(a.size, 0) AS filesize, a.downloads, a.approved,
					a.width, a.height' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : ',
					IFNULL(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height') . '
				FROM {db_prefix}attachments AS a' . (empty($modSettings['attachmentShowImages']) || empty($modSettings['attachmentThumbnails']) ? '' : '
					LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)') . '
				WHERE a.id_msg IN ({array_int:message_list})
					AND a.attachment_type = {int:attachment_type}',
				array(
					'message_list' => $messages,
					'attachment_type' => 0,
					'is_approved' => 1,
				)
			);
			$temp = array();
			while ($row = wesql::fetch_assoc($request))
			{
				if (!$row['approved'] && $modSettings['postmod_active'] && !allowedTo('approve_posts') && (!isset($all_posters[$row['id_msg']]) || $all_posters[$row['id_msg']] != $user_info['id']))
					continue;

				$temp[$row['id_attach']] = $row;

				if (!isset($attachments[$row['id_msg']]))
					$attachments[$row['id_msg']] = array();
			}
			wesql::free_result($request);

			// This is better than sorting it with the query...
			ksort($temp);

			foreach ($temp as $row)
				$attachments[$row['id_msg']][] = $row;
		}

		// What? It's not like it *couldn't* be only guests in this topic...
		if (!empty($posters))
			loadMemberData($posters);

		// Figure out the ordering.
		if ($board_info['type'] != 'board')
			$order = empty($options['view_newest_first']) ? 'ORDER BY id_msg' : 'ORDER BY id_msg != {int:first_msg}, id_msg DESC';
		else
			$order = 'ORDER BY id_msg' . (empty($options['view_newest_first']) ? '' : ' DESC');

		$messages_request = wesql::query('
			SELECT
				id_msg, icon, subject, poster_time, li.member_ip AS poster_ip, id_member, modified_time, modified_name, body,
				smileys_enabled, poster_name, poster_email, approved,
				id_msg_modified < {int:new_from} AS is_read
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}log_ips AS li ON (m.poster_ip = li.id_ip)
			WHERE id_msg IN ({array_int:message_list})
			' . $order,
			array(
				'message_list' => $messages,
				'new_from' => $topicinfo['new_from'],
				'first_msg' => $topicinfo['id_first_msg'],
			)
		);

		// Go to the last message if the given time is beyond the time of the last message.
		if (isset($context['start_from']) && $context['start_from'] >= $context['total_visible_posts'])
			$context['start_from'] = $context['total_visible_posts'] - 1;

		// Since the anchor information is needed on the top of the page we load these variables beforehand.
		$context['first_message'] = isset($messages[$firstIndex]) ? $messages[$firstIndex] : $messages[0];
		if (empty($options['view_newest_first']))
			$context['first_new_message'] = isset($context['start_from']) && $_REQUEST['start'] == $context['start_from'];
		else
			$context['first_new_message'] = isset($context['start_from']) && $_REQUEST['start'] == $context['total_visible_posts'] - 1 - $context['start_from'];
	}
	else
	{
		$messages_request = false;
		$context['first_message'] = 0;
		$context['first_new_message'] = false;
	}

	// Set the callback. (Do you REALIZE how much memory all the messages would take?!?)
	$context['get_message'] = 'prepareDisplayContext';

	// Now set all the wonderful, wonderful permissions... like moderation ones...
	$common_permissions = array(
		'can_approve' => 'approve_posts',
		'can_ban' => 'manage_bans',
		'can_sticky' => 'make_sticky',
		'can_merge' => 'merge_any',
		'can_split' => 'split_any',
		'calendar_post' => 'calendar_post',
		'can_mark_notify' => 'mark_any_notify',
		'can_send_topic' => 'send_topic',
		'can_send_pm' => 'pm_send',
		'can_report_moderator' => 'report_any',
		'can_moderate_forum' => 'moderate_forum',
		'can_issue_warning' => 'issue_warning',
		'can_restore_topic' => 'move_any',
		'can_restore_msg' => 'move_any',
	);
	foreach ($common_permissions as $contextual => $perm)
		$context[$contextual] = allowedTo($perm);

	// Permissions with _any/_own versions.  $context[YYY] => ZZZ_any/_own.
	$anyown_permissions = array(
		'can_move' => 'move',
		'can_lock' => 'lock',
		'can_delete' => 'remove',
		'can_add_poll' => 'poll_add',
		'can_remove_poll' => 'poll_remove',
		'can_reply' => 'post_reply',
		'can_reply_unapproved' => 'post_unapproved_replies',
	);
	foreach ($anyown_permissions as $contextual => $perm)
		$context[$contextual] = allowedTo($perm . '_any') || ($context['user']['started'] && allowedTo($perm . '_own'));

	// Cleanup all the permissions with extra stuff...
	$context['can_mark_notify'] &= !$context['user']['is_guest'];
	$context['calendar_post'] &= !empty($modSettings['cal_enabled']);
	$context['can_add_poll'] &= $modSettings['pollMode'] == '1' && $topicinfo['id_poll'] <= 0;
	$context['can_remove_poll'] &= $modSettings['pollMode'] == '1' && $topicinfo['id_poll'] > 0;
	$context['can_reply'] &= empty($topicinfo['locked']) || allowedTo('moderate_board');
	$context['can_reply_unapproved'] &= $modSettings['postmod_active'] && (empty($topicinfo['locked']) || allowedTo('moderate_board'));
	// Handle approval flags...
	$context['can_reply_approved'] = $context['can_reply'];
	$context['can_reply'] |= $context['can_reply_unapproved'];
	$context['can_quote'] = $context['can_reply'] && (empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC'])));
	$context['can_mark_unread'] = !$user_info['is_guest'];
	// Prevent robots from accessing the Post template
	$context['can_reply'] &= empty($context['possibly_robot']);

	$context['can_send_topic'] = (!$modSettings['postmod_active'] || $topicinfo['approved']) && allowedTo('send_topic');

	// Start this off for quick moderation - it will be or'd for each post.
	$context['can_remove_post'] = allowedTo('delete_any') || (allowedTo('delete_replies') && $context['user']['started']);

	// Can restore topic? That's if the topic is in the recycle board and has a previous restore state.
	$context['can_restore_topic'] &= !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board && !empty($topicinfo['id_previous_board']);
	$context['can_restore_msg'] &= !empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] == $board && !empty($topicinfo['id_previous_topic']);

	// Wireless shows a "more" if you can do anything special.
	if (WIRELESS)
	{
		$context['wireless_more'] = $context['can_sticky'] || $context['can_lock'] || allowedTo('modify_any');
		$context['wireless_moderate'] = isset($_GET['moderate']) ? ';moderate' : '';
	}

	// Load up the "double post" sequencing magic.
	if (!empty($options['display_quick_reply']))
	{
		checkSubmitOnce('register');
		$context['name'] = isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : '';
		$context['email'] = isset($_SESSION['guest_email']) ? $_SESSION['guest_email'] : '';

		loadSource('Class-Editor');
		$context['postbox'] = new wedit(
			array(
				'id' => 'message',
				'value' => '',
				'buttons' => array(
					array(
						'name' => 'post_button',
						'button_text' => $txt['post'],
						'onclick' => 'return submitThisOnce(this);',
						'accesskey' => 's',
					),
					array(
						'name' => 'preview',
						'button_text' => $txt['preview'],
						'onclick' => 'return submitThisOnce(this);',
						'accesskey' => 'p',
					),
				),
				// Add height and width for the editor. The textarea can be bigger if it's collapsed by default.
				'height' => $options['display_quick_reply'] == 2 ? '100px' : '150px',
				'width' => '100%',
				'drafts' => !allowedTo('save_post_draft') || empty($modSettings['masterSavePostDrafts']) ? 'none' : (!allowedTo('auto_save_post_draft') || empty($modSettings['masterAutoSavePostDrafts']) || !empty($options['disable_auto_save']) ? 'basic_post' : 'auto_post'),
				// Now, since we're custom styling these, we need our own divs. For shame!
				'custom_bbc_div' => 'bbcBox_message',
				'custom_smiley_div' => 'smileyBox_message',
			)
		);
	}

	// "Mini-menu's small in size, but it's very wise."
	$short_profiles = !empty($modSettings['pretty_filters']['profiles']);
	$context['user_menu'] = array();
	$context['user_menu_items_show'] = array();
	$context['user_menu_items'] = array(
		'pr' => array(
			'caption' => 'usermenu_profile',
			'action' => '\'\'',
			'class' => '\'profile_button\'',
		),
		'pm' => array(
			'caption' => 'usermenu_sendpm',
			'action' => '\'' . $scripturl . '?action=pm;sa=send;u=%id%\'',
			'class' => '\'pm_button\'',
		),
		'we' => array(
			'caption' => 'usermenu_website',
			'action' => '\'%special%\'',
			'class' => '\'www_button\'',
		),
		'po' => array(
			'caption' => 'usermenu_showposts',
			'action' => $short_profiles ? '\'?area=showposts\'' : '\'' . $scripturl . '?action=profile;u=%id%;area=showposts\'',
			'class' => '\'post_button\'',
		),
		'ab' => array(
			'caption' => 'usermenu_addbuddy',
			'action' => '\'' . $scripturl . '?action=buddy;u=%id%;' . $context['session_query'] . '\'',
			'class' => '\'contact_button\'',
		),
		'rb' => array(
			'caption' => 'usermenu_removebuddy',
			'action' => '\'' . $scripturl . '?action=buddy;u=%id%;' . $context['session_query'] . '\'',
			'class' => '\'contact_button\'',
		),
	);

	$context['action_menu'] = array();
	$context['action_menu_items_show'] = array();
	$context['action_menu_items'] = array(
		'ap' => array(
			'caption' => 'acme_approve',
			'action' => '\'' . $scripturl . '?action=moderate;area=postmod;sa=approve;topic=' . $context['current_topic'] . '.' . $context['start'] . ';msg=%id%;' . $context['session_query'] . '\'',
			'class' => '\'approve_button\'',
		),
		're' => array(
			'caption' => 'acme_remove',
			'action' => '\'' . $scripturl . '?action=deletemsg;topic=' . $context['current_topic'] . '.' . $context['start'] . ';msg=%id%;' . $context['session_query'] . '\'',
			'class' => '\'remove_button\'',
			'custom' => JavaScriptEscape('onclick="return confirm(' . JavaScriptEscape($txt['remove_message_confirm']) . ');"'),
		),
		'sp' => array(
			'caption' => 'acme_split',
			'action' => '\'' . $scripturl . '?action=splittopics;topic=' . $context['current_topic'] . ';at=%id%\'',
			'class' => '\'split_button\'',
		),
		'me' => array(
			'caption' => 'acme_merge',
			'action' => '\'' . $scripturl . '?action=mergeposts;pid=%id%;msgid=%special%;topic=' . $context['current_topic'] . '\'',
			'class' => '\'mergepost_button\'',
		),
		'rs' => array(
			'caption' => 'acme_restore',
			'action' => '\'' . $scripturl . '?action=restoretopic;msgs=%id%;' . $context['session_query'] . '\'',
			'class' => '\'restore_button\'',
		),
		'rp' => array(
			'caption' => 'acme_report',
			'action' => '\'' . $scripturl . '?action=report;topic=' . $context['current_topic'] . ';msg=%id%\'',
			'class' => '\'report_button\'',
		),
	);

	$su = '~' . preg_quote($scripturl, '~');
	// A total hack for pretty URLs... Wanna spend more processing time on this detail? I don't think so!
	if (!empty($modSettings['pretty_filters']['actions']))
	{
		foreach ($context['user_menu_items'] as &$user)
			$user['action'] = preg_replace($su . '\?action=([a-z]+);~', $boardurl . '/do/$1/?', $user['action']);
		foreach ($context['action_menu_items'] as &$action)
			$action['action'] = preg_replace($su . '\?action=([a-z]+);~', $boardurl . '/do/$1/?', $action['action']);
	}
}

// Callback for the message display.
function prepareDisplayContext($reset = false)
{
	global $settings, $txt, $modSettings, $scripturl, $options, $user_info, $board_info;
	global $memberContext, $context, $messages_request, $topic, $attachments, $topicinfo;

	static $counter = null, $can_pm = null, $profile_own = null, $profile_any = null, $buddy = null;

	// If the query returned false, bail.
	if ($messages_request == false)
		return false;

	// Remember which message this is, e.g. reply #83.
	if ($counter === null || $reset)
		$counter = empty($options['view_newest_first']) ? $context['start'] : $context['total_visible_posts'] - $context['start'];

	// Start from the beginning...
	if ($reset)
		return @wesql::data_seek($messages_request, 0);

	// Attempt to get the next message.
	$message = wesql::fetch_assoc($messages_request);
	if (!$message)
	{
		wesql::free_result($messages_request);
		return false;
	}

	call_hook('display_prepare_post', array(&$counter, &$message));

	// $context['icon_sources'] says where each icon should come from - here we set up the ones which will always exist!
	if (empty($context['icon_sources']))
	{
		$stable_icons = stable_icons();
		$context['icon_sources'] = array();
		foreach ($stable_icons as $icon)
			$context['icon_sources'][$icon] = 'images_url';
	}

	// Message Icon Management... check the images exist.
	if (!empty($modSettings['messageIconChecks_enable']))
	{
		// If the current icon isn't known, then we need to do something...
		if (!isset($context['icon_sources'][$message['icon']]))
			$context['icon_sources'][$message['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $message['icon'] . '.gif') ? 'images_url' : 'default_images_url';
	}
	elseif (!isset($context['icon_sources'][$message['icon']]))
		$context['icon_sources'][$message['icon']] = 'images_url';

	// If you're a lazy bum, you probably didn't give a subject...
	$message['subject'] = $message['subject'] != '' ? $message['subject'] : $txt['no_subject'];

	// Are you allowed to remove at least a single reply?
	$context['can_remove_post'] |= allowedTo('delete_own') && (empty($modSettings['edit_disable_time']) || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 >= time()) && $message['id_member'] == $user_info['id'];

	// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
	if (!loadMemberContext($message['id_member'], true))
	{
		// Notice this information isn't used anywhere else....
		$memberContext[$message['id_member']]['name'] = $message['poster_name'];
		$memberContext[$message['id_member']]['id'] = 0;
		$memberContext[$message['id_member']]['group'] = $txt['guest_title'];
		// Wedge supports showing guest posts, grouping them by e-mail address. Can restrict to current board: add ;only=$context['current_board']
		$memberContext[$message['id_member']]['href'] = $scripturl . '?action=profile;u=0;area=showposts;guest=' . base64_encode($message['poster_name']);
		$memberContext[$message['id_member']]['link'] = '<a href="' . $memberContext[$message['id_member']]['href'] . '">' . $message['poster_name'] . '</a>';
		$memberContext[$message['id_member']]['email'] = $message['poster_email'];
		$memberContext[$message['id_member']]['show_email'] = showEmailAddress(true, 0);
		$memberContext[$message['id_member']]['is_guest'] = true;
	}
	else
	{
		$memberContext[$message['id_member']]['can_view_profile'] = allowedTo('profile_view_any') || ($message['id_member'] == $user_info['id'] && allowedTo('profile_view_own'));
		$memberContext[$message['id_member']]['is_topic_starter'] = $message['id_member'] == $context['topic_starter_id'];
		$memberContext[$message['id_member']]['can_see_warning'] = !isset($context['disabled_fields']['warning_status']) && $memberContext[$message['id_member']]['warning_status'] && ($context['user']['can_mod'] || (!$user_info['is_guest'] && !empty($modSettings['warning_show']) && ($modSettings['warning_show'] > 1 || $message['id_member'] == $user_info['id'])));
	}

	$memberContext[$message['id_member']]['ip'] = format_ip($message['poster_ip']);

	// Do the censor thang.
	censorText($message['body']);
	censorText($message['subject']);

	$merge_max_limit_length = true;
	$merge_safe = false;

	// Avoid having too large a post if we do any merger.
	if (empty($modSettings['merge_post_ignore_length']) && $modSettings['max_messageLength'] > 0)
	{
		// Calculating the length...
		if (!isset($context['correct_post_length']))
			$context['correct_post_length'] = westr::strlen(empty($modSettings['merge_post_no_sep']) ? (empty($modSettings['merge_post_no_time']) ?
				'<br>[size=1][mergedate]' . $message['modified_time'] . '[/mergedate][/size]' : '') . '[hr]<br>' : '<br>');

		$context['current_post_length'] = westr::strlen(un_htmlspecialchars($message['body']));
		if (!isset($context['last_post_length']))
			$context['last_post_length'] = 0;
		else
			$merge_safe = ($context['current_post_length'] + $context['last_post_length'] + $context['correct_post_length']) < $modSettings['max_messageLength'];
	}
	else
		$context['current_post_length'] = 0;

	// Run BBC interpreter on the message.
	$message['body'] = parse_bbc($message['body'], $message['smileys_enabled'], $message['id_msg']);

	// Compose the memory eat- I mean message array.
	$output = array(
		'attachment' => loadAttachmentContext($message['id_msg']),
		'alternate' => $counter % 2,
		'id' => $message['id_msg'],
		'href' => $scripturl . '?topic=' . $topic . '.msg' . $message['id_msg'] . '#msg' . $message['id_msg'],
		'link' => '<a href="' . $scripturl . '?topic=' . $topic . '.msg' . $message['id_msg'] . '#msg' . $message['id_msg'] . '" rel="nofollow">' . $message['subject'] . '</a>',
		'member' => &$memberContext[$message['id_member']],
		'icon' => $message['icon'],
		'icon_url' => $settings[$context['icon_sources'][$message['icon']]] . '/post/' . $message['icon'] . '.gif',
		'subject' => $message['subject'],
		'time' => on_timeformat($message['poster_time']),
		'timestamp' => forum_time(true, $message['poster_time']),
		'counter' => $board_info['type'] == 'board' ? $counter : ($counter == $context['start'] ? 0 : $counter),
		'modified' => array(
			'time' => on_timeformat($message['modified_time']),
			'timestamp' => forum_time(true, $message['modified_time']),
			'name' => $message['modified_name']
		),
		'body' => $message['body'],
		'new' => empty($message['is_read']),
		'approved' => $message['approved'],
		'first_new' => isset($context['start_from']) && $context['start_from'] == $counter,
		'is_ignored' => !empty($modSettings['enable_buddylist']) && !empty($options['posts_apply_ignore_list']) && in_array($message['id_member'], $context['user']['ignoreusers']),
		'can_approve' => !$message['approved'] && $context['can_approve'],
		'can_unapprove' => $message['approved'] && $context['can_approve'],
		'can_modify' => (!$context['is_locked'] || allowedTo('moderate_board')) && (allowedTo('modify_any') || (allowedTo('modify_replies') && $context['user']['started']) || (allowedTo('modify_own') && $message['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || !$message['approved'] || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time()))),
		'can_remove' => allowedTo('delete_any') || (allowedTo('delete_replies') && $context['user']['started']) || (allowedTo('delete_own') && $message['id_member'] == $user_info['id'] && (empty($modSettings['edit_disable_time']) || $message['poster_time'] + $modSettings['edit_disable_time'] * 60 > time())),
		'can_see_ip' => allowedTo('view_ip_address_any') || (!empty($user_info['id']) && $message['id_member'] == $user_info['id'] && allowedTo('view_ip_address_own')),
		'can_mergeposts' => $merge_safe && !empty($context['last_user_id']) && $context['last_user_id'] == (empty($message['id_member']) ? (empty($message['poster_email']) ? $message['poster_name'] : $message['poster_email']) : $message['id_member']) && (allowedTo('modify_any') || (allowedTo('modify_own') && $message['id_member'] == $user_info['id'])),
		'last_post_id' => $context['last_msg_id'],
	);

	$output['can_mergeposts'] &= !empty($output['last_post_id']);

	// Is this a board? If not, we're dealing with this as replies to a post, and we won't allow merging the first reply into the post.
	if ($board_info['type'] != 'board')
		$output['can_mergeposts'] &= $counter != 1;

	// Is this user the message author?
	$output['is_message_author'] = $is_me = $message['id_member'] == $user_info['id'];

	if (!empty($message['id_member']))
		$context['last_user_id'] = $message['id_member'];
	// If you're the admin, you can merge guest posts
	elseif ($user_info['is_admin'])
		$context['last_user_id'] = $message['poster_email'];
	$context['last_msg_id'] = $message['id_msg'];
	$context['last_post_length'] = $context['current_post_length'];

	// Now, to business. Is it not a guest, and we haven't done this before?
	if ($output['member']['id'] != 0 && !isset($context['user_menu'][$output['member']['id']]))
	{
		// 1. Preparation, since we'd rather not figure this stuff out time and again if we can help it.
		if ($can_pm === null)
		{
			$can_pm = allowedTo('pm_send');
			$profile_own = allowedTo('profile_view_own');
			$profile_any = allowedTo('profile_view_any');
			$buddy = allowedTo('profile_identity_own') && !empty($modSettings['enable_buddylist']);
		}

		// 2. Figure out that user's menu to the stack. It may be different if it's our menu.
		// Start by putting the user's website URL.
		$menu = array(!empty($output['member']['website']['url']) ? $output['member']['website']['url'] : '');
		if ($is_me)
		{
			// Can't PM, email, add to buddy list
			if ($profile_own)
				$menu[] = 'pr';
			if (!empty($output['member']['website']['url']))
				$menu[] = 'we';
			if ($profile_own)
				$menu[] = 'po';
		}
		else
		{
			if ($profile_any)
				$menu[] = 'pr';
			if ($can_pm)
				$menu[] = 'pm';
			if (!empty($output['member']['website']['url']))
				$menu[] = 'we';
			if ($profile_any)
				$menu[] = 'po';
			if ($buddy)
				$menu[] = $memberContext[$message['id_member']]['is_buddy'] ? 'rb' : 'ab';
		}

		// If we can't do anything, it's not even worth recording the user's website...
		if (count($menu) > 1)
		{
			$context['user_menu'][$output['member']['id']] = $menu;
			$context['user_menu_items_show'] += array_flip($menu);
		}
	}

	// Bit longer, but this should be helpful too... The per-post menu.
	if ($output['member']['id'] != 0)
	{
		// Start by putting the last message's id, for merging purposes.
		$menu = array($output['last_post_id']);

		// Maybe we can approve it, maybe we should?
		if ($output['can_approve'])
			$menu[] = 'ap';

		// How about... even... remove it entirely?!
		if ($output['can_remove'])
			$menu[] = 're';

		// What about splitting it off the rest of the topic?
		if ($context['can_split'] && !empty($context['real_num_replies']))
			$menu[] = 'sp';

		// Can we merge this post to the previous one? (Normally requires same author)
		if ($output['can_mergeposts'])
			$menu[] = 'me';

		// Can we restore topics?
		if ($context['can_restore_msg'])
			$menu[] = 'rs';

		if ($context['can_report_moderator'] && !$is_me)
			$menu[] = 'rp';

		// If we can't do anything, it's not even worth recording the last message ID...
		if (count($menu) > 1)
		{
			$context['action_menu'][$output['id']] = $menu;
			$context['action_menu_items_show'] += array_flip($menu);
		}
	}

	// Don't forget to set this to true in the following hook if you're going to add a non-menu button.
	$output['has_buttons'] = $context['can_quote'] || $output['can_modify'] || !empty($context['action_menu'][$output['id']]);

	call_hook('display_post_done', array(&$counter, &$output));

	if (empty($options['view_newest_first']))
		$counter++;
	else
		$counter--;

	return $output;
}

function loadAttachmentContext($id_msg)
{
	global $attachments, $modSettings, $txt, $scripturl, $topic;

	// Set up the attachment info - based on code by Meriadoc.
	$attachmentData = array();
	$have_unapproved = false;
	if (isset($attachments[$id_msg]) && !empty($modSettings['attachmentEnable']))
	{
		foreach ($attachments[$id_msg] as $i => $attachment)
		{
			$attachmentData[$i] = array(
				'id' => $attachment['id_attach'],
				'name' => preg_replace('~&amp;#(\\d{1,7}|x[0-9a-fA-F]{1,6});~', '&#\\1;', htmlspecialchars($attachment['filename'])),
				'downloads' => $attachment['downloads'],
				'size' => round($attachment['filesize'] / 1024, 2) . ' ' . $txt['kilobyte'],
				'byte_size' => $attachment['filesize'],
				'href' => $scripturl . '?action=dlattach;topic=' . $topic . '.0;attach=' . $attachment['id_attach'],
				'link' => '<a href="' . $scripturl . '?action=dlattach;topic=' . $topic . '.0;attach=' . $attachment['id_attach'] . '">' . htmlspecialchars($attachment['filename']) . '</a>',
				'is_image' => !empty($attachment['width']) && !empty($attachment['height']) && !empty($modSettings['attachmentShowImages']),
				'is_approved' => $attachment['approved'],
			);

			// If something is unapproved we'll note it so we can sort them.
			if (!$attachment['approved'])
				$have_unapproved = true;

			if (!$attachmentData[$i]['is_image'])
				continue;

			$attachmentData[$i]['real_width'] = $attachment['width'];
			$attachmentData[$i]['width'] = $attachment['width'];
			$attachmentData[$i]['real_height'] = $attachment['height'];
			$attachmentData[$i]['height'] = $attachment['height'];

			// Let's see, do we want thumbs?
			if (!empty($modSettings['attachmentThumbnails']) && !empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight']) && ($attachment['width'] > $modSettings['attachmentThumbWidth'] || $attachment['height'] > $modSettings['attachmentThumbHeight']) && strlen($attachment['filename']) < 249)
			{
				// A proper thumb doesn't exist yet? Create one!
				if (empty($attachment['id_thumb']) || $attachment['thumb_width'] > $modSettings['attachmentThumbWidth'] || $attachment['thumb_height'] > $modSettings['attachmentThumbHeight'] || ($attachment['thumb_width'] < $modSettings['attachmentThumbWidth'] && $attachment['thumb_height'] < $modSettings['attachmentThumbHeight']))
				{
					$filename = getAttachmentFilename($attachment['filename'], $attachment['id_attach'], $attachment['id_folder']);

					loadSource('Subs-Graphics');
					if (createThumbnail($filename, $modSettings['attachmentThumbWidth'], $modSettings['attachmentThumbHeight']))
					{
						// So what folder are we putting this image in?
						if (!empty($modSettings['currentAttachmentUploadDir']))
						{
							if (!is_array($modSettings['attachmentUploadDir']))
								$modSettings['attachmentUploadDir'] = @unserialize($modSettings['attachmentUploadDir']);
							$path = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
							$id_folder_thumb = $modSettings['currentAttachmentUploadDir'];
						}
						else
						{
							$path = $modSettings['attachmentUploadDir'];
							$id_folder_thumb = 1;
						}

						// Calculate the size of the created thumbnail.
						$size = @getimagesize($filename . '_thumb');
						list ($attachment['thumb_width'], $attachment['thumb_height']) = $size;
						$thumb_size = filesize($filename . '_thumb');

						// These are the only valid image types for Wedge.
						$validImageTypes = array(1 => 'gif', 2 => 'jpeg', 3 => 'png', 5 => 'psd', 6 => 'bmp', 7 => 'tiff', 8 => 'tiff', 9 => 'jpeg', 14 => 'iff');

						// What about the extension?
						$thumb_ext = isset($validImageTypes[$size[2]]) ? $validImageTypes[$size[2]] : '';

						// Figure out the mime type.
						if (!empty($size['mime']))
							$thumb_mime = $size['mime'];
						else
							$thumb_mime = 'image/' . $thumb_ext;

						$thumb_filename = $attachment['filename'] . '_thumb';
						$thumb_hash = getAttachmentFilename($thumb_filename, false, null, true);

						// Add this beauty to the database.
						wesql::insert('',
							'{db_prefix}attachments',
							array('id_folder' => 'int', 'id_msg' => 'int', 'attachment_type' => 'int', 'filename' => 'string', 'file_hash' => 'string', 'size' => 'int', 'width' => 'int', 'height' => 'int', 'fileext' => 'string', 'mime_type' => 'string'),
							array($id_folder_thumb, $id_msg, 3, $thumb_filename, $thumb_hash, (int) $thumb_size, (int) $attachment['thumb_width'], (int) $attachment['thumb_height'], $thumb_ext, $thumb_mime),
							array('id_attach')
						);
						$old_id_thumb = $attachment['id_thumb'];
						$attachment['id_thumb'] = wesql::insert_id();
						if (!empty($attachment['id_thumb']))
						{
							wesql::query('
								UPDATE {db_prefix}attachments
								SET id_thumb = {int:id_thumb}
								WHERE id_attach = {int:id_attach}',
								array(
									'id_thumb' => $attachment['id_thumb'],
									'id_attach' => $attachment['id_attach'],
								)
							);

							$thumb_realname = getAttachmentFilename($thumb_filename, $attachment['id_thumb'], $id_folder_thumb, false, $thumb_hash);
							rename($filename . '_thumb', $thumb_realname);

							// Do we need to remove an old thumbnail?
							if (!empty($old_id_thumb))
							{
								loadSource('ManageAttachments');
								removeAttachments(array('id_attach' => $old_id_thumb), '', false, false);
							}
						}
					}
				}

				// Only adjust dimensions on successful thumbnail creation.
				if (!empty($attachment['thumb_width']) && !empty($attachment['thumb_height']))
				{
					$attachmentData[$i]['width'] = $attachment['thumb_width'];
					$attachmentData[$i]['height'] = $attachment['thumb_height'];
				}
			}

			if (!empty($attachment['id_thumb']))
				$attachmentData[$i]['thumbnail'] = array(
					'id' => $attachment['id_thumb'],
					'href' => $scripturl . '?action=dlattach;topic=' . $topic . '.0;attach=' . $attachment['id_thumb'] . ';image',
				);
			$attachmentData[$i]['thumbnail']['has_thumb'] = !empty($attachment['id_thumb']);

			// If thumbnails are disabled, check the maximum size of the image.
			if (!$attachmentData[$i]['thumbnail']['has_thumb'] && ((!empty($modSettings['max_image_width']) && $attachment['width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $attachment['height'] > $modSettings['max_image_height'])))
			{
				if (!empty($modSettings['max_image_width']) && (empty($modSettings['max_image_height']) || $attachment['height'] * $modSettings['max_image_width'] / $attachment['width'] <= $modSettings['max_image_height']))
				{
					$attachmentData[$i]['width'] = $modSettings['max_image_width'];
					$attachmentData[$i]['height'] = floor($attachment['height'] * $modSettings['max_image_width'] / $attachment['width']);
				}
				elseif (!empty($modSettings['max_image_width']))
				{
					$attachmentData[$i]['width'] = floor($attachment['width'] * $modSettings['max_image_height'] / $attachment['height']);
					$attachmentData[$i]['height'] = $modSettings['max_image_height'];
				}
			}
			elseif ($attachmentData[$i]['thumbnail']['has_thumb'])
			{
				// If the image is too large to show inline, make it a popup.
				if (((!empty($modSettings['max_image_width']) && $attachmentData[$i]['real_width'] > $modSettings['max_image_width']) || (!empty($modSettings['max_image_height']) && $attachmentData[$i]['real_height'] > $modSettings['max_image_height'])))
					$attachmentData[$i]['thumbnail']['javascript'] = 'return reqWin(\'' . $attachmentData[$i]['href'] . ';image\', ' . ($attachment['width'] + 20) . ', ' . ($attachment['height'] + 20) . ', true);';
				else
					$attachmentData[$i]['thumbnail']['javascript'] = 'return expandThumb(' . $attachment['id_attach'] . ');';
			}

			if (!$attachmentData[$i]['thumbnail']['has_thumb'])
				$attachmentData[$i]['downloads']++;
		}
	}

	// Do we need to instigate a sort?
	if ($have_unapproved)
		usort($attachmentData, 'approved_attach_sort');

	return $attachmentData;
}

// A sort function for putting unapproved attachments first.
function approved_attach_sort($a, $b)
{
	if ($a['is_approved'] == $b['is_approved'])
		return 0;

	return $a['is_approved'] > $b['is_approved'] ? -1 : 1;
}

// In-topic quick moderation.
function QuickInTopicModeration()
{
	global $topic, $board, $user_info, $modSettings, $context;

	// Check the session = get or post.
	checkSession('request');

	loadSource('RemoveTopic');

	if (empty($_REQUEST['msgs']))
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);

	$messages = array();
	foreach ($_REQUEST['msgs'] as $dummy)
		$messages[] = (int) $dummy;

	// We are restoring messages. We handle this in another place.
	if (isset($_REQUEST['restore_selected']))
		redirectexit('action=restoretopic;msgs=' . implode(',', $messages) . ';' . $context['session_query']);

	// Allowed to delete any message?
	if (allowedTo('delete_any'))
		$allowed_all = true;
	// Allowed to delete replies to their messages?
	elseif (allowedTo('delete_replies'))
	{
		$request = wesql::query('
			SELECT id_member_started
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => $topic,
			)
		);
		list ($starter) = wesql::fetch_row($request);
		wesql::free_result($request);

		$allowed_all = $starter == $user_info['id'];
	}
	else
		$allowed_all = false;

	// Make sure they're allowed to delete their own messages, if not any.
	if (!$allowed_all)
		isAllowedTo('delete_own');

	// Allowed to remove which messages?
	$request = wesql::query('
		SELECT id_msg, subject, id_member, poster_time
		FROM {db_prefix}messages
		WHERE id_msg IN ({array_int:message_list})
			AND id_topic = {int:current_topic}' . (!$allowed_all ? '
			AND id_member = {int:current_member}' : '') . '
		LIMIT ' . count($messages),
		array(
			'current_member' => $user_info['id'],
			'current_topic' => $topic,
			'message_list' => $messages,
		)
	);
	$messages = array();
	while ($row = wesql::fetch_assoc($request))
	{
		if (!$allowed_all && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + $modSettings['edit_disable_time'] * 60 < time())
			continue;

		$messages[$row['id_msg']] = array($row['subject'], $row['id_member']);
	}
	wesql::free_result($request);

	// Get the first message in the topic - because you can't delete that!
	$request = wesql::query('
		SELECT id_first_msg, id_last_msg
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($first_message, $last_message) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Delete all the messages we know they can delete. ($messages)
	foreach ($messages as $message => $info)
	{
		// Just skip the first message - if it's not the last.
		if ($message == $first_message && $message != $last_message)
			continue;
		// If the first message is going then don't bother going back to the topic as we're effectively deleting it.
		elseif ($message == $first_message)
			$topicGone = true;

		removeMessage($message);

		// Log this moderation action ;).
		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info[1] != $user_info['id']))
			logAction('delete', array('topic' => $topic, 'subject' => $info[0], 'member' => $info[1], 'board' => $board));
	}

	redirectexit(!empty($topicGone) ? 'board=' . $board : 'topic=' . $topic . '.' . $_REQUEST['start']);
}

?>