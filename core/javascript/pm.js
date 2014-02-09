/*!
 * Helper functions for the personal messages send form
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

@language PersonalMessage

function weSendPM(opt)
{
	if (!opt.bBccShowByDefault)
	{
		// Hide the BCC control.
		$('#' + opt.sBccDivId + ', #' + opt.sBccDivId2).hide();

		// Show the link to set the BCC control back.
		$('#' + opt.sBccLinkContainerId).show();

		// Make the link show the BCC control.
		$('#' + opt.sBccLinkId).click(function() {
			$('#' + opt.sBccDivId + ', #' + opt.sBccDivId2).show();
			return false;
		});
	}

	var
		oToAutoSuggest = new weAutoSuggest({
			minChars: opt.minChars,
			bItemList: true,
			sControlId: opt.sToControlId,
			sPostName: 'recipient_to',
			aListItems: opt.aToRecipients
		}),
		oBccAutoSuggest = new weAutoSuggest({
			minChars: opt.minChars,
			bItemList: true,
			sControlId: opt.sBccControlId,
			sPostName: 'recipient_bcc',
			aListItems: opt.aBccRecipients
		});

	oToAutoSuggest.registerCallback('onBeforeAddItem', function (sSuggestId) {
		// Prevent items to be added twice or to both the 'To' and 'Bcc'.
		oToAutoSuggest.deleteAddedItem(sSuggestId);
		oBccAutoSuggest.deleteAddedItem(sSuggestId);
		return true;
	});
	oBccAutoSuggest.registerCallback('onBeforeAddItem', function (sSuggestId) {
		// Prevent items to be added twice or to both the 'To' and 'Bcc'.
		oToAutoSuggest.deleteAddedItem(sSuggestId);
		oBccAutoSuggest.deleteAddedItem(sSuggestId);
		return true;
	});

	// Is there a contact list? If not, a search on $('# tr') will return an empty set anyway.
	$('#' + opt.sContactList + ' tr').each(function () {
		var that = $(this);
		// The \u00a0 (nbsp) replacement is for the French language. ;)
		$('<td/>')
			.append($('<input type="button" class="to">').val($txt['pm_to'].replace(/\u00a0/, '')).click(function () {
				oToAutoSuggest.addItemLink(that.data('uid'), that.data('name'), false);
			}))
			.append($('<input type="button" class="bcc">').val($txt['pm_bcc'].replace(/\u00a0/, '')).click(function () {
				$('#' + opt.sBccDivId + ', #' + opt.sBccDivId2).show();
				oBccAutoSuggest.addItemLink(that.data('uid'), that.data('name'), false);
			}))
			.appendTo(this);
	});
}

function expandCollapseLabels()
{
	$('#searchLabelsExpand').toggle(300);
	$('#expandLabelsIcon').toggleClass('fold');
}
