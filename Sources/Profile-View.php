<?php
/**
 * Wedge
 *
 * Handles gathering information for read-only areas of the user profile system.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*

	void summary(int id_member)
		// !!!

	void showDrafts(int id_member)
		// !!!

	void showPosts(int id_member)
		// !!!

	void showAttachments(int id_member)
		// !!!

	void statPanel(int id_member)
		// !!!

	void tracking(int id_member)
		// !!!

	void trackUser(int id_member)
		// !!!

	int list_getUserErrorCount(string where)
		// !!!

	array list_getUserErrors(int start, int items_per_page, string sort, string where, array where_vars)
		// !!!

	int list_getIPMessageCount(string where)
		// !!!

	array list_getIPMessages(int start, int items_per_page, string sort, string where, array where_vars)
		// !!!

	void trackIP(int id_member = none)
		// !!!

	void trackEdits(int id_member)
		// !!!

	int list_getProfileEditCount(int id_member)
		// !!!

	array list_getProfileEdits(int start, int items_per_page, string sort, int id_member)
		// !!!

	void showPermissions(int id_member)
		// !!!

*/

// View a summary.
function summary($memID)
{
	global $context, $memberContext, $txt, $settings, $user_profile;

	// Attempt to load the member's profile data.
	if (!loadMemberContext($memID) || !isset($memberContext[$memID]))
		fatal_lang_error('not_a_user', false);

	// Set up the stuff and load the user.
	$context += array(
		'page_title' => sprintf($txt['profile_of_username'], $memberContext[$memID]['name']),
		'can_send_pm' => allowedTo('pm_send'),
		'can_have_buddy' => allowedTo('profile_identity_own') && !empty($settings['enable_buddylist']),
		'can_issue_warning' => allowedTo('issue_warning'),
	);
	$context['member'] =& $memberContext[$memID];
	$context['can_view_warning'] = !we::$is_guest && ((allowedTo('issue_warning') && !we::$user['is_owner']) || (!empty($settings['warning_show']) && ($settings['warning_show'] > 1 || we::$user['is_owner'])));

	// Set a canonical URL for this page.
	$context['canonical_url'] = '<URL>?action=profile;u=' . $memID;

	// Are there things we don't show?
	$context['disabled_fields'] = isset($settings['disabled_profile_fields']) ? array_flip(explode(',', $settings['disabled_profile_fields'])) : array();

	// They haven't even been registered for a full day!?
	$days_registered = (int) ((time() - $user_profile[$memID]['date_registered']) / (3600 * 24));
	if (empty($user_profile[$memID]['date_registered']) || $days_registered < 1)
		$context['member']['posts_per_day'] = $txt['not_applicable'];
	else
		$context['member']['posts_per_day'] = comma_format($context['member']['real_posts'] / $days_registered, 3);

	// Set the age...
	if (empty($context['member']['birth_date']))
	{
		$context['member'] += array(
			'age' => $txt['not_applicable'],
			'today_is_birthday' => false
		);
	}
	else
	{
		list ($birth_year, $birth_month, $birth_day) = sscanf($context['member']['birth_date'], '%d-%d-%d');
		$datearray = getdate(forum_time());
		$context['member'] += array(
			'age' => $birth_year <= 4 ? $txt['not_applicable'] : $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] == $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1),
			'today_is_birthday' => $datearray['mon'] == $birth_month && $datearray['mday'] == $birth_day
		);
	}

	$context['can_see_ip'] = allowedTo('manage_bans');
	if ($context['can_see_ip'])
	{
		// Make sure it's a valid ip address; otherwise, don't bother...
		if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $memberContext[$memID]['ip']) == 1 && empty($settings['disableHostnameLookup']))
			$context['member']['hostname'] = host_from_ip($memberContext[$memID]['ip']);
		else
			$context['member']['hostname'] = '';
	}

	// Can we see what the user is doing?
	if (!empty($settings['who_enabled']) && allowedTo('who_view') && (allowedTo('moderate_forum') || !empty($user_profile[$memID]['show_online'])))
	{
		loadSource('Who');
		$action = determineActions($user_profile[$memID]['url'], false, $memID);

		if ($action !== false)
			$context['member']['action'] = empty($user_profile[$memID]['show_online']) ? '<em>' . $action . '</em>' : $action;
	}

	// If the user is awaiting activation, and the viewer has permission - setup some activation context messages.
	$state = $context['member']['is_activated'] % 10;
	if ($state != 1 && allowedTo('moderate_forum'))
	{
		$context['activate_type'] = $context['member']['is_activated'];
		// What should the link text be?
		$context['activate_link_text'] = in_array($state, array(3, 4, 5, 6)) ? $txt['account_approve'] : $txt['account_activate'];

		// Should we show a custom message?
		$context['activate_message'] = isset($txt['account_activate_method_' . $state]) ? $txt['account_activate_method_' . $state] : $txt['account_not_activated'];
	}

	// Is the signature even enabled on this forum?
	$context['signature_enabled'] = $settings['signature_settings'][0] == 1;

	// What about warnings?
	if ($context['can_view_warning'])
	{
		// A few things we need to set up first.
		loadSource(array('Profile-Actions', 'ManageInfractions'));
		loadLanguage('ManageInfractions');
		getInfractionLevels();

		$inf_settings = !empty($settings['infraction_settings']) ? unserialize($settings['infraction_settings']) : array();
		$revoke_any = isset($inf_settings['revoke_any_issued']) ? $inf_settings['revoke_any_issued'] : array();
		$revoke_any[] = 1; // Admins really are special.
		$context['revoke_own'] = !empty($inf_settings['revoke_own_issued']);
		$context['revoke_any'] = count(array_intersect(we::$user['groups'], $revoke_any)) != 0;

		get_validated_infraction_log($memID, false);
	}
	
	// How about, are they banned?
	$context['member']['bans'] = array();
	if (allowedTo('moderate_forum'))
	{
		add_css_file('mana', true);
		loadLanguage('ManageBans');
		// Can they edit the ban?
		$context['can_edit_ban'] = allowedTo('manage_bans');

		$groups = array($user_profile[$memID]['id_group']);
		if (!empty($user_profile[$memID]['additional_groups']))
			$groups += explode(',', $user_profile[$memID]['additional_groups']);

		// Administrators are never banned as such.
		if (!in_array(1, $groups))
		{
			$ban_ids = array();

			// User ban
			$member_bans = check_banned_member($memID);
			if (!empty($member_bans))
				foreach ($member_bans as $ban)
					$ban_ids[] = $ban['id'];

			// Emails...
			$email_bans = isBannedEmail($context['member']['email'], '', true);
			if (!empty($email_bans))
				foreach ($email_bans as $ban)
					$ban_ids[] = $ban['id'];

			// IP and hostname bans...
			if (!empty($memberContext[$memID]['ip']))
			{
				$ip = expand_ip($memberContext[$memID]['ip']);
				$ip_hostname_bans = check_banned_ip($ip);
				if (!empty($ip_hostname_bans))
					foreach ($ip_hostname_bans as $ban)
						$ban_ids[] = $ban['id'];
			}

			// Now get all the rest of the details.
			if (!empty($ban_ids))
			{
				$request = wesql::query('
					SELECT id_ban, hardness, ban_type, ban_content, ban_reason
					FROM {db_prefix}bans
					WHERE id_ban IN ({array_int:bans})',
					array(
						'bans' => $ban_ids,
					)
				);
				while ($row = wesql::fetch_assoc($request))
					$context['member']['bans'][$row['id_ban']] = $row;
				wesql::free_result($request);
			}
		}
	}

	loadCustomFields($memID);
}

