<?php
/**
 * Provides theme administration handling.
 * Most of these functions will eventually go away.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file concerns itself almost completely with theme administration.
	Its tasks include changing theme settings, installing and removing
	themes, choosing the current theme, and editing themes. This is done in:

	void Themes()
		- manages the action and delegates control to the proper sub action.
		- loads both the Themes and Settings language files.
		- checks the session by GET or POST to verify the sent data.
		- requires the user not be a guest.
		- is accessed via ?action=admin;area=theme.

	void ThemeAdmin()
		- administrates themes and their settings, as well as global theme
		  settings.
		- sets the settings theme_allow, theme_skin_guests, and knownThemes.
		- loads the template Themes.
		- requires the admin_forum permission.
		- accessed with ?action=admin;area=theme;sa=admin.

	void ThemeList()
		- lists the available themes.
		- provides an interface to reset the paths of all the installed themes.

	void SetThemeOptions()
		// !!!

	void RemoveSkin()
		- removes an installed skin.
		- requires an administrator.
		- accessed with ?action=admin;area=theme;sa=remove.

	void PickTheme()
		- allows user or administrator to pick a new theme with an interface.
		- can edit everyone's (u = 0), guests' (u = -1), or a specific user's.
		- uses the Themes template (pick block.)
		- accessed with ?action=theme;sa=pick or ?action=skin.

	void ThemeInstall()
		- installs new themes, either from a gzip or copy of the default.
		- requires an administrator.
		- puts themes in ROOT/Themes.
		- assumes the gzip has a root directory in it. (ie default.)
		- accessed with ?action=admin;area=theme;sa=install.

	void EditTheme()
		- shows an interface for editing the templates.
		- uses the Themes template and edit_template/edit_style block.
		- accessed via ?action=admin;area=theme;sa=edit

	// !!! Update this for the new package manager?
	Creating and distributing theme packages:
	---------------------------------------------------------------------------
		There isn't that much required to package and distribute your own
		themes... just do the following:
		- create a theme_info.xml file, with the root element theme-info.
		- its name should go in a name element, just like description.
		- your name should go in author. (email in the email attribute.)
		- any support website for the theme should be in website.
		- layers and templates (non-default) should go in those elements ;).
		- any extra rows for themes should go in extra, serialized,
		  as in "array(variable => value)".
		- tar and gzip the directory - and you're done!
		- please include any special license in a license.txt file.
	// !!! Thumbnail?
*/

// Subaction handler.
function Themes()
{
	global $txt, $context;

	// Load the important language files...
	loadLanguage('Themes');

	// No funny business - guests only.
	is_not_guest();

	// Default the page title to Theme Administration by default.
	$context['page_title'] = $txt['themeadmin_title'];

	// Theme administration, removal, choice, or installation...
	$subActions = array(
		'admin' => 'ThemeAdmin',
		'list' => 'ThemeList',
		'install' => 'ThemeInstall',
		'remove' => 'RemoveSkin',
		'pick' => 'PickTheme',
		'edit' => 'EditTheme',
		'copy' => 'CopyTemplate',
	);

	// !!! Layout Settings?
	if (!empty($context['admin_menu_name']))
	{
		$context[$context['admin_menu_name']]['tab_data'] = array(
			'title' => $txt['themeadmin_title'],
			'help' => 'themes',
			'description' => $txt['themeadmin_description'],
			'tabs' => array(
				'admin' => array(
					'description' => $txt['themeadmin_admin_desc'],
				),
				'list' => array(
					'description' => $txt['themeadmin_list_desc'],
				),
				'edit' => array(
					'description' => $txt['themeadmin_edit_desc'],
				),
			),
		);
	}

	// Follow the sa or just go to administration.
	if (isset($_GET['sa']) && !empty($subActions[$_GET['sa']]))
		$subActions[$_GET['sa']]();
	else
		$subActions['admin']();
}

function ThemeAdmin()
{
	global $context, $settings;

	loadLanguage('Admin');
	isAllowedTo('admin_forum');

	// If we aren't submitting - that is, if we are about to...
	if (!isset($_POST['save']))
	{
		loadTemplate('Themes');

		// Make our known themes a little easier to work with.
		$knownThemes = !empty($settings['knownThemes']) ? explode(',', $settings['knownThemes']) : array();

		// Get all skins...
		$context['themes'][1]['skins'] = wedge_get_skin_list();

		// Can we create a new theme?
		$context['can_create_new'] = is_writable(ROOT_DIR . '/Themes');
		$context['new_theme_dir'] = substr(realpath(ROOT_DIR . '/Themes'), 0, -7);

		// Look for a non-existent theme directory. (i.e. theme87.)
		$theme_dir = ROOT_DIR . '/Themes/theme';
		$i = 1;
		while (file_exists($theme_dir . $i))
			$i++;
		$context['new_theme_name'] = 'theme' . $i;
	}
	else
	{
		checkSession();

/*
		// !!! @todo: fix this.
		if (isset($_POST['options']['known_themes']))
			foreach ($_POST['options']['known_themes'] as $key => $id)
				$_POST['options']['known_themes'][$key] = (int) $id;
		else
			fatal_lang_error('themes_none_selectable', false);

		if (!in_array($_POST['options']['theme_guests'], $_POST['options']['known_themes']))
				fatal_lang_error('themes_default_selectable', false);
*/

		// Commit the new settings.
		updateSettings(array(
			'theme_allow' => $_POST['options']['theme_allow'],
			'theme_skin_guests' => isset($_POST['options']['theme_guests']) ? $_POST['options']['theme_guests'] : $settings['theme_skin_guests'],
			'theme_skin_guests_mobile' => isset($_POST['options']['theme_guests_mobile']) ? $_POST['options']['theme_guests_mobile'] : $settings['theme_skin_guests_mobile'],
//			'knownThemes' => implode(',', $_POST['options']['known_themes']),
		));

		if (!empty($_POST['theme_reset']))
			wedge_update_skin(null, $_POST['theme_reset'] == -1 ? '' : $_POST['theme_reset']);

		redirectexit('action=admin;area=theme;' . $context['session_query'] . ';sa=admin');
	}
}

