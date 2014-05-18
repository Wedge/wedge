<?php
/**
 * Displays the main listing of boards.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

function template_boards()
{
	global $context, $txt, $settings;

	echo '
	<div id="boards_container">';

	/* Each category in categories is made up of:
	id, href, link, name, is_collapsed (is it collapsed?), can_collapse (is it okay if it is?),
	new (is it new?), collapse_href (href to collapse/expand), collapse_image (up/down image),
	and boards. (see below.) */
	$alt = false;
	$is_guest = we::$is_guest;
	$nb_new = get_unread_numbers($context['board_ids'], false, true);

	foreach ($context['categories'] as $category)
	{
		// If there are no parent boards we can see, avoid showing an empty category (unless it's collapsed)
		if (empty($category['boards']) && !$category['is_collapsed'])
			continue;

		echo '
		<we:cat id="title_cat_', $category['id'], '">';

		// If this category even can collapse, show a link to collapse it.
		if ($category['can_collapse'])
			echo '
			<a class="collapse" href="', $category['collapse_href'], '">', $category['collapse_image'], '</a>';

		if (!$is_guest && !empty($category['show_unread']))
			echo '
			<a class="unreadlink" href="<URL>?action=unread;c=', $category['id'], '">', $txt['view_unread_category'], '</a>';

		if (empty($category['hide_rss']))
			echo '
			<a class="catfeed feed_icon" href="<URL>?action=feed;c=', $category['id'], '"></a>';

		echo '
			', $category['link'], '
		</we:cat>
		<div class="wide">
			<table class="table_list board_list" id="boards_cat_', $category['id'], '">';

		// Assuming the category hasn't been collapsed...
		if (!$category['is_collapsed'])
		{
			/* Each board in each category's boards has:
			new (is it new?), id, name, description, moderators (see below), link_moderators (just a list),
			children (see below.), link_children (easier to use.), children_new (are they new?),
			topics (# of), posts (# of), link, href, and last_post. (see below.) */

			foreach ($category['boards'] as $board)
			{
				$alt = !$alt;
				$nb = !empty($nb_new[$board['id']]) ? $nb_new[$board['id']] : '';
				$boardstate = 'boardstate' . (SKIN_MOBILE ? ' mobile' : '');

				echo '
				<tr id="board_', $board['id'], '" class="windowbg', $alt ? '2' : '', '">
					<td class="icon">
						<a href="<URL>?action=unread;board=' . $board['id'] . ';children" title="' . $txt['show_unread'] . '">';

				// If this board is told to have a custom icon, use it.
				if (!empty($board['custom_class']))
					echo '<div class="', $boardstate, ' ', $board['custom_class'], '"', !empty($board['custom_title']) ? ' title="' . $board['custom_title'] . '"' : '', '>';
				// Is it a redirection board?
				elseif ($board['is_redirect'])
					echo '<div class="', $boardstate, ' link" title="', $txt['redirect_board'], '">';
				// Show an indicator of the board's recent activity.
				else
					echo '<div class="', $boardstate, empty($board['new']) ? '' : ' unread', '">';

				echo '</div></a>
					</td>
					<td class="info">
						', $settings['display_flags'] == 'all' || ($settings['display_flags'] == 'specified' && !empty($board['language'])) ? '<img src="' . LANGUAGES . $context['languages'][$board['language']]['folder'] . '/Flag.' . $board['language'] . '.gif">&nbsp; ': '', '<a', $board['redirect_newtab'] ? ' target="_blank"' : '', ' class="subject" href="', $board['href'], '" id="b', $board['id'], '">', $board['name'], '</a>';

				// Has it outstanding posts for approval?
				if (!empty($board['can_approve_posts']) && (!empty($board['unapproved_posts']) || !empty($board['unapproved_topics'])))
					echo '
						<a href="<URL>?action=moderate;area=postmod;sa=', $board['unapproved_topics'] > 0 ? 'topics' : 'posts', ';brd=', $board['id'], ';', $context['session_query'], '" title="', sprintf($txt['unapproved_posts'], $board['unapproved_topics'], $board['unapproved_posts']), '" class="moderation_link">(!)</a>';

				if ($nb && empty($board['redirect_newtab']))
					echo '
						<a href="<URL>?action=unread;board=', $board['id'], '.0;children', '" title="', $txt['show_unread'], '" class="note">', $nb, '</a>';

				if (!empty($board['description']))
					echo '
						<p>', $board['description'], '</p>';

				// Show the "Moderators: ". Each has name, href, link, and id. (but we're gonna use link_moderators.)
				if (!empty($board['moderators']))
					echo '
						<p class="moderators">', count($board['moderators']) === 1 ? $txt['moderator'] : $txt['moderators'], ': ', implode(', ', $board['link_moderators']), '</p>';

				// Show the "Child Boards: " area. (There's a link_children but we're going to bold the new ones...)
				if (!empty($board['children']))
				{
					// Sort the links into an array with new boards bold so it can be imploded.
					$children = array();
					/* Each child in each board's children has:
							id, name, description, new (is it new?), topics (#), posts (#), href, link, and last_post. */
					foreach ($board['children'] as $child)
					{
						$display = array();
						if (isset($child['display']))
							foreach ($child['display'] as $item => $string)
								if (isset($child[$item]))
									$display[] = number_context($string, $child[$item]);

						if ($child['is_redirect'])
							$child['link'] = '<a href="' . $child['href'] . '"' . (!empty($display) ? ' title="' . implode(', ', $display) . '"' : '') . '>' . $child['name'] . '</a>';
						else
						{
							$nb = !empty($nb_new[$child['id']]) ? $nb_new[$child['id']] : 0;
							$child['link'] = '<a href="' . $child['href'] . '">' . $child['name'] . '</a>' . ($nb ? ' <a href="<URL>?action=unread;board=' . $child['id'] . ';children" title="' . $txt['show_unread'] . '" class="notevoid">' . $nb . '</a>' : '');
						}

						// Has it posts awaiting approval?
						if (!empty($child['can_approve_posts']) && (!empty($child['unapproved_posts']) || !empty($child['unapproved_topics'])))
							$child['link'] .= ' <a href="<URL>?action=moderate;area=postmod;sa=' . ($child['unapproved_topics'] > 0 ? 'topics' : 'posts') . ';brd=' . $child['id'] . ';' . $context['session_query'] . '" title="' . sprintf($txt['unapproved_posts'], $child['unapproved_topics'], $child['unapproved_posts']) . '" class="moderation_link">(!)</a>';

						$children[] = $child['new'] ? '<strong>' . $child['link'] . '</strong>' : $child['link'];
					}
					echo '
						<div class="children windowbg', $alt ? '2' : '', '" id="board_', $board['id'], '_children">
							<p><strong>', $txt['sub_boards'], '</strong>: ', implode(', ', $children), '</p>
						</div>';
				}

				echo '
					</td>';

				// Show some basic information about the number of posts, etc.
				if (!SKIN_MOBILE)
				{
					$display = array();
					foreach ($board['display'] as $item => $string)
						if (isset($board[$item]))
							$display[] = number_context($string, $board[$item]);

					echo '
					<td class="stats">
						<p>', implode('<br>', $display),  '</p>
					</td>';
				}

				echo '
					<td class="lastpost">';

				/* The board's and children's 'last_post's have:
				time, timestamp (a number that represents the time.), id (of the post), topic (topic id.),
				link, href, subject, start (where they should go for the first unread post.),
				and member. (which has id, name, link, href, username in it.) */
				if (!empty($board['last_post']['offlimits']))
					echo '
						<p>', $board['last_post']['offlimits'], '</p>
					</td>';
				elseif (!empty($board['last_post']['id']))
					echo '
						<p>
							', strtr($txt['last_post_author_link_time'], array(
								'{author}' => $board['last_post']['member']['link'],
								'{link}' => $board['last_post']['link'],
								'{time}' => $board['last_post']['on_time'])
							), '
						</p>
					</td>';
				else
					echo '</td>';

				echo '
				</tr>';
			}
		}

		echo '
			</table>
		</div>';
	}

	echo '
	</div>';
}

function template_boards_ministats()
{
	global $context, $settings, $txt;

	// Show some statistics if stat info is off.
	if (empty($settings['show_stats_index']))
		echo '
	<div id="index_common_stats">
		', $txt['members'], ': ', $context['common_stats']['total_members'], ' &nbsp;&#8226;&nbsp; ', $txt['posts_made'], ': ', $context['common_stats']['total_posts'], ' &nbsp;&#8226;&nbsp; ', $txt['topics'], ': ', $context['common_stats']['total_topics'], '
		', !empty($settings['show_latest_member']) ? ' &nbsp;&#8226;&nbsp; ' . sprintf($txt['welcome_member'], '<strong>' . $context['common_stats']['latest_member']['link'] . '</strong>') : '', '
	</div>';
}

function template_boards_newsfader()
{
	// Show the news fader?  (assuming there are things to show...)
	global $context, $options, $txt, $settings;

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

		add_js_file('fader.js');

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

	if (we::$is_member)
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
