<?php
/**
 * Wedge
 *
 * This file handles functions that allow caching data in Wedge: regular data (cache_get/put_data), CSS and JavaScript.
 * It also ensures that CSS and JS files are properly parsed and compressed before they're cached.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
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
<script><!-- // --><![CDATA[';
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
	global $context, $settings, $theme, $jsdir, $boardurl;
	global $footer_coding, $user_info, $language;
	static $done_files = array();

	if (!is_array($files))
		$files = (array) $files;

	// Delete all duplicates and already cached files.
	$files = array_diff(array_keys(array_flip($files)), $done_files);
	$done_files = array_merge($done_files, $files);
	if (empty($files))
		return;

	if ($is_direct_url || strpos($files[0], '://') !== false)
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
	$not_default = $theme['theme_dir'] !== $theme['default_theme_dir'];

	foreach ($files as $fid => $file)
	{
		$target = $not_default && file_exists($theme['theme_dir'] . '/' . $file) ? 'theme_' : (file_exists($theme['default_theme_dir'] . '/' . $file) ? 'default_theme_' : false);
		if (!$target)
		{
			unset($files[$fid]);
			continue;
		}

		$is_default_theme &= $target === 'default_theme_';
		$add = $theme[$target . 'dir'] . '/' . $file;

		// Turn scripts/name.js into 'name', and plugin/other.js into 'plugin_other' for the final filename.
		// Don't add theme.js, sbox.js and custom.js files to the final filename, to save a few bytes on all pages.
		if (!isset($ignore_files[$file]))
			$id .= str_replace(array('scripts/', '/'), array('', '_'), substr(strrchr($file, '/'), 1, -3)) . '-';

		$latest_date = max($latest_date, filemtime($add));
	}

	$id = $is_default_theme ? $id : substr(strrchr($theme['theme_dir'], '/'), 1) . '-' . $id;
	$id = !empty($settings['obfuscate_filenames']) ? md5(substr($id, 0, -1)) . '-' : $id;

	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? ($context['browser']['is_safari'] ? '.jgz' : '.js.gz') : '.js';

	$final_file = $jsdir . '/' . $id . (!empty($settings['js_lang'][$id]) && !empty($user_info['language']) && $user_info['language'] != $language ? $user_info['language'] . '-' : '') . $latest_date . $ext;

	if (!file_exists($final_file))
		wedge_cache_js($id, $latest_date, $final_file, $files, $can_gzip, $ext);

	$final_script = $boardurl . '/js/' . $id . $latest_date . $ext;

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
	global $context, $pluginsdir, $jsdir, $boardurl, $settings, $footer_coding;
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

		// Turn scripts/name.js into 'name', and plugin/other.js into 'plugin_other' for the final filename.
		$id .= str_replace(array('scripts/', '/'), array('', '_'), substr(strrchr($file, '/'), 1, -3)) . '-';
		$latest_date = max($latest_date, filemtime($file));
	}

	if (empty($files))
		return;

	$id = substr(strrchr($context['plugins_dir'][$plugin_name], '/'), 1) . '-' . $id;
	$id = !empty($settings['obfuscate_filenames']) ? md5(substr($id, 0, -1)) . '-' : $id;

	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? ($context['browser']['is_safari'] ? '.jgz' : '.js.gz') : '.js';

	$final_file = $jsdir . '/' . $id . $latest_date . $ext;
	if (!file_exists($final_file))
		wedge_cache_js($id, $latest_date, $final_file, $files, $can_gzip, $ext, true);

	$final_script = $boardurl . '/js/' . $id . $latest_date . $ext;

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
 * @param boolean $add_link Set to true if you want Wedge to automatically add the link tag around the URL and move it to the header.
 * @param boolean $is_main Determines whether this is the primary CSS file list (index.css and other files), which gets special treatment.
 * @return string The generated code for direct inclusion in the source code, if $out_of_flow is set. Otherwise, nothing.
 */