// Show the user's drafts.
function showDrafts($memID)
{
	global $context, $memberContext, $txt, $settings, $user_profile;

	// Attempt to load the member's profile data.
	if (!loadMemberContext($memID) || !isset($memberContext[$memID]))
		fatal_lang_error('not_a_user', false);

	// Are we deleting any drafts here?
	if (isset($_GET['deleteall']))
	{
		checkSession('post');
		wesql::query('
			DELETE FROM {db_prefix}drafts
			WHERE is_pm = {int:not_pm}
				AND id_member = {int:member}',
			array(
				'not_pm' => 0,
				'member' => $memID,
			)
		);

		redirectexit('action=profile;u=' . $memID . ';area=showdrafts');
	}
	elseif (!empty($_GET['delete']))
	{
		$draft_id = (int) $_GET['delete'];
		checkSession('get');

		wesql::query('
			DELETE FROM {db_prefix}drafts
			WHERE id_draft = {int:draft}
				AND id_member = {int:member}
			LIMIT 1',
			array(
				'draft' => $draft_id,
				'member' => $memID,
			)
		);

		if (AJAX)
			obExit(false);
		else
			redirectexit('action=profile;u=' . $memID . ';area=showdrafts');
	}

	// Some initial context.
	wetem::load('showDrafts');
	$context['start'] = (int) $_REQUEST['start'];
	$context['current_member'] = $memID;
	$context['page_title'] = $txt['showDrafts'] . ' - ' . $context['member']['name'];

	if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		$_REQUEST['viewscount'] = 10;

	// Get the count of applicable drafts
	$request = wesql::query('
		SELECT COUNT(id_draft)
		FROM {db_prefix}drafts AS d
		WHERE id_member = {int:member}
			AND is_pm = {int:not_pm}',
		array(
			'member' => $memID,
			'not_pm' => 0,
		)
	);
	list ($msgCount) = wesql::fetch_row($request);
	wesql::free_result($request);

	$reverse = false;
	$maxIndex = (int) $settings['defaultMaxMessages'];

	// Make sure the starting place makes sense and construct our friend the page index.
	$context['page_index'] = template_page_index('<URL>?action=profile;u=' . $memID . ';area=showdrafts', $context['start'], $msgCount, $maxIndex);
	$context['current_page'] = $context['start'] / $maxIndex;

	// Reverse the query if we're past 50% of the pages for better performance.
	$start = $context['start'];
	$reverse = $_REQUEST['start'] > $msgCount / 2;
	if ($reverse)
	{
		$maxIndex = $msgCount < $context['start'] + $settings['defaultMaxMessages'] + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : (int) $settings['defaultMaxMessages'];
		$start = $msgCount < $context['start'] + $settings['defaultMaxMessages'] + 1 || $msgCount < $context['start'] + $settings['defaultMaxMessages'] ? 0 : $msgCount - $context['start'] - $settings['defaultMaxMessages'];
	}

	// Find this user's drafts.
	$request = wesql::query('
		SELECT
			b.id_board, b.name AS bname, t.id_topic, t.locked, d.id_draft, d.subject, d.body,
			d.post_time, d.id_context, d.extra
		FROM {db_prefix}drafts AS d
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = d.id_board AND {query_see_board})
			LEFT JOIN {db_prefix}topics AS t ON (t.id_topic = d.id_context AND t.id_board = b.id_board AND {query_see_board})
		WHERE d.id_member = {int:current_member}
			AND d.is_pm = {int:not_pm}
		ORDER BY d.post_time ' . ($reverse ? 'ASC' : 'DESC') . '
		LIMIT ' . $start . ', ' . $maxIndex,
		array(
			'current_member' => $memID,
			'not_pm' => 0,
		)
	);

	// Start counting at the number of the first message displayed.
	$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
	$context['posts'] = array();
	$is_locked = false;
	while ($row = wesql::fetch_assoc($request))
	{
		// Censor....
		if (empty($row['body']))
			$row['body'] = '';

		$row['subject'] = westr::htmltrim($row['subject']);
		if ($row['subject'] === '')
			$row['subject'] = $txt['no_subject'];

		$row['extra'] = empty($row['extra']) ? array() : unserialize($row['extra']);

		censorText($row['body']);
		censorText($row['subject']);

		// Do the code.
		$row['body'] = parse_bbc($row['body'], 'post-draft', array('smileys' => !empty($row['extra']['smileys_enabled']), 'cache' => 'draft' . $row['id_draft']));

		// And the array...
		$context['posts'][$counter += $reverse ? -1 : 1] = array(
			'id' => $row['id_draft'],
			'subject' => $row['subject'],
			'body' => $row['body'],
			'counter' => $counter,
			'alternate' => $counter % 2,
			'board' => array(
				'id' => $row['id_board'],
				'name' => empty($row['bname']) ? $txt['drafts_noboard'] : $row['bname'],
				'link' => empty($row['bname']) ? $txt['drafts_noboard'] : '<a href="<URL>?board=' . $row['id_board'] . '.0">' . $row['bname'] . '</a>',
			),
			'topic' => array(
				'id' => $row['id_context'],
				'original_topic' => $row['id_topic'],
				'link' => empty($row['id_topic']) ? $row['subject'] : '<a href="<URL>?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
				'locked' => !empty($row['locked']),
				'no_edit' => !empty($row['id_context']) && empty($row['id_topic']),
			),
			'on_time' => on_timeformat($row['post_time']),
			'timestamp' => forum_time(true, $row['post_time']),
			'icon' => $row['extra']['post_icon'],
		);

		$is_locked |= !empty($row['locked']);
	}
	wesql::free_result($request);

	// OK, were any of these posts locked? If so, fetch the boards we're allowed to moderate, such that we can override...
	if ($is_locked)
	{
		$boards = boardsAllowedTo('moderate_board');
		foreach ($context['posts'] as $id => $post)
		{
			if ($post['topic']['locked'])
			{
				if (in_array($post['board']['id'], $boards))
					$context['posts'][$id]['topic']['locked'] = false;
				else
					$context['posts'][$id]['topic']['no_edit'] = true;
			}
		}
	}

	// All posts were retrieved in reverse order, get them right again.
	if ($reverse)
		$context['posts'] = array_reverse($context['posts'], true);
}

