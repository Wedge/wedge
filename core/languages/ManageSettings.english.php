<?php
// Version: 2.0; ManageSettings

$txt['settings_desc'] = 'This page allows you to change the settings of features and basic options in your forum. Click the help icons for more information about a setting.';

$txt['allow_guestAccess'] = 'Allow guests to browse the forum';
$txt['userLanguage'] = 'Enable user-selectable language support';
$txt['time_offset'] = 'Overall time offset';
$txt['time_offset_subtext'] = '(added to the member specific option.)';
$txt['default_timezone'] = 'Server timezone';
$txt['failed_login_threshold'] = 'Failed login threshold';
$txt['enable_quick_login'] = 'Show a quick login on every page';
$txt['age_restrictions'] = 'Minimum Age Restrictions';
$txt['trackStats'] = 'Track daily statistics';
$txt['hitStats'] = 'Track daily page views (must have stats enabled)';
$txt['enableCompressedOutput'] = 'Enable compressed output';
$txt['enableCompressedData'] = 'Enable compressed JS/CSS';
$txt['obfuscate_filenames'] = 'Obfuscate JS/CSS filenames';
$txt['minify'] = 'Minify JavaScript files with...';
$txt['minify_none'] = 'Don\'t minify|Useful for debugging.';
$txt['minify_jsmin'] = 'JSMin|The safest choice.';
$txt['minify_packer'] = 'Packer|Best compromise. Still, please view&lt;br&gt;the help popup before choosing this.';
$txt['minify_closure'] = 'Google Closure|Please view the help popup&lt;br&gt;before choosing this!';
$txt['jquery_origin'] = 'jQuery source location';
$txt['jquery_local'] = 'This server (merged with script.js)';
$txt['jquery_jquery'] = 'jQuery CDN';
$txt['jquery_google'] = 'Google CDN';
$txt['jquery_microsoft'] = 'Microsoft CDN';
$txt['disableTemplateEval'] = 'Disable evaluation of templates';
$txt['db_show_debug'] = 'Show debug information';
$txt['db_show_debug_who'] = 'Show debug information to which users:';
$txt['db_show_debug_who_log'] = 'Show debug query log to which users:';
$txt['db_show_debug_admin'] = 'Administrators only';
$txt['db_show_debug_admin_mod'] = 'Administrators and moderators';
$txt['db_show_debug_regular'] = 'All logged-in users';
$txt['db_show_debug_any'] = 'All users, including guests';
$txt['db_show_debug_none'] = 'No one';

