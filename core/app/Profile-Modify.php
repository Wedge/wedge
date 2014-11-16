<?php
/**
 * Handles gathering data on, and allowing editing of, user profiles - can include preferences too.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	void loadProfileFields(bool force_reload = false)
		// !!!

	void setupProfileContext(array fields)
		// !!!

	void saveProfileFields()
		// !!!

	void saveProfileChanges(array &profile_variables, int id_member)
		// !!!

	void makeThemeChanges(int id_member)
		// !!!

	void makeNotificationChanges(int id_member)
		// !!!

	void makeCustomFieldChanges(int id_member, string area, bool sanitize = true)
		// !!!

	void editContacts(int id_member)
		// !!!

	void editBuddies(int id_member)
		// !!!

	void editIgnoreList(int id_member)
		// !!!

	void account(int id_member)
		// !!!

	void forumProfile(int id_member)
		// !!!

	void pmprefs(int id_member)
		// !!!

	array getAvatars(string directory, int level)
		// !!!

	void options(int id_member)
		// !!!

	void notification(int id_member)
		// !!!

	int list_getTopicNotificationCount(int memID)
		// !!!

	array list_getTopicNotifications(int start, int items_per_page, string sort, int memID)
		// !!!

	array list_getBoardNotifications(int start, int items_per_page, string sort, int memID)
		// !!!

	void loadThemeOptions(int id_member)
		// !!!

	void ignoreboards(int id_member)
		// !!!

	bool profileLoadLanguages()
		// !!!

	bool profileLoadGroups()
		// !!!

	bool profileLoadSignatureData()
		// !!!

	bool profileLoadAvatarData()
		// !!!

	bool profileSaveGroups(mixed &value)
		// !!!

	mixed profileSaveAvatarData(array &value)
		// !!!

	mixed profileValidateSignature(mixed &value)
		// !!!

	bool profileValidateEmail(string email, int id_member = none)
		// !!!

	void profileReloadUser()
		// !!!

	void profileSendActivation()
		// !!!

	void groupMembership(int id_member)
		// !!!

	mixed groupMembership2(array profile_vars, int id_member)
		// !!!

	Adding new fields to the profile:
	---------------------------------------------------------------------------
		// !!!
*/

// This defines every profile field known to man.
function loadProfileFields($force_reload = false)
{
	global $context, $profile_fields, $txt, $settings, $cur_profile;

	// Don't load this twice!
	if (!empty($profile_fields) && !$force_reload)
		return;

	/* This horrific array defines all the profile fields in the whole world!
		In general each "field" has one array - the key of which is the database column name associated with said field. Each item
		can have the following attributes:

				string $type:				The type of field this is - valid types are:
					- callback:				This is a field which has its own callback mechanism for templating.
					- check:				A simple checkbox.
					- hidden:				This doesn't have any visual aspects but may have some validity.
					- password:				A password box.
					- select:				A select box.
					- text:					A string of some description.

				string $label:				The label for this item - default will be $txt[$key] if this isn't set.
				string $subtext:			The subtext (Small label) for this item.
				int $size:					Optional size for a text area.
				array $input_attr:			An array of text strings to be added to the input box for this item.
				string $value:				The value of the item. If not set $cur_profile[$key] is assumed.
				string $permission:			Permission required for this item (Excluded _any/_own subfix which is applied automatically).
				function $input_validate:	A runtime function which validates the element before going to the database. It is passed
												the relevant $_POST element if it exists and should be treated like a reference.
					Return types:
						- true:				Element can be stored.
						- false:			Skip this element.
						- a text string:	An error occurred - this is the error message.

				function $preload:			A function that is used to load data required for this element to be displayed.
												Must return true to be displayed at all.

				string $cast_type:			If set casts the element to a certain type. Valid types (bool, int, float).
				string $save_key:			If the index of this element isn't the database column name it can be overriden
												with this string.
				bool $is_dummy:				If set then nothing is acted upon for this element.
				bool $enabled:				A test to determine whether this is even available - if not is unset.
				string $link_with:			Key which links this field to an overall set.

		Note that all elements that have a custom input_validate must ensure they set the value of $cur_profile correct to enable
		the changes to be displayed correctly on submit of the form.

	*/

	$profile_fields = array(
		'avatar_choice' => array(
			'type' => 'callback',
			'callback_func' => 'avatar_select',
			// This handles the permissions too.
			'preload' => 'profileLoadAvatarData',
			'input_validate' => 'profileSaveAvatarData',
			'save_key' => 'avatar',
		),
		'bday1' => array(
			'type' => 'callback',
			'callback_func' => 'birthdate',
			'permission' => 'profile_extra',
			'preload' => function () {
				global $cur_profile, $context;

				// Split up the birthdate....
				list ($uyear, $umonth, $uday) = explode('-', empty($cur_profile['birthdate']) || $cur_profile['birthdate'] == '0001-01-01' ? '0000-00-00' : $cur_profile['birthdate']);
				$context['member']['birth_date'] = array(
					'year' => $uyear == '0004' ? '0000' : $uyear,
					'month' => $umonth,
					'day' => $uday,
				);

				return true;
			},
			'input_validate' => function (&$value) {
				global $profile_vars, $cur_profile;

				if (isset($_POST['bday2'], $_POST['bday3']) && $value > 0 && $_POST['bday2'] > 0)
				{
					// Set to blank?
					if ((int) $_POST['bday3'] == 1 && (int) $_POST['bday2'] == 1 && (int) $value == 1)
						$value = '0001-01-01';
					else
						$value = checkdate($value, $_POST['bday2'], $_POST['bday3'] < 4 ? 4 : $_POST['bday3']) ? sprintf('%04d-%02d-%02d', $_POST['bday3'] < 4 ? 4 : $_POST['bday3'], $_POST['bday1'], $_POST['bday2']) : '0001-01-01';
				}
				else
					$value = '0001-01-01';

				$profile_vars['birthdate'] = $value;
				$cur_profile['birthdate'] = $value;
				return false;
			},
		),
		// Setting the birthdate the old style way?
		'birthdate' => array(
			'type' => 'hidden',
			'permission' => 'profile_extra',
			'input_validate' => function (&$value) {
				global $cur_profile;
				// !!! Should we check for this year and tell them they made a mistake :P? (based on coppa at least?)
				if (preg_match('/(\d{4})[., -](\d{2})[., -](\d{2})/', $value, $dates) === 1)
				{
					$value = checkdate($dates[2], $dates[3], $dates[1] < 4 ? 4 : $dates[1]) ? sprintf('%04d-%02d-%02d', $dates[1] < 4 ? 4 : $dates[1], $dates[2], $dates[3]) : '0001-01-01';
					return true;
				}
				else
				{
					$value = empty($cur_profile['birthdate']) ? '0001-01-01' : $cur_profile['birthdate'];
					return false;
				}
			},
		),
		'date_registered' => array(
			'type' => 'text',
			'value' => empty($cur_profile['date_registered']) ? $txt['not_applicable'] : strftime('%Y-%m-%d', $cur_profile['date_registered'] + (we::$user['time_offset'] + $settings['time_offset']) * 3600),
			'label' => $txt['date_registered'],
			'log_change' => true,
			'permission' => 'moderate_forum',
			'input_validate' => function (&$value) {
				global $txt, $settings, $cur_profile;

				// Bad date! Go try again - please?
				if (($value = strtotime($value)) === -1)
				{
					$value = $cur_profile['date_registered'];
					return $txt['invalid_registration'] . ' ' . strftime('%d %b %Y ' . (strpos(we::$user['time_format'], '%H') !== false ? '%I:%M:%S %p' : '%H:%M:%S'), forum_time(false));
				}
				// As long as it doesn't equal "N/A"...
				elseif ($value != $txt['not_applicable'] && $value != strtotime(strftime('%Y-%m-%d', $cur_profile['date_registered'] + (we::$user['time_offset'] + $settings['time_offset']) * 3600)))
					$value = $value - (we::$user['time_offset'] + $settings['time_offset']) * 3600;
				else
					$value = $cur_profile['date_registered'];

				return true;
			},
		),
		'email_address' => array(
			'type' => 'email',
			'label' => $txt['email'],
			'subtext' => $txt['valid_email'],
			'log_change' => true,
			'permission' => 'profile_identity',
			'input_validate' => function (&$value) {
				global $context, $old_profile, $profile_vars, $settings;

				if (strtolower($value) == strtolower($old_profile['email_address']))
					return false;

				$isValid = profileValidateEmail($value, $context['id_member']);

				// Do they need to revalidate? If so schedule the function!
				if ($isValid === true && !empty($settings['send_validation_onChange']) && !allowedTo('moderate_forum'))
				{
					loadSource('Subs-Members');
					$profile_vars['validation_code'] = generateValidationCode();
					$profile_vars['is_activated'] = 2;
					$context['profile_execute_on_save'][] = 'profileSendActivation';
					unset($context['profile_execute_on_save']['reload_user']);
				}

				return $isValid;
			},
		),
		'gender' => array(
			'type' => 'select',
			'cast_type' => 'int',
			'options' => function () { global $txt; return array(0 => '', 1 => $txt['male'], 2 => $txt['female']); },
			'label' => $txt['gender'],
			'permission' => 'profile_extra',
			'class' => 'fixed',
		),
		'hide_email' => array(
			'type' => 'check',
			'value' => empty($cur_profile['hide_email']) ? true : false,
			'label' => $txt['allow_user_email'],
			'permission' => 'profile_identity',
			'input_validate' => function (&$value) { $value = $value == 0 ? 1 : 0; return true; },
		),
		// Selecting group membership is a complicated one so we treat it separate!
		'id_group' => array(
			'type' => 'callback',
			'callback_func' => 'group_manage',
			'permission' => 'manage_membergroups',
			'preload' => 'profileLoadGroups',
			'log_change' => true,
			'input_validate' => 'profileSaveGroups',
		),
		'lngfile' => array(
			'type' => 'select',
			'options' => function () { global $context; return $context['profile_languages']; },
			'label' => $txt['preferred_language'],
			'permission' => 'profile_identity',
			'privacy' => 'language',
			'preload' => 'profileLoadLanguages',
			'enabled' => !empty($settings['userLanguage']),
			'value' => empty($cur_profile['lngfile']) ? $settings['language'] : $cur_profile['lngfile'],
			'input_validate' => function (&$value) {
				global $context, $cur_profile;

				// Load the languages.
				profileLoadLanguages();

				if (isset($context['profile_languages'][$value]))
				{
					if (we::$user['is_owner'] && empty($context['password_auth_failed']))
						$_SESSION['language'] = $value;
					return true;
				}
				else
				{
					$value = $cur_profile['lngfile'];
					return false;
				}
			},
		),
		'location' => array(
			'type' => 'text',
			'label' => $txt['location'],
			'log_change' => true,
			'size' => 50,
			'permission' => 'profile_extra',
			'privacy' => 'location',
		),
		// The username is not always editable - so adjust it as such.
		'member_name' => array(
			'type' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? 'text' : 'label',
			'label' => $txt['username'],
			'subtext' => allowedTo('admin_forum') && !isset($_GET['changeusername']) ? '(<a href="<URL>?action=profile;u=' . $context['id_member'] . ';area=account;changeusername" style="font-style: italic">' . $txt['username_change'] . '</a>)' : '',
			'log_change' => true,
			'permission' => 'profile_identity',
			'prehtml' => allowedTo('admin_forum') && isset($_GET['changeusername']) ? '<div class="alert">' . $txt['username_warning'] . '</div>' : '',
			'input_validate' => function (&$value) {
				global $context, $cur_profile;

				if (allowedTo('admin_forum'))
				{
					// We'll need this...
					loadSource('Subs-Auth');

					// Maybe they are trying to change their password as well?
					$resetPassword = true;
					if (isset($_POST['passwrd1'], $_POST['passwrd2']) && $_POST['passwrd1'] != '' && $_POST['passwrd1'] == $_POST['passwrd2'] && validatePassword($_POST['passwrd1'], $value, array($cur_profile['real_name'], we::$user['username'], we::$user['name'], we::$user['email'])) == null)
						$resetPassword = false;

					// Do the reset... this will send them an email too.
					if ($resetPassword)
						resetPassword($context['id_member'], $value);
					elseif ($value !== null)
					{
						loadLanguage('Login');
						validateUsername($context['id_member'], trim(preg_replace('~[\t\n\r \x0B\0\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}]+~u', ' ', $value)));
						updateMemberData($context['id_member'], array('member_name' => $value));
					}
				}
				return false;
			},
		),
		'passwrd1' => array(
			'type' => 'password',
			'label' => $txt['choose_pass'],
			'subtext' => $txt['password_strength'],
			'size' => 20,
			'value' => '',
			'permission' => 'profile_identity',
			'save_key' => 'passwd',

			// Note this will only work if passwrd2 also exists!
			'input_validate' => function (&$value) {
				global $cur_profile, $txt, $settings;

				// If we didn't try it, then ignore it!
				if ($value == '')
					return false;

				// Do the two entries for the password even match?
				if (!isset($_POST['passwrd2']) || $value != $_POST['passwrd2'])
					return 'bad_new_password';

				// Let's get the validation function into play...
				loadSource('Subs-Auth');
				$passwordErrors = validatePassword($value, $cur_profile['member_name'], array($cur_profile['real_name'], we::$user['username'], we::$user['name'], we::$user['email']));

				// Were there errors?
				if ($passwordErrors != null)
				{
					if ($passwordErrors == 'short')
					{
						loadLanguage('Errors');
						$txt['profile_error_password_short'] = sprintf($txt['profile_error_password_short'], empty($settings['password_strength']) ? 4 : 8);
					}

					return 'password_' . $passwordErrors;
				}

				// Set up the new password variable... ready for storage.
				$value = sha1(strtolower($cur_profile['member_name']) . un_htmlspecialchars($value));
				return true;
			},
		),
		'passwrd2' => array(
			'type' => 'password',
			'label' => $txt['verify_pass'],
			'size' => 20,
			'value' => '',
			'permission' => 'profile_identity',
			'is_dummy' => true,
		),
		'prefslist' => array(
			'type' => 'callback',
			'callback_func' => 'display_prefslist',
			'permission' => 'profile_extra',
			'is_dummy' => true,
		),
		// This does ALL the pm settings
		'pm_prefs' => array(
			'type' => 'callback',
			'callback_func' => 'pm_settings',
			'permission' => 'pm_read',
			'preload' => function () {
				global $context, $cur_profile;

				loadLanguage('PersonalMessage');
				$context['display_mode'] = $cur_profile['pm_prefs'] & 3;
				$context['send_email'] = $cur_profile['pm_email_notify'];
				$context['receive_from'] = !empty($cur_profile['pm_receive_from']) ? $cur_profile['pm_receive_from'] : 0;

				return true;
			},
			'input_validate' => function (&$value) {
				global $cur_profile, $profile_vars;

				// Validate and apply the two "sub settings"
				$value = max(min($value, 2), 0);

				$cur_profile['pm_email_notify'] = $profile_vars['pm_email_notify'] = max(min((int) $_POST['pm_email_notify'], 2), 0);
				$cur_profile['pm_receive_from'] = $profile_vars['pm_receive_from'] = max(min((int) $_POST['pm_receive_from'], 4), 0);

				return true;
			},
		),
		'posts' => array(
			'type' => 'int',
			'label' => $txt['profile_posts'],
			'subtext' => $txt['digits_only'],
			'log_change' => true,
			'size' => 7,
			'permission' => 'moderate_forum',
			'input_validate' => function (&$value) {
				$value = $value != '' ? strtr($value, array(',' => '', '.' => '', ' ' => '')) : 0;
				return true;
			},
		),
		'real_name' => array(
			'type' => !empty($settings['allow_editDisplayName']) || allowedTo('moderate_forum') ? 'text' : 'label',
			'label' => $txt['name'],
			'subtext' => $txt['display_name_desc'],
			'log_change' => true,
			'input_attr' => array(' maxlength="60"'),
			'permission' => 'profile_identity',
			'enabled' => !empty($settings['allow_editDisplayName']) || allowedTo('moderate_forum'),
			'input_validate' => function (&$value) {
				global $context, $cur_profile;

				$value = trim(preg_replace('~[\t\n\r \x0B\0\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}]+~u', ' ', $value));

				if (trim($value) == '')
					return 'no_name';
				elseif (westr::strlen($value) > 60)
					return 'name_too_long';
				elseif ($cur_profile['real_name'] != $value)
				{
					loadSource('Subs-Members');
					if (isReservedName($value, $context['id_member']))
						return 'name_taken';
				}
				return true;
			},
		),
		'secret_question' => array(
			'type' => 'text',
			'label' => $txt['secret_question'],
			'subtext' => $txt['secret_desc'],
			'size' => 50,
			'permission' => 'profile_identity',
			'input_validate' => function (&$value) {
				return false;
			},
		),
		'secret_answer' => array(
			'type' => 'text',
			'label' => $txt['secret_answer'],
			'subtext' => $txt['secret_desc2'],
			'size' => 20,
			'postinput' => '<dfn>(<a href="<URL>?action=help;in=secret_why_blank" onclick="return reqWin(this);">' . $txt['secret_why_blank'] . '</a>)</dfn>',
			'value' => '',
			'permission' => 'profile_identity',
			'input_validate' => function (&$value) {
				return false;
			},
		),
		'signature' => array(
			'type' => 'callback',
			'callback_func' => 'signature_modify',
			'permission' => 'profile_signature',
			'enabled' => $settings['signature_settings'][0] == 1,
			'preload' => 'profileLoadSignatureData',
			'input_validate' => 'profileValidateSignature',
		),
		'show_online' => array(
			'type' => 'check',
			'label' => $txt['show_online'],
			'permission' => 'profile_identity',
			'enabled' => !empty($settings['allow_hideOnline']) || allowedTo('moderate_forum'),
		),
		'smiley_set' => array(
			'type' => 'callback',
			'callback_func' => 'smiley_pick',
			'enabled' => !empty($settings['smiley_sets_enable']),
			'permission' => 'profile_extra',
			'preload' => function () {
				global $settings, $context, $txt, $cur_profile;

				$context['member']['smiley_set']['id'] = empty($cur_profile['smiley_set']) ? '' : $cur_profile['smiley_set'];
				$context['smiley_sets'] = explode(',', 'none,,' . $settings['smiley_sets_known']);
				$set_names = explode("\n", $txt['smileys_none'] . "\n" . $txt['smileys_forum_board_default'] . "\n" . $settings['smiley_sets_names']);
				foreach ($context['smiley_sets'] as $i => $set)
				{
					$context['smiley_sets'][$i] = array(
						'id' => htmlspecialchars($set),
						'name' => htmlspecialchars($set_names[$i]),
						'selected' => $set == $context['member']['smiley_set']['id']
					);

					if ($context['smiley_sets'][$i]['selected'])
						$context['member']['smiley_set']['name'] = $set_names[$i];
				}
				return true;
			},
			'input_validate' => function (&$value) {
				global $settings;

				$smiley_sets = explode(',', $settings['smiley_sets_known']);
				if (!in_array($value, $smiley_sets) && $value != 'none')
					$value = '';
				return true;
			},
		),
		'time_format' => array(
			'type' => 'callback',
			'callback_func' => 'timeformat_modify',
			'permission' => 'profile_extra',
			'preload' => function () {
				global $context, $txt, $cur_profile, $settings;

				$context['easy_timeformats'] = array(
					array('format' => '', 'title' => $txt['timeformat_default']),
					array('format' => '%B %d, %Y, %I:%M:%S %p', 'title' => $txt['timeformat_easy1']),
					array('format' => '%B %d, %Y, %H:%M:%S', 'title' => $txt['timeformat_easy2']),
					array('format' => '%Y-%m-%d, %H:%M:%S', 'title' => $txt['timeformat_easy3']),
					array('format' => '%d %B %Y, %H:%M:%S', 'title' => $txt['timeformat_easy4']),
					array('format' => '%d-%m-%Y, %H:%M:%S', 'title' => $txt['timeformat_easy5'])
				);

				$context['member']['time_format'] = $cur_profile['time_format'];
				$context['current_forum_time'] = timeformat(time() - we::$user['time_offset'] * 3600 + date_offset_get(new DateTime), false);
				$context['current_forum_time_js'] = time() + $settings['time_offset'] * 3600;
				$context['current_forum_time_hour'] = (int) strftime('%H', forum_time(false));
				return true;
			},
		),
		'time_offset' => array(
			'type' => 'callback',
			'callback_func' => 'timeoffset_modify',
			'permission' => 'profile_extra',
			'preload' => function () {
				global $context, $cur_profile;

				$context['member']['time_offset'] = $cur_profile['time_offset'];
				return true;
			},
			'input_validate' => function (&$value) {
				// Validate the time_offset...
				$value = (float) strtr($value, ',', '.');

				if ($value < -23.5 || $value > 23.5)
					return 'bad_offset';

				return true;
			},
		),
		'timezone' => array(
			'type' => 'select',
			'options' => function () { return get_wedge_timezones(); },
			'permission' => 'profile_extra',
			'privacy' => 'time',
			'label' => $txt['time_zone'],
		),
		'personal_text' => array(
			'type' => 'text',
			'label' => $txt['personal_text'],
			'log_change' => true,
			'size' => 50,
			'input_attr' => array('maxlength="50"'),
			'permission' => 'profile_extra',
		),
		'usertitle' => array(
			'type' => 'text',
			'label' => $txt['custom_title'],
			'log_change' => true,
			'size' => 50,
			'input_attr' => array(' maxlength="42"'),
			'permission' => 'profile_title',
			'enabled' => !empty($settings['titlesEnable']),
		),
		'website_title' => array(
			'type' => 'text',
			'label' => $txt['website_title'],
			'subtext' => $txt['include_website_url'],
			'size' => 50,
			'permission' => 'profile_website',
			'link_with' => 'website',
		),
		'website_url' => array(
			'type' => 'url',
			'label' => $txt['website_url'],
			'subtext' => $txt['complete_url'],
			'size' => 50,
			'permission' => 'profile_website',
			// Fix the URL...
			'input_validate' => function (&$value) {
				if (strlen(trim($value)) > 0 && strpos($value, '://') === false)
					$value = 'http://' . $value;
				if (strlen($value) < 8 || (substr($value, 0, 7) !== 'http://' && substr($value, 0, 8) !== 'https://'))
					$value = '';
				return true;
			},
			'link_with' => 'website',
		),
	);

	$cur_profile['secret_question'] = isset($cur_profile['data']['secret']) ? implode('|', explode('|', $cur_profile['data']['secret'], -1)) : '';
	$disabled_fields = !empty($settings['disabled_profile_fields']) ? explode(',', $settings['disabled_profile_fields']) : array();
	$is_owner = we::$user['is_owner'];

	// For each of the above let's take out the bits which don't apply - to save memory and security!
	foreach ($profile_fields as $key => $field)
	{
		// Do we have permission to do this?
		if (isset($field['permission']) && !allowedTo(($is_owner ? array($field['permission'] . '_own', $field['permission'] . '_any') : $field['permission'] . '_any')) && !allowedTo($field['permission']))
			unset($profile_fields[$key]);

		// Is it enabled?
		if (isset($field['enabled']) && !$field['enabled'])
			unset($profile_fields[$key]);

		// Is it specifically disabled?
		if (in_array($key, $disabled_fields) || (isset($field['link_with']) && in_array($field['link_with'], $disabled_fields)))
			unset($profile_fields[$key]);
	}
}

