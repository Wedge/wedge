<?php
// Version: 2.0; ManageInfractions

$txt['infractions_desc'] = 'From here you can manage the infractions that your staff members can issue.';
$txt['infractionlevels_desc'] = 'From here you can manage pre-set levels of infraction points and punishments for them.';
$txt['infractionsettings_desc'] = 'From here you can change key settings for how infractions behave.';

$txt['revoke_own_issued'] = 'Staff can remove infractions they issued';
$txt['revoke_any_issued'] = 'Staff groups who can remove any infractions issued';
$txt['no_warn_groups'] = 'Groups who cannot receive a warning';

$txt['setting_warning_show'] = 'Users who can see warning status';
$txt['setting_warning_show_subtext'] = 'Determines who can see the warning level of users on the forum.';
$txt['setting_warning_show_none'] = 'No one';
$txt['setting_warning_show_mods'] = 'Moderators only';
$txt['setting_warning_show_user'] = 'Moderators and warned users';
$txt['setting_warning_show_all'] = 'All users';

$txt['infractionlevels_extra'] = 'The following are the different punishments that can be applied to user accounts. As users accumulate infractions, they will receive points - as they do so, you can use the following to set automatic penalties that apply just based on total points. The idea is that a given infraction may carry a given punishment, but users who consistently receive small punishments can also earn themselves a larger one.';
$txt['infraction_no_avatar'] = 'User\'s avatar is hidden';
$txt['infraction_no_sig'] = 'User\'s signature is hidden';
$txt['infraction_scramble'] = 'User\'s posts are scrambled';
$txt['infraction_scramble_help'] = 'The user\'s posts are shown but all the words are scrambled: the first and last letters are left in place but all the other letters are in the wrong order. It\'s generally still readable but likely to be ignored by users.';
$txt['infraction_disemvowel'] = 'User\'s posts are disemvowelled';
$txt['infraction_disemvowel_help'] = 'The user\'s posts are shown, but missing vowels. The effect is that posts are still readable with effort, but that most users will simply ignore the posts.';
$txt['infraction_moderate'] = 'User\'s posts will require moderator approval';
$txt['infraction_post_ban'] = 'User will not be permitted to post';
$txt['infraction_pm_ban'] = 'User will not be permitted to send personal messages';
$txt['infraction_soft_ban'] = 'The user will be soft-banned';
$txt['infraction_hard_ban'] = 'The user will be hard-banned';

$txt['enact_infraction'] = 'Infraction to be applied:';
$txt['points_infraction'] = 'When the user has accumulated:';

$txt['enabled_infraction'] = 'Enabled?';

$txt['preset_infractions'] = 'Pre-Set Infractions';
$txt['preset_infractions_desc'] = 'Pre-set infractions are those defined by the administrator to be issued quickly and easily, sort of like defaults or templates. You may decide to let certain moderator groups only issue from this list, or you can give them additional power below.';
$txt['adhoc_infractions'] = 'Ad-Hoc Infractions';
$txt['adhoc_infractions_desc'] = 'You may be able to plan for the types of incident that might occur and give pre-set infractions for your moderators to issue but there are going to be times that moderators may just have to go "off-script". You can configure what the rules for those are here.';

$txt['add_infraction'] = 'Add New Infraction';
$txt['delete_infraction_confirm'] = 'Are you sure you wish to remove this infraction?';

$txt['infraction_name'] = 'Infraction Name';
$txt['infraction_points'] = 'Points';
$txt['infraction_duration'] = 'Duration';
$txt['infraction_sanctions'] = 'Punishments';
$txt['infraction_issuers'] = 'Can be issued by';
$txt['infraction_no_punishments'] = 'None';
$txt['no_infractions'] = 'There aren\'t any infractions set up right now.';

$txt['infraction_d'] = array(
	1 => '%1$s day',
	'n' => '%1$s days',
);
$txt['infraction_w'] = array(
	1 => '%1$s week',
	'n' => '%1$s weeks',
);
$txt['infraction_m'] = array(
	1 => '%1$s month',
	'n' => '%1$s months',
);
$txt['infraction_y'] = array(
	1 => '%1$s year',
	'n' => '%1$s years',
);
$txt['infraction_i'] = 'Indefinitely';

$txt['infraction_duration_types'] = array(
	'd' => 'Day(s)',
	'w' => 'Week(s)',
	'm' => 'Month(s)',
	'y' => 'Year(s)',
	'i' => 'Indefinitely',
);

$txt['can_issue_adhoc'] = 'Can issue ad-hoc infractions';
$txt['max_points'] = 'Maximum points per infraction:';
$txt['max_infractions_day'] = 'Maximum infractions to a single member per day:';
$txt['punishments_issuable'] = 'Punishments that can be issued:';

