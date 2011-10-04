<?php
/**
 * Wedge
 *
 * This file handles functions that allow caching data in Wedge: regular data (cache_get/put_data), CSS and JavaScript.
 * It also ensures that CSS and JS files are properly parsed and compressed before they're cached.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
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
function add_js_file($files = array(), $is_direct_url = false, $is_out_of_flow = false)
{
	global $context, $modSettings, $footer_coding, $settings, $cachedir, $boardurl;
	static $done_files = array();

	if (!is_array($files))
		$files = (array) $files;

	// Delete all duplicates and already cached files.
	$files = array_diff(array_keys(array_flip($files)), $done_files);
	$done_files = array_merge($done_files, $files);
	if (empty($files))
		return;

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
	global $context, $pluginsdir, $cachedir, $boardurl, $footer_coding;
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
	$id = !empty($modSettings['obfuscate_filenames']) ? md5(substr($id, 0, -1)) . '-' : $id;

	$can_gzip = !empty($modSettings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? ($context['browser']['is_safari'] ? '.jgz' : '.js.gz') : '.js';

	$final_file = $cachedir . '/' . $id . $latest_date . $ext;
	if (!file_exists($final_file))
		wedge_cache_js($id, $latest_date, $final_file, $files, $can_gzip, $ext, true);

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
 * This function adds a string to the header's inline CSS.
 * Several strings can be passed as parameters, allowing for easier conversion from an "echo" to an "add_css()" call.
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
 * @return string The generated code for direct inclusion in the source code, if $out_of_flow is set. Otherwise, nothing.
 */
