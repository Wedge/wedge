<?php
/**
 * Wedge
 *
 * This file carries many useful functions that will come into use on most page loads, but not tied to a specific area of operations.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

if (isset($sourcedir))
{
	require_once($sourcedir . '/Subs-BBC.php');
	require_once($sourcedir . '/Subs-Cache.php');
}

/**
 * This function updates some internal statistics as necessary.
 *
 * Although there are three parameters listed, the second and third parameters may be ignored depending on the first.
 *
 * This function handles four distinct branches of statistic/data management, reflected by the type: member, message, subject, topic.
 * - If type is member, two operations can be carried out. If neither parameter 1 or parameter 2 is set, recalculate the total number of members, and obtain the user id and name of the latest member (and update the $modSettings with this for the board index), and also ensure the count of unapproved users is correct (excluding COPPA users). Alternatively, when coming directly from registration etc, supply parameter 1 as the numeric user id and parameter 2 as the user name.
 * - If type is message, two operations can be carried out. If parameter 1 is boolean true, and parameter 2 is not null, have {@link updateSettings()} recalculate the total messages, and supply to it the contents of parameter 2 to be used as the id of the 'highest known message at this time', which is used for tracking read/unread status. Alternatively, recalculate the forum-wide total number of messages and the highest message id using the general board data.
 * - If type is subject, this function should be being called to update search data when a subject changes in a message. Parameter 1 should be the topic id, parameter 2 the new subject of the topic.
 * - If type is topic, two operations can be carried out. If parameter 1 is boolean true, increment the total number of topics (parameter 2 is ignored). Otherwise manually recalculate the forum-wide number of topics from the board data.
 * - If type is postgroups, this function is to ensure post count groups are updated. Parameter 1 can be either null (update all members), an integer (a single user id) or an array (of user ids) as the scope of update. Parameter 2 will either be null, or an array of columns which should include 'posts' as a value (for when called from other areas where multiple other columns are being updated)
 *
 * @param string $type An string denoting the operation, can be any one of: member, message, subject, topic, postgroups.
 * @param mixed $parameter1 See notes above as for operations
 * @param mixed $parameter2 See notes above as for operations
 */
function updateStats($type, $parameter1 = null, $parameter2 = null)
{
	global $modSettings;

	if ($type === 'member')
	{
		$changes = array(
			'memberlist_updated' => time(),
		);

		// #1 latest member ID, #2 the real name for a new registration.
		if (is_numeric($parameter1))
		{
			$changes['latestMember'] = $parameter1;
			$changes['latestRealName'] = $parameter2;

			updateSettings(array('totalMembers' => true), true);
		}

		// We need to calculate the totals.
		else
		{
			// Update the latest activated member (highest id_member) and count.
			$result = wesql::query('
				SELECT COUNT(*), MAX(id_member)
				FROM {db_prefix}members
				WHERE is_activated = {int:is_activated}',
				array(
					'is_activated' => 1,
				)
			);
			list ($changes['totalMembers'], $changes['latestMember']) = wesql::fetch_row($result);
			wesql::free_result($result);

			// Get the latest activated member's display name.
			$result = wesql::query('
				SELECT real_name
				FROM {db_prefix}members
				WHERE id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => (int) $changes['latestMember'],
				)
			);
			list ($changes['latestRealName']) = wesql::fetch_row($result);
			wesql::free_result($result);

			// Are we using registration approval?
			if ((!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 2) || !empty($modSettings['approveAccountDeletion']))
			{
				// Update the amount of members awaiting approval - ignoring COPPA accounts, as you can't approve them until you get permission.
				$result = wesql::query('
					SELECT COUNT(*)
					FROM {db_prefix}members
					WHERE is_activated IN ({array_int:activation_status})',
					array(
						'activation_status' => array(3, 4),
					)
				);
				list ($changes['unapprovedMembers']) = wesql::fetch_row($result);
				wesql::free_result($result);
			}
		}

		updateSettings($changes);
	}
	elseif ($type === 'message')
	{
		if ($parameter1 === true && $parameter2 !== null)
			updateSettings(array('totalMessages' => true, 'maxMsgID' => $parameter2), true);
		else
		{
			// SUM and MAX on a smaller table is better for InnoDB tables.
			$result = wesql::query('
				SELECT SUM(num_posts + unapproved_posts) AS total_messages, MAX(id_last_msg) AS max_msg_id
				FROM {db_prefix}boards
				WHERE redirect = {string:blank_redirect}' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
					AND id_board != {int:recycle_board}' : ''),
				array(
					'recycle_board' => isset($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
					'blank_redirect' => '',
				)
			);
			$row = wesql::fetch_assoc($result);
			wesql::free_result($result);

			updateSettings(array(
				'totalMessages' => $row['total_messages'] === null ? 0 : $row['total_messages'],
				'maxMsgID' => $row['max_msg_id'] === null ? 0 : $row['max_msg_id']
			));
		}
	}
	elseif ($type === 'subject')
	{
		// Remove the previous subject (if any).
		wesql::query('
			DELETE FROM {db_prefix}log_search_subjects
			WHERE id_topic = {int:id_topic}',
			array(
				'id_topic' => $parameter1,
			)
		);
		wesql::query('
			DELETE FROM {db_prefix}pretty_topic_urls
			WHERE id_topic = {int:id_topic}',
			array(
				'id_topic' => $parameter1,
			)
		);
		if (!empty($modSettings['pretty_enable_cache']) && is_numeric($parameter1) && $parameter1 > 0)
			wesql::query('
				DELETE FROM {db_prefix}pretty_urls_cache
				WHERE url_id LIKE {string:topic_search}',
				array(
					'topic_search' => '%topic=' . $parameter1 . '%',
				)
			);

		// Insert the new subject.
		if ($parameter2 !== null)
		{
			if (!function_exists('pretty_generate_url'))
				loadSource('Subs-PrettyUrls');
			pretty_update_topic($parameter2, $parameter1);

			$parameter1 = (int) $parameter1;
			$parameter2 = text2words($parameter2);

			$inserts = array();
			foreach ($parameter2 as $word)
				$inserts[] = array($word, $parameter1);

			if (!empty($inserts))
				wesql::insert('ignore',
					'{db_prefix}log_search_subjects',
					array('word' => 'string', 'id_topic' => 'int'),
					$inserts,
					array('word', 'id_topic')
				);
		}
	}
	elseif ($type === 'topic')
	{
		if ($parameter1 === true)
			updateSettings(array('totalTopics' => true), true);
		else
		{
			// Get the number of topics - a SUM is better for InnoDB tables.
			// We also ignore the recycle bin here because there will probably be a bunch of one-post topics there.
			$result = wesql::query('
				SELECT SUM(num_topics + unapproved_topics) AS total_topics
				FROM {db_prefix}boards' . (!empty($modSettings['recycle_enable']) && $modSettings['recycle_board'] > 0 ? '
				WHERE id_board != {int:recycle_board}' : ''),
				array(
					'recycle_board' => !empty($modSettings['recycle_board']) ? $modSettings['recycle_board'] : 0,
				)
			);
			$row = wesql::fetch_assoc($result);
			wesql::free_result($result);

			updateSettings(array('totalTopics' => $row['total_topics'] === null ? 0 : $row['total_topics']));
		}
	}
	elseif ($type === 'postgroups')
	{
		// Parameter two is the updated columns: we should check to see if we base groups off any of these.
		if ($parameter2 !== null && !in_array('posts', $parameter2))
			return;

		if (($postgroups = cache_get_data('updateStats:postgroups', 360)) == null)
		{
			// Fetch the postgroups!
			$request = wesql::query('
				SELECT id_group, min_posts
				FROM {db_prefix}membergroups
				WHERE min_posts != {int:min_posts}',
				array(
					'min_posts' => -1,
				)
			);
			$postgroups = array();
			while ($row = wesql::fetch_assoc($request))
				$postgroups[$row['id_group']] = $row['min_posts'];
			wesql::free_result($request);

			// Sort them this way because if it's done with MySQL it causes a filesort :(.
			arsort($postgroups);

			cache_put_data('updateStats:postgroups', $postgroups, 360);
		}

		// Oh great, they've screwed their post groups.
		if (empty($postgroups))
			return;

		// Set all membergroups from most posts to least posts.
		$conditions = '';
		foreach ($postgroups as $id => $min_posts)
		{
			$conditions .= '
					WHEN posts >= ' . $min_posts . (!empty($lastMin) ? ' AND posts <= ' . $lastMin : '') . ' THEN ' . $id;
			$lastMin = $min_posts;
		}

		// A big fat CASE WHEN... END should be faster than a zillion UPDATE's ;)
		wesql::query('
			UPDATE {db_prefix}members
			SET id_post_group = CASE ' . $conditions . '
					ELSE 0
				END' . ($parameter1 != null ? '
			WHERE ' . (is_array($parameter1) ? 'id_member IN ({array_int:members})' : 'id_member = {int:members}') : ''),
			array(
				'members' => $parameter1,
			)
		);
	}
	else
		trigger_error('updateStats(): Invalid statistic type \'' . $type . '\'', E_USER_NOTICE);
}

/**
 * Update the members table with data.
 *
 * This function ensures the member table is updated for one, multiple or all users. Note:
 * - If level 2 caching is in use, the appropriate cache data will be flushed with the new values.
 * - The change_member_data hook where any of the common values are updated.
 * - {@link updateStats() is also called so that if we have updated post count, post count groups will also be managed automatically.
 * - This function should always be called for updating member data rather than updating the members table directly.
 * - All string data should have been processed with htmlspecialchars for security; no sanitisation is performed on the data.
 *
 * @param mixed $members The member or members that are to be updated. null for all members, an integer for an individual user, or an array of integers for multiple users to be affected.
 * @param array $data A key/value pair array that contains the field to be updated and the new value. Additionally, if the field is known to be an integer (of which a list of known columns is stated), supplying a value of + or - will allow the column to be incremented or decremented without explicitly specifying the new value.
 */
function updateMemberData($members, $data)
{
	global $modSettings, $user_info;

	$parameters = array();
	if (is_array($members))
	{
		$condition = 'id_member IN ({array_int:members})';
		$parameters['members'] = $members;
	}
	elseif ($members === null)
		$condition = '1=1';
	else
	{
		$condition = 'id_member = {int:member}';
		$parameters['member'] = $members;
	}

	if (!empty($modSettings['hooks']['change_member_data']))
	{
		// Only a few member variables are really interesting for hooks.
		$hook_vars = array(
			'member_name',
			'real_name',
			'email_address',
			'id_group',
			'gender',
			'birthdate',
			'website_title',
			'website_url',
			'location',
			'hide_email',
			'time_format',
			'time_offset',
			'avatar',
			'lngfile',
		);
		$vars_to_integrate = array_intersect($hook_vars, array_keys($data));

		// Only proceed if there are any variables left to call the hook.
		if (count($vars_to_integrate) != 0)
		{
			// Fetch a list of member_names if necessary
			if ((!is_array($members) && $members === $user_info['id']) || (is_array($members) && count($members) == 1 && in_array($user_info['id'], $members)))
				$member_names = array($user_info['username']);
			else
			{
				$member_names = array();
				$request = wesql::query('
					SELECT member_name
					FROM {db_prefix}members
					WHERE ' . $condition,
					$parameters
				);
				while ($row = wesql::fetch_assoc($request))
					$member_names[] = $row['member_name'];
				wesql::free_result($request);
			}

			if (!empty($member_names))
				foreach ($vars_to_integrate as $var)
					call_hook('change_member_data', array($member_names, $var, &$data[$var]));
		}
	}

	// Everything is assumed to be a string unless it's in the below.
	$knownInts = array(
		'date_registered', 'posts', 'id_group', 'last_login', 'instant_messages', 'unread_messages',
		'new_pm', 'pm_prefs', 'gender', 'hide_email', 'show_online', 'pm_email_notify', 'pm_receive_from',
		'notify_announcements', 'notify_send_body', 'notify_regularity', 'notify_types',
		'id_theme', 'is_activated', 'id_msg_last_visit', 'id_post_group', 'total_time_logged_in', 'warning',
	);
	$knownFloats = array(
		'time_offset',
	);

	$setString = '';
	foreach ($data as $var => $val)
	{
		$type = 'string';
		if (in_array($var, $knownInts))
			$type = 'int';
		elseif (in_array($var, $knownFloats))
			$type = 'float';
		elseif ($var == 'birthdate')
			$type = 'date';

		// Doing an increment?
		if ($type == 'int' && ($val === '+' || $val === '-'))
		{
			$val = $var . ' ' . $val . ' 1';
			$type = 'raw';
		}

		// Ensure posts, instant_messages, and unread_messages don't overflow or underflow.
		if (in_array($var, array('posts', 'instant_messages', 'unread_messages')))
		{
			if (preg_match('~^' . $var . ' (\+ |- |\+ -)([\d]+)~', $val, $match))
			{
				if ($match[1] != '+ ')
					$val = 'CASE WHEN ' . $var . ' <= ' . abs($match[2]) . ' THEN 0 ELSE ' . $val . ' END';
				$type = 'raw';
			}
		}

		$setString .= ' ' . $var . ' = {' . $type . ':p_' . $var . '},';
		$parameters['p_' . $var] = $val;
	}

	wesql::query('
		UPDATE {db_prefix}members
		SET' . substr($setString, 0, -1) . '
		WHERE ' . $condition,
		$parameters
	);

	updateStats('postgroups', $members, array_keys($data));

	// Clear any caching?
	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2 && !empty($members))
	{
		if (!is_array($members))
			$members = array($members);

		foreach ($members as $member)
		{
			if ($modSettings['cache_enable'] >= 3)
			{
				cache_put_data('member_data-profile-' . $member, null, 120);
				cache_put_data('member_data-normal-' . $member, null, 120);
				cache_put_data('member_data-minimal-' . $member, null, 120);
			}
			cache_put_data('user_settings-' . $member, null, 60);
		}
	}
}

/**
 * Updates settings in the primary forum-wide settings table, and its local $modSettings equivalent.
 *
 * If a value to be updated would not be changed (is the same), that change will not be issued as a query. Also note that $modSettings will be updated too, and that the cache entry for $modSettings will be purged so that next page load is using the current (recached) settings.
 *
 * @param array $changeArray A key/value pair where the array key specifies the entry in the settings table and $modSettings array to be updated, and the value specifies the new value. Additionally, when $update is true, the value can be specified as true or false to increment or decrement (respectively) the current value.
 * @param bool $update If the value is known to already exist, this can be specified as true to have the data in the table be managed through an UPDATE query, rather than a REPLACE query. Note that UPDATE queries are run individually, while a REPLACE applies all changes simultaneously to the table.
 */
function updateSettings($changeArray, $update = false)
{
	global $modSettings;

	if (empty($changeArray) || !is_array($changeArray))
		return;

	// In some cases, this may be better and faster, but for large sets we don't want so many UPDATEs.
	if ($update)
	{
		foreach ($changeArray as $variable => $value)
		{
			wesql::query('
				UPDATE {db_prefix}settings
				SET value = {' . ($value === false || $value === true ? 'raw' : 'string') . ':value}
				WHERE variable = {string:variable}',
				array(
					'value' => $value === true ? 'value + 1' : ($value === false ? 'value - 1' : $value),
					'variable' => $variable,
				)
			);
			$modSettings[$variable] = $value === true ? $modSettings[$variable] + 1 : ($value === false ? $modSettings[$variable] - 1 : $value);
		}

		// Clean out the cache and make sure the cobwebs are gone too.
		cache_put_data('modSettings', null, 90);

		return;
	}

	$replaceArray = array();
	foreach ($changeArray as $variable => $value)
	{
		// Don't bother if it's already like that ;)
		if (isset($modSettings[$variable]) && $modSettings[$variable] == $value)
			continue;

		// If the variable isn't set, but would only be set to nothingness, then don't bother setting it.
		elseif (!isset($modSettings[$variable]) && empty($value))
			continue;

		$replaceArray[] = array($variable, $value);

		$modSettings[$variable] = $value;
	}

	if (empty($replaceArray))
		return;

	wesql::insert('replace',
		'{db_prefix}settings',
		array('variable' => 'string-255', 'value' => 'string-65534'),
		$replaceArray,
		array('variable')
	);

	// Kill the cache - it needs redoing now, but we won't bother ourselves with that here.
	cache_put_data('modSettings', null, 90);
}

