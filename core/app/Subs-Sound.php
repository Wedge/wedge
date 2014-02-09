<?php
/**
 * This file carries the foundation of the non-visual (audible) CAPTCHA support.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Create a wave file that represents an audio version of an CAPTCHA string.
 *
 * Used by {@link VerificationCode()} in VerificationCode.php, this function attempts to process the CAPTCHA into a sound file spelling the letters of the CAPTCHA out. It attempts to use the user's language first, before defaulting back to English - it requires one wave file per letter in assets/sounds/ in the format of {letter}.{language}.wav.
 *
 * @param string $word The string containing the CAPTCHA.
 * @return mixed Return false in the event of failure; if successful the audio data in wave format is output.
 */
function createWaveFile($word)
{
	// Allow max 2 requests per 20 seconds.
	if (($ip = cache_get_data('wave_file/' . we::$user['ip'], 20)) > 2 || ($ip2 = cache_get_data('wave_file/' . we::$user['ip2'], 20)) > 2)
		exit(header('HTTP/1.1 400 Bad Request'));
	cache_put_data('wave_file/' . we::$user['ip'], $ip ? $ip + 1 : 1, 20);
	cache_put_data('wave_file/' . we::$user['ip2'], $ip2 ? $ip2 + 1 : 1, 20);

	// Fixate randomization for this word.
	$unpacked = unpack('n', md5($word . session_id()));
	mt_srand(end($unpacked));

	// Try to see if there's a sound font in the user's language.
	if (file_exists(ASSETS_DIR . '/sounds/a.' . we::$user['language'] . '.wav'))
		$sound_language = we::$user['language'];

	// English should be there.
	elseif (file_exists(ASSETS_DIR . '/sounds/a.english.wav'))
		$sound_language = 'english';

	// Guess not...
	else
		return false;

	// File names are in lower case so let's make sure that we are only using a lower case string
	$word = strtolower($word);

	// Loop through all letters of the word $word.
	$sound_word = '';
	for ($i = 0; $i < strlen($word); $i++)
	{
		$sound_letter = implode('', file(ASSETS_DIR . '/sounds/' . $word{$i} . '.' . $sound_language . '.wav'));
		if (strpos($sound_letter, 'data') === false)
			return false;

		$sound_letter = substr($sound_letter, strpos($sound_letter, 'data') + 8);
		switch ($word{$i} === 's' ? 0 : mt_rand(0, 2))
		{
			case 0:
				for ($j = 0, $n = strlen($sound_letter); $j < $n; $j++)
					for ($k = 0, $m = round(mt_rand(15, 25) / 10); $k < $m; $k++)
						$sound_word .= $word{$i} === 's' ? $sound_letter{$j} : chr(mt_rand(max(ord($sound_letter{$j}) - 1, 0x00), min(ord($sound_letter{$j}) + 1, 0xFF)));
			break;

			case 1:
				for ($j = 0, $n = strlen($sound_letter) - 1; $j < $n; $j += 2)
					$sound_word .= (mt_rand(0, 3) == 0 ? '' : $sound_letter{$j}) . (mt_rand(0, 3) === 0 ? $sound_letter{$j + 1} : $sound_letter{$j}) . (mt_rand(0, 3) === 0 ? $sound_letter{$j} : $sound_letter{$j + 1}) . $sound_letter{$j + 1} . (mt_rand(0, 3) == 0 ? $sound_letter{$j + 1} : '');
				$sound_word .= str_repeat($sound_letter{$n}, 2);
			break;

			case 2:
				$shift = 0;
				for ($j = 0, $n = strlen($sound_letter); $j < $n; $j++)
				{
					if (mt_rand(0, 10) === 0)
						$shift += mt_rand(-3, 3);
					for ($k = 0, $m = round(mt_rand(15, 25) / 10); $k < $m; $k++)
						$sound_word .= chr(min(max(ord($sound_letter{$j}) + $shift, 0x00), 0xFF));
				}
			break;

		}

		$sound_word .= str_repeat(chr(0x80), mt_rand(10000, 10500));
	}

	$data_size = strlen($sound_word);
	$file_size = $data_size + 0x24;
	$sample_rate = 16000;

	// Disable compression.
	ob_end_clean();
	header('Content-Encoding: none');

	// Output the wav.
	header('Content-type: audio/x-wav');
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('Content-Length: ' . ($file_size + 0x08));

	echo pack('nnVnnnnnnnnVVnnnnV', 0x5249, 0x4646, $file_size, 0x5741, 0x5645, 0x666D, 0x7420, 0x1000, 0x0000, 0x0100, 0x0100, $sample_rate, $sample_rate, 0x0100, 0x0800, 0x6461, 0x7461, $data_size), $sound_word;

	// Nothing more to add.
	exit;
}
