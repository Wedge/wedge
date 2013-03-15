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

// Wedge CAPTCHA: ledicons

class captcha_ledicons
{
	public $is_available = true;
	protected $font; // the font data will be in here
	protected $colours; // the hi/lo colour pairs for LEDs
	protected $image; // the internal image reference
	protected $leds = array(); // where we internally store the leds
	protected $pointwidth; // how wide the image is in LEDs
	protected $pointheight;
	protected $width; // size of the final image
	protected $height;
	protected $code;

	protected function init()
	{
		$this->load_font();
		$this->load_colours();

		// Figure out how big this is going to be. Start by getting the height we're using.
		$this->pointwidth = 0;
		$this->pointheight = array_rand($this->font['A']); // ['A'] contains an array of at least 5 => and 6 =>, so pick a random key -> random size
		for ($i = 0, $n = strlen($this->code); $i < $n; $i++)
			$this->pointwidth += count($this->font[substr($this->code, $i, 1)][$this->pointheight]);

		$this->width = ($this->pointwidth * 5) + 3;
		$this->height = ($this->pointheight * 6) + 2;
	}

	public function render($code)
	{
		$this->code = $code;
		$this->init();

		$this->image = imagecreate($this->width, $this->height);

		// Draw the background and a border
		$black = imagecolorallocate($this->image, 0, 0, 0);
		$grey = imagecolorallocate($this->image, 127, 127, 127);
		imagefilledrectangle($this->image, 0, 0, $this->width - 1, $this->height - 1, $grey);
		imagefilledrectangle($this->image, 1, 1, $this->width - 2, $this->height - 2, $black);

		// Now pick the colours. There aren't that many combinations that really work well though.
		$lit_colour = array_rand($this->colours);

		$this->create_led('lit', false, $this->colours[$lit_colour][0], $this->colours[$lit_colour][1]);
		$this->create_led('dull', false, array(63, 63, 63), array(100, 100, 100));

		// Now go do :)
		$xpos = 2;
		for ($i = 0, $n = strlen($this->code); $i < $n; $i++)
		{
			$thisletter = substr($this->code, $i, 1);
			foreach ($this->font[$thisletter][$this->pointheight] as $column)
			{
				for ($j = 0; $j < $this->pointheight; $j++)
				{
					$id = (pow(2, $j) & $column) ? 'lit' : 'dull';
					$this->paint_led($id, $xpos, ($this->pointheight - $j - 1) * 6 + 2);
				}
				$xpos += 5;
			}
		}

		return $this->image;
	}

	protected function create_led($id, $background = false, $lowlight_col = '', $highlight_col = '')
	{
		if (isset($this->leds[$id]))
			return false;

		if ($background === false)
			$background = array(0, 0, 0); // default of black

		$this->leds[$id] = imagecreate(4, 4); // indexed
		$ledbg = imagecolorallocate($this->leds[$id], $background[0], $background[1], $background[2]); // corners
		$ledlo = imagecolorallocate($this->leds[$id], $lowlight_col[0], $lowlight_col[1], $lowlight_col[2]); // background (main colour)
		$ledhi = imagecolorallocate($this->leds[$id], $highlight_col[0], $highlight_col[1], $highlight_col[2]); // highlight

		imagefilledrectangle($this->leds[$id], 0, 0, 3, 3, $ledlo);
		imagesetpixel($this->leds[$id], 0, 0, $ledbg); // black corners
		imagesetpixel($this->leds[$id], 0, 3, $ledbg);
		imagesetpixel($this->leds[$id], 3, 0, $ledbg);
		imagesetpixel($this->leds[$id], 3, 3, $ledbg);
		imagesetpixel($this->leds[$id], 1, 1, $ledhi); // highlight

		// Transfer colour palette to master image too
		$ledbg2 = imagecolorallocate($this->image, $background[0], $background[1], $background[2]); // corners
		$ledlo2 = imagecolorallocate($this->image, $lowlight_col[0], $lowlight_col[1], $lowlight_col[2]); // background (main colour)
		$ledhi2 = imagecolorallocate($this->image, $highlight_col[0], $highlight_col[1], $highlight_col[2]); // highlight

		return true;
	}

	protected function paint_led($id, $x, $y, $image = null)
	{
		imagecopy ($this->image, $this->leds[$id], $x, $y, 0, 0, 4, 4);
	}

	protected function load_colours()
	{
		$this->colours = array(
			'blue' => array(
				array(145, 145, 200),
				array(170, 170, 255),
			),
			'green' => array(
				array(64, 200, 64),
				array(64, 255, 64),
			),
			'red' => array(
				array(200, 64, 64),
				array(255, 64, 64),
			),
			'orange' => array(
				array(255, 115, 0),
				array(255, 150, 0),
			),
		);
	}

