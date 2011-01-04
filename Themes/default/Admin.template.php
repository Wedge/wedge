<?php
// Version: 2.0 RC4; Admin

// This is the administration center home.
function template_admin()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Welcome message for the admin.
	echo '
	<div id="admincenter">
		<we:title>';

	if ($context['user']['is_admin'])
		echo '
			<form action="', $scripturl, '?action=admin;area=search" method="post" accept-charset="UTF-8" class="floatright" id="quick_search">
				<img src="', $settings['images_url'], '/filter.gif" />
				<input type="text" name="search_term" value="', $txt['admin_search'], '" onclick="if (this.value == \'', $txt['admin_search'], '\') this.value = \'\';" class="input_text" />
				<select name="search_type">
					<option value="internal"', (empty($context['admin_preferences']['sb']) || $context['admin_preferences']['sb'] == 'internal' ? ' selected="selected"' : ''), '>', $txt['admin_search_type_internal'], '</option>
					<option value="member"', (!empty($context['admin_preferences']['sb']) && $context['admin_preferences']['sb'] == 'member' ? ' selected="selected"' : ''), '>', $txt['admin_search_type_member'], '</option>
					<option value="online"', (!empty($context['admin_preferences']['sb']) && $context['admin_preferences']['sb'] == 'online' ? ' selected="selected"' : ''), '>', $txt['admin_search_type_online'], '</option>
				</select>
				<input type="submit" name="search_go" id="search_go" value="', $txt['admin_search_go'], '" class="button_submit" />
			</form>';

	echo '
			', $txt['admin_center'], '
		</we:title>
		<div class="roundframe">
			<div id="welcome">
				<strong>', $txt['hello_guest'], ' ', $context['user']['name'], '!</strong>
				', sprintf($txt['admin_main_welcome'], $txt['admin_center'], $txt['help'], $txt['help']), '
			</div>
		</div>';

	// Is there an update available?
	echo '
		<div id="update_section"></div>
		<div id="admin_main_section">';

	// Display the "live news" from simplemachines.org.
	echo '
			<div id="live_news" class="floatleft">
				<div class="cat_bar">
					<h3>
						<a href="', $scripturl, '?action=helpadmin;help=live_news" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" /></a>', $txt['live'], '
					</h3>
				</div>
				<div class="windowbg wrc">
					<div id="smfAnnouncements">', $txt['lfyi'], '</div>
				</div>
			</div>';

	// Show the user version information from their server.
	echo '
			<div id="supportVersionsTable" class="floatright">
				<div class="cat_bar">
					<h3>
						<a href="', $scripturl, '?action=admin;area=credits">', $txt['support_title'], '</a>
					</h3>
				</div>
				<div class="windowbg wrc">
					<div id="version_details">
						<strong>', $txt['support_versions'], ':</strong><br />
						', $txt['support_versions_forum'], ':
						<em id="yourVersion" class="nowrap">', $context['forum_version'], '</em><br />
						', $txt['support_versions_current'], ':
						<em id="smfVersion" class="nowrap">??</em><br />
						', $context['can_admin'] ? '<a href="' . $scripturl . '?action=admin;area=maintain;sa=routine;activity=version">' . $txt['version_check_more'] . '</a>' : '', '<br />';

	// Display all the members who can administrate the forum.
	echo '
						<br />
						<strong>', $txt['administrators'], ':</strong>
						', implode(', ', $context['administrators']);
	// If we have lots of admins... don't show them all.
	if (!empty($context['more_admins_link']))
		echo '
						(', $context['more_admins_link'], ')';

	echo '
					</div>
				</div>
			</div>
		</div>';

	echo '
		<div class="windowbg2 wrc clear_right">
			<ul id="quick_tasks" class="flow_hidden">';

	foreach ($context['quick_admin_tasks'] as $task)
		echo '
				<li>
					', !empty($task['icon']) ? '<a href="' . $task['href'] . '"><img src="' . $settings['default_images_url'] . '/admin/' . $task['icon'] . '" class="home_image" /></a>' : '', '
					<h5>', $task['link'], '</h5>
					<span class="task">', $task['description'], '</span>
				</li>';

	echo '
			</ul>
			<div class="clear"></div>
		</div>
	</div>
	<br class="clear" />';

	// The below functions include all the scripts needed from the simplemachines.org site. The language and format are passed for internationalization.
	if (empty($modSettings['disable_smf_js']))
		add_js_file(array(
			$scripturl . '?action=viewsmfile;filename=current-version.js',
			$scripturl . '?action=viewsmfile;filename=latest-news.js'
		), true);

	add_js_file('scripts/admin.js');

	// This sets the announcements and current versions themselves ;)
	add_js('
	var oAdminIndex = new smf_AdminIndex({
		sSelf: \'oAdminCenter\',

		bLoadAnnouncements: true,
		sAnnouncementTemplate: ', JavaScriptEscape('
			<dl>
				%content%
			</dl>
		'), ',
		sAnnouncementMessageTemplate: ', JavaScriptEscape('
			<dt><a href="%href%">%subject%</a> ' . $txt['on'] . ' %time%</dt>
			<dd>
				%message%
			</dd>
		'), ',
		sAnnouncementContainerId: \'smfAnnouncements\',

		bLoadVersions: true,
		sSmfVersionContainerId: \'smfVersion\',
		sYourVersionContainerId: \'yourVersion\',
		sVersionOutdatedTemplate: ' . JavaScriptEscape('
			<span class="alert">%currentVersion%</span>
		') . ',

		bLoadUpdateNotification: true,
		sUpdateNotificationContainerId: \'update_section\',
		sUpdateNotificationDefaultTitle: ' . JavaScriptEscape($txt['update_available']) . ',
		sUpdateNotificationDefaultMessage: ' . JavaScriptEscape($txt['update_message']) . ',
		sUpdateNotificationTemplate: ' . JavaScriptEscape('
			<div class="cat_bar">
				<h3 id="update_title">
					%title%
				</h3>
			</div>
			<div class="windowbg wrc">
				<div id="update_message" class="smalltext">
					%message%
				</div>
			</div>') . ',
		sUpdateNotificationLink: ' . JavaScriptEscape($scripturl . '?action=admin;area=packages;pgdownload;auto;package=%package%;' . $context['session_var'] . '=' . $context['session_id']) . '
	});');
}

