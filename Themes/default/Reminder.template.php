<?php
// Version: 2.0 RC4; Reminder

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<br>
	<form action="', $scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="tborder login">
			<div class="cat_bar">
				<h3>', $txt['authentication_reminder'], '</h3>
			</div>
			<div class="roundframe">
				<p class="smalltext centertext">', $txt['password_reminder_desc'], '</p>
				<dl>
					<dt>', $txt['user_email'], ':</dt>
					<dd><input type="text" name="user" size="30"></dd>
				</dl>
				<p class="centertext"><input type="submit" value="', $txt['reminder_continue'], '" class="submit"></p>
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

function template_reminder_pick()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<br>
	<form action="', $scripturl, '?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="tborder login">
			<div class="cat_bar">
				<h3>', $txt['authentication_reminder'], '</h3>
			</div>
			<div class="roundframe">
				<p><strong>', $txt['authentication_options'], ':</strong></p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_email" value="email" checked></dt>
					<label for="reminder_type_email">', $txt['authentication_' . $context['account_type'] . '_email'], '</label></dd>
				</p>
				<p>
					<input type="radio" name="reminder_type" id="reminder_type_secret" value="secret">
					<label for="reminder_type_secret">', $txt['authentication_' . $context['account_type'] . '_secret'], '</label>
				</p>
				<p class="centertext"><input type="submit" value="', $txt['reminder_continue'], '" class="submit"></p>
			</div>
		</div>
		<input type="hidden" name="uid" value="', $context['current_member']['id'], '">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

function template_sent()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
		<br>
		<div class="tborder login" id="reminder_sent">
			<div class="cat_bar">
				<h3>' . $context['page_title'] . '</h3>
			</div>
			<p class="information">' . $context['description'] . '</p>
		</div>';
}

function template_set_password()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	echo '
	<br>
	<form action="', $scripturl, '?action=reminder;sa=setpassword2" name="reminder_form" id="reminder_form" method="post" accept-charset="UTF-8">
		<div class="tborder login">
			<div class="cat_bar">
				<h3>', $context['page_title'], '</h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt>', $txt['choose_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd1" id="smf_autov_pwmain" size="22">
						<span id="smf_autov_pwmain_div" style="display: none;">
							<img id="smf_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.gif">
						</span>
					</dd>
					<dt>', $txt['verify_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd2" id="smf_autov_pwverify" size="22">
						<span id="smf_autov_pwverify_div" style="display: none;">
							<img id="smf_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_invalid.gif">
						</span>
					</dd>
				</dl>
				<p class="align_center"><input type="submit" value="', $txt['save'], '" class="save"></p>
			</div>
		</div>
		<input type="hidden" name="code" value="', $context['code'], '">
		<input type="hidden" name="u" value="', $context['memID'], '">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';

	add_js_file('scripts/register.js');

	add_js('
	var regTextStrings = {
		"password_short": ', JavaScriptEscape($txt['registration_password_short']), ',
		"password_reserved": ', JavaScriptEscape($txt['registration_password_reserved']), ',
		"password_numbercase": ', JavaScriptEscape($txt['registration_password_numbercase']), ',
		"password_no_match": ', JavaScriptEscape($txt['registration_password_no_match']), ',
		"password_valid": ', JavaScriptEscape($txt['registration_password_valid']), '
	};
	var verificationHandle = new smfRegister("reminder_form", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);');
}

function template_ask()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	echo '
	<br>
	<form action="', $scripturl, '?action=reminder;sa=secret2" method="post" accept-charset="UTF-8" name="creator" id="creator">
		<div class="tborder login">
			<div class="cat_bar">
				<h3>', $txt['authentication_reminder'], '</h3>
			</div>
			<div class="roundframe">
				<p class="smalltext">', $context['account_type'] == 'password' ? $txt['enter_new_password'] : $txt['openid_secret_reminder'], '</p>
				<dl>
					<dt>', $txt['secret_question'], ':</dt>
					<dd>', $context['secret_question'], '</dd>
					<dt>', $txt['secret_answer'], ':</dt>
					<dd><input type="text" name="secret_answer" size="22"></dd>';

	if ($context['account_type'] == 'password')
		echo '
					<dt>', $txt['choose_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd1" id="smf_autov_pwmain" size="22">
						<span id="smf_autov_pwmain_div" style="display: none;">
							<img id="smf_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.gif">
						</span>
					</dd>
					<dt>', $txt['verify_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd2" id="smf_autov_pwverify" size="22">
						<span id="smf_autov_pwverify_div" style="display: none;">
							<img id="smf_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_valid.gif">
						</span>
					</dd>';

	echo '
				</dl>
				<p class="align_center"><input type="submit" value="', $txt['save'], '" class="save"></p>
			</div>
		</div>
		<input type="hidden" name="uid" value="', $context['remind_user'], '">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';

	add_js_file('scripts/register.js');

	if ($context['account_type'] == 'password')
		add_js('
	var regTextStrings = {
		"password_short": ', JavaScriptEscape($txt['registration_password_short']), ',
		"password_reserved": ', JavaScriptEscape($txt['registration_password_reserved']), ',
		"password_numbercase": ', JavaScriptEscape($txt['registration_password_numbercase']), ',
		"password_no_match": ', JavaScriptEscape($txt['registration_password_no_match']), ',
		"password_valid": ', JavaScriptEscape($txt['registration_password_valid']), '
	};
	var verificationHandle = new smfRegister("creator", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);');
}

?>