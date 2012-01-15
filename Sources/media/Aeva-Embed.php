<?php
/**
 * Wedge
 *
 * The original auto-embedder, Aeva!
 * Uses portions written by Karl Benson.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

// Main auto embed function
function aeva_main($message)
{
	global $context, $modSettings, $sites, $upto, $user_info, $sourcedir;

	// Auto-embedding is disabled. We shouldn't have got this far, but if we have... get out of here.
	if (empty($modSettings['embed_enabled']))
		return $message;

	// Attempt to load all Enabled Sites, if not already loaded
	if (empty($sites) && file_exists($sourcedir . '/media/Aeva-Sites.php'))
		loadSource('media/Aeva-Sites');

	// Are we checking a link in the media gallery? We'd best avoid JavaScript then.
	if (isset($_REQUEST, $_REQUEST['action'], $_REQUEST['sa']) && $_REQUEST['action'] == 'media' && $_REQUEST['sa'] == 'post')
		$context['embed_mg_hack'] = true;

	// If we can't use generated version (either just after install, OR permissions meant generated version
	// couldn't be created, OR it can't be found), load the full un-optimized version and just keep the popular sites for performance.
	if (empty($sites))
	{
		loadSource(
			file_exists($sourcedir . '/media/Aeva-Sites-Custom.php') ? array('media/Subs-Aeva-Sites', 'media/Aeva-Sites-Custom') : 'media/Subs-Aeva-Sites'
		);

		// We're using the full version, so we just keep allowed local embeds and popular sites.
		foreach ($sites as $a => $b)
		{
			if (substr($b['id'], 0, 6) == 'local_')
			{
				// The local plugin for this site is disabled
				if (empty($modSettings['embed_' . $b['plugin']]))
					unset($sites[$a]);
			}
			// No more local ones, from now on only the popular sites will be kept.
			elseif ($b['type'] !== 'pop')
				unset($sites[$a]);
		}
	}

	// Houston, no sites = no embedding, so get out of here (after reversing any protections in place)
	if (empty($sites))
		return aeva_reverse_protection($message);

	// Protect...
	$message = aeva_protection(
		array(
			'noae' => true,
			'noembed' => true,
			'<noembed>' => false,
		),
		$message, true
	);

	// No embeddable links, so reverse protection and get out of here as fast as possible.
	if (!empty($context['aeva']['skip']))
	{
		// Clear this variable, otherwise we won't be able to embed again
		unset($context['aeva']['skip']);
		// Reverse protection/leftovers
		return aeva_reverse_protection($message);
	}

	// Create an array for our vars
	if (empty($context['aeva']))
		$context['aeva'] = array();

	// This array will contain the number of embeddable links we have per site (YouTube, etc.) in $message.
	$has = array();

	// Set / redo the limits
	aeva_limits();

	// Find embeddable links for each enabled site to the end
	for ($upto = 0, $j = count($sites); $upto < $j; $upto++)
	{
		$has_any = aeva_match($message);

		if ($has_any)
			$has[$upto] = $has_any;

		// If no active http links remain (making further embedding impossible), then stop
		if ($has_any && strpos($message, '<a href="http://') === false)
			break;
	}

	// Do we need to ensure the link isn't inside a sentence?
	if (empty($modSettings['embed_incontext']))
	{
		// First, we start by making a test string with all div tags turned into linebreaks,
		// because they act the same way, and all other tags removed (except for <aeva>).
		$test = '<br>' . strip_tags(preg_replace('~</?div(?:\s[^>]*)?>~s', '<br>', $message), '<br><aeva>') . '<br>';
		$offset = 0;

		// Now, we get a list of all <aeva> tags (i.e. videos to embed), as well as their position.
		preg_match_all('`<aeva .*?</aeva>`', $test, $matches, PREG_OFFSET_CAPTURE);
		preg_match_all('`<aeva .*?</aeva>`', $message, $original_matches, PREG_OFFSET_CAPTURE);

		foreach ($matches[0] as $i => &$match)
		{
			// Now we get the entire test string before the current <aeva> and after it.
			$len = strlen($match[0]);
			$first = substr($test, 0, $match[1]);
			$last  = substr($test, $match[1] + $len);

			// We remove everything up to the <br>'s closest to the link...
			$final = substr($first, strrpos($first, '<br>') + 4) . substr($last, 0, strpos ($last,  '<br>'));

			// ...and remove whitespace and other <aeva>'s. If there's anything left, it means we're in a sentence. Hail to the king.
			if (trim(preg_replace('`<aeva .*?</aeva>`', '', $final)) !== '')
			{
				$message = substr_replace($message, str_replace(array('<aeva ', '</aeva>'), array('<a ', '</a>'), $match[0]), $original_matches[0][$i][1] + $offset, $len);
				$offset += 6;
			}
		}
	}

	// ... Start embedding ;)
	foreach ($has as $upto => $num)
		if ($num > 0)
			aeva_match($message, true);

	// Reverse protections and return the finished embedded content
	return aeva_reverse_protection($message);
}

// Protects urls in places we don't want to touch, from being embedded or autolinked.
// aeva_protection(
//		array('<noembed>' => false),	= BBC/html item to protect => whether to retain it
//		$message,						= Content
//		true							= If no http links remain, return non-protected and set $context['embed_skip']
// );
function aeva_protection($array = array(), $input, $reverse = false)
{
	// Return if either is empty
	if (empty($input) || empty($array))
		return $input;

	global $context, $modSettings;

	// Protect each item
	foreach ($array as $item => $retain)
	{
		// Quotes
		if ($item == 'quote')
		{
			// Quotes are not to be protected from embedding inside
			if (!empty($modSettings['embed_quotes']))
				continue;

			// Always retained, never replaced, we need a preg for this one as well.
			$input = preg_replace(
				array('~(\[quote[]\s=])~i', '~\[/quote]~i'),
				array('[noae]$1', '[/quote][/noae]'),
				$input
			);

			// With quotes they are always retained, never replaced.
			// So we've reused this value instead of decide whether to continue to full protection or just wrap
			if (empty($retain))
				continue;
		}
		// Html? tag
		elseif ($item == '<noembed>')
			$input = str_ireplace(
				array('<noembed>', '</noembed>'),
				array('[noae]<noembed>', '</noembed>[/noae]'),
				$input
			);
		// All other tags apart from [noae]
		elseif ($item != 'noae')
			$input = str_ireplace(
				array('[' . $item . ']', '[/' . $item . ']'),
				array('[noae]' . (empty($retain) ? '' : '[' . $item . ']'), (empty($retain) ? '' : '[/' . $item . ']') . '[/noae]'),
				$input
			);

		$unaltered = $input;

		// Protect the item. Those in the array won't be autolinked.
		$input = in_array($item, array('html', 'code', 'php', '<noembed>', 'noae')) ? aeva_protect_recursive($input) : aeva_protect_recursive_autolink($input);

		// Recursive error? Check for null/empty.
		if (empty($input))
		{
			// Set this variable to prevent further embedding for this topic.
			$context['aeva']['skip'] = true;
			// Returned the pre-altered version
			return $unaltered;
		}

		// Oh crumbs! Remove any leftovers which could screw up recursions.
		$input = aeva_crumbs($input);
	}

	// Tidy up
	unset($unaltered);

	// No links remaining? No further embedding possible, so set this variable before we return.
	if ($reverse === true && strpos($input, '<a href="http://') === false)
		$context['aeva']['skip'] = true;

	return $input;
}

// Protect noembed & autolink items from embedding *before* BBC parsing - wrap quotes, but don't protect
function aeva_preprotect(&$message, $cache_id)
{
	if ((strpos($message, 'http://') === false && stripos($message, '[noembed]') === false) || strpos($cache_id, 'sig') !== false)
		return false;

	global $modSettings, $context;

	if (!empty($modSettings['cache_enable']))
	{
		$context['embed_cache_enable'] = $modSettings['cache_enable'];
		$modSettings['cache_enable'] = false;
	}

	// Replace now, so any [url] links get converted as well
	$array = array('noembed' => false);

	// Protect quotes from embedding
	if (empty($modSettings['embed_quote']))
		$array['quote'] = false;

	// Protect all these items
	$message = aeva_protection($array, $message, false);

	return true;
}

// Remove any breadcrumbs (leftovers) remaining
function aeva_crumbs($input)
{
	// Lowercase only as [noae] is only used by this mod.
	return str_replace(array('[noae]', '[/noae]'), '', $input);
}

// Reverses protections, and also clears any leftovers in one go.
function aeva_reverse_protection($input)
{
	return str_ireplace(array('[noembed]', '[/noembed]'), '', str_replace(array('noae://', '[noae]', '[/noae]'), array('http://'), $input));
}

// Callback, only build the embed on each match
function aeva_match(&$message, $for_real = false)
{
	global $context, $sites, $upto, $boardurl, $modSettings;
	static $local_done = false;

	// Prevent stupid loop/crash. Also, if loading full version, return if disabled.
	if (empty($sites[$upto]['pattern']) || !empty($sites[$upto]['disabled']))
		return 0;

	// I don't think we'll be saving $sites later, so we'll just
	// transform {local} variables in it so it's only done once...
	$regex =& $sites[$upto]['pattern'];

	// Local files embed - do some magic
	if (!$local_done && strpos($regex, '{local}') !== false)
	{
		// Parse the boardurl to grab the domain we're on
		if (!empty($modSettings['embed_nonlocal']))
			$regex = str_replace('{local}', '[a-z]+://[^"]+?/', $regex);
		else
		{
			$x = @parse_url($boardurl);
			$x['scheme'] = !empty($x['scheme']) ? $x['scheme'] : 'http';
			$x['host'] = !empty($x['host']) ? $x['host'] : null;

			// We can't parse your domain, so return to avoid errors
			if (empty($x['host']))
				return 0;

			$regex = str_replace('{local}', preg_quote($x['scheme'], '`') . '://(?:[a-z0-9-]{1,32}\.){0,3}' . preg_quote($x['host'], '`') . '/', $regex);
		}
	}
	// Local sites are at the beginning. If this site isn't local, we can stop looking.
	else
		$local_done = true;

	// Match everything, and do the actual embedding.
	if ($for_real)
	{
		$message = preg_replace_callback('`<aeva href="(' . $regex . '[^"]*)"[^>]*>(.*?)</aeva>`', 'aeva_build_object', $message);
		return;
	}

	// Match everything, and return the number of existing embeddable links for this site.
	$message = preg_replace('`<a (href="' . $regex . '[^"]*"[^>]*>.*?)</a>`', '<aeva $1</aeva>', $message, -1, $has_any);
	return $has_any;
}

// The core function: replace matched links with the full embedded object.
function aeva_build_object($input)
{
	global $context, $modSettings, $sites, $upto, $boardurl, $txt;
	static $swfobjects = 0;

	// Load the language files, English if no translated version is available.
	if (!isset($txt['aeva']) && loadLanguage('Media') == false)
		loadLanguage('Media', 'english');

	if ($context['aeva']['remaining'] == 0)
		return str_replace(array('<aeva href="http://', '</aeva>'), array('<a href="noae://', '</a>'), preg_replace('`#[\w/.~-]*`', '', $input[0], 1)) . ' ' . $txt['media_too_many_embeds'];

	$arr =& $sites[$upto];
	$use_object_init = (isset($_REQUEST['action']) && $_REQUEST['action'] == '.xml') || isset($_REQUEST['xml']) || WEDGE == 'SSI' || !empty($modSettings['embed_noscript']) || !empty($context['embed_mg_hack']);
	$use_object = $use_object_init || (!empty($arr['plugin']) && $arr['plugin'] != 'flash') || !empty($arr['allow-script']) || ($arr['id'] == 'yav' && $context['browser']['is_firefox']);

	$object = $extra_js = '';
	$link = '<a href="<aeva-link>" target="_blank" class="aeva_link bbc_link new_win"><aeva-title></a>';

	if (!isset($context['browser']['is_ie8']) && !$context['browser']['is_ie'])
		$context['browser']['is_ie'] = $context['browser']['is_ie8'] = strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE 8') !== false;

	// Depending on the plugin, use different parameters
	if (empty($arr['plugin']) || $arr['plugin'] == 'flash')
	{
		$plugin = array(
			'classid' => 'CLSID:D27CDB6E-AE6D-11CF-96B8-444553540000',
			'type' => 'application/x-shockwave-flash',
			'codebase' => 'http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,115,0',
			'params' => array(
				'wmode' => 'transparent', 'quality' => 'high', 'allowFullScreen' => 'true', 'allowScriptAccess' => !empty($arr['allow-script']) ? 'always' : 'never',
				'pluginspage' => 'http://www.macromedia.com/go/getflashplayer', 'autoplay' => 'false', 'autostart' => 'false',
			),
		);
	}
	elseif ($arr['plugin'] == 'divx')
	{
		$plugin = array(
			'classid' => 'CLSID:67DABFBF-D0AB-41FA-9C46-CC0F21721616',
			'type' => 'video/divx',
			'codebase' => 'http://go.divx.com/plugin/DivXBrowserPlugin.cab',
			'params' => array(
				'allowFullScreen' => 'true', 'allowScriptAccess' => 'never', 'pluginspage' => 'http://go.divx.com/plugin/download/',
				'custommode' => 'none', 'autoPlay' => 'false', 'mode' => 'full', 'minVersion' => '1.0.0', 'allowContextMenu' => 'true',
				'url' => '$2', 'bannerEnabled' => 'false', 'showpostplaybackad' => 'false',
			),
			'src' => 'src',
		);
	}
	elseif ($arr['plugin'] == 'wmp')
	{
		$plugin = array(
			'classid' => 'CLSID:22D6F312-B0F6-11D0-94AB-0080C74C7E95',
			'type' => 'application/x-mplayer2',
			'codebase' => 'http://activex.microsoft.com/activex/controls/mplayer/en/nsmp2inf.cab#Version=6,4,7,1112',
			'params' => array(
				'wmode' => 'transparent', 'quality' => 'high', 'allowFullScreen' => 'true', 'allowScriptAccess' => 'never',
				'ShowControls' => 'True', 'autostart' => 'false', 'autoplay' => 'false',
			),
			'src' => 'filename', // wmp uses filename instead of src or movie for the param name
		);
	}
	elseif ($arr['plugin'] == 'quicktime')
	{
		$plugin = array(
			'classid' => 'CLSID:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B',
			'type' => 'video/quicktime',
			'codebase' => 'http://www.apple.com/qtactivex/qtplugin.cab',
			'params' => array(
				'pluginspage' => 'http://www.apple.com/quicktime/download/', 'wmode' => 'transparent', 'quality' => 'high', 'allowFullScreen' => 'true',
				'allowScriptAccess' => 'never', 'scale' => 'aspect', 'loop' => 'false', 'controller' => 'true', 'autoplay' => 'false',
			),
			'src' => 'src',
		);
	}
	elseif ($arr['plugin'] == 'realmedia')
	{
		$plugin = array(
			'classid' => 'CLSID:CFCDAA03-8BE4-11CF-B84B-0020AFBBCCFA',
			'type' => 'audio/x-pn-realaudio-plugin',
			'params' => array(
				'pluginspage' => 'http://www.real.com', 'wmode' => 'transparent', 'quality' => 'high', 'allowFullScreen' => 'true',
				'allowScriptAccess' => 'never', 'controls' => 'imagewindow', 'console' => 'video', 'autostart' => 'false',
			),
			'src' => 'src',
			'extra' => ($context['browser']['is_ie'] ? '<object id="rvocx" classid="CLSID:CFCDAA03-8BE4-11CF-B84B-0020AFBBCCFA" width="$8px" height="30px"><param name="src" value="$1"><param name="autostart" value="false"><param name="controls" value="ControlPanel"><param name="console" value="video">' : '') .
				'<embed src="$1" width="$8px" height="30" controls="ControlPanel" type="audio/x-pn-realaudio-plugin" console="video" autostart="false"></embed>' . ($context['browser']['is_ie'] ? '</object>' : ''),
		);
	}

	// Build the params with both formats for <object> and <embed>
	$swo_params = $object_params = $embed_params = '';

	$tentative_title = array_pop($input);

	// Get the option list, such as videolink#hq-w500-h400
	$options = strrchr($input[1], '#');
	$opt = empty($options) ? array() : str_replace('~', '-', explode('-', substr($options, 1)));

	// Strip the #options out of the original link
	$input[1] = preg_replace('~#.*~', '', $input[1]);
	$input = preg_replace('~(#[^"<]*)~e', 'str_replace("~", "-", "$1")', $input);
	if ($tentative_title && substr($tentative_title, 0, 7) !== 'http://')
		$title = $tentative_title;

	$current_width = isset($_COOKIE['embed_quality']) ? $_COOKIE['embed_quality'] : 1;
	$default_size = $size_type = isset($arr['size']['normal']) ? $arr['size'][isset($opt['ws'], $arr['size']['ws']) ? 'ws' : 'normal'] : $arr['size'];
	$default_movie = is_array($arr['movie']) ? $arr['movie']['normal'] : $arr['movie'];
	$fixed_size = !empty($size_type[2]);

	// This is the place where we do our anchor settings magic... Parse the #ws-hd stuff!
	foreach ($opt as $ion)
	{
		if ($ion === '' || $ion === 'id' || $ion === 'key')
			continue;

		// Add some information for the user that external embedding of this clip is disallowed by the video's author
		if ($ion == 'noexternalembed')
			return str_replace(array('<aeva href="http://', '</aeva>'), array('<a href="noae://', '</a>'), preg_replace('`#noext[\w/.~-]*`', '', $input[0])) . ' ' . $txt['media_noexternalembedding'];

		if (is_array($arr['movie']) && isset($arr['movie'][$ion]))
			$movie_type = $ion;
		if (isset($arr['size'][$ion]))
			$size_type = $arr['size'][$ion];

		if ($ion[0] == 'w' && is_numeric(substr($ion, 1)))
			$size_type[0] = (int) substr($ion, 1);
		elseif ($ion[0] == 'h' && is_numeric($ion[1]))
			$size_type[1] = strpos($ion, '+') === false ? (int) substr($ion, 1) : (int) substr($ion, 1, strpos($ion, '+')-1) + (int) substr($ion, strpos($ion, '+')+1);
		elseif (empty($title) && substr($ion, 0, 2) == 't=')
			$title = base64_decode(substr($ion, 2));
		elseif ($ion == 'center')
			$center_this = true;
	}

	if (!empty($plugin))
	{
		foreach ($plugin['params'] as $a => $b)
		{
			if ($b === '')
				continue;
			$swo_params .= ',' . $a . ':"' . $b . '"';
			if ($use_object)
			{
				$embed_params .= ' ' . $a . '="' . $b . '"';
				if ($context['browser']['is_ie'])
					$object_params .= '<param name="' . $a . '" value="' . $b . '">';
			}
		}
	}
	unset($a, $b);

	if (!empty($arr['show-flashvars']) && $use_object)
	{
		$embed_params .= ' flashvars="<flashvars>"';
		if ($context['browser']['is_ie'])
			$object_params .= '<param name="flashvars" value="<flashvars>">';
	}

	if (!empty($plugin) && (isset($_REQUEST['xml']) || WEDGE == 'SSI'))
	{
		if ($swfobjects++ === 0)
			$swfobjects = rand(1000,9999);
	}
	elseif (!empty($plugin) && !$swfobjects++)
	{
		add_css('
		.maeva { font-size: 8pt; line-height: 11pt; overflow: auto }
		.maeva a:link, .maeva a:visited { text-decoration: none !important; border-bottom: 0 !important }
		.aeva_dq { font-weight: bold }
		.aeva_t { text-align: left; padding-top: 3px }
		.aeva_q { text-align: right; padding-top: 3px }
		a.aeva_dq:link { color: inherit }');

		if (!$use_object_init)
		{
			add_js_file('http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js', true);
			add_js('
	aevams = {', substr($swo_params, 1), '};', !empty($modSettings['embed_expins']) ? '
	aeinst = "' . $boardurl . '/expressInstall.swf";' : '');

			if (!$fixed_size)
				add_js('
	function aevatq(q, id, w, h)
	{
		var yt = $("#aevawi" + id)[0];
		$("#sae" + id).css("width", w);
		$("#saeva" + id).css("height", h);
		var dat = yt.data != "" ? yt.data : yt.movie;
		$("#aqc" + id).children("a").each(function () {
			this.className = this.href.indexOf("(" + q + ",") > 0 ? "aeva_dq" : "";
		});
		swfobject.createSWF({ data: dat, width: w, height: h }, aevams, "aevawi" + id);
		document.cookie = "aeva_quality=" + q + ";path=/";
	}');
		}
	}

	$embed = empty($movie_type) ? $default_movie : (isset($arr['movie'][$movie_type]) ? $arr['movie'][$movie_type] : $default_movie);

	// We need to deal with videos where titles can be viewed inside the thumbnail...
	$show_video_title = (int) (empty($modSettings['embed_inlinetitles']) || ($modSettings['embed_inlinetitles'] == 1 && empty($title)));
	if ($arr['id'] == 'ytb')
		$embed .= (!empty($modSettings['embed_yq']) ? '&hd=1' : '') . '&showinfo=' . $show_video_title;
	elseif ($arr['id'] == 'vimeo')
		$embed .= '&show_title=' . $show_video_title;

	$sw = $altw = !$size_type[0] ? '100%' : $size_type[0];
	$sh = $alth = !$size_type[1] ? '100%' : $size_type[1];
	$max_width = !empty($modSettings['embed_max_width']) && $sw != '100%' && !$fixed_size ? (int) $modSettings['embed_max_width'] : $sw;
	if ($sw != '100%')
	{
		$ui_height = isset($arr['ui-height']) ? (int) $arr['ui-height'] : 0;
		$altw = $max_width;
		$alth = round(($max_width / $sw) * ($sh - $ui_height)) + $ui_height;
		// If maximum width is lower than the default embed size, always use the max width.
		if ($max_width < $sw)
		{
			$sw = $altw;
			$sh = $alth;
		}
		if ($current_width == 1)
		{
			list ($sw, $altw) = array($altw, $sw);
			list ($sh, $alth) = array($alth, $sh);
		}
	}
	$swp = $sw == '100%' ? $sw : $sw . 'px';
	$shp = $sh == '100%' ? $sh : $sh . 'px';

	$show_raw_link = (isset($title) && (empty($modSettings['embed_titles']) || $modSettings['embed_titles'] < 2)) ||
					 (!empty($modSettings['embed_includeurl']) && !empty($arr['show-link']) && empty($context['embed_mg_calling']));
	$show_something_below = $show_raw_link || (!$use_object && $sw != $altw);

	if (!empty($plugin))
	{
		$object .= '
<table class="maeva cp0 cs0' . (!empty($modSettings['embed_center']) || !empty($center_this) ? ' centertext' : '') . '" style="width: ' . $swp . '" id="sae' . $swfobjects . '">
<tr><td style="width: ' . $swp . '; height: ' . $shp . '"' . ($show_something_below ? ' colspan="2"' : '') . ' id="saeva' . $swfobjects . '">';

		// Some sites might have stuff to show before
		if (!empty($arr['html-before']))
			$object .= $arr['html-before'];
	}

	if (empty($plugin))
		$object = $arr['movie'];

	// Build the <object> (Non-Mac IE Only)
	elseif ($use_object)
	{
		if ($context['browser']['is_ie'])
			$object .= '<object classid="' . $plugin['classid'] . '" ' .
				(!empty($plugin['codebase']) ? 'codebase="' . $plugin['codebase'] . '" ' : '') .
				'type="' . $plugin['type'] . '" width="' . $sw . '" height="' . $sh . '">' .
				'<param name="' . (empty($plugin['src']) ? 'movie' : $plugin['src']) . '" value="' . $embed . '">' . $object_params;

		// Build the <embed>
		$object .= '<embed type="' . $plugin['type'] . '" src="' . $embed
				. '" width="' . $sw . '" height="' . $sh . '"' . $embed_params . '>';

		// <noembed> tag containing raw link in case Flash is disabled. However, don't include it if we're going to show the raw link anyway.
		if (!$show_raw_link)
			$object .= '<noembed>' . $link . '</noembed>';

		// If using <object> remember to close it
		if ($context['browser']['is_ie'])
			$object .= '</object>';
	}
	else
	{
		$object .= '<div id="aevid' . $swfobjects . '">' . ($show_raw_link ? '' : $link) . '</div>';
		$extra_js .= '
	swfobject.embedSWF("<aeva-embed>", "aevid' . $swfobjects . '", "' . $sw . '", "' . $sh . '", "9", ' . (!empty($modSettings['embed_expins']) ? 'aeinst' : '""') . ', {}, ' . (!empty($arr['show-flashvars']) ?
		'{' . substr($swo_params, 1) . ',flashvars:"<flashvars>"}' : 'aevams') . ', {id:"aevawi' . $swfobjects . '"});';
	}

	// Any extra - required for RealPlayer since it needs dual embeds, and fix the width/height while we're at it
	if (!empty($plugin['extra']))
		$object .= str_replace(array('$8', '$9'), array($sw, $sh), $plugin['extra']);

	// Like before, but after ;)
	if (!empty($arr['html-after']))
		$object .= $arr['html-after'];

	if (!empty($plugin))
		$object .= '</td>';

	if ($show_something_below && empty($plugin))
		$object .= '<br>' . $link;
	elseif ($show_something_below)
	{
		$object .= '</tr>
<tr><td class="aeva_t">';

		// Include the original link? Don't do it if AM's gallery is calling for integration, because it would create a double embed.
		if ($show_raw_link)
			$object .= $link;

		$object .= '</td><td class="aeva_q" id="aqc' . $swfobjects . '">';

		// Only show the Normal | Large links if we can actually enlarge the object and the 'fixed size' parameter isn't set.
		if (!$use_object && !$fixed_size && $sw != $altw)
			$object .= '<a href="javascript:aevatq(0, ' . $swfobjects . ', ' . min($sw, $altw) . ', ' . min($sh, $alth) . ');" ' . ($sw < $altw ? 'class="aeva_dq" ' : '') . 'title="-">' . $txt['media_small'] . '</a>'
				  . ' | <a href="javascript:aevatq(1, ' . $swfobjects . ', ' . max($sw, $altw) . ', ' . max($sh, $alth) . ');" ' . ($sw > $altw ? 'class="aeva_dq" ' : '') . 'title="+">' . $txt['media_large'] . '</a>';

		$object .= '</td>';
	}

	// Close our table from earlier.
	if (!empty($plugin))
		$object .= '</tr></table>';
	else
		$object = str_replace(array('{width}', '{height}', '{int:width}', '{int:height}'), array($swp, $shp, $sw, $sh), $object);

	// Replace the $1, $2, $3 etc
	for ($i = 1, $j = count($input); $i < $j; $i++)
	{
		if (strpos($object, '(') || strpos($embed, '('))
		{
			// (&option=$1|&nothing) will say &option=test if $1 is 'test', and &nothing if $1 is empty
			$object = preg_replace('~\(([^$()|]*)\$' . $i . '([^$()|]*)\|([^)]*)\)~', empty($input[$i]) ? '$3' : '${1}' . $input[$i] . '$2', $object);
			$embed  = preg_replace('~\(([^$()|]*)\$' . $i . '([^$()|]*)\|([^)]*)\)~', empty($input[$i]) ? '$3' : '${1}' . str_replace('&amp;', '&', $input[$i]) . '$2', $embed);
		}
		$object = str_replace('$' . $i, $input[$i], $object);
		$embed = str_replace('$' . $i, str_replace('&amp;', '&', $input[$i]), $embed);
	}

	// All ampersands need to be &amp;, however some may be already encoded. So to prevent double-encoding, convert them back to &, then back again
	$object = str_replace(
		array('&amp;', '&', '&amp;nbsp;', '&amp;amp;', '<aeva-link>', '<aeva-title>'),
		array('&', '&amp;', '&nbsp;', '&amp;', $input[1], isset($title) ? $title : $input[1]),
		$object
	);
	add_js(
		str_replace('<aeva-embed>', $embed, $extra_js)
	);
	if (strpos($object, '<flashvars>') !== false)
		$object = str_replace('<flashvars>', substr(strrchr($embed, '?'), 1), $object);

	// Reduce remaining replacements allowed
	if ($context['aeva']['remaining'] != -1)
	{
		$context['aeva']['remaining'] -= 1;
		$context['aeva']['remaining_per_page'] -= 1;
	}

	return $object;
}

// Limits change, so this keeps track between nolimits vs limits, limits per page vs post
function aeva_limits()
{
	global $modSettings, $context;

	// Per post limit is always reset everytime this function is called
	$context['aeva']['remaining_per_post'] = empty($modSettings['embed_max_per_post']) ? -1 : $modSettings['embed_max_per_post'];

	// First time - work out limit per page
	if (!isset($context['aeva']['remaining_per_page']))
		$context['aeva']['remaining_per_page'] = empty($modSettings['embed_max_per_page']) ? -1 : $modSettings['embed_max_per_page'];

	// First time - work out the max for this object between the two different ones
	// If both are unlimited (-1), then remaining = unlimited as well, otherwise temp value
	if (!isset($context['aeva']['remaining']))
		$context['aeva']['remaining'] = $context['aeva']['remaining_per_page'] == -1 && $context['aeva']['remaining_per_post'] == -1 ? -1 : 99;

	// If it's limited, we need to work out the limit to use
	if ($context['aeva']['remaining'] != -1)
	{
		// Post is unlimited, so use the page limit
		if ($context['aeva']['remaining_per_post'] == -1)
			$context['aeva']['remaining'] = $context['aeva']['remaining_per_page'];
		// Page is unlimited, so use the post limit
		elseif ($context['aeva']['remaining_per_page'] == -1)
			$context['aeva']['remaining'] = $context['aeva']['remaining_per_post'];
		else
			$context['aeva']['remaining'] = min($context['aeva']['remaining_per_post'], $context['aeva']['remaining_per_page']);
	}
}

// Links urls that haven't already been linked
function aeva_autolink_urls($input)
{
	global $context, $modSettings;

	// Should haven't got here if autolinking of URLs is disabled
	if (empty($modSettings['autoLinkUrls']))
		return $input;

	// Parse any URLs....
	if (preg_match('~http://|www\.~i', $input))
	{
		$input = strtr($input, array('&#039;' => '\'', '&quot;' => '>">', '"' => '<"<', '&lt;' => '<lt<'));
		$input = preg_replace(
			array(
				'`(^|[]\s>.(;\'"])((?:http|https|ftp|ftps)://[\w%@:|-]+(?:\.[\w%-]+)*(?::\d+)?(?:/[\w~%.@,?&;=#+:\'\\\\-]*|[({][\w~%.@,?&;=#(){}+:\'\\\\-]*)*[/\w~%@?;=#}\\\\-]?)`',
				'`(^|[]\s>.(;\'"])(www(?:\.[\w-]+)+(?::\d+)?(?:/[\w~%.@,?&;=#+:\'\\\\-]*|[({][\w~%.@,?&;=#(){}+:\'\\\\-]*)*[/\w~%@?;=#}\\\\-])`i'
			), array(
				'$1[url]$2[/url]',
				'$1[url=http://$2]$2[/url]'
			), $input
		);
		$input = strtr($input, array('>">' => '&quot;', '<"<' => '"', '<lt<' => '&lt;'));
	}

	// Return it
	return $input;
}

// Protects [noae] bbcoded items - recursive. Used instead of the ACTUAL tag to prevent infinite loop.
// The tags are lost on each recursion.
// Known issue: anchor settings remain visible when video isn't embedded. No known "good" solution?
function aeva_protect_recursive($input, $autolink = false)
{
	global $context;

	// Prevent error if null or empty
	if (empty($input))
		return false;

	// Matches found
	if (is_array($input))
	{
		// Auto-Link items that might have been protected before (we want html version - so false)
		if ($autolink)
			$input[1] = aeva_autolink_urls($input[1]);

		// Changing http:// to noae:// should prevent ALL of the links from matching a site.
		$input = str_replace('http://', 'noae://', $input[1]);
	}

	// The goddess of all regexps - works for complex nested bbcode.
	return preg_replace_callback(
		'~\[noae]((?>[^[]|\[(?!/?noae])|(?R))+?)\[/noae]~',
		'aeva_protect_recursive' . ($autolink ? '_autolink' : ''),
		$input
	);
}

function aeva_protect_recursive_autolink($input)
{
	return aeva_protect_recursive($input, true);
}

// The 'Lookup' function to grab a page and match a regex
function aeva_lookup_and_match($regex, $url, $fetch_title = '')
{
	// Don't timeout.
	@set_time_limit(600);

	// Hmm we might need more...
	@ini_set('memory_limit', '64M');

	// Go get 'em tiger
	$data = @aeva_fetch($url);

	// Return if empty or too short to be a proper response/page
	if (empty($data) || strlen($data) < 100)
		return false;

	$ret = array();
	if (is_array($fetch_title))
	{
		foreach ($fetch_title as $search_title)
		{
			if (!preg_match('~' . $search_title . '~is', $data, $output) || empty($output[1]) || trim($output[1]) === '')
				continue;
			$ret['title'] = preg_replace('~\s+~', ' ', trim(strip_tags($output[1])));
			break;
		}
	}

	// Find the actual data inside the page. Returns false on failure.
	if (!is_array($regex))
		return array('inline' => !empty($regex) && preg_match('`' . $regex . '`im', $data, $output) ? $output[1] : false, 'title' => !empty($ret['title']) ? $ret['title'] : '');

	foreach ($regex as $a => $b)
		if (preg_match('`' . $b . '`i', $data, $output))
			$ret[$a] = isset($output[1]) ? $output[1] : '';

	return $ret;
}

// Callback, only build the embed on each match
function embed_lookups_obtain_callback($input)
{
	global $context, $sites, $upto, $modSettings;

	$arr =& $sites[$upto];

	// On callback this is an array
	if (is_array($input))
	{
		// Secondary url - we use the variable in another url.
		$url = !empty($arr['lookup-actual-url']) ? str_replace('$1', $input[3], $arr['lookup-actual-url']) : $input[2];

		// Search for a title if: link has no existing title, current video site has a title lookup, or all video sites
		// are set to look for a title, and title storage is enabled (aeva_titles is not 1 or 3). I know, it's a bit scary.
		$has_title = preg_match('~(?:<a [^>]+>|\[url=[^]]+])(.+)(?:</a>|\[/url])~', $input[0], $tst);
		$lookup_title = (empty($arr['lookup-title']) && (empty($modSettings['embed_lookup_titles']) || isset($arr['lookup-title']))) || !empty($has_title)
					|| (!empty($modSettings['embed_titles']) && $modSettings['embed_titles'] != 2) ? false : array('<meta\s+name="title"\s+content="([^"]+)"', '<title>([^<]+)</title>');
		if (is_array($lookup_title) && !empty($arr['lookup-title']) && $arr['lookup-title'] !== true)
			array_unshift($lookup_title, $arr['lookup-title']);

		$actual = empty($arr['lookup-pattern']) && empty($lookup_title) ? false : aeva_lookup_and_match(!empty($arr['lookup-pattern']) ? $arr['lookup-pattern'] : '', $url, $lookup_title);

		if (is_array($actual))
		{
			if (isset($actual['title']))
			{
				$title = $actual['title'];
				unset($actual['title']);
				if (isset($actual['inline']))
					$actual = empty($actual['inline']) ? $url : $actual['inline'];
			}
			if (is_array($actual) && isset($actual['error']))
			{
				$title = '[b][color=red]![/color][/b] ' . $actual['error'] . (isset($title) ? ' - ' . $title : '');
				unset($actual['error']);
			}
			if (isset($actual['w'], $arr['size'][0]) && $actual['w'] == $arr['size'][0])
				unset($actual['w']);
			if (isset($actual['h'], $arr['size'][1]) && $actual['h'] == $arr['size'][1])
				unset($actual['h']);
			if (is_array($actual) && (empty($arr['lookup-skip-empty']) || !empty($actual)))
			{
				if (!empty($arr['lookup-final-url']) && !empty($actual['id']))
				{
					$url = $arr['lookup-final-url'];
					$final_id = $actual['id'];
					unset($actual['id']);
				}
				$opt = '#';
				foreach ($actual as $a => $b)
					$opt .= $a . str_replace('-', '~', $b) . '-';
				$actual = $url . substr($opt, 0, max(1, strlen($opt)-1));
			}
		}

		// Failed so return
		if (empty($actual))
			return $input[0];

		// Url-decode some characters
		if (!empty($arr['lookup-urldecode']))
			for ($j = 0; $j < $arr['lookup-urldecode']; $j++)
				$actual = urldecode($actual);

		// Unencode some html characters to prevent double encoding by Wedge
		if (!empty($arr['lookup-unencode']))
			$actual = un_htmlspecialchars($actual);

		// If we were only returned a partial url, we add the remainder/actual bit here
		if (!empty($arr['lookup-final-url']))
		{
			// Normally, all websites should use $input[2] if the ID wasn't found.
			// I'm afraid I don't remember why I did something else, so I'm only using $input[2] for YouTube now.
			$actual = empty($final_id) ? ($arr['id'] == 'ytb' ? $input[2] . '#' : str_replace('$1', $actual, $arr['lookup-final-url'])) : str_replace('$1', $final_id, $actual);
			// Cheat to put the original url looked up back in the link
			$actual = str_replace('$2', $url, $actual);
		}
		if (!empty($title))
			$title = html_entity_decode(str_replace('&amp;', '&', westr::force_utf8($title)), ENT_QUOTES, 'UTF-8');
		return !empty($title) ? '[' . $input[1] . '=' . $actual . ']' . $title . '[/' . $input[1] . ']' : str_replace($input[2], $actual, $input[0]);
	}
	else
	{
		// Complete the regex pattern
		$regex = '`\[(i?url)[]=](' . (!empty($arr['lookup-url']) ? $arr['lookup-url'] : $arr['pattern']) . ')(?:[^]#[]*?][^[]*\[/\1\]|[^#[]*?\[/\1\])`i';
		return preg_replace_callback($regex, 'embed_lookups_obtain_callback', $input);
	}
}

// Goes through each site definition, calling the lookup to match any urls on posting.
function embed_lookups_match($input)
{
	global $context, $sites, $upto, $modSettings;

	// Create an array for our vars
	if (empty($context['aeva']))
		$context['aeva'] = array();

	// Count sites
	$j = count($sites);

	// For each enabled site to the end, attempt to embed
	for ($upto = 0; $upto < $j; $upto++)
		if (!empty($sites[$upto]['lookup-url']) || ((!empty($sites[$upto]['lookup-title']) || !empty($modSettings['embed_lookup_titles'])) && (empty($modSettings['embed_titles']) || $modSettings['embed_titles'] == 2)))
			$input = embed_lookups_obtain_callback($input);

	// Undo All protection
	$input = str_replace('noae://', 'http://', $input);

	// All sites are done.
	return $input;
}

// This function is called on Posting to protect bbcode and then obtain any video/audio clips necessary
// Called on both quick reply and full posting
function aeva_onposting($input)
{
	global $modSettings, $context, $sites, $sourcedir;

	// Exit if all three are disabled:
	// - Lookups (retrieve final URL, check whether embeds are allowed, etc.)
	// - Fix embed HTML (when n00bs try to post the full embed code rather than just the URL)
	if (empty($modSettings['embed_fix_html']) && empty($modSettings['embed_lookups']))
		return $input;

	$array = array(
		// bbc => retain
		'code' => true,
		'html' => true,
		'php' => true,
		'noembed' => true,
	);

	// Protect quotes from embedding
	// False doesn't meant retain or replace, but whether we're fully protecting it.
	if (empty($modSettings['embed_quote']))
		$array['quote'] = true;

	// Protect all these items
	$input = aeva_protection($array, $input, false);

	// Attempt to Load - Enabled Sites
	if (empty($sites) && file_exists($sourcedir . '/media/Aeva-Sites.php'))
		loadSource('media/Aeva-Sites');

	// If we can't use generated version (either just after install, OR permissions meant generated
	// version couldn't be created, OR it can't be found), load the full un-optimized version
	if (empty($sites))
		loadSource(
			file_exists($sourcedir . '/media/Aeva-Sites-Custom.php') ? array('media/Subs-Aeva-Sites', 'media/Aeva-Sites-Custom') : 'media/Subs-Aeva-Sites'
		);

	// Noob users might have included the full embed code provided by the site
	if (!empty($modSettings['embed_fix_html']))
		$input = aeva_fix_html($input);

	// Will [url] BBCode links which aren't currently URL-BBCoded (so they get can get looked up/embedded by Aeva)
	if (!empty($modSettings['autoLinkUrls']))
		$input = aeva_autolink_urls($input);

	// Do Lookups
	if (!empty($modSettings['embed_lookups']))
		$input = embed_lookups_match($input);

	// Undo all protection and return
	return str_replace('noae://', 'http://', $input);
}

// Noobs might use the full embed code provided by the site, so try to fix it on posting (replace with an embeddable link and save some db space as well)
function aeva_fix_html($input)
{
	global $sites, $context;

	// If no sites, return
	if (empty($sites))
		return $input;

	// No html objects potentially to replace. Different sites use different objects, some <embed> <object> <script> or <iframe>
	if (!preg_match('~<(?:embed|object|script|iframe)|\?width=~i', $input))
		return $input;

	$stripped = false;
	while (!$stripped)
	{
		// For each enabled site attempt to fix the html
		foreach ($sites as $site)
		{
			// If this site doesn't have an pattern to fix, continue
			if (empty($site['fix-html-pattern']) || empty($site['pattern']))
				continue;

			// Re-use the embed pattern
			$regex = str_replace('$1', '(' . $site['pattern'] . ')[^">]*?', $site['fix-html-pattern']);
			// Complete the pattern with delimiter and utf8 support. If starting with a <, make sure to escape it
			$regex = '`' . ($regex[0] == '<' ? '\\' : '') . $regex . '`isu';

			// Match, and replace with a valid link
			$input = preg_replace($regex, empty($site['fix-html-url']) ? "$1\r\n" : $site['fix-html-url'], $input);

			if (!empty($site['fix-html-urldecode']))
				$input = urldecode($input);

			// No html objects potentially to replace. Then break.
			if (!preg_match('~<(?:embed|object|script|iframe)|\?width=~i', $input))
			{
				$stripped = true;
				break;
			}
		}

		// If nothing was changed, maybe backslashes are ruining it? Try again with them stripped. If it still won't work, give up.
		if (!$stripped)
			if (strpos($input, '\\"') !== false)
				$input = stripslashes($input);
			else
				$stripped = true;
	}

	// Tidy up
	unset($site, $regex);

	// All sites are done.
	return $input;
}

/*
	Do not attempt to auto-embed if Aeva is disabled, or for
	- Printer-friendly pages ($smileys === 'print')
	- Messages that don't contain links
	- Signatures/Wysiwyg window (or anywhere where $context['embed_disable'] is set)
	- SSI functions such as ssi_recentTopics() (they tend to crash your browser)
*/
function aeva_parse_bbc2(&$message, &$smileys, &$cache_id)
{
	global $context, $modSettings, $txt;

	if (empty($context['uninstalling']))
	{
		if (!empty($modSettings['embed_enabled']) && empty($context['embed_disable']) && strpos($message, 'http://') !== false && $smileys !== 'print' && strpos($cache_id, 'sig') === false)
			$message = aeva_main($message);
		else
		{
			// Removes any noembed
			$message = aeva_protection(array('noembed' => false), $message, false);

			// And reverses any protection already in place
			$message = aeva_reverse_protection($message);
		}
		// Reset any technical reasons to stop
		unset($context['embed_disable']);
		if (isset($context['aeva']['skip']))
			unset($context['aeva']['skip']);
	}

	// Reset any technical reasons to stop
	unset($context['embed_disable']);
	if (isset($context['aeva']['skip']))
		unset($context['aeva']['skip']);

	if (!empty($context['embed_cache_enable']))
		$modSettings['cache_enable'] = $context['embed_cache_enable'];
}

