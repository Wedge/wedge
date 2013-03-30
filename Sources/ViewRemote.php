<?php
/**
 * Wedge
 *
 * Outputs remote files, provided they've been cached locally.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

// Get one of the admin information files from Wedge.org.
function ViewRemote()
{
	global $context;

	wetem::hide();
	ini_set('memory_limit', '32M');

	if (empty($_REQUEST['filename']) || !is_string($_REQUEST['filename']))
		fatal_lang_error('no_access', false);

	$request = wesql::query('
		SELECT data, filetype
		FROM {db_prefix}admin_info_files
		WHERE filename = {string:current_filename}
		LIMIT 1',
		array(
			'current_filename' => $_REQUEST['filename'],
		)
	);

	if (wesql::num_rows($request) == 0)
		fatal_lang_error('admin_file_not_found', true, array($_REQUEST['filename']));

	list ($file_data, $filetype) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Let's make sure we aren't going to output anything nasty.
	clean_output(true);

	// Make sure they know what type of file we are.
	header('Content-Type: ' . $filetype);
	echo $file_data;
	obExit(false);
}
