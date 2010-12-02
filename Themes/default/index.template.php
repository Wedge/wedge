<?php
// Version: 2.0 RC4; index

/*	This template is, perhaps, the most important template in the theme. It
	contains the main template layer that displays the header and footer of
	the forum, namely with main_above and main_below. It also contains the
	menu sub template, which appropriately displays the menu; the init sub
	template, which is there to set the theme up; (init can be missing.) and
	the linktree sub template, which sorts out the link tree.

	The init sub template should load any data and set any hardcoded options.

	The main_above sub template is what is shown above the main content, and
	should contain anything that should be shown up there.

	The main_below sub template, conversely, is shown after the main content.
	It should probably contain the copyright statement and some other things.

	The linktree sub template should display the link tree, using the data
	in the $context['linktree'] variable.

	The menu sub template should display all the relevant buttons the user
	wants and or needs.

	For more information on the templating system, please see the site at:
	http://www.simplemachines.org/
*/

// Initialize the template... mainly little settings.
function template_init()
{
	global $context, $settings, $options, $txt;

	// Add the theme-specific Javascript files to our cache list.
	if (!empty($context['javascript_files']))
	{
		$context['javascript_files'][] = 'scripts/theme.js';
		if ($context['user']['is_guest'] && !empty($context['show_login_bar']))
			$context['javascript_files'][] = 'scripts/sha1.js';
		if ($context['browser']['is_ie6'])
			$context['javascript_files'][] = 'scripts/pngfix.js';
	}

	/* Use images from default theme when using templates from the default theme?
		if this is 'always', images from the default theme will be used.
		if this is 'defaults', images from the default theme will only be used with default templates.
		if this is 'never' or isn't set at all, images from the default theme will not be used. */
	$settings['use_default_images'] = 'never';

	/* The version this template/theme is for.
		This should probably be the version of SMF it was created for. */
	$settings['theme_version'] = '2.0 RC4';

	/* Set a setting that tells the theme that it can render the tabs. */
	$settings['use_tabs'] = true;

	/* Use plain buttons - as opposed to text buttons? */
	$settings['use_buttons'] = true;

	/* Does this theme use post previews on the message index? */
	$settings['message_index_preview'] = false;

	/* Set the following variable to true if this theme requires the optional theme strings file to be loaded. */
	$settings['require_theme_strings'] = false;
}

// The main sub template above the content.
function template_html_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings, $boardurl;

	// Declare HTML5, and show right to left and the character set for ease of translating.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
<!-- Powered by Wedge, (c) Wedgebox 2010 - http://wedgeforum.com -->
<head>';

	// The ?rc3 part of this link is just here to make sure browsers don't cache it wrongly.
	echo '
	<link rel="stylesheet" href="', $context['cached_css'], '" />
	<title>', $context['page_title_html_safe'], '</title>
	<link rel="shortcut icon" href="', $boardurl, '/favicon.ico" type="image/vnd.microsoft.icon" />
	<meta charset="utf-8" />
	<meta name="description" content="', $context['page_title_html_safe'], '" />', !empty($context['meta_keywords']) ? '
	<meta name="keywords" content="' . $context['meta_keywords'] . '" />' : '';

	// Please don't index these Mr Robotto.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex" />';

	if ($context['browser']['is_ie7'] || $context['browser']['is_ie8'])
	{
		$theme_url = empty($modSettings['pretty_enable_filters']) || empty($context['current_board']) ? $settings['default_theme_url'] : preg_replace('~(?<=//)([^/]+)~', $_SERVER['HTTP_HOST'], $settings['default_theme_url']);
		echo '
	<style>
		#header, #footer, .title_bar, .cat_bar, .wrc, .roundframe, .buttonlist a { position: relative; behavior: url(', $theme_url, '/css/PIE.htc); }
	</style>';
	}

	// Our alltime favorites don't really like HTML5...
	if ($context['browser']['is_ie'] && !$context['browser']['is_ie9'])
		echo '
	<script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>';

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '" />';

	// Show all the relative links, such as search, contents, and the like.
	echo '
	<link rel="search" href="', $scripturl, '?action=search" />
	<link rel="contents" href="', $scripturl, '" />';

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		echo '
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['rss'], '" href="', $scripturl, '?action=feed;type=rss" />';

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty($context['current_topic']))
		echo '
	<link rel="prev" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=prev" />
	<link rel="next" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=next" />';

	// If we're in a board, or a topic for that matter, the index will be the board's index.
	if (!empty($context['current_board']))
		echo '
	<link rel="index" href="', $scripturl, '?board=', $context['current_board'], '.0" />';

	// Output any remaining HTML headers. (Mods may easily add code here.)
	echo $context['header'];

	echo '
