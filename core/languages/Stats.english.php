<?php
// Version: 2.0; Stats

$txt['stats_center'] = 'Statistics Center';
$txt['general_stats'] = 'General Statistics';

// This is where we list all possible filter permutations for statistics. An empty line means a separator.
$txt['charts'] = array(
	'posts,topics,registers,most_on' => 'Everything|Except page hits',
	'topics,registers,most_on' => 'Everything|Except hits and posts',
	'',
	'posts,hits' => 'New Posts &amp; Page Hits',
	'posts,topics' => 'New Posts &amp; Topics',
	'',
	'topics' => 'New Topics',
	'posts' => 'New Posts',
	'registers' => 'New Members',
	'most_on' => 'Most Online',
	'hits' => 'Page Hits',
);

$txt['group_monthly'] = 'Monthly stats';
$txt['group_daily'] = 'Daily stats';
$txt['lifetime'] = 'Everything';
$txt['last_month'] = 'Last 31 days';
$txt['last_year'] = 'Last 12 months';
$txt['stat_sum'] = 'Sum:';
$txt['stat_average'] = 'Average:';

$txt['date_format'] = '%B %@, %Y';
$txt['date_format_this_year'] = '%B %@';

$txt['top_posters'] = 'Top 10 Posters';
$txt['top_boards'] = 'Top 10 Boards';
$txt['forum_history'] = 'Forum History (using forum time offset)';
$txt['stats_date'] = 'Date (yyyy/mm/dd)';
$txt['top_topics_replies'] = 'Top 10 Topics (by Replies)';
$txt['top_topics_views'] = 'Top 10 Topics (by Views)';
$txt['top_liked'] = 'Top Liked Posts';
$txt['top_liked_posters'] = 'Top Liked Posters';
$txt['top_starters'] = 'Top Topic Starters';
$txt['most_time_online'] = 'Most Time Online';
$txt['stats_more_detailed'] = 'more detailed &raquo;';

$txt['average_registers'] = 'Average registrations per day';
$txt['average_posts'] = 'Average posts per day';
$txt['average_topics'] = 'Average topics per day';
$txt['average_most_on'] = 'Average online per day';
$txt['users_online'] = 'Users Online';
$txt['gender_ratio'] = 'Male to Female Ratio';
$txt['users_online_today'] = 'Online Today';
$txt['num_hits'] = 'Total page views';
$txt['average_hits'] = 'Average page views per day';

$txt['ssi_comment'] = 'comment';
$txt['ssi_comments'] = 'comments';
$txt['ssi_write_comment'] = 'Write Comment';
$txt['ssi_no_guests'] = 'You cannot specify a board that doesn\'t allow guests. Please check the board ID before trying again.';
$txt['xml_feed_desc'] = 'Live information from {forum_name}';

$txt['total_registers'] = 'Total Members';
$txt['total_posts'] = 'Total Posts';
$txt['total_topics'] = 'Total Topics';
$txt['total_boards'] = 'Total Boards';
$txt['total_cats'] = 'Total Categories';

$txt['totalTimeLogged_d_short'] = 'd ';
$txt['totalTimeLogged_h_short'] = 'h ';
$txt['totalTimeLogged_m_short'] = 'm';

// Debug related stats - when $db_show_debug is true.
$txt['debug_report'] = '
	<strong>Templates</strong> (%1$d): <em>%2$s</em>.
	<strong>Blocks</strong> (%3$d): <a href="#" onclick="%11$s">list</a><span class="hide"><em>%4$s</em></span>.
	<strong>Language files</strong> (%5$d): <a href="#" onclick="%11$s">list</a><span class="hide"><em>%6$s</em></span>.
	<strong>Style sheets</strong> (%7$d): <a href="#" onclick="%11$s">list</a><span class="hide"><em>%8$s</em></span>.
	<strong>Files included</strong> (%9$d): %10$sKB - <a href="#" onclick="%11$s">list</a><span class="hide"><em>%12$s</em></span>.
	<strong>Peak memory use</strong>: %13$dKB.';
$txt['debug_cache_hits'] = '
	<strong>Cache hits</strong> (%1$d): %2$ss for %3$s bytes - <a href="#" onclick="%4$s">list</a><span class="hide"><em>%5$s</em></span>.';
$txt['debug_cache_seconds_bytes'] = '%1$s %2$ss - %3$s bytes';
$txt['debug_queries_used'] = '<strong>Queries used</strong>: %1$d';
$txt['debug_queries_used_and_warnings'] = '<strong>Queries used</strong>: %1$d, %2$d warnings';
$txt['debug_query_in_line'] = 'in <em>%1$s</em> line <em>%2$s</em>, ';
$txt['debug_query_which_took'] = 'which took %1$s seconds.';
$txt['debug_query_which_took_at'] = 'which took %1$s seconds at %2$s into request.';
$txt['debug_show_queries'] = '<strong>Expand Queries</strong>';
$txt['debug_hide_queries'] = '<strong>Hide Queries</strong>';
$txt['html5_validation'] = '<strong>HTML5 Validation</strong>';
