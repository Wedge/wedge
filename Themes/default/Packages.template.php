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
	global $context, $theme, $options, $txt, $scripturl;

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
			<form action="', $scripturl, '?action=admin;area=packages;get" method="post" accept-charset="UTF-8">
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
						<span class="package_server floatright"><a href="' . $scripturl . '?action=admin;area=packages;get;sa=remove;server=' . $server['id'] . ';', $context['session_query'], '">[ ' . $txt['delete'] . ' ]</a></span>
						<span class="package_server floatright"><a href="' . $scripturl . '?action=admin;area=packages;get;sa=browse;server=' . $server['id'] . '">[ ' . $txt['package_browse'] . ' ]</a></span>
					</li>';

	echo '
				</ul>
			</fieldset>
			<fieldset>
				<legend>' . $txt['add_server'] . '</legend>
				<form action="' . $scripturl . '?action=admin;area=packages;get;sa=add" method="post" accept-charset="UTF-8">
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
				<form action="', $scripturl, '?action=admin;area=packages;get;sa=download;byurl;', $context['session_query'], '" method="post" accept-charset="UTF-8">
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
		<br>
		<we:title>
			', $txt['package_upload_title'], '
		</we:title>
		<div class="windowbg wrc">
			<form action="' . $scripturl . '?action=admin;area=packages;get;sa=upload" method="post" accept-charset="UTF-8" enctype="multipart/form-data" style="margin-bottom: 0">
				<dl class="settings">
					<dt>
						<strong>' . $txt['package_upload_select'] . ':</strong>
					</dt>
					<dd>
						<input type="file" name="package">
					</dd>
				</dl>
				<div class="right">
					<input type="submit" value="' . $txt['package_upload'] . '" class="save">
					<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
				</div>
			</form>
		</div>';
}

