
/*
	Helper functions.
	Can safely be called by mods.
*/

// Checks for variable in theArray.
function array_search(variable, theArray)
{
	for (var i in theArray)
		if (theArray[i] == variable)
			return i;

	return null;
}

// Replaces the currently selected text with the passed text.
function replaceText(text, oTextHandle)
{
	// Attempt to create a text range (IE).
	if ('caretPos' in oTextHandle && 'createTextRange' in oTextHandle)
	{
		var caretPos = oTextHandle.caretPos;

		caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == ' ' ? text + ' ' : text;
		caretPos.select();
	}
	// Mozilla text range replace.
	else if ('selectionStart' in oTextHandle)
	{
		var begin = oTextHandle.value.substr(0, oTextHandle.selectionStart);
		var end = oTextHandle.value.substr(oTextHandle.selectionEnd);
		var scrollPos = oTextHandle.scrollTop;

		oTextHandle.value = begin + text + end;

		if (oTextHandle.setSelectionRange)
		{
			oTextHandle.focus();
			var ma, goForward = is_opera && (ma = text.match(/\n/g)) ? ma.length : 0;
			oTextHandle.setSelectionRange(begin.length + text.length + goForward, begin.length + text.length + goForward);
		}
		oTextHandle.scrollTop = scrollPos;
	}
	// Just put it on the end.
	else
	{
		oTextHandle.value += text;
		oTextHandle.focus(oTextHandle.value.length - 1);
	}
}

// Surrounds the selected text with text1 and text2.
function surroundText(text1, text2, oTextHandle)
{
	// Can a text range be created?
	if ('caretPos' in oTextHandle && 'createTextRange' in oTextHandle)
	{
		var caretPos = oTextHandle.caretPos, temp_length = caretPos.text.length;

		caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == ' ' ? text1 + caretPos.text + text2 + ' ' : text1 + caretPos.text + text2;

		if (temp_length == 0)
		{
			caretPos.moveStart('character', -text2.length);
			caretPos.moveEnd('character', -text2.length);
			caretPos.select();
		}
		else
			oTextHandle.focus(caretPos);
	}
	// Mozilla text range wrap.
	else if ('selectionStart' in oTextHandle)
	{
		var
			begin = oTextHandle.value.substr(0, oTextHandle.selectionStart),
			selection = oTextHandle.value.substr(oTextHandle.selectionStart, oTextHandle.selectionEnd - oTextHandle.selectionStart),
			end = oTextHandle.value.substr(oTextHandle.selectionEnd),
			newCursorPos = oTextHandle.selectionStart,
			scrollPos = oTextHandle.scrollTop;

		oTextHandle.value = begin + text1 + selection + text2 + end;

		if (oTextHandle.setSelectionRange)
		{
			var
				t1 = is_opera ? text1.match(/\n/g) : '',
				t2 = is_opera ? text2.match(/\n/g) : '',
				goForward1 = t1 ? t1.length : 0,
				goForward2 = t2 ? t2.length : 0;

			if (selection.length == 0)
				oTextHandle.setSelectionRange(newCursorPos + text1.length + goForward1, newCursorPos + text1.length + goForward1);
			else
				oTextHandle.setSelectionRange(newCursorPos, newCursorPos + text1.length + selection.length + text2.length + goForward1 + goForward2);
			oTextHandle.focus();
		}
		oTextHandle.scrollTop = scrollPos;
	}
	// Just put them on the end, then.
	else
	{
		oTextHandle.value += text1 + text2;
		oTextHandle.focus(oTextHandle.value.length - 1);
	}
}

