<?php
/**
 * Wedge
 *
 * Provides theme administration handling.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file concerns itself almost completely with theme administration.
	Its tasks include changing theme settings, installing and removing
	themes, choosing the current theme, and editing themes. This is done in:

	void ThemesMain()
		- manages the action and delegates control to the proper sub action.
		- loads both the Themes and Settings language files.
		- checks the session by GET or POST to verify the sent data.
		- requires the user not be a guest.
		- is accessed via ?action=admin;area=theme.

	void ThemeAdmin()
		- administrates themes and their settings, as well as global theme
		  settings.
		- sets the settings theme_allow, theme_guests, and knownThemes.
		- loads the template Themes.
		- requires the admin_forum permission.
		- accessed with ?action=admin;area=theme;sa=admin.

	void ThemeList()
		- lists the available themes.
		- provides an interface to reset the paths of all the installed themes.

	void SetThemeOptions()
		// !!!

	void SetThemeSettings()
		- saves and requests global theme settings. ($theme)
		- loads the Admin language file.
		- calls ThemeAdmin() if no theme is specified. (the theme center.)
		- requires an administrator.
		- accessed with ?action=admin;area=theme;sa=settings&th=xx.

	void RemoveTheme()
		- removes an installed theme.
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
		- puts themes in $boardurl/Themes.
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
		- if the images dir isn't images, specify in the images element.
		- any extra rows for themes should go in extra, serialized,
		  as in "array(variable => value)".
		- tar and gzip the directory - and you're done!
		- please include any special license in a license.txt file.
	// !!! Thumbnail?
*/