/**
 * This function is used to construct the page lists used throughout the application, e.g. 1 ... 6 7 [8] 9 10 ... 15.
 *
 * - The function accepts a start position, for calculating the page out of the list of possible pages, however if the value is not the start of an actual page, the function will sanitise the value so that it will be the actual start of the 'page' of content. It also will sanitise where the start is beyond the last item.
 * - Parameters such as wireless being in the URL are also managed.
 * - Many URLs in the application are in the form of item=x.y format, e.g. index.php?topic=1.20 to denote topic 1, 20 items in. This can be achieved by specifying $flexible_start as true, and %1$d in the basic URL component, e.g. passing the base URL as index.php?topic=1.%1$d
 * - If $modSettings['compactTopicPagesEnable'] is empty, no compaction of page items is used and all pages are displayed; if enabled, only the first, last and the display will consist of multiple contiguous items centered on the current page (stated as $modSettings['compactTopicPagesContiguous'], halved, either side of the current page)
 *
 * @param string $base_url The basic URL to be used for each link.
 * @param int &$start The start position, by reference. If this is not a multiple of the number of items per page, it is sanitized to be so and the value will persist upon the function's return.
 * @param int $max_value The total number of items you are paginating for.
 * @param int $num_per_page The number of items to be displayed on a given page. $start will be forced to be a multiple of this value.
 * @param bool $flexible_start Whether a ;start=x component should be introduced into the URL automatically (see above)
 * @param bool $show_prevnext Whether the Previous and Next links should be shown (should be on only when navigating the list)
 * @return string The complete HTML of the page index that was requested.
 */
