<?php
/**
 * Wedge
 *
 * This file handles the parsing of BBC (Bulletin Board Code). Let's just say it's important on its own.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Returns the given string, parsed for inline bbcode, i.e. a limited set of bbcode that can be safely parsed and shown in areas where the user doesn't want layout to be potentially broken, such as board descriptions or profile fields.
 *
 * !!! Notes:
 * - This is currently handled by passing an array to parse_bbc().
 * - This should be written with performance in mind, i.e. use regular expressions for most tags.
 *
 * @param mixed $message The original text, transmitted to parse_bbc()
 * @param mixed $smileys Whether smileys should be parsed too, transmitted to parse_bbc()
 * @param string $cache_id If specified, a quasi-unique key for the item being parsed, transmitted to parse_bbc()
 * @param bool $short_list A boolean, true by default, specifying whether to disable the parsing of inline bbcode that is scarcely used, or that could slightly disrupt layout, such as colors, sub and sup.
 * @param int $owner The user id of the owner, if applicable and appropriate.
 * @return mixed See parse_bbc()
 */
function parse_bbc_inline($message, $smileys = true, $cache_id = '', $short_list = true, $owner = 0)
{
	return parse_bbc($message, $smileys, $cache_id, $short_list ?
		array(
			'b', 'u', 'i', 's',
			'email', 'ftp', 'iurl', 'url', 'nobbc',
		) :
		array(
			'b', 'u', 'i', 's',
			'email', 'ftp', 'iurl', 'url', 'nobbc',
			'abbr', 'me', 'sub', 'sup', 'time', 'color',
		), $owner
	);
}

/**
 * Returns the given string, parsed for most forms of bbcode, according to the function parameters and general application state.
 *
 * Notes:
 * - This function handles all bbcode parsing, as well as containing the list of all bbcodes known to the system, and where new bbcodes should be added.
 * - The state of bbcode disabled in the admin panel is stored in $settings['disabledBBC'] as a comma-separated list and parsed here.
 * - The master toggle switch of $settings['enableBBC'] is applied here, as is $settings['enablePostHTML'] being able to handle basic HTML (including b, u, i, s, em, pre, blockquote; a and img are converted to bbcode equivalents)
 *
 * @param mixed $message The original text, including bbcode, to be parsed. This is expected to have been parsed with {@link preparsecode()} previously (for handling of quotes and apostrophes). Alternatively, if boolean false is passed here, the return value is the array listing the acceptable bbcode types.
 * @param mixed $smileys Whether smileys should be parsed too, prior to (and in addition to) any bbcode, defaults to true. Nominally this is a boolean value, true for 'parse smileys', false for not, however the function also accepts the string 'print', for parsing in the print-page environment, which disables non printable tags and smileys.
 * @param string $cache_id If specified, a quasi-unique key for the item being parsed, so that if it took over 0.05 seconds, it can be cached. (The final key used for the cache takes the supplied key and includes details such as the user's locale and time offsets, an MD5 digest of the message and other details that potentially affect the way parsing occurs)
 * @param array $parse_tags An array of tags that will be allowed for this parse only. (This overrides any user settings for what is and is not allowed. Additionally, runs with this set are never cached, regardless of cache id being set)
 * @param int $owner The user id of the owner, if applicable and appropriate.
 * @return mixed If $message was boolean false, the return set is the master list of available bbcode, otherwise it is the parsed message.
 */
