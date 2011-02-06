<?php
/**********************************************************************************
* captcha-ledicons.php                                                            *
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

// Wedge CAPTCHA: silhouette

class captcha_silhouette
{
	public $is_available = true;
	protected $image; // the internal image reference

	public function render($code)
	{
		global $settings;

		// Create a background
		$width = 300;
		$height = 100;
		$this->image = imagecreate($width, $height);
		$black = imagecolorallocate($this->image, 0, 0, 0);
		$white = imagecolorallocate($this->image, 255, 255, 255);
		imagefilledrectangle($this->image, 0, 0, $width - 1, $height - 1, $black);

		// Create some noise on said background
		$noise_factor = 12; // percent
		$noisedots = round($width * $height * ($noise_factor / 100));
		$drawwidth = $width - 1;
		$drawheight = $height - 1;
		for ($i = 0; $i < $noisedots; $i++)
		{
			$x = mt_rand(0, $drawwidth);
			$y = mt_rand(0, $drawheight);
			imagesetpixel($this->image, $x, $y, $white);
		}

		for ($i = 0, $n = strlen($code); $i < $n; $i++)
			imagettftext($this->image, 50, 0, $i * 45 + mt_rand(18, 22), mt_rand(60, 80), $black, $settings['default_theme_dir'] . '/fonts/Screenge.ttf', substr($code, $i, 1));

		return $this->image;
	}

	public function __destruct()
	{
		// Make sure we clean up the main image when we're done.
		if (!empty($this->image))
			@imagedestroy($this->image);
	}
}

?>