function constructPageIndex($base_url, &$start, $max_value, $num_per_page, $flexible_start = false, $show_prevnext = true)
{
	global $modSettings, $txt;

	// Save whether $start was less than 0 or not.
	$start = (int) $start;
	$start_invalid = $start < 0;

	// Make sure $start is a proper variable - not less than 0.
	if ($start_invalid)
		$start = 0;
	// Not greater than the upper bound.
	elseif ($start >= $max_value)
		$start = max(0, (int) $max_value - (((int) $max_value % (int) $num_per_page) == 0 ? $num_per_page : ((int) $max_value % (int) $num_per_page)));
	// And it has to be a multiple of $num_per_page!
	else
		$start = max(0, (int) $start - ((int) $start % (int) $num_per_page));

	// Wireless will need the protocol on the URL somewhere.
	if (WIRELESS)
		$base_url .= ';' . WIRELESS_PROTOCOL;

	$base_link = '<a class="navPages" href="' . ($flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d') . '">%2$s</a> ';

	// Compact pages is off or on?
	if (empty($modSettings['compactTopicPagesEnable']))
	{
		// Show the left arrow.
		$pageindex = $start == 0 ? ' ' : sprintf($base_link, $start - $num_per_page, '&#171;');

		// Show all the pages.
		$display_page = 1;
		for ($counter = 0; $counter < $max_value; $counter += $num_per_page)
			$pageindex .= $start == $counter && !$start_invalid ? '<strong>' . $display_page++ . '</strong> ' : sprintf($base_link, $counter, $display_page++);

		// Show the right arrow.
		$display_page = ($start + $num_per_page) > $max_value ? $max_value : ($start + $num_per_page);
		if ($start != $counter - $max_value && !$start_invalid)
			$pageindex .= $display_page > $counter - $num_per_page ? ' ' : sprintf($base_link, $display_page, '&#187;');
	}
	else
	{
		// If they didn't enter an odd value, pretend they did.
		$PageContiguous = (int) ($modSettings['compactTopicPagesContiguous'] - ($modSettings['compactTopicPagesContiguous'] % 2)) / 2;

		$pageindex = '';

		// First of all, do we want a 'next' button to take us closer to the first (most interesting) page?
		if ($show_prevnext && $start >= $num_per_page)
			$pageindex .= sprintf($base_link, $start - $num_per_page, $txt['previous_next_back']);

		// Show the first page. (>1< ... 6 7 [8] 9 10 ... 15)
		if ($start > $num_per_page * $PageContiguous)
			$pageindex .= sprintf($base_link, 0, '1');

		// Show the ... after the first page. (1 >...< 6 7 [8] 9 10 ... 15)
		if ($start > $num_per_page * ($PageContiguous + 1))
		{
			$base_page = JavaScriptEscape($flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d');
			$pageindex .= '<span onclick="' . htmlspecialchars('expandPages(this, ' . $base_page . ', ' . $num_per_page . ', ' . ($start - $num_per_page * $PageContiguous) . ', ' . $num_per_page . ');') . '" onmouseover="this.style.cursor = \'pointer\';"> ... </span>';
		}

		// Show the pages before the current one. (1 ... >6 7< [8] 9 10 ... 15)
		for ($nCont = $PageContiguous; $nCont >= 1; $nCont--)
			if ($start >= $num_per_page * $nCont)
			{
				$tmpStart = $start - $num_per_page * $nCont;
				$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
			}

		// Show the current page. (1 ... 6 7 >[8]< 9 10 ... 15)
		if (!$start_invalid)
			$pageindex .= '[<strong>' . ($start / $num_per_page + 1) . '</strong>] ';
		else
			$pageindex .= sprintf($base_link, $start, $start / $num_per_page + 1);

		// Show the pages after the current one... (1 ... 6 7 [8] >9 10< ... 15)
		$tmpMaxPages = (int) (($max_value - 1) / $num_per_page) * $num_per_page;
		for ($nCont = 1; $nCont <= $PageContiguous; $nCont++)
			if ($start + $num_per_page * $nCont <= $tmpMaxPages)
			{
				$tmpStart = $start + $num_per_page * $nCont;
				$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
			}

		// Show the '...' part near the end. (1 ... 6 7 [8] 9 10 >...< 15)
		if ($start + $num_per_page * ($PageContiguous + 1) < $tmpMaxPages)
		{
			if (!isset($base_page))
				$base_page = JavaScriptEscape($flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d');
			$pageindex .= '<span onclick="' . htmlspecialchars('expandPages(this, ' . $base_page . ', ' . ($start + $num_per_page * ($PageContiguous + 1)) . ', ' . $tmpMaxPages . ', ' . $num_per_page . ');') . '" onmouseover="this.style.cursor = \'pointer\';"> ... </span>';
		}

		// Show the last number in the list. (1 ... 6 7 [8] 9 10 ... >15<)
		if ($start + $num_per_page * $PageContiguous < $tmpMaxPages)
			$pageindex .= sprintf($base_link, $tmpMaxPages, $tmpMaxPages / $num_per_page + 1);

		// Finally, the next link.
		if ($show_prevnext && $start + $num_per_page < $max_value)
			$pageindex .= sprintf($base_link, $start + $num_per_page, $txt['previous_next_forward']);
	}

	return $pageindex;
}

/**
 * Prunes non valid XML/XHTML characters from a string intended for XML/XHTML transport use.
 *
 * Primarily this function removes non-printable control codes from an XML output (tab, CR, LF are preserved), including non valid UTF-8 character signatures if appropriate.
 *
 * @param string $string A string of potential output.
 * @return string The sanitized string.
 */
function cleanXml($string)
{
	global $context;

	// http://www.w3.org/TR/2000/REC-xml-20001006#NT-Char
	return preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x19\x{D800}-\x{DFFF}\x{FFFE}\x{FFFF}]~u', '', $string);
}

/**
 * Sanitize strings that might be passed through to JavaScript.
 *
 * Multiple instances of scripts will need to be adjusted through the codebase if passed to JavaScript through the template. This function will handle quoting of the string's contents, including providing the encapsulating quotes (so no need to echo '"', JavaScriptEscape($var), '"'; but simply echo JavaScriptEscape($var); instead)
 *
 * Other protections include dealing with newlines, carriage returns (through suppression), single quotes, links, inline script tags, and $scripturl. (Probably to prevent search bots from indexing JS-only URLs.)
 *
 * @param string $string A string whose contents to be quoted.
 * @return string A transformed string with contents suitably single quoted for use in JavaScript.
 */
function JavaScriptEscape($string)
{
	global $scripturl;

	return '\'' . strtr($string, array(
		"\n" => "\\\n",
		'\\' => '\\\\',
		'\'' => '\\\'',
		'script' => 'scr\\ipt',
		'href=' => 'hr\\ef=',
		'"' . $scripturl => '"' . $scripturl . '"+"',
		'\'' . $scripturl => '\'' . $scripturl . '\'+\''
	)) . '\'';
}

/**
 * Format a number in a localized fashion.
 *
 * Each of the language packs should declare $txt['number_format'] in the index language file, which is simply a string that consists of the number 1234.00 localized to that region. This function detects the thousands and decimal separators, and uses those in its place. It also detects the number of digits in the decimal position, and rounds to that many digits. Note that the style is cached locally (statically) for the life of the page.
 *
 * @param float $number The number to format.
 * @param bool $override_decimal_count If true, $number will be treated as an integer even if it is not (numbers will be rounded to suit)
 */
function comma_format($number, $override_decimal_count = false)
{
	global $txt;
	static $thousands_separator = null, $decimal_separator = null, $decimal_count = null;

	// Skip formatting if number needs no separators. (is_integer($number) && abs($number) < 1000, optimized for speed.)
	if (((int) $number) === $number && $number > -1000 && $number < 1000)
		return $number;

	// Cache these values...
	if ($decimal_separator === null)
	{
		// Not set for whatever reason?
		if (empty($txt['number_format']) || preg_match('~^1([^\d]*)?234([^\d]*)(0*?)$~', $txt['number_format'], $matches) != 1)
			return $number;

		// Cache these each load...
		$thousands_separator = $matches[1];
		$decimal_separator = $matches[2];
		$decimal_count = strlen($matches[3]);
	}

	// Format the string with our friend, number_format.
	return number_format($number, is_float($number) ? ($override_decimal_count === false ? $decimal_count : $override_decimal_count) : 0, $decimal_separator, $thousands_separator);
}

/**
 * Attempts to find the correct language string for a given numeric string. For example, to be able to find the right string to use for '1 cookie' vs '2 cookies'.
 *
 * $txt is checked for prefix_number as a string, e.g. calling $string as 'cookie' and $number as 1, $txt['cookie_1'] will be examined, if present it will be used, otherwise cookie_n will be used instead. Different languages have different needs in this case, so it is up to the language files to provide the different constructions necessary. Note that there will be a call to sprintf as well since the string should contain %s for the number if appropriate as it will be passed through comma_format.
 *
 * @param $string The prefix in $txt to check against.
 * @param $number The number of items to look for.
 * @param bool $format_comma Specify whether to comma-format the number.
 * @return The string as found in $txt (note: the case where _n is used but not present will return an error, it is up to the language files to present a minimum fallback)
 */
function number_context($string, $number, $format_comma = true)
{
	global $txt;

	return sprintf($txt[$string . '_' . (isset($txt[$string . '_' . $number]) ? $number : 'n')], $format_comma ? comma_format($number) : $number);
}

/**
 * Format a given timestamp, optionally applying the forum and user offsets, for display including 'Today' and 'Yesterday' prefixes.
 *
 * This function also applies the date/time format string the admin can specify in the admin panel (Features and Options / General) user can specify in their Look and Layout Preferences through strftime.
 *
 * @param int $log_time Timestamp to use. No default is given, will often be derived from stored content.
 * @param mixed $show_today When calling from outside this function, it is whether to use 'Today' format at all, or override the forum settings and not use it (use it is default). This function also makes use of this function to call itself for formatting the time part of 'Today' dates, and uses this to pass the time-only format back.
 * @param mixed $offset_type The offset type to use when considering the timestamp; Boolean false (default) means to apply forum and user offsets to the given timestamp, 'forum' to apply only the forum's time offset, any other value to bypass any offsets being applied.
 *
 * @return string The formatted time and date, will include localized strings with HTML formatting the case of 'Today' and 'Yesterday' strings.
 */
function timeformat($log_time, $show_today = true, $offset_type = false)
{
	global $context, $user_info, $txt, $modSettings;
	static $non_twelve_hour;

	// Offset the time.
	if (!$offset_type)
		$time = $log_time + ($user_info['time_offset'] + $modSettings['time_offset']) * 3600;
	// Just the forum offset?
	else
		$time = $log_time + ($offset_type == 'forum' ? $modSettings['time_offset'] * 3600 : 0);

	// We can't have a negative date (on Windows, at least.)
	if ($log_time < 0)
		$log_time = 0;

	// Today and Yesterday?
	if ($modSettings['todayMod'] >= 1 && $show_today === true)
	{
		// Get the current time.
		$nowtime = forum_time();

		$then = @getdate($time);
		$now = @getdate($nowtime);

		// Try to make something of a time format string...
		$s = strpos($user_info['time_format'], '%S') === false ? '' : ':%S';
		if (strpos($user_info['time_format'], '%H') === false && strpos($user_info['time_format'], '%T') === false)
		{
			$h = strpos($user_info['time_format'], '%l') === false ? '%I' : '%l';
			$today_fmt = $h . ':%M' . $s . ' %p';
		}
		else
			$today_fmt = '%H:%M' . $s;

		// Same day of the year, same year.... Today!
		if ($then['yday'] == $now['yday'] && $then['year'] == $now['year'])
			return $txt['today'] . timeformat($log_time, $today_fmt, $offset_type);

		// Day-of-year is one less and same year, or it's the first of the year and that's the last of the year...
		if ($modSettings['todayMod'] == '2' && (($then['yday'] == $now['yday'] - 1 && $then['year'] == $now['year']) || ($now['yday'] == 0 && $then['year'] == $now['year'] - 1) && $then['mon'] == 12 && $then['mday'] == 31))
			return $txt['yesterday'] . timeformat($log_time, $today_fmt, $offset_type);

		// Is this the current year? Then why bother printing out the year?
		if ($then['year'] == $now['year'])
			$show_today = str_replace(' %Y', '', $user_info['time_format']);
	}

	$str = !is_bool($show_today) ? $show_today : $user_info['time_format'];

	if (!isset($non_twelve_hour))
		$non_twelve_hour = trim(strftime('%p')) === '';
	if ($non_twelve_hour && strpos($str, '%p') !== false)
		$str = str_replace('%p', strftime('%H', $time) < 12 ? $txt['time_am'] : $txt['time_pm'], $str);

	if ($user_info['setlocale'])
	{
		foreach (array('%a', '%A', '%b', '%B') as $token)
			if (strpos($str, $token) !== false)
				$str = str_replace($token, !empty($txt['lang_capitalize_dates']) ? westr::ucwords(strftime($token, $time)) : strftime($token, $time), $str);
	}
	else
	{
		// Do-it-yourself time localization. Fun.
		foreach (array('%a' => 'days_short', '%A' => 'days', '%b' => 'months_short', '%B' => 'months') as $token => $text_label)
			if (strpos($str, $token) !== false)
				$str = str_replace($token, $txt[$text_label][(int) strftime($token === '%a' || $token === '%A' ? '%w' : '%m', $time)], $str);

		if (strpos($str, '%p') !== false)
			$str = str_replace('%p', strftime('%H', $time) < 12 ? $txt['time_am'] : $txt['time_pm'], $str);
	}

	// Windows doesn't support %e; on some versions, strftime fails altogether if used, so let's prevent that.
	if ($context['server']['is_windows'] && strpos($str, '%e') !== false)
		$str = str_replace('%e', '%#d', $str);

	// Format any other characters..
	return strftime($str, $time);
}

/**
 * Format a time, and add "on" if not "today" or "yesterday"
 *
 * @param int $log_time See timeformat()
 * @param mixed $show_today See timeformat()
 * @param mixed $offset_type See timeformat()
 *
 * @return string Same as timeformat(), except that "on" will be shown before numeric dates.
 */
function on_timeformat($log_time, $show_today = true, $offset_type = false)
{
	global $txt;
	$ret = timeformat($log_time, $show_today, $offset_type);
	return is_numeric($ret[0]) ? sprintf($txt['on_date'], $ret) : $ret;
}

/**
 * Returns 'On March 21' or 'Today', depending on the date. Used when templating.
 *
 * @param int $time Human-readable date
 * @param bool $upper Set to true if this starts a sentence or a block
 *
 * @return string Human-readable date in a "on" context.
 */
function on_date($time, $upper = false)
{
	global $txt;
	return is_numeric($time[0]) ? ($upper ? ucfirst(sprintf($txt['on_date'], $ret)) : sprintf($txt['on_date'], $ret)) : $ret;
}

/**
 * Returns the current timestamp (seconds since midnight 1/1/1970) with forum offset and optionally user's preference for time offset.
 *
 * @param bool $use_user_offset Specifies that the time returned should include the user's time offset set in their Look and Layout Preferences.
 * @param mixed $timestamp Specifies a timestamp to be used for calculation; this will return the timestamp modified by the forum/user options. If unspecified or null, return the current time modified by these options.
 *
 * @return int Timestamp since Unix epoch in seconds
 */
function forum_time($use_user_offset = true, $timestamp = null)
{
	global $user_info, $modSettings;

	if ($timestamp === null)
		$timestamp = time();
	elseif ($timestamp == 0)
		return 0;

	return $timestamp + ($modSettings['time_offset'] + ($use_user_offset ? $user_info['time_offset'] : 0)) * 3600;
}

/**
 * Reconverts a number of the translations performed by {@link preparsecode()} with respect to HTML entity characters (e.g. angle brackets, quotes, apostrophes)
 *
 * This function effectively performs mostly as htmlspecialchars_decode(ENT_QUOTES) for the important characters, however it also adds the apostrophe and non-breaking spaces.
 *
 * @param string $string A string that has been converted through {@link preparsecode()} previously; this ensures the common HTML entities, non breaking spaces and apostrophes are not subject to double conversion or being over-escaped when submitted back to the editor component.
 * @return string The string, with the characters converted back.
 */
function un_htmlspecialchars($string)
{
	static $translation;

	if (!isset($translation))
		$translation = array_flip(get_html_translation_table(HTML_SPECIALCHARS, ENT_QUOTES)) + array('&#039;' => '\'', '&nbsp;' => ' ');

	return strtr($string, $translation);
}

/**
 * Shortens a string, typically a thread subject, in a way that is intended to avoid breaking in internationalization ways.
 *
 * Specifically, if a string is longer than the specified length, shorten it and add an ellipsis. Internationlized characters and entities are respected as 'one' character for length calculations, and also trailing entities are avoided too.
 *
 * @param string $subject The string of the full subject.
 * @param int $length The maximum length in characters of the shortened string.
 *
 * @return string The shortened string
 */
function shorten_subject($subject, $len)
{
	// It was already short enough!
	if (westr::strlen($subject) <= $len)
		return $subject;

	// Shorten it by the length it was too long, and strip off junk from the end.
	return westr::substr($subject, 0, $len) . '...';
}

/**
 * Log the current user (even as a guest), as being online and optionally including their current location.
 *
 * - If the theme settings are set to display users in a board or topic, ensure the user is listed as being in those places (adjusting $force as necessary)
 * - If the user is possibly a robot, carry on with spider logging.
 * - If the last time the user was logged online is less than 8 seconds ago, and force is off; exit.
 * - If "Who's Online" is enabled, grab everything from $_GET, plus the user agent, prepare to store it.
 * - Ensure we have their user id, check to see if older things need to be purged and if so, do so.
 * - Log them online, store it in the session, and update how long the user has been online.
 *
 * @param bool $force Whether to force there to be an update of the table or not.
 */
function writeLog($force = false)
{
	global $user_info, $user_settings, $context, $modSettings, $settings, $topic, $board;

	// If we are showing who is viewing a topic, let's see if we are, and force an update if so - to make it accurate.
	if (!empty($settings['display_who_viewing']) && ($topic || $board))
	{
		// Take the opposite approach!
		$force = true;
		// Don't update for every page - this isn't wholly accurate but who cares.
		if ($topic)
		{
			if (isset($_SESSION['last_topic_id']) && $_SESSION['last_topic_id'] == $topic)
				$force = false;
			$_SESSION['last_topic_id'] = $topic;
		}
	}

	// Are they a spider we should be tracking? Mode = 1 gets tracked on its spider check...
	if (!empty($user_info['possibly_robot']) && !empty($modSettings['spider_mode']) && $modSettings['spider_mode'] > 1)
	{
		loadSource('ManageSearchEngines');
		logSpider();
	}

	// Don't mark them as online more than every so often.
	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= (time() - 8) && !$force)
		return;

	if (!empty($modSettings['who_enabled']))
	{
		$serialized = $_GET + array('USER_AGENT' => $_SERVER['HTTP_USER_AGENT']);

		// In the case of a dlattach action, session_var may not be set.
		if (!isset($context['session_var']))
		{
			$context['session_var'] = $_SESSION['session_var'];
			$context['session_query'] = $context['session_var'] . '=' . $_SESSION['session_value'];
		}

		unset($serialized['sesc'], $serialized[$context['session_var']]);
		$serialized = serialize($serialized);
	}
	else
		$serialized = '';

	// Guests use 0, members use their session ID.
	$session_id = $user_info['is_guest'] ? 'ip' . $user_info['ip'] : session_id();

	// Grab the last all-of-SMF-specific log_online deletion time.
	$do_delete = cache_get_data('log_online-update', 30) < time() - 30;

	// If the last click wasn't a long time ago, and there was a last click...
	if (!empty($_SESSION['log_time']) && $_SESSION['log_time'] >= time() - $modSettings['lastActive'] * 20)
	{
		if ($do_delete)
		{
			wesql::query('
				DELETE FROM {db_prefix}log_online
				WHERE log_time < {int:log_time}
					AND session != {string:session}',
				array(
					'log_time' => time() - $modSettings['lastActive'] * 60,
					'session' => $session_id,
				)
			);

			// Cache when we did it last.
			cache_put_data('log_online-update', time(), 30);
		}

		wesql::query('
			UPDATE {db_prefix}log_online
			SET log_time = {int:log_time}, ip = {int:ip}, url = {string:url}
			WHERE session = {string:session}',
			array(
				'log_time' => time(),
				'ip' => get_ip_identifier($user_info['ip']),
				'url' => $serialized,
				'session' => $session_id,
			)
		);

		// Guess it got deleted.
		if (wesql::affected_rows() == 0)
			$_SESSION['log_time'] = 0;
	}
	else
		$_SESSION['log_time'] = 0;

	// Otherwise, we have to delete and insert.
	if (empty($_SESSION['log_time']))
	{
		if ($do_delete || !empty($user_info['id']))
			wesql::query('
				DELETE FROM {db_prefix}log_online
				WHERE ' . ($do_delete ? 'log_time < {int:log_time}' : '') . ($do_delete && !empty($user_info['id']) ? ' OR ' : '') . (empty($user_info['id']) ? '' : 'id_member = {int:current_member}'),
				array(
					'current_member' => $user_info['id'],
					'log_time' => time() - $modSettings['lastActive'] * 60,
				)
			);

		wesql::insert($do_delete ? 'ignore' : 'replace',
			'{db_prefix}log_online',
			array('session' => 'string', 'id_member' => 'int', 'id_spider' => 'int', 'log_time' => 'int', 'ip' => 'int', 'url' => 'string'),
			array($session_id, $user_info['id'], empty($_SESSION['id_robot']) ? 0 : $_SESSION['id_robot'], time(), get_ip_identifier($user_info['ip']), $serialized),
			array('session')
		);
	}

	// Mark your session as being logged.
	$_SESSION['log_time'] = time();

	// Well, they are online now.
	if (empty($_SESSION['timeOnlineUpdated']))
		$_SESSION['timeOnlineUpdated'] = time();

	// Set their login time, if not already done within the last minute.
	if (SMF != 'SSI' && !empty($user_info['last_login']) && $user_info['last_login'] < time() - 60)
	{
		// Don't count longer than 15 minutes.
		if (time() - $_SESSION['timeOnlineUpdated'] > 60 * 15)
			$_SESSION['timeOnlineUpdated'] = time();

		$user_settings['total_time_logged_in'] += time() - $_SESSION['timeOnlineUpdated'];
		updateMemberData($user_info['id'], array('last_login' => time(), 'member_ip' => $user_info['ip'], 'member_ip2' => $_SERVER['BAN_CHECK_IP'], 'total_time_logged_in' => $user_settings['total_time_logged_in']));

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('user_settings-' . $user_info['id'], $user_settings, 60);

		$user_info['total_time_logged_in'] += time() - $_SESSION['timeOnlineUpdated'];
		$_SESSION['timeOnlineUpdated'] = time();
	}
}

/**
 * Ensures the browser is redirected to another location. Should be used after anything is posted to ensure the browser cannot repost the form data.
 *
 * This often marks the end of general processing, since ultimately it diverts execution to {@link obExit()} which means a closedown of processing, buffers and final output. Things to note:
 * - A call is made before continuing to ensure that the mail queue is processed.
 * - Session IDs (where applicable, e.g. for those without cookies) are added in if needed.
 * - The redirect hook is called, just before the actual redirect, in case the hook wishes to alter where redirection occurs.
 * - The source of redirection is noted in the session when in debug mode.
 *
 * @param string $setLocation The string representing the URL. If an internal (into the forum) link, this should be in the form of action=whatever (i.e. without the full domain and path to index.php, or the ?). Note this can be an external URL too.
 * @param bool $refresh Whether to use a Refresh HTTP header or whether to use Location (default).
 */
function redirectexit($setLocation = '', $refresh = false, $permanent = false)
{
	global $scripturl, $context, $modSettings, $db_show_debug, $db_cache;

	// In case we have mail to send, better do that - as obExit doesn't always quite make it...
	if (!empty($context['flush_mail']))
		AddMailQueue(true);

	$add = preg_match('~^(ftp|http)[s]?://~', $setLocation) == 0 && substr($setLocation, 0, 6) != 'about:';

	if (WIRELESS)
	{
		// Add the scripturl on if needed.
		if ($add)
			$setLocation = $scripturl . '?' . $setLocation;

		$char = strpos($setLocation, '?') === false ? '?' : ';';

		if (strpos($setLocation, '#') !== false)
			$setLocation = strtr($setLocation, array('#' => $char . WIRELESS_PROTOCOL . '#'));
		else
			$setLocation .= $char . WIRELESS_PROTOCOL;
	}
	elseif ($add)
		$setLocation = $scripturl . ($setLocation != '' ? '?' . $setLocation : '');

	// Put the session ID in.
	if (defined('SID') && SID != '')
		$setLocation = preg_replace('/^' . preg_quote($scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', $scripturl . '?' . SID . ';', $setLocation);
	// Keep that debug in their for template debugging!
	elseif (isset($_GET['debug']))
		$setLocation = preg_replace('/^' . preg_quote($scripturl, '/') . '\\??/', $scripturl . '?debug;', $setLocation);

	// Redirections should be prettified too
	if (!empty($modSettings['pretty_enable_filters']))
	{
		loadSource('PrettyUrls-Filters');
		$url = array(0 => array('url' => str_replace($scripturl, '', $setLocation)));
		foreach ($modSettings['pretty_filters'] as $id => $enabled)
		{
			if ($enabled)
				$pretty_url = call_user_func('pretty_filter_' . $id, $url);
			if (isset($pretty_url[0]['replacement']))
				break;
		}
		if (isset($pretty_url[0]['replacement']))
			$setLocation = $pretty_url[0]['replacement'];
		$setLocation = str_replace(chr(18), '\'', $setLocation);
		$setLocation = preg_replace(array('~;+|=;~', '~\?;~', '~[?;=]#|&amp;#~', '~[?;=#]$|&amp;$~'), array(';', '?', '#', ''), $setLocation);
	}

	// Maybe hooks want to change where we are heading?
	call_hook('redirect', array(&$setLocation, &$refresh));

	if ($permanent)
		header('HTTP/1.1 301 Moved Permanently');

	// We send a Refresh header only in special cases because Location looks better. (and is quicker...)
	if ($refresh && !WIRELESS)
		header('Refresh: 0; URL=' . strtr($setLocation, array(' ' => '%20')));
	else
		header('Location: ' . str_replace(' ', '%20', $setLocation));

	// Debugging.
	if (isset($db_show_debug) && $db_show_debug === true)
		$_SESSION['debug_redirect'] = $db_cache;

	obExit(false);
}

/**
 * This function marks the end of processing, proceeds to close down output buffers, flushing content as it does so, before terminating execution.
 *
 * This function operates in two principle ways - raw content, or forum page mode, depending on the parameters passed; this is so non HTML data can be passed through it, e.g. XML.
 *
 * Several side operations occur alongside principle handling:
 * - Recursive calls to this function will attempt to be blocked.
 * - The stats cache will be flushed to the tables (so instead of issuing queries that potentially update the same values multiple times, they are only updated on closedown)
 * - A call will be put in to work on the mail queue.
 * - Make sure the page title is sanitised.
 * - Begin the session ID injecting output buffer.
 * - Ensure any hooked buffers are called.
 * - Display the header if correct to display then main page content, then the contents of $context['include_after_template'], followed by footer if correct to display, and lastly by debug data if enabled and available.
 * - Store the user agent string from the browser for security comparisons next page load.
 *
 * @param mixed $header Whether to issue the header templates or not (often including the main menu). Normally this will be the case, because normally you will require standard templating (i.e pass null, or true here when calling from elsewhere in the app), or false if you require raw content output.
 * @param mixed $do_footer Nominally this follows $header, with one important difference. Whereas with $header, null means to have headers, with $do_footer, null means to inherit from $header. So to have headers, a null/null combination is usually desirable (as index.php does), or to have raw output, simply pass $header as false and omit this parameter.
 * @param bool $from_index If this function is being called in the normal process of execution, this will be true, which enables this function to return so it can be called again later (so the header can be issued, followed by normal processing, followed by the footer, which is all driven by this function). Normally there will be no need to change this because when calling from elsewhere, execution is intended to end.
 * @param boom $from_fatal_error If obExit is being called in resolution of a fatal error, this must be set. It is used in ensuring obExit cascades correctly for header/footer when a fatal error has been encountered. Note that the error handler itself should attend to this (and thus, should be called instead of invoking this with an error message)
 */
function obExit($header = null, $do_footer = null, $from_index = false, $from_fatal_error = false)
{
	global $context, $settings, $modSettings, $txt;
	static $header_done = false, $footer_done = false, $level = 0, $has_fatal_error = false;

	// Attempt to prevent a recursive loop.
	++$level;
	if ($level > 1 && !$from_fatal_error && !$has_fatal_error)
		exit;
	if ($from_fatal_error)
		$has_fatal_error = true;

	// Clear out the stat cache.
	trackStats();

	// If we have mail to send, send it.
	if (!empty($context['flush_mail']))
		AddMailQueue(true);

	$do_header = $header === null ? !$header_done : $header;
	if ($do_footer === null)
		$do_footer = $do_header;

	// Has the template/header been done yet?
	if ($do_header)
	{
		// Was the page title set last minute? Also update the HTML safe one.
		if (!empty($context['page_title']) && empty($context['page_title_html_safe']))
			$context['page_title_html_safe'] = westr::htmlspecialchars(un_htmlspecialchars($context['page_title']));

		// Start up the session URL fixer.
		ob_start('ob_sessrewrite');

		if (!empty($settings['output_buffers']) && is_string($settings['output_buffers']))
			$buffers = explode(',', $settings['output_buffers']);
		elseif (!empty($settings['output_buffers']))
			$buffers = $settings['output_buffers'];
		else
			$buffers = array();

		if (isset($modSettings['hooks']['buffer']))
			$buffers = array_merge($modSettings['hooks']['buffer'], $buffers);

		if (!empty($buffers))
			foreach ($buffers as $function)
			{
				$call = strpos($function, '::') !== false ? array_map('trim', explode('::', $function)) : trim($function);

				// Is it valid?
				if (is_callable($call))
					ob_start($call);
			}

		// Display the screen in the logical order.
		template_header();
		$header_done = true;
	}

	if ($do_footer)
	{
		if (WIRELESS && !isset($context['sub_template']))
			fatal_lang_error('wireless_error_notyet', false);

		// We can either get side, top and/or main sub-templates, either as a string or an array of strings.
		if (empty($context['sidebar_template']))
			$context['sidebar_template'] = array();
		if (empty($context['top_template']))
			$context['top_template'] = array();
		if (empty($context['sub_template']))
			$context['sub_template'] = array('main');

		// If we're calling from a page that wants to hide the UI, don't show the sidebar
		if (empty($context['hide_chrome']))
		{
			execSubTemplate('sidebar_above', 'ignore');
			foreach ((array) $context['sidebar_template'] as $key => $template)
				execSubTemplate($template);
			execSubTemplate('sidebar_below', 'ignore');
		}

		execSubTemplate('main_above', 'ignore');
		// If we're calling from a page that wants to hide the UI, don't show the menus/tabs
		if (empty($context['hide_chrome']))
			foreach ((array) $context['top_template'] as $template)
				execSubTemplate($template);
		foreach ((array) $context['sub_template'] as $template)
			execSubTemplate($template);
		execSubTemplate('main_below', 'ignore');

		// Just so we don't get caught in an endless loop of errors from the footer...
		if (!$footer_done)
		{
			$footer_done = true;
			template_footer();

			if (!isset($_REQUEST['xml']) && empty($context['hide_chrome']))
				db_debug_junk();
		}
	}

	// Remember this URL in case someone doesn't like sending HTTP_REFERER.
	if (strpos($_SERVER['REQUEST_URL'], 'action=dlattach') === false && strpos($_SERVER['REQUEST_URL'], 'action=viewsmfile') === false)
		$_SESSION['old_url'] = $_SERVER['REQUEST_URL'];

	// For session check verfication.... don't switch browsers...
	$_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];

	// Hand off the output to the portal, etc. we're integrated with.
	call_hook('exit', array($do_footer && !WIRELESS));

	// Don't exit if we're coming from index.php; that will pass through normally.
	if (!$from_index || WIRELESS)
	{
		if (!isset($modSettings['app_error_count']))
			$modSettings['app_error_count'] = 0;
		if (!empty($context['app_error_count']))
			updateSettings(
				array(
					'app_error_count' => $modSettings['app_error_count'] + $context['app_error_count'],
				)
			);
		exit;
	}
}

/**
 * Rewrites URLs in the page to include the session ID if the user is using a normal browser and is not accepting cookies.
 *
 * - If $scripturl is empty, or no session id (e.g. SSI), exit.
 * - If ?debug has been specified previously, re-inject it back into the page's canonical reference.
 * - We also do our Pretty URLs voodoo here...
 *
 * @param string $buffer The contents of the output buffer thus far. Managed by PHP during the relevant ob_*() calls.
 * @return string The modified buffer.
 */
function ob_sessrewrite($buffer)
{
	global $scripturl, $modSettings, $user_info, $context, $db_prefix, $session_var;
	global $txt, $time_start, $db_count, $db_show_debug, $cached_urls, $use_cache;

	// Just quit if $scripturl is set to nothing, or the SID is not defined. (SSI?)
	if ($scripturl == '' || !defined('SID'))
		return $buffer;

	if (!empty($context['show_load_time']))
	{
		$old_db_count = $db_count;
		$old_load_time = microtime(true);
	}

	// Do nothing if the session is cookied, or they are a crawler - guests are caught by redirectexit().
	if (empty($_COOKIE) && SID != '' && empty($context['browser']['possibly_robot']))
		$buffer = preg_replace('/"' . preg_quote($scripturl, '/') . '(?!\?' . preg_quote(SID, '/') . ')\\??/', '"' . $scripturl . '?' . SID . '&amp;', $buffer);
	// Debugging templates, are we?
	elseif (isset($_GET['debug']))
		$buffer = preg_replace('/(?<!<link rel="canonical" href=)"' . preg_quote($scripturl, '/') . '\\??/', '"' . $scripturl . '?debug;', $buffer);

	call_hook('dynamic_rewrite', array(&$buffer));

	if (!allowedTo('profile_view_any'))
		$buffer = preg_replace(
			'~<a(?:\s+|\s[^>]*\s)href="' . preg_quote($scripturl, '~') . '\?action=profile' . (!$user_info['is_guest'] && allowedTo('profile_view_own') ? ';(?:[^"]+;)?u=(?!' . $user_info['id'] . ')' : '') . '[^"]*"[^>]*>(.*?)</a>~',
			'$1', $buffer
		);

	// Rewrite the buffer with pretty URLs!
	if (!empty($modSettings['pretty_enable_filters']))
	{
		// !!! $insideurl = str_replace(array('.','/',':','?'), array('\.','\/','\:','\?'), $scripturl);
		$insideurl = preg_quote($scripturl, '~');
		$use_cache = !empty($modSettings['pretty_enable_cache']);
		$session_var = $context['session_var'];

		// Remove the script tags
		$context['pretty']['scriptID'] = 0;
		$context['pretty']['scripts'] = array();
		$buffer = preg_replace_callback('~<script.+?</script>~s', 'pretty_scripts_remove', $buffer);

		// Find all URLs in the buffer
		$context['pretty']['search_patterns'][] =  '~(<a[^>]+href=|<link[^>]+href=|<img[^>]+?src=|<form[^>]+?action=)["\']' . $insideurl . '([^"\'#]*?[?;&](board|topic|action)=[^"\'#]+)~';
		$context['pretty']['replace_patterns'][] = '~(<a[^>]+href=|<link[^>]+href=|<img[^>]+?src=|<form[^>]+?action=)["\']' . $insideurl . '([^"\'#]*?[?;&](board|topic|action)=([^"]+\"|[^\']+\'))~';
		$urls_query = array();
		$uncached_urls = array();

		// Making sure we don't execute patterns twice.
		$context['pretty']['search_patterns'] = array_flip(array_flip($context['pretty']['search_patterns']));

		foreach ($context['pretty']['search_patterns'] as $pattern)
		{
			preg_match_all($pattern, $buffer, $matches, PREG_PATTERN_ORDER);
			foreach ($matches[2] as $match)
			{
				// Rip out everything that shouldn't be cached
				// !!! base64_encode sometimes finishes strings with '=' gaps IIRC. Should check whether they're not going to be ripped by this.
				// !!! Also, '~=?;+~' (in various places) should probably be rewritten as '~=;+|;{2,}~'
				if ($use_cache)
					$match = preg_replace(array('~^["\']|PHPSESSID=[^&;]+|' . $session_var . '=[^;]+~', '~"~', '~=?;+~', '~\?;|\?&amp;~', '~[?;=]+$~'), array('', '%22', ';', '?', ''), $match);
				else
					// !!! This can easily be optimized into a simple str_replace by adding placeholders for ^ and $.
					$match = preg_replace(array('~^["\']~', '~"~', '~=?;+~', '~\?;~', '~[?;=]+$~'), array('', '%22', ';', '?', ''), $match);
				$url_id = $match;
				$urls_query[] = $url_id;
				$uncached_urls[$match] = array(
					'url' => $match,
				);
			}
		}

		// Proceed only if there are actually URLs in the page
		if (count($urls_query) != 0)
		{
			// Eliminate duplicate URLs.
			$urls_query = array_keys(array_flip($urls_query));

			// Retrieve cached URLs
			$cached_urls = array();

			if ($use_cache)
			{
				$query = wesql::query('
					SELECT url_id, replacement
					FROM {db_prefix}pretty_urls_cache
					WHERE url_id IN ({array_string:urls})
						AND log_time > ' . (int) (time() - 86400),
					array(
						'urls' => $urls_query
					)
				);
				while ($row = wesql::fetch_assoc($query))
				{
					$cached_urls[$row['url_id']] = $row['replacement'];
					unset($uncached_urls[$row['url_id']]);
				}
				wesql::free_result($query);
			}

			// If there are any uncached URLs, process them
			if (count($uncached_urls) != 0)
			{
				// Run each filter callback function on each URL
				if (!function_exists('pretty_filter_topics'))
					loadSource('PrettyUrls-Filters');
				foreach ($modSettings['pretty_filters'] as $id => $enabled)
					if ($enabled)
						$uncached_urls = call_user_func('pretty_filter_' . $id, $uncached_urls);

				// Fill the cached URLs array
				$cache_data = array();
				foreach ($uncached_urls as $url_id => $url)
				{
					if (!isset($url['replacement']))
						$url['replacement'] = $url['url'];
					$url['replacement'] = str_replace(chr(18), "'", $url['replacement']);
					$url['replacement'] = preg_replace(array('~"~', '~=?;+~', '~\?;~', '~[?;=]+$~'), array('%22', ';', '?', ''), $url['replacement']);
					$cached_urls[$url_id] = $url['replacement'];
					if ($use_cache && strlen($url_id) < 256)
						$cache_data[] = '(\'' . $url_id . '\', \'' . addslashes($url['replacement']) . '\')';
				}

				// Cache these URLs in the database (use mysql_query to avoid some issues.)
				if (count($cache_data) > 0)
					mysql_query("REPLACE INTO {$db_prefix}pretty_urls_cache (url_id, replacement) VALUES " . implode(', ', $cache_data));
			}

			// Put the URLs back into the buffer
			foreach ($context['pretty']['replace_patterns'] as $pattern)
				$buffer = preg_replace_callback($pattern, 'pretty_buffer_callback', $buffer);
		}

		// Restore the script tags
		if ($context['pretty']['scriptID'] > 0)
			$buffer = preg_replace_callback('~' . chr(20) . '([0-9]+)' . chr(20) . '~', 'pretty_scripts_restore', $buffer);
	}

	// Moving all inline events (<code onclick="event();">) to the footer, to make
	// sure they're not triggered before jQuery and stuff are loaded. Trick and treats!
	$context['delayed_events'] = array();
	$cut = explode("<!-- JavaScript area -->\n", $buffer);

	// If the placeholder isn't there, it means we're probably not in a default index template,
	// and we probably don't need to postpone any events. Otherwise, go ahead and do the magic!
	if (!empty($cut[1]))
		$buffer = preg_replace_callback('~<[^>]+?\son[a-z]+="[^">]*"[^>]*>~i', 'wedge_event_delayer', $cut[0]) . $cut[1];

	if (!empty($context['delayed_events']))
	{
		$thing = 'var eves = {';
		foreach ($context['delayed_events'] as $eve)
			$thing .= '
		' . $eve[0] . ': ["' . $eve[1] . '", function (e) { ' . $eve[2] . ' }],';
		$thing = substr($thing, 0, -1) . '
	};
	$("*[data-eve]").each(function() {
		for (var eve = 0, elis = $(this).data("eve"), eil = elis.length; eve < eil; eve++)
			$(this).bind(eves[elis[eve]][0], eves[elis[eve]][1]);
	});';
		$buffer = substr_replace($buffer, $thing, strpos($buffer, '<!-- insert inline events here -->'), 34);
	}
	else
		$buffer = str_replace("\n\t<!-- insert inline events here -->\n", '', $buffer);

	// Nerd alert -- the first few lines (tag search process) can be done in a simple regex.
	//	while (preg_match_all('~<we:([^>\s]+)\s*([a-z][^>]+)?\>((?' . '>[^<]+|<(?!/?we:\\1))*?)</we:\\1>~i', $buffer, $matches, PREG_SET_ORDER))
	// It's case-insensitive, but always slower -- noticeably so with hundreds of blocks.

	// Don't waste time replacing blocks if there's none in the first place.
	if (!empty($context['blocks']) && strpos($buffer, '<we:') !== false)
	{
		// Case-sensitive version - you themers please don't use <We> or <WE> tags, or I'll tell your momma.
		while (strpos($buffer, '<we:') !== false)
		{
			$p = 0;
			while (($p = strpos($buffer, '<we:', $p)) !== false)
			{
				$space = strpos($buffer, ' ', $p);
				$gt = strpos($buffer, '>', $p);
				$code = substr($buffer, $p + 4, min($space, $gt) - $p - 4);
				$end_code = strpos($buffer, '</we:' . strtolower($code), $p + 4);
				$next_code = strpos($buffer, '<we:', $p + 4);

				if ($end_code === false)
					$end_code = strlen($buffer);

				// Did we find a block with no nested blocks?
				if ($next_code !== false && $end_code > $next_code)
				{
					$p += 4;
					continue;
				}

				// We don't like unknown blocks in this town.
				$block = isset($context['blocks'][$code]) ? $context['blocks'][$code] : array('has_if' => false, 'body' => '');
				$body = str_replace('{body}', substr($buffer, $gt + 1, $end_code - $gt - 1), $block['body']);

				// Has it got an <if:param> block?
				if ($block['has_if'])
				{
					preg_match_all('~([a-z][^\s="]*)="([^"]+)"~', substr($buffer, $p, $gt - $p), $params);

					// Remove <if> and its contents if the param is not used in the template. Otherwise, clean up the <if> tag...
					while (preg_match_all('~<if:([^>]+)>((?' . '>[^<]+|<(?!/?if:\\1>))*?)</if:\\1>~i', $body, $ifs, PREG_SET_ORDER))
						foreach ($ifs as $ifi)
							$body = str_replace($ifi[0], !empty($params) && in_array($ifi[1], $params[1]) ? $ifi[2] : '', $body);

					// ...And replace with the contents.
					if (!empty($params))
						foreach ($params[1] as $id => $param)
							$body = str_replace('{' . $param . '}', $params[2][$id], $body);
				}
				$buffer = str_replace(substr($buffer, $p, $end_code + strlen($code) + 6 - $p), $body, $buffer);
			}
		}
	}

	if (!empty($context['debugging_info']))
		$buffer = substr_replace($buffer, $context['debugging_info'], strrpos($buffer, '</body>'), 0);

	// Update the load times
	if (!empty($context['show_load_time']))
	{
		$new_load_time = microtime(true);
		$loadTime = $txt['page_created'] . sprintf($txt['seconds_with_' . ($db_count > 1 ? 'queries' : 'query')], $new_load_time - $time_start, $db_count);
		$queriesDiff = $db_count - $old_db_count;
		if ($user_info['is_admin'])
			$loadTime .= '</li>
			<li class="rd">(' . $txt['dynamic_replacements'] . ': ' . sprintf($txt['seconds_with_' . ($queriesDiff > 1 ? 'queries' : 'query')], $new_load_time - $old_load_time, $queriesDiff) . ')';
		$buffer = str_replace('<!-- insert stats here -->', $loadTime, $buffer);
	}

	// Return the changed buffer.
	return $buffer;
}

// Move inline events to the end
function wedge_event_delayer($match)
{
	global $context;
	static $eve = 1, $dupes = array();

	$eve_list = array();
	preg_match_all('~\son(\w+)="([^"]+)"~', $match[0], $insides, PREG_SET_ORDER);
	foreach ($insides as $inside)
	{
		$match[0] = str_replace($inside[0], '', $match[0]);
		$dupe = serialize($inside);
		if (!isset($dupes[$dupe]))
		{
			// Build the inline event array. Because inline events are more of a hassle to work with, we replace &quot; with
			// double quotes, because that's how " is shown in an inline event to avoid conflicting with the surrounding quotes.
			// !!! @todo: maybe &amp; should be turned into &, too.
			$context['delayed_events'][$eve] = array($eve, $inside[1], str_replace(array('&quot;', '\\\\n'), array('"', '\\n'), $inside[2]));
			$dupes[$dupe] = $eve;
			$eve_list[] = $eve++;
		}
		else
			$eve_list[] = $dupes[$dupe];
	}
	return rtrim($match[0], ' />') . ' data-eve="[' . implode(',', $eve_list) . ']">';
}

// Remove and save script tags
function pretty_scripts_remove($match)
{
	global $context;

	$context['pretty']['scriptID']++;
	$context['pretty']['scripts'][$context['pretty']['scriptID']] = $match[0];
	return chr(20) . $context['pretty']['scriptID'] . chr(20);
}

// A callback function to replace the buffer's URLs with their cached URLs
function pretty_buffer_callback($matches)
{
	global $cached_urls, $scripturl, $use_cache, $session_var;

	// Is this URL part of a feed?
	$isFeed = strpos($matches[1], '>') === false ? '"' : '';

	// Remove those annoying quotes
	$matches[2] = preg_replace('~^[\"\']|[\"\']$~', '', $matches[2]);

	// Store the parts of the URL that won't be cached so they can be inserted later
	if ($use_cache)
	{
		preg_match('~PHPSESSID=[^;#&]+~', $matches[2], $PHPSESSID);
		preg_match('~' . $session_var . '=[^;#]+~', $matches[2], $sesc);
		preg_match('~#.*~', $matches[2], $fragment);
		$url_id = preg_replace(array('~PHPSESSID=[^;#]+|' . $session_var . '=[^;#]+|#.*$~', '~"~', '~=?;+~', '~\?;~', '~[?;=]+$~'), array('', '%22', ';', '?', ''), $matches[2]);
	}
	else
	{
		preg_match('~#.*~', $matches[2], $fragment);
		// Rip out everything that won't have been cached
		$url_id = preg_replace(array('~#.*$~', '~"~', '~=?;+~', '~\?;~', '~[?;=]+$~'), array('', '%22', ';', '?', ''), $matches[2]);
	}

	// Stitch everything back together, clean it up and return
	$replacement = isset($cached_urls[$url_id]) ? $cached_urls[$url_id] : $url_id;
	$replacement .= (strpos($replacement, '?') === false ? '?' : ';') . (isset($PHPSESSID[0]) ? $PHPSESSID[0] : '') . ';' . (isset($sesc[0]) ? $sesc[0] : '') . (isset($fragment[0]) ? $fragment[0] : '');
	$replacement = preg_replace(array('~=?;+~', '~\?;~', '~[?;=]+#|&amp;#~', '~[?;=#]+$|&amp;$~'), array(';', '?', '#', ''), $replacement);

	if (empty($replacement) || $replacement[0] == '?')
		$replacement = $scripturl . $replacement;
	return $matches[1] . $isFeed . $replacement . $isFeed;
}

// Put the script tags back
function pretty_scripts_restore($match)
{
	global $context;

	return $context['pretty']['scripts'][(int) $match[1]];
}

/**
 * A quick alias to tell Wedge to hide sub-templates that don't belong to the main flow.
 *
 * @param array $layers An array with the layers we actually want to show. Usually empty.
 */
function hideChrome($layers = null)
{
	global $context;

	// Removing layers such as sidebar and top area.
	$context['template_layers'] = $layers === null ? array() : $layers;

	// Nothing to see here, sir.
	$context['hide_chrome'] = true;

	// This is a bit of a hack, but it's likely 'content' is linked
	// with 'sidebar', so we need to strip it down to the minimum.
	$context['blocks']['content'] = array('has_if' => false, 'body' => '{body}');
}

/**
 * Ensures content above the main page content is loaded, including HTTP page headers.
 *
 * Several things happen here.
 * - {@link setupThemeContext()} is called to get some key values.
 * - Issue HTTP headers that cause browser-side caching to be turned off (old expires and last modified). This is turned off for attachments errors, though.
 * - Issue MIME type header
 * - Step through the template layers from outermost, and ensure those happen.
 * - If using a conventional theme (with body or main layers), and the user is an admin, check whether certain files are present, and if so give the admin a warning. These include the installer, repair-settings and backups of the Settings files (with php~ extensions)
 * - If the user is post-banned, provide a nice warning for them.
 * - If the settings dictate it so, update the theme settings to use the default images and path.
 */
function template_header()
{
	global $txt, $modSettings, $context, $settings, $user_info, $boarddir, $cachedir;

	if (!isset($_REQUEST['xml']))
		setupThemeContext();

	// Print stuff to prevent caching of pages (except on attachment errors, etc.)
	if (empty($context['no_last_modified']))
	{
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

		if (!isset($_REQUEST['xml']) && !WIRELESS)
			header('Content-Type: text/html; charset=UTF-8');
	}

	header('Content-Type: text/' . (isset($_REQUEST['xml']) ? 'xml' : 'html') . '; charset=UTF-8');

	$checked_securityFiles = false;
	$showed_banned = false;
	$showed_behav_error = false;
	foreach ($context['template_layers'] as $layer)
	{
		execSubTemplate($layer . '_above', true);

		if ($layer !== 'main' && $layer !== 'body')
			continue;

		// May seem contrived, but this is done in case the body and main layer aren't there...
		// Was there a security error for the admin?
		if ($context['user']['is_admin'] && !empty($context['behavior_error']) && !$showed_behav_error)
		{
			$showed_behav_error = true;
			loadLanguage('Security');

			echo '
			<div class="errorbox">
				<p class="alert">!!</p>
				<h3>', $txt['behavior_admin'], '</h3>
				<p>', $txt[$context['behavior_error'] . '_log'], '</p>
			</div>';
		}
		elseif (allowedTo('admin_forum') && !$user_info['is_guest'] && !$checked_securityFiles)
		{
			$checked_securityFiles = true;
			$securityFiles = array('install.php', 'webinstall.php', 'upgrade.php', 'convert.php', 'repair_paths.php', 'repair_settings.php', 'Settings.php~', 'Settings_bak.php~');
			foreach ($securityFiles as $i => $securityFile)
			{
				if (!file_exists($boarddir . '/' . $securityFile))
					unset($securityFiles[$i]);
			}

			if (!empty($securityFiles) || (!empty($modSettings['cache_enable']) && !is_writable($cachedir)))
			{
				echo '
		<div class="errorbox">
			<p class="alert">!!</p>
			<h3>', empty($securityFiles) ? $txt['cache_writable_head'] : $txt['security_risk'], '</h3>
			<p>';

				foreach ($securityFiles as $securityFile)
				{
					echo '
				', $txt['not_removed'], '<strong>', $securityFile, '</strong>!<br>';

					if ($securityFile == 'Settings.php~' || $securityFile == 'Settings_bak.php~')
						echo '
				', sprintf($txt['not_removed_extra'], $securityFile, substr($securityFile, 0, -1)), '<br>';
				}

				if (!empty($modSettings['cache_enable']) && !is_writable($cachedir))
					echo '
				<strong>', $txt['cache_writable'], '</strong><br>';

				echo '
			</p>
		</div>';
			}
		}
		// If the user is banned from posting inform them of it.
		elseif (isset($_SESSION['ban']['cannot_post']) && !$showed_banned)
		{
			$showed_banned = true;
			echo '
				<div class="windowbg wrc alert" style="margin: 2ex; padding: 2ex; border: 2px dashed red">
					', sprintf($txt['you_are_post_banned'], $user_info['is_guest'] ? $txt['guest_title'] : $user_info['name']);

			if (!empty($_SESSION['ban']['cannot_post']['reason']))
				echo '
					<div style="padding-left: 4ex; padding-top: 1ex">', $_SESSION['ban']['cannot_post']['reason'], '</div>';

			if (!empty($_SESSION['ban']['expire_time']))
				echo '
					<div>', sprintf($txt['your_ban_expires'], timeformat($_SESSION['ban']['expire_time'], false)), '</div>';
			else
				echo '
					<div>', $txt['your_ban_expires_never'], '</div>';

			echo '
				</div>';
		}
	}

	if (isset($settings['use_default_images'], $settings['default_template']) && $settings['use_default_images'] == 'defaults')
	{
		$settings['theme_url'] = $settings['default_theme_url'];
		$settings['images_url'] = $settings['default_images_url'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}
}

/**
 * Shows the copyright notice.
 */
function theme_copyright()
{
	global $forum_copyright, $forum_version;

	// For SSI and other things, skip the version number.
	echo sprintf($forum_copyright, empty($forum_version) ? 'Wedge' : $forum_version);
}

/**
 * Ensure the content below the main content is loaded, i.e. the footer and including the copyright (and displaying a large warning if copyright has been hidden)
 *
 * Several things occur here.
 * - Load time and query count are moved into $context.
 * - Theme dirs and paths are re-established from the master values (as opposed to being modified through any other page)
 * - Template layers after the main content are executed in reverse order of the layers (deepest layer first)
 * - If not in SSI or wireless, and there were template layers, check the theme did display the copyright, and if not, displaying a big message and log this in the error log.
 */
function template_footer()
{
	global $context, $settings, $modSettings;

	$context['show_load_time'] = !empty($modSettings['timeLoadPageEnable']);

	if (isset($settings['use_default_images'], $settings['default_template']) && $settings['use_default_images'] == 'defaults')
	{
		$settings['theme_url'] = $settings['actual_theme_url'];
		$settings['images_url'] = $settings['actual_images_url'];
		$settings['theme_dir'] = $settings['actual_theme_dir'];
	}

	foreach (array_reverse($context['template_layers']) as $layer)
		execSubTemplate($layer . '_below', true);
}

/**
 * Display the debug data at the foot of the page if debug mode ($db_show_debug) is set to boolean true (only) and not in wireless or the query viewer page.
 *
 * Lots of interesting debug information is collated through workflow and displayed in this function, called from the footer.
 * - Check if the current user is on the list of people who can see the debug (and query debug) information, and clear information if not appropriate.
 * - Clean up a list of things that might not have been initialized this page, especially if heavily caching.
 * - Get the list of included files, and strip out the long paths to the board dir, replacing with a . for "current directory"; also collate the size of included files.
 * - Examine the DB query cache, and see if any warnings have been issued from queries.
 * - Grab the page content, and remove the trailing ending of body and html tags, so the footer information can replace them (and still leave legal HTML)
 * - Output the list of included templates, subtemplates, language files, properly included (through loadTemplate) stylesheets, and master list of files.
 * - If caching is enabled, also include the list of cache items included, how much data was loaded and how long was spent on caching retrieval.
 * - Additionally, if we already have a list of queries in session (i.e. the query list is expanded), display that too, stripping out ones that we can't send for EXPLAIN.
 * - Finally, clear cached language files.
 */
function db_debug_junk()
{
	global $context, $scripturl, $boarddir, $modSettings, $txt;
	global $db_cache, $db_count, $db_show_debug, $cache_count, $cache_hits;

	// Is debugging on? (i.e. it is set, and it is true, and we're not on action=viewquery or an help popup.
	$show_debug = (isset($db_show_debug) && $db_show_debug === true && (!isset($_GET['action']) || ($_GET['action'] != 'viewquery' && $_GET['action'] != 'help')) && !WIRELESS);
	// Check groups
	if (empty($modSettings['db_show_debug_who']) || $modSettings['db_show_debug_who'] == 'admin')
		$show_debug &= $context['user']['is_admin'];
	elseif ($modSettings['db_show_debug_who'] == 'mod')
		$show_debug &= allowedTo('moderate_forum');
	elseif ($modSettings['db_show_debug_who'] == 'regular')
		$show_debug &= $context['user']['is_logged'];
	else
		$show_debug &= ($modSettings['db_show_debug_who'] == 'any');

	// Now, who can see the query log? Need to have the ability to see any of this anyway.
	$show_debug_query = $show_debug;
	if (empty($modSettings['db_show_debug_who_log']) || $modSettings['db_show_debug_who_log'] == 'admin')
		$show_debug_query &= $context['user']['is_admin'];
	elseif ($modSettings['db_show_debug_who_log'] == 'mod')
		$show_debug_query &= allowedTo('moderate_forum');
	elseif ($modSettings['db_show_debug_who_log'] == 'regular')
		$show_debug_query &= $context['user']['is_logged'];
	else
		$show_debug_query &= ($modSettings['db_show_debug_who_log'] == 'any');

	// Now, let's tidy this up. If we're not showing queries, make sure anything that was logged is gone.
	if (!$show_debug_query)
	{
		unset($_SESSION['debug'], $db_cache);
		$_SESSION['view_queries'] = 0;
	}
	if (!$show_debug)
		return;

	if (empty($_SESSION['view_queries']))
		$_SESSION['view_queries'] = 0;
	if (empty($context['debug']['language_files']))
		$context['debug']['language_files'] = array();
	if (empty($context['debug']['sheets']))
		$context['debug']['sheets'] = array();

	$files = get_included_files();
	$total_size = 0;
	for ($i = 0, $n = count($files); $i < $n; $i++)
	{
		if (file_exists($files[$i]))
			$total_size += filesize($files[$i]);
		$files[$i] = strtr($files[$i], array($boarddir => '.'));
	}

	$warnings = 0;
	if (!empty($db_cache))
	{
		foreach ($db_cache as $q => $qq)
			if (!empty($qq['w']))
				$warnings += count($qq['w']);

		$_SESSION['debug'] = &$db_cache;
	}

	$temp = '
<div class="smalltext" style="text-align: left; margin: 1ex">
	' . $txt['debug_templates'] . count($context['debug']['templates']) . ': <em>' . implode(', ', $context['debug']['templates']) . '</em>.<br>
	' . $txt['debug_subtemplates'] . count($context['debug']['sub_templates']) . ': <em>' . implode(', ', $context['debug']['sub_templates']) . '</em>.<br>
	' . $txt['debug_language_files'] . count($context['debug']['language_files']) . ': <em>' . implode(', ', $context['debug']['language_files']) . '</em>.<br>
	' . $txt['debug_stylesheets'] . count($context['debug']['sheets']) . ': <em>' . implode(', ', $context['debug']['sheets']) . '</em>.<br>
	' . $txt['debug_files_included'] . count($files) . ' - ' . round($total_size / 1024) . $txt['debug_kb'] . ' (<a href="javascript:void(0)" onclick="$(\'#debug_include_info\').css(\'display\', \'inline\'); this.style.display = \'none\';">' . $txt['debug_show'] . '</a><span id="debug_include_info" style="display: none"><em>' . implode(', ', $files) . '</em></span>)<br>';

	if (!empty($modSettings['cache_enable']) && !empty($cache_hits))
	{
		$entries = array();
		$total_t = 0;
		$total_s = 0;
		foreach ($cache_hits as $cache_hit)
		{
			$entries[] = $cache_hit['d'] . ' ' . $cache_hit['k'] . ': ' . sprintf($txt['debug_cache_seconds_bytes'], comma_format($cache_hit['t'], 5), $cache_hit['s']);
			$total_t += $cache_hit['t'];
			$total_s += $cache_hit['s'];
		}

		$temp .= '
	' . $txt['debug_cache_hits'] . $cache_count . ': ' . sprintf($txt['debug_cache_seconds_bytes_total'], comma_format($total_t, 5), comma_format($total_s)) . ' (<a href="javascript:void(0)" onclick="$(\'#debug_cache_info\').css(\'display\', \'inline\'); this.style.display = \'none\';">' . $txt['debug_show'] . '</a><span id="debug_cache_info" style="display: none"><em>' . implode(', ', $entries) . '</em></span>)<br>';
	}

	if ($show_debug_query)
		$temp .= '
	<a href="' . $scripturl . '?action=viewquery" target="_blank" class="new_win">' . ($warnings == 0 ? sprintf($txt['debug_queries_used'], (int) $db_count) : sprintf($txt['debug_queries_used_and_warnings'], (int) $db_count, $warnings)) . '</a><br>
	<br>';
	else
		$temp .= '
	' . sprintf($txt['debug_queries_used'], (int) $db_count) . '<br>
	<br>';

	if ($_SESSION['view_queries'] == 1 && !empty($db_cache))
		foreach ($db_cache as $q => $qq)
		{
			$is_select = substr(trim($qq['q']), 0, 6) == 'SELECT' || preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+SELECT .+$~s', trim($qq['q'])) != 0;
			// Temporary tables created in earlier queries are not explainable.
			if ($is_select)
			{
				foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
					if (strpos(trim($qq['q']), $tmp) !== false)
					{
						$is_select = false;
						break;
					}
			}
			// But actual creation of the temporary tables are.
			elseif (preg_match('~^CREATE TEMPORARY TABLE .+?SELECT .+$~s', trim($qq['q'])) != 0)
				$is_select = true;

			// Make the filenames look a bit better.
			if (isset($qq['f']))
				$qq['f'] = preg_replace('~^' . preg_quote($boarddir, '~') . '~', '...', $qq['f']);

			$temp .= '
	<strong>' . ($is_select ? '<a href="' . $scripturl . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '" target="_blank" class="new_win" style="text-decoration: none">' : '') . westr::nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', htmlspecialchars(ltrim($qq['q'], "\n\r")))) . ($is_select ? '</a></strong>' : '</strong>') . '<br>
	&nbsp;&nbsp;&nbsp;';
			if (!empty($qq['f']) && !empty($qq['l']))
				$temp .= sprintf($txt['debug_query_in_line'], $qq['f'], $qq['l']);

			if (isset($qq['s'], $qq['t'], $txt['debug_query_which_took_at']))
				$temp .= sprintf($txt['debug_query_which_took_at'], round($qq['t'], 8), round($qq['s'], 8)) . '<br>';
			elseif (isset($qq['t']))
				$temp .= sprintf($txt['debug_query_which_took'], round($qq['t'], 8)) . '<br>';
			$temp .= '
	<br>';
		}

	if ($show_debug_query)
		$temp .= '
	<a href="' . $scripturl . '?action=viewquery;sa=hide">' . $txt['debug_' . (empty($_SESSION['view_queries']) ? 'show' : 'hide') . '_queries'] . '</a>';

	$context['debugging_info'] = $temp . '
</div>';
}

