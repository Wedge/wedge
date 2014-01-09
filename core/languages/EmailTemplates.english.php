<?php
// Version: 2.0; EmailTemplates

// Since all of these strings are being used in emails, numeric entities should be used.

// Do not translate anything that is between {}, they are used as replacement variables and MUST remain exactly how they are.

$txt['regards_team'] = 'Regards,
The {FORUMNAME} Team.';

$txt['scheduled_approval_email_topic'] = 'The following topics are awaiting approval:';
$txt['scheduled_approval_email_msg'] = 'The following posts are awaiting approval:';

$txt['emailtemplate_resend_activate_message'] = array(
	'desc' => 'The email sent to a user when they have registered and their account needs activating through email (as the second or later email)',
	'subject' => 'Welcome to {FORUMNAME}',
	'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you can reset it by visiting {FORGOTPASSWORDLINK}.

Before you can login, you must first activate your account by selecting the following link:

{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}',
);

$txt['emailtemplate_mc_group_approve'] = array(
	'desc' => 'The email sent to a user when they requested to join a group and the request was approved.',
	'subject' => 'Group Membership Approval',
	'body' => '{REALNAME},

We\'re pleased to notify you that your application to join the "{GROUPNAME}" group at {FORUMNAME} has been accepted, and your account has been updated to include this new membergroup.

{REGARDS}',
);

$txt['emailtemplate_mc_group_reject'] = array(
	'desc' => 'The email sent to a user when they requested to join a group and the request was not approved.',
	'subject' => 'Group Membership Rejection',
	'body' => '{REALNAME},

We\'re sorry to notify you that your application to join the "{GROUPNAME}" group at {FORUMNAME} has been rejected.

{REGARDS}',
);

$txt['emailtemplate_mc_group_reject_reason'] = array(
	'desc' => 'The email sent to a user when they requested to join a group and the request was not approved, including a reason why.',
	'subject' => 'Group Membership Rejection',
	'body' => '{REALNAME},

We\'re sorry to notify you that your application to join the "{GROUPNAME}" group at {FORUMNAME} has been rejected.

This is due to the following reason: {REFUSEREASON}

{REGARDS}',
);

$txt['emailtemplate_admin_approve_accept'] = array(
	'desc' => 'The email sent to a user when their account was manually activated (e.g. not through email) by the administrator.',
	'subject' => 'Welcome to {FORUMNAME}',
	'body' => 'Welcome, {REALNAME}!

Your account has been activated manually by the admin and you can now login and post. Your username is: {USERNAME}. If you forget your password, you can change it at {FORGOTPASSWORDLINK}.

{REGARDS}',
);

$txt['emailtemplate_admin_approve_activation'] = array(
	'desc' => 'The email sent to a user when their account was approved by the administrator (e.g. through admin approval)',
	'subject' => 'Welcome to {FORUMNAME}',
	'body' => 'Welcome, {REALNAME}!

Your account on {FORUMNAME} has been approved by the forum administrator. Before you can login, you must first activate your account by selecting the following link:

{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}',
);

$txt['emailtemplate_admin_approve_reject'] = array(
	'desc' => 'The email sent to a user when they registered, sat in admin approval and was subsequently rejected.',
	'subject' => 'Registration Rejected',
	'body' => '{USERNAME},

Regrettably, your application to join {FORUMNAME} has been rejected.

{REGARDS}',
);

$txt['emailtemplate_admin_approve_delete'] = array(
	'desc' => 'The email sent to a user when their account is deleted.',
	'subject' => 'Account Deleted',
	'body' => '{USERNAME},

Your account on {FORUMNAME} has been deleted. This may be because you never activated your account, in which case you should be able to register again.

{REGARDS}',
);

$txt['emailtemplate_admin_approve_remind'] = array(
	'desc' => 'The email sent to a user when their account is pending email registration but has not been completed.',
	'subject' => 'Registration Reminder',
	'body' => '{REALNAME},

You still have not activated your account at {FORUMNAME}.

Please use the link below to activate your account:
{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}',
);

