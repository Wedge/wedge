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

function template_notifications()
{
	global $txt, $context;

	echo '
	<p class="notifs">
		<span class="n_count note', $context['unread_notifications'] ? 'nice' : '', '">
			', $context['unread_notifications'], '
		</span>
		', $txt['notifications'], '
		<div id="notification_shade">
			<ul class="actions">
				<li class="windowbg" style="m-width: 300px">
					<h6><a href="<URL>?action=notification" style="color: #888">(', $txt['view_all'], ')</a></h6>
					<div class="notification_container">
						<div class="notification template">
							<div class="notification_text"></div>
							<div>
								<div class="notification_markread">x</div>
								<div class="notification_time"></div>
							</div>
							<hr class="clear" />
						</div>
					</div>
				</li>
			</ul>
		</div>
	</p>';
}

function template_notifications_list()
{
	global $txt, $context;

	echo '
		<we:title>', $txt['notifications'], '</we:title>
		<div class="notification_container">';

	foreach ($context['notifications'] as $notification)
		echo '
			<div class="notification">
				<div class="notification_text', $notification->getUnread() ? ' wrc windowbg' : '', '">
					', $notification->getText(), '<br />
					<span class="smalltext">', timeformat($notification->getTime()), '</span>
				</div>
			</div>';

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
