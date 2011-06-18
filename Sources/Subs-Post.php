<?php
/**
 * Wedge
 *
 * Various support routines for posting, including sending mail and similar operations.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file contains those functions pertaining to posting, and other such
	operations, including sending emails, ims, blocking spam, preparsing posts,
	spell checking, and the post box.  This is done with the following:

	bool sendmail(array to, string subject, string message,
			string message_id = auto, string from = webmaster,
			bool send_html = false, int priority = 3, bool hotmail_fix = null)
		- sends an email to the specified recipient.
		- uses the mail_type setting and the webmaster_email global.
		- to is he email(s), string or array, to send to.
		- subject and message are those of the email - expected to have
		  slashes but not be parsed.
		- subject is expected to have entities, message is not.
		- from is a string which masks the address for use with replies.
		- if message_id is specified, uses that as the local-part of the
		  Message-ID header.
		- send_html indicates whether or not the message is HTML vs. plain
		  text, and does not add any HTML.
		- returns whether or not the email was sent properly.

	bool AddMailQueue(bool flush = true, array to_array = array(), string subject = '', string message = '',
		string headers = '', bool send_html = false, int priority = 3)
		//!!

	array sendpm(array recipients, string subject, string message,
			bool store_outbox = false, array from = current_member, int pm_head = 0)
		- sends an personal message from the specified person to the
		  specified people. (from defaults to the user.)
		- recipients should be an array containing the arrays 'to' and 'bcc',
		  both containing id_member's.
		- subject and message should have no slashes and no html entities.
		- pm_head is the ID of the chain being replied to - if any.
		- from is an array, with the id, name, and username of the member.
		- returns an array with log entries telling how many recipients were
		  successful and which recipients it failed to send to.

	string mimespecialchars(string text, bool with_charset = true,
			hotmail_fix = false, string custom_charset = null)
		- prepare text strings for sending as email.
		- in case there are higher ASCII characters in the given string, this
		  function will attempt the transport method 'quoted-printable'.
		  Otherwise the transport method '7bit' is used.
		- with hotmail_fix set all higher ASCII characters are converted to
		  HTML entities to assure proper display of the mail.
		- uses character set custom_charset if set.
		- returns an array containing the character set, the converted string
		  and the transport method.

	bool smtp_mail(array mail_to_array, string subject, string message,
			string headers)
		- sends mail, like mail() but over SMTP.  Used internally.
		- takes email addresses, a subject and message, and any headers.
		- expects no slashes or entities.
		- returns whether it sent or not.

	bool server_parse(string message, resource socket, string response)
		- sends the specified message to the server, and checks for the
		  expected response. (used internally.)
		- takes the message to send, socket to send on, and the expected
		  response code.
		- returns whether it responded as such.

	void sendNotifications(array topics, string type, array exclude = array(), array members_only = array())
		- sends a notification to members who have elected to receive emails
		  when things happen to a topic, such as replies are posted.
		- uses the Post langauge file.
		- topics represents the topics the action is happening to.
		- the type can be any of reply, sticky, lock, unlock, remove, move,
		  merge, and split.  An appropriate message will be sent for each.
		- automatically finds the subject and its board, and checks permissions
		  for each member who is "signed up" for notifications.
		- will not send 'reply' notifications more than once in a row.
		- members in the exclude array will not be processed for the topic with the same key.
		- members_only are the only ones that will be sent the notification if they have it on.

	bool createPost(&array msgOptions, &array topicOptions, &array posterOptions)
		// !!!

	bool createAttachment(&array attachmentOptions)
		// !!!

	bool modifyPost(&array msgOptions, &array topicOptions, &array posterOptions)
		// !!!

	bool approvePosts(array msgs, bool approve)
		// !!!

	array approveTopics(array topics, bool approve)
		// !!!

	void sendApprovalNotifications(array topicData)
		// !!!

	void updateLastMessages(array id_board's, int id_msg)
		- takes an array of board IDs and updates their last messages.
		- if the board has a parent, that parent board is also automatically
		  updated.
		- columns updated are id_last_msg and lastUpdated.
		- note that id_last_msg should always be updated using this function,
		  and is not automatically updated upon other changes.

	void adminNotify(string type, int memberID, string member_name = null)
		- sends all admins an email to let them know a new member has joined.
		- types supported are 'approval', 'activation', and 'standard'.
		- called by registerMember() function in Subs-Members.php.
		- email is sent to all groups that have the moderate_forum permission.
		- uses the Login language file.
		- the language set by each member is being used (if available).

	Sending emails from SMF:
	---------------------------------------------------------------------------
		// !!!
*/

