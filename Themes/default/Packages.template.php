<?php
/**
 * Wedge
 *
 * Displays the various aspects of the package manager.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_main()
{
	global $context, $theme, $options;
}

function template_servers()
{
	global $context, $theme, $options, $txt;

	if (!empty($context['package_ftp']['error']))
			echo '
					<div class="errorbox">
						<tt>', $context['package_ftp']['error'], '</tt>
					</div>';

	echo '
		<we:cat>
			', $txt['download_new_package'], '
		</we:cat>';

	if ($context['package_download_broken'])
	{
		echo '
		<we:title>
			', $txt['package_ftp_necessary'], '
		</we:title>
		<div class="windowbg wrc">
			<p>
				', $txt['package_ftp_why_download'], '
			</p>
			<form action="<URL>?action=admin;area=packages;get" method="post" accept-charset="UTF-8">
				<dl class="settings">
					<dt>
						<label for="ftp_server">', $txt['package_ftp_server'], ':</label>
					</dt>
					<dd>
						<input type="text" size="30" name="ftp_server" id="ftp_server" value="', $context['package_ftp']['server'], '">
						<label>', $txt['package_ftp_port'], ':&nbsp;<input type="text" size="3" name="ftp_port" id="ftp_port" value="', $context['package_ftp']['port'], '"></label>
					</dd>
					<dt>
						<label for="ftp_username">', $txt['package_ftp_username'], ':</label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_username" id="ftp_username" value="', $context['package_ftp']['username'], '" style="width: 99%">
					</dd>
					<dt>
						<label for="ftp_password">', $txt['package_ftp_password'], ':</label>
					</dt>
					<dd>
						<input type="password" size="50" name="ftp_password" id="ftp_password" style="width: 99%">
					</dd>
					<dt>
						<label for="ftp_path">', $txt['package_ftp_path'], ':</label>
					</dt>
					<dd>
						<input type="text" size="50" name="ftp_path" id="ftp_path" value="', $context['package_ftp']['path'], '" style="width: 99%">
					</dd>
				</dl>
				<div class="right">
					<input type="submit" value="', $txt['package_proceed'], '" class="submit">
				</div>
			</form>
		</div>';
	}

	echo '
		<div class="windowbg2 wrc">
			<fieldset>
				<legend>' . $txt['package_servers'] . '</legend>
				<ul class="package_servers">';

	foreach ($context['servers'] as $server)
		echo '
					<li class="flow_auto">
						<span class="floatleft">' . $server['name'] . '</span>
						<span class="package_server floatright"><a href="<URL>?action=admin;area=packages;get;sa=remove;server=' . $server['id'] . ';', $context['session_query'], '">[ ' . $txt['delete'] . ' ]</a></span>
						<span class="package_server floatright"><a href="<URL>?action=admin;area=packages;get;sa=browse;server=' . $server['id'] . '">[ ' . $txt['package_browse'] . ' ]</a></span>
					</li>';

	echo '
				</ul>
			</fieldset>
			<fieldset>
				<legend>' . $txt['add_server'] . '</legend>
				<form action="<URL>?action=admin;area=packages;get;sa=add" method="post" accept-charset="UTF-8">
					<dl class="settings">
						<dt>
							<strong>' . $txt['server_name'] . ':</strong>
						</dt>
						<dd>
							<input type="text" name="servername" size="44" value="Wedge">
						</dd>
						<dt>
							<strong>' . $txt['serverurl'] . ':</strong>
						</dt>
						<dd>
							<input type="text" name="serverurl" size="44" value="http://">
						</dd>
					</dl>
					<div class="right">
						<input type="submit" value="' . $txt['add_server'] . '" class="new">
						<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
					</div>
				</form>
			</fieldset>
			<fieldset>
				<legend>', $txt['package_download_by_url'], '</legend>
				<form action="<URL>?action=admin;area=packages;get;sa=download;byurl;', $context['session_query'], '" method="post" accept-charset="UTF-8">
					<dl class="settings">
						<dt>
							<strong>' . $txt['serverurl'] . ':</strong>
						</dt>
						<dd>
							<input type="text" name="package" size="44" value="http://">
						</dd>
						<dt>
							<strong>', $txt['package_download_filename'], ':</strong>
						</dt>
						<dd>
							<input type="text" name="filename" size="44">
							<dfn>', $txt['package_download_filename_info'], '</dfn>
						</dd>
					</dl>
					<div class="right">
						<input type="submit" value="', $txt['download'], '" class="save">
					</div>
				</form>
			</fieldset>
		</div>
		<br>';
}

function template_package_confirm()
{
	global $context, $theme, $options, $txt;

	echo '
		<we:cat>
			', $context['page_title'], '
		</we:cat>
		<div class="windowbg wrc">
			<p>', $context['confirm_message'], '</p>
			<a href="', $context['proceed_href'], '">[ ', $txt['package_confirm_proceed'], ' ]</a> <a href="JavaScript:history.go(-1);">[ ', $txt['package_confirm_go_back'], ' ]</a>
		</div>';
}

function template_package_list()
{
	global $context, $theme, $options, $txt;

	echo '
		<we:cat>
			', $context['page_title'], '
		</we:cat>
		<div class="windowbg wrc">';

	// No packages, as yet.
	if (!empty($context['package_list']))
	{
		echo '
			<ul id="package_list">';

		foreach ($context['package_list'] as $i => $packageSection)
		{
			echo '
				<li>
					<strong><div class="shrinkable" id="ps_img_', $i, '"></div> ', $packageSection['title'], '</strong>';

			if (!empty($packageSection['text']))
				echo '
					<div class="information">', $packageSection['text'], '</div>';

			echo '
					<', $context['list_type'], ' id="package_section_', $i, '" class="packages">';

			$alt = false;

			foreach ($packageSection['items'] as $id => $package)
			{
				echo '
						<li>';

				// Textual message. Could be empty just for a blank line...
				if ($package['is_text'])
					echo '
							', empty($package['name']) ? '&nbsp;' : $package['name'];

				// This is supposed to be a rule..
				elseif ($package['is_line'])
					echo '
							<hr>';

				// A remote link.
				elseif ($package['is_remote'])
					echo '
							<strong>', $package['link'], '</strong>';

				// A title?
				elseif ($package['is_heading'] || $package['is_title'])
					echo '
							<strong>', $package['name'], '</strong>';

				// Otherwise, it's a package.
				else
				{
					// 1. Some mod [Download]
					echo '
							<strong><div class="shrinkable" id="ps_img_', $i, '_pkg_', $id, '"></div> ', $package['can_install'] ? '<strong>' . $package['name'] . '</strong> <a href="' . $package['download']['href'] . '">[ ' . $txt['download'] . ' ]</a>' : $package['name'];

					echo '</strong>
							<ul id="package_section_', $i, '_pkg_', $id, '" class="package_section">';

					// Show the mod type?
					if ($package['type'] != '')
						echo '
								<li class="package_section">', $txt['package_type'], ':&nbsp; ', westr::ucwords(westr::strtolower($package['type'])), '</li>';
					// Show the version number?
					if ($package['version'] != '')
						echo '
								<li class="package_section">', $txt['mod_version'], ':&nbsp; ', $package['version'], '</li>';
					// How 'bout the author?
					if (!empty($package['author']) && $package['author']['name'] != '' && isset($package['author']['link']))
						echo '
								<li class="package_section">', $txt['author'], ':&nbsp; ', $package['author']['link'], '</li>';
					// The homepage....
					if ($package['author']['website']['link'] != '')
						echo '
								<li class="package_section">', $txt['author_website'], ':&nbsp; ', $package['author']['website']['link'], '</li>';

					// Desciption: bleh bleh!
					// Location of file: http://someplace/.
					echo '
								<li class="package_section">', $txt['file_location'], ':&nbsp; <a href="', $package['href'], '">', $package['href'], '</a></li>
								<li class="package_section"><div class="information">', $txt['package_description'], ':&nbsp; ', $package['description'], '</div></li>
							</ul>';
				}
				$alt = !$alt;
				echo '
						</li>';
			}
			echo '
					</', $context['list_type'], '>
				</li>';
		}
		echo '
			</ul>';
	}
	echo '
		</div>
		<div class="padding smalltext floatleft">
		</div>';

	// Now go through and turn off all the sections.
	if (!empty($context['package_list']))
	{
		$section_count = count($context['package_list']);

		foreach ($context['package_list'] as $section => $ps)
		{
			add_js('
	new weToggle({
		isCollapsed: ', count($ps['items']) == 1 || $section_count == 1 ? 'false' : 'true', ',
		aSwapContainers: [\'package_section_', $section, '\'],
		aSwapImages: [\'ps_img_', $section, '\']
	});');

			foreach ($ps['items'] as $id => $package)
				if (!$package['is_text'] && !$package['is_line'] && !$package['is_remote'])
					add_js('
	new weToggle({
		isCollapsed: true,
		aSwapContainers: [\'package_section_', $section, '_pkg_', $id, '\'],
		aSwapImages: [\'ps_img_', $section, '_pkg_', $id, '\']
	});');
		}
	}
}

function template_downloaded()
{
	global $context, $theme, $options, $txt;

	echo '
		<we:cat>
			', $context['page_title'], '
		</we:cat>
		<div class="windowbg wrc">
			<p>', $txt['package_downloaded_successfully'], '</p>
			<ul class="reset">
				<li class="reset">
					<span class="floatleft"><strong>', $context['package']['name'], '</strong></span>
					<span class="package_server floatright">', $context['package']['list_files']['link'], '</span>
					<span class="package_server floatright">', $context['package']['install']['link'], '</span>
				</li>
			</ul>
			<br><br>
			<p><a href="<URL>?action=admin;area=packages;get;sa=browse;server=' . $context['package_server'], '">[ ', $txt['back'], ' ]</a></p>
		</div>';
}
