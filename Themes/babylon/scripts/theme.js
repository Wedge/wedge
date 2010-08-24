function smf_addButton(sButtonStripId, bUseImage, oOptions)
{
	var oButtonStrip = document.getElementById(sButtonStripId);

	// Add the button.
	var oNewButton = document.createElement('span');
	setInnerHTML(oNewButton, ' <a href="' + oOptions.sUrl + '" ' + (typeof(oOptions.sCustom) == 'string' ? oOptions.sCustom : '') + '><span class="last"' + (typeof(oOptions.sId) == 'string' ? ' id="' + oOptions.sId + '"': '') + '>' + (bUseImage ? '<img src="' + oOptions.sImage + '" alt="' + oOptions.sText + '" />' : oOptions.sText) + '</span></a>');

	oButtonStrip.appendChild(oNewButton);
}