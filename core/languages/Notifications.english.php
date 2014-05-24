<?php
// Language file for notifications

$txt['notifications'] = 'Notifications';
$txt['notifications_short_unread'] = 'Unread';
$txt['notifications_short_latest'] = 'Latest';
$txt['notifications_short_all'] = 'View all';
$txt['notifications_short_settings'] = 'Settings';
$txt['notifications_short_unread_pms'] = 'Unread';
$txt['notifications_short_inbox'] = 'Inbox';
$txt['notifications_short_sent'] = 'Outbox';
$txt['notifications_short_drafts'] = 'Drafts';
$txt['notifications_short_write_pm'] = 'Compose';

$txt['notification_unread_title'] = 'Unread Notifications';
$txt['notification_unread_none'] = 'No unread notifications.';
$txt['notification_none'] = 'No notifications.';
$txt['notification_disable'] = 'Disable notifications?';
$txt['notification_profile_desc'] = 'You can disable notifications from specific notifiers here, please note that disabling notifications will only prevent new notifications but will keep the existing ones.';
$txt['scheduled_task_weNotif::scheduled_prune'] = 'Prune read notifications';
$txt['scheduled_task_desc_weNotif::scheduled_prune'] = 'Prune read notifications which are older than the specified days in Admin &gt; Notifications';
$txt['notification_admin_desc'] = 'Settings for the notifications core';
$txt['notifications_prune_days'] = 'Prune notifications older than (days)';
$txt['notifications_prune_days_subtext'] = 'Any read notification older than the specified number of days will be deleted from the database. If you want to disable this feature, disable the scheduled task found under Admin &gt; Server &amp; Maintenance &gt; Scheduled Tasks';
$txt['notification_email'] = 'Notify via e-mail?';
$txt['enabled'] = 'Enabled';
$txt['disabled'] = 'Disabled';
$txt['notify_periodically'] = 'Notify periodically';
$txt['notify_instantly'] = 'Notify instantly and periodically';
$txt['notify_disable'] = 'Don\'t notify via e-mail';
$txt['notify_period_desc'] = 'The number of days after which your periodical notifications will be sent. All of your unread notifications will be grouped and sent via e-mail after this many days';
$txt['notify_period'] = 'Periodical notification';
$txt['scheduled_task_weNotif::scheduled_periodical'] = 'Send periodical notification e-mails';
$txt['scheduled_task_desc_weNotif::scheduled_periodical'] = 'Sends all the periodical notification e-mails for the members who have unread notifications';
$txt['notification_email_periodical_subject'] = '%s, you have %d unread notification(s)!';
$txt['notification_email_periodical_body'] = 'It looks like you have gathered a bunch of unread notifications over the past few days, here is the gist of them. Head over to the forums to check them out!';

$txt['notifier_likes_title'] = 'Post Likes';
$txt['notifier_likes_desc'] = 'Notify you when one of your messages has been liked.';
$txt['notifier_likes_subject'] = 'Someone liked one of your posts!';
$txt['notifier_likes_html'] = '<span class="like_button"></span>{MEMBER_LINK} liked your post, "{OBJECT_LINK}"';
$txt['notifier_likes_text'] = '{MEMBER_NAME} liked your post, "{OBJECT_NAME}", which is located here:

{OBJECT_URL}';

$txt['notifier_likes_thought_title'] = 'Thought Likes';
$txt['notifier_likes_thought_desc'] = 'Notify you when one of your thoughts has been liked.';
$txt['notifier_likes_thought_subject'] = 'Someone liked one of your thoughts!';
$txt['notifier_likes_thought_html'] = '<span class="like_button"></span>{MEMBER_LINK} liked your thought: "{OBJECT_NAME}"';
$txt['notifier_likes_thought_text'] = '{MEMBER_NAME} liked your thought: "{OBJECT_NAME}"';

$txt['notifier_move_title'] = 'Moved Topics';
$txt['notifier_move_desc'] = 'Receive notifications when anyone moves topics you started';
$txt['notifier_move_subject'] = 'Someone moved your topic!';
$txt['notifier_move_noaccess_html']  = '{MEMBER_LINK} moved your topic {TOPIC_LINK} to a board you cannot access.';
$txt['notifier_move_noaccess_text'] = '{MEMBER_NAME} moved your topic {TOPIC_NAME} to a board you cannot access.';
$txt['notifier_move_html'] = '{MEMBER_LINK} moved {TOPIC_LINK} to {BOARD_LINK}.';
$txt['notifier_move_text'] = '{MEMBER_NAME} moved {TOPIC_NAME} to {BOARD_NAME}. You can access the board here:

{BOARD_URL}';

$txt['notif_subs'] = 'Subscriptions';
$txt['notif_subs_start_time'] = 'Start time';
$txt['notif_unsubscribe'] = 'Unsubscribe';
$txt['notif_subscribe'] = 'Subscribe';
$txt['notif_subs_desc'] = 'You can manage your existing subscriptions to various notifiers here, note that if you have subscriptions from disabled notifiers then they will not be displayed.';

$txt['no_pms'] = 'You have no unread messages.';
$txt['pm_sent_to_you'] = '%1$s sent you a message, "%2$s".';
$txt['pm_replied_to_pm'] = '%1$s replied to your message, "%2$s".';
