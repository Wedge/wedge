<?php
/**
 * Wedge
 *
 * Initializes the administration panel area for Wedge and routes the request appropriately.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file, unpredictable as this might be, handles basic administration.

	void Admin()
		- initialises all the basic context required for the admin center.
		- passes execution onto the relevant admin section.
		- if the passed section is not found it shows the admin home page.

	void AdminHome()
		- prepares all the data necessary for the administration front page.
		- uses the Admin template along with the admin block.
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
	global $txt, $context, $scripturl, $settings, $user_info;
	global $theme, $options, $boardurl, $admin_areas;

	// Load the language strings for use in the menu...
	loadLanguage('Admin');

	// No indexing evil stuff.
	$context['robot_no_index'] = true;

	loadSource('Subs-Menu');

	// Some preferences.
	$context['admin_preferences'] = !empty($options['admin_preferences']) ? unserialize($options['admin_preferences']) : array();

	// Define all the menu structure - see Subs-Menu.php for details!
	$admin_areas = array(
		'config' => array(
			'title' => $txt['admin_config'],
			'permission' => array('admin_forum'),
			'areas' => array(
				'featuresettings' => array(
					'label' => $txt['settings_title'],
					'file' => 'ManageSettings',
					'function' => 'ModifyFeatureSettings',
					'icon' => 'features.gif',
					'subsections' => array(
						'basic' => array($txt['mods_cat_features']),
						'',
						'pretty' => array($txt['pretty_urls']),
					),
				),
				'modfilters' => array(
					'label' => $txt['admin_mod_filters'],
					'file' => 'ManageModeration',
					'function' => 'ManageModeration',
					'icon' => 'permissions.gif',
					'bigicon' => 'moderation_filters.png',
				),
				'',
				'languages' => array(
					'label' => $txt['language_configuration'],
					'file' => 'ManageServer',
					'function' => 'ManageLanguages',
					'icon' => 'languages.gif',
					'bigicon' => 'languages.png',
					'subsections' => array(
						'edit' => array($txt['language_edit']),
						'add' => array($txt['language_add']),
						'',
						'settings' => array($txt['language_settings']),
					),
				),
				'',
				'current_theme' => array(
					'label' => $txt['theme_current_settings'],
					'file' => 'Themes',
					'function' => 'ThemesMain',
					'custom_url' => $scripturl . '?action=admin;area=theme;sa=settings;th=' . $theme['theme_id'],
					'icon' => 'current_theme.gif',
					'bigicon' => 'current_theme.png',
				),
				'theme' => array(
					'label' => $txt['theme_admin'],
					'file' => 'Themes',
					'function' => 'ThemesMain',
					'icon' => 'themes.gif',
					'bigicon' => 'themes_and_layout.png',
					'subsections' => array(
						'admin' => array($txt['themeadmin_admin_title']),
						'list' => array($txt['themeadmin_list_title']),
						'edit' => array($txt['themeadmin_edit_title']),
					),
				),
			),
		),
		'layout' => array(
			'title' => $txt['layout_controls'],
			'permission' => array('manage_boards', 'admin_forum', 'manage_smileys', 'manage_attachments', 'moderate_forum', 'edit_news', 'send_mail'),
			'areas' => array(
				'manageboards' => array(
					'label' => $txt['admin_boards'],
					'file' => 'ManageBoards',
					'function' => 'ManageBoards',
					'icon' => 'boards.gif',
					'bigicon' => 'boards.png',
					'permission' => array('manage_boards'),
					'subsections' => array(
						'main' => array($txt['boardsEdit']),
						'newcat' => array($txt['mboards_new_cat']),
						'',
						'settings' => array($txt['settings'], 'admin_forum'),
					),
				),
				'postsettings' => array(
					'label' => $txt['manageposts'],
					'file' => 'ManagePosts',
					'function' => 'ManagePostSettings',
					'permission' => array('admin_forum'),
					'icon' => 'posts.gif',
					'bigicon' => 'poststopics.png',
					'subsections' => array(
						'posts' => array($txt['manageposts_settings']),
						'topics' => array($txt['manageposts_topic_settings']),
						'',
						'bbc' => array($txt['manageposts_bbc_settings']),
						'censor' => array($txt['admin_censored_words']),
						'merge' => array($txt['manageposts_merge']),
						'drafts' => array($txt['manageposts_draft_settings']),
					),
				),
				'',
				'antispam' => array(
					'label' => $txt['antispam_title'],
					'file' => 'ManageSettings',
					'function' => 'ModifySpamSettings',
					'icon' => 'security.gif',
					'bigicon' => 'security.png',
					'subsections' => array(
					),
				),
				'manageattachments' => array(
					'label' => $txt['attachments_avatars'],
					'file' => 'ManageAttachments',
					'function' => 'ManageAttachments',
					'icon' => 'attachment.gif',
					'bigicon' => 'attach.png',
					'permission' => array('manage_attachments'),
					'subsections' => array(
						'browse' => array($txt['attachment_manager_browse']),
						'',
						'attachments' => array($txt['attachment_manager_settings']),
						'avatars' => array($txt['attachment_manager_avatar_settings']),
						'',
						'maintenance' => array($txt['attachment_manager_maintenance']),
					),
				),
				'aeva_embed' => array(
					'label' => $txt['media_admin_labels_embed'],
					'icon' => 'aeva.png',
					'bigicon' => !empty($settings['embed_enabled']) ? 'autoembed_on.png' : 'autoembed_off.png',
					'file' => 'media/ManageMedia',
					'function' => 'aeva_admin_init',
					'subsections' => array(
						'config' => array($txt['media_admin_settings_config']),
						'sites' => array($txt['media_admin_settings_sites']),
					),
				),
				'smileys' => array(
					'label' => $txt['smileys_manage'],
					'file' => 'ManageSmileys',
					'function' => 'ManageSmileys',
					'icon' => 'smiley.gif',
					'bigicon' => 'smileys.png',
					'permission' => array('manage_smileys'),
					'subsections' => array(
						'editsets' => array($txt['smiley_sets']),
						'addsmiley' => array($txt['smileys_add'], 'enabled' => !empty($settings['smiley_enable'])),
						'editsmileys' => array($txt['smileys_edit'], 'enabled' => !empty($settings['smiley_enable'])),
						'setorder' => array($txt['smileys_set_order'], 'enabled' => !empty($settings['smiley_enable'])),
						'editicons' => array($txt['icons_edit_message_icons'], 'enabled' => !empty($settings['messageIcons_enable'])),
						'',
						'settings' => array($txt['settings']),
					),
				),
				'',
				'news' => array(
					'label' => $txt['news_title'],
					'file' => 'ManageNews',
					'function' => 'ManageNews',
					'icon' => 'news.gif',
					'bigicon' => 'news_and_newsletters.png',
					'permission' => array('edit_news', 'send_mail', 'admin_forum'),
					'subsections' => array(
						'editnews' => array($txt['admin_edit_news'], 'edit_news'),
						'mailingmembers' => array($txt['admin_newsletters'], 'send_mail'),
						'',
						'settings' => array($txt['settings'], 'admin_forum'),
					),
				),
				'',
				'sengines' => array(
					'label' => $txt['search_engines'],
					'file' => 'ManageSearchEngines',
					'icon' => 'engines.gif',
					'bigicon' => !empty($settings['spider_mode']) ? 'search_engines_on.png' : 'search_engines_off.png',
					'function' => 'SearchEngines',
					'permission' => 'admin_forum',
					'subsections' => array(
						'stats' => array($txt['spider_stats'], 'enabled' => !empty($settings['spider_mode'])),
						'logs' => array($txt['spider_log'], 'enabled' => !empty($settings['spider_mode'])),
						'spiders' => array($txt['spiders'], 'enabled' => !empty($settings['spider_mode'])),
						'',
						'settings' => array($txt['settings']),
					),
				),
			),
		),
		'media' => array(
			'title' => $txt['media_title'],
			'permission' => array('media_manage'), // if not specified lower down, this will just be inherited, no need to set it individually!
			'areas' => array(
				'aeva_about' => array(
					'label' => $txt['media_admin_labels_about'],
					'icon' => 'administration.gif',
					'bigicon' => 'about.png',
					'enabled' => !empty($settings['media_enabled']),
					'file' => 'media/ManageMedia',
					'function' => 'aeva_admin_init',
				),
				'aeva_settings' => array(
					'label' => $txt['media_admin_labels_settings'],
					'icon' => 'settings.gif',
					'bigicon' => 'media_settings.png',
					'file' => 'media/ManageMedia',
					'function' => 'aeva_admin_init',
					'subsections' => array(
						'config' => array($txt['media_admin_settings_config'], ),
						'meta' => array($txt['media_admin_settings_meta'], 'enabled' => !empty($settings['media_enabled'])),
						'layout' => array($txt['media_admin_settings_layout'], 'enabled' => !empty($settings['media_enabled'])),
					),
				),
				'aeva_albums' => array(
					'label' => $txt['media_admin_labels_albums'],
					'enabled' => !empty($settings['media_enabled']),
					'icon' => 'mgallery.png',
					'bigicon' => 'albums.png',
					'file' => 'media/ManageMedia',
					'function' => 'aeva_admin_init',
					'subsections' => array(
						'index' => array($txt['media_admin_labels_index']),
						'normal' => array($txt['media_admin_filter_normal_albums']),
						'featured' => array($txt['media_admin_filter_featured_albums']),
						'',
						'add' => array($txt['media_admin_add_album']),
					),
				),
				'',
				'aeva_fields' => array(
					'label' => $txt['media_cf'],
					'enabled' => !empty($settings['media_enabled']),
					'icon' => 'packages.gif',
					'bigicon' => 'custom_fields.png',
					'file' => 'media/ManageMedia',
					'function' => 'aeva_admin_init',
					'subsections' => array(
						'index' => array($txt['media_admin_labels_index']),
						'edit' => array($txt['media_cf_add']),
					),
				),
				'',
				'aeva_perms' => array(
					'label' => $txt['media_admin_labels_perms'],
					'enabled' => !empty($settings['media_enabled']),
					'icon' => 'permissions.gif',
					'bigicon' => 'permissions.png',
					'file' => 'media/ManageMedia',
					'function' => 'aeva_admin_init',
				),
				'aeva_quotas' => array(
					'label' => $txt['media_admin_labels_quotas'],
					'enabled' => !empty($settings['media_enabled']),
					'icon' => 'attachment.gif',
					'bigicon' => 'quotas.png',
					'file' => 'media/ManageMedia',
					'function' => 'aeva_admin_init',
				),
				'aeva_bans' => array(
					'label' => $txt['media_admin_labels_bans'],
					'enabled' => !empty($settings['media_enabled']),
					'icon' => 'ban.gif',
					'bigicon' => 'ban_list.png',
					'file' => 'media/ManageMedia',
					'function' => 'aeva_admin_init',
					'subsections' => array(
						'index' => array($txt['media_admin_labels_index']),
						'add' => array($txt['media_admin_bans_add']),
					),
				),
				'',
				'aeva_ftp' => array(
					'label' => $txt['media_admin_labels_ftp'],
					'enabled' => !empty($settings['media_enabled']),
					'icon' => 'boards.gif',
					'bigicon' => 'ftpimport.png',
					'file' => 'media/ManageMedia',
					'function' => 'aeva_admin_init',
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
					'bigicon' => 'members.png',
					'permission' => array('moderate_forum'),
					'subsections' => array(
						'all' => array($txt['view_all_members']),
						'search' => array($txt['mlist_search']),
					),
				),
				'regcenter' => array(
					'label' => $txt['registration_center'],
					'file' => 'ManageRegistration',
					'function' => 'RegCenter',
					'icon' => 'regcenter.gif',
					'bigicon' => 'registration.png',
					'permission' => array('admin_forum', 'moderate_forum'),
					'subsections' => array(
						'register' => array($txt['admin_browse_register_new'], 'moderate_forum'),
						'agreement' => array($txt['registration_agreement'], 'admin_forum'),
						'reservednames' => array($txt['admin_reserved_set'], 'admin_forum'),
						'',
						'settings' => array($txt['settings'], 'admin_forum'),
					),
				),
				'',
				'membergroups' => array(
					'label' => $txt['admin_groups'],
					'file' => 'ManageMembergroups',
					'function' => 'ModifyMembergroups',
					'icon' => 'membergroups.gif',
					'bigicon' => 'membergroups.png',
					'permission' => array('manage_membergroups'),
					'subsections' => array(
						'index' => array($txt['membergroups_edit_groups'], 'manage_membergroups'),
						'add' => array($txt['membergroups_new_group'], 'manage_membergroups'),
						'',
						'settings' => array($txt['settings'], 'admin_forum'),
					),
				),
				'permissions' => array(
					'label' => $txt['edit_permissions'],
					'file' => 'ManagePermissions',
					'function' => 'ModifyPermissions',
					'icon' => 'permissions.gif',
					'bigicon' => 'permissions.png',
					'permission' => array('manage_permissions'),
					'subsections' => array(
						'index' => array($txt['permissions_groups'], 'manage_permissions'),
						'board' => array($txt['permissions_boards'], 'manage_permissions'),
						'profiles' => array($txt['permissions_profiles'], 'manage_permissions'),
						'',
						'settings' => array($txt['settings'], 'admin_forum'),
					),
				),
				'warnings' => array(
					'label' => $txt['warning_title'],
					'file' => 'ManageSettings',
					'function' => 'ModifyWarningSettings',
					'icon' => 'security.gif',
					'bigicon' => 'warning.png',
					'subsections' => array(
					),
				),
				'ban' => array(
					'label' => $txt['ban_title'],
					'file' => 'ManageBans',
					'function' => 'Ban',
					'icon' => 'ban.gif',
					'bigicon' => 'ban_list.png',
					'permission' => 'manage_bans',
					'subsections' => array(
						'list' => array($txt['ban_edit_list']),
						'add' => array($txt['ban_add_new']),
						'browse' => array($txt['ban_trigger_browse']),
					),
				),
				'memberoptions' => array(
					'label' => $txt['member_options_title'],
					'file' => 'ManageMemberOptions',
					'function' => 'ManageMemberOptions',
					'icon' => 'settings.gif',
					'bigicon' => 'memberoptions.png',
					'permission' => 'admin_forum',
					'subsections' => array(
						'options' => array($txt['configure_options']),
						'sig' => array($txt['signature_settings_short']),
						'prefs' => array($txt['admin_member_prefs']),
						'profile' => array($txt['custom_profile_shorttitle']),
						'whosonline' => array($txt['admin_whos_online']),
					),
				),
				'reports' => array(
					'label' => $txt['generate_reports'],
					'file' => 'Reports',
					'function' => 'ReportsMain',
					'icon' => 'reports.gif',
					'bigicon' => 'reports.png',
				),
				'',
				'pm' => array(
					'label' => $txt['admin_personal_messages'],
					'file' => 'ManageSettings',
					'function' => 'ModifyPmSettings',
					'icon' => 'members.gif',
					'bigicon' => !empty($settings['pm_enabled']) ? 'personal_messages_on.png' : 'personal_messages_off.png',
					'permission' => 'admin_forum',
				),
				'',
				'paidsubscribe' => array(
					'label' => $txt['paid_subscriptions'],
					'file' => 'ManagePaid',
					'icon' => 'paid.gif',
					'bigicon' => !empty($settings['paid_enabled']) ? 'paid_subs_on.png' : 'paid_subs_off.png',
					'function' => 'ManagePaidSubscriptions',
					'permission' => 'admin_forum',
					'subsections' => array(
						'view' => array($txt['paid_subs_view'], 'enabled' => !empty($settings['paid_enabled'])),
						'',
						'settings' => array($txt['settings']),
					),
				),
			),
		),
		'maintenance' => array(
			'title' => $txt['admin_maintenance'],
			'permission' => array('admin_forum'),
			'areas' => array(
				'serversettings' => array(
					'label' => $txt['admin_server_settings'],
					'file' => 'ManageServer',
					'function' => 'ModifySettings',
					'icon' => 'server.gif',
					'bigicon' => 'server_settings.png',
					'subsections' => array(
						'general' => array($txt['general_settings']),
						'database' => array($txt['database_paths_settings']),
						'cookie' => array($txt['cookies_sessions_settings']),
						'',
						'cache' => array($txt['caching_settings']),
						'loads' => array($txt['load_balancing_settings'], 'enabled' => strpos(strtolower(PHP_OS), 'win') !== 0),
						'proxy' => array($txt['proxy_settings']),
						'debug' => array($txt['debug_settings']),
					),
				),
				'mailqueue' => array(
					'label' => $txt['mailqueue_title'],
					'file' => 'ManageMail',
					'function' => 'ManageMail',
					'icon' => 'mail.gif',
					'bigicon' => 'mail_settings.png',
					'subsections' => array(
						'browse' => array($txt['mailqueue_browse'], 'admin_forum', 'enabled' => !empty($settings['mail_queue'])),
						'',
						'settings' => array($txt['mailqueue_settings'], 'admin_forum', 'enabled' => !empty($settings['mail_queue'])),
					),
				),
				'',
				'maintain' => array(
					'label' => $txt['maintain_title'],
					'file' => 'ManageMaintenance',
					'icon' => 'maintain.gif',
					'bigicon' => 'maintenance.png',
					'function' => 'ManageMaintenance',
					'subsections' => array(
						'routine' => array($txt['maintain_sub_routine'], 'admin_forum'),
						'database' => array($txt['maintain_sub_database'], 'admin_forum'),
						'members' => array($txt['maintain_sub_members'], 'admin_forum'),
						'topics' => array($txt['maintain_sub_topics'], 'admin_forum'),
					),
				),
				'aeva_maintenance' => array(
					'label' => $txt['media_admin_labels_maintenance'],
					'enabled' => !empty($settings['media_enabled']),
					'icon' => 'maintain.gif',
					'bigicon' => 'media_maintenance.png',
					'file' => 'media/ManageMedia',
					'function' => 'aeva_admin_init',
					'subsections' => array(
						'index' => array($txt['media_admin_maintenance_all_tasks']),
						'',
						'recount' => array($txt['media_admin_maintenance_recount']),
						'checkfiles' => array($txt['media_admin_maintenance_checkfiles']),
						'finderrors' => array($txt['media_admin_maintenance_finderrors']),
						'prune' => array($txt['media_admin_maintenance_prune']),
					),
				),
				'scheduledtasks' => array(
					'label' => $txt['maintain_tasks'],
					'file' => 'ManageScheduledTasks',
					'icon' => 'scheduled.gif',
					'bigicon' => 'scheduled_tasks.png',
					'function' => 'ManageScheduledTasks',
					'subsections' => array(
						'tasks' => array($txt['maintain_tasks'], 'admin_forum'),
						'tasklog' => array($txt['scheduled_log'], 'admin_forum'),
					),
				),
				'',
				'managesearch' => array(
					'label' => $txt['manage_search'],
					'file' => 'ManageSearch',
					'function' => 'ManageSearch',
					'icon' => 'search.gif',
					'bigicon' => 'search.png',
					'permission' => array('admin_forum'),
					'subsections' => array(
						'weights' => array($txt['search_weights']),
						'method' => array($txt['search_method']),
						'',
						'settings' => array($txt['settings']),
					),
				),
				'',
				'logs' => array(
					'label' => $txt['logs'],
					'function' => 'AdminLogs',
					'icon' => 'logs.gif',
					'bigicon' => 'logs.png',
					'subsections' => array(
						'errorlog' => array($txt['log_error'], 'admin_forum', 'enabled' => !empty($settings['enableErrorLogging']), 'url' => $scripturl . '?action=admin;area=logs;sa=errorlog;desc'),
						'intrusionlog' => array($txt['log_intrusion'], 'admin_forum'),
						'',
						'adminlog' => array($txt['log_admin'], 'admin_forum', 'enabled' => !empty($settings['log_enabled_admin'])),
						'modlog' => array($txt['log_moderation'], 'admin_forum', 'enabled' => !empty($settings['log_enabled_moderate'])),
						'spiderlog' => array($txt['log_spider'], 'admin_forum', 'enabled' => !empty($settings['spider_mode'])),
						'tasklog' => array($txt['log_scheduled'], 'admin_forum'),
						'',
						'settings' => array($txt['log_settings'], 'admin_forum'),
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
		'plugins' => array(
			'title' => $txt['plugin_manager'],
			'permission' => array('admin_forum'),
			'areas' => array(
				'plugins' => array(
					'label' => $txt['plugin_manager'],
					'file' => 'ManagePlugins',
					'function' => 'PluginsHome',
					'permission' => array('admin_forum'),
					'icon' => 'packages.gif',
					'bigicon' => 'plugin_manager.png',
					'subsections' => array(
						'plugins' => array($txt['plugin_manager']),
						'add' => array($txt['plugins_add_plugins']),
						/*'browse' => array($txt['browse_packages']),
						'packageget' => array($txt['download_packages'], 'url' => $scripturl . '?action=admin;area=packages;sa=packageget;get'),
						'perms' => array($txt['package_file_perms']),
						'',
						'options' => array($txt['package_settings']),*/
					),
				),
				'packages' => array(
					'label' => 'Packages (old)',
					'file' => 'Packages',
					'function' => 'Packages',
					'permission' => array('admin_forum'),
					'icon' => 'packages.gif',
					'bigicon' => 'plugin_manager.png',
					'subsections' => array(
						'browse' => array($txt['browse_packages']),
						'packageget' => array($txt['download_packages'], 'url' => $scripturl . '?action=admin;area=packages;sa=packageget;get'),
						'perms' => array($txt['package_file_perms']),
						'',
						'options' => array($txt['package_settings']),
					),
				),
			),
		),
	);

	$menuOptions = array(
		'menu_type' => '_dropdown',
	);
	if (isset($_GET['togglebar']) && (empty($_GET['area']) || $_GET['area'] == 'index'))
		$menuOptions['toggle_redirect_url'] = 'action=admin;' . $context['session_query'];

	// Any files to include for administration?
	if (!empty($settings['integrate_admin_include']))
	{
		$admin_includes = explode(',', $settings['integrate_admin_include']);
		foreach ($admin_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $theme['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Integrate plugins with handy menu pages.
	if (!empty($settings['plugins_admin']))
	{
		$admin_areas['plugins']['areas'][] = '';
		$admin_cache = unserialize($settings['plugins_admin']);
		foreach ($admin_cache as $plugin_id => $plugin_details)
		{
			if (!isset($context['plugins_dir'][$plugin_id]))
				continue;

			$new_item = array(
				'label' => $plugin_details['name'],
				'file' => 'ManageSettings',
				'function' => 'ModifySettingsPageHandler',
				'plugin_id' => $plugin_id,
			);
			foreach (array('icon', 'bigicon', 'permission') as $item)
				if (isset($plugin_details[$item]))
					$new_item[$item] = strtr($plugin_details[$item], array('$pluginurl' => $context['plugins_url'][$plugin_id]));
			$admin_areas['plugins']['areas'][$plugin_details['area']] = $new_item;
		}
	}

	// Let modders modify admin areas easily. You can insert a top-level menu
	// into the admin menu (global $admin_areas) by doing something like this in your hook:
	// $admin_areas = array_merge(array_splice($admin_areas, 0, 2), $my_top_level_menu_array, $admin_areas);

	call_hook('admin_areas');

	// Make sure the administrator has a valid session...
	validateSession();

	// Load the template, and build the CSS file (we need the admin menu to be filled at this point.)
	loadTemplate('Admin');
	add_css_file(array('admin', 'admenu'), true);

	// Actually create the menu!
	$admin_include_data = createMenu($admin_areas, $menuOptions);
	unset($admin_areas);

	// Nothing valid?
	if ($admin_include_data == false)
		fatal_lang_error('no_access', false);

	// The admin search function used to depend on the front page, but really, there's no need to put that into the menu...
	// Note that we have to explicitly override the default menu behaviour here because the menu code never expects to have items from outside it.
	$menu_context =& $context['menu_data_' . $context['max_menu_id']];
	if (isset($_REQUEST['area']) && $_REQUEST['area'] == 'search')
	{
		$admin_include_data['current_area'] = 'search';
		$admin_include_data['function'] = 'AdminSearch';
		$admin_include_data['label'] = $txt['admin_search_results'];
		$menu_context['current_section'] = '';
		$menu_context['current_area'] = '';
		$menu_context['toggle_url'] = $menu_context['base_url'] . $menu_context['extra_parameters'] . ';togglebar';
	}
	// The admin front page is not part of the above. But if you can see any of the items in the admin panel, you can see the front page too.
	elseif (empty($_GET['area']) || $_GET['area'] != $menu_context['current_area'])
	{
		$admin_include_data['current_area'] = 'index';
		$admin_include_data['function'] = 'AdminHome';
		$menu_context['current_section'] = '';
		$menu_context['current_area'] = '';
		$menu_context['toggle_url'] = $menu_context['base_url'] . $menu_context['extra_parameters'] . ';togglebar';
	}

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=admin',
		'name' => $txt['admin_center'],
	);
	if (isset($admin_include_data['current_area']) && $admin_include_data['current_area'] != 'index')
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=admin;area=' . $admin_include_data['current_area'] . ';' . $context['session_query'],
			'name' => $admin_include_data['label'],
		);
	if (!empty($admin_include_data['current_subsection']) && $admin_include_data['current_area'] != 'index' && $admin_include_data['subsections'][$admin_include_data['current_subsection']][0] != $admin_include_data['label'])
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=admin;area=' . $admin_include_data['current_area'] . ';sa=' . $admin_include_data['current_subsection'] . ';' . $context['session_query'],
			'name' => $admin_include_data['subsections'][$admin_include_data['current_subsection']][0],
		);

	// Make a note of the Unique ID for this menu.
	$context['admin_menu_id'] = $context['max_menu_id'];
	$context['admin_menu_name'] = 'menu_data_' . $context['admin_menu_id'];

	// Why on the admin are we?
	$context['admin_area'] = $admin_include_data['current_area'];

	// Set up the sidebar information, like the news and so on, and introduce all the JavaScript we'll need.
	$context['can_admin'] = allowedTo('admin_forum');
	$context['forum_version'] = WEDGE_VERSION;
	setupAdminSidebar();

	// Now - finally - call the right place!
	if (isset($admin_include_data['file']))
	{
		if (is_array($admin_include_data['file']))
			loadPluginSource($admin_include_data['file'][0], $admin_include_data['file'][1]);
		else
			loadSource($admin_include_data['file']);
	}

	$admin_include_data['function']();
}

