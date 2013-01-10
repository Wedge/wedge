<?php
/**
 * Wedge
 *
 * Deals with showing the CAPTCHA.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

define('WEDGE_NO_LOG', 1);

// Show the verification code or let it hear.
function VerificationCode()
{
	global $settings, $context, $scripturl;

	$verification_id = isset($_GET['vid']) ? $_GET['vid'] : '';
	$code = $verification_id && isset($_SESSION[$verification_id . '_vv'], $_SESSION[$verification_id . '_vv']['code']) ? $_SESSION[$verification_id . '_vv']['code'] : (isset($_SESSION['visual_verification_code']) ? $_SESSION['visual_verification_code'] : '');

	// Somehow no code was generated or the session was lost.
	if (empty($code))
		blankGif();

	// Show a window that will play the verification code.
	elseif (isset($_REQUEST['sound']))
	{
		loadLanguage('Login');
		loadTemplate('Register');

		$context['verification_sound_href'] = $scripturl . '?action=verificationcode;rand=' . md5(mt_rand()) . ($verification_id ? ';vid=' . $verification_id : '') . ';format=.wav';
		wetem::load('verification_sound');
		wetem::hide();

		obExit();
	}

	// Try the nice code using GD.
	elseif (empty($_REQUEST['format']))
	{
		loadSource('Subs-Captcha');

		if (!showCodeImage($code))
			header('HTTP/1.1 400 Bad Request');
		// You must be up to no good.
		else
			blankGif();
	}

	elseif ($_REQUEST['format'] === '.wav')
	{
		loadSource('Subs-Sound');

		if (!createWaveFile($code))
			header('HTTP/1.1 400 Bad Request');
	}

	// And we're done.
	exit;
}

// Output a 1x1 transparent GIF image and end execution.
function blankGif()
{
	header('Content-Type: image/gif');
	exit("\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\x00\x00\x00\x21\xF9\x04\x01\x00\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3B");
}
