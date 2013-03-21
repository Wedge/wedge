<?php
/**
 * Wedge
 *
 * This file carries many useful functions for loading various general data from the database, often required on every page.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Loads the forum-wide settings into $settings as an array.
 *
 * - Ensure the database is using the same character set as the application thinks it is.
 * - Attempt to load the settings from cache, failing that from the database, with some fallback/sanity values for a few common settings.
 * - Save the value in cache for next time.
 * - Set the timezone (mandatory in PHP)
 * - Check the load average settings if available.
 * - Check whether post moderation is enabled.
 * - Run any functions specified in the pre_load hook.
 */
function reloadSettings()
{
	global $settings, $boarddir, $txt, $context, $sourcedir, $pluginsdir, $pluginsurl;

	// Most database systems have not set UTF-8 as their default input charset.
	wesql::query('
		SET NAMES utf8',
		array(
		)
	);

	// Try to load it from the cache first; it'll never get cached if the setting is off.
	if (($settings = cache_get_data('settings', 90)) == null)
	{
		$request = wesql::query('
			SELECT variable, value
			FROM {db_prefix}settings',
			array(
			)
		);
		$settings = array();
		if (!$request)
			show_db_error();
		while ($row = wesql::fetch_row($request))
			$settings[$row[0]] = $row[1];
		wesql::free_result($request);

		if (empty($settings['language']))
			$settings['language'] = 'english';

		// Do a few things to protect against missing settings or settings with invalid values...
		if (empty($settings['defaultMaxTopics']) || $settings['defaultMaxTopics'] <= 0 || $settings['defaultMaxTopics'] > 999)
			$settings['defaultMaxTopics'] = 20;
		if (empty($settings['defaultMaxMessages']) || $settings['defaultMaxMessages'] <= 0 || $settings['defaultMaxMessages'] > 999)
			$settings['defaultMaxMessages'] = 15;
		if (empty($settings['defaultMaxMembers']) || $settings['defaultMaxMembers'] <= 0 || $settings['defaultMaxMembers'] > 999)
			$settings['defaultMaxMembers'] = 30;
		$settings['js_lang'] = empty($settings['js_lang']) ? array() : unserialize($settings['js_lang']);
		$settings['registered_hooks'] = empty($settings['registered_hooks']) ? array() : unserialize($settings['registered_hooks']);
		$settings['hooks'] = $settings['registered_hooks'];
		$settings['pretty_filters'] = unserialize($settings['pretty_filters']);

		if (!empty($settings['cache_enable']))
			cache_put_data('settings', $settings, 90);
	}

	// Deal with loading plugins.
	$context['enabled_plugins'] = array();
	$context['extra_actions'] = array();
	if (!empty($settings['enabled_plugins']))
	{
		// Step through the list we think we have enabled.
		$plugins = explode(',', $settings['enabled_plugins']);
		$sane_path = str_replace('\\', '/', $pluginsdir);
		$hook_stack = array();
		foreach ($plugins as $plugin)
		{
			if (!empty($settings['plugin_' . $plugin]) && file_exists($sane_path . '/' . $plugin . '/plugin-info.xml'))
			{
				$plugin_details = @unserialize($settings['plugin_' . $plugin]);
				$context['enabled_plugins'][$plugin_details['id']] = $plugin;
				$this_plugindir = $context['plugins_dir'][$plugin_details['id']] = $sane_path . '/' . $plugin;
				$context['plugins_url'][$plugin_details['id']] = $pluginsurl . '/' . $plugin;
				if (isset($plugin_details['actions']))
					foreach ($plugin_details['actions'] as $action)
					{
						if (strpos($action['function'], '::') !== false)
							$action['function'] = explode('::', $action['function']);

						$context['extra_actions'][$action['action']] = array($action['filename'], $action['function'], $plugin_details['id']);
					}
				unset($plugin_details['id'], $plugin_details['provides'], $plugin_details['actions']);

				foreach ($plugin_details as $hook => $functions)
					foreach ($functions as $function)
					{
						$priority = (int) substr(strrchr($function, '|'), 1);
						$hook_stack[$hook][$priority][] = strtr($function, array('$plugindir' => $this_plugindir));
					}
			}
			else
				$reset_plugins = true;
		}
		if (isset($reset_plugins))
			updateSettings(array('enabled_plugins' => implode(',', $context['enabled_plugins'])));

		// Having got all the hooks, figure out the priority ordering and commit to the master list.
		foreach ($hook_stack as $hook => $hooks_by_priority)
		{
			krsort($hooks_by_priority);
			if (!isset($settings['hooks'][$hook]))
				$settings['hooks'][$hook] = array();
			foreach ($hooks_by_priority as $priority => $hooks)
				$settings['hooks'][$hook] = array_merge($settings['hooks'][$hook], $hooks);
		}
	}

	loadSource('Class-String');
	westr::getInstance();

	// Setting the timezone is a requirement.
	if (isset($settings['default_timezone']))
		date_default_timezone_set($settings['default_timezone']);
	else
		date_default_timezone_set(@date_default_timezone_get()); // At least attempt to use what the host has to try to prevent lots and lots of errors spewing everywhere.

	// Check the load averages?
	if (!empty($settings['loadavg_enable']))
	{
		if (($settings['load_average'] = cache_get_data('loadavg', 90)) == null)
		{
			$settings['load_average'] = @file_get_contents('/proc/loadavg');
			if (!empty($settings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $settings['load_average'], $matches) != 0)
				$settings['load_average'] = (float) $matches[1];
			elseif (can_shell_exec() && ($settings['load_average'] = `uptime`) != null && preg_match('~load average[s]?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $settings['load_average'], $matches) != 0)
				$settings['load_average'] = (float) $matches[1];
			else
				unset($settings['load_average']);

			if (!empty($settings['load_average']))
				cache_put_data('loadavg', $settings['load_average'], 90);
		}

		if (!empty($settings['loadavg_forum']) && !empty($settings['load_average']) && $settings['load_average'] >= $settings['loadavg_forum'])
			show_db_error(true);
	}

	// Is post moderation alive and well?
	$settings['postmod_active'] = !empty($settings['postmod_rules']);

	// Call pre-load hook functions.
	call_hook('pre_load');
}

function can_shell_exec()
{
	static $result = null;
	if ($result !== null)
		return $result;

	$disable_functions = explode(',', ini_get('disable_functions') . ',' . ini_get('suhosin.executor.func.blacklist'));
	return $result = (is_callable('shell_exec') && !ini_get('safe_mode') && !in_array('shell_exec', $disable_functions));
}

/**
 * Validate whether we are dealing with a board, and whether the current user has access to that board.
 *
 * - Initialize the link tree (and later, populating it).
 * - If only an individual msg is specified in the URL, identify the topic it belongs to, and redirect to that topic normally. (Assuming it exists; if not throw a fatal error that topic does not exist)
 * - If no board or topic is applicable, return (we're not in a board, and there won't be board moderators)
 * - See if we have checked this board or board+topic lately, and if so, grab from cache.
 * - If we don't have this, load the board information into $board_info, including category id and name, board name and other details of this board
 * - See if there are board moderators, and whether the current user is amongst them (which means possibly upgrading access if they did not have so before, as well as adding group id 3 to their groups)
 * - If the user cannot see the topic (and isn't a local moderator), issue a fatal error.
 */
function loadBoard()
{
	global $txt, $context, $settings;
	global $board_info, $board, $topic, $user_settings;

	// Start the linktree off empty..
	$context['linktree'] = array();

	// Have they by chance specified a message id but nothing else?
	if (!$context['action'] && empty($topic) && empty($board) && !empty($_REQUEST['msg']))
	{
		// Make sure the message id is really an int.
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Looking through the message table can be slow, so try using the cache first.
		if (($topic = cache_get_data('msg_topic-' . $_REQUEST['msg'], 120)) === null)
		{
			$request = wesql::query('
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $_REQUEST['msg'],
				)
			);

			// So did it find anything?
			if (wesql::num_rows($request))
			{
				list ($topic) = wesql::fetch_row($request);
				wesql::free_result($request);
				// Save save save.
				cache_put_data('msg_topic-' . $_REQUEST['msg'], $topic, 120);
			}
		}

		// Remember redirection is the key to avoiding fallout from your bosses.
		if (!empty($topic))
			redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg']);
		else
		{
			loadPermissions();
			loadTheme();
			fatal_lang_error('topic_gone', false);
		}
	}

	// Load this board only if it is specified.
	if (empty($board) && empty($topic))
	{
		$board_info = array(
			'moderators' => array(),
			'skin' => '',
		);
		return;
	}
	// Is this a XML feed requesting a topic?
	elseif (empty($board) && !empty($topic) && $context['action'] === 'feed')
		return;

	if (!empty($settings['cache_enable']) && (empty($topic) || $settings['cache_enable'] >= 3))
	{
		// !!! SLOW?
		if (!empty($topic))
			$temp = cache_get_data('topic_board-' . $topic, 120);
		else
			$temp = cache_get_data('board-' . $board, 120);

		if (!empty($temp))
		{
			$board_info = $temp;
			$board = $board_info['id'];
		}
	}

	if (empty($temp))
	{
		$request = wesql::query('
			SELECT
				c.id_cat, b.name AS bname, b.url, b.id_owner, b.description, b.num_topics, b.member_groups,
				b.num_posts, b.id_parent, c.name AS cname, IFNULL(mem.id_member, 0) AS id_moderator,
				mem.real_name' . (!empty($topic) ? ', b.id_board' : '') . ', b.child_level, b.skin,
				b.id_theme, b.override_theme, b.count_posts, b.id_profile, b.redirect, b.language, bm.permission = \'deny\' AS banned,
				bm.permission = {literal:access} AS allowed, mco.real_name AS owner_name, mco.buddy_list AS contacts, b.board_type, b.sort_method,
				b.sort_override, b.unapproved_topics, b.unapproved_posts' . (!empty($topic) ? ', t.approved, t.id_member_started' : '') . '
			FROM {db_prefix}boards AS b' . (!empty($topic) ? '
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = {int:current_topic} AND {query_see_topic})' : '') . '
				LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = {raw:board_link})
				LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
				LEFT JOIN {db_prefix}members AS mco ON (mco.id_member = b.id_owner)
				LEFT JOIN {db_prefix}board_members AS bm ON b.id_board = bm.id_board AND b.id_owner = {int:id_member}
			WHERE b.id_board = {raw:board_link}',
			array(
				'current_topic' => $topic,
				'board_link' => empty($topic) ? wesql::quote('{int:current_board}', array('current_board' => $board)) : 't.id_board',
				'id_member' => we::$id,
			)
		);
		// If there aren't any, skip.
		if (wesql::num_rows($request) > 0)
		{
			$row = wesql::fetch_assoc($request);

			// Set the current board.
			if (!empty($row['id_board']))
				$board = $row['id_board'];

			// Basic operating information. (Globals... :-/)
			$board_info = array(
				'id' => $board,
				'owner_id' => $row['id_owner'],
				'owner_name' => $row['owner_name'],
				'moderators' => array(),
				'cat' => array(
					'id' => $row['id_cat'],
					'name' => $row['cname']
				),
				'name' => $row['bname'],
				'description' => $row['description'],
				'url' => $row['url'],
				'num_posts' => $row['num_posts'],
				'num_topics' => $row['num_topics'],
				'unapproved_topics' => $row['unapproved_topics'],
				'unapproved_posts' => $row['unapproved_posts'],
				'unapproved_user_topics' => 0,
				'parent_boards' => getBoardParents($row['id_parent']),
				'parent' => $row['id_parent'],
				'child_level' => $row['child_level'],
				'skin' => $row['skin'],
				'theme' => $row['id_theme'],
				'override_theme' => !empty($row['override_theme']),
				'profile' => $row['id_profile'],
				'redirect' => $row['redirect'],
				'posts_count' => empty($row['count_posts']),
				'cur_topic_approved' => empty($topic) || $row['approved'],
				'cur_topic_starter' => empty($topic) ? 0 : $row['id_member_started'],
				'allowed_member' => $row['allowed'],
				'banned_member' => $row['banned'],
				'contacts' => $row['contacts'],
				'language' => $row['language'],
				'type' => $row['board_type'],
				'sort_method' => $row['sort_method'],
				'sort_override' => $row['sort_override'],
			);

			// Load privacy settings.
			// !!! Are we still meant to be using this now we have query_see_topic ? It's going to be inaccurate!
			if ($row['member_groups'] === '0')
				$board_info['privacy'] = 'members';
			elseif ($row['member_groups'] === '-1,0')
				$board_info['privacy'] = 'everyone';
			elseif ($row['member_groups'] === 'contacts')
				$board_info['privacy'] = 'contacts';
			elseif ($row['member_groups'] === '')
			{
				$board_info['privacy'] = 'author';
				$row['member_groups'] = '';
			}
			else
				$board_info['privacy'] = 'everyone';

			if (!empty($row['id_owner']))
				$board_info['moderators'] = array(
					$row['id_owner'] => array(
						'id' => $row['id_owner'],
						'name' => $row['owner_name'],
						'href' => '<URL>?action=profile;u=' . $row['id_owner'],
						'link' => '<a href="<URL>?action=profile;u=' . $row['id_owner'] . '">' . $row['owner_name'] . '</a>'
					)
				);

			do
			{
				if (!empty($row['id_moderator']) && $row['id_moderator'] != $row['id_owner'])
					$board_info['moderators'][$row['id_moderator']] = array(
						'id' => $row['id_moderator'],
						'name' => $row['real_name'],
						'href' => '<URL>?action=profile;u=' . $row['id_moderator'],
						'link' => '<a href="<URL>?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
					);
			}
			while ($row = wesql::fetch_assoc($request));

			// If the board only contains unapproved posts and the user isn't an approver then they can't see any topics.
			// If that is the case do an additional check to see if they have any topics waiting to be approved.
			if ($board_info['num_topics'] == 0 && $settings['postmod_active'] && !allowedTo('approve_posts'))
			{
				wesql::free_result($request); // Free the previous result

				$request = wesql::query('
					SELECT COUNT(id_topic)
					FROM {db_prefix}topics
					WHERE id_member_started={int:id_member}
						AND approved = {int:is_unapproved}
						AND id_board = {int:board}',
					array(
						'id_member' => we::$id,
						'is_unapproved' => 0,
						'board' => $board,
					)
				);

				list ($board_info['unapproved_user_topics']) = wesql::fetch_row($request);
			}

			if (!empty($settings['cache_enable']) && (empty($topic) || $settings['cache_enable'] >= 3))
			{
				// !!! SLOW?
				if (!empty($topic))
					cache_put_data('topic_board-' . $topic, $board_info, 120);
				cache_put_data('board-' . $board, $board_info, 120);
			}
		}
		else
		{
			// Otherwise the topic is invalid, there are no moderators, etc.
			$board_info = array(
				'moderators' => array(),
				'skin' => '',
				'error' => 'exist',
			);
			$topic = null;
			$board = 0;
		}
		wesql::free_result($request);
	}

	if (!empty($topic))
		$_GET['board'] = (int) $board;

	if (!empty($board))
	{
		// Now check if the user is a moderator.
		we::$cache = array();
		we::$user['is_mod'] |= isset($board_info['moderators'][we::$id]);

		if ($board_info['banned_member'] && !$board_info['allowed_member'])
			$board_info['error'] = 'access';

		if (!we::$is_admin && !in_array($board_info['id'], we::$user['qsb_boards']))
		{
			if (!we::$user['is_mod'] && (!empty($board_info['owner_id']) && we::$id != $board_info['owner_id']))
			{
				switch ($board_info['privacy'])
				{
					case 'contacts':
						if (!in_array(we::$id, explode(',', $board_info['contacts'])))
							$board_info['error'] = 'access';
						break;
					case 'members':
						if (we::$is_guest)
							$board_info['error'] = 'access';
						break;
					case 'author':
						$board_info['error'] = 'access'; // We've already established that the user is not the owner
						break;
					case 'everyone':
						$board_info['error'] = 'access'; // The fact we're here means there are some groups denying/not granting access which must be adhered to
						break;
				}
			}
			else
				$board_info['error'] = 'access'; // You're not permitted here, not an admin or mod and there's no owner rights to allow you in either.
		}

		// Build up the linktree.
		$context['linktree'] = array_merge(
			$context['linktree'],
			array(array(
				'url' => '<URL>?category=' . $board_info['cat']['id'],
				'name' => $board_info['cat']['name']
			)),
			array_reverse($board_info['parent_boards']),
			array(array(
				'url' => '<URL>?board=' . $board . '.0',
				'name' => $board_info['name']
			))
		);

		// Does this board have its own language setting? If so, does the user have their
		// own personal language set? (User preference beats board, which beats forum default)
		if (!empty($board_info['language']) && empty($user_settings['lngfile']))
		{
			we::$user['language'] = $board_info['language'];
			$user_settings['lngfile'] = $board_info['language'];
		}
	}

	// Set the template contextual information.
	$context['current_topic'] = $topic;
	$context['current_board'] = $board;

	// Hacker... you can't see this topic, I'll tell you that. (But moderators can!)
	if (!empty($board_info['error']) && ($board_info['error'] != 'access' || !we::$user['is_mod']))
	{
		// The permissions and theme need loading, just to make sure everything goes smoothly.
		loadPermissions();
		loadTheme();

		$_GET['board'] = '';
		$_GET['topic'] = '';

		// The linktree should not give the game away mate! However, it WILL be available to admins etc. for Who's Online so they can see what's going on.
		$context['linktree'] = array(
			array(
				'url' => '<URL>',
				'name' => $context['forum_name_html_safe']
			)
		);

		// If it's a prefetching agent or we're requesting an attachment.
		preventPrefetch($context['action'] === 'dlattach');

		if (we::$is_guest)
		{
			loadLanguage('Errors');
			is_not_guest($txt['topic_gone']);
		}
		else
			fatal_lang_error('topic_gone', false);
	}

	if (we::$user['is_mod'])
		we::$user['groups'][] = 3;
}