// Setup the context for a page load!
function setupProfileContext($fields)
{
	global $profile_fields, $context, $cur_profile, $txt;

	// Make sure we have this!
	loadProfileFields(true);

	// First check for any linked sets.
	foreach ($profile_fields as $key => $field)
		if (isset($field['link_with']) && in_array($field['link_with'], $fields))
			$fields[] = $key;

	// Some default bits.
	$context['profile_prehtml'] = '';
	$context['profile_posthtml'] = '';

	$i = 0;
	$last_type = '';

	foreach ($fields as $key => $field)
	{
		if (isset($profile_fields[$field]))
		{
			// Shortcut.
			$cur_field =& $profile_fields[$field];

			// Does it have a preload and does that preload succeed?
			if (isset($cur_field['preload']) && !$cur_field['preload']())
				continue;

			// If this is anything but complex we need to do more cleaning!
			if ($cur_field['type'] != 'callback' && $cur_field['type'] != 'hidden')
			{
				if (!isset($cur_field['label']))
					$cur_field['label'] = isset($txt[$field]) ? $txt[$field] : $field;

				// Everything has a value!
				if (!isset($cur_field['value']))
					$cur_field['value'] = isset($cur_profile[$field]) ? $cur_profile[$field] : '';

				// Any input attributes?
				$cur_field['input_attr'] = !empty($cur_field['input_attr']) ? implode(',', $cur_field['input_attr']) : '';
			}

			// Was there an error with this field on posting?
			if (isset($context['profile_errors'][$field]))
				$cur_field['is_error'] = true;

			// Any template stuff?
			if (!empty($cur_field['prehtml']))
				$context['profile_prehtml'] .= $cur_field['prehtml'];
			if (!empty($cur_field['posthtml']))
				$context['profile_posthtml'] .= $cur_field['posthtml'];

			// Finally put it into context?
			if ($cur_field['type'] != 'hidden')
			{
				$last_type = $cur_field['type'];
				$context['profile_fields'][$field] =& $profile_fields[$field];
			}
		}
		// Bodge in a line break - without doing two in a row ;)
		elseif ($field == 'hr' && $last_type != 'hr' && $last_type != '')
		{
			$last_type = 'hr';
			$context['profile_fields'][$i++]['type'] = 'hr';
		}
	}

	// Free up some memory.
	unset($profile_fields);
}

// Save the profile changes.
function saveProfileFields()
{
	global $profile_fields, $profile_vars, $context, $old_profile, $post_errors, $cur_profile;

	// Load them up.
	loadProfileFields();

	// This makes things easier...
	$old_profile = $cur_profile;

	// This allows variables to call activities when they save - by default just to reload their settings
	$context['profile_execute_on_save'] = array();
	if (we::$user['is_owner'])
		$context['profile_execute_on_save']['reload_user'] = 'profileReloadUser';

	// Assume we log nothing.
	$context['log_changes'] = array();

	$secret = isset($cur_profile['data']['secret']) ? $cur_profile['data']['secret'] : '|';
	$question = isset($_POST['secret_question']) && $_POST['secret_question'] !== '' ? $_POST['secret_question'] : substr($secret, 0, strrpos($secret, '|'));
	$answer = isset($_POST['secret_answer']) && $_POST['secret_answer'] !== '' ? md5($_POST['secret_answer']) : substr(strrchr($secret, '|'), 1);

	if (trim($question) === '' || trim($answer) === '')
	{
		unset($cur_profile['data']['secret']);
		updateMemberData($context['id_member'], array('data' => serialize($cur_profile['data'])));
	}
	elseif (!isset($cur_profile['data']['secret']) || $cur_profile['data']['secret'] !== $secret)
	{
		$cur_profile['data']['secret'] = $question . '|' . $answer;
		updateMemberData($context['id_member'], array('data' => serialize($cur_profile['data'])));
	}

	// Cycle through the profile fields working out what to do!
	foreach ($profile_fields as $key => $field)
	{
		if (!isset($_POST[$key]) || !empty($field['is_dummy']))
			continue;

		// What gets updated?
		$db_key = isset($field['save_key']) ? $field['save_key'] : $key;

		// Right - we have something that is enabled, we can act upon and has a value posted to it. Does it have a validation function?
		if (isset($field['input_validate']))
		{
			$is_valid = $field['input_validate']($_POST[$key]);
			// An error occurred - set it as such!
			if ($is_valid !== true)
			{
				// Is this an actual error?
				if ($is_valid !== false)
				{
					$post_errors[$key] = $is_valid;
					$profile_fields[$key]['is_error'] = $is_valid;
				}
				// Retain the old value.
				$cur_profile[$key] = $_POST[$key];
				continue;
			}
		}

		// Are we doing a cast?
		$field['cast_type'] = empty($field['cast_type']) ? $field['type'] : $field['cast_type'];

		// Finally, clean up certain types.
		if ($field['cast_type'] == 'int')
			$_POST[$key] = (int) $_POST[$key];
		elseif ($field['cast_type'] == 'float')
			$_POST[$key] = (float) $_POST[$key];
		elseif ($field['cast_type'] == 'check')
			$_POST[$key] = !empty($_POST[$key]) ? 1 : 0;

		// If we got here we're doing OK.
		if ($field['type'] != 'hidden' && (!isset($old_profile[$key]) || $_POST[$key] != $old_profile[$key]))
		{
			// Set the save variable.
			$profile_vars[$db_key] = $_POST[$key];
			// And update the user profile.
			$cur_profile[$key] = $_POST[$key];

			// Are we logging it?
			if (!empty($field['log_change']) && isset($old_profile[$key]))
				$context['log_changes'][$key] = array(
					'previous' => $old_profile[$key],
					'new' => $_POST[$key],
				);
		}

		// Logging group changes are a bit different...
		if ($key == 'id_group' && $field['log_change'])
		{
			profileLoadGroups();

			// Any changes to primary group?
			if ($_POST['id_group'] != $old_profile['id_group'])
			{
				$context['log_changes']['id_group'] = array(
					'previous' => !empty($old_profile[$key]) && isset($context['member_groups'][$old_profile[$key]]) ? $context['member_groups'][$old_profile[$key]]['name'] : '',
					'new' => !empty($_POST[$key]) && isset($context['member_groups'][$_POST[$key]]) ? $context['member_groups'][$_POST[$key]]['name'] : '',
				);
			}

			// Prepare additional groups for comparison.
			$additional_groups = array(
				'previous' => !empty($old_profile['additional_groups']) ? explode(',', $old_profile['additional_groups']) : array(),
				'new' => !empty($_POST['additional_groups']) ? array_diff($_POST['additional_groups'], array(0)) : array(),
			);

			sort($additional_groups['previous']);
			sort($additional_groups['new']);

			// What about additional groups?
			if ($additional_groups['previous'] != $additional_groups['new'])
			{
				foreach ($additional_groups as $type => $groups)
				{
					foreach ($groups as $id => $group)
					{
						if (isset($context['member_groups'][$group]))
							$additional_groups[$type][$id] = $context['member_groups'][$group]['name'];
						else
							unset($additional_groups[$type][$id]);
					}
					$additional_groups[$type] = implode(', ', $additional_groups[$type]);
				}

				$context['log_changes']['additional_groups'] = $additional_groups;
			}
		}
	}

	$changeOther = allowedTo(we::$user['is_owner'] ? array('profile_extra_any', 'profile_extra_own') : 'profile_extra_any');
	if ($changeOther && empty($post_errors))
	{
		makeThemeChanges($context['id_member']);
		if (!empty($_REQUEST['sa']))
			makeCustomFieldChanges($context['id_member'], $_REQUEST['sa'], false);
	}

	// Free memory!
	unset($profile_fields);
}

