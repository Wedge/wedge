<?php
// Version: 2.0 RC3; index

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

	/* Use images from default theme when using templates from the default theme?
		if this is 'always', images from the default theme will be used.
		if this is 'defaults', images from the default theme will only be used with default templates.
		if this is 'never' or isn't set at all, images from the default theme will not be used. */
	$settings['use_default_images'] = 'never';

	/* What document type definition is being used? (for font size and other issues.)
		'xhtml' for an XHTML 1.0 document type definition.
		'html' for an HTML 4.01 document type definition. */
	$settings['doctype'] = 'xhtml';

	/* The version this template/theme is for.
		This should probably be the version of SMF it was created for. */
	$settings['theme_version'] = '2.0 RC1';

	/* Set a setting that tells the theme that it can render the tabs. */
	$settings['use_tabs'] = false;

	/* Use plain buttons - as opposed to text buttons? */
	$settings['use_buttons'] = false;

	/* Show sticky and lock status separate from topic icons? */
	$settings['separate_sticky_lock'] = false;

	/* Does this theme use the strict doctype? */
	$settings['strict_doctype'] = false;

	/* Does this theme use post previews on the message index? */
	$settings['message_index_preview'] = false;

	/* Set the following variable to true if this theme requires the optional theme strings file to be loaded. */
	$settings['require_theme_strings'] = false;
}

// The main sub template above the content.
function template_html_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Show right to left and the character set for ease of translating.
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '><head>
	<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
	<meta name="description" content="', $context['page_title_html_safe'], '" />', !empty($context['meta_keywords']) ? '
	<meta name="keywords" content="' . $context['meta_keywords'] . '" />' : '', '
	<title>', $context['page_title_html_safe'], '</title>';

	// Please don't index these Mr Robot.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex" />';

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '" />';

	// The ?rc3 part of this link is just here to make sure browsers don't cache it wrongly.
	echo '
	<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/style.css?rc3" />
	<link rel="stylesheet" type="text/css" href="', $settings['default_theme_url'], '/css/print.css?rc3" media="print" />';

	// Show all the relative links, such as help, search, contents, and the like.
	echo '
	<link rel="help" href="', $scripturl, '?action=help" />
	<link rel="search" href="' . $scripturl . '?action=search" />
	<link rel="contents" href="', $scripturl, '" />';

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty($modSettings['xmlnews_enable']))
		echo '
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['rss'], '" href="', $scripturl, '?type=rss;action=.xml" />';

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty($context['current_topic']))
		echo '
	<link rel="prev" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=prev" />
	<link rel="next" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=next" />';

	// If we're in a board, or a topic for that matter, the index will be the board's index.
	if (!empty($context['current_board']))
		echo '
	<link rel="index" href="' . $scripturl . '?board=' . $context['current_board'] . '.0" />';

	// We'll have to use the cookie to remember the header...
	if ($context['user']['is_guest'])
		$options['collapse_header'] = !empty($_COOKIE['upshrink']);

	echo '
	<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/script.js?rc3"></script>
	<script type="text/javascript" src="', $settings['theme_url'], '/scripts/theme.js?rc3"></script>
	<script type="text/javascript"><!-- // --><![CDATA[
		var smf_theme_url = "', $settings['theme_url'], '";
		var smf_default_theme_url = "', $settings['default_theme_url'], '";
		var smf_images_url = "', $settings['images_url'], '";
		var smf_scripturl = "', $scripturl, '";
		var smf_iso_case_folding = ', $context['server']['iso_case_folding'] ? 'true' : 'false', ';
		var smf_charset = "', $context['character_set'], '";', $context['show_pm_popup'] ? '
		var fPmPopup = function ()
		{
			if (confirm("' . $txt['show_personal_messages'] . '"))
				window.open(smf_prepareScriptUrl(smf_scripturl) + "action=pm");
		}
		addLoadEvent(fPmPopup);' : '', '
		var ajax_notification_text = "', $txt['ajax_in_progress'], '";
		var ajax_notification_cancel_text = "', $txt['modify_cancel'], '";
	// ]]></script>';

	// Output any remaining HTML headers. (from mods, maybe?)
	echo $context['html_headers'];

	echo '