// !!! This function needs to be split up properly.
// Show all posts by the current user
function showPosts($memID)
{
	global $txt, $settings;
	global $context, $user_profile, $board;

	$guest = '';
	$specGuest = '';
	if (isset($_GET['guest']))
	{
		$memID = 0;
		$guest = base64_decode($_GET['guest']);
		$specGuest .= ' AND m.poster_name = {string:guest}';
	}
	else
		// Create the tabs for the template.
		$context[$context['profile_menu_name']]['tab_data'] = array(
			'title' => $txt['showPosts'],
			'description' => $txt['showPosts_help'],
			'icon' => 'profile_sm.gif',
			'tabs' => array(
				'messages' => array(
				),
				'topics' => array(
				),
				'attach' => array(
				),
			),
		);

	// Some initial context.
	$context['start'] = (int) $_REQUEST['start'];
	$context['current_member'] = $memID;

	// Set the page title
	$context['page_title'] = $txt['showPosts'] . ' - ' . ($guest ? base64_decode($guest) : $user_profile[$memID]['real_name']);

	// Is the load average too high to allow searching just now?
	if (!empty($context['load_average']) && !empty($settings['loadavg_show_posts']) && $context['load_average'] >= $settings['loadavg_show_posts'])
		fatal_lang_error('loadavg_show_posts_disabled', false);

	// If we're specifically dealing with attachments use that function!
	if (isset($_GET['sa']) && $_GET['sa'] == 'attach')
		return showAttachments($memID);

	// Are we just viewing topics?
	$context['is_topics'] = isset($_GET['sa']) && $_GET['sa'] == 'topics' ? true : false;

	// If just deleting a message, do it and then redirect back.
	if (isset($_GET['delete']) && !$context['is_topics'])
	{
		checkSession('get');

		// We need msg info for logging.
		$request = wesql::query('
			SELECT subject, id_member, id_topic, id_board
			FROM {db_prefix}messages
			WHERE id_msg = {int:id_msg}',
			array(
				'id_msg' => (int) $_GET['delete'],
			)
		);
		$info = wesql::fetch_row($request);
		wesql::free_result($request);

		// Trying to remove a message that doesn't exist.
		if (empty($info))
			redirectexit('action=profile;u=' . $memID . ';area=showposts;start=' . $_GET['start']);

		// We can be lazy, since removeMessage() will check the permissions for us.
		loadSource('RemoveTopic');
		removeMessage((int) $_GET['delete']);

		// Add it to the mod log.
		if (allowedTo('delete_any') && (!allowedTo('delete_own') || $info[1] != we::$id))
			logAction('delete', array('topic' => $info[2], 'subject' => $info[0], 'member' => $info[1], 'board' => $info[3]));

		// Back to... where we are now ;).
		redirectexit('action=profile;u=' . $memID . ';area=showposts;start=' . $_GET['start']);
	}

	// Default to 10.
	if (empty($_REQUEST['viewscount']) || !is_numeric($_REQUEST['viewscount']))
		$_REQUEST['viewscount'] = '10';

	// We want users to be able to see their own posts, regardless, and admins can see everything.
	$ignore_perms = we::$user['is_owner'] || we::$is_admin;
	if ($context['is_topics'])
		$request = wesql::query('
			SELECT COUNT(t.id_topic)
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)' . ($ignore_perms ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})') . '
			WHERE t.id_member_started = {int:current_member}' . $specGuest . (!empty($board) ? '
				AND t.id_board = {int:board}' : '') . (we::$user['is_owner'] ? '' : '
				AND {query_see_topic}'),
			array(
				'current_member' => $memID,
				'board' => $board,
				'guest' => $guest,
			)
		);
	else
		$request = wesql::query('
			SELECT COUNT(m.id_msg)
			FROM {db_prefix}messages AS m' . (we::$user['query_see_board'] == '1=1' ? '' : '
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})') . '
			WHERE m.id_member = {int:current_member}' . $specGuest . (!empty($board) ? '
				AND m.id_board = {int:board}' : '') . (!$settings['postmod_active'] || $ignore_perms ? '' : '
				AND m.approved = {int:is_approved}'),
			array(
				'current_member' => $memID,
				'is_approved' => 1,
				'board' => $board,
				'guest' => $guest,
			)
		);
	list ($msgCount) = wesql::fetch_row($request);
	wesql::free_result($request);

	$request = wesql::query('
		SELECT MIN(id_msg), MAX(id_msg)
		FROM {db_prefix}messages AS m
		INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
		WHERE m.id_member = {int:current_member}' . (!empty($board) ? '
			AND m.id_board = {int:board}' : '') . ($ignore_perms ? '' : '
			AND {query_see_topic}') . (!$settings['postmod_active'] || $ignore_perms ? '' : '
			AND m.approved = {int:is_approved}'),
		array(
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
		)
	);
	list ($min_msg_member, $max_msg_member) = wesql::fetch_row($request);
	wesql::free_result($request);

	$reverse = false;
	$range_limit = '';
	$maxIndex = (int) $settings['defaultMaxMessages'];

	// Make sure the starting place makes sense and construct our friend the page index.
	$context['page_index'] = template_page_index('<URL>?action=profile' . ($guest ? ';guest=' . $_GET['guest'] : (we::$user['is_owner'] ? '' : ';u=' . $memID) . ';area=showposts') . ($context['is_topics'] ? ';sa=topics' : '') . (!empty($board) ? ';board=' . $board : ''), $context['start'], $msgCount, $maxIndex);
	$context['current_page'] = $context['start'] / $maxIndex;

	// Reverse the query if we're past 50% of the pages for better performance.
	$start = $context['start'];
	$reverse = $_REQUEST['start'] > $msgCount / 2;
	if ($reverse)
	{
		$maxIndex = $msgCount < $context['start'] + $settings['defaultMaxMessages'] + 1 && $msgCount > $context['start'] ? $msgCount - $context['start'] : (int) $settings['defaultMaxMessages'];
		$start = $msgCount < $context['start'] + $settings['defaultMaxMessages'] + 1 || $msgCount < $context['start'] + $settings['defaultMaxMessages'] ? 0 : $msgCount - $context['start'] - $settings['defaultMaxMessages'];
	}

	// Guess the range of messages to be shown.
	if ($msgCount > 1000)
	{
		$margin = floor(($max_msg_member - $min_msg_member) * (($start + $settings['defaultMaxMessages']) / $msgCount) + .1 * ($max_msg_member - $min_msg_member));
		// Make a bigger margin for topics only.
		if ($context['is_topics'])
		{
			$margin *= 5;
			$range_limit = $reverse ? 't.id_first_msg < ' . ($min_msg_member + $margin) : 't.id_first_msg > ' . ($max_msg_member - $margin);
		}
		else
			$range_limit = $reverse ? 'm.id_msg < ' . ($min_msg_member + $margin) : 'm.id_msg > ' . ($max_msg_member - $margin);
	}

	// Find this user's posts. The left join on categories somehow makes this faster, weird as it looks.
	$looped = false;
	while (true)
	{
		if ($context['is_topics'])
		{
			$request = wesql::query('
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, t.id_member_started, t.id_first_msg, t.id_last_msg,
					t.approved, m.body, m.smileys_enabled, m.subject, m.poster_time, m.id_topic, m.id_msg
				FROM {db_prefix}topics AS t
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
					INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
				WHERE t.id_member_started = {int:current_member}' . $specGuest . (!empty($board) ? '
					AND t.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . ($ignore_perms ? '' : '
					AND {query_see_board}
					AND {query_see_topic}' . (!$settings['postmod_active'] ? '' : ' AND m.approved = {int:is_approved}')) . '
				ORDER BY t.id_first_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT ' . $start . ', ' . $maxIndex,
				array(
					'current_member' => $memID,
					'is_approved' => 1,
					'board' => $board,
					'guest' => $guest,
				)
			);
		}
		else
		{
			$request = wesql::query('
				SELECT
					b.id_board, b.name AS bname, c.id_cat, c.name AS cname, m.id_topic, m.id_msg,
					t.id_member_started, t.id_first_msg, t.id_last_msg, m.body, m.smileys_enabled,
					m.subject, m.poster_time, m.approved
				FROM {db_prefix}messages AS m
					INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
					INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
					LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
				WHERE m.id_member = {int:current_member}' . $specGuest . (!empty($board) ? '
					AND b.id_board = {int:board}' : '') . (empty($range_limit) ? '' : '
					AND ' . $range_limit) . ($ignore_perms ? '' : '
					AND {query_see_board}
					AND {query_see_topic}' . (!$settings['postmod_active'] ? '' : ' AND m.approved = {int:is_approved}')) . '
				ORDER BY m.id_msg ' . ($reverse ? 'ASC' : 'DESC') . '
				LIMIT ' . $start . ', ' . $maxIndex,
				array(
					'current_member' => $memID,
					'is_approved' => 1,
					'board' => $board,
					'guest' => $guest,
				)
			);
		}

		// Make sure we quit this loop.
		if (wesql::num_rows($request) === $maxIndex || $looped)
			break;
		$looped = true;
		$range_limit = '';
	}

	// Start counting at the number of the first message displayed.
	$counter = $reverse ? $context['start'] + $maxIndex + 1 : $context['start'];
	$context['posts'] = array();
	$board_ids = array('own' => array(), 'any' => array());
	while ($row = wesql::fetch_assoc($request))
	{
		// Censor....
		censorText($row['body']);
		censorText($row['subject']);

		// Do the code.
		$row['body'] = parse_bbc($row['body'], 'post', array('smileys' => $row['smileys_enabled'], 'cache' => $row['id_msg'], 'user' => $memID));

		// And the array...
		$context['posts'][$counter += $reverse ? -1 : 1] = array(
			'can_see' => $ignore_perms ? (we::$is_admin || in_array($row['id_board'], we::$user['qsb_boards'])) : true,
			'body' => $row['body'],
			'counter' => $counter,
			'alternate' => $counter % 2,
			'category' => array(
				'name' => $row['cname'],
				'id' => $row['id_cat']
			),
			'board' => array(
				'name' => $row['bname'],
				'id' => $row['id_board']
			),
			'topic' => $row['id_topic'],
			'subject' => $row['subject'],
			'start' => 'msg' . $row['id_msg'],
			'on_time' => on_timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'id' => $row['id_msg'],
			'can_reply' => false,
			'can_delete' => false,
			'delete_possible' => ($row['id_first_msg'] != $row['id_msg'] || $row['id_last_msg'] == $row['id_msg']) && (empty($settings['edit_disable_time']) || $row['poster_time'] + $settings['edit_disable_time'] * 60 >= time()),
			'approved' => $row['approved'],
		);

		if (we::$id == $row['id_member_started'])
			$board_ids['own'][$row['id_board']][] = $counter;
		$board_ids['any'][$row['id_board']][] = $counter;
	}
	wesql::free_result($request);

	// All posts were retrieved in reverse order, get them right again.
	if ($reverse)
		$context['posts'] = array_reverse($context['posts'], true);

	// These are all the permissions that are different from board to board...
	if ($context['is_topics'])
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
			)
		);
	else
		$permissions = array(
			'own' => array(
				'post_reply_own' => 'can_reply',
				'delete_own' => 'can_delete',
			),
			'any' => array(
				'post_reply_any' => 'can_reply',
				'delete_any' => 'can_delete',
			)
		);

	// For every permission in the own/any lists...
	foreach ($permissions as $type => $list)
	{
		foreach ($list as $permission => $allowed)
		{
			// Get the boards they can do this on...
			$boards = boardsAllowedTo($permission);

			// Hmm, they can do it on all boards, can they?
			if (!empty($boards) && $boards[0] == 0)
				$boards = array_keys($board_ids[$type]);

			// Now go through each board they can do the permission on.
			foreach ($boards as $board_id)
			{
				// There aren't any posts displayed from this board.
				if (!isset($board_ids[$type][$board_id]))
					continue;

				// Set the permission to true ;).
				foreach ($board_ids[$type][$board_id] as $counter)
					$context['posts'][$counter][$allowed] = true;
			}
		}
	}

	// Clean up after posts that cannot be deleted and quoted.
	$quote_enabled = empty($settings['disabledBBC']) || !in_array('quote', explode(',', $settings['disabledBBC']));
	foreach ($context['posts'] as $counter => $dummy)
	{
		$context['posts'][$counter]['can_delete'] &= $context['posts'][$counter]['delete_possible'];
		$context['posts'][$counter]['can_quote'] = $context['posts'][$counter]['can_reply'] && $quote_enabled;
	}

	// Just quickly, get the likes.
	$msgs = array();
	foreach ($context['posts'] as $counter => $row)
		$msgs[] = $row['id'];
	if (!empty($msgs))
	{
		loadSource('Display');
		loadTemplate('Msg');
		prepareLikeContext($msgs);
	}
}

