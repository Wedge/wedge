<?php
/**********************************************************************************
* Admin.php                                                                       *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC5                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file, unpredictable as this might be, handles basic administration.

	void Admin()
		- initialises all the basic context required for the admin center.
		- passes execution onto the relevant admin section.
		- if the passed section is not found it shows the admin home page.

	void AdminHome()
		- prepares all the data necessary for the administration front page.
		- uses the Admin template along with the admin sub template.
		- requires the moderate_forum, manage_membergroups, manage_bans,
		  admin_forum, manage_permissions, manage_attachments, manage_smileys,
		  manage_boards, edit_news, or send_mail permission.
		- uses the index administrative area.
		- can be found by going to ?action=admin.

	void AdminSearch()
		// !!

	void AdminSearchInternal()
		// !!

	void AdminSearchMember()
		// !!

*/

// The main admin handling function.
function Admin()
{
	global $txt, $context, $scripturl, $sc, $modSettings, $user_info, $settings, $options, $boardurl;

	// Load the language and templates....
	loadLanguage('Admin');
	loadTemplate('Admin', 'admin');

	// No indexing evil stuff.
	$context['robot_no_index'] = true;

	loadSource('Subs-Menu');

	// Some preferences.
	$context['admin_preferences'] = !empty($options['admin_preferences']) ? unserialize($options['admin_preferences']) : array();

	// Define all the menu structure - see Subs-Menu.php for details!
	$admin_areas = array(
		'forum' => array(
			'title' => $txt['admin_main'],
			'permission' => array('admin_forum', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_boards', 'manage_smileys', 'manage_attachments'),
			'areas' => array(
				'index' => array(
					'label' => $txt['admin_center'],
					'function' => 'AdminHome',
					'icon' => 'administration.gif',
				),
				'credits' => array(
					'label' => $txt['support_credits_title'],
					'function' => 'AdminHome',
					'icon' => 'support.gif',
				),
				'',
				'news' => array(
					'label' => $txt['news_title'],
					'file' => 'ManageNews',
					'function' => 'ManageNews',
					'icon' => 'news.gif',
					'permission' => array('edit_news', 'send_mail', 'admin_forum'),
					'subsections' => array(
						'edit_news' => array($txt['admin_edit_news'], 'edit_news'),
						'mailingmembers' => array($txt['admin_newsletters'], 'send_mail'),
						'settings' => array($txt['settings'], 'admin_forum'),
					),
				),
				'',
				'packages' => array(
					'label' => $txt['package'],
					'file' => 'Packages',
					'function' => 'Packages',
					'permission' => array('admin_forum'),
					'icon' => 'packages.gif',
					'subsections' => array(
						'browse' => array($txt['browse_packages']),
						'packageget' => array($txt['download_packages'], 'url' => $scripturl . '?action=admin;area=packages;sa=packageget;get'),
						'perms' => array($txt['package_file_perms']),
						'options' => array($txt['package_settings']),
					),
				),
				'search' => array(
					'function' => 'AdminSearch',
					'permission' => array('admin_forum'),
					'select' => 'index'
				),
			),
		),
		'config' => array(
			'title' => $txt['admin_config'],
			'permission' => array('admin_forum'),
			'areas' => array(
				'corefeatures' => array(
					'label' => $txt['core_settings_title'],
					'file' => 'ManageSettings',
					'function' => 'ModifyCoreFeatures',
					'icon' => 'corefeatures.gif',
				),
				'',
				'featuresettings' => array(
					'label' => $txt['modSettings_title'],
					'file' => 'ManageSettings',
					'function' => 'ModifyFeatureSettings',
					'icon' => 'features.gif',
					'subsections' => array(
						'basic' => array($txt['mods_cat_features']),
						'layout' => array($txt['mods_cat_layout']),
						'sig' => array($txt['signature_settings_short']),
						'profile' => array($txt['custom_profile_shorttitle'], 'enabled' => !empty($modSettings['cf_enabled'])),
						'pretty' => array($txt['pretty_urls']),
					),
				),
				'securitysettings' => array(
					'label' => $txt['admin_security_moderation'],
					'file' => 'ManageSettings',
					'function' => 'ModifySecuritySettings',
					'icon' => 'security.gif',
					'subsections' => array(
						'general' => array($txt['mods_cat_security_general']),
						'spam' => array($txt['antispam_title']),
						'moderation' => array($txt['moderation_settings_short'], 'enabled' => substr($modSettings['warning_settings'], 0, 1) == 1),
					),
				),
				'serversettings' => array(
					'label' => $txt['admin_server_settings'],
					'file' => 'ManageServer',
					'function' => 'ModifySettings',
					'icon' => 'server.gif',
					'subsections' => array(
						'general' => array($txt['general_settings']),
						'database' => array($txt['database_paths_settings']),
						'cookie' => array($txt['cookies_sessions_settings']),
						'cache' => array($txt['caching_settings']),
						'loads' => array($txt['load_balancing_settings']),
						'proxy' => array($txt['proxy_settings']),
					),
				),
				'',
				'languages' => array(
					'label' => $txt['language_configuration'],
					'file' => 'ManageServer',
					'function' => 'ManageLanguages',
					'icon' => 'languages.gif',
					'subsections' => array(
						'edit' => array($txt['language_edit']),
						'add' => array($txt['language_add']),
						'settings' => array($txt['language_settings']),
					),
				),
				'',
				'current_theme' => array(
					'label' => $txt['theme_current_settings'],
					'file' => 'Themes',
					'function' => 'ThemesMain',
					'custom_url' => $scripturl . '?action=admin;area=theme;sa=settings;th=' . $settings['theme_id'],
					'icon' => 'current_theme.gif',
				),
				'theme' => array(
					'label' => $txt['theme_admin'],
					'file' => 'Themes',
					'function' => 'ThemesMain',
					'icon' => 'themes.gif',
					'subsections' => array(
						'admin' => array($txt['themeadmin_admin_title']),
						'list' => array($txt['themeadmin_list_title']),
						'reset' => array($txt['themeadmin_reset_title']),
						'edit' => array($txt['themeadmin_edit_title']),
					),
				),
				'',
				'modsettings' => array(
					'label' => $txt['admin_modifications'],
					'file' => 'ManageSettings',
					'function' => 'ModifyModSettings',
					'icon' => 'modifications.gif',
					'subsections' => array(
						'general' => array($txt['mods_cat_modifications_misc']),
						// Mod Authors, don't edit these lines. Instead, add the 'admin_area' hook
						// to automatically insert your menu entry into this spot.
					),
				),
			),
		),
		'layout' => array(
			'title' => $txt['layout_controls'],
			'permission' => array('manage_boards', 'admin_forum', 'manage_smileys', 'manage_attachments', 'moderate_forum'),
			'areas' => array(
				'manageboards' => array(
					'label' => $txt['admin_boards'],
					'file' => 'ManageBoards',
					'function' => 'ManageBoards',
					'icon' => 'boards.gif',
					'permission' => array('manage_boards'),
					'subsections' => array(
						'main' => array($txt['boardsEdit']),
						'newcat' => array($txt['mboards_new_cat']),
						'settings' => array($txt['settings'], 'admin_forum'),
					),
				),
				'postsettings' => array(
					'label' => $txt['manageposts'],
					'file' => 'ManagePosts',
					'function' => 'ManagePostSettings',
					'permission' => array('admin_forum'),
					'icon' => 'posts.gif',
					'subsections' => array(
						'posts' => array($txt['manageposts_settings']),
						'bbc' => array($txt['manageposts_bbc_settings']),
						'censor' => array($txt['admin_censored_words']),
						'topics' => array($txt['manageposts_topic_settings']),
						'drafts' => array($txt['manageposts_draft_settings']),
						'merge' => array($txt['manageposts_merge']),
					),
				),
				'',
				'smileys' => array(
					'label' => $txt['smileys_manage'],
					'file' => 'ManageSmileys',
					'function' => 'ManageSmileys',
					'icon' => 'smiley.gif',
					'permission' => array('manage_smileys'),
					'subsections' => array(
						'editsets' => array($txt['smiley_sets']),
						'addsmiley' => array($txt['smileys_add'], 'enabled' => !empty($modSettings['smiley_enable'])),
						'editsmileys' => array($txt['smileys_edit'], 'enabled' => !empty($modSettings['smiley_enable'])),
						'setorder' => array($txt['smileys_set_order'], 'enabled' => !empty($modSettings['smiley_enable'])),
						'editicons' => array($txt['icons_edit_message_icons'], 'enabled' => !empty($modSettings['messageIcons_enable'])),
						'settings' => array($txt['settings']),
					),
				),
				'manageattachments' => array(
					'label' => $txt['attachments_avatars'],
					'file' => 'ManageAttachments',
					'function' => 'ManageAttachments',
					'icon' => 'attachment.gif',
					'permission' => array('manage_attachments'),
					'subsections' => array(
						'browse' => array($txt['attachment_manager_browse']),
						'attachments' => array($txt['attachment_manager_settings']),
						'avatars' => array($txt['attachment_manager_avatar_settings']),
						'maintenance' => array($txt['attachment_manager_maintenance']),
					),
				),
				'',
				'managecalendar' => array(
					'label' => $txt['manage_calendar'],
					'file' => 'ManageCalendar',
					'function' => 'ManageCalendar',
					'icon' => 'calendar.gif',
					'permission' => array('admin_forum'),
					'enabled' => !empty($modSettings['cal_enabled']),
					'subsections' => array(
						'holidays' => array($txt['manage_holidays'], 'admin_forum', 'enabled' => !empty($modSettings['cal_enabled'])),
						'settings' => array($txt['calendar_settings'], 'admin_forum'),
					),
				),
				'managesearch' => array(
					'label' => $txt['manage_search'],
					'file' => 'ManageSearch',
					'function' => 'ManageSearch',
					'icon' => 'search.gif',
					'permission' => array('admin_forum'),
					'subsections' => array(
						'weights' => array($txt['search_weights']),
						'method' => array($txt['search_method']),
						'settings' => array($txt['settings']),
					),
				),
			),
		),
		'media' => array(
			'title' => $txt['media_title'],
			'permission' => array('media_manage'),
			'areas' => array(
				'aeva_about' => array(
					'label' => $txt['media_admin_labels_about'],
					'icon' => 'administration.gif',
					'enabled' => !empty($modSettings['media_enabled']) || !empty($modSettings['embed_enabled']),
					'subsections' => array(
						'about' => 'media_admin_labels_index',
						'readme' => 'media_admin_readme',
						'changelog' => 'media_admin_changelog',
					),
				),
				'aeva_settings' => array(
					'label' => $txt['media_admin_labels_settings'],
					'icon' => 'corefeatures.gif',
					'subsections' => array(
						'config' => 'media_admin_settings_config',
						'meta' => 'media_admin_settings_meta',
						'layout' => 'media_admin_settings_layout',
					),
				),
				'aeva_embed' => array(
					'label' => $txt['media_admin_labels_embed'],
					'icon' => 'aeva.png',
					'enabled' => !empty($modSettings['embed_enabled']),
					'subsections' => array(
						'config' => 'media_admin_settings_config',
						'sites' => 'media_admin_settings_sites',
					),
				),
				'aeva_albums' => array(
					'label' => $txt['media_admin_labels_albums'],
					'icon' => 'mgallery.png',
					'subsections' => array(
						'index' => 'media_admin_labels_index',
						'normal' => 'media_admin_filter_normal_albums',
						'featured' => 'media_admin_filter_featured_albums',
						'add' => 'media_admin_add_album',
					),
				),
				'aeva_maintenance' => array(
					'label' => $txt['media_admin_labels_maintenance'],
					'icon' => 'maintain.gif',
					'subsections' => array(
						'index' => 'media_admin_maintenance_all_tasks',
						'recount' => 'media_admin_maintenance_recount',
						'checkfiles' => 'media_admin_maintenance_checkfiles',
						'finderrors' => 'media_admin_maintenance_finderrors',
						'prune' => 'media_admin_maintenance_prune',
					),
				),
				'aeva_bans' => array(
					'label' => $txt['media_admin_labels_bans'],
					'icon' => 'ban.gif',
					'subsections' => array(
						'index' => 'media_admin_labels_index',
						'add' => 'media_admin_bans_add',
					),
				),
				'aeva_fields' => array(
					'label' => $txt['media_cf'],
					'icon' => 'packages.gif',
					'subsections' => array(
						'index' => 'media_admin_labels_index',
						'edit' => 'media_cf_add',
					),
				),
				'aeva_perms' => array(
					'label' => $txt['media_admin_labels_perms'],
					'icon' => 'permissions.gif',
				),
				'aeva_quotas' => array(
					'label' => $txt['media_admin_labels_quotas'],
					'icon' => 'attachment.gif',
				),
				'aeva_ftp' => array(
					'label' => $txt['media_admin_labels_ftp'],
					'icon' => 'boards.gif',
				),
			),
		),
		'members' => array(
			'title' => $txt['admin_manage_members'],
			'permission' => array('moderate_forum', 'manage_membergroups', 'manage_bans', 'manage_permissions', 'admin_forum'),
			'areas' => array(
				'viewmembers' => array(
					'label' => $txt['admin_users'],
					'file' => 'ManageMembers',
					'function' => 'ViewMembers',
					'icon' => 'members.gif',
					'permission' => array('moderate_forum'),
					'subsections' => array(
						'all' => array($txt['view_all_members']),
						'search' => array($txt['mlist_search']),
					),
				),
				'membergroups' => array(
					'label' => $txt['admin_groups'],
					'file' => 'ManageMembergroups',
					'function' => 'ModifyMembergroups',
					'icon' => 'membergroups.gif',
					'permission' => array('manage_membergroups'),
					'subsections' => array(
						'index' => array($txt['membergroups_edit_groups'], 'manage_membergroups'),
						'add' => array($txt['membergroups_new_group'], 'manage_membergroups'),
						'settings' => array($txt['settings'], 'admin_forum'),
					),
				),
				'permissions' => array(
					'label' => $txt['edit_permissions'],
					'file' => 'ManagePermissions',
					'function' => 'ModifyPermissions',
					'icon' => 'permissions.gif',
					'permission' => array('manage_permissions'),
					'subsections' => array(
						'index' => array($txt['permissions_groups'], 'manage_permissions'),
						'board' => array($txt['permissions_boards'], 'manage_permissions'),
						'profiles' => array($txt['permissions_profiles'], 'manage_permissions'),
						'postmod' => array($txt['permissions_post_moderation'], 'manage_permissions', 'enabled' => $modSettings['postmod_active']),
						'settings' => array($txt['settings'], 'admin_forum'),
					),
				),
				'regcenter' => array(
					'label' => $txt['registration_center'],
					'file' => 'ManageRegistration',
					'function' => 'RegCenter',
					'icon' => 'regcenter.gif',
					'permission' => array('admin_forum', 'moderate_forum'),
					'subsections' => array(
						'register' => array($txt['admin_browse_register_new'], 'moderate_forum'),
						'agreement' => array($txt['registration_agreement'], 'admin_forum'),
						'reservednames' => array($txt['admin_reserved_set'], 'admin_forum'),
						'settings' => array($txt['settings'], 'admin_forum'),
					),
				),
				'ban' => array(
					'label' => $txt['ban_title'],
					'file' => 'ManageBans',
					'function' => 'Ban',
					'icon' => 'ban.gif',
					'permission' => 'manage_bans',
					'subsections' => array(
						'list' => array($txt['ban_edit_list']),
						'add' => array($txt['ban_add_new']),
						'browse' => array($txt['ban_trigger_browse']),
						'log' => array($txt['ban_log']),
					),
				),
				'paidsubscribe' => array(
					'label' => $txt['paid_subscriptions'],
					'enabled' => !empty($modSettings['paid_enabled']),
					'file' => 'ManagePaid',
					'icon' => 'paid.gif',
					'function' => 'ManagePaidSubscriptions',
					'permission' => 'admin_forum',
					'subsections' => array(
						'view' => array($txt['paid_subs_view']),
						'settings' => array($txt['settings']),
					),
				),
				'',
				'sengines' => array(
					'label' => $txt['search_engines'],
					'enabled' => !empty($modSettings['spider_mode']),
					'file' => 'ManageSearchEngines',
					'icon' => 'engines.gif',
					'function' => 'SearchEngines',
					'permission' => 'admin_forum',
					'subsections' => array(
						'stats' => array($txt['spider_stats']),
						'logs' => array($txt['spider_logs']),
						'spiders' => array($txt['spiders']),
						'settings' => array($txt['settings']),
					),
				),
			),
		),
		'maintenance' => array(
			'title' => $txt['admin_maintenance'],
			'permission' => array('admin_forum'),
			'areas' => array(
				'maintain' => array(
					'label' => $txt['maintain_title'],
					'file' => 'ManageMaintenance',
					'icon' => 'maintain.gif',
					'function' => 'ManageMaintenance',
					'subsections' => array(
						'routine' => array($txt['maintain_sub_routine'], 'admin_forum'),
						'database' => array($txt['maintain_sub_database'], 'admin_forum'),
						'members' => array($txt['maintain_sub_members'], 'admin_forum'),
						'topics' => array($txt['maintain_sub_topics'], 'admin_forum'),
					),
				),
				'scheduledtasks' => array(
					'label' => $txt['maintain_tasks'],
					'file' => 'ManageScheduledTasks',
					'icon' => 'scheduled.gif',
					'function' => 'ManageScheduledTasks',
					'subsections' => array(
						'tasks' => array($txt['maintain_tasks'], 'admin_forum'),
						'tasklog' => array($txt['scheduled_log'], 'admin_forum'),
					),
				),
				'',
				'mailqueue' => array(
					'label' => $txt['mailqueue_title'],
					'file' => 'ManageMail',
					'function' => 'ManageMail',
					'icon' => 'mail.gif',
					'subsections' => array(
						'browse' => array($txt['mailqueue_browse'], 'admin_forum'),
						'settings' => array($txt['mailqueue_settings'], 'admin_forum'),
					),
				),
				'reports' => array(
					'enabled' => !empty($modSettings['reports_enabled']),
					'label' => $txt['generate_reports'],
					'file' => 'Reports',
					'function' => 'ReportsMain',
					'icon' => 'reports.gif',
				),
				'',
				'logs' => array(
					'label' => $txt['logs'],
					'function' => 'AdminLogs',
					'icon' => 'logs.gif',
					'subsections' => array(
						'errorlog' => array($txt['errlog'], 'admin_forum', 'enabled' => !empty($modSettings['enableErrorLogging']), 'url' => $scripturl . '?action=admin;area=logs;sa=errorlog;desc'),
						'adminlog' => array($txt['admin_log'], 'admin_forum', 'enabled' => !empty($modSettings['modlog_enabled'])),
						'modlog' => array($txt['moderation_log'], 'admin_forum', 'enabled' => !empty($modSettings['modlog_enabled'])),
						'banlog' => array($txt['ban_log'], 'manage_bans'),
						'spiderlog' => array($txt['spider_logs'], 'admin_forum', 'enabled' => !empty($modSettings['spider_mode'])),
						'tasklog' => array($txt['scheduled_log'], 'admin_forum'),
						'pruning' => array($txt['pruning_title'], 'admin_forum'),
					),
				),
				'repairboards' => array(
					'label' => $txt['admin_repair'],
					'file' => 'RepairBoards',
					'function' => 'RepairBoards',
					'select' => 'maintain',
					'hidden' => true,
				),
			),
		),
	);

	// Temp compatibility code for Aeva Media integration...
	foreach ($admin_areas['media']['areas'] as &$tab)
	{
		$tab['file'] = 'media/ManageMedia';
		$tab['function'] = 'aeva_admin_init';
		$tab['permission'] = array('media_manage');
		$tab['enabled'] = isset($tab['enabled']) ? $tab['enabled'] : !empty($modSettings['media_enabled']);
		if (!empty($tab['subsections']))
			foreach ($tab['subsections'] as &$title)
				$title = array($txt[$title]);
	}
	// End of compatibility code.

	// Any files to include for administration?
	if (!empty($modSettings['integrate_admin_include']))
	{
		$admin_includes = explode(',', $modSettings['integrate_admin_include']);
		foreach ($admin_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Let modders modify admin areas easily.
	// You can insert a top-level menu into the admin menu by doing something like this in your hook:
	// $admin_areas = array_merge(array_splice($admin_areas, 0, 2), $my_top_level_menu_array, $admin_areas);

	call_hook('admin_areas', array(&$admin_areas));

	// Make sure the administrator has a valid session...
	validateSession();

	// Actually create the menu!
	$admin_include_data = createMenu($admin_areas);
	unset($admin_areas);

	// Nothing valid?
	if ($admin_include_data == false)
		fatal_lang_error('no_access', false);

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=admin',
		'name' => $txt['admin_center'],
	);
	if (isset($admin_include_data['current_area']) && $admin_include_data['current_area'] != 'index')
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=admin;area=' . $admin_include_data['current_area'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			'name' => $admin_include_data['label'],
		);
	if (!empty($admin_include_data['current_subsection']) && $admin_include_data['subsections'][$admin_include_data['current_subsection']][0] != $admin_include_data['label'])
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=admin;area=' . $admin_include_data['current_area'] . ';sa=' . $admin_include_data['current_subsection'] . ';' . $context['session_var'] . '=' . $context['session_id'],
			'name' => $admin_include_data['subsections'][$admin_include_data['current_subsection']][0],
		);

	// Make a note of the Unique ID for this menu.
	$context['admin_menu_id'] = $context['max_menu_id'];
	$context['admin_menu_name'] = 'menu_data_' . $context['admin_menu_id'];

	// Why on the admin are we?
	$context['admin_area'] = $admin_include_data['current_area'];

	// Now - finally - call the right place!
	if (isset($admin_include_data['file']))
		loadSource($admin_include_data['file']);

	$admin_include_data['function']();
}

