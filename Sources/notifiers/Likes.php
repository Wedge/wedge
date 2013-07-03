<?php
/**
 * Wedge
 *
 * Contains functions for notifying users based on likes.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('File cannot be requested directly.');

class Likes_Notifier extends Notifier
{
	public function getText(Notification $notification, $is_email = false)
	{
		global $txt, $scripturl;

		$data = $notification->getData();
		$url = $scripturl . '?' . $notification->getURL();
		$member_url = $scripturl . '?action=profile;u=' . $data['member']['id'];

		return strtr(
			$txt[$is_email ? 'notifier_likes_email' : 'notifier_likes_html'],
			array(
				'{MEMBER}' => $data['member']['name'],
				'{MEMBER_LINK}' => '<a href="' . $member_url . '">' . $data['member']['name'] . '</a>',
				'{MEMBER_URL}' => $member_url,
				'{OBJECT_NAME}' => $data['subject'],
				'{OBJECT_URL}' => $url,
				'{OBJECT_LINK}' => '<a href="' . $url . '">' . $data['subject'] . '</a>',
			)
		);
	}
}
