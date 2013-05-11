/*!
 * Wedge
 *
 * Helper functions for creating and managing the auto-suggest control
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

@language index;

function weAutoSuggest(oOptions)
{
	this.opt = oOptions;

	// Nothing else for now.
	this.opt.sSearchType = 'member';

	// Store the handle to the text box.
	var oText = $('#' + this.opt.sControlId), that = this;

	this.opt.sItemTemplate = this.opt.sItemTemplate || '<input type="hidden" name="%post_name%[]" value="%item_id%"><a href="%item_href%" class="extern" onclick="window.open(this.href, \'_blank\'); return false;">%item_name%</a>&nbsp;<img src="%images_url%/pm_recipient_delete.gif" alt="%delete_text%" title="%delete_text%"> &nbsp; ';

	this.oTextHandle = oText;
	this.oSuggestDivHandle = null;
	this.oXmlRequestHandle = null;
	this.oSelectedDiv = null;
	this.oHideTimer = null;
	this.oCallback = {};
	this.aCache = [];
	this.aDisplayData = [];
	this.iItemCount = 0;
	this.bDoAutoAdd = false;
	this.bPositionComplete = false;
	this.sLastDirtySearch = '';
	this.sLastSearch = '';

	// Should selected items be added to a list?
	this.bItemList = !!this.opt.bItemList;

	// Create a div that'll contain the results later on.
	this.oSuggestDivHandle = $('<div></div>').addClass('auto_suggest').hide().appendTo('body')[0];

	// Create a backup text input for single-entry inputs.
	this.oRealTextHandle = $('<input type="hidden" name="' + oText[0].name + '" />').val(oText.val()).appendTo(oText[0].form);

	// Disable autocomplete in any browser by obfuscating the name.
	oText.attr({ name: 'dummy_' + Math.floor(Math.random() * 1000000), autocomplete: 'off' })
		.on(is_opera || is_ie ? 'keypress keydown' : 'keydown', function (oEvent) { return that.handleKey(oEvent); })
		.on('keyup change focus', function () { return that.autoSuggestUpdate(); })
		.blur(function () { return that.autoSuggestHide(); });

	if (this.bItemList)
		this.oItemList = $('<div></div>').insertBefore(oText);

	// Are there any items that should be added in advance?
	$.each(this.opt.aListItems || {}, function (sItemId, sItemName) { that.addItemLink(sItemId, sItemName); });

	return true;
}

// Was it an enter key - if so assume they are trying to select something.
weAutoSuggest.prototype.handleKey = function (oEvent)
{
	// Get the keycode of the key that was pressed.
	var iKeyPress = oEvent.which;

	// Tab.
	if (iKeyPress == 9)
	{
		if (this.aDisplayData.length)
			this.oSelectedDiv != null ? this.itemClicked(this.oSelectedDiv) : this.handleSubmit();
	}
	// Enter. (Returns false to prevent submitting the form.)
	else if (iKeyPress == 13)
	{
		if (this.aDisplayData.length && this.oSelectedDiv != null)
			this.itemClicked(this.oSelectedDiv);
		return false;
	}
	else if (iKeyPress == 38 || iKeyPress == 40)
	{
		// Up/Down arrow?
		if (!(this.aDisplayData.length && $(this.oSuggestDivHandle).is(':visible')))
			return true;

		// Loop through the display data trying to find our entry.
		var bPrevHandle = false, oToHighlight = null, i;
		for (i = 0; i < this.aDisplayData.length; i++)
		{
			// If we're going up and yet the top one was already selected don't go around.
			if (iKeyPress == 38 && i == 0 && this.oSelectedDiv != null && this.oSelectedDiv == this.aDisplayData[i])
			{
				oToHighlight = this.oSelectedDiv;
				break;
			}
			// If nothing is selected and we are going down then we select the first one.
			if (iKeyPress == 40 && this.oSelectedDiv == null)
			{
				oToHighlight = this.aDisplayData[i];
				break;
			}
			// If the previous handle was the actual previously selected one and we're hitting down then this is the one we want.
			if (iKeyPress == 40 && bPrevHandle != false && bPrevHandle == this.oSelectedDiv)
			{
				oToHighlight = this.aDisplayData[i];
				break;
			}
			// If we're going up and this is the previously selected one then we want the one before, if there was one.
			if (iKeyPress == 38 && bPrevHandle != false && this.aDisplayData[i] == this.oSelectedDiv)
			{
				oToHighlight = bPrevHandle;
				break;
			}
			// Turn this into the previous handle!
			bPrevHandle = this.aDisplayData[i];
		}

		// If we don't have one to highlight by now then it must be the last one that we're after.
		if (oToHighlight == null)
			oToHighlight = bPrevHandle;

		// Remove any old highlighting.
		if (this.oSelectedDiv != null)
			this.itemMouseLeave(this.oSelectedDiv);
		// Mark what the selected div now is.
		this.oSelectedDiv = oToHighlight;
		this.itemMouseEnter(this.oSelectedDiv);
	}

	return true;
};

// Functions for integration.
weAutoSuggest.prototype.registerCallback = function (sCallbackType, sCallback)
{
	this.oCallback[sCallbackType] = sCallback;
};

// User hit submit?
weAutoSuggest.prototype.handleSubmit = function()
{
	// Do we have something that matches the current text?
	for (var bReturnValue = true, entryId = entryName = null, i = 0; i < this.aCache.length; i++)
	{
		var sLastSearch = this.sLastSearch.toLowerCase(), entry = this.aCache[i];

		if (sLastSearch == entry.sItemName.toLowerCase().slice(0, sLastSearch.length))
		{
			// Exact match?
			if (sLastSearch.length == entry.sItemName.length)
			{
				// This is the one!
				entryId = entry.sItemId;
				entryName = entry.sItemName;
				break;
			}
			// Not an exact match, but it'll do for now.
			else
			{
				// If we have two matches don't find anything.
				if (entryId != null)
					bReturnValue = false;
				else
				{
					entryId = entry.sItemId;
					entryName = entry.sItemName;
				}
			}
		}
	}

	if (entryId == null || !bReturnValue || !this.bItemList)
		return bReturnValue;
	else
	{
		this.addItemLink(entryId, entryName, true);
		return false;
	}
};

// Positions the box correctly on the window.
weAutoSuggest.prototype.positionDiv = function ()
{
	// Only do it once.
	if (this.bPositionComplete)
		return;

	this.bPositionComplete = true;

	// Put the div under the text box.
	var aParentPos = this.oTextHandle.offset();

	$(this.oSuggestDivHandle).css({
		left: aParentPos.left,
		top: aParentPos.top + this.oTextHandle.outerHeight() + 1
	});
};

// Do something after clicking an item.
weAutoSuggest.prototype.itemClicked = function (oCurElement)
{
	// Is there a div that we are populating?
	if (this.bItemList)
		this.addItemLink($(oCurElement).data('sItemId'), oCurElement.innerHTML);

	// Otherwise clear things down.
	else
		this.oTextHandle.val(oCurElement.innerHTML.php_unhtmlspecialchars());

	this.oRealTextHandle.val(this.oTextHandle.val());
	this.autoSuggestActualHide();
	this.bPositionComplete = false;
};

// Remove the last searched for name from the search box.
weAutoSuggest.prototype.removeLastSearchString = function ()
{
	// Remove the text we searched for from the div.
	var
		sTempText = this.oTextHandle.val().toLowerCase(),
		iStartString = sTempText.indexOf(this.sLastSearch.toLowerCase());

	// Just attempt to remove the bits we just searched for.
	if (iStartString != -1)
	{
		while (iStartString > 0)
		{
			if (sTempText.charAt(iStartString - 1) == '"' || sTempText.charAt(iStartString - 1) == ',' || sTempText.charAt(iStartString - 1) == ' ')
			{
				iStartString--;
				if (sTempText.charAt(iStartString - 1) == ',')
					break;
			}
			else
				break;
		}

		// Now remove anything from iStartString upwards.
		this.oTextHandle.val(this.oTextHandle.val().slice(0, iStartString));
	}
	// Just take it all.
	else
		this.oTextHandle.val('');
};

// Add a result if not already done.
weAutoSuggest.prototype.addItemLink = function (sItemId, sItemName, bFromSubmit)
{
	// If there's a callback then call it. If it returns false, the item must not be added.
	if (this.oCallback && this.oCallback.onBeforeAddItem)
		if (!this.oCallback.onBeforeAddItem.call(this, sItemId))
			return;

	// Increase the internal item count.
	this.iItemCount++;

	var that = this, eid = 'suggest_' + this.opt.sControlId + '_' + sItemId;
	if (!$('#' + eid).length)
	{
		$('<span id="' + eid + '"></span>').html(
			this.opt.sItemTemplate.wereplace({
				post_name: this.opt.sPostName,
				item_id: sItemId,
				item_href: weUrl((this.opt.sURLMask || 'action=profile;u=%item_id%').wereplace({ item_id: sItemId })),
				item_name: sItemName,
				images_url: we_theme_url + '/images',
				delete_text: this.opt.sTextDeleteItem || $txt['autosuggest_delete_item']
			})
		).appendTo(this.oItemList);
		$('#' + eid).find('img').click(function () { that.deleteAddedItem(sItemId); });
	}

	// Clear the div a bit.
	this.removeLastSearchString();

	// If we came from a submit, and there's still more to go, turn on auto add for all the other things.
	this.bDoAutoAdd = this.oTextHandle.val() != '' && bFromSubmit;

	// Update the fellow...
	this.autoSuggestUpdate();

	// We'll need to recalculate the auto-suggest's position.
	this.bPositionComplete = false;
};

// Delete an item that has been added, if at all?
weAutoSuggest.prototype.deleteAddedItem = function (sItemId)
{
	// Remove the div if it exists...
	if ($('#suggest_' + this.opt.sControlId + '_' + sItemId).remove().length)
		this.iItemCount--; // ...And decrease the internal item count.
};

// Hide the box.
weAutoSuggest.prototype.autoSuggestHide = function ()
{
	// Delay to allow events to propagate through....
	var that = this;
	this.oHideTimer = setTimeout(function () { that.autoSuggestActualHide.call(that); }, 250);
};

// Do the actual hiding after a timeout.
weAutoSuggest.prototype.autoSuggestActualHide = function ()
{
	$(this.oSuggestDivHandle).hide();
	this.oSelectedDiv = null;
};

// Show the box.
weAutoSuggest.prototype.autoSuggestShow = function ()
{
	if (this.oHideTimer)
	{
		clearTimeout(this.oHideTimer);
		this.oHideTimer = false;
	}

	this.positionDiv();

	$(this.oSuggestDivHandle).has(':hidden').slideDown(200);
};

// Populate the actual div.
weAutoSuggest.prototype.populateDiv = function (aResults)
{
	// How many objects can we show at once?
	for (var aNewDisplayData = [], i = 0, j = Math.min(this.opt.iMaxDisplayQuantity || 15, aResults.length); i < j; i++)
		// Create the sub element, and attach some events to it so we can do stuff.
		aNewDisplayData[i] = $('<div></div>')
			.data({ sItemId: aResults[i].sItemId, that: this })
			.html(aResults[i].sItemName)
			.mouseenter(function (oEvent) { $(this).data('that').itemMouseEnter(this); })
			.mouseleave(function (oEvent) { $(this).data('that').itemMouseLeave(this); })
			.click(function (oEvent) { $(this).data('that').itemClicked(this); })[0];

	this.aDisplayData = aNewDisplayData;
	$(this.oSuggestDivHandle).html(aNewDisplayData);
	if (!aResults.length)
		$(this.oSuggestDivHandle).hide();

	return true;
};

// Refocus the element.
weAutoSuggest.prototype.itemMouseEnter = function (oCurElement)
{
	this.oSelectedDiv = oCurElement;
	$(oCurElement).addClass('auto_suggest_hover');
};

// Unfocus the element
weAutoSuggest.prototype.itemMouseLeave = function (oCurElement)
{
	$(oCurElement).removeClass('auto_suggest_hover');
};

weAutoSuggest.prototype.onSuggestionReceived = function (XMLDoc)
{
	var i, ac = [];

	$('item', XMLDoc).each(function (i) {
		ac[i] = { sItemId: $(this).attr('id'), sItemName: $(this).text() };
	});

	// If we're doing auto add and we find the exact person, then add them!
	if (this.bDoAutoAdd)
		for (i in ac)
			if (this.sLastSearch == ac[i].sItemName)
			{
				var sItemId = ac[i].sItemId, sItemName = ac[i].sItemName;
				this.aCache = ac = [];
				return this.addItemLink(sItemId, sItemName, true);
			}

	// Check we don't try to keep auto-updating!
	this.bDoAutoAdd = false;

	// Populate the div.
	this.populateDiv(this.aCache = ac);

	// Make sure we can see it.
	ac.length ? this.autoSuggestShow() : this.autoSuggestHide();

	return true;
};

// Get a new suggestion.
weAutoSuggest.prototype.autoSuggestUpdate = function ()
{
	this.oRealTextHandle.val(this.oTextHandle.val());

	if ($.trim(this.oTextHandle.val()) === '')
	{
		this.populateDiv(this.aCache = []);
		this.autoSuggestHide();

		return true;
	}

	// Nothing changed?
	if (this.oTextHandle.val() == this.sLastDirtySearch)
		return true;

	this.sLastDirtySearch = this.oTextHandle.val();

	// We're only actually interested in the last string.
	var sSearchString = this.oTextHandle.val().replace(/^("[^"]+",[ ]*)+/, '').replace(/^([^,]+,[ ]*)+/, '');
	if (sSearchString[0] == '"')
		sSearchString = sSearchString.slice(1);

	// Stop replication ASAP.
	var sRealLastSearch = this.sLastSearch;
	this.sLastSearch = sSearchString;

	// Either nothing or we've completed a sentence.
	if (sSearchString == '' || sSearchString.slice(-1) == '"')
		return this.populateDiv([]);

	// Nothing?
	var sLowercaseSearch = sSearchString.toLowerCase();
	if (sRealLastSearch.toLowerCase() == sLowercaseSearch)
		return true;

	// How many characters shall we start searching on? Too small?
	else if (sSearchString.length < (this.opt.iMinimumSearchChars || 3))
	{
		this.aCache = [];
		this.autoSuggestHide();
		return true;
	}
	else if (sSearchString.slice(0, sRealLastSearch.length) == sRealLastSearch)
	{
		// Instead of hitting the server again, just narrow down the results...
		for (var aNewCache = [], j = 0, k = 0; k < this.aCache.length; k++)
			if (this.aCache[k].sItemName.slice(0, sSearchString.length).toLowerCase() == sLowercaseSearch)
				aNewCache[j++] = this.aCache[k];

		this.aCache = [];
		if (aNewCache.length)
		{
			// Repopulate.
			this.populateDiv(this.aCache = aNewCache);

			// Can it be seen?
			this.autoSuggestShow();

			return true;
		}
	}

	// In progress means destroy!
	if (this.oXmlRequestHandle && this.oXmlRequestHandle.abort)
		this.oXmlRequestHandle.abort();

	var data = {
		suggest_type: this.opt.sSearchType,
		search: sSearchString,
		time: $.now()
	};
	data[we_sessvar] = we_sessid;

	// Get the document.
	this.oXmlRequestHandle = $.ajax(
		weUrl('action=suggest'),
		{
			context: this,
			data: data,
			success: this.onSuggestionReceived
		}
	);

	return true;
};
