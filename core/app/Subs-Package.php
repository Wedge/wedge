<?php
/**
 * Provides .tar.gz and .zip decompression support, and file structure helpers.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file contains functions for handling files, notably tar.gz and zip.

	array read_tgz_file(string filename, string destination,
			bool single_file = false, bool overwrite = false, array files_to_extract = null)
		- reads a .tar.gz file, filename, in and extracts file(s) from it.
		- essentially just a shortcut for read_tgz_data().

	array read_tgz_data(string data, string destination,
			bool single_file = false, bool overwrite = false, array files_to_extract = null)
		- extracts a file or files from the .tar.gz contained in data.
		- detects if the file is really a .zip file, and if so returns the
		  result of read_zip_data
		- if destination is null, returns a list of files in the archive.
		- if single_file is true, returns the contents of the file specified
		  by destination, if it exists, or false.
		- if single_file is true, destination can start with * and / to
		  signify that the file may come from any directory.
		- destination should not begin with a / if single_file is true.
		- overwrites existing files with newer modification times if and
		  only if overwrite is true.
		- creates the destination directory if it doesn't exist, and is
		  is specified.
		- requires zlib support be built into PHP.
		- returns an array of the files extracted.
		- if files_to_extract is not equal to null only extracts file within this array.

	array read_zip_data(string data, string destination,
			bool single_file = false, bool overwrite = false, array files_to_extract = null)
		- extracts a file or files from the .zip contained in data.
		- if destination is null, returns a list of files in the archive.
		- if single_file is true, returns the contents of the file specified
		  by destination, if it exists, or false.
		- if single_file is true, destination can start with * and / to
		  signify that the file may come from any directory.
		- destination should not begin with a / if single_file is true.
		- overwrites existing files with newer modification times if and
		  only if overwrite is true.
		- creates the destination directory if it doesn't exist, and is
		  is specified.
		- requires zlib support be built into PHP.
		- returns an array of the files extracted.
		- if files_to_extract is not equal to null only extracts file within this array.

	bool mktree(string path, int mode)
		- creates the specified tree structure with the mode specified.
		- creates every directory in path until it finds one that already
		  exists.
		- returns true if successful, false otherwise.

	int file_put_contents_vary(string filename, string data)
		- writes data to a file, almost exactly like the file_put_contents() function.
		- uses text mode for text mode file extensions.
		- returns the number of bytes written.

	void try_chmod(string filename, string perm_state)
		// !!!
*/

// Get the data from the file and extract it.
function read_tgz_file($gzfilename, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	if (substr($gzfilename, 0, 7) == 'http://')
	{
		loadSource('Class-WebGet');
		$weget = new weget($gzfilename);
		$data = $weget->get();

		if ($data === false)
			return false;
	}
	else
	{
		$data = @file_get_contents($gzfilename);

		if ($data === false)
			return false;
	}

	return read_tgz_data($data, $destination, $single_file, $overwrite, $files_to_extract);
}