// This annoying little function is needed when the frigging server really doesn't want to cooperate...
// Inspired (and hopefully improved) from http://www.php.net/manual/en/function.curl-setopt.php#71313
function aeva_curl_redir_exec($ch)
{
	static $curl_loops = 0;
	if ($curl_loops++ >= 20)
	{
		$curl_loops = 0;
		return false;
	}
	curl_setopt($ch, CURLOPT_HEADER, true);
	$data = curl_exec($ch);
	list ($header, $data) = preg_split("/(\n\n|\r\r|\r\n\r\n)/", $data, 2);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($http_code == 301 || $http_code == 302)
	{
		$matches = array();
		preg_match('/Location:(.*?)[\r\n]/', $header, $matches);
		if ($url = @parse_url(trim(array_pop($matches))))
		{
			$last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
			$url['scheme'] = $url['scheme'] ? $url['scheme'] : $last_url['scheme'];
			$url['host'] = $url['host'] ? $url['host'] : $last_url['host'];
			$url['path'] = $url['path'] ? $url['path'] : $last_url['path'];
			$new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . (isset($url['query']) ? '?' . $url['query'] : '');
			curl_setopt($ch, CURLOPT_URL, $new_url);
			return aeva_curl_redir_exec($ch);
		}
	}
	$curl_loops = 0;
	return $data;
}

