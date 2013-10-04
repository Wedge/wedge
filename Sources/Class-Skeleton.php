<?php
/**
 * The skeleton code, backbone of the rendering process.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*************
 *
 * This is the skeleton object.
 *
 * It is used to manage skeleton arrays that hold
 * all of the layers and blocks in a template or mini-template.
 *
 * Skeletons themselves can't be accessed from outside the object.
 * Thus, you'll have to rely on the public functions to manipulate them.
 *
 * $skeleton->load()	- load a block ('block_name'), layer (array('layer_name' => array())) or array of these *into* a given layer
 * $skeleton->replace()	- same, but deletes existing sub-layers in the process
 * $skeleton->add()		- same, but adds data to given layer
 * $skeleton->first()	- same, but prepends data to given layer
 * $skeleton->before()	- same, but inserts data *before* given layer/block
 * $skeleton->after()	- same, but inserts data *after* given layer/block
 * $skeleton->insert()	- shortcut to do a combination of the above based on the preferred target.
 *
 * $skeleton->layer()	- various layer creation functions (see documentation for the function)
 * $skeleton->rename()	- rename an existing layer/block
 * $skeleton->outer()	- wrap a new outer layer around the given layer
 * $skeleton->inner()	- inject a new inner layer directly below the given layer
 *
 * $skeleton->move()	- moves an existing layer/block to another position in the skeleton
 * $skeleton->remove()	- remove a layer/block from the skeleton
 *
 * $skeleton->hide()	- erase the skeleton and replace it with a simple structure (template-less pages)
 *
 * $skeleton->parent()	- return the name of the layer/block's parent layer
 * $skeleton->get()		- see weSkeletonItem description. If you only have one action to apply, avoid using it.
 * $skeleton->has()		- does the skeleton have this layer/block in it?
 *						- ->has_block($block) forces a test for blocks only
 *						- ->has_layer($layer) forces a test for layers only
 *
 *************/

final class weSkeleton
{
	private $skeleton = array();	// store the full skeleton array
	private $layers = array();		// store shortcuts to individual layers
	private $opt = array();			// options for individual layers/block
	private $obj = array();			// store shortcuts to individual layer/block objects
	private $hidden = false;		// did we call hide()?
	var $skip = array();			// list of blocks to skip in the next pass
	var $id = '';					// name of the skeleton

	function __construct($id)
	{
		global $context;

		if (!empty($context['skeleton'][$id]))
			$this->build($context['skeleton'][$id]);
		$this->id = $id;

		// If this is the main skeleton and it's empty, it means it's a barebones skeleton.
		// Give it a couple of layers to allow it to load the main content.
		if ($id === 'main' && empty($context['skeleton'][$id]))
			$this->hide();

		// Apply all skeleton operations now.
		if (!empty($context['skeleton_ops'][$id]))
			foreach ($context['skeleton_ops'][$id] as $op)
				call_user_func_array(array($this, $op[0]), array_slice($op, 1));
	}

	// Does the skeleton hold a specific layer or block?
	function has($item)
	{
		return isset($this->layers[$item]) || (bool) $this->parent($item);
	}

	// Does the skeleton hold a specific block?
	function has_block($block)
	{
		return !isset($this->layers[$block]) && $this->parent($block) !== false;
	}

	// Does the skeleton hold a specific layer?
	function has_layer($layer)
	{
		return isset($this->layers[$layer]);
	}

	/**
	 * This is where we render the HTML page!
	 */
	function render()
	{
		if ($this->id === 'main' && empty($this->layers['default']))
			fatal_lang_error('default_layer_missing');
		$here = reset($this->skeleton);
		$key = key($this->skeleton);
		$this->render_recursive($here, $key);
		$this->skip = array();
	}

	/**
	 * Returns a weSkeletonItem object representing the first layer or block we need.
	 *
	 * @param string $targets A layer or block, or array of layers or blocks to look for.
	 */
	function get($targets = '')
	{
		$to = $this->find($targets);
		// Not a valid block/layer? Return the default layer.
		// @todo: add a proper error message for this... Like, 'Not a valid layer or block!'
		if ($to === false)
			$to = 'default';
		if (!isset($this->obj[$to]))
			$this->obj[$to] = new weSkeletonItem($this, $to);
		return $this->obj[$to];
	}