$txt['emailtemplate_admin_register_activate'] = array(
	'desc' => 'The email sent to users who have been registered in the administration panel, and it requires email validation.',
	'subject' => 'Welcome to {FORUMNAME}',
	'body' => 'A member account was created for you at {FORUMNAME}. Your username is {USERNAME} and your password is {PASSWORD}.

Before you can login, you must first activate your account by selecting the following link:

{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

{REGARDS}',
);

$txt['emailtemplate_admin_register_immediate'] = array(
	'desc' => 'The email sent to users who have been registered in the administration panel and no further action is required.',
	'subject' => 'Welcome to {FORUMNAME}',
	'body' => 'A member account was created for you at {FORUMNAME}. Your username is {USERNAME} and your password is {PASSWORD}.

You can reach {FORUMNAME} by visiting {SCRIPTURL} in your browser.

{REGARDS}',
);

$txt['emailtemplate_new_announcement'] = array(
	'desc' => 'The email used when an administrator or moderator makes a forum announcement.',
	'subject' => 'New announcement: {TOPICSUBJECT}',
	'body' => '{MESSAGE}

To unsubscribe from these announcements, login to the forum and uncheck "Receive forum announcements and important notifications by email." in your profile.

You can view the full announcement by following this link:
{TOPICLINK}

{REGARDS}',
);

$txt['emailtemplate_notify_boards_once_body'] = array(
	'desc' => 'The email sent to users who have requested notifications on a given board, including the body, for the first new topic in that board (until they log in and read).',
	'subject' => 'New Topic: {TOPICSUBJECT}',
	'body' => 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

More topics may be posted, but you won\'t receive more email notifications until you return to the board and read some of them.

The text of the topic is shown below:
{MESSAGE}

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
);

$txt['emailtemplate_notify_boards_once'] = array(
	'desc' => 'The email sent to users who have requested notifications on a given board, for the first new topic in that board (until they log in and read).',
	'subject' => 'New Topic: {TOPICSUBJECT}',
	'body' => 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

More topics may be posted, but you won\'t receive more email notifications until you return to the board and read some of them.

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
);

$txt['emailtemplate_notify_boards_body'] = array(
	'desc' => 'The email sent to users who have requested notifications on a given board, including the body.',
	'subject' => 'New Topic: {TOPICSUBJECT}',
	'body' => 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

The text of the topic is shown below:
{MESSAGE}

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
);

$txt['emailtemplate_notify_boards'] = array(
	'desc' => 'The email sent to users who have requested notifications on a given board.',
	'subject' => 'New Topic: {TOPICSUBJECT}',
	'body' => 'A new topic, \'{TOPICSUBJECT}\', has been made on a board you are watching.

You can see it at
{TOPICLINK}

Unsubscribe to new topics from this board by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
);

$txt['emailtemplate_request_membership'] = array(
	'desc' => 'The email sent to moderators when a user applies to join another membergroup.',
	'subject' => 'New Group Application',
	'body' => '{RECPNAME},

{APPYNAME} has requested membership to the "{GROUPNAME}" group. The user has given the following reason:

{REASON}

You can approve or reject this application by clicking the link below:

{MODLINK}

{REGARDS}',
);

$txt['emailtemplate_paid_subscription_reminder'] = array(
	'desc' => 'The email sent to a user when a paid subscription is due to expire.',
	'subject' => 'Subscription about to expire at {FORUMNAME}',
	'body' => '{REALNAME},

A subscription you are subscribed to at {FORUMNAME} is about to expire. If when you took out the subscription you selected to auto-renew you need take no action - otherwise you may wish to consider subscribing once more. Details are below:

Subscription Name: {SUBSCRNAME}
Expires: {END_DATE}

To edit your subscriptions visit the following URL:
{PROFILELINKSUBS}

{REGARDS}',
);

$txt['emailtemplate_activate_reactivate'] = array(
	'desc' => 'The email sent to a user that has changed their email address and requires it be re-validated.',
	'subject' => 'Welcome back to {FORUMNAME}',
	'body' => 'In order to re-validate your email address, your account has been deactivated. Click the following link to activate it again:
{ACTIVATIONLINK}

Should you have any problems with activation, please visit {ACTIVATIONLINKWITHOUTCODE} and use the code "{ACTIVATIONCODE}".

{REGARDS}',
);

$txt['emailtemplate_forgot_password'] = array(
	'desc' => 'The email sent to a user when "forgot password" was used on their account.',
	'subject' => 'New password for {FORUMNAME}',
	'body' => 'Dear {REALNAME},
This mail was sent because the \'forgot password\' function has been applied to your account. To set a new password, click the following link:
{REMINDLINK}

IP: {IP}
Username: {USERNAME}

{REGARDS}',
);

$txt['emailtemplate_scheduled_approval'] = array(
	'desc' => 'The email sent to moderators, listing all items awaiting approval that they can approve.',
	'subject' => 'Summary of posts awaiting approval at {FORUMNAME}',
	'body' => '{REALNAME},

This email contains a summary of all items awaiting approval at {FORUMNAME}.

{MESSAGE}

Please log in to the forum to review these items.
{SCRIPTURL}

{REGARDS}',
);

$txt['emailtemplate_send_topic'] = array(
	'desc' => 'The email sent out when a user uses "Send This Topic" to encourage sharing links.',
	'subject' => 'Topic: {TOPICSUBJECT} (From: {SENDERNAMEMANUAL})',
	'body' => 'Dear {RECPNAMEMANUAL},
I want you to check out "{TOPICSUBJECT}" on {FORUMNAME}. To view it, please click this link:

{TOPICLINK}

Thanks,

{SENDERNAMEMANUAL}',
);

$txt['emailtemplate_send_topic_comment'] = array(
	'desc' => 'The email sent out when a user uses "Send This Topic" and a comment was added.',
	'subject' => 'Topic: {TOPICSUBJECT} (From: {SENDERNAMEMANUAL})',
	'body' => 'Dear {RECPNAMEMANUAL},

I want you to check out "{TOPICSUBJECT}" on {FORUMNAME}. To view it, please click this link:

{TOPICLINK}

A comment has also been added regarding this topic:
{COMMENT}

Thanks,

{SENDERNAMEMANUAL}',
);

$txt['emailtemplate_send_email'] = array(
	'desc' => 'A placeholder for the "Email User" function.',
	'subject' => '{EMAILSUBJECT}',
	'body' => '{EMAILBODY}',
);

$txt['emailtemplate_report_to_moderator'] = array(
	'desc' => 'The email notification sent to moderators when a post is reported.',
	'subject' => 'Reported post: {TOPICSUBJECT} by {POSTERNAME}',
	'body' => 'The following post, "{TOPICSUBJECT}" by {POSTERNAME} has been reported by {REPORTERNAME} on a board you moderate:

The topic: {TOPICLINK}
Moderation center: {REPORTLINK}

The reporter has made the following comment:
{COMMENT}

{REGARDS}',
);

$txt['emailtemplate_change_password'] = array(
	'desc' => 'The email sent to users when their password has been changed, including their new password.',
	'subject' => 'New Password Details',
	'body' => 'Hey, {REALNAME}!

Your login details at {FORUMNAME} have been changed and your password reset. Below are your new login details.

Your username is "{USERNAME}" and your password is "{PASSWORD}".

You may change it after you login by going to the profile page, or by visiting this page after you login:

{SCRIPTURL}?action=profile

{REGARDS}',
);

$txt['emailtemplate_register_activate'] = array(
	'desc' => 'The email sent to users when registering and need to activate their email address.',
	'subject' => 'Welcome to {FORUMNAME}',
	'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you can reset it by visiting {FORGOTPASSWORDLINK}.

Before you can login, you first need to activate your account. To do so, please follow this link:

{ACTIVATIONLINK}

Should you have any problems with activation, please visit {ACTIVATIONLINKWITHOUTCODE} use the code "{ACTIVATIONCODE}".

{REGARDS}',
);

$txt['emailtemplate_register_activate_approve'] = array(
	'desc' => 'The email sent to users to activate their account, but when the administrator then has to approve their account.',
	'subject' => 'Welcome to {FORUMNAME}',
	'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you can reset it by visiting {FORGOTPASSWORDLINK}.

Before you can login, you must first activate your account by selecting the following link:

{ACTIVATIONLINK}

Should you have any problems with the activation, please visit {ACTIVATIONLINKWITHOUTCODE} and enter the code "{ACTIVATIONCODE}".

Once that has taken place, the administrator will review your application and decide whether to approve or reject it. Once a decision is made, a further email will be sent to you.

{REGARDS}',
);

$txt['emailtemplate_register_coppa'] = array(
	'desc' => 'The email sent to users when registering but then need to fill out the COPPA form for parental consent.',
	'subject' => 'Welcome to {FORUMNAME}',
	'body' => 'Thank you for registering at {FORUMNAME}. Your username is {USERNAME}. If you forget your password, you can change it at {FORGOTPASSWORDLINK}

Before you can login, the admin requires consent from your parent/guardian for you to join the community. You can obtain more information at the link below:

{COPPALINK}

{REGARDS}',
);

$txt['emailtemplate_register_immediate'] = array(
	'desc' => 'The email sent to users when registering and their account is immediately active.',
	'subject' => 'Welcome to {FORUMNAME}',
	'body' => 'Thank you for registering at {FORUMNAME}.

Your username is {USERNAME}.

If you forget your password, you may change it at:

{FORGOTPASSWORDLINK}

{REGARDS}',
);

$txt['emailtemplate_register_pending'] = array(
	'desc' => 'The email sent to users when they have registered and their account is awaiting administrator approval.',
	'subject' => 'Welcome to {FORUMNAME}',
	'body' => 'Your registration request at {FORUMNAME} has been received, {REALNAME}.

The username you registered with was {USERNAME}. If you forget your password, you can change it at {FORGOTPASSWORDLINK}.

Before you can login and start using the forum, your request will be reviewed and approved. When this happens, you will receive another email from this address.

{REGARDS}',
);

$txt['emailtemplate_notification_reply'] = array(
	'desc' => 'The email sent when a user is following a topic and it has been replied to.',
	'subject' => 'Topic reply: {TOPICSUBJECT}',
	'body' => 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at:
{TOPICLINK}

Unsubscribe to this topic by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
);

$txt['emailtemplate_notification_reply_body'] = array(
	'desc' => 'The email sent when a user is following a topic and it has been replied to. The content of the post is included.',
	'subject' => 'Topic reply: {TOPICSUBJECT}',
	'body' => 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at:
{TOPICLINK}

Unsubscribe to this topic by using this link:
{UNSUBSCRIBELINK}

The text of the reply is shown below:
{MESSAGE}

{REGARDS}',
);

$txt['emailtemplate_notification_reply_once'] = array(
	'desc' => 'The email sent when a user is following a topic, and will only receive it on the first new post.',
	'subject' => 'Topic reply: {TOPICSUBJECT}',
	'body' => 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at:
{TOPICLINK}

Unsubscribe to this topic by using this link:
{UNSUBSCRIBELINK}

More replies may be posted, but you won\'t receive any more notifications until you read the topic.

{REGARDS}',
);

$txt['emailtemplate_notification_reply_body_once'] = array(
	'desc' => 'The email sent when a user is following a topic, and will only receive it on the first new post. The content of the post is included.',
	'subject' => 'Topic reply: {TOPICSUBJECT}',
	'body' => 'A reply has been posted to a topic you are watching by {POSTERNAME}.

View the reply at:
{TOPICLINK}

Unsubscribe to this topic by using this link:
{UNSUBSCRIBELINK}

The text of the reply is shown below:
{MESSAGE}

More replies may be posted, but you won\'t receive any more notifications until you read the topic.

{REGARDS}',
);

$txt['emailtemplate_notification_pin'] = array(
	'desc' => 'The email sent when a user is following a topic, and the topic is subsequently pinned or unpinned.',
	'subject' => 'Topic pinned: {TOPICSUBJECT}',
	'body' => 'A topic you are watching has been pinned or unpinned by {POSTERNAME}.

View the topic at:
{TOPICLINK}

Unsubscribe to this topic by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
);

$txt['emailtemplate_notification_lock'] = array(
	'desc' => 'The email sent when a user is following a topic, and the topic is subsequently locked.',
	'subject' => 'Topic locked: {TOPICSUBJECT}',
	'body' => 'A topic you are watching has been locked by {POSTERNAME}.

View the topic at:
{TOPICLINK}

Unsubscribe to this topic by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
);

$txt['emailtemplate_notification_unlock'] = array(
	'desc' => 'The email sent when a user is following a topic, and the topic is subsequently unlocked.',
	'subject' => 'Topic unlocked: {TOPICSUBJECT}',
	'body' => 'A topic you are watching has been unlocked by {POSTERNAME}.

View the topic at:
{TOPICLINK}

Unsubscribe to this topic by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
);

$txt['emailtemplate_notification_remove'] = array(
	'desc' => 'The email sent when a user is following a topic, and the topic is subsequently removed.',
	'subject' => 'Topic removed: {TOPICSUBJECT}',
	'body' => 'A topic you were watching has been removed by {POSTERNAME}.

{REGARDS}',
);

$txt['emailtemplate_notification_move'] = array(
	'desc' => 'The email sent when a user is following a topic, and the topic is subsequently moved elsewhere.',
	'subject' => 'Topic moved: {TOPICSUBJECT}',
	'body' => 'A topic you are watching has been moved to another board by {POSTERNAME}.

View the topic at:
{TOPICLINK}

Unsubscribe to this topic by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
);

$txt['emailtemplate_notification_merge'] = array(
	'desc' => 'The email sent when a user is following a topic, and the topic is subsequently merged with another.',
	'subject' => 'Topic merged: {TOPICSUBJECT}',
	'body' => 'A topic you are watching has been merged with another topic by {POSTERNAME}.

View the new merged topic at:
{TOPICLINK}

Unsubscribe to this topic by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
);

$txt['emailtemplate_notification_split'] = array(
	'desc' => 'The email sent when a user is following a topic, and the topic is subsequently split into more than one.',
	'subject' => 'Topic split: {TOPICSUBJECT}',
	'body' => 'A topic you are watching has been split into two or more topics by {POSTERNAME}.

View what remains of this topic at:
{TOPICLINK}

Unsubscribe to this topic by using this link:
{UNSUBSCRIBELINK}

{REGARDS}',
);

$txt['emailtemplate_admin_notify'] = array(
	'desc' => 'The email sent to administrators when a new member registers.',
	'subject' => 'A new member has joined',
	'body' => '{USERNAME} has just signed up as a new member of your forum. Click the link below to view their profile.
{PROFILELINK}

{REGARDS}',
);

$txt['emailtemplate_admin_notify_approval'] = array(
	'desc' => 'The email sent to administrators when a new member registers and they need to approve the account.',
	'subject' => 'A new member has joined',
	'body' => '{USERNAME} has just signed up as a new member of your forum. Click the link below to view their profile.
{PROFILELINK}

Before this member can begin posting they must first have their account approved. Click the link below to go to the approval screen.
{APPROVALLINK}

{REGARDS}',
);

$txt['emailtemplate_admin_attachments_full'] = array(
	'desc' => 'The email sent to warn administrators when the size of the attachments folder is close to the limit set in the admin panel.',
	'subject' => 'Urgent! Attachments folder almost full',
	'body' => '{REALNAME},

The attachments folder at {FORUMNAME} is almost full. Please visit the forum to resolve this problem.

Once the attachments folder reaches it\'s maximum permitted size users will not be able to continue to post attachments or upload custom avatars (If enabled).

{REGARDS}',
);

$txt['emailtemplate_paid_subscription_refund'] = array(
	'desc' => 'The email sent to administrators to advise them of a refund on a paid subscription.',
	'subject' => 'Refunded Paid Subscription',
	'body' => '{REALNAME},

A member has received a refund on a paid subscription. Below are the details of this subscription:

	Subscription: {SUBSCRNAME}
	User Name: {REFUNDNAME} ({REFUNDUSER})
	Date: {DATE}

You can view this members profile by clicking the link below:
{PROFILELINK}

{REGARDS}',
);

$txt['emailtemplate_paid_subscription_new'] = array(
	'desc' => 'The email sent to administrators to advise them a new paid subscription has been taken out.',
	'subject' => 'New Paid Subscription',
	'body' => '{REALNAME},

A member has taken out a new paid subscription. Below are the details of this subscription:

	Subscription: {SUBSCRNAME}
	User Name: {SUBNAME} ({SUBUSER})
	User Email: {SUBEMAIL}
	Price: {PRICE}
	Date: {DATE}

You can view this members profile by clicking the link below:
{PROFILELINK}

{REGARDS}',
);

$txt['emailtemplate_paid_subscription_error'] = array(
	'desc' => 'The email sent to administrators to advise them of an error in processing a paid subscription.',
	'subject' => 'Paid Subscription Error Occurred',
	'body' => '{REALNAME},

The following error occurred when processing a paid subscription
----------------------------------------------------------------
{SUBERROR}

{REGARDS}',
);

$txt['emailtemplate_pm_email'] = array(
	'desc' => 'The template used when sending notification of personal messages.',
	'subject' => 'New Personal Message: {SUBJECT}',
	'body' => 'You have just been sent a personal message by {SENDERNAME} on {FORUMNAME}.

IMPORTANT: Remember, this is just a notification. Please do not reply to this email.

The message they sent you was:

{MESSAGE}

Reply to this message here: {REPLYLINK}',
);
