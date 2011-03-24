<?php
/**
 * CSS file parser, written by Nao for Wedge. (c) 2011 Wedgeward
 * Released under the Wedge license.
 *
 * Uses some code and ideas from Shaun Inman's CSS Cacheer library
 * http://www.shauninman.com/archive/2008/05/30/check_out_css_cacheer
 * Also implements concepts and ideas from Sass (http://sass-lang.com)
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

				if (!empty($mixin[1]) && !array_intersect(explode(',', strtolower($mixin[1])), $context['css_generic_files']))
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
		// set in $context['css_generic_files']. Like this:
		//
		//		$variable = "rgba(2,4,6,.5)";
		//		$variable {ie6,ie7,ie8} = rgb(1,2,3);
		//
		// The only reason we're not accepting ":" in declarations is that
		// we want to be able to do this: (Check the last line carefully)
		//
		// (index.css)	$border-pos = right
		// (rtl.css)	$border-pos = left
		// (index.css)	.class
		//					border-$border-pos: 1px solid $border-col;

		if (preg_match_all('~^\s*(\$[\w-]+)\s*(?:{([^}]+)}\s*)?=\s*(.*);?$~m', $css, $matches))
		{
			foreach ($matches[0] as $i => &$dec)
			{
				$css = str_replace($dec, '', $css);
				if (empty($matches[2][$i]) || array_intersect(explode(',', strtolower($matches[2][$i])), $context['css_generic_files']))
					$css_vars[$matches[1][$i]] = trim(rtrim($matches[3][$i], '; '), '"');
				// We need to keep this one for later...
				if ($matches[1][$i] === '$alphamix')
					$alphamix = trim($matches[3][$i], '"');
			}

			// Sort the array by key length, to avoid conflicts.
			$keys = array_map('strlen', array_keys($css_vars));
			array_multisort($keys, SORT_DESC, $css_vars);
		}

		// Replace recursively - good for variables referencing variables
		$count = 1;
		if (!empty($css_vars))
			while ($count)
				$css = str_replace(array_keys($css_vars), array_values($css_vars), $css, $count);
	}
}

/**
 * Apply color functions to the CSS file.
 */
class wecss_func extends wecss
{
	// Transform "gradient-background: rgba(1,2,3,.5)" into background-color, or the equivalent IE filter.
	// You can add a second parameter for an actual gradient effect, and a third: top (vertical gradient) or left (horizontal.)
	protected function gradient_background($input)
	{
		global $browser;
		static $test_gradient_support = true, $no_gradients;

		$is_ie = $browser['is_ie8down'] || $browser['is_ie9'];
		if ($test_gradient_support)
		{
			$test_gradient_support = false;
			$no_gradients = $browser['is_ie8down'] || $browser['is_ie9'];
			if ($browser['is_firefox'] && preg_match('~Firefox/([\d\.]+)~', $browser['ua'], $version))
				$no_gradients |= (float) $version[1] < 3.6;
			if ($browser['is_opera'] && preg_match('~Version/([\d\.]+)~', $browser['ua'], $version))
				$no_gradients |= (float) $version[1] < 11.1;
		}
		$bg1 = $input[2];
		$bg2 = empty($input[3]) ? $bg1 : $input[3];
		$dir = empty($input[4]) ? 'top' : $input[4];

		// If you're not specifying a gradient shade, IE 9 won't need the filter.
		if ($browser['is_ie8down'] && $bg1 != $bg2)
			return $input[1] . 'background: transparent' . $input[1] . (!$browser['is_ie8'] ? 'zoom: 1' . $input[1] .
				'filter:progid:DXImageTransform.Microsoft.Gradient(startColorStr=' . $bg1 . ',endColorStr=' . $bg2 . ($dir == 'left' ? ',GradientType=1' : '') . ')' :
				'-ms-filter:"progid:DXImageTransform.Microsoft.Gradient(startColorStr=' . $bg1 . ',endColorStr=' . $bg2 . ($dir == 'left' ? ',GradientType=1' : '') . ')"');

		// Better than nothing...
		if ($no_gradients)
			return $input[1] . 'background-color: ' . $bg1;

		$grad = 'linear-gradient(' . $dir . ', %1$s, %2$s)';
		if ($browser['is_opera'])
			$grad = '-o-' . $grad;
		elseif ($browser['is_gecko'])
			$grad = '-moz-' . $grad;
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
		while (preg_match_all('~(darken|lighten|desaturize|saturize|hue|complement|alpha|channels)\(((?:(?:rgb|hsl)a?\([^()]+\)|[^()])+)\)~i', $css, $matches))
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

				// Run our functions
				if ($code === 'alpha')
					$hsl['a'] += $parg[0] ? $hsl['a'] * $parg[0] : $arg[0];

				elseif ($code === 'darken')
					$hsl['l'] -= $parg[0] ? $hsl['l'] * $parg[0] : $arg[0];

				elseif ($code === 'lighten')
					$hsl['l'] += $parg[0] ? $hsl['l'] * $parg[0] : $arg[0];

				elseif ($code === 'desaturize')
					$hsl['s'] -= $parg[0] ? $hsl['s'] * $parg[0] : $arg[0];

				elseif ($code === 'saturize')
					$hsl['s'] += $parg[0] ? $hsl['s'] * $parg[0] : $arg[0];

				elseif ($code === 'hue')
					$hsl['h'] += $parg[0] ? $parg[0] * 360 : $arg[0];

				elseif ($code === 'complement')
					$hsl['h'] += 180;

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
					$hook = call_hook('css_color', array(&$nc, &$hsl, &$color, &$arg, &$parg, &$dec));

					// Set $nc or $hsl, and then return true to tell Wedge you were there.
					if (empty($hook))
						continue;
				}

