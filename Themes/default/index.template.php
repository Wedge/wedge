<?php
// Version: 2.0 RC5; index

/*
	This template is probably the most important one in the theme.
	It contains the html and body layers, as well as the init sub-
	template, which can be missing, and should be used to load any
	data and set any hardcoded options. The html layer sets the HTML
	headers, while the body layer shows the actual header, contents
	and footer of the page, split this way:

	- sidebar (a list of sub-templates that should be shown in the sidebar)
	- main (a list of sub-templates that should be shown in the main contents area)
	- theme_linktree (displays the link tree, using the data in the $context['linktree'] variable)
	- template_menu (displays the menu, using the data in $context['menu_items'])
	- template_button_strip (displays contextual buttons)
*/

// Initialize the template... mainly little settings.
function template_init()
{
	global $context, $settings, $options, $txt;

	// Add the theme-specific Javascript files to our priority cache list.
	if (!empty($context['javascript_files']))
	{
		$context['javascript_files'][] = 'scripts/theme.js';
		if ($context['user']['is_guest'] && !empty($context['show_login_bar']))
			$context['javascript_files'][] = 'scripts/sha1.js';
	}

	/* Use images from default theme when using templates from the default theme?
		if this is 'always', images from the default theme will be used.
		if this is 'defaults', images from the default theme will only be used with default templates.
		if this is 'never' or isn't set at all, images from the default theme will not be used. */
	$settings['use_default_images'] = 'never';

	/* Use plain buttons - as opposed to text buttons? */
	$settings['use_buttons'] = true;

	/* Does this theme use post previews on the message index? */
	$settings['message_index_preview'] = false;

	/* Set the following variable to true if this theme requires the optional theme strings file to be loaded. */
	$settings['require_theme_strings'] = false;

	/* You can define blocks for your theme, with default contents. Then, stylings can override them through
		the settings.xml file (see the styles/Warm/settings.xml file for a sample implementation.)
		Block names are case-sensitive, for performance reasons. */

	$settings['blocks'] = array(

		// We start with the header bars. Nothing special about them...
		'title'		=> '<header class="title">{body}</header>',
		'title2'	=> '<header class="title2">{body}</header>',
		'cat'		=> '<header class="cat">{body}</header>',

		// Now with a regular content block. You may add a class, title and/or footer to it. If you don't specify a title,
		// everything between the <if:title> tags will be hidden. Same for the footer and class.
		'block'		=> '<section class="block<if:class> {class}</if:class>"<if:style> style="{style}"</if:style>>'
						. '<if:header><header>{header}</header></if:header>'
						. '{body}'
						. '<if:footer><footer>{footer}</footer></if:footer></section>',

		// Our sidebar. Note that we can serve different content to different browsers by using an array
		// with browser names and a "else" fallback. This can also be done in settings.xml
		// with the <block name="..." for="ie6,ie7"> keyword.
		'sidebar'	=> array(
			'ie6'	=> '<table id="edge"><tr><td id="sidebar" class="top">{body}</td>',
			'ie7'	=> '<table id="edge"><tr><td id="sidebar" class="top">{body}</td>',
			'else'	=> '<div id="edge"><aside id="sidebar">{body}</aside>',
		),

		// Now for a little trick -- since IE6 and IE7 need to be in a table, we're closing here
		// the table that was opened in the sidebar block.
		'content'	=> array(
			'ie6'	=> '<td id="main_content" class="top">{body}</td></tr></table>',
			'ie7'	=> '<td id="main_content" class="top">{body}</td></tr></table>',
			'else'	=> '<div id="main_content">{body}</div></div>',
		),

		// The main header of the website. Feel free to redefine it in your stylings and themes.
		'header'	=> '
			<if:logo><h1>
				<a href="{scripturl}">{logo}</a>
			</h1></if:logo>
			{body}',

	);
}

// The main sub template above the content.
function template_html_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings, $boardurl;

	// Declare HTML5, and show right to left and the character set for ease of translating.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', !empty($txt['lang_dictionary']) ? ' lang="' . $txt['lang_dictionary'] . '"' : '', '>
