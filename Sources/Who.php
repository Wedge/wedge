<?php
/**
 * Wedge
 *
 * This file is all about showing you the Who's Online list.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	It contains only the following functions:

	void Who()
		- prepares the who's online data for the Who template.
		- uses the Who template (main block) and language file.
		- requires the who_view permission.
		- is enabled with the who_enabled setting.
		- is accessed via ?action=who.

	array determineActions(array urls, string preferred_prefix = false)
		- determine the actions of the members passed in urls.
		- urls should be a single url (string) or an array of arrays, each
		  inner array being (serialized request data, id_member).
		- returns an array of descriptions if you passed an array, otherwise
		  the string describing their current location.

	Adding actions to the Who's Online list:
	---------------------------------------------------------------------------
		Adding actions to this list is actually fairly easy....

		- For actions anyone should be able to see, just add a string named
		  whoall_ACTION, where ACTION is the action used in index.php.

		- For actions that have a subaction which should be represented
		  differently, use whoall_ACTION_SUBACTION.

		- For actions that include a topic, and should be restricted, use
		  whotopic_ACTION, or for subactions to be represented differently,
		  use whotopic_ACTION_SUBACTION.

		- For actions that use a message, by msg or quote, use whopost_ACTION.

		- For administrator-only actions, use whoadmin_ACTION.

		- For actions that should be viewable only with certain permissions,
		  use whoallow_ACTION and add a list of possible permissions to the
		  $allowedActions array, using ACTION as the key.
*/

