<?php
/**********************************************************************************
* SMStats.php                                                                     *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC4                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

/*	Getting stats for simplemachines.org's periodic requests.

	void SMStats()
		- called by simplemachines.org.
		- only returns anything if stats was enabled during installation.
		- can also be accessed by the admin, to show what stats sm.org collects.
		- does not return any data directly to sm.org, instead starts a new request for security.

*/

// This is the function which returns stats to simplemachines.org IF enabled!
// See http://www.simplemachines.org/about/stats.php for more info.
function SMStats()
{
	global $modSettings, $user_info, $forum_version, $sourcedir;

	// First, is it disabled?
	if (empty($modSettings['allow_sm_stats']))
		die();

	// Are we saying who we are, and are we right? (OR an admin)
	if (!$user_info['is_admin'] && (!isset($_GET['sid']) || $_GET['sid'] != $modSettings['allow_sm_stats']))
		die();

	// Verify the referer...
	if (!$user_info['is_admin'] && (!isset($_SERVER['HTTP_REFERER']) || md5($_SERVER['HTTP_REFERER']) != '746cb59a1a0d5cf4bd240e5a67c73085'))
		die();

	// Get some server versions.
	require_once($sourcedir . '/Subs-Admin.php');
	$checkFor = array(
		'php',
		'db_server',
	);
	$serverVersions = getServerVersions($checkFor);

	// Get the actual stats.
	$stats_to_send = array(
		'UID' => $modSettings['allow_sm_stats'],
		'time_added' => time(),
		'members' => $modSettings['totalMembers'],
		'messages' => $modSettings['totalMessages'],
		'topics' => $modSettings['totalTopics'],
		'boards' => 0,
		'php_version' => $serverVersions['php']['version'],
		'database_type' => strtolower($serverVersions['db_server']['title']),
		'database_version' => $serverVersions['db_server']['version'],
		'smf_version' => $forum_version,
		'smfd_version' => $modSettings['smfVersion'],
	);

	// Encode all the data, for security.
	foreach ($stats_to_send as $k => $v)
		$stats_to_send[$k] = urlencode($k) . '=' . urlencode($v);

	// Turn this into the query string!
	$stats_to_send = implode('&', $stats_to_send);

	// If we're an admin, just plonk them out.
	if ($user_info['is_admin'])
		echo $stats_to_send;
	else
	{
		// Connect to the collection script.
		$fp = @fsockopen('www.simplemachines.org', 80, $errno, $errstr);
		if ($fp)
		{
			$length = strlen($stats_to_send);

			$out = 'POST /smf/stats/collect_stats.php HTTP/1.1' . "\r\n";
			$out .= 'Host: www.simplemachines.org' . "\r\n";
			$out .= 'Content-Type: application/x-www-form-urlencoded' . "\r\n";
			$out .= 'Content-Length: ' . $length . "\r\n\r\n";
			$out .= $stats_to_send . "\r\n";
			$out .= 'Connection: Close' . "\r\n\r\n";
			fwrite($fp, $out);
			fclose($fp);
		}
	}

	// Die.
	die('OK');
}

?>