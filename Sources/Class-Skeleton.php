<?php
/**
 * Wedge
 *
 * The skeleton code, backbone of the rendering process.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*************
 *
 * This is a helper class that holds a template layer or block.
 *
 * It is provided to allow plugins to use method chaining on a single item. If you don't need
 * to chain calls, then use the static methods in the wetem object.
 *
 * You can access an item by using wetem::get('item'), and then you would apply calls to it.
 * For instance, if you wanted to get block sidebar_dummy's parent, rename it to sidebar2
 * and insert a new layer into it, you may want to do it this way:
 *
 * wetem::get('sidebar_dummy')->parent()->rename('sidebar2')->inner('inside_sidebar');
 *
 *************/

final class wetemItem
{
	private $target;

	function __construct($to = '')
	{
		if (!$to)
			$to = 'default';
		$this->target = $to;
	}

	// Remove specified layer/block from the skeleton. (Non-chainable)
	function remove()
	{
		wetem::remove($this->target);
	}

	// The following are chainable aliases to the equivalent wetem:: functions.
	function load($items)		{ wetem::load($this->target, $items); return $this; }
	function replace($items)	{ wetem::replace($this->target, $items); return $this; }
	function add($items)		{ wetem::add($this->target, $items); return $this; }
	function first($items)		{ wetem::first($this->target, $items); return $this; }
	function before($items)		{ wetem::before($this->target, $items); return $this; }
	function after($items)		{ wetem::after($this->target, $items); return $this; }
	function move($layer, $p)	{ wetem::move($this->target, $layer, $p); return $this; }
	function rename($layer)		{ wetem::rename($this->target, $layer); return $this; }
	function outer($layer)		{ wetem::outer($this->target, $layer); return $this; }
	function inner($layer)		{ wetem::inner($this->target, $layer); return $this; }
	function parent()			{ return wetem::get(wetem::parent($this->target)); }
}

/*************
 *
 * This is the template object.
 *
 * It is used to manage the skeleton array that holds
 * all of the layers and blocks in the template.
 *
 * The skeleton itself can't be accessed from outside the object.
 * Thus, you'll have to rely on the public functions to manipulate it.
 *
 * wetem::load()	- load a block ('block_name'), layer (array('layer_name' => array())) or array of these *into* a given layer
 * wetem::replace()	- same, but deletes existing sub-layers in the process
 * wetem::add()		- same, but adds data to given layer
 * wetem::first()	- same, but prepends data to given layer
 * wetem::before()	- same, but inserts data *before* given layer or block
 * wetem::after()	- same, but inserts data *after* given layer or block
 * wetem::move()	- moves an existing block or layer to another position in the skeleton
 *
 * wetem::layer()	- various layer creation functions (see documentation for the function)
 * wetem::rename()	- rename an existing layer
 * wetem::outer()	- wrap a new outer layer around the given layer
 * wetem::inner()	- inject a new inner layer directly below the given layer
 * wetem::remove()	- remove a block or layer from the skeleton
 *
 * wetem::hide()	- erase the skeleton and replace it with a simple structure (template-less pages)
 *
 * wetem::parent()	- return the name of the block/layer's parent layer
 * wetem::get()		- see wetemItem description. If you only have one action to apply, avoid using it.
 * wetem::has()		- does the skeleton have this block or layer in it?
 *					  - ::has_block($block) forces a test for blocks only
 *					  - ::has_layer($layer) forces a test for layers only
 *
 *************/

final class wetem
{
	private static $instance;				// container for self
	private static $skeleton = array();		// store the full skeleton array
	private static $layers = array();		// store shortcuts to individual layers
	private static $opt = array();			// options for individual layers/block
	private static $obj = array();			// store shortcuts to individual layer/block objects
	private static $hidden = false;			// did we call hide()?

	// What kind of class are you, anyway? One of a kind!
	private function __clone()
	{
		return false;
	}

