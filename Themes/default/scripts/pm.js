/*!
 * Wedge
 *
 * Helper functions for the personal messages send form
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

@language PersonalMessage

function wePersonalMessageSend(opt)
{
	this.opt = opt;
	var that = this;

	this.oToAutoSuggest = null;
	this.oBccAutoSuggest = null;

	if (!this.opt.bBccShowByDefault)
	{
		// Hide the BCC control.
		$('#' + this.opt.sBccDivId + ', #' + this.opt.sBccDivId2).hide();

		// Show the link to set the BCC control back.
		$('#' + this.opt.sBccLinkContainerId).show();

		// Make the link show the BCC control.
		$('#' + this.opt.sBccLinkId).click( function() { return that.showBcc(); } );
	}

	this.oToAutoSuggest = new weAutoSuggest({
		bItemList: true,
		sControlId: this.opt.sToControlId,
		sPostName: 'recipient_to',
		aListItems: this.opt.aToRecipients
	});
	this.oToAutoSuggest.registerCallback('onBeforeAddItem', function (sSuggestId) { return that.onAddItem(sSuggestId); });

	this.oBccAutoSuggest = new weAutoSuggest({
		bItemList: true,
		sControlId: this.opt.sBccControlId,
		sPostName: 'recipient_bcc',
		aListItems: this.opt.aBccRecipients
	});
	this.oBccAutoSuggest.registerCallback('onBeforeAddItem', function (sSuggestId) { return that.onAddItem(sSuggestId); });

	// Is there a contact list?
	if (this.opt.sContactList != '')
		this.initContacts(this.opt.sObject);
}

wePersonalMessageSend.prototype.showBcc = function ()
{
	// No longer hide it, show it to the world!
	$('#' + this.opt.sBccDivId + ', #' + this.opt.sBccDivId2).show();
	return false;
};

wePersonalMessageSend.prototype.onAddItem = function (sSuggestId)
{
	// Prevent items to be added twice or to both the 'To' and 'Bcc'.
	this.oToAutoSuggest.deleteAddedItem(sSuggestId);
	this.oBccAutoSuggest.deleteAddedItem(sSuggestId);

	return true;
};

wePersonalMessageSend.prototype.initContacts = function (sObject)
{
	$('#' + this.opt.sContactList + ' tr').each(function () {
		$(this).append('<td><input type="button" value="' + $txt['pm_to'] + '" class="to" onclick="' + sObject + '.addContact(\'to\', ' + $(this).data('uid') + ', \'' + $(this).data('name') + '\');"></td>');
		$(this).append('<td><input type="button" value="' + $txt['pm_bcc'] + '" class="bcc" onclick="' + sObject + '.addContact(\'bcc\', ' + $(this).data('uid') + ', \'' + $(this).data('name') + '\');"></td>');
	});
};

wePersonalMessageSend.prototype.addContact = function (where, uid, name)
{
	if (where == 'to')
		this.oToAutoSuggest.addItemLink(uid, name, false);
	else if (where == 'bcc')
	{
		this.showBcc();
		this.oBccAutoSuggest.addItemLink(uid, name, false);
	}
};

function expandCollapseLabels()
{
	$('#searchLabelsExpand').toggle(300);
	$('#expandLabelsIcon').toggleClass('fold');
}
