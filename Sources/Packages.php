<?php
/**
 * Wedge
 *
 * Handles a number of minor operations concerning the package manager.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*
	void Packages()
		// !!!

	void PackageInstallTest()
		// !!!

	void PackageInstall()
		// !!!

	void PackageList()
		// !!!

	void ExamineFile()
		// !!!

	void FlushInstall()
		// !!!

	void PackageRemove()
		// !!!

	void PackageOptions()
		// !!!

	void ViewOperations()
		// !!!
*/

// This is the notoriously defunct package manager..... :/
function Packages()
{
	global $txt, $scripturl, $context;

	// !! Remove this!
	if (isset($_GET['get']) || isset($_GET['pgdownload']))
	{
		loadSource('PackageGet');
		return PackageGet();
	}

	isAllowedTo('admin_forum');

	// Load all the basic stuff.
	loadSource('Subs-Package');
	loadLanguage('Packages');
	loadTemplate('Packages');

	$context['page_title'] = $txt['plugin_manager'];

	// Delegation makes the world... that is, the package manager go 'round.
	$subActions = array(
		'ftptest' => 'PackageFTPTest',
		'perms' => 'PackagePermissions',
	);

	// Work out exactly who it is we are calling.
	if (isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]))
		$context['sub_action'] = $_REQUEST['sa'];
	else
	{
		loadSource('PackageGet');
		return PackageGet();
	}

	// Set up some tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['package_manager'],
		// !!! 'help' => 'registrations',
		'description' => $txt['package_manager_desc'],
		'tabs' => array(
			'packageget' => array(
				'description' => $txt['download_packages_desc'],
			),
			'perms' => array(
				'description' => $txt['package_file_perms_desc'],
			),
		),
	);

	// Call the function we're handing control to.
	$subActions[$context['sub_action']]();
}

