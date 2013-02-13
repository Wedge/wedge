<?php
/**
 * Wedge
 *
 * Various subroutines for graphics handling, such as downloading remote avatars and creating thumbnails.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

/* TrueType fonts supplied by www.LarabieFonts.com */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This whole file deals almost exclusively with handling avatars,
	specifically uploaded ones.  It uses, for gifs at least, Gif Util... for
	more information on that, please see its website, shown above.  The other
	functions are as follows:

	bool downloadAvatar(string url, int id_member, int max_width,
			int max_height)
		- downloads file from url and stores it locally for avatar use
		  by id_member.
		- supports GIF, JPG, PNG, BMP and WBMP formats.
		- uses resizeImageFile() to resize to max_width by max_height,
		  and saves the result to a file.
		- updates the database info for the member's avatar.
		- returns whether the download and resize was successful.

	bool createThumbnail(string source, int max_width, int max_height)
		- create a thumbnail of the given source.
		- uses the resizeImageFile function to achieve the resize.
		- returns whether the thumbnail creation was successful.

	bool reencodeImage(string fileName, int preferred_format = 0)
		- creates a copy of the file at the same location as fileName.
		- the file would have the format preferred_format if possible,
		  otherwise the default format is jpeg.
		- makes sure that all non-essential image contents are disposed.
		- returns true on success, false on failure.

	bool checkImageContents(string fileName, bool extensiveCheck = false)
		- searches through the file to see if there's non-binary content.
		- if extensiveCheck is true, searches for asp/php short tags as well.
		- returns true on success, false on failure.

	void resizeImageFile(string source, string destination,
			int max_width, int max_height, int preferred_format = 0)
		- resizes an image from a remote location or a local file.
		- puts the resized image at the destination location.
		- the file would have the format preferred_format if possible,
		  otherwise the default format is jpeg.
		- returns whether it succeeded.

	void resizeImage(resource src_img, string destination_filename,
			int src_width, int src_height, int max_width, int max_height,
			int preferred_format)
		- resizes src_img proportionally to fit within max_width and
		  max_height limits if it is too large.
		- saves the new image to destination_filename.
		- saves as preferred_format if possible, default is jpeg.

	bool imagecreatefrombmp(string filename)
		- is set only if it doesn't already exist (for forwards compatiblity.)
		- only supports uncompressed bitmaps.
		- returns an image identifier representing the bitmap image obtained
		  from the given filename.

*/

