<?php
/**
 * Handle viewing the error log, deleting errors from the log and showing the contents of a file when there has been an error.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/* Show a list of all errors that were logged on the forum.

	void ViewErrorLog()
		- sets all the context up to show the error log for maintenance.
		- uses the Errors template and error_log block.
		- requires the maintain_forum permission.
		- uses the 'view_errors' administration area.
		- accessed from ?action=admin;area=logs;sa=errorlog.

	void deleteErrors()
		- deletes all or some of the errors in the error log.
		- applies any necessary filters to deletion.
		- should only be called by ViewErrorLog().
		- attempts to TRUNCATE the table to reset the auto_increment.
		- redirects back to the error log when done.

	void ViewIntrusionLog()
		- shows all intrusion attempts caught by the security code.

	void ViewFile()
		- will do php highlighting on the file specified in $_REQUEST['file']
		- file must be readable
		- full file path must be base64 encoded
		- user must have admin_forum permission
		- the line number number is specified by $_REQUEST['line']
		- Will try to get the 20 lines before and after the specified line
*/

// View the forum's error log.
function ViewErrorLog()
{
	global $txt, $context, $settings, $user_profile, $filter;

	// Only admins can view error logs and files.
	isAllowedTo('admin_forum');

	// Viewing contents of a file?
	if (isset($_GET['file']))
		return ViewFile();

	$context['can_see_ip'] = allowedTo('manage_bans');

	// Templates, etc...
	loadLanguage(array('Errors', 'ManageMaintenance'));
	loadTemplate('Errors');

	// You can filter by any of the following columns:
	$filters = array(
		'id_member' => $txt['username'],
		'ip' => $txt['ip_address'],
		'url' => $txt['error_url'],
		'message' => $txt['error_message'],
		'error_type' => $txt['error_type'],
		'file' => $txt['file'],
		'line' => $txt['line'],
	);
	if (!$context['can_see_ip'])
		unset($filters['ip']);

	// Set up the filtering...
	if (isset($_GET['value'], $_GET['filter'], $filters[$_GET['filter']]))
		$filter = array(
			'variable' => $_GET['filter'],
			'value' => array(
				'sql' => in_array($_GET['filter'], array('message', 'url', 'file')) ? base64_decode(strtr($_GET['value'], array(' ' => '+'))) : wesql::escape_wildcard_string($_GET['value']),
			),
			'href' => ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'],
			'entity' => $filters[$_GET['filter']]
		);

	// Deleting, are we?
	if (isset($_POST['delall']) || isset($_POST['delete']))
		deleteErrors();

	// Just how many errors are there?
	$result = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}log_errors' . (isset($filter) ? '
		WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : ''),
		array(
			'filter' => isset($filter) ? $filter['value']['sql'] : '',
		)
	);
	list ($num_errors) = wesql::fetch_row($result);
	wesql::free_result($result);

	// If this filter is empty...
	if ($num_errors == 0 && isset($filter))
		redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['asc']) ? ';asc' : ''));

	// Clean up start.
	if (!isset($_GET['start']) || $_GET['start'] < 0)
		$_GET['start'] = 0;

	// Do we want to reverse error listing?
	$context['sort_direction'] = isset($_REQUEST['asc']) ? 'up' : 'down';

	// Set the page listing up.
	$context['page_index'] = template_page_index('<URL>?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'up' ? ';asc' : '') . (isset($filter) ? $filter['href'] : ''), $_GET['start'], $num_errors, $settings['defaultMaxMessages']);
	$context['start'] = $_GET['start'];

	// Find and sort out the errors. 10KB per message log should be enough. And help prevent page crashes.
	$request = wesql::query('
		SELECT id_error, id_member, ip, li.member_ip AS display_ip, url, log_time, SUBSTR(message, 1, 10240) AS message, error_type, file, line
		FROM {db_prefix}log_errors AS le
			LEFT JOIN {db_prefix}log_ips AS li ON (le.ip = li.id_ip)' . (isset($filter) ? '
		WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : '') . '
		ORDER BY id_error' . ($context['sort_direction'] == 'down' ? ' DESC' : '') . '
		LIMIT ' . $_GET['start'] . ', ' . $settings['defaultMaxMessages'],
		array(
			'filter' => isset($filter) ? $filter['value']['sql'] : '',
		)
	);
	$context['errors'] = array();
	$members = array();

	for ($i = 0; $row = wesql::fetch_assoc($request); $i++)
	{
		$search_message = preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '%', wesql::escape_wildcard_string($row['message']));
		if ($search_message == $filter['value']['sql'])
			$search_message = wesql::escape_wildcard_string($row['message']);
		$show_message = strtr(strtr(preg_replace('~&lt;span class=&quot;remove&quot;&gt;(.+?)&lt;/span&gt;~', '$1', $row['message']), array("\r" => '', '<br>' => "\n", '<' => '&lt;', '>' => '&gt;', '"' => '&quot;')), array("\n" => '<br>'));

		if (strpos($row['error_type'], ':') !== false && empty($txt['errortype_' . $row['error_type']]))
			$txt['errortype_' . $row['error_type']] = substr(strrchr($row['error_type'], ':'), 1);

		$context['errors'][$row['id_error']] = array(
			'alternate' => $i %2 == 0,
			'member' => array(
				'id' => $row['id_member'],
				'ip' => $row['ip'],
				'display_ip' => $context['can_see_ip'] ? format_ip($row['display_ip']) : '',
			),
			'time' => timeformat($row['log_time']),
			'timestamp' => $row['log_time'],
			'url' => array(
				'html' => htmlspecialchars(($row['url'][0] === '?' ? SCRIPT : '') . $row['url']),
				'href' => base64_encode(wesql::escape_wildcard_string($row['url']))
			),
			'message' => array(
				'html' => $show_message,
				'href' => base64_encode($search_message)
			),
			'id' => $row['id_error'],
			'error_type' => array(
				'type' => $row['error_type'],
				'name' => isset($txt['errortype_' . $row['error_type']]) ? $txt['errortype_' . $row['error_type']] : $row['error_type'],
			),
			'file' => array(),
		);
		if (!empty($row['file']) && !empty($row['line']))
		{
			// Eval'd files rarely point to the right location and cause havoc for linking, so don't link them.
			$linkfile = strpos($row['file'], 'eval') === false || strpos($row['file'], '?') === false; // De Morgan's Law. Want this true unless both are present.

			$context['errors'][$row['id_error']]['file'] = array(
				'file' => $row['file'],
				'line' => $row['line'],
				'href' => '<URL>?action=admin;area=logs;sa=errorlog;file=' . base64_encode($row['file']) . ';line=' . $row['line'],
				'link' => $linkfile ? '<a href="<URL>?action=admin;area=logs;sa=errorlog;file=' . base64_encode($row['file']) . ';line=' . $row['line'] . '" onclick="return reqWin(this, 1280);">' . $row['file'] . '</a>' : $row['file'],
				'search' => base64_encode($row['file']),
			);
		}

		if ($filter['variable'] == 'ip')
			$context['filtering_ip'] = format_ip($row['display_ip']);

		// Make a list of members to load later.
		$members[$row['id_member']] = $row['id_member'];
	}
	wesql::free_result($request);

	// Load the member data.
	if (!empty($members))
	{
		// Get some additional member info...
		$request = wesql::query('
			SELECT id_member, member_name, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})
			LIMIT ' . count($members),
			array(
				'member_list' => $members,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$members[$row['id_member']] = $row;
		wesql::free_result($request);

		// This is a guest...
		$members[0] = array(
			'id_member' => 0,
			'member_name' => '',
			'real_name' => $txt['guest_title']
		);

		// Go through each error and tack the data on.
		foreach ($context['errors'] as $id => $dummy)
		{
			$memID = $context['errors'][$id]['member']['id'];
			$context['errors'][$id]['member']['username'] = isset($members[$memID]['member_name']) ? $members[$memID]['member_name'] : '(?)';
			$context['errors'][$id]['member']['name'] = isset($members[$memID]['real_name']) ? $members[$memID]['real_name'] : '(?)';
			$context['errors'][$id]['member']['href'] = empty($memID) ? '' : '<URL>?action=profile;u=' . $memID;
			$context['errors'][$id]['member']['link'] = empty($memID) ? $txt['guest_title'] : '<a href="<URL>?action=profile;u=' . $memID . '">' . $context['errors'][$id]['member']['name'] . '</a>';
		}
	}

	// Filtering anything?
	if (isset($filter))
	{
		$context['filter'] =& $filter;

		// Set the filtering context.
		if ($filter['variable'] == 'id_member')
		{
			$id = $filter['value']['sql'];
			loadMemberData($id, false, 'minimal');
			$context['filter']['value']['html'] = '<a href="<URL>?action=profile;u=' . $id . '">' . $user_profile[$id]['real_name'] . '</a>';
		}
		elseif ($filter['variable'] == 'message')
		{
			$context['filter']['value']['html'] = '&quot;' . strtr(westr::safe($filter['value']['sql']), array("\n" => '<br>', '&lt;br&gt;' => '<br>', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\')) . '&quot;';
			$context['filter']['value']['html'] = preg_replace('~&amp;lt;span class=&amp;quot;remove&amp;quot;&amp;gt;(.+?)&amp;lt;/span&amp;gt;~', '$1', $context['filter']['value']['html']);
		}
		elseif ($filter['variable'] == 'error_type')
			$context['filter']['value']['html'] = '&quot;' . strtr(westr::safe($filter['value']['sql']), array("\n" => '<br>', '&lt;br&gt;' => '<br>', "\t" => '&nbsp;&nbsp;&nbsp;', '\_' => '_', '\\%' => '%', '\\\\' => '\\')) . '&quot;';
		elseif ($filter['variable'] == 'url')
			$context['filter']['value']['html'] = '&quot;' . strtr(westr::safe((substr($filter['value']['sql'], 0, 1) == '?' ? SCRIPT : '') . $filter['value']['sql']), array('\_' => '_')) . '&quot;';
		elseif ($filter['variable'] == 'ip')
			$context['filter']['value']['html'] = $context['filtering_ip']; // we already stored this earlier!
		else
			$context['filter']['value']['html'] =& $filter['value']['sql'];
	}

	$context['error_types'] = array();

	$context['error_types']['all'] = array(
		'label' => $txt['errortype_all'],
		'description' => isset($txt['errortype_all_desc']) ? $txt['errortype_all_desc'] : '',
		'url' => '<URL>?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'up' ? ';asc' : ''),
		'is_selected' => empty($filter),
	);

	// What type of errors do we have and how many do we have?
	$sum = 0;
	$request = wesql::query('
		SELECT error_type, COUNT(*) AS num_errors
		FROM {db_prefix}log_errors
		GROUP BY error_type
		ORDER BY error_type = {literal:critical} DESC, error_type ASC'
	);
	while ($row = wesql::fetch_assoc($request))
	{
		// Total errors so far?
		$sum += $row['num_errors'];

		$context['error_types'][$sum] = array(
			'label' => (isset($txt['errortype_' . $row['error_type']]) ? $txt['errortype_' . $row['error_type']] : $row['error_type']) . ' (' . $row['num_errors'] . ')',
			'description' => isset($txt['errortype_' . $row['error_type'] . '_desc']) ? $txt['errortype_' . $row['error_type'] . '_desc'] : '',
			'url' => '<URL>?action=admin;area=logs;sa=errorlog' . ($context['sort_direction'] == 'up' ? ';asc' : '') . ';filter=error_type;value=' . $row['error_type'],
			'is_selected' => isset($filter) && $filter['value']['sql'] == wesql::escape_wildcard_string($row['error_type']),
		);
	}
	wesql::free_result($request);

	// Update the all errors tab with the total number of errors
	$context['error_types']['all']['label'] .= ' (' . $sum . ')';

	if (!isset($settings['app_error_count']) || $settings['app_error_count'] != $sum)
		updateErrorCount($sum);

	// And this is pretty basic ;)
	$context['page_title'] = $txt['errlog'];
	$context['has_filter'] = isset($filter);
	wetem::load('error_log');

	// Don't rewrite any URLs, we need them to remain exact!
	$settings['pretty_filters'] = array();
	$settings['pretty_enable_filters'] = false;
}

// Delete errors from the database.
function deleteErrors()
{
	global $filter;

	// Make sure the session exists and is correct; otherwise, might be a hacker.
	checkSession();

	// Delete all or just some?
	if (isset($_POST['delall']) && !isset($filter))
		wesql::query('
			TRUNCATE {db_prefix}log_errors',
			array(
			)
		);
	// Deleting all with a filter?
	elseif (isset($_POST['delall'], $filter))
		wesql::query('
			DELETE FROM {db_prefix}log_errors
			WHERE ' . $filter['variable'] . ' LIKE {string:filter}',
			array(
				'filter' => $filter['value']['sql'],
			)
		);
	// Just specific errors?
	elseif (!empty($_POST['delete']))
	{
		wesql::query('
			DELETE FROM {db_prefix}log_errors
			WHERE id_error IN ({array_int:error_list})',
			array(
				'error_list' => array_unique($_POST['delete']),
			)
		);
		updateErrorCount();

		// Go back to where we were.
		redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['asc']) ? ';asc' : '') . ';start=' . $_GET['start'] . (isset($filter) ? ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'] : ''));
	}
	updateErrorCount();

	// Back to the error log!
	redirectexit('action=admin;area=logs;sa=errorlog' . (isset($_REQUEST['asc']) ? ';asc' : ''));
}

