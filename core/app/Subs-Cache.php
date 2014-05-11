<?php
/**
 * This file handles all caching data in Wedge: regular data (cache_get/put_data), CSS and JavaScript.
 * It also ensures that CSS and JS files are properly parsed and compressed by Wess before they're cached.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * This function adds a string to the footer JavaScript that relies on jQuery and script.js being loaded.
 * Several strings can be passed as parameters, allowing for easier conversion from an "echo" to an "add_js()" call.
 */
function add_js()
{
	global $context, $footer_coding;

	if (empty($footer_coding))
	{
		$footer_coding = true;
		$context['footer_js'] .= '
<script>';
	}
	$args = func_get_args();
	$context['footer_js'] .= implode('', $args);
}

/**
 * Alias to add_js that will keep track of possible duplicates and ignore them.
 */
function add_js_unique($code)
{
	static $uniques = array();

	if (isset($uniques[$code]))
		return;
	$uniques[$code] = true;

	add_js($code);
}

/**
 * This function adds a string to the footer JavaScript. Because this will be shown before jQuery or script.js are loaded,
 * you must not use any of the functions provided by default in Wedge. This is only good for quick snippets that manipulate document IDs.
 */
function add_js_inline()
{
	global $context;

	$args = func_get_args();
	$context['footer_js_inline'] .= implode('', $args);
}

/**
 * This function adds one or more minified, gzipped files to the footer JavaScript. It takes care of everything. Good boy.
 *
 * @param mixed $files A filename or an array of filenames, with a relative path set to the theme root folder.
 * @param boolean $is_direct_url Set to true if you want to add complete URLs (e.g. external libraries), with no minification and no gzipping.
 * @param boolean $is_out_of_flow Set to true if you want to get the URL immediately and not put it into the JS flow. Used for jQuery/script.js.
 * @return string The generated code for direct inclusion in the source code, if $out_of_flow is set. Otherwise, nothing.
 */
function add_js_file($files = array(), $is_direct_url = false, $is_out_of_flow = false, $ignore_files = array())
{
	global $context, $settings, $footer_coding;
	static $done_files = array();

	if (!is_array($files))
		$files = (array) $files;

	// Delete all duplicates and already cached files.
	$files = array_diff(array_keys(array_flip($files)), $done_files);
	$done_files = array_merge($done_files, $files);
	if (empty($files))
		return;

	if ($is_direct_url || strpos($files[0], '://') !== false || strpos($files[0], '//') === 0)
	{
		if (!empty($footer_coding))
		{
			$footer_coding = false;
			$context['footer_js'] .= '
</script>';
		}
		$context['footer_js'] .= '
<script src="' . implode('"></script>
<script src="', $files) . '"></script>';
		return;
	}

	$id = '';
	$latest_date = 0;

	foreach ($files as $fid => $file)
	{
		if (!file_exists($add = ROOT_DIR . '/core/javascript/' . $file)) // !! Temp?
		{
			unset($files[$fid]);
			continue;
		}

		// Turn name.min.js into 'name' for the final filename. We won't add keywords
		// that are always in the final filename, to save a few bytes on all pages.
		if (!isset($ignore_files[$file]))
			$id .= str_replace('/', '_', substr(strpos($file, '/') !== false ? substr(strrchr($file, '/'), 1) : $file, 0, strpos($file, '.min.js') !== false ? -7 : -3)) . '-';

		$latest_date = max($latest_date, filemtime($add));
	}

	// If no files were found, don't waste more time.
	if (empty($files))
		return;

	// Add the 'm' keyword for member files -- using 'member' would add an extra couple of bytes per page for no reason.
	$id .= (we::$is_member && !we::$is_admin ? 'm-' : '');
	$id .= (we::$is_admin ? 'a-' : '');
	$id = !empty($settings['obfuscate_filenames']) ? md5(substr($id, 0, -1)) . '-' : $id;
	$latest_date %= 1000000;

	$lang_name = !empty($settings['js_lang'][$id]) && !empty(we::$user['language']) && we::$user['language'] != $settings['language'] ? we::$user['language'] . '-' : '';
	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? (we::is('safari[-5.0]') ? '.jgz' : '.js.gz') : '.js';

	// jQuery never gets updated, so let's be bold and shorten its filename to... The version number!
	$is_jquery = count($files) == 1 && reset($files) == 'jquery-' . $context['jquery_version'] . '.min.js';
	$final_name = $is_jquery ? $context['jquery_version'] : $id . $lang_name . $latest_date;
	if (!file_exists(CACHE_DIR . '/js/' . $final_name . $ext))
	{
		wedge_cache_js($id, $lang_name, $latest_date, $ext, $files, $can_gzip);
		if ($is_jquery)
			@rename(CACHE_DIR . '/js/' . $id . $lang_name . $latest_date . $ext, CACHE_DIR . '/js/' . $final_name . $ext);
	}

	$final_script = CACHE . '/js/' . $final_name . $ext;

	// Do we just want the URL?
	if ($is_out_of_flow)
		return $final_script;

	if (!empty($footer_coding))
	{
		$footer_coding = false;
		$context['footer_js'] .= '
</script>';
	}
	$context['footer_js'] .= '
<script src="' . $final_script . '"></script>';
}

/**
 * This function adds one or more minified, gzipped files to the footer JavaScript, specifically for plugins to use.
 *
 * @param string $plugin_name The name of the plugin in question, e.g. Arantor:MyPlugin.
 * @param mixed $files A filename or an array of filenames, with a relative path set to the plugin's folder.
 * @param boolean $is_direct_url Set to true if you want to add complete URLs (e.g. external libraries), with no minification and no gzipping.
 * @param boolean $is_out_of_flow Set to true if you want to get the URL immediately and not put it into the JS flow. Used for jQuery/script.js.
 * @return string The generated code for direct inclusion in the source code, if $out_of_flow is set. Otherwise, nothing.
 */
function add_plugin_js_file($plugin_name, $files = array(), $is_direct_url = false, $is_out_of_flow = false)
{
	global $context, $settings, $footer_coding;
	static $done_files = array();

	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	if (!is_array($files))
		$files = (array) $files;

	// Direct URL may as well reuse the main handler once we provide the right (full) URLs for it.
	if ($is_direct_url)
	{
		foreach ($files as $k => $v)
			$files[$k] = $context['plugins_url'][$plugin_name] . '/' . $v;
		return add_js_file($files, true, $is_out_of_flow);
	}

	// OK, we're going to be caching files.
	if (empty($done_files[$plugin_name]))
		$done_files[$plugin_name] = array_flip(array_flip($files));
	else
	{
		$files = array_diff(array_keys(array_flip($files)), $done_files[$plugin_name]);
		$done_files[$plugin_name] = array_merge($done_files[$plugin_name], $files);
	}

	$id = '';
	$latest_date = 0;

	foreach ($files as $k => &$file)
	{
		$file = $context['plugins_dir'][$plugin_name] . '/' . $file;
		if (!file_exists($file))
			unset($files[$k]);

		// Turn plugin/other.js into 'plugin_other' for the final filename.
		$id .= str_replace('/', '_', substr(strrchr($file, '/'), 1, strpos($file, '.min.js') !== false ? -7 : -3)) . '-';
		$latest_date = max($latest_date, filemtime($file));
	}

	if (empty($files))
		return;

	$id = substr(strrchr($context['plugins_dir'][$plugin_name], '/'), 1) . '-' . $id;
	$id .= (we::$is_member && !we::$is_admin ? 'm-' : '');
	$id .= (we::$is_admin ? 'a-' : '');
	$id = !empty($settings['obfuscate_filenames']) ? md5(substr($id, 0, -1)) . '-' : $id;
	$latest_date %= 1000000;

	$lang_name = !empty($settings['js_lang'][$id]) && !empty(we::$user['language']) && we::$user['language'] != $settings['language'] ? we::$user['language'] . '-' : '';
	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? (we::is('safari[-5.0]') ? '.jgz' : '.js.gz') : '.js';

	if (!file_exists(CACHE_DIR . '/js/' . $id . $lang_name . $latest_date . $ext))
		wedge_cache_js($id, $lang_name, $latest_date, $ext, $files, $can_gzip, true);

	$final_script = CACHE . '/js/' . $id . $lang_name . $latest_date . $ext;

	// Do we just want the URL?
	if ($is_out_of_flow)
		return $final_script;

	if (!empty($footer_coding))
	{
		$footer_coding = false;
		$context['footer_js'] .= '
</script>';
	}
	$context['footer_js'] .= '
<script src="' . $final_script . '"></script>';
}

/**
 * If you want to use jQuery UI in your plugins, call this simple function.
 * This allows you to ensure it's included once, and uses the correct version
 * of jQuery UI relative to the current version of jQuery used in Wedge.
 * Oh, and please avoid including jQuery UI if you can do without it... So heavy.
 */
function add_jquery_ui()
{
	global $settings;
	static $done = false;

	if ($done)
		return;
	$done = true;

	// !! Note: should we add an $add_css flag to add the CSS file?
	// http://code.jquery.com/ui/$version/themes/base/jquery-ui.css
	$version = '1.10.0';
	$remote = array(
		'google' =>		'ajax.googleapis.com/ajax/libs/jqueryui/' . $version . '/jquery-ui.min.js',
		'microsoft' =>	'ajax.aspnetcdn.com/ajax/jquery.ui/' . $version . '/jquery-ui.min.js',
		'jquery' =>		'code.jquery.com/ui/' . $version . '/jquery-ui.min.js',
	);

	if (empty($settings['jquery_origin']) || $settings['jquery_origin'] === 'local')
		add_js_file('jquery-ui-' . $version . '.min.js');
	else
		add_js_file('//' . $remote[$settings['jquery_origin']]);
}

