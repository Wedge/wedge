/*!
 * This file is under the SMF license.
 * All code changes compared against SMF 2.0 are protected
 * by the Wedge license, http://wedgeforum.com/license/
 */

var
	smf_formSubmitted = false,
	lastKeepAliveCheck = new Date().getTime(),
	smf_editorArray = [],
	ajax_indicator_ele = null;

// Basic browser detection
var
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
	return $.ajax(typeof(funcCallback) != 'undefined' ?
		{ url: sUrl, success: funcCallback, context: this } :
		{ url: sUrl, async: false, context: this }
	);
}

// Send a post form to the server using XMLHttpRequest.
function sendXMLDocument(sUrl, sContent, funcCallback)
{
	$.ajax(typeof(funcCallback) != 'undefined' ?
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
	return typeof(smf_iso_case_folding) == 'boolean' && smf_iso_case_folding == true ? this.php_strtr(
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

// Open a new window.
function reqWin(from, alternateWidth, alternateHeight, noScrollbars)
{
	var desktopURL = typeof(from) == 'object' && from.href ? from.href : from;
	if ((alternateWidth && self.screen.availWidth * 0.8 < alternateWidth) || (alternateHeight && self.screen.availHeight * 0.8 < alternateHeight))
	{
		noScrollbars = false;
		alternateWidth = Math.min(alternateWidth, self.screen.availWidth * 0.8);
		alternateHeight = Math.min(alternateHeight, self.screen.availHeight * 0.8);
	}
	else
		noScrollbars = typeof(noScrollbars) == 'boolean' && noScrollbars == true;

	var aPos = typeof(from) == 'object' ? smf_itemPos(from) : [10, 10];

	var helf = $('#helf'), previousTarget = helf.data('src');
	if (previousTarget && helf.remove().length && previousTarget == desktopURL)
		return false;

	$('<div></div>').attr('id', 'helf')
		.addClass('windowbg wrc').data('src', desktopURL).load(desktopURL, function() {
		$(this).css({
			overflow: noScrollbars ? 'hidden' : 'auto',
			position: 'absolute',
			width: (alternateWidth ? alternateWidth : 480) + 'px',
			padding: '10px 12px 12px',
			left: (aPos[0] + 15) + 'px',
			top: (aPos[1] + 15) + 'px',
			border: '1px solid #999'
		}).hide().appendTo('body').fadeIn(300);
	});
	if (alternateHeight)
		$('#helf').css('height', alternateHeight + 'px');

	// Return false so the click won't follow the link ;)
	return false;
}

// Checks if the passed input's value is nothing.
function isEmptyText(theField)
{
	// Copy the value so changes can be made..
	var theValue = theField.value;

	// Strip whitespace off the left side.
	while (theValue.length > 0 && (theValue.charAt(0) == ' ' || theValue.charAt(0) == '\t'))
		theValue = theValue.substring(1, theValue.length);
	// Strip whitespace off the right side.
	while (theValue.length > 0 && (theValue.charAt(theValue.length - 1) == ' ' || theValue.charAt(theValue.length - 1) == '\t'))
		theValue = theValue.substring(0, theValue.length - 1);

	return theValue == '';
}

// Only allow form submission ONCE.
function submitonce()
{
	smf_formSubmitted = true;

	// If there are any editors warn them submit is coming!
	for (var i = 0; i < smf_editorArray.length; i++)
		smf_editorArray[i].doSubmit();
}

function submitThisOnce(oControl)
{
	// Hateful, hateful fix for Safari 1.3 beta.
	if (!is_safari || vers > 2)
		$('textarea', 'form' in oControl ? oControl.form : oControl).attr('readOnly', true);

	return !smf_formSubmitted;
}

// Set the "outer" HTML of an element.
function setOuterHTML(oElement, sToValue)
{
	if ('outerHTML' in oElement)
		oElement.outerHTML = sToValue;
	else
	{
		var range = document.createRange();
		range.setStartBefore(oElement);
		oElement.parentNode.replaceChild(range.createContextualFragment(sToValue), oElement);
	}
}

// Checks for variable in theArray.
function in_array(variable, theArray)
{
	for (var i in theArray)
		if (theArray[i] == variable)
			return true;

	return false;
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
		if (!('name' in oForm[i]) || (typeof(sMask) == 'string' && oForm[i].name.substr(0, sMask.length) != sMask && oForm[i].id.substr(0, sMask.length) != sMask))
			continue;

		if (!oForm[i].disabled || (typeof(bIgnoreDisabled) == 'boolean' && bIgnoreDisabled))
			oForm[i].checked = oInvertCheckbox.checked;
	}
}

// Keep the session alive - always!
function smf_sessionKeepAlive()
{
	var curTime = new Date().getTime();

	// Prevent a Firefox bug from hammering the server.
	if (smf_scripturl && curTime - lastKeepAliveCheck > 900000)
	{
		var tempImage = new Image();
		tempImage.src = smf_prepareScriptUrl(smf_scripturl) + 'action=keepalive;time=' + curTime;
		lastKeepAliveCheck = curTime;
	}

	window.setTimeout('smf_sessionKeepAlive();', 1200000);
}
window.setTimeout('smf_sessionKeepAlive();', 1200000);

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
			this.avatar.width = this.width;
			this.avatar.height = this.height;
			if (smf_avatarMaxWidth != 0 && this.width > smf_avatarMaxWidth)
			{
				this.avatar.height = (smf_avatarMaxWidth * this.height) / this.width;
				this.avatar.width = smf_avatarMaxWidth;
			}
			if (smf_avatarMaxHeight != 0 && this.avatar.height > smf_avatarMaxHeight)
			{
				this.avatar.width = (smf_avatarMaxHeight * this.avatar.width) / this.avatar.height;
				this.avatar.height = smf_avatarMaxHeight;
			}
		}).attr('src', this.src);
	});
}