	/***********************************************************************************
	 *
	 * The following functions are available for plugins to manipulate LAYERS or BLOCKS.
	 *
	 ***********************************************************************************/

	// Add contents before the specified layer or block.
	function before($target, $contents = '')
	{
		return $this->op($contents, $target, 'before');
	}

	// Add contents after the specified layer or block.
	function after($target, $contents = '')
	{
		return $this->op($contents, $target, 'after');
	}

	// Add contents with fine-tuning of position depending on target.
	function insert($target, $contents = '')
	{
		return $this->op($contents, $target, 'generic');
	}

	/**
	 * Skip a block or layer from the next render pass.
	 * This is only useful for non-main skeletons, obviously.
	 * If you want to remove the target for all passes, use remove().
	 */
	function skip($target)
	{
		$this->skip[$target] = true;
	}

	/**
	 * Rename the current layer/block to $new_name.
	 */
	function rename($target, $new_name)
	{
		if (empty($target) || empty($new_name) || $target == 'default' || !$this->has($target))
			return false;
		if (isset($this->layers[$target]))
		{
			$result = $this->insert_layer($new_name, $target, 'rename');
			$result &= $this->remove_layer($target);
		}
		else
		{
			$result = $this->before($target, $new_name);
			$result &= $this->remove($target);
		}
		return $result ? $new_name : false;
	}

	/**
	 * Remove a block or layer from the skeleton.
	 * If done on a layer, it will only be removed if it doesn't contain the default layer.
	 * @todo: add option to remove only the layer, not its contents.
	 *
	 * @param string $item The name of the block or layer to remove.
	 */
	function remove($target)
	{
		$layer = $this->parent($target);
		// If it's a valid block, just remove it.
		if ($layer && !is_array($this->layers[$layer][$target]))
			unset($this->layers[$layer][$target]);
		// Otherwise it's a layer, make sure it's removable.
		elseif (isset($this->layers[$layer]))
			$this->remove_layer($target);
	}

	/**
	 * Move an existing block or layer to somewhere else in the skeleton.
	 *
	 * @param string $item The name of the block or layer to move.
	 * @param string $target The target block or layer.
	 * @param string $where The new position relative to the target: before, after, anything accepted by $this->op.
	 */
	function move($item, $target, $where)
	{
		if (!$this->has($item) || !$this->has($target))
			return false;

		if (isset($this->layers[$item]))
		{
			$to_move = $this->layers[$item];
			unset($this->layers[$item]);
		}
		else
		{
			$parent = $this->parent($item);
			if (!$parent)
				return false;
			$to_move = $this->layers[$parent][$item];
			unset($this->layers[$parent][$item]);
		}
		$this->op(array($item => $to_move), $target, $where, true);
	}