// Show some support information and credits to those who helped make this.
function template_credits()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Show the user version information from their server.
	echo '

	<div id="admincenter">
		<div class="cat_bar">
			<h3>
				', $txt['support_title'], '
			</h3>
		</div>
		<div class="windowbg wrc">
			<strong>', $txt['support_versions'], ':</strong><br />
				', $txt['support_versions_forum'], ':
			<em id="yourVersion" class="nowrap">', $context['forum_version'], '</em>', $context['can_admin'] ? ' <a href="' . $scripturl . '?action=admin;area=maintain;sa=routine;activity=version">' . $txt['version_check_more'] . '</a>' : '', '<br />
				', $txt['support_versions_current'], ':
			<em id="smfVersion" class="nowrap">??</em><br />';

	// Display all the variables we have server information for.
	foreach ($context['current_versions'] as $version)
		echo '
			', $version['title'], ':
			<em>', $version['version'], '</em><br />';

	echo '
		</div>';

	// Point the admin to common support resources.
	echo '
		<div class="cat_bar">
			<h3>
				', $txt['support_resources'], '
			</h3>
		</div>
		<div class="windowbg2 wrc">
			<p>', $txt['support_resources_p1'], '</p>
			<p>', $txt['support_resources_p2'], '</p>
		</div>';

	// Display latest support questions from simplemachines.org.
	echo '
		<div class="cat_bar">
			<h3>
				<a href="', $scripturl, '?action=helpadmin;help=latest_support" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" /></a> ', $txt['support_latest'], '
			</h3>
		</div>
		<div class="windowbg2 wrc">
			<div id="latestSupport">', $txt['support_latest_fetch'], '</div>
		</div>';

	// The most important part - the credits :P.
	echo '
		<div class="cat_bar">
			<h3>
				', $txt['admin_credits'], '
			</h3>
		</div>
		<div class="windowbg wrc">';

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
		</div>
	</div>
	<br class="clear" />';

	// This makes all the support information available to the support script...
	add_js('
	var smfSupportVersions = {};

	smfSupportVersions.forum = "', $context['forum_version'], '";');

	// Don't worry, none of this is logged, it's just used to give information that might be of use.
	foreach ($context['current_versions'] as $variable => $version)
		add_js('
	smfSupportVersions.', $variable, ' = "', $version['version'], '";');

	// Now we just have to include the script and wait ;).
	add_js_file(array(
		$scripturl . '?action=viewsmfile;filename=current-version.js',
		$scripturl . '?action=viewsmfile;filename=latest-news.js',
		$scripturl . '?action=viewsmfile;filename=latest-support.js'
	), true);

	// This sets the latest support stuff.
	add_js('
	if (window.smfLatestSupport)
		$("#latestSupport").html(window.smfLatestSupport);

	if (window.smfVersion)
	{
		$("#smfVersion").html(window.smfVersion);

		var yourVer = $("#yourVersion"), currentVersion = yourVer.text();
		if (currentVersion != window.smfVersion)
			yourVer.wrap(\'<span class="alert"></span>\');
	}');
}

// Displays information about file versions installed, and compares them to current version.
function template_view_versions()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3>
				', $txt['admin_version_check'], '
			</h3>
		</div>
		<div class="information">', $txt['version_check_desc'], '</div>
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg left">
						<th scope="col" class="first_th w50">
							<strong>', $txt['admin_smffile'], '</strong>
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

	// The current version of the core SMF package.
	echo '
					<tr>
						<td class="windowbg">
							', $txt['admin_smfpackage'], '
						</td>
						<td class="windowbg">
							<em id="yourSMF">', $context['forum_version'], '</em>
						</td>
						<td class="windowbg">
							<em id="currentSMF">??</em>
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

	echo '
		</div>
		<br class="clear" />';

	/* Below is the hefty javascript for this. Upon opening the page it checks the current file versions with ones
	   held at simplemachines.org and works out if they are up to date.  If they aren't it colors that files number
	   red.  It also contains the function, swapOption, that toggles showing the detailed information for each of the
	   file categories. (sources, languages, and templates.) */
	add_js_file($scripturl . '?action=viewsmfile;filename=detailed-version.js', true);
	add_js_file('scripts/admin.js');

	add_js('
	var oViewVersions = new smf_ViewVersions({
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
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// First section is for adding/removing words from the censored list.
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=postsettings;sa=censor" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3>
					', $txt['admin_censored_words'], '
				</h3>
			</div>
			<div class="windowbg2 wrc">
				<p>', $txt['admin_censored_where'], '</p>';

	// Show text boxes for censoring [bad   ] => [good  ].
	foreach ($context['censored_words'] as $vulgar => $proper)
		echo '
				<div style="margin-top: 1ex;"><input type="text" name="censor_vulgar[]" value="', $vulgar, '" size="20" /> => <input type="text" name="censor_proper[]" value="', $proper, '" size="20" /></div>';

	// Now provide a way to censor more words.
	echo '
				<noscript>
					<div style="margin-top: 1ex;"><input type="text" name="censor_vulgar[]" size="20" class="input_text" /> => <input type="text" name="censor_proper[]" size="20" class="input_text" /></div>
				</noscript>
				<div id="moreCensoredWords"></div><div style="margin-top: 1ex; display: none;" id="moreCensoredWords_link"><a href="#" onclick="addNewWord(); return false;">', $txt['censor_clickadd'], '</a></div>';

	add_js('
	$("#moreCensoredWords_link").show();

	function addNewWord()
	{
		$("#moreCensoredWords").append(\'<div style="margin-top: 1ex;"><input type="text" name="censor_vulgar[]" size="20" class="input_text" /> => <input type="text" name="censor_proper[]" size="20" class="input_text" /><\' + \'/div>\');
	}');

	echo '
				<hr style="width: 100%; height: 1px" class="hrcolor" />
				<dl class="settings">
					<dt>
						<strong><label for="censorWholeWord_check">', $txt['censor_whole_words'], ':</label></strong>
					</dt>
					<dd>
						<input type="checkbox" name="censorWholeWord" value="1" id="censorWholeWord_check"', empty($modSettings['censorWholeWord']) ? '' : ' checked="checked"', ' class="input_check" />
					</dd>
					<dt>
						<strong><label for="censorIgnoreCase_check">', $txt['censor_case'], ':</label></strong>
					</dt>
					<dd>
						<input type="checkbox" name="censorIgnoreCase" value="1" id="censorIgnoreCase_check"', empty($modSettings['censorIgnoreCase']) ? '' : ' checked="checked"', ' class="input_check" />
					</dd>
				</dl>
				<input type="submit" name="save_censor" value="', $txt['save'], '" class="button_submit" />
			</div>';

	// This table lets you test out your filters by typing in rude words and seeing what comes out.
	echo '
			<div class="cat_bar">
				<h3>
					', $txt['censor_test'], '
				</h3>
			</div>
			<div class="windowbg wrc">
				<p class="centertext">
					<input type="text" name="censortest" value="', empty($context['censor_test']) ? '' : $context['censor_test'], '" class="input_text" />
					<input type="submit" value="', $txt['censor_test_save'], '" class="button_submit" />
				</p>
			</div>

			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';
}

// Maintenance is a lovely thing, isn't it?
function template_not_done()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3>
				', $txt['not_done_title'], '
			</h3>
		</div>
		<div class="windowbg wrc">
			', $txt['not_done_reason'];

	if (!empty($context['continue_percent']))
		echo '
			<div style="padding-left: 20%; padding-right: 20%; margin-top: 1ex;">
				<div style="font-size: 8pt; height: 12pt; border: 1px solid black; background-color: white; padding: 1px; position: relative;">
					<div style="padding-top: ', $context['browser']['is_webkit'] ? '2pt' : '1pt', '; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold;">', $context['continue_percent'], '%</div>
					<div style="width: ', $context['continue_percent'], '%; height: 12pt; z-index: 1; background-color: red;">&nbsp;</div>
				</div>
			</div>';

	if (!empty($context['substep_enabled']))
		echo '
			<div style="padding-left: 20%; padding-right: 20%; margin-top: 1ex;">
				<span class="smalltext">', $context['substep_title'], '</span>
				<div style="font-size: 8pt; height: 12pt; border: 1px solid black; background-color: white; padding: 1px; position: relative;">
					<div style="padding-top: ', $context['browser']['is_webkit'] ? '2pt' : '1pt', '; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold;">', $context['substep_continue_percent'], '%</div>
					<div style="width: ', $context['substep_continue_percent'], '%; height: 12pt; z-index: 1; background-color: blue;">&nbsp;</div>
				</div>
			</div>';

	echo '
			<form action="', $scripturl, $context['continue_get_data'], '" method="post" accept-charset="UTF-8" style="margin: 0;" name="autoSubmit" id="autoSubmit">
				<div style="margin: 1ex; text-align: right;"><input type="submit" name="cont" value="', westr::htmlspecialchars($txt['not_done_continue']), '" class="button_submit" /></div>
				', $context['continue_post_data'], '
			</form>
		</div>
	</div>
	<br class="clear" />';

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

		setTimeout("doAutoSubmit();", 1000);
	}');
}

// Template for showing settings (of any kind, really!)
function template_show_settings()
{
	global $context, $txt, $settings, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $context['post_url'], '" method="post" accept-charset="UTF-8"', !empty($context['force_form_onsubmit']) ? ' onsubmit="' . $context['force_form_onsubmit'] . '"' : '', '>';

	// Is there a custom title?
	if (isset($context['settings_title']))
		echo '
			<div class="cat_bar">
				<h3>
					', $context['settings_title'], '
				</h3>
			</div>';

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

			<div class="cat_bar settings_cat">
				<h3', !empty($config_var['class']) ? ' class="' . $config_var['class'] . '"' : '', !empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '"' : '', '>', ($config_var['help'] ? '
					<a href="' . $scripturl . '?action=helpadmin;help=' . $config_var['help'] . '" onclick="return reqWin(this);" class="help"><img src="' . $settings['images_url'] . '/helptopics.gif" alt="' . $txt['help'] . '" /></a>' : ''), '
					', $config_var['label'], '
				</h3>
			</div>';

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
					<dd', $config_var['type'] == 'warning' ? ' class="alert"' : '', (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : ''), '>
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
				$disabled = !empty($config_var['disabled']) ? ' disabled="disabled"' : '';
				$subtext = !empty($config_var['subtext']) ? '<dfn> ' . $config_var['subtext'] . '</dfn>' : '';

				// Show the [?] button.
				if ($config_var['help'])
					echo '
						<a id="setting_', $config_var['name'], '" href="', $scripturl, '?action=helpadmin;help=', $config_var['help'], '" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" /></a><span', ($config_var['disabled'] ? ' style="color: #777777;"' : ($config_var['invalid'] ? ' class="error"' : '')), '>', $config_var['type'] == 'var_message' ? $config_var['label'] : '<label for="' . $config_var['name'] . '">' . $config_var['label'] . '</label>', $subtext, ($config_var['type'] == 'password' ? '<br /><em>' . $txt['admin_confirm_password'] . '</em>' : ''), '</span>
					</dt>';
				else
					echo '
						<a id="setting_', $config_var['name'], '"></a> <span', ($config_var['disabled'] ? ' style="color: #777777;"' : ($config_var['invalid'] ? ' class="error"' : '')), '>', $config_var['type'] == 'var_message' ? $config_var['label'] : '<label for="' . $config_var['name'] . '">' . $config_var['label'] . '</label>', $subtext, ($config_var['type'] == 'password' ? '<br /><em>' . $txt['admin_confirm_password'] . '</em>' : ''), '</span>
					</dt>';

				echo '
					<dd', (!empty($config_var['force_div_id']) ? ' id="' . $config_var['force_div_id'] . '_dd"' : ''), '>', $config_var['preinput'];

				// Show a check box.
				if ($config_var['type'] == 'check')
					echo '
						<input type="checkbox"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '"', ($config_var['value'] ? ' checked="checked"' : ''), ' value="1" class="input_check" />';
				// Escape (via htmlspecialchars.) the text box.
				elseif ($config_var['type'] == 'password')
					echo '
						<input type="password"', $disabled, $javascript, ' name="', $config_var['name'], '[0]"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' value="*#fakepass#*" onfocus="this.value = \'\'; this.form.', $config_var['name'], '.disabled = false;" class="input_password" /><br />
						<input type="password" disabled="disabled" id="', $config_var['name'], '" name="', $config_var['name'], '[1]"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' class="input_password" />';
				// Show a selection box.
				elseif ($config_var['type'] == 'select')
				{
					echo '
						<select name="', $config_var['name'], '" id="', $config_var['name'], '" ', $javascript, $disabled, (!empty($config_var['multiple']) ? ' multiple="multiple"' : ''), '>';
					foreach ($config_var['data'] as $option)
						echo '
							<option value="', $option[0], '"', (($option[0] == $config_var['value'] || (!empty($config_var['multiple']) && in_array($option[0], $config_var['value']))) ? ' selected="selected"' : ''), '>', $option[1], '</option>';
					echo '
						</select>';
				}
				// Text area?
				elseif ($config_var['type'] == 'large_text')
					echo '
						<textarea rows="', ($config_var['size'] ? $config_var['size'] : 4), '" cols="30" ', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '">', $config_var['value'], '</textarea>';
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
									<input type="checkbox" name="', $config_var['name'], '_enabledTags[]" id="tag_', $config_var['name'], '_', $bbcTag['tag'], '" value="', $bbcTag['tag'], '"', !in_array($bbcTag['tag'], $context['bbc_sections'][$config_var['name']]['disabled']) ? ' checked="checked"' : '', ' class="input_check" /> <label for="tag_', $config_var['name'], '_', $bbcTag['tag'], '">', $bbcTag['tag'], '</label>', $bbcTag['show_help'] ? ' (<a href="' . $scripturl . '?action=helpadmin;help=tag_' . $bbcTag['tag'] . '" onclick="return reqWin(this);">?</a>)' : '', '
								</li>';
					}
					echo '
							</ul>
							<input type="checkbox" id="select_all" onclick="invertAll(this, this.form, \'', $config_var['name'], '_enabledTags\');"', $context['bbc_sections'][$config_var['name']]['all_selected'] ? ' checked="checked"' : '', ' class="input_check" /> <label for="select_all"><em>', $txt['bbcTagsToUse_select_all'], '</em></label>
						</fieldset>';
				}
				// A simple message?
				elseif ($config_var['type'] == 'var_message')
					echo '
						<div', !empty($config_var['name']) ? ' id="' . $config_var['name'] . '"' : '', '>', $config_var['var_message'], '</div>';
				// Assume it must be a text box.
				else
					echo '
						<input type="text"', $javascript, $disabled, ' name="', $config_var['name'], '" id="', $config_var['name'], '" value="', $config_var['value'], '"', ($config_var['size'] ? ' size="' . $config_var['size'] . '"' : ''), ' class="input_text" />';

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
				<hr class="hrcolor" />
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
				<hr class="hrcolor" />
				<div class="righttext">
					<input type="submit" value="', $txt['save'], '"', (!empty($context['save_disabled']) ? ' disabled="disabled"' : ''), (!empty($context['settings_save_onclick']) ? ' onclick="' . $context['settings_save_onclick'] . '"' : ''), ' class="button_submit" />
				</div>';

	if ($is_open)
		echo '
			</div>';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';
}

// Template for showing custom profile fields.
function template_show_custom_profile()
{
	global $context, $txt, $settings, $scripturl;

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
	global $context, $txt, $settings, $scripturl;

	// All the javascript for this page - quite a bit!
	add_js_inline('
	function updateInputBoxes()
	{
		var curType = document.getElementById("field_type").value;
		var privStatus = document.getElementById("private").value;
		document.getElementById("max_length_dt").style.display = curType == "text" || curType == "textarea" ? "" : "none";
		document.getElementById("max_length_dd").style.display = curType == "text" || curType == "textarea" ? "" : "none";
		document.getElementById("dimension_dt").style.display = curType == "textarea" ? "" : "none";
		document.getElementById("dimension_dd").style.display = curType == "textarea" ? "" : "none";
		document.getElementById("bbc_dt").style.display = curType == "text" || curType == "textarea" ? "" : "none";
		document.getElementById("bbc_dd").style.display = curType == "text" || curType == "textarea" ? "" : "none";
		document.getElementById("options_dt").style.display = curType == "select" || curType == "radio" ? "" : "none";
		document.getElementById("options_dd").style.display = curType == "select" || curType == "radio" ? "" : "none";
		document.getElementById("default_dt").style.display = curType == "check" ? "" : "none";
		document.getElementById("default_dd").style.display = curType == "check" ? "" : "none";
		document.getElementById("mask_dt").style.display = curType == "text" ? "" : "none";
		document.getElementById("mask").style.display = curType == "text" ? "" : "none";
		document.getElementById("can_search_dt").style.display = curType == "text" || curType == "textarea" ? "" : "none";
		document.getElementById("can_search_dd").style.display = curType == "text" || curType == "textarea" ? "" : "none";
		document.getElementById("regex_div").style.display = curType == "text" && document.getElementById("mask").value == "regex" ? "" : "none";
		document.getElementById("display").disabled = false;

		// Cannot show this on the topic
		if (curType == "textarea" || privStatus >= 2)
		{
			document.getElementById("display").checked = false;
			document.getElementById("display").disabled = true;
		}
	}
	updateInputBoxes();');

	add_js('
	var startOptID = ', count($context['field']['options']), ';
	function addOption()
	{
		$("#addopt").append(\'<br /><input type="radio" name="default_select" value="\' + startOptID + \'" id="\' + startOptID + \'" class="input_radio" /><input type="text" name="select_option[\' + startOptID + \']" value="" class="input_text" />\');
		startOptID++;
	}');

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=featuresettings;sa=profileedit;fid=', $context['fid'], ';', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3>
					', $context['page_title'], '
				</h3>
			</div>
			<div class="windowbg wrc">
				<fieldset>
					<legend>', $txt['custom_edit_general'], '</legend>

					<dl class="settings">
						<dt>
							<strong>', $txt['custom_edit_name'], ':</strong>
						</dt>
						<dd>
							<input type="text" name="field_name" value="', $context['field']['name'], '" size="20" maxlength="40" class="input_text" />
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
								<option value="none"', $context['field']['profile_area'] == 'none' ? ' selected="selected"' : '', '>', $txt['custom_edit_profile_none'], '</option>
								<option value="account"', $context['field']['profile_area'] == 'account' ? ' selected="selected"' : '', '>', $txt['account'], '</option>
								<option value="forumprofile"', $context['field']['profile_area'] == 'forumprofile' ? ' selected="selected"' : '', '>', $txt['forumprofile'], '</option>
								<option value="theme"', $context['field']['profile_area'] == 'theme' ? ' selected="selected"' : '', '>', $txt['theme'], '</option>
							</select>
						</dd>
						<dt>
							<strong>', $txt['custom_edit_registration'], ':</strong>
						</dt>
						<dd>
							<select name="reg" id="reg">
								<option value="0"', $context['field']['reg'] == 0 ? ' selected="selected"' : '', '>', $txt['custom_edit_registration_disable'], '</option>
								<option value="1"', $context['field']['reg'] == 1 ? ' selected="selected"' : '', '>', $txt['custom_edit_registration_allow'], '</option>
								<option value="2"', $context['field']['reg'] == 2 ? ' selected="selected"' : '', '>', $txt['custom_edit_registration_require'], '</option>
							</select>
						</dd>
						<dt>
							<strong>', $txt['custom_edit_display'], ':</strong>
						</dt>
						<dd>
							<input type="checkbox" name="display" id="display"', $context['field']['display'] ? ' checked="checked"' : '', ' class="input_check" />
						</dd>

						<dt>
							<strong>', $txt['custom_edit_placement'], ':</strong>
						</dt>
						<dd>
							<select name="placement" id="placement">
								<option value="0"', $context['field']['placement'] == '0' ? ' selected="selected"' : '', '>', $txt['custom_edit_placement_standard'], '</option>
								<option value="1"', $context['field']['placement'] == '1' ? ' selected="selected"' : '', '>', $txt['custom_edit_placement_withicons'], '</option>
								<option value="2"', $context['field']['placement'] == '2' ? ' selected="selected"' : '', '>', $txt['custom_edit_placement_abovesignature'], '</option>
							</select>
						</dd>
						<dt>
							<a id="field_show_enclosed" href="', $scripturl, '?action=helpadmin;help=field_show_enclosed" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" class="top" /></a>
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
								<option value="text"', $context['field']['type'] == 'text' ? ' selected="selected"' : '', '>', $txt['custom_profile_type_text'], '</option>
								<option value="textarea"', $context['field']['type'] == 'textarea' ? ' selected="selected"' : '', '>', $txt['custom_profile_type_textarea'], '</option>
								<option value="select"', $context['field']['type'] == 'select' ? ' selected="selected"' : '', '>', $txt['custom_profile_type_select'], '</option>
								<option value="radio"', $context['field']['type'] == 'radio' ? ' selected="selected"' : '', '>', $txt['custom_profile_type_radio'], '</option>
								<option value="check"', $context['field']['type'] == 'check' ? ' selected="selected"' : '', '>', $txt['custom_profile_type_check'], '</option>
							</select>
						</dd>
						<dt id="max_length_dt">
							<strong>', $txt['custom_edit_max_length'], ':</strong>
							<dfn>', $txt['custom_edit_max_length_desc'], '</dfn>
						</dt>
						<dd id="max_length_dd">
							<input type="text" name="max_length" value="', $context['field']['max_length'], '" size="7" maxlength="6" class="input_text" />
						</dd>
						<dt id="dimension_dt">
							<strong>', $txt['custom_edit_dimension'], ':</strong>
						</dt>
						<dd id="dimension_dd">
							<strong>', $txt['custom_edit_dimension_row'], ':</strong> <input type="text" name="rows" value="', $context['field']['rows'], '" size="5" maxlength="3" class="input_text" />
							<strong>', $txt['custom_edit_dimension_col'], ':</strong> <input type="text" name="cols" value="', $context['field']['cols'], '" size="5" maxlength="3" class="input_text" />
						</dd>
						<dt id="bbc_dt">
							<strong>', $txt['custom_edit_bbc'], '</strong>
						</dt>
						<dd id="bbc_dd">
							<input type="checkbox" name="bbc"', $context['field']['bbc'] ? ' checked="checked"' : '', ' class="input_check" />
						</dd>
						<dt id="options_dt">
							<a href="', $scripturl, '?action=helpadmin;help=customoptions" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" /></a>
							<strong>', $txt['custom_edit_options'], ':</strong>
							<dfn>', $txt['custom_edit_options_desc'], '</dfn>
						</dt>
						<dd id="options_dd">
							<div>';

	foreach ($context['field']['options'] as $k => $option)
		echo '
								', $k == 0 ? '' : '<br />', '<input type="radio" name="default_select" value="', $k, '"', $context['field']['default_select'] == $option ? ' checked="checked"' : '', ' class="input_radio" /><input type="text" name="select_option[', $k, ']" value="', $option, '" class="input_text" />';

	echo '
								<span id="addopt"></span>
								[<a href="" onclick="addOption(); return false;">', $txt['custom_edit_options_more'], '</a>]
							</div>
						</dd>
						<dt id="default_dt">
							<strong>', $txt['custom_edit_default'], ':</strong>
						</dt>
						<dd id="default_dd">
							<input type="checkbox" name="default_check"', $context['field']['default_check'] ? ' checked="checked"' : '', ' class="input_check" />
						</dd>
					</dl>
				</fieldset>
				<fieldset>
					<legend>', $txt['custom_edit_advanced'], '</legend>
					<dl class="settings">
						<dt id="mask_dt">
							<a id="custom_mask" href="', $scripturl, '?action=helpadmin;help=custom_mask" onclick="return reqWin(this);" class="help"><img src="', $settings['images_url'], '/helptopics.gif" alt="', $txt['help'], '" class="top" /></a>
							<strong>', $txt['custom_edit_mask'], ':</strong>
							<dfn>', $txt['custom_edit_mask_desc'], '</dfn>
						</dt>
						<dd>
							<select name="mask" id="mask" onchange="updateInputBoxes();">
								<option value="nohtml"', $context['field']['mask'] == 'nohtml' ? ' selected="selected"' : '', '>', $txt['custom_edit_mask_nohtml'], '</option>
								<option value="email"', $context['field']['mask'] == 'email' ? ' selected="selected"' : '', '>', $txt['custom_edit_mask_email'], '</option>
								<option value="number"', $context['field']['mask'] == 'number' ? ' selected="selected"' : '', '>', $txt['custom_edit_mask_number'], '</option>
								<option value="regex"', substr($context['field']['mask'], 0, 5) == 'regex' ? ' selected="selected"' : '', '>', $txt['custom_edit_mask_regex'], '</option>
							</select>
							<br />
							<span id="regex_div">
								<input type="text" name="regex" value="', $context['field']['regex'], '" size="30" class="input_text" />
							</span>
						</dd>
						<dt>
							<strong>', $txt['custom_edit_privacy'], ':</strong>
							<dfn>', $txt['custom_edit_privacy_desc'], '</dfn>
						</dt>
						<dd>
							<select name="private" id="private" onchange="updateInputBoxes();" style="width: 100%">
								<option value="0"', $context['field']['private'] == 0 ? ' selected="selected"' : '', '>', $txt['custom_edit_privacy_all'], '</option>
								<option value="1"', $context['field']['private'] == 1 ? ' selected="selected"' : '', '>', $txt['custom_edit_privacy_see'], '</option>
								<option value="2"', $context['field']['private'] == 2 ? ' selected="selected"' : '', '>', $txt['custom_edit_privacy_owner'], '</option>
								<option value="3"', $context['field']['private'] == 3 ? ' selected="selected"' : '', '>', $txt['custom_edit_privacy_none'], '</option>
							</select>
						</dd>
						<dt id="can_search_dt">
							<strong>', $txt['custom_edit_can_search'], ':</strong>
							<dfn>', $txt['custom_edit_can_search_desc'], '</dfn>
						</dt>
						<dd id="can_search_dd">
							<input type="checkbox" name="can_search"', $context['field']['can_search'] ? ' checked="checked"' : '', ' class="input_check" />
						</dd>
						<dt>
							<strong>', $txt['custom_edit_active'], ':</strong>
							<dfn>', $txt['custom_edit_active_desc'], '</dfn>
						</dt>
						<dd>
							<input type="checkbox" name="active"', $context['field']['active'] ? ' checked="checked"' : '', ' class="input_check" />
						</dd>
					</dl>
				</fieldset>
				<div class="righttext">
					<input type="submit" name="save" value="', $txt['save'], '" class="button_submit" />';

	if ($context['fid'])
		echo '
					<input type="submit" name="delete" value="', $txt['delete'], '" onclick="return confirm(', JavaScriptEscape($txt['custom_edit_delete_sure']), ');" class="button_submit" />';

	echo '
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		</form>
	</div>
	<br class="clear" />';
}

// Results page for an admin search.
function template_admin_search_results()
{
	global $context, $txt, $settings, $options, $scripturl;

	echo '
	<we:title>
		<div class="floatright">
			<form action="', $scripturl, '?action=admin;area=search" method="post" accept-charset="UTF-8" style="font-weight: normal; display: inline;" id="quick_search">
				<input type="text" name="search_term" value="', $context['search_term'], '" class="input_text" />
				<input type="hidden" name="search_type" value="', $context['search_type'], '" />
				<input type="submit" name="search_go" value="', $txt['admin_search_results_again'], '" class="button_submit" />
			</form>
		</div>
		', $txt['admin_search_results'], '
	</we:title>

	<div class="windowbg wrc">
		', sprintf($txt['admin_search_results_desc'], $context['search_term']);

	if (empty($context['search_results']))
		echo '
		<p class="centertext"><strong>', $txt['admin_search_results_none'], '</strong></p>';

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
	<br class="clear" />';
}

// Turn on and off certain key features.
function template_core_features()
{
	global $context, $txt, $settings, $options, $scripturl;

	$switch_off = JavaScriptEscape($txt['core_settings_switch_off']);
	$switch_on = JavaScriptEscape($txt['core_settings_switch_on']);

	add_js('
	function toggleItem(itemID)
	{
		// Toggle the hidden item.
		var itemValueHandle = $("#feature_" + itemID);
		itemValueHandle.val(itemValueHandle.val() == 1 ? 0 : 1);

		// Change the image, alternative text and the title.
		$("#switch_" + itemID).attr({
			src: \'', $settings['images_url'], '/admin/switch_\' + (itemValueHandle.val() == 1 ? \'on\' : \'off\') + \'.png\',
			alt: itemValueHandle.val() == 1 ? ', $switch_off, ' : ', $switch_on, ',
			title: itemValueHandle.val() == 1 ? ', $switch_off, ' : ', $switch_on, '
		});

		return false;
	}');

	echo '
	<div id="admincenter">';
	if ($context['is_new_install'])
	{
		echo '
			<div class="cat_bar">
				<h3>
					', $txt['core_settings_welcome_msg'], '
				</h3>
			</div>
			<div class="information">
				', $txt['core_settings_welcome_msg_desc'], '
			</div>';
	}

	echo '
		<form action="', $scripturl, '?action=admin;area=corefeatures;" method="post" accept-charset="UTF-8">
			<we:title>
				', $txt['core_settings_title'], '
			</we:title>
			<div style="overflow: hidden">';

	$alternate = 0;
	foreach ($context['features'] as $id => $feature)
	{
		echo '
				<div class="features">
					<div class="windowbg', $alternate < 2 ? '2' : '', ' wrc">
						<img class="features_image" src="', $settings['default_images_url'], '/admin/feature_', $id, '.png" alt="', $feature['title'], '" />
						<div class="features_switch" id="js_feature_', $id, '" style="display: none;">
							<a href="', $scripturl, '?action=admin;area=featuresettings;sa=core;', $context['session_var'], '=', $context['session_id'], ';toggle=', $id, ';state=', $feature['enabled'] ? 0 : 1, '" onclick="return toggleItem(\'', $id, '\');">
								<input type="hidden" name="feature_', $id, '" id="feature_', $id, '" value="', $feature['enabled'] ? 1 : 0, '" /><img src="', $settings['images_url'], '/admin/switch_', $feature['enabled'] ? 'on' : 'off', '.png" id="switch_', $id, '" style="margin-top: 1.3em;" alt="', $txt['core_settings_switch_' . ($feature['enabled'] ? 'off' : 'on')], '" title="', $txt['core_settings_switch_' . ($feature['enabled'] ? 'off' : 'on')], '" />
							</a>
						</div>
						<h4>', ($feature['enabled'] && $feature['url'] ? '<a href="' . $feature['url'] . '">' . $feature['title'] . '</a>' : $feature['title']), '</h4>
						<p>', $feature['desc'], '</p>
						<div id="plain_feature_', $id, '">
							<label for="plain_feature_', $id, '_radio_on"><input type="radio" name="feature_plain_', $id, '" id="plain_feature_', $id, '_radio_on" value="1"', $feature['enabled'] ? ' checked="checked"' : '', ' class="input_radio" />', $txt['core_settings_enabled'], '</label>
							<label for="plain_feature_', $id, '_radio_off"><input type="radio" name="feature_plain_', $id, '" id="plain_feature_', $id, '_radio_off" value="0"', !$feature['enabled'] ? ' checked="checked"' : '', ' class="input_radio" />', $txt['core_settings_disabled'], '</label>
						</div>
					</div>
				</div>';

		$alternate = ($alternate + 1) % 4;
	}

	echo '
			</div>
			<div class="righttext">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="hidden" value="0" name="js_worked" id="js_worked" />
				<input type="submit" value="', $txt['save'], '" name="save" class="button_submit" />
			</div>
		</form>
	</div>
	<br class="clear" />';

	// Turn on the pretty javascript if we can!
	add_js_inline('
	document.getElementById(\'js_worked\').value = "1";');

	foreach ($context['features'] as $id => $feature)
		add_js_inline('
	document.getElementById(\'js_feature_', $id, '\').style.display = "";
	document.getElementById(\'plain_feature_', $id, '\').style.display = "none";');
}

// Add a new language
function template_add_language()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=languages;sa=add;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3>
					', $txt['add_language'], '
				</h3>
			</div>
			<div class="windowbg wrc">
				<fieldset>
					<legend>', $txt['add_language_smf'], '</legend>
					<label class="smalltext">', $txt['add_language_smf_browse'], '</label>
					<input type="text" name="smf_add" size="40" value="', !empty($context['smf_search_term']) ? $context['smf_search_term'] : '', '" class="input_text" />';

	if (!empty($context['smf_error']))
		echo '
					<div class="smalltext error">', $txt['add_language_error_' . $context['smf_error']], '</div>';

	echo '
				</fieldset>
				<div class="righttext">
					', $context['browser']['is_ie'] ? '<input type="text" name="ie_fix" style="display: none;" class="input_text" /> ' : '', '
					<input type="submit" name="smf_add_sub" value="', $txt['search'], '" class="button_submit" />
				</div>
			</div>';

	// Had some results?
	if (!empty($context['smf_languages']))
	{
		echo '
			<div class="information">', $txt['add_language_smf_found'], '</div>

			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg">
						<th class="first_th" scope="col">', $txt['name'], '</th>
						<th scope="col">', $txt['add_language_smf_desc'], '</th>
						<th scope="col">', $txt['add_language_smf_version'], '</th>
						<th scope="col">', $txt['add_language_smf_utf8'], '</th>
						<th class="first_th" scope="col">', $txt['add_language_smf_install'], '</th>
					</tr>
				</thead>
				<tbody>';

		foreach ($context['smf_languages'] as $language)
			echo '
					<tr class="windowbg2 left">
						<td>', $language['name'], '</td>
						<td>', $language['description'], '</td>
						<td>', $language['version'], '</td>
						<td class="center">', $language['utf8'] ? $txt['yes'] : $txt['no'], '</td>
						<td><a href="', $language['link'], '">', $txt['add_language_smf_install'], '</a></td>
					</tr>';

		echo '
				</tbody>
			</table>';
	}

	echo '
		</form>
	</div>
	<br class="clear" />';
}

// Download a new language file?
function template_download_language()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Actually finished?
	if (!empty($context['install_complete']))
	{
		echo '
	<div id="admincenter">
		<div class="cat_bar">
			<h3>
				', $txt['languages_download_complete'], '
			</h3>
		</div>
		<div class="windowbg wrc">
			', $context['install_complete'], '
		</div>
	</div>
	<br class="clear" />';
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
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=languages;sa=downloadlang;did=', $context['download_id'], ';', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3>
					', $txt['languages_download'], '
				</h3>
			</div>
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
			<br />
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

	foreach ($context['files']['images'] as $theme => $group)
	{
		$count = 0;
		echo '
				<tr class="titlebg">
					<td colspan="4">
						<img src="', $settings['images_url'], '/sort_down.gif" id="toggle_image_', $theme, '" alt="*" />&nbsp;', isset($context['theme_names'][$theme]) ? $context['theme_names'][$theme] : $theme, '
					</td>
				</tr>';

		$alternate = false;
		foreach ($group as $file)
		{
			echo '
				<tr class="windowbg', $alternate ? '2' : '', '" id="', $theme, '-', $count++, '">
					<td>
						<strong>', $file['name'], '</strong>
						<div class="smalltext">', $txt['languages_download_dest'], ': ', $file['destination'], '</div>
					</td>
					<td>
						<span style="color: ', ($file['writable'] ? 'green' : 'red'), ';">', ($file['writable'] ? $txt['yes'] : $txt['no']), '</span>
					</td>
					<td>
						', $file['exists'] ? ($file['exists'] == 'same' ? $txt['languages_download_exists_same'] : $txt['languages_download_exists_different']) : $txt['no'], '
					</td>
					<td>
						<input type="checkbox" name="copy_file[]" value="', $file['generaldest'], '"', ($file['default_copy'] ? ' checked="checked"' : ''), ' class="input_check" />
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
			<div class="cat_bar">
				<h3>
					', $txt['package_ftp_necessary'], '
				</h3>
			</div>
			<div class="windowbg wrc">
				<p>', $txt['package_ftp_why'], '</p>
				<dl class="settings">
					<dt
						<label for="ftp_server">', $txt['package_ftp_server'], ':</label>
					</dt>
					<dd>
						<div class="floatright" style="margin-right: 1px;"><label for="ftp_port" style="padding-top: 2px; padding-right: 2ex;">', $txt['package_ftp_port'], ':&nbsp;</label> <input type="text" size="3" name="ftp_port" id="ftp_port" value="', isset($context['package_ftp']['port']) ? $context['package_ftp']['port'] : (isset($modSettings['package_port']) ? $modSettings['package_port'] : '21'), '" class="input_text" /></div>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="', isset($context['package_ftp']['server']) ? $context['package_ftp']['server'] : (isset($modSettings['package_server']) ? $modSettings['package_server'] : 'localhost'), '" style="width: 70%;" class="input_text" />
					</dd>

					<dt>
						<label for="ftp_username">', $txt['package_ftp_username'], ':</label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_username" id="ftp_username" value="', isset($context['package_ftp']['username']) ? $context['package_ftp']['username'] : (isset($modSettings['package_username']) ? $modSettings['package_username'] : ''), '" style="width: 99%;" class="input_text" />
					</dd>

					<dt>
						<label for="ftp_password">', $txt['package_ftp_password'], ':</label>
					</dt>
					<dd>
						<input type="password" size="50" name="ftp_password" id="ftp_password" style="width: 99%;" class="input_text" />
					</dd>

					<dt>
						<label for="ftp_path">', $txt['package_ftp_path'], ':</label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_path" id="ftp_path" value="', $context['package_ftp']['path'], '" style="width: 99%;" class="input_text" />
					</dd>
				</dl>
			</div>';
	}

	// Install?
	echo '
			<div class="righttext padding">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
				<input type="submit" name="do_install" value="', $txt['add_language_smf_install'], '" class="button_submit" />
			</div>
		</form>
	</div>
	<br class="clear" />';

	// The javascript for expanding and collapsing sections.
	// Each theme gets its own handler.
	foreach ($context['files']['images'] as $theme => $group)
	{
		$count = 0;

		add_js('
	var oTogglePanel_', $theme, ' = new smc_Toggle({
		bToggleEnabled: true,
		bCurrentlyCollapsed: true,
		aSwappableContainers: [');

		foreach ($group as $file)
			add_js('
			', JavaScriptEscape($theme . '-' . $count++), ',');

		add_js('
			null
		],
		aSwapImages: [
			{
				sId: \'toggle_image_', $theme, '\',
				srcExpanded: smf_images_url + \'/sort_down.gif\',
				altExpanded: \'*\',
				srcCollapsed: smf_images_url + \'/selected.gif\',
				altCollapsed: \'*\'
			}
		]
	});');
	}
}

// Edit some language entries?
function template_modify_language_entries()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=languages;sa=editlang;lid=', $context['lang_id'], '" method="post" accept-charset="UTF-8">
			<div class="cat_bar">
				<h3>
					', $txt['edit_languages'], '
				</h3>
			</div>';

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
						<input type="text" name="locale" size="20" value="', $context['primary_settings']['locale'], '"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' class="input_text" />
					</dd>
					<dt>
						', $txt['languages_dictionary'], ':
					</dt>
					<dd>
						<input type="text" name="dictionary" size="20" value="', $context['primary_settings']['dictionary'], '"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' class="input_text" />
					</dd>
					<dt>
						', $txt['languages_spelling'], ':
					</dt>
					<dd>
						<input type="text" name="spelling" size="20" value="', $context['primary_settings']['spelling'], '"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' class="input_text" />
					</dd>
					<dt>
						', $txt['languages_rtl'], ':
					</dt>
					<dd>
						<input type="checkbox" name="rtl"', $context['primary_settings']['rtl'] ? ' checked="checked"' : '', ' class="input_check"', (empty($context['file_entries']) ? '' : ' disabled="disabled"'), ' />
					</dd>
				</dl>
				</fieldset>
				<div class="righttext">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="submit" name="save_main" value="', $txt['save'], '"', $context['lang_file_not_writable_message'] || !empty($context['file_entries']) ? ' disabled="disabled"' : '', ' class="button_submit" />';

	// English can't be deleted.
	if ($context['lang_id'] != 'english')
		echo '
					<input type="submit" name="delete_main" value="', $txt['delete'], '"', $context['lang_file_not_writable_message'] || !empty($context['file_entries']) ? ' disabled="disabled"' : '', ' onclick="confirm(', JavaScriptEscape($txt['languages_delete_confirm']), ');" class="button_submit" />';

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

	foreach ($context['possible_files'] as $id_theme => $theme)
	{
		echo '
						<option value="-1">', $theme['name'], '</option>';

		foreach ($theme['files'] as $file)
			echo '
						<option value="', $id_theme, '+', $file['id'], '"', $file['selected'] ? ' selected="selected"' : '', '> =&gt; ', $file['name'], '</option>';
	}

	echo '
					</select>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="submit" value="', $txt['go'], '" class="button_submit" />
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
						<input type="hidden" name="comp[', $cached['key'], ']" value="', $cached['value'], '" />
						<textarea name="entry[', $cached['key'], ']" cols="40" rows="', $cached['rows'] < 2 ? 2 : $cached['rows'], '" style="width: 96%;">', $cached['value'], '</textarea>
					</dt>
					<dd>
						<input type="hidden" name="comp[', $entry['key'], ']" value="', $entry['value'], '" />
						<textarea name="entry[', $entry['key'], ']" cols="40" rows="', $entry['rows'] < 2 ? 2 : $entry['rows'], '" style="width: 96%;">', $entry['value'], '</textarea>
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
						<input type="hidden" name="comp[', $cached['key'], ']" value="', $cached['value'], '" />
						<textarea name="entry[', $cached['key'], ']" cols="40" rows="2" style="width: 96%;">', $cached['value'], '</textarea>
					</dt>
					<dd>
					</dd>';

		echo '
				</dl>
				<input type="submit" name="save_entries" value="', $txt['save'], '"', !empty($context['entries_not_writable_message']) ? ' disabled="disabled"' : '', ' class="button_submit" />';

		echo '
			</div>';
	}
	echo '
		</form>
	</div>
	<br class="clear" />';
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
				<input type="text" name="question[', $data['id'], ']" value="', $data['question'], '" size="50" class="input_text verification_question" />
			</dt>
			<dd>
				<input type="text" name="answer[', $data['id'], ']" value="', $data['answer'], '" size="50" class="input_text verification_answer" />
			</dd>';

	// Some blank ones.
	for ($count = 0; $count < 3; $count++)
		echo '
			<dt>
				<input type="text" name="question[]" size="50" class="input_text verification_question" />
			</dt>
			<dd>
				<input type="text" name="answer[]" size="50" class="input_text verification_answer" />
			</dd>';

	echo '
		<dt id="add_more_question_placeholder" style="display: none;"></dt><dd></dd>
		<dt id="add_more_link_div" style="display: none;">
			<a href="#" onclick="addAnotherQuestion(); return false;">&#171; ', $txt['setup_verification_add_more'], ' &#187;</a>

		</dt><dd></dd>';

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
		newInput.className = "input_text";
		newInput.size = "50";
		newInput.setAttribute("class", "verification_question");
		newDT.appendChild(newInput);

		newDD = document.createElement("dd");

		newInput = createNamedElement("input", "answer[]");
		newInput.type = "text";
		newInput.className = "input_text";
		newInput.size = "50";
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
	<div id="admincenter">
		<div class="cat_bar">
			<h3>',
				$context['error_search'] ? $txt['errors_list'] : $txt['errors_fixing'], '
			</h3>
		</div>
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
					<strong><a href="', $scripturl, '?action=admin;area=repairboards;fixErrors;', $context['session_var'], '=', $context['session_id'], '">', $txt['yes'], '</a> - <a href="', $scripturl, '?action=admin;area=maintain">', $txt['no'], '</a></strong>
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
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="submit" name="recount" id="recount_now" value="', westr::htmlspecialchars($txt['errors_recount_now']), '" />
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
			</div>
		</div>
	</div>
	<br class="clear" />';

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

		setTimeout("doAutoSubmit();", 1000);
	}');
}

// Pretty URLs
function template_pretty_urls()
{
	global $context, $scripturl, $txt;

	if (!empty($context['pretty']['chrome']['menu']))
	{
		echo '
	<div class="cat_bar">
		<h3>', $txt['pretty_urls'], '</h3>
	</div>
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
	<div class="cat_bar">
		<h4>', $txt['pretty_settings'], '</h4>
	</div>
	<div class="windowbg2 wrc">
		<form id="adminsearch" action="', $scripturl, '?action=admin;area=featuresettings;sa=pretty;save" method="post" accept-charset="UTF-8">
			<fieldset>
				<input type="checkbox" name="pretty_enable" id="pretty_enable"', ($context['pretty']['settings']['enable'] ? ' checked="checked"' : ''), ' />
				<label for="pretty_enable">', $txt['pretty_enable'], '</label>
				<br />
				<input type="checkbox" name="pretty_cache" id="pretty_cache"', ($context['pretty']['settings']['cache'] ? ' checked="checked"' : ''), ' />
				<label for="pretty_cache">', $txt['pretty_cache'], '</label>
			</fieldset>';

	// Display the filters
	if (!empty($context['pretty']['filters']))
	{
		echo '
			<fieldset>
				<legend>', $txt['pretty_filters'], '</legend>';

		foreach ($context['pretty']['filters'] as $filter)
			echo '
				<div>
					<input type="checkbox" name="pretty_filter_', $filter['id'], '" id="pretty_filter_', $filter['id'], '"', ($filter['enabled'] ? ' checked="checked"' : ''), ' />
					<label for="pretty_filter_', $filter['id'], '">', $txt['pretty_filter_' . $filter['id']], '</label>
				</div>';

		echo '
			</fieldset>';
	}

	echo '
			<input type="submit" name="save" value="', $txt['pretty_save'], '">
		</form>
	</div>

	<div class="cat_bar">
		<h4>', $txt['pretty_maintenance'], '</h4>
	</div>
	<div class="windowbg wrc">
		<form id="pretty_maintain_reset" action="', $scripturl, '?action=admin;area=featuresettings;sa=pretty;reset" method="post" accept-charset="UTF-8">
			<input type="submit" value="', $txt['pretty_reset'], '">
		</form>

		<form id="pretty_maintain_refill" action="', $scripturl, '?action=admin;area=featuresettings;sa=pretty;refill" method="post" accept-charset="UTF-8">
			<input type="submit" value="', $txt['pretty_refill'], '" style="margin-top: 10px">
		</form>
	</div>';
}

?>