// Allow the admin to reset permissions on files.
function PackagePermissions()
{
	global $context, $txt, $settings, $boarddir, $sourcedir, $cachedir, $package_ftp;

	// Let's try and be good, yes?
	checkSession('get');

	// If we're restoring permissions this is just a pass through really.
	if (isset($_GET['restore']))
	{
		create_chmod_control(array(), array(), true);
		fatal_lang_error('no_access', false);
	}

	// This is a memory eat.
	ini_set('memory_limit', '128M');
	@set_time_limit(600);

	// Load up some FTP stuff.
	create_chmod_control();

	if (empty($package_ftp) && !isset($_POST['skip_ftp']))
	{
		loadSource('Class-FTP');
		$ftp = new ftp_connection(null);
		list ($username, $detect_path, $found_path) = $ftp->detect_path($boarddir);

		$context['package_ftp'] = array(
			'server' => isset($settings['package_server']) ? $settings['package_server'] : 'localhost',
			'port' => isset($settings['package_port']) ? $settings['package_port'] : '21',
			'username' => empty($username) ? (isset($settings['package_username']) ? $settings['package_username'] : '') : $username,
			'path' => $detect_path,
			'form_elements_only' => true,
		);
	}
	else
		$context['ftp_connected'] = true;

	// Define the template.
	$context['page_title'] = $txt['package_file_perms'];
	wetem::load('file_permissions');

	// Define what files we're interested in, as a tree.
	$context['file_tree'] = array(
		strtr($boarddir, array('\\' => '/')) => array(
			'type' => 'dir',
			'contents' => array(
				'Settings.php' => array(
					'type' => 'file',
					'writable_on' => 'restrictive',
				),
				'Settings_bak.php' => array(
					'type' => 'file',
					'writable_on' => 'restrictive',
				),
				'attachments' => array(
					'type' => 'dir',
					'writable_on' => 'restrictive',
				),
				'avatars' => array(
					'type' => 'dir',
					'writable_on' => 'standard',
				),
				'cache' => array(
					'type' => 'dir',
					'writable_on' => 'restrictive',
				),
				'custom_avatar_dir' => array(
					'type' => 'dir',
					'writable_on' => 'restrictive',
				),
				'Smileys' => array(
					'type' => 'dir_recursive',
					'writable_on' => 'standard',
				),
				'Sources' => array(
					'type' => 'dir',
					'list_contents' => true,
					'writable_on' => 'standard',
				),
				'Themes' => array(
					'type' => 'dir_recursive',
					'writable_on' => 'standard',
					'contents' => array(
						'default' => array(
							'type' => 'dir_recursive',
							'list_contents' => true,
							'contents' => array(
								'languages' => array(
									'type' => 'dir',
									'list_contents' => true,
								),
							),
						),
					),
				),
				'Packages' => array(
					'type' => 'dir',
					'writable_on' => 'standard',
					'contents' => array(
						'temp' => array(
							'type' => 'dir',
						),
						'backup' => array(
							'type' => 'dir',
						),
						'installed.list' => array(
							'type' => 'file',
							'writable_on' => 'standard',
						),
					),
				),
			),
		),
	);

	// Directories that can move.
	if (substr($sourcedir, 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['Sources']);
		$context['file_tree'][strtr($sourcedir, array('\\' => '/'))] = array(
			'type' => 'dir',
			'list_contents' => true,
			'writable_on' => 'standard',
		);
	}

	// Moved the cache?
	if (substr($cachedir, 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['cache']);
		$context['file_tree'][strtr($cachedir, array('\\' => '/'))] = array(
			'type' => 'dir',
			'list_contents' => false,
			'writable_on' => 'restrictive',
		);
	}

	// Are we using multiple attachment directories?
	if (!empty($settings['currentAttachmentUploadDir']))
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['attachments']);

		if (!is_array($settings['attachmentUploadDir']))
			$settings['attachmentUploadDir'] = unserialize($settings['attachmentUploadDir']);

		// !!! Should we suggest non-current directories be read only?
		foreach ($settings['attachmentUploadDir'] as $dir)
			$context['file_tree'][strtr($dir, array('\\' => '/'))] = array(
			'type' => 'dir',
			'writable_on' => 'restrictive',
		);

	}
	elseif (substr($settings['attachmentUploadDir'], 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['attachments']);
		$context['file_tree'][strtr($settings['attachmentUploadDir'], array('\\' => '/'))] = array(
			'type' => 'dir',
			'writable_on' => 'restrictive',
		);
	}

	if (substr($settings['smileys_dir'], 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['Smileys']);
		$context['file_tree'][strtr($settings['smileys_dir'], array('\\' => '/'))] = array(
			'type' => 'dir_recursive',
			'writable_on' => 'standard',
		);
	}
	if (substr($settings['avatar_directory'], 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['avatars']);
		$context['file_tree'][strtr($settings['avatar_directory'], array('\\' => '/'))] = array(
			'type' => 'dir',
			'writable_on' => 'standard',
		);
	}
	if (isset($settings['custom_avatar_dir']) && substr($settings['custom_avatar_dir'], 0, strlen($boarddir)) != $boarddir)
	{
		unset($context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['custom_avatar_dir']);
		$context['file_tree'][strtr($settings['custom_avatar_dir'], array('\\' => '/'))] = array(
			'type' => 'dir',
			'writable_on' => 'restrictive',
		);
	}

	// Load up any custom themes.
	$request = wesql::query('
		SELECT value
		FROM {db_prefix}themes
		WHERE id_theme > {int:default_theme_id}
			AND id_member = {int:guest_id}
			AND variable = {string:theme_dir}
		ORDER BY value ASC',
		array(
			'default_theme_id' => 1,
			'guest_id' => 0,
			'theme_dir' => 'theme_dir',
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		if (substr(strtolower(strtr($row['value'], array('\\' => '/'))), 0, strlen($boarddir) + 7) == strtolower(strtr($boarddir, array('\\' => '/')) . '/Themes'))
			$context['file_tree'][strtr($boarddir, array('\\' => '/'))]['contents']['Themes']['contents'][substr($row['value'], strlen($boarddir) + 8)] = array(
				'type' => 'dir_recursive',
				'list_contents' => true,
				'contents' => array(
					'languages' => array(
						'type' => 'dir',
						'list_contents' => true,
					),
				),
			);
		else
		{
			$context['file_tree'][strtr($row['value'], array('\\' => '/'))] = array(
				'type' => 'dir_recursive',
				'list_contents' => true,
				'contents' => array(
					'languages' => array(
						'type' => 'dir',
						'list_contents' => true,
					),
				),
			);
		}
	}
	wesql::free_result($request);

	// If we're submitting then let's move on to another function to keep things cleaner..
	if (isset($_POST['action_changes']))
		return PackagePermissionsAction();

	$context['look_for'] = array();
	// Are we looking for a particular tree - normally an expansion?
	if (!empty($_REQUEST['find']))
		$context['look_for'][] = base64_decode($_REQUEST['find']);
	// Only that tree?
	$context['only_find'] = we::$is_ajax && !empty($_REQUEST['onlyfind']) ? $_REQUEST['onlyfind'] : '';
	if ($context['only_find'])
		$context['look_for'][] = $context['only_find'];

	// Have we got a load of back-catalogue trees to expand from a submit etc?
	if (!empty($_GET['back_look']))
	{
		$potententialTrees = unserialize(base64_decode($_GET['back_look']));
		foreach ($potententialTrees as $tree)
			$context['look_for'][] = $tree;
	}
	// ... maybe posted?
	if (!empty($_POST['back_look']))
		$context['only_find'] = array_merge($context['only_find'], $_POST['back_look']);

	$context['back_look_data'] = base64_encode(serialize(array_slice($context['look_for'], 0, 15)));

	// Are we finding more files than first thought?
	$context['file_offset'] = !empty($_REQUEST['fileoffset']) ? (int) $_REQUEST['fileoffset'] : 0;
	// Don't list more than this many files in a directory.
	$context['file_limit'] = 150;

	// How many levels shall we show?
	$context['default_level'] = empty($context['only_find']) ? 2 : 25;

	// This will be used if we end up catching XML data.
	$context['xml_data'] = array(
		'roots' => array(
			'identifier' => 'root',
			'children' => array(
				array(
					'value' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find']),
				),
			),
		),
		'folders' => array(
			'identifier' => 'folder',
			'children' => array(),
		),
	);

	foreach ($context['file_tree'] as $path => $data)
	{
		// Run this directory.
		if (file_exists($path) && (empty($context['only_find']) || substr($context['only_find'], 0, strlen($path)) == $path))
		{
			// Get the first level down only.
			fetchPerms__recursive($path, $context['file_tree'][$path], 1);
			$context['file_tree'][$path]['perms'] = array(
				'chmod' => @is_writable($path),
				'perms' => @fileperms($path),
			);
		}
		else
			unset($context['file_tree'][$path]);
	}

	// Is this actually xml?
	if (we::$is_ajax)
	{
		loadTemplate('Xml');
		wetem::load('generic_xml');
		wetem::hide();
	}
}

