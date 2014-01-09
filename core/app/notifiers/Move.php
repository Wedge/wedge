<?php
/**
 * Contains functions for notifying users based on topic moves.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('File cannot be requested directly');

class Move_Notifier extends Notifier
{
	// We'll need to override getURL, because getObject stores a topic ID, not a post ID. (See Class-Notifier.php)
	public function getURL(Notification $notification)
	{
		$data = $notification->getData();
		return '<URL>?topic=' . $notification->getObject() . '.0';
	}

	// We'll override the getText function, too.
	public function getText(Notification $notification, $is_email = false)
	{
		global $txt;

		$data = $notification->getData();

		// Only one member?
		$notif = we::$is['admin'] || in_array($data['id_board'], we::$user['qsb_boards']) ? 'notifier_move' : 'notifier_move_noaccess';

		return strtr($txt[$notif . ($is_email ? '_text' : '_html')], array(
			'{MEMBER_NAME}' => $data['member']['name'],
			'{MEMBER_LINK}' => '<a href="<URL>?action=profile;u=' . $data['member']['id'] . '">' . $data['member']['name'] . '</a>',
			'{TOPIC_NAME}' => $data['subject'],
			'{TOPIC_LINK}' => '<a href="' . $notification->getURL() . '">' . $data['subject'] . '</a>',
			'{BOARD_NAME}' => $data['board'],
			'{BOARD_LINK}' => '<a href="<URL>?board=' . $data['id_board'] . '">' . $data['board'] . '</a>',
		));
	}

	public function getPreview(Notification $notification)
	{
		global $txt;

		$data = $notification->getData();
		$raw = get_single_post($data['id_msg']);

		if ($raw !== false)
			return $raw;

		// Since this is a topic post, if it's gone, give the natural error message.
		loadLanguage('Errors');
		return '<div class="errorbox">' . $txt['topic_gone'] . '</div>';
	}
}
