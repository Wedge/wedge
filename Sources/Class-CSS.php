<?php
/******************************************************************************
 CSS Cacheer library, originally written by Shaun Inman
 http://www.shauninman.com/archive/2008/05/30/check_out_css_cacheer
 The Wedge version merges all useful classes together and fixes a few bugs.
 ******************************************************************************/

class CacheerPlugin
{
	function process(&$css) {}
}

class ServerImportPlugin extends CacheerPlugin
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

class ConstantsPlugin extends CacheerPlugin
{
	function process(&$css)
	{
		$constants = array();
		if (preg_match_all('#@constants\s*\{\s*([^\}]+)\s*\}\s*#i', $css, $matches))
		{
			foreach ($matches[0] as $i => $constant)
			{
				$css = str_replace($constant, '', $css);
				preg_match_all('#([_a-z0-9]+)\s*:\s*([^;]+);#i', $matches[1][$i], $vars);

				foreach ($vars[1] as $var => $name)
					$constants["const($name)"] = $vars[2][$var];
			}
		}

		if (!empty($constants))
			$css = str_replace(array_keys($constants), array_values($constants), $css);
	}
}

class BasedOnPlugin extends CacheerPlugin
{
	function process(&$css)
	{
		$bases = array();
		if (preg_match_all('#@base\(([^\s\{]+)\)\s*\{(\s*[^\}]+)\s*\}\s*#i', $css, $matches))
		{
			// For each declaration
			foreach ($matches[0] as $key => $base)
			{
				// Remove the @base declaration
				$css = str_replace($base, '', $css);

				// Add declaration to our array indexed by base name
				$bases[$matches[1][$key]] = $matches[2][$key];
			}

			// Parse nested based-on properties, stopping at circular references
			foreach ($bases as $base_name => $properties)
				$bases[$base_name] = $this->replace_bases($bases, $properties, $base_name);

			// Now apply replaced based-on properties in our CSS
			$css = $this->replace_bases($bases, $css);
		}
	}

	function replace_bases($bases, &$css, $current_base_name = false)
	{
		// As long as there's based-on properties in the CSS string
		// Get all instances
		while (preg_match_all('#\s*based-on:\s*base\(([^;]+)\);#i', $css, $matches))
		{
			// Loop through based-on instances
			foreach ($matches[0] as $key => $based_on)
			{
				$styles = '';
				$base_names = array();
				// Determine bases
				$base_names = preg_split('/[\s,]+/', $matches[1][$key]);
				// Loop through bases
				foreach ($base_names as $base_name)
				{
					// Looks like a circular reference, skip to next base
					if ($current_base_name && $base_name == $current_base_name)
					{
						$styles .= '/* RECURSION */';
						continue;
					}

					$styles .= $bases[$base_name];
				}

				// Insert styles this is based on
				$css = str_replace($based_on, $styles, $css);
			}
		}
	}
}

class NestedSelectorsPlugin extends CacheerPlugin
{
	var $DOM;
	function process(&$css)
	{
		/******************************************************************************
		 Process nested selectors
		 ******************************************************************************/
		// Transform the CSS into XML
		// does not like the data: protocol
		$xml = trim($css);
		$xml = preg_replace('#(/\*[^*]*\*+([^/*][^*]*\*+)*/)#', '', $xml); // Strip comments to prevent parsing errors
		$xml = str_replace('"', '#SI-CSSC-QUOTE#', $xml);
		$xml = preg_replace('/([-a-z]+)\s*:\s*([^;}{]+);?\s*(?=[\r\n}])/ie', "'<property name=\"'.trim('$1').'\" value=\"'.trim('$2').'\" />'", $xml); // Transform properties
		$xml = preg_replace('/^(\s*)([\+>&#*@:.a-z][^{]+)\{/me', "'$1<rule selector=\"'.preg_replace('/\s+/', ' ', trim(str_replace(array('&','>'),array('&amp;','&gt;'),'$2'))).'\">'", $xml); // Transform selectors
		$xml = str_replace('}', '</rule>', $xml); // Close rules
		$xml = preg_replace('/\n/', "\r\t", $xml); // Indent everything one tab
		$xml = '<?xml version="1.0" ?'.">\r<css>\r\t$xml\r</css>\r"; // Tie it all up with a bow

		// header('Content-type: text/text');
		// echo $xml;
		// exit();

		/******************************************************************************
		 Parse the XML into a crawlable DOM
		 ******************************************************************************/
		$this->DOM = new SI_Dom($xml);
		$rule_nodes =& $this->DOM->getNodesByNodeName('rule');

		/******************************************************************************
		 Rebuild parsed CSS
		 ******************************************************************************/
		$css = '';
		$standard_nest = '';
		foreach ($rule_nodes as $node)
		{
			if (preg_match('#^@media#', $node->selector))
			{
				$standard_nest = $node->selector;
				$css .= $node->selector . ' {';
			}

			$properties = $node->getChildNodesByNodeName('property');
			if (!empty($properties))
			{
				$selector = str_replace('&gt;', '>', $this->parseAncestorSelectors($this->getAncestorSelectors($node)));

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
					$css .= $property->name.': '.$property->value.';';

				$css .= '}';
			}
		}

		if (!empty($standard_nest))
		{
			$css .= '}';
			$standard_nest = '';
		}
	}

	function getAncestorSelectors($node)
	{
		$selectors = array();

		if (!empty($node->selector))
		{
			$selectors[] = $node->selector;
		}
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

class SI_DomNode
{
	var $dom;
	var $nodeName = '';
	var $cdata = '';
	var $nodeId;
	var $parentNodeId;
	var $childNodes = array();

	function SI_DomNode($nodeId, $nodeName = '', $attrs = array())
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

	function &getNodesByNodeName($nodeNames, $childrenOnly = false)
	{
		$nodeNamesArray = explode('|', strtolower($nodeNames));
		$nodes = array();

		foreach ($this->childNodes as $node)
		{
			if (in_array(strtolower($node->nodeName), $nodeNamesArray))
				array_push($nodes, $node);

			if (!$childrenOnly)
			{
				$nestedNodes = $node->getNodesByNodeName($nodeNames);
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

class SI_Dom extends SI_DomNode
{
	var $xmlObj;
	var $nodeLookUp = array();

	function SI_Dom($xml = '')
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
		$node = new SI_DomNode(count($this->nodeLookUp), $nodeName, $attrs);
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
