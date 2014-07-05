<?php
// Version: 2.0; Index

// Locale (strftime, pspell_new) and spelling. (pspell_new, can be left as '' normally.)
// For more information see:
//		http://www.php.net/function.pspell-new
//		http://www.php.net/function.setlocale
// Again, SPELLING SHOULD BE '' 99% OF THE TIME!! Please read this!
$txt['lang_name'] = 'English (US)';
$txt['lang_locale'] = 'en_US';
$txt['lang_paypal'] = 'US';
$txt['lang_dictionary'] = 'en';
$txt['lang_spelling'] = 'american';

// Character set and right to left?
$txt['lang_rtl'] = false;

// Number formats?
$txt['number_format'] = '1,234.00';
$txt['time_format'] = '%B %@, %Y, %I:%M %p';
$txt['time_format_this_year'] = '%B %@, %I:%M %p';

// %@ is a special format that adds a suffix to a day (1-31), e.g. 1st, 2nd...
// If your language doesn't have any prefixes/suffixes it could use, just set it to $txt['day_suffix'] = '%s';
$txt['day_suffix'] = array(
	'n' => '%sth',
	1 => '1st',
	2 => '2nd',
	3 => '3rd',
	21 => '21st',
	22 => '22nd',
	23 => '23rd',
	31 => '31st',
);

$txt['just_now'] = 'just now';
$txt['seconds_ago'] = '{time} seconds ago';
$txt['minutes_ago'] = '{time} minutes ago';
$txt['hours_ago'] = '{time} hours ago';
$txt['days_ago'] = '{time} days ago';
$txt['months_ago'] = '{time} months ago';
$txt['years_ago'] = '{time} years ago';

$txt['page_indicator'] = ' - page %s'; // can be turned into an array as needed (1 => '', 'n' => ...)