function ViewIntrusionLog()
{
	global $txt, $context, $settings, $user_profile, $filter;

	// Check for the administrative permission to do this.
	isAllowedTo('admin_forum');

	$context['can_see_ip'] = allowedTo('manage_bans');

	// Templates, etc...
	loadLanguage('ManageMaintenance');
	loadLanguage('Security');
	loadTemplate('Errors');

	// You can filter by any of the following columns:
	$filters = array(
		'id_member' => $txt['username'],
		'ip' => $txt['ip_address'],
		'request_uri' => $txt['error_url'],
		'error_type' => $txt['error_type'],
		'http_method' => $txt['request_method'],
		'protocol' => $txt['request_protocol'],
		'user_agent' => $txt['user_agent'],
	);

	if (!$context['can_see_ip'])
		unset($filters['ip']);

	// Set up the filtering...
	if (isset($_GET['value'], $_GET['filter'], $filters[$_GET['filter']]))
		$filter = array(
			'variable' => $_GET['filter'],
			'value' => array(
				'sql' => in_array($_GET['filter'], array('request_uri', 'protocol', 'user_agent')) ? base64_decode(strtr($_GET['value'], array(' ' => '+'))) : wesql::escape_wildcard_string($_GET['value']),
			),
			'href' => ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'],
			'entity' => $filters[$_GET['filter']]
		);

	// Deleting, are we?
	if (isset($_POST['delall']) || isset($_POST['delete']))
		deleteIntrusions();

	// Just how many errors are there?
	$result = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}log_intrusion' . (isset($filter) ? '
		WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : ''),
		array(
			'filter' => isset($filter) ? $filter['value']['sql'] : '',
		)
	);
	list ($num_errors) = wesql::fetch_row($result);
	wesql::free_result($result);

	// If this filter is empty...
	if ($num_errors == 0 && isset($filter))
		redirectexit('action=admin;area=logs;sa=intrusionlog' . (isset($_REQUEST['asc']) ? ';asc' : ''));

	// Clean up start.
	if (!isset($_GET['start']) || $_GET['start'] < 0)
		$_GET['start'] = 0;

	// Do we want to reverse error listing?
	$context['sort_direction'] = isset($_REQUEST['asc']) ? 'up' : 'down';

	// Set the page listing up.
	$context['page_index'] = template_page_index('<URL>?action=admin;area=logs;sa=intrusionlog' . ($context['sort_direction'] == 'up' ? ';asc' : '') . (isset($filter) ? $filter['href'] : ''), $_GET['start'], $num_errors, $settings['defaultMaxMessages']);
	$context['start'] = $_GET['start'];

	// Find and sort out the errors.
	$request = wesql::query('
		SELECT id_event, id_member, error_type, ip, li.member_ip AS display_ip, event_time, http_method, request_uri, protocol, user_agent, headers
		FROM {db_prefix}log_intrusion AS lt
			LEFT JOIN {db_prefix}log_ips AS li ON (lt.ip = li.id_ip)' . (isset($filter) ? '
		WHERE ' . $filter['variable'] . ' LIKE {string:filter}' : '') . '
		ORDER BY id_event' . ($context['sort_direction'] == 'down' ? ' DESC' : '') . '
		LIMIT ' . $_GET['start'] . ', ' . $settings['defaultMaxMessages'],
		array(
			'filter' => isset($filter) ? $filter['value']['sql'] : '',
		)
	);
	$context['errors'] = array();
	$members = array();

	for ($i = 0; $row = wesql::fetch_assoc($request); $i++)
	{
		$context['errors'][$row['id_event']] = array(
			'alternate' => $i %2 == 0,
			'member' => array(
				'id' => $row['id_member'],
				'ip' => $row['ip'],
				'display_ip' => $context['can_see_ip'] ? format_ip($row['display_ip']) : '',
			),
			'time' => timeformat($row['event_time']),
			'timestamp' => $row['event_time'],
			'http_method' => $row['http_method'],
			'request_uri' => array(
				'html' => htmlspecialchars(($row['request_uri'][0] === '?' ? SCRIPT : '') . $row['request_uri']),
				'href' => base64_encode(wesql::escape_wildcard_string($row['request_uri']))
			),
			'protocol' => array(
				'html' => htmlspecialchars($row['protocol']),
				'href' => base64_encode(wesql::escape_wildcard_string($row['protocol']))
			),
			'user_agent' => array(
				'html' => htmlspecialchars($row['user_agent']),
				'href' => base64_encode(wesql::escape_wildcard_string($row['user_agent']))
			),
			'id' => $row['id_event'],
			'error_type' => array(
				'type' => $row['error_type'],
				'name' => isset($txt['behav_' . $row['error_type'] . '_log']) ? $txt['behav_' . $row['error_type'] . '_log'] : $row['error_type'],
			),
			'headers' => '',
		);

		if ($filter['variable'] == 'ip')
			$context['filtering_ip'] = format_ip($row['display_ip']);

		$row['headers'] = explode('<br>', $row['headers']);
		foreach ($row['headers'] as $k => $v)
		{
			$row['headers'][$k] = preg_replace('~^([a-z0-9-]+)=(.*)~i', '<b>$1</b>: $2', $v);
			if (strpos($v, 'Cookie') === 0)
				$row['headers'][$k] = '<span class="smalltext">' . str_replace('%', '%&shy;', $row['headers'][$k]) . '</span>';
		}
		$context['errors'][$row['id_event']]['headers'] = implode('<br>', $row['headers']);

		// Make a list of members to load later.
		$members[$row['id_member']] = $row['id_member'];
	}
	wesql::free_result($request);

	// Load the member data.
	if (!empty($members))
	{
		// Get some additional member info...
		$request = wesql::query('
			SELECT id_member, member_name, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:member_list})
			LIMIT ' . count($members),
			array(
				'member_list' => $members,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$members[$row['id_member']] = $row;
		wesql::free_result($request);

		// This is a guest...
		$members[0] = array(
			'id_member' => 0,
			'member_name' => '',
			'real_name' => $txt['guest_title']
		);

		// Go through each error and tack the data on.
		foreach ($context['errors'] as $id => $dummy)
		{
			$memID = $context['errors'][$id]['member']['id'];
			if (empty($members[$memID]) || !is_array($members[$memID]))
				$memID = 0; // If they're not currently a member, they're a guest...

			$context['errors'][$id]['member']['username'] = $members[$memID]['member_name'];
			$context['errors'][$id]['member']['name'] = $members[$memID]['real_name'];
			$context['errors'][$id]['member']['href'] = empty($memID) ? '' : '<URL>?action=profile;u=' . $memID;
			$context['errors'][$id]['member']['link'] = empty($memID) ? $txt['guest_title'] : '<a href="<URL>?action=profile;u=' . $memID . '">' . $context['errors'][$id]['member']['name'] . '</a>';
		}
	}

	// Filtering anything?
	if (isset($filter))
	{
		$context['filter'] =& $filter;

		// Set the filtering context.
		if ($filter['variable'] == 'id_member')
		{
			$id = $filter['value']['sql'];
			loadMemberData($id, false, 'minimal');
			$context['filter']['value']['html'] = '<a href="<URL>?action=profile;u=' . $id . '">' . $user_profile[$id]['real_name'] . '</a>';
		}
		elseif ($filter['variable'] == 'request_uri')
			$context['filter']['value']['html'] = '&quot;' . strtr(westr::safe((substr($filter['value']['sql'], 0, 1) == '?' ? SCRIPT : '') . $filter['value']['sql']), array('\_' => '_')) . '&quot;';
		elseif ($filter['variable'] == 'error_type')
			$context['filter']['value']['html'] = '&quot;' . (isset($txt['behav_' . $_GET['value'] . '_log']) ? $txt['behav_' . $_GET['value'] . '_log'] : $_GET['value']) . '&quot;';
		elseif ($filter['variable'] == 'ip')
			$context['filter']['value']['html'] = $context['filtering_ip']; // We already stored this earlier!
		else
			$context['filter']['value']['html'] =& $filter['value']['sql'];
	}

	$context['error_types'] = array();

	$context['error_types']['all'] = array(
		'label' => $txt['errortype_all'],
		'url' => '<URL>?action=admin;area=logs;sa=intrusionlog' . ($context['sort_direction'] == 'up' ? ';asc' : ''),
		'is_selected' => empty($filter),
	);

	// What type of errors do we have and how many do we have?
	$sum = 0;
	$request = wesql::query('
		SELECT error_type, COUNT(*) AS num_errors
		FROM {db_prefix}log_intrusion
		GROUP BY error_type
		ORDER BY error_type = {literal:critical} DESC, error_type ASC'
	);
	while ($row = wesql::fetch_assoc($request))
	{
		// Total errors so far?
		$sum += $row['num_errors'];

		$context['error_types'][$sum] = array(
			'label' => (isset($txt['behav_' . $row['error_type'] . '_log']) ? $txt['behav_' . $row['error_type'] . '_log'] : $row['error_type']) . ' (' . $row['num_errors'] . ')',
			'url' => '<URL>?action=admin;area=logs;sa=intrusionlog' . ($context['sort_direction'] == 'up' ? ';asc' : '') . ';filter=error_type;value=' . $row['error_type'],
			'is_selected' => isset($filter) && $filter['value']['sql'] == wesql::escape_wildcard_string($row['error_type']),
		);
	}
	wesql::free_result($request);

	// Update the all errors tab with the total number of errors
	$context['error_types']['all']['label'] .= ' (' . $sum . ')';

	// And this is pretty basic ;)
	$context['page_title'] = $txt['log_intrusion'];
	$context['has_filter'] = isset($filter);
	wetem::load('intrusion_log');

	// Don't rewrite any URLs, we need them to remain exact!
	$settings['pretty_filters'] = array();
	$settings['pretty_enable_filters'] = false;
}