<!-- Powered by Wedge, (c) Wedgeward 2010-2011 - http://wedge.org -->
<head>
	<meta charset="utf-8">';

	// Our alltime favorites don't really like HTML5...
	if ($context['browser']['is_ie8down'])
		echo '
	<script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>';

	echo theme_base_css(), '
	<title>', $context['page_title_html_safe'], '</title>
	<link rel="shortcut icon" href="', $boardurl, '/favicon.ico" type="image/vnd.microsoft.icon">
	<meta name="description" content="', $context['page_title_html_safe'], '">', !empty($context['meta_keywords']) ? '
	<meta name="keywords" content="' . $context['meta_keywords'] . '">' : '';

	// Please don't index these, Mr Robotto.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex">';

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '">';

	// Show all the relative links, such as search, contents, and the like.
	echo '
	<link rel="search" href="', $scripturl, '?action=search">
	<link rel="contents" href="', $scripturl, '">';

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		echo '
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['rss'], '" href="', $scripturl, '?action=feed;type=rss">';

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty($context['prev_topic']))
		echo '
	<link rel="prev" href="', $scripturl, '?topic=', $context['prev_topic'], '.0">';
	if (!empty($context['next_topic']))
		echo '
	<link rel="next" href="', $scripturl, '?topic=', $context['next_topic'], '.0">';

	// If we're in a board, or a topic for that matter, the index will be the board's index.
	if (!empty($context['current_board']))
		echo '
	<link rel="index" href="', $scripturl, '?board=', $context['current_board'], '.0">';

	// Output any remaining HTML headers. (Mods may easily add code there.)
	echo $context['header'], '
</head>
<body>';
}

function template_body_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings, $user_info;

	echo '
<div id="wedge">', !empty($settings['forum_width']) ? '<div id="wrapper" style="width: ' . $settings['forum_width'] . '">' : '', '
	<div id="header"><div class="frame">
		<div id="top_section"><div class="frame">
			<div id="upshrink"', empty($options['collapse_header']) ? ' class="fold"' : '', ' title="', $txt['upshrink_description'], '"></div>';

	if (!empty($context['allow_search']))
	{
		echo '
			<form id="search_form" action="', $scripturl, '?action=search2" method="post" accept-charset="UTF-8">
				<input type="search" name="search" value="" class="search">
				<input type="submit" name="submit" value="', $txt['search'], '">
				<input type="hidden" name="advanced" value="0">';

		// Search within current topic?
		if (!empty($context['current_topic']))
			echo '
				<input type="hidden" name="topic" value="', $context['current_topic'], '">';
		// Or within current board?
		elseif (!empty($context['current_board']))
			echo '
				<input type="hidden" name="brd[', $context['current_board'], ']" value="', $context['current_board'], '">';

		echo '
			</form>';
	}

	$languages = glob($settings['theme_dir'] . '/languages/Flag.*.png');
	if (count($languages) > 1)
	{
		$lng = $user_info['url'];
		$lng .= strpos($lng, '?') !== false ? ';' : '?';
		if (strpos($lng, 'language=') !== false)
			$lng = preg_replace('~([;&?])language=[a-z]+[;&]~i', '$1', $lng);

		echo '
			<p>';
		foreach (array_map('basename', $languages) as $language)
			echo '
				<a href="' . $lng . 'language=' . substr($language, 5, -4) . '" title="' . westr::ucwords(westr::htmlspecialchars(substr($language, 5, -4))) . '"><img src="' . $settings['theme_url'] . '/languages/' . $language . '"></a>';
		echo '
			</p>';
	}

	// Show a random news item? (or you could pick one from news_lines...)
	if (!empty($settings['enable_news']))
		echo '
			<h2>', $txt['news'], '</h2>
			<p>', $context['random_news_line'], '</p>';

	echo '
		</div></div>
		<div id="upper_section" class="middletext"', empty($options['collapse_header']) ? '' : ' style="display: none"', '><div class="frame">
			<we:header logo="', $context['header_logo_url_html_safe'], '">', $context['site_slogan'], '</we:header>
		</div></div>
	</div></div>';

	// Show the menu here, according to the menu sub template.
	template_menu();

	// Show the navigation tree.
	theme_linktree();

	// The main content should go here.
	echo '

	<div id="content"><div class="frame">';
}

