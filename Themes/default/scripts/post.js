/*!
 * Wedge
 *
 * Helper functions for manipulating text and sending posts
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

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

// Split a quote (or any unclosed tag) if we press Enter inside it.
function splitQuote(e)
{
	// Did we just press Shift+Enter?
	if (e.which != 13 || !e.shiftKey)
		return true;

	// Where are we, already?
	if ('selectionStart' in this)
		var selectionStart = this.selectionStart;
	else
	{
		var selectionStart, range = document.selection.createRange(), dul = range.duplicate();
		dul.moveToElementText(this);
		dul.setEndPoint('EndToEnd', range);
		selectionStart = dul.text.length - range.text.length;
	}

	var
		selection = this.value.substr(0, selectionStart), lcs = selection.toLowerCase(), nextBreak, has_slash,
		lcsl = lcs.length, pos = 0, tag, bbcode, taglist = [], baretags = [], baretag, extag, log_tags = true,
		protect_tags = this.instanceRef.opt.aProtectTags, closed_tags = this.instanceRef.opt.aClosedTags;

	// Build a list of opened tags...
	while (true)
	{
		pos = lcs.indexOf('[', pos) + 1;
		if (!pos)
			break;
		tag = selection.substring(pos, lcs.indexOf(']', pos + 1));
		has_slash = tag[0] == '/';
		bbcode = tag.substr(+has_slash);
		baretag = ((nextBreak = /[\s=]/.exec(bbcode)) ? bbcode.substr(0, bbcode.indexOf(nextBreak)) : bbcode).toLowerCase();

		// Is it a closer tag?
		if (has_slash)
		{
			// Maybe it's a loose tag. Ignore it.
			if (!taglist.length)
				break;

			// Or maybe we're looking for a protected tag's closer. If it isn't it, skip it.
			if (!log_tags && baretag != baretags[baretags.length - 1])
				continue;

			// Otherwise, empty the stack until we find the equivalent opener. Normally, immediately.
			do
			{
				taglist.pop();
				extag = baretags.pop();
				log_tags |= in_array(extag, protect_tags);
			}
			while (extag && baretag != extag);
		}
		// Then it's an opener tag. If we're not within a protected tag loop,
		// and it's not a self-closed tag, add it to the tag stack.
		else if (log_tags && !in_array(baretag, closed_tags))
		{
			taglist.push(bbcode);
			baretags.push(baretag);

			// If we just met a protected opener, like [code], we'll ignore all further tags until we find a closer for it.
			log_tags &= !in_array(baretag, protect_tags);
		}
	}

	if (baretags.length)
		surroundText('[/' + baretags.reverse().join('][/') + ']\n', '\n\n[' + taglist.join('][') + ']', this);

	return true;
};

String.prototype.easyReplace = function (oReplacements)
{
	var sResult = this, sSearch;
	for (sSearch in oReplacements)
		sResult = sResult.replace(new RegExp('%' + sSearch + '%', 'g'), oReplacements[sSearch]);

	return sResult;
};


/*
	A smiley is worth
	a thousands words.
*/

function weSmileyBox(oOptions)
{
	var that = this;
	that.opt = oOptions;
	that.oSmileyRowsContent = {};

	// Get the HTML content of the smileys visible on the post screen.
	that.getSmileyRowsContent('postform');

	// Inject the HTML.
	$('#' + oOptions.sContainer).html(oOptions.sSmileyBoxTemplate.easyReplace({
		smileyRows: that.oSmileyRowsContent.postform,
		moreSmileys: oOptions.oSmileyLocations.popup.length == 0 ? '' : oOptions.sMoreSmileysTemplate.easyReplace({
			moreSmileysId: oOptions.sContainer + '_addMoreSmileys'
		})
	}));

	// Initialize the smileys.
	that.initSmileys('postform');

	// Initialize the [more] button.
	if (oOptions.oSmileyLocations.popup.length)
		$('#' + oOptions.sContainer + '_addMoreSmileys').click(function () {
			$(this).hide();

			// Get the popup smiley HTML, add the new smileys to the list and activate them.
			that.getSmileyRowsContent('popup');
			$('#' + oOptions.sContainer + ' .more').hide().html(that.oSmileyRowsContent.popup).slideDown();
			that.initSmileys('popup');

			return false;
		});
}