// Extract tar.gz data. If destination is null, return a listing.
function read_tgz_data($data, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	umask(0);
	if (!$single_file && $destination !== null && !file_exists($destination))
		mktree($destination, 0777);

	// No signature?
	if (strlen($data) < 2)
		return false;

	$id = unpack('H2a/H2b', substr($data, 0, 2));
	if (strtolower($id['a'] . $id['b']) != '1f8b')
	{
		// Okay, this ain't no tar.gz, but maybe it's a zip file.
		if (substr($data, 0, 2) == 'PK')
			return read_zip_data($data, $destination, $single_file, $overwrite, $files_to_extract);
		else
			return false;
	}

	$flags = unpack('Ct/Cf', substr($data, 2, 2));

	// Not deflate!
	if ($flags['t'] != 8)
		return false;
	$flags = $flags['f'];

	$offset = 10;
	$octdec = array('mode', 'uid', 'gid', 'size', 'mtime', 'checksum', 'type');

	// "Read" the filename and comment. // !!! Might be mussed.
	if ($flags & 12)
	{
		while ($flags & 8 && $data{$offset++} != "\0")
			continue;
		while ($flags & 4 && $data{$offset++} != "\0")
			continue;
	}

	$crc = unpack('Vcrc32/Visize', substr($data, strlen($data) - 8, 8));
	$data = @gzinflate(substr($data, $offset, strlen($data) - 8 - $offset));

	// wedge_crc32 and crc32 may not return the same results, so we accept either.
	if ($crc['crc32'] != wedge_crc32($data) && $crc['crc32'] != crc32($data))
		return false;

	$blocks = strlen($data) / 512 - 1;
	$offset = 0;

	$return = array();

	while ($offset < $blocks)
	{
		$header = substr($data, $offset << 9, 512);
		$current = unpack('a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1type/a100linkname/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155path', $header);

		// Blank record? This is probably at the end of the file.
		if (empty($current['filename']))
		{
			$offset += 512;
			continue;
		}

		if ($current['type'] == 5 && substr($current['filename'], -1) != '/')
			$current['filename'] .= '/';

		foreach ($current as $k => $v)
		{
			if (in_array($k, $octdec))
				$current[$k] = octdec(trim($v));
			else
				$current[$k] = trim($v);
		}

		$checksum = 256;
		for ($i = 0; $i < 148; $i++)
			$checksum += ord($header{$i});
		for ($i = 156; $i < 512; $i++)
			$checksum += ord($header{$i});

		if ($current['checksum'] != $checksum)
			break;

		$size = ceil($current['size'] / 512);
		$current['data'] = substr($data, ++$offset << 9, $current['size']);
		$offset += $size;

		// Not a directory and doesn't exist already...
		if (substr($current['filename'], -1, 1) != '/' && !file_exists($destination . '/' . $current['filename']))
			$write_this = true;
		// File exists... check if it is newer.
		elseif (substr($current['filename'], -1, 1) != '/')
			$write_this = $overwrite || filemtime($destination . '/' . $current['filename']) < $current['mtime'];
		// Folder... create.
		elseif ($destination !== null && !$single_file)
		{
			// Protect from accidental parent directory writing...
			$current['filename'] = strtr($current['filename'], array('../' => '', '/..' => ''));

			if (!file_exists($destination . '/' . $current['filename']))
				mktree($destination . '/' . $current['filename'], 0777);
			$write_this = false;
		}
		else
			$write_this = false;

		if ($write_this && $destination !== null)
		{
			if (strpos($current['filename'], '/') !== false && !$single_file)
				mktree($destination . '/' . dirname($current['filename']), 0777);

			// Is this the file we're looking for?
			if ($single_file && ($destination == $current['filename'] || $destination == '*/' . basename($current['filename'])))
				return $current['data'];
			// If we're looking for another file, keep going.
			elseif ($single_file)
				continue;
			// Looking for restricted files?
			elseif ($files_to_extract !== null && !in_array($current['filename'], $files_to_extract))
				continue;

			file_put_contents_vary($destination . '/' . $current['filename'], $current['data']);
		}

		if (substr($current['filename'], -1, 1) != '/')
			$return[] = array(
				'filename' => $current['filename'],
				'md5' => md5($current['data']),
				'preview' => substr($current['data'], 0, 100),
				'size' => $current['size'],
				'skipped' => false
			);
	}

	return $single_file ? false : $return;
}

