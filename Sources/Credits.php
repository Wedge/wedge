<?php
/**********************************************************************************
* Credits.php                                                                     *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC4                                         *
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

/*	This file concerns the application credits.

	void Credits(bool in_admin)
		- prepares credit and copyright information for the credits page or the admin page
		- if parameter is true the it will not load the sub template nor the template file

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
						'<b>Nao &#23578;</b> (Ren&eacute;-Gilles Deberdt)',
						'<b>Arantor</b> (Peter Spicer)',
					),
				),
				array(
					'title' => $txt['credits_groups_dev'],
					'members' => array(
						'<b>Arantor</b> (Peter Spicer)',
						'<b>Nao &#23578;</b> (Ren&eacute;-Gilles Deberdt)',
					),
				),
			),
		),
		array(
			'title' => $txt['credits_smf_team'],
			'groups' => array(
				array(
					'title' => $txt['credits_groups_ps'],
					'members' => array(
					),
				),
				array(
					'title' => $txt['credits_groups_dev'],
					'members' => array(
						'Norv',
						'A&auml;ron van Geffen',
						'Antechinus',
						'Bjoern &quot;Bloc&quot; Kristiansen',
						'Hendrik Jan &quot;Compuart&quot; Visser',
						'Juan &quot;JayBachatero&quot; Hernandez',
						'Karl &quot;RegularExpression&quot; Benson',
						$user_info['is_admin'] ? 'Matt &quot;Grudge&quot; Wolf' : 'Grudge',
						'Michael &quot;Thantos&quot; Miller',
						'Sinan &quot;&#12471;&#12490;&#12531;&quot; &Ccedil;evik',
						'Theodore &quot;Orstio&quot; Hildebrandt',
						'Thorsten &quot;TE&quot; Eurich',
						'winrules',
					),
				),
				array(
					'title' => $txt['credits_groups_consultants'],
					'members' => array(
						'Ren&eacute;-Gilles &quot;Nao &#23578;&quot; Deberdt',
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
			array(
				'title' => $txt['credits_groups_founder'],
				'members' => array(
					'Unknown W. &quot;[Unknown]&quot; Brackets',
				),
			),
		),
	);

	$context['copyrights'] = array(
		'smf' => sprintf($forum_copyright, $forum_version),
		'mods' => array(
		),
	);

	/*
		To Add-on Authors:
		You may add a copyright statement to this array for your add-ons.
		Do NOT edit the file, it would be messy. Simply call an add_hook('place_credit', 'my_function')
		where my_function will simply add your copyright to $context['copyrights']['mods'].
		You may also add credits at the end of the $context['credits'] array, following the same structure.

		Copyright statements should be in the form of a value only without a array key, i.e.:
			'Some Mod by WedgeBox &copy; 2010',
			$txt['some_mod_copyright'],
	*/

	call_hook('place_credit');

	if (!$in_admin)
	{
		loadTemplate('Who');
		$context['sub_template'] = 'credits';
		$context['robot_no_index'] = true;
		$context['page_title'] = $txt['credits'];
	}
}

?>