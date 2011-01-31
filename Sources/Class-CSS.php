<?php
/**
 * CSS file parser, written by Nao for Wedge.
 * Partly based on the CSS Cacheer library written by Shaun Inman
 * http://www.shauninman.com/archive/2008/05/30/check_out_css_cacheer
 * The Wedge version merges all classes together, adds features and fixes bugs.
 */

class CSSCache
{
	function process(&$css) {}
}

class CSS_ServerImport extends CSSCache
{
	function process(&$css)
	{
		global $relative_file, $relative_dir;

		$imported = array($relative_file);
		$context = $relative_dir;

		while (preg_match_all('#@server\s+import\s+url\(([^\)]+)+\);#i', $css, $matches))
		{
			foreach($matches[1] as $i => $include)
			{
				$include = preg_replace('#^("|\')|("|\')$#', '', $include);

				// import each file once, only import css
				if (!in_array($include, $imported) && substr($include, -3) == 'css')
				{
					$imported[] = $include;
					if (file_exists($include))
					{
						$include_css = file_get_contents($include);
						$css = str_replace($matches[0][$i], $include_css, $css);
					}
					else
						$css .= "\r\nerror { -si-missing: url('{$include}'); }";
				}
				$css = str_replace($matches[0][$i], '', $css);
			}
		}
	}
}