// Show all the attachments of a user.
function showAttachments($memID)
{
	global $txt, $settings, $board, $context, $user_profile;

	// OBEY permissions!
	$boardsAllowed = boardsAllowedTo('view_attachments');
	// Make sure we can't actually see anything...
	if (empty($boardsAllowed))
		$boardsAllowed = array(-1);

	// Get the total number of attachments they have posted.
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
			INNER JOIN {db_prefix}topics AS t ON (m.id_topic = t.id_topic)
		WHERE a.attachment_type = {int:attachment_type}
			AND a.id_msg != {int:no_message}
			AND m.id_member = {int:current_member}' . (!empty($board) ? '
			AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_list})' : '') . (we::$user['is_owner'] ? '' : '
			AND {query_see_topic}') . (!$settings['postmod_active'] || we::$user['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}'),
		array(
			'boards_list' => $boardsAllowed,
			'attachment_type' => 0,
			'no_message' => 0,
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
		)
	);
	list ($attachCount) = wesql::fetch_row($request);
	wesql::free_result($request);

	$maxIndex = (int) $settings['defaultMaxMessages'];

	// What about ordering?
	$sortTypes = array(
		'filename' => 'a.filename',
		'downloads' => 'a.downloads',
		'subject' => 'm.subject',
		'posted' => 'm.poster_time',
	);
	$context['sort_order'] = isset($_GET['sort'], $sortTypes[$_GET['sort']]) ? $_GET['sort'] : 'posted';
	$context['sort_direction'] = isset($_GET['asc']) ? 'up' : 'down';

	$sort = $sortTypes[$context['sort_order']];

	// Let's get ourselves a lovely page index.
	$context['page_index'] = template_page_index('<URL>?action=profile;u=' . $memID . ';area=showposts;sa=attach;sort=' . $context['sort_order'] . ($context['sort_direction'] == 'up' ? ';asc' : ''), $context['start'], $attachCount, $maxIndex);

	// Retrieve some attachments.
	$request = wesql::query('
		SELECT a.id_attach, a.id_msg, a.filename, a.downloads, m.id_msg, m.id_topic,
			m.id_board, m.poster_time, m.subject, b.name
		FROM {db_prefix}attachments AS a
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
		WHERE a.attachment_type = {int:attachment_type}
			AND a.id_msg != {int:no_message}
			AND m.id_member = {int:current_member}' . (!empty($board) ? '
			AND b.id_board = {int:board}' : '') . (!in_array(0, $boardsAllowed) ? '
			AND b.id_board IN ({array_int:boards_list})' : '') . (!$settings['postmod_active'] || we::$user['is_owner'] ? '' : '
			AND m.approved = {int:is_approved}') . '
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'boards_list' => $boardsAllowed,
			'attachment_type' => 0,
			'no_message' => 0,
			'current_member' => $memID,
			'is_approved' => 1,
			'board' => $board,
			'sort' => $sort . ' ' . ($context['sort_direction'] == 'down' ? 'DESC' : 'ASC'),
			'offset' => $context['start'],
			'limit' => $maxIndex,
		)
	);
	$context['attachments'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$row['subject'] = censorText($row['subject']);

		$context['attachments'][] = array(
			'id' => $row['id_attach'],
			'filename' => $row['filename'],
			'downloads' => $row['downloads'],
			'subject' => $row['subject'],
			'posted' => timeformat($row['poster_time']),
			'msg' => $row['id_msg'],
			'topic' => $row['id_topic'],
			'board' => $row['id_board'],
			'board_name' => $row['name'],
		);
	}
	wesql::free_result($request);
}

function statPanel($memID)
{
	global $txt, $context, $user_profile, $settings;

	$context['page_title'] = $txt['statPanel_showStats'] . ' ' . $user_profile[$memID]['real_name'];

	// General user statistics.
	$timeDays = floor($user_profile[$memID]['total_time_logged_in'] / 86400);
	$timeHours = floor(($user_profile[$memID]['total_time_logged_in'] % 86400) / 3600);
	$context['time_logged_in'] = ($timeDays > 0 ? $timeDays . $txt['totalTimeLogged_d'] : '') . ($timeHours > 0 ? $timeHours . $txt['totalTimeLogged_h'] : '') . floor(($user_profile[$memID]['total_time_logged_in'] % 3600) / 60) . $txt['totalTimeLogged_m'];
	$context['num_posts'] = comma_format($user_profile[$memID]['posts']);

	// Number of topics started.
	$result = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}topics
		WHERE id_member_started = {int:current_member}' . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
			AND id_board != {int:recycle_board}' : ''),
		array(
			'current_member' => $memID,
			'recycle_board' => $settings['recycle_board'],
		)
	);
	list ($context['num_topics']) = wesql::fetch_row($result);
	wesql::free_result($result);

	// Number polls started.
	$result = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}topics
		WHERE id_member_started = {int:current_member}' . (!empty($settings['recycle_enable']) && $settings['recycle_board'] > 0 ? '
			AND id_board != {int:recycle_board}' : '') . '
			AND id_poll != {int:no_poll}',
		array(
			'current_member' => $memID,
			'recycle_board' => $settings['recycle_board'],
			'no_poll' => 0,
		)
	);
	list ($context['num_polls']) = wesql::fetch_row($result);
	wesql::free_result($result);

	// Number polls voted in.
	$result = wesql::query('
		SELECT COUNT(DISTINCT id_poll)
		FROM {db_prefix}log_polls
		WHERE id_member = {int:current_member}',
		array(
			'current_member' => $memID,
		)
	);
	list ($context['num_votes']) = wesql::fetch_row($result);
	wesql::free_result($result);

	// Format the numbers...
	$context['num_topics'] = comma_format($context['num_topics']);
	$context['num_polls'] = comma_format($context['num_polls']);
	$context['num_votes'] = comma_format($context['num_votes']);

	// Grab the board this member posted in most often.
	$result = wesql::query('
		SELECT
			b.id_board, MAX(b.name) AS name, MAX(b.num_posts) AS num_posts, COUNT(*) AS message_count
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_member = {int:current_member}
			AND b.count_posts = {int:count_enabled}
			AND {query_see_board}
		GROUP BY b.id_board
		ORDER BY message_count DESC
		LIMIT 10',
		array(
			'current_member' => $memID,
			'count_enabled' => 0,
		)
	);
	$context['popular_boards'] = array();
	while ($row = wesql::fetch_assoc($result))
	{
		$context['popular_boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => '<URL>?board=' . $row['id_board'] . '.0',
			'link' => '<a href="<URL>?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'posts_percent' => $user_profile[$memID]['posts'] == 0 ? 0 : ($row['message_count'] * 100) / $user_profile[$memID]['posts'],
			'total_posts' => $row['num_posts'],
			'total_posts_member' => $user_profile[$memID]['posts'],
		);
	}
	wesql::free_result($result);

	// Now get the 10 boards this user has most often participated in.
	$result = wesql::query('
		SELECT
			b.id_board, MAX(b.name) AS name, b.num_posts, COUNT(*) AS message_count,
			CASE WHEN COUNT(*) > MAX(b.num_posts) THEN 1 ELSE COUNT(*) / MAX(b.num_posts) END * 100 AS percentage
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE m.id_member = {int:current_member}
			AND {query_see_board}
		GROUP BY b.id_board, b.num_posts
		ORDER BY percentage DESC
		LIMIT 10',
		array(
			'current_member' => $memID,
		)
	);
	$context['board_activity'] = array();
	while ($row = wesql::fetch_assoc($result))
	{
		$context['board_activity'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'posts' => $row['message_count'],
			'href' => '<URL>?board=' . $row['id_board'] . '.0',
			'link' => '<a href="<URL>?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
			'percent' => comma_format((float) $row['percentage'], 2),
			'posts_percent' => (float) $row['percentage'],
			'total_posts' => $row['num_posts'],
		);
	}
	wesql::free_result($result);

	// Posting activity by time.
	$result = wesql::query('
		SELECT
			HOUR(FROM_UNIXTIME(poster_time + {int:time_offset})) AS hour,
			COUNT(*) AS post_count
		FROM {db_prefix}messages
		WHERE id_member = {int:current_member}' . ($settings['totalMessages'] > 100000 ? '
			AND id_topic > {int:top_ten_thousand_topics}' : '') . '
		GROUP BY hour',
		array(
			'current_member' => $memID,
			'top_ten_thousand_topics' => $settings['totalTopics'] - 10000,
			'time_offset' => ((we::$user['time_offset'] + $settings['time_offset']) * 3600),
		)
	);
	$maxPosts = $realPosts = 0;
	$context['posts_by_time'] = array();
	while ($row = wesql::fetch_assoc($result))
	{
		// Cast as an integer to remove the leading 0.
		$row['hour'] = (int) $row['hour'];

		$maxPosts = max($row['post_count'], $maxPosts);
		$realPosts += $row['post_count'];

		$context['posts_by_time'][$row['hour']] = array(
			'hour' => $row['hour'],
			'hour_format' => stripos(we::$user['time_format'], '%p') === false ? $row['hour'] : date('g a', mktime($row['hour'])),
			'posts' => $row['post_count'],
			'posts_percent' => 0,
			'is_last' => $row['hour'] == 23,
		);
	}
	wesql::free_result($result);

	if ($maxPosts > 0)
		for ($hour = 0; $hour < 24; $hour++)
		{
			if (!isset($context['posts_by_time'][$hour]))
				$context['posts_by_time'][$hour] = array(
					'hour' => $hour,
					'hour_format' => stripos(we::$user['time_format'], '%p') === false ? $hour : date('g a', mktime($hour)),
					'posts' => 0,
					'posts_percent' => 0,
					'relative_percent' => 0,
					'is_last' => $hour == 23,
				);
			else
			{
				$context['posts_by_time'][$hour]['posts_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $realPosts);
				$context['posts_by_time'][$hour]['relative_percent'] = round(($context['posts_by_time'][$hour]['posts'] * 100) / $maxPosts);
			}
		}

	// Put it in the right order.
	ksort($context['posts_by_time']);
}

