<?php
/**
 * Wedge
 *
 * Contains base notifier class which is to be extended to implement notifiers
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('File cannot be requested directly.');

/**
 * Notifier interface, every notifier adding their own stuff must implement this interface
 */
abstract class Notifier
{
	/**
	 * Callback for getting the URL of the object
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string A fully qualified HTTP URL
	 */
	abstract public function getURL(Notification $notification);

	/**
	 * Callback for getting the text to display on the notification screen
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string The text this notification wants to display
	 */
	abstract public function getText(Notification $notification);

	/**
	 * Returns the name of this notifier
	 *
	 * @access public
	 * @return string
	 */
	abstract public function getName();

	/**
	 * Callback for handling multiple notifications on the same object
	 *
	 * @access public
	 * @param Notification $notification
	 * @param array &$data Reference to the new notification's data, if something needs to be altered
	 * @param array &$email_data Any extra e-mail data passed
	 * @return bool, if false then a new notification is not created but the current one's time is updated
	 */
	public function handleMultiple(Notification $notification, array &$data, array &$email_data)
	{
		return true;
	}

	/**
	 * Returns the elements for notification's profile area
	 * The third parameter of the array, config_vars, is same as the settings config vars specified in
	 * various settings page
	 *
	 * @access public
	 * @param int $id_member The ID of the member whose profile is currently being accessed
	 * @return array(title, description, config_vars)
	 */
	abstract public function getProfile($id_member);

	/**
	 * Callback for profile area, called when saving the profile area
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
	 * E-mail handler, must be present since the user has the ability to receive e-mail
	 * from any notifier. This only applies for instant e-mail notification, otherwise
	 * for periodicals standard notification text is sent. This is to prevent overbearing
	 * information in the notification e-mail
	 *
	 * @access public
	 * @param Notification $notification
	 * @param array $email_data
	 * @return array(subject, body)
	 */
	abstract public function getEmail(Notification $notification, array $email_data);

	/**
	 * A notifier can add an icon which'll show alongside each notification, by default
	 * we pass the member's avatar
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
	 * Returns the preview of the notification, to be displayed on notification view
	 *
	 * @access public
	 * @param Notification $notification
	 * @return string
	 */
	public function getPreview(Notification $notification)
	{
	}

	/**
	 * Returns all the preferences for this notifier
	 *
	 * @access public
	 * @param int $id_member If NULL, the current member is assumed
	 * @return array
	 */
	public function getPrefs($id_member = null)
	{
		$all_prefs = weNotif::getPrefs($id_member ? $id_member : we::$id);
		return $all_prefs[$this->getName()];
	}

	/**
	 * Shorthand for returning a single preference for the specific member
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
	 * Saves a specific member's preference for this notifier
	 *
	 * @access public
	 * @param string $key
	 * @param mixed $value
	 * @param int $id_member If NULL, assumed current member
	 * @return void
	 */
	public function savePref($key, $value, $id_member = null)
	{
		$prefs = weNotif::getPrefs($id_member ? $id_member : we::$id);

		if (empty($prefs[$this->getName()]))
			$prefs[$this->getName()] = array();

		$prefs[$this->getName()][$key] = $value;

		weNotif::savePrefs($id_member ? $id_member : we::$id, $prefs);
	}

	/**
	 * This hook is called right before any actual notification is sent
	 * If the function returns false, the notification is not sent.
	 *
	 * @access public
	 * @param array &$members An array of members with ID as the key
	 * @param int &$id_object
	 * @param array &$data Any data passed to the Notification::issue
	 * @param array &$email_data
	 * @return bool
	 */
	public function beforeNotify(array &$members, &$id_object, array &$data, array &$email_data)
	{
		return true;
	}

	/**
	 * This hook is called after notifications have been issued
	 *
	 * @access public
	 * @param array $notifications
	 * @return void
	 */
	public function afterNotify(array $notifications)
	{
	}
}
