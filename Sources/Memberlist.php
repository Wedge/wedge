<?php
/**
 * Wedge
 *
 * Handles displaying and searching the member list.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file contains the functions for displaying and searching in the
	members list.  It does so with these functions:

	void MemberList()
		- shows a list of registered members.
		- if a subaction is not specified, lists all registered members.
		- allows searching for members with the 'search' sub action.
		- calls MLAll or MLSearch depending on the sub action.
		- uses the Memberlist template with the main block.
		- requires the view_mlist permission.
		- is accessed via ?action=mlist.

	void MLAll()
		- used to display all members on a page by page basis with sorting.
		- called from MemberList().
		- can be passed a sort parameter, to order the display of members.
		- calls printMemberListRows to retrieve the results of the query.

	void MLSearch()
		- used to search for members or display search results.
		- called by MemberList().
		- if variable 'search' is empty displays search dialog box, using the
		  search block.
		- calls printMemberListRows to retrieve the results of the query.

	void printMemberListRows(resource request)
		- retrieves results of the request passed to it
		- puts results of request into the context for the block.
*/

// Show a listing of the registered members.
function Memberlist()
{
	global $scripturl, $txt, $settings, $context, $theme, $settings;

	// Make sure they can view the memberlist.
	isAllowedTo('view_mlist');

	loadLanguage('ManageMembers');
	loadTemplate('Memberlist');

	$context['listing_by'] = !empty($_GET['sa']) ? $_GET['sa'] : 'all';

	// $subActions array format:
	// 'subaction' => array('label', 'function', 'is_selected')
	$subActions = array(
		'all' => array($txt['view_all_members'], 'MLAll', $context['listing_by'] == 'all'),
		'search' => array($txt['mlist_search'], 'MLSearch', $context['listing_by'] == 'search'),
	);

	// Set up the sort links.
	$context['sort_links'] = array();
	foreach ($subActions as $act => $text)
		$context['sort_links'][] = array(
			'label' => $text[0],
			'action' => $act,
			'selected' => $text[2],
		);

	$context['num_members'] = $settings['totalMembers'];

	// Set up the columns...
	$context['columns'] = array(
		'is_online' => array(
			'label' => $txt['online_status'],
			'width' => '60',
			'class' => 'first_th',
		),
		'real_name' => array(
			'label' => $txt['username']
		),
		'email_address' => array(
			'label' => $txt['email'],
			'width' => '25'
		),
		'website_url' => array(
			'label' => $txt['website'],
			'width' => '70',
			'link_with' => 'website',
		),
		'id_group' => array(
			'label' => $txt['position']
		),
		'registered' => array(
			'label' => $txt['date_registered']
		),
		'posts' => array(
			'label' => $txt['posts'],
			'width' => '115',
			'colspan' => '2',
			'default_sort_rev' => true,
		)
	);

	$context['colspan'] = 0;
	$context['disabled_fields'] = isset($settings['disabled_profile_fields']) ? array_flip(explode(',', $settings['disabled_profile_fields'])) : array();
	foreach ($context['columns'] as $key => $column)
	{
		if (isset($context['disabled_fields'][$key]) || isset($column['link_with'], $context['disabled_fields'][$column['link_with']]))
		{
			unset($context['columns'][$key]);
			continue;
		}

		$context['colspan'] += isset($column['colspan']) ? $column['colspan'] : 1;
	}

	// Are there any custom fields for the memberlist? (Ordering as per the member options code!)
	$context['custom_fields'] = array();
	$request = wesql::query('
		SELECT col_name, field_name
		FROM {db_prefix}custom_fields
		WHERE show_mlist = {int:show_mlist}
		ORDER BY position',
		array(
			'show_mlist' => 1,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		// We get this both for the main column list and for our own reference for later.
		$context['custom_fields'][$row['col_name']] = array(
			'label' => $row['field_name'],
			'no_sort' => true,
		);
		$context['colspan']++;
	}
	if (!empty($context['custom_fields']))
		$context['columns'] = array_insert($context['columns'], 'id_group', $context['custom_fields']);

	// Aesthetic stuff.
	end($context['columns']);
	$context['columns'][key($context['columns'])]['class'] = 'last_th';

	$context['linktree'][] = array(
		'url' => $scripturl . '?action=mlist',
		'name' => $txt['members_list']
	);

	$context['can_send_pm'] = allowedTo('pm_send');

	// Jump to the sub action.
	if (isset($subActions[$context['listing_by']]))
		$subActions[$context['listing_by']][1]();
	else
		$subActions['all'][1]();
}

// List all members, page by page.
function MLAll()
{
	global $txt, $scripturl;
	global $settings, $context;

	// The chunk size for the cached index.
	$cache_step_size = 500;

	// Only use caching if:
	// 1. there are at least 2k members,
	// 2. the default sorting method (real_name) is being used,
	// 3. the page shown is high enough to make a DB filesort unprofitable.
	$use_cache = $settings['totalMembers'] > 2000 && (!isset($_REQUEST['sort']) || $_REQUEST['sort'] === 'real_name') && isset($_REQUEST['start']) && $_REQUEST['start'] > $cache_step_size;

	if ($use_cache)
	{
		// Maybe there's something cached already.
		if (!empty($settings['memberlist_cache']))
			$memberlist_cache = @unserialize($settings['memberlist_cache']);

		// The chunk size for the cached index.
		$cache_step_size = 500;

		// Only update the cache if something changed or no cache existed yet.
		if (empty($memberlist_cache) || empty($settings['memberlist_updated']) || $memberlist_cache['last_update'] < $settings['memberlist_updated'])
		{
			$request = wesql::query('
				SELECT real_name
				FROM {db_prefix}members
				WHERE is_activated = {int:is_activated}
				ORDER BY real_name',
				array(
					'is_activated' => 1,
				)
			);

			$memberlist_cache = array(
				'last_update' => time(),
				'num_members' => wesql::num_rows($request),
				'index' => array(),
			);

			for ($i = 0, $n = wesql::num_rows($request); $i < $n; $i += $cache_step_size)
			{
				wesql::data_seek($request, $i);
				list ($memberlist_cache['index'][$i]) = wesql::fetch_row($request);
			}
			wesql::data_seek($request, $memberlist_cache['num_members'] - 1);
			list ($memberlist_cache['index'][$i]) = wesql::fetch_row($request);
			wesql::free_result($request);

			// Now we've got the cache...store it.
			updateSettings(array('memberlist_cache' => serialize($memberlist_cache)));
		}

		$context['num_members'] = $memberlist_cache['num_members'];
	}

	// Without cache we need an extra query to get the amount of members.
	else
	{
		$request = wesql::query('
			SELECT COUNT(*)
			FROM {db_prefix}members
			WHERE is_activated = {int:is_activated}',
			array(
				'is_activated' => 1,
			)
		);
		list ($context['num_members']) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	// Set defaults for sort (real_name) and start. (0)
	if (!isset($_REQUEST['sort']) || !isset($context['columns'][$_REQUEST['sort']]))
		$_REQUEST['sort'] = 'real_name';

	if (!is_numeric($_REQUEST['start']))
	{
		if (preg_match('~^[^\'\\\\/]~u', westr::strtolower($_REQUEST['start']), $match) === 0)
			fatal_lang_error('name_invalid_character', false);

		$_REQUEST['start'] = $match[0];

		$request = wesql::query('
			SELECT COUNT(*)
			FROM {db_prefix}members
			WHERE LOWER(SUBSTRING(real_name, 1, 1)) < {string:first_letter}
				AND is_activated = {int:is_activated}',
			array(
				'is_activated' => 1,
				'first_letter' => $_REQUEST['start'],
			)
		);
		list ($_REQUEST['start']) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	$context['letter_links'] = '';
	for ($i = 97; $i < 123; $i++)
		$context['letter_links'] .= '<a href="' . $scripturl . '?action=mlist;sa=all;start=' . chr($i) . '#letter' . chr($i) . '">' . strtoupper(chr($i)) . '</a> ';

	// Sort out the column information.
	foreach ($context['columns'] as $col => $column_details)
	{
		if (!empty($column_details['no_sort']))
		{
			$context['columns'][$col]['href'] = '';
			$context['columns'][$col]['link'] = $column_details['label'];
			$context['columns'][$col]['selected'] = false;
			continue;
		}

		$context['columns'][$col]['href'] = $scripturl . '?action=mlist;sort=' . $col . ';start=0';

		if ((!isset($_REQUEST['desc']) && $col == $_REQUEST['sort']) || ($col != $_REQUEST['sort'] && !empty($column_details['default_sort_rev'])))
			$context['columns'][$col]['href'] .= ';desc';

		$context['columns'][$col]['link'] = '<a href="' . $context['columns'][$col]['href'] . '" rel="nofollow">' . $context['columns'][$col]['label'] . '</a>';
		$context['columns'][$col]['selected'] = $_REQUEST['sort'] == $col;
	}

	$context['sort_by'] = $_REQUEST['sort'];
	$context['sort_direction'] = !isset($_REQUEST['desc']) ? 'up' : 'down';

	// Construct the page index.
	$context['page_index'] = template_page_index($scripturl . '?action=mlist;sort=' . $_REQUEST['sort'] . (isset($_REQUEST['desc']) ? ';desc' : ''), $_REQUEST['start'], $context['num_members'], $settings['defaultMaxMembers']);

	// Send the data to the template.
	$context['start'] = $_REQUEST['start'] + 1;
	$context['end'] = min($_REQUEST['start'] + $settings['defaultMaxMembers'], $context['num_members']);

	$context['can_moderate_forum'] = allowedTo('moderate_forum');
	$context['page_title'] = sprintf($txt['viewing_members'], $context['start'], $context['end']);
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=mlist;sort=' . $_REQUEST['sort'] . ';start=' . $_REQUEST['start'],
		'name' => &$context['page_title'],
		'extra_after' => ' (' . sprintf($txt['of_total_members'], $context['num_members']) . ')'
	);

	// List out the different sorting methods...
	$sort_methods = array(
		'is_online' => array(
			'down' => allowedTo('moderate_forum') ? 'IFNULL(lo.log_time, 1) ASC, real_name ASC' : 'IF(mem.show_online, IFNULL(lo.log_time, 1), 1) ASC, real_name ASC',
			'up' => allowedTo('moderate_forum') ? 'IFNULL(lo.log_time, 1) DESC, real_name DESC' : 'IF(mem.show_online, IFNULL(lo.log_time, 1), 1) DESC, real_name DESC'
		),
		'real_name' => array(
			'down' => 'mem.real_name DESC',
			'up' => 'mem.real_name ASC'
		),
		'email_address' => array(
			'down' => allowedTo('moderate_forum') ? 'mem.email_address DESC' : 'mem.hide_email DESC, mem.email_address DESC',
			'up' => allowedTo('moderate_forum') ? 'mem.email_address ASC' : 'mem.hide_email ASC, mem.email_address ASC'
		),
		'website_url' => array(
			'down' => 'LENGTH(mem.website_url) > 0 ASC, IFNULL(mem.website_url, 1=1) DESC, mem.website_url DESC',
			'up' => 'LENGTH(mem.website_url) > 0 DESC, IFNULL(mem.website_url, 1=1) ASC, mem.website_url ASC'
		),
		'registered' => array(
			'down' => 'mem.date_registered DESC',
			'up' => 'mem.date_registered ASC'
		),
		'id_group' => array(
			'down' => 'IFNULL(mg.group_name, 1=1) DESC, mg.group_name DESC',
			'up' => 'IFNULL(mg.group_name, 1=1) ASC, mg.group_name ASC'
		),
		'posts' => array(
			'down' => 'mem.posts DESC',
			'up' => 'mem.posts ASC'
		)
	);

	$limit = $_REQUEST['start'];
	$query_parameters = array(
		'regular_id_group' => 0,
		'is_activated' => 1,
		'sort' => $sort_methods[$_REQUEST['sort']][$context['sort_direction']],
	);

	// Using cache allows to narrow down the list to be retrieved.
	if ($use_cache && $_REQUEST['sort'] === 'real_name' && !isset($_REQUEST['desc']))
	{
		$first_offset = $_REQUEST['start'] - ($_REQUEST['start'] % $cache_step_size);
		$second_offset = ceil(($_REQUEST['start'] + $settings['defaultMaxMembers']) / $cache_step_size) * $cache_step_size;

		$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
		$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
		$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
		$limit -= $first_offset;
	}

	// Reverse sorting is a bit more complicated...
	elseif ($use_cache && $_REQUEST['sort'] === 'real_name')
	{
		$first_offset = floor(($memberlist_cache['num_members'] - $settings['defaultMaxMembers'] - $_REQUEST['start']) / $cache_step_size) * $cache_step_size;
		if ($first_offset < 0)
			$first_offset = 0;
		$second_offset = ceil(($memberlist_cache['num_members'] - $_REQUEST['start']) / $cache_step_size) * $cache_step_size;

		$where = 'mem.real_name BETWEEN {string:real_name_low} AND {string:real_name_high}';
		$query_parameters['real_name_low'] = $memberlist_cache['index'][$first_offset];
		$query_parameters['real_name_high'] = $memberlist_cache['index'][$second_offset];
		$limit = $second_offset - ($memberlist_cache['num_members'] - $_REQUEST['start']) - ($second_offset > $memberlist_cache['num_members'] ? $cache_step_size - ($memberlist_cache['num_members'] % $cache_step_size) : 0);
	}

	// Select the members from the database.
	$request = wesql::query('
		SELECT mem.id_member
		FROM {db_prefix}members AS mem' . ($_REQUEST['sort'] === 'is_online' ? '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)' : '') . ($_REQUEST['sort'] === 'id_group' ? '
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)' : '') . '
		WHERE mem.is_activated = {int:is_activated}' . (empty($where) ? '' : '
			AND ' . $where) . '
		ORDER BY {raw:sort}
		LIMIT ' . $limit . ', ' . $settings['defaultMaxMembers'],
		$query_parameters
	);
	printMemberListRows($request);
	wesql::free_result($request);

	// Add anchors at the start of each letter.
	if ($_REQUEST['sort'] == 'real_name')
	{
		$last_letter = '';
		foreach ($context['members'] as $i => $dummy)
		{
			$this_letter = westr::strtolower(westr::substr($context['members'][$i]['name'], 0, 1));

			if ($this_letter != $last_letter && preg_match('~[a-z]~', $this_letter) === 1)
			{
				$context['members'][$i]['sort_letter'] = htmlspecialchars($this_letter);
				$last_letter = $this_letter;
			}
		}
	}
}

