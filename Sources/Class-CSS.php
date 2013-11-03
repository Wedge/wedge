<?php
/**
 * Wess (WEdge pre-proceSS) is a pre-parser for CSS files, bringing new possibilities to CSS.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

/**
 * Uses some code and ideas from Shaun Inman's CSS Cacheer library
 * http://www.shauninman.com/archive/2008/05/30/check_out_css_cacheer
 * Also implements some ideas from Sass (http://sass-lang.com)
 */

class wess
{
	/**
	 * The HSL to RGB and RGB to HSL functions are based on ABC algorithms from the CWI,
	 * based on 'Fundamentals of Interactive Computer Graphics' (J.D. Foley, 1982.)
	 */

	protected static function rgb2hex($r, $g, $b)
	{
		$hex = sprintf('%02x%02x%02x', $r, $g, $b);
		if (preg_match('~^([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3?$~i', $hex, $m))
			return '#' . $m[1] . $m[2] . $m[3];
		return '#' . $hex;
	}

	// Converts from a RGBA color to a string
	protected static function color2string($r, $g, $b, $a)
	{
		$a = max(0, min(1, $a));
		$r = max(0, min(255, round($r)));
		$g = max(0, min(255, round($g)));
		$b = max(0, min(255, round($b)));

		return $a === 1 ? wess::rgb2hex($r, $g, $b) : "rgba($r, $g, $b, $a)";
	}

	// Converts from hue to RGB
	protected static function hue2rgb($m1, $m2, $h)
	{
		$h < 0 ? $h++ : ($h > 1 ? $h-- : '');

		if ($h * 6 < 1)
			return $m2 + ($m1 - $m2) * $h * 6;
		if ($h * 2 < 1)
			return $m1;
		if ($h * 3 < 2)
			return $m2 + ($m1 - $m2) * (2 / 3 - $h) * 6;
		return $m2;
	}

	/**
	 * Converts from HSL to RGB
	 * Algorithm from the CSS3 spec: http://www.w3.org/TR/css3-color/
	 * $h(ue) is in degrees, $s(aturation) and $l(ightness) are in percents
	 */
	protected static function hsl2rgb($h, $s, $l, $a)
	{
		while ($h < 0)
			$h += 360;
		$h = fmod($h, 360) / 360;
		$s = max(0, min(1, $s / 100));
		$l = max(0, min(1, $l / 100));

		$m1 = $l <= 0.5 ? $l * ($s + 1) : $l + $s - $l * $s;
		$m2 = $l * 2 - $m1;

		return array(
			'r' => wess::hue2rgb($m1, $m2, $h + 1 / 3) * 255,
			'g' => wess::hue2rgb($m1, $m2, $h) * 255,
			'b' => wess::hue2rgb($m1, $m2, $h - 1 / 3) * 255,
			'a' => $a
		);
	}

	/**
	 * Converts from RGB to HSL
	 * $r/$g/$b are RGB values (0-255)
	 */
	protected static function rgb2hsl($r, $g, $b, $a)
	{
		$r /= 255;
		$g /= 255;
		$b /= 255;
		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$c = $max - $min;
		$l = ($max + $min) / 2;

		if ($max === $min)
			return array('h' => 0, 's' => 0, 'l' => $l * 100, 'a' => $a);

		if ($max === $r)
		{
			$h = ($g - $b) / $c;
			while ($h < 0)
				$h += 6;
			$h = fmod($h, 6);
		}
		elseif ($max === $g)
			$h = (($b - $r) / $c) + 2;
		else
			$h = (($r - $g) / $c) + 4;

		return array(
			'h' => $h * 60,
			's' => $c / ($l <= 0.5 ? $l + $l : 2 - $l - $l) * 100,
			'l' => $l * 100,
			'a' => $a
		);
	}

	// Converts from a string to a RGBA or HSLA color
	protected static function string2color($data)
	{
		// We'll only support the standard (non-CSS3) color names. Who uses 'PapayaWhip' anyway?
		static $colors = array(
			'aqua'		=> '00ffff', 'black'	=> '000000', 'blue'		=> '0000ff',
			'fuchsia'	=> 'ff00ff', 'gray'		=> '808080', 'green'	=> '008000',
			'grey'		=> '808080', 'lime'		=> '00ff00', 'maroon'	=> '800000',
			'navy'		=> '000080', 'olive'	=> '808000', 'purple'	=> '800080',
			'red'		=> 'ff0000', 'silver'	=> 'c0c0c0', 'teal'		=> '008080',
			'white'		=> 'ffffff', 'yellow'	=> 'ffff00'
		);

		if (!function_exists('to_max'))
		{
			function to_max($d, $max = 255)
			{
				return substr($d, -1) === '%' ? (int) substr($d, 0, -1) / 100 * $max : $d;
			}
		}

		// Extract color data
		preg_match('~(?:(rgb|hsl)a?\(\h*(\d+%?)\h*,\h*(\d+%?)\h*,\h*(\d+%?)(?:\h*\,\h*(\d*(?:\.\d+)?%?))?\h*\)|#([0-9a-f]{6}|[0-9a-f]{3}))~', $data, $rgb);

		$color = $hsl = 0;
		if (empty($rgb[0]))
		{
			$data = explode(',', $data);
			$rgb[0] = $data[0];
			$data = trim($data[0]);
			if (!isset($colors[$data]))
				return false;
			$color = array(hexdec(substr($colors[$data], 0, 2)), hexdec(substr($colors[$data], 2, 2)), hexdec(substr($colors[$data], -2)), 1);
		}
		elseif ($rgb[2] !== '' && $rgb[1] === 'rgb')
			$color = array(to_max($rgb[2]), to_max($rgb[3]), to_max($rgb[4]), !isset($rgb[5]) || $rgb[5] === '' ? 1 : to_max((float) $rgb[5], 1));
		elseif ($rgb[2] !== '')
			$hsl = array('h' => to_max($rgb[2], 360), 's' => to_max($rgb[3], 100), 'l' => to_max($rgb[4], 100), 'a' => $rgb[5] === '' ? 1 : to_max((float) $rgb[5], 1));
		elseif ($rgb[6] !== '' && isset($rgb[6][3]))
			$color = array(hexdec(substr($rgb[6], 0, 2)), hexdec(substr($rgb[6], 2, 2)), hexdec(substr($rgb[6], -2)), 1);
		elseif ($rgb[6] !== '')
			$color = array(hexdec($rgb[6][0] . $rgb[6][0]), hexdec($rgb[6][1] . $rgb[6][1]), hexdec($rgb[6][2] . $rgb[6][2]), 1);
		else
			$color = array(255, 255, 255, 1);

		return array($rgb[0], $color, $hsl);
	}

	function process(&$css) {}
}

class wess_mixin extends wess
{
	var $default = array();
	var $target;

	private function def($a)
	{
		return $this->default[$this->target][(int) $a[1]];
	}

