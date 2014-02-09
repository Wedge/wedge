<?php
/**
 * Displays a given image CAPTCHA.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

// Wedge CAPTCHA: recomposeanim_shadow

class captcha_recomposeanim_shadow extends captcha_recomposeanim
{
	public $is_available = false;

	protected function init()
	{
		$this->width = 350;
		$this->height = 76;
		$this->size = 40;

		$this->generate_pixelmap();

		$this->frames = mt_rand(20, 23);
		$this->px_per_frame = ceil(count($this->ordermap) / $this->frames);
	}

	protected function generate_pixelmap()
	{
		$image = imagecreate($this->width, $this->height);
		$black = imagecolorallocate($image, 0, 0, 0);
		$white = imagecolorallocate($image, 255, 255, 255);
		$purple = imagecolorallocate($image, 255, 0, 255); // this will, shortly, be our transparent color.
		imagefilledrectangle($image, 0, 0, $this->width, $this->height, $purple);

		$font = ASSETS_DIR . '/fonts/wecaptcha1.ttf';
		$this->pixelmap = array();
		$angle = mt_rand(0, 359);
		$bisect = ($angle % 45);
		if ($bisect < 5 || $bisect > 40)
			$angle += 10;
		$angle = deg2rad($angle);
		$shadowprints = 3;
		$length = 1;

		$xoffset = $length * cos($angle);
		$yoffset = $length * sin($angle);

		// For each letter...
		for ($i = 0, $n = strlen($this->code); $i < $n; $i++)
		{
			// ... get the letter, figure out its base position and render it. It's not strictly necessary but doesn't really hurt.
			$thisletter = substr($this->code, $i, 1);
			$letterpos[$i] = array($i * 57 + mt_rand(4, 8), $this->height - mt_rand(10, 12));

			// Then, render for no offset, then subsequent offsets.
			for ($j = 0; $j <= $shadowprints; $j++)
				imagettftext($image, $this->size, 0, $letterpos[$i][0] + round($j * $xoffset), $letterpos[$i][1] + ($j * $yoffset), -$white, $font, $thisletter);
				// ^^ Note the slightly odd - syntax above. This is because for some reason you have to do this to turn off antialiasing. NFI why.

			// Then lastly, erase the original letter.
			imagettftext($image, $this->size, 0, $letterpos[$i][0], $letterpos[$i][1], -$purple, $font, $thisletter);
		}
		imagecolortransparent($image, $purple);

		// OK, now we have our control image. Let's get the pixelmap. Sorry, server, this IS going to hurt.
		for ($y = 0; $y < $this->height; $y++)
			for ($x = 0; $x < $this->width; $x++)
				if (imagecolorat($image, $x, $y) == $white)
					$this->pixelmap[] = array($x, $y);
		imagedestroy($image);

		// Now add some noise
		$noise = $this->width * $this->height * 0.1 - count($this->pixelmap);
		for ($i = 0; $i < $noise; $i++)
		$this->pixelmap[] = array(mt_rand(0, $this->width - 1), mt_rand(0, $this->height - 1));

		$this->ordermap = range(0, count($this->pixelmap) - 1);
		shuffle($this->ordermap);
	}
}
