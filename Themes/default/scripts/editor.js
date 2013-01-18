/*!
 * Wedge
 *
 * weEditor manages the post editor for you, both in its plain text and WYSIWTF versions.
 * (I did say WYSIWTF, no typos here.)
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

@language index, Post;

function weEditor(opt)
{
	/**
	 * Global and local variables
	 */

	this.bRichTextEnabled = !!opt.bWysiwyg;
	this.bRichTextPossible = !opt.bRichEditOff && (is_ie || is_ff || is_opera || is_webkit) && !(is_ios || is_android);
	this.urlTxt = $txt['prompt_text_url'];
	this.imgTxt = $txt['prompt_text_img'];

	// Create some links to the editor object.
	var
		sCurrentText = opt.sText || '',
		aKeyboardShortcuts = [],
		sFormId = opt.sFormId || 'postmodify',

		// Current resize state.
		oCurrentResize = {},

		// Define the event wrapper functions.
		that = this,

		// Get a reference to the textarea.
		oText = $('#' + opt.sUniqueId);

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

	/**
	 * Public functions
	 */

	// Return the current text.
	this.getText = function (bPrepareEntities, bModeOverride, undefined)
	{
		var sText, bCurMode = bModeOverride !== undefined ? bModeOverride : this.bRichTextEnabled;

		if (!bCurMode || !oFrameDoc)
		{
			sText = oText.val();
			if (bPrepareEntities)
				sText = sText.replace(/</g, '#welt#').replace(/>/g, '#wegt#').replace(/&/g, '#weamp#');
		}
		else
		{
			sText = $FrameBody.html();
			if (bPrepareEntities)
				sText = sText.replace(/&lt;/g, '#welt#').replace(/&gt;/g, '#wegt#').replace(/&amp;/g, '#weamp#');
		}

		// Clean it up - including removing semi-colons.
		if (bPrepareEntities)
			sText = sText.replace(/&nbsp;/g, ' ').replace(/;/g, '#wecol#');

		// Return it.
		return sText;
	};

	// Rebuild the breadcrumb etc - and set things to the correct context.
	this.updateEditorControls = function ()
	{
		// Everything else is specific to HTML mode.
		if (!this.bRichTextEnabled)
		{
			// Set none of the buttons active.
			if (opt.oBBCBox)
				opt.oBBCBox.setActive([]);
			return;
		}

		var
			aCrumb = [], aAllCrumbs = [],
			iMaxLength = 6, i = 0,
			sCurFontName = '', sCurFontSize = '', sCurFontColor = '', sCrumbName,

			// What is the current element?
			oCurTag = getCurElement(),
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
			if (sCrumbName == 'strike')
				sCrumbName = 's';
			else if (sCrumbName == 'strong')
				sCrumbName = 'b';
			else if (sCrumbName == 'em')
				sCrumbName = 'i';
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
					if (sUrlInfo.slice(0, 3) == 'ftp')
						sCrumbName = 'ftp';
					else if (sUrlInfo.slice(0, 6) == 'mailto')
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

					if (style.textDecoration == 'underline')	sCrumbName = 'u';
					if (style.textDecoration == 'line-through')	sCrumbName = 's';
					if (style.textAlign == 'left')				sCrumbName = 'left';
					if (style.textAlign == 'center')			sCrumbName = 'center';
					if (style.textAlign == 'right')				sCrumbName = 'right';
					if (style.fontWeight == 'bold')				sCrumbName = 'b';
					if (style.fontStyle == 'italic')			sCrumbName = 'i';
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

		opt.oBBCBox.setActive(aAllCrumbs);

		// Set the correct font box values.
		opt.oBBCBox.setSelect('sel_face', sCurFontName);
		opt.oBBCBox.setSelect('sel_size', sCurFontSize);
		opt.oBBCBox.setSelect('sel_color', sCurFontColor);
	};

	// Set the HTML content to be that of the text box - if we are in wysiwyg mode.
	this.doSubmit = function ()
	{
		if (this.bRichTextEnabled)
			oText.val($FrameBody.html());
	};


	// Replaces the currently selected text with the passed text.
	this.replaceText = function (text)
	{
		var oTextHandle = oText[0];

		// Attempt to create a text range (IE).
		if ('caretPos' in oTextHandle && oTextHandle.createTextRange)
		{
			var caretPos = oTextHandle.caretPos;

			caretPos.text = caretPos.text.match(/ $/) ? text + ' ' : text;
			caretPos.select();
		}
		// Mozilla text range replace.
		else if ('selectionStart' in oTextHandle)
		{
			var
				begin = oTextHandle.value.slice(0, oTextHandle.selectionStart),
				scrollPos = oTextHandle.scrollTop;

			oTextHandle.value = begin + text + oTextHandle.value.slice(oTextHandle.selectionEnd);

			if (oTextHandle.setSelectionRange)
			{
				oTextHandle.focus();
				oTextHandle.setSelectionRange(begin.length + text.length, begin.length + text.length);
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
	this.surroundText = function (text1, text2)
	{
		var oTextHandle = oText[0];

		// Can a text range be created? (IE version.)
		if ('caretPos' in oTextHandle && oTextHandle.createTextRange)
		{
			var caretPos = oTextHandle.caretPos, temp_length = caretPos.text.length;

			caretPos.text = caretPos.text.match(/ $/) ? text1 + caretPos.text + text2 + ' ' : text1 + caretPos.text + text2;

			if (!temp_length)
			{
				caretPos.moveStart('character', -text2.length);
				caretPos.moveEnd('character', -text2.length);
				caretPos.select();
			}
			else
				oTextHandle.focus(caretPos);
		}
		// Mozilla text range wrap. (Standards version.)
		else if ('selectionStart' in oTextHandle)
		{
			var
				selectionStart = oTextHandle.selectionStart,
				selectionLength = oTextHandle.selectionEnd - selectionStart,
				scrollPos = oTextHandle.scrollTop;

			// This is where the insertion actually happens.
			oTextHandle.value =
				oTextHandle.value.slice(0, selectionStart) + text1
				+ oTextHandle.value.slice(selectionStart, oTextHandle.selectionEnd) + text2
				+ oTextHandle.value.slice(oTextHandle.selectionEnd);

			// The selection values are now reset, so we'll have to use the ones we cached in the above var.
			if (oTextHandle.setSelectionRange)
			{
				if (!selectionLength)
					oTextHandle.setSelectionRange(selectionStart + text1.length, selectionStart + text1.length);
				else
					oTextHandle.setSelectionRange(selectionStart, selectionStart + text1.length + text2.length + selectionLength);
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
	this.insertText = function (sText, bClear, bForceEntityReverse, iMoveCursorBack)
	{
		// This restores welt, wegt and weamp into boring entities, to unprotect against XML'd information like quotes.
		if (bForceEntityReverse)
			sText = sText.replace(/#welt#/g, '&lt;').replace(/#wegt#/g, '&gt;').replace(/#weamp#/g, '&amp;');

		// Erase it all?
		if (bClear)
		{
			if (this.bRichTextEnabled)
			{
				$FrameBody.html(sText);

				// Trick the cursor into coming back!
				if (is_opera || is_ff)
				{
					// For some obscure reason, some Opera versions still require this.
					// Firefox also needs it to focus, although it doesn't actually blink.
					$FrameBody[0].contentEditable = false;
					oFrameDoc.designMode = 'off';
					oFrameDoc.designMode = 'on';
				}
			}
			else
				oText.val(sText);
		}
		else
		{
			this.setFocus();
			if (this.bRichTextEnabled)
			{
				// IE croaks if you have an image selected and try to insert!
				if (oFrameDoc.selection && oFrameDoc.selection.type != 'Text' && oFrameDoc.selection.type != 'None' && oFrameDoc.selection.clear)
					oFrameDoc.selection.clear();

				var oRange = getRange();

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
					this.we_execCommand('inserthtml', false, sText.slice(0, -iMoveCursorBack));

					// Does the cursor needs to be repositioned?
					if (iMoveCursorBack)
					{
						var oSelection = getSelect();
						oSelection.getRangeAt(0).insertNode(oFrameDoc.createTextNode(sText.slice(-iMoveCursorBack)));
					}
				}
			}
			else
				this.replaceText(sText);
		}

		if (opt.oDrafts)
			opt.oDrafts.needsUpdate(true);
	};

	// Special handler for WYSIWYG.
	this.we_execCommand = function (sCommand, bUi, sValue)
	{
		if (opt.oDrafts)
			opt.oDrafts.needsUpdate(true);

		return oFrameDoc.execCommand(sCommand, bUi, sValue);
	};

	this.insertSmiley = function (oSmileyProperties)
	{
		var handle = oText[0], smileytext = oSmileyProperties[0];

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
			else if (handle.selectionStart > 0 && handle.value.charAt(handle.selectionStart - 1) != ' ')
				smileytext = ' ' + smileytext;

			this.insertText(smileytext);
		}
		// Otherwise we need to do a whole image...
		else
			this.insertText('<img alt="' + oSmileyProperties[0].php_htmlspecialchars() + '" class="smiley ' + oSmileyProperties[1] + '" src="' + we_theme_url + '/images/blank.gif" onresizestart="return false;" title="' + oSmileyProperties[2].php_htmlspecialchars() + '">');
	};

	this.handleButtonClick = function (oButtonProperties)
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
					sText = prompt(this.urlTxt, 'http://');
					if (!sText)
						return;

					var sDesc = prompt($txt['prompt_text_desc']);
					bbcode = !sDesc || sDesc == '' ? '[url]' + sText + '[/url]' : '[url=' + sText + ']' + sDesc + '[/url]';
					this.replaceText(bbcode.replace(/\\n/g, '\n'));
				}
				// img popup?
				else if (sCode == 'img')
				{
					// Ask them where to link to.
					sText = prompt(this.imgTxt, 'http://');
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

		if (opt.oDrafts)
			opt.oDrafts.needsUpdate(true);

		// Finally set the focus.
		this.setFocus();
	};

	// Changing a select box?
	this.handleSelectChange = function (oSelectProperties)
	{
		var sel = $(oSelectProperties.oSelect), sValue = sel.val();
		this.setFocus();

		if (sValue == '')
			return true;

		// Changing font face?
		if (oSelectProperties[1] == 'sel_face')
		{
			if (!this.bRichTextEnabled)
				this.surroundText('[font=' + sValue.replace(/"/, '') + ']', '[/font]');
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

		if (!this.bRichTextEnabled)
			sel.attr('selectedIndex', 0).sb();

		this.updateEditorControls();

		// A hack to force removing focus from the select boxes...
		sel.prev().removeClass('focused');
		$(document).unbind('.sb');

		if (opt.oDrafts)
			opt.oDrafts.needsUpdate(true);

		return true;
	};

	// Insert arbitrary CSS into a Wysiwyg selection
	this.insertStyle = function (sCss)
	{
		this.we_execCommand('fontSize', false, '7'); // Thanks to Tim Down for the concept!
		$(oFrameDoc).find('font[size=7]').removeAttr('size').css(sCss);
	};

	// Put in some custom HTML.
	this.insertCustomHTML = function (sLeftTag, sRightTag)
	{
		var sSelection = getSelect(true, true);

		if (!sSelection.length)
			sSelection = '';

		// Are we overwriting?
		if (sRightTag == '')
			this.insertText(sLeftTag);
		// If something was selected, replace and position cursor at the end of it.
		else if (sSelection.length)
			this.insertText(sLeftTag + sSelection + sRightTag, false, false, 0);
		// Wrap the tags around the cursor position.
		else
			this.insertText(sLeftTag + sRightTag, false, false, sRightTag.length);
	};

	// Insert a URL link.
	this.insertLink = function (sType)
	{
		var sPromptText = sType == 'email' ? $txt['prompt_text_email'] : (sType == 'ftp' ? $txt['prompt_text_ftp'] : this.urlTxt);

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
			var oCurText = getSelect(true, true);

			if (oCurText.toString().length)
			{
				this.we_execCommand('unlink');
				this.we_execCommand('createlink', false, sText);
			}
			else
				this.insertText('<a href="' + sText + '">' + sText + '</a>');
		}
	};

	this.insertImage = function (sSrc)
	{
		if (!sSrc)
		{
			sSrc = prompt(this.imgTxt, 'http://');
			if (!sSrc || sSrc.length < 10)
				return;
		}
		this.we_execCommand('insertimage', false, sSrc);
	};

	// Remove formatting for the selected text.
	this.removeFormatting = function ()
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

			if (oText[0].caretPos)
				cText = oText[0].caretPos.text;

			else if ('selectionStart' in oText[0])
				cText = oText[0].value.slice(oText[0].selectionStart, oText[0].selectionEnd);

			else
				return;

			cText = cText
				// Do bits that are likely to have attributes.
				.replace(RegExp("\\[/?(url|img|iurl|ftp|email|img|color|font|size|list|bdo).*?\\]", 'g'), '')
				// Then just anything that looks like BBC.
				.replace(RegExp("\\[/?[A-Za-z]+\\]", 'g'), '');

			this.replaceText(cText);
		}
	};

	// Upload/add a media file (picture, video...)
	// !! Integrate this cleanly.
	this.addMedia = function ()
	{
		open(weUrl('action=media;sa=post;noh=' + opt.sUniqueId), 'media', 'toolbar=no,titlebar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=' + Math.min(1000, screen.availWidth - 50) + ',height=' + Math.min(700, screen.availHeight - 50));
	};

	// Toggle wysiwyg/normal mode.
	this.toggleView = function (bView)
	{
		if (!this.bRichTextPossible)
		{
			say($txt['rich_edit_wont_work']);
			return false;
		}

		// Overriding or alternating?
		bView |= !this.bRichTextEnabled;

		$.ajax(
			weUrl('action=jseditor;xml;view=' + +bView + ';' + we_sessvar + '=' + we_sessid),
			{
				data: { message: this.getText(true, !bView) },
				context: this,
				type: 'POST',
				success: function (oXMLDoc)
				{
					var sText = $('message', oXMLDoc).text();

					// What is this new view we have?
					this.bRichTextEnabled = $('message', oXMLDoc).attr('view') != '0';

					if (this.bRichTextEnabled)
					{
						$Frame.show();
						oText.hide();
					}
					else
					{
						sText = sText.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
						$Frame.hide();
						oText.show();
					}

					// First we focus.
					this.setFocus();

					this.insertText(sText, true);

					// Record the new status.
					$('#' + opt.sUniqueId + '_mode').val(+this.bRichTextEnabled);

					// Rebuild the bread crumb!
					this.updateEditorControls();
				}
			}
		);
	};

	// Set the focus for the editing window.
	this.setFocus = function ()
	{
		if (!this.bRichTextEnabled)
			oText[0].focus();
		else if (is_ff || is_opera)
			$Frame[0].focus();
		else
			oFrameWindow.focus();
	};

	// Start up the spellchecker!
	this.spellCheckStart = function ()
	{
		if (!spellCheck)
			return false;

		// If we're in HTML mode we need to get the non-HTML text.
		if (this.bRichTextEnabled)
			$.ajax(
				weUrl('action=jseditor;xml;view=0;' + we_sessvar + '=' + we_sessid),
				{
					data: { message: this.getText(true, 1) },
					context: this,
					type: 'POST',
					success: function (oXMLDoc)
					{
						// The spellcheckable text.
						var sText = $('message', oXMLDoc).text();
						oText.val(sText.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&'));
						spellCheck(sFormId, opt.sUniqueId);
					}
				}
			);
		// Otherwise start spell-checking right away.
		else
			spellCheck(sFormId, opt.sUniqueId);

		return true;
	};

	// Function called when the Spellchecker is finished and ready to pass back.
	this.spellCheckEnd = function ()
	{
		// If HTML edit put the text back!
		if (this.bRichTextEnabled)
			$.ajax(
				weUrl('action=jseditor;xml;view=1;' + we_sessvar + '=' + we_sessid),
				{
					data: { message: this.getText(true, 0) },
					context: this,
					type: 'POST',
					success: function (oXMLDoc)
					{
						// The corrected text.
						var sText = $('message', oXMLDoc).text();
						this.insertText(sText, true);
						this.setFocus();
					}
				}
			);
		else
			this.setFocus();
	};

	// Register a keyboard shortcut. ('s', 'ctrl,alt', 'submit') => ctrl+alt+s calls shortcutCheck with the 'submit' param.
	this.registerShortcut = function (sLetter, sModifiers, sCodeName)
	{
		var oNewShortcut = {
			code: sCodeName,
			key: sLetter.toUpperCase().charCodeAt(0),
			ctrl: false,
			alt: false
		};

		$.each(sModifiers.split(','), function () { oNewShortcut[this] = true; });

		aKeyboardShortcuts.push(oNewShortcut);
	};

	/**
	 * Private functions
	 */

	var getSelect = function (bWantText, bWantHTMLText)
	{
		// This is mainly Firefox.
		if (oFrameWindow.getSelection)
		{
			// Plain text?
			if (bWantText && !bWantHTMLText)
				return oFrameWindow.getSelection().toString();

			// HTML is harder - currently using: http://www.faqts.com/knowledge_base/view.phtml/aid/32427
			else if (bWantHTMLText)
			{
				var oSelection = oFrameWindow.getSelection();

				if (oSelection.rangeCount > 0)
				{
					var oDiv = oFrameDoc.createElement('div');
					oDiv.appendChild(oSelection.getRangeAt(0).cloneContents());
					return oDiv.innerHTML;
				}
				else
					return '';
			}

			// Want the whole object then.
			return oFrameWindow.getSelection();
		}

		if (oFrameDoc.selection) // IE?
		{
			// Just want plain text?
			if (bWantText && !bWantHTMLText)
				return oFrameDoc.selection.createRange().text;
			// We want the HTML flavoured variety?
			else if (bWantHTMLText)
				return oFrameDoc.selection.createRange().htmlText;

			return oFrameDoc.selection;
		}

		// If we're here it's not good.
		return oFrameDoc.getSelection();
	},

	getRange = function ()
	{
		// Get the current selection.
		var oSelection = getSelect();

		if (!oSelection)
			return null;

		if (is_ie && oSelection.createRange)
			return oSelection.createRange();

		return !oSelection.rangeCount ? null : oSelection.getRangeAt(0);
	},

	// Get the current element.
	getCurElement = function ()
	{
		var oRange = getRange(), oElement;

		if (!oRange)
			return null;

		if (oElement = oRange.commonAncestorContainer)
		{
			do {
				if (oElement.nodeType == 1)
					return oElement;
			} while (oElement = oElement.parentNode)

			return null;
		}
		else // IE?
			return oRange.item ? oRange.item(0) : oRange.parentElement();
	},

	// The actual event check for the above!
	shortcutCheck = function (oEvent)
	{
		var sFoundCode;

		// Check whether the key has triggered a shortcut?
		if (oEvent.altKey || oEvent.ctrlKey)
			$.each(aKeyboardShortcuts, function () {
				if (oEvent.altKey == this.alt && oEvent.ctrlKey == this.ctrl && oEvent.which == this.key)
					sFoundCode = this.code; // Found something?
			});

		// Trigger corresponding buttons and prevent default keyboard action if found.
		if (sFoundCode == 'submit')
		{
			$('#' + sFormId + ' input[name=post_button]').click();
			return false;
		}
		else if (sFoundCode == 'draft')
		{
			$('#' + sFormId + ' input[name=draft]').click();
			return false;
		}
		else if (sFoundCode == 'preview')
		{
			// - This doesn't save entities, i.e. &# stuff won't show up the same way as in the final post.
			// - No need to trigger the click, because e.ctrlKey was already rejected at this point.
			previewPost();
			return false;
		}
		else if (sFoundCode)
			return opt.oBBCBox.emulateClick(sFoundCode);
	},

	// This resizes an editor.
	resizeOver = function (oEvent)
	{
		if (!oEvent)
			return true;

		var newHeight = Math.max(30, oEvent.pageY - oCurrentResize.old_y + oCurrentResize.cur_height);

		// Do the HTML editor - but only if it's enabled!
		if (that.bRichTextPossible)
			$Frame.height(newHeight);

		// Do the text box regardless!
		oText.height(newHeight);

		return false;
	},

	endResize = function (oEvent)
	{
		// Remove the event...
		$(document)
			.unbind('mousemove', resizeOver)
			.unbind('mouseup', endResize);

		return false;
	};

	/**
	 * Initialize
	 */

	// Ensure the currentText is set correctly depending on the mode.
	if (sCurrentText === '' && !this.bRichTextEnabled)
		sCurrentText = oText.html().php_unhtmlspecialchars();

	// Only try to do this if rich text is supported.
	if (this.bRichTextPossible)
	{
		// Make the iframe itself, give it its proper dimensions, stick it next to the current text area, and give it an ID.
		var $Frame = $('<iframe class="rich_editor" id="html_' + opt.sUniqueId + '" src="about:blank" tabindex="' + oText[0].tabIndex + '"></iframe>')
			.width(opt.sEditWidth || '70%')
			.height(opt.sEditHeight || 150)
			.insertAfter(oText)
			.toggle(this.bRichTextEnabled);

		// Hide the textarea if wysiwyg is on - and vice versa.
		oText.toggle(!this.bRichTextEnabled);

		// Create some handy shortcuts.
		var
			oFrameDoc = $Frame[0].contentDocument || $Frame[0].contentWindow.document,
			oFrameWindow = $Frame[0].contentWindow || oFrameDoc.parentWindow;

		// Populate the editor with nothing by default. Opera doesn't need that, but won't complain either.
		oFrameDoc.open();
		oFrameDoc.write('');
		oFrameDoc.close();

		// Inherit direction (LTR/RTL) from our <html> tag.
		var $FrameBody = $(oFrameDoc.body);
		$FrameBody[0].dir = $(document).find('html')[0].dir;

		// Mark it as editable...
		if ($FrameBody[0].contentEditable)
			$FrameBody[0].contentEditable = true;
		else
		{
			$Frame.show();
			oFrameDoc.designMode = 'on';
			$Frame.hide();
		}

		$('link[rel=stylesheet]').each(function() { $Frame.contents().find('head').append($('<p>').append($(this).clone()).html()); });

		// Apply the class and set the frame padding/margin inside the editor.
		$FrameBody.addClass('rich_editor');

		// Attach functions to the key and mouse events.
		$(oFrameDoc)
			.bind('keydown', shortcutCheck)
			.bind('keyup mouseup', function ()
			{
				if (opt.oDrafts)
					opt.oDrafts.needsUpdate(true);

				// Rebuild the breadcrumb.
				that.updateEditorControls();
			});
		oText
			.bind('keydown', shortcutCheck)
			.bind('keydown', splitQuote)[0].instanceRef = this;
	}
	// If we can't do advanced stuff, then just do the basics.
	else
		this.bRichTextEnabled = false;

	if (opt.oDrafts)
		oText.keyup(function () {
			opt.oDrafts.needsUpdate(true);
		});

	// Make sure we set the message mode correctly.
	$('#' + opt.sUniqueId + '_mode').val(+this.bRichTextEnabled);

	// Show the resizer.
	var sizer = $('#' + opt.sUniqueId + '_resizer');
	if (sizer.length)
	{
		sizer.show().bind('mousedown', function (oEvent) {
			// This is the method called after clicking the resize bar.
			if (!oEvent)
				return true;

			oCurrentResize.old_y = oEvent.pageY;
			oCurrentResize.cur_height = oText.height();

			// Set the necessary events for resizing.
			$(document)
				.bind('mousemove', resizeOver)
				.bind('mouseup', endResize);

			return false;
		});
	}

	// Set the text - if WYSIWYG is enabled that is.
	if (this.bRichTextEnabled)
	{
		this.insertText(sCurrentText, true);

		// Better make us the focus!
		this.setFocus();
	}

	// Finally, register shortcuts.
	// Register default keyboard shortcuts.
	this.registerShortcut('b', 'ctrl', 'b');
	this.registerShortcut('u', 'ctrl', 'u');
	this.registerShortcut('i', 'ctrl', 'i');
	this.registerShortcut('p', 'alt', 'preview');
	this.registerShortcut('s', 'alt', 'submit');
	this.registerShortcut('d', 'alt', 'draft');

	this.updateEditorControls();
}
