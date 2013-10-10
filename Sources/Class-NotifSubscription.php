<?php
/**
 * Handles individual notification subscriptions
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

/**
 * Handles individual and overall subscription's records.
 */
class NotifSubscription
{
	protected $member;
	protected $object;
	protected $subs;

	/**
	 * Fetches a single subscription for a given member.
	 *
	 * @static
	 * @access public
	 * @param NotifSubscriber $subs
	 * @param int $object
	 * @param int $member (Defaults on current member if null)
	 * @return NotifSubscription
	 */
	public static function get(NotifSubscriber $subs, $object, $member = null)
	{
		if ($member == null)
			$member = we::$id;

		$query = wesql::query('
			SELECT id_member, id_object
			FROM {db_prefix}notif_subs
			WHERE id_member = {int:member}
				AND id_object = {int:object}
				AND type = {string:type}
			LIMIT 1', array(
				'member' => $member,
				'object' => $object,
				'type' => $subs->getName(),
			)
		);

		if (wesql::num_rows($query) == 0)
			return false;

		list ($id_member, $id_object) = wesql::fetch_row($query);
		wesql::free_result($query);

		return new self($id_member, $id_object, $subs);
	}

	/**
	 * Stores the new subscription record, does no security check.
	 *
	 * @static
	 * @access public
	 * @param NotifSubscriber $subs
	 * @param int $object
	 * @param int $member (Defaults on current member if null)
	 * @return NotifSubscription
	 */
	public static function store(NotifSubscriber $subs, $object, $member = null)
	{
		if ($member == null)
			$member = we::$id;

		wesql::insert('', '{db_prefix}notif_subs',
			array('id_member' => 'int', 'id_object' => 'int', 'type' => 'string', 'starttime' => 'int'),
			array($member, $object, $subs->getName(), time())
		);

		return new self($member, $object, $subs);
	}

	/**
	 * Issues a specific notification to all the members subscribed
	 * to the specific notification, returns all the notifications issued.
	 *
	 * @static
	 * @access public
	 * @param NotifSubscriber $subs
	 * @param string $notifier Notifier name
	 * @param int $object
	 * @param array $data
	 * @return array of Notification
	 */
	public static function issue(NotifSubscriber $subs, string $notifier, $object, array $data)
	{
		// Fetch all the members having this subscription
		$query = wesql::query('
			SELECT id_member
			FROM {db_prefix}notif_subs
			WHERE type = {string:type}
				AND id_object = {int:object}',
			array(
				'type' => $subs->getName(),
				'object' => $object,
			)
		);
		if (wesql::num_rows($query) == 0)
			return array();

		$notifications = array();
		$members = array();

		while ($row = wesql::fetch_row($query))
			if ($row[0] != we::$id)
				$members[] = $row[0];

		wesql::free_result($query);

		if (empty($members))
			return array();

		$notifications = Notification::issue($notifier, $members, $object, $data);

		return $notifications;
	}

	/**
	 * Basic constructor, basically cannot be called from outside
	 * and only through self::get.
	 *
	 * @access protected
	 * @param int $member
	 * @param int $object
	 * @param NotifSubscriber $subs
	 * @return void
	 */
	protected function __construct($member, $object, $subs)
	{
		$this->member = (int) $member;
		$this->object = (int) $object;
		$this->subs = $subs;
	}

	/**
	 * Deletes this subscription record.
	 *
	 * @access public
	 * @return void
	 */
	public function delete()
	{
		wesql::query('
			DELETE FROM {db_prefix}notif_subs
			WHERE id_member = {int:member}
				AND id_object = {int:object}
				AND type = {string:type}',
			array(
				'member' => $this->member,
				'object' => $this->object,
				'type' => $this->subs->getName(),
			)
		);
	}
}
