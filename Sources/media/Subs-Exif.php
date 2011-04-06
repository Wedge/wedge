<?php

if (!defined('SMF'))
	die('Hacking attempt...');

/*
	Exifixer 1.7
	Extracts EXIF information from digital photos.

	Originally created by:
	Copyright © 2005 Jake Olefsky
	http://www.offsky.com/software/exif/index.php
	jake@olefsky.com

	This program is free software; you can redistribute it and/or modify it under the terms of 
	the GNU General Public License as published by the Free Software Foundation; either version 2 
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
	without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
	See the GNU General Public License for more details. http:// www.gnu.org/copyleft/gpl.html

	SUMMARY:
				This script will correctly parse all of the EXIF data included in images taken
				with digital cameras.  It will read the IDF0, IDF1, SubIDF and InteroperabilityIFD
				fields as well as parsing some of the MakerNote fields that vary depending on
				camera make and model.  This script parses more tags than the internal PHP exif
				implementation and it will correctly identify and decode what all the values mean.

	TESTED WITH:
				Nikon, Canon, Olympus, FujiFilm, Sony, Kodak, Ricoh, Sanyo and Epson devices

	VERSION HISTORY:

	1.7    April 11th, 2008 [Zenphoto]

		+ Fixed bug with newer Olympus cameras where number of fields was miscalculated leading to bad performance.
		+ More logical fraction calculation for shutter speed.

*/

function intel2Moto($intel) {
	$len  = strlen($intel);
	$moto = '';
	for($i = 0; $i <= $len; $i += 2) {
		$moto .= substr($intel, $len-$i, 2);
	}
	return $moto;
}

function lookup_tag($tag) {
	switch($tag) {
		case '000b': $tag = 'ACDComment'; break;
		case '00fe': $tag = 'ImageType'; break;
		case '0106': $tag = 'PhotometricInterpret'; break;
		case '010e': $tag = 'ImageDescription'; break;
		case '010f': $tag = 'Make'; break;
		case '0110': $tag = 'Model'; break;
		case '0112': $tag = 'Orientation'; break;
		case '0115': $tag = 'SamplePerPixel'; break;
		case '011a': $tag = 'xResolution'; break;
		case '011b': $tag = 'yResolution'; break;
		case '011c': $tag = 'PlanarConfig'; break;
		case '0128': $tag = 'ResolutionUnit'; break;
		case '0131': $tag = 'Software'; break;
		case '0132': $tag = 'DateTime'; break;
		case '013b': $tag = 'Artist'; break;
		case '013c': $tag = 'HostComputer'; break;
		case '013e': $tag = 'WhitePoint'; break;
		case '013f': $tag = 'PrimaryChromaticities'; break;
		case '0211': $tag = 'YCbCrCoefficients'; break;
		case '0213': $tag = 'YCbCrPositioning'; break;
		case '0214': $tag = 'ReferenceBlackWhite'; break;
		case '8298': $tag = 'Copyright'; break;
		case '8649': $tag = 'PhotoshopSettings'; break;
		case '8769': $tag = 'ExifOffset'; break;
		case '8825': $tag = 'GPSInfoOffset'; break;            
		case '9286': $tag = 'UserCommentOld'; break;

		case '829a': $tag = 'ExposureTime'; break;
		case '829d': $tag = 'FNumber'; break;
		case '8822': $tag = 'ExposureProgram'; break;
		case '8824': $tag = 'SpectralSensitivity'; break;
		case '8827': $tag = 'ISOSpeedRatings'; break;
		case '9000': $tag = 'ExifVersion'; break;
		case '9003': $tag = 'DateTimeOriginal'; break;
		case '9004': $tag = 'DateTimedigitized'; break;
		case '9101': $tag = 'ComponentsConfiguration'; break;
		case '9102': $tag = 'CompressedBitsPerPixel'; break;
		case '9201': $tag = 'ShutterSpeedValue'; break;
		case '9202': $tag = 'ApertureValue'; break;
		case '9203': $tag = 'BrightnessValue'; break;
		case '9204': $tag = 'ExposureBiasValue'; break;
		case '9205': $tag = 'MaxApertureValue'; break;
		case '9206': $tag = 'SubjectDistance'; break;
		case '9207': $tag = 'MeteringMode'; break;
		case '9208': $tag = 'LightSource'; break;
		case '9209': $tag = 'Flash'; break;
		case '920a': $tag = 'FocalLength'; break;
		case '9213': $tag = 'ImageHistory'; break;
		case '927c': $tag = 'MakerNote'; break;
		case '9286': $tag = 'UserComment'; break;
		case '9290': $tag = 'SubsecTime'; break;
		case '9291': $tag = 'SubsecTimeOriginal'; break;
		case '9292': $tag = 'SubsecTimeDigitized'; break;
		case 'a000': $tag = 'FlashPixVersion'; break;
		case 'a001': $tag = 'ColorSpace'; break;
		case 'a002': $tag = 'ExifImageWidth'; break;
		case 'a003': $tag = 'ExifImageHeight'; break;
		case 'a004': $tag = 'RelatedSoundFile'; break;
		case 'a005': $tag = 'ExifInteroperabilityOffset'; break;
		case 'a20c': $tag = 'SpacialFreqResponse'; break;
		case 'a20b': $tag = 'FlashEnergy'; break;
		case 'a20e': $tag = 'FocalPlaneXResolution'; break;
		case 'a20f': $tag = 'FocalPlaneYResolution'; break;
		case 'a210': $tag = 'FocalPlaneResolutionUnit'; break;
		case 'a214': $tag = 'SubjectLocation'; break;
		case 'a215': $tag = 'ExposureIndex'; break;
		case 'a217': $tag = 'SensingMethod'; break;
		case 'a300': $tag = 'FileSource'; break;
		case 'a301': $tag = 'SceneType'; break;
		case 'a302': $tag = 'CFAPattern'; break;
		case 'a401': $tag = 'CustomerRender'; break;
		case 'a402': $tag = 'ExposureMode'; break;
		case 'a403': $tag = 'WhiteBalance'; break;
		case 'a404': $tag = 'DigitalZoomRatio'; break;
		case 'a405': $tag = 'FocalLengthIn35mmFilm'; break;
		case 'a406': $tag = 'SceneCaptureMode'; break;
		case 'a407': $tag = 'GainControl'; break;
		case 'a408': $tag = 'Contrast'; break;
		case 'a409': $tag = 'Saturation'; break;
		case 'a40a': $tag = 'Sharpness'; break;

		case '0001': $tag = 'InteroperabilityIndex'; break;
		case '0002': $tag = 'InteroperabilityVersion'; break;
		case '1000': $tag = 'RelatedImageFileFormat'; break;
		case '1001': $tag = 'RelatedImageWidth'; break;
		case '1002': $tag = 'RelatedImageLength'; break;

		case '0100': $tag = 'ImageWidth'; break;
		case '0101': $tag = 'ImageLength'; break;
		case '0102': $tag = 'BitsPerSample'; break;
		case '0103': $tag = 'Compression'; break;
		case '0106': $tag = 'PhotometricInterpretation'; break;
		case '010e': $tag = 'ThumbnailDescription'; break;
		case '010f': $tag = 'ThumbnailMake'; break;
		case '0110': $tag = 'ThumbnailModel'; break;
		case '0111': $tag = 'StripOffsets'; break;
		case '0112': $tag = 'ThumbnailOrientation'; break;
		case '0115': $tag = 'SamplesPerPixel'; break;
		case '0116': $tag = 'RowsPerStrip'; break;
		case '0117': $tag = 'StripByteCounts'; break;
		case '011a': $tag = 'ThumbnailXResolution'; break;
		case '011b': $tag = 'ThumbnailYResolution'; break;
		case '011c': $tag = 'PlanarConfiguration'; break;
		case '0128': $tag = 'ThumbnailResolutionUnit'; break;
		case '0201': $tag = 'JpegIFOffset'; break;        
		case '0202': $tag = 'JpegIFByteCount'; break;
		case '0212': $tag = 'YCbCrSubSampling'; break;

		case '00ff': $tag = 'SubfileType'; break;
		case '012d': $tag = 'TransferFunction'; break;
		case '013d': $tag = 'Predictor'; break;
		case '0142': $tag = 'TileWidth'; break;
		case '0143': $tag = 'TileLength'; break;
		case '0144': $tag = 'TileOffsets'; break;
		case '0145': $tag = 'TileByteCounts'; break;
		case '014a': $tag = 'SubIFDs'; break;
		case '015b': $tag = 'JPEGTables'; break;
		case '828d': $tag = 'CFARepeatPatternDim'; break;
		case '828e': $tag = 'CFAPattern'; break;
		case '828f': $tag = 'BatteryLevel'; break;
		case '83bb': $tag = 'IPTC/NAA'; break;
		case '8773': $tag = 'InterColorProfile'; break;

		case '8828': $tag = 'OECF'; break;
		case '8829': $tag = 'Interlace'; break;
		case '882a': $tag = 'TimeZoneOffset'; break;
		case '882b': $tag = 'SelfTimerMode'; break;
		case '920b': $tag = 'FlashEnergy'; break;
		case '920c': $tag = 'SpatialFrequencyResponse'; break;
		case '920d': $tag = 'Noise'; break;
		case '9211': $tag = 'ImageNumber'; break;
		case '9212': $tag = 'SecurityClassification'; break;
		case '9214': $tag = 'SubjectLocation'; break;
		case '9215': $tag = 'ExposureIndex'; break;
		case '9216': $tag = 'TIFF/EPStandardID'; break;
		case 'a20b': $tag = 'FlashEnergy'; break;

		default: $tag = 'unknown:'.$tag; break;
	}
	return $tag;

}

function lookup_type(&$type,&$size) {
	switch($type) {
		case '0001': $type = 'UBYTE'; $size=1; break;
		case '0002': $type = 'ASCII'; $size=1; break;
		case '0003': $type = 'USHORT'; $size=2; break;
		case '0004': $type = 'ULONG'; $size=4; break;
		case '0005': $type = 'URATIONAL'; $size=8; break;
		case '0006': $type = 'SBYTE'; $size=1; break;
		case '0007': $type = 'UNDEFINED'; $size=1; break;
		case '0008': $type = 'SSHORT'; $size=2; break;
		case '0009': $type = 'SLONG'; $size=4; break;
		case '000a': $type = 'SRATIONAL'; $size=8; break;
		case '000b': $type = 'FLOAT'; $size=4; break;
		case '000c': $type = 'DOUBLE'; $size=8; break;
		default: $type = 'error:'.$type; $size=0; break;
	}
	return $type;
}