// Save the profile changes....
function saveProfileChanges(&$profile_vars, $memID)
{
	global $settings, $user_profile;

	// These make life easier....
	$old_profile =& $user_profile[$memID];

	// Permissions...
	if (we::$user['is_owner'])
	{
		$changeIdentity = allowedTo(array('profile_identity_any', 'profile_identity_own'));
		$changeOther = allowedTo(array('profile_extra_any', 'profile_extra_own'));
	}
	else
	{
		$changeIdentity = allowedTo('profile_identity_any');
		$changeOther = allowedTo('profile_extra_any');
	}

	// Arrays of all the changes - makes things easier.
	$profile_bools = array(
		'notify_announcements', 'notify_send_body',
	);
	$profile_ints = array(
		'notify_regularity',
		'notify_types',
	);
	$profile_floats = array(
	);
	$profile_strings = array(
		'buddy_list',
		'ignore_boards',
	);

	if (isset($_POST['sa']) && $_POST['sa'] == 'ignoreboards' && empty($_POST['ignore_brd']))
		$_POST['ignore_brd'] = array();

	unset($_POST['ignore_boards']); // Whatever it is set to is a dirty fithy thing. Kinda like our minds.
	if (isset($_POST['ignore_brd']))
	{
		if (!is_array($_POST['ignore_brd']))
			$_POST['ignore_brd'] = array ($_POST['ignore_brd']);

		$ignorable_boards = !empty($settings['ignorable_boards']) ? unserialize($settings['ignorable_boards']) : array();

		foreach ($_POST['ignore_brd'] as $k => $d)
		{
			$d = (int) $d;
			if ($d != 0)
				$_POST['ignore_brd'][$k] = $d;
			else
				unset($_POST['ignore_brd'][$k]);
		}
		$_POST['ignore_boards'] = implode(',', array_intersect($_POST['ignore_brd'], $ignorable_boards));
		unset($_POST['ignore_brd']);

	}

	// Here's where we sort out all the 'other' values...
	if ($changeOther)
	{
		makeThemeChanges($memID);
		makeNotificationChanges($memID);
		if (!empty($_REQUEST['sa']))
			makeCustomFieldChanges($memID, $_REQUEST['sa'], false);

		foreach ($profile_bools as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = empty($_POST[$var]) ? '0' : '1';
		foreach ($profile_ints as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = $_POST[$var] != '' ? (int) $_POST[$var] : '';
		foreach ($profile_floats as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = (float) $_POST[$var];
		foreach ($profile_strings as $var)
			if (isset($_POST[$var]))
				$profile_vars[$var] = $_POST[$var];
	}
}

// Make any theme changes that are sent with the profile...
// !! @todo: rewrite this...
function makeThemeChanges($memID)
{
	global $settings, $context;

	// Don't allow any overriding of custom fields with default or non-default options.
	$request = wesql::query('
		SELECT col_name
		FROM {db_prefix}custom_fields
		WHERE active = {int:is_active}',
		array(
			'is_active' => 1,
		)
	);
	$custom_fields = array();
	while ($row = wesql::fetch_assoc($request))
		$custom_fields[] = $row['col_name'];
	wesql::free_result($request);

	// These are the theme changes...
	$themeSetArray = array();
	if (isset($_POST['options']) && is_array($_POST['options']))
	{
		foreach ($_POST['options'] as $opt => $val)
		{
			if (in_array($opt, $custom_fields))
				continue;

			// These need to be controlled.
			if ($opt == 'topics_per_page' || $opt == 'messages_per_page')
				$val = max(0, min($val, 50));

			$themeSetArray[] = array($memID, $opt, is_array($val) ? implode(',', $val) : $val);
		}
	}

	if (isset($_POST['default_options']) && is_array($_POST['default_options']))
		foreach ($_POST['default_options'] as $opt => $val)
		{
			if (in_array($opt, $custom_fields))
				continue;

			// These need to be controlled.
			if ($opt == 'topics_per_page' || $opt == 'messages_per_page')
				$val = max(0, min($val, 50));

			$themeSetArray[] = array($memID, $opt, is_array($val) ? implode(',', $val) : $val);
		}

	// If themeSetArray isn't still empty, send it to the database.
	if (empty($context['password_auth_failed']))
	{
		if (!empty($themeSetArray))
		{
			wesql::insert('replace',
				'{db_prefix}themes',
				array('id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
				$themeSetArray
			);
		}

		$themes = explode(',', $settings['knownThemes']);
		foreach ($themes as $t)
			cache_put_data('theme_settings-' . $t . ':' . $memID, null, 60);
	}
}

// Make any notification changes that need to be made.
function makeNotificationChanges($memID)
{
	// Update the boards they are being notified on.
	if (isset($_POST['edit_notify_boards']) && !empty($_POST['notify_boards']))
	{
		// Make sure only integers are deleted.
		foreach ($_POST['notify_boards'] as $index => $id)
			$_POST['notify_boards'][$index] = (int) $id;

		// id_board = 0 is reserved for topic notifications.
		$_POST['notify_boards'] = array_diff($_POST['notify_boards'], array(0));

		wesql::query('
			DELETE FROM {db_prefix}log_notify
			WHERE id_board IN ({array_int:board_list})
				AND id_member = {int:selected_member}',
			array(
				'board_list' => $_POST['notify_boards'],
				'selected_member' => $memID,
			)
		);
	}

	// We are editing topic notifications......
	elseif (isset($_POST['edit_notify_topics']) && !empty($_POST['notify_topics']))
	{
		foreach ($_POST['notify_topics'] as $index => $id)
			$_POST['notify_topics'][$index] = (int) $id;

		// Make sure there are no zeros left.
		$_POST['notify_topics'] = array_diff($_POST['notify_topics'], array(0));

		wesql::query('
			DELETE FROM {db_prefix}log_notify
			WHERE id_topic IN ({array_int:topic_list})
				AND id_member = {int:selected_member}',
			array(
				'topic_list' => $_POST['notify_topics'],
				'selected_member' => $memID,
			)
		);
	}
}

// Save any changes to the custom profile fields...
function makeCustomFieldChanges($memID, $area, $sanitize = true)
{
	global $context, $user_profile, $settings;

	if ($sanitize && isset($_POST['customfield']))
		$_POST['customfield'] = htmlspecialchars__recursive($_POST['customfield']);

	$where = $area === 'register' ? 'show_reg != 0' : 'show_profile = {string:area}';

	// Load the fields we are saving too - make sure we save valid data (etc).
	$request = wesql::query('
		SELECT col_name, field_type, field_length, field_options, default_value, mask, can_edit
		FROM {db_prefix}custom_fields
		WHERE ' . $where . '
			AND active = {int:is_active}',
		array(
			'is_active' => 1,
			'area' => $area === 'theme' ? 'options' : $area,
		)
	);
	$changes = array();
	$log_changes = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// Evaluate privacy. If it's during registration, we're expecting to get the information, so no skipping.
		// No skip for admins (because they have the power), otherwise check 1) the user has 1+ matching group and 2) if it's the user, whether the user has owner permission
		if ($area != 'register' && !allowedTo('admin_forum'))
		{
			$privacy_groups = explode(',', $row['can_edit']);
			foreach ($privacy_groups as $k => $v)
				$privacy_groups[$k] = (int) $v;
			// So if the user has no matching groups, skip it.
			if (count(array_intersect(we::$user['groups'], $privacy_groups)) == 0)
				continue;
			elseif ($memID == MID && !in_array(-2, $privacy_groups))
				continue;
		}

		// Validate the user data.
		if ($row['field_type'] == 'check')
			$value = isset($_POST['customfield'][$row['col_name']]) ? 1 : 0;
		elseif ($row['field_type'] == 'select' || $row['field_type'] == 'radio')
		{
			$value = $row['default_value'];
			foreach (explode(',', $row['field_options']) as $k => $v)
				if (isset($_POST['customfield'][$row['col_name']]) && $_POST['customfield'][$row['col_name']] == $k)
					$value = $v;
		}
		// Otherwise some form of text!
		else
		{
			$value = isset($_POST['customfield'][$row['col_name']]) ? $_POST['customfield'][$row['col_name']] : '';
			if ($row['field_length'])
				$value = westr::substr($value, 0, $row['field_length']);

			// Any masks?
			if ($row['field_type'] == 'text' && !empty($row['mask']) && $row['mask'] != 'none')
			{
				// !! We never error on this - just ignore it at the moment...
				if ($row['mask'] == 'email' && (!is_valid_email($value) || strlen($value) > 255))
					$value = '';
				elseif ($row['mask'] == 'number')
				{
					$value = (int) $value;
				}
				elseif (substr($row['mask'], 0, 5) == 'regex' && trim($value) != '' && preg_match(substr($row['mask'], 5), $value) === 0)
					$value = '';
			}
		}

		// Did it change?
		if (!isset($user_profile[$memID]['options'][$row['col_name']]) || $user_profile[$memID]['options'][$row['col_name']] != $value)
		{
			$log_changes[] = array(
				'action' => 'customfield_' . $row['col_name'],
				'id_log' => 2,
				'log_time' => time(),
				'id_member' => $memID,
				'ip' => get_ip_identifier(we::$user['ip']),
				'extra' => serialize(array('previous' => !empty($user_profile[$memID]['options'][$row['col_name']]) ? $user_profile[$memID]['options'][$row['col_name']] : '', 'new' => $value, 'applicator' => MID)),
			);
			$changes[] = array($row['col_name'], $value, $memID);
			$user_profile[$memID]['options'][$row['col_name']] = $value;
		}
	}
	wesql::free_result($request);

	// Make those changes!
	if (!empty($changes) && empty($context['password_auth_failed']))
	{
		wesql::insert('replace',
			'{db_prefix}themes',
			array('variable' => 'string-255', 'value' => 'string-65534', 'id_member' => 'int'),
			$changes
		);
		if (!empty($log_changes) && !empty($settings['log_enabled_profile']))
			wesql::insert('',
				'{db_prefix}log_actions',
				array(
					'action' => 'string', 'id_log' => 'int', 'log_time' => 'int', 'id_member' => 'int', 'ip' => 'int',
					'extra' => 'string-65534',
				),
				$log_changes
			);
	}
}

// Redirect to buddies or ignore list.
function editBuddyIgnoreLists($memID)
{
	global $context, $txt, $settings;

	// Do a quick check to ensure people aren't getting here illegally!
	if (!we::$user['is_owner'] || empty($settings['enable_buddylist']))
		fatal_lang_error('no_access', false);

	$subActions = array(
		'buddies' => array('editBuddies', $txt['editBuddies']),
		'ignore' => array('editIgnoreList', $txt['editIgnoreList']),
	);

	$context['list_area'] = isset($_GET['sa'], $subActions[$_GET['sa']]) ? $_GET['sa'] : 'buddies';

	// Create the tabs for the template.
	$context[$context['profile_menu_name']]['tab_data'] = array(
		'title' => $txt['editBuddyIgnoreLists'],
		'description' => $txt['buddy_ignore_desc'],
		'icon' => 'profile_sm.gif',
		'tabs' => array(
			'buddies' => array(),
			'ignore' => array(),
		),
	);

	// Pass on to the actual function.
	wetem::load($subActions[$context['list_area']][0]);
	$subActions[$context['list_area']][0]($memID);
}

// Show all the user's contacts, as well as an add/delete interface.
function editContacts($memID)
{
	global $context, $txt, $settings;

	// Do a quick check to ensure people aren't getting here illegally!
	if (empty($settings['enable_buddylist']))
		fatal_lang_error('no_access', false);

//	$subActions = array(
//		'new' => array('addContactList', $txt['editBuddies']),
//		'edit' => array('editContactList', $txt['editIgnoreList']),
//	);

	// Pass on to the actual function.
	wetem::load('editContactList');
	editContactList($memID);
}

// !! Currently not being used; to remove.
/*
function addContactList($memID)
{
	global $context, $user_profile, $memberContext;

	// For making changes!
	$buddiesArray = array_filter(explode(',', $user_profile[$memID]['buddy_list']));

	// Removing a buddy?
	if (isset($_GET['remove']))
	{
		checkSession('get');

		// Heh, I'm lazy, do it the easy way...
		foreach ($buddiesArray as $key => $buddy)
			if ($buddy == (int) $_GET['remove'])
				unset($buddiesArray[$key]);

		// Make the changes.
		$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
		updateMemberData($memID, array('buddy_list' => $user_profile[$memID]['buddy_list']));

		// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
		redirectexit('action=profile;u=' . $memID . ';area=lists;sa=buddies');
	}
	elseif (isset($_POST['new_buddy']))
	{
		// Prepare the string for extraction...
		$_POST['new_buddy'] = strtr(westr::htmlspecialchars($_POST['new_buddy'], ENT_QUOTES), array('&quot;' => '"'));
		preg_match_all('~"([^"]+)"~', $_POST['new_buddy'], $matches);
		$new_buddies = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_buddy']))));

		foreach ($new_buddies as $k => $dummy)
		{
			$new_buddies[$k] = strtr(trim($new_buddies[$k]), array("'" => '&#039;'));

			if (strlen($new_buddies[$k]) == 0 || in_array($new_buddies[$k], array($user_profile[$memID]['member_name'], $user_profile[$memID]['real_name'])))
				unset($new_buddies[$k]);
		}

		if (!empty($new_buddies))
		{
			// Now find out the id_member of the buddy.
			$request = wesql::query('
				SELECT id_member
				FROM {db_prefix}members
				WHERE member_name IN ({array_string:new_buddies}) OR real_name IN ({array_string:new_buddies})
				LIMIT {int:count_new_buddies}',
				array(
					'new_buddies' => $new_buddies,
					'count_new_buddies' => count($new_buddies),
				)
			);

			// Add the new member to the buddies array.
			while ($row = wesql::fetch_assoc($request))
				$buddiesArray[] = (int) $row['id_member'];
			wesql::free_result($request);
			$buddiesArray = array_flip(array_flip(array_filter($buddiesArray)));

			// Now update the current users buddy list.
			$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
			updateMemberData($memID, array('buddy_list' => $user_profile[$memID]['buddy_list']));
		}

		// Back to the buddy list!
		redirectexit('action=profile;u=' . $memID . ';area=lists;sa=buddies');
	}

	// Get all the users "buddies"...
	$buddies = array();

	if (!empty($buddiesArray))
	{
		$result = wesql::query('
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:buddy_list})
			ORDER BY real_name
			LIMIT {int:buddy_list_count}',
			array(
				'buddy_list' => $buddiesArray,
				'buddy_list_count' => substr_count($user_profile[$memID]['buddy_list'], ',') + 1,
			)
		);
		while ($row = wesql::fetch_assoc($result))
			$buddies[] = $row['id_member'];
		wesql::free_result($result);
	}

	$context['buddy_count'] = count($buddies);

	// Load all the members up.
	loadMemberData($buddies, false, 'profile');

	// Setup the context for each buddy.
	$context['buddies'] = array();
	foreach ($buddies as $buddy)
	{
		loadMemberContext($buddy);
		$context['buddies'][$buddy] = $memberContext[$buddy];
	}
}
*/

function editContactList($memID)
{
	global $context, $user_profile, $memberContext;

	// For making changes!
	$buddiesArray = array_filter(explode(',', $user_profile[$memID]['buddy_list']));
	$canChangeOther = allowedTo('profile_extra_any');
	$canChange = $canChangeOther || allowedTo('profile_extra_own');

	// Adding or removing a contact from one or more lists, maybe..?
	if (isset($_POST['uid']) && $canChange)
	{
		checkSession('post');

		$user = (int) $_POST['uid'];
		if (!$user)
			fatal_lang_error('not_a_user', false);

		$c = isset($_POST['c']) ? $_POST['c'] : array();
		$c += we::$user['contacts']['lists'];

		foreach ($c as $clist => $val)
		{
			if ((int) $clist == 0)
			{
				// 'New lists' should be of the custom type by default.
				if ($clist === 'new')
					$clist = 'custom';
				wesql::insert('ignore',
					'{db_prefix}contact_lists',
					array('id_owner' => 'int', 'name' => 'string', 'list_type' => 'string', 'added' => 'int'),
					array($memID, '{' . $clist . '}', $clist, time())
				);
				$cid = wesql::insert_id();
				we::$user['contacts']['lists'][$cid] = array('{' . $clist . '}', $clist);
				$clist = $cid;
			}
			else
				$clist = (int) $clist;

			// $val is either 'on'/1 (add to this) or array(name, type) (existing list, not in $_POST, remove from it.)
			// Add to a list..?
			if (!is_array($val) && !isset(we::$user['contacts']['users'][$clist][$user]))
				wesql::insert('ignore',
					'{db_prefix}contacts',
					array('id_member' => 'int', 'id_owner' => 'int', 'id_list' => 'int', 'list_type' => 'string', 'added' => 'int'),
					array($user, $memID, $clist, we::$user['contacts']['lists'][$clist][1], time())
				);

			// Remove from a list?
			elseif (is_array($val) && isset(we::$user['contacts']['users'][$clist][$user]))
				wesql::query('
					DELETE FROM {db_prefix}contacts
					WHERE id_member = {int:user}
					AND id_list = {int:list}' . ($canChangeOther ? '' : '
					AND id_owner = {int:me}'),
					array(
						'user' => $user,
						'list' => $clist,
						'me' => $memID, // Make sure we're the legitimate owner!
					)
				);

			// At this point, would be nice to know if we're friendly to each other.
/*			$request = wesql::query('
				SELECT id_list FROM {db_prefix}contacts
				WHERE (id_owner = {int:user} AND id_member = {int:me} AND list_type != {literal:restrict})
				OR (id_owner = {int:me} AND id_member = {int:user} AND list_type != {literal:restrict})',
				array(
					'user' => $user,
					'me' => we::$id,
				)
			);
			$is_synchronous = wesql::num_rows($request) > 0;
			wesql::free_result($request);

			// And then save it. Not sure it'll be very useful, but it's not too costly...
			wesql::query('
				UPDATE {db_prefix}contacts
				SET id_synchronous = {int:sync}
				WHERE (id_owner = {int:user} AND id_member = {int:me})
				OR (id_owner = {int:me} AND id_member = {int:user})',
				array(
					'user' => $user,
					'me' => we::$id,
					'sync' => (int) $is_synchronous,
				)
			);*/
		}
		cache_put_data('contacts_' . $memID, null, 3000);
		redirectexit('action=profile;u=' . $user);
	}

	// !! This should be removed, once it's all set and done.
/*
	// Removing a buddy?
	if (isset($_GET['remove']))
	{
		checkSession('get');

		// Heh, I'm lazy, do it the easy way...
		foreach ($buddiesArray as $key => $buddy)
			if ($buddy == (int) $_GET['remove'])
				unset($buddiesArray[$key]);

		// Make the changes.
		$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
		updateMemberData($memID, array('buddy_list' => $user_profile[$memID]['buddy_list']));

		// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
		redirectexit('action=profile;u=' . $memID . ';area=lists;sa=buddies');
	}
	elseif (isset($_POST['new_buddy']))
	{
		// Prepare the string for extraction...
		$_POST['new_buddy'] = strtr(westr::htmlspecialchars($_POST['new_buddy'], ENT_QUOTES), array('&quot;' => '"'));
		preg_match_all('~"([^"]+)"~', $_POST['new_buddy'], $matches);
		$new_buddies = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_buddy']))));

		foreach ($new_buddies as $k => $dummy)
		{
			$new_buddies[$k] = strtr(trim($new_buddies[$k]), array("'" => '&#039;'));

			if (strlen($new_buddies[$k]) == 0 || in_array($new_buddies[$k], array($user_profile[$memID]['member_name'], $user_profile[$memID]['real_name'])))
				unset($new_buddies[$k]);
		}

		if (!empty($new_buddies))
		{
			// Now find out the id_member of the buddy.
			$request = wesql::query('
				SELECT id_member
				FROM {db_prefix}members
				WHERE member_name IN ({array_string:new_buddies}) OR real_name IN ({array_string:new_buddies})
				LIMIT {int:count_new_buddies}',
				array(
					'new_buddies' => $new_buddies,
					'count_new_buddies' => count($new_buddies),
				)
			);

			// Add the new member to the buddies array.
			while ($row = wesql::fetch_assoc($request))
				$buddiesArray[] = (int) $row['id_member'];
			wesql::free_result($request);
			$buddiesArray = array_flip(array_flip(array_filter($buddiesArray)));

			// Now update the current users buddy list.
			$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
			updateMemberData($memID, array('buddy_list' => $user_profile[$memID]['buddy_list']));
		}

		// Back to the buddy list!
		redirectexit('action=profile;u=' . $memID . ';area=lists;sa=buddies');
	}
*/

	if (isset($_GET['uli']) && $canChange)
	{
		checkSession('get');
		$data = explode('_', $_GET['uli']);

		// Deleting a contact from a list, or an entire list..?
		if (isset($_GET['del']))
		{
			cache_put_data('contacts_' . $memID, null, 3000);
			wesql::query('
				DELETE FROM {db_prefix}contacts
				WHERE ' . (isset($data[1]) ? 'id_member = {int:user}
				AND ' : '') . 'id_list = {int:list}' . ($canChangeOther ? '' : '
				AND id_owner = {int:me}'),
				array(
					'user' => isset($data[1]) ? $data[1] : 0,
					'list' => $data[0],
					'me' => $memID, // Make sure we're the legitimate owner!
				)
			);
			if (!isset($data[1]))
				wesql::query('
					DELETE FROM {db_prefix}contact_lists
					WHERE id_list = {int:list}' . ($canChangeOther ? '' : '
					AND id_owner = {int:me}'),
					array(
						'list' => $data[0],
						'me' => $memID, // Make sure we're the legitimate owner!
					)
				);
			return_raw('ok');
		}

		if (isset($_GET['order'], $_POST['order']))
		{
			$query = '
				UPDATE {db_prefix}contacts
				SET position = CASE id_member';

			foreach (explode(',', $_POST['order']) as $sort => $id)
				$query .= '
					WHEN ' . (int) $id . ' THEN ' . (int) $sort;

			$query .= '
					ELSE 0
				END
				WHERE id_list = {int:list}' . ($canChangeOther ? '' : '
				AND id_owner = {int:me}');

			wesql::query($query, array('list' => $data[0]));
			return_raw('ok');
		}

		if (isset($_GET['name']) || isset($_GET['type']) || isset($_GET['visi']))
		{
			$what = isset($_GET['name']) ? 'name' : (isset($_GET['type']) ? 'list_type' : (isset($_GET['visi']) ? 'visibility' : ''));
			$target = isset($_GET['name']) ? $_POST['target'] : (isset($_GET['type']) ? $_GET['type'] : (isset($_GET['visi']) ? $_GET['to'] : ''));
			cache_put_data('contacts_' . $memID, null, 3000);
			wesql::query('
				UPDATE {db_prefix}contact_lists
				SET ' . $what . ' = {string:target}
				WHERE id_list = {int:list}' . ($canChangeOther ? '' : '
				AND id_owner = {int:me}'),
				array(
					'target' => $target,
					'list' => $data[0],
					'me' => $memID,
				)
			);
			return_raw(isset($_GET['name']) ? generic_contacts($target) : $target);
		}

		wesql::query('
			UPDATE {db_prefix}contacts
			SET hidden = !hidden
			WHERE id_member = {int:user}
			AND id_list = {int:list}' . ($canChangeOther ? '' : '
			AND id_owner = {int:me}'),
			array(
				'user' => $data[1],
				'list' => $data[0],
				'me' => $memID,
			)
		);
		$request = wesql::query('
			SELECT hidden
			FROM {db_prefix}contacts
			WHERE id_member = {int:user}
			AND id_list = {int:list}',
			array(
				'user' => $data[1],
				'list' => $data[0],
			)
		);
		list ($hidden) = wesql::fetch_row($request);
		wesql::free_result($request);
		return_raw($hidden);
	}

	// Get the list IDs of your contact lists.
	$lists = array();
	$request = wesql::query('
		SELECT l.id_list, l.name, l.list_type, l.id_owner, l.visibility, l.added, l.position
		FROM {db_prefix}contact_lists AS l
		WHERE l.id_owner = {int:user}
		ORDER BY l.position, l.id_list',
		array(
			'user' => $memID,
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$lists[$row['id_list']] = array_merge($row, array('members' => array()));
	wesql::free_result($request);

	// Get the member IDs of your contacts.
	$contacts = array();
	$request = wesql::query('
		SELECT
			c.id_member, c.id_list, c.list_type, c.hidden, c.added, c.position,
			m.real_name, m.show_online, IFNULL(lo.log_time, 0) AS is_online
		FROM {db_prefix}contacts AS c
		INNER JOIN {db_prefix}members AS m ON m.id_member = c.id_member
		LEFT JOIN {db_prefix}log_online AS lo ON lo.id_member = c.id_member
		WHERE c.id_owner = {int:user}
		ORDER BY c.position, c.added',
		array(
			'user' => $memID,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$lists[$row['id_list']]['members'][$row['id_member']] = $row;
		$contacts[$row['id_member']] = $row;
	}
	wesql::free_result($request);

	$context['can_send_pm'] = allowedTo('pm_send');

	if (!empty($contacts))
	{
		$members = array_keys($contacts);
		loadMemberData($members, false, 'profile');
		loadMemberContext($members);
		loadMemberAvatar($members);
	}

	$context['profile_contacts'] = $contacts;
	$context['profile_lists'] = $lists;

	// !! This should be removed, once it's all set and done.
	// Get all the user's "buddies"...
/*
	$buddies = array();

	if (!empty($buddiesArray))
	{
		$result = wesql::query('
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:buddy_list})
			ORDER BY real_name
			LIMIT {int:buddy_list_count}',
			array(
				'buddy_list' => $buddiesArray,
				'buddy_list_count' => substr_count($user_profile[$memID]['buddy_list'], ',') + 1,
			)
		);
		while ($row = wesql::fetch_assoc($result))
			$buddies[] = $row['id_member'];
		wesql::free_result($result);
	}

	$context['buddy_count'] = count($buddies);

	// Load all the members up.
	loadMemberData($buddies, false, 'profile');

	// Setup the context for each buddy.
	$context['buddies'] = array();
	foreach ($buddies as $buddy)
	{
		loadMemberContext($buddy);
		$context['buddies'][$buddy] = $memberContext[$buddy];
	}
*/
}

// Show all the user's buddies, as well as an add/delete interface.
function editBuddies($memID)
{
	global $context, $user_profile, $memberContext;

	// For making changes!
	$buddiesArray = array_filter(explode(',', $user_profile[$memID]['buddy_list']));

	// Removing a buddy?
	if (isset($_GET['remove']))
	{
		checkSession('get');

		// Heh, I'm lazy, do it the easy way...
		foreach ($buddiesArray as $key => $buddy)
			if ($buddy == (int) $_GET['remove'])
				unset($buddiesArray[$key]);

		// Make the changes.
		$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
		updateMemberData($memID, array('buddy_list' => $user_profile[$memID]['buddy_list']));

		// Redirect off the page because we don't like all this ugly query stuff to stick in the history.
		redirectexit('action=profile;u=' . $memID . ';area=lists;sa=buddies');
	}
	elseif (isset($_POST['new_buddy']))
	{
		// Prepare the string for extraction...
		$_POST['new_buddy'] = strtr(westr::htmlspecialchars($_POST['new_buddy'], ENT_QUOTES), array('&quot;' => '"'));
		preg_match_all('~"([^"]+)"~', $_POST['new_buddy'], $matches);
		$new_buddies = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_buddy']))));

		foreach ($new_buddies as $k => $dummy)
		{
			$new_buddies[$k] = strtr(trim($new_buddies[$k]), array("'" => '&#039;'));

			if (strlen($new_buddies[$k]) == 0 || in_array($new_buddies[$k], array($user_profile[$memID]['member_name'], $user_profile[$memID]['real_name'])))
				unset($new_buddies[$k]);
		}

		if (!empty($new_buddies))
		{
			// Now find out the id_member of the buddy.
			$request = wesql::query('
				SELECT id_member
				FROM {db_prefix}members
				WHERE member_name IN ({array_string:new_buddies}) OR real_name IN ({array_string:new_buddies})
				LIMIT {int:count_new_buddies}',
				array(
					'new_buddies' => $new_buddies,
					'count_new_buddies' => count($new_buddies),
				)
			);

			// Add the new member to the buddies array.
			while ($row = wesql::fetch_assoc($request))
				$buddiesArray[] = (int) $row['id_member'];
			wesql::free_result($request);
			$buddiesArray = array_flip(array_flip(array_filter($buddiesArray)));

			// Now update the current users buddy list.
			$user_profile[$memID]['buddy_list'] = implode(',', $buddiesArray);
			updateMemberData($memID, array('buddy_list' => $user_profile[$memID]['buddy_list']));
		}

		// Back to the buddy list!
		redirectexit('action=profile;u=' . $memID . ';area=lists;sa=buddies');
	}

	// Get all the users "buddies"...
	$buddies = array();

	if (!empty($buddiesArray))
	{
		$result = wesql::query('
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:buddy_list})
			ORDER BY real_name
			LIMIT {int:buddy_list_count}',
			array(
				'buddy_list' => $buddiesArray,
				'buddy_list_count' => substr_count($user_profile[$memID]['buddy_list'], ',') + 1,
			)
		);
		while ($row = wesql::fetch_assoc($result))
			$buddies[] = $row['id_member'];
		wesql::free_result($result);
	}

	$context['buddy_count'] = count($buddies);

	// Load all the members up.
	loadMemberData($buddies, false, 'profile');

	// Setup the context for each buddy.
	$context['buddies'] = array();
	foreach ($buddies as $buddy)
	{
		loadMemberContext($buddy);
		$context['buddies'][$buddy] = $memberContext[$buddy];
	}
}

// Allows the user to view their ignore list, as well as the option to manage members on it.
function editIgnoreList($memID)
{
	global $context, $user_profile, $memberContext;

	// For making changes!
	$ignoreArray = explode(',', $user_profile[$memID]['pm_ignore_list']);
	foreach ($ignoreArray as $k => $dummy)
		if ($dummy == '')
			unset($ignoreArray[$k]);

	// Adding a member to, or removing from the ignore list?
	if ((isset($_GET['add']) && ((int) $_GET['add'] > 0)) || (isset($_GET['remove']) && ((int) $_GET['remove'] > 0)))
	{
		checkSession('get');

		if (isset($_GET['add']))
		{
			if ($_GET['add'] != $memID && !in_array($_GET['add'], $ignoreArray))
				$ignoreArray[] = $_GET['add'];
		}
		elseif (isset($_GET['remove']))
		{
			// Heh, I'm lazy, do it the easy way...
			foreach ($ignoreArray as $key => $id_remove)
				if ($id_remove == (int) $_GET['remove'])
					unset($ignoreArray[$key]);
		}

		// Make the changes.
		$user_profile[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
		updateMemberData($memID, array('pm_ignore_list' => $user_profile[$memID]['pm_ignore_list']));

		if (isset($_GET['msg']) && ((int) $_GET['msg'] > 0))
		{
			$request = wesql::query('
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:msg}',
				array(
					'msg' => (int) $_GET['msg'],
				)
			);
			if (wesql::num_rows($request) == 1)
			{
				list ($topic) = wesql::fetch_row($request);
				redirectexit('topic=' . $topic . '.msg' . $_GET['msg'] . '#msg' . $_GET['msg']);
			}
			wesql::free_result($request);
		}

		redirectexit('action=profile;u=' . $memID . ';area=lists;sa=ignore');
	}
	elseif (isset($_POST['new_ignore']))
	{
		// Prepare the string for extraction...
		$_POST['new_ignore'] = strtr(westr::htmlspecialchars($_POST['new_ignore'], ENT_QUOTES), array('&quot;' => '"'));
		preg_match_all('~"([^"]+)"~', $_POST['new_ignore'], $matches);
		$new_entries = array_unique(array_merge($matches[1], explode(',', preg_replace('~"[^"]+"~', '', $_POST['new_ignore']))));

		foreach ($new_entries as $k => $dummy)
		{
			$new_entries[$k] = strtr(trim($new_entries[$k]), array("'" => '&#039;'));

			if (strlen($new_entries[$k]) == 0 || in_array($new_entries[$k], array($user_profile[$memID]['member_name'], $user_profile[$memID]['real_name'])))
				unset($new_entries[$k]);
		}

		if (!empty($new_entries))
		{
			// Now find out the id_member for the members in question.
			$request = wesql::query('
				SELECT id_member
				FROM {db_prefix}members
				WHERE member_name IN ({array_string:new_entries}) OR real_name IN ({array_string:new_entries})
				LIMIT {int:count_new_entries}',
				array(
					'new_entries' => $new_entries,
					'count_new_entries' => count($new_entries),
				)
			);

			// Add the new member to the buddies array.
			while ($row = wesql::fetch_assoc($request))
				$ignoreArray[] = (int) $row['id_member'];
			wesql::free_result($request);

			// Now update the current users buddy list.
			$user_profile[$memID]['pm_ignore_list'] = implode(',', $ignoreArray);
			updateMemberData($memID, array('pm_ignore_list' => $user_profile[$memID]['pm_ignore_list']));
		}

		// Back to the list of pityful people!
		redirectexit('action=profile;u=' . $memID . ';area=lists;sa=ignore');
	}

	// Initialize the list of members we're ignoring.
	$ignored = array();

	if (!empty($ignoreArray))
	{
		$result = wesql::query('
			SELECT id_member
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:ignore_list})
			ORDER BY real_name
			LIMIT {int:ignore_list_count}',
			array(
				'ignore_list' => $ignoreArray,
				'ignore_list_count' => substr_count($user_profile[$memID]['pm_ignore_list'], ',') + 1,
			)
		);
		while ($row = wesql::fetch_assoc($result))
			$ignored[] = $row['id_member'];
		wesql::free_result($result);
	}

	$context['ignore_count'] = count($ignored);

	// Load all the members up.
	loadMemberData($ignored, false, 'profile');

	// Setup the context for each buddy.
	$context['ignore_list'] = array();
	foreach ($ignored as $ignore_member)
	{
		loadMemberContext($ignore_member);
		$context['ignore_list'][$ignore_member] = $memberContext[$ignore_member];
	}
}

function account($memID)
{
	global $context, $txt;

	loadThemeOptions($memID);
	if (allowedTo(array('profile_identity_own', 'profile_identity_any')))
		loadCustomFields($memID, 'account');

	wetem::load('edit_options');
	$context['page_desc'] = $txt['account_info'];

	setupProfileContext(
		array(
			'member_name', 'real_name', 'date_registered', 'posts', 'lngfile', 'hr',
			'id_group', 'hr',
			'email_address', 'hide_email', 'show_online', 'hr',
			'passwrd1', 'passwrd2', 'hr',
			'secret_question', 'secret_answer',
		)
	);
}

function forumProfile($memID)
{
	global $context, $txt;

	loadThemeOptions($memID);
	if (allowedTo(array('profile_extra_own', 'profile_extra_any')))
		loadCustomFields($memID, 'forumprofile');

	wetem::load('edit_options');
	$context['page_desc'] = str_replace('{forum_name_safe}', $context['forum_name_html_safe'], $txt['forumProfile_info']);

	setupProfileContext(
		array(
			'avatar_choice', 'hr',
			'bday1', 'location', 'gender', 'hr',
			'personal_text', 'usertitle', 'signature', 'hr',
			'website_title', 'website_url',
		)
	);
}

// Allow the edit of *someone else's* personal message settings.
function pmprefs($memID)
{
	global $context, $txt;

	loadThemeOptions($memID);
	loadCustomFields($memID, 'pmprefs');

	wetem::load('edit_options');
	$context['page_desc'] = $txt['pm_settings_desc'];

	setupProfileContext(
		array(
			'pm_prefs',
		)
	);
}

// Recursive function to retrieve avatar files
function getAvatars($directory, $level)
{
	global $context, $txt;

	$result = array();

	// Open the directory..
	$dir = dir(ASSETS_DIR . '/avatars' . (!empty($directory) ? '/' : '') . $directory);
	$dirs = array();
	$files = array();

	if (!$dir)
		return array();

	while ($line = $dir->read())
	{
		if (in_array($line, array('.', '..', 'blank.gif', 'index.php')))
			continue;

		if (is_dir(ASSETS_DIR . '/avatars/' . $directory . (!empty($directory) ? '/' : '') . $line))
			$dirs[] = $line;
		else
			$files[] = $line;
	}
	$dir->close();

	// Sort the results...
	natcasesort($dirs);
	natcasesort($files);

	if ($level == 0)
	{
		$result[] = array(
			'filename' => 'blank.gif',
			'checked' => in_array($context['member']['avatar']['server_pic'], array('', 'blank.gif')),
			'name' => $txt['no_pic'],
			'is_dir' => false
		);
	}

	foreach ($dirs as $line)
	{
		$tmp = getAvatars($directory . (!empty($directory) ? '/' : '') . $line, $level + 1);
		if (!empty($tmp))
			$result[] = array(
				'filename' => htmlspecialchars($line),
				'checked' => strpos($context['member']['avatar']['server_pic'], $line . '/') !== false,
				'name' => '[' . htmlspecialchars(str_replace('_', ' ', $line)) . ']',
				'is_dir' => true,
				'files' => $tmp
		);
		unset($tmp);
	}

	foreach ($files as $line)
	{
		$filename = substr($line, 0, (strlen($line) - strlen(strrchr($line, '.'))));
		$extension = substr(strrchr($line, '.'), 1);

		// Make sure it is an image.
		if (strcasecmp($extension, 'gif') != 0 && strcasecmp($extension, 'jpg') != 0 && strcasecmp($extension, 'jpeg') != 0 && strcasecmp($extension, 'png') != 0 && strcasecmp($extension, 'bmp') != 0)
			continue;

		$result[] = array(
			'filename' => htmlspecialchars($line),
			'checked' => $line == $context['member']['avatar']['server_pic'],
			'name' => htmlspecialchars(str_replace('_', ' ', $filename)),
			'is_dir' => false
		);
		if ($level == 1)
			$context['avatar_list'][] = $directory . '/' . $line;
	}

	return $result;
}

function options($memID)
{
	global $txt, $context;

	loadThemeOptions($memID);
	if (allowedTo(array('profile_extra_own', 'profile_extra_any')))
		loadCustomFields($memID, 'options');

	wetem::load('edit_options');
	$context['page_desc'] = $txt['options_info'];

	// Now we need to load the remainder of the member options.
	loadSource('ManageMemberOptions');
	$context['member_options'] = ModifyMemberOptions(true);
	// We will need to do some work on these before displaying, however.
	processMemberPrefs('looklayout');

	setupProfileContext(
		array(
			'smiley_set', 'hr',
			'time_format', 'timezone', 'hr',
			'prefslist',
		)
	);
}

// Display the notifications and settings for changes.
function notification($memID)
{
	global $txt, $user_profile, $context, $settings;

	// Gonna want this for the list.
	loadSource('Subs-List');

	// Fine, start with the board list.
	$listOptions = array(
		'id' => 'board_notification_list',
		'width' => '100%',
		'no_items_label' => $txt['notifications_boards_none'] . '<br><br>' . $txt['notifications_boards_howto'],
		'no_items_align' => 'left',
		'base_href' => '<URL>?action=profile;u=' . $memID . ';area=notifications;sa=posts',
		'default_sort_col' => 'board_name',
		'get_items' => array(
			'function' => 'list_getBoardNotifications',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'board_name' => array(
				'header' => array(
					'value' => $txt['notifications_boards'],
					'class' => 'left',
				),
				'data' => array(
					'function' => function ($board) {
						return $board['link'];
					},
				),
				'sort' => array(
					'default' => 'name',
					'reverse' => 'name DESC',
				),
			),
			'delete' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'style' => 'width: 4%',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="notify_boards[]" value="%1$d">',
						'params' => array(
							'id' => false,
						),
					),
					'style' => 'text-align: center',
				),
			),
		),
		'form' => array(
			'href' => '<URL>?action=profile;area=notifications;sa=posts;save',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				'u' => $memID,
				'sa' => $context['menu_item_selected'],
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="edit_notify_boards" value="' . $txt['notifications_update'] . '" class="submit">',
				'align' => 'right',
			),
		),
	);

	// Create the board notification list.
	createList($listOptions);

	// Now do the topic notifications.
	$listOptions = array(
		'id' => 'topic_notification_list',
		'width' => '100%',
		'items_per_page' => $settings['defaultMaxMessages'],
		'no_items_label' => $txt['notifications_topics_none'] . '<br><br>' . $txt['notifications_topics_howto'],
		'no_items_align' => 'left',
		'base_href' => '<URL>?action=profile;u=' . $memID . ';area=notifications;sa=posts',
		'default_sort_col' => 'last_post',
		'get_items' => array(
			'function' => 'list_getTopicNotifications',
			'params' => array(
				$memID,
			),
		),
		'get_count' => array(
			'function' => 'list_getTopicNotificationCount',
			'params' => array(
				$memID,
			),
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => $txt['notifications_topics'],
					'class' => 'left',
				),
				'data' => array(
					'function' => function ($topic) {
						global $txt;
						return $topic['link'] . '<div class="smalltext"><em>' . $txt['in'] . ' ' . $topic['board_link'] . '</em></div>';
					},
				),
				'sort' => array(
					'default' => 'ms.subject',
					'reverse' => 'ms.subject DESC',
				),
			),
			'started_by' => array(
				'header' => array(
					'value' => $txt['started_by'],
					'class' => 'left',
				),
				'data' => array(
					'db' => 'poster_link',
				),
				'sort' => array(
					'default' => 'real_name_col',
					'reverse' => 'real_name_col DESC',
				),
			),
			'last_post' => array(
				'header' => array(
					'value' => $txt['last_post'],
						'class' => 'left',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<span class="smalltext">%1$s<br>' . $txt['by'] . ' %2$s</span>',
						'params' => array(
							'updated' => false,
							'poster_updated_link' => false,
						),
					),
				),
				'sort' => array(
					'default' => 'ml.id_msg DESC',
					'reverse' => 'ml.id_msg',
				),
			),
			'delete' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
					'style' => 'width: 4%',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="notify_topics[]" value="%1$d">',
						'params' => array(
							'id' => false,
						),
					),
					'style' => 'text-align: center',
				),
			),
		),
		'form' => array(
			'href' => '<URL>?action=profile;area=notifications;sa=posts;save',
			'include_sort' => true,
			'include_start' => true,
			'hidden_fields' => array(
				'u' => $memID,
				'sa' => $context['menu_item_selected'],
				$context['session_var'] => $context['session_id'],
			),
		),
		'additional_rows' => array(
			array(
				'position' => 'bottom_of_list',
				'value' => '<input type="submit" name="edit_notify_topics" value="' . $txt['notifications_update'] . '" class="submit">',
				'align' => 'right',
			),
		),
	);

	// Create the notification list.
	createList($listOptions);

	// What options are set?
	$context['member'] += array(
		'notify_announcements' => $user_profile[$memID]['notify_announcements'],
		'notify_send_body' => $user_profile[$memID]['notify_send_body'],
		'notify_types' => $user_profile[$memID]['notify_types'],
		'notify_regularity' => $user_profile[$memID]['notify_regularity'],
	);

	loadThemeOptions($memID);
}

