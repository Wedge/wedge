<?php
/**
 * The Who's Who of Wedge Wardens. Keeps track of all the credits, and displays them to everyone, or just within the admin panel.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
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
				'<div style="float: left; text-align: center"><img src="http://wedge.org/about/nao.png" style="margin: 8px auto 4px"><br class="clear">Ren&eacute;-Gilles<br>Deberdt</div>
				<div style="float: left; text-align: center; margin-left: 8px"><img src="http://wedge.org/about/pete.png" style="margin: 8px auto 4px"><br class="clear">Peter Spicer</div>
				<div class="clear"></div>',
			),
		),
		array(
			'title' => $txt['credits_groups_dev'],
			'members' => array(
				'<b>Nao &#23578;</b> (Ren&eacute;-Gilles Deberdt)',
			),
		),
		array(
			'title' => $txt['credits_groups_contributors'],
			'members' => array(
				'Arantor (Peter Spicer)',
				'Aaron (Aaron van Geffen)',
				'Dragooon (Shitiz Garg)',
				'live627 (John Rayes)',
				'TE (Thorsten Eurich)',
			),
		),
		array(
			'title' => $txt['credits_special'],
			'members' => array(
				'Pandos (Sven Rissmann)',
				'MultiformeIngegno (Lorenzo Raffio)',
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
			$txt['credits_smf2'],
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
