<?php
/**
 * Displays the custom homepage. Hack away!
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

function template_main()
{
	global $context;

	echo '
	<div class="windowbg2 wrc">
		<h6>
			Welcome, hacker!
		</h6>
		<ul style="margin-bottom: 0">
			<li><a href="<URL>?action=boards">Board index</a></li>';

	if (!empty($context['allow_pm']))
		echo '
			<li><a href="<URL>?action=pm">Personal messages</a></li>';

	echo '
		</ul>
	</div>';
}

function template_info_before()
{
	global $txt;

	echo '
	<we:block class="windowbg" style="margin: 16px 0">
		<header>
			', $txt['info_center_title'], '
		</header>';
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
	</we:block>';
}

function template_thoughts_before()
{
	echo '
	<we:block class="windowbg2" style="margin: 16px 0">';
}

function template_thoughts_after()
{
	echo '
	</we:block>';
}

function template_thoughts($limit = 18)
{
	global $txt, $context;

	$is_thought_page = isset($_GET['s']) && $_GET['s'] === 'thoughts';

	if (!$is_thought_page)
		echo '
		<header>
			<div class="thought_icon"></div>
			', $txt['thoughts'], '... (<a href="<URL>?s=thoughts">', $txt['all_pages'], '</a>)
		</header>';

	echo '
		<div class="tborder" style="margin: 5px 0 10px 0">
		<table class="w100 cp0 cs0 thought_list">';

	if ($is_thought_page)
		echo '
			<tr><td colspan="2" class="titlebg" style="padding: 4px">', $txt['pages'], ': ', $context['page_index'], '</td></tr>';

	foreach ($context['thoughts'] as $id => $thought)
	{
		$col = empty($col) ? 2 : '';
		echo '
			<tr>
				<td class="bc', $col, '">', $thought['updated'], '</td>
				<td class="windowbg', $col, ' thought"><a id="t', $id, '"></a><a href="<URL>?action=profile;u=', $thought['id_member'], '">',
				$thought['owner_name'], '</a> &raquo; ', $thought['text'], '</td>
			</tr>';
	}

	if ($is_thought_page)
		echo '
			<tr><td colspan="2" class="titlebg" style="padding: 4px">', $txt['pages'], ': ', $context['page_index'], '</td></tr>';

	echo '
		</table>
		</div>';
}
