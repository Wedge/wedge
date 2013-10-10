<?php
/**
 * Configuration of mail, and more usefully, the mail queue.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file is all about mail, how we love it so. In particular it handles the admin side of
	mail configuration, as well as reviewing the mail queue - if enabled.

	void ManageMail()
		// !!

	void BrowseMailQueue()
		// !!

	void ModifyMailSettings()
		// !!

	void ClearMailQueue()
		// !!

*/

// This function passes control through to the relevant section
function ManageMail()
{
	global $context, $txt, $settings;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageMail');

	// We'll need the utility functions from here.
	loadSource('ManageServer');

	$context['page_title'] = $txt['mailqueue_title'];
	wetem::load('show_settings');

	$subActions = array(
		'settings' => 'ModifyMailSettings',
		'templates' => 'ModifyEmailTemplates',
	);

	if (!empty($settings['mail_queue']))
		$subActions += array(
			'browse' => 'BrowseMailQueue',
			'clear' => 'ClearMailQueue',
		);

	// By default we want to browse - if the queue's enabled.
	$context['sub_action'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (empty($settings['mail_queue']) ? 'settings' : 'browse');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['mailqueue_title'],
		'description' => $txt['mailqueue_desc'],
		'tabs' => array(
			'browse' => array(
			),
			'settings' => array(
			),
			'templates' => array(
				'description' => $txt['mailqueue_templates_desc'],
			),
		),
	);

	// Call the right function for this sub-action.
	$subActions[$context['sub_action']]();
}