// Extract zip data. If destination is null, return a listing.
function read_zip_data($data, $destination, $single_file = false, $overwrite = false, $files_to_extract = null)
{
	umask(0);
	if ($destination !== null && !file_exists($destination) && !$single_file)
		mktree($destination, 0777);

	// Look for the PK header...
	if (substr($data, 0, 2) != 'PK')
		return false;

	// Find the central whosamawhatsit at the end; if there's a comment it's a pain.
	if (substr($data, -22, 4) == 'PK' . chr(5) . chr(6))
		$p = -22;
	else
	{
		// Have to find where the comment begins, ugh.
		for ($p = -22; $p > -strlen($data); $p--)
		{
			if (substr($data, $p, 4) == 'PK' . chr(5) . chr(6))
				break;
		}
	}

	$return = array();

	// Get the basic zip file info.
	$zip_info = unpack('vfiles/Vsize/Voffset', substr($data, $p + 10, 10));

	$p = $zip_info['offset'];
	for ($i = 0; $i < $zip_info['files']; $i++)
	{
		// Make sure this is a file entry...
		if (substr($data, $p, 4) != 'PK' . chr(1) . chr(2))
			return false;

		// Get all the important file information.
		$file_info = unpack('Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len/vcomment_len/vdisk/vinternal/Vexternal/Voffset', substr($data, $p + 16, 30));
		$file_info['filename'] = substr($data, $p + 46, $file_info['filename_len']);

		// Skip all the information we don't care about anyway.
		$p += 46 + $file_info['filename_len'] + $file_info['extra_len'] + $file_info['comment_len'];

		// If this is a file, and it doesn't exist.... happy days!
		if (substr($file_info['filename'], -1, 1) != '/' && !file_exists($destination . '/' . $file_info['filename']))
			$write_this = true;
		// If the file exists, we may not want to overwrite it.
		elseif (substr($file_info['filename'], -1, 1) != '/')
			$write_this = $overwrite;
		// This is a directory, so we're gonna want to create it. (probably...)
		elseif ($destination !== null && !$single_file)
		{
			// Just a little accident prevention, don't mind me.
			$file_info['filename'] = strtr($file_info['filename'], array('../' => '', '/..' => ''));

			if (!file_exists($destination . '/' . $file_info['filename']))
				mktree($destination . '/' . $file_info['filename'], 0777);
			$write_this = false;
		}
		else
			$write_this = false;

		// Check that the data is there and does exist.
		if (substr($data, $file_info['offset'], 4) != 'PK' . chr(3) . chr(4))
			return false;

		// Get the actual compressed data.
		$file_info['data'] = substr($data, $file_info['offset'] + 30 + $file_info['filename_len'], $file_info['compressed_size']);

		// Only inflate it if we need to ;).
		if ($file_info['compressed_size'] != $file_info['size'])
			$file_info['data'] = @gzinflate($file_info['data']);

		// Okay! We can write this file, looks good from here...
		if ($write_this && $destination !== null)
		{
			if (strpos($file_info['filename'], '/') !== false && !$single_file)
				mktree($destination . '/' . dirname($file_info['filename']), 0777);

			// If we're looking for a specific file, and this is it... ka-bam, baby.
			if ($single_file && ($destination == $file_info['filename'] || $destination == '*/' . basename($file_info['filename'])))
				return $file_info['data'];
			// Oh? Another file. Fine. You don't like this file, do you? I know how it is. Yeah... just go away. No, don't apologize. I know this file's just not *good enough* for you.
			elseif ($single_file)
				continue;
			// Don't really want this?
			elseif ($files_to_extract !== null && !in_array($file_info['filename'], $files_to_extract))
				continue;

			file_put_contents_vary($destination . '/' . $file_info['filename'], $file_info['data']);
		}

		if (substr($file_info['filename'], -1, 1) != '/')
			$return[] = array(
				'filename' => $file_info['filename'],
				'md5' => md5($file_info['data']),
				'preview' => substr($file_info['data'], 0, 100),
				'size' => $file_info['size'],
				'skipped' => false
			);
	}

	return $single_file ? false : $return;
}