// Who's online, and what are they doing?
function Who()
{
	global $context, $txt, $settings, $memberContext;

	// Permissions, permissions, permissions.
	isAllowedTo('who_view');

	// You can't do anything if this is off.
	if (empty($settings['who_enabled']))
		fatal_lang_error('who_off', false);

	// Load the 'Who' template.
	loadTemplate('Who');
	loadLanguage('Who');

	// Sort out... the column sorting.
	$sort_methods = array(
		'user' => 'mem.real_name',
		'time' => 'lo.log_time'
	);

	$show_methods = array(
		'members' => '(lo.id_member != 0)',
		'guests' => '(lo.id_member = 0)',
		'all' => '1=1',
	);

	// Store the sort methods and the show types for use in the template.
	$context['sort_methods'] = array(
		'user' => $txt['who_user'],
		'time' => $txt['who_time'],
	);
	$context['show_methods'] = array(
		'all' => $txt['who_show_all'],
		'members' => $txt['who_show_members_only'],
		'guests' => $txt['who_show_guests_only'],
	);

	// Can they see spiders too?
	if (!empty($settings['spider_mode']) && !empty($settings['show_spider_online']) && ($settings['show_spider_online'] == 2 || allowedTo('admin_forum')) && !empty($settings['spider_name_cache']))
	{
		$show_methods['spiders'] = '(lo.id_member = 0 AND lo.id_spider > 0)';
		$show_methods['guests'] = '(lo.id_member = 0 AND lo.id_spider = 0)';
		$context['show_methods']['spiders'] = $txt['who_show_spiders_only'];
	}
	elseif (empty($settings['show_spider_online']) && isset($_SESSION['who_online_filter']) && $_SESSION['who_online_filter'] == 'spiders')
		unset($_SESSION['who_online_filter']);

	// Does the user prefer a different sort direction?
	if (isset($_REQUEST['sort'], $sort_methods[$_REQUEST['sort']]))
	{
		$context['sort_by'] = $_SESSION['who_online_sort_by'] = $_REQUEST['sort'];
		$sort_method = $sort_methods[$_REQUEST['sort']];
	}
	// Did we set a preferred sort order earlier in the session?
	elseif (isset($_SESSION['who_online_sort_by']))
	{
		$context['sort_by'] = $_SESSION['who_online_sort_by'];
		$sort_method = $sort_methods[$_SESSION['who_online_sort_by']];
	}
	// Default to last time online.
	else
	{
		$context['sort_by'] = $_SESSION['who_online_sort_by'] = 'time';
		$sort_method = 'lo.log_time';
	}

	$context['sort_direction'] = isset($_REQUEST['asc']) || (isset($_REQUEST['sort_dir']) && $_REQUEST['sort_dir'] == 'asc') ? 'up' : 'down';

	$conditions = array();
	if (!allowedTo('moderate_forum'))
		$conditions[] = '(IFNULL(mem.show_online, 1) = 1)';

	// Does the user wish to apply a filter?
	if (isset($_REQUEST['show'], $show_methods[$_REQUEST['show']]))
	{
		// There should be two inputs, show and showtop. If we're coming from JS, these will be the same. If not... make sure we use the right one!
		if (isset($_REQUEST['btnTop'], $_REQUEST['showtop'], $show_methods[$_REQUEST['showtop']]))
			$_REQUEST['show'] = $_REQUEST['showtop'];
		$context['show_by'] = $_SESSION['who_online_filter'] = $_REQUEST['show'];
		$conditions[] = $show_methods[$_REQUEST['show']];
	}
	// Perhaps we saved a filter earlier in the session?
	elseif (isset($_SESSION['who_online_filter'], $show_methods[$_SESSION['who_online_filter']]))
	{
		$context['show_by'] = $_SESSION['who_online_filter'];
		$conditions[] = $show_methods[$_SESSION['who_online_filter']];
	}
	else
		$context['show_by'] = $_SESSION['who_online_filter'] = 'all';

	// Get the total amount of members online.
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}log_online AS lo
			LEFT JOIN {db_prefix}members AS mem ON (lo.id_member = mem.id_member)' . (!empty($conditions) ? '
		WHERE ' . implode(' AND ', $conditions) : ''),
		array(
		)
	);
	list ($totalMembers) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Prepare some page index variables.
	$context['page_index'] = template_page_index('<URL>?action=who;sort=' . $context['sort_by'] . ($context['sort_direction'] == 'up' ? ';asc' : '') . ';show=' . $context['show_by'], $_REQUEST['start'], $totalMembers, $settings['defaultMaxMembers']);
	$context['start'] = $_REQUEST['start'];

	// Look for people online, provided they don't mind if you see they are.
	$request = wesql::query('
		SELECT
			lo.log_time, lo.id_member, lo.url, li.member_ip AS ip, mem.real_name,
			lo.session, IFNULL(mem.show_online, 1) AS show_online,
			lo.id_spider
		FROM {db_prefix}log_online AS lo
			LEFT JOIN {db_prefix}members AS mem ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}log_ips AS li ON (lo.ip = li.id_ip)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_member} THEN mem.id_post_group ELSE mem.id_group END)' . (!empty($conditions) ? '
		WHERE ' . implode(' AND ', $conditions) : '') . '
		ORDER BY {raw:sort_method} {raw:sort_direction}
		LIMIT {int:offset}, {int:limit}',
		array(
			'regular_member' => 0,
			'sort_method' => $sort_method,
			'sort_direction' => $context['sort_direction'] == 'up' ? 'ASC' : 'DESC',
			'offset' => $context['start'],
			'limit' => $settings['defaultMaxMembers'],
		)
	);
	$context['members'] = array();
	$member_ids = array();
	$url_data = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$actions = @unserialize($row['url']);
		if ($actions === false)
			continue;

		// Send the information to the template.
		$context['members'][$row['session']] = array(
			'id' => $row['id_member'],
			'ip' => allowedTo('manage_bans') ? format_ip($row['ip']) : '',
			// It is *going* to be today or yesterday, so why keep that information in there?
			'time' => strtr(timeformat($row['log_time']), array($txt['today'] => '', $txt['yesterday'] => '')),
			'timestamp' => forum_time(true, $row['log_time']),
			'query' => $actions,
			'is_hidden' => $row['show_online'] == 0,
			'id_spider' => $row['id_spider']
		);

		$url_data[$row['session']] = array($row['url'], $row['id_member']);
		$member_ids[] = $row['id_member'];
	}
	wesql::free_result($request);

	// Load the user data for these members.
	loadMemberData($member_ids);

	// Load up the guest user.
	$memberContext[0] = array(
		'id' => 0,
		'name' => $txt['guest_title'],
		'group' => $txt['guest_title'],
		'href' => '',
		'link' => $txt['guest_title'],
		'email' => $txt['guest_title'],
		'is_guest' => true
	);

	// Are we showing spiders?
	$spiderContext = array();
	if (!empty($settings['spider_mode']) && !empty($settings['show_spider_online']) && ($settings['show_spider_online'] == 2 || allowedTo('admin_forum')) && !empty($settings['spider_name_cache']))
	{
		foreach (unserialize($settings['spider_name_cache']) as $id => $name)
			$spiderContext[$id] = array(
				'id' => 0,
				// Spiders get their own Noisen style.
				'name' => '<em style="color: green">' . $name . '</em>',
				'group' => $txt['spiders'],
				'href' => '',
				'link' => $name,
				'email' => $name,
				'is_guest' => true
			);
	}

	$url_data = determineActions($url_data);

	// Setup the linktree and page title (do it down here because the language files are now loaded..)
	$context['page_title'] = $txt['who_title'];
	$context['linktree'][] = array(
		'url' => '<URL>?action=who',
		'name' => $txt['who_title']
	);

	// Put it in the context variables.
	foreach ($context['members'] as $i => $member)
	{
		if ($member['id'] != 0)
			$member['id'] = loadMemberContext($member['id']) ? $member['id'] : 0;

		// Keep the IP that came from the database.
		$memberContext[$member['id']]['ip'] = $member['ip'];
		$context['members'][$i]['action'] = isset($url_data[$i]) ? $url_data[$i] : $txt['who_hidden'];
		if ($member['id'] == 0 && isset($spiderContext[$member['id_spider']]))
			$context['members'][$i] += $spiderContext[$member['id_spider']];
		else
			$context['members'][$i] += $memberContext[$member['id']];
	}

	// Some people can't send personal messages...
	$context['can_send_pm'] = allowedTo('pm_send');

	// Any profile fields disabled?
	$context['disabled_fields'] = isset($settings['disabled_profile_fields']) ? array_flip(explode(',', $settings['disabled_profile_fields'])) : array();

}

