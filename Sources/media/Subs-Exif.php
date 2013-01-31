<?php

if (!defined('WEDGE'))
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

				This version will correctly parse the MakerNote field for Nikon, Olympus, and Canon
				digital cameras.  Others will follow.

	TESTED WITH:
				Nikon CoolPix 700
				Nikon CoolPix E3200
				Nikon CoolPix 4500
				Nikon CoolPix 950
				Nikon Coolpix 5700
				Canon PowerShot S200
				Canon PowerShot S110
				Olympus C2040Z
				Olympus C960
				Olumpus E-300
				Olympus E-410
				Olympus E-500
				Olympus E-510
				Olympus E-3
				Canon Ixus
				Canon EOS 300D
				Canon Digital Rebel
				Canon EOS 10D
				Canon PowerShot G2
				FujiFilm DX 10
				FujiFilm MX-1200
				FujiFilm FinePix2400
				FujiFilm FinePix2600
				FujiFilm FinePix S602
				FujiFilm FinePix40i
				Sony D700
				Sony Cybershot
				Kodak DC210
				Kodak DC240
				Kodak DC4800
				Kodak DX3215
				Ricoh RDC-5300
				Sanyo VPC-G250
				Sanyo VPC-SX550
				Epson 3100z


	VERSION HISTORY:

	1.0    September 23, 2002

		+ First Public Release

	1.1    January 25, 2003

		+ Gracefully handled the error case where you pass an empty string to this library
		+ Fixed an inconsistency in the Olympus Camera parsing module
		+ Added support for parsing the MakerNote of Canon images.
		+ Modified how the imagefile is opened so it works for windows machines.
		+ Correctly parses the FocalPlaneResolutionUnit and PhotometricInterpretation fields
		+ Negative rational numbers are properly displayed
		+ Strange old cameras that use Motorola endineness are now properly supported
		+ Tested with several more cameras

		Potential Problem: Negative Shorts and Negative Longs may not be correctly displayed, but I
			have not yet found an example of negative shorts or longs being used.

	1.2    March 30, 2003

		+ Fixed an error that was displayed if you edited your image with WinXP's image viewer
		+ Fixed a bug that caused some images saved from 3rd party software to not parse correctly
		+ Changed the ExposureTime tag to display in fractional seconds rather than decimal
		+ Updated the ShutterSpeedValue tag to have the units of 'sec'
		+ Added support for parsing the MakeNote of FujiFilm images
		+ Added support for parsing the MakeNote of Sanyo images
		+ Fixed a bug with parsing some Olympus MakerNote tags
		+ Tested with several more cameras

	1.3    June 15, 2003

		+ Fixed Canon MakerNote support for some models
			(Canon has very difficult and inconsistent MakerNote syntax)
		+ Negative signed shorts and negative signed longs are properly displayed
		+ Several more tags are defined
		+ More information in my comments about what each tag is
		+ Parses and Displays GPS information if available
		+ Tested with several more cameras

	1.4    September 14, 2003

		+ This software is now licensed under the GNU General Public License
		+ Exposure time is now correctly displayed when the numerator is 10
		+ Fixed the calculation and display of ShutterSpeedValue, ApertureValue and MaxApertureValue
		+ Fixed a bug with the GPS code
		+ Tested with several more cameras

	1.5    February 18, 2005

		+ It now gracefully deals with a passed in file that cannot be found.
		+ Fixed a GPS bug for the parsing of Altitude and other signed rational numbers
		+ Defined more values for Canon cameras.
		+ Added 'bulb' detection for ShutterSpeed
		+ Made script loading a little faster and less memory intensive.
		+ Bug fixes
		+ Better error reporting
		+ Graceful failure for files with corrupt exif info.
		+ QuickTime (including iPhoto) messes up the Makernote tag for certain photos (no workaround yet)
		+ Now reads exif information when the jpeg markers are out of order
		+ Gives raw data output for IPTC, COM and APP2 fields which are sometimes set by other applications
		+ Improvements to Nikon Makernote parsing

	1.6    March 25th, 2007 [Zenphoto]

		+ Adopted into the Zenphoto gallery project, at http://www.zenphoto.org
		+ Fixed a bug where strings had trailing null bytes.
		+ Formatted selected strings better.
		+ Added calculation of 35mm-equivalent focal length when possible.
		+ Cleaned up code for readability and efficiency.

	1.7    April 11th, 2008 [Zenphoto]

		+ Fixed bug with newer Olympus cameras where number of fields was miscalculated leading to bad performance.
		+ More logical fraction calculation for shutter speed.

2009: For all further changes, see the Zenphoto change logs.

*/






//================================================================================================
// Converts from Intel to Motorola endien.  Just reverses the bytes (assumes hex is passed in)
//================================================================================================

function intel2Moto($intel) {
	static $cache = array();
	if (isset($cache[$intel])) {
		return $cache[$intel];
	}

	$cache[$intel] = '';
	$len  = strlen($intel);
	if ($len > 1000) { // an unreasonable length, override it.
		$len = 1000;
	}
	for($i = 0; $i <= $len; $i += 2) {
		$cache[$intel] .= substr($intel, $len-$i, 2);
	}
	return $cache[$intel];
}


