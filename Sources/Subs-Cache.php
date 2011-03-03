<?php
/**********************************************************************************
* Subs-Cache.php                                                                  *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC5                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

/**
 * This file handles functions that allow caching data in Wedge: regular data (cache_get/put_data), CSS and JavaScript.
 * It also ensures that CSS and JS files are properly parsed and compressed before they're cached.
 *
 * @package wedge
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * This function adds a string to the footer Javascript that relies on jQuery and script.js being loaded.
 * Several strings can be passed as parameters, allowing for easier conversion from an "echo" to an "add_js()" call.
 */
function add_js()
{
	global $context, $footer_coding;

	if (empty($footer_coding))
	{
		$footer_coding = true;
		$context['footer_js'] .= '
<script><!-- // --><[!CDATA[';
	}
	$args = func_get_args();
	$context['footer_js'] .= implode('', $args);
}

/**
 * This function adds a string to the footer Javascript. Because this will be shown before jQuery or script.js are loaded,
 * you must not use any of the functions provided by default in Wedge. This is only good for quick snippets that manipulate document IDs.
 */
function add_js_inline()
{
	global $context;

	$args = func_get_args();
	$context['footer_js_inline'] .= implode('', $args);
}

/**
 * This function adds one or more minified, gzipped files to the footer Javascript. It takes care of everything. Good boy.
 *
 * @param mixed $files A filename or an array of filenames, with a relative path set to the theme root folder.
 * @param boolean $is_direct_url Set to true if you want to add complete URLs (e.g. external libraries), with no minification and no gzipping.
 * @param boolean $is_out_of_flow Set to true if you want to get the URL immediately and not put it into the JS flow. Used for jQuery/script.js.
 * @return string The generated code for direct inclusion in the source code, if $out_of_flow is set. Otherwise, nothing.
 */
