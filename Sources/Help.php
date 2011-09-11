<?php
/**
 * Wedge
 *
 * This file provides the handling of the help popups.
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
 * Handles provision of pop-ups for user help.
 *
 * - Accessed via ?action=help;in=xyz and subsequently $_GET['in'] is used as the identifier of the help to display.
 * - Identifies where a help string may be located (normally the Help language file, but if the string starts with permissionhelp, it also loads the permissions strings)
 * - steps through whether to use $helptxt (for administrative help strings as $txt[$_GET['in']] will be the option description), $txt (for permissions) or if not found, simply uses the provided string.
 * - Uses the Help template, popup block, removing all layers.
 */
function Help()
{
	global $txt, $helptxt, $context, $scripturl;

	if (!isset($_GET['in']) || !is_string($_GET['in']))
		fatal_lang_error('no_access', false);

	if (!isset($helptxt))
		$helptxt = array();

	// Load the admin help language file and template.
	loadLanguage('Help');

	// Permission specific help?
	if (isset($_GET['in']) && substr($_GET['in'], 0, 14) == 'permissionhelp')
		loadLanguage('ManagePermissions');

	loadTemplate('Help');
	hideChrome();

	// Set the page title to something relevant.
	$context['page_title'] = $context['forum_name'] . ' - ' . $txt['help'];

	// Just show the popup sub template.
	loadBlock('popup');

	// What help string should be used?
	if (isset($helptxt[$_GET['in']]))
		$context['help_text'] = $helptxt[$_GET['in']];
	elseif (isset($txt[$_GET['in']]))
		$context['help_text'] = $txt[$_GET['in']];
	else
		$context['help_text'] = $_GET['in'];

	// Does this text contain a link that we should fill in?
	if (preg_match('~%([0-9]+\$)?s\?~', $context['help_text'], $match))
		$context['help_text'] = sprintf($context['help_text'], $scripturl, $context['session_id'], $context['session_var']);
}

?>