$txt['databaseSession_enable'] = 'Use database driven sessions';
$txt['databaseSession_loose'] = 'Allow browsers to go back to cached pages';
$txt['databaseSession_lifetime'] = 'Seconds before an unused session timeout';
$txt['enableErrorLogging'] = 'Enable error logging';
$txt['enableErrorPasswordLogging'] = 'Log where people use the wrong password';
$txt['enableError404Logging'] = 'Log 404 (file not found) errors in the error log';
$txt['enableErrorQueryLogging'] = 'Include database query in the error log';
$txt['logPruning'] = 'Log Pruning';
$txt['log_enabled_moderate'] = 'Enable logging of moderation actions';
$txt['log_enabled_moderate_subtext'] = 'This includes deleting posts, moving topics and so on.';
$txt['log_enabled_admin'] = 'Enable logging of administrative actions';
$txt['log_enabled_admin_subtext'] = 'This includes things like creating new boards.';
$txt['log_enabled_profile'] = 'Enable logging of edits made to members\' profiles';
$txt['log_enabled_profile_subtext'] = 'This includes changes to display name and other fields.';
$txt['pruningOptions'] = 'Enable pruning of log entries';
$txt['pruneZeroDisable'] = '(0 to disable)';
$txt['pruneErrorLog'] = 'Remove error log entries older than';
$txt['pruneModLog'] = 'Remove moderation log entries older than';
$txt['pruneReportLog'] = 'Remove report to moderator log entries older than';
$txt['pruneScheduledTaskLog'] = 'Remove scheduled task log entries older than';
$txt['pruneSpiderHitLog'] = 'Remove search engine hit logs older than';
$txt['cookieTime'] = 'Default login cookies length (in minutes)';
$txt['localCookies'] = 'Enable local storage of cookies';
$txt['localCookies_subtext'] = '(SSI won\'t work well with this on.)';
$txt['globalCookies'] = 'Use subdomain independent cookies';
$txt['globalCookies_subtext'] = '(turn off local cookies first!)';
$txt['secureCookies'] = 'Force cookies to be secure';
$txt['secureCookies_subtext'] = '(This only applies if you are using HTTPS - don\'t use otherwise!)';
$txt['send_validation_onChange'] = 'Require reactivation after email change';
$txt['approveAccountDeletion'] = 'Require admin approval when member deletes account';
$txt['autoOptMaxOnline'] = 'Maximum users online when optimizing';
$txt['autoOptMaxOnline_subtext'] = '(0 for no max.)';
$txt['autoFixDatabase'] = 'Automatically fix broken tables';
$txt['allow_disableAnnounce'] = 'Allow users to disable announcements';
$txt['disallow_sendBody'] = 'Don\'t allow post text in notifications';
$txt['max_pm_recipients'] = 'Maximum number of recipients allowed in a personal message';
$txt['max_pm_recipients_subtext'] = '(0 for no limit, admins are exempt)';
$txt['pm_posts_verification'] = 'Post count under which users must pass verification when sending personal messages';
$txt['pm_posts_verification_subtext'] = '(0 for no limit, admins are exempt)';
$txt['pm_posts_per_hour'] = 'Number of personal messages a user may send in an hour';
$txt['pm_posts_per_hour_subtext'] = '(0 for no limit, moderators are exempt)';
$txt['home_url'] = 'Site address';
$txt['home_url_subtext'] = 'If your forum is part of a larger site, put the address of that here, and the Home tab on the menu will point to it, as opposed to the forum front page.';
$txt['home_link'] = 'Link the title to the home page';
$txt['home_link_subtext'] = 'The forum title at the top of the page is also a link back to the forum front page. Ticking this box will make it link back to the overall site address you provided above.';
$txt['site_slogan'] = 'Site slogan';
$txt['site_slogan_desc'] = 'Add your own text for a slogan here. Leave empty to show an empty #logo, which you can customize via CSS.';
$txt['header_logo_url'] = 'Logo image URL';
$txt['header_logo_url_desc'] = 'Leave blank to show forum name or default logo.';
$txt['todayMod'] = 'Enable shorthand date display';
$txt['today_disabled'] = 'Disabled';
$txt['today_only'] = 'Only Today';
$txt['yesterday_today'] = 'Today &amp; Yesterday';
$txt['timeLoadPageEnable'] = 'Display time taken to create every page';
$txt['disableHostnameLookup'] = 'Disable hostname lookups';
$txt['who_enabled'] = 'Enable who\'s online list';
$txt['display_who_viewing'] = 'Show current viewers on board index and topics';
$txt['who_display_viewing_off'] = 'Don\'t show';
$txt['who_display_viewing_numbers'] = 'Show only numbers';
$txt['who_display_viewing_names'] = 'Show member names';
$txt['show_stats_index'] = 'Show statistics on board index';
$txt['show_latest_member'] = 'Show latest member on board index';
$txt['show_avatars'] = 'Show user avatars in posts';
$txt['show_signatures'] = 'Show user signatures in posts';
$txt['show_blurb'] = 'Show personal texts in posts';
$txt['show_gender'] = 'Show gender indicator in posts';
$txt['show_board_desc'] = 'Show board descriptions inside boards.';
$txt['show_children'] = 'Show sub-boards on every page inside boards, not just the first.';

$txt['pm_enabled'] = 'Enable personal messages between members';
$txt['pm_read'] = 'Groups allowed to read their messages';
$txt['pm_send'] = 'Groups allowed to send messages';
$txt['save_pm_draft'] = 'Groups allowed to save drafts';
$txt['auto_save_pm_draft'] = 'Groups whose drafts will automatically save';
$txt['pm_draft_other_settings'] = 'You can also set how often drafts are saved and whether old drafts are removed from the <a href="<URL>?action=admin;area=postsettings;sa=drafts">Draft Settings</a> page.';

$txt['likes_enabled'] = 'Enable likes system';
$txt['likes_own_posts'] = 'Users can like their own posts';

$txt['boardurl'] = 'Forum URL';

$txt['caching_information'] = '<div class="center" style="font-weight: bold; text-decoration: underline">Important! Read this first before enabling these features.</div><br>
	Wedge supports caching through the use of accelerators. The currently supported accelerators include:<br>
	<ul class="list">
		<li>APC</li>
		<li>Memcached</li>
		<li>Zend Platform/Performance Suite (not Zend Optimizer)</li>
		<li>XCache</li>
	</ul>
	Caching will work best if you have PHP compiled with one of the above optimizers, or have a Memcached server available (along with the associated PHP extension.)
	If you do not have any optimizer installed, Wedge will do file-based caching.<br><br>
	Wedge performs caching at a variety of levels. The higher the level of caching enabled, the more CPU time will be spent
	retrieving cached information. If caching is available on your machine, it is recommended that you try caching at level 1 first.
	<br><br>
	Note that if you use Memcached, you need to provide the server details in the setting below. Wedge will perform random load balancing across the servers.
	They should be entered as a comma-separated list as shown in the example below:<br>
	&quot;localhost,server2,server3:port,127.0.0.1&quot;<br><br>
	If you do not specify a port, the default port (11211) will be used.
	<br><br>
	%1$s';