function aeva_file_get_contents($url)
{
	if (!function_exists('curl_init') || !$c = curl_init())
		return false;
	curl_setopt($c, CURLOPT_URL, $url);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($c, CURLOPT_TIMEOUT, 3);
	$contents = curl_exec($c);
	if (!$contents)
		$contents = aeva_curl_redir_exec($c);
	curl_close($c);
	return $contents ? $contents : false;
}

function aeva_download_to_string($url, &$file)
{
	$ctx = stream_context_create(array('http' => array('timeout' => 3)));
	$file = @file_get_contents($url, 0, $ctx);
	if (!$file)
		$file = @implode('', @file($url, 0, $ctx));
	if (!$file)
		$file = aeva_file_get_contents($url);
}

function aeva_download_to_local($url, $local)
{
	$file = '';
	aeva_download_to_string($url, $file);
	$len = strlen($file);
	$my_file = @fopen($local, 'w');
	@fwrite($my_file, $file, $len);
	@fclose($my_file);
	unset($file);
	return $len;
}

// Get the contents of a URL, irrespective of allow_url_fopen.
function aeva_fetch($url, $test_connection = false)
{
	preg_match('~^(http)(s)?://([^/:]+)(:(\d))?(.+)$~', $url, $match);

	if (empty($match[1]))
		return false;

	$data = '';
	@ini_set('user_agent', 'Mozilla/4.0 (Compatible; PHP/Wedge; http://wedge.org)');
	aeva_download_to_string($url, $data);
	if (!empty($data))
		return $data;

	// Open the socket on the port we want...
	$fp = @fsockopen(($match[2] ? 'ssl://' : '') . $match[3], empty($match[5]) ? ($match[2] ? 443 : 80) : $match[5], $err, $err, 5);
	if (!$fp)
		return false;

	// I want this, from there, and I'm not going to be bothering you for more (probably.)
	fwrite($fp, 'GET ' . $match[6] . ' HTTP/1.0' . "\r\n");
	fwrite($fp, 'Host: ' . $match[3] . (empty($match[5]) ? ($match[2] ? '443' : '') : ':' . $match[5]) . "\r\n");
	fwrite($fp, 'User-Agent: Mozilla/4.0 (Compatible; PHP/Wedge; http://wedge.org)' . "\r\n");
	fwrite($fp, 'Connection: close' . "\r\n\r\n");

	// Make sure we get a response.
	$response = fgets($fp, 768);
	if ((preg_match('~^HTTP/.+\s+[23][0-9][0-9]\s~i', $response, $reply) != 1) || $test_connection)
	{
		fclose($fp);
		return $test_connection ? $reply[0] : false;
	}

	// Skip the headers...
	while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
		if (preg_match('~content-length:\s*(\d+)~i', $header, $match) != 0)
			$content_length = $match[1];

	if (isset($content_length))
	{
		while (!feof($fp) && strlen($data) < $content_length)
			$data .= fread($fp, $content_length - strlen($data));
	}
	else
		while (!feof($fp))
			$data .= fread($fp, 4096);

	fclose($fp);

	return $data;
}