				$nc = $nc ? $nc : wecss::hsl2rgb($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
				$css = str_replace($dec, wecss::color2string($nc['r'], $nc['g'], $nc['b'], $nc['a']), $css);
			}
		}

		$colval = '((?:rgb|hsl)a?\([^()]+\)|[^()]+)';
		$css = preg_replace_callback('~(\n[\t ]*)gradient-background\s*:\s*' . $colval . '(?:\s*,\s*' . $colval . ')?(?:\s*,\s*(top|left))?~i', array($this, 'gradient_background'), $css);
		$css = str_replace('alpha_ms_wedge', 'alpha', $css);
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

		// Transform the CSS into XML
		$xml = str_replace('"', '#wedge-quote#', trim($css));

		// Does this file use the regular CSS syntax?
		$css_syntax = strpos($xml, "{\n") !== false && strpos($xml, "\n}") !== false;
		if (!$css_syntax)
		{
			// Nope? Then let's have fun with our simplified syntax.
			$xml = preg_replace("~\n\s*\n~", "\n", $xml); // Delete blank lines
			$xml = preg_replace_callback('~^([\t ]*)~m', 'wecss_nesting::indentation', $xml);
			$tree = explode("\n", $xml);
			$level = 0;
			$xml = '';
			foreach ($tree as &$line)
			{
				$l = explode(':', $line, 2);
				if (!isset($indent) && !empty($l[0]))
					$indent = $l[0];
				if (!isset($indent))
				{
					$xml .= $l[1] . "\n";
					continue;
				}
				if ($level == $l[0] && substr($ex_string, -1) !== ',')
					$xml .= ";\n";
				elseif ($level < $l[0])
					$xml .= " {\n";
				else
				{
					while ($level > $l[0])
					{
						$xml .= "}\n";
						$level -= $indent;
					}
				}

				$level = $l[0];
				$xml .= $l[1];
				$ex_string = $l[1];
			}
			while ($level > 0)
			{
				$xml .= '}';
				$level -= $indent;
			}
		}

		$xml = preg_replace('~([a-z-, ]+)\s*:\s*([^;}{' . ($css_syntax ? '' : '\n') . ']+?);*\s*(?=[\n}])~i', '<property name="$1" value="$2" />', $xml); // Transform properties
		$xml = preg_replace('~^(\s*)([+>&#*@:.a-z][^{]*?)\s*\{~mi', '$1<rule selector="$2">', $xml); // Transform selectors
		$xml = preg_replace(array('~ {2,}~'), array(' '), $xml); // Remove extra spaces
		$xml = str_replace(array('}', "\n"), array('</rule>', "\n\t"), $xml); // Close rules and indent everything one tab
		$xml = '<?xml version="1.0"?'.">\n<css>\n\t$xml\n</css>\n"; // Tie it all up with a bow

		 // Parse the XML into a crawlable DOM
		$this->pierce($xml);

		/******************************************************************************
		 Rebuild parsed CSS
		 ******************************************************************************/
		$css = $standard_nest = '';

		$bases = array();
		// Replace ".class extends .original_class, .class2 extends .other_class" with ".class, .class2"
		foreach ($this->rules as &$node)
		{
			// A quick hack to avoid extending selectors with a direct child selector if we're in IE6 - it would cancel ALL extends in the batch.
			// !!! Need to figure out an alternative solution redirecting these selectors to jQuery ($('something > something').addClass('.ie6_emulate_xxx'))
			if (strpos($node['selector'], 'extends') !== false && (!$browser['is_ie6'] || strpos($node['selector'], '>') === false))
			{
				preg_match_all('~((?:(?<![a-z])[abipqsu]|[+>&#*@:.a-z][^{};,\n"]+))[\t ]+extends[\t ]+([^\n,{"]+)~i', $node['selector'], $matches, PREG_SET_ORDER);
				foreach ($matches as $m)
				{
					$save_selector = $node['selector'];
					$node['selector'] = $m[1];
					$path = $this->parseAncestorSelectors($this->getAncestorSelectors($node));
					if (strpos($m[2], '&') !== false)
					{
						$parent = isset($parent) ? $parent : $this->parseAncestorSelectors($this->getAncestorSelectors($this->rules[$node['parent']]));
						$m[2] = str_replace('&', $parent, $m[2]);
					}

					$bases[] = array(
						rtrim($m[2]), // Add to this class in the tree...
						preg_quote(rtrim($m[2])),
						$path // ...The current selector
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
					$parent = empty($this->rules[$node['parent']]['parent']) ? array() : $this->getAncestorSelectors($this->rules[$this->rules[$node['parent']]['parent']]);
					$path = $this->parseAncestorSelectors(array_merge((array) $here, $parent));
					$target = str_replace('&', $path, $node['value']);
					$bases[] = array(
						$target, // Add to this class in the tree...
						preg_quote($target),
						$path // ...The current selector
					);
				}
				if (isset($this->rules[$node['parent']]))
					unset($this->rules[$node['parent']]['props'][$node['id']]);
				unset($this->props[$node['id']], $node);
			}
		}

		// Sort the bases array by the first argument's length.
		usort($bases, 'wecss_nesting::lensort');
		$prop = 'property';
		$alpha = array_flip(array_merge(range('a', 'z'), range('A', 'Z')));

		// Do the proper nesting
		foreach ($this->rules as &$node)
		{
			if ($node['selector'][0] === '@' && (strpos($node['selector'], '@media') === 0 || preg_match('~@(?:-[a-z]+-)?keyframes~i', $node['selector'])))
			{
				$standard_nest = $node['selector'];
				$css .= $node['selector'] . ' {';
				continue;
			}

			$selector = str_replace('&gt;', '>', $this->parseAncestorSelectors($this->getAncestorSelectors($node)));
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
						// !!! This will fail on any strings with commas. If you have a good reason to use them, please share.
						if (empty($selectors))
							$selectors = explode(',', $selector);
						$beginning = isset($alpha[$base[0][0]]) ? '(?<!\w)' : '';

						foreach ($selectors as &$snippet)
						{
							if (!isset($done[$snippet]) && preg_match('~' . $beginning . '(' . $base[1] . ')(?!\w|.*[\t ]+final(?:\s|$))~i', $snippet))
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
				if (!strpos($prop['name'], ','))
					$css .= $prop['name'] . ': ' . $prop['value'] . ';';
				else
					foreach (explode(',', $prop['name']) as $names)
						$css .= $names . ': ' . $prop['value'] . ';';
			}

			$css .= '}';
		}

		if (!empty($standard_nest))
			$css .= '}';
	}

	private function getAncestorSelectors(&$node)
	{
		if (empty($node['parent']))
			return (array) $node['selector'];

		return array_merge((array) $node['selector'], $this->getAncestorSelectors($this->rules[$node['parent']]));
	}

	private function parseAncestorSelectors($ancestors = array())
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

		foreach ($tags as &$tag)
		{
			if (empty($tag[1]))
			{
				if ($tag[2] === $rule)
				{
					$rules[$id] = array(
						'selector' => $tag[4],
						'parent' => $parent[$level],
						'props' => array(),
					);
					$parent[++$level] = $id++;
				}
				elseif ($tag[2] === $prop)
				{
					$props[$id++] = array(
						'name' => $tag[3],
						'value' => $tag[4],
						'id' => $id - 1,
						'parent' => $parent[$level],
					);
				}
			}
			else
				$level--;
		}

		foreach ($props as $id => &$node)
			$rules[$node['parent']]['props'][$id] =& $node;

		$this->rules =& $rules;
		$this->props =& $props;
	}
}