function template_package_confirm()
{
	global $context, $theme, $options, $txt, $scripturl;

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
	global $context, $theme, $options, $txt, $scripturl;

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
		aSwapImages: [{ sId: \'ps_img_', $section, '\' }]
	});');

			foreach ($ps['items'] as $id => $package)
				if (!$package['is_text'] && !$package['is_line'] && !$package['is_remote'])
					add_js('
	new weToggle({
		isCollapsed: true,
		aSwapContainers: [\'package_section_', $section, '_pkg_', $id, '\'],
		aSwapImages: [{ sId: \'ps_img_', $section, '_pkg_', $id, '\' }]
	});');
		}
	}
}

function template_downloaded()
{
	global $context, $theme, $options, $txt, $scripturl;

	echo '
		<we:cat>
			', $context['page_title'], '
		</we:cat>
		<div class="windowbg wrc">
			<p>', (empty($context['package_server']) ? $txt['package_uploaded_successfully'] : $txt['package_downloaded_successfully']), '</p>
			<ul class="reset">
				<li class="reset">
					<span class="floatleft"><strong>', $context['package']['name'], '</strong></span>
					<span class="package_server floatright">', $context['package']['list_files']['link'], '</span>
					<span class="package_server floatright">', $context['package']['install']['link'], '</span>
				</li>
			</ul>
			<br><br>
			<p><a href="', $scripturl, '?action=admin;area=packages;get', (isset($context['package_server']) ? ';sa=browse;server=' . $context['package_server'] : ''), '">[ ', $txt['back'], ' ]</a></p>
		</div>';
}

function template_control_chmod()
{
	global $context, $theme, $options, $txt, $scripturl;

	// Nothing to do? Brilliant!
	if (empty($context['package_ftp']))
		return false;

	if (empty($context['package_ftp']['form_elements_only']))
	{
		echo '
				', sprintf($txt['package_ftp_why'], '$(\'#need_writable_list\').show(); return false;'), '<br>
				<div id="need_writable_list" class="smalltext">
					', $txt['package_ftp_why_file_list'], '
					<ul style="display: inline">';

		if (!empty($context['notwritable_files']))
			foreach ($context['notwritable_files'] as $file)
				echo '
						<li>', $file, '</li>';

		echo '
					</ul>
				</div>';
	}

	echo '
				<div id="ftp_error_div" style="', !empty($context['package_ftp']['error']) ? '' : 'display: none; ', 'padding: 1px; margin: 1ex"><div class="windowbg2" id="ftp_error_innerdiv" style="padding: 1ex">
					<tt id="ftp_error_message">', !empty($context['package_ftp']['error']) ? $context['package_ftp']['error'] : '', '</tt>
				</div></div>';

	if (!empty($context['package_ftp']['destination']))
		echo '
				<form action="', $context['package_ftp']['destination'], '" method="post" accept-charset="UTF-8" style="margin: 0">';

	echo '
					<fieldset>
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
							<input type="text" size="50" name="ftp_username" id="ftp_username" value="', $context['package_ftp']['username'], '" style="width: 98%">
						</dd>
						<dt>
							<label for="ftp_password">', $txt['package_ftp_password'], ':</label>
						</dt>
						<dd>
							<input type="password" size="50" name="ftp_password" id="ftp_password" style="width: 98%">
						</dd>
						<dt>
							<label for="ftp_path">', $txt['package_ftp_path'], ':</label>
						</dt>
						<dd>
							<input type="text" size="50" name="ftp_path" id="ftp_path" value="', $context['package_ftp']['path'], '" style="width: 98%">
						</dd>
					</dl>
					</fieldset>';

	if (empty($context['package_ftp']['form_elements_only']))
		echo '

					<div class="right" style="margin: 1ex">
						<span id="test_ftp_placeholder_full"></span>
						<input type="submit" value="', $txt['package_proceed'], '" class="submit">
					</div>';

	if (!empty($context['package_ftp']['destination']))
		echo '
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</form>';

	// Hide the details of the list.
	if (empty($context['package_ftp']['form_elements_only']))
		add_js_inline('
	document.getElementById(\'need_writable_list\').style.display = \'none\';');

	// Quick generate the test button.
	add_js('
	// Generate a "test ftp" button.
	var generatedButton = false;
	function generateFTPTest()
	{
		// Don\'t ever call this twice!
		if (generatedButton)
			return false;
		generatedButton = true;

		// No XML?
		if (!$("#test_ftp_placeholder").length && !$("#test_ftp_placeholder_full").length)
			return false;

		var ftpTest = $(\'<input type="button"></input>\').click(testFTP);

		if ($("#test_ftp_placeholder").length)
			ftpTest.val(', JavaScriptEscape($txt['package_ftp_test']), ').appendTo($("#test_ftp_placeholder"));
		else
			ftpTest.val(', JavaScriptEscape($txt['package_ftp_test_connection']), ').appendTo($("#test_ftp_placeholder_full"));
	}
	function testFTP()
	{
		show_ajax();

		// What we need to post.
		var oPostData = {
			0: "ftp_server",
			1: "ftp_port",
			2: "ftp_username",
			3: "ftp_password",
			4: "ftp_path"
		}, sPostData = "";

		for (i = 0; i < 5; i++)
			sPostData += (sPostData.length == 0 ? "" : "&") + oPostData[i] + "=" + encodeURIComponent($("#" + oPostData[i]).val());

		// Post the data out.
		$.post(weUrl(\'action=admin;area=packages;sa=ftptest;xml;' . $context['session_query'] . '\'), sPostData, testFTPResults);
	}
	function testFTPResults(oXMLDoc)
	{
		hide_ajax();

		// This assumes it went wrong!
		var wasSuccess = false;
		var message = ' . JavaScriptEscape($txt['package_ftp_test_failed']) . ';

		var results = oXMLDoc.getElementsByTagName("results")[0].getElementsByTagName("result");
		if (results.length > 0)
		{
			if (results[0].getAttribute("success") == 1)
				wasSuccess = true;
			message = results[0].firstChild.nodeValue;
		}

		$("#ftp_error_div").show().css("backgroundColor", wasSuccess ? "green" : "red");
		$("#ftp_error_innerdiv").css("backgroundColor", wasSuccess ? "#DBFDC7" : "#FDBDBD");
		$("#ftp_error_message").html(message);
	}
	generateFTPTest();');
}

function template_ftp_required()
{
	global $context, $theme, $options, $txt, $scripturl;

	echo '
		<fieldset>
			<legend>
				', $txt['package_ftp_necessary'], '
			</legend>
			<div class="ftp_details">
				', template_control_chmod(), '
			</div>
		</fieldset>';
}

function template_file_permissions()
{
	global $txt, $scripturl, $context, $theme;

	// This will handle expanding the selection.
	add_js('
	var oRadioColors = {
		0: "#D1F7BF",
		1: "#FFBBBB",
		2: "#FDD7AF",
		3: "#C2C6C0",
		4: "#EEEEEE"
	}
	var oRadioValues = {
		0: "read",
		1: "writable",
		2: "execute",
		3: "custom",
		4: "no_change"
	}
	function expandFolder(folderIdent, folderReal)
	{
		// See if it already exists.
		var foundOne = false;

		$(\'tr[id^="content_\' + folderIdent + \':-:"]\').each(function (tr) {
			$(this).toggle();
			foundOne = true;
		});

		// Got something? Then we\'re done.
		if (foundOne)
			return false;

		// Otherwise we need to get the wicked thing.
		show_ajax();
		$.get(weUrl("action=admin;area=packages;onlyfind=" + encodeURIComponent(folderReal) + ";sa=perms;xml;', $context['session_query'], '"), onNewFolderReceived);

		return false;
	}
	function dynamicExpandFolder()
	{
		expandFolder(this.ident, this.path);

		return false;
	}
	function dynamicAddMore()
	{
		show_ajax();

		$.get(weUrl("action=admin;area=packages;fileoffset=" + (parseInt(this.offset) + ', $context['file_limit'], ') + ";onlyfind=" + this.path.php_urlencode() + ";sa=perms;xml;', $context['session_query'], '"), onNewFolderReceived);
	}
	function repeatString(sString, iTime)
	{
		return iTime < 1 ? "" : sString + repeatString(sString, iTime - 1);
	}
	// Create a named element dynamically - thanks to: http://www.thunderguy.com/semicolon/2005/05/23/setting-the-name-attribute-in-internet-explorer/
	function createNamedElement(type, name, customFields)
	{
		var element = null;

		if (!customFields)
			customFields = "";

		// Try the IE way; this fails on standards-compliant browsers
		try
		{
			element = document.createElement("<" + type + \' name="\' + name + \'"\' + customFields + ">");
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
	// Getting something back?
	function onNewFolderReceived(oXMLDoc)
	{
		hide_ajax();

		var fileItems = $("folders folder", oXMLDoc);

		// No folders, no longer worth going further.
		if (!fileItems.length)
		{
			if (oXMLDoc.getElementsByTagName(\'roots\')[0].getElementsByTagName(\'root\')[0])
			{
				var itemLink = $(\'#link_\' + $("roots root", oXMLDoc).text())[0];

				// Move the children up.
				for (i = 0; i <= itemLink.childNodes.length; i++)
					itemLink.parentNode.insertBefore(itemLink.childNodes[0], itemLink);

				// And remove the link.
				itemLink.parentNode.removeChild(itemLink);
			}
			return false;
		}
		var tableHandle = false;
		var isMore = false;
		var ident = "";
		var my_ident = "";
		var curLevel = 0;

		for (var i = 0; i < fileItems.length; i++)
		{
			if (fileItems[i].getAttribute(\'more\') == 1)
			{
				isMore = true;
				var curOffset = fileItems[i].getAttribute(\'offset\');
			}

			if (fileItems[i].getAttribute(\'more\') != 1 && document.getElementById("insert_div_loc_" + fileItems[i].getAttribute(\'ident\')))
			{
				ident = fileItems[i].getAttribute(\'ident\');
				my_ident = fileItems[i].getAttribute(\'my_ident\');
				curLevel = fileItems[i].getAttribute(\'level\') * 5;
				curPath = fileItems[i].getAttribute(\'path\');

				// Get where we\'re putting it next to.
				tableHandle = document.getElementById("insert_div_loc_" + fileItems[i].getAttribute(\'ident\'));

				var curRow = document.createElement("tr");
				curRow.className = "windowbg";
				curRow.id = "content_" + my_ident;
				curRow.style.display = "";
				var curCol = document.createElement("td");
				curCol.className = "smalltext";
				curCol.width = "40%";

				// This is the name.
				var fileName = document.createTextNode(String.fromCharCode(160) + fileItems[i].firstChild.nodeValue);

				// Start by wacking in the spaces.
				curCol.innerHTML = repeatString("&nbsp;", curLevel);

				// Create the actual text.
				if (fileItems[i].getAttribute(\'folder\') == 1)
				{
					var linkData = document.createElement("a");
					linkData.name = "fol_" + my_ident;
					linkData.id = "link_" + my_ident;
					linkData.href = \'#\';
					linkData.path = curPath + "/" + fileItems[i].firstChild.nodeValue;
					linkData.ident = my_ident;
					linkData.onclick = dynamicExpandFolder;

					var folderImage = document.createElement("img");
					folderImage.style.verticalAlign = "bottom";
					folderImage.src = \'', addcslashes($theme['default_images_url'], "\\"), '/board.gif\';
					linkData.appendChild(folderImage);

					linkData.appendChild(fileName);
					curCol.appendChild(linkData);
				}
				else
					curCol.appendChild(fileName);

				curRow.appendChild(curCol);

				// Right, the permissions.
				curCol = document.createElement("td");
				curCol.className = "smalltext";

				var writeSpan = document.createElement("span");
				writeSpan.style.color = fileItems[i].getAttribute(\'writable\') ? "green" : "red";
				writeSpan.innerHTML = fileItems[i].getAttribute(\'writable\') ? \'', $txt['package_file_perms_writable'], '\' : \'', $txt['package_file_perms_not_writable'], '\';
				curCol.appendChild(writeSpan);

				if (fileItems[i].getAttribute(\'permissions\'))
				{
					var permData = document.createTextNode("\u00a0(', $txt['package_file_perms_chmod'], ': " + fileItems[i].getAttribute(\'permissions\') + ")");
					curCol.appendChild(permData);
				}

				curRow.appendChild(curCol);

				// Now add the five radio buttons.
				for (j = 0; j < 5; j++)
				{
					curCol = document.createElement("td");
					curCol.style.backgroundColor = oRadioColors[j];
					curCol.align = "center";

					var curInput = createNamedElement("input", "permStatus[" + curPath + "/" + fileItems[i].firstChild.nodeValue + "]", j == 4 ? \' checked\' : "");
					curInput.type = "radio";
					curInput.checked = "checked";
					curInput.value = oRadioValues[j];

					curCol.appendChild(curInput);
					curRow.appendChild(curCol);
				}

				// Put the row in.
				tableHandle.parentNode.insertBefore(curRow, tableHandle);

				// Put in a new dummy section?
				if (fileItems[i].getAttribute(\'folder\') == 1)
				{
					var newRow = document.createElement("tr");
					newRow.id = "insert_div_loc_" + my_ident;
					newRow.style.display = "none";
					tableHandle.parentNode.insertBefore(newRow, tableHandle);
					var newCol = document.createElement("td");
					newCol.colspan = 2;
					newRow.appendChild(newCol);
				}
			}
		}

		// Is there some more to remove?
		$("#content_" + ident + "_more").remove();

		// Add more?
		if (isMore && tableHandle)
		{
			// Create the actual link.
			var linkData = document.createElement("a");
			linkData.href = \'#fol_\' + my_ident;
			linkData.path = curPath;
			linkData.offset = curOffset;
			linkData.onclick = dynamicAddMore;

			linkData.appendChild(document.createTextNode(\'', $txt['package_file_perms_more_files'], '\'));

			curRow = document.createElement("tr");
			curRow.className = "windowbg";
			curRow.id = "content_" + ident + "_more";
			tableHandle.parentNode.insertBefore(curRow, tableHandle);
			curCol = document.createElement("td");
			curCol.className = "smalltext";
			curCol.width = "40%";

			curCol.innerHTML = repeatString("&nbsp;", curLevel);
			curCol.appendChild(document.createTextNode(\'\\u00ab \'));
			curCol.appendChild(linkData);
			curCol.appendChild(document.createTextNode(\' \\u00bb\'));

			curRow.appendChild(curCol);
			curCol = document.createElement("td");
			curCol.className = "smalltext";
			curRow.appendChild(curCol);
		}

		// Keep track of it.
		var curInput = createNamedElement("input", "back_look[]");
		curInput.type = "hidden";
		curInput.value = curPath;

		curCol.appendChild(curInput);
	}');

		echo '
	<div class="information">
		<div>
			<strong>', $txt['package_file_perms_warning'], ':</strong>
			<div class="smalltext">
				<ol style="margin-top: 2px; margin-bottom: 2px">
					', $txt['package_file_perms_warning_desc'], '
				</ol>
			</div>
		</div>
	</div>

	<form action="', $scripturl, '?action=admin;area=packages;sa=perms;', $context['session_query'], '" method="post" accept-charset="UTF-8">
		<we:title>
			<span class="fperm floatright">', $txt['package_file_perms_new_status'], '</span>
			', $txt['package_file_perms'], '
		</we:title>
		<table class="table_grid w100 cs0 cp0">
		<thead>
			<tr class="catbg center">
				<th class="first_th left" style="width: 30%">&nbsp;', $txt['package_file_perms_name'], '&nbsp;</th>
				<th style="width: 30%" class="left">', $txt['package_file_perms_status'], '</th>
				<th style="width: 8%"><span class="filepermissions">', $txt['package_file_perms_status_read'], '</span></th>
				<th style="width: 8%"><span class="filepermissions">', $txt['package_file_perms_status_write'], '</span></th>
				<th style="width: 8%"><span class="filepermissions">', $txt['package_file_perms_status_execute'], '</span></th>
				<th style="width: 8%"><span class="filepermissions">', $txt['package_file_perms_status_custom'], '</span></th>
				<th style="width: 8%" class="last_th"><span class="filepermissions">', $txt['package_file_perms_status_no_change'], '</span></th>
			</tr>
		</thead>';

	foreach ($context['file_tree'] as $name => $dir)
	{
		echo '
		<tbody>
			<tr class="windowbg2 center">
				<td style="width: 30%" class="left"><strong>';

		if (!empty($dir['type']) && ($dir['type'] == 'dir' || $dir['type'] == 'dir_recursive'))
			echo '
					<img src="', $theme['default_images_url'], '/board.gif" class="bottom">';

		echo '
					', $name, '
				</strong></td>
				<td style="width: 30%" class="left">
					<span style="color: ', ($dir['perms']['chmod'] ? 'green' : 'red'), '">', ($dir['perms']['chmod'] ? $txt['package_file_perms_writable'] : $txt['package_file_perms_not_writable']), '</span>
					', ($dir['perms']['perms'] ? '&nbsp;(' . $txt['package_file_perms_chmod'] . ': ' . substr(sprintf('%o', $dir['perms']['perms']), -4) . ')' : ''), '
				</td>
				<td style="width: 8%" class="perm_read"><input type="radio" name="permStatus[', $name, ']" value="read"></td>
				<td style="width: 8%" class="perm_write"><input type="radio" name="permStatus[', $name, ']" value="writable"></td>
				<td style="width: 8%" class="perm_execute"><input type="radio" name="permStatus[', $name, ']" value="execute"></td>
				<td style="width: 8%" class="perm_custom"><input type="radio" name="permStatus[', $name, ']" value="custom"></td>
				<td style="width: 8%" class="perm_nochange"><input type="radio" name="permStatus[', $name, ']" value="no_change" checked></td>
			</tr>
		</tbody>';

		if (!empty($dir['contents']))
			template_permission_show_contents($name, $dir['contents'], 1);
	}

	echo '

		</table>
		<br>
		<we:title>
			', $txt['package_file_perms_change'], '
		</we:title>
		<div class="windowbg wrc">
			<fieldset>
				<dl>
					<dt>
						<label><input type="radio" name="method" value="individual" checked id="method_individual">
						<strong>', $txt['package_file_perms_apply'], '</strong></label>
					</dt>
					<dd>
						<em class="smalltext">', $txt['package_file_perms_custom'], ': <input type="text" name="custom_value" value="0755" maxlength="4" size="5">&nbsp;<a href="', $scripturl, '?action=help;in=chmod_flags" onclick="return reqWin(this);">(?)</a></em>
					</dd>
					<dt>
						<label><input type="radio" name="method" value="predefined" id="method_predefined">
						<strong>', $txt['package_file_perms_predefined'], ':</strong></label>
						<select name="predefined" onchange="$(\'#method_predefined\').prop(\'checked\', true);">
							<option value="restricted" selected>', $txt['package_file_perms_pre_restricted'], '</option>
							<option value="standard">', $txt['package_file_perms_pre_standard'], '</option>
							<option value="free">', $txt['package_file_perms_pre_free'], '</option>
						</select>
					</dt>
					<dd>
						<em class="smalltext">', $txt['package_file_perms_predefined_note'], '</em>
					</dd>
				</dl>
			</fieldset>';

	// Likely to need FTP?
	if (empty($context['ftp_connected']))
		echo '
			<p>
				', $txt['package_file_perms_ftp_details'], ':
			</p>
			', template_control_chmod(), '
			<div class="information">', $txt['package_file_perms_ftp_retain'], '</div>';

	echo '
			<span id="test_ftp_placeholder_full"></span>
			<div class="right padding">
				<input type="hidden" name="action_changes" value="1">
				<input type="submit" value="', $txt['package_file_perms_go'], '" name="go" class="submit">
			</div>
		</div>';

	// Any looks fors we've already done?
	foreach ($context['look_for'] as $path)
		echo '
		<input type="hidden" name="back_look[]" value="', $path, '">';

	echo '
	</form><br>';
}

function template_permission_show_contents($ident, $contents, $level, $has_more = false)
{
	global $theme, $txt, $scripturl, $context;

	$js_ident = preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident);
	// Have we actually done something?
	$drawn_div = false;

	foreach ($contents as $name => $dir)
	{
		if (isset($dir['perms']))
		{
			if (!$drawn_div)
			{
				$drawn_div = true;
				echo '
		</table>
		<table class="table_grid w100 cs0" id="', $js_ident, '">';
			}

			$cur_ident = preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident . '/' . $name);
			echo '
			<tr class="windowbg center" id="content_', $cur_ident, '">
				<td style="width: 30%" class="smalltext left">' . str_repeat('&nbsp;', $level * 5), '
					', (!empty($dir['type']) && $dir['type'] == 'dir_recursive') || !empty($dir['list_contents']) ? '<a id="link_' . $cur_ident . '" href="' . $scripturl . '?action=admin;area=packages;sa=perms;find=' . base64_encode($ident . '/' . $name) . ';back_look=' . $context['back_look_data'] . ';' . $context['session_query'] . '#fol_' . $cur_ident . '" onclick="return expandFolder(\'' . $cur_ident . '\', \'' . addcslashes($ident . '/' . $name, "'\\") . '\');">' : '';

			if (!empty($dir['type']) && ($dir['type'] == 'dir' || $dir['type'] == 'dir_recursive'))
				echo '
					<img src="', $theme['default_images_url'], '/board.gif" class="bottom">';

			echo '
					', $name, '
					', (!empty($dir['type']) && $dir['type'] == 'dir_recursive') || !empty($dir['list_contents']) ? '</a>' : '', '
				</td>
				<td style="width: 30%" class="smalltext left">
					<span class="', ($dir['perms']['chmod'] ? 'success' : 'error'), '">', ($dir['perms']['chmod'] ? $txt['package_file_perms_writable'] : $txt['package_file_perms_not_writable']), '</span>
					', ($dir['perms']['perms'] ? '&nbsp;(' . $txt['package_file_perms_chmod'] . ': ' . substr(sprintf('%o', $dir['perms']['perms']), -4) . ')' : ''), '
				</td>
				<td style="width: 8%" class="perm_read"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="read"></td>
				<td style="width: 8%" class="perm_write"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="writable"></td>
				<td style="width: 8%" class="perm_execute"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="execute"></td>
				<td style="width: 8%" class="perm_custom"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="custom"></td>
				<td style="width: 8%" class="perm_nochange"><input type="radio" name="permStatus[', $ident . '/' . $name, ']" value="no_change" checked></td>
			</tr>
			<tr id="insert_div_loc_' . $cur_ident . '" class="hide"><td></td></tr>';

			if (!empty($dir['contents']))
				template_permission_show_contents($ident . '/' . $name, $dir['contents'], $level + 1, !empty($dir['more_files']));
		}
	}

	// We have more files to show?
	if ($has_more)
		echo '
			<tr class="windowbg" id="content_', $js_ident, '_more">
				<td class="smalltext" style="width: 40%">' . str_repeat('&nbsp;', $level * 5), '
					&#171; <a href="' . $scripturl . '?action=admin;area=packages;sa=perms;find=' . base64_encode($ident) . ';fileoffset=', ($context['file_offset'] + $context['file_limit']), ';' . $context['session_query'] . '#fol_' . preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $ident) . '">', $txt['package_file_perms_more_files'], '</a> &#187;
				</td>
				<td colspan="6"></td>
			</tr>';

	if ($drawn_div)
	{
		// Hide anything too far down the tree.
		$isFound = false;
		foreach ($context['look_for'] as $tree)
			if (substr($tree, 0, strlen($ident)) == $ident)
				$isFound = true;

		if ($level > 1 && !$isFound)
		{
			echo '
		</table>';
			add_js('
	expandFolder(\'', $js_ident, '\', \'\');');
		}

		echo '
		<table class="table_grid w100 cs0">
			<tr class="hide"><td></td></tr>';
	}
}

function template_action_permissions()
{
	global $txt, $scripturl, $context, $theme;

	$countDown = 3;

	echo '
		<form action="', $scripturl, '?action=admin;area=packages;sa=perms;', $context['session_query'], '" id="perm_submit" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['package_file_perms_applying'], '
			</we:cat>';

	if (!empty($context['skip_ftp']))
		echo '
			<div class="errorbox">
				', $txt['package_file_perms_skipping_ftp'], '
			</div>';

	// How many have we done?
	$remaining_items = count($context['method'] == 'individual' ? $context['to_process'] : $context['directory_list']);
	$progress_message = sprintf($context['method'] == 'individual' ? $txt['package_file_perms_items_done'] : $txt['package_file_perms_dirs_done'], $context['total_items'] - $remaining_items, $context['total_items']);
	$progress_percent = round(($context['total_items'] - $remaining_items) / $context['total_items'] * 100, 1);

	echo '
			<div class="windowbg wrc">
				<div style="padding-left: 20%; padding-right: 20%; margin-top: 1ex">
					<strong>', $progress_message, '</strong>
					<div style="font-size: 8pt; height: 12pt; border: 1px solid black; background-color: white; padding: 1px; position: relative">
						<div style="padding-top: ', we::is('webkit') ? '2pt' : '1pt', '; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold">', $progress_percent, '%</div>
						<div style="width: ', $progress_percent, '%; height: 12pt; z-index: 1; background-color: #98b8f4">&nbsp;</div>
					</div>
				</div>';

	// Second progress bar for a specific directory?
	if ($context['method'] != 'individual' && !empty($context['total_files']))
	{
		$file_progress_message = sprintf($txt['package_file_perms_files_done'], $context['file_offset'], $context['total_files']);
		$file_progress_percent = round($context['file_offset'] / $context['total_files'] * 100, 1);

		echo '
				<br>
				<div style="padding-left: 20%; padding-right: 20%; margin-top: 1ex">
					<strong>', $file_progress_message, '</strong>
					<div style="font-size: 8pt; height: 12pt; border: 1px solid black; background-color: white; padding: 1px; position: relative">
						<div style="padding-top: ', we::is('webkit') ? '2pt' : '1pt', '; width: 100%; z-index: 2; color: black; position: absolute; text-align: center; font-weight: bold">', $file_progress_percent, '%</div>
						<div style="width: ', $file_progress_percent, '%; height: 12pt; z-index: 1; background-color: #c1ffc1">&nbsp;</div>
					</div>
				</div>';
	}

	echo '
				<br>';

	// Put out the right hidden data.
	if ($context['method'] == 'individual')
		echo '
				<input type="hidden" name="custom_value" value="', $context['custom_value'], '">
				<input type="hidden" name="totalItems" value="', $context['total_items'], '">
				<input type="hidden" name="toProcess" value="', base64_encode(serialize($context['to_process'])), '">';
	else
		echo '
				<input type="hidden" name="predefined" value="', $context['predefined_type'], '">
				<input type="hidden" name="fileOffset" value="', $context['file_offset'], '">
				<input type="hidden" name="totalItems" value="', $context['total_items'], '">
				<input type="hidden" name="dirList" value="', base64_encode(serialize($context['directory_list'])), '">
				<input type="hidden" name="specialFiles" value="', base64_encode(serialize($context['special_files'])), '">';

	// Are we not using FTP for whatever reason.
	if (!empty($context['skip_ftp']))
		echo '
				<input type="hidden" name="skip_ftp" value="1">';

	// Retain state.
	foreach ($context['back_look_data'] as $path)
		echo '
				<input type="hidden" name="back_look[]" value="', $path, '">';

	echo '
				<input type="hidden" name="method" value="', $context['method'], '">
				<input type="hidden" name="action_changes" value="1">
				<div class="right padding">
					<input type="submit" name="go" id="cont" value="', westr::htmlspecialchars($txt['not_done_continue']), '" class="submit">
				</div>
			</div>
		</form>';

	// Just the countdown stuff
	add_js_inline('
	var countdown = ', $countDown, ';
	doAutoSubmit();

	function doAutoSubmit()
	{
		if (countdown == 0)
			document.forms.perm_submit.submit();
		else if (countdown == -1)
			return;

		$(\'#cont\').val(', JavaScriptEscape($txt['not_done_continue']), ' + " (" + countdown + ")");
		countdown--;

		setTimeout(doAutoSubmit, 1000);
	}');
}
