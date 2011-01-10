/*!
 * This file is under the SMF license.
 * All code changes compared against SMF 2.0 are protected
 * by the Wedge license, http://wedgeforum.com/license/
 */

var
	_formSubmitted = false,
	_lastKeepAliveCheck = new Date().getTime(),
	_ajax_indicator_ele = null,
	smf_editorArray = [],

	// Basic browser detection
	ua = navigator.userAgent.toLowerCase(),
	vers = $.browser.version,

	// If you need support for more versions, just test for $.browser.version yourself...
	is_opera = $.browser.opera, is_opera95up = is_opera && vers >= 9.5,
	is_ff = ua.indexOf('gecko/') != -1 && ua.indexOf('like gecko') == -1 && !is_opera, is_gecko = !is_opera && ua.indexOf('gecko') != -1,
	is_webkit = $.browser.webkit, is_chrome = ua.indexOf('chrome') != -1, is_iphone = is_webkit && ua.indexOf('iphone') != -1 || ua.indexOf('ipod') != -1,
	is_android = is_webkit && ua.indexOf('android') != -1, is_safari = is_webkit && !is_chrome && !is_iphone && !is_android,
	is_ie = $.browser.msie && !is_opera, is_ie6 = is_ie && vers == 6, is_ie7 = is_ie && vers == 7,
	is_ie8 = is_ie && vers == 8, is_ie9up = is_ie && vers >= 9;

// Load an XML document using XMLHttpRequest.
function getXMLDocument(sUrl, funcCallback)
{
	return $.ajax(typeof funcCallback != 'undefined' ?
		{ url: sUrl, success: funcCallback, context: this } :
		{ url: sUrl, async: false, context: this }
	);
}

// Send a post form to the server using XMLHttpRequest.
function sendXMLDocument(sUrl, sContent, funcCallback)
{
	$.ajax(typeof funcCallback != 'undefined' ?
		{ url: sUrl, data: sContent, type: 'POST', context: this, success: funcCallback } :
		{ url: sUrl, data: sContent, type: 'POST', context: this }
	);
	return true;
}

// Convert a string to an 8 bit representation (like in PHP).
String.prototype.php_to8bit = function ()
{
	var n, sReturn = '', c = String.fromCharCode;

	for (var i = 0, iTextLen = this.length; i < iTextLen; i++)
	{
		n = this.charCodeAt(i);
		if (n < 128)
			sReturn += c(n);
		else if (n < 2048)
			sReturn += c(192 | n >> 6) + c(128 | n & 63);
		else if (n < 65536)
			sReturn += c(224 | n >> 12) + c(128 | n >> 6 & 63) + c(128 | n & 63);
		else
			sReturn += c(240 | n >> 18) + c(128 | n >> 12 & 63) + c(128 | n >> 6 & 63) + c(128 | n & 63);
	}

	return sReturn;
};

// Character-level replacement function.
String.prototype.php_strtr = function (sFrom, sTo)
{
	return this.replace(new RegExp('[' + sFrom + ']', 'g'), function (sMatch) {
		return sTo.charAt(sFrom.indexOf(sMatch));
	});
};

