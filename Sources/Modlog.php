<?php
/**
 * Wedge
 *
 * Displays the moderation and administration logs, and processes the delete request if it arrives.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	void ViewModlog()
		- prepares the information from the moderation log for viewing.
		- disallows the deletion of events within twenty-four hours of now.
		- requires the admin_forum permission.
		- uses the Modlog template, main block.
		- is accessed via ?action=moderate;area=modlog.

	int list_getModLogEntries()
		// !!

	array list_getModLogEntries($start, $items_per_page, $sort, $query_string = '', $query_params = array(), $log_type = 1)
		- Gets the moderation log entries that match the specified paramaters
		- limit can be an array with two values
		- search_param and order should be proper SQL strings or blank.  If blank they are not used.
*/

// Show the moderation log
function ViewModlog()
{
	global $txt, $context, $theme;

	// Are we looking at the moderation log or the administration log.
	$context['log_type'] = isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'adminlog' ? 3 : 1;
	if ($context['log_type'] == 3)
		isAllowedTo('admin_forum');

	// These change depending on whether we are viewing the moderation or admin log.
	if ($context['log_type'] == 3 || $context['action'] === 'admin')
		$context['url_start'] = '?action=admin;area=logs;sa=' . ($context['log_type'] == 3 ? 'adminlog' : 'modlog') . ';type=' . $context['log_type'];
	else
		$context['url_start'] = '?action=moderate;area=modlog;type=' . $context['log_type'];

	$context['can_delete'] = allowedTo('admin_forum');

	loadLanguage('Modlog');
	call_lang_hook('lang_modlog');

	$context['page_title'] = $context['log_type'] == 3 ? $txt['modlog_admin_log'] : $txt['modlog_view'];

	// The number of entries to show per page of log file.
	$context['displaypage'] = 30;
	// Amount of hours that must pass before allowed to delete file.
	$context['hoursdisable'] = 24;

	// Handle deletion...
	if (isset($_POST['removeall']) && $context['can_delete'])
	{
		checkSession();

		wesql::query('
			DELETE FROM {db_prefix}log_actions
			WHERE id_log = {int:moderate_log}
				AND log_time < {int:twenty_four_hours_wait}',
			array(
				'twenty_four_hours_wait' => time() - $context['hoursdisable'] * 3600,
				'moderate_log' => $context['log_type'],
			)
		);
	}
	elseif (!empty($_POST['remove']) && isset($_POST['delete']) && $context['can_delete'])
	{
		checkSession();
		wesql::query('
			DELETE FROM {db_prefix}log_actions
			WHERE id_log = {int:moderate_log}
				AND id_action IN ({array_string:delete_actions})
				AND log_time < {int:twenty_four_hours_wait}',
			array(
				'twenty_four_hours_wait' => time() - $context['hoursdisable'] * 3600,
				'delete_actions' => array_unique($_POST['delete']),
				'moderate_log' => $context['log_type'],
			)
		);
	}

	// Do the column stuff!
	$sort_types = array(
		'action' =>'lm.action',
		'time' => 'lm.log_time',
		'member' => 'mem.real_name',
		'group' => 'mg.group_name',
		'ip' => 'lm.ip',
	);

	// Setup the direction stuff...
	$context['order'] = isset($_REQUEST['sort'], $sort_types[$_REQUEST['sort']]) ? $_REQUEST['sort'] : 'time';

	// If we're coming from a search, get the variables.
	if (!empty($_REQUEST['params']) && empty($_REQUEST['is_search']))
	{
		$search_params = base64_decode(strtr($_REQUEST['params'], array(' ' => '+')));
		$search_params = @unserialize($search_params);
	}

	// This array houses all the valid search types.
	$searchTypes = array(
		'action' => array('sql' => 'lm.action', 'label' => $txt['modlog_action']),
		'member' => array('sql' => 'mem.real_name', 'label' => $txt['modlog_member']),
		'group' => array('sql' => 'mg.group_name', 'label' => $txt['modlog_position']),
		'ip' => array('sql' => 'lm.ip', 'label' => $txt['modlog_ip'])
	);

	if (!isset($search_params['string']) || (!empty($_REQUEST['search']) && $search_params['string'] != $_REQUEST['search']))
		$search_params_string = empty($_REQUEST['search']) ? '' : $_REQUEST['search'];
	else
		$search_params_string = $search_params['string'];

	if (isset($_REQUEST['search_type']) || empty($search_params['type']) || !isset($searchTypes[$search_params['type']]))
		$search_params_type = isset($_REQUEST['search_type'], $searchTypes[$_REQUEST['search_type']]) ? $_REQUEST['search_type'] : (isset($searchTypes[$context['order']]) ? $context['order'] : 'member');
	else
		$search_params_type = $search_params['type'];

	$search_params_column = $searchTypes[$search_params_type]['sql'];
	$search_params = array(
		'string' => $search_params_string,
		'type' => $search_params_type,
	);

	// Setup the search context.
	$context['search_params'] = empty($search_params['string']) ? '' : base64_encode(serialize($search_params));
	$context['search'] = array(
		'string' => $search_params['string'],
		'type' => $search_params['type'],
		'label' => $searchTypes[$search_params_type]['label'],
		'display_string' => westr::safe($search_params['string'], ENT_QUOTES),
	);

	// If they are searching by action, then we must do some manual intervention to search in their language!
	if ($search_params['type'] == 'action' && !empty($search_params['string']))
	{
		// For the moment they can only search for ONE action!
		foreach ($txt as $key => $text)
		{
			if (substr($key, 0, 10) == 'modlog_ac_' && strpos($text, $search_params['string']) !== false)
			{
				$search_params['string'] = substr($key, 10);
				break;
			}
		}
	}

	loadSource('Subs-List');

	// This is all the information required for a watched user listing.
	$listOptions = array(
		'id' => 'moderation_log_list',
		'title' => '<a href="<URL>?action=help;in=' . ($context['log_type'] == 3 ? 'adminlog' : 'modlog') . '" onclick="return reqWin(this);" class="help" title="' . $txt['help'] . '"></a> ' . $txt['modlog_' . ($context['log_type'] == 3 ? 'admin' : 'moderation') . '_log'],
		'width' => '100%',
		'items_per_page' => $context['displaypage'],
		'no_items_label' => $txt['modlog_' . ($context['log_type'] == 3 ? 'admin_log_' : '') . 'no_entries_found'],
		'base_href' => '<URL>' . $context['url_start'] . (!empty($context['search_params']) ? ';params=' . $context['search_params'] : ''),
		'default_sort_col' => 'time',
		'get_items' => array(
			'function' => 'list_getModLogEntries',
			'params' => array(
				(!empty($search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
				array('sql_type' => $search_params_column, 'search_string' => $search_params['string']),
				$context['log_type'],
			),
		),
		'get_count' => array(
			'function' => 'list_getModLogEntryCount',
			'params' => array(
				(!empty($search_params['string']) ? ' INSTR({raw:sql_type}, {string:search_string})' : ''),
				array('sql_type' => $search_params_column, 'search_string' => $search_params['string']),
				$context['log_type'],
			),
		),
		// This assumes we are viewing by user.
		'columns' => array(
			'action' => array(
				'header' => array(
					'value' => $txt['modlog_action'],
					'class' => 'left first_th',
				),
				'data' => array(
					'db' => 'action_text',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.action',
					'reverse' => 'lm.action DESC',
				),
			),
			'time' => array(
				'header' => array(
					'value' => $txt['modlog_date'],
					'class' => 'left',
				),
				'data' => array(
					'db' => 'time',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.log_time DESC',
					'reverse' => 'lm.log_time',
				),
			),
			'moderator' => array(
				'header' => array(
					'value' => $txt['modlog_member'],
					'class' => 'left',
				),
				'data' => array(
					'db' => 'moderator_link',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'mem.real_name',
					'reverse' => 'mem.real_name DESC',
				),
			),
			'position' => array(
				'header' => array(
					'value' => $txt['modlog_position'],
					'class' => 'left',
				),
				'data' => array(
					'db' => 'position',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'mg.group_name',
					'reverse' => 'mg.group_name DESC',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => $txt['modlog_ip'],
					'class' => 'left',
				),
				'data' => array(
					'db' => 'ip',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'lm.ip',
					'reverse' => 'lm.ip DESC',
				),
			),
			'delete' => array(
				'header' => array(
					'value' => '<input type="checkbox" name="all" onclick="invertAll(this, this.form);">',
				),
				'data' => array(
					'function' => create_function('$entry', '
						return \'<input type="checkbox" name="delete[]" value="\' . $entry[\'id\'] . \'"\' . ($entry[\'editable\'] ? \'\' : \' disabled\') . \'>\';
					'),
					'style' => 'text-align: center',
				),
			),
		),
		'form' => array(
			'href' => '<URL>' . $context['url_start'],
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				$context['session_var'] => $context['session_id'],
				'params' => $context['search_params']
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['modlog_' . ($context['log_type'] == 3 ? 'admin' : 'moderation') . '_log_desc'],
				'class' => 'smalltext',
				'style' => 'padding: 2ex',
			),
			array(
				'position' => 'below_table_data',
				'value' => '
					' . $txt['modlog_search'] . ' (' . $txt['modlog_by'] . ': ' . $context['search']['label'] . '):
					<input type="search" name="search" size="18" value="' . $context['search']['display_string'] . '"> <input type="submit" name="is_search" value="' . $txt['modlog_go'] . '">
					' . ($context['can_delete'] ? ' |
						<input type="submit" name="remove" value="' . $txt['remove'] . '" class="delete">
						<input type="submit" name="removeall" value="' . $txt['modlog_removeall'] . '" class="delete">' : ''),
			),
		),
	);

	// Can they see all IP addresses? If not, they shouldn't see any.
	if (!allowedTo('manage_bans'))
		unset($listOptions['columns']['ip']);

	// Create the watched user list.
	createList($listOptions);

	wetem::load('show_list');
	$context['default_list'] = 'moderation_log_list';
}

// Get the number of mod log entries.
function list_getModLogEntryCount($query_string = '', $query_params = array(), $log_type = 1)
{
	$modlog_query = allowedTo('admin_forum') || we::$user['mod_cache']['bq'] == '1=1' ? '1=1' : (we::$user['mod_cache']['bq'] == '0=1' ? 'lm.id_board = 0 AND lm.id_topic = 0' : (strtr(we::$user['mod_cache']['bq'], array('id_board' => 'b.id_board')) . ' AND ' . strtr(we::$user['mod_cache']['bq'], array('id_board' => 't.id_board'))));

	$result = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}log_actions AS lm
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lm.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = lm.id_board)
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = lm.id_topic)
		WHERE id_log = {int:log_type}
			AND {raw:modlog_query}'
			. (!empty($query_string) ? '
				AND ' . $query_string : ''),
		array_merge($query_params, array(
			'reg_group_id' => 0,
			'log_type' => $log_type,
			'modlog_query' => $modlog_query,
		))
	);
	list ($entry_count) = wesql::fetch_row($result);
	wesql::free_result($result);

	return $entry_count;
}