function add_js_file($files = array(), $is_direct_url = false, $is_out_of_flow = false)
{
	global $context, $modSettings, $footer_coding, $settings, $cachedir, $boardurl;

	if (!is_array($files))
		$files = (array) $files;

	// Delete all duplicates.
	$files = array_keys(array_flip($files));

	if ($is_direct_url)
	{
		if (!empty($footer_coding))
		{
			$footer_coding = false;
			$context['footer_js'] .= '
// ]]></script>';
		}
		$context['footer_js'] .= '
<script src="' . implode('"></script>
<script src="', $files) . '"></script>';
		return;
	}

	$id = '';
	$latest_date = 0;
	$is_default_theme = true;
	$not_default = $settings['theme_dir'] !== $settings['default_theme_dir'];

	foreach ($files as &$file)
	{
		$target = $not_default && file_exists($settings['theme_dir'] . '/' . $file) ? 'theme_' : (file_exists($settings['default_theme_dir'] . '/' . $file) ? 'default_theme_' : false);
		if (!$target)
			continue;

		$is_default_theme &= $target === 'default_theme_';
		$add = $settings[$target . 'dir'] . '/' . $file;
		// Turn scripts/name.js into 'name', and plugin/other.js into 'plugin_other' for the final filename.
		$id .= str_replace(array('scripts/', '/'), array('', '_'), substr(strrchr($file, '/'), 1, -3)) . '-';
		$latest_date = max($latest_date, filemtime($add));
	}

	$id = $is_default_theme ? $id : substr(strrchr($settings['theme_dir'], '/'), 1) . '-' . $id;
	$id = !empty($modSettings['obfuscate_filenames']) ? md5(substr($id, 0, -1)) . '-' : $id;

	$can_gzip = !empty($modSettings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? ($context['browser']['is_safari'] ? '.jgz' : '.js.gz') : '.js';

	$final_file = $cachedir . '/' . $id . $latest_date . $ext;
	if (!file_exists($final_file))
		wedge_cache_js($id, $latest_date, $final_file, $files, $can_gzip, $ext);

	$final_script = $boardurl . '/cache/' . $id . $latest_date . $ext;

	// Do we just want the URL?
	if ($is_out_of_flow)
		return $final_script;

	if (!empty($footer_coding))
	{
		$footer_coding = false;
		$context['footer_js'] .= '
// ]]></script>';
	}
	$context['footer_js'] .= '
<script src="' . $final_script . '"></script>';
}

/**
 * This function adds one or more minified, gzipped files to the header stylesheets. It takes care of everything. Good boy.
 *
 * @param mixed $files A filename or an array of filenames, with a relative path set to the theme root folder. Just specify the filename, like 'index', if it's a file from the current styling.
 * @param boolean $add_link Set to true if you want Wedge to automatically add the link tag around the URL and move it to the header.
 * @return string The generated code for direct inclusion in the source code, if $out_of_flow is set. Otherwise, nothing.
 */
function add_css_file($files = array(), $add_link = false)
{
	global $context, $modSettings, $settings, $cachedir, $boardurl;

	if (!is_array($files))
		$files = (array) $files;

	// Delete all duplicates.
	$files = array_keys(array_flip($files));

	$id = '';
	$latest_date = 0;
	$is_default_theme = true;
	$not_default = $settings['theme_dir'] !== $settings['default_theme_dir'];
	$styling = empty($context['styling']) ? 'styles' : $context['styling'];

	foreach ($files as &$file)
	{
		if (strpos($file, '.css') === false)
		{
			$dir = $styling;
			$ofile = $file;
			$file = $dir . '/' . $ofile . '.css';
			// Does this file at least exist in the current styling...? If not, try the parent styling, until our hands are empty.
			while (!empty($dir) && !file_exists($settings['theme_dir'] . '/' . $file) && !file_exists($settings['default_theme_dir'] . '/' . $file))
			{
				$dir = dirname($dir);
				$file = $dir . '/' . $ofile . '.css';
			}
		}
		$target = $not_default && file_exists($settings['theme_dir'] . '/' . $file) ? 'theme_' : (file_exists($settings['default_theme_dir'] . '/' . $file) ? 'default_theme_' : false);
		if (!$target)
			continue;

		$is_default_theme &= $target === 'default_theme_';
		// Turn styles/name.css into 'name', and othertheme/file.css into 'othertheme_css' for the final filename.
		$id .= str_replace(array('styles/', '/'), array('', '_'), substr(strrchr($file, '/'), 1, -4)) . '-';
		$file = $settings[$target . 'dir'] . '/' . $file;
		$latest_date = max($latest_date, filemtime($file));
	}

	$id = $is_default_theme ? $id : substr(strrchr($settings['theme_dir'], '/'), 1) . '-' . $id;
	$id = !empty($modSettings['obfuscate_filenames']) ? md5(substr($id, 0, -1)) . '-' : $id;

	$can_gzip = !empty($modSettings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? ($context['browser']['is_safari'] ? '.cgz' : '.css.gz') : '.css';

	$final_file = $cachedir . '/' . $id . $latest_date . $ext;
	if (!file_exists($final_file))
		wedge_cache_css_files($id, $latest_date, $final_file, $files, $can_gzip, $ext);

	$final_script = $boardurl . '/cache/' . $id . $latest_date . $ext;

	// Do we just want the URL?
	if (!$add_link)
		return $final_script;

	$context['header'] .= '
	<link rel="stylesheet" src="' . $final_script . '">';
}

/**
 * Add a generic CSS file to the list of files loaded on this page, in the form of "admin" (no folder, no extension.)
 *
 * @param array $style_sheets List of CSS files to add to $context['css_generic_files']
 */
function wedge_add_css($style_sheets)
{
	global $context;

	$context['css_generic_files'] = array_merge($context['css_generic_files'], (array) $style_sheets);
}

/**
 * Create a compact CSS file that concatenates and compresses a list of existing CSS files, also fixing relative paths.
 *
 * @return int Returns the current timestamp, for use in caching
 */
function wedge_cache_css()
{
	global $settings, $modSettings, $css_vars, $context, $db_show_debug, $cachedir, $boarddir, $boardurl;

	// Mix CSS files together!
	$css = array();
	$latest_date = 0;
	$is_default_theme = true;
	$not_default = $settings['theme_dir'] !== $settings['default_theme_dir'];
	$context['extra_styling_css'] = '';

	foreach ($context['css_folders'] as $folder)
	{
		$target = $not_default && file_exists($settings['theme_dir'] . '/' . $folder) ? 'theme_' : 'default_theme_';
		$is_default_theme &= $target === 'default_theme_';
		$fold = $settings[$target . 'dir'] . '/' . $folder . '/';

		if ($folder !== 'styles' && file_exists($fold . 'settings.xml'))
		{
			$set = file_get_contents($fold . '/settings.xml');
			// If this is a replace-type styling, erase all of the parent files.
			if (strpos($set, '</type>') !== false && preg_match('~<type>([^<]+)</type>~', $set, $match) && trim($match[1]) === 'replace')
				$css = array();
		}

		foreach ($context['css_generic_files'] as $file)
		{
			$add = $fold . $file . '.css';
			if (file_exists($add))
			{
				$css[] = $add;
				if ($db_show_debug === true)
					$context['debug']['sheets'][] = $file . ' (' . basename($settings[$target . 'url']) . ')';
				$latest_date = max($latest_date, filemtime($add));
			}
		}
	}

	$id = $is_default_theme ? '' : substr(strrchr($settings['theme_dir'], '/'), 1) . '-';
	$id .= $folder === 'styles' ? 'Wedge' : str_replace('/', '-', strpos($folder, 'styles/') === 0 ? substr($folder, 7) : $folder);

	// The last folder in the list is the deepest styling.
	// It's the one that gets CSS/JavaScript attention.
	if (!empty($set))
	{
		if (strpos($set, '</css>') !== false && preg_match_all('~<css(?:\s+for="([^"]+)")?\>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</css>~s', $set, $matches, PREG_SET_ORDER))
			foreach ($matches as $match)
				if (empty($match[1]) || in_array($context['browser']['agent'], explode(',', $match[1])))
					$context['extra_styling_css'] .= rtrim($match[2], "\t");

		if (strpos($set, '</code>') !== false && preg_match_all('~<code(?:\s+for="([^"]+)")?\>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</code>~s', $set, $matches, PREG_SET_ORDER))
			foreach ($matches as $match)
				if (empty($match[1]) || in_array($context['browser']['agent'], explode(',', $match[1])))
					add_js(rtrim($match[2], "\t"));

		if (strpos($set, '</block>') !== false && preg_match_all('~<block\s+name="([^"]+)">(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</block>~s', $set, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				$block = explode('|', $match[2]);
				$context['blocks_to_search'][$match[1]] = '<we:' . $match[1] . '>';
				$context['blocks_to_search'][$match[1] . '_end'] = '</we:' . $match[1] . '>';
				$context['blocks_to_replace'][$match[1]] = $block[0];
				$context['blocks_to_replace'][$match[1] . '_end'] = $block[1];
			}
		}
	}

	$can_gzip = !empty($modSettings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? ($context['browser']['is_safari'] ? '.cgz' : '.css.gz') : '.css';
	// No need to have all URLs say 'index-sections'...
	unset($context['css_generic_files'][0], $context['css_generic_files'][1]);
	if (!empty($context['css_generic_files']))
		$id .= '-' . implode('-', $context['css_generic_files']);

	$context['cached_css'] = $boardurl . '/cache/' . $id . '-' . $latest_date . $ext;
	$final_file = $cachedir . '/' . $id . '-' . $latest_date . $ext;

	// Is the file already cached and not outdated? If not, recache it.
	if (!file_exists($final_file) || filemtime($final_file) < $latest_date)
		wedge_cache_css_files($id, $latest_date, $final_file, $css, $can_gzip, $ext);
}

function wedge_cache_css_files($id, $latest_date, $final_file, $css, $can_gzip, $ext)
{
	global $settings, $modSettings, $css_vars, $context, $cachedir, $boarddir, $boardurl;

	// Delete cached versions, unless they have the same timestamp (i.e. up to date.)
	if (is_callable('glob'))
		foreach (glob($cachedir . '/' . $id . '-*.*') as $del)
			if (!strpos($del, $latest_date))
				@unlink($del);

	$final = '';
	$discard_dir = strlen($boarddir) + 1;

	// Load our sweet, short and fast CSS parser
	loadSource('Class-CSS');

	$plugins = array(
		new wecss_mixin(),		// CSS mixins (mixin hello($world: 0))
		new wecss_var(),		// CSS variables ($hello_world)
		new wecss_func(),		// CSS functions (color transforms)
		new wecss_nesting(),	// Nested selectors (.hello { .world { color: 0 } }) + selector inheritance (.hello { base: .world })
		new wecss_math()		// Math function (math(1px + 3px), math((4*$var)/2em)...)
	);
	// No need to start the Base64 plugin if we can't gzip the result or the browser can't see it...
	// (Probably should use more specific browser sniffing.)
	if ($can_gzip && !$context['browser']['is_ie6'] && !$context['browser']['is_ie7'])
		$plugins[] = new wecss_base64();

	// Default CSS variables (paths are set relative to the cache folder)
	// !!! If subdomains are allowed, should we use absolute paths instead?
	$css_vars = array(
		'$images' => '..' . str_replace($boardurl, '', $settings['images_url']),
		'$theme' => '..' . str_replace($boardurl, '', $settings['theme_url']),
		'$here' => '',
		'$root' => '../',
	);

	// CSS is always minified. It takes just a sec' to do, and doesn't impair anything.
	foreach ($css as $file)
	{
		$css_vars['$here'] = '..' . str_replace($boarddir, '', dirname($file));
		$final .= file_get_contents($file);
	}

	$final = str_replace(array("\r\n", "\r"), "\n", $final); // Always use \n line endings.
	$final = preg_replace('~/\*(?!!).*?\*/~s', '', $final); // Strip comments except...
	preg_match_all('~/\*!(.*?)\*/~s', $final, $comments); // ...for /*! Copyrights */...
	$final = preg_replace('~/\*!.*?\*/~s', '.wedge_comment_placeholder{border:0}', $final); // Which we save.
	$final = preg_replace('~//[ \t][^\n]*~', '', $final); // Strip comments like me. OMG does this mean I'm gonn

	foreach ($plugins as $plugin)
		$plugin->process($final);

	$final = preg_replace('~\s*([+:;,>{}[\]\s])\s*~', '$1', $final);
	// Only the basic CSS3 we actually use. May add more in the future.
	$final = preg_replace_callback('~(?:border-radius|box-shadow|transition):[^\n;]+[\n;]~', 'wedge_fix_browser_css', $final);

	// Remove double quote hacks, remaining whitespace, and the 'final' keyword in its compact form.
	$final = str_replace(
		array('#wedge-quote#', "\n\n", ';;', ';}', "}\n", "\t", ' final{', ' final,', ' final '),
		array('"', "\n", ';', '}', '}', ' ', '{', ',', ' '),
		$final
	);
	// Restore comments as requested.
	foreach ($comments[0] as $comment)
		$final = preg_replace('~\.wedge_comment_placeholder{border:0}~', "\n" . $comment . "\n", $final, 1);
	$final = ltrim($final, "\n");

	// If we find any empty rules, we should be able to remove them.
	// Obviously, don't use content: "{}" or something in your CSS. (Why would you?)
	if (strpos($final, '{}') !== false)
		$final = preg_replace('~[^{}]+{}~', '', $final);

	if ($can_gzip)
		$final = gzencode($final, 9);

	file_put_contents($final_file, $final);
}

/**
 * Add browser-specific prefixes to a few commonly used CSS attributes.
 *
 * @param string $matches The actual CSS contents
 * @return string Updated CSS contents with fixed code
 */
function wedge_fix_browser_css($matches)
{
	global $context;

	if ($context['browser']['is_opera'] && strpos($matches[0], 'bo') !== 0)
		return '-o-' . $matches[0] . ';' . $matches[0];
	if ($context['browser']['is_webkit'])
		return '-webkit-' . $matches[0] . ';' . $matches[0];
	if ($context['browser']['is_gecko'])
		return '-moz-' . $matches[0] . ';' . $matches[0];
	if ($context['browser']['is_ie9'])
		return '-ms-' . $matches[0] . ';' . $matches[0];
	elseif ($context['browser']['is_ie8down'])
		return '';
	return $matches[0];
}

/**
 * Create a compact JS file that concatenates and compresses a list of existing JS files.
 *
 * @param string $id Name of the file to create, unobfuscated, minus the date component
 * @param int $latest_date Date of the most recent JS file in the list, used to force recaching
 * @param string $final_file Final name of the file to create (obfuscated, with date, etc.)
 * @param array $js List of all JS files to concatenate
 * @param bool $gzip Should we gzip the resulting file?
 * @return int Returns the current timestamp, for use in caching
 */
function wedge_cache_js($id, $latest_date, $final_file, $js, $gzip = false)
{
	global $settings, $modSettings, $comments, $cachedir;

	$final = '';
	$dir = $settings['theme_dir'] . '/';

	// Delete cached versions, unless they have the same timestamp (i.e. up to date.)
	if (is_callable('glob'))
		foreach (glob($cachedir . '/' . $id. '*.*') as $del)
			if (!strpos($del, $latest_date))
				@unlink($del);

	$minify = empty($modSettings['minify']) ? 'none' : $modSettings['minify'];

	foreach ($js as $file)
	{
		$cont = file_get_contents($dir . $file);

		// Replace long variable names with shorter ones. Our own quick super-minifier!
		if (preg_match("~/\* Optimize:\n(.*?)\n\*/~s", $cont, $match))
		{
			$match = explode("\n", $match[1]);
			$search = $replace = array();
			foreach ($match as $variable)
			{
				$pair = explode(' = ', $variable);
				$search[] = $pair[0];
				$replace[] = $pair[1];
			}
			$cont = str_replace($search, $replace, $cont);
			if ($minify == 'none')
				$cont = preg_replace("~/\* Optimize:\n(.*?)\n\*/~s", '', $cont);
		}
		$final .= $cont;
	}

	// Call the minify process, either JSMin or Packer.
	if ($minify === 'jsmin')
	{
		loadSource('Class-JSMin');
		$final = JSMin::minify($final);
	}
	elseif ($minify === 'packer')
	{
		// We want to keep the copyright-type comments, starting with /*!, which Packer usually removes...
		preg_match_all("~(/\*!\n.*?\*/)~s", $final, $comments);
		if (!empty($comments[1]))
			$final = preg_replace("~/\*!\n.*?\*/~s", 'WEDGE_COMMENT();', $final);

		loadSource('Class-Packer');
		$packer = new Packer;
		$final = $packer->pack($final);

		if (!empty($comments[1]))
			foreach ($comments[1] as $comment)
				$final = substr_replace($final, "\n" . $comment . "\n", strpos($final, 'WEDGE_COMMENT();'), 16);

		// Adding a semicolon after a function/prototype declaration is mandatory in Packer.
		// The original SMF code didn't bother with that, and developers are advised NOT to
		// follow that 'advice'. If you can't fix your scripts, uncomment the following
		// block and semicolons will be added automatically, at a small performance cost.

		/*
		$max = strlen($final);
		$i = 0;
		$alphabet = array_flip(array_merge(range('A', 'Z'), range('a', 'z')));
		while (true)
		{
			$i = strpos($final, '=function(', $i);
			if ($i === false)
				break;
			$k = strpos($final, '{', $i) + 1;
			$m = 1;
			while ($m > 0 && $k <= $max)
			{
				$d = $final[$k++];
				$m += $d === '{' ? 1 : ($d === '}' ? -1 : 0);
			}
			$e = $k < $max ? $final[$k] : $final[$k - 1];
			if (isset($alphabet[$e]))
			{
				$final = substr_replace($final, ';', $k, 0);
				$max++;
			}
			$i++;
		}
		*/
	}

	if ($gzip)
		$final = gzencode($final, 9);

	file_put_contents($final_file, $final);
}

/**
 * Shows the base CSS file, i.e. index.css and any other files added through {@link wedge_add_css()}.
 */
function theme_base_css()
{
	global $context, $boardurl;

	// We only generate the cached file at the last moment (i.e. when first needed.)
	if (empty($context['cached_css']))
		wedge_cache_css();

	echo '
	<link rel="stylesheet" href="', $context['cached_css'], '">';

	if (!empty($context['extra_styling_css']))
	{
		global $user_info;

		// Replace $behavior with the forum's root URL in context, because pretty URLs complicate things in IE.
		if (strpos($context['extra_styling_css'], '$behavior') !== false)
			$context['extra_styling_css'] = str_replace('$behavior', strpos($boardurl, '://' . $user_info['host']) !== false ? $boardurl
				: preg_replace('~(?<=://)([^/]+)~', $user_info['host'], $boardurl), $context['extra_styling_css']);
		echo "\n\t<style>", $context['extra_styling_css'], "\t</style>";
	}
}

/**
 * Shows the base Javascript calls, i.e. including jQuery and script.js
 *
 * @param boolean $indenting Number of tabs on each new line. For the average anal-retentive web developer.
 */
function theme_base_js($indenting = 0)
{
	global $context;

	$tab = str_repeat("\t", $indenting);
	echo !empty($context['remote_javascript_files']) ? '
' . $tab . '<script src="' . implode('"></script>
' . $tab . '<script src="', $context['remote_javascript_files']) . '"></script>' : '', '
' . $tab . '<script src="', add_js_file($context['javascript_files'], false, true), '"></script>';
}

/**
 * Cleans some or all of the files stored in the file cache.
 *
 * @param string $type Optional, designates the file prefix that must be matched in order to be cleared from the file cache folder, typically 'data', to prune 'data_*.php' files.
 * @param string $extensions Optional, a comma-separated list of 3-char file extensions that should be pruned. 'php' by default. Use '.js' instead of 'js' for JavaScript files.
 * @todo Figure out a better way of doing this and get rid of $sourcedir being globalled again.
 */
function clean_cache($type = '', $extensions = 'php')
{
	global $cachedir, $sourcedir;

	// No directory = no game.
	if (!is_dir($cachedir))
		return;

	// Remove the files in SMF's own disk cache, if any
	$dh = scandir($cachedir);
	$ext = array_flip(explode(',', $extensions));
	$len = strlen($type);
	foreach ($dh as $file)
		if ($file === '.' && $file !== '..' && $file !== 'index.php' && $file !== '.htaccess' && (!$type || substr($file, 0, $len) == $type) && isset($exts[substr($file, -3)]))
			@unlink($cachedir . '/' . $file);

	// Invalidate cache, to be sure!
	// ... as long as Load.php can be modified, anyway.
	@touch($sourcedir . '/Load.php');
	clearstatcache();
}

/**
 * Load a cache entry, and if the cache entry could not be found, load a named file and call a function to get the relevant information.
 *
 * The cache-serving function must consist of an array, and contain some or all of the following:
 * - data, required, which is the content of the item to be cached
 * - expires, required, the timestamp at which the item should expire
 * - refresh_eval, optional, a string containing a piece of code to be evaluated that returns boolean as to whether some external factor may trigger a refresh
 * - post_retri_eval, optional, a string containing a piece of code to be evaluated after the data has been updated and cached
 *
 * Refresh the cache if either:
 * - Caching is disabled.
 * - The cache level isn't high enough.
 * - The item has not been cached or the cached item expired.
 * - The cached item has a custom expiration condition evaluating to true.
 * - The expire time set in the cache item has passed (needed for Zend).
 */
function cache_quick_get($key, $file, $function, $params, $level = 1)
{
	global $modSettings, $sourcedir;

	if (empty($modSettings['cache_enable']) || $modSettings['cache_enable'] < $level || !is_array($cache_block = cache_get_data($key, 3600)) || (!empty($cache_block['refresh_eval']) && eval($cache_block['refresh_eval'])) || (!empty($cache_block['expires']) && $cache_block['expires'] < time()))
	{
		// !!! Convert this to loadSource sometime
		require_once($sourcedir . '/' . $file);
		$cache_block = call_user_func_array($function, $params);

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= $level)
			cache_put_data($key, $cache_block, $cache_block['expires'] - time());
	}

	// Some cached data may need a freshening up after retrieval.
	if (!empty($cache_block['post_retri_eval']))
		eval($cache_block['post_retri_eval']);

	return $cache_block['data'];
}

/**
 * Store an item in the cache; supports multiple methods of which one is chosen by the admin in the server settings area.
 *
 * Important: things in the cache cannot be relied or assumed to continue to exist; cache misses on both reading and writing are possible and in some cases, frequent.
 *
 * This function supports the following cache methods:
 * - Memcache
 * - eAccelerator
 * - Alternative PHP Cache
 * - Zend Platform/ZPS
 * - or a custom file cache
 *
 * @param string $key A string that denotes the identity of the data being saved, and for later retrieval.
 * @param mixed $value The raw data to be cached. This may be any data type but it will be serialized prior to being stored in the cache.
 * @param int $ttl The time the cache is valid for, in seconds. If a request to retrieve is received after this time, the item will not be retrieved.
 * @todo Remove cache types that are obsolete and no longer maintained.
 */
function cache_put_data($key, $value, $ttl = 120)
{
	global $boardurl, $sourcedir, $modSettings, $memcached;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;

	if (empty($modSettings['cache_enable']) && !empty($modSettings))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'put', 's' => $value === null ? 0 : strlen(serialize($value)));
		$st = microtime();
	}

	$key = md5($boardurl . filemtime($sourcedir . '/Load.php')) . '-SMF-' . strtr($key, ':', '-');
	$value = $value === null ? null : serialize($value);

	// The simple yet efficient memcached.
	if (function_exists('memcache_set') && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '')
	{
		// Not connected yet?
		if (empty($memcached))
			get_memcached_server();
		if (!$memcached)
			return;

		memcache_set($memcached, $key, $value, 0, $ttl);
	}
	// eAccelerator...
	elseif (function_exists('eaccelerator_put'))
	{
		if (mt_rand(0, 10) == 1)
			eaccelerator_gc();

		if ($value === null)
			@eaccelerator_rm($key);
		else
			eaccelerator_put($key, $value, $ttl);
	}
	// Alternative PHP Cache, ahoy!
	elseif (function_exists('apc_store'))
	{
		// An extended key is needed to counteract a bug in APC.
		if ($value === null)
			apc_delete($key . 'smf');
		else
			apc_store($key . 'smf', $value, $ttl);
	}
	// Zend Platform/ZPS/etc.
	elseif (function_exists('output_cache_put'))
		output_cache_put($key, $value);
	elseif (function_exists('xcache_set') && ini_get('xcache.var_size') > 0)
	{
		if ($value === null)
			xcache_unset($key);
		else
			xcache_set($key, $value, $ttl);
	}
	// Otherwise custom cache?
	else
	{
		if ($value === null)
			@unlink($cachedir . '/data_' . $key . '.php');
		else
		{
			$cache_data = '<' . '?php if (!defined(\'SMF\')) die; if (' . (time() + $ttl) . ' < time()) $expired = true; else{$expired = false; $value = \'' . addcslashes($value, '\\\'') . '\';}?' . '>';
			$fh = @fopen($cachedir . '/data_' . $key . '.php', 'w');
			if ($fh)
			{
				// Write the file.
				set_file_buffer($fh, 0);
				flock($fh, LOCK_EX);
				$cache_bytes = fwrite($fh, $cache_data);
				flock($fh, LOCK_UN);
				fclose($fh);

				// Check that the cache write was successful; all the data should be written
				// If it fails due to low diskspace, remove the cache file
				if ($cache_bytes != strlen($cache_data))
					@unlink($cachedir . '/data_' . $key . '.php');
			}
		}
	}

	if (isset($db_show_debug) && $db_show_debug === true)
		$cache_hits[$cache_count]['t'] = array_sum(explode(' ', microtime())) - array_sum(explode(' ', $st));
}

