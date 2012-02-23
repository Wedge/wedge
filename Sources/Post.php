<?php
/**
 * Wedge
 *
 * This file handles the posting interface and redisplaying it in the event of an error.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Handles showing the post screen, loading a post to be modified (or quoted), as well as previews and display of errors and polls too.
 *
 * Requested primarily from ?action=post, however will be called from ?action=post2 internally in the event of errors or non-inline preview (e.g. from quick reply)
 *
 * - Loads the Post language file, and the editor components.
 * - If this is a reply, there shouldn't be a poll attached, so remove it.
 * - Tell robots not to index.
 * - Validate that we're posting in a board (or, that some external integration is happy for us to post outside of a board)
 * - We may be handling inline previews via XML, so prepare for that.
 * - If we don't have a topic id but just a message id, find the topic id if available (if not, assume it's a new message)
 * - Check things we can check if we have an existing topic (and grab them): whether it's locked, pinned, has notifications on it, has a poll, some message ids and the subject.
 * - If there's already a poll, disallow adding another.
 * - If this is a guest trying to post, check whether they can, and if not throw them at a log-in screen.
 * - If this is a reply, check whether the permissions allow such (own/any/replies, whether it will require approval), and details like whether it is/can be locked/pinned.
 * - Perform other checks, such as whether we can receive notifications, whether it can be announced.
 * - If the topic is locked and you do not have moderate_board, you cannot post. (Need to unlock first otherwise.)
 * - Are polls enabled and user trying to post a poll? Check permissions in that case (e.g. coming from add poll button) and if all allowed, set up the default empty choices, plus grab anything that's in $_POST (e.g. coming from Post2()) or use blank defaults.
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
 * - Instance the editor component.
 * - Get all the possible icons in this board, and prepare them ready for the template.
 * - All the relevant posts to display after the editor box (i.e. the last replies, or the last posts before your currently-editing post)
 * - Set up whether they can see the additional options including attachments (and the details of any such restrictions)
 * - Set up CAPTCHA if appropriate (and also add error if they came from quick reply without said CAPTCHA)
 * - Last bits: set up whether WYSIWYG is available, set the nonce so we can't submit twice normally, and load the template.
 *
 * @todo Why is the response prefix cached? Is it really that much effort to determine... what... 4 friggin' bytes? Would be better in $settings in that case.
 * @todo Is it possible to force something through approval if you edit the form manually?
 * @todo Censoring appears to be done out of sequence if previewing compared to parsing. Issue? Non-issue?
 */
