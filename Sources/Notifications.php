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
		return !we::$user['is_guest'] && in_array($notifier->getName(), self::$disabled);
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
		global $context, $scripturl, $txt;

		loadSource('Class-Notification');
		loadSource('Class-Notifier');

		// Register the notifiers
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
			$quick_notifications = self::get_quick_notifications();
			
			// Get the unread count and load the disabled notifiers along with it
			$request = wesql::query('
				SELECT unread_notifications, disabled_notifiers, notifier_prefs
				FROM {db_prefix}members
				WHERE id_member = {int:member}
				LIMIT 1',
				array(
					'member' => we::$id,
				)
			);
			list ($context['unread_notifications'], $disabled_notifiers, $prefs) = wesql::fetch_row($request);
			wesql::free_result($request);

			// Automatically cache the current member's notifier preferences, save us some queries
			self::$pref_cache[we::$id] = json_decode($prefs, true);

			self::$disabled = explode(',', $disabled_notifiers);

			loadTemplate('Notifications');

			wetem::before('sidebar', 'notifications_block');

			add_js_inline('
	we_notifs = ', json_encode(array(
		'count' => $context['unread_notifications'],
		'notifs' => $quick_notifications,
	)), ';');
			add_js_file('scripts/notifications.js');
		}
	}

	/**
	 * Caches and loads quick notifications, also serializes them into array for quick access
	 *
	 * @static
	 * @access protected
	 * @return array
	 */
	protected static function get_quick_notifications()
	{
		$notifications = cache_get_data('quick_notification_' . we::$id, 86400);

		if ($notifications == null)
		{
			$notifications = Notification::get(null, we::$id, self::$quick_count, true);

			// Cache it
			cache_put_data('quick_notification_' . we::$id, $notifications, 86400);
		}

		$notifs = array();
		foreach ($notifications as $notification)
		{
			$notifs[] = array(
				'id' => $notification->getID(),
				'text' => $notification->getText(),
				'url' => $notification->getURL(),
				'time' => timeformat($notification->getTime()),
			);
		}

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
			SELECT notifier_prefs
			FROM {db_prefix}members
			WHERE id_member = {int:member}
			LIMIT 1',
			array(
				'member' => $id_member,
			)
		);
		list($pref) = wesql::fetch_assoc($request);
		wesql::free_result($request);

		self::$pref_cache[$id_member] = json_decode($pref, true);

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

		updateMemberData($id_member, array('notifier_prefs' => json_encode($prefs)));
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
		global $context;

		if (we::$user['is_guest'])
			fatal_lang_error('access_denied');

		$area = !empty($_REQUEST['area']) ? $_REQUEST['area'] : '';

		if ($area == 'redirect')
		{
			// We are accessing a notification and redirecting to it's target
			list ($notification) = Notification::get((int) $_REQUEST['id'], we::$id);

			// Not found?
			if (empty($notification))
				fatal_lang_error('notification_not_found');

			// Mark this as read
			$notification->markAsRead();

			// Redirect to the target
			redirectexit($notification->getURL());
		}
		elseif ($area == 'getunread')
		{
			header('Content-type: application/json; charset=utf-8');

			$notifications = self::get_quick_notifications();

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

			echo json_encode(array(
				'count' => $unread_count,
				'notifications' => $notifications,
			));

			exit;
		}
		elseif ($area == 'markread' && !empty($_REQUEST['id']))
		{
			$notifications = Notification::get($_REQUEST['id'], we::$id);

			if (!empty($notifications[0]))
				$notifications[0]->markAsRead();

			redirectexit();
		}
		elseif (!empty($area) && !empty(self::$notifiers[$area]) && is_callable(self::$notifiers[$area], 'action'))
			return self::$notifiers[$area]->action();

		// Otherwise we're displaying all the notifications this user has
		$context['notifications'] = Notification::get(null, we::$id, 0);

		wetem::load('notifications_list');
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
		global $context, $txt, $scripturl;

		// Not the same user? hell no
		if ($memID != we::$id)
			fatal_lang_error('access_denied');
		
		$notifiers = self::getNotifiers();

 		$request = wesql::query('
 			SELECT disabled_notifiers, email_notifiers, notify_email_period
 			FROM {db_prefix}members
 			WHERE id_member = {int:member}
 			LIMIT 1',
 			array(
	 			'member' => we::$id,
	 		)
	 	);
	 	list ($disabled_notifiers, $email_notifiers, $period) = wesql::fetch_row($request);
	 	wesql::free_result($request);

		$disabled_notifiers = explode(',', $disabled_notifiers);
		$email_notifiers = json_decode($email_notifiers, true);

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

			updateMemberData(we::$id, array(
				'disabled_notifiers' => implode(',', $disabled),
				'email_notifiers' => json_encode($email),
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

			redirectexit('action=profile;area=notification');
		}

		// Load the template and form
		loadSource('ManageServer');
		loadTemplate('Admin');

		prepareDBSettingContext($config_vars);

		$context['settings_title'] = $txt['notifications'];
		$context['settings_message'] = $txt['notification_profile_desc'];
		$context['post_url'] = $scripturl . '?action=profile;area=notification;save';

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
			SELECT id_member, real_name, email_address email_notifiers, disabled_notifiers, unread_notifications
			FROM {db_prefix}members
			WHERE unread_notifications > 0
				AND UNIX_TIMESTAMP() > notify_email_last_sent + (notify_email_period * 86400)',
			array()
		);

		$members = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$valid_notifiers = array();
			foreach (json_decode($row['email_notifiers'], true) as $notifier => $status)
				if ($status < 2 && !in_array($notifier, explode(',', $row['disabled_notifiers'])) && weNotif::getNotifiers($notifier) !== null)
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

		// It's cheaper to check for the notifier for the members in a PHP if
		// rather than checking in a MySQL query of huge IF/ELSE clauses
		$request = wesql::query('
			SELECT *
			FROM {db_prefi}notifications
			WHERE id_member IN ({array_int:members})
				AND unread = 1',
			array(
				'members' => array_keys($members),
			)
		);

		while ($row = wesql::fetch_assoc($request))
			if (in_array($row['notifier'], $members[$row['id_member']]['valid_notifiers']))
			{
				$member = &$member[$row['id_member']];

				if (empty($member['notifications'][$row['notifier']]))
					$member['notifications'][$row['notifier']] = array();

				$member['notifications'][$row['notifier']][] = new Notification($row, weNotif::getNotifiers($row['notifier']));
			}

		wesql::free_result($request);

		loadTemplate('Notifications');

		foreach ($members as $member)
		{
			if (empty($member['notifications']))
				continue;

			// Assemble the notifications into one huge text
			$email = template_notification_email($member['notifications']);
			$subject = sprintf($txt['notification_email_periodical_subject'], $member['name'], $member['unread']);

			sendmail($member['email'], $subject, $email, null, null, true);
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
?>