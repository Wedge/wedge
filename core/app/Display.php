<?php
/**
 * Displays a single topic and paginates the posts within.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This is perhaps the most important and probably most accessed files
	in all of Wedge. This file controls topic and message display.
	It does so with the following functions:

	void Display()
		- loads the posts in a topic up so they can be displayed.
		- uses the main block of the Display template.
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

	void QuickInTopicModeration()
		- in-topic quick moderation.

*/

// The central part of the board - topic display.
function Display()
{
	global $txt, $context, $settings;
	global $options, $board_info, $topic, $board;
	global $attachments, $messages_request, $topicinfo;

	// What are you gonna display if these are empty?!
	if (empty($topic))
		fatal_lang_error('no_board', false);

	// 301 redirects on old-school queries like "?topic=242.0"
	// !!! Should we just be taking the original HTTP var and redirect to it?
	if ((isset($context['pretty']['oldschoolquery']) || $_SERVER['HTTP_HOST'] != $board_info['url']) && !empty($settings['pretty_filters']['topics']))
	{
		$url = 'topic=' . $topic . '.' . (isset($_REQUEST['start']) ? $_REQUEST['start'] : '0') . (isset($_REQUEST['seen']) ? ';seen' : '') . (isset($_REQUEST['all']) ? ';all' : '') . (isset($_REQUEST['viewresults']) ? ';viewresults' : '');
		header('HTTP/1.1 301 Moved Permanently');
		redirectexit($url, false);
	}

	// Not only does a prefetch make things slower for the server, but it makes it impossible to know if they read it.
	preventPrefetch();

	// How much are we sticking on each page?
	$context['messages_per_page'] = empty($settings['disableCustomPerPage']) && !empty($options['messages_per_page']) ? $options['messages_per_page'] : $settings['defaultMaxMessages'];

	// Let's do some work on what to search index.
	if (count($_GET) > 2)
		foreach ($_GET as $k => $v)
			if (!in_array($k, array('topic', 'board', 'start', session_name())))
				$context['robot_no_index'] = true;

	if (!empty($_REQUEST['start']) && (!is_numeric($_REQUEST['start']) || $_REQUEST['start'] % $context['messages_per_page'] != 0))
		$context['robot_no_index'] = true;

	// OK, set up for the joy that is meta description. The rest we do in the bowels of prepareDisplayContext.
	$context['meta_description'] = '<META-DESCRIPTION>';

	// Add 1 to the number of views of this topic.
	if (!we::$user['possibly_robot'] && (empty($_SESSION['last_read_topic']) || $_SESSION['last_read_topic'] != $topic))
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
			t.num_replies, t.num_views, t.locked, ms.subject, t.is_pinned, t.id_poll, t.id_member_started, ms.icon,
			t.id_first_msg, t.id_last_msg, t.approved, t.unapproved_posts, t.privacy, ms.poster_time, ms.data AS msgdata,
			' . (we::$is_guest ? 't.id_last_msg + 1' : 'IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1') . ' AS new_from
			' . (!empty($settings['recycle_board']) && $settings['recycle_board'] == $board ? ', id_previous_board, id_previous_topic' : '') . '
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)' . (we::$is_guest ? '' : '
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:current_topic} AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})') . '
		WHERE t.id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_member' => MID,
			'current_topic' => $topic,
			'current_board' => $board,
		)
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('not_a_topic', false);
	$topicinfo = wesql::fetch_assoc($request);
	wesql::free_result($request);

	$topicinfo['msgdata'] = !empty($topicinfo['msgdata']) ? unserialize($topicinfo['msgdata']) : array();
	if (!empty($topicinfo['msgdata']['mv_brd']))
	{
		// This topic was moved. Hrm. Can we see this board?
		if (!we::$is['admin'] && !in_array($topicinfo['msgdata']['mv_brd'], we::$user['qsb_boards']))
			fatal_lang_error('moved_no_access', false);

		// Failing that, are we doing an instant redirect?
		if (!empty($topicinfo['msgdata']['mv_tpc']))
			redirectexit('topic=' . $topicinfo['msgdata']['mv_tpc']);
	}

	// If the first message's icon is 'moved', it's a moved notice. This should not, in itself, be indexed.
	if ($topicinfo['icon'] == 'moved')
		$context['robot_no_index'] = true;

	// Load the proper template and/or block.
	loadTemplate('Display'); // Topic page
	loadTemplate('Msg'); // Message skeleton
	wetem::load(
		array(
			'report_success',
			'display_draft',
			'title_upper',
			'postlist' => array(
				'display_posts',
			),
			'title_lower',
			'mod_buttons',
			'quick_reply'
		)
	);

	$context['real_num_replies'] = $context['num_replies'] = $topicinfo['num_replies'];
	$context['topic_first_message'] = $topicinfo['id_first_msg'];
	$context['topic_last_message'] = $topicinfo['id_last_msg'];

	// Add up unapproved replies to get real number of replies...
	if ($settings['postmod_active'] && allowedTo('approve_posts'))
		$context['real_num_replies'] += $topicinfo['unapproved_posts'] - ($topicinfo['approved'] ? 0 : 1);

	// If this topic has unapproved posts, we need to work out how many posts the user can see, for page indexing.
	// We also need to discount the first post if this is a blog board.
	$including_first = $topicinfo['approved'] && $board_info['type'] == 'forum' ? 1 : 0;
	if ($settings['postmod_active'] && $topicinfo['unapproved_posts'] && we::$is_member && !allowedTo('approve_posts'))
	{
		$request = wesql::query('
			SELECT COUNT(id_member) AS my_unapproved_posts
			FROM {db_prefix}messages
			WHERE id_topic = {int:current_topic}
				AND id_member = {int:current_member}
				AND approved = 0',
			array(
				'current_topic' => $topic,
				'current_member' => MID,
			)
		);
		list ($myUnapprovedPosts) = wesql::fetch_row($request);
		wesql::free_result($request);

		$context['total_visible_posts'] = $context['num_replies'] + $myUnapprovedPosts + $including_first;
	}
	else
		$context['total_visible_posts'] = $context['num_replies'] + $topicinfo['unapproved_posts'] + $including_first;

	// The start isn't a number; it's information about what to do, where to go.
	if (!is_numeric($_REQUEST['start']))
	{
		// Redirect to the page and post with new messages, originally by Omar Bazavilvazo.
		if ($_REQUEST['start'] === 'new')
		{
			// Guests automatically go to the last post.
			if (we::$is_guest)
				$_REQUEST['start'] = 'msg' . $topicinfo['id_last_msg'];
			else
			{
				// Find the earliest unread message in the topic. The use of topics here is just for both tables.
				$request = wesql::query('
					SELECT IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1
					FROM {db_prefix}topics AS t
						LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = {int:current_topic} AND lt.id_member = {int:current_member})
						LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = {int:current_board} AND lmr.id_member = {int:current_member})
					WHERE t.id_topic = {int:current_topic}
					LIMIT 1',
					array(
						'current_board' => $board,
						'current_member' => MID,
						'current_topic' => $topic,
					)
				);
				list ($new_from) = wesql::fetch_row($request);
				wesql::free_result($request);

				// Fall through to the next if statement.
				$_REQUEST['start'] = 'msg' . $new_from;
			}
		}

		// Link to a message...
		if (strpos($_REQUEST['start'], 'msg') === 0)
		{
			$virtual_msg = (int) substr($_REQUEST['start'], 3);
			if (!$topicinfo['unapproved_posts'] && $virtual_msg >= $topicinfo['id_last_msg'])
				$context['start_from'] = $context['total_visible_posts'] - 1 + ($board_info['type'] == 'forum' ? 0 : 1);
			elseif (!$topicinfo['unapproved_posts'] && $virtual_msg <= $topicinfo['id_first_msg'])
				$context['start_from'] = 0;
			else
			{
				// Find the start value for that message......
				$request = wesql::query('
					SELECT COUNT(*)
					FROM {db_prefix}messages
					WHERE id_msg < {int:virtual_msg}
						AND id_topic = {int:current_topic}' . ($settings['postmod_active'] && $topicinfo['unapproved_posts'] && !allowedTo('approve_posts') ? '
						AND (approved = {int:is_approved}' . (we::$is_guest ? '' : ' OR id_member = {int:current_member}') . ')' : ''),
					array(
						'current_member' => MID,
						'current_topic' => $topic,
						'virtual_msg' => $virtual_msg,
						'is_approved' => 1,
					)
				);
				list ($context['start_from']) = wesql::fetch_row($request);
				wesql::free_result($request);
				if ($board_info['type'] != 'forum')
					$context['start_from']--;
			}

			// We need to reverse the start as well in this case.
			$_REQUEST['start'] = empty($options['view_newest_first']) ? $context['start_from'] : $context['total_visible_posts'] - $context['start_from'] - 1;
		}
	}

	// No use in calculating the next topic if there's only one.
	if (!empty($settings['enablePreviousNext']) && $board_info['num_topics'] > 1)
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

		$request = wesql::query('
			(
				SELECT t.id_topic, m.subject, 1
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}messages AS m ON (t.id_first_msg = m.id_msg)
				WHERE t.id_topic = (
					SELECT t.id_topic' . (isset($sort_methods[$sort_by]['select']) ? $sort_methods[$sort_by]['select'] : '') . '
					FROM {db_prefix}topics AS t' . (isset($sort_methods[$sort_by]['join']) ? $sort_methods[$sort_by]['join'] : '') . '
					WHERE {query_see_topic}
						AND t.id_board = {int:current_board}
						AND ((' . $sort . ' ' . $sort_methods[$sort_by]['cmp'] . ' AND t.is_pinned >= {int:current_pinned}) OR t.is_pinned > {int:current_pinned})
					ORDER BY t.is_pinned' . ($ascending ? ' DESC' : '') . ', ' . $sort . ($ascending ? ' DESC' : '') . '
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
					WHERE {query_see_topic}
						AND t.id_board = {int:current_board}
						AND ((' . $sort . ' ' . str_replace('>', '<', $sort_methods[$sort_by]['cmp']) . ' AND t.is_pinned <= {int:current_pinned}) OR t.is_pinned < {int:current_pinned})
					ORDER BY t.is_pinned' . (!$ascending ? ' DESC' : '') . ', ' . $sort . (!$ascending ? ' DESC' : '') . '
					LIMIT 1
				)
			)',
			array(
				'current_board' => $board,
				'current_member' => MID,
				'current_topic' => $topic,
				'current_subject' => $topicinfo['subject'],
				'current_replies' => $topicinfo['num_replies'],
				'current_views' => $topicinfo['num_views'],
				'current_first_msg' => $topicinfo['id_first_msg'],
				'current_last_msg' => $topicinfo['id_last_msg'],
				'current_pinned' => $topicinfo['is_pinned'],
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
	}

	// Create a previous/next string if the selected theme has it as a selected option.
	$short_prev = empty($prev_title) ? '' : westr::cut($prev_title, SKIN_MOBILE ? 20 : 60);
	$short_next = empty($next_title) ? '' : westr::cut($next_title, SKIN_MOBILE ? 20 : 60);
	$context['prevnext_prev'] = '
			<div class="prevnext_prev">' . (empty($prev_topic) ? '' : '&laquo;&nbsp;<a href="<URL>?topic=' . $prev_topic . '.new#new"' . ($prev_title != $short_prev ? ' title="' . $prev_title . '"' : '') . '>' . $short_prev . '</a>') . '</div>';
	$context['prevnext_next'] = '
			<div class="prevnext_next">' . (empty($next_topic) ? '' : '<a href="<URL>?topic=' . $next_topic . '.new#new"' . ($next_title != $short_next ? ' title="' . $next_title . '"' : '') . '>' . $short_next . '</a>&nbsp;&raquo;') . '</div>';
	$context['no_prevnext'] = empty($prev_topic) && empty($next_topic);

	// Do we need to show the visual verification image?
	$context['require_verification'] = !we::$is['mod'] && !we::$is_admin && !empty($settings['posts_require_captcha']) && (we::$user['posts'] < $settings['posts_require_captcha'] || (we::$is_guest && $settings['posts_require_captcha'] == -1));
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
	$context['signature_enabled'] = $settings['signature_settings'][0] == 1;
	$context['disabled_fields'] = isset($settings['disabled_profile_fields']) ? array_flip(explode(',', $settings['disabled_profile_fields'])) : array();

	// Censor the title...
	censorText($topicinfo['subject']);
	$context['page_title'] = $topicinfo['subject'];

	wetem::add('sidebar', 'display_statistics');

	// Default this topic to not marked for notifications... of course...
	$context['is_marked_notify'] = false;

	// Did we report a post to a moderator just now?
	if ($context['report_sent'] = isset($_GET['reportsent']))
		loadLanguage('Post');

	// Did someone save a conventional draft?
	if ($context['draft_saved'] = isset($_GET['draftsaved']))
		loadLanguage('Post');

	// Let's get nosey, who is viewing this topic?
	if (!empty($settings['display_who_viewing']))
	{
		loadSource('Subs-MembersOnline');
		getMembersOnlineDetails('topic');
		wetem::add('sidebar', 'display_whoviewing');
	}

	// If all is set, but not allowed... just unset it.
	$can_show_all = !empty($settings['enableAllMessages']) && $context['total_visible_posts'] > $context['messages_per_page'] && $context['total_visible_posts'] < $settings['enableAllMessages'];
	if (isset($_REQUEST['all']) && !$can_show_all)
		unset($_REQUEST['all']);
	// Otherwise, it must be allowed...
	elseif (isset($_REQUEST['all']))
		$context['messages_per_page'] = $context['total_visible_posts'];

	// !! Because template_page_index will overwrite $_REQUEST['start'], it's a good idea to store it before.
	// @todo: make use of this...
	$start_index = $_REQUEST['start'];
	$_REQUEST['start'] = max(0, $_REQUEST['start']);

	// Construct the page index, allowing for the .START method...
	$context['page_index'] = template_page_index('<URL>?topic=' . $topic . '.%1$d', $_REQUEST['start'], $context['total_visible_posts'], $context['messages_per_page'], true);
	$context['start'] = $_REQUEST['start'];

	// Figure out the previous/next links for header <link>.
	// !! Doesn't work in View Newest First mode...
	$context['links'] = array(
		'prev' => $_REQUEST['start'] >= $context['messages_per_page'] ? '<URL>?topic=' . $topic . '.' . ($_REQUEST['start'] - $context['messages_per_page']) : '',
		'next' => $_REQUEST['start'] + $context['messages_per_page'] < $context['total_visible_posts'] ? '<URL>?topic=' . $topic. '.' . ($_REQUEST['start'] + $context['messages_per_page']) : '',
	);

	// If they are viewing all the posts, show all the posts, otherwise limit the number.
	if ($can_show_all)
	{
		if (isset($_REQUEST['all']))
			$context['page_index'] = str_replace('[<strong>1</strong>]', '[<strong>' . $txt['all_pages'] . '</strong>]', $context['page_index']);
		// They aren't using it, but the *option* is there, at least.
		else
			$context['page_index'] .= '<a href="<URL>?topic=' . $topic . '.0;all">' . $txt['all_pages'] . '</a>';
	}

	// Build the link tree.
	add_linktree($topicinfo['subject'], '<URL>?topic=' . $topic . '.0');

	// Build a list of this board's moderators.
	$context['moderators'] =& $board_info['moderators'];
	$context['link_moderators'] = array();
	if (!empty($board_info['moderators']))
	{
		wetem::add('sidebar', 'display_staff');
		foreach ($board_info['moderators'] as $mod)
			$context['link_moderators'][] = '<a href="<URL>?action=profile;u=' . $mod['id'] . '" title="' . $txt['board_moderator'] . '">' . $mod['name'] . '</a>';
	}

	// Show linktree at the page foot too.
	$context['bottom_linktree'] = true;

	// Information about the current topic...
	$context['is_locked'] = $topicinfo['locked'];
	$context['is_pinned'] = $topicinfo['is_pinned'];
	$context['is_approved'] = $topicinfo['approved'];

	$context['is_poll'] = $topicinfo['id_poll'] > 0 && allowedTo('poll_view');

	// Did this user start the topic or not?
	we::$user['started'] = MID == $topicinfo['id_member_started'] && we::$is_member;
	$context['topic_starter_id'] = $topicinfo['id_member_started'];

	// Set the topic's information for the template.
	$context['subject'] = $topicinfo['subject'];
	$context['num_views'] = $topicinfo['num_views'];

	// Set a canonical URL for this page.
	$context['canonical_url'] = '<URL>?topic=' . $topic . '.' . $context['start'] . ($can_show_all ? ';all' : '');

	// For quick reply we need a response prefix in the default forum language.
	getRePrefix();

	// Create the poll info if it exists.
	if ($context['is_poll'])
	{
		// Get the question and if it's locked.
		$request = wesql::query('
			SELECT
				p.question, p.voting_locked, p.hide_results, p.voters_visible, p.expire_time, p.max_votes, p.change_vote,
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
				'current_member' => MID,
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
			$pollOptions[$row['id_choice']]['voters'] = array();
			$realtotal += $row['votes'];
			$pollinfo['has_voted'] |= $row['voted_this'] != -1;
		}
		wesql::free_result($request);

		// Can we actually see who the voters were? (Assuming there were some voters)
		// voters_visible -> 0 = admin only, 1 = admin + creator only, 2 = members, 3 = anyone
		if ($realtotal > 0 && (we::$is_admin || ($pollinfo['voters_visible'] == 1 && we::$user['started']) || ($pollinfo['voters_visible'] == 2 && we::$is_member) || ($pollinfo['voters_visible'] == 3)))
		{
			$pollinfo['showing_voters'] = true;
			$request = wesql::query('
				SELECT lp.id_member, lp.id_choice, mem.real_name
				FROM {db_prefix}log_polls AS lp
					INNER JOIN {db_prefix}members AS mem ON (lp.id_member = mem.id_member)
				WHERE lp.id_poll = {int:poll}
				ORDER BY lp.id_choice, mem.real_name',
				array(
					'poll' => $topicinfo['id_poll'],
				)
			);
			while ($row = wesql::fetch_assoc($request))
				$pollOptions[$row['id_choice']]['voters'][$row['id_member']] = $row['real_name'];

			wesql::free_result($request);
		}

		// If this is a guest we need to do our best to work out if they have voted, and what they voted for.
		if (we::$is_guest && $pollinfo['guest_vote'] && allowedTo('poll_vote'))
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
			'question' => parse_bbc($pollinfo['question'], 'poll-question'),
			'total_votes' => $pollinfo['total'],
			'voters_visible' => $pollinfo['voters_visible'],
			'showing_voters' => !empty($pollinfo['showing_voters']),
			'change_vote' => !empty($pollinfo['change_vote']),
			'is_locked' => !empty($pollinfo['voting_locked']),
			'options' => array(),
			'lock' => allowedTo('poll_lock_any') || (we::$user['started'] && allowedTo('poll_lock_own')),
			'edit' => allowedTo('poll_edit_any') || (we::$user['started'] && allowedTo('poll_edit_own')),
			'allowed_warning' => $pollinfo['max_votes'] > 1 ? sprintf($txt['poll_options6'], min(count($pollOptions), $pollinfo['max_votes'])) : '',
			'is_expired' => !empty($pollinfo['expire_time']) && $pollinfo['expire_time'] < time(),
			'expire_time' => !empty($pollinfo['expire_time']) ? timeformat($pollinfo['expire_time']) : 0,
			'has_voted' => !empty($pollinfo['has_voted']),
			'starter' => array(
				'id' => $pollinfo['id_member'],
				'name' => $row['poster_name'],
				'href' => $pollinfo['id_member'] == 0 ? '' : '<URL>?action=profile;u=' . $pollinfo['id_member'],
				'link' => $pollinfo['id_member'] == 0 ? $row['poster_name'] : '<a href="<URL>?action=profile;u=' . $pollinfo['id_member'] . '">' . $row['poster_name'] . '</a>'
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
		$context['allow_vote'] = !$context['poll']['is_expired'] && (we::$is_member || ($pollinfo['guest_vote'] && allowedTo('poll_vote'))) && empty($pollinfo['voting_locked']) && allowedTo('poll_vote') && !$context['poll']['has_voted'];

		// You're allowed to view the results if:
		// 1. you're just a super-nice-guy, or
		// 2. anyone can see them (hide_results == 0), or
		// 3. you can see them after you voted (hide_results == 1), or
		// 4. you've waited long enough for the poll to expire. (whether hide_results is 1 or 2.)
		$context['allow_poll_view'] = allowedTo('moderate_board') || $pollinfo['hide_results'] == 0 || ($pollinfo['hide_results'] == 1 && $context['poll']['has_voted']) || $context['poll']['is_expired'];
		$context['poll']['show_results'] = $context['allow_poll_view'] && isset($_REQUEST['viewresults']);
		$context['show_view_results_button'] = $context['allow_poll_view'] && !isset($_REQUEST['viewresults']) && $context['allow_vote'];

		// You're allowed to change your vote if:
		// 1. the poll did not expire, and
		// 2. you're not a guest... and
		// 3. the poll is not locked, and
		// 4. you have the proper permissions, and
		// 5. you have already voted, and
		// 6. the poll creator has said you can!
		$context['allow_change_vote'] = !$context['poll']['is_expired'] && we::$is_member && empty($pollinfo['voting_locked']) && allowedTo('poll_vote') && $context['poll']['has_voted'] && $context['poll']['change_vote'];

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
				'voters' => $option['voters'],
				'voted_this' => $option['voted_this'] != -1,
				'bar' => '<span class="nowrap"><img src="' . ASSETS . '/poll_' . ($context['right_to_left'] ? 'right' : 'left') . '.gif"><img src="' . ASSETS . '/poll_middle.gif" width="' . $barWide . '" height="12"><img src="' . ASSETS . '/poll_' . ($context['right_to_left'] ? 'left' : 'right') . '.gif"></span>',
				'bar_ndt' => $bar > 0 ? '<div class="bar' . ($option['voted_this'] != -1 ? ' voted' : '') . '" style="width: ' . ($bar * .75) . '%"></div>' : '',
				'bar_width' => $barWide,
				'option' => parse_bbc($option['label'], 'poll-option'),
				'vote_button' => '<input type="' . ($pollinfo['max_votes'] > 1 ? 'checkbox' : 'radio') . '" name="options[]" value="' . $i . '">'
			);
		}

		// Now to add it to the list
		$sublayer = $context['poll']['show_results'] || !$context['allow_vote'] ? 'topic_poll_results' : 'topic_poll_vote';
		wetem::before('postlist', array('topic_poll' => array($sublayer)));
	}

	// Calculate the fastest way to get the messages!
	$ascending = empty($options['view_newest_first']);
	$start = $_REQUEST['start'] + ($ascending && $board_info['type'] != 'forum' ? 1 : 0);
	$limit = $context['messages_per_page'];

	/* The following code is buggy. I'm leaving it here, but commented out for now. Can't even see notable performance improvements...

	if ($start >= $context['total_visible_posts'] / 2 && $context['messages_per_page'] != -1)
	{
		$ascending = !$ascending;
		$limit = min($context['total_visible_posts'] - $_REQUEST['start'], $limit);
		$start = max($context['total_visible_posts'] - $_REQUEST['start'] - $limit, $ascending && $board_info['type'] != 'forum' ? 1 : 0);
	}
	*/

	// Find out if there is a double post...
	$context['last_user_id'] = 0;
	$context['last_msg_id'] = 0;

	if ($start > 0 && $context['messages_per_page'] != -1)
	{
		$request = wesql::query('
			SELECT id_member, id_msg, body, poster_email
			FROM {db_prefix}messages
			WHERE id_topic = {int:id_topic}
			ORDER BY id_msg' . ($ascending ? '' : ' DESC') . '
			LIMIT {int:postbefore}, 1',
			array(
				'id_topic' => $topic,
				'postbefore' => $start - 1,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (!empty($row['id_member']))
				$context['last_user_id'] = $row['id_member'];
			// If you have moderation rights, you can merge guest posts.
			elseif (allowedTo('modify_any'))
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
		SELECT id_msg, id_member, modified_member, approved, poster_time
		FROM {db_prefix}messages
		WHERE id_topic = {int:current_topic}' . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
		GROUP BY id_msg
		HAVING (approved = {int:is_approved}' . (we::$is_guest ? '' : ' OR id_member = {int:current_member}') . ')') . '
		ORDER BY id_msg' . ($ascending ? '' : ' DESC') . ($context['messages_per_page'] == -1 ? '' : '
		LIMIT ' . $start . ', ' . $limit),
		array(
			'current_member' => MID,
			'current_topic' => $topic,
			'is_approved' => 1,
			'blank_id_member' => 0,
		)
	);

	$all_posters = array();
	if ($board_info['type'] != 'forum')
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
		if (!empty($row['modified_member']))
			$all_posters[$row['id_msg'] . 'mod'] = $row['modified_member'];
		$messages[] = $row['id_msg'];
		$times[$row['id_msg']] = $row['poster_time'];
	}
	wesql::free_result($request);

	call_hook('display_message_list', array(&$messages, &$times, &$all_posters));
	$posters = array_unique($all_posters);

	// What's the oldest comment in the page..?
	$context['mark_unread_time'] = min($board_info['type'] == 'forum' || count($messages) < 2 ? $messages : array_slice($messages, 1));

	// When was the last time this topic was replied to? Should we warn them about it?
	if (!empty($settings['oldTopicDays']))
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

		$context['oldTopicError'] = $lastPostTime + $settings['oldTopicDays'] * 86400 < time();
	}

	// Guests can't mark topics read or for notifications, just can't sorry.
	if (we::$is_member)
	{
		// In case the page is being pre-fetched for infinite scrolling, mark it as
		// partially unread only, in case the user actually didn't read the posts.
		$mark_at_msg = INFINITE ? $context['mark_unread_time'] - 1 : max($messages);
		if ($mark_at_msg >= $topicinfo['id_last_msg'])
			$mark_at_msg = $settings['maxMsgID'];
		if ($mark_at_msg >= $topicinfo['new_from'])
			wesql::insert($topicinfo['new_from'] == 0 ? 'ignore' : 'replace',
				'{db_prefix}log_topics',
				array('id_member' => 'int', 'id_topic' => 'int', 'id_msg' => 'int'),
				array(MID, $topic, $mark_at_msg)
			);

		// Check for notifications on this topic OR board.
		$request = wesql::query('
			SELECT sent, id_topic
			FROM {db_prefix}log_notify
			WHERE (id_topic = {int:current_topic} OR id_board = {int:current_board})
				AND id_member = {int:current_member}
			LIMIT 2',
			array(
				'current_board' => $board,
				'current_member' => MID,
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
						'current_member' => MID,
						'current_topic' => $topic,
						'is_not_sent' => 0,
					)
				);
				$do_once = false;
			}
		}

		// Have we recently cached the number of new topics in this board, and it's still a lot?
		if (isset($_REQUEST['seen'], $_SESSION['seen_cache'][$board]) && $_SESSION['seen_cache'][$board] > 5)
			$_SESSION['seen_cache'][$board]--;
		// Mark board as seen if this is the only new topic.
		elseif (isset($_REQUEST['seen']))
		{
			// Use the mark read tables... and the last visit to figure out if this should be read or not.
			// @todo: should this use {query_see_topic}? Wouldn't recommend, as a topic might be marked private, but later published.
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
					'current_member' => MID,
					'id_msg_last_visit' => (int) $_SESSION['id_msg_last_visit'],
				)
			);
			list ($numNewTopics) = wesql::fetch_row($request);
			wesql::free_result($request);

			// If there're no real new topics in this board, mark the board as seen.
			if (empty($numNewTopics))
				$_REQUEST['boardseen'] = true;
			else
				$_SESSION['seen_cache'][$board] = $numNewTopics;
		}
		// Probably one less topic - maybe not, but even if we decrease this too fast it will only make us look more often.
		elseif (isset($_SESSION['seen_cache'][$board]))
			$_SESSION['seen_cache'][$board]--;

		// Mark board as seen if we came using the last post link from the board list or other places.
		if (isset($_REQUEST['boardseen']))
		{
			wesql::insert('replace',
				'{db_prefix}log_boards',
				array('id_msg' => 'int', 'id_member' => 'int', 'id_board' => 'int'),
				array($settings['maxMsgID'], MID, $board)
			);
		}
	}

	$attachments = array();

	// If there _are_ messages here... (Probably an error otherwise!)
	if (!empty($messages))
	{
		// Fetch attachments.
		if (!empty($settings['attachmentEnable']) && allowedTo('view_attachments'))
		{
			$request = wesql::query('
				SELECT
					a.id_attach, a.id_folder, a.id_msg, a.filename, a.file_hash, IFNULL(a.size, 0) AS filesize, a.downloads, a.transparency,
					a.width, a.height' . (empty($settings['attachmentShowImages']) || empty($settings['attachmentThumbnails']) ? '' : ',
					IFNULL(thumb.id_attach, 0) AS id_thumb, thumb.width AS thumb_width, thumb.height AS thumb_height') . '
				FROM {db_prefix}attachments AS a' . (empty($settings['attachmentShowImages']) || empty($settings['attachmentThumbnails']) ? '' : '
					LEFT JOIN {db_prefix}attachments AS thumb ON (thumb.id_attach = a.id_thumb)') . '
				WHERE a.id_msg IN ({array_int:message_list})
					AND a.attachment_type = {int:attachment_type}',
				array(
					'message_list' => $messages,
					'attachment_type' => 0,
				)
			);
			$temp = array();
			while ($row = wesql::fetch_assoc($request))
			{
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
			loadMemberData($posters, false, 'userbox');

		// Figure out the ordering.
		if ($board_info['type'] != 'forum')
			$order = empty($options['view_newest_first']) ? 'ORDER BY id_msg' : 'ORDER BY id_msg != {int:first_msg}, id_msg DESC';
		else
			$order = 'ORDER BY id_msg' . (empty($options['view_newest_first']) ? '' : ' DESC');

		$messages_request = wesql::query('
			SELECT
				id_msg, icon, subject, poster_time, li.member_ip AS poster_ip, id_member,
				modified_time, modified_name, modified_member, body, smileys_enabled, poster_name, poster_email,
				approved, id_msg < {int:new_from} AS is_read, m.data, id_msg_modified
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

		// Since the anchor information is needed on the top of the page we load these variables beforehand.
		$context['first_message'] = empty($options['view_newest_first']) || $board_info['type'] != 'forum' ? min($messages) : max($messages);
		if (empty($options['view_newest_first']))
			$context['first_new_message'] = isset($context['start_from']) && empty($_REQUEST['start']) && empty($context['start_from']);
		else
			$context['first_new_message'] = isset($context['start_from']) && $_REQUEST['start'] == $context['total_visible_posts'] - 1 - $context['start_from'];
	}
	else
	{
		$messages_request = false;
		$context['first_message'] = 0;
		$context['first_new_message'] = false;
	}

	// Get the likes.
	if (!empty($settings['likes_enabled']))
		prepareLikeContext($messages);

	// Set the callback. (Do you REALIZE how much memory all the messages would take?!?)
	$context['get_message'] = 'prepareDisplayContext';

	// Now set all the wonderful, wonderful permissions... like moderation ones...
	// - allowedTo('moderate_forum') is the 'Moderate forum members' permission.
	// - allowedTo('moderate_board') is also set for board owners even if they're not
	//   in an allowed group, unless the admin explicitly set their permission to 'deny'.
	$common_permissions = array(
		'can_approve' => 'approve_posts',
		'can_ban' => 'manage_bans',
		'can_pin' => 'pin_topic',
		'can_merge' => 'merge_any',
		'can_split' => 'split_any',
		'can_mark_notify' => 'mark_any_notify',
		'can_send_topic' => 'send_topic',
		'can_send_pm' => 'pm_send',
		'can_report_moderator' => 'report_any',
		'can_moderate_members' => 'moderate_forum',
		'can_moderate_board' => 'moderate_board',
		'can_issue_warning' => 'issue_warning',
		'can_restore_topic' => 'move_any',
		'can_restore_msg' => 'move_any',
	);
	foreach ($common_permissions as $key => $perm)
		$context[$key] = allowedTo($perm);

	// Permissions with _any/_own versions. $context[YYY] => ZZZ_any/_own.
	$anyown_permissions = array(
		'can_move' => 'move',
		'can_lock' => 'lock',
		'can_delete' => 'remove',
		'can_add_poll' => 'poll_add',
		'can_remove_poll' => 'poll_remove',
		'can_reply' => 'post_reply',
	);
	foreach ($anyown_permissions as $key => $perm)
		$context[$key] = allowedTo($perm . '_any') || (we::$user['started'] && allowedTo($perm . '_own'));

	// Cleanup all the permissions with extra stuff...
	$context['post_moderated'] = we::$user['post_moderated'];
	$context['can_mark_notify'] &= we::$is_member;
	$context['can_add_poll'] &= $topicinfo['id_poll'] <= 0;
	$context['can_remove_poll'] &= $topicinfo['id_poll'] > 0;
	$context['can_reply'] &= empty($topicinfo['locked']) || $context['can_moderate_board'];

	$context['can_quote'] = $context['can_reply'] && (empty($settings['disabledBBC']) || !in_array('quote', explode(',', $settings['disabledBBC'])));
	$context['can_mark_unread'] = we::$is_member;
	// Prevent robots from accessing the Post template
	$context['can_reply'] &= empty($context['possibly_robot']);
	// Check that the first post's icon is not a moved icon - i.e. the thread has been moved!
	$context['can_merge'] &= $topicinfo['icon'] != 'moved';

	$context['can_send_topic'] = (!$settings['postmod_active'] || $topicinfo['approved']) && allowedTo('send_topic');

	// Start this off for quick moderation - it will be or'd for each post.
	$context['can_remove_post'] = allowedTo('delete_any') || (allowedTo('delete_replies') && we::$user['started']);

	// Can restore topic? That's if the topic is in the recycle board and has a previous restore state.
	$context['can_restore_topic'] &= !empty($settings['recycle_enable']) && $settings['recycle_board'] == $board && !empty($topicinfo['id_previous_board']);
	$context['can_restore_msg'] &= !empty($settings['recycle_enable']) && $settings['recycle_board'] == $board && !empty($topicinfo['id_previous_topic']);

	// Phew. After all that...
	if ($context['can_reply'] && we::$user['activated'] == 6)
	{
		// They haven't re-agreed, but it's a soft reagreement force (if it weren't, they wouldn't be here anyway!)
		// So they can view but no posting.
		$context['can_reply'] = $context['can_quote'] = false;
		$txt['reagree_reply'] = sprintf($txt['reagree_reply'], '<URL>?action=register;reagree');
		$last_msg = max($messages);
		$_SESSION['reagree_url'] = '<URL>?topic=' . $context['current_topic'] . '.msg' . $last_msg . '#msg' . $last_msg;
		wetem::before('quick_reply', 'reagree_warning');
	}

	// Load up the "double post" sequencing magic.
	if ($context['can_reply'] && !empty($options['display_quick_reply']))
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
				'drafts' => !allowedTo('save_post_draft') || empty($settings['masterSavePostDrafts']) ? 'none' : (!allowedTo('auto_save_post_draft') || empty($settings['masterAutoSavePostDrafts']) || !empty($options['disable_auto_save']) ? 'basic_post' : 'auto_post'),
				// Now, since we're custom styling these, we need our own divs. For shame!
				'custom_bbc_div' => 'bbcBox_message',
				'custom_smiley_div' => 'smileyBox_message',
			)
		);
	}

	// "Mini-menu's small in size, but it's very wise."
	$short_profiles = !empty($settings['pretty_filters']['profiles']);
	$context['mini_menu']['user'] = array();
	$context['mini_menu_items_show']['user'] = array();
	$context['mini_menu_items']['user'] = array(
		'pr' => array(
			'caption' => 'usermenu_profile',
			'action' => '',
			'class' => 'profile_button',
		),
		'pm' => array(
			'caption' => 'usermenu_sendpm',
			'action' => '<URL>?action=pm;sa=send;u=%1%',
			'class' => 'pm_button',
		),
		'we' => array(
			'caption' => 'usermenu_website',
			'action' => '%2%',
			'class' => 'www_button',
		),
		'po' => array(
			'caption' => 'usermenu_showposts',
			'action' => $short_profiles ? '?area=showposts' : '<URL>?action=profile;u=%1%;area=showposts',
			'class' => 'post_button',
		),
		'ab' => array(
			'caption' => 'usermenu_addbuddy',
			'action' => '<URL>?action=buddy;u=%1%;' . $context['session_query'] . '',
			'class' => 'contact_button',
		),
		'rb' => array(
			'caption' => 'usermenu_removebuddy',
			'action' => '<URL>?action=buddy;u=%1%;' . $context['session_query'] . '',
			'class' => 'contact_button',
		),
		'ip' => array(
			'caption' => 'usermenu_seeip',
			'action' => '<URL>?action=help;in=see_member_ip',
			'class' => 'ip_button',
			'click' => 'return reqWin(this)',
		),
		'tk' => array(
			'caption' => 'usermenu_trackip',
			'action' => '<URL>?action=profile;u=%1%;area=tracking;sa=ip;searchip=%2%',
			'class' => 'ip_button',
		),
	);

	$context['mini_menu']['action'] = array();
	$context['mini_menu_items_show']['action'] = array();
	$context['mini_menu_items']['action'] = array(
		'lk' => array(
			'caption' => 'acme_like',
			'action' => '<URL>?action=like;topic=' . $context['current_topic'] . ';msg=%1%;' . $context['session_query'] . '',
			'class' => 'like_button',
		),
		'uk' => array(
			'caption' => 'acme_unlike',
			'action' => '<URL>?action=like;topic=' . $context['current_topic'] . ';msg=%1%;' . $context['session_query'] . '',
			'class' => 'unlike_button',
		),
		'qu' => array(
			'caption' => 'acme_quote',
			'action' => '<URL>?action=post;quote=%1%;topic=' . $context['current_topic'] . ';last=' . $context['topic_last_message'] . '',
			'class' => 'quote_button',
		),
		'mo' => array(
			'caption' => 'acme_modify',
			'action' => '<URL>?action=post;msg=%1%;topic=' . $context['current_topic'] . '',
			'class' => 'edit_button',
		),
		'ap' => array(
			'caption' => 'acme_approve',
			'action' => '<URL>?action=moderate;area=postmod;sa=approve;topic=' . $context['current_topic'] . ';msg=%1%;' . $context['session_query'] . '',
			'class' => 'approve_button',
		),
		're' => array(
			'caption' => 'acme_remove',
			'action' => '<URL>?action=deletemsg;topic=' . $context['current_topic'] . ';msg=%1%;' . $context['session_query'] . '',
			'class' => 'remove_button',
			'click' => substr(JavaScriptEscape('return ask(' . JavaScriptEscape($txt['remove_message_confirm']) . ', e)'), 1, -1),
		),
		'sp' => array(
			'caption' => 'acme_split',
			'action' => '<URL>?action=splittopics;topic=' . $context['current_topic'] . ';at=%1%',
			'class' => 'split_button',
		),
		'me' => array(
			'caption' => 'acme_merge',
			'action' => '<URL>?action=mergeposts;pid=%1%;msgid=%2%;topic=' . $context['current_topic'] . '',
			'class' => 'mergepost_button',
		),
		'rs' => array(
			'caption' => 'acme_restore',
			'action' => '<URL>?action=restoretopic;msgs=%1%;' . $context['session_query'] . '',
			'class' => 'restore_button',
		),
		'rp' => array(
			'caption' => 'acme_report',
			'action' => '<URL>?action=report;topic=' . $context['current_topic'] . ';msg=%1%',
			'class' => 'report_button',
		),
		'wa' => array(
			'caption' => 'acme_warn',
			'action' => '<URL>?action=profile;u=%2%;area=infractions;warn;for=post:%1%',
			'class' => 'warn_button',
		),
		'ai' => array(
			'caption' => 'usermenu_ignore',
			'action' => '<URL>?action=profile;area=lists;sa=ignore;add=%2%;msg=%1%;' . $context['session_query'],
			'class' => 'unlike_button',
		),
		'ri' => array(
			'caption' => 'usermenu_unignore',
			'action' => '<URL>?action=profile;area=lists;sa=ignore;remove=%2%;msg=%1%;' . $context['session_query'],
			'class' => 'like_button',
		),
	);

	// Lastly, set up the navigation items that we're going to be using.
	$context['nav_buttons'] = array(
		'normal' => array(
			'reply' => array('test' => 'can_reply', 'text' => 'reply', 'url' => '<URL>?action=post;topic=' . $context['current_topic'] . '.' . $context['start'] . ';last=' . $context['topic_last_message'], 'class' => 'active'),
			($context['is_marked_notify'] ? 'unnotify' : 'notify') => array('test' => 'can_mark_notify', 'text' => $context['is_marked_notify'] ? 'unnotify' : 'notify', 'custom' => 'onclick="return ask(' . JavaScriptEscape($txt['notification_' . ($context['is_marked_notify'] ? 'disable_topic' : 'enable_topic')]) . ', e);"', 'url' => '<URL>?action=notify;sa=' . ($context['is_marked_notify'] ? 'off' : 'on') . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_query']),
			'mark_unread' => array('test' => 'can_mark_unread', 'text' => 'mark_unread', 'url' => '<URL>?action=markasread;sa=topic;t=' . $context['mark_unread_time'] . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_query']),
			'send' => array('test' => 'can_send_topic', 'text' => 'send_topic', 'url' => '<URL>?action=emailuser;sa=sendtopic;topic=' . $context['current_topic'] . '.0'),
			'print' => array('text' => 'print', 'custom' => 'rel="nofollow"', 'url' => '<URL>?action=printpage;topic=' . $context['current_topic'] . '.0'),
		),
		'mod' => array(
			'move' => array('test' => 'can_move', 'text' => 'move_topic', 'url' => '<URL>?action=movetopic;topic=' . $context['current_topic'] . '.0'),
			'delete' => array('test' => 'can_delete', 'text' => 'remove_topic', 'custom' => 'onclick="return ask(' . JavaScriptEscape($txt['are_sure_remove_topic']) . ', e);"', 'url' => '<URL>?action=removetopic2;topic=' . $context['current_topic'] . '.0;' . $context['session_query']),
			'lock' => array('test' => 'can_lock', 'text' => empty($context['is_locked']) ? 'set_lock' : 'set_unlock', 'url' => '<URL>?action=lock;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_query']),
			'pin' => array('test' => 'can_pin', 'text' => empty($context['is_pinned']) ? 'set_pin' : 'set_unpin', 'url' => '<URL>?action=pin;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_query']),
			'merge' => array('test' => 'can_merge', 'text' => 'merge', 'url' => '<URL>?action=mergetopics;topic=' . $context['current_topic']),
			'add_poll' => array('test' => 'can_add_poll', 'text' => 'add_poll', 'url' => '<URL>?action=poll;sa=editpoll;add;topic=' . $context['current_topic'] . '.' . $context['start']),
		),
	);

	if ($context['is_poll'])
		$context['nav_buttons']['poll'] = array(
			'vote' => array('test' => 'allow_return_vote', 'text' => 'poll_return_vote', 'url' => '<URL>?topic=' . $context['current_topic'] . '.' . $context['start']),
			'results' => array('test' => 'show_view_results_button', 'text' => 'poll_results', 'url' => '<URL>?topic=' . $context['current_topic'] . '.' . $context['start'] . ';viewresults'),
			'change_vote' => array('test' => 'allow_change_vote', 'text' => 'poll_change_vote', 'url' => '<URL>?action=poll;sa=vote;topic=' . $context['current_topic'] . '.' . $context['start'] . ';poll=' . $context['poll']['id'] . ';' . $context['session_query']),
			'lock' => array('test' => 'allow_lock_poll', 'text' => (!$context['poll']['is_locked'] ? 'poll_lock' : 'poll_unlock'), 'url' => '<URL>?action=poll;sa=lockvoting;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_query']),
			'edit' => array('test' => 'allow_edit_poll', 'text' => 'poll_edit', 'url' => '<URL>?action=poll;sa=editpoll;topic=' . $context['current_topic'] . '.' . $context['start']),
			'remove_poll' => array('test' => 'can_remove_poll', 'text' => 'poll_remove', 'custom' => 'onclick="return ask(' . JavaScriptEscape($txt['poll_remove_warn']) . ', e);"', 'url' => '<URL>?action=poll;sa=removepoll;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_query']),
		);

	// Restore topic. Eh? No monkey business.
	if ($context['can_restore_topic'])
		$context['nav_buttons']['mod']['restore'] = array('text' => 'restore_topic', 'url' => '<URL>?action=restoretopic;topics=' . $context['current_topic'] . ';' . $context['session_query']);

	// All of this... FOR THAT?!
	if (INFINITE)
	{
		wetem::replace(array('postlist_infinite' => array('display_posts')));
		wetem::hide();
	}

	// Generic processing that doesn't apply to per-post handling.
	call_hook('display_main');
}

