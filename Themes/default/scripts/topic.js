
var
	cur_topic_id, cur_msg_id, cur_subject_div,
	buff_subject, in_edit_mode = 0,
	current_user_menu = null,
	hide_prefixes = [];

function is_editing()
{
	return in_edit_mode == 1;
}

// For templating, shown when an inline edit is made.
function modify_topic_show_edit(subject)
{
	// Just template the subject.
	cur_subject_div.html('<input type="text" name="subject" value="' + subject + '" size="60" style="width: 95%" maxlength="80" onkeypress="modify_topic_keypress(event);" class="input_text" /><input type="hidden" name="topic" value="' + cur_topic_id + '" /><input type="hidden" name="msg" value="' + cur_msg_id.substr(4) + '" />');
}

// And the reverse for hiding it.
function modify_topic_hide_edit(subject)
{
	// Re-template the subject!
	cur_subject_div.html('<a href="' + smf_prepareScriptUrl(smf_scripturl) + 'topic=' + cur_topic_id + '.0">' + subject + '</a>');
}

function modify_topic(topic_id, first_msg_id)
{
	if (!can_ajax)
		return;

	if (in_edit_mode == 1)
	{
		if (cur_topic_id == topic_id)
			return;
		else
			modify_topic_cancel();
	}

	mouse_on_div = 1;
	in_edit_mode = 1;
	cur_topic_id = topic_id;

	if (typeof window.ajax_indicator == "function")
		ajax_indicator(true);
	getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + "action=quotefast;quote=" + first_msg_id + ";modify;xml", onDocReceived_modify_topic);
}

function onDocReceived_modify_topic(XMLDoc)
{
	cur_msg_id = $('message', XMLDoc).attr('id').substr(4);

	cur_subject_div = $('#msg_' + cur_msg_id);
	buff_subject = cur_subject_div.html();

	// Here we hide any other things they want hiding on edit.
	set_hidden_topic_areas('none');

	modify_topic_show_edit($('subject', XMLDoc).text());
	if (typeof window.ajax_indicator == "function")
		ajax_indicator(false);
}

function modify_topic_cancel()
{
	cur_subject_div.html(buff_subject);
	set_hidden_topic_areas('');

	in_edit_mode = 0;
	return false;
}

