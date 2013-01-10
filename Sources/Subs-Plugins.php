<?php
/**
 * Wedge
 *
 * General file handling for the plugin manager.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function getWritableObject()
{
	global $pluginsdir, $context, $settings, $boarddir;

	// Normally it'll be on the plugins folder, but there's no reason it absolutely to be.
	if (empty($path))
		$path = $pluginsdir;

	// Easy case, it's directly writable.
	if (is_writable($path))
	{
		loadSource('Class-FileWritable');
		return new weFileWritable();
	}

	// Have we already done it lately? If so, gather everything we need from the session.
	if (!empty($_SESSION['pack_ftp']['type']))
	{
		$context['connect_details'] = $_SESSION['pack_ftp'];
	}
	// They weren't specified before we got here. But maybe we can get them because we've already been given them before.
	elseif (!empty($_POST['connect_pwd']))
	{
		// OK, let's start with looking for a stored connection.
		if (!empty($settings['default_con']))
			$context['connect_details'] = unserialize(base64_decode($settings['default_con']));
		else
			$context['connect_details'] = array();

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

		// FTP often features 'virtualised' paths of sorts, relative to the user's home directory.
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
			$remote_path = !empty($_SESSION['pack_ftp']['path']) ? strtr($full_path, array($_SESSION['pack_ftp']['path'] => '')) : $full_path;
			if (!is_writable($remote_path . '/' . $entryname))
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
function uploaded_plugin_validate()
{
	global $context, $txt, $cachedir;

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
		$required_functions = testRequiredFunctions($manifest->{'required_functions'});
		if (!empty($required_functions))
			fatal_lang_error('fatal_install_error_reqfunc', false, westr::htmlspecialchars(implode(', ', $required_functions)));
	}

	// !!! Should we test for all the hooks too? That's pretty heavy work and it's not like we don't have a ton to do right now!

	// What we do need to do, though, is check against plugins that we have currently enabled. (Not enabled... they can fix that themselves from the main listing.)
	// And we need to store this and make sure it won't be automatically garbage collected.
	$new_file = 'post_plugin_' . we::$id;
	if (!move_uploaded_file($_FILES['plugin']['tmp_name'], $cachedir . '/' . $new_file))
		fatal_lang_error('plugins_invalid_upload', false);

	$_SESSION['uploadplugin'] = array(
		'file' => $new_file,
		'size' => $_FILES['plugin']['size'],
		'name' => $_FILES['plugin']['name'],
		'mtime' => filemtime($cachedir . '/' . $new_file),
		'md5' => md5_file($cachedir . '/' . $new_file),
	);

	$id = (string) $manifest['id'];
	if (isset($context['plugins_dir'][$id]))
	{
		$context['page_title'] = $txt['plugin_duplicate_detected_title'];
		// We want to get the existing plugin's name. We know what plugin to look at.
		//$context['existing_plugin']
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
		wesql::extend('extra');
		$mysql_version = wedbExtra::get_version();

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
