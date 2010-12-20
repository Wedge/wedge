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
 * This file handles saving posts, or redirecting back to Post() in Post.php in the event of not being able to make the post for some reason.
 *
 * @package wedge
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Handles all the receipts of message posting, including calendar events, attachments and polls.
 *
 * Accessed via ?action=post2 (be it from the main reply page with all the goodies or the quick reply page)
 *
 * - If nothing is specified, return the user to the right posting place (either to the topic if specified, or to the board if not) so they can post a new message.
 * - Tell bots not to index.
 * - Load the relevant editor components.
 * - If we've come from WYSIWYG, process it back to BBC.
 * - Are we previewing? (i.e. hit the preview button from quick reply) Push the user to the main post action instead where it will be dealt with.
 * - Check the user hasn't submitted twice.
 * - Check the session from the form - however don't fail fatally if it mismatches, just add it to the list of errors we may be accumulating.
 * - Check whether a CAPTCHA was requested, and add to the error list if necessary.
 * - Load Subs-Post for the post subfunctions, and the Post language file.
 * - Is this a new topic? If not, grab all the topic's details. Die if we can't obtain them (not a permissions check, simply an existence one)
 * - Are we replying to a topic? Check it's not locked (if it is, require moderate_board to post without unlock), check there aren't multiple polls, then check permissions including approvals, and check whether the lock and/or sticky status is changing.
 * - Otherwise, we're doing a new topic, so... override any listed message id, check permissions including approvals, sort out sticky/lock status.
 * - Or, actually, are we doing a message edit? If so, get all the details of the message that will enable us to do things like permission checks (is it our own, etc), deal with locked/sticky. Also is it that we can approve it through editing?
 * - Is it a guest doing this? If so, check whether the name and email we've requested could be valid (e.g. not over-long, valid email address, not a banned email address)
 * - Check the message and subject exist and aren't empty (and the message isn't too long)
 * - Assuming we have a message, use our custom htmlspecialchars on it, preparse it for BBC compliance and safety, and see if there's still a message after we strip all the tags (except images) from it.
 * - If we're posting a calendar event (and everything's on for that), check there's a title there too.
 * - Is there a poll (and polls are on)? Check we're either doing a new topic+poll or adding one to an existing topic (and not via edit post). Validate permissions (either new or can-add-to-(my/any)-posts), check there's a subject and answers (and validate using the recursive HTML trim option, pruning empty ones, erroring if less than 2 choices left)
 * - If the user is a guest, validate the name isn't reserved, otherwise store the member's name+email ready.
 * - OK, at this point, we're done with checking stuff. If there are errors now, throw it over to {@link Post()} to display, and advise that we're previewing in the process.
 * - If this is a new post (rather than an edit) check the flood log.
 * - Attempt to set the time limit to 5 minutes for this post, and disable user-abort from the cancel button.
 * - Sanitize the subject with our custom htmlspecialchars, and convert CR, LF and TAB to empty strings.
 * - Sanitize the guest posting name and email with htmlspecialchars.
 * - Shorten the subject if necessary.
 * - If this is a poll, sanitize: the number of options, the expiry time if set, plus other options such as whether users can change vote or whether guests can vote. Lastly sanitize all the question and answers through recursively stepping through the array from $_POST.
 * - If this is an edit, see if the user is attempting to remove attachments. (If so, they need the ability to post them to be able to remove them, or at least be able to post unapproved attachments) Assuming permission is granted, check the ones they did not tick, and pass that to {@link removeAttachments()} to deal with.
 * - Are there new attachments? Check what's been submitted there, validate about coming from the session or quick reply, and also check the session - in the case of there being an error and bounced back to the main post code, the details of attachments are preserved in the session. Check permissions to post new (unapproved or otherwise) attachments, and also grab details of any current attachments to this post if it's an edit - we need to be able to see if we step over any limits. Proceed to check the limits: number per post, max size total per post. Then proceed to call the attachment handler, which can return any one of several errors (file too big, file fails extension check, attach directory full etc) - if an error is hit, ensure the nonce is unset for the form so we could go back and edit.
 * - Is this a poll? If so, insert the question details, followed by all the possible answers into the DB.
 * - Collate the main arrays of details, $msgOptions, $topicOptions and $posterOptions.
 * - If this is an edit, divert to {@link modifyPost()} otherwise head to {@link createPost()} to create the new post (and topic if appropriate).
 * - If this is an event being linked to the calendar, manage that too.
 * - If this is a new event being posted, validate the contents, check permissions, delete it if that's what the request said to do, update if necessary (including the master cache value)
 * - Mark this board read, and all its hierarchical parents, for the person making the edit.
 * - If the user asks to be notified on the topic's replies, add them to the notification list (assuming they have permission)
 * - If this isn't a new topic, remove them from the notification log because we don't need to tell them about this post they just dealt with.
 * - If the action carried out here was a modify not done by the post's owner, log it in the moderation log.
 * - If we've locked this post through reply or edit, and it's not a user lock, log that.
 * - If we've stickied it through reply or edit, log that.
 * - If the new post would be approved (either automatically or as a result of this action): on new topic, send notification to everyone who wants notifications on this board. Otherwise, send it to everyone if the topic is approved, or just the topic starter if they want it.
 * - Now, where next? If they're going back to the topic, mark the board as read.
 * - If there's no topics in the board, empty the cache.
 * - If we hit the announce option, redirect to the announce-topic stuff.
 * - If we hit 'move this topic', well, go to that.
 * - If we have a message (i.e. we came from a message via the modify button) and we're going back to topic, go back to that message.
 * - If we're just going back to the topic, go to the end of it (which is our new post)
 * - Otherwise just go back to the board.
 */