/**
 * Attempt to retrieve an item from cache, previously stored with {@link cache_put_data()}.
 *
 * This function supports all of the same cache systems that {@link cache_put_data()} does, and with the same caveat that cache misses can occur so content should not be relied upon to exist.
 *
 * @param string $key A string denoting the identity of the key to be retrieved.
 * @param int $ttl The maximum age in seconds that the data can be; if more than the specified time to live, no data will be returned even if it is in cache.
 * @return mixed If retrieving from cache was not possible, null will be returned, otherwise the item will be unserialized and passed back.
 */
function cache_get_data($key, $ttl = 120)
{
	global $boardurl, $sourcedir, $modSettings, $memcached;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;

	if (empty($modSettings['cache_enable']) && !empty($modSettings))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'get');
		$st = microtime();
	}

	$key = md5($boardurl . filemtime($sourcedir . '/Load.php')) . '-SMF-' . strtr($key, ':', '-');

	// Okay, let's go for it memcached!
	if (function_exists('memcache_get') && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '')
	{
		// Not connected yet?
		if (empty($memcached))
			get_memcached_server();
		if (!$memcached)
			return;

		$value = memcache_get($memcached, $key);
	}
	// Again, eAccelerator.
	elseif (function_exists('eaccelerator_get'))
		$value = eaccelerator_get($key);
	// This is the free APC from PECL.
	elseif (function_exists('apc_fetch'))
		$value = apc_fetch($key . 'smf');
	// Zend's pricey stuff.
	elseif (function_exists('output_cache_get'))
		$value = output_cache_get($key, $ttl);
	elseif (function_exists('xcache_get') && ini_get('xcache.var_size') > 0)
		$value = xcache_get($key);
	// Otherwise it's SMF data!
	elseif (file_exists($cachedir . '/data_' . $key . '.php') && filesize($cachedir . '/data_' . $key . '.php') > 10)
	{
		require($cachedir . '/data_' . $key . '.php');
		if (!empty($expired) && isset($value))
		{
			@unlink($cachedir . '/data_' . $key . '.php');
			unset($value);
		}
	}

	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count]['t'] = array_sum(explode(' ', microtime())) - array_sum(explode(' ', $st));
		$cache_hits[$cache_count]['s'] = isset($value) ? strlen($value) : 0;
	}

	if (empty($value))
		return null;
	// If it's broke, it's broke... so give up on it.
	else
		return @unserialize($value);
}

/**
 * Attempt to connect to Memcache server for retrieving cached items.
 *
 * This function acts to attempt to connect (or persistently connect, if persistent connections are enabled) to a memcached instance, looking up the server details from $modSettings['cache_memcached'].
 *
 * If connection is successful, the global $memcached will be a resource holding the connection or will be false if not successful. The function will attempt to call itself in a recursive fashion if there are more attempts remaining.
 *
 * @param int $level The number of connection attempts that will be made, defaulting to 3, but reduced if the number of server connections is fewer than this.
 */
function get_memcached_server($level = 3)
{
	global $modSettings, $memcached, $db_persist;

	$servers = explode(',', $modSettings['cache_memcached']);
	$server = explode(':', trim($servers[array_rand($servers)]));

	// Don't try more times than we have servers!
	$level = min(count($servers), $level);

	// Don't wait too long: yes, we want the server, but we might be able to run the query faster!
	if (empty($db_persist))
		$memcached = memcache_connect($server[0], empty($server[1]) ? 11211 : $server[1]);
	else
		$memcached = memcache_pconnect($server[0], empty($server[1]) ? 11211 : $server[1]);

	if (!$memcached && $level > 0)
		get_memcached_server($level - 1);
}

?>