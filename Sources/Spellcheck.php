<?php
/**
 * Wedge
 *
 * This file contains those functions dealing with spell checking.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	void Spellcheck()
		- spell checks the post for typos ;).
		- uses the pspell library, which MUST be installed.
		- has problems with internationalization.
		- is accessed via ?action=spellcheck.

*/

function Spellcheck()
{
	global $txt, $context;

	// A list of "words" we know about but pspell may not.
	$known_words = array('wedge', 'smf', 'php', 'mysql', 'www', 'gif', 'jpeg', 'jpg', 'png', 'http', 'bbc');

	loadLanguage('Post');
	loadTemplate('Post');

	// Okay, this looks funny, but it actually fixes a weird bug.
	ob_start();
	$old = error_reporting(0);

	// See, first, some windows machines don't load pspell properly on the first try.  Dumb, but this is a workaround.
	pspell_new('en');

	// Next, the dictionary in question may not exist. So, we try it... but...
	$pspell_link = pspell_new($txt['lang_dictionary'], $txt['lang_spelling'], '', 'utf-8', PSPELL_FAST | PSPELL_RUN_TOGETHER);

	// Most people don't have anything but English installed... So we use English as a last resort.
	if (!$pspell_link)
		$pspell_link = pspell_new('en', '', '', '', PSPELL_FAST | PSPELL_RUN_TOGETHER);

	error_reporting($old);
	ob_end_clean();

	// If an error happened, just close the window and look innocent.
	// (Why do I need to have *THIS* be valid HTML5?! I must be sick.)
	if (!isset($_POST['spellstring']) || !$pspell_link)
		exit('<!DOCTYPE html>
<head><title></title><script>
	window.close();
</script></head>');

	// Construct a bit of JavaScript code.
	$context['spell_js'] = '
	var txt = { done: ' . JavaScriptEscape($txt['spellcheck_done']) . ' };
	var mispstr = window.opener.document.forms[spell_formname][spell_fieldname].value;
	var misps = Array(';

	// Get all the words (JavaScript already separated them.)
	$alphas = explode("\n", strtr($_POST['spellstring'], array("\r" => '')));

	$found_words = false;
	for ($i = 0, $n = count($alphas); $i < $n; $i++)
	{
		// Words are sent like 'word|offset_begin|offset_end'.
		$check_word = explode('|', $alphas[$i]);

		// If the word is a known word, or spelled right...
		if (in_array(westr::strtolower($check_word[0]), $known_words) || pspell_check($pspell_link, $check_word[0]) || !isset($check_word[2]))
			continue;

		// Find the word, and move up the "last occurance" to here.
		$found_words = true;

		// Add on the JavaScript for this misspelling.
		$context['spell_js'] .= '
		new misp("' . strtr($check_word[0], array('\\' => '\\\\', '"' => '\\"', '<' => '', '&gt;' => '')) . '", ' . (int) $check_word[1] . ', ' . (int) $check_word[2] . ', [';

		// If there are suggestions, add them in...
		$suggestions = pspell_suggest($pspell_link, $check_word[0]);
		if (!empty($suggestions))
		{
			// But first check they aren't going to be censored - no naughty words!
			foreach ($suggestions as $k => $word)
				if ($suggestions[$k] != censorText($word))
					unset($suggestions[$k]);

			if (!empty($suggestions))
				$context['spell_js'] .= '"' . implode('", "', $suggestions) . '"';
		}

		$context['spell_js'] .= ']),';
	}

	// If words were found, take off the last comma.
	if ($found_words)
		$context['spell_js'] = substr($context['spell_js'], 0, -1);

	$context['spell_js'] .= '
	);';

	// And instruct the template system to just show the spellcheck block.
	wetem::load('spellcheck');
	wetem::hide();
}

?>