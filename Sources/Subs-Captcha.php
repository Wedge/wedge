<?php
/**
 * Wedge
 *
 * Deals with instancing the specific CAPTCHA image class that is to be used, and controlling its output.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

/* TrueType fonts supplied by www.LarabieFonts.com */

if (!defined('WEDGE'))
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
	global $theme, $user_info, $settings, $sourcedir, $context;

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

	$captcha_types = array();
	if ($dh = scandir($sourcedir . '/captcha'))
	{
		foreach ($dh as $file)
		{
			if (!is_dir($file) && preg_match('~captcha-([A-Za-z\d_]+)\.php$~', $file, $matches))
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

	// Maybe a plugin wants to add some CAPTCHA types? If they're doing that, here's a hook. The plugin sources attached to this hook
	// probably should be individual files containing the receiver for this hook, plus the class itself, to minimise loading effort.
	call_hook('add_captcha', array(&$captcha_types));

	return $captcha_types;
}

?>