function list_getTopicNotificationCount($memID)
{
	$request = wesql::query('
		SELECT COUNT(*)
		FROM {db_prefix}log_notify AS ln' . (we::$user['query_see_topic'] === '1=1' ? '' : '
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic AND {query_see_topic})') . (we::$user['query_see_board'] === '1=1' ? '' : '
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})') . '
		WHERE ln.id_member = {int:selected_member}',
		array(
			'selected_member' => $memID,
		)
	);
	list ($totalNotifications) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $totalNotifications;
}

function list_getTopicNotifications($start, $items_per_page, $sort, $memID)
{
	// All the topics with notification on...
	$request = wesql::query('
		SELECT
			IFNULL(lt.id_msg, IFNULL(lmr.id_msg, -1)) + 1 AS new_from, b.id_board, b.name,
			t.id_topic, ms.subject, ms.id_member, IFNULL(mem.real_name, ms.poster_name) AS real_name_col,
			ml.id_msg_modified, ml.poster_time, ml.id_member AS id_member_updated,
			IFNULL(mem2.real_name, ml.poster_name) AS last_real_name
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = ln.id_topic AND {query_see_topic})
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board AND {query_see_board})
			INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = ms.id_member)
			LEFT JOIN {db_prefix}members AS mem2 ON (mem2.id_member = ml.id_member)
			LEFT JOIN {db_prefix}log_topics AS lt ON (lt.id_topic = t.id_topic AND lt.id_member = {int:current_member})
			LEFT JOIN {db_prefix}log_mark_read AS lmr ON (lmr.id_board = b.id_board AND lmr.id_member = {int:current_member})
		WHERE ln.id_member = {int:selected_member}
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:items_per_page}',
		array(
			'current_member' => MID,
			'selected_member' => $memID,
			'sort' => $sort,
			'offset' => $start,
			'items_per_page' => $items_per_page,
		)
	);
	$notification_topics = array();
	while ($row = wesql::fetch_assoc($request))
	{
		censorText($row['subject']);

		$notification_topics[] = array(
			'id' => $row['id_topic'],
			'poster_link' => empty($row['id_member']) ? $row['real_name_col'] : '<a href="<URL>?action=profile;u=' . $row['id_member'] . '">' . $row['real_name_col'] . '</a>',
			'poster_updated_link' => empty($row['id_member_updated']) ? $row['last_real_name'] : '<a href="<URL>?action=profile;u=' . $row['id_member_updated'] . '">' . $row['last_real_name'] . '</a>',
			'subject' => $row['subject'],
			'href' => '<URL>?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="<URL>?topic=' . $row['id_topic'] . '.0">' . $row['subject'] . '</a>',
			'new' => $row['new_from'] <= $row['id_msg_modified'],
			'new_from' => $row['new_from'],
			'updated' => timeformat($row['poster_time']),
			'new_href' => '<URL>?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new',
			'new_link' => '<a href="<URL>?topic=' . $row['id_topic'] . '.msg' . $row['new_from'] . '#new">' . $row['subject'] . '</a>',
			'board_link' => '<a href="<URL>?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
		);
	}
	wesql::free_result($request);

	return $notification_topics;
}

