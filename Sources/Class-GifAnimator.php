<?php
/**
 * Wedge
 *
 * Handles chaining together multiple GIF images to form a single animated image.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

/*
 This is a modified version of László Zsidi's GIFEncoder class (v2.06).
 http://www.phpclasses.org/package/3163-PHP-Generate-GIF-animations-from-a-set-of-GIF-images.html

 Specifically, since all the images animated are PHP generated, none of the original loader was required.
 The structure of the code has changed, as well as performance considerations and helper functions.
*/

class GIF_Animator
{
	protected $GIF = ''; // final animated GIF
	protected $buffer = array();
	protected $frame_num = -1;

	/**
	 * Adds a frame to the buffer held within the object instance.
	 *
	 * @param resource &$image The variable containing the image resource
	 * @param int $delay Time in hundredths of seconds the frame should remain visible for (e.g. 1 for 1/100 of a second, a.k.a. 10ms)
	 * @param int $transparency The index of the image that should be considered transparent, or -1 if that's not applicable
	 * @param bool $disposal Boolean as to disposal of frame (true = dispose to background color, false = restore to previous)
	 */
	public function AddFrame(&$image, $delay, $transparency = -1, $disposal = true)
	{
		$this->frame_num = count($this->buffer);
		$this->buffer[$this->frame_num] = array(
			'image' => $this->gifString($image),
			'delay' => $delay,
			'transparency' => $transparency,
			'disposal' => ($disposal ? 2 : 3), // 2 = restore to bgcol, 3 = restore to previous ;; these are bit numbers!
		);
	}

	/**
	 * Take the frames previously added in the buffer, and assemble into an animated GIF.
	 *
	 * @param int $playback Number of times to loop/play the animation, 0 for infinite looping.
	 *
	 * @return string Contains the raw binary string containing the animated GIF, suitable for output directly to the browser with a suitable header (effectively the contents of imagegif)
	 */
	public function AssembleFrames($playback = 0)
	{
		$playback = abs($playback); // just in case we accidentally do something banal with negative looping

		// Assemble the file headers
		$this->GIF = 'GIF89a';

		$global_byte = $this->byteAt(0, 10);
		if ( $global_byte & 0x80 ) {
			$cmap = 3 * (2 << ($global_byte & 0x07));

			$this->GIF .= substr ( $this->buffer[0]['image'], 6, 7);
			$this->GIF .= substr ( $this->buffer[0]['image'], 13, 3 * (2 << ($global_byte & 0x07)));
			$this->GIF .= "!\377\13NETSCAPE2.0\3\1" . $this->stringWord($playback) . "\0";
		}

		$global_length = 2 << ($this->byteAt(0, 10) && 0x07);

		$global_palette = substr($this->buffer[0]['image'], 13, 3 * $global_length);

		// Assemble the frames
		for ($i = 0; $i <= $this->frame_num; $i++)
		{
			// Cleans the code up a little if we're not farting around with $this->stuff everywhere
			$image = &$this->buffer[$i]['image'];
			$delay = &$this->buffer[$i]['delay'];
			$transparent_idx = &$this->buffer[$i]['transparency'];
			$disposal = &$this->buffer[$i]['disposal'] << 2; // the numbers in disposal are bit numbers

			$local_byte = $this->byteAt($i, 10); // this is to do with GCT

			$Locals_str = 13 + 3 * (2 << ($local_byte & 0x07));

			$Locals_end = strlen($image) - $Locals_str - 1;
			$Locals_tmp = substr($image, $Locals_str, $Locals_end);

			$local_length = 2 << ($local_byte & 0x07);

			$Locals_rgb = substr($image, 13, 3 * $local_length);

			$Locals_ext = "!\xF9\x04" . chr ($disposal + ($transparent_idx != -1 ? 1 : 0)) . $this->stringWord($delay) . ($transparent_idx != -1 ? chr($transparent_idx) : "\x0") . "\x0";

			switch ($Locals_tmp[0]) {
				case "!":
					$Locals_img = substr ( $Locals_tmp, 8, 10 );
					$Locals_tmp = substr ( $Locals_tmp, 18, strlen ( $Locals_tmp ) - 18 );
					break;
				case ",":
					$Locals_img = substr ( $Locals_tmp, 0, 10 );
					$Locals_tmp = substr ( $Locals_tmp, 10, strlen ( $Locals_tmp ) - 10 );
					break;
			}

			if ( $local_byte & 0x80 && $i != 0)
			{
				if ($global_palette === $Locals_rgb)
					$this->GIF .= ( $Locals_ext . $Locals_img . $Locals_tmp );
				else
				{
					$byte = ord($Locals_img[9]);
					$byte |= 0x80;
					$byte &= 0xF8;
					$byte |= ($global_length == $local_length ? ($global_byte & 0x07) : ($local_byte & 0x07));
					$Locals_img[9] = chr($byte);
					$this->GIF .= ($Locals_ext . $Locals_img . $Locals_rgb . $Locals_tmp);
				}
			}
			else
			{
				$this->GIF .= ($Locals_ext . $Locals_img . $Locals_tmp);
			}
		}

		// Assemble the footers
		$this->GIF .= ";";
	}

	/**
	 * Output the GIF either to stdout with headers, or to a file.
	 *
	 * @param string $filename The filename to save the animated GIF to. If not specified, the GIF will be output to stdout with appropriate HTTP header.
	 */
	public function Output($filename = null)
	{
		if ($filename === null)
		{
			header('Content-type: image/gif');
			echo $this->GIF;
		}
		else
		{
			$handle = fopen($filename, 'wb');
			fwrite($handle, $this->GIF);
			fclose($handle);
		}
	}

	/**
	 * Returns the byte value (as an int) at a given location in a given frame
	 *
	 * @param int $frame The frame number, between 0 and $this->frame_num inclusive
	 * @param int $pos The byte position, from 0 of the byte to return
	 */
	protected function byteAt($frame, $pos)
	{
		return ord($this->buffer[$frame]['image'][$pos]); // Note $string{n} syntax is deprecated as of PHP 5.3 so it must be done with [] instead.
	}

	/**
	 * When given an int, convert to the necessary binary string, Intel format (LSB first)
	 *
	 * @param int $int A word-length integer (16 bit) to be converted
	 */
	protected function stringWord ($int) {
		return chr($int & 0xFF) . chr(($int >> 8) & 0xFF);
	}

	/**
	 * Converts a raw image resource into a GIF formatted binary string for compilation into the final animation.
	 *
	 * @param resource &$image The variable containing the reference to the GD image.
	 */
	protected function gifString(&$image)
	{
		ob_start();
		imagegif($image);
		$string = ob_get_clean();
		return $string;
	}
}

?>