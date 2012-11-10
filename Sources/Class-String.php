<?php
/**
 * Wedge
 *
 * This file handles string processing. Please note that this is what we feel to be the sanest method of providing everything, but it's really not pretty.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

global $settings;

class westr_foundation
{
	protected static $instance; // container for self
	static $can_mb;				// does PHP support multibyte functions?
	static $can_utf;			// does PHP support utf8_encode/decode functions? It does by default.
	static $can_iconv;			// does PHP support iconv functions? It does by default.

	const westr_SPACECHARS = '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}';

	// What kind of class are you, anyway? One of a kind!
	private function __clone()
	{
		return false;
	}

	static function getInstance()
	{
		// Quero ergo sum
		if (self::$instance == null)
		{
			self::$instance = new self();
			self::$can_mb = is_callable('mb_internal_encoding');
			self::$can_utf = is_callable('utf8_encode');
			self::$can_iconv = is_callable('iconv');

			if (self::$can_mb)
				mb_internal_encoding('UTF-8');
			if (!is_callable('mb_strtolower'))
				loadSource('Subs-Charset');
		}

		return self::$instance;
	}

	static function is_utf8($string)
	{
		if (self::$can_mb)
			return !!mb_detect_encoding($string, 'UTF-8', true);
		return preg_match('`(?:[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})+`', $string);
	}

	static function to_utf8($string)
	{
		if (self::$can_utf)
			return utf8_encode($string);
		elseif (self::$can_mb) // Better than nothing...
			return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');
		elseif (self::$can_iconv)
			return iconv('ISO-8859-1', 'UTF-8//IGNORE', $string);
		return $string;
	}

	static function force_utf8($string)
	{
		if (!self::is_utf8($string))
			return self::to_utf8($string);
		return $string;
	}
}

if (!empty($settings['disableEntityCheck']))
{
	// No entity checking version
	// westr_ENT_ANY = Any character or entity
	// !!! What is &#021;...? Negative acknowledge?
	class westr_entity extends westr_foundation
	{
		const westr_ENT_ANY = '&#021;|&quot;|&amp;|&lt;|&gt;|&nbsp;|.';

		static function entity_fix($string)
		{
			return $string;
		}

		static function entity_clean($string)
		{
			return $string;
		}

		static function htmlspecialchars($string, $quote_style = ENT_COMPAT, $force_utf8 = false, $double_enc = true)
		{
			return htmlspecialchars($force_utf8 ? self::force_utf8($string) : $string, $quote_style, 'UTF-8', $double_enc);
		}
	}
}
else
{
	// Entity checking version
	class westr_entity extends westr_foundation
	{
		const westr_ENT_ANY = '&quot;|&amp;|&lt;|&gt;|&nbsp;|&#\d{1,7};|.';

		static function entity_fix($string)
		{
			$num = $string[1][0] === 'x' ? hexdec(substr($string[1], 1)) : (int) $string[1];
			return $num < 32 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num === 0x202D || $num === 0x202E ? '' : '&#' . $num . ';';
		}

		static function entity_clean($string)
		{
			return preg_replace_callback('~&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});~', 'westr_entity::entity_fix', $string);
		}

		static function htmlspecialchars($string, $quote_style = ENT_COMPAT, $force_utf8 = false, $double_enc = true)
		{
			return self::entity_clean(htmlspecialchars($force_utf8 ? self::force_utf8($string) : $string, $quote_style, 'UTF-8', $double_enc));
		}
	}
}

if (is_callable('mb_strtolower'))
{
	// With multibyte extension
	class westr_mb extends westr_entity
	{
		static function strtolower($string)
		{
			return mb_strtolower($string, 'UTF-8');
		}

		static function strtoupper($string)
		{
			return mb_strtoupper($string, 'UTF-8');
		}

		static function strlen($string)
		{
			return mb_strlen(preg_replace('~&(?:amp)?(?:#\d{1,7}|[a-zA-Z0-9]+);~', '_', $string));
		}
	}
}
else
{
	// Without mb - Subs-Charset should have been loaded at this point though
	class westr_mb extends westr_entity
	{
		static function strtolower($string)
		{
			return utf8_strtolower($string);
		}

		static function strtoupper($string)
		{
			return utf8_strtoupper($string);
		}

		static function strlen($string)
		{
			return strlen(preg_replace('~&(?:amp)?(?:#\d{1,7}|[a-zA-Z0-9]+);|.~us', '_', $string));
		}
	}
}

class westr extends westr_mb
{
	static function htmltrim($string)
	{
		return preg_replace('~^(?:[ \t\n\r\x0B\x00' . self::westr_SPACECHARS . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00' . self::westr_SPACECHARS . ']|&nbsp;)+$~u', '', self::entity_clean($string));
	}

	static function strpos($haystack, $needle, $offset = 0)
	{
		$haystack_arr = preg_split('~(' . self::westr_ENT_ANY . ')~u', self::entity_clean($haystack), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$haystack_size = count($haystack_arr);
		if (strlen($needle) === 1)
		{
			$result = array_search($needle, array_slice($haystack_arr, $offset));
			return ((int) $result) === $result ? $result + $offset : false;
		}
		else
		{
			$needle_arr = preg_split('~(' . self::westr_ENT_ANY . ')~u', self::entity_clean($needle), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$needle_size = count($needle_arr);
			$needle_arr[0] = isset($needle_arr[0]) ? $needle_arr[0] : '';

			$result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));
			while (((int) $result) === $result)
			{
				$offset += $result;
				if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr)
					return $offset;
				$result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
			}
			return false;
		}
	}

	static function substr($string, $start, $length = null)
	{
		$ent_arr = preg_split('~(' . self::westr_ENT_ANY . ')~u', self::entity_clean($string), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		return $length === null ? implode('', array_slice($ent_arr, $start)) : implode('', array_slice($ent_arr, $start, $length));
	}

	static function truncate($string, $length)
	{
		preg_match('~^(' . self::westr_ENT_ANY . '){' . self::strlen(substr($string, 0, $length)) . '}~u', self::entity_clean($string), $matches);
		$string = $matches[0];
		while (strlen($string) > $length)
			$string = preg_replace('~(?:' . self::westr_ENT_ANY . ')$~u', '', $string);
		return $string;
	}

	static function ucfirst($string)
	{
		return self::strtoupper(self::substr($string, 0, 1)) . self::substr($string, 1);
	}

	static function ucwords($string)
	{
		$words = preg_split('~([\s\r\n\t]+)~', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0, $n = count($words); $i < $n; $i += 2)
			$words[$i] = self::ucfirst($words[$i]);
		return implode('', $words);
	}

	static function nl2br($string)
	{
		return preg_replace('~(\r\n|\r|\n)~', '<br>$1', $string);
	}

	/**
	 * An alias for htmlspecialchars, preventing double encoding and forcing UTF-8 conversion by default, contrary to the original.
	 */
	static function safe($string, $quote_style = ENT_COMPAT, $force_utf8 = true, $double_enc = false)
	{
		return self::htmlspecialchars($string, $quote_style, $force_utf8, $double_enc);
	}

	/**
	 * Cuts a HTML string to requested length, taking entities and tags into account.
	 * You can say this is the ultimate string cutter. (Thank you very much.)
	 *
	 * $max_length is the max desired length in characters, $hard_limit in bytes (for database storage reasons)
	 * $cut_long_words will add a soft hyphen inside long words at position X, where X is either cut_long_words/2 or max_length/3
	 * $ellipsis will add a '...' sign at the end of any string that ends up being cut
	 * $preparse will run the string through parse_bbc before cutting it
	 */
	static function cut($string, $max_length = 255, $check_multibyte = true, $cut_long_words = true, $ellipsis = true, $preparse = false, $hard_limit = 0)
	{
		global $entities, $replace_counter, $context;
		static $test_mb = false, $strlen;

		if (empty($string))
			return $ellipsis ? '&hellip;' : '';

		if (!$check_multibyte)
			return rtrim(preg_replace('~&#?\w*$~', '', substr($string, 0, $max_length))) . ($ellipsis && strlen($string) > $max_length ? '&hellip;' : '');

		if ($preparse)
			$string = parse_bbc($string);

		$work = preg_replace('~(?:&[^&;]+;|<[^>]+>)~', chr(20), $string);

		if (!$test_mb)
			$strlen = self::$can_mb ? 'mb_strlen' : create_function('$str', 'return strlen(preg_replace(\'~.~us\', \'_\', $str));');

		$test_mb = true;

		if ($strlen($work) <= $max_length && (empty($hard_limit) || strlen($string) <= $hard_limit))
			return $string;

		preg_match_all("~(?:\x14|&[^&;]+;|<[^>]+>)~", $string, $entities);
		$len = $strlen($work);
		$work = rtrim(self::$can_mb ? mb_substr($work, 0, $max_length) : self::substr($work, 0, $max_length));

		if ($cut_long_words)
		{
			$cw = is_int($cut_long_words) ? round($cut_long_words / 2) + 1 : round($max_length / 3) + 1;
			$work = preg_replace('~(\w{' . $cw . '})(\w+)~u', '$1&shy;$2', $work);
		}

		$replace_counter = 0;
		$work = preg_replace_callback("~\x14~", 'westr::restore_entities', $work) . ($ellipsis && $len > $max_length ? '&hellip;' : '');

		// Make sure to close any opened tags after preparsing the string...
		if (strpos($work, '<') !== false)
			self::close_tags($work, $hard_limit);

		return $hard_limit && strlen($work) > $hard_limit ? rtrim(preg_replace('~&#?\w*$~', '', substr($work, 0, $hard_limit))) : $work;
	}

	// Recursively reattributes entities to strings
	private static function restore_entities($match)
	{
		global $entities, $replace_counter;
		return $entities[0][$replace_counter++];
	}

	// Closes all open tags, in recursive order, in order for pages not to be broken and to validate.
	static function close_tags(&$str, $hard_limit)
	{
		// These tags can safely be ignored, as they're self-closing.
		static $self_closed = '(?!area|base|br|col|command|embed|hr|img|input|keygen|link|meta|param|source|track|wbr)\b';

		// Could be made faster with substr_count(), but it wouldn't always validate.
		if (!preg_match_all('~<' . $self_closed . '([^/\s>]+)(?:>|[^>]*?[^/]>)~', $str, $m) || empty($m[1]))
			return;

		$mo = $m[1];
		preg_match_all('~</' . $self_closed . '([^>]+)~', $str, $m);
		$mc = $m[1];
		$ct = array();
		if (count($mo) > count($mc))
		{
			foreach ($mc as $tag)
				$ct[$tag] = isset($ct[$tag]) ? $ct[$tag] + 1 : 1;
			foreach (array_reverse($mo) as $tag)
			{
				if (empty($ct[$tag]) || !($ct[$tag]--))
				{
					// If we're not limited in size, close the tag, otherwise just give up and strip all tags.
					if (!$hard_limit || strlen($str . $tag) + 3 <= $hard_limit)
						$str .= '</' . $tag . '>';
					else
					{
						$str = strip_tags($str);
						return;
					}
				}
			}
		}
	}
}

?>