function formatData($type,$tag,$intel,$data) {

	if ($type == 'ASCII') {
		if (($pos = strpos($data, chr(0))) !== false) {
			$data = substr($data, 0, $pos);
		}
		if ($tag == '010f') $data = ucwords(strtolower(trim($data)));

	} else if ($type == 'URATIONAL' || $type == 'SRATIONAL') {
		$data = bin2hex($data);
		if ($intel == 1) $data = intel2Moto($data);

		if ($intel == 1) $top = hexdec(substr($data,8,8));
		else  $top = hexdec(substr($data,0,8));

		if ($intel == 1) $bottom = hexdec(substr($data,0,8));
		else  $bottom = hexdec(substr($data,8,8));

		if ($type == 'SRATIONAL' && $top > 2147483647) $top = $top - 4294967296;
		if ($bottom != 0) $data=$top/$bottom;
		else if ($top == 0) $data = 0;
		else $data = $top.'/'.$bottom;

		if (($tag == '011a' || $tag == '011b') && $bottom == 1) {
			$data = $top.' dots per ResolutionUnit';
		} else if ($tag == '829a') {
			if ($top != 0 && ($bottom % $top) == 0) {
				$data = '1/'.round($bottom/$top, 0).' sec'; 
			} else {
				if ($bottom == 1) {
					$data = $top.' sec';
				} else {
					$data = $top.'/'.$bottom.' sec';
				}
			}
		} else if ($tag == '829d') {
			$data = 'f/'.$data;
		} else if ($tag == '9204') {
			$data = round($data, 2) . ' EV';
		} else if ($tag == '9205' || $tag == '9202') {
			// ApertureValue is given in the APEX Mode. Many thanks to Matthieu Froment for this code
			$data = exp(($data*log(2))/2);
			$data = round($data, 1);
			$data='f/'.$data; 
		} else if ($tag == '920a') {
			$data = $data.' mm';
		} else if ($tag == '8827') {
			$data = 'ISO '.$data;
		} else if ($tag == '9201') {
			// The ShutterSpeedValue is given in the APEX mode. Many thanks to Matthieu Froment for this code
			$data = exp($data * log(2)); 
			if ($data > 1) $data = floor($data);
			if ($data > 0) {
				$data = 1/$data;
				$n=0; $d=0;
				ConvertToFraction($data, $n, $d); 
				if ($n >= 1 && $d == 1) $data = $n.' sec';
				else $data = $n.'/'.$d.' sec';
			} else {
				$data = 'Bulb';
			}
		}
	} else if ($type == 'USHORT' || $type == 'SSHORT' || $type == 'ULONG' || $type == 'SLONG' || $type == 'FLOAT' || $type == 'DOUBLE') {
		$data = bin2hex($data);
		if ($intel == 1) $data = intel2Moto($data);
		if ($intel == 0 && ($type == 'USHORT' || $type == 'SSHORT')) $data = substr($data,0,4);
		$data = hexdec($data);

		if ($type == 'SSHORT' && $data > 32767)     $data = $data - 65536;
		if ($type == 'SLONG' && $data > 2147483647) $data = $data - 4294967296;

		if ($tag == '0112') {
			switch ($data) {
				case 1  :   $data = '1: Normal (0 deg)';      break;
				case 2  :   $data = '2: Mirrored';            break;
				case 3  :   $data = '3: Upsidedown';          break;
				case 4  :   $data = '4: Upsidedown Mirrored'; break;
				case 5  :   $data = '5: 90 deg CW Mirrored';  break;
				case 6  :   $data = '6: 90 deg CCW';          break;
				case 7  :   $data = '7: 90 deg CCW Mirrored'; break;
				case 8  :   $data = '8: 90 deg CW';           break;
				default :   $data = 'Unknown: '.$data;
			}
		} else if ($tag == '0128' || $tag == 'a210' || $tag == '0128') {
			if ($data == 1)         $data = 'No Unit';
			else if ($data == 2)    $data = 'Inch';
			else if ($data == 3)    $data = 'Centimeter';
		} else if ($tag == '0213') {
			if ($data == 1)         $data = 'Center of Pixel Array';
			else if ($data == 2)    $data = 'Datum Point';
		} else if ($tag == '8822') {
			if ($data == 1)         $data = 'Manual';
			else if ($data == 2)    $data = 'Program';
			else if ($data == 3)    $data = 'Aperature Priority';
			else if ($data == 4)    $data = 'Shutter Priority';
			else if ($data == 5)    $data = 'Program Creative';
			else if ($data == 6)    $data = 'Program Action';
			else if ($data == 7)    $data = 'Portrat';
			else if ($data == 8)    $data = 'Landscape';
			else                    $data = 'Unknown: '.$data;
		} else if ($tag == '9207') {
			if ($data == 0) $data = 'Unknown';
			else if ($data == 1)    $data = 'Average';
			else if ($data == 2)    $data = 'Center Weighted Average';
			else if ($data == 3)    $data = 'Spot';
			else if ($data == 4)    $data = 'Multi-Spot';
			else if ($data == 5)    $data = 'Multi-Segment';
			else if ($data == 6)    $data = 'Partial';
			else if ($data == 255)  $data = 'Other';
			else                    $data = 'Unknown: '.$data;
		} else if ($tag == '9208') {
			if ($data == 0) $data = 'Unknown or Auto';
			else if ($data == 1)    $data = 'Daylight';
			else if ($data == 2)    $data = 'Flourescent';
			else if ($data == 3)    $data = 'Tungsten';
			else if ($data == 10)   $data = 'Flash';
			else if ($data == 17)   $data = 'Standard Light A';
			else if ($data == 18)   $data = 'Standard Light B';
			else if ($data == 19)   $data = 'Standard Light C';
			else if ($data == 20)   $data = 'D55';
			else if ($data == 21)   $data = 'D65';
			else if ($data == 22)   $data = 'D75';
			else if ($data == 255)  $data = 'Other';
			else                    $data = 'Unknown: '.$data;
		} else if ($tag == '9209') {
			if ($data == 0) $data = 'No Flash';
			else if ($data == 1)    $data = 'Flash';
			else if ($data == 5)    $data = 'Flash, strobe return light not detected';
			else if ($data == 7)    $data = 'Flash, strob return light detected';
			else if ($data == 9)    $data = 'Compulsory Flash';
			else if ($data == 13)   $data = 'Compulsory Flash, Return light not detected';
			else if ($data == 15)   $data = 'Compulsory Flash, Return light detected';
			else if ($data == 16)   $data = 'No Flash';
			else if ($data == 24)   $data = 'No Flash';
			else if ($data == 25)   $data = 'Flash, Auto-Mode';
			else if ($data == 29)   $data = 'Flash, Auto-Mode, Return light not detected';
			else if ($data == 31)   $data = 'Flash, Auto-Mode, Return light detected';
			else if ($data == 32)   $data = 'No Flash';
			else if ($data == 65)   $data = 'Red Eye';
			else if ($data == 69)   $data = 'Red Eye, Return light not detected';
			else if ($data == 71)   $data = 'Red Eye, Return light detected';
			else if ($data == 73)   $data = 'Red Eye, Compulsory Flash';
			else if ($data == 77)   $data = 'Red Eye, Compulsory Flash, Return light not detected';
			else if ($data == 79)   $data = 'Red Eye, Compulsory Flash, Return light detected';
			else if ($data == 89)   $data = 'Red Eye, Auto-Mode';
			else if ($data == 93)   $data = 'Red Eye, Auto-Mode, Return light not detected';
			else if ($data == 95)   $data = 'Red Eye, Auto-Mode, Return light detected';
			else                    $data = 'Unknown: '.$data;
		} else if ($tag == 'a001') {
			if ($data == 1)         $data = 'sRGB';
			else                    $data = 'Uncalibrated';
		} else if ($tag == 'a002' || $tag == 'a003') {
			$data = $data. ' pixels';
		} else if ($tag == '0103') {
			if ($data == 1)      $data = 'No Compression';
			else if ($data == 6) $data = 'Jpeg Compression';
			else                 $data = 'Unknown: '.$data;
		} else if ($tag == 'a217') {
			if ($data == 1)      $data = 'Not defined';
			if ($data == 2)      $data = 'One Chip Color Area Sensor';
			if ($data == 3)      $data = 'Two Chip Color Area Sensor';
			if ($data == 4)      $data = 'Three Chip Color Area Sensor';
			if ($data == 5)      $data = 'Color Sequential Area Sensor';
			if ($data == 7)      $data = 'Trilinear Sensor';
			if ($data == 8)      $data = 'Color Sequential Linear Sensor';
			else                 $data = 'Unknown: '.$data;
		} else if ($tag == '0106') {
			if ($data == 1)      $data = 'Monochrome';
			else if ($data == 2) $data = 'RGB';
			else if ($data == 6) $data = 'YCbCr';
			else                 $data = 'Unknown: '.$data;
		}
	} else if ($type == 'UNDEFINED') {
		if ($tag == '9000' || $tag == 'a000' || $tag == '0002') {
			$data='version '.$data/100;
		}
		if ($tag == 'a300') {
			$data = bin2hex($data);
			$data = str_replace('00','',$data);
			$data = str_replace('03','Digital Still Camera',$data);
		}
		if ($tag == 'a301') {
			$data = bin2hex($data);
			$data = str_replace('00','',$data);
			$data = str_replace('01','Directly Photographed',$data);
		}
		if ($tag == '9101') {
			$data = bin2hex($data);
			$data = str_replace('01','Y',$data);
			$data = str_replace('02','Cb',$data);
			$data = str_replace('03','Cr',$data);
			$data = str_replace('04','R',$data);
			$data = str_replace('05','G',$data);
			$data = str_replace('06','B',$data);
			$data = str_replace('00','',$data);
		}
	} else {
		$data = bin2hex($data);
		if ($intel == 1) $data = intel2Moto($data);
	}

	return $data;
}

