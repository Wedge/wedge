<?php
/**
 * This file manages the minification of PHP files.
 * Note that, like anything not loaded through loadSource,
 * you CANNOT edit it directly from a plugin!
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

// Cache a minified PHP file.
function minify_php($file, $remove_whitespace = false)
{
	global $save_strings;

	// Replace comments with equivalent whitespace, and protect strings.
	$php = clean_me_up($file, $remove_whitespace);

	// Do the actual process of removing whitespace.
	if ($remove_whitespace)
	{
		$php = preg_replace('~\s+~', ' ', $php);
		$php = preg_replace('~(?<=[^a-zA-Z0-9_.])\s+|\s+(?=[^$a-zA-Z0-9_.])~', '', $php);
		$php = preg_replace('~(?<=[^0-9.])\s+\.|\.\s+(?=[^0-9.])~', '.', $php); // 2 . 1 != 2.1
		$php = str_replace(',)', ')', $php);
	}
	else // Remove at least spaces in empty lines...
		$php = preg_replace('~[\t ]+(?=\n)~', '', $php);

	// Restore saved strings.
	$pos = 0;
	foreach ($save_strings as $str)
		if (($pos = strpos($php, "\x0f", $pos)) !== false)
			$php = substr_replace($php, $str, $pos, 1);

	file_put_contents($file, $php);
}

// Remove comments and protect strings.
function clean_me_up($file, $remove_whitespace = false)
{
	global $save_strings, $is_output_buffer;

	$search_for = array('/*', '//', "'", '"');

	// Set this to true if calling loadSource within an output buffer handler.
	if (empty($is_output_buffer) && $remove_whitespace)
	{
		$search_for = array("'", '"');
		$php = php_strip_whitespace($file);
	}
	else
		$php = file_get_contents($file);

	$save_strings = array();
	$pos = 0;

	while (true)
	{
		$pos = find_next($php, $pos, $search_for);
		if ($pos === false)
			return $php;

		$look_for = $php[$pos];
		if ($look_for === '/')
		{
			if ($php[$pos + 1] === '/') // Remove //
				$look_for = array("\r", "\n", "\r\n");
			else // Remove /* ... */
				$look_for = '*/';
		}
		else
		{
			$next = find_next($php, $pos + 1, $look_for);
			if ($next === false) // Shouldn't be happening.
				return $php;
			if ($php[$next] === "\r" && $php[$next + 1] === "\n")
				$next++;
			$save_strings[] = substr($php, $pos, $next + 1 - $pos);
			$php = substr_replace($php, "\x0f", $pos, $next + 1 - $pos);
			continue;
		}

		$end = find_next($php, $pos + 1, $look_for);
		if ($end === false)
			return $php;
		if (!is_array($look_for))
			$end += strlen($look_for);
		$temp = substr($php, $pos, $end - $pos);

		$breaks = substr_count($temp, "\n") + substr_count($temp, "\r") - substr_count($temp, "\r\n");
		$php = substr_replace($php, str_pad(str_repeat("\n", $breaks), $end - $pos), $pos, $end - $pos);
		$pos = $end + 1;
	}
}

function find_next(&$php, $pos, $search_for)
{
	if (is_array($search_for))
	{
		$positions = array();
		foreach ((array) $search_for as $item)
		{
			$position = strpos($php, $item, $pos);
			if ($position !== false)
				$positions[] = $position;
		}
		if (empty($positions))
			return false;
		$next = min($positions);
	}
	else
	{
		$next = strpos($php, $search_for, $pos);
		if ($next === false)
			return false;
	}

	$check_before = $next;
	$escaped = false;
	while (--$check_before >= 0 && $php[$check_before] == '\\')
		$escaped = !$escaped;
	if ($escaped)
		return find_next($php, ++$next, $search_for);
	return $next;
}

