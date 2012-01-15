/*!
 * Wedge
 *
 * Helper functions for topic pages
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

var cur_topic_id, cur_msg_id, cur_subject_div, buff_subject, in_edit_mode = 0;
hide_prefixes = [];

function is_editing()
{
	return in_edit_mode == 1;
}

function go_up()
{
	$('html,body').animate(
		{ scrollTop: 0 },
		1000
	);

	return false;
}

function go_down()
{
	$('html,body').animate(
		{ scrollTop: $(document).height() - $(window).height() },
		1000
	);

	return false;
}

// For templating, shown when an inline edit is made.
function modify_topic_show_edit(subject)
{
	// Just template the subject.
	cur_subject_div.html('<input type="text" name="subject" value="' + subject + '" size="60" style="width: 95%" maxlength="80" onkeypress="modify_topic_keypress(e);"><input type="hidden" name="topic" value="' + cur_topic_id + '"><input type="hidden" name="msg" value="' + cur_msg_id.substr(4) + '">');
}

// And the reverse for hiding it.
function modify_topic_hide_edit(subject)
{
	// Re-template the subject!
	cur_subject_div.html('<a href="' + we_prepareScriptUrl() + 'topic=' + cur_topic_id + '.0">' + subject + '</a>');
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

	show_ajax();
	getXMLDocument(we_prepareScriptUrl() + 'action=quotefast;quote=' + first_msg_id + ';modify;xml', onDocReceived_modify_topic);
}

function onDocReceived_modify_topic(XMLDoc)
{
	cur_msg_id = $('message', XMLDoc).attr('id').substr(4);

	cur_subject_div = $('#msg_' + cur_msg_id);
	buff_subject = cur_subject_div.html();

	// Here we hide any other things they want hiding on edit.
	set_hidden_topic_areas(false);

	modify_topic_show_edit($('subject', XMLDoc).text());
	hide_ajax();
}

function modify_topic_cancel()
{
	cur_subject_div.html(buff_subject);
	set_hidden_topic_areas(true);

	in_edit_mode = 0;
	return false;
}

function modify_topic_save()
{
	if (!in_edit_mode)
		return true;

	var x = [], qm = document.forms.quickModForm;
	x.push('subject=' + qm.subject.value.replace(/&#/g, '&#38;#').php_urlencode());
	x.push('topic=' + qm.elements.topic.value);
	x.push('msg=' + qm.elements.msg.value);

	show_ajax();
	sendXMLDocument(we_prepareScriptUrl() + 'action=jsmodify;topic=' + qm.elements.topic.value + ';' + we_sessvar + '=' + we_sessid + ';xml', x.join('&'), modify_topic_done);

	return false;
}

function modify_topic_done(XMLDoc)
{
	if (!XMLDoc)
	{
		modify_topic_cancel();
		return true;
	}

	var
		subject = $('we message subject', XMLDoc),
		error = $('we message error', XMLDoc);

	hide_ajax();

	if (!subject.length || error.length)
		return false;

	modify_topic_hide_edit(subject.text());
	set_hidden_topic_areas(true);
	in_edit_mode = 0;

	return false;
}

// Simply restore any hidden bits during topic editing.
function set_hidden_topic_areas(state)
{
	for (var i = 0; i < hide_prefixes.length; i++)
		$('#' + hide_prefixes[i] + cur_msg_id).toggle(state);
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
		window.location.href = we_prepareScriptUrl() + 'action=post;quote=' + iMessageId + ';topic=' + this.opt.iTopicId + '.' + this.opt.iStart;
		return false;
	}
	else
	{
		show_ajax();
		getXMLDocument(we_prepareScriptUrl() + 'action=quotefast;quote=' + iMessageId + ';xml;mode=' + (oEditorHandle_message.bRichTextEnabled ? 1 : 0), this.onQuoteReceived);

		// Move the view to the quick reply box.
		window.location.hash = (is_ie ? '' : '#') + this.opt.sJumpAnchor;

		return false;
	}
};

// This is the callback function used after the Ajax request.
QuickReply.prototype.onQuoteReceived = function (oXMLDoc)
{
	oEditorHandle_message.insertText($('quote', oXMLDoc).text(), false, true);

	hide_ajax();
};

// The function handling the swapping of the quick reply.
QuickReply.prototype.swap = function ()
{
	var cont = $('#' + this.opt.sContainerId);
	$('#' + this.opt.sImageId).toggleClass('fold', this.bCollapsed);
	this.bCollapsed ? cont.slideDown(150) : cont.slideUp(200);

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

	// Did we press the Quick Modify button by error while trying to submit? Oops.
	if (this.sCurMessageId && this.sCurMessageId.substr(4) == iMessageId)
		return;

	// First cancel if there's another message still being edited.
	if (this.sCurMessageId)
		this.modifyCancel();

	// Send out the Ajax request to get more info
	show_ajax();

	getXMLDocument.call(this, we_prepareScriptUrl() + 'action=quotefast;quote=' + iMessageId + ';modify;xml', this.onMessageReceived);
};

// The callback function used for the Ajax request retrieving the message.
QuickModify.prototype.onMessageReceived = function (XMLDoc)
{
	// Hide the 'loading...' sign.
	hide_ajax();

	// Grab the message ID.
	var sId = $('message', XMLDoc).attr('id');

	if (sId == this.sCurMessageId)
		return;
	else if (this.sCurMessageId)
		this.modifyCancel();
	this.sCurMessageId = sId;

	// If this is not valid then simply give up.
	this.oCurMessageDiv = $('#' + this.sCurMessageId);

	if (!this.oCurMessageDiv.length)
		return this.modifyCancel();

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
	this.sCurMessageId = '';

	return false;
};

// The function called after a user wants to save his precious message.
QuickModify.prototype.modifySave = function ()
{
	// We cannot save if we weren't in edit mode.
	if (!this.sCurMessageId)
		return true;

	var x = [], qm = document.forms.quickModForm;
	x.push('subject=' + qm.subject.value.replace(/&#/g, '&#38;#').php_urlencode());
	x.push('message=' + qm.message.value.replace(/&#/g, '&#38;#').php_urlencode());
	x.push('topic=' + qm.elements.topic.value);
	x.push('msg=' + qm.elements.msg.value);

	// Send in the Ajax request and let's hope for the best.
	show_ajax();
	sendXMLDocument.call(this, we_prepareScriptUrl() + 'action=jsmodify;topic=' + this.opt.iTopicId + ';' + we_sessvar + '=' + we_sessid + ';xml', x.join('&'), this.onModifyDone);

	return false;
};

// Callback function of the Ajax request sending the modified message.
QuickModify.prototype.onModifyDone = function (XMLDoc)
{
	// We've finished the loading part.
	hide_ajax();

	var
		message = $('we message', XMLDoc),
		body = $('body', message),
		error = $('error', message);

	// If we didn't get a valid document, just cancel.
	if (!XMLDoc || !message.length)
	{
		// If you could instead tell us what's wrong...?
		if (XMLDoc)
			$('#error_box').html(XMLDoc.childNodes && XMLDoc.childNodes.length > 0 && XMLDoc.firstChild.nodeName == 'parsererror' ? XMLDoc.firstChild.textContent : XMLDoc);
		else
			this.modifyCancel();
		return;
	}

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
			$('#top_subject').html(sSubjectText.replace(/\{&dollarfix;\$\}/g, '$'));

		// Show this message as 'modified on x by y'.
		if (this.opt.bShowModify)
			$('#modified_' + this.sCurMessageId.substr(4)).html($('modified', message).text());

		// Finally, we can safely declare we're up and running...
		this.sCurMessageId = '';
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
			$('<input type="checkbox" name="msgs[]" value="' + this.opt.aMessageIds[i] + '"></input>')
			.data('that', this).click(function () { $(this).data('that').handleClick(this); })
		).show();
}

InTopicModeration.prototype.handleClick = function (oCheckbox)
{
	var
		opt = this.opt,
		button_strip = opt.sButtonStrip,
		use_image = opt.bUseImageButton,
		display = opt.sButtonStripDisplay;

	if (!this.bButtonsShown)
	{
		// Make sure it can go somewhere.
		if (!$('#' + display).length)
			$('<ul id="' + display + '" class="' + (opt.sButtonStripClass ? opt.sButtonStripClass : 'buttonlist floatleft') + '"></ul>').appendTo('#' + button_strip);
		else
			$('#' + display).show();

		// Add the 'remove selected items' button.
		if (opt.bCanRemove)
			wedge_addButton(button_strip, use_image, {
				sId: opt.sSelf + '_remove_button',
				sText: opt.sRemoveButtonLabel,
				sImage: opt.sRemoveButtonImage,
				sCustom: ' onclick="return ' + opt.sSelf + '.handleSubmit(\'remove\')"'
			});

		// Add the 'restore selected items' button.
		if (opt.bCanRestore)
			wedge_addButton(button_strip, use_image, {
				sId: opt.sSelf + '_restore_button',
				sText: opt.sRestoreButtonLabel,
				sImage: opt.sRestoreButtonImage,
				sCustom: ' onclick="return ' + opt.sSelf + '.handleSubmit(\'restore\')"'
			});

		// Adding these buttons once should be enough.
		this.bButtonsShown = true;
	}

	// Keep stats on how many items were selected.
	this.iNumSelected += oCheckbox.checked ? 1 : -1;
	var i = this.iNumSelected;

	// Show the number of messages selected in the button.
	if (opt.bCanRemove && !use_image)
		var but1 = $('#' + opt.sSelf + '_remove_button')
			.html(opt.sRemoveButtonLabel + ' [' + i + ']');

	if (opt.bCanRestore && !use_image)
		var but2 = $('#' + opt.sSelf + '_restore_button')
			.html(opt.sRestoreButtonLabel + ' [' + i + ']');

	if (but1 && i < 1 && but1.is(':visible'))
		but1.fadeOut(300).hide();
	if (but1 && i > 0 && but1.is(':hidden'))
		but1.fadeIn(300).show();
	if (but2 && i < 1 && but2.is(':visible'))
		but2.fadeOut(300).hide();
	if (but2 && i > 0 && but2.is(':hidden'))
		but2.fadeIn(300).show();

	// Try to restore the correct position.
	$('#' + button_strip + ' li').slice(-3, -1).toggleClass('position_holder', i > 0).toggleClass('last', i < 1);
};

InTopicModeration.prototype.handleSubmit = function (sSubmitType)
{
	// Make sure this form isn't submitted in another way than this function.
	var
		oForm = $('#' + this.opt.sFormId)[0],
		oInput = $('<input type="hidden" name="' + we_sessvar + '" />').val(we_sessid).appendTo(oForm);

	if (sSubmitType == 'remove')
	{
		if (!confirm(this.opt.sRemoveButtonConfirm))
			return false;

		oForm.action = oForm.action.replace(/;restore_selected=1/, '');
	}
	else if (sSubmitType == 'restore')
	{
		if (!confirm(this.opt.sRestoreButtonConfirm))
			return false;

		oForm.action = oForm.action + ';restore_selected=1';
	}
	else
		return false;

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
			$(document.images[i]).replaceWith('<div title="' + this.opt.sLabelIconList + '" onclick="' + this.opt.sBackReference + '.openPopup(this, ' + document.images[i].id.substr(iPrefixLength) + ')" onmouseover="' + this.opt.sBackReference + '.onBoxHover(this, true)" onmouseout="' + this.opt.sBackReference + '.onBoxHover(this, false)" style="background: ' + this.opt.sBoxBackground + '; cursor: pointer; padding: 3px 3px 1px; text-align: center"><img src="' + document.images[i].src + '" alt="' + document.images[i].alt + '" id="' + document.images[i].id + '" style="margin: 0px; padding: ' + (is_ie ? '3px' : '3px 0 2px') + '"></div>');
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
		this.oContainerDiv = $('<div></div>', { id: 'iconList' }).hide().css({
			cursor: 'pointer',
			position: 'absolute',
			width: oDiv.offsetWidth,
			background: this.opt.sContainerBackground,
			border: this.opt.sContainerBorder,
			padding: 1,
			textAlign: 'center'
		}).appendTo('body');

		// Start to fetch its contents.
		show_ajax();
		getXMLDocument.call(this, we_prepareScriptUrl() + 'action=ajax;sa=messageicons;board=' + this.opt.iBoardId + ';xml', this.onIconsReceived);
	}

	// Set the position of the container.
	var aPos = $(oDiv).offset();

	this.oContainerDiv.css({
		top: aPos.top + oDiv.offsetHeight,
		left: aPos.left - 1
	}).toggle(this.bListLoaded);

	this.oClickedIcon = oDiv;

	$('body').mousedown(this.onWindowMouseDown);
};

// Setup the list of icons once it is received through Ajax.
IconList.prototype.onIconsReceived = function (oXMLDoc)
{
	var sItems = '', br = this.opt.sBackReference, bord = this.opt.sItemBorder, bg = this.opt.sItemBackground;

	$('we icon', oXMLDoc).each(function () {
		sItems += '<div onmouseover="' + br + '.onItemHover(this, true)" onmouseout="' + br + '.onItemHover(this, false)" onmousedown="' + br + '.onItemMouseDown(this, \'' + $(this).attr('value') + '\')" style="padding: 3px 0px; margin-left: auto; margin-right: auto; border: ' + bord + '; background: ' + bg + '"><img src="' + $(this).attr('url') + '" alt="' + $(this).attr('name') + '" title="' + $(this).text() + '"></div>';
	});

	this.oContainerDiv.html(sItems).show();
	this.bListLoaded = true;

	if (is_ie)
		this.oContainerDiv.css('width', this.oContainerDiv.clientWidth);

	hide_ajax();
};

// Event handler for hovering over the icons.
IconList.prototype.onItemHover = function (oDiv, bMouseOver)
{
	oDiv.style.background = bMouseOver ? this.opt.sItemBackgroundHover : this.opt.sItemBackground;
	oDiv.style.border = bMouseOver ? this.opt.sItemBorderHover : this.opt.sItemBorder;
	if (this.iCurTimeout != 0)
		clearTimeout(this.iCurTimeout);
	if (bMouseOver)
		this.onBoxHover(this.oClickedIcon, true);
	else
		this.iCurTimeout = setTimeout(this.opt.sBackReference + '.collapseList();', 500);
};

// Event handler for clicking on one of the icons.
IconList.prototype.onItemMouseDown = function (oDiv, sNewIcon)
{
	if (this.iCurMessageId != 0)
	{
		show_ajax();
		var oXMLDoc = getXMLDocument(we_prepareScriptUrl() + 'action=jsmodify;topic=' + this.opt.iTopicId + ';msg=' + this.iCurMessageId + ';' + we_sessvar + '=' + we_sessid + ';icon=' + sNewIcon + ';xml');
		hide_ajax();

		var oMessage = $('we message', oXMLDoc.responseXML);
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
IconList.prototype.collapseList = function ()
{
	this.onBoxHover(this.oClickedIcon, false);
	this.oContainerDiv.hide();
	this.iCurMessageId = 0;
	$('body').mousedown(this.onWindowMouseDown);
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
	$('#' + sButtonStripId + ' li').last().removeClass('last').addClass('position_holder');

	// Add the button.
	$('<li></li>').html('<a href="#"' + oOptions.sCustom + ' class="last" id="' + oOptions.sId + '">' + oOptions.sText + '</a>')
		.hide().appendTo($('#' + sButtonStripId + ' ul')).fadeIn(300);
}


// *** The UserMenu
function MiniMenu(oList, bAcme, oStrings)
{
	var that = this, is_right_side = bAcme ? true : $('.right-side').length > 0;
	that.list = oList;
	that.strings = oStrings;
	$(bAcme ? '.acme' : '.umme')
		.mouseenter(function () {
			that.switchMenu(this, bAcme, is_right_side ? 'left' : '');
		})
		.mouseleave(function (e) {
			var menu = (bAcme ? 'ac' : 'user') + 'menu', target = e.relatedTarget;
			if (target.className.indexOf(menu) == -1 && !$(target).parents('.' + menu).length)
				$('.' + menu).remove();
		});
}

MiniMenu.prototype.switchMenu = function (oLink, acme, direction)
{
	var
		details = oLink.id.substr(2).split('_'),
		iMsg = details[0], id = details[acme ? 0 : 1],
		pos = $(oLink).offset(), parent = $(oLink).parent(),
		aLinkList = this.list[id], $body = $('body'),
		menuid = (acme ? '#actMenu' : '#userMenu') + iMsg;
		mm = acme ? 'acme' : 'umme', mmove = 'mousemove.' + mm,
		leave = function (e) {
			if (!e || e.relatedTarget.className.indexOf(mm) == -1)
			{
				parent.removeClass('show');
				$(this).remove();
			}
		};

	if ($(menuid).length || !aLinkList)
		return;

	var sHTML = '', i = 1, j = aLinkList.length, special = aLinkList[0], mtarget, pms, sLink, $men, mpo, paw;
	for (; i < j; i++)
	{
		pms = this.strings[aLinkList[i]];
		sLink = pms[2] ? pms[2].replace(/%id%/, id).replace(/%special%/, special) : oLink.href;
		if (!acme && sLink.charAt(0) == '?')
			sLink = oLink.href + sLink;

		sHTML += '<li><a href="' + sLink + '"'
			+ (pms[3] ? ' class="' + pms[3] + '"' : '')
			+ (pms[4] ? ' ' + pms[4] : '') // Custom data, such as events?
			+ (pms[1] ? ' title="' + pms[1] + '"' : '')
			+ '>' + pms[0] + '</a></li>';
	}
	parent.addClass('show');

	$men = acme ?
		$('<div class="acmenu" id="actMenu' + id + '"></div>').html('<ul class="quickbuttons acmenuitem windowbg">' + sHTML + '</ul>') :
		$('<div class="usermenu' + (direction == 'left' ? ' right-side' : '') + '" id="userMenu' + iMsg + '"></div>').html('<ul class="quickbuttons usermenuitem windowbg">' + sHTML + '</ul>');
	$men.hide().appendTo($body);

	if (direction == 'left')
	{
		mpo = [ $men.width(), $men.height() ];
		paw = $(acme ? parent : oLink).width();
		$men.css({ right: $(window).width() - (pos.left + paw + 6), top: pos.top - 4, minWidth: $(oLink).width() + 1, width: 0, height: 0 })
			.mouseleave(leave)
			.animate({ width: mpo[0], height: mpo[1], opacity: 'show' }, 500, function () {
				$men.css({ left: pos.left + paw - mpo[0] - 4, right: 'auto' });
				$body.unbind(mmove);
				// Once the animation is completed, is the mouse still inside the menu area?
				if (mtarget && mtarget.className != mm && !$(mtarget).parents(menuid).length)
					leave();
			});
	}
	else
	{
		$men.css({ left: pos.left - 6, top: pos.top - 4, minWidth: $(oLink).width() + 1 });
		$men.mouseleave(leave).show(500, function () {
			$body.unbind(mmove);
			if (mtarget && mtarget.className != mm && !$(mtarget).parents(menuid).length)
				leave();
		});
	}
	$body.bind(mmove, function (e) { mtarget = e.target; });
};
