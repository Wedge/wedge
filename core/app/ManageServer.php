<?php
/**
 * Handles the settings for the core forum configuration (paths, database, cookies), plus language configuration.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
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

	void prepareDBSettingContext(array config_vars)
		// !!!

	void saveSettings(array config_vars)
		- saves those settings set from ?action=admin;area=serversettings
		  to the Settings.php file and the database.
		- requires the admin_forum permission.
		- contains arrays of the types of data to save into Settings.php.

	void saveDBSettings(array config_vars)
		// !!!
*/

/*
	Most of the admin panel pages use this standardized setup.
	If you're building a plugin, very often you just need to use <settings-page> in your plugin-info.xml
	file. You only need to worry about this if you have a reason to do any of it manually, and if you
	do, this is how it works.

	Setting up options for one of the setting screens isn't hard. Call prepareDBSettingsContext;
	The basic format for a checkbox is:
		array('check', 'nameInSettingsAndSQL'),

	And for a text box:
		array('text', 'nameInSettingsAndSQL'),

	In these cases, it will look for $txt['nameInSettingsAndSQL'] as the description,
	and $txt['help_nameInSettingsAndSQL'] as the help popup description.

	Here's a quick explanation of how to add a new item:

	* A text input box. For textual values.
	ie.	array('text', 'nameInSettingsAndSQL', optionalInputBoxWidth),

	* A text input box. For numerical values.
	ie.	array('int', 'nameInSettingsAndSQL', optionalInputBoxWidth, 'min' => optionalMin, 'max' => optionalMax, 'step' => optionalSteppingValue),
	The stepping value is if you want a value to be stepped in increments that aren't 1.
	!!! Stepping is only in supported browsers, not actually enforced in code at this time.

	* A text input box. For floating point values.
	ie.	array('float', 'nameInSettingsAndSQL', optionalInputBoxWidth),

	* A large text input box. Used for textual values spanning multiple lines.
	ie.	array('large_text', 'nameInSettingsAndSQL', optionalNumberOfRows),

	* A check box. Either one or zero (boolean.)
	ie.	array('check', 'nameInSettingsAndSQL'),

	* A selection box. Used for the selection of something from a list.
	ie.	array('select', 'nameInSettingsAndSQL', array('valueForSQL' => $txt['displayedValue'])),
	Note that just saying array('first', 'second') will put 0 in the SQL for 'first'.
	You can add groups by using a slightly more complex array format:
	array(dummyKey => array('', '', groupName), firstKey => array(firstKey, entryName), secondKey => array(firstKey, entryName)...)

	* A password input box. Used for passwords, no kidding!
	ie.	array('password', 'nameInSettingsAndSQL', optionalInputBoxWidth),

	* A permission - for picking groups who have a permission.
	ie.	array('permissions', 'manage_groups'),

	* A BBC selection box.
	ie.	array('bbc', 'sig_bbc'),

	For each option:
		array(type (see above), variable name, size/possible values)
	OR	replace the array with just '' for a horizontal rule.
	SET	preinput - to put some HTML prior to the input box.
	SET	postinput - to put some HTML following the input box.
	SET	invalid - to mark the data as invalid.
	PLUS	You can override label and help parameters by forcing their keys in the array, for example:
		array('text', 'invalidlabel', 3, 'label' => 'Actual Label')

	There are also other types available, such as message, warning, desc, title, etc. but let's not get carried away.
*/

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
		'phpinfo' => 'FetchPHPInfo',
	);

	if (strpos(strtolower(PHP_OS), 'win') === 0)
		unset($subActions['loads']);

	// By default we're editing the core settings
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'general';
	$context['sub_action'] = $_REQUEST['sa'];

	// Warn the user if there's any relevant information regarding Settings.php.
	if ($_REQUEST['sa'] != 'phpinfo')
	{
		// Warn the user if the backup of Settings.php failed.
		$context['settings_not_writable'] = !is_writable($boarddir . '/Settings.php');
		$settings_backup_fail = !@is_writable($boarddir . '/Settings_bak.php') || !@copy($boarddir . '/Settings.php', $boarddir . '/Settings_bak.php');

		if ($context['settings_not_writable'])
			$context['settings_message'] = '<p class="center"><strong>' . $txt['settings_not_writable'] . '</strong></p><br>';
		elseif ($settings_backup_fail)
			$context['settings_message'] = '<p class="center"><strong>' . $txt['admin_backup_fail'] . '</strong></p><br>';
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
		variable name, target (db/file), type (constant), size/possible values, helptext.
	OR	an empty string for a horizontal rule.
	OR	a string for a titled section. */
	$config_vars = array(
		array('check', 'maintenance', 'file' => true),
		array('text', 'mtitle', 36, 'file' => true),
		array('text', 'mmessage', 36, 'file' => true),
		'',
		array('float', 'time_offset', 'subtext' => $txt['time_offset_subtext']),
		'default_timezone' => array('select', 'default_timezone', array()),
		'',
		array('check', 'enableCompressedOutput'),
		array('check', 'enableCompressedData'),
		array('check', 'obfuscate_filenames'),
		array('select', 'minify', array(
			'none' => $txt['minify_none'],
			'jsmin' => $txt['minify_jsmin'],
			'closure' => $txt['minify_closure'],
			'packer' => $txt['minify_packer'],
		)),
		array('select', 'jquery_origin', array(
			'local' => $txt['jquery_local'],
			'jquery' => $txt['jquery_jquery'],
			'google' => $txt['jquery_google'],
			'microsoft' => $txt['jquery_microsoft'],
		)),
		'',
		array('check', 'disableHostnameLookup'),
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
			$config_vars['default_timezone'][2][$zone] = array('', '', $region);
		else
			$config_vars['default_timezone'][2][$zone] = array($zone, strtr($place, '_', ' '));
		$last_region = $region;
	}
	// Don't forget UTC!
	$config_vars['default_timezone'][2]['UTC_group'] = array('', '', 'UTC');
	$config_vars['default_timezone'][2]['UTC'] = array('UTC', 'UTC');

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
	prepareDBSettingContext($config_vars);
}