/**
 * This function adds a string to the header's inline CSS.
 * Several strings can be passed as parameters, allowing for easier conversion from an "echo" to an "add_css()" call.
 * Please note that, obviously, this function will do nothing if you call wetem::hide() or generally don't output the HTML head.
 */
function add_css()
{
	global $context;

	if (empty($context['header_css']))
		$context['header_css'] = '';

	$args = func_get_args();
	$context['header_css'] .= ($args[0][0] !== "\n" ? "\n" : '') . implode('', $args);
}

/**
 * This function adds one or more minified, gzipped files to the header stylesheets. It takes care of everything. Good boy.
 *
 * @param mixed $original_files A filename or an array of filenames, with a relative path set to the theme root folder. Just specify the filename, like 'index', if it's a file from the current skin.
 * @param boolean $add_link Set to false if you want Wedge to return the URL instead of automatically adding the link to your page header.
 * @param boolean $is_main Determines whether this is the primary CSS file list (index.css and other files), which gets special treatment.
 * @return string The generated code for direct inclusion in the source code, if $out_of_flow is set. Otherwise, nothing.
 */
function add_css_file($original_files = array(), $add_link = true, $is_main = false, $ignore_files = array())
{
	global $settings, $context, $db_show_debug, $files;
	static $cached_files = array(), $paths_done = array();

	// Set hardcoded paths aside, e.g. plugin CSS.
	$latest_date = 0;
	$hardcoded_css = array();
	$original_files = (array) $original_files;
	foreach ($original_files as $key => $path)
	{
		if (isset($paths_done[$path]))
		{
			unset($original_files[$key]);
			continue;
		}
		if (strpos($path, '/') !== false)
		{
			unset($original_files[$key]);
			if (file_exists($path))
			{
				$hardcoded_css[] = $path;
				$latest_date = max($latest_date, filemtime($path));
			}
		}
		$paths_done[$path] = true;
	}

	// No files left to handle..?
	if (empty($original_files) && empty($hardcoded_css))
		return false;

	// Delete all duplicates and ensure $original_files is an array.
	$original_files = array_merge(array('common' => 0), array_flip($original_files));
	$files = array_keys($original_files);

	// If we didn't go through the regular theme initialization flow, get the skin options.
	if (!isset($context['skin_folders']))
		wedge_get_skin_options();

	$fallback_folder = rtrim(SKINS_DIR . '/' . reset($context['css_folders']), '/') . '/';
	$deep_folder = rtrim(SKINS_DIR . '/' . end($context['css_folders']), '/') . '/';
	$found_suffixes = array();
	$found_files = array();
	$css = array();

	// Pre-cache a special keyword that always returns true.
	we::$cache['global'] = 'global';

	// !! @todo: the following is quite resource intensive... Maybe we should cache the results somehow?
	// !! e.g. cache for several minutes, but delete cache if the filemtime for current folder or its parent folders was updated.
	foreach ($context['skin_folders'] as $fold)
	{
		if ($fold === $fallback_folder)
			$fallback_folder = '';

		if (empty($cached_files[$fold]))
			$cached_files[$fold] = array_diff((array) @scandir($fold ? $fold : '', 1), array('.', '..', '.htaccess', 'index.php', 'skin.xml', 'custom.xml'));

		// A 'local' suffix means the file should only be parsed if it's in the same folder as the current skin.
		we::$cache['local'] = $fold == $deep_folder ? 'local' : false;

		foreach ($cached_files[$fold] as $file)
		{
			if (substr($file, -4) !== '.css')
				continue;

			$radix = substr($file, 0, strpos($file, '.'));
			if (!isset($original_files[$radix]))
				continue;

			// Get the list of suffixes in the file name.
			$suffix = substr(strstr($file, '.'), 1, -4);

			// If we find a replace suffix, delete any parent skin file with the same radix.
			// !! This is a work in progress. Needs some extra fine-tuning.
			if (!empty($suffix) && strpos($suffix, 'replace') !== false)
			{
				$suffix = preg_replace('~[,&| ]*replace[,&| ]*~', '', $suffix);
				foreach ($css as $key => $val)
					if (strpos($val, '/' . $radix . '.' . ($suffix ? $suffix . '.' : '')) !== false)
						unset($css[$key]);
			}

			// If our suffix isn't valid, then skip the file.
			if (!empty($suffix) && !($found_suffix = we::is($suffix)))
				continue;

			$css[] = $fold . $file;

			// If a suffix-less file was found, make sure we tell Wedge.
			if (empty($suffix))
				$found_files[] = $radix;
			// Otherwise, add suffix to our list of keywords. If it was a list of AND-separated suffixes, add them all.
			else
				$found_suffixes[] = $found_suffix;

			if (!empty($db_show_debug))
				$context['debug']['sheets'][] = $file . ' (' . basename(TEMPLATES) . ')';
			$latest_date = max($latest_date, filemtime($fold . $file));
		}
	}

	// We can no longer be locals from this point on.
	we::$cache['local'] = false;

	// The following code is only executed if parsing a replace-type skin and one of the files wasn't found.
	if (!empty($fallback_folder))
	{
		$not_found = array_flip(array_diff($files, $found_files));
		$fold = $fallback_folder;

		if (empty($cached_files[$fold]))
			$cached_files[$fold] = array_diff((array) @scandir($fold ? $fold : '', 1), array('.', '..', '.htaccess', 'index.php', 'skin.xml', 'custom.xml'));

		foreach ($cached_files[$fold] as $file)
		{
			if (substr($file, -4) !== '.css')
				continue;

			$radix = substr($file, 0, strpos($file, '.'));
			if (!isset($original_files[$radix], $not_found[$radix]))
				continue;

			$css[] = $fold . $file;

			if (!empty($db_show_debug))
				$context['debug']['sheets'][] = $file . ' (' . basename(TEMPLATES) . ')';
			$latest_date = max($latest_date, filemtime($fold . $file));
		}
	}

	// Now that we have our final CSS file list, sort it in this order:
	// skins/index, skins/index.suffix.css, skins/SubSkin/index.css,
	// skins/sections.css, skins/SubSkin/sections.suffix.css, etc.
	usort($css, 'sort_skin_files');

	$folder = end($context['css_folders']);
	$id = $folder;
	$latest_date %= 1000000;

	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? (we::is('safari[-5.0]') ? '.cgz' : '.css.gz') : '.css';

	// And the language. Only do it if the skin allows for multiple languages and we're not in English mode.
	if (isset($context['skin_available_languages']) && we::$user['language'] !== 'english')
		$found_suffixes[] = we::$user['language'];

	// Build the target folder from our skin's folder names and main file name. We don't need to show 'common-index-sections-extra-custom' in the main filename, though!
	$target_folder = trim($id . '-' . implode('-', array_filter(array_diff($files, (array) 'common', $ignore_files))), '-');

	// Cache final file and retrieve its name.
	$final_script = CACHE . '/css/' . wedge_cache_css_files($target_folder . ($target_folder ? '/' : ''), $found_suffixes, $latest_date, array_merge($css, $hardcoded_css), $can_gzip, $ext);

	if ($final_script == CACHE . '/css/')
		return false;

	if ($is_main)
		return $context['cached_css'] = $final_script;

	// Do we just want the URL?
	if (!$add_link)
		return $final_script;

	$context['header'] .= '
	<link rel="stylesheet" href="' . $final_script . '">';
}

function add_plugin_css_file($plugin_name, $original_files = array(), $add_link = false, $ignore_files = array())
{
	global $context, $settings;

	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	if (!is_array($original_files))
		$original_files = (array) $original_files;

	// Delete all duplicates.
	$files = array_keys(array_flip($original_files));
	$basefiles = array();

	// Plugin CSS files don't support suffixes for now.
	// Use @if tests inside them, they should work.
	foreach ($files as $file)
	{
		if (substr($file, -4) === '.css')
			$file = substr($file, 0, -4);
		$basefiles[] = substr(strrchr($file, '/'), 1);
		$files[] = $file;
	}

	$latest_date = 0;

	foreach ($files as $i => &$file)
	{
		$full_path = $context['plugins_dir'][$plugin_name] . '/' . $file . '.css';
		if (!file_exists($full_path))
		{
			unset($files[$i]);
			continue;
		}

		$file = $full_path;
		$latest_date = max($latest_date, filemtime($full_path));
	}

	$pluginurl = '..' . str_replace(ROOT, '', $context['plugins_url'][$plugin_name]);

	// We need to cache different versions for different browsers, even if we don't have overrides available.
	// This is because Wedge also transforms regular CSS to add vendor prefixes and the like.
	$id = array_filter(array_merge(
		array($context['enabled_plugins'][$plugin_name]),
		$basefiles,
		we::$user['language'] !== 'english' ? (array) we::$user['language'] : array()
	));
	$latest_date %= 1000000;

	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? (we::is('safari[-5.0]') ? '.cgz' : '.css.gz') : '.css';

	// Build the target folder from our plugin's file names. We don't need to show 'common-index-sections-extra-custom' in the main filename, though!
	$target_folder = trim(str_replace(array('/', ':'), '-', strtolower($plugin_name) . '-' . implode('-', array_filter(array_diff($original_files, (array) 'common', $ignore_files)))), '-');

	// Cache final file and retrieve its name.
	$final_script = CACHE . '/css/' . wedge_cache_css_files($target_folder . ($target_folder ? '/' : ''), $id, $latest_date, $files, $can_gzip, $ext, array('$plugindir' => $context['plugins_url'][$plugin_name]));

	if ($final_script == CACHE . '/css/')
		return false;

	// Do we just want the URL?
	if (!$add_link)
		return $final_script;

	$context['header'] .= '
	<link rel="stylesheet" href="' . $final_script . '">';
}

