<?php
/**
 * Contains base notifier class, which is to be extended to implement notifiers.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('File cannot be requested directly.');

/**
 * Notifier interface, every notifier adding their own stuff must implement this interface.
 */
class Notifier
{
	/**
	 * Callback for getting the URL of the object.
	 * You can override it if the notification isn't about a post.
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string A fully qualified URL
	 */
	public function getURL(Notification $notification)
	{
		$data = $notification->getData();
		$msg = $notification->getObject();

		// Notifications are free to store the topic ID in 'id_topic' or 'topic', we're not judging.
		return '<URL>?topic=' . (isset($data['topic']) ? $data['topic'] : $data['id_topic']) . '.msg' . $msg . '#msg' . $msg;
	}

	/**
	 * Callback for getting the text to display on the notification screen or e-mail.
	 *
	 * @access public
	 * @param Notification $notification
	 * @param boolean $is_email
	 * @return string The text this notification wants to output
	 */
	public function getText(Notification $notification, $is_email = false)
	{
		global $txt;

		$data = $notification->getData();
		$object = $notification->getObject();
		$object_url = $notification->getURL();

		return strtr(
			$txt['notifier_' . $this->getName() . ($is_email ? '_text' : '_html')],
			array(
				'{MEMBER_NAME}' => $data['member']['name'],
				'{MEMBER_LINK}' => '<a href="<URL>?action=profile;u=' . $data['member']['id'] . '">' . $data['member']['name'] . '</a>',
				'{OBJECT_NAME}' => $data['subject'],
				'{OBJECT_LINK}' => '<a href="' . $object_url . '">' . $data['subject'] . '</a>',
				'{OBJECT_URL}'  => $object_url,
			)
		);
	}

	/**
	 * Returns the name of this notifier, e.g. 'likes'. You can override it if needed.
	 *
	 * @access public
	 * @return string
	 */
	public function getName()
	{
		static $name = '';
		if (empty($name))
			$name = strtolower(preg_replace('~_Notifier.*~', '', get_class($this)));
		return $name;
	}

	/**
	 * Callback for handling multiple notifications on the same object.
	 * By default, if an item already has a notification issued for it, further notifications
	 * will be ignored, and the last notification will have its date bumped.
	 * If you need to change this, just override the function to return true.
	 *
	 * @access public
	 * @param Notification $notification
	 * @param array &$data Reference to the new notification's data, if something needs to be altered.
	 * @return bool Whether or not to handle.
	 */
	public function handleMultiple(Notification $notification, array &$data)
	{
		return false;
	}

	/**
	 * Returns the elements for notification's profile area.
	 * The third parameter of the array, config_vars, is same as
	 * the settings' config vars specified in various settings page.
	 *
	 * @access public
	 * @param int $id_member The ID of the member whose profile is currently being accessed
	 * @return array(title, description, config_vars)
	 */
	public function getProfile($id_member)
	{
		global $txt;

		$name = $this->getName();
		return array($txt['notifier_' . $name . '_title'], $txt['notifier_' . $name . '_desc'], array());
	}

	/**
	 * Callback for profile area, called when saving the profile area.
	 *
	 * @access public
	 * @param int $id_member The ID of the member whose profile is currently being accessed
	 * @param array $settings A key => value pair of the fed settings
	 * @return void
	 */
	public function saveProfile($id_member, array $settings)
	{
	}

	/**
	 * E-mail handler. Returns the subject (if it's a single notification), and body.
	 *
	 * @access public
	 * @param Notification $notification
	 * @return array(subject, body)
	 */
	public function getEmail(Notification $notification)
	{
		global $txt;

		$name = $this->getName();
		return array($txt['notifier_' . $name . '_subject'], $this->getText($notification, true));
	}

	/**
	 * A notifier can add an icon which'll show alongside each notification.
	 * By default, we pass the member's avatar.
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string
	 */
	public function getIcon(Notification $notification)
	{
		global $memberContext;

		$member = $notification->getMemberFrom();
		if (empty($memberContext[$member]['avatar']))
			loadMemberAvatar($member, true);
		if (empty($memberContext[$member]['avatar']))
			return '';
		return $memberContext[$member]['avatar']['image'];
	}

	/**
	 * Returns the preview of the notification, to be displayed on notification view.
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string
	 */
	public function getPreview(Notification $notification)
	{
		global $txt;

		// By default, we'll be retrieving a topic post.
		// Override this method to retrieve something else, if needed.
		$data = $notification->getData();
		$raw = get_single_post($notification->getObject());

		if ($raw !== false)
			return $raw;

		// Since this is a topic post, if it's gone, give the natural error message.
		loadLanguage('Errors');
		return '<div class="errorbox">' . $txt['topic_gone'] . '</div>';
	}

	/**
	 * Returns all the preferences for this notifier.
	 *
	 * @access public
	 * @param int $id_member If NULL, the current member is assumed
	 * @return array
	 */
	public function getPrefs($id_member = null)
	{
		$all_prefs = weNotif::getPrefs($id_member ? $id_member : MID);
		return $all_prefs[$this->getName()];
	}

	/**
	 * Shorthand for returning a single preference for the specific member.
	 *
	 * @access public
	 * @param string $key
	 * @param int $id_member If NULL, assumed current member
	 * @return mixed
	 */
	public function getPref($key, $id_member = null)
	{
		$prefs = $this->getPrefs($id_member);
		return !empty($prefs[$key]) ? $prefs[$key] : null;
	}

	/**
	 * Saves a specific member's preference for this notifier.
	 *
	 * @access public
	 * @param string $key
	 * @param mixed $value
	 * @param int $id_member If NULL, assumed current member
	 * @return void
	 */
	public function savePref($key, $value, $id_member = null)
	{
		$prefs = weNotif::getPrefs($id_member ? $id_member : MID);

		if (empty($prefs[$this->getName()]))
			$prefs[$this->getName()] = array();

		$prefs[$this->getName()][$key] = $value;

		weNotif::savePrefs($id_member ? $id_member : MID, $prefs);
	}

	/**
	 * This hook is called right before any actual notification is sent
	 * If the function returns false, the notification is not sent.
	 *
	 * @access public
	 * @param array &$members An array of members with ID as the key
	 * @param int &$id_object
	 * @param array &$data Any data passed to the Notification::issue
	 * @return bool
	 */
	public function beforeNotify(array &$members, &$id_object, array &$data)
	{
		return true;
	}

	/**
	 * This hook is called after notifications have been issued.
	 *
	 * @access public
	 * @param array $notifications
	 * @return void
	 */
	public function afterNotify(array $notifications)
	{
	}
}