// Basic database and paths settings - database name, host, etc.
function ModifyDatabaseSettings($return_config = false)
{
	global $context, $txt;

	/* If you're writing a mod, it's a bad idea to add things here....
	For each option:
		variable name, description, type (constant), size/possible values, helptext.
	OR	an empty string for a horizontal rule.
	OR	a string for a titled section. */
	$config_vars = array(
		array('text', 'db_server', 'file' => true),
		array('text', 'db_user', 'file' => true),
		array('password', 'db_passwd', 'file' => true),
		array('text', 'db_name', 'file' => true),
		array('text', 'db_prefix', 'file' => true),
		array('check', 'db_persist', 'file' => true),
		array('check', 'db_error_send', 'file' => true),
		array('text', 'ssi_db_user', 'file' => true),
		array('password', 'ssi_db_passwd', 'file' => true),
		'',
		array('check', 'autoFixDatabase'),
		array('int', 'autoOptMaxOnline', 'subtext' => $txt['autoOptMaxOnline_subtext']),
	);

	if ($return_config)
		return $config_vars;

	// Setup the template stuff.
	$context['post_url'] = '<URL>?action=admin;area=serversettings;sa=database;save';
	$context['settings_title'] = $txt['database_settings'];
	$context['save_disabled'] = $context['settings_not_writable'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		saveSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=database;' . $context['session_query']);
	}

	// Fill the config array.
	prepareDBSettingContext($config_vars);
}

