<?php
/**
 * General file handling for the plugin manager.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function getWritableObject()
{
	global $pluginsdir, $context, $settings, $boarddir;

	// Normally it'll be on the plugins folder, but there's no reason for it to absolutely be.
	// !! Is $path supposed to be set earlier..?
	$path = $pluginsdir;

	// Easy case, it's directly writable.
	if (is_writable($path))
	{
		loadSource('Class-FileWritable');
		return new weFileWritable();
	}

	// Have we already done it lately? If so, gather everything we need from the session.
	if (!empty($_SESSION['pack_ftp']['type']))
		$context['connect_details'] = $_SESSION['pack_ftp'];

	// They weren't specified before we got here. But maybe we can get them because we've already been given them before.
	elseif (!empty($_POST['connect_pwd']))
	{
		// OK, let's start with looking for a stored connection.
		$context['connect_details'] = empty($settings['default_con']) ? array() : unserialize(base64_decode($settings['default_con']));

		$details = array('srv', 'user', 'pwd', 'port', 'type');
		// Whether we have a stored connection or not, look for details in the $_POST.
		foreach ($details as $detail)
			if (!empty($_POST['connect_' . $detail]))
				$context['connect_details'][$detail] = $_POST['connect_' . $detail];

		foreach ($context['connect_details'] as $val)
			if (empty($val))
				unset($context['connect_details']);

		if (!empty($context['connect_details']['type']) && $context['connect_details']['type'] != 'ftp' && $context['connect_details']['type'] != 'sftp')
			unset($context['connect_details']);

		// FTP often features 'virtualized' paths of sorts, relative to the user's home directory.
		if ($context['connect_details']['type'] == 'ftp' && !empty($_POST['connect_path']))
		{
			loadSource('Class-FTP');
			$class = new ftp_connection($context['connect_details']['srv'], $context['connect_details']['port'], $context['connect_details']['user'], $context['connect_details']['pwd']);

			// We're connected, jolly good!
			if ($class->error === false)
			{
				// Common mistake, so let's try to remedy it...
				if (!$class->chdir($_POST['connect_path']))
				{
					$ftp_error = $class->last_message;
					$class->chdir(preg_replace('~^/home[2]?/[^/]+?~', '', $_POST['connect_path']));
				}

				if (!in_array($_POST['ftp_path'], array('', '/')))
				{
					$ftp_root = strtr($boarddir, array($_POST['connect_path'] => ''));
					if (substr($ftp_root, -1) == '/' && ($_POST['connect_path'] == '' || substr($_POST['connect_path'], 0, 1) == '/'))
						$ftp_root = substr($ftp_root, 0, -1);
				}
				else
					$ftp_root = $boarddir;

				$context['connect_details']['path'] = $ftp_root;
			}
		}
		else
			$context['connect_details']['path'] = '';
	}

	// Do we have details? If not, throw the user at a relevant form. The calling code should have already set up return-to path if it might be an issue.
	if (empty($context['connect_details']))
	{
		loadTemplate('ManagePlugins');
		wetem::load('request_connect_details');
		return false;
	}

	if ($context['connect_details']['type'] == 'ftp')
	{
		if (!isset($class))
		{
			loadSource('Class-FTP');
			$class = new ftp_connection($context['connect_details']['srv'], $context['connect_details']['port'], $context['connect_details']['user'], $context['connect_details']['pwd']);
		}

		if ($class->error !== false)
			$error_message = $class->last_message;
	}
	elseif ($context['connect_details']['type'] == 'sftp')
	{
		// We won't have tested the path beforehand, mostly because SFTP doesn't have the whole virtual folder thing.
		loadSource('Class-SFTP');
		$class = new Net_SFTP($context['connect_details']['srv'], $context['connect_details']['port']);

		if (!$class->login($context['connect_details']['user'], $context['connect_details']['pwd']))
			$error_message = $class->getLastError();
	}

	// Oops, something went wrong!
	if (!empty($error_message))
		fatal_lang_error('could_not_connect_remote', false, htmlspecialchars($error_message));

	// If we're here, we must have got to a point where we could save details because it must have worked as intended, yay!
	if (!empty($_POST['save_connect']))
	{
		$default_con = base64_encode(serialize(array('srv' => $context['connect_details']['srv'], 'port' => $context['connect_details']['port'], 'user' => $context['connect_details']['user'], 'path' => $context['connect_details']['path'])));
		updateSettings(array('default_con' => $default_con));
	}

	$_SESSION['pack_ftp'] = $context['connect_details'];

	return $class;
}

// Return true on success.
function deleteFiletree(&$class, $dir, $delete_dir = true)
{
	$value = true;
	if (!file_exists($dir))
		return false;

	$current_dir = @opendir($dir);
	if ($current_dir == false)
	{
		if ($delete_dir)
		{
			$remote_path = !empty($_SESSION['pack_ftp']['path']) ? strtr($dir, array($_SESSION['pack_ftp']['path'] => '')) : $dir;
			if (!is_writable($dir))
				$class->chmod($remote_path, 0777);
			$class->unlink($remote_path);
		}

		return true;
	}

	while ($entryname = readdir($current_dir))
	{
		if (in_array($entryname, array('.', '..')))
			continue;

		$full_path = $dir . '/' . $entryname;

		if (!is_dir($full_path))
		{
			// It doesn't really matter what perms we use, since we're deleting.
			$remote_file = !empty($_SESSION['pack_ftp']['path']) ? strtr($full_path, array($_SESSION['pack_ftp']['path'] => '')) : $full_path;
			if (!is_writable($full_path))
				$class->chmod($remote_file, 0666);

			$class->unlink($remote_file);
		}
		else
			$value &= deleteFiletree($class, $full_path);
	}

	closedir($current_dir);

	if ($delete_dir)
	{
		$remote_path = !empty($_SESSION['pack_ftp']['path']) ? strtr($dir, array($_SESSION['pack_ftp']['path'] => '')) : $dir;
		if (!is_writable($dir . '/' . $entryname))
			$class->chmod($remote_path, 0777);
		$class->unlink($remote_path);
	}

	return $value;
}

/**
 * Prepare the first stage of plugin installation: validate the file has been uploaded satisfactorily.
 */