/**
 * Log changes in the state of the forum, such as moderation events or administrative changes.
 *
 * @param string $action A code for the report; a list of such strings can be found in Modlog.{language}.php (modlog_ac_ strings)
 * @param array $extra An associated array of parameters for the item being logged. Typically this will include 'topic' for the topic's id.
 * @param string $log_type A string reflecting the type of log, moderate for moderation actions (e.g. thread changes), admin for administrative actions.
 */
function logAction($action, $extra = array(), $log_type = 'moderate')
{
	global $modSettings, $user_info;

	$log_types = array(
		'moderate' => 1,
		'user' => 2,
		'admin' => 3,
	);

	if (!is_array($extra))
		trigger_error('logAction(): data is not an array with action \'' . $action . '\'', E_USER_NOTICE);

	// Pull out the parts we want to store separately, but also make sure that the data is proper
	if (isset($extra['topic']))
	{
		if (!is_numeric($extra['topic']))
			trigger_error('logAction(): data\'s topic is not a number', E_USER_NOTICE);
		$topic_id = empty($extra['topic']) ? '0' : (int)$extra['topic'];
		unset($extra['topic']);
	}
	else
		$topic_id = '0';

	if (isset($extra['message']))
	{
		if (!is_numeric($extra['message']))
			trigger_error('logAction(): data\'s message is not a number', E_USER_NOTICE);
		$msg_id = empty($extra['message']) ? '0' : (int)$extra['message'];
		unset($extra['message']);
	}
	else
		$msg_id = '0';

	// Is there an associated report on this?
	if (in_array($action, array('move', 'remove', 'split', 'merge')))
	{
		$request = wesql::query('
			SELECT id_report
			FROM {db_prefix}log_reported
			WHERE {raw:column_name} = {int:reported}
			LIMIT 1',
			array(
				'column_name' => !empty($msg_id) ? 'id_msg' : 'id_topic',
				'reported' => !empty($msg_id) ? $msg_id : $topic_id,
		));

		// Alright, if we get any result back, update open reports.
		if (wesql::num_rows($request) > 0)
		{
			loadSource('ModerationCenter');
			updateSettings(array('last_mod_report_action' => time()));
			recountOpenReports();
		}
		wesql::free_result($request);
	}

	// No point in doing anything else, if the log isn't even enabled.
	if (empty($modSettings['modlog_enabled']) || !isset($log_types[$log_type]))
		return false;

	if (isset($extra['member']) && !is_numeric($extra['member']))
		trigger_error('logAction(): data\'s member is not a number', E_USER_NOTICE);

	if (isset($extra['board']))
	{
		if (!is_numeric($extra['board']))
			trigger_error('logAction(): data\'s board is not a number', E_USER_NOTICE);
		$board_id = empty($extra['board']) ? '0' : (int)$extra['board'];
		unset($extra['board']);
	}
	else
		$board_id = '0';

	if (isset($extra['board_to']))
	{
		if (!is_numeric($extra['board_to']))
			trigger_error('logAction(): data\'s board_to is not a number', E_USER_NOTICE);
		if (empty($board_id))
		{
			$board_id = empty($extra['board_to']) ? '0' : (int)$extra['board_to'];
			unset($extra['board_to']);
		}
	}

	wesql::insert('',
		'{db_prefix}log_actions',
		array(
			'log_time' => 'int', 'id_log' => 'int', 'id_member' => 'int', 'ip' => 'int', 'action' => 'string',
			'id_board' => 'int', 'id_topic' => 'int', 'id_msg' => 'int', 'extra' => 'string-65534',
		),
		array(
			time(), $log_types[$log_type], $user_info['id'], get_ip_identifier($user_info['ip']), $action,
			$board_id, $topic_id, $msg_id, serialize($extra),
		),
		array('id_action')
	);

	return wesql::insert_id();
}