function determineActions($urls, $preferred_prefix = false)
{
	global $context, $txt, $settings, $theme;

	if (!allowedTo('who_view'))
		return array();
	loadLanguage('Who');
	call_lang_hook('lang_who');

	// Display of errors may be applicable. It's only for people with POWARZ you can't comprehend though.
	if (allowedTo('moderate_forum'))
		loadLanguage('Errors');

	// Actions that require a specific permission level.
	$allowedActions = array(
		'admin' => array('moderate_forum', 'manage_membergroups', 'manage_bans', 'admin_forum', 'manage_permissions', 'send_mail', 'manage_attachments', 'manage_smileys', 'manage_boards', 'edit_news'),
		'ban' => array('manage_bans'),
		'boardrecount' => array('admin_forum'),
		'editnews' => array('edit_news'),
		'mailing' => array('send_mail'),
		'maintain' => array('admin_forum'),
		'manageattachments' => array('manage_attachments'),
		'manageboards' => array('manage_boards'),
		'mlist' => array('view_mlist'),
		'moderate' => array('access_mod_center'),
		'optimizetables' => array('admin_forum'),
		'repairboards' => array('admin_forum'),
		'search' => array('search_posts'),
		'search2' => array('search_posts'),
		'setcensor' => array('moderate_forum'),
		'stats' => array('view_stats'),
		'viewErrorLog' => array('admin_forum'),
		'viewmembers' => array('moderate_forum'),
	);

	// And if plugins want to make use of this too...
	call_hook('who_allowed', array(&$allowedActions));

	if (!is_array($urls))
		$url_list = array(array($urls, we::$id));
	else
		$url_list = $urls;

	// These are done to later query these in large chunks. (instead of one by one.)
	$topic_ids = array();
	$profile_ids = array();
	$board_ids = array();

	$data = array();
	foreach ($url_list as $k => $url)
	{
		// Get the request parameters..
		$actions = @unserialize($url[0]);
		if ($actions === false)
			continue;

		// If it's the admin or moderation center, and there is an area set, use that instead.
		if (isset($actions['action'], $actions['area']) && ($actions['action'] == 'admin' || $actions['action'] == 'moderate'))
			$actions['action'] = $actions['area'];

		// By default, unlisted or unknown action.
		$data[$k] = $txt['who_unknown'];

		// Check if there was no action or the action is display.
		if (!isset($actions['action']) || $actions['action'] == 'display')
		{
			// It's a topic! Must be!
			if (isset($actions['topic']))
			{
				// Assume they can't view it, and queue it up for later.
				$data[$k] = $txt['who_hidden'];
				$topic_ids[(int) $actions['topic']][$k] = $txt['who_topic'];
			}
			// It's a board!
			elseif (isset($actions['board']))
			{
				// Hide first, show later.
				$data[$k] = $txt['who_hidden'];
				$board_ids[$actions['board']][$k] = $txt['who_board'];
			}
			// It's the board index!! It must be!
			else
				$data[$k] = str_replace('{forum_name}', $context['forum_name'], $txt['who_index']);
		}
		// Probably an error or some goon?
		elseif ($actions['action'] == '')
			$data[$k] = str_replace('{forum_name}', $context['forum_name'], $txt['who_index']);
		// Some other normal action...?
		else
		{
			// Viewing/editing a profile.
			if ($actions['action'] == 'profile')
			{
				// Whose? Their own?
				if (empty($actions['u']))
					$actions['u'] = $url[1];

				$data[$k] = $txt['who_hidden'];
				$profile_ids[(int) $actions['u']][$k] = $actions['action'] == 'profile' ? $txt['who_viewprofile'] : $txt['who_profile'];
			}
			elseif (($actions['action'] == 'post' || $actions['action'] == 'post2') && empty($actions['topic']) && isset($actions['board']))
			{
				$data[$k] = $txt['who_hidden'];
				$board_ids[(int) $actions['board']][$k] = isset($actions['poll']) ? $txt['who_poll'] : $txt['who_post'];
			}
			// A subaction anyone can view... if the language string is there, show it.
			elseif (isset($actions['sa'], $txt['whoall_' . $actions['action'] . '_' . $actions['sa']]))
				$data[$k] = $preferred_prefix && isset($txt[$preferred_prefix . $actions['action'] . '_' . $actions['sa']]) ? $txt[$preferred_prefix . $actions['action'] . '_' . $actions['sa']] : $txt['whoall_' . $actions['action'] . '_' . $actions['sa']];
			// An action any old fellow can look at. (if ['whoall_' . $action] exists, we know everyone can see it.)
			elseif (isset($txt['whoall_' . $actions['action']]))
				$data[$k] = $preferred_prefix && isset($txt[$preferred_prefix . $actions['action']]) ? $txt[$preferred_prefix . $actions['action']] : $txt['whoall_' . $actions['action']];
			// Viewable if and only if they can see the board, and it's a specific action/subaction
			elseif (isset($actions['sa'], $txt['whotopic_' . $actions['action'] . '_' . $actions['sa']]))
			{
				// Find out what topic they are accessing.
				$topic = (int) (isset($actions['topic']) ? $actions['topic'] : (isset($actions['from']) ? $actions['from'] : 0));

				$data[$k] = $txt['who_hidden'];
				$topic_ids[$topic][$k] = $txt['whotopic_' . $actions['action'] . '_' . $actions['sa']];
			}
			// Viewable if and only if they can see the board... but it's only a general action
			elseif (isset($txt['whotopic_' . $actions['action']]))
			{
				// Find out what topic they are accessing.
				$topic = (int) (isset($actions['topic']) ? $actions['topic'] : (isset($actions['from']) ? $actions['from'] : 0));

				$data[$k] = $txt['who_hidden'];
				$topic_ids[$topic][$k] = $txt['whotopic_' . $actions['action']];
			}
			elseif (isset($txt['whopost_' . $actions['action']]))
			{
				// Find out what message they are accessing.
				$msgid = (int) (isset($actions['msg']) ? $actions['msg'] : (isset($actions['quote']) ? $actions['quote'] : 0));

				$result = wesql::query('
					SELECT m.id_topic, m.subject
					FROM {db_prefix}messages AS m
						INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
						INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic AND {query_see_topic})
					WHERE m.id_msg = {int:id_msg}
						AND {query_see_board}' . ($settings['postmod_active'] ? '
						AND m.approved = {int:is_approved}' : '') . '
					LIMIT 1',
					array(
						'is_approved' => 1,
						'id_msg' => $msgid,
					)
				);
				list ($id_topic, $subject) = wesql::fetch_row($result);
				$data[$k] = sprintf($txt['whopost_' . $actions['action']], $id_topic, $subject);
				wesql::free_result($result);

				if (empty($id_topic))
					$data[$k] = $txt['who_hidden'];
			}
			// Viewable only by administrators.. (if it starts with whoadmin, it's admin only!)
			elseif (allowedTo('moderate_forum') && isset($txt['whoadmin_' . $actions['action']]))
				$data[$k] = $txt['whoadmin_' . $actions['action']];
			// Viewable by permission level.
			elseif (isset($allowedActions[$actions['action']]))
			{
				if (allowedTo($allowedActions[$actions['action']]))
					$data[$k] = $txt['whoallow_' . $actions['action']];
				else
					$data[$k] = $txt['who_hidden'];
			}
			elseif ($actions['action'] == 'media')
			{
				loadSource('media/Subs-Media');

				if (!isset($mediaFetch))
					$mediaFetch = array();

				$ret = array();
				$ret = aeva_getOnlineType($actions);
				if ($ret[0] == 'hidden')
					$data[$k] = $txt['who_hidden'];
				elseif ($ret[0] == 'direct')
					$data[$k] = $ret[1];
				// Viewing an item? We'll need to fetch up data...
				else
					$mediaFetch[$ret[3]][$k] = array(
						'id' => $ret[2],
						'type' => $ret[1]
					);
			}

			$data[$k] = str_replace('{forum_name}', $context['forum_name'], $data[$k]);
		}

		// Maybe the action is integrated into another system?
		if (count($hook_actions = call_hook('whos_online', array($actions))) > 0)
		{
			foreach ($hook_actions as $hook_action)
			{
				if (!empty($hook_action))
				{
					$data[$k] = $hook_action;
					break;
				}
			}
		}

		if (allowedTo('moderate_forum'))
		{
			$error_message = '';
			$is_warn = isset($actions['who_warn']);

			if (isset($actions['who_error_raw']))
				$error_message = str_replace('"', '&quot;', $actions['who_error_raw']);
			elseif (isset($actions['who_error_lang'], $txt[$actions['who_error_lang']]))
				$error_message = str_replace('"', '&quot;', empty($action['who_error_params']) ? $txt[$actions['who_error_lang']] : vsprintf($txt[$actions['who_error_lang']], $action['who_error_params']));
			elseif (isset($actions['who_warn']))
				$error_message = str_replace('"', '&quot;', $txt['who_guest_login']);

			if (!empty($error_message))
				$data[$k] = '<img src="' . $theme['images_url'] . '/' . ($is_warn ? 'who_warn' : 'who_error') . '.gif" title="' . $error_message . '" alt="' . $error_message . '"> ' . $data[$k];

			// !!! Should we store the full URL into the session, à la Noisen?
			$data[$k] .= ' (<abbr title="' . str_replace('"', "''", var_export($actions, true)) . '">?</abbr>)';
		}
	}

	if (!empty($mediaFetch))
		foreach ($mediaFetch as $type => $array)
			if ($dat = aeva_getFetchData($type, $array))
				$data = array_merge($data, $dat);

	// Load topic names.
	if (!empty($topic_ids))
	{
		$result = wesql::query('
			SELECT t.id_topic, m.subject, b.url, b.name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			WHERE {query_see_board}
				AND t.id_topic IN ({array_int:topic_list})
				AND {query_see_topic}
			LIMIT {int:limit}',
			array(
				'topic_list' => array_keys($topic_ids),
				'limit' => count($topic_ids),
			)
		);
		while ($row = wesql::fetch_assoc($result))
		{
			// Show the topic's subject for each of the actions.
			foreach ($topic_ids[$row['id_topic']] as $k => $session_text)
				$data[$k] = sprintf($session_text, $row['id_topic'], censorText($row['subject'])) . ' (<a href="http://' . $row['url'] . '">' . $row['name'] . '</a>)';
		}
		wesql::free_result($result);
	}

	// Load board names.
	if (!empty($board_ids))
	{
		$result = wesql::query('
			SELECT b.id_board, b.name
			FROM {db_prefix}boards AS b
			WHERE {query_see_board}
				AND b.id_board IN ({array_int:board_list})
			LIMIT ' . count($board_ids),
			array(
				'board_list' => array_keys($board_ids),
			)
		);
		while ($row = wesql::fetch_assoc($result))
		{
			// Put the board name into the string for each member...
			foreach ($board_ids[$row['id_board']] as $k => $session_text)
				$data[$k] = sprintf($session_text, $row['id_board'], $row['name']);
		}
		wesql::free_result($result);
	}

	// Load member names for the profile.
	if (!empty($profile_ids) && (allowedTo('profile_view_any') || allowedTo('profile_view_own')))
	{
		$result = wesql::query('
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})
			LIMIT ' . count($profile_ids),
			array(
				'member_list' => array_keys($profile_ids),
			)
		);
		while ($row = wesql::fetch_assoc($result))
		{
			// If they aren't allowed to view this person's profile, skip it.
			if (!allowedTo('profile_view_any') && we::$id != $row['id_member'])
				continue;

			// Set their action on each - session/text to sprintf.
			foreach ($profile_ids[$row['id_member']] as $k => $session_text)
				$data[$k] = sprintf($session_text, $row['id_member'], $row['real_name']);
		}
		wesql::free_result($result);
	}

	// While the above whos_online hook is good for more complex cases than action=x;sa=y, it's not particularly efficient if you're dealing with multiple lookups and so on. Thus the bulk hook too.
	call_hook('whos_online_complete', array(&$urls, &$data));

	if (!is_array($urls))
		return isset($data[0]) ? $data[0] : false;
	else
		return $data;
}