function list_getBoardNotifications($start, $items_per_page, $sort, $memID)
{
	$request = wesql::query('
		SELECT b.id_board, b.name
		FROM {db_prefix}log_notify AS ln
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = ln.id_board)
		WHERE ln.id_member = {int:selected_member}
			AND {query_see_board}
		ORDER BY ' . $sort,
		array(
			'current_member' => MID,
			'selected_member' => $memID,
		)
	);
	$notification_boards = array();
	while ($row = wesql::fetch_assoc($request))
		$notification_boards[] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'href' => '<URL>?board=' . $row['id_board'] . '.0',
			'link' => '<a href="<URL>?board=' . $row['id_board'] . '.0">' . $row['name'] . '</a>',
		);
	wesql::free_result($request);

	return $notification_boards;
}

function loadThemeOptions($memID)
{
	global $context, $options;

	if (isset($_POST['default_options']))
		$_POST['options'] = isset($_POST['options']) ? $_POST['options'] + $_POST['default_options'] : $_POST['default_options'];

	if (we::$user['is_owner'])
	{
		$context['member']['options'] = $options;
		if (isset($_POST['options']) && is_array($_POST['options']))
			foreach ($_POST['options'] as $k => $v)
				$context['member']['options'][$k] = $v;
	}
	else
	{
		$request = wesql::query('
			SELECT id_member, variable, value
			FROM {db_prefix}themes
			WHERE id_member IN (-1, {int:selected_member})',
			array(
				'selected_member' => $memID,
			)
		);
		$temp = array();
		while ($row = wesql::fetch_assoc($request))
		{
			if ($row['id_member'] == -1)
			{
				$temp[$row['variable']] = $row['value'];
				continue;
			}

			if (isset($_POST['options'][$row['variable']]))
				$row['value'] = $_POST['options'][$row['variable']];
			$context['member']['options'][$row['variable']] = $row['value'];
		}
		wesql::free_result($request);

		// Load up the default theme options for any missing.
		foreach ($temp as $k => $v)
		{
			if (!isset($context['member']['options'][$k]))
				$context['member']['options'][$k] = $v;
		}
	}
}

function ignoreboards($memID)
{
	global $context, $settings, $cur_profile;

	// Have the admins enabled this option?
	if (empty($settings['ignorable_boards']))
		fatal_lang_error('ignoreboards_disallowed', 'user');

	$ignorable_boards = unserialize($settings['ignorable_boards']);

	// Find all the boards this user is allowed to see.
	$request = wesql::query('
		SELECT b.id_cat, c.name AS cat_name, b.id_board, b.name, b.child_level,
			' . (!empty($cur_profile['ignore_boards']) ? 'b.id_board IN ({array_int:ignore_boards})' : '0') . ' AS is_ignored
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)
		WHERE {query_see_board}
			AND redirect = {string:empty_string}
		ORDER BY b.board_order',
		array(
			'ignore_boards' => !empty($cur_profile['ignore_boards']) ? explode(',', $cur_profile['ignore_boards']) : array(),
			'empty_string' => '',
		)
	);
	$context['num_boards'] = wesql::num_rows($request);
	$context['categories'] = array();
	while ($row = wesql::fetch_assoc($request))
	{
		// This category hasn't been set up yet..
		if (!isset($context['categories'][$row['id_cat']]))
			$context['categories'][$row['id_cat']] = array(
				'id' => $row['id_cat'],
				'name' => $row['cat_name'],
				'boards' => array()
			);

		// Set this board up, and let the template know when it's a child. (Indent them..)
		$context['categories'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'id' => $row['id_board'],
			'name' => $row['name'],
			'child_level' => $row['child_level'],
			'selected' => $row['is_ignored'],
			'enabled' => in_array($row['id_board'], $ignorable_boards),
		);
	}
	wesql::free_result($request);

	// Now, let's sort the list of categories into the boards for templates that like that.
	$temp_boards = array();
	foreach ($context['categories'] as $category)
	{
		// Include a list of boards per category for easy toggling.
		$context['categories'][$category['id']]['child_ids'] = array_keys($category['boards']);

		$temp_boards[] = array(
			'name' => $category['name'],
			'child_ids' => array_keys($category['boards'])
		);
		$temp_boards = array_merge($temp_boards, array_values($category['boards']));
	}

	$max_boards = ceil(count($temp_boards) / 2);
	if ($max_boards == 1)
		$max_boards = 2;

	// Now, alternate them so they can be shown left and right ;).
	$context['board_columns'] = array();
	for ($i = 0; $i < $max_boards; $i++)
	{
		$context['board_columns'][] = $temp_boards[$i];
		if (isset($temp_boards[$i + $max_boards]))
			$context['board_columns'][] = $temp_boards[$i + $max_boards];
		else
			$context['board_columns'][] = array();
	}

	loadThemeOptions($memID);
}

// ModifyMemberPreferences provides all possible preferences. This has to process them to exclude disabled items, and items not of $type.
// That way, we have generic preferences 'profile fields'.
function processMemberPrefs($type)
{
	global $context;
	$new_items = array();
	$last = array();
	foreach ($context['member_options'] as $key => $pref)
	{
		if (is_array($pref) && (!empty($pref['disabled']) || empty($pref['display']) || $pref['display'] != $type))
			continue;

		if ($pref != $last)
			if (!is_array($pref))
				$new_items[] = '';
			else
				$new_items[$key] = $pref;

		$last = $pref;
	}

	$context['member_options'] = $new_items;
}

// Load all the languages for the profile.
function profileLoadLanguages()
{
	global $context;

	$context['profile_languages'] = array();

	// Get our languages!
	getLanguages();

	// Setup our languages. There's no funny business, we only have one set of language files per language these days.
	foreach ($context['languages'] as $lang)
		$context['profile_languages'][$lang['filename']] = '&lt;span class="flag_' . $lang['filename'] . '"&gt;&lt;/span&gt; ' . $lang['name'];

	ksort($context['profile_languages']);

	// Return whether we should proceed with this.
	return count($context['profile_languages']) > 1 ? true : false;
}

// Load all the group info for the profile.
function profileLoadGroups()
{
	global $cur_profile, $txt, $context, $user_settings;

	$context['member_groups'] = array(
		0 => array(
			'id' => 0,
			'name' => $txt['no_primary_membergroup'],
			'is_primary' => $cur_profile['id_group'] == 0,
			'can_be_additional' => false,
			'can_be_primary' => true,
			'badge' => '',
			'show_when' => 0,
		)
	);
	$curGroups = explode(',', $cur_profile['additional_groups']);

	// Load membergroups, but only those groups the user can assign.
	$request = wesql::query('
		SELECT group_name, id_group, hidden, stars, show_when
		FROM {db_prefix}membergroups
		WHERE id_group != {int:moderator_group}
			AND min_posts = {int:min_posts}' . (allowedTo('admin_forum') ? '' : '
			AND group_type != {int:is_protected}') . '
		ORDER BY min_posts, CASE WHEN id_group < {int:newbie_group} THEN id_group ELSE 4 END, group_name',
		array(
			'moderator_group' => 3,
			'min_posts' => -1,
			'is_protected' => 1,
			'newbie_group' => 4,
		)
	);
	$lng = we::$user['language'];
	while ($row = wesql::fetch_assoc($request))
	{
		// We should skip the administrator group if they don't have the admin_forum permission!
		if ($row['id_group'] == 1 && !allowedTo('admin_forum'))
			continue;

		$badge = '';
		if (!empty($row['show_when']))
		{
			$stars = explode('#', $row['stars']);
			if (!empty($stars[0]) && !empty($stars[1]))
				$badge = str_repeat('<img src="' . str_replace('$language', $lng, ASSETS . '/' . $stars[1]) . '">', $stars[0]);
		}

		$context['member_groups'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'is_primary' => $cur_profile['id_group'] == $row['id_group'],
			'is_additional' => in_array($row['id_group'], $curGroups),
			'can_be_additional' => true,
			'can_be_primary' => $row['hidden'] != 2,
			'badge' => $badge,
			'show_when' => $row['show_when'],
		);
	}
	wesql::free_result($request);

	$context['member']['group_id'] = $user_settings['id_group'];

	return true;
}