function sort_skin_files($a, $b)
{
	global $context, $files;

	$c = strrchr($a, '/'); // 3x faster than basename()!
	$d = strrchr($b, '/');

	$i = substr($c, 1, strpos($c, '.') - 1);
	$j = substr($d, 1, strpos($d, '.') - 1);

	// Same main file name?
	if ($i == $j)
	{
		// Most top-level folder wins.
		foreach ($context['css_folders'] as $folder)
		{
			$root = SKINS_DIR . ($folder ? '/' . $folder : '');
			$is_a = $a === $root . $c;
			$is_b = $b === $root . $d;
			if ($is_a && !$is_b)
				return -1;
			elseif (!$is_a && $is_b)
				return 1;
		}

		// Same folder, same starting name? Shortest suffix wins.
		$x = strlen($c);
		$y = strlen($d);
		if ($x > $y)
			return 1;
		if ($x < $y)
			return -1;
		return 0;
	}

	// Different file names? First in $files wins.
	foreach ($files as $file)
		if ($i === $file)
			return -1;
		elseif ($j === $file)
			return 1;
	return 0;
}

/**
 * Retrieve a full, final set of dash-separated suffixes for the filename.
 */
function wedge_get_css_filename($add)
{
	global $settings;

	$suffix = array_flip(array_filter(array_map('we::is', is_array($add) ? $add : explode('|', $add))));

	if (isset($suffix['m' . MID]))
		unset($suffix['member'], $suffix['admin']);
	if (isset($suffix['admin']))
		unset($suffix['member']);
	if (isset($suffix[we::$os['os']]))
		$suffix = array(str_replace('dows', '', we::$os['os'] . we::$os['version']) => true) + array_diff_key($suffix, array(we::$os['os'] => 1));

	if (!empty(we::$browser['agent']))
		$suffix = array(we::$browser['agent'] . (!empty(we::$browser['version']) ? we::$browser['version'] : '') => 1) + $suffix;
	$id = implode('-', array_keys($suffix));

	return $id ? (empty($settings['obfuscate_filenames']) ? $id : md5($id)) : '';
}

/**
 * Create a compact CSS file that concatenates, pre-parses and compresses a list of existing CSS files.
 *
 * @param mixed $folder The target folder (relative to the cache folder.)
 * @param mixed $ids An array of filename radixes, such as array('index', 'member', 'extra').
 * @param integer $latest_date The most recent filedate (Unix timestamp format), to be used to differentiate the latest copy from expired ones.
 * @param string $css The CSS file to process, or an array of CSS files to process, in order, with complete path names.
 * @param boolean $gzip Set to true if you want the final file to be compressed with gzip.
 * @param string $ext The extension for the final file. Default is '.css', some browsers may have problems with '.css.gz' if gzipping is enabled.
 * @return array $additional_vars An array of key-pair values to associate custom CSS variables with their intended replacements.
 */
function wedge_cache_css_files($folder, $ids, $latest_date, $css, $gzip = false, $ext = '.css', $additional_vars = array())
{
	global $css_vars, $context, $settings;

	$final_folder = substr(CACHE_DIR . '/css/' . $folder, 0, -1);
	$cachekey = 'css_files-' . $folder . implode('-', $ids);

	// Get the list of tests that shall be done within the CSS files,
	// and quickly run them to get relevant suffixes. MAGIC!
	if (($add = cache_get_data($cachekey, 'forever')) !== null)
	{
		$id = wedge_get_css_filename($add);

		$full_name = ($id ? $id . '-' : '') . $latest_date . $ext;
		$final_file = $final_folder . '/' . $full_name;

		if (file_exists($final_file))
			return $folder . $full_name;
	}

	we::$user['extra_tests'] = array();

	if (!empty($folder) && $folder != '/' && !file_exists($final_folder))
	{
		@mkdir($final_folder, 0755);
		@copy(CACHE_DIR . '/css/index.php', $final_folder . '/index.php');
	}

	$final = '';
	$discard_dir = strlen(ROOT_DIR) + 1;

	// Load Wess, our sweet, short and fast CSS parser :)
	loadSource('Class-CSS');

	$plugins = array(
		new wess_dynamic(),		// Dynamic replacements through callback functions
		new wess_if(),			// CSS conditions, first pass (browser tests)
		new wess_mixin(),		// CSS mixins (mixin hello($world: 0))
		new wess_var(),			// CSS variables ($hello_world)
		new wess_color(),		// CSS color transforms
		new wess_func(),		// Various CSS functions
		new wess_math(),		// Math function (math(1px + 3px), math((4*$var)/2em)...)
		new wess_if(true),		// CSS conditions, second pass (variable tests)
		new wess_nesting(),		// Nested selectors (.hello { .world { color: 0 } }) + selector inheritance (.hello { base: .world })
		new wess_prefixes(),	// Automatically adds browser prefixes for many frequent elements, or manually through -prefix.
	);

	// rgba to rgb conversion for IE 6-8, and a hack for IE9.
	if (we::is('ie[-9]'))
		$plugins[] = new wess_rgba();

	// No need to start the Base64 plugin if we can't gzip the result or the browser can't see it...
	// (Probably should use more specific browser sniffing.)
	// Note that this is called last, mostly to avoid conflicts with the semicolon character.
	if ($gzip && !we::is('ie6,ie7'))
		$plugins[] = new wess_base64($folder);

	// Default CSS variables (paths are absolute or, if forum is in a sub-folder, relative to the CSS cache folder)
	// !!! If subdomains are allowed, should we use absolute paths instead?
	$languages = isset($context['skin_available_languages']) ? $context['skin_available_languages'] : array('english');
	$css_vars = array(
		'$language' => isset(we::$user['language']) && in_array(we::$user['language'], $languages) ? we::$user['language'] : $languages[0],
		'$images_dir' => ASSETS_DIR,
		'$theme_dir' => SKINS_DIR,
		'$root_dir' => ROOT_DIR,
		'$images' => ASSETS,
		'$root' => ROOT,
	);
	if (!empty($additional_vars))
		foreach ($additional_vars as $key => $val)
			$css_vars[$key] = $val;
	if (!empty($context['plugins_dir']))
		foreach ($context['plugins_dir'] as $key => $val)
			$css_vars['$plugins_dir[\'' . $key . '\']'] = str_replace(ROOT_DIR, '', $val);
	if (!empty($context['plugins_url']))
		foreach ($context['plugins_url'] as $key => $val)
			$css_vars['$plugins[\'' . $key . '\']'] = str_replace(ROOT, '', $val);

	// Our deepest skin folder is the one for which 'local' keywords will return true.
	$deep_folder = rtrim(SKINS_DIR . '/' . end($context['css_folders']), '/');

	// Load all CSS files in order, replace $here with the current folder while we're at it, and mark local sections.
	foreach ((array) $css as $file)
	{
		$local = file_get_contents($file);
		if (dirname($file) === $deep_folder && strpos(strtolower($local), 'local') !== false)
			$local = preg_replace('~@(is\h+\([^),]*|(?:else)?if\h+[^\n]*)\blocal\b~i', '@$1true', $local);
		$final .= str_replace('$here', str_replace(ROOT_DIR, ROOT, dirname($file)), $local);
	}
	unset($local);

	if (empty($final)) // Nothing loaded...?
	{
		cache_put_data($cachekey, '', 'forever');
		return false;
	}

	// Resolve references to some common Wedge variables.
	$final = preg_replace_callback('~\$(context|settings|theme|txt)\[([\'"])(.*?)\2]~', 'wedge_replace_theme_vars', $final);

	// CSS is always minified. It takes just a sec' to do, and doesn't impair anything.
	$final = str_replace(array("\r\n", "\r"), "\n", $final); // Always use \n line endings.
	$final = preg_replace('~/\*(?!!).*?\*/~s', '', $final); // Strip comments except for copyrights.

	// And this is where we preserve some of the comments.
	preg_match_all('~\n?/\*!(.*?)\*/\n?~s', $final, $comments);
	$final = preg_replace('~/\*!.*?\*/~s', '.wedge_comment_placeholder{border:0}', $final);

	$final = preg_replace('~\n\t*//[^\n]*~', "\n", $final); // Strip comments at the beginning of lines.
	$final = preg_replace('~//[ \t][^\n]*~', '', $final); // Strip remaining comments like me. OMG does this mean I'm gonn

	// Just like comments, we're going to preserve content tags.
	preg_match_all('~(?<=\s)content\s*:([^\n]+)~', $final, $contags);
	$context['reset_content_counter'] = true;
	$final = preg_replace_callback('~(?<=\s)content\s*:[^\n]+~', 'wedge_hide_content', $final);

	foreach ($plugins as $plugin)
		$plugin->process($final);

	if (we::$user['extra_tests'])
	{
		// Okay, so there are better ways to handle these... For instance, a test on android[-4.0] will store
		// a keyword if you're on Android, even 4.1+. But right now, it's better than what we had before.
		preg_match_all('~[bcm][0-9]+|[a-z]+~i', preg_replace('~".*?"~', '', implode(' ', array_flip(array_flip(array_merge($ids, we::$user['extra_tests']))))), $matches);
		$add = array_diff(array_flip(array_flip($matches[0])), array_keys(we::$browser), array('global', 'local', 'true'));
	}

	// Cache all tests.
	cache_put_data($cachekey, empty($add) ? $ids : $add, 'forever');

	// And we've finally got our full, working filename...
	$id = wedge_get_css_filename(isset($add) ? $add : array());

	$full_name = ($id ? $id . '-' : '') . $latest_date . $ext;
	$final_file = $final_folder . '/' . $full_name;

	// Delete cached versions, unless they have the same timestamp (i.e. up to date.)
	if (is_array($files = glob($final_folder . '/' . ($id ? $id . '-*' : '[0-9]*') . $ext, GLOB_NOSORT)))
		foreach ($files as $del)
			if (($id || preg_match('~/\d+\.~', $del)) && strpos($del, (string) $latest_date) === false)
				@unlink($del);

	// Remove the 'final' keyword.
	$final = preg_replace('~\s+final\b~', '', $final);

	// Remove extra whitespace.
	$final = preg_replace('~\s*([][+:;,>{}\s])\s*~', '$1', $final);

	// This is a bit quirky, like me, as we want to simplify paths but may still break non-path strings in the process.
	// At this point in the code, thought, strings should be hidden from view so it sounds okay to do that.
	while (preg_match('~/(?:\.[^.]*|[^.:*?"<>|/][^:*?"<>|/]*)/\.\./~', $final, $relpath))
		$final = str_replace($relpath[0], '/', $final);

	// Remove double quote hacks, remaining whitespace, and no-base64 tricks.
	$final = str_replace(
		array('#wedge-quote#', "\n\n", ';;', ';}', "}\n", "\t", ' !important', 'raw-url('),
		array('"', "\n", ';', '}', '}', ' ', '!important', 'url('),
		$final
	);

	// Group as many matches as we can, into a :matches() selector. This is new in CSS4 and implemented
	// in WebKit and Firefox as of this writing. Because I couldn't find a clean source indicating when
	// support was added, I'll stick to MDN's list, and use :any until :matches is officialized.
	$selector = '([abipqsu]|[!+>&#*@:.a-z0-9][^{};,\n"()\~+> ]+?)'; // like $selector_regex, but lazy (+?) and without compounds (\~+> ).
	if (we::is('chrome[12-],firefox[4-],safari[5.2-]') && preg_match_all('~(?:^|})' . $selector . '([>+: ][^,{]+)(?:,' . $selector . '\2)+(?={)~', $final, $matches, PREG_SET_ORDER))
	{
		$magic = we::$browser['webkit'] ? ':-webkit-any' : ':-moz-any';
		foreach ($matches as $m)
		{
			// The spec says pseudo-elements aren't allowed INSIDE :matches, but implementations seem to also refuse them NEXT to :matches.
			if (strpos($m[0], ':') !== false && strhas($m[0], array(':before', ':after', ':first-letter', ':first-line', ':selection')))
				continue;
			$final = str_replace(
				$m[0],
				($m[0][0] === '}' ? '}' : '') . $magic . '(' . str_replace(
					array($m[2] . ',', $m[2] . '{', '}'),
					array(',', '', ''),
					$m[0] . '{'
				) . ')' . $m[2],
				$final
			);
		}
	}

	// We may want to apply page replacements to CSS files as well.
	$rep = array();
	if (isset($settings['page_replacements']))
		$rep = unserialize($settings['page_replacements']);
	if (isset($context['ob_replacements']))
		$rep = array_merge($rep, $context['ob_replacements']);
	if (!empty($rep))
		$final = str_replace(array_keys($rep), array_values($rep), $final);

	// Turn url(ROOT/image) into url(/image), but *only* if the CSS file is served from inside ROOT!
	// We're going to go through page replacements to ensure mydomain/gz/css/ isn't turned into something else.
	$set_relative = true;
	if (!empty($rep))
		foreach ($rep as $key => $val)
			$set_relative &= strpos($key, 'gz/css') === false;

	if ($set_relative)
	{
		preg_match('~.*://[^/]+~', ROOT, $root_root);
		if (!empty($root_root))
			$final = str_replace('url(' . $root_root[0], 'url(', $final);
	}

	// Restore comments as requested.
	if (!empty($comments))
		wedge_replace_placeholders('.wedge_comment_placeholder{border:0}', $comments[0], $final);

	// And do the same for content tags.
	if (!empty($contags))
		wedge_replace_numbered_placeholders('content:wedge', $contags[0], $final);

	$final = ltrim($final, "\n");

	// If we find any empty rules, we should be able to remove them.
	if (strpos($final, '{}') !== false)
		$final = preg_replace('~(?<=[{}])[^{}]*{}~', '', $final);

	if ($gzip)
		$final = gzencode($final, 9);

	@file_put_contents($final_file, $final);

	return $folder . $full_name;
}

