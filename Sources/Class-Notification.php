<?php
/**
 * Wedge
 *
 * Contains class for handling individual notification and issuing them
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
 * For each individual notification
 * The aim for abstracting this into a class is mainly for readibility and sensibility
 * Passing arrays is a tad more confusing and error-prone
 */
class Notification
{
	/**
	 * Stores the basic notification information
	 */
	protected $id;
	protected $id_member;
	protected $id_member_from;
	protected $notifier;
	protected $id_object;
	protected $time;
	protected $unread;
	protected $data;

	/**
	 * Gets the notifications
	 *
	 * @static
	 * @access public
	 * @param int $id If specified, then fetches the notification of this ID
	 * @param int $id_member If specified, then fetches the notification of this member
	 * @param int $count 0 for no limit
	 * @param bool $unread (Optional) Whether to fetch only unread notifications or not
	 * @param int $object (Optional) If specified, limits it down to one object
	 * @param string $notifier (Optional) If specified, limits it down to the notifier
	 * @return array
	 */
	public static function get($id = null, $id_member = null, $count = 1, $unread = false, $object = null, $notifier = '')
	{
		if (empty($id) && empty($id_member))
			return array();

		$request = wesql::query('
			SELECT *
			FROM {db_prefix}notifications
			WHERE ' . (!empty($id) ? 'id_notification = {int:id}' : '1=1') . (!empty($id_member) ? '
				AND id_member = {int:member}' : '') . ($unread ? '
				AND unread = 1' : '') . (!empty($object) ? '
				AND id_object = {int:object}' : '') . (!empty($notifier) ? '
				AND notifier = {string:notifier}' : '') . '
			ORDER BY time DESC' . ($count ? '
			LIMIT {int:count}' : ''),
			array(
				'id' => (int) $id,
				'member' => (int) $id_member,
				'count' => (int) $count,
				'object' => (int) $object,
				'notifier' => $notifier,
			));
		return self::fetchNotifications($request);
	}

	/**
	 * Fetches notifications from a query and arranges them in an array
	 *
	 * @static
	 * @access protected
	 * @return array
	 */
	protected static function fetchNotifications($request)
	{
		$notifications = array();
		$notifiers = weNotif::getNotifiers();

		while ($row = wesql::fetch_assoc($request))
		{
			// Make sure the notifier for this exists
			if (!isset($notifiers[$row['notifier']]))
				continue;

			$notifications[] = new Notification($row, $notifiers[$row['notifier']]);
		}

		wesql::free_result($request);

		return $notifications;
	}

	/**
	 * Marks notification as read for a specific member, notifier and object
	 *
	 * @static
	 * @access public
	 * @param int $id_member
	 * @param Notifier $notifier
	 * @param array $id_object
	 * @return void
	 */
	public static function markReadForNotifier($id_member, Notifier $notifier, $objects)
	{
		// Oh goody, we have stuff to mark as unread
		wesql::query('
			UPDATE {db_prefix}notifications
			SET unread = 0
			WHERE id_member = {int:member}
				AND id_object IN ({array_int:object})
				AND notifier = {string:notifier}
				AND unread = 1',
			array(
				'member' => (int) $id_member,
				'object' => (array) $objects,
				'notifier' => $notifier->getName(),
			)
		);
		$affected_rows = wesql::affected_rows();

		if ($affected_rows > 0)
		{
			wesql::query('
				UPDATE {db_prefix}members
				SET unread_notifications = unread_notifications - {int:count}
				WHERE id_member = {int:member}',
				array(
					'count' => $affected_rows,
					'member' => (int) $id_member,
				)
			);

			// Flush the cache
			cache_put_data('quick_notification_' . $id_member, null, 86400);
		}
	}