function hashLoginPassword(doForm, cur_session_id)
{
	if (!cur_session_id)
		cur_session_id = smf_session_id;
	if (typeof(hex_sha1) == 'undefined')
		return;
	// Are they using an email address?
	if (doForm.user.value.indexOf('@') != -1)
		return;

	// Unless the browser is Opera, the password will not save properly.
	if (!('opera' in window))
		doForm.passwrd.autocomplete = 'off';

	doForm.hash_passwrd.value = hex_sha1(hex_sha1(doForm.user.value.php_to8bit().php_strtolower() + doForm.passwrd.value.php_to8bit()) + cur_session_id);

	// It looks nicer to fill it with asterisks, but Firefox will try to save that.
	doForm.passwrd.value = is_ff != -1 ? '' : doForm.passwrd.value.replace(/./g, '*');
}

function hashAdminPassword(doForm, username, cur_session_id)
{
	if (!cur_session_id)
		cur_session_id = smf_session_id;
	if (typeof(hex_sha1) == 'undefined')
		return;

	doForm.admin_hash_pass.value = hex_sha1(hex_sha1(username.php_to8bit().php_strtolower() + doForm.admin_pass.value.php_to8bit()) + cur_session_id);
	doForm.admin_pass.value = doForm.admin_pass.value.replace(/./g, '*');
}

// Shows the page numbers by clicking the dots (in compact view).
function expandPages(spanNode, baseURL, firstPage, lastPage, perPage)
{
	var replacement = '', i, oldLastPage = 0, perPageLimit = 50;

	// The dots were bold, the page numbers are not (in most cases).
	spanNode.style.fontWeight = 'normal';
	spanNode.onclick = '';

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
		replacement += '<span style="font-weight: bold; cursor: pointer;" onclick="expandPages(this, \'' + baseURL + '\', ' + lastPage + ', ' + oldLastPage + ', ' + perPage + ');"> ... </span> ';

	// Replace the dots by the new page links.
	spanNode.innerHTML = replacement;
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
	this.oCookies = {};
	this.init();
}

smc_Cookie.prototype.init = function ()
{
	if ('cookie' in document && document.cookie != '')
	{
		var aCookieList = document.cookie.split(';');
		for (var i = 0, n = aCookieList.length; i < n; i++)
		{
			var aNameValuePair = aCookieList[i].split('=');
			this.oCookies[aNameValuePair[0].replace(/^\s+|\s+$/g, '')] = decodeURIComponent(aNameValuePair[1]);
		}
	}
};

