<?php
/**
 * Wedge
 *
 * Lists thought threads and recent thoughts.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

// The infamous thought system from Noisen.com...
// Not exactly optimized for speed, but we can always rewrite it later.
function Thoughts()
{
	global $context, $txt, $user_info;

	// Some initial context.
	loadTemplate('Thoughts');
	loadLanguage('Profile');
	wetem::load('showThoughts');
	$context['page_title'] = $txt['showThoughts'];
	$master = isset($_REQUEST['in']) ? (int) $_REQUEST['in'] : 0;

	// If we're not in a specific thought thread, we're asking for the latest thoughts.
	if (!$master)
		return latestThoughts();

	$context['thoughts'] = $thoughts = array();
	$request = wesql::query('
		SELECT id_thought
		FROM {db_prefix}thoughts
		WHERE id_master = {int:id_master}
		OR id_thought = {int:id_master}',
		array(
			'id_master' => $master,
		)
	);
	$think = array();
	while ($row = wesql::fetch_row($request))
		$think[] = $row[0];
	wesql::free_result($request);

	if (!empty($think))
	{
		$request = wesql::query('
			SELECT
				h.updated, h.thought, h.id_thought, h.id_parent, h.id_member,
				h.id_master, h2.id_member AS id_parent_owner,
				m.real_name AS owner_name, m2.real_name AS parent_name
			FROM
				{db_prefix}thoughts AS h
			LEFT JOIN
				{db_prefix}members AS m ON (h.id_member = m.id_member)
			LEFT JOIN
				{db_prefix}thoughts AS h2 ON (h.id_parent = h2.id_thought)
			LEFT JOIN
				{db_prefix}members AS m2 ON (h2.id_member = m2.id_member)
			WHERE (
				h.id_thought IN ({array_int:think})
				OR h.id_master IN ({array_int:think})
				OR h.id_parent IN ({array_int:think})
			)
			AND (
				h.id_member = {int:me}
				OR h.privacy = {int:everyone}' . (we::$is_guest ? '' : '
				OR h.privacy = {int:members}') . '
				OR FIND_IN_SET(' . implode(', h.privacy)
				OR FIND_IN_SET(', $user_info['groups']) . ', h.privacy)
			)
			ORDER BY h.id_thought',
			array(
				'think' => $think,
				'me' => we::$id,
				'everyone' => -3,
				'members' => 0,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$thought = array(
				'id' => $row['id_thought'],
				'id_member' => $row['id_member'],
				'id_parent' => $row['id_parent'],
				'id_master' => $row['id_master'],
				'id_parent_owner' => $row['id_parent_owner'],
				'owner_name' => $row['owner_name'],
				'updated' => timeformat($row['updated']),
				'text' => $row['thought'],
			);

			if (empty($row['id_parent_owner']) && !empty($thoughts))
			{
				if (!isset($txt['deleted_thought']))
					loadLanguage('Post');
				$row['id_parent_owner'] = 0;
				$thoughts[$row['id_master']]['sub'][$row['id_parent']] = array(
					'id' => $row['id_parent'],
					'id_member' => 0,
					'id_parent' => $row['id_master'],
					'id_master' => $row['id_master'],
					'id_parent_owner' => $thoughts[$row['id_master']]['id_member'],
					'owner_name' => '',
					'updated' => timeformat($row['updated']),
					'text' => $txt['deleted_thought'],
				);
			}

			if (empty($thought['id_master'])) // !! Alternatively, add: || $row['id_master'] != $row['id_member']
				$thoughts[$thought['id']] = $thought;
			else
			{
				if (!isset($thoughts[$row['id_master']]))
				{
					if (empty($row['parent_name']) && !isset($txt['deleted_thought']))
						loadLanguage('Post');
					$thought['text'] = (empty($row['parent_name']) ? '@' . $txt['deleted_thought'] : '@<a href="<URL>?action=profile;u=' . $row['id_parent_owner'] . '">' . $row['parent_name'] . '</a>') . '&gt; ' . parse_bbc_inline($row['thought']);
					$thoughts[$row['id_master']] = $thought;
				}
				elseif ($row['id_master'] === $row['id_parent'] || !isset($thoughts[$row['id_master']]['sub']))
					$thoughts[$row['id_master']]['sub'][$row['id_thought']] = $thought;
				else
					populate_sub_thoughts($thoughts[$row['id_master']]['sub'], $thought);
			}
		}
		wesql::free_result($request);

		foreach (array_reverse(array_keys($thoughts)) as $nb)
			$context['thoughts'][$nb] = $thoughts[$nb];
	}
}

function populate_sub_thoughts(&$here, &$thought)
{
	foreach ($here as &$tho)
	{
		if ($tho['id'] === $thought['id_parent'])
			$tho['sub'][$thought['id']] = $thought;
		elseif (isset($tho['sub']))
			populate_sub_thoughts($tho['sub'], $thought);
	}
}

function latestThoughts($memID = 0)
{
	global $context, $txt, $user_info;

	// Some initial context.
	loadTemplate('Thoughts');
	wetem::load('showLatestThoughts');
	$context['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
	$thoughts_per_page = 20;

	$request = wesql::query('
		SELECT COUNT(h.id_thought)
		FROM {db_prefix}thoughts AS h
		WHERE ' . (!$memID ? '1=1' : 'h.id_member = {int:id_member}') . ($memID && (we::$id == $memID) ? '' : '
		AND (' . ($memID ? '' : '
			h.id_member = {int:me}
			OR ') . '
			h.privacy = {int:everyone}' . (we::$is_guest ? '' : '
			OR h.privacy = {int:members}') . '
			OR FIND_IN_SET(' . implode(', h.privacy)
			OR FIND_IN_SET(', $user_info['groups']) . ', h.privacy)
		)') . '
		LIMIT 1',
		array(
			'me' => we::$id,
			'id_member' => $memID,
			'everyone' => -3,
			'members' => 0,
		)
	);
	list ($total_thoughts) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Because I'm too lazy to show this properly...
	$context['page_title'] = $txt['showThoughts'] . (empty($context['member']) ? '' : ' - ' . $context['member']['name']) . ' (' . $total_thoughts . ')';
	$context['page_index'] = template_page_index($memID ? '<URL>?action=profile;u=' . $memID . ';area=thoughts' : '<URL>?action=thoughts', $context['start'], $total_thoughts, $thoughts_per_page);
	$context['total_thoughts'] = $total_thoughts;

	// Now we get our thought list from this member.
	$context['thoughts'] = $thoughts = array();
	$request = wesql::query('
		SELECT h.id_thought
		FROM {db_prefix}thoughts AS h
		WHERE ' . (!$memID ? '1=1' : 'h.id_member = {int:id_member}') . ($memID && (we::$id == $memID) ? '' : '
		AND (' . ($memID ? '' : '
			h.id_member = {int:me}
			OR ') . '
			h.privacy = {int:everyone}' . (we::$is_guest ? '' : '
			OR h.privacy = {int:members}') . '
			OR FIND_IN_SET(' . implode(', h.privacy)
			OR FIND_IN_SET(', $user_info['groups']) . ', h.privacy)
		)') . '
		ORDER BY h.id_thought DESC
		LIMIT {int:start}, {int:per_page}',
		array(
			'me' => we::$id,
			'id_member' => $memID,
			'everyone' => -3,
			'members' => 0,
			'start' => floor($context['start'] / $thoughts_per_page) * $thoughts_per_page,
			'per_page' => $thoughts_per_page,
		)
	);
	$think = array();
	while ($row = wesql::fetch_row($request))
		$think[] = $row[0];
	wesql::free_result($request);

	// We'll need to get data for: this user's thoughts, its parents, and one or no children.
	if (!empty($think))
	{
		$request = wesql::query('
			SELECT
				h.updated, h.thought, h.id_thought, h.id_parent, h.id_member,
				h.id_master, h_parent.id_member AS id_parent_owner, h.privacy,
				m.real_name AS owner_name, m_parent.real_name AS parent_name,
				h_child.id_thought > 0 AS has_children
			FROM
				{db_prefix}thoughts AS h
			LEFT JOIN
				{db_prefix}members AS m ON (h.id_member = m.id_member)
			LEFT JOIN
				{db_prefix}thoughts AS h_parent ON (h_parent.id_thought = h.id_parent AND (
					h_parent.id_member = {int:me}
					OR h_parent.privacy = {int:everyone}' . (we::$is_guest ? '' : '
					OR h_parent.privacy = {int:members}') . '
					OR FIND_IN_SET(' . implode(', h_parent.privacy)
					OR FIND_IN_SET(', $user_info['groups']) . ', h_parent.privacy)
				))
			LEFT JOIN
				{db_prefix}thoughts AS h_child ON (h_child.id_parent = h.id_thought)
			LEFT JOIN
				{db_prefix}members AS m_parent ON (h_parent.id_member = m_parent.id_member)
			WHERE
				h.id_thought IN ({array_int:think})
			GROUP BY h.id_thought
			ORDER BY h.id_thought DESC',
			array(
				'think' => $think,
				'me' => we::$id,
				'everyone' => -3,
				'members' => 0,
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			$thought = array(
				'id' => $row['id_thought'],
				'id_member' => $row['id_member'],
				'id_parent' => $row['id_parent'],
				'id_master' => $row['id_master'],
				'id_parent_owner' => $row['id_parent_owner'],
				'owner_name' => $row['owner_name'],
				'updated' => timeformat($row['updated']),
				'text' => $row['thought'],
				'privacy' => $row['privacy'],
				'has_children' => $row['has_children'],
			);

			if (!empty($thought['id_master']))
			{
				if (empty($thought['parent_name']) && !isset($txt['deleted_thought']))
					loadLanguage('Post');
				$thought['text'] = (empty($row['parent_name']) ? '@' . $txt['deleted_thought'] : '@<a href="<URL>?action=profile;u=' . $row['id_parent_owner'] . '">' . $row['parent_name'] . '</a>') . '&gt; ' . parse_bbc_inline($row['thought']);
			}
			if ($row['has_children'])
				$thought['text'] .= ' <em>(&hellip;)</em>';
			$context['thoughts'][$thought['id']] = $thought;
		}
		wesql::free_result($request);
	}
}

?>