// !! @todo: remove completely!
function ThemeList()
{
	global $context;

	loadLanguage('Admin');
	isAllowedTo('admin_forum');

	loadTemplate('Themes');

	$context['reset_dir'] = realpath(ROOT_DIR . '/Themes');
	$context['reset_url'] = ROOT . '/Themes';

	wetem::load('list_themes');
}

// Remove a skin from the database.
function RemoveSkin()
{
	global $settings, $context;

	checkSession('get');

	isAllowedTo('admin_forum');

	$_GET['skin'] = !empty($_GET['skin']) ? $_GET['skin'] : get_default_skin();

	// You can't delete the root skin!
	if ($_GET['skin'] == '/')
		fatal_lang_error('no_access', false);

	wesql::query('
		UPDATE {db_prefix}members
		SET skin = {string:default_skin}
		WHERE skin = {string:current_skin}',
		array(
			'default_skin' => '',
			'current_skin' => $_GET['skin'],
		)
	);

	wesql::query('
		UPDATE {db_prefix}boards
		SET skin = {string:default_skin}
		WHERE skin = {string:current_skin}',
		array(
			'default_skin' => '',
			'current_skin' => $_GET['skin'],
		)
	);

	// Fix it if the skin was the overall default skin.
	$upd = array();
	if ($settings['theme_skin_guests'] == $_GET['skin'])
		$upd['theme_skin_guests'] = '';
	if ($settings['theme_skin_guests_mobile'] == $_GET['skin'])
		$upd['theme_skin_guests_mobile'] = '';

	updateSettings($upd);

	redirectexit('action=admin;area=theme;sa=list;' . $context['session_query']);
}

// Choose a theme from a list.
function PickTheme()
{
	global $txt, $context, $settings;

	loadLanguage('Themes');
	loadTemplate('Themes');

	$u = isset($_REQUEST['u']) ? $_REQUEST['u'] : null;

	// Build the link tree.
	add_linktree($txt['change_skin'], $u === null ? '<URL>?action=skin' : ('<URL>' . ($u > 0 ? '?action=skin;u=' : '?action=theme;sa=pick;u=') . (int) $u));

	// Have we made a decision, or are we just browsing?
	if (isset($_GET['skin']))
	{
		checkSession('get');

		$skin = file_exists(SKINS_DIR . '/' . ltrim($_GET['skin'], '/')) ? $_GET['skin'] : '';

		if (we::$is_guest)
		{
			loadSource('Subs-Auth');
			$cookie_url = url_parts(!empty($settings['localCookies']), !empty($settings['globalCookies']));
			setcookie('guest_skin', $skin, $skin ? time() + 3600 * 24 * 365 : time() - 3600, $cookie_url[1], $cookie_url[0], 0, true);
			redirectexit(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'action=skin');
		}

		// Save for this user.
		if ($u === null || !allowedTo('admin_forum'))
		{
			wedge_update_skin(MID, $skin);
			// Redirect to the last page visited, if available -- useful for a skin selector :)
			redirectexit(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'action=skin');
		}

		// For everyone.
		if ($u == '0')
		{
			wedge_update_skin(null, $skin);
			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_query']);
		}
		// Change the default/guest theme.
		elseif ($u == '-1')
		{
			// Let's assume the admin is in mobile mode. Meaning they want to change the default mobile skin...
			if (we::is('mobile'))
				updateSettings(array(
					'theme_skin_guests_mobile' => $skin
				));
			else
				updateSettings(array(
					'theme_skin_guests' => $skin
				));
			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_query']);
		}
		// Change a specific member's theme.
		else
		{
			wedge_update_skin((int) $u, $skin);
			redirectexit('action=skin;u=' . (int) $u);
		}
	}

	// Figure out who the current member is, and what theme they've chosen.
	if ($u === null || !allowedTo('admin_forum'))
	{
		$context['specify_member'] = '';
		$context['current_skin'] = empty(we::$user['skin']) ? get_default_skin() : we::$user['skin'];
	}
	// Everyone can't choose just one.
	elseif ($u == '0')
	{
		$context['specify_member'] = ';u=0';
		$context['current_skin'] = '';
	}
	// Guests and such...
	elseif ($u == '-1')
	{
		$context['specify_member'] = ';u=-1';
		$context['current_skin'] = get_default_skin();
	}
	// Someone else :P
	else
	{
		$context['specify_member'] = ';u=' . (int) $u;

		$request = wesql::query('
			SELECT ' . (we::is('mobile') ? 'skin_mobile' : 'skin') . '
			FROM {db_prefix}members
			WHERE id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => (int) $u,
			)
		);
		list ($context['current_skin']) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	$request = wesql::query('SELECT skin, COUNT(*) FROM {db_prefix}members GROUP BY skin ORDER BY skin DESC');
	$context['skin_user_counts'] = array();
	while ($row = wesql::fetch_row($request))
		$context['skin_user_counts'][$row[0]] = $row[1];
	wesql::free_result($request);

	$context['available_themes'] = wedge_get_skin_list();
	unset($context['skin_user_counts']);

	if (file_exists(LANGUAGES_DIR . '/Settings.' . we::$user['language'] . '.php'))
		include(LANGUAGES_DIR . '/Settings.' . we::$user['language'] . '.php');
	elseif (file_exists(LANGUAGES_DIR . '/Settings.' . $settings['language'] . '.php'))
		include(LANGUAGES_DIR . '/Settings.' . $settings['language'] . '.php');
	else
		$txt['theme_description'] = '';

	$context['available_themes']['description'] = $txt['theme_description'];
	$context['available_themes']['name'] = $txt['theme_forum_default'];
	$context['available_themes']['selected'] = empty(we::$user['skin']);
	$context['available_themes']['description'] = $txt['theme_global_description'];

	$context['page_title'] = $txt['change_skin'];
	wetem::load('pick');
}

