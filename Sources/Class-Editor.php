<?php
/**********************************************************************************
* Class-Editor.php                                                                *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC3                                         *
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

class wedgeEditor
{
	protected static $editorLoaded = false;
	public $bbc = null;
	public $disabled_bbc = null;
	public $smileys = null;
	public $show_bbc = false;
	protected $editorOptions = null;

	public function __construct($editorOptions)
	{
		global $txt, $modSettings, $options, $smcFunc;
		global $context, $settings, $user_info, $sourcedir, $scripturl;

		if (!is_array($editorOptions))
			$editorOptions = array($editorOptions);

		// Needs an id that we will be using
		assert(isset($editorOptions['id']));
		if (empty($editorOptions['value']))
			$editorOptions['value'] = '';

		$this->editorOptions = array(
			'id' => $editorOptions['id'],
			'value' => $editorOptions['value'],
			'rich_value' => wedgeEditor::bbc_to_html($editorOptions['value']),
			'rich_active' => empty($modSettings['disable_wysiwyg']) && (!empty($options['wysiwyg_default']) || !empty($editorOptions['force_rich']) || !empty($_REQUEST[$editorOptions['id'] . '_mode'])),
			'disable_smiley_box' => !empty($editorOptions['disable_smiley_box']),
			'columns' => isset($editorOptions['columns']) ? $editorOptions['columns'] : 60,
			'rows' => isset($editorOptions['rows']) ? $editorOptions['rows'] : 12,
			'width' => isset($editorOptions['width']) ? $editorOptions['width'] : '70%',
			'height' => isset($editorOptions['height']) ? $editorOptions['height'] : '150px',
			'form' => isset($editorOptions['form']) ? $editorOptions['form'] : 'postmodify',
			'bbc_level' => !empty($editorOptions['bbc_level']) ? $editorOptions['bbc_level'] : 'full',
			'preview_type' => isset($editorOptions['preview_type']) ? (int) $editorOptions['preview_type'] : 1,
			'labels' => !empty($editorOptions['labels']) ? $editorOptions['labels'] : array(),
			'custom_bbc_div' => !empty($editorOptions['custom_bbc_div']) ? $editorOptions['custom_bbc_div'] : '',
			'custom_smiley_div' => !empty($editorOptions['custom_smiley_div']) ? $editorOptions['custom_smiley_div'] : '',
		);

		// Stuff to do once per page only.
		if (!self::$editorLoaded)
		{
			self::$editorLoaded = true;

			loadLanguage('Post');
			loadTemplate(false, $context['browser']['is_ie'] ? 'editor_ie' : 'editor'); // we don't need any templates; this class does it. But we do need CSS.

			$settings['smileys_url'] = $modSettings['smileys_url'] . '/' . $user_info['smiley_set'];
			$context['html_headers'] .= '
		<script type="text/javascript"><!-- // --><![CDATA[
			var smf_smileys_url = \'' . $settings['smileys_url'] . '\';
			var oEditorStrings = {
				wont_work: \'' . addcslashes($txt['rich_edit_wont_work'], "'") . '\',
				func_disabled: \'' . addcslashes($txt['rich_edit_function_disabled'], "'") . '\',
				prompt_text_email: \'' . addcslashes($txt['prompt_text_email'], "'") . '\',
				prompt_text_ftp: \'' . addcslashes($txt['prompt_text_ftp'], "'") . '\',
				prompt_text_url: \'' . addcslashes($txt['prompt_text_url'], "'") . '\',
				prompt_text_img: \'' . addcslashes($txt['prompt_text_img'], "'") . '\',
				prompt_text_desc: \'' . addcslashes($txt['prompt_text_desc'], "'") . '\'
			}
		// ]]></script>
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/editor.js?rc3"></script>';

			$context['show_spellchecking'] = !empty($modSettings['enableSpellChecking']) && function_exists('pspell_new');
			if ($context['show_spellchecking'])
			{
				$context['html_headers'] .= '
		<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/spellcheck.js"></script>';

				// Some hidden information is needed in order to make the spell checking work.
				if (!isset($_REQUEST['xml']))
					$context['insert_after_template'] .= '
		<form name="spell_form" id="spell_form" method="post" accept-charset="UTF-8" target="spellWindow" action="' . $scripturl . '?action=spellcheck">
			<input type="hidden" name="spellstring" value="" />
		</form>';

				// Also make sure that spell check works with rich edit.
				$context['html_headers'] .= '
		<script type="text/javascript"><!-- // --><![CDATA[
		function spellCheckDone()
		{
			for (i = 0; i < smf_editorArray.length; i++)
				setTimeout("smf_editorArray[" + i + "].spellCheckEnd()", 150);
		}
		// ]]></script>';
			}
		}

		$this->LoadBBC();
		$this->LoadSmileys();
	}

	public function __get($name)
	{
		if (isset($this->editorOptions[$name]))
			return $this->editorOptions[$name];
		else
			return false;
	}

	public static function bbc_to_html($text)
	{
		global $modSettings, $smcFunc;

		// Turn line breaks back into br's.
		$text = strtr($text, array("\r" => '', "\n" => '<br />'));

		// Prevent conversion of all bbcode inside these bbcodes.
		// !!! Tie in with bbc permissions ?
		foreach (array('code', 'php', 'nobbc') as $code)
		{
			if (strpos($text, '[' . $code) !== false)
			{
				$parts = preg_split('~(\[/' . $code . '\]|\[' . $code . '(?:=[^\]]+)?\])~i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

				// Only mess with stuff inside tags.
				for ($i = 0, $n = count($parts); $i < $n; $i++)
				{
					// Value of 2 means we're inside the tag.
					if ($i % 4 == 2)
						$parts[$i] = strtr($parts[$i], array('[' => '&#91;', ']' => '&#93;', "'" => "'"));
				}
				// Put our humpty dumpty message back together again.
				$text = implode('', $parts);
			}
		}

		// What tags do we allow?
		$allowed_tags = array('b', 'u', 'i', 's', 'hr', 'list', 'li', 'font', 'size', 'color', 'img', 'left', 'center', 'right', 'url', 'email', 'ftp', 'sub', 'sup');

		$text = parse_bbc($text, true, '', $allowed_tags);

		// Fix for having a line break then a thingy.
		$text = strtr($text, array('<br /><div' => '<div', "\n" => '', "\r" => ''));

		// Note that IE doesn't understand spans really - make them something "legacy"
		$working_html = array(
			'~<del>(.+?)</del>~i' => '<strike>$1</strike>',
			'~<span\sclass="bbc_u">(.+?)</span>~i' => '<u>$1</u>',
			'~<span\sstyle="color:\s*([#\d\w]+);" class="bbc_color">(.+?)</span>~i' => '<font color="$1">$2</font>',
			'~<span\sstyle="font-family:\s*([#\d\w\s]+);" class="bbc_font">(.+?)</span>~i' => '<font face="$1">$2</font>',
			'~<div\sstyle="text-align:\s*(left|right);">(.+?)</div>~i' => '<p align="$1">$2</p>',
		);
		$text = preg_replace(array_keys($working_html), array_values($working_html), $text);

		// Parse unique ID's and disable javascript into the smileys - using the double space.
		$i = 1;
		$text = preg_replace('~(?:\s|&nbsp;)?<(img\ssrc="' . preg_quote($modSettings['smileys_url'], '~') . '/[^<>]+?/([^<>]+?)"\s*)[^<>]*?class="smiley" />~e', '\'<\' . ' . 'stripslashes(\'$1\') . \'alt="" title="" onresizestart="return false;" id="smiley_\' . ' . "\$" . 'i++ . \'_$2" style="padding: 0 3px 0 3px;" />\'', $text);

		return $text;
	}

	public static function html_to_bbc($text)
	{
		global $modSettings, $smcFunc, $sourcedir, $scripturl, $context;

		// Replace newlines with spaces, as that's how browsers usually interpret them.
		$text = preg_replace("~\s*[\r\n]+\s*~", ' ', $text);

		// Though some of us love paragraphs, the parser will do better with breaks.
		$text = preg_replace('~</p>\s*?<p~i', '</p><br /><p', $text);
		$text = preg_replace('~</p>\s*(?!<)~i', '</p><br />', $text);

		// Safari/webkit wraps lines in Wysiwyg in <div>'s.
		if ($context['browser']['is_webkit'])
			$text = preg_replace(array('~<div(?:\s(?:[^<>]*?))?' . '>~i', '</div>'), array('<br />', ''), $text);

		// If there's a trailing break get rid of it - Firefox tends to add one.
		$text = preg_replace('~<br\s?/?' . '>$~i', '', $text);

		// Remove any formatting within code tags.
		if (strpos($text, '[code') !== false)
		{
			$text = preg_replace('~<br\s?/?' . '>~i', '#smf_br_spec_grudge_cool!#', $text);
			$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

			// Only mess with stuff outside [code] tags.
			for ($i = 0, $n = count($parts); $i < $n; $i++)
			{
				// Value of 2 means we're inside the tag.
				if ($i % 4 == 2)
					$parts[$i] = strip_tags($parts[$i]);
			}

			$text = strtr(implode('', $parts), array('#smf_br_spec_grudge_cool!#' => '<br />'));
		}

		// Remove scripts, style and comment blocks.
		$text = preg_replace('~<script[^>]*[^/]?' . '>.*?</script>~i', '', $text);
		$text = preg_replace('~<style[^>]*[^/]?' . '>.*?</style>~i', '', $text);
		$text = preg_replace('~\\<\\!--.*?-->~i', '', $text);
		$text = preg_replace('~\\<\\!\\[CDATA\\[.*?\\]\\]\\>~i', '', $text);

		// Do the smileys ultra first!
		preg_match_all('~<img\s+[^<>]*?id="*smiley_\d+_([^<>]+?)[\s"/>]\s*[^<>]*?/*>(?:\s)?~i', $text, $matches);
		if (!empty($matches[0]))
		{
			// Easy if it's not custom.
			if (empty($modSettings['smiley_enable']))
			{
				$smileysfrom = array('>:D', ':D', '::)', '>:(', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', '0:)');
				$smileysto = array('evil.gif', 'cheesy.gif', 'rolleyes.gif', 'angry.gif', 'smiley.gif', 'wink.gif', 'grin.gif', 'sad.gif', 'shocked.gif', 'cool.gif', 'tongue.gif', 'huh.gif', 'embarrassed.gif', 'lipsrsealed.gif', 'kiss.gif', 'cry.gif', 'undecided.gif', 'azn.gif', 'afro.gif', 'police.gif', 'angel.gif');

				foreach ($matches[1] as $k => $file)
				{
					$found = array_search($file, $smileysto);
					// Note the weirdness here is to stop double spaces between smileys.
					if ($found)
						$matches[1][$k] = '-[]-smf_smily_start#|#' . htmlspecialchars($smileysfrom[$found]) . '-[]-smf_smily_end#|#';
					else
						$matches[1][$k] = '';
				}
			}
			else
			{
				// Load all the smileys.
				$names = array();
				foreach ($matches[1] as $file)
					$names[] = $file;
				$names = array_unique($names);

				if (!empty($names))
				{
					$request = $smcFunc['db_query']('', '
						SELECT code, filename
						FROM {db_prefix}smileys
						WHERE filename IN ({array_string:smiley_filenames})',
						array(
							'smiley_filenames' => $names,
						)
					);
					$mappings = array();
					while ($row = $smcFunc['db_fetch_assoc']($request))
						$mappings[$row['filename']] = htmlspecialchars($row['code']);
					$smcFunc['db_free_result']($request);

					foreach ($matches[1] as $k => $file)
						if (isset($mappings[$file]))
							$matches[1][$k] = '-[]-smf_smily_start#|#' . $mappings[$file] . '-[]-smf_smily_end#|#';
				}
			}

			// Replace the tags!
			$text = str_replace($matches[0], $matches[1], $text);

			// Now sort out spaces
			$text = str_replace(array('-[]-smf_smily_end#|#-[]-smf_smily_start#|#', '-[]-smf_smily_end#|#', '-[]-smf_smily_start#|#'), ' ', $text);
		}

		// Only try to buy more time if the client didn't quit.
		if (connection_aborted() && $context['server']['is_apache'])
			@apache_reset_timeout();

		$parts = preg_split('~(<[A-Za-z]+\s*[^<>]*?style="?[^<>"]+"?[^<>]*?(?:/?)>|</[A-Za-z]+>)~', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		$replacement = '';
		$stack = array();

		foreach ($parts as $part)
		{
			if (preg_match('~(<([A-Za-z]+)\s*[^<>]*?)style="?([^<>"]+)"?([^<>]*?(/?)>)~', $part, $matches) === 1)
			{
				// If it's being closed instantly, we can't deal with it...yet.
				if ($matches[5] === '/')
					continue;
				else
				{
					// Get an array of styles that apply to this element. (The strtr is there to combat HTML generated by Word.)
					$styles = explode(';', strtr($matches[3], array('&quot;' => '')));
					$curElement = $matches[2];
					$precedingStyle = $matches[1];
					$afterStyle = $matches[4];
					$curCloseTags = '';
					$extra_attr = '';

					foreach ($styles as $type_value_pair)
					{
						// Remove spaces and convert uppercase letters.
						$clean_type_value_pair = strtolower(strtr(trim($type_value_pair), '=', ':'));

						// Something like 'font-weight: bold' is expected here.
						if (strpos($clean_type_value_pair, ':') === false)
							continue;

						// Capture the elements of a single style item (e.g. 'font-weight' and 'bold').
						list ($style_type, $style_value) = explode(':', $type_value_pair);

						$style_value = trim($style_value);

						switch (trim($style_type))
						{
							case 'font-weight':
								if ($style_value === 'bold')
								{
									$curCloseTags .= '[/b]';
									$replacement .= '[b]';
								}
							break;

							case 'text-decoration':
								if ($style_value == 'underline')
								{
									$curCloseTags .= '[/u]';
									$replacement .= '[u]';
								}
								elseif ($style_value == 'line-through')
								{
									$curCloseTags .= '[/s]';
									$replacement .= '[s]';
								}
							break;

							case 'text-align':
								if ($style_value == 'left')
								{
									$curCloseTags .= '[/left]';
									$replacement .= '[left]';
								}
								elseif ($style_value == 'center')
								{
									$curCloseTags .= '[/center]';
									$replacement .= '[center]';
								}
								elseif ($style_value == 'right')
								{
									$curCloseTags .= '[/right]';
									$replacement .= '[right]';
								}
							break;

							case 'font-style':
								if ($style_value == 'italic')
								{
									$curCloseTags .= '[/i]';
									$replacement .= '[i]';
								}
							break;

							case 'color':
								$curCloseTags .= '[/color]';
								$replacement .= '[color=' . $style_value . ']';
							break;

							case 'font-size':
								// Sometimes people put decimals where decimals should not be.
								if (preg_match('~(\d)+\.\d+(p[xt])~i', $style_value, $dec_matches) === 1)
									$style_value = $dec_matches[1] . $dec_matches[2];

								$curCloseTags .= '[/size]';
								$replacement .= '[size=' . $style_value . ']';
							break;

							case 'font-family':
								// Only get the first freaking font if there's a list!
								if (strpos($style_value, ',') !== false)
									$style_value = substr($style_value, 0, strpos($style_value, ','));

								$curCloseTags .= '[/font]';
								$replacement .= '[font=' . strtr($style_value, array("'" => '')) . ']';
							break;

							// This is a hack for images with dimensions embedded.
							case 'width':
							case 'height':
								if (preg_match('~[1-9]\d*~i', $style_value, $dimension) === 1)
									$extra_attr .= ' ' . $style_type . '="' . $dimension[0] . '"';
							break;

							case 'list-style-type':
								if (preg_match('~none|disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-alpha|upper-alpha|lower-greek|lower-latin|upper-latin|hebrew|armenian|georgian|cjk-ideographic|hiragana|katakana|hiragana-iroha|katakana-iroha~i', $style_value, $listType) === 1)
									$extra_attr .= ' listtype="' . $listType[0] . '"';
							break;
						}
					}

					// Preserve the a tag stripping the styling.
					if ($matches[2] === 'a')
					{
						$replacement .= $precedingStyle . $afterStyle;
						$curCloseTags = '</a>' . $curCloseTags;
					}

					// If there's something that still needs closing, push it to the stack.
					if (!empty($curCloseTags))
						array_push($stack, array(
								'element' => strtolower($curElement),
								'closeTags' => $curCloseTags
							)
						);
					elseif (!empty($extra_attr))
						$replacement .= $precedingStyle . $extra_attr . $afterStyle;
				}
			}

			elseif (preg_match('~</([A-Za-z]+)>~', $part, $matches) === 1)
			{
				// Is this the element that we've been waiting for to be closed?
				if (!empty($stack) && strtolower($matches[1]) === $stack[count($stack) - 1]['element'])
				{
					$byebyeTag = array_pop($stack);
					$replacement .= $byebyeTag['closeTags'];
				}

				// Must've been something else.
				else
					$replacement .= $part;
			}
			// In all other cases, just add the part to the replacement.
			else
				$replacement .= $part;
		}

		// Now put back the replacement in the text.
		$text = $replacement;

		// We are not finished yet, request more time.
		if (connection_aborted() && $context['server']['is_apache'])
			@apache_reset_timeout();

		// Let's pull out any legacy alignments.
		while (preg_match('~<([A-Za-z]+)\s+[^<>]*?(align="*(left|center|right)"*)[^<>]*?(/?)>~i', $text, $matches) === 1)
		{
			// Find the position in the text of this tag over again.
			$start_pos = strpos($text, $matches[0]);
			if ($start_pos === false)
				break;

			// End tag?
			if ($matches[4] != '/' && strpos($text, '</' . $matches[1] . '>', $start_pos) !== false)
			{
				$end_length = strlen('</' . $matches[1] . '>');
				$end_pos = strpos($text, '</' . $matches[1] . '>', $start_pos);

				// Remove the align from that tag so it's never checked again.
				$tag = substr($text, $start_pos, strlen($matches[0]));
				$content = substr($text, $start_pos + strlen($matches[0]), $end_pos - $start_pos - strlen($matches[0]));
				$tag = str_replace($matches[2], '', $tag);

				// Put the tags back into the body.
				$text = substr($text, 0, $start_pos) . $tag . '[' . $matches[3] . ']' . $content . '[/' . $matches[3] . ']' . substr($text, $end_pos);
			}
			else
			{
				// Just get rid of this evil tag.
				$text = substr($text, 0, $start_pos) . substr($text, $start_pos + strlen($matches[0]));
			}
		}

		// Let's do some special stuff for fonts - cause we all love fonts.
		while (preg_match('~<font\s+([^<>]*)>~i', $text, $matches) === 1)
		{
			// Find the position of this again.
			$start_pos = strpos($text, $matches[0]);
			$end_pos = false;
			if ($start_pos === false)
				break;

			// This must have an end tag - and we must find the right one.
			$lower_text = strtolower($text);

			$start_pos_test = $start_pos + 4;
			// How many starting tags must we find closing ones for first?
			$start_font_tag_stack = 0;
			while ($start_pos_test < strlen($text))
			{
				// Where is the next starting font?
				$next_start_pos = strpos($lower_text, '<font', $start_pos_test);
				$next_end_pos = strpos($lower_text, '</font>', $start_pos_test);

				// Did we past another starting tag before an end one?
				if ($next_start_pos !== false && $next_start_pos < $next_end_pos)
				{
					$start_font_tag_stack++;
					$start_pos_test = $next_start_pos + 4;
				}
				// Otherwise we have an end tag but not the right one?
				elseif ($start_font_tag_stack)
				{
					$start_font_tag_stack--;
					$start_pos_test = $next_end_pos + 4;
				}
				// Otherwise we're there!
				else
				{
					$end_pos = $next_end_pos;
					break;
				}
			}
			if ($end_pos === false)
				break;

			// Now work out what the attributes are.
			$attribs = wedgeEditor::fetchTagAttributes($matches[1]);
			$tags = array();
			foreach ($attribs as $s => $v)
			{
				if ($s == 'size')
					$tags[] = array('[size=' . (int) trim($v) . ']', '[/size]');
				elseif ($s == 'face')
					$tags[] = array('[font=' . trim(strtolower($v)) . ']', '[/font]');
				elseif ($s == 'color')
					$tags[] = array('[color=' . trim(strtolower($v)) . ']', '[/color]');
			}

			// As before add in our tags.
			$before = $after = '';
			foreach ($tags as $tag)
			{
				$before .= $tag[0];
				if (isset($tag[1]))
					$after = $tag[1] . $after;
			}

			// Remove the tag so it's never checked again.
			$content = substr($text, $start_pos + strlen($matches[0]), $end_pos - $start_pos - strlen($matches[0]));

			// Put the tags back into the body.
			$text = substr($text, 0, $start_pos) . $before . $content . $after . substr($text, $end_pos + 7);
		}

		// Almost there, just a little more time.
		if (connection_aborted() && $context['server']['is_apache'])
			@apache_reset_timeout();

		if (count($parts = preg_split('~<(/?)(li|ol|ul)([^>]*)>~i', $text, null, PREG_SPLIT_DELIM_CAPTURE)) > 1)
		{
			// A toggle that dermines whether we're directly under a <ol> or <ul>.
			$inList = false;

			// Keep track of the number of nested list levels.
			$listDepth = 0;

			// Map what we can expect from the HTML to what is supported by SMF.
			$listTypeMapping = array(
				'1' => 'decimal',
				'A' => 'upper-alpha',
				'a' => 'lower-alpha',
				'I' => 'upper-roman',
				'i' => 'lower-roman',
				'disc' => 'disc',
				'square' => 'square',
				'circle' => 'circle',
			);

			// $i: text, $i + 1: '/', $i + 2: tag, $i + 3: tail.
			for ($i = 0, $numParts = count($parts) - 1; $i < $numParts; $i += 4)
			{
				$tag = strtolower($parts[$i + 2]);
				$isOpeningTag = $parts[$i + 1] === '';

				if ($isOpeningTag)
				{
					switch ($tag)
					{
						case 'ol':
						case 'ul':

							// We have a problem, we're already in a list.
							if ($inList)
							{
								// Inject a list opener, we'll deal with the ol/ul next loop.
								array_splice($parts, $i, 0, array(
									'',
									'',
									str_repeat("\t", $listDepth) . '[li]',
									'',
								));
								$numParts = count($parts) - 1;

								// The inlist status changes a bit.
								$inList = false;
							}

							// Just starting a new list.
							else
							{
								$inList = true;

								if ($tag === 'ol')
									$listType = 'decimal';
								elseif (preg_match('~type="?(' . implode('|', array_keys($listTypeMapping)) . ')"?~', $parts[$i + 3], $match) === 1)
									$listType = $listTypeMapping[$match[1]];
								else
									$listType = null;

								$listDepth++;

								$parts[$i + 2] = '[list' . ($listType === null ? '' : ' type=' . $listType) . ']' . "\n";
								$parts[$i + 3] = '';
							}
						break;

						case 'li':

							// This is how it should be: a list item inside the list.
							if ($inList)
							{
								$parts[$i + 2] = str_repeat("\t", $listDepth) . '[li]';
								$parts[$i + 3] = '';

								// Within a list item, it's almost as if you're outside.
								$inList = false;
							}

							// The li is no direct child of a list.
							else
							{
								// We are apparently in a list item.
								if ($listDepth > 0)
								{
									$parts[$i + 2] = '[/li]' . "\n" . str_repeat("\t", $listDepth) . '[li]';
									$parts[$i + 3] = '';
								}

								// We're not even near a list.
								else
								{
									// Quickly create a list with an item.
									$listDepth++;

									$parts[$i + 2] = '[list]' . "\n\t" . '[li]';
									$parts[$i + 3] = '';
								}
							}

						break;
					}
				}

				// Handle all the closing tags.
				else
				{
					switch ($tag)
					{
						case 'ol':
						case 'ul':

							// As we expected it, closing the list while we're in it.
							if ($inList)
							{
								$inList = false;

								$listDepth--;

								$parts[$i + 1] = '';
								$parts[$i + 2] = str_repeat("\t", $listDepth) . '[/list]';
								$parts[$i + 3] = '';
							}

							else
							{
								// We're in a list item.
								if ($listDepth > 0)
								{
									// Inject closure for this list item first.
									// The content of $parts[$i] is left as is!
									array_splice($parts, $i + 1, 0, array(
										'',				// $i + 1
										'[/li]' . "\n",	// $i + 2
										'',				// $i + 3
										'',				// $i + 4
									));
									$numParts = count($parts) - 1;

									// Now that we've closed the li, we're in list space.
									$inList = true;
								}

								// We're not even in a list, ignore
								else
								{
									$parts[$i + 1] = '';
									$parts[$i + 2] = '';
									$parts[$i + 3] = '';
								}
							}
						break;

						case 'li':

							if ($inList)
							{
								// There's no use for a </li> after <ol> or <ul>, ignore.
								$parts[$i + 1] = '';
								$parts[$i + 2] = '';
								$parts[$i + 3] = '';
							}

							else
							{
								// Remove the trailing breaks from the list item.
								$parts[$i] = preg_replace('~\s*<br\s*' . '/?' . '>\s*$~', '', $parts[$i]);
								$parts[$i + 1] = '';
								$parts[$i + 2] = '[/li]' . "\n";
								$parts[$i + 3] = '';

								// And we're back in the [list] space.
								$inList = true;
							}

						break;
					}
				}

				// If we're in the [list] space, no content is allowed.
				if ($inList && trim(preg_replace('~\s*<br\s*' . '/?' . '>\s*~', '', $parts[$i + 4])) !== '')
				{
					// Fix it by injecting an extra list item.
					array_splice($parts, $i + 4, 0, array(
						'', // No content.
						'', // Opening tag.
						'li', // It's a <li>.
						'', // No tail.
					));
					$numParts = count($parts) - 1;
				}
			}

			$text = implode('', $parts);

			if ($inList)
			{
				$listDepth--;
				$text .= str_repeat("\t", $listDepth) . '[/list]';
			}

			for ($i = $listDepth; $i > 0; $i--)
				$text .= '[/li]' . "\n" . str_repeat("\t", $i - 1) . '[/list]';

		}

		// I love my own image...
		while (preg_match('~<img\s+([^<>]*)/*>~i', $text, $matches) === 1)
		{
			// Find the position of the image.
			$start_pos = strpos($text, $matches[0]);
			if ($start_pos === false)
				break;
			$end_pos = $start_pos + strlen($matches[0]);

			$params = '';
			$had_params = array();
			$src = '';

			$attrs = wedgeEditor::fetchTagAttributes($matches[1]);
			foreach ($attrs as $attrib => $value)
			{
				if (in_array($attrib, array('width', 'height')))
					$params .= ' ' . $attrib . '=' . (int) $value;
				elseif ($attrib == 'alt' && trim($value) != '')
					$params .= ' alt=' . trim($value);
				elseif ($attrib == 'src')
					$src = trim($value);
			}

			$tag = '';
			if (!empty($src))
			{
				// Attempt to fix the path in case it's not present.
				if (preg_match('~^https?://~i', $src) === 0 && is_array($parsedURL = parse_url($scripturl)) && isset($parsedURL['host']))
				{
					$baseURL = (isset($parsedURL['scheme']) ? $parsedURL['scheme'] : 'http') . '://' . $parsedURL['host'] . (empty($parsedURL['port']) ? '' : ':' . $parsedURL['port']);

					if (substr($src, 0, 1) === '/')
						$src = $baseURL . $src;
					else
						$src = $baseURL . (empty($parsedURL['path']) ? '/' : preg_replace('~/(?:index\\.php)?$~', '', $parsedURL['path'])) . '/' . $src;
				}

				$tag = '[img' . $params . ']' . $src . '[/img]';
			}

			// Replace the tag
			$text = substr($text, 0, $start_pos) . $tag . substr($text, $end_pos);
		}

		// The final bits are the easy ones - tags which map to tags which map to tags - etc etc.
		$tags = array(
			'~<b(\s(.)*?)*?' . '>~i' => '[b]',
			'~</b>~i' => '[/b]',
			'~<i(\s(.)*?)*?' . '>~i' => '[i]',
			'~</i>~i' => '[/i]',
			'~<u(\s(.)*?)*?' . '>~i' => '[u]',
			'~</u>~i' => '[/u]',
			'~<strong(\s(.)*?)*?' . '>~i' => '[b]',
			'~</strong>~i' => '[/b]',
			'~<em(\s(.)*?)*?' . '>~i' => '[i]',
			'~</em>~i' => '[/i]',
			'~<s(\s(.)*?)*?' . '>~i' => "[s]",
			'~</s>~i' => "[/s]",
			'~<strike(\s(.)*?)*?' . '>~i' => '[s]',
			'~</strike>~i' => '[/s]',
			'~<del(\s(.)*?)*?' . '>~i' => '[s]',
			'~</del>~i' => '[/s]',
			'~<center(\s(.)*?)*?' . '>~i' => '[center]',
			'~</center>~i' => '[/center]',
			'~<pre(\s(.)*?)*?' . '>~i' => '[pre]',
			'~</pre>~i' => '[/pre]',
			'~<sub(\s(.)*?)*?' . '>~i' => '[sub]',
			'~</sub>~i' => '[/sub]',
			'~<sup(\s(.)*?)*?' . '>~i' => '[sup]',
			'~</sup>~i' => '[/sup]',
			'~<tt(\s(.)*?)*?' . '>~i' => '[tt]',
			'~</tt>~i' => '[/tt]',
			'~<table(\s(.)*?)*?' . '>~i' => '[table]',
			'~</table>~i' => '[/table]',
			'~<tr(\s(.)*?)*?' . '>~i' => '[tr]',
			'~</tr>~i' => '[/tr]',
			'~<(td|th)\s[^<>]*?colspan="?(\d{1,2})"?.*?' . '>~ie' => 'str_repeat(\'[td][/td]\', $2 - 1) . \'[td]\'',
			'~<(td|th)(\s(.)*?)*?' . '>~i' => '[td]',
			'~</(td|th)>~i' => '[/td]',
			'~<br(?:\s[^<>]*?)?' . '>~i' => "\n",
			'~<hr[^<>]*>(\n)?~i' => "[hr]\n$1",
			'~(\n)?\\[hr\\]~i' => "\n[hr]",
			'~^\n\\[hr\\]~i' => "[hr]",
			'~<blockquote(\s(.)*?)*?' . '>~i' => "&lt;blockquote&gt;",
			'~</blockquote>~i' => "&lt;/blockquote&gt;",
			'~<ins(\s(.)*?)*?' . '>~i' => "&lt;ins&gt;",
			'~</ins>~i' => "&lt;/ins&gt;",
		);
		$text = preg_replace(array_keys($tags), array_values($tags), $text);

		// Please give us just a little more time.
		if (connection_aborted() && $context['server']['is_apache'])
			@apache_reset_timeout();

		// What about URL's - the pain in the ass of the tag world.
		while (preg_match('~<a\s+([^<>]*)>([^<>]*)</a>~i', $text, $matches) === 1)
		{
			// Find the position of the URL.
			$start_pos = strpos($text, $matches[0]);
			if ($start_pos === false)
				break;
			$end_pos = $start_pos + strlen($matches[0]);

			$tag_type = 'url';
			$href = '';

			$attrs = wedgeEditor::fetchTagAttributes($matches[1]);
			foreach ($attrs as $attrib => $value)
			{
				if ($attrib == 'href')
				{
					$href = trim($value);

					// Are we dealing with an FTP link?
					if (preg_match('~^ftps?://~', $href) === 1)
						$tag_type = 'ftp';

					// Or is this a link to an email address?
					elseif (substr($href, 0, 7) == 'mailto:')
					{
						$tag_type = 'email';
						$href = substr($href, 7);
					}

					// No http(s), so attempt to fix this potential relative URL.
					elseif (preg_match('~^https?://~i', $href) === 0 && is_array($parsedURL = parse_url($scripturl)) && isset($parsedURL['host']))
					{
						$baseURL = (isset($parsedURL['scheme']) ? $parsedURL['scheme'] : 'http') . '://' . $parsedURL['host'] . (empty($parsedURL['port']) ? '' : ':' . $parsedURL['port']);

						if (substr($href, 0, 1) === '/')
							$href = $baseURL . $href;
						else
							$href = $baseURL . (empty($parsedURL['path']) ? '/' : preg_replace('~/(?:index\\.php)?$~', '', $parsedURL['path'])) . '/' . $href;
					}
				}

				// External URL?
				if ($attrib == 'target' && $tag_type == 'url')
				{
					if (trim($value) == '_blank')
						$tag_type == 'iurl';
				}
			}

			$tag = '';
			if ($href != '')
			{
				if ($matches[2] == $href)
					$tag = '[' . $tag_type . ']' . $href . '[/' . $tag_type . ']';
				else
					$tag = '[' . $tag_type . '=' . $href . ']' . $matches[2] . '[/' . $tag_type . ']';
			}

			// Replace the tag
			$text = substr($text, 0, $start_pos) . $tag . substr($text, $end_pos);
		}

		$text = strip_tags($text);

		// Some tags often end up as just dummy tags - remove those.
		$text = preg_replace('~\[[bisu]\]\s*\[/[bisu]\]~', '', $text);

		// Fix up entities.
		$text = preg_replace('~&#38;~i', '&#38;#38;', $text);

		$text = wedgeEditor::legalise_bbc($text);

		return $text;
	}

	public static function fetchTagAttributes($text)
	{
		$attribs = array();
		$key = $value = '';
		$strpos = 0;
		$tag_state = 0; // 0 = key, 1 = attribute with no string, 2 = attribute with string
		for ($i = 0; $i < strlen($text); $i++)
		{
			// We're either moving from the key to the attribute or we're in a string and this is fine.
			if ($text{$i} == '=')
			{
				if ($tag_state == 0)
					$tag_state = 1;
				elseif ($tag_state == 2)
					$value .= '=';
			}
			// A space is either moving from an attribute back to a potential key or in a string is fine.
			elseif ($text{$i} == ' ')
			{
				if ($tag_state == 2)
					$value .= ' ';
				elseif ($tag_state == 1)
				{
					$attribs[$key] = $value;
					$key = $value = '';
					$tag_state = 0;
				}
			}
			// A quote?
			elseif ($text{$i} == '"')
			{
				// Must be either going into or out of a string.
				if ($tag_state == 1)
					$tag_state = 2;
				else
					$tag_state = 1;
			}
			// Otherwise it's fine.
			else
			{
				if ($tag_state == 0)
					$key .= $text{$i};
				else
					$value .= $text{$i};
			}
		}

		// Anything left?
		if ($key != '' && $value != '')
			$attribs[$key] = $value;

		return $attribs;
	}

	// This is an important yet frustrating function - it attempts to clean up illegal BBC caused by browsers like Opera which don't obey the rules!!!
	public static function legalise_bbc($text)
	{
		global $modSettings;

		// Don't care about the texts that are too short.
		if (strlen($text) < 3)
			return $text;

		// We are going to cycle through the BBC and keep track of tags as they arise - in order. If get to a block level tag we're going to make sure it's not in a non-block level tag!
		// This will keep the order of tags that are open.
		$current_tags = array();

		// This will quickly let us see if the tag is active.
		$active_tags = array();

		// A list of tags that's disabled by the admin.
		$disabled = empty($modSettings['disabledBBC']) ? array() : array_flip(explode(',', strtolower($modSettings['disabledBBC'])));

		// Add flash if it's disabled as embedded tag.
		if (empty($modSettings['enableEmbeddedFlash']))
			$disabled['flash'] = true;

		// Get a list of all the tags that are not disabled.
		$all_tags = parse_bbc(false);
		$valid_tags = array();
		$self_closing_tags = array();
		foreach ($all_tags as $tag)
		{
			if (!isset($disabled[$tag['tag']]))
				$valid_tags[$tag['tag']] = !empty($tag['block_level']);
			if (isset($tag['type']) && $tag['type'] == 'closed')
				$self_closing_tags[] = $tag['tag'];
		}

		// Don't worry if we're in a code/nobbc.
		$in_code_nobbc = false;

		// Right - we're going to start by going through the whole lot to make sure we don't have align stuff crossed as this happens load and is stupid!
		$align_tags = array('left', 'center', 'right', 'pre');

		// Remove those align tags that are not valid.
		$align_tags = array_intersect($align_tags, array_keys($valid_tags));

		// These keep track of where we are!
		if (!empty($align_tags) && count($matches = preg_split('~(\\[/?(?:' . implode('|', $align_tags) . ')\\])~', $text, -1, PREG_SPLIT_DELIM_CAPTURE)) > 1)
		{
			// The first one is never a tag.
			$isTag = false;

			// By default we're not inside a tag too.
			$insideTag = null;

			foreach ($matches as $i => $match)
			{
				// We're only interested in tags, not text.
				if ($isTag)
				{
					$isClosingTag = substr($match, 1, 1) === '/';
					$tagName = substr($match, $isClosingTag ? 2 : 1, -1);

					// We're closing the exact same tag that we opened.
					if ($isClosingTag && $insideTag === $tagName)
						$insideTag = null;

					// We're opening a tag and we're not yet inside one either
					elseif (!$isClosingTag && $insideTag === null)
						$insideTag = $tagName;

					// In all other cases, this tag must be invalid
					else
						unset($matches[$i]);
				}

				// The next one is gonna be the other one.
				$isTag = !$isTag;
			}

			// We're still inside a tag and had no chance for closure?
			if ($insideTag !== null)
				$matches[] = '[/' . $insideTag . ']';

			// And a complete text string again.
			$text = implode('', $matches);
		}

		// Quickly remove any tags which are back to back.
		$backToBackPattern = '~\\[(' . implode('|', array_diff(array_keys($valid_tags), array('td', 'anchor'))) . ')[^<>\\[\\]]*\\]\s*\\[/\\1\\]~';
		$lastlen = 0;
		while (strlen($text) !== $lastlen)
			$lastlen = strlen($text = preg_replace($backToBackPattern, '', $text));

		// Need to sort the tags my name length.
		uksort($valid_tags, array('wedgeEditor', 'sort_array_length'));

		// These inline tags can compete with each other regarding style.
		$competing_tags = array(
			'color',
			'size',
		);

		// In case things changed above set these back to normal.
		$in_code_nobbc = false;
		$new_text_offset = 0;

		// These keep track of where we are!
		if (count($parts = preg_split(sprintf('~(\\[)(/?)(%1$s)((?:[\\s=][^\\]\\[]*)?\\])~', implode('|', array_keys($valid_tags))), $text, -1, PREG_SPLIT_DELIM_CAPTURE)) > 1)
		{
			// Start with just text.
			$isTag = false;

			// Start outside [nobbc] or [code] blocks.
			$inCode = false;
			$inNoBbc = false;

			// A buffer containing all opened inline elements.
			$inlineElements = array();

			// A buffer containing all opened block elements.
			$blockElements = array();

			// A buffer containing the opened inline elements that might compete.
			$competingElements = array();

			// $i: text, $i + 1: '[', $i + 2: '/', $i + 3: tag, $i + 4: tag tail.
			for ($i = 0, $n = count($parts) - 1; $i < $n; $i += 5)
			{
				$tag = $parts[$i + 3];
				$isOpeningTag = $parts[$i + 2] === '';
				$isClosingTag = $parts[$i + 2] === '/';
				$isBlockLevelTag = isset($valid_tags[$tag]) && $valid_tags[$tag] && !in_array($tag, $self_closing_tags);
				$isCompetingTag = in_array($tag, $competing_tags);

				// Check if this might be one of those cleaned out tags.
				if ($tag === '')
					continue;

				// Special case: inside [code] blocks any code is left untouched.
				elseif ($tag === 'code')
				{
					// We're inside a code block and closing it.
					if ($inCode && $isClosingTag)
					{
						$inCode = false;

						// Reopen tags that were closed before the code block.
						if (!empty($inlineElements))
							$parts[$i + 4] .= '[' . implode('][', array_keys($inlineElements)) . ']';
					}

					// We're outside a coding and nobbc block and opening it.
					elseif (!$inCode && !$inNoBbc && $isOpeningTag)
					{
						// If there are still inline elements left open, close them now.
						if (!empty($inlineElements))
						{
							$parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';
							//$inlineElements = array();
						}

						$inCode = true;
					}

					// Nothing further to do.
					continue;
				}

				// Special case: inside [nobbc] blocks any BBC is left untouched.
				elseif ($tag === 'nobbc')
				{
					// We're inside a nobbc block and closing it.
					if ($inNoBbc && $isClosingTag)
					{
						$inNoBbc = false;

						// Some inline elements might've been closed that need reopening.
						if (!empty($inlineElements))
							$parts[$i + 4] .= '[' . implode('][', array_keys($inlineElements)) . ']';
					}

					// We're outside a nobbc and coding block and opening it.
					elseif (!$inNoBbc && !$inCode && $isOpeningTag)
					{
						// Can't have inline elements still opened.
						if (!empty($inlineElements))
						{
							$parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';
							//$inlineElements = array();
						}

						$inNoBbc = true;
					}

					continue;
				}

				// So, we're inside one of the special blocks: ignore any tag.
				elseif ($inCode || $inNoBbc)
					continue;

				// We're dealing with an opening tag.
				if ($isOpeningTag)
				{
					// Everyting inside the square brackets of the opening tag.
					$elementContent = $parts[$i + 3] . substr($parts[$i + 4], 0, -1);

					// A block level opening tag.
					if ($isBlockLevelTag)
					{
						// Are there inline elements still open?
						if (!empty($inlineElements))
						{
							// Close all the inline tags, a block tag is coming...
							$parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';

							// Now open them again, we're inside the block tag now.
							$parts[$i + 5] = '[' . implode('][', array_keys($inlineElements)) . ']' . $parts[$i + 5];
						}

						$blockElements[] = $tag;
					}

					// Inline opening tag.
					elseif (!in_array($tag, $self_closing_tags))
					{
						// Can't have two opening elements with the same contents!
						if (isset($inlineElements[$elementContent]))
						{
							// Get rid of this tag.
							$parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';

							// Now try to find the corresponding closing tag.
							$curLevel = 1;
							for ($j = $i + 5, $m = count($parts) - 1; $j < $m; $j += 5)
							{
								// Find the tags with the same tagname
								if ($parts[$j + 3] === $tag)
								{
									// If it's an opening tag, increase the level.
									if ($parts[$j + 2] === '')
										$curLevel++;

									// A closing tag, decrease the level.
									else
									{
										$curLevel--;

										// Gotcha! Clean out this closing tag gone rogue.
										if ($curLevel === 0)
										{
											$parts[$j + 1] = $parts[$j + 2] = $parts[$j + 3] = $parts[$j + 4] = '';
											break;
										}
									}
								}
							}
						}

						// Otherwise, add this one to the list.
						else
						{
							if ($isCompetingTag)
							{
								if (!isset($competingElements[$tag]))
									$competingElements[$tag] = array();

								$competingElements[$tag][] = $parts[$i + 4];

								if (count($competingElements[$tag]) > 1)
									$parts[$i] .= '[/' . $tag . ']';
							}

							$inlineElements[$elementContent] = $tag;
						}
					}

				}

				// Closing tag.
				else
				{
					// Closing the block tag.
					if ($isBlockLevelTag)
					{
						// Close the elements that should've been closed by closing this tag.
						if (!empty($blockElements))
						{
							$addClosingTags = array();
							while ($element = array_pop($blockElements))
							{
								if ($element === $tag)
									break;

								// Still a block tag was open not equal to this tag.
								$addClosingTags[] = $element['type'];
							}

							if (!empty($addClosingTags))
								$parts[$i + 1] = '[/' . implode('][/', array_reverse($addClosingTags)) . ']' . $parts[$i + 1];

							// Apparently the closing tag was not found on the stack.
							if (!is_string($element) || $element !== $tag)
							{
								// Get rid of this particular closing tag, it was never opened.
								$parts[$i + 1] = substr($parts[$i + 1], 0, -1);
								$parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';
								continue;
							}
						}
						else
						{
							// Get rid of this closing tag!
							$parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';
							continue;
						}

						// Inline elements are still left opened?
						if (!empty($inlineElements))
						{
							// Close them first..
							$parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';

							// Then reopen them.
							$parts[$i + 5] = '[' . implode('][', array_keys($inlineElements)) . ']' . $parts[$i + 5];
						}
					}

					// Inline tag.
					else
					{
						// Are we expecting this tag to end?
						if (in_array($tag, $inlineElements))
						{
							foreach (array_reverse($inlineElements, true) as $tagContentToBeClosed => $tagToBeClosed)
							{
								// Closing it one way or the other.
								unset($inlineElements[$tagContentToBeClosed]);

								// Was this the tag we were looking for?
								if ($tagToBeClosed === $tag)
									break;

								// Nope, close it and look further!
								else
									$parts[$i] .= '[/' . $tagToBeClosed . ']';
							}

							if ($isCompetingTag && !empty($competingElements[$tag]))
							{
								array_pop($competingElements[$tag]);

								if (count($competingElements[$tag]) > 0)
									$parts[$i + 5] = '[' . $tag . $competingElements[$tag][count($competingElements[$tag]) - 1] . $parts[$i + 5];
							}
						}

						// Unexpected closing tag, ex-ter-mi-nate.
						else
							$parts[$i + 1] = $parts[$i + 2] = $parts[$i + 3] = $parts[$i + 4] = '';
					}
				}
			}

			// Close the code tags.
			if ($inCode)
				$parts[$i] .= '[/code]';

			// The same for nobbc tags.
			elseif ($inNoBbc)
				$parts[$i] .= '[/nobbc]';

			// Still inline tags left unclosed? Close them now, better late than never.
			elseif (!empty($inlineElements))
				$parts[$i] .= '[/' . implode('][/', array_reverse($inlineElements)) . ']';

			// Now close the block elements.
			if (!empty($blockElements))
				$parts[$i] .= '[/' . implode('][/', array_reverse($blockElements)) . ']';

			$text = implode('', $parts);
		}

		// Final clean up of back to back tags.
		$lastlen = 0;
		while (strlen($text) !== $lastlen)
			$lastlen = strlen($text = preg_replace($backToBackPattern, '', $text));

		return $text;
	}

	public function LoadBBC()
	{
		global $modSettings, $txt, $settings;

		if ($this->bbc !== null)
			return;

		// The below array makes it dead easy to add images to this control. Add it to the array and everything else is done for you!
		$this->bbc = array();
		$this->bbc[] = array(
			array(
				'image' => 'bold',
				'code' => 'b',
				'before' => '[b]',
				'after' => '[/b]',
				'description' => $txt['bold'],
			),
			array(
				'image' => 'italicize',
				'code' => 'i',
				'before' => '[i]',
				'after' => '[/i]',
				'description' => $txt['italic'],
			),
			array(
				'image' => 'underline',
				'code' => 'u',
				'before' => '[u]',
				'after' => '[/u]',
				'description' => $txt['underline']
			),
			array(
				'image' => 'strike',
				'code' => 's',
				'before' => '[s]',
				'after' => '[/s]',
				'description' => $txt['strike']
			),
			array(),
			array(
				'image' => 'pre',
				'code' => 'pre',
				'before' => '[pre]',
				'after' => '[/pre]',
				'description' => $txt['preformatted']
			),
			array(
				'image' => 'left',
				'code' => 'left',
				'before' => '[left]',
				'after' => '[/left]',
				'description' => $txt['left_align']
			),
			array(
				'image' => 'center',
				'code' => 'center',
				'before' => '[center]',
				'after' => '[/center]',
				'description' => $txt['center']
			),
			array(
				'image' => 'right',
				'code' => 'right',
				'before' => '[right]',
				'after' => '[/right]',
				'description' => $txt['right_align']
			),
		);
		$this->bbc[] = array(
			array(
				'image' => 'flash',
				'code' => 'flash',
				'before' => '[flash=200,200]',
				'after' => '[/flash]',
				'description' => $txt['flash']
			),
			array(
				'image' => 'img',
				'code' => 'img',
				'before' => '[img]',
				'after' => '[/img]',
				'description' => $txt['image']
			),
			array(
				'image' => 'url',
				'code' => 'url',
				'before' => '[url]',
				'after' => '[/url]',
				'description' => $txt['hyperlink']
			),
			array(
				'image' => 'email',
				'code' => 'email',
				'before' => '[email]',
				'after' => '[/email]',
				'description' => $txt['insert_email']
			),
			array(
				'image' => 'ftp',
				'code' => 'ftp',
				'before' => '[ftp]',
				'after' => '[/ftp]',
				'description' => $txt['ftp']
			),
			array(),
			array(
				'image' => 'glow',
				'code' => 'glow',
				'before' => '[glow=red,2,300]',
				'after' => '[/glow]',
				'description' => $txt['glow']
			),
			array(
				'image' => 'shadow',
				'code' => 'shadow',
				'before' => '[shadow=red,left]',
				'after' => '[/shadow]',
				'description' => $txt['shadow']
			),
			array(
				'image' => 'move',
				'code' => 'move',
				'before' => '[move]',
				'after' => '[/move]',
				'description' => $txt['marquee']
			),
			array(),
			array(
				'image' => 'nb',
				'code' => 'nb',
				'before' => '[nb]',
				'after' => '[/nb]',
				'description' => $txt['footnote']
			),
			array(
				'image' => 'sup',
				'code' => 'sup',
				'before' => '[sup]',
				'after' => '[/sup]',
				'description' => $txt['superscript']
			),
			array(
				'image' => 'sub',
				'code' => 'sub',
				'before' => '[sub]',
				'after' => '[/sub]',
				'description' => $txt['subscript']
			),
			array(
				'image' => 'tele',
				'code' => 'tt',
				'before' => '[tt]',
				'after' => '[/tt]',
				'description' => $txt['teletype']
			),
			array(),
			array(
				'image' => 'table',
				'code' => 'table',
				'before' => '[table]\n[tr]\n[td]',
				'after' => '[/td]\n[/tr]\n[/table]',
				'description' => $txt['table']
			),
			array(
				'image' => 'code',
				'code' => 'code',
				'before' => '[code]',
				'after' => '[/code]',
				'description' => $txt['bbc_code']
			),
			array(
				'image' => 'quote',
				'code' => 'quote',
				'before' => '[quote]',
				'after' => '[/quote]',
				'description' => $txt['bbc_quote']
			),
			array(),
			array(
				'image' => 'list',
				'code' => 'list',
				'before' => '[list]\n[li]',
				'after' => '[/li]\n[li][/li]\n[/list]',
				'description' => $txt['list_unordered']
			),
			array(
				'image' => 'orderlist',
				'code' => 'orderlist',
				'before' => '[list type=decimal]\n[li]',
				'after' => '[/li]\n[li][/li]\n[/list]',
				'description' => $txt['list_ordered']
			),
			array(
				'image' => 'hr',
				'code' => 'hr',
				'before' => '[hr]',
				'description' => $txt['horizontal_rule']
			),
		);

		// Show the toggle?
		if (empty($modSettings['disable_wysiwyg']))
		{
			$this->bbc[count($this->bbc) - 1][] = array();
			$this->bbc[count($this->bbc) - 1][] = array(
				'image' => 'unformat',
				'code' => 'unformat',
				'before' => '',
				'description' => $txt['unformat_text'],
			);
			$this->bbc[count($this->bbc) - 1][] = array(
				'image' => 'toggle',
				'code' => 'toggle',
				'before' => '',
				'description' => $txt['toggle_view'],
			);
		}

		// Fix up the last item in each row
		foreach ($this->bbc as $row => $tagRow)
			$this->bbc[$row][count($tagRow) - 1]['isLast'] = true;

		// Set a flag for later in the template
		$this->show_bbc = !empty($modSettings['enableBBC']) && !empty($settings['show_bbc']);

		// Deal with disabled tags
		$disabled_tags = array();
		if (!empty($modSettings['disabledBBC']))
			$disabled_tags = explode(',', $modSettings['disabledBBC']);
		if (empty($modSettings['enableEmbeddedFlash']))
			$disabled_tags[] = 'flash';

		$this->disabled_tags = array();
		foreach ($disabled_tags as $tag)
		{
			if ($tag == 'list')
				$this->disabled_tags['orderlist'] = true;

			$this->disabled_tags[trim($tag)] = true;
		}
	}

	public function LoadSmileys()
	{
		global $modSettings, $user_info, $txt;

		if ($this->smileys !== null)
			return;

		$this->smileys = array(
			'postform' => array(),
			'popup' => array(),
		);

		// Load smileys - don't bother to run a query if we're not using the database's ones anyhow.
		if (empty($modSettings['smiley_enable']) && $user_info['smiley_set'] != 'none')
			$this->smileys['postform'][] = array(
				'smileys' => array(
					array(
						'code' => ':)',
						'filename' => 'smiley.gif',
						'description' => $txt['icon_smiley'],
					),
					array(
						'code' => ';)',
						'filename' => 'wink.gif',
						'description' => $txt['icon_wink'],
					),
					array(
						'code' => ':D',
						'filename' => 'cheesy.gif',
						'description' => $txt['icon_cheesy'],
					),
					array(
						'code' => ';D',
						'filename' => 'grin.gif',
						'description' => $txt['icon_grin']
					),
					array(
						'code' => '>:(',
						'filename' => 'angry.gif',
						'description' => $txt['icon_angry'],
					),
					array(
						'code' => ':(',
						'filename' => 'sad.gif',
						'description' => $txt['icon_sad'],
					),
					array(
						'code' => ':o',
						'filename' => 'shocked.gif',
						'description' => $txt['icon_shocked'],
					),
					array(
						'code' => '8)',
						'filename' => 'cool.gif',
						'description' => $txt['icon_cool'],
					),
					array(
						'code' => '???',
						'filename' => 'huh.gif',
						'description' => $txt['icon_huh'],
					),
					array(
						'code' => '::)',
						'filename' => 'rolleyes.gif',
						'description' => $txt['icon_rolleyes'],
					),
					array(
						'code' => ':P',
						'filename' => 'tongue.gif',
						'description' => $txt['icon_tongue'],
					),
					array(
						'code' => ':-[',
						'filename' => 'embarrassed.gif',
						'description' => $txt['icon_embarrassed'],
					),
					array(
						'code' => ':-X',
						'filename' => 'lipsrsealed.gif',
						'description' => $txt['icon_lips'],
					),
					array(
						'code' => ':-\\',
						'filename' => 'undecided.gif',
						'description' => $txt['icon_undecided'],
					),
					array(
						'code' => ':-*',
						'filename' => 'kiss.gif',
						'description' => $txt['icon_kiss'],
					),
					array(
						'code' => ':\'(',
						'filename' => 'cry.gif',
						'description' => $txt['icon_cry'],
						'isLast' => true,
					),
				),
				'isLast' => true,
			);
		elseif ($user_info['smiley_set'] != 'none')
		{
			if (($temp = cache_get_data('posting_smileys', 480)) == null)
			{
				$request = $smcFunc['db_query']('', '
					SELECT code, filename, description, smiley_row, hidden
					FROM {db_prefix}smileys
					WHERE hidden IN (0, 2)
					ORDER BY smiley_row, smiley_order',
					array(
					)
				);
				while ($row = $smcFunc['db_fetch_assoc']($request))
				{
					$row['filename'] = htmlspecialchars($row['filename']);
					$row['description'] = htmlspecialchars($row['description']);

					$this->smileys[empty($row['hidden']) ? 'postform' : 'popup'][$row['smiley_row']]['smileys'][] = $row;
				}
				$smcFunc['db_free_result']($request);

				foreach ($this->smileys as $section => $smileyRows)
				{
					foreach ($smileyRows as $rowIndex => $smileys)
						$this->smileys[$section][$rowIndex]['smileys'][count($smileys['smileys']) - 1]['isLast'] = true;

					if (!empty($smileyRows))
						$this->smileys[$section][count($smileyRows) - 1]['isLast'] = true;
				}

				cache_put_data('posting_smileys', $context['smileys'], 480);
			}
			else
				$this->smileys = $temp;
		}
	}

	public static function sort_array_length($a, $b)
	{
		return strlen($a) < strlen($b) ? 1 : -1;
	}

	// Parses some bbc before sending into the database...
	public static function preparsecode(&$message, $previewing = false)
	{
		global $user_info, $modSettings, $smcFunc, $context;

		// This line makes all languages *theoretically* work even with the wrong charset ;).
		$message = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $message);

		// Clean up after nobbc ;).
		$message = preg_replace('~\[nobbc\](.+?)\[/nobbc\]~ie', '\'[nobbc]\' . strtr(\'$1\', array(\'[\' => \'&#91;\', \']\' => \'&#93;\', \':\' => \'&#58;\', \'@\' => \'&#64;\')) . \'[/nobbc]\'', $message);

		// Remove \r's... they're evil!
		$message = strtr($message, array("\r" => ''));

		// You won't believe this - but too many periods upsets apache it seems!
		$message = preg_replace('~\.{100,}~', '...', $message);

		// Trim off trailing quotes - these often happen by accident.
		while (substr($message, -7) == '[quote]')
			$message = substr($message, 0, -7);
		while (substr($message, 0, 8) == '[/quote]')
			$message = substr($message, 8);

		// Find all code blocks, work out whether we'd be parsing them, then ensure they are all closed.
		$in_tag = false;
		$had_tag = false;
		$codeopen = 0;
		if (preg_match_all('~(\[(/)*code(?:=[^\]]+)?\])~is', $message, $matches))
			foreach ($matches[0] as $index => $dummy)
			{
				// Closing?
				if (!empty($matches[2][$index]))
				{
					// If it's closing and we're not in a tag we need to open it...
					if (!$in_tag)
						$codeopen = true;
					// Either way we ain't in one any more.
					$in_tag = false;
				}
				// Opening tag...
				else
				{
					$had_tag = true;
					// If we're in a tag don't do nought!
					if (!$in_tag)
						$in_tag = true;
				}
			}

		// If we have an open tag, close it.
		if ($in_tag)
			$message .= '[/code]';
		// Open any ones that need to be open, only if we've never had a tag.
		if ($codeopen && !$had_tag)
			$message = '[code]' . $message;

		// Now that we've fixed all the code tags, let's fix the img and url tags...
		$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

		// Only mess with stuff outside [code] tags.
		for ($i = 0, $n = count($parts); $i < $n; $i++)
		{
			// It goes 0 = outside, 1 = begin tag, 2 = inside, 3 = close tag, repeat.
			if ($i % 4 == 0)
			{
				wedgeEditor::fixTags($parts[$i]);

				// Replace /me.+?\n with [me=name]dsf[/me]\n.
				if (strpos($user_info['name'], '[') !== false || strpos($user_info['name'], ']') !== false || strpos($user_info['name'], '\'') !== false || strpos($user_info['name'], '"') !== false)
					$parts[$i] = preg_replace('~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '$1[me=&quot;' . $user_info['name'] . '&quot;]$2[/me]', $parts[$i]);
				else
					$parts[$i] = preg_replace('~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '$1[me=' . $user_info['name'] . ']$2[/me]', $parts[$i]);

				if (!$previewing && strpos($parts[$i], '[html]') !== false)
				{
					if (allowedTo('admin_forum'))
						$parts[$i] = preg_replace('~\[html\](.+?)\[/html\]~ise', '\'[html]\' . strtr(un_htmlspecialchars(\'$1\'), array("\n" => \'&#13;\', \'  \' => \' &#32;\', \'[\' => \'&#91;\', \']\' => \'&#93;\')) . \'[/html]\'', $parts[$i]);

					// We should edit them out, or else if an admin edits the message they will get shown...
					else
						while (strpos($parts[$i], '[html]') !== false)
							$parts[$i] = preg_replace('~\[[/]?html\]~i', '', $parts[$i]);
				}

				// Let's look at the time tags...
				$parts[$i] = preg_replace('~\[time(?:=(absolute))*\](.+?)\[/time\]~ie', '\'[time]\' . (is_numeric(\'$2\') || @strtotime(\'$2\') == 0 ? \'$2\' : strtotime(\'$2\') - (\'$1\' == \'absolute\' ? 0 : (($modSettings[\'time_offset\'] + $user_info[\'time_offset\']) * 3600))) . \'[/time]\'', $parts[$i]);

				// Change the color specific tags to [color=the color].
				$parts[$i] = preg_replace('~\[(black|blue|green|red|white)\]~', '[color=$1]', $parts[$i]);		// First do the opening tags.
				$parts[$i] = preg_replace('~\[/(?:black|blue|green|red|white)\]~', '[/color]', $parts[$i]);		// And now do the closing tags

				// Make sure all tags are lowercase.
				$parts[$i] = preg_replace('~\[([/]?)(list|li|table|tr|td)((\s[^\]]+)*)\]~ie', '\'[$1\' . strtolower(\'$2\') . \'$3]\'', $parts[$i]);

				$list_open = substr_count($parts[$i], '[list]') + substr_count($parts[$i], '[list ');
				$list_close = substr_count($parts[$i], '[/list]');
				if ($list_close - $list_open > 0)
					$parts[$i] = str_repeat('[list]', $list_close - $list_open) . $parts[$i];
				if ($list_open - $list_close > 0)
					$parts[$i] = $parts[$i] . str_repeat('[/list]', $list_open - $list_close);

				$mistake_fixes = array(
					// Find [table]s not followed by [tr].
					'~\[table\](?![\s\x{A0}]*\[tr\])~su' => '[table][tr]',
					// Find [tr]s not followed by [td].
					'~\[tr\](?![\s\x{A0}]*\[td\])~su' => '[tr][td]',
					// Find [/td]s not followed by something valid.
					'~\[/td\](?![\s\x{A0}]*(?:\[td\]|\[/tr\]|\[/table\]))~su' => '[/td][/tr]',
					// Find [/tr]s not followed by something valid.
					'~\[/tr\](?![\s\x{A0}]*(?:\[tr\]|\[/table\]))~su' => '[/tr][/table]',
					// Find [/td]s incorrectly followed by [/table].
					'~\[/td\][\s\x{A0}]*\[/table\]~su' => '[/td][/tr][/table]',
					// Find [table]s, [tr]s, and [/td]s (possibly correctly) followed by [td].
					'~\[(table|tr|/td)\]([\s\x{A0}]*)\[td\]~su' => '[$1]$2[_td_]',
					// Now, any [td]s left should have a [tr] before them.
					'~\[td\]~s' => '[tr][td]',
					// Look for [tr]s which are correctly placed.
					'~\[(table|/tr)\]([\s\x{A0}]*)\[tr\]~su' => '[$1]$2[_tr_]',
					// Any remaining [tr]s should have a [table] before them.
					'~\[tr\]~s' => '[table][tr]',
					// Look for [/td]s followed by [/tr].
					'~\[/td\]([\s\x{A0}]*)\[/tr\]~su' => '[/td]$1[_/tr_]',
					// Any remaining [/tr]s should have a [/td].
					'~\[/tr\]~s' => '[/td][/tr]',
					// Look for properly opened [li]s which aren't closed.
					'~\[li\]([^\[\]]+?)\[li\]~s' => '[li]$1[_/li_][_li_]',
					'~\[li\]([^\[\]]+?)\[/list\]~s' => '[_li_]$1[_/li_][/list]',
					'~\[li\]([^\[\]]+?)$~s' => '[li]$1[/li]',
					// Lists - find correctly closed items/lists.
					'~\[/li\]([\s\x{A0}]*)\[/list\]~su' => '[_/li_]$1[/list]',
					// Find list items closed and then opened.
					'~\[/li\]([\s\x{A0}]*)\[li\]~su' => '[_/li_]$1[_li_]',
					// Now, find any [list]s or [/li]s followed by [li].
					'~\[(list(?: [^\]]*?)?|/li)\]([\s\x{A0}]*)\[li\]~su' => '[$1]$2[_li_]',
					// Allow for sub lists.
					'~\[/li\]([\s\x{A0}]*)\[list\]~' => '[_/li_]$1[list]',
					'~\[/list\]([\s\x{A0}]*)\[li\]~' => '[/list]$1[_li_]',
					// Any remaining [li]s weren't inside a [list].
					'~\[li\]~' => '[list][li]',
					// Any remaining [/li]s weren't before a [/list].
					'~\[/li\]~' => '[/li][/list]',
					// Put the correct ones back how we found them.
					'~\[_(li|/li|td|tr|/tr)_\]~' => '[$1]',
					// Images with no real url.
					'~\[img\]https?://.{0,7}\[/img\]~' => '',
				);

				// Fix up some use of tables without [tr]s, etc. (it has to be done more than once to catch it all.)
				for ($j = 0; $j < 3; $j++)
					$parts[$i] = preg_replace(array_keys($mistake_fixes), $mistake_fixes, $parts[$i]);

				// Now we're going to do full scale table checking...
				$table_check = $parts[$i];
				$table_offset = 0;
				$table_array = array();
				$table_order = array(
					'table' => 'td',
					'tr' => 'table',
					'td' => 'tr',
				);
				while (preg_match('~\[(/)*(table|tr|td)\]~', $table_check, $matches) != false)
				{
					// Keep track of where this is.
					$offset = strpos($table_check, $matches[0]);
					$remove_tag = false;

					// Is it opening?
					if ($matches[1] != '/')
					{
						// If the previous table tag isn't correct simply remove it.
						if ((!empty($table_array) && $table_array[0] != $table_order[$matches[2]]) || (empty($table_array) && $matches[2] != 'table'))
							$remove_tag = true;
						// Record this was the last tag.
						else
							array_unshift($table_array, $matches[2]);
					}
					// Otherwise is closed!
					else
					{
						// Only keep the tag if it's closing the right thing.
						if (empty($table_array) || ($table_array[0] != $matches[2]))
							$remove_tag = true;
						else
							array_shift($table_array);
					}

					// Removing?
					if ($remove_tag)
					{
						$parts[$i] = substr($parts[$i], 0, $table_offset + $offset) . substr($parts[$i], $table_offset + strlen($matches[0]) + $offset);
						// We've lost some data.
						$table_offset -= strlen($matches[0]);
					}

					// Remove everything up to here.
					$table_offset += $offset + strlen($matches[0]);
					$table_check = substr($table_check, $offset + strlen($matches[0]));
				}

				// Close any remaining table tags.
				foreach ($table_array as $tag)
					$parts[$i] .= '[/' . $tag . ']';
			}
		}

		// Put it back together!
		if (!$previewing)
			$message = strtr(implode('', $parts), array('  ' => '&nbsp; ', "\n" => '<br />', "\xC2\xA0" => '&nbsp;'));
		else
			$message = strtr(implode('', $parts), array('  ' => '&nbsp; ', "\xC2\xA0" => '&nbsp;'));

		// Now let's quickly clean up things that will slow our parser (which are common in posted code.)
		$message = strtr($message, array('[]' => '&#91;]', '[&#039;' => '&#91;&#039;'));
	}

	// This is very simple, and just removes things done by preparsecode.
	public static function un_preparsecode($message)
	{
		global $smcFunc;

		$parts = preg_split('~(\[/code\]|\[code(?:=[^\]]+)?\])~i', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

		// We're going to unparse only the stuff outside [code]...
		for ($i = 0, $n = count($parts); $i < $n; $i++)
		{
			// If $i is a multiple of four (0, 4, 8, ...) then it's not a code section...
			if ($i % 4 == 0)
			{
				$parts[$i] = preg_replace('~\[html\](.+?)\[/html\]~ie', '\'[html]\' . strtr(htmlspecialchars(\'$1\', ENT_QUOTES), array(\'\\&quot;\' => \'&quot;\', \'&amp;#13;\' => \'<br />\', \'&amp;#32;\' => \' \', \'&amp;#38;\' => \'&#38;\', \'&amp;#91;\' => \'[\', \'&amp;#93;\' => \']\')) . \'[/html]\'', $parts[$i]);

				// Attempt to un-parse the time to something less awful.
				$parts[$i] = preg_replace('~\[time\](\d{0,10})\[/time\]~ie', '\'[time]\' . timeformat(\'$1\', false) . \'[/time]\'', $parts[$i]);
			}
		}

		// Change breaks back to \n's and &nsbp; back to spaces.
		return preg_replace('~<br( /)?' . '>~', "\n", str_replace('&nbsp;', ' ', implode('', $parts)));
	}

	// Fix any URLs posted - ie. remove 'javascript:'.
	public static function fixTags(&$message)
	{
		global $modSettings;

		// WARNING: Editing the below can cause large security holes in your forum.
		// Edit only if you are sure you know what you are doing.

		$fixArray = array(
			// [img]http://...[/img] or [img width=1]http://...[/img]
			array(
				'tag' => 'img',
				'protocols' => array('http', 'https'),
				'embeddedUrl' => false,
				'hasEqualSign' => false,
				'hasExtra' => true,
			),
			// [url]http://...[/url]
			array(
				'tag' => 'url',
				'protocols' => array('http', 'https'),
				'embeddedUrl' => true,
				'hasEqualSign' => false,
			),
			// [url=http://...]name[/url]
			array(
				'tag' => 'url',
				'protocols' => array('http', 'https'),
				'embeddedUrl' => true,
				'hasEqualSign' => true,
			),
			// [iurl]http://...[/iurl]
			array(
				'tag' => 'iurl',
				'protocols' => array('http', 'https'),
				'embeddedUrl' => true,
				'hasEqualSign' => false,
			),
			// [iurl=http://...]name[/iurl]
			array(
				'tag' => 'iurl',
				'protocols' => array('http', 'https'),
				'embeddedUrl' => true,
				'hasEqualSign' => true,
			),
			// [ftp]ftp://...[/ftp]
			array(
				'tag' => 'ftp',
				'protocols' => array('ftp', 'ftps'),
				'embeddedUrl' => true,
				'hasEqualSign' => false,
			),
			// [ftp=ftp://...]name[/ftp]
			array(
				'tag' => 'ftp',
				'protocols' => array('ftp', 'ftps'),
				'embeddedUrl' => true,
				'hasEqualSign' => true,
			),
			// [flash]http://...[/flash]
			array(
				'tag' => 'flash',
				'protocols' => array('http', 'https'),
				'embeddedUrl' => false,
				'hasEqualSign' => false,
				'hasExtra' => true,
			),
		);

		// Fix each type of tag.
		foreach ($fixArray as $param)
			wedgeEditor::fixTag($message, $param['tag'], $param['protocols'], $param['embeddedUrl'], $param['hasEqualSign'], !empty($param['hasExtra']));

		// Now fix possible security problems with images loading links automatically...
		$message = preg_replace('~(\[img.*?\])(.+?)\[/img\]~eis', '\'$1\' . preg_replace(\'~action(=|%3d)(?!dlattach)~i\', \'action-\', \'$2\') . \'[/img]\'', $message);

		// Limit the size of images posted?
		if (!empty($modSettings['max_image_width']) || !empty($modSettings['max_image_height']))
		{
			// Find all the img tags - with or without width and height.
			preg_match_all('~\[img(\s+width=\d+)?(\s+height=\d+)?(\s+width=\d+)?\](.+?)\[/img\]~is', $message, $matches, PREG_PATTERN_ORDER);

			$replaces = array();
			foreach ($matches[0] as $match => $dummy)
			{
				// If the width was after the height, handle it.
				$matches[1][$match] = !empty($matches[3][$match]) ? $matches[3][$match] : $matches[1][$match];

				// Now figure out if they had a desired height or width...
				$desired_width = !empty($matches[1][$match]) ? (int) substr(trim($matches[1][$match]), 6) : 0;
				$desired_height = !empty($matches[2][$match]) ? (int) substr(trim($matches[2][$match]), 7) : 0;

				// One was omitted, or both.  We'll have to find its real size...
				if (empty($desired_width) || empty($desired_height))
				{
					list ($width, $height) = url_image_size(un_htmlspecialchars($matches[4][$match]));

					// They don't have any desired width or height!
					if (empty($desired_width) && empty($desired_height))
					{
						$desired_width = $width;
						$desired_height = $height;
					}
					// Scale it to the width...
					elseif (empty($desired_width) && !empty($height))
						$desired_width = (int) (($desired_height * $width) / $height);
					// Scale if to the height.
					elseif (!empty($width))
						$desired_height = (int) (($desired_width * $height) / $width);
				}

				// If the width and height are fine, just continue along...
				if ($desired_width <= $modSettings['max_image_width'] && $desired_height <= $modSettings['max_image_height'])
					continue;

				// Too bad, it's too wide.  Make it as wide as the maximum.
				if ($desired_width > $modSettings['max_image_width'] && !empty($modSettings['max_image_width']))
				{
					$desired_height = (int) (($modSettings['max_image_width'] * $desired_height) / $desired_width);
					$desired_width = $modSettings['max_image_width'];
				}

				// Now check the height, as well.  Might have to scale twice, even...
				if ($desired_height > $modSettings['max_image_height'] && !empty($modSettings['max_image_height']))
				{
					$desired_width = (int) (($modSettings['max_image_height'] * $desired_width) / $desired_height);
					$desired_height = $modSettings['max_image_height'];
				}

				$replaces[$matches[0][$match]] = '[img' . (!empty($desired_width) ? ' width=' . $desired_width : '') . (!empty($desired_height) ? ' height=' . $desired_height : '') . ']' . $matches[4][$match] . '[/img]';
			}

			// If any img tags were actually changed...
			if (!empty($replaces))
				$message = strtr($message, $replaces);
		}
	}

	// Fix a specific class of tag - ie. url with =.
	public static function fixTag(&$message, $myTag, $protocols, $embeddedUrl = false, $hasEqualSign = false, $hasExtra = false)
	{
		global $boardurl, $scripturl;

		if (preg_match('~^([^:]+://[^/]+)~', $boardurl, $match) != 0)
			$domain_url = $match[1];
		else
			$domain_url = $boardurl . '/';

		$replaces = array();

		if ($hasEqualSign)
			preg_match_all('~\[(' . $myTag . ')=([^\]]*?)\](?:(.+?)\[/(' . $myTag . ')\])?~is', $message, $matches);
		else
			preg_match_all('~\[(' . $myTag . ($hasExtra ? '(?:[^\]]*?)' : '') . ')\](.+?)\[/(' . $myTag . ')\]~is', $message, $matches);

		foreach ($matches[0] as $k => $dummy)
		{
			// Remove all leading and trailing whitespace.
			$replace = trim($matches[2][$k]);
			$this_tag = $matches[1][$k];
			$this_close = $hasEqualSign ? (empty($matches[4][$k]) ? '' : $matches[4][$k]) : $matches[3][$k];

			$found = false;
			foreach ($protocols as $protocol)
			{
				$found = strncasecmp($replace, $protocol . '://', strlen($protocol) + 3) === 0;
				if ($found)
					break;
			}

			if (!$found && $protocols[0] == 'http')
			{
				if (substr($replace, 0, 1) == '/')
					$replace = $domain_url . $replace;
				elseif (substr($replace, 0, 1) == '?')
					$replace = $scripturl . $replace;
				elseif (substr($replace, 0, 1) == '#' && $embeddedUrl)
				{
					$replace = '#' . preg_replace('~[^A-Za-z0-9_\-#]~', '', substr($replace, 1));
					$this_tag = 'iurl';
					$this_close = 'iurl';
				}
				else
					$replace = $protocols[0] . '://' . $replace;
			}
			elseif (!$found && $protocols[0] == 'ftp')
				$replace = $protocols[0] . '://' . preg_replace('~^(?!ftps?)[^:]+://~', '', $replace);
			elseif (!$found)
				$replace = $protocols[0] . '://' . $replace;

			if ($hasEqualSign && $embeddedUrl)
				$replaces[$matches[0][$k]] = '[' . $this_tag . '=' . $replace . ']' . (empty($matches[4][$k]) ? '' : $matches[3][$k] . '[/' . $this_close . ']');
			elseif ($hasEqualSign)
				$replaces['[' . $matches[1][$k] . '=' . $matches[2][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']';
			elseif ($embeddedUrl)
				$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']' . $matches[2][$k] . '[/' . $this_close . ']';
			else
				$replaces['[' . $matches[1][$k] . ']' . $matches[2][$k] . '[/' . $matches[3][$k] . ']'] = '[' . $this_tag . ']' . $replace . '[/' . $this_close . ']';
		}

		foreach ($replaces as $k => $v)
		{
			if ($k == $v)
				unset($replaces[$k]);
		}

		if (!empty($replaces))
			$message = strtr($message, $replaces);
	}

	// If we came from WYSIWYG then turn it back into BBC regardless. Make sure we tell it what item we're expecting to use.
	public static function preparseWYSIWYG($id)
	{
		if (!empty($_REQUEST[$id . '_mode']) && isset($_REQUEST[$id]))
		{
			$_REQUEST[$id] = self::html_to_bbc($_REQUEST[$id]);

			// We need to unhtml it now as it gets done shortly.
			$_REQUEST[$id] = un_htmlspecialchars($_REQUEST[$id]);

			// We need this for everything else.
			$_POST[$id] = $_REQUEST[$id];
		}
	}

	public function outputEditor()
	{
		global $context, $settings, $options, $txt, $modSettings, $scripturl;

		$smileycontainer = empty($this->editorOptions['custom_smiley_div']) ? ('smileyBox_' . $this->id) : $this->editorOptions['custom_smiley_div'];
		$bbccontainer = empty($this->editorOptions['custom_bbc_div']) ? ('bbcBox_' . $this->id) : $this->editorOptions['custom_bbc_div'];

		// Output the bbc area
		if ($this->show_bbc && empty($this->editorOptions['custom_bbc_div']))
			echo '
		<div id="bbcBox_', $this->id, '"></div>';

		// What about smileys?
		if ((!empty($this->smileys['postform']) || !empty($this->smileys['popup'])) && empty($this->editorOptions['custom_smiley_div']))
			echo '
		<div id="smileyBox_', $this->id, '"></div>';

		echo '
		<div>
			<div style="width: 98.8%;">
				<div>
					<textarea class="editor" name="', $this->id, '" id="', $this->id, '" rows="', $this->rows, '" cols="', $context['browser']['is_ie8'] ? '600' : $this->columns, '" onselect="storeCaret(this);" onclick="storeCaret(this);" onkeyup="storeCaret(this);" onchange="storeCaret(this);" tabindex="', $context['tabindex']++, '" style="width: ', $this->width, '; height: ', $this->height, ';', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? ' border: 1px solid red;' : '', '">', $this->value, '</textarea>
				</div>
				<div id="', $this->id, '_resizer" style="display: none; width: ', $this->width, '; padding: 0 2px;" class="richedit_resize"></div>
			</div>
		</div>
		<input type="hidden" name="', $this->id, '_mode" id="', $this->id, '_mode" value="0" />
		<script type="text/javascript"><!-- // --><![CDATA[';

		// Smileys
		if ((!empty($this->smileys['postform']) || !empty($this->smileys['popup'])) && !$this->disable_smiley_box)
		{
			echo '
			var oSmileyBox_', $this->id, ' = new smc_SmileyBox({
				sUniqueId: ', JavaScriptEscape($smileycontainer), ',
				sContainerDiv: ', JavaScriptEscape($smileycontainer), ',
				sClickHandler: ', JavaScriptEscape('oEditorHandle_' . $this->id . '.insertSmiley'), ',
				oSmileyLocations: {';

			foreach ($this->smileys as $location => $smileyRows)
			{
				echo '
					', $location, ': [';
				foreach ($smileyRows as $smileyRow)
				{
					echo '
						[';
					foreach ($smileyRow['smileys'] as $smiley)
						echo '
							{
								sCode: ', JavaScriptEscape($smiley['code']), ',
								sSrc: ', JavaScriptEscape($settings['smileys_url'] . '/' . $smiley['filename']), ',
								sDescription: ', JavaScriptEscape($smiley['description']), '
							}', empty($smiley['isLast']) ? ',' : '';

				echo '
						]', empty($smileyRow['isLast']) ? ',' : '';
				}
				echo '
					]', $location === 'postform' ? ',' : '';
			}
			echo '
				},
				sSmileyBoxTemplate: ', JavaScriptEscape('
					%smileyRows% %moreSmileys%
				'), ',
				sSmileyRowTemplate: ', JavaScriptEscape('
					<div>%smileyRow%</div>
				'), ',
				sSmileyTemplate: ', JavaScriptEscape('
					<img src="%smileySource%" align="bottom" alt="%smileyDescription%" title="%smileyDescription%" id="%smileyId%" />
				'), ',
				sMoreSmileysTemplate: ', JavaScriptEscape('
					<a href="#" id="%moreSmileysId%">[' . (!empty($this->smileys['postform']) ? $txt['more_smileys'] : $txt['more_smileys_pick']) . ']</a>
				'), ',
				sMoreSmileysLinkId: ', JavaScriptEscape('moreSmileys_' . $this->id), ',
				sMoreSmileysPopupTemplate: ', JavaScriptEscape('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
					<html>
						<head>
							<title>' . $txt['more_smileys_title'] . '</title>
							<link rel="stylesheet" type="text/css" href="' . $settings['theme_url'] . '/css/index' . $context['theme_variant'] . '.css?rc3" />
						</head>
						<body id="help_popup">
							<div class="padding windowbg">
								<div class="cat_bar">
									<h3>' . $txt['more_smileys_pick'] . '</h3>
									</h3>
								</div>
								<div class="padding">
									%smileyRows%
								</div>
								<div class="smalltext centertext">
									<a href="#" id="%moreSmileysCloseLinkId%">' . $txt['more_smileys_close_window'] . '</a>
								</div>
							</div>
						</body>
					</html>'), '
			});';
		}

		if ($this->show_bbc)
		{
			echo '
			var oBBCBox_', $this->id, ' = new smc_BBCButtonBox({
				sUniqueId: ', JavaScriptEscape($bbccontainer), ',
				sContainerDiv: ', JavaScriptEscape($bbccontainer), ',
				sButtonClickHandler: ', JavaScriptEscape('oEditorHandle_' . $this->id . '.handleButtonClick'), ',
				sSelectChangeHandler: ', JavaScriptEscape('oEditorHandle_' . $this->id . '.handleSelectChange'), ',
				aButtonRows: [';

			// Here loop through the array, printing the images/rows/separators!
			foreach ($this->bbc as $i => $buttonRow)
			{
				echo '
					[';
				foreach ($buttonRow as $tag)
				{
					// Is there a "before" part for this bbc button? If not, it can't be a button!!
					if (isset($tag['before']))
						echo '
						{
							sType: \'button\',
							bEnabled: ', empty($this->disabled_tags[$tag['code']]) ? 'true' : 'false', ',
							sImage: ', JavaScriptEscape($settings['images_url'] . '/bbc/' . $tag['image'] . '.gif'), ',
							sCode: ', JavaScriptEscape($tag['code']), ',
							sBefore: ', JavaScriptEscape($tag['before']), ',
							sAfter: ', isset($tag['after']) ? JavaScriptEscape($tag['after']) : 'null', ',
							sDescription: ', JavaScriptEscape($tag['description']), '
						}', empty($tag['isLast']) ? ',' : '';

					// Must be a divider then.
					else
						echo '
						{
							sType: \'divider\'
						}', empty($tag['isLast']) ? ',' : '';
				}

				// Add the select boxes to the first row.
				if ($i == 0)
				{
					// Show the font drop down...
					if (!isset($this->disabled_tags['font']))
						echo ',
						{
							sType: \'select\',
							sName: \'sel_face\',
							oOptions: {
								\'\': ', JavaScriptEscape($txt['font_face']), ',
								\'courier\': \'Courier\',
								\'arial\': \'Arial\',
								\'arial black\': \'Arial Black\',
								\'impact\': \'Impact\',
								\'verdana\': \'Verdana\',
								\'times new roman\': \'Times New Roman\',
								\'georgia\': \'Georgia\',
								\'andale mono\': \'Andale Mono\',
								\'trebuchet ms\': \'Trebuchet MS\',
								\'comic sans ms\': \'Comic Sans MS\'
							}
						}';

					// Font sizes anyone?
					if (!isset($this->disabled_tags['size']))
						echo ',
						{
							sType: \'select\',
							sName: \'sel_size\',
							oOptions: {
								\'\': ', JavaScriptEscape($txt['font_size']), ',
								\'s6\': \'6pt\',
								\'s8\': \'8pt\',
								\'s10\': \'10pt\',
								\'s12\': \'12pt\',
								\'s14\': \'14pt\',
								\'s18\': \'18pt\',
								\'s24\': \'24pt\'
							}
						}';

					// Print a drop down list for all the colors we allow!
					if (!isset($this->disabled_tags['color']))
						echo ',
						{
							sType: \'select\',
							sName: \'sel_color\',
							oOptions: {
								\'\': ', JavaScriptEscape($txt['change_color']), ',
								\'black\': ', JavaScriptEscape($txt['black']), ',
								\'red\': ', JavaScriptEscape($txt['red']), ',
								\'yellow\': ', JavaScriptEscape($txt['yellow']), ',
								\'pink\': ', JavaScriptEscape($txt['pink']), ',
								\'green\': ', JavaScriptEscape($txt['green']), ',
								\'orange\': ', JavaScriptEscape($txt['orange']), ',
								\'purple\': ', JavaScriptEscape($txt['purple']), ',
								\'blue\': ', JavaScriptEscape($txt['blue']), ',
								\'beige\': ', JavaScriptEscape($txt['beige']), ',
								\'brown\': ', JavaScriptEscape($txt['brown']), ',
								\'teal\': ', JavaScriptEscape($txt['teal']), ',
								\'navy\': ', JavaScriptEscape($txt['navy']), ',
								\'maroon\': ', JavaScriptEscape($txt['maroon']), ',
								\'limegreen\': ', JavaScriptEscape($txt['lime_green']), ',
								\'white\': ', JavaScriptEscape($txt['white']), '
							}
						}';
				}
				echo '
					]', $i == count($this->bbc) - 1 ? '' : ',';
			}
			echo '
				],
				sButtonTemplate: ', JavaScriptEscape('
					<img id="%buttonId%" src="%buttonSrc%" align="bottom" width="23" height="22" alt="%buttonDescription%" title="%buttonDescription%" />
				'), ',
				sButtonBackgroundImage: ', JavaScriptEscape($settings['images_url'] . '/bbc/bbc_bg.gif'), ',
				sButtonBackgroundImageHover: ', JavaScriptEscape($settings['images_url'] . '/bbc/bbc_hoverbg.gif'), ',
				sActiveButtonBackgroundImage: ', JavaScriptEscape($settings['images_url'] . '/bbc/bbc_hoverbg.gif'), ',
				sDividerTemplate: ', JavaScriptEscape('
					<img src="' . $settings['images_url'] . '/bbc/divider.gif" alt="|" style="margin: 0 3px 0 3px;" />
				'), ',
				sSelectTemplate: ', JavaScriptEscape('
					<select name="%selectName%" id="%selectId%" style="margin-bottom: 1ex; font-size: x-small;">
						%selectOptions%
					</select>
				'), ',
				sButtonRowTemplate: ', JavaScriptEscape('
					<div>%buttonRow%</div>
				'), '
			});';
		}

		// Now it's all drawn out we'll actually setup the box.
		echo '
			var oEditorHandle_', $this->id, ' = new smc_Editor({
				sSessionId: ', JavaScriptEscape($context['session_id']), ',
				sSessionVar: ', JavaScriptEscape($context['session_var']), ',
				sFormId: ', JavaScriptEscape($this->form), ',
				sUniqueId: ', JavaScriptEscape($this->id), ',
				bRTL: ', $txt['lang_rtl'] ? 'true' : 'false', ',
				bWysiwyg: ', $this->rich_active ? 'true' : 'false', ',
				sText: ', JavaScriptEscape($this->rich_active ? $this->rich_value : ''), ',
				sEditWidth: ', JavaScriptEscape($this->width), ',
				sEditHeight: ', JavaScriptEscape($this->height), ',
				bRichEditOff: ', empty($modSettings['disable_wysiwyg']) ? 'false' : 'true', ',
				oSmileyBox: ', !empty($this->smileys['postform']) && !$this->disable_smiley_box ? 'oSmileyBox_' . $this->id : 'null', ',
				oBBCBox: ', $this->show_bbc ? 'oBBCBox_' . $this->id : 'null', '
			});
			smf_editorArray[smf_editorArray.length] = oEditorHandle_', $this->id, ';';

		echo '
		// ]]></script>';
	}

	public function outputButtons()
	{
		global $context, $settings, $options, $txt, $modSettings, $scripturl;

		echo '
		<input type="submit" value="', isset($this->labels['post_button']) ? $this->labels['post_button'] : $txt['post'], '" tabindex="', $context['tabindex']++, '" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit" />';

		if ($this->preview_type)
			echo '
		<input type="submit" name="preview" value="', isset($this->labels['preview_button']) ? $this->labels['preview_button'] : $txt['preview'], '" tabindex="', $context['tabindex']++, '" onclick="', $this->preview_type == 2 ? 'return event.ctrlKey || previewPost();' : 'return submitThisOnce(this);', '" accesskey="p" class="button_submit" />';

		if ($context['show_spellchecking'])
			echo '
		<input type="button" value="', $txt['spell_check'], '" tabindex="', $context['tabindex']++, '" onclick="oEditorHandle_', $this->id, '.spellCheckStart();" class="button_submit" />';
	}

	public static function EditorCallback()
	{
		global $context, $smcFunc;

		checkSession('get');

		if (!isset($_REQUEST['view']) || !isset($_REQUEST['message']))
			fatal_lang_error('no_access', false);

		$context['sub_template'] = 'sendbody';

		$context['view'] = (int) $_REQUEST['view'];

		// Return the right thing for the mode.
		if ($context['view'])
		{
			$_REQUEST['message'] = strtr($_REQUEST['message'], array('#smcol#' => ';', '#smlt#' => '&lt;', '#smgt#' => '&gt;', '#smamp#' => '&amp;'));
			$context['message'] = wedgeEditor::bbc_to_html($_REQUEST['message']);
		}
		else
		{
			$_REQUEST['message'] = un_htmlspecialchars($_REQUEST['message']);
			$_REQUEST['message'] = strtr($_REQUEST['message'], array('#smcol#' => ';', '#smlt#' => '&lt;', '#smgt#' => '&gt;', '#smamp#' => '&amp;'));

			$context['message'] = wedgeEditor::html_to_bbc($_REQUEST['message']);
		}

		$context['message'] = $smcFunc['htmlspecialchars']($context['message']);
	}
}

?>