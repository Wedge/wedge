<?php
/**
 * json_encode() polyfill. Since Wedge doesn't support versions of PHP
 * that don't provide json_decode, this is only provided for users
 * whose host disabled the function for whatever reason.
 *
 * Note that this file is a trimmed down version of the original (linked below).
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Converts to and from JSON format.
 *
 * All strings should be in ASCII or UTF-8 format!
 *
 * @package		Services_JSON
 * @author		Michal Migurski <mike-json@teczno.com>
 * @author		Matt Knapp <mdknapp[at]gmail[dot]com>
 * @author		Brett Stimmerman <brettstimmerman[at]gmail[dot]com>
 * @copyright	2005 Michal Migurski
 * @version		305040 2010-11-02 23:19:03Z alan_k $
 * @license		http://www.opensource.org/licenses/bsd-license.php
 * @link		http://pear.php.net/pepr/pepr-proposal-show.php?id=198
 */

define('SERVICES_JSON_SLICE', 1);
define('SERVICES_JSON_IN_STR', 2);
define('SERVICES_JSON_IN_ARR', 3);
define('SERVICES_JSON_IN_OBJ', 4);
define('SERVICES_JSON_IN_CMT', 5);
define('SERVICES_JSON_LOOSE_TYPE', 16);
define('SERVICES_JSON_USE_TO_JSON', 64);

weJSON::init();

if (!function_exists('json_encode'))
{
	function json_encode($str)
	{
		return weJSON::encode($str);
	}
}

if (!function_exists('json_decode'))
{
	function json_decode($str)
	{
		return weJSON::decode($str);
	}
}

/**
 * Converts to and from JSON format.
 *
 * Brief example of use:
 *
 * // convert a complexe value to JSON notation, and send it to the browser
 * $value = array('foo', 'bar', array(1, 2, 'baz'), array(3, array(4)));
 * $output = weJSON::encode($value);
 *
 * print($output);
 * // prints: ["foo","bar",[1,2,"baz"],[3,[4]]]
 *
 * // accept incoming POST data, assumed to be in JSON notation
 * $input = file_get_contents('php://input', 1000000);
 * $value = weJSON::decode($input);
 */
class weJSON
{
   /**
	* constructs a new JSON instance
	*
	* @param	int $use	object behavior flags; combine with boolean-OR
	*
	*						possible values:
	*						   - SERVICES_JSON_LOOSE_TYPE: loose typing.
	*								"{...}" syntax creates associative arrays
	*								instead of objects in decode().
	*						   - SERVICES_JSON_USE_TO_JSON: call toJSON when serializing objects.
	*								It serializes the return value from the toJSON call rather
	*								than the object itself, toJSON can return associative arrays,
	*								strings or numbers, if you return an object, make sure it does
	*								not have a toJSON method.
	*/

	static $use = 0, $_mb_strlen = false, $_mb_substr = false, $_mb_convert_encoding = false;

	static function init($use = 0)
	{
		self::$use = $use;
		self::$_mb_strlen			= function_exists('mb_strlen');
		self::$_mb_substr			= function_exists('mb_substr');
		self::$_mb_convert_encoding	= function_exists('mb_convert_encoding');
	}

	static function utf162utf8($utf16)
	{
		if (self::$_mb_convert_encoding)
			return mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');

		$bytes = (ord($utf16{0}) << 8) | ord($utf16{1});

		switch (true) {
			case ((0x7F & $bytes) == $bytes):
				return chr(0x7F & $bytes);

			case (0x07FF & $bytes) == $bytes:
				return chr(0xC0 | (($bytes >> 6) & 0x1F)) . chr(0x80 | ($bytes & 0x3F));

			case (0xFFFF & $bytes) == $bytes:
				return chr(0xE0 | (($bytes >> 12) & 0x0F)) . chr(0x80 | (($bytes >> 6) & 0x3F)) . chr(0x80 | ($bytes & 0x3F));
		}

		return '';
	}