smc_Cookie.prototype.get = function (sKey)
{
	return sKey in this.oCookies ? this.oCookies[sKey] : null;
};

smc_Cookie.prototype.set = function (sKey, sValue)
{
	document.cookie = sKey + '=' + encodeURIComponent(sValue);
};


// *** smc_Toggle class.
function smc_Toggle(oOptions)
{
	this.opt = oOptions;
	this.bCollapsed = false;
	this.oCookie = null;
	this.init();
}

smc_Toggle.prototype.init = function ()
{
	// The master switch can disable this toggle fully.
	if ('bToggleEnabled' in this.opt && !this.opt.bToggleEnabled)
		return;

	// If cookies are enabled and they were set, override the initial state.
	if ('oCookieOptions' in this.opt && this.opt.oCookieOptions.bUseCookie)
	{
		// Initialize the cookie handler.
		this.oCookie = new smc_Cookie({});

		// Check if the cookie is set.
		var cookieValue = this.oCookie.get(this.opt.oCookieOptions.sCookieName);
		if (cookieValue != null)
			this.opt.bCurrentlyCollapsed = cookieValue == '1';
	}

	// If the init state is set to be collapsed, collapse it.
	if (this.opt.bCurrentlyCollapsed)
		this.changeState(true, true);

	// Initialize the images to be clickable.
	var i, n;
	if ('aSwapImages' in this.opt)
	{
		for (i = 0, n = this.opt.aSwapImages.length; i < n; i++)
		{
			var oImage = document.getElementById(this.opt.aSwapImages[i].sId);
			if (typeof(oImage) == 'object' && oImage != null)
			{
				// Display the image in case it was hidden.
				if (oImage.style.display == 'none')
					oImage.style.display = '';

				oImage.instanceRef = this;
				oImage.onclick = function () {
					this.instanceRef.toggle();
					this.blur();
				};
				oImage.style.cursor = 'pointer';

				// Preload the collapsed image.
				smc_preCacheImage(this.opt.aSwapImages[i].srcCollapsed);
			}
		}
	}

	// Initialize links.
	if ('aSwapLinks' in this.opt)
	{
		for (i = 0, n = this.opt.aSwapLinks.length; i < n; i++)
		{
			var oLink = document.getElementById(this.opt.aSwapLinks[i].sId);
			if (typeof(oLink) == 'object' && oLink != null)
			{
				// Display the link in case it was hidden.
				if (oLink.style.display == 'none')
					oLink.style.display = '';

				oLink.instanceRef = this;
				oLink.onclick = function () {
					this.instanceRef.toggle();
					this.blur();
					return false;
				};
			}
		}
	}
};

// Collapse or expand the section.
smc_Toggle.prototype.changeState = function (bCollapse, bInit)
{
	// Default bInit to false.
	bInit = !!bInit;
	var i, n, o;

	// Handle custom function hook before collapse.
	if (!bInit && bCollapse && 'funcOnBeforeCollapse' in this.opt)
		this.opt.funcOnBeforeCollapse.call(this);

	// Handle custom function hook before expand.
	else if (!bInit && !bCollapse && 'funcOnBeforeExpand' in this.opt)
		this.opt.funcOnBeforeExpand.call(this);

	// Loop through all the images that need to be toggled.
	if ('aSwapImages' in this.opt)
	{
		for (i = 0, n = this.opt.aSwapImages.length; i < n; i++)
		{
			var oImage = document.getElementById(this.opt.aSwapImages[i].sId);
			if (typeof(oImage) == 'object' && oImage != null)
			{
				// Only (re)load the image if it's changed.
				var sTargetSource = bCollapse ? this.opt.aSwapImages[i].srcCollapsed : this.opt.aSwapImages[i].srcExpanded;
				if (oImage.src != sTargetSource)
					oImage.src = sTargetSource;

				oImage.alt = oImage.title = bCollapse ? this.opt.aSwapImages[i].altCollapsed : this.opt.aSwapImages[i].altExpanded;
			}
		}
	}

	// Loop through all the links that need to be toggled.
	if ('aSwapLinks' in this.opt)
		for (i = 0, n = this.opt.aSwapLinks.length; i < n; i++)
			$('#' + this.opt.aSwapLinks[i].sId).html(bCollapse ? this.opt.aSwapLinks[i].msgCollapsed : this.opt.aSwapLinks[i].msgExpanded);

	// Now go through all the sections to be collapsed.
	for (i = 0, n = this.opt.aSwappableContainers.length; i < n; i++)
		(o = $('#' + this.opt.aSwappableContainers[i])) && bCollapse ? o.slideUp(300) : o.slideDown(300);

	// Update the new state.
	this.bCollapsed = bCollapse;

	// Update the cookie, if desired.
	if ('oCookieOptions' in this.opt && this.opt.oCookieOptions.bUseCookie)
		this.oCookie.set(this.opt.oCookieOptions.sCookieName, this.bCollapsed ? '1' : '0');

	if ('oThemeOptions' in this.opt && this.opt.oThemeOptions.bUseThemeSettings)
		smf_setThemeOption(this.opt.oThemeOptions.sOptionName, this.bCollapsed ? '1' : '0', 'sThemeId' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sThemeId : null, this.opt.oThemeOptions.sSessionId, this.opt.oThemeOptions.sSessionVar, 'sAdditionalVars' in this.opt.oThemeOptions ? this.opt.oThemeOptions.sAdditionalVars : null);
};

