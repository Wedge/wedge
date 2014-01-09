<?php
/**
 * Handles the processing required to switch between WYSIWYG and BBCode-only editing modes.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function JSEditor()
{
	checkSession('get');

	if (!isset($_REQUEST['view']) || !isset($_REQUEST['message']))
		fatal_lang_error('no_access', false);

	loadSource('Class-Editor');

	// Return the right thing for the mode.
	if ((int) $_REQUEST['view'])
	{
		$_REQUEST['message'] = strtr($_REQUEST['message'], array('#wecol#' => ';', '#welt#' => '&lt;', '#wegt#' => '&gt;', '#weamp#' => '&amp;'));
		$message = wedit::bbc_to_html($_REQUEST['message']);
	}
	else
	{
		$_REQUEST['message'] = un_htmlspecialchars($_REQUEST['message']);
		$_REQUEST['message'] = strtr($_REQUEST['message'], array('#wecol#' => ';', '#welt#' => '&lt;', '#wegt#' => '&gt;', '#weamp#' => '&amp;'));
		$message = wedit::html_to_bbc($_REQUEST['message']);
	}

	return_xml('<we><message view="', (int) $_REQUEST['view'], '">', cleanXml(westr::htmlspecialchars($message)), '</message></we>');
}