// Split a quote if we press Enter inside it.
function splitQuote(oEvent)
{
	// Did we just press Enter?
	if (oEvent.which != 13)
		return true;

	// Where are we, already?
	if ('selectionStart' in this)
		var selectionStart = this.selectionStart;
	else
	{
		var range = document.selection.createRange(), dul = range.duplicate();
		dul.moveToElementText(this);
		dul.setEndPoint('EndToEnd', range);
		var selectionStart = dul.text.length - range.text.length;
	}

	var
		selection = this.value.substr(0, selectionStart), lcs = selection.toLowerCase(),
		lcsl = lcs.length, pos = 0, tag, bbcode, taglist = [], extag, log_tags = true,
		protect_tags = this.instanceRef.opt.aProtectTags, closed_tags = this.instanceRef.opt.aClosedTags;

	// Build a list of opened tags...
	while (true)
	{
		pos = lcs.indexOf('[', pos) + 1;
		if (!pos)
			break;
		tag = selection.substring(pos, lcs.indexOf(']', pos + 1));
		bbcode = tag.substr(tag.charAt(0) === '/' ? 1 : 0);

		if (tag.charAt(0) === '/')
		{
			if (!taglist.length)
				break;
			if (!log_tags && bbcode != taglist[taglist.length - 1].substr(0, bbcode.length))
				continue;
			do
			{
				extag = taglist.pop();
				log_tags |= in_array(extag, protect_tags);
			}
			while (extag && bbcode != extag.substr(0, bbcode.length).toLowerCase());
		}
		else if (log_tags && !in_array(bbcode, closed_tags))
			taglist.push(bbcode);
		if (log_tags && in_array(bbcode, protect_tags))
			log_tags = false;
	}

	var len = taglist.length, closers = [], j;
	if (len)
	{
		for (j = 0; j < len; j++)
			closers.push('[/' + (taglist[j].indexOf(' ') > 0 ? taglist[j].substr(0, taglist[j].indexOf(' ')) : taglist[j]) + ']');
		surroundText(closers.reverse().join('') + '\n', '\n\n[' + taglist.join('][') + ']', this);
	}

	return true;
};

/*
	A smiley is worth
	a thousands words.
*/

function smc_SmileyBox(oOptions)
{
	this.opt = oOptions;
	this.oSmileyRowsContent = {};
	this.oSmileyPopupWindow = null;

	// Get the HTML content of the smileys visible on the post screen.
	this.getSmileyRowsContent('postform');

	// Inject the HTML.
	$('#' + this.opt.sContainerDiv).html(this.opt.sSmileyBoxTemplate.easyReplace({
		smileyRows: this.oSmileyRowsContent.postform,
		moreSmileys: this.opt.oSmileyLocations.popup.length == 0 ? '' : this.opt.sMoreSmileysTemplate.easyReplace({
			moreSmileysId: this.opt.sUniqueId + '_addMoreSmileys'
		})
	}));

	// Initialize the smileys.
	this.initSmileys('postform', document);

	// Initialize the [more] button.
	if (this.opt.oSmileyLocations.popup.length > 0)
		$('#' + this.opt.sUniqueId + '_addMoreSmileys').data('that', this).click(function () {
			$(this).data('that').handleShowMoreSmileys();
			return false;
		});
}

// Loop through the smileys to setup the HTML.
smc_SmileyBox.prototype.getSmileyRowsContent = function(sLocation)
{
	// If it's already defined, don't bother.
	if (sLocation in this.oSmileyRowsContent)
		return;

	this.oSmileyRowsContent[sLocation] = '';

	for (var iSmileyRowIndex = 0, iSmileyRowCount = this.opt.oSmileyLocations[sLocation].length; iSmileyRowIndex < iSmileyRowCount; iSmileyRowIndex++)
	{
		var sSmileyRowContent = '';
		for (var iSmileyIndex = 0, iSmileyCount = this.opt.oSmileyLocations[sLocation][iSmileyRowIndex].length; iSmileyIndex < iSmileyCount; iSmileyIndex++)
			sSmileyRowContent += this.opt.sSmileyTemplate.easyReplace({
				smileySource: this.opt.oSmileyLocations[sLocation][iSmileyRowIndex][iSmileyIndex].sSrc.php_htmlspecialchars(),
				smileyDescription: this.opt.oSmileyLocations[sLocation][iSmileyRowIndex][iSmileyIndex].sDescription.php_htmlspecialchars(),
				smileyCode: this.opt.oSmileyLocations[sLocation][iSmileyRowIndex][iSmileyIndex].sCode.php_htmlspecialchars(),
				smileyId: this.opt.sUniqueId + '_' + sLocation + '_' + iSmileyRowIndex.toString() + '_' + iSmileyIndex.toString()
			});

		this.oSmileyRowsContent[sLocation] += this.opt.sSmileyRowTemplate.easyReplace({
			smileyRow: sSmileyRowContent
		});
	}
};

