<?php
/**
 * This file provides the handling for some of the AJAX operations, namely the very generic ones fired through action=ajax.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * This function handles the initial interaction from action=ajax, loading the template then directing process to the appropriate handler.
 *
 * @see GetJumpTo()
 * @see ListMessageIcons()
 */
function Ajax()
{
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
 */
function GetJumpTo()
{
	global $context, $settings;

	// Find the boards/cateogories they can see.
	// Note: you can set $context['current_category'] if you have too many boards and it kills performance.
	loadSource('Subs-MessageIndex');
	$boardListOptions = array(
		'use_permissions' => true,
		'selected_board' => isset($context['current_board']) ? $context['current_board'] : 0,
		'current_category' => isset($context['current_category']) ? $context['current_category'] : null, // null to list all categories
	);
	$url = !empty($settings['pretty_enable_filters']) ? '<URL>?board=' : '';
	$jump_to = getBoardList($boardListOptions);
	$skip_this = isset($_REQUEST['board']) ? $_REQUEST['board'] : 0;
	$json = array();

	foreach ($jump_to as $id_cat => $cat)
	{
		$json[] = array(
			'name' => un_htmlspecialchars(strip_tags($cat['name'])),
		);
		foreach ($cat['boards'] as $bdata)
			$json[] = array(
				'level' => (int) $bdata['child_level'],
				'id' => $bdata['id'] == $skip_this ? 'skip' : ($url ? $url . $bdata['id'] . '.0' : $bdata['id']),
				'name' => un_htmlspecialchars(strip_tags($bdata['name'])),
			);
	}

	// This will be returned as JSON, saving bytes and processing time.
	return_json($json);
}

/**
 * Produces a list of the message icons, used for the AJAX change-icon selector within the topic view.
 *
 * - Uses the {@link getMessageIcons()} function in Subs-Editor.php to achieve this.
 * - Uses the current board (from $board) to ensure that the correct iconset is loaded, as icons can be per-board.
 */
function ListMessageIcons()
{
	global $board;

	loadSource('Subs-Editor');
	$icons = getMessageIcons($board);

	$str = '';
	foreach ($icons as $icon)
		$str .= '
	<icon value="' . $icon['value'] . '" url="' . $icon['url'] . '"><![CDATA[' . cleanXml('<img src="' . $icon['url'] . '" alt="' . $icon['value'] . '" title="' . $icon['name'] . '">') . ']]></icon>';

	return_xml('<we>', $str, '</we>');
}

function Thought()
{
	if (isset($_REQUEST['personal']))
		ThoughtPersonal();

	// !! We need we::$user if we're going to allow the editing of older messages... Don't forget to check for sessions?
	if (we::$is_guest)
		exit;

	// !! Should we use censorText at store time, or display time...? we::$user (Load.php:1696) begs to differ.
	$text = isset($_POST['text']) ? westr::htmlspecialchars(trim($_POST['text']), ENT_QUOTES) : '';

	if (!empty($text))
	{
		loadSource('Class-Editor');
		wedit::preparsecode($text);
	}

	// Original thought ID (in case of an edit.)
	$oid = isset($_POST['oid']) ? (int) $_POST['oid'] : 0;
	$pid = !empty($_POST['parent']) ? (int) $_POST['parent'] : 0;
	$mid = !empty($_POST['master']) ? (int) $_POST['master'] : 0;

	if (isset($_GET['like']))
	{
		loadSource('Like');
		$_REQUEST['msg'] = $oid;
		$_GET['thought'] = true;
		Like();
		return;
	}

	// If we have a parent, then get the member data for the parent thought.
	if ($pid)
	{
		$request = wesql::query('
			SELECT m.id_member, m.real_name
			FROM {db_prefix}thoughts AS t
			LEFT JOIN {db_prefix}members AS m ON t.id_member = m.id_member
			WHERE id_thought = {int:id_parent}
			LIMIT 1',
			array(
				'id_parent' => $pid,
			)
		);
		list ($parent_id, $parent_name) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	// Is this a public thought?
	$privacy = isset($_POST['privacy']) && preg_match('~^-?\d+$~', $_POST['privacy']) ? (int) $_POST['privacy'] : PRIVACY_DEFAULT;

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
			SELECT thought
			FROM {db_prefix}thoughts
			WHERE id_thought = {int:original_id}' . (allowedTo('moderate_forum') ? '' : '
			AND id_member = {int:id_member}
			LIMIT 1'),
			array(
				'id_member' => MID,
				'original_id' => $_GET['in'],
			)
		);
		list ($thought) = wesql::fetch_row($request);
		wesql::free_result($request);

		return_raw(un_htmlspecialchars($thought));
	}

	// Is it an edit?
	if (!empty($oid))
	{
		$request = wesql::query('
			SELECT t.id_thought, t.thought, t.id_member, m.real_name
			FROM {db_prefix}thoughts AS t
			INNER JOIN {db_prefix}members AS m ON m.id_member = t.id_member
			WHERE t.id_thought = {int:original_id}' . (allowedTo('moderate_forum') ? '' : '
			AND t.id_member = {int:id_member}'),
			array(
				'id_member' => MID,
				'original_id' => $oid,
			)
		);
		list ($last_thought, $last_text, $last_member, $last_name) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	// Overwrite previous thought if it's just an edit.
	if (!empty($last_thought))
	{
		// Think before you think!
		if (empty($text) && empty($_GET['in']))
		{
			// Okay, so we want to delete it... Allow plugins to have a last peek.
			call_hook('thought_delete', array(&$last_thought, &$last_text));
			wesql::query('
				DELETE FROM {db_prefix}thoughts
				WHERE id_thought = {int:id_thought}', array(
					'id_thought' => $last_thought,
				)
			);
		}
		// If it's similar to the earlier version, don't update the time.
		else
		{
			similar_text($last_text, $text, $percent);
			$update = $percent >= 90 ? 'updated' : time();
			wesql::query('
				UPDATE {db_prefix}thoughts
				SET updated = {raw:updated}, thought = {string:thought}, privacy = {int:privacy}
				WHERE id_thought = {int:id_thought}', array(
					'id_thought' => $last_thought,
					'privacy' => $privacy,
					'updated' => $update,
					'thought' => $text
				)
			);
			call_hook('thought_update', array(&$last_thought, &$privacy, &$update, &$text));
		}
	}
	elseif ($text)
	{
		// Okay, so this is a new thought... Insert it, we'll cache it if it's not a comment.
		wesql::query('
			INSERT IGNORE INTO {db_prefix}thoughts (id_parent, id_member, id_master, privacy, updated, thought)
			VALUES ({int:id_parent}, {int:id_member}, {int:id_master}, {int:privacy}, {int:updated}, {string:thought})', array(
				'id_parent' => $pid,
				'id_member' => MID,
				'id_master' => $mid,
				'privacy' => $privacy,
				'updated' => time(),
				'thought' => $text
			)
		);
		$last_thought = wesql::insert_id();

		$user_id = $pid ? (empty($last_member) ? MID : $last_member) : 0;
		$user_name = empty($last_name) ? we::$user['name'] : $last_name;

		call_hook('thought_add', array(&$privacy, &$text, &$pid, &$mid, &$last_thought, &$user_id, &$user_name));
	}

	return_thoughts();
}

