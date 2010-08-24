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
	<title>', $context['page_title_html_safe'], '</title>
	<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/style.css?rc3" />
	<link rel="stylesheet" type="text/css" href="', $settings['default_theme_url'], '/css/print.css?rc3" media="print" />';

	// Please don't index these Mr Robot.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex" />';

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '" />';

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

	// The logo, user information, news, and menu.
	echo '
	<table cellspacing="0" cellpadding="0" border="0" align="center" width="95%" class="tborder">
		<tr style="background-color: #ffffff;">
			<td valign="middle" align="left"><img src="', !empty($context['header_logo_url_html_safe']) ? $context['header_logo_url_html_safe'] : $settings['images_url'] . '/smflogo.gif', '" alt="" /></td>
			<td valign="middle">';

	// If the user is logged in, display stuff like their name, new messages, etc.
	if ($context['user']['is_logged'])
	{
		echo '
				', $txt['hello_member'], ' <strong>', $context['user']['name'], '</strong>', $context['allow_pm'] ? ', ' . $txt['msg_alert_you_have'] . ' <a href="' . $scripturl . '?action=pm">' . $context['user']['messages'] . ' ' . ($context['user']['messages'] != 1 ? $txt['msg_alert_messages'] : $txt['message_lowercase']) . '</a>' . $txt['newmessages4'] . ' ' . $context['user']['unread_messages'] . ' ' . ($context['user']['unread_messages'] == 1 ? $txt['newmessages0'] : $txt['newmessages1']) : '', '.';

		// Are there any members waiting for approval?
		if (!empty($context['unapproved_members']))
			echo '<br />
				', $context['unapproved_members'] == 1 ? $txt['approve_thereis'] : $txt['approve_thereare'], ' <a href="', $scripturl, '?action=admin;area=viewmembers;sa=browse;type=approve">', $context['unapproved_members'] == 1 ? $txt['approve_member'] : $context['unapproved_members'] . ' ' . $txt['approve_members'], '</a> ', $txt['approve_members_waiting'];

		// Is the forum in maintenance mode?
		if ($context['in_maintenance'] && $context['user']['is_admin'])
			echo '<br />
				<strong>', $txt['maintain_mode_on'], '</strong>';

		if (!empty($context['open_mod_reports']) && $context['show_open_reports'])
			echo '<br />
				<a href="', $scripturl, '?action=moderate;area=reports">', sprintf($txt['mod_reports_waiting'], $context['open_mod_reports']), '</a>';
	}
	// Otherwise they're a guest - so politely ask them to register or login.
	else
		echo '
				', sprintf($txt['welcome_guest'], $txt['guest_title']);

	echo '
				<br />', $context['current_time'], '
			</td>
		</tr>
		<tr class="windowbg2">
			<td colspan="2" valign="middle" align="center" class="tborder" style="border-width: 1px 0 0 0; font-size: smaller;">';

	// Show the menu here, according to the menu sub template.
	template_menu();

	echo '
			</td>
		</tr>';

	// Show a random news item? (or you could pick one from news_lines...)
	if (!empty($settings['enable_news']))
		echo '
		<tr class="windowbg2">
			<td colspan="2" height="24" class="tborder" style="border-width: 1px 0 0 0; padding-left: 1ex;">
				<strong>', $txt['news'], ':</strong> ', $context['random_news_line'], '
			</td>
		</tr>';

	echo '
	</table>

	<br />
	<table cellspacing="0" cellpadding="10" border="0" align="center" width="95%" class="tborder">
		<tr><td valign="top" style="background-color: #ffffff;">';

	// Show the navigation tree.
	theme_linktree();
}

function template_body_below()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
		</td></tr>
	</table>';

	// Show a vB style login for quick login?
	if ($context['show_quick_login'])
	{
		echo '
	<table cellspacing="0" cellpadding="0" border="0" align="center" width="95%">
		<tr><td nowrap="nowrap" align="right">
			<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>

			<form action="', $scripturl, '?action=login2" method="post" accept-charset="', $context['character_set'], '"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '><br />
				<input type="text" name="user" size="7" class="input_text" />
				<input type="password" name="passwrd" size="7" class="input_password" />
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
			</form>
		</td></tr>
	</table>';
	}

	// Don't show a login box, just a break.
	else
		echo '
	<br />';

	// Show the "Powered by" and "Valid" logos, as well as the copyright.  Remember, the copyright must be somewhere!
	echo '
	<br />

	<table cellspacing="0" cellpadding="3" border="0" align="center" width="95%" class="tborder">
		<tr style="background-color: #ffffff;">
			<td width="28%" valign="middle" align="right">
				<a href="http://www.mysql.com/" target="_blank" class="new_win"><img src="', $settings['images_url'], '/mysql.gif" alt="', $txt['powered_by_mysql'], '" width="88" height="31" border="0" /></a>
				<a href="http://www.php.net/" target="_blank" class="new_win"><img src="', $settings['images_url'], '/php.gif" alt="', $txt['powered_by_php'], '" width="88" height="31" border="0" /></a>
			</td>
			<td width="44%" valign="middle" align="center">
				', theme_copyright(), '
			</td>
			<td width="28%" valign="middle" align="left">
				<a href="http://validator.w3.org/check/referer" target="_blank" class="new_win"><img src="', $settings['images_url'], '/valid-xhtml10.gif" alt="', $txt['valid_xhtml'], '" width="88" height="31" border="0" /></a>
				<a href="http://jigsaw.w3.org/css-validator/check/referer" target="_blank" class="new_win"><img src="', $settings['images_url'], '/valid-css.gif" alt="', $txt['valid_css'], '" width="88" height="31" border="0" /></a>
			</td>
		</tr>
	</table>';

	// Show the load time?
	if ($context['show_load_time'])
		echo '
	<div class="centertext smalltext">
		', $txt['page_created'], $context['load_time'], $txt['seconds_with'], $context['load_queries'], $txt['queries'], '
	</div>';
}

function template_html_below()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// And then we're done!
	echo '
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
			$buttons[] = '<a href="' . $value['url'] . '"' . (isset($value['active']) ? ' class="active"' : '') . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . ($settings['use_image_buttons'] ? '<img src="' . $settings['images_url'] . '/' . ($value['lang'] ? $context['user']['language'] . '/' : '') . $value['image'] . '" alt="' . $txt[$value['text']] . '" border="0" />' : $txt[$value['text']]) . '</a>';

	if (empty($button_strip))
		return '';

	echo '
		<div ', isset($strip_options['custom_td']) ? $strip_options['custom_td'] : '', '', (empty($buttons) ? ' style="display: none;"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"': ''), '>', implode($context['menu_separator'], $buttons) , '</div>';
}

?>