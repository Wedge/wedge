<?php
/**********************************************************************************
* FixLanguage.php                                                                 *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC3                                         *
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

//!!! No longer the case!
/* This file is used during the development of SMF 2.0 to keep track of text key changes. It will be deleted
   before distribution and it's only purpose is to ensure people using a non-default language are not left
   with errors. Eventually these key changes will form part of the translator for 2.0.

   Note this file is included from loadLanguage, and will take some processing power I'm afraid. */

// old_key => new_key
$txtChanges = array(
	'Admin' => array(
		4 => 'admin_boards',
		6 => 'admin_newsletters',
		7 => 'admin_edit_news',
		8 => 'admin_groups',
		9 => 'admin_members',
		135 => 'admin_censored_words',
		207 => 'admin_reserved_names',
		'attachment_mode' => 'attachmentEnable',
		'attachment_mode_deactivate' => 'attachmentEnable_deactivate',
		'attachment_mode_enable_all' => 'attachmentEnable_enable_all',
		'attachment_mode_disable_new' => 'attachmentEnable_disable_new',
		5 => 'admin_users',
		11 => 'admin_members_list',
		65 => 'admin_next',
		136 => 'admin_censored_where',
		141 => 'admin_censored_desc',
		216 => 'admin_template_edit',
		222 => 'admin_server_settings',
		341 => 'admin_reserved_set',
		342 => 'admin_reserved_line',
		347 => 'admin_basic_settings',
		348 => 'admin_maintain',
		350 => 'admin_title',
		351 => 'admin_url',
		352 => 'cookie_name',
		355 => 'admin_webmaster_email',
		356 => 'boarddir',
		360 => 'sourcesdir',
		379 => 'admin_news',
		380 => 'admin_guest_post',
		426 => 'admin_manage_members',
		427 => 'admin_main',
		428 => 'admin_config',
		429 => 'admin_version_check',
		495 => 'admin_smffile',
		496 => 'admin_smfpackage',
		501 => 'admin_maintenance',
		521 => 'admin_image_text',
		571 => 'admin_credits',
		584 => 'admin_agreement',
		608 => 'admin_delete_members',
		610 => 'admin_repair',
		644 => 'admin_main_welcome',
		670 => 'admin_news_desc',
		684 => 'administrators',
		699 => 'admin_reserved_desc',
		702 => 'admin_activation_email',
		726 => 'admin_match_whole',
		727 => 'admin_match_case',
		728 => 'admin_check_user',
		729 => 'admin_check_display',
		735 => 'admin_newsletter_send',
		739 => 'admin_fader_delay',
		740 => 'admin_bbc',
		'smf1' => 'admin_backup_fail',
		'smf5' => 'database_server',
		'smf6' => 'database_user',
		'smf7' => 'database_password',
		'smf8' => 'database_name',
		'smf11' => 'registration_agreement',
		'smf12' => 'registration_agreement_desc',
		'smf54' => 'database_prefix',
		'smf73' => 'errors_list',
		'smf74' => 'errors_found',
		'smf85' => 'errors_fix',
		'smf86' => 'errors_fixing',
		'smf92' => 'errors_fixed',
		'smf201' => 'attachments_avatars',
		'smf202' => 'attachments_desc',
		'smf203' => 'attachment_stats',
		'smf204' => 'attachment_total',
		'smf205' => 'attachmentdir_size',
		'smf206' => 'attachment_space',
		'smf207' => 'attachment_options',
		'smf208' => 'attachment_log',
		'smf209' => 'attachment_remove_old',
		'smf210' => 'attachment_remove_size',
		'smf213' => 'attachment_name',
		'smf214' => 'attachment_file_size',
		'smf215' => 'attachmentdir_size_not_set',
		'smf216' => 'attachment_delete_admin',
		'smf217' => 'live',
		'smf219' => 'remove_all',
		'smf281' => 'database_optimize',
		'smf282' => 'database_numb_tables',
		'smf283' => 'database_optimize_attempt',
		'smf284' => 'database_optimizing',
		'smf285' => 'database_already_optimized',
		'smf285b' => 'database_opimize_unneeded',
		'smf286' => 'database_optimized',
		'smf310' => 'database_no_id',
		'smf319' => 'approve_new_members',
		'smf320' => 'agreement_not_writable',
		'dvc1' => 'version_check_desc',
		'dvc_more' => 'version_check_more',
		'1fyi' => 'cant_connect',
		'package1' => 'package',
		724 => 'ban_ip',
		725 => 'ban_email',
		7252 => 'ban_username',
		'maintenance1' => 'maintenance_subject',
		'maintenance2' => 'maintenance_message',
		'errlog1' => 'errlog',
		'errlog2' => 'errlog_desc',
		'theme4' => 'theme_settings',
		'smf231' => 'censor_whole_words',
	),
	'Errors' => array(
		1 => 'no_access',
		73 => 'mods_only',
		75 => 'no_name',
		76 => 'no_email',
		90 => 'topic_locked',
		91 => 'no_password',
		100 => 'already_a_user',
		134 => 'cant_move',
		138 => 'members_only',
		165 => 'login_to_post',
		213 => 'passwords_dont_match',
		223 => 'register_to_use',
		241 => 'password_invalid_character',
		242 => 'name_invalid_character',
		243 => 'email_invalid_character',
		244 => 'username_reserved',
		337 => 'numbers_one_to_nine',
		453 => 'not_a_user',
		472 => 'not_a_topic',
		730 => 'email_in_use',
		'smf26' => 'didnt_select_vote',
		'smf27' => 'poll_error',
		'smf28' => 'members_only',
		'smf31' => 'locked_by_admin',
		'smf60' => 'not_enough_posts_karma',
		'smf61' => 'cant_change_own_karma',
		'smf62' => 'karma_wait_time',
		'smf63' => 'feature_disabled',
		'smf115b' => 'cant_access_upload_path',
		'smf122' => 'file_too_big',
		'smf124' => 'attach_timeout',
		'smf125' => 'filename_exists',
		'smf126' => 'ran_out_of_space',
		'smf191' => 'couldnt_connect',
		'smf232' => 'no_board',
		'smf253' => 'cant_split',
		'smf262' => 'cant_merge',
		'smf263' => 'no_topic_id',
		'smf268' => 'split_first_post',
		'smf270' => 'topic_one_post',
		'smf271' => 'no_posts_selected',
		'smf271b' => 'selected_all_posts',
		'smf272' => 'cant_find_messages',
		'smf273' => 'cant_insert_topic',
		'smf289' => 'already_a_mod',
		'smf304' => 'session_timeout',
		'smf305' => 'session_verify_fail',
		'smf306' => 'verify_url_fail',
		'theme3' => 'no_theme',
		'pswd7' => 'incorrect_answer',
		'rtm11' => 'no_mods',
		'calendar1' => 'invalid_month',
		'calendar2' => 'invalid_year',
		'calendar7' => 'event_month_missing',
		'calendar8' => 'event_year_missing',
		'calendar14' => 'event_day_missing',
		'calendar15' => 'event_title_missing',
		'calendar16' => 'invalid_date',
		'calendar17' => 'no_event_title',
		'calendar18' => 'missing_event_id',
		'calendar19' => 'cant_edit_event',
		'calendar38' => 'missing_board_id',
		'calendar39' => 'missing_topic_id',
		'calendar40' => 'topic_doesnt_exist',
		'calendar41' => 'not_your_topic',
		'calendar42' => 'board_doesnt_exist',
		'calendar55' => 'no_span',
		'calendar56' => 'invalid_days_numb',
		'filename_exisits' => 'filename_exists',
		'slected_all_posts' => 'selected_all_posts',
	),
	'Help' => array(
		1006 => 'close_window',
		'attachmentEnable' => 'attachment_manager_settings',
		'avatar_allow_server_stored' => 'avatar_server_stored',
		'avatar_allow_external_url' => 'avatar_external',
		'avatar_allow_upload' => 'avatar_upload',
		'default_personalText' => 'default_personal_text',
	),
	'index' => array(
		2 => 'admin',
		10 => 'save',
		17 => 'modify',
		18 => 'forum_index',
		19 => 'members',
		20 => 'board_name',
		21 => 'posts',
		22 => 'last_post',
		24 => 'no_subject',
		26 => 'member_postcount',
		27 => 'view_profile',
		28 => 'guest_title',
		29 => 'author',
		30 => 'on',
		31 => 'remove',
		33 => 'start_new_topic',
		34 => 'login',
		35 => 'username',
		36 => 'password',
		40 => 'username_no_exist',
		62 => 'board_moderator',
		63 => 'remove_topic',
		64 => 'topics',
		66 => 'modify_msg',
		68 => 'name',
		69 => 'email',
		70 => 'subject',
		72 => 'message',
		79 => 'profile',
		81 => 'choose_pass',
		82 => 'verify_pass',
		87 => 'position',
		92 => 'profile_of',
		94 => 'total',
		95 => 'posts_made',
		96 => 'website',
		97 => 'register',
		101 => 'message_index',
		102 => 'news',
		103 => 'home',
		104 => 'lock_unlock',
		105 => 'post',
		106 => 'error_occured',
		107 => 'at',
		108 => 'logout',
		109 => 'started_by',
		110 => 'replies',
		111 => 'last_post',
		114 => 'admin_login',
		118 => 'topic',
		119 => 'help',
		121 => 'remove_message',
		125 => 'notify',
		126 => 'notify_request',
		130 => 'regards_team',
		131 => 'notify_replies',
		132 => 'move_topic',
		133 => 'move_to',
		139 => 'pages',
		140 => 'users_active',
		144 => 'personal_messages',
		145 => 'reply_quote',
		146 => 'reply',
		151 => 'msg_alert_none',
		152 => 'msg_alert_you_have',
		153 => 'msg_alert_messages',
		154 => 'remove_message',
		158 => 'online_users',
		159 => 'personal_message',
		160 => 'jump_to',
		161 => 'go',
		162 => 'are_sure_remove_topic',
		163 => 'yes',
		164 => 'no',
		166 => 'search_results',
		167 => 'search_end_results',
		170 => 'search_no_results',
		176 => 'search_on',
		182 => 'search',
		190 => 'all',
		193 => 'back',
		194 => 'password_reminder',
		195 => 'topic_started',
		196 => 'title',
		197 => 'post_by',
		200 => 'memberlist_searchable',
		201 => 'welcome_member',
		208 => 'admin_center',
		211 => 'last_edit',
		212 => 'notify_deactivate',
		214 => 'recent_posts',
		227 => 'location',
		231 => 'gender',
		233 => 'date_registered',
		234 => 'recent_view',
		235 => 'recent_updated',
		238 => 'male',
		239 => 'female',
		240 => 'error_invalid_characters_username',
		247 => 'welmsg_hey',
		248 => 'welmsg_welcome',
		249 => 'welmsg_please',
		250 => 'welmsg_back',
		251 => 'select_destination',
		279 => 'posted_by',
		287 => 'icon_smiley',
		288 => 'icon_angry',
		289 => 'icon_cheesy',
		290 => 'icon_laugh',
		291 => 'icon_sad',
		292 => 'icon_wink',
		293 => 'icon_grin',
		294 => 'icon_shocked',
		295 => 'icon_cool',
		296 => 'icon_huh',
		298 => 'moderator',
		299 => 'moderators',
		300 => 'mark_board_read',
		301 => 'views',
		302 => 'new',
		303 => 'view_all_members',
		305 => 'view',
		// This removes this entry.
		307 => 'email',
		315 => 'forgot_your_password',
		450 => 'icon_rolleyes',
		451 => 'icon_tongue',
		526 => 'icon_embarrassed',
		527 => 'icon_lips',
		528 => 'icon_undecided',
		529 => 'icon_kiss',
		530 => 'icon_cry',
		685 => 'info_center_title',
		'calendar23' => 'calendar_post_event',
		'smf240' => 'quote',
		'smf251' => 'split',
		'smf252' => 'merge',
		'MSN' => 'msn',
		317 => 'date',
		318 => 'from',
		319 => 'subject',
		322 => 'check_new_messages',
		324 => 'to',
		330 => 'board_topics',
		331 => 'members_title',
		332 => 'members_list',
		333 => 'new_posts',
		334 => 'old_posts',
		371 => 'time_offset',
		377 => 'or',
		398 => 'no_matches',
		418 => 'notification',
		430 => 'your_ban',
		452 => 'mark_as_read',
		456 => 'locked_topic',
		457 => 'normal_topic',
		462 => 'go_caps',
		465 => 'print',
		467 => 'profile',
		468 => 'topic_summary',
		470 => 'not_applicable',
		471 => 'message_lowercase',
		473 => 'name_in_use',
		488 => 'total_members',
		489 => 'total_posts',
		490 => 'total_topics',
		497 => 'mins_logged_in',
		507 => 'preview',
		508 => 'always_logged_in',
		511 => 'logged',
		512 => 'ip',
		513 => 'icq',
		515 => 'www',
		525 => 'by',
		578 => 'hours',
		579 => 'days_word',
		581 => 'newest_member',
		582 => 'search_for',
		603 => 'aim',
		604 => 'yim',
		616 => 'maintain_mode_on',
		641 => 'read',
		642 => 'times',
		645 => 'forum_stats',
		656 => 'latest_member',
		658 => 'total_cats',
		659 => 'latest_post',
		660 => 'you_have',
		661 => 'click',
		662 => 'here',
		663 => 'to_view',
		665 => 'total_boards',
		668 => 'print_page',
		679 => 'valid_email',
		683 => 'geek',
		707 => 'send_topic',
		721 => 'hide_email',
		737 => 'check_all',
		1001 =>'database_error',
		1002 => 'try_again',
		1003 => 'file',
		1004 => 'line',
		1005 => 'tried_to_repair',
		'smf10' => 'today',
		'smf10b' => 'yesterday',
		'smf20' => 'new_poll',
		'smf21' => 'poll_question',
		'smf23' => 'poll_vote',
		'smf24' => 'poll_total_voters',
		'smf25' => 'shortcuts',
		'smf29' => 'poll_results',
		'smf30' => 'poll_lock',
		'smf30b' => 'poll_unlock',
		'smf39' => 'poll_edit',
		'smf43' => 'poll',
		'smf47' => 'one_day',
		'smf48' => 'one_week',
		'smf49' => 'one_month',
		'smf50' => 'forever',
		'smf52' => 'quick_login_dec',
		'smf53' => 'one_hour',
		'smf56' => 'moved',
		'smf57' => 'moved_why',
		'smf82' => 'board',
		'smf88' => 'in',
		'smf96' => 'sticky_topic',
		'smf138' => 'delete',
		'smf199' => 'your_pms',
		'smf211' => 'kilobyte',
		'smf223' => 'more_stats',
		'smf238' => 'code',
		'smf239' => 'quote_from',
		'smf254' => 'subject_new_topic',
		'smf255' => 'split_this_post',
		'smf256' => 'split_after_and_this_post',
		'smf257' => 'select_split_posts',
		'smf258' => 'new_topic',
		'smf259' => 'split_successful',
		'smf260' => 'origin_topic',
		'smf261' => 'please_select_split',
		'smf264' => 'merge_successful',
		'smf265' => 'new_merged_topic',
		'smf266' => 'topic_to_merge',
		'smf267' => 'target_board',
		'smf269' => 'target_topic',
		'smf274' => 'merge_confirm',
		'smf275' => 'with',
		'smf276' => 'merge_desc',
		'smf277' => 'set_sticky',
		'smf278' => 'set_nonsticky',
		'smf279' => 'set_lock',
		'smf280' => 'set_unlock',
		'smf298' => 'search_advanced',
		'smf299' => 'security_risk',
		'smf300' => 'not_removed',
		'smf301' => 'page_created',
		'smf302' => 'seconds_with',
		'smf302b' => 'queries',
		'smf315' => 'report_to_mod_func',
		'online2' => 'online',
		'online3' => 'offline',
		'online4' => 'pm_online',
		'online5' => 'pm_offline',
		'online8' => 'status',
		'topbottom4' => 'go_up',
		'topbottom5' => 'go_down',
		'calendar3' => 'birthdays',
		'calendar4' => 'events',
		'calendar5' => 'calendar_prompt',
		'calendar3b' => 'birthdays_upcoming',
		'calendar4b' => 'events_upcoming',
		'calendar9' => 'calendar_month',
		'calendar10' => 'calendar_year',
		'calendar11' => 'calendar_day',
		'calendar12' => 'calendar_event_title',
		'calendar13' => 'calendar_post_in',
		'calendar20' => 'calendar_edit',
		'calendar21' => 'event_delete_confirm',
		'calendar22' => 'event_delete',
		'calendar24' => 'calendar',
		'calendar37' => 'calendar_link',
		'calendar43' => 'calendar_link_event',
		'calendar47' => 'calendar_upcoming',
		'calendar47b' => 'calendar_today',
		'calendar51' => 'calendar_week',
		'calendar54' => 'calendar_numb_days',
		'mlist_search2' => 'mlist_search_again',
		'quick_reply_1' => 'quick_reply',
		'quick_reply_2' => 'quick_reply_desc',
		'rtm1' => 'report_to_mod',
	),
	'Login' => array(
		635 => 'login_below',
		636 => 'login_or_register',
		637 => 'login_with_forum',
		37 => 'need_username',
		38 => 'no_password',
		39 => 'incorrect_password',
		98 => 'choose_username',
		'maintenance3' => 'in_maintain_mode',
		115 => 'login_maintenance_mode',
		155 => 'maintain_mode',
		245 => 'registration_successful',
		431 => 'now_a_member',
		492 => 'your_password',
		500 => 'valid_email_needed',
		517 => 'required_info',
		520 => 'identification_by_smf',
		585 => 'agree',
		586 => 'decline',
		633 => 'warning',
		634 => 'only_members_can_access',
		701 => 'may_change_in_profile',
		719 => 'your_username_is',
		'coppa_not_completed1' => 'coppa_no_concent',
		'coppa_not_completed2' => 'coppa_need_more_details',
		'change_password_1' => 'change_password_login',
		'change_password_2' => 'change_password_new',
		'admin_setting_registration_method' => 'setting_registration_method',
		'admin_setting_registration_disabled' => 'setting_registration_disabled',
		'admin_setting_registration_activate' => 'setting_registration_activate',
		'admin_setting_registration_approval' => 'setting_registration_approval',
		'admin_setting_notify_new_registration' => 'setting_notify_new_registration',
		'admin_setting_send_welcomeEmail' => 'setting_send_welcomeEmail',
		'admin_setting_coppaAge' => 'setting_coppaAge',
		'admin_setting_coppaAge_desc' => 'setting_coppaAge_desc',
		'admin_setting_coppaType' => 'setting_coppaType',
		'admin_setting_coppaType_reject' => 'setting_coppaType_reject',
		'admin_setting_coppaType_approval' => 'setting_coppaType_approval',
		'admin_setting_coppaPost' => 'setting_coppaPost',
		'admin_setting_coppaPost_desc' => 'setting_coppaPost_desc',
		'admin_setting_coppaFax' => 'setting_coppaFax',
		'admin_setting_coppaPhone' => 'setting_coppaPhone',
	),
	'ManageBoards' => array(
		41 => 'boards_and_cats',
		43 => 'order',
		44 => 'full_name',
		672 => 'name_on_display',
		677 => 'boards_and_cats_desc',
	),
	'ManageSmileys' => array(
		'smiley_sets_enable' => 'setting_smiley_sets_enable',
		'smiley_sets_base_url' => 'setting_smileys_url',
		'smiley_sets_base_dir' => 'setting_smileys_dir',
		'smileys_enable' => 'setting_smiley_enable',
		'icons_enable_customized' => 'setting_messageIcons_enable',
		'icons_enable_customized_note' => 'setting_messageIcons_enable_note',
	),
	'ManageSettings' => array(
		'default_personalText' => 'default_personal_text',
		'smf3' => 'modSettings_desc',
		'smf34' => 'disable_polls',
		'smf32' => 'enable_polls',
		'smf33' => 'polls_as_topics',
		'smf235' => 'contiguous_page_display',
		'smf236' => 'to_display',
		'smf290' => 'today_disabled',
		'smf291' => 'today_only',
		'smf292' => 'yesterday_today',
		'smf293' => 'karma',
		'smf64' => 'karma_options',
	),
	'Packages' => array(
		'smf154' => 'package_proceed',
		'smf160' => 'php_script',
		'smf161' => 'package_run',
		'smf163' => 'package_read',
		'smf173' => 'script_output',
		'smf174' => 'additional_notes',
		'smf175' => 'notes_file',
		'smf180' => 'list_file',
		'smf181' => 'files_archive',
		'smf182' => 'package_get',
		'smf183' => 'package_servers',
		'smf184' => 'package_browse',
		'smf185' => 'add_server',
		'smf186' => 'server_name',
		'smf187' => 'serverurl',
		'smf189' => 'no_packages',
		'smf190' => 'download',
		'smf192' => 'download_success',
		'smf193' => 'package_downloaded_successfully',
		'smf198' => 'package_manager',
		'smf159b' => 'install_mod',
		'smf162b' => 'sql_file',
		'smf174b' => 'sql_queries',
		'smf189b' => 'no_mods_installed',
		'smf188b' => 'browse_installed',
		'smf198b' => 'uninstall',
		'smf198d' => 'delete_list',
		'smf198h' => 'php_safe_mode',
		'smf198i' => 'lets_try_anyway',
		'package3' => 'browse_packages',
		'package4' => 'create_package',
		'package5' => 'download_new_package',
		'package6' => 'view_and_remove',
		'package7' => 'modification_package',
		'package8' => 'avatar_package',
		'package9' => 'language_package',
		'package10' => 'unknown_package',
		'package11' => 'install_mod',
		'package12' => 'use_avatars',
		'package13' => 'add_languages',
		'package14' => 'list_files',
		'package15' => 'remove',
		'package24' => 'package_type',
		'package34' => 'archiving',
		'package37' => 'extracting',
		'package39' => 'avatars_extracted',
		'package41' => 'language_extracted',
		'pacman2' => 'mod_name',
		'pacman3' => 'mod_version',
		'pacman4' => 'mod_author',
		'pacman6' => 'author_website',
		'pacman8' => 'package_no_description',
		'pacman9' => 'package_description',
		'pacman10' => 'file_location',
		'package42' => 'install_actions',
		'package44' => 'perform_actions',
		'package45' => 'corrupt_compatible',
		'package50' => 'package_create',
		'package51' => 'package_move',
		'package52' => 'package_delete',
		'package53' => 'package_extract',
		'package54' => 'package_file',
		'package55' => 'package_tree',
		'package56' => 'execute_modification',
		'package57' => 'execute_code',
		'apply_mod' => 'install_mod',
		'mod_apply' => 'install_mod',
		'corrupt_compatable' => 'corrupt_compatible',
	),
	'PersonalMessage' => array(
		143 => 'pm_inbox',
		148 => 'send_message',
		150 => 'pm_to',
		1502 => 'pm_bcc',
		316 => 'inbox',
		321 => 'new_message',
		411 => 'delete_message',
		412 => 'delete_all',
		413 => 'delete_all_confirm',
		535 => 'recipient',
		561 => 'new_pm_subject',
		562 => 'pm_email',
		748 => 'pm_multiple',
		'smf249' => 'delete_selected_confirm',
		325 => 'ignorelist',
		326 => 'username_line',
		327 => 'email_notify',
	),
	'Post' => array(
		130 => 'regards_team',
		25 => 'post_reply',
		71 => 'message_icon',
		77 => 'subject_not_filled',
		78 => 'message_body_not_filled',
		252 => 'add_bbc',
		253 => 'bold',
		254 => 'italic',
		255 => 'underline',
		256 => 'center',
		257 => 'hyperlink',
		258 => 'insert_email',
		259 => 'bbc_code',
		260 => 'bbc_quote',
		261 => 'list',
		262 => 'black',
		263 => 'red',
		264 => 'yellow',
		265 => 'pink',
		266 => 'green',
		267 => 'orange',
		268 => 'purple',
		269 => 'blue',
		270 => 'beige',
		271 => 'brown',
		272 => 'teal',
		273 => 'navy',
		274 => 'maroon',
		275 => 'lime_green',
		276 => 'disable_smileys',
		277 => 'dont_use_smileys',
		280 => 'posted_on',
		281 => 'standard',
		282 => 'thumbs_up',
		283 => 'thumbs_down',
		284 => 'excamation_point',
		285 => 'question_mark',
		286 => 'lamp',
		297 => 'add_smileys',
		433 => 'flash',
		434 => 'ftp',
		435 => 'image',
		436 => 'table',
		437 => 'table_td',
		438 => 'topic_notify_no',
		439 => 'marquee',
		440 => 'teletype',
		441 => 'strike',
		442 => 'glow',
		443 => 'shadow',
		444 => 'preformatted',
		445 => 'left_align',
		446 => 'right_align',
		447 => 'superscript',
		448 => 'subscript',
		449 => 'table_tr',
		499 => 'post_too_long',
		531 => 'horizontal_rule',
		532 => 'font_size',
		533 => 'font_face',
		'smf13' => 'lock_after_post',
		'smf14' => 'notify_replies',
		'smf15' => 'lock_topic',
		'smf16' => 'shortcuts',
		'smf22' => 'option',
		'smf40' => 'reset_votes',
		'smf41' => 'reset_votes_check',
		'smf42' => 'votes',
		'smf119' => 'attach',
		'smf119b' => 'attached',
		'smf120' => 'allowed_types',
		'smf121' => 'max_size',
		'smf123' => 'cant_upload_type',
		'smf130' => 'uncheck_unwatchd_attach',
		'smf130b' => 'restricted_filename',
		'smf287' => 'topic_locked_no_reply',
		'notifyXAnn2' => 'new_announcement',
		'notifyXAnn3' => 'announce_unsubscribe',
		'notifyXOnce2' => 'more_but_no_reply',
		'rtm2' => 'enter_comment',
		'rtm3' => 'reported_post',
		'rtm4' => 'reported_to_mod_by',
		'rtm_email1' => 'report_following_post',
		'rtm_email2' => 'reported_by',
		'rtm_email3' => 'board_moderate',
		'rtm_email_comment' => 'report_comment',
		'sticky_after2' => 'sticky_after',
		'lock_after2' => 'lock_after',
		'poll_options1a' => 'poll_run',
		'poll_options1b' => 'poll_run_days',
		'poll_options2' => 'poll_results_anyone',
		'poll_options3' => 'poll_results_voted',
		'poll_options4' => 'poll_results_after',
		'poll_options5' => 'poll_max_votes',
		'poll_options7' => 'poll_do_change_vote',
		'poll_error1' => 'poll_too_many_votes',
	),
	'Profile' => array(
		80 => 'no_profile_edit',
		83 => 'website_title',
		84 => 'website_url',
		85 => 'signature',
		86 => 'profile_posts',
		88 => 'change_profile',
		89 => 'delete_user',
		113 => 'current_status',
		228 => 'personal_text',
		229 => 'personal_picture',
		232 => 'picture_text',
		329 => 'reset_form',
		349 => 'preferred_language',
		420 => 'age',
		422 => 'no_pic',
		458 => 'latest_posts',
		459 => 'additional_info',
		460 => 'show_latest',
		461 => 'posts_member',
		474 => 'avatar_by_url',
		475 => 'my_own_pic',
		479 => 'date_format',
		486 => 'time_format',
		518 => 'display_name_desc',
		519 => 'personal_time_offset',
		563 => 'dob',
		564 => 'dob_month',
		565 => 'dob_day',
		566 => 'dob_year',
		596 => 'password_strength',
		597 => 'additional_info',
		598 => 'include_website_url',
		599 => 'complete_url',
		600 => 'your_icq',
		601 => 'your_aim',
		602 => 'your_yim',
		606 => 'sig_info',
		664 => 'max_sig_characters',
		688 => 'send_member_pm',
		722 => 'hidden',
		741 => 'current_time',
		749 => 'digits_only',
		'smf225' => 'language',
		'smf227' => 'avatar_too_big',
		'smf233' => 'invalid_registration',
		'smf237' => 'msn_email_address',
		'smf241' => 'current_password',
		'smf244' => 'required_security_reasons',
		'pswd1' => 'secret_question',
		'pswd2' => 'secret_answer',
		'pswd3' => 'secret_ask',
		'pswd4' => 'cant_retrieve',
		'pswd5' => 'incorrect_answer',
		'pswd6' => 'enter_new_password',
		'pswd8' => 'password_success',
		'theme1a' => 'current_theme',
		'theme1b' => 'change',
		'theme2' => 'theme_preferences',
		'title1' => 'custom_title',
		'notifyX' => 'notify_settings',
		'notifyX1' => 'notify_save',
		'notifyXAnn4' => 'notify_important_email',
		394 => 'no_reminder_email',
		395 => 'send_email',
		396 => 'to_ask_password',
		'smf100' => 'user_email',
		'timeformat_easy0' => 'timeformat_default',
		'rtm8' => 'poster',
		732 => 'board_desc_inside',
	),
	'Reports' => array(
		'member_group_minPosts' => 'member_group_min_posts',
		'member_group_maxMessages' => 'member_group_max_messages',
	),
	'Search' => array(
		183 => 'set_perameters',
		189 => 'choose_board',
		343 => 'all_words',
		344 => 'any_words',
		583 => 'by_user',
		'set_perameters' => 'set_parameters',
	),
	'Stats' => array(
		888 => 'most_online',
		'smf_stats_1' => 'stats_center',
		'smf_stats_2' => 'general_stats',
		'smf_stats_3' => 'top_posters',
		'smf_stats_4' => 'top_boards',
		'smf_stats_5' => 'forum_history',
		'smf_stats_6' => 'stats_date',
		'smf_stats_7' => 'stats_new_topics',
		'smf_stats_8' => 'stats_new_posts',
		'smf_stats_9' => 'stats_new_members',
		'smf_stats_10' => 'page_views',
		'smf_stats_11' => 'top_topics_replies',
		'smf_stats_12' => 'top_topics_views',
		'smf_stats_13' => 'yearly_summary',
		'smf_stats_15' => 'top_starters',
		'smf_stats_16' => 'most_time_online',
		'smf_stats_17' => 'best_karma',
		'smf_stats_18' => 'worst_karma',
		'smf_news_1' => 'ssi_comment',
		'smf_news_2' => 'ssi_comments',
		'smf_news_3' => 'ssi_write_comment',
		'smf_news_error2' => 'ssi_no_guests',
	),
	'Themes' => array(
		'theme5' => 'theme_url_config',
		'theme6' => 'theme_options',
		'smf93' => 'disable_recent_posts',
		'smf94' => 'enable_single_post',
		'smf95' => 'enable_multiple_posts',
		'smf105' => 'enable_inline_links',
		'smf106' => 'inline_desc',
		382 => 'latest_members',
		383 => 'last_modification',
		384 => 'user_avatars',
		385 => 'user_text',
		386 => 'gender_images',
		387 => 'news_fader',
		510 => 'member_list_bar',
		522 => 'current_pos_text_img',
		523 => 'show_view_profile_button',
		618 => 'enable_mark_as_read',
	),
);