function tracking($memID)
{
	global $context, $txt, $settings, $user_profile;

	$subActions = array(
		'activity' => array('trackActivity', $txt['trackActivity']),
		'ip' => array('trackIP', $txt['trackIP']),
		'edits' => array('trackEdits', $txt['trackEdits']),
	);

	$context['tracking_area'] = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : (allowedTo('manage_bans') ? 'activity' : (allowedTo('moderate_forum') ? 'edits' : ''));

	if (empty($context['tracking_area']))
		isAllowedTo('moderate_forum');

	// Create the tabs for the template.
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['tracking'],
		'description' => $txt['tracking_description'],
		'icon' => 'profile_sm.gif',
		'tabs' => array(
			'activity' => array(),
			'ip' => array(),
			'edits' => array(),
		),
	);

	// Moderation must be on to track edits.
	if (empty($settings['log_enabled_moderate']))
		unset($context[$context['profile_menu_name']]['tab_data']['edits']);

	// Set a page title.
	$context['page_title'] = $txt['trackUser'] . ' - ' . $subActions[$context['tracking_area']][1] . ' - ' . $user_profile[$memID]['real_name'];

	// Pass on to the actual function.
	wetem::load($subActions[$context['tracking_area']][0]);
	$subActions[$context['tracking_area']][0]($memID);
}

function trackActivity($memID)
{
	// !!! THIS IS VERY BROKEN RIGHT NOW! Stopped after trying to get my head round the complexity of changes for this.
	global $txt, $settings, $user_profile, $context;

	// Verify if the user has sufficient permissions.
	isAllowedTo('manage_bans');

	$context['last_ip'] = $user_profile[$memID]['member_ip'];
	if ($context['last_ip'] != $user_profile[$memID]['member_ip2'])
		$context['last_ip2'] = $user_profile[$memID]['member_ip2'];
	$context['member']['name'] = $user_profile[$memID]['real_name'];

	// Set the options for the list component.
	$listOptions = array(
		'id' => 'track_user_list',
		'title' => $txt['errors_by'] . ' ' . $context['member']['name'],
		'items_per_page' => $settings['defaultMaxMessages'],
		'no_items_label' => $txt['no_errors_from_user'],
		'base_href' => '<URL>?action=profile;u=' . $memID . ';area=tracking;sa=user',
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getUserErrors',
			'params' => array(
				'le.id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'get_count' => array(
			'function' => 'list_getUserErrorCount',
			'params' => array(
				'id_member = {int:current_member}',
				array('current_member' => $memID),
			),
		),
		'columns' => array(
			'ip_address' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?action=profile;u=' . $memID. ';area=tracking;sa=ip;searchip=%1$s">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'le.ip',
					'reverse' => 'le.ip DESC',
				),
			),
			'message' => array(
				'header' => array(
					'value' => $txt['message'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '%1$s<br><a href="%2$s">%2$s</a>',
						'params' => array(
							'message' => false,
							'url' => false,
						),
					),
				),
			),
			'date' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'le.id_error DESC',
					'reverse' => 'le.id_error',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['errors_desc'],
				'class' => 'smalltext',
				'style' => 'padding: 2ex;',
			),
		),
	);

	// Create the list for viewing.
	loadSource('Subs-List');
	createList($listOptions);

	// If this is a big forum, or a large posting user, let's limit the search.
	if ($settings['totalMessages'] > 50000 && $user_profile[$memID]['posts'] > 500)
	{
		$request = wesql::query('
			SELECT MAX(id_msg)
			FROM {db_prefix}messages AS m
			WHERE m.id_member = {int:current_member}',
			array(
				'current_member' => $memID,
			)
		);
		list ($max_msg_member) = wesql::fetch_row($request);
		wesql::free_result($request);

		// There's no point worrying ourselves with messages made yonks ago, just get recent ones!
		$min_msg_member = max(0, $max_msg_member - $user_profile[$memID]['posts'] * 3);
	}

	// Default to at least the ones we know about.
	$ips = array(
		$user_profile[$memID]['member_ip'],
		$user_profile[$memID]['member_ip2'],
	);

	// Get all IP addresses this user has used for his messages.
	$request = wesql::query('
		SELECT poster_ip, li.member_ip
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}log_ips AS li ON (m.poster_ip = li.id_ip)
		WHERE id_member = {int:current_member}
			AND poster_ip != 0' . (isset($min_msg_member) ? '
			AND id_msg >= {int:min_msg_member} AND id_msg <= {int:max_msg_member}' : '') . '
		GROUP BY poster_ip',
		array(
			'current_member' => $memID,
			'min_msg_member' => !empty($min_msg_member) ? $min_msg_member : 0,
			'max_msg_member' => !empty($max_msg_member) ? $max_msg_member : 0,
		)
	);
	$context['ips'] = array();
	$context['ip_ids'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$format_ip = format_ip($row['member_ip']);
		if (empty($format_ip))
			continue;

		$context['ips'][] = '<a href="<URL>?action=profile;u=' . $memID . ';area=tracking;sa=ip;searchip=' . $format_ip . '">' . $format_ip . '</a>';
		$ips[] = $row['poster_ip'];
	}
	wesql::free_result($request);

	// Now also get the IP addresses from the error messages.
	$request = wesql::query('
		SELECT COUNT(*) AS error_count, li.member_ip
		FROM {db_prefix}log_errors AS le
			LEFT JOIN {db_prefix}log_ips AS li ON (le.ip = li.id_ip)
		WHERE id_member = {int:current_member}
			AND le.ip != 0
		GROUP BY ip',
		array(
			'current_member' => $memID,
		)
	);
	$context['error_ips'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$row['ip'] = format_ip($row['member_ip']);
		$context['error_ips'][] = '<a href="<URL>?action=profile;u=' . $memID . ';area=tracking;sa=ip;searchip=' . $row['ip'] . '">' . $row['ip'] . '</a>';
		$ips[] = $row['ip'];
	}
	wesql::free_result($request);

	// Find other users that might use the same IP.
	$ips = array_unique($ips);
	$context['members_in_range'] = array();
	if (!empty($ips))
	{
		// Get member ID's which are in messages...
		$request = wesql::query('
			SELECT mem.id_member
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			WHERE m.poster_ip IN ({array_string:ip_list})
			GROUP BY mem.id_member
			HAVING mem.id_member != {int:current_member}',
			array(
				'current_member' => $memID,
				'ip_list' => $ips,
			)
		);
		$message_members = array();
		while ($row = wesql::fetch_assoc($request))
			$message_members[] = $row['id_member'];
		wesql::free_result($request);

		// Fetch their names, cause of the GROUP BY doesn't like giving us that normally.
		if (!empty($message_members))
		{
			$request = wesql::query('
				SELECT id_member, real_name
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:message_members})',
				array(
					'message_members' => $message_members,
					'ip_list' => $ips,
				)
			);
			while ($row = wesql::fetch_assoc($request))
				$context['members_in_range'][$row['id_member']] = '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
			wesql::free_result($request);
		}

		$request = wesql::query('
			SELECT id_member, real_name
			FROM {db_prefix}members
			WHERE id_member != {int:current_member}
				AND member_ip IN ({array_string:ip_list})',
			array(
				'current_member' => $memID,
				'ip_list' => $ips,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$context['members_in_range'][$row['id_member']] = '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name'] . '</a>';
		wesql::free_result($request);
	}
}

