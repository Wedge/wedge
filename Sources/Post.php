<?php
/**********************************************************************************
* Post.php                                                                        *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC4                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

/**
 * This file handles the posting interface and redisplaying it in the event of an error.
 *
 * @package wedge
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Handles showing the post screen, loading a post to be modified (or quoted), as well as previews and display of errors. Additionally, new polls or calendar events too.
 *
 * Requested primarily from ?action=post, however will be called from ?action=post2 internally in the event of errors or non-inline preview (e.g. from quick reply)
 *
 * - Loads the Post language file, and the editor components.
 * - If this is a reply, there shouldn't be a poll attached, so remove it.
 * - Tell robots not to index.
 * - Validate that we're posting in a board (and not just to the calendar)
 * - We may be handling inline previews via XML, so prepare for that.
 * - If we don't have a topic id but just a message id, find the topic id if available (if not, assume it's a new message)
 * - Check things we can check if we have an existing topic (and grab them): whether it's locked, stickied, notifications on it, whether there's a poll, some message ids and the subject.
 * - If there's already a poll, disallow adding another.
 * - If this is a guest trying to post, check whether they can, and if not throw them at a log-in screen.
 * - If this is a reply, check whether the permissions allow such (own/any/replies, whether it will require approval), and details like whether it is/can be locked/sticky.
 * - Perform other checks, such as whether we can receive notifications, whether it can be announced.
 * - If the topic is locked and you do not have moderate_board, you cannot post. (Need to unlock first otherwise.)
 * - Are polls enabled and user trying to post a poll? Check permissions in that case (e.g. coming from add poll button) and if all allowed, set up the default empty choices, plus grab anything that's in $_POST (e.g. coming from Post2()) or use blank defaults.
 * - Are calendar events enabled, and this is an event? See if we can get anything from $_REQUEST, otherwise use blank defaults, and check permissions. If the user is trying to edit an event but can't, redirect them to the calendar handler {@link CalendarPost()}. Otherwise grab the event's information... or otherwise it's a new event, in which case set up everything as defaults (today's date, all things empty, if not otherwise from $_POST) and check it's all feasible. Lastly, grab a list of boards the linked topic could be in. And get the last day of the month so we can sanitize that too.
 * - If we have a topic and no message, we're replying. Time to check two things: one, that there's been no replies since we started writing (and if there was, check whether the user cares from $options['no_new_reply_warning']) and log that as a warning if they do care; two, whether this topic is older than the 'default old' topics without a reply, typically 120 days.
 * - Work out what the response prefix would be (the Re: in front of replies), in the default forum language, and cache it.
 * - Work out whether we have post content to work with (or an error) - if not, set up the subject, message and icon as defaults. Otherwise... if there's no error thus far but we have content, check for no subject, no message, over-long message, if it's a guest attempt to make some sense of guest name and email (like in Post2()), if it's a poll check for a question - but note that the user was actually attempting to preview if they came here without any actual errors previously known about.
 * - Check on approval status from any previous form.
 * - Sanitize subject (custom htmlspecialchars, replace all CR, LR and TAB with empty string), sanitize body with custom htmlspecialchars.
 * - Shorten subject if it's too long.
 * - Trim subject of whitespace, if empty, log this as an error to be displayed.
 * - If there are any errors, process them into a form that we can display (i.e. go from short error codes to full codes, and if it's the long_message error, make sure we substitute in the actual max length)
 * - If there is a poll, grab its question if available (or use blank), grab the choices if available (and sanitize, or use empty array).
 * - If a guest, set up all the variables we might need with guest name and email, and sanitize everything with htmlspecialchars.
 * - If the user selected preview (non inline) or they're previewing from XML, get everything together to provide that - so grab the message, preparse it, parse for bbcode and smileys as appropriate, censor it.
 * - If using XML, ensure that any inadvertant CDATA closures are protected adequately.
 * - Set up a few more variables for later (based on $_REQUEST, or using defaults if not available) - whether to use smileys, whether to receive notifications, the post icon, the post form destination, the label of the submit button (whether save or post depending on edit vs new)
 * - Are we previewing an edit (or there's an edit)? If so, get the original message, check the edit time (and disallow if we're using a limit on edit time and it's over the limit), get all the attachments from the message, check permissions for modifying posts, and deal with any changes of names.
 * - Lastly (for if we came here with post content, or an error), release the nonce for submitting the form once, since the form wasn't actually submitted.
 * - Next up, we're actually editing a message (as opposed to previewing an edit in progress), so get the message and any attachments. Again, check permissions, this time also get any modified-by details.
 * - Prepare the message for editing, e.g. un-preparse the code, apply censoring, set up typical values for the form (e.g. whether using smileys) and set the form destination again.
 * - Lastly, we're posting a new post - provide all the defaults. Additionally, if they're quoting a post (and not inline requesting it), get that, including permission checking, censoring, stripping the HTML bbcode if not applicable, adding the reply prefix, removing any nested quotes, and adding the quote header.
 * - If there's a reply without a quote, get the original subject, censor it and prepend the response prefix if it isn't already there for some reason. (Of course, if this is a new topic, just set default subject and body)
 * - If allowed to post attachments: kick off by setting up storing the details in the session (in the event of an error from attachments, we get to keep them in session to avoid having the user reupload them). Figure out the attachment directory, and load any attachments we already have (if this is an edit). This allows us to check the file size (based on the data being in session and having access to the temporary uploaded files). Proceed to remove anything the user selected (e.g. we've come back here because of too many files or something and doing a preview), then out of what's left, check the usual things (file too big, too many files etc.)
 * - Are there more replies than we thought there were? If so, flag that to the user.
 * - Is this topic over the threshold of being old? If so, flag that to the user.
 * - Set the page title and link tree depending on what we're doing.
 * - If using wireless, rewrite the last link tree item to point to the full post page instead.
 * - Instance the editor component.
 * - Get all the possible icons in this board, and prepare them ready for the template.
 * - All the relevant posts to display after the editor box (i.e. the last replies, or the last posts before your currently-editing post)
 * - Set up whether they can see the additional options including attachments (and the details of any such restrictions)
 * - Set up CAPTCHA if appropriate (and also add error if they came from quick reply without said CAPTCHA)
 * - Last bits: set up whether WYSIWYG is available, set the nonce so we can't submit twice normally, and load the normal or wireless template as appropriate.
 *
 * @todo Why is the response prefix cached? Is it really that much effort to determine... what... 4 friggin' bytes? Would be better in $modSettings in that case.
 * @todo Is it possible to force something through approval if you edit the form manually?
 * @todo Censoring appears to be done out of sequence if previewing compared to parsing. Issue? Non-issue?
 */
