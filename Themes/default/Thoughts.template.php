<?php
/**
 * Wedge
 *
 * Displays thoughts. Yup.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_showThoughts()
{
	global $context, $theme, $txt;

	echo '
		<we:cat>
			<div class="thought_icon"></div>
			', $txt['showThoughts'], '
		</we:cat>
		<table class="windowbg wrc w100 cp8 cs0 thought_list" id="thought_thread">';

	// !! @todo: allow editing & replying to thoughts directly from within the thread...?
	// onclick="oThought.edit(', $thought['id'], !empty($thought['id_master']) && $thought['id'] != $thought['id_master'] ? ', ' . $thought['id_master'] : '', ');"

	$col = 2;
	foreach ($context['thoughts'] as $thought)
	{
		$col = empty($col) ? 2 : '';
		echo '
			<tr><td class="windowbg', $col, ' thought"><ul><li id="t', $thought['id'], '">
				<div><a href="<URL>?action=profile;u=', $thought['id_member'], '">', $thought['owner_name'], '</a> <span class="date">(', $thought['updated'], ')</span> &raquo; ', $thought['text'], '</div>';

		if (!empty($thought['sub']))
			template_sub_thoughts($thought);

		echo '
			</li></ul></td></tr>';
	}
	echo '
		</table>';
}

function template_sub_thoughts(&$thought)
{
	if (empty($thought['sub']))
		return;

	// !! @todo: see above...
	echo '<ul>';
	foreach ($thought['sub'] as $tho)
	{
		echo '<li id="t', $tho['id'], '"><div>', empty($tho['owner_name']) ? '' : '<a href="<URL>?action=profile;u=' . $tho['id_member'] . '">' .
			$tho['owner_name'] . '</a> <span class="date">(' . $tho['updated'] . ')</span> &raquo; ', parse_bbc($tho['text']), '</div>';

		if (!empty($tho['sub']))
			template_sub_thoughts($tho);

		echo '</li>';
	}
	echo '</ul>';
}

function template_showMemberThoughts()
{
	global $context, $theme, $txt;

	echo '
		<we:cat>
			<img src="', $theme['images_url'], '/icons/profile_sm.gif">
			', $txt['showThoughts'], ' - ', $context['member']['name'], ' (', $context['total_thoughts'], ')
		</we:cat>
		<div class="pagesection">
			', $context['page_index'], '
		</div>
		<table class="windowbg wrc w100 cp8 cs0 thought_list" id="profile_thoughts">';

	// !! @todo: allow editing & replying to thoughts directly from within the Profile area...?
	// onclick="oThought.edit(', $thought['id'], !empty($thought['id_master']) && $thought['id'] != $thought['id_master'] ? ', ' . $thought['id_master'] : '', ');"

	// @worg!!
	$privacy_icon = array(
		-3 => 'everyone',
		0 => 'members',
		5 => 'justme',
		20 => 'friends',
	);

	$col = 2;
	foreach ($context['thoughts'] as $thought)
	{
		$col = empty($col) ? 2 : '';
		$id = $thought['id'];
		echo '
			<tr class="windowbg', $col, '">
				<td class="bc', $col, '"><a href="<URL>?action=thoughts;in=', $thought['id_master'] ? $thought['id_master'] : $id, '#t', $id, '"><img src="', $theme['images_url'], '/icons/last_post.gif" class="middle"></a> ', $thought['updated'], '</td>
				<td><div>', $thought['privacy'] != -3 ? '<div class="privacy_' . @$privacy_icon[$thought['privacy']] . '"></div>' : '', '<a href="<URL>?action=profile;u=', $thought['id_member'], '" id="t', $id, '>',
				$thought['owner_name'], '</a> &raquo; ', $thought['text'], '</div></td>
			</li></ul></td></tr>';
	}
	echo '
		</table>
		<div class="pagesection">
			', $context['page_index'], '
		</div>';
}

?>