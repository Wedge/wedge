<?php
/**
 * Wedge
 *
 * This file provides the handling for some of the AJAX operations, namely the very generic ones fired through action=ajax.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

define('WEDGE_NO_LOG', 1);

/**
 * This function handles the initial interaction from action=ajax, loading the template then directing process to the appropriate handler.
 *
 * @see GetJumpTo()
 * @see ListMessageIcons()
 */
function Ajax()
{
	loadTemplate('Xml');

	$sub_actions = array(
		'jumpto' => array(
			'function' => 'GetJumpTo',
		),
		'messageicons' => array(
			'function' => 'ListMessageIcons',
		),
	);
	if (!isset($_REQUEST['sa'], $sub_actions[$_REQUEST['sa']]))
		fatal_lang_error('no_access', false);

	$sub_actions[$_REQUEST['sa']]['function']();
}

/**
 * Produces the list of boards and categories for the jump-to dropdown.
 *
 * - Uses the {@link getBoardList()} function in Subs-MessageIndex.php.
 * - Only displays boards the user has permissions to see (does not honor ignored boards preferences)
 * - The current board (if there is a current board) is indicated, and so will be in the dataset returned via the template.
 * - Passes control to the jump_to subtemplate in the main Xml template.
 */
function GetJumpTo()
{
	global $user_info, $context, $modSettings, $scripturl;

	// Find the boards/cateogories they can see.
	loadSource('Subs-MessageIndex');
	$boardListOptions = array(
		'use_permissions' => true,
		'selected_board' => isset($context['current_board']) ? $context['current_board'] : 0,
	);
	$context['jump_to'] = getBoardList($boardListOptions);

	// Make the board safe for display.
	foreach ($context['jump_to'] as $id_cat => $cat)
	{
		$context['jump_to'][$id_cat]['name'] = un_htmlspecialchars(strip_tags($cat['name']));
		foreach ($cat['boards'] as $id_board => $board)
			$context['jump_to'][$id_cat]['boards'][$id_board]['name'] = un_htmlspecialchars(strip_tags($board['name']));
	}

	// Pretty URLs need to be rewritten. Just these ones...
	if (!empty($modSettings['pretty_enable_filters']))
	{
		ob_start('ob_sessrewrite');
		$insideurl = preg_quote($scripturl, '~');
		$context['pretty']['search_patterns'][]  = '~(url=)"' . $insideurl . '([^<"]*?[?;&](board)=[^#<"]+)~';
		$context['pretty']['replace_patterns'][] = '~(url=)"' . $insideurl . '([^<"]*?[?;&](board)=([^#<"]+"))~';
	}

	loadSubTemplate('jump_to');
}

/**
 * Produces a list of the message icons, used for the AJAX change-icon selector within the topic view.
 *
 * - Uses the {@link getMessageIcons()} function in Subs-Editor.php to achieve this.
 * - Uses the current board (from $board) to ensure that the correct iconset is loaded, as icons can be per-board.
 * - Passes control to the message_icons subtemplate in the main Xml template.
 */
function ListMessageIcons()
{
	global $context, $board;

	loadSource('Subs-Editor');
	$context['icons'] = getMessageIcons($board);

	loadSubTemplate('message_icons');
}

?>