function parse_bbc($message, $smileys = true, $cache_id = '', $parse_tags = array(), $owner = 0)
{
	global $txt, $context, $settings, $user_profile;
	static $bbc_codes = array(), $bbc_types = array(), $itemcodes = array(), $no_autolink_tags = array();
	static $master_codes = null, $strlower = null, $disabled, $feet = 0;

	// Don't waste cycles
	if ($message === '')
		return '';

	if ($smileys !== null && ($smileys == '1' || $smileys == '0'))
		$smileys = (bool) $smileys;

	if (empty($settings['enableBBC']) && $message !== false)
	{
		if ($smileys === true)
			parsesmileys($message);

		return $message;
	}

	if ($master_codes === null)
	{
		$strlower = array_combine(range(' ', "\xFF"), str_split(strtolower(implode('', range(' ', "\xFF")))));

		$field_list = array(
			'before_code' => 'before',
			'after_code' => 'after',
			'content' => 'content',
			'disabled_before' => 'disabled_before',
			'disabled_after' => 'disabled_after',
			'disabled_content' => 'disabled_content',
			'test' => 'test',
		);
		$explode_list = array(
			'disallow_children' => 'disallow_children',
			'require_children' => 'require_children',
			'require_parents' => 'require_parents',
			'parsed_tags_allowed' => 'parsed_tags_allowed',
		);

		$result = wesql::query('
			SELECT tag, len, bbctype, before_code, after_code, content, disabled_before,
				disabled_after, disabled_content, block_level, test, validate_func, disallow_children,
				require_parents, require_children, parsed_tags_allowed, quoted, params, trim_wspace
			FROM {db_prefix}bbcode',
			array()
		);

		while ($row = wesql::fetch_assoc($result))
		{
			$bbcode = array(
				'tag' => $row['tag'],
				'len' => $row['len'],
				'block_level' => !empty($row['block_level']),
				'trim' => $row['trim_wspace'],
			);
			if ($row['bbctype'] !== 'parsed')
				$bbcode['type'] = $row['bbctype'];
			if (!empty($row['params']))
				$bbcode['parameters'] = unserialize($row['params']);
			if (!empty($row['validate_func']))
				$bbcode['validate'] = create_function('&$tag, &$data, $disabled', $row['validate_func']);
			if ($row['quoted'] != 'none')
				$bbcode['quoted'] = $row['quoted'];

			foreach ($explode_list as $db_field => $bbc_field)
				if (!empty($row[$db_field]))
					$bbcode[$bbc_field] = explode(',', $row[$db_field]);
			foreach ($field_list as $db_field => $bbc_field)
				if (!empty($row[$db_field]))
					$bbcode[$bbc_field] = preg_replace('~{{(\w+)}}~e', '$txt[\'$1\']', trim($row[$db_field]));

			// Reformat it from DB structure
			$master_codes[] = $bbcode;
		}
		wesql::free_result($result);
	}

	// If we are not doing every tag then we don't cache this run.
	if (!empty($parse_tags) && !empty($bbc_codes))
	{
		$temp_bbc = $bbc_codes;
		$bbc_codes = array();
	}

	if (empty($parse_tags))
	{
		if (empty($disabled['media']) && stripos($message, '[media') !== false)
		{
			loadSource('media/Subs-Media');
			aeva_protect_bbc($message);
		}
	}

	// Sift out the bbc for a performance improvement.
	if (empty($bbc_codes) || $message === false || !empty($parse_tags))
	{
		if (!empty($settings['disabledBBC']))
			foreach (explode(',', strtolower($settings['disabledBBC'])) as $tag)
				$disabled[trim($tag)] = true;

		if (empty($settings['enableEmbeddedFlash']))
			$disabled['flash'] = true;

		// This is mainly for the bbc manager, so it's easy to add tags above. Custom BBC should be added above this line.
		if ($message === false)
		{
			if (isset($temp_bbc))
				$bbc_codes = $temp_bbc;
			return $master_codes;
		}

		// So the parser won't skip them.
		$itemcodes = array(
			'*' => 'disc',
			'@' => 'disc',
			'+' => 'square',
			'x' => 'square',
			'#' => 'square',
			'o' => 'circle',
			'O' => 'circle',
			'0' => 'circle',
		);

		if (!isset($disabled['li']) && !isset($disabled['list']))
			foreach ($itemcodes as $c => $dummy)
				$bbc_codes[$c] = array();

		// Inside these tags autolink is not recommendable.
		$no_autolink_tags = array_flip(array('url', 'iurl', 'ftp', 'email'));

		// These are all of the allowed bbcode formats.
		$bbc_types = array_flip(array('unparsed_equals', 'unparsed_commas', 'unparsed_commas_content', 'unparsed_equals_content', 'parsed_equals'));

		// If we are not doing every tag only do ones we are interested in.
		foreach ($master_codes as $code)
			if (empty($parse_tags) || in_array($code['tag'], $parse_tags))
				$bbc_codes[substr($code['tag'], 0, 1)][] = $code;
	}

	// Purify quotes. Whitespace is trimmed from both inside and outside them.
	if (empty($parse_tags))
		$message = preg_replace('~(?:<br>|&nbsp;|\s)*(\[noae])?\[(/?)quote\b([^]]*)](\[/noae])?(?:<br>|&nbsp;|\s)*~is', '$1[$2quote$3]$4', $message);

	// !! We could do the same for images..?
	//	$message = preg_replace('~(?:<br>|&nbsp;|\s)*(\[url=[^]]*?)?\[img\b([^]]*)]([^\[]+?)\[/img](\[/url])?(<br>|&nbsp;|\s)*~is', '$1[img$2]$3[/img]$4', $message);

	// Shall we take the time to cache this? Do it if: cache is enabled, at a high level, message is long enough to warrant it,
	// and after making sure that it doesn't hold an embeddable link -- except if we're in a signature, in which case we won't embed it.
	if ($cache_id != '' && !empty($settings['cache_enable']) && (($settings['cache_enable'] >= 2 && strlen($message) > 1000) || strlen($message) > 2400)
		&& empty($parse_tags) && (strpos($cache_id, 'sig') !== false || (strpos($message, 'http://') === false)))
	{
		// It's likely this will change if the message is modified.
		$cache_key = 'parse:' . $cache_id . '-' . md5(md5($message) . '-' . $smileys . (empty($disabled) ? '' : implode(',', array_keys($disabled)))
					. serialize(we::$browser) . $txt['lang_locale'] . we::$user['time_offset'] . we::$user['time_format']);

		if (($temp = cache_get_data($cache_key, 240)) != null)
			return $temp;

		$cache_t = microtime(true);
	}

	if ($smileys === 'print')
	{
		// Colors can't be displayed well... Supposed to be B&W. And show text
		// for links, which can't be clicked on paper. (Admit you tried. We saw you.)
		foreach	(array('color', 'black', 'blue', 'white', 'red', 'green', 'me', 'php', 'ftp', 'url', 'iurl', 'email', 'flash') as $disable)
			$disabled[$disable] = true;

		// !!! Change maybe?
		if (!isset($_GET['images']))
			$disabled['img'] = true;

		// !!! Interface/setting to add more?
	}

	$open_tags = array();
	$message = strtr($message, array("\n" => '<br>'));

	$pos = -1;

	while ($pos !== false)
	{
		$last_pos = isset($last_pos) ? max($pos, $last_pos) : $pos;
		$pos = strpos($message, '[', $pos + 1);

		// Failsafe.
		if ($pos === false || $last_pos > $pos)
			$pos = strlen($message) + 1;

		// Can't have a one letter smiley, URL, or email! (Sorry.)
		if ($last_pos < $pos - 1)
		{
			// Make sure the $last_pos is not negative.
			$last_pos = max($last_pos, 0);

			// Pick a block of data to do some raw fixing on.
			$data = $orig_data = substr($message, $last_pos, $pos - $last_pos);

			// Take care of some HTML!
			if (!empty($settings['enablePostHTML']) && strpos($data, '&lt;') !== false)
			{
				$data = preg_replace('~&lt;a\s+href=(&quot;)?((?:https?://|ftps?://|mailto:)\S+?)\\1&gt;~i', '[url=$2]', $data);
				$data = preg_replace('~&lt;/a&gt;~i', '[/url]', $data);

				// <br> should be empty.
				$data = str_replace(array('&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'), '[br]', $data);
				$data = str_replace(array('&lt;hr&gt;', '&lt;hr/&gt;', '&lt;hr /&gt;'), '[hr]', $data);

				// b, u, i, s, pre... basic closable tags.
				foreach (array('b', 'u', 'i', 's', 'em', 'pre', 'blockquote') as $tag)
				{
					$diff = substr_count($data, '&lt;' . $tag . '&gt;') - substr_count($data, '&lt;/' . $tag . '&gt;');
					$data = strtr($data, array('&lt;' . $tag . '&gt;' => '<' . $tag . '>', '&lt;/' . $tag . '&gt;' => '</' . $tag . '>'));

					if ($diff > 0)
						$data = substr($data, 0, -1) . str_repeat('</' . $tag . '>', $diff) . substr($data, -1);
				}

				// Do <img ...> - with security... action= -> action-.
				preg_match_all('~&lt;img\s+src=(&quot;)?((?:https?://|ftps?://)\S+?)\\1(?:\s+alt=(&quot;.*?&quot;|\S*?))?(?:\s*/)?&gt;~i', $data, $matches);
				if (!empty($matches[0]))
				{
					$replaces = array();
					foreach ($matches[2] as $match => $imgtag)
					{
						$alt = empty($matches[3][$match]) ? '' : ' alt=' . preg_replace('~^&quot;|&quot;$~', '', $matches[3][$match]);

						// Remove action= from the URL - but allow attachments and gallery items.
						if (preg_match('~\baction(?:=|%3d)(?!dlattach|media)~i', $imgtag) === 1)
							$imgtag = preg_replace('~\baction(?:=|%3d)(?!dlattach|media)~i', 'action-', $imgtag);

						// Check if the image is larger than allowed.
						if (!empty($settings['max_image_width']) || !empty($settings['max_image_height']))
						{
							list ($width, $height) = url_image_size($imgtag);

							if (!empty($settings['max_image_width']) && $width > $settings['max_image_width'])
							{
								$height = (int) (($settings['max_image_width'] * $height) / $width);
								$width = $settings['max_image_width'];
							}

							if (!empty($settings['max_image_height']) && $height > $settings['max_image_height'])
							{
								$width = (int) (($settings['max_image_height'] * $width) / $height);
								$height = $settings['max_image_height'];
							}

							// Set the new image tag.
							$replaces[$matches[0][$match]] = '[img width=' . $width . ' height=' . $height . $alt . ']' . $imgtag . '[/img]';
						}
						else
							$replaces[$matches[0][$match]] = '[img' . $alt . ']' . $imgtag . '[/img]';
					}

					$data = strtr($data, $replaces);
				}
			}

			if (!empty($settings['autoLinkUrls']))
			{
				// Are we inside tags that should be auto-linked?
				$no_autolink_area = false;
				if (!empty($open_tags))
					foreach ($open_tags as $open_tag)
						if (isset($no_autolink_tags[$open_tag['tag']]))
							$no_autolink_area = true;

				// Don't go backwards.
				//!!! Don't think is the real solution....
				if (isset($lastAutoPos) && $pos < $lastAutoPos)
					$no_autolink_area = true;
				$lastAutoPos = $pos;

				if (!$no_autolink_area)
				{
					// Parse any URLs.... have to get rid of the @ problems some things cause... stupid email addresses.
					if (!isset($disabled['url']) && (strpos($data, '://') !== false || strpos($data, 'www.') !== false) && strpos($data, '[url') === false)
					{
						// Switch out quotes really quick because they can cause problems.
						$data = strtr($data, array('&#039;' => '\'', '&nbsp;' => "\xC2\xA0", '&quot;' => '>">', '"' => '<"<', '&lt;' => '<lt<'));

						// Only do this if the preg survives.
						if (is_string($result = preg_replace(array(
							'`(?<=[\s>.(;\'"]|^)((?:http|https)://[\w%@:|-]+(?:\.[\w%-]+)*(?::\d+)?(?:/[\w~%.@,?&;=#+:\'\\\\!(){}-]*)*[/\w~%@?;=#}\\\\-])`i',
							'`(?<=[\s>.(;\'"]|^)((?:ftp|ftps)://[\w%@:|-]+(?:\.[\w%-]+)*(?::\d+)?(?:/[\w~%.@,?&;=#(){}+:\'\\\\-]*)*[/\w~%@?;=#}\\\\-])`i',
							'`(?<=[\s>(\'<]|^)(www(?:\.[\w-]+)+(?::\d+)?(?:/[\w~%.@!,?&;=#(){}+:\'\\\\-]*)*[/\w~%@?;=#}\\\\-])`i'
						), array(
							'[url]$1[/url]',
							'[ftp]$1[/ftp]',
							'[url=http://$1]$1[/url]'
						), $data)))
							$data = $result;

						$data = strtr($data, array('\'' => '&#039;', "\xC2\xA0" => '&nbsp;', '>">' => '&quot;', '<"<' => '"', '<lt<' => '&lt;'));
					}

					// Next, emails...
					if (!isset($disabled['email']) && strpos($data, '@') !== false && strpos($data, '[email') === false)
					{
						$data = preg_replace('~(?<=[?\s\x{A0}[\]()*\\\;>]|^)([\w.-]{1,80}@[\w-]+\.[\w-]+[\w-])(?=[?,\s\x{A0}[\]()*\\\]|$|<br>|&nbsp;|&gt;|&lt;|&quot;|&#039;|\.(?:\.|;|&nbsp;|\s|$|<br>))~u', '[email]$1[/email]', $data);
						$data = preg_replace('~(?<=<br>)([\w.-]{1,80}@[\w-]+\.[\w.-]+[\w-])(?=[?.,;\s\x{A0}[\]()*\\\]|$|<br>|&nbsp;|&gt;|&lt;|&quot;|&#039;)~u', '[email]$1[/email]', $data);
					}
				}
			}

			$data = strtr($data, array("\t" => '&nbsp;&nbsp;&nbsp;'));

			// If it wasn't changed, no copying or other boring stuff has to happen!
			if ($data != $orig_data)
			{
				$message = substr($message, 0, $last_pos) . $data . substr($message, $pos);

				// Since we changed it, look again in case we added or removed a tag. But we don't want to skip any.
				$old_pos = strlen($data) + $last_pos;
				$pos = strpos($message, '[', $last_pos);
				$pos = $pos === false ? $old_pos : min($pos, $old_pos);
			}
		}

		// Are we there yet? Are we there yet?
		if ($pos >= strlen($message) - 1)
			break;

		$tags = $strlower[$message[$pos + 1]];

		if ($tags === '/' && !empty($open_tags))
		{
			$pos2 = strpos($message, ']', $pos + 1);
			if ($pos2 === $pos + 2)
				continue;
			$look_for = strtolower(substr($message, $pos + 2, $pos2 - $pos - 2));

			$to_close = array();
			$block_level = null;
			do
			{
				$tag = array_pop($open_tags);
				if (!$tag)
					break;

				if ($tag['block_level'])
				{
					// Only find out if we need to.
					if ($block_level === false)
					{
						array_push($open_tags, $tag);
						break;
					}

					// The idea is, if we are LOOKING for a block-level tag, we can close them on the way.
					if ($look_for !== '' && isset($bbc_codes[$look_for[0]]))
					{
						foreach ($bbc_codes[$look_for[0]] as $temp)
							if ($temp['tag'] === $look_for)
							{
								$block_level = $temp['block_level'];
								break;
							}
					}

					if ($block_level !== true)
					{
						$block_level = false;
						array_push($open_tags, $tag);
						break;
					}
				}

				$to_close[] = $tag;
			}
			while ($tag['tag'] != $look_for);

			// Did we just eat through everything and not find it?
			if ((empty($open_tags) && (empty($tag) || $tag['tag'] !== $look_for)))
			{
				$open_tags = $to_close;
				continue;
			}
			elseif (!empty($to_close) && $tag['tag'] !== $look_for)
			{
				if ($block_level === null && isset($look_for[0], $bbc_codes[$look_for[0]]))
				{
					foreach ($bbc_codes[$look_for[0]] as $temp)
						if ($temp['tag'] === $look_for)
						{
							$block_level = $temp['block_level'];
							break;
						}
				}

				// We're not looking for a block-level tag (or maybe even a tag that exists...)
				if (!$block_level)
				{
					foreach ($to_close as $tag)
						array_push($open_tags, $tag);
					continue;
				}
			}

			foreach ($to_close as $tag)
			{
				$message = substr($message, 0, $pos) . "\n" . $tag['after'] . "\n" . substr($message, $pos2 + 1);
				$pos += strlen($tag['after']) + 2;
				$pos2 = $pos - 1;

				// See the comment at the end of the big loop - just eating whitespace ;)
				if ($tag['block_level'] && substr($message, $pos, 4) === '<br>')
					$message = substr($message, 0, $pos) . substr($message, $pos + 4);
				if (($tag['trim'] === 'outside' || $tag['trim'] === 'both') && preg_match('~^(?:<br>|&nbsp;|\s)+~', substr($message, $pos), $matches) === 1)
					$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));
			}

			if (!empty($to_close))
			{
				$to_close = array();
				$pos--;
			}

			continue;
		}

		// No tags for this character, so just keep going (fastest possible course.)
		if (!isset($bbc_codes[$tags]))
			continue;

		$inside = empty($open_tags) ? null : $open_tags[count($open_tags) - 1];
		$tag = null;
		foreach ($bbc_codes[$tags] as $possible)
		{
			// Not a match?
			if (strtolower(substr($message, $pos + 1, $possible['len'])) !== $possible['tag'])
				continue;

			$len = $possible['len'];
			$next_c = $message[$pos + 1 + $len];

			// A test validation?
			if (isset($possible['test']) && preg_match('~^' . $possible['test'] . '~', substr($message, $pos + 2 + $len)) !== 1)
				continue;
			// Do we want parameters?
			elseif (!empty($possible['parameters']))
			{
				if ($next_c !== ' ')
					continue;
			}
			elseif (isset($possible['type']))
			{
				// Do we need an equal sign?
				if (isset($bbc_types[$possible['type']]) && $next_c !== '=')
					continue;
				// Maybe we just want a /...
				if ($possible['type'] === 'closed' && $next_c !== ']' && substr($message, $pos + 1 + $len, 2) !== '/]' && substr($message, $pos + 1 + $len, 3) !== ' /]')
					continue;
				// An immediate ]?
				if ($possible['type'] === 'unparsed_content' && $next_c !== ']')
					continue;
			}
			// No type means 'parsed_content', which demands an immediate ] without parameters!
			elseif ($next_c != ']')
				continue;

			// Check allowed tree?
			if (isset($possible['require_parents']) && ($inside === null || !in_array($inside['tag'], $possible['require_parents'])))
				continue;
			elseif (isset($inside['require_children']) && !in_array($possible['tag'], $inside['require_children']))
				continue;
			// If this is in the list of disallowed child tags, don't parse it.
			elseif (isset($inside['disallow_children']) && in_array($possible['tag'], $inside['disallow_children']))
				continue;

			$pos1 = $pos + 2 + $len;

			// Quotes can have alternate styling, we do this php-side due to all the permutations of quotes.
			if ($possible['tag'] === 'quote')
			{
				// Start with standard
				$quote_alt = false;

				// Every parent quote this quote has flips the styling
				foreach ($open_tags as $open_quote)
					if ($open_quote['tag'] === 'quote')
						$quote_alt = !$quote_alt;

				// Add a class to the quote to style alternating blockquotes
				if ($quote_alt)
					$possible['before'] = strtr($possible['before'], array('<div class="bbc_quote">' => '<div class="bbc_quote alternate">'));
			}

			// This is long, but it makes things much easier and cleaner.
			if (!empty($possible['parameters']))
			{
				$preg = array();
				foreach ($possible['parameters'] as $p => $info)
					$preg[] = '(\s+' . $p . '=' . (empty($info['quoted']) ? '' : '&quot;') . (isset($info['match']) ? $info['match'] : '(.+?)') . (empty($info['quoted']) ? '' : '&quot;') . ')' . (empty($info['optional']) ? '' : '?');

				// Okay, this may look ugly and it is, but it's not going to happen much and it is the best way of allowing any order of parameters but still parsing them right.
				$match = false;
				$orders = bbc_permute($preg);
				foreach ($orders as $p)
				{
					if (preg_match('~^' . implode('', $p) . '\]~i', substr($message, $pos1 - 1), $matches) === 1)
					{
						$match = true;
						break;
					}
				}

				// Didn't match our parameter list, try the next possible.
				if (!$match)
					continue;

				$params = array();
				for ($i = 1, $n = count($matches); $i < $n; $i += 2)
				{
					$key = strtok(ltrim($matches[$i]), '=');
					if (isset($possible['parameters'][$key]['value']))
						$params['{' . $key . '}'] = strtr($possible['parameters'][$key]['value'], array('$1' => $matches[$i + 1]));
					elseif (isset($possible['parameters'][$key]['validate']))
						$params['{' . $key . '}'] = $possible['parameters'][$key]['validate']($matches[$i + 1]);
					else
						$params['{' . $key . '}'] = $matches[$i + 1];

					// Just to make sure: replace any $ or { so they can't interpolate wrongly.
					$params['{' . $key . '}'] = strtr($params['{' . $key . '}'], array('$' => '&#036;', '{' => '&#123;'));
				}

				foreach ($possible['parameters'] as $p => $info)
					if (!isset($params['{' . $p . '}']))
						$params['{' . $p . '}'] = '';

				$tag = $possible;

				// Put the parameters into the string.
				if (isset($tag['before']))
					$tag['before'] = strtr($tag['before'], $params);
				if (isset($tag['after']))
					$tag['after'] = strtr($tag['after'], $params);
				if (isset($tag['content']))
					$tag['content'] = strtr($tag['content'], $params);

				$pos1 += strlen($matches[0]) - 1;
			}
			else
				$tag = $possible;
			break;
		}

		// Item codes are complicated buggers... they are implicit [li]s and can make [list]s!
		if ($smileys !== false && $tag === null && isset($itemcodes[$message[$pos + 1]]) && $message[$pos + 2] === ']' && !isset($disabled['list']) && !isset($disabled['li']))
		{
			if ($message[$pos + 1] === '0' && !in_array($message[$pos - 1], array(';', ' ', "\t", '>')))
				continue;
			$tag = $itemcodes[$message[$pos + 1]];

			// First let's set up the tree: it needs to be in a list, or after an li.
			if ($inside === null || ($inside['tag'] != 'list' && $inside['tag'] != 'li'))
			{
				$open_tags[] = array(
					'tag' => 'list',
					'after' => '</ul>',
					'block_level' => true,
					'require_children' => array('li'),
					'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
					'trim' => 'outside',
				);
				$code = '<ul class="bbc_list">';
			}
			// We're in a list item already: another itemcode? Close it first.
			elseif ($inside['tag'] === 'li')
			{
				array_pop($open_tags);
				$code = '</li>';
			}
			else
				$code = '';

			// Now we open a new tag.
			$open_tags[] = array(
				'tag' => 'li',
				'after' => '</li>',
				'trim' => 'outside',
				'block_level' => true,
				'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
			);

			// First, open the tag...
			$code .= '<li' . ($tag === '' ? '' : ' type="' . $tag . '"') . '>';
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos + 3);
			$pos += strlen($code) + 1;

			// Next, find the next break (if any.) If there's more itemcode after it, keep it going - otherwise close!
			$pos2 = strpos($message, '<br>', $pos);
			$pos3 = strpos($message, '[/', $pos);
			if ($pos2 !== false && ($pos2 <= $pos3 || $pos3 === false))
			{
				preg_match('~^(<br>|&nbsp;|\s|\[)+~', substr($message, $pos2 + 6), $matches);
				$message = substr($message, 0, $pos2) . "\n" . (!empty($matches[0]) && substr($matches[0], -1) === '[' ? '[/li]' : '[/li][/list]') . "\n" . substr($message, $pos2);

				$open_tags[count($open_tags) - 2]['after'] = '</ul>';
			}
			// Tell the [list] that it needs to close specially.
			else
			{
				// Move the li over, because we're not sure what we'll hit.
				$open_tags[count($open_tags) - 1]['after'] = '';
				$open_tags[count($open_tags) - 2]['after'] = '</li></ul>';
			}

			continue;
		}

		// Implicitly close lists and tables if something other than what's required is in them. This is needed for itemcode.
		if ($tag === null && $inside !== null && !empty($inside['require_children']))
		{
			array_pop($open_tags);

			$message = substr($message, 0, $pos) . "\n" . $inside['after'] . "\n" . substr($message, $pos);
			$pos += strlen($inside['after']) + 1;
		}

		// No tag? Keep looking, then. Silly people using brackets without actual tags.
		if ($tag === null)
			continue;

		// Propagate the list to the child (so wrapping the disallowed tag won't work either.)
		if (isset($inside['disallow_children']))
			$tag['disallow_children'] = isset($tag['disallow_children']) ? array_unique(array_merge($tag['disallow_children'], $inside['disallow_children'])) : $inside['disallow_children'];

		// Is this tag disabled?
		if (isset($disabled[$tag['tag']]))
		{
			if (!isset($tag['disabled_before']) && !isset($tag['disabled_after']) && !isset($tag['disabled_content']))
			{
				$tag['before'] = !empty($tag['block_level']) ? '<div>' : '';
				$tag['after'] = !empty($tag['block_level']) ? '</div>' : '';
				$tag['content'] = isset($tag['type']) && $tag['type'] === 'closed' ? '' : (!empty($tag['block_level']) ? '<div>$1</div>' : '$1');
			}
			elseif (isset($tag['disabled_before']) || isset($tag['disabled_after']))
			{
				$tag['before'] = isset($tag['disabled_before']) ? $tag['disabled_before'] : (!empty($tag['block_level']) ? '<div>' : '');
				$tag['after'] = isset($tag['disabled_after']) ? $tag['disabled_after'] : (!empty($tag['block_level']) ? '</div>' : '');
			}
			else
				$tag['content'] = $tag['disabled_content'];
		}

		// The only special case is 'html', which doesn't need to close things.
		if (!empty($tag['block_level']) && $tag['tag'] != 'html' && empty($inside['block_level']))
		{
			$n = count($open_tags) - 1;
			while (empty($open_tags[$n]['block_level']) && $n >= 0)
				$n--;

			// Close all the non-block-level tags so this tag isn't surrounded by them.
			for ($i = count($open_tags) - 1; $i > $n; $i--)
			{
				$message = substr($message, 0, $pos) . "\n" . $open_tags[$i]['after'] . "\n" . substr($message, $pos);
				$len = strlen($open_tags[$i]['after']) + 2;
				$pos += $len;
				$pos1 += $len;

				// Trim or eat trailing stuff... see comment at the end of the big loop.
				if (!empty($open_tags[$i]['block_level']) && substr($message, $pos, 4) === '<br>')
					$message = substr($message, 0, $pos) . substr($message, $pos + 4);
				if (!empty($open_tags[$i]['trim']) && ($tag['trim'] === 'outside' || $tag['trim'] === 'both') && preg_match('~^(?:<br>|&nbsp;|\s)+~', substr($message, $pos), $matches) === 1)
					$message = substr($message, 0, $pos) . substr($message, $pos + strlen($matches[0]));

				array_pop($open_tags);
			}
		}

		// No type means 'parsed_content'.
		if (!isset($tag['type']))
		{
			// !!! Check for end tag first, so people can say "I like that [i] tag"?
			$open_tags[] = $tag;
			$message = substr($message, 0, $pos) . "\n" . $tag['before'] . "\n" . substr($message, $pos1);
			$pos += strlen($tag['before']) + 1;
		}
		// Don't parse the content, just skip it.
		elseif ($tag['type'] === 'unparsed_content')
		{
			$pos2 = stripos($message, '[/' . substr($message, $pos + 1, $tag['len']) . ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			if (!empty($tag['block_level']) && substr($data, 0, 4) === '<br>')
				$data = substr($data, 4);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 3 + $tag['len']);

			$pos += strlen($code) + 1;
			$last_pos = $pos + 1;
		}
		// Don't parse the content, just skip it.
		elseif ($tag['type'] === 'unparsed_equals_content')
		{
			// The value may be quoted for some tags - check.
			if (isset($tag['quoted']))
			{
				$quoted = substr($message, $pos1, 6) === '&quot;';
				if ($tag['quoted'] != 'optional' && !$quoted)
					continue;

				if ($quoted)
					$pos1 += 6;
			}
			else
				$quoted = false;

			$pos2 = strpos($message, $quoted ? '&quot;]' : ']', $pos1);
			if ($pos2 === false)
				continue;

			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag['len']) . ']', $pos2);
			if ($pos3 === false)
				continue;

			$data = array(
				substr($message, $pos2 + ($quoted ? 7 : 1), $pos3 - $pos2 - ($quoted ? 7 : 1)),
				substr($message, $pos1, $pos2 - $pos1)
			);

			if (!empty($tag['block_level']) && substr($data[0], 0, 4) === '<br>')
				$data[0] = substr($data[0], 4);

			// Validation for my parking, please!
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data[0], '$2' => $data[1]));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag['len']);
			$pos += strlen($code) + 1;
		}
		// A closed tag, with no content or value.
		elseif ($tag['type'] === 'closed')
		{
			if ($tag['tag'] === 'more')
			{
				if (!empty($context['current_topic']))
				{
					$pos2 = strpos($message, ']', $pos);
					$message = '<div class="headline">' . substr($message, 0, $pos) . '</div>' . substr($message, $pos2 + 1);
					$pos = $pos2 + 22;
				}
				else
				{
					$lent = westr::strlen(substr($message, $pos));
					if ($lent > 0)
					{
						// Add the headline class as well. It's up to CSS to style it differently outside topics.
						$message = '<div class="headline">' . rtrim(substr($message, 0, $pos));
						while (substr($message, -4) === '<br>')
							$message = substr($message, 0, -4);
						$message .= ' <span class="readmore">' . sprintf($txt['readmore'], $lent) . '</span></div>';
						$pos = false;
					}
				}
			}
			else
			{
				$pos2 = strpos($message, ']', $pos);
				$message = substr($message, 0, $pos) . "\n" . $tag['content'] . "\n" . substr($message, $pos2 + 1);
				$pos += strlen($tag['content']) + 1;
			}
		}
		// This one is sorta ugly... Unfortunately, it's needed for Flash. :-/
		elseif ($tag['type'] === 'unparsed_commas_content')
		{
			$pos2 = strpos($message, ']', $pos1);
			if ($pos2 === false)
				continue;

			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, $tag['len']) . ']', $pos2);
			if ($pos3 === false)
				continue;

			// We want $1 to be the content, and the rest to be csv.
			$data = explode(',', ',' . substr($message, $pos1, $pos2 - $pos1));
			$data[0] = substr($message, $pos2 + 1, $pos3 - $pos2 - 1);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = $tag['content'];
			foreach ($data as $k => $d)
				$code = strtr($code, array('$' . ($k + 1) => trim($d)));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + $tag['len']);
			$pos += strlen($code) + 1;
		}
		// This has parsed content, and a csv value which is unparsed.
		elseif ($tag['type'] === 'unparsed_commas')
		{
			$pos2 = strpos($message, ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = explode(',', substr($message, $pos1, $pos2 - $pos1));

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			// Fix after, for disabled code mainly.
			foreach ($data as $k => $d)
				$tag['after'] = strtr($tag['after'], array('$' . ($k + 1) => trim($d)));

			$open_tags[] = $tag;

			// Replace them out, $1, $2, $3, $4, etc.
			$code = $tag['before'];
			foreach ($data as $k => $d)
				$code = strtr($code, array('$' . ($k + 1) => trim($d)));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 1);
			$pos += strlen($code) + 1;
		}
		// A tag set to a value, parsed or not.
		elseif ($tag['type'] === 'unparsed_equals' || $tag['type'] === 'parsed_equals')
		{
			// The value may be quoted for some tags - check.
			if (isset($tag['quoted']))
			{
				$quoted = substr($message, $pos1, 6) === '&quot;';
				if ($tag['quoted'] != 'optional' && !$quoted)
					continue;

				if ($quoted)
					$pos1 += 6;
			}
			else
				$quoted = false;

			$pos2 = strpos($message, $quoted ? '&quot;]' : ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			// Validation for my parking, please!
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			// For parsed content, we must recurse to avoid security problems.
			if ($tag['type'] !== 'unparsed_equals')
				$data = parse_bbc($data, !empty($tag['parsed_tags_allowed']) ? false : true, '', !empty($tag['parsed_tags_allowed']) ? $tag['parsed_tags_allowed'] : array());

			$tag['after'] = strtr($tag['after'], array('$1' => $data));

			$open_tags[] = $tag;

			$code = strtr($tag['before'], array('$1' => $data));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + ($quoted ? 7 : 1));
			$pos += strlen($code) + 1;
		}

		// If this is block-level, eat any breaks after it.
		if (!empty($tag['block_level']) && substr($message, $pos + 1, 4) === '<br>')
			$message = substr($message, 0, $pos + 1) . substr($message, $pos + 5);

		// Are we trimming inside this tag?
		if (($tag['trim'] === 'inside' || $tag['trim'] === 'both') && preg_match('~^(?:<br>|&nbsp;|\s)+~', substr($message, $pos + 1), $matches) === 1)
			$message = substr($message, 0, $pos + 1) . substr($message, $pos + 1 + strlen($matches[0]));
	}

	// Close any remaining tags.
	while ($tag = array_pop($open_tags))
		$message .= "\n" . $tag['after'] . "\n";

	// Parse the smileys within the parts where it can be done safely.
	if ($smileys === true)
	{
		$message_parts = explode("\n", $message);
		for ($i = 0, $n = count($message_parts); $i < $n; $i += 2)
			parsesmileys($message_parts[$i]);

		$message = implode('', $message_parts);
	}

	// No smileys, just get rid of the markers.
	else
		$message = strtr($message, array("\n" => ''));

	if ($message[0] === ' ')
		$message = '&nbsp;' . substr($message, 1);

	// Cleanup whitespace.
	$message = strtr($message, array('  ' => ' &nbsp;', "\r" => '', "\n" => '<br>', '<br> ' => '<br>&nbsp;', '&#13;' => "\n"));

	if (empty($parse_tags))
	{
		// Do the actual embedding
		if (strlen($message) > 15 && strpos($message, '<a href="') !== false)
		{
			loadSource('media/Aeva-Embed');

			/*
				Do not attempt to auto-embed if Aeva is disabled, or for
				- Printer-friendly pages ($smileys === 'print')
				- Messages that don't contain links
				- Signatures/Wysiwyg window (or anywhere where $context['embed_disable'] is set)
				- SSI functions such as ssi_recentTopics() (they tend to crash your browser)
			*/

			if (!empty($settings['embed_enabled']) && empty($context['embed_disable']) && strpos($message, 'http://') !== false && $smileys !== 'print' && strpos($cache_id, 'sig') === false)
				$message = aeva_main($message);

			// And reverses any protection already in place
			$message = aeva_reverse_protection($message);

			// Reset any technical reasons to stop
			unset($context['embed_disable']);
			if (isset($context['aeva']['skip']))
				unset($context['aeva']['skip']);
		}

		if (empty($disabled['media']) && stripos($message, '[media') !== false)
		{
			loadSource('media/Subs-Media');
			aeva_parse_bbc($message, $cache_id);
		}

		if (strpos($message, '[noembed]') !== false)
			$message = str_replace(array('[noembed]', '[/noembed]'), '', $message);
	}

	// Deal with footnotes... They're more complex, so can't be parsed like other bbcodes.
	if (stripos($message, '[nb]') !== false && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'jseditor') && (empty($parse_tags) || in_array('nb', $parse_tags)))
	{
		preg_match_all('~\[nb]((?>[^[]|\[(?!/?nb])|(?R))+?)\[/nb\]~i', $message, $matches, PREG_SET_ORDER);

		if (count($matches) > 0)
		{
			$f = 0;
			global $addnote;
			if (is_null($addnote))
				$addnote = array();
			foreach ($matches as $m)
			{
				$my_pos = $end_blockquote = strpos($message, $m[0]);
				$message = substr_replace($message, '<a class="fnotel" id="footlink' . ++$feet . '" href="#footnote' . $feet . '">[' . ++$f . ']</a>', $my_pos, strlen($m[0]));
				$addnote[$feet] = array($feet, $f, $m[1]);

				while ($end_blockquote !== false)
				{
					$end_blockquote = strpos($message, '</blockquote>', $my_pos);
					if ($end_blockquote === false)
						continue;

					$start_blockquote = strpos($message, '<blockquote', $my_pos);
					if ($start_blockquote !== false && $start_blockquote < $end_blockquote)
						$my_pos = $end_blockquote + 1;
					else
					{
						$message = substr_replace($message, '<foot:' . $feet . '>', $end_blockquote, 0);
						break;
					}
				}

				if ($end_blockquote === false)
					$message .= '<foot:' . $feet . '>';
			}

			$message = preg_replace_callback('~(?:<foot:\d+>)+~', 'parse_footnotes', $message);
		}
	}

	// Is there anything we want to do just before we go home?
	call_hook('post_bbc_parse', array(&$message, &$smileys, $cache_id, $parse_tags, $owner));

	// There might possibly be some things to do.
	if (!empty($owner) && !empty($user_profile[$owner]))
	{
		// Have you been naughty? Well, have you?
		// !!! Note that there might be other punishments yet, so leave these as two separate ifs.
		if (!empty($user_profile[$owner]['sanctions']['disemvowel']) && $user_profile[$owner]['sanctions']['disemvowel'] < time())
			$message = disemvowel($message);
	}

	// Cache the output if it took some time...
	if (isset($cache_key, $cache_t) && microtime(true) - $cache_t > 0.05)
		cache_put_data($cache_key, $message, 240);

	// If this was a force parse revert if needed.
	if (!empty($parse_tags))
	{
		if (empty($temp_bbc))
			$bbc_codes = array();
		else
		{
			$bbc_codes = $temp_bbc;
			unset($temp_bbc);
		}
	}

	return $message;
}