</head>
<body>';
}

function template_body_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
<div id="wedge">', !empty($settings['forum_width']) ? '<div id="wrapper" style="width: ' . $settings['forum_width'] . '">' : '', '
	<div id="header"><div class="frame">
		<div id="top_section">
			<h1 class="forumtitle">
				<a href="', $scripturl, '">', empty($context['header_logo_url_html_safe']) ? $context['forum_name'] : '<img src="' . $context['header_logo_url_html_safe'] . '" alt="' . $context['forum_name'] . '" />', '</a>
			</h1>';

	// the upshrink image, right-floated
	echo '
			<img id="upshrink" src="', $settings['images_url'], '/upshrink.png" alt="*" title="', $txt['upshrink_description'], '" style="display: none;" />
			', empty($settings['site_slogan']) ? '<img id="wedgelogo" src="' . $settings['images_url'] . '/wedgelogo.png" alt="Wedge" title="Wedge" />' : '<div id="siteslogan" class="floatright">' . $settings['site_slogan'] . '</div>', '
		</div>
		<div id="upper_section" class="middletext"', empty($options['collapse_header']) ? '' : ' style="display: none;"', '>
			<div class="user">';

	// If the user is logged in, display stuff like their name, new messages, etc.
	if ($context['user']['is_logged'])
	{
		if (!empty($context['user']['avatar']))
			echo '
				<p class="avatar">', $context['user']['avatar']['image'], '</p>';
		echo '
				<ul class="reset">
					<li class="greeting">', $txt['hello_member_ndt'], ' <span>', $context['user']['name'], '</span></li>
					<li><a href="', $scripturl, '?action=unread">', $txt['unread_since_visit'], '</a></li>
					<li><a href="', $scripturl, '?action=unreadreplies">', $txt['show_unread_replies'], '</a></li>';

		// Is the forum in maintenance mode?
		if ($context['in_maintenance'] && $context['user']['is_admin'])
			echo '
					<li class="notice">', $txt['maintain_mode_on'], '</li>';

		// Are there any members waiting for approval?
		if (!empty($context['unapproved_members']))
			echo '
					<li>', $context['unapproved_members'] == 1 ? $txt['approve_thereis'] : $txt['approve_thereare'], ' <a href="', $scripturl, '?action=admin;area=viewmembers;sa=browse;type=approve">', $context['unapproved_members'] == 1 ? $txt['approve_member'] : $context['unapproved_members'] . ' ' . $txt['approve_members'], '</a> ', $txt['approve_members_waiting'], '</li>';

		if (!empty($context['open_mod_reports']) && $context['show_open_reports'])
			echo '
					<li><a href="', $scripturl, '?action=moderate;area=reports">', sprintf($txt['mod_reports_waiting'], $context['open_mod_reports']), '</a></li>';

		echo '
					<li>', $context['current_time'], '</li>
				</ul>';
	}
	// Otherwise they're a guest - this time ask them to either register or login - lazy bums...
	elseif (!empty($context['show_login_bar']))
	{
		echo '
				<form id="guest_form" action="', $scripturl, '?action=login2" method="post" accept-charset="UTF-8" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
					<div class="info">', $txt['login_or_register'], '</div>
					<input type="text" name="user" size="10" class="input_text" />
					<input type="password" name="passwrd" size="10" class="input_password" />
					<select name="cookielength">
						<option value="60">', $txt['one_hour'], '</option>
						<option value="1440">', $txt['one_day'], '</option>
						<option value="10080">', $txt['one_week'], '</option>
						<option value="43200">', $txt['one_month'], '</option>
						<option value="-1" selected="selected">', $txt['forever'], '</option>
					</select>
					<input type="submit" value="', $txt['login'], '" class="button_submit" /><br />
					<div class="info">', $txt['quick_login_dec'], '</div>';

		if (!empty($modSettings['enableOpenID']))
			echo '
					<br /><input type="text" name="openid_identifier" id="openid_url" size="25" class="input_text openid_login" />';

		echo '
					<input type="hidden" name="hash_passwrd" value="" />
				</form>';
	}

	echo '
			</div>
			<div class="news normaltext">';

	if (!empty($context['allow_search']))
	{
		echo '
				<form id="search_form" action="', $scripturl, '?action=search2" method="post" accept-charset="UTF-8">
					<input type="text" name="search" value="" class="input_text" />&nbsp;
					<input type="submit" name="submit" value="', $txt['search'], '" class="button_submit" />
					<input type="hidden" name="advanced" value="0" />';

		// Search within current topic?
		if (!empty($context['current_topic']))
			echo '
					<input type="hidden" name="topic" value="', $context['current_topic'], '" />';
		// Or within current board?
		elseif (!empty($context['current_board']))
			echo '
					<input type="hidden" name="brd[', $context['current_board'], ']" value="', $context['current_board'], '" />';

		echo '</form>';
	}

	// Show a random news item? (or you could pick one from news_lines...)
	if (!empty($settings['enable_news']))
		echo '
				<h2>', $txt['news'], ': </h2>
				<p>', $context['random_news_line'], '</p>';

	echo '
			</div>
		</div>
		<br class="clear" />
	</div></div>';

	echo '
	<div id="navi">';

	// Show the menu here, according to the menu sub template.
	template_menu();

	// Show the navigation tree.
	theme_linktree();

	// The main content should go here.
	echo '
	</div>
	<div id="content"><div class="frame">
		<div id="main_content">';
}

