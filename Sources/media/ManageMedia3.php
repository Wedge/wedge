<?php
/****************************************************************
* Aeva Media													*
* © Noisen.com													*
*****************************************************************
* ManageMedia3.php - admin area for the auto-embedder			*
*****************************************************************
* Users of this software are bound by the terms of the			*
* Aeva Media license. You can view it in the license_am.txt		*
* file, or online at http://noisen.com/license-am2.php			*
*																*
* For support and updates, go to http://aeva.noisen.com			*
****************************************************************/

// Prevent attempts to access this file directly
if (!defined('SMF'))
	die('Hacking attempt...');

//////////////////////////////////
define('AEVA_SITELIST_VERSION', '5');
//////////////////////////////////

// Handles the admin pages
function aeva_admin_embed()
{
	global $context, $scripturl, $txt, $modSettings, $sourcedir;

	// Our sub-template
	loadSubTemplate('aeva_form');
	$context['template_layers'][] = 'aeva_admin_enclose_table';

	$context['current_area'] = isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'sites' ? 'sites' : 'config';

	$is_sites = isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'sites';

	// Increase timeout limit - Reading and writing files may take a little time
	@set_time_limit(600);

	// Test whether lookups work - don't let it run more than once every day, except if we add ;flt (force lookup test) in the URL
	if (!isset($modSettings['embed_lookup_result']) || ((time() - 24*3600) >= @$modSettings['embed_lookup_test']) || isset($_GET['flt']))
	{
		// We need to access Aeva's aeva_fetch function to grab url files
		loadSource('media/Aeva-Embed');

		// Unlikely, but we might need more umph.
		@ini_set('memory_limit', '64M');

		// Domo arigato, misutaa Robotto
		$url = 'http://www.google.com/robots.txt';

		// Fetch the file... Now or never.
		$data = @aeva_fetch($url, true);

		// If we got nothin', try a last time on Noisen...
		if (empty($data) || strlen($data) < 50)
			$data = @aeva_fetch('http://noisen.com/external.gif');

		// Result? If it's empty or too short, then lookups won't work :(
		$modSettings['embed_lookup_result'] = $test = empty($data) || strlen($data) < 50 ? 0 : 1;

		// Save the result so we don't need to run this again.
		$results = array('embed_lookup_test' => time(), 'embed_lookup_result' => $test);
		if (!isset($modSettings['embed_lookups']))
			$results['embed_lookups'] = (int) $modSettings['embed_lookup_result'];
		updateSettings($results);

		$test = $txt['embed_lookup_' . (empty($test) ? 'fail' : 'success')];
	}
	else
		$test = $txt['embed_lookup_' . (empty($modSettings['embed_lookup_result']) ? 'fail' : 'success')];

	$test = $txt['embed_lookups_desc'] . '<br><span style="font-weight: bold; color: ' . (empty($modSettings['embed_lookup_result']) ? 'red' : 'green') . '">' . $test . '</span>';

	$settings = array(
		'media_admin_labels_embed'	=> array('title', 'config'),
		'hr1'						=> array('hr', 'config'),
		'embed_lookups'				=> array('yesno', 'config', 'subtext' => $test, 'disabled' => !empty($modSettings['embed_lookup_result']) ? 0 : 1),
		'embed_yq'					=> array('select', 'config', array(&$txt['embed_yq_default'], &$txt['embed_yq_hd'])),
		'hr2'						=> array('hr', 'config'),
		'embed_titles'				=> array('select', 'config', array(&$txt['embed_titles_yes'], &$txt['embed_titles_yes2'], &$txt['embed_titles_no'], &$txt['embed_titles_no2'])),
		'embed_lookup_titles'		=> array('yesno', 'config'),
		'embed_inlinetitles'		=> array('select', 'config', array(&$txt['embed_inlinetitles_yes'], &$txt['embed_inlinetitles_maybe'], &$txt['embed_inlinetitles_no'])),
		'hr3'						=> array('hr', 'config'),
		'embed_center'				=> array('yesno', 'config'),
		'embed_incontext'			=> array('yesno', 'config'),
		'embed_quotes'				=> array('yesno', 'config'),
		'embed_fix_html'			=> array('yesno', 'config'),
		'embed_includeurl'			=> array('yesno', 'config'),
		'hr4'						=> array('hr', 'config'),
		'embed_noscript'			=> array('yesno', 'config'),
		'embed_expins'				=> array('yesno', 'config'),
		'embed_checkadmin'			=> array('yesno', 'config'),
		'hr5'						=> array('hr', 'config'),
		'embed_max_width'			=> array('small_text', 'config', null, null, $txt['media_pixels']),
		'embed_max_per_post'		=> array('small_text', 'config', null, null, $txt['media_lower_items']),
		'embed_max_per_page'		=> array('small_text', 'config', null, null, $txt['media_lower_items']),
		'embed_local'				=> array('title', 'config'),
		'embed_nonlocal'			=> array('yesno', 'config'),
		'embed_ext'					=> array('checkbox_line', 'config', array(), true),
	);

	foreach (array('mp3','mp4','flv','avi','divx','mov','wmp','real','swf') as $ext)
		$settings['embed_ext'][2]['embed_' . $ext] = array($txt['embed_' . $ext], !empty($modSettings['embed_' . $ext]), 'force_name' => 'embed_' . $ext);

	// Clear sites that may have already been loaded (possibly for news and such)
	$sites = array();

	// Avoid errors - we'll use full in an emergency
	$definitions = 'full';

	// Attempt to load enabled sites
	if (file_exists($sourcedir . '/media/Subs-Aeva-Generated-Sites.php'))
		rename($sourcedir . '/media/Subs-Aeva-Generated-Sites.php', $sourcedir . '/media/Aeva-Sites.php');
	if (file_exists($sourcedir . '/media/Aeva-Sites.php'))
		include($sourcedir . '/media/Aeva-Sites.php');

	// Site definitions
	if (empty($sites))
		$definitions = 'full';
	elseif ($sites[0] == 'none')
	{
		// No enabled sites
		$definitions = 'none';
		$enabled_sites = array();
	}
	else
	{
		// Generated set means that we have an optimized array with only the enabled sites in it
		$definitions = 'generated';

		// Only count as enabled, sites with an actual ID
		foreach (array_keys($sites) as $site)
			if (!empty($sites[$site]['id']))
				$enabled_sites[$sites[$site]['id']] = 1;
	}

	// Clear static
	$sites = array();

	// Load the FULL definitions into the $sites static
	@include($sourcedir . '/media/Subs-Aeva-Sites.php');

	// Checkall helps us decide whether to make the checkboxes all checked
	$checkall = array('pop' => true, 'video' => true, 'audio' => true, 'adult' => true, 'other' => true);
	// Create arrays to store bits of information/organize them into various sections
	$stypes = array('local', 'pop', 'video', 'audio', 'adult', 'other');

	if (file_exists($sourcedir . '/media/Aeva-Sites-Custom.php'))
	{
		@include($sourcedir . '/media/Aeva-Sites-Custom.php');
		$checkall['custom'] = true;
		$stypes[] = 'custom';
	}

	$sitelist = array();
	foreach ($stypes as $stype)
		$sitelist[$stype] = array();

	// Prepare to organize the sites into specific sections
	foreach (array_keys($sites) as $site)
	{
		$s = &$sites[$site];

		// Make sure it has the enabled setting
		$s['disabled'] = !empty($s['disabled']);

		// Override the default setting, based on which sites are enabled
		if ($definitions == 'generated')
			$s['disabled'] = empty($enabled_sites[$s['id']]);
		elseif ($definitions == 'none')
			$s['disabled'] = true;

		// Checkall - whether the checkall setting for each section is checked. It won't be if just one is unchecked.
		if ($s['disabled'])
			$checkall[$s['type']] = false;

		// Store in arrays organized for different types of supported sites
		// We only need the local ones on saving
		if (isset($s['type'], $sitelist[$s['type']]) && ($s['type'] != 'local' || isset($_POST['submit_aeva'])))
			$sitelist[$s['type']][] = $s;
	}

	// Clear static
	$sites = array();

	// Submitting?
	if (isset($_POST['submit_aeva']))
	{
		// Prepare/optimize the arrays for the generated file by removing disabled sites and unneeded details
		$wsites = array();
		foreach ($stypes as $stype)
		{
			if (!empty($sitelist[$stype]))
			{
				$checkall[$stype] = true;
				$wsites[$stype] = aeva_prepare_sites($sitelist[$stype], $stype, $is_sites, $checkall[$stype]);
			}
		}
		unset($sitelist['local']);

		// Writes/outputs a php file of all the ENABLED sites only
		aeva_write_file($wsites);
		unset($wsites);

		if (!$is_sites)
		{
			// These need to be within limits, and max per page >= max per post
			if (isset($_POST['embed_max_per_page']))
			{
				$_POST['embed_max_per_page'] = min(1000, max(1, (int) $_POST['embed_max_per_page']));
				$_POST['embed_max_per_post'] = min(min(1000, max(1, (int) $_POST['embed_max_per_post'])), $_POST['embed_max_per_page']);
			}

			foreach ($settings as $setting => $options)
			{
				// Skip if we're not in the right page...
				if ($options[1] != $context['current_area'])
					continue;
				if ($options[0] != 'title' && isset($_POST[$setting]))
					$new_value = is_array($_POST[$setting]) ? $_POST[$setting] : westr::htmlspecialchars($_POST[$setting]);
				elseif ($options[0] == 'checkbox' && !isset($_POST[$setting]))
					$new_value = 0;
				elseif ($options[0] !== 'checkbox_line')
					continue;

				if (!empty($options[2]) && is_array($options[2]) && !in_array($options[0], array('radio', 'select')))
				{
					foreach ($options[2] as $sub_setting => $dummy)
					{
						updateSettings(array($sub_setting => isset($_POST[$sub_setting]) ? 1 : 0));
						$settings[$setting][2][$sub_setting][1] = !empty($modSettings[$sub_setting]);
					}
				}
				else
					updateSettings(array($setting => $new_value));
			}
		}
	}

	if ($is_sites)
		$warning_message =
			'<span style="font-weight: normal; color: ' . (empty($modSettings['embed_lookup_result']) ? 'red' : 'green') .
			'" class="smalltext">' . $txt['embed_' . (empty($modSettings['embed_lookup_result']) ? 'fish' : 'denotes')] . '</span>';

	foreach ($stypes as $stype)
		if ($is_sites && !empty($sitelist[$stype]))
			aeva_settings($settings, $sitelist[$stype], $stype, $checkall);

	// Render the form
	$context['aeva_form_url'] = $scripturl.'?action=admin;area=aeva_embed;sa='.$context['current_area'].';'.$context['session_query'];
	if (!empty($warning_message))
		$context['aeva_form']['warning'] = array('type' => 'info', 'label' => '', 'fieldname' => 'info', 'value' => $warning_message, 'options' => array(), 'multi' => false, 'next' => null, 'subtext' => '', 'skip_left' => true);

	foreach ($settings as $setting => $options)
	{
		if ($options[1] != $context['current_area'])
			continue;

		// Options
		if (!empty($options[2]))
			foreach ($options[2] as $k => $v)
				if (isset($modSettings[$setting]) && $modSettings[$setting] == $k)
					$options[2][$k] = array($v, true);

		$context['aeva_form'][$setting] = array(
			'type' => $options[0],
			'label' => !isset($options['force_title']) ? (isset($txt[$setting]) ? $txt[$setting] : $setting) : $options['force_title'],
			'fieldname' => $setting,
			'value' => isset($modSettings[$setting]) ? $modSettings[$setting] : '',
			'options' => !empty($options[2]) ? $options[2] : array(),
			'multi' => !empty($options[3]) && $options[3] == true,
			'next' => !empty($options[4]) ? ' ' . $options[4] : null,
			'subtext' => isset($options['subtext']) ? $options['subtext'] : (isset($txt[$setting.'_desc']) ? $txt[$setting.'_desc'] : ''),
			'disabled' => !empty($options['disabled']),
			'skip_left' => !empty($options['skip_left']),
		);
	}
}

