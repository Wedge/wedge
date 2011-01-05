<?php
/**********************************************************************************
* captcha-ledicons.php                                                            *
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

// Wedge CAPTCHA: stripes

class captcha_stripes
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

		// The 'foreground' colour must be declared first, so that when the 'background' colour is reapplied with text,
		// it has a colour index > 0 so turning antialiasing off actually works
		if (mt_rand(1, 100) % 2 == 0)
		{
			$fg = imagecolorallocate($this->image, 255, 255, 255);
			$bg = imagecolorallocate($this->image, 0, 0, 0);
		}
		else
		{
			$fg = imagecolorallocate($this->image, 0, 0, 0);
			$bg = imagecolorallocate($this->image, 255, 255, 255);
		}
		imagefilledrectangle($this->image, 0, 0, $width - 1, $height - 1, $bg);

		switch (mt_rand(1, 3))
		{
			case 1:
				$first_angle = 45;
				$second_angle = -45;
				$distance = mt_rand(4, 7);
				break;
			case 2:
				$first_angle = 60;
				$second_angle = -60;
				$distance = mt_rand(9, 11);
				break;
			case 3:
				$first_angle = 60;
				$second_angle = -30;
				$distance = mt_rand(9, 11);
				break;
		}

		$first_angle = deg2rad($first_angle);
		$second_angle = deg2rad($second_angle);

		// Draw the first angle slope
		$eff_width = round($height * tan($first_angle));
		for ($i = -$eff_width; $i < $width; $i += $distance)
			imageline($this->image, $i, 0, $i + $eff_width, $height, $fg);

		imagettftext($this->image, 50, 0, 20 + mt_rand(-10, 25), 70 + mt_rand(-10, 10), -$bg, $settings['default_theme_dir'] . '/fonts/Screenge.ttf', $code);

		// Draw the second angle slope
		$eff_width = abs(round($height * tan($second_angle)));
		for ($i = ($width + $eff_width); $i > -$eff_width; $i -= $distance)
			imageline($this->image, $i, 0, $i - $eff_width, $height, $fg);

		// Ta-dah
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