function wedge_parse_mod_tags(&$file, $name, $params = array())
{
	$tags = array();
	if (strpos($file, '</' . $name . '>') === false)
		return $tags;
	$params = (array) $params;

	// The CDATA stuff, to be honest, is only there for XML warriors. It doesn't actually allow you to use </$name> inside it.
	if (!preg_match_all('~<' . $name . '\b([^>]*)>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</' . $name . '>~s', $file, $matches, PREG_SET_ORDER))
		return $tags;

	$empty_list = array();
	foreach ($params as $param)
		$empty_list[$param] = '';
	foreach ($matches as $match)
	{
		$item = $empty_list;
		$item['value'] = $match[2];
		// No parameters? Just return now...
		if (empty($match[1]))
		{
			$tags[] = $item;
			continue;
		}

		// Now we'll retrieve the parameters individually, to allow for any param order.
		foreach ($params as $param)
			if (preg_match('~\b' . $param . '="([^"]*)"~', $match[1], $val))
				$item[$param] = $val[1];
		$tags[] = $item;
	}
	return $tags;
}

// Edit a source file from within a plugin.
function apply_plugin_mods($cache, $file)
{
	global $context;
	static $oplist = array();

	$is_template = strpos($cache, '.template.php') !== false;
	$folder = ROOT_DIR . '/gz/' . ($is_template ? 'html' : 'app');
	if (!file_exists($folder))
	{
		mkdir($folder);
		copy(ROOT_DIR . '/gz/index.php', $folder . '/index.php');
	}
	copy($cache, $file);

	// Phase 1: Waste no time if no plugins are enabled.
	if (empty($context['enabled_plugins']))
		return;

	$this_file = $error = false;
	foreach ($context['enabled_plugins'] as $plugin)
	{
		$mod = ROOT_DIR . '/plugins/' . $plugin . '/mods.xml';
		if (empty($datalist[$mod]) && !file_exists($mod))
			continue;
		if (!isset($oplist[$mod]))
		{
			$data = file_get_contents($mod);
			$oplist[$mod] = wedge_parse_mod_tags($data, 'file', 'name');
		}
		$tags = $oplist[$mod];

		// Now we'll be looking for <file name="filename"> tags; the name parameter accepts partial
		// names, such as 'Home.php' or 'Home.template.php', or more specific paths. Don't use
		// 'Home' if you don't want to touch both the app and html files with the same prefix!
		// Also, to avoid patching a plugin's templates, use 'core/html/...' as needed.
		$ops = array();
		foreach ($oplist[$mod] as $perfile)
			if (isset($perfile['name']) && strpos($cache, $perfile['name']) !== false)
				$ops[] = $perfile['value'];
		if (empty($ops))
			continue;
		$ops = implode($ops);
		$ops = wedge_parse_mod_tags($ops, 'operation');
		if (empty($ops))
			continue;
		if ($this_file === false)
			$this_file = file_get_contents($file);

		// Phase 2: ???
		foreach ($ops as $op)
		{
			$where = wedge_parse_mod_tags($op['value'], 'search', 'position');
			$add = wedge_parse_mod_tags($op['value'], 'add');
			if (empty($where[0]) || empty($add[0]))
				continue;
			$offset = strpos($this_file, $where[0]['value']);
			if ($offset === false)
			{
				$error = true;
				break;
			}
			$save_me = true;
			$position = isset($where[0]['position']) && in_array($where[0]['position'], array('before', 'after', 'replace')) ? $where[0]['position'] : 'after';
			if ($position == 'before')
				$this_file = substr_replace($this_file, $add[0], $offset, 0);
			elseif ($position == 'after')
				$this_file = substr_replace($this_file, $add[0], $offset + strlen($where[0]['value']), 0);
			elseif ($position == 'replace')
				$this_file = substr_replace($this_file, $add[0], $offset, strlen($where[0]['value']));
		}

		// If an error was found, I'm afraid we'll have to rollback.
		if ($error)
		{
			$context['enabled_plugins'] = array_diff($context['enabled_plugins'], $plugin);
			log_error('Couldn\'t apply data from <em>' . $plugin . '</em> plugin to file <tt>' . $cache . '</tt>. Disabling plugin automatically.');
			updateSettings(array('enabled_plugins' => implode(',', $context['enabled_plugins'])));
			clean_cache('php', '', CACHE_DIR . '/app');
			clean_cache('php', '', CACHE_DIR . '/html');
			exit('Plugin error. Please reload this page.');
		}
		$error = false;
	}

	// Phase 3: PROFIT!
	if (!empty($save_me))
		file_put_contents($file, $this_file);
}
