<?php
/**
 * Various supporting functionality throughout the administration area.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file contains functions that are specifically done by administrators.

	void getServerVersions(array checkFor)
		- get a list of versions that are currently installed on the server.

	void getFileVersions(array versionOptions)
		- get detailed version information about the physical Wedge files on the
		  server.
		- the input parameter allows to set whether to include SSI.php and
		  whether the results should be sorted.
		- returns an array containing information on source files, templates
		  and language files found in the default theme directory (grouped by
		  language).

	void updateAdminPreferences()
		- saves the admins current preferences to the database.

	void emailAdmins(string $template, array $replacements = array(), additional_recipients = array())
		- loads all users who are admins or have the admin forum permission.
		- uses the email template and replacements passed in the parameters.
		- sends them an email.

*/

function getServerVersions($checkFor)
{
	global $txt, $memcached_servers;

	loadSource('media/Class-Media');
	loadLanguage('Admin');

	$tick = '<img src="' . ASSETS . '/aeva/tick.png" class="middle">';
	$untick = '<img src="' . ASSETS . '/aeva/untick2.png" class="middle">';

	$versions = array();

	// Server versions
	if (in_array('server', $checkFor))
		$versions['server'] = array('title' => $txt['support_versions_server'], 'version' => $_SERVER['SERVER_SOFTWARE']);
	if (in_array('php', $checkFor))
		$versions['php'] = array('title' => 'PHP', 'version' => PHP_VERSION);
	// Now let's check for the Database.
	if (in_array('db_server', $checkFor))
	{
		loadSource('Class-DBHelper');
		if (!wesql::is_connected())
			trigger_error('getServerVersions(): you need to be connected to the database in order to get its server version', E_USER_NOTICE);
		else
			$versions['db_server'] = array('title' => sprintf($txt['support_versions_db'], 'MySQL'), 'version' => wedb::get_version());
	}

	// Now the right hand column
	if (in_array('safe_mode', $checkFor))
	{
		// It used to be simple. Now it isn't, with safe mode being deprecated and phased out.
		$safe_mode = ini_get('safe_mode');
		$safe_mode = !(empty($safe_mode) || $safe_mode == 'off');
		$versions['safe_mode'] = array(
			'title' => $txt['support_safe_mode'],
			'version' => ($safe_mode ? $untick : $tick) . ' ' . ($safe_mode ? $txt['support_safe_mode_enabled'] : $txt['support_safe_mode_disabled'])
		);
	}
	// Is GD available? If it is, we should show version information for it too.
	if (in_array('gd', $checkFor) && function_exists('gd_info'))
	{
		$temp = gd_info();
		$versions['gd'] = array(
			'title' => $txt['support_versions_gd'], 'version' => $temp['GD Version']);
	}
	// What about FFMPEG?
	if (in_array('ffmpeg', $checkFor))
		$versions['ffmpeg'] = array('title' => $txt['support_ffmpeg'], 'version' => class_exists('ffmpeg_movie') ? $tick . ' ' . $txt['support_available'] : $untick . ' ' . $txt['support_not_available']);

	// ImageMagick?
	if (in_array('imagick', $checkFor))
	{
		$data = array();
		$test = new media_handler;
		if ($test->testIMagick())
		{
			$data['imagick'] = true;
			$imagick = new Imagick;
			$data['imagick_ver'] = $imagick->getVersion();
			$imv = $data['imagick_ver']['versionString'];
		}
		if ($test->testMW())
		{
			$data['mw'] = true;
			$data['mw_ver'] = MagickGetVersion();
			$imv = $data['mw_ver'][0];
		}
		if ($im_ver = $test->testImageMagick())
			$imv = $im_ver;
		if (isset($imv))
			$versions['imagick'] = array(
				'title' => $txt['support_imagemagick'],
				'version' => $tick . ' (' . $imv . ')</em><br>
				&nbsp; ' . $txt['support_imagick'] . ': <em>' . (isset($data['imagick']) ? $tick . ' ' . $txt['support_available'] : $untick . ' ' . $txt['support_not_available']) . '</em><br>
				&nbsp; ' . $txt['support_MW'] . ': <em>' . (isset($data['mw']) ? $tick . ' ' . $txt['support_available'] : $untick . ' ' . $txt['support_not_available']), // don't end the /em tag! The template already does that...
			);
		else
			$versions['imagick'] = array('title' => $txt['support_imagemagick'], 'version' => $untick . ' ' . $txt['support_not_available']);
	}

	// Check to see if we have any accelerators installed...
	if (in_array('apc', $checkFor) && extension_loaded('apc'))
		$versions['apc'] = array('title' => 'APC (Alternative PHP Cache)', 'version' => phpversion('apc'));
	if (in_array('memcache', $checkFor) && isset($memcached_servers) && trim($memcached_servers) != '')
		$versions['memcache'] = array('title' => 'Memcached', 'version' => function_exists('memcache_get_version') ? memcache_get_version(get_memcached_server()) : '???');
	if (in_array('xcache', $checkFor) && function_exists('xcache_set'))
		$versions['xcache'] = array('title' => 'XCache', 'version' => XCACHE_VERSION);

	return $versions;
}