function uploadedPluginValidate()
{
	global $context, $txt, $cachedir;

	// It's just possible, however unlikely, that the user has done something silly.
	if (isset($_SESSION['uploadplugin']))
		redirectexit('action=admin;area=plugins;sa=add;upload;stage=1;' . $context['session_query']);

	// So, if we're here, the plugin has literally just been uploaded.
	if (empty($_FILES['plugin']['tmp_name']) || !is_uploaded_file($_FILES['plugin']['tmp_name']))
		fatal_lang_error('plugins_invalid_upload', false);

	// Now we attempt to process the ZIP file.
	try
	{
		// Get the contents, scan the contents for a valid plugin-info.xml file
		$zip = new wextr($_FILES['plugin']['tmp_name']);
		$list = $zip->list_contents();

		$idx = array();
		foreach ($list as $i => $file)
			if (!$file['is_folder'] && ($file['filename'] == 'plugin-info.xml' || preg_match('~/plugin-info\.xml$~', $file['filename'])))
				$idx[] = $i;

		if (count($idx) != 1)
		{
			@unlink($_FILES['plugin']['tmp_name']);
			fatal_lang_error('plugins_invalid_plugin_' . (empty($idx) ? 'no_info' : 'overinfo'), false);
		}

		// If the plugin file is not exactly plugin-info.xml, we need to store the path of it because that's an exclusion path later.
		if ($file['filename'] != 'plugin-info.xml')
			$_SESSION['uploadplugin']['trunc'] = substr($file['filename'], 0, strrpos($file['filename'], '/') + 1);

		// Get said plugin-info.xml file and attempt to make sense of it.
		$file = $zip->extractByIndex($idx);
	}
	catch (wextr_UnableRead_Exception $e)
	{
		@unlink($_FILES['plugin']['tmp_name']);
		fatal_lang_error('plugins_unable_read', false);
	}
	catch (wextr_InvalidZip_Exception $e)
	{
		@unlink($_FILES['plugin']['tmp_name']);
		fatal_lang_error('plugins_invalid_zip', false);
	}
	catch (Exception $e)
	{
		@unlink($_FILES['plugin']['tmp_name']);
		fatal_lang_error('plugins_generic_error', false, array(get_class($e), $e->getLine()));
	}

	// If we're here, we know that the package could be read and that $file contains the plugin-info.xml file. But is it valid?
	$manifest = simplexml_load_string(preg_replace('~\s*<(!DOCTYPE|xsl)[^>]+?>\s*~i', '', $file[$idx[0]]['content']));

	if ($manifest === false || empty($manifest['id']) || empty($manifest->name) || empty($manifest->author) || empty($manifest->version))
	{
		@unlink($_FILES['plugin']['tmp_name']);
		fatal_lang_error('plugins_invalid_plugin_no_info', false);
	}

	// Check the list of requirements stated by the package in terms of PHP, MySQL, required functions.
	if (!empty($manifest->{'min-versions'}))
	{
		$min_versions = testRequiredVersions($manifest->{'min-versions'});
		foreach (array('php', 'mysql') as $test)
			if (isset($min_versions[$test]))
				fatal_lang_error('fatal_install_error_min' . $test, false, $min_versions[$test]);
	}

	// Required functions?
	if (!empty($manifest->{'required-functions'}))
	{
		$required_functions = testRequiredFunctions($manifest->{'required-functions'});
		if (!empty($required_functions))
			fatal_lang_error('fatal_install_error_reqfunc', false, westr::htmlspecialchars(implode(', ', $required_functions)));
	}

	// !!! Should we test for all the hooks too? That's pretty heavy work and it's not like we don't have a ton to do right now!

	// What we do need to do, though, is check against plugins that we have currently enabled. (Not enabled... they can fix that themselves from the main listing.)
	// And we need to store this and make sure it won't be automatically garbage collected.
	$new_file = 'post_plugin_' . MID . '.zip';
	if (!move_uploaded_file($_FILES['plugin']['tmp_name'], $cachedir . '/' . $new_file))
		fatal_lang_error('plugins_invalid_upload', false);

	$id = (string) $manifest['id'];

	$_SESSION['uploadplugin'] = array(
		'file' => $new_file,
		'size' => $_FILES['plugin']['size'],
		'name' => $_FILES['plugin']['name'],
		'mtime' => filemtime($cachedir . '/' . $new_file),
		'md5' => md5_file($cachedir . '/' . $new_file),
		'id' => $id,
		'manifest' => $idx[0],
	);

	if (isset($context['plugins_dir'][$id]))
	{
		$context['page_title'] = $txt['plugin_duplicate_detected_title'];
		// We want to get the existing plugin's name. We know what plugin to look at.
		$existing_plugin = safe_sxml_load($context['plugins_dir'][$id] . '/plugin-info.xml');
		$context['existing_plugin'] = (string) $existing_plugin->name . ' ' . (string) $existing_plugin->version;
		$context['new_plugin'] = (string) $manifest->name . ' ' . (string) $manifest->version;
		wetem::load('upload_duplicate_detected');
	}
	else
	{
		// All the hoops here are mostly for shared servers, and we might as well take it easy a moment now we're successful for now.
		$context['page_title'] = $txt['plugin_upload_successful_title'];
		$context['form_url'] = '<URL>?action=admin;area=plugins;sa=add;upload;stage=1';
		$context['description'] = $txt['plugin_upload_successful'];
		wetem::load('upload_generic_progress');
	}
}