/**
 * Track changes in statistics of the forum, through the life of the page, and commit them at the end of page generation.
 *
 * The stats array passed to this function is a key/value pair, the value may well be a '+' to indicate an increment of a field, otherwise it will be treated as the real value.
 *
 * @param mixed $stats Nominally a key/value pair array, listing one or more changes to master stats, in the log_activity table. Submit boolean false to flush changes to the table.
 * @return bool As to whether the changes are logged and flushed, or whether they are not being processed.
 */
function trackStats($stats = array())
{
	global $modSettings;
	static $cache_stats = array();

	if (empty($modSettings['trackStats']))
		return false;
	if (!empty($stats))
		return $cache_stats = array_merge($cache_stats, $stats);
	elseif (empty($cache_stats))
		return false;

	$setStringUpdate = '';
	$insert_keys = array();
	$date = strftime('%Y-%m-%d', forum_time(false));
	$update_parameters = array(
		'current_date' => $date,
	);
	foreach ($cache_stats as $field => $change)
	{
		$setStringUpdate .= '
			' . $field . ' = ' . ($change === '+' ? $field . ' + 1' : '{int:' . $field . '}') . ',';

		if ($change === '+')
			$cache_stats[$field] = 1;
		else
			$update_parameters[$field] = $change;
		$insert_keys[$field] = 'int';
	}

	wesql::query('
		UPDATE {db_prefix}log_activity
		SET' . substr($setStringUpdate, 0, -1) . '
		WHERE date = {date:current_date}',
		$update_parameters
	);
	if (wesql::affected_rows() == 0)
	{
		wesql::insert('ignore',
			'{db_prefix}log_activity',
			array_merge($insert_keys, array('date' => 'date')),
			array_merge($cache_stats, array($date)),
			array('date')
		);
	}

	// Don't do this again.
	$cache_stats = array();

	return true;
}