$txt['detected_no_caching'] = '<strong class="alert">Wedge was unable to detect a compatible accelerator on your server.</strong>';
$txt['detected_APC'] = '<strong style="color: green">Wedge has detected that your PHP server has APC installed.</strong>';
$txt['detected_Zend'] = '<strong style="color: green">Wedge has detected that your PHP server has Zend installed.</strong>';
$txt['detected_Memcached'] = '<strong style="color: green">Wedge has detected that your PHP server has Memcached installed.</strong>';
$txt['detected_XCache'] = '<strong style="color: green">Wedge has detected that your PHP server has XCache installed.</strong>';

$txt['cache_enable'] = 'Caching Level';
$txt['cache_off'] = 'No caching';
$txt['cache_level1'] = 'Level 1 Caching (Recommended)';
$txt['cache_level2'] = 'Level 2 Caching';
$txt['cache_level3'] = 'Level 3 Caching (Not Recommended)';

$txt['cache_type'] = 'Caching Type';
$txt['cache_type_file'] = 'File-based caching';
$txt['cache_memcached'] = 'Memcached server details<dfn>Leave empty to disable Memcached</dfn>';

$txt['loadavg_warning'] = '<span class="error">Please note: the settings below are to be edited with care. Setting any of them too low may render your forum <strong>unusable</strong>! The current load average is <strong>%01.2f</strong></span>';
$txt['loadavg_enable'] = 'Enable load balancing by load averages';
$txt['loadavg_auto_opt'] = 'Threshold to disabling automatic database optimization';
$txt['loadavg_search'] = 'Threshold to disabling search';
$txt['loadavg_allunread'] = 'Threshold to disabling all unread topics';
$txt['loadavg_unreadreplies'] = 'Threshold to disabling unread replies';
$txt['loadavg_show_posts'] = 'Threshold to disabling showing user posts';
$txt['loadavg_forum'] = 'Threshold to disabling the forum <strong>completely</strong>';
$txt['loadavg_disabled_conf'] = '<span class="error">Load balancing support is disabled by your host configuration.</span>';

$txt['news_settings_submit'] = 'Save';
$txt['xmlnews_enable'] = 'Enable Atom feeds';
$txt['xmlnews_maxlen'] = 'Maximum message length:';
$txt['xmlnews_maxlen_subtext'] = '(0 to disable, bad idea.)';
$txt['xmlnews_sidebar'] = 'Show the "Latest Posts Feed" block in the sidebar';
$txt['enable_news'] = 'Show a random news line on all pages';
$txt['show_newsfader'] = 'Show news fader on board index';
$txt['newsfader_time'] = 'Fading delay between items for the news fader';

$txt['reverse_proxy'] = 'Enable reverse proxy support';
$txt['reverse_proxy_header'] = 'Proxy HTTP header with IP address';
$txt['reverse_proxy_ips'] = 'IP or CIDR block addresses of proxy servers';
$txt['reverse_proxy_one_per_line'] = 'Specify one address per line';

$txt['login_type'] = 'What is allowed for a user to log in with?';
$txt['login_username_or_email'] = 'Either their username or their email address';
$txt['login_username_only'] = 'Their username only';
$txt['login_email_only'] = 'Their email address only';

$txt['setting_password_strength'] = 'Required strength for user passwords';
$txt['setting_password_strength_low'] = 'Low - 4 character minimum';
$txt['setting_password_strength_medium'] = 'Medium - cannot contain username';
$txt['setting_password_strength_high'] = 'High - mixture of different characters';

$txt['antispam_settings'] = 'Anti-Spam Verification';
$txt['antispam_settings_desc'] = 'This section allows you to setup verification checks to ensure the user is a human (and not a bot), and tweak how and where these apply.';
$txt['setting_reg_verification'] = 'Require verification on registration page';
$txt['posts_require_captcha'] = 'Post count under which users must pass verification to make a post';
$txt['posts_require_captcha_desc'] = '(0 for no limit, moderators are exempt)';
$txt['search_enable_captcha'] = 'Require verification on all guest searches';
$txt['setting_guests_require_captcha'] = 'Guests must pass verification when making a post';
$txt['setting_guests_require_captcha_desc'] = '(Automatically set if you specify a minimum post count below)';
$txt['guests_report_require_captcha'] = 'Guests must pass verification when reporting a post';