// Load key signature context data.
function profileLoadSignatureData()
{
	global $settings, $context, $txt, $cur_profile;

	// Signature limits.
	list ($sig_limits, $sig_bbc) = explode(':', $settings['signature_settings']);
	$sig_limits = explode(',', $sig_limits);

	$context['signature_enabled'] = isset($sig_limits[0]) ? $sig_limits[0] : 0;
	$context['signature_limits'] = array(
		'max_length' => isset($sig_limits[1]) ? $sig_limits[1] : 0,
		'max_lines' => isset($sig_limits[2]) ? $sig_limits[2] : 0,
		'max_images' => isset($sig_limits[3]) ? $sig_limits[3] : 0,
		'max_smileys' => isset($sig_limits[4]) ? $sig_limits[4] : 0,
		'max_image_width' => isset($sig_limits[5]) ? $sig_limits[5] : 0,
		'max_image_height' => isset($sig_limits[6]) ? $sig_limits[6] : 0,
		'max_font_size' => isset($sig_limits[7]) ? $sig_limits[7] : 0,
		'bbc' => !empty($sig_bbc) ? explode(',', $sig_bbc) : array(),
	);
	// Kept this line in for backwards compatibility!
	$context['max_signature_length'] = $context['signature_limits']['max_length'];
	// Warning message for signature image limits?
	$context['signature_warning'] = '';
	if ($context['signature_limits']['max_image_width'] && $context['signature_limits']['max_image_height'])
		$context['signature_warning'] = sprintf($txt['profile_error_signature_max_image_size'], $context['signature_limits']['max_image_width'], $context['signature_limits']['max_image_height']);
	elseif ($context['signature_limits']['max_image_width'] || $context['signature_limits']['max_image_height'])
		$context['signature_warning'] = sprintf($txt['profile_error_signature_max_image_' . ($context['signature_limits']['max_image_width'] ? 'width' : 'height')], $context['signature_limits'][$context['signature_limits']['max_image_width'] ? 'max_image_width' : 'max_image_height']);

	$context['member']['signature'] = empty($cur_profile['signature']) ? '' : str_replace(array('<br>', '<', '>', '"', "'"), array("\n", '&lt;', '&gt;', '&quot;', '&#039;'), $cur_profile['signature']);

	if (!empty($settings['signature_minposts']) && (int) $cur_profile['posts'] < (int) $settings['signature_minposts'] && $cur_profile['id_group'] != 1)
		$context['signature_minposts'] = number_context('signature_needs_posts', $settings['signature_minposts']);

	return true;
}

// Load avatar context data.
function profileLoadAvatarData()
{
	global $context, $cur_profile, $settings;

	// Default context.
	$context['member']['avatar'] += array(
		'custom' => stristr($cur_profile['avatar'], 'http://') ? $cur_profile['avatar'] : 'http://',
		'selection' => $cur_profile['avatar'] == '' || stristr($cur_profile['avatar'], 'http://') ? '' : $cur_profile['avatar'],
		'id_attach' => $cur_profile['id_attach'],
		'filename' => $cur_profile['filename'],
		'allow_server_stored' => (empty($settings['gravatarEnabled']) || empty($settings['gravatarOverride'])) && (allowedTo('profile_server_avatar') || (!we::$user['is_owner'] && allowedTo('profile_extra_any'))),
		'allow_upload' => (empty($settings['gravatarEnabled']) || empty($settings['gravatarOverride'])) && (allowedTo('profile_upload_avatar') || (!we::$user['is_owner'] && allowedTo('profile_extra_any'))),
		'allow_external' => (empty($settings['gravatarEnabled']) || empty($settings['gravatarOverride'])) && (allowedTo('profile_remote_avatar') || (!we::$user['is_owner'] && allowedTo('profile_extra_any'))),
		'allow_gravatar' => !empty($settings['gravatarEnabled']),
	);

	if ($cur_profile['avatar'] == '' && $cur_profile['id_attach'] > 0 && $context['member']['avatar']['allow_upload'])
	{
		$context['member']['avatar'] += array(
			'choice' => 'upload',
			'server_pic' => 'blank.gif',
			'external' => 'http://'
		);
		$context['member']['avatar']['href'] = empty($cur_profile['attachment_type']) ? '<URL>?action=dlattach;attach=' . $cur_profile['id_attach'] . ';type=avatar' : $settings['custom_avatar_url'] . '/' . $cur_profile['filename'];
	}
	elseif (stristr($cur_profile['avatar'], 'http://') && $context['member']['avatar']['allow_external'])
		$context['member']['avatar'] += array(
			'choice' => 'external',
			'server_pic' => 'blank.gif',
			'external' => $cur_profile['avatar']
		);
	elseif (stristr($cur_profile['avatar'], 'gravatar://') && $context['member']['avatar']['allow_gravatar'])
		$context['member']['avatar'] += array(
			'choice' => 'gravatar',
			'server_pic' => 'blank.gif',
			'external' => $cur_profile['avatar'] === 'gravatar://' || empty($settings['gravatarAllowExtraEmail']) ? $cur_profile['email_address'] : substr($cur_profile['avatar'], 11),
		);
	elseif ($cur_profile['avatar'] != '' && file_exists(ASSETS_DIR . '/avatars/' . $cur_profile['avatar']) && $context['member']['avatar']['allow_server_stored'])
		$context['member']['avatar'] += array(
			'choice' => 'server_stored',
			'server_pic' => $cur_profile['avatar'] == '' ? 'blank.gif' : $cur_profile['avatar'],
			'external' => 'http://'
		);
	else
		$context['member']['avatar'] += array(
			'choice' => 'none',
			'server_pic' => 'blank.gif',
			'external' => 'http://'
		);

	// Get a list of all the avatars.
	if ($context['member']['avatar']['allow_server_stored'])
	{
		$context['avatar_list'] = array();
		$context['avatars'] = is_dir(ASSETS_DIR . '/avatars') ? getAvatars('', 0) : array();
	}
	else
		$context['avatars'] = array();

	// Second level selected avatar...
	$context['avatar_selected'] = substr(strrchr($context['member']['avatar']['server_pic'], '/'), 1);
	return true;
}

// Save a members group.
function profileSaveGroups(&$value)
{
	global $profile_vars, $old_profile, $context, $cur_profile;

	// Do we need to protect some groups?
	if (!allowedTo('admin_forum'))
	{
		$request = wesql::query('
			SELECT id_group
			FROM {db_prefix}membergroups
			WHERE group_type = {int:is_protected}',
			array(
				'is_protected' => 1,
			)
		);
		$protected_groups = array(1);
		while ($row = wesql::fetch_assoc($request))
			$protected_groups[] = $row['id_group'];
		wesql::free_result($request);

		$protected_groups = array_unique($protected_groups);
	}

	// The account page allows the change of your id_group - but not to a protected group!
	if (empty($protected_groups) || count(array_intersect(array((int) $value, $old_profile['id_group']), $protected_groups)) == 0)
		$value = (int) $value;
	// ... otherwise it's the old group sir.
	else
		$value = $old_profile['id_group'];

	// Find the additional membergroups (if any)
	if (isset($_POST['additional_groups']) && is_array($_POST['additional_groups']))
	{
		$additional_groups = array();
		foreach ($_POST['additional_groups'] as $group_id)
		{
			$group_id = (int) $group_id;
			if (!empty($group_id) && (empty($protected_groups) || !in_array($group_id, $protected_groups)))
				$additional_groups[] = $group_id;
		}

		// Put the protected groups back in there if you don't have permission to take them away.
		$old_additional_groups = explode(',', $old_profile['additional_groups']);
		foreach ($old_additional_groups as $group_id)
		{
			if (!empty($protected_groups) && in_array($group_id, $protected_groups))
				$additional_groups[] = $group_id;
		}

		if (implode(',', $additional_groups) !== $old_profile['additional_groups'])
		{
			$profile_vars['additional_groups'] = implode(',', $additional_groups);
			$cur_profile['additional_groups'] = implode(',', $additional_groups);
		}
	}

	// Too often, people remove delete their own account, or something.
	if (in_array(1, explode(',', $old_profile['additional_groups'])) || $old_profile['id_group'] == 1)
	{
		$stillAdmin = $value == 1 || (isset($additional_groups) && in_array(1, $additional_groups));

		// If they would no longer be an admin, look for any other...
		if (!$stillAdmin)
		{
			$request = wesql::query('
				SELECT id_member
				FROM {db_prefix}members
				WHERE (id_group = {int:admin_group} OR FIND_IN_SET({int:admin_group}, additional_groups) != 0)
					AND id_member != {int:selected_member}
				LIMIT 1',
				array(
					'admin_group' => 1,
					'selected_member' => $context['id_member'],
				)
			);
			list ($another) = wesql::fetch_row($request);
			wesql::free_result($request);

			if (empty($another))
				fatal_lang_error('at_least_one_admin', 'critical');
		}
	}

	// We've updated a member's groups. We should update this in case their color changes.
	cache_put_data('member-groups', null);

	// If we are changing group status, update permission cache as necessary.
	if ($value != $old_profile['id_group'] || isset($profile_vars['additional_groups']))
	{
		if (we::$user['is_owner'])
			$_SESSION['mc']['time'] = 0;
		else
			updateSettings(array('settings_updated' => time()));
	}

	return true;
}

// The avatar is incredibly complicated, what with the options... and what not.
function profileSaveAvatarData(&$value)
{
	global $settings, $profile_vars, $cur_profile, $context;

	$memID = $context['id_member'];
	if (empty($memID) && !empty($context['password_auth_failed']))
		return false;

	loadSource('ManageAttachments');

	// We need to know where we're going to be putting it..
	if (!empty($settings['custom_avatar_enabled']))
	{
		$uploadDir = $settings['custom_avatar_dir'];
		$id_folder = 1;
	}
	elseif (!empty($settings['currentAttachmentUploadDir']))
	{
		if (!is_array($settings['attachmentUploadDir']))
			$settings['attachmentUploadDir'] = unserialize($settings['attachmentUploadDir']);

		// Just use the current path for temp files.
		$uploadDir = $settings['attachmentUploadDir'][$settings['currentAttachmentUploadDir']];
		$id_folder = $settings['currentAttachmentUploadDir'];
	}
	else
	{
		$uploadDir = $settings['attachmentUploadDir'];
		$id_folder = 1;
	}

	$downloadedExternalAvatar = false;
	if ($value == 'external' && allowedTo('profile_remote_avatar') && strtolower(substr($_POST['userpicpersonal'], 0, 7)) == 'http://' && strlen($_POST['userpicpersonal']) > 7 && !empty($settings['avatar_download_external']))
	{
		if (!is_writable($uploadDir))
			fatal_lang_error('attachments_no_write', 'critical');

		loadSource('Class-WebGet');

		$url = parse_url($_POST['userpicpersonal']);
		try
		{
			$weget = new weget('http://' . $url['host'] . (empty($url['port']) ? '' : ':' . $url['port']) . str_replace(' ', '%20', trim($url['path'])));
			$contents = $weget->get();
		}
		catch (Exception $e)
		{
			$contents = false;
			return 'bad_avatar';
		}

		$new_filename = $uploadDir . '/' . getAttachmentFilename('avatar_tmp_' . $memID, false, null, true);
		if ($contents != false && $tmpAvatar = fopen($new_filename, 'wb'))
		{
			fwrite($tmpAvatar, $contents);
			fclose($tmpAvatar);

			$downloadedExternalAvatar = true;
			$_FILES['attachment']['tmp_name'] = $new_filename;
		}
	}

	if ($value == 'none')
	{
		$profile_vars['avatar'] = '';

		// Reset the attach ID.
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		removeAttachments(array('id_member' => $memID));
	}
	elseif ($value == 'gravatar' && !empty($settings['gravatarEnabled']))
	{
		// One wasn't specified, or it's not allowed to use extra email addresses, or it's not a valid one, reset to default Gravatar.
		if (empty($_POST['gravatarEmail']) || empty($settings['gravatarAllowExtraEmail']) || !is_valid_email($_POST['gravatarEmail']))
			$profile_vars['avatar'] = 'gravatar://';
		else
			$profile_vars['avatar'] = 'gravatar://' . ($_POST['gravatarEmail'] != $cur_profile['email_address'] ? $_POST['gravatarEmail'] : '');

		// Clear current profile...
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		// Get rid of their old avatar. (if uploaded.)
		removeAttachments(array('id_member' => $memID));
	}
	elseif ($value == 'server_stored' && allowedTo('profile_server_avatar'))
	{
		$profile_vars['avatar'] = strtr(empty($_POST['file']) ? (empty($_POST['cat']) ? '' : $_POST['cat']) : $_POST['file'], array('&amp;' => '&'));
		$profile_vars['avatar'] = preg_match('~^([][\w !@%*=#()&.,-]+/)?[][\w !@%*=#()&.,-]+$~', $profile_vars['avatar']) != 0 && preg_match('/\.\./', $profile_vars['avatar']) == 0 && file_exists(ASSETS_DIR . '/avatars/' . $profile_vars['avatar']) ? ($profile_vars['avatar'] == 'blank.gif' ? '' : $profile_vars['avatar']) : '';

		// Clear current profile...
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		// Get rid of their old avatar. (if uploaded.)
		removeAttachments(array('id_member' => $memID));
	}
	elseif ($value == 'external' && allowedTo('profile_remote_avatar') && strtolower(substr($_POST['userpicpersonal'], 0, 7)) == 'http://' && empty($settings['avatar_download_external']))
	{
		// We need these clean...
		$cur_profile['id_attach'] = 0;
		$cur_profile['attachment_type'] = 0;
		$cur_profile['filename'] = '';

		// Remove any attached avatar...
		removeAttachments(array('id_member' => $memID));

		$profile_vars['avatar'] = str_replace(' ', '%20', preg_replace('~action(?:=|%3d)(?!dlattach)~i', 'action-', $_POST['userpicpersonal']));

		if ($profile_vars['avatar'] == 'http://' || $profile_vars['avatar'] == 'http:///')
			$profile_vars['avatar'] = '';
		// Trying to make us do something we'll regret?
		elseif (substr($profile_vars['avatar'], 0, 7) != 'http://')
			return 'bad_avatar';
		// Should we check dimensions?
		elseif (!empty($settings['avatar_max_height_external']) || !empty($settings['avatar_max_width_external']))
		{
			// Now let's validate the avatar.
			$sizes = url_image_size($profile_vars['avatar']);

			if (is_array($sizes) && (($sizes[0] > $settings['avatar_max_width_external'] && !empty($settings['avatar_max_width_external'])) || ($sizes[1] > $settings['avatar_max_height_external'] && !empty($settings['avatar_max_height_external']))))
			{
				// Houston, we have a problem. The avatar is too large!!
				if ($settings['avatar_action_too_large'] == 'option_refuse')
					return 'bad_avatar';
				elseif ($settings['avatar_action_too_large'] == 'option_download_and_resize')
				{
					loadSource('Subs-Graphics');
					if (downloadAvatar($profile_vars['avatar'], $memID, $settings['avatar_max_width_external'], $settings['avatar_max_height_external']))
					{
						$profile_vars['avatar'] = '';
						$cur_profile['id_attach'] = $settings['new_avatar_data']['id'];
						$cur_profile['filename'] = $settings['new_avatar_data']['filename'];
						$cur_profile['attachment_type'] = $settings['new_avatar_data']['type'];
					}
					else
						return 'bad_avatar';
				}
			}
			elseif (empty($sizes))
				return 'bad_avatar';
		}
	}
	elseif (($value == 'upload' && allowedTo('profile_upload_avatar')) || $downloadedExternalAvatar)
	{
		if ((isset($_FILES['attachment']['name']) && $_FILES['attachment']['name'] != '') || $downloadedExternalAvatar)
		{
			// Get the dimensions of the image.
			if (!$downloadedExternalAvatar)
			{
				if (!is_writable($uploadDir))
					fatal_lang_error('attachments_no_write', 'critical');

				$new_filename = $uploadDir . '/' . getAttachmentFilename('avatar_tmp_' . $memID, false, null, true);
				if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $new_filename))
					fatal_lang_error('attach_timeout', 'critical');

				$_FILES['attachment']['tmp_name'] = $new_filename;
			}

			$sizes = @getimagesize($_FILES['attachment']['tmp_name']);

			// No size, then it's probably not a valid pic.
			if ($sizes === false)
			{
				@unlink($_FILES['attachment']['tmp_name']);
				return 'bad_avatar';
			}
			// Check whether the image is too large.
			elseif ((!empty($settings['avatar_max_width_upload']) && $sizes[0] > $settings['avatar_max_width_upload']) || (!empty($settings['avatar_max_height_upload']) && $sizes[1] > $settings['avatar_max_height_upload']))
			{
				if (!empty($settings['avatar_resize_upload']))
				{
					// Attempt to chmod it.
					@chmod($_FILES['attachment']['tmp_name'], 0644);

					loadSource('Subs-Graphics');
					if (!downloadAvatar($_FILES['attachment']['tmp_name'], $memID, $settings['avatar_max_width_upload'], $settings['avatar_max_height_upload']))
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}

					// Reset attachment avatar data.
					$cur_profile['id_attach'] = $settings['new_avatar_data']['id'];
					$cur_profile['filename'] = $settings['new_avatar_data']['filename'];
					$cur_profile['attachment_type'] = $settings['new_avatar_data']['type'];
				}
				else
				{
					@unlink($_FILES['attachment']['tmp_name']);
					return 'bad_avatar';
				}
			}
			elseif (is_array($sizes))
			{
				// Now try to find an infection.
				loadSource('Subs-Graphics');
				if (!checkImageContents($_FILES['attachment']['tmp_name'], !empty($settings['avatar_paranoid'])))
				{
					// It's bad. Try to re-encode the contents?
					if (empty($settings['avatar_reencode']) || (!reencodeImage($_FILES['attachment']['tmp_name'], $sizes[2])))
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}
					// We were successful. However, at what price?
					$sizes = @getimagesize($_FILES['attachment']['tmp_name']);
					// Hard to believe this would happen, but can you bet?
					if ($sizes === false)
					{
						@unlink($_FILES['attachment']['tmp_name']);
						return 'bad_avatar';
					}
				}

				$extensions = array(
					'1' => 'gif',
					'2' => 'jpg',
					'3' => 'png',
					'6' => 'bmp'
				);

				$extension = isset($extensions[$sizes[2]]) ? $extensions[$sizes[2]] : 'bmp';
				$mime_type = 'image/' . ($extension === 'jpg' ? 'jpeg' : ($extension === 'bmp' ? 'x-ms-bmp' : $extension));
				$destName = 'avatar_' . $memID . '_' . time() . '.' . $extension;
				list ($width, $height) = getimagesize($_FILES['attachment']['tmp_name']);
				$file_hash = empty($settings['custom_avatar_enabled']) ? getAttachmentFilename($destName, false, null, true) : '';

				// Remove previous attachments this member might have had.
				removeAttachments(array('id_member' => $memID));

				wesql::insert('',
					'{db_prefix}attachments',
					array(
						'id_member' => 'int', 'attachment_type' => 'int', 'filename' => 'string', 'file_hash' => 'string', 'fileext' => 'string', 'size' => 'int',
						'width' => 'int', 'height' => 'int', 'mime_type' => 'string', 'id_folder' => 'int',
					),
					array(
						$memID, (empty($settings['custom_avatar_enabled']) ? 0 : 1), $destName, $file_hash, $extension, filesize($_FILES['attachment']['tmp_name']),
						(int) $width, (int) $height, $mime_type, $id_folder,
					)
				);

				$cur_profile['id_attach'] = wesql::insert_id();
				$cur_profile['filename'] = $destName;
				$cur_profile['attachment_type'] = empty($settings['custom_avatar_enabled']) ? 0 : 1;

				$destinationPath = $uploadDir . '/' . (empty($file_hash) ? $destName : $cur_profile['id_attach'] . '_' . $file_hash . '.ext');
				if (!rename($_FILES['attachment']['tmp_name'], $destinationPath))
				{
					// I guess a man can try.
					removeAttachments(array('id_member' => $memID));
					fatal_lang_error('attach_timeout', 'critical');
				}

				// Attempt to chmod it.
				@chmod($uploadDir . '/' . $destinationPath, 0644);
			}
			$profile_vars['avatar'] = '';

			// Delete temporary files, if any.
			if (file_exists($_FILES['attachment']['tmp_name']))
				@unlink($_FILES['attachment']['tmp_name']);
		}
		// Selected the upload avatar option and had one already uploaded before or didn't upload one.
		else
			$profile_vars['avatar'] = '';
	}
	else
		$profile_vars['avatar'] = '';

	// Setup the profile variables so it shows things right on display!
	$cur_profile['avatar'] = $profile_vars['avatar'];

	return false;
}

