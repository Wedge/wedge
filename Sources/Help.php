<?php
/**********************************************************************************
* Help.php                                                                        *
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

/*	This file has the important job of taking care of help messages and the
	help center.  It does this with two simple functions:

	void ShowHelp()
		- loads information needed for the help section.
		- accesed by ?action=help.
		- uses the Help template and Manual language file.
		- calls the appropriate sub template depending on the page being viewed.

	void ShowAdminHelp()
		- shows a popup for administrative or user help.
		- uses the help parameter to decide what string to display and where
		  to get the string from. ($helptxt or $txt?)
		- loads the ManagePermissions language file if the help starts with
		  permissionhelp.
		- uses the Help template, popup sub template, no layers.
		- accessed via ?action=helpadmin;help=??.
*/

// Redirect to the user help ;).
function ShowHelp()
{
	global $settings, $user_info, $language, $context, $txt, $sourcedir, $options, $scripturl;

	loadTemplate('Help');
	loadLanguage('Manual');

	$manual_areas = array(
		'getting_started' => array(
			'title' => $txt['manual_category_getting_started'],
			'description' => '',
			'areas' => array(
				'introduction' => array(
					'label' => $txt['manual_section_intro'],
					'template' => 'manual_intro',
				),
				'main_menu' => array(
					'label' => $txt['manual_section_main_menu'],
					'template' => 'manual_main_menu',
				),
				'board_index' => array(
					'label' => $txt['manual_section_board_index'],
					'template' => 'manual_board_index',
				),
				'message_view' => array(
					'label' => $txt['manual_section_message_view'],
					'template' => 'manual_message_view',
				),
				'topic_view' => array(
					'label' => $txt['manual_section_topic_view'],
					'template' => 'manual_topic_view',
				),
			),
		),
		'registering' => array(
			'title' => $txt['manual_category_registering'],
			'description' => '',
			'areas' => array(
				'when_how' => array(
					'label' => $txt['manual_section_when_how_register'],
					'template' => 'manual_when_how_register',
				),
				'registration_screen' => array(
					'label' => $txt['manual_section_registration_screen'],
					'template' => 'manual_registration_screen',
				),
				'activating_account' => array(
					'label' => $txt['manual_section_activating_account'],
					'template' => 'manual_activating_account',
				),
				'logging_in' => array(
					'label' => $txt['manual_section_logging_in_out'],
					'template' => 'manual_logging_in_out',
				),
				'password_reminders' => array(
					'label' => $txt['manual_section_password_reminders'],
					'template' => 'manual_password_reminders',
				),
			),
		),
		'profile_features' => array(
			'title' => $txt['manual_category_profile_features'],
			'description' => '',
			'areas' => array(
				'profile_info' => array(
					'label' => $txt['manual_section_profile_info'],
					'template' => 'manual_profile_info_summary',
					'description' => $txt['manual_entry_profile_info_desc'],
					'subsections' => array(
						'summary' => array($txt['manual_entry_profile_info_summary'], 'template' => 'manual_profile_info_summary'),
						'posts' => array($txt['manual_entry_profile_info_posts'], 'template' => 'manual_profile_info_posts'),
						'stats' => array($txt['manual_entry_profile_info_stats'], 'template' => 'manual_profile_info_stats'),
					),
				),
				'modify_profile' => array(
					'label' => $txt['manual_section_modify_profile'],
					'template' => 'manual_modify_profile_settings',
					'description' => $txt['manual_entry_modify_profile_desc'],
					'subsections' => array(
						'settings' => array($txt['manual_entry_modify_profile_settings'], 'template' => 'manual_modify_profile_settings'),
						'forum' => array($txt['manual_entry_modify_profile_forum'], 'template' => 'manual_modify_profile_forum'),
						'look' => array($txt['manual_entry_modify_profile_look'], 'template' => 'manual_modify_profile_look'),
						'auth' => array($txt['manual_entry_modify_profile_auth'], 'template' => 'manual_modify_profile_auth'),
						'notify' => array($txt['manual_entry_modify_profile_notify'], 'template' => 'manual_modify_profile_notify'),
						'pm' => array($txt['manual_entry_modify_profile_pm'], 'template' => 'manual_modify_profile_pm'),
						'buddies' => array($txt['manual_entry_modify_profile_buddies'], 'template' => 'manual_modify_profile_buddies'),
						'groups' => array($txt['manual_entry_modify_profile_groups'], 'template' => 'manual_modify_profile_groups'),
					),
				),
				'actions' => array(
					'label' => $txt['manual_section_profile_actions'],
					'template' => 'manual_profile_actions_subscriptions',
					'description' => $txt['manual_entry_modify_profile_desc'],
					'subsections' => array(
						'subscriptions' => array($txt['manual_entry_profile_actions_subscriptions'], 'template' => 'manual_profile_actions_subscriptions'),
						'delete' => array($txt['manual_entry_profile_actions_delete'], 'template' => 'manual_profile_actions_delete'),
					),
				),
			),
		),
		'posting_basics' => array(
			'title' => $txt['manual_category_posting_basics'],
			'description' => '',
			'areas' => array(
				/*'posting_screen' => array(
					'label' => $txt['manual_section_posting_screen'],
					'template' => 'manual_posting_screen',
				),*/
				'posting_topics' => array(
					'label' => $txt['manual_section_posting_topics'],
					'template' => 'manual_posting_topics',
				),
				/*'quoting_posts' => array(
					'label' => $txt['manual_section_quoting_posts'],
					'template' => 'manual_quoting_posts',
				),
				'modifying_posts' => array(
					'label' => $txt['manual_section_modifying_posts'],
					'template' => 'manual_modifying_posts',
				),*/
				'smileys' => array(
					'label' => $txt['manual_section_smileys'],
					'template' => 'manual_smileys',
				),
				'bbcode' => array(
					'label' => $txt['manual_section_bbcode'],
					'template' => 'manual_bbcode',
				),
				/*'wysiwyg' => array(
					'label' => $txt['manual_section_wysiwyg'],
					'template' => 'manual_wysiwyg',
				),*/
			),
		),
		'personal_messages' => array(
			'title' => $txt['manual_category_personal_messages'],
			'description' => '',
			'areas' => array(
				'messages' => array(
					'label' => $txt['manual_section_pm_messages'],
					'template' => 'manual_pm_messages',
				),
				/*'actions' => array(
					'label' => $txt['manual_section_pm_actions'],
					'template' => 'manual_pm_actions',
				),
				'preferences' => array(
					'label' => $txt['manual_section_pm_preferences'],
					'template' => 'manual_pm_preferences',
				),*/
			),
		),
		'forum_tools' => array(
			'title' => $txt['manual_category_forum_tools'],
			'description' => '',
			'areas' => array(
				'searching' => array(
					'label' => $txt['manual_section_searching'],
					'template' => 'manual_searching',
				),
				/*'memberlist' => array(
					'label' => $txt['manual_section_memberlist'],
					'template' => 'manual_memberlist',
				),
				'calendar' => array(
					'label' => $txt['manual_section_calendar'],
					'template' => 'manual_calendar',
				),*/
			),
		),
	);

	// Set a few options for the menu.
	$menu_options = array(
		'disable_url_session_check' => true,
	);

	require_once($sourcedir . '/Subs-Menu.php');
	$manual_area_data = createMenu($manual_areas, $menu_options);
	unset($manual_areas);

	// Make a note of the Unique ID for this menu.
	$context['manual_menu_id'] = $context['max_menu_id'];
	$context['manual_menu_name'] = 'menu_data_' . $context['manual_menu_id'];

	// Get the selected item.
	$context['manual_area_data'] = $manual_area_data;
	$context['menu_item_selected'] = $manual_area_data['current_area'];

	// Set a title and description for the tab strip if subsections are present.
	if (isset($context['manual_area_data']['subsections']))
		$context[$context['manual_menu_name']]['tab_data'] = array(
			'title' => $manual_area_data['label'],
			'description' => isset($manual_area_data['description']) ? $manual_area_data['description'] : '',
		);

	// Bring it on!
	$context['sub_template'] = isset($manual_area_data['current_subsection'], $manual_area_data['subsections'][$manual_area_data['current_subsection']]['template']) ? $manual_area_data['subsections'][$manual_area_data['current_subsection']]['template'] : $manual_area_data['template'];
	$context['page_title'] = $manual_area_data['label'] . ' - ' . $txt['manual_smf_user_help'];

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $scripturl . '?action=help',
		'name' => $txt['help'],
	);
	if (isset($manual_area_data['current_area']) && $manual_area_data['current_area'] != 'index')
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=help;area=' . $manual_area_data['current_area'],
			'name' => $manual_area_data['label'],
		);
	if (!empty($manual_area_data['current_subsection']) && $manual_area_data['subsections'][$manual_area_data['current_subsection']][0] != $manual_area_data['label'])
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=help;area=' . $manual_area_data['current_area'] . ';sa=' . $manual_area_data['current_subsection'],
			'name' => $manual_area_data['subsections'][$manual_area_data['current_subsection']][0],
		);

	// We actually need a special style sheet for help ;)
	$context['template_layers'][] = 'manual';

	// The smiley info page needs some cheesy information.
	if ($manual_area_data['current_area'] == 'smileys')
		ShowSmileyHelp();
}

