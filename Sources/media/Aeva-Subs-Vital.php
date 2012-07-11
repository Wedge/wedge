<?php
/**
 * Wedge
 *
 * Database functions for the gallery, etc.
 * Uses portions written by Shitiz Garg.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

/*
	Contains vital media functions, previously half of Subs-Media.php....

	array aeva_allowed_types(bool flat = false, bool see_all = false)
		- Returns the allowed item extensions for the user
		- If flat is true, skips adding "BRs" to the array list

	int aeva_get_num_files(string path)
		- Gets the number of files inside the specified path

	bool aeva_allowedTo(mixed permissions, bool single_true)
		- Uses allowedTo to determine whether the user can perform a specific action against a permission name or not
		- If permissions is an array, performs test on all permissions
		- If single_true is false, makes sures that all the returned tests are true otherwise it returns false. If true, it requires only one true to pass.

	array aeva_get_dir_map(string path)
		- Returns the whole map of the directory
		- Returns all the folders, sub-folders (and so on), as well as their files
		- 0 contains the main array, 1 contains the list

	array aeva_get_dir_list_subfolders()
		- You really wanna know?

	string aeva_getEncryptedFilename(string name, int id)
		- Gets an encrypted filename for a specified name

	int aeva_getPHPSize(string size)
		- Returns a php.ini size limit, in bytes

	string aeva_getTags(array taglist)
		- Returns a formatted tag list

	array aeva_splitTags(string string, string separation)
		- Splits keywords from a comma-separated list

	string aeva_string(string source, bool is_filename, int limit, bool ellipsis, bool check_multibyte, bool cut_long_words)
		- Applies various freebies to strings. Has plenty of parameters because I suck at doing this clearly. But at least it works. Usually.

	string aeva_entities2utf(array mixed)
		// !!!

	string aeva_utf8_chr(string code)
		// !!!
*/

loadSource('media/Class-Media');

function aeva_allowed_types($flat = false, $see_all = false)
{
	$ext = aeva_extList();
	$allowed_types = array(
		'im' => array_keys($ext['image']),
		'au' => array_keys($ext['audio']),
		'vi' => array_keys($ext['video']),
		'do' => array_keys($ext['doc']),
		'zi' => array('zipm')
	);

	if (!$see_all)
	{
		if (!aeva_allowedTo('add_images'))
			unset($allowed_types['im']);
		if (!aeva_allowedTo('add_audios'))
			unset($allowed_types['au']);
		if (!aeva_allowedTo('add_videos'))
			unset($allowed_types['vi']);
		if (!aeva_allowedTo('add_docs'))
			unset($allowed_types['do']);
		if (!aeva_allowedTo(array('add_images', 'add_audios', 'add_videos', 'add_docs'), true))
			unset($allowed_types['zi']);
	}

	if (!$flat)
		return $allowed_types;

	$allowed_types_flat = array();
	foreach ($allowed_types as $all)
		foreach ($all as $v)
			$allowed_types_flat[] = $v;

	return $allowed_types_flat;
}

function aeva_allowedTo($perms, $single_true = false)
{
	global $context;

	if (empty($perms))
		return false;
	if (allowedTo('media_manage'))
		return true;
	if (!is_array($perms))
		return !in_array($perms, $context['aeva_album_permissions']) ? allowedTo('media_' . $perms) : isset($context['aeva_album']) && in_array($perms, $context['aeva_album']['permissions']);

	$tests = array();
	foreach ($perms as $perm)
		$tests[] = !in_array($perm, $context['aeva_album_permissions']) ? allowedTo('media_' . $perm) : isset($context['aeva_album']) && in_array($perm, $context['aeva_album']['permissions']);

	return $single_true ? in_array(true, $tests) : !in_array(false, $tests);
}

function aeva_get_num_files($path)
{
	// Counts number of items in a directory
	if (!is_readable($path))
		return false;
	if (!is_dir($path))
		return false;

	$files = scandir($path);

	return count($files) - (in_array('.', $files) ? 1 : 0) - (in_array('..', $files) ? 1 : 0);
}