	function process(&$css)
	{
		$mix = array();

		// Find mixin declarations, capture their tab level and stop at the first empty or unindented line.
		if (preg_match_all('~@mixin\h+(?:{([^}]+)}\h*)?([\w.-]+)(?:\(([^()]+)\))?[^\v]*\v+(\h+)([^\v]*\n+)((?:\4\h*[^\v]*\v+)*)~i', $css, $mixins, PREG_SET_ORDER))
		{
			// We start by building an array of mixins...
			foreach ($mixins as $mixin)
			{
				// Remove the mixin declaration
				$css = str_replace($mixin[0], '', $css);

				if (!empty($mixin[1]) && !we::is(we::$user['extra_tests'][] = strtolower($mixin[1])))
					continue;

				// Create our mixin entry...
				$mix[$mixin[2]] = rtrim(str_replace("\n" . $mixin[4], "\n", $mixin[5] . $mixin[6]));

				// Do we have variables to set?
				if (!empty($mixin[3]) && preg_match_all('~(\$[\w-]+)\h*(?:[:=]\h*"?([^",]+))?~', $mixin[3], $variables, PREG_SET_ORDER))
				{
					foreach ($variables as $i => $var)
					{
						$mix[$mixin[2]] = str_replace($var[1], '$%' . $i . '%', $mix[$mixin[2]]);
						$def[$mixin[2]][$i] = isset($var[2]) ? trim($var[2], '" ') : '';
					}
				}
			}
		}

		// ...And then we apply them to the CSS file.
		// We'll repeat the process as long as there are mixin calls left, allowing for nested mixins.
		for ($loop = 0; $loop < 10; $loop++)
		{
			$repa = array();
			$selector_regex = '([abipqsu]|[!+>&#*@:.a-z0-9][^{};,\n"()]+)';

			// Search for 'mixin: .otherclass' rules.
			if (preg_match_all('~(?<=\n)(\h*)mixin\h*:\h*' . $selector_regex . '\h*(?:\(([^\n]+)\))?~i', $css, $targets, PREG_SET_ORDER))
			{
				foreach ($targets as $mixin)
				{
					$rep = '';
					$tg = $mixin[2];
					if (isset($mix[$tg]))
					{
						$rep = $mix[$tg];
						if (!empty($mixin[3]))
						{
							$variables = explode(',', $mixin[3]);
							$i = 0;
							foreach ($variables as $i => $var)
								if (!empty($var))
									$rep = str_replace('$%' . $i . '%', trim($var, '" '), $rep);
						}

						// Replace all missing variables with their default value.
						if (!empty($def[$tg]))
						{
							$this->default = $def;
							$this->target = $tg;
							$rep = preg_replace_callback('~\$%(\d+)%~', array($this, 'def'), $rep);
						}
					}
					// Or is this a simple non-mixin selector we want to mix with? (Child selectors aren't allowed for these.)
					elseif (preg_match_all('~(?<=\n)' . preg_quote($tg, '~') . '\h*(?:[a-zA-Z]+\h*)?\v+(\h+)([^\v]*\v+)((?:\1[^\v]*\v+)*)~', $css, $selectors, PREG_SET_ORDER))
						foreach ($selectors as $sel)
							$rep .= "\n" . rtrim(str_replace("\n" . $sel[1], "\n", $sel[2] . $sel[3]));

					$repa[$mixin[0]] = $mixin[1] . str_replace("\n", "\n" . $mixin[1], $rep);
				}
			}

			// ...We should also do '.class mixes .otherclass' here.
			if (preg_match_all('~(?<=\n)(\h*)(.*?)\h+mixes\h*' . $selector_regex . '(?:\(([^\n]+)\))?~i', $css, $targets, PREG_SET_ORDER))
			{
				foreach ($targets as $mixin)
				{
					$rep = '';
					$tg = trim($mixin[3]);
					if (isset($mix[$tg]))
					{
						$rep = $mix[$tg];
						if (!empty($mixin[4]))
						{
							$variables = explode(',', $mixin[4]);
							$i = 0;
							foreach ($variables as $i => $var)
								if (!empty($var))
									$rep = str_replace('$%' . $i . '%', trim($var, '" '), $rep);
						}

						// Replace all missing variables with their default value.
						if (!empty($def[$tg]))
						{
							$this->default = $def;
							$this->target = $tg;
							$rep = preg_replace_callback('~\$%(\d+)%~', array($this, 'def'), $rep);
						}
					}
					// Or is this a simple non-mixin selector we want to mix with? (Child selectors aren't allowed for these.)
					elseif (preg_match_all('~(?<=\n)' . preg_quote($tg, '~') . '\h*(?:[a-zA-Z]+\h*)?\v+(\h+)([^\v]*\v+)((?:\1[^\v]*\v+)*)~', $css, $selectors, PREG_SET_ORDER))
						foreach ($selectors as $sel)
							$rep .= "\n" . rtrim(str_replace("\n" . $sel[1], "\n", $sel[2] . $sel[3]));

					$newline = "\n" . $mixin[1] . (isset($mixin[1][0]) ? $mixin[1][0] : "\t");
					$repa[$mixin[0]] = $mixin[1] . $mixin[2] . $newline . str_replace("\n", $newline, $rep);
				}
			}

			if (!empty($repa))
			{
				// Sort the array by key length, to avoid conflicts.
				$keys = array_map('strlen', array_keys($repa));
				array_multisort($keys, SORT_DESC, $repa);

				$css = str_replace(array_keys($repa), array_values($repa), $css);
			}
			else
				break;
		}
	}
}

/**
 * Find '@dynamic my_function', then execute dynamic_my_function() to replace them.
 * You may add parameters to the function call: @dynamic my_function(string, number)
 * Just don't use quotes around the parameters, they're all returned as trimmed strings.
 * Obviously, don't use a comma or spaces as a parameter either. Why would you do that anyway?
 */
class wess_dynamic extends wess
{
	function process(&$css)
	{
		static $done = array();

		if (preg_match_all('~@dynamic\h+([a-z0-9_]+)(?:\h+\([^)]*\))?~i', $css, $functions, PREG_SET_ORDER))
		{
			foreach ($functions as $func)
			{
				$callback = 'dynamic_' . $func[1];
				if (is_callable($callback))
				{
					if (isset($done[$func[0]]))
						continue;
					$data = isset($func[2]) ? call_user_func_array($callback, array_map('trim', explode(',', $func[2]))) : $callback();
					$css = preg_replace('~' . preg_quote($func[0], '~') . '~i', $data, $css);
					$done[$func[0]] = true;
				}
			}
		}
	}
}

// Dynamic CSS constants.
class wess_var extends wess
{
	// Sort arrays by their content length. The trim() is 10 times slower but needed. It's still super-fast.
	private static function lensort($a, $b)
	{
		return strlen(trim($a)) < strlen(trim($b));
	}

	private static function develop_var($k, $limit = 0)
	{
		global $css_vars;

		if (strpos($css_vars[$k], '$') !== false)
			foreach ($css_vars as $key => $val)
				$css_vars[$k] = str_replace($key, $val, $css_vars[$k]);

		if (strpos($css_vars[$k], '$') !== false && $limit < 8)
			wess_var::develop_var($k, ++$limit);
	}

	function process(&$css)
	{
		global $css_vars, $alphamix;

		// Reuse CSS variables from Wedge.
		$css_vars = isset($css_vars) ? $css_vars : array();

		/*
			Double quotes are only required for empty strings.
			Authors can specify conditions for the variable to be set,
			depending on the browser, rtl, guest or member, i.e. anything
			that can be queried with we::is(). Like this:

				$variable = "rgba(2,4,6,.5)";
				$variable {ie6,ie7,ie8} = rgb(1,2,3);

			The only reason we're not accepting ":" in declarations is that
			we want to be able to do this: (Check the last line carefully)

			(common.css)	$left = @is (rtl, right, left)
			(anything.css)	.class
								border-$left: 1px solid $border-col
		*/
		if (preg_match_all('~^\h*(\$[\w-]+)\h*(?:{([^}]+)}\h*)?=\h*("?)(.*)\\3;?\s*$~m', $css, $matches))
		{
			// Sort the matches by key length, to avoid conflicts as much as possible.
			$decs = $matches[0];
			usort($decs, 'wess_var::lensort');

			// Erase all traces of variable definitions.
			$css = str_replace($decs, '', $css);
			unset($decs);

			foreach ($matches[0] as $i => $dec)
				if (empty($matches[2][$i]) || we::is(we::$user['extra_tests'][] = strtolower($matches[2][$i])))
					$css_vars[$matches[1][$i]] = rtrim($matches[4][$i], '; ');

			// Same as above, but for the actual variables.
			$keys = array_map('strlen', array_keys($css_vars));
			array_multisort($keys, SORT_DESC, $css_vars);

			foreach ($css_vars as $key => $val)
				wess_var::develop_var($key);

			// We need to keep this one for later...
			if (isset($css_vars['$alphamix']))
				$alphamix = trim($css_vars['$alphamix'], '"');
		}

		// Replace @ifnull($var1, $var2) with $var1, or $var2 if $var1 doesn't exist.
		while (preg_match_all('~@ifnull\h*\((\$[\w-]+)\s*,\s*+(?!@ifnull)\s*([^)]+)\s*\)~i', $css, $matches))
			foreach ($matches[1] as $i => $var)
				$css = str_replace($matches[0][$i], isset($css_vars[$var]) ? $var : $matches[2][$i], $css);

		// Replace away!
		if (!empty($css_vars))
			$css = str_replace(array_keys($css_vars), array_values($css_vars), $css);
	}
}

/**
 * Conditionals, @if and @is (inline if).
 * If you test for a browser condition, you may place your test anywhere in the code.
 * If you test for a variable value, you can't declare mixins inside your test, because
 * the test will only be run after mixins are already transformed.
 */
class wess_if extends wess
{
	var $test_vars;

	public function __construct($test_vars = false)
	{
		$this->test_vars = $test_vars;
	}

