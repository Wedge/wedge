<?php
/**
 * The core template that underpins the entire layout, including key configuration settings.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

/*
	This template is probably the most important one. It defines the functions
	to be called by the layers and blocks defined in the main skeleton.
	Some layers of interest are: html, body, main and sidebar.
	Blocks of interest include linktree (which displays the navigation
	hierarchy), menu (for the main menu), or button_strip.
*/

// Initialize the template... mainly minor settings.
function template_init()
{
	global $context, $settings;

	// Add the login-specific JavaScript files to our priority cache list.
	if (we::$is_guest && !empty($context['main_js_files']) && empty($context['disable_login_hashing']) && !empty($settings['enable_quick_login']))
		$context['main_js_files']['sha1.js'] = true;

	// A couple of settings you might want to set:
	// $context['message_index_preview'] = true; // Does this theme use post previews on the message index?
	// $context['page_separator'] = '&nbsp;' // Custom separator between page index and up/down link.
	// $context['require_theme_strings'] = false; // Force loading of ThemeStrings.language.php file
}

// The main block above the content.
function template_html_before()
{
	global $context, $txt, $settings, $topic;

	// Declare our HTML5 doctype, and whether to show right to left.
	// The charset is already specified in the headers so it may be omitted,
	// but the specs recommend leaving them in, if the document is viewed offline.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', !empty($txt['lang_dictionary']) ? ' lang="' . $txt['lang_dictionary'] . '"' : '', '>
<head>', empty($topic) ? '' : '
	<meta charset="utf-8">';

	// Our all-time favorites don't really like HTML5...
	if (we::is('ie8down'))
		echo '
	<script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>';

	echo theme_base_css(), '
	<!-- Powered by Wedge, © R.-G. Deberdt - http://wedge.org -->
	<title>', $context['page_title_html_safe'], !empty($context['page_indicator']) ? $context['page_indicator'] : '', '</title>';

	// If the forum is in a sub-folder, it needs to explicitly set a favicon URL.
	if (strpos(str_replace('://', '', ROOT), '/') !== false)
		echo '
	<link rel="shortcut icon" href="', ROOT, '/favicon.ico" type="image/vnd.microsoft.icon">';

	// Present a canonical URL for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '">';

	// Show all the relative links, such as search.
	if (!empty($context['allow_search']))
		echo '
	<link rel="search" href="<URL>?action=search">';

	// If feeds are enabled, advertise the presence of one.
	if (!empty($settings['xmlnews_enable']) && (!empty($settings['allow_guestAccess']) || we::$is_member))
		echo '
	<link rel="alternate" href="<URL>?action=feed" type="application/atom+xml" title="', $context['forum_name_html_safe'], '">';

	// If we're viewing a topic, we should link to the previous and next pages, respectively. Search engines like this.
	if (empty($context['robot_no_index']))
	{
		if (!empty($context['links']['prev']))
			echo '
	<link rel="prev" href="', $context['links']['prev'], '">';
		if (!empty($context['links']['next']))
			echo '
	<link rel="next" href="', $context['links']['next'], '">';
	}

	if (SKIN_MOBILE && !we::is('opera[11-], ie[10-]'))
		echo '
	<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=2,minimum-scale=0.7">';

	if (!empty($context['meta_description']))
		echo '
	<meta name="description" content="', $context['meta_description'], '">';

	// Please don't index these, Mr Robotto.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex">';

	if (isset($_SESSION['session_var'], $_GET[$_SESSION['session_var']]))
		echo '
	<meta name="referrer" content="origin">';

	echo '
</head>';
}

function template_body_before()
{
	echo '
<body>';
}

// The main content should go here.
function template_wrapper_before()
{
	echo '
<div id="wedge">';
}

// Start the header layer.
function template_header_before()
{
	echo '
	<div id="header"><div class="frame">';
}

function template_top_bar_before()
{
	echo '
		<div id="top_section"><div class="frame">';
}

function template_top_bar_after()
{
	echo '
		</div></div>';
}

