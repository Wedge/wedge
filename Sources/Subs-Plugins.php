<?php
/**
 * Wedge
 *
 * General file handling for the plugin manager.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function getWritableObject()
{
	global $pluginsdir, $context, $modSettings, $boarddir;

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
		if (!empty($modSettings['default_con']))
			$context['connect_details'] = unserialize(base64_decode($modSettings['default_con']));
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

?>