function read_entry(&$result,$in,$seek,$intel,$ifd_name,$globalOffset)
{
	if (feof($in)) {
		$result['Errors'] = $result['Errors']+1;
		return;
	}

	$tag = bin2hex(fread($in, 2));
	if ($intel == 1) $tag = intel2Moto($tag);
	$tag_name = lookup_tag($tag);

	$type = bin2hex(fread($in, 2));
	if ($intel == 1) $type = intel2Moto($type);
	lookup_type($type, $size);

	$count = bin2hex(fread($in, 4));
	if ($intel == 1) $count = intel2Moto($count);
	$bytesofdata = $size*hexdec($count);

	$value = fread( $in, 4 );

	if ($bytesofdata <= 4) {
		$data = $value;
	} else if ($bytesofdata < 100000) {
		$value = bin2hex($value);
		if ($intel == 1) $value = intel2Moto($value);
		$v = fseek($seek, $globalOffset+hexdec($value));
		if ($v == 0) {
			$data = fread($seek, $bytesofdata);
		} else if ($v == -1) {
			$result['Errors'] = $result['Errors']+1;
		}
	} else {
		$result['Errors'] = $result['Errors']+1;
		return;
	}
	if ($tag_name == 'MakerNote') {
		$make = isset($result['IFD0'], $result['IFD0']['Make']) ? $result['IFD0']['Make'] : '';

		if ($result['VerboseOutput'] == 1) {
			$result[$ifd_name]['MakerNote']['RawData'] = $data;
		}
		if (preg_match('~NIKON~i',$make)) {
			parseNikon($data,$result);
			$result[$ifd_name]['KnownMaker'] = 1;
		} else if (preg_match('~OLYMPUS~i',$make)) {
			parseOlympus($data,$result,$seek,$globalOffset);
			$result[$ifd_name]['KnownMaker'] = 1;
		} else if (preg_match('~Canon~i',$make)) {
			parseCanon($data,$result,$seek,$globalOffset);
			$result[$ifd_name]['KnownMaker'] = 1;
		} else if (preg_match('~FUJIFILM~i',$make)) {
			parseFujifilm($data,$result);
			$result[$ifd_name]['KnownMaker'] = 1;
		} else if (preg_match('~SANYO~i',$make)) {
			parseSanyo($data,$result,$seek,$globalOffset);
			$result[$ifd_name]['KnownMaker'] = 1;
	} else if (preg_match('~Panasonic~i',$make)) { 
		parsePanasonic($data,$result,$seek,$globalOffset); 
		$result[$ifd_name]['KnownMaker'] = 1; 
		} else {
			$result[$ifd_name]['KnownMaker'] = 0;
		}
	} else if ($tag_name == 'GPSInfoOffset') {
		$formated_data = formatData($type,$tag,$intel,$data);
		$result[$ifd_name]['GPSInfo'] = $formated_data;
		parseGPS($data,$result,$formated_data,$seek,$globalOffset);
	} else {
		$formated_data = formatData($type,$tag,$intel,$data);

		$result[$ifd_name][$tag_name] = $formated_data;

		if ($result['VerboseOutput'] == 1) {
			if ($type == 'URATIONAL' || $type == 'SRATIONAL' || $type == 'USHORT' || $type == 'SSHORT' || $type == 'ULONG' || $type == 'SLONG' || $type == 'FLOAT' || $type == 'DOUBLE') {
				$data = bin2hex($data);
				if ($intel == 1) $data = intel2Moto($data);
			}
			$result[$ifd_name][$tag_name.'_Verbose']['RawData'] = $data;
		$result[$ifd_name][$tag_name.'_Verbose']['Type'] = $type;
			$result[$ifd_name][$tag_name.'_Verbose']['Bytes'] = $bytesofdata;
		}
	}
}

// http://www.ba.wakwak.com/~tsuruzoh/Computer/Digicams/exif-e.html
// http://www.w3.org/Graphics/JPEG/jfif.txt
// http://exif.org/
// http://www.ozhiker.com/electronics/pjmt/library/list_contents.php4
// http://www.ozhiker.com/electronics/pjmt/jpeg_info/makernotes.html
// http://pel.sourceforge.net/
// http://us2.php.net/manual/en/function.exif-read-data.php
function read_exif_data_raw($path,$verbose)
{
	if ($path == '' || $path == 'none') return;

	$in = @fopen($path, 'rb');
	$seek = @fopen($path, 'rb');

	$globalOffset = 0;

	if (!isset($verbose)) $verbose=0;

	$result['VerboseOutput'] = $verbose;
	$result['Errors'] = 0;

	if (!$in || !$seek) {
		$result['Errors'] = 1;
		$result['Error'][$result['Errors']] = 'The file could not be found.';
		return $result;
	}

	$data = bin2hex(fread( $in, 2 ));
	if ($data == 'ffd8') {
		$result['ValidJpeg'] = 1;
	} else {
		$result['ValidJpeg'] = 0;
		fclose($in);
		fclose($seek);
		return $result;
	}  

	$result['ValidIPTCData'] = 0;
	$result['ValidJFIFData'] = 0;
	$result['ValidEXIFData'] = 0;
	$result['ValidAPP2Data'] = 0;
	$result['ValidCOMData'] = 0;

	$data = bin2hex(fread( $in, 2 ));
	$size = bin2hex(fread( $in, 2 ));

	while(!feof($in) && $data!='ffe1' && $data!='ffc0' && $data!='ffd9') {
		if ($data == 'ffe0') {
			$result['ValidJFIFData'] = 1;
			$result['JFIF']['Size'] = hexdec($size);

			if (hexdec($size)-2 > 0) {
				$data = fread( $in, hexdec($size)-2);
				$result['JFIF']['Data'] = $data;
			} 

			$result['JFIF']['Identifier'] = substr($data,0,5);;
			$result['JFIF']['ExtensionCode'] =  bin2hex(substr($data,6,1));

			$globalOffset+=hexdec($size)+2;

		} else if ($data == 'ffed') {
			$result['ValidIPTCData'] = 1;
			$result['IPTC']['Size'] = hexdec($size);

			if (hexdec($size)-2 > 0) {
				$data = fread( $in, hexdec($size)-2);
				$result['IPTC']['Data'] = $data ;
			} 
			$globalOffset+=hexdec($size)+2;

		} else if ($data == 'ffe2') {
			$result['ValidAPP2Data'] = 1;
			$result['APP2']['Size'] = hexdec($size);

			if (hexdec($size)-2 > 0) {
				$data = fread( $in, hexdec($size)-2);
				$result['APP2']['Data'] = $data ;
			} 
			$globalOffset+=hexdec($size)+2;

		} else if ($data == 'fffe') {
			$result['ValidCOMData'] = 1;
			$result['COM']['Size'] = hexdec($size);

			if (hexdec($size)-2 > 0) {
				$data = fread( $in, hexdec($size)-2);
				$result['COM']['Data'] = $data ;
			} 
			$globalOffset+=hexdec($size)+2;

		} else if ($data == 'ffe1') {
			$result['ValidEXIFData'] = 1;
		}

		$data = bin2hex(fread( $in, 2 ));
		$size = bin2hex(fread( $in, 2 ));
	}

	if ($data == 'ffe1') {
		$result['ValidEXIFData'] = 1;
	} else {
		fclose($in);
		fclose($seek);
		return $result;
	}

	$result['APP1Size'] = hexdec($size);

	$header = fread( $in, 6 );

	$header = fread( $in, 2 );
	if ($header==='II') {
		$intel=1;
		$result['Endien'] = 'Intel';
	} else if ($header==='MM') {
		$intel=0;
		$result['Endien'] = 'Motorola';
	} else {
		$intel=1;
		$result['Endien'] = 'Unknown';
	}

	$tag = bin2hex(fread( $in, 2 ));

	$offset = bin2hex(fread( $in, 4 ));
	if ($intel == 1) $offset = intel2Moto($offset);

	if (hexdec($offset) > 100000) {
			$result['ValidEXIFData'] = 0;
		fclose($in);
		fclose($seek);
		return $result;
	}

	if (hexdec($offset)>8) $unknown = fread( $in, hexdec($offset)-8);

	$globalOffset+=12;

	$num = bin2hex(fread( $in, 2 ));
	if ($intel == 1) $num = intel2Moto($num);
	$num = hexdec($num);
	$result['IFD0NumTags'] = $num;

	if ($num<1000) {
		for($i=0; $i<$num; $i++) {
			read_entry($result,$in,$seek,$intel,'IFD0',$globalOffset);
		}
	} else {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = 'Illegal size for IFD0';
	}

	$offset = bin2hex(fread( $in, 4 ));
	if ($intel == 1) $offset = intel2Moto($offset);
	$result['IFD1Offset'] = hexdec($offset);

	if (!isset($result['IFD0']['ExifOffset']) || $result['IFD0']['ExifOffset'] == 0) {
		fclose($in);
		fclose($seek);
		return $result;
	}

	$ExitOffset = $result['IFD0']['ExifOffset'];
	$v = fseek($in,$globalOffset+$ExitOffset);
	if ($v == -1) {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = 'Couldnt Find SubIFD';
	}

	$num = bin2hex(fread( $in, 2 ));
	if ($intel == 1) $num = intel2Moto($num);
	$num = hexdec($num);
	$result['SubIFDNumTags'] = $num;

	if ($num<1000) {
		for($i=0; $i<$num; $i++) {
			read_entry($result,$in,$seek,$intel,'SubIFD',$globalOffset);
		}
	} else {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = 'Illegal size for SubIFD';
	}

	$result['SubIFD']['FocalLength35mmEquiv'] = get35mmEquivFocalLength($result);

	if (!isset($result['IFD1Offset']) || $result['IFD1Offset'] == 0) {
		fclose($in);
		fclose($seek);
		return $result;
	}
	$v = fseek($in,$globalOffset+$result['IFD1Offset']);
	if ($v == -1) {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = 'Couldnt Find IFD1';
	}

	$num = bin2hex(fread( $in, 2 ));
	if ($intel == 1) $num = intel2Moto($num);
	$num = hexdec($num);
	$result['IFD1NumTags'] = $num;

	if ($num<1000) {
		for($i=0; $i<$num; $i++) {
			read_entry($result,$in,$seek,$intel,'IFD1',$globalOffset);
		}
	} else {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = 'Illegal size for IFD1';
	}

	if ($result['VerboseOutput'] == 1 && $result['IFD1']['JpegIFOffset']>0 && $result['IFD1']['JpegIFByteCount']>0) {
			$v = fseek($seek,$globalOffset+$result['IFD1']['JpegIFOffset']);
			if ($v == 0) {
				$data = fread($seek, $result['IFD1']['JpegIFByteCount']);
			} else if ($v == -1) {
				$result['Errors'] = $result['Errors']+1;
			}
			$result['IFD1']['ThumbnailData'] = $data;
	} 

	if (!isset($result['SubIFD']['ExifInteroperabilityOffset']) || $result['SubIFD']['ExifInteroperabilityOffset'] == 0) {
		fclose($in);
		fclose($seek);
		return $result;
	}

	$v = fseek($in,$globalOffset+$result['SubIFD']['ExifInteroperabilityOffset']);
	if ($v == -1) {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = 'Couldnt Find InteroperabilityIFD';
	}

	$num = bin2hex(fread( $in, 2 ));
	if ($intel == 1) $num = intel2Moto($num);
	$num = hexdec($num);
	$result['InteroperabilityIFDNumTags'] = $num;

	if ($num<1000) {
		for($i=0; $i<$num; $i++) {
			read_entry($result,$in,$seek,$intel,'InteroperabilityIFD',$globalOffset);
		}
	} else {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = 'Illegal size for InteroperabilityIFD';
	}
	fclose($in);
	fclose($seek);
	return $result;
}