// End the header layer.
function template_header_after()
{
	global $context, $settings, $options;

	echo '
		<div id="banner"', empty($options['collapse_header']) ? '' : ' class="hide"', '><div class="frame"><we:banner title="',
		$context['header_logo_url_html_safe'], '" url="', !empty($settings['home_url']) && !empty($settings['home_link']) ?
		$settings['home_url'] : '<URL>', '">', $context['site_slogan'], '</we:banner>
		</div></div>
	</div></div>';
}

function template_search_box()
{
	global $context, $txt;

	if (empty($context['allow_search']))
		return;

	echo '
			<form id="search_form" action="<URL>?action=search2" method="post" accept-charset="UTF-8">
				<input type="search" name="search" value="" class="search">';

	// Search within current topic?
	if (!empty($context['current_topic']))
			echo '
				<input type="hidden" name="topic" value="', $context['current_topic'], '">';

	// Or within current board?
	if (!empty($context['current_board']))
		echo '
				<input type="hidden" name="brd[', $context['current_board'], ']" value="', $context['current_board'], '">';

	echo '
			</form>';
}

function template_notifications()
{
	global $txt, $context, $user_settings;

	if (isset($context['unread_notifications']))
		echo '
			<div class="notifs notif">
				<span class="note', !empty($user_settings['hey_not']) ? '' : 'void', '">', $context['unread_notifications'], '</span>
				', $txt['notifications'], '
			</div>';
}

function template_pm_notifications()
{
	global $txt, $context, $user_settings;

	if ($context['allow_pm'])
		echo '
			<div class="notifs npm">
				<span class="note', !empty($user_settings['hey_pm']) ? '' : 'void', '">', we::$user['unread_messages'], '</span>
				', $txt['pm_short'], '
			</div>';
}

function template_language_selector()
{
	global $context;

	if (empty($context['languages']) || count($context['languages']) < 2)
		return;

	echo '
			<form id="flags" method="get"><select name="language" onchange="this.form.submit();">';

	foreach ($context['languages'] as $id => $language)
		echo '
				<option value="', $id, '"', we::$user['language'] == $id ? ' selected' : '', '>&lt;span class="flag_', $language['filename'], '"&gt;', westr::htmlspecialchars($language['name']), '&lt;/span&gt;</option>';

	echo '
			</select></form>';
}

function template_logo_toggler()
{
	global $options;

	echo '
			<div id="upshrink"', empty($options['collapse_header']) ? ' class="fold"' : '', '>›</div>';
}

function template_random_news()
{
	global $txt, $context, $settings;

	// Show a random news item? (or you could pick one from news_lines...)
	if (empty($settings['enable_news']) || empty($context['random_news_line']))
		return;

	echo '
	<div id="sitenews">
		<span>', $txt['news'], we::is('ie6,ie7') ? ' > ' : '', '</span>
		', $context['random_news_line'], '
	</div>';
}

function template_sidebar_wrap_before()
{
	echo '
	<we:sidebar>';
}

function template_side_user_before()
{
	global $txt, $context, $settings;

	if (we::$is_guest && empty($settings['enable_quick_login']))
		return;

	echo '
	<section>
		<we:title>
			<span class="greeting">', sprintf($txt['hello_member_ndt'], we::$user['name']), '</span>
		</we:title>
		<div id="userbox">';

	// If the user is logged in, display stuff like their name, new messages, etc.
	if (we::$is_member)
	{
		echo empty(we::$user['avatar']['image']) ? '
			<ul id="noava">' : '
			' . we::$user['avatar']['image'] . '
			<ul>', '
				<li><a href="<URL>?action=unread">', $txt['show_unread'], '</a></li>
				<li><a href="<URL>?action=unreadreplies">', $txt['show_unread_replies'], '</a></li>';

		// Are there any members waiting for approval?
		if (!empty($context['unapproved_members']))
			echo '
				<li>', number_context('approve_members_waiting', $context['unapproved_members']), '</li>';

		echo '
			</ul>
			<p class="now">', $context['current_time'], '</p>
		</div>';
	}
	// Otherwise they're a guest - this time ask them to either register or login - lazy bums...
	elseif (!empty($settings['enable_quick_login']))
		echo '
			<form id="guest_form" action="<URL>?action=login2" method="post" accept-charset="UTF-8" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
				<div class="info">', (empty($settings['registration_method']) || $settings['registration_method'] != 3) ? $txt['login_or_register'] : $txt['please_login'], '</div>
				<input name="user" size="10">
				<input type="password" name="passwrd" size="10">
				<select name="cookielength">
					<option value="60">', $txt['one_hour'], '</option>
					<option value="1440">', $txt['one_day'], '</option>
					<option value="10080">', $txt['one_week'], '</option>
					<option value="43200">', $txt['one_month'], '</option>
					<option value="-1" selected>', $txt['forever'], '</option>
				</select>
				<input type="submit" value="', $txt['login'], '" class="submit"><br>
				<div class="info">', $txt['quick_login_desc'], '</div>
				<input type="hidden" name="hash_passwrd" value="">
			</form>
		</div>';
}

