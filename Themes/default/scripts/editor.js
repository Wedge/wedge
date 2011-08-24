/*!
 * Wedge
 *
 * weEditor manages the post editor for you, both in its plain text and WYSIWTF versions.
 * (I did say WYSIWTF, no typos here.)
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function weEditor(oOptions)
{
	this.opt = oOptions;

	// Create some links to the editor object.
	this.oTextHandle = this.oFrameHandle = this.oFrameDocument = null;
	this.oFrameWindow = this.oBreadHandle = null;
	this.sCurrentText = 'sText' in this.opt ? this.opt.sText : '';

	// How big?
	this.sEditWidth = 'sEditWidth' in this.opt ? this.opt.sEditWidth : '70%';
	this.sEditHeight = 'sEditHeight' in this.opt ? this.opt.sEditHeight : 150;

	this.showDebug = false;
	this.bRichTextEnabled = 'bWysiwyg' in this.opt && this.opt.bWysiwyg;
	this.bRichTextPossible = !this.opt.bRichEditOff && (is_ie || is_ff || is_opera95up || is_webkit) && !(is_iphone || is_android);

	// Kinda holds all the useful stuff.
	this.aKeyboardShortcuts = [];

	// This tracks the cursor position on IE to avoid refocus problems.
	this.cursorX = 0;
	this.cursorY = 0;

	// This is all the elements that can have a simple execCommand.
	this.oSimpleExec = {
		b: 'bold',
		u: 'underline',
		i: 'italic',
		s: 'strikethrough',
		left: 'justifyleft',
		center: 'justifycenter',
		right: 'justifyright',
		hr: 'inserthorizontalrule',
		list: 'insertunorderedlist',
		orderlist: 'insertorderedlist',
		sub: 'subscript',
		sup: 'superscript',
		indent: 'indent',
		outdent: 'outdent'
	};

	// Codes to call a private function
	this.oWedgeExec = {
		add_media: 'addMedia',
		unformat: 'removeFormatting',
		toggle: 'toggleView'
	};

	// Any special breadcrumb mappings to ensure we show a consistant tag name.
	this.breadCrumbNameTags = {
		strike: 's',
		strong: 'b',
		em: 'i'
	};

	this.aBreadCrumbNameStyles = [
		{
			sStyleType: 'text-decoration',
			sStyleValue: 'underline',
			sBbcTag: 'u'
		},
		{
			sStyleType: 'text-decoration',
			sStyleValue: 'line-through',
			sBbcTag: 's'
		},
		{
			sStyleType: 'text-align',
			sStyleValue: 'left',
			sBbcTag: 'left'
		},
		{
			sStyleType: 'text-align',
			sStyleValue: 'center',
			sBbcTag: 'center'
		},
		{
			sStyleType: 'text-align',
			sStyleValue: 'right',
			sBbcTag: 'right'
		},
		{
			sStyleType: 'font-weight',
			sStyleValue: 'bold',
			sBbcTag: 'b'
		},
		{
			sStyleType: 'font-style',
			sStyleValue: 'italic',
			sBbcTag: 'i'
		}
	];

	// Font maps (HTML => CSS size)
	this.aFontSizes = [
		0, 6, 8, 10, 12, 14, 18, 24
	];

	// Color maps!
	this.oFontColors = {
		black: '#000000',
		red: '#ff0000',
		yellow: '#ffff00',
		pink: '#ffc0cb',
		green: '#008000',
		orange: '#ffa500',
		purple: '#800080',
		blue: '#0000ff',
		beige: '#f5f5dc',
		brown: '#a52a2a',
		teal: '#008080',
		navy: '#000080',
		maroon: '#800000',
		limegreen: '#32cd32'
	};

	this.sFormId = 'sFormId' in this.opt ? this.opt.sFormId : 'postmodify';
	this.iArrayPosition = weEditors.length;

	// Current resize state.
	this.oweEditorCurrentResize = {};

	// Define the event wrapper functions.
	var oCaller = this;
	this.aEventWrappers = {
		editorKeyUp: function(oEvent) {return oCaller.editorKeyUp(oEvent);},
		shortcutCheck: function(oEvent) {return oCaller.shortcutCheck(oEvent);},
		editorBlur: function(oEvent) {return oCaller.editorBlur(oEvent);},
		editorFocus: function(oEvent) {return oCaller.editorFocus(oEvent);},
		startResize: function(oEvent) {return oCaller.startResize(oEvent);},
		resizeOverDocument: function(oEvent) {return oCaller.resizeOverDocument(oEvent);},
		endResize: function(oEvent) {return oCaller.endResize(oEvent);},
		resizeOverIframe: function(oEvent) {return oCaller.resizeOverIframe(oEvent);}
	};

	// Set the textHandle.
	this.oTextHandle = document.getElementById(this.opt.sUniqueId);

	// Ensure the currentText is set correctly depending on the mode.
	if (this.sCurrentText === '' && !this.bRichTextEnabled)
		this.sCurrentText = this.oTextHandle.innerHTML.php_unhtmlspecialchars();

	// Only try to do this if rich text is supported.
	if (this.bRichTextPossible)
	{
		// Make the iframe itself, stick it next to the current text area, and give it an ID.
		this.oFrameHandle = $('<iframe></iframe>', {
			'class': 'rich_editor_frame', src: 'about:blank', id: 'html_' + this.opt.sUniqueId, tabIndex: this.oTextHandle.tabIndex
		}).css({ display: 'none', margin: 0 }).appendTo(this.oTextHandle.parentNode)[0];

		// Create some handy shortcuts.
		this.oFrameDocument = this.oFrameHandle.contentDocument || ('contentWindow' in this.oFrameHandle ? this.oFrameHandle.contentWindow.document : this.oFrameHandle.document);
		this.oFrameWindow = 'contentWindow' in this.oFrameHandle ? this.oFrameHandle.contentWindow : this.oFrameHandle.document.parentWindow;

		// Create the debug window... and stick this under the main frame - make it invisible by default.
		this.oBreadHandle = $('<div></div>', { id: 'bread_' + this.opt.sUniqueId }).css({ visibility: 'visible', display: 'none' }).appendTo(this.oFrameHandle.parentNode)[0];

		// Size the iframe dimensions to something sensible.
		$(this.oFrameHandle).css({ width: this.sEditWidth, height: this.sEditHeight, visibility: 'visible' });

		// Only bother formatting the debug window if debug is enabled.
		if (this.showDebug)
			$(this.oBreadHandle).addClass('windowbg2').css({ width: this.sEditWidth, height: 20, border: '1px black solid', display: '' });

		// Populate the editor with nothing by default.
		if (!is_opera95up)
		{
			this.oFrameDocument.open();
			this.oFrameDocument.write('');
			this.oFrameDocument.close();
		}

		// Right to left mode?
		if (this.opt.bRTL)
		{
			this.oFrameDocument.dir = 'rtl';
			this.oFrameDocument.body.dir = 'rtl';
		}

		// Mark it as editable...
		if (this.oFrameDocument.body.contentEditable)
			this.oFrameDocument.body.contentEditable = true;
		else
		{
			this.oFrameHandle.style.display = '';
			this.oFrameDocument.designMode = 'on';
			this.oFrameHandle.style.display = 'none';
		}

		var thisFrameHead = $(this.oFrameHandle).contents().find('head');
		$('link[rel=stylesheet]').each(function() { thisFrameHead.append($('<p>').append($(this).clone()).html()); });

		// Apply the class and set the frame padding/margin inside the editor.
		$(this.oFrameDocument.body).addClass('rich_editor').css({ padding: 1, margin: 0 });

		// Listen for input.
		this.oFrameDocument.instanceRef = this;
		this.oFrameHandle.instanceRef = this;
		this.oTextHandle.instanceRef = this;

		// Attach functions to the key and mouse events.
		$(this.oFrameDocument).bind('keyup mouseup', this.aEventWrappers.editorKeyUp).keydown(this.aEventWrappers.shortcutCheck);
		$(this.oTextHandle).keydown(this.aEventWrappers.shortcutCheck).keydown(splitQuote);
		if (this.opt.oDrafts)
			$(this.oTextHandle).keyup(function () {
				oCaller.opt.oDrafts.needsUpdate(true); // This is established earlier in this function.
			});

		if (is_ie)
			$(this.oFrameDocument).blur(this.aEventWrappers.editorBlur).focus(this.aEventWrappers.editorFocus);

		// Show the iframe only if wysiwyrg is on - and hide the text area.
		this.oTextHandle.style.display = this.bRichTextEnabled ? 'none' : '';
		this.oFrameHandle.style.display = this.bRichTextEnabled ? '' : 'none';
		this.oBreadHandle.style.display = this.bRichTextEnabled ? '' : 'none';
	}
	// If we can't do advanced stuff, then just do the basics.
	else
	{
		this.bRichTextEnabled = false;
		if (this.opt.oDrafts)
			$(this.oTextHandle).keyup(this.opt.oDrafts.needsUpdate(true));
	}

	// Make sure we set the message mode correctly.
	$('#' + this.opt.sUniqueId + '_mode').val(this.bRichTextEnabled ? 1 : 0);

	// Show the resizer.
	var sizer = $('#' + this.opt.sUniqueId + '_resizer');
	if (sizer.length && (!is_opera || is_opera95up) && !(is_chrome && !this.bRichTextEnabled))
	{
		// Currently nothing is being resized... I assume!
		window.weCurrentResizeEditor = null;
		sizer.show().mousedown(this.aEventWrappers.startResize);
	}

	// Set the text - if WYSIWYG is enabled that is.
	if (this.bRichTextEnabled)
	{
		this.insertText(this.sCurrentText, true);

		// Better make us the focus!
		this.setFocus();
	}

	// Finally, register shortcuts.
	this.registerDefaultShortcuts();
	this.updateEditorControls();
}

// Return the current text.
weEditor.prototype.getText = function (bPrepareEntities, bModeOverride)
{
	var sText, bCurMode = typeof bModeOverride != 'undefined' ? bModeOverride : this.bRichTextEnabled;

	if (!bCurMode || this.oFrameDocument === null)
	{
		sText = this.oTextHandle.value;
		if (bPrepareEntities)
			sText = sText.replace(/</g, '#welt#').replace(/>/g, '#wegt#').replace(/&/g, '#weamp#');
	}
	else
	{
		sText = this.oFrameDocument.body.innerHTML;
		if (bPrepareEntities)
			sText = sText.replace(/&lt;/g, '#welt#').replace(/&gt;/g, '#wegt#').replace(/&amp;/g, '#weamp#');
	}

	// Clean it up - including removing semi-colons.
	if (bPrepareEntities)
		sText = sText.replace(/&nbsp;/g, ' ').replace(/;/g, '#wecol#');

	// Return it.
	return sText;
};

// Return the current text.
weEditor.prototype.unprotectText = function (sText)
{
	// This restores welt, wegt and weamp into boring entities, to unprotect against XML'd information like quotes.
	sText = sText.replace(/#welt#/g, '&lt;').replace(/#wegt#/g, '&gt;').replace(/#weamp#/g, '&amp;');

	// Return it.
	return sText;
};

weEditor.prototype.editorKeyUp = function ()
{
	if (this.opt.oDrafts)
		this.opt.oDrafts.needsUpdate(true);

	// Rebuild the breadcrumb.
	this.updateEditorControls();
};

weEditor.prototype.editorBlur = function ()
{
	if (!is_ie)
		return;

	// !!! Need to do something here.
};

weEditor.prototype.editorFocus = function ()
{
	if (!is_ie)
		return;

	// !!! Need to do something here.
};

// Rebuild the breadcrumb etc - and set things to the correct context.
weEditor.prototype.updateEditorControls = function ()
{
	// Everything else is specific to HTML mode.
	if (!this.bRichTextEnabled)
	{
		// Set none of the buttons active.
		if (this.opt.oBBCBox)
			this.opt.oBBCBox.setActive([]);
		return;
	}

	var aCrumb = [];
	var aAllCrumbs = [];
	var iMaxLength = 6;

	// What is the current element?
	var oCurTag = this.getCurElement();

	var i = 0;
	while (oCurTag !== null && typeof oCurTag == 'object' && oCurTag.nodeName.toLowerCase() != 'body' && i < iMaxLength)
	{
		aCrumb[i++] = oCurTag;
		oCurTag = oCurTag.parentNode;
	}

	// Now print out the tree.
	var sTree = '', sCurFontName = '', sCurFontSize = '', sCurFontColor = '', iNumCrumbs;
	for (i = 0, iNumCrumbs = aCrumb.length; i < iNumCrumbs; i++)
	{
		var sCrumbName = aCrumb[i].nodeName.toLowerCase();

		// Does it have an alternative name?
		if (sCrumbName in this.breadCrumbNameTags)
			sCrumbName = this.breadCrumbNameTags[sCrumbName];
		// Don't bother with this...
		else if (sCrumbName == 'p')
			continue;
		// A link?
		else if (sCrumbName == 'a')
		{
			var sUrlInfo = aCrumb[i].getAttribute('href');
			sCrumbName = 'url';
			if (typeof sUrlInfo == 'string')
			{
				if (sUrlInfo.substr(0, 3) == 'ftp')
					sCrumbName = 'ftp';
				else if (sUrlInfo.substr(0, 6) == 'mailto')
					sCrumbName = 'email';
			}
		}
		else if (sCrumbName == 'span' || sCrumbName == 'div')
		{
			if (aCrumb[i].style)
			{
				for (var j = 0, iNumStyles = this.aBreadCrumbNameStyles.length; j < iNumStyles; j++)
				{
					// Do we have a font?
					if (aCrumb[i].style.fontFamily && aCrumb[i].style.fontFamily != '' && sCurFontName == '')
					{
						sCurFontName = aCrumb[i].style.fontFamily;
						sCrumbName = 'face';
					}
					// ... or a font size?
					if (aCrumb[i].style.fontSize && aCrumb[i].style.fontSize != '' && sCurFontSize == '')
					{
						sCurFontSize = aCrumb[i].style.fontSize;
						sCrumbName = 'size';
					}
					// ... even color?
					if (aCrumb[i].style.color && aCrumb[i].style.color != '' && sCurFontColor == '')
					{
						sCurFontColor = aCrumb[i].style.color;
						if (in_array(sCurFontColor, this.oFontColors))
							sCurFontColor = array_search(sCurFontColor, this.oFontColors);
						sCrumbName = 'color';
					}

					if (this.aBreadCrumbNameStyles[j].sStyleType == 'text-align' && aCrumb[i].style.textAlign && aCrumb[i].style.textAlign == this.aBreadCrumbNameStyles[j].sStyleValue)
						sCrumbName = this.aBreadCrumbNameStyles[j].sBbcTag;
					else if (this.aBreadCrumbNameStyles[j].sStyleType == 'text-decoration' && aCrumb[i].style.textDecoration && aCrumb[i].style.textDecoration == this.aBreadCrumbNameStyles[j].sStyleValue)
						sCrumbName = this.aBreadCrumbNameStyles[j].sBbcTag;
					else if (this.aBreadCrumbNameStyles[j].sStyleType == 'font-weight' && aCrumb[i].style.fontWeight && aCrumb[i].style.fontWeight == this.aBreadCrumbNameStyles[j].sStyleValue)
						sCrumbName = this.aBreadCrumbNameStyles[j].sBbcTag;
					else if (this.aBreadCrumbNameStyles[j].sStyleType == 'font-style' && aCrumb[i].style.fontStyle && aCrumb[i].style.fontStyle == this.aBreadCrumbNameStyles[j].sStyleValue)
						sCrumbName = this.aBreadCrumbNameStyles[j].sBbcTag;
				}
			}
		}
		// Do we have a font?
		else if (sCrumbName == 'font')
		{
			if (aCrumb[i].getAttribute('face') && sCurFontName == '')
			{
				sCurFontName = aCrumb[i].getAttribute('face').toLowerCase();
				sCrumbName = 'face';
			}
			if (aCrumb[i].getAttribute('size') && sCurFontSize == '')
			{
				sCurFontSize = aCrumb[i].getAttribute('size');
				sCrumbName = 'size';
			}
			if (aCrumb[i].getAttribute('color') && sCurFontColor == '')
			{
				sCurFontColor = aCrumb[i].getAttribute('color');
				if (in_array(sCurFontColor, this.oFontColors))
					sCurFontColor = array_search(sCurFontColor, this.oFontColors);
				sCrumbName = 'color';
			}
			// Something else - ignore.
			if (sCrumbName == 'font')
				continue;
		}

		sTree += (i != 0 ? '&nbsp;<strong>&gt;</strong>' : '') + '&nbsp;' + sCrumbName;
		aAllCrumbs.push(sCrumbName);
	}

	// Since we're in WYSIWYG state, show the toggle button as active.
	aAllCrumbs.push('toggle');

	this.opt.oBBCBox.setActive(aAllCrumbs);

	// Try set the font boxes correct.
	this.opt.oBBCBox.setSelect('sel_face', sCurFontName);
	this.opt.oBBCBox.setSelect('sel_size', sCurFontSize);
	this.opt.oBBCBox.setSelect('sel_color', sCurFontColor);

	if (this.showDebug)
		this.oBreadHandle.innerHTML = sTree;
};

// Set the HTML content to be that of the text box - if we are in wysiwyg mode.
weEditor.prototype.doSubmit = function ()
{
	if (this.bRichTextEnabled)
		this.oTextHandle.value = this.oFrameDocument.body.innerHTML;
};

// Populate the box with text.
weEditor.prototype.insertText = function (sText, bClear, bForceEntityReverse, iMoveCursorBack)
{
	if (bForceEntityReverse)
		sText = this.unprotectText(sText);

	// Erase it all?
	if (bClear)
	{
		if (this.bRichTextEnabled)
		{
			this.oFrameDocument.body.innerHTML = sText;

			// Trick the cursor into coming back!
			if (is_ff || is_opera)
			{
				// For some obscure reason, FF3 Beta 2 and some
				// Opera versions may require this.
				this.oFrameDocument.body.contentEditable = false;
				this.oFrameDocument.designMode = 'off';
				this.oFrameDocument.designMode = 'on';
			}
		}
		else
			this.oTextHandle.value = sText;
	}
	else
	{
		this.setFocus();
		if (this.bRichTextEnabled)
		{
			// IE croaks if you have an image selected and try to insert!
			if ('selection' in this.oFrameDocument && this.oFrameDocument.selection.type != 'Text' && this.oFrameDocument.selection.type != 'None' && this.oFrameDocument.selection.clear)
				this.oFrameDocument.selection.clear();

			var oRange = this.getRange();

			if (oRange.pasteHTML)
			{
				oRange.pasteHTML(sText);

				// Do we want to move the cursor back at all?
				if (iMoveCursorBack)
					oRange.moveEnd('character', -iMoveCursorBack);

				oRange.select();
			}
			else
			{
				// If the cursor needs to be positioned, insert the last fragment first.
				if (typeof iMoveCursorBack != 'undefined' && iMoveCursorBack > 0 && sText.length > iMoveCursorBack)
				{
					var oSelection = this.getSelect(false, false);
					oSelection.getRangeAt(0).insertNode(this.oFrameDocument.createTextNode(sText.substr(sText.length - iMoveCursorBack)));
				}

				this.we_execCommand('inserthtml', false, typeof iMoveCursorBack == 'undefined' ? sText : sText.substr(0, sText.length - iMoveCursorBack));
			}
		}
		else
			replaceText(sText, this.oTextHandle);
	}

	if (this.opt.oDrafts)
		this.opt.oDrafts.needsUpdate(true);
};

// Special handler for WYSIWYG.
weEditor.prototype.we_execCommand = function (sCommand, bUi, sValue)
{
	if (this.opt.oDrafts)
		this.opt.oDrafts.needsUpdate(true);

	return this.oFrameDocument.execCommand(sCommand, bUi, sValue);
};

weEditor.prototype.insertSmiley = function (oSmileyProperties)
{
	var handle = this.oTextHandle, smileytext = oSmileyProperties[0];

	// In text mode we just add it in as we always did.
	if (!this.bRichTextEnabled)
	{
		if ('createTextRange' in handle)
		{
			var sel = 'caretPos' in handle ? handle.caretPos.duplicate() : null;
			if (sel != null)
				sel.moveStart('character', -1);
			if (sel == null || (sel.text.charAt(0) != ' ' && sel.text.charAt(0) != ''))
				smileytext = ' ' + smileytext;
		}
		else if ('selectionStart' in handle && handle.selectionStart > 0 && handle.value.charAt(handle.selectionStart - 1) != ' ')
			smileytext = ' ' + smileytext;

		this.insertText(smileytext);
	}
	// Otherwise we need to do a whole image...
	else
		this.insertText('<img alt="' + oSmileyProperties[0].php_htmlspecialchars() + '" class="smiley ' + oSmileyProperties[1] + '" src="' + we_theme_url + '/images/blank.gif" onresize="return false;" title="' + oSmileyProperties[2].php_htmlspecialchars() + '">');
};

weEditor.prototype.handleButtonClick = function (oButtonProperties)
{
	this.setFocus();
	var sCode = oButtonProperties[3], sText, bbcode;

	// A special Wedge function?
	if (sCode in this.oWedgeExec)
		this[this.oWedgeExec[sCode]]();

	else
	{
		// In text this is easy...
		if (!this.bRichTextEnabled)
		{
			// URL popup?
			if (sCode == 'url')
			{
				// Ask them where to link to.
				sText = prompt(oEditorStrings.prompt_text_url, 'http://');
				if (!sText)
					return;

				var sDesc = prompt(oEditorStrings.prompt_text_desc);
				bbcode = !sDesc || sDesc == '' ? '[url]' + sText + '[/url]' : '[url=' + sText + ']' + sDesc + '[/url]';
				replaceText(bbcode.replace(/\\n/g, '\n'), this.oTextHandle);
			}
			// img popup?
			else if (sCode == 'img')
			{
				// Ask them where to link to.
				sText = prompt(oEditorStrings.prompt_text_img, 'http://');
				if (!sText)
					return;

				bbcode = '[img]' + sText + '[/img]';
				replaceText(bbcode.replace(/\\n/g, '\n'), this.oTextHandle);
			}
			// Replace? (No After)
			else if (oButtonProperties[5] === '')
				replaceText(oButtonProperties[4].replace(/\\n/g, '\n'), this.oTextHandle);

			// Surround!
			else
				surroundText(oButtonProperties[4].replace(/\\n/g, '\n'), oButtonProperties[5].replace(/\\n/g, '\n'), this.oTextHandle);
		}
		else
		{
			// Is it easy?
			if (sCode in this.oSimpleExec)
				this.we_execCommand(this.oSimpleExec[sCode], false, null);

			// A link?
			else if (sCode == 'url' || sCode == 'email' || sCode == 'ftp')
				this.insertLink(sCode);

			// Maybe an image?
			else if (sCode == 'img')
				this.insertImage();

			// Everything else means doing something ourselves.
			else if (oButtonProperties[4] !== '')
				this.insertCustomHTML(oButtonProperties[4].replace(/\\n/g, '\n'), oButtonProperties[5].replace(/\\n/g, '\n'));
		}
	}

	this.updateEditorControls();

	if (this.opt.oDrafts)
		this.opt.oDrafts.needsUpdate(true);

	// Finally set the focus.
	this.setFocus();
};

// Changing a select box?
weEditor.prototype.handleSelectChange = function (oSelectProperties)
{
	this.setFocus();

	var sValue = oSelectProperties.oSelect.value;
	if (sValue == '')
		return true;

	// Changing font face?
	if (oSelectProperties[1] == 'sel_face')
	{
		if (!this.bRichTextEnabled)
		{
			sValue = sValue.replace(/"/, '');
			surroundText('[font=' + sValue + ']', '[/font]', this.oTextHandle);
			oSelectProperties.oSelect.selectedIndex = 0;
		}
		else // WYSIWYG
		{
			if (is_webkit)
				this.we_execCommand('styleWithCSS', false, true);
			this.we_execCommand('fontname', false, sValue);
		}
	}
	// Font size?
	else if (oSelectProperties[1] == 'sel_size')
	{
		if (!this.bRichTextEnabled)
		{
			surroundText('[size=' + this.aFontSizes[sValue] + 'pt]', '[/size]', this.oTextHandle);
			oSelectProperties.oSelect.selectedIndex = 0;
		}
		else // WYSIWYG
			this.we_execCommand('fontsize', false, sValue);
	}
	// Or color even?
	else if (oSelectProperties[1] == 'sel_color')
	{
		if (!this.bRichTextEnabled)
		{
			surroundText('[color=' + sValue + ']', '[/color]', this.oTextHandle);
			oSelectProperties.oSelect.selectedIndex = 0;
		}
		else // WYSIWYG
			this.we_execCommand('forecolor', false, sValue);
	}

	this.updateEditorControls();

	if (this.opt.oDrafts)
		this.opt.oDrafts.needsUpdate(true);

	return true;
};

// Put in some custom HTML.
weEditor.prototype.insertCustomHTML = function (sLeftTag, sRightTag)
{
	var sSelection = this.getSelect(true, true);
	if (sSelection.length == 0)
		sSelection = '';

	// Are we overwriting?
	if (sRightTag == '')
		this.insertText(sLeftTag);
	// If something was selected, replace and position cursor at the end of it.
	else if (sSelection.length > 0)
		this.insertText(sLeftTag + sSelection + sRightTag, false, false, 0);
	// Wrap the tags around the cursor position.
	else
		this.insertText(sLeftTag + sRightTag, false, false, sRightTag.length);
};

// Insert a URL link.
weEditor.prototype.insertLink = function (sType)
{
	var sPromptText = sType == 'email' ? oEditorStrings.prompt_text_email : (sType == 'ftp' ? oEditorStrings.prompt_text_ftp : oEditorStrings.prompt_text_url);

	// IE has a nice prompt for this - others don't.
	if (sType != 'email' && sType != 'ftp' && is_ie)
		this.we_execCommand('createlink', true, 'http://');

	else
	{
		// Ask them where to link to.
		var sText = prompt(sPromptText, sType == 'email' ? '' : (sType == 'ftp' ? 'ftp://' : 'http://'));
		if (!sText)
			return;

		if (sType == 'email' && sText.indexOf('mailto:') != 0)
			sText = 'mailto:' + sText;

		// Check if we have text selected and if not force us to have some.
		var oCurText = this.getSelect(true, true);

		if (oCurText.toString().length != 0)
		{
			this.we_execCommand('unlink');
			this.we_execCommand('createlink', false, sText);
		}
		else
			this.insertText('<a href="' + sText + '">' + sText + '</a>');
	}
};

weEditor.prototype.insertImage = function (sSrc)
{
	if (!sSrc)
	{
		sSrc = prompt(oEditorStrings.prompt_text_img, 'http://');
		if (!sSrc || sSrc.length < 10)
			return;
	}
	this.we_execCommand('insertimage', false, sSrc);
};

weEditor.prototype.getSelect = function (bWantText, bWantHTMLText)
{
	if (is_ie && 'selection' in this.oFrameDocument)
	{
		// Just want plain text?
		if (bWantText && !bWantHTMLText)
			return this.oFrameDocument.selection.createRange().text;
		// We want the HTML flavoured variety?
		else if (bWantHTMLText)
			return this.oFrameDocument.selection.createRange().htmlText;

		return this.oFrameDocument.selection;
	}

	// This is mainly Firefox.
	if ('getSelection' in this.oFrameWindow)
	{
		// Plain text?
		if (bWantText && !bWantHTMLText)
			return this.oFrameWindow.getSelection().toString();

		// HTML is harder - currently using: http://www.faqts.com/knowledge_base/view.phtml/aid/32427
		else if (bWantHTMLText)
		{
			var oSelection = this.oFrameWindow.getSelection();
			if (oSelection.rangeCount > 0)
			{
				var oDiv = this.oFrameDocument.createElement('div');
				oDiv.appendChild(oSelection.getRangeAt(0).cloneContents());
				return oDiv.innerHTML;
			}
			else
				return '';
		}

		// Want the whole object then.
		return this.oFrameWindow.getSelection();
	}

	// If we're here it's not good.
	return this.oFrameDocument.getSelection();
};

weEditor.prototype.getRange = function ()
{
	// Get the current selection.
	var oSelection = this.getSelect();

	if (!oSelection)
		return null;

	if (is_ie && oSelection.createRange)
		return oSelection.createRange();

	return oSelection.rangeCount == 0 ? null : oSelection.getRangeAt(0);
};

// Get the current element.
weEditor.prototype.getCurElement = function ()
{
	var oRange = this.getRange();

	if (!oRange)
		return null;

	if (is_ie)
	{
		if (oRange.item)
			return oRange.item(0);
		else
			return oRange.parentElement();
	}
	else
	{
		var oElement = oRange.commonAncestorContainer;
		return this.getParentElement(oElement);
	}
};

weEditor.prototype.getParentElement = function (oNode)
{
	if (oNode.nodeType == 1)
		return oNode;

	for (var i = 0; i < 50; i++)
	{
		if (!oNode.parentNode)
			break;

		oNode = oNode.parentNode;
		if (oNode.nodeType == 1)
			return oNode;
	}
	return null;
};

// Remove formatting for the selected text.
weEditor.prototype.removeFormatting = function ()
{
	// Do both at once.
	if (this.bRichTextEnabled)
	{
		this.we_execCommand('removeformat');
		this.we_execCommand('unlink');
	}
	// Otherwise do a crude move indeed.
	else
	{
		// Get the current selection first.
		var cText;
		if (this.oTextHandle.caretPos)
			cText = this.oTextHandle.caretPos.text;

		else if ('selectionStart' in this.oTextHandle)
			cText = this.oTextHandle.value.substr(this.oTextHandle.selectionStart, (this.oTextHandle.selectionEnd - this.oTextHandle.selectionStart));

		else
			return;

		// Do bits that are likely to have attributes.
		cText = cText.replace(RegExp("\\[/?(url|img|iurl|ftp|email|img|color|font|size|list|bdo).*?\\]", "g"), '');
		// Then just anything that looks like BBC.
		cText = cText.replace(RegExp("\\[/?[A-Za-z]+\\]", "g"), '');

		replaceText(cText, this.oTextHandle);
	}
};

// Upload/add a media file (picture, video...)
weEditor.prototype.addMedia = function ()
{
	reqWin(we_prepareScriptUrl() + 'action=media;sa=post;noh=' + (this.opt ? this.opt.sUniqueId : this.sUniqueId), Math.min(1000, self.screen.availWidth-50), Math.min(700, self.screen.availHeight-50), false, true, true);

	return true;
};

// Toggle wysiwyg/normal mode.
weEditor.prototype.toggleView = function (bView)
{
	if (!this.bRichTextPossible)
	{
		alert(oEditorStrings.wont_work);
		return false;
	}

	// Overriding or alternating?
	if (typeof bView == 'undefined')
		bView = !this.bRichTextEnabled;

	this.requestParsedMessage(bView);

	return true;
};

// Request the message in a different form.
weEditor.prototype.requestParsedMessage = function (bView)
{
	// Replace with a force reload.
	if (!can_ajax)
	{
		alert(oEditorStrings.func_disabled);
		return;
	}

	// Get the text.
	var sText = this.getText(true, !bView).replace(/&#/g, "&#38;#").php_urlencode();

	sendXMLDocument.call(this, we_prepareScriptUrl() + 'action=jseditor;view=' + (bView ? 1 : 0) + ';' + this.opt.sSessionVar + '=' + this.opt.sSessionId + ';xml', 'message=' + sText, this.onToggleDataReceived);
};

weEditor.prototype.onToggleDataReceived = function (oXMLDoc)
{
	var sText = '';
	for (var i = 0, j = oXMLDoc.getElementsByTagName('message')[0].childNodes.length; i < j; i++)
		sText += oXMLDoc.getElementsByTagName('message')[0].childNodes[i].nodeValue;

	// What is this new view we have?
	this.bRichTextEnabled = oXMLDoc.getElementsByTagName('message')[0].getAttribute('view') != '0';

	if (this.bRichTextEnabled)
	{
		this.oFrameHandle.style.display = '';
		if (this.showDebug)
			this.oBreadHandle.style.display = '';
		this.oTextHandle.style.display = 'none';
	}
	else
	{
		sText = sText.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
		this.oFrameHandle.style.display = 'none';
		this.oBreadHandle.style.display = 'none';
		this.oTextHandle.style.display = '';
	}

	// First we focus.
	this.setFocus();

	this.insertText(sText, true);

	// Record the new status.
	$('#' + this.opt.sUniqueId + '_mode').val(this.bRichTextEnabled ? 1 : 0);

	// Rebuild the bread crumb!
	this.updateEditorControls();
};

// Set the focus for the editing window.
weEditor.prototype.setFocus = function (force_both)
{
	if (!this.bRichTextEnabled)
		this.oTextHandle.focus();
	else if (is_ff || is_opera)
		this.oFrameHandle.focus();
	else
		this.oFrameWindow.focus();
};

// Start up the spellchecker!
weEditor.prototype.spellCheckStart = function ()
{
	if (!spellCheck)
		return false;

	// If we're in HTML mode we need to get the non-HTML text.
	if (this.bRichTextEnabled)
		sendXMLDocument.call(this, we_prepareScriptUrl() + 'action=jseditor;view=0;' + this.opt.sSessionVar + '=' + this.opt.sSessionId + ';xml', 'message=' + this.getText(true, 1).php_urlencode(), this.onSpellCheckDataReceived);
	// Otherwise start spell-checking right away.
	else
		spellCheck(this.sFormId, this.opt.sUniqueId);

	return true;
};

// This contains the spellcheckable text.
weEditor.prototype.onSpellCheckDataReceived = function (oXMLDoc)
{
	var sText = '';
	for (var i = 0; i < oXMLDoc.getElementsByTagName('message')[0].childNodes.length; i++)
		sText += oXMLDoc.getElementsByTagName('message')[0].childNodes[i].nodeValue;

	sText = sText.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');

	this.oTextHandle.value = sText;
	spellCheck(this.sFormId, this.opt.sUniqueId);
};

// Function called when the Spellchecker is finished and ready to pass back.
weEditor.prototype.spellCheckEnd = function ()
{
	// If HTML edit put the text back!
	if (this.bRichTextEnabled)
		sendXMLDocument.call(this, we_prepareScriptUrl() + 'action=jseditor;view=1;' + this.opt.sSessionVar + '=' + this.opt.sSessionId + ';xml', 'message=' + this.getText(true, 0).php_urlencode(), weEditors[this.iArrayPosition].onSpellCheckCompleteDataReceived);
	else
		this.setFocus();
};

// The corrected text.
weEditor.prototype.onSpellCheckCompleteDataReceived = function (oXMLDoc)
{
	var sText = '';
	for (var i = 0; i < oXMLDoc.getElementsByTagName('message')[0].childNodes.length; i++)
		sText += oXMLDoc.getElementsByTagName('message')[0].childNodes[i].nodeValue;

	this.insertText(sText, true);
	this.setFocus();
};

weEditor.prototype.resizeTextArea = function (newHeight, newWidth, is_change)
{
	// Work out what the new height is.
	if (is_change)
	{
		// We'll assume pixels but may not be.
		newHeight = this._calculateNewDimension(this.oTextHandle.style.height, newHeight);
		if (newWidth)
			newWidth = this._calculateNewDimension(this.oTextHandle.style.width, newWidth);
	}

	// Do the HTML editor - but only if it's enabled!
	if (this.bRichTextPossible)
	{
		this.oFrameHandle.style.height = newHeight;
		if (newWidth)
			this.oFrameHandle.style.width = newWidth;
	}
	// Do the text box regardless!
	this.oTextHandle.style.height = newHeight;
	if (newWidth)
		this.oTextHandle.style.width = newWidth;
};

// A utility instruction to save repetition when trying to work out what to change on a height/width.
weEditor.prototype._calculateNewDimension = function (old_size, change_size)
{
	// We'll assume pixels but may not be.
	var new_size, changeReg = change_size.toString().match(/(-)?(\d+)(\D*)/), curReg = old_size.toString().match(/(\d+)(\D*)/);

	if (!changeReg[3])
		changeReg[3] = 'px';

	if (changeReg[1] == '-')
		changeReg[2] = 0 - changeReg[2];

	// Both the same type?
	if (changeReg[3] == curReg[2])
	{
		new_size = parseInt(changeReg[2], 10) + parseInt(curReg[1], 10);
		if (new_size < 50)
			new_size = 50;
		new_size = new_size.toString() + changeReg[3];
	}
	// Is the change a percentage?
	else if (changeReg[3] == '%')
		new_size = (parseInt(curReg[1], 10) + parseInt((parseInt(changeReg[2], 10) * parseInt(curReg[1], 10)) / 100, 10)).toString() + 'px';
	// Otherwise just guess!
	else
		new_size = (parseInt(curReg[1], 10) + (parseInt(changeReg[2], 10) / 10)).toString() + '%';

	return new_size;
};

// Register default keyboard shortcuts.
weEditor.prototype.registerDefaultShortcuts = function ()
{
	if (!is_ff)
		return;

	this.registerShortcut('b', 'ctrl', 'b');
	this.registerShortcut('u', 'ctrl', 'u');
	this.registerShortcut('i', 'ctrl', 'i');
	this.registerShortcut('p', 'alt', 'preview');
	this.registerShortcut('s', 'alt', 'submit');
};

// Register a keyboard shortcut.
weEditor.prototype.registerShortcut = function (sLetter, sModifiers, sCodeName)
{
	if (!sCodeName)
		return;

	var oNewShortcut = {
		code: sCodeName,
		key: sLetter.toUpperCase().charCodeAt(0),
		alt: false,
		ctrl: false
	};

	var aSplitModifiers = sModifiers.split(',');
	for(var i = 0, n = aSplitModifiers.length; i < n; i++)
		if (aSplitModifiers[i] in oNewShortcut)
			oNewShortcut[aSplitModifiers[i]] = true;

	this.aKeyboardShortcuts.push(oNewShortcut);
};

// Check whether the key has triggered a shortcut?
weEditor.prototype.checkShortcut = function (oEvent)
{
	// To be a shortcut it needs to be one of these, duh!
	if (!oEvent.altKey && !oEvent.ctrlKey)
		return false;

	var sReturnCode = false;

	// Let's take a look at each of our shortcuts shall we?
	for (var i = 0, n = this.aKeyboardShortcuts.length; i < n; i++)
		if (oEvent.altKey == this.aKeyboardShortcuts[i].alt && oEvent.ctrlKey == this.aKeyboardShortcuts[i].ctrl && oEvent.which == this.aKeyboardShortcuts[i].key)
			sReturnCode = this.aKeyboardShortcuts[i].code; // Found something?

	return sReturnCode;
};

// The actual event check for the above!
weEditor.prototype.shortcutCheck = function (oEvent)
{
	var sFoundCode = this.checkShortcut(oEvent);

	// Run it and exit.
	if (typeof sFoundCode == 'string' && sFoundCode != '')
	{
		var bCancelEvent = false;
		if (sFoundCode == 'submit')
		{
			// So much to do!
			var oForm = document.getElementById(this.sFormId);
			submitThisOnce(oForm);
			submitonce();
			weSaveEntities(oForm.name, ['subject', this.opt.sUniqueId, 'guestname', 'evtitle', 'question']);
			oForm.submit();

			bCancelEvent = true;
		}
		else if (sFoundCode == 'preview')
		{
			previewPost();
			bCancelEvent = true;
		}
		else
			bCancelEvent = this.opt.oBBCBox.emulateClick(sFoundCode);

		if (bCancelEvent)
		{
			if (is_ie && oEvent.cancelBubble)
				oEvent.cancelBubble = true;

			else if (oEvent.stopPropagation)
			{
				oEvent.stopPropagation();
				oEvent.preventDefault();
			}

			return false;
		}
	}

	return true;
};

// This is the method called after clicking the resize bar.
weEditor.prototype.startResize = function (oEvent)
{
	if (!oEvent || window.weCurrentResizeEditor != null)
		return true;

	window.weCurrentResizeEditor = this.iArrayPosition;

	this.oweEditorCurrentResize.old_y = oEvent.pageY;
	this.oweEditorCurrentResize.old_rel_y = null;
	this.oweEditorCurrentResize.cur_height = parseInt(this.oTextHandle.style.height, 10);

	// Set the necessary events for resizing.
	$(is_ie ? document : window).mousemove(this.aEventWrappers.resizeOverDocument);

	if (this.bRichTextPossible)
		$(this.oFrameDocument).mousemove(this.aEventWrappers.resizeOverIframe).mouseup(this.aEventWrappers.endResize);

	$(document).mouseup(this.aEventWrappers.endResize);

	return false;
};

// This is kind of a cheat, as it only works over the IFRAME.
weEditor.prototype.resizeOverIframe = function (oEvent)
{
	if (!oEvent || window.weCurrentResizeEditor == null)
		return true;

	if (this.oweEditorCurrentResize.old_rel_y == null)
		this.oweEditorCurrentResize.old_rel_y = oEvent.pageY;
	else
	{
		var iNewHeight = oEvent.pageY - this.oweEditorCurrentResize.old_rel_y + this.oweEditorCurrentResize.cur_height;
		if (iNewHeight < 0)
			this.endResize();
		else
			this.resizeTextArea(iNewHeight + 'px', 0, false);
	}

	return false;
};

// This resizes an editor.
weEditor.prototype.resizeOverDocument = function (oEvent)
{
	if (!oEvent || window.weCurrentResizeEditor == null)
		return true;

	var iNewHeight = oEvent.pageY - this.oweEditorCurrentResize.old_y + this.oweEditorCurrentResize.cur_height;
	if (iNewHeight < 0)
		this.endResize();
	else
		this.resizeTextArea(iNewHeight + 'px', 0, false);

	return false;
};

weEditor.prototype.endResize = function (oEvent)
{
	if (window.weCurrentResizeEditor == null)
		return true;

	window.weCurrentResizeEditor = null;

	// Remove the event...
	$(is_ie ? document : window).unbind('mousemove', this.aEventWrappers.resizeOverDocument);

	if (this.bRichTextPossible)
		$(this.oFrameDocument).unbind('mousemove', this.aEventWrappers.resizeOverIframe);

	$(document).unbind('mouseup', this.aEventWrappers.endResize);

	if (this.bRichTextPossible)
		$(this.oFrameDocument).unbind('mouseup', this.aEventWrappers.endResize);

	return false;
};
