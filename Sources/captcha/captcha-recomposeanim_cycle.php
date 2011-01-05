<?php
/**********************************************************************************
* captcha-grid.php                                                                *
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

// Wedge CAPTCHA: recomposeanim_cycle

class captcha_recomposeanim_cycle extends captcha_recomposeanim
{
	public $is_available = true;

	public function render($code)
	{
		$this->code = $code;

		loadSource('Class-GifAnimator');
		$this->anim = new GIF_Animator();

		$this->init();

		// OK, it's showtime! Start by composing the image into place.
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
						imagesetpixel($this->image, $this->pixelmap[$pos][0], $this->pixelmap[$pos][1], $this->white);
					else
						break;
				}

				$this->anim->AddFrame($this->image, mt_rand(10, 30), $this->purple);
				imagedestroy($this->image);
			}
		}

		// Now we need to decompose it again.
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

?>