// The main administration section.
function AdminHome()
{
	global $txt, $scripturl, $context, $user_info, $boardurl, $settings;

	wetem::load('admin');
	$context['page_title'] = $txt['admin_center'];

	// For readability.
	$menu_context =& $context['menu_data_' . $context['max_menu_id']];
}

// We have to do some stuff for the admin sidebar.
function setupAdminSidebar()
{
	global $settings, $txt, $context, $scripturl;

	// Find all of this forum's administrators...
	loadSource('Subs-Membergroups');

	// Add a 'more' link if there are more than 32.
	if (listMembergroupMembers_Href($context['administrators'], 1, 32) && allowedTo('manage_membergroups'))
		$context['more_admins_link'] = '<a href="' . $scripturl . '?action=moderate;area=viewgroups;sa=members;group=1">' . $txt['more'] . '</a>';

	// Add the blocks into the sidebar.
	wetem::add('sidebar', array('admin_live_news', 'admin_support_info'));

	// The below functions include all the scripts needed from the wedge.org site. The language and format are passed for internationalization.
	if (empty($settings['disable_wedge_js']))
		add_js_file(array(
			$scripturl . '?action=viewremote;filename=current-version.js',
			$scripturl . '?action=viewremote;filename=latest-news.js'
		), true);

	add_js_file('scripts/admin.js');

	// This sets the announcements and current versions themselves ;)
	add_js('
	new we_AdminIndex({
		bLoadAnnouncements: true,
		sAnnouncementTemplate: ', JavaScriptEscape('
			<dl>
				%content%
			</dl>
		'), ',
		sAnnouncementMessageTemplate: ', JavaScriptEscape('
			<dt><a href="%href%">%subject%</a> ' . $txt['on'] . ' %time%</dt>
			<dd>
				%message%
			</dd>
		'), ',
		sAnnouncementContainerId: \'wedge_news\',
		sMonths: [\'', implode('\', \'', $txt['months']), '\'],
		sMonthsShort: [\'', implode('\', \'', $txt['months_short']), '\'],
		sDays: [\'', implode('\', \'', $txt['days']), '\'],
		sDaysShort: [\'', implode('\', \'', $txt['days_short']), '\'],

		bLoadVersions: true,
		sVersionOutdatedTemplate: ' . JavaScriptEscape('
			<span class="alert">%currentVersion%</span>
		') . ',

		bLoadUpdateNotification: true,
		sUpdateTitle: ' . JavaScriptEscape($txt['update_available']) . ',
		sUpdateMessage: ' . JavaScriptEscape($txt['update_message']) . ',
		sUpdateLink: we_script + \'?action=admin;area=packages;pgdownload;auto;package=%package%;' . $context['session_query'] . '\'
	});');
}

// This allocates out all the search stuff.
function AdminSearch()
{
	global $txt, $context;

	isAllowedTo('admin_forum');

	// What can we search for?
	$subactions = array(
		'internal' => 'AdminSearchInternal',
		'member' => 'AdminSearchMember',
	);

	$context['search_type'] = !isset($_REQUEST['search_type']) || !isset($subactions[$_REQUEST['search_type']]) ? 'internal' : $_REQUEST['search_type'];
	$context['search_term'] = isset($_REQUEST['search_term']) ? westr::htmlspecialchars($_REQUEST['search_term'], ENT_QUOTES) : '';

	wetem::load('admin_search_results');
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
	global $context, $txt, $helptxt, $scripturl, $settings_search, $settings;

	// Try to get some more memory.
	@ini_set('memory_limit', '128M');

	// Load a lot of language files.
	$language_files = array(
		'Help', 'ManageMail', 'ManageSettings', 'ManageBoards', 'ManagePaid', 'ManagePermissions', 'Search',
		'Login', 'ManageSmileys', 'ManageMaintenance',
	);
	loadLanguage(implode('+', $language_files));

	// All the files we need to include.
	$include_files = array(
		'ManageSettings', 'ManageBoards', 'ManageNews', 'ManageAttachments', 'ManageMail', 'ManageMemberOptions', 'ManagePaid', 'ManageMaintenance',
		'ManagePermissions', 'ManagePosts', 'ManageRegistration', 'ManageSearch', 'ManageSearchEngines', 'ManageServer', 'ManageSmileys',
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
			array('CAPTCHA', 'area=antispam'),
		),
		'preferences' => array(
		),
		'tasks' => array(
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
					if (isset($sublabel, $sublabel['label']))
						$search_data['sections'][] = array($sublabel['label'], 'area=' . $menu_key . ';sa=' . $key);
		}
	}

	// This is a special array of functions that contain setting data - we query all these to simply pull all setting bits!
	$search = array(
		'settings' => array(
			array('ModifyBasicSettings', 'area=featuresettings;sa=basic'),
			array('ModifyPrettyURLs', 'area=featuresettings;sa=pretty'),
			array('ModifyMemberSettings', 'area=memberoptions;sa=options'),
			array('ModifySignatureSettings', 'area=memberoptions;sa=sig'),
			array('ModifyWhosOnline', 'area=memberoptions;sa=whosonline'),
			array('ModifySpamSettings', 'area=antispam'),
			array('ModifyWarningSettings', 'area=warnings'),
			array('ManageAttachmentSettings', 'area=manageattachments;sa=attachments'),
			array('ManageAvatarSettings', 'area=manageattachments;sa=avatars'),
			array('EditBoardSettings', 'area=manageboards;sa=settings'),
			array('ModifyMailSettings', 'area=mailqueue;sa=settings'),
			array('ModifyNewsSettings', 'area=news;sa=settings'),
			array('GeneralPermissionSettings', 'area=permissions;sa=settings'),
			array('ModifyPostSettings', 'area=postsettings;sa=posts'),
			array('ModifyBBCSettings', 'area=postsettings;sa=bbc'),
			array('ModifyTopicSettings', 'area=postsettings;sa=topics'),
			array('ModifyMergeSettings', 'area=postsettings;sa=merge'),
			array('ModifyDraftSettings', 'area=postsettings;sa=drafts'),
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
			array('ModifyLogSettings', 'area=logs;sa=settings'),
			array('ModifyPmSettings', 'area=pm'),
		),
		'preferences' => array(
			array('ModifyMemberPreferences', 'area=memberoptions;sa=prefs'),
		),
		'tasks' => array(
			array('MaintainRoutine', 'area=maintain'),
		),
	);

	if (!empty($settings['plugins_admin']))
	{
		$admin_cache = unserialize($settings['plugins_admin']);
		foreach ($admin_cache as $plugin_id => $plugin_details)
		{
			if (!isset($context['plugins_dir'][$plugin_id]))
				continue;
			$search['settings'][] = array('ModifySettingsPageHandler', 'area=' . $plugin_details['area'], $plugin_id);
		}
	}

	// It will probably never be used by anyone, but anyway...
	call_hook('admin_search', array(&$search));

	foreach ($search as $setting_type => $search_places)
	{
		if ($setting_type == 'tasks')
			foreach ($search_places as $setting_area)
			{
				$tasks = $setting_area[0](true);
				foreach ($tasks as $id => $task)
					$search_data[$setting_type][] = array($id, $setting_area[1]);
			}
		else
			foreach ($search_places as $setting_area)
			{
				// Get a list of their variables.
				if (isset($setting_area[2]))
					$config_vars = $setting_area[0](true, $setting_area[2]);
				else
					$config_vars = $setting_area[0](true);

				foreach ($config_vars as $var)
					if (!empty($var[1]) && !in_array($var[0], array('permissions', 'switch', 'desc', 'warning')))
					{
						// Using the construction of prepareServerSettingsContext?
						if (isset($var[2]) && in_array($var[2], array('file', 'db')))
						{
							$item = array($var[0], $setting_area[1]);
							if (isset($var[5], $helptxt[$var[5]]))
								$item[2] = $var[5];
							$search_data[$setting_type][] = $item;
						}
						// Then it's prepareDBSettingsContext style.
						else
						{
							$item = array($var[1], $setting_area[1]);
							if (isset($var['help'], $helptxt[$var['help']]))
								$item[2] = $var['help'];
							$search_data[$setting_type][] = $item;
						}
					}
			}
	}

	$temp = $search_data;
	unset($temp['settings'], $temp['sections']);
	trigger_error(print_r($temp, true));

	$context['page_title'] = $txt['admin_search_results'];
	$context['search_results'] = array();

	$search_term = strtolower(un_htmlspecialchars($context['search_term']));
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
				$name = preg_replace('~<(?:dfn)>.+?</(?:dfn)>~', '', $name);

				$context['search_results'][] = array(
					'url' => (substr($item[1], 0, 4) == 'area' ? $scripturl . '?action=admin;' . $item[1] : $item[1]) . ';' . $context['session_query'] . ((substr($item[1], 0, 4) == 'area' && $section != 'sections' ? '#' . $item[0][0] : '')),
					'name' => $name,
					'type' => $section,
					'help' => shorten_subject(isset($item[2]) ? strip_tags($helptxt[$item[2]]) : (isset($helptxt[$found]) ? strip_tags($helptxt[$found]) : ''), 255),
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

// This function decides which log to load.
function AdminLogs()
{
	global $context, $txt, $scripturl;

	// These are the logs they can load.
	$log_functions = array(
		'errorlog' => array('ManageErrors', 'ViewErrorLog'),
		'intrusionlog' => array('ManageErrors', 'ViewIntrusionLog'),
		'adminlog' => array('Modlog', 'ViewModlog'),
		'modlog' => array('Modlog', 'ViewModlog'),
		'spiderlog' => array('ManageSearchEngines', 'SpiderLog'),
		'tasklog' => array('ManageScheduledTasks', 'TaskLog'),
		'settings' => array('ManageSettings', 'ModifyLogSettings'),
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
			'intrusionlog' => array(
				'description' => $txt['intrusion_log_desc'],
			),
			'adminlog' => array(
				'description' => $txt['admin_log_desc'],
			),
			'modlog' => array(
				'description' => $txt['moderation_log_desc'],
			),
			'spiderlog' => array(
				'description' => $txt['spider_log_desc'],
			),
			'tasklog' => array(
				'description' => $txt['scheduled_log_desc'],
			),
			'settings' => array(
				'description' => $txt['log_settings_desc'],
			),
		),
	);

	loadSource($log_functions[$sub_action][0]);
	$log_functions[$sub_action][1]();
}

?>