function deleteIntrusions()
{
	global $filter;

	// Make sure the session exists and is correct; otherwise, might be a hacker.
	checkSession();

	// Delete all or just some?
	if (isset($_POST['delall']) && !isset($filter))
		wesql::query('
			TRUNCATE {db_prefix}log_intrusion
		');
	// Deleting all with a filter?
	elseif (isset($_POST['delall'], $filter))
		wesql::query('
			DELETE FROM {db_prefix}log_intrusion
			WHERE ' . $filter['variable'] . ' LIKE {string:filter}',
			array(
				'filter' => $filter['value']['sql'],
			)
		);
	// Just specific errors?
	elseif (!empty($_POST['delete']))
	{
		wesql::query('
			DELETE FROM {db_prefix}log_intrusion
			WHERE id_event IN ({array_int:error_list})',
			array(
				'error_list' => array_unique($_POST['delete']),
			)
		);
		updateErrorCount();

		// Go back to where we were.
		redirectexit('action=admin;area=logs;sa=intrusionlog' . (isset($_REQUEST['asc']) ? ';asc' : '') . ';start=' . $_GET['start'] . (isset($filter) ? ';filter=' . $_GET['filter'] . ';value=' . $_GET['value'] : ''));
	}
	updateErrorCount();

	// Back to the intrusion log!
	redirectexit('action=admin;area=logs;sa=intrusionlog' . (isset($_REQUEST['asc']) ? ';asc' : ''));
}

function updateErrorCount($count = 0)
{
	if (empty($count))
	{
		$request = wesql::query('
			SELECT COUNT(id_error) AS errors
			FROM {db_prefix}log_errors',
			array()
		);
		list ($count) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	updateSettings(array('app_error_count' => $count));
}

function ViewFile()
{
	global $context, $txt;

	loadTemplate('GenericPopup');
	loadLanguage('Help');
	wetem::hide();
	wetem::load('popup');

	// Decode the file and get the line
	$file = realpath(base64_decode($_REQUEST['file']));
	$line = isset($_REQUEST['line']) ? (int) $_REQUEST['line'] : 0;
	$basename = strtolower(basename($file));

	// Make sure the file we are looking for is one they are allowed to look at
	if (strrchr($basename, '.') != '.php' || $basename == 'settings.php' || $basename == 'settings_bak.php' || !strhas($file, array(realpath(ROOT_DIR), realpath(APP_DIR), realpath(CACHE_DIR . '/php'))) || !is_readable($file))
		fatal_lang_error('error_bad_file', true, array(htmlspecialchars(base64_decode($_REQUEST['file']))));

	// Get the min and max lines
	$min = max(1, $line - 12);
	$max = $line + 13;

	if ($max <= 0 || $min >= $max)
		fatal_lang_error('error_bad_line');

	$file_data = explode('<br />', highlight_php_code(htmlspecialchars(implode('', file($file)))));

	// We don't want to slice off too many so let's make sure we stop at the last one
	$max = min($max, max(array_keys($file_data)));

	$file_data = array_slice($file_data, $min - 1, $max - $min);

	$context['page_title'] = $_POST['t'] = strtr($file, array('"' => '\\"'));

	// The file URL will be inserted into the <header>.
	$context['popup_contents'] = '
		<table id="fileviewer" class="w100 cp0 cs0">';

	$alt = '';
	foreach ($file_data as $index => $body)
	{
		$alt = $alt ? '' : '2';
		$line_num = $index + $min;
		$is_target = $line_num == $line;
		$context['popup_contents'] .= '
			<tr class="windowbg' . $alt . '">
				<td class="nowrap right' . ($is_target ? ' tar1">==&gt;' : '">') . $line_num . ':</td>
				<td class="nowrap left' . ($is_target ? ' tar2">' : '">') . $body . '</td>
			</tr>';
	}

	$context['popup_contents'] .= '
		</table>';

	if (empty($file_data))
	{
		loadLanguage('ManageMaintenance');
		$context['popup_contents'] = $txt['file_out_of_bounds'];
	}
}

function handleTemplateErrors($filename)
{
	global $txt, $context, $maintenance, $mtitle, $mmessage;

	clean_output();

	// Don't cache error pages!!
	header('Expires: Wed, 25 Aug 2010 17:00:00 GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-cache');

	if (!isset($txt['template_parse_error']))
		loadLanguage('Errors', '', false);

	if (!isset($txt['template_parse_error']))
	{
		$txt['template_parse_error'] = 'Template Parse Error!';
		$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system. This problem should only be temporary, so please come back later and try again. If you continue to see this message, please contact the administrator.<br><br>You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
		$txt['template_parse_error_details'] = 'There was a problem loading the <tt><strong>%1$s</strong></tt> template or language file. Please check the syntax and try again - remember, single quotes (<tt>\'</tt>) often have to be escaped with a slash (<tt>\\</tt>). To see more specific error information from PHP, try <a href="{board_url}%1$s" class="extern">accessing the file directly</a>.<br><br>You may want to try to <a href="javascript:location.reload();">refresh this page</a>.';
	}

	$txt['template_parse_error_details'] = str_replace('{board_url}', ROOT, $txt['template_parse_error_details']);

	// First, let's get the doctype and language information out of the way.
	echo '<!DOCTYPE html>
<html', !empty($context['right_to_left']) ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="utf-8">';

	if (!empty($maintenance) && !allowedTo('admin_forum'))
		echo '
		<title>', $mtitle, '</title>
	</head>
	<body>
		<h3>', $mtitle, '</h3>
		', $mmessage, '
	</body>
</html>';
	elseif (!allowedTo('admin_forum'))
		echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', $txt['template_parse_error_message'], '
	</body>
</html>';
	else
	{
		loadSource('Class-WebGet');
		$weget = new weget(str_replace(ROOT_DIR, ROOT, $filename));
		$error = $weget->get();

		if (empty($error))
			$error = isset($php_errormsg) ? $php_errormsg : '';
		elseif (strpos(strtolower($error), '<h2>error 403</h2>') !== false)
			$error = isset($php_errormsg) ? strtr($php_errormsg, array('<b>' => '<strong>', '</b>' => '</strong>')) : '(Please restore .htaccess file to core/html!)';

		$error = strtr($error, array('<b>' => '<strong>', '</b>' => '</strong>'));

		echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', sprintf($txt['template_parse_error_details'], str_replace(ROOT_DIR, '', $filename));

		if (!empty($error))
			echo '
		<hr>
		<div style="margin: 0 20px"><tt>', str_replace('<strong>' . ROOT_DIR, '<strong>...', $error), '</tt></div>';

		// Show the error in the context of its surrounding source code.
		if (preg_match('~ <strong>(\d+)</strong><br\s*/?\>$~i', $error, $match) != 0)
		{
			$data = file($filename);
			$data2 = highlight_php_code(implode('', $data));
			$data2 = preg_split('~\<br\s*/?\>~', $data2);

			// Fix the PHP code stuff...
			$data2 = str_replace('<span class="bbc_pre">' . "\t" . '</span>', "\t", $data2);

			// Now we get to work around a bug in PHP where it doesn't escape <br>s!
			$j = -1;
			foreach ($data as $line)
			{
				$j++;

				if (strpos($line, '<br>') === false)
					continue;

				$n = substr_count($line, '<br>');
				for ($i = 0; $i < $n; $i++)
				{
					$data2[$j] .= '&lt;br&gt;' . $data2[$j + $i + 1];
					unset($data2[$j + $i + 1]);
				}
				$j += $n;
			}
			$data2 = array_values($data2);
			array_unshift($data2, '');

			echo '
		<div style="margin: 2ex 20px"><div style="width: 100%; overflow: auto"><pre style="margin: 0">';

			// Figure out what the color coding was before...
			$line = max($match[1] - 9, 1);
			$last_line = '';
			for ($line2 = $line - 1; $line2 > 1; $line2--)
				if (strpos($data2[$line2], '<') !== false)
				{
					if (preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line2], $color_match) != 0)
						$last_line = $color_match[1];
					break;
				}

			// Show the relevant lines...
			for ($n = min($match[1] + 4, count($data2) + 1); $line <= $n; $line++)
			{
				if ($line == $match[1])
					echo '</pre><div style="display: inline-block; background-color: #ffb0b5"><pre style="margin: 0">';

				echo '<span style="color: black">', sprintf('%' . strlen($n) . 's', $line), ':</span> ';
				if (isset($data2[$line]) && $data2[$line] != '')
					echo substr($data2[$line], 0, 2) == '</' ? preg_replace('~^</[^>]+>~', '', $data2[$line]) : $last_line . $data2[$line];

				if (isset($data2[$line]) && preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line], $color_match) != 0)
				{
					$last_line = $color_match[1];
					echo '</', substr($last_line, 1, 4), '>';
				}
				elseif ($last_line != '' && strpos($data2[$line], '<') !== false)
					$last_line = '';
				elseif ($last_line != '' && $data2[$line] != '')
					echo '</', substr($last_line, 1, 4), '>';

				if ($line == $match[1])
					echo '</pre></div><pre style="margin: 0">';
				else
					echo "\n";
			}

			echo '</pre></div></div>';
		}

		echo '
	</body>
</html>';
	}

	exit;
}
