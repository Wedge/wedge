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
		'thought' => array(
			'function' => 'Thought',
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
 * - Passes control to the jump_to block in the main Xml template.
 */
function GetJumpTo()
{
	global $user_info, $context, $modSettings, $scripturl;

	// Find the boards/cateogories they can see.
	// Note: you can set $context['current_category'] if you have too many boards and it kills performance.
	loadSource('Subs-MessageIndex');
	$boardListOptions = array(
		'use_permissions' => true,
		'selected_board' => isset($context['current_board']) ? $context['current_board'] : 0,
		'current_category' => isset($context['current_category']) ? $context['current_category'] : null, // null to list all categories
	);
	$context['jump_to'] = getBoardList($boardListOptions);

	// Make the board safe for display.
	foreach ($context['jump_to'] as $id_cat => $cat)
	{
		$context['jump_to'][$id_cat]['name'] = un_htmlspecialchars(strip_tags($cat['name']));
		foreach ($cat['boards'] as $id_board => $board)
			$context['jump_to'][$id_cat]['boards'][$id_board]['name'] = un_htmlspecialchars(strip_tags($board['name']));
	}

	// Pretty URLs need to be rewritten.
	if (!empty($modSettings['pretty_enable_filters']))
	{
		ob_start('ob_sessrewrite');
		$insideurl = preg_quote($scripturl, '~');
		$context['pretty']['search_patterns'][] =  '~(url=)"' . $insideurl . '([^<"]*?[?;&](board|action)=[^"#]+)~';
		$context['pretty']['replace_patterns'][] = '~(url=)"' . $insideurl . '([^<"]*?[?;&](board|action)=([^"]+"))~';
	}

	wetem::load('jump_to');
}

/**
 * Produces a list of the message icons, used for the AJAX change-icon selector within the topic view.
 *
 * - Uses the {@link getMessageIcons()} function in Subs-Editor.php to achieve this.
 * - Uses the current board (from $board) to ensure that the correct iconset is loaded, as icons can be per-board.
 * - Passes control to the message_icons block in the main Xml template.
 */
function ListMessageIcons()
{
	global $context, $board;

	loadSource('Subs-Editor');
	$context['icons'] = getMessageIcons($board);

	wetem::load('message_icons');
}

function Thought()
{
	global $context, $user_info;

	// !! We need $user_info if we're going to allow the editing of older messages... Don't forget to check for sessions?
	if ($user_info['is_guest'])
		die;

	// !! Should we use censorText at store time, or display time...? $context['user'] (Load.php:1696) begs to differ.
	$text = isset($_POST['text']) ? westr::htmlspecialchars(trim($_POST['text'])) : '';
	if (empty($text) && empty($_GET['in']) && !isset($_REQUEST['remove']))
		die();

	wetem::load('thought');

	// Original thought ID (in case of an edit.)
	$oid = isset($_POST['oid']) ? (int) $_POST['oid'] : 0;

	// Is this a public thought?
	$privacy = isset($_POST['privacy']) && is_numeric($_POST['privacy']) && $_POST['privacy'] <= 4 ? (int) $_POST['privacy'] : 1;

	/*
		// Delete thoughts when they're older than 3 years...?
		// Commented out because it's only useful if your forum is very busy...
		wesql::query('
			DELETE FROM {db_prefix}thoughts
			WHERE updated < UNIX_TIMESTAMP() - 3 * 365 * 24 * 3600
		');
	*/

	// Are we asking for an existing thought?
	if (!empty($_GET['in']))
	{
		$request = wesql::query('
			SELECT id_thought, privacy, thought
			FROM {db_prefix}thoughts
			WHERE id_thought = {int:original_id}' . (allowedTo('moderate') ? '' : '
			AND id_member = {int:id_member}'),
			array(
				'id_member' => $user_info['id'],
				'original_id' => $_GET['in'],
			)
		);
		list ($id_thought, $privacy, $thought) = wesql::fetch_row($request);
		wesql::free_result($request);

		$context['return_thought'] = array(
			'id_thought' => $id_thought,
			'thought' => un_htmlspecialchars($thought),
			'privacy' => $privacy,
		);

		return;
	}

	// Is it an edit?
	if (!empty($oid))
	{
		$request = wesql::query('
			SELECT id_thought, id_member, thought
			FROM {db_prefix}thoughts
			WHERE id_thought = {int:original_id}
			AND id_member = {int:id_member}',
			array(
				'id_member' => $user_info['id'],
				'original_id' => $oid,
			)
		);
		list ($last_thought, $last_member, $last_text) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	// Overwrite previous thought if it's just an edit.
	if (!empty($last_thought) && (allowedTo('moderate') || $last_member === $user_info['id']))
	{
		similar_text($last_text, $text, $percent);

		// Think before you think!
		if (isset($_REQUEST['remove']))
		{
			// Does any member actually use this thought?
			$old_thought = 's:10:"id_thought";s:' . strlen($last_thought) . ':"' . $last_thought . '"';
			$request = wesql::query('
				SELECT id_member, data
				FROM {db_prefix}members
				WHERE data LIKE {string:data}',
				array(
					'data' => $old_thought,
				)
			);
			list ($member, $data) = wesql::fetch_row($request);
			wesql::free_result($request);

			// Okay, time to delete it...
			wesql::query('
				DELETE FROM {db_prefix}thoughts
				WHERE id_thought = {int:id_thought}', array(
					'id_thought' => $last_thought,
				)
			);

			// If anyone was using it, then update to their last valid thought.
			if (!empty($member))
			{
				$request = wesql::query('
					SELECT id_thought, thought, privacy
					FROM {db_prefix}thoughts
					WHERE id_member = {int:member}
					AND id_master = {int:not_a_reply}
					ORDER BY id_thought DESC
					LIMIT 1',
					array(
						'member' => $member,
						'not_a_reply' => 0,
					)
				);
				list ($id_thought, $thought, $privacy) = wesql::fetch_row($request);
				wesql::free_result($request);

				// Update their user data to use the new valid thought.
				if (!empty($id_thought))
				{
					// A complete hack, not ashamed of it :)
					if ($member !== $user_info['id'])
					{
						$real_user = $user_info;
						$user_info['id'] = $member;
						$user_info['data'] = $data;
					}
					updateMyData(array(
						'id_thought' => $id_thought,
						'thought' => $thought,
						'thought_privacy' => $privacy,
					));
					if (!empty($real_user))
						$user_info = $real_user;
				}

				// Similarly, we should update their personal text with the latest valid value.
				$request = wesql::query('
					SELECT id_thought, thought
					FROM {db_prefix}thoughts
					WHERE id_member = {int:member}
					AND privacy = {int:heed_my_words}
					ORDER BY id_thought DESC
					LIMIT 1',
					array(
						'member' => $member,
						'heed_my_words' => 0,
					)
				);
				list ($personal_id_thought, $personal_thought) = wesql::fetch_row($request);
				wesql::free_result($request);

				// Update their user data to use the new valid thought.
				if (!empty($personal_id_thought))
					updateMemberData($member, array('personal_text' => parse_bbc_inline($personal_thought)));
			}
			die;
		}
		// If it's similar to the earlier version, don't update the time.
		else
			wesql::query('
				UPDATE {db_prefix}thoughts
				SET updated = {raw:updated}, thought = {string:thought}, privacy = {int:privacy}
				WHERE id_thought = {int:id_thought}', array(
					'id_thought' => $last_thought,
					'privacy' => $privacy,
					'updated' => $percent >= 90 ? 'updated' : time(),
					'thought' => $text
				)
			);
	}
	else
	{
		// Okay, so this is a new thought... Insert it, we'll cache it if it's not a comment.
		wesql::query('
			INSERT IGNORE INTO {db_prefix}thoughts (id_parent, id_member, id_master, privacy, updated, thought)
			VALUES ({int:id_parent}, {int:id_member}, {int:id_master}, {int:privacy}, {int:updated}, {string:thought})', array(
				'id_parent' => !empty($_POST['parent']) ? (int) $_POST['parent'] : 0,
				'id_member' => $user_info['id'],
				'id_master' => !empty($_POST['master']) ? (int) $_POST['master'] : 0,
				'privacy' => $privacy,
				'updated' => time(),
				'thought' => $text
			)
		);
		if (empty($_POST['parent']))
			$last_thought = wesql::insert_id();
	}

	// This is for use in the XML template.
	$context['return_thought'] = array(
		'id_thought' => $last_thought,
		'thought' => parse_bbc_inline($text),
		'privacy' => $privacy,
	);

	// Only update the thought area if it's a public comment, and isn't a comment on another thought...
	if (empty($_POST['parent']) && !empty($last_thought))
	{
		updateMyData(array(
			'id_thought' => $last_thought,
			'thought' => $text,
			'thought_privacy' => $privacy,
		));
		// If the thought is public, we can store it as personal text. We'll also parse it now,
		// for performance reasons. Personal texts are likely to change, so BBC changes
		// shouldn't have a major influence on these fields. Correct me if I'm wrong.
		if ($privacy === 0)
			updateMemberData($user_info['id'], array('personal_text' => parse_bbc_inline($text)));
	}
}

?>