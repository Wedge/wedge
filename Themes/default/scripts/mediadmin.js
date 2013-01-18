/*!
 * Wedge
 *
 * Helper functions for the media admin area.
 * Uses portions written by Shitiz Garg.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function admin_toggle(id)
{
	if ($('#tr_expand_' + id).is(':hidden'))
	{
		var $img = $('#img_' + id);
		if ($img[0])
			$img.load(function () {
				$('#tr_expand_' + id).show().find('td').children().hide().slideDown();
				$(this).unbind();
			}).attr('src', weUrl('action=media;sa=media;in=' + id + ';icon'));
		else
			$('#tr_expand_' + id).show().find('td').children().hide().slideDown();
	}
	else
		$('#tr_expand_' + id).find('td').children().slideUp(500, function () { $(this).parent().parent().hide(); });

	$('#toggle_img_' + id).toggleClass('fold');

	return false;
}

function admin_toggle_all()
{
	$('tr[id^="tr_expand"]').each(function () {
		admin_toggle(this.id.slice(10));
	});

	return false;
}

function doSubAction(url)
{
	$.post(url, function (XMLDoc) {
		var id = $('ret id', XMLDoc).text();
		if ($('ret succ', XMLDoc).text() == 'true')
			$('#' + id + ', #tr_expand_' + id).hide();
	});
	return false;
}

function getPermAlbums(id_profile, args)
{
	$.post(location + (args || '') + ';sa=albums', 'prof=' + id_profile, function (XMLDoc) {
		var id_profile = $('albums id_profile', XMLDoc).text();
		$('#albums_td_' + id_profile).html($('albums album_string', XMLDoc).text()).show();
		$('#albums_' + id_profile).show();
	});
	return false;
}

function permDelCheck(e, id, el)
{
	if (el.checked && !ask(we_confirm, e))
	{
		el.checked = '';
		return;
	}

	$('select[name="del_prof"] option[value=' + id + ']').toggle(!el.checked);
	$('select[name="del_prof"]').sb();
}