function wedge_hide_content()
{
	global $context;
	static $i;

	if (!empty($context['reset_content_counter']))
	{
		$i = 0;
		unset($context['reset_content_counter']);
	}
	return 'content: wedge' . $i++;
}

function wedge_replace_theme_vars($match)
{
	global ${$match[1]};

	return isset(${$match[1]}[$match[3]]) ? '"' . (is_array(${$match[1]}[$match[3]]) ? count(${$match[1]}[$match[3]]) : ${$match[1]}[$match[3]]) . '"' : '""';
}

// This will replace {$str}, {$str}, ... in $final with successive entries in $arr
// $add_nl can be used to add newlines around the replacements.
function wedge_replace_placeholders($str, $arr, &$final, $add_nl = false)
{
	$i = 0;
	$len = strlen($str);
	while (($pos = strpos($final, $str)) !== false)
		$final = substr_replace($final, $add_nl ? "\n" . $arr[$i++] . "\n" : $arr[$i++], $pos, $len);
}

// This will replace {$str}0, {$str}1, {$str}2... in $final with the corresponding index in $arr
function wedge_replace_numbered_placeholders($str, $arr, &$final)
{
	$len = strlen($str);
	while (($pos = strpos($final, $str)) !== false)
	{
		$index = intval(substr($final, $pos + $len));
		$final = substr_replace($final, $arr[$index], $pos, $len + strlen($index));
	}
}

// Dynamic function to cache language flags into index.css
function dynamic_language_flags()
{
	global $context;

	if (empty($context['languages']) || count($context['languages']) < 2)
		return;

	$rep = '';
	foreach ($context['languages'] as $language)
	{
		$ext = file_exists(LANGUAGES_DIR . $language['folder'] . '/Flag.' . $language['filename'] . '.png') ? '.png' : '.gif';
		$icon = str_replace(ROOT_DIR, '', LANGUAGES_DIR) . $language['folder'] . '/Flag.' . $language['filename'] . $ext;
		$rep .= '
.flag_' . $language['filename'] . ' mixes .inline-block("")
	background: url($root'. $icon . ') no-repeat 0 center
	padding-left: math(width($root_dir'. $icon . ') + 6px)
	min-height: height($root_dir'. $icon . ')px';
	}
	return $rep;
}

