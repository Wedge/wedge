<?php
/**
 * Wedge
 *
 * This file is a sample homepage that can be used in place of your board index.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	It contains only the following function:

	void Homepage()
		- prepares the homepage data.
		- uses the Homepage template (main block) and language file.
		- is accessed via index.php, if $modSettings['default_index'] == 'custom'.
*/

// Welcome to the show.
function Homepage()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings, $language;

	// Load the 'Homepage' template.
	loadTemplate('Homepage');
	loadLanguage('Homepage');

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl;

	// Do not let search engines index anything if there is a random thing in $_GET.
	if (!empty($_GET))
		$context['robot_no_index'] = true;

	$context['page_title'] = isset($txt['homepage_title']) ? $txt['homepage_title'] : sprintf($txt['forum_index'], $context['forum_name']);
}

?>