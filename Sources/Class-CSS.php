<?php
/**
 * Wedge
 *
 * WeCSS is a pre-parser for CSS files, bringing new possibilities to CSS.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

/**
 * Uses some code and ideas from Shaun Inman's CSS Cacheer library
 * http://www.shauninman.com/archive/2008/05/30/check_out_css_cacheer
 * Also implements some ideas from Sass (http://sass-lang.com)
 */

class wecss
{
	/**
	 * The HSL to RGB and RGB to HSL functions are based on ABC algorithms from the CWI,
	 * based on 'Fundamentals of Interactive Computer Graphics' (J.D. Foley, 1982.)
	 */

	// Converts from a RGBA color to a string
	protected function color2string($r, $g, $b, $a)
	{
		$a = max(0, min(1, $a));
		$r = max(0, min(255, round($r)));
		$g = max(0, min(255, round($g)));
		$b = max(0, min(255, round($b)));

		return $a === 1 ? '#' . sprintf('%02x%02x%02x', $r, $g, $b) : "rgba($r, $g, $b, $a)";
	}

	// Converts from hue to RGB
	protected function hue2rgb($m1, $m2, $h)
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
	protected function hsl2rgb($h, $s, $l, $a)
	{
		while ($h < 0)
			$h += 360;
		$h = fmod($h, 360) / 360;
		$s = max(0, min(1, $s / 100));
		$l = max(0, min(1, $l / 100));

		$m1 = $l <= 0.5 ? $l * ($s + 1) : $l + $s - $l * $s;
		$m2 = $l * 2 - $m1;

		return array(
			'r' => wecss::hue2rgb($m1, $m2, $h + 1 / 3) * 255,
			'g' => wecss::hue2rgb($m1, $m2, $h) * 255,
			'b' => wecss::hue2rgb($m1, $m2, $h - 1 / 3) * 255,
			'a' => $a
		);
	}

	/**
	 * Converts from RGB to HSL
	 * $r/$g/$b are RGB values (0-255)
	 */
	protected function rgb2hsl($r, $g, $b, $a)
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
	protected function string2color($data)
	{
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
		preg_match('~(?:(rgb|hsl)a?\(\s*(\d+%?)\s*,\s*(\d+%?)\s*,\s*(\d+%?)(?:\s*\,\s*(\d*(?:\.\d+)?%?))?\s*\)|#([0-9a-f]{6}|[0-9a-f]{3}))~', $data, $rgb);

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

class wecss_mixin extends wecss
{
	function process(&$css)
	{
		global $context;

		$mix = $def = array();

		// Find mixin declarations, capture their tab level and stop at the first empty or unindented line.
		if (preg_match_all('~\nmixin\s+(?:{([^}]+)}\s*)?([\w-]+)(?:\(([^()]+)\))?[^\n]*\n([\t ]+)([^\n]*\n)((?:\4[\t ]*[^\n]*\n)*)~i', $css, $mixins, PREG_SET_ORDER))
		{
			// We start by building an array of mixins...
			foreach ($mixins as &$mixin)
			{
				// Remove the mixin declaration
				$css = str_replace($mixin[0], '', $css);

				if (!empty($mixin[1]) && !array_intersect(explode(',', strtolower($mixin[1])), $context['css_suffixes']))
					continue;

				// Create our mixin entry...
				$mix[$mixin[2]] = rtrim(str_replace("\n" . $mixin[4], "\n", $mixin[5] . $mixin[6]));

				// Do we have variables to set?
				if (!empty($mixin[3]) && preg_match_all('~(\$[\w-]+)\s*[:=]\s*"?([^",]+)~', $mixin[3], $variables, PREG_SET_ORDER))
				{
					foreach ($variables as $i => &$var)
					{
						$mix[$mixin[2]] = str_replace($var[1], '$%' . $i . '%', $mix[$mixin[2]]);
						$def[$mixin[2]][$i] = trim($var[2], '" ');
					}
				}
			}

			// ...And then we apply them to the CSS file.
			if (preg_match_all('~(?<=\n)(\s*)mixin\s*:\s*([\w-]+)(?:\(([^()]+)\))?~i', $css, $targets, PREG_SET_ORDER))
			{
				$repa = array();
				foreach ($targets as &$mixin)
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
							foreach ($variables as $i => &$var)
								if (!empty($var))
									$rep = str_replace('$%' . $i . '%', trim($var, '" '), $rep);
						}

						// Replace all missing variables with their default value.
						if (!empty($def[$tg]))
							$rep = preg_replace('~\$%(\d+)%~e', '$def[$tg][(int) \'$1\']', $rep);
					}
					$repa[$mixin[0]] = $mixin[1] . str_replace("\n", "\n" . $mixin[1], $rep);
				}

				// Sort the array by key length, to avoid conflicts.
				$keys = array_map('strlen', array_keys($repa));
				array_multisort($keys, SORT_DESC, $repa);

				$css = str_replace(array_keys($repa), array_values($repa), $css);
			}
		}
	}
}