	function process(&$css)
	{
		// @is (condition[, if_true[, if_false]])
		// This function will return if_true if true, or if_false if false. It will return literal 'true' and 'false' if no true/false are set (for use in variables.)
		// !! Note: this has got to be one of my most amusing regexes... But still, it doesn't always
		// correctly handle brackets. Add quotes around them if you run into any, err, problems. Lazy me.
		$pass_this = 0;
		$strex = '\s*+("(?:[^"@]|@(?!is\h*\())*"|\'(?:[^\'@]|@(?!is\h*\())*\'|(?:[^\'",@]|@(?!is\h*\())(?:[^,@]|@(?!is\h*\())*)\s*+';
		while (preg_match_all('~@is\h*\(' . $strex . '(?:,' . $strex . '(?:,' . str_replace(',', ')', $strex) . ')?)?\)~i', $css, $matches) > $pass_this)
		{
			foreach ($matches[1] as $i => $match)
			{
				// First, remove quotes that might be around our test...
				if ($match[0] == '\'' || $match[0] == '"')
					$match = substr($match, 1, -1);

				// If we're executing this before mixins, don't bother doing variables.
				if (!$this->test_vars && strpos($match, '$') !== false)
				{
					$pass_this++;
					continue;
				}

				// Are we doing a true/false test..?
				if (empty($matches[2][$i]) && empty($matches[3][$i]))
				{
					$matches[2][$i] = 'true';
					$matches[3][$i] = 'false';
				}

				if (we::is(we::$user['extra_tests'][] = $match))
				{
					if ($matches[2][$i][0] == '\'' || $matches[2][$i][0] == '"')
						$matches[2][$i] = substr($matches[2][$i], 1, -1);
					$css = str_replace($matches[0][$i], $matches[2][$i], $css);
				}
				else
				{
					if (!isset($matches[3][$i]))
						$matches[3][$i] = '';
					if ($matches[3][$i] !== '' && ($matches[3][$i][0] == '\'' || $matches[3][$i][0] == '"'))
						$matches[3][$i] = substr($matches[3][$i], 1, -1);
					$css = str_replace($matches[0][$i], $matches[3][$i], $css);
				}
			}
		}

		// @if / @else / @endif
		// This one is cleaner, but more demanding in terms of structure. @endif is required,
		// and all three commands need to be on the same tab level. Respect this or crash Wess.
		// You can nest commands inside @if and @else as well.

		// If PHP crashes, maybe it has too high a regex recursion limit, especially in Windows. Try uncommenting this:
		// ini_set('pcre.recursion_limit', '524');

		$pass_this = 0;
		while (preg_match_all('~(?<=\n)(\h*)@if\h+([^\n]+)(\n(?>[^@]|@(?!if\h))*?)\n\1@endif~i', $css, $matches, PREG_SET_ORDER) > $pass_this)
		{
			foreach ($matches as $m)
			{
				$match = $m[2];
				$parts = explode($m[1] . '@else', $m[3]);
				if (!isset($parts[1])) // no @else?
					$parts[1] = '';
				$remove_tabs = preg_match('~\h+~', $m[3], $tabs) ? strlen($tabs[0]) - strlen($m[1]) : 0;
				foreach ($parts as &$part)
					$part = preg_replace('~\n\h{' . $remove_tabs . '}~', "\n", $part);

				$i = -1;
				$num = count($parts);
				while (++$i < $num)
				{
					if (strtolower(substr($parts[$i], 0, 2)) == 'if' || strtolower(substr($parts[$i], 0, 3)) == ' if') // An @elseif, maybe?
					{
						$match = preg_match('~^if\h*([^\n]+)~', $parts[$i], $newif) ? trim($newif[1]) : '';
						$parts[$i] = substr($parts[$i], strlen($newif[0]));
					}

					// If we're executing this before mixins, don't bother doing variables.
					if (!$this->test_vars && strpos($match, '$') !== false)
					{
						$pass_this++;
						continue 2;
					}

					// And finally, the actual battery of tests.
					if (empty($match) || we::is(we::$user['extra_tests'][] = $match))
						break;

					$match = '';
				}
				$css = str_replace($m[0], $i < $num ? $parts[$i] : '', $css);
			}
		}
	}
}

/**
 * Apply color functions to the CSS file.
 */
class wess_color extends wess
{
	// Transforms "gradient: rgba(1,2,3,.5)" into background-color, or the equivalent IE filter.
	// Transforms "gradient: color1, color2, [angle]" into linear-gradient([angle], color1, color2), or the equivalent IE filter. Default angle is 180deg (top to bottom).
	// The angle parameter is optional. You can either use a direction (to left...), or the angle of the destination (0deg = from bottom to top)
	protected static function gradient_background($input)
	{
		$bg1 = $input[2];
		$bg2 = empty($input[3]) ? $bg1 : $input[3];
		$dir = empty($input[4]) ? '180deg' : $input[4];

		// IE 6, 7 and 8 will need a filter to apply the transparency effect, except for IE9. Also, IE8 can do without hasLayout.
		if (we::is('ie8down') || (we::is('ie9') && $bg1 != $bg2))
		{
			if (preg_match('~^#[0-9a-f]{3}$~i', $bg1))
				$bg1 = '#' . $bg1[1] . $bg1[1] . $bg1[2] . $bg1[2] . $bg1[3] . $bg1[3];
			if (preg_match('~^#[0-9a-f]{3}$~i', $bg2))
				$bg2 = '#' . $bg2[1] . $bg2[1] . $bg2[2] . $bg2[2] . $bg2[3] . $bg2[3];
			return $input[1] . 'background: none' . $input[1] . (we::is('ie6,ie7') ? 'zoom: 1' . $input[1] : '') .
				'filter:progid:DXImageTransform.Microsoft.Gradient(startColorStr=' . $bg1 . ',endColorStr=' . $bg2 . ($dir == 'left' ? ',GradientType=1' : '') . ')';
		}

		// Better than nothing...
		if (we::is('ie') && we::$browser['version'] < 10)
			return $input[1] . 'background-color: ' . $bg1;

		return $input[1] . 'background: ' . sprintf(we::is('safari') && we::$browser['version'] < 5.1 ?
			'-webkit-gradient(linear, 0%% 0%%, ' . ($dir == 'left' ? '100%% 0%%' : '0%% 100%%') . ', from(%1$s), to(%2$s))' :
			'linear-gradient(' . ($dir == '180deg' ? '' : $dir . ', ') . '%1$s, %2$s)',
			$bg1,
			$bg2
		);
	}