// !! @todo: remove this.
function ThemeInstall()
{
	global $txt, $context, $settings;

	checkSession('request');

	isAllowedTo('admin_forum');
	checkSession('request');

	loadSource('Subs-Package');

	loadTemplate('Themes');

	if (isset($_GET['theme_id']))
	{
		$result = wesql::query('
			SELECT value
			FROM {db_prefix}themes
			WHERE id_theme = {int:current_theme}
				AND id_member = {int:no_member}
				AND variable = {literal:name}
			LIMIT 1',
			array(
				'current_theme' => (int) $_GET['theme_id'],
				'no_member' => 0,
			)
		);
		list ($theme_name) = wesql::fetch_row($result);
		wesql::free_result($result);

		wetem::load('installed');
		$context['page_title'] = $txt['theme_installed'];
		$context['installed_theme'] = array(
			'id' => (int) $_GET['theme_id'],
			'name' => $theme_name,
		);

		return;
	}

	if ((!empty($_FILES['theme_gz']) && (!isset($_FILES['theme_gz']['error']) || $_FILES['theme_gz']['error'] != 4)) || !empty($_REQUEST['theme_gz']))
		$method = 'upload';
	elseif (isset($_REQUEST['theme_dir']) && rtrim(realpath($_REQUEST['theme_dir']), '/\\') != realpath(ROOT_DIR . '/Themes') && file_exists($_REQUEST['theme_dir']))
		$method = 'path';
	else
		$method = 'copy';

	if (!empty($_REQUEST['copy']) && $method == 'copy')
	{
		// Hopefully the themes directory is writable, or we might have a problem.
		if (!is_writable(ROOT_DIR . '/Themes'))
			fatal_lang_error('theme_install_write_error', 'critical');

		$theme_dir = ROOT_DIR . '/Themes/' . preg_replace('~[^A-Za-z0-9_\- ]~', '', $_REQUEST['copy']);

		umask(0);
		mkdir($theme_dir, 0777);

		@set_time_limit(600);
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		// Create subdirectories for CSS and JavaScript files.
		mkdir($theme_dir . '/skins', 0777);
		mkdir($theme_dir . '/scripts', 0777);

		// Copy over the default non-theme files.
		$to_copy = array('/index.php', '/index.template.php');
		foreach ($to_copy as $file)
		{
			copy(TEMPLATES_DIR . $file, $theme_dir . $file);
			@chmod($theme_dir . $file, 0777);
		}

		$theme_name = $_REQUEST['copy'];
		$theme_dir = realpath($theme_dir);

		// Let's get some data for the new theme.
		$request = wesql::query('
			SELECT value
			FROM {db_prefix}themes
			WHERE variable = {literal:theme_templates}
				AND id_member = {int:no_member}
				AND id_theme = {int:default_theme}',
			array(
				'no_member' => 0,
				'default_theme' => 1,
			)
		);
		list ($theme_templates) = wesql::fetch_row($request);
		wesql::free_result($request);

		// Let's add a theme_info.xml to this theme.
		$xml_info = '<?xml version="1.0"?' . '>
<theme-info xmlns="https://wedge.org/files/xml/theme-info.dtd" xmlns:we="https://wedge.org/">
	<!-- For the id, always use something unique - put your name, a colon, and then the package name. -->
	<id>wedge:' . westr::strtolower(str_replace(array(' '), '_', $_REQUEST['copy'])) . '</id>
	<version>' . WEDGE_VERSION . '</version>
	<!-- Theme name, used purely for aesthetics. -->
	<name>' . $_REQUEST['copy'] . '</name>
	<!-- Author: your email address or contact information. The name attribute is optional. -->
	<author name="Author">dummy@dummy.com</author>
	<!-- Website... where to get updates and more information. -->
	<website>https://wedge.org/</website>
	<!-- Templates to load on startup. Default is "index". -->
	<templates>' . (empty($theme_templates) ? 'index' : $theme_templates) . '</templates>
</theme-info>';

		// Now write it.
		$fp = @fopen($theme_dir . '/theme_info.xml', 'w+');
		if ($fp)
		{
			fwrite($fp, $xml_info);
			fclose($fp);
		}
	}
	elseif (isset($_REQUEST['theme_dir']) && $method == 'path')
	{
		if (!is_dir($_REQUEST['theme_dir']) || !file_exists($_REQUEST['theme_dir'] . '/theme_info.xml'))
			fatal_lang_error('theme_install_error', false);

		$theme_name = basename($_REQUEST['theme_dir']);
		$theme_dir = $_REQUEST['theme_dir'];
	}
	elseif ($method = 'upload')
	{
		// Hopefully the themes directory is writable, or we might have a problem.
		if (!is_writable(ROOT_DIR . '/Themes'))
			fatal_lang_error('theme_install_write_error', 'critical');

		loadSource('Subs-Package');

		// Set the default settings...
		$theme_name = strtok(basename(isset($_FILES['theme_gz']) ? $_FILES['theme_gz']['name'] : $_REQUEST['theme_gz']), '.');
		$theme_name = preg_replace(array('/\s/', '/\.{2,}/', '/[^\w.-]/'), array('_', '.', ''), $theme_name);
		$theme_dir = ROOT_DIR . '/Themes/' . $theme_name;

		if (isset($_FILES['theme_gz']) && is_uploaded_file($_FILES['theme_gz']['tmp_name']) && (ini_get('open_basedir') != '' || file_exists($_FILES['theme_gz']['tmp_name'])))
			$extracted = read_tgz_file($_FILES['theme_gz']['tmp_name'], ROOT_DIR . '/Themes/' . $theme_name, false, true);
		elseif (isset($_REQUEST['theme_gz']))
		{
			// Check that the theme is from wedge.org, for now... maybe add mirroring later.
			if (preg_match('~^http://[\w-]+\.wedge\.org/~', $_REQUEST['theme_gz']) == 0 || strpos($_REQUEST['theme_gz'], 'dlattach') !== false)
				fatal_lang_error('only_on_wedge');

			$extracted = read_tgz_file($_REQUEST['theme_gz'], ROOT_DIR . '/Themes/' . $theme_name, false, true);
		}
		else
			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_query']);
	}

	// Something go wrong?
	if ($theme_dir != '' && basename($theme_dir) != 'Themes')
	{
		// Defaults.
		$install_info = array(
			'theme_url' => ROOT . '/Themes/' . basename($theme_dir),
			'theme_dir' => $theme_dir,
			'name' => $theme_name
		);

		if (file_exists($theme_dir . '/theme_info.xml'))
		{
			$theme_info = file_get_contents($theme_dir . '/theme_info.xml');

			$xml_elements = array(
				'name' => 'name',
				'theme_templates' => 'templates',
			);
			foreach ($xml_elements as $var => $name)
				if (preg_match('~<' . $name . '>(?:<!\[CDATA\[)?(.+?)(?:\]\]>)?</' . $name . '>~', $theme_info, $match) == 1)
					$install_info[$var] = $match[1];

			if (preg_match('~<extra>(?:<!\[CDATA\[)?(.+?)(?:\]\]>)?</extra>~', $theme_info, $match) == 1)
				$install_info += unserialize($match[1]);
		}

		// Find the newest id_theme.
		$result = wesql::query('
			SELECT MAX(id_theme)
			FROM {db_prefix}themes',
			array(
			)
		);
		list ($id_theme) = wesql::fetch_row($result);
		wesql::free_result($result);

		// This will be the theme number...
		$id_theme++;

		$inserts = array();
		foreach ($install_info as $var => $val)
			$inserts[] = array($id_theme, $var, $val);

		if (!empty($inserts))
			wesql::insert('',
				'{db_prefix}themes',
				array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$inserts
			);

		updateSettings(array('knownThemes' => strtr($settings['knownThemes'] . ',' . $id_theme, array(',,' => ','))));
	}

	redirectexit('action=admin;area=theme;sa=install;theme_id=' . $id_theme . ';' . $context['session_query']);
}