function add_css_file($original_files = array(), $add_link = false, $is_main = false, $ignore_files = array())
{
	global $theme, $settings, $context, $db_show_debug, $boardurl, $files;
	static $cached_files = array();

	// Delete all duplicates and ensure $original_files is an array.
	$original_files = array_flip((array) $original_files);
	$files = array_keys($original_files);
	$latest_date = 0;

	// If we didn't go through the regular theme initialization flow, get the skin options.
	if (!isset($context['skin_folders']))
		wedge_get_skin_options();

	$fallback_folder = $deep_folder = $theme[$context['skin_uses_default_theme'] ? 'default_theme_dir' : 'theme_dir'] . '/';
	$fallback_folder .= reset($context['css_folders']) . '/';
	$deep_folder .= end($context['css_folders']) . '/';
	$requested_suffixes = array('' => 0) + array_flip($context['css_suffixes']);
	$ignore_versions = array();
	$found_suffixes = array();
	$found_files = array();

	// !! @todo: the following is quite resource intensive... Maybe we should cache the results somehow?
	// !! e.g. cache for several minutes, but delete cache if the filemtime for current folder or its parent folders was updated.
	foreach ($context['skin_folders'] as $folder)
	{
		$fold = $folder[0];
		$target = $folder[1];
		if ($fold === $fallback_folder)
			$fallback_folder = '';

		if (empty($cached_files[$fold]))
			$cached_files[$fold] = array_diff((array) @scandir($fold ? $fold : '', 1), array('.', '..', '.htaccess', 'index.php', 'skin.xml', 'custom.xml'));

		foreach ($cached_files[$fold] as $file)
		{
			if (substr($file, -4) !== '.css')
				continue;

			$radix = substr($file, 0, strpos($file, '.'));
			if (!isset($original_files[$radix]))
				continue;

			// Get the list of suffixes in the file name.
			$suffix_string = substr(strstr($file, '.'), 1, -4);
			$suffixes = array_flip(explode(',', $suffix_string));

			// If we found our browser version in the suffix list, then add to the list of files to load.
			if (!empty($suffix_string) && $brow = hasBrowser($suffixes))
			{
				$requested_suffixes[$brow] = true;
				if ($brow != $context['browser']['agent'] . $context['browser']['version'])
					$ignore_versions[$brow] = true;
			}
			$suffixes_to_keep = array_intersect_key($suffixes, $requested_suffixes);

			// If we find a local suffix in the filename, only process it if it's at the final level.
			// Also, if we can't find a required suffix in the suffix list, skip the file.
			if (!$suffixes_to_keep || (isset($suffixes['local']) && $fold !== $deep_folder))
				continue;

			// If we find a replace suffix, delete any parent skin file with the same radix.
			// !! This is a work in progress. Needs some extra fine-tuning.
			if (isset($suffixes['replace']))
				foreach ($css as $key => $val)
					if (strpos($val, '/' . $radix . '.') !== false)
						unset($css[$key]);

			$css[] = $fold . $file;

			// If a suffix-less file was found, make sure we tell Wedge.
			if (isset($suffixes['']))
				$found_files[] = $radix;

			$found_suffixes += $suffixes_to_keep;
			if ($db_show_debug === true)
				$context['debug']['sheets'][] = $file . ' (' . basename($theme[$target . 'url']) . ')';
			$latest_date = max($latest_date, filemtime($fold . $file));
		}
	}

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

			// Get the list of suffixes in the file name.
			$suffixes = array_flip(explode(',', substr(strstr($file, '.'), 1, -4)));
			$keep_suffixes = array_intersect_key($suffixes, $requested_suffixes);

			// Ignore local files (we're only guests in this folder.)
			// Also, if we can't find a required suffix in the suffix list, skip the file.
			if (!$keep_suffixes || isset($keep_suffixes['local']))
				continue;

			$css[] = $fold . $file;

			if ($db_show_debug === true)
				$context['debug']['sheets'][] = $file . ' (' . basename($theme[$target . 'url']) . ')';
			$latest_date = max($latest_date, filemtime($fold . $file));
		}
	}

	// Now that we have our final css file list, sort it.
	usort($css, 'sort_skin_files');

	$folder = end($context['css_folders']);
	$id = $context['skin_uses_default_theme'] || (!$is_main && $theme['theme_dir'] === 'default') ? '' : substr(strrchr($theme['theme_dir'], '/'), 1) . '-';
	$id = $folder === 'skins' ? substr($id, 0, -1) : $id . str_replace('/', '-', strpos($folder, 'skins/') === 0 ? substr($folder, 6) : $folder);

	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? ($context['browser']['agent'] == 'safari' ? '.cgz' : '.css.gz') : '.css';

	// Don't add the flow control keywords to the final URL. If you add a local/global override
	// and decide to remove it later, simply reupload other CSS files or empty your cache.
	unset($found_suffixes['local'], $found_suffixes['global'], $found_suffixes['replace']);

	// We need to cache different versions for different browsers, even if we don't have overrides available.
	// This is because Wedge also transforms regular CSS to add vendor prefixes and the like.
	$found_suffixes[$context['browser']['agent'] . $context['browser']['version']] = true;

	// Make sure to only keep 'webkit' if we have no other browser name on record.
	if ($context['browser']['is_webkit'] && $context['browser']['agent'] != 'webkit')
		unset($found_suffixes['webkit']);

	// Build the target folder from our skin's folder names and main file name. We don't need to show 'common-index-sections-extra-custom' in the main filename, though!
	$target_folder = trim($id . '-' . implode('-', array_filter(array_diff($files, (array) 'common', $ignore_files))), '-');

	$id = array_filter(array_merge(
		array_keys(array_diff_key($found_suffixes, $ignore_versions)),

		// And the language. Only do it if the skin allows for multiple languages and we're not in English mode.
		isset($context['user'], $context['skin_available_languages']) && $context['user']['language'] !== 'english'
			&& count($context['skin_available_languages']) > 1 ? (array) $context['user']['language'] : array()
	));

	// Cache final file and retrieve its name.
	$final_script = $boardurl . '/css/' . wedge_cache_css_files($target_folder . ($target_folder ? '/' : ''), $id, $latest_date, $css, $can_gzip, $ext);

	if ($is_main)
		return $context['cached_css'] = $final_script;

	// Do we just want the URL?
	if (!$add_link)
		return $final_script;

	$context['header'] .= '
	<link rel="stylesheet" href="' . $final_script . '">';
}