/**
 * Attempt to check whether a given user has been carrying out specific actions repeatedly, faster than a given frequency.
 *
 * Different actions take different periods of time. Each action also has a fatal message when triggered (and suspends execution), and the messages are based on the action, suffixed with 'WaitTime_broken' and which are specified in Errors.{language}.php.
 *
 * @param string $error_type The action whose frequency is being checked.
 */
function spamProtection($error_type)
{
	global $modSettings, $txt, $user_info;

	// Certain types take less/more time.
	$timeOverrides = array(
		'login' => 2,
		'register' => 2,
		'sendtopc' => $modSettings['spamWaitTime'] * 4,
		'sendmail' => $modSettings['spamWaitTime'] * 5,
		'report' => $modSettings['spamWaitTime'] * 4,
		'search' => !empty($modSettings['search_floodcontrol_time']) ? $modSettings['search_floodcontrol_time'] : 1,
	);

	// Moderators are free...
	if (!allowedTo('moderate_board'))
		$timeLimit = isset($timeOverrides[$error_type]) ? $timeOverrides[$error_type] : $modSettings['spamWaitTime'];
	else
		$timeLimit = 2;

	// Delete old entries...
	wesql::query('
		DELETE FROM {db_prefix}log_floodcontrol
		WHERE log_time < {int:log_time}
			AND log_type = {string:log_type}',
		array(
			'log_time' => time() - $timeLimit,
			'log_type' => $error_type,
		)
	);

	// Add a new entry, deleting the old if necessary.
	wesql::insert('replace',
		'{db_prefix}log_floodcontrol',
		array('ip' => 'int', 'log_time' => 'int', 'log_type' => 'string'),
		array(get_ip_identifier($user_info['ip']), time(), $error_type),
		array('ip', 'log_type')
	);

	// If affected is 0 or 2, it was there already.
	if (wesql::affected_rows() != 1)
	{
		// Spammer! You only have to wait a *few* seconds!
		fatal_lang_error($error_type . 'WaitTime_broken', false, array($timeLimit));
		return true;
	}

	// They haven't posted within the limit.
	return false;
}

/**
 * Determines whether an email address is malformed or not.
 */
function is_valid_email($email)
{
	return preg_match('~^[\w=+/-][\w=\'+/.-]*@[\w-]+(\.[\w-]+)*(\.\w{2,6})$~', $email);
}

/**
 * Gets the size of an image specified by a URL.
 *
 * Notes:
 * - Used for remote avatars that aren't downloaded, regular images (to check they're not oversized), signature images and so on.
 * - Attempts to use getimagesize with the provided URL bare if no match to a conventional URL fails (i.e. no protocol listed)
 * - If a protocol is listed, attempt to connect to the server normally (half second timeout), and send an HTTP HEAD request to establish the file exists. If so, do a second request to actually get the data and pipe it into imagecreatefromstring() to be able to assess it.
 * - If this took more than 0.8 seconds in total, cache the result.
 *
 * @param string $url A URL presumably containing an image, whose dimensions are requested.
 * @return mixed Returns false if not able to obtain the image (either unknown format, or file not found), or an indexed array of (x,y) dimensions.
 */
function url_image_size($url)
{
	// Make sure it is a proper URL.
	$url = str_replace(' ', '%20', $url);

	// Can we pull this from the cache... please please?
	if (($temp = cache_get_data('url_image_size-' . md5($url), 240)) !== null)
		return $temp;
	$t = microtime(true);

	// Get the host to pester...
	preg_match('~^\w+://(.+?)/(.*)$~', $url, $match);

	// Can't figure it out, just try the image size.
	if ($url == '' || $url === 'http://' || $url === 'https://')
		return false;
	elseif (!isset($match[1]))
		$size = @getimagesize($url);
	else
	{
		// Try to connect to the server... give it half a second.
		$temp = 0;
		$fp = @fsockopen($match[1], 80, $temp, $temp, 0.5);

		// Successful? Continue...
		if ($fp != false)
		{
			// Send the HEAD request (since we don't have to worry about chunked, HTTP/1.1 is fine here.)
			fwrite($fp, 'HEAD /' . $match[2] . ' HTTP/1.1' . "\r\n" . 'Host: ' . $match[1] . "\r\n" . 'User-Agent: PHP/SMF' . "\r\n" . 'Connection: close' . "\r\n\r\n");

			// Read in the HTTP/1.1 or whatever.
			$test = substr(fgets($fp, 11), -1);
			fclose($fp);

			// See if it returned a 404/403 or something.
			if ($test < 4)
			{
				$size = @getimagesize($url);

				// This probably means allow_url_fopen is off, let's try GD.
				if ($size === false && function_exists('imagecreatefromstring'))
				{
					loadSource('Subs-Package');

					// It's going to hate us for doing this, but another request...
					$image = @imagecreatefromstring(fetch_web_data($url));
					if ($image !== false)
					{
						$size = array(imagesx($image), imagesy($image));
						imagedestroy($image);
					}
				}
			}
		}
	}

	// If we didn't get it, we failed.
	if (!isset($size))
		$size = false;

	// If this took a long time, we may never have to do it again, but then again we might...
	if (microtime(true) - $t > 0.8)
		cache_put_data('url_image_size-' . md5($url), $size, 240);

	// Didn't work.
	return $size;
}

/**
 * Begin to prepare $context for the theme.
 *
 * Several operations are performed:
 * - Prevent multiple runs of the function unless necessary.
 * - Check whether in maintenance.
 * - Prepare the current time, current action and whether to show quick login to guests (for the theme to optionally display).
 * - Prepare the news items (load, split from the one entry in the admin option, randomize the other).
 * - Get various user details (or defaults for guests) such as number of PMs, avatar.
 * - Call {@link setupMenuContext()} to load the main menu.
 * - Load the JavaScript if we need that to resize the user information area's instance of the avatar.
 * - Load a few details about the latest member and current forum-wide stats.
 * - Set the page title and meta keywords.
 */