// Validate the signature!
function profileValidateSignature(&$value)
{
	global $settings, $txt;

	loadSource('Class-Editor');

	// Admins can do whatever they hell they want!
	if (!allowedTo('admin_forum'))
	{
		// Load all the signature limits.
		list ($sig_limits, $sig_bbc) = explode(':', $settings['signature_settings']);
		$sig_limits = explode(',', $sig_limits);
		$disabledTags = !empty($sig_bbc) ? explode(',', $sig_bbc) : array();

		$unparsed_signature = strtr(un_htmlspecialchars($value), array("\r" => '', '&#039' => "'"));
		// Too long?
		if (!empty($sig_limits[1]) && westr::strlen($unparsed_signature) > $sig_limits[1])
		{
			$_POST['signature'] = trim(htmlspecialchars(westr::substr($unparsed_signature, 0, $sig_limits[1]), ENT_QUOTES));
			$txt['profile_error_signature_max_length'] = sprintf($txt['profile_error_signature_max_length'], $sig_limits[1]);
			return 'signature_max_length';
		}
		// Too many lines?
		if (!empty($sig_limits[2]) && substr_count($unparsed_signature, "\n") >= $sig_limits[2])
		{
			$txt['profile_error_signature_max_lines'] = sprintf($txt['profile_error_signature_max_lines'], $sig_limits[2]);
			return 'signature_max_lines';
		}
		// Too many images?!
		if (!empty($sig_limits[3]) && (substr_count(strtolower($unparsed_signature), '[img') + substr_count(strtolower($unparsed_signature), '<img')) > $sig_limits[3])
		{
			$txt['profile_error_signature_max_image_count'] = sprintf($txt['profile_error_signature_max_image_count'], $sig_limits[3]);
			return 'signature_max_image_count';
		}
		// What about too many smileys!
		$smiley_parsed = $unparsed_signature;
		parsesmileys($smiley_parsed);
		$smiley_count = substr_count(strtolower($smiley_parsed), '<img') - substr_count(strtolower($unparsed_signature), '<img');
		if (!empty($sig_limits[4]) && $sig_limits[4] == -1 && $smiley_count > 0)
			return 'signature_allow_smileys';
		elseif (!empty($sig_limits[4]) && $sig_limits[4] > 0 && $smiley_count > $sig_limits[4])
		{
			$txt['profile_error_signature_max_smileys'] = sprintf($txt['profile_error_signature_max_smileys'], $sig_limits[4]);
			return 'signature_max_smileys';
		}
		// Maybe we are abusing font sizes?
		if (!empty($sig_limits[7]) && preg_match_all('~\[size=([\d.]+)?(px|pt|em|x-large|larger)~i', $unparsed_signature, $matches) !== false && isset($matches[2]))
		{
			foreach ($matches[1] as $ind => $size)
			{
				$limit_broke = 0;
				// Attempt to allow all sizes of abuse, so to speak.
				if ($matches[2][$ind] == 'px' && $size > $sig_limits[7])
					$limit_broke = $sig_limits[7] . 'px';
				elseif ($matches[2][$ind] == 'pt' && $size > ($sig_limits[7] * 0.75))
					$limit_broke = ((int) $sig_limits[7] * 0.75) . 'pt';
				elseif ($matches[2][$ind] == 'em' && $size > ((float) $sig_limits[7] / 16))
					$limit_broke = ((float) $sig_limits[7] / 16) . 'em';
				elseif ($matches[2][$ind] != 'px' && $matches[2][$ind] != 'pt' && $matches[2][$ind] != 'em' && $sig_limits[7] < 18)
					$limit_broke = 'large';

				if ($limit_broke)
				{
					$txt['profile_error_signature_max_font_size'] = sprintf($txt['profile_error_signature_max_font_size'], $limit_broke);
					return 'signature_max_font_size';
				}
			}
		}
		// The difficult one - image sizes! Don't error on this - just fix it.
		if ((!empty($sig_limits[5]) || !empty($sig_limits[6])))
		{
			// Get all BBC tags...
			preg_match_all('~\[img(\s+width=([\d]+))?(\s+height=([\d]+))?(\s+width=([\d]+))?\s*\](?:<br>)*([^<">]+?)(?:<br>)*\[/img\]~i', $unparsed_signature, $matches);
			// ... and all HTML ones.
			preg_match_all('~<img\s+src=(?:")?((?:http://|ftp://|https://|ftps://).+?)(?:")?(?:\s+alt=(?:")?(.*?)(?:")?)?(?:\s?/)?>~i', $unparsed_signature, $matches2);
			// And stick the HTML in the BBC.
			if (!empty($matches2))
			{
				foreach ($matches2[0] as $ind => $dummy)
				{
					$matches[0][] = $matches2[0][$ind];
					$matches[1][] = '';
					$matches[2][] = '';
					$matches[3][] = '';
					$matches[4][] = '';
					$matches[5][] = '';
					$matches[6][] = '';
					$matches[7][] = $matches2[1][$ind];
				}
			}

			$replaces = array();
			// Try to find all the images!
			if (!empty($matches))
			{
				foreach ($matches[0] as $key => $image)
				{
					$width = -1; $height = -1;

					// Does it have predefined restraints? Width first.
					if ($matches[6][$key])
						$matches[2][$key] = $matches[6][$key];
					if ($matches[2][$key] && $sig_limits[5] && $matches[2][$key] > $sig_limits[5])
					{
						$width = $sig_limits[5];
						$matches[4][$key] = $matches[4][$key] * ($width / $matches[2][$key]);
					}
					elseif ($matches[2][$key])
						$width = $matches[2][$key];
					// ... and height.
					if ($matches[4][$key] && $sig_limits[6] && $matches[4][$key] > $sig_limits[6])
					{
						$height = $sig_limits[6];
						if ($width != -1)
							$width = $width * ($height / $matches[4][$key]);
					}
					elseif ($matches[4][$key])
						$height = $matches[4][$key];

					// If the dimensions are still not fixed - we need to check the actual image.
					if (($width == -1 && $sig_limits[5]) || ($height == -1 && $sig_limits[6]))
					{
						$sizes = url_image_size($matches[7][$key]);
						if (is_array($sizes))
						{
							// Too wide?
							if ($sizes[0] > $sig_limits[5] && $sig_limits[5])
							{
								$width = $sig_limits[5];
								$sizes[1] = $sizes[1] * ($width / $sizes[0]);
							}
							// Too high?
							if ($sizes[1] > $sig_limits[6] && $sig_limits[6])
							{
								$height = $sig_limits[6];
								if ($width == -1)
									$width = $sizes[0];
								$width = $width * ($height / $sizes[1]);
							}
							elseif ($width != -1)
								$height = $sizes[1];
						}
					}

					// Did we come up with some changes? If so remake the string.
					if ($width != -1 || $height != -1)
						$replaces[$image] = '[img' . ($width != -1 ? ' width=' . round($width) : '') . ($height != -1 ? ' height=' . round($height) : '') . ']' . $matches[7][$key] . '[/img]';
				}
				if (!empty($replaces))
					$value = str_replace(array_keys($replaces), array_values($replaces), $value);
			}
		}
		// Any disabled BBC?
		$disabledSigBBC = implode('|', $disabledTags);
		if (!empty($disabledSigBBC))
		{
			if (preg_match('~\[(' . $disabledSigBBC . '[ =\]/])~i', $unparsed_signature, $matches) !== false && isset($matches[1]))
			{
				$disabledTags = array_unique($disabledTags);
				$txt['profile_error_signature_disabled_bbc'] = sprintf($txt['profile_error_signature_disabled_bbc'], implode(', ', $disabledTags));
				return 'signature_disabled_bbc';
			}
		}
	}

	wedit::preparsecode($value);
	return true;
}

// Validate an email address.
function profileValidateEmail($email, $memID = 0)
{
	$email = trim(strtr($email, array('&#039;' => "'")));

	// Check the name and email for validity.
	if (empty($email))
		return 'no_email';
	if (!is_valid_email($email))
		return 'bad_email';

	// Email addresses should be and stay unique.
	$request = wesql::query('
		SELECT id_member
		FROM {db_prefix}members
		WHERE ' . ($memID != 0 ? 'id_member != {int:selected_member} AND ' : '') . '
			email_address = {string:email_address}
		LIMIT 1',
		array(
			'selected_member' => $memID,
			'email_address' => $email,
		)
	);
	if (wesql::num_rows($request) > 0)
		return 'email_taken';
	wesql::free_result($request);

	return true;
}

// Reload a users settings.
function profileReloadUser()
{
	global $settings, $context, $cur_profile;

	// Log them back in - using the verify password as they must have matched and this one doesn't get changed by anyone!
	if (isset($_POST['passwrd2']) && $_POST['passwrd2'] != '')
	{
		loadSource('Subs-Auth');
		setLoginCookie(60 * $settings['cookieTime'], $context['id_member'], sha1(sha1(strtolower($cur_profile['member_name']) . un_htmlspecialchars($_POST['passwrd2'])) . $cur_profile['password_salt']));
	}

	we::getInstance();
	writeLog();
}

