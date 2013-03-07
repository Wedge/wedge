<?php
/**
 * Wedge
 *
 * Displays the main listing of boards.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_boards()
{
	global $context, $theme, $options, $txt, $settings, $language;

	echo '
	<div id="boards_container">';

	/* Each category in categories is made up of:
	id, href, link, name, is_collapsed (is it collapsed?), can_collapse (is it okay if it is?),
	new (is it new?), collapse_href (href to collapse/expand), collapse_image (up/down image),
	and boards. (see below.) */
	$alt = false;
	$is_guest = we::$is_guest;
	foreach ($context['categories'] as $category)
	{
		// If there are no parent boards we can see, avoid showing an empty category (unless it's collapsed)
		if (empty($category['boards']) && !$category['is_collapsed'])
			continue;

		echo '
		<table class="table_list board_list">
			<thead id="category_', $category['id'], '">
				<tr>
					<td colspan="4">
						<we:cat>';

		// If this category even can collapse, show a link to collapse it.
		if ($category['can_collapse'])
			echo '
							<a class="collapse" href="', $category['collapse_href'], '">', $category['collapse_image'], '</a>';

		if (!$is_guest && !empty($category['show_unread']))
			echo '
							<a class="unreadlink" href="<URL>?action=unread;c=', $category['id'], '">', $txt['view_unread_category'], '</a>';

		echo '
							<a class="catfeed" href="<URL>?action=feed;c=', $category['id'], '"><div class="feed_icon"></div></a>
							', $category['link'], '
						</we:cat>
					</td>
				</tr>
			</thead>';

		// Assuming the category hasn't been collapsed...
		if (!$category['is_collapsed'])
		{
			/* Each board in each category's boards has:
			new (is it new?), id, name, description, moderators (see below), link_moderators (just a list.),
			children (see below.), link_children (easier to use.), children_new (are they new?),
			topics (# of), posts (# of), link, href, and last_post. (see below.) */

			echo '
			<tbody id="category_', $category['id'], '_boards">';

			foreach ($category['boards'] as $board)
			{
				$alt = !$alt;

				echo '
				<tr id="board_', $board['id'], '" class="windowbg', $alt ? '2' : '', '">
					<td class="icon"', !empty($board['children']) ? ' rowspan="2"' : '', '>
						<a', $board['redirect_newtab'] ? ' target="_blank"' : '', ' href="', $board['is_redirect'] || $is_guest ? $board['href'] : '<URL>?action=unread;board=' . $board['id'] . '.0;children', '">';

				// If this board is told to have a custom icon, use it.
				if (!empty($board['custom_class']))
					echo '
							<div class="boardstatus ', $board['custom_class'], '"', !empty($board['custom_title']) ? ' title="' . $board['custom_title'] . '"' : '', '></div>';
				// If the board has new posts, show an indicator.
				elseif ($board['new'])
					echo '
							<div class="boardstate_on" title="', $txt['new_posts'], '"></div>';
				// Is it a redirection board?
				elseif ($board['is_redirect'])
					echo '
							<div class="boardstate_redirect" title="', $txt['redirect_board'], '"></div>';
				// No new posts at all! The agony!!
				else
					echo '
							<div class="boardstate_off" title="', $txt['old_posts'], '"></div>';

				echo '
						</a>
					</td>
					<td class="info">
						', $settings['display_flags'] == 'all' || ($settings['display_flags'] == 'specified' && !empty($board['language'])) ? '<img src="' . $theme['default_theme_url'] . '/languages/Flag.' . (empty($board['language']) ? $language : $board['language']) . '.png"> ': '', '<a', $board['redirect_newtab'] ? ' target="_blank"' : '', ' class="subject" href="', $board['href'], '" id="b', $board['id'], '">', $board['name'], '</a>';

				// Has it outstanding posts for approval?
				if ($board['can_approve_posts'] && ($board['unapproved_posts'] || $board['unapproved_topics']))
					echo '
						<a href="<URL>?action=moderate;area=postmod;sa=', ($board['unapproved_topics'] > 0 ? 'topics' : 'posts'), ';brd=', $board['id'], ';', $context['session_query'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

				if (!empty($board['description']))
					echo '
						<p>', $board['description'], '</p>';

				// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
				if (!empty($board['moderators']))
					echo '
						<p class="moderators">', count($board['moderators']) == 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

				// Show some basic information about the number of posts, etc.
				if (!SKIN_MOBILE)
					echo '
					</td>
					<td class="stats">
						<p>', number_context($board['is_redirect'] ? 'num_redirects' : 'num_posts', $board['posts']),
						$board['is_redirect'] ? '' : '<br>' . number_context('num_topics', $board['topics']), '</p>
					</td>
					<td class="lastpost">';

				/* The board's and children's 'last_post's have:
				time, timestamp (a number that represents the time.), id (of the post), topic (topic id.),
				link, href, subject, start (where they should go for the first unread post.),
				and member. (which has id, name, link, href, username in it.) */
				if (!empty($board['last_post']['offlimits']))
					echo '
						<p>', $board['last_post']['offlimits'], '</p>';
				elseif (!empty($board['last_post']['id']))
					echo '
						<p>
							', strtr($txt['last_post_author_link_time'], array(
								'{author}' => $board['last_post']['member']['link'],
								'{link}' => $board['last_post']['link'],
								'{time}' => $board['last_post']['on_time'])
							), '
						</p>';

				echo '
					</td>
				</tr>';

				// Show the "Child Boards: ". (there's a link_children but we're going to bold the new ones...)
				if (!empty($board['children']))
				{
					// Sort the links into an array with new boards bold so it can be imploded.
					$children = array();
					/* Each child in each board's children has:
							id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
					foreach ($board['children'] as $child)
					{
						if (!$child['is_redirect'])
						{
							$child_title = ($child['new'] ? $txt['new_posts'] : $txt['old_posts']) . ' (' . number_context('num_topics', $child['topics']) . ', ' . number_context('num_posts', $child['posts']) . ')';
							$child['link'] = '<a href="' . $child['href'] . '"' . ($child['new'] ? ' class="new_posts"' : '') . ' title="' . $child_title . '">' . $child['name'] . '</a>' . ($child['new'] ? ' <a href="<URL>?action=unread;board=' . $child['id'] . '" title="' . $child_title . '" class="note new_posts">' . $txt['new_short'] . '</a>' : '');
						}
						else
							$child['link'] = '<a href="' . $child['href'] . '" title="' . number_context('num_redirects', $child['posts']) . '">' . $child['name'] . '</a>';

						// Has it posts awaiting approval?
						if ($child['can_approve_posts'] && ($child['unapproved_posts'] || $child['unapproved_topics']))
							$child['link'] .= ' <a href="<URL>?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_query'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

						$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
					}
					echo '
					<tr id="board_', $board['id'], '_children">
						<td colspan="3" class="children windowbg', $alt ? '2' : '', '">
							<strong>', $txt['sub_boards'], '</strong>: ', implode(', ', $children), '
						</td>
					</tr>';
				}
			}

			echo '
			</tbody>';
		}
		echo '
		</table>';
	}
	echo '
	</div>';
}

function template_boards_ministats()
{
	global $context, $theme, $txt, $settings;

	// Show some statistics if stat info is off.
	if (!$theme['show_stats_index'])
		echo '
	<div id="index_common_stats">
		', $txt['members'], ': ', $context['common_stats']['total_members'], ' &nbsp;&#8226;&nbsp; ', $txt['posts_made'], ': ', $context['common_stats']['total_posts'], ' &nbsp;&#8226;&nbsp; ', $txt['topics'], ': ', $context['common_stats']['total_topics'], '
		', $theme['show_latest_member'] ? ' &nbsp;&#8226;&nbsp; ' . sprintf($txt['welcome_member'], '<strong>' . $context['common_stats']['latest_member']['link'] . '</strong>') : '', '
	</div>';
}

function template_boards_newsfader()
{
	// Show the news fader?  (assuming there are things to show...)
	global $context, $theme, $options, $txt, $settings;

	if (!empty($settings['show_newsfader']) && !empty($context['fader_news_lines']))
	{
		echo '
	<div id="newsfader">
		<we:cat>
			<div id="newsupshrink" title="', $txt['upshrink_description'], '"', empty($options['collapse_news_fader']) ? ' class="fold"' : '', '></div>
			', $txt['news'], '
		</we:cat>
		<ul class="reset" id="fadeScroller">';

			foreach ($context['news_lines'] as $news)
				echo '
			<li>', $news, '</li>';

		echo '
		</ul>
	</div>';

		add_js_file('scripts/fader.js');

		// Create a news fader object and toggle.
		add_js('
	new weFader({
		control: \'fadeScroller\',
		template: \'%1$s\',
		delay: ', empty($settings['newsfader_time']) ? 5000 : $settings['newsfader_time'], '
	});

	new weToggle({', empty($options['collapse_news_fader']) ? '' : '
		isCollapsed: true,', '
		aSwapContainers: [\'fadeScroller\'],
		aSwapImages: [\'newsupshrink\'],
		sOption: \'collapse_news_fader\'
	});');
	}
}

function template_boards_below()
{
	global $context;

	if (!we::$is_guest)
	{
		echo '
	<ul id="posting_icons" class="reset floatleft">';

		// Mark read button.
		$mark_read_button = array(
			'markread' => array('text' => 'mark_as_read', 'url' => '<URL>?action=markasread;sa=all;' . $context['session_query']),
		);

		echo '
	</ul>';

		// Show the mark all as read button?
		if (!empty($context['categories']))
			echo '<div class="mark_read">', template_button_strip($mark_read_button), '</div>';
	}
}
