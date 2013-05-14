<?php
/**
 * Wedge
 *
 * Initializes the profile area for Wedge and routes the request appropriately.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file has the primary job of showing and editing people's profiles.
	It also allows the user to change some of their or another's preferences,
	and such things. It uses the following functions:

	void ModifyProfile(array errors = none)
		// !!!

	void loadCustomFields(int id_member, string area)
		// !!!

*/

// Allow the change or view of profiles...
function ModifyProfile($post_errors = array())
{
	global $txt, $context, $user_profile, $cur_profile;
	global $settings, $memberContext, $profile_vars, $post_errors, $options;

	// Don't reload this as we may have processed error strings.
	if (empty($post_errors))
		loadLanguage('Profile');
	loadTemplate('Profile');
	add_js_file('scripts/profile.js');

	// If we only want to see guest posts, use this shortcut.
	if (isset($_REQUEST['guest']))
	{
		loadSource('Profile-View');
		wetem::load('showPosts');

		$context['profile_menu_name'] = 'dummy_menu';
		we::$user['is_owner'] = allowedTo('moderate_forum'); // !! or we::$is_admin..?
		showPosts(0);
		return;
	}

	loadSource('Subs-Menu');

	// Did we get the user by name...
	if (isset($_REQUEST['user']))
		$memberResult = loadMemberData($_REQUEST['user'], true, 'profile');
	// ... or by id_member?
	elseif (!empty($_REQUEST['u']))
		$memberResult = loadMemberData((int) $_REQUEST['u'], false, 'profile');
	// If it was just ?action=profile, edit your own profile.
	else
		$memberResult = loadMemberData(we::$id, false, 'profile');

	// Check if loadMemberData() has returned a valid result.
	if (!is_array($memberResult))
		fatal_lang_error('not_a_user', false);

	// If all went well, we have a valid member ID!
	list ($memID) = $memberResult;
	$context['id_member'] = $memID;
	$cur_profile = $user_profile[$memID];

	// Let's have some information about this member ready, too.
	loadMemberContext($memID);
	$context['member'] = $memberContext[$memID];

	// Is this the profile of the user himself or herself?
	we::$user['is_owner'] = $memID == we::$id;

	/* Define all the sections within the profile area!
		We start by defining the permission required - then Wedge takes this and turns it into the relevant context ;)
		Possible fields:
			For Section:
				string $title:		Section title.
				array $areas:		Array of areas within this section.

			For Areas:
				string $label:		Text string that will be used to show the area in the menu.
				string $file:		Optional text string that may contain a file name that's needed for inclusion in order to display the area properly.
				string $custom_url:	Optional href for area.
				string $function:	Function to execute for this section.
				bool $enabled:		Should area be shown?
				string $sc:		Session check validation to do on save - note without this save will get unset - if set.
				bool $hidden:		Does this not actually appear on the menu?
				bool $password:		Whether to require the user's password in order to save the data in the area.
				array $subsections:	Array of subsections, in order of appearance.
				array $permission:	Array of permissions to determine who can access this area. Should contain arrays $own and $any.
	*/

	$temp = boardsAllowedTo('save_post_draft');

	$profile_areas = array(
		'info' => array(
			'title' => $txt['profileInfo'],
			'areas' => array(
				'summary' => array(
					'label' => $txt['summary'],
					'file' => 'Profile-View',
					'function' => 'summary',
					'permission' => array(
						'own' => 'profile_view_own',
						'any' => 'profile_view_any',
					),
				),
				'',
				'showdrafts' => array(
					'label' => $txt['showDrafts'],
					'file' => 'Profile-View',
					'function' => 'showDrafts',
					'enabled' => !empty($settings['masterSavePostDrafts']) && !empty($temp), // so there is at least one board this user has this permission on
					'permission' => array(
						'own' => 'profile_view_own',
						'any' => array(),
					),
				),
				'thoughts' => array(
					'label' => $txt['showThoughts'],
					'file' => 'Thoughts',
					'function' => 'latestThoughts',
					'enabled' => true, // @todo: add a counter and enable based on it being non-zero?
					'permission' => array(
						'own' => 'profile_view_own',
						'any' => 'profile_view_any',
					),
				),
				'showposts' => array(
					'label' => $txt['showPosts'],
					'file' => 'Profile-View',
					'function' => 'showPosts',
					'subsections' => array(
						'messages' => array($txt['showMessages'], array('profile_view_own', 'profile_view_any')),
						'topics' => array($txt['showTopics'], array('profile_view_own', 'profile_view_any')),
						'attach' => array($txt['showAttachments'], array('profile_view_own', 'profile_view_any'), 'enabled' => !empty($settings['attachmentEnable'])),
					),
					'permission' => array(
						'own' => 'profile_view_own',
						'any' => 'profile_view_any',
					),
				),
				'statistics' => array(
					'label' => $txt['statPanel'],
					'file' => 'Profile-View',
					'function' => 'statPanel',
					'permission' => array(
						'own' => 'profile_view_own',
						'any' => 'profile_view_any',
					),
				),
			),
		),
		'edit_profile' => array(
			'title' => $txt['profileEdit'],
			'areas' => array(
				'forumprofile' => array(
					'label' => $txt['forumprofile'],
					'file' => 'Profile-Modify',
					'function' => 'forumProfile',
					'sc' => 'post',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own', 'profile_title_own', 'profile_title_any'),
						'any' => array('profile_extra_any', 'profile_title_any'),
					),
				),
				'options' => array(
					'label' => $txt['options'],
					'file' => 'Profile-Modify',
					'function' => 'options',
					'sc' => 'post',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array('profile_extra_any'),
					),
				),
				'',
				'skin' => array(
					'label' => $txt['change_skin'],
					'enabled' => !empty($settings['theme_allow']) || allowedTo('admin_forum'),
					'custom_url' => '<URL>?action=skin;u=' . $memID,
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array('profile_extra_any'),
					),
				),
				'',
				'notification' => array(
					'label' => $txt['notification'],
					'file' => 'Profile-Modify',
					'function' => 'notification',
					'sc' => 'post',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array('profile_extra_any'),
					),
				),
				'notifications' => array(
					'label' => $txt['notifications'],
					'enabled' => true,
					'function' => 'weNotif_profile',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
					),
				),
				// Without profile_extra_own, settings are accessible from the PM section.
				'pmprefs' => array(
					'label' => $txt['pmprefs'],
					'file' => 'Profile-Modify',
					'function' => 'pmprefs',
					'enabled' => allowedTo(array('profile_extra_own', 'profile_extra_any')),
					'sc' => 'post',
					'permission' => array(
						'own' => array('pm_read'),
						'any' => array('profile_extra_any'),
					),
				),
				'ignoreboards' => array(
					'label' => $txt['ignoreboards'],
					'file' => 'Profile-Modify',
					'function' => 'ignoreboards',
					'enabled' => !empty($settings['ignorable_boards']),
					'sc' => 'post',
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array('profile_extra_any'),
					),
				),
				'lists' => array(
					'label' => $txt['editBuddyIgnoreLists'],
					'file' => 'Profile-Modify',
					'function' => 'editBuddyIgnoreLists',
					'enabled' => !empty($settings['enable_buddylist']) && we::$user['is_owner'],
					'sc' => 'post',
					'subsections' => array(
						'buddies' => array($txt['editBuddies']),
						'ignore' => array($txt['editIgnoreList']),
					),
					'permission' => array(
						'own' => array('profile_extra_any', 'profile_extra_own'),
						'any' => array(),
					),
				),
			),
		),
		'aeva' => array(
			'title' => $txt['media_gallery'],
			'areas' => array(
				'aeva' => array(
					'file' => 'media/Aeva-Gallery2',
					'function' => 'aeva_profileSummary',
					'label' => $txt['media_profile_sum'],
					'permission' => array(
						'own' => array('media_viewprofile'),
						'any' => array('media_viewprofile'),
					),
					'load_member' => true,
				),
				'aevaitems' => array(
					'file' => 'media/Aeva-Gallery2',
					'function' => 'aeva_profileItems',
					'label' => $txt['media_view_items'],
					'permission' => array(
						'own' => array('media_viewprofile'),
						'any' => array('media_viewprofile'),
					),
					'load_member' => true,
				),
				'aevacoms' => array(
					'file' => 'media/Aeva-Gallery2',
					'function' => 'aeva_profileComments',
					'label' => $txt['media_view_coms'],
					'permission' => array(
						'own' => array('media_viewprofile'),
						'any' => array('media_viewprofile'),
					),
					'load_member' => true,
				),
				'aevavotes' => array(
					'file' => 'media/Aeva-Gallery2',
					'function' => 'aeva_profileVotes',
					'label' => $txt['media_view_votes'],
					'permission' => array(
						'own' => array('media_viewprofile'),
						'any' => array('media_viewprofile'),
					),
					'load_member' => true,
				),
			),
		),
		'profile_manage' => array(
			'title' => $txt['manage_account'],
			'areas' => array(
				'sendpm' => array(
					'label' => $txt['profileSendIm'],
					'custom_url' => '<URL>?action=pm;sa=send;u=' . $memID,
					'permission' => array(
						'own' => array(),
						'any' => array('pm_send'),
					),
				),
				'account' => array(
					'label' => $txt['account'],
					'file' => 'Profile-Modify',
					'function' => 'account',
					'enabled' => we::$is_admin || ($cur_profile['id_group'] != 1 && !in_array(1, explode(',', $cur_profile['additional_groups']))),
					'sc' => 'post',
					'password' => true,
					'permission' => array(
						'own' => array('profile_identity_any', 'profile_identity_own', 'manage_membergroups'),
						'any' => array('profile_identity_any', 'manage_membergroups'),
					),
				),
				'subscriptions' => array(
					'label' => $txt['subscriptions'],
					'file' => 'Profile-Actions',
					'function' => 'subscriptions',
					'enabled' => !empty($settings['paid_enabled']),
					'permission' => array(
						'own' => array('profile_view_own'),
						'any' => array('moderate_forum'),
					),
				),
				'groupmembership' => array(
					'label' => $txt['groupmembership'],
					'file' => 'Profile-Modify',
					'function' => 'groupMembership',
					'enabled' => !empty($settings['show_group_membership']) && we::$user['is_owner'],
					'sc' => 'request',
					'permission' => array(
						'own' => array('profile_view_own'),
						'any' => array('manage_membergroups'),
					),
				),
				'',
				'permissions' => array(
					'label' => $txt['showPermissions'],
					'file' => 'Profile-View',
					'function' => 'showPermissions',
					'permission' => array(
						'own' => 'manage_permissions',
						'any' => 'manage_permissions',
					),
				),
				'tracking' => array(
					'label' => $txt['trackUser'],
					'file' => 'Profile-View',
					'function' => 'tracking',
					'subsections' => array(
						'activity' => array($txt['trackActivity'], 'manage_bans'),
						'ip' => array($txt['trackIP'], 'manage_bans'),
						'edits' => array($txt['trackEdits'], 'moderate_forum'),
					),
					'permission' => array(
						'own' => 'moderate_forum',
						'any' => 'moderate_forum',
					),
				),
				'',
				'activateaccount' => array(
					'file' => 'Profile-Actions',
					'function' => 'activateAccount',
					'sc' => 'get',
					'select' => 'summary',
					'permission' => array(
						'own' => array(),
						'any' => array('moderate_forum'),
					),
				),
				'infractions' => array(
					'label' => $txt['profile_infractions'],
					'file' => 'Profile-Actions',
					'function' => 'profileInfractions',
					'permission' => array(
						'own' => array('profile_view_own'),
						'any' => array('issue_warning'),
					),
				),
				'banuser' => array(
					'label' => $txt['profileBanUser'],
					'file' => 'Profile-Actions',
					'function' => 'profileBan',
					'enabled' => $cur_profile['id_group'] != 1 && !in_array(1, explode(',', $cur_profile['additional_groups'])),
					'permission' => array(
						'own' => array(),
						'any' => array('manage_bans'),
					),
				),
				'deleteaccount' => array(
					'label' => $txt['deleteAccount'],
					'file' => 'Profile-Actions',
					'function' => 'deleteAccount',
					'sc' => 'post',
					'password' => true,
					'permission' => array(
						'own' => array('profile_remove_any', 'profile_remove_own'),
						'any' => array('profile_remove_any'),
					),
				),
			),
		),
	);

	// Let modders modify profile areas easily.
	call_hook('profile_areas', array(&$profile_areas));

	if (empty($settings['media_enabled']))
		unset($profile_areas['aeva']);

	// Do some cleaning ready for the menu function.
	$context['password_areas'] = array();
	$current_area = isset($_REQUEST['area']) ? $_REQUEST['area'] : '';

	foreach ($profile_areas as $section_id => &$section)
	{
		// Do a bit of spring cleaning so to speak.
		foreach ($section['areas'] as $area_id => &$area)
		{
			if (is_numeric($area_id))
				continue;
			// If it said no permissions that meant it wasn't valid!
			if (empty($area['permission'][we::$user['is_owner'] ? 'own' : 'any']))
				$area['enabled'] = false;
			// Otherwise pick the right set.
			else
				$area['permission'] = $area['permission'][we::$user['is_owner'] ? 'own' : 'any'];

			// Password required?
			if (!empty($area['password']))
				$context['password_areas'][] = $area_id;
		}
	}
	unset($area, $section);

	// Is there an updated message to show?
	if (isset($_GET['updated']))
		$context['profile_updated'] = $txt['profile_updated_own'];

	// Set a few options for the menu.
	$menuOptions = array(
		'disable_url_session_check' => true,
		'current_area' => $current_area,
		'action' => 'profile' . (we::$user['is_owner'] ? '' : ';u=' . $memID),
	);

	// Actually create the menu!
	$profile_include_data = createMenu($profile_areas, $menuOptions);

	// No menu means no access.
	if (!$profile_include_data && (we::$is_member || validateSession()))
		fatal_lang_error('no_access', false);

	// Make a note of the Unique ID for this menu.
	$context['profile_menu_id'] = $context['max_menu_id'];
	$context['profile_menu_name'] = 'menu_data_' . $context['profile_menu_id'];

	// Set the selected item - now it's been validated.
	$current_area = $profile_include_data['current_area'];
	$context['menu_item_selected'] = $current_area;

	// Before we go any further, let's work on the area we've said is valid. Note this is done here just in case we every compromise the menu function in error!
	$context['completed_save'] = false;
	$security_checks = array();
	$found_area = false;
	foreach ($profile_areas as $section_id => $section)
	{
		// Do a bit of spring cleaning so to speak.
		foreach ($section['areas'] as $area_id => $area)
		{
			// Is this our area?
			if ($current_area == $area_id)
			{
				// This can't happen - but is a security check.
				if ((isset($section['enabled']) && $section['enabled'] == false) || (isset($area['enabled']) && $area['enabled'] == false))
					fatal_lang_error('no_access', false);

				// Are we saving data in a valid area?
				if (isset($area['sc'], $_REQUEST['save']))
				{
					$security_checks['session'] = $area['sc'];
					$context['completed_save'] = true;
				}

				// Does this require session validating?
				if (!empty($area['validate']))
					$security_checks['validate'] = true;

				// Permissions for good measure.
				if (!empty($profile_include_data['permission']))
					$security_checks['permission'] = $profile_include_data['permission'];

				// Either way got something.
				$found_area = true;
			}
		}
	}

	// Oh dear, some serious security lapse is going on here... we'll put a stop to that!
	if (!$found_area)
		fatal_lang_error('no_access', false);

	// Release this now.
	unset($profile_areas);

	// Now the context is setup have we got any security checks to carry out additional to that above?
	if (isset($security_checks['session']))
		checkSession($security_checks['session']);
	if (isset($security_checks['validate']))
		validateSession();
	if (isset($security_checks['permission']))
		isAllowedTo($security_checks['permission']);

	// File to include?
	if (isset($profile_include_data['file']))
	{
		if (is_array($profile_include_data['file']))
			loadPluginSource($profile_include_data['file'][0], $profile_include_data['file'][1]);
		else
			loadSource($profile_include_data['file']);
	}

	// Make sure that the area function does exist!
	if (!isset($profile_include_data['function']) || !function_exists($profile_include_data['function']))
	{
		destroyMenu();
		fatal_lang_error('no_access', false);
	}

	// Build the link tree.
	add_linktree(sprintf($txt['profile_of_username'], $context['member']['name']), '<URL>?action=profile' . ($memID != we::$id ? ';u=' . $memID : ''));

	if (!empty($profile_include_data['label']))
		add_linktree($profile_include_data['label'], '<URL>?action=profile' . ($memID != we::$id ? ';u=' . $memID : '') . ';area=' . $profile_include_data['current_area']);

	if (!empty($profile_include_data['current_subsection']) && $profile_include_data['subsections'][$profile_include_data['current_subsection']][0] != $profile_include_data['label'])
		add_linktree($profile_include_data['subsections'][$profile_include_data['current_subsection']][0], '<URL>?action=profile' . ($memID != we::$id ? ';u=' . $memID : '') . ';area=' . $profile_include_data['current_area'] . ';sa=' . $profile_include_data['current_subsection']);

	// Set the template for this area and add the profile layer.
	wetem::load($profile_include_data['function']);
	wetem::add(array('top', 'default'), 'profile_top');

	// All the subactions that require a user password in order to validate.
	$check_password = we::$user['is_owner'] && in_array($profile_include_data['current_area'], $context['password_areas']);
	$context['require_password'] = $check_password;

	// These will get populated soon!
	$post_errors = array();
	$profile_vars = array();

	// Right - are we saving - if so let's save the old data first.
	if ($context['completed_save'])
	{
		// If it's someone elses profile then validate the session.
		if (!we::$user['is_owner'])
			validateSession();

		// Clean up the POST variables.
		$_POST = htmltrim__recursive($_POST);
		$_POST = htmlspecialchars__recursive($_POST);

		if ($check_password)
		{
			// You didn't even enter a password!
			if (trim($_POST['oldpasswrd']) == '')
				$post_errors[] = 'no_password';

			// Since the password got modified due to all the $_POST cleaning, let's undo it so we can get the correct password
			$_POST['oldpasswrd'] = un_htmlspecialchars($_POST['oldpasswrd']);

			// Does a hook want to check passwords?
			$good_password = in_array(true, call_hook('verify_password', array($cur_profile['member_name'], $_POST['oldpasswrd'], false)), true);

			// Bad password!!!
			if (!$good_password && we::$user['passwd'] != sha1(strtolower($cur_profile['member_name']) . $_POST['oldpasswrd']))
				$post_errors[] = 'bad_password';

			// Warn other elements not to jump the gun and do custom changes!
			if (in_array('bad_password', $post_errors))
				$context['password_auth_failed'] = true;
		}

		// Change the IP address in the database.
		if (we::$user['is_owner'])
			$profile_vars['member_ip'] = we::$user['ip'];

		// Now call the sub-action function...
		if ($current_area == 'activateaccount')
		{
			if (empty($post_errors))
				activateAccount($memID);
		}
		elseif ($current_area == 'deleteaccount')
		{
			if (empty($post_errors))
			{
				deleteAccount2($profile_vars, $post_errors, $memID);
				redirectexit();
			}
		}
		elseif ($current_area == 'groupmembership' && empty($post_errors))
		{
			$msg = groupMembership2($profile_vars, $post_errors, $memID);

			// Whatever we've done, we have nothing else to do here...
			redirectexit('action=profile' . (we::$user['is_owner'] ? '' : ';u=' . $memID) . ';area=groupmembership' . (!empty($msg) ? ';msg=' . $msg : ''));
		}
		elseif (in_array($current_area, array('account', 'forumprofile', 'options', 'pmprefs')))
			saveProfileFields();
		else
		{
			$force_redirect = true;
			// Ensure we include this.
			loadSource('Profile-Modify');
			saveProfileChanges($profile_vars, $post_errors, $memID);
		}

		// There was a problem, let them try to re-enter.
		if (!empty($post_errors))
		{
			// Load the language file so we can give a nice explanation of the errors.
			loadLanguage('Errors');
			$context['post_error'] = $post_errors;
		}
		elseif (!empty($profile_vars))
		{
			// If we've changed the password, notify any hook that may be listening in.
			if (isset($profile_vars['passwd']))
				call_hook('reset_pass', array($cur_profile['member_name'], $cur_profile['member_name'], $_POST['passwrd2']));

			updateMemberData($memID, $profile_vars);

			// What if this is the newest member?
			if ($settings['latestMember'] == $memID)
				updateStats('member');
			elseif (isset($profile_vars['real_name']))
				updateSettings(array('memberlist_updated' => time()));

			// Anything worth logging?
			if (!empty($context['log_changes']) && !empty($settings['log_enabled_profile']))
			{
				$log_changes = array();
				foreach ($context['log_changes'] as $k => $v)
					$log_changes[] = array(
						'action' => $k,
						'id_log' => 2,
						'log_time' => time(),
						'id_member' => $memID,
						'ip' => get_ip_identifier(we::$user['ip']),
						'extra' => serialize(array_merge($v, array('applicator' => we::$id))),
					);

				wesql::insert('',
					'{db_prefix}log_actions',
					array(
						'action' => 'string', 'id_log' => 'int', 'log_time' => 'int', 'id_member' => 'int', 'ip' => 'int',
						'extra' => 'string-65534',
					),
					$log_changes,
					array('id_action')
				);
			}

			// Have we got any post save functions to execute?
			if (!empty($context['profile_execute_on_save']))
				foreach ($context['profile_execute_on_save'] as $saveFunc)
					$saveFunc();

			// Let them know it worked!
			$context['profile_updated'] = we::$user['is_owner'] ? $txt['profile_updated_own'] : sprintf($txt['profile_updated_else'], $cur_profile['member_name']);

			// Invalidate any cached data.
			cache_put_data('member_data-profile-' . $memID, null, 0);
		}
	}

	// Have some errors for some reason?
	if (!empty($post_errors))
	{
		// Set all the errors so the template knows what went wrong.
		foreach ($post_errors as $error_type)
			$context['modify_error'][$error_type] = true;
	}
	// If it's you then we should redirect upon save.
	elseif (!empty($profile_vars) && we::$user['is_owner'])
		redirectexit('action=profile;area=' . $current_area . ';updated');
	elseif (!empty($force_redirect))
		redirectexit('action=profile' . (we::$user['is_owner'] ? '' : ';u=' . $memID) . ';area=' . $current_area);

	// Call the appropriate subaction function.
	$profile_include_data['function']($memID);

	// Set the page title if it's not already set...
	if (!isset($context['page_title']))
		$context['page_title'] = $txt['profile'] . (isset($txt[$current_area]) ? ' - ' . $txt[$current_area] : '');
}

