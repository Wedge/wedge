<?php
// Version: 2.0; ManageMail

$txt['mailqueue_desc'] = 'From this page you can configure your mail settings, as well as viewing and administrating the current mail queue if it is enabled.';
$txt['mailqueue_templates_desc'] = 'From this area you can review and update the templates used by Wedge to send emails to users.';

$txt['mail_type'] = 'Mail type';
$txt['mail_type_default'] = '(PHP default)';
$txt['smtp_host'] = 'SMTP server';
$txt['smtp_port'] = 'SMTP port';
$txt['smtp_username'] = 'SMTP username';
$txt['smtp_password'] = 'SMTP password';

$txt['webmaster_email'] = 'Webmaster Email Address';
$txt['mail_from'] = 'Email "From" Address';

$txt['mail_queue'] = 'Enable Mail Queue';
$txt['mail_limit'] = 'Maximum emails to send per minute';
$txt['mail_limit_desc'] = '(Set to 0 to disable)';
$txt['mail_quantity'] = 'Maximum amount of emails to send per page load';

$txt['mailqueue_stats'] = 'Mail Queue Statistics';
$txt['mailqueue_oldest'] = 'Oldest Mail';
$txt['mailqueue_oldest_not_available'] = 'N/A';
$txt['mailqueue_size'] = 'Queue Length';

$txt['mailqueue_age'] = 'Age';
$txt['mailqueue_priority'] = 'Priority';
$txt['mailqueue_recipient'] = 'Recipient';
$txt['mailqueue_subject'] = 'Subject';
$txt['mailqueue_clear_list'] = 'Send Mail Queue Now';
$txt['mailqueue_send_these_items'] = 'Send these items';
$txt['mailqueue_no_items'] = 'The mail queue is currently empty';
$txt['mailqueue_clear_list_warning'] = 'Are you sure you wish to send the whole mail queue now? This will override any limits you have set.';

$txt['mq_day'] = '%1.1f Day';
$txt['mq_days'] = '%1.1f Days';
$txt['mq_hour'] = '%1.1f Hour';
$txt['mq_hours'] = '%1.1f Hours';
$txt['mq_minute'] = '%1$d Minute';
$txt['mq_minutes'] = '%1$d Minutes';
$txt['mq_second'] = '%1$d Second';
$txt['mq_seconds'] = '%1$d Seconds';

$txt['mq_mpriority_5'] = 'Very Low';
$txt['mq_mpriority_4'] = 'Low';
$txt['mq_mpriority_3'] = 'Normal';
$txt['mq_mpriority_2'] = 'High';
$txt['mq_mpriority_1'] = 'Very High';

$txt['templates_register'] = 'Registration Emails';
$txt['templates_register_admin'] = 'Registration Emails for Admin Use';
$txt['templates_account_changes'] = 'Account Changes';
$txt['templates_notify_content'] = 'Notifications of Content';
$txt['templates_notify_moderation'] = 'Notifications of Moderation Actions';
$txt['templates_group_membership'] = 'Group Membership';
$txt['templates_user_email'] = 'User Email Functions';
$txt['templates_paid_subs'] = 'Paid Subscriptions';
$txt['templates_moderator'] = 'Moderator Emails';

$txt['template_edit_template'] = 'Edit Template';
$txt['template_replacements'] = 'Replacements';
$txt['template_replacement_desc'] = 'Since the emails have different content for different situations, there are placeholders that should be used instead. The ones available for this email are shown below.';

$txt['template_email_desc'] = 'Description:';
$txt['template_email_subject'] = 'Email subject:';
$txt['template_email_body'] = 'Email body:';

