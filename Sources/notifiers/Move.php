<?php

if (!defined('WEDGE'))
	die('File cannot be requested directly');

class Move_Notifier extends Notifier
{
	/**
	 * Callback for getting the URL of the object
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string A fully qualified HTTP URL
	 */
	public function getURL(Notification $notification)
	{
		$data = $notification->getData();
		return 'topic=' . $notification->getObject() . '.msg' . $data['id_msg'] . '#msg' . $data['id_msg'];
	}

	/**
	 * Callback for getting the text to display on the notification screen
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string The text this notification wants to display
	 */
	public function getText(Notification $notification)
	{
		global $txt;

		$data = $notification->getData();

		// Only one member?
		$notif = we::$is['admin'] || in_array($data['id_board'], we::$user['qsb_boards']) ? 'notification_move' : 'notification_move_noaccess';
		return strtr($txt[$notif], array(
			'{MEMBER}' => $data['member']['name'],
			'{SUBJECT}' => shorten_subject($data['subject'], 25),
			'{BOARD}' => $data['board'],
		));
	}

	/**
	 * Returns the name of this notifier
	 *
	 * @access public
	 * @return string
	 */
	public function getName()
	{
		return 'move';
	}

	/**
	 * Callback for handling multiple notifications on the same object
	 * We don't have any special criterion for this
	 *
	 * @access public
	 * @param Notification $notification
	 * @param array &$data Reference to the new notification's data, if something needs to be altered
	 * @return bool, if false then a new notification is not created but the current one's time is updated
	 */
	public function handleMultiple(Notification $notification, array &$data, array &$email_data)
	{
		return false;
	}

	/**
	 * Returns the elements for notification's profile area
	 *
	 * @access public
	 * @param int $id_member The ID of the member whose profile is currently being accessed
	 * @return array(title, description, config_vars)
	 */
	public function getProfile($id_member)
	{
		global $txt;

		return array($txt['notification_move_profile'], $txt['notification_move_profile_desc'], array());
	}

	/**
	 * E-mail handler
	 *
	 * @access public
	 * @param Notification $notification
	 * @param array $email_data Any additional e-mail data passed to Notification::issue function
	 * @return array(subject, body)
	 */
	public function getEmail(Notification $notification, array $email_data)
	{
		global $txt;

		return array($txt['notification_move_email_subject'], $this->getText($notification));
	}

	public function getPreview(Notification $notification)
	{
		$data = $notification->getData();
		return get_single_post($data['id_msg']);
	}
}