function aeva_update_sitelist()
{
	global $modSettings, $context;

	@set_time_limit(900);
	$data = @aeva_fetch('http://noisen.com/aeva_latest.js');
	if (!defined('AEVA_MEDIA_VERSION'))
		loadSource('media/Subs-Media');

	$latest_version = preg_match('~ver = "([^"]+)"~', $data, $latest_version) ? $latest_version[1] : AEVA_MEDIA_VERSION;
	$latest_sitelist = preg_match('~lis = "([^"]+)"~', $data, $latest_sitelist) ? $latest_sitelist[1] : AEVA_SITELIST_VERSION;
	$current_id = !empty($modSettings['aeva_latest_sitelist']) ? $modSettings['aeva_latest_sitelist'] : $latest_sitelist;

	if ($latest_sitelist != $current_id)
	{
		$new_sitelist = @aeva_fetch('http://noisen.com/sitelist.txt');
		if ((substr($new_sitelist, -2) == '?' . '>') && (preg_match('~\$aeva_min \= ([0-9]+)~', $new_sitelist, $aeva_min) ? $aeva_min[1] : 0) <= AEVA_SITELIST_VERSION)
		{
			$sites = array();
			@include($sourcedir . '/media/Subs-Aeva-Sites.php');
			$my_file = fopen($sourcedir . '/media/Subs-Aeva-Sites.php', 'w');
			if (!$my_file)
				return;
			fwrite($my_file, $new_sitelist, strlen($new_sitelist));
			fclose($my_file);
			aeva_show_sitelist_updates();
			$sites = array();
			$update_me = true;
		}
	}

	updateSettings(array('embed_latest_version' => $latest_version, 'embed_latest_sitelist' => $latest_sitelist, 'embed_version_test' => time()));

	if (empty($update_me))
		return;

	// Now it'll be easier to just simulate calling the Aeva admin area to save the updated sitelist, won't it?
	$ps = $_POST;
	$_POST['submit_aeva'] = true;
	$context['embed_auto_updating'] = true;
	foreach ($modSettings as $ms => $cms)
		if (substr($ms, 0, 6) == 'embed_' && !empty($cms))
			$_POST[$ms] = $cms;
	unset($_REQUEST['sa']);
	aeva_admin_embed();
	$_POST = $ps;
	unset($ps);
	$url = 'http' . (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? '' : 's') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$url = str_ireplace(array(';checkaeva', '&checkaeva', 'checkaeva'), '', $url);
	redirectexit($url, false);
}

