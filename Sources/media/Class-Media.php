<?php
/**
 * Wedge
 *
 * Media handler object.
 * Uses portions written by Shitiz Garg.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Media handler class
// This is for various image functions, supporting libraries such as GD2, IMageMagick and FFMpeg (when compiled with PHP).
class media_handler
{
	var $image_handler = 0;
	var $video_handler = 0;
	var $src;
	var $imagick = null;
	var $ffmpeg = null;
	var $magick = null;
	var $force_mime = null;
	var $getid3 = null;
	var $getid3_data = null;
	var $thumb_run = 0;

	// Sets up image and video handler
	// Only required if you need to directly handle the item
	function init($src, $image_handler = null, $video_handler = null, $start_handler = true)
	{
		global $amSettings, $context;

		// Source & image handler
		$this->src = $src;
		$this->image_handler = $image_handler != null ? $image_handler : (isset($amSettings['image_handler']) ? $amSettings['image_handler'] : 1);

		if ($this->image_handler == 1)
		{
			// GD2
			$gd2 = $this->testGD2();
			if (!$gd2)
				return $this->init($src, $this->image_handler + 1, $video_handler);
		}
		elseif ($this->image_handler == 2)
		{
			$im = $this->testIMagick();
			if (!$im)
				return $this->init($src, $image_handler + 1, $video_handler);

			if ($this->media_type() == 'image' && $start_handler)
				$this->imagick = new Imagick($this->src);
		}
		elseif ($this->image_handler == 3)
		{
			$im = $this->testMW();
			if (!$im)
				return $this->init($src, $image_handler + 1, $video_handler);

			if ($this->media_type() == 'image' && $start_handler)
			{
				$this->magick = NewMagickWand();
				MagickReadImage($this->magick, $this->src);
			}
		}
		elseif ($this->image_handler == 4)
		{
			$im = $this->testImageMagick();
			if (!$im)
				$this->image_handler = 0;
			elseif (empty($context['im_commands']))
			{
				@exec('convert -help', $ret);
				$context['im_commands'] = array();
				foreach ($ret as $command)
					if (preg_match('/^\s?[+-]([a-z\-]+) /', trim($command), $match))
						$context['im_commands'][$match[1]] = true;
			}
		}

		// Video handler
		$this->video_handler = $video_handler != null ? $video_handler : 1;
		if ($this->video_handler > 0)
		{
			// FFMpeg?
			$ffmpeg = $this->testFFMpeg();
			if (!$ffmpeg)
			{
				$this->video_handler = 2;
				if ($this->media_type() == 'video' || $this->media_type() == 'audio')
				{
					// A Small hack to prevent errors
					if (!defined('GETID3_HELPERAPPSDIR'))
					{
						define('GETID3_HELPERAPPSDIR', 'C:\\Windows\\System32\\');
						loadSource('media/getid3/getid3');
					}
					$this->getid3 = new getID3;
				}
			}
			elseif (($this->media_type() == 'video' || $this->media_type() == 'audio') && $start_handler)
				if (!isset($_REQUEST['thumb']) && !isset($_REQUEST['preview']))
					$this->ffmpeg = new ffmpeg_movie($this->src);
		}
	}

	// We need to perform this only at upload time...
	function securityCheck($filename)
	{
		global $amSettings;

		if (empty($amSettings['upload_security_check']))
			return;
		if (preg_match('~[\x00-\x1F/\\\\]~', $filename))
			die('Malformed filename... /, \\ or control code found! (' . westr::htmlspecialchars($filename) . ')');
		if (function_exists('file_get_contents'))
		{
			$scan_file = @file_get_contents($this->src, FILE_BINARY, null, 0, 256);
			if (preg_match('~<(iframe|\?php|eval|body|head|html|img[^>]+src|plaintext|a[^>]+href|pre|script|table|title)[\s>]~', $scan_file, $hack) === 1)
				die('Hacking attempt... (' . westr::htmlspecialchars($hack[0]) . ')');
			unset($scan_file);
		}
	}

	// Tests GD2
	function testGD2()
	{
		return function_exists('imagecreatetruecolor');
	}

	// Tests IMagick (ImageMagick PHP library)
	function testIMagick()
	{
		return class_exists('Imagick');
	}

	// Tests FF-MPEG
	function testFFMpeg()
	{
		return class_exists('ffmpeg_movie');
	}

	// Tests MagickWand
	function testMW()
	{
		return function_exists('NewMagickWand');
	}

	// Tests ImageMagick (command-line)
	function testImageMagick()
	{
		@exec('convert -version', $test_im);
		return isset($test_im, $test_im[0]) && preg_match('/ImageMagick\s([\d\.]+)/', $test_im[0], $ver) ? $ver[1] : false;
	}

	// Creates a thumbnail
	function createThumbnail($dest, $width, $height, $dontcreatenewhandler = false)
	{
		if (!file_exists($this->src))
			return false;

		$type = $this->media_type();

		$this->thumb_run++;

		// Re-initialise... Just in case...
		if ($this->thumb_run > 1)
		{
			$this->close();
			$this->init($this->src, $this->image_handler, $this->video_handler, true);
		}

		if ($type == 'image')
			$result = $this->createImageThumb($dest, $width, $height);
		elseif ($type == 'video')
			$result = $this->createVideoThumb($dest, $width, $height);
		else
			$result = false;

		if ($result && !$dontcreatenewhandler)
		{
			$return = new media_handler;
			$return->init($dest);
		}
		else
			$return = $result;

		return $return;
	}

	function createJpgThumbnail($source, $max_width, $max_height)
	{
		global $settings;

		loadSource('Subs-Graphics');

		$default_formats = array(
			'1' => 'gif',
			'2' => 'jpeg',
			'3' => 'png',
			'6' => 'bmp',
			'15' => 'wbmp'
		);

		// No GD?  Resizing to nothing?  Time to bail!
		if (empty($max_width) && empty($max_height))
			return false;

		$destName = $source . '_thumb.tmp';

		// Ask for more memory: we need it for this, and it'll only happen once!
		@ini_set('memory_limit', '128M');

		$success = false;
		$sizes = getimagesize($source);

		if (empty($sizes))
			return false;

		// If we have to handle a gif, we might be able to... but maybe not :-/
		if ($sizes[2] == 1 && !function_exists('imagecreatefromgif') && function_exists('imagecreatefrompng'))
		{
			// Try out a temporary file, if possible...
			if ($img = @gif_loadFile($source) && gif_outputAsPng($img, $destName))
				if ($src_img = imagecreatefrompng($destName))
					$success = !$this->resizeJpgImage($src_img, $destName, imagesx($src_img), imagesy($src_img), $max_width, $max_height, $source);
		}
		// Or is it one of the formats supported above?
		elseif (isset($default_formats[$sizes[2]]) && function_exists('imagecreatefrom' . $default_formats[$sizes[2]]))
		{
			$imagecreatefrom = 'imagecreatefrom' . $default_formats[$sizes[2]];
			if ($src_img = @$imagecreatefrom($source))
				$success = !$this->resizeJpgImage($src_img, $destName, imagesx($src_img), imagesy($src_img), $max_width, $max_height, $source);
		}

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

	function setTransparent(&$src, &$dst, $trn, $single)
	{
		if ($single) // single-color transparent PNG?
		{
			$rgb = imagecolorsforindex($src, $trn);
			$trn = imagecolorallocate($dst, $rgb['red'], $rgb['green'], $rgb['blue']);
			imagefill($dst, 0, 0, $trn);
			imagecolortransparent($dst, $trn);
		}
		else
		{
			imagecolortransparent($dst, imagecolorallocate($dst, 0, 0, 0));
			imagealphablending($dst, false);
			imagesavealpha($dst, true);
		}
	}

	function resizeJpgImage($src_img, $destName, $src_width, $src_height, $width, $height, $source)
	{
		global $amSettings;

		// Is it a transparent PNG file?
		$ext = aeva_getExt($this->src);
		$transp = $alpha = $trn = false;
		if (($ext == 'png' || $ext == 'gif') && $source != null)
		{
			$trn = imagecolortransparent($src_img);
			$alpha = $ext == 'png' && (((ord(@file_get_contents($source, false, null, 25, 1)) & 6) & 4) == 4);
			$single = $trn >= 0 && $ext != 'gif';
			$transp = $trn >= 0 || $alpha;
		}

		// Don't bother resizing if it's already smaller...
		if (!empty($width) && !empty($height) && ($width < $src_width || $height < $src_height))
		{
			// (make a true color image, because it just looks better for resizing.)
			$dst_img = imagecreatetruecolor($width, $height);

			if ($transp)
				$this->setTransparent($src_img, $dst_img, $trn, $single);

			// Resize it!
			$resizeFunc = 'imagecopy' . (empty($single) ? 'resampled' : 'resized');
			$resizeFunc($dst_img, $src_img, 0, 0, 0, 0, $width, $height, $src_width, $src_height);
		}
		else
			$dst_img = $src_img;

		$jcomp = isset($amSettings['jpeg_compression']) ? $amSettings['jpeg_compression'] : 80;
		if (!is_numeric($jcomp) || ($jcomp < 0) || ($jcomp > 100))
			$jcomp = 80;

		// Save it! As a reminder, SMF's thumbnail compression value is 65%.
		if ($transp)
		{
			if ($alpha && $dst_img == $src_img)
				imagesavealpha($dst_img, true);
			imagepng($dst_img, $destName);
		}
		else
			imagejpeg($dst_img, $destName, (int) $jcomp);

		// Free the memory.
		imagedestroy($src_img);
		if ($dst_img != $src_img)
			imagedestroy($dst_img);
	}

	// Creates a Image thumbnail
	// Currently Supports GD2, IMagick, MagickWand, ImageMagick command-line
	function createImageThumb($dest, $width, $height)
	{
		global $amSettings, $context;

		if ($this->image_handler == 0)
			return false;

		// Create a thumbnail!
		// GD2?

		// Get suitable sizes
		list ($cur_width, $cur_height) = $this->getSize();
		list ($width, $height) = $this->getAppropriateSizes($cur_width, $cur_height, $width, $height);

		if ($this->image_handler == 1)
		{
			if ($done = $this->createJpgThumbnail($this->src, $width, $height))
				rename(($this->src) . '_thumb', $dest);
			return (bool) $done;
		}
		// OK, maybe IMagick
		elseif ($this->image_handler == 2)
		{
			// Buy some memory
			@ini_set('memory_limit', '48M');

			$this->imagick->resizeImage($width, $height, 1, 1);
			$success = $this->imagick->writeImage($dest);
			return $success;
		}
		// This has to be MagickWand then?
		elseif ($this->image_handler == 3)
		{
			// Buy some memory
			@ini_set('memory_limit', '48M');

			MagickResizeImage($this->magick, $width, $height, MW_LanczosFilter, 1);
			$success = MagickWriteImage($this->magick, $dest);

			return $success;
		}
		// Do we at least have ImageMagick installed?
		elseif ($this->image_handler == 4)
		{
			$type = $width <= $amSettings['max_thumb_width'] && $height <= $amSettings['max_thumb_height'] && isset($context['im_commands']['thumbnail'])
					? 'thumbnail' : (isset($context['im_commands']['strip']) ? 'strip -resize' : 'resize');
			$jcomp = isset($amSettings['jpeg_compression']) ? $amSettings['jpeg_compression'] : 80;
			if (!is_numeric($jcomp) || ($jcomp < 0) || ($jcomp > 100))
				$jcomp = 80;
			$quality = isset($context['im_commands']['quality']) ? ' -quality ' . $jcomp : '';
			$ext = aeva_getExt($this->src);
			$trans = $ext == 'png' || $ext == 'gif' ? (isset($context['im_commands']['alpha']) ? ' -alpha set' : ' -matte') : '';
			// [0] requests the first layer. On animated GIFs, this is important.
			@exec("convert \"{$this->src}[0]\" -$type {$width}x{$height}{$quality}{$trans} \"$dest\" 2>&1", $err, $success);
			if (isset($err[0]))
				log_error("ImageMagick error:\n\n" . $err[0]);

			return $success == 0;
		}
	}

	// Creates a video item thumbnail
	// Currently only supports FFMpeg
	function createVideoThumb($dest, $width, $height)
	{
		global $amSettings;

		// FFMPEG handler not there?
		if ($this->video_handler != 1)
			return false;

		// Try to buy some (a lot of) memory
		@ini_set('memory_limit', '128M');
		if (($frame = @$this->ffmpeg->getFrame(5)) == false)
			return false;

		// Handle width and height of this thing
		$cur_width = $frame->getWidth();
		$cur_height = $frame->getHeight();
		list ($width, $height) = $this->getAppropriateSizes($cur_width, $cur_height, $width, $height);

		// Round them
		$width = round($width);
		$height = round($height);
		if ($width % 2 == 1)
			$width++;
		if ($height % 2 == 1)
			$height++;

		// Create the thumbnail
		$gd_img = $frame->toGDImage();
		imagejpeg($gd_img, $dest);
		return !$this->resizeJpgImage($gd_img, $dest, imagesx($gd_img), imagesy($gd_img), $width, $height, null);
	}

	function getSize()
	{
		$type = $this->media_type();
		if (!file_exists($this->src))
			return false;
		if ($type == 'image')
		{
			if ($this->image_handler == 0)
				return array(0, 0);

			if ($this->image_handler == 1)
				return getimagesize($this->src);
			elseif ($this->image_handler == 2)
			{
				$width = $this->imagick->getImageWidth();
				$height = $this->imagick->getImageHeight();
				return array($width, $height);
			}
			elseif ($this->image_handler == 3)
			{
				$width = MagickGetImageWidth($this->magick);
				$height = MagickGetImageHeight($this->magick);
				return array($width, $height);
			}
			elseif ($this->image_handler == 4)
			{
				// Adding a space after %h to make sure the height isn't
				// mixed with the second frame's width in animated GIFs.
				$run = @exec('identify -format "%w %h " "' . $this->src . '"', $ret, $success);
				return $run && isset($ret, $ret[0]) ? explode(' ', $ret[0]) : array(0, 0);
			}
		}
		elseif ($type == 'video')
		{
			if (empty($this->video_handler))
				return array(0, 0);

			// FFMPEG?
			if ($this->video_handler == 1)
			{
				$width = $this->ffmpeg->getFrameWidth();
				$height = $this->ffmpeg->getFrameHeight();
				return array($width, $height);
			}
			elseif ($this->video_handler == 2)
			{
				if (is_null($this->getid3_data))
					$this->getid3_data = $this->getid3->analyze($this->src);

				// FLV dimensions seem to be more reliable in metadata.
				if (!empty($this->getid3_data['meta']['onMetaData']['width']))
				{
					$width = @$this->getid3_data['meta']['onMetaData']['width'];
					$height = @$this->getid3_data['meta']['onMetaData']['height'];
				}
				else
				{
					$width = @$this->getid3_data['video']['resolution_x'];
					$height = @$this->getid3_data['video']['resolution_y'];
				}

				return array($width, $height);
			}
		}
		else
			return array(0, 0);
	}

	function getAppropriateSizes($src_width, $src_height, $max_width, $max_height)
	{
		// Inspired from Subs-Graphics.php
		// No need if they are already smaller
		if ($src_width < $max_width && $src_height < $max_height)
		{
			$dst_width = $src_width;
			$dst_height = $src_height;
		}
		elseif (!empty($max_width) && (empty($max_height) || round($max_width * $src_height / $src_width) <= $max_height))
		{
			$dst_width = $max_width;
			$dst_height = round($max_width * $src_height / $src_width);
		}
		elseif (!empty($max_height))
		{
			$dst_width = round($max_height * $src_width / $src_height);
			$dst_height = $max_height;
		}
		if (empty($dst_width))
			$dst_width = $src_width;
		if (empty($dst_height))
			$dst_height = $src_height;
		return array($dst_width, $dst_height);
	}

	// Returns whether an item is audio, video, image or an unknown file
	function media_type()
	{
		$mime = $this->getMimeType();
		$mimes = aeva_extList();

		if (in_array($mime, $mimes['image']))
			return 'image';
		elseif (in_array($mime, $mimes['audio']))
			return 'audio';
		elseif (in_array($mime, $mimes['video']))
			return 'video';
		else
			return empty($mime) ? 'doc' : 'unknown';
	}

	// Gets the mime type
	function getMimeType()
	{
		if (!is_null($this->force_mime))
			return $this->force_mime;

		return $this->getMimeFromExt($this->src);
	}

	// Gets metadata from the file
	function getInfo()
	{
		$return = array();
		$media_type = $this->media_type();

		if ($media_type == 'audio' || $media_type == 'video')
		{
			if ($this->video_handler == 0)
				return $return;

			// FFMPEG?
			if ($this->video_handler == 1)
			{
				$file = $this->ffmpeg;
				if (!$file)
					return $return;

				// Get the info
				$return['duration'] = @$file->getDuration();
				$return['frame_count'] = @$file->getFrameCount();
				$return['bit_rate'] = @$file->getBitRate();
				$return['audio_codec'] = @$file->getAudioCodec();
				$return['video_codec'] = @$file->getVideoCodec();
				$return['copyright'] = @$file->getCopyright();
			}
			// GETID3?
			// Currently we don't get much info out of everything that's available... Leaves room for the future.
			else
			{
				if (is_null($this->getid3_data))
					$this->getid3_data = $this->getid3->analyze($this->src);

				$return['duration'] = sprintf('%01.2f', @$this->getid3_data['playtime_seconds']);
				$return['bit_rate'] = @$this->getid3_data[$media_type]['bitrate'];
			}
		}
		elseif ($media_type == 'image')
		{
			// Who cares about PHP's read_exif_data? We have our own!
			loadSource('media/Subs-Exif');

			$data = read_exif_data_raw($this->src,0);

			if (!$data)
				return $return;

			if (isset($data['IFD0']['DateTime']))			$return['datetime']		= $data['IFD0']['DateTime'];
			if (isset($data['COMPUTED']['Copyright']))		$return['copyright']	= $data['COMPUTED']['Copyright'];
			if (isset($data['IFD0']['Make']))				$return['make']			= $data['IFD0']['Make'];
			if (isset($data['IFD0']['Model']))				$return['model']		= $data['IFD0']['Model'];
			if (isset($data['SubIFD']['Flash']))			$return['flash']		= $data['SubIFD']['Flash'];
			if (isset($data['SubIFD']['ExposureTime']))		$return['xposuretime']	= $data['SubIFD']['ExposureTime'];
			if (isset($data['IFD0']['Orientation']))		$return['orientation']	= $data['IFD0']['Orientation'];
			if (isset($data['SubIFD']['FocalLength']))		$return['focal_length']	= $data['SubIFD']['FocalLength'];
			if (isset($data['IFD0']['xResolution']))		$return['xres']			= $data['IFD0']['xResolution'];
			if (isset($data['IFD0']['yResolution']))		$return['yres']			= $data['IFD0']['yResolution'];
			if (isset($data['IFD0']['ResolutionUnit']))		$return['resunit']		= $data['IFD0']['ResolutionUnit'];
			if (isset($data['SubIFD']['FNumber']))			$return['fnumber']		= $data['SubIFD']['FNumber'];
			if (isset($data['SubIFD']['ISOSpeedRatings']))	$return['iso']			= $data['SubIFD']['ISOSpeedRatings'];
			if (isset($data['SubIFD']['MeteringMode']))		$return['meteringMode']	= $data['SubIFD']['MeteringMode'];
			if (isset($data['SubIFD']['DigitalZoom']))		$return['digitalZoom']	= $data['SubIFD']['DigitalZoom'];
			if (isset($data['SubIFD']['Contrast']))			$return['contrast']		= $data['SubIFD']['Contrast'];
			if (isset($data['SubIFD']['Sharpness']))		$return['sharpness']	= $data['SubIFD']['Sharpness'];
			if (isset($data['SubIFD']['FocusType']))		$return['focusType']	= $data['SubIFD']['FocusType'];
			if (isset($data['SubIFD']['ExifVersion']))		$return['exifVersion']	= $data['SubIFD']['ExifVersion'];
		}

		if (isset($return['xres']) && isset($return['resunit']))
		{
			if ($return['resunit'] == 'Inch')
				$return['xres'] = str_replace('dots per ResolutionUnit', 'dpi', $return['xres']);
			else
				$return['xres'] = str_replace('ResolutionUnit', $return['resunit'], $return['xres']);
		}
		if (isset($return['yres']) && isset($return['resunit']))
		{
			if ($return['resunit'] == 'Inch')
				$return['yres'] = str_replace('dots per ResolutionUnit', 'dpi', $return['yres']);
			else
				$return['yres'] = str_replace('ResolutionUnit', $return['resunit'], $return['yres']);
		}

		// Return it now
		return $return;
	}

	function getFileSize()
	{
		if ($this->media_type() == 'image' && ($this->image_handler == 2 || $this->image_handler == 3))
			return $this->image_handler == 2 ? $this->imagick->getImageSize() : MagickGetImageSize($this->magick);
		else
			return filesize($this->src);
	}

	function getStat()
	{
		return stat($this->src);
	}

	// This function returns the mime type for the given filename, although currently only popular formats of images, audio and videos are loaded.
	function getMimeFromExt($filename)
	{
		$mimeTypes = aeva_extList();
		$mimeTypes = array_merge($mimeTypes['image'], $mimeTypes['audio'], $mimeTypes['video'], $mimeTypes['doc']);

		// Let's get the file's extension
		$ext = aeva_getExt($filename);
		return isset($mimeTypes[$ext]) ? (is_numeric($mimeTypes[$ext]) ? '' : $mimeTypes[$ext]) : 'unknown';
	}

	// Closes the initialized stuff
	function close()
	{
		if ($this->imagick != null)
		{
			$this->imagick->destroy();
			$this->imagick = null;
		}
		if ($this->magick != null)
		{
			DestroyMagickWand($this->magick);
			$this->magick = null;
		}
		if ($this->ffmpeg != null)
			$this->ffmpeg = null;
	}
}

// Gets the extension
function aeva_getExt($file)
{
	$filename = strrchr($file, '/') ? substr(strrchr($file, '/'), 1) : $file;

	if (strrchr($filename, '.') == true)
		$ext = substr(strrchr($filename, '.'), 1);
	elseif (strrchr($filename, '_'))
	{
		$ext_part = substr(strrchr($filename, '_'), 1);
		if (substr($ext_part, 0, 3) == 'ext')
			$ext = substr($ext_part, 3);
	}
	return isset($ext) ? strtolower($ext) : false;
}

function aeva_extList()
{
	global $amSettings;

	$image_mime = array(
		// Image files
		'bmp' => 'image/bmp',
		'png' => 'image/png',
		'gif' => 'image/gif',
		'jpg' => 'image/jpeg',
		'jpe' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
	);
	$audio_mime = array(
		// Audio files
		'mp2' => 'audio/mpeg',
		'mp3' => 'audio/mpeg',
		'mpga' => 'audio/mpeg',
		'ram' => 'audio/x-pn-realaudio',
		'ra' => 'audio/x-pn-realaudio',
		'wax' => 'audio/x-ms-wax',
		'wma' => 'audio/x-ms-wma',
		'm4p' => 'audio/mp4a-latm',
		'm4a' => 'audio/mp4a-latm',
		'a-latm' => 'audio/mp4a-latm',
		'ogg' => 'application/ogg',
		'rpm' => 'audio/x-pn-RealAudio-plugin',
		'mka' => 'audio/x-matroska',
		'wav' => 'audio/wav',
	);
	$video_mime = array(
		// Video files
		'3gp' => 'video/3gpp',
		'3g2' => 'video/3gpp',
		'avi' => 'video/x-msvideo',
		'mpg' => 'video/mpeg',
		'mpeg' => 'video/mpeg',
		'mpe' => 'video/mpeg',
		'wmv' => 'video/x-ms-wmv',
		'mxu' => 'video/vnd.mpegurl',
		'm4u' => 'video/vnd.mpegurl',
		'movie' => 'video/x-sgi-movie',
		'qt' => 'video/quicktime',
		'mov' => 'video/quicktime',
		'rm' => 'application/vnd.rn-realmedia',
		'rmvb' => 'application/vnd.rn-realmedia-vbr',
		'rv' => 'video/vnd.rn-realvideo',
		'flv' => 'video/x-flv',
		'f4v' => 'video/mp4',
		'mp4' => 'video/mp4',
		'm4v' => 'video/x-m4v',
		'mkv' => 'video/x-matroska',
		'webm' => 'video/webm',
	);

	// Supported doc files are:
	// Desktop documents (text, PDF, Office...) - txt,rtf,pdf,xls,doc,ppt,docx,xlsx,pptx
	// Internet files - xml,html,htm,php,css,js
	// Archive files - zip,rar,ace,arj,7z,gz,tar,tgz,bz,bzip2,sit

	$doc_mime = !empty($amSettings['my_docs']) ?
		array_diff_key(array_flip(array_map('trim', explode(',', trim($amSettings['my_docs'], ', ')))), $image_mime, $audio_mime, $video_mime) : array();

	return array('image' => $image_mime, 'audio' => $audio_mime, 'video' => $video_mime, 'doc' => $doc_mime);
}

?>