function template_sidebar_above()
{
	global $txt, $scripturl, $context;

	echo '<we:sidebar><div class="column">
			<we:title>
				<span class="greeting">', $txt['hello_member_ndt'], ' <span>', $context['user']['name'], '</span></span>
			</we:title>
			<div id="userbox">';

	// If the user is logged in, display stuff like their name, new messages, etc.
	if ($context['user']['is_logged'])
	{
		echo empty($context['user']['avatar']) ? '
				<ul id="noava">' : '
				' . $context['user']['avatar']['image'] . '
				<ul>', '
					<li><a href="', $scripturl, '?action=unread">', $txt['show_unread'], '</a></li>
					<li><a href="', $scripturl, '?action=unreadreplies">', $txt['show_unread_replies'], '</a></li>';

		// Are there any members waiting for approval?
		if (!empty($context['unapproved_members']))
			echo '
					<li>', $context['unapproved_members'] == 1 ? $txt['approve_thereis'] : $txt['approve_thereare'], ' <a href="', $scripturl, '?action=admin;area=viewmembers;sa=browse;type=approve">', $context['unapproved_members'] == 1 ? $txt['approve_member'] : $context['unapproved_members'] . ' ' . $txt['approve_members'], '</a> ', $txt['approve_members_waiting'], '</li>';

		if (!empty($context['open_mod_reports']) && $context['show_open_reports'])
			echo '
					<li><a href="', $scripturl, '?action=moderate;area=reports">', sprintf($txt['mod_reports_waiting'], $context['open_mod_reports']), '</a></li>';

		echo '
				</ul>
				<p>', $context['current_time'], '</p>';

		// Is the forum in maintenance mode?
		if ($context['in_maintenance'] && $context['user']['is_admin'])
			echo '
				<p class="notice">', $txt['maintain_mode_on'], '</p>';
	}
	// Otherwise they're a guest - this time ask them to either register or login - lazy bums...
	elseif (!empty($context['show_login_bar']))
	{
		echo '
				<form id="guest_form" action="', $scripturl, '?action=login2" method="post" accept-charset="UTF-8" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
					<div class="info">', $txt['login_or_register'], '</div>
					<input type="text" name="user" size="10">
					<input type="password" name="passwrd" size="10">
					<select name="cookielength">
						<option value="60">', $txt['one_hour'], '</option>
						<option value="1440">', $txt['one_day'], '</option>
						<option value="10080">', $txt['one_week'], '</option>
						<option value="43200">', $txt['one_month'], '</option>
						<option value="-1" selected>', $txt['forever'], '</option>
					</select>
					<input type="submit" value="', $txt['login'], '" class="submit"><br>
					<div class="info">', $txt['quick_login_dec'], '</div>';

		if (!empty($modSettings['enableOpenID']))
			echo '
					<br><input type="text" name="openid_identifier" id="openid_url" size="25" class="openid_login">';

		echo '
					<input type="hidden" name="hash_passwrd" value="">
				</form>';
	}

	echo '
			</div>';
}

// This natty little function makes pretty RSS stuff in the sidebar. Mostly autonomous, it's lovely for that.
// This function is only added to the list if the feeds are available, so we don't even need to check anything.
function template_sidebar_rss()
{
	global $topic, $board, $txt, $context, $scripturl, $modSettings, $settings, $board_info;

	echo '
			<we:title>
				<div class="feed_icon"></div>
				', $txt['rss'], '
			</we:title>
			<dl id="rss">';

	// Topic RSS links
	if (!empty($topic))
		echo '
				<dt>', $txt['rss_current_topic'], '</dt>
				<dd>', sprintf($txt['rss_posts'], $scripturl . '?topic=' . $topic . ';action=feed;type=rss'), '</dd>';

	// Board level RSS links
	if (!empty($board))
	{
		$rss = $scripturl . '?board=' . $board_info['id'] . ';action=feed;type=rss';
		echo '
				<dt>', $board_info['type'] == 'blog' ? $txt['rss_current_blog'] : $txt['rss_current_board'], '</dt>
				<dd>', sprintf($txt['rss_posts'], $rss), ' / ', sprintf($txt['rss_topics'], $rss . ';sa=news'), '</dd>';
	}

	// Forum wide and end
	$rss = $scripturl . '?action=feed;type=rss';
	echo '
				<dt>', $txt['rss_everywhere'], '</dt>
				<dd>', sprintf($txt['rss_posts'], $rss), ' / ', sprintf($txt['rss_topics'], $rss . ';sa=news'), '</dd>
			</dl>';
}

function template_sidebar_below()
{
	echo '
		</div></we:sidebar>';
}

function template_main_above()
{
	echo '
		<we:content>';
}

