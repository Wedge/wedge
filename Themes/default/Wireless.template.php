<?php
/**
 * Wedge
 *
 * Displays the limited WAP2 view, using XHTMLMP (XHTML Mobile Profile)
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_wap2_before()
{
	global $context, $settings, $options, $user_info;

	echo '<?xml version="1.0" encoding="UTF-8"?', '>
<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" "http://www.wapforum.org/DTD/xhtml-mobile10.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>', $context['page_title'], '</title>';

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if ($user_info['is_guest'] && !empty($context['canonical_url']))
		echo '
		<link rel="canonical" href="', $context['canonical_url'], '" />';

	echo '
		<link rel="stylesheet" href="', add_css_file('wireless'), '" type="text/css" />
	</head>
	<body>';
}

function template_wap2_boards()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
		<p class="cat">', $context['forum_name_html_safe'], '</p>';

	$count = 0;
	foreach ($context['categories'] as $category)
	{
		if (!empty($category['boards']) || $category['is_collapsed'])
			echo '
		<p class="title">', $category['can_collapse'] ? '<a href="' . $scripturl . '?action=collapse;c=' . $category['id'] . ';sa=' . ($category['is_collapsed'] ? 'expand;' : 'collapse;') . $context['session_query'] . ';wap2">' : '', $category['name'], $category['can_collapse'] ? '</a>' : '', '</p>';

		foreach ($category['boards'] as $board)
		{
			$count++;
			echo '
		<p class="win">', $board['new'] ? '<span class="updated">' : '', $count < 10 ? '[' . $count . '' : '[-', $board['children_new'] && !$board['new'] ? '<span class="updated">' : '', '] ', $board['new'] || $board['children_new'] ? '</span>' : '', '<a href="', $scripturl, '?board=', $board['id'], '.0;wap2"', $count < 10 ? ' accesskey="' . $count . '"' : '', '>', $board['name'], '</a></p>';
		}
	}

	echo '
		<p class="title">', $txt['wireless_options'], '</p>';
	if ($context['user']['is_guest'])
		echo '
		<p class="win"><a href="', $scripturl, '?action=login;wap2">', $txt['wireless_options_login'], '</a></p>';
	else
	{
		if ($context['allow_pm'])
			echo '
		<p class="win"><a href="', $scripturl, '?action=pm;wap2">', empty($context['user']['unread_messages']) ? $txt['wireless_pm_inbox'] : sprintf($txt['wireless_pm_inbox_new'], $context['user']['unread_messages']), '</a></p>';
		echo '
		<p class="win"><a href="', $scripturl, '?action=unread;wap2">', $txt['wireless_recent_unread_posts'], '</a></p>
		<p class="win"><a href="', $scripturl, '?action=unreadreplies;wap2">', $txt['wireless_recent_unread_replies'], '</a></p>
		<hr />
		<p class="win"><a href="', $scripturl, '?action=logout;', $context['session_query'], ';wap2">', $txt['wireless_options_logout'], '</a></p>';
	}
}

function template_wap2_messageindex()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
		<p class="cat">', $context['name'], '</p>';

	if (!empty($context['boards']))
	{
		echo '
		<p class="title">', $txt['sub_boards'], '</p>';
		foreach ($context['boards'] as $board)
			echo '
		<p class="win">', $board['new'] ? '<span class="updated">[-] </span>' : ($board['children_new'] ? '[-<span class="updated">] </span>' : '[-] '), '<a href="', $scripturl, '?board=', $board['id'], '.0;wap2">', $board['name'], '</a></p>';
	}

	$count = 0;
	if (!empty($context['topics']))
	{
		echo '
		<p class="title">', $txt['topics'], '</p>
		<p class="win">', !empty($context['links']['prev']) ? '<a href="' . $context['links']['first'] . ';wap2">&lt;&lt;</a> <a href="' . $context['links']['prev'] . ';wap2">&lt;</a> ' : '', '(', $context['page_info']['current_page'], '/', $context['page_info']['num_pages'], ')', !empty($context['links']['next']) ? ' <a href="' . $context['links']['next'] . ';wap2">&gt;</a> <a href="' . $context['links']['last'] . ';wap2">&gt;&gt;</a> ' : '', '</p>';
		foreach ($context['topics'] as $topic)
		{
			$count++;
			echo '
		<p class="win">', $count < 10 ? '[' . $count . '] ' : '', '<a href="', $scripturl, '?topic=', $topic['id'], '.0;wap2"', $count < 10 ? ' accesskey="' . $count . '"' : '', '>', $topic['first_post']['subject'], '</a>', !$topic['approved'] ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : '', $topic['new'] && $context['user']['is_logged'] ? ' [<a href="' . $scripturl . '?topic=' . $topic['id'] . '.msg' . $topic['new_from'] . ';wap2#new" class="new">' . $txt['new'] . '</a>]' : '', '</p>';
		}
	}

	echo '
		<p class="title">', $txt['wireless_navigation'], '</p>
		<p class="win">[0] <a href="', $context['links']['up'], $context['links']['up'] == $scripturl . '?' ? '' : ';', 'wap2" accesskey="0">', $txt['wireless_navigation_up'], '</a></p>', !empty($context['links']['next']) ? '
		<p class="win">[#] <a href="' . $context['links']['next'] . ';wap2" accesskey="#">' . $txt['wireless_navigation_next'] . '</a></p>' : '', !empty($context['links']['prev']) ? '
		<p class="win">[*] <a href="' . $context['links']['prev'] . ';wap2" accesskey="*">' . $txt['wireless_navigation_prev'] . '</a></p>' : '', $context['can_post_new'] ? '
		<p class="win"><a href="' . $scripturl . '?action=post;board=' . $context['current_board'] . '.0;wap2">' . $txt['start_new_topic'] . '</a></p>' : '';
}

function template_wap2_display()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
		<p class="title">' . $context['linktree'][1]['name'] . ' > ' . $context['linktree'][count($context['linktree']) - 2]['name'] . '</p>
		<p class="cat">', $context['subject'], '</p>
		<p class="win">', !empty($context['links']['prev']) ? '<a href="' . $context['links']['first'] . ';wap2">&lt;&lt;</a> <a href="' . $context['links']['prev'] . ';wap2">&lt;</a> ' : '', '(', $context['page_info']['current_page'], '/', $context['page_info']['num_pages'], ')', !empty($context['links']['next']) ? ' <a href="' . $context['links']['next'] . ';wap2">&gt;</a> <a href="' . $context['links']['last'] . ';wap2">&gt;&gt;</a> ' : '', '</p>';
	$alternate = true;
	while ($message = $context['get_message']())
	{
		// This is a special modification to the post so it will work on phones:
		$message['body'] = preg_replace('~<div class="(?:quote|code)header">(.+?)</div>~', '<br />--- $1 ---', $message['body']);
		$message['body'] = strip_tags(str_replace(
			array(
				'<blockquote>',
				'</blockquote>',
				'<code>',
				'</code>',
				'<li>',
				$txt['code_select'],
			),
			array(
				'<br />',
				'<br />--- ' . $txt['wireless_end_quote'] . ' ---<br />',
				'<br />',
				'<br />--- ' . $txt['wireless_end_code'] . ' ---<br />',
				'<br />* ',
				'',
			), $message['body']), '<br>');

		echo $message['first_new'] ? '
		<a id="new"></a>' : '', '
		<p class="win', $alternate ? '' : '2', '">
			', $context['wireless_moderate'] && $message['member']['id'] ? '<a href="' . $scripturl . '?action=profile;u=' . $message['member']['id'] . ';wap2">' . $message['member']['name'] . '</a>' : '<strong>' . $message['member']['name'] . '</strong>',
			' <small>', $txt['on'], ' ', $message['time'], '</small>:', (empty($context['wireless_more']) && $message['can_modify']) || !empty($context['wireless_moderate']) ? '
			[<a href="' . $scripturl . '?action=post;msg=' . $message['id'] . ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';wap2">' . $txt['wireless_display_edit'] . '</a>]' : '', !$message['approved'] ? '&nbsp;<em>(' . $txt['awaiting_approval'] . ')</em>' : '', '<br />
			', $message['body'], '
		</p>';
		$alternate = !$alternate;
	}
	echo '
		<p class="title">', $txt['wireless_navigation'], '</p>
		<p class="win">[0] <a href="', $context['links']['up'], ';wap2" accesskey="0">', $txt['wireless_navigation_index'], '</a></p>', $context['user']['is_logged'] ? '
		<p class="win">[1] <a href="' . $scripturl . '?action=markasread;sa=topic;t=' . $context['mark_unread_time']. ';topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_query'] . ';wap2" accesskey="1">' . $txt['mark_unread'] . '</a></p>' : '', !empty($context['links']['next']) ? '
		<p class="win">[#] <a href="' . $context['links']['next'] . ';wap2' . $context['wireless_moderate'] . '" accesskey="#">' . $txt['wireless_navigation_next'] . '</a></p>' : '', !empty($context['links']['prev']) ? '
		<p class="win">[*] <a href="' . $context['links']['prev'] . ';wap2' . $context['wireless_moderate'] . '" accesskey="*">' . $txt['wireless_navigation_prev'] . '</a></p>' : '', $context['can_reply'] ? '
		<p class="win"><a href="' . $scripturl . '?action=post;topic=' . $context['current_topic'] . '.' . $context['start'] . ';wap2">' . $txt['reply'] . '</a></p>' : '';

	if (!empty($context['wireless_more']) && empty($context['wireless_moderate']))
		echo '
		<p class="win"><a href="', $scripturl, '?topic=', $context['current_topic'], '.', $context['start'], ';moderate;wap2">', $txt['wireless_display_moderate'], '</a></p>';
	elseif (!empty($context['wireless_moderate']))
	{
		if ($context['can_sticky'])
			echo '
				<p class="win"><a href="', $scripturl, '?action=sticky;topic=', $context['current_topic'], '.', $context['start'], ';', $context['session_query'], ';wap2">', $txt['wireless_display_' . ($context['is_sticky'] ? 'unsticky' : 'sticky')], '</a></p>';
		if ($context['can_lock'])
			echo '
				<p class="win"><a href="', $scripturl, '?action=lock;topic=', $context['current_topic'], '.', $context['start'], ';', $context['session_query'], ';wap2">', $txt['wireless_display_' . ($context['is_locked'] ? 'unlock' : 'lock')], '</a></p>';
	}
}

function template_wap2_login()
{
	global $context, $modSettings, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=login2;wap2" method="post">
			<p class="cat">', $txt['login'], '</p>';

	if (isset($context['login_errors']))
		foreach ($context['login_errors'] as $error)
			echo '
			<p class="win" style="color: #ff0000;"><strong>', $error, '</strong></p>';

	echo '
			<p class="win">', $txt['username'], ':</p>
			<p class="win"><input type="text" name="user" size="10" /></p>
			<p class="win">', $txt['password'], ':</p>
			<p class="win"><input type="password" name="passwrd" size="10" /></p>';

	// Open ID?
	if (!empty($modSettings['enableOpenID']))
		echo '
			<p class="win"><strong>&mdash;', $txt['or'], '&mdash;</strong></p>
			<p class="win">', $txt['openid'], ':</p>
			<p class="win"><input type="text" name="openid_identifier" class="openid_login" size="17" /></p>';

	echo '
			<p class="win"><input type="submit" value="', $txt['login'], '" class="submit" /><input type="hidden" name="cookieneverexp" value="1" /></p>
			<p class="cat">', $txt['wireless_navigation'], '</p>
			<p class="win">[0] <a href="', $scripturl, '?wap2" accesskey="0">', $txt['wireless_navigation_up'], '</a></p>
		</form>';
}

function template_wap2_post()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
		<form action="', $scripturl, '?action=', $context['destination'], ';board=', $context['current_board'], '.0;wap2" method="post">
			<p class="title">', $context['page_title'], '</p>';

	if (!$context['becomes_approved'])
		echo '
			<p class="win">
				' . $txt['wait_for_approval'] . '
				<input type="hidden" name="not_approved" value="1" />
			</p>';

	if ($context['locked'])
		echo '
			<p class="win">
				' . $txt['topic_locked_no_reply'] . '
			</p>';

	if (isset($context['name'], $context['email']))
	{
		echo '
			<p class="win"', isset($context['post_error']['long_name']) || isset($context['post_error']['no_name']) ? ' style="color: #ff0000"' : '', '>
				', $txt['username'], ': <input type="text" name="guestname" value="', $context['name'], '" />
			</p>';

		if (empty($modSettings['guest_post_no_email']))
			echo '
			<p class="win"', isset($context['post_error']['no_email']) || isset($context['post_error']['bad_email']) ? ' style="color: #ff0000"' : '', '>
				', $txt['email'], ': <input type="text" name="email" value="', $context['email'], '" />
			</p>';
	}

	if ($context['require_verification'])
		echo '
			<p class="win"', !empty($context['post_error']['need_qr_verification']) ? ' style="color: #ff0000"' : '', '>
				', $txt['verification'], ': ', template_control_verification($context['visual_verification_id'], 'all'), '
			</p>';

	echo '
			<p class="win"', isset($context['post_error']['no_subject']) ? ' style="color: #ff0000"' : '', '>
				', $txt['subject'], ': <input type="text" name="subject"', $context['subject'] == '' ? '' : ' value="' . $context['subject'] . '"', ' maxlength="80" />
			</p>
			<p class="win"', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? ' style="color: #ff0000;"' : '', '>
				', $txt['message'], ': <br />
				<textarea name="message" id="message" rows="5" cols="20">', $context['message'], '</textarea>
			</p>
			<p class="win">
				<input type="submit" name="post" value="', $context['submit_label'], '" class="submit" />
				<input type="hidden" name="icon" value="wireless" />
				<input type="hidden" name="goback" value="', $context['back_to_topic'] || !empty($options['return_to_post']) ? '1' : '0', '" />
				<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />', isset($context['current_topic']) ? '
				<input type="hidden" name="topic" value="' . $context['current_topic'] . '" />' : '', '
				<input type="hidden" name="notify" value="', $context['notify'] || !empty($options['auto_notify']) ? '1' : '0', '" />
			</p>
			<p class="win">[0] ', !empty($context['current_topic']) ? '<a href="' . $scripturl . '?topic=' . $context['current_topic'] . '.new;wap2">' . $txt['wireless_navigation_topic'] . '</a>' : '<a href="' . $scripturl . '?board=' . $context['current_board'] . '.0;wap2" accesskey="0">' . $txt['wireless_navigation_index'] . '</a>', '</p>
		</form>';
}

function template_wap2_pm()
{
	global $context, $settings, $options, $scripturl, $txt, $user_info;

	if ($_REQUEST['action'] == 'findmember')
	{
		echo '
				<form action="', $scripturl, '?action=findmember;', $context['session_query'], ';wap2" method="post">
					<p class="cat">', $txt['wireless_pm_search_member'], '</p>
					<p class="title">', $txt['find_members'], '</p>
					<p class="win">
						<strong>', $txt['wireless_pm_search_name'], ':</strong>
						<input type="text" name="search" value="', isset($context['last_search']) ? $context['last_search'] : '', '" />', empty($_REQUEST['u']) ? '' : '
						<input type="hidden" name="u" value="' . $_REQUEST['u'] . '" />', '
					</p>
					<p class="win"><input type="submit" value="', $txt['search'], '" class="submit" /></p>
				</form>';
		if (!empty($context['last_search']))
		{
			echo '
				<p class="title">', $txt['find_results'], '</p>';
			if (empty($context['results']))
				echo '
				<p class="win">[-] ', $txt['find_no_results'], '</p>';
			else
			{
				echo '
				<p class="win">', empty($context['links']['prev']) ? '' : '<a href="' . $context['links']['first'] . ';wap2">&lt;&lt;</a> <a href="' . $context['links']['prev'] . ';wap2">&lt;</a> ', '(', $context['page_info']['current_page'], '/', $context['page_info']['num_pages'], ')', empty($context['links']['next']) ? '' : ' <a href="' . $context['links']['next'] . ';wap2">&gt;</a> <a href="' . $context['links']['last'] . ';wap2">&gt;&gt;</a> ', '</p>';
				$count = 0;
				foreach ($context['results'] as $result)
				{
					$count++;
					echo '
				<p class="win">
					[', $count < 10 ? $count : '-', '] <a href="', $scripturl, '?action=pm;sa=send;u=', empty($_REQUEST['u']) ? $result['id'] : $_REQUEST['u'] . ',' . $result['id'], ';wap2"', $count < 10 ? ' accesskey="' . $count . '"' : '', '>', $result['name'], '</a>
				</p>';
				}
			}
		}
		echo '
				<p class="title">', $txt['wireless_navigation'], '</p>
				<p class="win">[0] <a href="', $context['links']['up'], ';wap2" accesskey="0">', $txt['wireless_navigation_up'], '</a></p>';
		if (!empty($context['results']))
			echo empty($context['links']['next']) ? '' : '
			<p class="win">[#] <a href="' . $context['links']['next'] . ';wap2" accesskey="#">' . $txt['wireless_navigation_next'] . '</a></p>', empty($context['links']['prev']) ? '' : '
			<p class="win">[*] <a href="' . $context['links']['prev'] . ';wap2" accesskey="*">' . $txt['wireless_navigation_prev'] . '</a></p>';
	}
	elseif (!empty($_GET['sa']))
	{
		if ($_GET['sa'] == 'addbuddy')
		{
			echo '
					<p class="cat">', $txt['wireless_pm_add_buddy'], '</p>
					<p class="title">', $txt['wireless_pm_select_buddy'], '</p>';
			$count = 0;
			foreach ($context['buddies'] as $buddy)
			{
				$count++;
				if ($buddy['selected'])
					echo '
					<p class="win">[-] <span style="color: gray">', $buddy['name'], '</span></p>';
				else
					echo '
					<p class="win">
						[', $count < 10 ? $count : '-', '] <a href="', $buddy['add_href'], ';wap2"', $count < 10 ? ' accesskey="' . $count . '"' : '', '>', $buddy['name'], '</a>
					</p>';
			}
			echo '
					<p class="title">', $txt['wireless_navigation'], '</p>
					<p class="win">[0] <a href="', $context['pm_href'], ';wap2" accesskey="0">', $txt['wireless_navigation_up'], '</a></p>';
		}
		if ($_GET['sa'] == 'send' || $_GET['sa'] == 'send2')
		{
			echo '
				<form action="', $scripturl, '?action=pm;sa=send2;wap2" method="post">
					<p class="cat">', $txt['new_message'], '</p>', empty($context['post_error']['messages']) ? '' : '
					<p class="win error">' . implode('<br />', $context['post_error']['messages']) . '</p>', '
					<p class="win">
						<strong>', $txt['pm_to'], ':</strong> ';
			if (empty($context['recipients']['to']))
				echo $txt['wireless_pm_no_recipients'];
			else
			{
				$to_names = array();
				$ids = array();
				foreach ($context['recipients']['to'] as $to)
				{
					$ids[] = $to['id'];
					$to_names[] = $to['name'];
				}
				echo implode(', ', $to_names);
				$ids = implode(',', $ids);
			}
			echo '
				', empty($ids) ? '' : '<input type="hidden" name="u" value="' . $ids . '" />', '<br />
						<a href="', $scripturl, '?action=findmember', empty($ids) ? '' : ';u=' . $ids, ';', $context['session_query'], ';wap2">', $txt['wireless_pm_search_member'], '</a>', empty($user_info['buddies']) ? '' : '<br />
						<a href="' . $scripturl . '?action=pm;sa=addbuddy' . (empty($ids) ? '' : ';u=' . $ids) . ';wap2">' . $txt['wireless_pm_add_buddy'] . '</a>', '
					</p>
					<p class="win">
						<strong>', $txt['subject'], ':</strong> <input type="text" name="subject" value="', $context['subject'], '" />
					</p>
					<p class="win">
						<strong>', $txt['message'], ':</strong><br />
						<textarea name="message" id="message" rows="5" cols="20">', $context['message'], '</textarea>
					</p>
					<p class="win">
						<input type="submit" value="', $txt['send_message'], '" class="submit" />
						<input type="hidden" name="outbox" value="', $context['copy_to_outbox'] ? '1' : '0', '" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '" />
						<input type="hidden" name="replied_to" value="', !empty($context['quoted_message']['id']) ? $context['quoted_message']['id'] : 0, '" />
						<input type="hidden" name="pm_head" value="', !empty($context['quoted_message']['pm_head']) ? $context['quoted_message']['pm_head'] : 0, '" />
						<input type="hidden" name="f" value="', isset($context['folder']) ? $context['folder'] : '', '" />
						<input type="hidden" name="l" value="', isset($context['current_label_id']) ? $context['current_label_id'] : -1, '" />
					</p>';
			if ($context['reply'])
				echo '
					<p class="title">', $txt['wireless_pm_reply_to'], '</p>
					<p class="win"><strong>', $context['quoted_message']['subject'], '</strong></p>
					<p class="win">', $context['quoted_message']['body'], '</p>';
			echo '
					<p class="title">', $txt['wireless_navigation'], '</p>
					<p class="win">[0] <a href="', $scripturl, '?action=pm;wap2" accesskey="0">', $txt['wireless_navigation_up'], '</a></p>
				</form>';
		}
	}
	elseif (empty($_GET['pmsg']))
	{
		echo '
			<p class="cat">', $context['current_label_id'] == -1 ? $txt['wireless_pm_inbox'] : $txt['pm_current_label'] . ': ' . $context['current_label'], '</p>
			<p class="win">', empty($context['links']['prev']) ? '' : '<a href="' . $context['links']['first'] . ';wap2">&lt;&lt;</a> <a href="' . $context['links']['prev'] . ';wap2">&lt;</a> ', '(', $context['page_info']['current_page'], '/', $context['page_info']['num_pages'], ')', empty($context['links']['next']) ? '' : ' <a href="' . $context['links']['next'] . ';wap2">&gt;</a> <a href="' . $context['links']['last'] . ';wap2">&gt;&gt;</a> ', '</p>';
		$count = 0;
		while ($message = $context['get_pmessage']())
		{
			$count++;
			echo '
			<p class="win">
				[', $count < 10 ? $count : '-', '] <a href="', $scripturl, '?action=pm;start=', $context['start'], ';pmsg=', $message['id'], ';l=', $context['current_label_id'], ';wap2"', $count < 10 ? ' accesskey="' . $count . '"' : '', '>', $message['subject'], ' <em>', $txt['wireless_pm_by'], '</em> ', $message['member']['name'], '</a>', $message['is_unread'] ? ' [' . $txt['new'] . ']' : '', '
			</p>';
		}

		if ($context['currently_using_labels'])
		{
			$labels = array();
			ksort($context['labels']);
			foreach ($context['labels'] as $label)
				$labels[] = '<a href="' . $scripturl . '?action=pm;l=' . $label['id'] . ';wap2">' . $label['name'] . '</a>' . (!empty($label['unread_messages']) ? ' (' . $label['unread_messages'] . ')' : '');
			echo '
			<p class="cat">
				', $txt['pm_labels'], '
			</p>
			<p class="win">
				', implode(', ', $labels), '
			</p>';
		}

		echo '
			<p class="title">', $txt['wireless_navigation'], '</p>
			<p class="win">[0] <a href="', $scripturl, '?wap2" accesskey="0">', $txt['wireless_navigation_up'], '</a></p>', empty($context['links']['next']) ? '' : '
			<p class="win">[#] <a href="' . $context['links']['next'] . ';wap2" accesskey="#">' . $txt['wireless_navigation_next'] . '</a></p>', empty($context['links']['prev']) ? '' : '
			<p class="win">[*] <a href="' . $context['links']['prev'] . ';wap2" accesskey="*">' . $txt['wireless_navigation_prev'] . '</a></p>', $context['can_send_pm'] ? '
			<p class="win"><a href="' . $scripturl . '?action=pm;sa=send;wap2">' . $txt['new_message'] . '</a></p>' : '';
	}
	else
	{
		$message = $context['get_pmessage']();
		$message['body'] = preg_replace('~<div class="(?:quote|code)header">(.+?)</div>~', '<br />--- $1 ---', $message['body']);
		$message['body'] = strip_tags(str_replace(
			array(
				'<blockquote>',
				'</blockquote>',
				'<code>',
				'</code>',
				'<li>',
				$txt['code_select'],
			),
			array(
				'<br />',
				'<br />--- ' . $txt['wireless_end_quote'] . ' ---<br />',
				'<br />',
				'<br />--- ' . $txt['wireless_end_code'] . ' ---<br />',
				'<br />* ',
				'',
			), $message['body']), '<br>');

		echo '
			<p class="cat">', $message['subject'], '</p>
			<p class="title">
				<strong>', $txt['wireless_pm_by'], ':</strong> ', $message['member']['name'], '<br />
				<strong>', $txt['on'], ':</strong> ', $message['time'], '
			</p>
			<p class="win">
				', $message['body'], '
			</p>
			<p class="title">', $txt['wireless_navigation'], '</p>
			<p class="win">[0] <a href="', $scripturl, '?action=pm;start=', $context['start'], ';l=', $context['current_label_id'], ';wap2" accesskey="0">', $txt['wireless_navigation_up'], '</a></p>';
			if ($context['can_send_pm'])
				echo '
			<p class="win"><a href="', $scripturl, '?action=pm;sa=send;pmsg=', $message['id'], ';u=', $message['member']['id'], ';reply;wap2">', $txt['wireless_pm_reply'], '</a></p>';

			if ($context['can_send_pm'] && $message['number_recipients'] > 1)
				echo '
			<p class="win"><a href="', $scripturl, '?action=pm;sa=send;pmsg=', $message['id'], ';u=all;reply;wap2">', $txt['wireless_pm_reply_all'], '</a></p>';

	}
}

function template_wap2_recent()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
		<p class="cat">', $_REQUEST['action'] == 'unread' ? $txt['wireless_recent_unread_posts'] : $txt['wireless_recent_unread_replies'], '</p>';

	$count = 0;
	if (empty($context['topics']))
		echo '
			<p class="win">', $txt['old_posts'], '</p>';
	else
	{
		echo '
		<p class="win">', !empty($context['links']['prev']) ? '<a href="' . $context['links']['first'] . ';wap2">&lt;&lt;</a> <a href="' . $context['links']['prev'] . ';wap2">&lt;</a> ' : '', '(', $context['page_info']['current_page'], '/', $context['page_info']['num_pages'], ')', !empty($context['links']['next']) ? ' <a href="' . $context['links']['next'] . ';wap2">&gt;</a> <a href="' . $context['links']['last'] . ';wap2">&gt;&gt;</a> ' : '', '</p>';
		foreach ($context['topics'] as $topic)
		{
			$count++;
			echo '
		<p class="win">', $count < 10 ? '[' . $count . '] ' : '', '<a href="', $scripturl, '?topic=', $topic['id'], '.msg', $topic['new_from'], ';topicseen;wap2#new"', $count < 10 ? ' accesskey="' . $count . '"' : '', '>', $topic['first_post']['subject'], '</a></p>';
		}
	}
	echo '
		<p class="title">', $txt['wireless_navigation'], '</p>
		<p class="win">[0] <a href="', $context['links']['up'], '?wap2" accesskey="0">', $txt['wireless_navigation_up'], '</a></p>', !empty($context['links']['next']) ? '
		<p class="win">[#] <a href="' . $context['links']['next'] . ';wap2" accesskey="#">' . $txt['wireless_navigation_next'] . '</a></p>' : '', !empty($context['links']['prev']) ? '
		<p class="win">[*] <a href="' . $context['links']['prev'] . ';wap2" accesskey="*">' . $txt['wireless_navigation_prev'] . '</a></p>' : '';
}

function template_wap2_error()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
		<p class="cat">', $context['error_title'], '</p>
		<p class="win">', $context['error_message'], '</p>
		<p class="win">[0] <a href="', $scripturl, '?wap2" accesskey="0">', $txt['wireless_error_home'], '</a></p>';
}

function template_wap2_profile()
{
	global $context, $settings, $options, $scripturl, $board, $txt;

	echo '
		<p class="cat">', $txt['summary'], ' - ', $context['member']['name'], '</p>
		<p class="win"><strong>', $txt['name'], ':</strong> ', $context['member']['name'], '</p>
		<p class="win"><strong>', $txt['position'], ': </strong>', !empty($context['member']['group']) ? $context['member']['group'] : $context['member']['post_group'], '</p>
		<p class="win"><strong>', $txt['lastLoggedIn'], ':</strong> ', $context['member']['last_login'], '</p>';

	if (!empty($context['member']['bans']))
	{
		echo '
		<p class="title"><strong>', $txt['user_banned_by_following'], ':</strong></p>';

		foreach ($context['member']['bans'] as $ban)
			echo '
		<p class="win">', $ban['explanation'], '</p>';

	}

	echo '

		<p class="title">', $txt['additional_info'], '</p>';

	if (!$context['user']['is_owner'] && $context['can_send_pm'])
		echo '
		<p class="win"><a href="', $scripturl, '?action=pm;sa=send;u=', $context['id_member'], ';wap2">', $txt['wireless_profile_pm'], '.</a></p>';

	if (!$context['user']['is_owner'] && !empty($context['can_edit_ban']))
		echo '
		<p class="win"><a href="', $scripturl, '?action=admin;area=ban;sa=add;u=', $context['id_member'], ';wap2">', $txt['profileBanUser'], '.</a></p>';

	echo '
		<p class="win"><a href="', $scripturl, '?wap2">', $txt['wireless_error_home'], '.</a></p>';

}

function template_wap2_ban_edit()
{
	global $context, $settings, $options, $scripturl, $board, $txt, $modSettings;

	echo '
	<form action="', $scripturl, '?action=admin;area=ban;sa=add;wap2" method="post">
		<p class="cat">', $context['ban']['is_new'] ? $txt['ban_add_new'] : $txt['ban_edit'] . ' \'' . $context['ban']['name'] . '\'', '</p>
		<p class="win">
			<strong>', $txt['ban_name'], ': </strong>
			<input type="text" name="ban_name" value="', $context['ban']['name'], '" size="20" />
		</p>
		<p class="win">
			<strong>', $txt['ban_expiration'], ': </strong><br />
			<input type="radio" name="expiration" value="never" ', $context['ban']['expiration']['status'] == 'never' ? ' checked="checked"' : '', ' /> ', $txt['never'], '<br />
			<input type="radio" name="expiration" value="one_day" ', $context['ban']['expiration']['status'] == 'still_active_but_we_re_counting_the_days' ? ' checked="checked"' : '', ' /> ', $txt['ban_will_expire_within'], ' <input type="text" name="expire_date" size="3" value="', $context['ban']['expiration']['days'], '" /> ', $txt['ban_days'], '<br />
			<input type="radio" name="expiration" value="expired" ', $context['ban']['expiration']['status'] == 'expired' ? ' checked="checked"' : '', ' /> ', $txt['ban_expired'], '<br />
		</p>
		<p class="win">
			<strong>', $txt['ban_reason'], ': </strong>
			<input type="text" name="reason" value="', $context['ban']['reason'], '" size="20" />
		</p>
		<p class="win">
			<strong>', $txt['ban_notes'], ': </strong><br />
			<textarea name="notes" cols="20" rows="3">', $context['ban']['notes'], '</textarea>
		</p>
		<p class="win">
			<strong>', $txt['ban_restriction'], ': </strong><br />
			<input type="checkbox" name="full_ban" value="1"', $context['ban']['cannot']['access'] ? ' checked="checked"' : '', ' /> ', $txt['ban_full_ban'], '<br />
			<input type="checkbox" name="cannot_post" value="1"', $context['ban']['cannot']['post'] ? ' checked="checked"' : '', ' /> ', $txt['ban_cannot_post'], '<br />
			<input type="checkbox" name="cannot_register" value="1"', $context['ban']['cannot']['register'] ? ' checked="checked"' : '', ' /> ', $txt['ban_cannot_register'], '<br />
			<input type="checkbox" name="cannot_login" value="1"', $context['ban']['cannot']['login'] ? ' checked="checked"' : '', ' /> ', $txt['ban_cannot_login'], '
		</p>';

	if (!empty($context['ban_suggestions']))
	{
		echo '
		<p class="title">', $txt['ban_triggers'], '</p>
		<p class="win">
			<input type="checkbox" name="ban_suggestion[]" value="main_ip" /> <strong>', $txt['wireless_ban_ip'], ':</strong><br />
			&nbsp;&nbsp;&nbsp;&nbsp;<input type="text" name="main_ip" value="', $context['ban_suggestions']['main_ip'], '" size="20" />
		</p>';

		if (empty($modSettings['disableHostnameLookup']))
			echo '
		<p class="win">
			<input type="checkbox" name="ban_suggestion[]" value="hostname" /> <strong>', $txt['wireless_ban_hostname'], ':</strong><br />
			&nbsp;&nbsp;&nbsp;&nbsp;<input type="text" name="hostname" value="', $context['ban_suggestions']['hostname'], '" size="20" />
		<p>';

		echo '
		<p class="win">
			<input type="checkbox" name="ban_suggestion[]" value="email" /> <strong>', $txt['wireless_ban_email'], ':</strong><br />
			&nbsp;&nbsp;&nbsp;&nbsp;<input type="text" name="email" value="', $context['ban_suggestions']['email'], '" size="20" />
		</p>
		<p class="win">
			<input type="checkbox" name="ban_suggestion[]" value="user" /> <strong>', $txt['ban_on_username'], ':</strong><br />';

		if (empty($context['ban_suggestions']['member']['id']))
			echo '
			&nbsp;&nbsp;&nbsp;&nbsp;<input type="text" name="user" value="" size="20" />';
		else
			echo '
			&nbsp;&nbsp;&nbsp;&nbsp;', $context['ban_suggestions']['member']['name'], '
			<input type="hidden" name="bannedUser" value="', $context['ban_suggestions']['member']['id'], '" />';

		echo '
		</p>';
	}

	echo '

		<p class="win"><input type="submit" name="', $context['ban']['is_new'] ? 'add_ban' : 'modify_ban', '" value="', $context['ban']['is_new'] ? $txt['ban_add'] : $txt['ban_modify'], '" class="submit" /></p>
		<p class="title">', $txt['wireless_additional_info'], '</p>
		<p class="win"><a href="', $scripturl, '?wap2">', $txt['wireless_error_home'], '.</a></p>';

	echo '
		<input type="hidden" name="old_expire" value="', $context['ban']['expiration']['days'], '" />
		<input type="hidden" name="bg" value="', $context['ban']['id'], '" />
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';
}

function template_wap2_after()
{
	global $context, $settings, $options, $txt;

	echo '
		<p><a href="', $context['linktree'][count($context['linktree']) - 1]['url'], count($context['linktree']) > 1 ? ';' : '?', 'nowap" rel="nofollow">', $txt['wireless_go_to_full_version'], '</a></p>
	</body>
</html>';
}

?>