function uploadedPluginConnection()
{
	global $cachedir, $settings, $context, $pluginsdir, $txt;

	// If we already have details, pass through to the next stage.
	if (isset($_SESSION['plugin_ftp']))
		redirectexit('action=admin;area=plugins;sa=add;upload;stage=' . (!empty($_SESSION['uploadplugin']['delete']) ? 2 : 3) . ';' . $context['session_query']);

	// Are we here with a valid plugin?
	$state = validate_plugin_session();
	if (!empty($state))
	{
		clean_up_plugin_session();
		fatal_lang_error($state, false);
	}

	// OK, so we have the plugin here. Next we're going to be getting some S/FTP details, but before we do, was it a duplicate to deal with?
	checkSession('request');
	if (isset($_POST['cancel']))
	{
		// OK, so we're not proceeding with this one, fair enough.
		@unlink($cachedir . '/' . $_SESSION['uploadplugin']['file']);
		unset($_SESSION['uploadplugin']);
		redirectexit('action=admin;area=plugins');
	}
	else
		$_SESSION['uploadplugin']['delete'] = true; // We need to get S/FTP details before we can proceed to install. But flag the existing one for deletion later.

	// OK, so we need to get some details. Let's start with some details.
	$context['ftp_details'] = array(
		'server' => 'localhost',
		'user' => '',
		'password' => '',
		'port' => '21',
		'type' => 'ftp',
		'path' => realpath($pluginsdir),
	);

	if (!empty($settings['ftp_settings']))
	{
		$new = @unserialize($settings['ftp_settings']);
		if (!empty($new))
			$context['ftp_details'] = array_merge($context['ftp_details'], $new);
	}

	// OK, just in case, they might have supplied something.
	foreach (array('server', 'user', 'password') as $item)
		if (isset($_POST[$item]))
			$context['ftp_details'][$item] = $_POST[$item];

	if (isset($_POST['port']))
	{
		$_POST['port'] = (int) $_POST['port'];
		if ($_POST['port'] >= 1 && $_POST['port'] <= 65535)
			$context['ftp_details']['port'] = $_POST['port'];
	}
	if (isset($_POST['type']) && ($_POST['type'] == 'ftp' || $_POST['type'] == 'sftp'))
		$context['ftp_details']['type'] = $_POST['type'];

	// Lastly, the path. The path is serious voodoo evil stuff. Mostly at this stage we're relying on the user to get it right, we hope. SFTP doesn't need this, we already have the real path there.
	if (!empty($_POST['path']))
		$context['ftp_details']['path'] = $_POST['path'];

	// OK, so the password is the one thing the user must have supplied. That's never saved except in session, and we only do that once validating connection.
	if (!empty($context['ftp_details']['password']))
	{
		if ($context['ftp_details']['type'] == 'ftp')
		{
			loadSource('Class-FTP');
			$ftp = new ftp_connection($context['ftp_details']['server'], $context['ftp_details']['port'], $context['ftp_details']['user'], $context['ftp_details']['password']);
			if (!empty($ftp->error))
				$context['ftp_details']['error'][] = $ftp->error;
			elseif (!empty($context['ftp_details']['path']) && $context['ftp_details']['path'] != '/')
			{
				// No error so far, let's validate the path now.
				$paths = explode(DIRECTORY_SEPARATOR, $context['ftp_details']['path']);
				while (!empty($paths))
				{
					$lpath = '/' . ltrim(implode('/', $paths), '/');
					if ($ftp->chdir($lpath))
					{
						// We matched the entire path we have. That seems promising.
						$dir = $ftp->raw_list();
						if (!$dir)
						{
							$context['ftp_details']['error'][] = 'wrong_folder';
							break;
						}
						// So we have a folder and it has some files in. Does it, perhaps, have an index.php file?
						else
						{
							$dir = preg_split('~\s+~', $dir);
							if (in_array('index.php', $dir))
							{
								$data = $ftp->get('index.php');
								if ($data)
								{
									if (strpos($data, 'Plugins folder. Please leave me be.') !== false)
									{
										$context['ftp_details']['found'] = true;
										$context['ftp_details']['path'] = $lpath;
										break;
									}
								}
							}
							else
								array_shift($paths);
						}
					}
					else
						array_shift($paths); // OK, so we didn't match, lop off another folder and try again.
				}
			}
			$ftp->close();
		}
	}

	if (!empty($context['ftp_details']['found']))
	{
		// We remember some things for next time - but not the password, of course.
		if (!empty($_POST['savedetails']))
		{
			$ftp_settings = !empty($settings['ftp_settings']) ? @unserialize($settings['ftp_settings']) : array();
			foreach (array('server', 'user', 'port', 'type', 'path') as $item)
				$ftp_settings[$item] = $context['ftp_details'][$item];
			updateSettings(array('ftp_settings' => serialize($ftp_settings)));
		}

		// Now we need to save the details in the session for later. Just get clean first.
		unset($context['ftp_details']['found']);
		$context['ftp_details']['password'] = obfuscate_pass($context['ftp_details']['password']);
		$_SESSION['plugin_ftp'] = $context['ftp_details'];

		$context['page_title'] = $txt['plugin_connection_successful_title'];
		$context['form_url'] = '<URL>?action=admin;area=plugins;sa=add;upload;stage=' . (!empty($_SESSION['uploadplugin']['delete']) ? 2 : 3);
		$context['description'] = $txt['plugin_connection_successful'];
		wetem::load('upload_generic_progress');
	}
	else
	{
		// Bah, one way or another, we didn't find it.
		$context['page_title'] = $txt['plugin_connection_details_title'];
		$context['callback_url'] = '<URL>?action=admin;area=plugins;sa=add;upload;stage=1';
		$context['general_description'] = $txt['plugin_connection_details'];
		wetem::load('upload_connection_details');
		return;
	}
}