// Search for members...
function MLSearch()
{
	global $txt, $scripturl, $context, $settings;

	$context['page_title'] = $txt['mlist_search'];
	$context['can_moderate_forum'] = allowedTo('moderate_forum');

	// Can they search custom fields?
	$request = wesql::query('
		SELECT col_name, field_name, field_desc
		FROM {db_prefix}custom_fields
		WHERE active = {int:active}
			' . (allowedTo('admin_forum') ? '' : ' AND private < {int:private_level}') . '
			AND can_search = {int:can_search}
			AND (field_type = {literal:text} OR field_type = {literal:textarea})',
		array(
			'active' => 1,
			'can_search' => 1,
			'private_level' => 2,
		)
	);
	$context['custom_search_fields'] = array();
	while ($row = wesql::fetch_assoc($request))
		$context['custom_search_fields'][$row['col_name']] = array(
			'colname' => $row['col_name'],
			'name' => $row['field_name'],
			'desc' => $row['field_desc'],
		);
	wesql::free_result($request);

	// They're searching..
	if (isset($_REQUEST['search'], $_REQUEST['fields']))
	{
		$_POST['search'] = trim(isset($_GET['search']) ? $_GET['search'] : $_POST['search']);
		$_POST['fields'] = isset($_GET['fields']) ? explode(',', $_GET['fields']) : $_POST['fields'];

		$context['old_search'] = $_REQUEST['search'];
		$context['old_search_value'] = urlencode($_REQUEST['search']);

		// No fields?  Use default...
		if (empty($_POST['fields']))
			$_POST['fields'] = array('name');

		$query_parameters = array(
			'regular_id_group' => 0,
			'is_activated' => 1,
			'blank_string' => '',
			'search' => '%' . strtr(westr::htmlspecialchars($_POST['search'], ENT_QUOTES), array('_' => '\\_', '%' => '\\%', '*' => '%')) . '%',
		);

		// Search for a name?
		if (in_array('name', $_POST['fields']))
			$fields = array('member_name', 'real_name');
		else
			$fields = array();

		// Search for websites.
		if (in_array('website', $_POST['fields']))
			$fields += array(3 => 'website_title', 'website_url');
		// Search for groups.
		if (in_array('group', $_POST['fields']))
			$fields += array(5 => 'IFNULL(group_name, {string:blank_string})');
		// Search for an email address?
		if (in_array('email', $_POST['fields']))
		{
			$fields += array(2 => allowedTo('moderate_forum') ? 'email_address' : '(hide_email = 0 AND email_address');
			$condition = allowedTo('moderate_forum') ? '' : ')';
		}
		else
			$condition = '';

		$customJoin = array();
		$customCount = 10;
		// Any custom fields to search for - these being tricky?
		foreach ($_POST['fields'] as $field)
		{
			$curField = substr($field, 5);
			if (substr($field, 0, 5) == 'cust_' && isset($context['custom_search_fields'][$curField]))
			{
				$customJoin[] = 'LEFT JOIN {db_prefix}themes AS t' . $curField . ' ON (t' . $curField . '.variable = {string:t' . $curField . '} AND t' . $curField . '.id_theme = 1 AND t' . $curField . '.id_member = mem.id_member)';
				$query_parameters['t' . $curField] = $curField;
				$fields += array($customCount++ => 'IFNULL(t' . $curField . '.value, {string:blank_string})');
			}
		}

		$query = $_POST['search'] == '' ? '= {string:blank_string}' : 'LIKE {string:search}';

		$request = wesql::query('
			SELECT COUNT(*)
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)' .
				(empty($customJoin) ? '' : implode('
				', $customJoin)) . '
			WHERE (' . implode(' ' . $query . ' OR ', $fields) . ' ' . $query . $condition . ')
				AND mem.is_activated = {int:is_activated}',
			$query_parameters
		);
		list ($numResults) = wesql::fetch_row($request);
		wesql::free_result($request);

		$context['page_index'] = template_page_index($scripturl . '?action=mlist;sa=search;search=' . $_POST['search'] . ';fields=' . implode(',', $_POST['fields']), $_REQUEST['start'], $numResults, $settings['defaultMaxMembers']);

		// Find the members from the database.
		// !!! SLOW This query is slow.
		$request = wesql::query('
			SELECT mem.id_member
			FROM {db_prefix}members AS mem
				LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
				LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = CASE WHEN mem.id_group = {int:regular_id_group} THEN mem.id_post_group ELSE mem.id_group END)' .
				(empty($customJoin) ? '' : implode('
				', $customJoin)) . '
			WHERE (' . implode(' ' . $query . ' OR ', $fields) . ' ' . $query . $condition . ')
				AND mem.is_activated = {int:is_activated}
			LIMIT ' . $_REQUEST['start'] . ', ' . $settings['defaultMaxMembers'],
			$query_parameters
		);
		printMemberListRows($request);
		wesql::free_result($request);
	}
	else
	{
		// These are all the possible fields.
		$context['search_fields'] = array(
			'name' => $txt['mlist_search_name'],
			'email' => $txt['mlist_search_email'],
			'website' => $txt['mlist_search_website'],
			'group' => $txt['mlist_search_group'],
		);

		foreach ($context['custom_search_fields'] as $field)
			$context['search_fields']['cust_' . $field['colname']] = sprintf($txt['mlist_search_by'], $field['name']);

		// What do we search for by default?
		$context['search_defaults'] = array('name', 'email');

		wetem::load('search');
		$context['old_search'] = isset($_GET['search']) ? $_GET['search'] : (isset($_POST['search']) ? htmlspecialchars($_POST['search']) : '');
	}

	$context['linktree'][] = array(
		'url' => $scripturl . '?action=mlist;sa=search',
		'name' => &$context['page_title']
	);
}

function printMemberListRows($request)
{
	global $scripturl, $txt, $settings;
	global $context, $theme, $memberContext;

	// Get the most posts.
	$result = wesql::query('
		SELECT MAX(posts)
		FROM {db_prefix}members',
		array(
		)
	);
	list ($most_posts) = wesql::fetch_row($result);
	wesql::free_result($result);

	// Avoid division by zero...
	if ($most_posts == 0)
		$most_posts = 1;

	$members = array();
	while ($row = wesql::fetch_assoc($request))
		$members[] = $row['id_member'];

	// Load all the members for display.
	loadMemberData($members);

	$context['members'] = array();
	foreach ($members as $member)
	{
		if (!loadMemberContext($member, true))
			continue;

		// We need to do some work on the custom fields, because we need to be selective about it.
		$context['members'][$member] = $memberContext[$member];
		if (!empty($context['members'][$member]['custom_fields']))
			foreach ($context['members'][$member]['custom_fields'] as $field)
				$context['members'][$member]['mlist_cf'][$field['colname']] = $field['value'];

		$context['members'][$member]['post_percent'] = round(($context['members'][$member]['real_posts'] * 100) / $most_posts);
		$context['members'][$member]['registered_date'] = strftime('%Y-%m-%d', $context['members'][$member]['registered_timestamp']);
	}
}
