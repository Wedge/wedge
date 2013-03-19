<?php
/**
 * Wedge
 *
 * Handles the settings for the core forum configuration (paths, database, cookies), plus language configuration.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file contains all the functionality required to be able to edit the
	core server settings. This includes anything from which an error may result
	in the forum destroying itself in a firey fury.

	void ModifySettings()
		- Sets up all the available sub-actions.
		- Requires the admin_forum permission.
		- Uses the edit_settings adminIndex.
		- Sets up all the tabs and selects the appropriate one based on the sub-action.
		- Redirects to the appropriate function based on the sub-action.

	void ModifyGeneralSettings()
		- shows an interface for the settings in Settings.php to be changed.
		- requires the admin_forum permission.
		- uses the edit_settings administration area.
		- contains the actual array of settings to show from Settings.php.
		- accessed from ?action=admin;area=serversettings;sa=general.

	void ModifyDatabaseSettings()
		- shows an interface for the settings in Settings.php to be changed.
		- requires the admin_forum permission.
		- uses the edit_settings administration area.
		- contains the actual array of settings to show from Settings.php.
		- accessed from ?action=admin;area=serversettings;sa=database.

	void ModifyCookieSettings()
		// !!!

	void ModifyCacheSettings()
		// !!!

	void ModifyLoadBalancingSettings()
		// !!!

	void prepareServerSettingsContext(array config_vars)
		// !!!

	void prepareDBSettingContext(array config_vars)
		// !!!

	void saveSettings(array config_vars)
		- saves those settings set from ?action=admin;area=serversettings to the
		  Settings.php file and the database.
		- requires the admin_forum permission.
		- contains arrays of the types of data to save into Settings.php.

	void saveDBSettings(array config_vars)
		// !!!

*/

/*	Most of the admin panel pages use this standardized setup.
	If you're building a plugin, very often you just need to use <settings-page> in your plugin-info.xml
	file. You only need to worry about this if you have a reason to do any of it manually, and if you
	do, this is how it works.

	Setting up options for one of the setting screens isn't hard. Call prepareDBSettingsContext;
	The basic format for a checkbox is:
		array('check', 'nameInSettingsAndSQL'),

	And for a text box:
		array('text', 'nameInSettingsAndSQL')

	In these cases, it will look for $txt['nameInSettingsAndSQL'] as the description,
	and $helptxt['nameInSettingsAndSQL'] as the help popup description.

	Here's a quick explanation of how to add a new item:

	* A text input box. For textual values.
	ie.	array('text', 'nameInSettingsAndSQL', 'OptionalInputBoxWidth'),

	* A text input box. For numerical values.
	ie.	array('int', 'nameInSettingsAndSQL', 'OptionalInputBoxWidth', 'min' => optional min, 'max' => optional max, 'step' => optional stepping value),
	The stepping value is if you want a value to be stepped in increments that aren't 1.
	!!! Stepping is only in supported browsers, not actually enforced in code at this time.

	* A text input box. For floating point values.
	ie.	array('float', 'nameInSettingsAndSQL', 'OptionalInputBoxWidth'),

	* A large text input box. Used for textual values spanning multiple lines.
	ie.	array('large_text', 'nameInSettingsAndSQL', 'OptionalNumberOfRows'),

	* A check box. Either one or zero (boolean.)
	ie.	array('check', 'nameInSettingsAndSQL'),

	* A selection box. Used for the selection of something from a list.
	ie.	array('select', 'nameInSettingsAndSQL', array('valueForSQL' => $txt['displayedValue'])),
	Note that just saying array('first', 'second') will put 0 in the SQL for 'first'.

	* A password input box. Used for passwords, no less!
	ie.	array('password', 'nameInSettingsAndSQL', 'OptionalInputBoxWidth'),

	* A permission - for picking groups who have a permission.
	ie.	array('permissions', 'manage_groups'),

	* A BBC selection box.
	ie.	array('bbc', 'sig_bbc'),

	For each option:
		type (see above), variable name, size/possible values.
	OR	make type '' for an empty string for a horizontal rule.
	SET	preinput - to put some HTML prior to the input box.
	SET	postinput - to put some HTML following the input box.
	SET	invalid - to mark the data as invalid.
	PLUS	You can override label and help parameters by forcing their keys in the array, for example:
		array('text', 'invalidlabel', 3, 'label' => 'Actual Label') */

