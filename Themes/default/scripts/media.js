/*!
 * Wedge Media
 * media.js
 * © wedge.org
 *
 * Users of this software are bound by the terms of the
 * Wedge license. You can view it online at http://wedgeforum.com/license/
 *
 * Support and updates for this software can be found at
 * http://wedge.org
 */

function selectText(box)
{
	box.focus();
	box.select();
}

function ajaxRating()
{
	$('#ratingElement').html('<img src="' + (typeof smf_default_theme_url == "undefined" ? smf_theme_url : smf_default_theme_url) + '/images/aeva/loading.gif">');
	sendXMLDocument($('#ratingForm').attr('action') + ';xml', 'rating=' + $('#rating').value(), ajaxRating2);
}

function ajaxRating2(XMLDoc)
{
	$('#ratingElement').html($('ratingObject', XMLDoc).text());
}

function aevaDelConfirm(txt)
{
	var sel = $('#modtype')[0];
	return sel && sel.options[sel.selectedIndex].value == 'delete' ? confirm(txt) : true;
}

// This function is only used by IE6 because it can't cope with td:hover. Stupid IE6...
function mouseo(id, over)
{
	$('#visio_' + id).css('visibility', over ? 'hidden' : 'visible');
	window.event.cancelBubble = true;
}