function uploadedPluginPrune()
{
	global $context, $txt;

	// So, this is primarily to deal with plugins that need dealing with.
	if (empty($_SESSION['uploadplugin']['delete']) || empty($_SESSION['uploadplugin']['id']) || empty($context['plugins_dir'][$_SESSION['uploadplugin']['id']]))
		redirectexit('action=admin;area=plugins;sa=add;upload;stage=3;' . $context['session_query']);

	checkSession('request');

	// So we know the plugin exists and is currently active. If it's not currently active, the user can deal with it on the main screen.
	$manifest = safe_sxml_load($context['plugins_dir'][$_SESSION['uploadplugin']['id']] . '/plugin-info.xml');
	$manifest_id = (string) $manifest['id'];

	// Just like regular disabling, check that it's not using anything existing.
	$test = test_hooks_conflict($manifest);
	if (!empty($test))
	{
		clean_up_plugin_session();
		$list = '<ul><li>' . implode('</li><li>', $test) . '</li></ul>';
		fatal_lang_error('fatal_conflicted_plugins', false, array($list));
	}

	$state = validate_plugin_session();
	if (!empty($state))
	{
		clean_up_plugin_session();
		fatal_lang_error($state, false);
	}

	$path = explode(DIRECTORY_SEPARATOR, realpath($context['plugins_dir'][$_SESSION['uploadplugin']['id']]));
	$plugin = array_pop($path);
	DisablePlugin($manifest, $plugin);

	// So at this stage, we know we can go ahead and delete everything. We need to start with the list of folders.
	$path = $context['plugins_dir'][$_SESSION['uploadplugin']['id']];
	$dirs = array();
	$files = RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
	$repl = array($path => '', DIRECTORY_SEPARATOR => '/');
	foreach ($files as $name => $f)
		if (is_dir($name))
			$dirs[] = strtr($name, $repl);
	unset($files);
	$dirs[] = '/';
	$dirs = array_reverse($dirs);

	// Now we have a list of paths, from plugindir, to systematically enter and play Cyberman on.
	if ($_SESSION['plugin_ftp']['type'] == 'ftp')
	{
		loadSource('Class-FTP');
		$ftp = new ftp_connection($_SESSION['plugin_ftp']['server'], $_SESSION['plugin_ftp']['port'], $_SESSION['plugin_ftp']['user'], obfuscate_pass($_SESSION['plugin_ftp']['password']));
		if ($ftp->error)
		{
			$ftp->close();
			clean_up_plugin_session();
			fatal_lang_error('plugin_ftp_error_' . $ftp->error);
		}

		$path = rtrim($_SESSION['plugin_ftp']['path'], '/');
		foreach ($dirs as $dir)
		{
			$this_path = $path . $dir;
			$ftp->chdir($this_path);
			$data = $ftp->raw_list();
			if ($data)
			{
				$success = true;
				$files = explode("\n", $data);
				foreach ($files as $file)
				{
					$file = trim($file);
					if ($file == '.' || $file == '..')
						continue;
					$success &= $ftp->unlink($file);
				}
				if ($success && $ftp->cdup())
					$success &= $ftp->unlink(substr(strrchr($dir, '/'), 1));

				if (!$success)
				{
					$ftp->close();
					clean_up_plugin_session();
					fatal_lang_error('remove_plugin_files_still_there', false, substr(strrchr($this_path, '/'), 1));
				}
			}
			$ftp->close();
		}
	}

	unset($_SESSION['uploadplugin']['delete']);
	$context['page_title'] = $txt['plugin_files_pruned_title'];
	$context['form_url'] = '<URL>?action=admin;area=plugins;sa=add;upload;stage=3';
	$context['description'] = $txt['plugin_files_pruned'];
	wetem::load('upload_generic_progress');
}

