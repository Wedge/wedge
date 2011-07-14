
function smfRegister(formID, passwordDifficultyLevel, regTextStrings)
{
	this.addVerify = addVerificationField;
	this.autoSetup = autoSetup;
	this.refreshMainPassword = refreshMainPassword;
	this.refreshVerifyPassword = refreshVerifyPassword;

	var
		verificationFields = [],
		verificationFieldLength = 0,
		textStrings = regTextStrings ? regTextStrings : [],
		passwordLevel = passwordDifficultyLevel ? passwordDifficultyLevel : 0;

	// Setup all the fields!
	autoSetup(formID);

	// This is a field which requires some form of verification check.
	function addVerificationField(fieldType, fieldID)
	{
		// Check the field exists.
		var inputHandle = $('#' + fieldID);
		if (!inputHandle.length)
			return;

		// Get the handles.
		var
			imageHandle = $('#' + fieldID + '_img'),
			// !!! Look like this one is never used...
			divHandle = $('#' + fieldID + '_div'),
			eventHandler = false;

		// What is the event handler?
		if (fieldType == 'pwmain')
			eventHandler = refreshMainPassword;
		else if (fieldType == 'pwverify')
			eventHandler = refreshVerifyPassword;
		else if (fieldType == 'username')
			eventHandler = refreshUsername;
		else if (fieldType == 'reserved')
			eventHandler = refreshMainPassword;

		// Store this field.
		verificationFields[fieldType == 'reserved' ? fieldType + verificationFieldLength : fieldType] = [
			fieldID, inputHandle[0], imageHandle, divHandle[0], fieldType, inputHandle[0].className
		];

		// Keep a count to it!
		verificationFieldLength++;

		// Step to it!
		if (eventHandler)
		{
			// Username will auto-check on blur!
			inputHandle.keyup(eventHandler).blur(autoCheckUsername);
			eventHandler();
		}

		// Make the div visible!
		divHandle.show();
	}

	// This function will automatically pick up all the necessary verification fields and initialize their visual status.
	function autoSetup(formID)
	{
		return !!($('#' + formID).find('input[type="text"][id*="autov"], input[type="password"][id*="autov"]').each(function () {
			var curType = 0, id = this.id;

			// Username can only be done with XML.
			if (id.indexOf('username') != -1 && can_ajax)
				curType = 'username';
			else if (id.indexOf('pwmain') != -1)
				curType = 'pwmain';
			else if (id.indexOf('pwverify') != -1)
				curType = 'pwverify';
			// This means this field is reserved and cannot be contained in the password!
			else if (id.indexOf('reserve') != -1)
				curType = 'reserved';

			// If we're happy let's add this element!
			if (curType)
				addVerificationField(curType, id);

			// If this is the username do we also have a button to find the user?
			if (curType == 'username')
				$('#' + id + '_link').click(checkUsername);
		}).length);
	}

	// What is the password state?
	function refreshMainPassword(called_from_verify)
	{
		if (!verificationFields.pwmain)
			return false;

		var curPass = verificationFields.pwmain[1].value, stringIndex = '';

		// Is it a valid length?
		if ((curPass.length < 8 && passwordLevel >= 1) || curPass.length < 4)
			stringIndex = 'password_short';

		// More than basic?
		if (passwordLevel >= 1)
		{
			// If there is a username, check it's not in the password!
			if (verificationFields.username && verificationFields.username[1].value && curPass.indexOf(verificationFields.username[1].value) != -1)
				stringIndex = 'password_reserved';

			// Any reserved fields?
			for (var i in verificationFields)
				if (verificationFields[i][4] == 'reserved' && verificationFields[i][1].value && curPass.indexOf(verificationFields[i][1].value) != -1)
					stringIndex = 'password_reserved';

			// Finally - is it hard and, as such, requiring mixed cases and numbers?
			if ((passwordLevel > 1) && ((curPass == curPass.toLowerCase()) || (!curPass.match(/(\D\d|\d\D)/))))
				stringIndex = 'password_numbercase';
		}

		var isValid = stringIndex == '' ? true : false;
		if (stringIndex == '')
			stringIndex = 'password_valid';

		// Set the image.
		setVerificationImage(verificationFields.pwmain[2], isValid, textStrings[stringIndex] ? textStrings[stringIndex] : '');
		verificationFields.pwmain[1].className = verificationFields.pwmain[5] + ' ' + (isValid ? 'valid' : 'invalid') + '_input';

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
		var
			isValid = verificationFields.pwmain[1].value == verificationFields.pwverify[1].value && refreshMainPassword(true),
			alt = textStrings[isValid ? 'password_valid' : 'password_no_match'] ? textStrings[isValid ? 'password_valid' : 'password_no_match'] : '';

		setVerificationImage(verificationFields.pwverify[2], isValid, alt);
		verificationFields.pwverify[1].className = verificationFields.pwverify[5] + ' ' + (isValid ? 'valid' : 'invalid') + '_input';

		return true;
	}

	// If the username is changed just revert the status of whether it's valid!
	function refreshUsername()
	{
		if (!verificationFields.username)
			return false;

		// Restore the class name.
		if (verificationFields.username[1].className)
			verificationFields.username[1].className = verificationFields.username[5];
		// Check the image is correct.
		var alt = textStrings.username_check ? textStrings.username_check : '';
		setVerificationImage(verificationFields.username[2], 'check', alt);

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
			ajax_indicator(true);

		// Request a search on that username.
		getXMLDocument(smf_prepareScriptUrl(we_script) + 'action=register;sa=usernamecheck;xml;username=' + curUsername.php_to8bit().php_urlencode(), checkUsernameCallback);

		return true;
	}

	// Callback for getting the username data.
	function checkUsernameCallback(XMLDoc)
	{
		var
			isValid = $('username', XMLDoc).attr('valid') == 1,
			alt = textStrings['username_' + (isValid ? 'valid' : 'invalid')];

		verificationFields.username[1].className = verificationFields.username[5] + ' ' + (isValid ? 'valid' : 'invalid') + '_input';
		setVerificationImage(verificationFields.username[2], isValid, alt);

		ajax_indicator(false);
	}

	// Set the image to be the correct type.
	function setVerificationImage(imageHandle, imageIcon, alt)
	{
		if (!imageHandle.length)
			return false;
		if (!alt)
			alt = '*';

		imageHandle.attr({
			src: we_images_url + '/icons/' + (imageIcon ? (imageIcon == 'check' ? 'field_check.gif' : 'field_valid.gif') : 'field_invalid.gif'),
			alt: alt,
			title: alt
		});

		return true;
	}
}

/* Optimize:
verificationFields = v
verificationFieldLength = vl
textStrings = ts
passwordLevel = pl
inputHandle = ih
imageHandle = mh
divHandle = dh
*/