	/**
	 * Issues a new notification to a member, also calls the hook
	 * If an array of IDs is passed as $id_member, then the same
	 * notification is issued for all the members
	 *
	 * @static
	 * @access public
	 * @param array $id_member
	 * @param Notifier $notifier
	 * @param int $id_object
	 * @param array $data
	 * @param array $email_data
	 * @return Notification
	 * @throws Exception, upon the failure of creating a notification for whatever reason
	 */
	public static function issue($id_member, Notifier $notifier, $id_object, $data = array(), $email_data = array())
	{
		loadSource('Subs-Post');

		$id_object = (int) $id_object;
		if (empty($id_object))
			throw new Exception('Object cannot be empty for notification');

		$members = (array) $id_member;
		$return_single = !is_array($id_member);

		// Load the pending member's preferences for checking email notification and disabled notifiers
		$request = wesql::query('
			SELECT data, email_address, id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member})
			LIMIT {int:limit}',
			array(
				'member' => $members,
				'limit' => count($members),
			)
		);
		$members = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$member_data = empty($row['data']) ? array() : unserialize($row['data']);
			$members[$row['id_member']] = array(
				'id' => $row['id_member'],
				'disabled_notifiers' => empty($member_data['disabled_notifiers']) ? array() : $member_data['disabled_notifiers'],
				'email_notifiers' => empty($member_data['email_notifiers']) ? array() : $member_data['email_notifiers'],
				'email' => $row['email_address'],
			);
		}
		wesql::free_result($request);

		// Run this by the notifier before we do anything else
		if (!$notifier->beforeNotify($members, $id_object, $data, $email_data))
			return false;

		// Load the members' unread notifications for handling multiples
		$request = wesql::query('
			SELECT *
			FROM {db_prefix}notifications
			WHERE notifier = {string:notifier}
				AND id_member IN ({array_int:member})
				AND id_object = {int:object}
				AND unread = 1
			LIMIT {int:limit}',
			array(
				'notifier' => $notifier->getName(),
				'object' => $id_object,
				'member' => array_keys($members),
				'limit' => count($members),
			)
		);
		// If we do, then we run it by the notifier
		while ($row = wesql::fetch_assoc($request))
		{
			$notification = new Notification($row, $notifier);

			// If the notifier returns false, we drop this notification
			if (!$notifier->handleMultiple($notification, $data, $email_data)
				&& !in_array($notifier->getName(), $members[$row['id_member']]['disabled_notifiers']))
			{
				$notification->updateTime();
				unset($members[$row['id_member']]);

				if (!empty($members[$row['id_member']]['email_notifiers'][$notifier->getName()])
					&& $members[$row['id_member']]['email_notifiers'][$notifier->getName()] === 1)
				{
					list ($subject, $body) = $notifier->getEmail($notification, $email_data);
					sendemail($members[$row['id_member']]['email'], $subject, $body);
				}
			}
		}
		wesql::free_result($request);

		$time = time();

		if (empty($members))
			return array();

		// Process individual member's notification now
		$notifications = array();
		foreach ($members as $id_member => $pref)
		{
			if (in_array($notifier->getName(), $pref['disabled_notifiers']))
				continue;

			// Create the row
			wesql::insert('', '{db_prefix}notifications',
				array('id_member' => 'int', 'id_member_from' => 'int', 'notifier' => 'string-50', 'id_object' => 'int', 'time' => 'int', 'unread' => 'int', 'data' => 'string'),
				array($id_member, we::$id, $notifier->getName(), $id_object, $time, 1, serialize((array) $data)),
				array('id_notification')
			);
			$id_notification = wesql::insert_id();

			if (!empty($id_notification))
			{
				$notifications[$id_member] = new self(array(
					'id_notification' => $id_notification,
					'id_member' => $id_member,
					'id_member_from' => we::$id,
					'id_object' => $id_object,
					'time' => $time,
					'unread' => 1,
					'data' => serialize((array) $data),
				), $notifier);

				// Send the e-mail?
				$notifier_name = $notifier->getName();
				if (!empty($pref['email_notifiers'][$notifier_name]) && $pref['email_notifiers'][$notifier_name] === 1)
				{
					list ($subject, $body) = $notifier->getEmail($notifications[$id_member], $email_data);

					sendmail($pref['email'], $subject, $body);
				}

				// Flush the cache
				cache_put_data('quick_notification_' . $id_member, null, 86400);
			}
			else
				throw new Exception('Unable to create notification');
		}

		// Update the unread notification count
		wesql::query('
			UPDATE {db_prefix}members
			SET unread_notifications = unread_notifications + 1
			WHERE id_member IN ({array_int:member})',
			array(
				'member' => array_keys($notifications),
			)
		);

		// Run the post notify hook
		$notifier->afterNotify($notifications);

		// Run the general hook
		call_hook('notification_new', array($notifications));

		return $return_single ? array_pop($notifications) : $notifications;
	}

	/**
	 * Constructor, just initializes the member variables...
	 *
	 * @access public
	 * @param array $row The DB row of this notification (Entirely done in order to prevent typing...)
	 * @param Notifier $notifier The notifier's instance
	 * @return void
	 */
	public function __construct(array $row, Notifier $notifier)
	{
		// Store the data
		$this->id = $row['id_notification'];
		$this->id_member = $row['id_member'];
		$this->id_member_from = $row['id_member_from'];
		$this->notifier = $notifier;
		$this->id_object = $row['id_object'];
		$this->time = (int) $row['time'];
		$this->unread = $row['unread'];
		$this->data = unserialize($row['data']);
	}

	/**
	 * Marks the current notification as read
	 *
	 * @access public
	 * @return void
	 */
	public function markAsRead()
	{
		if ($this->unread == 0)
			return;

		$this->unread = 0;
		$this->updateCol('unread', 0);

		// Update the unread notification count
		wesql::query('
			UPDATE {db_prefix}members
			SET unread_notifications = unread_notifications - 1
			WHERE id_member = {int:member}',
			array(
				'member' => $this->getMember(),
			)
		);

		// Flush the cache
		cache_put_data('quick_notification_' . $this->getMember(), null, 86400);
	}

	/**
	 * Updates the data of this notification
	 *
	 * @access public
	 * @param array $data
	 * @return void
	 */
	public function updateData(array $data)
	{
		$this->data = (array) $data;
		$this->updateCol('data', serialize((array) $data));
	}

	/**
	 * Updates the time of this notification
	 *
	 * @access public
	 * @return void
	 */
	public function updateTime()
	{
		$this->time = time();
		$this->updateCol('time', time());
	}

	/**
	 * Internal function for updating a column
	 *
	 * @access protected
	 * @param string $column
	 * @param string $value
	 * @return void
	 */
	protected function updateCol($column, $value)
	{
		wesql::query('
			UPDATE {db_prefix}notifications
			SET {raw:column} = {string:value}
			WHERE id_notification = {int:notification}',
			array(
				'column' => addslashes($column),
				'value' => $value,
				'notification' => $this->getID(),
			)
		);
	}

	/**
	 * Returns this notification's ID
	 *
	 * @access public
	 * @return int
	 */
	public function getID()
	{
		return $this->id;
	}

	/**
	 * Returns the text for this notification
	 *
	 * @access public
	 * @return string
	 */
	public function getText()
	{
		return $this->notifier->getText($this);
	}

	/**
	 * Returns the icon for this notification, usually an avatar.
	 *
	 * @access public
	 * @return string
	 */
	public function getIcon()
	{
		return $this->notifier->getIcon($this);
	}

	/**
	 * Returns the URL for this notification
	 *
	 * @access public
	 * @return string
	 */
	public function getURL()
	{
		return $this->notifier->getURL($this);
	}

	/**
	 * Returns a contextual preview for this notification
	 *
	 * @access public
	 * @return string
	 */
	public function getPreview()
	{
		return $this->notifier->getPreview($this);
	}

	/**
	 * Returns the object's notifier
	 *
	 * @access public
	 * @return object
	 */
	public function getNotifier()
	{
		return $this->notifier;
	}

	/**
	 * Returns this notification's associated object's ID
	 *
	 * @access public
	 * @return int
	 */
	public function getObject()
	{
		return $this->id_object;
	}

	/**
	 * Returns this notification's data
	 *
	 * @access public
	 * @return array
	 */
	public function getData()
	{
		return $this->data;
	}

	/**
	 * Returns this notification's time
	 *
	 * @access public
	 * @return int
	 */
	public function getTime()
	{
		return $this->time;
	}

	/**
	 * Returns this notification's unread status
	 *
	 * @access public
	 * @return int (0, 1)
	 */
	public function getUnread()
	{
		return $this->unread;
	}

	/**
	 * Returns this notification's member's ID
	 *
	 * @access public
	 * @return int
	 */
	public function getMember()
	{
		return $this->id_member;
	}

	/**
	 * Returns the issuing member's ID
	 *
	 * @access public
	 * @return int
	 */
	public function getMemberFrom()
	{
		return $this->id_member_from;
	}
}
