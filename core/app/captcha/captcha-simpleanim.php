<?php
/**
 * Displays a given image CAPTCHA.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

// Wedge CAPTCHA: simpleanim

class captcha_simpleanim
{
	public $is_available = false;

	public function __construct()
	{
		global $settings;
		$this->is_available = !empty($settings['use_animated_captcha']);
	}

	public function render($code)
	{
		loadSource('Class-GifAnimator');
		$anim = new GIF_Animator();

		$width = 230;
		$height = 36;
		$size = 22;

		for ($i = 0, $n = strlen($code); $i <= $n; $i++)
		{
			$image = imagecreate($width, $height);
			$black = imagecolorallocate($image, 0, 0, 0);
			$white = imagecolorallocate($image, 255, 255, 255);
			$purple = imagecolorallocate($image, 255, 0, 255); // this will, shortly, be our transparent color.

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

				imagettftext($image, $size, 0, ($i-1) * 36 + mt_rand(12, 15), $height - mt_rand(3, 5), -$white, ASSETS_DIR . '/fonts/wecaptcha1.ttf', substr($code, $i - 1, 1));
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
