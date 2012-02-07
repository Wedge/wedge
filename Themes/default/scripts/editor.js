/*!
 * Wedge
 *
 * weEditor manages the post editor for you, both in its plain text and WYSIWTF versions.
 * (I did say WYSIWTF, no typos here.)
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function weEditor(oOptions)
{
	this.opt = oOptions;

	// Create some links to the editor object.
	this.sCurrentText = this.opt.sText || '';

	// How big?
	this.sEditWidth = this.opt.sEditWidth || '70%';
	this.sEditHeight = this.opt.sEditHeight || 150;

	this.bRichTextEnabled = !!this.opt.bWysiwyg;
	this.bRichTextPossible = !this.opt.bRichEditOff && (is_ie || is_ff || is_opera95up || is_webkit) && !(is_iphone || is_android);

	// Kinda holds all the useful stuff.
	this.aKeyboardShortcuts = [];

	// This tracks the cursor position on IE to avoid refocus problems.
	this.cursorX = this.cursorY = 0;

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

	this.sFormId = this.opt.sFormId || 'postmodify';
	this.iArrayPosition = weEditors.length;

	// Current resize state.
	this.oCurrentResize = {};

	// Define the event wrapper functions.
	var oCaller = this;
	this.aEventWrappers = {
		editorKeyUp: function (oEvent) { return oCaller.editorKeyUp(oEvent); },
		shortcutCheck: function (oEvent) { return oCaller.shortcutCheck(oEvent); },
		startResize: function (oEvent) { return oCaller.startResize(oEvent); },
		resizeOver: function (oEvent) { return oCaller.resizeOver(oEvent); },
		endResize: function (oEvent) { return oCaller.endResize(oEvent); }
	};

	// Get a reference to the textarea.
	this.oText = $('#' + this.opt.sUniqueId);

	// Ensure the currentText is set correctly depending on the mode.
	if (this.sCurrentText === '' && !this.bRichTextEnabled)
		this.sCurrentText = this.oText.html().php_unhtmlspecialchars();

	// Only try to do this if rich text is supported.
	if (this.bRichTextPossible)
	{
		// Make the iframe itself, stick it next to the current text area, and give it an ID.
		var fh = this.$Frame = $('<iframe class="rich_editor" id="html_' + this.opt.sUniqueId + '" src="about:blank" tabindex="' + this.oText[0].tabIndex + '"></iframe>')
			.width(this.sEditWidth).height(this.sEditHeight)
			.insertAfter(this.oText)
			.toggle(this.bRichTextEnabled);

		// Hide the textarea if wysiwyg is on - and vice versa.
		this.oText.toggle(!this.bRichTextEnabled);

		// Create some handy shortcuts.
		this.oFrameDoc = this.$Frame[0].contentDocument || this.$Frame[0].contentWindow.document;
		this.oFrameWindow = this.$Frame[0].contentWindow || this.oFrameDoc.parentWindow;

		// Populate the editor with nothing by default. Opera doesn't need that, but won't complain either.
		this.oFrameDoc.open();
		this.oFrameDoc.write('');
		this.oFrameDoc.close();

		// Inherit direction (LTR/RTL) from our <html> tag.
		this.$FrameBody = $(this.oFrameDoc.body);
		this.$FrameBody[0].dir = $(document).find('html')[0].dir;

		// Mark it as editable...
		if (this.$FrameBody[0].contentEditable)
			this.$FrameBody[0].contentEditable = true;
		else
		{
			this.$Frame.show();
			this.oFrameDoc.designMode = 'on';
			this.$Frame.hide();
		}

		$('link[rel=stylesheet]').each(function() { fh.contents().find('head').append($('<p>').append($(this).clone()).html()); });

		// Apply the class and set the frame padding/margin inside the editor.
		this.$FrameBody.addClass('rich_editor');

		// Attach functions to the key and mouse events.
		$(this.oFrameDoc)
			.bind('keyup mouseup', this.aEventWrappers.editorKeyUp)
			.bind('keydown', this.aEventWrappers.shortcutCheck);
		this.oText
			.bind('keydown', this.aEventWrappers.shortcutCheck)
			.bind('keydown', splitQuote);

		if (this.opt.oDrafts)
			this.oText.keyup(function () {
				oCaller.opt.oDrafts.needsUpdate(true); // This is established earlier in this function.
			});
	}
	// If we can't do advanced stuff, then just do the basics.
	else
	{
		this.bRichTextEnabled = false;
		if (this.opt.oDrafts)
			this.oText.keyup(this.opt.oDrafts.needsUpdate(true));
	}

	// Make sure we set the message mode correctly.
	$('#' + this.opt.sUniqueId + '_mode').val(+this.bRichTextEnabled);

	// Show the resizer.
	var sizer = $('#' + this.opt.sUniqueId + '_resizer');
	if (sizer.length && (!is_opera || is_opera95up))
	{
		// Currently nothing is being resized... I assume!
		window.weCurrentResizeEditor = null;
		sizer.show().bind('mousedown', this.aEventWrappers.startResize);
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
weEditor.prototype.getText = function (bPrepareEntities, bModeOverride, undefined)
{
	var sText, bCurMode = bModeOverride !== undefined ? bModeOverride : this.bRichTextEnabled;

	if (!bCurMode || !this.oFrameDoc)
	{
		sText = this.oText.val();
		if (bPrepareEntities)
			sText = sText.replace(/</g, '#welt#').replace(/>/g, '#wegt#').replace(/&/g, '#weamp#');
	}
	else
	{
		sText = this.$FrameBody.html();
		if (bPrepareEntities)
			sText = sText.replace(/&lt;/g, '#welt#').replace(/&gt;/g, '#wegt#').replace(/&amp;/g, '#weamp#');
	}

	// Clean it up - including removing semi-colons.
	if (bPrepareEntities)
		sText = sText.replace(/&nbsp;/g, ' ').replace(/;/g, '#wecol#');

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

	var
		aCrumb = [], aAllCrumbs = [],
		iMaxLength = 6, i = 0,
		that = this, sCurFontName = '', sCurFontSize = '', sCurFontColor = '', sCrumbName;

		// What is the current element?
		oCurTag = this.getCurElement(),
		array_key = function (variable, theArray)
		{
			for (i in theArray)
				if (theArray[i] == variable)
					return i;

			return null;
		};

	while (oCurTag !== null && typeof oCurTag == 'object' && oCurTag.nodeName.toLowerCase() != 'body' && i < iMaxLength)
	{
		aCrumb[i++] = oCurTag;
		oCurTag = oCurTag.parentNode;
	}

	// Now print out the tree.
	$.each(aCrumb, function ()
	{
		sCrumbName = this.nodeName.toLowerCase();

		// Does it have an alternative name?
		if (that.breadCrumbNameTags[sCrumbName])
			sCrumbName = that.breadCrumbNameTags[sCrumbName];
		// Don't bother with this...
		else if (sCrumbName == 'p')
			return;
		// A link?
		else if (sCrumbName == 'a')
		{
			sCrumbName = 'url';
			var sUrlInfo = this.getAttribute('href');
			if (sUrlInfo)
			{
				if (sUrlInfo.substr(0, 3) == 'ftp')
					sCrumbName = 'ftp';
				else if (sUrlInfo.substr(0, 6) == 'mailto')
					sCrumbName = 'email';
			}
		}
		else if (sCrumbName == 'span' || sCrumbName == 'div' || sCrumbName == 'font')
		{
			var style = this.style;
			if (style)
			{
				// Do we have a font?
				if (style.fontFamily && !sCurFontName)
					sCurFontName = style.fontFamily.replace(/^['"]/, '').replace(/['"]$/, '').toLowerCase();

				// ... or a font size?
				if (style.fontSize && !sCurFontSize)
				{
					sCurFontSize = style.fontSize.replace(/pt$/, '');
					sCurFontSize = array_key(sCurFontSize, that.aFontSizes) || sCurFontSize;
				}

				// ... even color?
				if (style.color && !sCurFontColor)
				{
					sCurFontColor = style.color;
					sCurFontColor = array_key(sCurFontColor, that.oFontColors) || sCurFontColor;
				}

				$.each([
					['textDecoration', 'underline', 'u'],
					['textDecoration', 'line-through', 's'],
					['textAlign', 'left', 'left'],
					['textAlign', 'center', 'center'],
					['textAlign', 'right', 'right'],
					['fontWeight', 'bold', 'b'],
					['fontStyle', 'italic', 'i']
				], function () {
					if (style[this[0]] == this[1])
						sCrumbName = this[2];
				});
			}
		}

		// Do we have a font?
		if (sCrumbName == 'font')
		{
			if (this.getAttribute('face') && !sCurFontName)
				sCurFontName = this.getAttribute('face').toLowerCase();

			if (this.getAttribute('size') && !sCurFontSize)
				sCurFontSize = this.getAttribute('size');

			if (this.getAttribute('color') && !sCurFontColor)
			{
				sCurFontColor = this.getAttribute('color');
				sCurFontColor = array_key(sCurFontColor, that.oFontColors) || sCurFontColor;
			}

			// Something else - ignore.
			if (!sCurFontName && !sCurFontSize && !sCurFontColor)
				return;
		}

		aAllCrumbs.push(sCrumbName);
	});

	// Since we're in WYSIWYG state, show the toggle button as active.
	aAllCrumbs.push('toggle');

	this.opt.oBBCBox.setActive(aAllCrumbs);

	// Set the correct font box values.
	this.opt.oBBCBox.setSelect('sel_face', sCurFontName);
	this.opt.oBBCBox.setSelect('sel_size', sCurFontSize);
	this.opt.oBBCBox.setSelect('sel_color', sCurFontColor);
};

// Set the HTML content to be that of the text box - if we are in wysiwyg mode.
weEditor.prototype.doSubmit = function ()
{
	if (this.bRichTextEnabled)
		this.oText.val(this.$FrameBody.html());
};


// Replaces the currently selected text with the passed text.
weEditor.prototype.replaceText = function (text)
{
	var oTextHandle = this.oText[0];

	// Attempt to create a text range (IE).
	if ('caretPos' in oTextHandle && oTextHandle.createTextRange)
	{
		var caretPos = oTextHandle.caretPos;

		caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == ' ' ? text + ' ' : text;
		caretPos.select();
	}
	// Mozilla text range replace.
	else if ('selectionStart' in oTextHandle)
	{
		var
			begin = oTextHandle.value.substr(0, oTextHandle.selectionStart),
			end = oTextHandle.value.substr(oTextHandle.selectionEnd),
			scrollPos = oTextHandle.scrollTop;

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
};

// Surrounds the selected text with text1 and text2.
weEditor.prototype.surroundText = function (text1, text2)
{
	var oTextHandle = this.oText[0];

	// Can a text range be created?
	if ('caretPos' in oTextHandle && oTextHandle.createTextRange)
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
};

// Populate the box with text.
weEditor.prototype.insertText = function (sText, bClear, bForceEntityReverse, iMoveCursorBack)
{
	// This restores welt, wegt and weamp into boring entities, to unprotect against XML'd information like quotes.
	if (bForceEntityReverse)
		sText = sText.replace(/#welt#/g, '&lt;').replace(/#wegt#/g, '&gt;').replace(/#weamp#/g, '&amp;');

	// Erase it all?
	if (bClear)
	{
		if (this.bRichTextEnabled)
		{
			this.$FrameBody.html(sText);

			// Trick the cursor into coming back!
			if (is_opera || is_ff)
			{
				// For some obscure reason, some Opera versions still require this.
				// Firefox also needs it to focus, although it doesn't actually blink.
				this.$FrameBody[0].contentEditable = false;
				this.oFrameDoc.designMode = 'off';
				this.oFrameDoc.designMode = 'on';
			}
		}
		else
			this.oText.val(sText);
	}
	else
	{
		this.setFocus();
		if (this.bRichTextEnabled)
		{
			// IE croaks if you have an image selected and try to insert!
			if (this.oFrameDoc.selection && this.oFrameDoc.selection.type != 'Text' && this.oFrameDoc.selection.type != 'None' && this.oFrameDoc.selection.clear)
				this.oFrameDoc.selection.clear();

			var oRange = this.getRange();

			if (oRange && oRange.pasteHTML)
			{
				oRange.pasteHTML(sText);

				// Do we want to move the cursor back at all?
				if (iMoveCursorBack)
					oRange.moveEnd('character', -iMoveCursorBack);

				oRange.select();
			}
			else
			{
				iMoveCursorBack = iMoveCursorBack || 0;
				this.we_execCommand('inserthtml', false, sText.substr(0, sText.length - iMoveCursorBack));

				// Does the cursor needs to be repositioned?
				if (iMoveCursorBack)
				{
					var oSelection = this.getSelect();
					oSelection.getRangeAt(0).insertNode(this.oFrameDoc.createTextNode(sText.substr(sText.length - iMoveCursorBack)));
				}
			}
		}
		else
			this.replaceText(sText);
	}

	if (this.opt.oDrafts)
		this.opt.oDrafts.needsUpdate(true);
};

// Special handler for WYSIWYG.
weEditor.prototype.we_execCommand = function (sCommand, bUi, sValue)
{
	if (this.opt.oDrafts)
		this.opt.oDrafts.needsUpdate(true);

	return this.oFrameDoc.execCommand(sCommand, bUi, sValue);
};

weEditor.prototype.insertSmiley = function (oSmileyProperties)
{
	var handle = this.oText[0], smileytext = oSmileyProperties[0];

	// In text mode we just add it in as we always did.
	if (!this.bRichTextEnabled)
	{
		if (handle.createTextRange)
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
		this.insertText('<img alt="' + oSmileyProperties[0].php_htmlspecialchars() + '" class="smiley ' + oSmileyProperties[1] + '" src="' + we_theme_url + '/images/blank.gif" onresizestart="return false;" title="' + oSmileyProperties[2].php_htmlspecialchars() + '">');
};

weEditor.prototype.handleButtonClick = function (oButtonProperties)
{
	this.setFocus();
	var sCode = oButtonProperties[3], sText, bbcode;

	// A special Wedge function?
	if (this.oWedgeExec[sCode])
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
				this.replaceText(bbcode.replace(/\\n/g, '\n'));
			}
			// img popup?
			else if (sCode == 'img')
			{
				// Ask them where to link to.
				sText = prompt(oEditorStrings.prompt_text_img, 'http://');
				if (!sText)
					return;

				bbcode = '[img]' + sText + '[/img]';
				this.replaceText(bbcode.replace(/\\n/g, '\n'));
			}
			// Replace? (No After)
			else if (oButtonProperties[5] === '')
				this.replaceText(oButtonProperties[4].replace(/\\n/g, '\n'));

			// Surround!
			else
				this.surroundText(oButtonProperties[4].replace(/\\n/g, '\n'), oButtonProperties[5].replace(/\\n/g, '\n'));
		}
		else
		{
			// Is it easy?
			if (this.oSimpleExec[sCode])
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
	var sel = $(oSelectProperties.oSelect), sValue = sel.val();
	this.setFocus();

	if (sValue == '')
		return true;

	// Changing font face?
	if (oSelectProperties[1] == 'sel_face')
	{
		if (!this.bRichTextEnabled)
		{
			sValue = sValue.replace(/"/, '');
			this.surroundText('[font=' + sValue + ']', '[/font]');
		}
		else // WYSIWYG
			this.we_execCommand('fontname', false, sValue);
	}
	// Font size?
	else if (oSelectProperties[1] == 'sel_size')
	{
		if (!this.bRichTextEnabled)
			this.surroundText('[size=' + this.aFontSizes[sValue] + 'pt]', '[/size]');
		else // WYSIWYG
			this.insertStyle({ fontSize: this.aFontSizes[sValue] + 'pt' });
	}
	// Or color even?
	else if (oSelectProperties[1] == 'sel_color')
	{
		if (!this.bRichTextEnabled)
			this.surroundText('[color=' + sValue + ']', '[/color]');
		else // WYSIWYG
			this.we_execCommand('forecolor', false, sValue);
	}

	this.updateEditorControls();

	// A hack to force removing focus from the select boxes...
	sel.prev().removeClass('focused');
	$(document).unbind('.sb');

	if (this.opt.oDrafts)
		this.opt.oDrafts.needsUpdate(true);

	return true;
};

// Insert arbitrary CSS into a Wysiwyg selection
weEditor.prototype.insertStyle = function (sCss)
{
	this.we_execCommand('fontSize', false, '7'); // Thanks to Tim Down for the concept!
	$(this.oFrameDoc).find('font[size=7]').removeAttr('size').css(sCss);
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

		if (oCurText.toString().length)
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
	// This is mainly Firefox.
	if (this.oFrameWindow.getSelection)
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
				var oDiv = this.oFrameDoc.createElement('div');
				oDiv.appendChild(oSelection.getRangeAt(0).cloneContents());
				return oDiv.innerHTML;
			}
			else
				return '';
		}

		// Want the whole object then.
		return this.oFrameWindow.getSelection();
	}

	if (this.oFrameDoc.selection) // IE?
	{
		// Just want plain text?
		if (bWantText && !bWantHTMLText)
			return this.oFrameDoc.selection.createRange().text;
		// We want the HTML flavoured variety?
		else if (bWantHTMLText)
			return this.oFrameDoc.selection.createRange().htmlText;

		return this.oFrameDoc.selection;
	}

	// If we're here it's not good.
	return this.oFrameDoc.getSelection();
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
	do {
		if (oNode.nodeType == 1)
			return oNode;
	} while (oNode = oNode.parentNode)

	return null;
};

// Remove formatting for the selected text.
weEditor.prototype.removeFormatting = function ()
{
	// Do both at once.
	if (this.bRichTextEnabled)
	{
		this.we_execCommand('removeFormat');
		this.we_execCommand('unlink');
	}
	// Otherwise do a crude move indeed.
	else
	{
		// Get the current selection first.
		var cText;

		if (this.oText[0].caretPos)
			cText = this.oText[0].caretPos.text;

		else if ('selectionStart' in this.oText[0])
			cText = this.oText[0].value.substr(this.oText[0].selectionStart, (this.oText[0].selectionEnd - this.oText[0].selectionStart));

		else
			return;

		// Do bits that are likely to have attributes.
		cText = cText.replace(RegExp("\\[/?(url|img|iurl|ftp|email|img|color|font|size|list|bdo).*?\\]", 'g'), '');
		// Then just anything that looks like BBC.
		cText = cText.replace(RegExp("\\[/?[A-Za-z]+\\]", 'g'), '');

		this.replaceText(cText);
	}
};

// Upload/add a media file (picture, video...)
weEditor.prototype.addMedia = function ()
{
	reqWin(we_prepareScriptUrl() + 'action=media;sa=post;noh=' + this.opt.sUniqueId, Math.min(1000, self.screen.availWidth - 50), Math.min(700, self.screen.availHeight - 50), false, true, true);
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
	bView = bView || !this.bRichTextEnabled;

	// Request the message in a different form.
	// Replace with a force reload.
	if (!can_ajax)
	{
		alert(oEditorStrings.func_disabled);
		return;
	}

	// Get the text.
	var sText = this.getText(true, !bView).replace(/&#/g, '&#38;#').php_urlencode();

	sendXMLDocument.call(this, we_prepareScriptUrl() + 'action=jseditor;view=' + +bView + ';' + we_sessvar + '=' + we_sessid + ';xml', 'message=' + sText, this.onToggleDataReceived);
};

weEditor.prototype.onToggleDataReceived = function (oXMLDoc)
{
	var sText = '';
	$.each(oXMLDoc.getElementsByTagName('message')[0].childNodes || [], function () { sText += this.nodeValue; });

	// What is this new view we have?
	this.bRichTextEnabled = oXMLDoc.getElementsByTagName('message')[0].getAttribute('view') != '0';

	if (this.bRichTextEnabled)
	{
		this.$Frame.show();
		this.oText.hide();
	}
	else
	{
		sText = sText.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
		this.$Frame.hide();
		this.oText.show();
	}

	// First we focus.
	this.setFocus();

	this.insertText(sText, true);

	// Record the new status.
	$('#' + this.opt.sUniqueId + '_mode').val(+this.bRichTextEnabled);

	// Rebuild the bread crumb!
	this.updateEditorControls();
};

// Set the focus for the editing window.
weEditor.prototype.setFocus = function ()
{
	if (!this.bRichTextEnabled)
		this.oText[0].focus();
	else if (is_ff || is_opera)
		this.$Frame[0].focus();
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
		sendXMLDocument.call(this, we_prepareScriptUrl() + 'action=jseditor;view=0;' + we_sessvar + '=' + we_sessid + ';xml', 'message=' + this.getText(true, 1).php_urlencode(), this.onSpellCheckDataReceived);
	// Otherwise start spell-checking right away.
	else
		spellCheck(this.sFormId, this.opt.sUniqueId);

	return true;
};

// This contains the spellcheckable text.
weEditor.prototype.onSpellCheckDataReceived = function (oXMLDoc)
{
	var sText = '';
	$.each(oXMLDoc.getElementsByTagName('message')[0].childNodes || [], function () { sText += this.nodeValue; });

	sText = sText.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');

	this.oText.val(sText);
	spellCheck(this.sFormId, this.opt.sUniqueId);
};

// Function called when the Spellchecker is finished and ready to pass back.
weEditor.prototype.spellCheckEnd = function ()
{
	// If HTML edit put the text back!
	if (this.bRichTextEnabled)
		sendXMLDocument.call(this, we_prepareScriptUrl() + 'action=jseditor;view=1;' + we_sessvar + '=' + we_sessid + ';xml', 'message=' + this.getText(true, 0).php_urlencode(), weEditors[this.iArrayPosition].onSpellCheckCompleteDataReceived);
	else
		this.setFocus();
};

// The corrected text.
weEditor.prototype.onSpellCheckCompleteDataReceived = function (oXMLDoc)
{
	var sText = '';
	$.each(oXMLDoc.getElementsByTagName('message')[0].childNodes || [], function () { sText += this.nodeValue; });

	this.insertText(sText, true);
	this.setFocus();
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

	$.each(sModifiers.split(','), function () {
		if (this in oNewShortcut)
			oNewShortcut[this] = true;
	});

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
	$.each(this.aKeyboardShortcuts, function () {
		if (oEvent.altKey == this.alt && oEvent.ctrlKey == this.ctrl && oEvent.which == this.key)
			sReturnCode = this.code; // Found something?
	});

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

	this.oCurrentResize.old_y = oEvent.pageY;
	this.oCurrentResize.cur_height = this.oText.height();

	// Set the necessary events for resizing.
	$(document)
		.bind('mousemove', this.aEventWrappers.resizeOver)
		.bind('mouseup', this.aEventWrappers.endResize);

	return false;
};

weEditor.prototype.resizeTextArea = function (newHeight)
{
	newHeight = Math.max(30, newHeight);

	// Do the HTML editor - but only if it's enabled!
	if (this.bRichTextPossible)
		this.$Frame.height(newHeight);

	// Do the text box regardless!
	this.oText.height(newHeight);
};

// This resizes an editor.
weEditor.prototype.resizeOver = function (oEvent)
{
	if (!oEvent || window.weCurrentResizeEditor == null)
		return true;

	this.resizeTextArea(oEvent.pageY - this.oCurrentResize.old_y + this.oCurrentResize.cur_height);

	return false;
};

weEditor.prototype.endResize = function (oEvent)
{
	if (window.weCurrentResizeEditor == null)
		return true;

	window.weCurrentResizeEditor = null;

	// Remove the event...
	$(document)
		.unbind('mousemove', this.aEventWrappers.resizeOver)
		.unbind('mouseup', this.aEventWrappers.endResize);

	return false;
};