function uploadedPluginFolders()
{
	global $context, $txt, $cachedir, $pluginsdir;

	if (!empty($_SESSION['uploadplugin']['folders']))
		redirectexit('action=admin;area=plugins;sa=add;upload;stage=4;' . $context['session_query']);

	$state = validate_plugin_session();
	if (!empty($state))
	{
		clean_up_plugin_session();
		fatal_lang_error($state, false);
	}

	checkSession('request');

	// Now we get the job of going through and figuring out what folders we need.
	loadSource('Class-ZipExtract');
	$folders = array('' => true); // We want an empty entry, this represents the plugin's root folder
	$file_count = 0;
	try
	{
		$zip = new wextr($cachedir . '/' . $_SESSION['uploadplugin']['file']);
		$list = $zip->list_contents();

		if (!empty($_SESSION['uploadplugin']['trunc']))
			$trunc = '~^' . preg_quote($_SESSION['uploadplugin']['trunc'], '~') . '~i';

		foreach ($list as $i => $file)
		{
			if (!$file['is_folder'])
				$file_count++; // We're already going through the zip, let's count how many actual files there are while we're here

			// We may have learned earlier on that there's a folder inside a folder here... this should fix it.
			if (isset($trunc))
				$file['filename'] = preg_replace($trunc, '', $file['filename']);

			if ($file['is_folder'])
				$folders[$file['filename']] = true;
			elseif (($pos = strpos($file['filename'], '/')) !== false)
				$folders[substr($file['filename'], 0, $pos + 1)] = true;
		}
		ksort($folders);
	}
	catch (Exception $e)
	{
		clean_up_plugin_session();
		fatal_lang_error('plugins_invalid_zip', false);
	}

	// Attempt to come up with a plugin filename: strip the extension and try to parse out daft names
	$filename = preg_replace('~\.zip$~i', '', basename($_SESSION['uploadplugin']['name']));
	if (is_callable('iconv'))
		$filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);

	if (strpos($filename, './') !== false)
		$filename = preg_replace('~\.\.?/~', '', $filename);

	if (empty($filename))
		$filename = 'plugin';

	// Having come up with a hopefully sane name, were there any duplicates?
	if (is_dir($pluginsdir . '/' . $filename))
	{
		$count = 1;
		while (is_dir($pluginsdir . '/' . $filename . '_' . $count))
			$count++;

		$filename .= '_' . $count;
	}
	$_SESSION['uploadplugin']['pfolder'] = $filename;

	// Now we have a list of folders that need uploading, of course we do need to create the master folder too.
	if ($_SESSION['plugin_ftp']['type'] == 'ftp')
	{
		// Now we add the server path so we don't do it in the loop.
		$filename = $_SESSION['plugin_ftp']['path'] . '/' . $filename . '/';
		loadSource('Class-FTP');
		$ftp = new ftp_connection($_SESSION['plugin_ftp']['server'], $_SESSION['plugin_ftp']['port'], $_SESSION['plugin_ftp']['user'], obfuscate_pass($_SESSION['plugin_ftp']['password']));
		if ($ftp->error)
		{
			clean_up_plugin_session();
			fatal_lang_error('plugin_ftp_error_' . $ftp->error, false);
		}

		foreach ($folders as $folder => $state)
		{
			if (!$ftp->create_dir($filename . $folder))
			{
				clean_up_plugin_session();
				fatal_lang_error('plugins_unable_write', false);
			}
		}
	}

	$_SESSION['uploadplugin']['folders'] = true;
	$context['page_title'] = $txt['plugin_folders_created_title'];
	$context['form_url'] = '<URL>?action=admin;area=plugins;sa=add;upload;stage=4';
	$context['description'] = $txt['plugin_folders_created'];
	wetem::load('upload_generic_progress');
}

