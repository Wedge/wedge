<?php
/**********************************************************************************
* captcha-grid.php                                                                *
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

// Wedge CAPTCHA: grid

class captcha_grid
{
	public $is_available = true;
	protected $image; // the internal image reference

	public function render($code)
	{
		global $settings;

		$width = 230;
		$height = 36;
		$this->image = imagecreate($width, $height);

		$bg = imagecolorallocate($this->image, 0, 0, 0);
		$fg = imagecolorallocate($this->image, 255, 255, 255);

		imagefilledrectangle($this->image, 0, 0, $width, $height, $bg);

		$grid_size = mt_rand(5, 8);
		for ($y = 0; $y < $height; $y += $grid_size)
			imageline($this->image, 0, $y, $width - 1, $y, $fg);

		for ($x = 0; $x < $width; $x += $grid_size)
			imageline($this->image, $x, 0, $x, $height - 1, $fg);

		$size = 26;

		for ($i = 0, $n = strlen($code); $i < $n; $i++)
			imagettftext($this->image, $size, 0, $i * 36 + mt_rand(12, 15), $height - mt_rand(3, 5), $fg, $settings['default_theme_dir'] . '/fonts/Screenge.ttf', substr($code, $i, 1));

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