	// Bootstrap's bootstraps
	static function getInstance()
	{
		// Squeletto ergo sum
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	// Does the skeleton hold a specific layer or block?
	static function has($item)
	{
		return (bool) self::parent($item);
	}

	// Does the skeleton hold a specific block?
	static function has_block($block)
	{
		return !isset(self::$layers[$block]) && self::parent($block) !== false;
	}

	// Does the skeleton hold a specific layer?
	static function has_layer($layer)
	{
		return isset(self::$layers[$layer]);
	}

	/**
	 * Build the multi-dimensional layout skeleton array from an single-dimension array of tags.
	 */
	static function build(&$arr)
	{
		// Unset any pending layer objects.
		if (!empty(self::$obj))
			foreach (self::$obj as &$layer)
				$layer = null;

		self::parse($arr, self::$skeleton);
	}

	/**
	 * This is where we render the HTML page!
	 */
	static function render()
	{
		if (empty(self::$layers['default']))
			fatal_lang_error('default_layer_missing');

		self::render_recursive(reset(self::$skeleton), key(self::$skeleton));
	}

	/**
	 * Returns a wetemItem object representing the first layer or block we need.
	 *
	 * @param string $targets A layer or block, or array of layers or blocks to look for.
	 */
	static function get($targets = '')
	{
		$to = self::find($targets);
		// Not a valid block/layer? Return the default layer.
		// @todo: add a proper error message for this... Like, 'Not a valid layer or block!'
		if ($to === false)
			$to = 'default';
		if (!isset(self::$obj[$to]))
			self::$obj[$to] = new wetemItem($to);
		return self::$obj[$to];
	}

	/***********************************************************************************
	 *
	 * The following functions are available for plugins to manipulate LAYERS or BLOCKS.
	 *
	 ***********************************************************************************/

	// Add contents before the specified layer or block.
	static function before($target, $contents = '')
	{
		return wetem::op($contents, $target, 'before');
	}

	// Add contents after the specified layer or block.
	static function after($target, $contents = '')
	{
		return wetem::op($contents, $target, 'after');
	}

	/**
	 * Remove a block or layer from the skeleton.
	 * If done on a layer, it will only be removed if it doesn't contain the default layer.
	 * @todo: add option to remove only the layer, not its contents.
	 *
	 * @param string $item The name of the block or layer to remove.
	 */
	static function remove($target)
	{
		$layer = self::parent($target);
		// If it's a valid block, just remove it.
		if ($layer && !is_array(self::$layers[$layer][$target]))
			unset(self::$layers[$layer][$target]);
		// Otherwise it's a layer, make sure it's removable.
		elseif (isset(self::$layers[$layer]))
			self::remove_layer($target);
	}

	/**
	 * Move an existing block or layer to somewhere else in the skeleton.
	 *
	 * @param string $item The name of the block or layer to move.
	 * @param string $target The target block or layer.
	 * @param string $where The new position relative to the target: before, after, anything accepted by wetem::op.
	 */
	static function move($item, $target, $where)
	{
		if (!self::has($item) || !self::has($target))
			return false;

		if (isset(self::$layers[$item]))
		{
			$to_move = self::$layers[$item];
			unset(self::$layers[$item]);
		}
		else
		{
			$parent = self::parent($item);
			if (!$parent)
				return false;
			$to_move = self::$layers[$parent][$item];
			unset(self::$layers[$parent][$item]);
		}
		self::op(array($item => $to_move), $target, $where, true);
	}

	/**
	 * Find a block or layer's parent layer.
	 *
	 * @param string $child The name of the block or layer. Really.
	 * @return mixed Returns either the name of the parent layer, or FALSE if not found.
	 */
	static function parent($child)
	{
		foreach (self::$layers as $id => &$layer)
			if (isset($layer[$child]))
				return $id;

		return false;
	}

	/*************************************************************************
	 *
	 * The following functions are available for plugins to manipulate LAYERS.
	 *
	 *************************************************************************/

	// Replace specified layer's contents with our new contents. Leave its existing layers alone.
	static function load($target, $contents = '')
	{
		return wetem::op($contents, $target, 'load');
	}

	// Add contents inside specified layer, at the end. (jQuery equivalent: .append())
	static function add($target, $contents = '')
	{
		return wetem::op($contents, $target, 'add');
	}

	// Add contents inside specified layer, at the beginning. (jQuery equivalent: .prepend())
	static function first($target, $contents = '')
	{
		return wetem::op($contents, $target, 'first');
	}

	// Replace specified layer's contents with our new contents.
	static function replace($target, $contents = '')
	{
		return wetem::op($contents, $target, 'replace');
	}

	// Rename the current layer to $target.
	static function rename($target, $new_name)
	{
		if (empty($target) || empty($new_name) || $target == 'default' || !isset(self::$layers[$target]))
			return false;
		$result = self::insert_layer($new_name, $target, 'rename');
		$result &= self::remove_layer($target);
		return $result ? $new_name : false;
	}

	// Wrap a new layer around the current one. (Equivalent to jQuery's wrap)
	// @todo: accept blocks, as we should be able to add layers around them.
	static function outer($target, $new_layer = '')
	{
		if (empty($new_layer))
			list ($target, $new_layer) = array('default', $target);
		if (!isset(self::$layers[$target]))
			return false;
		return self::insert_layer($new_layer, $target, 'outer');
	}

	// Wrap a new layer around the current one's contents. (Equivalent to jQuery's wrapInner)
	static function inner($target, $new_layer = '')
	{
		if (empty($new_layer))
			list ($target, $new_layer) = array('default', $target);
		if (!isset(self::$layers[$target]))
			return false;
		self::$layers[$target] = array($new_layer => self::$layers[$target]);
		self::$layers[$new_layer] =& self::$layers[$target][$new_layer];
		return $new_layer;
	}

	/**
	 * A quick alias to tell Wedge to hide blocks that don't belong to the main flow (default layer).
	 *
	 * @param array $layer The layers we want to keep, or 'html' for the main html/body layers. Leave empty to just keep the default layer.
	 */
	static function hide($layer = '')
	{
		global $context;

		if (empty(self::$layers['default']))
			self::$layers['default'] = array('main' => true);

		// We only keep the default layer and its content. (e.g. we're inside an Ajax frame)
		if (empty($layer))
			self::$skeleton = array(
				'dummy' => array(
					'default' => self::$layers['default']
				)
			);
		// Or we only keep the HTML headers, body definition and content (e.g. we're inside a popup window)
		elseif ($layer === 'html')
			self::$skeleton = array(
				'html' => array(
					'body' => array(
						'default' => self::$layers['default']
					)
				)
			);
		// Or finally... Do we want to keep/add a specific layer, like 'print' maybe?
		else
			self::$skeleton = array(
				'dummy' => array(
					$layer => array(
						'default' => self::$layers['default']
					)
				)
			);
		self::reindex();

		// Give plugins/themes a simple way to know we're hiding it all.
		$context['hide_chrome'] = self::$hidden = true;
	}

	/**
	 * Add a layer dynamically.
	 *
	 * A layer is a special block that contains other blocks/layers instead of a dedicated template function.
	 * These can also be done through the equivalent wetem:: functions, by specifying array('layer' => array()) as the contents.
	 *
	 * @param string $layer The name of the layer to be called. e.g. 'layer' will attempt to load 'template_layer_before' and 'template_layer_after' functions.
	 * @param string $target Which layer to add it relative to, e.g. 'body' (overall page, outside the wrapper divs), etc. Leave empty to wrap around the default layer (which doesn't accept any positioning, either.)
	 * @param string $where Where should we add the layer? Check the comments inside the function for a fully documented list of positions.
	 * @return mixed Returns false if a problem occurred, otherwise the name of the inserted layer.
	 */
	static function layer($layer, $target = '', $where = 'replace')
	{
		/*
			This is the full list of $where possibilities.
			<layer> is $layer, <target> is $target, and <sub> is anything already inside <target>, block or layer.

			add			add as a child to the target, in last position			<target> <sub /> <layer> </layer> </target>
			first		add as a child to the target, in first position			<target> <layer> </layer> <sub /> </target>
			before		add before the item										<layer> </layer> <target> <sub /> </target>
			after		add after the item										<target> <sub /> </target> <layer> </layer>
			replace		replace the target layer and empty its contents			<layer>                            </layer>
		*/

		if (empty($target))
			$target = 'default';

		// Target layer doesn't exist..? Enter brooding mode.
		if (!isset(self::$layers[$target]))
			return false;

		if ($where === 'before' || $where === 'after')
			self::insert_layer($layer, $target, $where);
		elseif ($where === 'replace')
		{
			self::insert_layer($layer, $target, $where);
			self::remove_layer($target);
		}
		elseif ($where === 'first' || $where === 'add')
		{
			if ($where === 'first')
				self::$layers[$target] = array_merge(array($layer => array()), self::$layers[$target]);
			else
				self::$layers[$target][$layer] = array();
			self::$layers[$layer] =& self::$layers[$target][$layer];
		}
		else
			return false;
		return $layer;
	}

	/**********************************************************************
	 *
	 * All functions below are private, plugins shouldn't bother with them.
	 *
	 **********************************************************************/

	/**
	 * Builds the skeleton array (self::$skeleton) and the layers array (self::$layers)
	 * based on the contents of $context['skeleton'].
	 */
	private static function parse(&$arr, &$dest, &$pos = 0, $name = '')
	{
		for ($c = count($arr); $pos < $c;)
		{
			$tag =& $arr[$pos++];

			// Ending a layer?
			if (!empty($tag[1]))
			{
				self::$layers[$name] =& $dest;
				return;
			}

			// Starting a layer?
			if (empty($tag[4]))
			{
				$dest[$tag[2]] = array();
				self::parse($arr, $dest[$tag[2]], $pos, $tag[2]);
			}
			// Then it's a block...
			else
				$dest[$tag[2]] = true;

			// Has this layer/block got any options? (Wedge only accepts indent="x" as of now.)
			if (!empty($tag[3]))
			{
				preg_match_all('~(\w+)="([^"]+)"?~', $tag[3], $options, PREG_SET_ORDER);
				foreach ($options as $option)
					self::$opt[$option[1]][$tag[2]] = $option[2];
			}
		}
	}

	/**
	 * Rebuilds $layers according to the current skeleton.
	 * The skeleton builder doesn't call this because it does it automatically.
	 */
	private static function reindex()
	{
		// Save $skeleton as raw data to ensure it doesn't get erased next.
		$transit = unserialize(serialize(self::$skeleton));
		self::$layers = array();
		self::$skeleton = $transit;

		// Sadly, array_walk_recursive() won't trigger on child arrays... :(
		self::reindex_recursive(self::$skeleton);
	}

	private static function reindex_recursive(&$here)
	{
		foreach ($here as $id => &$item)
		{
			if (is_array($item))
			{
				self::$layers[$id] =& $item;
				self::reindex_recursive($item);
			}
		}
	}

	private static function render_recursive(&$here, $key)
	{
		if (isset(self::$opt['indent'][$key]))
			echo '<inden@zi=', $key, '=', self::$opt['indent'][$key], '>';

		// Show the _before part of the layer.
		execBlock($key . '_before', 'ignore');

		if ($key === 'top' || $key === 'default')
			while_we_re_here();

		foreach ($here as $id => $temp)
		{
			// If the item is an array, then it's a layer. Otherwise, it's a block.
			if (is_array($temp))
				self::render_recursive($temp, $id);
			elseif (isset(self::$opt['indent'][$id]))
			{
				echo '<inden@zi=', $id, '=', self::$opt['indent'][$id], '>';
				execBlock($id);
				echo '</inden@zi=', $id, '>';
			}
			else
				execBlock($id);
		}

		// Show the _after part of the layer
		execBlock($key . '_after', 'ignore');

		if (isset(self::$opt['indent'][$key]))
			echo '</inden@zi=', $key, '>';

		// !! We should probably move this directly to template_html_after() and forget the buffering thing...
		if ($key === 'html' && !isset($_REQUEST['xml']) && !self::$hidden)
			db_debug_junk();
	}

	/**
	 * Returns the name of the first valid layer/block in the list, or false if nothing was found.
	 *
	 * @param string $targets A layer or block, or array of layers or blocks to look for. Leave empty to use the default layer.
	 * @param string $where The magic keyword. See definition for ::op().
	 */
	private static function find($targets = '', $where = '')
	{
		// Find the first target layer that isn't wishful thinking.
		foreach ((array) $targets as $layer)
		{
			if (empty($layer))
				$layer = 'default';
			if (isset(self::$layers[$layer]))
			{
				$to = $layer;
				break;
			}
		}

		// No valid layer found.
		if (empty($to))
		{
			// If we try to insert a sideback block in XML or minimal mode (hide_chrome), it will fail.
			// Plugins should provide a 'default' fallback if they consider it vital to show the block, e.g. array('sidebar', 'default').
			if (!empty($where) && $where !== 'before' && $where !== 'after')
				return false;

			// Or maybe we're looking for a block..?
			$all_blocks = iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator(self::$skeleton)));
			foreach ((array) $targets as $block)
			{
				if (isset($all_blocks[$block]))
				{
					$to = $block;
					break;
				}
			}
			unset($all_blocks);
		}
		return $to;
	}

	private static function list_blocks($items)
	{
		$blocks = array();
		foreach ($items as $key => $val)
		{
			if (is_array($val))
				$blocks[$key] = self::list_blocks($val);
			else
				$blocks[$val] = true;
		}
		return $blocks;
	}

	/**
	 * Insert a layer to the skeleton.
	 *
	 * @param string $source Name of the layer to insert.
	 * @param string $target Name of the parent layer to target.
	 * @param string $where Determines where to position the source layer relative to the target.
	 */
	private static function insert_layer($source, $target = 'default', $where = 'outer')
	{
		$lay = self::parent($target);
		$lay = $lay ? $lay : 'default';
		if (!isset(self::$layers[$lay]))
			return false;
		$dest =& self::$layers[$lay];

		$temp = array();
		foreach ($dest as $key => &$value)
		{
			if ($key === $target)
			{
				if ($where === 'after')
					$temp[$key] = $value;
				$temp[$source] = $where === 'outer' ? array($key => $value) : ($where === 'replace' ? array() : ($where === 'rename' ? $value : array()));
				if ($where === 'before')
					$temp[$key] = $value;
			}
			else
				$temp[$key] = $value;
		}

		$dest = $temp;
		// We need to reindex, in case the layer had child layers.
		if ($where !== 'after' && $where !== 'before')
			self::reindex();
		return true;
	}

	// Helper function to remove a layer from the page.
	private static function remove_layer($layer)
	{
		// Does the layer at least exist...?
		if (!isset(self::$layers[$layer]) || $layer === 'default')
			return false;

		// Determine whether removing this layer would also remove the default layer. Which you may not.
		$current = 'default';
		$loop = true;
		while ($loop)
		{
			$loop = false;
			foreach (self::$layers as $id => &$curlay)
			{
				if (isset($curlay[$current]))
				{
					// There there! Go away now, we won't tell your parents.
					if ($id === $layer)
						return false;
					$current = $id;
					$loop = true;
				}
			}
		}

		// This isn't a direct parent of 'default', so we can safely remove it.
		self::$skeleton = self::remove_item($layer);
		self::reindex();
		return true;
	}

	private static function remove_item($item, $from = array(), $level = 0)
	{
		if (empty($from))
			$from = self::$skeleton;

		$ret = array();
		foreach ($from as $key => $val)
			if ($key !== $item)
				$ret[$key] = is_array($val) && !empty($val) ? self::remove_item($item, $val, $level + 1) : $val;

		return $ret;
	}

	/**
	 * Add blocks or layers to the skeleton.
	 *
	 * @param string $blocks The name of the blocks or layers to be added.
	 * @param string $target Which layer to load this function in, e.g. 'default' (main contents), 'top' (above the main area), 'sidebar' (sidebar area), etc. If using 'before' or 'after', you may instead specify a block name.
	 * @param string $where Where should we add the item? Check the comments inside the function for a fully documented list of positions. Non-default layers should use wetem::add() rather than wetem::load().
	 * @param bool $force Only used when $blocks shouldn't be tempered with.
	 */
	private static function op($blocks, $target, $where, $force = false)
	{
		/*
			This is the full list of $where possibilities.
			<blocks> is our source block(s), <layer> is our $target layer, and <other> is anything already inside <layer>, block or layer.

			load		replace existing blocks with this, leave layers in		<layer> <ITEMS /> <other /> </layer>
			replace		replace existing blocks AND layers with this			<layer>      <ITEMS />      </layer>

			add			add block(s) at the end of the layer					<layer> <other /> <ITEMS /> </layer>
			first		add block(s) at the beginning of the layer				<layer> <ITEMS /> <other /> </layer>

			before		add block(s) before the specified layer or block		    <ITEMS /> <layer-or-block />
			after		add block(s) after the specified layer or block			    <layer-or-block /> <ITEMS />
		*/

		// If we only have one parameter, this means we only provided a list of blocks/layer,
		// and expect to use them relative to the default layer, so we'll swap the variables.
		if (empty($blocks))
			list ($target, $blocks) = array('default', $target);

		if (!$force)
			$blocks = self::list_blocks((array) $blocks);
		$has_layer = (bool) count(array_filter($blocks, 'is_array'));
		$to = self::find($target, $where);
		if (empty($to))
			return false;

		// If a mod requests to replace the contents of the sidebar, just smile politely.
		if (($where === 'load' || $where === 'replace') && $to === 'sidebar')
			$where = 'add';

		if ($where === 'load' || $where === 'replace')
		{
			// Most likely case: no child layers (or erase all). Replace away!
			if ($where === 'replace' || !isset(self::$layers[$to]) || count(self::$layers[$to]) === count(self::$layers[$to], COUNT_RECURSIVE))
			{
				self::$layers[$to] = $blocks;
				// If we erase, we might have to delete layer entries.
				if ($where === 'replace' || $has_layer)
					self::reindex();
				return $to;
			}

			// Otherwise, we're in for some fun... :-/
			$keys = array_keys(self::$layers[$to]);
			foreach ($keys as $id)
			{
				if (!is_array(self::$layers[$to][$id]))
				{
					// We're going to insert our item(s) right before the first block we find...
					if (!isset($offset))
					{
						$offset = array_search($id, $keys, true);
						self::$layers[$to] = array_merge(array_slice(self::$layers[$to], 0, $offset, true), $blocks, array_slice(self::$layers[$to], $offset, null, true));
					}
					// ...And then we delete the other block(s) and leave the layers where they are.
					unset(self::$layers[$to][$id]);
				}
			}

			// So, we found a layer but no blocks..? Add our blocks at the end.
			if (!isset($offset))
				self::$layers[$to] += $blocks;

			self::reindex();
			return $to;
		}

		elseif ($where === 'add')
			self::$layers[$to] += $blocks;

		elseif ($where === 'first')
			self::$layers[$to] = array_merge(array_reverse($blocks), self::$layers[$to]);

		elseif ($where === 'before' || $where === 'after')
		{
			foreach (self::$layers as &$layer)
			{
				if (!isset($layer[$to]))
					continue;

				$layer = array_insert($layer, $to, $blocks, $where === 'after');
				self::reindex();
				return $to;
			}
		}
		else
			return false;

		if ($has_layer)
			self::reindex();

		return $to;
	}
}
