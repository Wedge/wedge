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
	global $context, $txt, $boardurl;

	echo '
	<we:cat class="wtop">
		', $txt['wedge_home_title'], '
	</we:cat>';

	if (!SKIN_MOBILE)
		echo '
	<div class="home-intro">
		<img src="http://wedge.org/wedge.png" style="width: 130px; height: 135px; float: left; margin-top: 0" />
		<div class="windowbg2 wrc" style="margin: 16px 0 0 146px">', $txt['wedge_home_intro'], '
		</div>
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
	$naoboards = ssi_recentTopicTitles($n, we::$is_admin || ($boardurl != 'http://wedge.org') ? null : array(136), null, 'naos');
	$nb_new = get_unread_numbers($naoboards);

	$alt = '';
	foreach ($naoboards as $post)
	{
		$safe = strpos($post['board']['url'], '/pub') === false;
		$blo = strpos($post['board']['url'], '/blog') !== false;
		$alt = $alt ? '' : '2';
		echo '
			<tr class="windowbg', $alt, '">
				<td class="latestp1">
					<div>', strftime('%d/%m %H:%M', $post['timestamp']), '<br>', $post['poster']['link'], '</div>
				</td>
				<td class="latestp2">
					', $post['board']['link'], ' &gt; ';

		if ($post['is_new'] && we::$is_member)
			echo isset($nb_new[$post['topic']]) ? '<a href="' . $post['href'] . '" class="note">' . $nb_new[$post['topic']] . '</a> ' : '';

		echo '<a href="', $post['href'], $safe ? '" style="color: ' . ($blo ? '#a62' : 'green') : '', '">', $post['subject'], '</a>
				</td>
			</tr>';
		}

	echo '
		</table>
	</we:block>';
}