// Find {%my_function%}, then execute dynamic_my_function() to replace them.
// You may add parameters to the function call: {%my_function:param%}, then
// declare your function as dynamic_my_function($match), where $match[1] is the param.
class wecss_dynamic extends wecss
{
	function process(&$css)
	{
		if (preg_match_all('~{%([a-z0-9_]+)(?::([^%]+))?%}~i', $css, $functions, PREG_SET_ORDER))
			foreach ($functions as $func)
				if (is_callable('dynamic_' . $func[1]))
					$css = preg_replace_callback('~{%' . $func[1] . '(?::([^%]+))?%}~i', 'dynamic_' . $func[1], $css);
	}
}

class wecss_var extends wecss
{
	function process(&$css)
	{
		global $css_vars, $context, $alphamix;

		// Reuse CSS variables from Wedge.
		$css_vars = isset($css_vars) ? $css_vars : array();

		// Double quotes are only required for empty strings.
		// Authors can specific conditions for the variable to be set,
		// depending on the browser, rtl, guest or member, i.e. anything
		// set in $context['css_suffixes']. Like this:
		//
		//		$variable = "rgba(2,4,6,.5)";
		//		$variable {ie6,ie7,ie8} = rgb(1,2,3);
		//
		// The only reason we're not accepting ":" in declarations is that
		// we want to be able to do this: (Check the last line carefully)
		//
		// (index.css)		$border-pos = right
		// (index.rtl.css)	$border-pos = left
		// (index.css)		.class
		//						border-$border-pos: 1px solid $border-col;

		if (preg_match_all('~^\s*(\$[\w-]+)\s*(?:{([^}]+)}\s*)?=\s*("?)(.*)\\3;?\s*$~m', $css, $matches))
		{
			foreach ($matches[0] as $i => &$dec)
			{
				$css = str_replace($dec, '', $css);
				if (empty($matches[2][$i]) || array_intersect(explode(',', strtolower($matches[2][$i])), $context['css_suffixes']))
					$css_vars[$matches[1][$i]] = rtrim($matches[4][$i], '; ');
				// We need to keep this one for later...
				if ($matches[1][$i] === '$alphamix')
					$alphamix = trim($matches[4][$i], '"');
			}

			// Sort the array by key length, to avoid conflicts.
			$keys = array_map('strlen', array_keys($css_vars));
			array_multisort($keys, SORT_DESC, $css_vars);
		}

		// Replace recursively - good for variables referencing variables. Also has a safety against endless loops.
		$left = $count = 1;
		if (!empty($css_vars))
			while ($left && $count++ < 10)
				$css = str_replace(array_keys($css_vars), array_values($css_vars), $css, $left);
	}
}

/**
 * Apply color functions to the CSS file.
 */
