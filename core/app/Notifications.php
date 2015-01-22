<?php
/**
 * Contains functions for handling the entire notification system.
 * The original notification system code was written by Dragooon
 * under the Modified BSD license, used with permission.
 * https://github.com/Dragooon/WeNotif - © Shitiz Garg 2012
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('File cannot be requested directly.');

/**
 * Class for handling notification hooks and actions.
 */
class weNotif
{
	protected static $notifiers = array();
	protected static $quick_count = 25;
	protected static $disabled = array();
	protected static $pref_cache = array();
	protected static $subscribers = array();

	/**
	 * Returns the notifiers.
	 *
	 * @static
	 * @access public
	 * @param string $notifier If specified, only returns this notifier.
	 * @return array
	 */
	public static function getNotifiers($notifier = null)
	{
		return !empty($notifier) ? (!empty(self::$notifiers[$notifier]) ? self::$notifiers[$notifier] : null) : self::$notifiers;
	}

	/**
	 * Returns the subscribers.
	 *
	 * @static
	 * @access public
	 * @param string $subscriber If specified, only returns this subscriber.
	 * @return array
	 */
	public static function getSubscribers($subscriber = null)
	{
		return !empty($subscriber) ? (!empty(self::$subscribers[$subscriber]) ? self::$subscribers[$subscriber] : null) : self::$subscribers;
	}

	/**
	 * Checks if a notifier is disabled or not for this user.
	 *
	 * @static
	 * @access public
	 * @param Notifier $notifier
	 * @return bool
	 */
	public static function isNotifierDisabled(Notifier $notifier)
	{
		return we::$is_member && in_array($notifier->getName(), self::$disabled);
	}

	/**
	 * Calls notification_callback hook for registering notification hooks.
	 * Also loads notification for this user's quick view.
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function initialize()
	{
		global $context;

		loadSource(array(
			'Class-Notification',
			'Class-Notifier',
			'Class-NotifSubscriber',
			'Class-NotifSubscription',
		));

		// Register the notifiers
		if (MID)
		{
			loadSource(array('notifiers/Likes', 'notifiers/Move'));
			self::$notifiers['move'] = new Move_Notifier();
			self::$notifiers['likes'] = new Likes_Notifier();
			self::$notifiers['likes_thought'] = new Likes_Thought_Notifier();
		}

		call_hook('notification_callback', array(&self::$notifiers));

		foreach (self::$notifiers as $notifier => $object)
		{
			unset(self::$notifiers[$notifier]);
			if ($object instanceof Notifier)
				self::$notifiers[$object->getName()] = $object;
		}

		// Register the subscribers
		call_hook('notification_subscription', array(&self::$subscribers));

		foreach (self::$subscribers as $type => $object)
			if (!($object instanceof NotifSubscriber) || self::isNotifierDisabled($object->getNotifier()))
				unset(self::$subscribers[$type]);

		loadLanguage('Notifications');

		// Load quick notifications
		if (MID)
		{
			$context['unread_notifications'] = we::$user['unread_notifications'];
			$disabled_notifiers = !empty(we::$user['data']['disabled_notifiers']) ? we::$user['data']['disabled_notifiers'] : array();
			$prefs = !empty(we::$user['data']['notifier_prefs']) ? we::$user['data']['notifier_prefs'] : array();

			// Automatically cache the current member's notifier preferences, save us some queries.
			self::$pref_cache[MID] = $prefs;

			self::$disabled = $disabled_notifiers;

			add_js_inline('
	we_notifs = ', (int) $context['unread_notifications'], ';');
		}
	}

	/**
	 * Caches and loads quick notifications, also serializes them into an array for quick access.
	 *
	 * @static
	 * @access protected
	 * @return array
	 */
	protected static function get_quick_notifications()
	{
		$notifications = cache_get_data('quick_notification_' . MID, 86400);

		// Nothing in cache? Build it.
		if ($notifications === null)
		{
			$notifications = Notification::get(null, MID, self::$quick_count);
			cache_put_data('quick_notification_' . MID, $notifications, 86400);
		}

		$notifs = $notification_members = array();
		foreach ($notifications as $notification)
			$notification_members[] = $notification->getMemberFrom();
		loadMemberData($notification_members);

		foreach ($notifications as $notification)
			$notifs[] = array(
				'id' => $notification->getID(),
				'unread' => $notification->getUnread(),
				'text' => $notification->getText(),
				'icon' => $notification->getIcon(),
				'url' => $notification->getURL(),
				'time' => timeformat($notification->getTime()),
			);

		return $notifs;
	}

	public static function unread_count($id_member = 0)
	{
		$request = wesql::query('
			SELECT unread_notifications
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => $id_member ?: MID,
			)
		);
		list ($unread) = wesql::fetch_row($request);
		wesql::free_result($request);