function uploadedPluginFiles()
{
	global $context, $txt, $cachedir;

	if (isset($_SESSION['uploadplugin']['flist']) && empty($_SESSION['uploadplugin']['flist']))
	{
		clean_up_plugin_session();
		$context['page_title'] = $txt['plugin_files_unpacked_title'];
		$context['form_url'] = '<URL>?action=admin;area=plugins';
		$context['description'] = $txt['plugin_files_unpacked'];
		wetem::load('upload_generic_progress');
	}

	$state = validate_plugin_session();
	if (!empty($state))
	{
		clean_up_plugin_session();
		fatal_lang_error($state, false);
	}

	checkSession('request');

	// Now we get the job of going through and figuring out what folders we need.
	loadSource('Class-ZipExtract');

	// So, we're here. We have something to do. First, figure out if we need our todo list figuring out.
	if (!isset($_SESSION['uploadplugin']['flist']))
	{
		try
		{
			$zip = new wextr($cachedir . '/' . $_SESSION['uploadplugin']['file']);
			$list = $zip->list_contents();

			$files = array();
			// We want all the actual files in order, but not the manifest. We will add that to the end of the list so we do it last.
			foreach ($list as $i => $file)
				if (!$file['is_folder'] && $i != $_SESSION['uploadplugin']['manifest'])
					$files[] = $i;
			$files[] = $_SESSION['uploadplugin']['manifest'];
			$_SESSION['uploadplugin']['flist'] = $files;
			$_SESSION['uploadplugin']['fcount'] = count($files);
		}
		catch (Exception $e)
		{
			clean_up_plugin_session();
			fatal_lang_error('plugins_invalid_zip', false);
		}
	}

	// Right. We know what we're unpacking. We know where it is being unpacked to. Let's do dis fing.
	if ($_SESSION['plugin_ftp']['type'] == 'ftp')
	{
		$base_folder = $_SESSION['plugin_ftp']['path'] . '/' . $_SESSION['uploadplugin']['pfolder'];
		loadSource('Class-FTP');
		$ftp = new ftp_connection($_SESSION['plugin_ftp']['server'], $_SESSION['plugin_ftp']['port'], $_SESSION['plugin_ftp']['user'], obfuscate_pass($_SESSION['plugin_ftp']['password']));
		if ($ftp->error)
		{
			clean_up_plugin_session();
			fatal_lang_error('plugin_ftp_error_' . $ftp->error, false);
		}

		try
		{
			if (!isset($zip))
				$zip = new wextr($cachedir . '/' . $_SESSION['uploadplugin']['file']);

			while (!empty($_SESSION['uploadplugin']['flist']))
			{
				$file_id = array_shift($_SESSION['uploadplugin']['flist']);
				$files = $zip->extractByIndex(array($file_id));
				$file =& $files[$file_id];
				$ftp->put_string($file['content'], $base_folder . '/' . $file['filename']);
			}
			$ftp->close();
			clean_up_plugin_session();
			$context['page_title'] = $txt['plugin_files_unpacked_title'];
			$context['form_url'] = '<URL>?action=admin;area=plugins';
			$context['description'] = $txt['plugin_files_unpacked'];
			wetem::load('upload_generic_progress');
		}
		catch (Exception $e)
		{
			clean_up_plugin_session();
			fatal_lang_error('plugins_invalid_zip', false);
		}
	}
}