function downloadAvatar($url, $memID, $max_width, $max_height)
{
	global $settings;

	$ext = !empty($settings['avatar_download_png']) ? 'png' : 'jpeg';
	$destName = 'avatar_' . $memID . '_' . time() . '.' . $ext;

	// Just making sure there is a non-zero member.
	if (empty($memID))
		return false;

	loadSource('ManageAttachments');
	removeAttachments(array('id_member' => $memID));

	$id_folder = !empty($settings['currentAttachmentUploadDir']) ? $settings['currentAttachmentUploadDir'] : 1;
	$avatar_hash = empty($settings['custom_avatar_enabled']) ? getAttachmentFilename($destName, false, null, true) : '';
	wesql::insert('',
		'{db_prefix}attachments',
		array(
			'id_member' => 'int', 'attachment_type' => 'int', 'filename' => 'string-255', 'file_hash' => 'string-255', 'fileext' => 'string-8', 'size' => 'int',
			'id_folder' => 'int',
		),
		array(
			$memID, empty($settings['custom_avatar_enabled']) ? 0 : 1, $destName, $avatar_hash, $ext, 1,
			$id_folder,
		),
		array('id_attach')
	);
	$attachID = wesql::insert_id();
	// Retain this globally in case the script wants it.
	$settings['new_avatar_data'] = array(
		'id' => $attachID,
		'filename' => $destName,
		'type' => empty($settings['custom_avatar_enabled']) ? 0 : 1,
	);

	$destName = (empty($settings['custom_avatar_enabled']) ? (is_array($settings['attachmentUploadDir']) ? $settings['attachmentUploadDir'][$settings['currentAttachmentUploadDir']] : $settings['attachmentUploadDir']) : $settings['custom_avatar_dir']) . '/' . $destName . '.tmp';

	// Resize it.
	if (!empty($settings['avatar_download_png']))
		$success = resizeImageFile($url, $destName, $max_width, $max_height, 3);
	else
		$success = resizeImageFile($url, $destName, $max_width, $max_height);

	// Remove the .tmp extension.
	$destName = substr($destName, 0, -4);

	if ($success)
	{
		// Walk the right path.
		if (!empty($settings['currentAttachmentUploadDir']))
		{
			if (!is_array($settings['attachmentUploadDir']))
				$settings['attachmentUploadDir'] = unserialize($settings['attachmentUploadDir']);
			$path = $settings['attachmentUploadDir'][$settings['currentAttachmentUploadDir']];
		}
		else
			$path = $settings['attachmentUploadDir'];

		// Remove the .tmp extension from the attachment.
		if (rename($destName . '.tmp', empty($avatar_hash) ? $destName : $path . '/' . $attachID . '_' . $avatar_hash . '.ext'))
		{
			$destName = empty($avatar_hash) ? $destName : $path . '/' . $attachID . '_' . $avatar_hash . '.ext';
			list ($width, $height) = getimagesize($destName);
			$mime_type = 'image/' . $ext;

			// Write filesize in the database.
			wesql::query('
				UPDATE {db_prefix}attachments
				SET size = {int:filesize}, width = {int:width}, height = {int:height},
					mime_type = {string:mime_type}
				WHERE id_attach = {int:current_attachment}',
				array(
					'filesize' => filesize($destName),
					'width' => (int) $width,
					'height' => (int) $height,
					'current_attachment' => $attachID,
					'mime_type' => $mime_type,
				)
			);
			return true;
		}
		else
			return false;
	}
	else
	{
		wesql::query('
			DELETE FROM {db_prefix}attachments
			WHERE id_attach = {int:current_attachment}',
			array(
				'current_attachment' => $attachID,
			)
		);

		@unlink($destName . '.tmp');
		return false;
	}
}

function createThumbnail($source, $max_width, $max_height)
{
	global $settings;

	$destName = $source . '_thumb.tmp';

	// Do the actual resize.
	if (!empty($settings['attachment_thumb_png']))
		$success = resizeImageFile($source, $destName, $max_width, $max_height, 3);
	else
		$success = resizeImageFile($source, $destName, $max_width, $max_height);

	// Okay, we're done with the temporary stuff.
	$destName = substr($destName, 0, -4);

	if ($success && @rename($destName . '.tmp', $destName))
		return true;
	else
	{
		@unlink($destName . '.tmp');
		@touch($destName);
		return false;
	}
}

function reencodeImage($fileName, $preferred_format = 0)
{
	if (!resizeImageFile($fileName, $fileName . '.tmp', null, null, $preferred_format))
	{
		if (file_exists($fileName . '.tmp'))
			unlink($fileName . '.tmp');

		return false;
	}

	if (!unlink($fileName))
		return false;

	if (!rename($fileName . '.tmp', $fileName))
		return false;

	return true;
}

function checkImageContents($fileName, $extensiveCheck = false)
{
	$fp = fopen($fileName, 'rb');
	if (!$fp)
		fatal_lang_error('attach_timeout');

	$prev_chunk = '';
	while (!feof($fp))
	{
		$cur_chunk = fread($fp, 8192);

		// Though not exhaustive lists, better safe than sorry.
		if (!empty($extensiveCheck))
		{
			// Paranoid check. Some like it that way.
			if (preg_match('~(iframe|\\<\\?|\\<%|html|eval|body|script\W|[CF]WS[\x01-\x0C])~i', $prev_chunk . $cur_chunk) === 1)
			{
				fclose($fp);
				return false;
			}
		}
		else
		{
			// Check for potential infection
			if (preg_match('~(iframe|html|eval|body|script\W|[CF]WS[\x01-\x0C])~i', $prev_chunk . $cur_chunk) === 1)
			{
				fclose($fp);
				return false;
			}
		}
		$prev_chunk = $cur_chunk;
	}
	fclose($fp);

	return true;
}

