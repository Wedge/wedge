<?php
/**
 * The Who's Who of Wedge Wardens. Keeps track of all the credits, and displays them to everyone, or just within the admin panel.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Display the credits.
 *
 * - Uses the Who language file.
 * - Builds $context['credits'] to list the different teams behind application development, and the people who contributed.
 * - Adds $context['copyright']['mods'] where plugin developers can add their copyrights without touching the footer or anything else.
 * - Calls the 'place_credit' hook to enable modders to add to this page.
 *
 * @param bool $in_admin If calling from the admin panel, this should be true, to prevent loading the template that is normally loaded where this function would be called as a regular action (action=credits)
 */

function Credits()
{
	global $context, $txt;

	// Don't blink. Don't even blink. Blink and you're dead.
	loadLanguage('Who');

	add_linktree($txt['site_credits'], '<URL>?action=credits');

	$context['site_credits'] = array();
	$query = wesql::query('
		SELECT id_member, real_name, id_group, additional_groups
		FROM {db_prefix}members
		WHERE id_group IN (1, 2)
			OR FIND_IN_SET(1, additional_groups)
			OR FIND_IN_SET(2, additional_groups)',
		array()
	);
	while ($row = wesql::fetch_assoc($query))
		$context['site_credits'][$row['id_group'] == 1 || (!empty($row['additional_groups']) && in_array(1, explode(',', $row['additional_groups']))) ? 'admins' : 'mods'][] = $row;
	wesql::free_result($query);

	$context['credits'] = array(
		array(
			'title' => $txt['credits_groups_ps'],
			'members' => array(
				'Ren&eacute;-Gilles Deberdt',
				'Peter Spicer',
			),
		),
		array(
			'title' => $txt['credits_groups_dev'],
			'members' => array(
				'<img src="http' . (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off' ? 's://secure.' : '://') . 'gravatar.com/avatar/0879c588019800e5349fe171d69e1c28" class="opaque left" style="margin: 8px 20px 8px -24px"><br><br>Ren&eacute;-Gilles Deberdt<br>(Nao &#23578;)<br class="clear">',
			),
		),
		array(
			'title' => $txt['credits_groups_contributors'],
			'members' => array(
				'Peter Spicer (Arantor)',
				'Shitiz Garg (Dragooon)',
				'John Rayes (live627)',
				'Thorsten Eurich (TE)',
			),
		),
		array(
			'title' => $txt['credits_special'],
			'members' => array(
				'Sven Rissmann (Pandos)',
				'Lorenzo Raffio (MultiformeIngegno)',
				'Aaron van Geffen (Aaron)',
				'[Unknown] &amp; Karl Benson',
				'Norodo',
			),
		),
	);

	// Give the translators some credit for their hard work.
	if (!empty($txt['translation_credits']))
		$context['credits'][] = array(
			'title' => $txt['credits_groups_language'],
			'members' => $txt['translation_credits'],
		);

	$context['credits'][] = array(
		'title' => $txt['credits_copyright'],
		'members' => array(
			sprintf(
				$txt['credits_wedge'],
				'René-Gilles Deberdt',
				'http://wedge.org/license/',
				2010
			),
			sprintf(
				$txt['credits_aeme'],
				'Nao &#23578;',
				'Dragooon',
				'Karl Benson',
				'http://aeva.noisen.com/'
			),
		),
	);

	$context['plugin_credits'] = array();

	/*
		To Plugin Authors:
		The best way to credit your plugins in a visible, yet unobtrusive way, is to add a copyright statement to this array.
		Do NOT edit the file, it could get messy. Simply call an add_hook('place_credit', 'my_function', 'my_source_file'), with:
		function my_function() {
			global $context, $txt;
			// e.g. '<a href="link">Plugin42</a> is &copy; Nao and Wedge contributors 2010, MIT license.'
			$context['plugin_credits'][] = $txt['copyright_string_for_my_plugin'];
		}
	*/

	call_hook('place_credit');

	if (!empty($context['plugin_credits']))
		$context['credits']['mods'] = array(
			'title' => $txt['credits_plugins'],
			'members' => $context['plugin_credits'],
		);

	loadTemplate('Who');
	wetem::load('credits');
	$context['robot_no_index'] = true;
	$context['page_title'] = $txt['credits_site'];
}