// Dynamic function to cache group colors into index.css
function dynamic_group_colors()
{
	// If the database isn't ready yet, skip this...
	if (defined('WEDGE_INSTALLER'))
		return '';

	$bius = array('b', 'i', 'u', 's');
	$rep = '';
	$request = wesql::query('
		SELECT id_group, online_color, format
		FROM {db_prefix}membergroups AS g
		WHERE g.online_color != {string:blank} OR g.format != {string:blank}',
		array(
			'blank' => '',
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$rep .= '
.group' . $row['id_group'];

		if (!empty($row['online_color']))
			$rep .= '
	color: ' . $row['online_color'];

		if (!empty($row['format']))
		{
			$row['format'] = explode('|', $row['format']);
			// Actually faster than foreaching+switch.
			if (in_array('b', $row['format']))
				$rep .= '
	font-weight: bold';

			if (in_array('i', $row['format']))
				$rep .= '
	font-style: italic';

			// Now we do underline and strikethrough which nest.
			$text_decoration = array();
			if (in_array('u', $row['format']))
				$text_decoration[] = 'underline';
			if (in_array('s', $row['format']))
				$text_decoration[] = 'line-through';
			if (!empty($text_decoration))
				$rep .= '
	text-decoration: ' . implode(' ', $text_decoration);

			// Lastly freeformat. As in... anything left?
			$row['format'] = array_diff($row['format'], $bius);
			if (!empty($row['format']))
			{
				// Users get a single line textbox, they expect to use ; as a separator.
				$row['format'] = explode(';', implode('', $row['format']));
				array_walk($row['format'], 'trim');
				foreach ($row['format'] as $item)
					$rep .= '
	' . $item;
			}
		}
	}

	return $rep;
}

// Dynamic function to cache admin menu icons into admenu.css
function dynamic_admin_menu_icons()
{
	global $admin_areas, $ina;

	function array_search_key($needle, &$arr)
	{
		global $ina;

		foreach ($arr as $key => &$val)
		{
			if (!is_array($val))
				continue;
			if (isset($val[$needle]))
				$ina[] = array($key, $val[$needle]);
			else
				array_search_key($needle, $val);
		}
	}

	$ina = array();
	array_search_key('icon', $admin_areas);

	$rep = '';
	foreach ($ina as $val)
	{
		$is_abs = isset($val[1]) && ($val[1][0] == '/' || strpos($val[1], '://') !== false);
		$icon = $is_abs ? $val[1] : '/admin/' . $val[1];
		$rep .= '
.admenu_icon_' . $val[0] . ' mixes .inline-block
	background: url('. ($is_abs ? $icon : '$images' . $icon) . ') no-repeat
	width: width('. ($is_abs ? $icon : '$images_dir' . $icon) . ')px
	height: height('. ($is_abs ? $icon : '$images_dir' . $icon) . ')px';
	}
	unset($ina);

	return $rep;
}

/**
 * Create a compact JS file that concatenates and compresses a list of existing JS files.
 *
 * @param string $id Name of the file to create, minus the date and extension.
 * @param string $lang_name Name of the user's language, if needed.
 * @param int $latest_date Date of the most recent JS file in the list, used to force recaching.
 * @param string $ext The file extension to use.
 * @param array $js List of all JS files to concatenate.
 * @param bool $gzip Should we gzip the resulting file?
 * @param bool $full_path Whether or not the file list provided is using a full physical path or not, typically not (so it falls to the theme directory instead)
 * @return int Returns the current timestamp, for use in caching.
 */
function wedge_cache_js($id, &$lang_name, $latest_date, $ext, $js, $gzip = false, $full_path = false)
{
	global $settings, $comments, $txt;
	static $closure_failed = false;

	$final = '';
	$dir = $full_path ? '' : ROOT_DIR . '/core/javascript/';
	$no_packing = array();

	// Delete cached versions, unless they have the same timestamp (i.e. up to date.)
	if (is_array($files = glob(CACHE_DIR . '/js/' . $id. '*' . $ext, GLOB_NOSORT)))
		foreach ($files as $del)
			if (strpos($del, (string) $latest_date) === false)
				@unlink($del);

	$minify = empty($settings['minify']) ? 'none' : $settings['minify'];

	foreach ($js as $file)
	{
		$cont = file_get_contents($dir . $file);

		// We make sure to remove any minified files, to be re-added later.
		if (strpos($file, '.min.js') !== false)
		{
			$no_packing[] = preg_replace('~\n//[^\n]+$~', '', $cont);
			$cont = 'WEDGE_NO_PACKING();';
		}
		// Replace long variable names with shorter ones. Our own quick super-minifier!
		elseif (preg_match('~/\* Optimize:\n(.*?)\n\*/~s', $cont, $match))
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
				$cont = preg_replace('~/\* Optimize:\n(.*?)\n\*/~s', '', $cont);
		}
		// An UglifyJS-inspired trick. We're taking it on the safe side though.
		$cont = preg_replace(array('~\bfalse\b~', '~\btrue\b~'), array('!1', '!0'), $cont);
		$final .= $cont;
	}

	// Load any requested language files, and replace all $txt['string'] occurrences.
	// !! @todo: implement cache flush by checking for language modification deltas.
	// In the meantime, if you update a language file, empty the JS cache folder if it fails to update.
	if (preg_match_all('~@language\h+([^\n;]+)[\n;]~i', $final, $languages))
	{
		// Format: @language LanguageFile1, Author:Plugin:LanguageFile2, Author:Plugin:LanguageFile3
		$langstring = implode(',', $languages[1]);
		$langlist = serialize($langs = array_map('trim', explode(',', $langstring)));
		if (strpos($langstring, ':') !== false)
		{
			foreach ($langs as $i => $lng)
				if (strpos($lng, ':') !== false && count($exp = explode(':', $lng)) == 3)
				{
					loadPluginLanguage($exp[0] . ':' . $exp[1], $exp[2]);
					unset($langs[$i]);
				}
		}
		loadLanguage($langs);
		$final = str_replace($languages[0], '', $final);

		if (!isset($settings['js_lang'][$id]) || $settings['js_lang'][$id] != $langlist)
		{
			$use_update = !empty($settings['js_lang']);
			$settings['js_lang'][$id] = $langlist;
			$save = $settings['js_lang'];
			updateSettings(array('js_lang' => serialize($settings['js_lang'])), $use_update);
			$settings['js_lang'] = $save;
			// We need to fix the language string for first time use. $lang_name is passed by reference.
			$lang_name = !empty(we::$user['language']) && we::$user['language'] != $settings['language'] ? we::$user['language'] . '-' : '';
		}

		// The following will store $txt strings into the JS file, processing them in that order:
		// - Replace &#8239; (in UTF-8 bytes) to &#160; (&nbsp;) in UTF-8 bytes,
		// - Escape JS-incompatible strings (unless it's an array),
		// - Turn all 'named' entities and UTF-8 characters to numeric entities,
		// - Then turn these to \u0000 strings in return (they gzip better),
		// - And finally, convert \x0f and \x10 hacks back to their unescaped values, i.e. quotes.
		if (preg_match_all('~\$txt\[([\'"])(.*?)\1]~i', $final, $strings, PREG_SET_ORDER))
			foreach ($strings as $str)
				if (isset($txt[$str[2]]))
					$final = str_replace(
						$str[0],
						strtr(
							westr::entity_to_js_code(
								westr::utf8_to_entity(
									is_array($txt[$str[2]]) ?
										html_entity_decode(
											str_replace("\xe2\x80\xaf", "\xc2\xa0", json_encode($txt[$str[2]])),
											ENT_NOQUOTES,
											'UTF-8'
										)
									:
									JavaScriptEscape(
										html_entity_decode(
											str_replace("\xe2\x80\xaf", "\xc2\xa0", $txt[$str[2]]),
											ENT_NOQUOTES,
											'UTF-8'
										)
									)
								)
							),
							"\x0f\x10",
							'"\''
						),
						$final
					);
				else
					$final = str_replace($str[0], "'Error'", $final);
	}
	// Did we remove all language files from the list? Clean it up...
	elseif (!empty($settings['js_lang'][$id]))
	{
		unset($settings['js_lang'][$id]);
		$save = $settings['js_lang'];
		updateSettings(array('js_lang' => serialize($settings['js_lang'])), true);
		$settings['js_lang'] = $save;
		$lang_name = '';
	}

	// The @if {} (with/without @else {}) compiler directives allow you
	// to output blocks of code only for members, guests or admins.
	$final = preg_replace_callback(
		'~@if\s*\(?(guest|member|admin)\s*\)?\s*({((?:(?>[^{}]+)|(?-2))*)})(?:\s*@else\s*({((?:(?>[^{}]+)|(?-2))*)}))?~i',
		function ($match) { return !empty(we::$is[$match[1]]) ? $match[3] : (isset($match[5]) ? $match[5] : ''); },
		$final
	);

	if (!$closure_failed && !is_callable('curl_exec') && !preg_match('~1|yes|on|true~i', ini_get('allow_url_fopen')))
		$closure_failed = true;

	// Call the minify process, either JSMin, Packer or Closure.
	if ($minify === 'closure' && !$closure_failed) // Google Closure version
	{
		// We want to keep the copyright-type comments, starting with /*!, which Closure usually removes...
		preg_match_all('~/\*!\n.*?\*/~s', $final, $comments);
		if (!empty($comments[0]))
			$final = str_replace($comments[0], 'WEDGE_COMMENT();', $final);

		// We're requesting the JSON version because it makes error handling a bit cleaner.
		$data = 'output_info=compiled_code&output_format=json&js_code=' . urlencode(preg_replace('~/\*.*?\*/~s', '', $final));

		if (is_callable('curl_init'))
		{
			// We're going to handle a request to the Closure web service. Ohhh, so excited.
			$ch = curl_init('http://closure-compiler.appspot.com/compile');

			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($ch, CURLOPT_POST, 1);

			// Move it!
			$packed_js = curl_exec($ch);

			// Now get off my lawn!
			curl_close($ch);
		}
		else
		{
			$packed_js = file_get_contents(
				'http://closure-compiler.appspot.com/compile',
				false, stream_context_create(
					array('http' => array(
						'method' => 'POST',
						'header' => 'Content-type: application/x-www-form-urlencoded',
						'content' => $data,
						'max_redirects' => 0,
						'timeout' => 15,
					))
				)
			);
		}

		$packed_js = json_decode($packed_js);

		if (!empty($packed_js->errors) || !empty($packed_js->serverErrors))
		{
			log_error('Google Closure Compiler - ' . print_r(empty($packed_js->errors) ? $packed_js->serverErrors : $packed_js->errors, true));
			$closure_failed = true;
		}
		elseif (!empty($packed_js->compiledCode))
			$final = $packed_js->compiledCode;
		else // No data received? Must be a timeout or something...
			$closure_failed = true;

		unset($packed_js, $data);

		if (!empty($comments[0]))
			wedge_replace_placeholders('WEDGE_COMMENT();', $comments[0], $final, true);
	}

	if ($minify === 'packer' || $closure_failed)
	{
		// We want to keep the copyright-type comments, starting with /*!, which Packer usually removes...
		preg_match_all('~/\*!\n.*?\*/~s', $final, $comments);
		if (!empty($comments[0]))
			$final = str_replace($comments[0], 'WEDGE_COMMENT();', $final);

		loadSource('Class-Packer');
		$packer = new Packer;
		$final = $packer->pack($final);

		if (!empty($comments[0]))
			wedge_replace_placeholders('WEDGE_COMMENT();', $comments[0], $final, true);

		/*
			Another note: Packer doesn't seem to support things like this:
				for (var something in { some: 1, object: 2 })
			It can be fixed by replacing the $VAR_TIDY line in Class-Packer.php with:
				private $VAR_TIDY = '/\\b(var|function)\\b|\\s(in\\s+[^;{]+|in(?=[);]|$))/';
			But this is (relatively) untested, so I chose not to include it.

			Also, adding a semicolon after a function/prototype var declaration is MANDATORY in Packer.
			The original SMF code didn't bother with that, and developers are advised NOT to
			follow that 'advice'. If you can't fix your scripts, uncomment the following
			block. Semicolons will be added automatically, at a small performance cost.
		*/
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
	elseif ($minify === 'jsmin')
	{
		loadSource('Class-JSMin');
		$final = JSMin::minify($final);
	}

	// ...And we restore jQuery and other pre-minified files.
	if (!empty($no_packing))
		wedge_replace_placeholders('WEDGE_NO_PACKING();', $no_packing, $final, true);

	// Remove the copyright years and version information, in case you're a paranoid android.
	// Then remove the extra whitespace that we may have added around comments to avoid glitches.
	$final = preg_replace(
		array('~/\*!(?:[^*]|\*[^/])*?@package Wedge.*?\*/~s', '~(^|\n)\n/\*~', '~\*/\n~'),
		array("/*!\n * @package Wedge\n * @copyright René-Gilles Deberdt, wedge.org\n * @license http://wedge.org/license/\n */", '$1/*', '*/'),
		$final
	);

	if ($gzip)
		$final = gzencode($final, 9);

	file_put_contents(CACHE_DIR . '/js/' . $id . $lang_name . $latest_date . $ext, $final);
}

/**
 * Builds the special smiley CSS file, directly minified.
 *
 * @param string $set The current smiley folder
 * @param array $smileys The list of smileys to cache
 * @param string $extra '-ie', if using oldIE, which doesn't support base64 encoding.
 */
function wedge_cache_smileys($set, $smileys, $extra)
{
	global $context, $settings;

	$final_gzip = $final_raw = '';
	$path = ASSETS_DIR . '/smileys/' . $set . '/';
	$url = (strpos(str_replace('://', '', ROOT), '/') === false && strpos(SMILEYS, ROOT) === 0 ? '' : '../..') . str_replace(ROOT, '', SMILEYS) . '/' . $set . '/';

	// Delete other cached versions, if they exist.
	clean_cache($context['smiley_ext'], 'smileys' . $extra, CACHE_DIR . '/css');

	foreach ($smileys as $name => $smiley)
	{
		$filename = $path . $smiley['file'];
		if (!file_exists($filename))
			continue;
		// Only small files should be embedded, really. 4KB should have a fair bandwidth/hit ratio.
		if ($extra || ($smiley['embed'] && filesize($filename) > 4096) || !$context['smiley_gzip'])
			$smiley['embed'] = false;
		list ($width, $height) = getimagesize($filename);
		$ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
		$stream = 'final_' . ($smiley['embed'] ? 'gzip' : 'raw');
		$$stream .= '.' . $name . '{width:' . $width . 'px;height:' . $height . 'px;background:url('
				. ($smiley['embed'] ? 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($filename)) : $url . $smiley['file']) . ')}';
	}

	// We can't apply a mixin here, but as .smiley is a naturally inline tag anyway, .inline-block isn't needed.
	$final = '.smiley{display:inline-block;vertical-align:middle;text-indent:100%;white-space:nowrap;overflow:hidden}' . $final_raw . $final_gzip;
	unset($final_raw, $final_gzip);
	if ($context['smiley_gzip'])
		$final = gzencode($final, 9);

	file_put_contents(CACHE_DIR . '/css/smileys' . $extra . ($set == 'default' ? '' : '-' . $set) . '-' . $context['smiley_now'] . $context['smiley_ext'], $final);
}

