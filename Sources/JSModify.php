<?php
/**
 * Wedge
 *
 * This file handles the core of quick (inline) edit functionality.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * This function handles the inline editor functionality.
 *
 * - Session check via URL.
 * - Checks that there is a topic specified, and if not, exit.
 * - Load the relevant message or die fatally if the message could not be accessed (or doesn't exist)
 * - Check we have permission to modify the message (if locked, validate we have ability to override with moderation, otherwise check if it is our own post, and whether we can modify anyone's or simply our own, whether we can modify replies or not, and whether we are within a given time-window for editing)
 * - Flag whether we are editing our own or someone else's message, for mod-log purposes.
 * - Sanitize the incoming title (check not empty, and if so: replace CR, LF or TAB with empty string and custom htmlspecialchars replacement, then truncate to 100 characters)
 * - Sanitize the incoming message (check not empty, check not over-long, custom htmlspecialchars, preparsecode, check that if the tags except img are removed that there is some content)
 * - Handle the message locking and sticky status.
 * - If no errors from the above, prepare the array triplet (msg/topic/poster Options) and pass to {@link modifyPost()} noting that the post is modified on subject/message/icon changing.
 * - If there are no errors and we edited something in the message, prepare the newly edited message for return, i.e. build an array with everything needed to make the post, parsed for BBC, and return via XML.
 * - If there were no errors but we only changed the icon or subject, return only those details.
 * - Otherwise there were some errors - so get ready to return those too (including loading the relevant language file, and do any preprocessing on the strings, e.g. the long_message one)
 *
 * - @todo Remove icon being logged as an edit - there is no reason to flag it as edited if only the icon has changed.
 */
