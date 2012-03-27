<?php
/**
 * Wedge
 *
 * The main administration template, including the home page, credits, file versions, censored words, pending-completion, generic settings handling and more.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// This is the administration center home.
function template_admin()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

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

	if ($context['user']['is_admin'])
		echo '
		<div id="quick_search">
			<form action="', $scripturl, '?action=admin;area=search" method="post" accept-charset="UTF-8" class="floatright">
				<input type="search" name="search_term" placeholder="', $txt['admin_search'], '" class="search">
				<select name="search_type">
					<option value="internal"', empty($context['admin_preferences']['sb']) || $context['admin_preferences']['sb'] == 'internal' ? ' selected' : '', '>', $txt['admin_search_type_internal'], '</option>
					<option value="member"', !empty($context['admin_preferences']['sb']) && $context['admin_preferences']['sb'] == 'member' ? ' selected' : '', '>', $txt['admin_search_type_member'], '</option>
				</select>
				<input type="submit" name="search_go" id="search_go" value="', $txt['admin_search_go'], '">
			</form>
			', $txt['admin_search_welcome'], '
		</div>';

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
			<div class="admin_item inline-block"><a href="', $scripturl, '?action=admin;area=', $area_id, ';' , $context['session_query'], '">', $area['bigicon'], '<br>', $area['label'], '</a></div>';
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
	global $txt, $scripturl;

	echo '
	<section>
		<we:title>
			<a href="', $scripturl, '?action=help;in=live_news" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
			', $txt['live'], '
		</we:title>
		<div id="wedge_news">', $txt['lfyi'], '</div>
	</section>';
}

// Display version numbers and who the admins are.
function template_admin_support_info()
{
	global $scripturl, $txt, $context;

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
			', $context['can_admin'] ? '<a href="' . $scripturl . '?action=admin;area=maintain;sa=routine;activity=version">' . $txt['version_check_more'] . '</a>' : '', '<br>';

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

// Show some support information and credits to those who helped make this.
function template_credits()
{
	global $context, $theme, $options, $scripturl, $txt;

	// Display latest support questions from wedge.org
	echo '
		<we:cat>
			<a href="', $scripturl, '?action=help;in=latest_support" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
			', $txt['support_latest'], '
		</we:cat>
		<div class="windowbg2 wrc">
			<div id="latestSupport">', $txt['support_latest_fetch'], '</div>
		</div>
		<we:cat>
			', $txt['admin_credits'], '
		</we:cat>
		<div class="windowbg wrc">';

	// The most important part - the credits. :P
	foreach ($context['credits'] as $section)
	{
		if (isset($section['pretext']))
			echo '
			<p>', $section['pretext'], '</p>';

		echo '
			<dl>';

		foreach ($section['groups'] as $group)
		{
			if (empty($group['members']))
				continue;

			if (isset($group['title']))
				echo '
				<dt>
					<strong>', $group['title'], ':</strong>
				</dt>';

			echo '
				<dd>', implode(', ', $group['members']), '</dd>';
		}

		echo '
			</dl>';

		if (isset($section['posttext']))
			echo '
			<p>', $section['posttext'], '</p>';
	}

	echo '
		</div>';
}

// Displays information about file versions installed, and compares them to current version.
function template_view_versions()
{
	global $context, $theme, $options, $scripturl, $txt;

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
		$scripturl . '?action=viewremote;filename=current-version.js',
		$scripturl . '?action=viewremote;filename=detailed-version.js',
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
	global $context, $theme, $options, $scripturl, $txt, $settings;

	// First section is for adding/removing words from the censored list.
	echo '
		<form action="', $scripturl, '?action=admin;area=postsettings;sa=censor" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['admin_censored_words'], '
			</we:cat>
			<div class="windowbg2 wrc">
				<p>', $txt['admin_censored_where'], '</p>';

	// Show text boxes for censoring [bad   ] => [good  ].
	foreach ($context['censored_words'] as $vulgar => $proper)
		echo '
				<div style="margin-top: 1ex"><input type="text" name="censor_vulgar[]" value="', $vulgar, '" size="20"> => <input type="text" name="censor_proper[]" value="', $proper, '" size="20"></div>';

	// Now provide a way to censor more words.
	echo '
				<noscript>
					<div style="margin-top: 1ex"><input type="text" name="censor_vulgar[]" size="20"> => <input type="text" name="censor_proper[]" size="20"></div>
				</noscript>
				<div id="moreCensoredWords"></div><div style="margin-top: 1ex; display: none" id="moreCensoredWords_link"><a href="#" onclick="addNewWord(); return false;">', $txt['censor_clickadd'], '</a></div>';

	add_js('
	$("#moreCensoredWords_link").show();

	function addNewWord()
	{
		$("#moreCensoredWords").append(\'<div style="margin-top: 1ex"><input type="text" name="censor_vulgar[]" size="20"> =&gt; <input type="text" name="censor_proper[]" size="20"><\' + \'/div>\');
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
					<input type="text" name="censortest" value="', empty($context['censor_test']) ? '' : $context['censor_test'], '">
					<input type="submit" value="', $txt['censor_test_save'], '" class="submit">
				</p>
			</div>

			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
}

// Maintenance is a lovely thing, isn't it?
function template_not_done()
{
	global $context, $theme, $options, $txt, $scripturl;

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
					<div style="padding-top: ', $context['browser']['is_webkit'] ? '2pt' : '1pt', '; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold">', $context['continue_percent'], '%</div>
					<div style="width: ', $context['continue_percent'], '%; height: 12pt; z-index: 1; background-color: red">&nbsp;</div>
				</div>
			</div>';

	if (!empty($context['substep_enabled']))
		echo '
			<div style="padding-left: 20%; padding-right: 20%; margin-top: 1ex">
				<span class="smalltext">', $context['substep_title'], '</span>
				<div style="font-size: 8pt; height: 12pt; border: 1px solid black; background-color: white; padding: 1px; position: relative">
					<div style="padding-top: ', $context['browser']['is_webkit'] ? '2pt' : '1pt', '; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold">', $context['substep_continue_percent'], '%</div>
					<div style="width: ', $context['substep_continue_percent'], '%; height: 12pt; z-index: 1; background-color: blue">&nbsp;</div>
				</div>
			</div>';

	echo '
			<form action="', $scripturl, $context['continue_get_data'], '" method="post" accept-charset="UTF-8" style="margin: 0" name="autoSubmit" id="autoSubmit">
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
	global $context, $txt, $theme, $scripturl;

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
					<a href="' . $scripturl . '?action=help;in=' . $config_var['help'] . '" onclick="return reqWin(this);" class="help" title="' . $txt['help'] . '"></a>' : '', '
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
						<label for="', $config_var['name'], '"><a id="setting_', $config_var['name'], '" href="', $scripturl, '?action=help;in=', $config_var['help'], '" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
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
						echo '
							<option value="', $option[0], '"', $option[0] == $config_var['value'] ? ' selected' : '', '>', $option[1], '</option>';
					echo '
						</select>';
				}
				elseif ($config_var['type'] == 'multi_select')
				{
					echo '
						<fieldset id="fs_', $config_var['name'], '">
							<legend><a href="#" onclick="$(\'#fs_', $config_var['name'], '\').hide(); $(\'#fs_', $config_var['name'], '_groups_link\').show(); return false;">', $txt['select_from_list'], '</a></legend>
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
						<a href="#" onclick="$(\'#fs_', $config_var['name'], '\').show(); $(\'#fs_', $config_var['name'], '_link\').hide(); return false;" id="fs_', $config_var['name'], '_link" class="hide">[ ', $txt['click_to_see_more'], ' ]</a>';

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
									<label><input type="checkbox" name="', $config_var['name'], '_enabledTags[]" id="tag_', $config_var['name'], '_', $bbcTag['tag'], '" value="', $bbcTag['tag'], '"', !in_array($bbcTag['tag'], $context['bbc_sections'][$config_var['name']]['disabled']) ? ' checked' : '', '> ', $bbcTag['tag'], '</label>', $bbcTag['show_help'] ? ' (<a href="' . $scripturl . '?action=help;in=tag_' . $bbcTag['tag'] . '" onclick="return reqWin(this);">?</a>)' : '', '
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
						<input type="number"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '"', $config_var['size'] ? ' size="' . $config_var['size'] . '"' : '', isset($config_var['min']) ? ' min="' . $config_var['min'] . '"' : '', isset($config_var['max']) ? ' max="' . $config_var['max'] . '"' : '', ' step="', !empty($config_var['step']) ? $config_var['step'] : 1, '">';
				// Assume it must be a text box.
				else
					echo '
						<input type="text"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '"', $config_var['size'] ? ' size="' . $config_var['size'] . '"' : '', '>';

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

// Template for showing custom profile fields.
function template_show_custom_profile()
{
	global $context, $txt, $theme, $scripturl;

	// Standard fields.
	template_show_list('standard_profile_fields');

	add_js_inline('
	var iNumChecks = document.forms.standardProfileFields.length;
	for (var i = 0; i < iNumChecks; i++)
		if (document.forms.standardProfileFields[i].id.indexOf(\'reg_\') == 0)
			document.forms.standardProfileFields[i].disabled = document.forms.standardProfileFields[i].disabled || !document.getElementById(\'active_\' + document.forms.standardProfileFields[i].id.substr(4)).checked;');

	// Custom fields.
	template_show_list('custom_profile_fields');
}

// Edit a profile field?
function template_edit_profile_field()
{
	global $context, $txt, $theme, $scripturl;

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
		$("#display").attr("disabled", false);

		// Cannot show this on the topic
		if (curType == "textarea" || privStatus >= 2)
			$("#display").attr("checked", false).attr("disabled", true);

		// Able to show to guests?
		$("#guest_access_dt, #guest_access_dd").toggle(privStatus < 2);
	}
	updateInputBoxes();');

	add_js('
	var startOptID = ', count($context['field']['options']), ';
	function addOption()
	{
		$("#addopt").append(\'<br><input type="radio" name="default_select" value="\' + startOptID + \'" id="\' + startOptID + \'"><input type="text" name="select_option[\' + startOptID + \']" value="">\');
		startOptID++;
	}');

	echo '
		<form action="', $scripturl, '?action=admin;area=memberoptions;sa=profileedit;fid=', $context['fid'], ';', $context['session_query'], '" method="post" accept-charset="UTF-8">
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
					$("input[name=\"" + i + "\"]").attr("checked", insertTemplate.templates[field][i]);
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
	insertTemplate.templates = ', json_encode($fields), ';');
	}

	echo '
				<fieldset>
					<legend>', $txt['custom_edit_general'], '</legend>

					<dl class="settings">
						<dt>
							<strong>', $txt['custom_edit_name'], ':</strong>
						</dt>
						<dd>
							<input type="text" name="field_name" value="', $context['field']['name'], '" size="20" maxlength="40">
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
							<a id="field_show_enclosed" href="', $scripturl, '?action=help;in=field_show_enclosed" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
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
							<select name="field_type" id="field_type" onchange="updateInputBoxes();">
								<option value="text"', $context['field']['type'] == 'text' ? ' selected' : '', '>', $txt['custom_profile_type_text'], '</option>
								<option value="textarea"', $context['field']['type'] == 'textarea' ? ' selected' : '', '>', $txt['custom_profile_type_textarea'], '</option>
								<option value="select"', $context['field']['type'] == 'select' ? ' selected' : '', '>', $txt['custom_profile_type_select'], '</option>
								<option value="radio"', $context['field']['type'] == 'radio' ? ' selected' : '', '>', $txt['custom_profile_type_radio'], '</option>
								<option value="check"', $context['field']['type'] == 'check' ? ' selected' : '', '>', $txt['custom_profile_type_check'], '</option>
							</select>
						</dd>
						<dt id="max_length_dt">
							<strong>', $txt['custom_edit_max_length'], ':</strong>
							<dfn>', $txt['custom_edit_max_length_desc'], '</dfn>
						</dt>
						<dd id="max_length_dd">
							<input type="text" name="max_length" value="', $context['field']['max_length'], '" size="7" maxlength="6">
						</dd>
						<dt id="dimension_dt">
							<strong>', $txt['custom_edit_dimension'], ':</strong>
						</dt>
						<dd id="dimension_dd">
							<strong>', $txt['custom_edit_dimension_row'], ':</strong> <input type="text" name="rows" value="', $context['field']['rows'], '" size="5" maxlength="3">
							<strong>', $txt['custom_edit_dimension_col'], ':</strong> <input type="text" name="cols" value="', $context['field']['cols'], '" size="5" maxlength="3">
						</dd>
						<dt id="bbc_dt">
							<strong>', $txt['custom_edit_bbc'], '</strong>
						</dt>
						<dd id="bbc_dd">
							<input type="checkbox" name="bbc"', $context['field']['bbc'] ? ' checked' : '', '>
						</dd>
						<dt id="options_dt">
							<a href="', $scripturl, '?action=help;in=customoptions" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
							<strong>', $txt['custom_edit_options'], ':</strong>
							<dfn>', $txt['custom_edit_options_desc'], '</dfn>
						</dt>
						<dd id="options_dd">
							<div>';

	foreach ($context['field']['options'] as $k => $option)
		echo '
								', $k == 0 ? '' : '<br>', '<input type="radio" name="default_select" value="', $k, '"', $context['field']['default_select'] == $option ? ' checked' : '', '><input type="text" name="select_option[', $k, ']" value="', $option, '">';

	echo '
								<span id="addopt"></span>
								[<a href="" onclick="addOption(); return false;">', $txt['custom_edit_options_more'], '</a>]
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
							<a id="custom_mask" href="', $scripturl, '?action=help;in=custom_mask" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
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
							<input type="text" name="regex" id="regex_div" value="', $context['field']['regex'], '" size="30">
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
					<input type="submit" name="delete" value="', $txt['delete'], '" onclick="return confirm(', JavaScriptEscape($txt['custom_edit_delete_sure']), ');" class="delete">';

	echo '
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
}

// Results page for an admin search.
function template_admin_search_results()
{
	global $context, $txt, $theme, $options, $scripturl;

	echo '
	<we:cat>
		<div class="floatright">
			<form action="', $scripturl, '?action=admin;area=search" method="post" accept-charset="UTF-8" style="font-weight: normal; display: inline" id="quick_search">
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
			// Is it a result from the online manual?
			if ($context['search_type'] == 'online')
			{
				echo '
			<li>
				<p>
					<a href="', $context['doc_scripturl'], '?topic=', $result['topic_id'], '.0" target="_blank" class="new_win"><strong>', $result['messages'][0]['subject'], '</strong></a>
					<div class="smalltext"><a href="', $result['category']['href'], '" target="_blank" class="new_win">', $result['category']['name'], '</a> &nbsp;/&nbsp;
					<a href="', $result['board']['href'], '" target="_blank" class="new_win">', $result['board']['name'], '</a>&nbsp;/</div>
				</p>
				<p class="double_height">
					', $result['messages'][0]['body'], '
				</p>
			</li>';
			}
			// Otherwise it's... not!
			else
			{
				echo '
			<li>
				<a href="', $result['url'], '"><strong>', $result['name'], '</strong></a> [', isset($txt['admin_search_section_' . $result['type']]) ? $txt['admin_search_section_' . $result['type']] : $result['type'], ']';

				if ($result['help'])
					echo '
				<p class="double_height">', $result['help'], '</p>';

				echo '
			</li>';
			}
		}
		echo '
		</ol>';
	}

	echo '
	</div>
	<br class="clear">';
}

// Add a new language
function template_add_language()
{
	global $context, $theme, $options, $txt, $scripturl;

	echo '
		<form action="', $scripturl, '?action=admin;area=languages;sa=add;', $context['session_query'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['add_language'], '
			</we:cat>
			<div class="windowbg wrc">
				<fieldset>
					<legend>', $txt['add_language_wedge'], '</legend>
					<label class="smalltext">', $txt['add_language_wedge_browse'], '</label>
					<input type="text" name="we_add" size="40" value="', !empty($context['we_search_term']) ? $context['we_search_term'] : '', '">';

	if (!empty($context['wedge_error']))
		echo '
					<div class="smalltext error">', $txt['add_language_error_' . $context['wedge_error']], '</div>';

	echo '
				</fieldset>
				<div class="right">', $context['browser']['is_ie'] ? '
					<input type="text" name="ie_fix" class="hide"> ' : '', '
					<input type="submit" name="we_add_sub" value="', $txt['search'], '" class="submit">
				</div>
			</div>';

	// Had some results?
	if (!empty($context['wedge_languages']))
	{
		echo '
			<div class="information">', $txt['add_language_wedge_found'], '</div>

			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg">
						<th class="first_th" scope="col">', $txt['name'], '</th>
						<th scope="col">', $txt['add_language_wedge_desc'], '</th>
						<th scope="col">', $txt['add_language_wedge_version'], '</th>
						<th class="last_th" scope="col">', $txt['add_language_wedge_install'], '</th>
					</tr>
				</thead>
				<tbody>';

		foreach ($context['wedge_languages'] as $language)
			echo '
					<tr class="windowbg2 left">
						<td>', $language['name'], '</td>
						<td>', $language['description'], '</td>
						<td>', $language['version'], '</td>
						<td><a href="', $language['link'], '">', $txt['add_language_wedge_install'], '</a></td>
					</tr>';

		echo '
				</tbody>
			</table>';
	}

	echo '
		</form>';
}

// Download a new language file?
function template_download_language()
{
	global $context, $theme, $options, $txt, $scripturl, $settings;

	// Actually finished?
	if (!empty($context['install_complete']))
	{
		echo '
		<we:cat>
			', $txt['languages_download_complete'], '
		</we:cat>
		<div class="windowbg wrc">
			', $context['install_complete'], '
		</div>';
		return;
	}

	// An error?
	if (!empty($context['error_message']))
		echo '
		<div id="errorbox">
			<p>', $context['error_message'], '</p>
		</div>';

	// Provide something of an introduction...
	echo '
		<form action="', $scripturl, '?action=admin;area=languages;sa=downloadlang;did=', $context['download_id'], ';', $context['session_query'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['languages_download'], '
			</we:cat>
			<div class="windowbg wrc">
				<p>
					', $txt['languages_download_note'], '
				</p>
				<div class="smalltext">
					', $txt['languages_download_info'], '
				</div>
			</div>';

	// Show the main files.
	template_show_list('lang_main_files_list');

	// Now, all the images and the likes, hidden via javascript 'cause there are so fecking many.
	echo '
			<br>
			<we:title>
				', $txt['languages_download_theme_files'], '
			</we:title>
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg">
						<th class="first_th" scope="col">
							', $txt['languages_download_filename'], '
						</th>
						<th scope="col" style="width: 100px">
							', $txt['languages_download_writable'], '
						</th>
						<th scope="col" style="width: 100px">
							', $txt['languages_download_exists'], '
						</th>
						<th class="last_th" scope="col" style="width: 50px">
							', $txt['languages_download_copy'], '
						</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['files']['images'] as $th => $group)
	{
		$count = 0;
		echo '
				<tr class="titlebg">
					<td colspan="4">
						<div class="sortselect" id="toggle_image_', $th, '"></div>&nbsp;', isset($context['theme_names'][$th]) ? $context['theme_names'][$th] : $th, '
					</td>
				</tr>';

		$alternate = false;
		foreach ($group as $file)
		{
			echo '
				<tr class="windowbg', $alternate ? '2' : '', '" id="', $th, '-', $count++, '">
					<td>
						<strong>', $file['name'], '</strong>
						<div class="smalltext">', $txt['languages_download_dest'], ': ', $file['destination'], '</div>
					</td>
					<td>
						<span style="color: ', $file['writable'] ? 'green' : 'red', '">', $file['writable'] ? $txt['yes'] : $txt['no'], '</span>
					</td>
					<td>
						', $file['exists'] ? ($file['exists'] == 'same' ? $txt['languages_download_exists_same'] : $txt['languages_download_exists_different']) : $txt['no'], '
					</td>
					<td>
						<input type="checkbox" name="copy_file[]" value="', $file['generaldest'], '"', $file['default_copy'] ? ' checked' : '', '>
					</td>
				</tr>';
			$alternate = !$alternate;
		}
	}

	echo '
			</tbody>
			</table>';

	// Do we want some FTP baby?
	if (!empty($context['still_not_writable']))
	{
		if (!empty($context['package_ftp']['error']))
			echo '
			<div id="errorbox">
				<tt>', $context['package_ftp']['error'], '</tt>
			</div>';

		echo '
			<we:cat>
				', $txt['package_ftp_necessary'], '
			</we:cat>
			<div class="windowbg wrc">
				<p>', $txt['package_ftp_why'], '</p>
				<dl class="settings">
					<dt
						<label for="ftp_server">', $txt['package_ftp_server'], ':</label>
					</dt>
					<dd>
						<div class="floatright" style="margin-right: 1px"><label style="padding-top: 2px; padding-right: 2ex">', $txt['package_ftp_port'], ':&nbsp;<input type="text" size="3" name="ftp_port" value="', isset($context['package_ftp']['port']) ? $context['package_ftp']['port'] : (isset($settings['package_port']) ? $settings['package_port'] : '21'), '"></label></div>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="', isset($context['package_ftp']['server']) ? $context['package_ftp']['server'] : (isset($settings['package_server']) ? $settings['package_server'] : 'localhost'), '" style="width: 70%">
					</dd>

					<dt>
						<label for="ftp_username">', $txt['package_ftp_username'], ':</label>
					</dt>
					<dd>
						<input type="text" size="42" name="ftp_username" id="ftp_username" value="', isset($context['package_ftp']['username']) ? $context['package_ftp']['username'] : (isset($settings['package_username']) ? $settings['package_username'] : ''), '" style="width: 99%">
					</dd>

					<dt>
						<label for="ftp_password">', $txt['package_ftp_password'], ':</label>
					</dt>
					<dd>
						<input type="password" size="42" name="ftp_password" id="ftp_password" style="width: 99%">
					</dd>

					<dt>
						<label for="ftp_path">', $txt['package_ftp_path'], ':</label>
					</dt>
					<dd>
						<input type="text" size="42" name="ftp_path" id="ftp_path" value="', $context['package_ftp']['path'], '" style="width: 99%">
					</dd>
				</dl>
			</div>';
	}

	// Install?
	echo '
			<div class="right padding">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" name="do_install" value="', $txt['add_language_wedge_install'], '" class="submit">
			</div>
		</form>';

	// The javascript for expanding and collapsing sections.
	// Each theme gets its own handler.
	foreach ($context['files']['images'] as $th => $group)
	{
		$count = 0;

		add_js('
	new weToggle({
		bCurrentlyCollapsed: true,
		aSwappableContainers: [');

		foreach ($group as $file)
			add_js('
			', JavaScriptEscape($th . '-' . $count++), ',');

		add_js('
			null
		],
		aSwapImages: [{ sId: \'toggle_image_', $th, '\' }]
	});');
	}
}

// Edit some language entries?
function template_modify_language_entries()
{
	global $context, $theme, $options, $txt, $scripturl;

	echo '
		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['edit_languages'], '
			</we:cat>';

	// Not writable?
	if ($context['lang_file_not_writable_message'])
		echo '
			<div id="errorbox">
				<p class="alert">', $context['lang_file_not_writable_message'], '</p>
			</div>';

	echo '
			<div class="information">
				', $txt['edit_language_entries_primary'], '
			</div>
			<div class="windowbg wrc">
				<fieldset>
					<legend>', $context['primary_settings']['name'], '</legend>
				<dl class="settings">
					<dt>
						', $txt['languages_locale'], ':
					</dt>
					<dd>
						<input type="text" name="locale" size="20" value="', $context['primary_settings']['locale'], '"', empty($context['file_entries']) ? '' : ' disabled', '>
					</dd>
					<dt>
						', $txt['languages_dictionary'], ':
					</dt>
					<dd>
						<input type="text" name="dictionary" size="20" value="', $context['primary_settings']['dictionary'], '"', empty($context['file_entries']) ? '' : ' disabled', '>
					</dd>
					<dt>
						', $txt['languages_spelling'], ':
					</dt>
					<dd>
						<input type="text" name="spelling" size="20" value="', $context['primary_settings']['spelling'], '"', empty($context['file_entries']) ? '' : ' disabled', '>
					</dd>
					<dt>
						', $txt['languages_rtl'], ':
					</dt>
					<dd>
						<input type="checkbox" name="rtl"', $context['primary_settings']['rtl'] ? ' checked' : '', empty($context['file_entries']) ? '' : ' disabled', '>
					</dd>
				</dl>
				</fieldset>
				<div class="right">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="submit" name="save_main" value="', $txt['save'], '"', $context['lang_file_not_writable_message'] || !empty($context['file_entries']) ? ' disabled' : '', ' class="save">';

	// English can't be deleted.
	if ($context['lang_id'] != 'english')
		echo '
					<input type="submit" name="delete_main" value="', $txt['delete'], '"', $context['lang_file_not_writable_message'] || !empty($context['file_entries']) ? ' disabled' : '', ' onclick="confirm(', JavaScriptEscape($txt['languages_delete_confirm']), ');" class="delete">';

	echo '
				</div>
			</div>
		</form>

		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], ';entries" id="entry_form" method="post" accept-charset="UTF-8">
			<we:title>
				', $txt['edit_language_entries'], '
			</we:title>
			<div id="taskpad" class="floatright">
				', $txt['edit_language_entries_file'], ':
				<select name="tfid" onchange="if (this.value != -1) document.forms.entry_form.submit();">';

	foreach ($context['possible_files'] as $id_theme => $th)
	{
		echo '
					<option value="-1">', $th['name'], '</option>';

		foreach ($th['files'] as $file)
			echo '
					<option value="', $id_theme, '|', $file['id'], '"', $file['selected'] ? ' selected' : '', '> =&gt; ', $file['name'], '</option>';
	}

	echo '
				</select>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" value="', $txt['go'], '" class="submit">
			</div>';

		// Is it not writable?
	if (!empty($context['entries_not_writable_message']))
		echo '
			<div id="errorbox">
				<span class="alert">', $context['entries_not_writable_message'], '</span>
			</div>';

	// Already have some?
	if (!empty($context['file_entries']))
	{
		echo '
			<div class="windowbg2 wrc">
				<dl class="settings">';

		$cached = array();
		foreach ($context['file_entries'] as $entry)
		{
			// Do it in two's!
			if (empty($cached))
			{
				$cached = $entry;
				continue;
			}

			echo '
					<dt>
						<span class="smalltext">', $cached['key'], '</span>
					</dt>
					<dd>
						<span class="smalltext">', $entry['key'], '</span>
					</dd>
					<dt>
						<input type="hidden" name="comp[', $cached['key'], ']" value="', $cached['value'], '">
						<textarea name="entry[', $cached['key'], ']" cols="40" rows="', $cached['rows'] < 2 ? 2 : $cached['rows'], '" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 96%; min-width: 96%' : 'width: 96%') . '">', $cached['value'], '</textarea>
					</dt>
					<dd>
						<input type="hidden" name="comp[', $entry['key'], ']" value="', $entry['value'], '">
						<textarea name="entry[', $entry['key'], ']" cols="40" rows="', $entry['rows'] < 2 ? 2 : $entry['rows'], '" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 96%; min-width: 96%' : 'width: 96%') . '">', $entry['value'], '</textarea>
					</dd>';
			$cached = array();
		}

		// Odd number?
		if (!empty($cached))
			echo '
					<dt>
						<span class="smalltext">', $cached['key'], '</span>
					</dt>
					<dd>
					</dd>
					<dt>
						<input type="hidden" name="comp[', $cached['key'], ']" value="', $cached['value'], '">
						<textarea name="entry[', $cached['key'], ']" cols="40" rows="2" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 96%; min-width: 96%' : 'width: 96%') . '">', $cached['value'], '</textarea>
					</dt>
					<dd>
					</dd>';

		echo '
				</dl>
				<input type="submit" name="save_entries" value="', $txt['save'], '"', !empty($context['entries_not_writable_message']) ? ' disabled' : '', ' class="save">';

		echo '
			</div>';
	}

	echo '
		</form>';
}

// This little beauty shows questions and answer from the captcha type feature.
function template_callback_question_answer_list()
{
	global $txt, $context;

	echo '
			<dt>
				<strong>', $txt['setup_verification_question'], '</strong>
			</dt>
			<dd>
				<strong>', $txt['setup_verification_answer'], '</strong>
			</dd>';

	foreach ($context['question_answers'] as $data)
		echo '

			<dt>
				<input type="text" name="question[', $data['id'], ']" value="', $data['question'], '" size="42" class="verification_question">
			</dt>
			<dd>
				<input type="text" name="answer[', $data['id'], ']" value="', $data['answer'], '" size="42" class="verification_answer">
			</dd>';

	// Some blank ones.
	for ($count = 0; $count < 3; $count++)
		echo '
			<dt>
				<input type="text" name="question[]" size="42" class="verification_question">
			</dt>
			<dd>
				<input type="text" name="answer[]" size="42" class="verification_answer">
			</dd>';

	echo '
			<dt id="add_more_question_placeholder" class="hide"></dt>
			<dd></dd>
			<dt id="add_more_link_div" class="hide">
				<a href="#" onclick="addAnotherQuestion(); return false;">&#171; ', $txt['setup_verification_add_more'], ' &#187;</a>
			</dt>
			<dd></dd>';

	// Create a named element dynamically
	// Thanks to: http://www.thunderguy.com/semicolon/2005/05/23/setting-the-name-attribute-in-internet-explorer/
	add_js_inline('
	function createNamedElement(type, name, customFields)
	{
		var element = null;

		if (!customFields)
			customFields = "";

		// Try the IE way; this fails on standards-compliant browsers
		try
		{
			element = document.createElement("<" + type + \' name="\' + name + \'" \' + customFields + ">");
		}
		catch (e) {}
		if (!element || element.nodeName != type.toUpperCase())
		{
			// Non-IE browser; use canonical method to create named element
			element = document.createElement(type);
			element.name = name;
		}

		return element;
	}

	var placeHolder = document.getElementById(\'add_more_question_placeholder\');

	function addAnotherQuestion()
	{
		var newDT = document.createElement("dt");

		var newInput = createNamedElement("input", "question[]");
		newInput.type = "text";
		newInput.size = "42";
		newInput.setAttribute("class", "verification_question");
		newDT.appendChild(newInput);

		newDD = document.createElement("dd");

		newInput = createNamedElement("input", "answer[]");
		newInput.type = "text";
		newInput.size = "42";
		newInput.setAttribute("class", "verification_answer");
		newDD.appendChild(newInput);

		placeHolder.parentNode.insertBefore(newDT, placeHolder);
		placeHolder.parentNode.insertBefore(newDD, placeHolder);
	}
	document.getElementById(\'add_more_link_div\').style.display = \'\';');
}

// Repairing boards.
function template_repair_boards()
{
	global $context, $txt, $scripturl;

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
				<strong><a href="', $scripturl, '?action=admin;area=repairboards;fixErrors;', $context['session_query'], '">', $txt['yes'], '</a> - <a href="', $scripturl, '?action=admin;area=maintain">', $txt['no'], '</a></strong>
			</p>';
		}
		else
			echo '
			<p>', $txt['maintain_no_errors'], '</p>
			<p class="padding">
				<a href="', $scripturl, '?action=admin;area=maintain;sa=routine">', $txt['maintain_return'], '</a>
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
			<form action="', $scripturl, '?action=admin;area=maintain;sa=routine;activity=recount" id="recount_form" method="post">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" name="recount" id="recount_now" value="', westr::htmlspecialchars($txt['errors_recount_now']), '">
			</form>';
		}
		else
		{
			echo '
			<p>', $txt['errors_fixed'], '</p>
			<p class="padding">
				<a href="', $scripturl, '?action=admin;area=maintain;sa=routine">', $txt['maintain_return'], '</a>
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
	global $context, $scripturl, $txt, $settings, $boardurl;

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
		<form id="adminsearch" action="', $scripturl, '?action=admin;area=featuresettings;sa=pretty;save" method="post" accept-charset="UTF-8">';

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
		<form id="pretty_maintain_refill" action="', $scripturl, '?action=admin;area=featuresettings;sa=pretty;refill" method="post" accept-charset="UTF-8">
			<input type="submit" value="', $txt['pretty_refill'], '">
		</form>
	</div>';
}

?>