<?php
/**
 * Wedge
 *
 * Contains interface for a subcriber
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
 * Base interface that every subscriber should follow.
 */
interface NotifSubscriber
{
	/**
	 * Returns a URL for the object.
	 *
	 * @access public
	 * @param int $object
	 * @return string
	 */
	public function getURL($object);

	/**
	 * Returns this subscription's name.
	 *
	 * @access public
	 * @return string
	 */
	public function getName();

	/**
	 * Returns the Notifier object associated with this subscription.
	 *
	 * @access public
	 * @return Notifier
	 */
	public function getNotifier();

	/**
	 * Checks whether the passed object is valid or not for subscribing.
	 *
	 * @access public
	 * @param int $object
	 * @return bool
	 */
	public function isValidObject($object);

	/**
	 * Returns text for profile areas which will be displayed to the user
	 * Returned array will be formatted like:
	 *
	 * array(
	 *		label => The title of the subscriber
	 *		description => Any additional help text to be displayed for the user
	 * )
	 *
	 * @access public
	 * @param int $id_member
	 * @return array
	 */
	public function getProfile($id_member);

	/**
	 * Returns the ID, name and an URL for the passed objects for this
	 * subscriber. Returned array will be formatted like:
	 *
	 * array(
	 *		[Object's ID] => array(
	 *			id => Object's ID
	 *			title => Plain text identifying the object (Topic's title, member's name etc)
	 *			href => A fully qualified URL for the object
	 *		)
	 * )
	 *
	 * @access public
	 * @param array $objects IDs of the objects to fetch
	 * @return array
	 */
	public function getObjects(array $objects);
}