	static function utf82utf16($utf8)
	{
		if (self::$_mb_convert_encoding)
			return mb_convert_encoding($utf8, 'UTF-16', 'UTF-8');

		switch (self::strlen8($utf8)) {
			case 1:
				return $utf8;

			case 2:
				return chr(0x07 & (ord($utf8{0}) >> 2)) . chr((0xC0 & (ord($utf8{0}) << 6)) | (0x3F & ord($utf8{1})));

			case 3:
				return chr((0xF0 & (ord($utf8{0}) << 4)) | (0x0F & (ord($utf8{1}) >> 2))) . chr((0xC0 & (ord($utf8{1}) << 6)) | (0x7F & ord($utf8{2})));
		}

		return '';
	}

	static function encode($var)
	{
		$lc = setlocale(LC_NUMERIC, 0);
		setlocale(LC_NUMERIC, 'C');
		$ret = self::_encode($var);
		setlocale(LC_NUMERIC, $lc);
		return $ret;
	}

	private static function _encode($var)
	{
		switch (gettype($var)) {
			case 'boolean':
				return $var ? 'true' : 'false';

			case 'NULL':
				return 'null';

			case 'integer':
				return (int) $var;

			case 'double':
			case 'float':
				return (float) $var;

			case 'string':
				$ascii = '';
				$strlen_var = self::strlen8($var);

				for ($c = 0; $c < $strlen_var; ++$c) {

					$ord_var_c = ord($var{$c});

					switch (true) {
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
							if ($c+1 >= $strlen_var) {
								$c += 1;
								$ascii .= '?';
								break;
							}

							$char = pack('C*', $ord_var_c, ord($var{$c + 1}));
							$c += 1;
							$utf16 = self::utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xF0) == 0xE0):
							if ($c+2 >= $strlen_var) {
								$c += 2;
								$ascii .= '?';
								break;
							}
							$char = pack('C*', $ord_var_c, @ord($var{$c + 1}), @ord($var{$c + 2}));
							$c += 2;
							$utf16 = self::utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xF8) == 0xF0):
							if ($c+3 >= $strlen_var) {
								$c += 3;
								$ascii .= '?';
								break;
							}
							$char = pack('C*', $ord_var_c, ord($var{$c + 1}), ord($var{$c + 2}), ord($var{$c + 3}));
							$c += 3;
							$utf16 = self::utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xFC) == 0xF8):
							if ($c+4 >= $strlen_var) {
								$c += 4;
								$ascii .= '?';
								break;
							}
							$char = pack('C*', $ord_var_c, ord($var{$c + 1}), ord($var{$c + 2}), ord($var{$c + 3}), ord($var{$c + 4}));
							$c += 4;
							$utf16 = self::utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;

						case (($ord_var_c & 0xFE) == 0xFC):
						if ($c+5 >= $strlen_var) {
								$c += 5;
								$ascii .= '?';
								break;
							}
							$char = pack('C*', $ord_var_c, ord($var{$c + 1}), ord($var{$c + 2}), ord($var{$c + 3}), ord($var{$c + 4}), ord($var{$c + 5}));
							$c += 5;
							$utf16 = self::utf82utf16($char);
							$ascii .= sprintf('\u%04s', bin2hex($utf16));
							break;
					}
				}
				return '"' . $ascii . '"';

			case 'array':

				if (is_array($var) && count($var) && (array_keys($var) !== range(0, sizeof($var) - 1))) {
					$properties = array_map('weJSON::name_value', array_keys($var), array_values($var));

					return '{' . join(',', $properties) . '}';
				}

				$elements = array_map('weJSON::_encode', $var);

				return '[' . join(',', $elements) . ']';

			case 'object':

				if ((self::$use & SERVICES_JSON_USE_TO_JSON) && method_exists($var, 'toJSON')) {
					$recode = $var->toJSON();

					if (method_exists($recode, 'toJSON'))
						return 'null';

					return self::_encode( $recode );
				}

				$vars = get_object_vars($var);
				$properties = array_map('weJSON::name_value', array_keys($vars), array_values($vars));

				return '{' . join(',', $properties) . '}';

			default:
				return 'null';
		}
	}

	private static function name_value($name, $value)
	{
		return self::_encode(strval($name)) . ':' . self::_encode($value);
	}

	private static function reduce_string($str)
	{
		return trim(preg_replace(array('#^\s*//(.+)$#m', '#^\s*/\*(.+)\*/#Us', '#/\*(.+)\*/\s*$#Us'), '', $str));
	}

	static function decode($str)
	{
		$str = self::reduce_string($str);

		switch (strtolower($str)) {
			case 'true':
				return true;

			case 'false':
				return false;

			case 'null':
				return null;

			default:
				$m = array();

				if (is_numeric($str))
					return ((float) $str == (integer) $str) ? (integer) $str : (float) $str;

				if (preg_match('/^("|\').*(\1)$/s', $str, $m) && $m[1] == $m[2]) {
					$delim = self::substr8($str, 0, 1);
					$chrs = self::substr8($str, 1, -1);
					$utf8 = '';
					$strlen_chrs = self::strlen8($chrs);

					for ($c = 0; $c < $strlen_chrs; ++$c) {

						$substr_chrs_c_2 = self::substr8($chrs, $c, 2);
						$ord_chrs_c = ord($chrs{$c});

						switch (true) {
							case $substr_chrs_c_2 == '\b':
								$utf8 .= chr(0x08);
								++$c;
								break;
							case $substr_chrs_c_2 == '\t':
								$utf8 .= chr(0x09);
								++$c;
								break;
							case $substr_chrs_c_2 == '\n':
								$utf8 .= chr(0x0A);
								++$c;
								break;
							case $substr_chrs_c_2 == '\f':
								$utf8 .= chr(0x0C);
								++$c;
								break;
							case $substr_chrs_c_2 == '\r':
								$utf8 .= chr(0x0D);
								++$c;
								break;

							case $substr_chrs_c_2 == '\\"':
							case $substr_chrs_c_2 == '\\\'':
							case $substr_chrs_c_2 == '\\\\':
							case $substr_chrs_c_2 == '\\/':
								if (($delim == '"' && $substr_chrs_c_2 != '\\\'') || ($delim == "'" && $substr_chrs_c_2 != '\\"'))
									$utf8 .= $chrs{++$c};
								break;

							case preg_match('/\\\u[0-9A-F]{4}/i', self::substr8($chrs, $c, 6)):
								// single, escaped unicode character
								$utf16 = chr(hexdec(self::substr8($chrs, ($c + 2), 2))) . chr(hexdec(self::substr8($chrs, ($c + 4), 2)));
								$utf8 .= self::utf162utf8($utf16);
								$c += 5;
								break;

							case ($ord_chrs_c >= 0x20) && ($ord_chrs_c <= 0x7F):
								$utf8 .= $chrs{$c};
								break;

							case ($ord_chrs_c & 0xE0) == 0xC0:
								$utf8 .= self::substr8($chrs, $c, 2);
								++$c;
								break;

							case ($ord_chrs_c & 0xF0) == 0xE0:
								$utf8 .= self::substr8($chrs, $c, 3);
								$c += 2;
								break;

							case ($ord_chrs_c & 0xF8) == 0xF0:
								$utf8 .= self::substr8($chrs, $c, 4);
								$c += 3;
								break;

							case ($ord_chrs_c & 0xFC) == 0xF8:
								$utf8 .= self::substr8($chrs, $c, 5);
								$c += 4;
								break;

							case ($ord_chrs_c & 0xFE) == 0xFC:
								$utf8 .= self::substr8($chrs, $c, 6);
								$c += 5;
								break;

						}
					}

					return $utf8;

				} elseif (preg_match('/^\[.*\]$/s', $str) || preg_match('/^\{.*\}$/s', $str)) {

					if ($str{0} == '[') {
						$stk = array(SERVICES_JSON_IN_ARR);
						$arr = array();
					} else {
						if (self::$use & SERVICES_JSON_LOOSE_TYPE) {
							$stk = array(SERVICES_JSON_IN_OBJ);
							$obj = array();
						} else {
							$stk = array(SERVICES_JSON_IN_OBJ);
							$obj = new stdClass();
						}
					}

					array_push($stk, array('what' => SERVICES_JSON_SLICE, 'where' => 0, 'delim' => false));

					$chrs = self::substr8($str, 1, -1);
					$chrs = self::reduce_string($chrs);

					if ($chrs == '') {
						if (reset($stk) == SERVICES_JSON_IN_ARR) {
							return $arr;

						} else {
							return $obj;

						}
					}

					$strlen_chrs = self::strlen8($chrs);

					for ($c = 0; $c <= $strlen_chrs; ++$c) {

						$top = end($stk);
						$substr_chrs_c_2 = self::substr8($chrs, $c, 2);

						if (($c == $strlen_chrs) || (($chrs{$c} == ',') && ($top['what'] == SERVICES_JSON_SLICE))) {
							$slice = self::substr8($chrs, $top['where'], ($c - $top['where']));
							array_push($stk, array('what' => SERVICES_JSON_SLICE, 'where' => ($c + 1), 'delim' => false));

							if (reset($stk) == SERVICES_JSON_IN_ARR) {
								array_push($arr, self::decode($slice));

							} elseif (reset($stk) == SERVICES_JSON_IN_OBJ) {
								$parts = array();

								if (preg_match('/^\s*(["\'].*[^\\\]["\'])\s*:/Uis', $slice, $parts)) {
									$key = self::decode($parts[1]);
									$val = self::decode(trim(substr($slice, strlen($parts[0])), ", \t\n\r\0\x0B"));
									if (self::$use & SERVICES_JSON_LOOSE_TYPE) {
										$obj[$key] = $val;
									} else {
										$obj->$key = $val;
									}
								} elseif (preg_match('/^\s*(\w+)\s*:/Uis', $slice, $parts)) {
									$key = $parts[1];
									$val = self::decode(trim(substr($slice, strlen($parts[0])), ", \t\n\r\0\x0B"));

									if (self::$use & SERVICES_JSON_LOOSE_TYPE) {
										$obj[$key] = $val;
									} else {
										$obj->$key = $val;
									}
								}

							}

						} elseif ((($chrs{$c} == '"') || ($chrs{$c} == "'")) && ($top['what'] != SERVICES_JSON_IN_STR)) {
							array_push($stk, array('what' => SERVICES_JSON_IN_STR, 'where' => $c, 'delim' => $chrs{$c}));

						} elseif (($chrs{$c} == $top['delim']) &&
								 ($top['what'] == SERVICES_JSON_IN_STR) &&
								 ((self::strlen8(self::substr8($chrs, 0, $c)) - self::strlen8(rtrim(self::substr8($chrs, 0, $c), '\\'))) % 2 != 1)) {
							array_pop($stk);

						} elseif (($chrs{$c} == '[') && in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
							array_push($stk, array('what' => SERVICES_JSON_IN_ARR, 'where' => $c, 'delim' => false));

						} elseif (($chrs{$c} == ']') && ($top['what'] == SERVICES_JSON_IN_ARR)) {
							array_pop($stk);

						} elseif (($chrs{$c} == '{') && in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
							array_push($stk, array('what' => SERVICES_JSON_IN_OBJ, 'where' => $c, 'delim' => false));

						} elseif (($chrs{$c} == '}') && ($top['what'] == SERVICES_JSON_IN_OBJ)) {
							array_pop($stk);

						} elseif (($substr_chrs_c_2 == '/*') && in_array($top['what'], array(SERVICES_JSON_SLICE, SERVICES_JSON_IN_ARR, SERVICES_JSON_IN_OBJ))) {
							array_push($stk, array('what' => SERVICES_JSON_IN_CMT, 'where' => $c, 'delim' => false));
							$c++;

						} elseif (($substr_chrs_c_2 == '*/') && ($top['what'] == SERVICES_JSON_IN_CMT)) {
							array_pop($stk);
							$c++;

							for ($i = $top['where']; $i <= $c; ++$i)
								$chrs = substr_replace($chrs, ' ', $i, 1);
						}

					}

					if (reset($stk) == SERVICES_JSON_IN_ARR)
						return $arr;

					if (reset($stk) == SERVICES_JSON_IN_OBJ)
						return $obj;
				}
		}
	}

	private static function strlen8($str)
	{
		if (self::$_mb_strlen)
			return mb_strlen($str, '8bit');

		return strlen($str);
	}

	private static function substr8($string, $start, $length = false)
	{
		if ($length === false)
			$length = self::strlen8($string) - $start;

		if (self::$_mb_substr)
			return mb_substr($string, $start, $length, '8bit');

		return substr($string, $start, $length);
	}
}
