<?php
/**
 * Wedge
 *
 * Displays the custom homepage. Hack away!
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_main()
{
	echo '
	<div class="windowbg2 wrc">
		<h1>
			Welcome, hacker!
		</h1>
		<ul>
			<li><a href="<URL>?action=boards">Board index</a></li>
			<li><a href="<URL>?action=pm">Personal messages</a></li>
		</ul>
	</div>';
}

function template_info_before()
{
	echo '
		<div class="roundframe" style="margin: 16px 0">';
}

// This one is just here to show you that layers can get _before_before,
// _before_override, _before_after and _after_* overrides ;)
// It only works on layers, though!
function template_info_center_before_after()
{
	echo '
		<div style="height: 8px"></div>';
}

function template_info_after()
{
	echo '
		</div>';
}

function template_thoughts_before()
{
	echo '
	<div class="roundframe" style="margin: 16px 0">';
}

function template_thoughts_after()
{
	echo '
	</div>';
}

function template_thoughts($limit = 18)
{
	global $txt, $user_info, $context;

	if (isset($_GET['s']) && $_GET['s'] === 'thoughts')
		$limit = 30;

	if ($limit !== 30)
		echo '
		<we:title>
			<div class="thought_icon"></div>
			', $txt['thoughts'], '... (<a href="<URL>?s=thoughts">', $txt['all_pages'], '</a>)
		</we:title>';

	echo '
		<div class="tborder" style="margin: 5px 0 10px 0">
		<table class="w100 cp0 cs0 thought_list">';

	$request = wesql::query('
		SELECT COUNT(id_thought)
		FROM {db_prefix}thoughts
		LIMIT 1', array()
	);
	list ($total_thoughts) = wesql::fetch_row($request);
	wesql::free_result($request);

	$page = isset($_GET['p']) ? $_GET['p'] : 0;

	$request = wesql::query('
		SELECT
			h.updated, h.thought, h.id_thought, h.id_parent, h.privacy,
			h.id_member, h.id_master, h2.id_member AS id_parent_owner,
			m.real_name AS owner_name, mp.real_name AS parent_name, m.posts
		FROM {db_prefix}thoughts AS h
		LEFT JOIN {db_prefix}thoughts AS h2 ON (h.id_parent = h2.id_thought)
		LEFT JOIN {db_prefix}members AS m ON (h.id_member = m.id_member)
		LEFT JOIN {db_prefix}members AS mp ON (h2.id_member = mp.id_member)
		WHERE h.id_member = {int:me}
			OR (h.privacy' . ($user_info['is_guest'] ? ' IN (0, 1))' : ' IN (0, 1, 2))
			OR (h.privacy = 3 AND (FIND_IN_SET({int:me}, m.buddy_list) != 0))') . '
		ORDER BY h.id_thought DESC LIMIT ' . ($page * 30) . ', ' . $limit,
		array(
			'me' => $user_info['id']
		)
	);
	$can_think = !$user_info['is_guest'];
	$thoughts = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$id = $row['id_thought'];
		$mid = $row['id_master'];
		$thoughts[$row['id_thought']] = array(
			'id' => $row['id_thought'],
			'id_member' => $row['id_member'],
			'id_parent' => $row['id_parent'],
			'id_master' => $mid,
			'id_parent_owner' => $row['id_parent_owner'],
			'owner_name' => $row['owner_name'],
			'privacy' => $row['privacy'],
			'updated' => timeformat($row['updated']),
			'text' => $row['posts'] < 10 ? preg_replace('~\</?a(?:\s[^>]+)?\>(?:https?://)?~', '', parse_bbc_inline($row['thought'])) : parse_bbc_inline($row['thought']),
		);

		$thought =& $thoughts[$row['id_thought']];
		$thought['text'] = '<span class="thought" id="thought_update' . $id . '" data-oid="' . $id . '" data-prv="' . $thought['privacy'] . '"' . ($can_think ? ' data-tid="' . $id . '"' . ($mid && $mid != $id ? ' data-mid="' . $mid . '"' : '') : '') . '><span>' . $thought['text'] . '</span></span>';

		if (!empty($row['id_parent_owner']))
			$thought['text'] = '@<a href="<URL>?action=profile;u=' . $row['id_parent_owner'] . ';area=thoughts#t' . $row['id_parent'] . '" class="bbc_link">' . $row['parent_name'] . '</a>&gt; ' . $thought['text'];
	}
	wesql::free_result($request);
	unset($thought);

	$context['page_index'] = constructPageIndex('<URL>?s=thoughts;p=%1$d', $page, round($total_thoughts / 30), 1, true);
	if ($limit === 30)
		echo '
			<tr><td colspan="2" class="titlebg" style="padding: 4px">', $txt['pages'], ': ', $context['page_index'], '</td></tr>';

	if ($can_think)
		echo '
			<tr id="new_thought">
				<td class="bc">{date}</td><td class="windowbg thought">{uname} &raquo; {text}</td>
			</tr>';

	foreach ($thoughts as $id => $thought)
	{
		$col = empty($col) ? 2 : '';
		echo '
			<tr>
				<td class="bc', $col, '">', $thought['updated'], '</td>
				<td class="windowbg', $col, ' thought"><a id="t', $id, '"></a><a href="<URL>?action=profile;u=', $thought['id_member'], '">',
				$thought['owner_name'], '</a> &raquo; ', $thought['text'], '</td>
			</tr>';
	}

	echo $limit != 30 ? '' : '
			<tr><td colspan="2" class="titlebg" style="padding: 4px">' . $txt['pages'] . ': ' . $context['page_index'] . '</td></tr>', '
		</table>
		</div>';
}

?>