// Callback for the message display.
function prepareDisplayContext($reset = false)
{
	global $txt, $settings, $options, $board_info;
	global $memberContext, $context, $messages_request, $topic, $topicinfo;

	static $counter = null, $can_ip = null, $can_pm = null, $profile_own = null, $profile_any = null, $buddy = null, $ignore = null, $is_new = false;

	// If the query returned false, bail.
	if ($messages_request == false)
		return false;

	// If you can issue bans, you should see IPs. If you can moderate members or this board,
	// it can also be useful, to spot duplicate accounts.
	if ($can_ip === null)
		$can_ip = allowedTo('manage_bans') || $context['can_moderate_members'] || $context['can_moderate_board'];

	// Remember which message this is, e.g. reply #83.
	if ($counter === null || $reset)
		$counter = empty($options['view_newest_first']) ? $context['start'] : $context['total_visible_posts'] - $context['start'] + ($board_info['type'] == 'forum' ? -1 : 1);

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

	// We have a fun extra piece of information to unpack before letting hooks have their wicked way with this.
	$message['data'] = !empty($message['data']) ? unserialize($message['data']) : array();

	call_hook('display_prepare_post', array(&$counter, &$message));

	// Is this user the message author?
	$is_me = we::$is_member && $message['id_member'] == MID;

	// If you're a lazy bum, you probably didn't give a subject...
	$message['subject'] = $message['subject'] !== '' ? $message['subject'] : $txt['no_subject'];

	// Are you allowed to remove at least a single reply?
	$context['can_remove_post'] |= allowedTo('delete_own') && (empty($settings['edit_disable_time']) || $message['poster_time'] + $settings['edit_disable_time'] * 60 >= time()) && $message['id_member'] == MID;

	// If it couldn't load, or the user was a guest.... someday may be done with a guest table.
	if (!loadMemberContext($message['id_member'], true))
	{
		// Notice this information isn't used anywhere else....
		$memberContext[$message['id_member']]['name'] = $message['poster_name'];
		$memberContext[$message['id_member']]['id'] = 0;
		$memberContext[$message['id_member']]['group'] = $txt['guest_title'];
		// Wedge supports showing guest posts, grouping them by e-mail address. Can restrict to current board: add ;board=$context['current_board']
		$memberContext[$message['id_member']]['href'] = '<URL>?action=profile;guest=' . base64_encode($message['poster_name']);
		$memberContext[$message['id_member']]['link'] = '<a href="' . $memberContext[$message['id_member']]['href'] . '">' . $message['poster_name'] . '</a>';
		$memberContext[$message['id_member']]['email'] = $message['poster_email'];
		$memberContext[$message['id_member']]['show_email'] = showEmailAddress(true, 0);
		$memberContext[$message['id_member']]['is_guest'] = true;
	}
	else
	{
		$memberContext[$message['id_member']]['can_view_profile'] = allowedTo('profile_view_any') || ($message['id_member'] == MID && allowedTo('profile_view_own'));
		$memberContext[$message['id_member']]['is_topic_starter'] = $message['id_member'] == $context['topic_starter_id'];
		$memberContext[$message['id_member']]['can_see_warning'] = !empty($settings['warning_show']) && we::$is_member && $memberContext[$message['id_member']]['warning_status'] && ($settings['warning_show'] == 3 || allowedTo('issue_warning') || ($settings['warning_show'] == 2 && $message['id_member'] == MID));
	}

	$memberContext[$message['id_member']]['ip'] = format_ip($message['poster_ip']);

	// Do the censor thang.
	censorText($message['body']);
	censorText($message['subject']);
	if (SKIN_MOBILE)
		$message['subject'] = westr::cut($message['subject'], 20);

	$merge_safe = true;

	// Avoid having too large a post if we do any merger.
	if (empty($settings['merge_post_ignore_length']) && $settings['max_messageLength'] > 0)
	{
		// Calculating the length...
		if (!isset($context['correct_post_length']))
			$context['correct_post_length'] = westr::strlen(empty($settings['merge_post_no_sep']) ? (empty($settings['merge_post_no_time']) ?
				'<br>[size=1][mergedate]' . $message['modified_time'] . '[/mergedate][/size]' : '') . '[hr]<br>' : '<br>');

		$context['current_post_length'] = westr::strlen(un_htmlspecialchars($message['body']));
		if (!isset($context['last_post_length']))
			$context['last_post_length'] = 0;
		else
			$merge_safe = ($context['current_post_length'] + $context['last_post_length'] + $context['correct_post_length']) < $settings['max_messageLength'];
	}
	else
		$context['current_post_length'] = 0;

	// Run BBC interpreter on the message.
	$message['body'] = parse_bbc($message['body'], 'post', array('smileys' => $message['smileys_enabled'], 'cache' => $message['id_msg'], 'user' => $message['id_member']));

	// Compose the memory eat- I mean message array.
	$output = array(
		'attachment' => loadAttachmentContext($message['id_msg']),
		'id' => $message['id_msg'],
		'href' => '<URL>?topic=' . $topic . '.msg' . $message['id_msg'] . '#msg' . $message['id_msg'],
		'link' => '<a href="<URL>?topic=' . $topic . '.msg' . $message['id_msg'] . '#msg' . $message['id_msg'] . '" rel="nofollow">' . $message['subject'] . '</a>',
		'member' => &$memberContext[$message['id_member']],
		'can_like' => we::$is_member && !empty($settings['likes_enabled']) && (!empty($settings['likes_own_posts']) || !$is_me),
		'icon' => $message['icon'],
		'icon_url' => ASSETS . '/post/' . $message['icon'] . '.gif',
		'subject' => $message['subject'],
		'on_time' => on_timeformat($message['poster_time']),
		'timestamp' => $message['poster_time'], // Don't apply time offset here. This isn't used, but doesn't cost anything to include here, so...
		'counter' => $board_info['type'] == 'forum' ? $counter : ($counter == $context['start'] ? 0 : $counter),
		'modified' => array(
			'on_time' => on_timeformat($message['modified_time']),
			'timestamp' => forum_time(true, $message['modified_time']),
			'name' => $message['modified_name'],
			'member' => $message['modified_member'],
		),
		'body' => $message['body'],
		'new' => !$message['is_read'] && !$is_new,
		'edited' => $message['is_read'] && $message['id_msg_modified'] > $topicinfo['new_from'],
		'approved' => $message['approved'],
		'first_new' => isset($context['start_from']) && $context['start_from'] == $counter,
		'is_message_author' => $is_me,
		'is_ignored' => !empty($settings['enable_buddylist']) && !empty($options['posts_apply_ignore_list']) && in_array($message['id_member'], we::$user['ignoreusers']),
		'can_approve' => !$message['approved'] && $context['can_approve'],
		'can_unapprove' => $message['approved'] && $context['can_approve'],
		'can_modify' => (!$context['is_locked'] || $context['can_moderate_board']) && (allowedTo('modify_any') || (allowedTo('modify_replies') && we::$user['started']) || (allowedTo('modify_own') && $message['id_member'] == MID && (empty($settings['edit_disable_time']) || !$message['approved'] || $message['poster_time'] + $settings['edit_disable_time'] * 60 > time()))) && (empty($message['modified_member']) || $message['modified_member'] == MID || !empty($settings['allow_non_mod_edit']) || $context['can_moderate_board']),
		'can_remove' => allowedTo('delete_any') || (allowedTo('delete_replies') && we::$user['started']) || (allowedTo('delete_own') && $message['id_member'] == MID && (empty($settings['edit_disable_time']) || $message['poster_time'] + $settings['edit_disable_time'] * 60 > time())),
		'can_see_ip' => $can_ip || $is_me,
		'can_mergeposts' => $merge_safe && !empty($context['last_user_id']) && $context['last_user_id'] == (empty($message['id_member']) ? (empty($message['poster_email']) ? $message['poster_name'] : $message['poster_email']) : $message['id_member']) && (allowedTo('modify_any') || (allowedTo('modify_own') && $message['id_member'] == MID)),
		'last_post_id' => $context['last_msg_id'],
		'unapproved_msg' => $message['approved'] && !empty($message['data']['unapproved_msg']) ? $message['data']['unapproved_msg'] : '',
		'warn_msg' => !empty($message['data']['warn_msg']) ? $message['data']['warn_msg'] : '',
	);

	// Keep showing the New logo on every unread post in Newest First mode. Otherwise it gets confusing.
	if (empty($options['view_newest_first']) && empty($output['edited']))
		$is_new |= !$message['is_read'];

	$output['can_mergeposts'] &= !empty($output['last_post_id']);

	// Is this a board? If not, we're dealing with this as replies to a post, and we won't allow merging the first reply into the post.
	if ($board_info['type'] != 'forum' && $message['id_msg'] == $context['first_message'])
		$output['can_mergeposts'] = false;
	else
	{
		if (!empty($message['id_member']))
			$context['last_user_id'] = $message['id_member'];
		// If you're the admin, you can merge guest posts
		elseif (allowedTo('modify_any'))
			$context['last_user_id'] = $message['poster_email'];
		$context['last_msg_id'] = $message['id_msg'];
		$context['last_post_length'] = $context['current_post_length'];
	}

	// Now, to business. Is it not a guest, and we haven't done this before?
	if ($output['member']['id'] != 0 && !isset($context['mini_menu']['user'][$output['member']['id']]))
	{
		// 1. Preparation, since we'd rather not figure this stuff out time and again if we can help it.
		if ($can_pm === null)
		{
			$can_pm = allowedTo('pm_send');
			$profile_own = allowedTo('profile_view_own');
			$profile_any = allowedTo('profile_view_any');
			$buddy = allowedTo('profile_identity_own') && !empty($settings['enable_buddylist']);
			$ignore = allowedTo('profile_identity_own') && !empty($settings['enable_buddylist']);
		}

		// 2. Figure out that user's menu to the stack. It may be different if it's our menu.
		$menu = array();

		if ($profile_any || ($is_me && $profile_own))
			$menu[] = 'pr';

		if ($can_pm && !$is_me)
			$menu[] = 'pm';

		if (!empty($output['member']['website']['url']))
			$menu[] = 'we/' . $output['member']['website']['url'];

		if ($profile_any || ($is_me && $profile_own))
			$menu[] = 'po';

		if ($buddy && !$is_me)
			$menu[] = $memberContext[$message['id_member']]['is_buddy'] ? 'rb' : 'ab';

		if ($output['can_see_ip'] && !empty($output['member']['ip']))
			$menu[] = ($context['can_moderate_members'] || $context['can_moderate_board'] ? 'tk' : 'ip') . '/' . $output['member']['ip'];

		// If we can't do anything, it's not even worth recording the user's website...
		if (count($menu))
		{
			$context['mini_menu']['user'][$output['member']['id']] = $menu;
			$amenu = array();
			foreach ($menu as $mid => $name)
				$amenu[substr($name, 0, 2)] = true;
			$context['mini_menu_items_show']['user'] += $amenu;
		}
	}

	// Bit longer, but this should be helpful too... The per-post menu.
	if ($output['member']['id'] != 0)
	{
		$menu = array();

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
			$menu[] = 'me/' . $output['last_post_id'];

		// Can we restore topics?
		if ($context['can_restore_msg'])
			$menu[] = 'rs';

		if ($context['can_report_moderator'] && !$is_me)
			$menu[] = 'rp';

		if ($context['can_issue_warning'] && !$is_me && !$output['member']['is_guest'])
			$menu[] = 'wa/' . $output['member']['id'];

		if ($ignore && !$is_me)
			$menu[] = ($output['is_ignored'] ? 'ri' : 'ai') . '/' . $output['member']['id'];

		// If we can't do anything, it's not even worth recording the last message ID...
		if (!empty($menu))
		{
			$context['mini_menu']['action'][$output['id']] = $menu;
			$amenu = array();
			foreach ($menu as $mid => $name)
				$amenu[substr($name, 0, 2)] = true;
			$context['mini_menu_items_show']['action'] += $amenu;
		}
	}

	// Don't forget to set this to true in the following hook if you're going to add a non-menu button.
	$output['has_buttons'] = $context['can_quote'] || $output['can_modify'] || !empty($context['mini_menu']['action'][$output['id']]);

	call_hook('display_post_done', array(&$counter, &$output));

	// Fixing the meta description. We do it here because we pull things post by post.
	// Since that's the case, the header has already been done by the point we get to here.
	if (!isset($context['meta_description_repl']))
		$context['meta_description_repl'] = westr::cut(preg_replace('~\s+~', ' ', strip_tags(str_replace(array('"', '<br>'), array('\'', ' '), $output['body']))), 160);

	if (empty($options['view_newest_first']))
		$counter++;
	else
		$counter--;

	return $output;
}

