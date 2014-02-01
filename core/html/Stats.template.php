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
	global $context, $txt, $settings;

	echo '
		<we:cat>
			', $context['page_title'], '
		</we:cat>';

	if (we::is('!ie[-8]'))
	{
		echo '
		<div class="flow_hidden clear">
			<we:title2>
				<img src="', ASSETS, '/stats_history.gif">
				', $txt['forum_history'], '
			</we:title2>
			<div id="ranges" class="padding">
				<select id="range"></select>
				<select id="filter">';

		$filters = array();
		foreach ($txt['charts'] as $key => $name)
		{
			if (empty($name))
				echo '
					<option class="hr"></option>';
			elseif (count(array_intersect(explode(',', $key), $context['available_filters'])) == substr_count($key, ',') + 1)
			{
				$filters[] = $key;
				echo '
					<option value="', $key, '"', $_SESSION['stat_charts']['filter'] == $key ? ' selected' : '', '>', $name, '</option>';
			}
		}

		echo '
				</select>
			</div>
			<canvas id="wraph" height="', we::is('mobile') ? 200 : 300, '"></canvas>
			<div id="labels" style="text-align: center"></div>
		</div>';

		$col = array(
			'hits' => '200,180,140',
			'posts' => '151,187,205',
			'topics' => '120,210,180',
			'registers' => '200,150,180',
			'most_on' => '220,160,160',
			'205,151,187',
		);

		$i = 0;
		$colors = array();
		$names = array();
		foreach ($filters as $type)
		{
			if (!$type || strpos($type, ',') !== false)
				continue;
			$colors[$type] = array(
				'fillColor' => $type != 'hits' && $type != 'posts' ? 'transparent' : 'rgba(' . $col[$type] . ',0.5)',
				'strokeColor' => 'rgb(' . $col[$type] . ')',
				'pointColor' => 'rgb(' . $col[$type] . ')',
				'pointStrokeColor' => '#fff',
			);
			$names[$type] = isset($txt['charts'][$type]) ? $txt['charts'][$type] : $type;
		}

		add_js('
	lineChartData = ', we_json_encode($context['full_chart']), ';
	nameData = ', we_json_encode($names), ';
	colorData = ', we_json_encode($colors), ';');

		// And now, we can prepare to show that data.
		add_js_file(array('wraph.js', 'stats.js'));
	}

	echo '

		<we:title2 style="margin: 8px 0">
			<img src="', ASSETS, '/stats_info.gif">
			', $txt['general_stats'], '
		</we:title2>

		<div class="two-columns"><div class="windowbg wrc top_row">
			<dl class="stats">
				<dt>', $txt['total_registers'], ':</dt>
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
				<dt>', $txt['average_registers'], ':</dt>
				<dd>', $context['average_registers'], '</dd>
				<dt>', $txt['average_posts'], ':</dt>
				<dd>', $context['average_posts'], '</dd>
				<dt>', $txt['average_topics'], ':</dt>
				<dd>', $context['average_topics'], '</dd>
				<dt>', $txt['average_most_on'], ':</dt>
				<dd>', $context['average_most_on'], '</dd>
				<dt>', $txt['users_online_today'], ':</dt>
				<dd>', $context['online_today'], '</dd>
				<dt>', $txt['users_online'], ':</dt>
				<dd>', $context['users_online'], '</dd>';

	if (allowedTo('moderate_forum'))
		echo '
				<dt>', $txt['most_online_ever'], ':</dt>
				<dd>', $context['most_online']['number'], ' (', $context['most_online']['date'], ')</dd>';

	if (!empty($settings['hitStats']))
		echo '
				<dt>', $txt['average_hits'], ':</dt>
				<dd>', $context['average_hits'], '</dd>';

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg2 wrc">
			<h6>
				<img src="', ASSETS, '/stats_posters.gif">
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
					<div class="bar" style="width: ', $poster['post_percent'], 'px"></div>';

		echo '
					<span>', $poster['num_posts'], '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg wrc">
			<h6>
				<img src="', ASSETS, '/stats_board.gif">
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
					<div class="bar" style="width: ', $board['post_percent'], 'px"></div>';

		echo '
					<span>', $board['num_posts'], '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg wrc">
			<h6>
				<img src="', ASSETS, '/stats_replies.gif">
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
					<div class="bar" style="width: ', $topic['post_percent'], 'px"></div>';

		echo '
					<span>' . $topic['num_replies'] . '</span>
				</dd>';
	}
	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg2 wrc">
			<h6>
				<img src="', ASSETS, '/stats_views.gif">
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
					<div class="bar" style="width: ', $topic['post_percent'], 'px"></div>';

		echo '
					<span>' . $topic['num_views'] . '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg2 wrc">
			<h6>
				<img src="', ASSETS, '/stats_replies.gif">
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
					<div class="bar" style="width: ', $poster['post_percent'], 'px"></div>';

		echo '
					<span>', $poster['num_topics'], '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>

		<div class="two-columns"><div class="windowbg wrc">
			<h6>
				<img src="', ASSETS, '/stats_views.gif">
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
					<div class="bar" style="width: ', $poster['time_percent'], 'px"></div>';

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
					<div class="bar" style="width: ', $like['post_percent'], 'px"></div>';

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
					<div class="bar" style="width: ', $like['post_percent'], 'px"></div>';

		echo '
					<span>', $like['num_likes'], '</span>
				</dd>';
	}

	echo '
			</dl>
		</div></div>';
}