// Loop through the smileys to setup the HTML.
weSmileyBox.prototype.getSmileyRowsContent = function (sLocation)
{
	// If it's already defined, don't bother.
	if (sLocation in this.oSmileyRowsContent)
		return;

	this.oSmileyRowsContent[sLocation] = '';
	var aLocation = this.opt.oSmileyLocations[sLocation], iSmileyRowIndex, iSmileyRowCount = aLocation.length;

	for (iSmileyRowIndex = 0; iSmileyRowIndex < iSmileyRowCount; iSmileyRowIndex++)
	{
		var sSmileyRowContent = '', iSmileyIndex, aSmileyRow = aLocation[iSmileyRowIndex], iSmileyCount = aSmileyRow.length;
		for (iSmileyIndex = 0; iSmileyIndex < iSmileyCount; iSmileyIndex++)
			sSmileyRowContent += this.opt.sSmileyTemplate.easyReplace({
				smileySource: aSmileyRow[iSmileyIndex][1].php_htmlspecialchars(),
				smileyDesc: aSmileyRow[iSmileyIndex][2].php_htmlspecialchars(),
				smileyCode: aSmileyRow[iSmileyIndex][0].php_htmlspecialchars(),
				smileyId: this.opt.sContainer + '_' + sLocation + '_' + iSmileyRowIndex.toString() + '_' + iSmileyIndex.toString()
			});

		this.oSmileyRowsContent[sLocation] += this.opt.sSmileyRowTemplate.easyReplace({
			smileyRow: sSmileyRowContent
		});
	}
};

weSmileyBox.prototype.initSmileys = function (sLocation)
{
	var that = this, iSmileyRowIndex = 0, iSmileyRowCount = this.opt.oSmileyLocations[sLocation].length;
	for (; iSmileyRowIndex < iSmileyRowCount; iSmileyRowIndex++)
		for (var iSmileyIndex = 0, iSmileyCount = this.opt.oSmileyLocations[sLocation][iSmileyRowIndex].length; iSmileyIndex < iSmileyCount; iSmileyIndex++)
			$('#' + that.opt.sContainer + '_' + sLocation + '_' + iSmileyRowIndex.toString() + '_' + iSmileyIndex.toString())
				.css('cursor', 'pointer')
				.click(function () {
					// Dissect the id to determine its exact smiley properties.
					var aMatches = this.id.match(/([^_]+)_(\d+)_(\d+)$/);
					if (aMatches.length == 4 && 'sClickHandler' in that.opt)
						that.opt.sClickHandler(that.opt.oSmileyLocations[aMatches[1]][aMatches[2]][aMatches[3]]);

					return false;
				});
};

/*
	The BBC button box.
	Press 1 for Doctor Who,
	and 2 for Red Dwarf.
*/

