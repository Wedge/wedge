<?php
/**
 * Wedge
 *
 * Handles the processing required to switch betwen WYSIWYG and bbcode-only editing modes.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

define('WEDGE_NO_LOG', 1);

function Jseditor()
{
	global $context;

	checkSession('get');

	if (!isset($_REQUEST['view']) || !isset($_REQUEST['message']))
		fatal_lang_error('no_access', false);

	loadSource('Class-Editor');

	loadSubTemplate('sendbody');

	$context['view'] = (int) $_REQUEST['view'];

	// Return the right thing for the mode.
	if ($context['view'])
	{
		$_REQUEST['message'] = strtr($_REQUEST['message'], array('#smcol#' => ';', '#smlt#' => '&lt;', '#smgt#' => '&gt;', '#smamp#' => '&amp;'));
		$context['message'] = wedit::bbc_to_html($_REQUEST['message']);
	}
	else
	{
		$_REQUEST['message'] = un_htmlspecialchars($_REQUEST['message']);
		$_REQUEST['message'] = strtr($_REQUEST['message'], array('#smcol#' => ';', '#smlt#' => '&lt;', '#smgt#' => '&gt;', '#smamp#' => '&amp;'));

		$context['message'] = wedit::html_to_bbc($_REQUEST['message']);
	}

	$context['message'] = westr::htmlspecialchars($context['message']);
}

?>