$txt['configure_captcha'] = 'Configure CAPTCHA images';
$txt['configure_captcha_desc'] = '<span class="smalltext">A CAPTCHA is a form of anti-bot protection to help guard against automated robots posting nonsense on your forum.</span>';
$txt['use_captcha_images'] = 'Enable using CAPTCHA images?';
$txt['use_animated_captcha'] = 'Use the animated CAPTCHA images?';
$txt['use_animated_captcha_desc'] = 'Animated CAPTCHAs are particularly tricky for bots to beat, but they may be harder for normal users to solve, too.';

$txt['setting_qa_verification_number'] = 'Number of verification questions user must answer';
$txt['setting_qa_verification_number_desc'] = '(0 to disable; questions are set below)';
$txt['setup_verification_questions'] = 'Verification Questions';
$txt['setup_verification_questions_desc'] = '<span class="smalltext">If you want users to answer verification questions in order to stop spam bots you should setup a number of questions in the table below. You should pick relatively simple questions; answers are not case sensitive. You may use BBC in the questions for formatting, to remove a question simply delete the contents of that line.</span>';
$txt['setup_verification_question'] = 'Question';
$txt['setup_verification_answer'] = 'Answer';
$txt['setup_verification_add'] = 'Add a question';
$txt['setup_verification_add_answer'] = 'Add answer';