function template_main_below()
{
	echo '
		</we:content>';
}

function template_body_below()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
	</div></div>';

	if (!empty($context['bottom_linktree']))
		theme_linktree(false, true);

	// Show the "Powered by" and "Valid" logos, as well as the copyright.
	echo '
	<div id="footer"><div class="frame">
		<ul class="reset">
			<li class="copyright">', theme_copyright(), '</li>
			<li><a id="site_credits" href="', $scripturl, '?action=credits"><span>', $txt['site_credits'], '</span></a></li>
			<li>|&nbsp;&nbsp;<a id="button_html5" href="http://validator.w3.org/check/referer" target="_blank" class="new_win" title="', $txt['valid_html5'], '"><span>', $txt['html5'], '</span></a></li>
			<li class="last">|&nbsp;&nbsp;<a id="button_wap2" href="', $scripturl, '?wap2" class="new_win"><span>', $txt['wap2'], '</span></a></li>
		</ul>';

	// Show the load time?
	if ($context['show_load_time'])
		echo '
		<p>', $txt['page_created'], $context['load_time'], $txt['seconds_with'], $context['load_queries'], $txt['queries'], '</p>';

	echo '
	</div></div>', !empty($settings['forum_width']) ? '</div>' : '', '
</div>
', $context['browser']['is_ie6'] || $context['browser']['is_ie7'] || $context['browser']['is_iphone'] ? '' : '
<script><!-- // --><![CDATA[
	function noi_resize()
	{
		var d = document, e1 = d.getElementById("edge"), e2 = d.getElementById("edgehide"), m = d.getElementById("main_content"), w = m ? m.clientWidth : 0;
		if (w && w < 728 && !wedge_side && e1) { wedge_side = 1; e1.id = "edgehide"; }
		else if (w >= 952 && wedge_side && e2) { wedge_side = 0; e2.id = "edge"; }
	}
	wedge_side = 0; noi_resize(); window.onresize = noi_resize;
// ]]></script>';
}

function template_html_below()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings, $footer_coding;

	// Include postponed inline JS, postponed HTML, and then kickstart the main
	// Javascript section -- files to include, main vars and functions to start.
	// Don't modify the HTML comments, as they're placeholders for Wedge.

	echo $context['footer'], '
<!-- Javascript area -->';

	// Code added here through add_js_inline() will execute before jQuery
	// and script.js are loaded. You may add time-critical events here.
	if (!empty($context['footer_js_inline']))
		echo '

<script><!-- // --><![CDATA[', $context['footer_js_inline'], '
// ]]></script>';

	echo "\n", theme_base_js(), '