function template_body_below()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
		</div>
	</div></div>';

	// Show the "Powered by" and "Valid" logos, as well as the copyright. Remember, the copyright must be somewhere!
	echo '
	<div id="footer"><div class="frame">
		<ul class="reset">
			<li class="copyright">', theme_copyright(), '</li>
			<li><a id="site_credits" href="', $scripturl, '?action=credits"><span>', $txt['site_credits'], '</span></a></li>
			<li>|&nbsp;&nbsp;<a id="button_html5" href="http://validator.w3.org/check/referer" target="_blank" class="new_win" title="', $txt['valid_html5'], '"><span>', $txt['html5'], '</span></a></li>',
			!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']) ? '
			<li>|&nbsp;&nbsp;<a id="button_rss" href="' . $scripturl . '?action=feed;type=rss" class="new_win"><span>' . $txt['rss'] . '</span></a></li>' : '', '
			<li class="last">|&nbsp;&nbsp;<a id="button_wap2" href="', $scripturl, '?wap2" class="new_win"><span>', $txt['wap2'], '</span></a></li>
		</ul>';

	// Show the load time?
	if ($context['show_load_time'])
		echo '
		<p>', $txt['page_created'], $context['load_time'], $txt['seconds_with'], $context['load_queries'], $txt['queries'], '</p>';

	echo '
	</div></div>', !empty($settings['forum_width']) ? '</div>' : '', $context['browser']['is_ie6'] ? '' : '
</div>';
}

function template_html_below()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings, $footer_coding;

	// Include postponed inline JS, postponed HTML, and then kickstart the main
	// Javascript section -- files to include, main vars and functions to start.
	// Don't modify <!-- insert inline events here -->, as it's a placeholder.

	echo "\n";

	if (!empty($context['footer_js_inline']))
		echo '
<script><!-- // --><![CDATA[', $context['footer_js_inline'], '
// ]]></script>';

	echo $context['footer'], "\n", !empty($modSettings['jquery_remote']) ? '
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js"></script>' : '', '
<script src="', $context['cached_js'], '"></script>
<script><!-- // --><![CDATA[
	var smf_theme_url = "', $settings['theme_url'], '";
	var smf_default_theme_url = "', $settings['default_theme_url'], '";
	var smf_images_url = "', $settings['images_url'], '";
	var smf_scripturl = "', $scripturl, '";
	var smf_iso_case_folding = ', $context['server']['iso_case_folding'] ? 'true' : 'false', ';
	var ajax_notification_text = "', $txt['ajax_in_progress'], '";
	var ajax_notification_cancel_text = "', $txt['modify_cancel'], '";', $context['browser']['is_ie6'] ? '
	DD_belatedPNG.fix(\'div,#wedgelogo,#boardindex_table img\');' : '', '

	initMenu(document.getElementById("main_menu"));

	<!-- insert inline events here -->

	var oMainHeaderToggle = new smc_Toggle({
		bToggleEnabled: true,
		bCurrentlyCollapsed: ', empty($options['collapse_header']) ? 'false' : 'true', ',
		aSwappableContainers: [
			\'upper_section\'
		],
		aSwapImages: [
			{
				sId: \'upshrink\',
				srcExpanded: smf_images_url + \'/upshrink.png\',
				altExpanded: ', JavaScriptEscape($txt['upshrink_description']), ',
				srcCollapsed: smf_images_url + \'/upshrink2.png\',
				altCollapsed: ', JavaScriptEscape($txt['upshrink_description']), '
			}
		],
		oThemeOptions: {
			bUseThemeSettings: ', $context['user']['is_guest'] ? 'false' : 'true', ',
			sOptionName: \'collapse_header\',
			sSessionVar: ', JavaScriptEscape($context['session_var']), ',
			sSessionId: ', JavaScriptEscape($context['session_id']), '
		},
		oCookieOptions: {
			bUseCookie: ', $context['user']['is_guest'] ? 'true' : 'false', ',
			sCookieName: \'upshrink\'
		}
	});', $context['show_pm_popup'] ? '

	if (confirm(' . JavaScriptEscape($txt['show_personal_messages']) . '))
		window.open(smf_prepareScriptUrl(smf_scripturl) + "action=pm");' : '';

	// Output any postponed Javascript added by templates and mods.
	echo $context['footer_js'], empty($footer_coding) ? '' : '
// ]]></script>';

	echo $context['browser']['is_ie6'] ? '
</div>' : '', '
</body></html>';
}

