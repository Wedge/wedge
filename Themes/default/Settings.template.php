<?php
/**
 * Wedge
 *
 * Displays user-specific theme options.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_options()
{
	global $context, $txt;

	$context['theme_options'] = array(
		array(
			'id' => 'show_board_desc',
			'label' => $txt['board_desc_inside'],
			'default' => true,
		),
		array(
			'id' => 'show_children',
			'label' => $txt['show_children'],
			'default' => true,
		),
		array(
			'id' => 'use_sidebar_menu',
			'label' => $txt['use_sidebar_menu'],
			'default' => true,
		),
		array(
			'id' => 'show_no_avatars',
			'label' => $txt['show_no_avatars'],
			'default' => true,
		),
		array(
			'id' => 'show_no_signatures',
			'label' => $txt['show_no_signatures'],
			'default' => true,
		),
		array(
			'id' => 'show_no_censored',
			'label' => $txt['show_no_censored'],
			'default' => true,
		),
		array(
			'id' => 'return_to_post',
			'label' => $txt['return_to_post'],
			'default' => true,
		),
		array(
			'id' => 'no_new_reply_warning',
			'label' => $txt['no_new_reply_warning'],
			'default' => true,
		),
		array(
			'id' => 'view_newest_first',
			'label' => $txt['recent_posts_at_top'],
			'default' => true,
		),
		array(
			'id' => 'view_newest_pm_first',
			'label' => $txt['recent_pms_at_top'],
			'default' => true,
		),
		array(
			'id' => 'posts_apply_ignore_list',
			'label' => $txt['posts_apply_ignore_list'],
			'default' => false,
		),
		array(
			'id' => 'wysiwyg_default',
			'label' => $txt['wysiwyg_default'],
			'default' => false,
		),
		array(
			'id' => 'popup_messages',
			'label' => $txt['popup_messages'],
			'default' => true,
		),
		array(
			'id' => 'copy_to_outbox',
			'label' => $txt['copy_to_outbox'],
			'default' => true,
		),
		array(
			'id' => 'pm_remove_inbox_label',
			'label' => $txt['pm_remove_inbox_label'],
			'default' => true,
		),
		array(
			'id' => 'auto_notify',
			'label' => $txt['auto_notify'],
			'default' => true,
		),
		array(
			'id' => 'topics_per_page',
			'label' => $txt['topics_per_page'],
			'options' => array(
				0 => $txt['per_page_default'],
				5 => 5,
				10 => 10,
				25 => 25,
				50 => 50,
			),
			'default' => true,
		),
		array(
			'id' => 'messages_per_page',
			'label' => $txt['messages_per_page'],
			'options' => array(
				0 => $txt['per_page_default'],
				5 => 5,
				10 => 10,
				25 => 25,
				50 => 50,
			),
			'default' => true,
		),
		array(
			'id' => 'display_quick_reply',
			'label' => $txt['display_quick_reply'],
			'options' => array(
				0 => $txt['display_quick_reply1'],
				1 => $txt['display_quick_reply2'],
				2 => $txt['display_quick_reply3']
			),
			'default' => true,
		),
		array(
			'id' => 'display_quick_mod',
			'label' => $txt['display_quick_mod'],
			'options' => array(
				0 => $txt['display_quick_mod_none'],
				1 => $txt['display_quick_mod_check'],
				2 => $txt['display_quick_mod_image'],
			),
			'default' => true,
		),
	);

	// !!! Must find a better way of doing this.
	call_hook('theme_options');
}

function template_settings()
{
	global $context, $txt;

	$context['theme_settings'] = array(
		array(
			'id' => 'header_logo_url',
			'label' => $txt['header_logo_url'],
			'description' => $txt['header_logo_url_desc'],
			'type' => 'text',
		),
		array(
			'id' => 'site_slogan',
			'label' => $txt['site_slogan'],
			'description' => $txt['site_slogan_desc'],
			'type' => 'text',
		),
		array(
			'id' => 'smiley_sets_default',
			'label' => $txt['smileys_default_set_for_theme'],
			'options' => $context['smiley_sets'],
			'type' => 'text',
		),
		array(
			'id' => 'forum_width',
			'label' => $txt['forum_width'],
			'description' => $txt['forum_width_desc'],
			'type' => 'text',
			'size' => 8,
		),
	'',
		array(
			'id' => 'quick_login',
			'label' => $txt['enable_quick_login'],
			'description' => $txt['enable_quick_login_desc'],
		),
	'',
		array(
			'id' => 'enable_news',
			'label' => $txt['enable_random_news'],
		),
	'',
		array(
			'id' => 'show_newsfader',
			'label' => $txt['news_fader'],
		),
		array(
			'id' => 'newsfader_time',
			'label' => $txt['admin_fader_delay'],
			'type' => 'number',
		),
		array(
			'id' => 'show_stats_index',
			'label' => $txt['show_stats_index'],
		),
		array(
			'id' => 'show_latest_member',
			'label' => $txt['latest_members'],
		),
		array(
			'id' => 'show_group_key',
			'label' => $txt['show_group_key'],
		),
	'',
		array(
			'id' => 'show_modify',
			'label' => $txt['last_modification'],
		),
		array(
			'id' => 'show_profile_buttons',
			'label' => $txt['show_view_profile_button'],
		),
		array(
			'id' => 'show_user_images',
			'label' => $txt['user_avatars'],
		),
		array(
			'id' => 'show_blurb',
			'label' => $txt['user_text'],
		),
		array(
			'id' => 'show_gender',
			'label' => $txt['gender_images'],
		),
		array(
			'id' => 'hide_post_group',
			'label' => $txt['hide_post_group'],
			'description' => $txt['hide_post_group_desc'],
		),
	);

	// !!! Must find a better way of doing this.
	call_hook('theme_settings');
}

?>