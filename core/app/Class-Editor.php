<?php
/**
 * Provides all functionality directly tied to the editor component.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

class wedit
{
	protected static $editorLoaded = false;
	public $bbc = null;
	public $disabled_bbc = null;
	public $smileys = null;
	public $show_bbc = false;
	protected $editorOptions = null;

	public function __construct($editorOptions)
	{
		global $settings, $options;

		if (!is_array($editorOptions))
			$editorOptions = array($editorOptions);

		// Needs an id that we will be using.
		assert(isset($editorOptions['id']));
		if (empty($editorOptions['value']))
			$editorOptions['value'] = '';

		// WYSIWYG only works if BBC is enabled
		$settings['disable_wysiwyg'] = !empty($settings['disable_wysiwyg']) || empty($settings['enableBBC']);

		// We should also disable it on devices that don't support contentEditable.
		$settings['disable_wysiwyg'] &= !we::is('android[-2.9],ios[-4.9],firefox[-3]') && !isset($_SERVER['HTTP_X_OPERAMINI_PHONE_UA']);

		$this->editorOptions = array(
			'id' => $editorOptions['id'],
			'value' => $editorOptions['value'],
			'rich_value' => wedit::bbc_to_html($editorOptions['value']),
			'rich_active' => !$settings['disable_wysiwyg'] && (!empty($options['wysiwyg_default']) || !empty($editorOptions['force_rich']) || !empty($_REQUEST[$editorOptions['id'] . '_mode'])),
			'disable_smiley_box' => !empty($editorOptions['disable_smiley_box']),
			'columns' => isset($editorOptions['columns']) ? $editorOptions['columns'] : 60,
			'rows' => isset($editorOptions['rows']) ? $editorOptions['rows'] : 15,
			'width' => isset($editorOptions['width']) ? $editorOptions['width'] : '70%',
			'height' => isset($editorOptions['height']) ? $editorOptions['height'] : '200px',
			'form' => isset($editorOptions['form']) ? $editorOptions['form'] : 'postmodify',
			'bbc_level' => !empty($editorOptions['bbc_level']) ? $editorOptions['bbc_level'] : 'full',
			'buttons' => !empty($editorOptions['buttons']) ? $editorOptions['buttons'] : array(),
			'labels' => !empty($editorOptions['labels']) ? $editorOptions['labels'] : array(),
			'custom_bbc_div' => !empty($editorOptions['custom_bbc_div']) ? $editorOptions['custom_bbc_div'] : '',
			'custom_smiley_div' => !empty($editorOptions['custom_smiley_div']) ? $editorOptions['custom_smiley_div'] : '',
			'drafts' => !empty($editorOptions['drafts']) ? $editorOptions['drafts'] : 'none',
			'entity_fields' => array($editorOptions['id']),
		);

		// Stuff to do once per page only.
		if (!self::$editorLoaded)
		{
			self::$editorLoaded = true;
			loadLanguage('Post');

			add_css_file('editor');
			add_js_file(array(
				'editor.js',
				'editor-func.js',
				'post.js'
			), false, false, array(
				'editor-func.js' => false,
				'post.js' => false
			));
		}

		$this->loadBBC();
		$this->loadSmileys();
	}

	public function __get($name)
	{
		if (isset($this->editorOptions[$name]))
			return $this->editorOptions[$name];
		return null;
	}

	private static function parse_smileys($a)
	{
		return '<img alt="' . htmlspecialchars($a[2]) . '" class="smiley ' . $a[1] . '" src="' . ASSETS . '/blank.gif" onresizestart="return false;">';
	}

	private static function unparse_smileys($a)
	{
		return ' <div class="smiley ' . $a[2] . '">' . un_htmlspecialchars(isset($a[3]) ? $a[3] : $a[1]) . '</div>';
	}

	private static function unparse_td($a)
	{
		return str_repeat('[td][/td]', $a[1] - 1) . '[td]';
	}

	private static function fix_img_links($a)
	{
		return $a[1] . preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $a[2]) . '[/img]';
	}

	private static function protect_html($a)
	{
		return '[html]' . strtr(un_htmlspecialchars($a[1]), array("\n" => '&#13;', '  ' => ' &#32;', '[' => '&#91;', ']' => '&#93;')) . '[/html]';
	}

	// !! These don't exactly match the function above... Oversight?
	private static function unprotect_html($a)
	{
		return '[html]' . strtr(htmlspecialchars($a[1], ENT_QUOTES), array('\\&quot;' => '&quot;', '&amp;#13;' => '<br>', '&amp;#32;' => ' ', '&amp;#91;' => '[', '&amp;#93;' => ']')) . '[/html]';
	}

	private static function preparse_time($a)
	{
		global $settings;
		return '[time]' . (is_numeric($a[2]) || @strtotime($a[2]) == 0 ? $a[2] : strtotime($a[2]) - ($a[1] == 'absolute' ? 0 : (($settings['time_offset'] + we::$user['time_offset']) * 3600))) . '[/time]';
	}

	private static function format_time($a)
	{
		return '[time]' . timeformat($a[1], false) . '[/time]';
	}

	private static function lowercase_tags($a)
	{
		return '[' . $a[1] . strtolower($a[2]) . $a[3] . ']';
	}

	public function add_button($name, $button_text, $onclick = '', $access_key = '', $class = '')
	{
		// This allows us to add buttons to it from code side since we don't let users manipulate this array directly otherwise.
		$this->editorOptions['buttons'][] = array(
			'name' => $name,
			'button_text' => $button_text,
			'onclick' => $onclick,
			'access_key' => $access_key,
			'class' => $class,
		);
	}

	public static function bbc_to_html($text)
	{
		// Turn line breaks back into br's.
		$text = strtr($text, array("\r" => '', "\n" => '<br>'));

		// Prevent conversion of all BBCode inside these tags.
		if (strihas($text, array('[code', '[php', '[nobbc')))
		{
			// Only mess with stuff inside tags.
			$parts = self::protect_string($text);
			foreach ($parts as $i => $part)
				if (self::is_protected($part))
					$parts[$i] = strtr($part, array('[' => '&#91;', ']' => '&#93;', "'" => "'"));

			// Put our humpty dumpty message back together again.
			$text = implode('', $parts);
		}

		// What tags do we allow?
		$allowed_tags = array('b', 'u', 'i', 's', 'hr', 'list', 'li', 'font', 'size', 'color', 'img', 'left', 'center', 'right', 'url', 'email', 'ftp', 'sub', 'sup');

		$text = parse_bbc($text, 'post-convert', array('tags' => $allowed_tags));

		// Fix for having a line break then a thingy.
		$text = strtr($text, array('<br><div' => '<div', "\n" => '', "\r" => ''));

		// Note that IE doesn't understand spans really - make them something "legacy"
		$working_html = array(
			'~<del>(.+?)</del>~i' => '<strike>$1</strike>',
			'~<span\sclass="bbc_u">(.+?)</span>~i' => '<u>$1</u>',
			'~<span\sstyle="color:\s*([#\d\w]+);?" class="bbc_color">(.+?)</span>~i' => '<font color="$1">$2</font>',
			'~<span\sstyle="font-family:\s*([#\d\w\s]+);?" class="bbc_font">(.+?)</span>~i' => '<font face="$1">$2</font>',
			'~<div\sstyle="text-align:\s*(left|right);?">(.+?)</div>~i' => '<p align="$1">$2</p>',
		);
		$text = preg_replace(array_keys($working_html), array_values($working_html), $text);

		// Parse smileys into something browsable.
		$text = preg_replace_callback('~(?:\s|&nbsp;)?<i class="smiley ([^<>]+?)"[^<>]*?>([^<]*)</i>~', 'wedit::parse_smileys', $text);

		return $text;
	}

	public static function html_to_bbc($text)
	{
		global $context;

		// Replace newlines with spaces, as that's how browsers usually interpret them.
		$text = preg_replace("~\s*[\r\n]+\s*~", ' ', $text);

		// Though some of us love paragraphs, the parser will do better with breaks.
		$text = preg_replace('~</p>\s*?<p~i', '</p><br><p', $text);
		$text = preg_replace('~</p>\s*(?!<)~i', '</p><br>', $text);

		// Safari/webkit wraps lines in Wysiwyg in <div>'s.
		if (we::is('webkit'))
			$text = preg_replace(array('~<div(?:\s(?:[^<>]*?))?\>~i', '</div>'), array('<br>', ''), $text);

		// If there's a trailing break get rid of it - Firefox tends to add one.
		$text = preg_replace('~<br\s*/?\>$~i', '', $text);

		// Remove any formatting within code tags.
		if (strihas($text, array('[code', '[php', '[nobbc')))
		{
			$text = preg_replace('~<br\s*/?\>~i', '#wedge_br_nao_was_here#', $text);

			// Only mess with stuff outside [code] tags.
			$parts = self::protect_string($text);
			foreach ($parts as $i => $part)
				if (self::is_protected($part))
					$parts[$i] = strip_tags($parts[$i]);

			$text = strtr(implode('', $parts), array('#wedge_br_nao_was_here#' => '<br>'));
		}

		// Remove scripts, style and comment blocks.
		$text = preg_replace('~<script[^>]*[^/]>.*?</script>~i', '', $text);
		$text = preg_replace('~<style[^>]*[^/]>.*?</style>~i', '', $text);
		$text = preg_replace('~\\<\\!--.*?-->~i', '', $text);
		$text = preg_replace('~\\<\\!\\[CDATA\\[.*?\\]\\]\\>~i', '', $text);

		// Do the smileys ultra fast!
		$text = preg_replace_callback('~<img(?:[^>]*\salt="([^"]+)")?[^>]*\sclass="smiley ([^"]+)"(?:[^>]*\salt="([^"]+)")?[^>]*>~', 'wedit::unparse_smileys', $text);

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
								if ($style_value === 'underline')
								{
									$curCloseTags .= '[/u]';
									$replacement .= '[u]';
								}
								elseif ($style_value === 'line-through')
								{
									$curCloseTags .= '[/s]';
									$replacement .= '[s]';
								}
							break;

							case 'text-align':
								if ($style_value === 'left')
								{
									$curCloseTags .= '[/left]';
									$replacement .= '[left]';
								}
								elseif ($style_value === 'center')
								{
									$curCloseTags .= '[/center]';
									$replacement .= '[center]';
								}
								elseif ($style_value === 'right')
								{
									$curCloseTags .= '[/right]';
									$replacement .= '[right]';
								}
							break;

							case 'font-style':
								if ($style_value === 'italic')
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

					// Preserve some tags stripping the styling.
					if ($matches[2] === 'a' || $matches[2] === 'font')
					{
						$replacement .= $precedingStyle . $afterStyle;
						$curCloseTags = '</' . $matches[2] . '>' . $curCloseTags;
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
		// !!! @todo: After the <, add this if [bareimg] is implemented: (?!(?:bare)?img)
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
			// Just get rid of this evil tag.
			else
				$text = substr($text, 0, $start_pos) . substr($text, $start_pos + strlen($matches[0]));
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
			$attribs = wedit::fetchTagAttributes($matches[1]);
			$tags = array();
			foreach ($attribs as $s => $v)
			{
				if ($s === 'size')
					$tags[] = array('[size=' . (int) trim($v) . ']', '[/size]');
				elseif ($s === 'face')
					$tags[] = array('[font=' . trim(strtolower($v)) . ']', '[/font]');
				elseif ($s === 'color')
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

			// Map what we can expect from the HTML to what is supported by Wedge.
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
								$parts[$i] = preg_replace('~\s*<br\s*/?\>\s*$~', '', $parts[$i]);
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
				if ($inList && trim(preg_replace('~\s*<br\s*/?\>\s*~', '', $parts[$i + 4])) !== '')
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

			$attrs = wedit::fetchTagAttributes($matches[1]);
			foreach ($attrs as $attrib => $value)
			{
				if (in_array($attrib, array('width', 'height')))
					$params .= ' ' . $attrib . '=' . (int) $value;
				elseif ($attrib === 'alt' && trim($value) != '')
					$params .= ' alt=' . trim($value);
				elseif ($attrib === 'align' && trim($value) != '')
					$params .= ' align=' . trim($value);
				elseif ($attrib === 'src')
					$src = trim($value);
			}

			$tag = '';
			if (!empty($src))
			{
				// Attempt to fix the path in case it's not present.
				if (preg_match('~^https?://~i', $src) === 0 && is_array($parsedURL = parse_url(SCRIPT)) && isset($parsedURL['host']))
				{
					$baseURL = (isset($parsedURL['scheme']) ? $parsedURL['scheme'] : 'http') . '://' . $parsedURL['host'] . (empty($parsedURL['port']) ? '' : ':' . $parsedURL['port']);

					if ($src[0] === '/')
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
			'~<b(?:\s.*?)*?\>~i' => '[b]',
			'~</b>~i' => '[/b]',
			'~<i(?:\s.*?)*?\>~i' => '[i]',
			'~</i>~i' => '[/i]',
			'~<u(?:\s.*?)*?\>~i' => '[u]',
			'~</u>~i' => '[/u]',
			'~<strong(?:\s.*?)*?\>~i' => '[b]',
			'~</strong>~i' => '[/b]',
			'~<em(?:\s.*?)*?\>~i' => '[i]',
			'~</em>~i' => '[/i]',
			'~<s(?:\s.*?)*?\>~i' => '[s]',
			'~</s>~i' => '[/s]',
			'~<strike(?:\s.*?)*?\>~i' => '[s]',
			'~</strike>~i' => '[/s]',
			'~<del(?:\s.*?)*?\>~i' => '[s]',
			'~</del>~i' => '[/s]',
			'~<ins(?:\s.*?)*?\>~i' => '[u]',
			'~</ins>~i' => '[/u]',
			'~<center(?:\s.*?)*?\>~i' => '[center]',
			'~</center>~i' => '[/center]',
			'~<pre(?:\s.*?)*?\>~i' => '[pre]',
			'~</pre>~i' => '[/pre]',
			'~<sub(?:\s.*?)*?\>~i' => '[sub]',
			'~</sub>~i' => '[/sub]',
			'~<sup(?:\s.*?)*?\>~i' => '[sup]',
			'~</sup>~i' => '[/sup]',
			'~<tt(?:\s.*?)*?\>~i' => '[tt]',
			'~</tt>~i' => '[/tt]',
			'~<table(?:\s.*?)*?\>~i' => '[table]',
			'~</table>~i' => '[/table]',
			'~<tr(?:\s.*?)*?\>~i' => '[tr]',
			'~</tr>~i' => '[/tr]',
			'~<(?:td|th)(?:\s.*?)*?\>~i' => '[td]',
			'~</(?:td|th)>~i' => '[/td]',
			'~<br(?:\s[^<>]*?)?\>~i' => "\n",
			'~<hr(?:\s[^<>]*?)?\>(\n)?~i' => "[hr]\n$1",
			'~\n?\\[hr\\]~i' => "\n[hr]",
			'~^\n\\[hr\\]~i' => '[hr]',
			'~<blockquote(?:\s.*?)*?\>~i' => '&lt;blockquote&gt;',
			'~</blockquote>~i' => '&lt;/blockquote&gt;',
		);
		$text = preg_replace(array_keys($tags), array_values($tags), $text);
		$text = preg_replace_callback('~<(?:td|th)\s[^<>]*?colspan="?(\d{1,2})"?[^>]*>~i', 'wedit::unparse_td', $text);

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

			$attrs = wedit::fetchTagAttributes($matches[1]);
			foreach ($attrs as $attrib => $value)
			{
				if ($attrib === 'href')
				{
					$href = trim($value);

					// Are we dealing with an FTP link?
					if (preg_match('~^ftps?://~', $href) === 1)
						$tag_type = 'ftp';

					// Or is this a link to an email address?
					elseif (strpos($href, 'mailto:') === 0)
					{
						$tag_type = 'email';
						$href = substr($href, 7);
					}

					// No http(s), so attempt to fix this potential relative URL.
					elseif (preg_match('~^https?://~i', $href) === 0 && is_array($parsedURL = parse_url(SCRIPT)) && isset($parsedURL['host']))
					{
						$baseURL = (isset($parsedURL['scheme']) ? $parsedURL['scheme'] : 'http') . '://' . $parsedURL['host'] . (empty($parsedURL['port']) ? '' : ':' . $parsedURL['port']);

						if ($href[0] === '/')
							$href = $baseURL . $href;
						else
							$href = $baseURL . (empty($parsedURL['path']) ? '/' : preg_replace('~/(?:index\\.php)?$~', '', $parsedURL['path'])) . '/' . $href;
					}
				}

				// External URL?
				if ($attrib === 'target' && $tag_type === 'url')
				{
					if (trim($value) === '_blank')
						$tag_type === 'iurl';
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

		$text = wedit::legalize_bbc($text);

		return $text;
	}

	public static function fetchTagAttributes($text)
	{
		$attribs = array();
		$key = $value = '';
		$tag_state = 0; // 0 = key, 1 = attribute with no string, 2 = attribute with string
		for ($i = 0; $i < strlen($text); $i++)
		{
			// We're either moving from the key to the attribute or we're in a string and this is fine.
			if ($text{$i} === '=')
			{
				if ($tag_state == 0)
					$tag_state = 1;
				elseif ($tag_state == 2)
					$value .= '=';
			}
			// A space is either moving from an attribute back to a potential key or in a string is fine.
			elseif ($text{$i} === ' ')
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
			elseif ($text{$i} === '"')
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
	public static function legalize_bbc($text)
	{
		global $settings;

		// Don't care about the texts that are too short.
		if (strlen($text) < 3)
			return $text;

		// We are going to cycle through the BBC and keep track of tags as they arise - in order. If get to a block level tag we're going to make sure it's not in a non-block level tag!
		// This will keep the order of tags that are open.
		$current_tags = array();

		// This will quickly let us see if the tag is active.
		$active_tags = array();

		// A list of tags that's disabled by the admin.
		$disabled = empty($settings['disabledBBC']) ? array() : array_flip(explode(',', strtolower($settings['disabledBBC'])));

		// Add flash if it's disabled as embedded tag.
		if (empty($settings['enableEmbeddedFlash']))
			$disabled['flash'] = true;

		// Get a list of all the tags that are not disabled.
		$all_tags = parse_bbc(false);
		$valid_tags = array();
		$self_closing_tags = array();
		foreach ($all_tags as $tag)
		{
			if (!isset($disabled[$tag['tag']]))
				$valid_tags[$tag['tag']] = !empty($tag['block_level']);
			if (isset($tag['type']) && $tag['type'] === 'closed')
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
					$isClosingTag = $match[1] === '/';
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
		uksort($valid_tags, array('wedit', 'sort_array_length'));

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
							// !!! $inlineElements = array(); ??
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
							// !! $inlineElements = array(); ??
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

	public function loadBBC()
	{
		global $settings, $txt;

		if ($this->bbc !== null)
			return;

		// The array below makes it dead easy to add images to this control.
		// Add a button to the array through the hook, and everything else is done for you!
		// 'image' can hold a full button URL, or the position within the default bbcode sprite (sprite.png)
		// The first column is taken by the button backgrounds; array(5, 1) means "6th button, 2nd row".
		$this->bbc = array();
		$this->bbc[] = array(
			array(
				'image' => array(5, 1),
				'code' => 'b',
				'before' => '[b]',
				'after' => '[/b]',
				'description' => $txt['bold'],
			),
			array(
				'image' => array(1, 1),
				'code' => 'i',
				'before' => '[i]',
				'after' => '[/i]',
				'description' => $txt['italic'],
			),
			array(
				'image' => array(4, 1),
				'code' => 'u',
				'before' => '[u]',
				'after' => '[/u]',
				'description' => $txt['underline']
			),
			array(
				'image' => array(2, 1),
				'code' => 's',
				'before' => '[s]',
				'after' => '[/s]',
				'description' => $txt['strike']
			),
			array(),
			array(
				'image' => array(9, 1),
				'code' => 'pre',
				'before' => '[pre]',
				'after' => '[/pre]',
				'description' => $txt['preformatted']
			),
			array(
				'image' => array(6, 1),
				'code' => 'left',
				'before' => '[left]',
				'after' => '[/left]',
				'description' => $txt['left_align']
			),
			array(
				'image' => array(7, 1),
				'code' => 'center',
				'before' => '[center]',
				'after' => '[/center]',
				'description' => $txt['center']
			),
			array(
				'image' => array(8, 1),
				'code' => 'right',
				'before' => '[right]',
				'after' => '[/right]',
				'description' => $txt['right_align']
			),
		);
		$this->bbc[] = array(
			array(
				'image' => array(13, 1),
				'code' => 'add_media',
				'before' => '',
				'description' => $txt['media'],
			),
			array(
				'image' => array(7, 0),
				'code' => 'flash',
				'before' => '[flash=200,200]',
				'after' => '[/flash]',
				'description' => $txt['flash']
			),
			array(
				'image' => array(2, 0),
				'code' => 'img',
				'before' => '[img]',
				'after' => '[/img]',
				'description' => $txt['image']
			),
			array(
				'image' => array(12, 1),
				'code' => 'url',
				'before' => '[url]',
				'after' => '[/url]',
				'description' => $txt['hyperlink']
			),
			array(
				'image' => array(3, 0),
				'code' => 'email',
				'before' => '[email]',
				'after' => '[/email]',
				'description' => $txt['insert_email']
			),
			array(
				'image' => array(12, 0),
				'code' => 'ftp',
				'before' => '[ftp]',
				'after' => '[/ftp]',
				'description' => $txt['ftp']
			),
			array(),
			array(
				'image' => array(11, 0),
				'code' => 'nb',
				'before' => '[nb]',
				'after' => '[/nb]',
				'description' => $txt['footnote']
			),
			array(
				'image' => array(8, 0),
				'code' => 'sup',
				'before' => '[sup]',
				'after' => '[/sup]',
				'description' => $txt['superscript']
			),
			array(
				'image' => array(9, 0),
				'code' => 'sub',
				'before' => '[sub]',
				'after' => '[/sub]',
				'description' => $txt['subscript']
			),
			array(
				'image' => array(10, 0),
				'code' => 'tt',
				'before' => '[tt]',
				'after' => '[/tt]',
				'description' => $txt['teletype']
			),
			array(),
			array(
				'image' => array(4, 0),
				'code' => 'table',
				'before' => '[table]\n[tr]\n[td]',
				'after' => '[/td]\n[/tr]\n[/table]',
				'description' => $txt['table']
			),
			array(
				'image' => array(3, 1),
				'code' => 'code',
				'before' => '[code]',
				'after' => '[/code]',
				'description' => $txt['bbc_code']
			),
			array(
				'image' => array(0, 0),
				'code' => 'spoiler',
				'before' => '[spoiler]',
				'after' => '[/spoiler]',
				'description' => $txt['bbc_spoiler']
			),
			array(
				'image' => array(1, 0),
				'code' => 'quote',
				'before' => '[quote]',
				'after' => '[/quote]',
				'description' => $txt['bbc_quote']
			),
			array(),
			array(
				'image' => array(11, 1),
				'code' => 'list',
				'before' => '[list]\n[li]',
				'after' => '[/li]\n[li][/li]\n[/list]',
				'description' => $txt['list_unordered']
			),
			array(
				'image' => array(10, 1),
				'code' => 'orderlist',
				'before' => '[list type=decimal]\n[li]',
				'after' => '[/li]\n[li][/li]\n[/list]',
				'description' => $txt['list_ordered']
			),
			array(
				'image' => array(0, 1),
				'code' => 'hr',
				'before' => '[hr]',
				'description' => $txt['horizontal_rule']
			),
			array(
				'image' => array(13, 0),
				'code' => 'more',
				'before' => '[more]',
				'description' => $txt['more_bbc'],
			),
		);

		// Allow mods to modify BBC buttons.
		// Read the PHP docs on array_splice() to
		// position a button in a specific place
		call_hook('bbc_buttons', array(&$this->bbc));

		// Show the toggle?
		if (!$settings['disable_wysiwyg'])
			array_push(
				$this->bbc[count($this->bbc) - 1],
				array(),
				array(
					'image' => array(6, 0),
					'code' => 'unformat',
					'before' => '',
					'description' => $txt['unformat_text'],
				),
				array(
					'image' => array(5, 0),
					'code' => 'toggle',
					'before' => '',
					'description' => $txt['toggle_view'],
				)
			);

		// Fix up the last item in each row
		foreach ($this->bbc as $row => $tagRow)
			$this->bbc[$row][count($tagRow) - 1]['isLast'] = true;

		// Set a flag for later in the template
		$this->show_bbc = !empty($settings['enableBBC']);

		// Deal with disabled tags
		$disabled_tags = array();
		if (!empty($settings['disabledBBC']))
			$disabled_tags = explode(',', $settings['disabledBBC']);
		if (empty($settings['enableEmbeddedFlash']))
			$disabled_tags[] = 'flash';
		if (empty($settings['media_enabled']))
			$disabled_tags[] = 'add_media';

		$this->disabled_tags = array();
		foreach ($disabled_tags as $tag)
		{
			if ($tag === 'list')
				$this->disabled_tags['orderlist'] = true;

			$this->disabled_tags[trim($tag)] = true;
		}
	}

	public function loadSmileys()
	{
		global $settings, $txt;

		if ($this->smileys !== null)
			return;

		$this->smileys = array(
			'postform' => array(),
			'popup' => array(),
		);

		// Load smileys - don't bother to run a query if we're not using the database's ones anyhow.
		if (empty($settings['smiley_enable']) && we::$user['smiley_set'] !== 'none')
		{
			$this->smileys['postform'][] = array(
				'smileys' => array(
					array(
						'code' => ':)',
						'class' => 'smiley_gif',
						'description' => $txt['icon_smiley'],
					),
					array(
						'code' => ';)',
						'class' => 'wink_gif',
						'description' => $txt['icon_wink'],
					),
					array(
						'code' => ':D',
						'class' => 'cheesy_gif',
						'description' => $txt['icon_cheesy'],
					),
					array(
						'code' => ';D',
						'class' => 'grin_gif',
						'description' => $txt['icon_grin']
					),
					array(
						'code' => '>:(',
						'class' => 'angry_gif',
						'description' => $txt['icon_angry'],
					),
					array(
						'code' => ':(',
						'class' => 'sad_gif',
						'description' => $txt['icon_sad'],
					),
					array(
						'code' => ':o',
						'class' => 'shocked_gif',
						'description' => $txt['icon_shocked'],
					),
					array(
						'code' => '8)',
						'class' => 'cool_gif',
						'description' => $txt['icon_cool'],
					),
					array(
						'code' => '???',
						'class' => 'huh_gif',
						'description' => $txt['icon_huh'],
					),
					array(
						'code' => '::)',
						'class' => 'rolleyes_gif',
						'description' => $txt['icon_rolleyes'],
					),
					array(
						'code' => ':P',
						'class' => 'tongue_gif',
						'description' => $txt['icon_tongue'],
					),
					array(
						'code' => ':-[',
						'class' => 'embarrassed_gif',
						'description' => $txt['icon_embarrassed'],
					),
					array(
						'code' => ':-X',
						'class' => 'lipsrsealed_gif',
						'description' => $txt['icon_lips'],
					),
					array(
						'code' => ':-\\',
						'class' => 'undecided_gif',
						'description' => $txt['icon_undecided'],
					),
					array(
						'code' => ':-*',
						'class' => 'kiss_gif',
						'description' => $txt['icon_kiss'],
					),
					array(
						'code' => ':\'(',
						'class' => 'cry_gif',
						'description' => $txt['icon_cry'],
						'isLast' => true,
					),
				),
				'isLast' => true,
			);
			$this->smileys['popup'][] = array(
				'smileys' => array(
					array(
						'code' => 'O0',
						'class' => 'afro_gif',
						'description' => '',
					),
					array(
						'code' => '>:D',
						'class' => 'evil_gif',
						'description' => '',
					),
					array(
						'code' => '^-^',
						'class' => 'azn_gif',
						'description' => '',
					),
					array(
						'code' => ':))',
						'class' => 'laugh_gif',
						'description' => '',
					),
					array(
						'code' => 'O:-)',
						'class' => 'angel_gif',
						'description' => '',
					),
					array(
						'code' => 'C:-)',
						'class' => 'police_gif',
						'description' => '',
					),
					array(
						'code' => ':edit:',
						'class' => 'edit_gif',
						'description' => $txt['icon_edit'],
						'isLast' => true,
					),
				),
				'isLast' => true,
			);
		}
		elseif (we::$user['smiley_set'] !== 'none')
		{
			if (($temp = cache_get_data('smiley_poster', 'forever')) === null)
			{
				$request = wesql::query('
					SELECT code, filename, description, smiley_row, hidden
					FROM {db_prefix}smileys
					WHERE hidden IN (0, 2)
					ORDER BY smiley_row, smiley_order'
				);
				while ($row = wesql::fetch_assoc($request))
				{
					$row['class'] = preg_replace(array('~[^\w]~', '~_+~'), array('_', '_'), $row['filename']);
					$row['description'] = htmlspecialchars($row['description']);

					$this->smileys[empty($row['hidden']) ? 'postform' : 'popup'][$row['smiley_row']]['smileys'][] = $row;
				}
				wesql::free_result($request);

				foreach ($this->smileys as $section => $smileyRows)
				{
					foreach ($smileyRows as $rowIndex => $smileys)
						$this->smileys[$section][$rowIndex]['smileys'][count($smileys['smileys']) - 1]['isLast'] = true;

					if (!empty($smileyRows))
						$this->smileys[$section][count($smileyRows) - 1]['isLast'] = true;
				}

				cache_put_data('smiley_poster', $this->smileys, 'forever');
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
	public static function preparsecode(&$message, $previewing = false, &$post_errors = null)
	{
		// This line makes all languages *theoretically* work even with the wrong charset ;)
		$message = preg_replace('~&amp;#(\d{4,5}|[2-9]\d{2,4}|1[2-9]\d);~', '&#$1;', $message);

		// Clean up after nobbc ;)
		$message = preg_replace_callback('~\[nobbc\](.+?)\[/nobbc\]~is', function ($a) {
			return '[nobbc]' . strtr($a[1], array('[' => '&#91;', ']' => '&#93;', '://' => '&#58;//', '@' => '&#64;', 'www.' => 'www&#46;')) . '[/nobbc]';
		}, $message);

		// Remove \r's... they're evil!
		$message = strtr($message, array("\r" => ''));

		// You won't believe this - but too many periods upsets apache it seems!
		$message = preg_replace('~\.{100,}~', '...', $message);

		// Trim off trailing quotes - these often happen by accident.
		while (substr($message, -7) === '[quote]')
			$message = substr($message, 0, -7);
		while (substr($message, 0, 8) === '[/quote]')
			$message = substr($message, 8);

		// Find all code blocks, and ensure they follow the [code][/code] syntax closely.
		if (preg_match_all('~\[(/?)code[^]]*]~i', $message, $matches))
		{
			$pos = 0;
			$tags = array();
			foreach ($matches[0] as $id => $tag)
			{
				$tag_pos = strpos($message, $tag, $pos);
				$length = strlen($tag);
				$tags[] = array($tag_pos, $length, $matches[1][$id] === '/');
				$pos = $tag_pos + $length;
			}
			$was_closed = true;
			$offset = 0;
			foreach ($tags as $tag)
			{
				if ($was_closed === $tag[2]) // consecutive opening or closing tags, or closing tag at the beginning?
				{
					$message = substr($message, 0, $tag[0] + $offset) . ($was_closed ? '' : '&#91;code]') . substr($message, $tag[0] + $tag[1] + $offset);
					$offset += ($was_closed ? 0 : 10) - $tag[1];
				}
				$was_closed = $tag[2];
			}
			if (!$was_closed) // no final closing tag?
				$message .= '[/code]';
		}

		self::fixNesting($message, $post_errors);
		if (!empty($post_errors))
			return;

		// Now that we've fixed all the code tags, let's fix the img and url tags...
		// Only mess with stuff outside of [code]/[php] tags.
		$parts = self::protect_string($message);
		foreach ($parts as $i => $part)
		{
			if (($is_protected = self::is_protected($part)) === false)
			{
				wedit::fixTags($parts[$i]);

				// Replace /me.+?\n with [me=name]dsf[/me]\n.
				if (strhas(we::$user['name'], array('[', ']', '\'', '"')))
					$parts[$i] = preg_replace('~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '$1[me=&quot;' . we::$user['name'] . '&quot;]$2[/me]', $parts[$i]);
				else
					$parts[$i] = preg_replace('~(\A|\n)/me(?: |&nbsp;)([^\n]*)(?:\z)?~i', '$1[me=' . we::$user['name'] . ']$2[/me]', $parts[$i]);

				if (!$previewing && strpos($parts[$i], '[html]') !== false)
				{
					if (allowedTo('admin_forum'))
						$parts[$i] = preg_replace_callback('~\[html](.+?)\[/html]~is', 'wedit::protect_html', $parts[$i]);

					// We should edit them out, or else if an admin edits the message they will get shown...
					else
						while (strpos($parts[$i], '[html]') !== false)
							$parts[$i] = preg_replace('~\[/?html]~i', '', $parts[$i]);
				}

				// Let's look at the time tags...
				$parts[$i] = preg_replace_callback('~\[time(?:=(absolute))*\](.+?)\[/time\]~i', 'wedit::preparse_time', $parts[$i]);

				// Change the color specific tags to [color=the color].
				$parts[$i] = preg_replace('~\[(black|blue|green|red|white)\]~', '[color=$1]', $parts[$i]);		// First do the opening tags.
				$parts[$i] = preg_replace('~\[/(?:black|blue|green|red|white)\]~', '[/color]', $parts[$i]);		// And now do the closing tags

				// Make sure all tags are lowercase.
				$parts[$i] = preg_replace_callback('~\[(/?)(list|li|table|tr|td)((\s[^\]]+)*)]~i', 'wedit::lowercase_tags', $parts[$i]);

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
					'~\[li\]([^][]+?)\[li\]~s' => '[li]$1[_/li_][_li_]',
					'~\[li\]([^][]+?)\[/list\]~s' => '[_li_]$1[_/li_][/list]',
					'~\[li\]([^][]+?)$~s' => '[li]$1[/li]',
					// Lists - find correctly closed items/lists.
					'~\[/li\]([\s\x{A0}]*)\[/list\]~su' => '[_/li_]$1[/list]',
					// Find list items closed and then opened.
					'~\[/li\]([\s\x{A0}]*)\[li\]~su' => '[_/li_]$1[_li_]',
					// Now, find any [list]s or [/li]s followed by [li].
					'~\[(list(?: [^\]]*?)?|/li)\]([\s\x{A0}]*)\[li\]~su' => '[$1]$2[_li_]',
					// Allow for sub lists.
					'~\[/li\]([\s\x{A0}]*)\[list\]~su' => '[_/li_]$1[list]',
					'~\[/list\]([\s\x{A0}]*)\[li\]~su' => '[/list]$1[_li_]',
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
			// Inside code tags, protect anything that's not parsed the regular way.
			if ($is_protected === true)
				$parts[$i] = str_ireplace(
					array('[nb]', '[/nb]', '://', 'www.'),
					array('&#91;nb]', '&#91;/nb]', '&#58;//', 'www&#46;'),
					$parts[$i]
				);
		}

		// Put it back together!
		if (!$previewing)
			$message = strtr(implode('', $parts), array('  ' => '&nbsp; ', "\n" => '<br>', "\xC2\xA0" => '&nbsp;'));
		else
			$message = strtr(implode('', $parts), array('  ' => '&nbsp; ', "\xC2\xA0" => '&nbsp;'));

		// On posting/editing: replace embed HTML, do lookups, and/or check whether YouTube links are embeddable.
		loadSource('media/Aeva-Embed');
		$message = aeva_onposting($message);

		// Now let's quickly clean up things that will slow our parser (which are common in posted code.)
		$message = strtr($message, array('[]' => '&#91;]', '[&#039;' => '&#91;&#039;'));
	}

	// This is very simple, and just removes things done by preparsecode.
	public static function un_preparsecode($message)
	{
		// We're going to unparse only the stuff outside [code]...
		$parts = self::protect_string($message);
		foreach ($parts as $i => $part)
		{
			// Is $part a protected chunk of code?
			if (self::is_protected($part) === false)
			{
				$parts[$i] = preg_replace_callback('~\[html](.+?)\[/html]~i', 'wedit::unprotect_html', $parts[$i]);

				// Attempt to un-parse the time to something less awful.
				$parts[$i] = preg_replace_callback('~\[time](\d{0,10})\[/time]~i', 'wedit::format_time', $parts[$i]);
			}
		}

		// Change breaks back to \n's and &nbsp; back to spaces.
		return preg_replace('~<br\s*/?\>~', "\n", str_replace('&nbsp;', ' ', implode('', $parts)));
	}

	// Fix any URLs posted - ie. remove 'javascript:'.
	public static function fixTags(&$message)
	{
		global $settings;

		// WARNING: Editing what follows can cause large security holes in your forum.
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
			wedit::fixTag($message, $param['tag'], $param['protocols'], $param['embeddedUrl'], $param['hasEqualSign'], !empty($param['hasExtra']));

		// Now fix possible security problems with images loading links automatically...
		$message = preg_replace_callback('~(\[img.*?\])(.+?)\[/img\]~is', 'wedit::fix_img_links', $message);

		// Limit the size of images posted?
		if (!empty($settings['max_image_width']) || !empty($settings['max_image_height']))
		{
			// Find all the img tags - with or without width and height.
			preg_match_all('~\[img(\s+width=\d+)?(\s+height=\d+)?(\s+width=\d+)?\](.+?)\[/img\]~is', $message, $matches);

			$replaces = array();
			foreach ($matches[0] as $match => $dummy)
			{
				// If the width was after the height, handle it.
				$matches[1][$match] = !empty($matches[3][$match]) ? $matches[3][$match] : $matches[1][$match];

				// Now figure out if they had a desired height or width...
				$desired_width = !empty($matches[1][$match]) ? (int) substr(trim($matches[1][$match]), 6) : 0;
				$desired_height = !empty($matches[2][$match]) ? (int) substr(trim($matches[2][$match]), 7) : 0;

				// One was omitted, or both. We'll have to find its real size...
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
				if ($desired_width <= $settings['max_image_width'] && $desired_height <= $settings['max_image_height'])
					continue;

				// Too bad, it's too wide. Make it as wide as the maximum.
				if ($desired_width > $settings['max_image_width'] && !empty($settings['max_image_width']))
				{
					$desired_height = (int) (($settings['max_image_width'] * $desired_height) / $desired_width);
					$desired_width = $settings['max_image_width'];
				}

				// Now check the height, as well. Might have to scale twice, even...
				if ($desired_height > $settings['max_image_height'] && !empty($settings['max_image_height']))
				{
					$desired_width = (int) (($settings['max_image_height'] * $desired_width) / $desired_height);
					$desired_height = $settings['max_image_height'];
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
		if (preg_match('~^([^:]+://[^/]+)~', ROOT, $match) != 0)
			$domain_url = $match[1];
		else
			$domain_url = ROOT . '/';

		$replaces = array();

		if ($hasEqualSign)
			preg_match_all('~\[(' . $myTag . ')=([^]]*?)\](?:(.+?)\[/(' . $myTag . ')\])?~is', $message, $matches);
		else
			preg_match_all('~\[(' . $myTag . ($hasExtra ? '(?:[^]]*?)' : '') . ')\](.+?)\[/(' . $myTag . ')\]~is', $message, $matches);

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

			if (!$found && $protocols[0] === 'http' && $replace !== '')
			{
				if ($replace[0] === '/')
					$replace = $domain_url . $replace;
				elseif ($replace[0] === '?')
					$replace = SCRIPT . $replace;
				elseif ($replace[0] === '#' && $embeddedUrl)
				{
					$replace = '#' . preg_replace('~[^A-Za-z0-9_\-#]~', '', substr($replace, 1));
					$this_tag = 'iurl';
					$this_close = 'iurl';
				}
				else
					$replace = $protocols[0] . '://' . $replace;
			}
			elseif (!$found && $protocols[0] === 'ftp')
				$replace = $protocols[0] . '://' . preg_replace('~^(?!ftps?)[^:]+://~', '', $replace);
			elseif (!$found)
				$replace = $protocols[0] . '://' . $replace;

			if ($hasEqualSign && $embeddedUrl)
				$replaces[$matches[0][$k]] = '[' . $this_tag . '=' . $replace . ']' . (empty($matches[4][$k]) ? '' : $matches[3][$k] . '[/' . $this_close . ']');
			elseif ($hasEqualSign)
				$replaces['[' . $matches[1][$k] . '=' . $matches[2][$k] . ']'] = '[' . $this_tag . '=' . $replace . ']';
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

	public static function fixNesting(&$text, &$post_errors = null)
	{
		$do_fix = $post_errors === null;

		$result = wesql::query('
			SELECT tag, block_level
			FROM {db_prefix}bbcode
			WHERE bbctype != {literal:closed}'
		);
		$is_block = array();
		while ($row = wesql::fetch_row($result))
			$is_block[$row[0]] = $row[1];
		wesql::free_result($result);
		$is_block['nb'] = 1;

		preg_match_all('~\[(/)?(' . implode('|', array_keys($is_block)) . ')\b[^]]*]~', $text, $bbcs, PREG_SET_ORDER);
		$bbcs = unserialize(strtolower(serialize($bbcs)));
		$restart = true;
		$last_safe = 0;
		$m = 0;

		while (!empty($restart) && $m++ < 50)
		{
			$restart = false;
			$passthru = false;
			$stack = $apos = array();
			$offset = 0;
			$last_pos = 0;

			// Add item positions within the string.
			foreach ($bbcs as $id => &$tg)
			{
				$tg[3] = strpos($text, $tg[0], $last_pos);
				$last_pos = $tg[3] + 1;
			}

			for ($id = 0, $bbc_len = count($bbcs); $id < $bbc_len; $id++)
			{
				$tag = $bbcs[$id];
				$full_tag = $tag[0];
				$is_closer = $tag[1];
				$name = $tag[2];
				$pos = $tag[3];

				$is_special = $name === 'code' || $name === 'html' || $name === 'nobbc' || $name === 'php';
				$latest = end($stack);

				// Do we have a block opener but currently opened non-block tags?
				if (!$is_closer && $is_block[$name] && !empty($stack) && !$is_block[$latest[1]])
				{
					$last = $bbcs[$latest[0]];
					if (!$do_fix)
					{
						$post_errors[] = array(
							'mismatched_tags',
							substr($text, max(0, $last[3] - 50), min(50, $last[3]))
							. '<strong>' . $last[0] . '</strong>'
							. substr($text, $last[3] + strlen($last[0]), 50)
						);
						// Remove unclosed non-block tags, to prevent any further related errors.
						while (($latest = end($stack)) && !$is_block[$latest[1]])
							array_pop($stack);
					}
					else
					{
						$quant = array();
						$found = false;
						// Close all opened non-block tags so far.
						for ($i = $id - 1; $i > 0; $i--)
						{
							$st = $bbcs[$i];
							if ($st[1] || $is_block[$st[2]])
								break;
							else
								$quant[$st[2]] = isset($quant[$st[2]]) ? $quant[$st[2]] + 1 : 1;
						}
						foreach ($quant as $opener => $add)
						{
							for ($i = 0; $i < $add; $i++)
							{
								array_splice($bbcs, $id, 0, array(array(0 => '[/' . $opener . ']', 1 => true, 2 => $opener, 3 => 0)));
								$text = substr_replace($text, '[/' . $opener . ']', $pos + $offset, 0);
								$offset += strlen($opener) + 3;
								array_pop($stack);
								$id++;
							}
						}
						$restart = true;
						break;
					}
				}
				// So, we found a closer tag but the last opened tag doesn't match...? Someone needs a helping hand.
				if (!$passthru && $is_closer && (empty($stack) || $latest[1] !== $name))
				{
					if (!$do_fix)
					{
						$post_errors[] = array(
							'mismatched_tags',
							substr($text, max(0, $pos - 50), min(50, $pos))
							. '<strong>' . $full_tag . '</strong>'
							. substr($text, $pos + strlen($full_tag), 50)
						);
						continue; // Ah! The easy way out...
					}
					// Is this a block-type closer, at the start of a new line, and
					// that doesn't have a matching opened tag in the entire stack?
					if ($is_block[$name] && ($pos === 0 || $text[$pos - 1] === "\n") && !in_array($name, $stack))
					{
						// Then maybe it's a typo? We'll just turn this into an opener!
						$text = substr_replace($text, '[' . $name . ']', $pos, strlen($full_tag));
						$bbcs[$id][0] = '[' . $name . ']';
						$bbcs[$id][1] = false;
						$restart = true;
						break;
					}
					$quant = array();
					$found = false;
					foreach (array_reverse($stack) as $st)
					{
						// So, we meet again. An unrelated tag that isn't closed.
						if ($st[1] !== $name)
						{
							// If we're inside a code block, then everything's allowed. NOTE: normally, we
							// shouldn't be going through this, because $passthru would be set. Unless we meet
							// something like [code][html][/html][/code]. So I left it in, just to be sure.
							if ($st[1] === 'code' || $st[1] === 'html' || $st[1] === 'nobbc' || $st[1] === 'php')
							{
								$quant = array();
								break;
							}
							// If it's a block tag, we'll stop here and mark it as needing more work.
							if ($is_block[$st[1]])
							{
								$found = $st[0];
								break;
							}
							// If it's an opener, increase the number of items to close, otherwise decrease it.
							$quant[$st[1]] = isset($quant[$st[1]]) ? $quant[$st[1]] + 1 : 1;
						}
						// Did we just find a tag of the same type? Then it just means we have to close the other openers.
						elseif ($st[1] === $name)
						{
							$found = $st[0];
							break;
						}
					}
					// Was the matching opener found at all, or maybe we really need to close opened tags?
					if ($found !== false || $is_block[$name])
					{
						$really_found = $bbcs[$found][2] === $name;
						// Let's close all opened tags in between.
						foreach ($quant as $key => $add)
						{
							for ($i = 0; $i < $add; $i++)
							{
								array_splice($bbcs, $id, 0, array(array(0 => '[/' . $key . ']', 1 => true, 2 => $key, 3 => 0)));
								$text = substr_replace($text, '[/' . $key . ']', $pos + $offset, 0);
								$offset += strlen($key) + 3;
								array_pop($stack);
								$id++;
							}
						}
						// And now we remove the opener to our current closer... (Only if it was really found.)
						if ($really_found)
							array_pop($stack);
					}
					// If we didn't find a matching opener, or we had to stop searching...
					if ($found === false || !$really_found)
					{
						// If the closer is a block tag, then we should open it no matter what, so the user can see any errors they made.
						if ($is_block[$name])
						{
							array_splice($bbcs, $found ? $found + 1 : $id, 0, array(array(0 => '[' . $name . ']', 1 => false, 2 => $name, 3 => 0)));
							$text = substr_replace($text, '[' . $name . ']', $bbcs[$found ? $found + 1 : $id][3], 0);
						}
						// Otherwise, who cares. Gone with it.
						else
						{
							$text = substr_replace($text, '', $pos, strlen($full_tag));
							unset($bbcs[$id]);
							$bbcs = array_values($bbcs); // Re-index
						}
					}
					$restart = true;
					break;
				}
				elseif ($is_closer && (!$passthru || $is_special))
				{
					$passthru = false;
					array_pop($stack);
					if (empty($stack))
						$last_safe = $id + 1;
				}
				elseif (!$passthru)
				{
					$stack[] = array($id, $name);
					if ($is_special)
						$passthru = true;
				}
			}
		}
		// Do we have any outstanding missing closers to fix?
		if ($do_fix && !empty($stack))
			foreach (array_reverse($stack) as $st)
				$text .= '[/' . $st[1] . ']';
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
		global $context, $txt, $settings, $smiley_css_done;

		$smileycontainer = empty($this->editorOptions['custom_smiley_div']) ? 'smileyBox_' . $this->id : $this->editorOptions['custom_smiley_div'];
		$bbccontainer = empty($this->editorOptions['custom_bbc_div']) ? 'bbcBox_' . $this->id : $this->editorOptions['custom_bbc_div'];

		// Output the bbc area
		if ($this->show_bbc && empty($this->editorOptions['custom_bbc_div']))
			echo '
				<div id="bbcBox_', $this->id, '"></div>';

		// What about smileys?
		if ((!empty($this->smileys['postform']) || !empty($this->smileys['popup'])) && empty($this->editorOptions['custom_smiley_div']))
			echo '
				<div id="smileyBox_', $this->id, '"></div>';

		if (we::is('ie[-10]'))
			add_js('
	$("#', $this->id, '").on("select click keyup change", function () { this.caretPos = document.selection.createRange().duplicate(); });');

		$has_error = isset($context['post_error']) && (isset($context['post_error']['no_message']) || in_array(array('long_message', $settings['max_messageLength']), $context['post_error']));
		echo '
				<div class="writer">
					<div>
						<textarea class="editor" name="', $this->id, '" id="', $this->id, '" rows="', $this->rows, '" cols="', we::is('ie8') ? '600' : $this->columns,
						'" tabindex="', $context['tabindex']++, '" style="width: ', $this->width, '; height: ', $this->height, $has_error ? '; border: 1px solid red' : '', '">', $this->value, '</textarea>
					</div>
					<div id="', $this->id, '_resizer" style="width: ', $this->width, '" class="hide rich_resize"></div>
				</div>
				<input type="hidden" name="', $this->id, '_mode" id="', $this->id, '_mode" value="0">';

		// Smileys
		if ((!empty($this->smileys['postform']) || !empty($this->smileys['popup'])) && !$this->disable_smiley_box)
		{
			$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
			$context['smiley_gzip'] = $can_gzip;
			$context['smiley_ext'] = $can_gzip ? (we::is('safari') ? '.cgz' : '.css.gz') : '.css';
			$extra = we::is('ie6,ie7') ? '-ie' : '';
			$var_name = 'smiley-cache-' . $extra . '-' . we::$user['smiley_set'];
			$dummy = '';
			$max = 0;

			// Retrieve the current smiley cache's URL. If not available, attempt to regenerate it.
			while (empty($exists) && $max++ < 3)
			{
				if (!isset($settings[$var_name]))
					updateSettings(array($var_name => time() % 1000));
				$context['smiley_now'] = $settings[$var_name];
				$filename = '/css/smileys' . $extra . (we::$user['smiley_set'] == 'default' ? '' : '-' . we::$user['smiley_set']) . '-' . $context['smiley_now'] . $context['smiley_ext'];
				$exists = file_exists(CACHE_DIR . $filename);
				if (!$exists)
					parsesmileys($dummy);
			}

			if (empty($smiley_css_done) && strpos($context['header'], CACHE . $filename) === false)
				$context['header'] .= '
	<link rel="stylesheet" href="' . CACHE . $filename . '">';

			$js = '';
			foreach ($this->smileys as $location => $smileyRows)
			{
				$js .= '
			' . $location . ': [';
				foreach ($smileyRows as $smileyRow)
				{
					$js .= '[';
					foreach ($smileyRow['smileys'] as $smiley)
						$js .= '
				[' . JavaScriptEscape($smiley['code']) . ', ' . JavaScriptEscape($smiley['class']) . ', ' . JavaScriptEscape($smiley['description']) . ']' . (empty($smiley['isLast']) ? ',' : '');

				$js .= '
			]' . (empty($smileyRow['isLast']) ? ',
			' : '');
				}
				$js .= ']' . ($location === 'postform' ? ',' : '');
			}

			add_js('
	var oSmileyBox_' . $this->id . ' = new weSmileyBox({
		id: ' . JavaScriptEscape($this->id) . ',
		sContainer: ' . JavaScriptEscape($smileycontainer) . ',
		sClickHandler: function (o) { oEditorHandle_' . $this->id . '.insertSmiley(o); },
		oSmileyLocations: {'
			. $js . '
		},
		sSmileyRowTemplate: ' . JavaScriptEscape('<div>%smileyRow%</div>') . ',
		sSmileyTemplate: ' . (we::is('ie') ? JavaScriptEscape('<div class="smiley %smileySource% smpost" title="%smileyDesc%" id="%smileyId%"><img src="')
			. ' + we_assets + \'/blank.gif\' + ' . JavaScriptEscape('" class="%smileySource%" /></div>')
			: JavaScriptEscape('<div class="smiley %smileySource% smpost" title="%smileyDesc%" id="%smileyId%"></div>')) . ',
		sSmileyBoxTemplate: ' . JavaScriptEscape('<div class="inline-block">%smileyRows%<div class="more"></div></div> <div class="inline-block">%moreSmileys%</div>') . ',
		sMoreSmileysTemplate: ' . JavaScriptEscape('<a href="#" id="%moreSmileysId%">[' . (!empty($this->smileys['postform']) ? $txt['more_smileys'] : $txt['more_smileys_pick']) . ']</a>') . ',
		sMoreSmileysLinkId: ' . JavaScriptEscape('moreSmileys_' . $this->id) . '
	});');
		}

		if ($this->show_bbc)
		{
			// Here loop through the array, printing the images/rows/separators!
			$js = '';
			$last_row = count($this->bbc) - 1;
			foreach ($this->bbc as $i => $buttonRow)
			{
				$js .= '
			[';
				foreach ($buttonRow as $tag)
				{
					// Is there a "before" part for this bbc button? If not, it can't be a button!!
					// In order, we show: sType, bEnabled, sImage/sPos, sCode, sBefore, sAfter, sDescription.
					if (isset($tag['before']))
						$js .= '
				[' .
					'\'button\', ' . (empty($this->disabled_tags[$tag['code']]) ? '1, ' : '0, ') . (!is_array($tag['image']) ?
					JavaScriptEscape($tag['image']) . ', ' : '[' . ($tag['image'][0] + 1) * 23 . ', ' . $tag['image'][1] * 22 . '], ') .
					JavaScriptEscape($tag['code']) . ', ' .
					JavaScriptEscape($tag['before']) . ', ' .
					(isset($tag['after']) ? JavaScriptEscape($tag['after']) : '\'\'') . ', ' .
					JavaScriptEscape($tag['description']) .
				']' . (empty($tag['isLast']) ? ',' : '');

					// Must be a divider then.
					else
						$js .= '
				[]' . (empty($tag['isLast']) ? ',' : '');
				}

				// Add the select boxes to the first row.
				if ($i == 0)
				{
					// Show the font drop down...
					if (!isset($this->disabled_tags['font']) && !empty($settings['editorFonts']))
					{
						$fonts = array_filter(array_map('trim', preg_split('~[\s,]+~', $settings['editorFonts'])));
						if (!empty($fonts))
						{
							$js .= ',
				["select", "sel_face", {"": ' . JavaScriptEscape($txt['font_face']);
							foreach ($fonts as $font)
								$js .= ', "' . strtolower($font) . '": "' . $font . '"';
							$js .= '}]';
						}
					}

					// Font sizes anyone?
					if (!isset($this->disabled_tags['size']) && !empty($settings['editorSizes']))
					{
						$fonts = array_filter(array_map('trim', preg_split('~[\s,]+~', $settings['editorSizes'])));
						if (!empty($fonts))
						{
							$js .= ',
				["select", "sel_size", { "": ' . JavaScriptEscape($txt['font_size']);
							foreach ($fonts as $k => $v)
								$js .= ', ' . ($k + 1) . ': "' . $v . '"';
							$js .= '}]';
						}
					}

					// Print a drop down list for all the colors we allow!
					if (!isset($this->disabled_tags['color']))
						$js .= ',
				["select", "sel_color", {
					"": ' . JavaScriptEscape($txt['change_color']) . ',
					"black": ' . JavaScriptEscape($txt['black']) . ', "red": ' . JavaScriptEscape($txt['red']) . ', "orange": ' . JavaScriptEscape($txt['orange']) . ',
					"limegreen": ' . JavaScriptEscape($txt['lime_green']) . ', "teal": ' . JavaScriptEscape($txt['teal']) . ', "green": ' . JavaScriptEscape($txt['green']) . ',
					"blue": ' . JavaScriptEscape($txt['blue']) . ', "navy": ' . JavaScriptEscape($txt['navy']) . ', "purple": ' . JavaScriptEscape($txt['purple']) . ',
					"brown": ' . JavaScriptEscape($txt['brown']) . ', "maroon": ' . JavaScriptEscape($txt['maroon']) . ', "pink": ' . JavaScriptEscape($txt['pink']) . ',
					"yellow": ' . JavaScriptEscape($txt['yellow']) . ', "beige": ' . JavaScriptEscape($txt['beige']) . ', "white": ' . JavaScriptEscape($txt['white']) . '
				}]';
				}
				$js .= '
			]' . ($i == $last_row ? '' : ',');
			}

			add_js('
	var oBBCBox_' . $this->id . ' = new weButtonBox({
		sContainer: ' . JavaScriptEscape($bbccontainer) . ',
		sButtonClickHandler: function (o) { oEditorHandle_' . $this->id . '.handleButtonClick(o); },
		sSelectChangeHandler: function (o) { oEditorHandle_' . $this->id . '.handleSelectChange(o); },
		sSprite: we_assets + \'/bbc.png\',
		aButtonRows: ['
			. $js . '
		],
		sButtonTemplate: ' . (we::is('ie') ? JavaScriptEscape(
			'<div class="bbc_button" id="%buttonId%"><div style="background: url(%buttonSrc%) -%posX%px -%posY%px no-repeat" title="%buttonDescription%">
				<img id="%buttonId%" src="') . ' + we_assets + \'/blank.gif\' + '
			. JavaScriptEscape('" align="bottom" width="23" height="22" alt="%buttonDescription%" title="%buttonDescription%" /></div></div>') : JavaScriptEscape(
			'<div class="bbc_button" id="%buttonId%"><div style="background: url(%buttonSrc%) -%posX%px -%posY%px no-repeat" title="%buttonDescription%"></div></div>')
		) . ',
		sButtonBackgroundPos: [0, 22],
		sButtonBackgroundPosHover: [0, 0],
		sActiveButtonBackgroundPos: [0, 0],
		sDividerTemplate: ' . JavaScriptEscape('<div class="bbc_divider"></div>') . ',
		sSelectTemplate: ' . JavaScriptEscape('<select name="%selectName%" id="%selectId%" class="seledit">%selectOptions%</select>') . ',
		sButtonRowTemplate: ' . JavaScriptEscape('<div>%buttonRow%</div>') . '
	});');
		}

		$auto_drafts = in_array($this->editorOptions['drafts'], array('auto_post', 'auto_pm'));
		// If we're doing it, let's add the auto saver for drafts.
		if ($auto_drafts)
			add_js('
	var oAutoSave = new wedge_autoDraft({
		sForm: \'postmodify\',
		sEditor: ' . JavaScriptEscape($this->id) . ',
		sType: ' . JavaScriptEscape($this->editorOptions['drafts']) . ',
		sLastNote: \'draft_lastautosave\',
		iFreq: ', (empty($settings['masterAutoSaveDraftsDelay']) ? 30000 : $settings['masterAutoSaveDraftsDelay'] * 1000), '
	});');

		// Get a list of all the tags that are not disabled.
		$all_tags = parse_bbc(false);
		$unparsed_tags = array();
		$closed_tags = array();
		foreach ($all_tags as $tag)
		{
			if (isset($tag['type']) && $tag['type'] === 'closed')
				$closed_tags[] = $tag['tag'];
			elseif (isset($tag['type']) && $tag['type'] === 'unparsed_content')
				$unparsed_tags[] = $tag['tag'];
		}

		// Now it's all drawn out we'll actually setup the box.
		add_js('
	var protectTags = ["' . implode('", "', array_flip(array_flip($unparsed_tags))) . '"],
		closedTags = ["' . implode('", "', array_flip(array_flip($closed_tags))) . '"];
	var oEditorHandle_' . $this->id . ' = new weEditor({
		sFormId: ' . JavaScriptEscape($this->form) . ',
		sUniqueId: ' . JavaScriptEscape($this->id) . ($this->rich_active ? ',
		bWysiwyg: true' : '') . (!$settings['disable_wysiwyg'] ? '' : ',
		bWysiwygOff: true') . ',
		sText: ' . JavaScriptEscape($this->rich_active ? $this->rich_value : '') . ',
		sEditWidth: ' . JavaScriptEscape($this->width) . ',
		sEditHeight: ' . JavaScriptEscape($this->height) . ',
		oSmileyBox: ' . (!empty($this->smileys['postform']) && !$this->disable_smiley_box ? 'oSmileyBox_' . $this->id : 'null') . ',
		oBBCBox: ' . ($this->show_bbc ? 'oBBCBox_' . $this->id : 'null') . ',
		oDrafts: ' . ($auto_drafts ? 'oAutoSave' : 'false') . '
	});
	weEditors.push(oEditorHandle_' . $this->id . ');');
	}

	public function outputButtons()
	{
		global $context, $txt;

		foreach ($this->editorOptions['buttons'] as $button)
		{
			$class = (!empty($button['class']) ? $button['class'] : '') . ($button['name'] === 'post_button' ? ' submit' : '');
			if (!empty($class))
				$class = ' class="' . trim($class) . '"';

			echo '
				<input type="submit" name="', $button['name'], '" value="', $button['button_text'], '" tabindex="', $context['tabindex']++, '"', !empty($button['onclick']) ? ' onclick="' . $button['onclick'] . '"' : '', !empty($button['accesskey']) ? ' accesskey="' . $button['accesskey'] . '"' : '', $class, '>';
		}

		// These two buttons get added semi-magically rather than not.
		if ($this->editorOptions['drafts'] != 'none')
			echo '
				<input type="hidden" id="draft_id" name="draft_id" value="', empty($_REQUEST['draft_id']) ? '0' : $_REQUEST['draft_id'], '">
				<input type="submit" name="draft" value="', $txt['save_draft'], '" tabindex="', $context['tabindex']++, '" onclick="return ', in_array($this->editorOptions['drafts'], array('basic_post', 'auto_post')) ? 'ask(' . JavaScriptEscape($txt['save_draft_warning']) . ', e) && ' : '', 'submitThisOnce(this);" accesskey="d" class="save">';

		if (in_array($this->editorOptions['drafts'], array('auto_post', 'auto_pm')))
			echo '
				<span id="draft_lastautosave"></span>';
	}

	public function saveEntityFields()
	{
		foreach ($this->editorOptions['entity_fields'] as $field)
			$tmp[] = '\'' . $field . '\'';
		return '[' . implode(',', $tmp) . ']';
	}

	public function addEntityField($field)
	{
		if (!is_array($field))
			$field = array($field);
		$this->editorOptions['entity_fields'] = array_unique(array_merge($this->editorOptions['entity_fields'], $field));
	}

	// Helper functions for handling special BBC tags like [code].
	// This will return false (outside of a tag), true (inside a tag), and 1 or 0 for opening or closing tag.
	// Everything between protectable tags is taken literally, e.g. [code][php][/code] outputs [php].
	public static function is_protected($str)
	{
		global $protector;

		if (!isset($protector))
			$protector = '';
		if ($str !== '' && $str[0] === '[')
		{
			$t = strtolower($str);
			if (!$protector && ($t === '[code]' || $t === '[php]' || $t === '[nobbc]'))
			{
				$protector = substr($t, 1, -1);
				return 1;
			}
			if ($protector && $t === '[/' . $protector . ']')
			{
				$protector = '';
				return 0;
			}
		}
		return !!$protector;
	}

	public static function protect_string($str)
	{
		global $protector;

		$protector = '';
		return preg_split('~(\[/?(?:code|php|nobbc)\b[^]]*?\])~i', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
	}
}
