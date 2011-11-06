<?php
/**
 * Wedge
 *
 * Common administration settings are declared and managed in this file.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
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

	void ModifySecuritySettings()
		// !!!

	void ModifyModSettings()
		// !!!

	void ModifyCoreFeatures()
		// !!!

	void ModifyBasicSettings()
		// !!!

	void ModifyModerationSettings()
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
	global $context, $txt, $scripturl, $modSettings, $settings;

	$context['page_title'] = $txt['modSettings_title'];

	$subActions = array(
		'basic' => 'ModifyBasicSettings',
		'pretty' => 'ModifyPrettyURLs',
	);

	loadGeneralSettingParameters($subActions, 'basic');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['modSettings_title'],
		'help' => 'featuresettings',
		'description' => sprintf($txt['modSettings_desc'], $settings['theme_id'], $context['session_query'], $scripturl),
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

// This function passes control through to the relevant security tab.
function ModifySecuritySettings()
{
	global $context, $txt, $scripturl, $modSettings, $settings;

	$context['page_title'] = $txt['admin_security_moderation'];

	$subActions = array(
		'spam' => 'ModifySpamSettings',
		'moderation' => 'ModifyModerationSettings',
	);

	loadGeneralSettingParameters($subActions, 'spam');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['admin_security_moderation'],
		'help' => 'securitysettings',
		'description' => $txt['security_settings_desc'],
		'tabs' => array(
			'spam' => array(
				'description' => $txt['antispam_Settings_desc'],
			),
			'moderation' => array(
			),
		),
	);

	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

// This my friend, is for all the mod authors out there. They're like builders without the ass crack - with the possible exception of... /cut short
function ModifyModSettings()
{
	global $context, $txt, $scripturl, $modSettings, $settings;

	$context['page_title'] = $txt['admin_modifications'];

	$subActions = array(
		'general' => 'ModifyGeneralModSettings',
		// Mod authors, once again, if you have a whole section to add do it AFTER this line, and keep a comma at the end.
	);

	// Make it easier for mods to add new areas.
	call_hook('modify_modifications', array(&$subActions));

	loadGeneralSettingParameters($subActions, 'general');

	// Load up all the tabs...
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['admin_modifications'],
		'help' => 'modsettings',
		'description' => $txt['modification_settings_desc'],
		'tabs' => array(
			'general' => array(
			),
		),
	);


	// Call the right function for this sub-acton.
	$subActions[$_REQUEST['sa']]();
}

// This is an overall control panel enabling/disabling lots of Wedge's key feature components.
function ModifyCoreFeatures($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $modSettings;

	/* This is an array of all the features that can be enabled/disabled - each option can have the following:
		title		- Text title of this item (If standard string does not exist).
		desc		- Description of this feature (If standard string does not exist).
		image		- Custom image to show next to feature.
		settings	- Array of settings to change (For each name => value) on enable - reverse is done for disable. If > 1 will not change value if set.
		setting_callback- Function that returns an array of settings to save - takes one parameter which is value for this feature.
		save_callback	- Function called on save, takes state as parameter.
	*/
	$core_features = array(
		// Media area
		'm' => array(
			'url' => 'action=admin;area=media',
			'setting' => 'media_enabled',
		),
		// Post Moderation
		'pm' => array(
			'url' => 'action=admin;area=permissions;sa=postmod',
			'setting' => 'postmod_enabled',
			// Can't use warning post moderation if disabled!
			'setting_callback' => create_function('$value', '
				if ($value)
					return array();
				loadSource(\'PostModeration\');
				approveAllData();
				return array(\'warning_moderate\' => 0);
			'),
		),
		// Search engines
		'sp' => array(
			'url' => 'action=admin;area=sengines',
			'setting' => 'spider_mode',
			'setting_callback' => create_function('$value', '
				// Turn off the spider group if disabling.
				if (!$value)
					return array(\'spider_group\' => 0, \'show_spider_online\' => 0);
			'),
			'on_save' => create_function('', '
				loadSource(\'ManageSearchEngines\');
				recacheSpiderNames();
			'),
		),
	);

	// Anyone who would like to add a core feature?
	call_hook('core_features', array(&$core_features));

	// Are we getting info for the help section.
	if ($return_config)
	{
		$return_data = array();
		foreach ($core_features as $id => $data)
			$return_data[] = array('switch', isset($data['title']) ? $data['title'] : $txt['core_settings_item_' . $id]);
		return $return_data;
	}

	loadGeneralSettingParameters();

	// Are we saving?
	if (isset($_POST['save']))
	{
		checkSession();

		$setting_changes = array();

		// Are we using the javascript stuff or radios to submit?
		$post_var_prefix = empty($_POST['js_worked']) ? 'feature_plain_' : 'feature_';

		// Cycle each feature and change things as required!
		foreach ($core_features as $id => $feature)
		{
			// Setting values to change?
			if (isset($feature['setting']))
				$setting_changes[$feature['setting']] = !empty($_POST[$post_var_prefix . $id]) ? 1 : 0;

			// Is there a call back for settings?
			if (isset($feature['setting_callback']))
			{
				$returned_settings = $feature['setting_callback'](!empty($_POST[$post_var_prefix . $id]));
				if (!empty($returned_settings))
					$setting_changes = array_merge($setting_changes, $returned_settings);
			}

			// Standard save callback?
			if (isset($feature['on_save']))
				$feature['on_save']();
		}

		// Make any setting changes!
		updateSettings($setting_changes);

		// Any post save things?
		foreach ($core_features as $id => $feature)
			if (isset($feature['save_callback'])) // Standard save callback?
				$feature['save_callback'](!empty($_POST[$post_var_prefix . $id]));

		redirectexit('action=admin;area=corefeatures;' . $context['session_query']);
	}

	// Put them in context.
	$context['features'] = array();
	foreach ($core_features as $id => $feature)
		$context['features'][$id] = array(
			'title' => isset($feature['title']) ? $feature['title'] : $txt['core_settings_item_' . $id],
			'desc' => isset($feature['desc']) ? $feature['desc'] : $txt['core_settings_item_' . $id . '_desc'],
			'enabled' => isset($feature['setting']) && !empty($modSettings[$feature['setting']]),
			'url' => !empty($feature['url']) ? $scripturl . '?' . $feature['url'] . ';' . $context['session_query'] : '',
		);

	// Are they a new user?
	$context['is_new_install'] = !isset($modSettings['media_enabled']);
	$context['force_disable_tabs'] = $context['is_new_install'];
	// Don't show them this twice!
	if ($context['is_new_install'])
		updateSettings(array('media_enabled' => 1));

	wetem::load('core_features');
	$context['page_title'] = $txt['core_settings_title'];
}

function ModifyBasicSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $modSettings;

	$config_vars = array(
			// Big Options... polls, sticky, bbc....
			array('select', 'pollMode', array($txt['disable_polls'], $txt['enable_polls'], $txt['polls_as_topics'])),
		'',
			// Basic stuff, titles, flash, permissions...
			array('check', 'allow_guestAccess'),
		'',
			// Number formatting, timezones.
			array('float', 'time_offset'),
			'default_timezone' => array('select', 'default_timezone', array()),
			array('select', 'todayMod', array($txt['today_disabled'], $txt['today_only'], $txt['yesterday_today'])),
		'',
			// Who's online?
			array('check', 'who_enabled'),
			array('int', 'lastActive'),
		'',
			// Statistics.
			array('check', 'trackStats'),
			array('check', 'hitStats'),
		'',
			// Option-ish things... miscellaneous sorta.
			array('check', 'allow_disableAnnounce'),
			array('check', 'disallow_sendBody'),
	);

	// PHP can give us a list of all the time zones. Yay.
	$all_zones = timezone_identifiers_list();

	// Make sure we set the value to the same as the printed value.
	$useful_regions = array_flip(array('America', 'Antartica', 'Arctic', 'Asia', 'Atlantic', 'Europe', 'Indian', 'Pacific'));
	foreach ($all_zones as $zone)
	{
		if (strpos($zone, '/') === false)
			continue;
		list ($region, $country) = explode('/', $zone, 2);
		if (isset($useful_regions[$region]))
			$config_vars['default_timezone'][2][$zone] = $zone;
	}

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		// Prevent absurd boundaries here - make it a day tops.
		if (isset($_POST['lastActive']))
			$_POST['lastActive'] = min((int) $_POST['lastActive'], 1440);

		saveDBSettings($config_vars);

		writeLog();
		redirectexit('action=admin;area=featuresettings;sa=basic');
	}

	$context['post_url'] = $scripturl . '?action=admin;area=featuresettings;save;sa=basic';
	$context['settings_title'] = $txt['mods_cat_features'];

	prepareDBSettingContext($config_vars);
}

// Moderation type settings - although there are fewer than we have you believe ;)
function ModifyModerationSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $modSettings;

	$config_vars = array(
			// Warning system?
			array('int', 'warning_watch', 'help' => 'warning_enable'),
			'moderate' => array('int', 'warning_moderate'),
			array('int', 'warning_mute'),
		'',
			'rem1' => array('int', 'user_limit'),
			'rem2' => array('int', 'warning_decrement'),
			array('select', 'warning_show', array($txt['setting_warning_show_mods'], $txt['setting_warning_show_user'], $txt['setting_warning_show_all'])),
	);

	if ($return_config)
		return $config_vars;

	// Cannot use moderation if post moderation is not enabled.
	if (!$modSettings['postmod_active'])
		unset($config_vars['moderate']);

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		$_POST['warning_watch'] = min($_POST['warning_watch'], 100);
		$_POST['warning_moderate'] = $modSettings['postmod_active'] ? min($_POST['warning_moderate'], 100) : 0;
		$_POST['warning_mute'] = min($_POST['warning_mute'], 100);

		// Fix the warning setting array!
		$_POST['warning_settings'] = min(100, (int) $_POST['user_limit']) . ',' . min(100, (int) $_POST['warning_decrement']);
		$save_vars = $config_vars;
		$save_vars[] = array('text', 'warning_settings');
		unset($save_vars['rem1'], $save_vars['rem2']);

		saveDBSettings($save_vars);
		redirectexit('action=admin;area=securitysettings;sa=moderation');
	}

	// We actually store lots of these together - for efficiency.
	list ($modSettings['user_limit'], $modSettings['warning_decrement']) = explode(',', $modSettings['warning_settings']);

	$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=moderation';
	$context['settings_title'] = $txt['moderation_settings'];

	prepareDBSettingContext($config_vars);
}

// Let's try keep the spam to a minimum ah Thantos?
function ModifySpamSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $modSettings;

	// Generate a sample registration image.
	$context['verification_image_href'] = $scripturl . '?action=verificationcode;rand=' . md5(mt_rand());

	$config_vars = array(
			array('check', 'reg_verification'),
			array('check', 'search_enable_captcha'),
			// This, my friend, is a cheat :p
			'guest_verify' => array('check', 'guests_require_captcha', 'subtext' => $txt['setting_guests_require_captcha_desc']),
			array('int', 'posts_require_captcha', 'subtext' => $txt['posts_require_captcha_desc'], 'onchange' => 'if (this.value > 0) $(\'#guests_require_captcha\').attr({ checked: true, disabled: true }); else $(\'#guests_require_captcha\').attr(\'disabled\', false);'),
			array('check', 'guests_report_require_captcha'),
		'',
			// PM Settings
			'pm1' => array('int', 'max_pm_recipients'),
			'pm2' => array('int', 'pm_posts_verification'),
			'pm3' => array('int', 'pm_posts_per_hour'),
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

		// Fix PM settings.
		$_POST['pm_spam_settings'] = (int) $_POST['max_pm_recipients'] . ',' . (int) $_POST['pm_posts_verification'] . ',' . (int) $_POST['pm_posts_per_hour'];

		// Hack in guest requiring verification!
		if (empty($_POST['posts_require_captcha']) && !empty($_POST['guests_require_captcha']))
			$_POST['posts_require_captcha'] = -1;

		$save_vars = $config_vars;
		unset($save_vars['pm1'], $save_vars['pm2'], $save_vars['pm3'], $save_vars['guest_verify']);

		$save_vars[] = array('text', 'pm_spam_settings');

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
		saveDBSettings($save_vars);

		cache_put_data('verificationQuestionIds', null, 300);

		redirectexit('action=admin;area=securitysettings;sa=spam');
	}

	$character_range = array_merge(range('A', 'H'), array('K', 'M', 'N', 'P', 'R'), range('T', 'Y'));
	$_SESSION['visual_verification_code'] = '';
	for ($i = 0; $i < 6; $i++)
		$_SESSION['visual_verification_code'] .= $character_range[array_rand($character_range)];

	// Hack for PM spam settings.
	list ($modSettings['max_pm_recipients'], $modSettings['pm_posts_verification'], $modSettings['pm_posts_per_hour']) = explode(',', $modSettings['pm_spam_settings']);

	// Hack for guests requiring verification.
	$modSettings['guests_require_captcha'] = !empty($modSettings['posts_require_captcha']);
	$modSettings['posts_require_captcha'] = !isset($modSettings['posts_require_captcha']) || $modSettings['posts_require_captcha'] == -1 ? 0 : $modSettings['posts_require_captcha'];

	// Some minor javascript for the guest post setting.
	if ($modSettings['posts_require_captcha'])
		add_js('
	$(\'#guests_require_captcha\').attr(\'disabled\', true);');

	$context['post_url'] = $scripturl . '?action=admin;area=securitysettings;save;sa=spam';
	$context['settings_title'] = $txt['antispam_Settings'];

	prepareDBSettingContext($config_vars);
}

function ModifyLogSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $modSettings;

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
	if (!empty($modSettings['pruningOptions']))
		@list ($modSettings['pruneErrorLog'], $modSettings['pruneModLog'], $modSettings['pruneReportLog'], $modSettings['pruneScheduledTaskLog'], $modSettings['pruneSpiderHitLog']) = explode(',', $modSettings['pruningOptions']);
	else
		$modSettings['pruneErrorLog'] = $modSettings['pruneModLog'] = $modSettings['pruneReportLog'] = $modSettings['pruneScheduledTaskLog'] = $modSettings['pruneSpiderHitLog'] = 0;

	prepareDBSettingContext($config_vars);
}

/**
 *	To Plugin Authors:
 *	You may add your plugin settings area here.
 *	Do not edit this file, it could get messy. Simply call, at install time:
 *
 *		add_hook('plugin_settings', 'my_function', 'my_source_file');
 *
 *	Where my_function() will simply add your settings to $config_vars (which is provided to you for editing.)
 *	The 'true' parameter allows for the call to be registered and handled without any further action on your part.
 */
function ModifyGeneralModSettings($return_config = false)
{
	global $txt, $scripturl, $context, $settings, $modSettings;

	$config_vars = array();

	call_hook('plugin_settings', array(&$config_vars));

	if ($return_config)
		return $config_vars;

	$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=general';
	$context['settings_title'] = $txt['mods_cat_modifications_misc'];

	// No removing this line you, dirty unwashed plugin authors. :p
	if (empty($config_vars))
	{
		$context['settings_save_dont_show'] = true;
		$context['settings_message'] = '<p class="centertext">' . $txt['modification_no_misc_settings'] . '</p>';

		return prepareDBSettingContext($config_vars);
	}

	// Saving?
	if (isset($_GET['save']))
	{
		checkSession();

		$save_vars = $config_vars;

		// This line is to help mod authors do a search/add after if you want to add something here. Keyword: FOOT TAPPING SUCKS!
		saveDBSettings($save_vars);

		// This line is to help mod authors do a search/add after if you want to add something here. Keyword: I LOVE TEA!
		redirectexit('action=admin;area=modsettings;sa=general');
	}

	// This line is to help mod authors do a search/add after if you want to add something here. Keyword: RED INK IS FOR TEACHERS AND THOSE WHO LIKE PAIN!
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
	global $context, $modSettings, $settings, $txt;

	// For the administrative search function not to get upset.
	if ($return_config)
		return array();

	wetem::load('pretty_urls');
	$context['page_title'] = $txt['admin_pretty_urls'];

	// The action filter should always be last, because it's generic.
	if (isset($modSettings['pretty_filters']['actions']))
	{
		$action = $modSettings['pretty_filters']['actions'];
		unset($modSettings['pretty_filters']['actions']);
		$modSettings['pretty_filters']['actions'] = $action;
	}
	$context['pretty']['filters'] = $modSettings['pretty_filters'];

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
		foreach ($modSettings['pretty_filters'] as $id => &$filter)
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
				'pretty_filters' => serialize($modSettings['pretty_filters']),
				'pretty_prefix_action' => $action,
				'pretty_prefix_profile' => $profile,
			)
		);
		$modSettings['pretty_filters'] = unserialize($modSettings['pretty_filters']);

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
		'cache' => !empty($modSettings['pretty_enable_cache']) ? $modSettings['pretty_enable_cache'] : 0,
		'index' => !empty($modSettings['pretty_remove_index']) ? $modSettings['pretty_remove_index'] : 0,
	);
}

?>