function aeva_show_sitelist_updates()
{
	global $sites, $txt, $modSettings;

	if (!isset($txt['media']) && loadLanguage('Media') == false)
		loadLanguage('Media', 'english');

	$alu = empty($modSettings['embed_list_updates']) ? '' : $modSettings['embed_list_updates'];
	$old_sites = $new_sites = array();
	foreach ($sites as $s)
		$old_sites[$s['id']] = $s['title'] . '@' . serialize($s);

	$sites = array();
	@include($sourcedir . '/media/Subs-Aeva-Sites.php');
	foreach ($sites as $s)
		$new_sites[$s['id']] = $s['title'] . '@' . serialize($s);
	unset($sites);

	$sites_added = array_diff_key($new_sites, $old_sites);
	$sites_removed = array_diff_key($old_sites, $new_sites);
	$sites_modified = array();
	foreach (array_keys($new_sites) as $a)
		if (isset($old_sites[$a]) && $old_sites[$a] != $new_sites[$a])
			$sites_modified[] = $new_sites[$a];

	$msg = '';
	if (count($sites_added) > 0)
	{
		$msg .= '<br>' . $txt['embed_sitelist_added'] . ' ';
		foreach ($sites_added as $s)
			$msg .= substr($s, 0, strpos($s, '@')) . ', ';
		$msg = substr($msg, 0, -2) . '<br>';
	}
	if (count($sites_removed) > 0)
	{
		$msg .= '<br>' . $txt['embed_sitelist_removed'] . ' ';
		foreach ($sites_removed as $s)
			$msg .= substr($s, 0, strpos($s, '@')) . ', ';
		$msg = substr($msg, 0, -2) . '<br>';
	}
	if (count($sites_modified) > 0)
	{
		$msg .= '<br>' . $txt['embed_sitelist_modified'] . ' ';
		foreach ($sites_modified as $s)
			$msg .= substr($s, 0, strpos($s, '@')) . ', ';
		$msg = substr($msg, 0, -2) . '<br>';
	}
	unset($sites_added, $sites_removed, $sites_modified);

	// Nothing changed? Then it's a false alarm, keep it silent.
	if (empty($msg))
		return;

	updateSettings(array('embed_list_updates' => empty($alu) ? '<span style="color: red">' . $txt['embed_sitelist_updated'] . '</span><br>' . $msg : $alu . $msg));
}

