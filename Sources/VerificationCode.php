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

// Show the verification code or let it hear.
function VerificationCode()
{
	global $context, $txt;

	$verification_id = isset($_GET['vid']) ? $_GET['vid'] : '';
	$code = $verification_id && isset($_SESSION[$verification_id . '_vv'], $_SESSION[$verification_id . '_vv']['code']) ? $_SESSION[$verification_id . '_vv']['code'] : (isset($_SESSION['visual_verification_code']) ? $_SESSION['visual_verification_code'] : '');

	// Somehow no code was generated or the session was lost.
	if (empty($code))
		blankGif();

	// Show a window that will play the verification code.
	elseif (isset($_REQUEST['sound']))
	{
		loadLanguage(array('Help', 'Login'));
		loadTemplate('GenericPopup');
		wetem::hide();
		wetem::load('popup');

		$context['page_title'] = $txt['visual_verification_sound'];
		$context['verification_sound_href'] = '<URL>?action=verificationcode;rand=' . md5(mt_rand()) . ($verification_id ? ';vid=' . $verification_id : '') . ';format=.wav';

		$context['popup_contents'] = '
		<audio src="' . $context['verification_sound_href'] . '" controls id="audio">';

		if (we::is('ie'))
			$context['popup_contents'] .= '
			<object classid="clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95" type="audio/x-wav">
				<param name="AutoStart" value="1">
				<param name="FileName" value="' . $context['verification_sound_href'] . '">
			</object>';
		else
			$context['popup_contents'] .= '
			<object type="audio/x-wav" data="' . $context['verification_sound_href'] . '">
				<a href="' . $context['verification_sound_href'] . '" rel="nofollow">' . $context['verification_sound_href'] . '</a>
			</object>';

		$context['popup_contents'] .= '
		</audio>
		<br>
		<a href="#" onclick="$(\'#audio\')[0].play(); return false;">' . $txt['visual_verification_sound_again'] . '</a><br>
		<a href="' . $context['verification_sound_href'] . '" rel="nofollow">' . $txt['visual_verification_sound_direct'] . '</a>';

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