</head>
<body>';
}

function template_body_above()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Because of the way width/padding are calculated, we have to tell Internet Explorer 4 and 5 that the content should be 100% wide. (or else it will assume about 108%!)
	echo '
	<div id="headerarea" style="padding: 12px 30px 4px 30px;', $context['browser']['needs_size_fix'] && !$context['browser']['is_ie6'] ? ' width: 100%;' : '', '">';

	// The logo and the three info boxes.
	echo '
		<table cellspacing="0" cellpadding="0" border="0" width="100%" style="position: relative;">
			<tr>
				<td colspan="2" valign="bottom" style="padding: 5px; white-space: nowrap;">';

	// This part is the logo and forum name.  You should be able to change this to whatever you want...
	echo '
					<img src="', $settings['images_url'], '/smflogo.gif" style="width: 250px; float: ', !$context['right_to_left'] ? 'right' : 'left', ';" alt="" />';
	if (empty($context['header_logo_url_html_safe']))
		echo '
					<span style="font-family: Georgia, sans-serif; font-size: xx-large;">', $context['forum_name_html_safe'], '</span>';
	else
		echo '
					<img src="', $context['header_logo_url_html_safe'], '" alt="', $context['forum_name_html_safe'], '" border="0" />';

	echo '
				</td>
			</tr>
			<tr id="upshrinkHeader"', empty($options['collapse_header']) ? '' : ' style="display: none;"', '>
				<td valign="top">
					<div class="headertitles" style="margin-right: 5px; position: relative;"><img src="', $settings['images_url'], '/blank.gif" height="12" alt="" /></div>
					<div class="headerbodies" style="position: relative; margin-right: 5px; background-image: url(', $settings['images_url'], '/box_bg.gif);">
						<img src="', $settings['lang_images_url'], '/userinfo.gif" style="position: absolute; left: ', $context['browser']['is_ie5'] ? '0' : '-1px', '; top: -16px;" class="clear" alt="" />
						<table width="99%" cellpadding="0" cellspacing="5" border="0"><tr>';

	if (!empty($context['user']['avatar']))
		echo '<td valign="middle">', $context['user']['avatar']['image'], '</td>';

	echo '<td valign="top" class="smalltext" style="width: 100%; font-family: verdana, arial, sans-serif;">';

	// If the user is logged in, display stuff like their name, new messages, etc.
	if ($context['user']['is_logged'])
	{
		echo '
							', $txt['hello_member'], ' <strong>', $context['user']['name'], '</strong>';

		// Only tell them about their messages if they can read their messages!
		if ($context['allow_pm'])
			echo ', ', $txt['msg_alert_you_have'], ' <a href="', $scripturl, '?action=pm">', $context['user']['messages'], ' ', $context['user']['messages'] != 1 ? $txt['msg_alert_messages'] : $txt['message_lowercase'], '</a>', $txt['newmessages4'], ' ', $context['user']['unread_messages'], ' ', $context['user']['unread_messages'] == 1 ? $txt['newmessages0'] : $txt['newmessages1'];
		echo '.<br />';

		// Is the forum in maintenance mode?
		if ($context['in_maintenance'] && $context['user']['is_admin'])
			echo '
							<strong>', $txt['maintain_mode_on'], '</strong><br />';

		// Are there any members waiting for approval?
		if (!empty($context['unapproved_members']))
			echo '
							', $context['unapproved_members'] == 1 ? $txt['approve_thereis'] : $txt['approve_thereare'], ' <a href="', $scripturl, '?action=admin;area=viewmembers;sa=browse;type=approve">', $context['unapproved_members'] == 1 ? $txt['approve_member'] : $context['unapproved_members'] . ' ' . $txt['approve_members'], '</a> ', $txt['approve_members_waiting'], '<br />';

		// Show the total time logged in?
		if (!empty($context['user']['total_time_logged_in']))
		{
			echo '
							', $txt['totalTimeLogged1'];

			// If days is just zero, don't bother to show it.
			if ($context['user']['total_time_logged_in']['days'] > 0)
				echo $context['user']['total_time_logged_in']['days'] . $txt['totalTimeLogged2'];

			// Same with hours - only show it if it's above zero.
			if ($context['user']['total_time_logged_in']['hours'] > 0)
				echo $context['user']['total_time_logged_in']['hours'] . $txt['totalTimeLogged3'];

			// But, let's always show minutes - Time wasted here: 0 minutes ;).
			echo $context['user']['total_time_logged_in']['minutes'], $txt['totalTimeLogged4'], '<br />';
		}

		echo '
							<a href="', $scripturl, '?action=unread">', $txt['unread_since_visit'], '</a><br />
							<a href="', $scripturl, '?action=unreadreplies">', $txt['show_unread_replies'], '</a><br />
							', $context['current_time'], '<br />';
		if (!empty($context['open_mod_reports']) && $context['show_open_reports'])
			echo '
								<a href="', $scripturl, '?action=moderate;area=reports">', sprintf($txt['mod_reports_waiting'], $context['open_mod_reports']), '</a>';
	}
	// Otherwise they're a guest - so politely ask them to register or login.
	else
	{
		echo '
							', sprintf($txt['welcome_guest'], $txt['guest_title']), '<br />
							', $context['current_time'], '<br />

							<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>

							<form action="', $scripturl, '?action=login2" method="post" accept-charset="', $context['character_set'], '" style="margin: 3px 1ex 1px 0;"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
								<div class="righttext">
									<input type="text" name="user" size="10" class="input_text" /> <input type="password" name="passwrd" size="10" class="input_password" />
									<select name="cookielength">
										<option value="60">', $txt['one_hour'], '</option>
										<option value="1440">', $txt['one_day'], '</option>
										<option value="10080">', $txt['one_week'], '</option>
										<option value="43200">', $txt['one_month'], '</option>
										<option value="-1" selected="selected">', $txt['forever'], '</option>
									</select>
									<input type="submit" value="', $txt['login'], '" class="button_submit" /><br />
									', $txt['quick_login_dec'], '
									<input type="hidden" name="hash_passwrd" value="" />
								</div>
							</form>';
	}

	echo '
						</td></tr></table>
					</div>

					<form action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '" style="margin: 0;">
						<div style="margin-top: 7px;">
							<strong>', $txt['search'], ': </strong><input type="text" name="search" value="" style="width: 190px;" class="input_text" />&nbsp;
							<input type="submit" name="submit" value="', $txt['search'], '" style="width: 8ex;" class="button_submit" />&nbsp;
							<a href="', $scripturl, '?action=search;advanced">', $txt['search_advanced'], '</a>
							<input type="hidden" name="advanced" value="0" />';

	// Search within current topic?
	if (!empty($context['current_topic']))
		echo '
							<input type="hidden" name="topic" value="', $context['current_topic'], '" />';

		// If we're on a certain board, limit it to this board ;).
	elseif (!empty($context['current_board']))
		echo '
							<input type="hidden" name="brd[', $context['current_board'], ']" value="', $context['current_board'], '" />';

	echo '
						</div>
					</form>

				</td>
				<td style="width: 262px; ', !$context['right_to_left'] ? 'padding-left' : 'padding-right', ': 6px;" valign="top">';

	// Show a random news item? (or you could pick one from news_lines...)
	if (!empty($settings['enable_news']))
		echo '
					<div class="headertitles" style="width: 260px;"><img src="', $settings['images_url'], '/blank.gif" height="12" alt="" /></div>
					<div class="headerbodies" style="width: 260px; position: relative; background-image: url(', $settings['images_url'], '/box_bg.gif); margin-bottom: 8px;">
						<img src="', $settings['lang_images_url'], '/newsbox.gif" style="position: absolute; left: -1px; top: -16px;" alt="" />
						<div style="height: 50px; overflow: auto; padding: 5px;" class="smalltext">', $context['random_news_line'], '</div>
					</div>';

	// The "key stats" box.
	echo '
					<div class="headertitles" style="width: 260px;"><img src="', $settings['images_url'], '/blank.gif" height="12" alt="" /></div>
					<div class="headerbodies" style="width: 260px; position: relative; background-image: url(', $settings['images_url'], '/box_bg.gif);">
						<img src="', $settings['lang_images_url'], '/keystats.gif" style="position: absolute; left: -1px; top: -16px;" alt="" />
						<div style="', !$context['browser']['is_ie'] ? 'min-height: 35px;' : 'height: 4em;', ' padding: 5px;" class="smalltext">
							<strong>', $context['common_stats']['total_posts'], '</strong> ', $txt['posts_made'], ' ', $txt['in'], ' <strong>', $context['common_stats']['total_topics'], '</strong> ', $txt['topics'], ' ', $txt['by'], ' <span style="white-space: nowrap;"><strong>', $context['common_stats']['total_members'], '</strong> ', $txt['members'], '</span><br />
							', $txt['latest_member'], ': <strong> ', $context['common_stats']['latest_member']['link'], '</strong>
						</div>
					</div>';

	echo '
				</td>
			</tr>
		</table>

		<img id="upshrink" src="', $settings['images_url'], '/upshrink.gif" alt="*" title="', $txt['upshrink_description'], '" style="margin: 2px 2ex 2px 0; display: none;" border="0" />';

		// Show the menu here, according to the menu sub template.
		template_menu();

	// Define the upper_section toggle in JavaScript.
	echo '
		<script type="text/javascript"><!-- // --><![CDATA[
			var oMainHeaderToggle = new smc_Toggle({
				bToggleEnabled: true,
				bCurrentlyCollapsed: ', empty($options['collapse_header']) ? 'false' : 'true', ',
				aSwappableContainers: [
					\'upshrinkHeader\',
					\'upshrinkHeader2\'
				],
				aSwapImages: [
					{
						sId: \'upshrink\',
						srcExpanded: smf_images_url + \'/upshrink.gif\',
						altExpanded: ', JavaScriptEscape($txt['upshrink_description']), ',
						srcCollapsed: smf_images_url + \'/upshrink2.gif\',
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
			});
		// ]]></script>';

	echo '
	</div>';

	// The main content should go here.  A table is used because IE 6 just can't handle a div.
	echo '
	<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
		<td id="bodyarea" style="padding: 1ex 20px 2ex 20px;">';

	// Show the navigation tree.
	theme_linktree();
}

function template_body_below()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '</td>
	</tr></table>';

	// Show the "Powered by" and "Valid" logos, as well as the copyright.  Remember, the copyright must be somewhere!
	echo '

	<div id="footerarea" style="text-align: center; padding-bottom: 1ex;', $context['browser']['needs_size_fix'] && !$context['browser']['is_ie6'] ? ' width: 100%;' : '', '">
		<table cellspacing="0" cellpadding="3" border="0" width="100%">
			<tr>
				<td width="28%" valign="middle" align="', !$context['right_to_left'] ? 'right' : 'left', '">
					<a href="http://www.mysql.com/" target="_blank" class="new_win"><img id="powered-mysql" src="', $settings['images_url'], '/powered-mysql.gif" alt="', $txt['powered_by_mysql'], '" width="54" height="20" style="margin: 5px 16px;" border="0" onmouseover="smfFooterHighlight(this, true);" onmouseout="smfFooterHighlight(this, false);" /></a>
					<a href="http://www.php.net/" target="_blank" class="new_win"><img id="powered-php" src="', $settings['images_url'], '/powered-php.gif" alt="', $txt['powered_by_php'], '" width="54" height="20" style="margin: 5px 16px;" border="0" onmouseover="smfFooterHighlight(this, true);" onmouseout="smfFooterHighlight(this, false);" /></a>
				</td>
				<td valign="middle" align="center" style="white-space: nowrap;">
					', theme_copyright(), '
				</td>
				<td width="28%" valign="middle" align="', !$context['right_to_left'] ? 'left' : 'right', '">
					<a href="http://validator.w3.org/check/referer" target="_blank" class="new_win"><img id="valid-xhtml10" src="', $settings['images_url'], '/valid-xhtml10.gif" alt="', $txt['valid_xhtml'], '" width="54" height="20" style="margin: 5px 16px;" border="0" onmouseover="smfFooterHighlight(this, true);" onmouseout="smfFooterHighlight(this, false);" /></a>
					<a href="http://jigsaw.w3.org/css-validator/check/referer" target="_blank" class="new_win"><img id="valid-css" src="', $settings['images_url'], '/valid-css.gif" alt="', $txt['valid_css'], '" width="54" height="20" style="margin: 5px 16px;" border="0" onmouseover="smfFooterHighlight(this, true);" onmouseout="smfFooterHighlight(this, false);" /></a>
				</td>
			</tr>
		</table>';

	// Show the load time?
	if ($context['show_load_time'])
		echo '
		<span class="smalltext">', $txt['page_created'], $context['load_time'], $txt['seconds_with'], $context['load_queries'], $txt['queries'], '</span>';

	echo '
		</div>';

	// This is an interesting bug in Internet Explorer, Safari/Webkit or Firefox.  Rather annoying, it makes overflows just not tall enough.
	if ($context['browser']['is_ie'] || $context['browser']['is_mac_ie'] || $context['browser']['is_webkit'] || $context['browser']['is_firefox'])
	{
		// The purpose of this code is to fix the height of overflow: auto div blocks, because IE can't figure it out for itself.
		echo '
		<script type="text/javascript"><!-- // --><![CDATA[';

		// Unfortunately, Safari/Webkit didn't support "getComputedStyle" implementation until Safari 3 and its buggy, so we have to just do it to code...
		if ($context['browser']['is_webkit'])
			echo '
			window.addEventListener("load", smf_codeFix, false);

			function smf_codeFix()
			{
				var codeFix = document.getElementsByTagName ? document.getElementsByTagName("div") : document.all.tags("div");

				for (var i = 0; i < codeFix.length; i++)
				{
					if ((codeFix[i].className == "code" || codeFix[i].className == "post" || codeFix[i].className == "signature") && codeFix[i].offsetHeight < 20)
						codeFix[i].style.height = (codeFix[i].offsetHeight + 20) + "px";
				}
			}';
		elseif ($context['browser']['is_firefox'])
			echo '
			window.addEventListener("load", smf_codeFix, false);
			function smf_codeFix()
			{
				var codeFix = document.getElementsByTagName ? document.getElementsByTagName("div") : document.all.tags("div");

				for (var i = 0; i < codeFix.length; i++)
				{
					if (codeFix[i].className == "code" && (codeFix[i].scrollWidth > codeFix[i].clientWidth || codeFix[i].clientWidth == 0))
						codeFix[i].style.overflow = "scroll";
				}
			}';
		else
		{
			echo '
			function smf_codeFix()
			{
				var codeFix = document.getElementsByTagName ? document.getElementsByTagName("div") : document.all.tags("div");

				for (var i = codeFix.length - 1; i > 0; i--)
				{
					if (\'currentStyle\' in codeFix[i] && codeFix[i].currentStyle.overflow == "auto" && (codeFix[i].currentStyle.height == "" || codeFix[i].currentStyle.height == "auto") && (codeFix[i].scrollWidth > codeFix[i].clientWidth || codeFix[i].clientWidth == 0) && (codeFix[i].offsetHeight != 0 || codeFix[i].className == "code"))
						codeFix[i].style.height = (codeFix[i].offsetHeight + 36) + "px";
				}
			}
			addLoadEvent(smf_codeFix);';
		}

		echo '
		// ]]></script>';
	}
}
function template_html_below()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// The following will be used to let the user know that some AJAX process is running
	echo '
		<div id="ajax_in_progress" style="display: none;', $context['browser']['is_ie'] && !$context['browser']['is_ie7'] ? 'position: absolute;' : '', '">', $txt['ajax_in_progress'], '</div>
	</body>
</html>';
}

