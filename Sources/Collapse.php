<?php
/**********************************************************************************
* Collapse.php                                                                    *
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

/**
 * This file provides the primary view, and a minor action, for the board index; also known as the list of all boards, and the default initial of the forum.
 *
 * @package wedge
 */

if (!defined('SMF'))
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
	global $user_info, $context;

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

	// And go back to the board index.
	loadSource('BoardIndex');
	BoardIndex();
}

?>