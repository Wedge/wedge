<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
/////////////////////////////////////////////////////////////////
//                                                             //
// Please see readme.txt for more information                  //
//                                                            ///
/////////////////////////////////////////////////////////////////

define('GETID3_VERSION', '1.7.9-20090308');
define('GETID3_FREAD_BUFFER_SIZE', 16384);



class getID3
{
	var $encoding        = 'ISO-8859-1';
	var $encoding_id3v1  = 'ISO-8859-1';
	var $tempdir         = '*';
	var $option_tag_id3v1         = true;
	var $option_tag_id3v2         = true;
	var $option_tag_lyrics3	= false;
	var $option_tag_apetag		= false;
	var $option_tags_process      = true;
	var $option_tags_html         = true;
	var $option_extra_info        = true;
	var $option_md5_data          = false;
	var $option_md5_data_source   = false;
	var $option_sha1_data         = false;
	var $option_max_2gb_check     = true;
	var $filename;

	function getID3()
	{
		$this->startup_error   = '';
		$this->startup_warning = '';

		if (phpversion() < '4.2.0') {
		    $this->startup_error .= 'getID3() requires PHP v4.2.0 or higher - you are running v'.phpversion();
		}

		$memory_limit = ini_get('memory_limit');
		if (preg_match('/([0-9]+)[Mm]/', $memory_limit, $matches)) {
			$memory_limit = $matches[1] * 1048576;
		}
		if ($memory_limit <= 0) {
		} elseif ($memory_limit <= 3145728) {
	    	$this->startup_error .= 'PHP has less than 3MB available memory and will very likely run out. Increase memory_limit in php.ini';
		} elseif ($memory_limit <= 12582912) {
	    	$this->startup_warning .= 'PHP has less than 12MB available memory and might run out if all modules are loaded. Increase memory_limit in php.ini';
		}

		if ((bool) ini_get('safe_mode')) {
		    $this->warning('WARNING: Safe mode is on, shorten support disabled, md5data/sha1data for ogg vorbis disabled, ogg vorbos/flac tag writing disabled.');
		}

		if (!defined('GETID3_OS_ISWINDOWS')) {
			if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
				define('GETID3_OS_ISWINDOWS', true);
			} else {
				define('GETID3_OS_ISWINDOWS', false);
			}
		}

		if (!defined('GETID3_INCLUDEPATH')) {
			foreach (get_included_files() as $key => $val) {
				if (basename($val) == 'getid3.php') {
					define('GETID3_INCLUDEPATH', dirname($val).DIRECTORY_SEPARATOR);
					break;
				}
			}
		}

		if (!include_once(GETID3_INCLUDEPATH.'getid3.lib.php')) {
			$this->startup_error .= 'getid3.lib.php is missing or corrupt';
		}