// The main administration section.
function AdminHome()
{
	global $forum_version, $txt, $scripturl, $context, $user_info, $boardurl, $modSettings;

	// You have to be able to do at least one of the below to see this page.
	isAllowedTo(array('admin_forum', 'manage_permissions', 'moderate_forum', 'manage_membergroups', 'manage_bans', 'send_mail', 'edit_news', 'manage_boards', 'manage_smileys', 'manage_attachments'));

	// Find all of this forum's administrators...
	loadSource('Subs-Membergroups');

	// Add a 'more' link if there are more than 32.
	if (listMembergroupMembers_Href($context['administrators'], 1, 32) && allowedTo('manage_membergroups'))
		$context['more_admins_link'] = '<a href="' . $scripturl . '?action=moderate;area=viewgroups;sa=members;group=1">' . $txt['more'] . '</a>';

	// Load the credits stuff.
	loadSource('Credits');
	Credits(true);

	// This makes it easier to get the latest news with your time format.
	$context['time_format'] = urlencode($user_info['time_format']);

	$context['current_versions'] = array(
		'php' => array('title' => $txt['support_versions_php'], 'version' => PHP_VERSION),
		'db' => array('title' => sprintf($txt['support_versions_db'], 'MySQL'), 'version' => ''),
		'server' => array('title' => $txt['support_versions_server'], 'version' => $_SERVER['SERVER_SOFTWARE']),
	);
	$context['forum_version'] = $forum_version;

	// Get a list of current server versions.
	loadSource('Subs-Admin');
	$checkFor = array(
		'gd',
		'db_server',
		'eaccelerator',
		'phpa',
		'apc',
		'memcache',
		'xcache',
		'php',
		'server',
	);
	$context['current_versions'] = getServerVersions($checkFor);

	$context['can_admin'] = allowedTo('admin_forum');

	loadSubTemplate($context['admin_area'] == 'credits' ? 'credits' : 'admin');
	$context['page_title'] = $context['admin_area'] == 'credits' ? $txt['support_credits_title'] : $txt['admin_center'];

	// The format of this array is: permission, action, title, description, icon.
	$quick_admin_tasks = array(
		array('', 'credits', 'support_credits_title', 'support_credits_info', 'support_and_credits.png'),
		array('admin_forum', 'featuresettings', 'modSettings_title', 'modSettings_info', 'features_and_options.png'),
		array('admin_forum', 'maintain', 'maintain_title', 'maintain_info', 'forum_maintenance.png'),
		array('manage_permissions', 'permissions', 'edit_permissions', 'edit_permissions_info', 'permissions.png'),
		array('admin_forum', 'theme;sa=admin;' . $context['session_var'] . '=' . $context['session_id'], 'theme_admin', 'theme_admin_info', 'themes_and_layout.png'),
		array('admin_forum', 'packages', 'package', 'package_info', 'packages.png'),
		array('manage_smileys', 'smileys', 'smileys_manage', 'smileys_manage_info', 'smilies_and_messageicons.png'),
		array('moderate_forum', 'viewmembers', 'admin_users', 'member_center_info', 'members.png'),
	);

	$context['quick_admin_tasks'] = array();
	foreach ($quick_admin_tasks as $task)
	{
		if (!empty($task[0]) && !allowedTo($task[0]))
			continue;

		$context['quick_admin_tasks'][] = array(
			'href' => $scripturl . '?action=admin;area=' . $task[1],
			'link' => '<a href="' . $scripturl . '?action=admin;area=' . $task[1] . '">' . $txt[$task[2]] . '</a>',
			'title' => $txt[$task[2]],
			'description' => $txt[$task[3]],
			'icon' => $task[4],
			'is_last' => false
		);
	}

	if (count($context['quick_admin_tasks']) % 2 == 1)
	{
		$context['quick_admin_tasks'][] = array(
			'href' => '',
			'link' => '',
			'title' => '',
			'description' => '',
			'is_last' => true
		);
		$context['quick_admin_tasks'][count($context['quick_admin_tasks']) - 2]['is_last'] = true;
	}
	elseif (count($context['quick_admin_tasks']) != 0)
	{
		$context['quick_admin_tasks'][count($context['quick_admin_tasks']) - 1]['is_last'] = true;
		$context['quick_admin_tasks'][count($context['quick_admin_tasks']) - 2]['is_last'] = true;
	}
}

