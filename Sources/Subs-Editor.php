<?php
/**********************************************************************************
* Subs-Editor.php                                                                 *
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

if (!defined('SMF'))
	die('Hacking attempt...');

/*	
	void theme_postbox(string message)
		- for compatibility - passes right through to the template_control_richedit function.

	void create_control_verification(&array suggestOptions)
		// !!

	array getMessageIcons(int board_id)
	- retrieves a list of message icons.
	- based on the settings, the array will either contain a list of default
	  message icons or a list of custom message icons retrieved from the
	  database.
	- the board_id is needed for the custom message icons (which can be set for
	  each board individually).

*/

function getMessageIcons($board_id)
{
	global $modSettings, $context, $txt, $settings;

	if (empty($modSettings['messageIcons_enable']))
	{
		loadLanguage('Post');

		$icons = array(
			array('value' => 'xx', 'name' => $txt['standard']),
			array('value' => 'thumbup', 'name' => $txt['thumbs_up']),
			array('value' => 'thumbdown', 'name' => $txt['thumbs_down']),
			array('value' => 'exclamation', 'name' => $txt['excamation_point']),
			array('value' => 'question', 'name' => $txt['question_mark']),
			array('value' => 'lamp', 'name' => $txt['lamp']),
			array('value' => 'smiley', 'name' => $txt['icon_smiley']),
			array('value' => 'angry', 'name' => $txt['icon_angry']),
			array('value' => 'cheesy', 'name' => $txt['icon_cheesy']),
			array('value' => 'grin', 'name' => $txt['icon_grin']),
			array('value' => 'sad', 'name' => $txt['icon_sad']),
			array('value' => 'wink', 'name' => $txt['icon_wink'])
		);

		foreach ($icons as $k => $dummy)
		{
			$icons[$k]['url'] = $settings['images_url'] . '/post/' . $dummy['value'] . '.gif';
			$icons[$k]['is_last'] = false;
		}
	}
	// Otherwise load the icons, and check we give the right image too...
	else
	{
		if (($temp = cache_get_data('posting_icons-' . $board_id, 480)) == null)
		{
			$request = wedb::query('
				SELECT title, filename
				FROM {db_prefix}message_icons
				WHERE id_board IN (0, {int:board_id})',
				array(
					'board_id' => $board_id,
				)
			);
			$icon_data = array();
			while ($row = wedb::fetch_assoc($request))
				$icon_data[] = $row;
			wedb::free_result($request);

			cache_put_data('posting_icons-' . $board_id, $icon_data, 480);
		}
		else
			$icon_data = $temp;

		$icons = array();
		foreach ($icon_data as $icon)
		{
			$icons[$icon['filename']] = array(
				'value' => $icon['filename'],
				'name' => $icon['title'],
				'url' => $settings[file_exists($settings['theme_dir'] . '/images/post/' . $icon['filename'] . '.gif') ? 'images_url' : 'default_images_url'] . '/post/' . $icon['filename'] . '.gif',
				'is_last' => false,
			);
		}
	}

	return array_values($icons);
}

// Compatibility function - used in 1.1 for showing a post box.
function theme_postbox($msg)
{
	global $context;

	return $context['postbox']->outputEditor();
}

