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

if (!defined('WEDGE'))
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
	global $context, $modSettings, $forum_copyright, $boardurl, $txt, $user_info;

	// Don't blink. Don't even blink. Blink and you're dead.
	loadLanguage('Who');

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
		'wedge' => array(
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
					),
				),
				array(
					'title' => $txt['credits_special'],
					'members' => array(
						'Dismal Shadow (Edwin Mendez)',
						'MultiformeIngegno (Lorenzo Raffio)',
						'[Unknown] &amp; Karl Benson',
						'Norodo',
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
					'title' => $txt['credits_groups_language'],
					'members' => $txt['translation_credits'],
				),
			),
		);

	$context['copyrights'] = array(
		'software' => array(
			'wedge' => $txt['credits_wedge'],
			'smf2' => sprintf($txt['credits_smf2'], implode(', ', array(
				'[Unknown]',
				'Aaron (Aaron van Geffen)',
				'Antechinus (Peter Sharpe)',
				'Bloc (Bjoern Kristiansen)',
				'Compuart (Hendrik Jan Visser)',
				'Grudge',
				'JayBachatero (Juan Hernandez)',
				'Nao &#23578; (Ren&eacute;-Gilles Deberdt)',
				'Norv',
				'Orstio (Theodore Hildebrandt)',
				'regularexpression (Karl Benson)',
				'[SiNaN] (Selman Eser)',
				'TE (Thorsten Eurich)',
				'Thantos (Michael Miller)',
			)), 'winrules'),
		),
		'images' => array(
			'famfamfam' => '<a href="http://famfamfam.com/">FamFamFam</a> Flags &amp; Silk &copy; Mark James, 2005',
			'icons' => '<a href="http://www.everaldo.com/crystal/">Crystal Icons</a> &copy; Crystal Project, 2001-2011',
			'diagona' => '<a href="http://p.yusukekamiyamane.com/">Diagona</a> &copy; Y&#363;suke Kamiyamane',
		),
		'tools' => array(
			'idtags' => '<a href="http://getid3.org">GetID3</a>',
			'exif' => '<a href="http://www.zenphoto.org/trac/wiki/ExifixerLibrary">Exifixer</a>',
			'player' => '<a href="http://www.longtailvideo.com/players/jw-flv-player/">JW Player</a>',
			'uploader' => '<a href="http://developer.yahoo.com/yui/">Yahoo! UI Uploader</a>',
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
		loadBlock('credits');
		$context['robot_no_index'] = true;
		$context['page_title'] = $txt['credits_site'];
	}
}

?>