$txt['modfilter_norules'] = 'There are no filter rules set up.';
$txt['modfilter_addrule'] = 'Add a filter rule';
$txt['modfilter_editrule'] = 'Edit filter rule';
$txt['modfilter_rule_posts'] = 'When saving any post:';
$txt['modfilter_rule_topics'] = 'When starting a new topic:';
$txt['modfilter_action_prevent'] = 'Prevent the post being saved';
$txt['modfilter_action_moderate'] = 'Moderator must approve post (before being public)';
$txt['modfilter_action_pin'] = 'Pin the current topic';
$txt['modfilter_action_unpin'] = 'Unpin the current topic';
$txt['modfilter_action_lock'] = 'Lock the topic';
$txt['modfilter_action_unlock'] = 'Unlock the topic';
$txt['modfilter_conditions'] = 'If these rules are met:';
$txt['modfilter_cond_boards_in'] = 'Posting in these boards:';
$txt['modfilter_cond_boards_ex'] = 'Posting anywhere except in:';
$txt['modfilter_cond_groups_in'] = 'Member of any of these groups:';
$txt['modfilter_cond_groups_ex'] = 'Not a member of any of these groups:';
$txt['modfilter_cond_permissions_in'] = 'Has any of these permissions:';
$txt['modfilter_cond_permissions_ex'] = 'Has none of these permissions:';
$txt['modfilter_cond_userid_in'] = 'User is one of the following:';
$txt['modfilter_cond_userid_ex'] = 'User is not one of the following:';
$txt['modfilter_cond_subject_begins'] = 'Post subject begins with:';
$txt['modfilter_cond_subject_ends'] = 'Post subject ends with:';
$txt['modfilter_cond_subject_contains'] = 'Post subject contains:';
$txt['modfilter_cond_subject_matches'] = 'Post subject matches:';
$txt['modfilter_cond_subject_regex'] = 'Post subject matches regular expression:';
$txt['modfilter_cond_body_begins'] = 'Post body begins with:';
$txt['modfilter_cond_body_ends'] = 'Post body ends with:';
$txt['modfilter_cond_body_contains'] = 'Post body contains:';
$txt['modfilter_cond_body_matches'] = 'Post body matches:';
$txt['modfilter_cond_body_regex'] = 'Post body matches regular expression:';
$txt['modfilter_case_sensitive'] = '(exact match)';
$txt['modfilter_case_insensitive'] = '(upper/lower case doesn\'t matter)';
$txt['modfilter_cond_postcount'] = 'Post count:';
$txt['modfilter_cond_warning'] = 'Infraction points:';
$txt['modfilter_cond_links'] = 'Number of links in post:';
$txt['modfilter_range_lt'] = 'less than';
$txt['modfilter_range_lte'] = 'less than or equal to';
$txt['modfilter_range_eq'] = 'equal to';
$txt['modfilter_range_gt'] = 'greater than';
$txt['modfilter_range_gte'] = 'greater than or equal to';
$txt['modfilter_cond_unknownrule'] = 'Unknown rule type:';
$txt['modfilter_approve_title'] = 'Approve Outstanding Items';
$txt['modfilter_approve_desc'] = 'If there are outstanding items to be approved, or you are thinking of disabling moderation filters, you may wish to make sure all pending items get approved so that items are not just simply lost. You may wish to review moderated items before using this option, however, to make sure that nothing gets approved that should not be.';
$txt['modfilter_all_approved'] = 'All outstanding moderation items have been approved.';
$txt['modfilter_applies_legend'] = 'When this rule should be applied';
$txt['modfilter_applies_desc'] = 'Sometimes a filter rule might need to be applied to all posts, sometimes just to new topics.';
$txt['modfilter_applies_rule'] = 'When should this particular rule be applied?';
$txt['modfilter_applies_posts'] = 'when any new post is made';
$txt['modfilter_applies_topics'] = 'when a new topic is started';
$txt['modfilter_action_legend'] = 'What the rule will do';
$txt['modfilter_action_desc'] = 'Each rule has a single action that it performs when all of its conditions are met, with "preventing the post" and "moderating the post" excluding all other possible actions.';
$txt['modfilter_action_selectone'] = '--- select one ---';
$txt['modfilter_action_rule'] = 'What should happen if the conditions are met?';
$txt['modfilter_actionlist_prevent'] = 'Prevent the post from being made';
$txt['modfilter_actionlist_moderate'] = 'Moderate the post';
$txt['modfilter_actionlist_pin'] = 'Pin the topic to the top of its board';
$txt['modfilter_actionlist_unpin'] = 'Unpin the topic from the top of its board';
$txt['modfilter_actionlist_lock'] = 'Lock the topic from further posts';
$txt['modfilter_actionlist_unlock'] = 'Unlock the topic to allow further posts';
$txt['modfilter_conds_legend'] = 'Conditions for this rule';
$txt['modfilter_conds_desc'] = 'Any filter rule requires conditions, to know whether the rule should be applied. Here you add the conditions for this rule - all conditions must be met in order for the rule to be activated.';
$txt['modfilter_conds_item'] = 'Item';
$txt['modfilter_conds_criteria'] = 'Criteria';
$txt['modfilter_conds_no_conditions'] = 'There are no conditions set. Add one!';
$txt['modfilter_conds_new'] = 'Type of condition:';
$txt['modfilter_conds_add'] = 'Add new condition';
$txt['modfilter_conds_select'] = '--- select a condition type ---';
$txt['modfilter_condtype_boards'] = 'Applies to one or more boards';
$txt['modfilter_condtype_groups'] = 'Applies to one or more membergroups';
$txt['modfilter_condtype_userid'] = 'Applies to one or more users';
$txt['modfilter_condtype_postcount'] = 'Applies based on user postcount';
$txt['modfilter_condtype_warning'] = 'Applies based on user infractions';
$txt['modfilter_condtype_permission'] = 'Applies based on user permissions';
$txt['modfilter_condtype_subject'] = 'Depends on the post subject';
$txt['modfilter_condtype_body'] = 'Depends on the post contents';
$txt['modfilter_condtype_links'] = 'Depends on how many links are in the post';
$txt['modfilter_applies_all'] = 'Applies to the selected items:';
$txt['modfilter_applies_allexcept'] = 'Applies to <strong>all except</strong> the selected items:';
$txt['modfilter_condition_done'] = 'Add this condition';
$txt['modfilter_postcount_is'] = 'The user\'s post-count is:';
$txt['modfilter_warning_is'] = 'The user has:';
$txt['modfilter_warning_is_post'] = 'infraction points';
$txt['modfilter_links_is'] = 'The number of links in the post is:';
$txt['modfilter_the_post_subject'] = 'The post\'s subject';
$txt['modfilter_the_post_body'] = 'The post\'s body';
$txt['modfilter_regex_begins'] = 'begins with';
$txt['modfilter_regex_contains'] = 'contains';
$txt['modfilter_regex_ends'] = 'ends with';
$txt['modfilter_regex_matches'] = 'matches';
$txt['modfilter_regex_regex'] = 'matches regular expression';
$txt['modfilter_be_case_sensitive'] = 'Be case sensitive (treat UPPER and lower case differently)';
$txt['modfilter_save_this_rule'] = 'Save this rule';
$txt['modfilter_remove_this_rule'] = 'Remove this rule';
$txt['modfilter_error_saving'] = 'This rule could not be saved, there was something wrong in sending the data. Please go back and try again.';
$txt['modfilter_rule_not_found'] = 'The rule you are trying to edit does not exist.';
$txt['modfilter_msg'] = 'and shows a custom message';
$txt['modfilter_msg_popup_title'] = 'Informing the user...';
$txt['modfilter_msg_no_lang'] = 'No custom messages were set up for this rule.';
$txt['modfilter_msg_popup'] = 'When this rule is activated, the user will be shown the following message. (Where possible, the forum will try to use the language selected by the user.)';
$txt['modfilter_lang_msg'] = 'While it will be clear to the user that their post has had work done to it, it will not always be clear why that is the case. Here you can add a message for your users to explain why action has been taken. You do not have to fill one in, or indeed fill in any specific language - it will just try to use the most appropriate for the user out of the ones you have provided. You can also use HTML, for example to link to a rules post that you have.';