// Don't translate the (%1$s) bits, they're used as placeholders. These are the standard ones.
$txt['template_repl_forumname'] = 'name of the forum (%1$s)';
$txt['template_repl_scripturl'] = 'the link to the front page of the site (%1$s)';
$txt['template_repl_themeurl'] = 'the URL of the theme site (%1$s)';
$txt['template_repl_imagesurl'] = 'the URL of the images folder for the theme currently in use (%1$s)';
$txt['template_repl_regards'] = 'the standard greeting (%1$s)';
// And these are the not so standard ones.
$txt['template_repl_username'] = 'the username (for login purposes) of the person receiving the email';
$txt['template_repl_realname'] = 'the display name of the person receiving the email';
$txt['template_repl_password'] = 'the password for the user receiving the email (usually on registration)';
$txt['template_repl_forgotpasswordlink'] = 'the link to the forgot-password page';
$txt['template_repl_idmember'] = 'the user id of the person receiving the email';
$txt['template_repl_emailsubject'] = 'the subject of the email that the user wants to send';
$txt['template_repl_emailbody'] = 'the body of the email that the user wants to send';
$txt['template_repl_sendername'] = 'the display name of the user sending the email';
$txt['template_repl_recpname'] = 'the display name of the user receiving the email';
$txt['template_repl_appyname'] = 'the display name of the user requesting group membership';
$txt['template_repl_groupname'] = 'the name of the group whose membership is being applied for';
$txt['template_repl_reason'] = 'the reason for wanting to join the group';
$txt['template_repl_modlink'] = 'the link to the relevant area of the moderation center';
$txt['template_repl_sendernamemanual'] = 'the name of the sender, user can enter it manually';
$txt['template_repl_recpnamemanual'] = 'the name of the recipient of the email, sender can enter it manually';
$txt['template_repl_topicsubject'] = 'the topic being discussed in the email';
$txt['template_repl_topiclink'] = 'the link to the topic discussed in the email';
$txt['template_repl_comment'] = 'a comment added by the sender of the email';
$txt['template_repl_refusereason'] = 'a comment added by the moderator to explain why the application was refused';
$txt['template_repl_remindlink'] = 'the link to the reset password area, including the reset code';
$txt['template_repl_ip'] = 'the IP address of the user causing the email to be sent';
$txt['template_repl_activationlink'] = 'the link to activate an account, including its activation code';
$txt['template_repl_activationcode'] = 'the code required to activate an account, provided in case of difficulties with email providers';
$txt['template_repl_activationlinkwithoutcode'] = 'the basic form of activation link without the code, for use with the above';
$txt['template_repl_subscrname'] = 'the name of a paid subscription';
$txt['template_repl_subname'] = 'the username of a user, whose paid subscription triggered the email';
$txt['template_repl_subuser'] = 'the display name of a user, whose paid subscription triggered the email';
$txt['template_repl_subemail'] = 'the email address of the user';
$txt['template_repl_price'] = 'the price of the paid subscription';
$txt['template_repl_profilelink'] = 'the link to the profile of the user';
$txt['template_repl_profilelinksubs'] = 'the link to the subscriptions area of the user\'s profile';
$txt['template_repl_date'] = 'the date when the subscription was changed';
$txt['template_repl_end_date'] = 'the date when the subscription expires';
$txt['template_repl_suberror'] = 'the error details from the payment system';
$txt['template_repl_refundname'] = 'the username of a user who received a refund on a paid subscription';
$txt['template_repl_refunduser'] = 'the display name of a user who received a refund';
$txt['template_repl_postername'] = 'the display name of the user who made the post in question';
$txt['template_repl_unsubscribelink'] = 'the link to unsubscribe from notifications';
$txt['template_repl_message'] = 'the message body';
$txt['template_repl_reportername'] = 'the display name of the user who reported the post';
$txt['template_repl_reportlink'] = 'the link to the moderation center where the report can be reviewed in full';
$txt['template_repl_approvallink'] = 'the link to the members approval area';
$txt['template_repl_coppalink'] = 'the link to the COPPA (parental consent) form for users to download and complete';
$txt['template_repl_subject'] = 'the subject of the message';
$txt['template_repl_replylink'] = 'the link to the reply-to-PM page';