// Removes disabled sites, and removes information we won't need.
function aeva_prepare_sites(&$original_array, $type, $is_sites, &$checkall)
{
	global $modSettings;

	if ($is_sites && $type != 'local' && (empty($_POST['embed_' . $type]) || !is_array($_POST['embed_' . $type])))
	{
		$checkall = false;
		return array();
	}

	// Unset our KNOWN unnecessary information - this way it won't interfere with future variables, upgrading, or any custom variables you decide to use.

	// These are NEVER needed
	$fields = array('title', 'website', 'type', 'disabled');

	// Lookups are disabled, so get rid of all unnecessary information
	if ($type != 'local' && ($is_sites ? empty($modSettings['embed_lookups']) : empty($_POST['embed_lookups'])))
		$fields = array_merge($fields, array(
			'lookup-url', 'lookup-title', 'lookup-title-skip', 'lookup-pattern', 'lookup-actual-url',
			'lookup-final-url', 'lookup-unencode', 'lookup-urldecode', 'lookup-skip-empty')
		);

	// If fixing embed html is disabled, add that to the fields to drop (is likely to be bandwidth saving with this one)
	if ($type != 'local' && ($is_sites ? empty($modSettings['embed_fix_html']) : empty($_POST['embed_fix_html'])))
		$fields = array_merge($fields, array('fix-html-pattern', 'fix-html-url'));

	// Unset video sites from arrays which are disabled
	$array = $original_array;
	foreach ($array as $a => $b)
	{
		if ($type == 'local')
		{
			// No plugin, then it can't be a local one, so unset it
			if (empty($b['plugin']))
				unset($array[$a], $b);
			// Don't save data if box was unchecked or option was disabled
			elseif ($is_sites ? empty($modSettings['embed_' . substr($b['id'], 6)]) : !isset($_POST['embed_' . substr($b['id'], 6)]))
				unset($array[$a], $b);
		}
		// Site disabled? Skip it
		elseif ($is_sites ? !isset($_POST['embed_' . $type][$b['id']]) : $b['disabled'])
			unset($array[$a], $b);
		elseif (isset($b['plugin']) && $b['plugin'] == 'flash')
			unset($array[$a]['plugin']);

		// Drop each one of those fields from our array if it exists
		if (!empty($b))
			foreach ($fields as $c)
				unset($array[$a][$c]);

		if (isset($array[$a]['lookup-title']) && ($is_sites ? !empty($modSettings['embed_titles']) : !empty($_POST['embed_titles']))
		&& (empty($array[$a]['lookup-title-skip']) || (!empty($modSettings['embed_titles']) && ($modSettings['embed_titles'] % 2 == 1))))
			unset($array[$a]['lookup-title']);

		$checkall &= !($original_array[$a]['disabled'] = empty($array[$a]));
	}
	unset($_POST['embed_' . $type]);

	return $array;
}