function applyTxtFixes()
{
	global $txtChanges, $txt, $helptxt;

	foreach ($txtChanges as $key => $file)
		foreach ($file as $old => $new)
		{
			if ($key == 'Help' && isset($helptxt[$old]))
				$helptxt[$new] = $helptxt[$old];
			elseif (isset($txt[$old]))
				$txt[$new] = $txt[$old];
			elseif (isset($txt[$new]) && !isset($txt[$old]))
				$txt[$old] = $txt[$new];
		}
}

// Fix the formatting of a legacy file
function fixLanguageFile($filename, $type, $lang, $test = false)
{
	global $txtChanges;

	if (!file_exists($filename))
		return -1;

	$edit_count = -1;

	// Load the file.
	$fileContents = implode('', file($filename));

	// The warning for editing files direct?
	if ($type != 'index' && $type != 'Install' && preg_match('~//\sVersion:[\s\d\w\.]*;\s*' . $type . '\s*//\s[\w\d\s!\.&;]*index\.' . $lang . '\.php\.~', $fileContents, $matches) == false)
	{
		$fileContents = preg_replace('~(//\sVersion:[\s\d\w\.]*;\s*' . $type . '\s*)~', "$" . '1// Important! Before editing these language files please read the text at the top of index.' . $lang . '.php.' . "\n\n", $fileContents);
		$edit_count = 0;
	}
	// Instructions on index?
	if ($type == 'index' && preg_match('~//\sVersion:[\s\d\w\.]*;\s*' . $type . '\s*/\*~', $fileContents, $matches) == false)
	{
		$long_warning = '/* Important note about language files in SMF 2.0 upwards:
1) All language entries in SMF 2.0 are cached. All edits should therefore be made through the admin menu. If you do
edit a language file manually you will not see the changes in SMF until the cache refreshes. To manually refresh
the cache go to Admin => Maintenance => Clean Cache.

2) Please also note that strings should use single quotes, not double quotes for enclosing the string
   except for line breaks.