// Subaction handler.
function ThemesMain()
{
	global $txt, $context, $scripturl;

	// Load the important language files...
	loadLanguage('Themes');
	loadLanguage('Settings');

	// No funny business - guests only.
	is_not_guest();

	// Default the page title to Theme Administration by default.
	$context['page_title'] = $txt['themeadmin_title'];

	// Theme administration, removal, choice, or installation...
	$subActions = array(
		'admin' => 'ThemeAdmin',
		'list' => 'ThemeList',
		'settings' => 'SetThemeSettings',
		'install' => 'ThemeInstall',
		'remove' => 'RemoveTheme',
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
	global $context, $boarddir, $settings;

	loadLanguage('Admin');
	isAllowedTo('admin_forum');

	// If we aren't submitting - that is, if we are about to...
	if (!isset($_POST['save']))
	{
		loadTemplate('Themes');

		// Make our known themes a little easier to work with.
		$knownThemes = !empty($settings['knownThemes']) ? explode(',', $settings['knownThemes']) : array();

		// Load up all the themes.
		$request = wesql::query('
			SELECT id_theme, value AS name
			FROM {db_prefix}themes
			WHERE variable = {string:name}
				AND id_member = 0
			ORDER BY id_theme',
			array(
				'name' => 'name',
			)
		);
		$context['themes'] = array();
		while ($row = wesql::fetch_assoc($request))
			$context['themes'][$row['id_theme']] = array(
				'id' => $row['id_theme'],
				'name' => $row['name'],
				'known' => in_array($row['id_theme'], $knownThemes),
			);
		wesql::free_result($request);

		// While we're at it, get all skins...
		$request = wesql::query('
			SELECT id_theme, value AS dir
			FROM {db_prefix}themes
			WHERE variable = {string:dir}
				AND id_member = 0',
			array(
				'dir' => 'theme_dir',
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$context['themes'][$row['id_theme']]['skins'] = wedge_get_skin_list($row['dir'] . '/skins');
		wesql::free_result($request);

		// Can we create a new theme?
		$context['can_create_new'] = is_writable($boarddir . '/Themes');
		$context['new_theme_dir'] = substr(realpath($boarddir . '/Themes/default'), 0, -7);

		// Look for a non existent theme directory. (i.e. theme87.)
		$theme_dir = $boarddir . '/Themes/theme';
		$i = 1;
		while (file_exists($theme_dir . $i))
			$i++;
		$context['new_theme_name'] = 'theme' . $i;
	}
	else
	{
		checkSession();

		if (isset($_POST['options']['known_themes']))
			foreach ($_POST['options']['known_themes'] as $key => $id)
				$_POST['options']['known_themes'][$key] = (int) $id;
		else
			fatal_lang_error('themes_none_selectable', false);

		if (!in_array($_POST['options']['theme_guests'], $_POST['options']['known_themes']))
				fatal_lang_error('themes_default_selectable', false);

		$arrh = explode('_', $_POST['options']['theme_guests']);
		$arrm = explode('_', $_POST['options']['theme_guests_mobile']);

		// Commit the new settings.
		updateSettings(array(
			'theme_allow' => $_POST['options']['theme_allow'],
			'theme_guests' => $arrh[0],
			'theme_guests_mobile' => $arrm[0],
			'theme_skin_guests' => isset($arrh[1]) ? base64_decode($arrh[1]) : 'skins',
			'theme_skin_guests_mobile' => isset($arrm[1]) ? base64_decode($arrm[1]) : 'skins/Wireless',
			'knownThemes' => implode(',', $_POST['options']['known_themes']),
		));

		if (!empty($_POST['theme_reset']))
		{
			$reset = explode('_', $_POST['theme_reset']);
			if ((int) $reset[0] === 0 || in_array($reset[0], $_POST['options']['known_themes']))
				wedge_update_skin(null, (int) $reset[0], isset($reset[1]) ? base64_decode($reset[1]) : '');
		}

		redirectexit('action=admin;area=theme;' . $context['session_query'] . ';sa=admin');
	}
}

function ThemeList()
{
	global $context, $boarddir, $boardurl;

	loadLanguage('Admin');
	isAllowedTo('admin_forum');

	if (isset($_POST['save']))
	{
		checkSession();

		$request = wesql::query('
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({string:theme_dir}, {string:theme_url}, {string:images_url}, {string:base_theme_dir}, {string:base_theme_url}, {string:base_images_url})
				AND id_member = {int:no_member}',
			array(
				'no_member' => 0,
				'theme_dir' => 'theme_dir',
				'theme_url' => 'theme_url',
				'images_url' => 'images_url',
				'base_theme_dir' => 'base_theme_dir',
				'base_theme_url' => 'base_theme_url',
				'base_images_url' => 'base_images_url',
			)
		);
		$themes = array();
		while ($row = wesql::fetch_assoc($request))
			$themes[$row['id_theme']][$row['variable']] = $row['value'];
		wesql::free_result($request);

		$setValues = array();
		foreach ($themes as $id => $th)
		{
			if (file_exists($_POST['reset_dir'] . '/' . basename($th['theme_dir'])))
			{
				$setValues[] = array($id, 0, 'theme_dir', realpath($_POST['reset_dir'] . '/' . basename($th['theme_dir'])));
				$setValues[] = array($id, 0, 'theme_url', $_POST['reset_url'] . '/' . basename($th['theme_dir']));
				$setValues[] = array($id, 0, 'images_url', $_POST['reset_url'] . '/' . basename($th['theme_dir']) . '/' . basename($th['images_url']));
			}

			if (isset($th['base_theme_dir']) && file_exists($_POST['reset_dir'] . '/' . basename($th['base_theme_dir'])))
			{
				$setValues[] = array($id, 0, 'base_theme_dir', realpath($_POST['reset_dir'] . '/' . basename($th['base_theme_dir'])));
				$setValues[] = array($id, 0, 'base_theme_url', $_POST['reset_url'] . '/' . basename($th['base_theme_dir']));
				$setValues[] = array($id, 0, 'base_images_url', $_POST['reset_url'] . '/' . basename($th['base_theme_dir']) . '/' . basename($th['base_images_url']));
			}

			cache_put_data('theme_settings-' . $id, null, 90);
		}

		if (!empty($setValues))
		{
			wesql::insert('replace',
				'{db_prefix}themes',
				array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$setValues,
				array('id_theme', 'variable', 'id_member')
			);
		}

		redirectexit('action=admin;area=theme;sa=list;' . $context['session_query']);
	}

	loadTemplate('Themes');

	$request = wesql::query('
		SELECT id_theme, variable, value
		FROM {db_prefix}themes
		WHERE variable IN ({string:name}, {string:theme_dir}, {string:theme_url}, {string:images_url})
			AND id_member = {int:no_member}',
		array(
			'no_member' => 0,
			'name' => 'name',
			'theme_dir' => 'theme_dir',
			'theme_url' => 'theme_url',
			'images_url' => 'images_url',
		)
	);
	$context['themes'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		if (!isset($context['themes'][$row['id_theme']]))
			$context['themes'][$row['id_theme']] = array(
				'id' => $row['id_theme'],
			);
		$context['themes'][$row['id_theme']][$row['variable']] = $row['value'];
	}
	wesql::free_result($request);

	foreach ($context['themes'] as $i => $th)
	{
		$context['themes'][$i]['theme_dir'] = realpath($context['themes'][$i]['theme_dir']);

		if (file_exists($context['themes'][$i]['theme_dir'] . '/index.template.php'))
		{
			// Fetch the header... a good 256 bytes should be more than enough.
			$fp = fopen($context['themes'][$i]['theme_dir'] . '/index.template.php', 'rb');
			$header = fread($fp, 256);
			fclose($fp);

			// Can we find a version comment, at all?
			if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
				$context['themes'][$i]['version'] = $match[1];
		}

		$context['themes'][$i]['valid_path'] = file_exists($context['themes'][$i]['theme_dir']) && is_dir($context['themes'][$i]['theme_dir']);
	}

	$context['reset_dir'] = realpath($boarddir . '/Themes');
	$context['reset_url'] = $boardurl . '/Themes';

	wetem::load('list_themes');
}

// Administrative global settings.
function SetThemeSettings()
{
	global $txt, $context, $theme, $settings;

	if (empty($_GET['th']) && empty($_GET['id']))
		return ThemeAdmin();
	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	// Select the best fitting tab.
	$context[$context['admin_menu_name']]['current_subsection'] = 'list';

	loadLanguage('Admin');
	isAllowedTo('admin_forum');

	// Validate inputs/user.
	if (empty($_GET['th']))
		fatal_lang_error('no_theme', false);

	// Fetch the smiley sets...
	$sets = explode(',', 'none,' . $settings['smiley_sets_known']);
	$set_names = explode("\n", $txt['smileys_none'] . "\n" . $settings['smiley_sets_names']);
	$context['smiley_sets'] = array(
		'' => $txt['smileys_no_default']
	);
	foreach ($sets as $i => $set)
		$context['smiley_sets'][$set] = htmlspecialchars($set_names[$i]);

	$old_id = $theme['theme_id'];
	$old_settings = $theme;

	loadTheme($_GET['th'], false);

	// Sadly we really do need to init the template.
	execBlock('init', 'ignore');

	// Also load the actual themes language file - in case of special settings.
	loadLanguage('Settings', '', true, true);
	// And the custom language strings...
	loadLanguage('ThemeStrings', '', false, true);

	// Let the theme take care of the settings.
	loadTemplate('Settings');
	execBlock('settings');

	// Submitting!
	if (isset($_POST['save']))
	{
		checkSession();

		if (empty($_POST['options']))
			$_POST['options'] = array();
		if (empty($_POST['default_options']))
			$_POST['default_options'] = array();

		// Make sure items are cast correctly.
		foreach ($context['theme_settings'] as $item)
		{
			// Disregard this item if this is just a separator.
			if (!is_array($item))
				continue;

			foreach (array('options', 'default_options') as $option)
			{
				if (!isset($_POST[$option][$item['id']]))
					continue;
				// Checkbox.
				elseif (empty($item['type']))
					$_POST[$option][$item['id']] = $_POST[$option][$item['id']] ? 1 : 0;
				// Number
				elseif ($item['type'] == 'number')
					$_POST[$option][$item['id']] = (int) $_POST[$option][$item['id']];
			}
		}

		// Set up the sql query.
		$inserts = array();
		foreach ($_POST['options'] as $opt => $val)
			$inserts[] = array(0, $_GET['th'], $opt, is_array($val) ? implode(',', $val) : $val);
		foreach ($_POST['default_options'] as $opt => $val)
			$inserts[] = array(0, 1, $opt, is_array($val) ? implode(',', $val) : $val);
		// If we're actually inserting something..
		if (!empty($inserts))
		{
			wesql::insert('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$inserts,
				array('id_member', 'id_theme', 'variable')
			);
		}

		cache_put_data('theme_settings-' . $_GET['th'], null, 90);
		cache_put_data('theme_settings-1', null, 90);

		// Invalidate the cache.
		updateSettings(array('settings_updated' => time()));

		redirectexit('action=admin;area=theme;sa=settings;th=' . $_GET['th'] . ';' . $context['session_query']);
	}

	wetem::load('set_settings');
	$context['page_title'] = $txt['theme_settings'];

	foreach ($theme as $setting => $dummy)
		if (!in_array($setting, array('theme_url', 'theme_dir', 'images_url', 'template_dirs')))
			$theme[$setting] = htmlspecialchars__recursive($theme[$setting]);

	$context['settings'] = $context['theme_settings'];
	$context['theme_settings'] = $theme;

	foreach ($context['settings'] as $i => $setting)
	{
		// Separators are dummies, so leave them alone.
		if (!is_array($setting))
			continue;

		if (!isset($setting['type']) || $setting['type'] == 'bool')
			$context['settings'][$i]['type'] = 'checkbox';
		elseif ($setting['type'] == 'int' || $setting['type'] == 'integer')
			$context['settings'][$i]['type'] = 'number';
		elseif ($setting['type'] == 'string')
			$context['settings'][$i]['type'] = 'text';

		if (isset($setting['options']))
			$context['settings'][$i]['type'] = 'list';

		$context['settings'][$i]['value'] = !isset($theme[$setting['id']]) ? '' : $theme[$setting['id']];
	}

	// Restore the current theme.
	loadTheme($old_id, false);

	// Reinit just incase.
	execBlock('init', 'ignore');

	$theme = $old_settings;

	loadTemplate('Themes');
}

// Remove a theme from the database.
function RemoveTheme()
{
	global $settings, $context;

	checkSession('get');

	isAllowedTo('admin_forum');

	// The theme's ID must be an integer.
	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	// You can't delete the default theme!
	if ($_GET['th'] == 1)
		fatal_lang_error('no_access', false);

	$known = explode(',', $settings['knownThemes']);
	for ($i = 0, $n = count($known); $i < $n; $i++)
		if ($known[$i] == $_GET['th'])
			unset($known[$i]);

	wesql::query('
		DELETE FROM {db_prefix}themes
		WHERE id_theme = {int:current_theme}',
		array(
			'current_theme' => $_GET['th'],
		)
	);

	wesql::query('
		UPDATE {db_prefix}members
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $_GET['th'],
		)
	);

	wesql::query('
		UPDATE {db_prefix}boards
		SET id_theme = {int:default_theme}
		WHERE id_theme = {int:current_theme}',
		array(
			'default_theme' => 0,
			'current_theme' => $_GET['th'],
		)
	);

	$known = strtr(implode(',', $known), array(',,' => ','));

	// Fix it if the theme was the overall default theme.
	$upd = array('knownThemes' => $known);
	if ($settings['theme_guests'] == $_GET['th'])
		$upd['theme_guests'] = '1';
	if ($settings['theme_guests_mobile'] == $_GET['th'])
		$upd['theme_guests_mobile'] = '1';

	updateSettings($upd);

	redirectexit('action=admin;area=theme;sa=list;' . $context['session_query']);
}

// Choose a theme from a list.
function PickTheme()
{
	global $txt, $context, $settings, $user_info, $language, $theme, $scripturl;

	loadLanguage('Themes');
	loadTemplate('Themes');

	$u = isset($_REQUEST['u']) ? $_REQUEST['u'] : null;

	// Build the link tree.
	$context['linktree'][] = array(
		'url' => $u === null ? $scripturl . '?action=skin' : ($scripturl . ($u > 0 ? '?action=skin;u=' : '?action=theme;sa=pick;u=') . (int) $u),
		'name' => $txt['change_skin'],
	);

	$_SESSION['id_theme'] = 0;
	$_SESSION['skin'] = '';

	// Have we made a decision, or are we just browsing?
	if (isset($_GET['th']))
	{
		checkSession('get');

		$th = explode('_', $_GET['th']);
		$id = (int) $th[0];
		$css = isset($th[1]) ? base64_decode($th[1]) : '';

		// Save for this user.
		if ($u === null || !allowedTo('admin_forum'))
		{
			wedge_update_skin($user_info['id'], $id, $css);
			redirectexit('action=skin');
		}

		// For everyone.
		if ($u == '0')
		{
			wedge_update_skin(null, $id, $css);
			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_query']);
		}
		// Change the default/guest theme.
		elseif ($u == '-1')
		{
			// Let's assume the admin is in mobile mode. Meaning they want to change the default mobile skin...
			if (!empty($user_info['is_mobile']))
				updateSettings(array(
					'theme_guests_mobile' => $id,
					'theme_skin_guests_mobile' => $css
				));
			else
				updateSettings(array(
					'theme_guests' => $id,
					'theme_skin_guests' => $css
				));
			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_query']);
		}
		// Change a specific member's theme.
		else
		{
			wedge_update_skin((int) $u, $id, $css);
			redirectexit('action=skin;u=' . (int) $u);
		}
	}

	// Figure out who the current member is, and what theme they've chosen.
	if ($u === null || !allowedTo('admin_forum'))
	{
		$context['specify_member'] = '';
		$context['current_theme'] = $user_info['theme'];
		$context['current_skin'] = empty($user_info['theme']) ? '' : $user_info['skin'];
	}
	// Everyone can't choose just one.
	elseif ($u == '0')
	{
		$context['specify_member'] = ';u=0';
		$context['current_theme'] = 0;
		$context['current_skin'] = '';
	}
	// Guests and such...
	elseif ($u == '-1')
	{
		$context['specify_member'] = ';u=-1';
		$context['current_theme'] = !empty($user_info['is_mobile']) ? $settings['theme_guests_mobile'] : $settings['theme_guests'];
		$context['current_skin'] = !empty($user_info['is_mobile']) ? $settings['theme_skin_guests_mobile'] : $settings['theme_skin_guests'];
	}
	// Someone else :P
	else
	{
		$context['specify_member'] = ';u=' . (int) $u;

		$request = wesql::query('
			SELECT ' . (!empty($user_info['is_mobile']) ? 'id_theme_mobile, skin_mobile' : 'id_theme, skin') . '
			FROM {db_prefix}members
			WHERE id_member = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => (int) $u,
			)
		);
		list ($context['current_theme'], $context['current_skin']) = wesql::fetch_row($request);
		wesql::free_result($request);
	}

	// Get the theme name and descriptions.
	$context['available_themes'] = array();
	if (!empty($settings['knownThemes']))
	{
		$request = wesql::query('
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({string:name}, {string:theme_url}, {string:theme_dir}, {string:images_url})' . (!allowedTo('admin_forum') ? '
				AND id_theme IN ({array_string:known_themes})' : '') . '
				AND id_theme != {int:default_theme}
				AND id_member = {int:no_member}',
			array(
				'default_theme' => 0,
				'name' => 'name',
				'no_member' => 0,
				'theme_url' => 'theme_url',
				'theme_dir' => 'theme_dir',
				'images_url' => 'images_url',
				'known_themes' => explode(',', $settings['knownThemes']),
			)
		);
		while ($row = wesql::fetch_assoc($request))
		{
			if (!isset($context['available_themes'][$row['id_theme']]))
				$context['available_themes'][$row['id_theme']] = array(
					'id' => $row['id_theme'],
					'selected' => $context['current_theme'] == $row['id_theme'],
					'num_users' => 0
				);
			$context['available_themes'][$row['id_theme']][$row['variable']] = $row['value'];
			if ($row['variable'] == 'theme_dir')
				$context['available_themes'][$row['id_theme']]['skins'] = wedge_get_skin_list($row['value'] . '/skins');
		}
		wesql::free_result($request);
	}

	// Okay, this is a complicated problem: the default theme is 1, but they aren't allowed to access 1!
	if (!isset($context['available_themes'][$settings['theme_guests']]))
	{
		$context['available_themes'][0] = array(
			'num_users' => 0
		);
		$guest_theme = 0;
	}
	else
		$guest_theme = $settings['theme_guests'];

	$request = wesql::query('
		SELECT id_theme, COUNT(*) AS the_count
		FROM {db_prefix}members
		GROUP BY id_theme
		ORDER BY id_theme DESC',
		array(
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		// Figure out which theme it is they are REALLY using.
		if (!empty($settings['knownThemes']) && !in_array($row['id_theme'], explode(',', $settings['knownThemes'])))
			$row['id_theme'] = $guest_theme;
		elseif (empty($settings['theme_allow']))
			$row['id_theme'] = $guest_theme;

		if (isset($context['available_themes'][$row['id_theme']]))
			$context['available_themes'][$row['id_theme']]['num_users'] += $row['the_count'];
		else
			$context['available_themes'][$guest_theme]['num_users'] += $row['the_count'];
	}
	wesql::free_result($request);

	// Save the setting first.
	$current_images_url = $theme['images_url'];

	foreach ($context['available_themes'] as $id_theme => $theme_data)
	{
		// Don't try to load the forum or board default theme's data... it doesn't have any!
		if ($id_theme == 0)
			continue;

		// The thumbnail needs the correct path.
		$theme['images_url'] =& $theme_data['images_url'];

		if (file_exists($theme_data['theme_dir'] . '/languages/Settings.' . $user_info['language'] . '.php'))
			include($theme_data['theme_dir'] . '/languages/Settings.' . $user_info['language'] . '.php');
		elseif (file_exists($theme_data['theme_dir'] . '/languages/Settings.' . $language . '.php'))
			include($theme_data['theme_dir'] . '/languages/Settings.' . $language . '.php');
		else
			$txt['theme_description'] = '';

		$context['available_themes'][$id_theme]['description'] = $txt['theme_description'];
	}

	// Then return it.
	$theme['images_url'] = $current_images_url;

	// As long as we're not doing the default theme...
	if ($u === null || $u >= 0)
	{
		if ($guest_theme != 0)
			$context['available_themes'][0] = $context['available_themes'][$guest_theme];

		$context['available_themes'][0]['id'] = 0;
		$context['available_themes'][0]['name'] = $txt['theme_forum_default'];
		$context['available_themes'][0]['selected'] = $context['current_theme'] == 0;
		$context['available_themes'][0]['description'] = $txt['theme_global_description'];
	}

	ksort($context['available_themes']);

	$context['page_title'] = $txt['change_skin'];
	wetem::load('pick');
}

function ThemeInstall()
{
	global $boarddir, $boardurl, $txt, $context, $theme, $settings;

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
				AND variable = {string:name}
			LIMIT 1',
			array(
				'current_theme' => (int) $_GET['theme_id'],
				'no_member' => 0,
				'name' => 'name',
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
	elseif (isset($_REQUEST['theme_dir']) && rtrim(realpath($_REQUEST['theme_dir']), '/\\') != realpath($boarddir . '/Themes') && file_exists($_REQUEST['theme_dir']))
		$method = 'path';
	else
		$method = 'copy';

	if (!empty($_REQUEST['copy']) && $method == 'copy')
	{
		// Hopefully the themes directory is writable, or we might have a problem.
		if (!is_writable($boarddir . '/Themes'))
			fatal_lang_error('theme_install_write_error', 'critical');

		$theme_dir = $boarddir . '/Themes/' . preg_replace('~[^A-Za-z0-9_\- ]~', '', $_REQUEST['copy']);

		umask(0);
		mkdir($theme_dir, 0777);

		@set_time_limit(600);
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		// Create subdirectories for CSS and JavaScript files.
		mkdir($theme_dir . '/skins', 0777);
		mkdir($theme_dir . '/scripts', 0777);

		// Copy over the default non-theme files.
		$to_copy = array('/index.php', '/index.template.php', '/skins/index.css', '/skins/index.rtl.css', '/scripts/theme.js');
		foreach ($to_copy as $file)
		{
			copy($theme['default_theme_dir'] . $file, $theme_dir . $file);
			@chmod($theme_dir . $file, 0777);
		}

		// And now the entire images directory!
		copytree($theme['default_theme_dir'] . '/images', $theme_dir . '/images');
		package_flush_cache();

		$theme_name = $_REQUEST['copy'];
		$images_url = $boardurl . '/Themes/' . basename($theme_dir) . '/images';
		$theme_dir = realpath($theme_dir);

		// Let's get some data for the new theme.
		$request = wesql::query('
			SELECT value
			FROM {db_prefix}themes
			WHERE variable = {string:theme_templates}
				AND id_member = {int:no_member}
				AND id_theme = {int:default_theme}',
			array(
				'no_member' => 0,
				'default_theme' => 1,
				'theme_templates' => 'theme_templates',
			)
		);
		list ($theme_templates) = wesql::fetch_row($request);
		wesql::free_result($request);

		// Let's add a theme_info.xml to this theme.
		$xml_info = '<?xml version="1.0"?' . '>
<theme-info xmlns="http://wedge.org/files/xml/theme-info.dtd" xmlns:we="http://wedge.org/">
	<!-- For the id, always use something unique - put your name, a colon, and then the package name. -->
	<id>wedge:' . westr::strtolower(str_replace(array(' '), '_', $_REQUEST['copy'])) . '</id>
	<version>' . $settings['weVersion'] . '</version>
	<!-- Theme name, used purely for aesthetics. -->
	<name>' . $_REQUEST['copy'] . '</name>
	<!-- Author: your email address or contact information. The name attribute is optional. -->
	<author name="Author">dummy@dummy.com</author>
	<!-- Website... where to get updates and more information. -->
	<website>http://wedge.org/</website>
	<!-- Templates to load on startup. Default is "index". -->
	<templates>' . (empty($theme_templates) ? 'index' : $theme_templates) . '</templates>
	<!-- Base this theme off another? Default is blank, or no. It could be "default". -->
	<based-on></based-on>
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
		if (!is_writable($boarddir . '/Themes'))
			fatal_lang_error('theme_install_write_error', 'critical');

		loadSource('Subs-Package');

		// Set the default settings...
		$theme_name = strtok(basename(isset($_FILES['theme_gz']) ? $_FILES['theme_gz']['name'] : $_REQUEST['theme_gz']), '.');
		$theme_name = preg_replace(array('/\s/', '/\.{2,}/', '/[^\w.-]/'), array('_', '.', ''), $theme_name);
		$theme_dir = $boarddir . '/Themes/' . $theme_name;

		if (isset($_FILES['theme_gz']) && is_uploaded_file($_FILES['theme_gz']['tmp_name']) && (@ini_get('open_basedir') != '' || file_exists($_FILES['theme_gz']['tmp_name'])))
			$extracted = read_tgz_file($_FILES['theme_gz']['tmp_name'], $boarddir . '/Themes/' . $theme_name, false, true);
		elseif (isset($_REQUEST['theme_gz']))
		{
			// Check that the theme is from wedge.org, for now... maybe add mirroring later.
			if (preg_match('~^http://[\w-]+\.wedge\.org/~', $_REQUEST['theme_gz']) == 0 || strpos($_REQUEST['theme_gz'], 'dlattach') !== false)
				fatal_lang_error('package_not_on_wedge');

			$extracted = read_tgz_file($_REQUEST['theme_gz'], $boarddir . '/Themes/' . $theme_name, false, true);
		}
		else
			redirectexit('action=admin;area=theme;sa=admin;' . $context['session_query']);
	}

	// Something go wrong?
	if ($theme_dir != '' && basename($theme_dir) != 'Themes')
	{
		// Defaults.
		$install_info = array(
			'theme_url' => $boardurl . '/Themes/' . basename($theme_dir),
			'images_url' => isset($images_url) ? $images_url : $boardurl . '/Themes/' . basename($theme_dir) . '/images',
			'theme_dir' => $theme_dir,
			'name' => $theme_name
		);

		if (file_exists($theme_dir . '/theme_info.xml'))
		{
			$theme_info = file_get_contents($theme_dir . '/theme_info.xml');

			$xml_elements = array(
				'name' => 'name',
				'theme_templates' => 'templates',
				'based_on' => 'based-on',
			);
			foreach ($xml_elements as $var => $name)
				if (preg_match('~<' . $name . '>(?:<!\[CDATA\[)?(.+?)(?:\]\]>)?</' . $name . '>~', $theme_info, $match) == 1)
					$install_info[$var] = $match[1];

			if (preg_match('~<images>(?:<!\[CDATA\[)?(.+?)(?:\]\]>)?</images>~', $theme_info, $match) == 1)
			{
				$install_info['images_url'] = $install_info['theme_url'] . '/' . $match[1];
				$explicit_images = true;
			}
			if (preg_match('~<extra>(?:<!\[CDATA\[)?(.+?)(?:\]\]>)?</extra>~', $theme_info, $match) == 1)
				$install_info += unserialize($match[1]);
		}

		if (isset($install_info['based_on']))
		{
			if ($install_info['based_on'] == 'default')
			{
				$install_info['theme_url'] = $theme['default_theme_url'];
				$install_info['images_url'] = $theme['default_images_url'];
			}
			elseif ($install_info['based_on'] != '')
			{
				$install_info['based_on'] = preg_replace('~[^A-Za-z0-9\-_ ]~', '', $install_info['based_on']);

				$request = wesql::query('
					SELECT th.value AS base_theme_dir, th2.value AS base_theme_url' . (!empty($explicit_images) ? '' : ', th3.value AS images_url') . '
					FROM {db_prefix}themes AS th
						INNER JOIN {db_prefix}themes AS th2 ON (th2.id_theme = th.id_theme
							AND th2.id_member = {int:no_member}
							AND th2.variable = {string:theme_url})' . (!empty($explicit_images) ? '' : '
						INNER JOIN {db_prefix}themes AS th3 ON (th3.id_theme = th.id_theme
							AND th3.id_member = {int:no_member}
							AND th3.variable = {string:images_url})') . '
					WHERE th.id_member = {int:no_member}
						AND (th.value LIKE {string:based_on} OR th.value LIKE {string:based_on_path})
						AND th.variable = {string:theme_dir}
					LIMIT 1',
					array(
						'no_member' => 0,
						'theme_url' => 'theme_url',
						'images_url' => 'images_url',
						'theme_dir' => 'theme_dir',
						'based_on' => '%/' . $install_info['based_on'],
						'based_on_path' => '%' . "\\" . $install_info['based_on'],
					)
				);
				$temp = wesql::fetch_assoc($request);
				wesql::free_result($request);

				// !!! An error otherwise?
				if (is_array($temp))
				{
					$install_info = $temp + $install_info;

					if (empty($explicit_images) && !empty($install_info['base_theme_url']))
						$install_info['theme_url'] = $install_info['base_theme_url'];
				}
			}

			unset($install_info['based_on']);
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

		// This will be theme number...
		$id_theme++;

		$inserts = array();
		foreach ($install_info as $var => $val)
			$inserts[] = array($id_theme, $var, $val);

		if (!empty($inserts))
			wesql::insert('',
				'{db_prefix}themes',
				array('id_theme' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$inserts,
				array('id_theme', 'variable')
			);

		updateSettings(array('knownThemes' => strtr($settings['knownThemes'] . ',' . $id_theme, array(',,' => ','))));
	}

	redirectexit('action=admin;area=theme;sa=install;theme_id=' . $id_theme . ';' . $context['session_query']);
}

function EditTheme()
{
	global $context, $theme, $scripturl, $boarddir;

	// !!! Should this be removed?
	if (isset($_REQUEST['preview']))
		die;

	isAllowedTo('admin_forum');
	loadTemplate('Themes');

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) @$_GET['id'];

	if (empty($_GET['th']))
	{
		$request = wesql::query('
			SELECT id_theme, variable, value
			FROM {db_prefix}themes
			WHERE variable IN ({string:name}, {string:theme_dir}, {string:theme_templates})
				AND id_member = {int:no_member}',
			array(
				'name' => 'name',
				'theme_dir' => 'theme_dir',
				'theme_templates' => 'theme_templates',
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
	$request = wesql::query('
		SELECT value, id_theme
		FROM {db_prefix}themes
		WHERE variable = {string:theme_dir}
			AND id_theme = {int:current_theme}
		LIMIT 1',
		array(
			'current_theme' => $_GET['th'],
			'theme_dir' => 'theme_dir',
		)
	);
	list ($theme_dir, $context['theme_id']) = wesql::fetch_row($request);
	wesql::free_result($request);

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
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_query'] . ';sa=edit;directory=' . $temp,
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
			if (substr($_REQUEST['filename'], -13) == '.template.php' && is_writable($theme_dir) && @ini_get('display_errors'))
			{
				$request = wesql::query('
					SELECT value
					FROM {db_prefix}themes
					WHERE variable = {string:theme_url}
						AND id_theme = {int:current_theme}
					LIMIT 1',
					array(
						'current_theme' => $_GET['th'],
						'theme_url' => 'theme_url',
					)
				);
				list ($theme_url) = wesql::fetch_row($request);
				wesql::free_result($request);

				$fp = fopen($theme_dir . '/tmp_' . session_id() . '.php', 'w');
				fwrite($fp, $_POST['entire_file']);
				fclose($fp);

				// !!! Use Class-WebGet()?
				$error = @file_get_contents($theme_url . '/tmp_' . session_id() . '.php');
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

				redirectexit('action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_query'] . ';sa=edit;directory=' . dirname($_REQUEST['filename']));
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
	$context['allow_save_filename'] = strtr($theme_dir . '/' . $_REQUEST['filename'], array($boarddir => '...'));
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
	global $scripturl, $txt, $context;

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
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_query'] . ';sa=edit;directory=' . $relative . $entry,
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
				'href' => $scripturl . '?action=admin;area=theme;th=' . $_GET['th'] . ';' . $context['session_query'] . ';sa=edit;filename=' . $relative . $entry,
				'size' => $size,
				'last_modified' => timeformat(filemtime($path . '/' . $entry)),
			);
		}
	}

	return array_merge($listing1, $listing2);
}

function CopyTemplate()
{
	global $context, $theme;

	isAllowedTo('admin_forum');
	loadTemplate('Themes');

	$context[$context['admin_menu_name']]['current_subsection'] = 'edit';

	$_GET['th'] = isset($_GET['th']) ? (int) $_GET['th'] : (int) $_GET['id'];

	$request = wesql::query('
		SELECT th1.value, th1.id_theme, th2.value
		FROM {db_prefix}themes AS th1
			LEFT JOIN {db_prefix}themes AS th2 ON (th2.variable = {string:base_theme_dir} AND th2.id_theme = {int:current_theme})
		WHERE th1.variable = {string:theme_dir}
			AND th1.id_theme = {int:current_theme}
		LIMIT 1',
		array(
			'current_theme' => $_GET['th'],
			'base_theme_dir' => 'base_theme_dir',
			'theme_dir' => 'theme_dir',
		)
	);
	list ($theme_dir, $context['theme_id'], $base_theme_dir) = wesql::fetch_row($request);
	wesql::free_result($request);

	if (isset($_REQUEST['template']) && preg_match('~[./\\\\:\0]~', $_REQUEST['template']) == 0)
	{
		if (!empty($base_theme_dir) && file_exists($base_theme_dir . '/' . $_REQUEST['template'] . '.template.php'))
			$filename = $base_theme_dir . '/' . $_REQUEST['template'] . '.template.php';
		elseif (file_exists($theme['default_theme_dir'] . '/' . $_REQUEST['template'] . '.template.php'))
			$filename = $theme['default_theme_dir'] . '/' . $_REQUEST['template'] . '.template.php';
		else
			fatal_lang_error('no_access', false);

		$fp = fopen($theme_dir . '/' . $_REQUEST['template'] . '.template.php', 'w');
		fwrite($fp, file_get_contents($filename));
		fclose($fp);

		redirectexit('action=admin;area=theme;th=' . $context['theme_id'] . ';' . $context['session_query'] . ';sa=copy');
	}
	elseif (isset($_REQUEST['lang_file']) && preg_match('~^[^./\\\\:\0]\.[^./\\\\:\0]$~', $_REQUEST['lang_file']) != 0)
	{
		if (!empty($base_theme_dir) && file_exists($base_theme_dir . '/languages/' . $_REQUEST['lang_file'] . '.php'))
			$filename = $base_theme_dir . '/languages/' . $_REQUEST['template'] . '.php';
		elseif (file_exists($theme['default_theme_dir'] . '/languages/' . $_REQUEST['template'] . '.php'))
			$filename = $theme['default_theme_dir'] . '/languages/' . $_REQUEST['template'] . '.php';
		else
			fatal_lang_error('no_access', false);

		$fp = fopen($theme_dir . '/languages/' . $_REQUEST['lang_file'] . '.php', 'w');
		fwrite($fp, file_get_contents($filename));
		fclose($fp);

		redirectexit('action=admin;area=theme;th=' . $context['theme_id'] . ';' . $context['session_query'] . ';sa=copy');
	}

	$templates = array();
	$lang_files = array();

	$dir = dir($theme['default_theme_dir']);
	while ($entry = $dir->read())
		if (substr($entry, -13) == '.template.php')
			$templates[] = substr($entry, 0, -13);
	$dir->close();

	$dir = dir($theme['default_theme_dir'] . '/languages');
	while ($entry = $dir->read())
		if (preg_match('~^([^.]+\.[^.]+)\.php$~', $entry, $matches))
			$lang_files[] = $matches[1];
	$dir->close();

	if (!empty($base_theme_dir))
	{
		$dir = dir($base_theme_dir);
		while ($entry = $dir->read())
			if (substr($entry, -13) == '.template.php' && !in_array(substr($entry, 0, -13), $templates))
				$templates[] = substr($entry, 0, -13);
		$dir->close();

		if (file_exists($base_theme_dir . '/languages'))
		{
			$dir = dir($base_theme_dir . '/languages');
			while ($entry = $dir->read())
				if (preg_match('~^([^.]+\.[^.]+)\.php$~', $entry, $matches) && !in_array($matches[1], $lang_files))
					$lang_files[] = $matches[1];
			$dir->close();
		}
	}

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
function wedge_get_skin_list($dir, $files = array(), &$root = array())
{
	global $theme;

	$skins = array();
	$is_root = empty($root);
	if ($is_root)
		$root =& $skins;
	if (empty($files))
		$files = scandir($dir);

	foreach ($files as $file)
	{
		$this_dir = $dir . '/' . $file;
		// If we're in the root, skip anything but the '.' folder. Otherwise, skip all '.' folders. It makes sense, really.
		if (($is_root && $file !== '.') || (!$is_root && $file === '.') || $file === '..' || !is_dir($this_dir))
			continue;
		if ($is_root)
			$this_dir = $dir;
		$these_files = scandir($this_dir);
		$is_valid = false;
		// We need to have at least one .css file *or* skin.xml for a skin to be valid.
		foreach ($these_files as $test)
		{
			if (substr($test, -4) === '.css')
			{
				$is_valid = true;
				break;
			}
		}
		if (!$is_valid && !in_array('skin.xml', $these_files))
			continue;
		if (in_array('skin.xml', $these_files))
		{
			// I'm not actually parsing it XML-style... Mwahaha! I'm evil.
			$setxml = file_get_contents($this_dir . '/skin.xml');
			$skin = array(
				'name' => preg_match('~<name>(?:<!\[CDATA\[)?(.*?)(?:]]>)?</name>~sui', $setxml, $match) ? trim($match[1]) : $file,
				'type' => $is_root ? 'replace' : (preg_match('~<type>(.*?)</type>~sui', $setxml, $match) ? trim($match[1]) : 'add'),
				'comment' => preg_match('~<comment>(?:<!\[CDATA\[)?(.*?)(?:]]>)?</comment>~sui', $setxml, $match) ? trim($match[1]) : '',
			);
		}
		else
			$skin = array(
				'name' => $file,
				'type' => 'add',
				'comment' => '',
			);
		$skin['dir'] = substr($this_dir, strpos($this_dir, $is_root ? '/skins' : '/skins/') + 1);
		if ($skin['type'] == 'add')
			$skins[$this_dir] = $skin;
		else
			$root[$this_dir] = $skin;

		if ($is_root || $file !== '.')
			$sub_skins = wedge_get_skin_list($this_dir, $these_files, $root);
		if (!empty($sub_skins))
		{
			if ($skin['type'] == 'add')
				$skins[$this_dir]['skins'] = $sub_skins;
			else
				$root[$this_dir]['skins'] = $sub_skins;
		}
	}
	return $skins;
}

/**
 * Return a list of <option> variables for use in Themes and ManageBoard templates.
 */
function wedge_show_skins(&$th, &$style, $level, $current_theme_id, $current_skin)
{
	global $context;

	$last = count($style);
	$current = 1;
	foreach ($style as $sty)
	{
		$intro = str_repeat('&#9130;&nbsp;&nbsp;', $level - 1) . ($current == $last ? '&#9492;' : '&#9500;') . '&mdash; ';
		echo '<option value="', $th['id'], '_', base64_encode($sty['dir']), '"', $current_theme_id == $th['id'] && $current_skin == $sty['dir'] ? ' selected' : '', '>', $intro, $sty['name'], '</option>';
		if (!empty($sty['skins']))
			wedge_show_skins($th, $sty['skins'], $level + 1, $current_theme_id, $current_skin);
		$current++;
	}
}

function wedge_update_skin($mem, $id_theme, $skin)
{
	global $user_info;

	if (!empty($user_info['is_mobile']))
		updateMemberData($mem, array(
			'id_theme_mobile' => $id_theme,
			'skin_mobile' => $skin
		));
	else
		updateMemberData($mem, array(
			'id_theme' => $id_theme,
			'skin' => $skin
		));
}

?>