function template_side_user_after()
{
	echo '
	</section>';
}

function template_side_maintenance()
{
	global $context, $txt;

	// Is the forum in maintenance mode?
	if ($context['in_maintenance'] && we::$is_admin)
		echo '
	<section>
		<p class="notice">', $txt['maintain_mode_on'], '</p>
	</section>';
}

// Show the Quick Access (JumpTo) select box.
function template_sidebar_quick_access()
{
	global $txt;

	add_js('
	new JumpTo("jump_to");');

	echo '
	<section>
		<we:title>
			', $txt['jump_to'], '
		</we:title>
		<p id="jump_to"></p>
	</section>';
}

// This natty little function adds feed links to the sidebar. Mostly autonomous, it's lovely for that.
// This function is only added to the list if the feeds are available, so we don't even need to check anything.
function template_sidebar_feed()
{
	global $txt, $topic, $board, $board_info;

	echo '
	<section>
		<we:title>
			<div class="feed_icon">', $txt['feed'], '</div>
		</we:title>
		<dl id="feed">';

	// Topic feed
	if (!empty($topic))
		echo '
			<dt>', $txt['feed_current_topic'], '</dt>
			<dd>', sprintf($txt['feed_posts'], '<URL>?topic=' . $topic . ';action=feed'), '</dd>';

	// Board level feed
	if (!empty($board))
	{
		$feed = '<URL>?board=' . $board_info['id'] . ';action=feed';
		echo '
			<dt>', $board_info['type'] == 'blog' ? $txt['feed_current_blog'] : $txt['feed_current_forum'], '</dt>
			<dd>', sprintf($txt['feed_posts'], $feed), ' / ', sprintf($txt['feed_topics'], $feed . ';sa=news'), '</dd>';
	}

	// Forum-wide and end
	$feed = '<URL>?action=feed';
	echo '
			<dt>', $txt['feed_everywhere'], '</dt>
			<dd>', sprintf($txt['feed_posts'], $feed), ' / ', sprintf($txt['feed_topics'], $feed . ';sa=news'), '</dd>
		</dl>
	</section>';
}

function template_sidebar_wrap_after()
{
	echo '</we:sidebar>';
}

function template_offside_wrap_before()
{
	echo '<we:offside>';
}

function template_offside_wrap_after()
{
	echo '</we:offside>';
}

function template_content_wrap_before()
{
	global $context;

	if ($context['action'])
		$id = $context['action'];
	elseif (!empty($context['current_topic']))
		$id = 'topic';
	elseif (!empty($context['current_board']))
		$id = 'board';
	if (wetem::has_block('admin_login'))
		$id = 'login';

	echo '
	<div id="content"><div class="frame"', isset($id) ? ' id="' . $id . '"' : '', '>';
}

function template_main_wrap_before()
{
	echo '
	<div id="main">';
}

function template_main_wrap_after()
{
	echo '
	</div>';
}

function template_content_wrap_after()
{
	echo '
	</div></div>';
}

function template_wrapper_after()
{
	echo '
</div>';
}

function template_body_after()
{
	template_insert_javascript();

	echo '
</body>';
}

function template_insert_javascript()
{
	global $context, $options, $txt, $settings;

	// Include postponed inline JS, postponed HTML, and then kickstart the main
	// JavaScript section -- files to include, main vars and functions to start.
	// Don't modify the HTML comments, as they're placeholders for Wedge.

	echo $context['footer'], '
<!-- JavaScript area -->';

	// Code added here through add_js_inline() will execute before jQuery
	// and script.js are loaded. You may add time-critical events here.
	if (!empty($context['footer_js_inline']))
		echo '

<script>', $context['footer_js_inline'], '
</script>';

	// If user has loaded at least a page in the current session,
	// assume their script files are cached and run them in the header.
	// IE 6-8 should always put it below because they're too slow anyway.
	if (!we::is('ie8down') && isset($_SESSION['js_loaded']))
	{
		$context['header'] .= theme_base_js(1);
		echo '
<script>
	<!-- insert inline events here -->';
	}
	else
	{
		$_SESSION['js_loaded'] = true;
		echo '
<script>
	<!-- insert inline events here -->
</script>', "\n", theme_base_js(), '<script>';
	}

	if (!empty($settings['pm_enabled']))
		echo '
	we_pms = ', we::$user['unread_messages'], ';';

	$groups = $lists = array();
	foreach (we::$user['contacts']['groups'] as $id => $group)
		$groups[] = '"' . $id . '|' . $group[1] . '|' . str_replace('|', ' ', $group[0]) . '"';
	foreach (we::$user['contacts']['lists'] as $id => $clist)
		$lists[] = '"' . $id . '|' . $clist[1] . '|' . str_replace('|', ' ', generic_contacts($clist[0])) . '"';

	echo '
	we_script = "<URL>";
	we_assets = "', ASSETS, '";', '
	we_sessid = "', $context['session_id'], '";
	we_sessvar = "', $context['session_var'], '";', $context['server']['iso_case_folding'] && isset($context['main_js_files']['sha1.js']) ? '
	we_iso_case_folding = 1;' : '', empty($options['collapse_header']) ? '' : '
	we_colhead = 1;', empty($context['current_topic']) ? '' : '
	we_topic = ' . $context['current_topic'] . ';', empty($context['current_board']) ? '' : '
	we_board = ' . $context['current_board'] . ';', '
	we_groups = [' . implode(', ', $groups) . '];
	we_lists = [' . implode(', ', $lists) . '];', $context['show_pm_popup'] ? '

	ask(' . JavaScriptEscape($txt['show_personal_messages'], '"') . ', function (yes) { yes && window.open(weUrl("action=pm")); });' : '';

	// Output any postponed JavaScript added by templates
	// and mods, and close all outstanding tags. We're done!
	// $context['footer_js'] assumes the <script> tag is already output.
	echo $context['footer_js'], '
</script>';
}

function template_html_after()
{
	echo '</html>';
}

// Show a linktree - the thing that says "My Community > General Category > General Discussion"...
function template_linktree($position = 'top', $force_show = false)
{
	global $context;

	if ($position === 'bottom' && empty($context['bottom_linktree']) && !$force_show)
		return;

	// itemtype is provided for validation purposes.
	if ($position === 'bottom')
		echo '
	<div id="linktree_bt">';
	else
		echo '
	<div id="linktree" itemtype="http://schema.org/WebPage" itemscope>';

	// If linktree is empty, just return - also allow an override.
	if (!empty($context['linktree']) && ($linksize = count($context['linktree'])) !== 1 && (empty($context['dont_default_linktree']) || $force_show))
	{
		$needs_fix = we::is('ie6,ie7');

		if ($position === 'bottom')
			echo '
		<ul>';
		else
			echo '
		<ul itemprop="breadcrumb">';

		// Each tree item has a URL and name. Some may have extra_before and extra_after.
		$num = 0;
		foreach ($context['linktree'] as &$tree)
		{
			echo '<li', ++$num == $linksize ? ' class="last"' : '', '>';

			// Show something before the link?
			if (isset($tree['extra_before']))
				echo $tree['extra_before'];

			// Show the link, including a URL if it should have one.
			echo isset($tree['url']) ? '<a href="' . $tree['url'] . '">' . $tree['name'] . '</a>' : $tree['name'];

			// Show something after the link...?
			if (isset($tree['extra_after']))
				echo $tree['extra_after'];

			echo '</li>';
			if ($needs_fix)
				echo ' > ';
		}
		echo '</ul>
	</div>';
	}
	else
		echo '</div>';
}

// Show the main menu, up top.
function template_menu()
{
	global $context;

	echo '
	<div id="navi">';

	template_menu_recursive('', $context['menu_items'], true);

	echo '
	</div>';
}

// Sub-menus... And more nested action.
function template_menu_recursive($oact, $oitem, $is_root = false)
{
	echo '<ul', $is_root ? ' id="main_menu" class="menu"' : '', '>';

	foreach ($oitem as $act => $item)
	{
		if (empty($item))
		{
			echo '<li class="sep"><a><hr></a></li>';
			continue;
		}
		$class = (!empty($item['active_item']) ? ' chosen' : '') . (empty($item['items']) ? ' nodrop' : '') . (!empty($item['items']) && !$is_root ? ' subsection' : '');
		echo '<li', $class ? ' class="' . substr($class, 1) . '"' : '', '>';

		echo empty($item['icon']) && !$is_root ? '' : '<span id="m_' . ($is_root ? '' : $oact . '_') . $act . '"></span>',
			'<a href="', $item['href'], '"', !empty($item['nofollow']) ? ' rel="nofollow"' : '', '>', $item['title'],
			!empty($item['notice']) ? '<span class="note' . ($is_root ? 'warn' : '') . '">' . $item['notice'] . '</span>' : '', '</a>';

		if ($is_root)
			echo '</h4>';

		if (!empty($item['items']))
			template_menu_recursive($act, $item['items']);

		echo '</li>';
	}

	echo '</ul>';
}

function template_mini_menu($menu, $class)
{
	global $context, $txt;

	if (empty($context['mini_menu'][$menu]))
		return;

	$js = '
	$(".' . $class . '").mime({';

	foreach ($context['mini_menu'][$menu] as $post => $linklist)
		$js .= '
		' . $post . ': ["' . implode('", "', $linklist) . '"],';

	$js = substr($js, 0, -1) . '
	}, {';

	foreach ($context['mini_menu_items'][$menu] as $key => $pmi)
	{
		if (!isset($context['mini_menu_items_show'][$menu][$key]))
			continue;
		$js .= '
		' . $key . ': [';
		foreach ($pmi as $type => $item)
			if ($type === 'caption')
				$js .= (isset($txt[$item]) ? JavaScriptEscape($txt[$item], '"') : '""') . ', '
					. (isset($txt[$item . '_desc']) ? JavaScriptEscape($txt[$item . '_desc'], '"') : '""') . ', ';
			else
				$js .= '"' . $item . '", ';
		$js = substr($js, 0, -2) . '],';
	}

	add_js(substr($js, 0, -1) . '
	});');
}

// The same footer area...
function template_footer()
{
	global $context, $txt;

	// Show the credits page (forum admin/mod team and credits), and a link to an HTML conformity checker, for geeks.
	// If you want to use validator.nu instead, replace the w3.org link with:
	// "http://validator.nu/?doc=', we::$user['url'], '"
	echo '
	<div id="footer"><div class="frame">
		<ul class="reset">
			<li id="copyright">', $txt['copyright'], '</li>
			<li class="links">
				<a id="site_credits" href="<URL>?action=credits">', $txt['site_credits'], '</a> |
				<a id="button_html5" href="http://validator.w3.org/check?uri=referer" target="_blank" class="new_win" title="', $txt['valid_html5'], '">', $txt['html5'], '</a>',
				empty($context['custom_credits']) ? '' : $context['custom_credits'], '
			</li>';

	// Show the load time?
	if ($context['show_load_time'])
		echo '
			<li class="stats"><!-- insert stats here --></li>';

	echo '
		</ul>
	</div></div>';
}

/**
 * This function is used to construct the page lists used throughout the application, e.g. 1 ... 6 7 [8] 9 10 ... 15.
 *
 * - The function accepts a start position, for calculating the page out of the list of possible pages, however if the value is not the start of an actual page, the function will sanitize the value so that it will be the actual start of the 'page' of content. It also will sanitize where the start is beyond the last item.
 * - Many URLs in the application are in the form of item=x.y format, e.g. index.php?topic=1.20 to denote topic 1, 20 items in. This can be achieved by specifying $flexible_start as true, and %1$d in the basic URL component, e.g. passing the base URL as index.php?topic=1.%1$d
 * - Only the first and last pages are linked to, and the display will consist of 5 contiguous items centered on the current page, so displaying the current page and 2 page-links either side)
 *
 * @param string $base_url The basic URL to be used for each link.
 * @param int &$start The start position, by reference. If this is not a multiple of the number of items per page, it is sanitized to be so and the value will persist upon the function's return.
 * @param int $max_value The total number of items you are paginating for.
 * @param int $num_per_page The number of items to be displayed on a given page. $start will be forced to be a multiple of this value.
 * @param bool $flexible_start Whether a ;start=x component should be introduced into the URL automatically (see above)
 * @param bool $show_prevnext Whether the Previous and Next links should be shown (should be on only when navigating the list)
 * @return string The complete HTML of the page index that was requested.
 */
function template_page_index($base_url, &$start, $max_value, $num_per_page, $flexible_start = false, $show_prevnext = true)
{
	global $settings, $options, $topicinfo, $txt, $context;

	// Save whether $start was less than 0 or not.
	$start = (int) $start;
	$start_invalid = $start < 0;

	// Make sure $start is a proper variable - not less than 0.
	if ($start_invalid)
		$start = 0;
	// Not greater than the upper bound.
	elseif ($start >= $max_value)
		$start = max(0, (int) $max_value - (((int) $max_value % (int) $num_per_page) == 0 ? $num_per_page : ((int) $max_value % (int) $num_per_page)));
	// And it has to be a multiple of $num_per_page!
	else
		$start = max(0, (int) $start - ((int) $start % (int) $num_per_page));

	$base_link = '<a href="' . ($flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d') . '">%2$s</a> ';
	$pageindex = '';

	// The number of items to show on each side of the current page, e.g. "6 7 [8] 9 10" if $contiguous = 2. If you want to change it, here's the place.
	$contiguous = isset($settings['compactTopicPagesContiguous']) ? $settings['compactTopicPagesContiguous'] >> 1 : 2;

	// First of all, do we want a 'next' button to take us closer to the first (most interesting) page?
	if ($show_prevnext && $start >= $num_per_page)
	{
		// If we're in a topic page, and later pages have unread posts, show a New notification after the Next link!
		if (!empty($options['view_newest_first']) && !empty($topicinfo['new_from']) && $topicinfo['new_from'] <= $topicinfo['id_last_msg'])
			$pageindex .= '<div class="note next_page">' . $txt['new_short'] . '</div> ';

		$pageindex .= sprintf($base_link, $start - $num_per_page, $txt['previous_next_back']);
	}

	// Show the first page. (>1< ... 6 7 [8] 9 10 ... 15)
	if ($start > $num_per_page * $contiguous)
		$pageindex .= sprintf($base_link, 0, '1');

	// Show the ... after the first page. (1 >...< 6 7 [8] 9 10 ... 15)
	if ($start > $num_per_page * ($contiguous + 1))
	{
		$base_page = $flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d';
		$pageindex .= '<a data-href="' . $base_page . '" onclick="expandPages(this, ' . $num_per_page . ', ' . ($start - $num_per_page * $contiguous) . ', ' . $num_per_page . ');">&hellip;</a> ';
	}

	// Show the pages before the current one. (1 ... >6 7< [8] 9 10 ... 15)
	for ($nCont = $contiguous; $nCont >= 1; $nCont--)
		if ($start >= $num_per_page * $nCont)
		{
			$tmpStart = $start - $num_per_page * $nCont;
			$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
		}

	// Show the current page. (1 ... 6 7 >[8]< 9 10 ... 15)
	if (!$start_invalid)
	{
		$page_num = ($start / $num_per_page + 1);
		$pageindex .= '<strong>' . $page_num . '</strong> ';
		if ($page_num > 1 && !isset($context['page_indicator']))
			$context['page_indicator'] = number_context('page_indicator', $page_num);
	}
	else
		$pageindex .= sprintf($base_link, $start, $start / $num_per_page + 1);

	// Show the pages after the current one... (1 ... 6 7 [8] >9 10< ... 15)
	$tmpMaxPages = (int) (($max_value - 1) / $num_per_page) * $num_per_page;
	for ($nCont = 1; $nCont <= $contiguous; $nCont++)
		if ($start + $num_per_page * $nCont <= $tmpMaxPages)
		{
			$tmpStart = $start + $num_per_page * $nCont;
			$pageindex .= sprintf($base_link, $tmpStart, $tmpStart / $num_per_page + 1);
		}

	// Show the '...' part near the end. (1 ... 6 7 [8] 9 10 >...< 15)
	if ($start + $num_per_page * ($contiguous + 1) < $tmpMaxPages)
	{
		if (!isset($base_page))
			$base_page = $flexible_start ? $base_url : strtr($base_url, array('%' => '%%')) . ';start=%1$d';
		$pageindex .= '<a data-href="' . $base_page . '" onclick="expandPages(this, ' . ($start + $num_per_page * ($contiguous + 1)) . ', ' . $tmpMaxPages . ', ' . $num_per_page . ');">&hellip;</a> ';
	}

	// Show the last number in the list. (1 ... 6 7 [8] 9 10 ... >15<)
	if ($start + $num_per_page * $contiguous < $tmpMaxPages)
		$pageindex .= sprintf($base_link, $tmpMaxPages, $tmpMaxPages / $num_per_page + 1);

	// Finally, the next link.
	if ($show_prevnext && $start + $num_per_page < $max_value)
	{
		$pageindex .= '<span class="next_page">' . sprintf($base_link, $start + $num_per_page, $txt['previous_next_forward']) . '</span>';

		// If we're in a topic page, and later pages have unread posts, show a New notification after the Next link!
		if (empty($options['view_newest_first']) && !empty($topicinfo['new_from']) && $topicinfo['new_from'] <= $topicinfo['id_last_msg'])
			$pageindex .= ' <div class="note next_page">' . $txt['new_short'] . '</div> ';
	}

	return rtrim($pageindex, ' ');
}

// Generate a strip of buttons.
function template_button_strip($button_strip, $direction = 'right', $extra = '')
{
	global $context, $txt;

	// List the buttons in reverse order for RTL languages.
	if ($context['right_to_left'])
		$button_strip = array_reverse($button_strip, true);

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
		if (!isset($value['test']) || !empty($context[$value['test']]))
			$buttons[] = '
				<li><a' . (isset($value['id']) ? ' id="button_strip_' . $value['id'] . '"' : '') . ' class="' . $key . (!empty($value['class']) ? ' ' . $value['class'] : '') . '" href="' . $value['url'] . '"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $txt[$value['text']] . '</a></li>';

	// No buttons? No button strip either.
	if (empty($buttons))
		return '';

	// Make the last one, as easy as possible.
	$buttons[count($buttons) - 1] = str_replace('<li>', '<li class="last">', $buttons[count($buttons) - 1]);

	return '
			<ul class="buttonlist' . (!empty($direction) ? ' float' . $direction : '') . (empty($buttons) ? ' hide' : '') . '"' . ($extra ? ' ' . ltrim($extra) : '') . '>' .
				implode('', $buttons) . '
			</ul>';
}
