<?php
/**********************************************************************************
* captcha-simpleanim.php                                                          *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC4                                         *
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

// Wedge CAPTCHA: simpleanim

class captcha_simpleanim
{
	public $is_available = false;

	public function __construct()
	{
		global $modSettings;
		$this->is_available = !empty($modSettings['use_animated_captcha']);
	}

	public function render($code)
	{
		global $settings;

		loadSource('Class-GifAnimator');
		$anim = new GIF_Animator();

		$width = 230;
		$height = 36;
		$size = 26;

		for ($i = 0, $n = strlen($code); $i <= $n; $i++)
		{
			$image = imagecreate($width, $height);
			$black = imagecolorallocate($image, 0, 0, 0);
			$white = imagecolorallocate($image, 255, 255, 255);
			$purple = imagecolorallocate($image, 255, 0, 255); // this will, shortly, be our transparent colour.

			if ($i == 0)
			{
				// Don't care what there was before, make the first frame all black, no transparency.
				imagefilledrectangle($image, 0, 0, $width, $height, $black);
				$anim->AddFrame($image, 10);
				imagedestroy($image);
			}
			else
			{
				// Make the entire page purple, then make purple transparent so we get just the 'relevant' bits on our canvas
				imagefilledrectangle($image, 0, 0, $width, $height, $purple);
				imagecolortransparent($image, $purple);

				imagettftext($image, $size, 0, ($i-1) * 36 + mt_rand(12, 15), $height - mt_rand(3, 5), -$white, $settings['default_theme_dir'] . '/fonts/Screenge.ttf', substr($code, $i - 1, 1));
				// ^^ Note the slightly odd -$white syntax above. This is because for some reason you have to do this to turn off antialiasing.
				$anim->AddFrame($image, mt_rand(25,40), $purple);
				imagedestroy($image);
			}
		}

		$anim->AssembleFrames(1); // play through once, which due to the GIF format's oddities, will play twice in most cases.
		$anim->Output();
		exit;
	}
}

?>