/**
 * Load the current user's permissions, to be stored in we::$user['permissions']
 *
 * - If the user is an admin, simply validate that they have not been banned then return.
 * - Attempt to load from cache (level 2+ caching only); if matched, apply ban restrictions and return.
 * - See if the user is possibly a spider, extend the user's "permissions" appropriately.
 * - If we have not been able to establish permissions thus far (because caching failed us), query the general permissions table for our groups.
 * - Then apply those permissions, both allow and denied.
 * - If inside a board, identify the board profile, and load the permissions from that, following the same process.
 * - If on caching level 2 or up, cache, then apply banned user permissions if banned.
 * - If the user is not a guest, identify what other boards they may have access to through the moderator cache.
 */
function loadPermissions()
{
	global $board, $board_info, $settings;

	if (we::$is_admin)
	{
		banPermissions();
		return;
	}

	if (!empty($settings['cache_enable']))
	{
		$cache_groups = we::$user['groups'];
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);
		// If it's a spider then cache it different.
		if (we::$user['possibly_robot'])
			$cache_groups .= '-spider';

		if ($settings['cache_enable'] >= 2 && !empty($board) && ($temp = cache_get_data('permissions:' . $cache_groups . ':' . $board, 240)) != null && time() - 240 > $settings['settings_updated'])
		{
			list (we::$user['permissions']) = $temp;
			banPermissions();

			return;
		}
		elseif (($temp = cache_get_data('permissions:' . $cache_groups, 240)) != null && time() - 240 > $settings['settings_updated'])
			list (we::$user['permissions'], $removals) = $temp;
	}

	// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
	$spider_restrict = we::$user['possibly_robot'] && !empty($settings['spider_mode']) && !empty($settings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

	if (empty(we::$user['permissions']))
	{
		// Get the general permissions.
		$request = wesql::query('
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:member_groups})
				' . $spider_restrict,
			array(
				'member_groups' => we::$user['groups'],
				'spider_group' => !empty($settings['spider_group']) ? $settings['spider_group'] : 0,
			)
		);
		$removals = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				we::$user['permissions'][] = $row['permission'];
		}
		wesql::free_result($request);

		if (isset($cache_groups))
			cache_put_data('permissions:' . $cache_groups, array(we::$user['permissions'], $removals), 240);
	}

	// Get the board permissions.
	if (!empty($board))
	{
		// Make sure the board (if any) has been loaded by loadBoard().
		if (!isset($board_info['profile']))
			fatal_lang_error('no_board');

		$request = wesql::query('
			SELECT permission, add_deny
			FROM {db_prefix}board_permissions
			WHERE (id_group IN ({array_int:member_groups})
				' . $spider_restrict . ')
				AND id_profile = {int:id_profile}',
			array(
				'member_groups' => we::$user['groups'],
				'id_profile' => $board_info['profile'],
				'spider_group' => !empty($settings['spider_mode']) && !empty($settings['spider_group']) ? $settings['spider_group'] : 0,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				we::$user['permissions'][] = $row['permission'];
		}
		wesql::free_result($request);
	}

	// Remove all the permissions they shouldn't have ;).
	if (!empty($settings['permission_enable_deny']))
		we::$user['permissions'] = array_diff(we::$user['permissions'], $removals);

	if (isset($cache_groups) && !empty($board) && $settings['cache_enable'] >= 2)
		cache_put_data('permissions:' . $cache_groups . ':' . $board, array(we::$user['permissions'], null), 240);

	// Banned? Watch, don't touch..
	banPermissions();

	// Load the mod cache so we can know what additional boards they should see, but no sense in doing it for guests
	if (!we::$is_guest)
	{
		if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= $settings['settings_updated'])
		{
			loadSource('Subs-Auth');
			rebuildModCache();
		}
		else
			we::$user['mod_cache'] = $_SESSION['mc'];
	}
}

/**
 * Loads user data, either by id or member_name, and can load one or many users' data together.
 *
 * User data, where successful, is loaded into the global $user_profiles array, keyed by user id. The exact data set is dependent on $set.
 *
 * @param mixed $users This can be either a single value or an array, representing a single user or multiple users.
 * @param bool $is_name If this parameter is true, treat the value(s) in $users as denoting user names, otherwise they are numeric user ids.
 * @param string $set Complexity of data to load, from 'minimal', 'normal', 'profile', each successively increasing in complexity.
 * @return mixed Returns either an array of users whose data was matched, or false if no matches were made.
 */
