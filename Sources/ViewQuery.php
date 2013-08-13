<?php
/**
 * Wedge
 *
 * This file is concerned with viewing SQL queries, and is used for debugging.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Displays queries we have previously logged through execution and allows for some analysis.
 *
 * - Toggles the session variable 'view_queries'.
 * - Outputs a list of the queries stored in the session.
 * - Requires permissions as set in the administration panel, namely admin, moderator, regular member or guest.
 * - Accessed via ?action=viewquery
 * - Strings in this function have not, and do not need to be, internationalized; it is internal debugging data only.
 */
function ViewQuery()
{
	global $scripturl, $theme, $context, $db_connection, $settings, $boarddir, $txt, $db_show_debug;

	$show_debug = isset($db_show_debug) && $db_show_debug === true;
	// We should have debug mode enabled, as well as something to display!
	if (!$show_debug || !isset($_SESSION['debug']))
		fatal_lang_error('no_access', false);

	// Check groups
	if (empty($settings['db_show_debug_who']) || $settings['db_show_debug_who'] == 'admin')
		$show_debug &= we::$is_admin;
	elseif ($settings['db_show_debug_who'] == 'mod')
		$show_debug &= allowedTo('moderate_forum');
	elseif ($settings['db_show_debug_who'] == 'regular')
		$show_debug &= we::$is_member;
	else
		$show_debug &= ($settings['db_show_debug_who'] == 'any');

	// Now, who can see the query log? Need to have the ability to see any of this anyway.
	$show_debug_query = $show_debug;
	if (empty($settings['db_show_debug_who_log']) || $settings['db_show_debug_who_log'] == 'admin')
		$show_debug_query &= we::$is_admin;
	elseif ($settings['db_show_debug_who_log'] == 'mod')
		$show_debug_query &= allowedTo('moderate_forum');
	elseif ($settings['db_show_debug_who_log'] == 'regular')
		$show_debug_query &= we::$is_member;
	else
		$show_debug_query &= ($settings['db_show_debug_who_log'] == 'any');

	// If it's turned off for this group, simply just force them to a generic log-in-or-be-administrator situation.
	if (!$show_debug_query)
		isAllowedTo('admin_forum');

	// If we're just hiding/showing, do it now.
	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'hide')
	{
		$_SESSION['view_queries'] = $_SESSION['view_queries'] == 1 ? 0 : 1;

		redirectexit(empty($_SERVER['HTTP_REFERER']) ? (strpos($_SESSION['old_url'], 'action=viewquery') === false ? $_SESSION['old_url'] : '') : $_SERVER['HTTP_REFERER']);
	}

	$query_id = isset($_REQUEST['qq']) ? (int) $_REQUEST['qq'] - 1 : -1;

	loadLanguage('Stats');

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
<head>
	<meta charset="utf-8">
	<title>', $context['forum_name_html_safe'], '</title>',
	theme_base_css(), '
	<style>
		body { margin: 1ex }
		body, td, th, .normaltext { font-size: x-small }
		.smalltext { font-size: xx-small }
	</style>
</head>
<body id="helf">
	<div class="windowbg wrc">';

	foreach ($_SESSION['debug'] as $q => $query_data)
	{
		// Fix the indentation....
		$query_data['q'] = ltrim(str_replace("\r", '', $query_data['q']), "\n");
		$query = explode("\n", $query_data['q']);
		$min_indent = 0;
		foreach ($query as $line)
		{
			preg_match('/^(\t*)/', $line, $temp);
			if (strlen($temp[0]) < $min_indent || $min_indent == 0)
				$min_indent = strlen($temp[0]);
		}
		foreach ($query as $l => $dummy)
			$query[$l] = substr($dummy, $min_indent);
		$query_data['q'] = implode("\n", $query);

		// Make the filenames look a bit better.
		if (isset($query_data['f']))
			$query_data['f'] = preg_replace('~^' . preg_quote($boarddir, '~') . '~', '...', $query_data['f']);

		$is_select_query = substr(trim($query_data['q']), 0, 6) == 'SELECT';
		if ($is_select_query)
			$select = $query_data['q'];
		elseif (preg_match('~^INSERT(?: IGNORE)? INTO \w+(?:\s+\([^)]+\))?\s+(SELECT .+)$~s', trim($query_data['q']), $matches) != 0)
		{
			$is_select_query = true;
			$select = $matches[1];
		}
		elseif (preg_match('~^CREATE TEMPORARY TABLE .+?(SELECT .+)$~s', trim($query_data['q']), $matches) != 0)
		{
			$is_select_query = true;
			$select = $matches[1];
		}
		// Temporary tables created in earlier queries are not explainable.
		if ($is_select_query)
		{
			foreach (array('log_topics_unread', 'topics_posted_in', 'tmp_log_search_topics', 'tmp_log_search_messages') as $tmp)
				if (strpos($select, $tmp) !== false)
				{
					$is_select_query = false;
					break;
				}
		}

		echo '
	<div id="qq', $q, '" style="margin-bottom: 2ex">
		<a', $is_select_query ? ' href="' . $scripturl . '?action=viewquery;qq=' . ($q + 1) . '#qq' . $q . '"' : '', ' style="font-weight: bold; text-decoration: none">
			', westr::nl2br(str_replace("\t", '&nbsp;&nbsp;&nbsp;', htmlspecialchars($query_data['q']))), '
		</a>
		<br>';

		if (!empty($query_data['f']) && !empty($query_data['l']))
			echo sprintf($txt['debug_query_in_line'], $query_data['f'], $query_data['l']);

		if (isset($query_data['s'], $query_data['t'], $txt['debug_query_which_took_at']))
			echo sprintf($txt['debug_query_which_took_at'], round($query_data['t'], 8), round($query_data['s'], 8));
		elseif (isset($query_data['t']))
			echo sprintf($txt['debug_query_which_took'], round($query_data['t'], 8));

		echo '
	</div>';

		// Explain the query.
		if ($query_id == $q && $is_select_query)
		{
			$result = wesql::query('
				EXPLAIN ' . $select,
				array(
				)
			);
			if ($result === false)
			{
				echo '
	<table class="cp4" style="margin-bottom: 2ex">
		<tr><td>', wesql::error($db_connection), '</td></tr>
	</table>';
				continue;
			}

			$row = wesql::fetch_assoc($result);

			echo '
	<table class="cp4" rules="all" style="margin-bottom: 2ex">
		<tr>
			<th>' . implode('</th>
			<th>', array_keys($row)) . '</th>
		</tr>';

			wesql::data_seek($result, 0);
			while ($row = wesql::fetch_assoc($result))
				echo '
		<tr>
			<td>' . implode('</td>
			<td>', $row) . '</td>
		</tr>';

			wesql::free_result($result);

			echo '
	</table>';
		}
	}

	echo '
	</div>
</body>
</html>';

	obExit(false);
}