	protected function load_font()
	{
		$this->font = array(
			'A' => array(
				5 => array(0x0F, 0x14, 0x14, 0x0F, 0x00),
				6 => array(0x1F, 0x28, 0x28, 0x28, 0x1F, 0x00),
			),
			'B' => array(
				5 => array(0x1F, 0x15, 0x15, 0x0A, 0x00),
				6 => array(0x3F, 0x29, 0x29, 0x29, 0x16, 0x00),
			),
			'C' => array(
				5 => array(0x0E, 0x11, 0x11, 0x11, 0x00),
				6 => array(0x1E, 0x21, 0x21, 0x21, 0x12, 0x00),
			),
			'D' => array(
				5 => array(0x1F, 0x11, 0x0A, 0x04, 0x00),
				6 => array(0x3F, 0x21, 0x21, 0x12, 0x0C, 0x00),
			),
			'E' => array(
				5 => array(0x1F, 0x15, 0x15, 0x11, 0x00),
				6 => array(0x3F, 0x29, 0x29, 0x29, 0x21, 0x00),
			),
			'F' => array(
				5 => array(0x1F, 0x14, 0x14, 0x10, 0x00),
				6 => array(0x3F, 0x28, 0x28, 0x28, 0x20, 0x00),
			),
			'G' => array(
				5 => array(0x0E, 0x11, 0x15, 0x17, 0x00),
				6 => array(0x1E, 0x21, 0x25, 0x25, 0x16, 0x00),
			),
			'H' => array(
				5 => array(0x1F, 0x04, 0x04, 0x1F, 0x00),
				6 => array(0x3F, 0x08, 0x08, 0x08, 0x3F, 0x00),
			),
			'K' => array(
				5 => array(0x1F, 0x04, 0x0A, 0x11, 0x00),
				6 => array(0x3F, 0x08, 0x14, 0x22, 0x01, 0x00),
			),
			'M' => array(
				5 => array(0x1F, 0x08, 0x04, 0x08, 0x1F, 0x00),
				6 => array(0x3F, 0x10, 0x08, 0x10, 0x3F, 0x00),
			),
			'N' => array(
				5 => array(0x1F, 0x08, 0x04, 0x1F, 0x00),
				6 => array(0x3F, 0x10, 0x08, 0x04, 0x3F, 0x00),
			),
			'P' => array(
				5 => array(0x1F, 0x14, 0x14, 0x08, 0x00),
				6 => array(0x3F, 0x28, 0x28, 0x28, 0x10, 0x00),
			),
			'R' => array(
				5 => array(0x1F, 0x14, 0x16, 0x09, 0x00),
				6 => array(0x3F, 0x28, 0x2C, 0x2A, 0x11, 0x00),
			),
			'T' => array(
				5 => array(0x10, 0x1F, 0x10, 0x00),
				6 => array(0x20, 0x20, 0x3F, 0x20, 0x20, 0x00),
			),
			'U' => array(
				5 => array(0x1E, 0x01, 0x01, 0x1E, 0x01, 0x00),
				6 => array(0x3E, 0x01, 0x01, 0x01, 0x3E, 0x01, 0x00),
			),
			'V' => array(
				5 => array(0x1C, 0x02, 0x01, 0x02, 0x1C, 0x00),
				6 => array(0x3C, 0x02, 0x01, 0x02, 0x3C, 0x00),
			),
			'W' => array(
				5 => array(0x1E, 0x01, 0x06, 0x01, 0x1E, 0x00),
				6 => array(0x3E, 0x01, 0x0E, 0x01, 0x3E, 0x00),
			),
			'X' => array(
				5 => array(0x11, 0x0A, 0x04, 0x0A, 0x11, 0x00),
				6 => array(0x23, 0x14, 0x08, 0x14, 0x23, 0x00),
			),
			'Y' => array(
				5 => array(0x10, 0x08, 0x07, 0x08, 0x10, 0x00),
				6 => array(0x20, 0x10, 0x0F, 0x10, 0x20, 0x00),
			),
		);
	}

	public function __destruct()
	{
		// Make sure we clean up the main image when we're done.
		if (!empty($this->image))
			@imagedestroy($this->image);

		// Make sure we clean up any leds we created. Doing it this way means we have freedom later if we expand this to multi-colored or something.
		// Also note we don't iterate over the array itself. It's safer this way, trust me.
		if (!empty($this->leds))
		{
			$keys = array_keys($this->leds);
			for ($i = 0, $n = count($keys); $i < $n; $i++)
				@imagedestroy($this->leds[$keys[$i]]);
		}
	}
}