// This function will sort a CSS file list in this order:
// skins/index, skins/index.suffix.css, skins/SubSkin/index.css,
// skins/sections.css, skins/SubSkin/sections.suffix.css, etc.
function sort_skin_files($a, $b)
{
	$x = strrpos($a, '/');
	$y = strrpos($b, '/');

	$c = substr($a, $x + 1);
	$d = substr($b, $y + 1);

	$i = substr($c, 0, strpos($c, '.'));
	$j = substr($d, 0, strpos($d, '.'));

	// Same main file name?
	if ($i == $j)
	{
		// Most top-level folder wins.
		if ($x > $y)
			return 1;
		if ($x < $y)
			return -1;

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
	global $files;
	foreach ($files as $file)
		if ($i === $file)
			return -1;
		elseif ($j === $file)
			return 1;
	return 0;
}

function add_plugin_css_file($plugin_name, $original_files = array(), $add_link = false)
{
	global $context, $settings, $theme, $boardurl, $pluginsdir;

	if (empty($context['plugins_dir'][$plugin_name]))
		return;

	if (!is_array($original_files))
		$original_files = (array) $original_files;

	// Delete all duplicates.
	$files = array_keys(array_flip($original_files));
	$basefiles = array();

	foreach ($files as $file)
	{
		$basefiles[] = substr(strrchr($file, '/'), 1);
		foreach ($context['css_suffixes'] as $gen)
			$files[] = $file . '.' . $gen;
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

	$pluginurl = '..' . str_replace($boardurl, '', $context['plugins_url'][$plugin_name]);

	// We need to cache different versions for different browsers, even if we don't have overrides available.
	// This is because Wedge also transforms regular CSS to add vendor prefixes and the like.
	$id = array_filter(array_merge(
		array($context['enabled_plugins'][$plugin_name]),
		$basefiles,
		array_diff($context['css_suffixes'], array($context['browser']['is_webkit'] && $context['browser']['agent'] != 'webkit' ? 'webkit' : '')),
		isset($context['user']) && $context['user']['language'] !== 'english' ? (array) $context['user']['language'] : array()
	));

	$can_gzip = !empty($settings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? ($context['browser']['agent'] == 'safari' ? '.cgz' : '.css.gz') : '.css';

	// Cache final file and retrieve its name.
	$final_script = $boardurl . '/css/' . wedge_cache_css_files('', $id, $latest_date, $files, $can_gzip, $ext, array('$plugindir' => $context['plugins_url'][$plugin_name]));

	// Do we just want the URL?
	if (!$add_link)
		return $final_script;

	$context['header'] .= '
	<link rel="stylesheet" href="' . $final_script . '">';
}

/**
 * Create a compact CSS file that concatenates, pre-parses and compresses a list of existing CSS files.
 *
 * @param mixed $folder The target folder (relative to the cache folder.)
 * @param mixed $ids A filename or an array of filename radixes, such as 'index'.
 * @param integer $latest_date The most recent filedate (Unix timestamp format), to be used to differentiate the latest copy from expired ones.
 * @param string $css The CSS file to process, or an array of CSS files to process, in order, with complete path names.
 * @param boolean $gzip Set to true if you want the final file to be compressed with gzip.
 * @param string $ext The extension for the final file. Default is '.css', some browsers may have problems with '.css.gz' if gzipping is enabled.
 * @return array $additional_vars An array of key-pair values to associate custom CSS variables with their intended replacements.
 */
function wedge_cache_css_files($folder, $ids, $latest_date, $css, $gzip = false, $ext = '.css', $additional_vars = array())
{
	global $theme, $settings, $css_vars, $context, $cssdir, $boarddir, $boardurl, $prefix;

	$id = empty($settings['obfuscate_filenames']) ? implode('-', (array) $ids) : md5(implode('-', (array) $ids));

	$full_name = ($id ? $id . '-' : '') . $latest_date . $ext;
	$final_folder = substr($cssdir . '/' . $folder, 0, -1);
	$final_file = $final_folder . '/' . $full_name;

	if (file_exists($final_file))
		return $folder . $full_name;

	if (!empty($folder) && $folder != '/' && !file_exists($final_folder))
	{
		@mkdir($final_folder, 0755);
		@copy($cssdir . '/index.php', $final_folder . '/index.php');
	}

	// Delete cached versions, unless they have the same timestamp (i.e. up to date.)
	$files = glob($final_folder . '/' . ($id ? $id . '-*' : '[0-9]*') . $ext);
	if (!empty($files))
		foreach ($files as $del)
			if (($id || preg_match('~/\d+\.~', $del)) && strpos($del, (string) $latest_date) === false)
				@unlink($del);

	$final = '';
	$discard_dir = strlen($boarddir) + 1;

	// Load Wess, our sweet, short and fast CSS parser :)
	loadSource('Class-CSS');

	$plugins = array(
		new wess_dynamic(),	// Dynamic replacements through callback functions
		new wess_if(),		// CSS conditions, first pass (browser tests)
		new wess_mixin(),	// CSS mixins (mixin hello($world: 0))
		new wess_var(),		// CSS variables ($hello_world)
		new wess_color(),	// CSS color transforms
		new wess_func(),	// Various CSS functions
		new wess_math(),	// Math function (math(1px + 3px), math((4*$var)/2em)...)
		new wess_if(true),	// CSS conditions, second pass (variable tests)
		new wess_nesting(),	// Nested selectors (.hello { .world { color: 0 } }) + selector inheritance (.hello { base: .world })
		new wess_prefixes(),
	);

	// rgba to rgb conversion for IE 6/7/8/9
	if ($context['browser']['is_ie'])
		$plugins[] = new wess_rgba();

	// No need to start the Base64 plugin if we can't gzip the result or the browser can't see it...
	// (Probably should use more specific browser sniffing.)
	// Note that this is called last, mostly to avoid conflicts with the semicolon character.
	if ($gzip && !$context['browser']['is_ie6'] && !$context['browser']['is_ie7'])
		$plugins[] = new wess_base64($folder);

	// Default CSS variables (paths are set relative to the cache folder)
	// !!! If subdomains are allowed, should we use absolute paths instead?
	$relative_root = '..' . str_repeat('/..', substr_count($folder, '/'));
	$images_url = $relative_root . str_replace($boardurl, '', $theme['images_url']);
	$languages = isset($context['skin_available_languages']) ? $context['skin_available_languages'] : array('english');
	$css_vars = array(
		'$language' => isset($context['user']['language']) && in_array($context['user']['language'], $languages) ? $context['user']['language'] : $languages[0],
		'$images_dir' => $theme['theme_dir'] . '/images',
		'$images' => $images_url,
		'$theme_dir' => $theme['theme_dir'],
		'$theme' => $relative_root . str_replace($boardurl, '', $theme['theme_url']),
		'$root' => $relative_root,
	);
	if (!empty($additional_vars))
		foreach ($additional_vars as $key => $val)
			$css_vars[$key] = $val;

	// Load all CSS files in order, and replace $here with the current folder while we're at it.
	foreach ((array) $css as $file)
		$final .= str_replace('$here', $relative_root . str_replace('\\', '/', str_replace($boarddir, '', dirname($file))), file_get_contents($file));

	// CSS is always minified. It takes just a sec' to do, and doesn't impair anything.
	$final = str_replace(array("\r\n", "\r"), "\n", $final); // Always use \n line endings.
	$final = preg_replace('~/\*(?!!).*?\*/~s', '', $final); // Strip comments except for copyrights.

	// And this is where we preserve some of the comments.
	preg_match_all('~\n?/\*!(.*?)\*/\n?~s', $final, $comments);
	$final = preg_replace('~/\*!.*?\*/~s', '.wedge_comment_placeholder{border:0}', $final);

	$final = preg_replace('~\n\t*//[^\n]*~', "\n", $final); // Strip comments at the beginning of lines.
	$final = preg_replace('~//[ \t][^\n]*~', '', $final); // Strip remaining comments like me. OMG does this mean I'm gonn

	// Build a prefix variable, enabling you to use "-prefix-something" to get it replaced with your browser's own flavor, e.g. "-moz-something".
	// Please note that it isn't currently used by Wedge itself, but you can use it to provide both prefixed and standard versions of a tag that isn't
	// already taken into account by the wess_prefixes() function (otherwise you only need to provide the unprefixed version.)
	$prefix = $context['browser']['is_opera'] ? '-o-' : ($context['browser']['is_webkit'] ? '-webkit-' : ($context['browser']['is_gecko'] ? '-moz-' : ($context['browser']['is_ie'] ? '-ms-' : '')));

	// Just like comments, we're going to preserve content tags.
	$i = 0;
	preg_match_all('~(?<=\s)content\s*:\s*(?:\'.+\'|".+")~', $final, $contags);
	$final = preg_replace('~(?<=\s)content\s*:\s*(?:\'.+\'|".+")~e', '\'content: wedge\' . $i++', $final);

	foreach ($plugins as $plugin)
		$plugin->process($final);

	// Remove the 'final' keyword.
	$final = preg_replace('~\s+final\b~', '', $final);

	// Remove extra whitespace.
	$final = preg_replace('~\s*([][+:;,>{}\s])\s*~', '$1', $final);

	// Remove double quote hacks, remaining whitespace, no-base64 tricks, and replace browser prefixes.
	$final = str_replace(
		array('#wedge-quote#', "\n\n", ';;', ';}', "}\n", "\t", 'url-no-base64(', '-prefix-'),
		array('"', "\n", ';', '}', '}', ' ', 'url(', $prefix),
		$final
	);

	// Restore comments as requested.
	if (!empty($comments))
		wedge_replace_placeholders('.wedge_comment_placeholder{border:0}', $comments[0], $final);

	// And do the same for content tags.
	if (!empty($contags))
		wedge_replace_numbered_placeholders('content:wedge', $contags[0], $final);

	$final = ltrim($final, "\n");

	// If we find any empty rules, we should be able to remove them.
	if (strpos($final, '{}') !== false)
		$final = preg_replace('~[^{}]+{}~', '', $final);

	if ($gzip)
		$final = gzencode($final, 9);

	file_put_contents($final_file, $final);

	return $folder . $full_name;
}

// This will replace {$str}, {$str}, ... in $final with successive entries in $arr
function wedge_replace_placeholders($str, $arr, &$final)
{
	$i = 0;
	$len = strlen($str);
	while (($pos = strpos($final, $str)) !== false)
		$final = substr_replace($final, $arr[$i++], $pos, $len);
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
	global $context, $settings;

	if (empty($context['languages']) || count($context['languages']) < 2)
		return;

	$rep = '';
	foreach ($context['languages'] as $language)
	{
		$icon = '/languages/Flag.' . $language['filename'] . '.png';
		$rep .= '
.flag_' . $language['filename'] . ' extends .inline-block
	background: url($theme'. $icon . ') no-repeat
	width: width($theme_dir'. $icon . ')px
	height: height($theme_dir'. $icon . ')px';
	}
	return $rep;
}

// Dynamic function to cache admin menu icons into admenu.css
function dynamic_admin_menu_icons()
{
	global $context, $settings, $admin_areas, $ina;

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
		$icon = $is_abs ? $val[1] : '/images/admin/' . $val[1];
		$rep .= '
.admenu_icon_' . $val[0] . ' extends .inline-block
	background: url('. ($is_abs ? $icon : '$theme' . $icon) . ') no-repeat
	width: width('. ($is_abs ? $icon : '$theme_dir' . $icon) . ')px
	height: height('. ($is_abs ? $icon : '$theme_dir' . $icon) . ')px';
	}
	unset($ina);

	return $rep;
}

/**
 * Create a compact JS file that concatenates and compresses a list of existing JS files.
 *
 * @param string $id Name of the file to create, unobfuscated, minus the date component
 * @param int $latest_date Date of the most recent JS file in the list, used to force recaching
 * @param string $final_file Final name of the file to create (obfuscated, with date, etc.)
 * @param array $js List of all JS files to concatenate
 * @param bool $gzip Should we gzip the resulting file?
 * @param string $ext The file extension to use.
 * @param bool $full_path Whether or not the file list provided is using a full physical path or not, typically not (so it falls to the theme directory instead)
 * @return int Returns the current timestamp, for use in caching
 */
function wedge_cache_js($id, $latest_date, $final_file, $js, $gzip = false, $ext, $full_path = false)
{
	global $theme, $settings, $comments, $jsdir, $txt;

	$final = '';
	$dir = $full_path ? '' : $theme['theme_dir'] . '/';

	// Delete cached versions, unless they have the same timestamp (i.e. up to date.)
	$files = glob($jsdir . '/' . $id. '*' . $ext);
	if (!empty($files))
		foreach ($files as $del)
			if (strpos($del, (string) $latest_date) === false)
				@unlink($del);

	$minify = empty($settings['minify']) ? 'none' : $settings['minify'];

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
		// An UglifyJS-inspired trick. We're taking it on the safe side though.
		$cont = preg_replace(array('~\bfalse\b~', '~\btrue\b~'), array('!1', '!0'), $cont);
		$final .= $cont;
	}

	// We make sure to remove jQuery (if present) before we pack the file.
	if (strpos($id, 'jquery') !== false)
	{
		preg_match('~<wedge_jquery>(.*?)</wedge_jquery>~s', $final, $jquery);
		if (!empty($jquery[1]))
			$final = str_replace($jquery[0], 'WEDGE_JQUERY();', $final);
	}

	// Load any requested language files, and replace all $txt['string'] occurrences.
	// !! @todo: implement cache flush by checking for language modification deltas.
	// In the meantime, if you update a language file, empty the JS cache folder if it fails to update.
	if (preg_match_all('~@language\h+([\w\h,:]+)(?=[\n;])~i', $final, $languages))
	{
		// Format: @language ThemeLanguage, Author:Plugin:Language, Author:Plugin:Language2
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
		}

		if (preg_match_all('~\$txt\[([\'"])(.*?)\1]~i', $final, $strings, PREG_SET_ORDER))
			foreach ($strings as $str)
				if (isset($txt[$str[2]]))
					$final = str_replace($str[0], JavaScriptEscape($txt[$str[2]]), $final);
	}
	// Did we remove all language files from the list? Clean it up...
	elseif (!empty($settings['js_lang'][$id]))
	{
		unset($settings['js_lang'][$id]);
		$save = $settings['js_lang'];
		updateSettings(array('js_lang' => serialize($settings['js_lang'])), true);
		$settings['js_lang'] = $save;
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

		/*
			Adding a semicolon after a function/prototype var declaration is mandatory in Packer.
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

		/*
			Another note: Packer doesn't seem to support things like this:
				for (var something in { some: 1, object: 2 })
			It can be fixed by replacing the $VAR_TIDY line in Class-Packer.php with:
				private $VAR_TIDY = '/\\b(var|function)\\b|\\s(in\\s+[^;{]+|in(?=[);]|$))/';
			But this is (relatively) untested, so I chose not to include it.
		*/
	}

	// ...And we restore jQuery.
	if (isset($jquery, $jquery[1]))
		$final = str_replace('WEDGE_JQUERY();', trim($jquery[1]) . "\n", $final);

	// Remove the copyright years and version information, in case you're a paranoid android.
	$final = preg_replace("~/\*!(?:[^*]|\*[^/])*?@package wedge.*?\*/~s", "/*!\n * @package wedge\n * @copyright Wedgeward, wedge.org\n * @license http://wedge.org/license/\n */", $final);

	if ($gzip)
		$final = gzencode($final, 9);

	file_put_contents($final_file, $final);
}