function fetchPerms__recursive($path, &$data, $level)
{
	global $context;

	$isLikelyPath = false;
	foreach ($context['look_for'] as $possiblePath)
		if (substr($possiblePath, 0, strlen($path)) == $path)
			$isLikelyPath = true;

	// Is this where we stop?
	if (we::$is_ajax && !empty($context['look_for']) && !$isLikelyPath)
		return;
	elseif ($level > $context['default_level'] && !$isLikelyPath)
		return;

	// Are we actually interested in saving this data?
	$save_data = empty($context['only_find']) || $context['only_find'] == $path;

	// !! Shouldn't happen - but better error message?
	if (!is_dir($path))
		fatal_lang_error('no_access', false);

	// This is where we put stuff we've found for sorting.
	$foundData = array(
		'files' => array(),
		'folders' => array(),
	);

	$dh = scandir($path);
	foreach ($dh as $entry)
	{
		// Some kind of file?
		if (!is_dir($path . '/' . $entry))
		{
			// Are we listing PHP files in this directory?
			if ($save_data && !empty($data['list_contents']) && substr($entry, -4) == '.php')
				$foundData['files'][$entry] = true;
			// A file we were looking for.
			elseif ($save_data && isset($data['contents'][$entry]))
				$foundData['files'][$entry] = true;
		}
		// It's a directory - we're interested one way or another, probably...
		elseif ($entry != '.' && $entry != '..')
		{
			// Going further?
			if ((!empty($data['type']) && $data['type'] == 'dir_recursive') || (isset($data['contents'][$entry]) && (!empty($data['contents'][$entry]['list_contents']) || (!empty($data['contents'][$entry]['type']) && $data['contents'][$entry]['type'] == 'dir_recursive'))))
			{
				if (!isset($data['contents'][$entry]))
					$foundData['folders'][$entry] = 'dir_recursive';
				else
					$foundData['folders'][$entry] = true;

				// If this wasn't expected inherit the recusiveness...
				if (!isset($data['contents'][$entry]))
					// We need to do this as we will be going all recursive.
					$data['contents'][$entry] = array(
						'type' => 'dir_recursive',
					);

				// Actually do the recursive stuff...
				fetchPerms__recursive($path . '/' . $entry, $data['contents'][$entry], $level + 1);
			}
			// Maybe it is a folder we are not descending into.
			elseif (isset($data['contents'][$entry]))
				$foundData['folders'][$entry] = true;
			// Otherwise we stop here.
		}
	}

	// Nothing to see here?
	if (!$save_data)
		return;

	// Now actually add the data, starting with the folders.
	ksort($foundData['folders']);
	foreach ($foundData['folders'] as $folder => $type)
	{
		$additional_data = array(
			'perms' => array(
				'chmod' => @is_writable($path . '/' . $folder),
				'perms' => @fileperms($path . '/' . $folder),
			),
		);
		if ($type !== true)
			$additional_data['type'] = $type;

		// If there's an offset ignore any folders in XML mode.
		if (we::$is_ajax && $context['file_offset'] == 0)
		{
			$context['xml_data']['folders']['children'][] = array(
				'attributes' => array(
					'writable' => $additional_data['perms']['chmod'] ? 1 : 0,
					'permissions' => substr(sprintf('%o', $additional_data['perms']['perms']), -4),
					'folder' => 1,
					'path' => $context['only_find'],
					'level' => $level,
					'more' => 0,
					'offset' => $context['file_offset'],
					'my_ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find'] . '/' . $folder),
					'ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find']),
				),
				'value' => $folder,
			);
		}
		elseif (!we::$is_ajax)
		{
			if (isset($data['contents'][$folder]))
				$data['contents'][$folder] = array_merge($data['contents'][$folder], $additional_data);
			else
				$data['contents'][$folder] = $additional_data;
		}
	}

	// Now we want to do a similar thing with files.
	ksort($foundData['files']);
	$counter = -1;
	foreach ($foundData['files'] as $file => $dummy)
	{
		$counter++;

		// Have we reached our offset?
		if ($context['file_offset'] > $counter)
			continue;
		// Gone too far?
		if ($counter > ($context['file_offset'] + $context['file_limit']))
			continue;

		$additional_data = array(
			'perms' => array(
				'chmod' => @is_writable($path . '/' . $file),
				'perms' => @fileperms($path . '/' . $file),
			),
		);

		// XML?
		if (we::$is_ajax)
		{
			$context['xml_data']['folders']['children'][] = array(
				'attributes' => array(
					'writable' => $additional_data['perms']['chmod'] ? 1 : 0,
					'permissions' => substr(sprintf('%o', $additional_data['perms']['perms']), -4),
					'folder' => 0,
					'path' => $context['only_find'],
					'level' => $level,
					'more' => $counter == ($context['file_offset'] + $context['file_limit']) ? 1 : 0,
					'offset' => $context['file_offset'],
					'my_ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find'] . '/' . $file),
					'ident' => preg_replace('~[^A-Za-z0-9_\-=:]~', ':-:', $context['only_find']),
				),
				'value' => $file,
			);
		}
		elseif ($counter != ($context['file_offset'] + $context['file_limit']))
		{
			if (isset($data['contents'][$file]))
				$data['contents'][$file] = array_merge($data['contents'][$file], $additional_data);
			else
				$data['contents'][$file] = $additional_data;
		}
	}
}