function list_getUserErrorCount($where, $where_vars = array())
{
	$request = wesql::query('
		SELECT COUNT(*) AS error_count
		FROM {db_prefix}log_errors
		WHERE ' . $where,
		$where_vars
	);
	list ($count) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $count;
}

function list_getUserErrors($start, $items_per_page, $sort, $where, $where_vars = array())
{
	global $txt;

	// Get a list of error messages from this ip (range).
	$request = wesql::query('
		SELECT
			le.log_time, le.ip, li.member_ip, le.url, le.message, IFNULL(mem.id_member, 0) AS id_member,
			IFNULL(mem.real_name, {string:guest_title}) AS display_name, mem.member_name
		FROM {db_prefix}log_errors AS le
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = le.id_member)
			LEFT JOIN {db_prefix}log_ips AS li ON (le.ip = li.id_ip)
		WHERE ' . $where . '
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array_merge($where_vars, array(
			'guest_title' => $txt['guest_title'],
		))
	);
	$error_messages = array();
	while ($row = wesql::fetch_assoc($request))
		$error_messages[] = array(
			'ip' => format_ip($row['member_ip']),
			'member_link' => $row['id_member'] > 0 ? '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>' : $row['display_name'],
			'message' => strtr($row['message'], array('&lt;span class=&quot;remove&quot;&gt;' => '', '&lt;/span&gt;' => '')),
			'url' => $row['url'],
			'time' => timeformat($row['log_time']),
			'timestamp' => forum_time(true, $row['log_time']),
		);
	wesql::free_result($request);

	return $error_messages;
}

function list_getIPMessageCount($where, $where_vars = array())
{
	$request = wesql::query('
		SELECT COUNT(*) AS message_count
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
		WHERE {query_see_board} AND ' . $where,
		$where_vars
	);
	list ($count) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $count;
}

function list_getIPMessages($start, $items_per_page, $sort, $where, $where_vars = array())
{
	global $txt;

	// Get all the messages fitting this where clause.
	// !!! SLOW This query is using a filesort.
	$request = wesql::query('
		SELECT
			m.id_msg, m.poster_ip, li.member_ip, IFNULL(mem.real_name, m.poster_name) AS display_name, mem.id_member,
			m.subject, m.poster_time, m.id_topic, m.id_board
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}log_ips AS li ON (m.poster_ip = li.id_ip)
		WHERE {query_see_board} AND ' . $where . '
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array_merge($where_vars, array(
		))
	);
	$messages = array();
	while ($row = wesql::fetch_assoc($request))
		$messages[] = array(
			'ip' => format_ip($row['member_ip']),
			'member_link' => empty($row['id_member']) ? $row['display_name'] : '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>',
			'board' => array(
				'id' => $row['id_board'],
				'href' => '<URL>?board=' . $row['id_board']
			),
			'topic' => $row['id_topic'],
			'id' => $row['id_msg'],
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time'])
		);
	wesql::free_result($request);

	return $messages;
}