/**
 * Shows the base CSS file, i.e. index.css and any other files added through {@link wedge_add_css()}.
 */
function theme_base_css()
{
	global $context, $settings;

	// First, let's purge the cache if any files are over a month old. This ensures we don't waste space for IE6 & co. when they die out.
	$one_month_ago = time() - 30 * 24 * 3600;
	if (empty($settings['last_cache_purge']) || $settings['last_cache_purge'] < $one_month_ago)
	{
		clean_cache('css', $one_month_ago);
		clean_cache('js', $one_month_ago);
		updateSettings(array('last_cache_purge' => time()));
	}

	// We only generate the cached file at the last moment (i.e. when first needed.)
	// Make sure extra.css and custom.css, if available, are added last. Also, strip index/sections/extra/custom from the final filename.
	if (empty($context['cached_css']))
	{
		$context['main_css_files']['extra'] = false;
		$context['main_css_files']['custom'] = false;

		add_css_file(
			array_keys($context['main_css_files']),
			false, true,
			array_keys(array_diff($context['main_css_files'], array_filter($context['main_css_files'])))
		);
	}

	if (!empty($context['header_css']))
	{
		// Replace $behavior with the forum's root URL in context, because pretty URLs complicate things in IE.
		if (strpos($context['header_css'], '$behavior') !== false)
			$context['header_css'] = str_replace('$behavior', strpos(ROOT, '://' . we::$user['host']) !== false ? ROOT
				: preg_replace('~(?<=://)([^/]+)~', we::$user['host'], ROOT), $context['header_css']);
	}

	return '
	<link rel="stylesheet" href="' . $context['cached_css'] . '">';
}

/**
 * Shows the base JavaScript calls, i.e. including jQuery and script.js
 * Also transmits a list of filenames to ignore (e.g. sbox, avasize, theme, custom...)
 *
 * @param boolean $indenting Number of tabs on each new line. For the average anal-retentive web developer.
 */
function theme_base_js($indenting = 0)
{
	global $context;

	$tab = str_repeat("\t", $indenting);
	return (!empty($context['remote_js_files']) ? '
' . $tab . '<script src="' . implode('"></script>
' . $tab . '<script src="', $context['remote_js_files']) . '"></script>
	<script>window.$ || document.write(\'<script src="' . add_js_file('jquery-' . $context['jquery_version'] . '.min.js', false, true) . '"><\/script>\');</script>' : '') . '
' . $tab . '<script src="' . add_js_file(
		array_keys($context['main_js_files']), false, true,
		array_diff($context['main_js_files'], array_filter($context['main_js_files']))
	) . '"></script>';
}

/**
 * A helper function that retrieves the extension for a given filename. ".*.gz" is recognized as a full extension.
 *
 * @param string $file The string to process. Yeah really.
 */
function wedge_get_extension($file)
{
	$ext = substr(strrchr($file, '.'), 1);
	if ($ext === 'gz')
		return substr(strrchr(substr($file, 0, -3), '.'), 1) . '.gz';
	return $ext;
}

function wedge_get_skeleton_operations($set, $op, $required_vars = array())
{
	global $context;

	if (strpos($set, '<' . $op) === false || !preg_match_all('~<' . $op . '(?:\s+[a-z]+="[^"]+")*\s*/?>~', $set, $matches, PREG_SET_ORDER))
		return;

	foreach ($matches as $match)
	{
		preg_match_all('~\s([a-z]+)="([^"]+)"~', $match[0], $v);
		$pos_id = array_search('id', $v[1], true);
		$id = $pos_id !== false ? $v[2][$pos_id] : 'main';
		$match_all = true;
		$arr = array($op);
		foreach ($required_vars as $var)
		{
			$match_all &= ($pos = array_search($var, $v[1], true)) !== false;
			if (!$match_all)
				continue 2;
			$arr[] = $v[2][$pos];
		}
		// Only one operation allowed per item per skeleton. Latest one has priority.
		$context['skeleton_ops'][$id][$op . $arr[1]] = $arr;
	}
}

// Allow for <if> tests inside skins and skeletons.
function wedge_skin_conditions(&$str)
{
	if (strpos($str, '<if') === false || !preg_match_all('~(?<=\n)(\t*)<if\b([^>]+)>(.*?)</if>~s', $str, $ifs, PREG_SET_ORDER))
		return;

	foreach ($ifs as $if)
	{
		$exe = array_merge(explode('<else>', $if[3]), array(''));
		$str = str_replace($if[0], str_replace("\n" . $if[1], "\n" . substr($if[1], 0, -1), $exe[(int) !we::is(trim($if[2]))]), $str);
	}
}

/**
 * A helper function to parse skin tags in any order, e.g. <css for="ie[-8]" include="file"> or <css include="file" for="ie[-8]">.
 * Specifying params is required, to prevent undefined index errors when accessing the param by name directly. Plus, it's cleaner.
 */
function wedge_parse_skin_tags(&$file, $name, $params = array())
{
	global $board_info;

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
		// Or is a different browser targeted? Ignore this entry completely...
		elseif (strpos($match[1], 'for="') !== false && preg_match('~\bfor="([^"]*)"~', $match[1], $val) && !we::is($val[1]))
			continue;
		elseif (strpos($match[1], 'board-type="') !== false && (!isset($board_info, $board_info['type']) || (preg_match('~\bboard-type="([^"]*)"~', $match[1], $val) && $board_info['type'] != $val[1])))
			continue;
		// Or do we require to be on a certain page? i.e., <css url-action="profile" url-area="forumprofile"> would only apply CSS to the Forum Profile page.
		elseif (strpos($match[1], 'url-') !== false && preg_match_all('~\burl-([a-z]+)="([^"]*)"~', $match[1], $url_bits, PREG_SET_ORDER))
			foreach ($url_bits as $bit)
				if (!isset($_GET[$bit[1]]) || ($_GET[$bit[1]] != $bit[2] && $bit[2] != '*'))
					continue 2;

		// Now we'll retrieve the parameters individually, to allow for any param order.
		foreach ($params as $param)
			if (preg_match('~\b' . $param . '="([^"]*)"~', $match[1], $val))
				$item[$param] = $val[1];
		$tags[] = $item;
	}
	return $tags;
}

/**
 * Parses the current skin's skin.xml and skeleton.xml files, and those above them.
 */
