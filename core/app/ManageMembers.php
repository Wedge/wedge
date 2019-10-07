<?php
/**
 * Displays and searches the memberlist within the administration panel, which includes banned members and more search options.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	Show a list of members or a selection of members.

	void ViewMembers()
		- the main entrance point for the Manage Members screen.
		- called by ?action=admin;area=viewmembers.
		- requires the moderate_forum permission.
		- loads the ManageMembers template and ManageMembers language file.
		- calls a function based on the given sub-action.

	void ViewMemberlist()
		- shows a list of members.
		- called by ?action=admin;area=viewmembers;sa=all or ?action=admin;area=viewmembers;sa=query.
		- requires the moderate_forum permission.
		- uses the view_members block of the ManageMembers template.
		- allows sorting on several columns.
		- handles deletion of selected members.
		- handles the search query sent by ?action=admin;area=viewmembers;sa=search.

	void SearchMembers()
		- search the member list, using one or more criteria.
		- called by ?action=admin;area=viewmembers;sa=search.
		- requires the moderate_forum permission.
		- uses the search_members block of the ManageMembers template.
		- form is submitted to action=admin;area=viewmembers;sa=query.

	void MembersAwaitingActivation()
		- show a list of members awaiting approval or activation.
		- called by ?action=admin;area=viewmembers;sa=browse;type=approve or
		  ?action=admin;area=viewmembers;sa=browse;type=activate.
		- requires the moderate_forum permission.
		- uses the admin_browse block of the ManageMembers template.
		- allows instant approval or activation of (a selection of) members.
		- list can be sorted on different columns.
		- form submits to ?action=admin;area=viewmembers;sa=approve.

	void AdminApprove()
		- handles the approval, rejection, activation or deletion of members.
		- called by ?action=admin;area=viewmembers;sa=approve.
		- requires the moderate_forum permission.
		- redirects to ?action=admin;area=viewmembers;sa=browse with the same parameters
		  as the calling page.

	int jeffsdatediff(int old)
		- nifty function to calculate the number of days ago a given date was.
		- requires a unix timestamp as input, returns an integer.
		- in honour of Jeff Lewis, the original creator of...this function.
		- the returned number of days is based on the forum time.
*/

function ViewMembers()
{
	global $txt, $context, $settings;

	$subActions = array(
		'all' => array('ViewMemberlist', 'moderate_forum'),
		'approve' => array('AdminApprove', 'moderate_forum'),
		'browse' => array('MembersAwaitingActivation', 'moderate_forum'),
		'search' => array('SearchMembers', 'moderate_forum'),
		'query' => array('ViewMemberlist', 'moderate_forum'),
	);

	// Default to sub action 'index' or 'settings' depending on permissions.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'all';

	// We know the sub action, now we know what you're allowed to do.
	isAllowedTo($subActions[$_REQUEST['sa']][1]);

	// Load the essentials.
	loadLanguage('ManageMembers');
	loadTemplate('ManageMembers');

	// Get counts on every type of activation - for sections and filtering alike.
	$request = wesql::query('
		SELECT COUNT(*) AS total_members, is_activated
		FROM {db_prefix}members
		WHERE is_activated != {int:is_activated}
		GROUP BY is_activated',
		array(
			'is_activated' => 1,
		)
	);
	$context['activation_numbers'] = array();
	$context['awaiting_activation'] = 0;
	$context['awaiting_approval'] = 0;
	while ($row = wesql::fetch_assoc($request))
		$context['activation_numbers'][$row['is_activated']] = $row['total_members'];
	wesql::free_result($request);

	foreach ($context['activation_numbers'] as $activation_type => $total_members)
	{
		if (in_array($activation_type, array(0, 2)))
			$context['awaiting_activation'] += $total_members;
		elseif (in_array($activation_type, array(3, 4, 5)))
			$context['awaiting_approval'] += $total_members;
	}

	// For the page header... do we show activation?
	$context['show_activate'] = (!empty($settings['registration_method']) && ($settings['registration_method'] == 1 || $settings['registration_method'] == 4)) || !empty($context['awaiting_activation']);

	// What about approval?
	$context['show_approve'] = (!empty($settings['registration_method']) && ($settings['registration_method'] == 2 || $settings['registration_method'] == 4)) || !empty($context['awaiting_approval']) || !empty($settings['approveAccountDeletion']);

	// We have to override what the menu code thinks is the right tab - some of these are not defined in the master admin menu.
	$context[$context['admin_menu_name']]['current_subsection'] = $_REQUEST['sa'];
	// Setup the admin tabs.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['admin_members'],
		'help' => 'view_members',
		'description' => $txt['admin_members_list'],
		'tabs' => array(
			'all' => array(
				'label' => $txt['view_all_members'],
				'description' => $txt['admin_members_list'],
				'url' => '<URL>?action=admin;area=viewmembers;sa=all',
				'is_selected' => $_REQUEST['sa'] == 'all',
			),
			'search' => array(
				'label' => $txt['mlist_search'],
				'description' => $txt['admin_members_list'],
				'url' => '<URL>?action=admin;area=viewmembers;sa=search',
				'is_selected' => $_REQUEST['sa'] == 'search' || $_REQUEST['sa'] == 'query',
			),
			'approve' => array(
				'label' => $txt['admin_members_approve'] . (!empty($context['awaiting_approval']) ? '<span class="note">' . $context['awaiting_approval'] . '</span>' : ''),
				'description' => $txt['admin_browse_approve_desc'],
				'url' => '<URL>?action=admin;area=viewmembers;sa=browse;type=approve',
				'is_selected' => $_REQUEST['sa'] == 'browse' && !empty($_REQUEST['type']) && $_REQUEST['type'] == 'approve',
				'disabled' => !$context['show_approve'],
			),
			'activate' => array(
				'label' => $txt['admin_members_activate'] . (!empty($context['awaiting_activation']) ? '<span class="note">' . $context['awaiting_activation'] . '</span>' : ''),
				'description' => $txt['admin_browse_activate_desc'],
				'url' => '<URL>?action=admin;area=viewmembers;sa=browse;type=activate',
				'is_selected' => $_REQUEST['sa'] == 'browse' && !empty($_REQUEST['type']) && $_REQUEST['type'] == 'activate',
				'disabled' => !$context['show_activate'],
			),
		),
	);

	$subActions[$_REQUEST['sa']][0]();
}