// Show a linktree.  This is that thing that shows "My Community | General Category | General Discussion"..
function theme_linktree()
{
	global $context, $settings, $options;

	// Folder style or inline?  Inline has a smaller font.
	echo '<span class="nav"', $settings['linktree_inline'] ? ' style="font-size: smaller;"' : '', '>';

	// Each tree item has a URL and name.  Some may have extra_before and extra_after.
	foreach ($context['linktree'] as $link_num => $tree)
	{
		// Show the | | |-[] Folders.
		if (!$settings['linktree_inline'])
		{
			if ($link_num > 0)
				echo str_repeat('<img src="' . $settings['images_url'] . '/icons/linktree_main.gif" alt="| " border="0" />', $link_num - 1), '<img src="' . $settings['images_url'] . '/icons/linktree_side.gif" alt="|-" border="0" />';
			echo '<img src="' . $settings['images_url'] . '/icons/folder_open.gif" alt="+" border="0" />&nbsp; ';
		}

		// Show something before the link?
		if (isset($tree['extra_before']))
			echo $tree['extra_before'];

		// Show the link, including a URL if it should have one.
		echo '<strong>', $settings['linktree_link'] && isset($tree['url']) ? '<a href="' . $tree['url'] . '" class="nav">' . $tree['name'] . '</a>' : $tree['name'], '</strong>';

		// Show something after the link...?
		if (isset($tree['extra_after']))
			echo $tree['extra_after'];

		// Don't show a separator for the last one.
		if ($link_num != count($context['linktree']) - 1)
			echo $settings['linktree_inline'] ? ' &nbsp;|&nbsp; ' : '<br />';
	}

	echo '</span>';
}