function wedge_get_skin_options($options_only = false)
{
	global $context;

	$skin_options = array();
	$skeleton = $macros = $set = '';

	// We will rebuild the skin folder list, in case we have a replace-type skin in our path.
	$root_dir = SKINS_DIR;
	$context['skin_folders'] = array();
	$context['template_folders'] = array();
	$css_folders = array(ltrim(isset($context['skin']) ? $context['skin'] : '', '/'));

	loadSource('Themes');
	$test_folders = wedge_get_skin_list(true); // Get a linear list of skins.

	// From deepest to root.
	for ($i = 0; $i < count($css_folders) && $i < 10; $i++)
	{
		$folder = $css_folders[$i];
		$context['skin_folders'][] = $full_path = $root_dir . ($folder ? '/' . $folder : '') . '/';

		if ($test_folders[$folder]['has_templates'])
			$context['template_folders'][] = $full_path . 'html';

		// If this is a replace-type skin, skip all parent folders.
		if ($test_folders[$folder]['type'] === 'replace')
			break;

		// Has this skin got a parent?
		if (isset($test_folders[$folder]['parent']))
			$css_folders[] = $test_folders[$folder]['parent'];
	}
	$context['template_folders'][] = TEMPLATES_DIR;
	$context['css_folders'] = array_reverse($css_folders);

	// From root to deepest.
	foreach (array_reverse($context['skin_folders']) as $fold)
	{
		// Remember all of the skeletons we can find.
		if (file_exists($fold . 'skeleton.xml'))
			$skeleton .= file_get_contents($fold . 'skeleton.xml');

		// Remember all of the macros we can find.
		if (file_exists($fold . 'macros.xml'))
			$macros .= file_get_contents($fold . 'macros.xml');

		if (file_exists($fold . 'skin.xml'))
			$set = file_get_contents($fold . 'skin.xml');

		// custom.xml files can be used to override skin.xml, skeleton.xml and macros.xml...
		if (file_exists($fold . 'custom.xml'))
		{
			$custom = file_get_contents($fold . 'custom.xml');
			$skeleton = $custom . $skeleton;
			$macros = $custom . $macros;
			$set = $custom . $set;
		}
	}

	// $set should now contain the local skin's settings.
	// First get the skin options, such as <sidebar> position.
	if (strpos($set, '</options>') !== false && preg_match('~<options>(.*?)</options>~s', $set, $match))
	{
		preg_match_all('~<([\w-]+)>(.*?)</\\1>~s', $match[1], $opts, PREG_SET_ORDER);
		foreach ($opts as $option)
			$skin_options[$option[1]] = trim($option[2]);
	}

	wedge_parse_skin_options($skin_options);

	if ($options_only)
		return;

	// Any conditional directives inside the skin.xml or skeleton.xml files..?
	$sources = $skeleton ? array(&$set, &$skeleton) : array(&$set);
	foreach ($sources as &$source)
	{
		wedge_skin_conditions($source);

		// Did we ask to do post-loading operations on blocks/layers of the skeleton?
		wedge_get_skeleton_operations($source, 'move', array('block', 'to', 'where'));
		wedge_get_skeleton_operations($source, 'rename', array('block', 'to'));
		wedge_get_skeleton_operations($source, 'remove', array('block'));
	}

	// Now, find skeletons and feed them to the $context['skeleton'] array for later parsing.
	$matches = $skeleton ? wedge_parse_skin_tags($skeleton, 'skeleton', 'id') : array();
	foreach ($matches as $match)
		$context['skeleton'][empty($match['id']) ? 'main' : $match['id']] = $match['value'];

	// Gather macros here.
	$matches = wedge_parse_skin_tags($macros, 'macro', 'name');
	foreach ($matches as $match)
		$context['macros'][$match['name']] = array(
			'has_if' => strpos($match['value'], '<if:') !== false,
			'body' => $match['value']
		);

	if (!$set)
		return;

	$matches = wedge_parse_skin_tags($set, 'replace', 'regex');
	foreach ($matches as $match)
		if (preg_match('~<from>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</from>\s*<to>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</to>~', $match['value'], $from_to))
			$context['skin_replace'][trim($from_to[1], "\x00..\x1F")] = array(trim($from_to[2], "\x00..\x1F"), !empty($match['regex']));

	// Add inline CSS or CSS files to all pages.
	$matches = wedge_parse_skin_tags($set, 'css', 'include');
	foreach ($matches as $match)
	{
		// If we have an include param in the tag, it should either use a full URI, or 'something', in which case
		// it'll look for skins/something.css in the root, then satellite files (suffixes, child folders..), if any.
		if (!empty($match['include']))
		{
			$includes = array_map('trim', explode(' ', $match['include']));
			$has_external = strpos($match['include'], '://') !== false;
			foreach ($includes as $val)
			{
				if ($has_external && strpos($val, '://') !== false)
					$context['header'] .= '
	<link rel="stylesheet" href="' . $val . '">';
				else
					add_css_file($val);
			}
		}
		if (!empty($match['value']))
			add_css(rtrim($match['value'], "\t"));
	}

	// Add inline JS or JS files to all pages. Very similar to the above code...
	$matches = wedge_parse_skin_tags($set, 'script', 'include');
	foreach ($matches as $match)
	{
		// If we have an include param in the tag, it should either use a full URI, or 'something.js'
		// to load a local script, or '$here/something.js', where it'll look for it in the skin folder.
		if (!empty($match['include']))
		{
			$includes = array_map('trim', explode(' ', $match['include']));
			$has_here = strpos($match['include'], '$here') !== false;
			foreach ($includes as $val)
				add_js_file($has_here ? str_replace('$here', str_replace($root_dir . '/', '', $folder), $val) : $val);
		}
		if (!empty($match['value']))
			add_js(rtrim($match['value'], "\t"));
	}

	// Override template functions directly.
	$matches = wedge_parse_skin_tags($set, 'template', array('name', 'param(?:s|eters)?', 'where'));
	foreach ($matches as $match)
		$context['template_' . ($match['where'] != 'before' && $match['where'] != 'after' ? 'override' : $match['where']) . 's']['template_' . preg_replace('~^template_~', '', $match['name'])] = array($match['param(?:s|eters)?'], $match['value']);

	$matches = wedge_parse_skin_tags($set, 'languages');
	foreach ($matches as $match)
		$context['skin_available_languages'] = array_filter(preg_split('~[\s,]+~', $match['value']));

	// If you write a plugin that adds new skin options, plug it into this!
	call_hook('skin_parser', array(&$set, &$skeleton, &$macros));
}

function wedge_parse_skin_options($skin_options)
{
	if (defined('SKIN_MOBILE'))
		return;

	// Skin variables can be accessed either through PHP or Wess code with a test on the SKIN_* constant.
	define('SKIN_SIDEBAR_RIGHT', we::$is['SKIN_SIDEBAR_RIGHT'] = empty($skin_options['sidebar']) || $skin_options['sidebar'] == 'right');
	define('SKIN_SIDEBAR_LEFT', we::$is['SKIN_SIDEBAR_LEFT'] = isset($skin_options['sidebar']) && $skin_options['sidebar'] == 'left');
	unset($skin_options['sidebar']);
	if (!isset($skin_options['mobile']))
		$skin_options['mobile'] = 0;

	// Any other variables, maybe..? e.g. SKIN_MOBILE
	foreach ($skin_options as $key => $val)
		define('SKIN_' . strtoupper($key), we::$is['SKIN_' . strtoupper($key)] = !empty($val));
}

// And now for the actual cache system.
// Portions are © 2011 Simple Machines.

/**
 * /do/uncache will refresh all cache: CSS, JavaScript, PHP, language files and key-value pairs.
 */
function uncache()
{
	global $settings;

	if (empty($settings['cache_enable']) || !allowedTo('admin_forum'))
		return;

	clean_cache();
	clean_cache('css');
	clean_cache('js');
	updateSettings(array('last_cache_purge' => time()));

	// Redirect to the current page, if possible.
	redirectexit(isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], ROOT) !== false ? $_SERVER['HTTP_REFERER'] : '');
}

/**
 * Cleans some or all of the files stored in the file cache.
 *
 * @param string $extensions Optional, a comma-separated list of file extensions that should be pruned. Leave empty to clear the regular data cache (data sub-folder), you may also use 'css' or 'js' as special values to clean the CSS or JavaScript cache folders.
 * @param string $filter Optional, designates a filter to match the files either again a name mask, or a modification date, before they can be cleared from the cache folder.
 * @param string $force_folder Optional, specifies folder to clean.
 * @param boolean $remove_folder Try to remove the folder after cleanup?
 */
function clean_cache($extensions = 'php', $filter = '', $force_folder = '', $remove_folder = false)
{
	global $cache_system;

	$folder = CACHE_DIR . '/keys';
	$is_recursive = false;
	$there_is_another = false;
	if ($extensions === 'css')
	{
		$folder = CACHE_DIR . '/css';
		$extensions = array('css', 'cgz', 'css.gz');
		$is_recursive = true;
	}
	elseif ($extensions === 'js')
	{
		$folder = CACHE_DIR . '/js';
		$extensions = array('js', 'jgz', 'js.gz');
	}
	elseif (!is_array($extensions))
		$extensions = ltrim($extensions, '.');

	if ($force_folder)
		$folder = $force_folder;

	if (!is_dir($folder))
		return;

	$dh = scandir($folder, 1);
	$exts = array_flip((array) $extensions);
	$by_date = '';
	if (is_integer($filter))
	{
		$filter = '';
		$by_date = $filter;
	}
	$filter_is_folder = !$filter || strpos($force_folder, $filter) !== false;

	// If we're emptying the regular cache, chances are we also want to reset non-file-based cached data if possible.
	if ($folder == CACHE_DIR . '/keys')
	{
		cache_get_type();
		if ($cache_system === 'apc')
			apc_clear_cache('user');
		elseif ($cache_system === 'memcached')
			$val = memcache_flush(get_memcached_server());
		elseif ($cache_system === 'xcache' && function_exists('xcache_clear_cache'))
		{
			for ($i = 0; $i < xcache_count(XC_TYPE_VAR); $i++)
				xcache_clear_cache(XC_TYPE_VAR, $i);
		}
		elseif ($cache_system === 'zend' && function_exists('zend_shm_cache_clear'))
			zend_shm_cache_clear('we');
		elseif ($cache_system === 'zend' && ($zend_cache_folder = ini_get('zend_accelerator.output_cache_dir')))
			clean_cache('', '', $zend_cache_folder . '/.php_cache_api');

		// Also get the source and language caches!
		clean_cache('php', '', CACHE_DIR . '/app');
		clean_cache('php', '', CACHE_DIR . '/lang');
	}

	// Remove the files in Wedge's own disk cache, if any.
	foreach ($dh as $file)
	{
		if ($file[0] === '.' || $file === 'index.php')
			continue;
		$path = $folder . '/' . $file;
		if (is_dir($path))
			$is_recursive && clean_cache($extensions, $filter, $path, true);
		elseif (($by_date && filemtime($path) < $by_date) || !$filter || $filter_is_folder || strpos($path, $filter) !== false)
		{
			if (!$extensions || isset($exts[wedge_get_extension($file)]))
				@unlink($path);
		}
		// Protect sub-folders from deletion in case a file should remain in it.
		else
			$there_is_another = true;
	}

	// Invalidate cache, to be sure!
	if (!$force_folder && !is_array($extensions))
	{
		@fclose(@fopen(CACHE_DIR . '/cache.lock', 'w'));
		clearstatcache();
	}
	elseif ($remove_folder && !$there_is_another)
	{
		@unlink($folder . '/index.php');
		@rmdir($force_folder);
	}
}