		return $unread;
	}

	/**
	 * Loads a specific member's notifier preferences.
	 *
	 * @static
	 * @access public
	 * @param int $id_member
	 * @return array
	 */
	public static function getPrefs($id_member)
	{
		if (isset(self::$pref_cache[$id_member]))
			return self::$pref_cache[$id_member];

		$request = wesql::query('
			SELECT data
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => $id_member,
			)
		);
		list ($data) = wesql::fetch_assoc($request);
		wesql::free_result($request);

		$data = unserialize($data);
		self::$pref_cache[$id_member] = !empty($data['notifier_prefs']) ? $data['notifier_prefs'] : array();

		return self::$pref_cache[$id_member];
	}

	/**
	 * Saves a specific member's notifier preferences. Not really
	 * meant to be used directly, but via Notifier interface.
	 *
	 * @static
	 * @access public
	 * @param int $id_member
	 * @param array $prefs
	 * @return void
	 */
	public static function savePrefs($id_member, array $prefs)
	{
		unset(self::$pref_cache[$id_member]);

		$request = wesql::query('
			SELECT data
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => $id_member,
			)
		);
		list ($data) = wesql::fetch_assoc($request);
		wesql::free_result($request);

		$data = unserialize($data);
		$data['notifier_prefs'] = $prefs;
		updateMemberData($id_member, array('data' => serialize($data)));
	}

	/**
	 * Handles the notification action.
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function action()
	{
		global $context, $txt, $settings, $user_settings;

		$sa = !empty($_REQUEST['sa']) ? $_REQUEST['sa'] : '';

		if (we::$is_guest)
			fatal_lang_error('no_access', $sa == 'unread' ? false : 'general');

		if ($sa == 'redirect' && isset($_REQUEST['in']))
		{
			// We are accessing a notification and redirecting to its target
			list ($notification) = Notification::get((int) $_REQUEST['in'], MID);

			// Not found?
			if (empty($notification))
				fatal_lang_error('notification_not_found');

			// Mark this as read
			$notification->markAsRead();

			// Redirect to the target
			redirectexit($notification->getURL());
		}

		if ($sa == 'subscribe' || $sa == 'unsubscribe')
		{
			checkSession('get');

			$object = (int) $_REQUEST['object'];
			$type = strtolower(trim($_REQUEST['type']));

			if (empty($object) || empty($type) || we::$is_guest || !isset(self::$subscribers[$type]))
				fatal_lang_error('wenotif_subs_object_type_empty');

			// Run it by the subscription objects and see if
			// this thing is subscribable (is that a word?)
			if (!self::$subscribers[$type]->isValidObject($object))
				fatal_lang_error('wenotif_subs_invalid_object');

			$subscription = NotifSubscription::get(self::$subscribers[$type], $object);
			if (($sa == 'subscribe' && $subscription !== false) || ($sa == 'unsubscribe' && $subscription === false))
				fatal_lang_error('wenotif_subs_' . ($sa == 'subscribe' ? 'already' : 'not') . '_subscribed');

			if ($sa == 'subscribe')
				NotifSubscription::store(self::$subscribers[$type], $object);
			else
				$subscription->delete();

			redirectexit(self::$subscribers[$type]->getURL($object));
		}

		if ($sa == 'unread')
			return_raw(self::unread_count() . ';' . (!empty($settings['pm_enabled']) ? we::$user['unread_messages'] : -1));

		if ($sa == 'markread' && isset($_REQUEST['in']))
		{
			$notifications = Notification::get($_REQUEST['in'], MID);

			if (!empty($notifications[0]))
				$notifications[0]->markAsRead();

			if (AJAX)
				exit();
			redirectexit();
		}

		if ($sa == 'preview' && isset($_REQUEST['in']))
		{
			$notifications = Notification::get($_REQUEST['in'], MID);

			// Return the preview, and possibly some CSS or JS generated by it, y'never know.
			$preview = !empty($notifications[0]) ? $notifications[0]->getPreview() : '';
			return_raw($context['header'] . $preview . '<script>' . $context['footer_js'] . '</script>');
		}
		elseif (!empty($sa) && !empty(self::$notifiers[$sa]) && is_callable(self::$notifiers[$sa], 'action'))
			return self::$notifiers[$sa]->action();

		// Otherwise we're displaying all the notifications this user has.
		loadTemplate('Notifications');
		wetem::load('notifications_list');
		if (AJAX)
			wetem::hide();
		else
			$context['page_title'] = $txt['notifications'];

		if (isset($_GET['show']))
			updateMyData(array('n_all' => $_GET['show'] == 'latest'));

		// Was the user notified in a visible way of a notification..? Then opening the popup should end this.
		if (!empty($user_settings['hey_not']))
			updateMemberData(MID, array('hey_not' => 0));

		// Show read & unread notifications, unless we're calling from AJAX and the user didn't specify they wanted all of them.
		$context['notifications'] = (array) Notification::get(null, MID, 0, AJAX && empty(we::$user['data']['n_all']));
		$notification_members = array();
		foreach ($context['notifications'] as $notif)
			$notification_members[] = $notif->getMemberFrom();
		loadMemberData($notification_members);

		$context['unread_count'] = self::unread_count();
	}

	/**
	 * Handles our profile area.
	 *
	 * @static
	 * @access public
	 * @param int $memID
	 * @return void
	 */
	public static function profile($memID)
	{
		global $context, $txt;

		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['notifications'],
			'description' => $txt['notification_profile_desc'],
			'icon' => 'profile_sm.gif',
			'tabs' => array(
				'general' => array(),
				'posts' => array(),
			),
		);

		// Did we want the old system, instead..?
		if (isset($_GET['sa']) && $_GET['sa'] == 'posts')
		{
			loadSource('Profile-Modify');
			wetem::rename('weNotif::profile', 'notification');
			$context[$context['profile_menu_name']]['tab_data']['description'] = $txt['notification_info'];
			notification($memID);
			return;
		}

		// Not the same user? Hell no.
		if ($memID != MID)
			fatal_lang_error('no_access');

		$notifiers = self::getNotifiers();

		$request = wesql::query('
			SELECT data, notify_email_period
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => MID,
			)
		);
		list ($data, $period) = wesql::fetch_row($request);
		wesql::free_result($request);

		$data = unserialize($data);
		$disabled_notifiers = !empty($data['disabled_notifiers']) ? $data['disabled_notifiers'] : array();
		$email_notifiers = !empty($data['email_notifiers']) ? $data['email_notifiers'] : array();

		// Store which settings belong to which notifier
		$settings_map = array();

		// Assemble the config_vars for the entire page
		$config_vars = array();
		$config_vars[] = array(
			'int', 'notify_period',
			'text_label' => $txt['notify_period'],
			'subtext' => $txt['notify_period_desc'],
			'value' => (int) $period,
		);
		$config_vars[] = '';

		foreach ($notifiers as $notifier)
		{
			list ($title, $desc, $notifier_config) = $notifier->getProfile(MID);

			// Add the title and desc into the array
			$config_vars[] = array(
				'select', 'disable_' . $notifier->getName(),
				'text_label' => $title,
				'subtext' => $desc,
				'data' => array(
					array(0, $txt['enabled']),
					array(1, $txt['disabled']),
				),
				'value' => in_array($notifier->getName(), $disabled_notifiers)
			);

			// Add the e-mail setting
			$config_vars[] = array(
				'select', 'email_' . $notifier->getName(),
				'value' => !empty($email_notifiers[$notifier->getName()]) ? $email_notifiers[$notifier->getName()] : 0,
				'text_label' => $txt['notification_email'],
				'data' => array(
					array(0, $txt['notify_periodically']),
					array(1, $txt['notify_instantly']),
					array(2, $txt['notify_disable']),
				),
			);

			// Merge this with notifier config
			$config_vars = array_merge($config_vars, $notifier_config);

			// Map the settings
			foreach ($notifier_config as $config)
				if (!empty($config) && !empty($config[1]) && !in_array($config[0], array('message', 'warning', 'title', 'desc')))
					$settings_map[$config[1]] = $notifier->getName();

			$config_vars[] = '';
		}

		unset($config_vars[count($config_vars) - 1]);

		// Saving the settings?
		if (isset($_GET['nsave']))
		{
			$disabled = array();
			$email = array();
			foreach ($notifiers as $notifier)
			{
				if (!empty($_POST['disable_' . $notifier->getName()]))
					$disabled[] = $notifier->getName();
				if (isset($_POST['email_' . $notifier->getName()]))
					$email[$notifier->getName()] = (int) $_POST['email_' . $notifier->getName()];
			}

			$data['disabled_notifiers'] = $disabled;
			$data['email_notifiers'] = $email;

			updateMemberData(MID, array(
				'data' => serialize($data),
				'notify_email_period' => max(1, (int) $_POST['notify_period']),
			));

			// Store the notifier settings
			$notifier_settings = array();
			foreach ($settings_map as $setting => $notifier)
			{
				if (empty($notifier_settings[$notifier]))
					$notifier_settings[$notifier] = array();

				if (!empty($_POST[$setting]))
					$notifier_settings[$notifier][$setting] = $_POST[$setting];
			}

			// Call the notifier callback
			foreach ($notifier_settings as $notifier => $setting)
				$notifiers[$notifier]->saveProfile(MID, $setting);

			redirectexit('action=profile;area=notifications;updated');
		}

		// Load the template and form
		loadSource('ManageServer');
		loadTemplate('Admin');

		prepareDBSettingContext($config_vars);

		$context['post_url'] = '<URL>?action=profile;area=notifications;nsave';

		wetem::load('show_settings');
	}

	/**
	 * Profile area for subscriptions.
	 *
	 * @static
	 * @access public
	 * @param int $memID
	 * @return void
	 */
	public static function subs_profile($memID)
	{
		global $txt, $context;

		$subscriptions = array();
		$starttimes = array();
		foreach (self::$subscribers as $type => $subscriber)
		{
			$subscriptions[$type] = array(
				'type' => $type,
				'subscriber' => $subscriber,
				'profile' => $subscriber->getProfile($memID),
				'objects' => array(),
			);
			$starttimes[$type] = array();
		}

		$request = wesql::query('
			SELECT id_object, type, starttime
			FROM {db_prefix}notif_subs
			WHERE id_member = {int:member}',
			array(
				'member' => $memID,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (isset($subscriptions[$row['type']]))
				$subscriptions[$row['type']]['objects'][] = $row['id_object'];
			$starttimes[$row['type']][$row['id_object']] = timeformat($row['starttime']);
		}

		wesql::free_result($request);

		// Load individual subscription's objects
		foreach ($subscriptions as &$subscription)
			if (!empty($subscription['objects']))
			{
				$subscription['objects'] = $subscription['subscriber']->getObjects($subscription['objects']);

				foreach ($subscription['objects'] as $id => &$object)
					$object['time'] = $starttimes[$subscription['type']][$id];
			}

		$context['notif_subscriptions'] = $subscriptions;
		$context['page_title'] = $txt['notif_subs'];
		loadTemplate('Notifications');
		wetem::load('notification_subs_profile');
	}

	/**
	 * Handles routinely pruning notifications older than x days.
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function scheduled_prune()
	{
		global $settings;

		wesql::query('
			DELETE FROM {db_prefix}notifications
			WHERE unread = 0
				AND time < {int:time}',
			array(
				'time' => time() - ($settings['notifications_prune_days'] * 86400),
			)
		);
	}

	/**
	 * Handles sending periodical notifications to every member.
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function scheduled_periodical()
	{
		global $txt;

		loadSource('Subs-Post');

		// Fetch all the members which have pending e-mails
		$request = wesql::query('
			SELECT id_member, real_name, email_address, data, unread_notifications
			FROM {db_prefix}members
			WHERE unread_notifications > 0
				AND UNIX_TIMESTAMP() > notify_email_last_sent + (notify_email_period * 86400)',
			array()
		);

		$members = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$data = unserialize($row['data']);

			$valid_notifiers = array();

			foreach (self::$notifiers as $notifier)
			{
				$status = isset($data['email_notifiers'][$notifier->getName()]) ? $data['email_notifiers'][$notifier->getName()] : 0;
				if ($status < 2 && (empty($data['disabled_notifiers']) || !in_array($notifier, $data['disabled_notifiers'])))
					$valid_notifiers[$notifier->getName()] = true;
			}

			if (empty($valid_notifiers))
				continue;

			$members[$row['id_member']] = array(
				'id' => $row['id_member'],
				'name' => $row['real_name'],
				'email' => $row['email_address'],
				'valid_notifiers' => $valid_notifiers,
				'notifications' => array(),
				'unread' => $row['unread_notifications'],
			);
		}

		wesql::free_result($request);

		if (empty($members))
			return true;

		// It's cheaper to check for the notifier for the members in a PHP if()
		// rather than checking in a MySQL query of huge IF/ELSE clauses.
		$request = wesql::query('
			SELECT *
			FROM {db_prefix}notifications
			WHERE id_member IN ({array_int:members})
				AND unread = 1',
			array(
				'members' => array_keys($members),
			)
		);

		while ($row = wesql::fetch_assoc($request))
			if (isset($members[$row['id_member']]['valid_notifiers'][$row['notifier']]))
			{
				$mem =& $members[$row['id_member']];

				if (empty($mem['notifications'][$row['notifier']]))
					$mem['notifications'][$row['notifier']] = array();

				$mem['notifications'][$row['notifier']][] = new Notification($row, self::getNotifiers($row['notifier']));
			}

		wesql::free_result($request);

		loadTemplate('Notifications');

		foreach ($members as $m)
		{
			if (empty($m['notifications']))
				continue;

			// Assemble the notifications into one huge text.
			// !! We're setting HTML to false for now, as it saves some bandwidth and CPU time; consider adding a user setting..?
			$use_html = false;
			$body = template_notification_email($m['notifications'], $use_html);
			$plain_body = $use_html ? template_notification_email($m['notifications'], false) : false;
			$subject = sprintf($txt['notification_email_periodical_subject'], $m['name'], $m['unread']);

			sendmail($m['email'], $subject, $body, null, null, $plain_body);
		}

		updateMemberData(array_keys($members), array(
			'notify_email_last_sent' => time(),
		));
	}
}
