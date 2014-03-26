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
	global $context, $txt;

	echo '
	<we:cat class="wtop">
		', $txt['home_title'], '
	</we:cat>';

	if (!SKIN_MOBILE)
		echo '
	<div class="home-intro">
		<div class="windowbg2 wrc">', $txt['home_intro'], '</div>
	</div>';

	if (!$context['home_show']['topics'])
		return;

	$n = isset($_REQUEST['n']) ? (int) $_REQUEST['n'] : 5;
	$next = $n < 50 ? ($n < 20 ? ($n < 10 ? 10 : 20) : 50) : 100;

	echo '
	<we:cat style="margin-top: 16px">', $n == $next ? '' : '
		<span class="floatright"><a href="<URL>?action=boards">' . $txt['board_index'] . '</a></span>', '
		<a href="?n=' . $next . '" class="middle" style="display: inline-block; height: 16px"><div class="floatleft foldable"></div></a>', '
		', $txt['recent_posts'], '
	</we:cat>
	<we:block class="tborder" style="margin: 5px 0 15px; padding: 2px; border: 1px solid #dcc; border-radius: 5px">
		<table class="homeposts w100 cs0">';

	loadSource('../SSI');
	$boards = ssi_recentTopicTitles($n, null, null, 'naos');
	$nb_new = get_unread_numbers($boards);

	$alt = '';
	$is_mobile = we::is('mobile');
	foreach ($boards as $post)
	{
		$alt = $alt ? '' : '2';
		echo '
			<tr class="windowbg', $alt, '">', $is_mobile ? '' : '
				<td class="latestp1">
					<div>' . timeformat($post['timestamp']) . ' ' . $txt['by'] . ' ' . $post['poster']['link'] . '</div>
				</td>', '
				<td class="latestp2">', $is_mobile ? '
					' . timeformat($post['timestamp']) . ' ' . $txt['by'] . ' ' . $post['poster']['link'] . '<br>' : '', '
					', $post['board']['link'], ' &gt; ';

		if ($post['is_new'] && we::$is_member)
			echo isset($nb_new[$post['topic']]) ? '<a href="' . $post['href'] . '" class="note">' . $nb_new[$post['topic']] . '</a> ' : '';

		echo '<a href="', $post['href'], '">', $post['subject'], '</a>
				</td>
			</tr>';
	}

	echo '
		</table>
	</we:block>';
}
