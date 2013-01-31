<?php
/**
 * Wedge
 *
 * Displays the currently available plugins.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_browse()
{
	global $context, $theme, $options, $scripturl, $txt;

	// Showing the filtering.
	$items = array();
	foreach ($context['filter_plugins'] as $k => $v)
		$items[] = $k != $context['current_filter'] ? '<a href="' . $scripturl . '?action=admin;area=plugins;filter=' . $k . '">' . sprintf($txt['plugin_filter_' . $k], $v) . '</a>' : '<strong>' . sprintf($txt['plugin_filter_' . $k], $v) . '</strong>';

	echo '
	<p class="description">', $txt['plugin_filter'], ' ', implode(' | ', $items), '</p>';

	// Nothing to show? Might as well just get gone, then.
	if (empty($context['available_plugins']))
	{
		echo '
	<div class="information">', $txt['no_plugins_found'], '</div>
	<br class="clear">';
		return;
	}

	$use_bg2 = true;
	// Just before printing content, go through and work out what icons we're going to display. Need to do it first though, because we need to know how many icons we're working on.
	$icons = array();
	$max_icons = 0;
	foreach ($context['available_plugins'] as $id => $plugin)
	{
		$icons[$id] = array();

		if ($plugin['enabled'])
		{
			$item = array(
				array(
					'icon' => 'switch_on.png',
					'url' => $scripturl . '?action=admin;area=plugins;sa=disable;plugin=' . $plugin['folder'] . ';' . $context['session_query'],
					'title' => $txt['disable_plugin']
				)
			);

			if (!empty($plugin['acp_url']))
				$item[] = array(
					'icon' => 'plugin_settings.png',
					'url' => $scripturl . '?' . $plugin['acp_url'],
					'title' => $txt['admin_plugin_settings'],
				);

			$icons[$id] = $item;
		}
		else
		{
			$item = array();

			if (empty($plugin['install_errors']))
				$item[0] = array(
					'icon' => 'switch_off.png',
					'url' => $scripturl . '?action=admin;area=plugins;sa=enable;plugin=' . $plugin['folder'] . ';' . $context['session_query'],
					'title' => $txt['enable_plugin'],
				);

			$item[1] = array(
				'icon' => 'plugin_remove.png',
				'url' => $scripturl . '?action=admin;area=plugins;sa=remove;plugin=' . $plugin['folder'],
				'title' => $txt['remove_plugin'],
			);

			$icons[$id] = $item;
		}
		$max_icons = max($max_icons, count($icons[$id]));
	}

	// Print out the content.
	foreach ($context['available_plugins'] as $id => $plugin)
	{
		echo '
	<fieldset class="windowbg', $use_bg2 ? '2' : '', ' wrc">
		<legend>', $plugin['name'], ' ', $plugin['version'], '</legend>';

		for ($i = $max_icons - 1; $i >= 0; $i--)
		{
			if (!isset($icons[$id][$i]))
				echo '
			<div class="plugin_item inline_block floatright">&nbsp;</div>';
			else
				echo '
			<div class="plugin_item inline_block floatright">
				<a href="', $icons[$id][$i]['url'], '">
					<img src="', $theme['images_url'], '/admin/', $icons[$id][$i]['icon'], '"', !empty($icons[$id][$i]['title']) ? ' title="' . $icons[$id][$i]['title'] . '"' : '', '>
				</a>
			</div>';
		}

		// Plugin buttons. They're floated right, so need to be first. Besides which, the floating means they need to be in reverse order :/
		if (!empty($plugin['install_errors']))
			echo '
		<div class="floatright smalltext errorbox plugin_error"><strong>', $txt['install_errors'], '</strong><br>', implode('<br>', $plugin['install_errors']), '</div>';

		// Plugin description
		if (!empty($plugin['description']))
			echo '
		<p>', $plugin['description'], '</p>';

		// Plugin author, including links home.
		echo '
		<div class="plugin_from">', $txt['plugin_written_by'], ': ', $plugin['author'];
		if (!empty($plugin['author_url']))
			echo '
		&nbsp;<a href="', $plugin['author_url'], '" target="_blank"><img src="', $theme['images_url'], '/icons/profile_sm.gif" title="', $txt['plugin_author_url'], '"></a>';

		if (!empty($plugin['website']))
			echo '
		&nbsp;<a href="', $plugin['website'], '" target="_blank"><img src="', $theme['images_url'], '/www.gif" title="', sprintf($txt['plugin_website'], $plugin['name']), '"></a>';

		if (!empty($plugin['author_email']))
			echo '
		&nbsp;<a href="mailto:', $plugin['author_email'], '"><img src="', $theme['images_url'], '/email_sm.gif" title="', $txt['plugin_author_email'], '"></a>';

		echo '</div>';

		// Plugin readmes
		if (!empty($plugin['readmes']))
		{
			echo '
		<div class="smalltext floatleft inline-block">', $txt['plugin_readmes'], ':';

			foreach ($plugin['readmes'] as $readme => $state)
				echo ' &nbsp;<a href="', $scripturl, '?action=admin;area=plugins;sa=readme;plugin=', rawurlencode($plugin['folder']), ';lang=', $readme, '" onclick="return reqWin(this);"><img src="', $theme['theme_url'], '/languages/Flag.', $readme, '.png"></a>';

			echo '
		</div>';
		}

		echo '
	</fieldset>';
	}

	echo '
	<br class="clear">';
}

function template_remove()
{
	global $context, $theme, $options, $txt;

	echo '
	<form action="<URL>?action=admin;area=plugins;sa=remove;plugin=', $_GET['plugin'], ';commit" method="post">
		<div class="windowbg2 wrc">
			<p><strong>', sprintf($txt['remove_plugin_desc'], $context['plugin_name']), '</strong></p>
			<p>', $txt['remove_plugin_blurb'], '</p>
			<fieldset>
				<legend>', $txt['remove_plugin_nodelete'], '</legend>
				', $txt['remove_plugin_nodelete_desc'], '<br>
				<input name="nodelete" type="submit" class="save floatright" value="', $txt['remove_plugin_nodelete'], '">
			</fieldset>
			<br>
			<fieldset>
				<legend>', $txt['remove_plugin_delete'], '</legend>
				', $txt['remove_plugin_delete_desc'], '<br>
				<input name="delete" type="submit" class="delete floatright" value="', $txt['remove_plugin_delete'], '"', !empty($context['requires_maint']) ? ' disabled' : '', '>';
	if (!empty($context['requires_maint']))
		echo '
				<div class="errorbox plugin_error">', $txt['remove_plugin_maint'], '</div>';
	echo '
			</fieldset>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>
	<br class="clear">';
}

function template_add_plugins()
{
	global $context, $txt;

	// First, the download/browse facility, including the 'add repository' button
	echo '
		<we:cat>', $txt['plugins_add_download'], '</we:cat>
		<p class="description">', $txt['plugins_add_download_desc'], '</p>
		<table class="table_grid cs0" style="width: 100%">
			<thead>
				<tr class="catbg">
					<th scope="col" class="first_th" style="text-align: left; width: 55%">', $txt['plugins_repository'], '</th>
					<th scope="col" style="width: 15%">', $txt['plugins_active'], '</th>
					<th scope="col" style="width: 15%"></th>
					<th scope="col" style="width: 15%" class="last_th"></th>
				</tr>
			</thead>
			<tbody>';

	if (empty($context['plugin_repositories']))
	{
		echo '
				<tr class="windowbg">
					<td class="center" colspan="4">', $txt['plugins_no_repos'], '</td>
				</tr>';
	}
	else
	{
		$use_bg2 = false;
		foreach ($context['plugin_repositories'] as $repo_id => $repo)
		{
			echo '
				<tr class="windowbg', $use_bg2 ? '2' : '', '">
					<td>', $repo['name'], $repo['auth'] ? ' <span class="plugin_auth" title="' . $txt['plugins_repo_auth'] . '"></span>' : '', '</td>
					<td class="center">';

			// While it's fairly straightforward here, it may be more complex in future.
			switch ($repo['status'])
			{
				case REPO_ACTIVE:
					echo $txt['yes'];
					break;
				case REPO_INACTIVE:
					echo $txt['no'];
					break;
				case REPO_ERROR:
					echo $txt['plugins_repo_error'], ' <a href="<URL>?action=help;in=error_plugin_repo" onclick="return reqWin(this);" class="help"></a>';
					break;
			}

			echo '</td>
					<td class="center"><a href="<URL>?action=admin;area=plugins;sa=add;browserepo=', $repo_id, '">', $txt['plugins_browse'], '</a></td>
					<td class="center"><a href="<URL>?action=admin;area=plugins;sa=add;editrepo=', $repo_id, '">', $txt['plugins_modify'], '</a></td>
				</tr>';

			$use_bg2 = !$use_bg2;
		}
	}

	echo '
			</tbody>
		</table>
		<form action="<URL>?action=admin;area=plugins;sa=add;editrepo=add" method="post">
			<div class="floatright">
				<div class="additional_row" style="text-align: right"><input type="submit" name="new" value="', $txt['plugins_add_repo'], '" class="new"></div>
			</div>
		</form>
		<br class="clear">';

	// Then the upload form.
	echo '
		<br>
		<we:cat>', $txt['plugins_add_upload'], '</we:cat>
		<p class="description">', $txt['plugins_add_upload_desc'], '</p>
		<div class="windowbg wrc">
			<form action="<URL>?action=admin;area=plugins;sa=add;upload" method="post" accept-charset="UTF-8" enctype="multipart/form-data" style="margin-bottom: 0">
				<dl class="settings">
					<dt>
						<strong>', $txt['plugins_add_upload_file'], '</strong>
					</dt>
					<dd>
						<input type="file" name="plugin">
					</dd>
				</dl>
				<div class="right">
					<input type="submit" value="', $txt['plugins_upload_plugin'], '" class="save">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</form>
		</div>
		<br class="clear">';
}

function template_edit_repo()
{
	global $context, $txt;

	if (!empty($context['tried_to_find']))
		echo '
		<div class="errorbox">', $txt['plugins_edit_invalid'], '</div>';

	echo '
		<we:cat>', $txt['plugins_repo_details'], '</we:cat>
		<p class="description">', $txt['plugins_repo_details_desc'], '</p>
		<form action="<URL>?action=admin;area=plugins;sa=add;editrepo=', $context['repository']['id'], ';save" method="post" accept-charset="UTF-8" enctype="multipart/form-data">
			<div class="windowbg2 wrc">
				<fieldset>
					<legend>', $txt['plugins_repo_details'], '</legend>
					<dl class="settings">
						<dt>', $txt['plugins_repo_name'], '</dt>
						<dd><input type="text" name="name" size="44" value="', $context['repository']['name'], '">
						<dt>', $txt['plugins_repo_address'], '</dt>
						<dd><input type="text" name="url" size="44" value="', $context['repository']['url'], '">
						<dt><a href="<URL>?action=help;in=plugins_repo_active" onclick="return reqWin(this);" class="help"></a> ', $txt['plugins_repo_active'], '</dt>
						<dd>
							<input type="checkbox" name="active"', $context['repository']['status'] == REPO_ACTIVE ? ' checked="checked"' : '', '>', $context['repository']['status'] == REPO_ERROR ? '
							' . $txt['plugins_repo_error'] . ' <a href="<URL>?action=help;in=error_plugin_repo" onclick="return reqWin(this);" class="help"></a>' : '', '
						</dd>
					</dl>
				</fieldset>
				<fieldset>
					<legend>', $txt['plugins_repo_auth'], '</legend>
					<p>', $txt['plugins_repo_auth_desc'], '</p>
					<dl class="settings">
						<dt>', $txt['plugins_repo_username'], '</dt>
						<dd><input type="text" name="username" size="44" value="', $context['repository']['username'], '">
						<dt>
							', $txt['plugins_repo_password'], !empty($context['repository']['password']) ? '
							<span class="smalltext">(<a href="<URL>?action=help;in=plugins_password_blank" onclick="return reqWin(this);">' . $txt['plugins_repo_password_blank'] . '</a>)' : '', '
						</dt>
						<dd><input type="password" name="password" size="44" value=""></dd>
					</dl>
				</fieldset>
				<div class="right">
					<input type="submit" value="', $txt['save'], '" class="save">
					<input type="submit" name="delete" value="', $txt['plugins_repo_delete'], '" class="delete" onclick="return ask(', JavaScriptEscape($txt['plugins_repo_delete_confirm']), ', e);">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>';
}

function template_upload_generic_progress()
{
	global $context, $txt;

	echo '
		<we:cat>', $context['page_title'],'</we:cat>
		<form action="', $context['form_url'], '" method="post">
			<div class="windowbg2 wrc">
				<p>', $context['description'], '</p>
				<input type="submit" class="submit" value="', $txt['not_done_continue'], '">';
	if (!empty($context['continue_post']))
		foreach ($context['continue_post'] as $k => $v)
			echo '
				<input type="hidden" name="', $k, '" value="', $v, '">';
	echo '
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>';
}

function template_upload_duplicate_detected()
{
	global $txt, $context;

	echo '
	<we:cat>', $txt['plugin_duplicate_detected_title'], '</we:cat>
	<form action="<URL>?action=admin;area=plugins;sa=add;upload;stage=1;duplicate" method="post">
		<div class="windowbg2 wrc">
			<p>', sprintf($txt['plugin_duplicate_detected'], $context['new_plugin'], $context['existing_plugin']), '</p>
			<fieldset>
				<legend>', $txt['plugin_duplicate_cancel'], '</legend>
				', $txt['plugin_duplicate_cancel_desc'], '<br>
				<input name="cancel" type="submit" class="delete floatright" value="', $txt['plugin_duplicate_cancel'], '">
			</fieldset>
			<br>
			<fieldset>
				<legend>', $txt['plugin_duplicate_proceed'], '</legend>
				', $txt['plugin_duplicate_proceed_desc'], '<br>
				<input name="upgrade" type="submit" class="submit floatright" value="', $txt['plugin_duplicate_proceed'], '">
			</fieldset>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>
	<br class="clear">';
}

function template_upload_connection_details()
{
	global $txt, $context;

	echo '
	<we:cat>', $txt['plugin_connection_details_title'], '</we:cat>';

	if (!empty($context['ftp_details']['error']))
	{
		echo '
	<div class="errorbox" id="errors">
		<h3 id="error_serious">', $txt['plugin_ftp_error'], '</h3>
		<ul class="error" id="error_list">';

		foreach ($context['ftp_details']['error'] as $err)
			echo '
			<li>', $txt['plugin_ftp_error_' . $err], '</li>';
		echo '
		</ul>
	</div>';
	}

	echo '
	<form action="<URL>?action=admin;area=plugins;sa=add;upload;stage=1" method="post">
		<div class="windowbg2 wrc">
			<p>', $txt['plugin_connection_details'], '</p>
			<fieldset>
				<legend>', $txt['plugin_connection_details_title'], '</legend>
				', $txt['plugin_connection_required'], '
				<dl class="settings">
					<dt>
						<label for="ftp_server">', $txt['plugin_ftp_server'], '</label>
					</dt>
					<dd>
						<input type="text" size="42" name="server" id="ftp_server" value="', /* Servalan? */ htmlspecialchars($context['ftp_details']['server'], ENT_QUOTES), '" style="width: 99%"><!-- We are not the 1% -->
					<dt>
						<label for="ftp_username">', $txt['plugin_ftp_username'], '</label>
					</dt>
					<dd>
						<input type="text" size="42" name="user" id="ftp_username" value="', htmlspecialchars($context['ftp_details']['user'], ENT_QUOTES), '" style="width: 99%">
					</dd>
					<dt>
						<label for="ftp_password">', $txt['plugin_ftp_password'], '</label>
					</dt>
					<dd>
						<input type="password" size="42" name="password" id="ftp_password" style="width: 99%">
					</dd>
					<dt>
						<label for="ftp_type">', $txt['plugin_ftp_type'], '</label>
					</dt>
					<dd>
						<select name="type" id="ftp_type" onchange="update_server(this.value);">
							<option value="ftp"', $context['ftp_details']['type'] == 'ftp' ? ' selected' : '', '>FTP</option>
							<option value="sftp"', $context['ftp_details']['type'] == 'sftp' ? ' selected' : '', '>SFTP</option>
						</select>
					</dd>
					<dt>
						<label for="ftp_port">', $txt['plugin_ftp_port'], '</label>
					</dt>
					<dd>
						<input type="number" min="1" max="65535" size="5" id="ftp_port" name="port" value="', $context['ftp_details']['port'], '">
					</dd>
					<dt class="path">
						<label for="ftp_path">', $txt['plugin_ftp_path'], '</label>
					</dt>
					<dd class="path">
						<input type="path" size="42" name="path" id="ftp_path" style="width: 99%" value="', /* We don't need another hero, but we do need to know the way home */ htmlspecialchars($context['ftp_details']['path'], ENT_QUOTES), '">
					</dd>
				</dl>
				<div class="right">
					<label><input type="checkbox" name="savedetails" checked> ', $txt['plugin_ftp_save'], '</label>
				</div>
				<br class="clear">
				<input type="submit" name="connect" class="submit floatright" value="', $txt['plugin_connection'], '">
			</fieldset>
			<fieldset>
				<legend>', $txt['plugin_connection_cancel_oops'], '</legend>
				', $txt['plugin_connection_cancel'], '
				<br>
				<input type="submit" name="cancel" class="delete floatright" value="', $txt['plugin_connection_button'], '">
			</fieldset>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>
	<br class="clear">';

	// If using SFTP, hide the path variable, and whichever we go to, if the port is at the old default, update it.
	add_js('
	function update_server(type)
	{
		if (type == "ftp")
		{
			$(".path").show();
			if ($("#ftp_port").val() == 22)
				$("#ftp_port").val(21);
		}
		else
		{
			$(".path").hide();
			if ($("#ftp_port").val() == 21)
				$("#ftp_port").val(22);
		}
	};');
}
