<?php
/**
 * Wedge
 *
 * Contains functions for handling the entire notification system
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
 * Class for handling notification hooks and actions
 */
class weNotif
{
	protected static $notifiers = array();
	protected static $quick_count = 25;
	protected static $disabled = array();
	protected static $pref_cache = array();

	/**
	 * Returns the notifiers
	 *
	 * @static
	 * @access public
	 * @param string $notifier If specified, only returns this notifier
	 * @return array
	 */
	public static function getNotifiers($notifier = null)
	{
		return !empty($notifier) ? (!empty(self::$notifiers[$notifier]) ? self::$notifiers[$notifier] : null) : self::$notifiers;
	}

	/**
	 * Checks if a notifier is disabled or not for this user
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
	 * Calls notification_callback hook for registering notification hooks
	 * Also loads notification for this user's quick view
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function initialize()
	{
		global $context, $txt;

		loadSource(array(
			'Class-Notification',
			'Class-Notifier',
		));

		// Register the notifiers
		if (!empty(we::$id))
		{
			loadSource('notifiers/Likes');
			self::$notifiers['likes'] = new Likes_Notifier();
		}

		call_hook('notification_callback', array(&self::$notifiers));

		foreach (self::$notifiers as $notifier => $object)
		{
			unset(self::$notifiers[$notifier]);
			if ($object instanceof Notifier)
				self::$notifiers[$object->getName()] = $object;
		}

		loadLanguage('Notifications');

		// Load quick notifications
		if (!empty(we::$id))
		{
			$context['unread_notifications'] = we::$user['unread_notifications'];
			$disabled_notifiers = !empty(we::$user['data']['disabled_notifiers']) ? we::$user['data']['disabled_notifiers'] : array();
			$prefs = !empty(we::$user['data']['notifier_prefs']) ? we::$user['data']['notifier_prefs'] : array();

			// Automatically cache the current member's notifier preferences, save us some queries.
			self::$pref_cache[we::$id] = $prefs;

			self::$disabled = $disabled_notifiers;

			wetem::insert(
				array(
					'search_box' => 'after',
					'sidebar' => 'add',
					'default' => 'first'
				),
				'notifications'
			);

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
		global $context;

		$notifications = cache_get_data('quick_notification_' . we::$id, 86400);

		// Nothing in cache? Build it.
		if ($notifications === null)
		{
			$notifications = Notification::get(null, we::$id, self::$quick_count);
			cache_put_data('quick_notification_' . we::$id, $notifications, 86400);
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

	/**
	 * Loads a specific member's notifier preferences
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
	 * Saves a specific member's notifier preferences. Not really meant to be used
	 * directly but via Notifier interface
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
	 * Handles the notification action
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function action()
	{
		global $context, $txt;

		$sa = !empty($_REQUEST['sa']) ? $_REQUEST['sa'] : '';

		if (we::$is_guest)
			fatal_lang_error('no_access', $sa == 'unread' ? false : 'general');

		if ($sa == 'redirect' && isset($_REQUEST['in']))
		{
			// We are accessing a notification and redirecting to its target
			list ($notification) = Notification::get((int) $_REQUEST['in'], we::$id);

			// Not found?
			if (empty($notification))
				fatal_lang_error('notification_not_found');

			// Mark this as read
			$notification->markAsRead();

			// Redirect to the target
			redirectexit($notification->getURL());
		}
		elseif ($sa == 'unread')
		{
			$request = wesql::query('
				SELECT unread_notifications
				FROM {db_prefix}members
				WHERE id_member = {int:member}
				LIMIT 1',
				array(
					'member' => we::$id,
				)
			);
			list ($unread_count) = wesql::fetch_row($request);
			wesql::free_result($request);

			return_raw($unread_count);
		}
		elseif ($sa == 'markread' && isset($_REQUEST['in']))
		{
			$notifications = Notification::get($_REQUEST['in'], we::$id);

			if (!empty($notifications[0]))
				$notifications[0]->markAsRead();

			redirectexit();
		}
		elseif ($sa == 'preview' && isset($_REQUEST['in']))
		{
			$notifications = Notification::get($_REQUEST['in'], we::$id);

			$raw = !empty($notifications[0]) ? $notifications[0]->getPreview() : '';
			if ($raw === false)
			{
				// This is usually a topic, so if it's gone, give the natural error message.
				loadLanguage('Errors');
				return_raw('<div class="errorbox">' . $txt['topic_gone'] . '</div>');
			}
			return_raw($raw);
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

		// Show read & unread notifications, unless we're calling from AJAX and the user didn't specify they wanted all of them.
		$context['notifications'] = (array) Notification::get(null, we::$id, 0, AJAX && empty(we::$user['data']['n_all']));
		$notification_members = array();
		foreach ($context['notifications'] as $notif)
			$notification_members[] = $notif->getMemberFrom();
		loadMemberData($notification_members);

		$request = wesql::query('
			SELECT unread_notifications
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => we::$id,
			)
		);
		list ($context['unread_count']) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	/**
	 * Handles our profile area
	 *
	 * @static
	 * @access public
	 * @param int $memID
	 * @return void
	 */
	public static function profile($memID)
	{
		global $context, $txt;

		// Not the same user? Hell no.
		if ($memID != we::$id)
			fatal_lang_error('no_access');

		$notifiers = self::getNotifiers();

		$request = wesql::query('
			SELECT data, notify_email_period
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => we::$id,
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
			list ($title, $desc, $notifier_config) = $notifier->getProfile(we::$id);

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
		if (isset($_GET['save']))
		{
			$disabled = array();
			$email = array();
			foreach ($notifiers as $notifier)
			{
				if (!empty($_POST['disable_' . $notifier->getName()]))
					$disabled[] = $notifier->getName();
				if (!empty($_POST['email_' . $notifier->getName()]))
					$email[$notifier->getName()] = (int) $_POST['email_' . $notifier->getName()];
			}

			$data['disabled_notifiers'] = $disabled;
			$data['email_notifiers'] = $email;

			updateMemberData(we::$id, array(
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
			foreach ($notifier_settings as $notifier => $settings)
				$notifiers[$notifier]->saveProfile(we::$id, $settings);

			redirectexit('action=profile;area=notifications');
		}

		// Load the template and form
		loadSource('ManageServer');
		loadTemplate('Admin');

		prepareDBSettingContext($config_vars);

		$context['settings_title'] = $txt['notifications'];
		$context['settings_message'] = $txt['notification_profile_desc'];
		$context['post_url'] = '<URL>?action=profile;area=notifications;save';

		wetem::load('show_settings');
	}

	/**
	 * Handles routinely pruning notifications older than x days
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
	 * Handled sending periodical notifications to every member
	 *
	 * @static
	 * @access public
	 * @return void
	 */
	public static function scheduled_periodical()
	{
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
			if (empty($data['email_notifiers']))
				continue;

			$valid_notifiers = array();

			foreach ($data['email_notifiers'] as $notifier => $status)
				if ($status < 2 && (empty($data['disabled_notifiers']) || !in_array($notifier, $data['disabled_notifiers'])) && self::getNotifiers($notifier) !== null)
					$valid_notifiers[] = $notifier;

			if (empty($valid_notifiers))
				continue;

			$members[$row['id_member']] = array(
				'id' => $row['id_member'],
				'name' => $ow['real_name'],
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
			if (in_array($row['notifier'], $members[$row['id_member']]['valid_notifiers']))
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

			// Assemble the notifications into one huge text
			$body = template_notification_email($m['notifications']);
			$subject = sprintf($txt['notification_email_periodical_subject'], $m['name'], $m['unread']);

			sendmail($m['email'], $subject, $body, null, null, true);
		}

		updateMemberData(array_keys($members), array(
			'notify_email_last_sent' => time(),
		));
	}
}

function weNotif_profile($memID)
{
	return weNotif::profile($memID);
}

function scheduled_notification_prune()
{
	return weNotif::scheduled_prune();
}

function scheduled_notification_periodical()
{
	return weNotif::scheduled_periodical();
}