// Generates the file containing optimized arrays (ONLY enabled sites with only necessary information
function aeva_write_file($arrays)
{
	global $sourcedir;

	// Filename
	$filename = $sourcedir . '/media/Aeva-Sites.php';

	// Chmod - suppress errors, especially for Windows
	@chmod($filename, 0777);

	// Open file for writing (replacing what's there)
	$fp = fopen($filename, 'w');

	// Comment header - left-justified
	$page = '<?php
/********************************************************************************
* Aeva-Sites.php
* By Rene-Gilles Deberdt
*********************************************************************************
* The full/complete definitions are stored in Subs-Aeva-Sites.php
* This is a GENERATED php file containing ONLY ENABLED sites for Aeva Media,
* and is created when enabling/disabling sites via the admin panel.
* It\'s more efficient this way.
*********************************************************************************
* This program is distributed in the hope that it is and will be useful, but
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY
* or FITNESS FOR A PARTICULAR PURPOSE.
********************************************************************************/

';

	// If no sites are enabled, then exit early
	if (count($arrays) == count($arrays, COUNT_RECURSIVE))
	{
		// Last piece, and close the file early
		$page .= 'global $sites;
$sites = array(\'none\');
				/* No Sites Are Enabled */
?' . '>';
		fwrite($fp, $page);
		fclose($fp);
		return;
	}

	// Ok we've got some enabled sites to output, start the array
	$page .= 'global $sites;
$sites = array(';
	fwrite($fp, $page);

	foreach ($arrays as $one_array)
		if (!empty($one_array))
			fwrite($fp, aeva_generate_sites($one_array));

	// Last piece, and close the file
	$page = '
);
?' . '>';
	fwrite($fp, $page);
	fclose($fp);
}

// Returns a string with the sites in array - ONLY necessary pieces are included for optimized/effiency
function aeva_generate_sites(&$array)
{
	$page = '';
	foreach ($array as $a)
	{
		$page .= '
	array(';

		// Bools show as bools, the rest shows in single quotes. (Re-adding them because PHP stripped them.)
		foreach ($a as $b => $c)
			if (isset($c) && $c !== '')
				if (is_array($c) && $b == 'size')
				{
					$page .= "
		'$b' => array(";
					if (isset($c['normal']))
					{
						foreach ($c as $d => $e)
							$page .= "'$d' => array(" . $e[0] . ', ' . $e[1] . '), ';
						$page = substr($page, 0, -2) . '),';
					}
					else
						$page .= implode($c, ', ') . '),';
				}
				elseif (is_array($c))
				{
					$page .= "
		'$b' => array(";
					foreach ($c as $d => $e)
						$page .= "'$d' => " . (is_int($e) ? $e . ', ' : "'" . str_replace("'", "\'", $e) . "', ");
					$page = substr($page, 0, -2) . '),';
				}
				else
					$page .= "
		'$b' => " . ($b == 'ui-height' ? (int) $c : (is_bool($c) || is_int($c) ? ($c == true ? 'true' : 'false') : "'" . str_replace('\'', '\\\'', $c) . "'")) . ",";

		$page .= '
	),';
	}
	return $page;
}