function JSModify()
{
	global $modSettings, $board, $topic, $txt;
	global $user_info, $context, $language;

	// We have to have a topic!
	if (empty($topic))
		obExit(false);

	checkSession('get');
	loadSource(array('Subs-Post', 'Class-Editor'));

	// Assume the first message if no message ID was given.
	$request = wesql::query('
			SELECT
				t.locked, t.num_replies, t.id_member_started, t.id_first_msg,
				m.id_msg, m.id_member, m.poster_time, m.subject, m.smileys_enabled, m.body, m.icon,
				m.modified_time, m.modified_name, m.approved
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic})
			WHERE m.id_msg = {raw:id_msg}
				AND m.id_topic = {int:current_topic}' . (allowedTo('approve_posts') ? '' : (!$modSettings['postmod_active'] ? '
				AND (m.id_member != {int:guest_id} AND m.id_member = {int:current_member})' : '
				AND (m.approved = {int:is_approved} OR (m.id_member != {int:guest_id} AND m.id_member = {int:current_member}))')),
			array(
				'current_member' => $user_info['id'],
				'current_topic' => $topic,
				'id_msg' => empty($_REQUEST['msg']) ? 't.id_first_msg' : (int) $_REQUEST['msg'],
				'is_approved' => 1,
				'guest_id' => 0,
			)
		);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('no_board', false);
	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// Change either body or subject requires permissions to modify messages.
	if (isset($_POST['message']) || isset($_POST['subject']) || isset($_REQUEST['icon']))
	{
		if (!empty($row['locked']))
			isAllowedTo('moderate_board');

		if ($row['id_member'] == $user_info['id'] && !allowedTo('modify_any'))
		{
			if ((!$modSettings['postmod_active'] || $row['approved']) && !empty($modSettings['edit_disable_time']) && $row['poster_time'] + ($modSettings['edit_disable_time'] + 5) * 60 < time())
				fatal_lang_error('modify_post_time_passed', false);
			elseif ($row['id_member_started'] == $user_info['id'] && !allowedTo('modify_own'))
				isAllowedTo('modify_replies');
			else
				isAllowedTo('modify_own');
		}
		// Otherwise, they're locked out; someone who can modify the replies is needed.
		elseif ($row['id_member_started'] == $user_info['id'] && !allowedTo('modify_any'))
			isAllowedTo('modify_replies');
		else
			isAllowedTo('modify_any');

		// Only log this action if it wasn't your message.
		$moderationAction = $row['id_member'] != $user_info['id'];
	}

	$post_errors = array();
	if (isset($_POST['subject']) && westr::htmltrim(westr::htmlspecialchars($_POST['subject'])) !== '')
	{
		$_POST['subject'] = strtr(westr::htmlspecialchars($_POST['subject']), array("\r" => '', "\n" => '', "\t" => ''));

		// Maximum number of characters.
		if (westr::strlen($_POST['subject']) > 100)
			$_POST['subject'] = westr::substr($_POST['subject'], 0, 100);
	}
	elseif (isset($_POST['subject']))
	{
		$post_errors[] = 'no_subject';
		unset($_POST['subject']);
	}

	if (isset($_POST['message']))
	{
		if (westr::htmltrim(westr::htmlspecialchars($_POST['message'])) === '')
		{
			$post_errors[] = 'no_message';
			unset($_POST['message']);
		}
		elseif (!empty($modSettings['max_messageLength']) && westr::strlen($_POST['message']) > $modSettings['max_messageLength'])
		{
			$post_errors[] = 'long_message';
			unset($_POST['message']);
		}
		else
		{
			$_POST['message'] = westr::htmlspecialchars($_POST['message'], ENT_QUOTES);

			wedit::preparsecode($_POST['message']);

			if (westr::htmltrim(strip_tags(parse_bbc($_POST['message'], false), '<img><object><embed><iframe><video><audio>')) === '')
			{
				$post_errors[] = 'no_message';
				unset($_POST['message']);
			}
		}
	}

	if (isset($_POST['lock']))
	{
		if (!allowedTo(array('lock_any', 'lock_own')) || (!allowedTo('lock_any') && $user_info['id'] != $row['id_member']))
			unset($_POST['lock']);
		elseif (!allowedTo('lock_any'))
		{
			if ($row['locked'] == 1)
				unset($_POST['lock']);
			else
				$_POST['lock'] = empty($_POST['lock']) ? 0 : 2;
		}
		elseif (!empty($row['locked']) && !empty($_POST['lock']) || $_POST['lock'] == $row['locked'])
			unset($_POST['lock']);
		else
			$_POST['lock'] = empty($_POST['lock']) ? 0 : 1;
	}

	if (isset($_POST['sticky']) && !allowedTo('make_sticky'))
		unset($_POST['sticky']);

	if (empty($post_errors))
	{
		$msgOptions = array(
			'id' => $row['id_msg'],
			'subject' => isset($_POST['subject']) ? $_POST['subject'] : null,
			'body' => isset($_POST['message']) ? $_POST['message'] : null,
			'icon' => isset($_REQUEST['icon']) ? preg_replace('~[./\\\\*\':"<>]~', '', $_REQUEST['icon']) : null,
		);
		$topicOptions = array(
			'id' => $topic,
			'board' => $board,
			'lock_mode' => isset($_POST['lock']) ? (int) $_POST['lock'] : null,
			'sticky_mode' => isset($_POST['sticky']) ? (int) $_POST['sticky'] : null,
			'mark_as_read' => true,
		);
		$posterOptions = array();

		// Only consider marking as editing if they have edited the subject, message or icon.
		if ((isset($_POST['subject']) && $_POST['subject'] != $row['subject']) || (isset($_POST['message']) && $_POST['message'] != $row['body']) || (isset($_REQUEST['icon']) && $_REQUEST['icon'] != $row['icon']))
		{
			// And even then only if the time has passed...
			if (time() - $row['poster_time'] > $modSettings['edit_wait_time'] || $user_info['id'] != $row['id_member'])
			{
				$msgOptions['modify_time'] = time();
				$msgOptions['modify_name'] = $user_info['name'];
			}
		}
		// If nothing was changed there's no need to add an entry to the moderation log.
		else
			$moderationAction = false;

		modifyPost($msgOptions, $topicOptions, $posterOptions);

		// If we didn't change anything this time but had before put back the old info.
		if (!isset($msgOptions['modify_time']) && !empty($row['modified_time']))
		{
			$msgOptions['modify_time'] = $row['modified_time'];
			$msgOptions['modify_name'] = $row['modified_name'];
		}

		// Changing the first subject updates other subjects to 'Re: new_subject'.
		if (isset($_POST['subject'], $_REQUEST['change_all_subjects']) && $row['id_first_msg'] == $row['id_msg'] && !empty($row['num_replies']) && (allowedTo('modify_any') || ($row['id_member_started'] == $user_info['id'] && allowedTo('modify_replies'))))
		{
			// Get the proper (default language) response prefix first.
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

			wesql::query('
				UPDATE {db_prefix}messages
				SET subject = {string:subject}
				WHERE id_topic = {int:current_topic}
					AND id_msg != {int:id_first_msg}',
				array(
					'current_topic' => $topic,
					'id_first_msg' => $row['id_first_msg'],
					'subject' => $context['response_prefix'] . $_POST['subject'],
				)
			);
		}

		if (!empty($moderationAction))
			logAction('modify', array('topic' => $topic, 'message' => $row['id_msg'], 'member' => $row['id_member'], 'board' => $board));
	}

	if (isset($_REQUEST['xml']))
	{
		loadSubTemplate('modifydone');
		if (empty($post_errors) && isset($msgOptions['subject'], $msgOptions['body']))
		{
			$context['message'] = array(
				'id' => $row['id_msg'],
				'modified' => array(
					'time' => isset($msgOptions['modify_time']) ? timeformat($msgOptions['modify_time']) : '',
					'timestamp' => isset($msgOptions['modify_time']) ? forum_time(true, $msgOptions['modify_time']) : 0,
					'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
				),
				'subject' => $msgOptions['subject'],
				'first_in_topic' => $row['id_msg'] == $row['id_first_msg'],
				'body' => strtr($msgOptions['body'], array(']]>' => ']]]]><![CDATA[>')),
			);

			censorText($context['message']['subject']);
			censorText($context['message']['body']);

			$context['message']['body'] = parse_bbc($context['message']['body'], $row['smileys_enabled'], $row['id_msg']);
		}
		// Topic?
		elseif (empty($post_errors))
		{
			loadSubTemplate('modifytopicdone');
			$context['message'] = array(
				'id' => $row['id_msg'],
				'modified' => array(
					'time' => isset($msgOptions['modify_time']) ? timeformat($msgOptions['modify_time']) : '',
					'timestamp' => isset($msgOptions['modify_time']) ? forum_time(true, $msgOptions['modify_time']) : 0,
					'name' => isset($msgOptions['modify_time']) ? $msgOptions['modify_name'] : '',
				),
				'subject' => isset($msgOptions['subject']) ? $msgOptions['subject'] : '',
			);

			censorText($context['message']['subject']);
		}
		else
		{
			$context['message'] = array(
				'id' => $row['id_msg'],
				'errors' => array(),
				'error_in_subject' => in_array('no_subject', $post_errors),
				'error_in_body' => in_array('no_message', $post_errors) || in_array('long_message', $post_errors),
			);

			loadLanguage('Errors');
			foreach ($post_errors as $post_error)
			{
				if ($post_error == 'long_message')
					$context['message']['errors'][] = sprintf($txt['error_' . $post_error], $modSettings['max_messageLength']);
				else
					$context['message']['errors'][] = $txt['error_' . $post_error];
			}
		}
	}
	else
		obExit(false);
}

?>