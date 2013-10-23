<?php
/**
 * Displays forum statistics.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

function template_main()
{
	global $context, $theme, $txt, $settings;

	echo '
		<we:cat>
			', $context['page_title'], '
		</we:cat>
		<we:title2 style="margin-bottom: 8px">
			<img src="', $theme['images_url'], '/stats_info.gif">
			', $txt['general_stats'], '
		</we:title2>

		<div class="two-columns"><div class="windowbg wrc top_row">
			<dl class="stats">
				<dt>', $txt['total_members'], ':</dt>
				<dd>', $context['show_member_list'] ? '<a href="<URL>?action=mlist">' . $context['num_members'] . '</a>' : $context['num_members'], '</dd>
				<dt>', $txt['total_posts'], ':</dt>
				<dd>', $context['num_posts'], '</dd>
				<dt>', $txt['total_topics'], ':</dt>
				<dd>', $context['num_topics'], '</dd>
				<dt>', $txt['total_boards'], ':</dt>
				<dd>', $context['num_boards'], '</dd>
				<dt>', $txt['total_cats'], ':</dt>
				<dd>', $context['num_categories'], '</dd>
				<dt>', $txt['latest_member'], ':</dt>
				<dd>', $context['common_stats']['latest_member']['link'], '</dd>
				<dt>', $txt['gender_ratio'], ':</dt>
				<dd>', $context['gender']['ratio'], '</dd>';

	if (!empty($settings['hitStats']))
		echo '
				<dt>', $txt['num_hits'], ':</dt>
				<dd>', $context['num_hits'], '</dd>';

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg2 wrc top_row">
			<dl class="stats">
				<dt>', $txt['average_members'], ':</dt>
				<dd>', $context['average_members'], '</dd>
				<dt>', $txt['average_posts'], ':</dt>
				<dd>', $context['average_posts'], '</dd>
				<dt>', $txt['average_topics'], ':</dt>
				<dd>', $context['average_topics'], '</dd>
				<dt>', $txt['average_online'], ':</dt>
				<dd>', $context['average_online'], '</dd>
				<dt>', $txt['users_online_today'], ':</dt>
				<dd>', $context['online_today'], '</dd>
				<dt>', $txt['users_online'], ':</dt>
				<dd>', $context['users_online'], '</dd>';

	if (allowedTo('moderate_forum'))
		echo '
				<dt>', $txt['most_online_ever'], ':</dt>
				<dd>', $context['most_members_online']['number'], ' (', $context['most_members_online']['date'], ')</dd>';

	if (!empty($settings['hitStats']))
		echo '
				<dt>', $txt['average_hits'], ':</dt>
				<dd>', $context['average_hits'], '</dd>';

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg2 wrc">
			<h6>
				<img src="', $theme['images_url'], '/stats_posters.gif">
				', $txt['top_posters'], '
			</h6>
			<dl class="stats">';

	foreach ($context['top_posters'] as $poster)
	{
		echo '
				<dt>
					', $poster['link'], '
				</dt>
				<dd>';

		if (!empty($poster['post_percent']))
			echo '
					<div class="bar" style="width: ', $poster['post_percent'] + 4, 'px"></div>';

		echo '
					<span>', $poster['num_posts'], '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg wrc">
			<h6>
				<img src="', $theme['images_url'], '/stats_board.gif">
				', $txt['top_boards'], '
			</h6>
			<dl class="stats">';

	foreach ($context['top_boards'] as $board)
	{
		echo '
				<dt>
					', $board['link'], '
				</dt>
				<dd>';

		if (!empty($board['post_percent']))
			echo '
					<div class="bar" style="width: ', $board['post_percent'] + 4, 'px"></div>';

		echo '
					<span>', $board['num_posts'], '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg wrc">
			<h6>
				<img src="', $theme['images_url'], '/stats_replies.gif">
				', $txt['top_topics_replies'], '
			</h6>
			<dl class="stats">';

	foreach ($context['top_topics_replies'] as $topic)
	{
		echo '
				<dt>
					', $topic['link'], '
				</dt>
				<dd>';

		if (!empty($topic['post_percent']))
			echo '
					<div class="bar" style="width: ', $topic['post_percent'] + 4, 'px"></div>';

		echo '
					<span>' . $topic['num_replies'] . '</span>
				</dd>';
	}
	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg2 wrc">
			<h6>
				<img src="', $theme['images_url'], '/stats_views.gif">
				', $txt['top_topics_views'], '
			</h6>
			<dl class="stats">';

	foreach ($context['top_topics_views'] as $topic)
	{
		echo '
				<dt>', $topic['link'], '</dt>
				<dd>';

		if (!empty($topic['post_percent']))
			echo '
					<div class="bar" style="width: ', $topic['post_percent'] + 4, 'px"></div>';

		echo '
					<span>' . $topic['num_views'] . '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg2 wrc">
			<h6>
				<img src="', $theme['images_url'], '/stats_replies.gif">
				', $txt['top_starters'], '
			</h6>
			<dl class="stats">';

	foreach ($context['top_starters'] as $poster)
	{
		echo '
				<dt>
					', $poster['link'], '
				</dt>
				<dd>';

		if (!empty($poster['post_percent']))
			echo '
					<div class="bar" style="width: ', $poster['post_percent'] + 4, 'px"></div>';

		echo '
					<span>', $poster['num_topics'], '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg wrc">
			<h6>
				<img src="', $theme['images_url'], '/stats_views.gif">
				', $txt['most_time_online'], '
			</h6>
			<dl class="stats">';

	foreach ($context['top_time_online'] as $poster)
	{
		echo '
				<dt>
					', $poster['link'], '
				</dt>
				<dd>';

		if (!empty($poster['time_percent']))
			echo '
					<div class="bar" style="width: ', $poster['time_percent'] + 4, 'px"></div>';

		echo '
					<span>', $poster['time_online'], '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg wrc">
			<h6>
				<span class="like_button" style="vertical-align: 2px; padding: 0 11px 0"></span>
				', $txt['top_liked'], '
			</h6>
			<dl class="stats">';

	foreach ($context['top_likes'] as $like)
	{
		echo '
				<dt>
					', $like['link'], '
				</dt>
				<dd>';

		if (!empty($like['post_percent']))
			echo '
					<div class="bar" style="width: ', $like['post_percent'] + 4, 'px"></div>';

		echo '
					<span>', $like['num_likes'], '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg2 wrc">
			<h6>
				<span class="like_button" style="vertical-align: 2px; padding: 0 11px 0"></span>
				', $txt['top_liked_posters'], '
			</h6>
			<dl class="stats">';

	foreach ($context['top_author_likes'] as $like)
	{
		echo '
				<dt>
					<a href="<URL>?action=profile;u=', $like['id_member'], '">', $like['member_name'], '</a>
				</dt>
				<dd>';

		if (!empty($like['post_percent']))
			echo '
					<div class="bar" style="width: ', $like['post_percent'] + 4, 'px"></div>';

		echo '
					<span>', $like['num_likes'], '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>

		<br>
		<div class="flow_hidden clear">
			<we:title2>
				<img src="', $theme['images_url'], '/stats_history.gif">
				', $txt['forum_history'], '
			</we:title2>';

	if (!empty($context['yearly']))
	{
		echo '
			<table class="table_grid w100 cs0 cp4" id="stats_history">
				<thead>
					<tr class="titlebg">
						<th class="w25">', $txt['yearly_summary'], '</th>
						<th>', $txt['stats_new_topics'], '</th>
						<th>', $txt['stats_new_posts'], '</th>
						<th>', $txt['stats_new_members'], '</th>';

		if (allowedTo('moderate_forum'))
			echo '
						<th>', $txt['most_online'], '</th>';

		if (!empty($settings['hitStats']))
			echo '
						<th>', $txt['page_views'], '</th>';

		echo '
					</tr>
				</thead>
				<tbody>';

		foreach ($context['yearly'] as $id => $year)
		{
			echo '
					<tr class="windowbg2" id="year_', $id, '">
						<th class="year">
							<span class="foldable fold" id="year_img_', $id, '"></span>
							<a href="#year_', $id, '" id="year_link_', $id, '">', $year['year'], '</a>
						</th>
						<th>', $year['new_topics'], '</th>
						<th>', $year['new_posts'], '</th>
						<th>', $year['new_members'], '</th>';

			if (allowedTo('moderate_forum'))
				echo '
						<th>', $year['most_members_online'], '</th>';

			if (!empty($settings['hitStats']))
				echo '
						<th>', $year['hits'], '</th>';

			echo '
					</tr>';

			foreach ($year['months'] as $month)
			{
				echo '
					<tr class="windowbg2" id="tr_month_', $month['id'], '">
						<th class="month">
							<span class="foldable', $month['expanded'] ? ' fold' : '', '" id="img_', $month['id'], '"></span>
							<a id="m', $month['id'], '" href="', $month['href'], '">', $month['month'], ' ', $month['year'], '</a>
						</th>
						<th>', $month['new_topics'], '</th>
						<th>', $month['new_posts'], '</th>
						<th>', $month['new_members'], '</th>';

			if (allowedTo('moderate_forum'))
				echo '
						<th>', $month['most_members_online'], '</th>';

				if (!empty($settings['hitStats']))
					echo '
						<th>', $month['hits'], '</th>';

				echo '
					</tr>';

				if ($month['expanded'])
				{
					foreach ($month['days'] as $day)
					{
						echo '
					<tr class="windowbg2" id="tr_day_', $day['year'], '-', $day['month'], '-', $day['day'], '">
						<td class="day">', $day['year'], '-', $day['month'], '-', $day['day'], '</td>
						<td>', $day['new_topics'], '</td>
						<td>', $day['new_posts'], '</td>
						<td>', $day['new_members'], '</td>';

						if (allowedTo('moderate_forum'))
							echo '
						<td>', $day['most_members_online'], '</td>';

						if (!empty($settings['hitStats']))
							echo '
						<td>', $day['hits'], '</td>';

						echo '
					</tr>';
					}
				}
			}
		}

		echo '
				</tbody>
			</table>
		</div>';

		add_js_file('scripts/stats.js');

		add_js('
	var oStatsCenter = new weStatsCenter({
		reYearPattern: /year_(\d+)/,
		sYearImageIdPrefix: \'year_img_\',
		sYearLinkIdPrefix: \'year_link_\',

		reMonthPattern: /tr_month_(\d+)/,
		sMonthImageIdPrefix: \'img_\',
		sMonthLinkIdPrefix: \'m\',

		reDayPattern: /tr_day_(\d+-\d+-\d+)/,
		sDayRowClassname: \'windowbg2\',
		sDayRowIdPrefix: \'tr_day_\',

		aCollapsedYears: [');

		foreach ($context['collapsed_years'] as $id => $year)
			add_js('
			\'' . $year . '\'' . ($id != count($context['collapsed_years']) - 1 ? ',' : ''));

		add_js('
		],

		aDataCells: [
			\'date\',
			\'new_topics\',
			\'new_posts\',
			\'new_members\'' . (!allowedTo('moderate_forum') ? '' : ',
			\'most_members_online\'') . (empty($settings['hitStats']) ? '' : ',
			\'hits\'') . '
		]
	});');
	}
}
