<?php

/**
 * Wedge
 *
 * json_encode() polyfill. Since Wedge doesn't support versions of PHP
 * that don't provide json_decode, this is only provided for users
 * whose host disabled the function for whatever reason.
 *
 * Note that this file is a trimmed down version of the original (linked below),
 * without comments or the equivalent decode() function.
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
 * LICENSE: Redistribution and use in source and binary forms, with or
 * without modification, are permitted provided that the following
 * conditions are met: Redistributions of source code must retain the
 * above copyright notice, this list of conditions and the following
 * disclaimer. Redistributions in binary form must reproduce the above
 * copyright notice, this list of conditions and the following disclaimer
 * in the documentation and/or other materials provided with the
 * distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN
 * NO EVENT SHALL CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @package Services_JSON
 * @author Michal Migurski <mike-json@teczno.com>
 * @author Matt Knapp <mdknapp[at]gmail[dot]com>
 * @author Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
 * @copyright 2005 Michal Migurski
 * @version 1.31 2006/06/28
 * @license http://www.opensource.org/licenses/bsd-license.php
 * @link http://pear.php.net/pepr/pepr-proposal-show.php?id=198
 */

class weJSON
{
	/**
	 * You may set weJSON::$suppress_errors to true.
	 * Values which can't be encoded (e.g. resources)
	 * appear as NULL instead of throwing errors.
	 * By default, a deeply-nested resource will
	 * bubble up with an error, so all return values
	 * from encode() should be checked with isError()
	 */
	static $suppress_errors = false;

	static function utf82utf16($utf8)
	{
		if (function_exists('mb_convert_encoding'))
			return mb_convert_encoding($utf8, 'UTF-16', 'UTF-8');

		switch (strlen($utf8))
		{
			case 1: return $utf8;
			case 2: return chr(0x07 & (ord($utf8{0}) >> 2)) . chr((0xC0 & (ord($utf8{0}) << 6)) | (0x3F & ord($utf8{1})));
			case 3: return chr((0xF0 & (ord($utf8{0}) << 4)) | (0x0F & (ord($utf8{1}) >> 2))) . chr((0xC0 & (ord($utf8{1}) << 6)) | (0x7F & ord($utf8{2})));
		}

		return '';
	}

	static function encode($var)
	{
		switch (gettype($var))
		{
			case 'boolean':
				return $var ? 'true' : 'false';

			case 'NULL':
				return 'null';

			case 'integer':
				return (int) $var;

			case 'double':
			case 'float':
				return (float) $var;

			// STRINGS ARE EXPECTED TO BE IN ASCII OR UTF-8 FORMAT.
			case 'string':
				$ascii = '';
				$strlen_var = strlen($var);

				for ($c = 0; $c < $strlen_var; ++$c)
				{
					$ord_var_c = ord($var{$c});

					switch (true)
					{
						case $ord_var_c == 0x08:
							$ascii .= '\b';
							break;
						case $ord_var_c == 0x09:
							$ascii .= '\t';
							break;
						case $ord_var_c == 0x0A:
							$ascii .= '\n';
							break;
						case $ord_var_c == 0x0C:
							$ascii .= '\f';
							break;
						case $ord_var_c == 0x0D:
							$ascii .= '\r';
							break;

						case $ord_var_c == 0x22:
						case $ord_var_c == 0x2F:
						case $ord_var_c == 0x5C:
							$ascii .= '\\' . $var{$c};
							break;

						case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
							$ascii .= $var{$c};
							break;

						case (($ord_var_c & 0xE0) == 0xC0):
							$char = pack('C*', $ord_var_c, ord($var{$c + 1}));
							$c += 1;
							$utf16 = self::utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xF0) == 0xE0):
							$char = pack('C*', $ord_var_c,
										 ord($var{$c + 1}),
										 ord($var{$c + 2}));
							$c += 2;
							$utf16 = self::utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xF8) == 0xF0):
							$char = pack('C*', $ord_var_c,
										 ord($var{$c + 1}),
										 ord($var{$c + 2}),
										 ord($var{$c + 3}));
							$c += 3;
							$utf16 = self::utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xFC) == 0xF8):
							$char = pack('C*', $ord_var_c, ord($var{$c + 1}), ord($var{$c + 2}), ord($var{$c + 3}), ord($var{$c + 4}));
							$c += 4;
							$utf16 = self::utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xFE) == 0xFC):
							$char = pack('C*', $ord_var_c, ord($var{$c + 1}), ord($var{$c + 2}), ord($var{$c + 3}), ord($var{$c + 4}), ord($var{$c + 5}));
							$c += 5;
							$utf16 = self::utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;
					}
				}

				return '"' . $ascii . '"';

			case 'array':
				if (is_array($var) && count($var) && (array_keys($var) !== range(0, sizeof($var) - 1)))
				{
					$properties = array_map('weJSON::name_value', array_keys($var), array_values($var));

					foreach ($properties as $property)
						if (self::isError($property))
							return $property;

					return '{' . join(',', $properties) . '}';
				}

				$elements = array_map('weJSON::encode', $var);

				foreach ($elements as $element)
					if (self::isError($element))
						return $element;

				return '[' . join(',', $elements) . ']';

			case 'object':
				$vars = get_object_vars($var);

				$properties = array_map('weJSON::name_value', array_keys($vars), array_values($vars));

				foreach ($properties as $property)
					if (self::isError($property))
						return $property;

				return '{' . join(',', $properties) . '}';

			default:
				return self::$suppress_errors ? 'null' : gettype($var) . ' can not be encoded as JSON string';
		}
	}

	static function name_value($name, $value)
	{
		$encoded_value = self::encode($value);

		if (self::isError($encoded_value))
			return $encoded_value;

		return self::encode(strval($name)) . ':' . $encoded_value;
	}

	static function isError($data, $code = null)
	{
		return is_object($data) && (get_class($data) == 'services_json_error' || is_subclass_of($data, 'services_json_error'));
	}
}
