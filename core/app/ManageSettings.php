<?php
/**
 * Common administration settings are declared and managed in this file.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file is here to make it easier for installed mods to have settings
	and options.  It uses the following functions:

	void ModifyFeatureSettings()
		// !!!

	void ModifyBasicSettings()
		// !!!

	void ModifyWarningSettings()
		// !!!

	void ModifySpamSettings()
		// !!!

	void ModifyLogSettings()
		// !!!

	void disablePostModeration()
		// !!!

	void ModifyPrettyURLs()
		- Admin area for Pretty URLs
*/

// This just avoids some repetition.
function loadGeneralSettingParameters($subActions = array(), $defaultAction = '')
{
	global $context;

	// You need to be an admin to edit settings!
	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	// Will need the utility functions from here.
	loadSource('ManageServer');

	wetem::load('show_settings');

	// By default do the basic settings.
	$_REQUEST['sa'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : (!empty($defaultAction) ? $defaultAction : array_pop(array_keys($subActions)));
	$context['sub_action'] = $_REQUEST['sa'];
}

// This function passes control through to the relevant tab.
function ModifyFeatureSettings()
{
	global $context, $txt;

	$context['page_title'] = $txt['settings_title'];

	$subActions = array(
		'basic' => 'ModifyBasicSettings',
		'paths' => 'ModifyPathSettings',
		'pretty' => 'ModifyPrettyURLs',
	);

	loadGeneralSettingParameters($subActions, 'basic');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['settings_title'],
		'help' => 'featuresettings',
		'description' => $txt['settings_desc'],
		'tabs' => array(
			'basic' => array(
			),
			'paths' => array(
			),
			'pretty' => array(
				'description' => $txt['pretty_urls_desc'],
			),
		),
	);

	// Call the right function for this sub-action.
	$subActions[$_REQUEST['sa']]();
}

function ModifyBasicSettings($return_config = false)
{
	global $txt, $context;

	$config_vars = array(
			array('text', 'mbname', 30, 'file' => true),
			array('text', 'home_url', 40, 'subtext' => $txt['home_url_subtext']),
			array('check', 'home_link', 'subtext' => $txt['home_link_subtext']),
		'',
			array('text', 'site_slogan', 40, 'subtext' => $txt['site_slogan_desc']),
			array('text', 'header_logo_url', 40, 'subtext' => $txt['header_logo_url_desc']),
		'',
			// Statistics.
			array('check', 'trackStats'),
			array('check', 'hitStats'),
		'',
			array('select', 'todayMod', array(
				$txt['today_disabled'],
				$txt['today_only'],
				$txt['yesterday_today'],
			)),
		'',
			// Option-ish things... miscellaneous sorta.
			array('check', 'disallow_sendBody'),

		array('title', 'member_options_title', 'icon' => 'memberoptions.png'),

			array('check', 'allow_guestAccess'),
		'',
			array('check', 'enable_buddylist'),
			array('check', 'allow_editDisplayName'),
			array('check', 'titlesEnable'),
			array('check', 'approveAccountDeletion'),
		'',
			array('check', 'allow_disableAnnounce'),

		array('title', 'look_layout_title', 'icon' => 'current_theme.png'),

			array('check', 'show_stats_index'),
			array('check', 'show_latest_member'),
			array('check', 'show_board_desc'),
			array('check', 'show_children'),
			array('check', 'show_avatars'),
			array('check', 'show_gender'),
			array('check', 'show_blurb'),
			array('check', 'show_signatures'),

		array('title', 'admin_likes', 'icon' => 'likes.png'),

			array('check', 'likes_enabled'),
			array('check', 'likes_own_posts'),
	);

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		saveSettings($config_vars);

		redirectexit('action=admin;area=featuresettings;sa=basic');
	}

	$context['post_url'] = '<URL>?action=admin;area=featuresettings;save;sa=basic';
	$context['settings_title'] = $txt['mods_cat_features'];

	prepareDBSettingContext($config_vars);
}