// Actually action the permission changes they want.
function PackagePermissionsAction()
{
	global $context, $txt, $time_start, $package_ftp;

	umask(0);

	$timeout_limit = 5;

	$context['method'] = $_POST['method'] == 'individual' ? 'individual' : 'predefined';
	wetem::load('action_permissions');
	$context['page_title'] = $txt['package_file_perms_applying'];
	$context['back_look_data'] = isset($_POST['back_look']) ? $_POST['back_look'] : array();

	// Skipping use of FTP?
	if (empty($package_ftp))
		$context['skip_ftp'] = true;

	// We'll start off in a good place, security. Make sure that if we're dealing with individual files that they seem in the right place.
	if ($context['method'] == 'individual')
	{
		// Only these path roots are legal.
		$legal_roots = array_keys($context['file_tree']);
		$context['custom_value'] = (int) $_POST['custom_value'];

		// Continuing?
		if (isset($_POST['toProcess']))
			$_POST['permStatus'] = unserialize(base64_decode($_POST['toProcess']));

		if (isset($_POST['permStatus']))
		{
			$context['to_process'] = array();
			$validate_custom = false;
			foreach ($_POST['permStatus'] as $path => $status)
			{
				// Nothing to see here?
				if ($status == 'no_change')
					continue;
				$legal = false;
				foreach ($legal_roots as $root)
					if (substr($path, 0, strlen($root)) == $root)
						$legal = true;

				if (!$legal)
					continue;

				// Check it exists.
				if (!file_exists($path))
					continue;

				if ($status == 'custom')
					$validate_custom = true;

				// Now add it.
				$context['to_process'][$path] = $status;
			}
			$context['total_items'] = isset($_POST['totalItems']) ? (int) $_POST['totalItems'] : count($context['to_process']);

			// Make sure the chmod status is valid?
			if ($validate_custom)
			{
				if (preg_match('~^[4567][4567][4567]$~', $context['custom_value']) == false)
					fatal_lang_error('chmod_value_invalid');
			}

			// Nothing to do?
			if (empty($context['to_process']))
				redirectexit('action=admin;area=packages;sa=perms' . (!empty($context['back_look_data']) ? ';back_look=' . base64_encode(serialize($context['back_look_data'])) : '') . ';' . $context['session_query']);
		}
		// Should never get here,
		else
			fatal_lang_error('no_access', false);

		// Setup the custom value.
		$custom_value = octdec('0' . $context['custom_value']);

		// Start processing items.
		foreach ($context['to_process'] as $path => $status)
		{
			if (in_array($status, array('execute', 'writable', 'read')))
				package_chmod($path, $status);
			elseif ($status == 'custom' && !empty($custom_value))
			{
				// Use FTP if we have it.
				if (!empty($package_ftp) && !empty($_SESSION['pack_ftp']))
				{
					$ftp_file = strtr($path, array($_SESSION['pack_ftp']['root'] => ''));
					$package_ftp->chmod($ftp_file, $custom_value);
				}
				else
					@chmod($path, $custom_value);
			}

			// This fish is fried...
			unset($context['to_process'][$path]);

			// See if we're out of time?
			if (microtime(true) - $time_start > $timeout_limit)
				return false;
		}
	}
	// If predefined this is a little different.
	else
	{
		$context['predefined_type'] = isset($_POST['predefined']) ? $_POST['predefined'] : 'restricted';

		$context['total_items'] = isset($_POST['totalItems']) ? (int) $_POST['totalItems'] : 0;
		$context['directory_list'] = isset($_POST['dirList']) ? unserialize(base64_decode($_POST['dirList'])) : array();

		$context['file_offset'] = isset($_POST['fileOffset']) ? (int) $_POST['fileOffset'] : 0;

		// Haven't counted the items yet?
		if (empty($context['total_items']))
		{
			function count_directories__recursive($dir)
			{
				global $context;

				$count = 0;
				$dh = @scandir($dir);
				foreach ($dh as $entry)
				{
					if ($entry != '.' && $entry != '..' && is_dir($dir . '/' . $entry))
					{
						$context['directory_list'][$dir . '/' . $entry] = 1;
						$count++;
						$count += count_directories__recursive($dir . '/' . $entry);
					}
				}

				return $count;
			}

			foreach ($context['file_tree'] as $path => $data)
			{
				if (is_dir($path))
				{
					$context['directory_list'][$path] = 1;
					$context['total_items'] += count_directories__recursive($path);
					$context['total_items']++;
				}
			}
		}

		// Have we built up our list of special files?
		if (!isset($_POST['specialFiles']) && $context['predefined_type'] != 'free')
		{
			$context['special_files'] = array();
			function build_special_files__recursive($path, &$data)
			{
				global $context;

				if (!empty($data['writable_on']))
					if ($context['predefined_type'] == 'standard' || $data['writable_on'] == 'restrictive')
						$context['special_files'][$path] = 1;

				if (!empty($data['contents']))
					foreach ($data['contents'] as $name => $contents)
						build_special_files__recursive($path . '/' . $name, $contents);
			}

			foreach ($context['file_tree'] as $path => $data)
				build_special_files__recursive($path, $data);
		}
		// Free doesn't need special files.
		elseif ($context['predefined_type'] == 'free')
			$context['special_files'] = array();
		else
			$context['special_files'] = unserialize(base64_decode($_POST['specialFiles']));

		// Now we definitely know where we are, we need to go through again doing the chmod!
		foreach ($context['directory_list'] as $path => $dummy)
		{
			// Do the contents of the directory first.
			$dh = @scandir($path);
			$file_count = 0;
			$dont_chmod = false;
			foreach ($dh as $entry)
			{
				$file_count++;
				// Actually process this file?
				if (!$dont_chmod && !is_dir($path . '/' . $entry) && (empty($context['file_offset']) || $context['file_offset'] < $file_count))
				{
					$status = $context['predefined_type'] == 'free' || isset($context['special_files'][$path . '/' . $entry]) ? 'writable' : 'execute';
					package_chmod($path . '/' . $entry, $status);
				}

				// See if we're out of time?
				if (!$dont_chmod && microtime(true) - $time_start > $timeout_limit)
				{
					$dont_chmod = true;
					// Don't do this again.
					$context['file_offset'] = $file_count;
				}
			}

			// If this is set it means we timed out half way through.
			if ($dont_chmod)
			{
				$context['total_files'] = $file_count;
				return false;
			}

			// Do the actual directory.
			$status = $context['predefined_type'] == 'free' || isset($context['special_files'][$path]) ? 'writable' : 'execute';
			package_chmod($path, $status);

			// We've finished the directory so no file offset, and no record.
			$context['file_offset'] = 0;
			unset($context['directory_list'][$path]);

			// See if we're out of time?
			if (microtime(true) - $time_start > $timeout_limit)
				return false;
		}
	}

	// If we're here we are done!
	redirectexit('action=admin;area=packages;sa=perms' . (!empty($context['back_look_data']) ? ';back_look=' . base64_encode(serialize($context['back_look_data'])) : '') . ';' . $context['session_query']);
}

// Test an FTP connection.
function PackageFTPTest()
{
	global $context, $txt, $package_ftp;

	checkSession('get');

	// Try to make the FTP connection.
	create_chmod_control(array(), array('force_find_error' => true));

	// Deal with the template stuff.
	loadTemplate('Xml');
	wetem::load('generic_xml');
	wetem::hide();

	// Define the return data, this is simple.
	$context['xml_data'] = array(
		'results' => array(
			'identifier' => 'result',
			'children' => array(
				array(
					'attributes' => array(
						'success' => !empty($package_ftp) ? 1 : 0,
					),
					'value' => !empty($package_ftp) ? $txt['package_ftp_test_success'] : (isset($context['package_ftp'], $context['package_ftp']['error']) ? $context['package_ftp']['error'] : $txt['package_ftp_test_failed']),
				),
			),
		),
	);
}