// Send the user a new activation email if they need to reactivate!
function profileSendActivation()
{
	global $profile_vars, $txt, $context, $cookiename, $cur_profile, $settings;

	loadSource('Subs-Post');

	// Shouldn't happen but just in case.
	if (empty($profile_vars['email_address']))
		return;

	$replacements = array(
		'ACTIVATIONLINK' => SCRIPT . '?action=activate;u=' . $context['id_member'] . ';code=' . $profile_vars['validation_code'],
		'ACTIVATIONCODE' => $profile_vars['validation_code'],
		'ACTIVATIONLINKWITHOUTCODE' => SCRIPT . '?action=activate;u=' . $context['id_member'],
	);

	// Send off the email.
	$emaildata = loadEmailTemplate('activate_reactivate', $replacements, empty($cur_profile['lngfile']) || empty($settings['userLanguage']) ? $settings['language'] : $cur_profile['lngfile']);
	sendmail($profile_vars['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 0);

	// Log the user out.
	wesql::query('
		DELETE FROM {db_prefix}log_online
		WHERE id_member = {int:selected_member}',
		array(
			'selected_member' => $context['id_member'],
		)
	);
	$_SESSION['log_time'] = 0;
	$_SESSION['login_' . $cookiename] = serialize(array(0, '', 0));

	if (isset($_COOKIE[$cookiename]))
		$_COOKIE[$cookiename] = '';

	we::getInstance();

	we::$is_guest = true;

	// Send them to the done-with-registration-login screen.
	loadTemplate('Register');

	wetem::load('after');
	$context['page_title'] = $txt['profile'];
	$context['title'] = $txt['activate_changed_email_title'];
	$context['description'] = $txt['activate_changed_email_desc'];

	// We're gone!
	obExit();
}

// Function to allow the user to choose group membership etc...
function groupMembership($memID)
{
	global $txt, $user_profile, $context;

	$curMember = $user_profile[$memID];
	$context['primary_group'] = $curMember['id_group'];

	// Can they manage groups?
	$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
	$context['can_manage_protected'] = allowedTo('admin_forum');
	$context['can_edit_primary'] = $context['can_manage_protected'];
	$context['update_message'] = isset($_GET['msg'], $txt['group_membership_msg_' . $_GET['msg']]) ? $txt['group_membership_msg_' . $_GET['msg']] : '';

	// Get all the groups this user is a member of.
	$groups = explode(',', $curMember['additional_groups']);
	$groups[] = $curMember['id_group'];

	// Ensure the query doesn't croak!
	if (empty($groups))
		$groups = array(0);
	// Just to be sure...
	foreach ($groups as $k => $v)
		$groups[$k] = (int) $v;

	// Get all the membergroups they can join.
	$request = wesql::query('
		SELECT mg.id_group, mg.group_name, mg.description, mg.group_type, mg.online_color, mg.hidden,
			IFNULL(lgr.id_member, 0) AS pending
		FROM {db_prefix}membergroups AS mg
			LEFT JOIN {db_prefix}log_group_requests AS lgr ON (lgr.id_member = {int:selected_member} AND lgr.id_group = mg.id_group)
		WHERE (mg.id_group IN ({array_int:group_list})
			OR mg.group_type > {int:nonjoin_group_id})
			AND mg.min_posts = {int:min_posts}
			AND mg.id_group != {int:moderator_group}
		ORDER BY group_name',
		array(
			'group_list' => $groups,
			'selected_member' => $memID,
			'nonjoin_group_id' => 1,
			'min_posts' => -1,
			'moderator_group' => 3,
		)
	);
	// This beast will be our group holder.
	$context['groups'] = array(
		'member' => array(),
		'available' => array()
	);
	while ($row = wesql::fetch_assoc($request))
	{
		// Can they edit their primary group?
		if (($row['id_group'] == $context['primary_group'] && $row['group_type'] > 1) || ($row['hidden'] != 2 && $context['primary_group'] == 0 && in_array($row['id_group'], $groups)))
			$context['can_edit_primary'] = true;

		// If they can't manage (protected) groups, and it's not publically joinable or already assigned, they can't see it.
		if (((!$context['can_manage_protected'] && $row['group_type'] == 1) || (!$context['can_manage_membergroups'] && $row['group_type'] == 0)) && $row['id_group'] != $context['primary_group'])
			continue;

		$context['groups'][in_array($row['id_group'], $groups) ? 'member' : 'available'][$row['id_group']] = array(
			'id' => $row['id_group'],
			'name' => $row['group_name'],
			'desc' => $row['description'],
			'color' => $row['online_color'],
			'type' => $row['group_type'],
			'pending' => $row['pending'],
			'is_primary' => $row['id_group'] == $context['primary_group'],
			'can_be_primary' => $row['hidden'] != 2,
			// Anything more than this needs to be done through account settings for security.
			'can_leave' => $row['id_group'] != 1 && $row['group_type'] > 1 ? true : false,
		);
	}
	wesql::free_result($request);

	// Add registered members on the end.
	$context['groups']['member'][0] = array(
		'id' => 0,
		'name' => $txt['regular_members'],
		'desc' => $txt['regular_members_desc'],
		'type' => 0,
		'is_primary' => $context['primary_group'] == 0 ? true : false,
		'can_be_primary' => true,
		'can_leave' => 0,
	);

	// No changing primary one unless you have enough groups!
	if (count($context['groups']['member']) < 2)
		$context['can_edit_primary'] = false;

	// In the special case that someone is requesting membership of a group, setup some special context vars.
	if (isset($_REQUEST['request'], $context['groups']['available'][(int) $_REQUEST['request']]) && $context['groups']['available'][(int) $_REQUEST['request']]['type'] == 2)
		$context['group_request'] = $context['groups']['available'][(int) $_REQUEST['request']];
}

// This function actually makes all the group changes...
function groupMembership2($profile_vars, $memID)
{
	global $context, $user_profile, $settings;

	// Let's be extra cautious...
	if (!we::$user['is_owner'] || empty($settings['show_group_membership']))
		isAllowedTo('manage_membergroups');
	if (!isset($_REQUEST['gid']) && !isset($_POST['primary']))
		fatal_lang_error('no_access', false);

	checkSession(isset($_GET['gid']) ? 'get' : 'post');

	$old_profile =& $user_profile[$memID];
	$context['can_manage_membergroups'] = allowedTo('manage_membergroups');
	$context['can_manage_protected'] = allowedTo('admin_forum');

	// By default the new primary is the old one.
	$newPrimary = $old_profile['id_group'];
	$addGroups = array_flip(explode(',', $old_profile['additional_groups']));
	$canChangePrimary = $old_profile['id_group'] == 0 ? 1 : 0;
	$changeType = isset($_POST['primary']) ? 'primary' : (isset($_POST['req']) ? 'request' : 'free');

	// One way or another, we have a target group in mind...
	$group_id = isset($_REQUEST['gid']) ? (int) $_REQUEST['gid'] : (int) $_POST['primary'];
	$foundTarget = $changeType == 'primary' && $group_id == 0 ? true : false;

	// Sanity check!!
	if ($group_id == 1)
		isAllowedTo('admin_forum');
	// Protected groups too!
	else
	{
		$request = wesql::query('
			SELECT group_type
			FROM {db_prefix}membergroups
			WHERE id_group = {int:current_group}
			LIMIT {int:limit}',
			array(
				'current_group' => $group_id,
				'limit' => 1,
			)
		);
		list ($is_protected) = wesql::fetch_row($request);
		wesql::free_result($request);

		if ($is_protected == 1)
			isAllowedTo('admin_forum');
	}

	// What ever we are doing, we need to determine if changing primary is possible!
	$request = wesql::query('
		SELECT id_group, group_type, hidden, group_name
		FROM {db_prefix}membergroups
		WHERE id_group IN ({int:group_list}, {int:current_group})',
		array(
			'group_list' => $group_id,
			'current_group' => $old_profile['id_group'],
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		// Is this the new group?
		if ($row['id_group'] == $group_id)
		{
			$foundTarget = true;
			$group_name = $row['group_name'];

			// Does the group type match what we're doing - are we trying to request a non-requestable group?
			if ($changeType == 'request' && $row['group_type'] != 2)
				fatal_lang_error('no_access', false);
			// What about leaving a requestable group we are not a member of?
			elseif ($changeType == 'free' && $row['group_type'] == 2 && $old_profile['id_group'] != $row['id_group'] && !isset($addGroups[$row['id_group']]))
				fatal_lang_error('no_access', false);
			elseif ($changeType == 'free' && $row['group_type'] != 3 && $row['group_type'] != 2)
				fatal_lang_error('no_access', false);

			// We can't change the primary group if this is hidden!
			if ($row['hidden'] == 2)
				$canChangePrimary = false;
		}

		// If this is their old primary, can we change it?
		if ($row['id_group'] == $old_profile['id_group'] && ($row['group_type'] > 1 || $context['can_manage_membergroups']) && $canChangePrimary !== false)
			$canChangePrimary = 1;

		// If we are not doing a force primary move, don't do it automatically if current primary is not 0.
		if ($changeType != 'primary' && $old_profile['id_group'] != 0)
			$canChangePrimary = false;

		// If this is the one we are acting on, can we even act?
		if ((!$context['can_manage_protected'] && $row['group_type'] == 1) || (!$context['can_manage_membergroups'] && $row['group_type'] == 0))
			$canChangePrimary = false;
	}
	wesql::free_result($request);

	// Didn't find the target?
	if (!$foundTarget)
		fatal_lang_error('no_access', false);

	// Final security check, don't allow users to promote themselves to admin.
	if ($context['can_manage_membergroups'] && !allowedTo('admin_forum'))
	{
		$request = wesql::query('
			SELECT COUNT(permission)
			FROM {db_prefix}permissions
			WHERE id_group = {int:selected_group}
				AND permission = {literal:admin_forum}
				AND add_deny = {int:not_denied}',
			array(
				'selected_group' => $group_id,
				'not_denied' => 1,
			)
		);
		list ($disallow) = wesql::fetch_row($request);
		wesql::free_result($request);

		if ($disallow)
			isAllowedTo('admin_forum');
	}

	// If we're requesting, add the note then return.
	if ($changeType == 'request')
	{
		$request = wesql::query('
			SELECT id_member
			FROM {db_prefix}log_group_requests
			WHERE id_member = {int:selected_member}
				AND id_group = {int:selected_group}',
			array(
				'selected_member' => $memID,
				'selected_group' => $group_id,
			)
		);
		if (wesql::num_rows($request) != 0)
			fatal_lang_error('profile_error_already_requested_group');
		wesql::free_result($request);

		// Log the request.
		wesql::insert('',
			'{db_prefix}log_group_requests',
			array(
				'id_member' => 'int', 'id_group' => 'int', 'time_applied' => 'int', 'reason' => 'string-65534',
			),
			array(
				$memID, $group_id, time(), $_POST['reason'],
			)
		);

		// Send an email to all group moderators etc.
		loadSource('Subs-Post');

		// Do we have any group moderators?
		$request = wesql::query('
			SELECT id_member
			FROM {db_prefix}group_moderators
			WHERE id_group = {int:selected_group}',
			array(
				'selected_group' => $group_id,
			)
		);
		$moderators = array();
		while ($row = wesql::fetch_assoc($request))
			$moderators[] = $row['id_member'];
		wesql::free_result($request);

		// Otherwise this is the backup!
		if (empty($moderators))
		{
			loadSource('Subs-Members');
			$moderators = membersAllowedTo('manage_membergroups');
		}

		if (!empty($moderators))
		{
			$request = wesql::query('
				SELECT id_member, email_address, lngfile, member_name, data
				FROM {db_prefix}members
				WHERE id_member IN ({array_int:moderator_list})
					AND notify_types != {int:no_notifications}
				ORDER BY lngfile',
				array(
					'moderator_list' => $moderators,
					'no_notifications' => 4,
				)
			);
			while ($row = wesql::fetch_assoc($request))
			{
				// Check whether they are interested.
				$data = empty($row['data']) ? array() : @unserialize($row['data']);
				if (!empty($data['modset']))
				{
					list (, $pref_binary) = explode('|', $data['modset']);
					if (!($pref_binary & 4))
						continue;
				}

				$replacements = array(
					'RECPNAME' => $row['member_name'],
					'APPYNAME' => $old_profile['member_name'],
					'GROUPNAME' => $group_name,
					'REASON' => $_POST['reason'],
					'MODLINK' => SCRIPT . '?action=moderate;area=groups;sa=requests',
				);

				$emaildata = loadEmailTemplate('request_membership', $replacements, empty($row['lngfile']) || empty($settings['userLanguage']) ? $settings['language'] : $row['lngfile']);
				sendmail($row['email_address'], $emaildata['subject'], $emaildata['body'], null, null, false, 2);
			}
			wesql::free_result($request);
		}

		return $changeType;
	}
	// Otherwise we are leaving/joining a group.
	elseif ($changeType == 'free')
	{
		// Are we leaving?
		if ($old_profile['id_group'] == $group_id || isset($addGroups[$group_id]))
		{
			if ($old_profile['id_group'] == $group_id)
				$newPrimary = 0;
			else
				unset($addGroups[$group_id]);
		}
		// ... if not, must be joining.
		else
		{
			// Can we change the primary, and do we want to?
			if ($canChangePrimary)
			{
				if ($old_profile['id_group'] != 0)
					$addGroups[$old_profile['id_group']] = -1;
				$newPrimary = $group_id;
			}
			// Otherwise it's an additional group...
			else
				$addGroups[$group_id] = -1;
		}
	}
	// Finally, we must be setting the primary.
	elseif ($canChangePrimary)
	{
		if ($old_profile['id_group'] != 0)
			$addGroups[$old_profile['id_group']] = -1;
		if (isset($addGroups[$group_id]))
			unset($addGroups[$group_id]);
		$newPrimary = $group_id;
	}

	// Finally, we can make the changes!
	foreach ($addGroups as $id => $dummy)
		if (empty($id))
			unset($addGroups[$id]);
	$addGroups = implode(',', array_flip($addGroups));

	// Ensure that we don't cache permissions if the group is changing.
	if (we::$user['is_owner'])
		$_SESSION['mc']['time'] = 0;
	else
		updateSettings(array('settings_updated' => time()));

	updateMemberData($memID, array('id_group' => $newPrimary, 'additional_groups' => $addGroups));

	return $changeType;
}

// Because we use this elsewhere too
function get_wedge_timezones()
{
	return array(
		'',
		'Pacific/Midway' => '[UTC-11:00] American Samoa',
		'Pacific/Apia' => '[UTC-11:00] Apia, Samoa',
		'Pacific/Honolulu' => '[UTC-10:00] Hawaii',
		'Pacific/Marquesas' => '[UTC-09:30] Marquesas Islands',
		'America/Anchorage' => '[UTC-09:00] Alaska',
		'America/Los_Angeles' => '[UTC-08:00] Pacific Time (USA / Canada)',
		'America/Santa_Isabel' => '[UTC-08:00] Baja California',
		'America/Tijuana' => '[UTC-08:00] Tijuana',
		'America/Denver' => '[UTC-07:00] Mountain Time (USA / Canada)',
		'America/Chihuahua' => '[UTC-07:00] Chihuahua, La Paz, Mazatlan',
		'America/Phoenix' => '[UTC-07:00] Arizona',
		'America/Chicago' => '[UTC-06:00] Central Time (USA / Canada)',
		'America/Belize' => '[UTC-06:00] Saskatchewan, Central America',
		'America/Mexico_City' => '[UTC-06:00] Guadalajara, Mexico City, Monterrey',
		'Pacific/Easter' => '[UTC-06:00] Easter Island',
		'America/New_York' => '[UTC-05:00] Eastern Time (USA / Canada)',
		'America/Havana' => '[UTC-05:00] Cuba',
		'America/Bogota' => '[UTC-05:00] Bogota, Lima, Quito',
		'America/Caracas' => '[UTC-04:30] Caracas',
		'America/Halifax' => '[UTC-04:00] Atlantic Time (Canada)',
		'America/Goose_Bay' => '[UTC-04:00] Atlantic Time (Goose Bay)',
		'America/Asuncion' => '[UTC-04:00] Asuncion',
		'America/Santiago' => '[UTC-04:00] Santiago',
		'America/Cuiaba' => '[UTC-04:00] Cuiaba',
		'America/La_Paz' => '[UTC-04:00] Georgetown, La Paz, Manaus, San Juan',
		'Atlantic/Stanley' => '[UTC-04:00] Falkland Islands',
		'America/St_Johns' => '[UTC-03:30] Newfoundland',
		'America/Argentina/Buenos_Aires' => '[UTC-03:00] Buenos Aires',
		'America/Argentina/San_Luis' => '[UTC-03:00] San Luis',
		'America/Argentina/Mendoza' => '[UTC-03:00] Argentina, Cayenne, Fortaleza',
		'America/Godthab' => '[UTC-03:00] Greenland',
		'America/Montevideo' => '[UTC-03:00] Montevideo',
		'America/Sao_Paulo' => '[UTC-03:00] Brasilia',
		'America/Miquelon' => '[UTC-03:00] Saint Pierre and Miquelon',
		'America/Noronha' => '[UTC-02:00] Mid-Atlantic',
		'Atlantic/Cape_Verde' => '[UTC-01:00] Cape Verde Is.',
		'Atlantic/Azores' => '[UTC-01:00] Azores',
		'Europe/London' => '[UTC] Dublin, Edinburgh, Lisbon, London',
		'Africa/Casablanca' => '[UTC] Casablanca',
		'Atlantic/Reykjavik' => '[UTC] Monrovia, Reykjavik',
		'Europe/Amsterdam' => '[UTC+01:00] Central European Time',
		'Europe/Paris' => '[UTC+01:00] Paris, Brussels, Madrid, Copenhagen',
		'Africa/Algiers' => '[UTC+01:00] West Central Africa',
		'Africa/Windhoek' => '[UTC+01:00] Windhoek',
		'Africa/Tunis' => '[UTC+01:00] Tunis',
		'Europe/Athens' => '[UTC+02:00] Eastern European Time',
		'Africa/Johannesburg' => '[UTC+02:00] South Africa Standard Time',
		'Asia/Amman' => '[UTC+02:00] Amman',
		'Asia/Beirut' => '[UTC+02:00] Beirut',
		'Africa/Cairo' => '[UTC+02:00] Cairo',
		'Asia/Jerusalem' => '[UTC+02:00] Jerusalem',
		'Europe/Minsk' => '[UTC+02:00] Minsk',
		'Asia/Gaza' => '[UTC+02:00] Gaza',
		'Asia/Damascus' => '[UTC+02:00] Syria',
		'Africa/Nairobi' => '[UTC+03:00] Nairobi, Baghdad, Kuwait, Qatar, Riyadh',
		'Europe/Kaliningrad' => '[UTC+03:00] Kaliningrad',
		'Asia/Tehran' => '[UTC+03:30] Tehran',
		'Europe/Moscow' => '[UTC+04:00] Moscow, St. Petersburg, Volgograd',
		'Asia/Dubai' => '[UTC+04:00] Abu Dhabi, Muscat, Tbilisi',
		'Asia/Yerevan' => '[UTC+04:00] Yerevan',
		'Asia/Baku' => '[UTC+04:00] Baku',
		'Indian/Mauritius' => '[UTC+04:00] Mauritius',
		'Asia/Kabul' => '[UTC+04:30] Kabul',
		'Asia/Tashkent' => '[UTC+05:00] Tashkent, Karachi',
		'Asia/Kolkata' => '[UTC+05:30] Chennai, Kolkata, Mumbai, New Delhi',
		'Asia/Kathmandu' => '[UTC+05:45] Kathmandu',
		'Asia/Dhaka' => '[UTC+06:00] Astana, Dhaka',
		'Asia/Yekaterinburg' => '[UTC+06:00] Ekaterinburg',
		'Asia/Almaty' => '[UTC+06:00] Almaty, Bishkek, Qyzylorda',
		'Asia/Rangoon' => '[UTC+06:30] Yangon (Rangoon)',
		'Asia/Bangkok' => '[UTC+07:00] Bangkok, Hanoi, Jakarta',
		'Asia/Novosibirsk' => '[UTC+07:00] Novosibirsk',
		'Asia/Hong_Kong' => '[UTC+08:00] Beijing, Chongqing, Hong Kong, Urumqi',
		'Asia/Krasnoyarsk' => '[UTC+08:00] Krasnoyarsk',
		'Asia/Singapore' => '[UTC+08:00] Kuala Lumpur, Singapore',
		'Australia/Perth' => '[UTC+08:00] Perth',
		'Asia/Irkutsk' => '[UTC+09:00] Irkutsk',
		'Asia/Tokyo' => '[UTC+09:00] Osaka, Sapporo, Tokyo',
		'Asia/Seoul' => '[UTC+09:00] Seoul',
		'Australia/Adelaide' => '[UTC+09:30] Adelaide',
		'Australia/Darwin' => '[UTC+09:30] Darwin',
		'Australia/Brisbane' => '[UTC+10:00] Brisbane, Guam',
		'Australia/Sydney' => '[UTC+10:00] Sydney, Melbourne, Hobart',
		'Asia/Yakutsk' => '[UTC+10:00] Yakutsk',
		'Pacific/Noumea' => '[UTC+11:00] Solomon Is., New Caledonia',
		'Asia/Vladivostok' => '[UTC+11:00] Vladivostok',
		'Pacific/Norfolk' => '[UTC+11:30] Norfolk Island',
		'Asia/Anadyr' => '[UTC+12:00] Anadyr, Kamchatka',
		'Pacific/Auckland' => '[UTC+12:00] Auckland, Wellington',
		'Pacific/Fiji' => '[UTC+12:00] Fiji',
		'Asia/Magadan' => '[UTC+12:00] Magadan',
		'Pacific/Chatham' => '[UTC+12:45] Chatham Islands',
		'Pacific/Tongatapu' => '[UTC+13:00] Nuku\'alofa',
		'Pacific/Kiritimati' => '[UTC+14:00] Kiritimati',
	);
}
