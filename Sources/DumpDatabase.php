<?php
/**
 * Wedge
 *
 * This file provides the database backup options.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * This function manages outputting the backup of the database.
 *
 * - Requires admin-forum permission (not explicitly a true administrator account.
 * - User specifies what of the schema to back up, the structure, and/or the data. (Requires 'struct' and/or 'data' in $_REQUEST respectively)
 * - Session check via form POST.
 * - Extends the database library capabilities with the 'extra' set.
 * - Attempt to extend the limits - time limit upgraded to 5 minutes, memory limit to 256 MB.
 * - If the user has requested compression ('compress' in the POST variables), and GZ encoding is available, begin the appropriate output buffering (and clean the buffer)
 * - Set the appropriate headers (depending on compression) for the file type.
 * - Set the appropriate headers to ensure the content will be sent for download.
 * - Issue the file header.
 * - Step through the tables, output the structure if requested, then iterate through the table itself to output if requested - all to STDOUT (which will be gzipped if the appropriate settings and handler has been fired)
 *
 * @todo Should this function require a true admin account (i.e. $user_info['is_admin'] rather than allowedTo('admin_forum'))
 */
function DumpDatabase2()
{
	global $db_name, $scripturl, $context, $modSettings, $crlf, $db_prefix;

	// Administrators only!
	if (!allowedTo('admin_forum'))
		fatal_lang_error('no_dump_database', 'critical');

	// You can't dump nothing!
	if (!isset($_REQUEST['struct']) && !isset($_REQUEST['data']))
		$_REQUEST['data'] = true;

	checkSession('post');

	// We will need this, badly!
	wesql::extend();

	// Attempt to stop from dying...
	@set_time_limit(600);
	if (@ini_get('memory_limit') < 256)
		@ini_set('memory_limit', '256M');

	// Start saving the output... (don't do it otherwise for memory reasons.)
	if (isset($_REQUEST['compress']) && function_exists('gzencode'))
	{
		// Make sure we're gzipping output, but then say we're not in the header ^_^.
		if (empty($modSettings['enableCompressedOutput']))
			@ob_start('ob_gzhandler');
		// Try to clean any data already outputted.
		elseif (ob_get_length() != 0)
		{
			ob_end_clean();
			@ob_start('ob_gzhandler');
		}

		// Send faked headers so it will just save the compressed output as a gzip.
		header('Content-Type: application/x-gzip');
		header('Accept-Ranges: bytes');
		header('Content-Encoding: none');

		// Gecko browsers... don't like this. (Mozilla, Firefox, etc.)
		if (!$context['browser']['is_gecko'])
			header('Content-Transfer-Encoding: binary');

		// The file extension will include .gz...
		$extension = '.sql.gz';
	}
	else
	{
		// Get rid of the gzipping alreading being done.
		if (!empty($modSettings['enableCompressedOutput']))
			@ob_end_clean();
		// If we can, clean anything already sent from the output buffer...
		elseif (ob_get_length() != 0)
			ob_clean();

		// Tell the client to save this file, even though it's text.
		header('Content-Type: ' . ($context['browser']['is_ie'] || $context['browser']['is_opera'] ? 'application/octetstream' : 'application/octet-stream'));
		header('Content-Encoding: none');

		// This time the extension should just be .sql.
		$extension = '.sql';
	}

	// This should turn off the session URL parser.
	$scripturl = '';

	// Send the proper headers to let them download this file.
	header('Content-Disposition: filename="' . $db_name . '-' . (empty($_REQUEST['struct']) ? 'data' : (empty($_REQUEST['data']) ? 'structure' : 'complete')) . '_' . strftime('%Y-%m-%d') . $extension . '"');
	header('Cache-Control: private');
	header('Connection: close');

	// This makes things simpler when using it so very very often.
	$crlf = "\r\n";

	// SQL Dump Header.
	echo
		'-- ==========================================================', $crlf,
		'--', $crlf,
		'-- Database dump of tables in `', $db_name, '`', $crlf,
		'-- ', timeformat(time(), false), $crlf,
		'--', $crlf,
		'-- ==========================================================', $crlf,
		$crlf;

	// Get all tables in the database....
	if (preg_match('~^`(.+?)`\.(.+?)$~', $db_prefix, $match) != 0)
	{
		$db = strtr($match[1], array('`' => ''));
		$dbp = str_replace('_', '\_', $match[2]);
	}
	else
	{
		$db = false;
		$dbp = $db_prefix;
	}

	// Dump each table.
	$tables = wedbExtra::list_tables(false, $db_prefix . '%');
	foreach ($tables as $tableName)
	{
		if (function_exists('apache_reset_timeout'))
			@apache_reset_timeout();

		// Are we dumping the structures?
		if (isset($_REQUEST['struct']))
		{
			echo
				$crlf,
				'--', $crlf,
				'-- Table structure for table `', $tableName, '`', $crlf,
				'--', $crlf,
				$crlf,
				wedbExtra::table_sql($tableName), ';', $crlf;
		}

		// How about the data?
		if (!isset($_REQUEST['data']) || substr($tableName, -10) == 'log_errors')
			continue;

		// Are there any rows in this table?
		$get_rows = wedbExtra::insert_sql($tableName);

		// No rows to get - skip it.
		if (empty($get_rows))
			continue;

		echo
			$crlf,
			'--', $crlf,
			'-- Dumping data in `', $tableName, '`', $crlf,
			'--', $crlf,
			$crlf,
			$get_rows,
			'-- --------------------------------------------------------', $crlf;
	}

	echo
		$crlf,
		'-- Done', $crlf;

	exit;
}

?>