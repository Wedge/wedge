<?php
/**
 * Displays a given image CAPTCHA.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

// Wedge CAPTCHA: grid

class captcha_grid
{
	public $is_available = true;
	protected $image; // the internal image reference

	public function render($code)
	{
		global $theme;

		$width = 380;
		$height = 70;
		$this->image = imagecreate($width, $height);

		$bg = imagecolorallocate($this->image, 0, 0, 0);
		$fg = imagecolorallocate($this->image, 255, 255, 255);

		imagefilledrectangle($this->image, 0, 0, $width, $height, $bg);

		$grid_size = mt_rand(5, 8);
		for ($y = 0; $y < $height; $y += $grid_size)
			imageline($this->image, 0, $y, $width - 1, $y, $fg);

		for ($x = 0; $x < $width; $x += $grid_size)
			imageline($this->image, $x, 0, $x, $height - 1, $fg);

		$size = 40;

		for ($i = 0, $n = strlen($code); $i < $n; $i++)
			imagettftext($this->image, $size, 0, $i * 60 + mt_rand(16, 22), $height - mt_rand(3, 5), $fg, $theme['default_theme_dir'] . '/fonts/wecaptcha1.ttf', substr($code, $i, 1));

		return $this->image;
	}

	public function __destruct()
	{
		// Make sure we clean up the main image when we're done.
		if (!empty($this->image))
			@imagedestroy($this->image);
	}
}
