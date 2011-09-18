<?php
/**
 * Wedge
 *
 * Collapse or expand a category from the board index view.
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
 * Collapse or expand a category from the board index view.
 *
 * - Called via ?action=collapse
 * - Checks session via both POST and GET.
 * - Expects sa to be set in the request, to either expand/collapse/toggle as the operation, and c in the request to represent the individual category id.
 * - Calls {@link collapseCategories()} in Subs-Categories.php to manage the changeover.
 * - Redisplays the board index once complete.
 */
function Collapse()
{
	global $user_info, $context, $modSettings;

	// Just in case, no need, no need.
	$context['robot_no_index'] = true;

	checkSession('request');

	if (!isset($_GET['sa']))
		fatal_lang_error('no_access', false);

	// Check if the input values are correct.
	if (in_array($_REQUEST['sa'], array('expand', 'collapse', 'toggle')) && isset($_REQUEST['c']))
	{
		// And collapse/expand/toggle the category.
		loadSource('Subs-Categories');
		collapseCategories(array((int) $_REQUEST['c']), $_REQUEST['sa'], array($user_info['id']));
	}

	// And go back to the board list.
	redirectexit(empty($modSettings['default_index']) ? '' : 'action=boards');
}

?>