<script><!-- // --><![CDATA[
	var smf_theme_url = "', $settings['theme_url'], '";
	var smf_default_theme_url = "', $settings['default_theme_url'], '";
	var smf_images_url = "', $settings['images_url'], '";
	var smf_scripturl = "', $scripturl, '";
	var smf_iso_case_folding = ', $context['server']['iso_case_folding'] ? 'true' : 'false', ';
	var ajax_notification_text = "', $txt['ajax_in_progress'], '";
	var ajax_notification_cancel_text = "', $txt['modify_cancel'], '";

	initMenu("main_menu");

	var oMainHeaderToggle = new smc_Toggle({
		bCurrentlyCollapsed: ', empty($options['collapse_header']) ? 'false' : 'true', ',
		aSwappableContainers: [\'upper_section\'],
		aSwapImages: [{ sId: \'upshrink\', altExpanded: ', JavaScriptEscape($txt['upshrink_description']), '}],
		oThemeOptions: {
			bUseThemeSettings: ', $context['user']['is_guest'] ? 'false' : 'true', ',
			sOptionName: \'collapse_header\',
			sSessionVar: \'', $context['session_var'], '\',
			sSessionId: \'', $context['session_id'], '\'
		},
		oCookieOptions: {
			bUseCookie: ', $context['user']['is_guest'] ? 'true' : 'false', ',
			sCookieName: \'upshrink\'
		}
	});', $context['show_pm_popup'] ? '

	if (confirm(' . JavaScriptEscape($txt['show_personal_messages']) . '))
		window.open(smf_prepareScriptUrl(smf_scripturl) + "action=pm");' : '';

	// Output any postponed Javascript added by templates
	// and mods, and close all outstanding tags. We're done!
	// $context['footer_js'] assumes the <script> tag is already output.
	echo $context['footer_js'], empty($footer_coding) ? '
<script><!-- // --><![CDATA[' : '', '
	<!-- insert inline events here -->
// ]]></script>
</body></html>';
}

// Show a linktree. This is that thing that shows "My Community | General Category | General Discussion"..
function theme_linktree($force_show = false, $on_bottom = false)
{
	global $context, $settings, $options, $shown_linktree;

	echo '
	<div id="linktree', $on_bottom ? '_bt' : '', '">';

	// If linktree is empty, just return - also allow an override.
	if (!empty($context['linktree']) && count($context['linktree']) !== 1 && (empty($context['dont_default_linktree']) || $force_show))
	{
		echo '
		<ul>';

		// Each tree item has a URL and name. Some may have extra_before and extra_after.
		foreach ($context['linktree'] as $link_num => $tree)
		{
			echo '
			<li', ($link_num == count($context['linktree']) - 1) ? ' class="last"' : '', '>';

			// Show something before the link?
			if (isset($tree['extra_before']))
				echo $tree['extra_before'];

			// Show the link, including a URL if it should have one.
			echo $settings['linktree_link'] && isset($tree['url']) ? '<a href="' . $tree['url'] . '"><span>' . $tree['name'] . '</span></a>' : '<span>' . $tree['name'] . '</span>';

			// Show something after the link...?
			if (isset($tree['extra_after']))
				echo $tree['extra_after'];

			echo '</li>';
		}
		echo '
		</ul>';
	}

	echo '
	</div>';

	$shown_linktree = true;
}

// Show the menu up top. Something like [home] [profile] [logout]...
function template_menu()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="navi"><ul id="main_menu" class="css menu">';

	foreach ($context['menu_items'] as $act => $item)
	{
		$class = ($item['active_item'] ? ' chosen' : '') . (empty($item['sub_items']) ? ' nodrop' : '');

		echo '
		<li id="item_', $act, '"', $class ? ' class="' . ltrim($class) . '"' : '', '>
			<span class="m_' . $act . '"></span>
			<h4><a href="', $item['href'], '"', isset($item['target']) ? ' target="' . $item['target'] . '"' : '', '>', $item['title'], '</a></h4>';

		if (!empty($item['sub_items']))
		{
			echo '
			<ul>';

			foreach ($item['sub_items'] as $sub_item)
			{
				echo '
				<li><a href="', $sub_item['href'], '"', isset($sub_item['target']) ? ' target="' . $sub_item['target'] . '"' : '', '>', $sub_item['title'], '</a>';

				// 3rd level menus
				if (!empty($sub_item['sub_items']))
				{
					echo '
					<ul>';

					foreach ($sub_item['sub_items'] as $subsub_item)
						echo '<li><a href="', $subsub_item['href'], '"', isset($subsub_item['target']) ? ' target="' . $subsub_item['target'] . '"' : '', '>', $subsub_item['title'], '</a></li>';

					echo '</ul>';
				}
				echo '</li>';
			}
			echo '
			</ul>';
		}
		echo '
		</li>';
	}
	echo '
	</ul></div>';
}

// Generate a strip of buttons.
function template_button_strip($button_strip, $direction = 'right', $strip_options = array())
{
	global $settings, $context, $txt, $scripturl;

	if (!is_array($strip_options))
		$strip_options = array();

	// List the buttons in reverse order for RTL languages.
	if ($context['right_to_left'])
		$button_strip = array_reverse($button_strip, true);

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
		if (!isset($value['test']) || !empty($context[$value['test']]))
			$buttons[] = '
				<li><a' . (isset($value['id']) ? ' id="button_strip_' . $value['id'] . '"' : '') . ' class="button_strip_' . $key . (isset($value['active']) ? ' active' : '') . '" href="' . $value['url'] . '"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $txt[$value['text']] . '</a></li>';

	// No buttons? No button strip either.
	if (empty($buttons))
		return;

	// Make the last one, as easy as possible.
	$buttons[count($buttons) - 1] = str_replace('class="button_strip_', 'class="last button_strip_', $buttons[count($buttons) - 1]);

	echo '
		<div class="buttonlist', !empty($direction) ? ' float' . $direction : '', '"', (empty($buttons) ? ' style="display: none"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"': ''), '>
			<ul>',
				implode('', $buttons), '
			</ul>
		</div>';
}

?>