// Simulate PHP's strtolower (in SOME cases, PHP uses ISO-8859-1 case folding.)
String.prototype.php_strtolower = function ()
{
	return typeof smf_iso_case_folding == 'boolean' && smf_iso_case_folding == true ? this.php_strtr(
		'ABCDEFGHIJKLMNOPQRSTUVWXYZ\x8a\x8c\x8e\x9f\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde',
		'abcdefghijklmnopqrstuvwxyz\x9a\x9c\x9e\xff\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe'
	) : this.php_strtr('ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
};

String.prototype.php_urlencode = function ()
{
	return escape(this).replace(/\+/g, '%2b').replace('*', '%2a').replace('/', '%2f').replace('@', '%40');
};

String.prototype.php_htmlspecialchars = function ()
{
	return this.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
};

String.prototype.php_unhtmlspecialchars = function ()
{
	return this.replace(/&quot;/g, '"').replace(/&gt;/g, '>').replace(/&lt;/g, '<').replace(/&amp;/g, '&');
};

String.prototype.php_addslashes = function ()
{
	return this.replace(/\\/g, '\\\\').replace(/'/g, '\\\'');
};

String.prototype._replaceEntities = function (sInput, sDummy, sNum)
{
	return String.fromCharCode(parseInt(sNum, 10));
};

String.prototype.removeEntities = function ()
{
	return this.replace(/&(amp;)?#(\d+);/g, this._replaceEntities);
};

String.prototype.easyReplace = function (oReplacements)
{
	var sResult = this;
	for (var sSearch in oReplacements)
		sResult = sResult.replace(new RegExp('%' + sSearch + '%', 'g'), oReplacements[sSearch]);

	return sResult;
};

// Open a new popup window.
function reqWin(from, alternateWidth, alternateHeight, noScrollbars)
{
	var
		desktopURL = from && from.href ? from.href : from, vpw = $(window).width() * 0.8, vph = $(window).height() * 0.8,
		helf = $('#helf'), previousTarget = helf.data('src'), px = 'px', auto = 'auto';

	alternateWidth = alternateWidth ? alternateWidth : 480;
	if ((vpw < alternateWidth) || (alternateHeight && vph < alternateHeight))
	{
		noScrollbars = false;
		alternateWidth = Math.min(alternateWidth, vpw);
		alternateHeight = Math.min(alternateHeight, vph);
	}
	else
		noScrollbars = noScrollbars && (noScrollbars === true);

	// If the reqWin event was created on the fly, it'll bubble up to the body and cancel itself... Avoid that.
	$.event.fix(window.event).stopPropagation();

	// Clicking the help icon twice should close the popup and remove the global click event.
	if ($('body').unbind('click.h') && helf.remove().length && previousTarget == desktopURL)
		return false;

	// We create the popup inside a dummy div to fix positioning in freakin' IE.
	$('<div class="windowbg wrc"></div>').hide()
		.load(desktopURL, function () {
			$(this).css({
				overflow: noScrollbars ? 'hidden' : auto,
				width: (alternateWidth - 25) + px,
				height: alternateHeight ? (alternateHeight - 20) + px : auto,
				padding: '10px 12px 12px',
				border: '1px solid #999'
			}).fadeIn(300);
		}).appendTo(
			$('<div id="helf"></div>').data('src', desktopURL).css({
				position: is_ie6 ? 'absolute' : 'fixed',
				width: alternateWidth + px,
				height: alternateHeight ? alternateHeight + px : auto,
				bottom: 10,
				right: 10
			}).appendTo('body')
		);

	// Clicking anywhere on the page should close the popup. The namespace is for the earlier unbind().
	$('body').bind('click.h', function (e) {
		// If we clicked somewhere in the popup, don't close it, because we may want to select text.
		if (!$(e.srcElement).parents('#helf').length)
		{
			$('#helf').remove();
			$(this).unbind(e);
		}
	});

	// Return false so the click won't follow the link ;)
	return false;
}

// Checks if the passed input's value is nothing.
function isEmptyText(theField)
{
	return ($.trim(theField.value) == '');
}

// Only allow form submission ONCE.
function submitonce()
{
	_formSubmitted = true;

	// If there are any editors warn them submit is coming!
	for (var i = 0; i < smf_editorArray.length; i++)
		smf_editorArray[i].doSubmit();
}

function submitThisOnce(oControl)
{
	// Hateful, hateful fix for Safari 1.3 beta.
	if (!is_safari || vers > 2)
		$('textarea', 'form' in oControl ? oControl.form : oControl).attr('readOnly', true);

	return !_formSubmitted;
}

// Checks for variable in an array.
function in_array(variable, theArray)
{
	return $.inArray(variable, theArray) != -1;
}

// Find a specific radio button in its group and select it.
function selectRadioByName(oRadioGroup, sName)
{
	if (!('length' in oRadioGroup))
		return (oRadioGroup.checked = true);

	for (var i = 0, n = oRadioGroup.length; i < n; i++)
		if (oRadioGroup[i].value == sName)
			return (oRadioGroup[i].checked = true);

	return false;
}

// Invert all checkboxes at once by clicking a single checkbox.
function invertAll(oInvertCheckbox, oForm, sMask, bIgnoreDisabled)
{
	for (var i = 0; i < oForm.length; i++)
	{
		if (!('name' in oForm[i]) || (typeof sMask == 'string' && oForm[i].name.substr(0, sMask.length) != sMask && oForm[i].id.substr(0, sMask.length) != sMask))
			continue;

		if (!oForm[i].disabled || (typeof bIgnoreDisabled == 'boolean' && bIgnoreDisabled))
			oForm[i].checked = oInvertCheckbox.checked;
	}
}

// Keep the session alive - always!
function _sessionKeepAlive()
{
	var curTime = new Date().getTime();

	// Prevent a Firefox bug from hammering the server.
	if (smf_scripturl && curTime - _lastKeepAliveCheck > 900000)
	{
		var tempImage = new Image();
		tempImage.src = smf_prepareScriptUrl(smf_scripturl) + 'action=keepalive;time=' + curTime;
		_lastKeepAliveCheck = curTime;
	}
	setTimeout('_sessionKeepAlive();', 1200000);
}
setTimeout('_sessionKeepAlive();', 1200000);

// Set a theme option through javascript.
function smf_setThemeOption(option, value, theme, cur_session_id, cur_session_var, additional_vars)
{
	if (!cur_session_id)
		cur_session_id = smf_session_id;
	if (!additional_vars)
		additional_vars = '';

	var tempImage = new Image();
	tempImage.src = smf_prepareScriptUrl(smf_scripturl) + 'action=jsoption;var=' + option + ';val=' + value + ';' + cur_session_var + '=' + cur_session_id + additional_vars + (theme == null ? '' : '&id=' + theme) + ';time=' + (new Date().getTime());
}

function smf_avatarResize()
{
	var tempAvatars = [], j = 0;
	$('img.avatar').each(function () {
		tempAvatars[j] = new Image();
		tempAvatars[j].avatar = this;

		$(tempAvatars[j++]).load(function () {
			var ava = this.avatar;
			ava.width = this.width;
			ava.height = this.height;
			if (smf_avatarMaxWidth != 0 && this.width > smf_avatarMaxWidth)
			{
				ava.height = (smf_avatarMaxWidth * this.height) / this.width;
				ava.width = smf_avatarMaxWidth;
			}
			if (smf_avatarMaxHeight != 0 && ava.height > smf_avatarMaxHeight)
			{
				ava.width = (smf_avatarMaxHeight * ava.width) / ava.height;
				ava.height = smf_avatarMaxHeight;
			}
		}).attr('src', this.src);
	});
}


// Shows the page numbers by clicking the dots (in compact view).
function expandPages(spanNode, baseURL, firstPage, lastPage, perPage)
{
	var replacement = '', i, oldLastPage = 0, perPageLimit = 50;

	// Prevent too many pages to be loaded at once.
	if ((lastPage - firstPage) / perPage > perPageLimit)
	{
		oldLastPage = lastPage;
		lastPage = firstPage + perPageLimit * perPage;
	}

	// Calculate the new pages.
	for (i = firstPage; i < lastPage; i += perPage)
		replacement += '<a class="navPages" href="' + baseURL.replace(/%1\$d/, i).replace(/%%/g, '%') + '">' + (1 + i / perPage) + '</a> ';

	if (oldLastPage > 0)
		replacement += '<span style="font-weight: bold; cursor: pointer;" onclick="expandPages(this, \'' + baseURL + '\', ' + lastPage + ', ' + oldLastPage + ', ' + perPage + ');"> &hellip; </span> ';

	// The dots were bold, the page numbers are not (in most cases). Replace the dots by the new page links.
	$(spanNode).unbind('click').css('fontWeight', 'normal').html(replacement);
}

function smc_preCacheImage(sSrc)
{
	if (!('smc_aCachedImages' in window))
		window.smc_aCachedImages = [];

	if (!in_array(sSrc, window.smc_aCachedImages))
	{
		var oImage = new Image();
		oImage.src = sSrc;
	}
}


// *** smc_Cookie class.
function smc_Cookie(oOptions)
{
	this.opt = oOptions;
	this._cookies = {};

	if ('cookie' in document && document.cookie != '')
	{
		var aCookieList = document.cookie.split(';');
		for (var i = 0, n = aCookieList.length; i < n; i++)
		{
			var aNameValuePair = aCookieList[i].split('=');
			this._cookies[aNameValuePair[0].replace(/^\s+|\s+$/g, '')] = decodeURIComponent(aNameValuePair[1]);
		}
	}
};

smc_Cookie.prototype.get = function (sKey)
{
	return sKey in this._cookies ? this._cookies[sKey] : null;
};

smc_Cookie.prototype.set = function (sKey, sValue)
{
	document.cookie = sKey + '=' + encodeURIComponent(sValue);
};


// *** smc_Toggle class.
function smc_Toggle(oOptions)
{
	this.opt = oOptions;
	this._collapsed = false;
	this._cookie = null;

	// The master switch can disable this toggle fully.
	if ('bToggleEnabled' in this.opt && !this.opt.bToggleEnabled)
		return;

	// If cookies are enabled and they were set, override the initial state.
	if ('oCookieOptions' in this.opt && this.opt.oCookieOptions.bUseCookie)
	{
		// Initialize the cookie handler.
		this._cookie = new smc_Cookie({});

		// Check if the cookie is set.
		var cookieValue = this._cookie.get(this.opt.oCookieOptions.sCookieName);
		if (cookieValue != null)
			this.opt.bCurrentlyCollapsed = cookieValue == '1';
	}

	// If the init state is set to be collapsed, collapse it.
	if (this.opt.bCurrentlyCollapsed)
		this._changeState(true, true);

	// Initialize the images to be clickable.
	var i, n;
	if ('aSwapImages' in this.opt)
	{
		for (i = 0, n = this.opt.aSwapImages.length; i < n; i++)
		{
			$('#' + this.opt.aSwapImages[i].sId).show().data('that', this).click(function () {
				$(this).data('that').toggle();
				this.blur();
			}).css('cursor', 'pointer');

			// Preload the collapsed image.
			smc_preCacheImage(this.opt.aSwapImages[i].srcCollapsed);
		}
	}

	// Initialize links.
	if ('aSwapLinks' in this.opt)
		for (i = 0, n = this.opt.aSwapLinks.length; i < n; i++)
			$('#' + this.opt.aSwapLinks[i].sId).show().data('that', this).click(function () {
				$(this).data('that').toggle();
				this.blur();
				return false;
			});
};

// Collapse or expand the section.
smc_Toggle.prototype._changeState = function (bCollapse, bInit)
{
	// Default bInit to false.
	bInit = !!bInit;
	var i, n, o, op;

	// Handle custom function hook before collapse.
	if (!bInit && bCollapse && 'funcOnBeforeCollapse' in this.opt)
		this.opt.funcOnBeforeCollapse.call(this);

	// Handle custom function hook before expand.
	else if (!bInit && !bCollapse && 'funcOnBeforeExpand' in this.opt)
		this.opt.funcOnBeforeExpand.call(this);

	// Loop through all the images that need to be toggled.
	if ('aSwapImages' in this.opt)
	{
		op = this.opt.aSwapImages;
		for (i = 0, n = op.length; i < n; i++)
		{
			var
				oImage			= $('#' + op[i].sId),
				sTargetSource	= bCollapse ? op[i].srcCollapsed : op[i].srcExpanded,
				sAlt			= bCollapse ? op[i].altCollapsed : op[i].altExpanded;
			// Only (re)load the image if it's changed.
			if (oImage.attr('src') != sTargetSource)
				oImage.attr('src', sTargetSource);
			oImage.attr({ alt: sAlt, title: sAlt });
		}
	}

	// Loop through all the links that need to be toggled.
	if ('aSwapLinks' in this.opt)
		for (i = 0, op = this.opt.aSwapLinks, n = op.length; i < n; i++)
			$('#' + op[i].sId).html(bCollapse ? op[i].msgCollapsed : op[i].msgExpanded);

	// Now go through all the sections to be collapsed.
	for (i = 0, op = this.opt.aSwappableContainers, n = op.length; i < n; i++)
		(o = $('#' + op[i])) && bCollapse ? o.slideUp(300) : o.slideDown(300);

	// Update the new state.
	this._collapsed = bCollapse;

	// Update the cookie, if desired.
	if ('oCookieOptions' in this.opt && (op = this.opt.oCookieOptions) && op.bUseCookie)
		this._cookie.set(op.sCookieName, this._collapsed ? '1' : '0');

	if ('oThemeOptions' in this.opt && (op = this.opt.oThemeOptions) && op.bUseThemeSettings)
		smf_setThemeOption(op.sOptionName, this._collapsed ? '1' : '0', 'sThemeId' in op ? op.sThemeId : null, op.sSessionId, op.sSessionVar, 'sAdditionalVars' in op ? op.sAdditionalVars : null);
};

// Reverse the current state.
smc_Toggle.prototype.toggle = function ()
{
	this._changeState(!this._collapsed);
};


function ajax_indicator(turn_on)
{
	if (!_ajax_indicator_ele)
	{
		_ajax_indicator_ele = $('#ajax_in_progress');
		if (!(_ajax_indicator_ele.length) && ajax_notification_text !== null && turn_on)
			create_ajax_indicator_ele();
	}
	if (is_ie6)
		_ajax_indicator_ele.css({ position: 'absolute', top: $(document).scrollTop() });
	_ajax_indicator_ele.toggle(turn_on);
}

function create_ajax_indicator_ele()
{
	// Create the div for the indicator, and add the image, link to turn it off, and loading text.
	_ajax_indicator_ele = $('<div></div>').attr('id', 'ajax_in_progress').html(
		'<a href="#" onclick="ajax_indicator(false);"><img src="' + smf_images_url + '/icons/quick_remove.gif"'	+ (ajax_notification_cancel_text ?
		' alt="' + ajax_notification_cancel_text + '" title="' + ajax_notification_cancel_text + '"' : '') + ' />' + ajax_notification_text
	).appendTo('body');
}


// This'll contain all JumpTo objects on the page.
var aJumpTo = [];

// This function will retrieve the contents needed for the jump to boxes.
function grabJumpToContent()
{
	var aBoardsAndCategories = [], i, n;

	ajax_indicator(true);

	$('smf item', getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=xmlhttp;sa=jumpto;xml').responseXML).each(function () {
		aBoardsAndCategories[aBoardsAndCategories.length] = {
			id: parseInt(this.getAttribute('id'), 10),
			isCategory: this.getAttribute('type') == 'category',
			name: $(this).text().removeEntities(),
			is_current: false,
			childLevel: parseInt(this.getAttribute('childlevel'), 10)
		};
	});

	ajax_indicator(false);

	for (i = 0, n = aJumpTo.length; i < n; i++)
		aJumpTo[i]._fillSelect(aBoardsAndCategories);
}

// *** JumpTo class.
function JumpTo(opt)
{
	this.opt = opt;
	this.dropdownList = null;

	var sChildLevelPrefix = '';
	for (var i = opt.iCurBoardChildLevel; i > 0; i--)
		sChildLevelPrefix += opt.sBoardChildLevelIndicator;
	$('#' + opt.sContainerId).html(opt.sJumpToTemplate
		.replace(/%select_id%/, opt.sContainerId + '_select')
		.replace(/%dropdown_list%/, '<select name="' + opt.sContainerId + '_select" id="' + opt.sContainerId + '_select" '
			+ ('onbeforeactivate' in document ? 'onbeforeactivate' : 'onfocus') + '="grabJumpToContent();"><option value="?board='
			+ opt.iCurBoardId + '.0">' + sChildLevelPrefix + opt.sBoardPrefix + opt.sCurBoardName.removeEntities()
			+ '</option></select>&nbsp;<input type="button" value="' + opt.sGoButtonLabel + '" onclick="window.location.href = \''
			+ smf_prepareScriptUrl(smf_scripturl) + 'board=' + opt.iCurBoardId + '.0\';" />'));
	this.dropdownList = document.getElementById(opt.sContainerId + '_select');
};

// Fill the jump to box with entries. Method of the JumpTo class.
JumpTo.prototype._fillSelect = function (aBoardsAndCategories)
{
	var
		// Create an option that'll be above and below the category.
		oDashOption = $('<option></option>').append(this.opt.sCatSeparator).attr('disabled', 'disabled').val('')[0],
		// Create a document fragment that'll allowing inserting big parts at once.
		oListFragment = document.createDocumentFragment(),
		i, j, n, sChildLevelPrefix;

	if ('onbeforeactivate' in document)
		this.dropdownList.onbeforeactivate = null;
	else
		this.dropdownList.onfocus = null;

	// Loop through all items to be added.
	for (i = 0, n = aBoardsAndCategories.length; i < n; i++)
	{
		// If we've reached the currently selected board add all items so far.
		if (!aBoardsAndCategories[i].isCategory && aBoardsAndCategories[i].id == this.opt.iCurBoardId)
		{
			$(this.dropdownList).prepend(oListFragment);
			oListFragment = document.createDocumentFragment();
			continue;
		}

		if (aBoardsAndCategories[i].isCategory)
			oListFragment.appendChild(oDashOption.cloneNode(true));
		else
			for (j = aBoardsAndCategories[i].childLevel, sChildLevelPrefix = ''; j > 0; j--)
				sChildLevelPrefix += this.opt.sBoardChildLevelIndicator;

		oListFragment.appendChild(
			$('<option>' + (aBoardsAndCategories[i].isCategory ? this.opt.sCatPrefix : sChildLevelPrefix + this.opt.sBoardPrefix) + aBoardsAndCategories[i].name + '</option>')
				.val(aBoardsAndCategories[i].isCategory ? '#c' + aBoardsAndCategories[i].id : '?board=' + aBoardsAndCategories[i].id + '.0')[0]
		);

		if (aBoardsAndCategories[i].isCategory)
			oListFragment.appendChild(oDashOption.cloneNode(true));
	}

	// Add the remaining items after the currently selected item.
	// Internet Explorer needs css() to keep the box dropped down.
	$(this.dropdownList).append(oListFragment).css('width', 'auto').focus().change(function () {
		if (this.selectedIndex > 0 && $(this).val())
			window.location.href = smf_scripturl + $(this).val().substr(smf_scripturl.indexOf('?') == -1 || $(this).val().substr(0, 1) != '?' ? 0 : 1);
	});
};

// Find the actual position of an item.
// This is a dummy replacement for add-ons -- might be removed later.
function smf_itemPos(itemHandle)
{
	var offset = $(itemHandle).offset();
	return [offset.left, offset.top];
}

// This function takes the script URL and prepares it to allow the query string to be appended to it.
// It also replaces the host name with the current one. Which is required for security reasons.
function smf_prepareScriptUrl(sUrl)
{
	var finalUrl = sUrl.indexOf('?') == -1 ? sUrl + '?' : sUrl + (sUrl.charAt(sUrl.length - 1) == '?' || sUrl.charAt(sUrl.length - 1) == '&' || sUrl.charAt(sUrl.length - 1) == ';' ? '' : ';');
	return finalUrl.replace(/:\/\/[^\/]+/g, '://' + window.location.host);
}

// Alias for onload() event.
function addLoadEvent(fNewOnload)
{
	$(window).load(typeof fNewOnload == 'string' ? new Function(fNewOnload) : fNewOnload);
}

// Get the text in a code tag.
function smfSelectText(oCurElement, bActOnElement)
{
	// The place we're looking for is one div up, and next door - if it's auto detect.
	var oCodeArea = (typeof bActOnElement == 'boolean' && bActOnElement) ? document.getElementById(oCurElement) : oCurElement.parentNode.nextSibling, oCurRange;

	if (typeof oCodeArea != 'object' || oCodeArea == null)
		return false;

	// Start off with IE
	if ('createTextRange' in document.body)
	{
		oCurRange = document.body.createTextRange();
		oCurRange.moveToElementText(oCodeArea);
		oCurRange.select();
	}
	// Firefox et al.
	else if (window.getSelection)
	{
		var oCurSelection = window.getSelection();
		// Safari is special!
		if (oCurSelection.setBaseAndExtent)
		{
			var oLastChild = oCodeArea.lastChild;
			oCurSelection.setBaseAndExtent(oCodeArea, 0, oLastChild, 'innerText' in oLastChild ? oLastChild.innerText.length : oLastChild.textContent.length);
		}
		else
		{
			oCurRange = document.createRange();
			oCurRange.selectNodeContents(oCodeArea);

			oCurSelection.removeAllRanges();
			oCurSelection.addRange(oCurRange);
		}
	}

	return false;
}

// A function needed to discern HTML entities from non-western characters.
function smc_saveEntities(sFormName, aElementNames, sMask)
{
	var i, f = document.forms, e = f[sFormName].elements, n = e.length;
	if (typeof sMask == 'string')
		for (i = 0; i < n; i++)
			if (e[i].id.substr(0, sMask.length) == sMask)
				aElementNames[aElementNames.length] = e[i].name;

	for (i = 0, n = aElementNames.length; i < n; i++)
		if (aElementNames[i] in f[sFormName])
			f[sFormName][aElementNames[i]].value = f[sFormName][aElementNames[i]].value.replace(/&#/g, '&#38;#');
}

/*
// This will add an extra class to any external links, except those with title="-".
// Ignored for now because it needs some improvement to the domain name detection.
function _linkMagic()
{
	$('a[title!="-"]').each(function () {
		var hre = $(this).attr('href');
		if (typeof hre == 'string' && hre.length > 0 && (hre.indexOf(window.location.hostname) == -1) && (hre.indexOf('://') != -1))
			$(this).addClass('xt');
	});
}
*/

function _testStyle(sty)
{
	var uc = sty.charAt(0).toUpperCase() + sty.substr(1), stys = [ sty, 'Moz'+uc, 'Webkit'+uc, 'Khtml'+uc, 'ms'+uc, 'O'+uc ];
	for (var i in stys) if (_w.style[stys[i]] !== undefined) return true;
	return false;
}

// Dropdown menu in JS with CSS fallback, Wedge style.
// It may not show, but it took me years to refine it. -- Nao
var
	menu_baseId = 0, hoverable = 0, menu_delay = [], menu_ieshim = [];

function initMenu(menu)
{
	menu = $('#' + menu).show().css('visibility', 'visible');
	menu[0].style.opacity = 1;
	$('h4:not(:has(a))', menu).wrapInner('<a href="#" onclick="hoverable = 1; menu_show_me.call(this.parentNode.parentNode); hoverable = 0; return false;"></a>');

	var k = menu_baseId;
	$('li', menu).each(function () {
		if (is_ie6)
		{
			$(this).keyup(menu_show_me);
			document.write('<iframe src="" id="shim' + k + '" class="iefs" frameborder="0" scrolling="no"></iframe>');
			menu_ieshim[k] = $('#shim' + k)[0];
		}
		$(this).attr('id', 'li' + k++)
			.bind('mouseenter focus', menu_show_me)
			.bind('mouseleave blur', menu_hide_me)
			.mousedown(false)
			.click(function () {
				$('.hove').removeClass('hove');
				$('ul', menu).css(is_ie && !is_ie9up ? { visibility: 'hidden' } : { visibility: 'hidden', opacity: 0 });
				if (is_ie6)
					$('li', menu).each(function () { menu_show_shim(false, this.id); });
			});
	});
	menu_baseId = k;

	// Now that JS is ready to take action... Disable the pure CSS menu!
	$('.css.menu').removeClass('css');
}

// Without this, IE6 would show form elements in front of the menu. Bad IE6.
function menu_show_shim(showsh, ieid, j)
{
	var iem = ieid.substring(2);
	if (!(menu_ieshim[iem]))
		return;

	var i = menu_ieshim[iem].style;
	if (showsh)
	{
		i.top = j.offsetTop + j.offsetParent.offsetTop + 'px';
		i.left = j.offsetLeft + j.offsetParent.offsetLeft + 'px';
		i.width = (j.offsetWidth + 1) + 'px';
		i.height = (j.offsetHeight + 1) + 'px';
	}
	i.display = showsh ? 'block' : 'none';
}

// Entering a menu entry?
function menu_show_me()
{
	var hasul = $('ul', this).first()[0], is_top = this.parentNode.className == 'menu';

	if (hoverable && hasul && hasul.style.visibility == 'visible')
		return menu_hide_children(this.id);

	if (hasul)
	{
		hasul.style.visibility = 'visible';
		hasul.style.opacity = 1;
		hasul.style['margin' + (document.dir && document.dir == 'rtl' ? 'Right' : 'Left')] = (is_top ? 0 : this.parentNode.clientWidth - 5) + 'px';
		if (is_ie6)
			menu_show_shim(true, this.id, hasul);
	}

	if (!is_top || !$('h4', this).first().addClass('hove').length)
		$(this).addClass('hove').parentsUntil('.menu>li').each(function () {
			if (this.nodeName == 'LI')
				$(this).addClass('hove');
		});

	clearTimeout(menu_delay[this.id.substring(2)]);

	$(this).siblings('li').each(function () { menu_hide_children(this.id); });
}

// Leaving a menu entry?
function menu_hide_me(e)
{
	// The deepest level should hide the hover class immediately.
	if (!$(this).children('ul').length)
		$(this).removeClass('hove');

	// Are we leaving the menu entirely, and thus triggering the time
	// threshold, or are we just switching to another menu item?
	$(e.relatedTarget || e.toElement).parents('.menu').length ?
		menu_hide_children(this.id) :
		menu_delay[this.id.substring(2)] = setTimeout('menu_hide_children("' + this.id + '")', 250);
}

// Hide all children menus.
function menu_hide_children(id)
{
	$('#' + id).children().andSelf().removeClass('hove').find('ul')
		.css(is_ie && !is_ie9up ? { visibility: 'hidden' } : { visibility: 'hidden', opacity: 0 });

	if (is_ie6)
		menu_show_shim(false, id);
}

// Has your browser got the goods?
// These variables aren't used, but you can now use them in your custom scripts.
// In short: if (!can_borderradius) inject_rounded_border_emulation_hack();
var
	_w = document.createElement('wedgerocks'),
	can_borderradius = _testStyle('borderRadius'),
	can_boxshadow = _testStyle('boxShadow'),
	can_ajax = $.support.ajax;

/* Optimize:
_ajax_indicator_ele = _a
menu_baseId = _b
_cookie = _c
menu_delay = _d
_formSubmitted = _f
menu_hide_children = _h
menu_hide_me = _hm
menu_ieshim = _ie
_lastKeepAliveCheck = _k
dropdownList = _l
_collapsed = _o
menu_show_me = _sm
menu_show_shim = _sh
aBoardsAndCategories = b
aElementNames = e
additional_vars = a
alternateHeight = h
alternateWidth = w
bActOnElement = t
bAsync = b
cur_session_id = s
cur_session_var = v
currentNode = n
fNewOnload = f
funcCallback = f
itemHandle = h
noScrollbars = n
oCaller = o
oCodeArea = a
oCurElement = e
oCurRange = r
oCurSelection = c
oDashOption = d
oMyDoc = d
oRadioGroup = r
oReplacements = o
previousTarget = p
sChildLevelPrefix = p
sContent = c
sFormName = f
sFrom = f
sMask = m
sMatch = m
sReturn = s
sTo = t
sUrl = u
theArray = a
theField = f
theValue = v
_fillSelect = _fs
_changeState = _cs
grabJumpToContent = gjtc
_replaceEntities = _re
*/