function mktree($strPath, $mode)
{
	if (is_dir($strPath))
	{
		if (!is_writable($strPath) && $mode !== false)
			@chmod($strPath, $mode);

		$test = @opendir($strPath);
		if ($test)
		{
			closedir($test);
			return is_writable($strPath);
		}
		else
			return false;
	}
	// Is this an invalid path and/or we can't make the directory?
	if ($strPath == dirname($strPath) || !mktree(dirname($strPath), $mode))
		return false;

	if (!is_writable(dirname($strPath)) && $mode !== false)
		@chmod(dirname($strPath), $mode);

	if ($mode === false)
	{
		$test = @opendir(dirname($strPath));
		if ($test)
		{
			closedir($test);
			return true;
		}
		else
			return false;
	}
	else
	{
		@mkdir($strPath, $mode);
		$test = @opendir($strPath);
		if ($test)
		{
			closedir($test);
			return true;
		}
		else
			return false;
	}
}

function file_put_contents_vary($filename, $data)
{
	static $text_filetypes = array('php', 'txt', '.js', 'css', 'tml', 'htm');

	if (!file_exists($filename))
		@touch($filename);

	try_chmod($filename);

	$fp = @fopen($filename, in_array(substr($filename, -3), $text_filetypes) ? 'w' : 'wb');

	// We should show an error message or attempt a rollback, no?
	if (!$fp)
		return false;

	fwrite($fp, $data);
	fclose($fp);

	return strlen($data);
}

// Try to make a file writable. Return true if it worked, false if it didn't.
function try_chmod($filename, $perm_state = 'writable')
{
	if (file_exists($filename) && is_writable($filename) && $perm_state == 'writable')
		return true;

	for ($i = 0; $i < 2; $i++)
	{
		$chmod_file = $filename;

		// Start off with a less aggressive test.
		if ($i == 0)
		{
			// If this file doesn't exist, then we actually want to look at whatever parent directory does.
			$subTraverseLimit = 2;
			while (!file_exists($chmod_file) && $subTraverseLimit)
			{
				$chmod_file = dirname($chmod_file);
				$subTraverseLimit--;
			}

			// Keep track of the writable status here.
			$file_permissions = @fileperms($chmod_file);
		}
		else
		{
			// This looks odd, but it's an attempt to work around PHP suExec.
			if (!file_exists($chmod_file) && $perm_state == 'writable')
			{
				$file_permissions = @fileperms(dirname($chmod_file));

				mktree(dirname($chmod_file), 0755);
				@touch($chmod_file);
				@chmod($chmod_file, 0755);
			}
			else
				$file_permissions = @fileperms($chmod_file);
		}

		// This looks odd, but it's another attempt to work around PHP suExec.
		if ($perm_state != 'writable')
			@chmod($chmod_file, $perm_state == 'execute' ? 0755 : 0644);
		else
		{
			if (!@is_writable($chmod_file))
				@chmod($chmod_file, 0755);
			if (!@is_writable($chmod_file))
				@chmod($chmod_file, 0777);
			if (!@is_writable(dirname($chmod_file)))
				@chmod($chmod_file, 0755);
			if (!@is_writable(dirname($chmod_file)))
				@chmod($chmod_file, 0777);
		}

		// The ultimate writable test.
		if ($perm_state == 'writable')
		{
			$fp = is_dir($chmod_file) ? @opendir($chmod_file) : @fopen($chmod_file, 'rb');
			if (@is_writable($chmod_file) && $fp)
			{
				if (!is_dir($chmod_file))
					fclose($fp);
				else
					closedir($fp);

				return true;
			}
		}
	}

	// Oh dear, we failed if we get here.
	return false;
}

// crc32 doesn't work as expected on 64-bit functions - make our own.
// http://www.php.net/crc32#79567
if (!function_exists('wedge_crc32'))
{
	function wedge_crc32($number)
	{
		$crc = crc32($number);

		if ($crc & 0x80000000)
		{
			$crc ^= 0xffffffff;
			$crc += 1;
			$crc = -$crc;
		}

		return $crc;
	}
}