/***************************************
* Interface between AE and the gallery *
***************************************/

function aeva_embed_video($message, $id_media = 0, $id_preview = 0)
{
	global $context, $sites, $modSettings, $scripturl;

	$msg = '<a href="' . preg_replace(array('~\[url=([^]]*)][^[]*\[/url]~', '~\[url]([^[]*)\[/url]~'), '$1', $message) . '"></a>';

	$context['embed_mg_hack'] = true;
	$msg = parse_bbc(substr($message, 0, 4) == 'http' ? $msg : $message);
	unset($context['embed_mg_hack']);

	if (substr($msg, 0, 7) !== '<a href')
		return $msg;
	$where = $id_media && $id_preview ? $scripturl . '?action=media;sa=media;in=' . (int) $id_media . ';preview' : '$2';
	return preg_replace('~(<a href="([^"]+)".*?)>.*?(</a>)~', '$1 class="zoom is_embed"><img src="' . $where . '">$3', $msg);
}

function aeva_check_embed_link($link)
{
	global $context, $sites, $boardurl, $modSettings, $sourcedir;

	if (empty($modSettings['embed_enabled']))
		return false;

	$link2 = aeva_onposting($link);
	$link3 = aeva_main(preg_replace(array('~\[url=([^]]*)]([^[]*)\[/url]~', '~\[url]([^[]*)\[/url]~'), array('<a href="$1">$2</a>', '<a href="$1">$1</a>'), $link2));
	if (strpos($link3, '<embed ') !== false || strpos($link3, 'swfobject.embedSWF(') !== false)
		return $link2;

	// Parse the boardurl to grab the domain we're on.
	$x = @parse_url($boardurl);
	$x['scheme'] = !empty($x['scheme']) ? $x['scheme'] : 'http';
	$x['host'] = !empty($x['host']) ? $x['host'] : null;

	if (!empty($x['host']))
		if (preg_match('`' . preg_quote($x['scheme'], '`') . '://(?:[a-z0-9-]{1,32}\.){0,3}' . preg_quote($x['host'], '`') . '/`i', $link, $void))
			return true;
	unset($x);

	if (empty($sites) && file_exists($sourcedir . '/media/Aeva-Sites.php'))
		loadSource('media/Aeva-Sites');

	if (empty($sites))
		loadSource(
			file_exists($sourcedir . '/media/Aeva-Sites-Custom.php') ? array('media/Subs-Aeva-Sites', 'media/Aeva-Sites-Custom') : 'media/Subs-Aeva-Sites'
		);

	$link = preg_replace(array('~\[url=([^]]*)][^[]*\[/url]~', '~\[url]([^[]*)\[/url]~'), '$1', $link);
	foreach ($sites as $arr)
		if (preg_match('`^' . (isset($arr['pattern']) ? $arr['pattern'] : $arr['embed-pattern']) . '`iu', $link))
			return true;

	if (function_exists('aeva_foxy_remote_image'))
		return aeva_foxy_remote_image($link);

	return false;
}