// Converts a floating point number into a fraction.  Many thanks to Matthieu Froment for this code
function ConvertToFraction($v, &$n, &$d)
{
	$MaxTerms = 15;
	$MinDivisor = 0.000001;
	$MaxError = 0.00000001;

	$f = $v;

	$n_un = 1;
	$d_un = 0;
	$n_deux = 0;
	$d_deux = 1;

	for ($i = 0; $i<$MaxTerms; $i++)
	{
		$a = floor($f);
		$f = $f - $a;
		$n = $n_un * $a + $n_deux;
		$d = $d_un * $a + $d_deux;
		$n_deux = $n_un;
		$d_deux = $d_un;
		$n_un = $n;
		$d_un = $d;

		if ($f < $MinDivisor)
			break;

		if (abs($v - $n / $d) < $MaxError)
			break;

		$f = 1 / $f;
	}
}  

// Calculates the 35mm-equivalent focal length from the reported sensor resolution, by Tristan Harward.
function get35mmEquivFocalLength(&$result)
{
	if (empty($result['SubIFD']['ExifImageWidth']) || empty($result['SubIFD']['FocalPlaneResolutionUnit'])
	 || empty($result['SubIFD']['FocalPlaneXResolution']) || empty($result['SubIFD']['FocalLength']))
		return null;

	$width = $result['SubIFD']['ExifImageWidth'];
	$units = $result['SubIFD']['FocalPlaneResolutionUnit'];
	$unitfactor = 1;
	switch ($units) {
		case 'Inch' :       $unitfactor = 25.4; break;
		case 'Centimeter' : $unitfactor = 10;   break;
		case 'No Unit' :    $unitfactor = 25.4; break;
		default :           $unitfactor = 25.4;
	}
	$xres = $result['SubIFD']['FocalPlaneXResolution'];
	$fl = $result['SubIFD']['FocalLength'];

	$ccdwidth = ($width * $unitfactor) / $xres;
	$equivfl = $fl / $ccdwidth*36+0.5;
	return $equivfl;
}

function lookup_Canon_tag($tag)
{
	switch($tag) {
		case "0001": $tag = "Settings 1"; break;
		case "0004": $tag = "Settings 4"; break;
		case "0006": $tag = "ImageType"; break;
		case "0007": $tag = "FirmwareVersion"; break;
		case "0008": $tag = "ImageNumber"; break;
		case "0009": $tag = "OwnerName"; break;
		case "000c": $tag = "CameraSerialNumber"; break;
		case "000f": $tag = "CustomFunctions"; break;

		default: $tag = "unknown:".$tag; break;
	}

	return $tag;
}

function formatCanonData($type,$tag,$intel,$data,$exif,&$result)
{
	$place = 0;

	if($type=="ASCII") {
		$result = $data = str_replace("\0", "", $data);
	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$top = hexdec(substr($data,8,8));
		$bottom = hexdec(substr($data,0,8));
		if($bottom!=0) $data=$top/$bottom;
		else if($top==0) $data = 0;
		else $data=$top."/".$bottom;

		if($tag=="0204") {
			$data=$data."x";
		} 
	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {

		$data = bin2hex($data);
		$result['RAWDATA'] = $data;

		if($tag=="0001") {
			$result['Bytes']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			if ($result['Bytes'] != strlen($data) / 2) return $result;
			$result['Macro']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['Macro']) {
				case 1: $result['Macro'] = "Macro"; break;
				case 2: $result['Macro'] = "Normal"; break;
				default: $result['Macro'] = "Unknown";
			}
			$result['SelfTimer']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['SelfTimer']) {
				case 0: $result['SelfTimer'] = "Off"; break;
				default: $result['SelfTimer'] .= "/10s";
			}
			$result['Quality']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['Quality']) {
				case 2: $result['Quality'] = "Normal"; break;
				case 3: $result['Quality'] = "Fine"; break;
				case 5: $result['Quality'] = "Superfine"; break;
				default: $result['Quality'] = "Unknown";
			}
			$result['Flash']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['Flash']) {
				case 0: $result['Flash'] = "Off"; break;
				case 1: $result['Flash'] = "Auto"; break;
				case 2: $result['Flash'] = "On"; break;
				case 3: $result['Flash'] = "Red Eye Reduction"; break;
				case 4: $result['Flash'] = "Slow Synchro"; break;
				case 5: $result['Flash'] = "Auto + Red Eye Reduction"; break;
				case 6: $result['Flash'] = "On + Red Eye Reduction"; break;
				case 16: $result['Flash'] = "External Flash"; break;
				default: $result['Flash'] = "Unknown";
			}
			$result['DriveMode']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['DriveMode']) {
				case 0: $result['DriveMode'] = "Single/Timer"; break;
				case 1: $result['DriveMode'] = "Continuous"; break;
				default: $result['DriveMode'] = "Unknown";
			}
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['FocusMode']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['FocusMode']) {
				case 0: $result['FocusMode'] = "One-Shot"; break;
				case 1: $result['FocusMode'] = "AI Servo"; break;
				case 2: $result['FocusMode'] = "AI Focus"; break;
				case 3: $result['FocusMode'] = "Manual Focus"; break;
				case 4: $result['FocusMode'] = "Single"; break;
				case 5: $result['FocusMode'] = "Continuous"; break;
				case 6: $result['FocusMode'] = "Manual Focus"; break;
				default: $result['FocusMode'] = "Unknown";
			}
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['ImageSize']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['ImageSize']) {
				case 0: $result['ImageSize'] = "Large"; break;
				case 1: $result['ImageSize'] = "Medium"; break;
				case 2: $result['ImageSize'] = "Small"; break;
				default: $result['ImageSize'] = "Unknown";
			}
			$result['EasyShooting']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['EasyShooting']) {
				case 0: $result['EasyShooting'] = "Full Auto"; break;
				case 1: $result['EasyShooting'] = "Manual"; break;
				case 2: $result['EasyShooting'] = "Landscape"; break;
				case 3: $result['EasyShooting'] = "Fast Shutter"; break;
				case 4: $result['EasyShooting'] = "Slow Shutter"; break;
				case 5: $result['EasyShooting'] = "Night"; break;
				case 6: $result['EasyShooting'] = "Black & White"; break;
				case 7: $result['EasyShooting'] = "Sepia"; break;
				case 8: $result['EasyShooting'] = "Portrait"; break;
				case 9: $result['EasyShooting'] = "Sport"; break;
				case 10: $result['EasyShooting'] = "Macro/Close-Up"; break;
				case 11: $result['EasyShooting'] = "Pan Focus"; break;
				default: $result['EasyShooting'] = "Unknown";
			}
			$result['DigitalZoom']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['DigitalZoom']) {
				case 0:
				case 65535: $result['DigitalZoom'] = "None"; break;
				case 1: $result['DigitalZoom'] = "2x"; break;
				case 2: $result['DigitalZoom'] = "4x"; break;
				default: $result['DigitalZoom'] = "Unknown";
			}
			$result['Contrast']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['Contrast']) {
				case 0: $result['Contrast'] = "Normal"; break;
				case 1: $result['Contrast'] = "High"; break;
				case 65535: $result['Contrast'] = "Low"; break;
				default: $result['Contrast'] = "Unknown";
			}
			$result['Saturation']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['Saturation']) {
				case 0: $result['Saturation'] = "Normal"; break;
				case 1: $result['Saturation'] = "High"; break;
				case 65535: $result['Saturation'] = "Low"; break;
				default: $result['Saturation'] = "Unknown";
			}
			$result['Sharpness']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['Sharpness']) {
				case 0: $result['Sharpness'] = "Normal"; break;
				case 1: $result['Sharpness'] = "High"; break;
				case 65535: $result['Sharpness'] = "Low"; break;
				default: $result['Sharpness'] = "Unknown";
			}
			$result['ISO']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['ISO']) {
				case 32767:
				case 0: $result['ISO'] = isset($exif['SubIFD']['ISOSpeedRatings'])
					? $exif['SubIFD']['ISOSpeedRatings'] : 'Unknown'; break;
				case 15: $result['ISO'] = "Auto"; break;
				case 16: $result['ISO'] = "50"; break;
				case 17: $result['ISO'] = "100"; break;
				case 18: $result['ISO'] = "200"; break;
				case 19: $result['ISO'] = "400"; break;
				default: $result['ISO'] = "Unknown";
			}
			$result['MeteringMode']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['MeteringMode']) {
				case 3: $result['MeteringMode'] = "Evaluative"; break;
				case 4: $result['MeteringMode'] = "Partial"; break;
				case 5: $result['MeteringMode'] = "Center-weighted"; break;
				default: $result['MeteringMode'] = "Unknown";
			}
			$result['FocusType']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['FocusType']) {
				case 0: $result['FocusType'] = "Manual"; break;
				case 1: $result['FocusType'] = "Auto"; break;
				case 3: $result['FocusType'] = "Close-up (Macro)"; break;
				case 8: $result['FocusType'] = "Locked (Pan Mode)"; break;
				default: $result['FocusType'] = "Unknown";
			}
			$result['AFPointSelected']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['AFPointSelected']) {
				case 12288: $result['AFPointSelected'] = "Manual Focus"; break;
				case 12289: $result['AFPointSelected'] = "Auto Selected"; break;
				case 12290: $result['AFPointSelected'] = "Right"; break;
				case 12291: $result['AFPointSelected'] = "Center"; break;
				case 12292: $result['AFPointSelected'] = "Left"; break;
				default: $result['AFPointSelected'] = "Unknown";
			}
			$result['ExposureMode']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['ExposureMode']) {
				case 0: $result['ExposureMode'] = "EasyShoot"; break;
				case 1: $result['ExposureMode'] = "Program"; break;
				case 2: $result['ExposureMode'] = "Tv"; break;
				case 3: $result['ExposureMode'] = "Av"; break;
				case 4: $result['ExposureMode'] = "Manual"; break;
				case 5: $result['ExposureMode'] = "Auto-DEP"; break;
				default: $result['ExposureMode'] = "Unknown";
			}
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['LongFocalLength']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
				$result['LongFocalLength'] .= " focal units";
			$result['ShortFocalLength']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
				$result['ShortFocalLength'] .= " focal units";
			$result['FocalUnits']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
				$result['FocalUnits'] .= " per mm";
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['FlashActivity']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['FlashActivity']) {
				case 0: $result['FlashActivity'] = "Flash Did Not Fire"; break;
				case 1: $result['FlashActivity'] = "Flash Fired"; break;
				default: $result['FlashActivity'] = "Unknown";
			}
			$result['FlashDetails']=str_pad(base_convert(intel2Moto(substr($data,$place,4)), 16, 2), 16, "0", STR_PAD_LEFT);$place+=4;
				$flashDetails = array();
				if (substr($result['FlashDetails'], 1, 1) == 1) { $flashDetails[] = 'External E-TTL'; }
				if (substr($result['FlashDetails'], 2, 1) == 1) { $flashDetails[] = 'Internal Flash'; }
				if (substr($result['FlashDetails'], 4, 1) == 1) { $flashDetails[] = 'FP sync used'; }
				if (substr($result['FlashDetails'], 8, 1) == 1) { $flashDetails[] = '2nd(rear)-curtain sync used'; }
				if (substr($result['FlashDetails'], 12, 1) == 1) { $flashDetails[] = '1st curtain sync'; }
				$result['FlashDetails']=implode(",", $flashDetails);
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$anotherFocusMode=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			if(strpos(strtoupper($exif['IFD0']['Model']), "G1") !== false) {
				switch($anotherFocusMode) {
					case 0: $result['FocusMode'] = "Single"; break;
					case 1: $result['FocusMode'] = "Continuous"; break;
					default: $result['FocusMode'] = "Unknown";
				}
			}

		} else if($tag=="0004") {
			$result['Bytes']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			if ($result['Bytes'] != strlen($data) / 2) return $result;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['WhiteBalance']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			switch($result['WhiteBalance']) {
				case 0: $result['WhiteBalance'] = "Auto"; break;
				case 1: $result['WhiteBalance'] = "Sunny"; break;
				case 2: $result['WhiteBalance'] = "Cloudy"; break;
				case 3: $result['WhiteBalance'] = "Tungsten"; break;
				case 4: $result['WhiteBalance'] = "Fluorescent"; break;
				case 5: $result['WhiteBalance'] = "Flash"; break;
				case 6: $result['WhiteBalance'] = "Custom"; break;
				default: $result['WhiteBalance'] = "Unknown";
			}
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['SequenceNumber']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['AFPointUsed']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;

			$afPointUsed = array();
			if ($result['AFPointUsed'] & 0x0001) $afPointUsed[] = "Right";
			if ($result['AFPointUsed'] & 0x0002) $afPointUsed[] = "Center";
			if ($result['AFPointUsed'] & 0x0004) $afPointUsed[] = "Left";
			if ($result['AFPointUsed'] & 0x0800) $afPointUsed[] = "12";
			if ($result['AFPointUsed'] & 0x1000) $afPointUsed[] = "13";
			if ($result['AFPointUsed'] & 0x2000) $afPointUsed[] = "14";
			if ($result['AFPointUsed'] & 0x4000) $afPointUsed[] = "15";
			$result['AFPointUsed'] = implode(",", $afPointUsed);
			$result['FlashBias']=intel2Moto(substr($data,$place,4));$place+=4;

			switch($result['FlashBias'])
			{
				case 'ffc0': $result['FlashBias'] = "-2 EV"; break;
				case 'ffcc': $result['FlashBias'] = "-1.67 EV"; break;
				case 'ffd0': $result['FlashBias'] = "-1.5 EV"; break;
				case 'ffd4': $result['FlashBias'] = "-1.33 EV"; break;
				case 'ffe0': $result['FlashBias'] = "-1 EV"; break;
				case 'ffec': $result['FlashBias'] = "-0.67 EV"; break;
				case 'fff0': $result['FlashBias'] = "-0.5 EV"; break;
				case 'fff4': $result['FlashBias'] = "-0.33 EV"; break;
				case '0000': $result['FlashBias'] = "0 EV"; break;
				case '000c': $result['FlashBias'] = "0.33 EV"; break;
				case '0010': $result['FlashBias'] = "0.5 EV"; break;
				case '0014': $result['FlashBias'] = "0.67 EV"; break;
				case '0020': $result['FlashBias'] = "1 EV"; break;
				case '002c': $result['FlashBias'] = "1.33 EV"; break;
				case '0030': $result['FlashBias'] = "1.5 EV"; break;
				case '0034': $result['FlashBias'] = "1.67 EV"; break;
				case '0040': $result['FlashBias'] = "2 EV"; break;
				default: $result['FlashBias'] = "Unknown";
			}
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['SubjectDistance']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;
			$result['SubjectDistance'] .= "/100 m";
		}
		elseif ($tag=="0008")
		{
			if($intel==1) $data = intel2Moto($data);
			$data=hexdec($data);
			$result = round($data/10000)."-".$data%10000;
		}
		elseif ($tag=="000c")
		{
			if ($intel==1)
				$data = intel2Moto($data);
			$data=hexdec($data);
			$result = "#".bin2hex(substr($data,0,16)).substr($data,16,16);
		}
	}
	elseif ($type=="UNDEFINED")
	{
	}
	else
	{
		$data = bin2hex($data);
		if ($intel==1) $data = intel2Moto($data);
	}

	return $data;
}