/**
 * Builds the special smiley CSS file, directly minified.
 *
 * @param string $set The current smiley folder
 * @param array $smileys The list of smileys to cache
 */
function wedge_cache_smileys($set, $smileys)
{
	global $cssdir, $context, $settings, $browser, $boardurl;

	$final = '';
	$path = $settings['smileys_dir'] . '/' . $set . '/';
	$url  = '..' . str_replace($boardurl, '', $settings['smileys_url']) . '/' . $set . '/';
	$extra = $browser['agent'] === 'ie' && $browser['version'] < 8 ? '-ie' : '';
	updateSettings(array('smiley_cache-' . str_replace('.', '', $context['smiley_ext']) . $extra . '-' . $set => $context['smiley_now']));

	// Delete all remaining cached versions, if any (e.g. *.cgz for Safari.)
	clean_cache('css', 'smileys' . $extra);

	foreach ($smileys as $name => $smiley)
	{
		$filename = $path . $smiley['file'];
		if (!file_exists($filename))
			continue;
		// Only small files should be embedded, really. We're saving on hits, not bandwidth.
		if ($extra || ($smiley['embed'] && filesize($filename) > 4096) || !$context['smiley_gzip'])
			$smiley['embed'] = false;
		list ($width, $height) = getimagesize($filename);
		$ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
		$final .= '.' . $name . '{width:' . $width . 'px;height:' . $height . 'px;background:url('
				. ($smiley['embed'] ? 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($filename)) : $url . $smiley['file']) . ')}';
	}

	if ($context['smiley_gzip'])
		$final = gzencode($final, 9);

	file_put_contents($cssdir . '/smileys' . $extra . '-' . $set . '-' . $context['smiley_now'] . $context['smiley_ext'], $final);
}

