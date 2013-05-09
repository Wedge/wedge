<?php
/**
 * Wedge
 *
 * The main administration template, including the home page, credits, file versions, censored words, pending-completion, generic settings handling and more.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_admin_time_remaining()
{
	global $context;

	echo '
	<div class="description" style="margin: 0 14px">
		', $context['time_remaining'], '
	</div>';
}

// This is the administration center home.
function template_admin()
{
	global $context, $theme, $options, $txt, $settings;

	// Welcome the admin, and mention any outstanding updates.
	echo '
	<div id="admin_home">
		<we:cat>
			', $txt['admin_center'], '
		</we:cat>
		<div id="update_section">
			<we:cat>
				<div id="update_title"></div>
			</we:cat>
			<div class="windowbg wrc">
				<div id="update_message" class="smalltext"></div>
			</div>
		</div>';

	if (we::$is_admin)
	{
		if (empty($options['hide_admin_intro']))
		{
			echo '
		<fieldset id="admin_intro" class="windowbg2 wrc">
			<legend>', $txt['new_to_wedge'], '</legend>
			', $txt['new_to_wedge_intro'], '<br><br>';

			foreach ($context['admin_intro'] as $column => $items)
			{
				echo '
			<div class="two-columns smalltext">';

				foreach ($items as $key => $url)
					echo '
				', $txt['new_to_wedge_' . $key], '<br>
				&rarr; ', sprintf($txt['new_to_wedge_' . $key . '_answer'], $url), '<br><br>';

				echo '
			</div>';
			}

			echo '
			<br class="clear">
			<hr>
			<div class="right">
				<form action="<URL>?action=admin">
					<input type="submit" class="delete" value="', $txt['hide_new_to_wedge'], '" onclick="hideAdminIntro(e);">
				</form>
			</div>
		</fieldset>';

			// And add the JS to deal with hiding the area. Note that we need to hide the button before sliding because some browsers drop
			// the floating aspects when performing the slide, causing the button to visibly jump further up the fieldset before it slides up.
			add_js('
	function hideAdminIntro(e)
	{
		e.preventDefault();
		$("#admin_intro input").hide();
		$("#admin_intro").slideUp();
		$.post(weUrl("action=jsoption;th=1;" + we_sessvar + "=" + we_sessid + ";time=" + $.now()), { v: "hide_admin_intro", val: 1 });
	};');
		}

		echo '
		<div id="quick_search">
			<form action="<URL>?action=admin;area=search" method="post" accept-charset="UTF-8" class="floatright">
				<input type="search" name="search_term" placeholder="', $txt['admin_search'], '" class="search">
				<select name="search_type">
					<option value="internal"', empty($context['admin_preferences']['sb']) || $context['admin_preferences']['sb'] == 'internal' ? ' selected' : '', '>', $txt['admin_search_type_internal'], '</option>
					<option value="member"', !empty($context['admin_preferences']['sb']) && $context['admin_preferences']['sb'] == 'member' ? ' selected' : '', '>', $txt['admin_search_type_member'], '</option>
				</select>
				<input type="submit" name="search_go" id="search_go" value="', $txt['admin_search_go'], '">
			</form>
			', $txt['admin_search_welcome'], '
		</div>';
	}

	// Now, let's do the main admin stuff. We'll take an alias to the menu stuff because it's simply easier than fighting with anything else.
	$menu_context =& $context['menu_data_' . $context['max_menu_id']];

	$use_bg2 = false;
	foreach ($menu_context['sections'] as $section_id => $section)
	{
		if (empty($section) || empty($section['title']))
			continue;

		echo '
		<fieldset id="admin_area_', $section_id, '" class="windowbg', $use_bg2 ? '2' : '', ' wrc">
			<legend>', $section['title'], '</legend>';

		foreach ($section['areas'] as $area_id => $area)
		{
			if (empty($area['label']) || $area_id == 'index')
				continue;

			if (empty($area['bigicon']))
				$area['bigicon'] = '<img src="' . $context['menu_image_path'] . '/features_and_options.png" style="width: 32px; height: 32px">';

			echo '
			<div class="admin_item"><a href="', isset($area['url']) ? $area['url'] : '<URL>?action=admin;area=' . $area_id, ';', $context['session_query'], '">', $area['bigicon'], '<br>', $area['label'], '</a></div>';
		}

		echo '
		</fieldset>';

		$use_bg2 = !$use_bg2;
	}

	// And we're done.
	echo '
	</div>
	<br class="clear">';
}

// Display the "live news" from wedge.org.
function template_admin_live_news()
{
	global $txt;

	echo '
	<section>
		<we:title>
			<a href="<URL>?action=help;in=live_news" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
			', $txt['live'], '
		</we:title>
		<div id="wedge_news">', $txt['lfyi'], '</div>
	</section>';
}

// Display version numbers and who the admins are.
function template_admin_support_info()
{
	global $txt, $context;

	// Show the user version information from their server.
	echo '
	<section>
		<we:title>
			', $txt['support_title'], '
		</we:title>
		<div id="version_details">
			<strong>', $txt['support_versions'], ':</strong><br>
			', $txt['support_versions_forum'], ':
			<em id="yourVersion">', $context['forum_version'], '</em><br>
			', $txt['support_versions_current'], ':
			<em id="wedgeVersion">??</em><br>
			', $context['can_admin'] ? '<a href="<URL>?action=admin;area=maintain;sa=routine;activity=version">' . $txt['version_check_more'] . '</a>' : '', '<br>';

	// Display all the members who can administrate the forum.
	echo '
			<br>
			<strong>', $txt['administrators'], ':</strong>
			', implode(', ', $context['administrators']);

	// If we have lots of admins... don't show them all.
	if (!empty($context['more_admins_link']))
		echo '
			(', $context['more_admins_link'], ')';

	echo '
		</div>
	</section>';
}

// Displays information about file versions installed, and compares them to current version.
function template_view_versions()
{
	global $context, $theme, $options, $txt;

	echo '
		<we:cat>
			', $txt['admin_version_check'], '
		</we:cat>
		<div class="information">', $txt['version_check_desc'], '</div>
		<we:cat>
			', $txt['support_title'], '
		</we:cat>
		<div class="windowbg wrc">
			<strong>', $txt['support_versions'], ':</strong><br>';

	// Display all the variables we have server information for, and the Wedge version itself.
	foreach ($context['current_versions'] as $list_key => $list)
	{
		echo '
			<div class="two-columns">';

		foreach ($list as $key => $version)
			if (empty($version))
				echo '
				<br>';
			else
				echo '
				', $version['title'], ':
				<em id="', $key, '">', $version['version'], '</em><br>';

		echo '
			</div>';
	}

	echo '
			<br class="clear">
		</div>';

	// And the JS to include all the version numbers, not to mention the actual information we need.
	add_js_file(array(
		'<URL>?action=viewremote;filename=current-version.js',
		'<URL>?action=viewremote;filename=detailed-version.js',
	), true);

	add_js('
	if (window.weVersion)
	{
		$("#wedgeVersion").html(window.weVersion);
		if ($("#yourVersion").text() != window.weVersion)
			$("#yourVersion").wrap(\'<span class="alert"></span>\');
	}');

	// And pass through the versions in case we want to make use of any of them.
	add_js('
	var weSupportVersions = {};

	weSupportVersions.forum = "', $context['forum_version'], '";');

	// If we're going to do this, we need to do all of it.
	$version_list = $context['current_versions']['left'] + $context['current_versions']['right'];
	foreach ($version_list as $variable => $version)
		if (!empty($version))
			add_js('
	weSupportVersions.', $variable, ' = "', $version['version'], '";');

	echo '
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg left">
						<th scope="col" class="first_th w50">
							<strong>', $txt['admin_wedgefile'], '</strong>
						</th>
						<th scope="col" class="w25">
							<strong>', $txt['dvc_your'], '</strong>
						</th>
						<th scope="col" class="last_th w25">
							<strong>', $txt['dvc_current'], '</strong>
						</th>
					</tr>
				</thead>
				<tbody>';

	// The current version of the core Wedge package.
	echo '
					<tr>
						<td class="windowbg">
							', $txt['admin_wedgepackage'], '
						</td>
						<td class="windowbg">
							<em id="yourWedge">', $context['forum_version'], '</em>
						</td>
						<td class="windowbg">
							<em id="currentWedge">??</em>
						</td>
					</tr>';

	// Now list all the source file versions, starting with the overall version (if all match!).
	echo '
					<tr>
						<td class="windowbg">
							<a href="#" id="Sources-link">', $txt['dvc_sources'], '</a>
						</td>
						<td class="windowbg">
							<em id="yourSources">??</em>
						</td>
						<td class="windowbg">
							<em id="currentSources">??</em>
						</td>
					</tr>
				</tbody>
			</table>

			<table id="Sources" class="table_grid w100 cs0">
			<tbody>';

	// Loop through every source file displaying its version - using javascript.
	foreach ($context['file_versions'] as $filename => $version)
		echo '
				<tr>
					<td class="windowbg2 w50" style="padding-left: 3ex">
						', $filename, '
					</td>
					<td class="windowbg2 w25">
						<em id="yourSources', $filename, '">', $version, '</em>
					</td>
					<td class="windowbg2 w25">
						<em id="currentSources', $filename, '">??</em>
					</td>
				</tr>';

	// Default template files.
	echo '
			</tbody>
			</table>

			<table class="table_grid w100 cs0">
				<tbody>
					<tr>
						<td class="windowbg w50">
							<a href="#" id="Default-link">', $txt['dvc_default'], '</a>
						</td>
						<td class="windowbg w25">
							<em id="yourDefault">??</em>
						</td>
						<td class="windowbg w25">
							<em id="currentDefault">??</em>
						</td>
					</tr>
				</tbody>
			</table>

			<table id="Default" class="table_grid w100 cs0">
				<tbody>';

	foreach ($context['default_template_versions'] as $filename => $version)
		echo '
					<tr>
						<td class="windowbg2 w50" style="padding-left: 3ex">
							', $filename, '
						</td>
						<td class="windowbg2 w25">
							<em id="yourDefault', $filename, '">', $version, '</em>
						</td>
						<td class="windowbg2 w25">
							<em id="currentDefault', $filename, '">??</em>
						</td>
					</tr>';

	// Now the language files...
	echo '
				</tbody>
			</table>

			<table class="table_grid w100 cs0">
				<tbody>
					<tr>
						<td class="windowbg w50">
							<a href="#" id="Languages-link">', $txt['dvc_languages'], '</a>
						</td>
						<td class="windowbg w25">
							<em id="yourLanguages">??</em>
						</td>
						<td class="windowbg w25">
							<em id="currentLanguages">??</em>
						</td>
					</tr>
				</tbody>
			</table>

			<table id="Languages" class="table_grid w100 cs0">
				<tbody>';

	foreach ($context['default_language_versions'] as $language => $files)
	{
		foreach ($files as $filename => $version)
			echo '
					<tr>
						<td class="windowbg2 w50" style="padding-left: 3ex">
							', $filename, '.<em>', $language, '</em>.php
						</td>
						<td class="windowbg2 w25">
							<em id="your', $filename, '.', $language, '">', $version, '</em>
						</td>
						<td class="windowbg2 w25">
							<em id="current', $filename, '.', $language, '">??</em>
						</td>
					</tr>';
	}

	echo '
				</tbody>
			</table>';

	// Finally, display the version information for the currently selected theme - if it is not the default one.
	if (!empty($context['template_versions']))
	{
		echo '
			<table class="table_grid w100 cs0">
				<tbody>
					<tr>
						<td class="windowbg w50">
							<a href="#" id="Templates-link">', $txt['dvc_templates'], '</a>
						</td>
						<td class="windowbg w25">
							<em id="yourTemplates">??</em>
						</td>
						<td class="windowbg w25">
							<em id="currentTemplates">??</em>
						</td>
					</tr>
				</tbody>
			</table>

			<table id="Templates" class="table_grid w100 cs0">
				<tbody>';

		foreach ($context['template_versions'] as $filename => $version)
			echo '
					<tr>
						<td class="windowbg2 w50" style="padding-left: 3ex">
							', $filename, '
						</td>
						<td class="windowbg2 w25">
							<em id="yourTemplates', $filename, '">', $version, '</em>
						</td>
						<td class="windowbg2 w25">
							<em id="currentTemplates', $filename, '">??</em>
						</td>
					</tr>';

		echo '
				</tbody>
			</table>';
	}

	/*
		Below is the hefty javascript for this. Upon opening the page it checks the current file versions with ones
		held at wedge.org and works out if they are up to date. If they aren't it colors that files number red.
		It also contains the function, swapOption, that toggles showing the detailed information for each of
		the file categories. (Sources, languages, and templates.)
	*/
	add_js_file('scripts/admin.js');

	add_js('
	var oViewVersions = new we_ViewVersions({
		aKnownLanguages: [
			\'.', implode('\',
			\'.', $context['default_known_languages']), '\'
		],
		oSectionContainerIds: {
			Sources: \'Sources\',
			Default: \'Default\',
			Languages: \'Languages\',
			Templates: \'Templates\'
		}
	});');
}

