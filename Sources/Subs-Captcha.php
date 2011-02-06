<?php
/**********************************************************************************
* Subs-Captcha.php                                                                *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC5                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/
/**********************************************************************************
* TrueType fonts supplied by www.LarabieFonts.com                                 *
**********************************************************************************/

if (!defined('SMF'))
	die('Hacking attempt...');

/*	This file deals with producing CAPTCHA images.

	bool showCodeImage(string code)
		- show an image containing the visual verification code for registration.
		- requires the GD extension.
		- returns false if something goes wrong.
*/

// Create the image for the visual verification code.
function showCodeImage($code)
{
	global $settings, $user_info, $modSettings, $sourcedir, $context;

	// Determine what types are available.
	$context['captcha_types'] = loadCaptchaTypes();

	if (empty($context['captcha_types']))
		return false;

	// Special case to allow the admin center to show samples.
	$imageType = ($user_info['is_admin'] && isset($_GET['type']) && in_array($_GET['type'], $context['captcha_types'])) ? $_GET['type'] : $context['captcha_types'][array_rand($context['captcha_types'])];

	@ini_set('memory_limit', '90M');

	$captcha = new $imageType();

	$image = $captcha->render($code);
	// We already know GIF is available, we checked on install. Display, clean up, good night.
	header('Content-type: image/gif');
	imagegif($image);
	imagedestroy($image);
	die();
}

function loadCaptchaTypes()
{
	global $sourcedir;

	$captcha_types = array('none');
	if ($dh = scandir($sourcedir . '/captcha'))
	{
		foreach ($dh as $file)
		{
			if (!is_dir($file) && preg_match('~captcha-([A-Za-z\d_]+)\.php~', $file, $matches))
			{
				// Check this is definitely a valid API!
				$fp = fopen($sourcedir . '/captcha/' . $file, 'rb');
				$header = fread($fp, 4096);
				fclose($fp);

				if (strpos($header, '// Wedge CAPTCHA: ' . $matches[1]) !== false)
				{
					loadSource('captcha/captcha-' . $matches[1]);

					$class_name = 'captcha_' . $matches[1];
					$captcha = new $class_name();

					// No Support? NEXT!
					if (!$captcha->is_available)
						continue;

					$captcha_types[] = $class_name;
				}
			}
		}
	}

	return $captcha_types;
}
?>