// Reverse the current state.
smc_Toggle.prototype.toggle = function ()
{
	this.changeState(!this.bCollapsed);
};


function ajax_indicator(turn_on)
{
	if (!ajax_indicator_ele)
	{
		ajax_indicator_ele = $('#ajax_in_progress');
		if (!(ajax_indicator_ele.length) && ajax_notification_text !== null)
			create_ajax_indicator_ele();
	}

	if (ajax_indicator_ele.length)
	{
		if (is_ie6)
			ajax_indicator_ele.css({ position: 'absolute', top: (document.documentElement.scrollTop ? document.documentElement : document.body).scrollTop });
		ajax_indicator_ele.css('display', turn_on ? 'block' : 'none');
	}
}

function create_ajax_indicator_ele()
{
	// Create the div for the indicator, and add the image, link to turn it off, and loading text.
	ajax_indicator_ele = $('<div></div>').attr('id', 'ajax_in_progress').html(
		'<a href="#" onclick="ajax_indicator(false);"><img src="' + smf_images_url + '/icons/quick_remove.gif"'	+ (ajax_notification_cancel_text ?
		' alt="' + ajax_notification_cancel_text + '" title="' + ajax_notification_cancel_text + '"' : '') + ' />' + ajax_notification_text
	).appendTo('body');
}


// This'll contain all JumpTo objects on the page.
var aJumpTo = [];

// This function will retrieve the contents needed for the jump to boxes.
function grabJumpToContent()
{
	var
		oXMLDoc = getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + 'action=xmlhttp;sa=jumpto;xml'),
		aBoardsAndCategories = [], i, n;

	ajax_indicator(true);

	if (oXMLDoc.responseXML)
	{
		var items = oXMLDoc.responseXML.getElementsByTagName('smf')[0].getElementsByTagName('item');
		for (i = 0, n = items.length; i < n; i++)
			aBoardsAndCategories[aBoardsAndCategories.length] = {
				id: parseInt(items[i].getAttribute('id'), 10),
				isCategory: items[i].getAttribute('type') == 'category',
				name: items[i].firstChild.nodeValue.removeEntities(),
				is_current: false,
				childLevel: parseInt(items[i].getAttribute('childlevel'), 10)
			};
	}

	ajax_indicator(false);

	for (i = 0, n = aJumpTo.length; i < n; i++)
		aJumpTo[i].fillSelect(aBoardsAndCategories);
}

// *** JumpTo class.
function JumpTo(oJumpToOptions)
{
	this.opt = oJumpToOptions;
	this.dropdownList = null;
	this.showSelect();
}

