<?php
// Version: 2.0; ManagePosts

// Post Settings
$txt['removeNestedQuotes'] = 'Remove nested quotes when quoting';
$txt['enableEmbeddedFlash'] = 'Allow Flash embedding in posts';
$txt['enableEmbeddedFlash_warning'] = 'May be a security risk!';
$txt['additional_options_collapsable'] = 'Enable collapsible additional post options';

$txt['max_messageLength'] = 'Maximum allowed post size';
$txt['max_messageLength_zero'] = '0 for no max.';
$txt['topicSummaryPosts'] = 'Posts to show on topic summary';

$txt['max_image_width'] = 'Max width of posted pictures (0 = disable)';
$txt['max_image_height'] = 'Max height of posted pictures (0 = disable)';

$txt['spamWaitTime'] = 'Time required between posts from the same IP';
$txt['edit_wait_time'] = 'Courtesy edit wait time';
$txt['edit_disable_time'] = 'Maximum time after posting to allow edit';
$txt['edit_disable_time_zero'] = '0 to disable';
$txt['allow_non_mod_edit'] = 'Allow non-moderators to edit moderator edits?';

// Topic Settings
$txt['enableParticipation'] = 'Enable participation icons';

$txt['correctExclamations'] = 'Correct too many exclamations in the subject';
$txt['correctShouting'] = 'Maximum % of capital letters in the subject';

$txt['oldTopicDays'] = 'Time before topic is warned as old on reply';
$txt['oldTopicDays_zero'] = '0 to disable';
$txt['defaultMaxTopics'] = 'Number of topics per page in the message index';
$txt['defaultMaxMessages'] = 'Number of posts per page in a topic page';

$txt['enableAllMessages'] = 'Max topic size to show &quot;All&quot; posts';
$txt['enableAllMessages_zero'] = '0 to never show &quot;All&quot;';
$txt['disableCustomPerPage'] = 'Disable user-defined topic/message count per page';
$txt['enablePreviousNext'] = 'Enable previous/next topic links';

$txt['ignoreMoveVsNew'] = 'When moving topics, allow moving to any board by default';

// Bulletin Board Code
$txt['enableBBC'] = 'Enable bulletin board code (BBC)';
$txt['enablePostHTML'] = 'Enable <em>basic</em> HTML in posts';
$txt['autoLinkUrls'] = 'Automatically link posted URLs';

$txt['disabledBBC'] = 'Enabled BBC tags';
$txt['bbcTagsToUse'] = 'Enabled BBC tags';
$txt['bbcTagsToUse_select'] = 'Select the tags allowed to be used';
$txt['bbcTagsToUse_select_all'] = 'Select all tags';

// Post Editor
$txt['disable_wysiwyg'] = 'Disable WYSIWYG editor';
$txt['editorSizes'] = 'Font sizes to list in the editor';
$txt['editorSizes_subtext'] = 'One per line';
$txt['editorFonts'] = 'Fonts to list in the editor';
$txt['editorFonts_subtext'] = 'While you can list any font here, and it will be shown in the main editor in the "Font Face" dropdown, it will only work if the font is installed on your users\' computers.';

// Censored Words
$txt['admin_censored_where'] = 'Put the word to be censored on the left, and what to change it to on the right.';
$txt['censor_whole_words'] = 'Check only whole words';
$txt['censor_case'] = 'Ignore case when censoring';
$txt['allow_no_censored'] = 'Allow users to turn off word censoring';

$txt['censor_test'] = 'Test Censored Words';
$txt['censor_test_save'] = 'Test';

// Draft Settings
$txt['masterSavePostDrafts'] = 'Enable saving of post drafts';
$txt['draftsave_subnote'] = 'Note that the user must still have permission in the Permissions area.';
$txt['masterAutoSavePostDrafts'] = 'Enable automatic saving of post drafts';
$txt['draftautosave_subnote'] = 'This does not override the above option, merely extending it. Also, user must have permission.';
$txt['masterAutoSaveDraftsDelay'] = 'How often should posts be autosaved?';
$txt['pruneSaveDrafts'] = 'Prune drafts after how many days?';

// Merging
$txt['merge_post_header'] = 'Merging double posts';
$txt['merge_post_auto'] = 'Merge double posts automatically';
$txt['merge_post_auto_time'] = 'Delay after which posts are no longer merged automatically.';
$txt['merge_post_auto_time_subtext'] = '(In seconds; set to 0 to always merge)';

$txt['merge_post_admin_double_post'] = 'Merge double posts sent by administrators';

$txt['merge_post_no_time'] = 'Don\'t show the older post\'s date before the separator';
$txt['merge_post_no_sep'] = 'Show neither the separator, nor the older post\'s date.';
$txt['merge_post_separator'] = 'Separator between merged posts (enable the setting above.)';
$txt['merge_post_separator_subtext'] = 'You may use BBCode. You can get the older post\'s date by using the <strong>$date</strong> variable in the text field.<br>Default is [size=1]$date[/size][hr][br]';
$txt['merge_post_custom_separator'] = 'Use a custom separator between merged posts.';
$txt['merge_post_custom_separator_subtext'] = 'The settings above will be disabled.';

$txt['merge_post_ignore_length'] = 'Ignore posts\' maximum length';
