<?php
/**
 * Various supporting functionality throughout the administration area.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file contains functions that are specifically done by administrators.
	The most important function in this file for mod makers happens to be the
	updateSettingsFile() function, but it shouldn't be used often anyway.

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

	void updateSettingsFile(array config_vars)
		- updates the Settings.php file with the changes in config_vars.
		- expects config_vars to be an associative array, with the keys as the
		  variable names in Settings.php, and the values the varaible values.
		- does not escape or quote values.
		- preserves case, formatting, and additional options in file.
		- writes nothing if the resulting file would be less than 10 lines
		  in length (sanity check for read lock.)

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
		loadSource('Class-DBPackages');
		if (!wesql::is_connected())
			trigger_error('getServerVersions(): you need to be connected to the database in order to get its server version', E_USER_NOTICE);
		else
			$versions['db_server'] = array('title' => sprintf($txt['support_versions_db'], 'MySQL'), 'version' => wedbPackages::get_version());
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
	// Is GD available?  If it is, we should show version information for it too.
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
	global $boarddir, $sourcedir;

	// Default place to find the languages would be the default theme dir.
	$lang_dir = LANGUAGES_DIR;

	$version_info = array(
		'file_versions' => array(),
		'template_versions' => array(),
		'language_versions' => array(),
	);

	// Find the version in SSI.php's file header.
	if (!empty($versionOptions['include_ssi']) && file_exists($boarddir . '/SSI.php'))
	{
		$fp = fopen($boarddir . '/SSI.php', 'rb');
		$header = fread($fp, 4096);
		fclose($fp);

		// The comment looks roughly like... that.
		if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
			$version_info['file_versions']['SSI.php'] = $match[1];
		// Not found!  This is bad.
		else
			$version_info['file_versions']['SSI.php'] = '??';
	}

	// Do the paid subscriptions handler?
	if (!empty($versionOptions['include_subscriptions']) && file_exists($boarddir . '/subscriptions.php'))
	{
		$fp = fopen($boarddir . '/subscriptions.php', 'rb');
		$header = fread($fp, 4096);
		fclose($fp);

		// Found it?
		if (preg_match('~\*\s@version\s+(.+)[\s]{2}~i', $header, $match) == 1)
			$version_info['file_versions']['subscriptions.php'] = $match[1];
		// If we haven't how do we all get paid?
		else
			$version_info['file_versions']['subscriptions.php'] = '??';
	}

	// Load all the files in the Sources directory, except this file and the redirect.
	$sources_dir = dir($sourcedir);
	while ($entry = $sources_dir->read())
	{
		if (substr($entry, -4) === '.php' && !is_dir($sourcedir . '/' . $entry) && $entry !== 'index.php')
		{
			// Read the first 4k from the file.... enough for the header.
			$fp = fopen($sourcedir . '/' . $entry, 'rb');
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

// Update the Settings.php file.
function updateSettingsFile($config_vars)
{
	global $boarddir, $cachedir;

	// When was Settings.php last changed?
	$last_settings_change = filemtime($boarddir . '/Settings.php');

	// Load the file.  Break it up based on \r or \n, and then clean out extra characters.
	$settingsArray = trim(file_get_contents($boarddir . '/Settings.php'));
	if (strpos($settingsArray, "\n") !== false)
		$settingsArray = explode("\n", $settingsArray);
	elseif (strpos($settingsArray, "\r") !== false)
		$settingsArray = explode("\r", $settingsArray);
	else
		return;

	// Make sure we got a good file.
	if (count($config_vars) == 1 && isset($config_vars['db_last_error']))
	{
		$temp = trim(implode("\n", $settingsArray));
		if (substr($temp, 0, 5) != '<?php' || substr($temp, -2) != '?' . '>')
			return;
		if (strpos($temp, 'sourcedir') === false || strpos($temp, 'boarddir') === false || strpos($temp, 'cookiename') === false)
			return;
	}

	// Presumably, the file has to have stuff in it for this function to be called :P.
	if (count($settingsArray) < 10)
		return;

	foreach ($settingsArray as $k => $dummy)
		$settingsArray[$k] = strtr($dummy, array("\r" => '')) . "\n";

	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		// Don't trim or bother with it if it's not a variable.
		if ($settingsArray[$i][0] !== '$')
			continue;

		$settingsArray[$i] = rtrim($settingsArray[$i]) . "\n";

		// Look through the variables to set....
		foreach ($config_vars as $var => $val)
		{
			if (strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) == 0)
			{
				$comment = strstr(substr($settingsArray[$i], strpos($settingsArray[$i], ';')), '#');
				$settingsArray[$i] = '$' . $var . ' = ' . $val . ';' . ($comment == '' ? '' : "\t\t" . rtrim($comment)) . "\n";

				// This one's been 'used', so to speak.
				unset($config_vars[$var]);
			}
		}

		if (substr(trim($settingsArray[$i]), 0, 2) == '?' . '>')
			$end = $i;
	}

	// This should never happen, but apparently it is happening.
	if (empty($end) || $end < 10)
		$end = count($settingsArray) - 1;

	// Still more?  Add them at the end.
	if (!empty($config_vars))
	{
		if (trim($settingsArray[$end]) == '?' . '>')
			$settingsArray[$end++] = '';
		else
			$end++;

		foreach ($config_vars as $var => $val)
			$settingsArray[$end++] = '$' . $var . ' = ' . $val . ';' . "\n";

		$settingsArray[$end++] = "\n";
		$settingsArray[$end] = '?' . '>';
	}
	else
		$settingsArray[$end] = trim($settingsArray[$end]);

	// Sanity error checking: the file needs to be at least 12 lines.
	if (count($settingsArray) < 12)
		return;

	// Try to avoid a few pitfalls:
	// like a possible race condition,
	// or a failure to write at low diskspace

	// Check before you act: if cache is enabled, we can do a simple test
	// Can we even write things on this filesystem?
	if ((empty($cachedir) || !file_exists($cachedir)) && file_exists($boarddir . '/cache'))
		$cachedir = $boarddir . '/cache';
	$test_fp = @fopen($cachedir . '/settings_update.tmp', 'w+');
	if ($test_fp)
	{
		fclose($test_fp);

		$test_fp = @fopen($cachedir . '/settings_update.tmp', 'r+');
		$written_bytes = fwrite($test_fp, 'test');
		fclose($test_fp);
		@unlink($cachedir . '/settings_update.tmp');

		if ($written_bytes !== strlen('test'))
		{
			// Oops. Low disk space, perhaps..? Don't mess with Settings.php, then.
			return;
		}
	}

	// Protect me from what I want! :P
	clearstatcache();
	if (filemtime($boarddir . '/Settings.php') === $last_settings_change)
	{
		// You asked for it...
		// Blank out the file - done to fix a oddity with some servers.
		$fp = @fopen($boarddir . '/Settings.php', 'w');

		// Is it even writable, though?
		if ($fp)
		{
			fclose($fp);

			$fp = fopen($boarddir . '/Settings.php', 'r+');
			foreach ($settingsArray as $line)
				fwrite($fp, strtr($line, "\r", ''));
			fclose($fp);
		}
	}
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