// View all members.
function ViewMemberlist()
{
	global $txt, $context, $settings;

	// Set the current sub action.
	$context['sub_action'] = $_REQUEST['sa'];
	$context['can_see_ip'] = allowedTo('manage_bans');

	// Are we performing a delete?
	if (isset($_POST['delete_members']) && !empty($_POST['delete']) && allowedTo('profile_remove_any'))
	{
		checkSession();

		// Clean the input.
		foreach ($_POST['delete'] as $key => $value)
		{
			$_POST['delete'][$key] = (int) $value;
			// Don't delete yourself, idiot.
			if ($value == MID)
				unset($_POST['delete'][$key]);
		}

		if (!empty($_POST['delete']))
		{
			// Delete all the selected members.
			loadSource('Subs-Members');
			deleteMembers($_POST['delete'], true);
		}
	}

	if ($context['sub_action'] == 'query' && !empty($_REQUEST['params']) && empty($_POST))
		$_POST += @unserialize(base64_decode($_REQUEST['params']));

	// Check input after a member search has been submitted.
	if ($context['sub_action'] == 'query')
	{
		// Retrieving the membergroups and postgroups.
		$context['membergroups'] = array(
			array(
				'id' => 0,
				'name' => $txt['membergroups_members'],
				'can_be_additional' => false
			)
		);
		$context['postgroups'] = array();

		$request = wesql::query('
			SELECT id_group, group_name, min_posts
			FROM {db_prefix}membergroups
			WHERE id_group != {int:moderator_group}
			ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
			array(
				'moderator_group' => 3,
				'newbie_group' => 4,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if ($row['min_posts'] == -1)
				$context['membergroups'][] = array(
					'id' => $row['id_group'],
					'name' => $row['group_name'],
					'can_be_additional' => true
				);
			else
				$context['postgroups'][] = array(
					'id' => $row['id_group'],
					'name' => $row['group_name']
				);
		}
		wesql::free_result($request);

		// Some data about the form fields and how they are linked to the database.
		$params = array(
			'mem_id' => array(
				'db_fields' => array('id_member'),
				'type' => 'int',
				'range' => true
			),
			'age' => array(
				'db_fields' => array('birthdate'),
				'type' => 'age',
				'range' => true
			),
			'posts' => array(
				'db_fields' => array('posts'),
				'type' => 'int',
				'range' => true
			),
			'reg_date' => array(
				'db_fields' => array('date_registered'),
				'type' => 'date',
				'range' => true
			),
			'last_online' => array(
				'db_fields' => array('last_login'),
				'type' => 'date',
				'range' => true
			),
			'gender' => array(
				'db_fields' => array('gender'),
				'type' => 'checkbox',
				'values' => array('0', '1', '2'),
			),
			'activated' => array(
				'db_fields' => array('CASE WHEN is_activated IN (1, 11, 21) THEN 1 ELSE 0 END'),
				'type' => 'checkbox',
				'values' => array('0', '1'),
			),
			'membername' => array(
				'db_fields' => array('member_name', 'real_name'),
				'type' => 'string'
			),
			'email' => array(
				'db_fields' => array('email_address'),
				'type' => 'string'
			),
			'website' => array(
				'db_fields' => array('website_title', 'website_url'),
				'type' => 'string'
			),
			'location' => array(
				'db_fields' => array('location'),
				'type' => 'string'
			),
			'ip' => array(
				'db_fields' => array('member_ip'),
				'type' => 'string'
			),
		);
		$range_trans = array(
			'--' => '<',
			'-' => '<=',
			'=' => '=',
			'+' => '>=',
			'++' => '>'
		);

		// Can they see all IP addresses? If not, they shouldn't see any.
		if (!allowedTo('manage_bans'))
			unset($params['ip']);
		elseif (isset($_POST['ip']) && $_POST['ip'] !== '')
		{
			$_POST['ip'] = expand_ip($_POST['ip']);
			if ($_POST['ip'] == INVALID_IP)
				unset($_POST['ip']);
		}

		// !!! Validate a little more.
		// Loop through every field of the form.
		$query_parts = array();
		$where_params = array();
		foreach ($params as $param_name => $param_info)
		{
			// Not filled in?
			if (!isset($_POST[$param_name]) || $_POST[$param_name] === '')
				continue;

			// Make sure numeric values are really numeric.
			if ($param_info['type'] == 'int' || $param_info['type'] == 'age')
				$_POST[$param_name] = (int) $_POST[$param_name];
			// Date values have to match the specified format.
			elseif ($param_info['type'] == 'date')
			{
				// Check if this date format is valid. It might be an int, or it might be a date. If it's an int, leave it as is, otherwise convert to an int.
				if ($_POST[$param_name] != (string) (int) $_POST[$param_name])
				{
					if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $_POST[$param_name]) == 0)
						continue;

					$_POST[$param_name] = strtotime($_POST[$param_name]);
				}
			}

			// Those values that are in some kind of range (<, <=, =, >=, >).
			if (!empty($param_info['range']))
			{
				// Default to '=', just in case...
				if (empty($range_trans[$_POST['types'][$param_name]]))
					$_POST['types'][$param_name] = '=';

				// Handle special case 'age'.
				if ($param_info['type'] == 'age')
				{
					// All people that were born between $lowerlimit and $upperlimit are currently the specified age.
					$datearray = getdate(forum_time());
					$upperlimit = sprintf('%04d-%02d-%02d', $datearray['year'] - $_POST[$param_name], $datearray['mon'], $datearray['mday']);
					$lowerlimit = sprintf('%04d-%02d-%02d', $datearray['year'] - $_POST[$param_name] - 1, $datearray['mon'], $datearray['mday']);
					if (in_array($_POST['types'][$param_name], array('-', '--', '=')))
					{
						$query_parts[] = ($param_info['db_fields'][0]) . ' > {string:' . $param_name . '_minlimit}';
						$where_params[$param_name . '_minlimit'] = ($_POST['types'][$param_name] == '--' ? $upperlimit : $lowerlimit);
					}
					if (in_array($_POST['types'][$param_name], array('+', '++', '=')))
					{
						$query_parts[] = ($param_info['db_fields'][0]) . ' <= {string:' . $param_name . '_pluslimit}';
						$where_params[$param_name . '_pluslimit'] = ($_POST['types'][$param_name] == '++' ? $lowerlimit : $upperlimit);

						// Make sure that members that didn't set their birth year are not queried.
						$query_parts[] = ($param_info['db_fields'][0]) . ' > {date:dec_zero_date}';
						$where_params['dec_zero_date'] = '0004-12-31';
					}
				}
				// Special case - equals a date.
				elseif ($param_info['type'] == 'date' && $_POST['types'][$param_name] == '=')
				{
					$query_parts[] = $param_info['db_fields'][0] . ' > ' . $_POST[$param_name] . ' AND ' . $param_info['db_fields'][0] . ' < ' . ($_POST[$param_name] + 86400);
				}
				else
					$query_parts[] = $param_info['db_fields'][0] . ' ' . $range_trans[$_POST['types'][$param_name]] . ' ' . $_POST[$param_name];
			}
			// Checkboxes.
			elseif ($param_info['type'] == 'checkbox')
			{
				// All checkboxes or none at all are checked? Ignore.
				if (!is_array($_POST[$param_name]) || count($_POST[$param_name]) == 0 || count($_POST[$param_name]) == count($param_info['values']))
					continue;

				$query_parts[] = ($param_info['db_fields'][0]) . ' IN ({array_string:' . $param_name . '_check})';
				$where_params[$param_name . '_check'] = $_POST[$param_name];
			}
			else
			{
				// Replace the wildcard characters ('*' and '?') into MySQL ones.
				$parameter = strtolower(strtr(westr::htmlspecialchars($_POST[$param_name], ENT_QUOTES), array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_')));

				$query_parts[] = '(' . implode(' LIKE {string:' . $param_name . '_normal} OR ', $param_info['db_fields']) . ' LIKE {string:' . $param_name . '_normal})';
				$where_params[$param_name . '_normal'] = '%' . $parameter . '%';
			}
		}

		// Set up the membergroup query part.
		$mg_query_parts = array();

		// Primary membergroups, but only if at least one was not selected.
		if (!empty($_POST['membergroups'][1]) && count($context['membergroups']) != count($_POST['membergroups'][1]))
		{
			$mg_query_parts[] = 'mem.id_group IN ({array_int:group_check})';
			$where_params['group_check'] = $_POST['membergroups'][1];
		}

		// Additional membergroups. These are only relevant if not all primary groups were selected!
		if (!empty($_POST['membergroups'][2]) && (empty($_POST['membergroups'][1]) || count($context['membergroups']) != count($_POST['membergroups'][1])))
			foreach ($_POST['membergroups'][2] as $mg)
			{
				$mg_query_parts[] = 'FIND_IN_SET({int:add_group_' . $mg . '}, mem.additional_groups) != 0';
				$where_params['add_group_' . $mg] = $mg;
			}

		// Combine the one or two membergroup parts into one query part linked with an OR.
		if (!empty($mg_query_parts))
			$query_parts[] = '(' . implode(' OR ', $mg_query_parts) . ')';

		// Get all selected postcount-based membergroups.
		if (!empty($_POST['postgroups']) && count($_POST['postgroups']) != count($context['postgroups']))
		{
			$query_parts[] = 'id_post_group IN ({array_int:post_groups})';
			$where_params['post_groups'] = $_POST['postgroups'];
		}

		// Construct the where part of the query.
		$where = empty($query_parts) ? '1' : implode('
			AND ', $query_parts);

		$search_params = base64_encode(serialize($_POST));
	}
	else
		$search_params = null;

	// Construct the additional URL part with the query info in it.
	$context['params_url'] = $context['sub_action'] == 'query' ? ';sa=query;params=' . $search_params : '';

	// Get the title and template block ready...
	$context['page_title'] = $txt['admin_members'];

	$listOptions = array(
		'id' => 'member_list',
		'items_per_page' => $settings['defaultMaxMembers'],
		'base_href' => '<URL>?action=admin;area=viewmembers' . $context['params_url'],
		'default_sort_col' => 'user_name',
		'get_items' => array(
			'file' => 'Subs-Members',
			'function' => 'list_getMembers',
			'params' => array(
				isset($where) ? $where : '1=1',
				isset($where_params) ? $where_params : array(),
			),
		),
		'get_count' => array(
			'file' => 'Subs-Members',
			'function' => 'list_getNumMembers',
			'params' => array(
				isset($where) ? $where : '1=1',
				isset($where_params) ? $where_params : array(),
			),
		),
		'columns' => array(
			'id_member' => array(
				'header' => array(
					'value' => $txt['member_id'],
				),
				'data' => array(
					'db' => 'id_member',
					'class' => 'center',
				),
				'sort' => array(
					'default' => 'id_member',
					'reverse' => 'id_member DESC',
				),
			),
			'user_name' => array(
				'header' => array(
					'value' => $txt['username'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'id_member' => false,
							'member_name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'member_name',
					'reverse' => 'member_name DESC',
				),
			),
			'display_name' => array(
				'header' => array(
					'value' => $txt['display_name'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'id_member' => false,
							'real_name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'real_name',
					'reverse' => 'real_name DESC',
				),
			),
			'email' => array(
				'header' => array(
					'value' => $txt['email_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="mailto:%1$s">%1$s</a>',
						'params' => array(
							'email_address' => true,
						),
					),
				),
				'sort' => array(
					'default' => 'email_address',
					'reverse' => 'email_address DESC',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=trackip;searchip=%1$s">%1$s</a>',
						'params' => array(
							'member_ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'INET_ATON(member_ip)',
					'reverse' => 'INET_ATON(member_ip) DESC',
				),
			),
			'last_active' => array(
				'header' => array(
					'value' => $txt['viewmembers_online'],
				),
				'data' => array(
					'function' => function ($rowData) {
						global $txt;

						// Calculate number of days since last online.
						if (empty($rowData['last_login']))
							$difference = $txt['never'];
						else
						{
							$num_days_difference = jeffsdatediff($rowData['last_login']);

							// Today.
							if (empty($num_days_difference))
								$difference = $txt['viewmembers_today'];

							// Yesterday.
							elseif ($num_days_difference == 1)
								$difference = sprintf('1 %1$s', $txt['viewmembers_day_ago']);

							// X days ago.
							else
								$difference = sprintf('%1$d %2$s', $num_days_difference, $txt['viewmembers_days_ago']);
						}

						// Show it in italics if they're not activated...
						if ($rowData['is_activated'] % 10 != 1)
							$difference = sprintf('<em title="%1$s">%2$s</em>', $txt['not_activated'], $difference);

						return $difference;
					},
				),
				'sort' => array(
					'default' => 'last_login DESC',
					'reverse' => 'last_login',
				),
			),
			'posts' => array(
				'header' => array(
					'value' => $txt['member_postcount'],
				),
				'data' => array(
					'db' => 'posts',
				),
				'sort' => array(
					'default' => 'posts',
					'reverse' => 'posts DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'class' => 'center',
				),
				'data' => array(
					'function' => function ($rowData) {
						return '<input type="checkbox" name="delete[]" value="' . $rowData['id_member'] . '"' .
							($rowData['id_member'] == MID || $rowData['id_group'] == 1 || in_array(1, explode(',', $rowData['additional_groups'])) ? ' disabled' : '') . '>';
					},
					'class' => 'center',
				),
			),
		),
		'form' => array(
			'href' => '<URL>?action=admin;area=viewmembers' . $context['params_url'],
			'include_start' => true,
			'include_sort' => true,
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="delete_members" value="' . $txt['admin_delete_members'] . '" onclick="return ask(' . JavaScriptEscape($txt['confirm_delete_members']) . ', e);" class="delete">',
			),
		),
	);

	// Can they see all IP addresses? If not, they shouldn't see any.
	if (!$context['can_see_ip'])
		unset($listOptions['columns']['ip']);

	// Without enough permissions, don't show 'delete members' checkboxes.
	if (!allowedTo('profile_remove_any'))
		unset($listOptions['columns']['check'], $listOptions['form'], $listOptions['additional_rows']);

	loadSource('Subs-List');
	createList($listOptions);

	wetem::load('show_list');
	$context['default_list'] = 'member_list';
}

// Search the member list, using one or more criteria.
function SearchMembers()
{
	global $context, $txt;

	// Get a list of all the membergroups and postgroups that can be selected.
	$context['membergroups'] = array(
		array(
			'id' => 0,
			'name' => $txt['membergroups_members'],
			'can_be_additional' => false
		)
	);
	$context['postgroups'] = array();

	$request = wesql::query('
		SELECT id_group, group_name, min_posts
		FROM {db_prefix}membergroups
		WHERE id_group != {int:moderator_group}
		ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'moderator_group' => 3,
			'newbie_group' => 4,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		if ($row['min_posts'] == -1)
			$context['membergroups'][] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name'],
				'can_be_additional' => true
			);
		else
			$context['postgroups'][] = array(
				'id' => $row['id_group'],
				'name' => $row['group_name']
			);
	}
	wesql::free_result($request);

	$context['page_title'] = $txt['admin_members'];
	wetem::load('search_members');
}

// List all members who are awaiting approval / activation
function MembersAwaitingActivation()
{
	global $txt, $context, $settings;

	// Not a lot here!
	$context['page_title'] = $txt['admin_members'];
	wetem::load('admin_browse');
	$context['browse_type'] = isset($_REQUEST['type']) ? $_REQUEST['type'] : (!empty($settings['registration_method']) && $settings['registration_method'] == 1 ? 'activate' : 'approve');

	// Allowed filters are those we can have, in theory.
	$context['allowed_filters'] = $context['browse_type'] == 'approve' ? array(3, 4, 5) : array(0, 2);
	$context['current_filter'] = isset($_REQUEST['filter']) && in_array($_REQUEST['filter'], $context['allowed_filters']) && !empty($context['activation_numbers'][$_REQUEST['filter']]) ? (int) $_REQUEST['filter'] : -1;

	// Sort out the different sub areas that we can actually filter by.
	$context['available_filters'] = array();
	foreach ($context['activation_numbers'] as $type => $amount)
	{
		// We have some of these...
		if (in_array($type, $context['allowed_filters']) && $amount > 0)
			$context['available_filters'][] = array(
				'type' => $type,
				'amount' => $amount,
				'desc' => isset($txt['admin_browse_filter_type_' . $type]) ? $txt['admin_browse_filter_type_' . $type] : '?',
				'selected' => $type == $context['current_filter']
			);
	}

	// If the filter was not sent, set it to whatever has people in it!
	if ($context['current_filter'] == -1 && !empty($context['available_filters'][0]['amount']))
		$context['current_filter'] = $context['available_filters'][0]['type'];

	// This little variable is used to determine if we should flag where we are looking.
	$context['show_filter'] = ($context['current_filter'] != 0 && $context['current_filter'] != 3) || count($context['available_filters']) > 1;

	// The columns that can be sorted.
	$context['columns'] = array(
		'id_member' => array('label' => $txt['admin_browse_id']),
		'member_name' => array('label' => $txt['admin_browse_username']),
		'email_address' => array('label' => $txt['admin_browse_email']),
		'member_ip' => array('label' => $txt['admin_browse_ip']),
		'date_registered' => array('label' => $txt['admin_browse_registered']),
		'last_login' => array('label' => $txt['admin_browse_login']),
	);
	// If they can't see IP addresses, they can't sort by them either.
	$context['can_see_ip'] = allowedTo('manage_bans');
	if (!$context['can_see_ip'])
		unset($context['columns']['member_ip']);

	// Are we showing duplicate information?
	if (isset($_GET['showdupes']))
		$_SESSION['showdupes'] = (int) $_GET['showdupes'];
	$context['show_duplicates'] = !empty($_SESSION['showdupes']);

	// Determine which actions we should allow on this page.
	if ($context['browse_type'] == 'approve')
	{
		// If we are approving deleted accounts we have a slightly different list... actually a mirror ;)
		if ($context['current_filter'] == 4)
			$context['allowed_actions'] = array(
				'reject' => $txt['admin_browse_w_approve_deletion'],
				'ok' => $txt['admin_browse_w_reject'],
			);
		else
			$context['allowed_actions'] = array(
				'ok' => $txt['admin_browse_w_approve'],
				'okemail' => $txt['admin_browse_w_approve'] . ' ' . $txt['admin_browse_w_email'],
				'require_activation' => $txt['admin_browse_w_approve_require_activate'],
				'reject' => $txt['admin_browse_w_reject'],
				'rejectemail' => $txt['admin_browse_w_reject'] . ' ' . $txt['admin_browse_w_email'],
			);
	}
	elseif ($context['browse_type'] == 'activate')
		$context['allowed_actions'] = array(
			'ok' => $txt['admin_browse_w_activate'],
			'okemail' => $txt['admin_browse_w_activate'] . ' ' . $txt['admin_browse_w_email'],
			'delete' => $txt['admin_browse_w_delete'],
			'deleteemail' => $txt['admin_browse_w_delete'] . ' ' . $txt['admin_browse_w_email'],
			'remind' => $txt['admin_browse_w_remind'] . ' ' . $txt['admin_browse_w_email'],
		);

	// Create an option list for actions allowed to be done with selected members.
	$allowed_actions = '<option value="" data-hide>' . $txt['admin_browse_with_selected'] . ':</option>';

	foreach ($context['allowed_actions'] as $key => $desc)
		$allowed_actions .= '
							<option value="' . $key . '">' . $desc . '</option>';

	// Set up the JavaScript function for selecting an action for the list.
	$javascript = '
	function onSelectChange(e)
	{
		var message = "", val = $("[name=todo]").val();
		if (val == "")
			return;';

	// We have special messages for approving deletion of accounts - it's surprisingly logical - honest.
	if ($context['current_filter'] == 4)
		$javascript .= '

		if (val.indexOf("reject") != -1)
			message = "' . $txt['admin_browse_w_delete'] . '";
		else
			message = "' . $txt['admin_browse_w_reject'] . '";';
	// Otherwise a nice standard message.
	else
		$javascript .= '

		if (val.indexOf("delete") != -1)
			message = "' . $txt['admin_browse_w_delete'] . '";
		else if (val.indexOf("reject") != -1)
			message = "' . $txt['admin_browse_w_reject'] . '";
		else if (val == "remind")
			message = "' . $txt['admin_browse_w_remind'] . '";
		else
			message = "' . ($context['browse_type'] == 'approve' ? $txt['admin_browse_w_approve'] : $txt['admin_browse_w_activate']) . '";';
	$javascript .= '
		if (ask(message + " ' . $txt['admin_browse_warn'] . '", e))
			document.forms.postForm.submit();
	}';

	$listOptions = array(
		'id' => 'approve_list',
		'items_per_page' => $settings['defaultMaxMembers'],
		'base_href' => '<URL>?action=admin;area=viewmembers;sa=browse;type=' . $context['browse_type'] . (!empty($context['show_filter']) ? ';filter=' . $context['current_filter'] : ''),
		'default_sort_col' => $context['current_filter'] == 4 ? 'last_login' : 'date_registered',
		'get_items' => array(
			'file' => 'Subs-Members',
			'function' => 'list_getMembers',
			'params' => array(
				'is_activated = {int:activated_status}',
				array('activated_status' => $context['current_filter']),
				$context['show_duplicates'],
			),
		),
		'get_count' => array(
			'file' => 'Subs-Members',
			'function' => 'list_getNumMembers',
			'params' => array(
				'is_activated = {int:activated_status}',
				array('activated_status' => $context['current_filter']),
			),
		),
		'columns' => array(
			'id_member' => array(
				'header' => array(
					'value' => $txt['member_id'],
				),
				'data' => array(
					'db' => 'id_member',
					'class' => 'center',
				),
				'sort' => array(
					'default' => 'id_member',
					'reverse' => 'id_member DESC',
				),
			),
			'user_name' => array(
				'header' => array(
					'value' => $txt['username'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=profile;u=%1$d">%2$s</a>',
						'params' => array(
							'id_member' => false,
							'member_name' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'member_name',
					'reverse' => 'member_name DESC',
				),
			),
			'email' => array(
				'header' => array(
					'value' => $txt['email_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="mailto:%1$s">%1$s</a>',
						'params' => array(
							'email_address' => true,
						),
					),
				),
				'sort' => array(
					'default' => 'email_address',
					'reverse' => 'email_address DESC',
				),
			),
			'ip' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=trackip;searchip=%1$s">%1$s</a>',
						'params' => array(
							'member_ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'INET_ATON(member_ip)',
					'reverse' => 'INET_ATON(member_ip) DESC',
				),
			),
			'hostname' => array(
				'header' => array(
					'value' => $txt['hostname'],
				),
				'data' => array(
					'function' => function ($rowData) {
						return host_from_ip($rowData['member_ip']);
					},
					'class' => 'smalltext',
				),
			),
			'last_login' => array(
				'header' => array(
					'value' => $txt['admin_browse_login'],
				),
				'data' => array(
					'function' => function ($rowData) {
						return timeformat($rowData['last_login']);
					},
				),
				'sort' => array(
					'default' => 'last_login',
					'reverse' => 'last_login DESC',
				),
			),
			'date_registered' => array(
				'header' => array(
					'value' => $txt['date_registered'],
				),
				'data' => array(
					'function' => function ($rowData) {
						return timeformat($rowData['date_registered']);
					},
				),
				'sort' => array(
					'default' => 'date_registered DESC',
					'reverse' => 'date_registered',
				),
			),
			'duplicates' => array(
				'header' => array(
					'value' => $txt['duplicates'],
					// Make sure it doesn't go too wide.
					'style' => 'width: 20%',
				),
				'data' => array(
					'function' => function ($rowData) {
						global $txt;

						$member_links = array();
						foreach ($rowData['duplicate_members'] as $member)
						{
							if ($member['id'])
								$member_links[] = '<a href="<URL>?action=profile;u=' . $member['id'] . '" ' . (!empty($member['is_banned']) ? 'style="color: red"' : '') . '>' . $member['name'] . '</a>';
							else
								$member_links[] = $member['name'] . ' (' . $txt['guest'] . ')';
						}
						return implode (', ', $member_links);
					},
					'class' => 'smalltext',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="todoAction[]" value="%1$d">',
						'params' => array(
							'id_member' => false,
						),
					),
					'class' => 'center',
				),
			),
		),
		'javascript' => $javascript,
		'form' => array(
			'href' => '<URL>?action=admin;area=viewmembers;sa=approve;type=' . $context['browse_type'],
			'name' => 'postForm',
			'include_start' => true,
			'include_sort' => true,
			'hidden_fields' => array(
				'orig_filter' => $context['current_filter'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '
					<div class="floatleft">
						[<a href="<URL>?action=admin;area=viewmembers;sa=browse;showdupes=' . ($context['show_duplicates'] ? 0 : 1) . ';type=' . $context['browse_type'] . (!empty($context['show_filter']) ? ';filter=' . $context['current_filter'] : '') . ';' . $context['session_query'] . '">' . ($context['show_duplicates'] ? $txt['dont_check_for_duplicate'] : $txt['check_for_duplicate']) . '</a>]
					</div>
					<div class="floatright">
						<select name="todo" onchange="onSelectChange(e);">
							' . $allowed_actions . '
						</select>
						<noscript><input type="submit" value="' . $txt['go'] . '"></noscript>
					</div>',
			),
		),
	);

	// Need to drop last login or registered date depending on whether it's showing deletions or not (last login for deletions, reg date otherwise)
	if ($context['current_filter'] == 4)
		unset($listOptions['columns']['date_registered']);
	else
		unset($listOptions['columns']['last_login']);

	// Pick what column to actually include if we're showing duplicates.
	if ($context['show_duplicates'])
		unset($listOptions['columns']['email']);
	else
		unset($listOptions['columns']['duplicates']);

	// Only show hostname on duplicates as it takes a lot of time. Note that you can't see hostname if you can't see IP.
	if (!$context['show_duplicates'] || !empty($settings['disableHostnameLookup']) || !$context['can_see_ip'])
		unset($listOptions['columns']['hostname']);

	// And don't show them the IP address.
	if (!$context['can_see_ip'])
		unset($listOptions['columns']['ip']);

	// Is there any need to show filters?
	if (isset($context['available_filters']) && count($context['available_filters']) > 1)
	{
		$filterOptions = '
			<strong>' . $txt['admin_browse_filter_by'] . ':</strong>
			<select name="filter" onchange="this.form.submit();">';
		foreach ($context['available_filters'] as $filter)
			$filterOptions .= '
				<option value="' . $filter['type'] . '"' . ($filter['selected'] ? ' selected' : '') . '>' . $filter['desc'] . ' - ' . $filter['amount'] . ' ' . ($filter['amount'] == 1 ? $txt['user'] : $txt['users']) . '</option>';
		$filterOptions .= '
			</select>
			<noscript><input type="submit" value="' . $txt['go'] . '" name="filter"></noscript>';
		$listOptions['additional_rows'][] = array(
			'position' => 'above_column_headers',
			'value' => $filterOptions,
			'class' => 'center',
		);
	}

	// What about if we only have one filter, but it's not the "standard" filter - show them what they are looking at.
	if (!empty($context['show_filter']) && !empty($context['available_filters']))
		$listOptions['additional_rows'][] = array(
			'position' => 'above_column_headers',
			'value' => '<strong>' . $txt['admin_browse_filter_show'] . ':</strong> ' . $txt['admin_browse_filter_type_' . $context['current_filter']],
			'class' => 'smalltext left',
		);

	// Now that we have all the options, create the list.
	loadSource('Subs-List');
	createList($listOptions);
}

// Do the approve/activate/delete stuff
function AdminApprove()
{
	global $settings;

	// First, check our session.
	checkSession();

	loadSource('Subs-Post');

	// We also need to the login languages here - for emails.
	loadLanguage('Login');

	// Sort out where we are going...
	$browse_type = isset($_REQUEST['type']) ? $_REQUEST['type'] : (!empty($settings['registration_method']) && $settings['registration_method'] == 1 ? 'activate' : 'approve');
	$current_filter = (int) $_REQUEST['orig_filter'];

	// If we are applying a filter do just that - then redirect.
	if (isset($_REQUEST['filter']) && $_REQUEST['filter'] != $_REQUEST['orig_filter'])
		redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $_REQUEST['filter'] . ';start=' . $_REQUEST['start']);

	// Nothing to do?
	if (!isset($_POST['todoAction']) && !isset($_POST['time_passed']))
		redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $current_filter . ';start=' . $_REQUEST['start']);

	// Are we dealing with members who have been waiting for > set amount of time?
	if (isset($_POST['time_passed']))
	{
		$timeBefore = time() - 86400 * (int) $_POST['time_passed'];
		$condition = '
			AND date_registered < {int:time_before}';
	}
	// Coming from checkboxes - validate the members passed through to us.
	else
	{
		$members = array();
		foreach ($_POST['todoAction'] as $id)
			$members[] = (int) $id;
		$condition = '
			AND id_member IN ({array_int:members})';
	}

	// Get information on each of the members, things that are important to us, like email address...
	$request = wesql::query('
		SELECT id_member, member_name, real_name, email_address, validation_code, lngfile
		FROM {db_prefix}members
		WHERE is_activated = {int:activated_status}' . $condition . '
		ORDER BY lngfile',
		array(
			'activated_status' => $current_filter,
			'time_before' => empty($timeBefore) ? 0 : $timeBefore,
			'members' => empty($members) ? array() : $members,
		)
	);

	$member_count = wesql::num_rows($request);

	// If no results then just return!
	if ($member_count == 0)
		redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $current_filter . ';start=' . $_REQUEST['start']);

	$member_info = array();
	$members = array();
	// Fill the info array.
	while ($row = wesql::fetch_assoc($request))
	{
		$members[] = $row['id_member'];
		$member_info[] = array(
			'id' => $row['id_member'],
			'username' => $row['member_name'],
			'name' => $row['real_name'],
			'email' => $row['email_address'],
			'language' => empty($row['lngfile']) || empty($settings['userLanguage']) ? $settings['language'] : $row['lngfile'],
			'code' => $row['validation_code']
		);
	}
	wesql::free_result($request);

	// Are we activating or approving the members?
	if ($_POST['todo'] == 'ok' || $_POST['todo'] == 'okemail')
	{
		// Approve/activate this member.
		wesql::query('
			UPDATE {db_prefix}members
			SET validation_code = {string:blank_string}, is_activated = {int:is_activated}, active_state_change = {int:active_state_change}
			WHERE is_activated = {int:activated_status}' . $condition,
			array(
				'is_activated' => 1,
				'active_state_change' => time(),
				'time_before' => empty($timeBefore) ? 0 : $timeBefore,
				'members' => empty($members) ? array() : $members,
				'activated_status' => $current_filter,
				'blank_string' => '',
			)
		);

		// Do we have to let any hooks know about the activations?
		if (!empty($settings['hooks']['activate']))
			foreach ($member_info as $member)
				call_hook('activate', array($member['username']));

		// Check for email.
		if ($_POST['todo'] == 'okemail')
		{
			foreach ($member_info as $member)
			{
				$replacements = array(
					'REALNAME' => $member['name'],
					'USERNAME' => $member['username'],
					'PROFILELINK' => SCRIPT . '?action=profile;u=' . $member['id'],
					'FORGOTPASSWORDLINK' => SCRIPT . '?action=reminder',
				);

				$emaildata = loadEmailTemplate('admin_approve_accept', $replacements, $member['language']);
				sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
			}
		}
	}
	// Maybe we're sending it off for activation?
	elseif ($_POST['todo'] == 'require_activation')
	{
		loadSource('Subs-Members');

		// We have to do this for each member I'm afraid.
		foreach ($member_info as $member)
		{
			// Generate a random activation code.
			$validation_code = generateValidationCode();

			// Set these members for activation - I know this includes two id_member checks but it's safer than bodging $condition ;).
			wesql::query('
				UPDATE {db_prefix}members
				SET validation_code = {string:validation_code}, is_activated = {int:not_activated}
				WHERE is_activated = {int:activated_status}
					' . $condition . '
					AND id_member = {int:selected_member}',
				array(
					'not_activated' => 0,
					'activated_status' => $current_filter,
					'selected_member' => $member['id'],
					'validation_code' => $validation_code,
					'time_before' => empty($timeBefore) ? 0 : $timeBefore,
					'members' => empty($members) ? array() : $members,
				)
			);

			$replacements = array(
				'REALNAME' => $member['name'],
				'ACTIVATIONLINK' => SCRIPT . '?action=activate;u=' . $member['id'] . ';code=' . $validation_code,
				'ACTIVATIONLINKWITHOUTCODE' => SCRIPT . '?action=activate;u=' . $member['id'],
				'ACTIVATIONCODE' => $validation_code,
			);

			$emaildata = loadEmailTemplate('admin_approve_activation', $replacements, $member['language']);
			sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);
		}
	}
	// Are we rejecting them?
	elseif ($_POST['todo'] == 'reject' || $_POST['todo'] == 'rejectemail')
	{
		loadSource('Subs-Members');
		deleteMembers($members);

		// Send email telling them they aren't welcome?
		if ($_POST['todo'] == 'rejectemail')
		{
			foreach ($member_info as $member)
			{
				$replacements = array(
					'USERNAME' => $member['name'],
				);

				$emaildata = loadEmailTemplate('admin_approve_reject', $replacements, $member['language']);
				sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);
			}
		}
	}
	// A simple delete?
	elseif ($_POST['todo'] == 'delete' || $_POST['todo'] == 'deleteemail')
	{
		loadSource('Subs-Members');
		deleteMembers($members);

		// Send email telling them they aren't welcome?
		if ($_POST['todo'] == 'deleteemail')
		{
			foreach ($member_info as $member)
			{
				$replacements = array(
					'USERNAME' => $member['name'],
				);

				$emaildata = loadEmailTemplate('admin_approve_delete', $replacements, $member['language']);
				sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);
			}
		}
	}
	// Remind them to activate their account?
	elseif ($_POST['todo'] == 'remind')
	{
		foreach ($member_info as $member)
		{
			$replacements = array(
				'REALNAME' => $member['name'],
				'ACTIVATIONLINK' => SCRIPT . '?action=activate;u=' . $member['id'] . ';code=' . $member['code'],
				'ACTIVATIONLINKWITHOUTCODE' => SCRIPT . '?action=activate;u=' . $member['id'],
				'ACTIVATIONCODE' => $member['code'],
			);

			$emaildata = loadEmailTemplate('admin_approve_remind', $replacements, $member['language']);
			sendmail($member['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);
		}
	}

	// Back to the user's language!
	if (isset($current_language) && $current_language != we::$user['language'])
	{
		loadLanguage('index');
		loadLanguage('ManageMembers');
	}

	// Log what we did?
	if (!empty($settings['log_enabled_admin']) && in_array($_POST['todo'], array('ok', 'okemail', 'require_activation', 'remind')))
	{
		$log_action = $_POST['todo'] == 'remind' ? 'remind_member' : 'approve_member';
		$log_inserts = array();
		$our_ip = get_ip_identifier(we::$user['ip']); // Yes, even though it's statically held in that function, no point in even calling it if we don't have to.
		foreach ($member_info as $member)
		{
			$log_inserts[] = array(
				time(), 3, MID, $our_ip, $log_action,
				0, 0, 0, serialize(array('member' => $member['id'])),
			);
		}
		wesql::insert('',
			'{db_prefix}log_actions',
			array(
				'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'int', 'action' => 'string',
				'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
			),
			$log_inserts
		);
	}

	// Although updateStats *may* catch this, best to do it manually just in case (Doesn't always sort out unapprovedMembers).
	if (in_array($current_filter, array(3, 4)))
		updateSettings(array('unapprovedMembers' => max(0, $settings['unapprovedMembers'] > $member_count)));

	// Update the member's stats. (but, we know the member didn't change their name.)
	updateStats('member', false);

	// If they haven't been deleted, update the post group statistics on them...
	if (!in_array($_POST['todo'], array('delete', 'deleteemail', 'reject', 'rejectemail', 'remind')))
		updateStats('postgroups', $members);

	redirectexit('action=admin;area=viewmembers;sa=browse;type=' . $_REQUEST['type'] . ';sort=' . $_REQUEST['sort'] . ';filter=' . $current_filter . ';start=' . $_REQUEST['start']);
}

function jeffsdatediff($old)
{
	// Get the current time as the user would see it...
	$forumTime = forum_time();

	// Calculate the seconds that have passed since midnight.
	$sinceMidnight = date('H', $forumTime) * 60 * 60 + date('i', $forumTime) * 60 + date('s', $forumTime);

	// Take the difference between the two times.
	$dis = time() - $old;

	// Before midnight?
	if ($dis < $sinceMidnight)
		return 0;
	else
		$dis -= $sinceMidnight;

	// Divide out the seconds in a day to get the number of days.
	return ceil($dis / (24 * 60 * 60));
}