function loadAttachmentContext($id_msg)
{
	global $attachments, $settings, $txt, $topic;

	// Set up the attachment info - based on code by Meriadoc.
	$attachmentData = array();
	if (isset($attachments[$id_msg]) && !empty($settings['attachmentEnable']))
	{
		foreach ($attachments[$id_msg] as $i => $attachment)
		{
			$attachmentData[$i] = array(
				'id' => $attachment['id_attach'],
				'name' => westr::htmlspecialchars($attachment['filename']),
				'downloads' => $attachment['downloads'],
				'size' => round($attachment['filesize'] / 1024, 2) . ' ' . $txt['kilobyte'],
				'byte_size' => $attachment['filesize'],
				'href' => '<URL>?action=dlattach;topic=' . $topic . '.0;attach=' . $attachment['id_attach'],
				'link' => '<a href="<URL>?action=dlattach;topic=' . $topic . '.0;attach=' . $attachment['id_attach'] . '">' . htmlspecialchars($attachment['filename']) . '</a>',
				'transparent' => $attachment['transparency'] == 'transparent',
				'is_image' => !empty($attachment['width']) && !empty($attachment['height']) && !empty($settings['attachmentShowImages']),
			);

			if (empty($attachment['transparency']))
			{
				$filename = getAttachmentFilename($attachment['filename'], $attachment['id_attach'], $attachment['id_folder']);
				$attachmentData[$i]['transparent'] = we_resetTransparency($attachment['id_attach'], $filename, $attachment['filename']);
			}
			else
				$filename = '';

			if (!$attachmentData[$i]['is_image'])
				continue;

			$attachmentData[$i]['real_width'] = $attachment['width'];
			$attachmentData[$i]['width'] = $attachment['width'];
			$attachmentData[$i]['real_height'] = $attachment['height'];
			$attachmentData[$i]['height'] = $attachment['height'];

			// Let's see, do we want thumbs?
			if (!empty($settings['attachmentThumbnails']) && !empty($settings['attachmentThumbWidth']) && !empty($settings['attachmentThumbHeight']) && ($attachment['width'] > $settings['attachmentThumbWidth'] || $attachment['height'] > $settings['attachmentThumbHeight']) && strlen($attachment['filename']) < 249)
			{
				// A proper thumb doesn't exist yet? Create one!
				if (empty($attachment['id_thumb']) || $attachment['thumb_width'] > $settings['attachmentThumbWidth'] || $attachment['thumb_height'] > $settings['attachmentThumbHeight'] || ($attachment['thumb_width'] < $settings['attachmentThumbWidth'] && $attachment['thumb_height'] < $settings['attachmentThumbHeight']))
				{
					if (empty($filename))
						$filename = getAttachmentFilename($attachment['filename'], $attachment['id_attach'], $attachment['id_folder']);

					loadSource('Subs-Graphics');
					if (createThumbnail($filename, $settings['attachmentThumbWidth'], $settings['attachmentThumbHeight']))
					{
						// So what folder are we putting this image in?
						if (!empty($settings['currentAttachmentUploadDir']))
						{
							if (!is_array($settings['attachmentUploadDir']))
								$settings['attachmentUploadDir'] = @unserialize($settings['attachmentUploadDir']);
							$path = $settings['attachmentUploadDir'][$settings['currentAttachmentUploadDir']];
							$id_folder_thumb = $settings['currentAttachmentUploadDir'];
						}
						else
						{
							$path = $settings['attachmentUploadDir'];
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
							array($id_folder_thumb, $id_msg, 3, $thumb_filename, $thumb_hash, (int) $thumb_size, (int) $attachment['thumb_width'], (int) $attachment['thumb_height'], $thumb_ext, $thumb_mime)
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
					'href' => '<URL>?action=dlattach;topic=' . $topic . '.0;attach=' . $attachment['id_thumb'] . ';image',
				);
			$attachmentData[$i]['thumbnail']['has_thumb'] = !empty($attachment['id_thumb']);

			// If thumbnails are disabled, check the maximum size of the image.
			if (!$attachmentData[$i]['thumbnail']['has_thumb'] && ((!empty($settings['max_image_width']) && $attachment['width'] > $settings['max_image_width']) || (!empty($settings['max_image_height']) && $attachment['height'] > $settings['max_image_height'])))
			{
				if (!empty($settings['max_image_width']) && (empty($settings['max_image_height']) || $attachment['height'] * $settings['max_image_width'] / $attachment['width'] <= $settings['max_image_height']))
				{
					$attachmentData[$i]['width'] = $settings['max_image_width'];
					$attachmentData[$i]['height'] = floor($attachment['height'] * $settings['max_image_width'] / $attachment['width']);
				}
				elseif (!empty($settings['max_image_width']))
				{
					$attachmentData[$i]['width'] = floor($attachment['width'] * $settings['max_image_height'] / $attachment['height']);
					$attachmentData[$i]['height'] = $settings['max_image_height'];
				}
			}
			elseif ($attachmentData[$i]['thumbnail']['has_thumb'])
			{
				loadSource('media/Aeva-Subs-Vital');
				aeva_initZoom(true);
			}

			if (!$attachmentData[$i]['thumbnail']['has_thumb'])
				$attachmentData[$i]['downloads']++;
		}
	}

	return $attachmentData;
}

function prepareLikeContext($messages, $type = 'post')
{
	global $context;

	$context['liked_posts'] = array();
	if (empty($messages))
		return;

	// First, get everyone who has marked this as Like.
	$request = wesql::query('
		SELECT id_content, id_member
		FROM {db_prefix}likes
		WHERE id_content IN ({array_int:messages})
			AND content_type = {string:type}
		ORDER BY like_time',
		array(
			'messages' => $messages,
			'type' => $type,
		)
	);

	while ($row = wesql::fetch_assoc($request))
	{
		if (in_array($row['id_member'], we::$user['ignoreusers']))
			continue;

		// If it's us, log it as being us.
		if ($row['id_member'] == MID)
			$context['liked_posts'][$row['id_content']]['you'] = true;
		elseif (empty($context['liked_posts'][$row['id_content']]['others']))
			$context['liked_posts'][$row['id_content']]['others'] = 1;
		else
			$context['liked_posts'][$row['id_content']]['others']++;
	}
	wesql::free_result($request);
}