// Show a linktree. This is that thing that shows "My Community | General Category | General Discussion"..
function theme_linktree($force_show = false)
{
	global $context, $settings, $options, $shown_linktree;

	// If linktree is empty, just return - also allow an override.
	if (empty($context['linktree']) || (!empty($context['dont_default_linktree']) && !$force_show))
		return;

	echo '
		<div class="navigate_section">
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

		// Don't show a separator for the last one.
		if ($link_num != count($context['linktree']) - 1)
			echo ' <span class="linktree"></span>';

		echo '</li>';
	}
	echo '
			</ul>
		</div>';

	$shown_linktree = true;
}

// Show the menu up top. Something like [home] [profile] [logout]...
function template_menu()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
		<div id="menu_container"><ul id="main_menu" class="menu">';

	foreach ($context['menu_buttons'] as $act => $button)
	{
		$mh4 = empty($button['padding']) ? '' : ' style="margin-left: ' . ($button['padding'] + 6) . 'px"';
		$class = ($button['active_button'] ? ' chosen' : '') . (empty($button['sub_buttons']) ? ' nodrop' : '');
		$ic = !$mh4 ? '' : '
				<div class="m_' . $act . '">&nbsp;</div>';

		echo '
			<li id="button_', $act, '"', $class ? ' class="' . ltrim($class) . '"' : '', '>', $ic, '
				<h4', $mh4, '><a href="', $button['href'], '"', isset($button['target']) ? ' target="' . $button['target'] . '"' : '', '>', $button['title'], '</a></h4>';

		if (!empty($button['sub_buttons']))
		{
			echo '
				<ul>';

			foreach ($button['sub_buttons'] as $childbutton)
			{
				echo '
					<li><a href="', $childbutton['href'], '"', isset($childbutton['target']) ? ' target="' . $childbutton['target'] . '"' : '', '>', $childbutton['title'], '</a>';

				// 3rd level menus
				if (!empty($childbutton['sub_buttons']))
				{
					echo '
						<ul>';

					foreach ($childbutton['sub_buttons'] as $grandchildbutton)
						echo '<li><a href="', $grandchildbutton['href'], '"', isset($grandchildbutton['target']) ? ' target="' . $grandchildbutton['target'] . '"' : '', '>', $grandchildbutton['title'], '</a></li>';

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
function template_button_strip($button_strip, $direction = 'top', $strip_options = array())
{
	global $settings, $context, $txt, $scripturl;

	if (!is_array($strip_options))
		$strip_options = array();

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
	{
		if (!isset($value['test']) || !empty($context[$value['test']]))
			$buttons[] = '
				<li><a' . (isset($value['id']) ? ' id="button_strip_' . $value['id'] . '"' : '') . ' class="button_strip_' . $key . (isset($value['active']) ? ' active' : '') . '" href="' . $value['url'] . '"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $txt[$value['text']] . '</a></li>';
	}

	// No buttons? No button strip either.
	if (empty($buttons))
		return;

	// Make the last one, as easy as possible.
	$buttons[count($buttons) - 1] = str_replace('class="button_strip_', 'class="last button_strip_', $buttons[count($buttons) - 1]);

	echo '
		<div class="buttonlist', !empty($direction) ? ' float' . $direction : '', '"', (empty($buttons) ? ' style="display: none;"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"': ''), '>
			<ul>',
				implode('', $buttons), '
			</ul>
		</div>';
}

?>