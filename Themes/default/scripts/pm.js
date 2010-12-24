
// Handle the JavaScript surrounding personal messages send form.
function smf_PersonalMessageSend(oOptions)
{
	this.opt = oOptions;
	this.oToAutoSuggest = null;
	this.oBccAutoSuggest = null;
	this.oToListContainer = null;

	if (!this.opt.bBccShowByDefault)
	{
		// Hide the BCC control.
		$('#' + this.opt.sBccDivId + ',#' + this.opt.sBccDivId2).hide();

		// Show the link to set the BCC control back.
		$('#' + this.opt.sBccLinkContainerId).show().html(this.opt.sShowBccLinkTemplate);

		// Make the link show the BCC control.
		$('#' + this.opt.sBccLinkId).data('that', this).click(function () { return !!$(this).data('that').showBcc(); });
	}

	this.oToAutoSuggest = new smc_AutoSuggest({
		sSelf: this.opt.sSelf + '.oToAutoSuggest',
		sSessionId: this.opt.sSessionId,
		sSessionVar: this.opt.sSessionVar,
		sControlId: this.opt.sToControlId,
		sPostName: 'recipient_to',
		sURLMask: 'action=profile;u=%item_id%',
		sTextDeleteItem: this.opt.sTextDeleteItem,
		bItemList: true,
		sItemListContainerId: 'to_item_list_container',
		aListItems: this.opt.aToRecipients
	});
	this.oToAutoSuggest.registerCallback('onBeforeAddItem', this.oToAutoSuggest.callbackAddItem);

	this.oBccAutoSuggest = new smc_AutoSuggest({
		sSelf: this.opt.sSelf + '.oBccAutoSuggest',
		sSessionId: this.opt.sSessionId,
		sSessionVar: this.opt.sSessionVar,
		sControlId: this.opt.sBccControlId,
		sPostName: 'recipient_bcc',
		sURLMask: 'action=profile;u=%item_id%',
		sTextDeleteItem: this.opt.sTextDeleteItem,
		bItemList: true,
		sItemListContainerId: 'bcc_item_list_container',
		aListItems: this.opt.aBccRecipients
	});
	this.oBccAutoSuggest.registerCallback('onBeforeAddItem', this.oBccAutoSuggest.callbackAddItem);
};

smf_PersonalMessageSend.prototype.showBcc = function()
{
	// No longer hide it, show it to the world!
	$('#' + this.opt.sBccDivId + ',#' + this.opt.sBccDivId2).show();
};

// Prevent items to be added twice or to both the 'To' and 'Bcc'.
smf_PersonalMessageSend.prototype.callbackAddItem = function(oAutoSuggestInstance, sSuggestId)
{
	this.oToAutoSuggest.deleteAddedItem(sSuggestId);
	this.oBccAutoSuggest.deleteAddedItem(sSuggestId);

	return true;
};