function ShowSmileyHelp()
{
	global $context, $smcFunc, $modSettings, $user_info;

	// Load the smileys in reverse order by length so they don't get parsed wrong.
	if (($temp = cache_get_data('parsing_smileys', 480)) == null)
	{
		$result = $smcFunc['db_query']('', '
			SELECT code, filename, description
			FROM {db_prefix}smileys',
			array(
			)
		);
		$smileysfrom = array();
		$smileysto = array();
		$smileysdescs = array();
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			$smileysfrom[] = $row['code'];
			$smileysto[] = $row['filename'];
			$smileysdescs[] = $row['description'];
		}
		$smcFunc['db_free_result']($result);

		cache_put_data('parsing_smileys', array($smileysfrom, $smileysto, $smileysdescs), 480);
	}
	else
		list ($smileysfrom, $smileysto, $smileysdescs) = $temp;

	// So, let's get this into $context.
	$context['smileys'] = array();
	for ($i = 0; $i < count($smileysfrom); $i ++)
	{
		$smileyCode = '<img src="' . $modSettings['smileys_url'] . '/' . $user_info['smiley_set'] . '/' . $smileysto[$i] . '" alt="' . strtr(htmlspecialchars($smileysfrom[$i]), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')). '" title="' . strtr(htmlspecialchars($smileysdescs[$i]), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')) . '" class="smiley" />';

		$context['smileys'][] = array(
			'name' => $smileysdescs[$i],
			'to' => $smileyCode,
			'from' => $smileysfrom[$i],
		);
	}
}