// This is the main pass through function, it creates tabs and the like.
function ModifySettings()
{
	global $context, $txt, $boarddir;

	// This is just to keep the database password more secure.
	isAllowedTo('admin_forum');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['admin_server_settings'],
		'help' => 'serversettings',
		'description' => $txt['admin_basic_settings'],
	);

	checkSession('request');

	// The settings are in here, I swear!
	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['admin_server_settings'];
	wetem::load('show_settings');

	$subActions = array(
		'general' => 'ModifyGeneralSettings',
		'database' => 'ModifyDatabaseSettings',
		'cookie' => 'ModifyCookieSettings',
		'cache' => 'ModifyCacheSettings',
		'loads' => 'ModifyLoadBalancingSettings',
		'proxy' => 'ModifyProxySettings',
		'debug' => 'ModifyDebugSettings',
	);

	if (strpos(strtolower(PHP_OS), 'win') === 0)
		unset($subActions['loads']);

	// By default we're editing the core settings
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'general';
	$context['sub_action'] = $_REQUEST['sa'];

	// Warn the user if there's any relevant information regarding Settings.php.
	if ($_REQUEST['sa'] != 'cache')
	{
		// Warn the user if the backup of Settings.php failed.
		$settings_not_writable = !is_writable($boarddir . '/Settings.php');
		$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');

		if ($settings_not_writable)
			$context['settings_message'] = '<p class="center"><strong>' . $txt['settings_not_writable'] . '</strong></p><br>';
		elseif ($settings_backup_fail)
			$context['settings_message'] = '<p class="center"><strong>' . $txt['admin_backup_fail'] . '</strong></p><br>';

		$context['settings_not_writable'] = $settings_not_writable;
	}

	// Call the right function for this sub-action.
	$subActions[$_REQUEST['sa']]();
}

