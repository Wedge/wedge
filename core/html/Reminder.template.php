<?php
/**
 * Displays the interface to request a password reminder.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

function template_main()
{
	global $context, $txt;

	echo '
	<br>
	<form action="<URL>?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="login">
			<we:cat>
				', $txt['authentication_reminder'], '
			</we:cat>
			<p class="information">', $txt['password_reminder_desc'], '</p>
			<div class="roundframe">
				<p><strong>', $txt['user_email'], '</strong>: <input name="user" size="30"></p>
				<p><input type="submit" value="', $txt['reminder_continue'], '" class="submit"></p>
			</div>
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

function template_reminder_pick()
{
	global $context, $txt;

	echo '
	<br>
	<form action="<URL>?action=reminder;sa=picktype" method="post" accept-charset="UTF-8">
		<div class="login">
			<we:cat>
				', $txt['authentication_reminder'], '
			</we:cat>
			<div class="roundframe">
				<p><strong>', $txt['authentication_options'], ':</strong></p>
				<p>
					<label><input type="radio" name="reminder_type" id="reminder_type_email" value="email" checked></dt>
					', $txt['authentication_password_email'], '</label></dd>
				</p>
				<p>
					<label><input type="radio" name="reminder_type" id="reminder_type_secret" value="secret">
					', $txt['authentication_password_secret'], '</label>
				</p>
				<p class="center"><input type="submit" value="', $txt['reminder_continue'], '" class="submit"></p>
			</div>
		</div>
		<input type="hidden" name="uid" value="', $context['current_member']['id'], '">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

function template_sent()
{
	global $context;

	echo '
		<br>
		<div class="login" id="reminder_sent">
			<we:cat>
				' . $context['page_title'] . '
			</we:cat>
			<p class="information">' . $context['description'] . '</p>
		</div>';
}

function template_set_password()
{
	global $context, $txt, $settings;

	echo '
	<br>
	<form action="<URL>?action=reminder;sa=setpassword2" name="reminder_form" id="reminder_form" method="post" accept-charset="UTF-8">
		<div class="login">
			<we:cat>
				', $context['page_title'], '
			</we:cat>
			<div class="roundframe">
				<dl>
					<dt>', $txt['choose_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd1" id="we_autov_pwmain" size="22">
						<span id="we_autov_pwmain_div" class="hide">
							<img id="we_autov_pwmain_img" src="', ASSETS, '/icons/field_invalid.gif">
						</span>
					</dd>
					<dt>', $txt['verify_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd2" id="we_autov_pwverify" size="22">
						<span id="we_autov_pwverify_div" class="hide">
							<img id="we_autov_pwverify_img" src="', ASSETS, '/icons/field_invalid.gif">
						</span>
					</dd>
				</dl>
				<p class="floatright"><input type="submit" value="', $txt['save'], '" class="save"></p>
			</div>
		</div>
		<input type="hidden" name="code" value="', $context['code'], '">
		<input type="hidden" name="u" value="', $context['memID'], '">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';

	add_js_file('register.js');

	add_js('
	new weRegister("reminder_form", ', empty($settings['password_strength']) ? 0 : $settings['password_strength'], ');');
}

function template_ask()
{
	global $context, $txt, $settings;

	echo '
	<br>
	<form action="<URL>?action=reminder;sa=secret2" method="post" accept-charset="UTF-8" name="creator" id="creator">
		<div class="login">
			<we:cat>
				', $txt['authentication_reminder'], '
			</we:cat>
			<div class="roundframe">
				<p>', $txt['enter_new_password'], '</p>
				<dl>
					<dt>', $txt['secret_question'], ':</dt>
					<dd>', $context['secret_question'], '</dd>
					<dt>', $txt['secret_answer'], ':</dt>
					<dd><input name="secret_answer" size="22"></dd>
					<dt>', $txt['choose_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd1" id="we_autov_pwmain" size="22">
						<span id="we_autov_pwmain_div" class="hide">
							<img id="we_autov_pwmain_img" src="', ASSETS, '/icons/field_invalid.gif">
						</span>
					</dd>
					<dt>', $txt['verify_pass'], ': </dt>
					<dd>
						<input type="password" name="passwrd2" id="we_autov_pwverify" size="22">
						<span id="we_autov_pwverify_div" class="hide">
							<img id="we_autov_pwverify_img" src="', ASSETS, '/icons/field_valid.gif">
						</span>
					</dd>
				</dl>
				<p class="floatright"><input type="submit" value="', $txt['save'], '" class="save"></p>
			</div>
		</div>
		<input type="hidden" name="uid" value="', $context['remind_user'], '">
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';

	add_js_file('register.js');

	add_js('
	new weRegister("creator", ', empty($settings['password_strength']) ? 0 : $settings['password_strength'], ');');
}
