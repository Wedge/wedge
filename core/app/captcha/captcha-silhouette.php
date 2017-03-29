<?php
/**
 * Displays a given image CAPTCHA.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

// Wedge CAPTCHA: silhouette

class captcha_silhouette
{
	public $is_available = true;
	protected $image; // the internal image reference

	public function render($code)
	{
		// Create a background
		$width = 500;
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
			imagettftext($this->image, 50, 0, $i * 80 + mt_rand(18, 22), mt_rand(70, 90), $black, ASSETS_DIR . '/fonts/wecaptcha1.ttf', substr($code, $i, 1));

		return $this->image;
	}

	public function __destruct()
	{
		// Make sure we clean up the main image when we're done.
		if (is_resource($this->image))
			@imagedestroy($this->image);
	}
}