// General forum settings - forum name, maintenance mode, etc.
function ModifyGeneralSettings($return_config = false)
{
	global $context, $txt, $settings;

	loadLanguage('ManageSettings');

	/* If you're writing a mod, it's a BAD idea to add anything here....
	For each option:
		variable name, description, type (constant), size/possible values, helptext.
	OR	an empty string for a horizontal rule.
	OR	a string for a titled section. */
	$config_vars = array(
		array('maintenance', $txt['maintenance'], 'file', 'check'),
		array('mtitle', $txt['setting_mtitle'], 'file', 'text', 36),
		array('mmessage', $txt['setting_mmessage'], 'file', 'text', 36),
		'',
		array('time_offset', $txt['setting_time_offset'], 'db', 'float', null, 'time_offset', 'subtext' => $txt['setting_time_offset_subtext']),
		'default_timezone' => array('default_timezone', $txt['setting_default_timezone'], 'db', 'select', array()),
		'',
		array('enableCompressedOutput', $txt['enableCompressedOutput'], 'db', 'check', null, 'enableCompressedOutput'),
		array('enableCompressedData', $txt['enableCompressedData'], 'db', 'check', null, 'enableCompressedData'),
		array('obfuscate_filenames', $txt['obfuscate_filenames'], 'db', 'check', null, 'obfuscate_filenames'),
		array('minify', $txt['minify'], 'db', 'select', array(
			'none' => array('none', $txt['minify_none']),
			'jsmin' => array('jsmin', $txt['minify_jsmin']),
			'closure' => array('closure', $txt['minify_closure']),
			'packer' => array('packer', $txt['minify_packer']),
		), 'minify'),
		array('jquery_origin', $txt['jquery_origin'], 'db', 'select', array(
			'local' => array('local', $txt['jquery_local']),
			'jquery' => array('jquery', $txt['jquery_jquery']),
			'google' => array('google', $txt['jquery_google']),
			'microsoft' => array('microsoft', $txt['jquery_microsoft']),
		), 'jquery_origin'),
		'',
		array('disableHostnameLookup', $txt['disableHostnameLookup'], 'db', 'check', null, 'disableHostnameLookup'),
	);

	// PHP can give us a list of all the time zones. Yay.
	$all_zones = timezone_identifiers_list();

	// Make sure we set the value to the same as the printed value. But this is sadly messy.
	$useful_regions = array_flip(array('Africa', 'America', 'Antartica', 'Arctic', 'Asia', 'Atlantic', 'Europe', 'Indian', 'Pacific'));
	$last_region = '';

	foreach ($all_zones as $zone)
	{
		if (strpos($zone, '/') === false)
			continue;
		list ($region, $place) = explode('/', $zone, 2);
		if (!isset($useful_regions[$region]))
			continue;
		if ($region !== $last_region)
			$config_vars['default_timezone'][4][$zone] = array('', '', $region);
		else
			$config_vars['default_timezone'][4][$zone] = array($zone, strtr($place, '_', ' '));
		$last_region = $region;
	}
	// Don't forget UTC!
	$config_vars['default_timezone'][4]['UTC_group'] = array('', '', 'UTC');
	$config_vars['default_timezone'][4]['UTC'] = array('UTC', 'UTC');

	if ($return_config)
		return $config_vars;

	// Setup the template stuff.
	$context['post_url'] = '<URL>?action=admin;area=serversettings;sa=general;save';
	$context['settings_title'] = $txt['general_settings'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		// Delete the JS cache in case we're changing one of these settings. Only does the current theme.
		// Cached JS files are also cleaned up on the fly so this is just a small time saver.
		foreach (array('enableCompressedData', 'obfuscate_filenames', 'minify') as $cache)
		{
			if (isset($_REQUEST[$cache]) && (!isset($settings[$cache]) || $_REQUEST[$cache] != $settings[$cache]))
			{
				loadSource('Subs-Cache');
				clean_cache('js');
				// Note: enableCompressedData should always be tested first in the array,
				// so we can safely remove CSS files too.
				if ($cache == 'enableCompressedData')
					clean_cache('css');
				break;
			}
		}

		saveSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=general;' . $context['session_query']);
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

// Basic database and paths settings - database name, host, etc.
function ModifyDatabaseSettings($return_config = false)
{
	global $context, $theme, $txt, $boarddir;

	/* If you're writing a mod, it's a bad idea to add things here....
	For each option:
		variable name, description, type (constant), size/possible values, helptext.
	OR	an empty string for a horizontal rule.
	OR	a string for a titled section. */
	$config_vars = array(
		array('db_server', $txt['database_server'], 'file', 'text'),
		array('db_user', $txt['database_user'], 'file', 'text'),
		array('db_passwd', $txt['database_password'], 'file', 'password'),
		array('db_name', $txt['database_name'], 'file', 'text'),
		array('db_prefix', $txt['database_prefix'], 'file', 'text'),
		array('db_persist', $txt['db_persist'], 'file', 'check', null, 'db_persist'),
		array('db_error_send', $txt['db_error_send'], 'file', 'check'),
		array('ssi_db_user', $txt['ssi_db_user'], 'file', 'text', null, 'ssi_db_user'),
		array('ssi_db_passwd', $txt['ssi_db_passwd'], 'file', 'password'),
		'',
		array('autoFixDatabase', $txt['autoFixDatabase'], 'db', 'check', false, 'autoFixDatabase'),
		array('autoOptMaxOnline', $txt['autoOptMaxOnline'], 'db', 'int', 'subtext' => $txt['autoOptMaxOnline_subtext']),
		'',
		array('boardurl', $txt['admin_url'], 'file', 'text', 36),
		array('boarddir', $txt['boarddir'], 'file', 'text', 36),
		array('sourcedir', $txt['sourcesdir'], 'file', 'text', 36),
		array('cachedir', $txt['cachedir'], 'file', 'text', 36),
		array('pluginsdir', $txt['pluginsdir'], 'file', 'text', 36),
		array('pluginsurl', $txt['pluginsurl'], 'file', 'text', 36),
	);

	if ($return_config)
		return $config_vars;

	// Setup the template stuff.
	$context['post_url'] = '<URL>?action=admin;area=serversettings;sa=database;save';
	$context['settings_title'] = $txt['database_paths_settings'];
	$context['save_disabled'] = $context['settings_not_writable'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		saveSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=database;' . $context['session_query']);
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

// This function basically edits anything which is configuration and stored in the database, except for caching.
function ModifyCookieSettings($return_config = false)
{
	global $context, $txt, $settings, $cookiename, $user_settings;

	// Define the variables we want to edit.
	$config_vars = array(
		// Cookies...
		array('cookiename', $txt['cookie_name'], 'file', 'text', 20),
		array('cookieTime', $txt['cookieTime'], 'db', 'int'),
		array('localCookies', $txt['localCookies'], 'db', 'check', false, 'localCookies', 'subtext' => $txt['localCookies_subtext']),
		array('globalCookies', $txt['globalCookies'], 'db', 'check', false, 'globalCookies', 'subtext' => $txt['globalCookies_subtext']),
		array('secureCookies', $txt['secureCookies'], 'db', 'check', false, 'secureCookies', 'disabled' => !isset($_SERVER['HTTPS']) || !($_SERVER['HTTPS'] == '1' || strtolower($_SERVER['HTTPS']) == 'on'), 'subtext' => $txt['secureCookies_subtext']),
		'',
		// Sessions
		array('databaseSession_enable', $txt['databaseSession_enable'], 'db', 'check', false, 'databaseSession_enable'),
		array('databaseSession_loose', $txt['databaseSession_loose'], 'db', 'check', false, 'databaseSession_loose'),
		array('databaseSession_lifetime', $txt['databaseSession_lifetime'], 'db', 'int', false, 'databaseSession_lifetime'),
	);

	if ($return_config)
		return $config_vars;

	$context['post_url'] = '<URL>?action=admin;area=serversettings;sa=cookie;save';
	$context['settings_title'] = $txt['cookies_sessions_settings'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		saveSettings($config_vars);

		// If the cookie name was changed, reset the cookie.
		if ($cookiename != $_POST['cookiename'])
		{
			$original_session_id = $context['session_id'];
			loadSource('Subs-Auth');

			// Remove the old cookie.
			setLoginCookie(-3600, 0);

			// Set the new one.
			$cookiename = $_POST['cookiename'];
			setLoginCookie(60 * $settings['cookieTime'], $user_settings['id_member'], sha1($user_settings['passwd'] . $user_settings['password_salt']));

			redirectexit('action=admin;area=serversettings;sa=cookie;' . $context['session_var'] . '=' . $original_session_id, $context['server']['needs_login_fix']);
		}

		redirectexit('action=admin;area=serversettings;sa=cookie;' . $context['session_query']);
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

// Simply modifying cache functions
function ModifyCacheSettings($return_config = false)
{
	global $context, $txt, $helptxt, $settings;

	// Define the variables we want to edit.
	$config_vars = array(
		// Only a couple of settings, but they are important
		array('select', 'cache_enable', array($txt['cache_off'], $txt['cache_level1'], $txt['cache_level2'], $txt['cache_level3'])),
		array('text', 'cache_memcached'),
	);

	if ($return_config)
		return $config_vars;

	// Saving again?
	if (isset($_GET['save']))
	{
		saveDBSettings($config_vars);

		// We have to manually force the clearing of the cache otherwise the changed settings might not get noticed.
		$settings['cache_enable'] = 1;
		cache_put_data('settings', null, 90);

		redirectexit('action=admin;area=serversettings;sa=cache;' . $context['session_query']);
	}

	$context['post_url'] = '<URL>?action=admin;area=serversettings;sa=cache;save';
	$context['settings_title'] = $txt['caching_settings'];
	$context['settings_message'] = $txt['caching_information'];

	// Detect an optimizer?
	if (is_callable('apc_store'))
		$detected = 'APC';
	elseif (is_callable('output_cache_put'))
		$detected = 'Zend';
	elseif (is_callable('memcache_set'))
		$detected = 'Memcached';
	elseif (is_callable('xcache_set'))
		$detected = 'XCache';
	else
		$detected = 'no_caching';

	$context['settings_message'] = sprintf($context['settings_message'], $txt['detected_' . $detected]);

	// Prepare the template.
	prepareDBSettingContext($config_vars);
}

function ModifyLoadBalancingSettings($return_config = false)
{
	global $txt, $context, $theme, $settings;

	// Setup a warning message, but disabled by default.
	$disabled = true;
	$context['settings_message'] = $txt['loadavg_disabled_conf'];

	$settings['load_average'] = @file_get_contents('/proc/loadavg');
	if (!empty($settings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $settings['load_average'], $matches) !== 0)
		$settings['load_average'] = (float) $matches[1];
	elseif (can_shell_exec() && ($settings['load_average'] = `uptime`) !== null && preg_match('~load averages?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $settings['load_average'], $matches) !== 0)
		$settings['load_average'] = (float) $matches[1];
	else
		unset($settings['load_average']);

	if (!empty($settings['load_average']))
	{
		$context['settings_message'] = sprintf($txt['loadavg_warning'], $settings['load_average']);
		$disabled = false;
	}

	// Start with a simple checkbox.
	$config_vars = array(
		array('check', 'loadavg_enable'),
	);

	// Set the default values for each option.
	$default_values = array(
		'loadavg_auto_opt' => '1.0',
		'loadavg_search' => '2.5',
		'loadavg_allunread' => '2.0',
		'loadavg_unreadreplies' => '3.5',
		'loadavg_show_posts' => '2.0',
		'loadavg_forum' => '40.0',
	);

	// Loop through the settings.
	foreach ($default_values as $name => $value)
	{
		// Use the default value if the setting isn't set yet.
		$value = !isset($settings[$name]) ? $value : $settings[$name];
		$config_vars[] = array('text', $name, 'value' => $value, 'disabled' => $disabled);
	}

	if ($return_config)
		return $config_vars;

	$context['post_url'] = '<URL>?action=admin;area=serversettings;sa=loads;save';
	$context['settings_title'] = $txt['load_balancing_settings'];

	// Saving?
	if (isset($_GET['save']))
	{
		// Stupidity is not allowed.
		foreach ($_POST as $key => $value)
		{
			if (strpos($key, 'loadavg') === 0 || $key === 'loadavg_enable')
				continue;
			elseif ($key == 'loadavg_auto_opt' && $value <= 1)
				$_POST['loadavg_auto_opt'] = '1.0';
			elseif ($key == 'loadavg_forum' && $value < 10)
				$_POST['loadavg_forum'] = '10.0';
			elseif ($value < 2)
				$_POST[$key] = '2.0';
		}

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=loads;' . $context['session_query']);
	}

	prepareDBSettingContext($config_vars);
}

function ModifyProxySettings($return_config = false)
{
	global $context, $txt, $helptxt, $settings;

	// Define the variables we want to edit.
	$config_vars = array(
		// Only a couple of settings, but they are important
		array('check', 'reverse_proxy'),
		array('text', 'reverse_proxy_header'),
		array('large_text', 'reverse_proxy_ips', 'subtext' => $txt['reverse_proxy_one_per_line']),
	);

	if ($return_config)
		return $config_vars;

	// Saving again?
	if (isset($_GET['save']))
	{
		$_POST['reverse_proxy_ips'] = !empty($_POST['reverse_proxy_ips']) ? array_map('trim', explode("\n", $_POST['reverse_proxy_ips'])) : '';
		foreach ($_POST['reverse_proxy_ips'] as $k => $v)
			if (empty($v))
				unset($_POST['reverse_proxy_ips'][$k]);
		$_POST['reverse_proxy_ips'] = implode(',', $_POST['reverse_proxy_ips']);

		saveDBSettings($config_vars);

		// We have to manually force the clearing of the cache otherwise the changed settings might not get noticed.
		$settings['cache_enable'] = 1;
		cache_put_data('settings', null, 90);

		redirectexit('action=admin;area=serversettings;sa=proxy;' . $context['session_query']);
	}

	$context['post_url'] = '<URL>?action=admin;area=serversettings;sa=proxy;save';
	$context['settings_title'] = $txt['proxy_settings'];

	// Prepare the template.
	prepareDBSettingContext($config_vars);
}

function ModifyDebugSettings($return_config = false)
{
	global $context, $txt, $settings, $cachedir;

	$config_vars = array(
		array('disableTemplateEval', $txt['disableTemplateEval'], 'db', 'check', null, 'disableTemplateEval'),
		array('timeLoadPageEnable', $txt['timeLoadPageEnable'], 'db', 'check', null, 'timeLoadPageEnable'),
		'',
		array('db_show_debug', $txt['db_show_debug'], 'file', 'check', null, 'db_show_debug'),
		array('db_show_debug_who', $txt['db_show_debug_who'], 'db', 'select', array(
			// !!! Hideous long format to cope with the lack of preparsing done by preparseServerSettingsContext and template expectations
			'admin' => array('admin', $txt['db_show_debug_admin']),
			'mod' => array('mod', $txt['db_show_debug_admin_mod']),
			'regular' => array('regular', $txt['db_show_debug_regular']),
			'any' => array('any', $txt['db_show_debug_any']),
		), 'db_show_debug_who'),
		array('db_show_debug_who_log', $txt['db_show_debug_who_log'], 'db', 'select', array(
			'admin' => array('admin', $txt['db_show_debug_admin']),
			'mod' => array('mod', $txt['db_show_debug_admin_mod']),
			'regular' => array('regular', $txt['db_show_debug_regular']),
			'any' => array('any', $txt['db_show_debug_any']),
		), 'db_show_debug_who_log'),
	);

	if ($return_config)
		return $config_vars;

	// Setup the template stuff.
	$context['post_url'] = '<URL>?action=admin;area=serversettings;sa=debug;save';
	$context['settings_title'] = $txt['general_settings'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		saveSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=debug;' . $context['session_query']);
	}

	// Fill the config array.
	prepareServerSettingsContext($config_vars);
}

// Helper function, it sets up the context for the manage server settings.
function prepareServerSettingsContext(&$config_vars)
{
	global $context, $settings;

	$context['config_vars'] = array();
	foreach ($config_vars as $identifier => $config_var)
	{
		if (!is_array($config_var) || !isset($config_var[1]))
			$context['config_vars'][] = $config_var;
		else
		{
			$varname = $config_var[0];
			global $$varname;

			$item = array(
				'label' => $config_var[1],
				'help' => isset($config_var[5]) ? $config_var[5] : '',
				'type' => $config_var[3],
				'size' => empty($config_var[4]) ? 0 : $config_var[4],
				'data' => isset($config_var[4]) && is_array($config_var[4]) ? $config_var[4] : array(),
				'name' => $config_var[0],
				'value' => $config_var[2] == 'file' ? htmlspecialchars($$varname) : (isset($settings[$config_var[0]]) ? htmlspecialchars($settings[$config_var[0]]) : (in_array($config_var[3], array('int', 'float')) ? 0 : '')),
				'disabled' => !empty($context['settings_not_writable']) || !empty($config_var['disabled']),
				'invalid' => false,
				'javascript' => '',
				'preinput' => '',
				'postinput' => '',
				'subtext' => !empty($config_var['subtext']) ? $config_var['subtext'] : '',
			);

			// If it's an int, there may be extra stuff.
			if ($config_var[3] == 'int')
			{
				if (isset($config_var['min']))
					$item['min'] = $config_var['min'];
				if (isset($config_var['max']))
					$item['max'] = $config_var['max'];
				$item['step'] = isset($config_var['step']) ? $config_var['step'] : 1;
			}

			$context['config_vars'][] = $item;
		}
	}

	$context['was_saved'] = !empty($_SESSION['settings_saved']);
	if (empty($context['was_saved_this_page']))
		unset($_SESSION['settings_saved']);
}

// Helper function, it sets up the context for database settings.
function prepareDBSettingContext(&$config_vars)
{
	global $txt, $helptxt, $context, $settings;

	loadLanguage('Help');

	$context['config_vars'] = array();
	$inlinePermissions = array();
	$bbcChoice = array();
	$boardChoice = array();
	foreach ($config_vars as $config_var)
	{
		// HR?
		if (!is_array($config_var))
			$context['config_vars'][] = $config_var;
		else
		{
			// If it has no name it doesn't have any purpose!
			if (empty($config_var[1]))
				continue;

			if ($config_var[0] == 'boards')
				$boardChoice[] = $config_var[1];

			// Special case for inline permissions
			if ($config_var[0] == 'permissions' && allowedTo('manage_permissions'))
				$inlinePermissions[$config_var[1]] = isset($config_var['exclude']) ? $config_var['exclude'] : array();
			elseif ($config_var[0] == 'permissions')
				continue;

			// Are we showing the BBC selection box?
			if ($config_var[0] == 'bbc')
				$bbcChoice[] = $config_var[1];

			$context['config_vars'][$config_var[1]] = array(
				'label' => isset($config_var['text_label']) ? $config_var['text_label'] : (isset($txt[$config_var[1]]) ? $txt[$config_var[1]] : (isset($config_var[3]) && !is_array($config_var[3]) ? $config_var[3] : '')),
				'help' => isset($helptxt[$config_var[1]]) ? $config_var[1] : '',
				'type' => $config_var[0],
				'size' => !empty($config_var[2]) && !is_array($config_var[2]) ? $config_var[2] : (in_array($config_var[0], array('int', 'float')) ? 6 : 0),
				'data' => array(),
				'name' => $config_var[1],
				'value' => !isset($config_var['value']) ? (isset($settings[$config_var[1]]) ? ($config_var[0] == 'select' || $config_var[0] == 'multi_select' || $config_var[0] == 'boards' ? $settings[$config_var[1]] : htmlspecialchars($settings[$config_var[1]])) : (in_array($config_var[0], array('int', 'float', 'percent')) ? 0 : '')) : $config_var['value'],
				'disabled' => false,
				'invalid' => !empty($config_var['invalid']),
				'javascript' => '',
				'var_message' => !empty($config_var['message']) && isset($txt[$config_var['message']]) ? $txt[$config_var['message']] : '',
				'preinput' => isset($config_var['preinput']) ? $config_var['preinput'] : '',
				'postinput' => isset($config_var['postinput']) ? $config_var['postinput'] : '',
			);

			// If it's an int, there may be extra stuff.
			if ($config_var[0] == 'int')
			{
				if (isset($config_var['min']))
					$context['config_vars'][$config_var[1]]['min'] = $config_var['min'];
				if (isset($config_var['max']))
					$context['config_vars'][$config_var[1]]['max'] = $config_var['max'];
				$context['config_vars'][$config_var[1]]['step'] = isset($config_var['step']) ? $config_var['step'] : 1;
			}

			// We need to do a little pre-emptive clean-up for boards.
			if ($config_var[0] == 'boards')
				$context['config_vars'][$config_var[1]]['value'] = !empty($context['config_vars'][$config_var[1]]['value']) ? unserialize($context['config_vars'][$config_var[1]]['value']) : array();

			// If this is a select box handle any data.
			if (!empty($config_var[2]) && is_array($config_var[2]))
			{
				// If we allow multiple selections, we need to adjust a few things.
				if ($config_var[0] == 'multi_select')
					$context['config_vars'][$config_var[1]]['value'] = !empty($context['config_vars'][$config_var[1]]['value']) ? unserialize($context['config_vars'][$config_var[1]]['value']) : array();

				// If it's associative
				if (isset($config_var[2][0]) && is_array($config_var[2][0]))
					$context['config_vars'][$config_var[1]]['data'] = $config_var[2];
				else
				{
					foreach ($config_var[2] as $key => $item)
						$context['config_vars'][$config_var[1]]['data'][] = array($key, $item);
				}
			}

			// Finally allow overrides - and some final cleanups.
			foreach ($config_var as $k => $v)
			{
				if (!is_numeric($k))
				{
					if (substr($k, 0, 2) == 'on')
						$context['config_vars'][$config_var[1]]['javascript'] .= ' ' . $k . '="' . $v . '"';
					else
						$context['config_vars'][$config_var[1]][$k] = $v;
				}

				// See if there are any other labels that might fit?
				if (isset($txt['setting_' . $config_var[1]]))
					$context['config_vars'][$config_var[1]]['label'] = $txt['setting_' . $config_var[1]];
				elseif (isset($txt['groups_' . $config_var[1]]))
					$context['config_vars'][$config_var[1]]['label'] = $txt['groups_' . $config_var[1]];
			}
		}
	}

	// If we have inline permissions we need to prep them.
	if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
	{
		loadSource('ManagePermissions');
		init_inline_permissions($inlinePermissions);
	}

	// If we have any board selections, we need to prep them as well
	if (!empty($boardChoice))
		get_inline_board_list();

	// What about any BBC selection boxes?
	if (!empty($bbcChoice))
	{
		// What are the options, eh?
		$temp = parse_bbc(false);
		$bbcTags = array();
		foreach ($temp as $tag)
			$bbcTags[] = $tag['tag'];

		$bbcTags = array_unique($bbcTags);
		$totalTags = count($bbcTags);

		// The number of columns we want to show the BBC tags in.
		$numColumns = isset($context['num_bbc_columns']) ? $context['num_bbc_columns'] : 3;

		// Start working out the context stuff.
		$context['bbc_columns'] = array();
		$tagsPerColumn = ceil($totalTags / $numColumns);

		$col = 0; $i = 0;
		foreach ($bbcTags as $tag)
		{
			if ($i % $tagsPerColumn == 0 && $i != 0)
				$col++;

			$context['bbc_columns'][$col][] = array(
				'tag' => $tag,
				// !!! 'tag_' . ?
				'show_help' => isset($helptxt[$tag]),
			);

			$i++;
		}

		// Now put whatever BBC options we may have into context too!
		$context['bbc_sections'] = array();
		foreach ($bbcChoice as $bbc)
		{
			$context['bbc_sections'][$bbc] = array(
				'title' => isset($txt['bbc_title_' . $bbc]) ? $txt['bbc_title_' . $bbc] : $txt['bbcTagsToUse_select'],
				'disabled' => empty($settings['bbc_disabled_' . $bbc]) ? array() : $settings['bbc_disabled_' . $bbc],
				'all_selected' => empty($settings['bbc_disabled_' . $bbc]),
			);
		}
	}

	$context['was_saved'] = !empty($_SESSION['settings_saved']);
	if (empty($context['was_saved_this_page']))
		unset($_SESSION['settings_saved']);
}

// Helper function. Saves settings by putting them in Settings.php or saving them in the settings table.
function saveSettings(&$config_vars)
{
	global $boarddir, $cookiename, $settings, $context, $cachedir;

	// Fix the darn stupid cookiename! (more may not be allowed, but these for sure!)
	if (isset($_POST['cookiename']))
		$_POST['cookiename'] = preg_replace('~[,;\s.$]+~u', '', $_POST['cookiename']);

	// Fix the forum's URL if necessary.
	if (isset($_POST['boardurl']))
	{
		if (substr($_POST['boardurl'], -10) == '/index.php')
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -10);
		elseif (substr($_POST['boardurl'], -1) == '/')
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -1);
		if (substr($_POST['boardurl'], 0, 7) != 'http://' && substr($_POST['boardurl'], 0, 7) != 'file://' && substr($_POST['boardurl'], 0, 8) != 'https://')
			$_POST['boardurl'] = 'http://' . $_POST['boardurl'];
	}

	// Any passwords?
	$config_passwords = array(
		'db_passwd',
		'ssi_db_passwd',
	);

	// All the strings to write.
	$config_strs = array(
		'mtitle', 'mmessage',
		'language', 'mbname', 'boardurl',
		'cookiename',
		'webmaster_email',
		'db_name', 'db_user', 'db_server', 'db_prefix', 'ssi_db_user',
		'boarddir', 'sourcedir', 'cachedir', 'pluginsdir',
	);
	// All the numeric variables.
	$config_ints = array(
	);
	// All the checkboxes.
	$config_bools = array(
		'db_persist', 'db_error_send',
		'maintenance',
	);
	// Values that explicitly require bool true/false
	$config_truebools = array(
		'db_show_debug',
	);

	// Now sort everything into a big array, and figure out arrays and etc.
	$new_settings = array();
	foreach ($config_passwords as $config_var)
		if (isset($_POST[$config_var][1]) && $_POST[$config_var][0] == $_POST[$config_var][1])
			$new_settings[$config_var] = '\'' . addcslashes($_POST[$config_var][0], '\'\\') . '\'';

	foreach ($config_strs as $config_var)
		if (isset($_POST[$config_var]))
			$new_settings[$config_var] = '\'' . addcslashes($_POST[$config_var], '\'\\') . '\'';

	foreach ($config_ints as $config_var)
		if (isset($_POST[$config_var]))
			$new_settings[$config_var] = (int) $_POST[$config_var];

	foreach ($config_bools as $key)
		$new_settings[$key] = !empty($_POST[$key]) ? '1' : '0';

	foreach ($config_truebools as $key)
		$new_settings[$key] = !empty($_POST[$key]) ? 'true' : 'false';

	// Save the relevant settings in the Settings.php file.
	loadSource('Subs-Admin');
	updateSettingsFile($new_settings);

	// Now loopt through the remaining (database-based) settings.
	$new_settings = array();
	foreach ($config_vars as $config_var)
	{
		// We just saved the file-based settings, so skip their definitions.
		if (!is_array($config_var) || $config_var[2] == 'file')
			continue;

		// Rewrite the definition a bit.
		if ($config_var[3] == 'int')
		{
			$array = array($config_var[3], $config_var[0]);
			if (isset($config_var['min']))
				$array['min'] = $config_var['min'];
			if (isset($config_var['max']))
				$array['max'] = $config_var['max'];
			$new_settings[] = $array;
		}
		elseif ($config_var[3] != 'select')
			$new_settings[] = array($config_var[3], $config_var[0]);
		else
			$new_settings[] = array($config_var[3], $config_var[0], $config_var[4]);
	}

	// Save the new database-based settings, if any.
	if (!empty($new_settings))
		saveDBSettings($new_settings);

	$context['was_saved_this_page'] = true;
	$_SESSION['settings_saved'] = true;
}

// Helper function for saving database settings.
function saveDBSettings(&$config_vars)
{
	global $context;

	get_inline_board_list();

	foreach ($config_vars as $var)
	{
		if (!isset($var[1]) || (!isset($_POST[$var[1]]) && !in_array($var[0], array('check', 'yesno', 'permissions', 'multi_select')) && ($var[0] != 'bbc' || !isset($_POST[$var[1] . '_enabledTags']))))
			continue;

		// Checkboxes!
		elseif ($var[0] == 'check' || $var[0] == 'yesno')
			$setArray[$var[1]] = !empty($_POST[$var[1]]) ? '1' : '0';
		// Select boxes!
		elseif ($var[0] == 'select' && in_array($_POST[$var[1]], array_keys($var[2])))
			$setArray[$var[1]] = $_POST[$var[1]];
		elseif ($var[0] == 'multi_select')
		{
			// For security purposes we validate this line by line.
			$options = array();
			if (isset($_POST[$var[1]]) && is_array($_POST[$var[1]]))
				foreach ($_POST[$var[1]] as $invar => $on)
					if (isset($var[2][$invar]))
						$options[] = $invar;

			$setArray[$var[1]] = serialize($options);
		}
		// Integers!
		elseif ($var[0] == 'int')
		{
			$setArray[$var[1]] = (int) $_POST[$var[1]];
			if (isset($var['min']) && $setArray[$var[1]] < $var['min'])
				$setArray[$var[1]] = $var['min'];
			if (isset($var['max']) && $setArray[$var[1]] > $var['max'])
				$setArray[$var[1]] = $var['max'];
		}
		// Floating point!
		elseif ($var[0] == 'float')
			$setArray[$var[1]] = (float) $_POST[$var[1]];
		// Percentage!
		elseif ($var[0] == 'percent')
		{
			if ($_POST[$var[1]] == 'SAME') // Non-JS fallback just in case
				$_POST[$var[1]] = isset($_POST[$var[1] . '_nojs']) ? $_POST[$var[1] . '_nojs'] : 0;
			$setArray[$var[1]] = max(min((int) $_POST[$var[1]], 100), 0);
		}
		// Text!
		elseif ($var[0] == 'text' || $var[0] == 'large_text' || $var[0] == 'email')
			$setArray[$var[1]] = $_POST[$var[1]];
		// Passwords!
		elseif ($var[0] == 'password')
		{
			if (isset($_POST[$var[1]][1]) && $_POST[$var[1]][0] == $_POST[$var[1]][1])
				$setArray[$var[1]] = $_POST[$var[1]][0];
		}
		// BBC.
		elseif ($var[0] == 'bbc')
		{
			$bbcTags = array();
			foreach (parse_bbc(false) as $tag)
				$bbcTags[] = $tag['tag'];

			if (!isset($_POST[$var[1] . '_enabledTags']))
				$_POST[$var[1] . '_enabledTags'] = array();
			elseif (!is_array($_POST[$var[1] . '_enabledTags']))
				$_POST[$var[1] . '_enabledTags'] = array($_POST[$var[1] . '_enabledTags']);

			$setArray[$var[1]] = implode(',', array_diff($bbcTags, $_POST[$var[1] . '_enabledTags']));
		}
		// Permissions?
		elseif ($var[0] == 'permissions')
			$inlinePermissions[$var[1]] = isset($var['exclude']) ? $var['exclude'] : array();
		elseif ($var[0] == 'boards')
		{
			// For security purposes we validate this line by line.
			$options = array();
			if (isset($_POST[$var[1]]) && is_array($_POST[$var[1]]))
				foreach ($_POST[$var[1]] as $invar => $on)
					if (isset($context['board_array'][$invar]))
						$options[] = $invar;

			$setArray[$var[1]] = serialize($options);
		}
	}

	if (!empty($setArray))
		updateSettings($setArray);

	// If we have inline permissions we need to save them.
	if (!empty($inlinePermissions) && allowedTo('manage_permissions'))
	{
		loadSource('ManagePermissions');
		save_inline_permissions($inlinePermissions);
	}

	$context['was_saved_this_page'] = true;
	$_SESSION['settings_saved'] = true;
}

function get_inline_board_list()
{
	global $context;

	if (isset($context['board_listing']))
		return;

	$context['board_listing'] = array();
	$context['board_array'] = array();
	$request = wesql::query('
		SELECT b.name AS board_name, b.child_level, b.id_board, c.id_cat AS id_cat, c.name AS cat_name
		FROM {db_prefix}boards AS b
			INNER JOIN {db_prefix}categories AS c ON (b.id_cat = c.id_cat)
		ORDER BY b.board_order');
	while ($row = wesql::fetch_assoc($request))
	{
		if (!isset($context['board_listing'][$row['id_cat']]))
			$context['board_listing'][$row['id_cat']] = array(
				'name' => $row['cat_name'],
				'boards' => array(),
			);
		$context['board_listing'][$row['id_cat']]['boards'][$row['id_board']] = array($row['child_level'], $row['board_name']);
		$context['board_array'][$row['id_board']] = true;
	}
	wesql::free_result($request);
}
