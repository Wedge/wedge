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
	public function getURL(Notification $notification)
	{
		$data = $notification->getData();
		return 'topic=' . $data['topic'] . '.msg' . $notification->getObject() . '#msg' . $notification->getObject();
	}

	public function getName()
	{
		return 'likes';
	}

	public function getText(Notification $notification)
	{
		global $txt;

		$data = $notification->getData();

		return sprintf($txt['welikes_notification'], $data['member']['name'], $data['subject']);
	}

	public function handleMultiple(Notification $notification, array &$data, array &$email_data)
	{
		return false;
	}

	public function getProfile($id_member)
	{
		global $txt;

		return array($txt['welikes_title'], $txt['welikes_desc'], array());
	}

	public function getIcon(Notification $notification)
	{
		global $txt, $memberContext;

		$data = $notification->getData();
		if (empty($data['member']['id']))
			return '';
		if (empty($memberContext[$data['member']['id']]['avatar']))
		{
			loadMemberAvatar($data['member']['id'], true);
			if (empty($memberContext[$data['member']['id']]['avatar']))
				return '';
		}
		return $memberContext[$data['member']['id']]['avatar']['image'];
	}

	public function getEmail(Notification $notification, array $email_data)
	{
		global $txt;

		return array($txt['welikes_subject'], $this->getText($notification));
	}

	public function getPreview(Notification $notification)
	{
		$data = $notification->getData();
		return get_single_post($notification->getObject());
	}
}