// Generates thumbnail for site if possible
function aeva_generate_embed_thumb($link, $id_album, $id_file = 0, $folder = '')
{
	global $embed_album, $embed_folder, $context, $force_id;

	$link = preg_replace(array('~\[url=([^]]*)]([^[]*)\[/url]~', '~\[url]([^[]*)\[/url]~'), array('$1', '$1'), $link);
	$thumbs = array(
		'YouTube' => array(
			'func' => 'youtubeCreateThumb',
			'pattern' => 'http://(?:video\.google\.(?:com|com?\.[a-z]{2}|[a-z]{2})/[^"]*?)?(?:(?:www|[a-z]{2})\.)?youtu(?:be\.com/[^"#[]*?(?:[&/?;]|&amp;|%[23]F)(?:video_id=|v(?:/|=|%3D|%2F))|\.be)([\w-]{11})',
		),
		'Dailymotion' => array(
			'func' => 'dailymotionCreateThumb',
			'pattern' => 'http://(?:www\.)?dailymotion\.(?:com|alice\.it)/(?:[^"]*?video|swf)/([a-z0-9]{1,18})',
		),
		'GoogleVideo' => array(
			'func' => 'googleCreateThumb',
			'pattern' => 'http://video\.google\.(com|com?\.[a-z]{2}|[a-z]{2})/(?:videoplay|url|googleplayer\.swf)\?[^"]*?docid=([\w-]{1,20})',
		),
		'GoogleMaps' => array(
			'func' => 'gmapsCreateThumb',
			'pattern' => 'http://maps\.google\.[^">]+/\w*?\?[^">]+',
		),
		'MetaCafe' => array(
			'func' => 'metacafeCreateThumb',
			'pattern' => 'http://(?:www\.)?metacafe\.com/(?:watch|fplayer)/([\w-]{1,20})/',
		),
		'Vimeo' => array(
			'func' => 'vimeoCreateThumb',
			'pattern' => 'http://(?:www\.)?vimeo\.com/(\d{1,12})',
		),
	);

	foreach ($thumbs as $ids => $arr)
		if (preg_match('`^' . $arr['pattern'] . '.*?$`iu', $link))
			$id = $ids;

	$embed_folder = $folder;
	$embed_album = $id_album;
	$force_id = $id_file;

	// Create the thumbnail
	return empty($id) ? 0 : $thumbs[$id]['func']($link, '`' . $thumbs[$id]['pattern'] . '`i');
}