smc_SmileyBox.prototype.initSmileys = function(sLocation, oDocument)
{
	for (var iSmileyRowIndex = 0, iSmileyRowCount = this.opt.oSmileyLocations[sLocation].length; iSmileyRowIndex < iSmileyRowCount; iSmileyRowIndex++)
	{
		for (var iSmileyIndex = 0, iSmileyCount = this.opt.oSmileyLocations[sLocation][iSmileyRowIndex].length; iSmileyIndex < iSmileyCount; iSmileyIndex++)
		{
			var oSmiley = oDocument.getElementById(this.opt.sUniqueId + '_' + sLocation + '_' + iSmileyRowIndex.toString() + '_' + iSmileyIndex.toString());
			oSmiley.instanceRef = this;
			oSmiley.style.cursor = 'pointer';
			oSmiley.onclick = function () {
				this.instanceRef.clickHandler(this);
				return false;
			};
		}
	}
};

smc_SmileyBox.prototype.clickHandler = function(oSmileyImg)
{
	// Dissect the id to determine its exact smiley properties.
	var aMatches = oSmileyImg.id.match(/([^_]+)_(\d+)_(\d+)$/);
	if (aMatches.length == 4 && 'sClickHandler' in this.opt)
		this.opt.sClickHandler(this.opt.oSmileyLocations[aMatches[1]][aMatches[2]][aMatches[3]]);

	return false;
};

smc_SmileyBox.prototype.handleShowMoreSmileys = function()
{
	// Focus the window if it's already opened.
	if (this.oSmileyPopupWindow != null && 'closed' in this.oSmileyPopupWindow && !this.oSmileyPopupWindow.closed)
	{
		this.oSmileyPopupWindow.focus();
		return;
	}

	// Get the smiley HTML.
	this.getSmileyRowsContent('popup');

	// Open the popup.
	this.oSmileyPopupWindow = window.open('about:blank', this.opt.sUniqueId + '_addMoreSmileysPopup', 'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,width=480,height=220,resizable=yes');

	// Paste the template in the popup.
	this.oSmileyPopupWindow.document.open('text/html', 'replace');
	this.oSmileyPopupWindow.document.write(this.opt.sMoreSmileysPopupTemplate.easyReplace({
		smileyRows: this.oSmileyRowsContent.popup,
		moreSmileysCloseLinkId: this.opt.sUniqueId + '_closeMoreSmileys'
	}));
	this.oSmileyPopupWindow.document.close();

	// Initialize the smileys that are in the popup window.
	this.initSmileys('popup', this.oSmileyPopupWindow.document);

	// Add a function to the close window button.
	var aCloseLink = this.oSmileyPopupWindow.document.getElementById(this.opt.sUniqueId + '_closeMoreSmileys');
	aCloseLink.instanceRef = this;
	aCloseLink.onclick = function() {
		this.instanceRef.oSmileyPopupWindow.close();
		return false;
	};
};

/*
	The BBC button box.
	Press 1 for Doctor Who,
	and 2 for Red Dwarf.
*/