function aeva_get_dir_map($path)
{
	if (!is_readable($path))
		return false;
	if (!is_dir($path))
		return false;

	// Actually map the directory
	$dirname_stack[] = array($path, null, 'root');
	$dirs = array();
	$i = 0;
	do
	{
		list ($dirname, $parent, $foldername) = array_shift($dirname_stack);
		$dirs[$i] = array(
			'dirname' => $dirname,
			'fname' => $foldername,
			'parent' => $parent,
			'files' => array(),
			'folders' => array(),
		);
		$files = scandir($dirname);
		foreach ($files as $file)
		{
			if ($file[0] !== '.' && is_readable($dirname . '/' . $file))
			{
				if (is_dir($dirname . '/' . $file))
				{
					$dirname_stack[] = array($dirname . '/' . $file, $i, $file);
					$dirs[$i]['folders'][] = array($file, $dirname . '/' . $file);
				}
				else
					$dirs[$i]['files'][] = array($file, filesize($dirname . '/' . $file), $dirname . '/' . $file);
			}
		}
		$i++;
	}
	while (count($dirname_stack) > 0);

	// Get the folders' child level
	$child_level_index = array(0 => 0);
	foreach ($dirs as $dir => $data)
	{
		if (isset($child_level_index[$dir['parent']]))
			continue;
		elseif (isset($child_level_index[$data['parent']]))
			$child_level_index[$dir] = $child_level_index[$data['parent']] + 1;
		else
			$child_level_index[$dir] = 1;
	}

	// Assign them
	foreach ($dirs as $dir => $data)
	{
		$dirs[$dir]['child_level'] = isset($child_level_index[$data['parent']]) ? $child_level_index[$data['parent']] : 0;
	}

	// Assign the sub-folders, list their index
	$dirpath_index = array();
	foreach ($dirs as $dir => $data)
	{
		$dirpath_index[$data['dirname']] = $dir;
	}
	foreach ($dirs as $dir => $data)
	{
		foreach ($data['folders'] as $folder => $folderdata)
		{
			$dirs[$dir]['folders'][$folder][2] = $dirpath_index[$folderdata[1]];
		}
	}

	// Get the list
	foreach ($dirs as $dir => $data)
	{
		// If the parent's not empty... They are already included!
		if (!is_null($data['parent']))
			continue;

		$_list[] = $dir;
		aeva_get_dir_list_subfolders($dirs, $data, $_list);
	}

	return array($dirs, $_list);
}

function aeva_get_dir_list_subfolders($dirs, $data, &$_list)
{
	foreach ($data['folders'] as $folder)
	{
		$_list[] = $folder[2];
		if (!empty($dirs[$folder[2]]['folders']))
			aeva_get_dir_list_subfolders($dirs, $dirs[$folder[2]], $_list);
	}
}