function weButtonBox(oOptions)
{
	this.opt = oOptions;

	var sBbcContent = '', iButtonRowIndex = 0, iRowCount = oOptions.aButtonRows.length;
	for (; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		var sRowContent = '', bPreviousWasDivider = false, iButtonIndex = 0, iButtonCount = oOptions.aButtonRows[iButtonRowIndex].length;
		for (; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurButton = oOptions.aButtonRows[iButtonRowIndex][iButtonIndex], is_sprite = $.isArray(oCurButton[2]);
			switch (oCurButton[0])
			{
				case 'button': // 0 = sType, 1 = bEnabled, 2 = sImage or sPos, 3 = sCode, 4 = sBefore, 5 = sAfter, 6 = sDescription
					if (oCurButton[1])
					{
						sRowContent += oOptions.sButtonTemplate.easyReplace({
							buttonId: oOptions.sContainer.php_htmlspecialchars() + '_button_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString(),
							buttonSrc: (is_sprite ? oOptions.sSprite : oCurButton[2]).php_htmlspecialchars(),
							posX: is_sprite ? oCurButton[2][0] : 0,
							posY: is_sprite ? oCurButton[2][1] + 2 : 2,
							buttonDescription: oCurButton[6].php_htmlspecialchars()
						});

						bPreviousWasDivider = false;
					}
				break;

				case 'select': // 0 = sType, 1 = sName, 2 = oOptions
					var sOptions = '', sSelectValue, sProt, optname = '%opt%';

					// Fighting JavaScript's idea of order in a for loop... :P
					if ('' in oCurButton[2])
						sOptions = '<option value="">' + oCurButton[2][''].php_htmlspecialchars() + '</option>';
					for (sSelectValue in oCurButton[2])
					{
						// we've been through this before
						if (oCurButton[1] == 'sel_face')
							optname = '&lt;span style="font-family: %opt%"&gt;%opt%&lt;/span&gt;';
						else if (oCurButton[1] == 'sel_size')
							optname = '&lt;span style="font-size: %opt%"&gt;%opt%&lt;/span&gt;';
						else if (oCurButton[1] == 'sel_color')
							optname = '&lt;span style="color: %val%"&gt;&diams;&lt;/span&gt; %opt%';
						sProt = sSelectValue.php_htmlspecialchars();
						if (sSelectValue != '')
							sOptions += '<option value="' + sProt + '">' + optname.replace(/%val%/g, sProt).replace(/%opt%/g, oCurButton[2][sSelectValue].php_htmlspecialchars()) + '</option>';
					}

					sRowContent += oOptions.sSelectTemplate.easyReplace({
						selectName: oCurButton[1],
						selectId: oOptions.sContainer.php_htmlspecialchars() + '_select_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString(),
						selectOptions: sOptions
					});

					bPreviousWasDivider = false;
				break;

				default:
					if (!bPreviousWasDivider)
						sRowContent += oOptions.sDividerTemplate;

					bPreviousWasDivider = true;
				break;
			}
		}
		sBbcContent += oOptions.sButtonRowTemplate.easyReplace({
			buttonRow: sRowContent
		});
	}

	$('#' + oOptions.sContainer).html(sBbcContent).find('select').sb();

	for (iButtonRowIndex = 0, iRowCount = oOptions.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (iButtonIndex = 0, iButtonCount = oOptions.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			oCurButton = oOptions.aButtonRows[iButtonRowIndex][iButtonIndex];
			switch (oCurButton[0])
			{
				case 'button':
					if (!oCurButton[1])
						break;

					oCurButton.oImg = document.getElementById(oOptions.sContainer.php_htmlspecialchars() + '_button_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString());
					oCurButton.oImg.style.cursor = 'pointer';
					if ('sButtonBackgroundPos' in oOptions)
					{
						oCurButton.oImg.style.background = 'url(' + oOptions.sSprite + ') no-repeat';
						oCurButton.oImg.style.backgroundPosition = '-' + oOptions.sButtonBackgroundPos[0] + 'px -' + oOptions.sButtonBackgroundPos[1] + 'px';
					}

					oCurButton.oImg.instanceRef = this;
					oCurButton.oImg.onmouseover = function () { this.instanceRef.handleButtonMouseOver(this); };
					oCurButton.oImg.onmouseout = function () { this.instanceRef.handleButtonMouseOut(this); };
					oCurButton.oImg.onclick = function () { this.instanceRef.handleButtonClick(this); };
					oCurButton.oImg.bIsActive = false;
					oCurButton.oImg.bHover = false;
				break;

				case 'select':
					oCurButton.oSelect = document.getElementById(oOptions.sContainer.php_htmlspecialchars() + '_select_' + iButtonRowIndex.toString() + '_' + iButtonIndex.toString());

					oCurButton.oSelect.instanceRef = this;
					oCurButton.oSelect.onchange = oCurButton.onchange = function () {
						this.instanceRef.handleSelectChange(this);
					};
				break;
			}
		}
	}
}

weButtonBox.prototype.handleButtonMouseOver = function (oButtonImg)
{
	oButtonImg.bHover = true;
	this.updateButtonStatus(oButtonImg);
};

weButtonBox.prototype.handleButtonMouseOut = function (oButtonImg)
{
	oButtonImg.bHover = false;
	this.updateButtonStatus(oButtonImg);
};

weButtonBox.prototype.updateButtonStatus = function (oButtonImg)
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

weButtonBox.prototype.handleButtonClick = function (oButtonImg)
{
	// Dissect the id attribute...
	var aMatches = oButtonImg.id.match(/(\d+)_(\d+)$/);
	if (aMatches.length != 3)
		return false;

	// ...so that we can point to the exact button.
	var
		iButtonRowIndex = aMatches[1],
		iButtonIndex = aMatches[2],
		oProperties = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
	oProperties.bIsActive = oButtonImg.bIsActive;

	if ('sButtonClickHandler' in this.opt)
		this.opt.sButtonClickHandler(oProperties);

	return false;
};

weButtonBox.prototype.handleSelectChange = function (oSelectControl)
{
	// Dissect the id attribute...
	var aMatches = oSelectControl.id.match(/(\d+)_(\d+)$/);
	if (aMatches.length != 3)
		return false;

	// ...so that we can point to the exact button.
	var
		iButtonRowIndex = aMatches[1],
		iButtonIndex = aMatches[2],
		oProperties = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];

	if ('sSelectChangeHandler' in this.opt)
		this.opt.sSelectChangeHandler(oProperties);

	return true;
};