// Show the menu up top.  Something like [home] [help] [profile] [logout]...
function template_menu()
{
	global $context, $settings, $options, $scripturl, $txt;

	// We aren't showing all the buttons in this theme.
	$hide_buttons = array('pm', 'mlist');

	foreach ($context['menu_buttons'] as $act => $button)
		if (in_array($act, $hide_buttons))
			continue;
		else
			echo '
				<a href="', $button['href'], '"', isset($button['target']) ? ' target="' . $button['target'] . '"' : '', '>', $settings['use_image_buttons'] ? '<img src="' . $settings['lang_images_url'] . '/' . $act . '.gif" alt="' . $button['title'] . '" border="0" />' : $button['title'], '</a>', !empty($button['is_last']) ? '' : $context['menu_separator'];
}

// Generate a strip of buttons, out of buttons.
function template_button_strip($button_strip, $direction = 'top', $strip_options = array())
{
	global $settings, $context, $txt, $scripturl;

	// Compatibility.
	if (!is_array($strip_options))
		$strip_options = array('custom_td' => $strip_options);

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
		if (!isset($value['test']) || !empty($context[$value['test']]))
			$buttons[] = '<a href="' . $value['url'] . '"' . (isset($value['content']) ? $value['content'] : (isset($value['active']) ? ' class="active"' : '') . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . ($settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/' . ($value['lang'] ? $context['user']['language'] . '/' : '') . $value['image'] . '" alt="' . $txt[$value['text']] . '" border="0" />' : $txt[$value['text']])) . '</a>';

	echo '
		<div ', isset($strip_options['custom_td']) ? $strip_options['custom_td'] : '', '', (empty($buttons) ? ' style="display: none;"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"': ''), '>', implode($context['menu_separator'], $buttons) , '</div>';
}

?>