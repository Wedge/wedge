/*!
 * Wedge
 *
 * Helper functions for the personal messages send form
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function wePersonalMessageSend(oOptions)
{
	this.opt = oOptions;
	this.oToListContainer = null;

	var
		oToAutoSuggest = null,
		oBccAutoSuggest = null,

		// Prevent items to be added twice or to both the 'To' and 'Bcc'.
		onAddItem = function (sSuggestId)
		{
			oToAutoSuggest.deleteAddedItem(sSuggestId);
			oBccAutoSuggest.deleteAddedItem(sSuggestId);

			return true;
		};

	if (!this.opt.bBccShowByDefault)
	{
		// Hide the BCC control.
		$('#' + this.opt.sBccDivId + ', #' + this.opt.sBccDivId2).hide();

		// Show the link to set the BCC control back.
		$('#' + this.opt.sBccLinkContainerId).show();

		// Make the link show the BCC control.
		$('#' + this.opt.sBccLinkId).data('that', this).click(function () { return !!$(this).data('that').showBcc(); });
	}

	oToAutoSuggest = new weAutoSuggest({
		bItemList: true,
		sControlId: this.opt.sToControlId,
		sPostName: 'recipient_to',
		sTextDeleteItem: this.opt.sTextDeleteItem,
		aListItems: this.opt.aToRecipients
	});
	oToAutoSuggest.registerCallback('onBeforeAddItem', onAddItem);

	oBccAutoSuggest = new weAutoSuggest({
		bItemList: true,
		sControlId: this.opt.sBccControlId,
		sPostName: 'recipient_bcc',
		sTextDeleteItem: this.opt.sTextDeleteItem,
		aListItems: this.opt.aBccRecipients
	});
	oBccAutoSuggest.registerCallback('onBeforeAddItem', onAddItem);
};

wePersonalMessageSend.prototype.showBcc = function ()
{
	// No longer hide it, show it to the world!
	$('#' + this.opt.sBccDivId + ', #' + this.opt.sBccDivId2).show();
};
