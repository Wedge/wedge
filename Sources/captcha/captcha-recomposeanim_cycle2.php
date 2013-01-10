<?php
/**
 * Wedge
 *
 * Displays a given image CAPTCHA.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Wedge CAPTCHA: recomposeanim_cycle2

class captcha_recomposeanim_cycle2 extends captcha_recomposeanim
{
	public $is_available = false;

	public function render($code)
	{
		$this->code = $code;

		loadSource('Class-GifAnimator');
		$this->anim = new GIF_Animator();

		$this->init();

		$this->px_per_frame = ceil($this->px_per_frame * (mt_rand(5,8) / 10));

		// OK, it's showtime! Start by composing the image into place.
		$used_pixels = array();
		for ($i = 0; $i <= $this->frames; $i++)
		{
			$this->create_image();

			if ($i == 0)
				$this->background_frame();
			else
			{
				$this->normal_frame_bg();

				for ($n = 0; $n < $this->px_per_frame; $n++)
				{
					$pos = array_pop($this->ordermap);
					if ($pos !== null)
					{
						imagesetpixel($this->image, $this->pixelmap[$pos][0], $this->pixelmap[$pos][1], $this->white);
						$used_pixels[] = $this->pixelmap[$pos];
					}
					else
						break;
				}

				$this->anim->AddFrame($this->image, mt_rand(10, 30), $this->purple);
				imagedestroy($this->image);
			}
		}

		// Now we need to decompose it again. But this time we need to reuse only the list of pixels we added in the first place.
		$this->pixelmap = $used_pixels;
		unset($used_pixels);

		$this->ordermap = range(0, count($this->pixelmap) - 1);
		shuffle($this->ordermap);
		$this->frames = mt_rand(20, 23);
		$this->px_per_frame = ceil(count($this->ordermap) / $this->frames);

		for ($i = 0; $i < $this->frames; $i++)
		{
			$this->create_image();
			$this->normal_frame_bg();

			for ($n = 0; $n < $this->px_per_frame; $n++)
			{
				$pos = array_pop($this->ordermap);
				if ($pos !== null)
					imagesetpixel($this->image, $this->pixelmap[$pos][0], $this->pixelmap[$pos][1], $this->black);
				else
					break;
			}

			$this->anim->AddFrame($this->image, mt_rand(10, 30), $this->purple);
			imagedestroy($this->image);
		}

		$this->anim->AssembleFrames(); // play through repeatedly
		$this->anim->Output();
		exit;
	}
}