// Get the HTML code for a media item
function aeva_embedObject($obj, $id_file, $cur_width = 0, $cur_height = 0, $desc = '', $type = null)
{
	global $galurl, $context, $theme, $amSettings, $settings, $cookiename;
	static $swfobjects = 0;

	if (empty($type))
		$type = $obj->media_type();

	$output = '';
	$pcol = !empty($amSettings['player_color']) ? ($amSettings['player_color'][0] == '#' ? substr($amSettings['player_color'], 1) : $amSettings['player_color']) : '';
	$bcol = !empty($context['aeva_override_bcolor']) ? $context['aeva_override_bcolor'] : (!empty($amSettings['player_bcolor']) ? ($amSettings['player_bcolor'][0] == '#' ? substr($amSettings['player_bcolor'], 1) : $amSettings['player_bcolor']) : '');
	$pwid = !empty($context['aeva_override_player_width']) ? $context['aeva_override_player_width'] : (!empty($amSettings['audio_player_width']) ? min($amSettings['max_preview_width'], max(100, (int) $amSettings['audio_player_width'])) : 400);
	$preview_image = $galurl . 'sa=media;in=' . $id_file . (!empty($context['aeva_has_preview']) || $type == 'image' ? ';preview' : ';thumb');
	$show_audio_preview = $type == 'audio' && !empty($_REQUEST['action']) && $_REQUEST['action'] == 'media';
	$increm = $show_audio_preview && !empty($context['aeva_has_preview']) ? '' : ';v';

	if ($show_audio_preview)
		$output .= '
		<div class="centered" style="width: ' . max($cur_width, $pwid) . 'px">
		<img src="' . $preview_image . '"' . ($cur_width > 0 && $cur_height > 0 ? ' width="' . $cur_width . '" height="' . $cur_height . '"' : '') . ' class="center" style="padding-bottom: 8px">';

	$ext = aeva_getExt($obj->src);
	if ($type == 'image')
	{
		$output .= '
		' . (!empty($context['aeva_has_preview']) ? '<a href="' . $galurl . 'sa=media;in=' . $id_file . '" title="' . westr::htmlspecialchars($desc) . '"' . ($amSettings['use_zoom'] ? ' class="zoom"' : '') . '>' : '')
		. '<img src="' . $preview_image . '" width="' . $cur_width . '" height="' . $cur_height . '">'
		. (!empty($context['aeva_has_preview']) ? '</a>' : '');
	}
	elseif ($type == 'doc')
	{
		$width = empty($cur_width) ? 48 : $cur_width;
		$height = empty($cur_height) ? 52 : $cur_height;
		$output .= '
		<a href="' . $galurl . 'sa=media;in=' . $id_file . ';dl" title="' . westr::htmlspecialchars($desc) . '">'
		. '<img src="' . $preview_image . '" width="' . $width . '" height="' . $height . '"></a>';
	}
	elseif ($type == 'video' || ($type == 'audio' && in_array($ext, array('mp3', 'm4a', 'm4p', 'a-latm'))))
	{
		$mime = $obj->getMimeType($obj->src);

		$qt = false;
		$width = empty($cur_width) ? 500 : $cur_width;
		$height = empty($cur_height) ? 470 : $cur_height;

		switch ($mime)
		{
			case 'audio/mpeg':
			case 'audio/mp4a-latm':
				// Hopefully getid3 should be able to return durations for all file types...
				$duration = $obj->getInfo();
				$width = $pwid;
				$height = 80;

			case 'video/x-flv':
			case 'video/x-m4v':
			case 'video/mp4':
			case 'video/3gpp':

				if ((isset($_GET['action']) && $_GET['action'] == '.xml') || isset($_GET['xml']) || WEDGE == 'SSI')
				{
					$output .= '
		<embed src="' . aeva_theme_url('player.swf') . '" flashvars="file=' . $galurl . 'sa=media;in=' . $id_file . $increm
		. (!empty($pcol) ? '&amp;backcolor=' . $pcol : '') . (!empty($bcol) ? '&amp;screencolor=' . $bcol : '')
		. ($show_audio_preview ? '' : 'amp;image=' . $preview_image) . '&amp;type=' . ($type != 'audio' ? $type : 'sound&amp;plugins=spectrumvisualizer-1&amp;showdigits=true&amp;repeat=always&amp;duration='
		. floor($duration['duration'])) . '" width="' . $width . '" height="' . ($height+20) . '" allowscriptaccess="always" allowfullscreen="true" wmode="transparent">';
				}
				else
				{
					if (!$swfobjects++)
						add_js_file('http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js');

					$output .= '
		<div id="sob'. $swfobjects . '">&nbsp;</div>
		<script><!-- // --><![CDATA[
			var fvars = { file: "' . $galurl . 'sa=media;in=' . $id_file . $increm . '", ' . (!empty($pcol) ? 'backcolor: "' . $pcol . '", ' : '') . (!empty($bcol) ? 'screencolor: "' . $bcol . '", ' : '')
			. ($show_audio_preview ? '' : 'image: "' . $preview_image . '", ') . 'type: "' . ($type != 'audio' ? $type : 'sound", plugins: "spectrumvisualizer-1", showdigits: true, repeat: "always", duration: "' . floor($duration['duration'])) . '" };
			swfobject.embedSWF("' . aeva_theme_url('player.swf') . '", "sob' . $swfobjects . '", "' . $width . '", "' . ($height+20) . '", "9", "", fvars, { allowFullscreen: "true", allowScriptAccess: "always", wmode: "transparent" });
		// ]]></script>';
				}

				return $show_audio_preview ? $output . '
		</div>' : $output;

			case 'video/quicktime':
				if ($context['browser']['is_ie'])
					$output .= '
		<object classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" codebase="http://www.apple.com/qtactivex/qtplugin.cab" width="' . $width . '" height="' . ($height + 15) . '">
			<param name="src" value="' . $galurl . 'sa=media;in=' . $id_file . ';v">
			<param name="wmode" value="transparent">
			<param name="controller" value="true">
			<param name="autoplay" value="false">
			<param name="loop" value="false">';

				$output .='
			<embed src="' . $galurl . 'sa=media;in=' . $id_file . ';v" width="' . $width . '" height="' . ($height + 15) . '" type="' . $mime . '"
				pluginspage="http://www.apple.com/quicktime/download/" controller="true" autoplay="false" loop="false" wmode="transparent">';

				if ($context['browser']['is_ie'])
					$output .='
		</object>';

				return $output;

			case 'video/mpeg':
			case 'video/x-msvideo':
			case 'video/x-ms-wmv':
				$class_id = 'CLSID:05589FA1-C356-11CE-BF01-00AA0055595A';
				// Stupid Windows Media Player seems to ignore cookies, so we'll force it in...
				if (isset($_COOKIE[$cookiename]))
					$upcook = ';upcook=' . urlencode(base64_encode($_COOKIE[$cookiename]));
			break;
		}

		if (!isset($class_id))
			$class_id = 'CLSID:22D6F312-B0F6-11D0-94AB-0080C74C7E95';

		if ($context['browser']['is_ie'])
			$output .= '
		<object classid="' . $class_id . '" width="' . $width . '" height="' . $height . '">
			<param name="wmode" value="transparent">
			<param name="ShowDisplay" value="0">
			<param name="ShowControls" value="1">
			<param name="AutoStart" value="0">
			<param name="AutoRewind" value="-1">
			<param name="Volume" value="0">
			<param name="FileName" value="' . $galurl . 'sa=media;in=' . $id_file . ';v">';

		$output .= '
			<embed src="' . $galurl . 'sa=media;in=' . $id_file . ';v' . (isset($upcook) ? $upcook : '')
			. '" width="' . $width . '" height="' . ($height+42) . '" type="' . $mime . '" controller="true" autoplay="false" autostart="0" loop="false" wmode="transparent">';

		if ($context['browser']['is_ie'])
			$output .= '
		</object>';
	}
	elseif ($type == 'audio')
	{
		// Audio, but no mp3..............

		if ($ext == 'ogg')
			$output .= '
		<audio src="' . $galurl . 'sa=media;in=' . $id_file . ';v" width="' . $pwid . '" height="50" controls="controls">
			<object style="border: 1px solid #999" type="application/ogg" data="' . $galurl . 'sa=media;in=' . $id_file . ';v" width="' . $pwid . '" height="50">
				<param name="wmode" value="transparent">
			</object>
		</audio>';
		else
			$output .= '
		<audio src="' . $galurl . 'sa=media;in=' . $id_file . ';v" width="' . $pwid . '" height="50" controls="controls">
			<embed src="' . $galurl . 'sa=media;in=' . $id_file . ';v' . (isset($_COOKIE[$cookiename]) ? ';upcook=' . urlencode(base64_encode($_COOKIE[$cookiename])) : '')
			. '" width="' . $pwid . '" height="50" autoplay="false" autostart="0" loop="true" wmode="transparent">
		</audio>';

		$output .= '
		</div>';
	}
	return $output;
}

function aeva_initZoom($autosize, $peralbum = array())
{
	global $txt, $context;
	static $done = false;

	if ($done)
		return;
	$done = true;
	add_css_file('zoom', true);
	add_js_file('scripts/zoomedia.js');
	add_js('
	$("a.zoom").zoomedia({
		lang: {
			move: "', $txt['media_zoom_move'], '",
			close: "', $txt['media_close'], '",
			closeTitle: "', $txt['media_zoom_close_title'], '",
			loading: "', $txt['media_zoom_loading'], '",
			loadingTitle: "', $txt['media_zoom_clicktocancel'], '",
			restoreTitle: "', $txt['media_zoom_clicktoclose'], '",
			focusTitle: "', $txt['media_zoom_focus'], '",
			fullExpandTitle: "', $txt['media_zoom_expandtoactual'], '",
			previousTitle: "', $txt['media_zoom_previous'], '",
			nextTitle: "', $txt['media_zoom_next'], '",
			playTitle: "', $txt['media_zoom_play'], '",
			pauseTitle: "', $txt['media_zoom_pause'], '"
		},
		outline: "', empty($peralbum) || !in_array($peralbum['outline'], array('drop-shadow', 'white', 'black')) ? 'glass' : $peralbum['outline'], '",
		expand: ', !isset($peralbum['expand']) ? 800 : (int) $peralbum['expand'], '
	});');
	return;

	// !!! WIP !! @todo: Convert this to the new format...

/*
	$not_single = empty($peralbum) ? 'true' : 'false';
	$fadein = empty($peralbum) || !empty($peralbum['fadeinout']) ? 'true' : 'false';

	add_js(empty($peralbum) ? '
	hs.Expander.prototype.onInit = function ()
	{
		for (var i = 0, j = this.a.attributes, k = j.length; i < k; i++)
		{
			if (j[i].value.indexOf(\'htmlExpand\') != -1)
			{
				getXMLDocument(\'index.php?action=media;sa=addview;in=\' + this.a.id.substr(3), function() {});
				return;
			}
		}
	}

	var slideOptions = { slideshowGroup: \'aeva\', align: \'center\', transitions: [\'expand\', \'crossfade\'], fadeInOut: ' . $fadein . ' };
	var mediaOptions = { slideshowGroup: \'aeva\', align: \'center\', transitions: [\'expand\', \'crossfade\'], fadeInOut: ' . $fadein . ', width: 1 };' : '
	var slideOptions = { align: \'center\', transitions: [\'expand\', \'crossfade\'], fadeInOut: ' . $fadein . ' };');

	add_js('

	if (hs.addSlideshow) hs.addSlideshow({
		slideshowGroup: \'aeva\',
		interval: 5000,
		repeat: false,
		useControls: true,
		fixedControls: \'fit\',
		overlayOptions: {
			opacity: .66,
			position: \'bottom center\',
			hideOnMouseOut: true
		}
	});

	$("a.zoom").click(function () {
		var $this = $(this);
		if ($this.hasClass("is_media"))
			mediaOptions.width = $this.data("width");
		return $this.hasClass("is_html") ? hs.htmlExpand(this) : ($this.hasClass("is_embed") ? hs.expand(this) :
			  ($this.hasClass("is_media") ? hs.htmlExpand(this, mediaOptions) : hs.expand(this, slideOptions)));
	});', $autosize ? '' : '

	hs.allowSizeReduction = false;
	hs.numberOfImagesToPreload = 0;');

	if (empty($peralbum))
		return;

	add_js($peralbum['autosize'] == 'yes' ? '' : '
	hs.allowSizeReduction = false;');

	return;
*/
}

// Gets an encrypted filename
// Derived from getAttachmentFilename function in Subs.php
// It's not much of a strong encryption though... Since it's a md5 string based on a string
// that appears in clear right before it... Uh. Is that really useful at this point?
function aeva_getEncryptedFilename($name, $id, $check_for_encrypted = false, $both = false)
{
	global $amSettings;

	if ($id < 5)
		return $both ? array($name, $name) : $name;

	// Remove special accented characters - eg. sí.
	$clean_name = strtr($name, 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
	$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));

	// Sorry, no spaces, dots, or anything else but letters allowed.
	$clean_name = preg_replace(array('/\s/', '/[^\w_\.-]/'), array('_', ''), $clean_name);
	$ext = aeva_getExt($name);
	$enc_name = $id . '_' . strtr($clean_name, '.', '_') . md5($clean_name) . '_ext' . $ext;
	$clean_name = substr(sha1($id), 0, 2) . sha1($id . $clean_name) . '.' . $ext;

	return $both ? array($clean_name, $enc_name) : (!$check_for_encrypted || empty($amSettings['clear_thumbnames']) ? $enc_name : $clean_name);
}

// Returns a php.ini size limit, in bytes
function aeva_getPHPSize($size)
{
	if (preg_match('/^([\d\.]+)([gmk])?$/i', @ini_get($size), $m))
	{
		$value = $m[1];
		if (isset($m[2]))
		{
			switch (strtolower($m[2]))
			{
				case 'g': $value *= 1024;
				case 'm': $value *= 1024;
				case 'k': $value *= 1024;
			}
		}
	}
	return isset($value) ? $value : 0;
}

function aeva_getTags($taglist)
{
	return aeva_splitTags(str_replace('&quot;', '"', $taglist));
}

function aeva_splitTags($string, $separator = ',')
{
	$elements = explode($separator, $string);
	for ($i = 0; $i < count($elements); $i++)
	{
		$nquotes = substr_count($elements[$i], '"');
		if ($nquotes % 2 == 1)
			for ($j = $i+1; $j < count($elements); $j++)
				if (substr_count($elements[$j], '"') % 2 == 1)
				{
					array_splice($elements, $i, $j-$i+1, implode($separator, array_slice($elements, $i, $j-$i+1)));
					break;
				}
		if ($nquotes > 0)
			$elements[$i] = str_replace('""', '"', $elements[$i]);
		$elements[$i] = westr::htmlspecialchars(trim($elements[$i], '" '));
	}
	return $elements;
}

function aeva_string($str, $is_filename = true, $limit = 255, $ellipsis = true, $check_multibyte = false, $cut_long_words = false, $hard_limit = 0)
{
	global $settings;

	if (!empty($settings['embed_enabled']) && function_exists('aeva_onposting'))
		$str = aeva_onposting($str);

	if ($limit === 0 || westr::strlen($str) <= $limit)
	{
		if ($cut_long_words)
		{
			$cw = is_int($cut_long_words) ? round($cut_long_words / 2) + 1 : round($limit / 3) + 1;
			$str = preg_replace('~(\w{'.$cw.'})(\w+)~u', '$1&shy;$2', $str);
		}
		return $str;
	}

	$ext = $is_filename ? strrchr($str, '.') : '';
	$base = !empty($ext) ? substr($str, 0, -strlen($ext)) : $str;
	return westr::cut($base, $limit, $check_multibyte, $cut_long_words, $ellipsis, false, $hard_limit) . $ext;
}

function aeva_entities2utf($mixed)
{
	if (function_exists('mb_decode_numericentity'))
		return mb_decode_numericentity($mixed, array(0x80, 0x2ffff, 0, 0xffff), 'UTF-8');

	$mixed = preg_replace('/&#(\d+);/me', 'aeva_utf8_chr($1)', $mixed);
	$mixed = preg_replace('/&#x(\d+);/me', 'aeva_utf8_chr(0x$1)', $mixed);
	return $mixed;
}

function aeva_utf8_chr($code)
{
	if ($code < 128) return chr($code);
	if ($code < 2048) return chr(($code >> 6) + 192) . chr(($code & 63) + 128);
	if ($code < 65536) return chr(($code >> 12) + 224) . chr((($code >> 6) & 63) + 128) . chr(($code & 63) + 128);
	if ($code < 2097152) return chr($code >> 18 + 240) . chr((($code >> 12) & 63) + 128) . chr(($code >> 6) & 63 + 128) . chr($code & 63 + 128);
}

?>