function return_thoughts()
{
	global $context;

	// Welcome to the world of rule-bending dirty hacks.
	// What you're going to see isn't for the faint-hearted...
	// We're going to emulate Wedge building a thought page.

	list ($type, $ctx, $page) = explode(' ', isset($_POST['cx']) ? $_POST['cx'] : 'invalid 0 0');
	if ($type == 'invalid')
		obExit(false);

	$_REQUEST['start'] = $page;

	loadSource(array('Thoughts', 'Subs-Cache'));
	loadTemplate('index'); // We need template_mini_menu
	wedge_get_skin_options(); // Yay, another rule broken! We need the SKIN_MOBILE status.

	// This is basically return_xml, but with a series of echo's in-between...
	clean_output();
	header('Content-Type: text/html; charset=UTF-8');

	$context['footer_js'] = '';
	if ($type == 'latest')
	{
		embedThoughts($ctx);
		template_thoughts_table();
	}
	elseif ($type == 'thread')
	{
		$_REQUEST['in'] = $ctx;
		Thoughts();
		template_thoughts_thread();
	}
	elseif ($type == 'profile')
	{
		loadLanguage('Profile');
		latestThoughts($ctx);
		template_thoughts_table();
	}

	echo '<script>breakLinks();', $context['footer_js'], '</script>'; // Yayz!
	obExit(false); // And finally, we skip the actual templating process.
}

function ThoughtPersonal()
{
	// !! Also check for sessions..?
	if (we::$is_guest || empty($_REQUEST['in']))
		exit;

	// Get the thought text, and ensure it's from the current member.
	$request = wesql::query('
		SELECT id_thought, thought
		FROM {db_prefix}thoughts
		WHERE id_member = {int:member}
		AND id_thought = {int:thought}
		LIMIT 1',
		array(
			'member' => MID,
			'thought' => $_REQUEST['in'],
		)
	);
	list ($personal_id_thought, $personal_thought) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Update their user data to use the new valid thought.
	if (!empty($personal_id_thought))
		updateMemberData(MID, array('personal_text' => parse_bbc_inline($personal_thought, 'thought', array('user' => MID))));

	exit;
}
