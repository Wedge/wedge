<?php
/**
 * This file manages the minification of PHP files.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

// Cache a minified PHP file.
function minify_php($file)
{
	global $save_strings;

	$php = preg_replace('~\s+~', ' ', clean_me_up($file));
	$php = preg_replace('~(?<=[^a-zA-Z0-9_.])\s+|\s+(?=[^$a-zA-Z0-9_.])~', '', $php);
	$php = preg_replace('~(?<=[^0-9.])\s+\.|\.\s+(?=[^0-9.])~', '.', $php); // 2 . 1 != 2.1
	$php = str_replace(',)', ')', $php);
	$pos = 0;

	foreach ($save_strings as $str)
		if (($pos = strpos($php, "\x0f", $pos)) !== false)
			$php = substr_replace($php, $str, $pos, 1);

	file_put_contents($file, $php);
}

// Remove comments and protect strings.
function clean_me_up($file, $remove_comments = false)
{
	global $save_strings, $is_output_buffer;

	// Set this to true if calling loadSource within an output buffer handler.
	if (empty($is_output_buffer))
	{
		$php = php_strip_whitespace($file);
		$search_for = array("'", '"');
	}
	else
	{
		$php = file_get_contents($file);
		$search_for = array('/*', '//', "'", '"');
	}

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
