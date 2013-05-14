<?php
/**
 * Wedge
 *
 * Handles the generation and validation of visual verifications and CAPTCHAs, as well as delegating image/sound generation elsewhere.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*
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
	global $context, $txt, $theme;

	if (($temp = cache_get_data('posting_icons-' . $board_id, 480)) == null)
	{
		$request = wesql::query('
			SELECT title, filename
			FROM {db_prefix}message_icons
			WHERE id_board IN (0, {int:board_id})',
			array(
				'board_id' => $board_id,
			)
		);
		$icon_data = array();
		while ($row = wesql::fetch_assoc($request))
			$icon_data[] = $row;
		wesql::free_result($request);

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
			'url' => $theme[file_exists($theme['theme_dir'] . '/images/post/' . $icon['filename'] . '.gif') ? 'images_url' : 'default_images_url'] . '/post/' . $icon['filename'] . '.gif',
		);
	}

	return array_values($icons);
}

// Create a anti-bot verification control?
function create_control_verification(&$verificationOptions, $do_test = false)
{
	global $txt, $settings, $options, $context;

	// First verification means we need to set up some bits...
	if (empty($context['controls']['verification']))
	{
		// The template
		loadTemplate('GenericControls');

		// Some javascript ma'am?
		if (!empty($verificationOptions['override_visual']) || (!empty($settings['use_captcha_images']) && !isset($verificationOptions['override_visual'])))
			add_js_file('scripts/captcha.js');

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
			'show_visual' => !empty($verificationOptions['override_visual']) || (!empty($settings['use_captcha_images']) && !isset($verificationOptions['override_visual'])),
			'number_questions' => isset($verificationOptions['override_qs']) ? $verificationOptions['override_qs'] : (!empty($settings['qa_verification_number']) ? $settings['qa_verification_number'] : 0),
			'max_errors' => isset($verificationOptions['max_errors']) ? $verificationOptions['max_errors'] : 3,
			'image_href' => '<URL>?action=verificationcode;vid=' . $verificationOptions['id'] . ';rand=' . md5(mt_rand()),
			'text_value' => '',
			'questions' => array(),
			'do_empty_field' => empty($verificationsOptions['no_empty_field']),
			'other_vv' => array(),
		);
	$thisVerification =& $context['controls']['verification'][$verificationOptions['id']];

	// Add javascript for the object.
	if ($thisVerification['show_visual'])
		add_js_unique('
	$(\'.vv_special\').remove();
	var verification' . $verificationOptions['id'] . 'Handle = new weCaptcha("' . $thisVerification['image_href'] . '", "' . $verificationOptions['id'] . '");');

	// Add any that are going to be hooked.
	$other_vv = call_hook('verification_setup', array(&$verificationOptions['id']));
	if (!empty($other_vv))
		foreach ($other_vv as $k => $v)
			if (empty($v))
				unset($other_vv[$k]);

	if (!empty($other_vv))
		$thisVerification['other_vv'] = $other_vv;

	// Is there actually going to be anything?
	if (empty($thisVerification['show_visual']) && empty($thisVerification['number_questions']) && empty($thisVerification['other_vv']))
		return false;
	elseif (!$isNew && !$do_test)
		return true;

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

	if (!empty($thisVerification['other_vv']))
		foreach ($thisVerification['other_vv'] as $ver)
			if (!empty($ver['force_refresh']))
			{
				$force_refresh = true;
				break;
			}

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
		// Nor this, really.
		if ($thisVerification['do_empty_field'] && empty($_SESSION[$verificationOptions['id'] . '_vv']['empty_field']))
			fatal_lang_error('no_access', false);

		if ($thisVerification['show_visual'] && (empty($_REQUEST[$verificationOptions['id'] . '_vv']['code']) || empty($_SESSION[$verificationOptions['id'] . '_vv']['code']) || strtoupper($_REQUEST[$verificationOptions['id'] . '_vv']['code']) !== $_SESSION[$verificationOptions['id'] . '_vv']['code']))
			$verification_errors[] = 'wrong_verification_code';
		if ($thisVerification['number_questions'])
		{
			$incorrectQuestions = array();
			$qa = unserialize($settings['qa_verification_qas']);
			foreach ($_SESSION[$verificationOptions['id'] . '_vv']['q'] as $q_id)
			{
				list ($lang, $id) = explode(':', $q_id);
				if (!isset($qa[$lang][$id]) || !isset($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$q_id]))
					$incorrectQuestions[] = $q_id;
				else
				{
					// Get all the answers, make them all lowercase.
					$answers = $qa[$lang][$id];
					array_shift($answers);
					foreach ($answers as $k => $v)
						$answers[$k] = westr::strtolower($v);

					if (!in_array(trim(westr::htmlspecialchars(westr::strtolower($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$q_id]), ENT_QUOTES)), $answers))
						$incorrectQuestions[] = $q_id;
				}
			}

			if (!empty($incorrectQuestions))
				$verification_errors[] = 'wrong_verification_answer';
		}

		if ($thisVerification['do_empty_field'] && !empty($_SESSION[$verificationOptions['id'] . '_vv']['empty_field']) && !empty($_REQUEST[$_SESSION[$verificationOptions['id'] . '_vv']['empty_field']]))
			$verification_errors[] = 'wrong_verification_answer';

		if (!empty($thisVerification['other_vv']))
			call_hook('verification_test', array(&$verificationOptions['id'], &$verification_errors));
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
		$_SESSION[$verificationOptions['id'] . '_vv']['code'] = '';

		if ($thisVerification['do_empty_field'])
		{
			// We're building a field that lives in the template, that we hope to be empty later. But at least we give it a believable name.
			$terms = array('validate', 'authenticate', 'authorize', 'check', 'key', 'crypto', 'encrypt');
			$second_terms = array('hash', 'cipher', 'field', 'name', 'id', 'code', 'key');
			$start = mt_rand(0, 27);
			$hash = substr(md5(time()), $start, 4);
			$_SESSION[$verificationOptions['id'] . '_vv']['empty_field'] = $terms[array_rand($terms)] . '-' . $second_terms[array_rand($second_terms)] . '-' . $hash;
		}

		// Generating a new image.
		if ($thisVerification['show_visual'])
		{
			// Are we overriding the range?
			$character_range = !empty($verificationOptions['override_range']) ? $verificationOptions['override_range'] : $context['standard_captcha_range'];

			for ($i = 0; $i < 6; $i++)
				$_SESSION[$verificationOptions['id'] . '_vv']['code'] .= $character_range[array_rand($character_range)];
		}

		// Getting some new questions?
		if ($thisVerification['number_questions'] && !empty($settings['qa_verification_qas']))
		{
			$qa = unserialize($settings['qa_verification_qas']);
			// OK, so which language are we going to use? Try session, followed by user choice, then forum default, and lastly whichever one is first - so we have *something*.
			if (isset($_SESSION['language'], $qa[$_SESSION['language']]))
				$lang = $_SESSION['language'];
			elseif (isset($qa[we::$user['language']]))
				$lang = we::$user['language'];
			elseif (isset($qa[$settings['language']]))
				$lang = $settings['language'];
			else
			{
				$items = array_keys($qa);
				$lang = array_shift($items);
			}

			// Pick some random IDs
			$questionIDs = array();
			$keys = array_keys($qa[$lang]);
			$q_ids = (array) array_rand($keys, $thisVerification['number_questions']);
			foreach ($q_ids as $id)
				$questionIDs[] = $lang . ':' . $id;
		}

		if (!empty($thisVerification['other_vv']))
			call_hook('verification_refresh', array(&$verificationOptions['id']));
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
		$qa = unserialize($settings['qa_verification_qas']);
		$_SESSION[$verificationOptions['id'] . '_vv']['q'] = array();
		foreach ($questionIDs as $q_id)
		{
			list ($lang, $id) = explode(':', $q_id);
			$row = $qa[$lang][$id];
			$question = array_shift($row);
			$thisVerification['questions'][] = array(
				'id' => $q_id,
				'q' => parse_bbc($question, array('parse_type' => 'q-and-a')),
				'is_error' => !empty($incorrectQuestions) && in_array($q_id, $incorrectQuestions),
				'a' => isset($_REQUEST[$verificationOptions['id'] . '_vv'], $_REQUEST[$verificationOptions['id'] . '_vv']['q'], $_REQUEST[$verificationOptions['id'] . '_vv']['q'][$q_id]) ? westr::htmlspecialchars($_REQUEST[$verificationOptions['id'] . '_vv']['q'][$q_id], ENT_QUOTES) : '',
			);
			$_SESSION[$verificationOptions['id'] . '_vv']['q'][] = $q_id;
		}
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