function smc_BBCButtonBox(oOptions)
{
	this.opt = oOptions;

	var sBbcContent = '';
	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		var sRowContent = '';
		var bPreviousWasDivider = false;
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurButton = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			switch (oCurButton.sType)
			{
				case 'button':
					if (oCurButton.bEnabled)
					{
						sRowContent += this.opt.sButtonTemplate.easyReplace({
							buttonId: this.opt.sUniqueId.php_htmlspecialchars() + '_button_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString(),
							buttonSrc: (oCurButton.sImage ? oCurButton.sImage : this.opt.sSprite).php_htmlspecialchars(),
							posX: oCurButton.sPos ? oCurButton.sPos[0] : 0,
							posY: oCurButton.sPos ? oCurButton.sPos[1] + 2 : 2,
							buttonDescription: oCurButton.sDescription.php_htmlspecialchars()
						});

						bPreviousWasDivider = false;
					}
				break;

				case 'divider':
					if (!bPreviousWasDivider)
						sRowContent += this.opt.sDividerTemplate;

					bPreviousWasDivider = true;
				break;

				case 'select':
					var sOptions = '';

					// Fighting javascript's idea of order in a for loop... :P
					if ('' in oCurButton.oOptions)
						sOptions = '<option value="">' + oCurButton.oOptions[''].php_htmlspecialchars() + '</option>';
					for (var sSelectValue in oCurButton.oOptions)
						// we've been through this before
						if (sSelectValue != '')
							sOptions += '<option value="' + sSelectValue.php_htmlspecialchars() + '">' + oCurButton.oOptions[sSelectValue].php_htmlspecialchars() + '</option>';

					sRowContent += this.opt.sSelectTemplate.easyReplace({
						selectName: oCurButton.sName,
						selectId: this.opt.sUniqueId.php_htmlspecialchars() + '_select_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString(),
						selectOptions: sOptions
					});

					bPreviousWasDivider = false;
				break;
			}
		}
		sBbcContent += this.opt.sButtonRowTemplate.easyReplace({
			buttonRow: sRowContent
		});
	}

	$('#' + this.opt.sContainerDiv).html(sBbcContent);

	for (iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurControl = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			switch (oCurControl.sType)
			{
				case 'button':
					if (!oCurControl.bEnabled)
						break;

					oCurControl.oImg = document.getElementById(this.opt.sUniqueId.php_htmlspecialchars() + '_button_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString());
					oCurControl.oImg.style.cursor = 'pointer';
					if ('sButtonBackgroundPos' in this.opt)
					{
						oCurControl.oImg.style.background = 'url(' + this.opt.sSprite + ') no-repeat';
						oCurControl.oImg.style.backgroundPosition = '-' + this.opt.sButtonBackgroundPos[0] + 'px -' + this.opt.sButtonBackgroundPos[1] + 'px';
					}

					oCurControl.oImg.instanceRef = this;
					oCurControl.oImg.onmouseover = function () {
						this.instanceRef.handleButtonMouseOver(this);
					};
					oCurControl.oImg.onmouseout = function () {
						this.instanceRef.handleButtonMouseOut(this);
					};
					oCurControl.oImg.onclick = function () {
						this.instanceRef.handleButtonClick(this);
					};

					oCurControl.oImg.bIsActive = false;
					oCurControl.oImg.bHover = false;
				break;

				case 'select':
					oCurControl.oSelect = document.getElementById(this.opt.sUniqueId.php_htmlspecialchars() + '_select_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString());

					oCurControl.oSelect.instanceRef = this;
					oCurControl.oSelect.onchange = oCurControl.onchange = function() {
						this.instanceRef.handleSelectChange(this);
					};
				break;
			}
		}
	}
}

smc_BBCButtonBox.prototype.handleButtonMouseOver = function(oButtonImg)
{
	oButtonImg.bHover = true;
	this.updateButtonStatus(oButtonImg);
};

smc_BBCButtonBox.prototype.handleButtonMouseOut = function(oButtonImg)
{
	oButtonImg.bHover = false;
	this.updateButtonStatus(oButtonImg);
};

smc_BBCButtonBox.prototype.updateButtonStatus = function(oButtonImg)
{
	var sNewPos = 0;
	if (oButtonImg.bHover && oButtonImg.bIsActive && 'sActiveButtonBackgroundPosHover' in this.opt)
		sNewPos = this.opt.sActiveButtonBackgroundPosHover;
	else if (!oButtonImg.bHover && oButtonImg.bIsActive && 'sActiveButtonBackgroundPos' in this.opt)
		sNewPos = this.opt.sActiveButtonBackgroundPos;
	else if (oButtonImg.bHover && 'sButtonBackgroundPosHover' in this.opt)
		sNewPos = this.opt.sButtonBackgroundPosHover;
	else if ('sButtonBackgroundPos' in this.opt)
		sNewPos = this.opt.sButtonBackgroundPos;

	if (oButtonImg.style.backgroundPosition != sNewPos && sNewPos)
		oButtonImg.style.backgroundPosition = '-' + sNewPos[0] + 'px -' + sNewPos[1] + 'px';
};

smc_BBCButtonBox.prototype.handleButtonClick = function(oButtonImg)
{
	// Dissect the id attribute...
	var aMatches = oButtonImg.id.match(/(\d+)_(\d+)$/);
	if (aMatches.length != 3)
		return false;

	// ...so that we can point to the exact button.
	var iButtonRowIndex = aMatches[1];
	var iButtonIndex = aMatches[2];
	var oProperties = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
	oProperties.bIsActive = oButtonImg.bIsActive;

	if ('sButtonClickHandler' in this.opt)
		this.opt.sButtonClickHandler(oProperties);

	return false;
};

