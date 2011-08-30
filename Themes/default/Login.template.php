<?php
/**
 * Wedge
 *
 * Displays the main login form as well as the maintenance lock-out screen.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_login()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	if (empty($context['disable_login_hashing']))
		$context['javascript_files'][] = 'scripts/sha1.js';

	echo '
		<form action="', $scripturl, '?action=login2" name="frmLogin" id="frmLogin" method="post" accept-charset="UTF-8" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
		<div class="login">
			<we:cat>
				<img src="', $settings['images_url'], '/icons/login_sm.gif">
				', $txt['login'], '
			</we:cat>
			<div class="roundframe"><br class="clear">';

	// Did they make a mistake last time?
	if (!empty($context['login_errors']))
		foreach ($context['login_errors'] as $error)
			echo '
				<p class="error">', $error, '</p>';

	// Or perhaps there's some special description for this time?
	if (isset($context['description']))
		echo '
				<p class="description">', $context['description'], '</p>';

	// Now just get the basic information - username, password, etc.
	echo '
				<dl>
					<dt>', $txt['username'], ':</dt>
					<dd><input type="text" name="user" size="20" value="', $context['default_username'], '"></dd>
					<dt>', $txt['password'], ':</dt>
					<dd><input type="password" name="passwrd" value="', $context['default_password'], '" size="20"></dd>
				</dl>';

	if (!empty($modSettings['enableOpenID']))
		echo '<p><strong>&mdash;', $txt['or'], '&mdash;</strong></p>
				<dl>
					<dt>', $txt['openid'], ':</dt>
					<dd><input type="text" name="openid_identifier" class="openid_login" size="17">&nbsp;<em><a href="', $scripturl, '?action=help;in=register_openid" onclick="return reqWin(this);">(?)</a></em></dd>
				</dl><hr>';

	echo '
				<dl>
					<dt>', $txt['mins_logged_in'], ':</dt>
					<dd><input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '"', $context['never_expire'] ? ' disabled' : '', '></dd>
					<dt>', $txt['always_logged_in'], ':</dt>
					<dd><input type="checkbox" name="cookieneverexp"', $context['never_expire'] ? ' checked' : '', ' onclick="this.form.cookielength.disabled = this.checked;"></dd>';

	// If they have deleted their account, give them a chance to change their mind.
	if (isset($context['login_show_undelete']))
		echo '
					<dt class="alert">', $txt['undelete_account'], ':</dt>
					<dd><input type="checkbox" name="undelete"></dd>';
	echo '
				</dl>
				<p><input type="submit" value="', $txt['login'], '" class="submit"></p>
				<p class="smalltext"><a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a></p>
				<input type="hidden" name="hash_passwrd" value="">
			</div>
		</div></form>';

	// Focus on the correct input - username or password.
	add_js_inline('
	document.forms.frmLogin.', isset($context['default_username']) && $context['default_username'] != '' ? 'passwrd' : 'user', '.focus();');
}

// Tell a guest to get lost or login!
function template_kick_guest()
{
	global $context, $settings, $options, $scripturl, $modSettings, $txt;

	// This isn't that much... just like normal login but with a message at the top.
	if (empty($context['disable_login_hashing']))
		$context['javascript_files'][] = 'scripts/sha1.js';

	echo '
	<form action="', $scripturl, '?action=login2" method="post" accept-charset="UTF-8" name="frmLogin" id="frmLogin"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
		<div class="login">
			<we:cat>
				', $txt['warning'], '
			</we:cat>';

	// Show the message or default message.
	echo '
			<p class="information centertext">
				', empty($context['kick_message']) ? $txt['only_members_can_access'] : $context['kick_message'], '<br>
				', $txt['login_below'], ' <a href="', $scripturl, '?action=register">', $txt['register_an_account'], '</a> ', sprintf($txt['login_with_forum'], $context['forum_name_html_safe']), '
			</p>';

	// And now the login information.
	echo '
			<we:cat>
				<img src="', $settings['images_url'], '/icons/login_sm.gif">
				', $txt['login'], '
			</we:cat>
			<div class="roundframe">
				<dl>
					<dt>', $txt['username'], ':</dt>
					<dd><input type="text" name="user" size="20"></dd>
					<dt>', $txt['password'], ':</dt>
					<dd><input type="password" name="passwrd" size="20"></dd>';

	if (!empty($modSettings['enableOpenID']))
		echo '
				</dl>
				<p><strong>&mdash;', $txt['or'], '&mdash;</strong></p>
				<dl>
					<dt>', $txt['openid'], ':</dt>
					<dd><input type="text" name="openid_identifier" class="openid_login" size="17"></dd>
				</dl>
				<hr>
				<dl>';

	echo '
					<dt>', $txt['mins_logged_in'], ':</dt>
					<dd><input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '"></dd>
					<dt>', $txt['always_logged_in'], ':</dt>
					<dd><input type="checkbox" name="cookieneverexp" onclick="this.form.cookielength.disabled = this.checked;"></dd>
				</dl>
				<p class="centertext"><input type="submit" value="', $txt['login'], '" class="submit"></p>
				<p class="centertext smalltext"><a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a></p>
			</div>
			<input type="hidden" name="hash_passwrd" value="">
		</div>
	</form>';

	// Do the focus thing...
	add_js_inline('
	document.forms.frmLogin.user.focus();');
}

// This is for maintenance mode.
function template_maintenance()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	// Display the administrator's message at the top.
	if (empty($context['disable_login_hashing']))
		$context['javascript_files'][] = 'scripts/sha1.js';

	echo '
<form action="', $scripturl, '?action=login2" method="post" accept-charset="UTF-8"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\');"' : '', '>
	<div class="login" id="maintenance_mode">
		<we:cat>
			', $context['title'], '
		</we:cat>
		<p class="description">
			<img class="floatleft" src="', $settings['images_url'], '/construction.png" width="40" height="40" alt="', $txt['in_maintain_mode'], '">
			', $context['description'], '<br class="clear">
		</p>
		<we:title2>
			', $txt['admin_login'], '
		</we:title2>
		<div class="roundframe">
			<dl>
				<dt>', $txt['username'], ':</dt>
				<dd><input type="text" name="user" size="20"></dd>
				<dt>', $txt['password'], ':</dt>
				<dd><input type="password" name="passwrd" size="20"></dd>
				<dt>', $txt['mins_logged_in'], ':</dt>
				<dd><input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '"></dd>
				<dt>', $txt['always_logged_in'], ':</dt>
				<dd><input type="checkbox" name="cookieneverexp"></dd>
			</dl>
			<p class="centertext"><input type="submit" value="', $txt['login'], '" class="submit"></p>
		</div>
		<input type="hidden" name="hash_passwrd" value="">
	</div>
</form>';
}

// This is for the security stuff - makes administrators login every so often.
function template_admin_login()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Since this should redirect to whatever they were doing, send all the get data.
	$context['javascript_files'][] = 'scripts/sha1.js';

	echo '
<form action="', $scripturl, $context['get_data'], '" method="post" accept-charset="UTF-8" name="frmLogin" id="frmLogin" onsubmit="hashAdminPassword(this, \'', $context['user']['username'], '\', \'', $context['session_id'], '\');">
	<div class="login" id="admin_login">
		<we:cat>
			<img src="', $settings['images_url'], '/icons/login_sm.gif">
			', $txt['login'], '
		</we:cat>
		<div class="roundframe centertext">';

	if (!empty($context['incorrect_password']))
		echo '
			<div class="error">', $txt['admin_incorrect_password'], '</div>';

	echo '
			<strong>', $txt['password'], ':</strong>
			<input type="password" name="admin_pass" size="24">
			<a href="', $scripturl, '?action=help;in=securityDisable_why" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
			<br><br>
			<input type="submit" style="margin-top: 1em" value="', $txt['login'], '" class="submit">';

	// Make sure to output all the old post data.
	echo $context['post_data'], '
		</div>
	</div>
	<input type="hidden" name="admin_hash_pass" value="">
</form>';

	// Focus on the password box.
	add_js_inline('
	document.forms.frmLogin.admin_pass.focus();');
}

// Activate your account manually?
function template_retry_activate()
{
	global $context, $settings, $options, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=activate;u=', $context['member_id'], '" method="post" accept-charset="UTF-8">
			<we:title>
				', $context['page_title'], '
			</we:title>
			<div class="roundframe">';

	// You didn't even have an ID?
	if (empty($context['member_id']))
		echo '
				<dl>
					<dt>', $txt['invalid_activation_username'], ':</dt>
					<dd><input type="text" name="user" size="30"></dd>';

	echo '
					<dt>', $txt['invalid_activation_retry'], ':</dt>
					<dd><input type="text" name="code" size="30"></dd>
				</dl>
				<p><input type="submit" value="', $txt['invalid_activation_submit'], '" class="submit"></p>
			</div>
		</form>';
}

// Activate your account manually?
function template_resend()
{
	global $context, $settings, $options, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=activate;sa=resend" method="post" accept-charset="UTF-8">
			<we:title>
				', $context['page_title'], '
			</we:title>
			<div class="roundframe">
				<dl>
					<dt>', $txt['invalid_activation_username'], ':</dt>
					<dd><input type="text" name="user" size="40" value="', $context['default_username'], '"></dd>
				</dl>
				<p>', $txt['invalid_activation_new'], '</p>
				<dl>
					<dt>', $txt['invalid_activation_new_email'], ':</dt>
					<dd><input type="text" name="new_email" size="40"></dd>
					<dt>', $txt['invalid_activation_password'], ':</dt>
					<dd><input type="password" name="passwd" size="30"></dd>
				</dl>';

	if ($context['can_activate'])
		echo '
				<p>', $txt['invalid_activation_known'], '</p>
				<dl>
					<dt>', $txt['invalid_activation_retry'], ':</dt>
					<dd><input type="text" name="code" size="30"></dd>
				</dl>';

	echo '
				<p><input type="submit" value="', $txt['invalid_activation_resend'], '" class="submit"></p>
			</div>
		</form>';
}

?>