function EditTheme()
{
	global $context;

	// !!! Should this be removed?
	if (isset($_REQUEST['preview']))
		exit;

	isAllowedTo('admin_forum');
	loadTemplate('Themes');

	$_GET['skin'] = !empty($_GET['skin']) ? $_GET['skin'] : get_default_skin();

	if (empty($_GET['skin']))
	{
		$request = wesql::query('
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({literal:name}, {literal:theme_dir}, {literal:theme_templates})
				AND id_member = {int:no_member}',
			array(
				'no_member' => 0,
			)
		);
		$context['themes'] = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if (!isset($context['themes'][$row['id_theme']]))
				$context['themes'][$row['id_theme']] = array(
					'id' => $row['id_theme'],
					'num_default_options' => 0,
					'num_members' => 0,
				);
			$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
		}
		wesql::free_result($request);

		foreach ($context['themes'] as $key => $th)
		{
			// There has to be a Settings template!
			if (!file_exists($th['theme_dir'] . '/index.template.php') && !file_exists($th['theme_dir'] . '/skins/index.css'))
				unset($context['themes'][$key]);
			else
			{
				if (!isset($th['theme_templates']))
					$templates = array('index');
				else
					$templates = explode(',', $th['theme_templates']);

				foreach ($templates as $template)
					if (file_exists($th['theme_dir'] . '/' . $template . '.template.php'))
					{
						// Fetch the header... a good 256 bytes should be more than enough.
						$fp = fopen($th['theme_dir'] . '/' . $template . '.template.php', 'rb');
						$header = fread($fp, 256);
						fclose($fp);

						// Can we find a version comment, at all?
						if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
						{
							$ver = $match[1];
							if (!isset($context['themes'][$key]['version']) || $context['themes'][$key]['version'] > $ver)
								$context['themes'][$key]['version'] = $ver;
						}
					}

				$context['themes'][$key]['can_edit_style'] = file_exists($th['theme_dir'] . '/skins/index.css');
			}
		}

		wetem::load('edit_list');

		return 'no_themes';
	}

	$context['session_error'] = false;

	// Get the directory of the theme we are editing.
	$theme_dir = TEMPLATES_DIR;

	if (!isset($_REQUEST['filename']))
	{
		if (isset($_GET['directory']))
		{
			if (substr($_GET['directory'], 0, 1) == '.')
				$_GET['directory'] = '';
			else
			{
				$_GET['directory'] = preg_replace(array('~^[./\\:\0\n\r]+~', '~[\\\\]~', '~/[./]+~'), array('', '/', '/'), $_GET['directory']);

				$temp = realpath($theme_dir . '/' . $_GET['directory']);
				if (empty($temp) || substr($temp, 0, strlen(realpath($theme_dir))) != realpath($theme_dir))
					$_GET['directory'] = '';
			}
		}

		if (isset($_GET['directory']) && $_GET['directory'] != '')
		{
			$context['theme_files'] = get_file_listing($theme_dir . '/' . $_GET['directory'], $_GET['directory'] . '/');

			$temp = dirname($_GET['directory']);
			array_unshift($context['theme_files'], array(
				'filename' => $temp == '.' || $temp == '' ? '/ (..)' : $temp . ' (..)',
				'is_writable' => is_writable($theme_dir . '/' . $temp),
				'is_directory' => true,
				'is_template' => false,
				'is_image' => false,
				'is_editable' => false,
				'href' => '<URL>?action=admin;area=theme;skin=' . $_GET['skin'] . ';' . $context['session_query'] . ';sa=edit;directory=' . $temp,
				'size' => '',
			));
		}
		else
			$context['theme_files'] = get_file_listing($theme_dir, '');

		wetem::load('edit_browse');

		return;
	}
	else
	{
		if (substr($_REQUEST['filename'], 0, 1) == '.')
			$_REQUEST['filename'] = '';
		else
		{
			$_REQUEST['filename'] = preg_replace(array('~^[./\\:\0\n\r]+~', '~[\\\\]~', '~/[./]+~'), array('', '/', '/'), $_REQUEST['filename']);

			$temp = realpath($theme_dir . '/' . $_REQUEST['filename']);
			if (empty($temp) || substr($temp, 0, strlen(realpath($theme_dir))) != realpath($theme_dir))
				$_REQUEST['filename'] = '';
		}

		if (empty($_REQUEST['filename']))
			fatal_lang_error('theme_edit_missing', false);
	}

	if (isset($_POST['save']))
	{
		if (checkSession('post', '', false) == '')
		{
			if (is_array($_POST['entire_file']))
				$_POST['entire_file'] = implode("\n", $_POST['entire_file']);
			$_POST['entire_file'] = rtrim(strtr($_POST['entire_file'], array("\r" => '', '   ' => "\t")));

			// Check for a parse error!
			if (substr($_REQUEST['filename'], -13) == '.template.php' && is_writable($theme_dir) && ini_get('display_errors'))
			{
				$fp = fopen($theme_dir . '/tmp_' . session_id() . '.php', 'w');
				fwrite($fp, $_POST['entire_file']);
				fclose($fp);

				// !!! Use Class-WebGet()?
				$error = @file_get_contents(TEMPLATES . '/tmp_' . session_id() . '.php');
				$error = strtr($error, array('<b>' => '<strong>', '</b>' => '</strong>'));
				if (preg_match('~ <strong>\d+</strong><br\s*/?\>$~i', $error) != 0)
					$error_file = $theme_dir . '/tmp_' . session_id() . '.php';
				else
					unlink($theme_dir . '/tmp_' . session_id() . '.php');
			}

			if (!isset($error_file))
			{
				$fp = fopen($theme_dir . '/' . $_REQUEST['filename'], 'w');
				fwrite($fp, $_POST['entire_file']);
				fclose($fp);

				redirectexit('action=admin;area=theme;skin=' . $_GET['skin'] . ';' . $context['session_query'] . ';sa=edit;directory=' . dirname($_REQUEST['filename']));
			}
		}
		// Session timed out.
		else
		{
			loadLanguage('Errors');

			$context['session_error'] = true;
			wetem::load('edit_file');

			// Recycle the submitted data.
			$context['entire_file'] = htmlspecialchars($_POST['entire_file']);

			// You were able to submit it, so it's reasonable to assume you are allowed to save.
			$context['allow_save'] = true;

			return;
		}
	}

	$context['allow_save'] = is_writable($theme_dir . '/' . $_REQUEST['filename']);
	$context['allow_save_filename'] = strtr($theme_dir . '/' . $_REQUEST['filename'], array(ROOT_DIR => '...'));
	$context['edit_filename'] = htmlspecialchars($_REQUEST['filename']);

	if (substr($_REQUEST['filename'], -4) == '.css')
	{
		wetem::load('edit_style');

		$context['entire_file'] = htmlspecialchars(strtr(file_get_contents($theme_dir . '/' . $_REQUEST['filename']), array("\t" => '   ')));
	}
	elseif (substr($_REQUEST['filename'], -13) == '.template.php')
	{
		wetem::load('edit_template');

		if (!isset($error_file))
			$file_data = file($theme_dir . '/' . $_REQUEST['filename']);
		else
		{
			if (preg_match('~(<strong>.+?</strong>:.+?<strong>).+?(</strong>.+?<strong>\d+</strong>)<br\s*/?\>$~i', $error, $match) != 0)
				$context['parse_error'] = $match[1] . $_REQUEST['filename'] . $match[2];
			$file_data = file($error_file);
			unlink($error_file);
		}

		$j = 0;
		$context['file_parts'] = array(array('lines' => 0, 'line' => 1, 'data' => ''));
		for ($i = 0, $n = count($file_data); $i < $n; $i++)
		{
			if (isset($file_data[$i + 1]) && substr($file_data[$i + 1], 0, 9) == 'function ')
			{
				// Try to format the functions a little nicer...
				$context['file_parts'][$j]['data'] = trim($context['file_parts'][$j]['data']) . "\n";

				if (empty($context['file_parts'][$j]['lines']))
					unset($context['file_parts'][$j]);
				$context['file_parts'][++$j] = array('lines' => 0, 'line' => $i + 1, 'data' => '');
			}

			$context['file_parts'][$j]['lines']++;
			$context['file_parts'][$j]['data'] .= htmlspecialchars(strtr($file_data[$i], array("\t" => '   ')));
		}

		$context['entire_file'] = htmlspecialchars(strtr(implode('', $file_data), array("\t" => '   ')));
	}
	else
	{
		wetem::load('edit_file');

		$context['entire_file'] = htmlspecialchars(strtr(file_get_contents($theme_dir . '/' . $_REQUEST['filename']), array("\t" => '   ')));
	}
}