// Show the initial select box (onload). Method of the JumpTo class.
JumpTo.prototype.showSelect = function ()
{
	var sChildLevelPrefix = '';
	for (var i = this.opt.iCurBoardChildLevel; i > 0; i--)
		sChildLevelPrefix += this.opt.sBoardChildLevelIndicator;
	$('#' + this.opt.sContainerId).html(this.opt.sJumpToTemplate
		.replace(/%select_id%/, this.opt.sContainerId + '_select')
		.replace(/%dropdown_list%/, '<select name="' + this.opt.sContainerId + '_select" id="' + this.opt.sContainerId + '_select" '
			+ ('onbeforeactivate' in document ? 'onbeforeactivate' : 'onfocus') + '="grabJumpToContent();"><option value="?board='
			+ this.opt.iCurBoardId + '.0">' + sChildLevelPrefix + this.opt.sBoardPrefix + this.opt.sCurBoardName.removeEntities()
			+ '</option></select>&nbsp;<input type="button" value="' + this.opt.sGoButtonLabel + '" onclick="window.location.href = \''
			+ smf_prepareScriptUrl(smf_scripturl) + 'board=' + this.opt.iCurBoardId + '.0\';" />'));
	this.dropdownList = document.getElementById(this.opt.sContainerId + '_select');
};

// Fill the jump to box with entries. Method of the JumpTo class.
JumpTo.prototype.fillSelect = function (aBoardsAndCategories)
{
	// Create an option that'll be above and below the category.
	var oDashOption = $(document.createElement('option')).append(document.createTextNode(this.opt.sCatSeparator)).attr({ disabled: 'disabled', value: '' })[0];

	if ('onbeforeactivate' in document)
		this.dropdownList.onbeforeactivate = null;
	else
		this.dropdownList.onfocus = null;

	// Create a document fragment that'll allowing inserting big parts at once.
	var oListFragment = document.createDocumentFragment();

	// Loop through all items to be added.
	for (var i = 0, n = aBoardsAndCategories.length; i < n; i++)
	{
		var j, sChildLevelPrefix, oOption;

		// If we've reached the currently selected board add all items so far.
		if (!aBoardsAndCategories[i].isCategory && aBoardsAndCategories[i].id == this.opt.iCurBoardId)
		{
			this.dropdownList.insertBefore(oListFragment, this.dropdownList.options[0]);
			oListFragment = document.createDocumentFragment();
			continue;
		}

		if (aBoardsAndCategories[i].isCategory)
			oListFragment.appendChild(oDashOption.cloneNode(true));
		else
			for (j = aBoardsAndCategories[i].childLevel, sChildLevelPrefix = ''; j > 0; j--)
				sChildLevelPrefix += this.opt.sBoardChildLevelIndicator;

		oOption = document.createElement('option');
		oOption.appendChild(document.createTextNode((aBoardsAndCategories[i].isCategory ? this.opt.sCatPrefix : sChildLevelPrefix + this.opt.sBoardPrefix) + aBoardsAndCategories[i].name));
		oOption.value = aBoardsAndCategories[i].isCategory ? '#c' + aBoardsAndCategories[i].id : '?board=' + aBoardsAndCategories[i].id + '.0';
		oListFragment.appendChild(oOption);

		if (aBoardsAndCategories[i].isCategory)
			oListFragment.appendChild(oDashOption.cloneNode(true));
	}

	// Add the remaining items after the currently selected item.
	// Internet Explorer needs css() to keep the box dropped down.
	$(this.dropdownList).append(oListFragment).css('width', 'auto').focus().change(function () {
		if (this.selectedIndex > 0 && this.options[this.selectedIndex].value)
			window.location.href = smf_scripturl + this.options[this.selectedIndex].value.substr(smf_scripturl.indexOf('?') == -1 || this.options[this.selectedIndex].value.substr(0, 1) != '?' ? 0 : 1);
	});
};

// Find the actual position of an item.
// Alternatively: var offset = $(itemHandle).offset().left/top;
// But it doesn't work well on floated elements in Opera. Hmm.
function smf_itemPos(itemHandle)
{
	var itemX = 0, itemY = 0;

	if ('offsetParent' in itemHandle)
	{
		itemX = itemHandle.offsetLeft;
		itemY = itemHandle.offsetTop;
		while (itemHandle.offsetParent && typeof(itemHandle.offsetParent) == 'object')
		{
			itemHandle = itemHandle.offsetParent;
			itemX += itemHandle.offsetLeft;
			itemY += itemHandle.offsetTop;
		}
	}
	else if ('x' in itemHandle)
	{
		itemX = itemHandle.x;
		itemY = itemHandle.y;
	}

	return [itemX, itemY];
}