class CSS_Var extends CSSCache
{
	function process(&$css)
	{
		global $css_vars, $context;

		// Reuse CSS variables from Wedge or parent CSS files.
		$css_vars = isset($css_vars) ? $css_vars : array();

		// Double quotes are only required for empty strings.
		// Authors can specific conditions for the variable to be set,
		// depending on the browser, rtl, guest or member, i.e. anything
		// set in $context['css_generic_files']. Like this:
		//
		//		$variable = "rgba(2,4,6,.5)";
		//		$variable {ie6,ie7,ie8} = rgb(1,2,3);

		if (preg_match_all('~^\s*(\$[\w-]+)\s*(?:{([^}]+)}\s*)?=\s*([^;]+);[\r\n]?~m', $css, $matches))
		{
			foreach ($matches[0] as $i => &$dec)
			{
				$css = str_replace($dec, '', $css);
				if (empty($matches[2][$i]) || array_intersect(explode(',', strtolower($matches[2][$i])), $context['css_generic_files']))
					$css_vars[$matches[1][$i]] = trim($matches[3][$i], '"');
			}

			// Sort the updated array by key length, to avoid conflicts.
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
 * Various functions for the CSS parser. Currently only color-related.
 *
 * The HSL to RGB and RGB to HSL functions are based on ABC algorithms from the CWI,
 * based on 'Fundamentals of Interactive Computer Graphics' (J.D. Foley, 1982.)
 *
 * The rest isn't as good, but it's mine. Ben oui.
 */
class CSS_Func extends CSSCache
{
	private function rgb_output($r, $g, $b, $a)
	{
		global $browser;

		$r = max(0, min(255, round($r)));
		$g = max(0, min(255, round($g)));
		$b = max(0, min(255, round($b)));
		$a = max(0, min(1, $a));

		return $a === 1 || $browser['is_ie8down'] ?
			'#' . sprintf('%02x%02x%02x', $r, $g, $b) : "rgba($r, $g, $b, $a)";
	}

	// Converts from hue to RGB
	private function hue2rgb($m1, $m2, $h)
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
	private function hsl2rgb($h, $s, $l, $a)
	{
		while ($h < 0)
			$h += 360;
		$h = fmod($h, 360) / 360;
		$s = max(0, min(1, $s / 100));
		$l = max(0, min(1, $l / 100));

		$m1 = $l <= 0.5 ? $l * ($s + 1) : $l + $s - $l * $s;
		$m2 = $l * 2 - $m1;

		return array(
			'r' => $this->hue2rgb($m1, $m2, $h + 1 / 3) * 255,
			'g' => $this->hue2rgb($m1, $m2, $h) * 255,
			'b' => $this->hue2rgb($m1, $m2, $h - 1 / 3) * 255,
			'a' => $a
		);
	}

	/**
	 * Converts from RGB to HSL
	 * $r/$g/$b are RGB values (0-255)
	 */
	private function rgb2hsl($r, $g, $b, $a)
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

	function process(&$css)
	{
		global $browser;

		$nodupes = array();
		$colors = array(
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

		// A quick but relatively elegant hack to allow replacing rgba, hsl and hsla
		// calls to pure rgb crap in IE 6/7/8, by wrapping them around a dummy function.
		if ($browser['is_ie8down'])
			$css = preg_replace('~(?:rgba|hsla?)\([^\(\)]*\)~i', 'channels($1,0,0,0,0)', $css);

		// No need for a recursive regex, as we shouldn't have more than one level of nested brackets...
		while (preg_match_all('~(darken|lighten|desaturize|saturize|hue|alpha|channels)\(((?:[^\(\)]|(?:rgb|hsl)a?\([^\(\)]*\))+)\)~i', $css, $matches))
		{
			foreach ($matches[0] as $i => &$dec)
			{
				if (isset($nodupes[$dec]))
					continue;
				$nodupes[$dec] = true;
				$code = $matches[1][$i];
				$m = strtolower(trim($matches[2][$i]));
				if (empty($m))
					continue;

				// Extract color data
				preg_match('~(?:(rgb|hsl)a?\(\s*(\d+%?)\s*,\s*(\d+%?)\s*,\s*(\d+%?)(?:\s*\,\s*(\d*(?:\.\d+)?%?))?\s*\)|#([0-9a-f]{6}|[0-9a-f]{3}))~', $m, $rgb);
				if (empty($rgb[0]))
				{
					// Syntax error? We just replace with red. Otherwise we'll end up in an infinite loop.
					$css = str_replace($dec, 'red', $css);
					continue;
				}

				$color = $hsl = $nc = 0;
				if (isset($colors[$m]))
					$color = array(hexdec(substr($colors[$m], 0, 2)), hexdec(substr($colors[$m], 2, 2)), hexdec(substr($colors[$m], -2)), 1);
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

				$arg = explode(',', substr($m, strlen($rgb[0])));
				$parg = array();
				while ($arg && $arg[0] === '')
					array_shift($arg);

				$arg[0] = isset($arg[0]) ? $arg[0] : 5;
				if ($code === 'channels' && !isset($arg[3]))
					for ($i = 1; $i < 3; $i++)
						$arg[$i] = isset($arg[$i]) ? $arg[$i] : 0;
				foreach ($arg as $i => &$a)
					$parg[$i] = substr($a, -1) === '%' ? ((float) substr($a, 0, -1)) / 100 : false;
				$hsl = $hsl ? $hsl : $this->rgb2hsl($color[0], $color[1], $color[2], $color[3]);

				// Run our functions
				if ($code === 'alpha')
					$hsl['a'] += $parg[0] ? $hsl['a'] * $parg[0] : $arg[0];

				elseif ($code === 'darken')
					$hsl['l'] -= $parg[0] ? $hsl['l'] * $parg[0] : $arg[0];

				elseif ($code === 'lighten')
					$hsl['l'] =+ $parg[0] ? $hsl['l'] * $parg[0] : $arg[0];

				elseif ($code === 'desaturize')
					$hsl['s'] -= $parg[0] ? $hsl['s'] * $parg[0] : $arg[0];

				elseif ($code === 'saturize')
					$hsl['s'] += $parg[0] ? $hsl['s'] * $parg[0] : $arg[0];

				elseif ($code === 'hue')
					$hsl['h'] += $parg[0] ? $parg[0] * 360 : $arg[0];

				elseif ($code === 'channels')
				{
					if ($color === 0)
						$color = $this->hsl2rgb($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
					$nc = array(
						'r' => $color[0] + ($parg[0] ? $color[0] * $parg[0] : $arg[0]),
						'g' => $color[1] + ($parg[1] ? $color[1] * $parg[1] : $arg[1]),
						'b' => $color[2] + ($parg[2] ? $color[2] * $parg[2] : $arg[2]),
						'a' => $color[3] + ($parg[3] ? $color[3] * $parg[3] : $arg[3])
					);
				}

				else
					continue;

				$nc = $nc ? $nc : $this->hsl2rgb($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
				$css = str_replace($dec, $this->rgb_output($nc['r'], $nc['g'], $nc['b'], $nc['a']), $css);
			}
		}
	}
}

class CSS_Base64 extends CSSCache
{
	function process(&$css)
	{
		global $boarddir;

		$images = array();
		if (preg_match_all('~url\(([^\)]+)\)~i', $css, $matches))
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

class CSS_NestedSelectors extends CSSCache
{
	var $DOM;

	function process(&$css)
	{
		/******************************************************************************
		 Process nested selectors
		 ******************************************************************************/
		global $seen_nodes, $bases;

		// Transform the CSS into XML
		// does not like the data: protocol
		$xml = trim($css);
		$xml = str_replace('"', '#SI-CSSC-QUOTE#', $xml);
		$xml = preg_replace('/([-a-z]+)\s*:\s*([^;}{]+);?\s*(?=[\r\n}])/ie', "'<property name=\"'.trim('$1').'\" value=\"'.trim(str_replace(array('&','>','<'),array('&amp;','&gt;','&lt;'),'$2')).'\" />'", $xml); // Transform properties
		$xml = preg_replace('/^(\s*)([\+>&#*@:.a-z][^{]+)\{/me', "'$1<rule selector=\"'.preg_replace('/\s+/', ' ', trim(str_replace(array('&','>'),array('&amp;','&gt;'),'$2'))).'\">'", $xml); // Transform selectors
		$xml = str_replace('}', '</rule>', $xml); // Close rules
		$xml = preg_replace('/\n/', "\r\t", $xml); // Indent everything one tab
		$xml = '<?xml version="1.0" ?'.">\r<css>\r\t$xml\r</css>\r"; // Tie it all up with a bow

		/******************************************************************************
		 Parse the XML into a crawlable DOM
		 ******************************************************************************/
		$this->DOM = new CSS_Dom($xml);
		$rule_nodes =& $this->DOM->getNodesByNodeName('rule');

		/******************************************************************************
		 Rebuild parsed CSS
		 ******************************************************************************/
		$css = '';
		$standard_nest = '';

		// Look for base: inheritance.
		$bases = $seen_nodes = array();
		foreach ($rule_nodes as $node)
			if (!isset($seen_nodes[$node->nodeId]))
				$this->searchProperty($node, 'base');
		unset($seen_nodes);

		// Do the proper nesting
		$basestr = 'base';
		foreach ($rule_nodes as $node)
		{
			if (strpos($node->selector, '@media') === 0)
			{
				$standard_nest = $node->selector;
				$css .= $node->selector . ' {';
			}

			$properties = $node->getChildNodesByNodeName('property');
			if (!empty($properties))
			{
				$selector = str_replace('&gt;', '>', $this->parseAncestorSelectors($this->getAncestorSelectors($node)));

				foreach ($bases as $i => &$base)
				{
					// We have a selector like ".class, #id > div a" and we want to know if it has the base "#id > div" in it
					if (strpos($selector, $base[0]) !== false)
					{
						$selectors = array_map('trim', explode(',', $selector));
						foreach ($selectors as &$snippet)
							if (preg_match('~[^\s,]' . $base[1] . '[\s,$]~', $snippet) !== false)
								$selector .= ', ' . str_replace($base[0], $base[2], $snippet); // And our magic trick happens here.
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

				foreach ($properties as $property)
					$css .= $property->name . ': ' . $property->value . ';';

				$css .= '}';
			}
		}

		if (!empty($standard_nest))
		{
			$css .= '}';
			$standard_nest = '';
		}
	}

	function searchProperty($here, $nodeName)
	{
		global $bases, $seen_nodes;

		$property = 'property';
		foreach ($here->childNodes as $i => &$node)
		{
			// Trying to avoid browsing through referenced objects.
			if (isset($seen_nodes[$node->nodeId]))
				continue;
			$seen_nodes[$node->nodeId] = true;

			$hereName = strtolower($node->nodeName);
			if ($hereName === $property && $node->name === $nodeName)
			{
				$bases[] = array(
					$node->value, // Add to this class in the tree...
					preg_quote($node->value),
					$this->parseAncestorSelectors($this->getAncestorSelectors($node)) // ...The current position
				);
				unset($here->childNodes[$i]); // !!! Tried unset($node) but it doesn't work...?
			}
			elseif ($hereName === 'rule')
				$this->searchProperty($node, $nodeName);
		}
	}

	function getAncestorSelectors($node)
	{
		$selectors = array();

		if (!empty($node->selector))
			$selectors[] = $node->selector;

		if (!empty($node->parentNodeId))
		{
			$parentNode = $this->DOM->nodeLookUp[$node->parentNodeId];
			if (isset($parentNode->selector))
			{
				$recursiveSelectors = $this->getAncestorSelectors($parentNode);
				$selectors = array_merge($selectors, $recursiveSelectors);
			}
		}
		return $selectors;
	}

	function parseAncestorSelectors($ancestors = array())
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
}

class CSS_DomNode
{
	var $nodeName = '';
	var $cdata = '';
	var $nodeId;
	var $parentNodeId;
	var $childNodes = array();

	function CSS_DomNode($nodeId, $nodeName = '', $attrs = array())
	{
		$this->nodeId = $nodeId;
		$this->nodeName = $nodeName;
		if (!empty($attrs))
		{
			foreach ($attrs as $attr => $value)
			{
				$attr = strtolower($attr);
				$this->$attr = $value;
			}
		}
	}

	function &getNodesByNodeName($nodeName, $childrenOnly = false)
	{
		$nodes = array();

		foreach ($this->childNodes as &$node)
		{
			if (strtolower($node->nodeName) === $nodeName)
				array_push($nodes, $node);

			if (!$childrenOnly)
			{
				$nestedNodes = $node->getNodesByNodeName($nodeName);
				$nodes = array_merge($nodes, $nestedNodes);
			}
		}
		return $nodes;
	}

	function &getChildNodesByNodeName($nodeNames)
	{
		return $this->getNodesByNodeName($nodeNames, true);
	}
}

class CSS_Dom extends CSS_DomNode
{
	var $xmlObj;
	var $nodeLookUp = array();

	function CSS_Dom($xml = '')
	{
		$this->name = 'DOM';
		$this->xmlObj = xml_parser_create();
		xml_set_object($this->xmlObj, $this);
		xml_set_element_handler($this->xmlObj, 'tagOpen', 'tagClose');
		xml_set_character_data_handler($this->xmlObj, "cdata");

		if (!empty($xml))
		{
			$this->nodeId = count($this->nodeLookUp);
			$this->nodeLookUp[] =& $this;
			$this->parse($xml);
		}
	}

	function parse($data)
	{
		if (!xml_parse($this->xmlObj, $data, true))
			printf("XML error: %s at line %d", xml_error_string(xml_get_error_code($this->xmlObj)), xml_get_current_line_number($this->xmlObj));
	}

	function tagOpen($parser, $nodeName, $attrs)
	{
		unset($node);
		$node = new CSS_DomNode(count($this->nodeLookUp), $nodeName, $attrs);
		$this->nodeLookUp[] = $node;
		array_push($this->childNodes, $node);
	}

	function cdata($parser, $cdata)
	{
		$parentId = count($this->childNodes) - 1;
		$this->childNodes[$parentId]->cdata = $cdata;
	}

	function tagClose($parser, $nodeName)
	{
		$totalNodes = count($this->childNodes);
		if ($totalNodes == 1)
		{
			$node =& $this->childNodes[0];
			$node->parentNodeId = 0;
			$container = strtolower($node->nodeName);
			$this->$container =& $node;
		}
		else if($totalNodes > 1)
		{
			$node = array_pop($this->childNodes);
			$parentId = count($this->childNodes) - 1;
			$node->parentNodeId = $this->childNodes[$parentId]->nodeId;
			$this->childNodes[$parentId]->childNodes[] =& $node;
			$this->nodeLookUp[$node->nodeId] =& $node;
		}
	}
}