// Retrieves Youtube thumbnail. Geek pawaa!
function youtubeCreateThumb($link, $regexp)
{
	return preg_match($regexp, $link, $dt) ? aeva_download_thumb('http://img.youtube.com/vi/' . $dt[1] . '/default.jpg', $dt[1]) : 0;
}

function dailymotionCreateThumb($link, $regexp)
{
	return preg_match($regexp, $link, $dt) ? aeva_download_thumb('http://www.dailymotion.com/thumbnail/320x240/video/' . $dt[1], $dt[1]) : 0;
}

function googleCreateThumb($link, $regexp)
{
	return preg_match($regexp, $link, $dt) ? aeva_download_thumb_via('http://video.google.com/videofeed?docid=' . $dt[2], $dt[2], '<media:thumbnail url="([^"]+)') : 0;
}

function gmapsCreateThumb($link, $regexp)
{
	global $amSettings, $embed_folder, $embed_album;

	aeva_download_to_string(str_replace('&amp;', '&', $link), $page);
	preg_match('`center:({lat:\d+\.\d+,lng:\d+\.\d+}|\[\d+\.\d+,\d+\.\d+\])`i', $page, $center);
	if (!isset($center, $center[1]))
		return 0;
	$cen = str_replace(array('lat:', 'lng:'), '', substr($center[1], 1, -1));

	$url = 'http://maps.google.com/maps/api/staticmap?zoom=10&maptype=roadmap&sensor=false&format=png32&center=' . $cen;
	$has_gd = function_exists('imagecopy');
	$w = $amSettings['max_thumb_width'];
	$h = $amSettings['max_thumb_height'];
	$url .= '&size=' . $w . 'x' . ($h + ($has_gd ? 60 : 0));
	if ($has_gd)
	{
		$src = @imagecreatefrompng($url);
		$dst = imagecreatetruecolor($w, $h);
		imagecopy($dst, $src, 0, 0, 0, 30, $w, $h);
		imagepng($dst, $amSettings['data_dir_path'] . '/tmp/thumb_' . $cen . '.png');
		return aeva_download_thumb(true, $cen, false, 'png');
	}
	return preg_match($regexp, $link, $dt) ? aeva_download_thumb($url, $cen) : 0;
}