/**
 * Load a cache entry, and if the cache entry could not be found, load a named file and call a function to get the relevant information.
 *
 * The cache-serving function must consist of an array, and contain some or all of the following:
 * - data, required, which is the content of the item to be cached
 * - expires, required, the timestamp at which the item should expire
 * - refresh_eval, optional, a string containing a piece of code to be evaluated that returns boolean as to whether some external factor may trigger a refresh
 * - after_run, optional, a callback containing code to be run after the data has been updated and cached
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
	global $settings, $cache_block;

	$needs_refresh = empty($settings['cache_enable']) || $settings['cache_enable'] < $level || !is_array($cache_block = cache_get_data($key, 3600)) || (!empty($cache_block['refresh_eval']) && eval($cache_block['refresh_eval'])) || (!empty($cache_block['expires']) && $cache_block['expires'] < time());
	if ($needs_refresh || !empty($cache_block['after_run']))
	{
		if (is_array($file))
			loadPluginSource($file[0], $file[1]);
		else
			loadSource($file);
	}

	if ($needs_refresh)
	{
		$cache_block = call_user_func_array($function, $params);

		if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= $level)
			cache_put_data($key, $cache_block, $cache_block['expires'] - time());
	}

	// Some cached data may need a freshening up after retrieval.
	if (!empty($cache_block['after_run']))
		$cache_block['after_run']($params);

	return $cache_block['data'];
}

/**
 * Store an item in the cache; supports multiple methods of which one is chosen by the admin in the server settings area.
 *
 * Important: things in the cache cannot be relied or assumed to continue to exist; cache misses on both reading and writing are possible and in some cases, frequent.
 *
 * This function supports the following cache methods:
 * - Memcache
 * - Alternative PHP Cache
 * - Zend Platform/ZPS
 * - or a custom file cache
 *
 * @param string $key A string that denotes the identity of the data being saved, and for later retrieval.
 * @param mixed $val The raw data to be cached. This may be any data type but it will be serialized prior to being stored in the cache.
 * @param int $ttl The time the cache is valid for, in seconds. If a request to retrieve is received after this time, the item will not be retrieved.
 * @todo Remove cache types that are obsolete and no longer maintained.
 */
function cache_put_data($key, $val, $ttl = 120)
{
	global $cache_system, $cache_hits, $cache_count;
	global $settings, $db_show_debug;

	if (empty($settings['cache_enable']) && !empty($settings))
		return;

	$st = microtime(true);
	$key = cache_prepare_key($key, $val, 'put');
	if ($ttl === 'forever')
		$ttl = PHP_INT_MAX;

	if ($val !== null)
		$val = serialize($val);

	cache_get_type();

	// The simple yet efficient memcached.
	if ($cache_system === 'memcached')
		memcache_set(get_memcached_server(), $key, $val, 0, $ttl);
	elseif ($cache_system === 'apc')
	{
		// An extended key is needed to counteract a bug in APC.
		if ($val === null)
			apc_delete($key . 'wedge');
		else
			apc_store($key . 'wedge', $val, $ttl === PHP_INT_MAX ? 0 : $ttl);
	}
	elseif ($cache_system === 'zend' && function_exists('zend_shm_cache_store'))
		zend_shm_cache_store('we::' . $key, $val, $ttl);
	elseif ($cache_system === 'zend' && function_exists('output_cache_put'))
		output_cache_put($key, $val);
	elseif ($cache_system === 'xcache')
	{
		if ($val === null)
			xcache_unset($key);
		else
			xcache_set($key, $val, $ttl);
	}
	// Otherwise file cache?
	else
	{
		if ($val === null)
			@unlink(CACHE_DIR . '/keys/' . $key . '.php');
		else
		{
			$cache_data = '<' . '?php if(defined(\'WEDGE\')&&$valid=' . ($ttl === PHP_INT_MAX ? '1' : 'time()<' . (time() + $ttl)) . ')$val=\'' . addcslashes($val, '\\\'') . '\';';

			// Check that the cache write was successful. If it fails due to low diskspace, remove the cache file.
			if (file_put_contents(CACHE_DIR . '/keys/' . $key . '.php', $cache_data, LOCK_EX) !== strlen($cache_data))
				@unlink(CACHE_DIR . '/keys/' . $key . '.php');
		}
	}

	if (!empty($db_show_debug))
		$cache_hits[$cache_count]['t'] = microtime(true) - $st;
}

/**
 * Attempt to retrieve an item from cache, previously stored with {@link cache_put_data()}.
 *
 * This function supports all of the same cache systems that {@link cache_put_data()} does, and with the same caveat that cache misses can occur so content should not be relied upon to exist.
 *
 * @param string $key A string denoting the identity of the key to be retrieved.
 * @param int $ttl Expiration date for the data, in seconds; after that delay, no data will be returned even if it is in cache.
 * @return mixed If retrieving from cache was not possible, null will be returned, otherwise the item will be unserialized and passed back.
 */
function cache_get_data($orig_key, $ttl = 120, $put_callback = null)
{
	global $cache_system, $cache_hits, $cache_count, $settings, $db_show_debug;

	if (empty($settings['cache_enable']) && !empty($settings))
		return null;

	$st = microtime(true);
	$key = cache_prepare_key($orig_key);
	if ($ttl === 'forever')
		$ttl = PHP_INT_MAX;

	cache_get_type();

	if ($cache_system === 'memcached')
		$val = memcache_get(get_memcached_server(), $key);
	elseif ($cache_system === 'apc')
		$val = apc_fetch($key . 'wedge');
	elseif ($cache_system === 'zend' && function_exists('zend_shm_cache_fetch'))
		zend_shm_cache_fetch('we::' . $key);
	elseif ($cache_system === 'zend' && function_exists('output_cache_get'))
		$val = output_cache_get($key, $ttl);
	elseif ($cache_system === 'xcache')
		$val = xcache_get($key);
	// Otherwise it's the file cache!
	elseif (file_exists(CACHE_DIR . '/keys/' . $key . '.php') && @filesize(CACHE_DIR . '/keys/' . $key . '.php') > 10)
	{
		@include(CACHE_DIR . '/keys/' . $key . '.php');
		if (empty($valid))
			@unlink(CACHE_DIR . '/keys/' . $key . '.php');
	}

	if (!empty($db_show_debug))
	{
		$cache_hits[$cache_count]['t'] = microtime(true) - $st;
		$cache_hits[$cache_count]['s'] = isset($val) ? strlen($val) : 0;
	}

	// If the operation requires re-caching, return null to let the script know.
	if (!empty($val))
		return unserialize($val);

	if ($put_callback === null)
		return null;

	cache_put_data($orig_key, $new_cache = $put_callback(), $ttl);
	return $new_cache;
}

function cache_prepare_key($key, $val = '', $type = 'get')
{
	global $settings, $cache_hits, $cache_count, $db_show_debug;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (!empty($db_show_debug))
		$cache_hits[$cache_count] = $type == 'get' ? array('k' => $key, 'd' => 'get') : array('k' => $key, 'd' => 'put', 's' => $val === null ? 0 : strlen(serialize($val)));

	if (empty($settings['cache_hash']))
	{
		if (!file_exists(CACHE_DIR . '/cache.lock'))
			@fclose(@fopen(CACHE_DIR . '/cache.lock', 'w'));
		$settings['cache_hash'] = md5(ROOT_DIR . filemtime(CACHE_DIR . '/cache.lock'));
	}

	return $settings['cache_hash'] . '-' . bin2hex($key);
}

function cache_get_type()
{
	global $cache_type, $cache_system, $memcached_servers;

	if (isset($cache_system))
		return;

	if (empty($cache_type))
		$cache_type = 'file';

	// Make sure memcached wasn't disabled.
	if ($cache_type === 'memcached' && !(isset($memcached_servers) && trim($memcached_servers) !== '' && function_exists('memcache_get') && function_exists('memcache_set') && get_memcached_server()))
		$cache_type = 'file';
	// Or PECL's APC.
	elseif ($cache_type === 'apc' && !(function_exists('apc_fetch') && function_exists('apc_store')))
		$cache_type = 'file';
	// Or Zend Platform/ZPS.
	elseif ($cache_type === 'zend' && !((function_exists('zend_shm_cache_fetch') && function_exists('zend_shm_cache_store')) || (function_exists('output_cache_get') && function_exists('output_cache_put'))))
		$cache_type = 'file';
	// Or XCache.
	elseif ($cache_type === 'xcache' && !(function_exists('xcache_get') && function_exists('xcache_set') && ini_get('xcache.var_size') > 0))
		$cache_type = 'file';

	$cache_system = $cache_type;
}

/**
 * Attempt to connect to Memcache server for retrieving cached items.
 *
 * This function acts to attempt to connect (or persistently connect, if persistent connections are enabled) to a memcached instance, looking up the server details from $memcached_servers.
 *
 * If connection is successful, the static $memcached will be a resource holding the connection or will be false if not successful. The function will attempt to call itself in a recursive fashion if there are more attempts remaining.
 *
 * @param int $level The number of connection attempts that will be made, defaulting to 3, but reduced if the number of server connections is fewer than this.
 */
function get_memcached_server($level = 3)
{
	global $db_persist, $memcached_servers;
	static $memcached = 0;

	if (!$memcached)
	{
		$servers = explode(',', $memcached_servers);
		$server = explode(':', trim($servers[array_rand($servers)]));

		// Don't try more times than we have servers!
		$level = min(count($servers), $level);

		// Don't wait too long; we might be able to get it done faster later!
		$func = 'memcache_' . (empty($db_persist) ? 'connect' : 'pconnect');
		$memcached = $func($server[0], empty($server[1]) ? 11211 : $server[1]);

		if (!$memcached)
			return $level > 0 ? get_memcached_server($level - 1) : false;
	}

	return $memcached;
}