	/**
	 * Find a block or layer's parent layer.
	 *
	 * @param string $child The name of the block or layer. Really.
	 * @return mixed Returns either the name of the parent layer, or FALSE if not found.
	 */
	function parent($child)
	{
		foreach ($this->layers as $id => &$layer)
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
	function load($target, $contents = '')
	{
		return $this->op($contents, $target, 'load');
	}

	// Add contents inside specified layer, at the end. (jQuery equivalent: .append())
	function add($target, $contents = '')
	{
		return $this->op($contents, $target, 'add');
	}

	// Add contents inside specified layer, at the beginning. (jQuery equivalent: .prepend())
	function first($target, $contents = '')
	{
		return $this->op($contents, $target, 'first');
	}

	// Replace specified layer's contents with our new contents.
	function replace($target, $contents = '')
	{
		return $this->op($contents, $target, 'replace');
	}

	// Wrap a new layer around the current one. (Equivalent to jQuery's wrap)
	// @todo: accept blocks, as we should be able to add layers around them.
	function outer($target, $new_layer = '')
	{
		if (empty($new_layer))
			list ($target, $new_layer) = array('default', $target);
		if (!isset($this->layers[$target]))
			return false;
		return $this->insert_layer($new_layer, $target, 'outer');
	}

	// Wrap a new layer around the current one's contents. (Equivalent to jQuery's wrapInner)
	function inner($target, $new_layer = '')
	{
		if (empty($new_layer))
			list ($target, $new_layer) = array('default', $target);
		if (!isset($this->layers[$target]))
			return false;
		$this->layers[$target] = array($new_layer => $this->layers[$target]);
		$this->layers[$new_layer] =& $this->layers[$target][$new_layer];
		return $new_layer;
	}

	/**
	 * A quick alias to create a basic skeleton with nothing to distract your attention.
	 *
	 * @param array $layer The layer we want to keep, or 'html' for the main html/body layers. Leave empty to just keep the default layer.
	 */
	function hide($layer = '')
	{
		global $context;

		if (empty($this->layers['default']))
			$this->layers['default'] = array('main' => true);

		// We only keep the default layer and its content. (e.g. we're inside an Ajax frame)
		if (empty($layer))
			$this->skeleton = array(
				'dummy' => array(
					'default' => $this->layers['default']
				)
			);
		// Or we only keep the HTML headers, body definition and content (e.g. we're inside a popup window)
		elseif ($layer === 'html')
			$this->skeleton = array(
				'html' => array(
					'body' => array(
						'default' => $this->layers['default']
					)
				)
			);
		// Or finally... Keep/add a specific layer, like 'print' or 'report'.
		else
			$this->skeleton = array(
				'dummy' => array(
					$layer => array(
						'default' => $this->layers['default']
					)
				)
			);
		$this->reindex();

		// Give plugins/themes a simple way to know we're hiding it all.
		$context['hide_chrome'] = $this->hidden = true;
	}

	/**
	 * Add a layer dynamically.
	 *
	 * A layer is a special block that contains other blocks/layers instead of a dedicated template function.
	 * These can also be done through the equivalent weSkeleton or wetem methods, by specifying array('layer' => array()) as the contents.
	 *
	 * @param string $layer The name of the layer to be called. e.g. 'layer' will attempt to load 'template_layer_before' and 'template_layer_after' functions.
	 * @param string $target Which layer to add it relative to, e.g. 'body' (overall page, outside the wrapper divs), etc. Leave empty to wrap around the default layer (which doesn't accept any positioning, either.)
	 * @param string $where Where should we add the layer? Check the comments inside the function for a fully documented list of positions.
	 * @return mixed Returns false if a problem occurred, otherwise the name of the inserted layer.
	 */
	function layer($layer, $target = '', $where = 'replace')
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
		if (!isset($this->layers[$target]))
			return false;

		if ($where === 'before' || $where === 'after')
			$this->insert_layer($layer, $target, $where);
		elseif ($where === 'replace')
		{
			$this->insert_layer($layer, $target, $where);
			$this->remove_layer($target);
		}
		elseif ($where === 'first' || $where === 'add')
		{
			if ($where === 'first')
				$this->layers[$target] = array_merge(array($layer => array()), $this->layers[$target]);
			else
				$this->layers[$target][$layer] = array();
			$this->layers[$layer] =& $this->layers[$target][$layer];
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
	 * Build the multi-dimensional layout skeleton array from a string of tags.
	 */
	private function build($str)
	{
		// Unset any pending layer objects.
		if (!empty($this->obj))
			foreach ($this->obj as &$layer)
				$layer = null;

		preg_match_all('~<(?!!)(/)?([\w:,]+)\s*([^>]*?)(/?)\>~', $str, $arr, PREG_SET_ORDER);
		$this->parse($arr, $this->skeleton);
	}

	/**
	 * Builds the skeleton array ($this->skeleton) and the layers array ($this->layers)
	 * based on the contents of $context['skeleton'].
	 */
	private function parse($arr, &$dest, &$pos = 0, $name = '')
	{
		for ($c = count($arr); $pos < $c;)
		{
			$tag =& $arr[$pos++];

			// Ending a layer?
			if (!empty($tag[1]))
			{
				$this->layers[$name] =& $dest;
				return;
			}

			// Starting a layer?
			if (empty($tag[4]))
			{
				$dest[$tag[2]] = array();
				$this->parse($arr, $dest[$tag[2]], $pos, $tag[2]);
			}
			// Then it's a block...
			else
				$dest[$tag[2]] = true;

			// Has this layer/block got any options? (Wedge only accepts indent="x" as of now.)
			if (!empty($tag[3]))
			{
				preg_match_all('~(\w+)="([^"]+)"?~', $tag[3], $options, PREG_SET_ORDER);
				foreach ($options as $option)
					$this->opt[$option[1]][$tag[2]] = $option[2];
			}
		}
	}

	/**
	 * Rebuilds $layers according to the current skeleton.
	 * The skeleton builder doesn't call this because it does it automatically.
	 */
	private function reindex()
	{
		// Save $skeleton as raw data to ensure it doesn't get erased next.
		$transit = unserialize(serialize($this->skeleton));
		$this->layers = array();
		$this->skeleton = $transit;

		// Sadly, array_walk_recursive() won't trigger on child arrays... :(
		$this->reindex_recursive($this->skeleton);
	}

	private function reindex_recursive(&$here)
	{
		foreach ($here as $id => &$item)
		{
			if (is_array($item))
			{
				$this->layers[$id] =& $item;
				$this->reindex_recursive($item);
			}
		}
	}

	private function render_recursive(&$here, $key)
	{
		if (isset($this->opt['indent'][$key]))
			echo '<inden@zi=', $key, '=', $this->opt['indent'][$key], '>';

		// Show the _before part of the layer.
		execBlock($key . '_before', 'ignore');

		if ($this->id === 'main' && ($key === 'top' || $key === 'default'))
			while_we_re_here();

		foreach ($here as $id => $temp)
		{
			if (isset($this->skip[$id]))
				continue;

			// If the item is an array, then it's a layer. Otherwise, it's a block.
			if (is_array($temp))
				$this->render_recursive($temp, $id);
			elseif (isset($this->opt['indent'][$id]))
			{
				echo '<inden@zi=', $id, '=', $this->opt['indent'][$id], '>';
				execBlock($id);
				echo '</inden@zi=', $id, '>';
			}
			else
				execBlock($id);
		}

		// Show the _after part of the layer
		execBlock($key . '_after', 'ignore');

		if (isset($this->opt['indent'][$key]))
			echo '</inden@zi=', $key, '>';

		// !! We should probably move this directly to template_html_after() and forget the buffering thing...
		if ($key === 'html' && !AJAX && !$this->hidden)
			db_debug_junk();
	}

	/**
	 * Returns the name of the first valid layer/block in the list, or false if nothing was found.
	 *
	 * @param string $targets A layer or block, or array of layers or blocks to look for. Leave empty to use the default layer.
	 * @param string $where The magic keyword. See definition for ::op().
	 */
	private function find($targets = '', $where = '')
	{
		// Plugins should provide a 'default' fallback if they consider it vital to show the block, e.g. array('sidebar', 'default').
		// Find the first target that isn't wishful thinking.
		foreach ((array) $targets as $target)
		{
			if (empty($target))
				$target = 'target';
			if (isset($this->layers[$target]) || $this->has_block($target)) // The generic has() is a few % slower.
				return $target;
		}

		// No valid target found.
		return false;
	}

	private function list_blocks($items)
	{
		$blocks = array();
		foreach ($items as $key => $val)
		{
			if (is_array($val))
				$blocks[$key] = $this->list_blocks($val);
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
	private function insert_layer($source, $target = 'default', $where = 'outer')
	{
		$lay = $this->parent($target);
		$lay = $lay ? $lay : 'default';
		if (!isset($this->layers[$lay]))
			return false;
		$dest =& $this->layers[$lay];

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
			$this->reindex();
		return true;
	}

	// Helper function to remove a layer from the page.
	private function remove_layer($layer)
	{
		// Does the layer at least exist...?
		if (!isset($this->layers[$layer]) || $layer === 'default')
			return false;

		// Determine whether removing this layer would also remove the default layer. Which you may not.
		$current = 'default';
		$loop = true;
		while ($loop)
		{
			$loop = false;
			foreach ($this->layers as $id => &$curlay)
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
		$this->skeleton = $this->remove_item($layer);
		$this->reindex();
		return true;
	}

	private function remove_item($item, $from = array(), $level = 0)
	{
		if (empty($from))
			$from = $this->skeleton;

		$ret = array();
		foreach ($from as $key => $val)
			if ($key !== $item)
				$ret[$key] = is_array($val) && !empty($val) ? $this->remove_item($item, $val, $level + 1) : $val;

		return $ret;
	}

	/**
	 * Add blocks or layers to the skeleton.
	 *
	 * @param string $blocks The name of the blocks or layers to be added.
	 * @param string $target Which layer to load this function in, e.g. 'default' (main contents), 'top' (above the main area), 'sidebar' (sidebar area), etc. If using 'before' or 'after', you may instead specify a block name.
	 * @param string $where Where should we add the item? Check the comments inside the function for a fully documented list of positions. Non-default layers should use wetem::add()/$skeleton->add() rather than load().
	 * @param bool $force Only used when $blocks shouldn't be tempered with.
	 */
	private function op($blocks, $target, $where, $force = false)
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
			$blocks = $this->list_blocks((array) $blocks);
		$has_layer = (bool) count(array_filter($blocks, 'is_array'));
		$to = $this->find($where === 'generic' ? array_keys($target) : $target, $where);

		if (empty($to))
			return false;

		// If working on a flexible array, choose the action requested for our final target.
		if ($where === 'generic')
			$where = $target[$to];

		// If we're using a fallback, ensure we're not in a special state where
		// the chrome is hidden, as we're not sure we can accept new content.
		if ($to === 'default' && $this->hidden && is_array($target) && reset($target) !== 'default')
			return false;

		// If a mod requests to replace the contents of the sidebar, just smile politely.
		if (($where === 'load' || $where === 'replace') && $to === 'sidebar')
			$where = 'add';

		if ($where === 'load' || $where === 'replace')
		{
			// Most likely case: no child layers (or erase all). Replace away!
			if ($where === 'replace' || !isset($this->layers[$to]) || count($this->layers[$to]) === count($this->layers[$to], COUNT_RECURSIVE))
			{
				$this->layers[$to] = $blocks;
				// If we erase, we might have to delete layer entries.
				if ($where === 'replace' || $has_layer)
					$this->reindex();
				return $to;
			}

			// Otherwise, we're in for some fun... :-/
			$keys = array_keys($this->layers[$to]);
			foreach ($keys as $id)
			{
				if (!is_array($this->layers[$to][$id]))
				{
					// We're going to insert our item(s) right before the first block we find...
					if (!isset($offset))
					{
						$offset = array_search($id, $keys, true);
						$this->layers[$to] = array_merge(array_slice($this->layers[$to], 0, $offset, true), $blocks, array_slice($this->layers[$to], $offset, null, true));
					}
					// ...And then we delete the other block(s) and leave the layers where they are.
					unset($this->layers[$to][$id]);
				}
			}

			// So, we found a layer but no blocks..? Add our blocks at the end.
			if (!isset($offset))
				$this->layers[$to] += $blocks;

			$this->reindex();
			return $to;
		}

		elseif ($where === 'add')
			$this->layers[$to] += $blocks;

		elseif ($where === 'first')
			$this->layers[$to] = array_merge(array_reverse($blocks), $this->layers[$to]);

		elseif ($where === 'before' || $where === 'after')
		{
			foreach ($this->layers as &$layer)
			{
				if (!isset($layer[$to]))
					continue;

				$layer = array_insert($layer, $to, $blocks, $where === 'after');
				$this->reindex();
				return $to;
			}
		}
		else
			return false;

		if ($has_layer)
			$this->reindex();

		return $to;
	}
}

/*************
 *
 * This is a helper class that holds a template layer or block.
 *
 * It is provided to allow plugins to use method chaining on a single item.
 * If you don't need to chain calls, then use skeleton object methods instead.
 *
 * You can access an item by using $skeleton->get('item') or wetem::get('item'), and then you would apply calls to it.
 * For instance, if you wanted to get block sidebar_dummy's parent, rename it to sidebar2
 * and insert a new layer into it, you may want to do it this way:
 *
 * wetem::get('sidebar_dummy')->parent()->rename('sidebar2')->inner('inside_sidebar');
 * $skeleton->get('pluginbar')->parent()->rename('explugin')->inner('inside_plugin');
 *
 *************/

final class weSkeletonItem
{
	private $target;
	private $skeleton;

	function __construct($that, $to = 'default')
	{
		$this->target = $to;
		$this->skeleton = $that;
	}

	// Remove specified layer/block from the skeleton. (Non-chainable)
	function remove()
	{
		$this->skeleton->remove($this->target);
	}

	// The following are chainable aliases to the equivalent functions.
	function load($items)		{ $this->skeleton->load($this->target, $items); return $this; }
	function replace($items)	{ $this->skeleton->replace($this->target, $items); return $this; }
	function add($items)		{ $this->skeleton->add($this->target, $items); return $this; }
	function first($items)		{ $this->skeleton->first($this->target, $items); return $this; }
	function before($items)		{ $this->skeleton->before($this->target, $items); return $this; }
	function after($items)		{ $this->skeleton->after($this->target, $items); return $this; }
	function insert($items)		{ $this->skeleton->insert($this->target, $items); return $this; }
	function move($layer, $p)	{ $this->skeleton->move($this->target, $layer, $p); return $this; }
	function rename($layer)		{ $this->skeleton->rename($this->target, $layer); return $this; }
	function outer($layer)		{ $this->skeleton->outer($this->target, $layer); return $this; }
	function inner($layer)		{ $this->skeleton->inner($this->target, $layer); return $this; }
	function skip()				{ $this->skeleton->skip($this->target); return $this; }
	function parent()			{ return $this->skeleton->get($this->skeleton->parent($this->target)); }
}

/*************
 *
 * This is the singleton object for the main skeleton.
 *
 * To facilitate calling it without adding a global variable, it is enclosed in
 * a static class called wetem that redirects all calls to the regular dynamic class.
 *
 * For instance, instead of doing $mainSkeleton->load('block_name'), you simply call wetem::load('block_name').
 * Generally, simply replace a "$skeleton->" call with "wetem::" and you're in control of the main skeleton.
 *
 *************/

final class wetem
{
	private static $main = null; // container for main skeleton

	// There can be only one main skeleton.
	private function __clone()
	{
		return false;
	}

	// Bootstrap's bootstraps
	static function createMainSkeleton()
	{
		global $context;

		// Squeletto ergo sum
		if (self::$main != null)
			return;

		self::$main = new weSkeleton('main');
	}

	static function has($item)									{ return self::$main->has($item); }
	static function has_block($block)							{ return self::$main->has_block($block); }
	static function has_layer($layer)							{ return self::$main->has_layer($layer); }
	static function render()									{		 self::$main->render(); }
	static function get($targets = '')							{ return self::$main->get($targets); }
	static function before($target, $contents = '')				{ return self::$main->before($target, $contents); }
	static function after($target, $contents = '')				{ return self::$main->after($target, $contents); }
	static function insert($target, $contents = '')				{ return self::$main->insert($target, $contents); }
	static function skip($target)								{ return self::$main->skip($target); }
	static function remove($target)								{		 self::$main->remove($target); }
	static function move($item, $target, $where)				{ return self::$main->move($item, $target, $where); }
	static function parent($child)								{ return self::$main->parent($child); }
	static function load($target, $contents = '')				{ return self::$main->load($target, $contents); }
	static function add($target, $contents = '')				{ return self::$main->add($target, $contents); }
	static function first($target, $contents = '')				{ return self::$main->first($target, $contents); }
	static function replace($target, $contents = '')			{ return self::$main->replace($target, $contents); }
	static function rename($target, $new_name)					{ return self::$main->rename($target, $new_name); }
	static function outer($target, $new_layer = '')				{ return self::$main->outer($target, $new_layer); }
	static function inner($target, $new_layer = '')				{ return self::$main->inner($target, $new_layer); }
	static function hide($layer = '')							{ return self::$main->hide($layer); }
	static function layer($layer, $target = '', $where = '')	{ return self::$main->layer($layer, $target, $where ? $where : 'replace'); }
}
