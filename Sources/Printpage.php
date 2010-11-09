<?php
/**********************************************************************************
* Printpage.php                                                                   *
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
 * This file provides all of the handling for the "printer friendly" view.
 *
 * @package wedge
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Manages display of a topic in a printer-friendly style.
 *
 * - The topic must be specified in the URL (topic=xyz)
 * - Permission to access the topic is ascertained elsewhere where $topic is resolved as standard from the URL component.
 * - Unlike normal pages, this uses the printpage templates only; this consists of a printpage template layer (above/below pair) with the main subtemplate resolving the post content.
 * - Accessed via ?action=printpage.
 * - There is a directive to the search engines not to index this page both specified here ($context['robot_no_index']), as well as explicitly stated in the template (without checking $context). Additionally the page does direct to the regular topic view as the canonical URL.
 * - Unlike the regular topic view, which includes pagination and a callback system to save memory, this function does neither, calling all the posts in a single query and building an array of every possible post at once. For very long topics this can cause memory issues.
 * - {@link parse_bbc()} is invoked with the 'print' parameter in place of the smileys option.
 */
function PrintPage()
{
	global $topic, $txt, $scripturl, $context, $user_info;
	global $board_info, $smcFunc, $modSettings;

	// Redirect to the boardindex if no valid topic id is provided.
	if (empty($topic))
		redirectexit();

	// Whatever happens don't index this.
	$context['robot_no_index'] = true;

	// Get the topic starter information.
	$request = $smcFunc['db_query']('', '
		SELECT m.poster_time, IFNULL(mem.real_name, m.poster_name) AS poster_name
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_topic = {int:current_topic}
		ORDER BY m.id_msg
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	// Redirect to the boardindex if no valid topic id is provided.
	if ($smcFunc['db_num_rows']($request) == 0)
		redirectexit();
	$row = $smcFunc['db_fetch_assoc']($request);
	$smcFunc['db_free_result']($request);

	// Let's "output" all that info.
	loadTemplate('Printpage');
	$context['template_layers'] = array('print');
	$context['board_name'] = $board_info['name'];
	$context['category_name'] = $board_info['cat']['name'];
	$context['poster_name'] = $row['poster_name'];
	$context['post_time'] = timeformat($row['poster_time'], false);
	$context['parent_boards'] = array();
	foreach ($board_info['parent_boards'] as $parent)
		$context['parent_boards'][] = $parent['name'];

	// Split the topics up so we can print them.
	$request = $smcFunc['db_query']('', '
		SELECT subject, poster_time, body, IFNULL(mem.real_name, poster_name) AS poster_name
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_topic = {int:current_topic}' . ($modSettings['postmod_active'] && !allowedTo('approve_posts') ? '
			AND (m.approved = {int:is_approved}' . ($user_info['is_guest'] ? '' : ' OR m.id_member = {int:current_member}') . ')' : '') . '
		ORDER BY m.id_msg',
		array(
			'current_topic' => $topic,
			'is_approved' => 1,
			'current_member' => $user_info['id'],
		)
	);
	$context['posts'] = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor the subject and message.
		censorText($row['subject']);
		censorText($row['body']);

		$context['posts'][] = array(
			'subject' => $row['subject'],
			'member' => $row['poster_name'],
			'time' => timeformat($row['poster_time'], false),
			'timestamp' => forum_time(true, $row['poster_time']),
			'body' => parse_bbc($row['body'], 'print'),
		);

		if (!isset($context['topic_subject']))
			$context['topic_subject'] = $row['subject'];
	}
	$smcFunc['db_free_result']($request);

	// Set a canonical URL for this page.
	$context['canonical_url'] = $scripturl . '?topic=' . $topic . '.0';
}

?>