$txt['add_preset_infraction'] = 'Add Pre-set Infraction';
$txt['edit_preset_infraction'] = 'Edit Pre-set Infraction';
$txt['infraction_name_desc'] = 'This is an internal name for the infraction for your reference.';
$txt['for_the_duration'] = 'For the duration of the infraction';
$txt['issued_by_adhoc'] = 'Remember: groups who can issue ad-hoc warnings will be able to customize what is set in this infraction. Groups who cannot issue ad-hoc warnings will only have the options you give them here.';
$txt['notification_text'] = 'Notification text to use';
$txt['notification_text_desc'] = 'If a staff member is not empowered to send ad-hoc infractions, any notification will use the wording set here.';
$txt['notification_use_none'] = 'Don\'t send a message';
$txt['notification_use_custom'] = 'Use custom wording (below)';
$txt['notification_subject'] = 'Subject:';
$txt['notification_body'] = 'Message:';
$txt['notification_body_note'] = '{PUNISHMENTS} will be automatically replaced with wording to describe the points and punishments given to the user from this infraction.';
$txt['notification_body_message'] = '{MESSAGE} will be automatically replaced with a link to the message someone is being warned for.';

$txt['tpl_infraction_bad_avatar'] = array(
	'desc' => 'Bad Avatar',
	'subject' => 'You have received an infraction about your avatar',
	'body' => 'You have received an infraction about your avatar being inappropriate for this forum.

It may be appropriate because:
* it contains rapidly moving or flashing images
* it contains inappropriate text
* it contains inappropriate pictures

{PUNISHMENTS}

Please note, any incidents in future may cause further infractions on your account and may limit what you may do on this site.',
);
$txt['tpl_infraction_bad_sig'] = array(
	'desc' => 'Bad Signature',
	'subject' => 'You have received an infraction about your signature',
	'body' => 'You have received an infraction about your signature being inappropriate for this forum.

It may be inappropriate because:
* it is very large and distracting for other members
* it contains too many links to external sites
* it contains content that is considered inappropriate here

{PUNISHMENTS}

Please note, any incidents in future may cause further infractions on your account and may limit what you may do on this site.',
);
$txt['tpl_infraction_bad_language'] = array(
	'desc' => 'Bad Language',
	'subject' => 'You have received an infraction about your language',
	'body' => 'You have received an infraction about your language on this forum.

We do not permit use of profanity or back-attitude to staff or other members.

{PUNISHMENTS}

Please note, any incidents in future may cause further infractions on your account and may limit what you may do on this site.',
);
$txt['tpl_infraction_spam'] = array(
	'desc' => 'Spam',
	'subject' => 'You have received an infraction for spamming',
	'body' => 'You have received an infraction for spam messages on this forum.

Spam is a very widespread problem, it regularly requires a lot of effort to keep it at bay.
As such we do not appreciate you spamming this site.

{PUNISHMENTS}

Any future incidents may cause further infractions on your acconut.',
);

$txt['no_punishment'] = 'No action has been taken on this occasion but this has has been logged on your account.';
$txt['received_punishments'] = array(
	1 => 'On this occasion, you have received the following punishment:',
	'n' => 'On this occasion, you have received the following punishments:',
);
$txt['punishments_will_expire'] = array(
	1 => 'This punishment will last on your account until {EXPIRY}.',
	'n' => 'These punishments will last on your account until {EXPIRY}.',
);
$txt['punishments_no_expire'] = array(
	1 => 'This punishment is not set to expire.',
	'n' => 'These punishments are not set to expire.',
);

$txt['pun_infraction_no_avatar'] = 'Your avatar will be hidden';
$txt['pun_infraction_no_sig'] = 'Your signature will be hidden';
$txt['pun_infraction_scramble'] = 'Your posts will be scrambled';
$txt['pun_infraction_disemvowel'] = 'Your posts will be disemvowelled';
$txt['pun_infraction_moderate'] = 'Your posts will require moderator approval';
$txt['pun_infraction_post_ban'] = 'You are not allowed to post';
$txt['pun_infraction_pm_ban'] = 'You are not allowed to send personal messages';
$txt['pun_infraction_soft_ban'] = 'Some aspects of the forum will be unavailable to you';
$txt['pun_infraction_hard_ban'] = 'You are banned from the forum';
$txt['pun_points'] = array(
	1 => 'You have accumulated an infraction point, bringing your total to {POINTS}. This may lead to further punishments in future.',
	'n' => 'You have accumulated %1$s infraction points, bringing your total to {POINTS}. This may lead to further punishments in future.',
);
$txt['pun_because_message'] = 'You received this infraction because of your post: {LINK}';

$txt['error_no_name_given'] = 'Each infraction must have a name for reference purposes.';
$txt['error_no_text'] = 'You selected to add a custom warning notification but did not fill any text in.';
$txt['error_invalid_duration'] = 'You did not specify how long the warning should last for.';
