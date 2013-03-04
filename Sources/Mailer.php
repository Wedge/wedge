<?php
/**
 * Wedge
 *
 * Handles sending topics to friends, or emailing a user based on a given message.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	The functions in this file deal with sending e-mails to a friend or
	moderator, and those functions are:

	void SendTopic()
		- sends information about a topic to a friend.
		- uses the Mailer template, with the main block.
		- requires the send_topic permission.
		- redirects back to the first page of the topic when done.
		- is accessed via ?action=emailuser;sa=sendtopic.

	void CustomEmail()
		- send an email to the user - allow the sender to write the message.
		- can either be passed a user ID as uid or a message id as msg.
		- does not check permissions for a message ID as there is no information disclosed.

*/

// The main handling function for sending specialist (or otherwise) emails to a user.
function EmailUser()
{
	global $topic, $txt, $context;

	// Don't index anything here.
	$context['robot_no_index'] = true;

	// Load the template.
	loadTemplate('Mailer');
	loadLanguage('ManageTopics');

	$sub_actions = array(
		'email' => 'CustomEmail',
		'sendtopic' => 'SendTopic',
	);

	if (!isset($_GET['sa']) || !isset($sub_actions[$_GET['sa']]))
		$_GET['sa'] = 'sendtopic';

	$sub_actions[$_GET['sa']]();
}