// This function basically edits anything which is configuration and stored in the database, except for caching.
function ModifyCookieSettings($return_config = false)
{
	global $context, $txt, $settings, $cookiename, $user_settings;

	// Define the variables we want to edit.
	$config_vars = array(
		// Cookies...
		array('text', 'cookiename', 20, 'file' => true),
		array('int', 'cookieTime'),
		array('check', 'localCookies', 'subtext' => $txt['localCookies_subtext']),
		array('check', 'globalCookies', 'subtext' => $txt['globalCookies_subtext']),
		array('check', 'secureCookies', 'disabled' => !isset($_SERVER['HTTPS']) || !($_SERVER['HTTPS'] == '1' || strtolower($_SERVER['HTTPS']) == 'on'), 'subtext' => $txt['secureCookies_subtext']),
		'',
		// Sessions
		array('check', 'databaseSession_enable'),
		array('check', 'databaseSession_loose'),
		array('int', 'databaseSession_lifetime'),
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
	prepareDBSettingContext($config_vars);
}

// Simply modifying cache functions
function ModifyCacheSettings($return_config = false)
{
	global $context, $txt, $settings, $memcached_servers;

	// Detect available optimizers.
	$detected = array();
	$detected['APC'] = function_exists('apc_fetch') && function_exists('apc_store') && is_callable('apc_store');
	$detected['Zend'] = (function_exists('output_cache_put') && is_callable('output_cache_put')) || (function_exists('zend_shm_cache_store') && is_callable('zend_shm_cache_store'));
	$detected['Memcached'] = function_exists('memcache_get') && function_exists('memcache_set') && is_callable('memcache_set');
	$detected['XCache'] = function_exists('xcache_set') && is_callable('xcache_set') && ini_get('xcache.var_size') > 0;
	$available_cache = array_keys(array_filter($detected));

	// Define the variable(s) we want to edit. By default, just the one.
	$config_vars = array(
		array('select', 'cache_enable', array($txt['cache_off'], $txt['cache_level1'], $txt['cache_level2'], $txt['cache_level3'])),
		array('select', 'cache_type',
			array_merge(
				array('file' => $txt['cache_type_file']),
				array('session' => $txt['cache_type_session']),
				empty($available_cache) ? array() : array_combine(array_map('strtolower', $available_cache), $available_cache)
			),
			'file' => true
		),
	);

	if ($detected['Memcached'] || !empty($memcached_servers))
	{
		$config_vars[] = array('text', 'cache_memcached');
		$settings['cache_memcached'] = isset($memcached_servers) ? $memcached_servers : '';
	}

	if ($return_config)
		return $config_vars;

	// Saving again?
	if (isset($_GET['save']))
	{
		// A short hack to avoid having to go through the mess that is saveSettings().
		$memcached_servers = isset($_POST['cache_memcached']) ? $_POST['cache_memcached'] : '';
		unset($_POST['cache_memcached']);

		clean_cache();
		saveSettings($config_vars);

		if ($detected['Memcached'])
		{
			loadSource('Subs-Admin');
			updateSettingsFile(array('memcached_servers' => "'" . $memcached_servers . "'"));
		}

		redirectexit('action=admin;area=serversettings;sa=cache;' . $context['session_query']);
	}

	$context['post_url'] = '<URL>?action=admin;area=serversettings;sa=cache;save';
	$context['settings_title'] = $txt['caching_settings'];

	$detected_list = array();
	foreach ($available_cache as $type)
		$detected_list[] = $txt['detected_' . $type];

	$context['settings_message'] = sprintf($txt['caching_information'], empty($detected_list) ? $txt['detected_no_caching'] : implode('<br>', $detected_list));

	// Prepare the template.
	prepareDBSettingContext($config_vars);
}

function ModifyLoadBalancingSettings($return_config = false)
{
	global $txt, $context, $settings;

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

		saveSettings($config_vars);
		redirectexit('action=admin;area=serversettings;sa=loads;' . $context['session_query']);
	}

	prepareDBSettingContext($config_vars);
}

function ModifyProxySettings($return_config = false)
{
	global $context, $txt, $settings;

	// Define the variables we want to edit.
	$config_vars = array(
		// Only a couple of settings, but they are important.
		array('check', 'reverse_proxy'),
		array('text', 'reverse_proxy_header'),
		array('large_text', 'reverse_proxy_ips', 'subtext' => $txt['reverse_proxy_one_per_line']),
	);

	if ($return_config)
		return $config_vars;

	// Saving again?
	if (isset($_GET['save']))
	{
		$_POST['reverse_proxy_ips'] = !empty($_POST['reverse_proxy_ips']) ? implode("\n", array_filter(array_map('trim', preg_split('~[\s,]+~', $_POST['reverse_proxy_ips'])))) : '';

		saveSettings($config_vars);

		// We have to manually force the clearing of the cache otherwise the changed settings might not get noticed.
		$settings['cache_enable'] = 1;
		cache_put_data('settings', null, 'forever');

		redirectexit('action=admin;area=serversettings;sa=proxy;' . $context['session_query']);
	}

	$context['post_url'] = '<URL>?action=admin;area=serversettings;sa=proxy;save';
	$context['settings_title'] = $txt['proxy_settings'];

	// Prepare the template.
	prepareDBSettingContext($config_vars);
}