// Form for stopping people using naughty words, etc.
function template_edit_censored()
{
	global $context, $theme, $options, $txt, $settings;

	// First section is for adding/removing words from the censored list.
	echo '
		<form action="<URL>?action=admin;area=postsettings;sa=censor" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['admin_censored_words'], '
			</we:cat>
			<div class="windowbg2 wrc">
				<p>', $txt['admin_censored_where'], '</p>';

	// Show text boxes for censoring [bad   ] => [good  ].
	foreach ($context['censored_words'] as $vulgar => $proper)
		echo '
				<div style="margin-top: 1ex"><input name="censor_vulgar[]" value="', $vulgar, '" size="20"> =&gt; <input name="censor_proper[]" value="', $proper, '" size="20"></div>';

	// Now provide a way to censor more words.
	echo '
				<div style="margin-top: 1ex"><input name="censor_vulgar[]" size="20"> =&gt; <input name="censor_proper[]" size="20"></div>
				<div id="moreCensoredWords"></div><div style="margin-top: 1ex; display: none" id="moreCensoredWords_link"><a href="#" onclick="addNewWord(); return false;">', $txt['censor_clickadd'], '</a></div>';

	add_js('
	$("#moreCensoredWords_link").show();

	function addNewWord()
	{
		$("#moreCensoredWords").append(\'<div style="margin-top: 1ex"><input name="censor_vulgar[]" size="20"> =&gt; <input name="censor_proper[]" size="20"><\' + \'/div>\');
	}');

	echo '
				<hr>
				<dl class="settings">
					<dt>
						<strong><label for="censorWholeWord_check">', $txt['censor_whole_words'], ':</label></strong>
					</dt>
					<dd>
						<input type="checkbox" name="censorWholeWord" value="1" id="censorWholeWord_check"', empty($settings['censorWholeWord']) ? '' : ' checked', '>
					</dd>
					<dt>
						<strong><label for="censorIgnoreCase_check">', $txt['censor_case'], ':</label></strong>
					</dt>
					<dd>
						<input type="checkbox" name="censorIgnoreCase" value="1" id="censorIgnoreCase_check"', empty($settings['censorIgnoreCase']) ? '' : ' checked', '>
					</dd>
					<dt>
						<label for="censorAllowDisable_check">', $txt['allow_no_censored'], ':</label>
					</dt>
					<dd>
						<input type="checkbox" name="allow_no_censored" value="1" id="censorAllowDisable_check"', empty($settings['allow_no_censored']) ? '' : ' checked', '>
					</dd>
				</dl>
				<input type="submit" name="save_censor" value="', $txt['save'], '" class="save">
			</div>';

	// This table lets you test out your filters by typing in rude words and seeing what comes out.
	echo '
			<we:cat>
				', $txt['censor_test'], '
			</we:cat>
			<div class="windowbg wrc">
				<p class="center">
					<input name="censortest" value="', empty($context['censor_test']) ? '' : $context['censor_test'], '">
					<input type="submit" value="', $txt['censor_test_save'], '" class="submit">
				</p>
			</div>

			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
}

// Maintenance is a lovely thing, isn't it?
function template_not_done()
{
	global $context, $theme, $options, $txt;

	echo '
		<we:cat>
			', $txt['not_done_title'], '
		</we:cat>
		<div class="windowbg wrc">
			', $txt['not_done_reason'];

	if (!empty($context['continue_percent']))
		echo '
			<div style="padding-left: 20%; padding-right: 20%; margin-top: 1ex">
				<div style="font-size: 8pt; height: 12pt; border: 1px solid black; background-color: white; padding: 1px; position: relative">
					<div style="padding-top: ', we::is('webkit') ? '2pt' : '1pt', '; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold">', $context['continue_percent'], '%</div>
					<div style="width: ', $context['continue_percent'], '%; height: 12pt; z-index: 1; background-color: red">&nbsp;</div>
				</div>
			</div>';

	if (!empty($context['substep_enabled']))
		echo '
			<div style="padding-left: 20%; padding-right: 20%; margin-top: 1ex">
				<span class="smalltext">', $context['substep_title'], '</span>
				<div style="font-size: 8pt; height: 12pt; border: 1px solid black; background-color: white; padding: 1px; position: relative">
					<div style="padding-top: ', we::is('webkit') ? '2pt' : '1pt', '; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold">', $context['substep_continue_percent'], '%</div>
					<div style="width: ', $context['substep_continue_percent'], '%; height: 12pt; z-index: 1; background-color: blue">&nbsp;</div>
				</div>
			</div>';

	echo '
			<form action="<URL>', $context['continue_get_data'], '" method="post" accept-charset="UTF-8" style="margin: 0" name="autoSubmit" id="autoSubmit">
				<div style="margin: 1ex; text-align: right"><input type="submit" name="cont" value="', westr::htmlspecialchars($txt['not_done_continue']), '"></div>
				', $context['continue_post_data'], '
			</form>
		</div>';

	add_js_inline('
	var countdown = ', $context['continue_countdown'], ';
	doAutoSubmit();

	function doAutoSubmit()
	{
		if (countdown == 0)
			document.forms.autoSubmit.submit();
		else if (countdown == -1)
			return;

		document.forms.autoSubmit.cont.value = ', JavaScriptEscape($txt['not_done_continue']), ' + " (" + countdown + ")";
		countdown--;

		setTimeout(doAutoSubmit, 1000);
	}');
}

// Template for showing settings (of any kind, really!)
function template_show_settings()
{
	global $context, $txt, $theme;

	if ($context['was_saved'])
		echo '
		<div class="windowbg" id="profile_success">
			', $txt['changes_saved'], '
		</div>';

	echo '
		<form action="', $context['post_url'], '" method="post" accept-charset="UTF-8"', !empty($context['force_form_onsubmit']) ? ' onsubmit="' . $context['force_form_onsubmit'] . '"' : '', '>';

	// Is there a custom title?
	if (isset($context['settings_title']))
		echo '
			<we:cat>
				', $context['settings_title'], '
			</we:cat>';

	// Have we got some custom code to insert?
	if (!empty($context['settings_message']))
		echo '
			<div class="information">
				', $context['settings_message'], '
			</div>';

	// Now actually loop through all the variables.
	$is_open = false;
	foreach ($context['config_vars'] as $config_var)
	{
		// Is it a title or a description?
		if (is_array($config_var) && ($config_var['type'] == 'title' || $config_var['type'] == 'desc'))
		{
			// Not a list yet?
			if ($is_open)
			{
				$is_open = false;
				echo '
				</dl>
			</div>';
			}

			// A title?
			if ($config_var['type'] == 'title')
				echo '

			<we:cat>
				<div', !empty($config_var['class']) ? ' class="' . $config_var['class'] . '"' : '', !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '', '>', $config_var['help'] ? '
					<a href="<URL>?action=help;in=' . $config_var['help'] . '" onclick="return reqWin(this);" class="help" title="' . $txt['help'] . '"></a>' : '', '
					', $config_var['label'], '
				</div>
			</we:cat>';

			// A description?
			else
				echo '
			<p class="description">
				', $config_var['label'], '
			</p>';

			continue;
		}

		// Not a list yet?
		if (!$is_open)
		{
			$is_open = true;
			echo '
			<div class="windowbg2 wrc">
				<dl class="settings">';
		}

		// Hang about? Are you pulling my leg - a callback?!
		if (is_array($config_var) && $config_var['type'] == 'callback')
		{
			if (function_exists('template_callback_' . $config_var['name']))
				call_user_func('template_callback_' . $config_var['name']);

			continue;
		}

		if (is_array($config_var))
		{
			// First off, is this a span like a message?
			if (in_array($config_var['type'], array('message', 'warning')))
			{
				echo '
					<dd', $config_var['type'] == 'warning' ? ' class="alert"' : '', !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : '', '>
						', $config_var['label'], '
					</dd>';
			}
			// Otherwise it's an input box of some kind.
			else
			{
				echo '
					<dt', is_array($config_var) && !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '', '>';

				// Some quick helpers...
				$javascript = $config_var['javascript'];
				$disabled = !empty($config_var['disabled']) ? ' disabled' : '';
				$subtext = !empty($config_var['subtext']) ? '<dfn> ' . $config_var['subtext'] . '</dfn>' : '';

				// Show the [?] button.
				if ($config_var['help'])
					echo '
						<label for="', $config_var['name'], '"><a id="setting_', $config_var['name'], '" href="<URL>?action=help;in=', $config_var['help'], '" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
							<span id="span_', $config_var['name'], '"', $config_var['disabled'] ? ' class="disabled"' : ($config_var['invalid'] ? ' class="error"' : ''), '>', $config_var['label'], $subtext, $config_var['type'] == 'password' ? '<br><em>' . $txt['admin_confirm_password'] . '</em>' : '', '</span>
						</label>
					</dt>';
				else
					echo '
						<label for="', $config_var['name'], '"><a id="setting_', $config_var['name'], '"></a> <span id="span_', $config_var['name'], '"', $config_var['disabled'] ? ' class="disabled"' : ($config_var['invalid'] ? ' class="error"' : ''), '>', $config_var['label'], $subtext, $config_var['type'] == 'password' ? '<br><em>' . $txt['admin_confirm_password'] . '</em>' : '', '</span></label>
					</dt>';

				echo '
					<dd', !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : '', '>', $config_var['preinput'];

				// Show a check box.
				if ($config_var['type'] == 'check')
					echo '
						<input type="checkbox"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '"', $config_var['value'] ? ' checked' : '', ' value="1">';
				// A yesno is a spiffier type of option that is slightly nicer than a checkbox even if it does the same thing.
				elseif ($config_var['type'] == 'yesno')
					echo '
						<select name="', $config_var['name'], '" id="', $config_var['name'], '"', $javascript, $disabled, '>
							<option value="1"', !empty($config_var['value']) ? ' selected' : '', ' style="color: green">', $txt['yes'], '</option>
							<option value="0"', empty($config_var['value']) ? ' selected' : '', ' style="color: red">', $txt['no'], '</option>
						</select>';
				// Escape (via htmlspecialchars.) the text box.
				elseif ($config_var['type'] == 'password')
					echo '
						<input type="password"', $disabled, $javascript, ' name="', $config_var['name'], '[0]"', $config_var['size'] ? ' size="' . $config_var['size'] . '"' : '', ' value="*#fakepass#*" onfocus="this.value = \'\'; this.form.', $config_var['name'], '.disabled = false;"><br>
						<input type="password" disabled id="', $config_var['name'], '" name="', $config_var['name'], '[1]"', $config_var['size'] ? ' size="' . $config_var['size'] . '"' : '', '>';
				// Show a selection box.
				elseif ($config_var['type'] == 'select')
				{
					echo '
						<select name="', $config_var['name'], '" id="', $config_var['name'], '"', $javascript, $disabled, '>';
					foreach ($config_var['data'] as $option)
						if (isset($option[2])) // optgroup?
						{
							if (isset($has_opened_optgroup))
								echo '
							</optgroup>';
							echo '
							<optgroup label="', $option[2], '">';
						}
						else
							echo '
							<option value="', $option[0], '"', $option[0] == $config_var['value'] ? ' selected' : '', '>', $option[1], '</option>';
					if (isset($has_opened_optgroup))
						echo '
							</optgroup>';
					echo '
						</select>';
				}
				elseif ($config_var['type'] == 'multi_select')
				{
					echo '
						<fieldset id="fs_', $config_var['name'], '">
							<legend><div class="foldable fold"></div> <a href="#" onclick="$(\'#fs_', $config_var['name'], '\').hide(); $(\'#fs_', $config_var['name'], '_link\').show(); return false;">', $txt['select_from_list'], '</a></legend>
							<ul class="permission_groups">';
					foreach ($config_var['data'] as $option)
						echo '
								<li>
									<input type="checkbox" name="', $config_var['name'], '[', $option[0], ']" value="on"', isset($config_var['value']) && in_array($option[0], $config_var['value']) ? ' checked' : '', '>
									', $option[1], '
								</li>';
					echo '
							</ul>
						</fieldset>
						<div id="fs_', $config_var['name'], '_link" class="hide"><div class="foldable"></div> <a href="#" onclick="$(\'#fs_', $config_var['name'], '\').show(); $(\'#fs_', $config_var['name'], '_link\').hide(); return false;">', $txt['click_to_see_more'], '</a></div>';

					add_js('$("#fs_', $config_var['name'], '").hide(); $("#fs_', $config_var['name'], '_link").show();');
				}
				// Text area?
				elseif ($config_var['type'] == 'large_text')
					echo '
						<textarea rows="', $config_var['size'] ? $config_var['size'] : 4, '" cols="30"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '">', $config_var['value'], '</textarea>';
				// Email address?
				elseif ($config_var['type'] == 'email')
					echo '
						<input type="email"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '"', $config_var['size'] ? ' size="' . $config_var['size'] . '"' : '', '>';
				// Permission group?
				elseif ($config_var['type'] == 'permissions')
					theme_inline_permissions($config_var['name']);
				elseif ($config_var['type'] == 'boards')
				{
					echo '
						<fieldset id="fs_', $config_var['name'], '">
							<legend><a href="#" onclick="$(\'#fs_', $config_var['name'], '\').hide(); $(\'#fs_', $config_var['name'], '_link\').show(); return false;">', $txt['select_from_list'], '</a></legend>';
					foreach ($context['board_listing'] as $cat_id => $cat)
					{
						echo '
							<label><strong>', $cat['name'], '</strong> <input type="checkbox" id="catsel', $cat_id, '" onclick="selectcat(', $cat_id, ');"></label>
							<ul class="permission_groups">';

						foreach ($cat['boards'] as $id_board => $board)
							echo '
								<li>&nbsp; ', $board[0] > 0 ? str_repeat('&nbsp; &nbsp; ', $board[0]) : '', '<label><input type="checkbox" class="cat', $cat_id, '" name="', $config_var['name'], '[', $id_board, ']" value="on"', !empty($config_var['value']) && in_array($id_board, $config_var['value']) ? ' checked' : '', '> ', $board[1], '</label></li>';

						echo '
							</ul>';
					}
					add_js('
	function selectcat(id)
	{
		$(".cat" + id).prop("checked", $("#catsel" + id).prop("checked"));
	};');

					echo '
						</fieldset>
						<a href="#" onclick="$(\'#fs_', $config_var['name'], '\').show(); $(\'#fs_', $config_var['name'], '_link\').hide(); return false;" id="fs_', $config_var['name'], '_link" class="hide">[ ', $txt['click_to_see_more'], ' ]</a>';

					add_js('$("#fs_', $config_var['name'], '").hide(); $("#fs_', $config_var['name'], '_link").show();');

				}
				// BBC selection?
				elseif ($config_var['type'] == 'bbc')
				{
					echo '
						<fieldset id="', $config_var['name'], '">
							<legend>', $txt['bbcTagsToUse_select'], '</legend>
							<ul class="reset">';

					foreach ($context['bbc_columns'] as $bbcColumn)
					{
						foreach ($bbcColumn as $bbcTag)
							echo '
								<li class="list_bbc floatleft">
									<label><input type="checkbox" name="', $config_var['name'], '_enabledTags[]" id="tag_', $config_var['name'], '_', $bbcTag['tag'], '" value="', $bbcTag['tag'], '"', !in_array($bbcTag['tag'], $context['bbc_sections'][$config_var['name']]['disabled']) ? ' checked' : '', '> ', $bbcTag['tag'], '</label>', $bbcTag['show_help'] ? ' (<a href="<URL>?action=help;in=tag_' . $bbcTag['tag'] . '" onclick="return reqWin(this);">?</a>)' : '', '
								</li>';
					}
					echo '
							</ul>
							<label><input type="checkbox" onclick="invertAll(this, this.form, \'', $config_var['name'], '_enabledTags\');"', $context['bbc_sections'][$config_var['name']]['all_selected'] ? ' checked' : '', '> <em>', $txt['bbcTagsToUse_select_all'], '</em></label>
						</fieldset>';
				}
				// A simple message?
				elseif ($config_var['type'] == 'var_message')
					echo '
						<div', !empty($config_var['name']) ? ' id="' . $config_var['name'] . '"' : '', '>', $config_var['var_message'], '</div>';
				// Numeric (int)?
				elseif ($config_var['type'] == 'int')
					echo '
						<input type="number"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '"', $config_var['size'] ? ' size="' . $config_var['size'] . '"' : '', ' min="', isset($config_var['min']) ? $config_var['min'] : 0, '"', isset($config_var['max']) ? ' max="' . $config_var['max'] . '"' : '', ' step="', !empty($config_var['step']) ? $config_var['step'] : 1, '">';
				// Percentage?
				elseif ($config_var['type'] == 'percent')
				{
					// !!! This is a thinly ported version of the Profile/Warnings one. If you fix this up for jQuery please do the profile one too.
					echo '
						<div id="', $config_var['name'], '_div1" class="hide">
							<div class="percent">
								<span class="floatleft"><a href="#" onclick="changeLevel(\'', $config_var['name'], '\', -5); return false;" onmousedown="return false;">[-]</a></span>
								<div class="floatleft container" id="', $config_var['name'], '_contain" style="width: 200px">
									<div id="', $config_var['name'], '_text" class="text" onmousedown="e.preventDefault();">', $config_var['value'], '%</div>
									<div id="', $config_var['name'], '_progress" class="progress" style="width: ', $config_var['value'], '%">&nbsp;</div>
								</div>
								<span class="floatleft"><a href="#" onclick="changeLevel(\'', $config_var['name'], '\', 5); return false;" onmousedown="return false;">[+]</a></span>
							</div>
							<input type="hidden" name="', $config_var['name'], '" id="', $config_var['name'], '_level" value="SAME">
						</div>
						<div id="', $config_var['name'], '_div2">
							<input type="number" name="', $config_var['name'], '_nojs" id="', $config_var['name'], '_nojs" size="6" maxlength="4" min="0" max="100" value="', $config_var['value'], '">
						</div>';

					if (empty($context['already_showing_percent']))
					{
						$context['already_showing_percent'] = true;
						add_js('
	var isMoving;
	function setBarPos(item, e, changeAmount)
	{
		var
			barWidth = 200, mouse = e.pageX,
			percent, size, color = "", effectText = "";

		// Are we passing the amount to change it by?
		if (changeAmount)
			percent = $("#" + item + "_level").val() == "SAME" ?
				parseInt($("#" + item + "_nojs").val(), 10) + changeAmount :
				parseInt($("#" + item + "_level").val(), 10) + changeAmount;
		// If not then it\'s a mouse thing.
		else
		{
			if (e.type == "mousedown" && e.which == 1)
				isMoving = true;
			if (e.type == "mouseup")
				isMoving = false;
			if (!isMoving)
				return false;

			// Get the position of the container.
			var position = $("#" + item + "_contain").offset().left;
			percent = Math.round(Math.round(((mouse - position) / barWidth) * 100) / 5) * 5;
		}

		percent = Math.min(Math.max(percent, 0), 100);
		size = barWidth * (percent/100);
		var newpc = { width: size + "px" };
		if (color != "")
			newpc.backgroundColor = color;

		$("#" + item + "_progress").css(newpc);
		$("#" + item + "_text").css("color", percent < 50 ? "black" : (percent < 60 ? (color == "green" ? "#ccc" : "black") : "white")).html(percent + "%");
		$("#" + item + "_level").val(percent);
		$("#cur_level_div").html(effectText);
	};

	function changeLevel(item, amount)
	{
		setBarPos(item, false, amount);
	}');
					}

					// This is very nasty but I can't immediately think of a better way of doing it.
					add_js('
	$("#', $config_var['name'], '_contain").on("mousedown mousemove mouseup", setBarPos_', $config_var['name'], ').mouseleave(function () { isMoving = false; });
	$("#', $config_var['name'], '_div1").show();
	$("#', $config_var['name'], '_div2").hide();
	function setBarPos_', $config_var['name'], '(e)
	{
		setBarPos("', $config_var['name'], '", e, false);
	};');
				}
				// Assume it must be a text box.
				else
					echo '
						<input', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '"', $config_var['size'] ? ' size="' . $config_var['size'] . '"' : '', '>';

				echo !empty($config_var['postinput']) ? '
						' . $config_var['postinput'] : '', '
					</dd>';
			}
		}
		else
		{
			// Just show a separator.
			if ($config_var == '')
				echo '
				</dl>
				<hr>
				<dl class="settings">';
			else
				echo '
					<dd>
						<strong>' . $config_var . '</strong>
					</dd>';
		}
	}

	if ($is_open)
		echo '
				</dl>';

	if (empty($context['settings_save_dont_show']))
		echo '
				<hr>
				<div class="right">
					<input type="submit" value="', $txt['save'], '"', !empty($context['save_disabled']) ? ' disabled' : '', !empty($context['settings_save_onclick']) ? ' onclick="' . $context['settings_save_onclick'] . '"' : '', ' class="submit">
				</div>';

	if ($is_open)
		echo '
			</div>';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
}

// For showing PHP info
function template_phpinfo()
{
	global $context;

	if (!empty($context['phpinfo_version']))
	{
		echo '
	<div class="windowbg wrc">';

		if (!empty($context['php_header_icons']))
			echo '
			<div class="floatright">', implode(' ', $context['php_header_icons']), '</div>';

		echo '
		', $context['phpinfo_version'], '
		<br class="clear">
	</div>';
	}

	$columns = array();
	$length = ceil(count($context['toc']) / 2);
	$count = 0;

	$in_column = false;

	foreach ($context['toc'] as $k => $v)
	{
		if (!$in_column)
		{
			echo '
	<div class="two-columns">
		<ul>';
			$in_column = true;
		}

		echo '
		<li><a href="#', $k, '">', $v, '</a></li>';

		$count++;
		if ($count >= $length)
		{
			$count = 0;
			$in_column = false;
			echo '
		</ul>
	</div>';
		}
	}

	if ($in_column)
		echo '
		</ul>
	</div>';
	echo $context['phpinfo'];
}

// Template for showing custom profile fields.
function template_show_custom_profile()
{
	global $context, $txt, $theme;

	// Standard fields.
	template_show_list('standard_profile_fields');

	// Custom fields.
	echo '
		<we:title>', $txt['custom_profile_title'], '</we:title>
		<form action="<URL>?action=admin;area=memberoptions;sa=profileedit" method="post">';

	if (empty($context['custom_fields']))
		echo '
			<div class="information">', $txt['custom_profile_none'], '</div>';
	else
	{
		echo '
			<ul id="sortable">';

		foreach ($context['custom_fields'] as $id => $field)
		{
			echo '
				<li class="windowbg">
					<span class="handle"></span>
					<div class="floatright">
						<input type="submit" name="modify[', $id, ']" value="', $txt['modify'], '" class="submit">
						<input type="hidden" name="order[]" value="', $id, '">
					</div>
					<span class="sortme">', $field['field_name'], '</span>
					<span class="badge">', $field['field_type_formatted'], '</span>
					<div class="badge"><span class="cf_', $field['active_type'], '"><div class="icon"></div> ', $txt['custom_profile_' . $field['active_type']], '</div>
					<div class="floatleft">', sprintf($txt['custom_profile_placement'], $field['placement_text']), '</div>
					<br class="clear">
				</li>';
		}

		echo '
			</ul>';
	}

	echo '
			<br class="clear">
			<div class="right">
				<input type="submit" name="add" value="', $txt['custom_profile_make_new'], '" class="new">
				<input type="submit" name="saveorder" value="', $txt['editnews_saveorder'], '" class="save" id="saveorder">
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';

	// !! template_show_list('custom_profile_fields'); ??

	add_js('
	var iNumChecks = document.forms.standardProfileFields.length;
	for (var i = 0; i < iNumChecks; i++)
		if (document.forms.standardProfileFields[i].id.indexOf(\'reg_\') == 0)
			document.forms.standardProfileFields[i].disabled = document.forms.standardProfileFields[i].disabled || !document.getElementById(\'active_\' + document.forms.standardProfileFields[i].id.slice(4)).checked;

	$(\'#sortable\').sortable({ handle: \'.handle\', update: function (event, ui) { $(\'#saveorder\').show(); } });
	$(\'#sortable\').disableSelection();
	$(\'#saveorder\').hide();');
}

// Edit a profile field?
function template_edit_profile_field()
{
	global $context, $txt, $theme;

	// All the javascript for this page - quite a bit!
	add_js('
	function updateInputBoxes()
	{
		var curType = $("#field_type").val(), privStatus = $("#private").val();
		$("#max_length_dt, #max_length_dd, #bbc_dt, #bbc_dd, #can_search_dt, #can_search_dd").toggle(curType == "text" || curType == "textarea");
		$("#dimension_dt, #dimension_dd").toggle(curType == "textarea");
		$("#options_dt, #options_dd").toggle(curType == "select" || curType == "radio");
		$("#default_dt, #default_dd").toggle(curType == "check");
		$("#mask_dt, #mask_dd").toggle(curType == "text");
		$("#regex_div").toggle(curType == "text" && $("#mask").val() == "regex");
		$("#display").prop("disabled", false);

		// Cannot show this on the topic
		if (curType == "textarea" || privStatus >= 2)
			$("#display").prop({ checked: false, disabled: true });

		// Able to show to guests?
		$("#guest_access_dt, #guest_access_dd").toggle(privStatus < 2);
	}
	updateInputBoxes();');

	add_js('
	var startOptID = ', count($context['field']['options']), ';
	function addOption()
	{
		$("#addopt").append(\'<br><input type="radio" name="default_select" value="\' + startOptID + \'" id="\' + startOptID + \'"><input name="select_option[\' + startOptID + \']" value="">\');
		startOptID++;
	}');

	echo '
		<form action="<URL>?action=admin;area=memberoptions;sa=profileedit;fid=', $context['fid'], ';', $context['session_query'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $context['page_title'], '
			</we:cat>
			<div class="windowbg2 wrc">';

	// If this is a new field, display the templates. (If it's not, leave them hidden to prevent overwriting accidentally with a template.)
	if (empty($context['fid']))
	{
		echo '
				<fieldset>
					<legend>', $txt['custom_edit_templates'], '</legend>
					', $txt['custom_edit_templates_desc'], '
					<br><br>
					<dl class="settings">
						<dt>
							<strong>', $txt['custom_edit_a_template'], ':</strong>
						</dt>
						<dd>
							<select id="field_template" onchange="insertTemplate();">
								<option value="" selected>', $txt['custom_edit_templates_select'], '</option>';

		foreach ($context['template_fields'] as $field_group => $fields)
		{
			echo '
								<optgroup label="', $txt['custom_edit_tplgrp_' . $field_group], '">';

			foreach ($fields as $field_id => $field)
				echo '
									<option value="', $field_id, '">', $field['field_name'], '</option>';

			echo '
								</optgroup>';
		}

		echo '
							</select>
						</dd>
					</dl>
				</fieldset>';

		add_js('
	function insertTemplate()
	{
		var
			field = $("#field_template").val();
		if (field == "" || !insertTemplate.templates[field])
			return;

		for (i in insertTemplate.templates[field])
		{
			switch (i)
			{
				case "display":	// these are checkboxes
				case "bbc":
					$("input[name=\"" + i + "\"]").prop("checked", insertTemplate.templates[field][i]);
					break;
				case "field_desc": // these are textareas
				case "enclose":
					$("textarea[name=\"" + i + "\"]").html(insertTemplate.templates[field][i]);
					break;
				default: // everything else (which should be input[type="text"] or select and thus support .val()
					$("[name=\"" + i + "\"]").val(insertTemplate.templates[field][i]).sb();
					break;
			}
		}

		updateInputBoxes();
	};');

		// Before we output the template fields, we need to reform the array slightly for the JS's benefit.
		$fields = array();
		foreach ($context['template_fields'] as $field_list)
			$fields = array_merge($fields, $field_list);

		add_js ('
	insertTemplate.templates = ', we_json_encode($fields), ';');
	}

	echo '
				<fieldset>
					<legend>', $txt['custom_edit_general'], '</legend>

					<dl class="settings">
						<dt>
							<strong>', $txt['custom_edit_name'], ':</strong>
						</dt>
						<dd>
							<input name="field_name" value="', $context['field']['name'], '" size="20" maxlength="40">
						</dd>
						<dt>
							<strong>', $txt['custom_edit_desc'], ':</strong>
						</dt>
						<dd>
							<textarea name="field_desc" rows="3" cols="40">', $context['field']['desc'], '</textarea>
						</dd>
						<dt>
							<strong>', $txt['custom_edit_profile'], ':</strong>
							<dfn>', $txt['custom_edit_profile_desc'], '</dfn>
						</dt>
						<dd>
							<select name="profile_area">
								<option value="none"', $context['field']['profile_area'] == 'none' ? ' selected' : '', '>', $txt['custom_edit_profile_none'], '</option>
								<option value="account"', $context['field']['profile_area'] == 'account' ? ' selected' : '', '>', $txt['account'], '</option>
								<option value="forumprofile"', $context['field']['profile_area'] == 'forumprofile' ? ' selected' : '', '>', $txt['forumprofile'], '</option>
								<option value="options"', $context['field']['profile_area'] == 'options' ? ' selected' : '', '>', $txt['options'], '</option>
							</select>
						</dd>
						<dt>
							<strong>', $txt['custom_edit_registration'], ':</strong>
						</dt>
						<dd>
							<select name="reg" id="reg">
								<option value="0"', $context['field']['reg'] == 0 ? ' selected' : '', '>', $txt['custom_edit_registration_disable'], '</option>
								<option value="1"', $context['field']['reg'] == 1 ? ' selected' : '', '>', $txt['custom_edit_registration_allow'], '</option>
								<option value="2"', $context['field']['reg'] == 2 ? ' selected' : '', '>', $txt['custom_edit_registration_require'], '</option>
							</select>
						</dd>
						<dt>
							<strong>', $txt['custom_edit_mlist'], ':</strong>
						</dt>
						<dd>
							<input type="checkbox" name="mlist" id="mlist"', $context['field']['mlist'] ? ' checked' : '', '>
						</dd>
						<dt>
							<strong>', $txt['custom_edit_display'], ':</strong>
						</dt>
						<dd>
							<input type="checkbox" name="display" id="display"', $context['field']['display'] ? ' checked' : '', '>
						</dd>
						<dt>
							<strong>', $txt['custom_edit_placement'], ':</strong>
						</dt>
						<dd>
							<select name="placement" id="placement">
								<option value="0"', $context['field']['placement'] == '0' ? ' selected' : '', '>', $txt['custom_edit_placement_standard'], '</option>
								<option value="1"', $context['field']['placement'] == '1' ? ' selected' : '', '>', $txt['custom_edit_placement_withicons'], '</option>
								<option value="2"', $context['field']['placement'] == '2' ? ' selected' : '', '>', $txt['custom_edit_placement_abovesignature'], '</option>
							</select>
						</dd>
						<dt>
							<a id="field_show_enclosed" href="<URL>?action=help;in=field_show_enclosed" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
							<strong>', $txt['custom_edit_enclose'], ':</strong>
							<dfn>', $txt['custom_edit_enclose_desc'], '</dfn>
						</dt>
						<dd>
							<textarea name="enclose" rows="10" cols="50">', @$context['field']['enclose'], '</textarea>
						</dd>
					</dl>
				</fieldset>
				<fieldset>
					<legend>', $txt['custom_edit_input'], '</legend>
					<dl class="settings">
						<dt>
							<strong>', $txt['custom_edit_picktype'], ':</strong>
						</dt>
						<dd>
							<select name="field_type" id="field_type" onchange="updateInputBoxes();">';

	$field_types = array('text', 'textarea', 'select', 'radio', 'check');
	foreach ($field_types as $type)
		echo '
								<option value="', $type, '"', $context['field']['type'] == $type ? ' selected' : '', '>&lt;div class="cf_items cf_', $type, '"&gt;&lt;/div&gt; ', $txt['custom_profile_type_' . $type] . '</option>';

	echo '
							</select>
						</dd>
						<dt id="max_length_dt">
							<strong>', $txt['custom_edit_max_length'], ':</strong>
							<dfn>', $txt['custom_edit_max_length_desc'], '</dfn>
						</dt>
						<dd id="max_length_dd">
							<input name="max_length" value="', $context['field']['max_length'], '" size="7" maxlength="6">
						</dd>
						<dt id="dimension_dt">
							<strong>', $txt['custom_edit_dimension'], ':</strong>
						</dt>
						<dd id="dimension_dd">
							<strong>', $txt['custom_edit_dimension_row'], ':</strong> <input name="rows" value="', $context['field']['rows'], '" size="5" maxlength="3">
							<strong>', $txt['custom_edit_dimension_col'], ':</strong> <input name="cols" value="', $context['field']['cols'], '" size="5" maxlength="3">
						</dd>
						<dt id="bbc_dt">
							<strong>', $txt['custom_edit_bbc'], '</strong>
						</dt>
						<dd id="bbc_dd">
							<input type="checkbox" name="bbc"', $context['field']['bbc'] ? ' checked' : '', '>
						</dd>
						<dt id="options_dt">
							<a href="<URL>?action=help;in=customoptions" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
							<strong>', $txt['custom_edit_options'], ':</strong>
							<dfn>', $txt['custom_edit_options_desc'], '</dfn>
						</dt>
						<dd id="options_dd">
							<div>';

	foreach ($context['field']['options'] as $k => $option)
		echo '
								', $k == 0 ? '' : '<br>', '<input type="radio" name="default_select" value="', $k, '"', $context['field']['default_select'] == $option ? ' checked' : '', '><input name="select_option[', $k, ']" value="', $option, '">';

	echo '
								<span id="addopt"></span>
								<input type="submit" onclick="addOption(); return false;" class="new" value="', $txt['custom_edit_options_more'], '">
							</div>
						</dd>
						<dt id="default_dt">
							<strong>', $txt['custom_edit_default'], ':</strong>
						</dt>
						<dd id="default_dd">
							<input type="checkbox" name="default_check"', $context['field']['default_check'] ? ' checked' : '', '>
						</dd>
					</dl>
				</fieldset>
				<fieldset>
					<legend>', $txt['custom_edit_advanced'], '</legend>
					<dl class="settings">
						<dt id="mask_dt">
							<a id="custom_mask" href="<URL>?action=help;in=custom_mask" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
							<strong>', $txt['custom_edit_mask'], ':</strong>
							<dfn>', $txt['custom_edit_mask_desc'], '</dfn>
						</dt>
						<dd id="mask_dd">
							<select name="mask" id="mask" onchange="updateInputBoxes();">
								<option value="nohtml"', $context['field']['mask'] == 'nohtml' ? ' selected' : '', '>', $txt['custom_edit_mask_nohtml'], '</option>
								<option value="email"', $context['field']['mask'] == 'email' ? ' selected' : '', '>', $txt['custom_edit_mask_email'], '</option>
								<option value="number"', $context['field']['mask'] == 'number' ? ' selected' : '', '>', $txt['custom_edit_mask_number'], '</option>
								<option value="regex"', substr($context['field']['mask'], 0, 5) == 'regex' ? ' selected' : '', '>', $txt['custom_edit_mask_regex'], '</option>
							</select>
							<br>
							<input name="regex" id="regex_div" value="', $context['field']['regex'], '" size="30">
						</dd>
						<dt>
							<strong>', $txt['custom_edit_privacy'], ':</strong>
							<dfn>', $txt['custom_edit_privacy_desc'], '</dfn>
						</dt>
						<dd>
							<select name="private" id="private" onchange="updateInputBoxes();" style="width: 100%">
								<option value="0"', $context['field']['private'] == 0 ? ' selected' : '', '>', $txt['custom_edit_privacy_all'], '</option>
								<option value="1"', $context['field']['private'] == 1 ? ' selected' : '', '>', $txt['custom_edit_privacy_see'], '</option>
								<option value="2"', $context['field']['private'] == 2 ? ' selected' : '', '>', $txt['custom_edit_privacy_owner'], '</option>
								<option value="3"', $context['field']['private'] == 3 ? ' selected' : '', '>', $txt['custom_edit_privacy_none'], '</option>
							</select>
						</dd>
						<dt id="guest_access_dt">
							<strong>', $txt['custom_edit_guest_access'], ':</strong>
							<dfn>', $txt['custom_edit_guest_access_desc'], '</dfn>
						</dt>
						<dd id="guest_access_dd">
							<input type="checkbox" name="guest_access"', $context['field']['guest_access'] ? ' checked' : '', '>
						</dd>
						<dt id="can_search_dt">
							<strong>', $txt['custom_edit_can_search'], ':</strong>
							<dfn>', $txt['custom_edit_can_search_desc'], '</dfn>
						</dt>
						<dd id="can_search_dd">
							<input type="checkbox" name="can_search"', $context['field']['can_search'] ? ' checked' : '', '>
						</dd>
						<dt>
							<strong>', $txt['custom_edit_active'], ':</strong>
							<dfn>', $txt['custom_edit_active_desc'], '</dfn>
						</dt>
						<dd>
							<input type="checkbox" name="active"', $context['field']['active'] ? ' checked' : '', '>
						</dd>
					</dl>
				</fieldset>
				<div class="right">
					<input type="submit" name="save" value="', $txt['save'], '" class="submit">';

	if ($context['fid'])
		echo '
					<input type="submit" name="delete" value="', $txt['delete'], '" onclick="return ask(', JavaScriptEscape($txt['custom_edit_delete_sure']), ', e);" class="delete">';

	echo '
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
}

// Results page for an admin search.
function template_admin_search_results()
{
	global $context, $txt, $theme, $options;

	echo '
	<we:cat>
		<div class="floatright">
			<form action="<URL>?action=admin;area=search" method="post" accept-charset="UTF-8" style="font-weight: normal; display: inline" id="quick_search">
				<input type="search" name="search_term" value="', $context['search_term'], '" class="search">
				<input type="hidden" name="search_type" value="', $context['search_type'], '">
				<input type="submit" name="search_go" value="', $txt['admin_search_results_again'], '">
			</form>
		</div>
		', $txt['admin_search_results'], '
	</we:cat>

	<div class="windowbg wrc">
		', sprintf($txt['admin_search_results_desc'], $context['search_term']);

	if (empty($context['search_results']))
		echo '
		<p class="center"><strong>', $txt['admin_search_results_none'], '</strong></p>';

	else
	{
		echo '
		<ol class="search_results">';
		foreach ($context['search_results'] as $result)
		{
			echo '
			<li>
				', !empty($result['parent_name']) ? $result['parent_name'] . ' &#187; ' : '', '<a href="', $result['url'], '"><strong>', $result['name'], '</strong></a> [', isset($txt['admin_search_section_' . $result['type']]) ? $txt['admin_search_section_' . $result['type']] : $result['type'], ']';

			if ($result['help'])
				echo '
				<dfn>', $result['help'], '</dfn>';

			echo '
			</li>';
		}
		echo '
		</ol>';
	}

	echo '
	</div>
	<br class="clear">';
}

// This little beauty shows questions and answer from the captcha type feature.
function template_callback_question_answer_list()
{
	global $txt, $context;

	// First, we need to output the languages to JS.
	$lang_js = '';
	foreach ($context['languages'] as $lang_id => $lang)
		$lang_js .= ($lang_js != '' ? ', ' : '') . JavaScriptEscape($lang_id) . ':' . JavaScriptEscape($lang['name']);

	$row = 0;

	echo '
			<div class="right">
				<input type="button" class="new" value="', $txt['setup_verification_add'], '" onclick="addNewRow();">
			</div>
			<table class="w100 cs0" id="antispam">
				<thead>
					<tr>
						<th></th>
						<th>', $txt['setup_verification_question'], '</th>
						<th>', $txt['setup_verification_answer'], '</th>
					</tr>
				</thead>
				<tbody>';

	if (!empty($context['qa_verification_qas']))
	{
		foreach ($context['qa_verification_qas'] as $row => $question)
		{
			echo '
					<tr id="row', $row, '">
						<td class="lang">
							<select name="lang_select[', $row, ']" id="lang_select[', $row, ']">';
			foreach ($context['languages'] as $lang_id => $lang)
				echo '
								<option value="', $lang_id, '"', $question['lang'] == $lang_id ? ' selected' : '', '>&lt;div class="flag_', $lang_id, '"&gt;&lt;/div&gt;', $lang['name'], '</option>';

			echo '
							</select>
						</td>
						<td class="question"><input name="question[', $row, ']" value="', $question['question'], '" size="42"></td>
						<td class="answer">';
			$answers = array();
			foreach ($question['answers'] as $answer)
				$answers[] = '<input name="answer[' . $row . '][]" value="' . $answer . '" size="25">';
			echo '
							', implode('<br>', $answers), '
						</td>
						<td><input type="submit" class="new" value="', $txt['setup_verification_add_answer'], '" onclick="addAnswer(', $row, '); return false;"></td>
						<td><input type="submit" class="delete" value="', $txt['remove'], '" onclick="removeRow(', $row, '); return false;"></td>
					</tr>';
		}
		$row++;
	}

	echo '
				</tbody>
			</table>';

	add_js('
	var langs = {' . $lang_js . '},
		nextrow = ' . $row . ';
		remove_str = ' . JavaScriptEscape($txt['remove']) . ',
		addans_str = ' . JavaScriptEscape($txt['setup_verification_add_answer']) . ';

	function addNewRow()
	{
		var row_id = \'row\' + nextrow;
		$(\'#antispam\').append(\'<tr id="\' + row_id + \'"></tr>\');
		var lang_select = \'<td class="lang"><select name="lang_select[\' + nextrow + \']" id="lang_select[\' + nextrow + \']">\';
		$.each(langs, function(key, value) {
			lang_select += \'<option value="\' + key + \'">&lt;div class="flag_\' + key + \'"&gt;&lt;/div&gt;\' + value + \'</option>\';
		});
		lang_select += \'</select></td>\';
		$(\'#\' + row_id).append(lang_select);

		$(\'#\' + row_id).append(\'<td class="question"><input name="question[\' + nextrow + \']" size="42"></td>\');
		$(\'#\' + row_id).append(\'<td class="answer"><input name="answer[\' + nextrow + \'][]" size="25"></td>\');
		$(\'#\' + row_id).append(\'<td><input type="submit" class="new" value="\' + addans_str + \'" onclick="addAnswer(\' + nextrow + \'); return false;"></td>\');
		$(\'#\' + row_id).append(\'<td><input type="submit" class="delete" value="\' + remove_str + \'" onclick="removeRow(\' + nextrow + \'); return false;"></td>\');
		$(\'#\' + row_id + \' select\').sb();

		nextrow++;
		return false;
	};

	function removeRow(row)
	{
		$(\'#row\' + row).remove();
	};

	function addAnswer(row)
	{
		$(\'#row\' + row + \' td.answer\').append(\'<br><input name="answer[\' + row + \'][]" size="25">\');
	};

	addNewRow();');
}

// Repairing boards.
function template_repair_boards()
{
	global $context, $txt;

	echo '
		<we:cat>
			', $context['error_search'] ? $txt['errors_list'] : $txt['errors_fixing'], '
		</we:cat>
		<div class="windowbg wrc">';

	// Are we actually fixing them, or is this just a prompt?
	if ($context['error_search'])
	{
		if (!empty($context['to_fix']))
		{
			echo '
			', $txt['errors_found'], ':
			<ul>';

			foreach ($context['repair_errors'] as $error)
				echo '
				<li>
					', $error, '
				</li>';

			echo '
			</ul>
			<p>
				', $txt['errors_fix'], '
			</p>
			<p class="padding">
				<strong><a href="<URL>?action=admin;area=repairboards;fixErrors;', $context['session_query'], '">', $txt['yes'], '</a> - <a href="<URL>?action=admin;area=maintain">', $txt['no'], '</a></strong>
			</p>';
		}
		else
			echo '
			<p>', $txt['maintain_no_errors'], '</p>
			<p class="padding">
				<a href="<URL>?action=admin;area=maintain;sa=routine">', $txt['maintain_return'], '</a>
			</p>';

	}
	else
	{
		if (!empty($context['redirect_to_recount']))
		{
			echo '
			<p>
				', $txt['errors_do_recount'], '
			</p>
			<form action="<URL>?action=admin;area=maintain;sa=routine;activity=recount" id="recount_form" method="post">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" name="recount" id="recount_now" value="', westr::htmlspecialchars($txt['errors_recount_now']), '">
			</form>';
		}
		else
		{
			echo '
			<p>', $txt['errors_fixed'], '</p>
			<p class="padding">
				<a href="<URL>?action=admin;area=maintain;sa=routine">', $txt['maintain_return'], '</a>
			</p>';
		}
	}

	echo '
		</div>';

	if (!empty($context['redirect_to_recount']))
		add_js_inline('
	var countdown = 5;
	doAutoSubmit();

	function doAutoSubmit()
	{
		if (countdown == 0)
			document.forms.recount_form.submit();
		else if (countdown == -1)
			return;

		document.forms.recount_form.recount_now.value = ', JavaScriptEscape($txt['errors_recount_now']), ' + " (" + countdown + ")";
		countdown--;

		setTimeout(doAutoSubmit, 1000);
	}');
}

// Pretty URLs
function template_pretty_urls()
{
	global $context, $txt, $settings, $boardurl;

	if (!empty($context['pretty']['chrome']['menu']))
	{
		echo '
	<we:cat>
		', $txt['pretty_urls'], '
	</we:cat>
	<div class="windowbg wrc">
		<ul>';

		// Sub-actions
		foreach ($context['pretty']['chrome']['menu'] as $id => $item)
			echo '
			<li><a href="', $item['href'], '" class="', $id, '" title="', $item['title'], '"></a></li>';

		echo '
		</ul>
	</div>';
	}

	if (!empty($context['reset_output']))
		echo '
	<div class="information">
		', $context['reset_output'], '
	</div>';

	echo '
	<we:cat>
		', $txt['pretty_settings'], '
	</we:cat>
	<div class="windowbg2 wrc">
		<form id="adminsearch" action="<URL>?action=admin;area=featuresettings;sa=pretty;save" method="post" accept-charset="UTF-8">';

	// Display the filters
	if (!empty($context['pretty']['filters']))
	{
		foreach ($context['pretty']['filters'] as $id => $enabled)
		{
			echo '
			<div>
				<label>
					<input type="checkbox" id="pretty_filter_', $id, '" name="pretty_filter_', $id, '"', $enabled ? ' checked' : '',
					$id === 'actions' || $id === 'profiles' ? ' onclick="$(\'select[name=pretty_prefix_' . substr($id, 0, -1) . ']\').slideToggle(200);"' : '', '>
					', $txt['pretty_filter_' . $id], !empty($txt['pretty_filter_' . $id . '_example']) ? '<div class="pretty_filter">' . $txt['pretty_filter_' . $id . '_example'] . '</div>' : '', '
				</label>';

			if ($id === 'actions')
			{
				$prefix = empty($settings['pretty_prefix_action']) ? '' : $settings['pretty_prefix_action'];
				echo '
				<select name="pretty_prefix_action" class="pretty_prefix', $enabled ? '' : ' hide', '">
					<option value=""', $prefix == '' ? ' selected' : '', '>', $boardurl, '/action</option>
					<option value="do/"', $prefix == 'do/' ? ' selected' : '', '>', $boardurl, '/do/action</option>
				</select>';
			}

			if ($id === 'profiles')
			{
				$prefix = empty($settings['pretty_prefix_profile']) ? '' : $settings['pretty_prefix_profile'];
				echo '
				<select name="pretty_prefix_profile" class="pretty_prefix', $enabled ? '' : ' hide', '">
					<option value="~"', $prefix == '~' ? ' selected' : '', '>', $boardurl, '/~UserName/</option>
					<option value="profile/"', $prefix == 'profile/' ? ' selected' : '', '>', $boardurl, '/profile/UserName/</option>
				</select>';
			}

			echo '
			</div>';
		}
	}

	echo '
			<hr>
			<label>
				<input type="checkbox" name="pretty_cache"', $context['pretty']['settings']['cache'] ? ' checked' : '', '>
				', $txt['pretty_cache'], '
			</label>
			<label>
				<input type="checkbox" name="pretty_remove_index"', $context['pretty']['settings']['index'] ? ' checked' : '', '>
				', $txt['pretty_remove_index'], '
			</label>
			<div class="floatright">
				<input type="submit" name="save" value="', $txt['pretty_save'], '" class="save">
			</div>
		</form>
		<br class="clear">
	</div>
	<br>
	<we:cat>
		', $txt['pretty_maintenance'], '
	</we:cat>
	<div class="windowbg wrc">
		<form id="pretty_maintain_refill" action="<URL>?action=admin;area=featuresettings;sa=pretty;refill" method="post" accept-charset="UTF-8">
			<input type="submit" value="', $txt['pretty_refill'], '">
		</form>
	</div>';
}