weButtonBox.prototype.setActive = function (aButtons)
{
	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurButton = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			if (oCurButton[0] == 'button' && oCurButton[1])
			{
				oCurButton.oImg.bIsActive = in_array(oCurButton[3], aButtons);
				this.updateButtonStatus(oCurButton.oImg);
			}
		}
	}
};

weButtonBox.prototype.emulateClick = function (sCode)
{
	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurButton = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			if (oCurButton[0] == 'button' && oCurButton[3] == sCode)
			{
				this.opt.sButtonClickHandler(oCurButton);
				return true;
			}
		}
	}
	return false;
};

weButtonBox.prototype.setSelect = function (sSelectName, sValue)
{
	if (!('sButtonClickHandler' in this.opt))
		return;

	for (var iButtonRowIndex = 0, iRowCount = this.opt.aButtonRows.length; iButtonRowIndex < iRowCount; iButtonRowIndex++)
	{
		for (var iButtonIndex = 0, iButtonCount = this.opt.aButtonRows[iButtonRowIndex].length; iButtonIndex < iButtonCount; iButtonIndex++)
		{
			var oCurButton = this.opt.aButtonRows[iButtonRowIndex][iButtonIndex];
			if (oCurButton[0] == 'select' && oCurButton[1] == sSelectName)
				oCurButton.oSelect.value = sValue;
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
	this.count = 0;
	this.attachId = 0;
	this.max = oOptions.max ? oOptions.max : -1;

	// Yay for scope issues.
	this.checkExtension = function (filename)
	{
		if (!oOptions.attachment_ext)
			return true; // We're not checking

		var dot = filename.lastIndexOf(".");
		if (!filename || filename.length == 0 || dot == -1)
		{
			oOptions.message_ext_error_final = oOptions.message_ext_error.replace(' ({ext})', '');
			return false; // Pfft, didn't specify anything, or no extension
		}

		var extension = (filename.substr(dot + 1, filename.length)).toLowerCase();
		if (!in_array(extension, oOptions.attachment_ext))
		{
			oOptions.message_ext_error_final = oOptions.message_ext_error.replace('{ext}', extension);
			return false;
		}

		return true;
	};

	this.checkActive = function ()
	{
		var session_attach = 0;
		$('input[type=checkbox]').each(function () {
			if (this.name == 'attach_del[]' && this.checked == true)
				session_attach++;
		});

		this.current_element.disabled = !(this.max == -1 || (this.max >= (session_attach + this.count)));
	};

	this.selectorHandler = function (event)
	{
		var element = event.target;

		if ($(element).val() === '')
			return false;

		// We've got one!! Check it, bag it.
		if (that.checkExtension(element.value))
		{
			// Hide this input.
			$(element).css({ position: 'absolute', left: -1000 });

			// Add a new file selector.
			that.createFileSelector();

			// Add the display entry and remove button.
			var new_row = document.createElement('div');
			new_row.element = element;
			new_row.innerHTML = '&nbsp; &nbsp;' + element.value;

			$('<input type="button" class="delete" style="margin-top: 4px" value="' + oOptions.message_txt_delete + '" />').click(function () {
				// Remove element from form
				this.parentNode.element.parentNode.removeChild(this.parentNode.element);
				this.parentNode.parentNode.removeChild(this.parentNode);
				this.parentNode.element.multi_selector.count--;
				that.checkActive();
				return false;
			}).prependTo(new_row);

			$('#' + oOptions.file_container).append(new_row);

			that.count++;
			that.current_element = element;
			that.checkActive();
		}
		else
		// Uh oh.
		{
			alert(oOptions.message_ext_error_final);
			that.createFileSelector();
			$(element).remove();
		}
	};

	this.prepareFileSelector = function (element)
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
		var new_element = $('<input type="file">').prependTo('#' + oOptions.file_container);
		this.current_element = new_element[0];
		this.prepareFileSelector(new_element[0]);
	};

	// And finally, we begin.
	var that = this;
	that.prepareFileSelector($('#' + oOptions.file_item)[0]);
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

wedge_autoDraft.prototype.needsUpdate = function (update)
{
	this.opt.needsUpdate = update;
};

wedge_autoDraft.prototype.draftSend = function ()
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
		object = this,
		lastSavedDiv = object.opt.sLastNote;

	// We're doing the whole WYSIWYG thing, but just for fun, we need to extract the object's frame
	if (draftInfo.message_mode == 1)
		draftInfo.message = $('#html_' + this.opt.sEditor).html();

	// This isn't nice either, but nicer than the above, sorry.
	draftInfo[we_sessvar] = we_sessid;

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
		$('#' + this.opt.sForm + ' input[name="recipient_to\\[\\]"]').each(function () { recipients.push($(this).val()); });
		if (recipients.length)
			draftInfo['recipient_to[]'] = recipients;

		recipients = [];
		$('#' + this.opt.sForm + ' input[name="recipient_bcc\\[\\]"]').each(function () { recipients.push($(this).val()); });
		if (recipients.length)
			draftInfo['recipient_bcc[]'] = recipients;
	}

	$.post(sUrl + ';xml', draftInfo, function (data)
	{
		$('#remove_draft').unbind('click'); // Just in case bad stuff happens.

		var
			obj = $('#lastsave', data),
			draft_id = obj.attr('draft'),
			url = obj.attr('url').replace(/DraftId/, draft_id).replace(/SessVar/, we_sessvar).replace(/SessId/, we_sessid);

		$('#draft_id').val(draft_id);
		$('#' + lastSavedDiv).html(obj.text() + ' &nbsp; ').append($('<input type="button" id="remove_draft" class="delete">').val(object.opt.sRemove));
		$('#remove_draft').click(function () {
			$.get(url, function () {
				$('#' + lastSavedDiv).empty();
				$('#draft_id').val('0');
			});
			return false;
		});
		object.needsUpdate(false);
	});
	return false;
};