// This function takes the script URL and prepares it to allow the query string to be appended to it.
// It also replaces the host name with the current one. Which is required for security reasons.
function smf_prepareScriptUrl(sUrl)
{
	var finalUrl = sUrl.indexOf('?') == -1 ? sUrl + '?' : sUrl + (sUrl.charAt(sUrl.length - 1) == '?' || sUrl.charAt(sUrl.length - 1) == '&' || sUrl.charAt(sUrl.length - 1) == ';' ? '' : ';');
	return finalUrl.replace(/:\/\/[^\/]+/g, '://' + window.location.host);
}

// Alias for onload event.
function addLoadEvent(fNewOnload)
{
	$(window).load(typeof(fNewOnload) == 'string' ? new Function(fNewOnload) : fNewOnload);
}

// Get the text in a code tag.
function smfSelectText(oCurElement, bActOnElement)
{
	// The place we're looking for is one div up, and next door - if it's auto detect.
	var oCodeArea = (typeof(bActOnElement) == 'boolean' && bActOnElement) ? document.getElementById(oCurElement) : oCurElement.parentNode.nextSibling, oCurRange;

	if (typeof(oCodeArea) != 'object' || oCodeArea == null)
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
	var i, n;
	if (typeof(sMask) == 'string')
		for (i = 0, n = document.forms[sFormName].elements.length; i < n; i++)
			if (document.forms[sFormName].elements[i].id.substr(0, sMask.length) == sMask)
				aElementNames[aElementNames.length] = document.forms[sFormName].elements[i].name;

	for (i = 0, n = aElementNames.length; i < n; i++)
		if (aElementNames[i] in document.forms[sFormName])
			document.forms[sFormName][aElementNames[i]].value = document.forms[sFormName][aElementNames[i]].value.replace(/&#/g, '&#38;#');
}

/**
 *
 * Dropdown menus, Wedge style.
 * © 2008-2010 René-Gilles Deberdt (http://wedgeforum.com)
 * Released under the Wedge license, http://wedgeforum.com/license/
 *
 * Uses portions © 2004 by Batiste Bieler (http://dosimple.ch/), released
 * under the LGPL license (http://www.gnu.org/licenses/lgpl.html)
 *
 */

var baseId = 0, hoverable = 0, rtl = 'margin' + (document.dir && document.dir == 'rtl' ? 'Right' : 'Left');
var timeoutli = [], ieshim = [];

function initMenu(menu)
{
	menu.style.display = 'block';
	menu.style.visibility = 'visible';
	menu.style.opacity = 1;
	$('h4:not(:has(a))', menu).wrapInner('<a href="#" onclick="hoverable = 1; show_me.call(this.parentNode.parentNode); hoverable = 0; return false;"></a>');

	var k = baseId;
	$('li', menu).each(function () {
		if (is_ie6)
		{
			$(this).keyup(show_me);
			document.write('<iframe src="" id="shim' + k + '" class="iefs" frameborder="0" scrolling="no"></iframe>');
			ieshim[k] = $('#shim' + k)[0];
		}
		$(this).attr('id', 'li' + k++)
			.bind('mouseenter focus', show_me)
			.bind('mouseleave blur', timeout_hide)
			.mousedown(false)
			.click(function () { $(menu).children('li').each(function () { hide_sub_ul(this); }); });
	});
	baseId = k;

	// Now that JS is ready to take action... Disable the pure CSS menu!
	$('.css.menu').removeClass('css');
}

// Hide the first ul element of the current element
function timeout_hide(e)
{
	var insitu, targ = e.relatedTarget || e.toElement;
	while (targ && !insitu)
	{
		insitu = targ.parentNode && targ.parentNode.className == 'menu';
		targ = targ.parentNode;
	}
	insitu ? hide_child_ul(this.id) : timeoutli[this.id.substring(2)] = window.setTimeout('hide_child_ul("' + this.id + '")', 242);
}

// Hide all children <ul>'s.
function hide_child_ul(id)
{
	var eid = $('#' + id), eul = $('ul', eid).css('visibility', 'hidden');
	eul.length ? eul[0].style.opacity = 0 : '';
	$(eid).removeClass('linkOver');
	$('h4:first', eid).removeClass('linkOver');
	$('.linkOver', eid).removeClass('linkOver');

	if (is_ie6)
		show_shim(false, id);
}

// Without this, IE6 would show form elements in front of the menu. Bad IE6.
function show_shim(showsh, ieid, iemenu)
{
	var iem = ieid.substring(2);
	if (!(ieshim[iem]))
		return;

	var i = ieshim[iem].style, j = iemenu;
	if (showsh)
	{
		i.top = j.offsetTop + j.offsetParent.offsetTop + 'px';
		i.left = j.offsetLeft + j.offsetParent.offsetLeft + 'px';
		i.width = (j.offsetWidth + 1) + 'px';
		i.height = (j.offsetHeight + 1) + 'px';
	}
	i.display = showsh ? 'block' : 'none';
}

// Show the first child <ul> we can find.
function show_me()
{
	var showul = $('ul:first', this)[0], is_top = this.parentNode.className == 'menu';

	if (hoverable && showul && showul.style.visibility == 'visible')
		return hide_child_ul(this.id);

	if (showul)
	{
		showul.style.visibility = 'visible';
		showul.style.opacity = 1;
		showul.style[rtl] = (is_top ? 0 : this.parentNode.clientWidth - 5) + 'px';
		if (is_ie6)
			show_shim(true, this.id, showul);
	}

	if (!is_top || !($('h4:first', this).addClass('linkOver').length))
		$(this).addClass('linkOver').parentsUntil('li:has(h4)').each(function () {
			if (this.nodeName == 'LI')
				$(this).addClass('linkOver');
		});

	clearTimeout(timeoutli[this.id.substring(2)]);

	$(this).siblings('li').each(function () { hide_sub_ul(this); });
}

function hide_sub_ul(li)
{
	if (!($('h4:first', li).removeClass().length))
		$('a', li).removeClass();

	$('ul', li).css(is_ie && !is_ie9up ? { visibility: 'hidden' } : { visibility: 'hidden', opacity: 0 });
}

/* --------------------------------------------------------
   End of dropdown menu code */

// This will add an extra class to any external links, except those with title="-".
// Ignored for now because it needs some improvement to the domain name detection.
function linkMagic()
{
	$('a[title!="-"]').each(function() {
		var hre = $(this).attr('href');
		if (typeof hre == 'string' && hre.length > 0)
			if ((hre.indexOf(window.location.hostname) == -1) && (hre.indexOf('://') != -1))
				$(this).addClass('xt');
	});
}

function testStyle(sty)
{
	var uc = sty.charAt(0).toUpperCase() + sty.substr(1), stys = [ sty, 'Moz'+uc, 'Webkit'+uc, 'Khtml'+uc, 'ms'+uc, 'O'+uc ];
	for (var i in stys) if (wedgerocks.style[stys[i]] !== undefined) return true;
	return false;
}

// Has your browser got the goods?
// These variables aren't used, but you can now use them in your custom scripts.
// In short: if (!can_borderradius) inject_rounded_border_emulation_hack();
var
	wedgerocks = document.createElement('wedgerocks'),
	can_ajax = 'XMLHttpRequest' in window || 'ActiveXObject' in window,
	can_borderradius = testStyle('borderRadius'),
	can_boxshadow = testStyle('boxShadow');

/* Optimize:
smf_formSubmitted = sfs
aBoardsAndCategories = b
aElementNames = e
additional_vars = a
ajax_indicator_ele = aie
alternateHeight = h
alternateWidth = w
bActOnElement = t
bAsync = b
cur_session_id = s
cur_session_var = v
currentNode = n
dropdownList = dl
fNewOnload = f
funcCallback = f
itemHandle = h
lastKeepAliveCheck = lka
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
*/
