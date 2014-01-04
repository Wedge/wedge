/*!
 * Helper functions for the registration process
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

@language Login;

function verifyAgree()
{
	if (document.forms.registration.we_autov_pwmain.value != document.forms.registration.we_autov_pwverify.value)
	{
		say($txt['register_passwords_differ_js']);
		return false;
	}

	return true;
}

function weRegister(formID, passwordDifficultyLevel)
{
	var
		verificationFields = [],
		verificationFieldLength = 0,
		txt_username_valid = $txt['registration_username_available'],
		txt_username_invalid = $txt['registration_username_unavailable'],
		txt_username_check = $txt['registration_username_check'],
		txt_password_short = $txt['registration_password_short'],
		txt_password_reserved = $txt['registration_password_reserved'],
		txt_password_numbercase = $txt['registration_password_numbercase'],
		txt_password_no_match = $txt['registration_password_no_match'],
		txt_password_valid = $txt['registration_password_valid'];

	// This will automatically pick up all the necessary verification fields and initialize their visual status.
	$('#' + formID).find('input[type="text"][id*="autov"], input[type="password"][id*="autov"]').each(function ()
	{
		var curType = 0, eventHandler = false, id = this.id;

		// Username can only be done with XML.
		if (id.indexOf('username') != -1)
		{
			curType = 'username';
			eventHandler = refreshUsername;
		}
		else if (id.indexOf('pwmain') != -1)
		{
			curType = 'pwmain';
			eventHandler = refreshMainPassword;
		}
		else if (id.indexOf('pwverify') != -1)
		{
			curType = 'pwverify';
			eventHandler = refreshVerifyPassword;
		}
		// This means this field is reserved and cannot be contained in the password!
		else if (id.indexOf('reserve') != -1)
		{
			curType = 'reserved';
			eventHandler = refreshMainPassword;
		}

		// If we're happy let's add this element!
		if (curType)
		{
			// This is a field which requires some form of verification check.
			// Get the handles.
			var $imageHandle = $('#' + id + '_img'), $inputHandle = $('#' + id);
			if ($inputHandle.length)
			{
				// Store this field.
				verificationFields[curType == 'reserved' ? curType + verificationFieldLength : curType] = [
					id, $inputHandle[0], $imageHandle, curType, $inputHandle[0].className
				];

				// Keep a count to it!
				verificationFieldLength++;

				// Step to it!
				if (eventHandler)
				{
					// Username will auto-check on blur!
					$inputHandle.keyup(eventHandler).blur(autoCheckUsername);
					eventHandler();
				}

				// Make the div visible!
				$('#' + id + '_div').show();
			}
		}

		// If this is the username do we also have a button to find the user?
		if (curType == 'username')
			$('#' + id + '_link').click(checkUsername);
	});

	// What is the password state?
	function refreshMainPassword(called_from_verify)
	{
		if (!verificationFields.pwmain)
			return false;

		var curPass = verificationFields.pwmain[1].value, result = '';

		// Is it a valid length?
		if ((curPass.length < 8 && passwordDifficultyLevel >= 1) || curPass.length < 4)
			result = txt_password_short;

		// More than basic?
		if (passwordDifficultyLevel >= 1)
		{
			// If there is a username, check it's not in the password!
			if (verificationFields.username && verificationFields.username[1].value && curPass.indexOf(verificationFields.username[1].value) != -1)
				result = txt_password_reserved;

			// Any reserved fields?
			for (var i in verificationFields)
				if (verificationFields[i][3] == 'reserved' && verificationFields[i][1].value && curPass.indexOf(verificationFields[i][1].value) != -1)
					result = txt_password_reserved;

			// Finally - is it hard and, as such, requiring mixed cases and numbers?
			if ((passwordDifficultyLevel > 1) && ((curPass == curPass.toLowerCase()) || (!curPass.match(/(\D\d|\d\D)/))))
				result = txt_password_numbercase;
		}

		var isValid = result == '';

		// Set the image.
		setVerificationImage(verificationFields.pwmain[2], isValid, result || txt_password_valid);
		verificationFields.pwmain[1].className = verificationFields.pwmain[4] + ' ' + (isValid ? 'valid' : 'invalid') + '_input';

		// As this has changed the verification one may have too!
		if (verificationFields.pwverify && !called_from_verify)
			refreshVerifyPassword();

		return isValid;
	}

	// Check that the verification password matches the main one!
	function refreshVerifyPassword()
	{
		// Can't do anything without something to check again!
		if (!verificationFields.pwmain)
			return false;

		// Check and set valid status!
		var isValid = verificationFields.pwmain[1].value == verificationFields.pwverify[1].value && refreshMainPassword(true);

		setVerificationImage(verificationFields.pwverify[2], isValid, isValid ? txt_password_valid : txt_password_no_match);
		verificationFields.pwverify[1].className = verificationFields.pwverify[4] + ' ' + (isValid ? 'valid' : 'invalid') + '_input';

		return true;
	}

	// If the username is changed just revert the status of whether it's valid!
	function refreshUsername()
	{
		if (!verificationFields.username)
			return false;

		// Restore the class name.
		if (verificationFields.username[1].className)
			verificationFields.username[1].className = verificationFields.username[4];
		// Check the image is correct.
		setVerificationImage(verificationFields.username[2], 'check', txt_username_check);

		// Check the password is still OK.
		refreshMainPassword();

		return true;
	}

	// This is a pass through function that ensures we don't do any of the AJAX notification stuff.
	function autoCheckUsername()
	{
		checkUsername(true);
	}

	// Check whether the username exists?
	function checkUsername(is_auto)
	{
		if (!verificationFields.username)
			return false;

		// Get the username and do nothing without one!
		var curUsername = verificationFields.username[1].value;
		if (!curUsername)
			return false;

		if (!is_auto)
			show_ajax();

		// Request a search on that username.
		$.get(weUrl('action=register;sa=usernamecheck;username=' + encodeURIComponent(curUsername)), function (XMLDoc)
		{
			var isValid = $('username', XMLDoc).attr('valid') == 1;

			verificationFields.username[1].className = verificationFields.username[4] + ' ' + (isValid ? 'valid' : 'invalid') + '_input';
			setVerificationImage(verificationFields.username[2], isValid, isValid ? txt_username_valid : txt_username_invalid);

			hide_ajax();
		});

		return true;
	}

	// Set the image to be the correct type.
	function setVerificationImage($imageHandle, imageIcon, alt)
	{
		$imageHandle.attr({
			src: we_assets + '/icons/' + (imageIcon ? (imageIcon == 'check' ? 'field_check' : 'field_valid') : 'field_invalid') + '.gif',
			alt: alt,
			title: alt
		});

		return true;
	}
}