function add_css_file($original_files = array(), $add_link = false)
{
	global $context, $modSettings, $settings, $cachedir, $boardurl;

	if (!is_array($original_files))
		$original_files = (array) $original_files;

	// Delete all duplicates.
	$files = $original_files = array_keys(array_flip($original_files));

	foreach ($files as $file)
		foreach ($context['css_suffixes'] as $gen)
			$files[] = $file . '.' . $gen;

	$latest_date = 0;
	$is_default_theme = true;
	$not_default = $settings['theme_dir'] !== $settings['default_theme_dir'];
	$skin = !empty($context['skin']) ? $context['skin'] : (!empty($modSettings['theme_skin_guests']) ? $modSettings['theme_skin_guests'] : 'skins');

	foreach ($files as $i => &$file)
	{
		if (strpos($file, '.css') === false)
		{
			$dir = $skin;
			$ofile = $file;
			$file = $dir . '/' . $ofile . '.css';
			// Does this file at least exist in the current skin...? If not, try the parent skin, until our hands are empty.
			while (!empty($dir) && !file_exists($settings['theme_dir'] . '/' . $file) && !file_exists($settings['default_theme_dir'] . '/' . $file))
			{
				$dir = $dir === '.' ? '' : dirname($dir);
				$file = $dir . '/' . $ofile . '.css';
			}
		}
		$target = $not_default && file_exists($settings['theme_dir'] . '/' . $file) ? 'theme_' : (file_exists($settings['default_theme_dir'] . '/' . $file) ? 'default_theme_' : false);
		if (!$target)
		{
			unset($files[$i]);
			continue;
		}

		$is_default_theme &= $target === 'default_theme_';
		$file = $settings[$target . 'dir'] . '/' . $file;
		$latest_date = max($latest_date, filemtime($file));
	}

	$folder = end($context['css_folders']);
	$id = $is_default_theme || $settings['theme_dir'] === 'default' ? '' : substr(strrchr($settings['theme_dir'], '/'), 1) . '-';
	$id = $folder === 'skins' ? substr($id, 0, -1) : $id . str_replace('/', '-', strpos($folder, 'skins/') === 0 ? substr($folder, 6) : $folder);
	$id .= (empty($id) ? '' : '-') . implode('-', $original_files) . '-';

	// We need to cache different versions for different browsers, even if we don't have overrides available.
	// This is because Wedge also transforms regular CSS to add vendor prefixes and the like.
	$id .= implode('-', $context['css_suffixes']);

	// We don't need to have 'webkit' in the URL if we already have a named browser in it.
	if ($context['browser']['is_webkit'] && $context['browser']['agent'] != 'webkit')
		$id = preg_replace('~(?:^webkit-|-webkit(?=-)|-webkit$)~', '', $id, 1);

	if (isset($context['user']) && $context['user']['language'] !== 'english')
		$id .= '-' . $context['user']['language'];

	$id = (!empty($modSettings['obfuscate_filenames']) ? md5(substr($id, 0, -1)) : $id) . '-';
	$can_gzip = !empty($modSettings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? ($context['browser']['agent'] == 'safari' ? '.cgz' : '.css.gz') : '.css';

	$final_file = $cachedir . '/' . $id . $latest_date . $ext;
	if (!file_exists($final_file))
		wedge_cache_css_files($id, $latest_date, $final_file, $files, $can_gzip, $ext);

	$final_script = $boardurl . '/cache/' . $id . $latest_date . $ext;

	// Do we just want the URL?
	if (!$add_link)
		return $final_script;

	$context['header'] .= '
	<link rel="stylesheet" href="' . $final_script . '">';
}

function add_plugin_css_file($plugin_name, $original_files = array(), $add_link = false)
{
	global $context, $modSettings, $settings, $cachedir, $boardurl, $pluginsdir;

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

	$id = $context['enabled_plugins'][$plugin_name] . '-' . implode('-', $basefiles) . '-';

	// We need to cache different versions for different browsers, even if we don't have overrides available.
	// This is because Wedge also transforms regular CSS to add vendor prefixes and the like.
	$id .= implode('-', $context['css_suffixes']);

	// We don't need to have 'webkit' in the URL if we already have a named browser in it.
	if ($context['browser']['is_webkit'] && $context['browser']['agent'] != 'webkit')
		$id = preg_replace('~(?:^webkit-|-webkit(?=-)|-webkit$)~', '', $id, 1);

	if (isset($context['user']) && $context['user']['language'] !== 'english')
		$id .= '-' . $context['user']['language'];

	$id = (!empty($modSettings['obfuscate_filenames']) ? md5(substr($id, 0, -1)) : $id) . '-';
	$can_gzip = !empty($modSettings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? ($context['browser']['agent'] == 'safari' ? '.cgz' : '.css.gz') : '.css';

	$final_file = $cachedir . '/' . $id . $latest_date . $ext;
	if (!file_exists($final_file))
		wedge_cache_css_files($id, $latest_date, $final_file, $files, $can_gzip, $ext, $context['plugins_url'][$plugin_name]);

	$final_script = $boardurl . '/cache/' . $id . $latest_date . $ext;

	// Do we just want the URL?
	if (!$add_link)
		return $final_script;

	$context['header'] .= '
	<link rel="stylesheet" href="' . $final_script . '">';
}

/**
 * Analyzes the list of required CSS files and returns (or generates) the final file.
 *
 * The CSS file list is taken from $context['css_main_files'], along with custom.css and css_suffixes variations.
 */
function wedge_cache_css()
{
	global $settings, $modSettings, $context, $db_show_debug, $cachedir, $boardurl;

	// Mix CSS files together!
	$css = array();
	$latest_date = 0;
	$is_default_theme = true;
	$not_default = $settings['theme_dir'] !== $settings['default_theme_dir'];

	// Make sure custom.css, if available, is added last.
	$files = array_merge($context['css_main_files'], (array) 'custom');

	// Add all possible variations of a file name.
	foreach ($files as $file)
		foreach ($context['css_suffixes'] as $gen)
			$files[] = $file . '.' . $gen;

	foreach ($context['skin_folders'] as &$folder)
	{
		$fold = $folder[0];
		$target = $folder[1];
		foreach ($files as &$file)
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

	$folder = end($context['css_folders']);
	$id = $context['skin_uses_default_theme'] ? '' : substr(strrchr($settings['theme_dir'], '/'), 1) . '-';
	$id = $folder === 'skins' ? substr($id, 0, -1) : $id . str_replace('/', '-', strpos($folder, 'skins/') === 0 ? substr($folder, 6) : $folder) . '-';

	$can_gzip = !empty($modSettings['enableCompressedData']) && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip');
	$ext = $can_gzip ? ($context['browser']['agent'] == 'safari' ? '.cgz' : '.css.gz') : '.css';

	unset($context['css_main_files'][0], $context['css_main_files'][1]);
	$id .= implode('-', $context['css_main_files']) . (empty($context['css_main_files']) ? '' : '-');
	$id .= implode('-', $context['css_suffixes']);

	// We don't need to have 'webkit' in the URL if we already have a named browser in it.
	if ($context['browser']['is_webkit'] && $context['browser']['agent'] != 'webkit')
		$id = preg_replace('~(?:^webkit-|-webkit(?=-)|-webkit$)~', '', $id, 1);

	if (isset($context['user']) && $context['user']['language'] !== 'english')
		$id .= '-' . $context['user']['language'];

	$context['cached_css'] = $boardurl . '/cache/' . $id . '-' . $latest_date . $ext;
	$final_file = $cachedir . '/' . $id . '-' . $latest_date . $ext;

	// Is the file already cached and not outdated? If not, recache it.
	if (!file_exists($final_file) || filemtime($final_file) < $latest_date)
		wedge_cache_css_files($id, $latest_date, $final_file, $css, $can_gzip, $ext);
}

/**
 * Create a compact CSS file that concatenates, pre-parses and compresses a list of existing CSS files.
 */
function wedge_cache_css_files($id, $latest_date, $final_file, $css, $can_gzip, $ext, $plugin_path = '')
{
	global $settings, $modSettings, $css_vars, $context, $cachedir, $boarddir, $boardurl, $prefix;

	// Delete cached versions, unless they have the same timestamp (i.e. up to date.)
	foreach (glob($cachedir . '/' . $id . '*' . $ext) as $del)
		if (!strpos($del, $latest_date))
			@unlink($del);

	$final = '';
	$discard_dir = strlen($boarddir) + 1;

	// Load our sweet, short and fast CSS parser
	loadSource('Class-CSS');

	$plugins = array(
		new wecss_dynamic(),	// Dynamic replacements through callback functions
		new wecss_mixin(),		// CSS mixins (mixin hello($world: 0))
		new wecss_var(),		// CSS variables ($hello_world)
		new wecss_color(),		// CSS color transforms
		new wecss_func(),		// Various CSS functions
		new wecss_nesting(),	// Nested selectors (.hello { .world { color: 0 } }) + selector inheritance (.hello { base: .world })
		new wecss_math()		// Math function (math(1px + 3px), math((4*$var)/2em)...)
	);

	// rgba to rgb conversion for IE 6/7/8/9
	if ($context['browser']['is_ie'])
		$plugins[] = new wecss_rgba();

	// No need to start the Base64 plugin if we can't gzip the result or the browser can't see it...
	// (Probably should use more specific browser sniffing.)
	if ($can_gzip && !$context['browser']['is_ie6'] && !$context['browser']['is_ie7'])
		$plugins[] = new wecss_base64();

	// Default CSS variables (paths are set relative to the cache folder)
	// !!! If subdomains are allowed, should we use absolute paths instead?
	$images_url = '..' . str_replace($boardurl, '', $settings['images_url']);
	$language_folder = isset($context['user']) && file_exists($settings['theme_dir'] . '/images/' . $context['user']['language']) ? $context['user']['language'] : 'english';
	$css_vars = array(
		'$language_dir' => $settings['theme_dir'] . '/images/' . $language_folder,
		'$language' => $images_url . '/' . $language_folder,
		'$images_dir' => $settings['theme_dir'] . '/images',
		'$images' => $images_url,
		'$theme_dir' => $settings['theme_dir'],
		'$theme' => '..' . str_replace($boardurl, '', $settings['theme_url']),
		'$root' => '../',
	);
	if (!empty($plugin_path))
		$css_vars['$plugindir'] = $plugin_path;
	else
		unset($css_vars['$plugindir']);

	// Load all CSS files in order, and replace $here with the current folder while we're at it.
	foreach ($css as $file)
		$final .= str_replace('$here', '..' . str_replace($boarddir, '', dirname($file)), file_get_contents($file));

	// CSS is always minified. It takes just a sec' to do, and doesn't impair anything.
	$final = str_replace(array("\r\n", "\r"), "\n", $final); // Always use \n line endings.
	$final = preg_replace('~/\*(?!!).*?\*/~s', '', $final); // Strip comments except...
	preg_match_all('~/\*!(.*?)\*/~s', $final, $comments); // ...for /*! Copyrights */...
	$final = preg_replace('~/\*!.*?\*/~s', '.wedge_comment_placeholder{border:0}', $final); // Which we save.
	$final = preg_replace('~\n\t*//[^\n]*~', "\n", $final); // Strip comments at the beginning of lines.
	$final = preg_replace('~//[ \t][^\n]*~', '', $final); // Strip remaining comments like me. OMG does this mean I'm gonn

	foreach ($plugins as $plugin)
		$plugin->process($final);

	$final = preg_replace('~\s*([+:;,>{}[\]\s])\s*~', '$1', $final);

	// Build a prefix variable, enabling you to use "-prefix-something" to get it replaced with your browser's own flavor, e.g. "-moz-something".
	$prefix = $context['browser']['is_opera'] ? '-o-' : ($context['browser']['is_webkit'] ? '-webkit-' : ($context['browser']['is_gecko'] ? '-moz-' : ($context['browser']['is_ie'] ? '-ms-' : '')));

	// Some CSS3 rules that are prominent enough in Wedge get the honor of a custom function. No need to use a prefix on them, although you may.
	$final = preg_replace_callback('~(?<!-)(?:border-radius|box-shadow|box-sizing|transition):[^\n;]+[\n;]~', 'wedge_fix_browser_css', $final);

	// Remove double quote hacks, remaining whitespace, no-base64 tricks, the 'final' keyword in its compact form, and replace browser prefixes.
	$final = str_replace(
		array('#wedge-quote#', "\n\n", ';;', ';}', "}\n", "\t", 'url-no-base64(', ' final{', ' final,', ' final ', '-prefix-'),
		array('"', "\n", ';', '}', '}', ' ', 'url(', '{', ',', ' ', $prefix),
		$final
	);
	// Restore comments as requested.
	if (!empty($comments))
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
 * Add browser-specific prefixes to a few commonly used CSS attributes (in Wedge at least.)
 * This is pretty basic, and could probably benefit from being less hackish and more systematic.
 *
 * @param string $matches The actual CSS contents
 * @return string Updated CSS contents with fixed code
 */
function wedge_fix_browser_css($matches)
{
	global $browser, $prefix;

	if ($browser['is_ie'] && $browser['version'] >= 9 && strpos($matches[0], 'border-radius') === 0)
		return $matches[0];

	if (!empty($prefix) && (!$browser['is_opera'] || strpos($matches[0], 'bo') !== 0))
		return $prefix . $matches[0];

	return $matches[0];
}

// Dynamic function to cache language flags into index.css
function dynamic_language_flags($match)
{
	global $context, $modSettings;

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
function dynamic_admin_menu_icons($match)
{
	global $context, $modSettings, $admin_areas, $ina;

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
		$icon = '/images/admin/' . $val[1];
		$rep .= '
.admenu_icon_' . $val[0] . ' extends .inline-block
	background: url($theme'. $icon . ') no-repeat
	width: width($theme_dir'. $icon . ')px
	height: height($theme_dir'. $icon . ')px';
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
	global $settings, $modSettings, $comments, $cachedir;

	$final = '';
	$dir = $full_path ? '' : $settings['theme_dir'] . '/';

	// Delete cached versions, unless they have the same timestamp (i.e. up to date.)
	foreach (glob($cachedir . '/' . $id. '*' . $ext) as $del)
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
		// An UglifyJS-inspired trick. We're taking it on the safe side though.
		$cont = preg_replace(array('~(?<=[ \t])false(?=[,; ])~', '~(?<=[ \t])true(?=[,; ])~'), array('!1', '!0'), $cont);
		$final .= $cont;
	}

	// We make sure to remove jQuery (if present) before we pack the file.
	if (strpos($id, 'jquery') !== false)
	{
		preg_match('~<wedge_jquery>(.*?)</wedge_jquery>~s', $final, $jquery);
		if (!empty($jquery[1]))
			$final = str_replace($jquery[0], 'WEDGE_JQUERY();', $final);
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
	global $cachedir, $context, $modSettings, $browser, $boardurl;

	$final = '';
	$path = $modSettings['smileys_dir'] . '/' . $set . '/';
	$url  = '..' . str_replace($boardurl, '', $modSettings['smileys_url']) . '/' . $set . '/';
	$agent = $browser['agent'];
	updateSettings(array('smiley_cache-' . str_replace('.', '', $context['smiley_ext']) . '-' . $agent . '-' . $set => $context['smiley_now']));

	// Delete all remaining cached versions, if any (e.g. *.cgz for Safari.)
	foreach (glob($cachedir . '/smileys-' . $agent . '-' . $set . '-*' . $context['smiley_ext']) as $del)
		@unlink($del);

	foreach ($smileys as $name => $smiley)
	{
		$filename = $path . $smiley['file'];
		if (!file_exists($filename))
			continue;
		// Only small files should be embedded, really. We're saving on hits, not bandwidth.
		if (($browser['is_ie'] && $browser['version'] < 7) || ($smiley['embed'] && filesize($filename) > 4096) || !$context['smiley_gzip'])
			$smiley['embed'] = false;
		list ($width, $height) = getimagesize($filename);
		$ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
		$final .= '.' . $name . '{width:' . $width . 'px;height:' . $height . 'px;background:url('
				. ($smiley['embed'] ? 'data:image/' . $ext . ';base64,' . base64_encode(file_get_contents($filename)) : $url . $smiley['file']) . ')}';
	}

	if ($context['smiley_gzip'])
		$final = gzencode($final, 9);

	file_put_contents($cachedir . '/smileys-' . $agent . '-' . $set . '-' . $context['smiley_now'] . $context['smiley_ext'], $final);
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

	if (!empty($context['header_css']))
	{
		global $user_info;

		// Replace $behavior with the forum's root URL in context, because pretty URLs complicate things in IE.
		if (strpos($context['header_css'], '$behavior') !== false)
			$context['header_css'] = str_replace('$behavior', strpos($boardurl, '://' . $user_info['host']) !== false ? $boardurl
				: preg_replace('~(?<=://)([^/]+)~', $user_info['host'], $boardurl), $context['header_css']);
	}
}

/**
 * Shows the base JavaScript calls, i.e. including jQuery and script.js
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
	global $settings, $context, $scripturl;

	$is_default_theme = true;
	$not_default = $settings['theme_dir'] !== $settings['default_theme_dir'];

	// We will rebuild the css folder list, in case we have a replace-type skin in our path.
	$context['skin_folders'] = array();

	foreach ($context['css_folders'] as &$folder)
	{
		$target = $not_default && file_exists($settings['theme_dir'] . '/' . $folder) ? 'theme_' : 'default_theme_';
		$is_default_theme &= $target === 'default_theme_';
		$fold = $settings[$target . 'dir'] . '/' . $folder . '/';
		$context['skin_folders'][] = array($fold, $target);

		if (file_exists($fold . 'skin.xml'))
		{
			$set = file_get_contents($fold . '/skin.xml');
			// If this is a replace-type skin, forget all of the parent folders.
			if ($folder !== 'skins' && strpos($set, '</type>') !== false && preg_match('~<type>([^<]+)</type>~', $set, $match) && strtolower(trim($match[1])) === 'replace')
				$context['skin_folders'] = array($fold, $target);
		}
	}

	$context['skin_uses_default_theme'] = $is_default_theme;

	// The deepest skin gets CSS/JavaScript attention.
	if (!empty($set))
	{
		if (strpos($set, '</skeleton>') !== false && preg_match('~<skeleton>(.*?)</skeleton>~s', $set, $match))
			$context['skeleton'] = $match[1];

		if (strpos($set, '</css>') !== false && preg_match_all('~<css(?:\s+for="([^"]+)")?\s*>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</css>~s', $set, $matches, PREG_SET_ORDER))
			foreach ($matches as $match)
				if (empty($match[1]) || in_array($context['browser']['agent'], explode(',', $match[1])))
					add_css(rtrim($match[2], "\t"));

		if (strpos($set, '</code>') !== false && preg_match_all('~<code(?:\s+for="([^"]+)")?(?:\s+include="([^"]+)")?\s*>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</code>~s', $set, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				if (!empty($match[1]) && !in_array($context['browser']['agent'], explode(',', $match[1])))
					continue;

				if (!empty($match[2]))
				{
					$includes = array_map('trim', explode(',', $match[2]));
					// If we have an include param in the code tag, it should either use 'scripts/something.js' (in which case it'll
					// find data in the current theme, or the default theme), or '$here/something.js', where it'll look in the skin folder.
					if (strpos($match[2], '$here') !== false)
						foreach ($includes as &$scr)
							$scr = str_replace('$here', str_replace($settings['theme_dir'] . '/', '', $folder), $scr);
					add_js_file($includes);
				}
				add_js(rtrim($match[3], "\t"));
			}
		}

		if (strpos($set, '</macro>') !== false && preg_match_all('~<macro\s+name="([^"]+)"(?:\s+for="([^"]+)")?\s*>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</macro>~s', $set, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				if (!empty($match[2]) && !in_array($context['browser']['agent'], explode(',', $match[2])))
					continue;
				$context['macros'][$match[1]] = array(
					'has_if' => strpos($match[3], '<if:') !== false,
					'body' => str_replace(array('{scripturl}'), array($scripturl), trim($match[3]))
				);
			}
		}
	}
}

/**
 * Cleans some or all of the files stored in the file cache.
 *
 * @param string $extensions Optional, a comma-separated list of file extensions that should be pruned. Leave empty to clear the regular data cache (data sub-folder.)
 * @param string $filter Optional, designates a filter to match the file names against before they can be cleared from the cache folder. Leave empty to clear the regular data cache (data sub-folder.)
 * @todo Figure out a better way of doing this and get rid of $sourcedir being globalled again.
 */
function clean_cache($extensions = 'php', $filter = '')
{
	global $cachedir, $sourcedir;

	// No directory = no game.
	$folder = $cachedir . ($filter === '' && $extensions === 'php' ? '/data' : '');
	if (!is_dir($folder))
		return;

	if ($extensions === 'css')
		$extensions = array('css', 'cgz', 'css.gz');
	elseif ($extensions === 'js')
		$extensions = array('js', 'jgz', 'js.gz');

	// Remove the files in Wedge's own disk cache, if any.
	$dh = scandir($folder);
	$exts = array_flip((array) $extensions);
	foreach ($dh as $file)
		if ($file[0] !== '.' && $file !== 'index.php' && (!$filter || strpos($file, $filter) !== false))
			if (!$extensions || isset($exts[wedge_get_extension($file)]))
				@unlink($folder . '/' . $file);

	// Invalidate cache, to be sure!
	// ...as long as Collapse.php can be modified, anyway.
	@touch($sourcedir . '/Collapse.php');
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
 * @param mixed $val The raw data to be cached. This may be any data type but it will be serialized prior to being stored in the cache.
 * @param int $ttl The time the cache is valid for, in seconds. If a request to retrieve is received after this time, the item will not be retrieved.
 * @todo Remove cache types that are obsolete and no longer maintained.
 */
function cache_put_data($key, $val, $ttl = 120)
{
	global $boardurl, $sourcedir, $modSettings, $memcached, $cache_type;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;

	if (empty($modSettings['cache_enable']) && !empty($modSettings))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'put', 's' => $val === null ? 0 : strlen(serialize($val)));
		$st = microtime(true);
	}

	$key = md5($boardurl . filemtime($sourcedir . '/Collapse.php')) . '-Wedge-' . strtr($key, ':', '-');
	$val = $val === null ? null : serialize($val);

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
	elseif ($cache_type === 'eaccelerator')
	{
		if (mt_rand(0, 10) == 1)
			eaccelerator_gc();

		if ($val === null)
			@eaccelerator_rm($key);
		else
			eaccelerator_put($key, $val, $ttl);
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
			@unlink($cachedir . '/data/' . $key . '.php');
		else
		{
			$cache_data = '<' . '?php if(defined(\'WEDGE\')&&$valid=time()<' . (time() + $ttl) . ')$val=\'' . addcslashes($val, '\\\'') . '\';?' . '>';
			$fh = @fopen($cachedir . '/data/' . $key . '.php', 'w');
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
					@unlink($cachedir . '/data/' . $key . '.php');
			}
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
	global $boardurl, $sourcedir, $modSettings, $memcached, $cache_type;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;

	if (empty($modSettings['cache_enable']) && !empty($modSettings))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'get');
		$st = microtime(true);
	}

	$key = md5($boardurl . filemtime($sourcedir . '/Collapse.php')) . '-Wedge-' . strtr($key, ':', '-');

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
	elseif ($cache_type === 'eaccelerator')
		$val = eaccelerator_get($key);
	elseif ($cache_type === 'apc')
		$val = apc_fetch($key . 'wedge');
	elseif ($cache_type === 'zend')
		$val = output_cache_get($key, $ttl);
	elseif ($cache_type === 'xcache')
		$val = xcache_get($key);
	// Otherwise it's the file cache!
	elseif (file_exists($cachedir . '/data/' . $key . '.php') && filesize($cachedir . '/data/' . $key . '.php') > 10)
	{
		require($cachedir . '/data/' . $key . '.php');
		if (empty($valid))
			@unlink($cachedir . '/data/' . $key . '.php');
	}

	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count]['t'] = microtime(true) - $st;
		$cache_hits[$cache_count]['s'] = isset($val) ? strlen($val) : 0;
	}

	// If the operation requires re-caching, return null to let the script know.
	return empty($val) ? null : unserialize($val);
}

function get_cache_type()
{
	global $cache_type, $modSettings;

	$cache_type = 'file';

	// Okay, let's go for it memcached!
	if (isset($modSettings['cache_memcached']) && function_exists('memcache_get') && function_exists('memcache_set') && trim($modSettings['cache_memcached']) !== '')
		$cache_type = 'memcached';
	// eAccelerator.
	elseif (function_exists('eaccelerator_get') && function_exists('eaccelerator_put'))
		$cache_type = 'eaccelerator';
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