// Useful:  http://www.burren.cx/david/canon.html
// http://www.burren.cx/david/canon.html
// http://www.ozhiker.com/electronics/pjmt/jpeg_info/canon_mn.html
function parseCanon($block, &$result, $seek, $globalOffset)
{
	$place = 0;

	if($result['Endien']=="Intel") $intel=1;
	else $intel=0;

	$model = $result['IFD0']['Model'];

	$num = bin2hex(substr($block,$place,2));
	$place += 2;
	if ($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);
	$exifilesize = !empty($GLOBALS['exiferFileSize']) ? $GLOBALS['exiferFileSize'] : 100000;

	for ($i=0; $i<hexdec($num); $i++)
	{
		$tag = bin2hex(substr($block,$place,2));
		$place += 2;
		if ($intel==1) $tag = intel2Moto($tag);
		$tag_name = lookup_Canon_tag($tag);

		$type = bin2hex(substr($block,$place,2));
		$place += 2;
		if ($intel==1) $type = intel2Moto($type);
		lookup_type($type,$size);

		$count = bin2hex(substr($block,$place,4));
		$place += 4;
		if ($intel==1) $count = intel2Moto($count);
		$bytesofdata = $size*hexdec($count);

		if ($bytesofdata<=0)
			return;

		$value = substr($block,$place,4);$place+=4;

		if($bytesofdata<=4)
			$data = $value;
		else
		{
			$value = bin2hex($value);
			if ($intel==1) $value = intel2Moto($value);
			$v = fseek($seek, $globalOffset+hexdec($value));
			if ($v==0 && $bytesofdata < $exifilesize)
				$data = fread($seek, $bytesofdata);
			elseif ($v==-1)
				$result['Errors'] = $result['Errors']++;
		}
		$formated_data = formatCanonData($type,$tag,$intel,$data,$result,$result['SubIFD']['MakerNote'][$tag_name]);

		if ($result['VerboseOutput']==1)
		{
			if ($type=="URATIONAL" || $type=="SRATIONAL" || $type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE")
			{
				$data = bin2hex($data);
				if ($intel==1) $data = intel2Moto($data);
			}
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['RawData'] = $data;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Type'] = $type;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Bytes'] = $bytesofdata;
		}
	}
}

function lookup_Fujifilm_tag($tag)
{
	switch($tag)
	{
		case "0000": $tag = "Version";break;
		case "1000": $tag = "Quality";break;
		case "1001": $tag = "Sharpness";break;
		case "1002": $tag = "WhiteBalance";break;
		case "1003": $tag = "Color";break;
		case "1004": $tag = "Tone";break;
		case "1010": $tag = "FlashMode";break;
		case "1011": $tag = "FlashStrength";break;
		case "1020": $tag = "Macro";break;
		case "1021": $tag = "FocusMode";break;
		case "1030": $tag = "SlowSync";break;
		case "1031": $tag = "PictureMode";break;
		case "1032": $tag = "Unknown";break;
		case "1100": $tag = "ContinuousTakingBracket";break;
		case "1200": $tag = "Unknown";break;
		case "1300": $tag = "BlurWarning";break;
		case "1301": $tag = "FocusWarning";break;
		case "1302": $tag = "AEWarning";break;

		default: $tag = "unknown:".$tag;break;
	}

	return $tag;
}

function formatFujifilmData($type,$tag,$intel,$data) {

	if($type=="ASCII") {


	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$top = hexdec(substr($data,8,8));
		$bottom = hexdec(substr($data,0,8));
		if($bottom!=0) $data=$top/$bottom;
		else if($top==0) $data = 0;
		else $data=$top."/".$bottom;

		if($tag=="1011") {
			$data=$data." EV";
		} 

	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$data=hexdec($data);

		if($tag=="1001") {
			if($data == 1) $data = "Soft";
			else if($data == 2) $data = "Soft";
			else if($data == 3) $data = "Normal";
			else if($data == 4) $data = "Hard";
			else if($data == 5) $data = "Hard";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1002") {
			if($data == 0) $data = "Auto";
			else if($data == 256) $data = "Daylight";
			else if($data == 512) $data = "Cloudy";
			else if($data == 768) $data = "DaylightColor-fluorescence";
			else if($data == 769) $data = "DaywhiteColor-fluorescence";
			else if($data == 770) $data = "White-fluorescence";
			else if($data == 1024) $data = "Incandenscense";
			else if($data == 3840) $data = "Custom";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1003") {
			if($data == 0) $data = "Chroma Saturation Normal(STD)";
			else if($data == 256) $data = "Chroma Saturation High";
			else if($data == 512) $data = "Chroma Saturation Low(ORG)";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1004") {
			if($data == 0) $data = "Contrast Normal(STD)";
			else if($data == 256) $data = "Contrast High(HARD)";
			else if($data == 512) $data = "Contrast Low(ORG)";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1010") {
			if($data == 0) $data = "Auto";
			else if($data == 1) $data = "On";
			else if($data == 2) $data = "Off";
			else if($data == 3) $data = "Red-Eye Reduction";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1020") {
			if($data == 0) $data = "Off";
			else if($data == 1) $data = "On";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1021") {
			if($data == 0) $data = "Auto";
			else if($data == 1) $data = "Manual";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1030") {
			if($data == 0) $data = "Off";
			else if($data == 1) $data = "On";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1031") {
			if($data == 0) $data = "Auto";
			else if($data == 1) $data = "Portrait";
			else if($data == 2) $data = "Landscape";
			else if($data == 4) $data = "Sports";
			else if($data == 5) $data = "Night";
			else if($data == 6) $data = "Program AE";
			else if($data == 256) $data = "Aperture Prority AE";
			else if($data == 512) $data = "Shutter Prority";
			else if($data == 768) $data = "Manual Exposure";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1100") {
			if($data == 0) $data = "Off";
			else if($data == 1) $data = "On";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1300") {
			if($data == 0) $data = "No Warning";
			else if($data == 1) $data = "Warning";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1301") {
			if($data == 0) $data = "Auto Focus Good";
			else if($data == 1) $data = "Out of Focus";
			else $data = "Unknown: ".$data;
		}
		if($tag=="1302") {
			if($data == 0) $data = "AE Good";
			else if($data == 1) $data = "Over Exposure";
			else $data = "Unknown: ".$data;
		}
	} else if($type=="UNDEFINED") {



	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
	}

	return $data;
}