// This allocates out all the search stuff.
function AdminSearch()
{
	global $txt, $context;

	isAllowedTo('admin_forum');

	// What can we search for?
	$subactions = array(
		'internal' => 'AdminSearchInternal',
		'online' => 'AdminSearchOM',
		'member' => 'AdminSearchMember',
	);

	$context['search_type'] = !isset($_REQUEST['search_type']) || !isset($subactions[$_REQUEST['search_type']]) ? 'internal' : $_REQUEST['search_type'];
	$context['search_term'] = isset($_REQUEST['search_term']) ? westr::htmlspecialchars($_REQUEST['search_term'], ENT_QUOTES) : '';

	loadSubTemplate('admin_search_results');
	$context['page_title'] = $txt['admin_search_results'];

	// Keep track of what the admin wants.
	if (empty($context['admin_preferences']['sb']) || $context['admin_preferences']['sb'] != $context['search_type'])
	{
		$context['admin_preferences']['sb'] = $context['search_type'];

		// Update the preferences.
		loadSource('Subs-Admin');
		updateAdminPreferences();
	}

	if (trim($context['search_term']) == '')
		$context['search_results'] = array();
	else
		$subactions[$context['search_type']]();
}

// A complicated but relatively quick internal search.
function AdminSearchInternal()
{
	global $context, $txt, $helptxt, $scripturl, $settings_search;

	// Try to get some more memory.
	@ini_set('memory_limit', '128M');

	// Load a lot of language files.
	$language_files = array(
		'Help', 'ManageMail', 'ManageSettings', 'ManageCalendar', 'ManageBoards', 'ManagePaid', 'ManagePermissions', 'Search',
		'Login', 'ManageSmileys',
	);
	loadLanguage(implode('+', $language_files));

	// All the files we need to include.
	$include_files = array(
		'ManageSettings', 'ManageBoards', 'ManageNews', 'ManageAttachments', 'ManageCalendar', 'ManageMail', 'ManagePaid', 'ManagePermissions',
		'ManagePosts', 'ManageRegistration', 'ManageSearch', 'ManageSearchEngines', 'ManageServer', 'ManageSmileys',
	);
	loadSource($include_files);

	/* This is the huge array that defines everything... it's a huge array of items formatted as follows:
		0 = Language index (Can be array of indexes) to search through for this setting.
		1 = URL for this indexes page.
		2 = Help index for help associated with this item (If different from 0)
	*/

	$search_data = array(
		// All the major sections of the forum.
		'sections' => array(
		),
		'settings' => array(
			array('COPPA', 'area=regcenter;sa=settings'),
			array('CAPTCHA', 'area=regcenter;sa=settings'),
		),
	);

	// Go through the admin menu structure trying to find suitably named areas!
	foreach ($context[$context['admin_menu_name']]['sections'] as $section)
	{
		foreach ($section['areas'] as $menu_key => $menu_item)
		{
			if ($menu_item === '')
				continue;
			$search_data['sections'][] = array($menu_item['label'], 'area=' . $menu_key);
			if (!empty($menu_item['subsections']))
				foreach ($menu_item['subsections'] as $key => $sublabel)
					if (isset($sublabel['label']))
						$search_data['sections'][] = array($sublabel['label'], 'area=' . $menu_key . ';sa=' . $key);
		}
	}

	// This is a special array of functions that contain setting data - we query all these to simply pull all setting bits!
	$settings_search = array(
		array('ModifyCoreFeatures', 'area=corefeatures'),
		array('ModifyBasicSettings', 'area=featuresettings;sa=basic'),
		array('ModifyLayoutSettings', 'area=featuresettings;sa=layout'),
		array('ModifySignatureSettings', 'area=featuresettings;sa=sig'),
		array('ModifyGeneralSecuritySettings', 'area=securitysettings;sa=general'),
		array('ModifySpamSettings', 'area=securitysettings;sa=spam'),
		array('ModifyModerationSettings', 'area=securitysettings;sa=moderation'),
		array('ModifyGeneralModSettings', 'area=modsettings;sa=general'),
		array('ManageAttachmentSettings', 'area=manageattachments;sa=attachments'),
		array('ManageAvatarSettings', 'area=manageattachments;sa=avatars'),
		array('ModifyCalendarSettings', 'area=managecalendar;sa=settings'),
		array('EditBoardSettings', 'area=manageboards;sa=settings'),
		array('ModifyMailSettings', 'area=mailqueue;sa=settings'),
		array('ModifyNewsSettings', 'area=news;sa=settings'),
		array('GeneralPermissionSettings', 'area=permissions;sa=settings'),
		array('ModifyPostSettings', 'area=postsettings;sa=posts'),
		array('ModifyBBCSettings', 'area=postsettings;sa=bbc'),
		array('ModifyTopicSettings', 'area=postsettings;sa=topics'),
		array('EditSearchSettings', 'area=managesearch;sa=settings'),
		array('EditSmileySettings', 'area=smileys;sa=settings'),
		array('ModifyGeneralSettings', 'area=serversettings;sa=general'),
		array('ModifyDatabaseSettings', 'area=serversettings;sa=database'),
		array('ModifyCookieSettings', 'area=serversettings;sa=cookie'),
		array('ModifyCacheSettings', 'area=serversettings;sa=cache'),
		array('ModifyLanguageSettings', 'area=languages;sa=settings'),
		array('ModifyRegistrationSettings', 'area=regcenter;sa=settings'),
		array('ManageSearchEngineSettings', 'area=sengines;sa=settings'),
		array('ModifySubscriptionSettings', 'area=paidsubscribe;sa=settings'),
		array('ModifyPruningSettings', 'area=logs;sa=pruning'),
	);

	// It will probably never be used by anyone, but anyway...
	call_hook('admin_search', array(&$settings_search));

	foreach ($settings_search as $setting_area)
	{
		// Get a list of their variables.
		$config_vars = $setting_area[0](true);

		foreach ($config_vars as $var)
			if (!empty($var[1]) && !in_array($var[0], array('permissions', 'switch')))
				$search_data['settings'][] = array($var[(isset($var[2]) && in_array($var[2], array('file', 'db'))) ? 0 : 1], $setting_area[1]);
	}

	$context['page_title'] = $txt['admin_search_results'];
	$context['search_results'] = array();

	$search_term = strtolower($context['search_term']);
	// Go through all the search data trying to find this text!
	foreach ($search_data as $section => $data)
	{
		foreach ($data as $item)
		{
			$found = false;
			if (!is_array($item[0]))
				$item[0] = array($item[0]);
			foreach ($item[0] as $term)
			{
				$lc_term = strtolower($term);
				if (strpos($lc_term, $search_term) !== false || (isset($txt[$term]) && strpos(strtolower($txt[$term]), $search_term) !== false) || (isset($txt['setting_' . $term]) && strpos(strtolower($txt['setting_' . $term]), $search_term) !== false))
				{
					$found = $term;
					break;
				}
			}

			if ($found)
			{
				// Format the name - and remove any descriptions the entry may have.
				$name = isset($txt[$found]) ? $txt[$found] : (isset($txt['setting_' . $found]) ? $txt['setting_' . $found] : $found);
				$name = preg_replace('~<(?:div|span)\sclass="smalltext">.+?</(?:div|span)>~', '', $name);

				$context['search_results'][] = array(
					'url' => (substr($item[1], 0, 4) == 'area' ? $scripturl . '?action=admin;' . $item[1] : $item[1]) . ';' . $context['session_var'] . '=' . $context['session_id'] . ((substr($item[1], 0, 4) == 'area' && $section == 'settings' ? '#' . $item[0][0] : '')),
					'name' => $name,
					'type' => $section,
					'help' => shorten_subject(isset($item[2]) ? strip_tags($helptxt[$item2]) : (isset($helptxt[$found]) ? strip_tags($helptxt[$found]) : ''), 255),
				);
			}
		}
	}
}