function get_file_listing($path, $relative)
{
	global $txt, $context;

	// Is it even a directory?
	if (!is_dir($path))
		fatal_lang_error('error_invalid_dir', 'critical');

	$dir = dir($path);
	$entries = array();
	while ($entry = $dir->read())
		$entries[] = $entry;
	$dir->close();

	natcasesort($entries);

	$listing1 = array();
	$listing2 = array();

	foreach ($entries as $entry)
	{
		// Skip all dot files, including .htaccess.
		if (substr($entry, 0, 1) == '.')
			continue;

		if (is_dir($path . '/' . $entry))
			$listing1[] = array(
				'filename' => $entry,
				'is_writable' => is_writable($path . '/' . $entry),
				'is_directory' => true,
				'is_template' => false,
				'is_image' => false,
				'is_editable' => false,
				'href' => '<URL>?action=admin;area=theme;skin=' . $_GET['skin'] . ';' . $context['session_query'] . ';sa=edit;directory=' . $relative . $entry,
				'size' => '',
			);
		else
		{
			$size = filesize($path . '/' . $entry);
			if ($size > 2048 || $size == 1024)
				$size = comma_format($size / 1024) . ' ' . $txt['themeadmin_edit_kilobytes'];
			else
				$size = comma_format($size) . ' ' . $txt['themeadmin_edit_bytes'];

			$listing2[] = array(
				'filename' => $entry,
				'is_writable' => is_writable($path . '/' . $entry),
				'is_directory' => false,
				'is_template' => preg_match('~\.template\.php$~', $entry) != 0,
				'is_image' => preg_match('~\.(jpg|jpeg|gif|bmp|png)$~', $entry) != 0,
				'is_editable' => is_writable($path . '/' . $entry) && preg_match('~\.(php|pl|css|js|vbs|xml|xslt|txt|xsl|html|htm|shtm|shtml|asp|aspx|cgi|py)$~', $entry) != 0,
				'href' => '<URL>?action=admin;area=theme;skin=' . $_GET['skin'] . ';' . $context['session_query'] . ';sa=edit;filename=' . $relative . $entry,
				'size' => $size,
				'last_modified' => timeformat(filemtime($path . '/' . $entry)),
			);
		}
	}

	return array_merge($listing1, $listing2);
}