// Search through source, theme and language files to determine their version.
function getFileVersions(&$versionOptions)
{
	// Default place to find the languages would be the default theme dir.
	$lang_dir = LANGUAGES_DIR;

	$version_info = array(
		'file_versions' => array(),
		'template_versions' => array(),
		'language_versions' => array(),
	);

	// Find the version in SSI.php's file header.
	if (!empty($versionOptions['include_ssi']) && file_exists(ROOT_DIR . '/core/SSI.php'))
	{
		$fp = fopen(ROOT_DIR . '/core/SSI.php', 'rb');
		$header = fread($fp, 4096);
		fclose($fp);

		// The comment looks roughly like... that.
		if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
			$version_info['file_versions']['SSI.php'] = $match[1];
		// Not found! This is bad.
		else
			$version_info['file_versions']['SSI.php'] = '??';
	}

	// Do the paid subscriptions handler?
	if (!empty($versionOptions['include_subscriptions']) && file_exists(ROOT_DIR . '/subscriptions.php'))
	{
		$fp = fopen(ROOT_DIR . '/subscriptions.php', 'rb');
		$header = fread($fp, 4096);
		fclose($fp);

		// Found it?
		if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
			$version_info['file_versions']['subscriptions.php'] = $match[1];
		// If we haven't how do we all get paid?
		else
			$version_info['file_versions']['subscriptions.php'] = '??';
	}

	// Load all the files in the core/app directory, except for this file and the redirect.
	$sources_dir = dir(APP_DIR);
	while ($entry = $sources_dir->read())
	{
		if (substr($entry, -4) === '.php' && !is_dir(APP_DIR . '/' . $entry) && $entry !== 'index.php')
		{
			// Read the first 4k from the file.... enough for the header.
			$fp = fopen(APP_DIR . '/' . $entry, 'rb');
			$header = fread($fp, 4096);
			fclose($fp);

			// Look for the version comment in the file header.
			if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
				$version_info['file_versions'][$entry] = $match[1];
			// It wasn't found, but the file was... show a '??'.
			else
				$version_info['file_versions'][$entry] = '??';
		}
	}
	$sources_dir->close();

	// Load all the files in the template folder.
	$dirname = TEMPLATES_DIR;
	$this_dir = dir($dirname);
	while ($entry = $this_dir->read())
	{
		if (substr($entry, -12) == 'template.php' && !is_dir($dirname . '/' . $entry))
		{
			// Read the first 768 bytes from the file.... enough for the header.
			$fp = fopen($dirname . '/' . $entry, 'rb');
			$header = fread($fp, 768);
			fclose($fp);

			// Look for the version comment in the file header.
			if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
				$version_info['template_versions'][$entry] = $match[1];
			// It wasn't found, but the file was... show a '??'.
			else
				$version_info['template_versions'][$entry] = '??';
		}
	}
	$this_dir->close();

	// Load up all the files in the default language directory and sort by language.
	$this_dir = dir($lang_dir);
	while ($entry = $this_dir->read())
	{
		if (substr($entry, -4) == '.php' && $entry != 'index.php' && !is_dir($lang_dir . '/' . $entry))
		{
			// Read the first 768 bytes from the file.... enough for the header.
			$fp = fopen($lang_dir . '/' . $entry, 'rb');
			$header = fread($fp, 768);
			fclose($fp);

			// Split the file name off into useful bits.
			list ($name, $language) = explode('.', $entry);

			// Look for the version comment in the file header.
			if (preg_match('~(?://|/\*)\s*Version:\s+(.+?);\s*' . preg_quote($name, '~') . '(?:[\s]{2}|\*/)~i', $header, $match) == 1)
				$version_info['language_versions'][$language][$name] = $match[1];
			// It wasn't found, but the file was... show a '??'.
			else
				$version_info['language_versions'][$language][$name] = '??';
		}
	}
	$this_dir->close();

	// Sort the file versions by filename.
	if (!empty($versionOptions['sort_results']))
	{
		ksort($version_info['file_versions']);
		ksort($version_info['template_versions']);
		ksort($version_info['language_versions']);

		// For languages sort each language too.
		foreach ($version_info['language_versions'] as $language => $dummy)
			ksort($version_info['language_versions'][$language]);
	}
	return $version_info;
}