/**
 * Attempts to hide the FTP password while it remains in the session.
 *
 * @param string $pass The string to be obfuscated (or the string to be returned)
 * @return string The string obfuscated or unobfuscated; the process is reversible.
*/
function obfuscate_pass($pass)
{
	$n = strlen($pass);

	$salt = session_id();
	while (strlen($salt) < $n)
		$salt .= session_id();

	for ($i = 0; $i < $n; $i++)
		$pass{$i} = chr(ord($pass{$i}) ^ (ord($salt{$i}) - 32));

	return $pass;
}

/**
 * Attempts to validate the plugin file with what was stored in session when the plugin was initially uploaded.
 *
 * @return mixed Boolean false if there were no errors, otherwise the key of the language error string to be displayed, this allows for whichever stage of plugin handling to do its own proper clean-up.
 */
function validate_plugin_session()
{
	global $cachedir;

	if (empty($_SESSION['uploadplugin']) || empty($_SESSION['uploadplugin']['file']))
		return 'plugins_unable_read';

	$filename = $cachedir . '/' . $_SESSION['uploadplugin']['file'];
	if (!file_exists($filename) || filesize($filename) != $_SESSION['uploadplugin']['size'])
		return 'plugins_uploaded_error';

	if (filemtime($filename) != $_SESSION['uploadplugin']['mtime'] || md5_file($filename) !== $_SESSION['uploadplugin']['md5'])
		return 'plugins_uploaded_tampering';

	return false;
}