function CopyTemplate()
{
	global $context;

	isAllowedTo('admin_forum');
	loadTemplate('Themes');

	$context[$context['admin_menu_name']]['current_subsection'] = 'edit';

	$_GET['skin'] = !empty($_GET['skin']) ? $_GET['skin'] : get_default_skin();

	$theme_dir = TEMPLATES_DIR;

	if (isset($_REQUEST['template']) && preg_match('~[./\\\\:\0]~', $_REQUEST['template']) == 0)
	{
		if (file_exists(TEMPLATES_DIR . '/' . $_REQUEST['template'] . '.template.php'))
			$filename = TEMPLATES_DIR . '/' . $_REQUEST['template'] . '.template.php';
		else
			fatal_lang_error('no_access', false);

		$fp = fopen($theme_dir . '/' . $_REQUEST['template'] . '.template.php', 'w');
		fwrite($fp, file_get_contents($filename));
		fclose($fp);

		redirectexit('action=admin;area=theme;' . $context['session_query'] . ';sa=copy');
	}
	elseif (isset($_REQUEST['lang_file']) && preg_match('~^[^./\\\\:\0]\.[^./\\\\:\0]$~', $_REQUEST['lang_file']) != 0)
	{
		if (file_exists(TEMPLATES_DIR . '/languages/' . $_REQUEST['template'] . '.php'))
			$filename = TEMPLATES_DIR . '/languages/' . $_REQUEST['template'] . '.php';
		else
			fatal_lang_error('no_access', false);

		$fp = fopen($theme_dir . '/languages/' . $_REQUEST['lang_file'] . '.php', 'w');
		fwrite($fp, file_get_contents($filename));
		fclose($fp);

		redirectexit('action=admin;area=theme;' . $context['session_query'] . ';sa=copy');
	}

	$templates = array();
	$lang_files = array();

	$dir = dir(TEMPLATES_DIR);
	while ($entry = $dir->read())
		if (substr($entry, -13) == '.template.php')
			$templates[] = substr($entry, 0, -13);
	$dir->close();

	$dir = dir(TEMPLATES_DIR . '/languages');
	while ($entry = $dir->read())
		if (preg_match('~^([^.]+\.[^.]+)\.php$~', $entry, $matches))
			$lang_files[] = $matches[1];
	$dir->close();

	natcasesort($templates);
	natcasesort($lang_files);

	$context['available_templates'] = array();
	foreach ($templates as $template)
		$context['available_templates'][$template] = array(
			'filename' => $template . '.template.php',
			'value' => $template,
			'already_exists' => false,
			'can_copy' => is_writable($theme_dir),
		);
	$context['available_language_files'] = array();
	foreach ($lang_files as $file)
		$context['available_language_files'][$file] = array(
			'filename' => $file . '.php',
			'value' => $file,
			'already_exists' => false,
			'can_copy' => file_exists($theme_dir . '/languages') ? is_writable($theme_dir . '/languages') : is_writable($theme_dir),
		);

	$dir = dir($theme_dir);
	while ($entry = $dir->read())
	{
		if (substr($entry, -13) == '.template.php' && isset($context['available_templates'][substr($entry, 0, -13)]))
		{
			$context['available_templates'][substr($entry, 0, -13)]['already_exists'] = true;
			$context['available_templates'][substr($entry, 0, -13)]['can_copy'] = is_writable($theme_dir . '/' . $entry);
		}
	}
	$dir->close();

	if (file_exists($theme_dir . '/languages'))
	{
		$dir = dir($theme_dir . '/languages');
		while ($entry = $dir->read())
		{
			if (preg_match('~^([^.]+\.[^.]+)\.php$~', $entry, $matches) && isset($context['available_language_files'][$matches[1]]))
			{
				$context['available_language_files'][$matches[1]]['already_exists'] = true;
				$context['available_language_files'][$matches[1]]['can_copy'] = is_writable($theme_dir . '/languages/' . $entry);
			}
		}
		$dir->close();
	}

	wetem::load('copy_template');
}