		if (GETID3_OS_ISWINDOWS && !defined('GETID3_HELPERAPPSDIR')) {

			$helperappsdir = GETID3_INCLUDEPATH.'..'.DIRECTORY_SEPARATOR.'helperapps';

			if (!is_dir($helperappsdir)) {
				$this->startup_error .= '"'.$helperappsdir.'" cannot be defined as GETID3_HELPERAPPSDIR because it does not exist';
			} elseif (strpos(realpath($helperappsdir), ' ') !== false) {
				$DirPieces = explode(DIRECTORY_SEPARATOR, realpath($helperappsdir));
				foreach ($DirPieces as $key => $value) {
					if ((strpos($value, '.') !== false) && (strpos($value, ' ') === false)) {
						if (strpos($value, '.') > 8) {
							$value = substr($value, 0, 6).'~1';
						}
					} elseif ((strpos($value, ' ') !== false) || strlen($value) > 8) {
						$value = substr($value, 0, 6).'~1';
					}
					$DirPieces[$key] = strtoupper($value);
				}
				$this->startup_error .= 'GETID3_HELPERAPPSDIR must not have any spaces in it - use 8dot3 naming convention if neccesary (on this server that would be something like "'.implode(DIRECTORY_SEPARATOR, $DirPieces).'" - NOTE: this may or may not be the actual 8.3 equivalent of "'.$helperappsdir.'", please double-check). You can run "dir /x" from the commandline to see the correct 8.3-style names.';
			}
			define('GETID3_HELPERAPPSDIR', realpath($helperappsdir).DIRECTORY_SEPARATOR);
		}

	}

	function setOption($optArray) {
		if (!is_array($optArray) || empty($optArray)) {
			return false;
		}
		foreach ($optArray as $opt => $val) {
			if (isset($this->$opt) === false) {
				continue;
			}
			$this->$opt = $val;
		}
		return true;
	}

	function analyze($filename) {

		if (!empty($this->startup_error)) {
			return $this->error($this->startup_error);
		}
		if (!empty($this->startup_warning)) {
			$this->warning($this->startup_warning);
		}

		$this->info = array();
		$this->info['GETID3_VERSION'] = GETID3_VERSION;

		if (!function_exists('iconv') && !in_array($this->encoding, array('ISO-8859-1', 'UTF-8', 'UTF-16LE', 'UTF-16BE', 'UTF-16'))) {
			$errormessage = 'iconv() support is needed for encodings other than ISO-8859-1, UTF-8, UTF-16LE, UTF16-BE, UTF-16. ';
			if (GETID3_OS_ISWINDOWS) {
				$errormessage .= 'PHP does not have iconv() support. Please enable php_iconv.dll in php.ini, and copy iconv.dll from c:/php/dlls to c:/windows/system32';
			} else {
				$errormessage .= 'PHP is not compiled with iconv() support. Please recompile with the --with-iconv switch';
			}
	    	return $this->error($errormessage);
		}

		$old_magic_quotes_runtime = get_magic_quotes_runtime();
		if ($old_magic_quotes_runtime) {
			if (version_compare(phpversion(), "5.3") === -1) {
				set_magic_quotes_runtime(0);
				if (get_magic_quotes_runtime()) {
					return $this->error('Could not disable magic_quotes_runtime - getID3() cannot work properly with this setting enabled');
				}
			}
			else {
				return $this->error('Magic quotes are deprecated in PHP 5.3 - getID3() will not operate with PHP 5.3 installed and magic quotes enabled.');
			}
		}

		if (preg_match('/^(ht|f)tp:\/\//', $filename)) {
			return $this->error('Remote files are not supported in this version of getID3() - please copy the file locally first');
		}

		$filename = str_replace('/', DIRECTORY_SEPARATOR, $filename);
		$filename = preg_replace('#'.preg_quote(DIRECTORY_SEPARATOR).'{2,}#', DIRECTORY_SEPARATOR, $filename);

		if (file_exists($filename) && ($fp = @fopen($filename, 'rb'))) {
		} else {
			return $this->error('Could not open file "'.$filename.'"');
		}

		$this->info['filesize'] = filesize($filename);

		if ($this->option_max_2gb_check) {
			fseek($fp, 0, SEEK_END);
			if ((($this->info['filesize'] != 0) && (ftell($fp) == 0)) ||
				($this->info['filesize'] < 0) ||
				(ftell($fp) < 0)) {
					$real_filesize = false;
					if (GETID3_OS_ISWINDOWS) {
						$commandline = 'dir /-C "'.str_replace('/', DIRECTORY_SEPARATOR, $filename).'"';
						$dir_output = `$commandline`;
						if (preg_match('/1 File\(s\)[ ]+([0-9]+) bytes/i', $dir_output, $matches)) {
							$real_filesize = (float) $matches[1];
						}
					} else {
						$commandline = 'ls -o -g -G --time-style=long-iso '.escapeshellarg($filename);
						$dir_output = `$commandline`;
						if (preg_match('/([0-9]+) ([0-9]{4}-[0-9]{2}\-[0-9]{2} [0-9]{2}:[0-9]{2}) /i'.preg_quote($filename).'$', $dir_output, $matches)) {
							$real_filesize = (float) $matches[1];
						}
					}
					if ($real_filesize === false) {
						unset($this->info['filesize']);
						fclose($fp);
						return $this->error('File is most likely larger than 2GB and is not supported by PHP');
					} elseif ($real_filesize < pow(2, 31)) {
						unset($this->info['filesize']);
						fclose($fp);
						return $this->error('PHP seems to think the file is larger than 2GB, but filesystem reports it as '.number_format($real_filesize, 3).'GB, please report to info@getid3.org');
					}
					$this->info['filesize'] = $real_filesize;
					$this->error('File is larger than 2GB (filesystem reports it as '.number_format($real_filesize, 3).'GB) and is not properly supported by PHP.');
			}
		}

		$this->info['avdataoffset']        = 0;
		$this->info['avdataend']           = $this->info['filesize'];
		$this->info['fileformat']          = '';
		$this->info['audio']['dataformat'] = '';
		$this->info['video']['dataformat'] = '';
		$this->info['tags']                = array();
		$this->info['error']               = array();
		$this->info['warning']             = array();
		$this->info['comments']            = array();
		$this->info['encoding']            = $this->encoding;

		$this->info['filename']            = basename($filename);
		$this->info['filepath']            = str_replace('\\', '/', realpath(dirname($filename)));
		$this->info['filenamepath']        = $this->info['filepath'].'/'.$this->info['filename'];

		if ($this->option_tag_id3v2) {

			$GETID3_ERRORARRAY = &$this->info['warning'];
			if (getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.tag.id3v2.php', __FILE__, false)) {
				$tag = new getid3_id3v2($fp, $this->info);
				unset($tag);
			}

		} else {

			fseek($fp, 0, SEEK_SET);
			$header = fread($fp, 10);
			if (substr($header, 0, 3) == 'ID3'  &&  strlen($header) == 10) {
				$this->info['id3v2']['header']           = true;
				$this->info['id3v2']['majorversion']     = ord($header{3});
				$this->info['id3v2']['minorversion']     = ord($header{4});
				$this->info['id3v2']['headerlength']     = getid3_lib::BigEndian2Int(substr($header, 6, 4), 1) + 10;

				$this->info['id3v2']['tag_offset_start'] = 0;
				$this->info['id3v2']['tag_offset_end']   = $this->info['id3v2']['tag_offset_start'] + $this->info['id3v2']['headerlength'];
				$this->info['avdataoffset']              = $this->info['id3v2']['tag_offset_end'];
			}
		}

		if ($this->option_tag_id3v1 && empty($this->info['id3v2'])) {
			if (!@include_once(GETID3_INCLUDEPATH.'module.tag.id3v1.php')) {
				return $this->error('module.tag.id3v1.php is missing - you may disable option_tag_id3v1.');
			}
			$tag = new getid3_id3v1($fp, $this->info);
			unset($tag);
		}

		if ($this->option_tag_apetag) {
			if (!@include_once(GETID3_INCLUDEPATH.'module.tag.apetag.php')) {
				return $this->error('module.tag.apetag.php is missing - you may disable option_tag_apetag.');
			}
			$tag = new getid3_apetag($fp, $this->info);
			unset($tag);
		}

		if ($this->option_tag_lyrics3) {
			if (!@include_once(GETID3_INCLUDEPATH.'module.tag.lyrics3.php')) {
				return $this->error('module.tag.lyrics3.php is missing - you may disable option_tag_lyrics3.');
			}
			$tag = new getid3_lyrics3($fp, $this->info);
			unset($tag);
		}

		fseek($fp, $this->info['avdataoffset'], SEEK_SET);
		$formattest = fread($fp, 32774);

		$determined_format = $this->GetFileFormat($formattest, $filename);

		if (!$determined_format) {
			fclose($fp);
			return $this->error('unable to determine file format');
		}

		if (isset($determined_format['fail_id3']) && (in_array('id3v1', $this->info['tags']) || in_array('id3v2', $this->info['tags']))) {
			if ($determined_format['fail_id3'] === 'ERROR') {
				fclose($fp);
				return $this->error('ID3 tags not allowed on this file type.');
			} elseif ($determined_format['fail_id3'] === 'WARNING') {
				$this->info['warning'][] = 'ID3 tags not allowed on this file type.';
			}
		}

		if (isset($determined_format['fail_ape']) && in_array('ape', $this->info['tags'])) {
			if ($determined_format['fail_ape'] === 'ERROR') {
				fclose($fp);
				return $this->error('APE tags not allowed on this file type.');
			} elseif ($determined_format['fail_ape'] === 'WARNING') {
				$this->info['warning'][] = 'APE tags not allowed on this file type.';
			}
		}

		$this->info['mime_type'] = $determined_format['mime_type'];

		if (!file_exists(GETID3_INCLUDEPATH.$determined_format['include'])) {
			fclose($fp);
			return $this->error('Format not supported, module "'.$determined_format['include'].'" was removed.');
		}

        if (!function_exists('iconv') && @$determined_format['iconv_req']) {
		    return $this->error('iconv support is required for this module ('.$determined_format['include'].').');
		}

		include_once(GETID3_INCLUDEPATH.$determined_format['include']);

		$class_name = 'getid3_'.$determined_format['module'];
		if (!class_exists($class_name)) {
			return $this->error('Format not supported, module "'.$determined_format['include'].'" is corrupt.');
		}
		if (isset($determined_format['option'])) {
			$class = new $class_name($fp, $this->info, $determined_format['option']);
		} else {
			$class = new $class_name($fp, $this->info);
		}
		unset($class);

		fclose($fp);

		if ($this->option_tags_process) {
			$this->HandleAllTags();
		}

		if ($this->option_extra_info) {
			$this->ChannelsBitratePlaytimeCalculations();
			$this->CalculateCompressionRatioVideo();
			$this->CalculateCompressionRatioAudio();
			$this->CalculateReplayGain();
			$this->ProcessAudioStreams();
		}

		if ($this->option_md5_data) {
			if (!$this->option_md5_data_source || empty($this->info['md5_data_source'])) {
				$this->getHashdata('md5');
			}
		}

		if ($this->option_sha1_data) {
			$this->getHashdata('sha1');
		}

		$this->CleanUp();

		if ($old_magic_quotes_runtime) set_magic_quotes_runtime($old_magic_quotes_runtime);

		return $this->info;
	}


	function error($message) {

		$this->CleanUp();

		$this->info['error'][] = $message;
		return $this->info;
	}


	function warning($message) {
		$this->info['warning'][] = $message;
		return true;
	}


	function CleanUp() {

		$AVpossibleEmptyKeys = array('dataformat', 'bits_per_sample', 'encoder_options', 'streams', 'bitrate');
		foreach ($AVpossibleEmptyKeys as $dummy => $key) {
			if (empty($this->info['audio'][$key]) && isset($this->info['audio'][$key])) {
				unset($this->info['audio'][$key]);
			}
			if (empty($this->info['video'][$key]) && isset($this->info['video'][$key])) {
				unset($this->info['video'][$key]);
			}
		}

		if (!empty($this->info)) {
			foreach ($this->info as $key => $value) {
				if (empty($this->info[$key]) && ($this->info[$key] !== 0) && ($this->info[$key] !== '0')) {
					unset($this->info[$key]);
				}
			}
		}

		if (empty($this->info['fileformat'])) {
			if (isset($this->info['avdataoffset'])) {
				unset($this->info['avdataoffset']);
			}
			if (isset($this->info['avdataend'])) {
				unset($this->info['avdataend']);
			}
		}
	}


	function GetFileFormatArray() {
		static $format_info = array();
		if (empty($format_info)) {
			$format_info = array(

				'ac3'  => array(
							'pattern'   => '^\x0B\x77',
							'group'     => 'audio',
							'module'    => 'ac3',
							'mime_type' => 'audio/ac3',
						),

				'adif' => array(
							'pattern'   => '^ADIF',
							'group'     => 'audio',
							'module'    => 'aac',
							'option'    => 'adif',
							'mime_type' => 'application/octet-stream',
							'fail_ape'  => 'WARNING',
						),


				'adts' => array(
							'pattern'   => '^\xFF[\xF0-\xF1\xF8-\xF9]',
							'group'     => 'audio',
							'module'    => 'aac',
							'option'    => 'adts',
							'mime_type' => 'application/octet-stream',
							'fail_ape'  => 'WARNING',
						),


				'au'   => array(
							'pattern'   => '^\.snd',
							'group'     => 'audio',
							'module'    => 'au',
							'mime_type' => 'audio/basic',
						),

				'avr'  => array(
							'pattern'   => '^2BIT',
							'group'     => 'audio',
							'module'    => 'avr',
							'mime_type' => 'application/octet-stream',
						),

				'bonk' => array(
							'pattern'   => '^\x00(BONK|INFO|META| ID3)',
							'group'     => 'audio',
							'module'    => 'bonk',
							'mime_type' => 'audio/xmms-bonk',
						),

				'dss'  => array(
							'pattern'   => '^[\x02]dss',
							'group'     => 'audio',
							'module'    => 'dss',
							'mime_type' => 'application/octet-stream',
						),

				'dts'  => array(
							'pattern'   => '^\x7F\xFE\x80\x01',
							'group'     => 'audio',
							'module'    => 'dts',
							'mime_type' => 'audio/dts',
						),

				'flac' => array(
							'pattern'   => '^fLaC',
							'group'     => 'audio',
							'module'    => 'flac',
							'mime_type' => 'audio/x-flac',
						),

				'la'   => array(
							'pattern'   => '^LA0[2-4]',
							'group'     => 'audio',
							'module'    => 'la',
							'mime_type' => 'application/octet-stream',
						),

				'lpac' => array(
							'pattern'   => '^LPAC',
							'group'     => 'audio',
							'module'    => 'lpac',
							'mime_type' => 'application/octet-stream',
						),

				'midi' => array(
							'pattern'   => '^MThd',
							'group'     => 'audio',
							'module'    => 'midi',
							'mime_type' => 'audio/midi',
						),

				'mac'  => array(
							'pattern'   => '^MAC ',
							'group'     => 'audio',
							'module'    => 'monkey',
							'mime_type' => 'application/octet-stream',
						),

				'it'   => array(
							'pattern'   => '^IMPM',
							'group'     => 'audio',
							'module'    => 'mod',
							'option'    => 'it',
							'mime_type' => 'audio/it',
						),

				'xm'   => array(
							'pattern'   => '^Extended Module',
							'group'     => 'audio',
							'module'    => 'mod',
							'option'    => 'xm',
							'mime_type' => 'audio/xm',
						),

				's3m'  => array(
							'pattern'   => '^.{44}SCRM',
							'group'     => 'audio',
							'module'    => 'mod',
							'option'    => 's3m',
							'mime_type' => 'audio/s3m',
						),

				'mpc'  => array(
							'pattern'   => '^(MPCK|MP\+|[\x00\x01\x10\x11\x40\x41\x50\x51\x80\x81\x90\x91\xC0\xC1\xD0\xD1][\x20-37][\x00\x20\x40\x60\x80\xA0\xC0\xE0])',
							'group'     => 'audio',
							'module'    => 'mpc',
							'mime_type' => 'audio/x-musepack',
						),

				'mp3'  => array(
							'pattern'   => '^\xFF[\xE2-\xE7\xF2-\xF7\xFA-\xFF][\x00-\xEB]',
							'group'     => 'audio',
							'module'    => 'mp3',
							'mime_type' => 'audio/mpeg',
						),

				'ofr'  => array(
							'pattern'   => '^(\*RIFF|OFR)',
							'group'     => 'audio',
							'module'    => 'optimfrog',
							'mime_type' => 'application/octet-stream',
						),

				'rkau' => array(
							'pattern'   => '^RKA',
							'group'     => 'audio',
							'module'    => 'rkau',
							'mime_type' => 'application/octet-stream',
						),

				'shn'  => array(
							'pattern'   => '^ajkg',
							'group'     => 'audio',
							'module'    => 'shorten',
							'mime_type' => 'audio/xmms-shn',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				'tta'  => array(
							'pattern'   => '^TTA',
							'group'     => 'audio',
							'module'    => 'tta',
							'mime_type' => 'application/octet-stream',
						),

				'voc'  => array(
							'pattern'   => '^Creative Voice File',
							'group'     => 'audio',
							'module'    => 'voc',
							'mime_type' => 'audio/voc',
						),

				'vqf'  => array(
							'pattern'   => '^TWIN',
							'group'     => 'audio',
							'module'    => 'vqf',
							'mime_type' => 'application/octet-stream',
						),

				'wv'   => array(
							'pattern'   => '^wvpk',
							'group'     => 'audio',
							'module'    => 'wavpack',
							'mime_type' => 'application/octet-stream',
						),

				'asf'  => array(
							'pattern'   => '^\x30\x26\xB2\x75\x8E\x66\xCF\x11\xA6\xD9\x00\xAA\x00\x62\xCE\x6C',
							'group'     => 'audio-video',
							'module'    => 'asf',
							'mime_type' => 'video/x-ms-asf',
							'iconv_req' => false,
						),

				'bink' => array(
							'pattern'   => '^(BIK|SMK)',
							'group'     => 'audio-video',
							'module'    => 'bink',
							'mime_type' => 'application/octet-stream',
						),

				'flv' => array(
							'pattern'   => '^FLV\x01',
							'group'     => 'audio-video',
							'module'    => 'flv',
							'mime_type' => 'video/x-flv',
						),

				'matroska' => array(
							'pattern'   => '^\x1A\x45\xDF\xA3',
							'group'     => 'audio-video',
							'module'    => 'matroska',
							'mime_type' => 'video/x-matroska',
						),

				'mpeg' => array(
							'pattern'   => '^\x00\x00\x01(\xBA|\xB3)',
							'group'     => 'audio-video',
							'module'    => 'mpeg',
							'mime_type' => 'video/mpeg',
						),

				'nsv'  => array(
							'pattern'   => '^NSV[sf]',
							'group'     => 'audio-video',
							'module'    => 'nsv',
							'mime_type' => 'application/octet-stream',
						),

				'ogg'  => array(
							'pattern'   => '^OggS',
							'group'     => 'audio',
							'module'    => 'ogg',
							'mime_type' => 'application/ogg',
							'fail_id3'  => 'WARNING',
							'fail_ape'  => 'WARNING',
						),

				'quicktime' => array(
							'pattern'   => '^.{4}(cmov|free|ftyp|mdat|moov|pnot|skip|wide)',
							'group'     => 'audio-video',
							'module'    => 'quicktime',
							'mime_type' => 'video/quicktime',
						),

				'riff' => array(
							'pattern'   => '^(RIFF|SDSS|FORM)',
							'group'     => 'audio-video',
							'module'    => 'riff',
							'mime_type' => 'audio/x-wave',
							'fail_ape'  => 'WARNING',
						),

				'real' => array(
							'pattern'   => '^(\\.RMF|\\.ra)',
							'group'     => 'audio-video',
							'module'    => 'real',
							'mime_type' => 'audio/x-realaudio',
						),

				'swf' => array(
							'pattern'   => '^(F|C)WS',
							'group'     => 'audio-video',
							'module'    => 'swf',
							'mime_type' => 'application/x-shockwave-flash',
						),

				'bmp'  => array(
							'pattern'   => '^BM',
							'group'     => 'graphic',
							'module'    => 'bmp',
							'mime_type' => 'image/bmp',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				'gif'  => array(
							'pattern'   => '^GIF',
							'group'     => 'graphic',
							'module'    => 'gif',
							'mime_type' => 'image/gif',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				'jpg'  => array(
							'pattern'   => '^\xFF\xD8\xFF',
							'group'     => 'graphic',
							'module'    => 'jpg',
							'mime_type' => 'image/jpeg',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),

				'pcd'  => array(
							'pattern'   => '^.{2048}PCD_IPI\x00',
							'group'     => 'graphic',
							'module'    => 'pcd',
							'mime_type' => 'image/x-photo-cd',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),


				'png'  => array(
							'pattern'   => '^\x89\x50\x4E\x47\x0D\x0A\x1A\x0A',
							'group'     => 'graphic',
							'module'    => 'png',
							'mime_type' => 'image/png',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),


				'svg'  => array(
							'pattern'   => '<!DOCTYPE svg PUBLIC ',
							'group'     => 'graphic',
							'module'    => 'svg',
							'mime_type' => 'image/svg+xml',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),


				'tiff' => array(
							'pattern'   => '^(II\x2A\x00|MM\x00\x2A)',
							'group'     => 'graphic',
							'module'    => 'tiff',
							'mime_type' => 'image/tiff',
							'fail_id3'  => 'ERROR',
							'fail_ape'  => 'ERROR',
						),


				'iso'  => array(
					'pattern'   => '^.{32769}CD001',
					'group'     => 'misc',
					'module'    => 'iso',
					'mime_type' => 'application/octet-stream',
					'fail_id3'  => 'ERROR',
					'fail_ape'  => 'ERROR',
					'iconv_req' => false,
				),

				'rar'  => array(
					'pattern'   => '^Rar\!',
					'group'     => 'archive',
					'module'    => 'rar',
					'mime_type' => 'application/octet-stream',
					'fail_id3'  => 'ERROR',
					'fail_ape'  => 'ERROR',
				),

				'szip' => array(
					'pattern'   => '^SZ\x0A\x04',
					'group'     => 'archive',
					'module'    => 'szip',
					'mime_type' => 'application/octet-stream',
					'fail_id3'  => 'ERROR',
					'fail_ape'  => 'ERROR',
				),

				'tar'  => array(
					'pattern'   => '^.{100}[0-9\x20]{7}\x00[0-9\x20]{7}\x00[0-9\x20]{7}\x00[0-9\x20\x00]{12}[0-9\x20\x00]{12}',
					'group'     => 'archive',
					'module'    => 'tar',
					'mime_type' => 'application/x-tar',
					'fail_id3'  => 'ERROR',
					'fail_ape'  => 'ERROR',
				),

				'gz'  => array(
					'pattern'   => '^\x1F\x8B\x08',
					'group'     => 'archive',
					'module'    => 'gzip',
					'mime_type' => 'application/x-gzip',
					'fail_id3'  => 'ERROR',
					'fail_ape'  => 'ERROR',
				),

				'zip'  => array(
					'pattern'   => '^PK\x03\x04',
					'group'     => 'archive',
					'module'    => 'zip',
					'mime_type' => 'application/zip',
					'fail_id3'  => 'ERROR',
					'fail_ape'  => 'ERROR',
				),

                'par2' => array (
					'pattern'   => '^PAR2\x00PKT',
					'group'     => 'misc',
					'module'    => 'par2',
					'mime_type' => 'application/octet-stream',
					'fail_id3'  => 'ERROR',
					'fail_ape'  => 'ERROR',
				),

				'pdf'  => array(
					'pattern'   => '^\x25PDF',
					'group'     => 'misc',
					'module'    => 'pdf',
					'mime_type' => 'application/pdf',
					'fail_id3'  => 'ERROR',
					'fail_ape'  => 'ERROR',
				),

				'msoffice' => array(
					'pattern'   => '^\xD0\xCF\x11\xE0',
					'group'     => 'misc',
					'module'    => 'msoffice',
					'mime_type' => 'application/octet-stream',
					'fail_id3'  => 'ERROR',
					'fail_ape'  => 'ERROR',
				),
			);
		}

		return $format_info;
	}



	function GetFileFormat(&$filedata, $filename='') {
		foreach ($this->GetFileFormatArray() as $format_name => $info) {
			if (preg_match('/'.$info['pattern'].'/s', $filedata)) {
				$info['include'] = 'module.'.$info['group'].'.'.$info['module'].'.php';
				return $info;
			}
		}

		if (preg_match('/\.mp[123a]$/i', $filename)) {
			$GetFileFormatArray = $this->GetFileFormatArray();
			$info = $GetFileFormatArray['mp3'];
			$info['include'] = 'module.'.$info['group'].'.'.$info['module'].'.php';
			return $info;
		}

		return false;
	}


	function CharConvert(&$array, $encoding) {
		if ($encoding == $this->encoding) {
			return;
		}
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$this->CharConvert($array[$key], $encoding);
			}
			elseif (is_string($value)) {
				$array[$key] = trim(getid3_lib::iconv_fallback($encoding, $this->encoding, $value));
			}
		}
	}


	function HandleAllTags() {
		static $tags;
		if (empty($tags)) {
			$tags = array(
				'asf'       => array('asf'           , 'UTF-16LE'),
				'midi'      => array('midi'          , 'ISO-8859-1'),
				'nsv'       => array('nsv'           , 'ISO-8859-1'),
				'ogg'       => array('vorbiscomment' , 'UTF-8'),
				'png'       => array('png'           , 'UTF-8'),
				'tiff'      => array('tiff'          , 'ISO-8859-1'),
				'quicktime' => array('quicktime'     , 'ISO-8859-1'),
				'real'      => array('real'          , 'ISO-8859-1'),
				'vqf'       => array('vqf'           , 'ISO-8859-1'),
				'zip'       => array('zip'           , 'ISO-8859-1'),
				'riff'      => array('riff'          , 'ISO-8859-1'),
				'lyrics3'   => array('lyrics3'       , 'ISO-8859-1'),
				'id3v1'     => array('id3v1'         , $this->encoding_id3v1),
				'id3v2'     => array('id3v2'         , 'UTF-8'),
				'ape'       => array('ape'           , 'UTF-8')
			);
		}

		foreach ($tags as $comment_name => $tagname_encoding_array) {
			list($tag_name, $encoding) = $tagname_encoding_array;

			if (isset($this->info[$comment_name]) && !isset($this->info[$comment_name]['encoding'])) {
				$this->info[$comment_name]['encoding'] = $encoding;
			}

			if (!empty($this->info[$comment_name]['comments'])) {

				foreach ($this->info[$comment_name]['comments'] as $tag_key => $valuearray) {
					foreach ($valuearray as $key => $value) {
						if (strlen(trim($value)) > 0) {
							$this->info['tags'][trim($tag_name)][trim($tag_key)][] = $value;
						}
					}
				}

				if (!isset($this->info['tags'][$tag_name])) {
					continue;
				}

				if ($this->option_tags_html) {
					foreach ($this->info['tags'][$tag_name] as $tag_key => $valuearray) {
						foreach ($valuearray as $key => $value) {
							if (is_string($value)) {
								$this->info['tags_html'][$tag_name][$tag_key][$key] = str_replace('&#0;', '', getid3_lib::MultiByteCharString2HTML($value, $encoding));
							} else {
								$this->info['tags_html'][$tag_name][$tag_key][$key] = $value;
							}
						}
					}
				}

				$this->CharConvert($this->info['tags'][$tag_name], $encoding);
			}

		}
		return true;
	}


	function getHashdata($algorithm) {
		switch ($algorithm) {
			case 'md5':
			case 'sha1':
				break;

			default:
				return $this->error('bad algorithm "'.$algorithm.'" in getHashdata()');
				break;
		}

		if ((@$this->info['fileformat'] == 'ogg') && (@$this->info['audio']['dataformat'] == 'vorbis')) {

			if ((bool) ini_get('safe_mode')) {

				$this->info['warning'][] = 'Failed making system call to vorbiscomment.exe - '.$algorithm.'_data is incorrect - error returned: PHP running in Safe Mode (backtick operator not available)';
				$this->info[$algorithm.'_data']  = false;

			} else {

				$old_abort = ignore_user_abort(true);

				$empty = tempnam('*', 'getID3');
				touch($empty);

				$temp = tempnam('*', 'getID3');
				$file = $this->info['filenamepath'];

				if (GETID3_OS_ISWINDOWS) {

					if (file_exists(GETID3_HELPERAPPSDIR.'vorbiscomment.exe')) {

						$commandline = '"'.GETID3_HELPERAPPSDIR.'vorbiscomment.exe" -w -c "'.$empty.'" "'.$file.'" "'.$temp.'"';
						$VorbisCommentError = `$commandline`;

					} else {

						$VorbisCommentError = 'vorbiscomment.exe not found in '.GETID3_HELPERAPPSDIR;

					}

				} else {

					$commandline = 'vorbiscomment -w -c "'.$empty.'" "'.$file.'" "'.$temp.'" 2>&1';
					$commandline = 'vorbiscomment -w -c '.escapeshellarg($empty).' '.escapeshellarg($file).' '.escapeshellarg($temp).' 2>&1';
					$VorbisCommentError = `$commandline`;

				}

				if (!empty($VorbisCommentError)) {

					$this->info['warning'][]         = 'Failed making system call to vorbiscomment(.exe) - '.$algorithm.'_data will be incorrect. If vorbiscomment is unavailable, please download from http://www.vorbis.com/download.psp and put in the getID3() directory. Error returned: '.$VorbisCommentError;
					$this->info[$algorithm.'_data']  = false;

				} else {

					switch ($algorithm) {
						case 'md5':
							$this->info[$algorithm.'_data'] = getid3_lib::md5_file($temp);
							break;

						case 'sha1':
							$this->info[$algorithm.'_data'] = getid3_lib::sha1_file($temp);
							break;
					}
				}

				unlink($empty);
				unlink($temp);

				ignore_user_abort($old_abort);

			}

		} else {

			if (!empty($this->info['avdataoffset']) || (isset($this->info['avdataend']) && ($this->info['avdataend'] < $this->info['filesize']))) {

				$this->info[$algorithm.'_data'] = getid3_lib::hash_data($this->info['filenamepath'], $this->info['avdataoffset'], $this->info['avdataend'], $algorithm);

			} else {

				switch ($algorithm) {
					case 'md5':
						$this->info[$algorithm.'_data'] = getid3_lib::md5_file($this->info['filenamepath']);
						break;

					case 'sha1':
						$this->info[$algorithm.'_data'] = getid3_lib::sha1_file($this->info['filenamepath']);
						break;
				}
			}

		}
		return true;
	}


	function ChannelsBitratePlaytimeCalculations() {

		if (@$this->info['audio']['channels'] == '1') {
			$this->info['audio']['channelmode'] = 'mono';
		} elseif (@$this->info['audio']['channels'] == '2') {
			$this->info['audio']['channelmode'] = 'stereo';
		}

		$CombinedBitrate  = 0;
		$CombinedBitrate += (isset($this->info['audio']['bitrate']) ? $this->info['audio']['bitrate'] : 0);
		$CombinedBitrate += (isset($this->info['video']['bitrate']) ? $this->info['video']['bitrate'] : 0);
		if (($CombinedBitrate > 0) && empty($this->info['bitrate'])) {
			$this->info['bitrate'] = $CombinedBitrate;
		}

		if (isset($this->info['video']['dataformat']) && $this->info['video']['dataformat'] && (!isset($this->info['video']['bitrate']) || ($this->info['video']['bitrate'] == 0))) {
			if (isset($this->info['audio']['bitrate']) && ($this->info['audio']['bitrate'] > 0) && ($this->info['audio']['bitrate'] == $this->info['bitrate'])) {
				if (isset($this->info['playtime_seconds']) && ($this->info['playtime_seconds'] > 0)) {
					if (isset($this->info['avdataend']) && isset($this->info['avdataoffset'])) {
						$this->info['bitrate'] = round((($this->info['avdataend'] - $this->info['avdataoffset']) * 8) / $this->info['playtime_seconds']);
						$this->info['video']['bitrate'] = $this->info['bitrate'] - $this->info['audio']['bitrate'];
					}
				}
			}
		}

		if ((!isset($this->info['playtime_seconds']) || ($this->info['playtime_seconds'] <= 0)) && !empty($this->info['bitrate'])) {
			$this->info['playtime_seconds'] = (($this->info['avdataend'] - $this->info['avdataoffset']) * 8) / $this->info['bitrate'];
		}

		if (!isset($this->info['bitrate']) && !empty($this->info['playtime_seconds'])) {
			$this->info['bitrate'] = (($this->info['avdataend'] - $this->info['avdataoffset']) * 8) / $this->info['playtime_seconds'];
		}
		if (isset($this->info['bitrate']) && empty($this->info['audio']['bitrate']) && empty($this->info['video']['bitrate'])) {
			if (isset($this->info['audio']['dataformat']) && empty($this->info['video']['resolution_x'])) {
				$this->info['audio']['bitrate'] = $this->info['bitrate'];
			} elseif (isset($this->info['video']['resolution_x']) && empty($this->info['audio']['dataformat'])) {
				$this->info['video']['bitrate'] = $this->info['bitrate'];
			}
		}

		if (!empty($this->info['playtime_seconds']) && empty($this->info['playtime_string'])) {
			$this->info['playtime_string'] = getid3_lib::PlaytimeString($this->info['playtime_seconds']);
		}
	}


	function CalculateCompressionRatioVideo() {
		if (empty($this->info['video'])) {
			return false;
		}
		if (empty($this->info['video']['resolution_x']) || empty($this->info['video']['resolution_y'])) {
			return false;
		}
		if (empty($this->info['video']['bits_per_sample'])) {
			return false;
		}

		switch ($this->info['video']['dataformat']) {
			case 'bmp':
			case 'gif':
			case 'jpeg':
			case 'jpg':
			case 'png':
			case 'tiff':
				$FrameRate = 1;
				$PlaytimeSeconds = 1;
				$BitrateCompressed = $this->info['filesize'] * 8;
				break;

			default:
				if (!empty($this->info['video']['frame_rate'])) {
					$FrameRate = $this->info['video']['frame_rate'];
				} else {
					return false;
				}
				if (!empty($this->info['playtime_seconds'])) {
					$PlaytimeSeconds = $this->info['playtime_seconds'];
				} else {
					return false;
				}
				if (!empty($this->info['video']['bitrate'])) {
					$BitrateCompressed = $this->info['video']['bitrate'];
				} else {
					return false;
				}
				break;
		}
		$BitrateUncompressed = $this->info['video']['resolution_x'] * $this->info['video']['resolution_y'] * $this->info['video']['bits_per_sample'] * $FrameRate;

		$this->info['video']['compression_ratio'] = $BitrateCompressed / $BitrateUncompressed;
		return true;
	}


	function CalculateCompressionRatioAudio() {
		if (empty($this->info['audio']['bitrate']) || empty($this->info['audio']['channels']) || empty($this->info['audio']['sample_rate'])) {
			return false;
		}
		$this->info['audio']['compression_ratio'] = $this->info['audio']['bitrate'] / ($this->info['audio']['channels'] * $this->info['audio']['sample_rate'] * (!empty($this->info['audio']['bits_per_sample']) ? $this->info['audio']['bits_per_sample'] : 16));

		if (!empty($this->info['audio']['streams'])) {
			foreach ($this->info['audio']['streams'] as $streamnumber => $streamdata) {
				if (!empty($streamdata['bitrate']) && !empty($streamdata['channels']) && !empty($streamdata['sample_rate'])) {
					$this->info['audio']['streams'][$streamnumber]['compression_ratio'] = $streamdata['bitrate'] / ($streamdata['channels'] * $streamdata['sample_rate'] * (!empty($streamdata['bits_per_sample']) ? $streamdata['bits_per_sample'] : 16));
				}
			}
		}
		return true;
	}


	function CalculateReplayGain() {
		if (isset($this->info['replay_gain'])) {
			$this->info['replay_gain']['reference_volume'] = 89;
			if (isset($this->info['replay_gain']['track']['adjustment'])) {
				$this->info['replay_gain']['track']['volume'] = $this->info['replay_gain']['reference_volume'] - $this->info['replay_gain']['track']['adjustment'];
			}
			if (isset($this->info['replay_gain']['album']['adjustment'])) {
				$this->info['replay_gain']['album']['volume'] = $this->info['replay_gain']['reference_volume'] - $this->info['replay_gain']['album']['adjustment'];
			}

			if (isset($this->info['replay_gain']['track']['peak'])) {
				$this->info['replay_gain']['track']['max_noclip_gain'] = 0 - getid3_lib::RGADamplitude2dB($this->info['replay_gain']['track']['peak']);
			}
			if (isset($this->info['replay_gain']['album']['peak'])) {
				$this->info['replay_gain']['album']['max_noclip_gain'] = 0 - getid3_lib::RGADamplitude2dB($this->info['replay_gain']['album']['peak']);
			}
		}
		return true;
	}

	function ProcessAudioStreams() {
		if (!empty($this->info['audio']['bitrate']) || !empty($this->info['audio']['channels']) || !empty($this->info['audio']['sample_rate'])) {
			if (!isset($this->info['audio']['streams'])) {
				foreach ($this->info['audio'] as $key => $value) {
					if ($key != 'streams') {
						$this->info['audio']['streams'][0][$key] = $value;
					}
				}
			}
		}
		return true;
	}

	function getid3_tempnam() {
		return tempnam($this->tempdir, 'gI3');
	}

}

?>