// Accepts the <min-versions> element and returns an key/value array, key is what isn't met, value is array (available, required)
function testRequiredVersions($manifest_element)
{
	$min_versions = array();
	$check_for = array('php', 'mysql');

	if (!empty($manifest_element))
	{
		$versions = $manifest_element->children();
		foreach ($versions as $version)
			if (in_array($version->getName(), $check_for))
				$min_versions[$version->getName()] = (string) $version;
	}

	$required_versions = array();

	// So, minimum versions? PHP?
	if (!empty($min_versions['php']))
	{
		// Users might insert 5 or 5.3 or 5.3.0. version_compare considers 5.3 to be less than 5.3.0. So we have to normalize it.
		preg_match('~^\d(\.\d){2}~', $min_versions['php'] . '.0.0', $matches);
		if (!empty($matches[0]) && version_compare($matches[0], PHP_VERSION, '>='))
			$required_versions['php'] = array($matches[0], PHP_VERSION);
	}

	// MySQL?
	if (!empty($min_versions['mysql']))
	{
		loadSource('Class-DBPackages');
		$mysql_version = wedbPackages::get_version();

		preg_match('~^\d(\.\d){2}~', $min_versions['mysql'] . '.0.0', $matches);
		if (!empty($matches[0]) && version_compare($matches[0], $mysql_version, '>='))
			$required_versions['mysql'] = array($matches[0], $mysql_version);
	}

	return $required_versions;
}

// Accepts the <required-functions> element and returns an array of functions that aren't available.
function testRequiredFunctions($manifest_element)
{
	$required_functions = array();
	foreach ($manifest_element->{'php-function'} as $function)
	{
		$function = trim((string) $function[0]);
		if (!empty($function))
			$required_functions[$function] = true;
	}
	foreach ($required_functions as $function => $dummy)
		if (is_callable($function))
			unset($required_functions[$function]);

	if (empty($required_functions))
		return array();
	else
		return array_keys($required_functions); // Can't array-flip because we will end up overwriting our values.
}

function get_maint_requirements($manifest)
{
	if (!empty($manifest['maintenance']))
	{
		$opt = (string) $manifest['maintenance'];
		$maint = explode(',', $opt);
		if (!empty($maint))
			return array_intersect($maint, array('enable', 'remove-clean')); // Maybe others later?
	}
	return array();
}

function test_hooks_conflict($manifest)
{
	global $pluginsdir, $settings;

	// This could be interesting, actually. Does this plugin declare any hooks that any other active plugin uses?
	if (!empty($manifest->hooks->provides))
	{
		// OK, so this plugin offers some hooks. We need to see which of these are actually in use by active plugins.
		$hooks_provided = array();
		foreach ($manifest->hooks->provides->hook as $hook)
		{
			$hook_name = (string) $hook;
			if (!empty($hook_name))
				$hooks_provided[$hook_name] = true;
		}

		$conflicted_plugins = array();
		// So now we know what hooks this plugin offers. Now let's see what other plugins use this.
		if (!empty($hooks_provided))
		{
			$plugins = explode(',', $settings['enabled_plugins']);
			foreach ($plugins as $plugin)
			{
				if ($plugin == $_GET['plugin'] || !file_exists($pluginsdir . '/' . $plugin . '/plugin-info.xml'))
					continue;

				// Now, we have to go and get the XML manifest for these plugins, because we have to be able to differentiate
				// optional from required hooks, and we can't do that with what's in context, only the actual manifest.
				$other_manifest = safe_sxml_load($pluginsdir . '/' . $plugin . '/plugin-info.xml');
				$hooks = $other_manifest->hooks->children();
				foreach ($hooks as $hook)
				{
					$type = $hook->getName();
					if ($type != 'provides' && !empty($hook['point']))
					{
						$hook_point = (string) $hook['point'];
						if (isset($hooks_provided[$hook_point]) && (empty($hook['optional']) || (string) $hook['optional'] != 'yes'))
						{
							$conflicted_plugins[$plugin] = (string) $other_manifest->name;
							break;
						}
					}
				}
				unset($other_manifest);
			}
		}
	}

	return !empty($conflicted_plugins) ? $conflicted_plugins : false;
}

function clean_up_plugin_session()
{
	global $cachedir;

	if (!empty($_SESSION['uploadplugin']['file']))
		@unlink($cachedir . '/' . $_SESSION['uploadplugin']['file']);
	unset($_SESSION['uploadplugin'], $_SESSION['plugin_ftp']);
}
