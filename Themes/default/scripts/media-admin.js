// Wedge Media
// © wedgeforum.com
// media_admin.js
//
// Users of this software are bound by the terms of the
// Wedge license. You can view it online at http://wedgeforum.com/license/
//
// Support and updates for this software can be found at
// http://wedgeforum.com

function admin_prune_toggle(show, hide)
{
	$('#' + show + '_prune_opts').show();
	$('#' + hide + '_prune_opts').hide();
}

// !!! Needs a rewrite to use the Wedge toggle...
function admin_toggle(id)
{
	if ($('#tr_expand_' + id).has(':hidden'))
	{
		$('#tr_expand_' + id).show();
		$('#toggle_img_' + id).attr('src', smf_images_url + '/collapse.gif');
		$('#img_' + id).attr('src', smf_prepareScriptUrl(smf_scripturl) + 'action=media;sa=media;in=' + id + ';icon');
	}
	else
	{
		$('#tr_expand_' + id).hide();
		$('#toggle_img_' + id).attr('src', smf_images_url + '/expand.gif');
		$('#img_' + id).attr('src', '');
	}
}

function admin_toggle_all()
{
	$('tr').filter(function () { return this.id.substr(0, 9) == 'tr_expand'; }).each(function () {
		var id = all_tr[i].id.substr(10);
		admin_toggle(id, $('#img_' + id).length > 0);
	});
}

function doSubAction(url)
{
	getXMLDocument(url, doSubAction2);
}

function doSubAction2(XMLDoc)
{
	var id = $('ret id', XMLDoc).text();

	if ($('ret succ', XMLDoc).text() == 'true')
		$('#' + id + ', #tr_expand_' + id).hide();
}

function getPermAlbums(id_profile, args)
{
	sendXMLDocument(location.href + (typeof args != 'undefined' ? args : '') + ';sa=albums', 'prof=' + id_profile, getPermAlbum2);
}

function getPermAlbum2(XMLDoc)
{
	var id_profile = $('albums id_profile', XMLDoc).text(), albums = $('albums album_string', XMLDoc).text();
	$('#albums_td_' + id_profile).html(albums).show();
	$('#albums_' + id_profile).show();
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
