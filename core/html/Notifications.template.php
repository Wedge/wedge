<?php
/**
 * Template for notifications
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

function template_notifications_list()
{
	global $txt, $context;

	if (AJAX)
		echo '
		<ul class="nlist"><li>
			<span class="floatright" style="margin: 0 4px 4px 0">', !$context['unread_count'] ? '' : '
				<a href="<URL>?action=notification;sa=markread" class="n_markread" onclick="$(\'.notifs.notif .notevoid\').html(0); notload(this.href); return false;">' . $txt['notifications_short_mark_as_read'] . '</a> |', '
				<a href="<URL>?action=profile;area=notifications" class="n_settings"><span id="m_admin"></span><span', !$context['unread_count'] ? '>' : ' class="n_setdesc">', $txt['notifications_short_settings'], '</span></a>
			</span>
			<span class="floatleft" style="margin: 0 0 4px 4px">
				', !empty(we::$user['data']['n_all']) ? '<a href="<URL>?action=notification;show=unread" onclick="notload(this.href); return false;">' . $txt['notifications_short_unread'] . '</a>' : '<strong>' . $txt['notifications_short_unread'] . '</strong>', ' |
				', empty(we::$user['data']['n_all']) ? '<a href="<URL>?action=notification;show=latest" onclick="notload(this.href); return false;">' . $txt['notifications_short_latest'] . '</a>' : '<strong>' . $txt['notifications_short_latest'] . '</strong>', ' |
				<a href="<URL>?action=notification" style="color: #888">', $txt['notifications_short_all'], '</a>
			</span>
			<div class="n_container">';
	else
		echo '
		<we:title>', $txt['notifications'], '</we:title>
		<div>';

	if (empty($context['notifications']))
		echo '
			<div class="center padding">', $txt['notification_none'], '</div>';
	else
		foreach ($context['notifications'] as $notification)
			echo '
			<div class="n_item', $notification->getUnread() ? ' n_new' : '', AJAX ? '' : ' wrc', '" data-id="', $notification->getID(), '"', $notification->getNotifier()->getName() == 'likes_thought' ? ' data-prev="no"' : '', '>', $notification->getUnread() ? '
				<div class="n_read">x</div>' : '', '
				<div class="n_time">', timeformat($notification->getTime()), '</div>
				<div class="n_icon">', $notification->getIcon(), '</div>
				<div class="n_text">', $notification->getText(), '</div>
			</div>';

	if (AJAX)
		echo '
			</div>
		</li></ul>';
	else
		echo '
		</div>';
}

function template_notification_subs_profile()
{
	global $txt, $context;

	echo '
		<we:cat>
			', $txt['notif_subs'], '
		</we:cat>
		<p class="windowbg description">', $txt['notif_subs_desc'], '</p>';

	foreach ($context['notif_subscriptions'] as $subscription)
	{
		echo '
		<div class="generic_list">
			<table class="table_grid cs0 w100">
				<thead>
					<tr class="catbg">
						<th class="left">', $subscription['profile']['label'], '</th>
						<th class="left">', $txt['notif_subs_start_time'], '</th>
						<th class="left" style="width: 8%">', $txt['notif_unsubscribe'], '</th>
					</tr>
				</thead>
				<tr>
					<td class="windowbg description" colspan="3">', $subscription['profile']['description'], '</td>
				</tr>';

		$alt = false;
		foreach ($subscription['objects'] as $object)
		{
			$alt = !$alt;

			echo '
				<tr class="windowbg', $alt ? '2' : '', '">
					<td><a href="', $object['link'], '">', $object['title'], '</a></td>
					<td>', $object['time'], '</td>
					<td class="center">
						<a href="<URL>?action=notification;sa=unsubscribe;object=', $object['id'], ';type=', $subscription['type'], ';', $context['session_query'], '">
							<img src="', ASSETS, '/small_delete.gif" alt="x">
						</a>
					</td>
				</tr>';
		}

		echo '
			</table>
		</div>';
	}
}

function template_notification_email($notifications, $use_html = false)
{
	global $txt;

	$str = $txt['notification_email_periodical_body'] . ($use_html ? '<br><br>' : "\n\n");

	foreach ($notifications as $notifier_name => $notifs)
	{
		$notifier = weNotif::getNotifiers($notifier_name);
		list ($title) = $notifier->getProfile($notifs);

		if ($use_html)
			$str .= '<h3>' . $title . '</h3><ul>';
		else
			$str .= "\n\n" . $title . "\n" . str_repeat('=', strlen($title)) . "\n\n";

		foreach ($notifs as $n)
		{
			$body = $notifier->getText($n, !$use_html); // If HTML is disabled, we'll ask for a text e-mail.
			$str .= $use_html ? '<li style="padding:0 0 6px">' . $body . '</li>' : $body . "\n\n";
		}

		if ($use_html)
			$str .= '</ul>';
	}

	return $str;
}
