/**
 * Wedge
 *
 * Helper functions for the media admin area.
 * Uses portions written by Shitiz Garg.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// !!! Needs a rewrite to use the Wedge toggle...
function admin_toggle(id)
{
	if ($('#tr_expand_' + id).is(':hidden'))
	{
		$('#img_' + id).load(function () {
			$('#tr_expand_' + id).show();
			$(this).unbind();
		}).attr('src', smf_prepareScriptUrl(smf_scripturl) + 'action=media;sa=media;in=' + id + ';icon');
	}
	else
	{
		$('#tr_expand_' + id).hide();
		$('#img_' + id).attr('src', '');
	}
	$('#toggle_img_' + id).toggleClass('fold');

	return false;
}

function admin_toggle_all()
{
	$('tr').filter(function () {
		return this.id.substr(0, 9) == 'tr_expand';
	}).each(function () {
		admin_toggle(all_tr[i].id.substr(10));
	});

	return false;
}

function doSubAction(url)
{
	getXMLDocument(url, function (XMLDoc) {
		var id = $('ret id', XMLDoc).text();
		if ($('ret succ', XMLDoc).text() == 'true')
			$('#' + id + ', #tr_expand_' + id).hide();
	});
	return false;
}

function getPermAlbums(id_profile, args)
{
	sendXMLDocument(location.href + (typeof args != 'undefined' ? args : '') + ';sa=albums', 'prof=' + id_profile, function (XMLDoc) {
		var id_profile = $('albums id_profile', XMLDoc).text();
		$('#albums_td_' + id_profile).html($('albums album_string', XMLDoc).text()).show();
		$('#albums_' + id_profile).show();
	});
	return false;
}

function permDelCheck(id, el, conf_text)
{
	if (!confirm(conf_text))
	{
		el.checked = '';
		return;
	}

	var opts = document.getElementsByName('del_prof')[0].getElementsByTagName('option');

	for (var i = 0; i < opts.length; i++)
	{
		if (opts[i].value == id)
		{
			opts[i].style.display = el.checked ? 'none' : '';
			break;
		}
	}
}

function aeva_prepareScriptUrl(sUrl)
{
	return sUrl.indexOf('?') == -1 ? sUrl + '?' : sUrl + (sUrl.charAt(sUrl.length - 1) == '?' || sUrl.charAt(sUrl.length - 1) == '&' || sUrl.charAt(sUrl.length - 1) == ';' ? '' : ';');
}