function parseFujifilm($block,&$result) {

	$intel=1;

	$model = $result['IFD0']['Model'];

	$place=8;
	$offset=8;


	$num = bin2hex(substr($block,$place,4));$place+=4;
	if($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['Offset'] = hexdec($num);

	$num = bin2hex(substr($block,$place,2));$place+=2;
	if($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);

	for($i=0;$i<hexdec($num);$i++) {

		$tag = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $tag = intel2Moto($tag);
		$tag_name = lookup_Fujifilm_tag($tag);

		$type = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $type = intel2Moto($type);
		lookup_type($type,$size);

		$count = bin2hex(substr($block,$place,4));$place+=4;
		if($intel==1) $count = intel2Moto($count);
		$bytesofdata = $size*hexdec($count);

		$value = substr($block,$place,4);$place+=4;


		if($bytesofdata<=4) {
			$data = $value;
		} else {
			$value = bin2hex($value);
			if($intel==1) $value = intel2Moto($value);
			$data = substr($block,hexdec($value)-$offset,$bytesofdata*2);
		}
		$formated_data = formatFujifilmData($type,$tag,$intel,$data);

		if($result['VerboseOutput']==1) {
			$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
			if($type=="URATIONAL" || $type=="SRATIONAL" || $type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
				$data = bin2hex($data);
				if($intel==1) $data = intel2Moto($data);
			}
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['RawData'] = $data;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Type'] = $type;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Bytes'] = $bytesofdata;
		} else {
			$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
		}
	}
}

function lookup_GPS_tag($tag) {

	switch($tag) {
		case "0000": $tag = "Version";break;
		case "0001": $tag = "Latitude Reference";break;
		case "0002": $tag = "Latitude";break;
		case "0003": $tag = "Longitude Reference";break;
		case "0004": $tag = "Longitude";break;
		case "0005": $tag = "Altitude Reference";break;
		case "0006": $tag = "Altitude";break;
		case "0007": $tag = "Time";break;
		case "0008": $tag = "Satellite";break;
		case "0009": $tag = "ReceiveStatus";break;
		case "000a": $tag = "MeasurementMode";break;
		case "000b": $tag = "MeasurementPrecision";break;
		case "000c": $tag = "SpeedUnit";break;
		case "000d": $tag = "ReceiverSpeed";break;
		case "000e": $tag = "MovementDirectionRef";break;
		case "000f": $tag = "MovementDirection";break;
		case "0010": $tag = "ImageDirectionRef";break;
		case "0011": $tag = "ImageDirection";break;
		case "0012": $tag = "GeodeticSurveyData";break;
		case "0013": $tag = "DestLatitudeRef";break;
		case "0014": $tag = "DestinationLatitude";break;
		case "0015": $tag = "DestLongitudeRef";break;
		case "0016": $tag = "DestinationLongitude";break;
		case "0017": $tag = "DestBearingRef";break;
		case "0018": $tag = "DestinationBearing";break;
		case "0019": $tag = "DestDistanceRef";break;
		case "001a": $tag = "DestinationDistance";break;
		case "001b": $tag = "ProcessingMethod";break;
		case "001c": $tag = "AreaInformation";break;
		case "001d": $tag = "Datestamp";break;
		case "001e": $tag = "DifferentialCorrection";break;


		default: $tag = "unknown:".$tag;break;
	}

	return $tag;
}

function GPSRational($data, $intel) {

	if($intel==1) $top = hexdec(substr($data,8,8));
	else  $top = hexdec(substr($data,0,8));

	if($intel==1) $bottom = hexdec(substr($data,0,8));
	else  $bottom = hexdec(substr($data,8,8));

	if($bottom!=0) $data=$top/$bottom;
	else if($top==0) $data = 0;
	else $data=$top."/".$bottom;

	return $data;
}

function formatGPSData($type,$tag,$intel,$data) {

	if($type=="ASCII") {
						if($tag=="0001" || $tag=="0003"){
								$data = ($data{1} == $data{2} && $data{1} == $data{3}) ? $data{0} : $data;
						}

	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);

		if($intel==1) $top = hexdec(substr($data,8,8));
		else  $top = hexdec(substr($data,0,8));

		if($intel==1) $bottom = hexdec(substr($data,0,8));
		else  $bottom = hexdec(substr($data,8,8));

		if($type=="SRATIONAL" && $top>2147483647) $top = $top - 4294967296;

								if($tag=="0002" || $tag=="0004") {

			if($intel==1){ 
				$seconds = GPSRational(substr($data,0,16),$intel); 
				$hour = GPSRational(substr($data,32,16),$intel); 
			} else { 
				$hour= GPSRational(substr($data,0,16),$intel); 
				$seconds = GPSRational(substr($data,32,16),$intel); 
			}
			$minutes = GPSRational(substr($data,16,16),$intel);

			$data = $hour+$minutes/60+$seconds/3600;
		} else if($tag=="0007") {
			$seconds = GPSRational(substr($data,0,16),$intel);
			$minutes = GPSRational(substr($data,16,16),$intel);
			$hour = GPSRational(substr($data,32,16),$intel);

			$data = $hour.":".$minutes.":".$seconds;
		} else {
			if($bottom!=0) $data=$top/$bottom;
			else if($top==0) $data = 0;
			else $data=$top."/".$bottom;

												if($tag=="0006"){
														$data .= 'm';
												}
		}
	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$data=hexdec($data);


	} else if($type=="UNDEFINED") {



	} else if($type=="UBYTE") {
		$data = bin2hex($data);
		if($intel==1) $num = intel2Moto($data);


		if($tag=="0000") {
										$data =  hexdec(substr($data,0,2)) .
												".". hexdec(substr($data,2,2)) .
												".". hexdec(substr($data,4,2)) .
												".". hexdec(substr($data,6,2));

								} else if($tag=="0005"){
										if($data == "00000000"){ $data = 'Above Sea Level'; }
										else if($data == "01000000"){ $data = 'Below Sea Level'; }
								} 

	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
	}

	return $data;
}

// http://drewnoakes.com/code/exif/sampleOutput.html
// http://www.geosnapper.com
function parseGPS($block,&$result,$offset,$seek, $globalOffset) {

	if($result['Endien']=="Intel") $intel=1;
	else $intel=0;

	$v = fseek($seek,$globalOffset+$offset);
	if($v==-1) {
		$result['Errors'] = $result['Errors']++;
	}

	$num = bin2hex(fread( $seek, 2 ));
	if($intel==1) $num = intel2Moto($num);
	$num=hexdec($num);
	$result['GPS']['NumTags'] = $num;

	$block = fread( $seek, $num*12 );
	$place = 0;

	for($i=0;$i<$num;$i++) {
		$tag = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $tag = intel2Moto($tag);
		$tag_name = lookup_GPS_tag($tag);

		$type = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $type = intel2Moto($type);
		lookup_type($type,$size);

		$count = bin2hex(substr($block,$place,4));$place+=4;
		if($intel==1) $count = intel2Moto($count);
		$bytesofdata = $size*hexdec($count);

		$value = substr($block,$place,4);$place+=4;

		if($bytesofdata<=4) {
			$data = $value;
		} else {
			$value = bin2hex($value);
			if($intel==1) $value = intel2Moto($value);

			$v = fseek($seek,$globalOffset+hexdec($value));
			if($v==0) {
				$data = fread($seek, $bytesofdata);
			} else if($v==-1) {
				$result['Errors'] = $result['Errors']++;
			}
		}

		if($result['VerboseOutput']==1) {
			$result['GPS'][$tag_name] = formatGPSData($type,$tag,$intel,$data);
			$result['GPS'][$tag_name."_Verbose"]['RawData'] = bin2hex($data);
			$result['GPS'][$tag_name."_Verbose"]['Type'] = $type;
			$result['GPS'][$tag_name."_Verbose"]['Bytes'] = $bytesofdata;
		} else {
			$result['GPS'][$tag_name] = formatGPSData($type,$tag,$intel,$data);
		}
	}
}

function lookup_Nikon_tag($tag,$model) {

	if($model==0) {
		switch($tag) {
			case "0003": $tag = "Quality";break;
			case "0004": $tag = "ColorMode";break;
			case "0005": $tag = "ImageAdjustment";break;
			case "0006": $tag = "CCDSensitivity";break;
			case "0007": $tag = "WhiteBalance";break;
			case "0008": $tag = "Focus";break;
			case "0009": $tag = "Unknown2";break;
			case "000a": $tag = "DigitalZoom";break;
			case "000b": $tag = "Converter";break;

			default: $tag = "unknown:".$tag;break;
		}
	} else if($model==1) {
		switch($tag) {
			case "0002": $tag = "ISOSetting";break;
			case "0003": $tag = "ColorMode";break;
			case "0004": $tag = "Quality";break;
			case "0005": $tag = "Whitebalance";break;
			case "0006": $tag = "ImageSharpening";break;
			case "0007": $tag = "FocusMode";break;
			case "0008": $tag = "FlashSetting";break;
			case "0009": $tag = "FlashMode";break;
			case "000b": $tag = "WhiteBalanceFine";break;
			case "000f": $tag = "ISOSelection";break;
			case "0013": $tag = "ISOSelection2";break;
			case "0080": $tag = "ImageAdjustment";break;
			case "0081": $tag = "ToneCompensation";break;
			case "0082": $tag = "Adapter";break;
			case "0083": $tag = "LensType";break;
			case "0084": $tag = "LensInfo";break;
			case "0085": $tag = "ManualFocusDistance";break; 
			case "0086": $tag = "DigitalZoom";break;
			case "0087": $tag = "FlashUsed";break;
			case "0088": $tag = "AFFocusPosition";break;
			case "008d": $tag = "ColorMode";break;
			case "0090": $tag = "LightType";break;
			case "0094": $tag = "Saturation";break;
			case "0095": $tag = "NoiseReduction";break;
			case "0010": $tag = "DataDump";break;

			default: $tag = "unknown:".$tag;break;
		}
	} 

	return $tag;
}