$txt['allow_editDisplayName'] = 'Allow members to edit their displayed name';
$txt['allow_hideOnline'] = 'Allow members to hide their online status';
$txt['titlesEnable'] = 'Enable custom titles';
$txt['enable_buddylist'] = 'Enable contacts &amp; ignore list';

$txt['signature_settings'] = 'Signature Settings';
$txt['signature_settings_desc'] = 'Use the settings on this page to decide what facilities members should have for their signatures.';
$txt['signature_settings_warning'] = 'Note that settings are not applied to existing signatures by default. Click <a href="%1$s?action=admin;area=memberoptions;sa=sig;apply;%2$s">here</a> to apply rules to all existing signatures.';
$txt['signature_enable'] = 'Enable signatures';
$txt['signature_minposts'] = 'Minimum number of posts for signatures to be visible';
$txt['signature_zero_no_max'] = '(0 for no max.)';
$txt['signature_max_length'] = 'Maximum allowed characters';
$txt['signature_max_lines'] = 'Maximum amount of lines';
$txt['signature_max_images'] = 'Maximum image count';
$txt['signature_max_images_subtext'] = '(0 for no max - excludes smileys)';
$txt['signature_allow_smileys'] = 'Allow smileys in signatures';
$txt['signature_max_smileys'] = 'Maximum smiley count';
$txt['signature_max_image_width'] = 'Maximum width of signature images (pixels)';
$txt['signature_max_image_height'] = 'Maximum height of signature images (pixels)';
$txt['signature_max_font_size'] = 'Maximum font size allowed in signatures';
$txt['signature_max_font_size_subtext'] = '(0 for no max, in pixels)';
$txt['signature_bbc'] = 'Enabled BBC tags';

$txt['custom_profile_title'] = 'Custom Profile Fields';
$txt['custom_profile_desc'] = 'From this page you can create your own custom profile fields that fit in with your own forum\'s requirements.';
$txt['custom_profile_active'] = 'Active';
$txt['custom_profile_inactive'] = 'Inactive';
$txt['custom_profile_make_new'] = 'New Field';
$txt['custom_profile_none'] = 'You have not created any custom profile fields yet!';
$txt['custom_profile_icon'] = 'Icon';

$txt['custom_profile_type_text'] = 'Text';
$txt['custom_profile_type_textarea'] = 'Large Text';
$txt['custom_profile_type_select'] = 'Select Box';
$txt['custom_profile_type_radio'] = 'Radio Buttons';
$txt['custom_profile_type_check'] = 'Checkbox';

$txt['custom_add_title'] = 'Add Profile Field';
$txt['custom_edit_title'] = 'Edit Profile Field';
$txt['custom_edit_general'] = 'Display Settings';
$txt['custom_edit_input'] = 'Input Settings';
$txt['custom_edit_advanced'] = 'Advanced Settings';
$txt['custom_edit_name'] = 'Name';
$txt['custom_edit_desc'] = 'Description';
$txt['custom_edit_profile'] = 'Profile Section';
$txt['custom_edit_profile_desc'] = 'Section of profile this is edited in.';
$txt['custom_edit_profile_none'] = 'None';
$txt['custom_edit_registration'] = 'Show on Registration';
$txt['custom_edit_registration_disable'] = 'No';
$txt['custom_edit_registration_allow'] = 'Yes';
$txt['custom_edit_registration_require'] = 'Yes, and require entry';
$txt['custom_edit_mlist'] = 'Show on Memberlist';
$txt['custom_edit_display'] = 'Show on Topic View';
$txt['custom_edit_picktype'] = 'Field Type';

$txt['whos_online_desc'] = 'From this page, you can manage how users can see who is online and who is not.';
$txt['lastActive'] = 'User online time threshold';
$txt['who_view'] = 'Membergroups who can see "Who\'s Online"';
$txt['member_options_desc'] = 'From this page, you can set certain options that are also configurable by your users; you can set what the normal option is and reset everyone\'s options if needed.';
$txt['member_options_default'] = 'Default value:';
$txt['member_options_change'] = 'Change';
$txt['no_change'] = '%s (current)';
$txt['leave_alone'] = 'Keep members\' own choices';
$txt['member_options_guest'] = 'For guests/new members:';
$txt['member_options_members'] = 'For existing members:';
$txt['member_options_override'] = 'Set to "%s" for everyone';