// Send a topic to a friend.
function SendTopic()
{
	global $topic, $txt, $context, $scripturl, $settings;

	// Check permissions...
	isAllowedTo('send_topic');

	// We need at least a topic... go away if you don't have one.
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	// Get the topic's subject.
	$request = wesql::query('
		SELECT m.subject, t.approved
		FROM {db_prefix}topics AS t
		INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
		WHERE t.id_topic = {int:current_topic}
			AND {query_see_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	if (wesql::num_rows($request) == 0)
		fatal_lang_error('not_a_topic', false);
	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// Can't send topic if it's unapproved and using post moderation.
	if ($settings['postmod_active'] && !$row['approved'])
		fatal_lang_error('not_approved_topic', false);

	// Censor the subject....
	censorText($row['subject']);

	// Sending yet, or just getting prepped?
	if (empty($_POST['send']))
	{
		$context['page_title'] = sprintf($txt['sendtopic_title'], $row['subject']);
		$context['start'] = $_REQUEST['start'];

		return;
	}

	// Actually send the message...
	checkSession();
	spamProtection('sendtopc');

	// This is needed for sendmail().
	loadSource('Subs-Post');

	// Trim the names..
	$_POST['y_name'] = trim($_POST['y_name']);
	$_POST['r_name'] = trim($_POST['r_name']);

	// Make sure they aren't playing "let's use a fake email".
	if ($_POST['y_name'] == '_' || !isset($_POST['y_name']) || $_POST['y_name'] == '')
		fatal_lang_error('no_name', false);
	if (!isset($_POST['y_email']) || $_POST['y_email'] == '')
		fatal_lang_error('no_email', false);
	if (!is_valid_email($_POST['y_email']))
		fatal_lang_error('email_invalid_character', false);

	// The receiver should be valid to.
	if ($_POST['r_name'] == '_' || !isset($_POST['r_name']) || $_POST['r_name'] == '')
		fatal_lang_error('no_name', false);
	if (!isset($_POST['r_email']) || $_POST['r_email'] == '')
		fatal_lang_error('no_email', false);
	if (!is_valid_email($_POST['r_email']))
		fatal_lang_error('email_invalid_character', false);

	// Emails don't like entities...
	$row['subject'] = un_htmlspecialchars($row['subject']);

	$replacements = array(
		'TOPICSUBJECT' => $row['subject'],
		'SENDERNAMEMANUAL' => $_POST['y_name'],
		'RECPNAMEMANUAL' => $_POST['r_name'],
		'TOPICLINK' => $scripturl . '?topic=' . $topic . '.0',
	);

	$emailtemplate = 'send_topic';

	if (!empty($_POST['comment']))
	{
		$emailtemplate .= '_comment';
		$replacements['COMMENT'] = $_POST['comment'];
	}

	$emaildata = loadEmailTemplate($emailtemplate, $replacements);
	// And off we go!
	sendmail($_POST['r_email'], $emaildata['subject'], $emaildata['body'], $_POST['y_email']);

	// Back to the topic!
	redirectexit('topic=' . $topic . '.0');
}

// Allow a user to send an email.
function CustomEmail()
{
	global $context, $settings, $txt;

	// Can the user even see this information?
	if (we::$is_guest)
		fatal_lang_error('no_access', false);

	// Are we sending to a user?
	$context['form_hidden_vars'] = array();
	if (isset($_REQUEST['uid']))
	{
		$request = wesql::query('
			SELECT email_address AS email, real_name AS name, id_member, hide_email
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}',
			array(
				'id_member' => (int) $_REQUEST['uid'],
			)
		);

		$context['form_hidden_vars']['uid'] = (int) $_REQUEST['uid'];
	}
	elseif (isset($_REQUEST['msg']))
	{
		$request = wesql::query('
			SELECT IFNULL(mem.email_address, m.poster_email) AS email, IFNULL(mem.real_name, m.poster_name) AS name, IFNULL(mem.id_member, 0) AS id_member, hide_email
			FROM {db_prefix}messages AS m
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.id_msg = {int:id_msg}',
			array(
				'id_msg' => (int) $_REQUEST['msg'],
			)
		);

		$context['form_hidden_vars']['msg'] = (int) $_REQUEST['msg'];
	}

	if (empty($request) || wesql::num_rows($request) == 0)
		fatal_lang_error('cant_find_user_email');

	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// Are you sure you got the address?
	if (empty($row['email']))
		fatal_lang_error('cant_find_user_email');

	// Can they actually do this?
	$context['show_email_address'] = showEmailAddress(!empty($row['hide_email']), $row['id_member']);
	if ($context['show_email_address'] === 'no')
		fatal_lang_error('no_access', false);

	// Setup the context!
	$context['recipient'] = array(
		'id' => $row['id_member'],
		'name' => $row['name'],
		'email' => $row['email'],
		'email_link' => ($context['show_email_address'] == 'yes_permission_override' ? '<em>' : '') . '<a href="mailto:' . $row['email'] . '">' . $row['email'] . '</a>' . ($context['show_email_address'] == 'yes_permission_override' ? '</em>' : ''),
		'link' => $row['id_member'] ? '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['name'] . '</a>' : $row['name'],
	);

	// Can we see this person's email address?
	$context['can_view_recipient_email'] = $context['show_email_address'] == 'yes_permission_override';

	// Are we actually sending it?
	if (isset($_POST['send'], $_POST['email_body']))
	{
		loadSource('Subs-Post');

		checkSession();

		$from_name = we::$user['name'];
		$from_email = we::$user['email'];

		// Check we have a body (etc).
		if (trim($_POST['email_body']) == '' || trim($_POST['email_subject']) == '')
			fatal_lang_error('email_missing_data');

		// We use a template in case they want to customise!
		$replacements = array(
			'EMAILSUBJECT' => $_POST['email_subject'],
			'EMAILBODY' => $_POST['email_body'],
			'SENDERNAME' => $from_name,
			'RECPNAME' => $context['recipient']['name'],
		);

		// Don't let them send too many!
		spamProtection('sendmail');

		// Get the template and get out!
		$emaildata = loadEmailTemplate('send_email', $replacements);
		sendmail($context['recipient']['email'], $emaildata['subject'], $emaildata['body'], $from_email, null, false, 1, null, true);

		// Now work out where to go!
		if (isset($_REQUEST['uid']))
			redirectexit('action=profile;u=' . (int) $_REQUEST['uid']);
		elseif (isset($_REQUEST['msg']))
			redirectexit('msg=' . (int) $_REQUEST['msg']);
		else
			redirectexit();
	}

	wetem::load('custom_email');
	$context['page_title'] = $txt['send_email'];
}