function loadMemberData($users, $is_name = false, $set = 'normal')
{
	global $user_profile, $settings, $board_info;

	// Can't just look for no users. :P
	if (empty($users))
		return false;

	// Make sure it's an array.
	$users = !is_array($users) ? array($users) : array_unique($users);
	$loaded_ids = array();

	if (!$is_name && !empty($settings['cache_enable']) && $settings['cache_enable'] >= 3)
	{
		$users = array_values($users);
		for ($i = 0, $n = count($users); $i < $n; $i++)
		{
			$data = cache_get_data('member_data-' . $set . '-' . $users[$i], 240);
			if ($data == null)
				continue;

			$loaded_ids[] = $data['id_member'];
			$user_profile[$data['id_member']] = $data;
			unset($users[$i]);
		}
	}

	if ($set === 'normal')
	{
		$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, a.transparency, a.id_folder,
			mem.id_member, mem.member_name, mem.real_name, mem.signature, mem.personal_text, mem.location, mem.gender,
			mem.avatar, mem.email_address, mem.hide_email, mem.website_title, mem.website_url, mem.birthdate,
			mem.posts, mem.id_group, mem.id_post_group, mem.show_online, mem.warning, mem.is_activated,

			mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile,
			mem.time_offset, mem.date_registered, mem.buddy_list,
			mem.media_items, mem.media_comments,

			IFNULL(mg.group_name, {string:blank}) AS member_group,
			IFNULL(pg.group_name, {string:blank}) AS post_group'
			. (!empty($settings['titlesEnable']) ? ', mem.usertitle' : '');

		$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';
	}
	elseif ($set === 'profile')
	{
		$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, a.transparency, a.id_folder,
			mem.id_member, mem.member_name, mem.real_name, mem.signature, mem.personal_text, mem.location, mem.gender,
			mem.avatar, mem.email_address, mem.hide_email, mem.website_title, mem.website_url, mem.birthdate,
			mem.posts, mem.id_group, mem.id_post_group, mem.show_online, mem.warning, mem.is_activated,

			mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile,
			mem.time_offset, mem.date_registered, mem.buddy_list,
			mem.media_items, mem.media_comments,

			mem.additional_groups,

			mem.id_theme, mem.pm_ignore_list, mem.pm_email_notify, mem.pm_receive_from,
			mem.time_format, mem.timezone, mem.secret_question, mem.smiley_set, mem.total_time_logged_in,
			mem.ignore_boards, mem.notify_announcements, mem.notify_regularity, mem.notify_send_body,
			mem.notify_types, lo.url, mem.password_salt, mem.pm_prefs,

			IFNULL(mg.group_name, {string:blank}) AS member_group,
			IFNULL(pg.group_name, {string:blank}) AS post_group'
			. (!empty($settings['titlesEnable']) ? ', mem.usertitle' : '');

		$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';
	}
	elseif ($set === 'minimal')
	{
		$select_columns = '
			mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.hide_email, mem.date_registered,
			mem.posts, mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group';

		$select_tables = '';
	}
	elseif ($set === 'userbox')
	{
		$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online,
			IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type, a.transparency, a.id_folder,
			mem.id_member, mem.member_name, mem.real_name, mem.signature, mem.personal_text, mem.location, mem.gender,
			mem.avatar, mem.email_address, mem.hide_email, mem.website_title, mem.website_url, mem.birthdate,
			mem.posts, mem.id_group, mem.id_post_group, mem.show_online, mem.warning, mem.is_activated,

			mem.additional_groups,

			IFNULL(mg.group_name, {string:blank}) AS member_group,
			IFNULL(pg.group_name, {string:blank}) AS post_group'
			. (!empty($settings['titlesEnable']) ? ', mem.usertitle' : '');

		$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';

		// Load cached membergroup badges (or rank images) and when to show them.
		if (($member_badges = cache_get_data('member-badges', 5000)) === null)
		{
			$member_badges = array();
			$request = wesql::query('
				SELECT g.id_group, g.stars, g.show_when, g.display_order
				FROM {db_prefix}membergroups AS g
				WHERE g.show_when != {int:never}
				ORDER BY g.display_order',
				array(
					'never' => 0,
				)
			);

			while ($row = wesql::fetch_assoc($request))
				$member_badges[$row['id_group']] = array($row['show_when'], $row['stars'], $row['display_order']);
			wesql::free_result($request);
			cache_put_data('member-badges', $member_badges, 5000);
		}
	}
	else
		trigger_error('loadMemberData(): Invalid member data set \'' . $set . '\'', E_USER_WARNING);

	if (!empty($users))
	{
		// Load the member's data.
		$request = wesql::query('
			SELECT' . $select_columns . '
			FROM {db_prefix}members AS mem' . $select_tables . '
			WHERE mem.' . ($is_name ? 'member_name' : 'id_member') . (count($users) == 1 ? ' = {' . ($is_name ? 'string' : 'int') . ':users}' : ' IN ({' . ($is_name ? 'array_string' : 'array_int') . ':users})'),
			array(
				'blank' => '',
				'users' => count($users) == 1 ? current($users) : $users,
			)
		);
		$new_loaded_ids = array();
		while ($row = wesql::fetch_assoc($request))
		{
			$new_loaded_ids[] = $row['id_member'];
			$loaded_ids[] = $row['id_member'];
			$row['options'] = array();
			if (!empty($row['member_ip']))
			{
				$row['member_ip'] = format_ip($row['member_ip']);
				$row['member_ip2'] = format_ip($row['member_ip2']);
			}

			if (!empty($settings['signature_minposts']) && ((int) $row['posts'] < (int) $settings['signature_minposts']))
			{
				// Hide normally (e.g. topic view) except if user is an admin
				if (($set === 'normal' || $set === 'userbox') && $row['id_group'] != 1)
					$row['signature'] = '';
				// Hide in profile unless it's the user's own profile and they have permission, or they have permission to modify anyone's.
				elseif ($set === 'profile' && !(($row['id_member'] == we::$id && allowedTo('profile_signature_own')) || allowedTo('profile_signature_any')))
					$row['signature'] = '';
			}
			$user_profile[$row['id_member']] = $row;
		}
		wesql::free_result($request);
	}

	if (!empty($new_loaded_ids) && $set !== 'minimal' && $set !== 'userbox')
	{
		$request = wesql::query('
			SELECT *
			FROM {db_prefix}themes
			WHERE id_member' . (count($new_loaded_ids) == 1 ? ' = {int:loaded_ids}' : ' IN ({array_int:loaded_ids})'),
			array(
				'loaded_ids' => count($new_loaded_ids) == 1 ? $new_loaded_ids[0] : $new_loaded_ids,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$user_profile[$row['id_member']]['options'][$row['variable']] = $row['value'];
		wesql::free_result($request);
	}

	if (!empty($new_loaded_ids) && !empty($settings['cache_enable']) && $settings['cache_enable'] >= 3)
		for ($i = 0, $n = count($new_loaded_ids); $i < $n; $i++)
			cache_put_data('member_data-' . $set . '-' . $new_loaded_ids[$i], $user_profile[$new_loaded_ids[$i]], 240);

	// Are we loading any moderators? If so, fix their group data...
	if (!empty($loaded_ids) && !empty($board_info['moderators']) && ($set === 'normal' || $set === 'userbox') && count($temp_mods = array_intersect($loaded_ids, array_keys($board_info['moderators']))) > 0)
	{
		if (($row = cache_get_data('moderator_group_info', 480)) == null)
		{
			$request = wesql::query('
				SELECT group_name AS member_group, stars
				FROM {db_prefix}membergroups
				WHERE id_group = {int:moderator_group}
				LIMIT 1',
				array(
					'moderator_group' => 3,
				)
			);
			$row = wesql::fetch_assoc($request);
			wesql::free_result($request);

			cache_put_data('moderator_group_info', $row, 480);
		}
		foreach ($temp_mods as $id)
		{
			// Add the local moderator group to the list.
			if (empty($user_profile[$id]['additional_groups']))
				$user_profile[$id]['additional_groups'] = '3';
			else
				$user_profile[$id]['additional_groups'] .= ',3';

			// By popular demand, don't show admins or global moderators as moderators.
			if ($user_profile[$id]['id_group'] != 1 && $user_profile[$id]['id_group'] != 2)
				$user_profile[$id]['member_group'] = $row['member_group'];

			// If the Moderator group has no color or stars, but their group does... don't overwrite.
			if (!empty($row['stars']))
				$user_profile[$id]['stars'] = $row['stars'];
		}
	}

	// Shall we show member badges..?
	if (!empty($loaded_ids) && $set === 'userbox')
	{
		// Badge types (show_when):
		// 0: never show (e.g. a custom user group can't have a badge.)
		// 1: always show (primary groups will always show unless set to never anyway.)
		// 2: only show when it's a primary group
		// 3: only show when there's no other badge already
		foreach ($loaded_ids as $id)
		{
			if (isset($user_profile[$id]['badges']))
				continue;
			$user_profile[$id]['badges'] = array();

			// Sort out the badges.
			$badges = array();

			// Should we show a badge for the primary group?
			$gid = $user_profile[$id]['id_group'];
			if (!empty($member_badges[$gid]))
				$badges[$member_badges[$gid][2]] = array($gid, $member_badges[$gid][1]);

			$groups = explode(',', $user_profile[$id]['additional_groups']);

			// Now do the additional groups -- and test whether we can show more than one badge.
			foreach ($groups as $gid)
				if (!empty($member_badges[$gid]) && $member_badges[$gid][0] != 2 && ($member_badges[$gid][0] == 1 || empty($user_profile[$id]['badges'])))
					$badges[$member_badges[$gid][2]] = array($gid, $member_badges[$gid][1]);

			// And finally, do the post group.
			$gid = $user_profile[$id]['id_post_group'];
			if (!empty($member_badges[$gid]) && ($member_badges[$gid][0] == 1 || empty($user_profile[$id]['badges'])))
				$badges[$member_badges[$gid][2]] = array($gid, $member_badges[$gid][1]);

			if (!empty($badges))
				ksort($badges);
			foreach ($badges as $badge)
				$user_profile[$id]['badges'][$badge[0]] = $badge[1];
		}
	}

	return empty($loaded_ids) ? false : $loaded_ids;
}

/**
 * Processes all the data previously loaded by {@link loadMemberData()} into a form more readily usable by the rest of the application.
 *
 * {@link loadMemberData()} issues the query and stores the result as-is, this function deals with formatting and setting up the results that make it much easier to use the data as-is, for example taking the raw data as issued about avatars, and creating a single unified array that can be used throughout the application.
 *
 * The following items are prepared by this function:
 * - Identity: username (raw username), name (display name), id (user id)
 * - Buddies: is_buddy (whether the user loaded is a buddy of the current user), is_reverse_buddy (whether the loaded user has the current user as a buddy), buddies (comma separated list of the user's buddies)
 * - User details: title (custom title), href (URL of user profile), link (a full HTML link to the user profile), email (user email address), show_email (whether to show the loaded user's address to the current user), blurb (personal text, censored)
 * - Dates: registered (formatted time/date of registration), registered_timestamp (timestap of registration), birth_date (regular date, showing the user's birth date)
 * - Gender: gender (array; contains name, text string of male/female; image, the HTML img link to the relevant image)
 * - Website: website (array; contains title, the title of the website; url, the bare URL of the given website)
 * - Profile fields: signature (signature, censored), location (user location, censored)
 * - Post counts: real_posts (bare integer of post count), posts (a formatted version of the post count, additionally using a fun comment if the user has more than half a million posts)
 * - Avatar: avatar (array; contains name, string for non uploaded avatar; image, HTML containing the final image link; href, basic href to uploaded avatar; url, URL to non uploaded avatar)
 * - Last login: last_login (formatted string denoting the time of last login), last_login_timestamp (timestamp of last login)
 * - IP address: ip and ip2 - the two user IP addresses held by a user.
 * - Online details: online (array; is_online, boolean whether the user is online or not; text, localized string for 'online' or 'offline'; href, URL to send this user a PM; link, the HTML for a link to send this user a PM; image_href, the HTML to send this user a PM, but with the online/offline indicator; label, same as 'text')
 * - User's language: language, the language name, capitalized
 * - Account status: is_activated (boolean for whether account is active), is_banned (boolean for whether account is currently banned), is_guest (true - user is not a guest), warning (user's warning level), warning_status (level of warn status: '', watch, moderate, mute)
 * - Groups: group (string, the user's primary group), group_id (integer, user's primary group id), post_group (string, the user's post group), group_badges (HTML markup for displaying the user's badge)
 * - Other: options (array of user's options), local_time (user's local time, using their offset), custom_fields (if $full_profile is true, but content depends on custom fields)
 *
 * The results are stored in the global $memberContext array, keyed by user id.
 *
 * @param int $user The user id to process for.
 * @param bool $full_profile Is this intended to be used in a full profile box, like Display or PM pages? This mostly defines group badge loading and custom field processing.
 * @return bool Return true if user's data was able to be loaded, false if not. (Error will be thrown if the user id is non-zero but the user was not passed through {@link loadMemberData()} first.
 */
function loadMemberContext($user, $full_profile = false)
{
	global $memberContext, $user_profile, $txt;
	global $context, $settings, $board_info, $theme;
	static $ban_threshold = null;
	static $dataLoaded = array();

	// If this person's data is already loaded, skip it.
	if (isset($dataLoaded[$user]))
		return true;

	// We can't load guests or members not loaded by loadMemberData()!
	if ($user == 0)
		return false;
	if (!isset($user_profile[$user]))
	{
		trigger_error('loadMemberContext(): member id ' . $user . ' not previously loaded by loadMemberData()', E_USER_WARNING);
		return false;
	}

	if (empty($ban_threshold))
		$ban_threshold = $user == we::$id ? 20 : 10;

	// Well, it's loaded now anyhow.
	$dataLoaded[$user] = true;
	$profile = $user_profile[$user];

	// Censor everything.
	censorText($profile['signature']);
	censorText($profile['personal_text']);
	censorText($profile['location']);

	// Set things up to be used before hand.
	$profile['signature'] = str_replace(array("\n", "\r"), array('<br>', ''), $profile['signature']);
	$profile['signature'] = parse_bbc($profile['signature'], true, 'sig' . $profile['id_member']);

	$profile['is_online'] = (!empty($profile['show_online']) || allowedTo('moderate_forum')) && $profile['is_online'] > 0;
	// Setup the buddy status here (One whole in_array call saved :P)
	$profile['buddy'] = in_array($profile['id_member'], we::$user['buddies']);
	$buddy_list = !empty($profile['buddy_list']) ? explode(',', $profile['buddy_list']) : array();

	// If we're always html resizing, assume it's too large.
	if ($settings['avatar_action_too_large'] == 'option_html_resize' || $settings['avatar_action_too_large'] == 'option_js_resize')
	{
		$avatar_width = !empty($settings['avatar_max_width_external']) ? ' width="' . $settings['avatar_max_width_external'] . '"' : '';
		$avatar_height = !empty($settings['avatar_max_height_external']) ? ' height="' . $settings['avatar_max_height_external'] . '"' : '';
	}
	else
	{
		$avatar_width = '';
		$avatar_height = '';
	}

	// What a monstrous array...
	$memberContext[$user] = array(
		'username' => $profile['member_name'],
		'name' => $profile['real_name'],
		'id' => $profile['id_member'],
		'is_buddy' => $profile['buddy'],
		'is_reverse_buddy' => in_array(we::$id, $buddy_list),
		'buddies' => $buddy_list,
		'title' => !empty($settings['titlesEnable']) ? $profile['usertitle'] : '',
		'href' => '<URL>?action=profile;u=' . $profile['id_member'],
		'link' => '<a href="<URL>?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['view_profile'] . '">' . $profile['real_name'] . '</a>',
		'email' => $profile['email_address'],
		'show_email' => showEmailAddress(!empty($profile['hide_email']), $profile['id_member']),
		'registered' => empty($profile['date_registered']) ? $txt['not_applicable'] : timeformat($profile['date_registered']),
		'registered_timestamp' => empty($profile['date_registered']) ? 0 : forum_time(true, $profile['date_registered']),
		'blurb' => $profile['personal_text'],
		'gender' => $profile['gender'] == 2 ? 'female' : ($profile['gender'] == 1 ? 'male' : ''),
		'website' => array(
			'title' => $profile['website_title'],
			'url' => $profile['website_url'],
		),
		'birth_date' => empty($profile['birthdate']) || $profile['birthdate'] === '0001-01-01' ? '0000-00-00' : (substr($profile['birthdate'], 0, 4) === '0004' ? '0000' . substr($profile['birthdate'], 4) : $profile['birthdate']),
		'signature' => $profile['signature'],
		'location' => $profile['location'],
		'real_posts' => $profile['posts'],
		'posts' => comma_format($profile['posts']),
		'last_login' => empty($profile['last_login']) ? $txt['never'] : timeformat($profile['last_login']),
		'last_login_timestamp' => empty($profile['last_login']) ? 0 : forum_time(0, $profile['last_login']),
		'ip' => isset($profile['member_ip']) ? htmlspecialchars($profile['member_ip']) : '',
		'ip2' => isset($profile['member_ip2']) ? htmlspecialchars($profile['member_ip2']) : '',
		'online' => array(
			'is_online' => $profile['is_online'],
			'text' => $txt[$profile['is_online'] ? 'online' : 'offline'],
			'href' => '<URL>?action=pm;sa=send;u=' . $profile['id_member'],
			'link' => '<a href="<URL>?action=pm;sa=send;u=' . $profile['id_member'] . '">' . $txt[$profile['is_online'] ? 'online' : 'offline'] . '</a>',
			'image_href' => $theme['images_url'] . '/' . ($profile['buddy'] ? 'buddy_' : '') . ($profile['is_online'] ? 'useron' : 'useroff') . '.gif',
			'label' => $txt[$profile['is_online'] ? 'online' : 'offline']
		),
		'language' => isset($profile['lngfile']) ? westr::ucwords(strtr($profile['lngfile'], array('_' => ' ', '-utf8' => ''))) : '',
		'is_activated' => isset($profile['is_activated']) ? $profile['is_activated'] : 1,
		'is_banned' => isset($profile['is_activated']) ? $profile['is_activated'] >= 10 : 0,
		'options' => $profile['options'],
		'is_guest' => false,
		'group' => $profile['member_group'],
		'group_id' => $profile['id_group'],
		'post_group' => $profile['post_group'],
		'group_badges' => array(),
		'warning' => $profile['warning'],
		'warning_status' => empty($settings['warning_mute']) ? '' : (isset($profile['is_activated']) && $profile['is_activated'] >= $ban_threshold ? 'ban' : ($settings['warning_mute'] <= $profile['warning'] ? 'mute' : (!empty($settings['warning_moderate']) && $settings['warning_moderate'] <= $profile['warning'] ? 'moderate' : (!empty($settings['warning_watch']) && $settings['warning_watch'] <= $profile['warning'] ? 'watch' : '')))),
		'local_time' => isset($profile['time_offset']) ? timeformat(time() + ($profile['time_offset'] - we::$user['time_offset']) * 3600, false) : 0,
		'media' => isset($profile['media_items']) ? array(
			'total_items' => $profile['media_items'],
			'total_comments' => $profile['media_comments'],
		) : array(),
		'avatar' => array(
			'name' => '',
			'image' => '',
			'href' => '',
			'url' => '',
		),
	);

	if (!empty($profile['badges']))
	{
		foreach ($profile['badges'] as $badge)
		{
			$stars = explode('#', $badge);
			if (!empty($stars[0]) && !empty($stars[1]))
				$memberContext[$user]['group_badges'][] = str_repeat('<img src="' . str_replace('$language', we::$user['language'], $theme['images_url'] . '/' . $stars[1]) . '">', $stars[0]);
		}
	}

	// Avatars are tricky, so let's do them next.
	// So, they're not banned, or if they are, we're not hiding their avatar.
	if (!$memberContext[$user]['is_banned'] || empty($settings['avatar_banned_hide']))
	{
		// So it's stored in members/avatar?
		if (!empty($profile['avatar']))
		{
			if (stristr($profile['avatar'], 'gravatar://'))
			{
				if ($profile['avatar'] === 'gravatar://' || empty($settings['gravatarAllowExtraEmail']))
					$image = get_gravatar_url($profile['email_address']);
				else
					$image = get_gravatar_url(substr($profile['avatar'], 11));

				$memberContext[$user]['avatar'] = array(
					'name' => $profile['avatar'],
					'image' => '<img class="avatar" src="' . $image . '"' . $avatar_width . $avatar_height . '>',
					'href' => $image,
					'url' => $image,
				);
			}
			else
				$memberContext[$user]['avatar'] = array(
					'name' => $profile['avatar'],
					'image' => stristr($profile['avatar'], 'http://') ? '<img class="avatar" src="' . $profile['avatar'] . '"' . $avatar_width . $avatar_height . '>' : '<img class="avatar" src="' . $settings['avatar_url'] . '/' . htmlspecialchars($profile['avatar']) . '">',
					'href' => stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $settings['avatar_url'] . '/' . $profile['avatar'],
					'url' => stristr($profile['avatar'], 'http://') ? $profile['avatar'] : $settings['avatar_url'] . '/' . $profile['avatar'],
				);
		}
		// It's an attachment?
		elseif (!empty($profile['id_attach']))
		{
			if (!$profile['transparency'])
			{
				$filename = getAttachmentFilename($profile['filename'], $profile['id_attach'], $profile['id_folder']);
				$profile['transparency'] = we_resetTransparency($profile['id_attach'], $filename, $profile['filename']) ? 'transparent' : 'opaque';
			}
			$memberContext[$user]['avatar'] = array(
				'name' => $profile['avatar'],
				'image' => $profile['id_attach'] > 0 ? '<img class="' . ($profile['transparency'] == 'transparent' ? '' : 'opaque ') . 'avatar" src="' . (empty($profile['attachment_type']) ? '<URL>?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $settings['custom_avatar_url'] . '/' . $profile['filename']) . '">' : '',
				'href' => $profile['id_attach'] > 0 ? (empty($profile['attachment_type']) ? '<URL>?action=dlattach;attach=' . $profile['id_attach'] . ';type=avatar' : $settings['custom_avatar_url'] . '/' . $profile['filename']) : '',
				'url' => '',
			);
		}
		// Default avatar?
		elseif (false)
		{
			// !!! @todo: Finish this.
		}
	}

	// Are we also loading the members custom fields into context?
	if ($full_profile && !empty($settings['displayFields']))
	{
		$memberContext[$user]['custom_fields'] = array();
		if (!isset($context['display_fields']))
			$context['display_fields'] = unserialize($settings['displayFields']);

		foreach ($context['display_fields'] as $custom)
		{
			if (empty($custom['title']) || empty($profile['options'][$custom['colname']]))
				continue;
			elseif (we::$is_guest && empty($custom['show_guest']))
				continue;

			$value = $profile['options'][$custom['colname']];

			// BBC?
			if ($custom['bbc'])
				$value = parse_bbc($value);
			// ... or checkbox?
			elseif (isset($custom['type']) && $custom['type'] == 'check')
				$value = $value ? $txt['yes'] : $txt['no'];

			// Enclosing the user input within some other text?
			if (!empty($custom['enclose']))
				$value = strtr($custom['enclose'], array(
					'{SCRIPTURL}' => '<URL>',
					'{IMAGES_URL}' => $theme['images_url'],
					'{DEFAULT_IMAGES_URL}' => $theme['default_images_url'],
					'{INPUT}' => $value,
				));

			$memberContext[$user]['custom_fields'][] = array(
				'title' => $custom['title'],
				'colname' => $custom['colname'],
				'value' => $value,
				'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
			);
		}
	}

	return true;
}

/**
 * Sets the transparency flag on attachments if not already set.
 * This is mainly useful to determine whether you can add a box-shadow
 * around an attachment thumbnail, or something.
 */
function we_resetTransparency($id_attach, $path, $real_name)
{
	loadSource('media/Subs-Media');
	$is_transparent = aeva_isTransparent($path, $real_name);
	wesql::query('
		UPDATE {db_prefix}attachments
		SET transparency = {string:transparency}
		WHERE id_attach = {int:id_attach}',
		array(
			'id_attach' => $id_attach,
			'transparency' => $is_transparent ? 'transparent' : 'opaque',
		)
	);
	return $is_transparent;
}

/**
 * Load all the details of a theme, given its ID.
 *
 * - Identify the theme to be loaded, from parameter or an external source: theme parameter in the URL, previously theme parameter in the URL and now in session, the user's preference, a board specific theme, and lastly the forum's default theme.
 * - Validate that the supplied theme is a valid id and that permission to use such theme (e.g. admin allows users to choose own theme, etc) is available.
 * - Load data from the themes table for this theme, both the user's preferences for this theme, plus the global settings for it, and load into $theme and $options respectively ($theme for theme settings/global settings, $options for user's specific settings within this theme)
 * - Save details to cache as appropriate.
 * - Prepare the list of folders to examine in priority for template loading (i.e. this theme's folder first, then default, but can include others)
 * - Identify if the user has come to the board from the wrong place (e.g. a www in the URL that shouldn't be there) so it can be fixed.
 * - Identify what smiley set should be used.
 * - Initialize $context['header'] and $context['footer'] for later use, as well as some $theme paths, some global $context values, $txt initially.
 * - Set up common server-side settings for later reference (in case of server configuration specific tweaks)
 * - Ensure the forum name is the first item in the link tree.
 * - Load the XML template if that is what we are going to use, otherwise load the index template (plus any templates the theme has specified it uses), and do not initialize template layers if we are using a 'simple' action that does not need them.
 * - Initialize the theme by calling the init block.
 * - Load any theme specific language files.
 * - See if scheduled tasks need to be loaded, if so add the call into the HTML header so they will be triggered next page load.
 * - Call the load_theme hook.
 */
function loadTheme($id_theme = 0, $initialize = true)
{
	global $user_settings, $board_info, $boarddir, $footer_coding;
	global $txt, $boardurl, $scripturl, $mbname, $settings;
	global $context, $theme, $options, $ssi_theme;

	// The theme was specified by parameter.
	if (!empty($id_theme))
		$id_theme = (int) $id_theme;
	// The theme was specified by REQUEST.
	elseif (!empty($_REQUEST['theme']) && (!empty($settings['theme_allow']) || allowedTo('admin_forum')))
	{
		$th = explode('_', $_REQUEST['theme']);
		$id_theme = $_SESSION['id_theme'] = (int) $th[0];
		$skin = $_SESSION['skin'] = isset($th[1]) ? base64_decode($th[1]) : '';
	}
	// The theme was specified by REQUEST... previously.
	elseif (!empty($_SESSION['id_theme']) && (!empty($settings['theme_allow']) || allowedTo('admin_forum')))
	{
		$id_theme = (int) $_SESSION['id_theme'];
		$skin = !empty($_SESSION['skin']) ? $_SESSION['skin'] : '';
	}
	// The theme is just the user's choice. (Might use ?board=1;theme=0 to force board theme.)
	elseif (!empty(we::$user['theme']) && !isset($_REQUEST['theme']) && (!empty($settings['theme_allow']) || allowedTo('admin_forum')))
	{
		$id_theme = we::$user['theme'];
		$skin = we::$user['skin'];
	}
	// The theme is the forum's mobile default.
	elseif (we::$user['is_mobile'])
	{
		$id_theme = $settings['theme_guests_mobile'];
		$skin = $settings['theme_skin_guests_mobile'];
	}
	// The theme was specified by the board.
	elseif (!empty($board_info['theme']))
	{
		$id_theme = $board_info['theme'];
		$skin = isset($board_info['skin']) ? $board_info['skin'] : '';
	}
	// The theme is the forum's default.
	else
	{
		$id_theme = $settings['theme_guests'];
		$skin = $settings['theme_skin_guests'];
	}

	// Verify the id_theme... no foul play.
	// Always allow the board specific theme, if they are overriding.
	if (!empty($board_info['theme']) && $board_info['override_theme'])
	{
		$id_theme = $board_info['theme'];
		$skin = isset($board_info['skin']) ? $board_info['skin'] : '';
	}
	// If they have specified a particular theme to use with SSI allow it to be used.
	elseif (!empty($ssi_theme) && $id_theme == $ssi_theme)
		$id_theme = (int) $id_theme;
	elseif (!empty($settings['knownThemes']) && !allowedTo('admin_forum'))
	{
		$themes = explode(',', $settings['knownThemes']);
		$id_theme = in_array($id_theme, $themes) ? (int) $id_theme : $settings['theme_guests'];
	}
	else
		$id_theme = (int) $id_theme;

	// Time to determine our CSS list...
	// First, load our requested skin folder.
	$context['skin'] = empty($skin) ? (empty($id_theme) ?
		(we::$user['is_mobile'] ? $settings['theme_skin_guests_mobile'] : $settings['theme_skin_guests']) :
		(we::$user['is_mobile'] ? 'skins/Wireless' : 'skins')) :
		($skin === 'skins' || strpos($skin, 'skins/') === 0 ? '' : 'skins/') . $skin;
	$folders = explode('/', $context['skin']);
	$context['css_folders'] = array();
	$current_folder = '';
	foreach ($folders as $folder)
	{
		$current_folder .= '/' . $folder;
		$context['css_folders'][] = substr($current_folder, 1);
	}

	// Then, we need to list the CSS files that will be part of our main CSS file.
	// false indicates that we don't want their names to show up in the final filename.
	// For the main file, we'll be happy with just the suffix list in the name :)
	// Note that common.css is prepended to all files, including this one.
	$context['main_css_files'] = array(
		'index' => false,
		'sections' => false
	);
	$context['css_suffixes'] = array();

	$member = empty(we::$id) ? -1 : we::$id;

	if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2 && ($temp = cache_get_data('theme_settings-' . $id_theme . ':' . $member, 60)) != null && time() - 60 > $settings['settings_updated'])
	{
		$themeData = $temp;
		$flag = true;
	}
	elseif (($temp = cache_get_data('theme_settings-' . $id_theme, 90)) != null && time() - 60 > $settings['settings_updated'])
		$themeData = $temp + array($member => array());
	else
		$themeData = array(-1 => array(), 0 => array(), $member => array());

	if (empty($flag))
	{
		// Load variables from the current or default theme, global or this user's.
		$result = wesql::query('
			SELECT variable, value, id_member, id_theme
			FROM {db_prefix}themes
			WHERE id_member' . (empty($themeData[0]) ? ' IN (-1, 0, {int:id_member})' : ' = {int:id_member}') . '
				AND id_theme' . ($id_theme == 1 ? ' = {int:id_theme}' : ' IN ({int:id_theme}, 1)'),
			array(
				'id_theme' => $id_theme,
				'id_member' => $member,
			)
		);
		// Pick between $theme and $options depending on whose data it is.
		while ($row = wesql::fetch_assoc($result))
		{
			// There are just things we shouldn't be able to change as members.
			if ($row['id_member'] != 0 && in_array($row['variable'], array('actual_theme_url', 'actual_images_url', 'default_images_url', 'default_theme_dir', 'default_theme_url', 'default_template', 'images_url', 'smiley_sets_default', 'theme_dir', 'theme_id', 'theme_templates', 'theme_url')))
				continue;

			// If this is the theme_dir of the default theme, store it.
			if (in_array($row['variable'], array('theme_dir', 'theme_url', 'images_url')) && $row['id_theme'] == '1' && empty($row['id_member']))
				$themeData[0]['default_' . $row['variable']] = $row['value'];

			// If this isn't set yet, is a theme option, or is not the default theme..
			if (!isset($themeData[$row['id_member']][$row['variable']]) || $row['id_theme'] != '1')
				$themeData[$row['id_member']][$row['variable']] = substr($row['variable'], 0, 5) == 'show_' ? $row['value'] == '1' : $row['value'];
		}
		wesql::free_result($result);

		if (!empty($themeData[-1]))
			foreach ($themeData[-1] as $k => $v)
				if (!isset($themeData[$member][$k]))
					$themeData[$member][$k] = $v;

		if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= 2)
			cache_put_data('theme_settings-' . $id_theme . ':' . $member, $themeData, 60);
		// Only if we didn't already load that part of the cache...
		elseif (!isset($temp))
			cache_put_data('theme_settings-' . $id_theme, array(-1 => $themeData[-1], 0 => $themeData[0]), 90);
	}

	$theme = $themeData[0];
	$options = $themeData[$member];

	$theme['theme_id'] = $id_theme;

	$theme['actual_theme_url'] = $theme['theme_url'];
	$theme['actual_images_url'] = $theme['images_url'];
	$theme['actual_theme_dir'] = $theme['theme_dir'];

	$theme['template_dirs'] = array();
	// This theme first.
	$theme['template_dirs'][] = $theme['theme_dir'];

	// Lastly the default theme.
	if ($theme['theme_dir'] != $theme['default_theme_dir'])
		$theme['template_dirs'][] = $theme['default_theme_dir'];

	if (!$initialize)
		return;

	// Check to see if they're accessing it from the wrong place.
	if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME']))
	{
		$detected_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 'https://' : 'http://';
		$detected_url .= empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
		$temp = preg_replace('~/' . basename($scripturl) . '(/.+)?$~', '', strtr(dirname($_SERVER['PHP_SELF']), '\\', '/'));
		if ($temp != '/')
			$detected_url .= $temp;
	}
	if (isset($detected_url) && $detected_url != $boardurl)
	{
		// Try #1 - check if it's in a list of alias addresses
		if (!empty($settings['forum_alias_urls']))
		{
			$aliases = explode(',', $settings['forum_alias_urls']);

			foreach ($aliases as $alias)
			{
				// Rip off all the boring parts, spaces, etc.
				if ($detected_url == trim($alias) || strtr($detected_url, array('http://' => '', 'https://' => '')) == trim($alias))
					$do_fix = true;
			}
		}

		// Hmm... check #2 - is it just different by a www? Send them to the correct place!!
		if (empty($do_fix) && strtr($detected_url, array('://' => '://www.')) == $boardurl && (empty($_GET) || count($_GET) == 1) && WEDGE != 'SSI')
		{
			// Okay, this seems weird, but we don't want an endless loop - this will make $_GET not empty ;)
			if (empty($_GET))
				redirectexit('wwwRedirect');
			else
			{
				list ($k, $v) = each($_GET);

				if ($k != 'wwwRedirect')
					redirectexit('wwwRedirect;' . $k . '=' . $v);
			}
		}

		// #3 is just a check for SSL...
		if (strtr($detected_url, array('https://' => 'http://')) == $boardurl)
			$do_fix = true;

		// Okay, #4 - perhaps it's an IP address? We're gonna want to use that one, then. (assuming it's the IP or something...)
		if (!empty($do_fix) || preg_match('~^http[s]?://(?:[\d.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~', $detected_url) == 1)
		{
			// Caching is good ;)
			$oldurl = $boardurl;

			// Fix $boardurl and $scripturl
			$boardurl = $detected_url;
			$scripturl = strtr($scripturl, array($oldurl => $boardurl));
			$_SERVER['REQUEST_URL'] = strtr($_SERVER['REQUEST_URL'], array($oldurl => $boardurl));

			// Fix the theme urls...
			$theme['theme_url'] = strtr($theme['theme_url'], array($oldurl => $boardurl));
			$theme['default_theme_url'] = strtr($theme['default_theme_url'], array($oldurl => $boardurl));
			$theme['actual_theme_url'] = strtr($theme['actual_theme_url'], array($oldurl => $boardurl));
			$theme['images_url'] = strtr($theme['images_url'], array($oldurl => $boardurl));
			$theme['default_images_url'] = strtr($theme['default_images_url'], array($oldurl => $boardurl));
			$theme['actual_images_url'] = strtr($theme['actual_images_url'], array($oldurl => $boardurl));

			// And just a few mod settings :)
			$settings['smileys_url'] = strtr($settings['smileys_url'], array($oldurl => $boardurl));
			$settings['avatar_url'] = strtr($settings['avatar_url'], array($oldurl => $boardurl));

			// Clean up after loadBoard()
			if (isset($board_info['moderators']))
			{
				foreach ($board_info['moderators'] as $k => $dummy)
				{
					$board_info['moderators'][$k]['href'] = strtr($dummy['href'], array($oldurl => $boardurl));
					$board_info['moderators'][$k]['link'] = strtr($dummy['link'], array('"' . $oldurl => '"' . $boardurl));
				}
			}
			foreach ($context['linktree'] as $k => $dummy)
				$context['linktree'][$k]['url'] = strtr($dummy['url'], array($oldurl => $boardurl));
		}
	}

	// Determine the current smiley set
	we::$user['smiley_set'] = (!in_array(we::$user['smiley_set'], explode(',', $settings['smiley_sets_known'])) && we::$user['smiley_set'] != 'none') || empty($settings['smiley_sets_enable']) ? (!empty($theme['smiley_sets_default']) ? $theme['smiley_sets_default'] : $settings['smiley_sets_default']) : we::$user['smiley_set'];

	// Some basic information...
	if (!isset($context['header']))
		$context['header'] = '';
	if (!isset($context['footer']))
		$context['footer'] = '';
	if (!isset($context['footer_js']))
		$context['footer_js'] = '';
	if (!isset($context['footer_js_inline']))
		$context['footer_js_inline'] = '';

	// Specifies that the JavaScript footer section is currently
	// open for sending JS code without <script> tags.
	$footer_coding = true;

	$context['menu_separator'] = !empty($theme['use_image_buttons']) ? ' ' : ' | ';
	$context['session_var'] = $_SESSION['session_var'];
	$context['session_id'] = $_SESSION['session_value'];
	$context['session_query'] = $context['session_var'] . '=' . $context['session_id'];
	$context['forum_name'] = $mbname;
	$context['forum_name_html_safe'] = westr::htmlspecialchars($context['forum_name']);
	$context['header_logo_url_html_safe'] = empty($theme['header_logo_url']) ? $context['forum_name_html_safe']
		: 'htmlsafe::' . westr::htmlspecialchars('<img src="' . westr::htmlspecialchars($theme['header_logo_url']) . '" alt="' . $context['forum_name'] . '">');
	$context['site_slogan'] = empty($theme['site_slogan']) ? '<div id="wedgelogo"></div>' : '<div id="siteslogan">' . $theme['site_slogan'] . '</div>';
	if (isset($settings['load_average']))
		$context['load_average'] = $settings['load_average'];

	// Set some permission related settings
	$context['show_login_bar'] = we::$is_guest && !empty($settings['enable_quick_login']);

	// This determines the server... not used in many places, except for login fixing.
	$context['server'] = array(
		'is_iis' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false,
		'is_apache' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false,
		'is_litespeed' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false,
		'is_lighttpd' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false,
		'is_nginx' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false,
		'is_cgi' => isset($_SERVER['SERVER_SOFTWARE']) && strpos(php_sapi_name(), 'cgi') !== false,
		'is_windows' => strpos(PHP_OS, 'WIN') === 0,
		'iso_case_folding' => ord(strtolower(chr(138))) === 154,
	);
	// A bug in some versions of IIS under CGI (older ones) makes cookie setting not work with Location: headers.
	$context['server']['needs_login_fix'] = $context['server']['is_cgi'] && $context['server']['is_iis'];

	// Set the top level linktree up
	array_unshift($context['linktree'], array(
		'url' => '<URL>',
		'name' => $context['forum_name_html_safe']
	));

	if (!isset($txt))
		$txt = array();

	// Initializing the Wedge templating magic.
	$context['macros'] = array();
	$context['skeleton'] = '';
	$context['skeleton_ops'] = array();

	// If output is an Ajax request, or printer-friendly
	// page, skip the index template entirely, and don't load skeletons.
	// Don't use macros in their templates!
	if (AJAX || $context['action'] === 'feed' || $context['action'] === 'printpage')
	{
		if (AJAX)
			loadTemplate('Xml');
		loadLanguage('index');
		$context['right_to_left'] = !empty($txt['lang_rtl']);
	}
	else
	{
		// Add support for media queries to IE 6-8. Don't wanna waste time on other browsers.
		if (we::is('ie8down'))
			add_js_file('scripts/respond.js');

		// Custom templates to load, or just default?
		if (isset($theme['theme_templates']))
			$templates = explode(',', $theme['theme_templates']);
		else
			$templates = array('index');

		// Load each template...
		foreach ($templates as $template)
			loadTemplate($template);

		// Users may also add a Custom.template.php file to their theme folder, to help them override
		// or add code before or after a specific function, e.g. in the index template, without
		// having to create a new theme. If it's not there, we'll just ignore that.
		loadTemplate('Custom', false);

		// ...and attempt to load their associated language files.
		$required_files = implode('+', $templates);
		loadLanguage($required_files, '', false);
		if ($context['right_to_left'] = !empty($txt['lang_rtl']))
			we::$user['is_rtl'] = true; // May be needed in we::is tests.

		// Initialize our JS files to cache right before we run template_init().
		weInitJS();

		// Initialize the theme and load the default macros.
		execBlock('init', 'ignore');

		// Now we initialize the search/replace pairs for macros.
		// They can be overloaded in a skin's skin.xml file.
		if (!empty($theme['macros']))
		{
			foreach ($theme['macros'] as $name => $contents)
			{
				if (is_array($contents))
					$contents = ($version = we::analyze($contents)) !== false ? $contents[$version] : (isset($contents['else']) ? $contents['else'] : '{body}');

				$context['macros'][$name] = array(
					'has_if' => strpos($contents, '<if:') !== false,
					'body' => $contents,
				);
			}
		}

		// Now we'll override all of these...
		loadSource('Subs-Cache');
		wedge_get_skin_options();
	}

	// We should have all our skeletons ready. Create the main one!
	wetem::createMainSkeleton();

	// Any theme-related strings that need to be loaded?
	if (!empty($theme['require_theme_strings']))
		loadLanguage('ThemeStrings', '', false);

	// Allow overriding the board wide time/number formats.
	if (empty($user_settings['time_format']) && !empty($txt['time_format']))
		we::$user['time_format'] = $txt['time_format'];

	if (isset($theme['use_default_images']) && $theme['use_default_images'] == 'always')
	{
		$theme['theme_url'] = $theme['default_theme_url'];
		$theme['images_url'] = $theme['default_images_url'];
		$theme['theme_dir'] = $theme['default_theme_dir'];
	}
	// Make a special URL for the language.
	// !!! $txt['image_lang'] isn't defined anywhere...
	$theme['lang_images_url'] = $theme['images_url'] . '/' . (!empty($txt['image_lang']) ? $txt['image_lang'] : we::$user['language']);

	// CSS suffixes are used for cached CSS filenames.
	// Add Webkit fixes -- there are so many popular browsers based on it.
	if (we::is('webkit') && we::$browser['agent'] !== 'webkit')
		$context['css_suffixes'][] = 'webkit';

	// Add any potential browser-based fixes.
	if (isset(we::$browser['agent']))
		$context['css_suffixes'][] = we::$browser['agent'] . we::$browser['version'];

	// Add any potential OS-based fixes.
	if (isset(we::$browser['os']))
		$context['css_suffixes'][] = we::$browser['os'] . we::$browser['os_version'];

	// RTL languages require an additional stylesheet.
	if ($context['right_to_left'])
		$context['css_suffixes'][] = 'rtl';

	// We may also have special stylesheets for guests and members.
	if (we::$is_guest)
		$context['css_suffixes'][] = 'guest';
	else
	{
		$context['css_suffixes'][] = 'member';
		$context['css_suffixes'][] = 'm' . we::$id;
	}

	// And even for boards and categories. Freedom is good.
	if (isset($board_info['id']))
	{
		$context['css_suffixes'][] = 'b' . $board_info['id'];
		$context['css_suffixes'][] = 'c' . $board_info['cat']['id'];
	}
	elseif (!empty($_GET['category']) && (int) $_GET['category'])
		$context['css_suffixes'][] = 'c' . (int) $_GET['category'];

	// Is the user an administrator?
	if (we::$is_admin)
		$context['css_suffixes'][] = 'admin';
	// Or maybe a moderator, then?
	elseif (we::$user['is_mod'])
		$context['css_suffixes'][] = 'mod';

	// These are special flow-control keywords.
	$context['css_suffixes'][] = 'local';		// index.local.css will only be included if the file is in the current skin's folder.
	$context['css_suffixes'][] = 'global';		// index.global.css will be included in the current skin and all its sub-skins (default.)
	$context['css_suffixes'][] = 'replace';		// index.replace.css will ensure that all parent folders' index.css and index.*.css files will be excluded.

	$context['tabindex'] = 1;
	$time = time();

	// If we think we have mail to send, let's offer up some possibilities... robots get pain (Now with scheduled task support!)
	if ((!empty($settings['mail_next_send']) && $settings['mail_next_send'] < $time && empty($settings['mail_queue_use_cron'])) || empty($settings['next_task_time']) || $settings['next_task_time'] < $time)
	{
		$is_task = empty($settings['next_task_time']) || $settings['next_task_time'] < $time;
		if (we::$browser['possibly_robot'])
		{
			// !! Maybe move this somewhere better?!
			loadSource('ScheduledTasks');

			// What to do, what to do?!
			if ($is_task)
				AutoTask();
			else
				ReduceMailQueue();
		}
		else
		{
			$type = $is_task ? 'task' : 'mailq';
			$ts = $type == 'mailq' ? $settings['mail_next_send'] : $settings['next_task_time'];

			add_js('
	$.get(weUrl("scheduled=' . $type . ';ts=' . $ts . '"));');
		}
	}

	// What about any straggling imperative tasks?
	if (empty($settings['next_imperative']))
	{
		loadSource('Subs-Scheduled');
		recalculateNextImperative();
	}

	if ($settings['next_imperative'] < $time)
		add_js('
	$.get(weUrl("imperative"));');

	// Any files to include at this point?
	if (!empty($settings['integrate_theme_include']))
	{
		$theme_includes = explode(',', $settings['integrate_theme_include']);
		foreach ($theme_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $theme['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Load the notifications system
	loadSource('Notifications');
	weNotif::initialize();

	// Call load theme hook.
	call_hook('load_theme');

	// We are ready to go.
	$context['theme_loaded'] = true;
}

function weInitJS()
{
	global $settings, $context;

	$version = we::is('ie[-8],firefox[-3.6]') ? '1.9.1' : '2.0.0b1';
	$origin = empty($settings['jquery_origin']) ? 'local' : $settings['jquery_origin'];

	// !! Temp code or permanent? We won't always need to test for jQuery's beta status...
	if ($origin !== 'local' && $origin !== 'jquery' && (strpos($version, 'b') !== false || strpos($version, 'rc') !== false))
		$origin = 'jquery';

	if ($origin === 'local')
		$context['main_js_files'] = array(
			'scripts/jquery-' . $version . '.min.js' => true,
			'scripts/script.js' => true,
			'scripts/sbox.js' => false,
			'scripts/custom.js' => false
		);
	else
	{
		$protocol = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off' ? 'https://' : 'http://';
		$remote = array(
			'google' =>		$protocol . 'ajax.googleapis.com/ajax/libs/jquery/' . $version . '/jquery.min.js',
			'microsoft' =>	$protocol . 'ajax.aspnetcdn.com/ajax/jquery/jquery-' . $version . '.min.js',
			'jquery' =>		'http://code.jquery.com/jquery-' . $version . '.min.js', // Doesn't support HTTPS.
		);
		$context['remote_js_files'] = array($remote[$origin]);
		$context['main_js_files'] = array(
			'scripts/script.js' => true,
			'scripts/sbox.js' => false,
			'scripts/custom.js' => false
		);
	}
}

function loadPluginSource($plugin_name, $source_name)
{
	global $context;
	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	foreach ((array) $source_name as $file)
		require_once($context['plugins_dir'][$plugin_name] . '/' . $file . '.php');
}

function loadPluginTemplate($plugin_name, $template_name, $fatal = true)
{
	global $context, $theme;
	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	// We may as well reuse the normal template loader. Might rewrite this later, however.
	$old_templates = $theme['template_dirs'];
	$theme['template_dirs'] = array($context['plugins_dir'][$plugin_name]);
	loadTemplate($template_name, $fatal);
	$theme['template_dirs'] = $old_templates;
}

function loadPluginLanguage($plugin_name, $template_name, $lang = '', $fatal = true, $force_reload = false)
{
	global $context, $settings, $txt, $db_show_debug;
	static $already_loaded = array();

	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	// Default to the user's language.
	if ($lang == '')
		$lang = isset(we::$user['language']) ? we::$user['language'] : $settings['language'];

	if (!$force_reload && isset($already_loaded[$template_name]) && $already_loaded[$template_name] == $lang)
		return $lang;

	$attempts = array();
	// If true, pass through to the next language attempt even if it's a match. But if it's not English, see about loading that *first*.
	if (empty($settings['disable_language_fallback']) && $lang !== 'english')
		$attempts['english'] = true;

	// Then go with user preference, followed by forum default (assuming it isn't already one of the previous)
	$attempts[$lang] = false;
	if ($settings['language'] !== 'english')
		$attempts[$settings['language']] = false;

	$found = false;
	foreach ($attempts as $load_lang => $continue)
	{
		$file = $context['plugins_dir'][$plugin_name] . '/' . $template_name . '.' . $load_lang . '.php';
		if (file_exists($file))
		{
			template_include($file);
			$found = true;
		}
		if ($found && !$continue)
			break;
	}

	// Oops, didn't find it. Log it.
	if (!$found)
		log_error(sprintf($txt['theme_language_error'], '(' . $plugin_name . ') ' . $template_name . '.' . $lang, 'template'));

	// Keep track of what we're up to soldier.
	if ($db_show_debug === true)
		$context['debug']['language_files'][] = $template_name . '.' . $lang . ' (' . $plugin_name . ')';

	// Remember what we have loaded, and in which language.
	$already_loaded[$plugin_name . ':' . $template_name] = $lang;

	// Return the language actually loaded.
	return $lang;
}

/**
 * Loads a named source file for later use.
 *
 * This function does not do any error handling as if this breaks, something is usually seriously wrong that error catching isn't going to solve.
 *
 * @param mixed $source_name Either a string holding the name of a file in the source directory, or an array of the same, without .php extension to load.
 */
function loadSource($source_name)
{
	global $sourcedir;
	static $done = array();

	foreach ((array) $source_name as $file)
		if (!isset($done[$file]))
		{
			require_once($sourcedir . '/' . $file . '.php');
			$done[$file] = true;
		}
}

/**
 * Attempt to load a language file.
 * Tries the current and default themes as well as the user and global languages.
 *
 * If full debugging is enabled, loads of language files will be logged too.
 *
 * @param string $template_name The name of the language file to load, without any .{language}.php prefix, e.g. 'Errors' or 'Who'.
 * @param string $lang Specifies the language to attempt to load; if not specified (or empty), load it in the current user's default language.
 * @param bool $fatal Whether to issue a fatal error in the event the language file could not be loaded.
 * @param bool $force_reload Whether to reload the language file even if previously loaded before.
 * @param bool $fallback Are we in a fallback call? (i.e. loading English prior to loading another language.)
 * @return string The name of the language from which the loaded language file was taken.
 */
function loadLanguage($template_name, $lang = '', $fatal = true, $force_reload = false, $fallback = false)
{
	global $theme, $context, $settings, $boarddir, $db_show_debug, $txt, $helptxt, $cachedir;
	static $already_loaded = array(), $folder_date = array();

	// Default to the user's language.
	if ($lang == '')
		$lang = isset(we::$user['language']) ? we::$user['language'] : $settings['language'];

	// Make sure we have $theme - if not we're in trouble and need to find it!
	if (empty($theme['default_theme_dir']))
	{
		loadSource('ScheduledTasks');
		loadEssentialThemeData();
	}

	// What theme are we in?
	$theme_name = basename($theme['theme_url']);
	if (empty($theme_name))
		$theme_name = 'unknown';

	// For each file open it up and write it out!
	foreach ((array) $template_name as $template)
	{
		if (!$force_reload && isset($already_loaded[$template]) && ($already_loaded[$template] == $lang || $fallback))
			continue;

		if (!defined('WEDGE_INSTALLER'))
		{
			$tid = !empty($theme['theme_id']) ? $theme['theme_id'] : 1;
			// So, firstly try to get this from the file cache.
			$filename = $cachedir . '/lang_' . $tid . '_' . $lang . '_' . $template . '.php';
			if (file_exists($filename))
			{
				@include($filename);
				if (!empty($val))
				{
					$val = @unserialize($val);
					foreach ($val as $file => $content)
						if (isset($$file))
							$$file = array_merge($$file, $content);
					$loaded = true;
				}
			}

			if (isset($loaded))
			{
				// If we've pulled it from cache, add it to the debug list, the internal list of what we've done then skip.
				$context['debug']['language_files'][] = $template . '.' . $lang . ' (' . $theme_name . ', cached)'; // !!! Yes, I know.
				$already_loaded[$template] = $lang;
				unset($loaded);
				continue;
			}

			// OK, this is messy. We need to load the file, grab any changes from the DB, but not touch the existing $txt state.
			$oldhelptxt = !empty($helptxt) ? (array) $helptxt : array();
			$oldtxt = $txt;
			$txt = array();
			$helptxt = array();
		}

		// Obviously, the current theme is most important to check.
		$attempts = array(
			array($theme['theme_dir'], $template, $lang, $theme['theme_url']),
			array($theme['default_theme_dir'], $template, $lang, $theme['default_theme_url']),
		);

		// Fall back on the default language if necessary.
		if ($settings['language'] != 'english')
		{
			$attempts[] = array($theme['theme_dir'], $template, $settings['language'], $theme['theme_url']);
			$attempts[] = array($theme['default_theme_dir'], $template, $settings['language'], $theme['default_theme_url']);
		}
		
		// First, try to ensure we have the English US version loaded first. We do not need to record whether we succeeded or not though.
		$fallbacks = array(
			array($theme['theme_dir'], $template),
			array($theme['default_theme_dir'], $template),
		);
		foreach ($fallbacks as $file)
			if (file_exists($file[0] . '/languages/' . $file[1] . '.english.php'))
				template_include($file[0] . '/languages/' . $file[1] . '.english.php');

		// Now try to find the actual language file.
		$found = false;
		foreach ($attempts as $k => $file)
		{
			if (file_exists($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php'))
			{
				// Include it!
				template_include($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php');

				// Note that we found it.
				$found = true;

				break;
			}
		}

		// That couldn't be found! Log the error, but *try* to continue normally.
		if (!$found)
		{
			// Put stuff back and if we did scrape a fallback together, add it to the current strings so that - hopefully, we won't get an error, even if there's a missing language file.
			if (isset($txt))
			{
				$txt = !empty($txt) ? array_merge($oldtxt, $txt) : $oldtxt;
				$helptxt = !empty($helptxt) ? array_merge($oldhelptxt, $helptxt) : $oldhelptxt;
			}

			if ($fatal)
			{
				log_error(sprintf($txt['theme_language_error'], $template . '.' . $lang), 'template');
				break;
			}
		}

		if (!defined('WEDGE_INSTALLER'))
		{
			$tid = !empty($theme['theme_id']) ? $theme['theme_id'] : 1;
			if ($found)
			{
				// So, now we need to get from the DB.
				$request = wesql::query('
					SELECT id_theme, lang_var, lang_key, lang_string, serial
					FROM {db_prefix}language_changes
					WHERE id_theme IN ({array_int:theme})
						AND id_lang = {string:lang}
						AND lang_file = {string:lang_file}',
					array(
						'theme' => ($tid == 1 ? array(1) : array(1, (int) $tid)),
						'lang' => $lang,
						'lang_file' => $template,
					)
				);
				$additions = array('txt' => array(), 'helptxt' => array());
				while ($row = wesql::fetch_assoc($request))
				{
					// This might look a bit weird. But essentially we might be loading two things from two themes.
					// If we don't have it already, use it. If we do have it already but it's not the default theme we're adding, replace it.
					if ($row['lang_var'] == 'txt')
					{
						if (!isset($additions['txt'][$row['lang_key']]) || $row['id_theme'] != 1)
						{
							$txt[$row['lang_key']] = !empty($row['serial']) ? @unserialize($row['lang_string']) : $row['lang_string'];
							$additions['txt'][$row['lang_key']] = true;
						}
					}
					elseif ($row['lang_var'] == 'helptxt')
					{
						if (!isset($additions['helptxt'][$row['lang_key']]) || $row['id_theme'] != 1)
						{
							$helptxt[$row['lang_key']] = !empty($row['serial']) ? @unserialize($row['lang_string']) : $row['lang_string'];
							$additions['helptxt'][$row['lang_key']] = true;
						}
					}
				}
				wesql::free_result($request);

				// Now cache this sucker.
				$filename = $cachedir . '/lang_' . $tid . '_' . $lang . '_' . $template . '.php';
				$val = array();
				if (!empty($txt))
					$val['txt'] = $txt;
				if (!empty($helptxt))
					$val['helptxt'] = $helptxt;
				$cache_data = '<' . '?php if(defined(\'WEDGE\'))$val=\'' . addcslashes(serialize($val), '\\\'') . '\';?' . '>';
				if (file_put_contents($filename, $cache_data, LOCK_EX) !== strlen($cache_data))
					@unlink($filename);

				// Now fix the master variables.
				if (!empty($txt) || !empty($oldtxt))
					$txt = array_merge($oldtxt, $txt);
				if (!empty($helptxt) || !empty($oldhelptxt))
					$helptxt = array_merge($oldhelptxt, $helptxt);
			}
		}

		// The index language file contains the locale. If that's what we're loading, we're changing time locales, so reload that. And only once.
		if ($found && !$fallback && $template === 'index')
		{
			we::$user['setlocale'] = setlocale(LC_TIME, $txt['lang_locale'] . '.utf-8', $txt['lang_locale'] . '.utf8');
			if (empty(we::$user['time_format']))
				we::$user['time_format'] = $txt['time_format'];
		}

		// Keep track of what we're up to soldier.
		if ($db_show_debug === true)
			$context['debug']['language_files'][] = $template . '.' . $lang . ' (' . $theme_name . ')';

		// Remember what we have loaded, and in which language.
		$already_loaded[$template] = $lang;
	}

	// Return the language actually loaded.
	return $lang;
}

/**
 * Get all parent boards (requires first parent as parameter)
 * From a given board, iterate up through the board hierarchy to find all of the parents back to forum root.
 *
 * Upon iterating up through the board hierarchy, the board's URL, name, depth and list of moderators will be provided upon return.
 *
 * @param int $id_parent The id of a board; this should only be called with the current board's id, the function will iterate itself until reaching the top level and does not require support with a list of boards to step through.
 * @return array The result of iterating through the board hierarchy; the order of boards should be deepest first.
 */
function getBoardParents($id_parent)
{
	$boards = array();

	// First check if we have this cached already.
	if (($boards = cache_get_data('board_parents-' . $id_parent, 480)) === null)
	{
		$boards = array();
		$original_parent = $id_parent;

		// Loop while the parent is non-zero.
		while ($id_parent != 0)
		{
			$result = wesql::query('
				SELECT
					b.id_parent, b.name, {int:board_parent} AS id_board, IFNULL(mem.id_member, 0) AS id_moderator,
					mem.real_name, b.child_level
				FROM {db_prefix}boards AS b
					LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board)
					LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = mods.id_member)
				WHERE b.id_board = {int:board_parent}',
				array(
					'board_parent' => $id_parent,
				)
			);
			// In the EXTREMELY unlikely event this happens, give an error message.
			if (wesql::num_rows($result) == 0)
				fatal_lang_error('parent_not_found', 'critical');
			while ($row = wesql::fetch_assoc($result))
			{
				if (!isset($boards[$row['id_board']]))
				{
					$id_parent = $row['id_parent'];
					$boards[$row['id_board']] = array(
						'url' => '<URL>?board=' . $row['id_board'] . '.0',
						'name' => $row['name'],
						'level' => $row['child_level'],
						'moderators' => array()
					);
				}
				// If a moderator exists for this board, add that moderator for all children too.
				if (!empty($row['id_moderator']))
					foreach ($boards as $id => $dummy)
					{
						$boards[$id]['moderators'][$row['id_moderator']] = array(
							'id' => $row['id_moderator'],
							'name' => $row['real_name'],
							'href' => '<URL>?action=profile;u=' . $row['id_moderator'],
							'link' => '<a href="<URL>?action=profile;u=' . $row['id_moderator'] . '">' . $row['real_name'] . '</a>'
						);
					}
			}
			wesql::free_result($result);
		}

		cache_put_data('board_parents-' . $original_parent, $boards, 480);
	}

	return $boards;
}

/**
 * Attempt to (re)load the list of known language packs.
 *
 * @param bool $use_cache Whether to cache the results of searching the language folders for index.{language}.php files.
 * @return array Returns an array, one element per known language pack, with: name (capitalized name of language pack), selected (bool whether this is the current language), filename (the raw language code, e.g. english_british-utf8), location (full system path to the index.{language}.php file) - this is all part of $context['languages'] too.
 */
function getLanguages($use_cache = true)
{
	global $context, $theme, $settings;

	// If the language array is already filled, or we wanna use the cache and it's not expired...
	if ($use_cache && (isset($context['languages']) || ($context['languages'] = cache_get_data('known_languages', !empty($settings['cache_enable']) && $settings['cache_enable'] < 1 ? 86400 : 3600)) !== null))
		return $context['languages'];

	// If we don't have our theme information yet, let's get it.
	if (empty($theme['default_theme_dir']))
		loadTheme(0, false);

	// Default language directories to try.
	$language_directories = array(
		$theme['default_theme_dir'] . '/languages',
		$theme['actual_theme_dir'] . '/languages',
	);

	// Initialize the array, otherwise if it's empty, Wedge won't cache it.
	$context['languages'] = array();

	// Go through all unique directories.
	foreach (array_unique($language_directories) as $language_dir)
	{
		// Can't look in here... doesn't exist!
		if (!file_exists($language_dir))
			continue;

		$dir = glob($language_dir . '/index.*.php');
		foreach ($dir as $entry)
		{
			if (!preg_match('~/index\.([^.]+)\.php$~', $entry, $matches))
				continue;
			// Try including this to retrieve the country code. If it doesn't work -- can live with it.
			@include($entry);
			$context['languages'][$matches[1]] = array(
				'name' => $txt['lang_name'],
				'code' => isset($txt['lang_dictionary']) ? $txt['lang_dictionary'] : '',
				'filename' => $matches[1],
				'location' => $entry,
			);
		}
	}

	// Let's cash in on this deal.
	if (!empty($settings['cache_enable']))
		cache_put_data('known_languages', $context['languages'], !empty($settings['cache_enable']) && $settings['cache_enable'] < 1 ? 86400 : 3600);

	return $context['languages'];
}

/**
 * Attempt to start the session.
 *
 * There are multiple other parts here too.
 * - Attempt to change some PHP settings (ensure cookies are enabled, but that cookies are not the only access method; disables PHP's auto tag rewriter to include sessions; turn off transparent session support; ensure normal ampersand separators for URL components)
 * - Set cookies to be global if that's what configuration dictates.
 * - Check if the session was started (e.g. session.auto_start) and attempt to close it if possible.
 * - Check for people using invalid PHPSESSIDs
 * - Enable database-based sessions and override PHP's own handler.
 * - Set the session code randomly.
 */
function loadSession()
{
	global $settings, $boardurl, $sc;

	// Attempt to change a few PHP settings.
	ini_set('session.use_cookies', true);
	ini_set('session.use_only_cookies', false);
	ini_set('url_rewriter.tags', '');
	ini_set('session.use_trans_sid', false);
	ini_set('arg_separator.output', '&amp;');

	if (!empty($settings['globalCookies']))
	{
		$parsed_url = parse_url($boardurl);

		if (preg_match('~^\d{1,3}(\.\d{1,3}){3}$~', $parsed_url['host']) == 0 && preg_match('~(?:[^.]+\.)?([^.]{2,}\..+)\z~i', $parsed_url['host'], $parts) == 1)
			ini_set('session.cookie_domain', '.' . $parts[1]);
	}
	// !!! Set the session cookie path?

	// If it's already been started... probably best to skip this.
	if ((ini_get('session.auto_start') == 1 && !empty($settings['databaseSession_enable'])) || session_id() == '')
	{
		// Attempt to end the already-started session.
		if (ini_get('session.auto_start') == 1)
			@session_write_close();

		// This is here to stop people from using bad junky PHPSESSIDs.
		if (isset($_REQUEST[session_name()]) && preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $_REQUEST[session_name()]) == 0 && !isset($_COOKIE[session_name()]))
		{
			$session_id = md5(md5('we_sess_' . time()) . mt_rand());
			$_REQUEST[session_name()] = $session_id;
			$_GET[session_name()] = $session_id;
			$_POST[session_name()] = $session_id;
		}

		// Use database sessions?
		if (!empty($settings['databaseSession_enable']))
		{
			ini_set('session.serialize_handler', 'php');
			session_set_save_handler('sessionOpen', 'sessionClose', 'sessionRead', 'sessionWrite', 'sessionDestroy', 'sessionGC');
			ini_set('session.gc_probability', '1');
		}
		elseif (ini_get('session.gc_maxlifetime') <= 1440 && !empty($settings['databaseSession_lifetime']))
			ini_set('session.gc_maxlifetime', max($settings['databaseSession_lifetime'], 60));

		session_start();

		// Change it so the cache settings are a little looser than default.
		if (!empty($settings['databaseSession_loose']))
			header('Cache-Control: private');
	}

	// Set the randomly generated code.
	if (!isset($_SESSION['session_var']))
	{
		$_SESSION['session_value'] = md5(session_id() . mt_rand());
		$_SESSION['session_var'] = substr(preg_replace('~^\d+~', '', sha1(mt_rand() . session_id() . mt_rand())), 0, rand(7, 12));
	}
	$sc = $_SESSION['session_value'];
}

/**
 * Part of the PHP Session API, this function is intended to apply when creating a session.
 *
 * @param string $save_path Normally the path that would be used in creating a session. Not applicable in the database replacement.
 * @param string $session_name Normally the name that would be used for the session. Not applicable in the database replacement.
 * @return bool Returns whether the session could be opened; in the database replacement this is always true.
 */
function sessionOpen($save_path, $session_name)
{
	return true;
}

/**
 * Part of the PHP Session API, this function is intended to apply when session closure is required, as part of shutdown.
 *
 * @return bool Returns whether the session was successfully closed (typically a file handle). In the database this is not applicable so always returns true.
 */
function sessionClose()
{
	return true;
}

/**
 * Part of the PHP Session API, this function retrieves the session data from the storage, as part of generally loading the session, for the database replacement.
 *
 * @param string $session_id The session's identifier, required.
 * @return string The session data, as a serialized array.
 */
function sessionRead($session_id)
{
	if (preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $session_id) == 0)
		return false;

	// Look for it in the database.
	$result = wesql::query('
		SELECT data
		FROM {db_prefix}sessions
		WHERE session_id = {string:session_id}
		LIMIT 1',
		array(
			'session_id' => $session_id,
		)
	);
	list ($sess_data) = wesql::fetch_row($result);
	wesql::free_result($result);

	return $sess_data;
}

/**
 * Part of the PHP Session API, this function manages the saving of session data, for the database replacement.
 *
 * @param string $session_id The session's identification, required. Note that the name is checked to ensure it is a valid formatted string from the application.
 * @param string $data A string, containing the serialized data normally held in the $_SESSION array.
 * @return bool Returns true on successful write, false on not.
 */
function sessionWrite($session_id, $data)
{
	// One of those weird bugs: sometimes, when using a combination of WebKit
	// and a certain server configuration, wesql is already shut down at this point.
	if (!class_exists('wesql') || !preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $session_id))
		return false;

	// First try to update an existing row...
	$result = wesql::query('
		UPDATE {db_prefix}sessions
		SET data = {string:data}, last_update = {int:last_update}
		WHERE session_id = {string:session_id}',
		array(
			'last_update' => time(),
			'data' => $data,
			'session_id' => $session_id,
		)
	);

	// If that didn't work, try inserting a new one.
	if (wesql::affected_rows() == 0)
		$result = wesql::insert('ignore',
			'{db_prefix}sessions',
			array('session_id' => 'string', 'data' => 'string', 'last_update' => 'int'),
			array($session_id, $data, time()),
			array('session_id')
		);

	return $result;
}

/**
 * Part of the PHP Session API, this function is for when the application terminates a session, typically on user actively logging out.
 *
 * @param string $session_id The id of the session to be removed.
 * @return bool Returns true if the session was able to be removed, false if not.
 */
function sessionDestroy($session_id)
{
	if (preg_match('~^[a-zA-Z0-9,-]{16,32}$~', $session_id) == 0)
		return false;

	// Just delete the row...
	return wesql::query('
		DELETE FROM {db_prefix}sessions
		WHERE session_id = {string:session_id}',
		array(
			'session_id' => $session_id,
		)
	);
}

/**
 * Part of the PHP Session API, this function manages 'garbage collection', i.e. pruning session data older than the current needs.
 *
 * @param int $max_lifetime The maximum time in seconds that a session should persist for without actively being updated. It is compared to the default value specified by the administrator (stored in $settings['databaseSession_lifetime'])
 */
function sessionGC($max_lifetime)
{
	global $settings;

	// Just set to the default or lower? Ignore it for a higher value. (hopefully)
	if (!empty($settings['databaseSession_lifetime']) && ($max_lifetime <= 1440 || $settings['databaseSession_lifetime'] > $max_lifetime))
		$max_lifetime = max($settings['databaseSession_lifetime'], 60);

	// Clean up ;).
	return wesql::query('
		DELETE FROM {db_prefix}sessions
		WHERE last_update < {int:last_update}',
		array(
			'last_update' => time() - $max_lifetime,
		)
	);
}

/**
 * Initialize the database connection to be used.
 *
 * - Begin by loading the relevant function set (currently the MySQL driver)
 * - Initiate the database connection through the wesql object.
 * - If the connection fails, revert to a fatal error to the user.
 * - If in SSI mode, ensure the database prefix is attended to.
 * - The global variable $db_connection will hold the connection data.
 */
function loadDatabase()
{
	global $db_persist, $db_server, $db_user, $db_passwd;
	global $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix;

	// Load the database.
	loadSource('Class-DB');
	wesql::getInstance();

	// If we are in SSI try them first, but don't worry if it doesn't work, we have the normal username and password we can use.
	if (WEDGE == 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
		$con = wesql::connect($db_server, $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix, array('persist' => $db_persist, 'non_fatal' => true, 'dont_select_db' => true));

	// Either we aren't in SSI mode, or it failed.
	if (empty($con))
		$con = wesql::connect($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => $db_persist, 'dont_select_db' => WEDGE == 'SSI'));

	// Safe guard here, if there isn't a valid connection let's put a stop to it.
	if (!$con)
		show_db_error();

	// If in SSI mode fix up the prefix.
	if (WEDGE == 'SSI')
		wesql::fix_prefix($db_prefix, $db_name);
}
