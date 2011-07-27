<?php
/**
 * Wedge
 *
 * This file handles the parsing of BBC (Bulletin Board Code). Let's just say it's important on its own.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
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
 * @return mixed See parse_bbc()
 */
function parse_bbc_inline($message, $smileys = true, $cache_id = '', $short_list = true)
{
	return parse_bbc($message, $smileys, $cache_id, $short_list ?
		array(
			'b', 'i', 's', 'u',
			'email', 'ftp', 'iurl', 'url',
			'nobbc',
		) :
		array(
			'b', 'i', 's', 'u',
			'email', 'ftp', 'iurl', 'url',
			'abbr', 'me', 'nobbc', 'sub', 'sup', 'time',
			'color', 'black', 'blue', 'green', 'red', 'white'
		)
	);
}

/**
 * Returns the given string, parsed for most forms of bbcode, according to the function parameters and general application state.
 *
 * Notes:
 * - This function handles all bbcode parsing, as well as containing the list of all bbcodes known to the system, and where new bbcodes should be added.
 * - The state of bbcode disabled in the admin panel is stored in $modSettings['disabledBBC'] as a comma-separated list and parsed here.
 * - The master toggle switch of $modSettings['enableBBC'] is applied here, as is $modSettings['enablePostHTML'] being able to handle basic HTML (including b, u, i, s, em, ins, del, pre, blockquote; a and img are converted to bbcode equivalents)
 * - Long words are also fixed here as directed by $modSettings['fixLongWords'].
 *
 * @param mixed $message The original text, including bbcode, to be parsed. This is expected to have been parsed with {@link preparsecode()} previously (for handling of quotes and apostrophes). Alternatively, if boolean false is passed here, the return value is the array listing the acceptable bbcode types.
 * @param mixed $smileys Whether smileys should be parsed too, prior to (and in addition to) any bbcode, defaults to true. Nominally this is a boolean value, true for 'parse smileys', false for not, however the function also accepts the string 'print', for parsing in the print-page environment, which disables non printable tags and smileys. This is also overridden in wireless mode.
 * @param string $cache_id If specified, a quasi-unique key for the item being parsed, so that if it took over 0.05 seconds, it can be cached. (The final key used for the cache takes the supplied key and includes details such as the user's locale and time offsets, an MD5 digest of the message and other details that potentially affect the way parsing occurs)
 * @param array $parse_tags An array of tags that will be allowed for this parse only. (This overrides any user settings for what is and is not allowed. Additionally, runs with this set are never cached, regardless of cache id being set)
 * @return mixed If $message was boolean false, the return set is the master list of available bbcode, otherwise it is the parsed message.
 */