smc_BBCButtonBox.prototype.handleSelectChange = function(oSelectControl)
{
	// Dissect the id attribute...
	var aMatches = oSelectControl.id.match(/(\d+)_(\d+)$/);
	if (aMatches.length != 3)
		return false;

	// ...so that we can point to the exact button.
	var iButtonRowIndex = aMatches[1];
	var iButtonIndex = aMatches[2];
	var oProperties = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];

	if ('sSelectChangeHandler' in this.opt)
		this.opt.sSelectChangeHandler(oProperties);

	return true;
};

smc_BBCButtonBox.prototype.setActive = function(aButtons)
{
	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurControl = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			if (oCurControl.sType == 'button' && oCurControl.bEnabled)
			{
				oCurControl.oImg.bIsActive = in_array(oCurControl.sCode, aButtons);
				this.updateButtonStatus(oCurControl.oImg);
			}
		}
	}
};

smc_BBCButtonBox.prototype.emulateClick = function(sCode)
{
	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurControl = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			if (oCurControl.sType == 'button' && oCurControl.sCode == sCode)
			{
				this.opt.sButtonClickHandler(oCurControl);
				return true;
			}
		}
	}
	return false;
};

smc_BBCButtonBox.prototype.setSelect = function(sSelectName, sValue)
{
	if (!('sButtonClickHandler' in this.opt))
		return;

	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurControl = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			if (oCurControl.sType == 'select' && oCurControl.sName == sSelectName)
				oCurControl.oSelect.value = sValue;
		}
	}
};

/*
	Attachment selector, originally based on http://the-stickman.com/web-development/javascript/upload-multiple-files-with-a-single-file-element/
	The original code is MIT licensed, as discussed on http://the-stickman.com/using-code-from-this-site-ie-licence/
	This is quite heavily rewritten, though, to suit our purposes.
*/

function wedgeAttachSelect(oOptions)
{
	this.opts = oOptions;
	this.count = 0;
	this.attachId = 0;
	this.max = (oOptions.max) ? oOptions.max : -1;

	// Yay for scope issues.
	this.checkExtension = function(filename)
	{
		if (!this.opts.attachment_ext)
			return true; // we're not checking

		var dot = filename.lastIndexOf(".");
		if (!filename || filename.length == 0 || dot == -1)
		{
			this.opts.message_ext_error_final = this.opts.message_ext_error.replace(' ({ext})', '');
			return false; // Pfft, didn't specify anything, or no extension
		}

		var extension = (filename.substr(dot + 1, filename.length)).toLowerCase();
		if (!in_array(extension, this.opts.attachment_ext))
		{
			this.opts.message_ext_error_final = this.opts.message_ext_error.replace('{ext}', extension);
			return false;
		}

		return true;
	};

	this.checkActive = function()
	{
		var session_attach = 0;
		$('input[type="checkbox"]').each(function() {
			if (this.name == 'attach_del[]' && this.checked == true)
				session_attach++;
		});

		this.current_element.disabled = !(this.max == -1 || (this.max >= (session_attach + this.count)));
	};

	this.selectorHandler = function(event)
	{
		var element = event.target;

		if ($(element).val() == '')
			return false;

		// We've got one!! Check it, bag it.
		if (that.checkExtension(element.value))
		{
			// Hide this input.
			$(element).css({ position: 'absolute', left: '-1000px' });

			// Add a new file selector.
			that.createFileSelector();

			// Add the display entry and remove button.
			var new_row = document.createElement('div');
			new_row.element = element;
			new_row.innerHTML = element.value + '&nbsp; &nbsp;';

			$('<input type="button" class="delete" style="margin-top: 4px; background-position: 4px -31px" value="' + that.opts.message_txt_delete + '" />').click(function() {
				// Remove element from form
				this.parentNode.element.parentNode.removeChild(this.parentNode.element);
				this.parentNode.parentNode.removeChild(this.parentNode);
				this.parentNode.element.multi_selector.count--;
				that.checkActive();
				return false;
			}).appendTo(new_row);

			$('#' + that.opts.file_container).append(new_row);

			that.count++;
			that.current_element = element;
			that.checkActive();
		}
		else
		// Uh oh.
		{
			alert(this.opts.message_ext_error_final);
			that.createFileSelector();
			$(element).remove();
		}
	};

	this.prepareFileSelector = function(element)
	{
		if (element.tagName != 'INPUT' || element.type != 'file')
			return;

		$(element).attr({
			id: 'file_' + this.attachId++,
			name: 'attachment[]'
		});
		element.multi_selector = this;
		$(element).bind('change', function (event) { that.selectorHandler(event); });
	};

	this.createFileSelector = function ()
	{
		var new_element = $('<input type="file">').prependTo('#' + this.opts.file_container);
		this.current_element = new_element[0];
		this.prepareFileSelector(new_element[0]);
	};

	// And finally, we begin.
	var that = this;
	that.prepareFileSelector($('#' + this.opts.file_item)[0]);
};