function resizeImageFile($source, $destination, $max_width, $max_height, $preferred_format = 0)
{
	static $default_formats = array(
		'1' => 'gif',
		'2' => 'jpeg',
		'3' => 'png',
		'6' => 'bmp',
		'15' => 'wbmp'
	);

	loadSource('Class-WebGet');
	ini_set('memory_limit', '90M');

	$success = false;

	// Get the image file, we have to work with something after all
	$fp_destination = fopen($destination, 'wb');
	if ($fp_destination && substr($source, 0, 7) == 'http://')
	{
		$weget = new weget($source);
		$fileContents = $weget->get();

		fwrite($fp_destination, $fileContents);
		fclose($fp_destination);

		$sizes = @getimagesize($destination);
	}
	elseif ($fp_destination)
	{
		$sizes = @getimagesize($source);

		$fp_source = fopen($source, 'rb');
		if ($fp_source !== false)
		{
			while (!feof($fp_source))
				fwrite($fp_destination, fread($fp_source, 8192));
			fclose($fp_source);
		}
		else
			$sizes = array(-1, -1, -1);
		fclose($fp_destination);
	}
	// We can't get to the file.
	else
		$sizes = array(-1, -1, -1);

	// A known and supported format?
	if (isset($default_formats[$sizes[2]]) && function_exists('imagecreatefrom' . $default_formats[$sizes[2]]))
	{
		$imagecreatefrom = 'imagecreatefrom' . $default_formats[$sizes[2]];
		if ($src_img = @$imagecreatefrom($destination))
		{
			resizeImage($src_img, $destination, imagesx($src_img), imagesy($src_img), $max_width === null ? imagesx($src_img) : $max_width, $max_height === null ? imagesy($src_img) : $max_height, true, $preferred_format);
			$success = true;
		}
	}

	return $success;
}

function resizeImage($src_img, $destName, $src_width, $src_height, $max_width, $max_height, $force_resize = false, $preferred_format = 0)
{
	global $settings;

	$success = false;

	// Determine whether to resize to max width or to max height (depending on the limits.)
	if (!empty($max_width) || !empty($max_height))
	{
		if (!empty($max_width) && (empty($max_height) || $src_height * $max_width / $src_width <= $max_height))
		{
			$dst_width = $max_width;
			$dst_height = floor($src_height * $max_width / $src_width);
		}
		elseif (!empty($max_height))
		{
			$dst_width = floor($src_width * $max_height / $src_height);
			$dst_height = $max_height;
		}

		// Don't bother resizing if it's already smaller...
		if (!empty($dst_width) && !empty($dst_height) && ($dst_width < $src_width || $dst_height < $src_height || $force_resize))
		{
			$dst_img = imagecreatetruecolor($dst_width, $dst_height);

			// Deal nicely with a PNG - because we can.
			if ((!empty($preferred_format)) && ($preferred_format == 3))
			{
				imagealphablending($dst_img, false);
				if (function_exists('imagesavealpha'))
					imagesavealpha($dst_img, true);
			}

			imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $dst_width, $dst_height, $src_width, $src_height);
		}
		else
			$dst_img = $src_img;
	}
	else
		$dst_img = $src_img;

	// Save the image as ...
	if (!empty($preferred_format) && ($preferred_format == 3) && function_exists('imagepng'))
		$success = imagepng($dst_img, $destName);
	elseif (!empty($preferred_format) && ($preferred_format == 1) && function_exists('imagegif'))
		$success = imagegif($dst_img, $destName);
	elseif (function_exists('imagejpeg'))
		$success = imagejpeg($dst_img, $destName);

	// Free the memory.
	imagedestroy($src_img);
	if ($dst_img != $src_img)
		imagedestroy($dst_img);

	return $success;
}

