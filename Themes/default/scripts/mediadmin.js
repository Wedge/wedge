/**
 * Wedge
 *
 * Helper functions for the media admin area.
 * Uses portions written by Shitiz Garg.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
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
		}).attr('src', weUrl() + 'action=media;sa=media;in=' + id + ';icon');
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
	$('tr[id^="tr_expand"]').each(function () {
		admin_toggle(this.id.substr(10));
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
	if (el.checked && !confirm(conf_text))
	{
		el.checked = '';
		return;
	}

	$('select[name="del_prof"] option[value=' + id + ']').toggle(!el.checked);
	$('select[name="del_prof"]').sb();
}