// Basic path settings - absolute locations for main folders.
function ModifyPathSettings($return_config = false)
{
	global $context, $txt;

	$config_vars = array(
		array('text', 'boardurl', 36, 'file' => true),
		array('text', 'boarddir', 36, 'file' => true),
		array('text', 'sourcedir', 36, 'file' => true),
		array('text', 'cachedir', 36, 'file' => true),
		array('text', 'pluginsdir', 36, 'file' => true),
		array('text', 'pluginsurl', 36, 'file' => true),
		'',
		array('text', 'theme_url', 36),
		array('text', 'theme_dir', 36),
		array('text', 'images_url', 36),
	);

	if ($return_config)
		return $config_vars;

	// Setup the template stuff.
	$context['post_url'] = '<URL>?action=admin;area=featuresettings;sa=paths;save';
	$context['settings_title'] = $txt['path_settings'];

	// Saving settings?
	if (isset($_REQUEST['save']))
	{
		saveSettings($config_vars);
		redirectexit('action=admin;area=featuresettings;sa=paths;' . $context['session_query']);
	}

	// Fill the config array.
	prepareDBSettingContext($config_vars);
}

// Let's try keep the spam to a minimum ah Thantos?
function ModifySpamSettings($return_config = false)
{
	global $txt, $context, $settings;

	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	loadSource('ManageServer');

	// Generate a sample registration image.
	$context['verification_image_href'] = '<URL>?action=verification;rand=' . md5(mt_rand());

	$context['page_title'] = $context['settings_title'] = $txt['antispam_settings'];

	$config_vars = array(
			array('desc', 'antispam_settings_desc'),
			array('check', 'reg_verification'),
			array('check', 'search_enable_captcha'),
			// This, my friend, is a cheat :p
			'guest_verify' => array('check', 'guests_require_captcha', 'subtext' => $txt['setting_guests_require_captcha_desc']),
			array('int', 'posts_require_captcha', 'max' => 999999, 'subtext' => $txt['posts_require_captcha_desc'], 'onchange' => '$(\'#guests_require_captcha\').prop(this.value > 0 ? { disabled: true, checked: true } : { disabled: false });'),
			array('check', 'guests_report_require_captcha'),
			// Visual verification.
			array('title', 'configure_captcha'),
			array('desc', 'configure_captcha_desc'),
			array('check', 'use_captcha_images'),
			array('check', 'use_animated_captcha', 'subtext' => $txt['use_animated_captcha_desc']),
			// Clever Thomas, who is looking sheepy now? Not I, the mighty sword swinger did say.
			array('title', 'setup_verification_questions'),
			array('desc', 'setup_verification_questions_desc'),
			array('int', 'qa_verification_number', 'max' => 5, 'subtext' => $txt['setting_qa_verification_number_desc']),
		'',
			array('callback', 'question_answer_list'),
	);

	call_hook('settings_spam', array(&$config_vars, &$return_config));

	if ($return_config)
		return $config_vars;

	// Load any question and answers!
	getLanguages();

	$css = array();
	foreach ($context['languages'] as $lang_id => $lang)
		$css[] = '#antispam .flag_' . $lang_id;
	add_css('
	' . implode(', ', $css) . ' { margin-right: 4px; margin-bottom: 1px } #antispam td { vertical-align: top; text-align: center } #antispam td.lang { text-align: right }');

	$context['qa_verification_qas'] = array();

	if (!empty($settings['qa_verification_qas']))
	{
		$qa = unserialize($settings['qa_verification_qas']);
		foreach ($qa as $lang => $questions)
			foreach ($questions as $q_a_set)
			{
				$question = array_shift($q_a_set);
				$context['qa_verification_qas'][] = array(
					'lang' => $lang,
					'question' => $question,
					'answers' => $q_a_set,
				);
			}
	}

	/*
	'english' => array(
		(a question, a possible answer, another possible answer)
		(another question, the only possible answer)
	),
	'french' => array(
		(la seule question, la seule réponse possible)
	),
	*/

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Hack in guest requiring verification!
		if (empty($_POST['posts_require_captcha']) && !empty($_POST['guests_require_captcha']))
			$_POST['posts_require_captcha'] = -1;

		$count_questions = 0;
		// Slightly hackish but means we push everything through one place.
		if (empty($_POST['question']))
		{
			$config_vars[] = array('text', 'qa_verification_qas');
			$_POST['qa_verification_number'] = 0;
			$_POST['qa_verification_qas'] = '';
		}
		else
		{
			$qa_verification_qas = array();
			$lang_list = array();

			foreach ($_POST['lang_select'] as $id => $lang)
			{
				if (empty($lang) || !isset($context['languages'][$lang]) || empty($_POST['question']) || !is_array($_POST['question']) || empty($_POST['question'][$id]))
					continue;

				$question = trim(westr::htmlspecialchars($_POST['question'][$id], ENT_QUOTES));
				if (empty($_POST['answer']) || !is_array($_POST['answer']) || empty($_POST['answer'][$id]))
					continue;
				$answers = array();
				foreach ($_POST['answer'][$id] as $answer)
				{
					$answer = trim(westr::htmlspecialchars($answer, ENT_QUOTES));
					if (!empty($answer))
						$answers[] = $answer;
				}
				if (!empty($question) && !empty($answers))
				{
					$qa_verification_qas[$lang][] = array_merge((array) $question, $answers);
					if (isset($lang_list[$lang]))
						$lang_list[$lang]++;
					else
						$lang_list[$lang] = 1;
				}
			}
			if (!empty($lang_list))
				$count_questions = min($lang_list);

			if (!empty($qa_verification_qas))
			{
				$qa_string = serialize($qa_verification_qas);
				if (strlen($qa_string) <= 65535)
				{
					$_POST['qa_verification_qas'] = $qa_string;
					$config_vars[] = array('text', 'qa_verification_qas');
				}
			}
		}

		if (empty($count_questions) || $_POST['qa_verification_number'] > $count_questions)
			$_POST['qa_verification_number'] = $count_questions;

		// Now save.
		saveSettings($config_vars);

		redirectexit('action=admin;area=antispam');
	}

	$character_range = array_merge(range('A', 'H'), array('K', 'M', 'N', 'P', 'R'), range('T', 'Y'));
	$_SESSION['visual_verification_code'] = '';
	for ($i = 0; $i < 6; $i++)
		$_SESSION['visual_verification_code'] .= $character_range[array_rand($character_range)];

	// Hack for guests requiring verification.
	$settings['guests_require_captcha'] = !empty($settings['posts_require_captcha']);
	$settings['posts_require_captcha'] = !isset($settings['posts_require_captcha']) || $settings['posts_require_captcha'] == -1 ? 0 : $settings['posts_require_captcha'];

	// Some minor javascript for the guest post setting.
	if ($settings['posts_require_captcha'])
		add_js('
	$(\'#guests_require_captcha\').prop(\'disabled\', true);');

	$context['post_url'] = '<URL>?action=admin;area=antispam;save';

	wetem::load('show_settings');

	prepareDBSettingContext($config_vars);
}

function ModifyLogSettings($return_config = false)
{
	global $txt, $context, $settings;

	// Make sure we understand what's going on.
	loadLanguage('ManageSettings');

	$context['page_title'] = $context['settings_title'] = $txt['log_settings'];

	$config_vars = array(
			array('check', 'enableErrorLogging'),
			array('check', 'enableErrorPasswordLogging'),
			array('check', 'enableError404Logging'),
			array('check', 'enableErrorQueryLogging'),
		'',
			array('check', 'log_enabled_moderate', 'subtext' => $txt['log_enabled_moderate_subtext']),
			array('check', 'log_enabled_admin', 'subtext' => $txt['log_enabled_admin_subtext']),
			array('check', 'log_enabled_profile', 'subtext' => $txt['log_enabled_profile_subtext']),
			// Even do the pruning?
			array('title', 'logPruning'),
			// The array indexes are there so we can remove/change them before saving.
			'pruningOptions' => array('check', 'pruningOptions'),
		'',
			// Various logs that could be pruned.
			array('int', 'pruneErrorLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['pruneZeroDisable']), // Error log.
			array('int', 'pruneModLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['pruneZeroDisable']), // Moderation log.
			array('int', 'pruneReportLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['pruneZeroDisable']), // Report to moderator log.
			array('int', 'pruneScheduledTaskLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['pruneZeroDisable']), // Log of the scheduled tasks and how long they ran.
			array('int', 'pruneSpiderHitLog', 'postinput' => $txt['days_word'], 'subtext' => $txt['pruneZeroDisable']), // Log of the scheduled tasks and how long they ran.
			// If you add any additional logs make sure to add them after this point.  Additionally, make sure you add them to the weekly scheduled task.
			// Plugin developers: do NOT use the pruningOptions master variable for this as Wedge may overwrite your setting in the future!
	);

	if ($return_config)
		return $config_vars;

	// We'll need this in a bit.
	loadSource('ManageServer');

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		$savevar = array(
			array('check', 'enableErrorLogging'),
			array('check', 'enableErrorQueryLogging'),
			array('check', 'log_enabled_moderate'),
			array('check', 'log_enabled_admin'),
			array('check', 'log_enabled_profile'),
			array('text', 'pruningOptions')
		);

		if (!empty($_POST['pruningOptions']))
		{
			$vals = array();
			foreach ($config_vars as $index => $dummy)
			{
				if (!is_array($dummy) || strpos($dummy[1], 'prune') !== 0) // Make sure for this that we only bother with the actual prune variables.
					continue;

				$vals[] = empty($_POST[$dummy[1]]) || $_POST[$dummy[1]] < 0 ? 0 : (int) $_POST[$dummy[1]];
			}
			$_POST['pruningOptions'] = implode(',', $vals);
		}
		else
			$_POST['pruningOptions'] = '';

		saveSettings($savevar);
		redirectexit('action=admin;area=logs;sa=settings');
	}

	$context['post_url'] = '<URL>?action=admin;area=logs;save;sa=settings';
	wetem::load('show_settings');

	// Get the actual values
	if (!empty($settings['pruningOptions']))
		@list ($settings['pruneErrorLog'], $settings['pruneModLog'], $settings['pruneReportLog'], $settings['pruneScheduledTaskLog'], $settings['pruneSpiderHitLog']) = explode(',', $settings['pruningOptions']);
	else
		$settings['pruneErrorLog'] = $settings['pruneModLog'] = $settings['pruneReportLog'] = $settings['pruneScheduledTaskLog'] = $settings['pruneSpiderHitLog'] = 0;

	prepareDBSettingContext($config_vars);
}

function ModifyPmSettings($return_config = false)
{
	global $context, $txt, $settings;
	$config_vars = array(
		array('check', 'pm_enabled'),
	);

	loadLanguage('ManageSettings');

	$context['page_title'] = $context['settings_title'] = $txt['admin_personal_messages'];

	if (!empty($settings['pm_enabled']))
		$config_vars = array_merge($config_vars, array(
			'',
			array('permissions', 'pm_read', 'exclude' => array(-1)),
			array('permissions', 'pm_send', 'exclude' => array(-1)),
			'',
			'pm1' => array('int', 'max_pm_recipients', 'subtext' => $txt['max_pm_recipients_subtext']),
			'pm2' => array('int', 'pm_posts_verification', 'subtext' => $txt['pm_posts_verification_subtext']),
			'pm3' => array('int', 'pm_posts_per_hour', 'subtext' => $txt['pm_posts_per_hour_subtext']),
			'',
			array('check', 'masterSavePmDrafts'),
			array('permissions', 'save_pm_draft', 'exclude' => array(-1)),
			array('check', 'masterAutoSavePmDrafts'),
			array('permissions', 'auto_save_pm_draft', 'exclude' => array(-1)),
			'',
			array('message', 'pm_draft_other_settings'),
		));

	if ($return_config)
		return $config_vars;

	loadSource('ManageServer');

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		$save_vars = $config_vars;
		// Fix PM settings.
		if (!empty($settings['pm_enabled']))
		{
			$_POST['pm_spam_settings'] = (int) $_POST['max_pm_recipients'] . ',' . (int) $_POST['pm_posts_verification'] . ',' . (int) $_POST['pm_posts_per_hour'];

			unset($save_vars['pm1'], $save_vars['pm2'], $save_vars['pm3']);

			$save_vars[] = array('text', 'pm_spam_settings');
		}

		saveSettings($save_vars);

		writeLog();
		redirectexit('action=admin;area=pm');
	}

	$context['post_url'] = '<URL>?action=admin;area=pm;save';

	// Hacky mess for PM settings.
	list ($settings['max_pm_recipients'], $settings['pm_posts_verification'], $settings['pm_posts_per_hour']) = explode(',', $settings['pm_spam_settings']);

	wetem::load('show_settings');
	prepareDBSettingContext($config_vars);
}

/*
	Pretty URLs - custom version for Wedge.
	Contains portions distributed under the New BSD license.
	See PrettyUrls-Filters.php for more details.
*/

// !!! To-do:
//		- Specifically allow for subdomains
//		- Disable the area and subdomain feature, and explain why, if $boardurl has a subfolder name in it!
//			i.e. if (preg_match('~://[^/]+/[^/]+~', $boardurl))

// Shell for all the Pretty URL interfaces
function ModifyPrettyURLs($return_config = false)
{
	global $context, $settings, $txt;

	// For the administrative search function not to get upset.
	if ($return_config)
		return array(
			array('check', 'pretty_filter_boards'),
			array('check', 'pretty_filter_topics'),
			array('check', 'pretty_filter_actions'),
			array('check', 'pretty_filter_profiles'),
		);

	wetem::load('pretty_urls');
	$context['page_title'] = $txt['admin_pretty_urls'];

	// The action filter should always be last, because it's generic.
	if (isset($settings['pretty_filters']['actions']))
	{
		$action = $settings['pretty_filters']['actions'];
		unset($settings['pretty_filters']['actions']);
		$settings['pretty_filters']['actions'] = $action;
	}
	$context['pretty']['filters'] = $settings['pretty_filters'];

	// Are we repopulating now?
	if (isset($_REQUEST['refill']))
	{
		loadSource('PrettyUrls-Filters');
		pretty_synchronize_topic_urls();
		$context['reset_output'] = $txt['pretty_converted'];
	}
	// Are we saving settings now?
	elseif (isset($_REQUEST['save']))
	{
		$is_enabled = false;
		foreach ($settings['pretty_filters'] as $id => &$filter)
			$is_enabled |= ($filter = isset($_POST['pretty_filter_' . $id]) ? 1 : 0);

		$action = isset($_POST['pretty_prefix_action']) ? $_POST['pretty_prefix_action'] : 'do/';
		if ($action != '' && $action != 'do/')
			$action = 'do/';
		$profile = isset($_POST['pretty_prefix_profile']) ? $_POST['pretty_prefix_profile'] : 'profile/';
		if ($profile != '~' && $profile != 'profile/')
			$profile = 'profile/';

		updateSettings(
			array(
				'pretty_enable_filters' => $is_enabled,
				'pretty_enable_cache' => isset($_POST['pretty_cache']) ? ($_POST['pretty_cache'] == 'on' ? 'on' : '') : '',
				'pretty_remove_index' => isset($_POST['pretty_remove_index']) ? ($_POST['pretty_remove_index'] == 'on' ? 'on' : '') : '',
				'pretty_filters' => serialize($settings['pretty_filters']),
				'pretty_prefix_action' => $action,
				'pretty_prefix_profile' => $profile,
			)
		);
		$settings['pretty_filters'] = unserialize($settings['pretty_filters']);

		if (isset($_REQUEST['pretty_cache']))
			wesql::query('
				TRUNCATE {db_prefix}pretty_urls_cache',
				array()
			);

		// Update the filters too
		loadSource('Subs-PrettyUrls');
		pretty_update_filters();

		redirectexit('action=admin;area=featuresettings;sa=pretty');
	}

	$context['pretty']['settings'] = array(
		'cache' => !empty($settings['pretty_enable_cache']) ? $settings['pretty_enable_cache'] : 0,
		'index' => !empty($settings['pretty_remove_index']) ? $settings['pretty_remove_index'] : 0,
	);
}

// This is a special function for handling plugins that want to create a simple one-page configuration area through the XML manifest.
function ModifySettingsPageHandler($return_config = false, $plugin_id = null)
{
	global $context, $txt, $settings, $admin_areas;

	// We can either call it with a supplied plugin id, or we can go attempt to match it from the URL.
	if ($plugin_id === null && !empty($_GET['area']) && !empty($admin_areas['plugins']['areas'][$_GET['area']]['plugin_id']))
		$plugin_id = $admin_areas['plugins']['areas'][$_GET['area']]['plugin_id'];

	if (empty($plugin_id) || empty($context['plugins_dir'][$plugin_id]) || !file_exists($context['plugins_dir'][$plugin_id] . '/plugin-info.xml'))
		redirectexit('action=admin');

	$manifest = safe_sxml_load($context['plugins_dir'][$plugin_id] . '/plugin-info.xml');
	if (empty($manifest->{'settings-page'}))
		redirectexit('action=admin');

	// First, attempt to load any language files.
	foreach ($manifest->{'settings-page'}->language as $lang)
		if (!empty($lang['file']))
			loadPluginLanguage($plugin_id, (string) $lang['file']);

	// Set up titles for things like the admin search.
	$admin_cache = unserialize($settings['plugins_admin']);
	$return_area = $admin_cache[$plugin_id]['area'];
	$context['settings_title'] = $context['page_title'] = $admin_cache[$plugin_id]['name'];

	// Now go through the rest of the manifest.
	$config_vars = array();
	$elements = $manifest->{'settings-page'}->children();
	foreach ($elements as $element)
	{
		$item = $element->getName();
		$name = !empty($element['name']) ? (string) $element['name'] : '';
		if (empty($name) && $item != 'hr')
			continue;
		$new_item = array();
		switch ($item)
		{
			case 'desc':
			case 'title':
			case 'check':
			case 'yesno':
			case 'email':
			case 'password':
			case 'bbc':
			case 'float':
			case 'boards':
				$new_item = array($item, $name);
				break;
			case 'text':
			case 'large_text':
				$array = array($item, $name);
				if (!empty($element['size']))
					$array['size'] = (string) $element['size'];
				$new_item = $array;
				break;
			case 'select':
			case 'multi_select':
				$array = array($item, $name, array());
				foreach ($element->option as $opt)
				{
					if (!empty($opt['name']) && isset($opt['value']))
					{
						$n = (string) $opt['name'];
						$array[2][(string) $opt['value']] = isset($txt[$n]) ? $txt[$n] : $n;
					}
				}
				if (!empty($array[2]))
					$new_item = $array;
				break;
			case 'int':
				$array = array($item, $name);
				foreach (array('step', 'min', 'max', 'size') as $attr)
					if (isset($element[$attr]))
						$array[$attr] = (int) $element[$attr];
				$new_item = $array;
				break;
			case 'percent':
				$array = array($item, $name);
				$new_item = $array;
				break;
			case 'permissions':
				$array = array($item, $name);
				if (!empty($element['noguests']) && $element['noguests'] == 'yes')
					$array['exclude'] = array(-1);
				$new_item = $array;
				break;
			case 'literal':
				$new_item = isset($txt[$name]) ? $txt[$name] : $name;
				break;
			case 'hr':
				$config_vars[] = ''; // This would ordinarily fall through the next test we do.
				break;
			// We already did language, just to clarify that we specifically do not want to do anything here with it.
			case 'language':
			default:
				break;
		}

		if (!empty($new_item))
		{
			if (isset($txt[$name . '_subtext']))
				$new_item['subtext'] = $txt[$name . '_subtext'];
			$config_vars[] = $new_item;
		}
	}

	if ($return_config)
		return $config_vars;

	loadSource('ManageServer');

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		saveSettings($config_vars);
		redirectexit('action=admin;area=' . $return_area);
	}

	$context['post_url'] = '<URL>?action=admin;area=' . $return_area . ';save';
	wetem::load('show_settings');
	prepareDBSettingContext($config_vars);
}
