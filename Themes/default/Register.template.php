<?php
/**
 * Wedge
 *
 * The registration agreement and form, as well as the COPPA form.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Before showing users a registration form, show them the registration agreement.
function template_registration_agreement()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	echo '
		<form action="', $scripturl, '?action=register" method="post" accept-charset="UTF-8" id="registration">
			<we:cat>
				', $txt['registration_agreement'], '
			</we:cat>
			<div class="roundframe">
				<p>', $context['agreement'], '</p>
			</div>
			<div id="confirm_buttons">';

	// Age restriction in effect?
	if ($context['show_coppa'])
		echo '
				<input type="submit" name="accept_agreement" value="', $context['coppa_agree_above'], '" class="submit"><br><br>
				<input type="submit" name="accept_agreement_coppa" value="', $context['coppa_agree_below'], '" class="submit">';
	else
		echo '
				<input type="submit" name="accept_agreement" value="', $txt['agreement_agree'], '" class="submit">';

	echo '
			</div>
			<input type="hidden" name="step" value="1">
		</form>';

}

// Before registering - get their information.
function template_registration_form()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	add_js_file(array(
		'scripts/register.js',
		'scripts/profile.js'
	));

	add_js('
	function verifyAgree()
	{
		if (currentAuthMethod == \'passwd\' && document.forms.registration.smf_autov_pwmain.value != document.forms.registration.smf_autov_pwverify.value)
		{
			alert(' . JavaScriptEscape($txt['register_passwords_differ_js']) . ');
			return false;
		}

		return true;
	}

	var currentAuthMethod = "passwd";

	function updateAuthMethod()
	{
		// What authentication method is being used?
		currentAuthMethod = $("#auth_openid").is(":checked") ? "openid" : "passwd";

		// No openID?
		if (!$("#auth_openid").length)
			return;

		var is_pw = currentAuthMethod == "passwd";
		document.forms.registration.openid_url.disabled = is_pw;
		document.forms.registration.smf_autov_pwmain.disabled = !is_pw;
		document.forms.registration.smf_autov_pwverify.disabled = !is_pw;
		$("#smf_autov_pwmain_div, #smf_autov_pwverify_div, #password1_group, #password2_group").toggle(is_pw);
		$("#openid_group").toggle(!is_pw);

		if (is_pw)
		{
			verificationHandle.refreshMainPassword();
			verificationHandle.refreshVerifyPassword();
			document.forms.registration.openid_url.style.backgroundColor = "";
		}
		else
		{
			document.forms.registration.smf_autov_pwmain.style.backgroundColor = "";
			document.forms.registration.smf_autov_pwverify.style.backgroundColor = "";
			document.forms.registration.openid_url.style.backgroundColor = "#FFF0F0";
		}
	}

	var regTextStrings = {
		"username_valid": ' . JavaScriptEscape($txt['registration_username_available']) . ',
		"username_invalid": ' . JavaScriptEscape($txt['registration_username_unavailable']) . ',
		"username_check": ' . JavaScriptEscape($txt['registration_username_check']) . ',
		"password_short": ' . JavaScriptEscape($txt['registration_password_short']) . ',
		"password_reserved": ' . JavaScriptEscape($txt['registration_password_reserved']) . ',
		"password_numbercase": ' . JavaScriptEscape($txt['registration_password_numbercase']) . ',
		"password_no_match": ' . JavaScriptEscape($txt['registration_password_no_match']) . ',
		"password_valid": ' . JavaScriptEscape($txt['registration_password_valid']) . '
	};

	var verificationHandle = new smfRegister("registration", ' . (empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength']) . ', regTextStrings);

	// Update the authentication status.
	updateAuthMethod();

	$(\'#time_offset\').val(autoDetectTimeOffset(' . $context['current_forum_time_js'] . '000));');

	// Any errors?
	if (!empty($context['registration_errors']))
	{
		echo '
		<div class="register_error">
			<span>', $txt['registration_errors_occurred'], '</span>
			<ul class="reset">';

		// Cycle through each error and display an error message.
		foreach ($context['registration_errors'] as $error)
				echo '
				<li>', $error, '</li>';

		echo '
			</ul>
		</div>';
	}

	echo '
		<form action="', $scripturl, '?action=register2" method="post" accept-charset="UTF-8" name="registration" id="registration" onsubmit="return verifyAgree();">
			<we:cat>
				', $txt['registration_form'], '
			</we:cat>
			<we:title2>
				', $txt['required_info'], '
			</we:title2>
			<div class="windowbg2 wrc">
				<fieldset>
					<dl class="register_form">
						<dt><strong><label for="smf_autov_username">', $txt['username'], ':</label></strong></dt>
						<dd>
							<input type="text" name="user" id="smf_autov_username" size="30" tabindex="', $context['tabindex']++, '" maxlength="25" value="', isset($context['username']) ? $context['username'] : '', '" required>
							<span id="smf_autov_username_div" style="display: none">
								<a id="smf_autov_username_link" href="#">
									<img id="smf_autov_username_img" src="', $settings['images_url'], '/icons/field_check.gif">
								</a>
							</span>
						</dd>
						<dt><strong><label for="smf_autov_reserve1">', $txt['email'], ':</label></strong></dt>
						<dd>
							<input type="email" name="email" id="smf_autov_reserve1" size="30" tabindex="', $context['tabindex']++, '" value="', isset($context['email']) ? $context['email'] : '', '" required>
						</dd>
						<dt><strong><label for="allow_email">', $txt['allow_user_email'], ':</label></strong></dt>
						<dd>
							<input type="checkbox" name="allow_email" id="allow_email" tabindex="', $context['tabindex']++, '">
						</dd>
					</dl>';

	// If OpenID is enabled, give the user a choice between password and OpenID.
	if (!empty($modSettings['enableOpenID']))
	{
		echo '
					<dl class="register_form" id="authentication_group">
						<dt>
							<strong>', $txt['authenticate_label'], ':</strong>
							<a href="', $scripturl, '?action=help;in=register_openid" onclick="return reqWin(this);">(?)</a>
						</dt>
						<dd>
							<label id="option_auth_pass">
								<input type="radio" name="authenticate" value="passwd" id="auth_pass" tabindex="', $context['tabindex']++, '"', empty($context['openid']) ? ' checked ' : '', ' onclick="updateAuthMethod();">
								', $txt['authenticate_password'], '
							</label>
							<label id="option_auth_openid">
								<input type="radio" name="authenticate" value="openid" id="auth_openid" tabindex="', $context['tabindex']++, '"', !empty($context['openid']) ? ' checked ' : '', ' onclick="updateAuthMethod();">
								', $txt['authenticate_openid'], '
							</label>
						</dd>
					</dl>';
	}

	echo '
					<dl class="register_form" id="password1_group">
						<dt><strong><label for="smf_autov_pwmain">', $txt['choose_pass'], ':</label></strong></dt>
						<dd>
							<input type="password" name="passwrd1" id="smf_autov_pwmain" size="30" tabindex="', $context['tabindex']++, '">
							<span id="smf_autov_pwmain_div" style="display: none;">
								<img id="smf_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.gif">
							</span>
						</dd>
					</dl>
					<dl class="register_form" id="password2_group">
						<dt><strong><label for="smf_autov_pwverify">', $txt['verify_pass'], ':</label></strong></dt>
						<dd>
							<input type="password" name="passwrd2" id="smf_autov_pwverify" size="30" tabindex="', $context['tabindex']++, '">
							<span id="smf_autov_pwverify_div" style="display: none;">
								<img id="smf_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_valid.gif">
							</span>
						</dd>
					</dl>';

	// If OpenID is enabled, give the user a choice between password and OpenID.
	if (!empty($modSettings['enableOpenID']))
	{
		echo '

					<dl class="register_form" id="openid_group">
						<dt><strong>', $txt['authenticate_openid_url'], ':</strong></dt>
						<dd>
							<input type="text" name="openid_identifier" id="openid_url" size="30" tabindex="', $context['tabindex']++, '" value="', isset($context['openid']) ? $context['openid'] : '', '" class="openid_login">
						</dd>
					</dl>';

	}

	echo '
				</fieldset>
			</div>';

	// If we have either of these, show the extra group.
	if (!empty($context['profile_fields']) || !empty($context['custom_fields']))
	{
		echo '
			<we:title2>
				', $txt['additional_information'], '
			</we:title2>
			<div class="windowbg2 wrc">
				<fieldset>
					<dl class="register_form" id="custom_group">';
	}

	if (!empty($context['profile_fields']))
	{
		// Any fields we particularly want?
		foreach ($context['profile_fields'] as $key => $field)
		{
			if ($field['type'] == 'callback')
			{
				if (isset($field['callback_func']) && function_exists('template_profile_' . $field['callback_func']))
				{
					$callback_func = 'template_profile_' . $field['callback_func'];
					$callback_func();
				}
			}
			else
			{
					echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' style="color: red;"' : '', '>', $field['label'], ':</strong>';

				// Does it have any subtext to show?
				if (!empty($field['subtext']))
					echo '
							<dfn>', $field['subtext'], '</dfn>';

				echo '
						</dt>
						<dd>';

				// Want to put something infront of the box?
				if (!empty($field['preinput']))
					echo '
							', $field['preinput'];

				// What type of data are we showing?
				if ($field['type'] == 'label')
					echo '
							', $field['value'];

				// Maybe it's a text box - very likely!
				elseif (in_array($field['type'], array('int', 'float', 'text', 'password')))
					echo '
							<input type="', $field['type'] == 'password' ? 'password' : 'text', '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" tabindex="', $context['tabindex']++, '"', $field['input_attr'], '>';

				// You "checking" me out? ;)
				elseif ($field['type'] == 'check')
					echo '
							<input type="hidden" name="', $key, '" value="0"><input type="checkbox" name="', $key, '" id="', $key, '"', !empty($field['value']) ? ' checked' : '', ' value="1" tabindex="', $context['tabindex']++, '"', $field['input_attr'], '>';

				// Always fun - select boxes!
				elseif ($field['type'] == 'select')
				{
					echo '
							<select name="', $key, '" id="', $key, '" tabindex="', $context['tabindex']++, '">';

					if (isset($field['options']))
					{
						// Is this some code to generate the options?
						if (!is_array($field['options']))
							$field['options'] = eval($field['options']);
						// Assuming we now have some!
						if (is_array($field['options']))
							foreach ($field['options'] as $value => $name)
								echo '
								<option value="', $value, '"', $value == $field['value'] ? ' selected' : '', '>', $name, '</option>';
					}

					echo '
							</select>';
				}

				// Something to end with?
				if (!empty($field['postinput']))
					echo '
							', $field['postinput'];

				echo '
						</dd>';
			}
		}
	}

	// Are there any custom fields?
	if (!empty($context['custom_fields']))
	{
		foreach ($context['custom_fields'] as $field)
			echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' style="color: red;"' : '', '>', $field['name'], ':</strong>
							<dfn>', $field['desc'], '</dfn>
						</dt>
						<dd>', $field['input_html'], '</dd>';
	}

	// If we have either of these, close the list like a proper gent.
	if (!empty($context['profile_fields']) || !empty($context['custom_fields']))
	{
		echo '
					</dl>
				</fieldset>
			</div>';
	}

	if ($context['visual_verification'])
	{
		echo '
			<we:title2>
				', $txt['verification'], '
			</we:title2>
			<div class="windowbg2 wrc">
				<fieldset class="centertext">
					', template_control_verification($context['visual_verification_id'], 'all'), '
				</fieldset>
			</div>';
	}

	echo '
			<div id="confirm_buttons">
				<input type="submit" name="regSubmit" value="', $txt['register'], '" tabindex="', $context['tabindex']++, '" class="submit">
			</div>
			<input type="hidden" name="step" value="2">
			<input type="hidden" name="time_offset" value="0" id="time_offset">
		</form>';
}

// After registration... all done ;).
function template_after()
{
	global $context, $settings, $options, $txt, $scripturl;

	// Not much to see here, just a quick... "you're now registered!" or what have you.
	echo '
		<div id="registration_success">
			<we:cat>
				', $context['title'], '
			</we:cat>
			<div class="windowbg wrc">
				<p>', $context['description'], '</p>
			</div>
		</div>';
}

// Template for giving instructions about COPPA activation.
function template_coppa()
{
	global $context, $settings, $options, $txt, $scripturl;

	// Formulate a nice complicated message!
	echo '
		<we:title>
			', $context['page_title'], '
		</we:title>
		<div class="windowbg2 wrc">
			<p>', $context['coppa']['body'], '</p>
			<p>
				<span><a href="', $scripturl, '?action=coppa;form;member=', $context['coppa']['id'], '" target="_blank" class="new_win">', $txt['coppa_form_link_popup'], '</a> | <a href="', $scripturl, '?action=coppa;form;dl;member=', $context['coppa']['id'], '">', $txt['coppa_form_link_download'], '</a></span>
			</p>
			<p>', $context['coppa']['many_options'] ? $txt['coppa_send_to_two_options'] : $txt['coppa_send_to_one_option'], '</p>';

	// Can they send by post?
	if (!empty($context['coppa']['post']))
		echo '
			<h4>1) ', $txt['coppa_send_by_post'], '</h4>
			<div class="coppa_contact">
				', $context['coppa']['post'], '
			</div>';

	// Can they send by fax??
	if (!empty($context['coppa']['fax']))
		echo '
			<h4>', !empty($context['coppa']['post']) ? '2' : '1', ') ', $txt['coppa_send_by_fax'], '</h4>
			<div class="coppa_contact">
				', $context['coppa']['fax'], '
			</div>';

	// Offer an alternative phone number?
	if ($context['coppa']['phone'])
		echo '
			<p>', $context['coppa']['phone'], '</p>';

	echo '
		</div>';
}

// An easily printable form for giving permission to access the forum for a minor.
function template_coppa_form()
{
	global $context, $settings, $options, $txt, $scripturl;

	// Show the form (As best we can)
	echo '
		<table class="w100 cp4 cs0 centertext">
			<tr>
				<td class="left">', $context['forum_contacts'], '</td>
			</tr>
			<tr>
				<td class="right">
					<em>', $txt['coppa_form_address'], '</em>: ', $context['ul'], '<br>
					', $context['ul'], '<br>
					', $context['ul'], '<br>
					', $context['ul'], '
				</td>
			</tr>
			<tr>
				<td class="right">
					<em>', $txt['coppa_form_date'], '</em>: ', $context['ul'], '
					<br><br>
				</td>
			</tr>
			<tr>
				<td class="left">
					', $context['coppa_body'], '
				</td>
			</tr>
		</table>
		<br>';
}

// Show a window containing the spoken verification code.
function template_verification_sound()
{
	global $context, $settings, $options, $txt;

	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
<head>
	<meta charset="utf-8">
	<meta name="robots" content="noindex">
	<title>', $context['page_title'], '</title>',
	theme_base_css(),
	theme_base_js(1), '
	<style>';

	// Just show the help text and a "close window" link.
	echo '
	</style>
</head>
<body style="margin: 1ex;">
	<div class="popuptext centertext">
		<audio src="', $context['verification_sound_href'], '" controls="controls">';

	if ($context['browser']['is_ie'])
		echo '
			<object classid="clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95" type="audio/x-wav">
				<param name="AutoStart" value="1">
				<param name="FileName" value="', $context['verification_sound_href'], '">
			</object>';
	else
		echo '
			<object type="audio/x-wav" data="', $context['verification_sound_href'], '">
				<a href="', $context['verification_sound_href'], '" rel="nofollow">', $context['verification_sound_href'], '</a>
			</object>';

	echo '
		</audio>
		<br>
		<a href="', $context['verification_sound_href'], ';sound" rel="nofollow">', $txt['visual_verification_sound_again'], '</a><br>
		<a href="#" onclick="$(\'#helf\').remove(); return false;">', $txt['visual_verification_sound_close'], '</a><br>
		<a href="', $context['verification_sound_href'], '" rel="nofollow">', $txt['visual_verification_sound_direct'], '</a>
	</div>
</body>
</html>';
}

function template_admin_register()
{
	global $context, $settings, $options, $scripturl, $txt, $modSettings;

	add_js('
	function onCheckChange()
	{
		var f = document.forms.postForm;
		if (f.emailActivate.checked || f.password.value == "")
			$(f.emailPassword).attr({ disabled: true, checked: true });
		else
			f.emailPassword.disabled = false;
	}');

	echo '
	<div id="admincenter">
		<we:cat>
			', $txt['admin_browse_register_new'], '
		</we:cat>
		<form action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8" name="postForm" id="postForm">
			<div class="windowbg2 wrc">
				<div id="register_screen">';

	if (!empty($context['registration_done']))
		echo '
					<div class="windowbg" id="profile_success">
						', $context['registration_done'], '
					</div>';

	echo '
					<dl class="register_form" id="admin_register_form">
						<dt>
							<strong><label for="user_input">', $txt['admin_register_username'], ':</label></strong>
							<dfn>', $txt['admin_register_username_desc'], '</dfn>
						</dt>
						<dd>
							<input type="text" name="user" id="user_input" tabindex="', $context['tabindex']++, '" size="30" maxlength="25" required>
						</dd>
						<dt>
							<strong><label for="email_input">', $txt['admin_register_email'], ':</label></strong>
							<dfn>', $txt['admin_register_email_desc'], '</dfn>
						</dt>
						<dd>
							<input type="email" name="email" id="email_input" tabindex="', $context['tabindex']++, '" size="30" required>
						</dd>
						<dt>
							<strong><label for="password_input">', $txt['admin_register_password'], ':</label></strong>
							<dfn>', $txt['admin_register_password_desc'], '</dfn>
						</dt>
						<dd>
							<input type="password" name="password" id="password_input" tabindex="', $context['tabindex']++, '" size="30" onchange="onCheckChange();" required>
						</dd>';

	if (!empty($context['member_groups']))
	{
		echo '
						<dt>
							<strong><label for="group_select">', $txt['admin_register_group'], ':</label></strong>
							<dfn>', $txt['admin_register_group_desc'], '</dfn>
						</dt>
						<dd>
							<select name="group" id="group_select" tabindex="', $context['tabindex']++, '">';

		foreach ($context['member_groups'] as $id => $name)
			echo '
								<option value="', $id, '">', $name, '</option>';

		echo '
							</select>
						</dd>';
	}

	echo '
						<dt>
							<strong><label for="emailPassword_check">', $txt['admin_register_email_detail'], ':</label></strong>
							<dfn>', $txt['admin_register_email_detail_desc'], '</dfn>
						</dt>
						<dd>
							<input type="checkbox" name="emailPassword" id="emailPassword_check" tabindex="', $context['tabindex']++, '" checked disabled>
						</dd>
						<dt>
							<strong><label for="emailActivate_check">', $txt['admin_register_email_activate'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="emailActivate" id="emailActivate_check" tabindex="', $context['tabindex']++, '"', !empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1 ? ' checked' : '', ' onclick="onCheckChange();">
						</dd>
					</dl>
					<div class="righttext">
						<input type="submit" name="regSubmit" value="', $txt['register'], '" tabindex="', $context['tabindex']++, '" class="submit">
						<input type="hidden" name="sa" value="register">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					</div>
				</div>
			</div>
		</form>
	</div>
	<br class="clear">';
}

// Form for editing the agreement shown for people registering to the forum.
function template_edit_agreement()
{
	global $context, $settings, $options, $scripturl, $txt;

	// Just a big box to edit the text file ;).
	echo '
		<we:cat>
			', $txt['registration_agreement'], '
		</we:cat>';

	// Warning for if the file isn't writable.
	if (!empty($context['warning']))
		echo '
		<p class="error">', $context['warning'], '</p>';

	echo '
		<div class="windowbg2 wrc" id="registration_agreement">';

	// Is there more than one language to choose from?
	if (count($context['editable_agreements']) > 1)
	{
		echo '
			<div class="information">
				<form action="', $scripturl, '?action=admin;area=regcenter" id="change_reg" method="post" accept-charset="UTF-8" style="display: inline;">
					<strong>', $txt['admin_agreement_select_language'], ':</strong>&nbsp;
					<select name="agree_lang" onchange="$(\'#change_reg\').submit();" tabindex="', $context['tabindex']++, '">';

		foreach ($context['editable_agreements'] as $file => $name)
			echo '
						<option value="', $file, '"', $context['current_agreement'] == $file ? ' selected' : '', '>', $name, '</option>';

		echo '
					</select>
					<div class="righttext">
						<input type="hidden" name="sa" value="agreement">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
						<input type="submit" name="change" value="', $txt['admin_agreement_select_language_change'], '" tabindex="', $context['tabindex']++, '" class="submit">
					</div>
				</form>
			</div>';
	}

	echo '
			<form action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8">';

	// Show the actual agreement in an oversized text box.
	echo '
				<p class="agreement">
					<textarea cols="70" rows="20" name="agreement" id="agreement">', $context['agreement'], '</textarea>
				</p>
				<p>
					<label><input type="checkbox" name="requireAgreement" id="requireAgreement"', $context['require_agreement'] ? ' checked' : '', ' tabindex="', $context['tabindex']++, '" value="1"> ', $txt['admin_agreement'], '.</label>
				</p>
				<div class="righttext">
					<input type="submit" value="', $txt['save'], '" tabindex="', $context['tabindex']++, '" class="save">
					<input type="hidden" name="agree_lang" value="', $context['current_agreement'], '">
					<input type="hidden" name="sa" value="agreement">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</form>
		</div>
		<br class="clear">';
}

function template_edit_reserved_words()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
		<we:cat>
			', $txt['admin_reserved_set'], '
		</we:cat>
		<form id="registration_agreement" action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="UTF-8">
			<div class="windowbg2 wrc">
				<h4>', $txt['admin_reserved_line'], '</h4>
				<p class="reserved_names">
					<textarea cols="30" rows="6" id="reserved">', implode("\n", $context['reserved_words']), '</textarea>
				</p>
				<ul class="reset">
					<li><label><input type="checkbox" name="matchword" id="matchword" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_word'] ? ' checked' : '', '> ', $txt['admin_match_whole'], '</label></li>
					<li><label><input type="checkbox" name="matchcase" id="matchcase" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_case'] ? ' checked' : '', '> ', $txt['admin_match_case'], '</label></li>
					<li><label><input type="checkbox" name="matchuser" id="matchuser" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_user'] ? ' checked' : '', '> ', $txt['admin_check_user'], '</label></li>
					<li><label><input type="checkbox" name="matchname" id="matchname" tabindex="', $context['tabindex']++, '"', $context['reserved_word_options']['match_name'] ? ' checked' : '', '> ', $txt['admin_check_display'], '</label></li>
				</ul>
				<div class="righttext">
					<input type="submit" value="', $txt['save'], '" name="save_reserved_names" tabindex="', $context['tabindex']++, '" style="margin: 1ex" class="save">
					<input type="hidden" name="sa" value="reservednames">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</div>
		</form>
		<br class="clear">';
}

?>