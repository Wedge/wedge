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

// Wedge CAPTCHA: recomposeanim

class captcha_recomposeanim
{
	public $is_available = false;
	protected $anim;
	protected $pixelmap;
	protected $ordermap;
	protected $code;

	// Dimensions
	protected $width;
	protected $height;
	protected $size;
	protected $frames;
	protected $px_per_frame;

	// Image containers
	protected $image;
	protected $black;
	protected $white;
	protected $purple;

	public function __construct()
	{
		global $modSettings;
		$this->is_available = !empty($modSettings['use_animated_captcha']);
	}

	protected function init()
	{
		$this->width = 230;
		$this->height = 36;
		$this->size = 26;

		$this->generate_pixelmap();

		$this->frames = mt_rand(20, 23);
		$this->px_per_frame = ceil(count($this->ordermap) / $this->frames);
	}

	public function render($code)
	{
		$this->code = $code;

		loadSource('Class-GifAnimator');
		$this->anim = new GIF_Animator();

		$this->init();

		// OK, it's showtime!
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

		$this->anim->AssembleFrames(1); // play through once
		$this->anim->Output();
		exit;
	}

	protected function create_image()
	{
		$this->image = imagecreate($this->width, $this->height);
		$this->black = imagecolorallocate($this->image, 0, 0, 0);
		$this->white = imagecolorallocate($this->image, 255, 255, 255);
		$this->purple = imagecolorallocate($this->image, 255, 0, 255);
	}

	protected function background_frame()
	{
		// Don't care what there was before, make the first frame all black, no transparency.
		imagefilledrectangle($this->image, 0, 0, $this->width, $this->height, $this->black);
		$this->anim->AddFrame($this->image, 10);
		imagedestroy($this->image);
	}

	protected function normal_frame_bg()
	{
		// Make the entire page purple, then make purple transparent so we get just the 'relevant' bits on our canvas
		imagefilledrectangle($this->image, 0, 0, $this->width, $this->height, $this->purple);
		imagecolortransparent($this->image, $this->purple);
	}

	protected function generate_pixelmap()
	{
		global $settings;

		$image = imagecreate($this->width, $this->height);
		$black = imagecolorallocate($image, 0, 0, 0);
		$white = imagecolorallocate($image, 255, 255, 255);
		$purple = imagecolorallocate($image, 255, 0, 255); // this will, shortly, be our transparent colour.
		imagefilledrectangle($image, 0, 0, $this->width, $this->height, $purple);
		imagecolortransparent($image, $purple);

		$this->pixelmap = array();
		for ($i = 0, $n = strlen($this->code); $i < $n; $i++)
			imagettftext($image, $this->size, 0, $i * 36 + mt_rand(7, 12), $this->height - mt_rand(3, 5), -$white, $settings['default_theme_dir'] . '/fonts/Screenge.ttf', substr($this->code, $i, 1));
			// ^^ Note the slightly odd - syntax above. This is because for some reason you have to do this to turn off antialiasing.

		// OK, now we have our control image. Let's get the pixelmap. Sorry, server, this IS going to hurt.
		for ($y = 0; $y < $this->height; $y++)
			for ($x = 0; $x < $this->width; $x++)
				if (imagecolorat($image, $x, $y) == $white)
					$this->pixelmap[] = array($x, $y);
		imagedestroy($image);

		$this->ordermap = range(0, count($this->pixelmap) - 1);
		shuffle($this->ordermap);
	}
}

?>