function ModifyDebugSettings($return_config = false)
{
	global $context, $txt;

	add_js('
	function update_show_debug()
	{
		$("#db_show_debug_who,#db_show_debug_who_log").attr("disabled", !$("#db_show_debug").is(":checked")).sb();
	}
	update_show_debug();');

	$config_vars = array(
		array('check', 'disableTemplateEval'),
		array('check', 'timeLoadPageEnable'),
		'',
		array('check', 'db_show_debug', 'file' => true, 'onclick' => 'update_show_debug()'),
		array('select', 'db_show_debug_who', array(
			'none' => $txt['db_show_debug_none'],
			'admin' => $txt['db_show_debug_admin'],
			'mod' => $txt['db_show_debug_admin_mod'],
			'regular' => $txt['db_show_debug_regular'],
			'any' => $txt['db_show_debug_any'],
		)),
		array('select', 'db_show_debug_who_log', array(
			'none' => $txt['db_show_debug_none'],
			'admin' => $txt['db_show_debug_admin'],
			'mod' => $txt['db_show_debug_admin_mod'],
			'regular' => $txt['db_show_debug_regular'],
			'any' => $txt['db_show_debug_any'],
		)),
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
	prepareDBSettingContext($config_vars);
}

function FetchPHPInfo($return_config = false)
{
	global $context, $txt;

	if ($return_config)
		return array();

	$context['page_title'] = $txt['phpinfo'];
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['phpinfo'],
		'description' => $txt['phpinfo_desc'],
	);

	ob_start();
	phpinfo(INFO_ALL & ~INFO_CREDITS & ~INFO_LICENSE);
	$context['phpinfo'] = ob_get_clean();

	// We've stripped some stuff, it's time to fix the markup.
	$context['phpinfo'] = substr($context['phpinfo'], stripos($context['phpinfo'], '<table') - 1);
	$context['phpinfo'] = substr($context['phpinfo'], 0, strrpos($context['phpinfo'], '</div>'));

	$context['phpinfo'] = strtr($context['phpinfo'], array('class="e">' => 'class="windowbg2">', 'class="v">' => 'class="windowbg">'));

	// Now fix me some headers.
	$context['phpinfo'] = strtr($context['phpinfo'], array('<h2><a' => '<we:cat><a', '</a></h2>' => '</a></we:cat>', '<h2>' => '<we:title>', '</h2>' => '</we:title>'));
	$context['phpinfo'] = str_replace('<hr />', '', $context['phpinfo']);

	// Now make a nice menu out of the headers.
	preg_match_all('~<a name="([^"]+)">([^<]+)</a>~i', $context['phpinfo'], $matches);
	$context['toc'] = empty($matches[1]) || empty($matches[2]) ? array() : array_combine($matches[1], $matches[2]);
	$context['phpinfo'] = preg_replace('~(<a name="[^"]+">)([^<]+)</a>~i', '$1</a>$2', $context['phpinfo']);

	// Time to do some judicious header editing. First, the PHP logo and stuff.
	$context['php_header_icons'] = array();
	$context['phpinfo_version'] = '';
	if (preg_match('~<table.+?www\.php\.net.+?</table>~is', $context['phpinfo'], $matches))
	{
		$context['phpinfo'] = str_replace($matches[0], '', $context['phpinfo']);
		$php_icon = $matches[0];

		if (preg_match('~<img.+?>~i', $php_icon, $matches))
			$context['php_header_icons'][] = $matches[0];

		$context['phpinfo_version'] .= '<strong>' . trim(strip_tags($php_icon)) . '</strong>';
	}

	// Now we match the Zend logo - some occasional debug info here. I'm not a regexp guru enough to be able to get this to point at the table nearest the link, so instead have it skip to a point where it should be hitting the right table anyway.
	if (preg_match('~<table.+?www\.zend\.com.+?</table>~is', $context['phpinfo'], $matches, 0, strpos($context['phpinfo'], 'Build Date')))
	{
		$context['phpinfo'] = str_replace($matches[0], '', $context['phpinfo']);
		$zend_icon = $matches[0];

		if (preg_match('~<img.+?>~i', $zend_icon, $matches))
			$context['php_header_icons'][] = $matches[0];

		$context['phpinfo_version'] .= (empty($context['phpinfo_version']) ? '' : '<br>') . westr::nl2br(trim(strip_tags(strtr($zend_icon, array('<br />' => "\n", '<br>' => "\n")))));
	}

	// Time to fix those tables into something a bit more sane.
	$context['phpinfo'] = str_replace('<table border="0" cellpadding="3" width="600">', '<table class="w100 phpinfo">', $context['phpinfo']);

	// And maybe some CSS to make it slightly less ugly.
	add_css('
	table.phpinfo { table-layout: fixed }
	.phpinfo td { font-size: 85% }
	.phpinfo .windowbg2 { width: 20% }
	.phpinfo .windowbg { word-wrap: break-word }');

	// And we're done.
	wetem::load('phpinfo');
}

// Set simplified setting arrays into more helpful ones.
function prepareDBSettingContext(&$config_vars)
{
	global $txt, $context, $settings;

	// Load the help items, plus any from plugins for (?) icons.
	loadLanguage('Help');
	call_lang_hook('lang_help');

	$context['config_vars'] = array();
	$inlinePermissions = array();
	$bbcChoice = array();
	$boardChoice = array();
	foreach ($config_vars as $config_var)
	{
		// <hr>?
		if (!is_array($config_var))
		{
			$context['config_vars'][] = $config_var;
			continue;
		}

		// If it has no name, it doesn't have any purpose!
		if (empty($config_var[1]))
			continue;

		$type = $config_var[0];
		$name = $config_var[1];

		if ($type == 'boards')
			$boardChoice[] = $name;

		// Special case for inline permissions.
		if ($type == 'permissions' && allowedTo('manage_permissions'))
			$inlinePermissions[$name] = isset($config_var['exclude']) ? $config_var['exclude'] : array();
		elseif ($type == 'permissions')
			continue;

		// Are we showing the BBC selection box?
		if ($type == 'bbc')
			$bbcChoice[] = $name;

		$var = array(
			'label' => isset($config_var['text_label']) ? $config_var['text_label'] : (isset($txt[$name]) ? $txt[$name] : (isset($config_var[3]) && !is_array($config_var[3]) ? $config_var[3] : '')),
			'help' => isset($txt['help_' . $name]) ? $name : '',
			'type' => $type,
			'size' => !empty($config_var[2]) && !is_array($config_var[2]) ? $config_var[2] : ($type == 'int' || $type == 'float' ? 6 : 0),
			'data' => array(),
			'name' => $name,
			'disabled' => (!empty($config_var['file']) && !empty($context['settings_not_writable'])) || !empty($config_var['disabled']),
			'invalid' => !empty($config_var['invalid']),
			'javascript' => '',
			'var_message' => !empty($config_var['message']) && isset($txt[$config_var['message']]) ? $txt[$config_var['message']] : '',
			'preinput' => isset($config_var['preinput']) ? $config_var['preinput'] : '', // Not used.
			'postinput' => isset($config_var['postinput']) ? $config_var['postinput'] : '',
			'subtext' => !empty($config_var['subtext']) ? $config_var['subtext'] : '',
		);

		if (!empty($config_var['file']))
		{
			global $$name;
			$var['value'] = htmlspecialchars($$name);
		}
		elseif (isset($config_var['value']))
			$var['value'] = $config_var['value'];
		elseif (isset($settings[$name]))
			$var['value'] = in_array($type, array('select', 'multi_select', 'boards')) ? $settings[$name] : htmlspecialchars($settings[$name]);
		else
			$var['value'] = in_array($type, array('int', 'float', 'percent')) ? 0 : '';

		// If it's an int, there may be extra stuff.
		if ($type == 'int')
		{
			if (isset($config_var['min']))
				$var['min'] = $config_var['min'];
			if (isset($config_var['max']))
				$var['max'] = $config_var['max'];
			$var['step'] = isset($config_var['step']) ? $config_var['step'] : 1;
		}

		// We need to do a little pre-emptive clean-up for boards.
		if ($type == 'boards')
			$var['value'] = !empty($var['value']) ? unserialize($var['value']) : array();

		// If this is a select box handle any data.
		if (!empty($config_var[2]) && is_array($config_var[2]))
		{
			// If we allow multiple selections, we need to adjust a few things.
			if ($type == 'multi_select')
				$var['value'] = !empty($var['value']) ? unserialize($var['value']) : array();

			// If it's associative...
			if (is_array(reset($config_var[2])))
				$var['data'] = $config_var[2];
			else
				foreach ($config_var[2] as $key => $item)
					$var['data'][] = array($key, $item);
		}

		// Finally allow overrides - and some final cleanups.
		foreach ($config_var as $k => $v)
		{
			if (!is_numeric($k))
			{
				if (substr($k, 0, 2) == 'on')
					$var['javascript'] .= ' ' . $k . '="' . $v . '"';
				else
					$var[$k] = $v;
			}

			// See if there are any other labels that might fit?
			if (isset($txt['setting_' . $name]))
				$var['label'] = $txt['setting_' . $name];
			elseif (isset($txt['groups_' . $name]))
				$var['label'] = $txt['groups_' . $name];
		}
		$context['config_vars'][$name] = $var;
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
				'show_help' => isset($txt['help_' . $tag]),
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

// Helper function. Goes through settings and determines whether to save them in Settings.php or in the settings table.
function saveSettings(&$config_vars)
{
	global $context;

	// Fix the darn stupid cookiename! (More may not be allowed, but these for sure!)
	if (isset($_POST['cookiename']))
		$_POST['cookiename'] = preg_replace('~[,;\s.$]+~u', '', $_POST['cookiename']);

	// Fix the forum's URL if necessary.
	if (isset($_POST['boardurl']))
	{
		if (substr($_POST['boardurl'], -10) == '/index.php')
			$_POST['boardurl'] = substr($_POST['boardurl'], 0, -10);
		elseif (substr($_POST['boardurl'], -1) == '/')
			$_POST['boardurl'] = rtrim($_POST['boardurl'], '/');
		if (substr($_POST['boardurl'], 0, 7) != 'http://' && substr($_POST['boardurl'], 0, 7) != 'file://' && substr($_POST['boardurl'], 0, 8) != 'https://')
			$_POST['boardurl'] = 'http://' . $_POST['boardurl'];
	}

	// Now sort everything into a big array, and figure out arrays and etc.
	$new_settings = array();

	foreach ($config_vars as $config_var)
	{
		// We only want the file-based settings for now, so skip the rest.
		if (empty($config_var['file']))
			continue;

		$type = $config_var[0];
		$name = $config_var[1];

		if ($type == 'password' && isset($_POST[$name][1]) && $_POST[$name][0] == $_POST[$name][1])
			$new_settings[$name] = '\'' . addcslashes($_POST[$name][0], '\'\\') . '\'';

		if ($type == 'select' && isset($_POST[$name]) && isset($config_var[2][$_POST[$name]]))
			$new_settings[$name] = '\'' . addcslashes($_POST[$name], '\'\\') . '\'';

		elseif (($type == 'text' || $type == 'email') && isset($_POST[$name]))
			$new_settings[$name] = '\'' . addcslashes($_POST[$name], '\'\\') . '\'';

		elseif ($type == 'int' && isset($_POST[$name])) // None so far.
			$new_settings[$name] = (int) $_POST[$name];

		elseif ($type == 'check')
			$new_settings[$name] = !empty($_POST[$name]) ? '1' : '0';
	}

	// Save the relevant settings in the Settings.php file.
	loadSource('Subs-Admin');

	if (!empty($new_settings))
		updateSettingsFile($new_settings);

	// Now loopt through the remaining (database-based) settings.
	$new_settings = array();
	foreach ($config_vars as $config_var)
	{
		// We just saved the file-based settings, so skip them.
		if (!is_array($config_var) || isset($config_var['file']))
			continue;

		$type = $config_var[0];
		$name = $config_var[1];

		// Rewrite the definition a bit.
		if ($type == 'int')
		{
			$array = array($type, $name);
			if (isset($config_var['min']))
				$array['min'] = $config_var['min'];
			if (isset($config_var['max']))
				$array['max'] = $config_var['max'];
			$new_settings[] = $array;
		}
		elseif ($type != 'select')
			$new_settings[] = array($type, $name);
		else
			$new_settings[] = array($type, $name, $config_var[2]);
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
		if (!isset($var[1]) || (!isset($_POST[$var[1]]) && !in_array($var[0], array('check', 'yesno', 'permissions', 'multi_select', 'boards')) && ($var[0] != 'bbc' || !isset($_POST[$var[1] . '_enabledTags']))))
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

			$setArray[$var[1]] = !empty($options) ? serialize($options) : '';
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

			$setArray[$var[1]] = !empty($options) ? serialize($options) : '';
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