// All this does is pass through to manage members.
function AdminSearchMember()
{
	global $context;

	loadSource('ManageMembers');
	$_REQUEST['sa'] = 'query';

	$_POST['membername'] = $context['search_term'];

	ViewMembers();
}

// This file allows the user to search the SimpleMachines online manual for a little help.
function AdminSearchOM()
{
	global $context;

	$docsURL = 'docs.simplemachines.org';
	$context['doc_scripturl'] = 'http://docs.simplemachines.org/index.php';

	// Set all the parameters search might expect.
	$postVars = array(
		'search' => $context['search_term'],
	);

	// Encode the search data.
	foreach ($postVars as $k => $v)
		$postVars[$k] = urlencode($k) . '=' . urlencode($v);

	// This is what we will send.
	$postVars = implode('&', $postVars);

	// Get the results from the doc site.
	loadSource('Subs-Package');
	$search_results = fetch_web_data($context['doc_scripturl'] . '?action=search2&xml', $postVars);

	// If we didn't get any xml back we are in trouble - perhaps the doc site is overloaded?
	if (!$search_results || preg_match('~<\?xml\sversion="\d+\.\d+"\sencoding=".+?"\?\>\s*(<smf>.+?</smf>)~is', $search_results, $matches) != true)
		fatal_lang_error('cannot_connect_doc_site');

	$search_results = $matches[1];

	// Otherwise we simply walk through the XML and stick it in context for display.
	$context['search_results'] = array();
	loadSource('Class-Package');

	// Get the results loaded into an array for processing!
	$results = new xmlArray($search_results, false);

	// Move through the smf layer.
	if (!$results->exists('smf'))
		fatal_lang_error('cannot_connect_doc_site');
	$results = $results->path('smf[0]');

	// Are there actually some results?
	if (!$results->exists('noresults') && !$results->exists('results'))
		fatal_lang_error('cannot_connect_doc_site');
	elseif ($results->exists('results'))
	{
		foreach ($results->set('results/result') as $result)
		{
			if (!$result->exists('messages'))
				continue;

			$context['search_results'][$result->fetch('id')] = array(
				'topic_id' => $result->fetch('id'),
				'relevance' => $result->fetch('relevance'),
				'board' => array(
					'id' => $result->fetch('board/id'),
					'name' => $result->fetch('board/name'),
					'href' => $result->fetch('board/href'),
				),
				'category' => array(
					'id' => $result->fetch('category/id'),
					'name' => $result->fetch('category/name'),
					'href' => $result->fetch('category/href'),
				),
				'messages' => array(),
			);

			// Add the messages.
			foreach ($result->set('messages/message') as $message)
				$context['search_results'][$result->fetch('id')]['messages'][] = array(
					'id' => $message->fetch('id'),
					'subject' => $message->fetch('subject'),
					'body' => $message->fetch('body'),
					'time' => $message->fetch('time'),
					'timestamp' => $message->fetch('timestamp'),
					'start' => $message->fetch('start'),
					'author' => array(
						'id' => $message->fetch('author/id'),
						'name' => $message->fetch('author/name'),
						'href' => $message->fetch('author/href'),
					),
				);
		}
	}
}