/**
 * Takes the specified message, parses it for smileys and updates them in place.
 *
 * This function is called from {@link parse_bbc()} to manage smileys, depending on whether smileys were requested, and whether this is the printer-friendly version.
 * - Firstly, load the default smileys; if custom smileys are not enabled, use the default set, otherwise load them from cache (if available) or database. They will persist for the life of page in any case (stored statically in the function for multiple calls)
 * - A complex regular expression is then built up of all the search/replaces to be made to substitute all the smileys for their image counterparts.
 * - The regular expression is crafted so expressions within tags do not get parsed, e.g. [url=mailto:David@bla.com] doesn't parse the :D smiley
 *
 * @param string &$message The original message, by reference, so it can be updated in place for smileys.
 */
function parsesmileys(&$message)
{
	global $smileyPregReplace;
	static $smileyPregSearch = '';

	// No smiley set at all?!
	if (we::$user['smiley_set'] === 'none')
		return;

	// If the smiley array hasn't been set, do it now.
	if (empty($smileyPregSearch))
	{
		global $settings, $txt, $context, $cssdir;

		// Use the default smileys if custom smileys are disabled. (Better for "portability".)
		if (empty($settings['smiley_enable']))
		{
			$smileysfrom = array('>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', 'O:-)', ':edit:');
			$smileysto = array('evil.gif', 'cheesy.gif', 'rolleyes.gif', 'angry.gif', 'laugh.gif', 'smiley.gif', 'wink.gif', 'grin.gif', 'sad.gif', 'shocked.gif', 'cool.gif', 'tongue.gif', 'huh.gif', 'embarrassed.gif', 'lipsrsealed.gif', 'kiss.gif', 'cry.gif', 'undecided.gif', 'azn.gif', 'afro.gif', 'police.gif', 'angel.gif', 'edit.gif');
			$smileysdiv = array();
			foreach ($smileysto as $file)
				$smileysdiv[] = array('embed' => true, 'name' => str_replace('.', '_', $file));
		}
		else
		{
			// Load the smileys in reverse order by length so they don't get parsed wrong.
			if (($temp = cache_get_data('smiley_parser', 480)) == null || !isset($temp[3]) || !is_array($temp[3]))
			{
				$result = wesql::query('
					SELECT code, filename, hidden
					FROM {db_prefix}smileys',
					array()
				);
				$smileysfrom = array();
				$smileysto = array();
				$smileysdiv = array();
				while ($row = wesql::fetch_assoc($result))
				{
					$smileysfrom[] = $row['code'];
					$smileysto[] = $row['filename'];
					$smileysdiv[] = array(
						'embed' => $row['hidden'] == 0,
						'name' => preg_replace(array('~[^\w]~', '~_+~'), array('_', '_'), $row['filename'])
					);
				}
				wesql::free_result($result);

				cache_put_data('smiley_parser', array($smileysfrom, $smileysto, $smileysdiv), 480);
			}
			else
				list ($smileysfrom, $smileysto, $smileysdiv) = $temp;
		}

		// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
		for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
		{
			$safe = htmlspecialchars($smileysfrom[$i], ENT_QUOTES); // !!! Use westr version?
			$smileyCode = '<i class="smiley ' . $smileysdiv[$i]['name'] . '">' . $safe . '</i>';

			$smileyPregReplace[$smileysfrom[$i]] = $smileyCode;
			$searchParts[] = preg_quote($smileysfrom[$i], '~');

			if ($safe != $smileysfrom[$i])
			{
				$smileyPregReplace[$safe] = $smileyCode;
				$searchParts[] = preg_quote($safe, '~');
			}
		}

		$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
		$context['smiley_gzip'] = $can_gzip;
		$context['smiley_ext'] = $can_gzip ? (we::is('safari') ? '.cgz' : '.css.gz') : '.css';
		$extra = we::is('ie6,ie7') ? '-ie' : '';
		$var_name = 'smiley_cache-' . str_replace('.', '', $context['smiley_ext']) . $extra . '-' . we::$user['smiley_set'];
		$context['smiley_now'] = empty($settings[$var_name]) ? time() : $settings[$var_name];

		if (!file_exists($cssdir . '/smileys' . $extra . '-' . we::$user['smiley_set'] . '-' . $context['smiley_now'] . $context['smiley_ext']))
		{
			// We're only going to cache the smileys that show up on the post editor by default.
			// The reason is to help save bandwidth by only storing whatever is most likely to be used.
			$cache = array();
			for ($i = 0; $i < $n; $i++)
				$cache[$smileysdiv[$i]['name']] = array('embed' => $smileysdiv[$i]['embed'], 'file' => $smileysto[$i]);
			if (!empty($cache))
			{
				loadSource('Subs-Cache');
				wedge_cache_smileys(we::$user['smiley_set'], $cache);
			}
		}

		$smileyPregSearch = '~(?<=[>:?.\s\x{A0}[\]()*\\\;]|^)(' . implode('|', $searchParts) . ')(?=[^[:alpha:]0-9]|$)~u';
	}

	// Replace away!
	$message = preg_replace_callback($smileyPregSearch, 'replace_smileys', $message);
}