// Display the mail queue...
function BrowseMailQueue()
{
	global $context, $txt;

	// First, are we deleting something from the queue?
	if (isset($_REQUEST['delete']))
	{
		checkSession('post');

		$ids = (array) $_REQUEST['delete'];
		foreach ($ids as $k => $v)
			if (empty($v))
				unset ($ids[$k]);
			else
				$ids[$k] = (int) $v;

		if (!empty($ids))
		{
			if (!empty($_POST['send_now']))
			{
				loadSource('ScheduledTasks');
				ReduceMailQueue(false, true, true, $ids);
			}
			else
				wesql::query('
					DELETE FROM {db_prefix}mail_queue
					WHERE id_mail IN ({array_int:mail_ids})',
					array(
						'mail_ids' => $ids,
					)
				);
		}
	}

	// How many items do we have?
	$request = wesql::query('
		SELECT COUNT(*) AS queue_size, MIN(time_sent) AS oldest
		FROM {db_prefix}mail_queue',
		array(
		)
	);
	list ($mailQueueSize, $mailOldest) = wesql::fetch_row($request);
	wesql::free_result($request);

	$context['oldest_mail'] = empty($mailOldest) ? $txt['mailqueue_oldest_not_available'] : time_since(time() - $mailOldest);
	$context['mail_queue_size'] = comma_format($mailQueueSize);

	$listOptions = array(
		'id' => 'mail_queue',
		'title' => $txt['mailqueue_browse'],
		'items_per_page' => 20,
		'base_href' => '<URL>?action=admin;area=mailqueue',
		'default_sort_col' => 'age',
		'no_items_label' => $txt['mailqueue_no_items'],
		'get_items' => array(
			'function' => 'list_getMailQueue',
		),
		'get_count' => array(
			'function' => 'list_getMailQueueSize',
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => $txt['mailqueue_subject'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return westr::strlen($rowData[\'subject\']) > 50 ? sprintf(\'%1$s...\', htmlspecialchars(westr::substr($rowData[\'subject\'], 0, 47))) : htmlspecialchars($rowData[\'subject\']);
					'),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'subject',
					'reverse' => 'subject DESC',
				),
			),
			'recipient' => array(
				'header' => array(
					'value' => $txt['mailqueue_recipient'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="mailto:%1$s">%1$s</a>',
						'params' => array(
							'recipient' => true,
						),
					),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'recipient',
					'reverse' => 'recipient DESC',
				),
			),
			'priority' => array(
				'header' => array(
					'value' => $txt['mailqueue_priority'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;

						// We probably have a text label with your priority.
						$txtKey = sprintf(\'mq_mpriority_%1$s\', $rowData[\'priority\']);

						// But if not, revert to priority 0.
						return isset($txt[$txtKey]) ? $txt[$txtKey] : $txt[\'mq_mpriority_1\'];
					'),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'priority',
					'reverse' => 'priority DESC',
				),
			),
			'age' => array(
				'header' => array(
					'value' => $txt['mailqueue_age'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return time_since(time() - $rowData[\'time_sent\']);
					'),
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'time_sent',
					'reverse' => 'time_sent DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return \'<input type="checkbox" name="delete[]" value="\' . $rowData[\'id_mail\'] . \'">\';
					'),
					'class' => 'smalltext',
				),
			),
		),
		'form' => array(
			'href' => '<URL>?action=admin;area=mailqueue',
			'include_start' => true,
			'include_sort' => true,
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
	<input type="submit" name="send_now" value="' . $txt['mailqueue_send_these_items'] . '" onclick="return ask(we_confirm, e);" class="submit">
	<input type="submit" name="delete_redirects" value="' . $txt['delete'] . '" onclick="return ask(we_confirm, e);" class="delete">',
			),
		),
	);

	loadSource('Subs-List');
	createList($listOptions);

	loadTemplate('ManageMail');
	wetem::load('browse');
}

function list_getMailQueue($start, $items_per_page, $sort)
{
	global $txt;

	$request = wesql::query('
		SELECT
			id_mail, time_sent, recipient, priority, private, subject
		FROM {db_prefix}mail_queue
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:items_per_page}',
		array(
			'start' => $start,
			'sort' => $sort,
			'items_per_page' => $items_per_page,
		)
	);
	$mails = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Private PM/email subjects and similar shouldn't be shown in the mailbox area.
		if (!empty($row['private']))
			$row['subject'] = $txt['personal_message'];

		$mails[] = $row;
	}
	wesql::free_result($request);

	return $mails;
}

function list_getMailQueueSize()
{
	// How many items do we have?
	$request = wesql::query('
		SELECT COUNT(*) AS queue_size
		FROM {db_prefix}mail_queue',
		array(
		)
	);
	list ($mailQueueSize) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $mailQueueSize;
}

function ModifyMailSettings($return_config = false)
{
	global $txt, $context;

	$config_vars = array(
			// Master email settings
			array('webmaster_email', $txt['webmaster_email'], 'file', 'email', 30, 'webmaster_email'),
			array('mail_from', $txt['mail_from'], 'db', 'email', 30, 'mail_from'),
		'',
			// Mail queue stuff, this rocks ;)
			array('mail_queue', $txt['mail_queue'], 'db', 'check'),
			array('mail_limit', $txt['mail_limit'], 'db', 'int'),
			array('mail_quantity', $txt['mail_quantity'], 'db', 'int'),
		'',
			// SMTP stuff.
			array('mail_type', $txt['mail_type'], 'db', 'select', array(
				0 => array(0, $txt['mail_type_default']),
				1 => array(1, 'SMTP'),
			)),
			array('smtp_host', $txt['smtp_host'], 'db', 'text', 30),
			array('smtp_port', $txt['smtp_port'], 'db', 'int', 30, 'min' => 1, 'max' => 65535), // port 0 is officially marked reserved
			array('smtp_username', $txt['smtp_username'], 'db', 'text', 30),
			array('smtp_password', $txt['smtp_password'], 'db', 'password', 30),
	);

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		// Make the SMTP password a little harder to see in a backup etc.
		if (!empty($_POST['smtp_password'][1]))
		{
			$_POST['smtp_password'][0] = base64_encode($_POST['smtp_password'][0]);
			$_POST['smtp_password'][1] = base64_encode($_POST['smtp_password'][1]);
		}
		checkSession();

		saveSettings($config_vars);
		redirectexit('action=admin;area=mailqueue;sa=settings');
	}

	$context['post_url'] = '<URL>?action=admin;area=mailqueue;save;sa=settings';
	$context['settings_title'] = $txt['mailqueue_settings'];

	// We need to use this instead of prepareDBSettingsContext because some of this stuff goes in Settings.php itself.
	prepareServerSettingsContext($config_vars);
}

function ModifyEmailTemplates()
{
	global $context, $txt, $mbname, $theme, $cachedir;

	getLanguages();

	loadTemplate('ManageMail');

	$lang = isset($_REQUEST['emaillang'], $context['languages'][$_REQUEST['emaillang']]) ? $_REQUEST['emaillang'] : 'english'; // Always fall back to that.
	loadLanguage('EmailTemplates', $lang);

	// This is where we add all the fields that a given template might provide access to. Yay.
	$context['email_templates'] = array(
		'register_immediate' => array('register', array('username', 'realname', 'password', 'forgotpasswordlink')),
		'register_activate' => array('register', array('username', 'realname', 'password', 'forgotpasswordlink', 'activationlink', 'activationcode', 'activationlinkwithoutcode')),
		'register_activate_approve' => array('register', array('username', 'realname', 'password', 'forgotpasswordlink', 'activationlink', 'activationcode', 'activationlinkwithoutcode')),
		'register_pending' => array('register', array('username', 'realname', 'password', 'forgotpasswordlink')),
		'register_coppa' => array('register', array('username', 'realname', 'password', 'forgotpasswordlink', 'coppalink')),
		'resend_activate_message' => array('register', array('username', 'realname', 'forgotpasswordlink', 'activationlink', 'activationcode', 'activationlinkwithoutcode')),

		'admin_register_immediate' => array('register_admin', array('username', 'realname', 'password', 'forgotpasswordlink', 'activationlink', 'activationcode', 'activationlinkwithoutcode')),
		'admin_register_activate' => array('register_admin', array('username', 'realname', 'password', 'forgotpasswordlink', 'activationlink', 'activationcode', 'activationlinkwithoutcode')),
		'admin_approve_activation' => array('register_admin', array('realname', 'activationlink', 'activationcode', 'activationlinkwithoutcode')),
		'admin_approve_remind' => array('register_admin', array('realname', 'activationlink', 'activationcode', 'activationlinkwithoutcode')),
		'admin_approve_accept' => array('register_admin', array('username', 'realname', 'profilelink', 'forgotpasswordlink')),
		'admin_approve_reject' => array('register_admin', array('username')),
		'admin_approve_delete' => array('register_admin', array('username')),

		'forgot_password' => array('account_changes', array('username', 'realname', 'remindlink', 'ip')),
		'change_password' => array('account_changes', array('username', 'realname', 'password')),
		'activate_reactivate' => array('account_changes', array('activationlink', 'activationcode', 'activationlinkwithoutcode')),

		'new_announcement' => array('notify_content', array('topicsubject', 'message', 'topiclink')),
		'notify_boards' => array('notify_content', array('topicsubject', 'topiclink', 'unsubscribelink')),
		'notify_boards_body' => array('notify_content', array('topicsubject', 'topiclink', 'unsubscribelink', 'message')),
		'notify_boards_once' => array('notify_content', array('topicsubject', 'topiclink', 'unsubscribelink')),
		'notify_boards_once_body' => array('notify_content', array('topicsubject', 'topiclink', 'unsubscribelink', 'message')),
		'notification_reply' => array('notify_content', array('topicsubject', 'topiclink', 'postername', 'unsubscribelink')),
		'notification_reply_body' => array('notify_content', array('topicsubject', 'topiclink', 'postername', 'unsubscribelink', 'message')),
		'notification_reply_once' => array('notify_content', array('topicsubject', 'topiclink', 'postername', 'unsubscribelink')),
		'notification_reply_body_once' => array('notify_content', array('topicsubject', 'topiclink', 'postername', 'unsubscribelink', 'message')),

		'notification_pin' => array('notify_moderation', array('topicsubject', 'topiclink', 'postername', 'unsubscribelink')),
		'notification_lock' => array('notify_moderation', array('topicsubject', 'topiclink', 'postername', 'unsubscribelink')),
		'notification_unlock' => array('notify_moderation', array('topicsubject', 'topiclink', 'postername', 'unsubscribelink')),
		'notification_remove' => array('notify_moderation', array('topicsubject', 'postername')),
		'notification_move' => array('notify_moderation', array('topicsubject', 'topiclink', 'postername', 'unsubscribelink')),
		'notification_merge' => array('notify_moderation', array('topicsubject', 'topiclink', 'postername', 'unsubscribelink')),
		'notification_split' => array('notify_moderation', array('topicsubject', 'topiclink', 'postername', 'unsubscribelink')),

		'request_membership' => array('group_membership', array('recpname', 'appyname', 'groupname', 'reason', 'modlink')),
		'mc_group_approve' => array('group_membership', array('realname', 'groupname')),
		'mc_group_reject' => array('group_membership', array('realname', 'groupname')),
		'mc_group_reject_reason' => array('group_membership', array('realname', 'groupname', 'refusereason')),

		'send_topic' => array('user_email', array('sendernamemanual', 'recpnamemanual', 'topicsubject', 'topiclink')),
		'send_topic_comment' => array('user_email', array('sendernamemanual', 'recpnamemanual', 'topicsubject', 'topiclink', 'comment')),
		'send_email' => array('user_email', array('emailsubject', 'emailbody', 'sendername', 'recpname')),
		'pm_email' => array('user_email', array('sendername', 'subject', 'message', 'replylink')),

		'paid_subscription_new' => array('paid_subs', array('subscrname', 'subname', 'subuser', 'subemail', 'price', 'profilelink', 'date')),
		'paid_subscription_reminder' => array('paid_subs', array('subscrname', 'realname', 'profilelinksubs', 'end_date')),
		'paid_subscription_refund' => array('paid_subs', array('username', 'realname', 'idmember', 'subscrname', 'refundname', 'refunduser', 'profilelink', 'date')),
		'paid_subscription_error' => array('paid_subs', array('username', 'realname', 'idmember', 'suberror')),

		'report_to_moderator' => array('moderator', array('topicsubject', 'topiclink', 'postername', 'reportername', 'comment', 'reportlink')),
		'scheduled_approval' => array('moderator', array('realname', 'message')),
		'admin_notify' => array('moderator', array('username', 'profilelink')),
		'admin_notify_approval' => array('moderator', array('username', 'profilelink', 'approvallink')),
		'admin_attachments_full' => array('moderator', array('username', 'realname', 'idmember')),
	);

	if (isset($_POST['save'], $_POST['email'], $context['email_templates'][$_POST['email']]))
	{
		checkSession();

		// !!! Bored of this now. Can't be arsed to do actual validation right now and force flow back to the form to make the user do it right.
		// I have spent all day, literally, on this stuff and it is dull as dishwater.

		foreach (array('subject', 'body') as $item)
			$_POST[$item] = isset($_POST[$item]) ? trim($_POST[$item]) : '';

		$new_entry = array(
			'desc' => $txt['emailtemplate_' . $_POST['email']]['desc'],
			'subject' => !empty($_POST['subject']) ? westr::safe($_POST['subject'], ENT_QUOTES) : $txt['emailtemplate_' . $_POST['email']]['subject'],
			'body' => !empty($_POST['body']) ? westr::safe($_POST['body'], ENT_QUOTES) : $txt['emailtemplate_' . $_POST['email']]['body'],
		);

		wesql::insert('replace',
			'{db_prefix}language_changes',
			array('id_theme' => 'int', 'id_lang' => 'string', 'lang_file' => 'string', 'lang_var' => 'string', 'lang_key' => 'string', 'lang_string' => 'string', 'serial' => 'int'),
			array(1, $lang, 'EmailTemplates', 'txt', 'emailtemplate_' . $_POST['email'], serialize($new_entry), 1)
		);

		foreach (glob($cachedir . '/lang_*_*_EmailTemplates.php') as $filename)
			@unlink($filename);

		$_SESSION['was_saved'] = true;
		redirectexit('action=admin;area=mailqueue;sa=templates;emaillang=' . $lang);
	}
	elseif (isset($_GET['email'], $context['email_templates'][$_GET['email']]))
	{
		// Need to inject these items in. See loadEmailTemplate() in Subs-Post.php.
		$items = array(
			'forumname' => $mbname,
			'scripturl' => '<URL>',
			'themeurl' => $theme['theme_url'],
			'imagesurl' => $theme['images_url'],
			'default_themeurl' => $theme['default_theme_url'],
			'regards' => str_replace('{FORUMNAME}', $context['forum_name'], $txt['regards_team']),
		);
		foreach ($items as $k => $v)
			$txt['template_repl_' . $k] = sprintf($txt['template_repl_' . $k], $v);

		$context['emailtemplate'] = array(
			'email' => $_GET['email'],
			'lang' => $lang,
			'desc' => $txt['emailtemplate_' . $_GET['email']]['desc'],
			'subject' => westr::safe($txt['emailtemplate_' . $_GET['email']]['subject'], ENT_QUOTES),
			'body' => westr::safe($txt['emailtemplate_' . $_GET['email']]['body'], ENT_QUOTES),
			'replacement_items' => array_merge(
				array('forumname', 'scripturl', 'themeurl', 'imagesurl', 'default_themeurl', 'regards'),
				$context['email_templates'][$_GET['email']][1]
			)
		);
		wetem::load('email_edit');
	}
	else
	{
		$context['email_groups'] = array();
		foreach ($context['email_templates'] as $template => $details)
			$context['email_groups'][$details[0]][] = $template;

		if (!empty($_SESSION['was_saved']))
		{
			$context['was_saved'] = true;
			unset($_SESSION['was_saved']);
		}

		wetem::load('email_template_list');
		add_css('
	.roundframe { margin-bottom: 1em }
	.roundframe.smalltext { font-size: .85em; line-height: 120% }');
	}
}

// This function clears the mail queue of all emails, and at the end redirects to browse.
function ClearMailQueue()
{
	checkSession('get');

	// This is certainly needed!
	loadSource('ScheduledTasks');

	// If we don't yet have the total to clear, find it.
	if (!isset($_GET['te']))
	{
		// How many items do we have?
		$request = wesql::query('
			SELECT COUNT(*) AS queue_size
			FROM {db_prefix}mail_queue',
			array(
			)
		);
		list ($_GET['te']) = wesql::fetch_row($request);
		wesql::free_result($request);
	}
	else
		$_GET['te'] = (int) $_GET['te'];

	$_GET['sent'] = isset($_GET['sent']) ? (int) $_GET['sent'] : 0;

	// Send 50 at a time, then go for a break...
	while (ReduceMailQueue(50, true, true) === true)
	{
		// Sent another 50.
		$_GET['sent'] += 50;
		pauseMailQueueClear();
	}

	return BrowseMailQueue();
}

// Used for pausing the mail queue.
function pauseMailQueueClear()
{
	global $context, $txt, $time_start;

	// Try to get more time...
	@set_time_limit(600);
	if (function_exists('apache_reset_timeout'))
		@apache_reset_timeout();

	// Have we already used our maximum time?
	if (microtime(true) - $time_start < 5)
		return;

	$context['continue_get_data'] = '?action=admin;area=mailqueue;sa=clear;te=' . $_GET['te'] . ';sent=' . $_GET['sent'] . ';' . $context['session_query'];
	$context['page_title'] = $txt['not_done_title'];
	$context['continue_post_data'] = '';
	$context['continue_countdown'] = '2';
	wetem::load('not_done');

	// Keep browse selected.
	$context['selected'] = 'browse';

	// What percent through are we?
	$context['continue_percent'] = round(($_GET['sent'] / $_GET['te']) * 100, 1);

	// Never more than 100%!
	$context['continue_percent'] = min($context['continue_percent'], 100);

	obExit();
}

// Little function to calculate how long ago a time was.
function time_since($time_diff)
{
	global $txt;

	if ($time_diff < 0)
		$time_diff = 0;

	// Just do a bit of an if fest...
	if ($time_diff > 86400)
	{
		$days = round($time_diff / 86400, 1);
		return sprintf($days == 1 ? $txt['mq_day'] : $txt['mq_days'], $time_diff / 86400);
	}
	// Hours?
	elseif ($time_diff > 3600)
	{
		$hours = round($time_diff / 3600, 1);
		return sprintf($hours == 1 ? $txt['mq_hour'] : $txt['mq_hours'], $hours);
	}
	// Minutes?
	elseif ($time_diff > 60)
	{
		$minutes = (int) ($time_diff / 60);
		return sprintf($minutes == 1 ? $txt['mq_minute'] : $txt['mq_minutes'], $minutes);
	}
	// Otherwise must be second
	else
		return sprintf($time_diff == 1 ? $txt['mq_second'] : $txt['mq_seconds'], $time_diff);
}