*/';
		$fileContents = preg_replace('~(//\sVersion:[\s\d\w\.]*;\s*' . $type . '\s*)~', "$" . '1' . $long_warning . "\n\n", $fileContents);

		$edit_count = 0;
	}
	// More silly amounts of joins.
	if ($type != 'Install' && preg_match('~\' \. \'~', $fileContents, $matches))
	{
		$fileContents = preg_replace('~\' \. \'~', '', $fileContents);
		$edit_count = 0;
	}
	// Scripturl/Boardurl?
	if ($type != 'Install' && $type != 'Help' && preg_match('~\$(scripturl|boardurl)~', $fileContents, $match))
	{
		$fileContents = preg_replace('~\$(scripturl|boardurl)~', '#' . "$" . '1', $fileContents);
	}
	// Forumname/images/regards?
	if ($type != 'Install' && $type != 'Help' && preg_match('~\$(context|settings|txt)\[\'?(forum_name|forum_name_html_safe|images_url|130|regards_team)\'?\]~', $fileContents, $match))
	{
		$fileContents = preg_replace('~\$((context|settings|txt)\[\'?(forum_name|forum_name_html_safe|images_url|130|regards_team)\'?\])~', '#' . "$" . '1', $fileContents);
	}
	// Remove variables.
	if ($type != 'Install' && preg_match('~\' \. \$(\w*) \. \'~', $fileContents, $match))
	{
		$fileContents = preg_replace('~\' \. \$(\w*) \. \'~', '%s', $fileContents);
		$edit_count = 0;
	}
	// And any double arrays.
	if ($type != 'Install' && preg_match('~\' \. \$(\w*)\[\'?([\d\w]*)\'?\] \. \'~', $fileContents))
	{
		$fileContents = preg_replace('~\' \. \$(\w*)\[\'?([\d\w]*)\'?\] \. \'~', '%s', $fileContents);
		$edit_count = 0;
	}
	// Do the same for ones which are only half opened.
	if ($type != 'Install' && preg_match('~\$(\w*) \. \'~', $fileContents))
	{
		$fileContents = preg_replace('~\$(\w*) \. \'~', '\'%s', $fileContents);
		$edit_count = 0;
	}
	// And any double arrays.
	if ($type != 'Install' && preg_match('~\$(\w*)\[\'?([\d\w]*)\'?\] \. \'~', $fileContents))
	{
		$fileContents = preg_replace('~\$(\w*)\[\'?([\d\w]*)\'?\] \. \'~', '\'%s', $fileContents);
		$edit_count = 0;
	}
	// Put back in any variables.
	if ($type != 'Install' && $type != 'Help' && preg_match('~#(context|settings|txt|boardurl|scripturl)~', $fileContents, $match))
	{
		$fileContents = preg_replace('~#(context|settings|txt|boardurl|scripturl)~', "$$" . '1', $fileContents);
	}

	if (isset($txtChanges[$type]))
	{
		foreach ($txtChanges[$type] as $find => $replace)
		{
			$find2 = is_integer($find) ? '$txt[' . $find . ']' : '$txt[\'' . $find . '\']';

			if (strpos($fileContents, $find2) !== false)
			{
				$findArray[] = $find2;
				if (is_integer($replace))
					$replaceArray[] = '$txt[' . $replace . ']';
				else
					$replaceArray[] = '$txt[\'' . $replace . '\']';
			}
		}
	}

	if (!empty($findArray))
	{
		if ($edit_count == -1)
			$edit_count = 0;
		$edit_count += count($findArray);

		$fileContents = str_replace($findArray, $replaceArray, $fileContents);
	}

	// Need no edits at all?
	if ($edit_count == -1)
		return -1;

	// Making some changes?
	if (!$test)
	{
		$fp = fopen($filename, 'w');
		fwrite($fp, $fileContents);
		fclose($fp);
	}

	return $edit_count;
}