if (!function_exists('imagecreatefrombmp'))
{
	function imagecreatefrombmp($filename)
	{
		$fp = fopen($filename, 'rb');

		$errors = error_reporting(0);

		$header = unpack('vtype/Vsize/Vreserved/Voffset', fread($fp, 14));
		$info = unpack('Vsize/Vwidth/Vheight/vplanes/vbits/Vcompression/Vimagesize/Vxres/Vyres/Vncolor/Vcolorimportant', fread($fp, 40));

		if ($header['type'] != 0x4D42)
			false;

		$dst_img = imagecreatetruecolor($info['width'], $info['height']);

		$palette_size = $header['offset'] - 54;
		$info['ncolor'] = $palette_size / 4;

		$palette = array();

		$palettedata = fread($fp, $palette_size);
		$n = 0;
		for ($j = 0; $j < $palette_size; $j++)
		{
			$b = ord($palettedata{$j++});
			$g = ord($palettedata{$j++});
			$r = ord($palettedata{$j++});

			$palette[$n++] = imagecolorallocate($dst_img, $r, $g, $b);
		}

		$scan_line_size = ($info['bits'] * $info['width'] + 7) >> 3;
		$scan_line_align = $scan_line_size & 3 ? 4 - ($scan_line_size & 3) : 0;

		for ($y = 0, $l = $info['height'] - 1; $y < $info['height']; $y++, $l--)
		{
			fseek($fp, $header['offset'] + ($scan_line_size + $scan_line_align) * $l);
			$scan_line = fread($fp, $scan_line_size);

			if (strlen($scan_line) < $scan_line_size)
				continue;

			if ($info['bits'] == 32)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$b = ord($scan_line{$j++});
					$g = ord($scan_line{$j++});
					$r = ord($scan_line{$j++});
					$j++;

					$color = imagecolorexact($dst_img, $r, $g, $b);
					if ($color == -1)
					{
						$color = imagecolorallocate($dst_img, $r, $g, $b);

						// Gah!  Out of colors?  Stupid GD 1... try anyhow.
						if ($color == -1)
							$color = imagecolorclosest($dst_img, $r, $g, $b);
					}

					imagesetpixel($dst_img, $x, $y, $color);
				}
			}
			elseif ($info['bits'] == 24)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$b = ord($scan_line{$j++});
					$g = ord($scan_line{$j++});
					$r = ord($scan_line{$j++});

					$color = imagecolorexact($dst_img, $r, $g, $b);
					if ($color == -1)
					{
						$color = imagecolorallocate($dst_img, $r, $g, $b);

						// Gah!  Out of colors?  Stupid GD 1... try anyhow.
						if ($color == -1)
							$color = imagecolorclosest($dst_img, $r, $g, $b);
					}

					imagesetpixel($dst_img, $x, $y, $color);
				}
			}
			elseif ($info['bits'] == 16)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$b1 = ord($scan_line{$j++});
					$b2 = ord($scan_line{$j++});

					$word = $b2 * 256 + $b1;

					$b = (($word & 31) * 255) / 31;
					$g = ((($word >> 5) & 31) * 255) / 31;
					$r = ((($word >> 10) & 31) * 255) / 31;

					// Scale the image colors up properly.
					$color = imagecolorexact($dst_img, $r, $g, $b);
					if ($color == -1)
					{
						$color = imagecolorallocate($dst_img, $r, $g, $b);

						// Gah!  Out of colors?  Stupid GD 1... try anyhow.
						if ($color == -1)
							$color = imagecolorclosest($dst_img, $r, $g, $b);
					}

					imagesetpixel($dst_img, $x, $y, $color);
				}
			}
			elseif ($info['bits'] == 8)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
					imagesetpixel($dst_img, $x, $y, $palette[ord($scan_line{$j++})]);
			}
			elseif ($info['bits'] == 4)
			{
				$x = 0;
				for ($j = 0; $j < $scan_line_size; $x++)
				{
					$byte = ord($scan_line{$j++});

					imagesetpixel($dst_img, $x, $y, $palette[(int) ($byte / 16)]);
					if (++$x < $info['width'])
						imagesetpixel($dst_img, $x, $y, $palette[$byte & 15]);
				}
			}
			else
			{
				// Sorry, I'm just not going to do monochrome :P.
			}
		}

		fclose($fp);

		error_reporting($errors);

		return $dst_img;
	}
}
