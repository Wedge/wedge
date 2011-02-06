<?php
/**********************************************************************************
* Subs-MessageIndex.php                                                           *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC5                                         *
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
 * This file provides subsidiary functions for the message index view.
 *
 * @package wedge
 */

/**
 * Returns a list of boards matching specific criteria.
 *
 * @param array $boardListOptions Parameters to control which boards' details are returned:
 * - excluded_boards - an array of board ids that should not be included in the list (and return all others)
 * - included_boards - an array of board ids to retrieve data from.
 * - ignore_boards - set to true to honor the ignore-boards options of the present user.
 * - use_permissions - set to true to honor the visibility permissions of the boards. (Does not apply if ignore_boards is used)
 * - not_redirection - set to true to exclude redirection boards.
 * - selected_board - an integer board id, representing a current/selected board (useful for the jump to code)
 *
 * @return array The principle array is a keyed array of categories, the key being the category id. Each category key consists of an array:
 * - id - the category's id
 * - name - the category's name
 * - boards - an array containing the boards in the category. Unlike the categories parent array, the board arrays are indexed rather than keyed, and each of these arrays consists of: id (board id), name (board name), child_level (the level of nesting), selected (whether this is the selected board - see selected_board)
 */
function getBoardList($boardListOptions = array())
{
	global $user_info;

	if (isset($boardListOptions['excluded_boards'], $boardListOptions['included_boards']))
		trigger_error('getBoardList(): Setting both excluded_boards and included_boards is not allowed.', E_USER_ERROR);

	$where = array();
	$where_parameters = array();
	if (isset($boardListOptions['excluded_boards']))
	{
		$where[] = 'b.id_board NOT IN ({array_int:excluded_boards})';
		$where_parameters['excluded_boards'] = $boardListOptions['excluded_boards'];
	}

	if (isset($boardListOptions['included_boards']))
	{
		$where[] = 'b.id_board IN ({array_int:included_boards})';
		$where_parameters['included_boards'] = $boardListOptions['included_boards'];
	}

	if (!empty($boardListOptions['ignore_boards']))
		$where[] = '{query_wanna_see_board}';

	elseif (!empty($boardListOptions['use_permissions']))
		$where[] = '{query_see_board}';

	if (!empty($boardListOptions['not_redirection']))
	{
		$where[] = 'b.redirect = {string:blank_redirect}';
		$where_parameters['blank_redirect'] = '';
	}

	$request = wesql::query('
		SELECT c.name AS cat_name, c.id_cat, b.id_board, b.name AS board_name, b.child_level
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' . (empty($where) ? '' : '
		WHERE ' . implode('
			AND ', $where)),
		$where_parameters
	);

	$return_value = array();
	if (wesql::num_rows($request) !== 0)
	{
		while ($row = wesql::fetch_assoc($request))
		{
			if (!isset($return_value[$row['id_cat']]))
				$return_value[$row['id_cat']] = array(
					'id' => $row['id_cat'],
					'name' => $row['cat_name'],
					'boards' => array(),
				);

			$return_value[$row['id_cat']]['boards'][] = array(
				'id' => $row['id_board'],
				'name' => $row['board_name'],
				'child_level' => $row['child_level'],
				'selected' => isset($boardListOptions['selected_board']) && $boardListOptions['selected_board'] == $row['id_board'],
			);
		}
	}
	wesql::free_result($request);

	return $return_value;
}

?>