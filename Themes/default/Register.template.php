<?php
/**
 * The registration agreement and form, as well as the COPPA form.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

// Before showing users a registration form, show them the registration agreement.
function template_registration_agreement()
{
	global $context, $txt;

	echo '
		<form action="<URL>?action=register" method="post" accept-charset="UTF-8" id="registration">
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
	global $context, $theme, $txt, $settings;

	add_js_file('scripts/register.js');

	add_js('
	new weRegister("registration", ' . (empty($settings['password_strength']) ? 0 : $settings['password_strength']) . ');');

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
		<form action="<URL>?action=register2" method="post" accept-charset="UTF-8" name="registration" id="registration" onsubmit="return verifyAgree();">
			<we:cat>
				', $txt['registration_form'], '
			</we:cat>
			<we:title2>
				', $txt['required_info'], '
			</we:title2>
			<div class="windowbg2 wrc">
				<fieldset>
					<dl class="register_form">
						<dt><strong><label for="we_autov_username">', $txt['username'], ':</label></strong></dt>
						<dd>
							<input name="user" id="we_autov_username" size="30" tabindex="', $context['tabindex']++, '" maxlength="25" value="', isset($context['username']) ? $context['username'] : '', '" required>
							<span id="we_autov_username_div" class="hide">
								<a id="we_autov_username_link" href="#">
									<img id="we_autov_username_img" src="', $theme['images_url'], '/icons/field_check.gif">
								</a>
							</span>
						</dd>
						<dt><strong><label for="we_autov_reserve1">', $txt['email'], ':</label></strong></dt>
						<dd>
							<input type="email" name="email" id="we_autov_reserve1" size="30" placeholder="', $txt['email_placeholder'], '" tabindex="', $context['tabindex']++, '" value="', isset($context['email']) ? $context['email'] : '', '" required>
						</dd>
						<dt><strong><label for="allow_email">', $txt['allow_user_email'], ':</label></strong></dt>
						<dd>
							<input type="checkbox" name="allow_email" id="allow_email" tabindex="', $context['tabindex']++, '">
						</dd>
					</dl>
					<dl class="register_form" id="password1_group">
						<dt><strong><label for="we_autov_pwmain">', $txt['choose_pass'], ':</label></strong></dt>
						<dd>
							<input type="password" name="passwrd1" id="we_autov_pwmain" size="30" tabindex="', $context['tabindex']++, '">
							<span id="we_autov_pwmain_div" class="hide">
								<img id="we_autov_pwmain_img" src="', $theme['images_url'], '/icons/field_invalid.gif">
							</span>
						</dd>
					</dl>
					<dl class="register_form" id="password2_group">
						<dt><strong><label for="we_autov_pwverify">', $txt['verify_pass'], ':</label></strong></dt>
						<dd>
							<input type="password" name="passwrd2" id="we_autov_pwverify" size="30" tabindex="', $context['tabindex']++, '">
							<span id="we_autov_pwverify_div" class="hide">
								<img id="we_autov_pwverify_img" src="', $theme['images_url'], '/icons/field_valid.gif">
							</span>
						</dd>
					</dl>
					<dl class="register_form" id="timezone_group">
						<dt><strong><label for="timezone">', $txt['choose_timezone'], '</label></strong></dt>
						<dd>
							<select name="timezone" id="timezone">';
	foreach ($context['user_timezones'] as $tz_id => $tz_desc)
		echo '
								<option value="', $tz_id, '"', $tz_id == $context['user_selected_timezone'] ? ' selected' : '', '>', $tz_desc, '</option>';

	echo '
							</select>
						</dd>
					</dl>
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
							<strong', !empty($field['is_error']) ? ' style="color: red"' : '', '>', $field['label'], ':</strong>';

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
							<input', $field['type'] == 'password' ? ' type="password"' : '', ' name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" tabindex="', $context['tabindex']++, '"', $field['input_attr'], '>';

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
							<strong', !empty($field['is_error']) ? ' style="color: red"' : '', '>', $field['name'], ':</strong>
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
				<fieldset class="center">
					', template_control_verification($context['visual_verification_id'], 'all'), '
				</fieldset>
			</div>';
	}

	echo '
			<div id="confirm_buttons">';

	if (!$context['require_agreement'] && $context['show_coppa'])
		echo '
				<input type="submit" name="accept_agreement" value="', $context['coppa_agree_above'], '" class="submit" /><br><br>
				<input type="submit" name="accept_agreement_coppa" value="', $context['coppa_agree_below'], '" class="submit">';
	else
		echo '
				<input type="submit" name="regSubmit" value="', $txt['register'], '" tabindex="', $context['tabindex']++, '" class="submit">';

	echo '
			</div>
			<input type="hidden" name="step" value="2">
		</form>';
}

// After registration... all done ;).
function template_after()
{
	global $context;

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
	global $context, $txt;

	// Formulate a nice complicated message!
	echo '
		<we:title>
			', $context['page_title'], '
		</we:title>
		<div class="windowbg2 wrc">
			<p>', $context['coppa']['body'], '</p>
			<p>
				<span><a href="<URL>?action=coppa;form;member=', $context['coppa']['id'], '" target="_blank" class="new_win">', $txt['coppa_form_link_popup'], '</a> | <a href="<URL>?action=coppa;form;dl;member=', $context['coppa']['id'], '">', $txt['coppa_form_link_download'], '</a></span>
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
	global $context, $txt;

	// Show the form (As best we can)
	echo '
		<table class="w100 cp4 cs0">
			<tr>
				<td>', $context['forum_contacts'], '</td>
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
				<td>
					', $context['coppa_body'], '
				</td>
			</tr>
		</table>
		<br>';
}

function template_admin_register()
{
	global $context, $txt, $settings;

	add_js('
	function onCheckChange()
	{
		var f = document.forms.postForm;
		if (f.emailActivate.checked || f.password.value == "")
			$(f.emailPassword).prop({ disabled: true, checked: true });
		else
			f.emailPassword.disabled = false;
	}');

	echo '
		<we:cat>
			', $txt['admin_browse_register_new'], '
		</we:cat>
		<form action="<URL>?action=admin;area=regcenter" method="post" accept-charset="UTF-8" name="postForm" id="postForm">
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
							<input name="user" id="user_input" tabindex="', $context['tabindex']++, '" size="30" maxlength="25" required>
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
							<input type="checkbox" name="emailActivate" id="emailActivate_check" tabindex="', $context['tabindex']++, '"', !empty($settings['registration_method']) && $settings['registration_method'] == 1 ? ' checked' : '', ' onclick="onCheckChange();">
						</dd>
					</dl>
					<div class="right">
						<input type="submit" name="regSubmit" value="', $txt['register'], '" tabindex="', $context['tabindex']++, '" class="submit">
						<input type="hidden" name="sa" value="register">
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					</div>
				</div>
			</div>
		</form>';
}

// Form for editing the agreement shown for people registering to the forum.
function template_edit_agreement()
{
	global $context, $txt;

	if (!empty($context['was_saved']))
		echo '
		<div class="windowbg" id="profile_success">
			', $txt['changes_saved'], '
		</div>';

	// Just a big box to edit the text file ;).
	echo '
		<we:cat>
			', $txt['registration_agreement'], '
		</we:cat>';

	// Is there a postbox? If not, we're displaying the list of languages.
	if (empty($context['postbox']))
	{
		echo '
		<div class="windowbg2 wrc">
			<strong>', $txt['admin_agreement_select_language'], ':</strong>&nbsp;
			<ul>';

		foreach ($context['languages'] as $lang)
			echo '
				<li><span class="flag_', $lang['filename'], '"></span> <a href="<URL>?action=admin;area=regcenter;sa=agreement;agreelang=', $lang['filename'], '">', $lang['name'], '</a></li>';

		echo '
			</ul>
			<form action="<URL>?action=admin;area=regcenter;sa=agreement" method="post" accept-charset="UTF-8">
				<p>
					<label><input type="checkbox" name="requireAgreement" id="requireAgreement"', $context['require_agreement'] ? ' checked' : '', ' tabindex="', $context['tabindex']++, '" value="1"> ', $txt['admin_agreement'], '.</label>
				</p>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<div class="right">
					<input type="submit" class="save" name="updatelang" value="', $txt['save'], '">
				</div>
			</form>
		</div>
		<we:cat>', $txt['admin_agreement_reagree'], '</we:cat>
		<div class="windowbg2 wrc">
			<form action="<URL>?action=admin;area=regcenter;sa=agreement" method="post" accept-charset="UTF-8">
				<dl class="settings newsletter">
					<dt>', $txt['admin_agreement_last_changed'], '</dt>
					<dd>', $context['last_changed'], '</dd>
					<dt>', $txt['admin_agreement_last_reagree'], '</dt>
					<dd>', $context['last_reagree'], '</dd>
					<dt>', $txt['admin_agreement_force'], '</dt>
					<dd>
						<select name="force">
							<option value="0"', $context['agreement_force'] == 0 ? ' selected' : '', '>', $txt['admin_agreement_force_post'], '</option>
							<option value="1"', $context['agreement_force'] == 1 ? ' selected' : '', '>', $txt['admin_agreement_force_all'], '</option>
						</select>
					</dd>
					<dt>', $txt['admin_agreement_exclude_groups'], '</dt>
					<dd>';
		foreach ($context['groups']['normal'] as $group)
			echo '
						<label><input type="checkbox" name="group', $group['id'], '"', $group['id'] == 1 ? ' checked' : '', '> <span class="group', $group['id'], '">', $group['name'], '</span></label><br>';
		echo '
					</dd>
					<dt></dt>
					<dd>';
		foreach ($context['groups']['post'] as $group)
			echo '
						<label><input type="checkbox" name="group', $group['id'], '"', $group['id'] == 1 ? ' checked' : '', '> <span class="group', $group['id'], '">', $group['name'], '</span></label> &ndash; ', $group['min_posts'], ' <span class="posts"></span><br>';
		echo '
					</dd>
				</dl>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<div class="right">
					<input type="submit" class="save" name="setreagree" value="', $txt['save'], '">
				</div>
			</form>
		</div>';
	}
	else
	{
		echo '
		<div class="windowbg2 wrc" id="registration_agreement">
			<form action="<URL>?action=admin;area=regcenter" method="post" accept-charset="UTF-8">
				', $context['postbox']->outputEditor(), '
				<div class="right">
					<input type="hidden" name="agreelang" value="', $context['agreelang'], '">
					<input type="hidden" name="sa" value="agreement">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					', $context['postbox']->outputButtons(), '
				</div>
			</form>
		</div>
		<br class="clear">';
	}
}
