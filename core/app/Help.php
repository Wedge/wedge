<?php
/**
 * This file provides the handling of the help popups.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Handles provision of pop-ups for user help.
 *
 * - Accessed via ?action=help;in=xyz and subsequently $_GET['in'] is used as the identifier of the help to display.
 * - Identifies where a help string may be located (normally the Help language file, but if the string starts with permissionhelp, it also loads the permissions strings)
 * - Looks into $txt['help_*'] or, if not found, simply uses the provided string.
 * - Uses the Help template, popup block, removing all layers.
 */
function Help()
{
	global $txt, $context;

	if (!isset($_GET['in']) || !is_string($_GET['in']))
		fatal_lang_error('no_access', false);

	// Load the admin help language file and template.
	loadLanguage('Help');
	call_lang_hook('lang_help');

	// Permission specific help?
	if (substr($_GET['in'], 0, 14) == 'permissionhelp')
		loadLanguage('ManagePermissions');

	loadTemplate('GenericPopup');
	wetem::hide();

	// Set the page title to something relevant.
	$context['page_title'] = $context['forum_name'] . ' - ' . $txt['help'];

	// Just show the popup block.
	wetem::load('popup');

	// What help string should be used?
	$context['popup_contents'] = isset($txt['help_' . $_GET['in']]) ? $txt['help_' . $_GET['in']] : (isset($txt[$_GET['in']]) ? $txt[$_GET['in']] : $_GET['in']);
}
