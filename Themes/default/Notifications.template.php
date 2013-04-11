<?php
/**
 * Wedge
 *
 * Template for notifications
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_notifications_list()
{
	global $txt, $context;

	if (AJAX)
		echo '
		<ul id="notlist"><li>
			<h6>
				<a href="<URL>?action=notification;sa=unread">', $txt['notifications_short_unread'], '</a> -
				<a href="<URL>?action=notification;sa=latest" style="color: #888">', $txt['notifications_short_latest'], '</a> -
				<a href="<URL>?action=notification" style="color: #888">', $txt['notifications_short_all'], '</a>
			</h6>
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
			<div class="n_item', $notification->getUnread() ? ' n_new' : '', AJAX ? '' : ' wrc', '" id="nti', $notification->getID(), '">
				<div class="n_read">x</div>
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

function template_notification_email($notifications)
{
	global $txt;

	$str = $txt['notification_email_periodical_body'] . '<br><br>';

	foreach ($notifications as $notifier => $notifs)
	{
		list ($title) = weNotif::getNotifiers($notifier)->getProfile();

		$str .= '
			<h3>' . $title . '</h3>
			<hr>
			<div style="margin-left: 15px">';

		foreach ($notifs as $n)
			$str .= '<div>' . $n->getText() . '</div>';

		$str .='
			</div>';
	}

	return $str;
}