// Send off an email.
function sendmail($to, $subject, $message, $from = null, $message_id = null, $send_html = false, $priority = 3, $hotmail_fix = null, $is_private = false)
{
	global $webmaster_email, $context, $modSettings, $txt, $scripturl;

	// Use sendmail if it's set or if no SMTP server is set.
	$use_sendmail = empty($modSettings['mail_type']) || $modSettings['smtp_host'] == '';

	// Line breaks need to be \r\n only in windows or for SMTP.
	$line_break = $context['server']['is_windows'] || !$use_sendmail ? "\r\n" : "\n";

	// So far so good.
	$mail_result = true;

	// If the recipient list isn't an array, make it one.
	$to_array = is_array($to) ? $to : array($to);

	// Once upon a time, Hotmail could not interpret non-ASCII mails.
	// In honour of those days, it's still called the 'hotmail fix'.
	if ($hotmail_fix === null)
	{
		$hotmail_to = array();
		foreach ($to_array as $i => $to_address)
		{
			if (preg_match('~@(att|comcast|bellsouth)\.[a-zA-Z.]{2,6}$~i', $to_address) === 1)
			{
				$hotmail_to[] = $to_address;
				$to_array = array_diff($to_array, array($to_address));
			}
		}

		// Call this function recursively for the hotmail addresses.
		if (!empty($hotmail_to))
			$mail_result = sendmail($hotmail_to, $subject, $message, $from, $message_id, $send_html, $priority, true);

		// The remaining addresses no longer need the fix.
		$hotmail_fix = false;

		// No other addresses left? Return instantly.
		if (empty($to_array))
			return $mail_result;
	}

	// Get rid of entities.
	$subject = un_htmlspecialchars($subject);
	// Make the message use the proper line breaks.
	$message = str_replace(array("\r", "\n"), array('', $line_break), $message);

	// Make sure hotmail mails are sent as HTML so that HTML entities work.
	if ($hotmail_fix && !$send_html)
	{
		$send_html = true;
		$message = strtr($message, array($line_break => '<br>' . $line_break));
		$message = preg_replace('~(' . preg_quote($scripturl, '~') . '(?:[?/][\w%.,?&;=#-]+)?)~', '<a href="$1">$1</a>', $message);
	}

	list (, $from_name) = mimespecialchars(addcslashes($from !== null ? $from : $context['forum_name'], '<>()\'\\"'), true, $hotmail_fix, $line_break);
	list (, $subject) = mimespecialchars($subject, true, $hotmail_fix, $line_break);

	// Construct the mail headers...
	$headers = 'From: "' . $from_name . '" <' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . '>' . $line_break;
	$headers .= $from !== null ? 'Reply-To: <' . $from . '>' . $line_break : '';
	$headers .= 'Return-Path: ' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . $line_break;
	$headers .= 'Date: ' . gmdate('D, d M Y H:i:s') . ' -0000' . $line_break;

	if ($message_id !== null && empty($modSettings['mail_no_message_id']))
		$headers .= 'Message-ID: <' . md5($scripturl . microtime()) . '-' . $message_id . strstr(empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from'], '@') . '>' . $line_break;
	$headers .= 'X-Mailer: SMF' . $line_break;

	// Pass this to the hook before we start modifying the output -- it'll make it easier later.
	if (in_array(false, call_hook('outgoing_email', array(&$subject, &$message, &$headers)), true))
		return false;

	// Save the original message...
	$orig_message = $message;

	// The mime boundary separates the different alternative versions.
	$mime_boundary = 'SMF-' . md5($message . time());

	// Using mime, as it allows to send a plain unencoded alternative.
	$headers .= 'Mime-Version: 1.0' . $line_break;
	$headers .= 'Content-Type: multipart/alternative; boundary="' . $mime_boundary . '"' . $line_break;
	$headers .= 'Content-Transfer-Encoding: 7bit' . $line_break;

	// Sending HTML?  Let's plop in some basic stuff, then.
	if ($send_html)
	{
		$no_html_message = un_htmlspecialchars(strip_tags(strtr($orig_message, array('</title>' => $line_break))));

		// But, then, dump it and use a plain one for dinosaur clients.
		list (, $plain_message) = mimespecialchars($no_html_message, false, true, $line_break);
		$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

		// This is the plain text version.  Even if no one sees it, we need it for spam checkers.
		list ($charset, $plain_charset_message, $encoding) = mimespecialchars($no_html_message, false, false, $line_break);
		$message .= 'Content-Type: text/plain; charset=' . $charset . $line_break;
		$message .= 'Content-Transfer-Encoding: ' . $encoding . $line_break . $line_break;
		$message .= $plain_charset_message . $line_break . '--' . $mime_boundary . $line_break;

		// This is the actual HTML message, prim and proper.  If we wanted images, they could be inlined here (with multipart/related, etc.)
		list ($charset, $html_message, $encoding) = mimespecialchars($orig_message, false, $hotmail_fix, $line_break);
		$message .= 'Content-Type: text/html; charset=' . $charset . $line_break;
		$message .= 'Content-Transfer-Encoding: ' . ($encoding == '' ? '7bit' : $encoding) . $line_break . $line_break;
		$message .= $html_message . $line_break . '--' . $mime_boundary . '--';
	}
	// Text is good too.
	else
	{
		// Send a plain message first, for the older web clients.
		list (, $plain_message) = mimespecialchars($orig_message, false, true, $line_break);
		$message = $plain_message . $line_break . '--' . $mime_boundary . $line_break;

		// Now add an encoded message using the forum's character set.
		list ($charset, $encoded_message, $encoding) = mimespecialchars($orig_message, false, false, $line_break);
		$message .= 'Content-Type: text/plain; charset=' . $charset . $line_break;
		$message .= 'Content-Transfer-Encoding: ' . $encoding . $line_break . $line_break;
		$message .= $encoded_message . $line_break . '--' . $mime_boundary . '--';
	}

	// Are we using the mail queue, if so this is where we butt in...
	if (!empty($modSettings['mail_queue']) && $priority != 0)
		return AddMailQueue(false, $to_array, $subject, $message, $headers, $send_html, $priority, $is_private);

	// If it's a priority mail, send it now - note though that this should NOT be used for sending many at once.
	elseif (!empty($modSettings['mail_queue']) && !empty($modSettings['mail_limit']))
	{
		list ($last_mail_time, $mails_this_minute) = @explode('|', $modSettings['mail_recent']);
		if (empty($mails_this_minute) || time() > $last_mail_time + 60)
			$new_queue_stat = time() . '|' . 1;
		else
			$new_queue_stat = $last_mail_time . '|' . ((int) $mails_this_minute + 1);

		updateSettings(array('mail_recent' => $new_queue_stat));
	}

	// SMTP or sendmail?
	if ($use_sendmail)
	{
		$subject = strtr($subject, array("\r" => '', "\n" => ''));
		if (!empty($modSettings['mail_strip_carriage']))
		{
			$message = strtr($message, array("\r" => ''));
			$headers = strtr($headers, array("\r" => ''));
		}

		foreach ($to_array as $to)
		{
			if (!mail(strtr($to, array("\r" => '', "\n" => '')), $subject, $message, $headers))
			{
				log_error(sprintf($txt['mail_send_unable'], $to));
				$mail_result = false;
			}

			// Wait, wait, I'm still sending here!
			@set_time_limit(300);
			if (function_exists('apache_reset_timeout'))
				@apache_reset_timeout();
		}
	}
	else
		$mail_result = $mail_result && smtp_mail($to_array, $subject, $message, $headers);

	// Everything go smoothly?
	return $mail_result;
}

// Add an email to the mail queue.
function AddMailQueue($flush = false, $to_array = array(), $subject = '', $message = '', $headers = '', $send_html = false, $priority = 3, $is_private = false)
{
	global $context, $modSettings;

	static $cur_insert = array();
	static $cur_insert_len = 0;

	if ($cur_insert_len == 0)
		$cur_insert = array();

	// If we're flushing, make the final inserts - also if we're near the MySQL length limit!
	if (($flush || $cur_insert_len > 800000) && !empty($cur_insert))
	{
		// Only do these once.
		$cur_insert_len = 0;

		// Dump the data...
		wesql::insert('',
			'{db_prefix}mail_queue',
			array(
				'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string-65534', 'subject' => 'string-255',
				'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int',
			),
			$cur_insert,
			array('id_mail')
		);

		$cur_insert = array();
		$context['flush_mail'] = false;
	}

	// If we're flushing we're done.
	if ($flush)
	{
		$nextSendTime = time() + 10;

		wesql::query('
			UPDATE {db_prefix}settings
			SET value = {string:nextSendTime}
			WHERE variable = {string:mail_next_send}
				AND value = {string:no_outstanding}',
			array(
				'nextSendTime' => $nextSendTime,
				'mail_next_send' => 'mail_next_send',
				'no_outstanding' => '0',
			)
		);

		return true;
	}

	// Ensure we tell obExit to flush.
	$context['flush_mail'] = true;

	foreach ($to_array as $to)
	{
		// Will this insert go over MySQL's limit?
		$this_insert_len = strlen($to) + strlen($message) + strlen($headers) + 700;

		// Insert limit of 1M (just under the safety) is reached?
		if ($this_insert_len + $cur_insert_len > 1000000)
		{
			// Flush out what we have so far.
			wesql::insert('',
				'{db_prefix}mail_queue',
				array(
					'time_sent' => 'int', 'recipient' => 'string-255', 'body' => 'string-65534', 'subject' => 'string-255',
					'headers' => 'string-65534', 'send_html' => 'int', 'priority' => 'int', 'private' => 'int',
				),
				$cur_insert,
				array('id_mail')
			);

			// Clear this out.
			$cur_insert = array();
			$cur_insert_len = 0;
		}

		// Now add the current insert to the array...
		$cur_insert[] = array(time(), (string) $to, (string) $message, (string) $subject, (string) $headers, ($send_html ? 1 : 0), $priority, (int) $is_private);
		$cur_insert_len += $this_insert_len;
	}

	// If they are using SSI there is a good chance obExit will never be called.  So let's be nice and flush it for them.
	if (SMF === 'SSI')
		return AddMailQueue(true);

	return true;
}

// Send off a personal message.
function sendpm($recipients, $subject, $message, $store_outbox = false, $from = null, $pm_head = 0)
{
	global $scripturl, $txt, $user_info, $language;
	global $modSettings;

	// Make sure the PM language file is loaded, we might need something out of it.
	loadLanguage('PersonalMessage');
	loadSource('Class-Editor');

	$onBehalf = $from !== null;

	// Initialize log array.
	$log = array(
		'failed' => array(),
		'sent' => array()
	);

	if ($from === null)
		$from = array(
			'id' => $user_info['id'],
			'name' => $user_info['name'],
			'username' => $user_info['username']
		);
	// Probably not needed.  /me something should be of the typer.
	else
		$user_info['name'] = $from['name'];

	// This is the one that will go in their inbox.
	$htmlmessage = westr::htmlspecialchars($message, ENT_QUOTES);
	$htmlsubject = westr::htmlspecialchars($subject);
	wedit::preparsecode($htmlmessage);

	// Integrated PMs
	call_hook('personal_message', array(&$recipients, &$from['username'], &$subject, &$message));

	// Get a list of usernames and convert them to IDs.
	$usernames = array();
	foreach ($recipients as $rec_type => $rec)
	{
		foreach ($rec as $id => $member)
		{
			if (!is_numeric($recipients[$rec_type][$id]))
			{
				$recipients[$rec_type][$id] = westr::strtolower(trim(preg_replace('/[<>&"\'=\\\]/', '', $recipients[$rec_type][$id])));
				$usernames[$recipients[$rec_type][$id]] = 0;
			}
		}
	}
	if (!empty($usernames))
	{
		$request = wesql::query('
			SELECT id_member, member_name
			FROM {db_prefix}members
			WHERE member_name IN ({array_string:usernames})',
			array(
				'usernames' => array_keys($usernames),
			)
		);
		while ($row = wesql::fetch_assoc($request))
			if (isset($usernames[westr::strtolower($row['member_name'])]))
				$usernames[westr::strtolower($row['member_name'])] = $row['id_member'];
		wesql::free_result($request);

		// Replace the usernames with IDs. Drop usernames that couldn't be found.
		foreach ($recipients as $rec_type => $rec)
			foreach ($rec as $id => $member)
			{
				if (is_numeric($recipients[$rec_type][$id]))
					continue;

				if (!empty($usernames[$member]))
					$recipients[$rec_type][$id] = $usernames[$member];
				else
				{
					$log['failed'][$id] = sprintf($txt['pm_error_user_not_found'], $recipients[$rec_type][$id]);
					unset($recipients[$rec_type][$id]);
				}
			}
	}

	// Make sure there are no duplicate 'to' members.
	$recipients['to'] = array_unique($recipients['to']);

	// Only 'bcc' members that aren't already in 'to'.
	$recipients['bcc'] = array_diff(array_unique($recipients['bcc']), $recipients['to']);

	// Combine 'to' and 'bcc' recipients.
	$all_to = array_merge($recipients['to'], $recipients['bcc']);

	// Check no-one will want it deleted right away!
	$request = wesql::query('
		SELECT
			id_member, criteria, is_or
		FROM {db_prefix}pm_rules
		WHERE id_member IN ({array_int:to_members})
			AND delete_pm = {int:delete_pm}',
		array(
			'to_members' => $all_to,
			'delete_pm' => 1,
		)
	);
	$deletes = array();
	// Check whether we have to apply anything...
	while ($row = wesql::fetch_assoc($request))
	{
		$criteria = unserialize($row['criteria']);
		// Note we don't check the buddy status, cause deletion from buddy = madness!
		$delete = false;
		foreach ($criteria as $criterium)
		{
			$match = false;
			if (($criterium['t'] == 'mid' && $criterium['v'] == $from['id']) || ($criterium['t'] == 'gid' && in_array($criterium['v'], $user_info['groups'])) || ($criterium['t'] == 'sub' && strpos($subject, $criterium['v']) !== false) || ($criterium['t'] == 'msg' && strpos($message, $criterium['v']) !== false))
				$delete = true;
			// If we're adding and one criteria don't match then we stop!
			elseif (!$row['is_or'])
			{
				$delete = false;
				break;
			}
		}
		if ($delete)
			$deletes[$row['id_member']] = 1;
	}
	wesql::free_result($request);

	// Load the membergrounp message limits.
	//!!! Consider caching this?
	static $message_limit_cache = array();
	if (!allowedTo('moderate_forum') && empty($message_limit_cache))
	{
		$request = wesql::query('
			SELECT id_group, max_messages
			FROM {db_prefix}membergroups',
			array(
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$message_limit_cache[$row['id_group']] = $row['max_messages'];
		wesql::free_result($request);
	}

	// Load the groups that are allowed to read PMs.
	$allowed_groups = array();
	$disallowed_groups = array();
	$request = wesql::query('
		SELECT id_group, add_deny
		FROM {db_prefix}permissions
		WHERE permission = {string:read_permission}',
		array(
			'read_permission' => 'pm_read',
		)
	);

	while ($row = wesql::fetch_assoc($request))
	{
		if (empty($row['add_deny']))
			$disallowed_groups[] = $row['id_group'];
		else
			$allowed_groups[] = $row['id_group'];
	}

	wesql::free_result($request);

	if (empty($modSettings['permission_enable_deny']))
		$disallowed_groups = array();

	$request = wesql::query('
		SELECT
			member_name, real_name, id_member, email_address, lngfile,
			pm_email_notify, instant_messages,' . (allowedTo('moderate_forum') ? ' 0' : '
			(pm_receive_from = {int:admins_only}' . (empty($modSettings['enable_buddylist']) ? '' : ' OR
			(pm_receive_from = {int:buddies_only} AND FIND_IN_SET({string:from_id}, buddy_list) = 0) OR
			(pm_receive_from = {int:not_on_ignore_list} AND FIND_IN_SET({string:from_id}, pm_ignore_list) != 0)') . ')') . ' AS ignored,
			FIND_IN_SET({string:from_id}, buddy_list) != 0 AS is_buddy, is_activated,
			additional_groups, id_group, id_post_group
		FROM {db_prefix}members
		WHERE id_member IN ({array_int:recipients})
		ORDER BY lngfile
		LIMIT {int:count_recipients}',
		array(
			'not_on_ignore_list' => 1,
			'buddies_only' => 2,
			'admins_only' => 3,
			'recipients' => $all_to,
			'count_recipients' => count($all_to),
			'from_id' => $from['id'],
		)
	);
	$notifications = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Don't do anything for members to be deleted!
		if (isset($deletes[$row['id_member']]))
			continue;

		// We need to know this members groups.
		$groups = explode(',', $row['additional_groups']);
		$groups[] = $row['id_group'];
		$groups[] = $row['id_post_group'];

		$message_limit = -1;
		// For each group see whether they've gone over their limit - assuming they're not an admin.
		if (!in_array(1, $groups))
		{
			foreach ($groups as $id)
			{
				if (isset($message_limit_cache[$id]) && $message_limit != 0 && $message_limit < $message_limit_cache[$id])
					$message_limit = $message_limit_cache[$id];
			}

			if ($message_limit > 0 && $message_limit <= $row['instant_messages'])
			{
				$log['failed'][$row['id_member']] = sprintf($txt['pm_error_data_limit_reached'], $row['real_name']);
				unset($all_to[array_search($row['id_member'], $all_to)]);
				continue;
			}

			// Do they have any of the allowed groups?
			if (count(array_intersect($allowed_groups, $groups)) == 0 || count(array_intersect($disallowed_groups, $groups)) != 0)
			{
				$log['failed'][$row['id_member']] = sprintf($txt['pm_error_user_cannot_read'], $row['real_name']);
				unset($all_to[array_search($row['id_member'], $all_to)]);
				continue;
			}
		}

		// If the receiving account is banned (>=10) or pending deletion (4), refuse to send the PM.
		if ($row['is_activated'] >= 10 || ($row['is_activated'] == 4 && !$user_info['is_admin']))
		{
			$log['failed'][$row['id_member']] = sprintf($txt['pm_error_user_cannot_read'], $row['real_name']);
			unset($all_to[array_search($row['id_member'], $all_to)]);
			continue;
		}

		// Send a notification, if enabled - taking the buddy list into account.
		if (!empty($row['email_address']) && ($row['pm_email_notify'] == 1 || ($row['pm_email_notify'] > 1 && (!empty($modSettings['enable_buddylist']) && $row['is_buddy']))) && $row['is_activated'] == 1)
			$notifications[empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']][] = $row['email_address'];

		$log['sent'][$row['id_member']] = sprintf(isset($txt['pm_successfully_sent']) ? $txt['pm_successfully_sent'] : '', $row['real_name']);
	}
	wesql::free_result($request);

	// Only 'send' the message if there are any recipients left.
	if (empty($all_to))
		return $log;

	// Insert the message itself and then grab the last insert id.
	wesql::insert('',
		'{db_prefix}personal_messages',
		array(
			'id_pm_head' => 'int', 'id_member_from' => 'int', 'deleted_by_sender' => 'int',
			'from_name' => 'string-255', 'msgtime' => 'int', 'subject' => 'string-255', 'body' => 'string-65534',
		),
		array(
			$pm_head, $from['id'], ($store_outbox ? 0 : 1),
			$from['username'], time(), $htmlsubject, $htmlmessage,
		),
		array('id_pm')
	);
	$id_pm = wesql::insert_id();

	// Add the recipients.
	if (!empty($id_pm))
	{
		// If this is new we need to set it part of it's own conversation.
		if (empty($pm_head))
			wesql::query('
				UPDATE {db_prefix}personal_messages
				SET id_pm_head = {int:id_pm_head}
				WHERE id_pm = {int:id_pm_head}',
				array(
					'id_pm_head' => $id_pm,
				)
			);

		// Some people think manually deleting personal_messages is fun... it's not. We protect against it though :)
		wesql::query('
			DELETE FROM {db_prefix}pm_recipients
			WHERE id_pm = {int:id_pm}',
			array(
				'id_pm' => $id_pm,
			)
		);

		$insertRows = array();
		foreach ($all_to as $to)
		{
			$insertRows[] = array($id_pm, $to, in_array($to, $recipients['bcc']) ? 1 : 0, isset($deletes[$to]) ? 1 : 0, 1);
		}

		wesql::insert('insert',
			'{db_prefix}pm_recipients',
			array(
				'id_pm' => 'int', 'id_member' => 'int', 'bcc' => 'int', 'deleted' => 'int', 'is_new' => 'int'
			),
			$insertRows,
			array('id_pm', 'id_member')
		);
	}

	censorText($message);
	censorText($subject);
	$message = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc(htmlspecialchars($message), false), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

	foreach ($notifications as $lang => $notification_list)
	{
		// Make sure to use the right language.
		loadLanguage('index+PersonalMessage', $lang, false);

		// Replace the right things in the message strings.
		$mailsubject = str_replace(array('SUBJECT', 'SENDER'), array($subject, un_htmlspecialchars($from['name'])), $txt['new_pm_subject']);
		$mailmessage = str_replace(array('SUBJECT', 'MESSAGE', 'SENDER'), array($subject, $message, un_htmlspecialchars($from['name'])), $txt['pm_email']);
		$mailmessage .= "\n\n" . $txt['instant_reply'] . ' ' . $scripturl . '?action=pm;sa=send;f=inbox;pmsg=' . $id_pm . ';quote;u=' . $from['id'];

		// Off the notification email goes!
		sendmail($notification_list, $mailsubject, $mailmessage, null, 'p' . $id_pm, false, 2, null, true);
	}

	// Back to what we were on before!
	loadLanguage('index+PersonalMessage');

	// Add one to their unread and read message counts.
	foreach ($all_to as $k => $id)
		if (isset($deletes[$id]))
			unset($all_to[$k]);
	if (!empty($all_to))
		updateMemberData($all_to, array('instant_messages' => '+', 'unread_messages' => '+', 'new_pm' => 1));

	return $log;
}

// Prepare text strings for sending as email body or header.
function mimespecialchars($string, $with_charset = true, $hotmail_fix = false, $line_break = "\r\n")
{
	global $context;

	// This is the fun part....
	if (preg_match_all('~&#(\d{3,8});~', $string, $matches) !== 0 && !$hotmail_fix)
	{
		// Let's, for now, assume there are only &#021;'ish characters.
		$simple = true;

		foreach ($matches[1] as $entity)
			if ($entity > 128)
				$simple = false;
		unset($matches);

		if ($simple)
			$string = preg_replace('~&#(\d{3,8});~e', 'chr(\'$1\')', $string);
		else
		{
			$fixchar = create_function('$n', '
				if ($n < 128)
					return chr($n);
				elseif ($n < 2048)
					return chr(192 | $n >> 6) . chr(128 | $n & 63);
				elseif ($n < 65536)
					return chr(224 | $n >> 12) . chr(128 | $n >> 6 & 63) . chr(128 | $n & 63);
				else
					return chr(240 | $n >> 18) . chr(128 | $n >> 12 & 63) . chr(128 | $n >> 6 & 63) . chr(128 | $n & 63);');

			$string = preg_replace('~&#(\d{3,8});~e', '$fixchar(\'$1\')', $string);
		}
	}

	// Convert all special characters to HTML entities...just for Hotmail :-\
	if ($hotmail_fix)
	{
		$entityConvert = create_function('$c', '
			if (strlen($c) === 1 && ord($c[0]) <= 0x7F)
				return $c;
			elseif (strlen($c) === 2 && ord($c[0]) >= 0xC0 && ord($c[0]) <= 0xDF)
				return "&#" . (((ord($c[0]) ^ 0xC0) << 6) + (ord($c[1]) ^ 0x80)) . ";";
			elseif (strlen($c) === 3 && ord($c[0]) >= 0xE0 && ord($c[0]) <= 0xEF)
				return "&#" . (((ord($c[0]) ^ 0xE0) << 12) + ((ord($c[1]) ^ 0x80) << 6) + (ord($c[2]) ^ 0x80)) . ";";
			elseif (strlen($c) === 4 && ord($c[0]) >= 0xF0 && ord($c[0]) <= 0xF7)
				return "&#" . (((ord($c[0]) ^ 0xF0) << 18) + ((ord($c[1]) ^ 0x80) << 12) + ((ord($c[2]) ^ 0x80) << 6) + (ord($c[3]) ^ 0x80)) . ";";
			else
				return "";');

		// Convert all 'special' characters to HTML entities.
		return array('UTF-8', preg_replace('~([\x80-\x{10FFFF}])~eu', '$entityConvert(\'\1\')', $string), '7bit');
	}

	// We don't need to mess with the subject line if no special characters were in it..
	elseif (!$hotmail_fix && preg_match('~([^\x09\x0A\x0D\x20-\x7F])~', $string) === 1)
	{
		// Base64 encode.
		$string = base64_encode($string);

		// Show the characterset and the transfer-encoding for header strings.
		if ($with_charset)
			$string = '=?UTF-8?B?' . $string . '?=';

		// Break it up in lines (mail body).
		else
			$string = chunk_split($string, 76, $line_break);

		return array('UTF-8', $string, 'base64');
	}

	else
		return array('UTF-8', $string, '7bit');
}

// Send an email via SMTP.
function smtp_mail($mail_to_array, $subject, $message, $headers)
{
	global $modSettings, $webmaster_email, $txt;

	$modSettings['smtp_host'] = trim($modSettings['smtp_host']);

	// Try POP3 before SMTP?
	// !!! There's no interface for this yet.
	if ($modSettings['mail_type'] == 2 && $modSettings['smtp_username'] != '' && $modSettings['smtp_password'] != '')
	{
		$socket = fsockopen($modSettings['smtp_host'], 110, $errno, $errstr, 2);
		if (!$socket && (substr($modSettings['smtp_host'], 0, 5) == 'smtp.' || substr($modSettings['smtp_host'], 0, 11) == 'ssl://smtp.'))
			$socket = fsockopen(strtr($modSettings['smtp_host'], array('smtp.' => 'pop.')), 110, $errno, $errstr, 2);

		if ($socket)
		{
			fgets($socket, 256);
			fputs($socket, 'USER ' . $modSettings['smtp_username'] . "\r\n");
			fgets($socket, 256);
			fputs($socket, 'PASS ' . base64_decode($modSettings['smtp_password']) . "\r\n");
			fgets($socket, 256);
			fputs($socket, 'QUIT' . "\r\n");

			fclose($socket);
		}
	}

	// Try to connect to the SMTP server... if it doesn't exist, only wait three seconds.
	if (!$socket = fsockopen($modSettings['smtp_host'], empty($modSettings['smtp_port']) ? 25 : $modSettings['smtp_port'], $errno, $errstr, 3))
	{
		// Maybe we can still save this?  The port might be wrong.
		if (substr($modSettings['smtp_host'], 0, 4) == 'ssl:' && (empty($modSettings['smtp_port']) || $modSettings['smtp_port'] == 25))
		{
			if ($socket = fsockopen($modSettings['smtp_host'], 465, $errno, $errstr, 3))
				log_error($txt['smtp_port_ssl']);
		}

		// Unable to connect!  Don't show any error message, but just log one and try to continue anyway.
		if (!$socket)
		{
			log_error($txt['smtp_no_connect'] . ': ' . $errno . ' : ' . $errstr);
			return false;
		}
	}

	// Wait for a response of 220, without "-" continuer.
	if (!server_parse(null, $socket, '220'))
		return false;

	if ($modSettings['mail_type'] == 1 && $modSettings['smtp_username'] != '' && $modSettings['smtp_password'] != '')
	{
		// !!! These should send the CURRENT server's name, not the mail server's!

		// EHLO could be understood to mean encrypted hello...
		if (server_parse('EHLO ' . $modSettings['smtp_host'], $socket, null) == '250')
		{
			if (!server_parse('AUTH LOGIN', $socket, '334'))
				return false;
			// Send the username and password, encoded.
			if (!server_parse(base64_encode($modSettings['smtp_username']), $socket, '334'))
				return false;
			// The password is already encoded ;)
			if (!server_parse($modSettings['smtp_password'], $socket, '235'))
				return false;
		}
		elseif (!server_parse('HELO ' . $modSettings['smtp_host'], $socket, '250'))
			return false;
	}
	else
	{
		// Just say "helo".
		if (!server_parse('HELO ' . $modSettings['smtp_host'], $socket, '250'))
			return false;
	}

	// Fix the message for any lines beginning with a period! (the first is ignored, you see.)
	$message = strtr($message, array("\r\n" . '.' => "\r\n" . '..'));

	// !! Theoretically, we should be able to just loop the RCPT TO.
	$mail_to_array = array_values($mail_to_array);
	foreach ($mail_to_array as $i => $mail_to)
	{
		// Reset the connection to send another email.
		if ($i != 0)
		{
			if (!server_parse('RSET', $socket, '250'))
				return false;
		}

		// From, to, and then start the data...
		if (!server_parse('MAIL FROM: <' . (empty($modSettings['mail_from']) ? $webmaster_email : $modSettings['mail_from']) . '>', $socket, '250'))
			return false;
		if (!server_parse('RCPT TO: <' . $mail_to . '>', $socket, '250'))
			return false;
		if (!server_parse('DATA', $socket, '354'))
			return false;
		fputs($socket, 'Subject: ' . $subject . "\r\n");
		if (strlen($mail_to) > 0)
			fputs($socket, 'To: <' . $mail_to . '>' . "\r\n");
		fputs($socket, $headers . "\r\n\r\n");
		fputs($socket, $message . "\r\n");

		// Send a ., or in other words "end of data".
		if (!server_parse('.', $socket, '250'))
			return false;

		// Almost done, almost done... don't stop me just yet!
		@set_time_limit(300);
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();
	}
	fputs($socket, 'QUIT' . "\r\n");
	fclose($socket);

	return true;
}

// Parse a message to the SMTP server.
function server_parse($message, $socket, $response)
{
	global $txt;

	if ($message !== null)
		fputs($socket, $message . "\r\n");

	// No response yet.
	$server_response = '';

	while (substr($server_response, 3, 1) != ' ')
		if (!($server_response = fgets($socket, 256)))
		{
			// !!! Change this message to reflect that it may mean bad user/password/server issues/etc.
			log_error($txt['smtp_bad_response']);
			return false;
		}

	if ($response === null)
		return substr($server_response, 0, 3);

	if (substr($server_response, 0, 3) != $response)
	{
		log_error($txt['smtp_error'] . $server_response);
		return false;
	}

	return true;
}

// Notify members that something has happened to a topic they marked!
function sendNotifications($topics, $type, $exclude = array(), $members_only = array())
{
	global $txt, $scripturl, $language, $user_info;
	global $modSettings, $context;

	// Can't do it if there's no topics.
	if (empty($topics))
		return;
	// It must be an array - it must!
	if (!is_array($topics))
		$topics = array($topics);

	// Get the subject and body...
	$result = wesql::query('
		SELECT mf.subject, ml.body, ml.id_member, t.id_last_msg, t.id_topic,
			IFNULL(mem.real_name, ml.poster_name) AS poster_name
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ml.id_member)
		WHERE t.id_topic IN ({array_int:topic_list})
		LIMIT 1',
		array(
			'topic_list' => $topics,
		)
	);
	$topicData = array();
	while ($row = wesql::fetch_assoc($result))
	{
		// Clean it up.
		censorText($row['subject']);
		censorText($row['body']);
		$row['subject'] = un_htmlspecialchars($row['subject']);
		$row['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($row['body'], false, $row['id_last_msg']), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

		$topicData[$row['id_topic']] = array(
			'subject' => $row['subject'],
			'body' => $row['body'],
			'last_id' => $row['id_last_msg'],
			'topic' => $row['id_topic'],
			'name' => $user_info['name'],
			'exclude' => '',
		);
	}
	wesql::free_result($result);

	// Work out any exclusions...
	foreach ($topics as $key => $id)
		if (isset($topicData[$id]) && !empty($exclude[$key]))
			$topicData[$id]['exclude'] = (int) $exclude[$key];

	// Nada?
	if (empty($topicData))
		trigger_error('sendNotifications(): topics not found', E_USER_NOTICE);

	$topics = array_keys($topicData);
	// Just in case they've gone walkies.
	if (empty($topics))
		return;

	// Insert all of these items into the digest log for those who want notifications later.
	$digest_insert = array();
	foreach ($topicData as $id => $data)
		$digest_insert[] = array($data['topic'], $data['last_id'], $type, (int) $data['exclude']);
	wesql::insert('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Find the members with notification on for this topic.
	$members = wesql::query('
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile,
			ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started,
			ln.id_topic
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ln.id_topic IN ({array_int:topic_list})
			AND mem.notify_types < {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
			AND mem.is_activated = {int:is_activated}
			AND ln.id_member != {int:current_member}' .
			(empty($members_only) ? '' : ' AND ln.id_member IN ({array_int:members_only})') . '
		ORDER BY mem.lngfile',
		array(
			'current_member' => $user_info['id'],
			'topic_list' => $topics,
			'notify_types' => $type == 'reply' ? '4' : '3',
			'notify_regularity' => 2,
			'is_activated' => 1,
			'members_only' => is_array($members_only) ? $members_only : array($members_only),
		)
	);
	$sent = 0;
	while ($row = wesql::fetch_assoc($members))
	{
		// Don't do the excluded...
		if ($topicData[$row['id_topic']]['exclude'] == $row['id_member'])
			continue;

		// Easier to check this here... if they aren't the topic poster do they really want to know?
		if ($type != 'reply' && $row['notify_types'] == 2 && $row['id_member'] != $row['id_member_started'])
			continue;

		if ($row['id_group'] != 1)
		{
			$allowed = explode(',', $row['member_groups']);
			$row['additional_groups'] = explode(',', $row['additional_groups']);
			$row['additional_groups'][] = $row['id_group'];
			$row['additional_groups'][] = $row['id_post_group'];

			if (count(array_intersect($allowed, $row['additional_groups'])) == 0)
				continue;
		}

		$needed_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
		if (empty($current_language) || $current_language != $needed_language)
			$current_language = loadLanguage('Post', $needed_language, false);

		$message_type = 'notification_' . $type;
		$replacements = array(
			'TOPICSUBJECT' => $topicData[$row['id_topic']]['subject'],
			'POSTERNAME' => un_htmlspecialchars($topicData[$row['id_topic']]['name']),
			'TOPICLINK' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
			'UNSUBSCRIBELINK' => $scripturl . '?action=notify;topic=' . $row['id_topic'] . '.0',
		);

		if ($type == 'remove')
			unset($replacements['TOPICLINK'], $replacements['UNSUBSCRIBELINK']);
		// Do they want the body of the message sent too?
		if (!empty($row['notify_send_body']) && $type == 'reply' && empty($modSettings['disallow_sendBody']))
		{
			$message_type .= '_body';
			$replacements['MESSAGE'] = $topicData[$row['id_topic']]['body'];
		}
		if (!empty($row['notify_regularity']) && $type == 'reply')
			$message_type .= '_once';

		// Send only if once is off or it's on and it hasn't been sent.
		if ($type != 'reply' || empty($row['notify_regularity']) || empty($row['sent']))
		{
			$emaildata = loadEmailTemplate($message_type, $replacements, $needed_language);
			sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicData[$row['id_topic']]['last_id']);
			$sent++;
		}
	}
	wesql::free_result($members);

	if (isset($current_language) && $current_language != $user_info['language'])
		loadLanguage('Post');

	// Sent!
	if ($type == 'reply' && !empty($sent))
		wesql::query('
			UPDATE {db_prefix}log_notify
			SET sent = {int:is_sent}
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member != {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'topic_list' => $topics,
				'is_sent' => 1,
			)
		);

	// For approvals we need to unsend the exclusions (This *is* the quickest way!)
	if (!empty($sent) && !empty($exclude))
	{
		foreach ($topicData as $id => $data)
			if ($data['exclude'])
				wesql::query('
					UPDATE {db_prefix}log_notify
					SET sent = {int:not_sent}
					WHERE id_topic = {int:id_topic}
						AND id_member = {int:id_member}',
					array(
						'not_sent' => 0,
						'id_topic' => $id,
						'id_member' => $data['exclude'],
					)
				);
	}
}

// Create a post, either as new topic (id_topic = 0) or in an existing one.
// The input parameters of this function assume:
// - Strings have been escaped.
// - Integers have been cast to integer.
// - Mandatory parameters are set.
function createPost(&$msgOptions, &$topicOptions, &$posterOptions)
{
	global $user_info, $txt, $modSettings, $context;

	// Set optional parameters to the default value.
	$msgOptions['icon'] = empty($msgOptions['icon']) ? 'xx' : $msgOptions['icon'];
	$msgOptions['smileys_enabled'] = !empty($msgOptions['smileys_enabled']);
	$msgOptions['attachments'] = empty($msgOptions['attachments']) ? array() : $msgOptions['attachments'];
	$msgOptions['approved'] = isset($msgOptions['approved']) ? (int) $msgOptions['approved'] : 1;
	$topicOptions['id'] = empty($topicOptions['id']) ? 0 : (int) $topicOptions['id'];
	$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
	$topicOptions['lock_mode'] = isset($topicOptions['lock_mode']) ? $topicOptions['lock_mode'] : null;
	$topicOptions['sticky_mode'] = isset($topicOptions['sticky_mode']) ? $topicOptions['sticky_mode'] : null;
	$posterOptions['id'] = empty($posterOptions['id']) ? 0 : (int) $posterOptions['id'];
	$posterOptions['ip'] = empty($posterOptions['ip']) ? $user_info['ip'] : $posterOptions['ip'];

	// We need to know if the topic is approved. If we're told that's great - if not find out.
	if (!$modSettings['postmod_active'])
		$topicOptions['is_approved'] = true;
	elseif (!empty($topicOptions['id']) && !isset($topicOptions['is_approved']))
	{
		$request = wesql::query('
			SELECT approved
			FROM {db_prefix}topics
			WHERE id_topic = {int:id_topic}
			LIMIT 1',
			array(
				'id_topic' => $topicOptions['id'],
			)
		);
		list ($topicOptions['is_approved']) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	// If nothing was filled in as name/e-mail address, try the member table.
	if (!isset($posterOptions['name']) || $posterOptions['name'] == '' || (empty($posterOptions['email']) && !empty($posterOptions['id'])))
	{
		if (empty($posterOptions['id']))
		{
			$posterOptions['id'] = 0;
			$posterOptions['name'] = $txt['guest_title'];
			$posterOptions['email'] = '';
		}
		elseif ($posterOptions['id'] != $user_info['id'])
		{
			$request = wesql::query('
				SELECT member_name, email_address
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $posterOptions['id'],
				)
			);
			// Couldn't find the current poster?
			if (wesql::num_rows($request) == 0)
			{
				trigger_error('createPost(): Invalid member id ' . $posterOptions['id'], E_USER_NOTICE);
				$posterOptions['id'] = 0;
				$posterOptions['name'] = $txt['guest_title'];
				$posterOptions['email'] = '';
			}
			else
				list ($posterOptions['name'], $posterOptions['email']) = wesql::fetch_row($request);
			wesql::free_result($request);
		}
		else
		{
			$posterOptions['name'] = $user_info['name'];
			$posterOptions['email'] = $user_info['email'];
		}
	}

	$new_topic = empty($topicOptions['id']);

	// Does an add-on want to manipulate all new posts/topics before they're created?
	// (e.g. check for user agent and change the icon based on that.)
	call_hook('create_post_before', array(&$msgOptions, &$topicOptions, &$posterOptions, &$new_topic));

	// It's do or die time: forget any user aborts!
	$previous_ignore_user_abort = ignore_user_abort(true);

	// Insert the post.
	wesql::insert('',
		'{db_prefix}messages',
		array(
			'id_board' => 'int', 'id_topic' => 'int', 'id_member' => 'int', 'subject' => 'string-255', 'body' => (!empty($modSettings['max_messageLength']) && $modSettings['max_messageLength'] > 65534 ? 'string-' . $modSettings['max_messageLength'] : 'string-65534'),
			'poster_name' => 'string-255', 'poster_email' => 'string-255', 'poster_time' => 'int', 'poster_ip' => 'int',
			'smileys_enabled' => 'int', 'modified_name' => 'string', 'icon' => 'string-16', 'approved' => 'int',
		),
		array(
			$topicOptions['board'], $topicOptions['id'], $posterOptions['id'], $msgOptions['subject'], $msgOptions['body'],
			$posterOptions['name'], $posterOptions['email'], time(), get_ip_identifier($posterOptions['ip']),
			$msgOptions['smileys_enabled'] ? 1 : 0, '', $msgOptions['icon'], $msgOptions['approved'],
		),
		array('id_msg')
	);
	$msgOptions['id'] = wesql::insert_id();

	// Something went wrong creating the message...
	if (empty($msgOptions['id']))
		return false;

	// Fix the attachments.
	if (!empty($msgOptions['attachments']))
		wesql::query('
			UPDATE {db_prefix}attachments
			SET id_msg = {int:id_msg}
			WHERE id_attach IN ({array_int:attachment_list})',
			array(
				'attachment_list' => $msgOptions['attachments'],
				'id_msg' => $msgOptions['id'],
			)
		);

	// Insert a new topic (if the topicID was left empty.)
	if ($new_topic)
	{
		wesql::insert('',
			'{db_prefix}topics',
			array(
				'id_board' => 'int', 'id_member_started' => 'int', 'id_member_updated' => 'int', 'id_first_msg' => 'int',
				'id_last_msg' => 'int', 'locked' => 'int', 'is_sticky' => 'int', 'num_views' => 'int',
				'id_poll' => 'int', 'unapproved_posts' => 'int', 'approved' => 'int',
			),
			array(
				$topicOptions['board'], $posterOptions['id'], $posterOptions['id'], $msgOptions['id'],
				$msgOptions['id'], $topicOptions['lock_mode'] === null ? 0 : $topicOptions['lock_mode'], $topicOptions['sticky_mode'] === null ? 0 : $topicOptions['sticky_mode'], 0,
				$topicOptions['poll'] === null ? 0 : $topicOptions['poll'], $msgOptions['approved'] ? 0 : 1, $msgOptions['approved'],
			),
			array('id_topic')
		);
		$topicOptions['id'] = wesql::insert_id();

		// The topic couldn't be created for some reason.
		if (empty($topicOptions['id']))
		{
			// We should delete the post that did work, though...
			wesql::query('
				DELETE FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				array(
					'id_msg' => $msgOptions['id'],
				)
			);

			return false;
		}

		// Fix the message with the topic.
		wesql::query('
			UPDATE {db_prefix}messages
			SET id_topic = {int:id_topic}
			WHERE id_msg = {int:id_msg}',
			array(
				'id_topic' => $topicOptions['id'],
				'id_msg' => $msgOptions['id'],
			)
		);

		// There's been a new topic AND a new post today.
		trackStats(array('topics' => '+', 'posts' => '+'));

		updateStats('topic', true);
		updateStats('subject', $topicOptions['id'], $msgOptions['subject']);
	}
	// The topic already exists, it only needs a little updating.
	else
	{
		$countChange = $msgOptions['approved'] ? 'num_replies = num_replies + 1' : 'unapproved_posts = unapproved_posts + 1';

		// Update the number of replies and the lock/sticky status.
		wesql::query('
			UPDATE {db_prefix}topics
			SET
				' . ($msgOptions['approved'] ? 'id_member_updated = {int:poster_id}, id_last_msg = {int:id_msg},' : '') . '
				' . $countChange . ($topicOptions['lock_mode'] === null ? '' : ',
				locked = {int:locked}') . ($topicOptions['sticky_mode'] === null ? '' : ',
				is_sticky = {int:is_sticky}') . '
			WHERE id_topic = {int:id_topic}',
			array(
				'poster_id' => $posterOptions['id'],
				'id_msg' => $msgOptions['id'],
				'locked' => $topicOptions['lock_mode'],
				'is_sticky' => $topicOptions['sticky_mode'],
				'id_topic' => $topicOptions['id'],
			)
		);

		// One new post has been added today.
		trackStats(array('posts' => '+'));

		// Merging a double post...
		if (!empty($modSettings['merge_post_auto']) && !($user_info['is_admin'] && empty($modSettings['merge_post_admin_double_post'])))
		{
			$_REQUEST['msgid'] = $msgOptions['id'];
			$_REQUEST['pid'] = $msgOptions['id'];
			$_REQUEST['topic'] = $topicOptions['id'];
			loadSource('SplitTopics');
			MergePosts(false);
		}
	}

	// Creating is modifying...in a way.
	// !!! Why not set id_msg_modified on the insert?
	wesql::query('
		UPDATE {db_prefix}messages
		SET id_msg_modified = {int:id_msg}
		WHERE id_msg = {int:id_msg}',
		array(
			'id_msg' => $msgOptions['id'],
		)
	);

	// Increase the number of posts and topics on the board.
	if ($msgOptions['approved'])
		wesql::query('
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + 1' . ($new_topic ? ', num_topics = num_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);
	else
	{
		wesql::query('
			UPDATE {db_prefix}boards
			SET unapproved_posts = unapproved_posts + 1' . ($new_topic ? ', unapproved_topics = unapproved_topics + 1' : '') . '
			WHERE id_board = {int:id_board}',
			array(
				'id_board' => $topicOptions['board'],
			)
		);

		// Add to the approval queue too.
		wesql::insert('',
			'{db_prefix}approval_queue',
			array(
				'id_msg' => 'int',
			),
			array(
				$msgOptions['id'],
			),
			array()
		);
	}

	// Mark inserted topic as read (only for the user calling this function).
	if (!empty($topicOptions['mark_as_read']) && !$user_info['is_guest'])
	{
		// Since it's likely they *read* it before replying, let's try an UPDATE first.
		if (!$new_topic)
		{
			wesql::query('
				UPDATE {db_prefix}log_topics
				SET id_msg = {int:id_msg}
				WHERE id_member = {int:current_member}
					AND id_topic = {int:id_topic}',
				array(
					'current_member' => $posterOptions['id'],
					'id_msg' => $msgOptions['id'],
					'id_topic' => $topicOptions['id'],
				)
			);

			$flag = wesql::affected_rows() != 0;
		}

		if (empty($flag))
		{
			wesql::insert('ignore',
				'{db_prefix}log_topics',
				array('id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
				array($topicOptions['id'], $posterOptions['id'], $msgOptions['id']),
				array('id_topic', 'id_member')
			);
		}
	}

	// If there's a custom search index, it needs updating...
	if (!empty($modSettings['search_custom_index_config']))
	{
		$customIndexSettings = unserialize($modSettings['search_custom_index_config']);

		$inserts = array();
		foreach (text2words($msgOptions['body'], $customIndexSettings['bytes_per_word'], true) as $word)
			$inserts[] = array($word, $msgOptions['id']);

		if (!empty($inserts))
			wesql::insert('ignore',
				'{db_prefix}log_search_words',
				array('id_word' => 'int', 'id_msg' => 'int'),
				$inserts,
				array('id_word', 'id_msg')
			);
	}

	// Increase the post counter for the user that created the post.
	if (!empty($posterOptions['update_post_count']) && !empty($posterOptions['id']) && $msgOptions['approved'])
	{
		// Are you the one that happened to create this post?
		if ($user_info['id'] == $posterOptions['id'])
			$user_info['posts']++;
		updateMemberData($posterOptions['id'], array('posts' => '+'));
	}

	// They've posted, so they can make the view count go up one if they really want. (this is to keep views >= replies...)
	$_SESSION['last_read_topic'] = 0;

	// Better safe than sorry.
	if (isset($_SESSION['topicseen_cache'][$topicOptions['board']]))
		$_SESSION['topicseen_cache'][$topicOptions['board']]--;

	// Update all the stats so everyone knows about this new topic and message.
	updateStats('message', true, $msgOptions['id']);

	// Update the last message on the board assuming it's approved AND the topic is.
	if ($msgOptions['approved'])
		updateLastMessages($topicOptions['board'], $new_topic || !empty($topicOptions['is_approved']) ? $msgOptions['id'] : 0);

	// Alright, done now... we can abort now, I guess... at least this much is done.
	ignore_user_abort($previous_ignore_user_abort);

	// What if we want to export new posts/topics out to a CMS?
	call_hook('create_post_after', array(&$msgOptions, &$topicOptions, &$posterOptions, &$new_topic));

	// Success.
	return true;
}

// !!! @todo: replace with Media Gallery
function createAttachment(&$attachmentOptions)
{
	global $modSettings, $context;

	loadSource('Subs-Graphics');

	// We need to know where this thing is going.
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);

		// Just use the current path for temp files.
		$attach_dir = $modSettings['attachmentUploadDir'][$modSettings['currentAttachmentUploadDir']];
		$id_folder = $modSettings['currentAttachmentUploadDir'];
	}
	else
	{
		$attach_dir = $modSettings['attachmentUploadDir'];
		$id_folder = 1;
	}

	$attachmentOptions['errors'] = array();
	if (!isset($attachmentOptions['post']))
		$attachmentOptions['post'] = 0;
	if (!isset($attachmentOptions['approved']))
		$attachmentOptions['approved'] = 1;

	$already_uploaded = preg_match('~^post_tmp_' . $attachmentOptions['poster'] . '_\d+$~', $attachmentOptions['tmp_name']) != 0;
	$file_restricted = @ini_get('open_basedir') != '' && !$already_uploaded;

	if ($already_uploaded)
		$attachmentOptions['tmp_name'] = $attach_dir . '/' . $attachmentOptions['tmp_name'];

	// Make sure the file actually exists... sometimes it doesn't.
	if ((!$file_restricted && !file_exists($attachmentOptions['tmp_name'])) || (!$already_uploaded && !is_uploaded_file($attachmentOptions['tmp_name'])))
	{
		$attachmentOptions['errors'] = array('could_not_upload');
		return false;
	}

	// These are the only valid image types for SMF.
	$validImageTypes = array(
		1 => 'gif',
		2 => 'jpeg',
		3 => 'png',
		5 => 'psd',
		6 => 'bmp',
		7 => 'tiff',
		8 => 'tiff',
		9 => 'jpeg',
		14 => 'iff'
	);

	if (!$file_restricted || $already_uploaded)
	{
		$size = @getimagesize($attachmentOptions['tmp_name']);
		list ($attachmentOptions['width'], $attachmentOptions['height']) = $size;

		// If it's an image get the mime type right.
		if (empty($attachmentOptions['mime_type']) && $attachmentOptions['width'])
		{
			// Got a proper mime type?
			if (!empty($size['mime']))
				$attachmentOptions['mime_type'] = $size['mime'];
			// Otherwise a valid one?
			elseif (isset($validImageTypes[$size[2]]))
				$attachmentOptions['mime_type'] = 'image/' . $validImageTypes[$size[2]];
		}
	}

	// Get the hash if no hash has been given yet.
	if (empty($attachmentOptions['file_hash']))
		$attachmentOptions['file_hash'] = getAttachmentFilename($attachmentOptions['name'], false, null, true);

	// Is the file too big?
	if (!empty($modSettings['attachmentSizeLimit']) && $attachmentOptions['size'] > $modSettings['attachmentSizeLimit'] * 1024)
		$attachmentOptions['errors'][] = 'too_large';

	if (!empty($modSettings['attachmentCheckExtensions']))
	{
		$allowed = explode(',', strtolower($modSettings['attachmentExtensions']));
		foreach ($allowed as $k => $dummy)
			$allowed[$k] = trim($dummy);

		if (!in_array(strtolower(substr(strrchr($attachmentOptions['name'], '.'), 1)), $allowed))
			$attachmentOptions['errors'][] = 'bad_extension';
	}

	if (!empty($modSettings['attachmentDirSizeLimit']))
	{
		// Make sure the directory isn't full.
		$dirSize = 0;
		$dir = @scandir($attach_dir) or fatal_lang_error('cant_access_upload_path', 'critical');
		foreach ($dir as $file)
		{
			if ($file == '.' || $file == '..')
				continue;

			if (preg_match('~^post_tmp_\d+_\d+$~', $file) != 0)
			{
				// Temp file is more than 5 hours old!
				if (filemtime($attach_dir . '/' . $file) < time() - 18000)
					@unlink($attach_dir . '/' . $file);
				continue;
			}

			$dirSize += filesize($attach_dir . '/' . $file);
		}

		// Too big!  Maybe you could zip it or something...
		if ($attachmentOptions['size'] + $dirSize > $modSettings['attachmentDirSizeLimit'] * 1024)
			$attachmentOptions['errors'][] = 'directory_full';
		// Soon to be too big - warn the admins...
		elseif (!isset($modSettings['attachment_full_notified']) && $modSettings['attachmentDirSizeLimit'] > 4000 && $attachmentOptions['size'] + $dirSize > ($modSettings['attachmentDirSizeLimit'] - 2000) * 1024)
		{
			loadSource('Subs-Admin');
			emailAdmins('admin_attachments_full');
			updateSettings(array('attachment_full_notified' => 1));
		}
	}

	// Check if the file already exists.... (for those who do not encrypt their filenames...)
	if (empty($modSettings['attachmentEncryptFilenames']))
	{
		// Make sure they aren't trying to upload a nasty file.
		$disabledFiles = array('con', 'com1', 'com2', 'com3', 'com4', 'prn', 'aux', 'lpt1', '.htaccess', 'index.php');
		if (in_array(strtolower(basename($attachmentOptions['name'])), $disabledFiles))
			$attachmentOptions['errors'][] = 'bad_filename';

		// Check if there's another file with that name...
		$request = wesql::query('
			SELECT id_attach
			FROM {db_prefix}attachments
			WHERE filename = {string:filename}
			LIMIT 1',
			array(
				'filename' => strtolower($attachmentOptions['name']),
			)
		);
		if (wesql::num_rows($request) > 0)
			$attachmentOptions['errors'][] = 'taken_filename';
		wesql::free_result($request);
	}

	if (!empty($attachmentOptions['errors']))
		return false;

	if (!is_writable($attach_dir))
		fatal_lang_error('attachments_no_write', 'critical');

	// Assuming no-one set the extension let's take a look at it.
	if (empty($attachmentOptions['fileext']))
	{
		$attachmentOptions['fileext'] = strtolower(strrpos($attachmentOptions['name'], '.') !== false ? substr($attachmentOptions['name'], strrpos($attachmentOptions['name'], '.') + 1) : '');
		if (strlen($attachmentOptions['fileext']) > 8 || '.' . $attachmentOptions['fileext'] == $attachmentOptions['name'])
			$attachmentOptions['fileext'] = '';
	}

	wesql::insert('',
		'{db_prefix}attachments',
		array(
			'id_folder' => 'int', 'id_msg' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'fileext' => 'string-8',
			'size' => 'int', 'width' => 'int', 'height' => 'int',
			'mime_type' => 'string-20', 'approved' => 'int',
		),
		array(
			$id_folder, (int) $attachmentOptions['post'], $attachmentOptions['name'], $attachmentOptions['file_hash'], $attachmentOptions['fileext'],
			(int) $attachmentOptions['size'], (empty($attachmentOptions['width']) ? 0 : (int) $attachmentOptions['width']), (empty($attachmentOptions['height']) ? '0' : (int) $attachmentOptions['height']),
			(!empty($attachmentOptions['mime_type']) ? $attachmentOptions['mime_type'] : ''), (int) $attachmentOptions['approved'],
		),
		array('id_attach')
	);
	$attachmentOptions['id'] = wesql::insert_id();

	if (empty($attachmentOptions['id']))
		return false;

	// If it's not approved add to the approval queue.
	if (!$attachmentOptions['approved'])
		wesql::insert('',
			'{db_prefix}approval_queue',
			array(
				'id_attach' => 'int', 'id_msg' => 'int',
			),
			array(
				$attachmentOptions['id'], (int) $attachmentOptions['post'],
			),
			array()
		);

	$attachmentOptions['destination'] = getAttachmentFilename(basename($attachmentOptions['name']), $attachmentOptions['id'], $id_folder, false, $attachmentOptions['file_hash']);

	if ($already_uploaded)
		rename($attachmentOptions['tmp_name'], $attachmentOptions['destination']);
	elseif (!move_uploaded_file($attachmentOptions['tmp_name'], $attachmentOptions['destination']))
		fatal_lang_error('attach_timeout', 'critical');

	// Attempt to chmod it.
	@chmod($attachmentOptions['destination'], 0644);

	$size = @getimagesize($attachmentOptions['destination']);
	list ($attachmentOptions['width'], $attachmentOptions['height']) = empty($size) ? array(null, null, null) : $size;

	// We couldn't access the file before...
	if ($file_restricted)
	{
		// Have a go at getting the right mime type.
		if (empty($attachmentOptions['mime_type']) && $attachmentOptions['width'])
		{
			if (!empty($size['mime']))
				$attachmentOptions['mime_type'] = $size['mime'];
			elseif (isset($validImageTypes[$size[2]]))
				$attachmentOptions['mime_type'] = 'image/' . $validImageTypes[$size[2]];
		}

		if (!empty($attachmentOptions['width']) && !empty($attachmentOptions['height']))
			wesql::query('
				UPDATE {db_prefix}attachments
				SET
					width = {int:width},
					height = {int:height},
					mime_type = {string:mime_type}
				WHERE id_attach = {int:id_attach}',
				array(
					'width' => (int) $attachmentOptions['width'],
					'height' => (int) $attachmentOptions['height'],
					'id_attach' => $attachmentOptions['id'],
					'mime_type' => empty($attachmentOptions['mime_type']) ? '' : $attachmentOptions['mime_type'],
				)
			);
	}

	// Security checks for images
	// Do we have an image? If yes, we need to check it out!
	if (isset($validImageTypes[$size[2]]))
	{
		if (!checkImageContents($attachmentOptions['destination'], !empty($modSettings['attachment_image_paranoid'])))
		{
			// It's bad. Last chance, maybe we can re-encode it?
			if (empty($modSettings['attachment_image_reencode']) || (!reencodeImage($attachmentOptions['destination'], $size[2])))
			{
				// Nothing to do: not allowed or not successful re-encoding it.
				loadSource('ManageAttachments');
				removeAttachments(array(
					'id_attach' => $attachmentOptions['id']
				));
				$attachmentOptions['id'] = null;
				$attachmentOptions['errors'][] = 'bad_attachment';

				return false;
			}
			// Success! However, successes usually come for a price:
			// we might get a new format for our image...
			$old_format = $size[2];
			$size = @getimagesize($attachmentOptions['destination']);
			if (!(empty($size)) && ($size[2] != $old_format))
			{
				// Let's update the image information
				// !!! This is becoming a mess: we keep coming back and update the database,
				//  instead of getting it right the first time.
				if (isset($validImageTypes[$size[2]]))
				{
					$attachmentOptions['mime_type'] = 'image/' . $validImageTypes[$size[2]];
					wesql::query('
						UPDATE {db_prefix}attachments
						SET
							mime_type = {string:mime_type}
						WHERE id_attach = {int:id_attach}',
						array(
							'id_attach' => $attachmentOptions['id'],
							'mime_type' => $attachmentOptions['mime_type'],
						)
					);
				}
			}
		}
	}

	if (!empty($attachmentOptions['skip_thumbnail']) || (empty($attachmentOptions['width']) && empty($attachmentOptions['height'])))
		return true;

	// Like thumbnails, do we?
	if (!empty($modSettings['attachmentThumbnails']) && !empty($modSettings['attachmentThumbWidth']) && !empty($modSettings['attachmentThumbHeight']) && ($attachmentOptions['width'] > $modSettings['attachmentThumbWidth'] || $attachmentOptions['height'] > $modSettings['attachmentThumbHeight']))
	{
		if (createThumbnail($attachmentOptions['destination'], $modSettings['attachmentThumbWidth'], $modSettings['attachmentThumbHeight']))
		{
			// Figure out how big we actually made it.
			$size = @getimagesize($attachmentOptions['destination'] . '_thumb');
			list ($thumb_width, $thumb_height) = $size;

			if (!empty($size['mime']))
				$thumb_mime = $size['mime'];
			elseif (isset($validImageTypes[$size[2]]))
				$thumb_mime = 'image/' . $validImageTypes[$size[2]];
			// Lord only knows how this happened...
			else
				$thumb_mime = '';

			$thumb_filename = $attachmentOptions['name'] . '_thumb';
			$thumb_size = filesize($attachmentOptions['destination'] . '_thumb');
			$thumb_file_hash = getAttachmentFilename($thumb_filename, false, null, true);

			// To the database we go!
			wesql::insert('',
				'{db_prefix}attachments',
				array(
					'id_folder' => 'int', 'id_msg' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-40', 'fileext' => 'string-8',
					'size' => 'int', 'width' => 'int', 'height' => 'int', 'mime_type' => 'string-20', 'approved' => 'int',
				),
				array(
					$id_folder, (int) $attachmentOptions['post'], 3, $thumb_filename, $thumb_file_hash, $attachmentOptions['fileext'],
					$thumb_size, $thumb_width, $thumb_height, $thumb_mime, (int) $attachmentOptions['approved'],
				),
				array('id_attach')
			);
			$attachmentOptions['thumb'] = wesql::insert_id();

			if (!empty($attachmentOptions['thumb']))
			{
				wesql::query('
					UPDATE {db_prefix}attachments
					SET id_thumb = {int:id_thumb}
					WHERE id_attach = {int:id_attach}',
					array(
						'id_thumb' => $attachmentOptions['thumb'],
						'id_attach' => $attachmentOptions['id'],
					)
				);

				rename($attachmentOptions['destination'] . '_thumb', getAttachmentFilename($thumb_filename, $attachmentOptions['thumb'], $id_folder, false, $thumb_file_hash));
			}
		}
	}

	return true;
}

// !!!
function modifyPost(&$msgOptions, &$topicOptions, &$posterOptions)
{
	global $user_info, $modSettings, $context;

	$topicOptions['poll'] = isset($topicOptions['poll']) ? (int) $topicOptions['poll'] : null;
	$topicOptions['lock_mode'] = isset($topicOptions['lock_mode']) ? $topicOptions['lock_mode'] : null;
	$topicOptions['sticky_mode'] = isset($topicOptions['sticky_mode']) ? $topicOptions['sticky_mode'] : null;

	// Does an add-on want to manipulate posts/topics before they're modified?
	call_hook('modify_post_before', array(&$msgOptions, &$topicOptions, &$posterOptions));

	// This is longer than it has to be, but makes it so we only set/change what we have to.
	$messages_columns = array();
	if (isset($posterOptions['name']))
		$messages_columns['poster_name'] = $posterOptions['name'];
	if (isset($posterOptions['email']))
		$messages_columns['poster_email'] = $posterOptions['email'];
	if (isset($msgOptions['icon']))
		$messages_columns['icon'] = $msgOptions['icon'];
	if (isset($msgOptions['subject']))
		$messages_columns['subject'] = $msgOptions['subject'];
	if (isset($msgOptions['body']))
	{
		$messages_columns['body'] = $msgOptions['body'];

		if (!empty($modSettings['search_custom_index_config']))
		{
			$request = wesql::query('
				SELECT body
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}',
				array(
					'id_msg' => $msgOptions['id'],
				)
			);
			list ($old_body) = wesql::fetch_row($request);
			wesql::free_result($request);
		}
	}
	if (!empty($msgOptions['modify_time']))
	{
		$messages_columns['modified_time'] = $msgOptions['modify_time'];
		$messages_columns['modified_name'] = $msgOptions['modify_name'];
		$messages_columns['id_msg_modified'] = $modSettings['maxMsgID'];
	}
	if (isset($msgOptions['smileys_enabled']))
		$messages_columns['smileys_enabled'] = empty($msgOptions['smileys_enabled']) ? 0 : 1;

	// Which columns need to be ints?
	$messageInts = array('modified_time', 'id_msg_modified', 'smileys_enabled');
	$update_parameters = array(
		'id_msg' => $msgOptions['id'],
	);

	foreach ($messages_columns as $var => $val)
	{
		$messages_columns[$var] = $var . ' = {' . (in_array($var, $messageInts) ? 'int' : 'string') . ':var_' . $var . '}';
		$update_parameters['var_' . $var] = $val;
	}

	// Lock and or sticky the post.
	if ($topicOptions['sticky_mode'] !== null || $topicOptions['lock_mode'] !== null || $topicOptions['poll'] !== null)
	{
		wesql::query('
			UPDATE {db_prefix}topics
			SET
				is_sticky = {raw:is_sticky},
				locked = {raw:locked},
				id_poll = {raw:id_poll}
			WHERE id_topic = {int:id_topic}',
			array(
				'is_sticky' => $topicOptions['sticky_mode'] === null ? 'is_sticky' : (int) $topicOptions['sticky_mode'],
				'locked' => $topicOptions['lock_mode'] === null ? 'locked' : (int) $topicOptions['lock_mode'],
				'id_poll' => $topicOptions['poll'] === null ? 'id_poll' : (int) $topicOptions['poll'],
				'id_topic' => $topicOptions['id'],
			)
		);
	}

	// Nothing to do?
	if (empty($messages_columns))
		return true;

	// Change the post.
	wesql::query('
		UPDATE {db_prefix}messages
		SET ' . implode(', ', $messages_columns) . '
		WHERE id_msg = {int:id_msg}',
		$update_parameters
	);

	// Mark the edited post as read.
	if (!empty($topicOptions['mark_as_read']) && !$user_info['is_guest'])
	{
		// Since it's likely they *read* it before editing, let's try an UPDATE first.
		wesql::query('
			UPDATE {db_prefix}log_topics
			SET id_msg = {int:id_msg}
			WHERE id_member = {int:current_member}
				AND id_topic = {int:id_topic}',
			array(
				'current_member' => $user_info['id'],
				'id_msg' => $modSettings['maxMsgID'],
				'id_topic' => $topicOptions['id'],
			)
		);

		$flag = wesql::affected_rows() != 0;

		if (empty($flag))
		{
			wesql::insert('ignore',
				'{db_prefix}log_topics',
				array('id_topic' => 'int', 'id_member' => 'int', 'id_msg' => 'int'),
				array($topicOptions['id'], $user_info['id'], $modSettings['maxMsgID']),
				array('id_topic', 'id_member')
			);
		}
	}

	// If there's a custom search index, it needs to be modified...
	if (isset($msgOptions['body']) && !empty($modSettings['search_custom_index_config']))
	{
		$customIndexSettings = unserialize($modSettings['search_custom_index_config']);

		$stopwords = empty($modSettings['search_stopwords']) ? array() : explode(',', $modSettings['search_stopwords']);
		$old_index = text2words($old_body, $customIndexSettings['bytes_per_word'], true);
		$new_index = text2words($msgOptions['body'], $customIndexSettings['bytes_per_word'], true);

		// Calculate the words to be added and removed from the index.
		$removed_words = array_diff(array_diff($old_index, $new_index), $stopwords);
		$inserted_words = array_diff(array_diff($new_index, $old_index), $stopwords);
		// Delete the removed words AND the added ones to avoid key constraints.
		if (!empty($removed_words))
		{
			$removed_words = array_merge($removed_words, $inserted_words);
			wesql::query('
				DELETE FROM {db_prefix}log_search_words
				WHERE id_msg = {int:id_msg}
					AND id_word IN ({array_int:removed_words})',
				array(
					'removed_words' => $removed_words,
					'id_msg' => $msgOptions['id'],
				)
			);
		}

		// Add the new words to be indexed.
		if (!empty($inserted_words))
		{
			$inserts = array();
			foreach ($inserted_words as $word)
				$inserts[] = array($word, $msgOptions['id']);
			wesql::insert('insert',
				'{db_prefix}log_search_words',
				array('id_word' => 'string', 'id_msg' => 'int'),
				$inserts,
				array('id_word', 'id_msg')
			);
		}
	}

	if (isset($msgOptions['subject']))
	{
		// Only update the subject if this was the first message in the topic.
		$request = wesql::query('
			SELECT id_topic
			FROM {db_prefix}topics
			WHERE id_first_msg = {int:id_first_msg}
			LIMIT 1',
			array(
				'id_first_msg' => $msgOptions['id'],
			)
		);
		if (wesql::num_rows($request) == 1)
			updateStats('subject', $topicOptions['id'], $msgOptions['subject']);
		wesql::free_result($request);
	}

	// Finally, if we are setting the approved state we need to do much more work :(
	if ($modSettings['postmod_active'] && isset($msgOptions['approved']))
		approvePosts($msgOptions['id'], $msgOptions['approved']);

	// Have fun with this one.
	call_hook('modify_post_after', array(&$msgOptions, &$topicOptions, &$posterOptions));

	return true;
}

// Approve (or not) some posts... without permission checks...
function approvePosts($msgs, $approve = true)
{
	if (!is_array($msgs))
		$msgs = array($msgs);

	if (empty($msgs))
		return false;

	// May as well start at the beginning, working out *what* we need to change.
	$request = wesql::query('
		SELECT m.id_msg, m.approved, m.id_topic, m.id_board, t.id_first_msg, t.id_last_msg,
			m.body, m.subject, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.id_member,
			t.approved AS topic_approved, b.count_posts
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
			AND m.approved = {int:approved_state}',
		array(
			'message_list' => $msgs,
			'approved_state' => $approve ? 0 : 1,
		)
	);
	$msgs = array();
	$topics = array();
	$topic_changes = array();
	$board_changes = array();
	$notification_topics = array();
	$notification_posts = array();
	$member_post_changes = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Easy...
		$msgs[] = $row['id_msg'];
		$topics[] = $row['id_topic'];

		// Ensure our change array exists already.
		if (!isset($topic_changes[$row['id_topic']]))
			$topic_changes[$row['id_topic']] = array(
				'id_last_msg' => $row['id_last_msg'],
				'approved' => $row['topic_approved'],
				'replies' => 0,
				'unapproved_posts' => 0,
			);
		if (!isset($board_changes[$row['id_board']]))
			$board_changes[$row['id_board']] = array(
				'posts' => 0,
				'topics' => 0,
				'unapproved_posts' => 0,
				'unapproved_topics' => 0,
			);

		// If it's the first message then the topic state changes!
		if ($row['id_msg'] == $row['id_first_msg'])
		{
			$topic_changes[$row['id_topic']]['approved'] = $approve ? 1 : 0;

			$board_changes[$row['id_board']]['unapproved_topics'] += $approve ? -1 : 1;
			$board_changes[$row['id_board']]['topics'] += $approve ? 1 : -1;

			// Note we need to ensure we announce this topic!
			$notification_topics[] = array(
				'body' => $row['body'],
				'subject' => $row['subject'],
				'name' => $row['poster_name'],
				'board' => $row['id_board'],
				'topic' => $row['id_topic'],
				'msg' => $row['id_first_msg'],
				'poster' => $row['id_member'],
			);
		}
		else
		{
			$topic_changes[$row['id_topic']]['replies'] += $approve ? 1 : -1;

			// This will be a post... but don't notify unless it's not followed by approved ones.
			if ($row['id_msg'] > $row['id_last_msg'])
				$notification_posts[$row['id_topic']][] = array(
					'id' => $row['id_msg'],
					'body' => $row['body'],
					'subject' => $row['subject'],
					'name' => $row['poster_name'],
					'topic' => $row['id_topic'],
				);
		}

		// If this is being approved and id_msg is higher than the current id_last_msg then it changes.
		if ($approve && $row['id_msg'] > $topic_changes[$row['id_topic']]['id_last_msg'])
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_msg'];
		// If this is being unapproved, and it's equal to the id_last_msg we need to find a new one!
		elseif (!$approve)
			// Default to the first message and then we'll override in a bit ;)
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_first_msg'];

		$topic_changes[$row['id_topic']]['unapproved_posts'] += $approve ? -1 : 1;
		$board_changes[$row['id_board']]['unapproved_posts'] += $approve ? -1 : 1;
		$board_changes[$row['id_board']]['posts'] += $approve ? 1 : -1;

		// Post count for the user?
		if ($row['id_member'] && empty($row['count_posts']))
			$member_post_changes[$row['id_member']] = isset($member_post_changes[$row['id_member']]) ? $member_post_changes[$row['id_member']] + 1 : 1;
	}
	wesql::free_result($request);

	if (empty($msgs))
		return;

	// Now we have the differences make the changes, first the easy one.
	wesql::query('
		UPDATE {db_prefix}messages
		SET approved = {int:approved_state}
		WHERE id_msg IN ({array_int:message_list})',
		array(
			'message_list' => $msgs,
			'approved_state' => $approve ? 1 : 0,
		)
	);

	// If we were unapproving find the last msg in the topics...
	if (!$approve)
	{
		$request = wesql::query('
			SELECT id_topic, MAX(id_msg) AS id_last_msg
			FROM {db_prefix}messages
			WHERE id_topic IN ({array_int:topic_list})
				AND approved = {int:approved}
			GROUP BY id_topic',
			array(
				'topic_list' => $topics,
				'approved' => 1,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$topic_changes[$row['id_topic']]['id_last_msg'] = $row['id_last_msg'];
		wesql::free_result($request);
	}

	// ... next the topics...
	foreach ($topic_changes as $id => $changes)
		wesql::query('
			UPDATE {db_prefix}topics
			SET approved = {int:approved}, unapproved_posts = unapproved_posts + {int:unapproved_posts},
				num_replies = num_replies + {int:num_replies}, id_last_msg = {int:id_last_msg}
			WHERE id_topic = {int:id_topic}',
			array(
				'approved' => $changes['approved'],
				'unapproved_posts' => $changes['unapproved_posts'],
				'num_replies' => $changes['replies'],
				'id_last_msg' => $changes['id_last_msg'],
				'id_topic' => $id,
			)
		);

	// ... finally the boards...
	foreach ($board_changes as $id => $changes)
		wesql::query('
			UPDATE {db_prefix}boards
			SET num_posts = num_posts + {int:num_posts}, unapproved_posts = unapproved_posts + {int:unapproved_posts},
				num_topics = num_topics + {int:num_topics}, unapproved_topics = unapproved_topics + {int:unapproved_topics}
			WHERE id_board = {int:id_board}',
			array(
				'num_posts' => $changes['posts'],
				'unapproved_posts' => $changes['unapproved_posts'],
				'num_topics' => $changes['topics'],
				'unapproved_topics' => $changes['unapproved_topics'],
				'id_board' => $id,
			)
		);

	// Finally, least importantly, notifications!
	if ($approve)
	{
		if (!empty($notification_topics))
		{
			loadSource('Post2');
			notifyMembersBoard($notification_topics);
		}
		if (!empty($notification_posts))
			sendApprovalNotifications($notification_posts);

		wesql::query('
			DELETE FROM {db_prefix}approval_queue
			WHERE id_msg IN ({array_int:message_list})
				AND id_attach = {int:id_attach}',
			array(
				'message_list' => $msgs,
				'id_attach' => 0,
			)
		);
	}
	// If unapproving add to the approval queue!
	else
	{
		$msgInserts = array();
		foreach ($msgs as $msg)
			$msgInserts[] = array($msg);

		wesql::insert('ignore',
			'{db_prefix}approval_queue',
			array('id_msg' => 'int'),
			$msgInserts,
			array('id_msg')
		);
	}

	// Update the last messages on the boards...
	updateLastMessages(array_keys($board_changes));

	// Post count for the members?
	if (!empty($member_post_changes))
		foreach ($member_post_changes as $id_member => $count_change)
			updateMemberData($id_member, array('posts' => 'posts ' . ($approve ? '+' : '-') . ' ' . $count_change));

	return true;
}

// Approve topics?
function approveTopics($topics, $approve = true)
{
	if (!is_array($topics))
		$topics = array($topics);

	if (empty($topics))
		return false;

	$approve_type = $approve ? 0 : 1;

	// Just get the messages to be approved and pass through...
	$request = wesql::query('
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE id_topic IN ({array_int:topic_list})
			AND approved = {int:approve_type}',
		array(
			'topic_list' => $topics,
			'approve_type' => $approve_type,
		)
	);
	$msgs = array();
	while ($row = wesql::fetch_assoc($request))
		$msgs[] = $row['id_msg'];
	wesql::free_result($request);

	return approvePosts($msgs, $approve);
}

// A special function for handling the hell which is sending approval notifications.
function sendApprovalNotifications(&$topicData)
{
	global $txt, $scripturl, $language, $user_info;
	global $modSettings, $context;

	// Clean up the data...
	if (!is_array($topicData) || empty($topicData))
		return;

	$topics = array();
	$digest_insert = array();
	foreach ($topicData as $topic => $msgs)
		foreach ($msgs as $msgKey => $msg)
	{
		censorText($topicData[$topic][$msgKey]['subject']);
		censorText($topicData[$topic][$msgKey]['body']);
		$topicData[$topic][$msgKey]['subject'] = un_htmlspecialchars($topicData[$topic][$msgKey]['subject']);
		$topicData[$topic][$msgKey]['body'] = trim(un_htmlspecialchars(strip_tags(strtr(parse_bbc($topicData[$topic][$msgKey]['body'], false), array('<br>' => "\n", '</div>' => "\n", '</li>' => "\n", '&#91;' => '[', '&#93;' => ']')))));

		$topics[] = $msg['id'];
		$digest_insert[] = array($msg['topic'], $msg['id'], 'reply', $user_info['id']);
	}

	// These need to go into the digest too...
	wesql::insert('',
		'{db_prefix}log_digest',
		array(
			'id_topic' => 'int', 'id_msg' => 'int', 'note_type' => 'string', 'exclude' => 'int',
		),
		$digest_insert,
		array()
	);

	// Find everyone who needs to know about this.
	$members = wesql::query('
		SELECT
			mem.id_member, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile,
			ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started,
			ln.id_topic
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}members AS mem ON (mem.id_member = ln.id_member)
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE ln.id_topic IN ({array_int:topic_list})
			AND mem.is_activated = {int:is_activated}
			AND mem.notify_types < {int:notify_types}
			AND mem.notify_regularity < {int:notify_regularity}
		GROUP BY mem.id_member, ln.id_topic, mem.email_address, mem.notify_regularity, mem.notify_types, mem.notify_send_body, mem.lngfile, ln.sent, mem.id_group, mem.additional_groups, b.member_groups, mem.id_post_group, t.id_member_started
		ORDER BY mem.lngfile',
		array(
			'topic_list' => $topics,
			'is_activated' => 1,
			'notify_types' => 4,
			'notify_regularity' => 2,
		)
	);
	$sent = 0;
	while ($row = wesql::fetch_assoc($members))
	{
		if ($row['id_group'] != 1)
		{
			$allowed = explode(',', $row['member_groups']);
			$row['additional_groups'] = explode(',', $row['additional_groups']);
			$row['additional_groups'][] = $row['id_group'];
			$row['additional_groups'][] = $row['id_post_group'];

			if (count(array_intersect($allowed, $row['additional_groups'])) == 0)
				continue;
		}

		$needed_language = empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile'];
		if (empty($current_language) || $current_language != $needed_language)
			$current_language = loadLanguage('Post', $needed_language, false);

		$sent_this_time = false;
		// Now loop through all the messages to send.
		foreach ($topicData[$row['id_topic']] as $msg)
		{
			$replacements = array(
				'TOPICSUBJECT' => $topicData[$row['id_topic']]['subject'],
				'POSTERNAME' => un_htmlspecialchars($topicData[$row['id_topic']]['name']),
				'TOPICLINK' => $scripturl . '?topic=' . $row['id_topic'] . '.new;topicseen#new',
				'UNSUBSCRIBELINK' => $scripturl . '?action=notify;topic=' . $row['id_topic'] . '.0',
			);

			$message_type = 'notification_reply';
			// Do they want the body of the message sent too?
			if (!empty($row['notify_send_body']) && empty($modSettings['disallow_sendBody']))
			{
				$message_type .= '_body';
				$replacements['BODY'] = $topicData[$row['id_topic']]['body'];
			}
			if (!empty($row['notify_regularity']))
				$message_type .= '_once';

			// Send only if once is off or it's on and it hasn't been sent.
			if (empty($row['notify_regularity']) || (empty($row['sent']) && !$sent_this_time))
			{
				$emaildata = loadEmailTemplate($message_type, $replacements, $needed_language);
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, 'm' . $topicData[$row['id_topic']]['last_id']);
				$sent++;
			}

			$sent_this_time = true;
		}
	}
	wesql::free_result($members);

	if (isset($current_language) && $current_language != $user_info['language'])
		loadLanguage('Post');

	// Sent!
	if (!empty($sent))
		wesql::query('
			UPDATE {db_prefix}log_notify
			SET sent = {int:is_sent}
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member != {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'topic_list' => $topics,
				'is_sent' => 1,
			)
		);
}

// Update the last message in a board, and its parents.
function updateLastMessages($setboards, $id_msg = 0)
{
	global $board_info, $board, $modSettings;

	// Please - let's be sane.
	if (empty($setboards))
		return false;

	if (!is_array($setboards))
		$setboards = array($setboards);

	// If we don't know the id_msg we need to find it.
	if (!$id_msg)
	{
		// Find the latest message on this board (highest id_msg.)
		$request = wesql::query('
			SELECT id_board, MAX(id_last_msg) AS id_msg
			FROM {db_prefix}topics
			WHERE id_board IN ({array_int:board_list})
				AND approved = {int:approved}
			GROUP BY id_board',
			array(
				'board_list' => $setboards,
				'approved' => 1,
			)
		);
		$lastMsg = array();
		while ($row = wesql::fetch_assoc($request))
			$lastMsg[$row['id_board']] = $row['id_msg'];
		wesql::free_result($request);
	}
	else
	{
		// Just to note - there should only be one board passed if we are doing this.
		foreach ($setboards as $id_board)
			$lastMsg[$id_board] = $id_msg;
	}

	$parent_boards = array();
	// Keep track of last modified dates.
	$lastModified = $lastMsg;
	// Get all the child boards for the parents, if they have some...
	foreach ($setboards as $id_board)
	{
		if (!isset($lastMsg[$id_board]))
		{
			$lastMsg[$id_board] = 0;
			$lastModified[$id_board] = 0;
		}

		if (!empty($board) && $id_board == $board)
			$parents = $board_info['parent_boards'];
		else
			$parents = getBoardParents($id_board);

		// Ignore any parents on the top child level.
		//!!! Why?
		foreach ($parents as $id => $parent)
		{
			if ($parent['level'] != 0)
			{
				// If we're already doing this one as a board, is this a higher last modified?
				if (isset($lastModified[$id]) && $lastModified[$id_board] > $lastModified[$id])
					$lastModified[$id] = $lastModified[$id_board];
				elseif (!isset($lastModified[$id]) && (!isset($parent_boards[$id]) || $parent_boards[$id] < $lastModified[$id_board]))
					$parent_boards[$id] = $lastModified[$id_board];
			}
		}
	}

	// Note to help understand what is happening here. For parents we update the timestamp of the last message for determining
	// whether there are child boards which have not been read. For the boards themselves we update both this and id_last_msg.

	$board_updates = array();
	$parent_updates = array();
	// Finally, to save on queries make the changes...
	foreach ($parent_boards as $id => $msg)
	{
		if (!isset($parent_updates[$msg]))
			$parent_updates[$msg] = array($id);
		else
			$parent_updates[$msg][] = $id;
	}

	foreach ($lastMsg as $id => $msg)
	{
		if (!isset($board_updates[$msg . '-' . $lastModified[$id]]))
			$board_updates[$msg . '-' . $lastModified[$id]] = array(
				'id' => $msg,
				'updated' => $lastModified[$id],
				'boards' => array($id)
			);

		else
			$board_updates[$msg . '-' . $lastModified[$id]]['boards'][] = $id;
	}

	// Now commit the changes!
	foreach ($parent_updates as $id_msg => $boards)
	{
		wesql::query('
			UPDATE {db_prefix}boards
			SET id_msg_updated = {int:id_msg_updated}
			WHERE id_board IN ({array_int:board_list})
				AND id_msg_updated < {int:id_msg_updated}',
			array(
				'board_list' => $boards,
				'id_msg_updated' => $id_msg,
			)
		);
	}
	foreach ($board_updates as $board_data)
	{
		wesql::query('
			UPDATE {db_prefix}boards
			SET id_last_msg = {int:id_last_msg}, id_msg_updated = {int:id_msg_updated}
			WHERE id_board IN ({array_int:board_list})',
			array(
				'board_list' => $board_data['boards'],
				'id_last_msg' => $board_data['id'],
				'id_msg_updated' => $board_data['updated'],
			)
		);
	}
}

// This simple function gets a list of all administrators and sends them an email to let them know a new member has joined.
function adminNotify($type, $memberID, $member_name = null)
{
	global $txt, $modSettings, $language, $scripturl, $user_info, $context;

	// If the setting isn't enabled then just exit.
	if (empty($modSettings['notify_new_registration']))
		return;

	if ($member_name == null)
	{
		// Get the new user's name....
		$request = wesql::query('
			SELECT real_name
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}
			LIMIT 1',
			array(
				'id_member' => $memberID,
			)
		);
		list ($member_name) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	$toNotify = array();
	$groups = array();

	// All membergroups who can approve members.
	$request = wesql::query('
		SELECT id_group
		FROM {db_prefix}permissions
		WHERE permission = {string:moderate_forum}
			AND add_deny = {int:add_deny}
			AND id_group != {int:id_group}',
		array(
			'add_deny' => 1,
			'id_group' => 0,
			'moderate_forum' => 'moderate_forum',
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$groups[] = $row['id_group'];
	wesql::free_result($request);

	// Add administrators too...
	$groups[] = 1;
	$groups = array_unique($groups);

	// Get a list of all members who have ability to approve accounts - these are the people who we inform.
	$request = wesql::query('
		SELECT id_member, lngfile, email_address
		FROM {db_prefix}members
		WHERE (id_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:group_array_implode}, additional_groups) != 0)
			AND notify_types != {int:notify_types}
		ORDER BY lngfile',
		array(
			'group_list' => $groups,
			'notify_types' => 4,
			'group_array_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$replacements = array(
			'USERNAME' => $member_name,
			'PROFILELINK' => $scripturl . '?action=profile;u=' . $memberID
		);
		$emailtype = 'admin_notify';

		// If they need to be approved add more info...
		if ($type == 'approval')
		{
			$replacements['APPROVALLINK'] = $scripturl . '?action=admin;area=viewmembers;sa=browse;type=approve';
			$emailtype .= '_approval';
		}

		$emaildata = loadEmailTemplate($emailtype, $replacements, empty($row['lngfile']) || empty($modSettings['userLanguage']) ? $language : $row['lngfile']);

		// And do the actual sending...
		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
	}
	wesql::free_result($request);

	if (isset($current_language) && $current_language != $user_info['language'])
		loadLanguage('Login');
}

function loadEmailTemplate($template, $replacements = array(), $lang = '', $loadLang = true)
{
	global $txt, $mbname, $scripturl, $settings, $user_info;

	// First things first, load up the email templates language file, if we need to.
	if ($loadLang)
		loadLanguage('EmailTemplates', $lang);

	if (!isset($txt['emails'][$template]))
		fatal_lang_error('email_no_template', 'template', array($template));

	$ret = array(
		'subject' => $txt['emails'][$template]['subject'],
		'body' => $txt['emails'][$template]['body'],
	);

	// Add in the default replacements.
	$replacements += array(
		'FORUMNAME' => $mbname,
		'SCRIPTURL' => $scripturl,
		'THEMEURL' => $settings['theme_url'],
		'IMAGESURL' => $settings['images_url'],
		'DEFAULT_THEMEURL' => $settings['default_theme_url'],
		'REGARDS' => $txt['regards_team'],
	);

	// Split the replacements up into two arrays, for use with str_replace
	$find = array();
	$replace = array();

	foreach ($replacements as $f => $r)
	{
		$find[] = '{' . $f . '}';
		$replace[] = $r;
	}

	// Do the variable replacements.
	$ret['subject'] = str_replace($find, $replace, $ret['subject']);
	$ret['body'] = str_replace($find, $replace, $ret['body']);

	// Now deal with the {USER.variable} items.
	$ret['subject'] = preg_replace_callback('~{USER.([^}]+)}~', 'user_info_callback', $ret['subject']);
	$ret['body'] = preg_replace_callback('~{USER.([^}]+)}~', 'user_info_callback', $ret['body']);

	// Finally return the email to the caller so they can send it out.
	return $ret;
}

function user_info_callback($matches)
{
	global $user_info;
	if (empty($matches[1]))
		return '';

	$use_ref = true;
	$ref = &$user_info;

	foreach (explode('.', $matches[1]) as $index)
	{
		if ($use_ref && isset($ref[$index]))
			$ref = &$ref[$index];
		else
		{
			$use_ref = false;
			break;
		}
	}

	return $use_ref ? $ref : $matches[0];
}

/**
 * If the user is saving a draft as part of action=post2, they come here.
 *
 * This function deals with all the saving of post drafts, regardless of anything else.
 * - Verify that the user did actually press save draft (or that it came from auto save), that they're not a guest, that they're allowed and draft saving is on.
 * - Sanitise it, just as we would for a post normally.
 * - Kick the user back if the draft is empty.
 * - If the draft id was supplied and it already exists, update it. Otherwise create a new one.
 *
 * @param bool $is_pm Whether we are saving a draft from a personal message or not.
 * @param int $id_context A contextual id for the draft as a hook back to content. If it's a new PM or post, this will be 0. If a reply to a topic, the topic id, or if a reply to a PM, the conversation id.
 * @return mixed False if the current state doesn't allow saving drafts, otherwise the saved draft id (0 if could not save), or to a fatal error in any other case.
 */
function saveDraft($is_pm, $id_context = 0)
{
	global $context, $txt, $board, $user_info, $modSettings;

	if ($user_info['is_guest'] || !allowedTo('save_post_draft') || empty($modSettings['masterSavePostDrafts']))
		return false;

	// Clean up what we may or may not have
	$subject = isset($_POST['subject']) ? $_POST['subject'] : '';
	$message = isset($_POST['message']) ? $_POST['message'] : '';
	$icon = isset($_POST['icon']) ? preg_replace('~[./\\\\*:"\'<>]~', '', $_POST['icon']) : 'xx';
	$is_pm = (bool) $is_pm;
	$id_context = (int) $id_context;

	// Sanitise what we do have
	$subject = westr::htmltrim(westr::htmlspecialchars($subject));
	$message = westr::htmlspecialchars($message, ENT_QUOTES);
	loadSource('Class-Editor'); // just in case

	// We would not have handled the WYSIWYG if we came from PM since that's done later in the PM workflow than we arrived here.
	if ($is_pm)
		wedit::preparseWYSIWYG('message');
	wedit::preparsecode($message);

	if (westr::htmltrim(westr::htmlspecialchars($subject)) === '' && westr::htmltrim(westr::htmlspecialchars($_POST['message']), ENT_QUOTES) === '')
		fatal_lang_error('empty_draft', false);

	$extra = array();

	// Hrm, so is this a new draft or not?
	$_REQUEST['draft_id'] = isset($_REQUEST['draft_id']) ? (int) $_REQUEST['draft_id'] : 0;
	if (!empty($_REQUEST['draft_id']))
	{
		// Does it exist already? Well, if so, we need to get its extra data because we might be updating it.
		$query = wesql::query('
			SELECT extra
			FROM {db_prefix}drafts
			WHERE id_draft = {int:draft}
				AND is_pm = {int:is_pm}
				AND id_context = {int:id_context}
				AND id_member = {int:id_member}',
			array(
				'draft' => $_REQUEST['draft_id'],
				'is_pm' => $is_pm ? 1 : 0,
				'id_context' => $id_context,
				'id_member' => $user_info['id'],
			)
		);

		if ($row = wesql::fetch_row($query))
		{
			$extra = empty($row[0]) ? array() : unserialize($row[0]);
			$found = true;
		}
		wesql::free_result($query);
	}

	// OK, so we either know it's a new draft, or we know we have the old one and have grabbed the mystical metadata. Now we update that either way.
	if (!$is_pm)
	{
		$extra['post_icon'] = $icon;
		$extra['smileys_enabled'] = !isset($_POST['ns']) ? 1 : 0;

		// !!! Locking, sticky?
	}
	else
	{
		// We left PM workflow very, very early. We need more information - recipients.
		$recipientList = array();
		$namedRecipientList = array();
		$namesNotFound = array();
		getPmRecipients($recipientList, $namedRecipientList, $namesNotFound);
		// So at this point, $recipientList is an array of 'to' and 'bcc' each containing an array of member ids, just ripe for saving.
		$extra['recipients'] = $recipientList;
	}
	$extra = serialize($extra);

	if (!empty($found))
	{
		wesql::query('
			UPDATE {db_prefix}drafts
			SET subject = {string:subject},
				body = {string:body},
				post_time = {int:post_time},
				extra = {string:extra}
			WHERE id_draft = {int:id_draft}
				AND is_pm = {int:is_pm}
				AND id_context = {int:id_context}
				AND id_member = {int:id_member}',
			array(
				'subject' => $subject,
				'body' => $message,
				'post_time' => time(),
				'extra' => $extra,
				'id_draft' => $_REQUEST['draft_id'],
				'id_member' => $user_info['id'],
				'is_pm' => !empty($is_pm) ? 1 : 0,
				'id_context' => $id_context,
			)
		);

		if (wesql::affected_rows() != 0)
			return $_REQUEST['draft_id'];
	}

	// Guess it is a new draft after all
	wesql::insert('insert',
		'{db_prefix}drafts',
		array(
			'id_member' => 'int',
			'subject' => 'string',
			'body' => 'string',
			'post_time' => 'int',
			'is_pm' => 'int',
			'id_board' => 'int',
			'id_context' => 'int',
			'extra' => 'string',
		),
		array(
			$user_info['id'],
			$subject,
			$message,
			time(),
			$is_pm ? 1 : 0,
			$board,
			$id_context,
			$extra,
		),
		array('id_draft')
	);

	return wesql::insert_id();
}

function draftXmlReturn($draft, $is_pm)
{
	global $txt, $context, $scripturl;
	header('Content-Type: text/xml; charset=UTF-8');
	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<response>
	<info id="lastsave" draft="', $draft, '" url="', $scripturl, $is_pm ? '?action=pm;sa=showdrafts;delete=DraftId;SessVar=SessId;xml' : '?action=profile;area=showdrafts;delete=DraftId;SessVar=SessId;xml', '"><![CD', 'ATA[', $txt['last_saved_on'], ': ', timeformat(time()), ']', ']></info>
</response>';

	// We send the otherwise fully completed URL back through the buffer, just in case Pretty URLs would reformat it for us.
	obExit(false);
}

?>