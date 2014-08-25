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
	// Nothing to say!
}

function template_home_topics($n = 0)
{
	global $txt;

	$n = $n ?: isset($_REQUEST['n']) ? (int) $_REQUEST['n'] : 5;
	$next = $n < 50 ? ($n < 20 ? ($n < 10 ? 10 : 20) : 50) : 100;

	echo '
	<we:cat style="margin-top: 16px">', $n == $next ? '' : '
		<span class="floatright"><a href="<URL>?action=boards">' . $txt['board_index'] . '</a></span>', '
		', $txt['recent_posts'], '
		<a href="?n=' . $next . '" class="middle" style="display: inline-block; height: 16px"><div class="floatleft foldable"></div></a>', '
	</we:cat>
	<we:block class="tborder wide" style="padding: 2px; border: 1px solid #dcc; border-radius: 5px">
		<table class="homeposts cs0">';

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
					<div>' . $post['time'] . ' ' . $txt['by'] . ' ' . $post['poster']['link'] . '</div>
				</td>', '
				<td class="latestp2">', $is_mobile ? '
					' . $post['time'] . ' ' . $txt['by'] . ' ' . $post['poster']['link'] . '<br>' : '', '
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

// Output a custom introduction. HTML is accepted, unfiltered.
function template_home_blurb()
{
	global $settings;

	if (isset($settings['homepage_blurb_' . we::$user['language']]))
		$lang = we::$user['language'];
	elseif (isset($settings['homepage_blurb_' . $settings['language']]))
		$lang = $settings['language'];
	else
		return;

	if (!SKIN_MOBILE)
	{
		if (!empty($settings['homepage_blurb_title_' . $lang]))
			echo '
	<we:cat class="wtop">
		', $settings['homepage_blurb_title_' . $lang], '
	</we:cat>';

		echo '
	<div class="windowbg2 wide home-intro">
		<div class="wrc">', str_replace("\n", '<br>', $settings['homepage_blurb_' . $lang]), '</div>
	</div>';
	}
}