function metacafeCreateThumb($link, $regexp)
{
	return preg_match($regexp, $link, $dt) ? aeva_download_thumb('http://www.metacafe.com/thumb/' . $dt[1] . '.jpg', $dt[1]) : 0;
}

function vimeoCreateThumb($link, $regexp)
{
	return preg_match($regexp, $link, $dt) ? aeva_download_thumb_via('http://vimeo.com/api/clip/' . $dt[1] .'.xml', $dt[1], '<thumbnail_medium>([^<]+)') : 0;
}

function aeva_url_exists($url)
{
	$a_url = parse_url($url);
	if (!isset($a_url['scheme']))
		return false;
	$temp = '';
	$fid = fsockopen($a_url['host'], !isset($a_url['port']) ? 80 : $a_url['port'], $temp, $temp, 8);
	if (!$fid)
		return false;
	fputs($fid, 'HEAD ' . $a_url['path'] . (isset($a_url['query']) ? '?' . $a_url['query'] : '') . ' HTTP/1.0' . "\r\n" . 'Host: ' . $a_url['host'] . "\r\n\r\n");
	$head = fread($fid, 1024);
	fclose($fid);
	return preg_match('~^HTTP/.+\s+[23][0-9][0-9]~i', $head) == 1;
}

function aeva_download_thumb_via($via_url, $name, $regexp)
{
	$via_page = '';
	if (aeva_url_exists($via_url))
		aeva_download_to_string($via_url, $via_page);
	return preg_match('`' . $regexp . '`i', $via_page, $thumb_url) ? aeva_download_thumb(str_replace('&amp;', '&', $thumb_url[1]), $name) : 0;
}

