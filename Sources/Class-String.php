<?php
/**********************************************************************************
* Class-String.php                                                                *
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

/**
 * This file handles string processing. Please note that this is what we feel to be the sanest method of providing everything, but it's really not pretty.
 *
 * @package wedge
 */

global $modSettings;

class westr_foundation
{
	protected static $instance; // container for self
	protected static $ent_list, $ent_check; // internals for checking entities

	const westr_SPACECHARS = '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}';

	// What kind of class are you, anyway? One of a kind!
	private function __clone()
	{
		return false;
	}

	public static function getInstance()
	{
		global $modSettings;

		// Quero ergo sum
		if (self::$instance == null)
		{
			self::$instance = new self();

			if (!is_callable('mb_strtolower'))
				loadSource('Subs-Charset');
		}

		return self::$instance;
	}
}

if (!empty($modSettings['disableEntityCheck']))
{
	// no entity checking version
	class westr_entity extends westr_foundation
	{
		const westr_STRPOS_ENT = '021';
		const westr_ENTLIST = '&(#021|quot|amp|lt|gt|nbsp);';

		public static function entity_fix($string)
		{
			return $string;
		}

		public static function entity_clean($string)
		{
			return $string;
		}

		public static function htmlspecialchars($string, $quote_style = ENT_COMPAT)
		{
			return htmlspecialchars($string, $quote_style, 'UTF-8');
		}
	}
}
else
{
	// entity checking version
	class westr_entity extends westr_foundation
	{
		const westr_STRPOS_ENT = '\d{1,7}';
		const westr_ENTLIST = '&(#\d{1,7}|quot|amp|lt|gt|nbsp);';

		public static function entity_fix($string)
		{
			$num = substr($string, 0, 1) === 'x' ? hexdec(substr($string, 1)) : (int) $string;
			return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num == 0x202E ? '' : '&#' . $num . ';';
		}

		public static function entity_clean($string)
		{
			return preg_replace('~(&amp;#(\d{1,7}|x[0-9a-fA-F]{1,6});)~e', 'westr::entity_fix(\\\'\\2\\\')', $string);
		}

		public static function htmlspecialchars($string, $quote_style = ENT_COMPAT)
		{
			return self::entity_clean(htmlspecialchars($string, $quote_style, 'UTF-8'));
		}
	}
}

class westr_base extends westr_entity
{
	public static function htmltrim($string)
	{
		return preg_replace('~^(?:[ \t\n\r\x0B\x00' . self::westr_SPACECHARS . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00' . self::westr_SPACECHARS . ']|&nbsp;)+$~u', '', self::entity_clean($string));
	}

	public static function strlen($string)
	{
		return strlen(preg_replace('~' . self::westr_STRPOS_ENT . '|.~u', '_', self::entity_clean($string)));
	}

	public static function strpos($haystack, $needle, $offset = 0)
	{
		$haystack_arr = preg_split('~(&' . self::westr_STRPOS_ENT . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', self::entity_clean($haystack), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$haystack_size = count($haystack_arr);
		if (strlen($needle) === 1)
		{
			$result = array_search($needle, array_slice($haystack_arr, $offset));
			return is_int($result) ? $result + $offset : false;
		}
		else
		{
			$needle_arr = preg_split('~(&#' . self::westr_STRPOS_ENT . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', self::entity_clean($needle), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$needle_size = count($needle_arr);

			$result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));
			while (is_int($result))
			{
				$offset += $result;
				if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr)
					return $offset;
				$result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
			}
			return false;
		}
	}

	public static function substr($string, $start, $length = null)
	{
		$ent_arr = preg_split('~(&#' . self::westr_STRPOS_ENT . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~u', self::entity_clean($string), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		return $length === null ? implode('', array_slice($ent_arr, $start)) : implode('', array_slice($ent_arr, $start, $length));
	}

	public static function truncate($string)
	{
		preg_match('~^(' . self::westr_ENTLIST . '|.){' . self::strlen(substr($string, 0, $length)) . '}~u', self::entity_clean($string), $matches);
		$string = $matches[0];
		while (strlen($string) > $length)
			$string = preg_replace('~(?:' . self::westr_ENTLIST . '|.)$~u', '', $string);
		return $string;
	}

	public static function ucfirst($string)
	{
		return westr::strtoupper(self::substr($string, 0, 1)) . self::substr($string, 1);
	}

	public static function ucwords($string)
	{
		$words = preg_split('~([\s\r\n\t]+)~', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0, $n = count($words); $i < $n; $i += 2)
			$words[$i] = self::ucfirst($words[$i]);
		return implode('', $words);
	}
}

if (is_callable('mb_strtolower'))
{
	// with multibyte extension
	class westr extends westr_base
	{
		public static function strtolower($string)
		{
			return mb_strtolower($string, 'UTF-8');
		}

		public static function strtoupper($string)
		{
			return mb_strtoupper($string, 'UTF-8');
		}
	}
}
else
{
	// without mb - Subs-Charset should have been loaded at this point though
	class westr extends westr_base
	{
		public static function strtolower($string)
		{
			return utf8_strtolower($string);
		}

		public static function strtoupper($string)
		{
			return utf8_strtoupper($string);
		}
	}
}
?>