/**
 * Get a list of all skins available for a given theme folder.
 */
function wedge_get_skin_list($linear = false)
{
	global $context, $settings;

	$skin_list = cache_get_data('wedge_skin_list', 180);
	if ($skin_list !== null)
		return $skin_list[$linear ? 0 : 1];

	$files = glob(SKINS_DIR . '/*', GLOB_ONLYDIR);
	$skin_list = $flat = array('' => array(
		'name' => 'Weaving',
		'type' => 'replace',
		'parent' => null,
		'comment' => '',
		'has_templates' => false,
		'enabled' => empty($settings['disabled_skins']['/']),
		'num_users' => isset($context['skin_user_counts']['/']) ? $context['skin_user_counts']['/'] : 0,
		'dir' => '/',
	));

	foreach ($files as $this_dir)
	{
		$file = basename($this_dir);
		if ($file[0] === '.')
			continue;

		$these_files = scandir($this_dir);
		$is_valid = false;
		foreach ($these_files as $test)
		{
			if ($test === 'skin.xml' || substr($test, -4) === '.css')
			{
				$is_valid = true;
				break;
			}
		}
		// We need to have at least one .css file *or* skin.xml for a skin to be valid.
		if (!$is_valid)
			continue;

		// I'm not actually parsing this XML-style... Mwahaha! I'm evil.
		$setxml = in_array('skin.xml', $these_files) ? file_get_contents($this_dir . '/skin.xml') : '';
		$skin = array(
			'name' => $setxml && preg_match('~<name>(?:<!\[CDATA\[)?(.*?)(?:]]>)?</name>~sui', $setxml, $match) ? trim($match[1]) : $file,
			'type' => $setxml && preg_match('~<type>(.*?)</type>~sui', $setxml, $match) ? trim($match[1]) : 'add',
			'parent' => $setxml && preg_match('~<parent>(.*?)</parent>~sui', $setxml, $match) ? trim($match[1]) : '',
			'comment' => $setxml && preg_match('~<comment>(?:<!\[CDATA\[)?(.*?)(?:]]>)?</comment>~sui', $setxml, $match) ? trim($match[1]) : '',
			'has_templates' => in_array('html', $these_files) && is_dir($this_dir . '/html'),
		);
		$skin['dir'] = str_replace(SKINS_DIR . '/', '', $this_dir);
		$skin['enabled'] = empty($settings['disabled_skins'][$skin['dir']]);
		$skin['num_users'] = isset($context['skin_user_counts'][$skin['dir']]) ? $context['skin_user_counts'][$skin['dir']] : 0;
		$flat[$skin['dir']] = $skin;

		// Nested lists without recursion? Easy-peasy!
		$entry =& $skin_list[$skin['dir']];
		$entry = isset($entry) ? array_merge($skin, $entry) : $skin;

		if ($skin['type'] === 'replace')
			$skin_list[$skin['dir']] =& $entry;
		else
			$skin_list[$skin['parent']]['skins'][$skin['dir']] =& $entry;
	}

	foreach ($skin_list as $id => $skin)
		if ($skin['type'] === 'add')
			unset($skin_list[$id]);

	// Get the theme name and descriptions.
	$nested = array(
		'num_users' => isset($context['skin_user_counts']['']) ? $context['skin_user_counts'][''] : 0,
		'skins' => $skin_list,
	);

	// !! Should we cache both in the same entry..?
	cache_put_data('wedge_skin_list', array($flat, $nested), 180);
	return $linear ? $flat : $nested;
}