class wecss_math extends wecss
{
	function process(&$css)
	{
		if (!preg_match_all('~math\(((?:[\t ()\d.+/*%-]|(?<=\d)(?:em|px|pt)|\b(?:round|ceil|floor|abs|fmod)\()+)\)~i', $css, $matches))
			return;

		$done = array();
		foreach ($matches[1] as $i => $math)
		{
			if (isset($done[$math]))
				continue;
			$done[$math] = true;

			$em = strpos($math, 'em') ? 1 : 0;
			$px = strpos($math, 'px') ? 1 : 0;
			$pt = strpos($math, 'pt') ? 1 : 0;

			// Are we mixing units? Nah. Write the routine yourself. Have fun.
			if ($em + $px + $pt > 1)
				continue;

			if ($em | $px | $pt)
				$math = str_replace(array('em', 'px', 'pt'), '', $math);

			$css = str_replace($matches[0][$i], eval('return (' . $math . ');') . ($em ? 'em' : ($px ? 'px' : ($pt ? 'pt' : ''))), $css);
		}
	}
}

// IE 6/7/8 don't support rgba/hsla, so we're replacing them with regular rgb colors mixed with an alpha variable.
// The only exception is the gradient function, because it accepts a #aarrggbb value.
class wecss_rgba extends wecss
{
	var $cache;

	// Converts from a string (possibly rgba) value to a rgb string
	private function rgba2rgb($input)
	{
		global $alphamix;

		if (isset($this->cache[$input[0]]))
			return $this->cache[$input[0]];

		$str = wecss::string2color($input[2]);
		if (empty($str))
			return $this->cache[$input[0]] = 'red';
		list ($r, $g, $b, $a) = $str[1] ? $str[1] : wecss::hsl2rgb($str[2]['h'], $str[2]['s'], $str[2]['l'], $str[2]['a']);

		if ($a == 1)
			return $this->cache[$input[0]] = $input[1] . '#' . sprintf('%02x%02x%02x', $r, $g, $b);
		if (!empty($input[1]))
			return $this->cache[$input[0]] = $input[1] . '#' . sprintf('%02x%02x%02x%02x', round($a * 255), $r, $g, $b);

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

		return $this->cache[$input[0]] = '#' . sprintf('%02x%02x%02x', $r, $g, $b);
	}

	function process(&$css)
	{
		$this->cache = array();
		$css = preg_replace_callback('~(colorstr=)?((?:rgba|hsla?)\([^()]*\))~i', array($this, 'rgba2rgb'), $css);
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
		$css = str_replace('url-no-base64(', 'url(', $css);
	}
}