/**
 * Shows the base CSS file, i.e. index.css and any other files added through {@link wedge_add_css()}.
 */
function theme_base_css()
{
	global $context, $boardurl, $settings, $cssdir;

	// First, let's purge the cache if any files are over a month old. This ensures we don't waste space for IE6 & co. when they die out.
	$one_month_ago = time() - 30 * 24 * 3600;
	if (empty($settings['last_cache_purge']) || $settings['last_cache_purge'] < $one_month_ago)
	{
		$search_extensions = array('.gz', '.css', '.js', '.cgz', '.jgz');
		clean_cache('css', $one_month_ago);
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
		global $user_info;

		// Replace $behavior with the forum's root URL in context, because pretty URLs complicate things in IE.
		if (strpos($context['header_css'], '$behavior') !== false)
			$context['header_css'] = str_replace('$behavior', strpos($boardurl, '://' . $user_info['host']) !== false ? $boardurl
				: preg_replace('~(?<=://)([^/]+)~', $user_info['host'], $boardurl), $context['header_css']);
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
	global $context,$user_info;

	$tab = str_repeat("\t", $indenting);
	return (!empty($context['remote_js_files']) ? '
' . $tab . '<script src="' . implode('"></script>
' . $tab . '<script src="', $context['remote_js_files']) . '"></script>' : '') . '
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

/**
 * Analyzes the current skin's skin.xml file (and those above it) and retrieves its options.
 */
function wedge_get_skin_options()
{
	global $theme, $context, $scripturl;

	$is_default_theme = true;
	$not_default = $theme['theme_dir'] !== $theme['default_theme_dir'];

	// We will rebuild the css folder list, in case we have a replace-type skin in our path.
	$context['skin_folders'] = array();

	foreach ($context['css_folders'] as &$folder)
	{
		$target = $not_default && file_exists($theme['theme_dir'] . '/' . $folder) ? 'theme_' : 'default_theme_';
		$is_default_theme &= $target === 'default_theme_';
		$fold = $theme[$target . 'dir'] . '/' . $folder . '/';

		if (file_exists($fold . 'skin.xml'))
		{
			$set = file_get_contents($fold . '/skin.xml');
			if (file_exists($fold . 'custom.xml'))
				$set .= file_get_contents($fold . '/custom.xml');

			// If this is a replace-type skin, forget all of the parent folders.
			if ($folder !== 'skins' && strpos($set, '</type>') !== false && preg_match('~<type>([^<]+)</type>~', $set, $match) && strtolower(trim($match[1])) === 'replace')
				$context['skin_folders'] = array();
		}

		$context['skin_folders'][] = array($fold, $target);
	}

	$context['skin_uses_default_theme'] = $is_default_theme;

	// The deepest skin gets CSS/JavaScript attention.
	if (!empty($set))
	{
		if (strpos($set, '</skeleton>') !== false && preg_match('~<skeleton>(.*?)</skeleton>~s', $set, $match))
		{
			$context['skeleton'] = $match[1];

			// We need to erase the skeleton from the skin file, in case
			// one of the layers/blocks has the same name as a skin keyword.
			$set = str_replace($match[1], '', $set);
		}

		if (strpos($set, '<move') !== false && preg_match_all('~<move(?:\s+[a-z]+="[^"]+")*\s*/>~', $set, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				preg_match_all('~\s([a-z]+)="([^"]+)"~', $match[0], $v);
				if (($block = array_search('block', $v[1], true)) !== false && ($where = array_search('where', $v[1], true)) !== false && ($to = array_search('to', $v[1], true)) !== false)
					$context['skeleton_moves'][] = array($v[2][$block], $v[2][$to], $v[2][$where]);
			}
		}

		// Skin options, such as <sidebar> position.
		if (strpos($set, '</options>') !== false && preg_match('~<options>(.*?)</options>~s', $set, $match))
		{
			preg_match_all('~<([\w-]+)>(.*?)</\\1>~s', $match[1], $options, PREG_SET_ORDER);
			foreach ($options as $option)
				$context['skin_options'][$option[1]] = trim($option[2]);
		}

		if (strpos($set, '</replace>') !== false && preg_match_all('~<replace(?:\s+(regex(?:="[^"]+")?))?(?:\s+for="([^"]+)")?\s*>\s*<from>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</from>\s*<to>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</to>\s*</replace>~s', $set, $matches, PREG_SET_ORDER))
			foreach ($matches as $match)
				if (!empty($match[3]) && (empty($match[2]) || hasBrowser($match[2])))
					$context['skin_replace'][trim($match[3], "\x00..\x1F")] = array(trim($match[4], "\x00..\x1F"), !empty($match[1]));

		if (strpos($set, '</css>') !== false && preg_match_all('~<css(?:\s+for="([^"]+)")?(?:\s+include="([^"]+)")?\s*>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</css>~s', $set, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				if (!empty($match[3]) && (empty($match[1]) || hasBrowser($match[1])))
					add_css(rtrim($match[3], "\t"));
				if (!empty($match[2]))
				{
					$includes = array_map('trim', explode(' ', $match[2]));
					// Wedge currently only supports providing a full URI in <css include=""> statements.
					foreach ($includes as $css_file)
						if (strpos($css_file, '://') !== false)
							$context['header'] .= '
	<link rel="stylesheet" href="' . $css_file . '">';
				}
			}
		}

		if (strpos($set, '</code>') !== false && preg_match_all('~<code(?:\s+for="([^"]+)")?(?:\s+include="([^"]+)")?\s*>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</code>~s', $set, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				if (!empty($match[1]) && !hasBrowser($match[1]))
					continue;

				if (!empty($match[2]))
				{
					$includes = array_map('trim', explode(' ', $match[2]));
					// If we have an include param in the code tag, it should either use a full URI, or 'scripts/something.js' (in which case
					// it'll find data in the current theme, or the default theme), or '$here/something.js', where it'll look in the skin folder.
					if (strpos($match[2], '$here') !== false)
						foreach ($includes as &$scr)
							$scr = str_replace('$here', str_replace($theme['theme_dir'] . '/', '', $folder), $scr);
					add_js_file($includes);
				}
				add_js(rtrim($match[3], "\t"));
			}
		}

		if (strpos($set, '</macro>') !== false && preg_match_all('~<macro\s+name="([^"]+)"(?:\s+for="([^"]+)")?\s*>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</macro>~s', $set, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				if (!empty($match[2]) && !hasBrowser($match[2]))
					continue;
				$context['macros'][$match[1]] = array(
					'has_if' => strpos($match[3], '<if:') !== false,
					'body' => $match[3]
				);
			}
		}

		if (strpos($set, '</languages>') !== false && preg_match('~<languages>(.*?)</languages>~s', $set, $match))
			$context['skin_available_languages'] = array_map('trim', preg_split('~[\s,]+~', $match[1]));
	}
}

/**
 * Cleans some or all of the files stored in the file cache.
 *
 * @param string $extensions Optional, a comma-separated list of file extensions that should be pruned. Leave empty to clear the regular data cache (data sub-folder.)
 * @param string $filter Optional, designates a filter to match the files either again a name mask, or a modification date, before they can be cleared from the cache folder.
 * @param string $force_folder Optional, used internally for recursivity.
 * @todo Figure out a better way of doing this and get rid of $sourcedir being globalled again.
 */
function clean_cache($extensions = 'php', $filter = '', $force_folder = '')
{
	global $cachedir, $cssdir, $jsdir, $sourcedir;

	$folder = $cachedir;
	$is_recursive = false;
	$there_is_another = false;
	if ($extensions === 'css')
	{
		$folder = $cssdir;
		$extensions = array('css', 'cgz', 'css.gz');
		$is_recursive = true;
	}
	elseif ($extensions === 'js')
	{
		$folder = $jsdir;
		$extensions = array('js', 'jgz', 'js.gz');
	}

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

	// Remove the files in Wedge's own disk cache, if any.
	foreach ($dh as $file)
	{
		if ($file[0] === '.' || $file === 'index.php')
			continue;
		$path = $folder . '/' . $file;
		if (is_dir($path))
			$is_recursive && clean_cache($extensions, $filter, $path);
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
	// ...as long as Collapse.php can be modified, anyway.
	if (!$force_folder && !is_array($extensions))
	{
		@touch($sourcedir . '/Collapse.php');
		clearstatcache();
	}
	elseif ($force_folder && !$there_is_another)
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
	global $settings;

	if (empty($settings['cache_enable']) || $settings['cache_enable'] < $level || !is_array($cache_block = cache_get_data($key, 3600)) || (!empty($cache_block['refresh_eval']) && eval($cache_block['refresh_eval'])) || (!empty($cache_block['expires']) && $cache_block['expires'] < time()))
	{
		if (is_array($file))
			loadPluginSource($file[0], $file[1]);
		else
			loadSource($file);

		$cache_block = call_user_func_array($function, $params);

		if (!empty($settings['cache_enable']) && $settings['cache_enable'] >= $level)
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
	global $boardurl, $sourcedir, $settings, $memcached, $cache_type;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;

	if (empty($settings['cache_enable']) && !empty($settings))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'put', 's' => $val === null ? 0 : strlen(serialize($val)));
		$st = microtime(true);
	}

	if (empty($settings['cache_hash']))
		$settings['cache_hash'] = md5($boardurl . filemtime($sourcedir . '/Collapse.php'));

	$key = $settings['cache_hash'] . '-' . bin2hex($key);

	if ($val !== null)
		$val = serialize($val);

	if (empty($cache_type))
		get_cache_type();

	// The simple yet efficient memcached.
	if ($cache_type === 'memcached')
	{
		// Not connected yet?
		if (empty($memcached))
			get_memcached_server();
		if (!$memcached)
			return;

		memcache_set($memcached, $key, $val, 0, $ttl);
	}
	elseif ($cache_type === 'apc')
	{
		// An extended key is needed to counteract a bug in APC.
		if ($val === null)
			apc_delete($key . 'wedge');
		else
			apc_store($key . 'wedge', $val, $ttl);
	}
	elseif ($cache_type === 'zend')
		output_cache_put($key, $val);
	elseif ($cache_type === 'xcache')
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
			@unlink($cachedir . '/' . $key . '.php');
		else
		{
			$cache_data = '<' . '?php if(defined(\'WEDGE\')&&$valid=time()<' . (time() + $ttl) . ')$val=\'' . addcslashes($val, '\\\'') . '\';?' . '>';

			// Check that the cache write was successful. If it fails due to low diskspace, remove the cache file.
			if (file_put_contents($cachedir . '/' . $key . '.php', $cache_data, LOCK_EX) !== strlen($cache_data))
				@unlink($cachedir . '/' . $key . '.php');
		}
	}

	if (isset($db_show_debug) && $db_show_debug === true)
		$cache_hits[$cache_count]['t'] = microtime(true) - $st;
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
	global $boardurl, $sourcedir, $settings, $memcached, $cache_type;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;

	if (empty($settings['cache_enable']) && !empty($settings))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'get');
		$st = microtime(true);
	}

	if (empty($settings['cache_hash']))
		$settings['cache_hash'] = md5($boardurl . filemtime($sourcedir . '/Collapse.php'));

	$key = $settings['cache_hash'] . '-' . bin2hex($key);

	if (empty($cache_type))
		get_cache_type();

	if ($cache_type === 'memcached')
	{
		// Not connected yet?
		if (empty($memcached))
			get_memcached_server();
		if (!$memcached)
			return;

		$val = memcache_get($memcached, $key);
	}
	elseif ($cache_type === 'apc')
		$val = apc_fetch($key . 'wedge');
	elseif ($cache_type === 'zend')
		$val = output_cache_get($key, $ttl);
	elseif ($cache_type === 'xcache')
		$val = xcache_get($key);
	// Otherwise it's the file cache!
	elseif (file_exists($cachedir . '/' . $key . '.php') && @filesize($cachedir . '/' . $key . '.php') > 10)
	{
		@include($cachedir . '/' . $key . '.php');
		if (empty($valid))
			@unlink($cachedir . '/' . $key . '.php');
	}

	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count]['t'] = microtime(true) - $st;
		$cache_hits[$cache_count]['s'] = isset($val) ? strlen($val) : 0;
	}

	// If the operation requires re-caching, return null to let the script know.
	if (!empty($val))
		return unserialize($val);
	return null;
}