// Quick preg_replace_callback...
function replace_smileys($match)
{
	global $smileyPregReplace, $smiley_css_done;

	if (isset($smileyPregReplace[$match[1]]))
	{
		if (empty($smiley_css_done))
		{
			global $boardurl, $settings, $context;

			$smiley_css_done = true;
			$context['header'] .= '
	<link rel="stylesheet" href="' . $boardurl . '/css/smileys' . (we::is('ie6,ie7') ? '-ie' : '') . '-' . we::$user['smiley_set'] . '-' . $context['smiley_now'] . $context['smiley_ext'] . '">';
		}
		return $smileyPregReplace[$match[1]];
	}
	return '';
}

// The footnote parser. As the name says.
function parse_footnotes($match)
{
	global $addnote;

	$msg = '<table class="footnotes w100">';
	preg_match_all('~<foot:(\d+)>~', $match[0], $mat);
	foreach ($mat[1] as $note)
	{
		$n =& $addnote[$note];
		$msg .= '<tr><td class="footnum"><a id="footnote' . $n[0] . '" href="#footlink' . $n[0] . '">&nbsp;' . $n[1] . '.&nbsp;</a></td><td class="footnote">'
			 . (stripos($n[2], '[nb]', 1) === false ? $n[2] : parse_bbc($n[2])) . '</td></tr>';
	}
	return $msg . '</table>';
}