/*
	Handles auto-saving of posts.
*/

function wedge_autoDraft(oOptions)
{
	this.opt = oOptions;
	this.opt.needsUpdate = false;

	if (this.opt.iFreq > 0)
		setInterval(this.opt.sSelf + '.draftSend();', this.opt.iFreq);
}

wedge_autoDraft.prototype.needsUpdate = function(update)
{
	this.opt.needsUpdate = update;
};

wedge_autoDraft.prototype.draftSend = function()
{
	if (!this.opt.needsUpdate)
		return;

	var
		sUrl = $('#' + this.opt.sForm).attr('action'),
		draftInfo = {
			draft: 'draft',
			draft_id: $('#draft_id').val(),
			subject: $('#' + this.opt.sForm + ' input[name="subject"]').val(),
			message: $('#' + this.opt.sEditor).val(),
			message_mode: $('#' + this.opt.sEditor + '_mode').val()
		},
		localVars = {
			removeString: this.opt.sRemove,
			lastSavedDiv: this.opt.sLastNote,
			sessvar: this.opt.sSessionVar,
			sessid: this.opt.sSessionId,
			object: this
		};

	// We're doing the whole WYSIWYG thing, but just for fun, we need to extract the object's frame
	if (draftInfo.message_mode == 1)
		draftInfo.message = $('#html_' + this.opt.sEditor).html();

	// This isn't nice either, but nicer than the above, sorry.
	draftInfo[this.opt.sSessionVar] = this.opt.sSessionId;

	// Depending on what we're doing, there might be other things we need to save, like topic details or PM recipients.
	if (this.opt.sType == 'auto_post')
	{
		draftInfo.topic = $('#' + this.opt.sForm + ' input[name="topic"]').val();
		draftInfo.icon = $('#' + this.opt.sForm + ' input[name="icon"]').val();
	}
	else if (this.opt.sType == 'auto_pm')
	{
		// Since we're here, we only need to bother with the JS, since the auto suggest will be available and will have already sorted out user ids.
		// This is not nice, though.
		var recipients = [];
		$('#' + this.opt.sForm + ' input[name="recipient_to\\[\\]"]').each(function() {
			recipients.push($(this).val());
		});
		if (recipients.length > 0)
			draftInfo['recipient_to[]'] = recipients;

		recipients = [];
		$('#' + this.opt.sForm + ' input[name="recipient_bcc\\[\\]"]').each(function() {
			recipients.push($(this).val());
		});
		if (recipients.length > 0)
			draftInfo['recipient_bcc[]'] = recipients;
	}

	$.post(sUrl + ';xml', draftInfo, function (data) {
		$('#remove_draft').unbind('click'); // Just in case bad stuff happens.

		var
			obj = $('#lastsave', data),
			draft_id = obj.attr('draft'),
			url = obj.attr('url').replace(/DraftId/, draft_id).replace(/SessVar/, localVars.sessvar).replace(/SessId/, localVars.sessid);

		$('#draft_id').val(draft_id);
		$('#' + localVars.lastSavedDiv).html(obj.text() + ' &nbsp; <a href="#" id="remove_draft">' + localVars.removeString + '</a>');
		$('#remove_draft').click(function () {
			$.get(url, function () {
				$('#' + localVars.lastSavedDiv).empty();
				$('#draft_id').val('0');
			});
			return false;
		});
		localVars.object.needsUpdate(false);
	});
	return false;
};