function formatNikonData($type,$tag,$intel,$model,$data) {

	if($type=="ASCII") {


	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$top = hexdec(substr($data,8,8));
		$bottom = hexdec(substr($data,0,8));
		if($bottom!=0) $data=$top/$bottom;
		else if($top==0) $data = 0;
		else $data=$top."/".$bottom;

		if($tag=="0085" && $model==1) {
			$data=$data." m";
		} 
		if($tag=="0086" && $model==1) {
			$data=$data."x";
		} 
		if($tag=="000a" && $model==0) {
			$data=$data."x";
		} 
	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$data=hexdec($data);

		if($tag=="0003" && $model==0) {
			if($data == 1) $data = "VGA Basic";
			else if($data == 2) $data = "VGA Normal";
			else if($data == 3) $data = "VGA Fine";
			else if($data == 4) $data = "SXGA Basic";
			else if($data == 5) $data = "SXGA Normal";
			else if($data == 6) $data = "SXGA Fine";
			else $data = "Unknown: ".$data;
		}
		if($tag=="0004" && $model==0) {
			if($data == 1) $data = "Color";
			else if($data == 2) $data = "Monochrome";
			else $data = "Unknown: ".$data;
		}
		if($tag=="0005" && $model==0) {
			if($data == 0) $data = "Normal";
			else if($data == 1) $data = "Bright+";
			else if($data == 2) $data = "Bright-";
			else if($data == 3) $data = "Contrast+";
			else if($data == 4) $data = "Contrast-";
			else $data = "Unknown: ".$data;
		}
		if($tag=="0006" && $model==0) {
			if($data == 0) $data = "ISO-80";
			else if($data == 2) $data = "ISO-160";
			else if($data == 4) $data = "ISO-320";
			else if($data == 5) $data = "ISO-100";
			else $data = "Unknown: ".$data;
		}
		if($tag=="0007" && $model==0) {
			if($data == 0) $data = "Auto";
			else if($data == 1) $data = "Preset";
			else if($data == 2) $data = "Daylight";
			else if($data == 3) $data = "Incandescense";
			else if($data == 4) $data = "Flourescence";
			else if($data == 5) $data = "Cloudy";
			else if($data == 6) $data = "SpeedLight";
			else $data = "Unknown: ".$data;
		}
		if($tag=="000b" && $model==0) {
			if($data == 0) $data = "None";
			else if($data == 1) $data = "Fisheye";
			else $data = "Unknown: ".$data;
		}
	} else if($type=="UNDEFINED") {

		if($tag=="0001" && $model==1) {
			$data=$data/100;
		}
		if($tag=="0088" && $model==1) {
			$temp = "Center";
			$data = bin2hex($data);
			$data = str_replace("01","Top",$data);
			$data = str_replace("02","Bottom",$data);
			$data = str_replace("03","Left",$data);
			$data = str_replace("04","Right",$data);
			$data = str_replace("00","",$data);
			if(strlen($data)==0) $data = $temp;
		}

	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);

		if($tag=="0083" && $model==1) {
				$data = hexdec(substr($data,0,2));
			if($data == 0) $data = "AF non D";
			else if($data == 1) $data = "Manual";
			else if($data == 2) $data = "AF-D or AF-S";
			else if($data == 6) $data = "AF-D G";
			else if($data == 10) $data = "AF-D VR";
			else $data = "Unknown: ".$data;
		}
		if($tag=="0087" && $model==1) {
				$data = hexdec(substr($data,0,2));
			if($data == 0) $data = "Did Not Fire";
			else if($data == 4) $data = "Unknown";
			else if($data == 7) $data = "External";
			else if($data == 9) $data = "On Camera";
			else $data = "Unknown: ".$data;
		}
	}

	return $data;
}

function parseNikon($block,&$result) {

	if($result['Endien']=="Intel") $intel=1;
	else $intel=0;

	$model = $result['IFD0']['Model'];

	if($model=="E700\0" || $model=="E800\0" || $model=="E900\0" || $model=="E900S\0" || $model=="E910\0" || $model=="E950\0") {
		$place=8;
		$model = 0;

		$num = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $num = intel2Moto($num);
		$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);

		for($i=0;$i<hexdec($num);$i++) {
			$tag = bin2hex(substr($block,$place,2));$place+=2;
			if($intel==1) $tag = intel2Moto($tag);
			$tag_name = lookup_Nikon_tag($tag, $model);

			$type = bin2hex(substr($block,$place,2));$place+=2;
			if($intel==1) $type = intel2Moto($type);
			lookup_type($type,$size);

			$count = bin2hex(substr($block,$place,4));$place+=4;
			if($intel==1) $count = intel2Moto($count);
			$bytesofdata = $size*hexdec($count);

			$value = substr($block,$place,4);$place+=4;

			if($tag=="0002") $offset = hexdec($value)-140;
			if($bytesofdata<=4) {
				$data = $value;
			} else {
				$value = bin2hex($value);
				if($intel==1) $value = intel2Moto($value);
				$data = substr($block,hexdec($value)-$offset,$bytesofdata*2);
			}
			$formated_data = formatNikonData($type,$tag,$intel,$model,$data);

			if($result['VerboseOutput']==1) {
				$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
				$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['RawData'] = $data;
				$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Type'] = $type;
				$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Bytes'] = $bytesofdata;
			} else {
				$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
			}
		}

	} else {
		$place=0;
		$model = 1;

		$nikon = substr($block,$place,8);$place+=8;
		$endien = substr($block,$place,4);$place+=4;

		$tag = bin2hex(substr($block,$place,2));$place+=2;

		$offset = bin2hex(substr($block,$place,4));$place+=4;
		if($intel==1) $offset = intel2Moto($offset);
		if(hexdec($offset)>8) $place+=$offset-8;

		$num = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $num = intel2Moto($num);

		for($i=0;$i<hexdec($num);$i++) {
			$tag = bin2hex(substr($block,$place,2));$place+=2;
			if($intel==1) $tag = intel2Moto($tag);
			$tag_name = lookup_Nikon_tag($tag, $model);

			$type = bin2hex(substr($block,$place,2));$place+=2;
			if($intel==1) $type = intel2Moto($type);
			lookup_type($type,$size);

			$count = bin2hex(substr($block,$place,4));$place+=4;
			if($intel==1) $count = intel2Moto($count);
			$bytesofdata = $size*hexdec($count);

			$value = substr($block,$place,4);$place+=4;

			if($bytesofdata<=4) {
				$data = $value;
			} else {
				$value = bin2hex($value);
				if($intel==1) $value = intel2Moto($value);
				$data = substr($block,hexdec($value)+hexdec($offset)+2,$bytesofdata);
			}
			$formated_data = formatNikonData($type,$tag,$intel,$model,$data);

			if($result['VerboseOutput']==1) {
				$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
				if($type=="URATIONAL" || $type=="SRATIONAL" || $type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
					$data = bin2hex($data);
					if($intel==1) $data = intel2Moto($data);
				}
				$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['RawData'] = $data;
				$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Type'] = $type;
				$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Bytes'] = $bytesofdata;
			} else {
				$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
			}
		}

	}
}

function lookup_Olympus_tag($tag) {
	switch($tag) {
		case "0200": $tag = "SpecialMode";break;
		case "0201": $tag = "JpegQual";break;
		case "0202": $tag = "Macro";break;
		case "0203": $tag = "Unknown1";break;
		case "0204": $tag = "DigiZoom";break;
		case "0205": $tag = "Unknown2";break;
		case "0206": $tag = "Unknown3";break;
		case "0207": $tag = "SoftwareRelease";break;
		case "0208": $tag = "PictInfo";break;
		case "0209": $tag = "CameraID";break;
		case "0f00": $tag = "DataDump";break;

		default: $tag = "unknown:".$tag;break;
	}

	return $tag;
}

function formatOlympusData($type,$tag,$intel,$data) {
	if($type=="ASCII") {

	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$top = hexdec(substr($data,8,8));
		$bottom = hexdec(substr($data,0,8));
		if($bottom!=0) $data=$top/$bottom;
		else if($top==0) $data = 0;
		else $data=$top."/".$bottom;

		if($tag=="0204") {
			$data=$data."x";
		} 
		if($tag=="0205") {
			$data=$top."/".$bottom;
		} 
	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$data=hexdec($data);

		if($tag=="0201") {
			if($data == 1) $data = "SQ";
			else if($data == 2) $data = "HQ";
			else if($data == 3) $data = "SHQ";
			else $data = "Unknown: ".$data;
		}
		if($tag=="0202") {
			if($data == 0) $data = "Normal";
			else if($data == 1) $data = "Macro";
			else $data = "Unknown: ".$data;
		}
	} else if($type=="UNDEFINED") {

	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
	}

	return $data;
}

function parseOlympus($block, &$result, $seek, $globalOffset) {

	if($result['Endien']=="Intel") $intel = 1;
	else $intel = 0;

	$model = $result['IFD0']['Model'];

	$new = false;
	if (substr($block, 0, 8) == "OLYMPUS\x00") {
		$new = true;
	} else if (substr($block, 0, 7) == "OLYMP\x00\x01"
		|| substr($block, 0, 7) == "OLYMP\x00\x02") {
		$new = false;
	} else {
		return false;
	}

	$place = 8;
	$offset = 8;

	$countfieldbits = $new ? 1 : 2;
	$num = bin2hex(substr($block, $place, $countfieldbits)); $place += 2;
	if ($intel == 1) $num = intel2Moto($num);
	$ntags = hexdec($num);
	$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = $ntags;
	$exifilesize = !empty($GLOBALS['exiferFileSize']) ? $GLOBALS['exiferFileSize'] : 100000;

	for($i=0; $i < $ntags; $i++) {
		$tag = bin2hex(substr($block, $place,2));
		$place += 2;
		if ($intel == 1) $tag = intel2Moto($tag);
		$tag_name = lookup_Olympus_tag($tag);

		$type = bin2hex(substr($block, $place,2));
		$place += 2;
		if ($intel == 1) $type = intel2Moto($type);
		lookup_type($type,$size);

		$count = bin2hex(substr($block, $place,4));
		$place+=4;
		if ($intel == 1) $count = intel2Moto($count);
		$bytesofdata = $size * hexdec($count);

		$value = substr($block, $place,4);
		$place += 4;


		if ($bytesofdata <= 4) {
			$data = $value;
		} else {
			$value = bin2hex($value);
			if($intel==1) $value = intel2Moto($value);
			$v = fseek($seek,$globalOffset+hexdec($value));
			if($v == 0 && $bytesofdata < $exifilesize)
				$data = fread($seek, $bytesofdata);
			else
				$result['Errors'] = $result['Errors']++;
		}
		$formated_data = formatOlympusData($type,$tag,$intel,$data);

		if($result['VerboseOutput']==1) {
			$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
			if($type=="URATIONAL" || $type=="SRATIONAL" || $type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
				$data = bin2hex($data);
				if($intel==1) $data = intel2Moto($data);
			}
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['RawData'] = $data;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Type'] = $type;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Bytes'] = $bytesofdata;
		} else {
			$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
		}
	}
}