function Post2()
{
	global $board, $topic, $txt, $modSettings, $context;
	global $user_info, $board_info, $options;

	// Sneaking off, are we?
	if (empty($_POST) && empty($topic))
		redirectexit('action=post;board=' . $board . '.0');
	elseif (empty($_POST) && !empty($topic))
		redirectexit('action=post;topic=' . $topic . '.0');

	// No need!
	$context['robot_no_index'] = true;

	// Things we need, to make us strong. We like being strong.
	loadSource(array('Subs-Editor', 'Class-Editor'));

	// If we came from WYSIWYG then turn it back into BBC regardless. Make sure we tell it what item we're expecting to use.
	wedgeEditor::preparseWYSIWYG('message');

	// Previewing? Go back to start.
	if (isset($_REQUEST['preview']))
	{
		loadSource('Post');
		return Post();
	}

	// Prevent double submission of this form.
	checkSubmitOnce('check');

	// No errors as yet.
	$post_errors = array();

	// If the session has timed out, let the user re-submit their form.
	if (checkSession('post', '', false) != '')
		$post_errors[] = 'session_timeout';

	// Wrong verification code?
	if (!$user_info['is_admin'] && !$user_info['is_mod'] && !empty($modSettings['posts_require_captcha']) && ($user_info['posts'] < $modSettings['posts_require_captcha'] || ($user_info['is_guest'] && $modSettings['posts_require_captcha'] == -1)))
	{
		loadSource('Subs-Editor');
		$verificationOptions = array(
			'id' => 'post',
		);
		$context['require_verification'] = create_control_verification($verificationOptions, true);
		if (is_array($context['require_verification']))
			$post_errors = array_merge($post_errors, $context['require_verification']);
	}

	loadSource('Subs-Post');
	loadLanguage('Post');

	// If this isn't a new topic load the topic info that we need.
	if (!empty($topic))
	{
		$request = wesql::query('
			SELECT locked, is_sticky, id_poll, approved, id_first_msg, id_last_msg, id_member_started, id_board
			FROM {db_prefix}topics
			WHERE id_topic = {int:current_topic}
			LIMIT 1',
			array(
				'current_topic' => $topic,
			)
		);
		$topic_info = wesql::fetch_assoc($request);
		wesql::free_result($request);

		// Though the topic should be there, it might have vanished.
		if (!is_array($topic_info))
			fatal_lang_error('topic_doesnt_exist');

		// Did this topic suddenly move? Just checking...
		if ($topic_info['id_board'] != $board)
			fatal_lang_error('not_a_topic');
	}

	// Replying to a topic?
	if (!empty($topic) && !isset($_REQUEST['msg']))
	{
		// Don't allow a post if it's locked.
		if ($topic_info['locked'] != 0 && !allowedTo('moderate_board'))
			fatal_lang_error('topic_locked', false);

		// Sorry, multiple polls aren't allowed... yet.  You should stop giving me ideas :P.
		if (isset($_REQUEST['poll']) && $topic_info['id_poll'] > 0)
			unset($_REQUEST['poll']);

		// Do the permissions and approval stuff...
		$becomesApproved = true;
		if ($topic_info['id_member_started'] != $user_info['id'])
		{
			if ($modSettings['postmod_active'] && allowedTo('post_unapproved_replies_any') && !allowedTo('post_reply_any'))
				$becomesApproved = false;
			else
				isAllowedTo('post_reply_any');
		}
		elseif (!allowedTo('post_reply_any'))
		{
			if ($modSettings['postmod_active'] && allowedTo('post_unapproved_replies_own') && !allowedTo('post_reply_own'))
				$becomesApproved = false;
			else
				isAllowedTo('post_reply_own');
		}

		if (isset($_POST['lock']))
		{
			// Nothing is changed to the lock.
			if ((empty($topic_info['locked']) && empty($_POST['lock'])) || (!empty($_POST['lock']) && !empty($topic_info['locked'])))
				unset($_POST['lock']);
			// You're have no permission to lock this topic.
			elseif (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && $user_info['id'] != $topic_info['id_member_started']))
				unset($_POST['lock']);
			// You are allowed to (un)lock your own topic only.
			elseif (!allowedTo('lock_any'))
			{
				// You cannot override a moderator lock.
				if ($topic_info['locked'] == 1)
					unset($_POST['lock']);
				else
					$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
			}
			// Hail mighty moderator, (un)lock this topic immediately.
			else
				$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;
		}

		// So you wanna (un)sticky this...let's see.
		if (isset($_POST['sticky']) && (empty($modSettings['enableStickyTopics']) || $_POST['sticky'] == $topic_info['is_sticky'] || !allowedTo('make_sticky')))
			unset($_POST['sticky']);

		if (isset($_REQUEST['draft']))
		{
			$draft = saveDraft(false, $topic);
			if (!empty($draft) && !in_array('session_timeout', $post_errors))
				redirectexit('topic=' . $topic . '.msg' . $topic_info['id_last_msg'] . ';draftsaved#msg' . $topic_info['id_last_msg']);
		}

		// If the number of replies has changed, if the setting is enabled, go back to Post() - which handles the error.
		if (empty($options['no_new_reply_warning']) && isset($_POST['last_msg']) && $topic_info['id_last_msg'] > $_POST['last_msg'])
		{
			$_REQUEST['preview'] = true;
			loadSource('Post');
			return Post();
		}

		$posterIsGuest = $user_info['is_guest'];
	}
	// Posting a new topic.
	elseif (empty($topic))
	{
		// Now don't be silly, new topics will get their own id_msg soon enough.
		unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);

		// Do like, the permissions, for safety and stuff...
		$becomesApproved = true;
		if ($modSettings['postmod_active'] && !allowedTo('post_new') && allowedTo('post_unapproved_topics'))
			$becomesApproved = false;
		else
			isAllowedTo('post_new');

		if (isset($_POST['lock']))
		{
			// New topics are by default not locked.
			if (empty($_POST['lock']))
				unset($_POST['lock']);
			// Besides, you need permission.
			elseif (!allowedTo(array('lock_any', 'lock_own')))
				unset($_POST['lock']);
			// A moderator-lock (1) can override a user-lock (2).
			else
				$_POST['lock'] = allowedTo('lock_any') ? 1 : 2;
		}

		if (isset($_POST['sticky']) && (empty($modSettings['enableStickyTopics']) || empty($_POST['sticky']) || !allowedTo('make_sticky')))
			unset($_POST['sticky']);

		$posterIsGuest = $user_info['is_guest'];

		// Are we saving a draft? If so, hand over control to the draft code -- except, in the case of a session failure
		if (isset($_REQUEST['draft']))
		{
			$draft = saveDraft(false, false); // technically, it's 0 but there's something semantically feel-good about saying false here, that we don't have a 'context'/topic.
			if (!empty($draft) && !in_array('session_timeout', $post_errors))
				redirectexit('board=' . $board . '.0;draftsaved');
		}
	}
	// Modifying an existing message?
	elseif (isset($_REQUEST['msg']) && !empty($topic))
	{
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		$request = wesql::query('
			SELECT id_member, poster_name, poster_email, poster_time, approved
			FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}
			LIMIT 1',
			array(
				'id_msg' => $_REQUEST['msg'],
			)
		);
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('cant_find_messages', false);
		$row = wesql::fetch_assoc($request);
		wesql::free_result($request);

		if (!empty($topic_info['locked']) && !allowedTo('moderate_board'))
			fatal_lang_error('topic_locked', false);

		if (isset($_POST['lock']))
		{
			// Nothing changes to the lock status.
			if ((empty($_POST['lock']) && empty($topic_info['locked'])) || (!empty($_POST['lock']) && !empty($topic_info['locked'])))
				unset($_POST['lock']);
			// You're simply not allowed to (un)lock this.
			elseif (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && $user_info['id'] != $topic_info['id_member_started']))
				unset($_POST['lock']);
			// You're only allowed to lock your own topics.
			elseif (!allowedTo('lock_any'))
			{
				// You're not allowed to break a moderator's lock.
				if ($topic_info['locked'] == 1)
					unset($_POST['lock']);
				// Lock it with a soft lock or unlock it.
				else
					$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
			}
			// You must be the moderator.
			else
				$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;
		}

		// Change the sticky status of this topic?
		if (isset($_POST['sticky']) && (!allowedTo('make_sticky') || $_POST['sticky'] == $topic_info['is_sticky']))
			unset($_POST['sticky']);

		if ($row['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
		{
			if ((!$modSettings['postmod_active'] || $row['approved']) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
			elseif ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('modify_own'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_own');
		}
		elseif ($topic_info['id_member_started'] == $user_info['id'] && !allowedTo('modify_any'))
		{
			isAllowedTo('modify_replies');

			// If you're modifying a reply, I say it better be logged...
			$moderationAction = true;
		}
		else
		{
			isAllowedTo('modify_any');

			// Log it, assuming you're not modifying your own post.
			if ($row['id_member'] != $user_info['id'])
				$moderationAction = true;
		}

		$posterIsGuest = empty($row['id_member']);

		// Can they approve it?
		$can_approve = allowedTo('approve_posts');
		$becomesApproved = $modSettings['postmod_active'] ? ($can_approve && !$row['approved'] ? (!empty($_REQUEST['approve']) ? 1 : 0) : $row['approved']) : 1;
		$approve_has_changed = $row['approved'] != $becomesApproved;

		if (!allowedTo('moderate_forum') || !$posterIsGuest)
		{
			$_POST['guestname'] = $row['poster_name'];
			$_POST['email'] = $row['poster_email'];
		}
	}

	// If the poster is a guest evaluate the legality of name and email.
	if ($posterIsGuest)
	{
		$_POST['guestname'] = !isset($_POST['guestname']) ? '' : trim($_POST['guestname']);
		$_POST['email'] = !isset($_POST['email']) ? '' : trim($_POST['email']);

		if ($_POST['guestname'] == '' || $_POST['guestname'] == '_')
			$post_errors[] = 'no_name';
		if (westr::strlen($_POST['guestname']) > 25)
			$post_errors[] = 'long_name';

		if (empty($modSettings['guest_post_no_email']))
		{
			// Only check if they changed it!
			if (!isset($row) || $row['poster_email'] != $_POST['email'])
			{
				if (!allowedTo('moderate_forum') && (!isset($_POST['email']) || $_POST['email'] == ''))
					$post_errors[] = 'no_email';
				if (!allowedTo('moderate_forum') && preg_match('~^[0-9A-Za-z=_+\-/][0-9A-Za-z=_\'+\-/\.]*@[\w\-]+(\.[\w\-]+)*(\.[\w]{2,6})$~', $_POST['email']) == 0)
					$post_errors[] = 'bad_email';
			}

			// Now make sure this email address is not banned from posting.
			isBannedEmail($_POST['email'], 'cannot_post', sprintf($txt['you_are_post_banned'], $txt['guest_title']));
		}

		// In case they are making multiple posts this visit, help them along by storing their name.
		if (empty($post_errors))
		{
			$_SESSION['guest_name'] = $_POST['guestname'];
			$_SESSION['guest_email'] = $_POST['email'];
		}
	}

	// Check the subject and message.
	if (!isset($_POST['subject']) || westr::htmltrim(westr::htmlspecialchars($_POST['subject'])) === '')
		$post_errors[] = 'no_subject';
	if (!isset($_POST['message']) || westr::htmltrim(westr::htmlspecialchars($_POST['message']), ENT_QUOTES) === '')
		$post_errors[] = 'no_message';
	elseif (!empty($modSettings['max_messageLength']) && westr::strlen($_POST['message']) > $modSettings['max_messageLength'])
		$post_errors[] = 'long_message';
	else
	{
		// Prepare the message a bit for some additional testing.
		$_POST['message'] = westr::htmlspecialchars($_POST['message'], ENT_QUOTES);

		// Preparse code. (Zef)
		if ($user_info['is_guest'])
			$user_info['name'] = $_POST['guestname'];
		wedgeEditor::preparsecode($_POST['message']);

		// Let's see if there's still some content left without the tags.
		if (westr::htmltrim(strip_tags(parse_bbc($_POST['message'], false), '<img>')) === '' && (!allowedTo('admin_forum') || strpos($_POST['message'], '[html]') === false))
			$post_errors[] = 'no_message';
	}
	if (isset($_POST['calendar']) && !isset($_REQUEST['deleteevent']) && westr::htmltrim($_POST['evtitle']) === '')
		$post_errors[] = 'no_event';

	// Validate the poll...
	if (isset($_REQUEST['poll']) && $modSettings['pollMode'] == '1')
	{
		if (!empty($topic) && !isset($_REQUEST['msg']))
			fatal_lang_error('no_access', false);

		// This is a new topic... so it's a new poll.
		if (empty($topic))
			isAllowedTo('poll_post');
		// Can you add to your own topics?
		elseif ($user_info['id'] == $topic_info['id_member_started'] && !allowedTo('poll_add_any'))
			isAllowedTo('poll_add_own');
		// Can you add polls to any topic, then?
		else
			isAllowedTo('poll_add_any');

		if (!isset($_POST['question']) || trim($_POST['question']) == '')
			$post_errors[] = 'no_question';

		$_POST['options'] = empty($_POST['options']) ? array() : htmltrim__recursive($_POST['options']);

		// Get rid of empty ones.
		foreach ($_POST['options'] as $k => $option)
			if ($option == '')
				unset($_POST['options'][$k], $_POST['options'][$k]);

		// What are you going to vote between with one choice?!?
		if (count($_POST['options']) < 2)
			$post_errors[] = 'poll_few';
	}

	if ($posterIsGuest)
	{
		// If user is a guest, make sure the chosen name isn't taken.
		loadSource('Subs-Members');
		if (isReservedName($_POST['guestname'], 0, true, false) && (!isset($row['poster_name']) || $_POST['guestname'] != $row['poster_name']))
			$post_errors[] = 'bad_name';
	}
	// If the user isn't a guest, get his or her name and email.
	elseif (!isset($_REQUEST['msg']))
	{
		$_POST['guestname'] = $user_info['username'];
		$_POST['email'] = $user_info['email'];
	}

	// Any mistakes?
	if (!empty($post_errors))
	{
		loadLanguage('Errors');
		// Previewing.
		$_REQUEST['preview'] = true;

		$context['post_error'] = array('messages' => array());
		foreach ($post_errors as $post_error)
		{
			$context['post_error'][$post_error] = true;
			if ($post_error == 'long_message')
				$txt['error_' . $post_error] = sprintf($txt['error_' . $post_error], $modSettings['max_messageLength']);

			$context['post_error']['messages'][] = $txt['error_' . $post_error];
		}

		loadSource('Post');
		return Post();
	}

	// Make sure the user isn't spamming the board.
	if (!isset($_REQUEST['msg']))
		spamProtection('post');

	// At about this point, we're posting and that's that.
	ignore_user_abort(true);
	@set_time_limit(300);

	// Add special html entities to the subject, name, and email.
	$_POST['subject'] = strtr(westr::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
	$_POST['guestname'] = htmlspecialchars($_POST['guestname']);
	$_POST['email'] = htmlspecialchars($_POST['email']);

	// At this point, we want to make sure the subject isn't too long.
	if (westr::strlen($_POST['subject']) > 100)
		$_POST['subject'] = westr::substr($_POST['subject'], 0, 100);

	// Make the poll...
	if (isset($_REQUEST['poll']))
	{
		// Make sure that the user has not entered a ridiculous number of options..
		if (empty($_POST['poll_max_votes']) || $_POST['poll_max_votes'] <= 0)
			$_POST['poll_max_votes'] = 1;
		elseif ($_POST['poll_max_votes'] > count($_POST['options']))
			$_POST['poll_max_votes'] = count($_POST['options']);
		else
			$_POST['poll_max_votes'] = (int) $_POST['poll_max_votes'];

		$_POST['poll_expire'] = (int) $_POST['poll_expire'];
		$_POST['poll_expire'] = $_POST['poll_expire'] > 9999 ? 9999 : ($_POST['poll_expire'] < 0 ? 0 : $_POST['poll_expire']);

		// Just set it to zero if it's not there..
		if (!isset($_POST['poll_hide']))
			$_POST['poll_hide'] = 0;
		else
			$_POST['poll_hide'] = (int) $_POST['poll_hide'];
		$_POST['poll_change_vote'] = isset($_POST['poll_change_vote']) ? 1 : 0;

		$_POST['poll_guest_vote'] = isset($_POST['poll_guest_vote']) ? 1 : 0;
		// Make sure guests are actually allowed to vote generally.
		if ($_POST['poll_guest_vote'])
		{
			loadSource('Subs-Members');
			$allowedVoteGroups = groupsAllowedTo('poll_vote', $board);
			if (!in_array(-1, $allowedVoteGroups['allowed']))
				$_POST['poll_guest_vote'] = 0;
		}

		// If the user tries to set the poll too far in advance, don't let them.
		if (!empty($_POST['poll_expire']) && $_POST['poll_expire'] < 1)
			fatal_lang_error('poll_range_error', false);
		// Don't allow them to select option 2 for hidden results if it's not time limited.
		elseif (empty($_POST['poll_expire']) && $_POST['poll_hide'] == 2)
			$_POST['poll_hide'] = 1;

		// Clean up the question and answers.
		$_POST['question'] = htmlspecialchars($_POST['question']);
		$_POST['question'] = westr::truncate($_POST['question'], 255);
		$_POST['question'] = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $_POST['question']);
		$_POST['options'] = htmlspecialchars__recursive($_POST['options']);
	}

	// Check if they are trying to delete any current attachments....
	if (isset($_REQUEST['msg'], $_POST['attach_del']) && (allowedTo('post_attachment') || ($modSettings['postmod_active'] && allowedTo('post_unapproved_attachments'))))
	{
		$del_temp = array();
		foreach ($_POST['attach_del'] as $i => $dummy)
			$del_temp[$i] = (int) $dummy;

		loadSource('ManageAttachments');
		$attachmentQuery = array(
			'attachment_type' => 0,
			'id_msg' => (int) $_REQUEST['msg'],
			'not_id_attach' => $del_temp,
		);
		removeAttachments($attachmentQuery);
	}

	// ...or attach a new file...
	if (isset($_FILES['attachment']['name']) || (!empty($_SESSION['temp_attachments']) && empty($_POST['from_qr'])))
	{
		// Verify they can post them!
		if (!$modSettings['postmod_active'] || !allowedTo('post_unapproved_attachments'))
			isAllowedTo('post_attachment');

		// Make sure we're uploading to the right place.
		if (!empty($modSettings['currentAttachmentUploadDir']))
		{
			if (!is_array($modSettings['attachmentUploadDir']))
				$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

			// The current directory, of course!
			$current_attach_dir = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
		}
		else
			$current_attach_dir = $modSettings['attachmentUploadDir'];

		// If this isn't a new post, check the current attachments.
		if (isset($_REQUEST['msg']))
		{
			$request = wesql::query('
				SELECT COUNT(*), SUM(size)
				FROM {db_prefix}attachments
				WHERE id_msg = {int:id_msg}
					AND attachment_type = {int:attachment_type}',
				array(
					'id_msg' => (int) $_REQUEST['msg'],
					'attachment_type' => 0,
				)
			);
			list ($quantity, $total_size) = wesql::fetch_row($request);
			wesql::free_result($request);
		}
		else
		{
			$quantity = 0;
			$total_size = 0;
		}

		if (!empty($_SESSION['temp_attachments']))
			foreach ($_SESSION['temp_attachments'] as $attachID => $name)
			{
				if (preg_match('~^post_tmp_' . $user_info['id'] . '_\d+$~', $attachID) == 0)
					continue;

				if (!empty($_POST['attach_del']) && !in_array($attachID, $_POST['attach_del']))
				{
					unset($_SESSION['temp_attachments'][$attachID]);
					@unlink($current_attach_dir . '/' . $attachID);
					continue;
				}

				$_FILES['attachment']['tmp_name'][] = $attachID;
				$_FILES['attachment']['name'][] = $name;
				$_FILES['attachment']['size'][] = filesize($current_attach_dir . '/' . $attachID);
				list ($_FILES['attachment']['width'][], $_FILES['attachment']['height'][]) = @getimagesize($current_attach_dir . '/' . $attachID);

				unset($_SESSION['temp_attachments'][$attachID]);
			}

		if (!isset($_FILES['attachment']['name']))
			$_FILES['attachment']['tmp_name'] = array();

		$attachIDs = array();
		foreach ($_FILES['attachment']['tmp_name'] as $n => $dummy)
		{
			if ($_FILES['attachment']['name'][$n] == '')
				continue;

			// Have we reached the maximum number of files we are allowed?
			$quantity++;
			if (!empty($modSettings['attachmentNumPerPostLimit']) && $quantity > $modSettings['attachmentNumPerPostLimit'])
			{
				checkSubmitOnce('free');
				fatal_lang_error('attachments_limit_per_post', false, array($modSettings['attachmentNumPerPostLimit']));
			}

			// Check the total upload size for this post...
			$total_size += $_FILES['attachment']['size'][$n];
			if (!empty($modSettings['attachmentPostLimit']) && $total_size > $modSettings['attachmentPostLimit'] * 1024)
			{
				checkSubmitOnce('free');
				fatal_lang_error('file_too_big', false, array($modSettings['attachmentPostLimit']));
			}

			$attachmentOptions = array(
				'post' => isset($_REQUEST['msg']) ? $_REQUEST['msg'] : 0,
				'poster' => $user_info['id'],
				'name' => $_FILES['attachment']['name'][$n],
				'tmp_name' => $_FILES['attachment']['tmp_name'][$n],
				'size' => $_FILES['attachment']['size'][$n],
				'approved' => !$modSettings['postmod_active'] || allowedTo('post_attachment'),
			);

			if (createAttachment($attachmentOptions))
			{
				$attachIDs[] = $attachmentOptions['id'];
				if (!empty($attachmentOptions['thumb']))
					$attachIDs[] = $attachmentOptions['thumb'];
			}
			else
			{
				if (in_array('could_not_upload', $attachmentOptions['errors']))
				{
					checkSubmitOnce('free');
					fatal_lang_error('attach_timeout', 'critical');
				}
				if (in_array('too_large', $attachmentOptions['errors']))
				{
					checkSubmitOnce('free');
					fatal_lang_error('file_too_big', false, array($modSettings['attachmentSizeLimit']));
				}
				if (in_array('bad_extension', $attachmentOptions['errors']))
				{
					checkSubmitOnce('free');
					fatal_error($attachmentOptions['name'] . '.<br />' . $txt['cant_upload_type'] . ' ' . $modSettings['attachmentExtensions'] . '.', false);
				}
				if (in_array('directory_full', $attachmentOptions['errors']))
				{
					checkSubmitOnce('free');
					fatal_lang_error('ran_out_of_space', 'critical');
				}
				if (in_array('bad_filename', $attachmentOptions['errors']))
				{
					checkSubmitOnce('free');
					fatal_error(basename($attachmentOptions['name']) . '.<br />' . $txt['restricted_filename'] . '.', 'critical');
				}
				if (in_array('taken_filename', $attachmentOptions['errors']))
				{
					checkSubmitOnce('free');
					fatal_lang_error('filename_exists');
				}
				if (in_array('bad_attachment', $attachmentOptions['errors']))
				{
					checkSubmitOnce('free');
					fatal_lang_error('bad_attachment');
				}
			}
		}
	}

	// Make the poll...
	if (isset($_REQUEST['poll']))
	{
		// Create the poll.
		wesql::insert('',
			'{db_prefix}polls',
			array(
				'question' => 'string-255', 'hide_results' => 'int', 'max_votes' => 'int', 'expire_time' => 'int', 'id_member' => 'int',
				'poster_name' => 'string-255', 'change_vote' => 'int', 'guest_vote' => 'int'
			),
			array(
				$_POST['question'], $_POST['poll_hide'], $_POST['poll_max_votes'], (empty($_POST['poll_expire']) ? 0 : time() + $_POST['poll_expire'] * 3600 * 24), $user_info['id'],
				$_POST['guestname'], $_POST['poll_change_vote'], $_POST['poll_guest_vote'],
			),
			array('id_poll')
		);
		$id_poll = wesql::insert_id();

		// Create each answer choice.
		$i = 0;
		$pollOptions = array();
		foreach ($_POST['options'] as $option)
		{
			$pollOptions[] = array($id_poll, $i, $option);
			$i++;
		}

		wesql::insert('insert',
			'{db_prefix}poll_choices',
			array('id_poll' => 'int', 'id_choice' => 'int', 'label' => 'string-255'),
			$pollOptions,
			array('id_poll', 'id_choice')
		);
	}
	else
		$id_poll = 0;

	// Creating a new topic?
	$newTopic = empty($_REQUEST['msg']) && empty($topic);

	$_POST['icon'] = !empty($attachIDs) && $_POST['icon'] == 'xx' ? 'clip' : $_POST['icon'];

	// Collect all parameters for the creation or modification of a post.
	$msgOptions = array(
		'id' => empty($_REQUEST['msg']) ? 0 : (int) $_REQUEST['msg'],
		'subject' => $_POST['subject'],
		'body' => $_POST['message'],
		'icon' => preg_replace('~[\./\\\\*:"\'<>]~', '', $_POST['icon']),
		'smileys_enabled' => !isset($_POST['ns']),
		'attachments' => empty($attachIDs) ? array() : $attachIDs,
		'approved' => $becomesApproved,
	);
	$topicOptions = array(
		'id' => empty($topic) ? 0 : $topic,
		'board' => $board,
		'poll' => isset($_REQUEST['poll']) ? $id_poll : null,
		'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
		'sticky_mode' => isset($_POST['sticky']) && !empty($modSettings['enableStickyTopics']) ? (int) $_POST['sticky'] : null,
		'mark_as_read' => true,
		'is_approved' => !$modSettings['postmod_active'] || empty($topic) || !empty($board_info['cur_topic_approved']),
	);
	$posterOptions = array(
		'id' => $user_info['id'],
		'name' => $_POST['guestname'],
		'email' => $_POST['email'],
		'update_post_count' => !$user_info['is_guest'] && !isset($_REQUEST['msg']) && $board_info['posts_count'],
	);

	// This is an already existing message. Edit it.
	if (!empty($_REQUEST['msg']))
	{
		// Have admins allowed people to hide their screwups?
		if (time() - $row['poster_time'] > $modSettings['edit_wait_time'] || $user_info['id'] != $row['id_member'])
		{
			$msgOptions['modify_time'] = time();
			$msgOptions['modify_name'] = $user_info['name'];
		}

		// This will save some time...
		if (empty($approve_has_changed))
			unset($msgOptions['approved']);

		modifyPost($msgOptions, $topicOptions, $posterOptions);
	}
	// This is a new topic or an already existing one. Save it.
	else
	{
		createPost($msgOptions, $topicOptions, $posterOptions);

		if (isset($topicOptions['id']))
			$topic = $topicOptions['id'];
	}

	// Editing or posting an event?
	if (isset($_POST['calendar']) && (!isset($_REQUEST['eventid']) || $_REQUEST['eventid'] == -1))
	{
		loadSource('Subs-Calendar');

		// Make sure they can link an event to this post.
		canLinkEvent();

		// Insert the event.
		$eventOptions = array(
			'board' => $board,
			'topic' => $topic,
			'title' => $_POST['evtitle'],
			'member' => $user_info['id'],
			'start_date' => sprintf('%04d-%02d-%02d', $_POST['year'], $_POST['month'], $_POST['day']),
			'span' => isset($_POST['span']) && $_POST['span'] > 0 ? min((int) $modSettings['cal_maxspan'], (int) $_POST['span'] - 1) : 0,
		);
		insertEvent($eventOptions);
	}
	elseif (isset($_POST['calendar']))
	{
		$_REQUEST['eventid'] = (int) $_REQUEST['eventid'];

		// Validate the post...
		loadSource('Subs-Calendar');
		validateEventPost();

		// If you're not allowed to edit any events, you have to be the poster.
		if (!allowedTo('calendar_edit_any'))
		{
			// Get the event's poster.
			$request = wesql::query('
				SELECT id_member
				FROM {db_prefix}calendar
				WHERE id_event = {int:id_event}',
				array(
					'id_event' => $_REQUEST['eventid'],
				)
			);
			$row2 = wesql::fetch_assoc($request);
			wesql::free_result($request);

			// Silly hacker, Trix are for kids. ...probably trademarked somewhere, this is FAIR USE! (parody...)
			isAllowedTo('calendar_edit_' . ($row2['id_member'] == $user_info['id'] ? 'own' : 'any'));
		}

		// Delete it?
		if (isset($_REQUEST['deleteevent']))
			wesql::query('
				DELETE FROM {db_prefix}calendar
				WHERE id_event = {int:id_event}',
				array(
					'id_event' => $_REQUEST['eventid'],
				)
			);
		// ... or just update it?
		else
		{
			$span = !empty($modSettings['cal_allowspan']) && !empty($_REQUEST['span']) ? min((int) $modSettings['cal_maxspan'], (int) $_REQUEST['span'] - 1) : 0;
			$start_time = mktime(0, 0, 0, (int) $_REQUEST['month'], (int) $_REQUEST['day'], (int) $_REQUEST['year']);

			wesql::query('
				UPDATE {db_prefix}calendar
				SET end_date = {date:end_date},
					start_date = {date:start_date},
					title = {string:title}
				WHERE id_event = {int:id_event}',
				array(
					'end_date' => strftime('%Y-%m-%d', $start_time + $span * 86400),
					'start_date' => strftime('%Y-%m-%d', $start_time),
					'id_event' => $_REQUEST['eventid'],
					'title' => westr::htmlspecialchars($_REQUEST['evtitle'], ENT_QUOTES),
				)
			);
		}
		updateSettings(array(
			'calendar_updated' => time(),
		));
	}

	// Marking read should be done even for editing messages....
	// Mark all the parents read, since you just posted and they will be unread.
	if (!$user_info['is_guest'] && !empty($board_info['parent_boards']))
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
	}

	// Turn notification on or off.  (note this just blows smoke if it's already on or off.)
	if (!empty($_POST['notify']) && allowedTo('mark_any_notify'))
	{
		wesql::insert('ignore',
			'{db_prefix}log_notify',
			array('id_member' => 'int', 'id_topic' => 'int', 'id_board' => 'int'),
			array($user_info['id'], $topic, 0),
			array('id_member', 'id_topic', 'id_board')
		);
	}
	elseif (!$newTopic)
		wesql::query('
			DELETE FROM {db_prefix}log_notify
			WHERE id_member = {int:current_member}
				AND id_topic = {int:current_topic}',
			array(
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
			)
		);

	// Log an act of moderation - modifying.
	if (!empty($moderationAction))
		logAction('modify', array('topic' => $topic, 'message' => (int) $_REQUEST['msg'], 'member' => $row['id_member'], 'board' => $board));

	if (isset($_POST['lock']) && $_POST['lock'] != 2)
		logAction('lock', array('topic' => $topicOptions['id'], 'board' => $topicOptions['board']));

	if (isset($_POST['sticky']) && !empty($modSettings['enableStickyTopics']))
		logAction('sticky', array('topic' => $topicOptions['id'], 'board' => $topicOptions['board']));

	// Notify any members who have notification turned on for this topic - only do this if it's going to be approved(!)
	if ($becomesApproved)
	{
		if ($newTopic)
		{
			$notifyData = array(
				'body' => $_POST['message'],
				'subject' => $_POST['subject'],
				'name' => $user_info['name'],
				'poster' => $user_info['id'],
				'msg' => $msgOptions['id'],
				'board' => $board,
				'topic' => $topic,
			);
			notifyMembersBoard($notifyData);
		}
		elseif (empty($_REQUEST['msg']))
		{
			// Only send it to everyone if the topic is approved, otherwise just to the topic starter if they want it.
			if ($topic_info['approved'])
				sendNotifications($topic, 'reply');
			else
				sendNotifications($topic, 'reply', array(), $topic_info['id_member_started']);
		}
	}

	// Um, did this come from a draft? If so, bye bye.
	if (!empty($_POST['draft_id']) && !empty($user_info['id']))
		wesql::query('
			DELETE FROM {db_prefix}drafts
			WHERE id_draft = {int:draft}
				AND id_member = {int:member}
			LIMIT 1',
			array(
				'draft' => (int) $_POST['draft_id'],
				'member' => $user_info['id'],
			)
		);

	// Returning to the topic?
	if (!empty($_REQUEST['goback']))
	{
		// Mark the board as read.... because it might get confusing otherwise.
		wesql::query('
			UPDATE {db_prefix}log_boards
			SET id_msg = {int:maxMsgID}
			WHERE id_member = {int:current_member}
				AND id_board = {int:current_board}',
			array(
				'current_board' => $board,
				'current_member' => $user_info['id'],
				'maxMsgID' => $modSettings['maxMsgID'],
			)
		);
	}

	if ($board_info['num_topics'] == 0)
		cache_put_data('board-' . $board, null, 120);

	if (!empty($_POST['announce_topic']))
		redirectexit('action=announce;sa=selectgroup;topic=' . $topic . (!empty($_POST['move']) && allowedTo('move_any') ? ';move' : '') . (empty($_REQUEST['goback']) ? '' : ';goback'));

	if (!empty($_POST['move']) && allowedTo('move_any'))
		redirectexit('action=movetopic;topic=' . $topic . '.0' . (empty($_REQUEST['goback']) ? '' : ';goback'));

	// Return to post if the mod is on.
	if (isset($_REQUEST['msg']) && !empty($_REQUEST['goback']))
		redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg'], $context['browser']['is_ie']);
	elseif (!empty($_REQUEST['goback']))
		redirectexit('topic=' . $topic . '.new#new', $context['browser']['is_ie']);
	// Dut-dut-duh-duh-DUH-duh-dut-duh-duh!  *dances to the Final Fantasy Fanfare...*
	else
		redirectexit('board=' . $board . '.0');
}

/**
 * Handle notifications sent when a user has requested them on a board (i.e. all new posts in that board)
 *
 * - Check whether we have received a single topic or array of topics; if a single topic, convert it to an array.
 * - Step through the topic data provided, censoring the subject and body for each new item to deal with, then parse the messages (without smileys), replacing certain tags with line breaks, stripping the rest, and removing HTML special characters.
 * - Divide the messages by board.
 * - Add the messages to the digest table, so we have a list of what to send out later.
 * - Find the members who have notifications on these boards.
 * - For each email we're going to send: check if we have loaded the language - if not, load the right language file, then the email templates for that language. Then add this recipient to the list for their language.
 * - Then send out emails by language, having replaced the text into the email template, low priority.
 * - Update the list of notifications to say we have sent the relevant notifications out to the relevant people (and tracking the ids so we don't send them any more than they need)
 *
 * @param array &$topicData Notionally an array of topics (indexed), with each topic entry being an array of 'board' (board id), 'topic' (topic id), 'msg' (message id), 'subject', 'body', 'poster' (poster's user id), 'name' (poster's name). Sometimes only a single topic will be passed, which will be just the subarray described here.
 */
function notifyMembersBoard(&$topicData)
{
	global $txt, $scripturl, $language, $user_info;
	global $modSettings, $board, $context;

	loadSource('Subs-Post');

	// Do we have one or lots of topics?
	if (isset($topicData['body']))
		$topicData = array($topicData);

	// Find out what boards we have... and clear out any rubbish!
	$boards = array();
	foreach ($topicData as $key => $topic)
	{
		if (!empty($topic['board']))
			$boards[$topic['board']][] = $key;
		else
		{
			unset($topic[$key]);
			continue;
		}

		// Censor the subject and body...
		censorText($topicData[$key]['subject']);
		censorText($topicData[$key]['body']);

		$topicData[$key]['subject'] = un_htmlspecialchars($topicData[$key]['subject']);
		$topicData[$key]['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($topicData[$key]['body'], false), array('<br />' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));
	}

	// Just the board numbers.
	$board_index = array_unique(array_keys($boards));

	if (empty($board_index))
		return;

	// Yea, we need to add this to the digest queue.
	$digest_insert = array();
	foreach ($topicData as $id => $data)
		$digest_insert[] = array($data['topic'], $data['msg'], 'topic', $user_info['id']);
	wesql::insert('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Find the members with notification on for these boards.
	$members = wesql::query('
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_send_body, mem.lngfile,
			ln.sent, ln.id_board, mem.id_group, mem.additional_groups, b.member_groups,
			mem.id_post_group
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
		WHERE ln.id_board IN ({array_int:board_list})
			AND mem.id_member != {int:current_member}
			AND mem.is_activated = {int:is_activated}
			AND mem.notify_types != {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
		ORDER BY mem.lngfile',
		array(
			'current_member' => $user_info['id'],
			'board_list' => $board_index,
			'is_activated' => 1,
			'notify_types' => 4,
			'notify_regularity' => 2,
		)
	);
	while ($rowmember = wesql::fetch_assoc($members))
	{
		if ($rowmember['id_group'] != 1)
		{
			$allowed = explode(',', $rowmember['member_groups']);
			$rowmember['additional_groups'] = explode(',', $rowmember['additional_groups']);
			$rowmember['additional_groups'][] = $rowmember['id_group'];
			$rowmember['additional_groups'][] = $rowmember['id_post_group'];

			if (count(array_intersect($allowed, $rowmember['additional_groups'])) == 0)
				continue;
		}

		$langloaded = loadLanguage('EmailTemplates', empty($rowmember['lngfile']) || empty($modSettings['userLanguage']) ? $language : $rowmember['lngfile'], false);

		// Now loop through all the notifications to send for this board.
		if (empty($boards[$rowmember['id_board']]))
			continue;

		$sentOnceAlready = 0;
		foreach ($boards[$rowmember['id_board']] as $key)
		{
			// Don't notify the guy who started the topic!
			//!!! In this case actually send them a "it's approved hooray" email
			if ($topicData[$key]['poster'] == $rowmember['id_member'])
				continue;

			// Setup the string for adding the body to the message, if a user wants it.
			$send_body = empty($modSettings['disallow_sendBody']) && !empty($rowmember['notify_send_body']);

			$replacements = array(
				'TOPICSUBJECT' => $topicData[$key]['subject'],
				'TOPICLINK' => $scripturl . '?topic=' . $topicData[$key]['topic'] . '.new#new',
				'MESSAGE' => $topicData[$key]['body'],
				'UNSUBSCRIBELINK' => $scripturl . '?action=notifyboard;board=' . $topicData[$key]['board'] . '.0',
			);

			if (!$send_body)
				unset($replacements['MESSAGE']);

			// Figure out which email to send off
			$emailtype = '';

			// Send only if once is off or it's on and it hasn't been sent.
			if (!empty($rowmember['notify_regularity']) && !$sentOnceAlready && empty($rowmember['sent']))
				$emailtype = 'notify_boards_once';
			elseif (empty($rowmember['notify_regularity']))
				$emailtype = 'notify_boards';

			if (!empty($emailtype))
			{
				$emailtype .= $send_body ? '_body' : '';
				$emaildata = loadEmailTemplate($emailtype, $replacements, $langloaded);
				sendmail($rowmember['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 3);
			}

			$sentOnceAlready = 1;
		}
	}
	wesql::free_result($members);

	// Sent!
	wesql::query('
		UPDATE {db_prefix}log_notify
		SET sent = {int:is_sent}
		WHERE id_board IN ({array_int:board_list})
			AND id_member != {int:current_member}',
		array(
			'current_member' => $user_info['id'],
			'board_list' => $board_index,
			'is_sent' => 1,
		)
	);
}

?>