function Post($post_errors = array())
{
	global $txt, $scripturl, $topic, $topic_info, $settings, $board, $user_info;
	global $board_info, $context, $theme, $options, $language;

	$context['form_fields'] = array(
		'text' => array('subject', 'icon', 'guestname', 'email', 'evtitle', 'question', 'topic'),
		'numeric' => array('board', 'topic', 'last_msg', 'poll_max_votes', 'poll_expire', 'poll_change_vote', 'poll_hide'),
		'checkbox' => array('ns'),
	);

	loadLanguage('Post');

	call_hook('post_form_pre');

	// Needed for the editor and message icons.
	loadSource(array('Subs-Editor', 'Class-Editor'));

	// You can't reply with a poll... hacker.
	if (isset($_REQUEST['poll']) && !empty($topic) && !isset($_REQUEST['msg']))
		unset($_REQUEST['poll']);

	$context['robot_no_index'] = true;

	// You must be posting to *some* board.
	if (empty($board) && empty($context['allow_no_board']))
		fatal_lang_error('no_board', false);

	loadSource('Subs-Post');

	if (isset($_REQUEST['xml']))
	{
		wetem::load('post');

		// Just in case of an earlier error...
		$context['preview_message'] = '';
		$context['preview_subject'] = '';
	}

	// No message is complete without a topic.
	if (empty($topic) && !empty($_REQUEST['msg']))
	{
		$request = wesql::query('
			SELECT id_topic
			FROM {db_prefix}messages
			WHERE id_msg = {int:msg}',
			array(
				'msg' => (int) $_REQUEST['msg'],
		));
		if (wesql::num_rows($request) != 1)
			unset($_REQUEST['msg'], $_POST['msg'], $_GET['msg']);
		else
			list ($topic) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	// Check if it's locked. It isn't locked if no topic is specified.
	if (!empty($topic))
	{
		$request = wesql::query('
			SELECT
				t.locked, IFNULL(ln.id_topic, 0) AS notify, t.is_pinned, t.id_poll, t.id_last_msg, mf.id_member,
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
		list ($locked, $context['notify'], $pinned, $pollID, $context['topic_last_message'], $id_member_poster, $id_first_msg, $first_subject, $lastPostTime) = wesql::fetch_row($request);
		wesql::free_result($request);

		// If this topic already has a poll, they sure can't add another.
		if (isset($_REQUEST['poll']) && $pollID > 0)
			unset($_REQUEST['poll']);

		if (empty($_REQUEST['msg']))
		{
			if ($user_info['is_guest'] && !allowedTo('post_reply_any') && (!$settings['postmod_active'] || !allowedTo('post_unapproved_replies_any')))
				is_not_guest();

			// By default the reply will be approved...
			$context['becomes_approved'] = true;
			if ($id_member_poster != $user_info['id'])
			{
				if ($settings['postmod_active'] && allowedTo('post_unapproved_replies_any') && !allowedTo('post_reply_any'))
					$context['becomes_approved'] = false;
				else
					isAllowedTo('post_reply_any');
			}
			elseif (!allowedTo('post_reply_any'))
			{
				if ($settings['postmod_active'] && allowedTo('post_unapproved_replies_own') && !allowedTo('post_reply_own'))
					$context['becomes_approved'] = false;
				else
					isAllowedTo('post_reply_own');
			}
		}
		else
			$context['becomes_approved'] = true;

		$context['can_lock'] = allowedTo('lock_any') || ($user_info['id'] == $id_member_poster && allowedTo('lock_own'));
		$context['can_pin'] = allowedTo('pin_topic');

		$context['notify'] = !empty($context['notify']);
		$context['pinned'] = isset($_REQUEST['pin']) ? !empty($_REQUEST['pin']) : $pinned;
	}
	else
	{
		$context['becomes_approved'] = true;
		if (!empty($board))
		{
			if ($settings['postmod_active'] && !allowedTo('post_new') && allowedTo('post_unapproved_topics'))
				$context['becomes_approved'] = false;
			else
				isAllowedTo('post_new');
		}

		$locked = 0;
		// !!! These won't work if you're making an event.
		$context['can_lock'] = allowedTo(array('lock_any', 'lock_own'));
		$context['can_pin'] = allowedTo('pin_topic');

		$context['notify'] = !empty($context['notify']);
		$context['pinned'] = !empty($_REQUEST['pin']);
	}

	// !!! These won't work if you're posting an event!
	$context['can_notify'] = allowedTo('mark_any_notify');
	$context['can_move'] = allowedTo('move_any');
	$context['move'] = !empty($_REQUEST['move']);
	$context['announce'] = !empty($_REQUEST['announce']);
	// You can only announce topics that will get approved...
	$context['can_announce'] = allowedTo('announce_topic') && $context['becomes_approved'];
	$context['locked'] = !empty($locked) || !empty($_REQUEST['lock']);
	$context['can_quote'] = empty($settings['disabledBBC']) || !in_array('quote', explode(',', $settings['disabledBBC']));

	// Generally don't show the approval box... (Assume we want things approved)
	$context['show_approval'] = false;

	// An array to hold all the attachments for this topic.
	$context['current_attachments'] = array();

	// Don't allow a post if it's locked and you aren't all powerful.
	if ($locked && !allowedTo('moderate_board'))
		fatal_lang_error('topic_locked', false);
	// Check the users permissions - is the user allowed to add or post a poll?
	if (isset($_REQUEST['poll']) && $settings['pollMode'] == '1')
	{
		// New topic, new poll.
		if (empty($topic))
			isAllowedTo('poll_post');
		// This is an old topic - but it is yours! Can you add to it?
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

	$context['post_error'] = array('messages' => array());

	// See if any new replies have come along.
	if (empty($_REQUEST['msg']) && !empty($topic))
	{
		if (empty($options['no_new_reply_warning']) && isset($_REQUEST['last']) && $context['topic_last_message'] > $_REQUEST['last'])
		{
			$request = wesql::query('
				SELECT COUNT(*)
				FROM {db_prefix}messages
				WHERE id_topic = {int:current_topic}
					AND id_msg > {int:last}' . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND approved = {int:is_approved}') . '
				LIMIT 1',
				array(
					'current_topic' => $topic,
					'last' => (int) $_REQUEST['last'],
					'is_approved' => 1,
				)
			);
			list ($context['new_replies']) = wesql::fetch_row($request);
			wesql::free_result($request);

			if (!empty($context['new_replies']))
			{
				if ($context['new_replies'] == 1)
					$txt['error_new_reply'] = isset($_GET['last']) ? $txt['error_new_reply_reading'] : $txt['error_new_reply'];
				else
					$txt['error_new_replies'] = sprintf(isset($_GET['last']) ? $txt['error_new_replies_reading'] : $txt['error_new_replies'], $context['new_replies']);

				// If they've come from the display page then we treat the error differently....
				if (isset($_GET['last']))
					$newRepliesError = $context['new_replies'];
				else
					$post_errors[] = $context['new_replies'] == 1 ? 'new_reply' : 'new_replies';

				$settings['topicSummaryPosts'] = $context['new_replies'] > $settings['topicSummaryPosts'] ? max($settings['topicSummaryPosts'], 5) : $settings['topicSummaryPosts'];
			}
		}
		// Check whether this is a really old post being bumped...
		if (!empty($settings['oldTopicDays']) && $lastPostTime + $settings['oldTopicDays'] * 86400 < time() && empty($pinned) && !isset($_REQUEST['subject']))
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
	if (isset($_REQUEST['message']) || !empty($post_errors))
	{
		// Validate inputs.
		if (empty($post_errors))
		{
			if (empty($_REQUEST['subject']) || westr::htmltrim($_REQUEST['subject']) === '')
				$post_errors[] = 'no_subject';
			if (empty($_REQUEST['message']) || westr::htmltrim($_REQUEST['message']) === '')
				$post_errors[] = 'no_message';
			elseif (!empty($settings['max_messageLength']) && westr::strlen($_REQUEST['message']) > $settings['max_messageLength'])
				$post_errors[] = array('long_message', $settings['max_messageLength']);

			// Are you... a guest?
			if ($user_info['is_guest'])
			{
				$_REQUEST['guestname'] = !isset($_REQUEST['guestname']) ? '' : trim($_REQUEST['guestname']);
				$_REQUEST['email'] = !isset($_REQUEST['email']) ? '' : trim($_REQUEST['email']);

				// Validate the name and email.
				if ($_REQUEST['guestname'] === '' || $_REQUEST['guestname'] === '_')
					$post_errors[] = 'no_name';
				elseif (westr::strlen($_REQUEST['guestname']) > 25)
					$post_errors[] = 'long_name';
				else
				{
					loadSource('Subs-Members');
					if (isReservedName(htmlspecialchars($_REQUEST['guestname']), 0, true, false))
						$post_errors[] = 'bad_name';
				}

				if (empty($settings['guest_post_no_email']))
				{
					if ($_REQUEST['email'] === '')
						$post_errors[] = 'no_email';
					elseif (!is_valid_email($_REQUEST['email']))
						$post_errors[] = 'bad_email';
				}
			}

			// This is self explanatory - got any questions?
			if (isset($_REQUEST['question']) && trim($_REQUEST['question']) == '')
				$post_errors[] = 'no_question';

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
		$form_subject = strtr(westr::htmlspecialchars($_REQUEST['subject']), array("\r" => '', "\n" => '', "\t" => ''));
		$form_message = westr::htmlspecialchars($_REQUEST['message'], ENT_QUOTES);

		// Make sure the subject isn't too long - taking into account special characters.
		if (westr::strlen($form_subject) > 100)
			$form_subject = westr::substr($form_subject, 0, 100);

		// Have we inadvertently trimmed off the subject of useful information?
		if (!in_array('no_subject', $post_errors) && westr::htmltrim($form_subject) === '')
			$post_errors[] = 'no_subject';

		$context['post_error'] = array('messages' => array());

		// Any errors occurred?
		if (!empty($post_errors))
		{
			loadLanguage('Errors');

			foreach ($post_errors as $error)
			{
				if (is_array($error))
				{
					$error_id = $error[0];
					// Not really used, but we'll still set that.
					$context['post_error'][$error_id] = true;
					$context['post_error']['messages'][] = sprintf($txt['error_' . $error_id], $error[1]);
				}
				else
				{
					$error_id = $error;
					$context['post_error'][$error_id] = true;
					$context['post_error']['messages'][] = $txt['error_' . $error_id];
				}

				// If it's not a minor error flag it as such.
				if (!in_array($error_id, array('new_reply', 'not_approved', 'new_replies', 'old_topic', 'need_qr_verification')))
					$context['error_type'] = 'serious';
			}
		}

		if (isset($_REQUEST['poll']))
		{
			$context['question'] = isset($_REQUEST['question']) ? westr::htmlspecialchars(trim($_REQUEST['question'])) : '';

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
			wedit::preparsecode($form_message, true);
			wedit::preparsecode($context['preview_message']);

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

		$context['icon'] = isset($_REQUEST['icon']) ? preg_replace('~[./\\\\*\':"<>]~', '', $_REQUEST['icon']) : 'xx';

		// Set the destination action for submission.
		$context['destination'] = 'post2;start=' . $_REQUEST['start'] . (isset($_REQUEST['msg']) ? ';msg=' . $_REQUEST['msg'] . ';' . $context['session_query'] : '') . (isset($_REQUEST['poll']) ? ';poll' : '');
		$context['submit_label'] = isset($_REQUEST['msg']) ? $txt['save'] : $txt['post'];

		// Previewing an edit?
		if (isset($_REQUEST['msg']) && !empty($topic))
		{
			// Get the existing message.
			$request = wesql::query('
				SELECT
					m.id_member, m.modified_time, m.smileys_enabled, m.body,
					m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
					IFNULL(a.size, -1) AS filesize, a.filename, a.id_attach,
					t.id_member_started AS id_member_poster, m.poster_time
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
			if (wesql::num_rows($request) == 0)
				fatal_lang_error('no_board', false);
			$row = wesql::fetch_assoc($request);

			$attachment_stuff = array($row);
			while ($row2 = wesql::fetch_assoc($request))
				$attachment_stuff[] = $row2;
			wesql::free_result($request);

			if ($row['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
			{
				// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
				if ($row['approved'] && !empty($settings['edit_disable_time']) && $row['poster_time'] + ($settings['edit_disable_time'] + 5) * 60 < time())
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

			if (!empty($settings['attachmentEnable']))
			{
				$request = wesql::query('
					SELECT IFNULL(size, -1) AS filesize, filename, id_attach
					FROM {db_prefix}attachments
					WHERE id_msg = {int:id_msg}
						AND attachment_type = {int:attachment_type}',
					array(
						'id_msg' => (int) $_REQUEST['msg'],
						'attachment_type' => 0,
					)
				);
				while ($row = wesql::fetch_assoc($request))
				{
					if ($row['filesize'] <= 0)
						continue;
					$context['current_attachments'][] = array(
						'name' => htmlspecialchars($row['filename']),
						'id' => $row['id_attach'],
					);
				}
				wesql::free_result($request);
			}

			// Allow moderators to change names....
			if (allowedTo('moderate_forum') && !empty($topic))
			{
				$request = wesql::query('
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
				$row = wesql::fetch_assoc($request);
				wesql::free_result($request);

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
		$request = wesql::query('
			SELECT
				m.id_member, m.modified_time, m.smileys_enabled, m.body,
				m.poster_name, m.poster_email, m.subject, m.icon, m.approved,
				IFNULL(a.size, -1) AS filesize, a.filename, a.id_attach,
				t.id_member_started AS id_member_poster, m.poster_time
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
		if (wesql::num_rows($request) == 0)
			fatal_lang_error('no_board', false);
		$row = wesql::fetch_assoc($request);

		$attachment_stuff = array($row);
		while ($row2 = wesql::fetch_assoc($request))
			$attachment_stuff[] = $row2;
		wesql::free_result($request);

		if ($row['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
		{
			// Give an extra five minutes over the disable time threshold, so they can type - assuming the post is public.
			if ($row['approved'] && !empty($settings['edit_disable_time']) && $row['poster_time'] + ($settings['edit_disable_time'] + 5) * 60 < time())
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
		$form_message = wedit::un_preparsecode($row['body']);
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
			if ($attachment['filesize'] >= 0 && !empty($settings['attachmentEnable']))
				$context['current_attachments'][] = array(
					'name' => htmlspecialchars($attachment['filename']),
					'id' => $attachment['id_attach'],
				);
		}

		// Allow moderators to change names....
		if (allowedTo('moderate_forum') && empty($row['id_member']))
		{
			$context['name'] = htmlspecialchars($row['poster_name']);
			$context['email'] = htmlspecialchars($row['poster_email']);
		}

		// Set the destinaton.
		$context['destination'] = 'post2;start=' . $_REQUEST['start'] . ';msg=' . $_REQUEST['msg'] . ';' . $context['session_query'] . (isset($_REQUEST['poll']) ? ';poll' : '');
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
			$request = wesql::query('
				SELECT m.subject, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
				WHERE m.id_msg = {int:id_msg}' . (!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
					AND m.approved = {int:is_approved}') . '
				LIMIT 1',
				array(
					'id_msg' => (int) $_REQUEST['quote'],
					'is_approved' => 1,
				)
			);
			if (wesql::num_rows($request) == 0)
				fatal_lang_error('quoted_post_deleted', false);
			list ($form_subject, $mname, $mdate, $form_message) = wesql::fetch_row($request);
			wesql::free_result($request);

			// Add 'Re: ' to the front of the quoted subject.
			if (trim($context['response_prefix']) != '' && westr::strpos($form_subject, trim($context['response_prefix'])) !== 0)
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
						$parts[$i] = preg_replace('~\[html\](.+?)\[/html\]~ise', '\'[html]\' . preg_replace(\'~<br\s*/?\>~i\', \'&lt;br&gt;<br>\', \'$1\') . \'[/html]\'', $parts[$i]);
				}
				$form_message = implode('', $parts);
			}

			$form_message = preg_replace('~<br\s*/?\>~i', "\n", $form_message);

			// Remove any nested quotes, if necessary.
			if (!empty($settings['removeNestedQuotes']))
				$form_message = preg_replace(array('~\n?\[quote.*?].+?\[/quote]\n?~is', '~^\n~', '~\[/quote]~'), '', $form_message);

			// Add a quote string on the front and end.
			$form_message = '[quote author=' . $mname . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $mdate . ']' . "\n" . rtrim($form_message) . "\n" . '[/quote]';
		}
		// Posting a reply without a quote?
		elseif (!empty($topic) && empty($_REQUEST['quote']))
		{
			// Get the first message's subject.
			$form_subject = $first_subject;

			// Add 'Re: ' to the front of the subject.
			if (trim($context['response_prefix']) != '' && $form_subject != '' && westr::strpos($form_subject, trim($context['response_prefix'])) !== 0)
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

	// Did we provide the message with a parent?
	$context['msg_parent'] = isset($_REQUEST['parent']) ? $_REQUEST['parent'] : (isset($_REQUEST['quote']) ? $_REQUEST['quote'] : 0);

	// !!! This won't work if you're posting an event.
	if (allowedTo('post_attachment'))
	{
		if (empty($_SESSION['temp_attachments']))
			$_SESSION['temp_attachments'] = array();

		if (!empty($settings['currentAttachmentUploadDir']))
		{
			if (!is_array($settings['attachmentUploadDir']))
				$settings['attachmentUploadDir'] = unserialize($settings['attachmentUploadDir']);

			// Just use the current path for temp files.
			$current_attach_dir = $settings['attachmentUploadDir'][$settings['currentAttachmentUploadDir']];
		}
		else
			$current_attach_dir = $settings['attachmentUploadDir'];

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

				if (!empty($settings['attachmentSizeLimit']) && $_FILES['attachment']['size'][$n] > $settings['attachmentSizeLimit'] * 1024)
					fatal_lang_error('file_too_big', false, array($settings['attachmentSizeLimit']));

				$quantity++;
				if (!empty($settings['attachmentNumPerPostLimit']) && $quantity > $settings['attachmentNumPerPostLimit'])
					fatal_lang_error('attachments_limit_per_post', false, array($settings['attachmentNumPerPostLimit']));

				$total_size += $_FILES['attachment']['size'][$n];
				if (!empty($settings['attachmentPostLimit']) && $total_size > $settings['attachmentPostLimit'] * 1024)
					fatal_lang_error('file_too_big', false, array($settings['attachmentPostLimit']));

				if (!empty($settings['attachmentCheckExtensions']))
				{
					if (!in_array(strtolower(substr(strrchr($_FILES['attachment']['name'][$n], '.'), 1)), explode(',', strtolower($settings['attachmentExtensions']))))
						fatal_error($_FILES['attachment']['name'][$n] . '.<br>' . $txt['cant_upload_type'] . ' ' . $settings['attachmentExtensions'] . '.', false);
				}

				if (!empty($settings['attachmentDirSizeLimit']))
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

					// Too big! Maybe you could zip it or something...
					if ($_FILES['attachment']['size'][$n] + $dirSize > $settings['attachmentDirSizeLimit'] * 1024)
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
		$context['post_error']['messages'][] = sprintf($txt['error_old_topic'], $settings['oldTopicDays']);
		$context['error_type'] = 'minor';
	}

	// What are you doing? Posting a poll, modifying, previewing, new post, or reply...
	if (isset($_REQUEST['poll']))
		$context['page_title'] = $txt['new_poll'];
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
			'extra_before' => '<span' . ($theme['linktree_inline'] ? ' class="smalltext"' : '') . '><strong class="nav">' . $context['page_title'] . ' [</strong></span>',
			'extra_after' => '<span' . ($theme['linktree_inline'] ? ' class="smalltext"' : '') . '><strong class="nav">]</strong></span>'
		);

	// We need to check permissions, and also send the maximum allowed attachments through to the front end - it's dealt with there.
	// !!! This won't work if you're posting an event.
	$context['max_allowed_attachments'] = empty($settings['attachmentNumPerPostLimit']) ? 50 : $settings['attachmentNumPerPostLimit'];
	$context['can_post_attachment'] = !empty($settings['attachmentEnable']) && $settings['attachmentEnable'] == 1 && allowedTo('post_attachment') && $context['max_allowed_attachments'] > 0;

	$context['subject'] = addcslashes($form_subject, '"');
	$context['message'] = str_replace(array('"', '<', '>', '&nbsp;'), array('&quot;', '&lt;', '&gt;', ' '), $form_message);

	// Hang on, we might be loading a draft.
	$_REQUEST['draft_id'] = isset($_REQUEST['draft_id']) ? (int) $_REQUEST['draft_id'] : 0;
	if (!empty($_REQUEST['draft_id']) && !empty($user_info['id']) && allowedTo('save_post_draft') && empty($_POST['subject']) && empty($_POST['message']))
	{
		$query = wesql::query('
			SELECT subject, body, extra
			FROM {db_prefix}drafts
			WHERE id_draft = {int:draft}
				AND id_member = {int:member}
				AND is_pm = {int:not_pm}
			LIMIT 1',
			array(
				'draft' => $_REQUEST['draft_id'],
				'member' => $user_info['id'],
				'not_pm' => 0,
			)
		);

		if ($row = wesql::fetch_assoc($query))
		{
			// OK, we have a draft in storage for this post. Let's get down and dirty with it.
			$context['subject'] = $row['subject'];
			$context['message'] = wedit::un_preparsecode($row['body']);
			$row['extra'] = empty($row['extra']) ? array() : unserialize($row['extra']);
			$context['use_smileys'] = !empty($row['extra']['smileys_enabled']);
			$context['icon'] = empty($row['extra']['post_icon']) ? 'xx' : $row['extra']['post_icon'];

			// !!! Deal with locked and pinned?
		}

		wesql::free_result($query);

		call_hook('post_form_load_draft');
	}

	// Now create the editor.
	$context['postbox'] = new wedit(
		array(
			'id' => 'message',
			'value' => $context['message'],
			'labels' => array(
				'post_button' => $context['submit_label'],
			),
			'buttons' => array(
				array(
					'name' => 'post_button',
					'button_text' => $context['submit_label'],
					'onclick' => 'return submitThisOnce(this);',
					'accesskey' => 's',
				),
				array(
					'name' => 'preview',
					'button_text' => $txt['preview'],
					'onclick' => 'return event.ctrlKey || previewPost();',
					'accesskey' => 'p',
				),
			),
			// add height and width for the editor
			'height' => '175px',
			'width' => '100%',
			'drafts' => !allowedTo('save_post_draft') || empty($settings['masterSavePostDrafts']) || !empty($_REQUEST['msg']) ? 'none' : (!allowedTo('auto_save_post_draft') || empty($settings['masterAutoSavePostDrafts']) || !empty($options['disable_auto_save']) ? 'basic_post' : 'auto_post'),
		)
	);

	// Add the postbox to the list of fields in the form.
	$context['form_fields']['text'][] = $context['postbox']->id;

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
		$context['icon_url'] = $theme[file_exists($theme['theme_dir'] . '/images/post/' . $context['icon'] . '.gif') ? 'images_url' : 'default_images_url'] . '/post/' . $context['icon'] . '.gif';
		array_unshift($context['icons'], array(
			'value' => $context['icon'],
			'name' => $txt['current_icon'],
			'url' => $context['icon_url'],
			'is_last' => empty($context['icons']),
			'selected' => true,
		));
	}

	if (!empty($topic) && !empty($settings['topicSummaryPosts']))
		getTopic();

	// If the user can post attachments prepare the warning labels.
	if ($context['can_post_attachment'])
	{
		$context['allowed_extensions'] = strtr($settings['attachmentExtensions'], array(',' => ', '));
		$context['attachment_restrictions'] = array();
		$attachmentRestrictionTypes = array('attachmentNumPerPostLimit', 'attachmentPostLimit', 'attachmentSizeLimit');
		foreach ($attachmentRestrictionTypes as $type)
			if (!empty($settings[$type]))
				$context['attachment_restrictions'][] = sprintf($txt['attach_restrict_' . $type], $settings[$type]);
	}

	$context['back_to_topic'] = isset($_REQUEST['goback']) || (isset($_REQUEST['msg']) && !isset($_REQUEST['subject']));
	$context['show_additional_options'] = !empty($_POST['additional_options']) || !empty($_SESSION['temp_attachments']) || !empty($deleted_attachments);

	$context['is_new_topic'] = empty($topic);
	$context['is_new_post'] = !isset($_REQUEST['msg']);
	$context['is_first_post'] = $context['is_new_topic'] || (isset($_REQUEST['msg']) && $_REQUEST['msg'] == $id_first_msg);

	// Do we need to show the visual verification image?
	$context['require_verification'] = !$user_info['is_mod'] && !$user_info['is_admin'] && !empty($settings['posts_require_captcha']) && ($user_info['posts'] < $settings['posts_require_captcha'] || ($user_info['is_guest'] && $settings['posts_require_captcha'] == -1));
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
	$settings['disable_wysiwyg'] = !empty($settings['disable_wysiwyg']) || empty($settings['enableBBC']);

	// Register this form in the session variables.
	checkSubmitOnce('register');

	// Finally, load the template.
	if (!isset($_REQUEST['xml']))
	{
		loadTemplate('Post');
		wetem::load(
			array(
				'preview', // It doesn't need to be actually in the form at all, there's no form elements in it (or shouldn't be)
				'postform' => array(
					'post_errors',
					'post_approval',
					'post_locked',
					'post_header' => array(
						'post_name_email',
						'post_subject',
					),
					'postbox',
					'post_additional_options',
					'post_attachments',
					'post_verification',
					'post_buttons',
				),
			)
		);

		// Now, we add some things dynamically.
		if ($context['make_poll'])
			wetem::before('postbox', 'make_poll');
	}

	// Add in any last minute changes.
	call_hook('post_form');
}

/**
 * This function gets the most recent posts, including new replies, for the post view.
 *
 * - If calling from an XML view, the number of replies is simply the new ones, otherwise it attempts to get the number specified as the amount of posts to return in $settings['topicSummaryPosts'].
 * - Note that if getting posts for an edit request, only the posts prior to the one being edited are considered.
 * - The posts are censored, BBC processed, and stored in an array within $context.
 * - The number of new replies is known from processing elsewhere in Post(), so it simply has to count the number of posts back to know which ones are new (e.g. 4 new replies, the 4 newest get 'new' flags set)
 */
function getTopic()
{
	global $topic, $settings, $context, $counter, $options;

	if (isset($_REQUEST['xml']))
		$limit = '
		LIMIT ' . (empty($context['new_replies']) ? '0' : $context['new_replies']);
	else
		$limit = empty($settings['topicSummaryPosts']) ? '' : '
		LIMIT ' . (int) $settings['topicSummaryPosts'];

	// If you're modifying, get only those posts before the current one. (otherwise get all.)
	$request = wesql::query('
		SELECT
			IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
			m.body, m.smileys_enabled, m.id_msg, m.id_member
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_topic = {int:current_topic}' . (isset($_REQUEST['msg']) ? '
			AND m.id_msg < {int:id_msg}' : '') .(!$settings['postmod_active'] || allowedTo('approve_posts') ? '' : '
			AND m.approved = {int:is_approved}') . '
		ORDER BY m.id_msg DESC' . $limit,
		array(
			'current_topic' => $topic,
			'id_msg' => isset($_REQUEST['msg']) ? (int) $_REQUEST['msg'] : 0,
			'is_approved' => 1,
		)
	);
	$context['previous_posts'] = array();
	while ($row = wesql::fetch_assoc($request))
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
			'is_ignored' => !empty($settings['enable_buddylist']) && !empty($options['posts_apply_ignore_list']) && in_array($row['id_member'], $context['user']['ignoreusers']),
		);

		if (!empty($context['new_replies']))
			$context['new_replies']--;
	}
	wesql::free_result($request);
}

?>