function modify_topic_save(cur_session_id, cur_session_var)
{
	if (!in_edit_mode)
		return true;

	var x = [], qm = document.forms.quickModForm;
	x[x.length] = 'subject=' + qm.subject.value.replace(/&#/g, "&#38;#").php_to8bit().php_urlencode();
	x[x.length] = 'topic=' + qm.elements.topic.value;
	x[x.length] = 'msg=' + qm.elements.msg.value;

	if (typeof window.ajax_indicator == "function")
		ajax_indicator(true);
	sendXMLDocument(smf_prepareScriptUrl(smf_scripturl) + "action=jsmodify;topic=" + qm.elements.topic.value + ";" + cur_session_var + "=" + cur_session_id + ";xml", x.join("&"), modify_topic_done);

	return false;
}

function modify_topic_done(XMLDoc)
{
	if (!XMLDoc)
	{
		modify_topic_cancel();
		return true;
	}

	var message = $('smf message', XMLDoc);
	var subject = $('subject', message);
	var error = $('error', message);

	if (typeof window.ajax_indicator == 'function')
		ajax_indicator(false);

	if (!subject.length || error.length)
		return false;

	modify_topic_hide_edit(subject.text());
	set_hidden_topic_areas('');
	in_edit_mode = 0;

	return false;
}

// Simply restore any hidden bits during topic editing.
function set_hidden_topic_areas(set_style)
{
	for (var i = 0; i < hide_prefixes.length; i++)
		$('#' + hide_prefixes[i] + cur_msg_id).css('display', set_style);
}

// *** QuickReply object.
function QuickReply(oOptions)
{
	this.opt = oOptions;
	this.bCollapsed = this.opt.bDefaultCollapsed;
	$('#' + this.opt.sSwitchMode).slideDown(200);
}

// When a user presses quote, put it in the quick reply box (if expanded).
QuickReply.prototype.quote = function (iMessage)
{
	// iMessageId is taken from the owner ID -- quote_button_xxx
	var iMessageId = iMessage && iMessage.id ? iMessage.id.substr(13) : '';

	if (this.bCollapsed)
	{
		window.location.href = smf_prepareScriptUrl(this.opt.sScriptUrl) + 'action=post;quote=' + iMessageId + ';topic=' + this.opt.iTopicId + '.' + this.opt.iStart;
		return false;
	}
	else
	{
		ajax_indicator(true);
		getXMLDocument(smf_prepareScriptUrl(this.opt.sScriptUrl) + 'action=quotefast;quote=' + iMessageId + ';xml;mode=' + (oEditorHandle_message.bRichTextEnabled ? 1 : 0), this.onQuoteReceived);

		// Move the view to the quick reply box.
		window.location.hash = (is_ie ? '' : '#') + this.opt.sJumpAnchor;

		return false;
	}
};

// This is the callback function used after the XMLHttp request.
QuickReply.prototype.onQuoteReceived = function (oXMLDoc)
{
	oEditorHandle_message.insertText($('quote', oXMLDoc).text(), false, true);

	ajax_indicator(false);
};

// The function handling the swapping of the quick reply.
QuickReply.prototype.swap = function ()
{
	$('#' + this.opt.sImageId).attr('src', this.opt.sImagesUrl + "/" + (this.bCollapsed ? this.opt.sImageCollapsed : this.opt.sImageExpanded));
	var cont = $('#' + this.opt.sContainerId);
	this.bCollapsed ? cont.slideDown(200) : cont.slideUp(200);

	this.bCollapsed = !this.bCollapsed;
	return false;
};

// Switch from basic to more powerful editor
QuickReply.prototype.switchMode = function ()
{
	if (this.opt.sBbcDiv != '')
		$('#' + this.opt.sBbcDiv).slideDown(500);
	if (this.opt.sSmileyDiv != '')
		$('#' + this.opt.sSmileyDiv).slideDown(500);
	if (this.opt.sBbcDiv != '' || this.opt.sSmileyDiv != '')
		$('#' + this.opt.sSwitchMode).slideUp(500);
	if (this.bUsingWysiwyg)
		oEditorHandle_message.toggleView(true);
};

// *** QuickModify object.
function QuickModify(oOptions)
{
	this.opt = oOptions;
	this.bInEditMode = false;
	this.sCurMessageId = '';
	this.oCurMessageDiv = null;
	this.oCurSubjectDiv = null;
	this.sMessageBuffer = '';
	this.sSubjectBuffer = '';
}

// Function called when a user presses the edit button.
QuickModify.prototype.modifyMsg = function (iMessage)
{
	if (!can_ajax)
		return;

	// iMessageId is taken from the owner ID -- modify_button_xxx
	var iMessageId = iMessage && iMessage.id ? iMessage.id.substr(14) : '';

	// First cancel if there's another message still being edited.
	if (this.bInEditMode)
		this.modifyCancel();

	// At least NOW we're in edit mode
	this.bInEditMode = true;

	// Send out the XMLHttp request to get more info
	ajax_indicator(true);

	getXMLDocument.call(this, smf_prepareScriptUrl(this.opt.sScriptUrl) + 'action=quotefast;quote=' + iMessageId + ';modify;xml', this.onMessageReceived);
};

// The callback function used for the XMLHttp request retrieving the message.
QuickModify.prototype.onMessageReceived = function (XMLDoc)
{
	// Hide the 'loading...' sign.
	ajax_indicator(false);

	// Grab the message ID.
	this.sCurMessageId = $('message', XMLDoc).attr('id');

	// If this is not valid then simply give up.
	if (!document.getElementById(this.sCurMessageId))
		return this.modifyCancel();

	this.oCurMessageDiv = $('#' + this.sCurMessageId);
	this.sMessageBuffer = this.oCurMessageDiv.html();

	// We have to force the body to lose its dollar signs thanks to IE.
	// !!! Is it still a valid fix, BTW...?
	var sBodyText = $('message', XMLDoc).text().replace(/\$/g, '{&dollarfix;$}');

	// Actually create the content, with a bodge for disappearing dollar signs.
	this.oCurMessageDiv.html(this.opt.sTemplateBodyEdit.replace(/%msg_id%/g, this.sCurMessageId.substr(4)).replace(/%body%/, sBodyText).replace(/\{&dollarfix;\$\}/g, '$'));

	// Replace the subject part.
	this.oCurSubjectDiv = $('#subject_' + this.sCurMessageId.substr(4));
	this.sSubjectBuffer = this.oCurSubjectDiv.html();

	var sSubjectText = $('subject', XMLDoc).text().replace(/\$/g, '{&dollarfix;$}');
	this.oCurSubjectDiv.html(this.opt.sTemplateSubjectEdit.replace(/%subject%/, sSubjectText).replace(/\{&dollarfix;\$\}/g, '$'));

	return true;
};

// Function in case the user presses cancel (or other circumstances cause it).
QuickModify.prototype.modifyCancel = function ()
{
	// Roll back the HTML to its original state.
	if (this.oCurMessageDiv)
	{
		this.oCurMessageDiv.html(this.sMessageBuffer);
		this.oCurSubjectDiv.html(this.sSubjectBuffer);
	}

	// No longer in edit mode, that's right.
	this.bInEditMode = false;

	return false;
};

// The function called after a user wants to save his precious message.
QuickModify.prototype.modifySave = function (sSessionId, sSessionVar)
{
	// We cannot save if we weren't in edit mode.
	if (!this.bInEditMode)
		return true;

	var x = [], qm = document.forms.quickModForm;
	x[x.length] = 'subject=' + escape(qm.subject.value.replace(/&#/g, "&#38;#").php_to8bit()).replace(/\+/g, "%2B");
	x[x.length] = 'message=' + escape(qm.message.value.replace(/&#/g, "&#38;#").php_to8bit()).replace(/\+/g, "%2B");
	x[x.length] = 'topic=' + qm.elements.topic.value;
	x[x.length] = 'msg=' + qm.elements.msg.value;

	// Send in the XMLHttp request and let's hope for the best.
	ajax_indicator(true);
	sendXMLDocument.call(this, smf_prepareScriptUrl(this.opt.sScriptUrl) + "action=jsmodify;topic=" + this.opt.iTopicId + ";" + sSessionVar + "=" + sSessionId + ";xml", x.join("&"), this.onModifyDone);

	return false;
};

// Callback function of the XMLHttp request sending the modified message.
QuickModify.prototype.onModifyDone = function (XMLDoc)
{
	// We've finished the loading part.
	ajax_indicator(false);

	// If we didn't get a valid document, just cancel.
	var xm = $('smf', XMLDoc);
	if (!XMLDoc || !xm.length)
	{
		// Mozilla will nicely tell us what's wrong.
		if (XMLDoc && XMLDoc.childNodes.length > 0 && XMLDoc.firstChild.nodeName == 'parsererror')
			$('#error_box').html(XMLDoc.firstChild.textContent);
		else
			this.modifyCancel();
		return;
	}

	var
		message = $('message', xm),
		body = $('body', message),
		error = $('error', message);

	if (body.length)
	{
		// Show new body.
		this.sMessageBuffer = this.opt.sTemplateBodyNormal.replace(/%body%/, body.text().replace(/\$/g, '{&dollarfix;$}')).replace(/\{&dollarfix;\$\}/g,'$');
		this.oCurMessageDiv.html(this.sMessageBuffer);

		// Show new subject.
		var oSubject = $('subject', message), sSubjectText = oSubject.text().replace(/\$/g, '{&dollarfix;$}');
		this.sSubjectBuffer = this.opt.sTemplateSubjectNormal.replace(/%msg_id%/g, this.sCurMessageId.substr(4)).replace(/%subject%/, sSubjectText).replace(/\{&dollarfix;\$\}/g,'$');
		this.oCurSubjectDiv.html(this.sSubjectBuffer);

		// If this is the first message, also update the topic subject.
		if (oSubject.attr('is_first') == '1')
			$('#top_subject').html(this.opt.sTemplateTopSubject.replace(/%subject%/, sSubjectText).replace(/\{&dollarfix;\$\}/g, '$'));

		// Show this message as 'modified on x by y'.
		if (this.opt.bShowModify)
			$('#modified_' + this.sCurMessageId.substr(4)).html($('modified', message).text());
	}
	else if (error.length)
	{
		$('#error_box').html(error.text());
		var qm = document.forms.quickModForm;
		qm.message.style.border = error.attr('in_body') == '1' ? this.opt.sErrorBorderStyle : '';
		qm.subject.style.border = error.attr('in_subject') == '1' ? this.opt.sErrorBorderStyle : '';
	}
};

function InTopicModeration(oOptions)
{
	this.opt = oOptions;
	this.bButtonsShown = false;
	this.iNumSelected = 0;

	// Add checkboxes to all the messages.
	for (var i = 0, n = this.opt.aMessageIds.length; i < n; i++)
		$('#' + this.opt.sCheckboxContainerMask + this.opt.aMessageIds[i]).append(
			$('<input type="checkbox" class="input_check" name="msgs[]" value="' + this.opt.aMessageIds[i] + '"></input>')
			.data('that', this).click(function () { $(this).data('that').handleClick(this); })
		).show();
}

InTopicModeration.prototype.handleClick = function(oCheckbox)
{
	if (!this.bButtonsShown)
	{
		// Make sure it can go somewhere.
		if (this.opt.sButtonStripDisplay && document.getElementById(this.opt.sButtonStripDisplay))
			$('#' + this.opt.sButtonStripDisplay).show();

		// Add the 'remove selected items' button.
		if (this.opt.bCanRemove)
			wedge_addButton(this.opt.sButtonStrip, this.opt.bUseImageButton, {
				sId: this.opt.sSelf + '_remove_button',
				sText: this.opt.sRemoveButtonLabel,
				sImage: this.opt.sRemoveButtonImage,
				sCustom: ' onclick="return ' + this.opt.sSelf + '.handleSubmit(\'remove\')"'
			});

		// Add the 'restore selected items' button.
		if (this.opt.bCanRestore)
			wedge_addButton(this.opt.sButtonStrip, this.opt.bUseImageButton, {
				sId: this.opt.sSelf + '_restore_button',
				sText: this.opt.sRestoreButtonLabel,
				sImage: this.opt.sRestoreButtonImage,
				sCustom: ' onclick="return ' + this.opt.sSelf + '.handleSubmit(\'restore\')"'
			});

		// Adding these buttons once should be enough.
		this.bButtonsShown = true;
	}

	// Keep stats on how many items were selected.
	this.iNumSelected += oCheckbox.checked ? 1 : -1;

	// Show the number of messages selected in the button.
	if (this.opt.bCanRemove && !this.opt.bUseImageButton)
		var but1 = $('#' + this.opt.sSelf + '_remove_button')
			.html(this.opt.sRemoveButtonLabel + ' [' + this.iNumSelected + ']');

	if (this.opt.bCanRestore && !this.opt.bUseImageButton)
		var but2 = $('#' + this.opt.sSelf + '_restore_button')
			.html(this.opt.sRestoreButtonLabel + ' [' + this.iNumSelected + ']');

	if (but1 && this.iNumSelected < 1 && but1.is(':visible'))
		but1.fadeOut(300).hide();
	if (but1 && this.iNumSelected > 0 && but1.is(':hidden'))
		but1.fadeIn(300).show();
	if (but2 && this.iNumSelected < 1 && but2.is(':visible'))
		but2.fadeOut(300).hide();
	if (but2 && this.iNumSelected > 0 && but2.is(':hidden'))
		but2.fadeIn(300).show();

	// Try to restore the correct position.
	var aItems = $('#' + this.opt.sButtonStrip)[0].getElementsByTagName('li');
	if (this.iNumSelected < 1)
	{
		aItems[aItems.length - 3].className = aItems[aItems.length - 3].className.replace(/\s*position_holder/, 'last');
		aItems[aItems.length - 2].className = aItems[aItems.length - 2].className.replace(/\s*position_holder/, 'last');
	}
	else
	{
		aItems[aItems.length - 2].className = aItems[aItems.length - 2].className.replace(/\s*last/, 'position_holder');
		aItems[aItems.length - 3].className = aItems[aItems.length - 3].className.replace(/\s*last/, 'position_holder');
	}
};

InTopicModeration.prototype.handleSubmit = function (sSubmitType)
{
	var oForm = document.getElementById(this.opt.sFormId);

	// Make sure this form isn't submitted in another way than this function.
	var oInput = document.createElement('input');
	oInput.type = 'hidden';
	oInput.name = this.opt.sSessionVar;
	oInput.value = this.opt.sSessionId;
	oForm.appendChild(oInput);

	switch (sSubmitType)
	{
		case 'remove':
			if (!confirm(this.opt.sRemoveButtonConfirm))
				return false;

			oForm.action = oForm.action.replace(/;restore_selected=1/, '');
		break;

		case 'restore':
			if (!confirm(this.opt.sRestoreButtonConfirm))
				return false;

			oForm.action = oForm.action + ';restore_selected=1';
		break;

		default:
			return false;
	}

	oForm.submit();
	return true;
};

// A global array containing all IconList objects.
var aIconLists = [];

// *** IconList object.
function IconList(oOptions)
{
	if (!can_ajax)
		return;

	this.opt = oOptions;
	this.bListLoaded = false;
	this.oContainerDiv = null;
	this.funcMousedownHandler = null;
	this.funcParent = this;
	this.iCurMessageId = 0;
	this.iCurTimeout = 0;

	// Replace all message icons by icons with hoverable and clickable div's.
	for (var i = document.images.length - 1, iPrefixLength = this.opt.sIconIdPrefix.length; i >= 0; i--)
		if (document.images[i].id.substr(0, iPrefixLength) == this.opt.sIconIdPrefix)
			$(document.images[i]).replaceWith('<div title="' + this.opt.sLabelIconList + '" onclick="' + this.opt.sBackReference + '.openPopup(this, ' + document.images[i].id.substr(iPrefixLength) + ')" onmouseover="' + this.opt.sBackReference + '.onBoxHover(this, true)" onmouseout="' + this.opt.sBackReference + '.onBoxHover(this, false)" style="background: ' + this.opt.sBoxBackground + '; cursor: pointer; padding: 3px 3px 1px; text-align: center;"><img src="' + document.images[i].src + '" alt="' + document.images[i].alt + '" id="' + document.images[i].id + '" style="margin: 0px; padding: ' + (is_ie ? '3px' : '3px 0 2px') + ';" /></div>');
}

// Event for the mouse hovering over the original icon.
IconList.prototype.onBoxHover = function (oDiv, bMouseOver)
{
	var i = (3 - this.opt.iBoxBorderWidthHover);
	$(oDiv).css({
		border: bMouseOver ? this.opt.iBoxBorderWidthHover + 'px solid ' + this.opt.sBoxBorderColorHover : '',
		background: bMouseOver ? this.opt.sBoxBackgroundHover : this.opt.sBoxBackground,
		padding: bMouseOver ? i + 'px ' + i + 'px 0' : '3px 3px 1px'
	});
};

// Show the list of icons after the user clicked the original icon.
IconList.prototype.openPopup = function (oDiv, iMessageId)
{
	this.iCurMessageId = iMessageId;

	if (!this.bListLoaded && this.oContainerDiv == null)
	{
		// Create a container div.
		this.oContainerDiv = $('<div></div>', { id: 'iconList' }).css({
			display: 'none',
			cursor: 'pointer',
			position: 'absolute',
			width: oDiv.offsetWidth + 'px',
			background: this.opt.sContainerBackground,
			border: this.opt.sContainerBorder,
			padding: '1px',
			textAlign: 'center'
		}).appendTo('body')[0];

		// Start to fetch its contents.
		ajax_indicator(true);
		getXMLDocument.call(this, smf_prepareScriptUrl(this.opt.sScriptUrl) + 'action=xmlhttp;sa=messageicons;board=' + this.opt.iBoardId + ';xml', this.onIconsReceived);
	}

	// Set the position of the container.
	var aPos = $(oDiv).offset();

	$(this.oContainerDiv).css({
		top: (aPos.top + oDiv.offsetHeight) + 'px',
		left: (aPos.left - 1) + 'px'
	}).toggle(this.bListLoaded);

	this.oClickedIcon = oDiv;

	$(document.body).mousedown(this.onWindowMouseDown);
};

// Setup the list of icons once it is received through XMLHttp.
IconList.prototype.onIconsReceived = function (oXMLDoc)
{
	var sItems = '', br = this.opt.sBackReference, bord = this.opt.sItemBorder, bg = this.opt.sItemBackground;

	$('smf icon', oXMLDoc).each(function () {
		sItems += '<div onmouseover="' + br + '.onItemHover(this, true)" onmouseout="' + br + '.onItemHover(this, false);" onmousedown="' + br + '.onItemMouseDown(this, \'' + $(this).attr('value') + '\');" style="padding: 3px 0px; margin-left: auto; margin-right: auto; border: ' + bord + '; background: ' + bg + '"><img src="' + $(this).attr('url') + '" alt="' + $(this).attr('name') + '" title="' + $(this).text() + '" /></div>';
	});

	$(this.oContainerDiv).html(sItems).show();
	this.bListLoaded = true;

	if (is_ie)
		this.oContainerDiv.style.width = this.oContainerDiv.clientWidth + 'px';

	ajax_indicator(false);
};

// Event handler for hovering over the icons.
IconList.prototype.onItemHover = function (oDiv, bMouseOver)
{
	oDiv.style.background = bMouseOver ? this.opt.sItemBackgroundHover : this.opt.sItemBackground;
	oDiv.style.border = bMouseOver ? this.opt.sItemBorderHover : this.opt.sItemBorder;
	if (this.iCurTimeout != 0)
		window.clearTimeout(this.iCurTimeout);
	if (bMouseOver)
		this.onBoxHover(this.oClickedIcon, true);
	else
		this.iCurTimeout = window.setTimeout(this.opt.sBackReference + '.collapseList();', 500);
};

// Event handler for clicking on one of the icons.
IconList.prototype.onItemMouseDown = function (oDiv, sNewIcon)
{
	if (this.iCurMessageId != 0)
	{
		ajax_indicator(true);
		var oXMLDoc = getXMLDocument(smf_prepareScriptUrl(this.opt.sScriptUrl) + 'action=jsmodify;topic=' + this.opt.iTopicId + ';msg=' + this.iCurMessageId + ';' + this.opt.sSessionVar + '=' + this.opt.sSessionId + ';icon=' + sNewIcon + ';xml');
		ajax_indicator(false);

		var oMessage = $('smf message', oXMLDoc.responseXML);
		if (!($('error', oMessage).length))
		{
			if (this.opt.bShowModify && $('modified', oMessage).length)
				$('#modified_' + this.iCurMessageId).html($('modified', oMessage).text());
			$('img', this.oClickedIcon).attr('src', $('img', oDiv).attr('src'));
		}
	}
};

// Event handler for clicking outside the list (will make the list disappear).
IconList.prototype.onWindowMouseDown = function ()
{
	for (var i = aIconLists.length - 1; i >= 0; i--)
		aIconLists[i].collapseList.call(aIconLists[i].funcParent);
};

// Collapse the list of icons.
IconList.prototype.collapseList = function()
{
	this.onBoxHover(this.oClickedIcon, false);
	$(this.oContainerDiv).hide();
	this.iCurMessageId = 0;
	$(document.body).mousedown(this.onWindowMouseDown);
};


// *** Other functions...
function expandThumb(thumbID)
{
	var img = $('#thumb_' + thumbID)[0];
	var link = $('#link_' + thumbID)[0];
	var tmp = img.src;
	img.src = link.href;
	link.href = tmp;
	img.style.width = '';
	img.style.height = '';
	return false;
}

// Adds a button to a certain button strip.
function wedge_addButton(sButtonStripId, bUseImage, oOptions)
{
	var oButtonStrip = document.getElementById(sButtonStripId);
	var aItems = oButtonStrip.getElementsByTagName('li');

	// Remove the 'last' class from the last item.
	if (aItems.length > 0)
	{
		var oLastItem = aItems[aItems.length - 1];
		oLastItem.className = oLastItem.className.replace(/\s*last/, 'position_holder');
	}

	// Add the button.
	$('<li></li>').html('<a href="#"' + oOptions.sCustom + ' class="last" id="' + oOptions.sId + '">' + oOptions.sText + '</a>')
		.hide().appendTo($('ul', oButtonStrip)).fadeIn(300);
}

// *** The UserMenu
function UserMenu(oList)
{
	this.list = oList;
}

UserMenu.prototype.switchMenu = function (oLink)
{
	var details = oLink && oLink.id ? oLink.id.substr(2).split('_') : [0, 0];
	var iMsg = details[0], iUserId = details[1];

	if (current_user_menu != null)
	{
		$('#userMenu' + current_user_menu).remove();
		if (current_user_menu == iMsg)
		{
			current_user_menu = null;
			return false;
		}
	}
	current_user_menu = iMsg;
	if (!(this.list['user' + iUserId]))
		return false;
	for (var i = 0, sHTML = '', aLinkList = this.list['user' + iUserId], j = aLinkList.length; i < j; i++)
	{
		if (aLinkList[i][0].charAt[0] == '?')
			aLinkList[i][0] = smf_scripturl + aLinkList[i][0];

		sHTML += '<div class="usermenuitem windowbg"><a href="' + aLinkList[i][0].replace(/%msg%/, iMsg) + '">' + aLinkList[i][1] + '</a></div>';
	}
	var pos = $(oLink).offset();
	$('<div></div>', { id: 'userMenu' + iMsg, 'class': 'usermenu' }).html(sHTML).appendTo('body')
		.css({ display: 'block', left: pos.left + 'px', top: (pos.top + oLink.offsetHeight) + 'px' })
		.mouseleave(function () { oUserMenu.switchMenu(); current_user_menu = null; });
	return false;
};


/* Optimize:
cur_topic_id = ct
cur_msg_id = cm
buff_subject = bs
cur_subject_div = cs
in_edit_mode = em
*/