class wecss_color extends wecss
{
	// Transforms "gradient: rgba(1,2,3,.5)" into background-color, or the equivalent IE filter.
	// Transforms "gradient: color1, color2, [top|left]?" into linear-gradient([top|left]?, color1, color2), or the equivalent IE filter.
	// The direction parameter is optional.
	protected static function gradient_background($input)
	{
		global $browser;
		static $test_gradient_support = true, $no_gradients;

		if ($test_gradient_support)
		{
			$test_gradient_support = false;
			$no_gradients = $browser['is_ie'] && $browser['version'] <= 9;
			$no_gradients |= $browser['is_firefox'] && $browser['version'] < 3.6;
			$no_gradients |= $browser['is_opera'] && $browser['version'] < 11.1;
		}
		$bg1 = $input[2];
		$bg2 = empty($input[3]) ? $bg1 : $input[3];
		$dir = empty($input[4]) ? 'top' : $input[4];

		// IE 6, 7 and 8 will need a filter to apply the transparency effect, except for IE9. Also, IE8 can do without hasLayout.
		if ($browser['is_ie8down'] || ($browser['is_ie9'] && $bg1 != $bg2))
		{
			if (preg_match('~^#[0-9a-f]{3}$~i', $bg1))
				$bg1 = '#' . $bg1[1] . $bg1[1] . $bg1[2] . $bg1[2] . $bg1[3] . $bg1[3];
			if (preg_match('~^#[0-9a-f]{3}$~i', $bg2))
				$bg2 = '#' . $bg2[1] . $bg2[1] . $bg2[2] . $bg2[2] . $bg2[3] . $bg2[3];
			return $input[1] . 'background: none' . $input[1] . ($browser['is_ie6'] || $browser['is_ie7'] ? 'zoom: 1' . $input[1] : '') .
				'filter:progid:DXImageTransform.Microsoft.Gradient(startColorStr=' . $bg1 . ',endColorStr=' . $bg2 . ($dir == 'left' ? ',GradientType=1' : '') . ')';
		}

		// Better than nothing...
		if ($no_gradients)
			return $input[1] . 'background-color: ' . $bg1;

		$grad = 'linear-gradient(' . ($dir == 'top' ? '' : $dir . ', ') . '%1$s, %2$s)';
		if ($browser['is_opera'])
			$grad = '-o-' . $grad;
		elseif ($browser['is_gecko'])
			$grad = '-moz-' . $grad;
		elseif ($browser['is_ie10'])
			$grad = '-ms-' . $grad;
		elseif ($browser['is_webkit'])
			$grad = '-webkit-gradient(linear, 0%% 0%%, ' . ($dir == 'left' ? '100%% 0%%' : '0%% 100%%') . ', from(%1$s), to(%2$s))';

		return $input[1] . 'background-image: ' . sprintf($grad, $bg1, $bg2);
	}

