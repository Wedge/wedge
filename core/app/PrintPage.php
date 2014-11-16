<?php
/**
 * This file provides all of the handling for the "printer friendly" view.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Manages display of a topic in a printer-friendly style.
 *
 * - The topic must be specified in the URL (topic=xyz)
 * - Permission to access the topic is ascertained elsewhere where $topic is resolved as standard from the URL component.
 * - Unlike normal pages, this uses the printpage templates only; this consists of a printpage template layer (above/below pair) with the main block resolving the post content.
 * - Accessed via ?action=printpage.
 * - There is a directive to the search engines not to index this page both specified here ($context['robot_no_index']), as well as explicitly stated in the template (without checking $context). Additionally the page does direct to the regular topic view as the canonical URL.
 * - Unlike the regular topic view, which includes pagination and a callback system to save memory, this function does neither, calling all the posts in a single query and building an array of every possible post at once. For very long topics this can cause memory issues.
 * - {@link parse_bbc()} is invoked with the 'print' parameter in place of the smileys option.
 */
function PrintPage()
{
	global $topic, $context, $board_info, $settings;

	// Redirect to the board list if no valid topic id is provided.
	if (empty($topic))
		redirectexit();

	// Whatever happens don't index this.
	$context['robot_no_index'] = true;

	// Get the topic starter information.
	$request = wesql::query('
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
	// Redirect to the board list if no valid topic id is provided.
	if (wesql::num_rows($request) == 0)
		redirectexit();
	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	// Let's "output" all that info.
	loadTemplate('PrintPage');
	wetem::hide('print');

	$context['board_name'] = $board_info['name'];
	$context['category_name'] = $board_info['cat']['name'];
	$context['poster_name'] = $row['poster_name'];
	$context['post_on_time'] = on_timeformat($row['poster_time'], false);
	$context['parent_boards'] = array();
	foreach ($board_info['parent_boards'] as $parent)
		$context['parent_boards'][] = $parent['name'];

	// Split the topics up so we can print them.
	$request = wesql::query('
		SELECT m.id_msg, m.subject, m.poster_time, m.body, IFNULL(mem.real_name, m.poster_name) AS poster_name
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_topic = {int:current_topic}' . ($settings['postmod_active'] && !allowedTo('approve_posts') ? '
			AND (m.approved = {int:is_approved}' . (we::$is_guest ? '' : ' OR m.id_member = {int:current_member}') . ')' : '') . '
		ORDER BY m.id_msg',
		array(
			'current_topic' => $topic,
			'is_approved' => 1,
			'current_member' => MID,
		)
	);
	$context['posts'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Censor the subject and message.
		censorText($row['subject']);
		censorText($row['body']);

		$context['posts'][] = array(
			'subject' => $row['subject'],
			'member' => $row['poster_name'],
			'on_time' => on_timeformat($row['poster_time'], false),
			'timestamp' => $row['poster_time'],
			'body' => parse_bbc($row['body'], 'post', array('print' => true, 'cache' => $row['id_msg'])),
		);

		if (!isset($context['topic_subject']))
			$context['topic_subject'] = $row['subject'];
	}
	wesql::free_result($request);

	// Set a canonical URL for this page.
	$context['canonical_url'] = '<URL>?topic=' . $topic . '.0';
}