function aeva_download_thumb($url_thumb, $name, $stack = false, $ext = 'jpg')
{
	global $amSettings, $embed_album, $embed_folder, $force_id;

	$dir = !empty($embed_folder) ? $embed_folder : aeva_getSuitableDir($embed_album);
	$local_file = $amSettings['data_dir_path'] . '/tmp/thumb_' . $name . '.' . $ext;
	$file_done = $url_thumb === true ? true : (aeva_url_exists($url_thumb) ? aeva_download_to_local($url_thumb, $local_file) : false);
	if (!$file_done)
		return 0;
	$my_file = new media_handler;
	$my_file->init($local_file);
	list ($width, $height) = $my_file->getSize();
	if ($width == 0 || $height == 0)
	{
		$my_file->close();
		@unlink($local_file);
		return 0;
	}

	aeva_deleteFiles($force_id, true);

	$id_preview = $stack && function_exists('aeva_foxy_remote_preview') && ($amSettings['max_preview_width'] < $width || $amSettings['max_preview_height'] < $height)
					? aeva_foxy_remote_preview($my_file, $local_file, $dir, $name, $width, $height) : 0;

	if ($resizedpic = $my_file->createThumbnail($local_file . '2.jpg', min($width, $amSettings['max_thumb_width']), min($height, $amSettings['max_thumb_height'])))
	{
		list ($twidth, $theight) = $resizedpic->getSize();
		$fsize = $resizedpic->getFileSize();
		$resizedpic->close();
		$twidth = empty($twidth) ? $amSettings['max_thumb_width'] : $twidth;
		$theight = empty($theight) ? $amSettings['max_thumb_height'] : $theight;

		$id_thumb = aeva_insertFileID(
			$force_id, $fsize, 'thumb_' . $name . '.jpg', $twidth, $theight,
			substr($dir, strlen($amSettings['data_dir_path']) + 1), $embed_album
		);
		@rename($local_file . '2.jpg', $dir . '/' . aeva_getEncryptedFilename('thumb_' . $name . '.jpg', $id_thumb, true));
	}
	else
		$id_thumb = 3;

	$my_file->close();

	@unlink($local_file);
	return $stack ? array(true, $id_thumb, $id_preview) : $id_thumb;
}

?>