	// Now, go with the actual color parsing.
	function process(&$css)
	{
		global $browser;

		$nodupes = array();

		// No need for a recursive regex, as we shouldn't have more than one level of nested brackets...
		while (preg_match_all('~(darken|lighten|desaturize|saturize|hue|complement|average|alpha|channels)\(((?:(?:rgb|hsl)a?\([^()]+\)|[^()])+)\)~i', $css, $matches))
		{
			foreach ($matches[0] as $i => &$dec)
			{
				if (isset($nodupes[$dec]))
					continue;
				$nodupes[$dec] = true;
				$code = strtolower($matches[1][$i]);
				$m = strtolower(trim($matches[2][$i]));
				if (empty($m))
					continue;

				$rgb = wecss::string2color($m);
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
				foreach ($arg as $i => &$a)
					$parg[$i] = substr($a, -1) === '%' ? ((float) substr($a, 0, -1)) / 100 : false;
				$hsl = $hsl ? $hsl : wecss::rgb2hsl($color[0], $color[1], $color[2], $color[3]);

				// This is where we run our color functions...

				if ($code === 'average' && !empty($rgb[0]))
				{
					// Extract the second color from our list. Technically, we COULD
					// have an infinite number of colors... Is it any useful, though?
					$rgb2 = wecss::string2color(ltrim(substr($m, strlen($rgb[0])), ', '));
					$color2 = $rgb2[1];
					$hsl2 = $rgb2[2] ? $rgb2[2] : wecss::rgb2hsl($rgb2[1][0], $rgb2[1][1], $rgb2[1][2], $rgb2[1][3]);
					$hsl['h'] = ($hsl['h'] + $hsl2['h']) / 2;
					$hsl['s'] = ($hsl['s'] + $hsl2['s']) / 2;
					$hsl['l'] = ($hsl['l'] + $hsl2['l']) / 2;
					$hsl['a'] = ($hsl['a'] + $hsl2['a']) / 2;
				}

				// Change alpha (transparency) level
				elseif ($code === 'alpha')
					$hsl['a'] += $parg[0] ? $hsl['a'] * $parg[0] : $arg[0];

				// Darken the color (brightness down)
				elseif ($code === 'darken')
					$hsl['l'] -= $parg[0] ? $hsl['l'] * $parg[0] : $arg[0];

				// Lighten the color (brightness up)
				elseif ($code === 'lighten')
					$hsl['l'] += $parg[0] ? $hsl['l'] * $parg[0] : $arg[0];

				// Desaturize the color (saturation down, gets color closer to grayscale)
				elseif ($code === 'desaturize')
					$hsl['s'] -= $parg[0] ? $hsl['s'] * $parg[0] : $arg[0];

				// Saturize the color (saturation up, gets color further away from grayscale)
				elseif ($code === 'saturize')
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
						$color = wecss::hsl2rgb($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
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

				$nc = $nc ? $nc : wecss::hsl2rgb($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
				$css = str_replace($dec, wecss::color2string($nc['r'], $nc['g'], $nc['b'], $nc['a']), $css);
			}
		}

		$colval = '((?:rgb|hsl)a?\([^()]+\)|[^()\n,]+)';
		$css = preg_replace_callback('~(\n[\t ]*)gradient\s*:\s*' . $colval . '(?:\s*,\s*' . $colval . ')?(?:\s*,\s*(top|left))?~i', 'wecss_color::gradient_background', $css);
		$css = str_replace('alpha_ms_wedge', 'alpha', $css);
	}
}

// Miscellaneous helper functions...
class wecss_func extends wecss
{
	function process(&$css)
	{
		if (!preg_match_all('~(width|height)\(([^)]+)\)~i', $css, $matches))
			return;

		$done = array();
		foreach ($matches[2] as $i => $file)
		{
			if (isset($done[$file]))
				continue;
			$done[$file] = true;

			list ($width, $height) = getimagesize($file);
			$css = str_replace(array('width(' . $file . ')', 'height(' . $file . ')'), array($width, $height), $css);
		}
	}
}

class wecss_nesting extends wecss
{
	var $rules, $props;

	// Sort the bases array by the first argument's length.
	private static function lensort($a, $b)
	{
		return strlen($a[0]) < strlen($b[0]);
	}

	private static function indentation($a)
	{
		return strlen($a[1]) . ':';
	}

	function process(&$css)
	{
		global $browser;

		/******************************************************************************
		 Process nested selectors
		 ******************************************************************************/

		// Transform the CSS into a tree
		$tree = str_replace('"', '#wedge-quote#', trim($css));

		// Does this file use the regular CSS syntax?
		$css_syntax = strpos($tree, "{\n") !== false && strpos($tree, "\n}") !== false;
		if (!$css_syntax)
		{
			// Nope? Then let's have fun with our simplified syntax.

			// WARNING: make sure to only use always tabs OR always spaces, and do proper
			// structure nesting. Otherwise, the file won't parse properly.
			// You must conform. It is my sworn duty to see that you do conform.

			$tree = preg_replace("~\n\s*\n~", "\n", $tree); // Delete blank lines
			$tree = preg_replace_callback('~^([\t ]*)~m', 'wecss_nesting::indentation', $tree);
			$branches = explode("\n", $tree);
			$level = 0;
			$tree = '';
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
			while ($level > 0)
			{
				$tree .= '}';
				$level -= $indent;
			}
		}
		$tree = preg_replace('~^(\s*)(@(?:import|charset)\s+.*?);$~mi', '$1<rule selector="$2"></rule>', $tree); // Transform single-line @rules into selectors
		$tree = preg_replace('~([a-z-, ]+)\s*:(?!//)\s*([^;}{' . ($css_syntax ? '' : '\n') . ']+?);*\s*(?=[\n}])~i', '<property name="$1" value="$2">', $tree); // Transform properties
		$tree = preg_replace('~^(\s*)([+>&#*@:.a-z][^{]*?)\s*{~mi', '$1<rule selector="$2">', $tree); // Transform selectors

		$tree = preg_replace(array('~ {2,}~'), array(' '), $tree); // Remove extra spaces
		$tree = str_replace(array('}', "\n"), array('</rule>', "\n\t"), $tree); // Close rules and indent everything one tab

		 // Parse the XML into a crawlable DOM
		$this->pierce($tree);

		/******************************************************************************
		 Rebuild parsed CSS
		 ******************************************************************************/

		$css = $standard_nest = '';
		$bases = $removals = $unextends = array();

		// 'reset' keyword: remove earlier occurrences of a selector.
		// e.g.: ".class reset" -> removes all previous ".class" definitions
		foreach ($this->rules as $n => &$node)
		{
			if (strpos($node['selector'], ' reset') !== false)
			{
				preg_match_all('~((?<![a-z])[abipqsu]|[+>&#*@:.a-z][^{};,\n"]+)\s+reset\b~i', $node['selector'], $matches, PREG_SET_ORDER);
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
							$node2['selector'] = trim(preg_replace('~(?:^|,)[^,]*' . $quoted . '[^,]*(?:,|$)~m', ',', $node2['selector']), "\x00..\x1F,");
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

		// Replace ".class extends .original_class, .class2 extends .other_class" with ".class, .class2"
		foreach ($this->rules as $n => &$node)
		{
			// '@remove' keyword: remove properties as specified from the entire CSS file.
			// e.g.: "@remove (line break) (tab) background: #fff" -> removes all "background: #fff" from the file
			if ($node['selector'] == '@remove')
			{
				foreach ($node['props'] as $remove)
					$removals[$remove['name'] . ':' . $remove['value']] = true;
				unset($this->rules[$n]);
				continue;
			}

			// 'unextends' keyword: reset specified inheritance.
			// e.g.: ".class unextends .orig "-> cancels any earlier ".class extends .orig"
			if (strpos($node['selector'], ' unextends') !== false)
			{
				preg_match_all('~((?<![a-z])[abipqsu]|[+>&#*@:.a-z][^{};,\n"]+)\s+unextends\b~i', $node['selector'], $matches, PREG_SET_ORDER);
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
				// any selectors declared alongside them. If you still want these selectors to inherit something, do it manually in *.ie6.css!
				if ($browser['is_ie6'] && strpos($node['selector'], '>') !== false)
					$node['selector'] = ' ';
				$node['selector'] = str_replace('#wedge-quote#', '"', $node['selector']);
				preg_match_all('~((?<![a-z])[abipqsu]|[+>&#*@:.a-z][^{};,\n"]+)[\t ]+extends[\t ]+("[^\n{"]+"|[^\n,{"]+)~i', $node['selector'], $matches, PREG_SET_ORDER);
				foreach ($matches as $m)
				{
					$save_selector = $node['selector'];
					$node['selector'] = $m[1];
					$path = $this->parse_ancestors($this->get_ancestors($node));
					// In case we extend directly from a parent's property, make sure to keep only one parent if we have several.
					if (strpos($m[2], '&') !== false)
					{
						$parent = $this->parse_ancestors($this->get_ancestors($this->rules[$node['parent']]));
						if (strpos($parent, ',') !== false)
							$parent = substr($parent, 0, strpos($parent, ','));
						$m[2] = str_replace('&', $parent, $m[2]);
					}
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
				$selectors = preg_split('/,\s*/', $this->rules[$node['parent']]['selector']);
				foreach ($selectors as &$here)
				{
					$parent = empty($this->rules[$node['parent']]['parent']) ? array() : $this->get_ancestors($this->rules[$this->rules[$node['parent']]['parent']]);
					$path = $this->parse_ancestors(array_merge((array) $here, $parent));
					if (strpos($node['value'], '&') !== false)
					{
						if (strpos($path, ',') !== false)
							$path = substr($path, 0, strpos($path, ','));
						$node['value'] = str_replace('&', $path, $node['value']);
					}
					$targets = array_map('trim', explode(',', $node['value']));
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

		foreach ($bases as $i => &$base)
		{
			if (isset($unextends[$base[2]]) && $base[3] < $unextends[$base[2]])
				unset($bases[$i]);
			// Do we have multiple selectors to extend?
			elseif (strpos($base[2], ',') !== false)
			{
				$selectors = array_map('trim', explode(',', $base[2]));
				$base[2] = $selectors[0];
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

		// Sort the bases array by the first argument's length.
		usort($bases, 'wecss_nesting::lensort');
		$prop = 'property';
		$alpha = array_flip(array_merge(range('a', 'z'), range('A', 'Z')));

		// Do the proper nesting
		foreach ($this->rules as &$node)
		{
			// Is this rule actually an at-rule?
			if ($node['selector'][0] === '@')
			{
				if (stripos($node['selector'], '@import') === 0 || stripos($node['selector'], '@charset') === 0)
				{
					$css .= $node['selector'] . ';';
					continue;
				}
				// @todo: should this only check for @media and @-*-keyframes, or actually give the same treatment to all @ commands?
				if (stripos($node['selector'], '@media') === 0 || preg_match('~@(?:-[a-z]+-)?keyframes~i', $node['selector']))
				{
					$standard_nest = $node['selector'];
					$css .= $node['selector'] . ' {';
					continue;
				}
			}

			$selector = str_replace('&gt;', '>', $this->parse_ancestors($this->get_ancestors($node)));
			$selectors = $done = array();
			$changed = true;

			while ($changed)
			{
				$done_temp = array();
				$changed = false;
				foreach ($bases as $i => &$base)
				{
					// We have a selector like ".class, #id > div a" and we want to know if it has the base "#id > div" in it
					if (strpos($selector, $base[0]) !== false)
					{
						// Note: this will fail on any strings with commas. If you have a good reason to use them, please share.
						if (empty($selectors))
							$selectors = explode(',', $selector);
						$beginning = isset($alpha[$base[0][0]]) ? '(?<![a-z0-9_-])' : '';

						foreach ($selectors as &$snippet)
						{
							if (!isset($done[$snippet]) && preg_match('~' . $beginning . '(' . $base[1] . ')(?![a-z0-9_-]|.*\s+final\b)~i', $snippet))
							{
								// And our magic trick happens here. Then we restart the process to handle inherited extends.
								$selector .= ', ' . str_replace($base[0], $base[2], $snippet);
								$done_temp[$snippet] = true;
								$changed = true;
							}
						}
					}
				}
				if ($changed)
				{
					$selectors = explode(',', $selector);
					$done = array_merge($done, $done_temp);
				}
			}

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
				// Is it a @removed property?
				if (isset($removals[$prop['name'] . ':' . $prop['value']]))
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
	}

	private function unset_recursive(&$n)
	{
		foreach ($this->rules[$n]['props'] as $n2 => $dummy)
			unset($this->rules[$n2], $this->props[$n2]);
		foreach ($this->rules[$n]['children'] as $n2)
			$this->unset_recursive($n2);
		unset($this->rules[$n]);
	}

	private function get_ancestors(&$node)
	{
		if (empty($node['parent']))
			return (array) $node['selector'];

		return array_merge((array) $node['selector'], $this->get_ancestors($this->rules[$node['parent']]));
	}

	private function parse_ancestors($ancestors = array())
	{
		$growth = array();
		foreach ($ancestors as $selector)
		{
			$these = preg_split('/,\s*/', $selector);
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
		return implode(',', $growth);
	}

	private function pierce(&$data)
	{
		preg_match_all('~<(/?)([a-z]+)\s*(?:name="([^"]*)"\s*)?(?:(?:value|selector)="([^"]*)")?[^>]*>~s', $data, $tags, PREG_SET_ORDER);

		$id = 1;
		$parent = array(0);
		$level = 0;
		$rules = $props = array();
		$rule = 'rule';
		$prop = 'property';

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
				elseif ($tag[2] === $prop)
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
class wecss_math extends wecss
{
	function process(&$css)
	{
		if (!preg_match_all('~math\(((?:[\t ()\d.+/*%-]|(?<=\d)(em|ex|px|pt|pc|rem|deg|rad|grad|in|cm|mm|ms|s|hz|khz)|\b(?:round|ceil|floor|abs|fmod|min|max|rand)\()+)\)~i', $css, $matches))
			return;

		$done = array();
		foreach ($matches[1] as $i => $math)
		{
			if (isset($done[$math]))
				continue;
			$done[$math] = true;

			if (isset($matches[2][$i]))
				$math = preg_replace('~(?<=\d)' . $matches[2][$i] . '~', '', $math);

			$css = str_replace($matches[0][$i], eval('return (' . $math . ');') . $matches[2][$i], $css);
		}
	}
}

// IE 6/7/8 don't support rgba/hsla, so we're replacing them with regular rgb colors mixed with an alpha variable.
// The only exception is the gradient function. It accepts a #aarrggbb value, and only that, so IE6/7/8/9 get the treatment.
class wecss_rgba extends wecss
{
	// Converts from a string (possibly rgba) value to a rgb string
	private static function rgba2rgb($input)
	{
		global $alphamix;
		static $cache = array();

		if (isset($cache[$input[0]]))
			return $cache[$input[0]];

		$str = wecss::string2color($input[2]);
		if (empty($str))
			return $cache[$input[0]] = 'red';
		list ($r, $g, $b, $a) = $str[1] ? $str[1] : wecss::hsl2rgb($str[2]['h'], $str[2]['s'], $str[2]['l'], $str[2]['a']);

		if ($a == 1)
			return $cache[$input[0]] = $input[1] . '#' . sprintf('%02x%02x%02x', $r, $g, $b);
		if (!empty($input[1]))
			return $cache[$input[0]] = $input[1] . '#' . sprintf('%02x%02x%02x%02x', round($a * 255), $r, $g, $b);

		// We're going to assume the matte color is white, otherwise, well, too bad.
		if (isset($alphamix) && !is_array($alphamix))
		{
			$rgb = wecss::string2color($alphamix);
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

		return $cache[$input[0]] = '#' . sprintf('%02x%02x%02x', $r, $g, $b);
	}

	function process(&$css)
	{
		global $browser;

		$ie_sucks = $browser['version'] < 10;
		$css = preg_replace_callback('~(colorstr=)' . ($ie_sucks ? '?' : '') . '((?:rgba' . ($ie_sucks ? '?' : '') . '|hsla?)\([^()]*\))~i', 'wecss_rgba::rgba2rgb', $css);
	}
}

class wecss_base64 extends wecss
{
	function process(&$css)
	{
		global $boarddir;

		$images = array();
		if (preg_match_all('~url\(([^)]+)\)~i', $css, $matches))
		{
			foreach ($matches[1] as $img)
				if (preg_match('~\.(gif|png|jpe?g)$~', $img, $ext))
					$images[$img] = $ext[1] == 'jpg' ? 'jpeg' : $ext[1];

			foreach ($images as $img => $img_ext)
			{
				$absolut = $boarddir . substr($img, 2);

				// Only small files should be embedded, really. We're saving on hits, not bandwidth.
				if (file_exists($absolut) && filesize($absolut) <= 4096)
				{
					$img_raw = file_get_contents($absolut);
					$img_data = 'url(data:image/' . $img_ext . ';base64,' . base64_encode($img_raw) . ')';
					$css = str_replace('url(' . $img . ')', $img_data, $css);
				}
			}
		}
	}
}

?>