// Fills the admin settings for each type of site
function aeva_settings(&$dest, &$array, $type, $checkall)
{
	global $txt, $modSettings, $settings;

	$dest['embed_' . $type] = array('title', 'sites', null, null, null, 'force_title' => '<strong>' . $txt['embed_' . $type . '_sites'] . ' (' . count($array) . ')</strong> - <input type="checkbox" id="checkall_' . $type . '" onclick="invertAll(this, this.form, \'embed_' . $type . '\');" ' . (!empty($checkall[$type]) ? ' checked' : '') . '><label class="smalltext" for="checkall_' . $type . '"> <em>' . $txt['media_select'] . '</em></label>');
	$dest['embed_' . $type . '_items'] = array('checkbox_line', 'sites', array(), true, null, 'skip_left' => true);

	// Now for the magic block builder
	foreach ($array as $arr)
	{
		$link = (!empty($arr['website']) ? '<a href="' . $arr['website'] . '" style="text-decoration: none" title="-" target="_blank">&oplus;</a> ' : '') . $arr['title']
				. (!empty($arr['lookup-url']) ? '<span style="color: ' . (empty($modSettings['embed_lookup_result']) ? 'red' : 'green') . '">*</span>' : '');
		$dest['embed_' . $type . '_items'][2]['embed_' . $arr['id']] = array($link, !$arr['disabled'], 'force_name' => 'embed_' . $type . '[' . $arr['id'] . ']');
	}
}

?>