// This function decides which log to load.
function AdminLogs()
{
	global $context, $txt, $scripturl;

	// These are the logs they can load.
	$log_functions = array(
		'errorlog' => array('ManageErrors', 'ViewErrorLog'),
		'adminlog' => array('Modlog', 'ViewModlog'),
		'modlog' => array('Modlog', 'ViewModlog'),
		'banlog' => array('ManageBans', 'BanLog'),
		'spiderlog' => array('ManageSearchEngines', 'SpiderLogs'),
		'tasklog' => array('ManageScheduledTasks', 'TaskLog'),
		'pruning' => array('ManageSettings', 'ModifyPruningSettings'),
	);

	$sub_action = isset($_REQUEST['sa'], $log_functions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'errorlog';
	// If it's not got a sa set it must have come here for first time, pretend error log should be reversed.
	if (!isset($_REQUEST['sa']))
		$_REQUEST['desc'] = true;

	// Setup some tab stuff.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['logs'],
		'help' => '',
		'description' => $txt['maintain_info'],
		'tabs' => array(
			'errorlog' => array(
				'url' => $scripturl . '?action=admin;area=logs;sa=errorlog;desc',
				'description' => sprintf($txt['errlog_desc'], $txt['remove']),
			),
			'adminlog' => array(
				'description' => $txt['admin_log_desc'],
			),
			'modlog' => array(
				'description' => $txt['moderation_log_desc'],
			),
			'banlog' => array(
				'description' => $txt['ban_log_description'],
			),
			'spiderlog' => array(
				'description' => $txt['spider_log_desc'],
			),
			'tasklog' => array(
				'description' => $txt['scheduled_log_desc'],
			),
			'pruning' => array(
				'description' => $txt['pruning_log_desc'],
			),
		),
	);

	loadSource($log_functions[$sub_action][0]);
	$log_functions[$sub_action][1]();
}

?>