$txt['days'] = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
$txt['days_short'] = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
// Months must start with 1 => 'January'. (or translated, of course.)
$txt['months'] = array(1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
$txt['months_short'] = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');

$txt['admin'] = 'Admin';
$txt['moderate'] = 'Moderate';

$txt['save'] = 'Save';
$txt['modify'] = 'Modify';

$txt['members'] = 'Members';
$txt['board_name'] = 'Board name';

$txt['member_postcount'] = 'Posts';
$txt['no_subject'] = '(No subject)';
$txt['view_profile'] = 'View profile';
$txt['guest_title'] = 'Guest';
$txt['author'] = 'Author';
$txt['on_date'] = 'on %1$s';
$txt['remove'] = 'Remove';
$txt['start_new_topic'] = 'Start new topic';

$txt['login'] = 'Login';
// Use numeric entities in the below string.
$txt['username'] = 'Username';
$txt['password'] = 'Password';
$txt['username_or_email'] = 'Username or Email';

$txt['board_moderator'] = 'Board Moderator';
$txt['remove_topic'] = 'Remove Topic';
$txt['modify_msg'] = 'Modify message';
$txt['name'] = 'Name';
$txt['email'] = 'Email';
$txt['subject'] = 'Subject';
$txt['message'] = 'Message';
$txt['quick_modify'] = 'Modify Inline';

$txt['posts'] = 'Posts';
$txt['topics'] = 'Topics';
$txt['redirects'] = 'Redirects';
$txt['replies'] = 'Replies';
$txt['views'] = 'Views';

$txt['num_posts'] = array(0 => 'No posts', 1 => '1 post', 'n' => '%s posts');
$txt['num_topics'] = array(0 => 'No topics', 1 => '1 topic', 'n' => '%s topics');
$txt['num_redirects'] = array(0 => 'No redirections', 1 => '1 redirection', 'n' => '%s redirections');
$txt['num_replies'] = array(0 => 'No reply', 1 => '1 reply', 'n' => '%s replies');
$txt['num_views'] = array(0 => 'Never viewed', 1 => '1 view', 'n' => '%s views');

// Likes. What you've done implied past tense, while when you haven't liked it thus far, implies a present tense for everyone else.
$txt['you_like_this'] = array(
	0 => 'You like this.',
	1 => 'You and 1 other person like this.',
	'n' => 'You and %1$s other people like this.',
);
$txt['like_this'] = array(
	1 => '1 person likes this.',
	'n' => '%1$s people have liked this.',
);
$txt['like'] = 'Like';
$txt['unlike'] = 'Unlike';

$txt['nobody_likes_this'] = 'Nobody likes this.';
$txt['likes_header'] = array(1 => '1 person likes this.', 'n' => '%s people like this.');

$txt['choose_pass'] = 'Choose password';
$txt['verify_pass'] = 'Verify password';
$txt['position'] = 'Position';

$txt['total'] = 'Total';
$txt['posts_made'] = 'Posts';
$txt['website'] = 'Website';
$txt['register'] = 'Register';
$txt['warning_status'] = 'Warning Status';
$txt['user_warn_warned'] = 'User has been warned';
$txt['user_warn_moderate'] = 'User posts join approval queue';
$txt['user_warn_mute'] = 'User is banned from posting';
$txt['user_warn_soft_ban'] = 'User is soft-banned';
$txt['user_warn_hard_ban'] = 'User is hard-banned';
$txt['warn_warned'] = 'Warned';
$txt['warn_moderate'] = 'Moderated';
$txt['warn_mute'] = 'Muted';
$txt['warn_soft_ban'] = '(Soft) Banned';
$txt['warn_hard_ban'] = '(Hard) Banned';

// User menu strings
$txt['usermenu_profile'] = 'Profile';
$txt['usermenu_profile_desc'] = 'View user profile';
$txt['usermenu_website'] = 'Website';
$txt['usermenu_website_desc'] = 'Go to user\'s website';
$txt['usermenu_sendpm'] = 'Message';
$txt['usermenu_sendpm_desc'] = 'Send user a personal message';
$txt['usermenu_showposts'] = 'View posts';
$txt['usermenu_showposts_desc'] = 'View user\'s latest contributions';
$txt['usermenu_addbuddy'] = '+ Contacts';
$txt['usermenu_addbuddy_desc'] = 'Add user to my contacts';
$txt['usermenu_removebuddy'] = '- Contacts';
$txt['usermenu_removebuddy_desc'] = 'Remove user from my contacts';
$txt['usermenu_ignore'] = 'Ignore';
$txt['usermenu_ignore_desc'] = 'Ignore this user';
$txt['usermenu_unignore'] = 'Unignore';
$txt['usermenu_unignore_desc'] = 'Unignore this user';
$txt['usermenu_seeip'] = '<span style="color: #aaa">IP: %2%</span>';
$txt['usermenu_seeip_desc'] = 'Posted from this IP address';
$txt['usermenu_trackip'] = '<span style="color: #aaa">IP: %2%</span>';
$txt['usermenu_trackip_desc'] = 'Track this IP address';

// Action menu strings (per post)
$txt['acme_like'] = 'Like';
$txt['acme_like_desc'] = 'Like this post';
$txt['acme_unlike'] = 'Unlike';
$txt['acme_unlike_desc'] = 'Unlike this post';
$txt['acme_quote'] = 'Quote';
$txt['acme_quote_desc'] = 'Reply to this post';
$txt['acme_modify'] = 'Modify';
$txt['acme_modify_desc'] = 'Edit this post';
$txt['acme_report'] = 'Report';
$txt['acme_report_desc'] = 'Report this post to a moderator';
$txt['acme_restore'] = 'Restore';
$txt['acme_restore_desc'] = 'Restore this post\'s visibility';
$txt['acme_merge'] = 'Merge';
$txt['acme_merge_desc'] = 'Merge this post with the previous one';
$txt['acme_split'] = 'Split';
$txt['acme_split_desc'] = 'Split this topic into a new one';
$txt['acme_remove'] = 'Delete';
$txt['acme_remove_desc'] = 'Delete this post definitively';
$txt['acme_approve'] = 'Approve';
$txt['acme_approve_desc'] = 'Allow this post to be viewed by others';
$txt['acme_warn'] = 'Warn';
$txt['acme_warn_desc'] = 'Issue a warning about this post';

$txt['actions_button'] = 'Actions&hellip;';
$txt['more_actions'] = 'More&hellip;';

$txt['board_index'] = 'Board index';
$txt['message_index'] = 'Message Index';
$txt['news'] = 'News';
$txt['home'] = 'Home';
$txt['community'] = 'Community';

$txt['lock_unlock'] = 'Lock/Unlock Topic';
$txt['post'] = 'Post';
$txt['error_occurred'] = 'An Error Has Occurred!';
$txt['logout'] = 'Logout';
$txt['started_by'] = 'Started by';
$txt['last_post_author_link_time'] = '<strong>Last post</strong> by {author} in {link} {time}';
$txt['last_post_time_author'] = '{time} by {author}';
$txt['board_off_limits'] = 'This board is off-limits to you.';

$txt['last_post'] = 'Last post';
// Use numeric entities in the below string.
$txt['topic'] = 'Topic';
$txt['help'] = 'Help';
$txt['notify'] = 'Notify';
$txt['unnotify'] = 'Unnotify';
$txt['notify_request'] = 'Do you want a notification email if someone replies to this topic?';

$txt['move_topic'] = 'Move Topic';
$txt['move_to'] = 'Move to';
$txt['pages'] = 'Pages';
$txt['users_active'] = 'Users active in past %1$d minutes';
$txt['personal_messages'] = 'Personal Messages';

$txt['quote_from'] = 'Quote from';
$txt['quote'] = 'Quote';
$txt['quote_noun'] = 'Quote';
$txt['reply'] = 'Reply';
$txt['reply_number'] = 'Reply #<strong>%1$d</strong>,';

$txt['approve'] = 'Approve';
$txt['approve_all'] = 'approve all';
$txt['awaiting_approval'] = 'Awaiting Approval';
$txt['post_awaiting_approval'] = 'Note: This message is awaiting approval by a moderator.';
$txt['there_are_unapproved_topics'] = 'There are %1$s topics and %2$s posts awaiting approval in this board. Click <a href="%3$s">here</a> to view them all.';

$txt['msg_alert_none'] = 'No messages...';
// SSI - The {new} construct is used to add the (x new) area in a language-dependent manner, using unread_pms, as below.
$txt['you_have_msg'] = array(
	0 => 'you have no messages',
	1 => 'you have <a href="<URL>?action=pm">1</a> message {new}',
	'n' => 'you have <a href="<URL>?action=pm">%s</a> messages {new}',
);
$txt['unread_pms'] = array(0 => '(none new)', 1 => '(1 new)', 'n' => '(%s new)');

$txt['remove_message'] = 'Remove this message';
$txt['remove_message_confirm'] = 'Remove this message?';

$txt['online_users'] = 'Users Online';
$txt['personal_message'] = 'Personal Message';
$txt['jump_to'] = 'Quick access';
$txt['are_sure_remove_topic'] = 'Are you sure you want to remove this topic?';

$txt['go'] = 'Go';
$txt['ok'] = 'OK';
$txt['yes'] = 'Yes';
$txt['no'] = 'No';

$txt['search'] = 'Search';
$txt['all_pages'] = 'All';

$txt['back'] = 'Back';
$txt['topic_started'] = 'Topic started by';
$txt['title'] = 'Title';
$txt['post_by'] = 'Post by';
$txt['welcome_member'] = 'Please welcome %1$s, our newest member.';
$txt['notify_deactivate'] = 'Would you like to deactivate notification on this topic?';

$txt['last_edit'] = 'Last edited {date} by {name}';
$txt['last_edit_mine'] = 'Last edited {date}';

$txt['location'] = 'Location';
$txt['gender'] = 'Gender';
$txt['date_registered'] = 'Date Registered';

$txt['recent_posts'] = 'Recent Posts';
$txt['recent_view'] = 'View the most recent posts on the forum.';

$txt['male'] = 'Male';
$txt['female'] = 'Female';

$txt['welcome_guest'] = 'Welcome, <strong>%1$s</strong>. Please <a href="<URL>?action=login">login</a> or <a href="<URL>?action=register">register</a>.';
$txt['welcome_guest_noregister'] = 'Welcome, <strong>%1$s</strong>. Please <a href="<URL>?action=login">login</a>.';
$txt['login_or_register'] = 'Please <a href="<URL>?action=login">login</a> or <a href="<URL>?action=register">register</a>.';
$txt['please_login'] = 'Please <a href="<URL>?action=login">login</a>.';
$txt['welcome_guest_activate'] = '<br>Did you miss your <a href="<URL>?action=activate">activation email</a>?';
$txt['hello_member'] = 'Hey,';
$txt['hello_guest'] = 'Welcome,';
$txt['select_destination'] = 'Select destination';

$txt['posted_by'] = 'Posted by';

$txt['moderator'] = 'Moderator';
$txt['moderators'] = 'Moderators';

// For the Short form, use '!' in case your language's 'New' is too long.
$txt['new'] = 'New';
$txt['new_short'] = 'New';

$txt['edited'] = 'Edited';

$txt['forgot_your_password'] = 'Forgot your password?';

$txt['date'] = 'Date';
$txt['from'] = 'From';
$txt['to'] = 'To';

$txt['members_title'] = 'Members';

$txt['redirect_board'] = 'Redirect Board';

$txt['notification'] = 'Notification';

$txt['your_ban'] = 'Sorry %1$s, you are banned from using this forum!<br>%2$s';
$txt['your_ban_expires'] = 'This ban is set to expire %1$s.';
$txt['your_ban_expires_never'] = 'This ban is not set to expire.';
$txt['ban_continue_browse'] = 'You may continue to browse the forum as a guest.';

$txt['mark_board_read'] = 'Mark Topics as Read for this Board';
$txt['mark_as_read'] = 'Mark ALL messages as read';

$txt['legend'] = 'Legend';
$txt['locked_topic'] = 'Locked Topic';
$txt['normal_topic'] = 'Normal Topic';
$txt['participation_caption'] = 'Topic you have posted in';

$txt['print'] = 'Print';
$txt['profile'] = 'Profile';
$txt['not_applicable'] = 'N/A';
$txt['preview'] = 'Preview';
$txt['remove_draft'] = 'Remove draft';
$txt['ip'] = 'IP';
$txt['by'] = 'by';
$txt['days_word'] = 'days';
$txt['search_for'] = 'Search for';
$txt['maintain_mode_on'] = 'Remember, this forum is in \'Maintenance Mode\'.';

$txt['global_stats'] = 'Global statistics';
$txt['forum_stats'] = 'Board statistics';
$txt['blog_stats'] = 'Blog statistics';
$txt['topic_stats'] = 'Topic statistics';

$txt['latest_member'] = 'Latest Member';
$txt['latest_post'] = 'Latest Post';

$txt['youve_got_pms'] = array(0 => 'You have no messages...', 1 => 'You have 1 message...', 'n' => 'You have %s messages...');
$txt['click_to_view_them'] = 'Click <a href="%1$s">here</a> to view them.';

$txt['print_page'] = 'Print Page';

$txt['info_center_title'] = 'Info Center';

$txt['send_topic'] = 'Send this topic';

$txt['check_all'] = 'Check all';

$txt['file'] = 'File';

$txt['today'] = '<strong>Today</strong> at ';
$txt['yesterday'] = '<strong>Yesterday</strong> at ';
$txt['new_poll'] = 'New poll';
$txt['poll_vote'] = 'Submit Vote';
$txt['poll_total_voters'] = 'Total Members Voted';
$txt['poll_results'] = 'View results';
$txt['poll_lock'] = 'Lock Voting';
$txt['poll_unlock'] = 'Unlock Voting';
$txt['poll_edit'] = 'Edit Poll';
$txt['poll'] = 'Poll';
$txt['poll_voters_guests_only'] = array(
	1 => '1 guest',
	'n' => '%1$s guests',
);
$txt['poll_voters'] = array(
	1 => 'and 1 guest',
	'n' => 'and %1$s guests',
);
$txt['poll_visibility_admin'] = 'Only forum administrators will be able to see what you voted for.';
$txt['poll_visibility_creator'] = 'The forum administrators, and the poll creator, will be able to see what you voted for.';
$txt['poll_visibility_members'] = 'Any signed-in member of the forum will be able to see what you voted for.';
$txt['poll_visibility_anyone'] = 'Anyone, even guests, will be able to see what you voted for.';
$txt['one_day'] = '1 Day';
$txt['one_week'] = '1 Week';
$txt['one_month'] = '1 Month';
$txt['forever'] = 'Forever';
$txt['quick_login_desc'] = 'Login with username, password and session length';
$txt['one_hour'] = '1 Hour';
$txt['board'] = 'Board';
$txt['in'] = 'in';
$txt['pinned_topic'] = 'Pinned Topic';

$txt['delete'] = 'Delete';

$txt['kilobyte'] = 'kB';

$txt['more_stats'] = '[More Stats]';

$txt['code'] = 'Code';
$txt['code_select'] = '[Select]';

$txt['merge'] = 'Merge Topics';
$txt['new_topic'] = 'New Topic';

$txt['set_pin'] = 'Pin topic';
$txt['set_unpin'] = 'Unpin topic';
$txt['set_lock'] = 'Lock topic';
$txt['set_unlock'] = 'Unlock topic';
$txt['order_pinned_topic'] = 'Reorder Pinned';

$txt['page_created'] = 'Page created in ';
$txt['seconds_with_query'] = '%1$.2f seconds with 1 query.';
$txt['seconds_with_queries'] = '%1$.2f seconds with %2$d queries.';

$txt['online'] = 'Online';
$txt['offline'] = 'Offline';
$txt['pm_online'] = 'Personal Message (Online)';
$txt['pm_offline'] = 'Personal Message (Offline)';
$txt['online_status'] = 'Status';

$txt['go_up'] = 'Go Up';
$txt['go_down'] = 'Go Down';

$txt['site_credits'] = 'Website credits';
$txt['copyright'] = 'Powered by <a href="http://wedge.org/" target="_blank" class="new_win">Wedge</a>';
$txt['dynamic_replacements'] = '<abbr title="Dynamic Replacements">DR</abbr>';

$txt['template_block_error'] = 'Unable to find the "%1$s" template block.';
$txt['theme_template_error'] = 'Unable to load the "%1$s" template.';
$txt['theme_language_error'] = 'Unable to load the "%1$s" language file.';

$txt['sub_boards'] = 'Sub-Boards';

$txt['smtp_no_connect'] = 'Could not connect to SMTP host';
$txt['smtp_port_ssl'] = 'SMTP port setting incorrect; it should be 465 for SSL servers.';
$txt['smtp_bad_response'] = 'Couldn\'t get mail server response codes';
$txt['smtp_error'] = 'Ran into problems sending Mail. Error: ';

$txt['mlist_search'] = 'Search For Members';
$txt['mlist_search_again'] = 'Search again';
$txt['mlist_search_email'] = 'Search by email address';
$txt['mlist_search_group'] = 'Search by position';
$txt['mlist_search_name'] = 'Search by name';
$txt['mlist_search_website'] = 'Search by website';
$txt['mlist_search_results'] = 'Search results for';
$txt['mlist_search_by'] = 'Search by %1$s';
$txt['mlist_menu_view'] = 'View the memberlist';

$txt['attach_downloaded'] = array(1 => 'downloaded once.', 'n' => 'downloaded %s times.');
$txt['attach_viewed'] = array(1 => 'viewed once.', 'n' => 'viewed %s times.');

$txt['settings'] = 'Settings';
$txt['never'] = 'Never';
$txt['more'] = 'more';

$txt['hostname'] = 'Hostname';
$txt['you_are_post_banned'] = 'Sorry %1$s, you are banned from posting on this forum.';
$txt['you_are_pm_banned'] = 'Sorry %1$s, you are banned from sending personal messages on this forum.';
$txt['you_are_post_pm_banned'] = 'Sorry %1$s, you are banned from posting and sending personal messages on this forum.';

$txt['add_poll'] = 'Add poll';
$txt['poll_options6'] = 'You may only select up to %1$s options.';
$txt['poll_remove'] = 'Remove Poll';
$txt['poll_remove_warn'] = 'Are you sure you want to remove this poll from the topic?';
$txt['poll_results_expire'] = 'Results will be shown when voting has closed';
$txt['poll_expires_on'] = 'Voting closes';
$txt['poll_expired_on'] = 'Voting closed';
$txt['poll_change_vote'] = 'Remove Vote';
$txt['poll_return_vote'] = 'Voting options';
$txt['poll_cannot_see'] = 'You cannot see the results of this poll at the moment.';

$txt['quick_mod_approve'] = 'Approve selected';
$txt['quick_mod_remove'] = 'Remove selected';
$txt['quick_mod_lock'] = 'Lock/Unlock selected';
$txt['quick_mod_pin'] = 'Pin/Unpin selected';
$txt['quick_mod_move'] = 'Move selected to';
$txt['quick_mod_merge'] = 'Merge selected';
$txt['quick_mod_markread'] = 'Mark selected read';
$txt['quick_mod_go'] = 'Go!';

$txt['generic_confirm_request'] = 'Are you sure you want to do this?';

$txt['reagree_reply'] = 'The site terms and conditions have changed. Before you can post on the site, you will need to re-accept the user agreement. You can do so by visiting <a href="%1$s">this page</a>.';
$txt['quick_reply'] = 'Quick Reply';
$txt['quick_reply_warning'] = 'Warning: this topic is currently locked! Only admins and moderators can reply.';
$txt['quick_reply_verification'] = 'After submitting your post you will be directed to the regular post page to verify your post %1$s.';
$txt['quick_reply_verification_guests'] = '(required for all guests)';
$txt['quick_reply_verification_posts'] = '(required for all users with less than %1$d posts)';
$txt['wait_for_approval'] = 'Note: this post will not display until it\'s been approved by a moderator.';

$txt['notification_enable_board'] = 'Are you sure you wish to enable notification of new topics for this board?';
$txt['notification_disable_board'] = 'Are you sure you wish to disable notification of new topics for this board?';
$txt['notification_enable_topic'] = 'Are you sure you wish to enable notification of new replies for this topic?';
$txt['notification_disable_topic'] = 'Are you sure you wish to disable notification of new replies for this topic?';

$txt['unread_topics'] = 'Unread Topics';
$txt['unread_replies'] = 'Updated Topics';

$txt['who_title'] = 'Who\'s Online';
$txt['who_and'] = ' and ';
$txt['who_viewing_topic'] = ' are viewing this topic.';
$txt['who_viewing_board'] = ' are viewing this board.';
$txt['who_member'] = 'Member';

// Feed block
$txt['feed'] = 'Latest Posts Feed';
$txt['feed_current_topic'] = 'This topic:';
$txt['feed_current_forum'] = 'This forum:';
$txt['feed_current_blog'] = 'This blog:';
$txt['feed_everywhere'] = 'All:';
$txt['feed_posts'] = '<a href="%1$s">posts</a>';
$txt['feed_topics'] = '<a href="%1$s">topics</a>';

$txt['guest'] = 'Guest';
$txt['guests'] = 'Guests';
$txt['user'] = 'User';
$txt['users'] = 'Users';
$txt['hidden'] = 'Hidden';

$txt['buddy'] = 'Contact';
$txt['buddies'] = 'Contacts';
$txt['contacts_friends'] = 'Friends';
$txt['contacts_known'] = 'Acquaintances';
$txt['contacts_work'] = 'Colleagues';
$txt['contacts_family'] = 'Family';
$txt['contacts_follow'] = 'Followed';
$txt['contacts_restrict'] = 'Restricted';
$txt['contacts_custom'] = 'Other';
$txt['contacts_new'] = 'New list';
$txt['is_buddy'] = 'Is in my contact list';
$txt['is_not_buddy'] = 'Isn\'t in my contact list';

$txt['most_online_ever'] = 'Most Online Ever';
$txt['most_online_today'] = 'Most Online Today';

$txt['response_prefix'] = 'Re: ';

$txt['approve_members_waiting'] = array(
	1 => 'There is <a href="<URL>?action=admin;area=viewmembers;sa=browse;type=approve">one member</a> awaiting approval.',
	'n' => 'There are <a href="<URL>?action=admin;area=viewmembers;sa=browse;type=approve">%s members</a> awaiting approval.',
);

$txt['notifyboard_turnon'] = 'Do you want a notification email when someone posts a new topic in this board?';
$txt['notifyboard_turnoff'] = 'Are you sure you do not want to receive new topic notifications for this board?';

$txt['show_unread'] = 'Unread posts';
$txt['show_unread_replies'] = 'Unread replies';

$txt['quickmod_delete_selected'] = 'Remove Selected';

$txt['show_personal_messages'] = 'You have received one or more new personal messages.<br><br>Would you like to open a new window to view them?';

$txt['previous_next_back'] = '&laquo; previous';
$txt['previous_next_forward'] = 'next &raquo;';

$txt['upshrink_description'] = 'Shrink or expand this.';

$txt['mark_unread'] = 'Mark unread';

$txt['error_while_submitting'] = 'The following error or errors occurred while posting this message:';
$txt['error_old_topic'] = 'Warning: this topic has not been posted in for at least %1$d days.<br>Unless you\'re sure you want to reply, please consider starting a new topic.';

$txt['mark_read_short'] = 'Mark Read';

$txt['pm_short'] = 'My Messages';

$txt['hello_member_ndt'] = 'Hello, <span>%1$s</span>!';

$txt['unapproved_posts'] = 'Unapproved Posts (Topics: %1$d, Posts: %2$d)';

$txt['ajax_in_progress'] = 'Loading...';

$txt['verification'] = 'Verification';
$txt['visual_verification_description'] = 'Type the letters shown in the picture';
$txt['visual_verification_sound'] = 'Listen to the letters';
$txt['visual_verification_request_new'] = 'Request another image';
$txt['visual_verification_hidden'] = 'This box must be left blank';

// Sub menu labels
$txt['summary'] = 'Summary';
$txt['account'] = 'Account Settings';
$txt['forumprofile'] = 'Forum Profile';
$txt['change_skin'] = 'Skin Selector';
$txt['draft_posts'] = 'Draft Posts';

$txt['skin_default'] = 'Default';
$txt['skin_default_mobile'] = 'Mobile default';

$txt['settings_title'] = 'General Options';
$txt['plugin_manager'] = 'Plugins';
$txt['errlog'] = 'Error Log';
$txt['edit_permissions'] = 'Permissions';
$txt['mc_unapproved_poststopics'] = 'Unapproved Posts';
$txt['mc_reported_posts'] = 'Reported Posts';
$txt['modlog_view'] = 'Moderation Log';
$txt['unapproved_members'] = 'Unapproved Members';
$txt['admin_uncache'] = 'Purge cache';

$txt['ignoring_user'] = 'You are ignoring this user. Click here to see or hide the post.';

$txt['spider'] = 'Spider';
$txt['spiders'] = 'Spiders';

$txt['downloads'] = 'Downloads';
$txt['filesize'] = 'Filesize';

$txt['sideshow'] = 'Click here, or middle-click anywhere on the page, to toggle the sidebar.';

// Restore topic
$txt['restore_topic'] = 'Restore Topic';
$txt['quick_mod_restore'] = 'Restore Selected';

$txt['autosuggest_delete_item'] = 'Delete Item';

// Ignoring topics
$txt['ignoring_topic'] = 'You\'re currently ignoring this topic.';
$txt['ignore_topic'] = 'Ignore';
$txt['unignore_topic'] = 'Unignore';

// Site type.
$txt['b_type'] = array('blog' => 'blog', 'forum' => 'forum', 'media' => 'gallery', 'site' => 'site');
$txt['b_item'] = array('blog' => 'post', 'forum' => 'topic', 'media' => 'item', 'site' => 'article');

// Indicates the date of the first post when merging two posts.
$txt['search_date_posted'] = 'Posted:';

// Spoiler tags. But you're smart, so you don't need me telling.
$txt['spoiler'] = 'Spoiler';
$txt['click_for_spoiler'] = '(click to show/hide)';

$txt['readmore'] = '(%1$d more chars)';
$txt['thoughts'] = 'Thoughts';
$txt['add_thought'] = '(Click here to send a thought)';
$txt['no_thoughts'] = 'No thoughts for now.';
$txt['thome_edit'] = 'Edit';
$txt['thome_remove'] = 'Delete';
$txt['thome_reply'] = 'Reply';
$txt['thome_context'] = 'In context';
$txt['thome_personal'] = 'Posterity?';
$txt['thome_personal_desc'] = 'Show this thought under my name in topics, for posterity.';

// Do not use double quotes in the following strings.
$txt['privacy'] = 'Privacy';
$txt['privacy_default'] = 'Default';
$txt['privacy_public'] = 'Public';
$txt['privacy_members'] = 'Members';
$txt['privacy_group'] = 'Membergroup';
$txt['privacy_list'] = 'Contacts';
$txt['privacy_author'] = 'Just me';

// Do not use double quotes in the form_* text strings. (Why would you?)
$txt['form_submit'] = 'Submit';
$txt['form_cancel'] = 'Cancel';

// Media Gallery
$txt['media_gallery'] = 'Media Gallery';
$txt['media_home'] = 'Home';
$txt['media_unseen'] = 'Unseen';
$txt['media_profile_sum'] = 'Summary';
$txt['media_view_items'] = 'View items';
$txt['media_view_coms'] = 'View comments';
$txt['media_view_votes'] = 'View votes';
$txt['media_gotolink'] = 'Details';
$txt['media_zoom'] = 'Zoom';