function updateAdminPreferences()
{
	global $options, $context;

	// This must exist!
	if (!isset($context['admin_preferences']))
		return false;

	// This is what we'll be saving.
	$options['admin_preferences'] = serialize($context['admin_preferences']);

	// Update the options table.
	wesql::insert('replace',
		'{db_prefix}themes',
		array('id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		array(MID, 'admin_preferences', $options['admin_preferences'])
	);

	// Make sure we invalidate the cache.
	cache_put_data('theme_settings:' . MID, null, 60);
}

// Send all the administrators a lovely email.
function emailAdmins($template, $replacements = array(), $additional_recipients = array())
{
	global $settings;

	// We certainly want this.
	loadSource('Subs-Post');

	// Load all groups which are effectively admins.
	$request = wesql::query('
		SELECT id_group
		FROM {db_prefix}permissions
		WHERE permission = {literal:admin_forum}
			AND add_deny = {int:add_deny}
			AND id_group != {int:id_group}',
		array(
			'add_deny' => 1,
			'id_group' => 0,
		)
	);
	$groups = array(1);
	while ($row = wesql::fetch_assoc($request))
		$groups[] = $row['id_group'];
	wesql::free_result($request);

	$request = wesql::query('
		SELECT id_member, member_name, real_name, lngfile, email_address
		FROM {db_prefix}members
		WHERE (id_group IN ({array_int:group_list}) OR FIND_IN_SET({raw:group_array_implode}, additional_groups) != 0)
			AND notify_types != {int:notify_types}
		ORDER BY lngfile',
		array(
			'group_list' => $groups,
			'notify_types' => 4,
			'group_array_implode' => implode(', additional_groups) != 0 OR FIND_IN_SET(', $groups),
		)
	);
	$emails_sent = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Stick their particulars in the replacement data.
		$replacements['IDMEMBER'] = $row['id_member'];
		$replacements['REALNAME'] = $row['real_name'];
		$replacements['USERNAME'] = $row['member_name'];

		// Load the data from the template.
		$emaildata = loadEmailTemplate($template, $replacements, empty($row['lngfile']) || empty($settings['userLanguage']) ? $settings['language'] : $row['lngfile']);

		// Then send the actual email.
		sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);

		// Track who we emailed so we don't do it twice.
		$emails_sent[] = $row['email_address'];
	}
	wesql::free_result($request);

	// Any additional users we must email this to?
	if (!empty($additional_recipients))
		foreach ($additional_recipients as $recipient)
		{
			if (in_array($recipient['email'], $emails_sent))
				continue;

			$replacements['IDMEMBER'] = $recipient['id'];
			$replacements['REALNAME'] = $recipient['name'];
			$replacements['USERNAME'] = $recipient['name'];

			// Load the template again.
			$emaildata = loadEmailTemplate($template, $replacements, empty($recipient['lang']) || empty($settings['userLanguage']) ? $settings['language'] : $recipient['lang']);

			// Send off the email.
			sendmail($recipient['email'], $emaildata['subject'], $emaildata['body'], null, null, false, 1);
		}
}