function parse_bbc($message, $smileys = true, $cache_id = '', $parse_tags = array())
{
	global $txt, $scripturl, $context, $modSettings, $user_info;
	static $master_codes = null, $bbc_codes = array(), $itemcodes = array(), $no_autolink_tags = array();
	static $disabled, $feet = 0;

	// Don't waste cycles
	if ($message === '')
		return '';

	// Never show smileys for wireless clients. More bytes, and may not see them anyway :P
	if (WIRELESS)
		$smileys = false;
	elseif ($smileys !== null && ($smileys == '1' || $smileys == '0'))
		$smileys = (bool) $smileys;

	if (empty($modSettings['enableBBC']) && $message !== false)
	{
		if ($smileys === true)
			parsesmileys($message);

		return $message;
	}

	if ($master_codes == null)
	{
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
			SELECT tag, bbctype, before_code, after_code, content, disabled_before,
				disabled_after, disabled_content, block_level, test, validate_func, disallow_children,
				require_parents, require_children, parsed_tags_allowed, quoted, params, trim_wspace
			FROM {db_prefix}bbcode',
			array()
		);

		while ($row = wesql::fetch_assoc($result))
		{
			$bbcode = array(
				'tag' => $row['tag'],
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
					$bbcode[$bbc_field] = preg_replace('~{{(\w+)}}~e', '$txt[\'$1\']', str_replace('{{scripturl}}', $scripturl, trim($row[$db_field])));

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

	if (empty($parse_tags) && empty($context['uninstalling']))
	{
		if (stripos($message, '[media') !== false)
		{
			if (!function_exists('aeva_protect_bbc'))
				loadSource('media/Subs-Media');
			aeva_protect_bbc($message);
		}

		// Protect noembed & autolink items from embedding *before* BBC parsing - wrap quotes, but don't protect
		if (!empty($modSettings['embed_enabled']) && strlen($message) > 15)
		{
			if (!function_exists('aeva_preprotect'))
				loadSource('media/Aeva-Embed');
			aeva_preprotect($message, $cache_id);
		}
	}

	// Sift out the bbc for a performance improvement.
	if (empty($bbc_codes) || $message === false || !empty($parse_tags))
	{
		if (!empty($modSettings['disabledBBC']))
		{
			$temp = explode(',', strtolower($modSettings['disabledBBC']));

			foreach ($temp as $tag)
				$disabled[trim($tag)] = true;
		}

		if (empty($modSettings['enableEmbeddedFlash']))
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
		$no_autolink_tags = array(
			'url',
			'iurl',
			'ftp',
			'email',
		);

		// If we are not doing every tag only do ones we are interested in.
		foreach ($master_codes as &$code)
			if (empty($parse_tags) || in_array($code['tag'], $parse_tags))
				$bbc_codes[substr($code['tag'], 0, 1)][] = $code;
	}

	// Shall we take the time to cache this?
	if ($cache_id != '' && !empty($modSettings['cache_enable']) && (($modSettings['cache_enable'] >= 2 && strlen($message) > 1000) || strlen($message) > 2400) && empty($parse_tags))
	{
		// It's likely this will change if the message is modified.
		$cache_key = 'parse:' . $cache_id . '-' . md5(md5($message) . '-' . $smileys . (empty($disabled) ? '' : implode(',', array_keys($disabled))) . serialize($context['browser']) . $txt['lang_locale'] . $user_info['time_offset'] . $user_info['time_format']);

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

	// This saves time by doing our break long words checks here.
	if (!empty($modSettings['fixLongWords']) && $modSettings['fixLongWords'] > 5)
	{
		if ($context['browser']['is_gecko'])
			$breaker = '<span style="margin: 0 -0.5ex 0 0"> </span>';
		// Opera...
		elseif ($context['browser']['is_opera'])
			$breaker = '<span style="margin: 0 -0.65ex 0 -1px"> </span>';
		// Internet Explorer...
		else
			$breaker = '<span style="width: 0; margin: 0 -0.6ex 0 -1px"> </span>';

		// PCRE will not be happy if we don't give it a short.
		$modSettings['fixLongWords'] = (int) min(65535, $modSettings['fixLongWords']);
	}

	$pos = -1;
	while ($pos !== false)
	{
		$last_pos = isset($last_pos) ? max($pos, $last_pos) : $pos;
		$pos = strpos($message, '[', $pos + 1);

		// Failsafe.
		if ($pos === false || $last_pos > $pos)
			$pos = strlen($message) + 1;

		// Can't have a one letter smiley, URL, or email! (sorry.)
		if ($last_pos < $pos - 1)
		{
			// Make sure the $last_pos is not negative.
			$last_pos = max($last_pos, 0);

			// Pick a block of data to do some raw fixing on.
			$data = substr($message, $last_pos, $pos - $last_pos);

			// Take care of some HTML!
			if (!empty($modSettings['enablePostHTML']) && strpos($data, '&lt;') !== false)
			{
				$data = preg_replace('~&lt;a\s+href=((?:&quot;)?)((?:https?://|ftps?://|mailto:)\S+?)\\1&gt;~i', '[url=$2]', $data);
				$data = preg_replace('~&lt;/a&gt;~i', '[/url]', $data);

				// <br> should be empty.
				foreach (array('br', 'hr') as $tag)
					$data = str_replace(array('&lt;' . $tag . '&gt;', '&lt;' . $tag . '/&gt;', '&lt;' . $tag . ' /&gt;'), '[' . $tag . ' /]', $data);

				// b, u, i, s, pre... basic closable tags.
				foreach (array('b', 'u', 'i', 's', 'em', 'ins', 'del', 'pre', 'blockquote') as $tag)
				{
					$diff = substr_count($data, '&lt;' . $tag . '&gt;') - substr_count($data, '&lt;/' . $tag . '&gt;');
					$data = strtr($data, array('&lt;' . $tag . '&gt;' => '<' . $tag . '>', '&lt;/' . $tag . '&gt;' => '</' . $tag . '>'));

					if ($diff > 0)
						$data = substr($data, 0, -1) . str_repeat('</' . $tag . '>', $diff) . substr($data, -1);
				}

				// Do <img ...> - with security... action= -> action-.
				preg_match_all('~&lt;img\s+src=((?:&quot;)?)((?:https?://|ftps?://)\S+?)\\1(?:\s+alt=(&quot;.*?&quot;|\S*?))?(?:\s?/)?&gt;~i', $data, $matches, PREG_PATTERN_ORDER);
				if (!empty($matches[0]))
				{
					$replaces = array();
					foreach ($matches[2] as $match => $imgtag)
					{
						$alt = empty($matches[3][$match]) ? '' : ' alt=' . preg_replace('~^&quot;|&quot;$~', '', $matches[3][$match]);

						// Remove action= from the URL - no funny business, now.
						if (preg_match('~action(=|%3d)(?!dlattach)~i', $imgtag) != 0)
							$imgtag = preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $imgtag);

						// Check if the image is larger than allowed.
						if (!empty($modSettings['max_image_width']) && !empty($modSettings['max_image_height']))
						{
							list ($width, $height) = url_image_size($imgtag);

							if (!empty($modSettings['max_image_width']) && $width > $modSettings['max_image_width'])
							{
								$height = (int) (($modSettings['max_image_width'] * $height) / $width);
								$width = $modSettings['max_image_width'];
							}

							if (!empty($modSettings['max_image_height']) && $height > $modSettings['max_image_height'])
							{
								$width = (int) (($modSettings['max_image_height'] * $width) / $height);
								$height = $modSettings['max_image_height'];
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

			if (!empty($modSettings['autoLinkUrls']))
			{
				// Are we inside tags that should be auto linked?
				$no_autolink_area = false;
				if (!empty($open_tags))
				{
					foreach ($open_tags as $open_tag)
						if (in_array($open_tag['tag'], $no_autolink_tags))
							$no_autolink_area = true;
				}

				// Don't go backwards.
				//!!! Don't think is the real solution....
				$lastAutoPos = isset($lastAutoPos) ? $lastAutoPos : 0;
				if ($pos < $lastAutoPos)
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
							'~(?<=[\s>.(;\'"]|^)((?:http|https)://[\w\-_%@:|]+(?:\.[\w%-]+)*(?::\d+)?(?:/[\w\~%.@!,?&;=#(){}+:\'\\\\-]*)*[/\w\~%@?;=#}\\\\-])~i',
							'~(?<=[\s>.(;\'"]|^)((?:ftp|ftps)://[\w%@:|-]+(?:\.[\w%-]+)*(?::\d+)?(?:/[\w\~%.@,?&;=#(){}+:\'\\\\-]*)*[/\w\~%@?;=#}\\\\-])~i',
							'~(?<=[\s>(\'<]|^)(www(?:\.[\w-]+)+(?::\d+)?(?:/[\w\~%.@!,?&;=#(){}+:\'\\\\-]*)*[/\w\~%@?;=#}\\\\-])~i'
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

			if (!empty($modSettings['fixLongWords']) && $modSettings['fixLongWords'] > 5)
			{
				// The idea is, find words xx long, and then replace them with xx + space + more.
				if (westr::strlen($data) > $modSettings['fixLongWords'])
				{
					// This is done in a roundabout way because $breaker has "long words" :P.
					$data = strtr($data, array($breaker => '< >', '&nbsp;' => "\xC2\xA0"));
					$data = preg_replace(
						'~(?<=[>;:!? \x{A0}\]()]|^)([\w\pL.]{' . $modSettings['fixLongWords'] . ',})~eu',
						'preg_replace(\'/(.{' . ($modSettings['fixLongWords'] - 1) . '})/u\', \'\\$1< >\', \'$1\')',
						$data);
					$data = strtr($data, array('< >' => $breaker, "\xC2\xA0" => '&nbsp;'));
				}
			}

			// If it wasn't changed, no copying or other boring stuff has to happen!
			if ($data != substr($message, $last_pos, $pos - $last_pos))
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

		$tags = strtolower(substr($message, $pos + 1, 1));

		if ($tags == '/' && !empty($open_tags))
		{
			$pos2 = strpos($message, ']', $pos + 1);
			if ($pos2 == $pos + 2)
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

					// The idea is, if we are LOOKING for a block level tag, we can close them on the way.
					if (strlen($look_for) > 0 && isset($bbc_codes[$look_for[0]]))
					{
						foreach ($bbc_codes[$look_for[0]] as $temp)
							if ($temp['tag'] == $look_for)
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
			if ((empty($open_tags) && (empty($tag) || $tag['tag'] != $look_for)))
			{
				$open_tags = $to_close;
				continue;
			}
			elseif (!empty($to_close) && $tag['tag'] != $look_for)
			{
				if ($block_level === null && isset($look_for[0], $bbc_codes[$look_for[0]]))
				{
					foreach ($bbc_codes[$look_for[0]] as $temp)
						if ($temp['tag'] == $look_for)
						{
							$block_level = $temp['block_level'];
							break;
						}
				}

				// We're not looking for a block level tag (or maybe even a tag that exists...)
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

				// See the comment at the end of the big loop - just eating whitespace ;).
				if ($tag['block_level'] && substr($message, $pos, 4) == '<br>')
					$message = substr($message, 0, $pos) . substr($message, $pos + 4);
				if (($tag['trim'] == 'outside' || $tag['trim'] == 'both') && preg_match('~(<br>|&nbsp;|\s)*~', substr($message, $pos), $matches) != 0)
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
			if (strtolower(substr($message, $pos + 1, strlen($possible['tag']))) != $possible['tag'])
				continue;

			$next_c = substr($message, $pos + 1 + strlen($possible['tag']), 1);

			// A test validation?
			if (isset($possible['test']) && preg_match('~^' . $possible['test'] . '~', substr($message, $pos + 1 + strlen($possible['tag']) + 1)) == 0)
				continue;
			// Do we want parameters?
			elseif (!empty($possible['parameters']))
			{
				if ($next_c != ' ')
					continue;
			}
			elseif (isset($possible['type']))
			{
				// Do we need an equal sign?
				if (in_array($possible['type'], array('unparsed_equals', 'unparsed_commas', 'unparsed_commas_content', 'unparsed_equals_content', 'parsed_equals')) && $next_c != '=')
					continue;
				// Maybe we just want a /...
				if ($possible['type'] == 'closed' && $next_c != ']' && substr($message, $pos + 1 + strlen($possible['tag']), 2) != '/]' && substr($message, $pos + 1 + strlen($possible['tag']), 3) != ' /]')
					continue;
				// An immediate ]?
				if ($possible['type'] == 'unparsed_content' && $next_c != ']')
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

			$pos1 = $pos + 1 + strlen($possible['tag']) + 1;

			// Quotes can have alternate styling, we do this php-side due to all the permutations of quotes.
			if ($possible['tag'] == 'quote')
			{
				// Start with standard
				$quote_alt = false;
				foreach ($open_tags as $open_quote)
				{
					// Every parent quote this quote has flips the styling
					if ($open_quote['tag'] == 'quote')
						$quote_alt = !$quote_alt;
				}
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
					if (preg_match('~^' . implode('', $p) . '\]~i', substr($message, $pos1 - 1), $matches) != 0)
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
				{
					if (!isset($params['{' . $p . '}']))
						$params['{' . $p . '}'] = '';
				}

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
		if ($smileys !== false && $tag === null && isset($itemcodes[substr($message, $pos + 1, 1)]) && substr($message, $pos + 2, 1) == ']' && !isset($disabled['list']) && !isset($disabled['li']))
		{
			if (substr($message, $pos + 1, 1) == '0' && !in_array(substr($message, $pos - 1, 1), array(';', ' ', "\t", '>')))
				continue;
			$tag = $itemcodes[substr($message, $pos + 1, 1)];

			// First let's set up the tree: it needs to be in a list, or after an li.
			if ($inside === null || ($inside['tag'] != 'list' && $inside['tag'] != 'li'))
			{
				$open_tags[] = array(
					'tag' => 'list',
					'after' => '</ul>',
					'block_level' => true,
					'require_children' => array('li'),
					'disallow_children' => isset($inside['disallow_children']) ? $inside['disallow_children'] : null,
				);
				$code = '<ul class="bbc_list">';
			}
			// We're in a list item already: another itemcode? Close it first.
			elseif ($inside['tag'] == 'li')
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
			$code .= '<li' . ($tag == '' ? '' : ' type="' . $tag . '"') . '>';
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos + 3);
			$pos += strlen($code) - 1 + 2;

			// Next, find the next break (if any.) If there's more itemcode after it, keep it going - otherwise close!
			$pos2 = strpos($message, '<br>', $pos);
			$pos3 = strpos($message, '[/', $pos);
			if ($pos2 !== false && ($pos2 <= $pos3 || $pos3 === false))
			{
				preg_match('~^(<br>|&nbsp;|\s|\[)+~', substr($message, $pos2 + 6), $matches);
				$message = substr($message, 0, $pos2) . "\n" . (!empty($matches[0]) && substr($matches[0], -1) == '[' ? '[/li]' : '[/li][/list]') . "\n" . substr($message, $pos2);

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
			$pos += strlen($inside['after']) - 1 + 2;
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
				$tag['content'] = isset($tag['type']) && $tag['type'] == 'closed' ? '' : (!empty($tag['block_level']) ? '<div>$1</div>' : '$1');
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

			// Close all the non block level tags so this tag isn't surrounded by them.
			for ($i = count($open_tags) - 1; $i > $n; $i--)
			{
				$message = substr($message, 0, $pos) . "\n" . $open_tags[$i]['after'] . "\n" . substr($message, $pos);
				$pos += strlen($open_tags[$i]['after']) + 2;
				$pos1 += strlen($open_tags[$i]['after']) + 2;

				// Trim or eat trailing stuff... see comment at the end of the big loop.
				if (!empty($open_tags[$i]['block_level']) && substr($message, $pos, 4) == '<br>')
					$message = substr($message, 0, $pos) . substr($message, $pos + 4);
				if (!empty($open_tags[$i]['trim']) && ($tag['trim'] == 'outside' || $tag['trim'] == 'both') && preg_match('~(<br>|&nbsp;|\s)*~', substr($message, $pos), $matches) != 0)
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
			$pos += strlen($tag['before']) - 1 + 2;
		}
		// Trim the urls
		elseif ($tag['type'] == 'unparsed_content' && $tag['tag'] == 'url')
		{
			$pos2 = stripos($message, '[/' . substr($message, $pos + 1, 3) . ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			if (!empty($tag['block_level']) && substr($data, 0, 4) == '<br>')
				$data = substr($data, 4);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data, '$2' => trim_url($data)));
			$message = substr($message, 0, $pos) . $code . substr($message, $pos2 + 6);
			$pos += strlen($code) - 1;
		}
		// Don't parse the content, just skip it.
		elseif ($tag['type'] == 'unparsed_content')
		{
			$pos2 = stripos($message, '[/' . substr($message, $pos + 1, strlen($tag['tag'])) . ']', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			if (!empty($tag['block_level']) && substr($data, 0, 4) == '<br>')
				$data = substr($data, 4);

			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + 3 + strlen($tag['tag']));

			$pos += strlen($code) - 1 + 2;
			$last_pos = $pos + 1;

		}
		// Don't parse the content, just skip it.
		elseif ($tag['type'] == 'unparsed_equals_content')
		{
			// The value may be quoted for some tags - check.
			if (isset($tag['quoted']))
			{
				$quoted = substr($message, $pos1, 6) == '&quot;';
				if ($tag['quoted'] != 'optional' && !$quoted)
					continue;

				if ($quoted)
					$pos1 += 6;
			}
			else
				$quoted = false;

			$pos2 = strpos($message, $quoted == false ? ']' : '&quot;]', $pos1);
			if ($pos2 === false)
				continue;
			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, strlen($tag['tag'])) . ']', $pos2);
			if ($pos3 === false)
				continue;

			$data = array(
				substr($message, $pos2 + ($quoted == false ? 1 : 7), $pos3 - ($pos2 + ($quoted == false ? 1 : 7))),
				substr($message, $pos1, $pos2 - $pos1)
			);

			if (!empty($tag['block_level']) && substr($data[0], 0, 4) == '<br>')
				$data[0] = substr($data[0], 4);

			// Validation for my parking, please!
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			$code = strtr($tag['content'], array('$1' => $data[0], '$2' => $data[1]));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + strlen($tag['tag']));
			$pos += strlen($code) - 1 + 2;
		}
		// A closed tag, with no content or value.
		elseif ($tag['type'] == 'closed')
		{
			if ($tag['tag'] == 'more')
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
		// This one is sorta ugly... :/ Unfortunately, it's needed for flash.
		elseif ($tag['type'] == 'unparsed_commas_content')
		{
			$pos2 = strpos($message, ']', $pos1);
			if ($pos2 === false)
				continue;
			$pos3 = stripos($message, '[/' . substr($message, $pos + 1, strlen($tag['tag'])) . ']', $pos2);
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
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos3 + 3 + strlen($tag['tag']));
			$pos += strlen($code) - 1 + 2;
		}
		// This has parsed content, and a csv value which is unparsed.
		elseif ($tag['type'] == 'unparsed_commas')
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
			$pos += strlen($code) - 1 + 2;
		}
		// A tag set to a value, parsed or not.
		elseif ($tag['type'] == 'unparsed_equals' || $tag['type'] == 'parsed_equals')
		{
			// The value may be quoted for some tags - check.
			if (isset($tag['quoted']))
			{
				$quoted = substr($message, $pos1, 6) == '&quot;';
				if ($tag['quoted'] != 'optional' && !$quoted)
					continue;

				if ($quoted)
					$pos1 += 6;
			}
			else
				$quoted = false;

			$pos2 = strpos($message, $quoted == false ? ']' : '&quot;]', $pos1);
			if ($pos2 === false)
				continue;

			$data = substr($message, $pos1, $pos2 - $pos1);

			// Validation for my parking, please!
			if (isset($tag['validate']))
				$tag['validate']($tag, $data, $disabled);

			// For parsed content, we must recurse to avoid security problems.
			if ($tag['type'] != 'unparsed_equals')
				$data = parse_bbc($data, !empty($tag['parsed_tags_allowed']) ? false : true, '', !empty($tag['parsed_tags_allowed']) ? $tag['parsed_tags_allowed'] : array());

			$tag['after'] = strtr($tag['after'], array('$1' => $data));

			$open_tags[] = $tag;

			$code = strtr($tag['before'], array('$1' => $data));
			$message = substr($message, 0, $pos) . "\n" . $code . "\n" . substr($message, $pos2 + ($quoted == false ? 1 : 7));
			$pos += strlen($code) - 1 + 2;
		}

		// If this is block level, eat any breaks after it.
		if (!empty($tag['block_level']) && substr($message, $pos + 1, 4) == '<br>')
			$message = substr($message, 0, $pos + 1) . substr($message, $pos + 5);

		// Are we trimming outside this tag?
		if (($tag['trim'] == 'inside' || $tag['trim'] == 'both') && preg_match('~(<br>|&nbsp;|\s)*~', substr($message, $pos + 1), $matches) != 0)
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

	if (substr($message, 0, 1) == ' ')
		$message = '&nbsp;' . substr($message, 1);

	// Cleanup whitespace.
	$message = strtr($message, array('  ' => ' &nbsp;', "\r" => '', "\n" => '<br>', '<br> ' => '<br>&nbsp;', '&#13;' => "\n"));

	if (empty($parse_tags) && empty($context['uninstalling']))
	{
		// Do the actual embedding
		if (!function_exists('aeva_parse_bbc2'))
			loadSource('media/Aeva-Embed');
		aeva_parse_bbc2($message, $smileys, $cache_id);

		if (function_exists('aeva_parse_bbc') && stripos($message, '[media') !== false)
			aeva_parse_bbc($message, $cache_id);
	}

	// Deal with footnotes... They're more complex, so can't be parsed like other bbcodes.
	if (stripos($message, '[nb]') !== false && (empty($parse_tags) || in_array('nb', $parse_tags)) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'jseditor'))
	{
		preg_match_all('~\s*\[nb]((?>[^[]|\[(?!/?nb])|(?R))+?)\[/nb\]~i', $message, $matches, PREG_SET_ORDER);

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

			$message = preg_replace_callback('~(?:<foot:\d+>)+~', create_function('$match', '
				global $addnote;
				$msg = \'<table class="footnotes" style="width: 100%">\';
				preg_match_all(\'~<foot:(\d+)>~\', $match[0], $mat);
				foreach ($mat[1] as $note)
				{
					$n = &$addnote[$note];
					$msg .= \'<tr><td class="footnum"><a id="footnote\' . $n[0] . \'" href="#footlink\' . $n[0] . \'">&nbsp;\' . $n[1] . \'.&nbsp;</a></td><td class="footnote">\'
						 . (stripos($n[2], \'[nb]\', 1) === false ? $n[2] : parse_bbc($n[2])) . \'</td></tr>\';
				}
				return $msg . \'</table>\';'), $message);
		}
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
 * This function is called from {@link parse_bbc()} to manage smileys, depending on whether smileys were requested, whether this is the printer-friendly version or wireless mode.
 * - Firstly, load the default smileys; if custom smileys are not enabled, use the default set, otherwise load them from cache (if available) or database. They will persist for the life of page in any case (stored statically in the function for multiple calls)
 * - A complex regular expression is then built up of all the search/replaces to be made to substitute all the smileys for their image counterparts.
 * - The regular expression is crafted so expressions within tags do not get parsed, e.g. [url=mailto:David@bla.com] doesn't parse the :D smiley
 *
 * @param string &$message The original message, by reference, so it can be updated in place for smileys.
 */
function parsesmileys(&$message)
{
	global $modSettings, $txt, $user_info, $context, $smileyPregReplace;
	static $smileyPregSearch = array();

	// No smiley set at all?!
	if ($user_info['smiley_set'] == 'none')
		return;

	// If the smiley array hasn't been set, do it now.
	if (empty($smileyPregSearch))
	{
		// Use the default smileys if it is disabled. (better for "portability" of smileys.)
		if (empty($modSettings['smiley_enable']))
		{
			$smileysfrom = array('>:D', ':D', '::)', '>:(', ':))', ':)', ';)', ';D', ':(', ':o', '8)', ':P', '???', ':-[', ':-X', ':-*', ':\'(', ':-\\', '^-^', 'O0', 'C:-)', '0:)', ':edit:');
			$smileysto = array('evil.gif', 'cheesy.gif', 'rolleyes.gif', 'angry.gif', 'laugh.gif', 'smiley.gif', 'wink.gif', 'grin.gif', 'sad.gif', 'shocked.gif', 'cool.gif', 'tongue.gif', 'huh.gif', 'embarrassed.gif', 'lipsrsealed.gif', 'kiss.gif', 'cry.gif', 'undecided.gif', 'azn.gif', 'afro.gif', 'police.gif', 'angel.gif', 'edit.gif');
			$smileysdescs = array('', $txt['icon_cheesy'], $txt['icon_rolleyes'], $txt['icon_angry'], '', $txt['icon_smiley'], $txt['icon_wink'], $txt['icon_grin'], $txt['icon_sad'], $txt['icon_shocked'], $txt['icon_cool'], $txt['icon_tongue'], $txt['icon_huh'], $txt['icon_embarrassed'], $txt['icon_lips'], $txt['icon_kiss'], $txt['icon_cry'], $txt['icon_undecided'], '', '', '', '', '');
		}
		else
		{
			// Load the smileys in reverse order by length so they don't get parsed wrong.
			if (($temp = cache_get_data('parsing_smileys', 480)) == null)
			{
				$result = wesql::query('
					SELECT code, filename, description
					FROM {db_prefix}smileys',
					array(
					)
				);
				$smileysfrom = array();
				$smileysto = array();
				$smileysdescs = array();
				while ($row = wesql::fetch_assoc($result))
				{
					$smileysfrom[] = $row['code'];
					$smileysto[] = $row['filename'];
					$smileysdescs[] = $row['description'];
				}
				wesql::free_result($result);

				cache_put_data('parsing_smileys', array($smileysfrom, $smileysto, $smileysdescs), 480);
			}
			else
				list ($smileysfrom, $smileysto, $smileysdescs) = $temp;
		}

		// This smiley regex makes sure it doesn't parse smileys within code tags (so [url=mailto:David@bla.com] doesn't parse the :D smiley)
		for ($i = 0, $n = count($smileysfrom); $i < $n; $i++)
		{
			$safe = htmlspecialchars($smileysfrom[$i], ENT_QUOTES); // !!! Use westr version?
			$smileyCode = '<img src="' . htmlspecialchars($modSettings['smileys_url'] . '/' . $user_info['smiley_set'] . '/' . $smileysto[$i]) . '" alt="' . strtr($safe, array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')). '" title="' . strtr(htmlspecialchars($smileysdescs[$i]), array(':' => '&#58;', '(' => '&#40;', ')' => '&#41;', '$' => '&#36;', '[' => '&#091;')) . '" class="smiley">';

			$smileyPregReplace[$smileysfrom[$i]] = $smileyCode;
			$searchParts[] = preg_quote($smileysfrom[$i], '~');

			if ($safe != $smileysfrom[$i])
			{
				$smileyPregReplace[$safe] = $smileyCode;
				$searchParts[] = preg_quote($safe, '~');
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
	global $smileyPregReplace;

	return isset($smileyPregReplace[$match[1]]) ? $smileyPregReplace[$match[1]] : '';
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
	$code = un_htmlspecialchars(strtr($code, array('<br>' => "\n", "\t" => 'SMF_TAB();', '&#91;' => '[')));

	$oldlevel = error_reporting(0);
	$buffer = str_replace(array("\n", "\r"), '', @highlight_string($code, true));
	error_reporting($oldlevel);

	// Yes, I know this is kludging it, but this is the best way to preserve tabs from PHP :P.
	$buffer = preg_replace('~SMF_TAB(?:</(?:font|span)><(?:font color|span style)="[^"]*?">)?\\(\\);~', '<span class="bbc_pre">' . "\t" . '</span>', $buffer);

	return strtr($buffer, array('\'' => '&#039;', '<code>' => '', '</code>' => ''));
}

/**
 * Shorten URLs
 *
 * This feature was shamelessly inspired by a mod by JayBachatero, which should have been made a core feature long ago. Thanks, man!
 *
 * @param string $url URL that should be shortened, in case it's longer than $modSettings['max_urlLength']
 * @return string The resulting string
 */
function trim_url($url)
{
	global $modSettings;

	$modSettings['max_urlLength'] = isset($modSettings['max_urlLength']) ? $modSettings['max_urlLength'] : 50;
	if (empty($modSettings['max_urlLength']))
		return $url;

	$u = html_entity_decode($url, ENT_QUOTES);

	// Check the length of the url
	if (westr::strlen($u) <= $modSettings['max_urlLength'])
		return $url;

	$break = $modSettings['max_urlLength'] / 2;
	return str_replace('&amp;hellip;', '&hellip;', htmlentities(preg_replace('/&[^&;]*$/', '', westr::substr($u, 0, floor($break))) . '&hellip;' . preg_replace('/^\d+;/', '', westr::substr($u, -ceil($break)))));
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

		for ($i = 1; $p[$i] == 0; $i++)
			$p[$i] = 1;

		$orders[] = $array;
	}

	return $orders;
}

/**
 * Handles censoring of provided text, subject to whether the current board can be disabled and it is disabled by the current user.
 *
 * Like a number of functions, this works by modifying the text in place through accepting the text by reference. The word censoring is based on two lists, held in $modSettings['censor_vulgar'] and $modSettings['censor_proper'], which are new-line delineated lists of search/replace pairs.
 *
 * @param string &$text The string to be censored, by reference (so updating this string, the master string will be updated too)
 * @param bool $force Whether to force it to be censored, even if user and theme settings might indicate otherwise.
 * @return string The censored text is also returned by reference and as such can be safely used in assignments as well as its more common use.
 */
function &censorText(&$text, $force = false)
{
	global $modSettings, $options, $settings, $txt;
	static $censor_vulgar = null, $censor_proper;

	if ((!empty($options['show_no_censored']) && $modSettings['allow_no_censored'] && !$force) || empty($modSettings['censor_vulgar']))
		return $text;

	// If they haven't yet been loaded, load them.
	if ($censor_vulgar == null)
	{
		$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
		$censor_proper = explode("\n", $modSettings['censor_proper']);

		// Quote them for use in regular expressions.
		for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
		{
			$censor_vulgar[$i] = strtr(preg_quote($censor_vulgar[$i], '/'), array('\\\\\\*' => '[*]', '\\*' => '[^\s]*?', '&' => '&amp;'));
			$censor_vulgar[$i] = (empty($modSettings['censorWholeWord']) ? '/' . $censor_vulgar[$i] . '/' : '/(?<=^|\W)' . $censor_vulgar[$i] . '(?=$|\W)/') . (empty($modSettings['censorIgnoreCase']) ? '' : 'i') . 'u';

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

?>