/**
 * Return a list of <option> variables for use in Themes and ManageBoard templates.
 * $show_defaults will add an indicator next to default (desktop and mobile) skins.
 */
function wedge_show_skins(&$style, $show_defaults = false, $current_skin = '', $filler = '')
{
	global $context, $settings, $txt;

	if ($current_skin === '')
		$current_skin = $context['skin_actual'];
	$last = count($style);
	$current = 1;
	$output = '';
	foreach ($style as $sty)
	{
		$intro = !$show_defaults || $filler ? '&lt;span class=mono&gt;' . $filler . ($current == $last ? '&#9492;' : '&#9500;') . '&mdash;&nbsp;&lt;/span&gt;' : '';
		$output .= '<option value="' . westr::safe($sty['dir']) . '"' . ($current_skin == $sty['dir'] ? ' selected' : '') . '>' . $intro . $sty['name'];
		$context['skin_names'][$sty['dir']] = $sty['name'];
		if ($show_defaults)
		{
			if ($sty['dir'] == $settings['theme_skin_guests'])
				$output .= ' &lt;small&gt;' . $txt['skin_default'] . '&lt;/small&gt;';
			elseif ($sty['dir'] == $settings['theme_skin_guests_mobile'])
				$output .= ' &lt;small&gt;' . $txt['skin_default_mobile'] . '&lt;/small&gt;';
		}
		$output .= '</option>';
		if (!empty($sty['skins']))
			$output .= wedge_show_skins($sty['skins'], $show_defaults, $current_skin, $current == $last ? $filler . '&nbsp;&nbsp;&nbsp;' : $filler . '&#9130;&nbsp;&nbsp;');
		$current++;
	}
	return $output;
}

function wedge_update_skin($mem, $skin)
{
	if (we::is('mobile'))
		updateMemberData($mem, array(
			'skin_mobile' => $skin,
		));
	else
		updateMemberData($mem, array(
			'skin' => $skin,
		));
}