function setupThemeContext($forceload = false)
{
	global $modSettings, $user_info, $scripturl, $context, $settings, $options, $txt, $maintenance;
	global $user_settings;
	static $loaded = false;

	// Under SSI this function can be called more then once. That can cause some problems.
	// So only run the function once unless we are forced to run it again.
	if ($loaded && !$forceload)
		return;

	$loaded = true;

	$context['in_maintenance'] = !empty($maintenance);
	$context['current_time'] = timeformat(time(), false);
	$context['current_action'] = isset($_GET['action']) ? $_GET['action'] : '';
	$context['show_quick_login'] = !empty($modSettings['enableVBStyleLogin']) && $user_info['is_guest'];

	// Get some news...
	$context['news_lines'] = cache_quick_get('news_lines', 'ManageNews.php', 'cache_getNews', array());
	$context['fader_news_lines'] = array();
	// Gotta be special for the javascript.
	for ($i = 0, $n = count($context['news_lines']); $i < $n; $i++)
		$context['fader_news_lines'][$i] = strtr(addslashes($context['news_lines'][$i]), array('/' => '\/', '<a href=' => '<a hre" + "f='));

	$context['random_news_line'] = $context['news_lines'][mt_rand(0, count($context['news_lines']) - 1)];

	if (!$user_info['is_guest'])
	{
		$context['user']['messages'] = &$user_info['messages'];
		$context['user']['unread_messages'] = &$user_info['unread_messages'];

		// Personal message popup...
		if ($user_info['unread_messages'] > (isset($_SESSION['unread_messages']) ? $_SESSION['unread_messages'] : 0))
			$context['user']['popup_messages'] = true;
		else
			$context['user']['popup_messages'] = false;
		$_SESSION['unread_messages'] = $user_info['unread_messages'];

		if (allowedTo('moderate_forum'))
			$context['unapproved_members'] = (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 2) || !empty($modSettings['approveAccountDeletion']) ? $modSettings['unapprovedMembers'] : 0;
		$context['show_open_reports'] = empty($user_settings['mod_prefs']) || $user_settings['mod_prefs'][0] == 1;

		$context['user']['avatar'] = array();

		// Figure out the avatar... uploaded?
		if ($user_info['avatar']['url'] == '' && !empty($user_info['avatar']['id_attach']))
			$context['user']['avatar']['href'] = $user_info['avatar']['custom_dir'] ? $modSettings['custom_avatar_url'] . '/' . $user_info['avatar']['filename'] : $scripturl . '?action=dlattach;attach=' . $user_info['avatar']['id_attach'] . ';type=avatar';
		// Full URL?
		elseif (strpos($user_info['avatar']['url'], 'http://') === 0)
		{
			$context['user']['avatar']['href'] = $user_info['avatar']['url'];

			if ($modSettings['avatar_action_too_large'] == 'option_html_resize' || $modSettings['avatar_action_too_large'] == 'option_js_resize')
			{
				if (!empty($modSettings['avatar_max_width_external']))
					$context['user']['avatar']['width'] = $modSettings['avatar_max_width_external'];
				if (!empty($modSettings['avatar_max_height_external']))
					$context['user']['avatar']['height'] = $modSettings['avatar_max_height_external'];
			}
		}
		// Gravatar?
		elseif (strpos($user_info['avatar']['url'], 'gravatar://') === 0)
		{
			if ($user_info['avatar']['url'] === 'gravatar://' || empty($modSettings['gravatarAllowExtraEmail']))
				$context['user']['avatar']['href'] = get_gravatar_url($user_info['email']);
			else
				$context['user']['avatar']['href'] = get_gravatar_url(substr($user_info['avatar']['url'], 11));

			if (!empty($modSettings['avatar_max_width_external']))
				$context['user']['avatar']['width'] = $modSettings['avatar_max_width_external'];
			if (!empty($modSettings['avatar_max_height_external']))
				$context['user']['avatar']['height'] = $modSettings['avatar_max_height_external'];
		}
		// Otherwise we assume it's server stored?
		elseif ($user_info['avatar']['url'] != '')
			$context['user']['avatar']['href'] = $modSettings['avatar_url'] . '/' . htmlspecialchars($user_info['avatar']['url']);

		if (!empty($context['user']['avatar']))
			$context['user']['avatar']['image'] = '<img src="' . $context['user']['avatar']['href'] . '"' . (isset($context['user']['avatar']['width']) ? ' width="' . $context['user']['avatar']['width'] . '"' : '') . (isset($context['user']['avatar']['height']) ? ' height="' . $context['user']['avatar']['height'] . '"' : '') . ' class="avatar">';

		// Figure out how long they've been logged in.
		$context['user']['total_time_logged_in'] = array(
			'days' => floor($user_info['total_time_logged_in'] / 86400),
			'hours' => floor(($user_info['total_time_logged_in'] % 86400) / 3600),
			'minutes' => floor(($user_info['total_time_logged_in'] % 3600) / 60)
		);
	}
	else
	{
		$context['user']['messages'] = 0;
		$context['user']['unread_messages'] = 0;
		$context['user']['avatar'] = array();
		$context['user']['total_time_logged_in'] = array('days' => 0, 'hours' => 0, 'minutes' => 0);
		$context['user']['popup_messages'] = false;

		if (!empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1)
			$txt['welcome_guest'] .= $txt['welcome_guest_activate'];

		// If we've upgraded recently, go easy on the passwords.
		if (!empty($modSettings['disableHashTime']) && ($modSettings['disableHashTime'] == 1 || time() < $modSettings['disableHashTime']))
			$context['disable_login_hashing'] = true;
	}

	// Setup the main menu items.
	setupMenuContext();

	// This is done to allow theme authors to customize it as they want.
	$context['show_pm_popup'] = $context['user']['popup_messages'] && !empty($options['popup_messages']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'pm');

	// Resize avatars the fancy, but non-GD requiring way.
	if ($modSettings['avatar_action_too_large'] == 'option_js_resize' && (!empty($modSettings['avatar_max_width_external']) || !empty($modSettings['avatar_max_height_external'])))
		add_js('
	var smf_avatarMaxWidth = ' . (int) $modSettings['avatar_max_width_external'] . ';
	var smf_avatarMaxHeight = ' . (int) $modSettings['avatar_max_height_external'] . ';
	$(window).load(smf_avatarResize);');

	// This looks weird, but it's because BoardIndex.php references the variable.
	$context['common_stats']['latest_member'] = array(
		'id' => $modSettings['latestMember'],
		'name' => $modSettings['latestRealName'],
		'href' => $scripturl . '?action=profile;u=' . $modSettings['latestMember'],
		'link' => '<a href="' . $scripturl . '?action=profile;u=' . $modSettings['latestMember'] . '">' . $modSettings['latestRealName'] . '</a>',
	);
	$context['common_stats'] = array(
		'total_posts' => comma_format($modSettings['totalMessages']),
		'total_topics' => comma_format($modSettings['totalTopics']),
		'total_members' => comma_format($modSettings['totalMembers']),
		'latest_member' => $context['common_stats']['latest_member'],
	);

	if (!isset($context['page_title']))
		$context['page_title'] = '';

	// Set some specific vars.
	$context['page_title_html_safe'] = westr::htmlspecialchars(un_htmlspecialchars($context['page_title']));
	$context['meta_keywords'] = !empty($modSettings['meta_keywords']) ? westr::htmlspecialchars($modSettings['meta_keywords']) : '';
}

/**
 * Establish the full encrypted filename where the details are specified in the database (for serving attachments). This should be used in preference to the legacy system; the filenames used by this system are more secure than the legacy function by ensuring there is a non-trivially-guessable component in the filename.
 *
 * @param string $filename The original unedited filename of the file to be served.
 * @param int $attachment_id The numeric attachment id, which forms part of the attachment filename.
 * @param mixed $dir If using multiple attachment folders, this should be set to the folder id.
 * @param bool $new If true (this is a new file being attached), generate and return the hash that should subsequently be used.
 * @param string $file_hash The file hash previously generated, which forms part of the attachment filename.
 * @return string The full path to the file that contains the stated attachment.
 */
function getAttachmentFilename($filename, $attachment_id, $dir = null, $new = false, $file_hash = '')
{
	global $modSettings;

	// Just make up a nice hash...
	if ($new)
		return sha1(md5($filename . time()) . mt_rand());

	// Grab the file hash if it wasn't added.
	if ($file_hash === '')
	{
		$request = wesql::query('
			SELECT file_hash
			FROM {db_prefix}attachments
			WHERE id_attach = {int:id_attach}',
			array(
				'id_attach' => $attachment_id,
		));

		if (wesql::num_rows($request) === 0)
			return false;

		list ($file_hash) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	// In case of files from the old system, do a legacy call.
	if (empty($file_hash))
		return getLegacyAttachmentFilename($filename, $attachment_id, $dir, $new);

	// Are we using multiple directories?
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
		$path = $modSettings['attachmentUploadDir'][$dir];
	}
	else
		$path = $modSettings['attachmentUploadDir'];

	return $path . '/' . $attachment_id . '_' . $file_hash . '.ext';
}

/**
 * Older versions of the application used a method to convert filenames of attachments in to a safer form.
 *
 * - Accented characters are converted to filesystem safe versions.
 * - Extended characters (dual characters in a single glyph) are converted to their ANSI equivalents.
 * - Remove characters other than letters or other word characters, and replace . with _
 * - Form the encrypted filename out of attachment id, the cleaned filename and the MD5 hash of the filename.
 *
 * @param string $filename The original filename, as it was originally uploaded (and stored in the database)
 * @param mixed $attachment_id If using encrypted filenames, the attachment id is required as it forms part of the filename. Otherwise it is not required and simply can be submitted as false.
 * @param mixed $dir If using multiple attachment folders, the id of the folder.
 * @param bool $new Submit true if using a newer attachment, or encrypted filenames are enabled.
 * @todo This must be removed at some point because it's a blocker on UTF-8 purity.
 */
function getLegacyAttachmentFilename($filename, $attachment_id, $dir = null, $new = false)
{
	global $modSettings, $db_character_set;

	// Remove international characters (windows-1252)
	// !!! These lines should never be needed again. Still, behave.
	if (empty($db_character_set) || $db_character_set != 'utf8')
	{
		$filename = strtr($filename,
			"\x8a\x8e\x9a\x9e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd1\xd2\xd3\xd4\xd5\xd6\xd8\xd9\xda\xdb\xdc\xdd\xe0\xe1\xe2\xe3\xe4\xe5\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xff",
			'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
		$filename = strtr($filename, array("\xde" => 'TH', "\xfe" =>
			'th', "\xd0" => 'DH', "\xf0" => 'dh', "\xdf" => 'ss', "\x8c" => 'OE',
			"\x9c" => 'oe', "\c6" => 'AE', "\xe6" => 'ae', "\xb5" => 'u'));
	}

	// Sorry, no spaces, dots, or anything else but letters allowed.
	$clean_name = preg_replace(array('/\s/', '/[^\w.-]/'), array('_', ''), $filename);

	$enc_name = $attachment_id . '_' . strtr($clean_name, '.', '_') . md5($clean_name);
	$clean_name = preg_replace('~\.{2,}~', '.', $clean_name);

	if ($attachment_id == false || ($new && empty($modSettings['attachmentEncryptFilenames'])))
		return $clean_name;
	elseif ($new)
		return $enc_name;

	// Are we using multiple directories?
	if (!empty($modSettings['currentAttachmentUploadDir']))
	{
		if (!is_array($modSettings['attachmentUploadDir']))
			$modSettings['attachmentUploadDir'] = unserialize($modSettings['attachmentUploadDir']);
		$path = $modSettings['attachmentUploadDir'][$dir];
	}
	else
		$path = $modSettings['attachmentUploadDir'];

	return $path . '/' . (file_exists($path . '/' . $enc_name) ? $enc_name : $clean_name);
}

/**
 * Converts a single IP string in SMF terms into an array showing ranges, which is suitable for database use.
 *
 * @param string $fullip A string in SMF format representing a single IP address, a range or wildcard (e.g. 127.0.0.1, 127.0.0.10-20 or 127.0.0.*) - if 'unknown' is passed, the effective IP address will be 255.255.255.255.
 * @return array An array of 4 elements, representing each dotted number. Each element consists of a subarray, with 'low' and 'high' elements showing the limits of the range. For a single IP dot component, these will be the same (1 in the 127.0.0.1 example); for a range it will be the lower and upper bounds (10 and 20 respectively for 127.0.0.10-20); for a wildcard it will use 0-255 instead of the *.
 */
function ip2range($fullip)
{
	// Pretend that 'unknown' is 255.255.255.255. (since that can't be an IP anyway.)
	if ($fullip == 'unknown')
		$fullip = '255.255.255.255';

	$ip_parts = explode('.', $fullip);
	$ip_array = array();

	if (count($ip_parts) != 4)
		return array();

	for ($i = 0; $i < 4; $i++)
	{
		if ($ip_parts[$i] == '*')
			$ip_array[$i] = array('low' => '0', 'high' => '255');
		elseif (preg_match('/^(\d{1,3})\-(\d{1,3})$/', $ip_parts[$i], $range) == 1)
			$ip_array[$i] = array('low' => $range[1], 'high' => $range[2]);
		elseif (is_numeric($ip_parts[$i]))
			$ip_array[$i] = array('low' => $ip_parts[$i], 'high' => $ip_parts[$i]);
	}

	return $ip_array;
}

/**
 * Matches IPv4 addresses against an IP range specified in CIDR format.
 *
 * Bots invariably occupy IP ranges; this allows us to specify netblocks to exclude that are more in line with the sorts of behavior we will be checking for.
 *
 * @param string $ip A regular IP address in dotted notation (127.0.0.1)
 * @param mixed $cidr_block A single IP in netblock format as a string, or an array of similar (e.g. 10.0.0.0/8)
 * @return bool Whether the individual CIDR netblock matched or not (can be recursive)
 */
function match_cidr($ip, $cidr_block)
{
	if (is_array($cidr_block))
	{
		foreach ($cidr_block as $cidr)
			if (match_cidr($ip, $cidr))
				return true;
	}
	else
	{
		if (strpos($cidr_block, '/') === false)
			$cidr_block .= '/32';

		list ($cidr_ip, $mask) = explode('/', $cidr_block);
		$mask = pow(2, 32) - pow(2, 32 - $mask);
		return (ip2long($ip) & $mask) === (ip2long($cidr_ip) & $mask);
	}
	return false;
}

/**
 * Attempts to look up the hostname from a given IP address.
 *
 * Multiple steps are taken in the pursuit of a hostname.
 * - Load from cache if the IP address has been looked up in the last 5 minutes, and was previously slow
 * - On Linux, attempt to call the 'host' command with shell_exec
 * - On Windows and specific Unix configurations, attempt to call 'nslookup' with shell_exec
 * - Failing those, call {@link gethostbyaddr()}
 * - If slow, cache the result
 *
 * @param string $ip A single IP address, normally formatted.
 * @return string If possible, the hostname associated with that IP address, or empty string if that was not possible.
 */
function host_from_ip($ip)
{
	global $modSettings;

	if (($host = cache_get_data('hostlookup-' . $ip, 600)) !== null)
		return $host;
	$t = microtime(true);

	// This should work for IPv4, its status is unknown for IPv6 however.
	if (preg_match('~\d{2,3}(\.\d{1,3}){3}~', $ip) && !isset($host) && is_callable('dns_get_record'))
	{
		$details = dns_get_record(implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa', DNS_PTR);
        if (!empty($details[0]['target']))
			$host = $details[0]['target'];
	}

	// Try the Linux host command, perhaps?
	if (!isset($host) && (strpos(strtolower(PHP_OS), 'win') === false || strpos(strtolower(PHP_OS), 'darwin') !== false) && mt_rand(0, 1) == 1)
	{
		if (!isset($modSettings['host_to_dis']))
			$test = @shell_exec('host -W 1 ' . @escapeshellarg($ip));
		else
			$test = @shell_exec('host ' . @escapeshellarg($ip));

		// Did host say it didn't find anything?
		if (strpos($test, 'not found') !== false)
			$host = '';
		// Invalid server option?
		elseif ((strpos($test, 'invalid option') || strpos($test, 'Invalid query name 1')) && !isset($modSettings['host_to_dis']))
			updateSettings(array('host_to_dis' => 1));
		// Maybe it found something, after all?
		elseif (preg_match('~\s([^\s]+?)\.\s~', $test, $match) == 1)
			$host = $match[1];
	}

	// This is nslookup; usually only Windows, but possibly some Unix?
	if (!isset($host) && strpos(strtolower(PHP_OS), 'win') !== false && strpos(strtolower(PHP_OS), 'darwin') === false && mt_rand(0, 1) == 1)
	{
		$test = @shell_exec('nslookup -timeout=1 ' . @escapeshellarg($ip));
		if (strpos($test, 'Non-existent domain') !== false)
			$host = '';
		elseif (preg_match('~Name:\s+([^\s]+)~', $test, $match) == 1)
			$host = $match[1];
	}

	// This is the last try :/.
	if (!isset($host) || $host === false)
		$host = @gethostbyaddr($ip);

	// It took a long time, so let's cache it!
	if (microtime(true) - $t > 0.5)
		cache_put_data('hostlookup-' . $ip, $host, 600);

	return $host;
}

/**
 * Compares a given IP address and a domain to validate that the IP address belongs to that domain.
 *
 * Given an IP address, look up the associated fully-qualified domain, validate the supplied domain contains the FQDN, then request a list of IPs that belong to that domain to validate they tie up. (It is a method to validate that an IP address belongs to a given parent domain)
 *
 * @param string $ip An IPv4 dotted-format IP address.
 * @param string $domain A top level domain name to validate relationship to IP address (e.g. domain.com)
 * @return bool Whether the IP address could be validated as being related to that domain.
 * @todo DNS failure causes a general failure in this check. Fix this!
 */
function test_ip_host($ip, $domain)
{
	// !!! DNS failure cannot be adequately detected due to a PHP bug. Until a solution is found, forcibly override this check.
	return true;

	$host = host_from_ip($ip);
	$host_result = strpos(strrev($host), strrev($domain));
	if ($host_result === false || $host_result > 0)
		return false; // either the (reversed) FQDN didn't match the (reversed) supplied parent domain, or it didn't match at the end of the name
	$addrs = gethostbynamel($host);
	return in_array($ip, $addrs);
}

/**
 * Breaks a string up into word-units, primarily for the purposes of searching and related code.
 *
 * This function is used surprisingly often, not only for the actual business of searching, but also maintaining custom indexes on the text too.
 *
 * @param string $text The original text that is to be processed. Assumed to be from a post or other case where entities will be present.
 * @param mixed $max_chars When $encrypt is true, this is the maximum number of bytes to use in the integer hashes for each word (typically 2-4); when $encrypt is false, the maximum number of letters in each 'word', null for no limit.
 * @param bool $encrypt Whether to hash the words into integer hashes or not. This is off by default; it is only used for custom indexes, other search methods do not normally require this to be provided to them.
 * @return array Returns an array of strings (if $encrypt is false) or an array of integers (if $encrypt is true) representing the unique words found in the source $text.
 */
function text2words($text, $max_chars = 20, $encrypt = false)
{
	global $context;

	// Step 1: Remove entities/things we don't consider words:
	$words = preg_replace('~(?:[\x0B\0\x{A0}\t\r\s\n(){}\\[\\]<>!@$%^*.,:+=`\~?/\\\\]+|&(?:amp|lt|gt|quot);)+~u', ' ', strtr($text, array('<br>' => ' ')));

	// Step 2: Entities we left to letters, where applicable, lowercase.
	$words = un_htmlspecialchars(westr::strtolower($words));

	// Step 3: Ready to split apart and index!
	$words = explode(' ', $words);

	if ($encrypt)
	{
		$possible_chars = array_flip(array_merge(range(46, 57), range(65, 90), range(97, 122)));
		$returned_ints = array();
		foreach ($words as $word)
		{
			if (($word = trim($word, '-_\'')) !== '')
			{
				$encrypted = substr(crypt($word, 'uk'), 2, $max_chars);
				$total = 0;
				for ($i = 0; $i < $max_chars; $i++)
					$total += $possible_chars[ord($encrypted{$i})] * pow(63, $i);
				$returned_ints[] = $max_chars == 4 ? min($total, 16777215) : $total;
			}
		}
		return array_unique($returned_ints);
	}
	else
	{
		// Trim characters before and after and add slashes for database insertion.
		$returned_words = array();
		foreach ($words as $word)
			if (($word = trim($word, '-_\'')) !== '')
				$returned_words[] = $max_chars === null ? $word : substr($word, 0, $max_chars);

		// Filter out all words that occur more than once.
		return array_unique($returned_words);
	}
}

/**
 * Create a 'button', comprised of an icon and a text string, subject to theme settings.
 *
 * This function first looks to see if the theme specifies its own button system, and if it does not (or, $force_use is true), this function manages the button generation.
 *
 * If the theme directs that image buttons should not be used, the button will simply be the text string dictated by $alt. If the theme does use image buttons, it looks to see if it uses full images, or image+text, and generates the appropriate HTML.
 *
 * @param string $name Name of the button, which is also the base of the filename of the image to be used.
 * @param string $alt The key within $txt to use as the alt-text of the image, or the textual caption if there is no image.
 * @param string $label The key within $txt to use in the event of image/text composite buttons.
 * @param string $custom Any additional custom parameters to attach to the img item in the HTML, perhaps an HTML class, inline style or similar.
 * @param bool $force_use By default, this function will transfer control of creating buttons to the theme if it provides for such; setting this value to true forces this to override the theme.
 * @return string The HTML for the given button.
 */
function create_button($name, $alt, $label = '', $custom = '', $force_use = false)
{
	global $settings, $txt, $context;

	// Does the current loaded theme have this and we are not forcing the usage of this function?
	if (function_exists('template_create_button') && !$force_use)
		return template_create_button($name, $alt, $label = '', $custom = '');

	if (!$settings['use_image_buttons'])
		return $txt[$alt];
	elseif (!empty($settings['use_buttons']))
		return '<img src="' . $settings['images_url'] . '/buttons/' . $name . '" alt="' . $txt[$alt] . '" ' . $custom . '>' . ($label != '' ? '<strong>' . $txt[$label] . '</strong>' : '');
	else
		return '<img src="' . $settings['lang_images_url'] . '/' . $name . '" alt="' . $txt[$alt] . '" ' . $custom . '>';
}

/**
 * This function handles the processing of the main application menu presented to the user.
 *
 * Notes:
 * - It defines every master item in the menu, as well as any sub-items it may have.
 * - It also matches the current action against the list of items to ensure the appropriate top level item is highlighted.
 * - The principle menu data is also cached, based on the user groups and language.
 * - The entire menu, as it will be displayed (i.e. disabled items/where show is set to false; these are removed) is pushed into $context['menu_items'].
 */
function setupMenuContext()
{
	global $context, $modSettings, $user_info, $board_info, $txt, $scripturl;

	// Set up the menu privileges.
	$context['allow_search'] = allowedTo('search_posts');
	$context['allow_admin'] = allowedTo(array('admin_forum', 'manage_boards', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_attachments', 'manage_smileys'));
	$context['allow_edit_profile'] = !$user_info['is_guest'] && allowedTo(array('profile_view_own', 'profile_view_any', 'profile_identity_own', 'profile_identity_any', 'profile_extra_own', 'profile_extra_any', 'profile_remove_own', 'profile_remove_any', 'moderate_forum', 'manage_membergroups', 'profile_title_own', 'profile_title_any'));
	$context['allow_memberlist'] = allowedTo('view_mlist');
	$context['allow_calendar'] = allowedTo('calendar_view') && !empty($modSettings['cal_enabled']);
	$context['allow_moderation_center'] = $context['user']['can_mod'];
	$context['allow_pm'] = allowedTo('pm_read');

	// Recalculate the number of unseen media items
	// !!! @todo: The SSI test is only here for as long as we're executing the Media DB installer separately.
	if (!empty($user_info['media_unseen']) && $user_info['media_unseen'] == -1 && SMF !== 'SSI')
	{
		loadSource('media/Subs-Media');
		loadMediaSettings();
	}

	$cacheTime = $modSettings['lastActive'] * 60;

	$error_count = allowedTo('admin_forum') ? (!empty($modSettings['app_error_count']) ? ' (<strong>' . $modSettings['app_error_count'] . '</strong>)' : '') : '';

	// All the items we can possible want and then some, try pulling the final list of items from cache first.
	if (($menu_items = cache_get_data('menu_items-' . implode('_', $user_info['groups']) . '-' . $user_info['language'], $cacheTime)) === null || time() - $cacheTime <= $modSettings['settings_updated'])
	{
		$is_b = !empty($board_info['id']);
		$items = array(
			'home' => array(
				'title' => $txt['home'],
				'href' => $scripturl,
				'show' => true,
				'sub_items' => array(
					'root' => array(
						'title' => $context['forum_name'],
						'href' => $scripturl,
						'show' => $is_b,
					),
					'board' => array(
						'title' => $is_b ? $board_info['name'] : '',
						'href' => $is_b ? $scripturl . '?board=' . $board_info['id'] . '.0' : '',
						'show' => $is_b,
					),
				),
			),
			'search' => array(
				'title' => $txt['search'],
				'href' => $scripturl . '?action=search',
				'show' => $context['allow_search'],
				'sub_items' => array(
					'search' => array(
						'title' => $txt['search_simple'],
						'href' => $scripturl . '?action=search',
						'show' => $context['allow_search'] && !empty($modSettings['simpleSearch']),
					),
					'advanced_search' => array(
						'title' => $txt['search_advanced'],
						'href' => $scripturl . '?action=search;advanced',
						'show' => $context['allow_search'] && !empty($modSettings['simpleSearch']),
					),
				),
			),
			'admin' => array(
				'title' => $txt['admin'] . $error_count,
				'href' => $scripturl . ($context['allow_admin'] ? '?action=admin' : '?action=moderate'),
				'show' => $context['allow_admin'] || $context['allow_moderation_center'],
				'sub_items' => array(
					'featuresettings' => array(
						'title' => $txt['modSettings_title'],
						'href' => $scripturl . '?action=admin;area=featuresettings',
						'show' => allowedTo('admin_forum'),
					),
					'errorlog' => array(
						'title' => $txt['errlog'] . $error_count,
						'href' => $scripturl . '?action=admin;area=logs;sa=errorlog;desc',
						'show' => allowedTo('admin_forum') && !empty($modSettings['enableErrorLogging']),
					),
					'permissions' => array(
						'title' => $txt['edit_permissions'],
						'href' => $scripturl . '?action=admin;area=permissions',
						'show' => allowedTo('manage_permissions'),
					),
					'packages' => array(
						'title' => $txt['package'],
						'href' => $scripturl . '?action=admin;area=packages',
						'show' => allowedTo('admin_forum'),
					),
					'',
					'modcenter' => array(
						'title' => $txt['moderate'],
						'href' => $scripturl . '?action=moderate',
						'show' => $context['allow_admin'],
					),
					'modlog' => array(
						'title' => $txt['modlog_view'],
						'href' => $scripturl . '?action=moderate;area=modlog',
						'show' => !empty($modSettings['modlog_enabled']) && !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
					),
					'reports' => array(
						'title' => $txt['mc_reported_posts'],
						'href' => $scripturl . '?action=moderate;area=reports',
						'show' => !empty($user_info['mod_cache']) && $user_info['mod_cache']['bq'] != '0=1',
					),
					'poststopics' => array(
						'title' => $txt['mc_unapproved_poststopics'],
						'href' => $scripturl . '?action=moderate;area=postmod;sa=posts',
						'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
					),
					'attachments' => array(
						'title' => $txt['mc_unapproved_attachments'],
						'href' => $scripturl . '?action=moderate;area=attachmod;sa=attachments',
						'show' => $modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap']),
					),
				),
			),
			'profile' => array(
				'title' => $txt['profile'],
				'href' => $scripturl . '?action=profile',
				'show' => $context['allow_edit_profile'],
				'sub_items' => array(
					'summary' => array(
						'title' => $txt['summary'],
						'href' => $scripturl . '?action=profile',
						'show' => true,
					),
					'showdrafts' => array(
						'title' => $txt['draft_posts'],
						'href' => $scripturl . '?action=profile;area=showdrafts',
						'show' => allowedTo('save_post_draft') && !empty($modSettings['masterSavePostDrafts']),
					),
					'account' => array(
						'title' => $txt['account'],
						'href' => $scripturl . '?action=profile;area=account',
						'show' => allowedTo(array('profile_identity_any', 'profile_identity_own', 'manage_membergroups')),
					),
					'profile' => array(
						'title' => $txt['forumprofile'],
						'href' => $scripturl . '?action=profile;area=forumprofile',
						'show' => allowedTo(array('profile_extra_any', 'profile_extra_own')),
					),
				),
			),
			'pm' => array(
				'title' => $txt['pm_short'],
				'href' => $scripturl . '?action=pm',
				'show' => $context['allow_pm'],
				'sub_items' => array(
					'pm_read' => array(
						'title' => $txt['pm_menu_read'],
						'href' => $scripturl . '?action=pm',
						'show' => allowedTo('pm_read'),
					),
					'',
					'pm_send' => array(
						'title' => $txt['pm_menu_send'],
						'href' => $scripturl . '?action=pm;sa=send',
						'show' => allowedTo('pm_send'),
					),
					'pm_draft' => array(
						'title' => $txt['pm_menu_drafts'],
						'href' => $scripturl . '?action=pm;sa=showdrafts',
						'show' => allowedTo('pm_send') && allowedTo('save_pm_draft') && !empty($modSettings['masterSavePmDrafts']),
					),
				),
			),
			'calendar' => array(
				'title' => $txt['calendar'],
				'href' => $scripturl . '?action=calendar',
				'show' => $context['allow_calendar'],
				'sub_items' => array(
					'view' => array(
						'title' => $txt['calendar_menu'],
						'href' => $scripturl . '?action=calendar',
						'show' => allowedTo('calendar_post'),
					),
					'post' => array(
						'title' => $txt['calendar_post_event'],
						'href' => $scripturl . '?action=calendar;sa=post',
						'show' => allowedTo('calendar_post'),
					),
				),
			),
			'media' => array(
				'title' => (isset($txt['media_gallery']) ? $txt['media_gallery'] : 'Media') . (!allowedTo('media_access_unseen') || empty($user_info['media_unseen']) || $user_info['media_unseen'] == -1 ? '' : ' [<b>' . $user_info['media_unseen'] . '</b>]'),
				'href' => $scripturl . '?action=media',
				'show' => !empty($modSettings['media_enabled']) && allowedTo('media_access'),
				'sub_items' => array(
					'home' => array(
						'title' => $txt['media_home'],
						'href' => $scripturl . '?action=media',
						'show' => allowedTo('media_access_unseen') && !empty($user_info['media_unseen']) && $user_info['media_unseen'] != -1,
					),
					'unseen' => array(
						'title' => $txt['media_unseen'],
						'href' => $scripturl . '?action=media;sa=unseen',
						'show' => allowedTo('media_access_unseen') && !empty($user_info['media_unseen']) && $user_info['media_unseen'] != -1,
					),
				),
			),
			'mlist' => array(
				'title' => $txt['members_title'],
				'href' => $scripturl . '?action=mlist',
				'show' => $context['allow_memberlist'],
				'sub_items' => array(
					'mlist_view' => array(
						'title' => $txt['mlist_menu_view'],
						'href' => $scripturl . '?action=mlist',
						'show' => true,
					),
					'mlist_search' => array(
						'title' => $txt['mlist_search'],
						'href' => $scripturl . '?action=mlist;sa=search',
						'show' => true,
					),
				),
			),
			'login' => array(
				'title' => $txt['login'],
				'href' => $scripturl . '?action=login',
				'show' => $user_info['is_guest'],
			),
			'register' => array(
				'title' => $txt['register'],
				'href' => $scripturl . '?action=register',
				'show' => $user_info['is_guest'],
			),
			'logout' => array(
				'title' => $txt['logout'],
				'href' => $scripturl . '?action=logout;%1$s=%2$s',
				'show' => !$user_info['is_guest'],
			),
		);

		// Allow editing menu items easily.
		// Use PHP's array_splice to add entries at a specific position.
		call_hook('menu_items', array(&$items));

		// Now we put the items in the context so the theme can use them.
		$menu_items = array();
		foreach ($items as $act => $item)
		{
			if (!empty($item['show']))
			{
				$item['active_item'] = false;

				// Go through the sub items if there are any.
				if (!empty($item['sub_items']))
				{
					foreach ($item['sub_items'] as $key => $subitem)
					{
						if (empty($subitem['show']) && !empty($subitem))
							unset($item['sub_items'][$key]);

						// 2nd level sub items next...
						if (!empty($subitem['sub_items']))
							foreach ($subitem['sub_items'] as $key2 => $subitem2)
								if (empty($subitem2['show']) && !empty($subitem2))
									unset($item['sub_items'][$key]['sub_items'][$key2]);
					}
				}

				$menu_items[$act] = $item;
			}
		}

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('menu_items-' . implode('_', $user_info['groups']) . '-' . $user_info['language'], $menu_items, $cacheTime);
	}

	$context['menu_items'] =& $menu_items;

	// Logging out requires the session id in the url.
	if (isset($menu_items['logout']))
		$menu_items['logout']['href'] = sprintf($menu_items['logout']['href'], $context['session_var'], $context['session_id']);

	// Figure out which action we are doing so we can set the active tab.
	// Default to home.
	$current_action = 'home';

	if (isset($menu_items[$context['current_action']]))
		$current_action = $context['current_action'];
	elseif ($context['current_action'] == 'search2')
		$current_action = 'search';
	elseif ($context['current_action'] == 'theme')
		$current_action = isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'pick' ? 'profile' : 'admin';
	elseif ($context['current_action'] == 'register2')
		$current_action = 'register';
	elseif ($context['current_action'] == 'login2' || ($user_info['is_guest'] && $context['current_action'] == 'reminder'))
		$current_action = 'login';
	elseif ($context['current_action'] == 'groups' && $context['allow_moderation_center'])
		$current_action = 'moderate';

	$menu_items[$current_action]['active_item'] = true;

	if (!$user_info['is_guest'] && $context['user']['unread_messages'] > 0 && isset($menu_items['pm']))
	{
		$menu_items['pm']['alttitle'] = $menu_items['pm']['title'] . ' [' . $context['user']['unread_messages'] . ']';
		$menu_items['pm']['title'] .= '<div class="new_icon" title="' . $txt['new'] . '"></div>[<strong>' . $context['user']['unread_messages'] . '</strong>]&nbsp;';
	}
}

/**
 * Generates a random seed to be used application-wide.
 *
 * This function updates $modSettings['rand_sand'] which is used in generating tokens for major SMF actions. It is updated if not found or on a 1/250 chance of regeneration per page load (both regular index.php and SSI.php use)
 */
function smf_seed_generator()
{
	global $modSettings;

	updateSettings(array('rand_seed' => mt_rand()));
}

/**
 * Calls a given hook at the related point in the code.
 *
 * Each of the hooks is an array of functions within $modSettings['hooks'], to be called at relevant points in the code, such as $modSettings['hooks']['login'] which is run during login (to facilitate login into an integrated application.)
 *
 * The contents of the $modSettings['hooks'] value is a comma separated list of function names to be called at the relevant point. These are either procedural functions or static class methods (classname::method).
 *
 * @param string $hook The name of the hook as given in $modSettings, e.g. login, buffer, reset_pass
 * @param array $parameters Parameters to be passed to the hooked functions. The list of parameters each method is exposed to is dependent on the calling code (e.g. the hook for 'new topic is posted' passes different parameters to the 'final buffer' hook), and parameters passed by reference will be passed to hook functions as such.
 * @return array An array of results, one element per hooked function. This will be solely dependent on the hooked function.
 */
function call_hook($hook, $parameters = array())
{
	global $modSettings;

	if (empty($modSettings['hooks'][$hook]))
		return array();

	$results = array();

	// Loop through each function.
	foreach ($modSettings['hooks'][$hook] as $function)
	{
		$fun = explode('|', trim($function));
		$call = strpos($fun[0], '::') !== false ? explode('::', $fun[0]) : $fun[0];

		// Load any required file.
		if (!empty($fun[1]))
			loadSource($fun[1]);

		// If it isn't valid, remove it from our list.
		if (is_callable($call))
			$results[$fun[0]] = call_user_func_array($call, $parameters);
		else
			remove_hook($call, $function);
	}

	return $results;
}

/**
 * Add a function to one of the hook stacks.
 * Gotta love hooks. What? I said hooks, not hookers.
 *
 * This function adds a function to be called. It also prevents duplicates on the same hook.
 *
 * @param string $hook The name of the hook that has zero or more functions attached, that the function will be added to.
 * @param string $function The name of the function whose name should be added to the named hook.
 * @param string $file The file where Wedge can find that function, in loadSource() format. To include /Sources/MyFile.php, simply use 'MyFile'. Use '../MyFile' if it's in the root folder, etc. Leave blank if you know it'll be loaded at any time.
 * @param bool $register Whether the named function will be added to the hook registry permanently (default), or simply for the current page load only.
 */
function add_hook($hook, $function, $file = '', $register = true)
{
	global $modSettings, $sourcedir;

	if (!empty($file) && !file_exists($sourcedir . '/' . ($file = trim($file)) . '.php'))
		$file = '';

	$function .= '|' . $file;

	if ($register && !isset($modSettings['registered_hooks'][$hook]))
		$modSettings['registered_hooks'][$hook] = array();
	elseif (!$register && !isset($modSettings['hooks'][$hook]))
		$modSettings['hooks'][$hook] = array();

	// Do nothing if it's already there, except if we're
	// asking for registration and it isn't registered yet.
	if ((!$register || in_array($function, $modSettings['registered_hooks'][$hook])) && ($in_hook = in_array($function, $modSettings['hooks'][$hook])))
		return;

	// Add it!
	if (empty($in_hook))
		$modSettings['hooks'][$hook][] = $function;
	if (!$register)
		return;

	// Add to the permanent registered list.
	$hooks = $modSettings['registered_hooks'];
	$hooks[$hook][] = $function;
	updateSettings(array('registered_hooks' => serialize($hooks)));
	$modSettings['registered_hooks'] = $hooks;
}

/**
 * Remove a function from one of the hook stacks.
 *
 * This function not only removes the hook from the local registry, but also from the master registry. Note that this function does not check whether the named function is callable, simply that it is part of the stack - it can be used on the file-include hook as well. If the function is not attached to the named hook, the function will simply return.
 *
 * @param string $hook The name of the hook that has one or more functions attached.
 * @param string $function The name of the function whose name should be removed from the named hook.
 * @param string $file The file where Wedge can find that function, in loadSource() format. To include /Sources/MyFile.php, simply use 'MyFile'. Use '../MyFile' if it's in the root folder, etc. Leave blank if you know it'll be loaded at any time.
 * @todo Modify the function to return true on success and false on fail.
 */
function remove_hook($hook, $function, $file = '')
{
	global $modSettings, $sourcedir;

	if (!empty($file) && !file_exists($sourcedir . '/' . ($file = trim($file)) . '.php'))
		$file = '';

	$function .= '|' . $file;

	// You can only remove it's available.
	if (empty($modSettings['hooks'][$hook]) || !in_array($function, $modSettings['hooks'][$hook]))
		return;

	$modSettings['hooks'][$hook] = array_diff($modSettings['hooks'][$hook], (array) $function);

	if (empty($modSettings['registered_hooks'][$hook]) || !in_array($function, $modSettings['registered_hooks'][$hook]))
		return;

	// Also remove it from the registered hooks.
	$hooks = $modSettings['registered_hooks'];
	$hooks[$hook] = array_diff($hooks[$hook], (array) $function);
	if (empty($hooks[$hook]))
		unset($hooks[$hook]);
	updateSettings(array('registered_hooks' => serialize($hooks)));
	$modSettings['registered_hooks'] = $hooks;
}

/**
 * Output a 1x1 transparent GIF image and end execution.
 */
function blankGif()
{
	header('Content-Type: image/gif');
	die("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
}

/**
 * Add a link (URL and title) to the linktree. Self-explained.
 */
function add_linktree($url, $name)
{
	global $context;

	$context['linktree'][] = array('url' => $url, 'name' => $name);
}

/**
 * Return a Gravatar URL based on the supplied email address, the global maximum rating, and maximum sizes as set in the admin panel.
 *
 * @todo Add the default URL support once we have one.
 */
function get_gravatar_url($email_address)
{
	global $modSettings;
	static $size_string = null;

	if ($size_string === null)
	{
		if (!empty($modSettings['avatar_max_width_external']))
			$size_string = (int) $modSettings['avatar_max_width_external'];
		if (!empty($modSettings['avatar_max_height_external']) && !empty($size_string))
			if ((int) $modSettings['avatar_max_height_external'] < $size_string)
				$size_string = $modSettings['avatar_max_height_external'];

		if (!empty($size_string))
			$size_string = '&amp;s=' . $size_string;
		else
			$size_string = '';
	}

	return 'http://www.gravatar.com/avatar.php?gravatar_id=' . md5(strtolower($email_address)) . (!empty($modSettings['gravatarMaxRating']) ? '&amp;rating=' . $modSettings['gravatarMaxRating']: '') . $size_string;
}

/**
 * Return the list of message icons that we can rely on having.
 */
function stable_icons()
{
	return array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'moved', 'recycled', 'clip', 'wireless', 'android', 'iphone', 'tablet');
}

?>