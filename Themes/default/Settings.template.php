<?php
/**
 * Displays user-specific theme options.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

function template_settings()
{
	global $context, $txt;

	$context['theme_settings'] = array(
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
			'id' => 'enable_news',
			'label' => $txt['enable_random_news'],
		),
	'',
		array(
			'id' => 'show_stats_index',
			'label' => $txt['show_stats_index'],
		),
		array(
			'id' => 'show_latest_member',
			'label' => $txt['latest_members'],
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
	);

	// !!! Must find a better way of doing this.
	call_hook('theme_settings');
}