function get_cache_type()
{
	global $cache_type, $settings;

	$cache_type = 'file';

	// Okay, let's go for it memcached!
	if (isset($settings['cache_memcached']) && function_exists('memcache_get') && function_exists('memcache_set') && trim($settings['cache_memcached']) !== '')
		$cache_type = 'memcached';
	// Alternative PHP Cache from PECL.
	elseif (function_exists('apc_fetch') && function_exists('apc_store'))
		$cache_type = 'apc';
	// Zend Platform/ZPS/pricey stuff.
	elseif (function_exists('output_cache_get') && function_exists('output_cache_put'))
		$cache_type = 'zend';
	// XCache
	elseif (function_exists('xcache_get') && function_exists('xcache_set') && ini_get('xcache.var_size') > 0)
		$cache_type = 'xcache';
}

/**
 * Attempt to connect to Memcache server for retrieving cached items.
 *
 * This function acts to attempt to connect (or persistently connect, if persistent connections are enabled) to a memcached instance, looking up the server details from $settings['cache_memcached'].
 *
 * If connection is successful, the global $memcached will be a resource holding the connection or will be false if not successful. The function will attempt to call itself in a recursive fashion if there are more attempts remaining.
 *
 * @param int $level The number of connection attempts that will be made, defaulting to 3, but reduced if the number of server connections is fewer than this.
 */
function get_memcached_server($level = 3)
{
	global $settings, $memcached, $db_persist;

	$servers = explode(',', $settings['cache_memcached']);
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