// Load any custom fields for this area... no area means load all, 'summary' loads all public ones.
function loadCustomFields($memID, $area = 'summary')
{
	global $context, $txt, $user_profile, $theme, $scripturl;

	// Get the right restrictions in place...
	$where = 'active = 1';
	if (!allowedTo('admin_forum') && $area != 'register')
	{
		// If it's the owner they can see two types of private fields, regardless.
		if ($memID == we::$id)
			$where .= $area == 'summary' ? ' AND private < 3' : ' AND (private = 0 OR private = 2)';
		else
			$where .= $area == 'summary' ? ' AND private < 2' : ' AND private = 0';
	}

	if ($area == 'register')
		$where .= ' AND show_reg != 0';
	elseif ($area != 'summary')
		$where .= ' AND show_profile = {string:area}';

	if (we::$is_guest && $area != 'register')
		$where .= ' AND guest_access = 1';

	// Load all the relevant fields - and data.
	$request = wesql::query('
		SELECT
			col_name, field_name, field_desc, field_type, field_length, field_options,
			default_value, bbc, enclose, placement, show_reg
		FROM {db_prefix}custom_fields
		WHERE ' . $where . '
		ORDER BY position',
		array(
			'area' => $area === 'theme' ? 'options' : $area,
		)
	);
	$context['custom_fields'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Shortcut.
		$exists = $memID && isset($user_profile[$memID], $user_profile[$memID]['options'][$row['col_name']]);
		$value = $exists ? $user_profile[$memID]['options'][$row['col_name']] : '';

		// If this was submitted already then make the value the posted version.
		if (isset($_POST['customfield'], $_POST['customfield'][$row['col_name']]))
		{
			$value = westr::htmlspecialchars($_POST['customfield'][$row['col_name']]);
			if (in_array($row['field_type'], array('select', 'radio')))
				$value = ($options = explode(',', $row['field_options'])) && isset($options[$value]) ? $options[$value] : '';
		}

		// HTML for the input form.
		$output_html = $value;
		if ($row['field_type'] == 'check')
		{
			$true = (!$exists && $row['default_value']) || $value;
			$input_html = '<input type="checkbox" name="customfield[' . $row['col_name'] . ']"' . ($true ? ' checked' : '') . '>';
			$output_html = $true ? $txt['yes'] : $txt['no'];
		}
		elseif ($row['field_type'] == 'select')
		{
			$input_html = '<select name="customfield[' . $row['col_name'] . ']"><option value="-1"></option>';
			$options = explode(',', $row['field_options']);
			foreach ($options as $k => $v)
			{
				$true = (!$exists && $row['default_value'] == $v) || $value == $v;
				$input_html .= '<option value="' . $k . '"' . ($true ? ' selected' : '') . '>' . $v . '</option>';
				if ($true)
					$output_html = $v;
			}

			$input_html .= '</select>';
		}
		elseif ($row['field_type'] == 'radio')
		{
			$input_html = '<fieldset>';
			$options = explode(',', $row['field_options']);
			foreach ($options as $k => $v)
			{
				$true = (!$exists && $row['default_value'] == $v) || $value == $v;
				$input_html .= '<label><input type="radio" name="customfield[' . $row['col_name'] . ']" value="' . $k . '"' . ($true ? ' checked' : '') . '> ' . $v . '</label><br>';
				if ($true)
					$output_html = $v;
			}
			$input_html .= '</fieldset>';
		}
		elseif ($row['field_type'] == 'text')
		{
			$input_html = '<input name="customfield[' . $row['col_name'] . ']" ' . ($row['field_length'] != 0 ? 'maxlength="' . $row['field_length'] . '"' : '') . ' size="' . ($row['field_length'] == 0 || $row['field_length'] >= 50 ? 50 : ($row['field_length'] > 30 ? 30 : ($row['field_length'] > 10 ? 20 : 10))) . '" value="' . $value . '"' . ($area == 'register' && $row['show_reg'] > 1 ? ' required' : '') . '>';
		}
		else
		{
			@list ($rows, $cols) = @explode(',', $row['default_value']);
			$input_html = '<textarea name="customfield[' . $row['col_name'] . ']" ' . (!empty($rows) ? 'rows="' . $rows . '"' : '') . ' ' . (!empty($cols) ? 'cols="' . $cols . '"' : '') . '>' . $value . '</textarea>';
		}

		// Parse BBCode
		if ($row['bbc'])
			$output_html = parse_bbc($output_html, 'custom-field');
		// Allow for newlines at least
		elseif ($row['field_type'] == 'textarea')
			$output_html = strtr($output_html, array("\n" => '<br>'));

		// Enclosing the user input within some other text?
		if (!empty($row['enclose']) && !empty($output_html))
			$output_html = strtr($row['enclose'], array(
				'{SCRIPTURL}' => $scripturl,
				'{IMAGES_URL}' => $theme['images_url'],
				'{DEFAULT_IMAGES_URL}' => $theme['default_images_url'],
				'{INPUT}' => $output_html,
			));

		$context['custom_fields'][] = array(
			'name' => $row['field_name'],
			'desc' => $row['field_desc'],
			'type' => $row['field_type'],
			'input_html' => $input_html,
			'output_html' => $output_html,
			'placement' => $row['placement'],
			'colname' => $row['col_name'],
			'value' => $value,
		);
	}
	wesql::free_result($request);
}