/**
 * Highlights any PHP code within posts, where either the PHP start tag, or the php bbcode tag is used.
 *
 * Highlighting is performed with PHP's highlight_string() function and will use the coloring and formatting rules that come with that function.
 *
 * @param string $code The original code, as from the bbcode parser.
 * @return string The string with HTML markup for formatting, and with custom handling of tabs in an attempt to preserve that formatting.
 */
function highlight_php_code($code)
{
	global $context;

	// Remove special characters.
	$code = un_htmlspecialchars(strtr($code, array('<br>' => "\n", "\t" => 'WEDGE_TAB();', '&#91;' => '[')));

	$oldlevel = error_reporting(0);
	$buffer = str_replace(array("\n", "\r"), '', @highlight_string($code, true));
	error_reporting($oldlevel);

	// Yes, I know this is kludging it, but this is the best way to preserve tabs from PHP :P
	$buffer = preg_replace('~WEDGE_TAB(?:</(?:font|span)><(?:font color|span style)="[^"]*?">)?\\(\\);~', '<span class="bbc_pre">' . "\t" . '</span>', $buffer);

	return strtr($buffer, array('\'' => '&#039;', '<code>' => '', '</code>' => ''));
}

/**
 * This function returns all possible permutations of an array.
 *
 * Notes:
 * - This function returns an array of arrays based on the values supplied to it. E.g. array(1,2,3,4) will be returned as a series of arrays of permutations based on that, e.g. array(4,3,2,1), array(1,3,2,4)
 * - The algorithm used does not ensure uniqueness, in fact given array(1,2,3,4), there are 3 instances of duplicate permutations. However, this would be faster than exhaustively computing it, or searching the array for uniqueness after.
 * - This function is used in one and only one place: within the bbcode parser, for parameters being provided so they can be processed regardless of the specified order.
 * - It is strongly not recommended to call this function with many (more than 8) options in the source array.
 *
 * @param array $array An indexed array of values
 * @return array An array of indexes arrays, representing all the permutations of the elements in the source $array.
 */