function trackIP($memID = 0)
{
	global $user_profile, $txt, $settings, $context;

	// Can the user do this?
	isAllowedTo('manage_bans');

	if ($memID == 0)
	{
		$context['ip'] = we::$user['ip'];
		loadTemplate('Profile');
		loadLanguage('Profile');
		wetem::load('trackIP');
		$context['page_title'] = $txt['profile'];
		$context['base_url'] = '<URL>?action=trackip';
	}
	else
	{
		$context['ip'] = $user_profile[$memID]['member_ip'];
		$context['base_url'] = '<URL>?action=profile;u=' . $memID . ';area=tracking;sa=ip';
	}

	// Searching?
	if (isset($_REQUEST['searchip']))
		$context['ip'] = trim($_REQUEST['searchip']);

	// OK, let's validate this as some kind of IP.
	if (strpos($context['ip'], '*') !== false)
	{
		// It's a ranged one. Fix the ranges then expand and valiate.
		$testing_ip = str_replace('*', '0', $context['ip']);
		$testing_ip_exp = expand_ip($testing_ip);
		if ($testing_ip_exp == INVALID_IP)
			fatal_lang_error('invalid_tracking_ip', false);

		// Having established that it's ranged, we need to process it and build the SQL clauses.
		if (strpos($context['ip'], '.') !== false)
		{
			// It's IPv4.
			$ip_search = '00000000000000000000ffff';
			$blocks = explode('.', $context['ip']);
			for ($i = 0; $i <= 3; $i++)
				$ip_search .= ($blocks[$i] == '*') ? '%' : substr($testing_ip_exp, 24 + $i * 2, 2);
		}
		else
		{
			// It's IPv6. This is likely unreliable.
			$pieces = explode('::', $testing_ip);

			$before_pieces = explode(':', $pieces[0]);
			$after_pieces = explode(':', $pieces[1]);
			foreach ($before_pieces as $k => $v)
				if ($v == '')
					unset($before_pieces[$k]);
			foreach ($after_pieces as $k => $v)
				if ($v == '')
					unset($after_pieces[$k]);
			// Glue everything back together.
			$ip = preg_replace('~((?<!\:):$)~', '', $pieces[0] . (count($before_pieces) ? ':' : '') . str_repeat('0:', 8 - (count($before_pieces) + count($after_pieces))) . $pieces[1]);

			$ipv6 = explode(':', $ip);
			foreach ($ipv6 as $k => $v)
				$ipv6[$k] = $v != '*' ? str_pad($v, 4, '0', STR_PAD_LEFT) : '%';
			$ip_search = implode('', $ipv6);
		}

		// Now get all the IP ids.
		$query = wesql::query('
			SELECT id_ip
			FROM {db_prefix}log_ips
			WHERE member_ip LIKE {string:ip}',
			array(
				'ip' => $ip_search,
			)
		);
		if (wesql::num_rows($query) == 0)
		{
			$ip_var = 0;
			$ip_string = '1=0';
		}
		else
		{
			$ip_var = array();
			while ($row = wesql::fetch_row($query))
				$ip_var[] = $row[0];
			wesql::free_result($query);
			$ip_string = 'IN ({array_int:ip_address})';
		}
	}
	else
	{
		$testing_ip = expand_ip($context['ip']);
		// It's not ranged. But we do need to identify the specific ID that we want to use.
		$query = wesql::query('
			SELECT id_ip
			FROM {db_prefix}log_ips
			WHERE member_ip = {string:ip}',
			array(
				'ip' => $testing_ip,
			)
		);
		if (wesql::num_rows($query) == 1)
			list ($ip_var) = wesql::fetch_row($query);
		else
			$ip_var = -1;

		$ip_string = '= {int:ip_address}';

		wesql::free_result($query);
	}

	if (empty($context['tracking_area']))
		$context['page_title'] = $txt['trackIP'] . ' - ' . $context['ip'];

	$request = wesql::query('
		SELECT id_member, real_name AS display_name, member_ip
		FROM {db_prefix}members
		WHERE member_ip ' . $ip_string,
		array(
			'ip_address' => $ip_var,
		)
	);
	$context['ips'] = array();
	while ($row = wesql::fetch_assoc($request))
		$context['ips'][$row['member_ip']][] = '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['display_name'] . '</a>';
	wesql::free_result($request);

	ksort($context['ips']);

	// Gonna want this for the list.
	loadSource('Subs-List');

	// Start with the user messages.
	$listOptions = array(
		'id' => 'track_message_list',
		'title' => sprintf($txt['messages_from_ip'], $context['ip']),
		'start_var_name' => 'messageStart',
		'items_per_page' => $settings['defaultMaxMessages'],
		'no_items_label' => $txt['no_messages_from_ip'],
		'base_href' => $context['base_url'] . ';searchip=' . $context['ip'],
		'default_sort_col' => 'date',
		'get_items' => array(
			'function' => 'list_getIPMessages',
			'params' => array(
				'm.poster_ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'get_count' => array(
			'function' => 'list_getIPMessageCount',
			'params' => array(
				'm.poster_ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'columns' => array(
			'ip_address' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $context['base_url'] . ';searchip=%1$s">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'INET_ATON(m.poster_ip)',
					'reverse' => 'INET_ATON(m.poster_ip) DESC',
				),
			),
			'poster' => array(
				'header' => array(
					'value' => $txt['poster'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
			'subject' => array(
				'header' => array(
					'value' => $txt['subject'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="<URL>?topic=%1$s.msg%2$s#msg%2$s" rel="nofollow">%3$s</a>',
						'params' => array(
							'topic' => false,
							'id' => false,
							'subject' => false,
						),
					),
				),
			),
			'date' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'm.id_msg DESC',
					'reverse' => 'm.id_msg',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['messages_from_ip_desc'],
				'class' => 'smalltext',
				'style' => 'padding: 2ex;',
			),
		),
	);

	// Create the messages list.
	createList($listOptions);

	// Set the options for the error lists.
	$listOptions = array(
		'id' => 'track_user_list',
		'title' => sprintf($txt['errors_from_ip'], $context['ip']),
		'start_var_name' => 'errorStart',
		'items_per_page' => $settings['defaultMaxMessages'],
		'no_items_label' => $txt['no_errors_from_ip'],
		'base_href' => $context['base_url'] . ';searchip=' . $context['ip'],
		'default_sort_col' => 'date2',
		'get_items' => array(
			'function' => 'list_getUserErrors',
			'params' => array(
				'le.ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'get_count' => array(
			'function' => 'list_getUserErrorCount',
			'params' => array(
				'ip ' . $ip_string,
				array('ip_address' => $ip_var),
			),
		),
		'columns' => array(
			'ip_address2' => array(
				'header' => array(
					'value' => $txt['ip_address'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<a href="' . $context['base_url'] . ';searchip=%1$s">%1$s</a>',
						'params' => array(
							'ip' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'INET_ATON(le.ip)',
					'reverse' => 'INET_ATON(le.ip) DESC',
				),
			),
			'display_name' => array(
				'header' => array(
					'value' => $txt['display_name'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
			'message' => array(
				'header' => array(
					'value' => $txt['message'],
				),
				'data' => array(
					'sprintf' => array(
						'format' => '%1$s<br><a href="%2$s">%2$s</a>',
						'params' => array(
							'message' => false,
							'url' => false,
						),
					),
				),
			),
			'date2' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'le.id_error DESC',
					'reverse' => 'le.id_error',
				),
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'after_title',
				'value' => $txt['errors_from_ip_desc'],
				'class' => 'smalltext',
				'style' => 'padding: 2ex;',
			),
		),
	);

	// Create the error list.
	createList($listOptions);

	call_hook('track_ip', array(&$ip_string, &$ip_var));

	$context['single_ip'] = strpos($context['ip'], '*') === false;
	if ($context['single_ip'])
	{
		// Data is believed to be valid as of June 21st, 2012, copied manually from:
		// http://www.iana.org/assignments/ipv4-address-space/ipv4-address-space.xml
		$context['whois_servers'] = array(
			'afrinic' => array(
				'name' => $txt['whois_afrinic'],
				'url' => 'http://www.afrinic.net/cgi-bin/whois?searchtext=' . $context['ip'],
				'range' => array(41, 102, 105, 154, 196, 197),
			),
			'apnic' => array(
				'name' => $txt['whois_apnic'],
				'url' => 'http://wq.apnic.net/apnic-bin/whois.pl?searchtext=' . $context['ip'],
				'range' => array(1, 14, 27, 36, 39, 42, 49, 58, 59, 60, 61, 101, 103, 106, 110, 111, 112, 113, 114,
					115, 116, 117, 118, 119, 120, 121, 122, 123, 124, 125, 126, 133, 150, 153, 163, 171, 175, 180,
					182, 183, 202, 203, 210, 211, 218, 219, 220, 221, 222, 223),
			),
			'arin' => array(
				'name' => $txt['whois_arin'],
				'url' => 'http://whois.arin.net/rest/ip/' . $context['ip'],
				'range' => array(7, 23, 24, 45, 50, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 96,
					97, 98, 99, 100, 104, 107, 108, 128, 129, 130, 131, 132, 134, 135, 136, 137, 138, 139, 140,
					142, 143, 144, 146, 147, 148, 149, 152, 155, 156, 157, 158, 159, 160, 161, 162, 164, 165, 166,
					167, 168, 169, 170, 172, 173, 174, 184, 192, 198, 199, 204, 205, 206, 207, 208, 209, 216),
			),
			'lacnic' => array(
				'name' => $txt['whois_lacnic'],
				'url' => 'http://lacnic.net/cgi-bin/lacnic/whois?query=' . $context['ip'],
				'range' => array(177, 179, 181, 186, 187, 189, 190, 191, 200, 201),
			),
			'ripe' => array(
				'name' => $txt['whois_ripe'],
				'url' => 'http://www.db.ripe.net/whois?searchtext=' . $context['ip'],
				'range' => array(2, 5, 31, 37, 46, 62, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90,
					91, 92, 93, 94, 95, 109, 141, 145, 151, 176, 178, 185, 188, 193, 194, 195, 212, 213, 217),
			),
		);

		foreach ($context['whois_servers'] as $whois)
		{
			// Strip off the "decimal point" and anything following...
			if (in_array((int) $context['ip'], $whois['range']))
				$context['auto_whois_server'] = $whois;
		}
	}
}

function trackEdits($memID)
{
	global $txt, $settings, $context;

	loadSource('Subs-List');

	// Get the names of any custom fields.
	$request = wesql::query('
		SELECT col_name, field_name, bbc
		FROM {db_prefix}custom_fields',
		array(
		)
	);
	$context['custom_field_titles'] = array();
	while ($row = wesql::fetch_assoc($request))
		$context['custom_field_titles']['customfield_' . $row['col_name']] = array(
			'title' => $row['field_name'],
			'parse_bbc' => $row['bbc'],
		);
	wesql::free_result($request);

	// Set the options for the error lists.
	$listOptions = array(
		'id' => 'edit_list',
		'title' => $txt['trackEdits'],
		'items_per_page' => $settings['defaultMaxMessages'],
		'no_items_label' => $txt['trackEdit_no_edits'],
		'base_href' => '<URL>?action=profile;u=' . $memID . ';area=tracking;sa=edits',
		'default_sort_col' => 'time',
		'get_items' => array(
			'function' => 'list_getProfileEdits',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getProfileEditCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'action' => array(
				'header' => array(
					'value' => $txt['trackEdit_action'],
				),
				'data' => array(
					'db' => 'action_text',
				),
			),
			'before' => array(
				'header' => array(
					'value' => $txt['trackEdit_before'],
				),
				'data' => array(
					'db' => 'before',
				),
			),
			'after' => array(
				'header' => array(
					'value' => $txt['trackEdit_after'],
				),
				'data' => array(
					'db' => 'after',
				),
			),
			'time' => array(
				'header' => array(
					'value' => $txt['date'],
				),
				'data' => array(
					'db' => 'time',
				),
				'sort' => array(
					'default' => 'id_action DESC',
					'reverse' => 'id_action',
				),
			),
			'applicator' => array(
				'header' => array(
					'value' => $txt['trackEdit_applicator'],
				),
				'data' => array(
					'db' => 'member_link',
				),
			),
		),
	);

	// Create the error list.
	createList($listOptions);

	wetem::load('show_list');
	$context['default_list'] = 'edit_list';
}

// How many edits?
function list_getProfileEditCount($memID)
{
	$request = wesql::query('
		SELECT COUNT(*) AS edit_count
		FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member = {int:owner}',
		array(
			'log_type' => 2,
			'owner' => $memID,
		)
	);
	list ($edit_count) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $edit_count;
}

function list_getProfileEdits($start, $items_per_page, $sort, $memID)
{
	global $txt, $context;

	// Get a list of error messages from this ip (range).
	$request = wesql::query('
		SELECT
			id_action, id_member, ip, log_time, action, extra
		FROM {db_prefix}log_actions
		WHERE id_log = {int:log_type}
			AND id_member = {int:owner}
		ORDER BY ' . $sort . '
		LIMIT ' . $start . ', ' . $items_per_page,
		array(
			'log_type' => 2,
			'owner' => $memID,
		)
	);
	$edits = array();
	$members = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$extra = @unserialize($row['extra']);
		if (!empty($extra['applicator']))
			$members[] = $extra['applicator'];

		// Work out what the name of the action is.
		if (isset($txt['trackEdit_action_' . $row['action']]))
			$action_text = $txt['trackEdit_action_' . $row['action']];
		elseif (isset($txt[$row['action']]))
			$action_text = $txt[$row['action']];
		// Custom field?
		elseif (isset($context['custom_field_titles'][$row['action']]))
			$action_text = $context['custom_field_titles'][$row['action']]['title'];
		else
			$action_text = $row['action'];

		// Parse BBC?
		$parse_bbc = isset($context['custom_field_titles'][$row['action']]) && $context['custom_field_titles'][$row['action']]['parse_bbc'] ? true : false;

		$edits[] = array(
			'id' => $row['id_action'],
			'ip' => format_ip($row['ip']),
			'id_member' => !empty($extra['applicator']) ? $extra['applicator'] : 0,
			'member_link' => $txt['trackEdit_deleted_member'],
			'action' => $row['action'],
			'action_text' => $action_text,
			'before' => !empty($extra['previous']) ? ($parse_bbc ? parse_bbc($extra['previous'], 'custom-field') : $extra['previous']) : '',
			'after' => !empty($extra['new']) ? ($parse_bbc ? parse_bbc($extra['new'], 'custom-field') : $extra['new']) : '',
			'time' => timeformat($row['log_time']),
		);
	}
	wesql::free_result($request);

	// Get any member names.
	if (!empty($members))
	{
		$request = wesql::query('
			SELECT
				id_member, real_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:members})',
			array(
				'members' => $members,
			)
		);
		$members = array();
		while ($row = wesql::fetch_assoc($request))
			$members[$row['id_member']] = $row['real_name'];
		wesql::free_result($request);

		foreach ($edits as $key => $value)
			if (isset($members[$value['id_member']]))
				$edits[$key]['member_link'] = '<a href="<URL>?action=profile;u=' . $value['id_member'] . '">' . $members[$value['id_member']] . '</a>';
	}

	return $edits;
}

function showPermissions($memID)
{
	global $txt, $board, $user_profile, $context;

	// Verify if the user has sufficient permissions.
	isAllowedTo('manage_permissions');

	loadLanguage('ManagePermissions');
	loadLanguage('Admin');
	loadTemplate('ManageMembers');

	// Load all the permission profiles.
	loadSource('ManagePermissions');
	loadPermissionProfiles();

	$context['member']['id'] = $memID;
	$context['member']['name'] = $user_profile[$memID]['real_name'];

	$context['page_title'] = $txt['showPermissions'];
	$board = empty($board) ? 0 : (int) $board;
	$context['board'] = $board;

	// Determine which groups this user is in.
	if (empty($user_profile[$memID]['additional_groups']))
		$curGroups = array();
	else
		$curGroups = explode(',', $user_profile[$memID]['additional_groups']);
	$curGroups[] = $user_profile[$memID]['id_group'];
	$curGroups[] = $user_profile[$memID]['id_post_group'];

	// Load a list of boards for the jump box - except the defaults.
	$request = wesql::query('
		SELECT b.id_board, b.name, b.id_profile, b.member_groups, IFNULL(mods.id_member, 0) AS is_mod
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})
		WHERE {query_see_board}',
		array(
			'current_member' => $memID,
		)
	);
	$context['boards'] = array();
	$context['no_access_boards'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		if (count(array_intersect($curGroups, explode(',', $row['member_groups']))) === 0 && !$row['is_mod'])
			$context['no_access_boards'][] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'is_last' => false,
			);
		elseif ($row['id_profile'] != 1 || $row['is_mod'])
			$context['boards'][$row['id_board']] = array(
				'id' => $row['id_board'],
				'name' => $row['name'],
				'selected' => $board == $row['id_board'],
				'profile' => $row['id_profile'],
				'profile_name' => $context['profiles'][$row['id_profile']]['name'],
			);
	}
	wesql::free_result($request);

	if (!empty($context['no_access_boards']))
		$context['no_access_boards'][count($context['no_access_boards']) - 1]['is_last'] = true;

	$context['member']['permissions'] = array(
		'general' => array(),
		'board' => array()
	);

	// If you're an admin we know you can do everything, we might as well leave.
	$context['member']['has_all_permissions'] = in_array(1, $curGroups);
	if ($context['member']['has_all_permissions'])
		return;

	$denied = array();

	// Get all general permissions.
	$result = wesql::query('
		SELECT p.permission, p.add_deny, mg.group_name, p.id_group
		FROM {db_prefix}permissions AS p
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = p.id_group)
		WHERE p.id_group IN ({array_int:group_list})
		ORDER BY p.add_deny DESC, p.permission, mg.min_posts, CASE WHEN mg.id_group < {int:newbie_group} THEN mg.id_group ELSE 4 END, mg.group_name',
		array(
			'group_list' => $curGroups,
			'newbie_group' => 4,
		)
	);
	while ($row = wesql::fetch_assoc($result))
	{
		// We don't know about this permission, it doesn't exist :P.
		if (!isset($txt['permissionname_' . $row['permission']]))
			continue;

		if (empty($row['add_deny']))
			$denied[] = $row['permission'];

		// Permissions that end with _own or _any consist of two parts.
		if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
			$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
		else
			$name = $txt['permissionname_' . $row['permission']];

		// Add this permission if it doesn't exist yet.
		if (!isset($context['member']['permissions']['general'][$row['permission']]))
			$context['member']['permissions']['general'][$row['permission']] = array(
				'id' => $row['permission'],
				'groups' => array(
					'allowed' => array(),
					'denied' => array()
				),
				'name' => $name,
				'is_denied' => false,
				'is_global' => true,
			);

		// Add the membergroup to either the denied or the allowed groups.
		$context['member']['permissions']['general'][$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][] = $row['id_group'] == 0 ? $txt['membergroups_members'] : $row['group_name'];

		// Once denied is always denied.
		$context['member']['permissions']['general'][$row['permission']]['is_denied'] |= empty($row['add_deny']);
	}
	wesql::free_result($result);

	$request = wesql::query('
		SELECT
			bp.add_deny, bp.permission, bp.id_group, mg.group_name' . (empty($board) ? '' : ',
			b.id_profile, CASE WHEN mods.id_member IS NULL THEN 0 ELSE 1 END AS is_moderator') . '
		FROM {db_prefix}board_permissions AS bp' . (empty($board) ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = {int:current_board})
			LEFT JOIN {db_prefix}moderators AS mods ON (mods.id_board = b.id_board AND mods.id_member = {int:current_member})') . '
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = bp.id_group)
		WHERE bp.id_profile = {raw:current_profile}
			AND bp.id_group IN ({array_int:group_list}' . (empty($board) ? ')' : ', {int:moderator_group})
			AND (mods.id_member IS NOT NULL OR bp.id_group != {int:moderator_group})'),
		array(
			'current_board' => $board,
			'group_list' => $curGroups,
			'current_member' => $memID,
			'current_profile' => empty($board) ? '1' : 'b.id_profile',
			'moderator_group' => 3,
		)
	);

	while ($row = wesql::fetch_assoc($request))
	{
		// We don't know about this permission, it doesn't exist :P.
		if (!isset($txt['permissionname_' . $row['permission']]))
			continue;

		// The name of the permission using the format 'permission name' - 'own/any topic/etc.'.
		if (in_array(substr($row['permission'], -4), array('_own', '_any')) && isset($txt['permissionname_' . substr($row['permission'], 0, -4)]))
			$name = $txt['permissionname_' . substr($row['permission'], 0, -4)] . ' - ' . $txt['permissionname_' . $row['permission']];
		else
			$name = $txt['permissionname_' . $row['permission']];

		// Create the structure for this permission.
		if (!isset($context['member']['permissions']['board'][$row['permission']]))
			$context['member']['permissions']['board'][$row['permission']] = array(
				'id' => $row['permission'],
				'groups' => array(
					'allowed' => array(),
					'denied' => array()
				),
				'name' => $name,
				'is_denied' => false,
				'is_global' => empty($board),
			);

		$context['member']['permissions']['board'][$row['permission']]['groups'][empty($row['add_deny']) ? 'denied' : 'allowed'][$row['id_group']] = $row['id_group'] == 0 ? $txt['membergroups_members'] : $row['group_name'];

		$context['member']['permissions']['board'][$row['permission']]['is_denied'] |= empty($row['add_deny']);
	}
	wesql::free_result($request);
}