// Strings for the templates
$txt['your_icq'] = 'This is your ICQ number.';
$txt['your_aim'] = 'This is your AOL Instant Messenger nickname.';
$txt['your_yim'] = 'This is your Yahoo! Instant Messenger nickname.';
// In this string, please use +'s for spaces.
$txt['aim_default_message'] = 'Hello!+Are+you+there?';
$txt['your_twitter'] = 'This is your Twitter username, without the @ at the front';
$txt['your_facebook'] = 'This is your Facebook account number, or vanity name (e.g. "wedgebook" in "facebook.com/wedgebook")';
$txt['your_skype'] = 'This is your Skype username';
$txt['your_steam'] = 'This is your id from the Steam Community';
$txt['custom_edit_tplgrp_social'] = 'Social Networking';
$txt['custom_edit_tplgrp_im'] = 'Instant Messaging';
$txt['custom_edit_tplgrp_gaming'] = 'Gaming';
$txt['custom_edit_templates'] = 'Template Fields';
$txt['custom_edit_templates_desc'] = 'You may also use as templates the following information fields for some common social networking services.';
$txt['custom_edit_a_template'] = 'Template to use';
$txt['custom_edit_templates_select'] = '-- Select a template --';

$txt['custom_edit_max_length'] = 'Maximum Length';
$txt['custom_edit_max_length_desc'] = '(0 for no limit)';
$txt['custom_edit_dimension'] = 'Dimensions';
$txt['custom_edit_dimension_row'] = 'Rows';
$txt['custom_edit_dimension_col'] = 'Columns';
$txt['custom_edit_bbc'] = 'Allow BBC';
$txt['custom_edit_options'] = 'Options';
$txt['custom_edit_options_desc'] = 'Leave option box blank to remove. Radio button selects default option.';
$txt['custom_edit_options_more'] = 'More';
$txt['custom_edit_default'] = 'Default State';
$txt['custom_edit_active'] = 'Active';
$txt['custom_edit_active_desc'] = 'If not selected this field will not be shown to anyone.';
$txt['custom_edit_privacy'] = 'Privacy';
$txt['custom_edit_privacy_desc'] = 'Who can see and edit this field.';
$txt['custom_edit_see_owner'] = 'The owner (subject to the following groups)';
$txt['custom_edit_can_search'] = 'Searchable';
$txt['custom_edit_can_search_desc'] = 'Can this field be searched from the members list.';
$txt['custom_edit_mask'] = 'Input Mask';
$txt['custom_edit_mask_desc'] = 'For text fields an input mask can be selected to validate the data.';
$txt['custom_edit_mask_email'] = 'Valid Email';
$txt['custom_edit_mask_number'] = 'Numeric';
$txt['custom_edit_mask_nohtml'] = 'No HTML';
$txt['custom_edit_mask_regex'] = 'Regex (Advanced)';
$txt['custom_edit_enclose'] = 'Show Enclosed Within Text (Optional)';
$txt['custom_edit_enclose_desc'] = 'We <strong>strongly</strong> recommend to use an input mask to validate the input supplied by the user.';
$txt['custom_edit_can_see'] = 'Can see';
$txt['custom_edit_can_edit'] = 'Can edit';

$txt['custom_edit_placement'] = 'Choose Placement';
$txt['custom_edit_placement_standard'] = 'Standard (with title)';
$txt['custom_edit_placement_withicons'] = 'With Icons';
$txt['custom_edit_placement_abovesignature'] = 'Above Signature';
$txt['custom_profile_placement'] = 'Placement: %1$s';

$txt['custom_edit_delete_sure'] = 'Are you sure you wish to delete this field - all related user data will be lost!';

$txt['standard_profile_title'] = 'Standard Profile Fields';
$txt['standard_profile_field'] = 'Field';

