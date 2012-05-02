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

function wePersonalMessageSend(opt)
{
	var
		oToAutoSuggest = null,
		oBccAutoSuggest = null,

		showBcc = function ()
		{
			// No longer hide it, show it to the world!
			$('#' + opt.sBccDivId + ', #' + opt.sBccDivId2).show();
		},

		// Prevent items to be added twice or to both the 'To' and 'Bcc'.
		onAddItem = function (sSuggestId)
		{
			oToAutoSuggest.deleteAddedItem(sSuggestId);
			oBccAutoSuggest.deleteAddedItem(sSuggestId);

			return true;
		};

	if (!opt.bBccShowByDefault)
	{
		// Hide the BCC control.
		$('#' + opt.sBccDivId + ', #' + opt.sBccDivId2).hide();

		// Show the link to set the BCC control back.
		$('#' + opt.sBccLinkContainerId).show();

		// Make the link show the BCC control.
		$('#' + opt.sBccLinkId).click(showBcc);
	}

	oToAutoSuggest = new weAutoSuggest({
		bItemList: true,
		sControlId: opt.sToControlId,
		sPostName: 'recipient_to',
		sTextDeleteItem: opt.sTextDeleteItem,
		aListItems: opt.aToRecipients
	});
	oToAutoSuggest.registerCallback('onBeforeAddItem', onAddItem);

	oBccAutoSuggest = new weAutoSuggest({
		bItemList: true,
		sControlId: opt.sBccControlId,
		sPostName: 'recipient_bcc',
		sTextDeleteItem: opt.sTextDeleteItem,
		aListItems: opt.aBccRecipients
	});
	oBccAutoSuggest.registerCallback('onBeforeAddItem', onAddItem);
}
