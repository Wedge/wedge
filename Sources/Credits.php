<?php
/**
 * Wedge
 *
 * The Who's Who of Wedge Wardens. Keeps track of all the credits, and displays them to everyone, or just within the admin panel.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Display the credits.
 *
 * - Uses the Who language file.
 * - Builds $context['credits'] to list the different teams behind application development, and the people who contributed.
 * - Adds $context['copyright']['mods'] where add-on developers can add their copyrights without touching the footer or anything else.
 * - Calls the 'place_credit' hook to enable modders to add to this page.
 *
 * @param bool $in_admin If calling from the admin panel, this should be true, to prevent loading the template that is normally loaded where this function would be called as a regular action (action=credits)
 */

function Credits($in_admin = false)
{
	global $context, $modSettings, $forum_copyright, $forum_version, $boardurl, $txt, $user_info;

	// Don't blink. Don't even blink. Blink and you're dead.
	loadLanguage('Who');

	$context['credits'] = array(
		array(
			'pretext' => $txt['credits_intro'],
			'title' => $txt['credits_team'],
			'groups' => array(
				array(
					'title' => $txt['credits_groups_ps'],
					'members' => array(
						'<b>Wedgeward</b> &ndash;
						<br>
						<div class="floatleft"><img src="http://wedge.org/about/pete.png" style="display: block; margin: 8px auto 4px">Peter Spicer</div>
						<div class="floatleft" style="margin-left: 8px"><img src="http://wedge.org/about/nao.png" style="display: block; margin: 8px auto 4px">Ren&eacute;-Gilles Deberdt</div>
						<br class="clear">',
					),
				),
				array(
					'title' => $txt['credits_groups_dev'],
					'members' => array(
						'<b>Nao &#23578;</b> (Ren&eacute;-Gilles Deberdt)',
						'<b>Arantor</b> (Peter Spicer)',
					),
				),
				array(
					'title' => $txt['credits_groups_consultants'],
					'members' => array(
						'Aaron (Aaron van Geffen)',
						'Bloc (Bjoern Kristiansen)',
						'Dragooon (Shitiz Garg)',
						'live627 (John Rayes)',
						'TE (Thorsten Eurich)',
						'[Unknown]',
					),
				),
				array(
					'title' => $txt['credits_groups_support'],
					'members' => array(
						'Dismal Shadow (Edwin Mendez)',
						'MultiformeIngegno (Lorenzo Raffio)',
					),
				),
			),
		),
		array(
			'title' => $txt['credits_smf_team'],
			'groups' => array(
				array(
					'title' => $txt['credits_groups_founder'],
					'members' => array(
						'Unknown W. "[Unknown]" Brackets',
					),
				),
				array(
					'title' => $txt['credits_groups_ps'],
					'members' => array(
					),
				),
				array(
					'title' => $txt['credits_groups_dev'],
					'members' => array(
						'Aaron (Aaron van Geffen)',
						'Antechinus',
						'Bloc (Bjoern Kristiansen)',
						'Compuart (Hendrik Jan Visser)',
						'Grudge' . ($user_info['is_admin'] ? ' (Matt Wolf)' : ''),
						'JayBachatero (Juan Hernandez)',
						'Nao &#23578; (Ren&eacute;-Gilles Deberdt)',
						'Norv',
						'Orstio (Theodore Hildebrandt)',
						'regularexpression (Karl Benson)',
						'[SiNaN] (Selman Eser)',
						'TE (Thorsten Eurich)',
						'Thantos (Michael Miller)',
						'winrules',
					),
				),
				array(
					'title' => $txt['credits_groups_consultants'],
					'members' => array(
					),
				),
				array(
					'title' => $txt['credits_groups_support'],
					'members' => array(
					),
				),
				array(
					'title' => $txt['credits_groups_customize'],
					'members' => array(
					),
				),
				array(
					'title' => $txt['credits_groups_docs'],
					'members' => array(
					),
				),
				array(
					'title' => $txt['credits_groups_marketing'],
					'members' => array(
					),
				),
				array(
					'title' => $txt['credits_groups_internationalizers'],
					'members' => array(
					),
				),
				array(
					'title' => $txt['credits_groups_servers'],
					'members' => array(
					),
				),
			),
		),
	);

	// Give the translators some credit for their hard work.
	if (!empty($txt['translation_credits']))
		$context['credits'][] = array(
			'title' => $txt['credits_groups_translation'],
			'groups' => array(
				array(
					'title' => $txt['credits_groups_translation'],
					'members' => $txt['translation_credits'],
				),
			),
		);

	$context['credits'][] = array(
		'title' => $txt['credits_special'],
		'posttext' => $txt['credits_anyone'],
		'groups' => array(
			array(
				'title' => $txt['credits_groups_beta'],
				'members' => array(
					$txt['credits_beta_message'],
				),
			),
			array(
				'title' => $txt['credits_groups_translators'],
				'members' => array(
					$txt['credits_translators_message'],
				),
			),
		),
	);

	$context['copyrights'] = array(
		'smf' => sprintf($forum_copyright, $forum_version),
		'images' => array(
			'flags' => '<a href="http://famfamfam.com/lab/icons/flags/">FamFamFam Flags</a> &copy; Mark James, 2005',
			'icons' => '<a href="http://www.everaldo.com/crystal/">Crystal Icons</a> &copy; Crystal Project, 2001-11',
		),
		'mods' => array(
		),
	);

	/*
		To Add-on Authors:
		You may add a copyright statement to this array for your add-ons.
		Do NOT edit the file, it could get messy. Simply call an add_hook('place_credit', 'my_function', 'my_source_file')
		where my_function will simply add your copyright to $context['copyrights']['mods'].
		You may also add credits at the end of the $context['credits'] array, following the same structure.

		Copyright statements should be in the form of a value only without a array key, i.e.:
			'Some Mod by Wedgeward &copy; 2010',
			$txt['some_mod_copyright'],
	*/

	call_hook('place_credit');

	if (!$in_admin)
	{
		loadTemplate('Who');
		loadSubTemplate('credits');
		$context['robot_no_index'] = true;
		$context['page_title'] = $txt['credits'];
	}
}

?>