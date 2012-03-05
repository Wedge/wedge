<?php
/**
 * Wedge
 *
 * Common administration settings are declared and managed in this file.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
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
	global $context, $txt;

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
	global $context, $txt, $scripturl, $theme;

	$context['page_title'] = $txt['settings_title'];

	$subActions = array(
		'basic' => 'ModifyBasicSettings',
		'pretty' => 'ModifyPrettyURLs',
	);

	loadGeneralSettingParameters($subActions, 'basic');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['settings_title'],
		'help' => 'featuresettings',
		'description' => sprintf($txt['settings_desc'], $theme['theme_id'], $context['session_query'], $scripturl),
		'tabs' => array(
			'basic' => array(
			),
			'pretty' => array(
				'description' => $txt['pretty_urls_desc'],
			),
		),
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

function ModifyBasicSettings($return_config = false)
{
	global $txt, $scripturl, $context;

	$config_vars = array(
			// Number formatting, timezones.
			array('select', 'todayMod', array($txt['today_disabled'], $txt['today_only'], $txt['yesterday_today'])),
		'',
			// Statistics.
			array('check', 'trackStats'),
			array('check', 'hitStats'),
		'',
			// Option-ish things... miscellaneous sorta.
			array('check', 'disallow_sendBody'),
	);

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		saveDBSettings($config_vars);

		writeLog();
		redirectexit('action=admin;area=featuresettings;sa=basic');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=basic';
	$context['settings_title'] = $txt['mods_cat_features'];

	prepareDBSettingContext($config_vars);
}

// Moderation type settings - although there are fewer than we have you believe ;)
function ModifyWarningSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings;

	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	// Will need the utility functions from here.
	loadSource('ManageServer');

	$config_vars = array(
			// Warning system?
			array('int', 'warning_watch', 'help' => 'warning_enable', 'min' => 0, 'max' => 100),
			'moderate' => array('int', 'warning_moderate', 'min' => 0, 'max' => 100),
			array('int', 'warning_mute', 'min' => 0, 'max' => 100),
		'',
			'rem1' => array('int', 'user_limit', 'min' => 0, 'max' => 100),
			'rem2' => array('int', 'warning_decrement', 'min' => 0, 'max' => 100),
			array('select', 'warning_show', array($txt['setting_warning_show_mods'], $txt['setting_warning_show_user'], $txt['setting_warning_show_all'])),
	);

	if ($return_config)
		return $config_vars;

	// Cannot use moderation if post moderation is not enabled.
	if (!$settings['postmod_active'])
		unset($config_vars['moderate']);

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Sadly, we can't use the normal validation routine here because we're doing a fun combination.
		$_POST['warning_watch'] = max(min($_POST['warning_watch'], 100), 0);
		$_POST['warning_moderate'] = $settings['postmod_active'] ? min($_POST['warning_moderate'], 100) : 0;
		$_POST['warning_mute'] = max(min($_POST['warning_mute'], 100), 0);

		// Fix the warning setting array!
		$_POST['warning_settings'] = min(100, (int) $_POST['user_limit']) . ',' . min(100, (int) $_POST['warning_decrement']);
		$save_vars = $config_vars;
		$save_vars[] = array('text', 'warning_settings');
		unset($save_vars['rem1'], $save_vars['rem2']);

		saveDBSettings($save_vars);
		redirectexit('action=admin;area=warnings');
	}

	// We actually store lots of these together - for efficiency.
	list ($settings['user_limit'], $settings['warning_decrement']) = explode(',', $settings['warning_settings']);

	$context['post_url'] = $scripturl . '?action=admin;area=warnings;save';
	$context['page_title'] = $context['settings_title'] = $txt['warning_title'];

	wetem::load('show_settings');
	prepareDBSettingContext($config_vars);
}

// Let's try keep the spam to a minimum ah Thantos?
function ModifySpamSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings;

	isAllowedTo('admin_forum');

	loadLanguage('Help');
	loadLanguage('ManageSettings');

	loadSource('ManageServer');

	// Generate a sample registration image.
	$context['verification_image_href'] = $scripturl . '?action=verificationcode;rand=' . md5(mt_rand());

	$config_vars = array(
			array('desc', 'antispam_Settings_desc'),
			array('check', 'reg_verification'),
			array('check', 'search_enable_captcha'),
			// This, my friend, is a cheat :p
			'guest_verify' => array('check', 'guests_require_captcha', 'subtext' => $txt['setting_guests_require_captcha_desc']),
			array('int', 'posts_require_captcha', 'subtext' => $txt['posts_require_captcha_desc'], 'onchange' => 'if (this.value > 0) $(\'#guests_require_captcha\').attr({ checked: true, disabled: true }); else $(\'#guests_require_captcha\').attr(\'disabled\', false);'),
			array('check', 'guests_report_require_captcha'),
			// Visual verification.
			array('title', 'configure_captcha'),
			array('desc', 'configure_captcha_desc'),
			array('check', 'use_captcha_images'),
			array('check', 'use_animated_captcha', 'subtext' => $txt['use_animated_captcha_desc']),
			// Clever Thomas, who is looking sheepy now? Not I, the mighty sword swinger did say.
			array('title', 'setup_verification_questions'),
			array('desc', 'setup_verification_questions_desc'),
			array('int', 'qa_verification_number', 'subtext' => $txt['setting_qa_verification_number_desc']),
		'',
			array('callback', 'question_answer_list'),
	);

	call_hook('spam_settings', array(&$config_vars, &$return_config));

	if ($return_config)
		return $config_vars;

	// Load any question and answers!
	$context['question_answers'] = array();
	$request = wesql::query('
		SELECT id_comment, body AS question, recipient_name AS answer
		FROM {db_prefix}log_comments
		WHERE comment_type = {string:ver_test}',
		array(
			'ver_test' => 'ver_test',
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$context['question_answers'][$row['id_comment']] = array(
			'id' => $row['id_comment'],
			'question' => $row['question'],
			'answer' => $row['answer'],
		);
	}
	wesql::free_result($request);

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Hack in guest requiring verification!
		if (empty($_POST['posts_require_captcha']) && !empty($_POST['guests_require_captcha']))
			$_POST['posts_require_captcha'] = -1;

		// Handle verification questions.
		$questionInserts = array();
		$count_questions = 0;
		foreach ($_POST['question'] as $id => $question)
		{
			$question = trim(westr::htmlspecialchars($question, ENT_COMPAT));
			$answer = trim(westr::strtolower(westr::htmlspecialchars($_POST['answer'][$id], ENT_COMPAT)));

			// Already existed?
			if (isset($context['question_answers'][$id]))
			{
				$count_questions++;
				// Changed?
				if ($context['question_answers'][$id]['question'] != $question || $context['question_answers'][$id]['answer'] != $answer)
				{
					if ($question == '' || $answer == '')
					{
						wesql::query('
							DELETE FROM {db_prefix}log_comments
							WHERE comment_type = {string:ver_test}
								AND id_comment = {int:id}',
							array(
								'id' => $id,
								'ver_test' => 'ver_test',
							)
						);
						$count_questions--;
					}
					else
						wesql::query('
							UPDATE {db_prefix}log_comments
							SET body = {string:question}, recipient_name = {string:answer}
							WHERE comment_type = {string:ver_test}
								AND id_comment = {int:id}',
							array(
								'id' => $id,
								'ver_test' => 'ver_test',
								'question' => $question,
								'answer' => $answer,
							)
						);
				}
			}
			// It's so shiny and new!
			elseif ($question != '' && $answer != '')
			{
				$questionInserts[] = array(
					'comment_type' => 'ver_test',
					'body' => $question,
					'recipient_name' => $answer,
				);
			}
		}

		// Any questions to insert?
		if (!empty($questionInserts))
		{
			wesql::insert('',
				'{db_prefix}log_comments',
				array('comment_type' => 'string', 'body' => 'string-65535', 'recipient_name' => 'string-80'),
				$questionInserts,
				array('id_comment')
			);
			$count_questions++;
		}

		if (empty($count_questions) || $_POST['qa_verification_number'] > $count_questions)
			$_POST['qa_verification_number'] = $count_questions;

		// Now save.
		saveDBSettings($config_vars);

		cache_put_data('verificationQuestionIds', null, 300);

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
	$(\'#guests_require_captcha\').attr(\'disabled\', true);');

	$context['post_url'] = $scripturl . '?action=admin;area=antispam;save';
	$context['page_title'] = $context['settings_title'] = $txt['antispam_Settings'];

	wetem::load('show_settings');

	prepareDBSettingContext($config_vars);
}

function ModifyLogSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings;

	// Make sure we understand what's going on.
	loadLanguage('ManageSettings');

	$context['page_title'] = $txt['log_settings'];

	$config_vars = array(
			array('check', 'enableErrorLogging'),
			array('check', 'enableErrorQueryLogging'),
		'',
			array('check', 'log_enabled_moderate'),
			array('check', 'log_enabled_admin'),
			array('check', 'log_enabled_profile'),
			// Even do the pruning?
			array('title', 'logPruning'),
			// The array indexes are there so we can remove/change them before saving.
			'pruningOptions' => array('check', 'pruningOptions'),
		'',
			// Various logs that could be pruned.
			array('int', 'pruneErrorLog', 'postinput' => $txt['days_word']), // Error log.
			array('int', 'pruneModLog', 'postinput' => $txt['days_word']), // Moderation log.
			array('int', 'pruneReportLog', 'postinput' => $txt['days_word']), // Report to moderator log.
			array('int', 'pruneScheduledTaskLog', 'postinput' => $txt['days_word']), // Log of the scheduled tasks and how long they ran.
			array('int', 'pruneSpiderHitLog', 'postinput' => $txt['days_word']), // Log of the scheduled tasks and how long they ran.
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

		saveDBSettings($savevar);
		redirectexit('action=admin;area=logs;sa=settings');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=logs;save;sa=settings';
	$context['settings_title'] = $txt['log_settings'];
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

	if (!empty($settings['pm_enabled']))
		$config_vars = array_merge($config_vars, array(
			'',
			array('permissions', 'pm_read', 'exclude' => array(-1)),
			array('permissions', 'pm_send', 'exclude' => array(-1)),
			'',
			'pm1' => array('int', 'max_pm_recipients', 'min' => 0),
			'pm2' => array('int', 'pm_posts_verification', 'min' => 0),
			'pm3' => array('int', 'pm_posts_per_hour', 'min' => 0),
			'',
			array('check', 'masterSavePmDrafts'),
			array('permissions', 'save_pm_draft', 'exclude' => array(-1)),
			array('check', 'masterAutoSavePmDrafts'),
			array('permissions', 'auto_save_pm_draft', 'exclude' => array(-1)),
		));

	loadLanguage('ManageSettings');

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

		saveDBSettings($save_vars);

		writeLog();
		redirectexit('action=admin;area=pm');
	}

	$context['post_url'] = '<URL>?action=admin;area=pm;save';
	$context['page_title'] = $context['settings_title'] = $txt['admin_personal_messages'];

	// Hacky mess for PM settings
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
		$output = pretty_synchronize_topic_urls();
		$context['reset_output'] = $output . $txt['pretty_converted'];
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

	$manifest = simplexml_load_file($context['plugins_dir'][$plugin_id] . '/plugin-info.xml');
	if (empty($manifest->{'settings-page'}))
		redirectexit('action=admin');

	// First, attempt to load any language files.
	foreach ($manifest->{'settings-page'}->language as $lang)
		if (!empty($lang['file']))
			loadPluginLanguage($plugin_id, (string) $lang['file']);

	// Now go through the rest of the manifest.
	$config_vars = array();
	$elements = $manifest->{'settings-page'}->children();
	foreach ($elements as $element)
	{
		$item = $element->getName();
		$name = !empty($element['name']) ? (string) $element['name'] : '';
		if (empty($name) && $item != 'hr')
			continue;
		switch ($item)
		{
			case 'desc':
			case 'title':
			case 'check':
			case 'email':
			case 'password':
			case 'bbc':
			case 'float':
				$config_vars[] = array($item, $name);
				break;
			case 'text':
			case 'large-text':
				$array = array($item, $name);
				if (!empty($element['size']))
					$array['size'] = (string) $element['size'];
				$config_vars[] = $array;
				break;
			case 'select':
			case 'multi-select':
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
					$config_vars[] = $array;
				break;
			case 'int':
				$array = array($item, $name);
				foreach (array('step', 'min', 'max', 'size') as $attr)
					if (isset($element[$attr]))
						$array[$attr] = (int) $element[$attr];
				$config_vars[] = $array;
				break;
			case 'permissions':
				$array = array($item, $name);
				if (!empty($element['noguests']) && $element['noguests'] == 'yes')
					$array['exclude'] = array(-1);
				$config_vars[] = $array;
				break;
			case 'literal':
				$config_vars[] = isset($txt[$name]) ? $txt[$name] : $name;
				break;
			case 'hr':
				$config_vars[] = '';
				break;
			// We already did language, just to clarify that we specifically do not want to do anything here with it.
			case 'language':
			default:
				break;
		}
	}

	if ($return_config)
		return $config_vars;

	loadSource('ManageServer');
	$admin_cache = unserialize($settings['plugins_admin']);
	$return_area = $admin_cache[$plugin_id]['area'];

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		saveDBSettings($config_vars);
		redirectexit('action=admin;area=' . $return_area);
	}

	$context['post_url'] = '<URL>?action=admin;area=' . $return_area . ';save';
	$context['settings_title'] = $context['page_title'] = $admin_cache[$plugin_id]['name'];
	wetem::load('show_settings');
	prepareDBSettingContext($config_vars);
}

?>