// Fix a legacy template.
function fixTemplateFile($filename, $test = false)
{
	global $txtChanges;

	if (!file_exists($filename))
		return -1;

	$edit_count = -1;

	// Load the file.
	$fileContents = implode('', file($filename));
	$findArray = array();
	$replaceArray = array();

	// Get all the buttons in the file.
	$buttons = array();
	preg_match_all('~create_button\([^,]+,\s*([^,)]+)(,\s*([^,)]+))?[,)]~i', $fileContents, $matches);
	if (!empty($matches[0]))
	{
		foreach ($matches[0] as $k => $match)
		{
			$buttons[] = array(
				'full' => $match,
				'replace' => $match,
				'lab1' => trim(strtr($matches[1][$k], array('"' => '', '\'' => ''))),
				'lab2' => trim(strtr($matches[3][$k], array('"' => '', '\'' => ''))),
			);
		}
	}

	// Any template_button_strip type things? (Look for 'text' =>)
	preg_match_all('~[\s\(]\'text\'\s=>\s(\'*[\da-zA-Z_]+\'*)[\),]~i', $fileContents, $matches);
	if (!empty($matches[0]))
	{
		foreach ($matches[0] as $k => $match)
		{
			$buttons[] = array(
				'full' => $match,
				'replace' => $match,
				'lab1' => trim(strtr($matches[1][$k], array('"' => '', '\'' => ''))),
			);
		}
	}

	foreach ($txtChanges as $type => $section)
	{
		foreach ($txtChanges[$type] as $find => $replace)
		{
			$find2 = is_integer($find) ? '$txt[' . $find . ']' : '$txt[\'' . $find . '\']';

			if (strpos($fileContents, $find2) !== false)
			{
				$findArray[] = $find2;
				if (is_integer($replace))
					$replaceArray[] = '$txt[' . $replace . ']';
				else
					$replaceArray[] = '$txt[\'' . $replace . '\']';
			}

			// Check for ones in quotes too.
			$find2 = '\'$txt[' . $find . ']\'';
			if (strpos($fileContents, $find2) !== false)
			{
				$findArray[] = $find2;
				$replaceArray[] = '\'$txt[' . $replace . ']\'';
			}

			// A quick create_button check!
			foreach ($buttons as $k => $button)
			{
				if (isset($button['lab1']) && $button['lab1'] == $find)
				{
					unset($buttons[$k]['lab1']);
					$buttons[$k]['replace'] = strtr($buttons[$k]['replace'], array($find => is_numeric($find) ? '\'' . $replace . '\'' : $replace));
				}

				if (isset($button['lab2']) && $button['lab2'] == $find)
				{
					unset($buttons[$k]['lab2']);
					$buttons[$k]['replace'] = strtr($buttons[$k]['replace'], array($find => is_numeric($find) ? '\'' . $replace . '\'' : $replace));
				}
			}
		}
	}

	// Some potential sprintf changes....
	$changes = array(
		'~([^\(])\$txt\[\'users_active\'\]~' => '$1sprintf($txt[\'users_active\'], $modSettings[\'lastActive\'])',
		'~([^\(])\$txt\[\'welcome_guest\'\]~' => '$1sprintf($txt[\'welcome_guest\'], $txt[\'guest_title\'])',
		'~([^\(])\$txt\[\'info_center_title\'\]~' => '$1sprintf($txt[\'info_center_title\'], $context[\'forum_name\'])',
		'~([^\(])\$txt\[\'login_with_forum\'\]~' => '$1sprintf($txt[\'login_with_forum\'], $context[\'forum_name\'])',
	);

	foreach ($buttons as $button)
	{
		if ($button['full'] != $button['replace'])
		{
			$changes['~' . preg_quote($button['full'], '~') . '~'] = $button['replace'];
			$edit_count++;
		}
	}

	$before = strlen($fileContents);
	$fileContents = preg_replace(array_keys($changes), array_values($changes), $fileContents);

	if (!empty($findArray) || strlen($fileContents) != $before)
	{
		if ($edit_count == -1)
			$edit_count = 0;

		if (!empty($findArray))
		{
			$edit_count += count($findArray);

			$fileContents = str_replace($findArray, $replaceArray, $fileContents);
		}
		else
			$edit_count = 1;
	}

	if ($edit_count == -1)
		return -1;

	// Making those changes?
	if (!$test)
	{
		$fp = fopen($filename, 'w');
		fwrite($fp, $fileContents);
		fclose($fp);
	}

	return $edit_count;
}

?>