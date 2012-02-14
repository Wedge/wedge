<?php
/**
 * Wedge
 *
 * This file handles the old search method for users (for sending them a message).
 * @todo: remove it!
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

define('WEDGE_NO_LOG', 1);

function FindMember()
{
	global $context, $scripturl, $user_info;

	checkSession('get');

	loadSource('Subs-Auth');

	if (isset($_REQUEST['search']))
		$context['last_search'] = westr::htmlspecialchars($_REQUEST['search'], ENT_QUOTES);
	else
		$_REQUEST['start'] = 0;

	// Allow the user to pass the input to be added to to the box.
	$context['input_box_name'] = isset($_REQUEST['input']) && preg_match('~^[\w-]+$~', $_REQUEST['input']) === 1 ? $_REQUEST['input'] : 'to';

	// Take the delimiter over GET in case it's \n or something.
	$context['delimiter'] = isset($_REQUEST['delim']) ? ($_REQUEST['delim'] == 'LB' ? "\n" : $_REQUEST['delim']) : ', ';
	$context['quote_results'] = !empty($_REQUEST['quote']);

	// List all the results.
	$context['results'] = array();

	// Some buddy related settings ;)
	$context['show_buddies'] = !empty($user_info['buddies']);
	$context['buddy_search'] = isset($_REQUEST['buddies']);

	// If the user has done a search, well - search.
	if (isset($_REQUEST['search']))
	{
		$_REQUEST['search'] = westr::htmlspecialchars($_REQUEST['search'], ENT_QUOTES);

		$context['results'] = findMembers(array($_REQUEST['search']), true, $context['buddy_search']);
		$total_results = count($context['results']);

		$context['page_index'] = template_page_index($scripturl . '?action=findmember;search=' . $context['last_search'] . ';' . $context['session_query'] . ';input=' . $context['input_box_name'] . ($context['quote_results'] ? ';quote=1' : '') . ($context['buddy_search'] ? ';buddies' : ''), $_REQUEST['start'], $total_results, 7);

		// Determine the navigation context.
		$base_url = $scripturl . '?action=findmember;search=' . urlencode($context['last_search']) . (empty($_REQUEST['u']) ? '' : ';u=' . $_REQUEST['u']) . ';' . $context['session_query'];
		$context['links'] = array(
			'prev' => $_REQUEST['start'] >= 7 ? $base_url . ';start=' . ($_REQUEST['start'] - 7) : '',
			'next' => $_REQUEST['start'] + 7 < $total_results ? $base_url . ';start=' . ($_REQUEST['start'] + 7) : '',
		);
		$context['page_info'] = array(
			'current_page' => $_REQUEST['start'] / 7 + 1,
			'num_pages' => floor(($total_results - 1) / 7) + 1
		);

		$context['results'] = array_slice($context['results'], $_REQUEST['start'], 7);
	}
}

?>