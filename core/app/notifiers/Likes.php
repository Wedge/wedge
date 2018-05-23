<?php
/**
 * Contains functions for notifying users based on likes.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('File cannot be requested directly.');

class Likes_Notifier extends Notifier
{
	public function getText(Notification $notification, $is_email = false)
	{
		global $txt;

		$data = $notification->getData();
		$url = $notification->getURL();
		$member_url = SCRIPT . '?action=profile;u=' . $data['member']['id'];

		return strtr(
			$txt[$is_email ? 'notifier_likes_text' : 'notifier_likes_html'],
			array(
				'{MEMBER_NAME}' => $data['member']['name'],
				'{MEMBER_LINK}' => '<a href="' . $member_url . '">' . $data['member']['name'] . '</a>',
				'{MEMBER_URL}' => $member_url,
				'{OBJECT_NAME}' => $data['subject'],
				'{OBJECT_URL}' => $url,
				'{OBJECT_LINK}' => '<a href="' . $url . '">' . $data['subject'] . '</a>',
			)
		);
	}
}

class Likes_Thought_Notifier extends Notifier
{
	public function getText(Notification $notification, $is_email = false)
	{
		global $txt;

		$data = $notification->getData();
		$member_url = SCRIPT . '?action=profile;u=' . $data['member']['id'];

		return strtr(
			$txt[$is_email ? 'notifier_likes_thought_text' : 'notifier_likes_thought_html'],
			array(
				'{MEMBER_NAME}' => $data['member']['name'],
				'{MEMBER_LINK}' => '<a href="' . $member_url . '">' . $data['member']['name'] . '</a>',
				'{MEMBER_URL}' => $member_url,
				'{OBJECT_NAME}' => parse_bbc($data['subject']),
			)
		);
	}
}