$txt['languages_area_edit_desc'] = 'This area allows you to browse and edit the language data used by Wedge. It is not designed for translations, and if you wish to translate Wedge into a new language, please contact the Wedge Team instead.';
$txt['languages_lang_name'] = 'Language Name (click to edit the language)';
$txt['languages_locale'] = 'Locale';
$txt['languages_available'] = 'Available';
$txt['languages_default'] = 'Default';
$txt['languages_users'] = 'Users';
$txt['edit_languages'] = 'Edit Languages';
$txt['edit_languages_specific'] = 'Edit Languages - %1$s';
$txt['language_edit_master_value'] = '<em>Master value:</em> %1$s';
$txt['language_edit_master_value_array'] = '<em>Master values:</em>';
$txt['language_edit_current_value'] = '<em>Current value:</em> %1$s';
$txt['language_edit_current_value_array'] = '<em>Current values:</em>';
$txt['language_edit_new_value'] = '<em>New value:</em>';
$txt['language_edit_new_value_array'] = '<em>New values:</em>';
$txt['language_edit_add_entry'] = 'Add an entry';
$txt['language_delete_value'] = 'Delete this item';
$txt['language_revert_value'] = 'Reset to master value';
$txt['language_clear_cache'] = 'Empty Language Cache';
$txt['language_clear_cache_desc'] = 'The data used to make language items appear in the forum are managed between special files and the database, so that you can modify them without having to modify files. Generally you should use the language editor facilities from the administration area to change language items, but sometimes you may need to force the system to rebuild its cache. This allows you to do so.';
$txt['language_clear_cache_btn'] = 'Clear the cache';
$txt['language_cache_cleared'] = 'The language cache has been cleared.';
$txt['language_no_entries'] = 'This particular language file has no entries, it is likely a placeholder and you really want the <a href="%1$s">parent language file</a>.';
$txt['language_search_results'] = 'Search Results - %1$s';
$txt['language_no_result_results'] = 'Unfortunately, no results were found.';
$txt['language_search_default'] = 'Matches within the standard files';
$txt['language_search_plugins'] = 'Matches within plugins';

$txt['language_edit_main'] = 'Main Files';
$txt['language_edit_admin'] = 'Admin Panel';
$txt['language_edit_default'] = 'Default Language Files';
$txt['language_edit_other'] = 'Other Language Files';
$txt['language_edit_search'] = 'Search Language Files';
$txt['language_edit_search_plugins'] = 'Include plugins?';
$txt['language_edit_search_keys'] = 'Search keys';
$txt['language_edit_search_values'] = 'Search values';
$txt['language_edit_search_both'] = 'Search both';
$txt['language_edit_no_plugins'] = 'Plugins';
$txt['language_edit_no_plugins_desc'] = 'There are no editable language files for any plugins.';
$txt['language_edit_plugins_title'] = 'Plugin: %1$s';
$txt['language_edit_elsewhere'] = 'Configured elsewhere';
$txt['language_edit_email_templates'] = 'Email templates';
$txt['language_edit_reg_agreement'] = 'Registration agreement';

$txt['edit_language_entries'] = 'Edit Language Entries';
$txt['languages_dictionary'] = 'Dictionary';
$txt['languages_orientation'] = 'Orientation';
$txt['languages_orients_ltr'] = 'Left-to-Right';
$txt['languages_orients_rtl'] = 'Right-to-Left';

$txt['lang_file_desc_index'] = 'Main language file, all generic strings';
$txt['lang_file_desc_Modlog'] = 'Moderation log entries';

// Homepage
$txt['homepage_desc'] = 'This page allows you to determine what to show your users when they visit your forum\'s root.';

$txt['homepage_type'] = 'What should the home page show?';
$txt['homepage_boardlist'] = 'Board list';
$txt['homepage_board'] = 'Specific board';
$txt['homepage_action'] = 'Specific action';
$txt['homepage_custom'] = 'Custom contents';
$txt['homepage_blurb_title'] = 'Blurb title (%s)';
$txt['homepage_blurb'] = 'Blurb text (%s)';
// !! Don't translate the bits between <strong> tags!
$txt['homepage_message'] = 'Choose "Board list" to show the full list of available boards; "Specific board" to direct users to a specific board in the list (make sure to
	choose which board, of course!); "Specific action" to direct them to a custom page on your forum (for instance, create files /app/Mine.php and /html/Mine.template.php,
	then enter "Mine" in the specific action box); and finally, "Custom contents" will allow you to show multiple elements at the same time. Enter one element per line
	(they\'ll be shown in order of appearance), from the following list:<br>

		<br><strong>blurb</strong>: a custom text; an introduction, maybe.
		<br><strong>topics</strong>: a list of the latest updated topics. Add \':x\' to start with x topics instead of 5, e.g. \'topics:10\'.
		<br><strong>thoughts</strong>: a list of the latest thoughts. Add \':x\' to show x thoughts instead of 10, e.g. \'thoughts:5\'.
		<br><strong>boards</strong>: the full list of boards.
		<br><strong>info</strong>: the information center (statistics). Desktop browsers show it in the sidebar.
	';