	// Now, go with the actual color parsing.
	function process(&$css)
	{
		$nodupes = array();

		// No need for a recursive regex, as we shouldn't have more than one level of nested brackets...
		while (preg_match_all('~(darker|lighter|stronger|desaturated|saturated|hue|complement|average|alpha|channels)\(((?:(?:rgb|hsl)a?\([^()]+\)|[^()])+)\)~i', $css, $matches))
		{
			foreach ($matches[0] as $i => $dec)
			{
				if (isset($nodupes[$dec]))
					continue;
				$nodupes[$dec] = true;
				$code = strtolower($matches[1][$i]);
				$m = strtolower(trim($matches[2][$i]));
				if (empty($m))
					continue;

				$rgb = wess::string2color($m);
				if ($rgb === false)
				{
					// Unfortunately, the alpha() function can clash with the equivalent IE filter...
					if ($code === 'alpha' && strpos($m, 'opacity') !== false)
						$css = str_replace($dec, 'alpha_ms_wedge' . substr($dec, 5), $css);
					// Syntax error? We just replace with red. Otherwise we'll end up in an infinite loop.
					else
						$css = str_replace($dec, 'red', $css);
					continue;
				}

				$nc = 0;
				$color = $rgb[1];
				$hsl = $rgb[2];
				$arg = explode(',', substr($m, strlen($rgb[0])));
				$parg = array();
				while ($arg && $arg[0] === '')
					array_shift($arg);

				$arg[0] = isset($arg[0]) ? $arg[0] : 5;
				if ($code === 'channels' && !isset($arg[0], $arg[1], $arg[2], $arg[3]))
					for ($i = 1; $i < 4; $i++)
						$arg[$i] = isset($arg[$i]) ? $arg[$i] : 0;
				foreach ($arg as $i => $a)
					$parg[$i] = substr($a, -1) === '%' ? ((float) substr($a, 0, -1)) / 100 : false;
				$hsl = $hsl ? $hsl : wess::rgb2hsl($color[0], $color[1], $color[2], $color[3]);

				// This is where we run our color functions...

				if ($code === 'average' && !empty($rgb[0]))
				{
					// Extract the second color from our list. Technically, we COULD
					// have an infinite number of colors... Is it any useful, though?
					$rgb2 = wess::string2color(ltrim(substr($m, strlen($rgb[0])), ', '));
					$color2 = $rgb2[1];
					$hsl2 = $rgb2[2] ? $rgb2[2] : wess::rgb2hsl($rgb2[1][0], $rgb2[1][1], $rgb2[1][2], $rgb2[1][3]);
					$hsl['h'] = ($hsl['h'] + $hsl2['h']) / 2;
					$hsl['s'] = ($hsl['s'] + $hsl2['s']) / 2;
					$hsl['l'] = ($hsl['l'] + $hsl2['l']) / 2;
					$hsl['a'] = ($hsl['a'] + $hsl2['a']) / 2;
				}

				// Change alpha (transparency) level
				elseif ($code === 'alpha')
					$hsl['a'] += $parg[0] ? $hsl['a'] * $parg[0] : $arg[0];

				// Darken the color (brightness down)
				elseif ($code === 'darker' || ($code === 'stronger' && $hsl['l'] < 0.5))
					$hsl['l'] -= $parg[0] ? $hsl['l'] * $parg[0] : $arg[0];

				// Lighten the color (brightness up)
				elseif ($code === 'lighter' || ($code === 'stronger' && $hsl['l'] >= 0.5))
					$hsl['l'] += $parg[0] ? $hsl['l'] * $parg[0] : $arg[0];

				// Desaturize the color (saturation down, gets color closer to grayscale)
				elseif ($code === 'desaturated')
					$hsl['s'] -= $parg[0] ? $hsl['s'] * $parg[0] : $arg[0];

				// Saturize the color (saturation up, gets color further away from grayscale)
				elseif ($code === 'saturated')
					$hsl['s'] += $parg[0] ? $hsl['s'] * $parg[0] : $arg[0];

				// Change color hue (moves it over the virtual color wheel by X degrees)
				elseif ($code === 'hue')
					$hsl['h'] += $parg[0] ? $parg[0] * 360 : $arg[0];

				// Get color's complement (retrieves the color at the opposite end of the color wheel)
				elseif ($code === 'complement')
					$hsl['h'] += 180;

				// Change color's channels individually (red, green, blue and alpha components)
				elseif ($code === 'channels')
				{
					if ($color === 0)
						$color = wess::hsl2rgb($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
					$nc = array(
						'r' => $color[0] + ($parg[0] ? $color[0] * $parg[0] : $arg[0]),
						'g' => $color[1] + ($parg[1] ? $color[1] * $parg[1] : $arg[1]),
						'b' => $color[2] + ($parg[2] ? $color[2] * $parg[2] : $arg[2]),
						'a' => $color[3] + ($parg[3] ? $color[3] * $parg[3] : $arg[3])
					);
				}

				else
				{
					// Do modders want to add their own color processor? Send them all the data they might need.
					// If they want even more, send them the author. Careful though, he's French. He bites.
					$hook = call_hook('css_color', array(&$nc, &$hsl, &$color, &$arg, &$parg, &$dec));

					// Attention modders: at the end of your hook function, set $nc or $hsl,
					// and then *return true* to tell Wedge you were here. Oh, and Brooks too.
					if (empty($hook))
						continue;
				}

				$nc = $nc ? $nc : wess::hsl2rgb($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
				$css = str_replace($dec, wess::color2string($nc['r'], $nc['g'], $nc['b'], $nc['a']), $css);
			}
		}

		$colval = '((?:rgb|hsl)a?\([^()]+\)|[^()\n,]+)';
		$css = preg_replace_callback('~(\n\h*)gradient\h*:\h*' . $colval . '(?:\s*,\s*' . $colval . ')?(?:\s*,\s*(-?\w+(?: \w+)?)?)?~i', 'wess_color::gradient_background', $css);
		$css = str_replace('alpha_ms_wedge', 'alpha', $css);
	}
}

// Miscellaneous helper functions...
class wess_func extends wess
{
	function process(&$css)
	{
		global $boardurl, $boarddir;

		if (!preg_match_all('~(width|height)\(([^)]+)\)~i', $css, $matches))
			return;

		$done = array();
		foreach ($matches[2] as $i => $file)
		{
			if (isset($done[$file]))
				continue;
			$done[$file] = true;

			// Get dimensions from file. Try to turn it into absolute path if a URL was given.
			list ($width, $height) = @getimagesize(str_replace($boardurl, $boarddir, $file));
			$css = str_replace(array('width(' . $file . ')', 'height(' . $file . ')'), array($width, $height), $css);
		}
	}
}

class wess_nesting extends wess
{
	var $rules, $props;

	// Sort the bases array by the first argument's length.
	private static function lensort($a, $b)
	{
		return strlen($b[0]) - strlen($a[0]);
	}

	private static function indentation($a)
	{
		return strlen($a[1]) . ':';
	}

	private static function protect_colons($a)
	{
		return str_replace(':', '#wedge-colon#', $a[0]);
	}

	function process(&$css)
	{
		/******************************************************************************
		 Process nested selectors
		 ******************************************************************************/

		// Transform the CSS into a tree
		$tree = str_replace('"', '#wedge-quote#', trim($css));

		// Does this file use the regular CSS syntax?
		$css_syntax = strpos($tree, "{\n") !== false && strpos($tree, "\n}") !== false;
		if (!$css_syntax)
		{
			/*
				Nope? Then let's have fun with our simplified syntax.

				WARNING: make sure to only use always tabs OR always spaces, and do proper
				structure nesting. Otherwise, the file won't parse properly.
				You must conform. It is my sworn duty to see that you do conform.
			*/
			$tree = preg_replace("~\n\s*\n~", "\n", $tree); // Delete blank lines
			$tree = preg_replace_callback('~^(\h*)~m', 'wess_nesting::indentation', $tree);
			$branches = explode("\n", $tree);
			$tree = $ex_string = '';
			$level = 0;
			foreach ($branches as &$line)
			{
				$l = explode(':', $line, 2);
				if (!isset($indent) && !empty($l[0]))
					$indent = $l[0];
				if (!isset($indent))
				{
					$tree .= $l[1] . "\n";
					continue;
				}

				// Do we have an extends/unextend line followed by a line on the same level or above it?
				// If yes, this means we just extended a selector and should close it immediately.
				if ($level >= $l[0] && (strpos($ex_string, ' extends ') !== false || strpos($ex_string, ' unextends ') !== false))
					$tree .= " {\n}\n";

				// Same level, and no continuation of a selector? We're probably in a list of properties.
				elseif ($level == $l[0] && substr($ex_string, -1) !== ',')
					$tree .= ";\n";
				// Higher level than before? This is a child, obviously.
				elseif ($level < $l[0])
					$tree .= " {\n";

				while ($level > $l[0])
				{
					$tree .= "}\n";
					$level -= $indent;
				}

				$level = $l[0];
				$tree .= $l[1];
				$ex_string = $l[1];
			}

			// Did we finish the file with an extends or unextends...? Immediately open it and close it.
			if ((strpos($ex_string, ' extends ') !== false || strpos($ex_string, ' unextends ') !== false))
				$tree .= " {\n}\n";
			while ($level > 0)
			{
				$tree .= '}';
				$level -= $indent;
			}
		}

		/*
			'@replace' command: replaces any string with another. Just put @replace on a line, and add
			two indented lines: search string on the first, replacement string on the second. For instance:

			@replace
				rule: search
				rule: replace
		*/
		// !! @todo: rewrite this at the end of the parser so that we can specify target selectors after the @replace keyword.
		preg_match_all('~\n\h*@replace\h*{\n\h*([^\n]+);\n\h*([^\n]*)}~i', $tree, $replacements, PREG_SET_ORDER);
		if (!empty($replacements))
			foreach ($replacements as $replace)
				$tree = str_replace($replace[1], $replace[2], $tree);
		$tree = preg_replace('~\n\h*@replace\h*{\n\h*[^\n]+;\n\h*[^\n]*}~i', "\n", $tree);

		/*
			And a few more pre-parsing actions...
			A couple of reminders on @import:
			(1) avoid using them in CSS files. REALLY. Use <css> in custom.xml, or add_css_file(), or add_css().
			(2) @import won't work if used inside a suffixed file, because @import is only parsed when
				found at the start of a physical file (or within <style> tags in the main HTML.)
		*/
		$tree = preg_replace('~^(@(?:import|charset)\h+[^{}\n]*);?$~mi', '<rule selector="$1"></rule>', $tree); // Transform single-line @rules into selectors
		$tree = preg_replace('~^([!+>&#*@:.a-z0-9][^{};]*?\h*reset);~mi', '<rule selector="$1"></rule>', $tree); // Transform single-line resets into selectors
		$tree = preg_replace_callback('~\burl\([^)]+\)~', 'wess_nesting::protect_colons', $tree); // Protect colons (:) inside URLs
		$tree = preg_replace('~([a-z-, ]+)\h*:(?!//)\h*([^;}{' . ($css_syntax ? '' : '\n') . ']+?);*\h*(?=[\n}])~i', '<property name="$1" value="$2">', $tree); // Transform properties
		$tree = preg_replace('~^([!+>&#*@:.a-z0-9](?:[^{\n]|(?=,)\n)*?)\s*{~mi', '<rule selector="$1">', $tree); // Transform selectors. Strings starting with a digit are only allowed because of keyframes.
		$tree = preg_replace(array('~ {2,}~'), array(' '), $tree); // Remove extra spaces
		$tree = str_replace(array('}', "\n"), array('</rule>', "\n\t"), $tree); // Close rules and indent everything one tab

		 // Parse the XML into a crawlable DOM
		$this->pierce($tree);

		/******************************************************************************
		 Rebuild parsed CSS
		 ******************************************************************************/

		$css = $standard_nest = '';
		$bases = $virtuals = $used_virtuals = $removals = $selector_removals = $unextends = array();
		$selector_regex = '((?<![a-z])[abipqsu]|[!+>&#*@:.a-z0-9][^{};,\n"]+)';

		// 'reset' keyword: remove earlier occurrences of a selector.
		// e.g.: ".class reset" -> removes all previous ".class" definitions
		foreach ($this->rules as $n => &$node)
		{
			if (strpos($node['selector'], ' reset') !== false)
			{
				preg_match_all('~' . $selector_regex . '\h+reset\b~i', $node['selector'], $matches, PREG_SET_ORDER);
				foreach ($matches as $m)
				{
					// Start by rebuilding a full selector. For efficiency reasons, and because I'm too lazy to rework this,
					// Wedge will only parse single parents, and will reject comma-separated classes and parents using
					// keywords such as 'extends'. If you come up with an implementation to support them, feel free to share!
					$selector = $m[1];
					$current_node = $node;
					while ($current_node['parent'] > 0)
					{
						$current_node = $this->rules[$current_node['parent']];
						$selector = $current_node['selector'] . ' ' . $selector;
					}
					$quoted = preg_quote($selector, '~');
					$full_test = '~(?:^|\s|,)' . $quoted . '(?:,|\s|$)~m';

					// Run through all earlier rules and delete anything related to this.
					foreach ($this->rules as $n2 => &$node2)
					{
						if ($n === $n2)
							break;
						// Remove our reset selector from the current selector.
						if (strpos($node2['selector'], $selector) !== false && preg_match($full_test, $node2['selector']))
						{
							$node2['selector'] = trim(preg_replace('~(?:^|,)[^,]*' . $quoted . '[^,]*(?:,|$)~m', ',', $node2['selector']), "\x00..\x20,");
							$node2['selector'] = str_replace(',,', ',', $node2['selector']);
							if (empty($node2['selector']))
								$this->unset_recursive($n2);
						}
					}
				}
				$node['selector'] = preg_replace('~\breset\b~i', '', $node['selector']);
				if (trim($node['selector'] == ''))
				{
					unset($this->rules[$n]);
					continue;
				}
			}
		}

		// 'virtual' keyword: remove rule if nothing extends on it.
		// Please note that Wess will only accept one virtual selector at a time.
		foreach ($this->rules as $n => &$node)
		{
			if (strpos($node['selector'], ' virtual') !== false)
			{
				if (preg_match('~' . $selector_regex . '\h+virtual\b~i', $node['selector'], $matches))
				{
					$node['selector'] = str_replace($matches[0], $matches[1], $node['selector']);
					$virtuals[$matches[1]] = $n;
				}
			}
		}

		$is_ie6 = we::is('ie6');

		// Replace ".class extends .original_class, .class2 extends .other_class" with ".class, .class2"
		foreach ($this->rules as $n => &$node)
		{
			/*
				'@remove' command: remove properties as specified. To remove all "background: #fff" rules from .class and h1
				and associated selectors (anything that inherits .class or h1, or is inherited by it), use this:

				@remove .class, h1
					background: #fff

				Of course you may also use just one selector, or provide no selectors; Wess will target all selectors in the entire file.
				You may also replace #fff with a wildcard (*), in which case this rule will be removed, whatever its value.
			*/
			if (strpos($node['selector'], '@remove') === 0)
			{
				$sels = preg_match('~@remove\h+(?:from\h+)?([^\n]+)~', $node['selector'], $sels) ? array_map('trim', explode(',', trim(str_replace('#wedge-quote#', '"', $sels[1]), "\x00..\x20\""))) : array();
				foreach ($node['props'] as $remove)
				{
					if (empty($sels))
						$removals[$remove['name'] . ':' . $remove['value']] = true;
					else
						foreach ($sels as $selector)
							$selector_removals[$selector][$remove['name'] . ':' . $remove['value']] = true;
				}
				unset($this->rules[$n]);
				continue;
			}

			// 'unextends' keyword: reset specified inheritance.
			// e.g.: ".class unextends .orig "-> cancels any earlier ".class extends .orig"
			if (strpos($node['selector'], ' unextends') !== false)
			{
				preg_match_all('~' . $selector_regex . '\h+unextends\b~i', $node['selector'], $matches, PREG_SET_ORDER);
				foreach ($matches as $m)
					$unextends[$m[1]] = $n;
				$node['selector'] = preg_replace('~\bunextends\b~i', '', $node['selector']);
				if (trim($node['selector'] == ''))
				{
					unset($this->rules[$n]);
					continue;
				}
			}

			// 'extends' keyword: selector inheritance.
			// e.g.: ".class extends .orig" -> turns all ".orig" definitions into ".orig, .class"
			if (strpos($node['selector'], 'extends') !== false)
			{
				// A quick hack to turn direct selectors into normal selectors when in IE6. This is because it ignores direct selectors, as well as
				// any selectors declared alongside them. If you still want these selectors to inherit something, do it manually in an '@if ie6' block!
				if ($is_ie6 && strpos($node['selector'], '>') !== false)
					$node['selector'] = ' ';
				$node['selector'] = str_replace('#wedge-quote#', '"', $node['selector']);
				preg_match_all('~' . $selector_regex . '\h+extends\h+("[^\n{"]+"|[^\n,{"]+)~i', $node['selector'], $matches, PREG_SET_ORDER);
				foreach ($matches as $m)
				{
					$save_selector = $node['selector'];
					$node['selector'] = $m[1];
					$path = implode(',', $this->parse_ancestors($node));

					// In case we extend directly from a parent's property, make sure to keep only the first parent (reset) if we have several.
					if (strpos($m[2], '&') !== false)
						$m[2] = str_replace('&', reset(($this->parse_ancestors($this->rules[$node['parent']]))), $m[2]);

					// And if we have multiple inheritance, add each selector to the base list.
					$targets = array_map('trim', explode(',', trim($m[2], '"')));
					foreach ($targets as $target)
						$bases[] = array(
							$target, // Add to this class in the tree...
							preg_quote($target),
							$path, // ...The current selector
							$n,
						);
					$node['selector'] = str_replace($m[0], $m[1], $save_selector);
				}
			}
		}

		// Look for base: property inheritance
		foreach ($this->props as &$node)
		{
			if ($node['name'] === 'base')
			{
				$selectors = $this->split_selectors($this->rules[$node['parent']]['selector']);
				foreach ($selectors as &$here)
				{
					$parent = empty($this->rules[$node['parent']]['parent']) ? array() : $this->get_ancestors($this->rules[$this->rules[$node['parent']]['parent']]);
					$path = $this->parse_ancestors(array_merge((array) $here, $parent), true);

					if (strpos($node['value'], '&') !== false)
						$node['value'] = str_replace('&', $path[0], $node['value']);

					$path = implode(',', $path);
					$targets = $this->split_selectors($node['value']);
					foreach ($targets as $target)
						$bases[] = array(
							$target, // Add to this class in the tree...
							preg_quote($target),
							$path, // ...The current selector
							$node['id'],
						);
				}
				if (isset($this->rules[$node['parent']]))
					unset($this->rules[$node['parent']]['props'][$node['id']]);
				unset($this->props[$node['id']], $node);
			}
		}

		foreach ($bases as $i => $base)
		{
			// Delete unextends
			if (isset($unextends[$base[2]]) && $base[3] < $unextends[$base[2]])
				unset($bases[$i]);

			// Do we have multiple selectors to extend?
			elseif (strpos($base[2], ',') !== false)
			{
				$selectors = $this->split_selectors($base[2]);
				$bases[$i][2] = $selectors[0];
				unset($selectors[0]);
				foreach ($selectors as $sel)
					$bases[] = array(
						$base[0],
						$base[1],
						$sel,
						$base[3],
					);
			}
		}

		// Sort the bases array by the first argument's length, and then by the third argument's length.
		usort($bases, 'wess_nesting::lensort');

		// Delete any virtuals that aren't actually inherited. Additionally,
		// ignore the $base value in case it's set by another virtual.
		foreach ($bases as $i => $base)
			if (isset($virtuals[$base[0]]) && !isset($virtuals[$base[2]]))
				$used_virtuals[$base[0]] = $virtuals[$base[0]];
		$unused_virtuals = array_diff_key($virtuals, $used_virtuals);
		foreach ($unused_virtuals as $n2)
			$this->unset_recursive($n2);

		// Now that all your base are belong to us, we'll just regroup them!
		$extends = array();
		foreach ($bases as $base)
		{
			if (isset($extends[$base[0]]))
				$extends[$base[0]][2][] = $base[2];
			else
			{
				$extends[$base[0]] = $base;
				$extends[$base[0]][2] = array($extends[$base[0]][0] => $extends[$base[0]][2]);
			}
		}

		// A time-saver to determine whether a character is within the alphabet...
		$alpha = array_flip(array_merge(range('a', 'z'), range('A', 'Z')));

		// We'll build this regex now. It determines whether a base selector is within a string.
		$no_virtuals_regex = '';
		foreach ($virtuals as $virtual => $dummy)
			$no_virtuals_regex .= (isset($alpha[$virtual[0]]) ? '(?<![a-z0-9_-])' : '') . preg_quote($virtual, '~') . '|';
		$no_virtuals_regex = '~(' . substr($no_virtuals_regex, 0, -1) . ')(?![a-z0-9_-])~i';

		// Do the proper nesting
		foreach ($this->rules as &$node)
		{
			// Is this rule actually an at-rule? They need special treatment.
			if ($node['selector'][0] === '@')
			{
				if (stripos($node['selector'], '@import') === 0 || stripos($node['selector'], '@charset') === 0)
				{
					$css .= $node['selector'] . ';';
					continue;
				}
				// Blocks starting with @media, @keyframes, @supports, @viewport...
				if (preg_match('~^@[a-z]+~i', $node['selector']))
				{
					// Are we already in a @keyword block? Then close it first...
					if (!empty($standard_nest))
						$css .= '}';
					$standard_nest = $node['selector'];
					$css .= $node['selector'] . ' {';
					continue;
				}
			}

			$selectors = $this->parse_ancestors($node);
			foreach ($selectors as $key => $val)
				if (strpos($val, '&gt;') !== false)
					$selectors[$key] = str_replace('&gt;', '>', $val);
			$done = array();
			$changed = true;

			while ($changed)
			{
				$done_temp = array();
				$changed = false;
				foreach ($extends as $name => $base)
				{
					// We have a selector like ".class, #id > div a" and we want to know if it has the base "#id > div" in it
					$is_in = false;
					foreach ($selectors as $sel)
					{
						if (strpos($sel, $name) !== false)
						{
							$is_in = true;
							break;
						}
					}
					if ($is_in)
					{
						$beginning = isset($alpha[$name[0]]) ? '(?<![\w-])' : '';

						foreach ($selectors as &$snippet)
						{
							if (!isset($done[$snippet]) && preg_match('~' . $beginning . '(' . $base[1] . ')(?![\w-]|.*\h+final\b)~i', $snippet))
							{
								// And our magic trick happens here.
								foreach ($base[2] as $extend)
									$selectors[] = trim(str_replace($name, $extend, $snippet));

								// Then we restart the process to handle inherited extends.
								$done_temp[$snippet] = true;
								$changed = true;
							}
						}
					}
				}

				if ($changed)
					$done = array_merge($done, $done_temp);
			}

			$selectors = array_flip(array_flip($selectors));
			if (!empty($virtuals))
				foreach ($selectors as $i => $sel)
					if (preg_match($no_virtuals_regex, $sel))
						unset($selectors[$i]);

			sort($selectors);
			$selector = implode(',', $selectors);

			$specific_removals = array();
			foreach ($selectors as $removable_selector)
				if (isset($selector_removals[$removable_selector]))
					$specific_removals += $selector_removals[$removable_selector];

			if (!empty($standard_nest))
			{
				if (substr_count($selector, $standard_nest))
					$selector = trim(str_replace($standard_nest, '', $selector));
				else
				{
					$css .= '}';
					$standard_nest = '';
				}
			}

			$css .= $selector . ' {';

			foreach ($node['props'] as &$prop)
			{
				// Does this property belong to a general @remove?
				if (isset($removals[$prop['name'] . ':' . $prop['value']]) || isset($removals[$prop['name'] . ':*']))
					continue;
				// Does this property belong to a @remove specific to this selector?
				if (isset($specific_removals[$prop['name'] . ':' . $prop['value']]) || isset($specific_removals[$prop['name'] . ':*']))
					continue;
				// Or maybe a regular one?
				if (!strpos($prop['name'], ','))
					$css .= $prop['name'] . ': ' . $prop['value'] . ';';
				// Or multiple properties with the same value?
				else
					foreach (explode(',', $prop['name']) as $names)
						$css .= $names . ': ' . $prop['value'] . ';';
			}

			$css .= '}';
		}

		if (!empty($standard_nest))
			$css .= '}';

		$css = str_replace('#wedge-colon#', ':', $css);
	}

	private function unset_recursive($n)
	{
		foreach ($this->rules[$n]['props'] as $n2 => $dummy)
			unset($this->rules[$n2], $this->props[$n2]);
		foreach ($this->rules[$n]['children'] as $n2)
			if (isset($this->rules[$n2]))
				$this->unset_recursive($n2);
		unset($this->rules[$n]);
	}

	private function get_ancestors(&$node)
	{
		if (empty($node['parent']))
			return (array) $node['selector'];

		return array_merge((array) $node['selector'], $this->get_ancestors($this->rules[$node['parent']]));
	}

	private function parse_ancestors($node, $is_ancestors = false)
	{
		$ancestors = $is_ancestors ? $node : $this->get_ancestors($node);
		$growth = array();

		foreach ($ancestors as $selector)
		{
			$these = $this->split_selectors($selector);

			if (empty($growth))
			{
				$growth = $these;
				continue;
			}

			$fresh = array();

			foreach ($these as $tSelector)
			{
				foreach ($growth as $gSelector)
				{
					$amp = strpos($gSelector, '&');
					$fresh[] = ($amp > 0 ? substr($gSelector, 0, $amp) : '') . $tSelector . ($amp !== false ? substr($gSelector, $amp + 1) : ($gSelector[0] === ':' ? '' : ' ') . $gSelector);
				}
			}

			$growth = $fresh;
		}

		sort($growth);
		return $growth;
	}

	private function split_selectors($selector)
	{
		// Does this selector have commas in it? We'll have to protect it first...
		// Basically, (..,..) [..,..] "..,.." and '..,..' will be protected before the split.
		if ($has_commas = strpos($selector, ',') !== false)
			while (preg_match('~\([^(]*,[^(]*\)|\[[^[]*,[^[]*]|"[^"]*,[^"]*"|\'[^\']*,[^\']*\'~', $selector, $match))
				$selector = str_replace($match[0], strtr($match[0], ',', "\x14"), $selector);

		$arr = array_map('trim', explode(',', $selector));
		if ($has_commas)
			foreach ($arr as $key => $val)
				if (strpos($val, "\x14") !== false)
					$arr[$key] = strtr($val, "\x14", ',');

		return $arr;
	}

	private function pierce(&$data)
	{
		preg_match_all('~<(/?)([a-z]+)\h*(?:name="([^"]*)"\h*)?(?:(?:value|selector)="([^"]*)")?[^>]*>~s', $data, $tags, PREG_SET_ORDER);

		$id = 1;
		$parent = array(0);
		$level = 0;
		$rules = $props = array();
		$rule = 'rule';
		$property = 'property';

		// This is where we analyze the nesting levels and rebuild the original structure.
		foreach ($tags as &$tag)
		{
			if (empty($tag[1]))
			{
				if ($tag[2] === $rule)
				{
					$rules[$id] = array(
						'selector' => $tag[4],
						'parent' => $parent[$level],
						'children' => array(),
						'props' => array(),
					);
					if (!empty($parent[$level]))
						$rules[$parent[$level]]['children'][] = $id;
					$parent[++$level] = $id++;
				}
				elseif ($tag[2] === $property)
				{
					$props[$id] = array(
						'name' => $tag[3],
						'value' => $tag[4],
						'id' => $id,
						'parent' => $parent[$level],
					);
					$rules[$parent[$level]]['props'][$id] =& $props[$id];
					$id++;
				}
			}
			// If the nesting is broken, $level will be < 0 and will generate an esoteric error.
			// We should skip that, at least until we have a nice and clear error message for that.
			elseif ($level > 0)
				$level--;
		}

		$this->rules =& $rules;
		$this->props =& $props;
	}
}

// Simple math functions. For instance, width: math((2px * 8px)/4) should return width: 4px
// Don't mix different unit types in the same operation, Wedge won't bother.
class wess_math extends wess
{
	function process(&$css)
	{
		if (!preg_match_all('~(["\']?)math\(((?:[\t ()\d.+/*%-]|(?<=\d)([a-z]{2,4})|\b(?:math|round|ceil|floor|abs|fmod|min|max|rand)\()+)\)\\1~i', $css, $matches))
			return;

		$done = array();
		foreach ($matches[2] as $i => $math)
		{
			if (isset($done[$math]))
				continue;
			$done[$math] = true;

			if (strpos(strtolower($math), 'math(') !== false)
				$this->process($math);

			if (isset($matches[3][$i]))
				$math = preg_replace('~(?<=\d)' . $matches[3][$i] . '~', '', $math);

			$css = str_replace($matches[0][$i], eval('return (' . $math . ');') . $matches[3][$i], $css);
		}
	}
}

// IE 6/7/8 don't support rgba/hsla, so we're replacing them with regular rgb colors mixed with an alpha variable.
// The only exception is the gradient function, because it accepts a #aarrggbb value.
class wess_rgba extends wess
{
	// Converts from a string (possibly rgba) value to a rgb string
	private static function rgba2rgb($input)
	{
		global $alphamix;
		static $cache = array();

		if (isset($cache[$input[0]]))
			return $cache[$input[0]];

		$str = wess::string2color($input[2]);
		if (empty($str))
			return $cache[$input[0]] = 'red';
		list ($r, $g, $b, $a) = $str[1] ? $str[1] : wess::hsl2rgb($str[2]['h'], $str[2]['s'], $str[2]['l'], $str[2]['a']);

		if ($a == 1)
			return $cache[$input[0]] = $input[1] . wess::rgb2hex($r, $g, $b);
		if (!empty($input[1]))
			return $cache[$input[0]] = $input[1] . '#' . sprintf('%02x%02x%02x%02x', round($a * 255), $r, $g, $b);

		// We're going to assume the matte color is white, otherwise, well, too bad.
		if (isset($alphamix) && !is_array($alphamix))
		{
			$rgb = wess::string2color($alphamix);
			if (empty($rgb[1]) && !empty($rgb[2]))
				$rgb[1] = hsl2rgb($rgb[2]['h'], $rgb[2]['s'], $rgb[2]['l'], $rgb[2]['a']);
			$alphamix = $rgb[1];
		}
		elseif (!isset($alphamix))
			$alphamix = array(255, 255, 255);

		$ma = 1 - $a;
		$r = $a * $r + $ma * $alphamix[0];
		$g = $a * $g + $ma * $alphamix[1];
		$b = $a * $b + $ma * $alphamix[2];

		return $cache[$input[0]] = wess::rgb2hex($r, $g, $b);
	}

	function process(&$css)
	{
		$css = preg_replace_callback('~(colorstr=)' . (we::is('ie8down') ? '?' : '') . '((?:rgb|hsl)a?\([^()]*\))~i', 'wess_rgba::rgba2rgb', $css);
	}
}

// Fix some commonly used CSS properties/values to use prefixes as required by the current browser.
class wess_prefixes extends wess
{
	// Build a prefix variable, enabling you to use "-prefix-something" to get it replaced with your browser's own flavor, e.g. "-moz-something".
	// Please note that it isn't currently used by Wedge itself, but you can use it to provide both prefixed and standard versions of a tag that isn't
	// already taken into account by the wess_prefixes() function (otherwise you only need to provide the unprefixed version.)
	var $prefix = '';

	public function __construct()
	{
		$this->prefix = we::is('opera') ? '-o-' : (we::is('webkit') ? '-webkit-' : (we::is('gecko') ? '-moz-' : (we::is('ie') ? '-ms-' : '')));
	}

	// This is a little trick to convert degrees between prefixed and unprefixed gradients. I even shared: http://tinyurl.com/pcnfk27
	private static function degrees($a)
	{
		return $a[1] . (90 - $a[2]);
	}

	private static function radial($a)
	{
		return $a[2] . ($a[1] != '' ? ', ' . $a[1] : '');
	}

	/**
	 * Fix CSS properties, i.e. rules, anything before a colon.
	 * Compatibility sheet is adapted from caniuse.com
	 *
	 * @param string $matches The actual CSS contents
	 * @return string Updated CSS contents with fixed code
	 */
	private function fix_rules($matches)
	{
		// Some shortcuts...
		$unchanged = $matches[0];
		$prefixed = $this->prefix . $unchanged;
		$both = $prefixed . $unchanged;
		$b = we::$browser;
		$os = we::$os;
		$v = $b['version'];
		$ov = $os['version'];
		list ($ie, $ie8down, $ie9, $ie10, $ie11, $opera, $firefox, $safari, $chrome, $ios, $android, $webkit) = array(
			$b['ie'], $b['ie8down'], $b['ie9'], $b['ie10'], $b['ie11'], $b['opera'], $b['firefox'],
			$b['safari'] && !$os['ios'], $b['chrome'], $os['ios'], $os['android'] && $b['webkit'] && !$b['chrome'], $b['webkit']
		);

		// Only IE6/7/8 don't support border-radius these days.
		if (strpos($matches[1], '-radius') !== false)
		{
			if ($ie8down)
				return '';
			// Older browsers require a prefix...
			if (($firefox && $v < 4) || ($ios && $ov < 4) || ($safari && $v < 5) || ($android && $ov < 2.2))
				return $prefixed;
			return $unchanged;
		}

		// Only newer Firefox, Chrome, IE and Safari versions support border-image without a prefix.
		if ($matches[1] === 'border-image')
		{
			if ($ie && $v < 11)
				return '';
			if ($ie || ($chrome && $v >= 16) || ($ios && $ov >= 6) || ($safari && $v >= 6) || ($firefox && $v >= 15))
				return $unchanged;
			return $prefixed;
		}

		// IE6/7/8 don't support box-shadow, and Safari Mobile requires a prefix up to version 5.
		if ($matches[1] === 'box-shadow')
		{
			if ($ie8down)
				return '';
			if (($firefox && $v < 4) || ($ios && $ov < 5) || ($safari && $v < 5.1))
				return $prefixed;
			if ($android)
				return $both;
			return $unchanged;
		}

		// IE6 and IE7 don't support box-sizing, while Mozilla, and older Androids and Safaris require a prefix.
		if ($matches[1] === 'box-sizing')
		{
			if ($ie && $v < 8)
				return '';
			if ($firefox || ($ios && $ov < 5) || ($safari && $v < 5.1) || ($android && $ov < 4))
				return $prefixed;
			return $unchanged;
		}

		// IE6/7/8/9 don't support columns, IE10 and Opera support them, other browsers require a prefix.
		if (strpos($matches[1], 'column') === 0)
		{
			if ($ie8down || $ie9 || ($firefox && $v < 3.6) || ($opera && $v < 11.1))
				return '';
			return $opera || $ie10 || $ie11 ? $unchanged : $prefixed;
		}

		// WebKit requires some magic for column breaks.
		if (strpos($matches[1], 'break-') === 0)
		{
			if ($ie8down || $ie9 || ($firefox && $v < 3.6) || ($opera && $v < 11.1))
				return '';
			return $opera || $ie10 || $ie11 ? $unchanged : $this->prefix . 'column-' . $unchanged;
		}

		// As of July 2013, IE10+, Firefox and WebKit support this prefixed. Opera<14 and IE<10 don't.
		if ($matches[1] === 'user-select')
		{
			if ($firefox || $webkit || ($ie && $v >= 10))
				return $prefixed;
			return '';
		}

		// As of July 2013, IE10 supports this unprefixed, and Firefox and Chrome need a prefix.
		if ($matches[1] === 'font-feature-settings')
		{
			if ($ie && $v >= 10)
				return $unchanged;
			return $prefixed;
		}

		// IE6/7/8/9 don't support animations, IE10, Firefox 16+ and Opera 12.1 support them unprefixed, other browsers require a prefix.
		if (strpos($matches[1], 'animation') === 0)
		{
			if ($ie8down || $ie9 || ($firefox && $v < 5) || ($opera && $v < 12) || ($safari && $v < 4))
				return '';
			if (($opera && $v < 12.1) || ($firefox && $v < 16) || $webkit)
				return $prefixed;
			return $unchanged;
		}

		// IE6/7/8 don't support transforms, IE10, Firefox 16+ and Opera 12.1x (not 15) support them unprefixed, other browsers require a prefix.
		if (strpos($matches[1], 'transform') === 0)
		{
			if ($ie8down || ($firefox && $v < 3.5))
				return '';
			if ($ie9 || ($opera && $v < 12.1) || ($firefox && $v < 16) || $webkit)
				return $prefixed;
			return $unchanged;
		}

		// Browser support level is identical for both of these, according to MDN and caniuse.com.
		if (strpos($matches[1], 'backface-visibility') === 0 || strpos($matches[1], 'perspective') === 0)
		{
			if (($ie && $v >= 10) || ($firefox && $v >= 16))
				return $unchanged;
			if (($firefox && $v >= 10) || ($chrome && $v >= 12) || $webkit)
				return $prefixed;
			return '';
		}

		// Hyphens aren't supported or always require a prefix, for now.
		return $both;
	}

	/**
	 * Same as above, but with some common values.
	 *
	 * @param string $matches The actual CSS contents
	 * @return string Updated CSS contents with fixed code
	 */
	private function fix_values($matches)
	{
		$unchanged = $matches[0];
		$b = we::$browser;
		$os = we::$os;
		$v = $b['version'];
		$ov = $os['version'];

		// IE 6-9 don't support gradients (screw 'em!), IE10 final supports them unprefixed,
		// and Firefox 16, Chrome 26 and Safari 7 dropped the prefix. Not iOS 7.
		if (strpos($matches[1], 'gradient(') !== false)
		{
			if (($b['chrome'] && $v >= 26) || ($b['gecko'] && $v >= 16) || ($b['opera'] && $v >= 12.1) || ($b['safari'] && $v >= 7) || ($b['ie'] && $v >= 10))
				return $unchanged;

			$prefixed = preg_replace('~(?<=[\s:])([a-z][a-z-]+-gradient\h*\()~', $this->prefix . '$1', $unchanged);

			if (strpos($prefixed, 'deg') !== false)
				$prefixed = preg_replace_callback('~(gradient\h*\(\s*)(-?(?:\d+|\d*\.\d+))(?=deg\b)~', 'wess_prefixes::degrees', $prefixed);

			if (strpos($prefixed, 'radial-gradient') !== false && $b['webkit']) // Pretty much Safari-specific...
				$prefixed = preg_replace_callback('~(?<=radial-gradient\()([\sa-z-]+\s+)?at\s([^,]+)(?=,)~', 'wess_prefixes::radial', $prefixed);

			return $prefixed;
		}

		// IE6/7/8/9 don't support transitions. IE10, Chrome 26+, Firefox 16+ and Opera 12.10+ support them unprefixed, other browsers require a prefix.
		if (strpos($matches[1], 'transition') !== false)
		{
			// In case the transition value is 'transform', we need to prefix it on browsers that need it.
			if ($b['ie9'] || ($b['opera'] && $v < 12.1) || ($b['firefox'] && $v < 16) || $b['webkit'])
				$unchanged = str_replace($matches[2], preg_replace('~\btransform\b~', $this->prefix . 'transform', $matches[2]), $unchanged);
			if ($b['ie8down'] || $b['ie9'] || ($b['firefox'] && $v < 4))
				return '';
			if (($b['opera'] && $v < 12.1) || ($b['firefox'] && $v < 16) || ($b['chrome'] && $v < 26) || ($b['safari'] && $v < 7) || ($os['ios'] && $ov < 7))
				return $this->prefix . $unchanged;
			return $unchanged;
		}

		// The final flexbox model (Chrome 21+, Opera 12.1, IE11+, Safari 7+) is final, as the name says, and shouldn't require a prefix. Silly WebKit...
		if ((($b['safari'] && $v >= 7) || ($os['ios'] && $ov >= 7) || ($b['chrome'] && $v >= 21 && $v < 29)) && strpos($matches[1], 'flex') !== false)
			return str_replace(array('inline-flex', 'flex'), array($this->prefix . 'inline-flex', $this->prefix . 'flex'), $unchanged);

		// There's a need for min/max-resolution to be rewritten for some browsers.
		if (strpos($matches[1], 'resolution') !== false)
		{
			// CSS 'dpi' really are 'dots-per-CSS-inch', not physical inches. Thus, default is always 96dpi (= 1dppx), even on iOS.
			$dpi = $matches[4] == 'dpi' ? $matches[3] : $matches[3] * 96;
			// Firefox 3.5 (?) to 15: min--moz-device-pixel-ratio: 2
			// Firefox 16+: directly accepts min-resolution: 2dppx (or 192dpi)
			if ($b['firefox'] && $v < 16)
				return $matches[2] . '-moz-device-pixel-ratio:' . ($dpi / 96);
			// Firefox >= 16, Chrome >= 29 and Opera >= 12.1 support dppx, so go with it...
			if ($b['firefox'] || ($b['chrome'] && $v >= 29) || ($b['opera'] && $v >= 12.1))
				return $unchanged;
			// WebKit and Chrome < 29 only support -webkit-min-device-pixel-ratio: 2
			if ($b['webkit'])
				return $this->prefix . $matches[2] . '-device-pixel-ratio:' . ($dpi / 96);
			// IE9+ and older Opera should be fine with a dpi unit.
			return $matches[2] . '-resolution:' . $dpi . 'dpi';
		}

		// All browsers expect commas between elements in rect(), except for IE 6/7. The usual...
		if ($b['ie'] && $v < 8 && strpos($matches[1], 'rect') !== false)
			return str_replace($matches[2], str_replace(',', ' ', $matches[2]), $matches[1]);

		// IE9+/Firefox 16+/Chrome 26+ support this unprefixed, Safari 6 needs a prefix.
		if (strpos($matches[1], 'calc') !== false)
		{
			if (($b['ie'] && $v >= 9) || ($b['chrome'] && $v >= 26) || ($b['firefox'] && $v >= 16))
				return $matches[1];
			if (($b['chrome'] && $v >= 19) || ($b['firefox'] && $v >= 4) || ($b['safari'] && $v == 6) || ($os['ios'] && $ov >= 6 && $ov < 7))
				return $this->prefix . $matches[1];
			// Keep it even if not supported. CSS may provide a prior fallback anyway..?
			return $matches[1];
		}

		// Nothing bad was found? Just ignore.
		return $unchanged;
	}

	function process(&$css)
	{
		// Some prominent CSS3 may or may not need a prefix. Wedge will take care of that for you.
		$rules = array(

			'border(?:-[a-z-]+)?-radius',	// Rounded corners
			'box-shadow',					// Rectangular drop shadows
			'box-sizing',					// Determines whether a container's width includes padding and border
			'border-image',					// Border images
			'user-select',					// Prevents from selecting custom controls
			'font-feature-settings',		// Ligatures and other things
			'hyphens',						// Automatic hyphens on long words
			'column(?:s|-[a-z-]+)',			// Multi-column layout
			'break-[a-z-]+',				// Multi-column layout
			'grid-[a-z]+',					// Grid layout
			'animation(?:-[a-z-]+)?',		// Proper animations
			'transform(?:-[a-z-]+)?',		// 2D/3D transformations (transform, transform-style, transform-origin...)
			'backface-visibility',			// 3D
			'perspective(?:-origin)?',		// 3D

		);
		foreach ($rules as $val)
			$css = preg_replace_callback('~(?<!-)(' . $val . '):[^\n;]+[\n;]~', array($this, 'fix_rules'), $css);

		// Same thing for a few more rules that need a more elaborate detection...
		$values = array(

			'background(?:-image)?:([^\n;]*?(?<!-o-)(?:linear|radial)-gradient\([^)]+\)[^\n;]*)',	// Gradients (linear, radial, repeating...)
			'transition(?:-[a-z-]+)?:([^\n;]*)',			// Animated transitions (we need to fix 'transform' values, if any.)
			'display:\h*(flex|inline-flex)\b',				// Final flexbox model declarations
			'\b(min|max)-resolution:\h*([\d.]+)(dppx|dpi)',	// Useful for responsive design
			'\brect\h*\(([^)]+)\)',							// rect() function, needs commas except in IE 6/7
			'\bcalc\h*\(',									// calc() function

		);
		foreach ($values as $val)
			$css = preg_replace_callback('~(?<!-)(' . $val . ')~', array($this, 'fix_values'), $css);

		// And now for some 'easy' rules that don't need our regex machine,
		// or custom rules that are better served individually.
		$b = we::$browser;
		$os = we::$os;
		$v = $b['version'];
		$ov = $os['version'];

		// IE 6 doesn't support min-height, but 'height' behaves the same way. If you don't use both at the same time, it should be okay.
		if ($b['ie'] && $v == 6)
			$css = preg_replace('~\bmin-height\b~', 'height', $css);

		// IE 6-9 don't support keyframes; IE 10, Firefox 16+ and Opera 12.10+ support them unprefixed, other browsers require a prefix.
		if (($b['opera'] && $v < 12.1) || ($b['firefox'] && $v < 16) || $b['webkit'])
			$css = str_replace('@keyframes ', '@' . $this->prefix . 'keyframes ', $css);

		// IE 10+ and Presto 11+ support @viewport, but prefixed. Other browsers...? No idea.
		if (($b['opera'] && $v >= 11) || ($b['ie'] && $v >= 10))
			$css = str_replace('@viewport', '@' . $this->prefix . 'viewport', $css);

		// Chrome 21-28 and Safari 7+ support the final flexbox model... But with a prefix.
		if (($b['safari'] && $v >= 7) || ($os['ios'] && $ov >= 7) || ($b['chrome'] && $v >= 21 && $v < 29))
			$css = preg_replace('~\b(order|justify-content|align-(?:content|items|self)|flex(?:-[a-z]+)?)\h*:~', $this->prefix . '$1:', $css);

		// IE 10 is a special case for flexboxing. It supports an older syntax, which we'll convert below,
		// but it's not 100% compatible, so you might want to check your CSS in an actual IE 10 browser, just in case.
		if ($b['ie'] && $v == 10)
			$css = preg_replace(
				array(
					'~\bdisplay\h*:\h*(flex|inline-flex)\b~',
					'~\bflex\h*:~',
					'~\border\h*:~',
					'~\balign-items\h*:~',
					'~\balign-self\h*:~',
					'~\balign-content\h*:~',
					'~\bjustify-content\h*:~',
					'~\bflex-direction\h*:~',
					'~\bflex-wrap\h*:\h*nowrap\b~',
					'~\bflex-wrap\h*:~',
					'~\bspace-between\b~',
					'~\bflex-(start|end)\b~',
				),
				array(
					'display:-ms-$1box',
					'-ms-flex:',
					'-ms-flex-order:',
					'-ms-flex-align:',
					'-ms-flex-item-align:',
					'-ms-flex-line-pack:',
					'-ms-flex-pack:',
					'-ms-flex-direction:',
					'-ms-flex-wrap:none',
					'-ms-flex-wrap:',
					'justify',
					'$1',
				),
				$css
			);

		// And finally, listen to the author -- you may add a prefix manually, that will be automatically turned into the current
		// browser's official prefix. e.g. add "-prefix-my-rule" and Wess will turn it into "-moz-my-rule" for Firefox users.
		$css = preg_replace('~(?<![\w-])-prefix-(?=[a-z-])~', $this->prefix, $css);
	}
}

class wess_base64 extends wess
{
	var $folder;

	public function __construct($base_folder)
	{
		$this->folder = $base_folder;
	}

	function process(&$css)
	{
		global $cssdir;

		$images = array();
		if (preg_match_all('~(?<!raw-)url\(([^)]+)\)~i', $css, $matches))
		{
			foreach ($matches[1] as $img)
				if (preg_match('~\.(gif|png|jpe?g)$~', $img, $ext))
					$images[$img] = $ext[1] == 'jpg' ? 'jpeg' : $ext[1];

			foreach ($images as $img => $img_ext)
			{
				$absolut = realpath($cssdir . '/' . $this->folder . $img);

				// Only small files should be embedded, really. We're saving on hits, not bandwidth.
				if (file_exists($absolut) && filesize($absolut) <= 3072)
				{
					$img_raw = file_get_contents($absolut);
					$img_data = 'url(data:image/' . $img_ext . ';base64,' . base64_encode($img_raw) . ')';
					$css = str_replace('url(' . $img . ')', $img_data, $css);
				}
			}
		}
	}
}