// Create a anti-bot verification control?
function create_control_verification(&$verificationOptions, $do_test = false)
{
	global $txt, $modSettings, $options;
	global $context, $settings, $user_info, $scripturl;

	// First verification means we need to set up some bits...
	if (empty($context['controls']['verification']))
	{
		// The template
		loadTemplate('GenericControls');

		// Some javascript ma'am?
		if (!empty($verificationOptions['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($verificationOptions['override_visual'])))
			add_js_file($settings['default_theme_url'] . '/scripts/captcha.js');

		// Skip I, J, L, O, Q, S and Z.
		$context['standard_captcha_range'] = array_merge(range('A', 'H'), array('K', 'M', 'N', 'P', 'R'), range('T', 'Y'));
	}

	// Always have an ID.
	assert(isset($verificationOptions['id']));
	$isNew = !isset($context['controls']['verification'][$verificationOptions['id']]);

	// Log this into our collection.
	if ($isNew)
		$context['controls']['verification'][$verificationOptions['id']] = array(
			'id' => $verificationOptions['id'],
			'show_visual' => !empty($verificationOptions['override_visual']) || (!empty($modSettings['visual_verification_type']) && !isset($verificationOptions['override_visual'])),
			'number_questions' => isset($verificationOptions['override_qs']) ? $verificationOptions['override_qs'] : (!empty($modSettings['qa_verification_number']) ? $modSettings['qa_verification_number'] : 0),
			'max_errors' => isset($verificationOptions['max_errors']) ? $verificationOptions['max_errors'] : 3,
			'image_href' => $scripturl . '?action=verificationcode;vid=' . $verificationOptions['id'] . ';rand=' . md5(mt_rand()),
			'text_value' => '',
			'questions' => array(),
		);
	$thisVerification = &$context['controls']['verification'][$verificationOptions['id']];

	// Add javascript for the object.
	if ($context['controls']['verification'][$verificationOptions['id']]['show_visual'] && !WIRELESS)
		add_js('
	var verification' . $verificationOptions['id'] . 'Handle = new smfCaptcha("' . $thisVerification['image_href'] . '", "' . $verificationOptions['id'] . '", 1);');

	// Is there actually going to be anything?
	if (empty($thisVerification['show_visual']) && empty($thisVerification['number_questions']))
		return false;
	elseif (!$isNew && !$do_test)
		return true;

	// If we want questions do we have a cache of all the IDs?
	if (!empty($thisVerification['number_questions']) && empty($modSettings['question_id_cache']))
	{
		if (($modSettings['question_id_cache'] = cache_get_data('verificationQuestionIds', 300)) == null)
		{
			$request = wedb::query('
				SELECT id_comment
				FROM {db_prefix}log_comments
				WHERE comment_type = {string:ver_test}',
				array(
					'ver_test' => 'ver_test',
				)
			);
			$modSettings['question_id_cache'] = array();
			while ($row = wedb::fetch_assoc($request))
				$modSettings['question_id_cache'][] = $row['id_comment'];
			wedb::free_result($request);

			if (!empty($modSettings['cache_enable']))
				cache_put_data('verificationQuestionIds', $modSettings['question_id_cache'], 300);
		}
	}

	if (!isset($_SESSION[$verificationOptions['id'] . '_vv']))
		$_SESSION[$verificationOptions['id'] . '_vv'] = array();

	// Do we need to refresh the verification?
	if (!$do_test && (!empty($_SESSION[$verificationOptions['id'] . '_vv']['did_pass']) || empty($_SESSION[$verificationOptions['id'] . '_vv']['count']) || $_SESSION[$verificationOptions['id'] . '_vv']['count'] > 3) && empty($verificationOptions['dont_refresh']))
		$force_refresh = true;
	else
		$force_refresh = false;

	// This can also force a fresh, although unlikely.
	if (($thisVerification['show_visual'] && empty($_SESSION[$verificationOptions['id'] . '_vv']['code'])) || ($thisVerification['number_questions'] && empty($_SESSION[$verificationOptions['id'] . '_vv']['q'])))
		$force_refresh = true;

	$verification_errors = array();

	// Start with any testing.
	if ($do_test)
	{
		// This cannot happen!
		if (!isset($_SESSION[$verificationOptions['id'] . '_vv']['count']))
			fatal_lang_error('no_access', false);
		// ... nor this!
		if ($thisVerification['number_questions'] && (!isset($_SESSION[$verificationOptions['id'] . '_vv']['q']) || !isset($_REQUEST[$verificationOptions['id'] . '_vv']['q'])))
			fatal_lang_error('no_access', false);

		if ($thisVerification['show_visual'] && (empty($_REQUEST[$verificationOptions['id'] . '_vv']['code']) || empty($_SESSION[$verificationOptions['id'] . '_vv']['code']) || strtoupper($_REQUEST[$verificationOptions['id'] . '_vv']['code']) !== $_SESSION[$verificationOptions['id'] . '_vv']['code']))
			$verification_errors[] = 'wrong_verification_code';
		if ($thisVerification['number_questions'])
		{
			// Get the answers and see if they are all right!
			$request = wedb::query('
				SELECT id_comment, recipient_name AS answer
				FROM {db_prefix}log_comments
				WHERE comment_type = {string:ver_test}
					AND id_comment IN ({array_int:comment_ids})',
				array(
					'ver_test' => 'ver_test',
					'comment_ids' => $_SESSION[$verificationOptions['id'] . '_vv']['q'],
				)
			);
			$incorrectQuestions = array();
			while ($row = wedb::fetch_assoc($request))
			{
				if (empty($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]) || trim(westr::htmlspecialchars(strtolower($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]))) != strtolower($row['answer']))
					$incorrectQuestions[] = $row['id_comment'];
			}
			wedb::free_result($request);

			if (!empty($incorrectQuestions))
				$verification_errors[] = 'wrong_verification_answer';
		}
	}

	// Any errors means we refresh potentially.
	if (!empty($verification_errors))
	{
		if (empty($_SESSION[$verificationOptions['id'] . '_vv']['errors']))
			$_SESSION[$verificationOptions['id'] . '_vv']['errors'] = 0;
		// Too many errors?
		elseif ($_SESSION[$verificationOptions['id'] . '_vv']['errors'] > $thisVerification['max_errors'])
			$force_refresh = true;

		// Keep a track of these.
		$_SESSION[$verificationOptions['id'] . '_vv']['errors']++;
	}

	// Are we refreshing then?
	if ($force_refresh)
	{
		// Assume nothing went before.
		$_SESSION[$verificationOptions['id'] . '_vv']['count'] = 0;
		$_SESSION[$verificationOptions['id'] . '_vv']['errors'] = 0;
		$_SESSION[$verificationOptions['id'] . '_vv']['did_pass'] = false;
		$_SESSION[$verificationOptions['id'] . '_vv']['q'] = array();

		// Generating a new image.
		if ($thisVerification['show_visual'])
		{
			// Are we overriding the range?
			$character_range = !empty($verificationOptions['override_range']) ? $verificationOptions['override_range'] : $context['standard_captcha_range'];

			$_SESSION[$verificationOptions['id'] . '_vv']['code'] = '';
			for ($i = 0; $i < 6; $i++)
				$_SESSION[$verificationOptions['id'] . '_vv']['code'] .= $character_range[array_rand($character_range)];
		}

		// Getting some new questions?
		if ($thisVerification['number_questions'])
		{
			// Pick some random IDs
			$questionIDs = array();
			if ($thisVerification['number_questions'] == 1)
				$questionIDs[] = $modSettings['question_id_cache'][array_rand($modSettings['question_id_cache'], $thisVerification['number_questions'])];
			else
				foreach (array_rand($modSettings['question_id_cache'], $thisVerification['number_questions']) as $index)
					$questionIDs[] = $modSettings['question_id_cache'][$index];
		}
	}
	else
	{
		// Same questions as before.
		$questionIDs = !empty($_SESSION[$verificationOptions['id'] . '_vv']['q']) ? $_SESSION[$verificationOptions['id'] . '_vv']['q'] : array();
		$thisVerification['text_value'] = !empty($_REQUEST[$verificationOptions['id'] . '_vv']['code']) ? westr::htmlspecialchars($_REQUEST[$verificationOptions['id'] . '_vv']['code']) : '';
	}

	// Have we got some questions to load?
	if (!empty($questionIDs))
	{
		$request = wedb::query('
			SELECT id_comment, body AS question
			FROM {db_prefix}log_comments
			WHERE comment_type = {string:ver_test}
				AND id_comment IN ({array_int:comment_ids})',
			array(
				'ver_test' => 'ver_test',
				'comment_ids' => $questionIDs,
			)
		);
		$_SESSION[$verificationOptions['id'] . '_vv']['q'] = array();
		while ($row = wedb::fetch_assoc($request))
		{
			$thisVerification['questions'][] = array(
				'id' => $row['id_comment'],
				'q' => parse_bbc($row['question']),
				'is_error' => !empty($incorrectQuestions) && in_array($row['id_comment'], $incorrectQuestions),
				// Remember a previous submission?
				'a' => isset($_REQUEST[$verificationOptions['id'] . '_vv'], $_REQUEST[$verificationOptions['id'] . '_vv']['q'], $_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]) ? westr::htmlspecialchars($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$row['id_comment']]) : '',
			);
			$_SESSION[$verificationOptions['id'] . '_vv']['q'][] = $row['id_comment'];
		}
		wedb::free_result($request);
	}

	$_SESSION[$verificationOptions['id'] . '_vv']['count'] = empty($_SESSION[$verificationOptions['id'] . '_vv']['count']) ? 1 : $_SESSION[$verificationOptions['id'] . '_vv']['count'] + 1;

	// Return errors if we have them.
	if (!empty($verification_errors))
		return $verification_errors;
	// If we had a test that one, make a note.
	elseif ($do_test)
		$_SESSION[$verificationOptions['id'] . '_vv']['did_pass'] = true;

	// Say that everything went well chaps.
	return true;
}

?>