function bbc_permute($array)
{
	$orders = array($array);

	$n = count($array);
	$p = range(0, $n);
	for ($i = 1; $i < $n; null)
	{
		$p[$i]--;
		$j = $i % 2 != 0 ? $p[$i] : 0;

		$temp = $array[$i];
		$array[$i] = $array[$j];
		$array[$j] = $temp;

		for ($i = 1; $p[$i] === 0; $i++)
			$p[$i] = 1;

		$orders[] = $array;
	}

	return $orders;
}

/**
 * Handles censoring of provided text, subject to whether the current board can be disabled and it is disabled by the current user.
 *
 * Like a number of functions, this works by modifying the text in place through accepting the text by reference. The word censoring is based on two lists, held in $settings['censor_vulgar'] and $settings['censor_proper'], which are new-line delineated lists of search/replace pairs.
 *
 * @param string &$text The string to be censored, by reference (so updating this string, the master string will be updated too)
 * @param bool $force Whether to force it to be censored, even if user and theme settings might indicate otherwise.
 * @return string The censored text is also returned by reference and as such can be safely used in assignments as well as its more common use.
 */
function &censorText(&$text, $force = false)
{
	global $settings, $options, $theme, $txt;
	static $censor_vulgar = null, $censor_proper;

	if ((!empty($options['show_no_censored']) && $settings['allow_no_censored'] && !$force) || empty($settings['censor_vulgar']))
		return $text;

	// If they haven't yet been loaded, load them.
	if ($censor_vulgar == null)
	{
		$censor_vulgar = explode("\n", $settings['censor_vulgar']);
		$censor_proper = explode("\n", $settings['censor_proper']);

		// Quote them for use in regular expressions.
		for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
		{
			$censor_vulgar[$i] = strtr(preg_quote($censor_vulgar[$i], '/'), array('\\\\\\*' => '[*]', '\\*' => '[^\s]*?', '&' => '&amp;'));
			$censor_vulgar[$i] = (empty($settings['censorWholeWord']) ? '/' . $censor_vulgar[$i] . '/' : '/(?<=^|\W)' . $censor_vulgar[$i] . '(?=$|\W)/') . (empty($settings['censorIgnoreCase']) ? '' : 'i') . 'u';

			if (strpos($censor_vulgar[$i], '\'') !== false)
			{
				$censor_proper[count($censor_vulgar)] = $censor_proper[$i];
				$censor_vulgar[count($censor_vulgar)] = strtr($censor_vulgar[$i], array('\'' => '&#039;'));
			}
		}
	}

	// Censoring isn't so very complicated :P.
	$text = preg_replace($censor_vulgar, $censor_proper, $text);
	return $text;
}