function list_getModLogEntries($start, $items_per_page, $sort, $query_string = '', $query_params = array(), $log_type = 1)
{
	global $context, $txt, $callback_entry;

	$modlog_query = allowedTo('admin_forum') || we::$user['mod_cache']['bq'] == '1=1' ? '1=1' : (we::$user['mod_cache']['bq'] == '0=1' ? 'lm.id_board = 0 AND lm.id_topic = 0' : (strtr(we::$user['mod_cache']['bq'], array('id_board' => 'b.id_board')) . ' AND ' . strtr(we::$user['mod_cache']['bq'], array('id_board' => 't.id_board'))));
	$see_IP = allowedTo('manage_bans');

	// Do a little bit of self protection.
	if (!isset($context['hoursdisable']))
		$context['hoursdisable'] = 24;

	// Here we have the query getting the log details.
	$result = wesql::query('
		SELECT
			lm.id_action, lm.id_member, li.member_ip AS ip, lm.log_time, lm.action, lm.id_board, lm.id_topic, lm.id_msg, lm.extra,
			mem.real_name, mg.group_name
		FROM {db_prefix}log_actions AS lm
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = lm.id_member)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:reg_group_id} THEN mem.id_post_group ELSE mem.id_group END)
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = lm.id_board)
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = lm.id_topic)
			LEFT JOIN {db_prefix}log_ips AS li ON (lm.ip = li.id_ip)
			WHERE id_log = {int:log_type}
				AND {raw:modlog_query}'
			. (!empty($query_string) ? '
				AND ' . $query_string : '') . '
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array_merge($query_params, array(
			'reg_group_id' => 0,
			'log_type' => $log_type,
			'modlog_query' => $modlog_query,
		))
	);

	// Arrays for decoding objects into.
	$topics = array();
	$boards = array();
	$members = array();
	$messages = array();
	$entries = array();
	while ($row = wesql::fetch_assoc($result))
	{
		$row['extra'] = @unserialize($row['extra']);

		// Corrupt?
		$row['extra'] = is_array($row['extra']) ? $row['extra'] : array();

		// Add on some of the column stuff info
		if (!empty($row['id_board']))
		{
			if ($row['action'] == 'move')
				$row['extra']['board_to'] = $row['id_board'];
			else
				$row['extra']['board'] = $row['id_board'];
		}

		if (!empty($row['id_topic']))
			$row['extra']['topic'] = $row['id_topic'];
		if (!empty($row['id_msg']))
			$row['extra']['message'] = $row['id_msg'];

		// Is this associated with a topic?
		if (isset($row['extra']['topic']))
			$topics[(int) $row['extra']['topic']][] = $row['id_action'];
		if (isset($row['extra']['new_topic']))
			$topics[(int) $row['extra']['new_topic']][] = $row['id_action'];

		// How about a member?
		if (isset($row['extra']['member']))
		{
			// Guests don't have names!
			if (empty($row['extra']['member']))
				$row['extra']['member'] = $txt['modlog_parameter_guest'];
			else
			{
				// Try to find it...
				$members[(int) $row['extra']['member']][] = $row['id_action'];
			}
		}

		// Associated with a board?
		if (isset($row['extra']['board_to']))
			$boards[(int) $row['extra']['board_to']][] = $row['id_action'];
		if (isset($row['extra']['board_from']))
			$boards[(int) $row['extra']['board_from']][] = $row['id_action'];
		if (isset($row['extra']['board']))
			$boards[(int) $row['extra']['board']][] = $row['id_action'];

		// A message?
		if (isset($row['extra']['message']))
			$messages[(int) $row['extra']['message']][] = $row['id_action'];

		// IP Info?
		if (isset($row['extra']['ip_range']))
			$row['extra']['ip_range'] = '<a href="<URL>?action=trackip;searchip=' . $row['extra']['ip_range'] . '">' . $row['extra']['ip_range'] . '</a>';

		// Email?
		if (isset($row['extra']['email']))
			$row['extra']['email'] = '<a href="mailto:' . $row['extra']['email'] . '">' . $row['extra']['email'] . '</a>';

		// Bans are complex.
		if ($row['action'] == 'ban')
		{
			$row['action_text'] = $txt['modlog_ac_ban'];
			foreach (array('member', 'email', 'ip_range', 'hostname') as $type)
				if (isset($row['extra'][$type]))
					$row['action_text'] .= $txt['modlog_ac_ban_trigger_' . $type];
		}

		// The array to go to the template. Note here that action is set to a "default" value of the action doesn't match anything in the descriptions. Allows easy adding of logging events with basic details.
		$entries[$row['id_action']] = array(
			'id' => $row['id_action'],
			'ip' => $see_IP ? format_ip($row['ip']) : $txt['logged'],
			'position' => empty($row['real_name']) && empty($row['group_name']) ? $txt['guest'] : $row['group_name'],
			'moderator_link' => $row['id_member'] ? '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>' : (empty($row['real_name']) ? ($txt['guest'] . (!empty($row['extra']['member_acted']) ? ' (' . $row['extra']['member_acted'] . ')' : '')) : $row['real_name']),
			'time' => timeformat($row['log_time']),
			'timestamp' => forum_time(true, $row['log_time']),
			'editable' => time() > $row['log_time'] + $context['hoursdisable'] * 3600,
			'extra' => $row['extra'],
			'action' => $row['action'],
			'action_text' => isset($row['action_text']) ? $row['action_text'] : '',
		);
	}
	wesql::free_result($result);

	if (!empty($boards))
	{
		$request = wesql::query('
			SELECT id_board, name
			FROM {db_prefix}boards
			WHERE id_board IN ({array_int:board_list})
			LIMIT ' . count(array_keys($boards)),
			array(
				'board_list' => array_keys($boards),
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			foreach ($boards[$row['id_board']] as $action)
			{
				// Make the board number into a link - dealing with moving too.
				if (isset($entries[$action]['extra']['board_to']) && $entries[$action]['extra']['board_to'] == $row['id_board'])
					$entries[$action]['extra']['board_to'] = '<a href="<URL>?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>';
				elseif (isset($entries[$action]['extra']['board_from']) && $entries[$action]['extra']['board_from'] == $row['id_board'])
					$entries[$action]['extra']['board_from'] = '<a href="<URL>?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>';
				elseif (isset($entries[$action]['extra']['board']) && $entries[$action]['extra']['board'] == $row['id_board'])
					$entries[$action]['extra']['board'] = '<a href="<URL>?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>';
			}
		}
		wesql::free_result($request);
	}

	if (!empty($topics))
	{
		$request = wesql::query('
			SELECT ms.subject, t.id_topic
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			WHERE t.id_topic IN ({array_int:topic_list})
			LIMIT ' . count(array_keys($topics)),
			array(
				'topic_list' => array_keys($topics),
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			foreach ($topics[$row['id_topic']] as $action)
			{
				$this_action =& $entries[$action];

				// This isn't used in the current theme.
				$this_action['topic'] = array(
					'id' => $row['id_topic'],
					'subject' => $row['subject'],
					'href' => '<URL>?topic=' . $row['id_topic'] . '.0',
					'link' => '<a href="<URL>?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>'
				);

				// Make the topic number into a link - dealing with splitting too.
				if (isset($this_action['extra']['topic']) && $this_action['extra']['topic'] == $row['id_topic'])
					$this_action['extra']['topic'] = '<a href="<URL>?topic=' . $row['id_topic'] . '.' . (isset($this_action['extra']['message']) ? 'msg' . $this_action['extra']['message'] . '#msg' . $this_action['extra']['message'] : '0') . '">' . $row['subject'] . '</a>';
				elseif (isset($this_action['extra']['new_topic']) && $this_action['extra']['new_topic'] == $row['id_topic'])
					$this_action['extra']['new_topic'] = '<a href="<URL>?topic=' . $row['id_topic'] . '.' . (isset($this_action['extra']['message']) ? 'msg' . $this_action['extra']['message'] . '#msg' . $this_action['extra']['message'] : '0') . '">' . $row['subject'] . '</a>';
			}
		}
		wesql::free_result($request);
	}

	if (!empty($messages))
	{
		$request = wesql::query('
			SELECT id_msg, subject
			FROM {db_prefix}messages
			WHERE id_msg IN ({array_int:message_list})
			LIMIT ' . count(array_keys($messages)),
			array(
				'message_list' => array_keys($messages),
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			foreach ($messages[$row['id_msg']] as $action)
			{
				$this_action =& $entries[$action];

				// This isn't used in the current theme.
				$this_action['message'] = array(
					'id' => $row['id_msg'],
					'subject' => $row['subject'],
					'href' => '<URL>?msg=' . $row['id_msg'],
					'link' => '<a href="<URL>?msg=' . $row['id_msg'] . '">' . $row['subject'] . '</a>',
				);

				// Make the message number into a link.
				if (isset($this_action['extra']['message']) && $this_action['extra']['message'] == $row['id_msg'])
					$this_action['extra']['message'] = '<a href="<URL>?msg=' . $row['id_msg'] . '">' . $row['subject'] . '</a>';
			}
		}
		wesql::free_result($request);
	}

	if (!empty($members))
	{
		$request = wesql::query('
			SELECT real_name, id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})
			LIMIT ' . count(array_keys($members)),
			array(
				'member_list' => array_keys($members),
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			foreach ($members[$row['id_member']] as $action)
			{
				// Not used currently.
				$entries[$action]['member'] = array(
					'id' => $row['id_member'],
					'name' => $row['real_name'],
					'href' => '<URL>?action=profile;u=' . $row['id_member'],
					'link' => '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>'
				);
				// Make the member number into a name.
				$entries[$action]['extra']['member'] = '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
			}
		}
		wesql::free_result($request);
	}

	// Do some formatting of the action string.
	foreach ($entries as $k => $entry)
	{
		// Make any message info links so it's easier to go find that message.
		if (isset($entry['extra']['message']) && (empty($entry['message']) || empty($entry['message']['id'])))
			$entries[$k]['extra']['message'] = '<a href="<URL>?msg=' . $entry['extra']['message'] . '">' . $entry['extra']['message'] . '</a>';

		// Mark up any deleted members, topics and boards.
		foreach (array('board', 'board_from', 'board_to', 'member', 'topic', 'new_topic') as $type)
			if (!empty($entry['extra'][$type]) && is_numeric($entry['extra'][$type]))
				$entries[$k]['extra'][$type] = sprintf($txt['modlog_id'], $entry['extra'][$type]);

		if (empty($entry['action_text']))
			$entries[$k]['action_text'] = isset($txt['modlog_ac_' . $entry['action']]) ? $txt['modlog_ac_' . $entry['action']] : $entry['action'];
		$callback_entry = $entry['extra'];
		$entries[$k]['action_text'] = preg_replace_callback('~\{([A-Za-z\d_]+)\}~i', 'modlog_entry_callback', $entry['action_text']);
	}

	// Back we go!
	return $entries;
}

function modlog_entry_callback($match)
{
	global $callback_entry;
	return isset($callback_entry[$match[1]]) ? $callback_entry[$match[1]] : '';
}