//================================================================================================
// Looks up the name of the tag
//================================================================================================
function lookup_tag($tag) {
	switch($tag) {
		// used by IFD0 'Camera Tags'
		case '000b': $tag = 'ACDComment'; break;               // text string up to 999 bytes long
		case '00fe': $tag = 'ImageType'; break;                // integer -2147483648 to 2147483647
		case '0106': $tag = 'PhotometricInterpret'; break;     // ?? Please send sample image with this tag
		case '010e': $tag = 'ImageDescription'; break;         // text string up to 999 bytes long
		case '010f': $tag = 'Make'; break;                     // text string up to 999 bytes long
		case '0110': $tag = 'Model'; break;                    // text string up to 999 bytes long
		case '0112': $tag = 'Orientation'; break;              // integer values 1-9
		case '0115': $tag = 'SamplePerPixel'; break;           // integer 0-65535
		case '011a': $tag = 'xResolution'; break;              // positive rational number
		case '011b': $tag = 'yResolution'; break;              // positive rational number
		case '011c': $tag = 'PlanarConfig'; break;             // integer values 1-2
		case '0128': $tag = 'ResolutionUnit'; break;           // integer values 1-3
		case '0131': $tag = 'Software'; break;                 // text string up to 999 bytes long
		case '0132': $tag = 'DateTime'; break;                 // YYYY:MM:DD HH:MM:SS
		case '013b': $tag = 'Artist'; break;                   // text string up to 999 bytes long
		case '013c': $tag = 'HostComputer'; break;             // text string
		case '013e': $tag = 'WhitePoint'; break;               // two positive rational numbers
		case '013f': $tag = 'PrimaryChromaticities'; break;    // six positive rational numbers
		case '0211': $tag = 'YCbCrCoefficients'; break;        // three positive rational numbers
		case '0213': $tag = 'YCbCrPositioning'; break;         // integer values 1-2
		case '0214': $tag = 'ReferenceBlackWhite'; break;      // six positive rational numbers
		case '8298': $tag = 'Copyright'; break;                // text string up to 999 bytes long
		case '8649': $tag = 'PhotoshopSettings'; break;        // ??
		case '8769': $tag = 'ExifOffset'; break;               // positive integer
		case '8825': $tag = 'GPSInfoOffset'; break;
		case '9286': $tag = 'UserCommentOld'; break;           // ??
		// used by Exif SubIFD 'Image Tags'
		case '829a': $tag = 'ExposureTime'; break;             // seconds or fraction of seconds 1/x
		case '829d': $tag = 'FNumber'; break;                  // positive rational number
		case '8822': $tag = 'ExposureProgram'; break;          // integer value 1-9
		case '8824': $tag = 'SpectralSensitivity'; break;      // ??
		case '8827': $tag = 'ISOSpeedRatings'; break;          // integer 0-65535
		case '9000': $tag = 'ExifVersion'; break;              // ??
		case '9003': $tag = 'DateTimeOriginal'; break;         // YYYY:MM:DD HH:MM:SS
		case '9004': $tag = 'DateTimeDigitized'; break;        // YYYY:MM:DD HH:MM:SS
		case '9101': $tag = 'ComponentsConfiguration'; break;  // ??
		case '9102': $tag = 'CompressedBitsPerPixel'; break;   // positive rational number
		case '9201': $tag = 'ShutterSpeedValue'; break;        // seconds or fraction of seconds 1/x
		case '9202': $tag = 'ApertureValue'; break;            // positive rational number
		case '9203': $tag = 'BrightnessValue'; break;          // positive rational number
		case '9204': $tag = 'ExposureBiasValue'; break;        // positive rational number (EV)
		case '9205': $tag = 'MaxApertureValue'; break;         // positive rational number
		case '9206': $tag = 'SubjectDistance'; break;          // positive rational number (meters)
		case '9207': $tag = 'MeteringMode'; break;             // integer 1-6 and 255
		case '9208': $tag = 'LightSource'; break;              // integer 1-255
		case '9209': $tag = 'Flash'; break;                    // integer 1-255
		case '920a': $tag = 'FocalLength'; break;              // positive rational number (mm)
		case '9213': $tag = 'ImageHistory'; break;             // text string up to 999 bytes long
		case '927c': $tag = 'MakerNote'; break;                // a bunch of data
		case '9286': $tag = 'UserComment'; break;              // text string
		case '9290': $tag = 'SubsecTime'; break;               // text string up to 999 bytes long
		case '9291': $tag = 'SubsecTimeOriginal'; break;       // text string up to 999 bytes long
		case '9292': $tag = 'SubsecTimeDigitized'; break;      // text string up to 999 bytes long
		case 'a000': $tag = 'FlashPixVersion'; break;          // ??
		case 'a001': $tag = 'ColorSpace'; break;               // values 1 or 65535
		case 'a002': $tag = 'ExifImageWidth'; break;           // ingeter 1-65535
		case 'a003': $tag = 'ExifImageHeight'; break;          // ingeter 1-65535
		case 'a004': $tag = 'RelatedSoundFile'; break;         // text string 12 bytes long
		case 'a005': $tag = 'ExifInteroperabilityOffset'; break;    // positive integer
		case 'a20c': $tag = 'SpacialFreqResponse'; break;      // ??
		case 'a20b': $tag = 'FlashEnergy'; break;              // positive rational number
		case 'a20e': $tag = 'FocalPlaneXResolution'; break;    // positive rational number
		case 'a20f': $tag = 'FocalPlaneYResolution'; break;    // positive rational number
		case 'a210': $tag = 'FocalPlaneResolutionUnit'; break; // values 1-3
		case 'a214': $tag = 'SubjectLocation'; break;          // two integers 0-65535
		case 'a215': $tag = 'ExposureIndex'; break;            // positive rational number
		case 'a217': $tag = 'SensingMethod'; break;            // values 1-8
		case 'a300': $tag = 'FileSource'; break;               // integer
		case 'a301': $tag = 'SceneType'; break;                // integer
		case 'a302': $tag = 'CFAPattern'; break;               // undefined data type
		case 'a401': $tag = 'CustomerRender'; break;           // values 0 or 1
		case 'a402': $tag = 'ExposureMode'; break;             // values 0-2
		case 'a403': $tag = 'WhiteBalance'; break;             // values 0 or 1
		case 'a404': $tag = 'DigitalZoomRatio'; break;         // positive rational number
		case 'a405': $tag = 'FocalLengthIn35mmFilm'; break;
		case 'a406': $tag = 'SceneCaptureMode'; break;         // values 0-3
		case 'a407': $tag = 'GainControl'; break;              // values 0-4
		case 'a408': $tag = 'Contrast'; break;                 // values 0-2
		case 'a409': $tag = 'Saturation'; break;               // values 0-2
		case 'a40a': $tag = 'Sharpness'; break;                // values 0-2
		case 'a434': $tag = 'LensInfo'; break;

		// used by Interoperability IFD
		case '0001': $tag = 'InteroperabilityIndex'; break;    // text string 3 bytes long
		case '0002': $tag = 'InteroperabilityVersion'; break;  // datatype undefined
		case '1000': $tag = 'RelatedImageFileFormat'; break;   // text string up to 999 bytes long
		case '1001': $tag = 'RelatedImageWidth'; break;        // integer in range 0-65535
		case '1002': $tag = 'RelatedImageLength'; break;       // integer in range 0-65535

		// used by IFD1 'Thumbnail'
		case '0100': $tag = 'ImageWidth'; break;               // integer in range 0-65535
		case '0101': $tag = 'ImageLength'; break;              // integer in range 0-65535
		case '0102': $tag = 'BitsPerSample'; break;            // integers in range 0-65535
		case '0103': $tag = 'Compression'; break;              // values 1 or 6
		case '0106': $tag = 'PhotometricInterpretation'; break;// values 0-4
		case '010e': $tag = 'ThumbnailDescription'; break;     // text string up to 999 bytes long
		case '010f': $tag = 'ThumbnailMake'; break;            // text string up to 999 bytes long
		case '0110': $tag = 'ThumbnailModel'; break;           // text string up to 999 bytes long
		case '0111': $tag = 'StripOffsets'; break;             // ??
		case '0112': $tag = 'ThumbnailOrientation'; break;     // integer 1-9
		case '0115': $tag = 'SamplesPerPixel'; break;          // ??
		case '0116': $tag = 'RowsPerStrip'; break;             // ??
		case '0117': $tag = 'StripByteCounts'; break;          // ??
		case '011a': $tag = 'ThumbnailXResolution'; break;     // positive rational number
		case '011b': $tag = 'ThumbnailYResolution'; break;     // positive rational number
		case '011c': $tag = 'PlanarConfiguration'; break;      // values 1 or 2
		case '0128': $tag = 'ThumbnailResolutionUnit'; break;  // values 1-3
		case '0201': $tag = 'JpegIFOffset'; break;
		case '0202': $tag = 'JpegIFByteCount'; break;
		case '0212': $tag = 'YCbCrSubSampling'; break;

		// misc
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


//================================================================================================
// Looks up the datatype
//================================================================================================
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

//================================================================================================
// truncates unreasonable read data requests.
//================================================================================================
function validSize($bytesofdata) {
	return min(8191,max(0,$bytesofdata));
}

//================================================================================================
// processes a irrational number
//================================================================================================
function unRational($data, $type, $intel) {
		$data = bin2hex($data);
		if ($intel == 1) {
			$data = intel2Moto($data);
			$top = hexdec(substr($data,8,8));   // intel stores them bottom-top
			$bottom = hexdec(substr($data,0,8));  // intel stores them bottom-top
		} else {
			$top = hexdec(substr($data,0,8));        // motorola stores them top-bottom
			$bottom = hexdec(substr($data,8,8));      // motorola stores them top-bottom
		}
		if ($type == 'SRATIONAL' && $top > 2147483647) $top = $top - 4294967296;    // this makes the number signed instead of unsigned
		if ($bottom != 0)
			$data=$top/$bottom;
		else
			if ($top == 0)
				$data = 0;
			else
				$data = $top.'/'.$bottom;
	return $data;
}

//================================================================================================
// processes a rational number
//================================================================================================
function rational($data,$type,$intel) {
	if (($type == 'USHORT' || $type == 'SSHORT')) {
		$data = substr($data,0,2);
	}
	$data = bin2hex($data);
	if ($intel == 1) {
		$data = intel2Moto($data);
	}
	$data = hexdec($data);
	if ($type == 'SSHORT' && $data > 32767)     $data = $data - 65536;  // this makes the number signed instead of unsigned
	if ($type == 'SLONG' && $data > 2147483647) $data = $data - 4294967296;  // this makes the number signed instead of unsigned
	return $data;
}

//================================================================================================
// Formats Data for the data type
//================================================================================================
function formatData($type,$tag,$intel,$data) {
	switch ($type) {
		case 'ASCII':
			if (($pos = strpos($data, chr(0))) !== false) {	// Search for a null byte and stop there.
				$data = substr($data, 0, $pos);
			}
			if ($tag == '010f') $data = ucwords(strtolower(trim($data)));	// Format certain kinds of strings nicely (Camera make etc.)
			break;
		case 'URATIONAL':
		case 'SRATIONAL':
			switch ($tag) {
				case '011a': // XResolution
				case '011b': // YResolution
					$data = round(unRational($data,$type,$intel)).' dots per ResolutionUnit';
					break;
				case '829a': // Exposure Time
					$data = formatExposure(unRational($data,$type,$intel));
					break;
				case '829d': // FNumber
					$data = 'f/'.round(unRational($data,$type,$intel),2);
					break;
				case '9204': // ExposureBiasValue
					$data = round(unRational($data,$type,$intel), 2) . ' EV';
					break;
				case '9205': // ApertureValue
				case '9202': // MaxApertureValue
					// ApertureValue is given in the APEX Mode. Many thanks to Matthieu Froment for this code
					// The formula is : Aperture = 2*log2(FNumber) <=> FNumber = e((Aperture.ln(2))/2)
					$datum = exp((unRational($data,$type,$intel)*log(2))/2);
					$data = 'f/'.round($datum, 1);// Focal is given with a precision of 1 digit.
					break;
				case '920a': // FocalLength
					$data = unRational($data,$type,$intel).' mm';
					break;
				case '9201': // ShutterSpeedValue
					// The ShutterSpeedValue is given in the APEX mode. Many thanks to Matthieu Froment for this code
					// The formula is : Shutter = - log2(exposureTime) (Appendix C of EXIF spec.)
					// Where shutter is in APEX, log2(exposure) = ln(exposure)/ln(2)
					// So final formula is : exposure = exp(-ln(2).shutter)
					// The formula can be developed : exposure = 1/(exp(ln(2).shutter))
					$datum = exp(unRational($data,$type,$intel) * log(2));
					if ($datum != 0) $datum = 1/$datum;
					$data = formatExposure($datum);
					break;
				default:
					$data = unRational($data,$type,$intel);
					break;
			}
			break;
		case 'USHORT':
		case 'SSHORT':
		case 'ULONG':
		case 'SLONG':
		case 'FLOAT':
		case 'DOUBLE':
			$data = rational($data,$type,$intel);
			switch ($tag) {
				case '0112':	// Orientation
					// Example of how all of these tag formatters should be...
					switch ($data) {
						case 0	:		// not set, presume normal
						case 1  :   $data = gettext('1: Normal (0 deg)');      break;
						case 2  :   $data = gettext('2: Mirrored');            break;
						case 3  :   $data = gettext('3: Upside-down');          break;
						case 4  :   $data = gettext('4: Upside-down Mirrored'); break;
						case 5  :   $data = gettext('5: 90 deg CW Mirrored');  break;
						case 6  :   $data = gettext('6: 90 deg CCW');          break;
						case 7  :   $data = gettext('7: 90 deg CCW Mirrored'); break;
						case 8  :   $data = gettext('8: 90 deg CW');           break;
						default :   $data = sprintf(gettext('%d: Unknown'),$data);	break;
					}
					break;
				case '0128':	// ResolutionUnit
				case 'a210':	// FocalPlaneResolutionUnit
				case '0128':	// ThumbnailResolutionUnit
					switch ($data) {
						case 1:	$data = gettext('No Unit');	break;
						case 2:	$data = gettext('Inch');	break;
						case 3:	$data = gettext('Centimeter');	break;
					}
					break;
				case '0213':	// YCbCrPositioning
					switch ($data) {
						case 1:	$data = gettext('Center of Pixel Array');	break;
						case 2:	$data = gettext('Datum Point');	break;
					}
					break;
				case '8822':	// ExposureProgram
					switch ($data) {
						case 1:		$data = gettext('Manual');	break;
						case 2:		$data = gettext('Program');	break;
						case 3:		$data = gettext('Aperture Priority');	break;
						case 4:		$data = gettext('Shutter Priority');	break;
						case 5:		$data = gettext('Program Creative');	break;
						case 6:		$data = gettext('Program Action');	break;
						case 7:		$data = gettext('Portrait');	break;
						case 8:		$data = gettext('Landscape');	break;
						default:	$data = gettext('Unknown').': '.$data;	break;
					}
					break;
				case '9207':	// MeteringMode
					switch ($data) {
						case 1:		$data = gettext('Average');	break;
						case 2:		$data = gettext('Center Weighted Average');	break;
						case 3:		$data = gettext('Spot');	break;
						case 4:		$data = gettext('Multi-Spot');	break;
						case 5:		$data = gettext('Pattern');	break;
						case 6:		$data = gettext('Partial');	break;
						case 255:	$data = gettext('Other');	break;
						default:	$data = gettext('Unknown').': '.$data;	break;
					}
					break;
				case '9208':	// LightSource
					switch ($data) {
						case 1:			$data = gettext('Daylight');	break;
						case 2:			$data = gettext('Fluorescent');	break;
						case 3:			$data = gettext('Tungsten');	break;	// 3 Tungsten (Incandescent light)
													// 4 Flash
													// 9 Fine Weather
						case 10:		$data = gettext('Flash');	break;	// 10 Cloudy Weather
													// 11 Shade
													// 12 Daylight Fluorescent (D 5700 - 7100K)
													// 13 Day White Fluorescent (N 4600 - 5400K)
													// 14 Cool White Fluorescent (W 3900 -4500K)
													// 15 White Fluorescent (WW 3200 - 3700K)
													// 10 Flash
						case 17:		$data = gettext('Standard Light A');	break;
						case 18:		$data = gettext('Standard Light B');	break;
						case 19:		$data = gettext('Standard Light C');	break;
						case 20:		$data = gettext('D55');	break;
						case 21:		$data = gettext('D65');	break;
						case 22:		$data = gettext('D75');	break;
						case 23:		$data = gettext('D50');	break;
						case 24:		$data = gettext('ISO Studio Tungsten');	break;
						case 255:		$data = gettext('Other');	break;
						default:		$data = gettext('Unknown').': '.$data;	break;
					}
					break;
				case '9209':	// Flash
					switch ($data) {


						case 0:
						case 16:
						case 24:
						case 32:
						case 64:
						case 80:		$data = gettext('No Flash');	break;
						case 1:			$data = gettext('Flash');	break;
						case 5:			$data = gettext('Flash, strobe return light not detected');	break;
						case 7:			$data = gettext('Flash, strobe return light detected');	break;
						case 9:			$data = gettext('Compulsory Flash');	break;
						case 13:		$data = gettext('Compulsory Flash, Return light not detected');	break;
						case 15:		$data = gettext('Compulsory Flash, Return light detected');	break;
						case 25:		$data = gettext('Flash, Auto-Mode');	break;
						case 29:		$data = gettext('Flash, Auto-Mode, Return light not detected');	break;
						case 31:		$data = gettext('Flash, Auto-Mode, Return light detected');	break;
						case 65:		$data = gettext('Red Eye');	break;
						case 69:		$data = gettext('Red Eye, Return light not detected');	break;
						case 71:		$data = gettext('Red Eye, Return light detected');	break;
						case 73:		$data = gettext('Red Eye, Compulsory Flash');	break;
						case 77:		$data = gettext('Red Eye, Compulsory Flash, Return light not detected');	break;
						case 79:		$data = gettext('Red Eye, Compulsory Flash, Return light detected');	break;
						case 89:		$data = gettext('Red Eye, Auto-Mode');	break;
						case 93:		$data = gettext('Red Eye, Auto-Mode, Return light not detected');	break;
						case 95:		$data = gettext('Red Eye, Auto-Mode, Return light detected');	break;
						default:		$data = gettext('Unknown').': '.$data;	break;
					}
					break;
				case 'a001':	// ColorSpace
					if ($data == 1)         $data = gettext('sRGB');
					else                    $data = gettext('Uncalibrated');
					break;
				case 'a002':	// ExifImageWidth
				case 'a003':	// ExifImageHeight
					$data = $data. ' '.gettext('pixels');
					break;
				case '0103':	// Compression
					switch ($data) {
						case 1:		$data = gettext('No Compression');	break;
						case 6:		$data = gettext('Jpeg Compression');	break;
						default:	$data = gettext('Unknown').': '.$data;	break;
					}
					break;
				case 'a217':	// SensingMethod
					switch ($data) {
						case 1:		$data = gettext('Not defined');	break;
						case 2:		$data = gettext('One Chip Color Area Sensor');	break;
						case 3:		$data = gettext('Two Chip Color Area Sensor');	break;
						case 4:		$data = gettext('Three Chip Color Area Sensor');	break;
						case 5:		$data = gettext('Color Sequential Area Sensor');	break;
						case 7:		$data = gettext('Trilinear Sensor');	break;
						case 8:		$data = gettext('Color Sequential Linear Sensor');	break;
						default:	$data = gettext('Unknown').': '.$data;	break;
					}
					break;
				case '0106':	// PhotometricInterpretation
					switch ($data) {
						case 1:		$data = gettext('Monochrome');	break;
						case 2:		$data = gettext('RGB');	break;
						case 6:		$data = gettext('YCbCr');	break;
						default:	$data = gettext('Unknown').': '.$data;	break;
					}
					break;
				//case "a408":	// Contrast
				//case "a40a":	//Sharpness
				//	switch($data) {
				//		case 0: $data="Normal"; break;
				//		case 1: $data="Soft"; break;
				//		case 2: $data="Hard"; break;
				//		default: $data="Unknown"; break;
				//	}
				//	break;
				//case "a409":	// Saturation
				//	switch($data) {
				//		case 0: $data="Normal"; break;
				//		case 1: $data="Low saturation"; break;
				//		case 2: $data="High saturation"; break;
				//		default: $data="Unknown"; break;
				//	}
				//	break;
				//case "a402":	// Exposure Mode
				//	switch($data) {
				//		case 0: $data="Auto exposure"; break;
				//		case 1: $data="Manual exposure"; break;
				//		case 2: $data="Auto bracket"; break;
				//		default: $data="Unknown"; break;
				//	}
				//	break;
			}
			break;
		case 'UNDEFINED':
			switch ($tag) {
				case '9000':	// ExifVersion
				case 'a000':	// FlashPixVersion
				case '0002':	// InteroperabilityVersion
					$data=gettext('version').' '.$data/100;
					break;
				case 'a300':	// FileSource
					$data = bin2hex($data);
					$data = str_replace('00','',$data);
					$data = str_replace('03',gettext('Digital Still Camera'),$data);
					break;
				case 'a301':	// SceneType
					$data = bin2hex($data);
					$data = str_replace('00','',$data);
					$data = str_replace('01',gettext('Directly Photographed'),$data);
					break;
				case '9101':	// ComponentsConfiguration
					$data = bin2hex($data);
					$data = str_replace('01','Y',$data);
					$data = str_replace('02','Cb',$data);
					$data = str_replace('03','Cr',$data);
					$data = str_replace('04','R',$data);
					$data = str_replace('05','G',$data);
					$data = str_replace('06','B',$data);
					$data = str_replace('00','',$data);
					break;
				//case "9286":	//UserComment
				//	$encoding	= rtrim(substr($data, 0, 8));
				//	$data		= rtrim(substr($data, 8));
				//	break;
			}
			break;
		default:
			$data = bin2hex($data);
			if ($intel == 1) $data = intel2Moto($data);
			break;
	}
	return $data;
}

function formatExposure($data) {
	if (strpos($data,'/')===false) {
		if ($data >= 1) {
			return round($data, 2).' '.gettext('sec');
		} else {
			$n=0; $d=0;
			ConvertToFraction($data, $n, $d);
			return $n.'/'.$d.' '.gettext('sec');
		}
	} else {
		return gettext('Bulb');
	}
}

//================================================================================================
// Reads one standard IFD entry
//================================================================================================
function read_entry(&$result,$in,$seek,$intel,$ifd_name,$globalOffset) {

	if (feof($in)) { // test to make sure we can still read.
		$result['Errors'] = $result['Errors']+1;
		return;
	}

	// 2 byte tag
	$tag = bin2hex(fread($in, 2));
	if ($intel == 1) $tag = intel2Moto($tag);
	$tag_name = lookup_tag($tag);

	// 2 byte datatype
	$type = bin2hex(fread($in, 2));
	if ($intel == 1) $type = intel2Moto($type);
	lookup_type($type, $size);

	if (strpos($tag_name, 'unknown:') !== false && strpos($type, 'error:') !== false) { // we have an error
		$result['Errors'] = $result['Errors']+1;
		return;
	}

	// 4 byte number of elements
	$count = bin2hex(fread($in, 4));
	if ($intel == 1) $count = intel2Moto($count);
	$bytesofdata = validSize($size*hexdec($count));

	// 4 byte value or pointer to value if larger than 4 bytes
	$value = fread( $in, 4 );

	if ($bytesofdata <= 4) {   // if datatype is 4 bytes or less, its the value
		$data = substr($value,0,$bytesofdata);
	} else if ($bytesofdata < 100000) {        // otherwise its a pointer to the value, so lets go get it
		$value = bin2hex($value);
		if ($intel == 1) $value = intel2Moto($value);
		$v = fseek($seek, $globalOffset+hexdec($value));  // offsets are from TIFF header which is 12 bytes from the start of the file
		if ($v == 0) {
			$data = fread($seek, $bytesofdata);
		} else if ($v == -1) {
			$result['Errors'] = $result['Errors']+1;
		}
	} else { // bytesofdata was too big, so the exif had an error
		$result['Errors'] = $result['Errors']+1;
		return;
	}
	if ($tag_name == 'MakerNote') { // if its a maker tag, we need to parse this specially
		$make = $result['IFD0']['Make'];
		if ($result['VerboseOutput'] == 1) {
			$result[$ifd_name]['MakerNote']['RawData'] = $data;
		}
		if (preg_match('/NIKON/i',$make)) {
			require_once(dirname(__FILE__).'/makers/nikon.php');
			parseNikon($data,$result);
			$result[$ifd_name]['KnownMaker'] = 1;
		} else if (preg_match('/OLYMPUS/i',$make)) {
			require_once(dirname(__FILE__).'/makers/olympus.php');
			parseOlympus($data,$result,$seek,$globalOffset);
			$result[$ifd_name]['KnownMaker'] = 1;
		} else if (preg_match('/Canon/i',$make)) {
			require_once(dirname(__FILE__).'/makers/canon.php');
			parseCanon($data,$result,$seek,$globalOffset);
			$result[$ifd_name]['KnownMaker'] = 1;
		} else if (preg_match('/FUJIFILM/i',$make)) {
			require_once(dirname(__FILE__).'/makers/fujifilm.php');
			parseFujifilm($data,$result);
			$result[$ifd_name]['KnownMaker'] = 1;
		} else if (preg_match('/SANYO/i',$make)) {
			require_once(dirname(__FILE__).'/makers/sanyo.php');
			parseSanyo($data,$result,$seek,$globalOffset);
			$result[$ifd_name]['KnownMaker'] = 1;
		} else if (preg_match('/Panasonic/i',$make)) {
			require_once(dirname(__FILE__).'/makers/panasonic.php');
			parsePanasonic($data,$result,$seek,$globalOffset);
			$result[$ifd_name]['KnownMaker'] = 1;
		} else {
			$result[$ifd_name]['KnownMaker'] = 0;
		}
	} else if ($tag_name == 'GPSInfoOffset') {
		require_once(dirname(__FILE__).'/makers/gps.php');
		$formated_data = formatData($type,$tag,$intel,$data);
		$result[$ifd_name]['GPSInfo'] = $formated_data;
		parseGPS($data,$result,$formated_data,$seek,$globalOffset);
	} else {
		// Format the data depending on the type and tag
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


//================================================================================================
// Pass in a file and this reads the EXIF data
//
// Usefull resources
// http:// www.ba.wakwak.com/~tsuruzoh/Computer/Digicams/exif-e.html
// http:// www.w3.org/Graphics/JPEG/jfif.txt
// http:// exif.org/
// http:// www.ozhiker.com/electronics/pjmt/library/list_contents.php4
// http:// www.ozhiker.com/electronics/pjmt/jpeg_info/makernotes.html
// http:// pel.sourceforge.net/
// http:// us2.php.net/manual/en/function.exif-read-data.php
//================================================================================================
function read_exif_data_raw($path,$verbose) {

	if ($path == '' || $path == 'none') return;

	$in = @fopen($path, 'rb'); // the b is for windows machines to open in binary mode
	$seek = @fopen($path, 'rb'); // There may be an elegant way to do this with one file handle.

	$globalOffset = 0;

	if (!isset($verbose)) $verbose=0;

	$result['VerboseOutput'] = $verbose;
	$result['Errors'] = 0;

	if (!$in || !$seek) {  // if the path was invalid, this error will catch it
		$result['Errors'] = 1;
		$result['Error'][$result['Errors']] = gettext('The file could not be found.');
		return $result;
	}

	$GLOBALS['exiferFileSize'] = filesize($path);

	// First 2 bytes of JPEG are 0xFFD8
	$data = bin2hex(fread( $in, 2 ));
	if ($data == 'ffd8') {
		$result['ValidJpeg'] = 1;
	} else {
		$result['ValidJpeg'] = 0;
		fseek($in, 0);
	}

	$result['ValidIPTCData'] = 0;
	$result['ValidJFIFData'] = 0;
	$result['ValidEXIFData'] = 0;
	$result['ValidAPP2Data'] = 0;
	$result['ValidCOMData'] = 0;

if ($result['ValidJpeg'] == 1) {

	// LOOP THROUGH MARKERS TILL ffe1 EXIF Marker
	$abortCount = 0;
	$header  =  '\0';
	while(!feof($in) && ++$abortCount < 200) {

		// Next 2 bytes are MARKER tag (0xFF**)
		$data = bin2hex(fread( $in, 2 ));
		$size = bin2hex(fread( $in, 2 ));

		if ($data == 'ffc0' || $data == 'ffd9') { // Start Of Frame Marker or End of Image Marker
			break;
		} else if ($data == 'ffe0') {             // JFIF Marker
			$result['ValidJFIFData'] = 1;
			$result['JFIF']['Size'] = hexdec($size);

			if (hexdec($size)-2 > 0) {
				$data = fread( $in, hexdec($size)-2);
				$result['JFIF']['Data'] = $data;
			}

			$result['JFIF']['Identifier'] = substr($data,0,5);;
			$result['JFIF']['ExtensionCode'] =  bin2hex(substr($data,6,1));

			$globalOffset+=hexdec($size)+2;

		} else if ($data == 'ffe1') {             // APP1 Marker : EXIF Metadata(TIFF IFD format) or JPEG Thumbnail or Adobe XMP
			$header = fread( $in, 6 );                      // Exif block starts with 'Exif\0\0' header
			if ($header == "Exif\0\0") {                    // EXIF Marker ?
				$result['ValidEXIFData'] = 1;
				$result['ValidAPP1Data'] = 1;
				$result['APP1']['Size'] = hexdec($size);
				break;
			} else {
				if (hexdec($size)-2 > 0) {
					$data = fread( $in, hexdec($size)-2-6); // skip XMP or Thumbnail data, and loop again
				}
				$globalOffset+=hexdec($size)+2;
			}

		} else if ($data == 'ffe2') {             // APP2 Marker : EXIF extension
			$result['ValidAPP2Data'] = 1;
			$result['APP2']['Size'] = hexdec($size);

			if (hexdec($size)-2 > 0) {
				$data = fread( $in, hexdec($size)-2);
				$result['APP2']['Data'] = $data ;
			}
			$globalOffset+=hexdec($size)+2;

		} else if ($data == 'ffed') {             // IPTC Marker
			$result['ValidIPTCData'] = 1;
			$result['IPTC']['Size'] = hexdec($size);

			if (hexdec($size)-2 > 0) {
				$data = fread( $in, hexdec($size)-2);
				$result['IPTC']['Data'] = $data ;
			}
			$globalOffset+=hexdec($size)+2;

		} else if ($data == 'fffe') {             // Comment extension Marker
			$result['ValidCOMData'] = 1;
			$result['COM']['Size'] = hexdec($size);

			if (hexdec($size)-2 > 0) {
				$data = fread( $in, hexdec($size)-2);
				$result['COM']['Data'] = $data ;
			}
			$globalOffset+=hexdec($size)+2;

		} else {                                  // unknown Marker
			if (hexdec($size)-2 > 0) {
				$data = fread( $in, hexdec($size)-2);
			}
			$globalOffset+=hexdec($size)+2;
		}
	}
	// END MARKER LOOP

	if ($header != "Exif\0\0") {
		fclose($in);
		fclose($seek);
		return $result;
	}


} // END IF ValidJpeg

	// Then theres a TIFF header with 2 bytes of endieness (II or MM)
	$header = fread( $in, 2 );
	if ($header==='II') {
		$intel=1;
		$result['Endien'] = 'Intel';
	} else if ($header==='MM') {
		$intel=0;
		$result['Endien'] = 'Motorola';
	} else {
		$intel=1; // not sure what the default should be, but this seems reasonable
		$result['Endien'] = 'Unknown';
	}

	// 2 bytes of 0x002a
	$tag = bin2hex(fread( $in, 2 ));

	// Then 4 bytes of offset to IFD0 (usually 8 which includes all 8 bytes of TIFF header)
	$offset = bin2hex(fread( $in, 4 ));
	if ($intel == 1) $offset = intel2Moto($offset);

	// Check for extremely large values here
	if (hexdec($offset) > 100000) {
			$result['ValidEXIFData'] = 0;
		fclose($in);
		fclose($seek);
		return $result;
	}

	if (hexdec($offset)>8) $unknown = fread( $in, hexdec($offset)-8); // fixed this bug in 1.3

	// add 12 to the offset to account for TIFF header
	if ($result['ValidJpeg'] == 1) {
		$globalOffset+=12;
	}


	//===========================================================
	// Start of IFD0
	$num = bin2hex(fread( $in, 2 ));
	if ($intel == 1) $num = intel2Moto($num);
	$num = hexdec($num);
	$result['IFD0NumTags'] = $num;

	if ($num<1000) { // 1000 entries is too much and is probably an error.
		for($i=0; $i<$num; $i++) {
			read_entry($result,$in,$seek,$intel,'IFD0',$globalOffset);
		}
	} else {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = 'Illegal size for IFD0';
	}

	// store offset to IFD1
	$offset = bin2hex(fread( $in, 4 ));
	if ($intel == 1) $offset = intel2Moto($offset);
	$result['IFD1Offset'] = hexdec($offset);

	// Check for SubIFD
	if (!isset($result['IFD0']['ExifOffset']) || $result['IFD0']['ExifOffset'] == 0) {
		fclose($in);
		fclose($seek);
		return $result;
	}

	// seek to SubIFD (Value of ExifOffset tag) above.
	$ExitOffset = $result['IFD0']['ExifOffset'];
	$v = fseek($in,$globalOffset+$ExitOffset);
	if ($v == -1) {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = gettext('Could not Find SubIFD');
	}

	//===========================================================
	// Start of SubIFD
	$num = bin2hex(fread( $in, 2 ));
	if ($intel == 1) $num = intel2Moto($num);
	$num = hexdec($num);
	$result['SubIFDNumTags'] = $num;

	if ($num<1000) { // 1000 entries is too much and is probably an error.
		for($i=0; $i<$num; $i++) {
			read_entry($result,$in,$seek,$intel,'SubIFD',$globalOffset);
		}
	} else {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = gettext('Illegal size for SubIFD');
	}

	// Add the 35mm equivalent focal length:
	if (isset($result['IFD0']['FocalLengthIn35mmFilm']) && !isset($result['SubIFD']['FocalLengthIn35mmFilm'])) { // found in the wrong place
		$result['SubIFD']['FocalLengthIn35mmFilm'] = $result['IFD0']['FocalLengthIn35mmFilm'];
	}
	if (!isset($result['SubIFD']['FocalLengthIn35mmFilm'])) {
		$result['SubIFD']['FocalLengthIn35mmFilm'] = get35mmEquivFocalLength($result);
	}

	// Check for IFD1
	if (!isset($result['IFD1Offset']) || $result['IFD1Offset'] == 0) {
		fclose($in);
		fclose($seek);
		return $result;
	}
	// seek to IFD1
	$v = fseek($in,$globalOffset+$result['IFD1Offset']);
	if ($v == -1) {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = gettext('Could not Find IFD1');
	}

	//===========================================================
	// Start of IFD1
	$num = bin2hex(fread( $in, 2 ));
	if ($intel == 1) $num = intel2Moto($num);
	$num = hexdec($num);
	$result['IFD1NumTags'] = $num;

	if ($num<1000) { // 1000 entries is too much and is probably an error.
		for($i=0; $i<$num; $i++) {
			read_entry($result,$in,$seek,$intel,'IFD1',$globalOffset);
		}
	} else {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = gettext('Illegal size for IFD1');
	}
	// If verbose output is on, include the thumbnail raw data...
	if ($result['VerboseOutput'] == 1 && $result['IFD1']['JpegIFOffset']>0 && $result['IFD1']['JpegIFByteCount']>0) {
			$v = fseek($seek,$globalOffset+$result['IFD1']['JpegIFOffset']);
			if ($v == 0) {
				$data = fread($seek, $result['IFD1']['JpegIFByteCount']);
			} else if ($v == -1) {
				$result['Errors'] = $result['Errors']+1;
			}
			$result['IFD1']['ThumbnailData'] = $data;
	}


	// Check for Interoperability IFD
	if (!isset($result['SubIFD']['ExifInteroperabilityOffset']) || $result['SubIFD']['ExifInteroperabilityOffset'] == 0) {
		fclose($in);
		fclose($seek);
		return $result;
	}
	// Seek to InteroperabilityIFD
	$v = fseek($in,$globalOffset+$result['SubIFD']['ExifInteroperabilityOffset']);
	if ($v == -1) {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = gettext('Could not Find InteroperabilityIFD');
	}

	//===========================================================
	// Start of InteroperabilityIFD
	$num = bin2hex(fread( $in, 2 ));
	if ($intel == 1) $num = intel2Moto($num);
	$num = hexdec($num);
	$result['InteroperabilityIFDNumTags'] = $num;

	if ($num<1000) { // 1000 entries is too much and is probably an error.
		for($i=0; $i<$num; $i++) {
			read_entry($result,$in,$seek,$intel,'InteroperabilityIFD',$globalOffset);
		}
	} else {
		$result['Errors'] = $result['Errors']+1;
		$result['Error'][$result['Errors']] = gettext('Illegal size for InteroperabilityIFD');
	}
	fclose($in);
	fclose($seek);
	return $result;
}

//=========================================================
// Converts a floating point number into a simple fraction.
//=========================================================
function ConvertToFraction($v, &$n, &$d) {
	if ($v == 0) {
		$n = 0;
		$d = 1;
		return;
	}
	for ($n=1; $n<100; $n++) {
		$v1 = 1/$v*$n;
		$d = round($v1, 0);
		if (abs($d - $v1) < 0.02) return; // within tolarance
	}
}

//================================================================================================
// Calculates the 35mm-equivalent focal length from the reported sensor resolution, by Tristan Harward.
//================================================================================================
function get35mmEquivFocalLength(&$result) {
	if (isset($result['SubIFD']['ExifImageWidth'])) {
		$width = $result['SubIFD']['ExifImageWidth'];
	} else {
		$width = 0;
	}
	if (isset($result['SubIFD']['FocalPlaneResolutionUnit'])) {
		$units = $result['SubIFD']['FocalPlaneResolutionUnit'];
	} else {
		$units = '';
	}
	$unitfactor = 1;
	switch ($units) {
		case 'Inch' :       $unitfactor = 25.4; break;
		case 'Centimeter' : $unitfactor = 10;   break;
		case 'No Unit' :    $unitfactor = 25.4; break;
		default :           $unitfactor = 25.4;
	}
	if (isset($result['SubIFD']['FocalPlaneXResolution'])) {
		$xres = $result['SubIFD']['FocalPlaneXResolution'];
	} else {
		$xres = '';
	}
	if (isset($result['SubIFD']['FocalLength'])) {
		$fl = $result['SubIFD']['FocalLength'];
	} else {
		$fl = 0;
	}

	if (($width != 0) && !empty($units) && !empty($xres) && !empty($fl) && !empty($width)) {
		$ccdwidth = ($width * $unitfactor) / $xres;
		$equivfl = $fl / $ccdwidth*36+0.5;
		return $equivfl;
	}
	return null;
}




// canon.php
//=================
// Looks up the name of the tag for the MakerNote (Depends on Manufacturer)
//====================================================================
function lookup_Canon_tag($tag) {

	switch($tag) {
		case "0001": $tag = "Settings 1";break;
		case "0004": $tag = "Settings 4";break;
		case "0006": $tag = "ImageType";break;
		case "0007": $tag = "FirmwareVersion";break;
		case "0008": $tag = "ImageNumber";break;
		case "0009": $tag = "OwnerName";break;
		case "000c": $tag = "CameraSerialNumber";break;
		case "000f": $tag = "CustomFunctions";break;
		case "0095": $tag = "LensInfo";break;

		default: $tag = "unknown:".$tag;break;
	}

	return $tag;
}

//=================
// Formats Data for the data type
//====================================================================
function formatCanonData($type,$tag,$intel,$data,$exif,&$result) {
	$place = 0;


	if($type=="ASCII") {
		$result = $data = str_replace("\0", "", $data);
	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = unRational($data,$type,$intel);

		if($tag=="0204") { //DigitalZoom
			$data=$data."x";
		}

	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {

		$data = rational($data,$type,$intel);
		$result['RAWDATA'] = $data;

		if($tag=="0001") { //first chunk
			$result['Bytes']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//0
			if ($result['Bytes'] != strlen($data) / 2) return $result; //Bad chunk
			$result['Macro']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//1
			switch($result['Macro']) {
				case 1: $result['Macro'] = gettext("Macro"); break;
				case 2: $result['Macro'] = gettext("Normal"); break;
				default: $result['Macro'] = gettext("Unknown");
			}
			$result['SelfTimer']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//2
			switch($result['SelfTimer']) {
				case 0: $result['SelfTimer'] = gettext("Off"); break;
				default: $result['SelfTimer'] .= gettext("/10s");
			}
			$result['Quality']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//3
			switch($result['Quality']) {
				case 2: $result['Quality'] = gettext("Normal"); break;
				case 3: $result['Quality'] = gettext("Fine"); break;
				case 5: $result['Quality'] = gettext("Superfine"); break;
				default: $result['Quality'] = gettext("Unknown");
			}
			$result['Flash']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//4
			switch($result['Flash']) {
				case 0: $result['Flash'] = gettext("Off"); break;
				case 1: $result['Flash'] = gettext("Auto"); break;
				case 2: $result['Flash'] = gettext("On"); break;
				case 3: $result['Flash'] = gettext("Red Eye Reduction"); break;
				case 4: $result['Flash'] = gettext("Slow Synchro"); break;
				case 5: $result['Flash'] = gettext("Auto + Red Eye Reduction"); break;
				case 6: $result['Flash'] = gettext("On + Red Eye Reduction"); break;
				case 16: $result['Flash'] = gettext("External Flash"); break;
				default: $result['Flash'] = gettext("Unknown");
			}
			$result['DriveMode']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//5
			switch($result['DriveMode']) {
				case 0: $result['DriveMode'] = gettext("Single/Timer"); break;
				case 1: $result['DriveMode'] = gettext("Continuous"); break;
				default: $result['DriveMode'] = gettext("Unknown");
			}
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//6
			$result['FocusMode']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//7
			switch($result['FocusMode']) {
				case 0: $result['FocusMode'] = gettext("One-Shot"); break;
				case 1: $result['FocusMode'] = gettext("AI Servo"); break;
				case 2: $result['FocusMode'] = gettext("AI Focus"); break;
				case 3: $result['FocusMode'] = gettext("Manual Focus"); break;
				case 4: $result['FocusMode'] = gettext("Single"); break;
				case 5: $result['FocusMode'] = gettext("Continuous"); break;
				case 6: $result['FocusMode'] = gettext("Manual Focus"); break;
				default: $result['FocusMode'] = gettext("Unknown");
			}
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//8
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//9
			$result['ImageSize']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//10
			switch($result['ImageSize']) {
				case 0: $result['ImageSize'] = gettext("Large"); break;
				case 1: $result['ImageSize'] = gettext("Medium"); break;
				case 2: $result['ImageSize'] = gettext("Small"); break;
				default: $result['ImageSize'] = gettext("Unknown");
			}
			$result['EasyShooting']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//11
			switch($result['EasyShooting']) {
				case 0: $result['EasyShooting'] = gettext("Full Auto"); break;
				case 1: $result['EasyShooting'] = gettext("Manual"); break;
				case 2: $result['EasyShooting'] = gettext("Landscape"); break;
				case 3: $result['EasyShooting'] = gettext("Fast Shutter"); break;
				case 4: $result['EasyShooting'] = gettext("Slow Shutter"); break;
				case 5: $result['EasyShooting'] = gettext("Night"); break;
				case 6: $result['EasyShooting'] = gettext("Black & White"); break;
				case 7: $result['EasyShooting'] = gettext("Sepia"); break;
				case 8: $result['EasyShooting'] = gettext("Portrait"); break;
				case 9: $result['EasyShooting'] = gettext("Sport"); break;
				case 10: $result['EasyShooting'] = gettext("Macro/Close-Up"); break;
				case 11: $result['EasyShooting'] = gettext("Pan Focus"); break;
				default: $result['EasyShooting'] = gettext("Unknown");
			}
			$result['DigitalZoom']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//12
			switch($result['DigitalZoom']) {
				case 0:
				case 65535: $result['DigitalZoom'] = gettext("None"); break;
				case 1: $result['DigitalZoom'] = gettext("2x"); break;
				case 2: $result['DigitalZoom'] = gettext("4x"); break;
				default: $result['DigitalZoom'] = gettext("Unknown");
			}
			$result['Contrast']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//13
			switch($result['Contrast']) {
				case 0: $result['Contrast'] = gettext("Normal"); break;
				case 1: $result['Contrast'] = gettext("High"); break;
				case 65535: $result['Contrast'] = gettext("Low"); break;
				default: $result['Contrast'] = gettext("Unknown");
			}
			$result['Saturation']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//14
			switch($result['Saturation']) {
				case 0: $result['Saturation'] = gettext("Normal"); break;
				case 1: $result['Saturation'] = gettext("High"); break;
				case 65535: $result['Saturation'] = gettext("Low"); break;
				default: $result['Saturation'] = gettext("Unknown");
			}
			$result['Sharpness']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//15
			switch($result['Sharpness']) {
				case 0: $result['Sharpness'] = gettext("Normal"); break;
				case 1: $result['Sharpness'] = gettext("High"); break;
				case 65535: $result['Sharpness'] = gettext("Low"); break;
				default: $result['Sharpness'] = gettext("Unknown");
			}
			$result['ISO']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//16
			switch($result['ISO']) {
				case 32767:
				case 0: $result['ISO'] = isset($exif['SubIFD']['ISOSpeedRatings'])
					? $exif['SubIFD']['ISOSpeedRatings'] : 'Unknown'; break;
				case 15: $result['ISO'] = gettext("Auto"); break;
				case 16: $result['ISO'] = gettext("50"); break;
				case 17: $result['ISO'] = gettext("100"); break;
				case 18: $result['ISO'] = gettext("200"); break;
				case 19: $result['ISO'] = gettext("400"); break;
				default: $result['ISO'] = gettext("Unknown");
			}
			$result['MeteringMode']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//17
			switch($result['MeteringMode']) {
				case 3: $result['MeteringMode'] = gettext("Evaluative"); break;
				case 4: $result['MeteringMode'] = gettext("Partial"); break;
				case 5: $result['MeteringMode'] = gettext("Center-weighted"); break;
				default: $result['MeteringMode'] = gettext("Unknown");
			}
			$result['FocusType']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//18
			switch($result['FocusType']) {
				case 0: $result['FocusType'] = gettext("Manual"); break;
				case 1: $result['FocusType'] = gettext("Auto"); break;
				case 3: $result['FocusType'] = gettext("Close-up (Macro)"); break;
				case 8: $result['FocusType'] = gettext("Locked (Pan Mode)"); break;
				default: $result['FocusType'] = gettext("Unknown");
			}
			$result['AFPointSelected']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//19
			switch($result['AFPointSelected']) {
				case 12288: $result['AFPointSelected'] = gettext("Manual Focus"); break;
				case 12289: $result['AFPointSelected'] = gettext("Auto Selected"); break;
				case 12290: $result['AFPointSelected'] = gettext("Right"); break;
				case 12291: $result['AFPointSelected'] = gettext("Center"); break;
				case 12292: $result['AFPointSelected'] = gettext("Left"); break;
				default: $result['AFPointSelected'] = gettext("Unknown");
			}
			$result['ExposureMode']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//20
			switch($result['ExposureMode']) {
				case 0: $result['ExposureMode'] = gettext("EasyShoot"); break;
				case 1: $result['ExposureMode'] = gettext("Program"); break;
				case 2: $result['ExposureMode'] = gettext("Tv"); break;
				case 3: $result['ExposureMode'] = gettext("Av"); break;
				case 4: $result['ExposureMode'] = gettext("Manual"); break;
				case 5: $result['ExposureMode'] = gettext("Auto-DEP"); break;
				default: $result['ExposureMode'] = gettext("Unknown");
			}
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//21
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//22
			$result['LongFocalLength']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//23
				$result['LongFocalLength'] .= " focal units";
			$result['ShortFocalLength']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//24
				$result['ShortFocalLength'] .= " focal units";
			$result['FocalUnits']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//25
				$result['FocalUnits'] .= " per mm";
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//26
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//27
			$result['FlashActivity']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//28
			switch($result['FlashActivity']) {
				case 0: $result['FlashActivity'] = gettext("Flash Did Not Fire"); break;
				case 1: $result['FlashActivity'] = gettext("Flash Fired"); break;
				default: $result['FlashActivity'] = gettext("Unknown");
			}
			$result['FlashDetails']=str_pad(base_convert(intel2Moto(substr($data,$place,4)), 16, 2), 16, "0", STR_PAD_LEFT);$place+=4;//29
				$flashDetails = array();
				if (substr($result['FlashDetails'], 1, 1) == 1) { $flashDetails[] = gettext('External E-TTL'); }
				if (substr($result['FlashDetails'], 2, 1) == 1) { $flashDetails[] = gettext('Internal Flash'); }
				if (substr($result['FlashDetails'], 4, 1) == 1) { $flashDetails[] = gettext('FP sync used'); }
				if (substr($result['FlashDetails'], 8, 1) == 1) { $flashDetails[] = gettext('2nd(rear)-curtain sync used'); }
				if (substr($result['FlashDetails'], 12, 1) == 1) { $flashDetails[] = gettext('1st curtain sync'); }
				$result['FlashDetails']=implode(",", $flashDetails);
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//30
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//31
			$anotherFocusMode=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//32
			if(strpos(strtoupper($exif['IFD0']['Model']), "G1") !== false) {
				switch($anotherFocusMode) {
					case 0: $result['FocusMode'] = gettext("Single"); break;
					case 1: $result['FocusMode'] = gettext("Continuous"); break;
					default: $result['FocusMode'] = gettext("Unknown");
				}
			}

		} else if($tag=="0004") { //second chunk
			$result['Bytes']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//0
			if ($result['Bytes'] != strlen($data) / 2) return $result; //Bad chunk
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//1
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//2
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//3
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//4
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//5
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//6
			$result['WhiteBalance']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//7
			switch($result['WhiteBalance']) {
				case 0: $result['WhiteBalance'] = gettext("Auto"); break;
				case 1: $result['WhiteBalance'] = gettext("Sunny"); break;
				case 2: $result['WhiteBalance'] = gettext("Cloudy"); break;
				case 3: $result['WhiteBalance'] = gettext("Tungsten"); break;
				case 4: $result['WhiteBalance'] = gettext("Fluorescent"); break;
				case 5: $result['WhiteBalance'] = gettext("Flash"); break;
				case 6: $result['WhiteBalance'] = gettext("Custom"); break;
				default: $result['WhiteBalance'] = gettext("Unknown");
			}
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//8
			$result['SequenceNumber']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//9
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//10
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//11
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//12
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//13
			$result['AFPointUsed']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//14
				$afPointUsed = array();
				if ($result['AFPointUsed'] & 0x0001) $afPointUsed[] = gettext("Right"); //bit 0
				if ($result['AFPointUsed'] & 0x0002) $afPointUsed[] = gettext("Center"); //bit 1
				if ($result['AFPointUsed'] & 0x0004) $afPointUsed[] = gettext("Left"); //bit 2
				if ($result['AFPointUsed'] & 0x0800) $afPointUsed[] = gettext("12"); //bit 12
				if ($result['AFPointUsed'] & 0x1000) $afPointUsed[] = gettext("13"); //bit 13
				if ($result['AFPointUsed'] & 0x2000) $afPointUsed[] = gettext("14"); //bit 14
				if ($result['AFPointUsed'] & 0x4000) $afPointUsed[] = gettext("15"); //bit 15
				$result['AFPointUsed'] = implode(",", $afPointUsed);
			$result['FlashBias']=intel2Moto(substr($data,$place,4));$place+=4;//15
			switch($result['FlashBias']) {
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
				default: $result['FlashBias'] = gettext("Unknown");
			}
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//16
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//17
			$result['Unknown']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//18
			$result['SubjectDistance']=hexdec(intel2Moto(substr($data,$place,4)));$place+=4;//19
				$result['SubjectDistance'] .= "/100 m";

		} else if($tag=="0008") { //image number
			if($intel==1) $data = intel2Moto($data);
			$data=hexdec($data);
			$result = round($data/10000)."-".$data%10000;
		} else if($tag=="000c") { //camera serial number
			if($intel==1) $data = intel2Moto($data);
			$data=hexdec($data);
			$result = "#".bin2hex(substr($data,0,16)).substr($data,16,16);
		}

	} else if($type=="UNDEFINED") {



	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
	}

	return $data;
}



//=================
// Cannon Special data section
// Useful:  http://www.burren.cx/david/canon.html
// http://www.burren.cx/david/canon.html
// http://www.ozhiker.com/electronics/pjmt/jpeg_info/canon_mn.html
//====================================================================
function parseCanon($block,&$result,$seek, $globalOffset) {
	$place = 0; //current place

	if($result['Endien']=="Intel") $intel=1;
	else $intel=0;

	$model = $result['IFD0']['Model'];

		//Get number of tags (2 bytes)
	$num = bin2hex(substr($block,$place,2));$place+=2;
	if($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);

	//loop thru all tags  Each field is 12 bytes
	for($i=0;$i<hexdec($num);$i++) {

			//2 byte tag
		$tag = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $tag = intel2Moto($tag);
		$tag_name = lookup_Canon_tag($tag);

			//2 byte type
		$type = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $type = intel2Moto($type);
		lookup_type($type,$size);

			//4 byte count of number of data units
		$count = bin2hex(substr($block,$place,4));$place+=4;
		if($intel==1) $count = intel2Moto($count);
		$bytesofdata = validSize($size*hexdec($count));
		if($bytesofdata<=0) {
			return; //if this value is 0 or less then we have read all the tags we can
		}

			//4 byte value of data or pointer to data
		$value = substr($block,$place,4);$place+=4;
		if($bytesofdata<=4) {
			$data = substr($value,0,$bytesofdata);
		} else {
			$value = bin2hex($value);
			if($intel==1) $value = intel2Moto($value);
			$v = fseek($seek,$globalOffset+hexdec($value));  //offsets are from TIFF header which is 12 bytes from the start of the file
			if (isset($GLOBALS['exiferFileSize'])) {
				$exiferFileSize = $GLOBALS['exiferFileSize'];
			} else {
				$exiferFileSize = 0;
			}
			if($v==0 && $bytesofdata < $exiferFileSize) {
				$data = fread($seek, $bytesofdata);
			} else if($v==-1) {
				$result['Errors'] = $result['Errors']++;
				$data = '';
			} else {
				$data = '';
			}
		}
		$result['SubIFD']['MakerNote'][$tag_name] = ''; // insure the index exists
		$formated_data = formatCanonData($type,$tag,$intel,$data,$result,$result['SubIFD']['MakerNote'][$tag_name]);

		if($result['VerboseOutput']==1) {
			//$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
			if($type=="URATIONAL" || $type=="SRATIONAL" || $type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
				$data = bin2hex($data);
				if($intel==1) $data = intel2Moto($data);
			}
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['RawData'] = $data;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Type'] = $type;
			$result['SubIFD']['MakerNote'][$tag_name."_Verbose"]['Bytes'] = $bytesofdata;
		} else {
			//$result['SubIFD']['MakerNote'][$tag_name] = $formated_data;
		}
	}
}


// fujifilm.php
//=================
// Looks up the name of the tag for the MakerNote (Depends on Manufacturer)
//====================================================================
function lookup_Fujifilm_tag($tag) {

	switch($tag) {
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
		case "1100": $tag = "ContinuousTakingBracket";break;
		case "1200": $tag = "Unknown";break;
		case "1300": $tag = "BlurWarning";break;
		case "1301": $tag = "FocusWarning";break;
		case "1302": $tag = "AEWarning";break;

		default: $tag = "unknown:".$tag;break;
	}

	return $tag;
}

//=================
// Formats Data for the data type
//====================================================================
function formatFujifilmData($type,$tag,$intel,$data) {

	if($type=="ASCII") {


	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = unRational($data,$type,$intel);

		if($tag=="1011") { //FlashStrength
			$data=$data." EV";
		}

	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
		$data =rational($data,$type,$intel);

		if($tag=="1001") { //Sharpness
			if($data == 1) $data = gettext("Soft");
			else if($data == 2) $data = gettext("Soft");
			else if($data == 3) $data = gettext("Normal");
			else if($data == 4) $data = gettext("Hard");
			else if($data == 5) $data = gettext("Hard");
			else $data = gettext("Unknown").": ".$data;
		}
		if($tag=="1002") { //WhiteBalance
			if($data == 0) $data = gettext("Auto");
			else if($data == 256) $data = gettext("Daylight");
			else if($data == 512) $data = gettext("Cloudy");
			else if($data == 768) $data = gettext("DaylightColor-fluorescence");
			else if($data == 769) $data = gettext("DaywhiteColor-fluorescence");
			else if($data == 770) $data = gettext("White-fluorescence");
			else if($data == 1024) $data = gettext("Incandescence");
			else if($data == 3840) $data = gettext("Custom");
			else $data = gettext("Unknown").": ".$data;
		}
		if($tag=="1003") { //Color
			if($data == 0) $data = gettext("Chroma Saturation Normal(STD)");
			else if($data == 256) $data = gettext("Chroma Saturation High");
			else if($data == 512) $data = gettext("Chroma Saturation Low(ORG)");
			else $data = gettext("Unknown: ").$data;
		}
		if($tag=="1004") { //Tone
			if($data == 0) $data = gettext("Contrast Normal(STD)");
			else if($data == 256) $data = gettext("Contrast High(HARD)");
			else if($data == 512) $data = gettext("Contrast Low(ORG)");
			else $data = gettext("Unknown: ").$data;
		}
		if($tag=="1010") { //FlashMode
			if($data == 0) $data = gettext("Auto");
			else if($data == 1) $data = gettext("On");
			else if($data == 2) $data = gettext("Off");
			else if($data == 3) $data = gettext("Red-Eye Reduction");
			else $data = gettext("Unknown: ").$data;
		}
		if($tag=="1020") { //Macro
			if($data == 0) $data = gettext("Off");
			else if($data == 1) $data = gettext("On");
			else $data = gettext("Unknown: ").$data;
		}
		if($tag=="1021") { //FocusMode
			if($data == 0) $data = gettext("Auto");
			else if($data == 1) $data = gettext("Manual");
			else $data = gettext("Unknown: ").$data;
		}
		if($tag=="1030") { //SlowSync
			if($data == 0) $data = gettext("Off");
			else if($data == 1) $data = gettext("On");
			else $data = gettext("Unknown: ").$data;
		}
		if($tag=="1031") { //PictureMode
			if($data == 0) $data = gettext("Auto");
			else if($data == 1) $data = gettext("Portrait");
			else if($data == 2) $data = gettext("Landscape");
			else if($data == 4) $data = gettext("Sports");
			else if($data == 5) $data = gettext("Night");
			else if($data == 6) $data = gettext("Program AE");
			else if($data == 256) $data = gettext("Aperture Priority AE");
			else if($data == 512) $data = gettext("Shutter Priority");
			else if($data == 768) $data = gettext("Manual Exposure");
			else $data = gettext("Unknown: ").$data;
		}
		if($tag=="1100") { //ContinuousTakingBracket
			if($data == 0) $data = gettext("Off");
			else if($data == 1) $data = gettext("On");
			else $data = gettext("Unknown: ").$data;
		}
		if($tag=="1300") { //BlurWarning
			if($data == 0) $data = gettext("No Warning");
			else if($data == 1) $data = gettext("Warning");
			else $data = gettext("Unknown: ").$data;
		}
		if($tag=="1301") { //FocusWarning
			if($data == 0) $data = gettext("Auto Focus Good");
			else if($data == 1) $data = gettext("Out of Focus");
			else $data = gettext("Unknown: ").$data;
		}
		if($tag=="1302") { //AEWarning
			if($data == 0) $data = gettext("AE Good");
			else if($data == 1) $data = gettext("Over Exposure");
			else $data = gettext("Unknown: ").$data;
		}
	} else if($type=="UNDEFINED") {



	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
	}

	return $data;
}



//=================
// Fujifilm Special data section
//====================================================================
function parseFujifilm($block,&$result) {

	//if($result['Endien']=="Intel") $intel=1;
	//else $intel=0;
	$intel=1;

	$model = $result['IFD0']['Model'];

	$place=8; //current place
	$offset=8;


	$num = bin2hex(substr($block,$place,4));$place+=4;
	if($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['Offset'] = hexdec($num);

		//Get number of tags (2 bytes)
	$num = bin2hex(substr($block,$place,2));$place+=2;
	if($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);

	//loop thru all tags  Each field is 12 bytes
	for($i=0;$i<hexdec($num);$i++) {

			//2 byte tag
		$tag = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $tag = intel2Moto($tag);
		$tag_name = lookup_Fujifilm_tag($tag);

			//2 byte type
		$type = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $type = intel2Moto($type);
		lookup_type($type,$size);

			//4 byte count of number of data units
		$count = bin2hex(substr($block,$place,4));$place+=4;
		if($intel==1) $count = intel2Moto($count);
		$bytesofdata = validSize($size*hexdec($count));

			//4 byte value of data or pointer to data
		$value = substr($block,$place,4);$place+=4;


		if($bytesofdata<=4) {
			$data = substr($value,0,$bytesofdata);
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


// gps.php
//=================
// Looks up the name of the tag
//====================================================================
function lookup_GPS_tag($tag) {

	switch($tag) {
		case "0000": $tag = "Version";break;
		case "0001": $tag = "Latitude Reference";break;			//north or south
		case "0002": $tag = "Latitude";break;					//dd mm.mm or dd mm ss
		case "0003": $tag = "Longitude Reference";break;		//east or west
		case "0004": $tag = "Longitude";break;					//dd mm.mm or dd mm ss
		case "0005": $tag = "Altitude Reference";break;			//sea level or below sea level
		case "0006": $tag = "Altitude";break;					//positive rational number
		case "0007": $tag = "Time";break;						//three positive rational numbers
		case "0008": $tag = "Satellite";break;					//text string up to 999 bytes long
		case "0009": $tag = "ReceiveStatus";break;				//in progress or interop
		case "000a": $tag = "MeasurementMode";break;			//2D or 3D
		case "000b": $tag = "MeasurementPrecision";break;		//positive rational number
		case "000c": $tag = "SpeedUnit";break;					//KPH, MPH, knots
		case "000d": $tag = "ReceiverSpeed";break;				//positive rational number
		case "000e": $tag = "MovementDirectionRef";break;		//true or magnetic north
		case "000f": $tag = "MovementDirection";break;			//positive rational number
		case "0010": $tag = "ImageDirectionRef";break;			//true or magnetic north
		case "0011": $tag = "ImageDirection";break;				//positive rational number
		case "0012": $tag = "GeodeticSurveyData";break;			//text string up to 999 bytes long
		case "0013": $tag = "DestLatitudeRef";break;			//north or south
		case "0014": $tag = "DestinationLatitude";break;		//three positive rational numbers
		case "0015": $tag = "DestLongitudeRef";break;			//east or west
		case "0016": $tag = "DestinationLongitude";break;		//three positive rational numbers
		case "0017": $tag = "DestBearingRef";break;				//true or magnetic north
		case "0018": $tag = "DestinationBearing";break;			//positive rational number
		case "0019": $tag = "DestDistanceRef";break;			//km, miles, knots
		case "001a": $tag = "DestinationDistance";break;		//positive rational number
		case "001b": $tag = "ProcessingMethod";break;
		case "001c": $tag = "AreaInformation";break;
		case "001d": $tag = "Datestamp";break;					//text string 10 bytes long
		case "001e": $tag = "DifferentialCorrection";break;		//integer in range 0-65535


		default: $tag = "unknown:".$tag;break;
	}

	return $tag;
}

//=================
// Formats Data for the data type
//====================================================================
function formatGPSData($type,$tag,$intel,$data) {

	if($type=="ASCII") {
		if($tag=="0001" || $tag=="0003"){ // Latitude Reference, Longitude Reference
			$data = ($data{1} == @$data{2} && @$data{1} == @$data{3}) ? $data{0} : $data;
		}

	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		if($tag=="0002" || $tag=="0004" || $tag=='0007') { //Latitude, Longitude, Time
			$datum = array();
			for ($i=0;$i<strlen($data);$i=$i+8) {
				array_push($datum,substr($data, $i, 8));
			}
			$hour = unRational($datum[0],$type,$intel);
			$minutes = unRational($datum[1],$type,$intel);
			$seconds = unRational($datum[2],$type,$intel);
			if($tag=="0007") { //Time
				$data = $hour.":".$minutes.":".$seconds;
			} else {
				$data = $hour+$minutes/60+$seconds/3600;
			}
		} else {
			$data = unRational($data,$type,$intel);

			if($tag=="0006"){
				$data .= 'm';
			}
		}
	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
		$data = rational($data,$type,$intel);


	} else if($type=="UNDEFINED") {



	} else if($type=="UBYTE") {
		$data = bin2hex($data);
		if($intel==1) $num = intel2Moto($data);


		if($tag=="0000") { // VersionID
			$data =  hexdec(substr($data,0,2)) .
												".". hexdec(substr($data,2,2)) .
												".". hexdec(substr($data,4,2)) .
												".". hexdec(substr($data,6,2));

		} else if($tag=="0005"){ // Altitude Reference
			if($data == "00000000"){ $data = '+'; }
			else if($data == "01000000"){ $data = '-'; }
		}

	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
	}

	return $data;
}


//=================
// GPS Special data section
// Useful websites
// http://drewnoakes.com/code/exif/sampleOutput.html
// http://www.geosnapper.com
//====================================================================
function parseGPS($block,&$result,$offset,$seek, $globalOffset) {

	if($result['Endien']=="Intel") $intel=1;
	else $intel=0;

	$v = fseek($seek,$globalOffset+$offset);  //offsets are from TIFF header which is 12 bytes from the start of the file
	if($v==-1) {
		$result['Errors'] = $result['Errors']++;
	}

	$num = bin2hex(fread( $seek, 2 ));
	if($intel==1) $num = intel2Moto($num);
	$num=hexdec($num);
	$result['GPS']['NumTags'] = $num;

	if ($num == 0) {
		return;
	}

	$block = fread( $seek, $num*12 );
	$place = 0;

	//loop thru all tags  Each field is 12 bytes
	for($i=0;$i<$num;$i++) {
			//2 byte tag
		$tag = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $tag = intel2Moto($tag);
		$tag_name = lookup_GPS_tag($tag);

		//2 byte datatype
		$type = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $type = intel2Moto($type);
		lookup_type($type,$size);

		//4 byte number of elements
		$count = bin2hex(substr($block,$place,4));$place+=4;
		if($intel==1) $count = intel2Moto($count);
		$bytesofdata = validSize($size*hexdec($count));

		//4 byte value or pointer to value if larger than 4 bytes
		$value = substr($block,$place,4);$place+=4;
		if($bytesofdata<=4) {
			$data = substr($value,0,$bytesofdata);
		} else {
			if (strpos('unknown',$tag_name) !== false || $bytesofdata > 1024) {
				$result['Errors'] = $result['Errors']++;
				$data = '';
				$type = 'ASCII';
			} else {
				$value = bin2hex($value);
				if($intel==1) $value = intel2Moto($value);
				$v = fseek($seek,$globalOffset+hexdec($value));  //offsets are from TIFF header which is 12 bytes from the start of the file
				if($v==0) {
					$data = fread($seek, $bytesofdata);
				} else {
					$result['Errors'] = $result['Errors']++;
					$data = '';
					$type = 'ASCII';
				}
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



// nikon.php
//=================
// Looks up the name of the tag for the MakerNote (Depends on Manufacturer)
//====================================================================
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
			case "000b": $tag = gettext("Converter");break;

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
			case "000c": $tag = "WB_RBLevels";break;
			case "000d": $tag = "ProgramShift";break;
			case "000e": $tag = "ExposureDifference";break;
			case "000f": $tag = "ISOSelection";break;
			case "0010": $tag = "DataDump";break;
			case "0011": $tag = "NikonPreview";break;
			case "0012": $tag = "FlashExposureComp";break;
			case "0013": $tag = "ISOSetting2";break;
			case "0014": $tag = "ColorBalanceA";break;
			case "0016": $tag = "ImageBoundary";break;
			case "0017": $tag = "FlashExposureComp";break;
			case "0018": $tag = "FlashExposureBracketValue";break;
			case "0019": $tag = "ExposureBracketValue";break;
			case "001a": $tag = "ImageProcessing";break;
			case "001b": $tag = "CropHiSpeed";break;
			case "001c": $tag = "ExposureTuning";break;
			case "001d": $tag = "SerialNumber";break;
			case "001e": $tag = "ColorSpace";break;
			case "001f": $tag = "VRInfo";break;
			case "0020": $tag = "ImageAuthentication";break;
			case "0022": $tag = "ActiveD-Lighting";break;
			case "0023": $tag = "PictureControl";break;
			case "0024": $tag = "WorldTime";break;
			case "0025": $tag = "ISOInfo";break;
			case "002a": $tag = "VignetteControl";break;
			case "002b": $tag = "DistortInfo";break;
			case "0080": $tag = "ImageAdjustment";break;
			case "0081": $tag = "ToneCompensation";break;
			case "0082": $tag = "Adapter";break;
			case "0083": $tag = "LensType";break;
			case "0084": $tag = "LensInfo";break;
			case "0085": $tag = "ManualFocusDistance";break;
			case "0086": $tag = "DigitalZoom";break;
			case "0087": $tag = "FlashUsed";break;
			case "0088": $tag = "AFFocusPosition";break;
			case "0089": $tag = "ShootingMode";break;
			case "008b": $tag = "LensFStops";break;
			case "008c": $tag = "ContrastCurve";break;
			case "008d": $tag = "ColorMode";break;
			case "0090": $tag = "LightType";break;
			case "0092": $tag = "HueAdjustment";break;
			case "0093": $tag = "NEFCompression";break;
			case "0094": $tag = "Saturation";break;
			case "0095": $tag = "NoiseReduction";break;
			case "009a": $tag = "SensorPixelSize";break;

			default: $tag = "unknown:".$tag;break;
		}
	}

	return $tag;
}


//=================
// Formats Data for the data type
//====================================================================
function formatNikonData($type,$tag,$intel,$model,$data) {
	switch ($type) {
		case "ASCII":
			break;	// do nothing!
		case "URATIONAL":
		case"SRATIONAL":
			switch ($tag) {
				case '0084':	// LensInfo
					$minFL = unRational(substr($data,0,8),$type,$intel);
					$maxFL = unRational(substr($data,8,8),$type,$intel);
					$minSP = unRational(substr($data,16,8),$type,$intel);
					$maxSP = unRational(substr($data,24,8),$type,$intel);
					if ($minFL == $maxFL) {
						$data = sprintf('%0.0f f/%0.0f',$minFL,$minSP);
					} elseif ($minSP == $maxSP) {
						$data = sprintf('%0.0f-%0.0fmm f/%0.1f',$minFL,$maxFL,$minSP);
					} else {
						$data = sprintf('%0.0f-%0.0fmm f/%0.1f-%0.1f',$minFL,$maxFL,$minSP,$maxSP);
					}
					break;
				case "0085":
					if ($model==1) $data=unRational($data,$type,$intel)." m";	//ManualFocusDistance
					break;
				case "0086":
					if ($model==1) $data=unRational($data,$type,$intel)."x";	//DigitalZoom
					break;
				case "000a":
					if ($model==0) $data=unRational($data,$type,$intel)."x";	//DigitalZoom
					break;
				default:
					$data=unRational($data,$type,$intel);
					break;
			}
			break;
		case "USHORT":
		case $type=="SSHORT":
		case $type=="ULONG":
		case $type=="SLONG":
		case $type=="FLOAT":
		case $type=="DOUBLE":
			$data = rational($data,$type,$intel);
			switch ($tag) {
				case "0003":
					if ($model==0) { //Quality
						switch ($data) {
							case 1:		$data = gettext("VGA Basic");	break;
							case 2:		$data = gettext("VGA Normal");	break;
							case 3:		$data = gettext("VGA Fine");	break;
							case 4:		$data = gettext("SXGA Basic");	break;
							case 5:		$data = gettext("SXGA Normal");	break;
							case 6:		$data = gettext("SXGA Fine");	break;
							default:	$data = gettext("Unknown").": ".$data;	break;
						}
					}
					break;
				case "0004":
					if ($model==0) { //Color
						switch ($data) {
							case 1:		$data = gettext("Color");	break;
							case 2:		$data = gettext("Monochrome");	break;
							default:	$data = gettext("Unknown").": ".$data;	break;
						}
					}
					break;
				case "0005":
					if ($model==0) { //Image Adjustment
						switch ($data) {
							case 0:		$data = gettext("Normal");	break;
							case 1:		$data = gettext("Bright+");	break;
							case 2:		$data = gettext("Bright-");	break;
							case 3:		$data = gettext("Contrast+");	break;
							case 4:		$data = gettext("Contrast-");	break;
							default:	$data = gettext("Unknown").": ".$data;	break;
						}
					}
					break;
				case "0006":
					if ($model==0) { //CCD Sensitivity
						switch($data) {
							case 0:		$data = "ISO-80";	break;
							case 2:		$data = "ISO-160";	break;
							case 4:		$data = "ISO-320";	break;
							case 5:		$data = "ISO-100";	break;
							default:	$data = gettext("Unknown").": ".$data;	break;
						}
					}
					break;
				case "0007":
					if ($model==0) { //White Balance
						switch ($data) {
							case 0:		 $data = gettext("Auto");	break;
							case 1:		$data = gettext("Preset");	break;
							case 2:		$data = gettext("Daylight");	break;
							case 3:		$data = gettext("Incandescence");	break;
							case 4:		$data = gettext("Fluorescence");	break;
							case 5:		$data = gettext("Cloudy");	break;
							case 6:		$data = gettext("SpeedLight");	break;
							default:	$data = gettext("Unknown").": ".$data;	break;
						}
					}
					break;
				case "000b":
					if ($model==0) { //Converter
						switch ($data) {
							case 0:	$data = gettext("None");	break;
							case 1:	$data = gettext("Fisheye");	break;
							default:	$data = gettext("Unknown").": ".$data;	break;
						}
					}
					break;
			}
			break;
		case "UNDEFINED":
			switch ($tag) {
				case "0001":
					if ($model==1) $data=$data/100;	break;	//Unknown (Version?)
					break;
				case "0088":
					if ($model==1) { //AF Focus Position
						$temp = gettext("Center");
						$data = bin2hex($data);
						$data = str_replace("01","Top",$data);
						$data = str_replace("02","Bottom",$data);
						$data = str_replace("03","Left",$data);
						$data = str_replace("04","Right",$data);
						$data = str_replace("00","",$data);
						if(strlen($data)==0) $data = $temp;
					}
					break;
			}
			break;
		default:
			$data = bin2hex($data);
			if($intel==1) $data = intel2Moto($data);
				switch ($tag) {
				case "0083":
					if ($model==1) { //Lens Type
						$data = hexdec(substr($data,0,2));
						switch ($data) {
							case 0: $data = gettext("AF non D"); break;
							case 1: $data = gettext("Manual"); break;
							case 2: $data = "AF-D or AF-S"; break;
							case 6: $data = "AF-D G"; break;
							case 10: $data = "AF-D VR"; break;
							case 14: $data = "AF-D G VR"; break;
							default: $data = gettext("Unknown").": ".$data; break;
						}
					}
					break;
				case "0087":
					if ($model==1) { //Flash type
						$data = hexdec(substr($data,0,2));
						if($data == 0) $data = gettext("Did Not Fire");
						else if($data == 4) $data = gettext("Unknown");
						else if($data == 7) $data = gettext("External");
						else if($data == 9) $data = gettext("On Camera");
						else $data = gettext("Unknown").": ".$data;
					}
					break;
			}
			break;
	}
	return $data;
}


//=================
// Nikon Special data section
//====================================================================
function parseNikon($block,&$result) {

	if($result['Endien']=="Intel") $intel=1;
	else $intel=0;

	$model = $result['IFD0']['Model'];

	//these 6 models start with "Nikon".  Other models dont.
	if($model=="E700\0" || $model=="E800\0" || $model=="E900\0" || $model=="E900S\0" || $model=="E910\0" || $model=="E950\0") {
		$place=8; //current place
		$model = 0;

		//Get number of tags (2 bytes)
		$num = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $num = intel2Moto($num);
		$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);

		//loop thru all tags  Each field is 12 bytes
		for($i=0;$i<hexdec($num);$i++) {
			//2 byte tag
			$tag = bin2hex(substr($block,$place,2));$place+=2;
			if($intel==1) $tag = intel2Moto($tag);
			$tag_name = lookup_Nikon_tag($tag, $model);

			//2 byte type
			$type = bin2hex(substr($block,$place,2));$place+=2;
			if($intel==1) $type = intel2Moto($type);
			lookup_type($type,$size);

			//4 byte count of number of data units
			$count = bin2hex(substr($block,$place,4));$place+=4;
			if($intel==1) $count = intel2Moto($count);
			$bytesofdata = validSize($size*hexdec($count));

			//4 byte value of data or pointer to data
			$value = substr($block,$place,4);$place+=4;

			//if tag is 0002 then its the ASCII value which we know is at 140 so calc offset
			//THIS HACK ONLY WORKS WITH EARLY NIKON MODELS
			if($tag=="0002") $offset = hexdec($value)-140;
			if($bytesofdata<=4) {
				$data = substr($value,0,$bytesofdata);
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
		$place=0;//current place
		$model = 1;

		$nikon = substr($block,$place,8);$place+=8;
		$endien = substr($block,$place,4);$place+=4;

		//2 bytes of 0x002a
		$tag = bin2hex(substr($block,$place,2));$place+=2;

		//Then 4 bytes of offset to IFD0 (usually 8 which includes all 8 bytes of TIFF header)
		$offset = bin2hex(substr($block,$place,4));$place+=4;
		if($intel==1) $offset = intel2Moto($offset);
		if(hexdec($offset)>8) $place+=$offset-8;

		//Get number of tags (2 bytes)
		$num = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $num = intel2Moto($num);

		//loop thru all tags  Each field is 12 bytes
		for($i=0;$i<hexdec($num);$i++) {
			//2 byte tag
			$tag = bin2hex(substr($block,$place,2));$place+=2;
			if($intel==1) $tag = intel2Moto($tag);
			$tag_name = lookup_Nikon_tag($tag, $model);

			//2 byte type
			$type = bin2hex(substr($block,$place,2));$place+=2;
			if($intel==1) $type = intel2Moto($type);
			lookup_type($type,$size);

			//4 byte count of number of data units
			$count = bin2hex(substr($block,$place,4));$place+=4;
			if($intel==1) $count = intel2Moto($count);
			$bytesofdata = validSize($size*hexdec($count));

			//4 byte value of data or pointer to data
			$value = substr($block,$place,4);$place+=4;

			if($bytesofdata<=4) {
				$data = substr($value,0,$bytesofdata);
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


// olympus.php
//=================
// Looks up the name of the tag for the MakerNote (Depends on Manufacturer)
//====================================================================
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

//=================
// Formats Data for the data type
//====================================================================
function formatOlympusData($type,$tag,$intel,$data) {
	if($type=="ASCII") {

	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = unRational($data,$type,$intel);
		if($intel==1) $data = intel2Moto($data);

		if($tag=="0204") { //DigitalZoom
			$data=$data."x";
		}
		if($tag=="0205") { //Unknown2

		}
	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
		$data = rational($data,$type,$intel);

		if($tag=="0201") { //JPEGQuality
			if($data == 1) $data = "SQ";
			else if($data == 2) $data = "HQ";
			else if($data == 3) $data = "SHQ";
			else $data = gettext("Unknown").": ".$data;
		}
		if($tag=="0202") { //Macro
			if($data == 0) $data = "Normal";
			else if($data == 1) $data = "Macro";
			else $data = gettext("Unknown").": ".$data;
		}
	} else if($type=="UNDEFINED") {

	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
	}

	return $data;
}



//==============================================================================
// Olympus Special data section
// - Updated by Zenphoto for new header tag in E-410/E-510/E-3 cameras. 2/24/2008
//==============================================================================
function parseOlympus($block, &$result, $seek, $globalOffset) {

	if($result['Endien']=="Intel") $intel = 1;
	else $intel = 0;

	$model = $result['IFD0']['Model'];

	// New header for new DSLRs - Check for it because the
	// number of bytes that count the IFD fields differ in each case.
	// Fixed by Zenphoto 2/24/08
	$new = false;
	if (substr($block, 0, 8) == "OLYMPUS\x00") {
		$new = true;
	} else if (substr($block, 0, 7) == "OLYMP\x00\x01"
		|| substr($block, 0, 7) == "OLYMP\x00\x02") {
		$new = false;
	} else {
		// Header does not match known Olympus headers.
		// This is not a valid OLYMPUS Makernote.
		return false;
	}

	// Offset of IFD entry after Olympus header.
	$place = 8;
	$offset = 8;

	// Get number of tags (1 or 2 bytes, depending on New or Old makernote)
	$countfieldbits = $new ? 1 : 2;
	// New makernote repeats 1-byte value twice, so increment $place by 2 in either case.
	$num = bin2hex(substr($block, $place, $countfieldbits)); $place += 2;
	if ($intel == 1) $num = intel2Moto($num);
	$ntags = hexdec($num);
	$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = $ntags;

	//loop thru all tags  Each field is 12 bytes
	for($i=0; $i < $ntags; $i++) {
		//2 byte tag
		$tag = bin2hex(substr($block, $place,2));
		$place += 2;
		if ($intel == 1) $tag = intel2Moto($tag);
		$tag_name = lookup_Olympus_tag($tag);

		//2 byte type
		$type = bin2hex(substr($block, $place,2));
		$place += 2;
		if ($intel == 1) $type = intel2Moto($type);
		lookup_type($type,$size);

		//4 byte count of number of data units
		$count = bin2hex(substr($block, $place,4));
		$place+=4;
		if ($intel == 1) $count = intel2Moto($count);
		$bytesofdata = $size * hexdec($count);

		//4 byte value of data or pointer to data
		$value = substr($block, $place,4);
		$place += 4;


		if ($bytesofdata <= 4) {
			$data = substr($value,0,$bytesofdata);
		} else {
			$value = bin2hex($value);
			if($intel==1) $value = intel2Moto($value);
			$v = fseek($seek,$globalOffset+hexdec($value));  //offsets are from TIFF header which is 12 bytes from the start of the file
			if(isset($GLOBALS['exiferFileSize']) && $v == 0 && $bytesofdata < $GLOBALS['exiferFileSize']) {
				$data = fread($seek, $bytesofdata);
			} else {
				$result['Errors'] = $result['Errors']++;
				$data = '';
			}
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



// panasonic.php
//=================
// Looks up the name of the tag for the MakerNote (Depends on Manufacturer)
//====================================================================
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

//=================
// Formats Data for the data type
//====================================================================
function formatPanasonicData($type,$tag,$intel,$data) {

	if($type=="ASCII") {

	} else if($type=="UBYTE" || $type=="SBYTE") {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
		$data=hexdec($data);

		if($tag=="000f") { //AFMode
			if($data == 256) $data = "9-area-focusing";
			else if($data == 16) $data = "1-area-focusing";
      else if($data == 4096) $data = gettext("3-area-focusing (High speed)");
			else if($data == 4112) $data = gettext("1-area-focusing (High speed)");
			else if($data == 16) $data = gettext("1-area-focusing");
			else if($data == 1) $data = gettext("Spot-focusing");
			else $data = "Unknown (".$data.")";
		}

	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = unRational($data,$type,$intel);

	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
		$data = rational($data,$type,$intel);

		if($tag=="0001") { //Image Quality
			if($data == 2) $data = gettext("High");
			else if($data == 3) $data = gettext("Standard");
			else if($data == 6) $data = gettext("Very High");
			else if($data == 7) $data = gettext("RAW");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="0003") { //White Balance
			if($data == 1) $data = gettext("Auto");
			else if($data == 2) $data = gettext("Daylight");
			else if($data == 3) $data = gettext("Cloudy");
			else if($data == 4) $data = gettext("Halogen");
			else if($data == 5) $data = gettext("Manual");
			else if($data == 8) $data = gettext("Flash");
			else if($data == 10) $data = gettext("Black and White");
			else if($data == 11) $data = gettext("Manual");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="0007") { //Focus Mode
			if($data == 1) $data = gettext("Auto");
			else if($data == 2) $data = gettext("Manual");
			else if($data == 4) $data = gettext("Auto, Focus button");
			else if($data == 5) $data = gettext("Auto, Continuous");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="001a") { //Image Stabilizer
			if($data == 2) $data = gettext("Mode 1");
			else if($data == 3) $data = gettext("Off");
			else if($data == 4) $data = gettext("Mode 2");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="001c") { //Macro mode
			if($data == 1) $data = gettext("On");
			else if($data == 2) $data = gettext("Off");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="001f") { //Shooting Mode
			if($data == 1) $data = gettext("Normal");
			else if($data == 2) $data = gettext("Portrait");
			else if($data == 3) $data = gettext("Scenery");
			else if($data == 4) $data = gettext("Sports");
			else if($data == 5) $data = gettext("Night Portrait");
			else if($data == 6) $data = gettext("Program");
			else if($data == 7) $data = gettext("Aperture Priority");
			else if($data == 8) $data = gettext("Shutter Priority");
			else if($data == 9) $data = gettext("Macro");
			else if($data == 11) $data = gettext("Manual");
			else if($data == 13) $data = gettext("Panning");
			else if($data == 14) $data = gettext("Simple");
			else if($data == 18) $data = gettext("Fireworks");
			else if($data == 19) $data = gettext("Party");
			else if($data == 20) $data = gettext("Snow");
			else if($data == 21) $data = gettext("Night Scenery");
			else if($data == 22) $data = gettext("Food");
			else if($data == 23) $data = gettext("Baby");
			else if($data == 27) $data = gettext("High Sensitivity");
			else if($data == 29) $data = gettext("Underwater");
			else if($data == 33) $data = gettext("Pet");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="0020") { //Audio
			if($data == 1) $data = gettext("Yes");
			else if($data == 2) $data = gettext("No");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="0023") { //White Balance Bias
			$data=$data." EV";
		}
		if($tag=="0024") { //Flash Bias
			$data = $data;
		}
		if($tag=="0028") { //Colour Effect
			if($data == 1) $data = gettext("Off");
			else if($data == 2) $data = gettext("Warm");
			else if($data == 3) $data = gettext("Cool");
			else if($data == 4) $data = gettext("Black and White");
			else if($data == 5) $data = gettext("Sepia");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="002a") { //Burst Mode
			if($data == 0) $data = gettext("Off");
			else if($data == 1) $data = gettext("Low/High Quality");
			else if($data == 2) $data = gettext("Infinite");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="002c") { //Contrast
			if($data == 0) $data = gettext("Standard");
			else if($data == 1) $data = gettext("Low");
			else if($data == 2) $data = gettext("High");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="002d") { //Noise Reduction
			if($data == 0) $data = gettext("Standard");
			else if($data == 1) $data = gettext("Low");
			else if($data == 2) $data = gettext("High");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="002e") { //Self Timer
			if($data == 1) $data = gettext("Off");
			else if($data == 2) $data = gettext("10s");
			else if($data == 3) $data = gettext("2s");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="0030") { //Rotation
			if($data == 1) $data = gettext("Horizontal (normal)");
			else if($data == 6) $data = gettext("Rotate 90 CW");
			else if($data == 8) $data = gettext("Rotate 270 CW");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="0032") { //Color Mode
			if($data == 0) $data = gettext("Normal");
			else if($data == 1) $data = gettext("Natural");
			else $data = gettext("Unknown")." (".$data.")";
		}
		if($tag=="0036") { //Travel Day
			$data=$data;
		}
	} else if($type=="UNDEFINED") {

	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
	}

	return $data;
}



//=================
// Panasonic Special data section
//====================================================================
function parsePanasonic($block,&$result) {

	//if($result['Endien']=="Intel") $intel=1;
	//else $intel=0;
	$intel=1;

	$model = $result['IFD0']['Model'];

	$place=8; //current place
	$offset=8;


	$num = bin2hex(substr($block,$place,4));$place+=4;
	if($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['Offset'] = hexdec($num);

	//Get number of tags (2 bytes)
	$num = bin2hex(substr($block,$place,2));$place+=2;
	if($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);

	//loop thru all tags  Each field is 12 bytes
	for($i=0;$i<hexdec($num);$i++) {

		//2 byte tag
		$tag = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $tag = intel2Moto($tag);
		$tag_name = lookup_Panasonic_tag($tag);

		//2 byte type
		$type = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $type = intel2Moto($type);
		lookup_type($type,$size);

		//4 byte count of number of data units
		$count = bin2hex(substr($block,$place,4));$place+=4;
		if($intel==1) $count = intel2Moto($count);
		$bytesofdata = validSize($size*hexdec($count));

		//4 byte value of data or pointer to data
		$value = substr($block,$place,4);$place+=4;


		if($bytesofdata<=4) {
			$data = substr($value,0,$bytesofdata);
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



// sanyo.php
//=================
// Looks up the name of the tag for the MakerNote (Depends on Manufacturer)
//====================================================================
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

//=================
// Formats Data for the data type
//====================================================================
function formatSanyoData($type,$tag,$intel,$data) {

	if($type=="ASCII") {


	} else if($type=="URATIONAL" || $type=="SRATIONAL") {
		$data = unRational($data,$type,$intel);

	} else if($type=="USHORT" || $type=="SSHORT" || $type=="ULONG" || $type=="SLONG" || $type=="FLOAT" || $type=="DOUBLE") {
		$data = rational($data,$type,$intel);

		if($tag=="0200") { //SpecialMode
			if($data == 0) $data = gettext("Normal");
			else $data = gettext("Unknown").": ".$data;
		}
		if($tag=="0201") { //Quality
			if($data == 2) $data = gettext("High");
			else $data = gettext("Unknown").": ".$data;
		}
		if($tag=="0202") { //Macro
			if($data == 0) $data = gettext("Normal");
			else $data = gettext("Unknown").": ".$data;
		}
	} else if($type=="UNDEFINED") {



	} else {
		$data = bin2hex($data);
		if($intel==1) $data = intel2Moto($data);
	}

	return $data;
}



//=================
// Sanyo Special data section
//====================================================================
function parseSanyo($block,&$result,$seek, $globalOffset) {

	if($result['Endien']=="Intel") $intel=1;
	else $intel=0;

	$model = $result['IFD0']['Model'];

	$place=8; //current place
	$offset=8;

		//Get number of tags (2 bytes)
	$num = bin2hex(substr($block,$place,2));$place+=2;
	if($intel==1) $num = intel2Moto($num);
	$result['SubIFD']['MakerNote']['MakerNoteNumTags'] = hexdec($num);

	//loop thru all tags  Each field is 12 bytes
	for($i=0;$i<hexdec($num);$i++) {

			//2 byte tag
		$tag = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $tag = intel2Moto($tag);
		$tag_name = lookup_Sanyo_tag($tag);

			//2 byte type
		$type = bin2hex(substr($block,$place,2));$place+=2;
		if($intel==1) $type = intel2Moto($type);
		lookup_type($type,$size);

			//4 byte count of number of data units
		$count = bin2hex(substr($block,$place,4));$place+=4;
		if($intel==1) $count = intel2Moto($count);
		$bytesofdata = validSize($size*hexdec($count));
			//4 byte value of data or pointer to data
		$value = substr($block,$place,4);$place+=4;


		if($bytesofdata<=4) {
			$data = substr($value,0,$bytesofdata);
		} else {
			$value = bin2hex($value);
			if($intel==1) $value = intel2Moto($value);
			$v = fseek($seek,$globalOffset+hexdec($value));  //offsets are from TIFF header which is 12 bytes from the start of the file
			if($tag!=0) {
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