/**
 * Attempts to strip all the vowels out of a post, usually for troublemakers, leaving their posts readable but easily ignorable.
 *
 * May exhibit strange behaviour with accented characters. Not sure there's a good way around that.
 *
 * @param string The message, should be already mostly HTML parsed, just needing final clean-up before leaving the bbc parser
 * @return string The message, with hopefully the best complement of vowels removed
 */
function disemvowel($message)
{
	$parts = preg_split('~(<.+?>)~', $message, null, PREG_SPLIT_DELIM_CAPTURE);
	$inside_script = $inside_cdata = $inside_comment = false;
	foreach ($parts as $id => &$part)
	{
		if (empty($part))
			continue;

		// We need to do special handling for funky tags because of plugin authors and crazy admins who inject scripts and other badness via bbcodes.
		if (stripos($part, '<script') === 0)
		{
			$inside_script = true;
			continue;
		}
		elseif ($inside_script)
		{
			if (stripos($part, '</script') !== false)
				$inside_script = false;

			continue;
		}

		// We don't have to worry about comments most of the time because the only normal use is to insulate script code - but that should be covered by the above
		if (stripos($part, '<!--') === 0)
		{
			$inside_comment = true;
			continue;
		}
		elseif ($inside_comment)
		{
			if (stripos($part, '-->') !== false)
				$inside_comment = false;

			continue;
		}

		// CDATA is rare but we gotta check.
		if (stripos($part, '<![CDATA[') === 0)
		{
			$inside_cdata = true;
			continue;
		}
		elseif ($inside_comment)
		{
			if (stripos($part, ']]>') !== false)
				$inside_cdata = false;

			continue;
		}

		// Now that icky is out the way
		if ($part[0] === '<')
			continue;

		// Now, comes Mr Super Happy Fun Time: parse all entities out, try to transliterate where possible, strip the vowels, then re-entify it. Not cheap, either.
		$part = html_entity_decode($part, ENT_QUOTES, 'UTF-8');
		if (is_callable('iconv'))
			$part = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $part);
		$part = htmlspecialchars(preg_replace('~[aeiou]~i', '', $part), ENT_QUOTES, 'UTF-8');
	}

	return implode('', $parts);
}