function lookup_Panasonic_tag($tag) {

	switch($tag) {
		case "0001": $tag = "Quality";break;
		case "0002": $tag = "FirmwareVersion";break;
		case "0003": $tag = "WhiteBalance";break;
		case "0007": $tag = "FocusMode";break;
		case "000f": $tag = "AFMode";break;
		case "001a": $tag = "ImageStabilizer";break;
		case "001c": $tag = "MacroMode";break;
		case "001f": $tag = "ShootingMode";break;
		case "0020": $tag = "Audio";break;
		case "0021": $tag = "DataDump";break;
		case "0023": $tag = "WhiteBalanceBias";break;
		case "0024": $tag = "FlashBias";break;
		case "0025": $tag = "SerialNumber";break;
		case "0028": $tag = "ColourEffect";break;
		case "002a": $tag = "BurstMode";break;
		case "002b": $tag = "SequenceNumber";break;
		case "002c": $tag = "Contrast";break;
		case "002d": $tag = "NoiseReduction";break;
		case "002e": $tag = "SelfTimer";break;
		case "0030": $tag = "Rotation";break;
		case "0032": $tag = "ColorMode";break;
		case "0036": $tag = "TravelDay";break;

		default: $tag = "unknown:".$tag;break;
	}

	return $tag;
}

function formatPanasonicData($type,$tag,$intel,$data) {

	if($type=="ASCII") {

	} else if($type=="UBYTE" || $type=="SBYTE") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$data=hexdec($data);

		if($tag=="000f") {
			if($data == 256) $data = "9-area-focusing";
			else if($data == 16) $data = "1-area-focusing";
			else $data = "Unknown (".$data.")";
		} 

	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$top = hexdec(substr($data,8,8));
		$bottom = hexdec(substr($data,0,8));
		if($bottom!=0) $data=$top/$bottom;
		else if($top==0) $data = 0;
		else $data=$top."/".$bottom;

	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$data=hexdec($data);

		if($tag=="0001") {
			if($data == 2) $data = "High";
			else if($data == 3) $data = "Standard";
			else if($data == 6) $data = "Very High";
			else if($data == 7) $data = "RAW";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="0003") {
			if($data == 1) $data = "Auto";
			else if($data == 2) $data = "Daylight";
			else if($data == 3) $data = "Cloudy";
			else if($data == 4) $data = "Halogen";
			else if($data == 5) $data = "Manual";
			else if($data == 8) $data = "Flash";
			else if($data == 10) $data = "Black and White";
			else if($data == 11) $data = "Manual";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="0007") {
			if($data == 1) $data = "Auto";
			else if($data == 2) $data = "Manual";
			else if($data == 4) $data = "Auto, Focus button";
			else if($data == 5) $data = "Auto, Continuous";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="001a") {
			if($data == 2) $data = "Mode 1";
			else if($data == 3) $data = "Off";
			else if($data == 4) $data = "Mode 2";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="001c") {
			if($data == 1) $data = "On";
			else if($data == 2) $data = "Off";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="001f") {
			if($data == 1) $data = "Normal";
			else if($data == 2) $data = "Portrait";
			else if($data == 3) $data = "Scenery";
			else if($data == 4) $data = "Sports";
			else if($data == 5) $data = "Night Portrait";
			else if($data == 6) $data = "Program";
			else if($data == 7) $data = "Aperture Priority";
			else if($data == 8) $data = "Shutter Priority";
			else if($data == 9) $data = "Macro";
			else if($data == 11) $data = "Manual";
			else if($data == 13) $data = "Panning";
			else if($data == 18) $data = "Fireworks";
			else if($data == 19) $data = "Party";
			else if($data == 20) $data = "Snow";
			else if($data == 21) $data = "Night Scenery";
			else if($data == 22) $data = "Food";
			else if($data == 23) $data = "Baby";
			else if($data == 27) $data = "High Sensitivity";
			else if($data == 29) $data = "Underwater";
			else if($data == 33) $data = "Pet";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="0020") {
			if($data == 1) $data = "Yes";
			else if($data == 2) $data = "No";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="0023") {
			$data=$data." EV";
		} 
		if($tag=="0024") {
			$data = $data;
		}
		if($tag=="0028") {
			if($data == 1) $data = "Off";
			else if($data == 2) $data = "Warm";
			else if($data == 3) $data = "Cool";
			else if($data == 4) $data = "Black and White";
			else if($data == 5) $data = "Sepia";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="002a") {
			if($data == 0) $data = "Off";
			else if($data == 1) $data = "Low/High Quality";
			else if($data == 2) $data = "Infinite";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="002c") {
			if($data == 0) $data = "Standard";
			else if($data == 1) $data = "Low";
			else if($data == 2) $data = "High";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="002d") {
			if($data == 0) $data = "Standard";
			else if($data == 1) $data = "Low";
			else if($data == 2) $data = "High";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="002e") {
			if($data == 1) $data = "Off";
			else if($data == 2) $data = "10s";
			else if($data == 3) $data = "2s";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="0030") {
			if($data == 1) $data = "Horizontal (normal)";
			else if($data == 6) $data = "Rotate 90 CW";
			else if($data == 8) $data = "Rotate 270 CW";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="0032") {
			if($data == 0) $data = "Normal";
			else if($data == 1) $data = "Natural";
			else $data = "Unknown (".$data.")";
		}
		if($tag=="0036") {
			$data=$data;
		} 
	} else if($type=="UNDEFINED") {

	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
	}

	return $data;
}

function parsePanasonic($block,&$result) {

	$intel=1;

	$model = $result['IFD0']['Model'];

	$place=8;
	$offset=8;


	$num = bin2hex(substr($block,$place,4));$place+=4;
	if($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['Offset'] = hexdec($num);

	$num = bin2hex(substr($block,$place,2));$place+=2;
	if($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);

	for($i=0;$i<hexdec($num);$i++) {

		$tag = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $tag = intel2Moto($tag);
		$tag_name = lookup_Panasonic_tag($tag);

		$type = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $type = intel2Moto($type);
		lookup_type($type,$size);

		$count = bin2hex(substr($block,$place,4));$place+=4;
		if($intel==1) $count = intel2Moto($count);
		$bytesofdata = $size*hexdec($count);

		$value = substr($block,$place,4);$place+=4;


		if($bytesofdata<=4) {
			$data = $value;
		} else {
			$value = bin2hex($value);
			if($intel==1) $value = intel2Moto($value);
			$data = substr($block,hexdec($value)-$offset,$bytesofdata*2);
		}
		$formated_data = formatPanasonicData($type,$tag,$intel,$data);

		if($result['VerboseOutput']==1) {
			$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
			if($type=="URATIONAL" || $type=="SRATIONAL" || $type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
				$data = bin2hex($data);
				if($intel==1) $data = intel2Moto($data);
			}
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['RawData'] = $data;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Type'] = $type;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Bytes'] = $bytesofdata;
		} else {
			$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
		}
	}
}

function lookup_Sanyo_tag($tag) {

	switch($tag) {
		case "0200": $tag = "SpecialMode";break;
		case "0201": $tag = "Quality";break;
		case "0202": $tag = "Macro";break;
		case "0203": $tag = "Unknown";break;
		case "0204": $tag = "DigiZoom";break;
		case "0f00": $tag = "DataDump";break;
		default: $tag = "unknown:".$tag;break;
	}

	return $tag;
}

function formatSanyoData($type,$tag,$intel,$data) {

	if($type=="ASCII") {


	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$top = hexdec(substr($data,8,8));
		$bottom = hexdec(substr($data,0,8));
		if($bottom!=0) $data=$top/$bottom;
		else if($top==0) $data = 0;
		else $data=$top."/".$bottom;


	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$data=hexdec($data);

		if($tag=="0200") {
			if($data == 0) $data = "Normal";
			else $data = "Unknown: ".$data;
		}
		if($tag=="0201") {
			if($data == 2) $data = "High";
			else $data = "Unknown: ".$data;
		}
		if($tag=="0202") {
			if($data == 0) $data = "Normal";
			else $data = "Unknown: ".$data;
		}
	} else if($type=="UNDEFINED") {



	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
	}

	return $data;
}

function parseSanyo($block,&$result,$seek, $globalOffset) {

	if($result['Endien']=="Intel") $intel=1;
	else $intel=0;

	$model = $result['IFD0']['Model'];

	$place=8;
	$offset=8;

	$num = bin2hex(substr($block,$place,2));$place+=2;
	if($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);

	for($i=0;$i<hexdec($num);$i++) {

		$tag = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $tag = intel2Moto($tag);
		$tag_name = lookup_Sanyo_tag($tag);

		$type = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $type = intel2Moto($type);
		lookup_type($type,$size);

		$count = bin2hex(substr($block,$place,4));$place+=4;
		if($intel==1) $count = intel2Moto($count);
		$bytesofdata = $size*hexdec($count);

		$value = substr($block,$place,4);$place+=4;


		if($bytesofdata<=4) {
			$data = $value;
		} else {
			$value = bin2hex($value);
			if($intel==1) $value = intel2Moto($value);
			$v = fseek($seek,$globalOffset+hexdec($value));
			if($v==0) {
				$data = fread($seek, $bytesofdata);
			} else if($v==-1) {
				$result['Errors'] = $result['Errors']++;
			}
		}
		$formated_data = formatSanyoData($type,$tag,$intel,$data);

		if($result['VerboseOutput']==1) {
			$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
			if($type=="URATIONAL" || $type=="SRATIONAL" || $type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
				$data = bin2hex($data);
				if($intel==1) $data = intel2Moto($data);
			}
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['RawData'] = $data;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Type'] = $type;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Bytes'] = $bytesofdata;
		} else {
			$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
		}
	}
}

?>