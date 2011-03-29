<?php
/**********************************************************************************
* VerificationCode.php                                                            *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC5                                         *
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

if (!defined('SMF'))
	die('Hacking attempt...');

define('WEDGE_NO_LOG', 1);

/*	Deals with showing the CAPTCHA.

	void VerificationCode()
		// Show the verification code or let it hear.

*/

// Show the verification code or let it hear.
function VerificationCode()
{
	global $modSettings, $context, $scripturl;

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
		showSubTemplate('verification_sound');
		hideChrome();

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

	// We all die one day...
	die();
}

?>