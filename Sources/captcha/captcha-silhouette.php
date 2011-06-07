<?php
/**
 * Wedge
 *
 * Displays a given image CAPTCHA.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

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