function Post()
{
	global $txt, $scripturl, $topic, $modSettings, $board;
	global $user_info, $sc, $board_info, $context, $settings;
	global $options, $smcFunc, $language;

	loadLanguage('Post');

	// Needed for the editor and message icons.
	loadSource(array('Subs-Editor', 'Class-Editor'));

	// You can't reply with a poll... hacker.
	if (isset($_REQUEST['poll']) && !empty($topic) && !isset($_REQUEST['msg']))
		unset($_REQUEST['poll']);

	// Posting an event?
	$context['make_event'] = isset($_REQUEST['calendar']);
	$context['robot_no_index'] = true;

	// You must be posting to *some* board.
	if (empty($board) && !$context['make_event'])
		fatal_lang_error('no_board', false);

	loadSource('Subs-Post');

	if (isset($_REQUEST['xml']))
	{
		$context['sub_template'] = 'post';

		// Just in case of an earlier error...
		$context['preview_message'] = '';
		$context['preview_subject'] = '';
	}

	// No message is complete without a topic.
	if (empty($topic) && !empty($_REQUEST['msg']))
	{
		$request = wedb::query('
			SELECT id_topic
			FROM {db_prefix}messages
			WHERE id_msg = {int:msg}',
			array(
				'msg' => (int) $_REQUEST['msg'],
		));
		if (wedb::num_rows($request) != 1)
			unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);
		else
			list ($topic) = wedb::fetch_row($request);
		wedb::free_result($request);
	}

	// Check if it's locked.  It isn't locked if no topic is specified.
	if (!empty($topic))
	{
		$request = wedb::query('
			SELECT
				t.locked, IFNULL(ln.id_topic, 0) AS notify, t.is_sticky, t.id_poll, t.id_last_msg, mf.id_member,
				t.id_first_msg, mf.subject,
				CASE WHEN ml.poster_time > ml.modified_time THEN ml.poster_time ELSE ml.modified_time END AS last_post_time
			FROM {db_prefix}topics AS t
				LEFT JOIN {db_prefix}log_notify AS ln ON (ln.id_topic = t.id_topic AND ln.id_member = {int:current_member})
				LEFT JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				LEFT JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			WHERE t.id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
			)
		);
		list ($locked, $context['notify'], $sticky, $pollID, $context['topic_last_message'], $id_member_poster, $id_first_msg, $first_subject, $lastPostTime) = wedb::fetch_row($request);
		wedb::free_result($request);

		// If this topic already has a poll, they sure can't add another.
		if (isset($_REQUEST['poll']) && $pollID > 0)
			unset($_REQUEST['poll']);

		if (empty($_REQUEST['msg']))
		{
			if ($user_info['is_guest'] && !allowedTo('post_reply_any') && (!$modSettings['postmod_active'] || !allowedTo('post_unapproved_replies_any')))
				is_not_guest();

			// By default the reply will be approved...
			$context['becomes_approved'] = true;
			if ($id_member_poster != $user_info['id'])
			{
				if ($modSettings['postmod_active'] && allowedTo('post_unapproved_replies_any') && !allowedTo('post_reply_any'))
					$context['becomes_approved'] = false;
				else
					isAllowedTo('post_reply_any');
			}
			elseif (!allowedTo('post_reply_any'))
			{
				if ($modSettings['postmod_active'] && allowedTo('post_unapproved_replies_own') && !allowedTo('post_reply_own'))
					$context['becomes_approved'] = false;
				else
					isAllowedTo('post_reply_own');
			}
		}
		else
			$context['becomes_approved'] = true;

		$context['can_lock'] = allowedTo('lock_any') || ($user_info['id'] == $id_member_poster && allowedTo('lock_own'));
		$context['can_sticky'] = allowedTo('make_sticky') && !empty($modSettings['enableStickyTopics']);

		$context['notify'] = !empty($context['notify']);
		$context['sticky'] = isset($_REQUEST['sticky']) ? !empty($_REQUEST['sticky']) : $sticky;
	}
	else
	{
		$context['becomes_approved'] = true;
		if ((!$context['make_event'] || !empty($board)))
		{
			if ($modSettings['postmod_active'] && !allowedTo('post_new') && allowedTo('post_unapproved_topics'))
				$context['becomes_approved'] = false;
			else
				isAllowedTo('post_new');
		}

		$locked = 0;
		// !!! These won't work if you're making an event.
		$context['can_lock'] = allowedTo(array('lock_any', 'lock_own'));
		$context['can_sticky'] = allowedTo('make_sticky') && !empty($modSettings['enableStickyTopics']);

		$context['notify'] = !empty($context['notify']);
		$context['sticky'] = !empty($_REQUEST['sticky']);
	}

	// !!! These won't work if you're posting an event!
	$context['can_notify'] = allowedTo('mark_any_notify');
	$context['can_move'] = allowedTo('move_any');
	$context['move'] = !empty($_REQUEST['move']);
	$context['announce'] = !empty($_REQUEST['announce']);
	// You can only announce topics that will get approved...
	$context['can_announce'] = allowedTo('announce_topic') && $context['becomes_approved'];
	$context['locked'] = !empty($locked) || !empty($_REQUEST['lock']);
	$context['can_quote'] = empty($modSettings['disabledBBC']) || !in_array('quote', explode(',', $modSettings['disabledBBC']));

	// Generally don't show the approval box... (Assume we want things approved)
	$context['show_approval'] = false;

	// An array to hold all the attachments for this topic.
	$context['current_attachments'] = array();

	// Don't allow a post if it's locked and you aren't all powerful.
	if ($locked && !allowedTo('moderate_board'))
		fatal_lang_error('topic_locked', false);
	// Check the users permissions - is the user allowed to add or post a poll?
	if (isset($_REQUEST['poll']) && $modSettings['pollMode'] == '1')
	{
		// New topic, new poll.
		if (empty($topic))
			isAllowedTo('poll_post');
		// This is an old topic - but it is yours!  Can you add to it?
		elseif ($user_info['id'] == $id_member_poster && !allowedTo('poll_add_any'))
			isAllowedTo('poll_add_own');
		// If you're not the owner, can you add to any poll?
		else
			isAllowedTo('poll_add_any');

		loadSource('Subs-Members');
		$allowedVoteGroups = groupsAllowedTo('poll_vote', $board);

		// Set up the poll options.
		$context['poll_options'] = array(
			'max_votes' => empty($_POST['poll_max_votes']) ? '1' : max(1, $_POST['poll_max_votes']),
			'hide' => empty($_POST['poll_hide']) ? 0 : $_POST['poll_hide'],
			'expire' => !isset($_POST['poll_expire']) ? '' : $_POST['poll_expire'],
			'change_vote' => isset($_POST['poll_change_vote']),
			'guest_vote' => isset($_POST['poll_guest_vote']),
			'guest_vote_enabled' => in_array(-1, $allowedVoteGroups['allowed']),
		);

		// Make all five poll choices empty.
		$context['choices'] = array(
			array('id' => 0, 'number' => 1, 'label' => '', 'is_last' => false),
			array('id' => 1, 'number' => 2, 'label' => '', 'is_last' => false),
			array('id' => 2, 'number' => 3, 'label' => '', 'is_last' => false),
			array('id' => 3, 'number' => 4, 'label' => '', 'is_last' => false),
			array('id' => 4, 'number' => 5, 'label' => '', 'is_last' => true)
		);
	}

	if ($context['make_event'])
	{
		// They might want to pick a board.
		if (!isset($context['current_board']))
			$context['current_board'] = 0;

		// Start loading up the event info.
		$context['event'] = array();
		$context['event']['title'] = isset($_REQUEST['evtitle']) ? htmlspecialchars(stripslashes($_REQUEST['evtitle'])) : '';

		$context['event']['id'] = isset($_REQUEST['eventid']) ? (int) $_REQUEST['eventid'] : -1;
		$context['event']['new'] = $context['event']['id'] == -1;

		// Permissions check!
		isAllowedTo('calendar_post');

		// Editing an event?  (but NOT previewing!?)
		if (!$context['event']['new'] && !isset($_REQUEST['subject']))
		{
			// If the user doesn't have permission to edit the post in this topic, redirect them.
			if ((empty($id_member_poster) || $id_member_poster != $user_info['id'] || !allowedTo('modify_own')) && !allowedTo('modify_any'))
			{
				loadSource('Calendar');
				return CalendarPost();
			}

			// Get the current event information.
			$request = wedb::query('
				SELECT
					id_member, title, MONTH(start_date) AS month, DAYOFMONTH(start_date) AS day,
					YEAR(start_date) AS year, (TO_DAYS(end_date) - TO_DAYS(start_date)) AS span
				FROM {db_prefix}calendar
				WHERE id_event = {int:id_event}
				LIMIT 1',
				array(
					'id_event' => $context['event']['id'],
				)
			);
			$row = wedb::fetch_assoc($request);
			wedb::free_result($request);

			// Make sure the user is allowed to edit this event.
			if ($row['id_member'] != $user_info['id'])
				isAllowedTo('calendar_edit_any');
			elseif (!allowedTo('calendar_edit_any'))
				isAllowedTo('calendar_edit_own');

			$context['event']['month'] = $row['month'];
			$context['event']['day'] = $row['day'];
			$context['event']['year'] = $row['year'];
			$context['event']['title'] = $row['title'];
			$context['event']['span'] = $row['span'] + 1;
		}
		else
		{
			$today = getdate();

			// You must have a month and year specified!
			if (!isset($_REQUEST['month']))
				$_REQUEST['month'] = $today['mon'];
			if (!isset($_REQUEST['year']))
				$_REQUEST['year'] = $today['year'];

			$context['event']['month'] = (int) $_REQUEST['month'];
			$context['event']['year'] = (int) $_REQUEST['year'];
			$context['event']['day'] = isset($_REQUEST['day']) ? $_REQUEST['day'] : ($_REQUEST['month'] == $today['mon'] ? $today['mday'] : 0);
			$context['event']['span'] = isset($_REQUEST['span']) ? $_REQUEST['span'] : 1;

			// Make sure the year and month are in the valid range.
			if ($context['event']['month'] < 1 || $context['event']['month'] > 12)
				fatal_lang_error('invalid_month', false);
			if ($context['event']['year'] < $modSettings['cal_minyear'] || $context['event']['year'] > $modSettings['cal_maxyear'])
				fatal_lang_error('invalid_year', false);

			// Get a list of boards they can post in.
			$boards = boardsAllowedTo('post_new');
			if (empty($boards))
				fatal_lang_error('cannot_post_new', 'user');

			// Load a list of boards for this event in the context.
			loadSource('Subs-MessageIndex');
			$boardListOptions = array(
				'included_boards' => in_array(0, $boards) ? null : $boards,
				'not_redirection' => true,
				'use_permissions' => true,
				'selected_board' => empty($context['current_board']) ? $modSettings['cal_defaultboard'] : $context['current_board'],
			);
			$context['event']['categories'] = getBoardList($boardListOptions);
		}

		// Find the last day of the month.
		$context['event']['last_day'] = (int) strftime('%d', mktime(0, 0, 0, $context['event']['month'] == 12 ? 1 : $context['event']['month'] + 1, 0, $context['event']['month'] == 12 ? $context['event']['year'] + 1 : $context['event']['year']));

		$context['event']['board'] = !empty($board) ? $board : $modSettings['cal_defaultboard'];
	}

	if (empty($context['post_errors']))
		$context['post_errors'] = array();

	// See if any new replies have come along.
	if (empty($_REQUEST['msg']) && !empty($topic))
	{
		if (empty($options['no_new_reply_warning']) && isset($_REQUEST['last_msg']) && $context['topic_last_message'] > $_REQUEST['last_msg'])
		{
			$request = wedb::query('
				SELECT COUNT(*)
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_msg > {int:last_msg}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND approved = {int:approved}') . '
				LIMIT 1',
				array(
					'current_topic' => $topic,
					'last_msg' => (int) $_REQUEST['last_msg'],
					'approved' => 1,
				)
			);
			list ($context['new_replies']) = wedb::fetch_row($request);
			wedb::free_result($request);

			if (!empty($context['new_replies']))
			{
				if ($context['new_replies'] == 1)
					$txt['error_new_reply'] = isset($_GET['last_msg']) ? $txt['error_new_reply_reading'] : $txt['error_new_reply'];
				else
					$txt['error_new_replies'] = sprintf(isset($_GET['last_msg']) ? $txt['error_new_replies_reading'] : $txt['error_new_replies'], $context['new_replies']);

				// If they've come from the display page then we treat the error differently....
				if (isset($_GET['last_msg']))
					$newRepliesError = $context['new_replies'];
				else
					$context['post_error'][$context['new_replies'] == 1 ? 'new_reply' : 'new_replies'] = true;

				$modSettings['topicSummaryPosts'] = $context['new_replies'] > $modSettings['topicSummaryPosts'] ? max($modSettings['topicSummaryPosts'], 5) : $modSettings['topicSummaryPosts'];
			}
		}
		// Check whether this is a really old post being bumped...
		if (!empty($modSettings['oldTopicDays']) && $lastPostTime + $modSettings['oldTopicDays'] * 86400 < time() && empty($sticky) && !isset($_REQUEST['subject']))
			$oldTopicError = true;
	}

	// Get a response prefix (like 'Re:') in the default forum language.
	if (!isset($context['response_prefix']) && !($context['response_prefix'] = cache_get_data('response_prefix')))
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

	// Previewing, modifying, or posting?
	if (isset($_REQUEST['message']) || !empty($context['post_error']))
	{
		// Validate inputs.
		if (empty($context['post_error']))
		{
			if (htmltrim__recursive(htmlspecialchars__recursive($_REQUEST['subject'])) == '')
				$context['post_error']['no_subject'] = true;
			if (htmltrim__recursive(htmlspecialchars__recursive($_REQUEST['message'])) == '')
				$context['post_error']['no_message'] = true;
			if (!empty($modSettings['max_messageLength']) && $smcFunc['strlen']($_REQUEST['message']) > $modSettings['max_messageLength'])
				$context['post_error']['long_message'] = true;

			// Are you... a guest?
			if ($user_info['is_guest'])
			{
				$_REQUEST['guestname'] = !isset($_REQUEST['guestname']) ? '' : trim($_REQUEST['guestname']);
				$_REQUEST['email'] = !isset($_REQUEST['email']) ? '' : trim($_REQUEST['email']);

				// Validate the name and email.
				if (!isset($_REQUEST['guestname']) || trim(strtr($_REQUEST['guestname'], '_', ' ')) == '')
					$context['post_error']['no_name'] = true;
				elseif ($smcFunc['strlen']($_REQUEST['guestname']) > 25)
					$context['post_error']['long_name'] = true;
				else
				{
					loadSource('Subs-Members');
					if (isReservedName(htmlspecialchars($_REQUEST['guestname']), 0, true, false))
						$context['post_error']['bad_name'] = true;
				}

				if (empty($modSettings['guest_post_no_email']))
				{
					if (!isset($_REQUEST['email']) || $_REQUEST['email'] == '')
						$context['post_error']['no_email'] = true;
					elseif (preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', $_REQUEST['email']) == 0)
						$context['post_error']['bad_email'] = true;
				}
			}

			// This is self explanatory - got any questions?
			if (isset($_REQUEST['question']) && trim($_REQUEST['question']) == '')
				$context['post_error']['no_question'] = true;

			// This means they didn't click Post and get an error.
			$really_previewing = true;
		}
		else
		{
			if (!isset($_REQUEST['subject']))
				$_REQUEST['subject'] = '';
			if (!isset($_REQUEST['message']))
				$_REQUEST['message'] = '';
			if (!isset($_REQUEST['icon']))
				$_REQUEST['icon'] = 'xx';

			// They are previewing if they asked to preview (i.e. came from quick reply).
			$really_previewing = !empty($_POST['preview']);
		}

		// In order to keep the approval status flowing through, we have to pass it through the form...
		$context['becomes_approved'] = empty($_REQUEST['not_approved']);
		$context['show_approval'] = isset($_REQUEST['approve']) ? ($_REQUEST['approve'] ? 2 : 1) : 0;
		$context['can_announce'] &= $context['becomes_approved'];

		// Set up the inputs for the form.
		$form_subject = strtr($smcFunc['htmlspecialchars']($_REQUEST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
		$form_message = $smcFunc['htmlspecialchars']($_REQUEST['message'], ENT_QUOTES);

		// Make sure the subject isn't too long - taking into account special characters.
		if ($smcFunc['strlen']($form_subject) > 100)
			$form_subject = $smcFunc['substr']($form_subject, 0, 100);

		// Have we inadvertently trimmed off the subject of useful information?
		if ($smcFunc['htmltrim']($form_subject) === '')
			$context['post_error']['no_subject'] = true;

		// Any errors occurred?
		if (!empty($context['post_error']))
		{
			loadLanguage('Errors');

			$context['error_type'] = 'minor';

			$context['post_error']['messages'] = array();
			foreach ($context['post_error'] as $post_error => $dummy)
			{
				if ($post_error == 'messages')
					continue;

				if ($post_error == 'long_message')
					$txt['error_' . $post_error] = sprintf($txt['error_' . $post_error], $modSettings['max_messageLength']);

				$context['post_error']['messages'][] = $txt['error_' . $post_error];

				// If it's not a minor error flag it as such.
				if (!in_array($post_error, array('new_reply', 'not_approved', 'new_replies', 'old_topic', 'need_qr_verification')))
					$context['error_type'] = 'serious';
			}
		}

		if (isset($_REQUEST['poll']))
		{
			$context['question'] = isset($_REQUEST['question']) ? $smcFunc['htmlspecialchars'](trim($_REQUEST['question'])) : '';

			$context['choices'] = array();
			$choice_id = 0;

			$_POST['options'] = empty($_POST['options']) ? array() : htmlspecialchars__recursive($_POST['options']);
			foreach ($_POST['options'] as $option)
			{
				if (trim($option) == '')
					continue;

				$context['choices'][] = array(
					'id' => $choice_id++,
					'number' => $choice_id,
					'label' => $option,
					'is_last' => false
				);
			}

			if (count($context['choices']) < 2)
			{
				$context['choices'][] = array(
					'id' => $choice_id++,
					'number' => $choice_id,
					'label' => '',
					'is_last' => false
				);
				$context['choices'][] = array(
					'id' => $choice_id++,
					'number' => $choice_id,
					'label' => '',
					'is_last' => false
				);
			}
			$context['choices'][count($context['choices']) - 1]['is_last'] = true;
		}

		// Are you... a guest?
		if ($user_info['is_guest'])
		{
			$_REQUEST['guestname'] = !isset($_REQUEST['guestname']) ? '' : trim($_REQUEST['guestname']);
			$_REQUEST['email'] = !isset($_REQUEST['email']) ? '' : trim($_REQUEST['email']);

			$_REQUEST['guestname'] = htmlspecialchars($_REQUEST['guestname']);
			$context['name'] = $_REQUEST['guestname'];
			$_REQUEST['email'] = htmlspecialchars($_REQUEST['email']);
			$context['email'] = $_REQUEST['email'];

			$user_info['name'] = $_REQUEST['guestname'];
		}

		// Only show the preview stuff if they hit Preview.
		if ($really_previewing == true || isset($_REQUEST['xml']))
		{
			// Set up the preview message and subject and censor them...
			$context['preview_message'] = $form_message;
			wedgeEditor::preparsecode($form_message, true);
			wedgeEditor::preparsecode($context['preview_message']);

			// Do all bulletin board code tags, with or without smileys.
			$context['preview_message'] = parse_bbc($context['preview_message'], isset($_REQUEST['ns']) ? 0 : 1);

			if ($form_subject != '')
			{
				$context['preview_subject'] = $form_subject;

				censorText($context['preview_subject']);
				censorText($context['preview_message']);
			}
			else
				$context['preview_subject'] = '<em>' . $txt['no_subject'] . '</em>';

			// Protect any CDATA blocks.
			if (isset($_REQUEST['xml']))
				$context['preview_message'] = strtr($context['preview_message'], array(']]>' => ']]]]><![CDATA[>'));
		}

		// Set up the checkboxes.
		$context['notify'] = !empty($_REQUEST['notify']);
		$context['use_smileys'] = !isset($_REQUEST['ns']);

		$context['icon'] = isset($_REQUEST['icon']) ? preg_replace('~[\./\\\\*\':"<>]~', '', $_REQUEST['icon']) : 'xx';

		// Set the destination action for submission.
		$context['destination'] = 'post2;start=' . $_REQUEST['start'] . (isset($_REQUEST['msg']) ? ';msg=' . $_REQUEST['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'] : '') . (isset($_REQUEST['poll']) ? ';poll' : '');
		$context['submit_label'] = isset($_REQUEST['msg']) ? $txt['save'] : $txt['post'];

		// Previewing an edit?
		if (isset($_REQUEST['msg']) && !empty($topic))
		{
			// Get the existing message.
			$request = wedb::query('
				SELECT
					m.id_member, m.modified_time, m.smileys_enabled, m.body,
					m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
					IFNULL(a.size, -1) AS filesize, a.filename, a.id_attach,
					a.approved AS attachment_approved, t.id_member_started AS id_member_poster,
					m.poster_time
			FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_msg = m.id_msg AND a.attachment_type = {int:attachment_type})
				WHERE m.id_msg = {int:id_msg}
					AND m.id_topic = {int:current_topic}',
				array(
					'current_topic' => $topic,
					'attachment_type' => 0,
					'id_msg' => $_REQUEST['msg'],
				)
			);
			// The message they were trying to edit was most likely deleted.
			// !!! Change this error message?
			if (wedb::num_rows($request) == 0)
				fatal_lang_error('no_board', false);
			$row = wedb::fetch_assoc($request);

			$attachment_stuff = array($row);
			while ($row2 = wedb::fetch_assoc($request))
				$attachment_stuff[] = $row2;
			wedb::free_result($request);

			if ($row['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
			{
				// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
				if ($row['approved'] && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
					fatal_lang_error('modify_post_time_passed', false);
				elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('modify_own'))
					isAllowedTo('modify_replies');
				else
					isAllowedTo('modify_own');
			}
			elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('modify_any'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_any');

			if (!empty($modSettings['attachmentEnable']))
			{
				$request = wedb::query('
					SELECT IFNULL(size, -1) AS filesize, filename, id_attach, approved
					FROM {db_prefix}attachments
					WHERE id_msg = {int:id_msg}
						AND attachment_type = {int:attachment_type}',
					array(
						'id_msg' => (int) $_REQUEST['msg'],
						'attachment_type' => 0,
					)
				);
				while ($row = wedb::fetch_assoc($request))
				{
					if ($row['filesize'] <= 0)
						continue;
					$context['current_attachments'][] = array(
						'name' => htmlspecialchars($row['filename']),
						'id' => $row['id_attach'],
						'approved' => $row['approved'],
					);
				}
				wedb::free_result($request);
			}

			// Allow moderators to change names....
			if (allowedTo('moderate_forum') && !empty($topic))
			{
				$request = wedb::query('
					SELECT id_member, poster_name, poster_email
					FROM {db_prefix}messages
					WHERE id_msg = {int:id_msg}
						AND id_topic = {int:current_topic}
					LIMIT 1',
					array(
						'current_topic' => $topic,
						'id_msg' => (int) $_REQUEST['msg'],
					)
				);
				$row = wedb::fetch_assoc($request);
				wedb::free_result($request);

				if (empty($row['id_member']))
				{
					$context['name'] = htmlspecialchars($row['poster_name']);
					$context['email'] = htmlspecialchars($row['poster_email']);
				}
			}
		}

		// No check is needed, since nothing is really posted.
		checkSubmitOnce('free');
	}
	// Editing a message...
	elseif (isset($_REQUEST['msg']) && !empty($topic))
	{
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Get the existing message.
		$request = wedb::query('
			SELECT
				m.id_member, m.modified_time, m.smileys_enabled, m.body,
				m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
				IFNULL(a.size, -1) AS filesize, a.filename, a.id_attach,
				a.approved AS attachment_approved, t.id_member_started AS id_member_poster,
				m.poster_time
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
				LEFT JOIN {db_prefix}attachments AS a ON (a.id_msg = m.id_msg AND a.attachment_type = {int:attachment_type})
			WHERE m.id_msg = {int:id_msg}
				AND m.id_topic = {int:current_topic}',
			array(
				'current_topic' => $topic,
				'attachment_type' => 0,
				'id_msg' => $_REQUEST['msg'],
			)
		);
		// The message they were trying to edit was most likely deleted.
		// !!! Change this error message?
		if (wedb::num_rows($request) == 0)
			fatal_lang_error('no_board', false);
		$row = wedb::fetch_assoc($request);

		$attachment_stuff = array($row);
		while ($row2 = wedb::fetch_assoc($request))
			$attachment_stuff[] = $row2;
		wedb::free_result($request);

		if ($row['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
		{
			// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
			if ($row['approved'] && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
			elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('modify_own'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_own');
		}
		elseif ($row['id_member_poster'] == $user_info['id'] && !allowedTo('modify_any'))
			isAllowedTo('modify_replies');
		else
			isAllowedTo('modify_any');

		// When was it last modified?
		if (!empty($row['modified_time']))
			$context['last_modified'] = timeformat($row['modified_time']);

		// Get the stuff ready for the form.
		$form_subject = $row['subject'];
		$form_message = wedgeEditor::un_preparsecode($row['body']);
		censorText($form_message);
		censorText($form_subject);

		// Check the boxes that should be checked.
		$context['use_smileys'] = !empty($row['smileys_enabled']);
		$context['icon'] = $row['icon'];

		// Show an "approve" box if the user can approve it, and the message isn't approved.
		if (!$row['approved'] && !$context['show_approval'])
			$context['show_approval'] = allowedTo('approve_posts');

		// Load up 'em attachments!
		foreach ($attachment_stuff as $attachment)
		{
			if ($attachment['filesize'] >= 0 && !empty($modSettings['attachmentEnable']))
				$context['current_attachments'][] = array(
					'name' => htmlspecialchars($attachment['filename']),
					'id' => $attachment['id_attach'],
					'approved' => $attachment['attachment_approved'],
				);
		}

		// Allow moderators to change names....
		if (allowedTo('moderate_forum') && empty($row['id_member']))
		{
			$context['name'] = htmlspecialchars($row['poster_name']);
			$context['email'] = htmlspecialchars($row['poster_email']);
		}

		// Set the destinaton.
		$context['destination'] = 'post2;start=' . $_REQUEST['start'] . ';msg=' . $_REQUEST['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'] . (isset($_REQUEST['poll']) ? ';poll' : '');
		$context['submit_label'] = $txt['save'];
	}
	// Posting...
	else
	{
		// By default....
		$context['use_smileys'] = true;
		$context['icon'] = 'xx';

		if ($user_info['is_guest'])
		{
			$context['name'] = isset($_SESSION['guest_name']) ? $_SESSION['guest_name'] : '';
			$context['email'] = isset($_SESSION['guest_email']) ? $_SESSION['guest_email'] : '';
		}
		$context['destination'] = 'post2;start=' . $_REQUEST['start'] . (isset($_REQUEST['poll']) ? ';poll' : '');

		$context['submit_label'] = $txt['post'];

		// Posting a quoted reply?
		if (!empty($topic) && !empty($_REQUEST['quote']))
		{
			// Make sure they _can_ quote this post, and if so get it.
			$request = wedb::query('
				SELECT m.subject, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE m.id_msg = {int:id_msg}' . (!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND m.approved = {int:is_approved}') . '
				LIMIT 1',
				array(
					'id_msg' => (int) $_REQUEST['quote'],
					'is_approved' => 1,
				)
			);
			if (wedb::num_rows($request) == 0)
				fatal_lang_error('quoted_post_deleted', false);
			list ($form_subject, $mname, $mdate, $form_message) = wedb::fetch_row($request);
			wedb::free_result($request);

			// Add 'Re: ' to the front of the quoted subject.
			if (trim($context['response_prefix']) != '' && $smcFunc['strpos']($form_subject, trim($context['response_prefix'])) !== 0)
				$form_subject = $context['response_prefix'] . $form_subject;

			// Censor the message and subject.
			censorText($form_message);
			censorText($form_subject);

			// But if it's in HTML world, turn them into htmlspecialchar's so they can be edited!
			if (strpos($form_message, '[html]') !== false)
			{
				$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $form_message, -1, PREG_SPLIT_DELIM_CAPTURE);
				for ($i = 0, $n = count($parts); $i < $n; $i++)
				{
					// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
					if ($i % 4 == 0)
						$parts[$i] = preg_replace('~\[html\](.+?)\[/html\]~ise', '\'[html]\' . preg_replace(\'~<br\s?/?' . '>~i\', \'&lt;br /&gt;<br />\', \'$1\') . \'[/html]\'', $parts[$i]);
				}
				$form_message = implode('', $parts);
			}

			$form_message = preg_replace('~<br ?/?' . '>~i', "\n", $form_message);

			// Remove any nested quotes, if necessary.
			if (!empty($modSettings['removeNestedQuotes']))
				$form_message = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $form_message);

			// Add a quote string on the front and end.
			$form_message = '[quote author=' . $mname . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $mdate . ']' . "\n" . rtrim($form_message) . "\n" . '[/quote]';
		}
		// Posting a reply without a quote?
		elseif (!empty($topic) && empty($_REQUEST['quote']))
		{
			// Get the first message's subject.
			$form_subject = $first_subject;

			// Add 'Re: ' to the front of the subject.
			if (trim($context['response_prefix']) != '' && $form_subject != '' && $smcFunc['strpos']($form_subject, trim($context['response_prefix'])) !== 0)
				$form_subject = $context['response_prefix'] . $form_subject;

			// Censor the subject.
			censorText($form_subject);

			$form_message = '';
		}
		else
		{
			$form_subject = isset($_GET['subject']) ? $_GET['subject'] : '';
			$form_message = '';
		}
	}

	// !!! This won't work if you're posting an event.
	if (allowedTo('post_attachment') || allowedTo('post_unapproved_attachments'))
	{
		if (empty($_SESSION['temp_attachments']))
			$_SESSION['temp_attachments'] = array();

		if (!empty($modSettings['currentAttachmentUploadDir']))
		{
			if (!is_array($modSettings['attachmentUploadDir']))
				$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

			// Just use the current path for temp files.
			$current_attach_dir = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
		}
		else
			$current_attach_dir = $modSettings['attachmentUploadDir'];

		// If this isn't a new post, check the current attachments.
		if (isset($_REQUEST['msg']))
		{
			$request = wedb::query('
				SELECT COUNT(*), SUM(size)
				FROM {db_prefix}attachments
				WHERE id_msg = {int:id_msg}
					AND attachment_type = {int:attachment_type}',
				array(
					'id_msg' => (int) $_REQUEST['msg'],
					'attachment_type' => 0,
				)
			);
			list ($quantity, $total_size) = wedb::fetch_row($request);
			wedb::free_result($request);
		}
		else
		{
			$quantity = 0;
			$total_size = 0;
		}

		$temp_start = 0;

		if (!empty($_SESSION['temp_attachments']))
		{
			if ($context['current_action'] != 'post2' || !empty($_POST['from_qr']))
			{
				$context['post_error']['messages'][] = $txt['error_temp_attachments'];
				$context['error_type'] = 'minor';
			}

			foreach ($_SESSION['temp_attachments'] as $attachID => $name)
			{
				$temp_start++;

				if (preg_match('~^post_tmp_' . $user_info['id'] . '_\d+$~', $attachID) == 0)
				{
					unset($_SESSION['temp_attachments'][$attachID]);
					continue;
				}

				if (!empty($_POST['attach_del']) && !in_array($attachID, $_POST['attach_del']))
				{
					$deleted_attachments = true;
					unset($_SESSION['temp_attachments'][$attachID]);
					@unlink($current_attach_dir . '/' . $attachID);
					continue;
				}

				$quantity++;
				$total_size += filesize($current_attach_dir . '/' . $attachID);

				$context['current_attachments'][] = array(
					'name' => htmlspecialchars($name),
					'id' => $attachID,
					'approved' => 1,
				);
			}
		}

		if (!empty($_POST['attach_del']))
		{
			$del_temp = array();
			foreach ($_POST['attach_del'] as $i => $dummy)
				$del_temp[$i] = (int) $dummy;

			foreach ($context['current_attachments'] as $k => $dummy)
				if (!in_array($dummy['id'], $del_temp))
				{
					$context['current_attachments'][$k]['unchecked'] = true;
					$deleted_attachments = !isset($deleted_attachments) || is_bool($deleted_attachments) ? 1 : $deleted_attachments + 1;
					$quantity--;
				}
		}

		if (!empty($_FILES['attachment']))
			foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy)
			{
				if ($_FILES['attachment']['name'][$n] == '')
					continue;

				if (!is_uploaded_file($_FILES['attachment']['tmp_name'][$n]) || (@ini_get('open_basedir') == '' && !file_exists($_FILES['attachment']['tmp_name'][$n])))
					fatal_lang_error('attach_timeout', 'critical');

				if (!empty($modSettings['attachmentSizeLimit']) && $_FILES['attachment']['size'][$n] > $modSettings['attachmentSizeLimit'] * 1024)
					fatal_lang_error('file_too_big', false, array($modSettings['attachmentSizeLimit']));

				$quantity++;
				if (!empty($modSettings['attachmentNumPerPostLimit']) && $quantity > $modSettings['attachmentNumPerPostLimit'])
					fatal_lang_error('attachments_limit_per_post', false, array($modSettings['attachmentNumPerPostLimit']));

				$total_size += $_FILES['attachment']['size'][$n];
				if (!empty($modSettings['attachmentPostLimit']) && $total_size > $modSettings['attachmentPostLimit'] * 1024)
					fatal_lang_error('file_too_big', false, array($modSettings['attachmentPostLimit']));

				if (!empty($modSettings['attachmentCheckExtensions']))
				{
					if (!in_array(strtolower(substr(strrchr($_FILES['attachment']['name'][$n], '.'), 1)), explode(',', strtolower($modSettings['attachmentExtensions']))))
						fatal_error($_FILES['attachment']['name'][$n] . '.<br />' . $txt['cant_upload_type'] . ' ' . $modSettings['attachmentExtensions'] . '.', false);
				}

				if (!empty($modSettings['attachmentDirSizeLimit']))
				{
					// Make sure the directory isn't full.
					$dirSize = 0;
					$dir = @scandir($current_attach_dir) or fatal_lang_error('cant_access_upload_path', 'critical');
					foreach ($dir as $file)
					{
						if ($file == '.' || $file == '..')
							continue;

						if (preg_match('~^post_tmp_\d+_\d+$~', $file) != 0)
						{
							// Temp file is more than 5 hours old!
							if (filemtime($current_attach_dir . '/' . $file) < time() - 18000)
								@unlink($current_attach_dir . '/' . $file);
							continue;
						}

						$dirSize += filesize($current_attach_dir . '/' . $file);
					}

					// Too big!  Maybe you could zip it or something...
					if ($_FILES['attachment']['size'][$n] + $dirSize > $modSettings['attachmentDirSizeLimit'] * 1024)
						fatal_lang_error('ran_out_of_space');
				}

				if (!is_writable($current_attach_dir))
					fatal_lang_error('attachments_no_write', 'critical');

				$attachID = 'post_tmp_' . $user_info['id'] . '_' . $temp_start++;
				$_SESSION['temp_attachments'][$attachID] = basename($_FILES['attachment']['name'][$n]);
				$context['current_attachments'][] = array(
					'name' => htmlspecialchars(basename($_FILES['attachment']['name'][$n])),
					'id' => $attachID,
					'approved' => 1,
				);

				$destName = $current_attach_dir . '/' . $attachID;

				if (!move_uploaded_file($_FILES['attachment']['tmp_name'][$n], $destName))
					fatal_lang_error('attach_timeout', 'critical');
				@chmod($destName, 0644);
			}
	}

	// If we are coming here to make a reply, and someone has already replied... make a special warning message.
	if (isset($newRepliesError))
	{
		$context['post_error']['messages'][] = $newRepliesError == 1 ? $txt['error_new_reply'] : $txt['error_new_replies'];
		$context['error_type'] = 'minor';
	}

	if (isset($oldTopicError))
	{
		$context['post_error']['messages'][] = sprintf($txt['error_old_topic'], $modSettings['oldTopicDays']);
		$context['error_type'] = 'minor';
	}

	// What are you doing?  Posting a poll, modifying, previewing, new post, or reply...
	if (isset($_REQUEST['poll']))
		$context['page_title'] = $txt['new_poll'];
	elseif ($context['make_event'])
		$context['page_title'] = $context['event']['id'] == -1 ? $txt['calendar_post_event'] : $txt['calendar_edit'];
	elseif (isset($_REQUEST['msg']))
		$context['page_title'] = $txt['modify_msg'];
	elseif (isset($_REQUEST['subject'], $context['preview_subject']))
		$context['page_title'] = $txt['preview'] . ' - ' . strip_tags($context['preview_subject']);
	elseif (empty($topic))
		$context['page_title'] = $txt['start_new_topic'];
	else
		$context['page_title'] = $txt['post_reply'];

	// Build the link tree.
	if (empty($topic))
		$context['linktree'][] = array(
			'name' => '<em>' . $txt['start_new_topic'] . '</em>'
		);
	else
		$context['linktree'][] = array(
			'url' => $scripturl . '?topic=' . $topic . '.' . $_REQUEST['start'],
			'name' => $form_subject,
			'extra_before' => '<span' . ($settings['linktree_inline'] ? ' class="smalltext"' : '') . '><strong class="nav">' . $context['page_title'] . ' ( </strong></span>',
			'extra_after' => '<span' . ($settings['linktree_inline'] ? ' class="smalltext"' : '') . '><strong class="nav"> )</strong></span>'
		);

	// Give wireless a linktree url to the post screen, so that they can switch to full version.
	if (WIRELESS)
		$context['linktree'][count($context['linktree']) - 1]['url'] = $scripturl . '?action=post;' . (!empty($topic) ? 'topic=' . $topic : 'board=' . $board) . '.' . $_REQUEST['start'] . (isset($_REQUEST['msg']) ? ';msg=' . (int) $_REQUEST['msg'] . ';' . $context['session_var'] . '=' . $context['session_id'] : '');

	// We need to check permissions, and also send the maximum allowed attachments through to the front end - it's dealt with there.
	// !!! This won't work if you're posting an event.
	$context['max_allowed_attachments'] = empty($modSettings['attachmentNumPerPostLimit']) ? 50 : $modSettings['attachmentNumPerPostLimit'];
	$context['can_post_attachment'] = !empty($modSettings['attachmentEnable']) && $modSettings['attachmentEnable'] == 1 && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments'))) && $context['max_allowed_attachments'] > 0;
	$context['can_post_attachment_unapproved'] = allowedTo('post_attachment');

	$context['subject'] = addcslashes($form_subject, '"');
	$context['message'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_message);

	// Now create the editor.
	$context['postbox'] = new wedgeEditor(
		array(
			'id' => 'message',
			'value' => $context['message'],
			'labels' => array(
				'post_button' => $context['submit_label'],
			),
			// add height and width for the editor
			'height' => '175px',
			'width' => '100%',
			// We do XML preview here.
			'preview_type' => 2,
		)
	);

	$context['attached'] = '';
	$context['make_poll'] = isset($_REQUEST['poll']);

	// Message icons - customized icons are off?
	$context['icons'] = getMessageIcons($board);

	if (!empty($context['icons']))
		$context['icons'][count($context['icons']) - 1]['is_last'] = true;

	$context['icon_url'] = '';
	for ($i = 0, $n = count($context['icons']); $i < $n; $i++)
	{
		$context['icons'][$i]['selected'] = $context['icon'] == $context['icons'][$i]['value'];
		if ($context['icons'][$i]['selected'])
			$context['icon_url'] = $context['icons'][$i]['url'];
	}
	if (empty($context['icon_url']))
	{
		$context['icon_url'] = $settings[file_exists($settings['theme_dir'] . '/images/post/' . $context['icon'] . '.gif') ? 'images_url' : 'default_images_url'] . '/post/' . $context['icon'] . '.gif';
		array_unshift($context['icons'], array(
			'value' => $context['icon'],
			'name' => $txt['current_icon'],
			'url' => $context['icon_url'],
			'is_last' => empty($context['icons']),
			'selected' => true,
		));
	}

	if (!empty($topic) && !empty($modSettings['topicSummaryPosts']))
		getTopic();

	// If the user can post attachments prepare the warning labels.
	if ($context['can_post_attachment'])
	{
		$context['allowed_extensions'] = strtr($modSettings['attachmentExtensions'], array(',' => ', '));
		$context['attachment_restrictions'] = array();
		$attachmentRestrictionTypes = array('attachmentNumPerPostLimit', 'attachmentPostLimit', 'attachmentSizeLimit');
		foreach ($attachmentRestrictionTypes as $type)
			if (!empty($modSettings[$type]))
				$context['attachment_restrictions'][] = sprintf($txt['attach_restrict_' . $type], $modSettings[$type]);
	}

	$context['back_to_topic'] = isset($_REQUEST['goback']) || (isset($_REQUEST['msg']) && !isset($_REQUEST['subject']));
	$context['show_additional_options'] = !empty($_POST['additional_options']) || !empty($_SESSION['temp_attachments']) || !empty($deleted_attachments);

	$context['is_new_topic'] = empty($topic);
	$context['is_new_post'] = !isset($_REQUEST['msg']);
	$context['is_first_post'] = $context['is_new_topic'] || (isset($_REQUEST['msg']) && $_REQUEST['msg'] == $id_first_msg);

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

	// If they came from quick reply, and have to enter verification details, give them some notice.
	if (!empty($_REQUEST['from_qr']) && !empty($context['require_verification']))
	{
		$context['post_error']['messages'][] = $txt['enter_verification_details'];
		$context['error_type'] = 'minor';
	}

	// WYSIWYG only works if BBC is enabled
	$modSettings['disable_wysiwyg'] = !empty($modSettings['disable_wysiwyg']) || empty($modSettings['enableBBC']);

	// Register this form in the session variables.
	checkSubmitOnce('register');

	// Finally, load the template.
	if (WIRELESS)
		$context['sub_template'] = WIRELESS_PROTOCOL . '_post';
	elseif (!isset($_REQUEST['xml']))
		loadTemplate('Post');
}

/**
 * This function gets the most recent posts, including new replies, for the post view.
 *
 * - If calling from an XML view, the number of replies is simply the new ones, otherwise it attempts to get the number specified as the amount of posts to return in $modSettings['topicSummaryPosts'].
 * - Note that if getting posts for an edit request, only the posts prior to the one being edited are considered.
 * - The posts are censored, BBC processed, and stored in an array within $context.
 * - The number of new replies is known from processing elsewhere in Post(), so it simply has to count the number of posts back to know which ones are new (e.g. 4 new replies, the 4 newest get 'new' flags set)
 */
function getTopic()
{
	global $topic, $modSettings, $context, $smcFunc, $counter, $options;

	if (isset($_REQUEST['xml']))
		$limit = '
		LIMIT ' . (empty($context['new_replies']) ? '0' : $context['new_replies']);
	else
		$limit = empty($modSettings['topicSummaryPosts']) ? '' : '
		LIMIT ' . (int) $modSettings['topicSummaryPosts'];

	// If you're modifying, get only those posts before the current one. (otherwise get all.)
	$request = wedb::query('
		SELECT
			IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
			m.body, m.smileys_enabled, m.id_msg, m.id_member
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_topic = {int:current_topic}' . (isset($_REQUEST['msg']) ? '
			AND m.id_msg < {int:id_msg}' : '') .(!$modSettings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND m.approved = {int:approved}') . '
		ORDER BY m.id_msg DESC' . $limit,
		array(
			'current_topic' => $topic,
			'id_msg' => isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0,
			'approved' => 1,
		)
	);
	$context['previous_posts'] = array();
	while ($row = wedb::fetch_assoc($request))
	{
		// Censor, BBC, ...
		censorText($row['body']);
		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// ...and store.
		$context['previous_posts'][] = array(
			'counter' => $counter++,
			'alternate' => $counter % 2,
			'poster' => $row['poster_name'],
			'message' => $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'id' => $row['id_msg'],
			'is_new' => !empty($context['new_replies']),
			'is_ignored' => !empty($modSettings['enable_buddylist']) && !empty($options['posts_apply_ignore_list']) && in_array($row['id_member'], $context['user']['ignoreusers']),
		);

		if (!empty($context['new_replies']))
			$context['new_replies']--;
	}
	wedb::free_result($request);
}

?>