// Show some of the more detailed help to give the admin an idea...
function ShowAdminHelp()
{
	global $txt, $helptxt, $context, $scripturl;

	if (!isset($_GET['help']) || !is_string($_GET['help']))
		fatal_lang_error('no_access', false);

	if (!isset($helptxt))
		$helptxt = array();

	// Load the admin help language file and template.
	loadLanguage('Help');

	// Permission specific help?
	if (isset($_GET['help']) && substr($_GET['help'], 0, 14) == 'permissionhelp')
		loadLanguage('ManagePermissions');

	loadTemplate('Help');

	// Set the page title to something relevant.
	$context['page_title'] = $context['forum_name'] . ' - ' . $txt['help'];

	// Don't show any template layers, just the popup sub template.
	$context['template_layers'] = array();
	$context['sub_template'] = 'popup';

	// What help string should be used?
	if (isset($helptxt[$_GET['help']]))
		$context['help_text'] = $helptxt[$_GET['help']];
	elseif (isset($txt[$_GET['help']]))
		$context['help_text'] = $txt[$_GET['help']];
	else
		$context['help_text'] = $_GET['help'];

	// Does this text contain a link that we should fill in?
	if (preg_match('~%([0-9]+\$)?s\?~', $context['help_text'], $match))
		$context['help_text'] = sprintf($context['help_text'], $scripturl, $context['session_id'], $context['session_var']);
}

?>