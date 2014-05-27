<?php
/**
 * The media template. This is where we show the gallery.
 * Uses portions written by Shitiz Garg.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

function template_aeva_header()
{
	global $context, $txt, $amSettings;

	// Show Media tabs, except if not inside the gallery itself or if uploading via a popup
	if (empty($context['current_board']) && !isset($_REQUEST['noh']))
		echo '
	<we:cat>
		', !isset($context['page_title']) ? $txt['media_gallery'] : $context['page_title'], '
	</we:cat>';

	// Any unapproved stuff?
	if (aeva_allowedTo('moderate') && (!empty($amSettings['num_unapproved_items']) || !empty($amSettings['num_unapproved_comments']) || !empty($amSettings['num_unapproved_albums'])))
	{
		echo '
	<div class="unapproved_notice">';
		if (!empty($amSettings['num_unapproved_items']))
			printf($txt['media_unapproved_items_notice'] . '<br>', '<URL>?action=media;area=moderate;sa=submissions;filter=items;' . $context['session_query'], $amSettings['num_unapproved_items']);
		if (!empty($amSettings['num_unapproved_comments']))
			printf($txt['media_unapproved_coms_notice'] . '<br>', '<URL>?action=media;area=moderate;sa=submissions;filter=coms;' . $context['session_query'], $amSettings['num_unapproved_comments']);
		if (!empty($amSettings['num_unapproved_albums']))
			printf($txt['media_unapproved_albums_notice'], '<URL>?action=media;area=moderate;sa=submissions;filter=albums;' . $context['session_query'], $amSettings['num_unapproved_albums']);
		echo '</div>';
	}

	// Any reported stuff?
	if (aeva_allowedTo('moderate') && (!empty($amSettings['num_reported_items']) || !empty($amSettings['num_reported_comments'])))
	{
		echo '
	<div class="unapproved_notice">';
		if (!empty($amSettings['num_reported_items']))
			printf($txt['media_reported_items_notice'] . '<br>', '<URL>?action=media;area=moderate;sa=reports;items;' . $context['session_query'], $amSettings['num_reported_items']);
		if (!empty($amSettings['num_reported_comments']))
			printf($txt['media_reported_comments_notice'] . '<br>', '<URL>?action=media;area=moderate;sa=reports;comments;' . $context['session_query'], $amSettings['num_reported_comments']);
		echo '</div>';
	}
}

function template_aeva_tabs()
{
	global $context;

	if (!empty($context['aeva_tabs']))
		template_show_generic_tabs($context['aeva_tabs']);
}

function template_aeva_home()
{
	global $context, $amSettings, $txt, $galurl, $settings;

	$has_albums = count($context['aeva_albums']) > 0;
	$can_feed = !empty($settings['xmlnews_enable']);

	// The Albums!
	echo '
<div id="home">', !empty($context['aeva_welcome']) ? '
	<div id="aeva_welcome">' . $context['aeva_welcome'] . '</div>' : '', '
	<div id="aeva_toplinks">
		<we:title>
			<img src="', ASSETS, '/aeva/house.png"> ', $txt['media_home'], $context['show_albums_link'] ? ' -
			<img src="' . ASSETS . '/aeva/album.png"> <a href="' . $galurl . 'sa=vua">' . $txt['media_albums'] . '</a>' : '', empty($amSettings['disable_playlists']) ? ' -
			<img src="' . ASSETS . '/aeva/playlist.png"> <a href="' . $galurl . 'sa=playlists">' . $txt['media_playlists'] . '</a>' : '', '
		</we:title>
	</div>';

	$context['aeva_windowbg'] = '';
	if ($has_albums)
		aeva_listChildren($context['aeva_albums']);

	echo "\n";
	$view = isset($_REQUEST['fw']) ? 'file' : 'normal';

	// Recent items?
	if (!empty($context['recent_items']))
	{
		echo '
	<we:title style="margin-top: 8px">
		', $txt['media_recent_items'], $can_feed ?
		' <a href="<URL>?action=feed;sa=media" class="feed_icon" title="' . $txt['feed'] . '"></a>' : '', '
	</we:title>';

		// Page index and sorting things
		$sort_list = array('m.id_media' => 0, 'm.time_added' => 1, 'm.title' => 2, 'm.views' => 3, 'm.weighted' => 4);
		$more_list = isset($_GET['fw']) ? 'fw;' : '';
		$more_sort = isset($_GET['sort']) ? 'sort=' . $_GET['sort'] . ';' : '';
		$more_asc = $context['aeva_asc'] ? 'asc;' : '';
		echo '
	<we:block id="recent_pics" class="windowbg">';

		template_aeva_sort_options(
			substr($galurl, 0, -1)
		);

		echo '
		<div>',
			$view == 'normal' ? aeva_listItems($context['recent_items']) : aeva_listFiles($context['recent_items']), '
		</div>
		<div class="pagesection" style="padding-bottom: 0">
			<nav>', $txt['pages'], ': ', $context['aeva_page_index'], '</nav>
		</div>
	</we:block>';
	}

	// Random items?
	if (!empty($context['random_items']))
	{
		echo '
	<we:title>
		', $txt['media_random_items'], '
	</we:title>
	<we:block id="random_pics" class="windowbg">',
		$view == 'normal' ? aeva_listItems($context['random_items']) : aeva_listFiles($context['random_items']), '
	</we:block>';
	}

	// Recent comments!
	if (!empty($context['recent_comments']))
	{
		echo '
	<div', !empty($context['recent_albums']) ? ' class="recent_comments"' : '', '>
		<we:title>
			', $txt['media_recent_comments'], $can_feed ?
			' <a href="<URL>?action=feed;sa=media;type=comments" class="feed_icon" title="' . $txt['feed'] . '"></a>' : '', '
		</we:title>
		<div class="windowbg wrc" style="line-height: 1.4em">';

		foreach ($context['recent_comments'] as $i)
			echo '
			<div>', $txt['media_comment_in'], ' <a href="', $i['url'], '">', $i['media_title'], '</a> ', $txt['media_by'],
			' ', $i['member_link'], ' ', is_numeric($i['posted_on'][0]) ? $txt['media_on_date'] . ' ' : '', $i['posted_on'], '</div>';

		echo '
		</div>
	</div>';
	}

	// Recent albums!
	if (!empty($context['recent_albums']))
	{
		echo '
	<div', !empty($context['recent_comments']) ? ' class="recent_albums"' : '', '>
		<we:title>
			', $txt['media_recent_albums'], '
		</we:title>
		<div class="windowbg wrc" style="line-height: 1.4em">';

		foreach ($context['recent_albums'] as $i)
			echo '
			<div><a href="<URL>?action=media;sa=album;in=', $i['id'], '">', $i['name'], '</a> (',
		$i['num_items'], ')', !empty($i['owner_id']) ? ' ' . $txt['media_by'] . ' <a href="<URL>?action=profile;u=' . $i['owner_id'] . ';area=aeva">' . $i['owner_name'] . '</a>' : '', '</div>';

		echo '
		</div>
	</div>';
	}

	// Fix
	if (!empty($context['recent_comments']) && !empty($context['recent_albums']))
		echo '<br class="clear">';

	// Show some general stats'n'stuff below
	echo '
	<we:title>
		<img src="', ASSETS, '/aeva/chart_bar.png" class="vam">&nbsp;<a href="', $galurl, 'sa=stats">', $txt['media_stats'], '</a>
	</we:title>
	<div class="windowbg wrc" style="line-height: 1.4em">
		<table class="w100 cs0 cp0"><tr><td>
			<div>', $txt['media_total_items'], ': ', $amSettings['total_items'], '</div>
			<div>', $txt['media_total_albums'], ': ', $amSettings['total_albums'], '</div>
			<div>', $txt['media_total_comments'], ': ', $amSettings['total_comments'], '</div>', allowedTo('media_manage') ? '
			' . show_stat($txt['media_reported_items'], $amSettings['num_reported_items']) : '', '
		</td>';

	if (aeva_allowedTo('moderate'))
	{
		echo '
		<td style="text-align: right">
			', show_stat($txt['media_unapproved_items'], $amSettings['num_unapproved_items']), '
			', show_stat($txt['media_unapproved_albums'], $amSettings['num_unapproved_albums']), '
			', show_stat($txt['media_unapproved_comments'], $amSettings['num_unapproved_comments']), allowedTo('media_manage') ? '
			' . show_stat($txt['media_reported_comments'], $amSettings['num_reported_comments']) : '', '
		</td>';
	}
	echo '</tr></table>
	</div>
</div>';
}

function show_stat($intro, $number)
{
	if (empty($number))
		return '<div>' . $intro . ': ' . $number . '</div>';
	return '<div><b>' . $intro . ': ' . $number . '</b></div>';
}

function show_prevnext($id, $url)
{
	global $galurl;

	$trans = $url[3] == 'transparent' ? ' ping' : '';

	return '<div class="aea' . $trans . '" style="width: ' . $url[1] . 'px; height: ' . $url[2] . 'px; background: url(' . $url[0] . ') 0 0">' .
			($id ? '<a href="' . $galurl . 'sa=item;in=' . $id . '">&nbsp;</a></div>' : '&nbsp;</div>');
}

function template_aeva_item_init()
{
	global $item, $context, $options, $txt;

	add_js_file('topic.js');

	add_js('
	var oQuickReply = new QuickReply({
		bDefaultCollapsed: ', !empty($options['display_quick_reply']) && $options['display_quick_reply'] == 2 ? 'false' : 'true', ',
		sContainerId: "qr_options",
		sImageId: "qr_expand",
		sJumpAnchor: "quickreply",
		sBbcDiv: "', $context['postbox']->show_bbc ? 'bbcBox_message' : '', '",
		sSmileyDiv: "', !empty($context['postbox']->smileys['postform']) || !empty($context['postbox']->smileys['popup']) ? 'smileyBox_message' : '', '",
		sSwitchMode: "switch_mode",
		bUsingWysiwyg: ', $context['postbox']->rich_active ? 'true' : 'false', '
	});');

	$item = $context['item_data'];

	if (isset($_REQUEST['noh']))
		echo sprintf($txt['media_foxy_add_tag'], 'javascript:insertTag();');

	// Show the item and info boxes
	echo '
	<div id="viewitem">';
}

function template_aeva_item_prevnext()
{
	global $item, $galurl, $amSettings, $txt;

	if (empty($amSettings['prev_next'])) // 3 items
		echo '
		<table class="mg_prevnext windowbg w100 cs0 cp4">
			<tr class="mg_prevnext_pad">
				<td rowspan="2">', (int) $item['prev'] > 0 ? '<a href="' . $galurl . 'sa=item;in=' . $item['prev'] . '">&lang;&lang;</a>' : '&lang;&lang;', '</td>
				<td style="width: 33%">', (int) $item['prev'] > 0 ? show_prevnext($item['prev'], $item['prev_thumb']) : $txt['media_prev'], '</td>
				<td style="width: 34%" class="windowbg2">', show_prevnext(0, $item['current_thumb']), '</td>
				<td style="width: 33%">', (int) $item['next'] > 0 ? show_prevnext($item['next'], $item['next_thumb']) : $txt['media_next'], '</td>
				<td rowspan="2">', (int) $item['next'] > 0 ? '<a href="' . $galurl . 'sa=item;in=' . $item['next'] . '">&rang;&rang;</a>' : '&rang;&rang;', '</td>
			</tr>
			<tr class="smalltext">
				<td>', (int) $item['prev'] > 0 ? '<a href="' . $galurl . 'sa=item;in=' . $item['prev'] . '">' . $item['prev_title'] . '</a>' : '', '</td>
				<td class="windowbg2">', $item['current_title'], '</td>
				<td>', (int) $item['next'] > 0 ? '<a href="' . $galurl . 'sa=item;in=' . $item['next'] . '">' . $item['next_title'] . '</a>' : '', '</td>
			</tr>
		</table>';
	elseif ($amSettings['prev_next'] == 1) // 5 items
		echo '
		<table class="mg_prevnext windowbg w100 cs0 cp4">
			<tr class="mg_prevnext_pad">
				<td rowspan="2">', (int) $item['prev_page'] > 0 ? '<a href="' . $galurl . 'sa=item;in=' . $item['prev_page'] . '">&laquo;</a>' : '&laquo;', '</td>
				<td style="width: 20%">', (int) $item['prev2'] > 0 ? show_prevnext($item['prev2'], $item['prev2_thumb']) : '', '</td>
				<td style="width: 20%">', (int) $item['prev'] > 0 ? show_prevnext($item['prev'], $item['prev_thumb']) : '', '</td>
				<td style="width: 20%" class="windowbg2">', show_prevnext(0, $item['current_thumb']), '</td>
				<td style="width: 20%">', (int) $item['next'] > 0 ? show_prevnext($item['next'], $item['next_thumb']) : '', '</td>
				<td style="width: 20%">', (int) $item['next2'] > 0 ? show_prevnext($item['next2'], $item['next2_thumb']) : '', '</td>
				<td rowspan="2">', (int) $item['next_page'] > 0 ? '<a href="' . $galurl . 'sa=item;in=' . $item['next_page'] . '">&raquo;</a>' : '&raquo;', '</td>
			</tr>
			<tr class="smalltext">
				<td>', (int) $item['prev2'] > 0 ? '<a href="' . $galurl . 'sa=item;in=' . $item['prev2'] . '">' . $item['prev2_title'] . '</a>' : '', '</td>
				<td>', (int) $item['prev'] > 0 ? '<a href="' . $galurl . 'sa=item;in=' . $item['prev'] . '">' . $item['prev_title'] . '</a>' : '', '</td>
				<td class="windowbg2">', $item['current_title'], '</td>
				<td>', (int) $item['next'] > 0 ? '<a href="' . $galurl . 'sa=item;in=' . $item['next'] . '">' . $item['next_title'] . '</a>' : '', '</td>
				<td>', (int) $item['next2'] > 0 ? '<a href="' . $galurl . 'sa=item;in=' . $item['next2'] . '">' . $item['next2_title'] . '</a>' : '', '</td>
			</tr>
		</table>';
	elseif ($amSettings['prev_next'] == 2) // Text links
		echo '
		<div class="mg_prev">', (int) $item['prev'] > 0 ? '&laquo; <a href="' . $galurl . 'sa=item;in=' . $item['prev'] . '">' . $txt['media_prev'] . '</a>' : '&laquo; ' . $txt['media_prev'], '</div>
		<div class="mg_next">', (int) $item['next'] > 0 ? '<a href="' . $galurl . 'sa=item;in=' . $item['next'] . '">' . $txt['media_next'] . '</a> &raquo;' : $txt['media_next'] . ' &raquo;', '</div>';
}

function template_aeva_item_wrap_begin()
{
	echo '
		<div id="itembox">';
}

function template_aeva_item_main_before()
{
	echo '
		<div id="media-item">';
}

function template_aeva_item_main_item()
{
	global $item, $context, $txt;

	echo '<div id="item-area">', $item['embed_object'], $item['is_resized'] ? '
			<dfn style="padding-top: 6px">' . $txt['media_resized'] . '</dfn>' : '', '<br>';

	if (!empty($item['description']))
	{
		$desc_len = westr::strlen($item['description']);
		echo '
			<div class="mg_item_desc" style="text-align: ' . ($desc_len > 200 ? 'justify' : 'center') . '; width: ' . ($desc_len > 800 ? '90%' : max($item['preview_width'], 400) . 'px') . '">' . $item['description'] . '</div>';
	}

	if ($context['aeva_size_mismatch'])
		echo '
			<div class="unapproved_yet">', $txt['media_size_mismatch'], '</div>';

	if (!$item['approved'] && ($item['member']['id'] == MID) && !aeva_allowedTo('moderate') && !aeva_allowedTo('auto_approve_item'))
		echo '
			<div class="unapproved_yet">', $txt['media_will_be_approved'], '</div>';

	if (!$item['approved'] && $item['can_approve'])
		echo '
			<div class="unapproved_yet">', $txt['media_approve_this'], '</div>';

	echo '</div>';
}

function template_aeva_item_main_after()
{
	echo '
		</div>';
}

function template_aeva_item_details()
{
	global $galurl, $context, $amSettings, $txt;

	$item =& $context['item_data'];
	$in_sidebar = wetem::parent('aeva_item_details') == 'sidebar';

	if ($in_sidebar)
		echo '
	<section>';

	echo '
		<div id="media-details">
			<we:block header="', westr::safe($txt['media_item_info']), '">
			<dl class="settings">
				<dt>', $txt['media_posted_on'], '</dt>
				<dd>', timeformat($item['time_added']), '</dd>', !empty($item['last_edited']) ? '
				<dt>' . $txt['media_last_edited'] . '</dt>
				<dd>' . $item['last_edited'] . ($item['last_edited_by'] !== -2 ? ' ' . $txt['media_by'] . ' ' . $item['last_edited_by'] : '') . '</dd>' : '';

	if ($item['type'] != 'embed')
		echo !empty($item['width']) && !empty($item['height']) ? '
				<dt>' . $txt['media_width'] . '&nbsp;&times;&nbsp;' . $txt['media_height'] . '</dt>
				<dd>' . $item['width'] . '&nbsp;&times;&nbsp;' . $item['height'] . '</dd>' : '', '
				<dt>', $txt['media_filesize'], '</dt>
				<dd>', $item['filesize'], '</dd>
				<dt>', $txt['media_filename'], '</dt>
				<dd>', $item['filename'], '</dd>';

	if ((!empty($item['keyword_list']) && implode('', $item['keyword_list']) != '') || !empty($item['keywords']))
	{
		echo '
				<dt>', $txt['media_keywords'], '</dt><dd>';
		$tag_list = '';
		if (!empty($item['keyword_list']))
		{
			foreach ($item['keyword_list'] as $tag)
				if (!empty($tag))
					$tag_list .= '<b><a href="' . $galurl . 'sa=search;search=' . urlencode($tag) . ';sch_kw">' . $tag . '</a></b>, ';
		}
		else
			echo $item['keywords'];
		echo substr($tag_list, 0, strlen($tag_list) - 2) . '</dd>';
	}

	echo '
				<dt>', $txt['media_views'], '</dt>
				<dd>', $item['views'], '</dd>', !empty($item['downloads']) ? '
				<dt>' . $txt['media_downloads'] . '</dt>
				<dd>' . $item['downloads'] . '</dd>' : '', $item['can_rate'] || $item['voters'] > 0 ? '
				<dt>' . $txt['media_rating'] . '</dt>
				<dd>' . template_aeva_rating_object($item) . '</dd>' : '', !empty($item['num_comments']) ? '
				<dt>' . $txt['media_comments'] . '</dt>
				<dd>' . $item['num_comments'] . '</dd>' : '';

	foreach ($item['custom_fields'] as $field)
	{
		if (!empty($field['value']))
		{
			echo '
				<dt>', $field['name'], '</dt>
				<dd>';
			if ($field['searchable'])
			{
				$build_list = '';
				foreach ($field['value'] as $val)
					$build_list .= '<a href="' . $galurl . 'sa=search;search=' . urlencode($val) . ';fields[]=' . $field['id'] . '">' . westr::htmlspecialchars($val) . '</a>, ';
				echo substr($build_list, 0, -2);
				unset($build_list);
			}
			else
				echo substr($field['value'], 0, 7) == 'http://' ? '<a href="' . $field['value'] . '">' . westr::htmlspecialchars($field['value']) . '</a>' : $field['value'];
			echo '</dd>';
		}
	}

	if ($amSettings['show_linking_code'])
	{
		echo '
				<dt>', $txt['media_embed_bbc'], '</dt>
				<dd>
					<input id="bbc_embed" size="18" value="[media id=' . $item['id_media'] . ($item['type'] == 'image' ? '' : ' type=av') . ']" onclick="this.focus(); this.select();" readonly>
					<a href="<URL>?action=help;in=mediatag" onclick="return reqWin(this, 800);" class="help"></a>
				</dd>';

		// Don't show html/direct links if the helper file was deleted.
		if ($amSettings['show_linking_code'] == 1)
		{
			if (strpos($item['embed_object'], 'swfobject.embedSWF') === false)
				echo '
				<dt>', $txt['media_embed_html'], '</dt>
				<dd>
					<input id="html_embed" size="24" value="', $item['type'] == 'image' ?
						westr::htmlspecialchars('<img src="' . ROOT . '/MGalleryItem.php?id=' . $item['id_media'] . '">') :
						westr::htmlspecialchars(trim(preg_replace('/[\t\r\n]+/', ' ', $item['embed_object']))), '" onclick="this.focus(); this.select();" readonly>
				</dd>';
			if ($item['type'] != 'embed')
				echo '
				<dt>', $txt['media_direct_link'], '</td>
				<dd>
					<input id="direct_link" size="24" value="' . ROOT . '/MGalleryItem.php?id=' . $item['id_media'] . '" onclick="this.focus(); this.select();" readonly>
				</dd>';
		}
	}
	echo '
			</dl>
			</we:block>';

	if ($amSettings['show_extra_info'] == 1 && !empty($item['extra_info']))
	{
		echo '
			<we:block header="', westr::safe($txt['media_extra_info']), '">';

		echo $amSettings['use_zoom'] ? '
				<div class="info"><img src="' . ASSETS . '/aeva/magnifier.png" class="vam"> <a href="#" class="zoom is_html">'
				. $txt['media_meta_entries'] . '</a> (' . count($item['extra_info']) . ')
				<div class="zoom-html">
					<div class="ae_header" style="margin-bottom: 8px"><we:title>' . $txt['media_extra_info'] . '</we:title></div>' : '', '
					<div class="smalltext meta">';

		foreach ($item['extra_info'] as $info => $data)
			if (!empty($data))
				echo '
						<div class="info"><b>', $txt['media_meta_' . $info], '</b>: ', $data, '</div>';

		echo '
					</div>
				</div>', $amSettings['use_zoom'] ? '</div>' : '', '
			</we:block>';
	}

	echo '
			<we:block class="windowbg" header="', westr::safe($txt['media_poster_info']), '">', empty($item['member']['id']) ? '
				<h4>' . $txt['guest'] . '</h4>' : '
				<h4>' . $item['member']['link'] . '</h4>
				<ul class="smalltext info_list">' . (!empty($item['member']['group']) ? '
					<li>' . $item['member']['group'] . '</li>' : '') . (!empty($item['member']['avatar']['image']) ? '
					<li>' . $item['member']['avatar']['image'] . '</li>' : '') . '
					<li>' . $txt['media_total_items'] . ': ' . $item['member']['media']['total_items'] . '</li>
					<li>' . $txt['media_total_comments'] . ': ' . $item['member']['media']['total_comments'] . '</li>', '
				</ul>
			</we:block>
		</div>';

	if ($in_sidebar)
		echo '
	</section>';
}

function template_aeva_item_wrap_end()
{
	echo '
		</div>';
}

function template_aeva_item_actions()
{
	global $item, $galurl, $txt, $amSettings, $context;

	if (!$item['can_report'] && !$item['can_edit'] && !$item['can_approve'] && !$item['can_download'] && !$item['can_add_playlist'])
		return;

	echo '
		<div class="actionbar"><ul class="actions">';

	if ($item['can_report'])
	{
		echo '
			<li>
				<a href="', $galurl, 'sa=report;type=item;in=', $item['id_media'] . '"', $amSettings['use_zoom'] ? ' class="zoom is_html"' : '', '>
					<img src="', ASSETS, '/aeva/report.png">&nbsp;', $txt['media_report_this_item'], '
				</a>';

		if ($amSettings['use_zoom'])
			echo '
				<div class="zoom-html">
					<form action="', $galurl, 'sa=report;type=item;in=', $item['id_media'], '" method="post">
						<h3>', $txt['media_reporting_this_item'], '</h3>
						<hr>', $txt['media_reason'], '<br>
						<textarea rows="8" style="width: 98%" name="reason"></textarea>
						<p class="mgra">
							<input type="submit" value="', $txt['media_submit'], '" class="submit" name="submit_aeva">
							<input type="button" onclick="return hs.close(this);" value="', $txt['media_close'], '" class="cancel">
						</p>
					</form>
				</div>';

		echo '
			</li>';
	}

	if ($item['can_edit'])
		echo '
			<li>
				<a href="', $galurl, 'sa=post;in=', $item['id_media'], '"><img src="', ASSETS, '/aeva/camera_edit.png">&nbsp;', $txt['media_edit_this_item'], '</a>
			</li>
			<li>
				<a href="', $galurl, 'sa=delete;in=', $item['id_media'], '" onclick="return ask(we_confirm, e);"><img src="', ASSETS, '/aeva/delete.png">&nbsp;', $txt['media_delete_this_item'], '</a>
			</li>';

	if ($item['can_download'])
		echo '
			<li>
				<a href="', $galurl, 'sa=media;in=', $item['id_media'], ';dl"><img src="', ASSETS, '/aeva/download.png">&nbsp;', $txt['media_download_this_item'], '</a>
			</li>';

	if ($item['can_edit'] && !empty($context['aeva_move_albums']))
	{
		echo '
			<li>
				<a href="', $galurl, 'sa=move;in=', $item['id_media'], '"', $amSettings['use_zoom'] ? ' class="zoom is_html"' : '', '><img src="', ASSETS, '/aeva/arrow_out.png">&nbsp;', $txt['media_move_item'], '</a>';

		if ($amSettings['use_zoom'])
		{
			echo '
				<div class="zoom-html">
					<h3>', $txt['media_moving_this_item'], '</h3>
					<h2>', sprintf($txt['media_album_is'], $item['album_name']), '</h2>
					<hr>
					<form action="', $galurl, 'sa=move;in=', $item['id_media'], '" method="post">
						', $txt['media_album_to_move'], ':
						<select name="album">';

				foreach ($context['aeva_move_albums'] as $album => $name)
				{
					if ($name[2] === '')
						echo '
							</optgroup>';
					elseif ($name[2] == 'begin')
						echo '
							<optgroup label="', $name[0], '">';
					else
						echo '
								<option value="', $album, '"', $name[1] ? ' disabled' : '', '>', $name[0], '</option>';
				}

				echo '
						</select>
						<p class="mgra">
							<input type="submit" value="', $txt['media_submit'], '" class="submit" name="submit_aeva">
							<input type="button" onclick="return $(\'#zoom-close\').click();" value="', $txt['media_close'], '" class="cancel">
						</p>
					</form>
				</div>';
		}

		echo '
			</li>';
	}

	$un = $item['approved'] ? 'un' : '';
	if ($item['can_approve'])
		echo '
			<li>
				<a href="', $galurl, 'sa=', $un, 'approve;in=', $item['id_media'], '"><img src="', ASSETS, '/aeva/', $un, 'tick.png">&nbsp;', $txt['media_admin_' . $un . 'approve'], '</a>
			</li>';

	if ($item['can_add_playlist'])
	{
		echo '
			<li>
				<a href="#" class="zoom is_html"><img src="', ASSETS, '/aeva/playlist.png">&nbsp;', $txt['media_add_to_playlist'], '</a>

				<div class="zoom-html">
					<form action="', $galurl, 'sa=item;in=', $item['id_media'], '" method="post" style="line-height: 2.2em">
					<span style="float: left">', $txt['media_playlists'], '</span>
					<span style="float: right">', $txt['media_add_to_playlist'], '&nbsp;
						<select name="add_to_playlist">';

		foreach ($item['playlists']['mine'] as $p)
			echo '<option value="', $p['id'], '">', $p['name'], '</option>';

		echo '
						</select>
						<input type="submit" value="', $txt['media_submit'], '" name="submit_playlist">
					</span>
					</form>
				</div>
			</li>';
	}

	echo '
		</ul></div>';
}

function template_aeva_item_playlists()
{
	global $item, $txt, $galurl, $context;

	if (!isset($item['playlists']) || empty($item['playlists']['current']))
		return;

	$id =& $item['id_media'];
	$pl =& $item['playlists'];

	echo '
			', $txt['media_playlists'];

	echo '
			</td></tr>', empty($pl['current']) ? '' : '
			<tr>';

	$res = 0;
	foreach ($pl['current'] as $p)
	{
		echo $res == 0 ? '
		<tr>' : '', '
			<td>
				<strong><a href="', $galurl, 'sa=playlists;in=', $p['id'], '">', $p['name'], '</a></strong>', empty($p['owner_id']) ? '' : '
				' . $txt['media_by'] . ' <a href="<URL>?action=profile;in=' . $p['owner_id'] . ';area=aeva">' . $p['owner_name'] . '</a>', '
				<br><span class="smalltext">', $txt['media_items'], ': ', $p['num_items'], $p['owner_id'] != MID && !we::$is_admin ? '' : '<br>
				<a href="' . $galurl . 'sa=item;in=' . $id . ';premove=' . $p['id'] . ';' . $context['session_query'] . '" style="text-decoration: none"><img src="' . ASSETS . '/aeva/delete.png" style="vertical-align: bottom"> ' . $txt['media_delete_this_item'] . '</a>',
				'</span>
			</td>', $res == 3 ? '
		</tr>' : '';

		$res = ($res + 1) % 4;
	}
	echo $res != 0 ? '
		</tr>' : '', '
		</table>';
}

function template_aeva_item_comments()
{
	global $item, $galurl, $txt, $settings, $context, $options;

	echo '
		<we:cat>', !empty($settings['xmlnews_enable']) ? '
			<a href="<URL>?action=feed;sa=media;item=' . $item['id_media'] . ';type=comments" class="feed_icon"></a>
			<a href="<URL>?action=feed;sa=media;item=' . $item['id_media'] . ';type=comments">
				' . $txt['media_comments'] . '
			</a>' : '
			' . $txt['media_comments'], '
		</we:cat>';

	if (empty($item['comments']))
		echo '
		<div class="windowbg wrc">
			', $txt['media_no_comments'], '
		</div>';
	else
	{
		echo '
		<div class="comment_sort_options">
			', $txt['media_sort_order_com'], ':
			<select onchange="location = $(this).val();">
				<option value="', $galurl, 'sa=item;in=', $item['id_media'], !empty($_REQUEST['start']) ? ';start=' . (int) $_REQUEST['start'] : '', '"', $context['aeva_asc'] ? ' selected' : '', '>', $txt['media_sort_order_asc'], '</option>
				<option value="', $galurl, 'sa=item;in=', $item['id_media'], ';com_desc', !empty($_REQUEST['start']) ? ';start=' . (int) $_REQUEST['start'] : '', '"', $context['aeva_asc'] ? '' : ' selected', '>', $txt['media_sort_order_desc'], '</option>
			</select>
		</div>
		<div class="pagesection clearfix">
			<nav>', $txt['pages'], ': ', $item['com_page_index'], '</nav>
		</div>';

		$alternative = false;
		foreach ($item['comments'] as $c)
		{
			$alternative = !$alternative;
			echo '
		<div class="windowbg', $alternative ? '' : '2', ' wrc core_posts">
			<table class="w100 cp0 cs0 tlf"><tr>
			<td style="width: 20%"', $c['is_edited'] ? ' rowspan="2"' : '', ' class="top">', empty($c['member']['id']) ? '
				<h4>' . $txt['guest'] . '</h4>' : '
				<h4>' . $c['member_link'] . '</h4>
				<ul class="smalltext info_list">' . (!empty($c['member']['group']) ? '
					<li>' . $c['member']['group'] . '</li>' : '') . (!empty($c['member']['avatar']['image']) ? '
					<li>' . $c['member']['avatar']['image'] . '</li>' : '') . '
					<li>' . $txt['media_total_items'] . ': ' . $c['member']['media']['total_items'] . '</li>
					<li>' . $txt['media_total_comments'] . ': ' . $c['member']['media']['total_comments'] . '</li>
				</ul>', '
			</td>
			<td class="top', $c['approved'] ? '' : ' unapp', '">
				<a id="com', $c['id_comment'], '"></a>
				<div class="mgc_main">
					', $txt['media_comment'], ' <a href="#com', $c['id_comment'], '" rel="nofollow">#', $c['counter'], '</a> - ',
					is_numeric($c['posted_on'][0]) ? $txt['media_posted_on_date'] : $txt['media_posted_on'], ' ', $c['posted_on'], '
				</div>';

			if ($c['can_edit'] || $c['can_report'])
				echo '
				<div class="mgc_icons">', $c['can_edit'] ? '
					<a href="' . $galurl . 'sa=edit;type=comment;in=' . $c['id_comment'] . '">
						<img src="' . ASSETS . '/aeva/comment_edit.png"> ' . $txt['media_edit_this_item'] . '
					</a>' : '', $c['can_delete'] ? '
					<a href="' . $galurl . 'sa=delete;type=comment;in=' . $c['id_comment'] . '" onclick="return ask(we_confirm, e);">
						<img src="' . ASSETS . '/aeva/delete.png"> ' . $txt['media_delete_this_item'] . '
					</a> ' : '', $c['can_report'] ? '
					<a href="' . $galurl . 'sa=report;type=comment;in=' . $c['id_comment'] . '">
						<img src="' . ASSETS . '/aeva/report.png"> ' . $txt['media_report_this_item'] . '
					</a>' : '', !$c['approved'] && $c['can_delete'] ? '
					<a href="<URL>?action=media;area=moderate;sa=submissions;do=approve;in=' . $c['id_comment'] . ';type=coms;' . $context['session_query'] . '">
						<img src="' . ASSETS . '/aeva/tick.png" title="' . $txt['media_admin_approve'] . '"> ' . $txt['media_admin_approve'] . '
					</a>' : '', '
				</div>';

			echo '
				<div class="mgc_post">
					', $c['message'], '
				</div>
			</td></tr>', $c['is_edited'] ? '
			<tr><td class="bottom">
				<div class="mgc_last_edit">' . $txt['media_last_edited'] . ': ' . $c['last_edited']['on'] .
				($c['last_edited']['id'] > -2 ? ' ' . strtolower($txt['media_by']) . ' ' . $c['last_edited']['link'] : '') . '</div>
			</td></tr>' : '', '
			</table>
		</div>';
		}
		echo '
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $item['com_page_index'], '</nav>
		</div>';
	}

	if (aeva_allowedTo('comment'))
		echo '
		<div id="quickreply" style="padding-top: 4px">
			<we:cat>
				<a href="#" onclick="return window.oQuickReply && oQuickReply.swap();" onmousedown="return false;"><div id="qr_expand"', !empty($options['display_quick_reply']) && $options['display_quick_reply'] == 2 ? ' class="fold"' : '', '></div></a>
				<a href="#" onclick="return window.oQuickReply && oQuickReply.swap();" onmousedown="return false;">', $txt['media_comment_this_item'], '</a>
			</we:cat>
			<div id="qr_options" class="hide">
				<div class="roundframe wrc">
					<form action="', $galurl, 'sa=comment;in=', $item['id_media'], '" method="post">
						<div>
							<h3>', $txt['media_commenting_this_item'], '</h3>
							<img src="', ASSETS, '/aeva/comment.png" class="middle"> <a href="', $galurl, 'sa=comment;in=', $item['id_media'], '">', $txt['media_switch_fulledit'], '</a>
						</div>
						<div class="qr_content">
							<div id="bbcBox_message" class="hide"></div>
							<div id="smileyBox_message" class="hide"></div>',
							$context['postbox']->outputEditor(), '
						</div>
						<div class="postbuttons">',
							$context['postbox']->outputButtons(), '
						</div>
						<div class="padding floatleft">
							<input type="button" name="switch_mode" id="switch_mode" value="', $txt['switch_mode'], '" class="hide" onclick="if (window.oQuickReply) oQuickReply.switchMode();">
						</div>
						<input type="hidden" name="submit_aeva">
					</form>
				</div>
			</div>
		</div>';

	echo '
	</div>';
}

function template_aeva_done()
{
	// Template to show some "done" kind of things, uses $context['aeva_done_txt'] as its context to show
	global $context;
	echo '
		<div style="font-size: 1.2em">
			<table class="w100" style="height: 100px">
				<tr class="windowbg2 center">
					<td>', $context['aeva_done_txt'], '</td>
				</tr>
			</table>
		</div>';
}

function template_aeva_form()
{
	// This is a pretty global template used for forms like reporting, commenting, adding, editing etc.
	global $context, $txt;

	static $chk = 1, $colspan = 0;

	echo '
	<form action="', $context['aeva_form_url'], '" method="post" enctype="multipart/form-data" id="aeva_form" name="aeva_form" onsubmit="submitonce(this);">
		<table class="w100 cs1 cp8"', !empty($context['aeva_form']) && isset($context['aeva_form']['whitespace']) ? ' style="margin-top: 12px"' : '', '>';

	// Form headers
	if (isset($context['aeva_form_headers']))
		foreach ($context['aeva_form_headers'] as $h)
			if (!isset($h[1]) || $h[1])
				echo '
			<tr class="windowbg2 center"><td colspan="2">', $h[0], '</td></tr>';

	// The form itself.
	$show_at_end = '';
	foreach ($context['aeva_form'] as $e)
	{
		if (!isset($e['perm']) || $e['perm'])
		{
			if (isset($e['skip']))
			{
				$show_at_end .= '
					<input type="hidden" name="' . $e['fieldname'] . '" value="' . $e['value'] . '">';
				continue;
			}

			if ($e['type'] == 'hr')
			{
				echo '
			<tr><td colspan="2" style="padding: 1px 0"></td></tr>';
				continue;
			}

			if ($e['type'] == 'title')
			{
				if (isset($e['class']))
					echo '
			<tr><td colspan="2" style="padding: 3px" class="' . $e['class'] . '"><h3 style="margin: 0">' . $e['label'] . '</h3></td></tr>';
				else
					echo '
			<tr><td colspan="2" style="padding: 3px 0 0 0"><we:title>' . $e['label'] . '</we:title></td></tr>';
				continue;
			}

			// If there's a text editor in the form, adjust the name/description column width accordingly.
			if ($colspan < 2)
				$alt = empty($alt) ? '2' : '';
			$valign = $e['type'] == 'textbox' || $e['type'] == 'select' || substr($e['type'], 0, 5) == 'check';

			echo '
			<tr>', !empty($e['skip_left']) || --$colspan > 0 ? '' : '
				<td' . (isset($context['postbox']) ? ' style="width: ' . ($context['postbox']->id == 'desc' ? '35' : '10') . '%"' : '') . ' class="windowbg' . $alt .
				($valign ? ' top' : '') . (isset($context['postbox']) ? '' : ' w50') . '"' . (!empty($e['colspan']) && ($colspan = $e['colspan']) ? ' rowspan="2"' : '') . '>' . $e['label'] . (!empty($e['subtext']) ? '<dfn>' . $e['subtext'] . '</dfn>' : '') . '</td>', '
				<td class="windowbg' . $alt . '"' . (!empty($e['skip_left']) ? ' colspan="2"' : '') . '>';

			if ($e['type'] != 'title')
				switch ($e['type'])
				{
					case 'text';
						echo '<input', isset($e['value']) ? ' value="' . $e['value'] . '"' : '', ' name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '"', !empty($e['size']) ? ' size="' . $e['size'] . '"' : '', isset($e['custom']) ? ' ' . $e['custom'] : '', '>';
					break;
					case 'textbox';
						if (isset($context['postbox']) && $context['postbox']->id == $e['fieldname'])
						{
							echo '<div id="bbcBox_message"></div><div id="smileyBox_message"></div>';
							template_aeva_text_editor();
						}
						else
							echo '<textarea name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '"', isset($e['custom']) ? ' ' . $e['custom'] : '', '>', isset($e['value']) ? $e['value'] : '', '</textarea>';
					break;
					case 'file';
						echo '<input type="file" name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '"', isset($e['custom']) ? ' ' . $e['custom'] : '', '>', isset($e['add_text']) ? ' ' . $e['add_text'] : '';
					break;
					case 'hidden';
						echo '<input type="hidden" name="', $e['fieldname'], '" value="', $e['value'], '"', isset($e['custom']) ? ' ' . $e['custom'] : '', '>';
					break;
					case 'small_text';
						echo '<input', isset($e['value']) ? ' value="' . $e['value'] . '"' : '', ' name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '" size="10"', isset($e['custom']) ? ' ' . $e['custom'] : '', '>';
					break;
					case 'select';
						echo '<select name="', $e['fieldname'], isset($e['multi']) && $e['multi'] === true ? '[]' : '', '" tabindex="', $context['tabindex']++, '"',
							isset($e['multi']) && $e['multi'] === true ? ' multiple' . (!empty($e['size']) ? ' size="' . $e['size'] . '"' : '') : '',
							isset($e['custom']) ? ' ' . $e['custom'] : '', '>';
						foreach ($e['options'] as $value => $name)
						{
							if (is_array($name) && isset($name[2]) && ($name[2] === '' || $name[2] === 'begin'))
								echo $name[2] == 'begin' ? '<optgroup label="' . $name[0] . '">' : '</optgroup>';
							else
								echo '<option value="', $value, '"', is_array($name) && isset($name[1]) && $name[1] == true ? ' selected' : '', is_array($name) && isset($name[2]) ? $name[2] : '', '>', is_array($name) ? $name[0] : $name, '</option>';
						}
						echo '</select>';
					break;
					case 'radio';
						foreach ($e['options'] as $value => $name)
							echo '<div><label><input id="radio', $chk++, '" name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '" value="', $value, '"', is_array($name) && isset($name[1]) && $name[1] === true ? ' checked' : '', ' type="radio"', isset($e['custom']) ? ' ' . $e['custom'] : '', '> ', is_array($name) ? $name[0] : $name, '</label></div>';
					break;
					case 'yesno';
						echo '<select name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '"><option value="1"', !empty($e['value']) ? ' selected' : '', ' style="color: green">', $txt['media_yes'], '</option><option value="0"', empty($e['value']) ? ' selected' : '', ' style="color: red">', $txt['media_no'], '</option></select>';
					break;
					case 'passwd';
						echo '<input name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '" value="', isset($e['value']) ? $e['value'] : '', '" type="password"', isset($e['custom']) ? ' ' . $e['custom'] : '', '>';
					break;
					case 'checkbox';
					case 'checkbox_line';
						foreach ($e['options'] as $opt => $label)
						{
							if (!is_array($label) && $label == 'sep')
								echo '<hr>';
							else
								echo '<div', $e['type'] == 'checkbox_line' ? ' class="aeva_ich' . (!empty($e['skip_left']) ? '2' : '') . '"' : '', '><label><input type="checkbox" id="chk', $chk++, '" name="', is_array($label) ?
									(!isset($label['force_name']) ? $e['fieldname'] . (isset($e['multi']) && $e['multi'] === true ? '[]' : '') : $label['force_name']) : $label,
									'" tabindex="', $context['tabindex']++, '" value="', $opt, '"', is_array($label) && isset($label[1]) && $label[1] === true ? ' checked' : '', is_array($label) && !empty($label['disabled']) ? ' disabled' : '',
									is_array($label) && isset($label['custom']) ? ' ' . $label['custom'] : '', '>&nbsp;&nbsp;', is_array($label) ? $label[0] : $label, '</label></div>';
						}
					break;
					case 'checkbox_dual'; // This one is for album creation only... ;)
						echo '<table class="w100 cs0 cp4"><thead><tr><th>', $txt['media_access_read'], '</th><th>', $txt['media_access_write'], '</th><th></th></tr></thead>';
						foreach ($e['options'] as $opt => $label)
						{
							echo '<tr>';
							if (!is_array($label))
								echo '<td colspan="3" style="padding: 1px 0 0 0; border-top: 1px dotted #aaa"></td>';
							else
							{
								for ($i = 0; $i < 2; $i++)
									echo '<td style="width: 15px" class="center"><input type="checkbox" id="chk', $chk++, '" name="',
										$opt === 'check_all' ? 'check_all_' . ($i+1) : $e['fieldname'][$i] . '[]',
										'" tabindex="', $context['tabindex']++, '" value="', $opt, '"',
										isset($label[$i+1]) && $label[$i+1] === true ? ' checked' : '',
										$opt == -1 && $i == 1 ? ' disabled' : '',
										isset($label['custom']) ? ' ' . str_replace('$1', $e['fieldname'][$i], $label['custom']) : '', '></td>';
								echo '<td><label for="chk', $chk-2, '">', $label[0], '</label></td>';
							}
							echo '</tr>';
						}
						echo '</table>';
					break;
					case 'link';
						echo '<a href="', $e['link'], '">', $e['text'], '</a>', !empty($e['add_text']) ? ' ' . $e['add_text'] : '';
					break;
					case 'info';
						echo $e['value'];
					break;
				}

			echo !empty($e['next']) ? $e['next'] : '', '
				</td>
			</tr>';
		}
	}

	// End the form.
	echo '
			<tr>
				<td class="windowbg', empty($alt) ? '2' : '', ' right" colspan="2">', $show_at_end, !empty($context['aeva_form']['silent']) ? '
					<input type="submit" value="' . $txt['media_silent_update'] . '" name="silent_update" tabindex="' . $context['tabindex']++ . '">' : '', '
					<input type="submit" value="', $txt['media_submit'], '" name="submit_aeva" tabindex="', $context['tabindex']++, '" class="submit">
				</td>
			</tr>
		</table>
	</form>';

	if (!empty($context['aeva_extra_data']))
		echo $context['aeva_extra_data'];
}

function template_aeva_viewAlbum()
{
	global $context, $txt, $galurl, $settings;

	$album_data =& $context['album_data'];

	// Show some album data
	echo '
	<div id="albums">
		<table class="w100 cs0 cp4 windowbg2">
		<tr><td class="windowbg top" style="width: 2%" rowspan="2">';
	$trans = $album_data['bigicon_transparent'] ? ' ping' : '';
	// If the big icon is too... big, then we can't round its corners. Ah well.
	if ($album_data['bigicon_resized'])
		echo '<img src="', $galurl, 'sa=media;in=', $album_data['id'], ';bigicon" style="width: ', $album_data['bigicon'][0], 'px; height: ', $album_data['bigicon'][1], 'px">';
	else
		echo '<div class="aea', $trans, '" style="width: ', $album_data['bigicon'][0], 'px; height: ', $album_data['bigicon'][1], 'px; background: url(', $galurl, 'sa=media;in=', $album_data['id'], ';bigicon) 0 0">&nbsp;</div>';
	echo '</td>
		<td style="padding: 12px 12px 6px 12px">
			<div class="mg_large mg_pb4">', !empty($album_data['passwd']) ? aeva_lockedAlbum($album_data['passwd'], $album_data['id'], $album_data['owner']) : '',
			$album_data['name'], !empty($settings['xmlnews_enable']) ? '&nbsp;&nbsp;&nbsp;<span class="title_feed">
			<a href="<URL>?action=feed;sa=media;album=' . $album_data['id'] . '" class="feed_icon">' . $txt['media_items'] . '</a>
			<a href="<URL>?action=feed;sa=media;album=' . $album_data['id'] . ';type=comments" class="feed_icon" title="' . $txt['feed'] . '">' . $txt['media_comments'] . '</a></span>' : '', '</div>
			<div>', $album_data['type2'], !empty($album_data['owner']['id']) ? '
			- ' . $txt['media_owner'] . ': ' . aeva_profile($album_data['owner']['id'], $album_data['owner']['name']) : '', '
			- ', $album_data['num_items'] == 0 ? $txt['media_no_items'] : $album_data['num_items'] . ' ' . $txt['media_lower_item' . ($album_data['num_items'] == 1 ? '' : 's')], !empty($album_data['last_item']) ? ' - ' . $txt['media_latest_item'] . ': <a href="' . $galurl . 'sa=item;in=' . $album_data['last_item'] . '">' . $album_data['last_item_title'] . '</a> (' . $album_data['last_item_date'] . ')' : '', '</div>', !empty($album_data['description']) ? '
			<div class="mg_desc">' . $album_data['description'] . '</div>' : '', $album_data['hidden'] ? '
			<div class="mg_hidden">' . $txt['media_album_is_hidden'] . '</div>' : '', '
		</td></tr>
		<tr><td class="bottom">';

	$can_moderate_here = $context['aeva_can_moderate_here'];
	$can_approve_here = $context['aeva_can_approve_here'];
	$can_add_playlist = !empty($context['aeva_my_playlists']);
	$can_edit_items = $context['aeva_can_edit_items'];

	if ($can_edit_items || aeva_allowedTo('multi_download') || aeva_allowedTo('access_unseen'))
	{
		echo '
			<ul class="buttonlist data">';

		if ($context['aeva_can_add_item'])
			echo '
				<li><a href="', $galurl, 'sa=post;album=', $album_data['id'], '"><span><img src="', ASSETS, '/aeva/camera_add.png"> ', $txt['media_add_item'], '</span></a></li>';

		if ($context['aeva_can_multi_upload'])
			echo '
				<li><a href="', $galurl, 'sa=mass;album=', $album_data['id'], '"><span><img src="', ASSETS, '/aeva/camera_mass.png"> ', $txt['media_multi_upload'], '</span></a></li>';

		if ($can_moderate_here)
		{
			echo '
				<li><a href="', $galurl, 'area=mya;sa=edit;in=', $album_data['id'], '"><span><img src="', ASSETS, '/aeva/folder_edit.png"> ', $txt['media_edit_this_item'], '</span></a></li>';
			if (we::$is_admin)
				echo '
				<li><a href="<URL>?action=admin;area=aeva_maintenance;sa=index;album=', $album_data['id'], ';', $context['session_query'], '"><span><img src="', ASSETS, '/aeva/maintain.gif" title="', $txt['media_admin_labels_maintenance'], '"> ', $txt['media_admin_labels_maintenance'], '</span></a></li>';
			if (aeva_allowedTo('moderate') && $album_data['approved'] == 0)
				echo '
				<li><a href="<URL>?action=media;area=moderate;sa=submissions;do=approve;type=albums;in=', $album_data['id'], ';', $context['session_query'], '"><span><img src="', ASSETS, '/aeva/tick.png" title="', $txt['media_admin_approve'], '"> ', $txt['media_admin_approve'], '</span></a></li>';
		}

		if (aeva_allowedTo('multi_download') && !$context['no_items'] && !empty($context['aeva_items']))
			echo '
				<li><a href="', $galurl, 'sa=massdown;album=', $album_data['id'], '"><span><img src="', ASSETS, '/aeva/download.png" title="', $txt['media_multi_download'], '"> ', $txt['media_multi_download'], '</span></a></li>';

		if (aeva_allowedTo('access_unseen'))
			echo '
				<li><a href="', $galurl, 'sa=album;in=', $album_data['id'], ';markseen;', $context['session_query'], '"><span><img src="', ASSETS, '/aeva/eye.png" title="', $txt['media_mark_album_as_seen'], '"> ', $txt['media_mark_album_as_seen'], '</span></a></li>';

		echo '
			</ul>';
	}
	$can_edit_items |= $can_add_playlist;

	echo '
		</td></tr>
		</table>';

	// Show their Sub-Albums
	if (!empty($context['aeva_sub_albums']))
	{
		echo '
		<div class="titlebg" style="padding: 4px">', $txt['media_sub_albums'], !empty($settings['xmlnews_enable']) ? '&nbsp;&nbsp;&nbsp;<span class="title_feed">
			<a href="<URL>?action=feed;sa=media;album=' . $album_data['id'] . ';children" class="feed_icon" title="' . $txt['feed'] . '">' . $txt['media_items'] . '</a>
			<a href="<URL>?action=feed;sa=media;album=' . $album_data['id'] . ';children;type=comments" class="feed_icon" title="' . $txt['feed'] . '">' . $txt['media_comments'] . '</a></span>' : '', '</div>';
		aeva_listChildren($context['aeva_sub_albums']);
	}

	if ($context['no_items'] || empty($context['aeva_items']))
	{
		echo '
		<div class="windowbg2 wrc notice">
			', $txt['media_no_listing'], '
		</div>
	</div>';
		return;
	}

	echo ($can_edit_items ? '
	<form action="<URL>?action=media;sa=quickmod;in=' . $album_data['id'] . '" method="post" enctype="multipart/form-data" id="aeva_form" name="aeva_form" onsubmit="submitonce(this);">' : '') . '
	<div class="pagesection">
		<nav>', $txt['pages'], ': ', $context['aeva_page_index'], '</nav>
	</div>
	<we:block id="album_pics" class="windowbg">';

	template_aeva_sort_options(
		$galurl . 'sa=album;in=' . $album_data['id'],
		$album_data['options']['view'] == 'filestack',
		strpos($album_data['options']['sort'], ' ASC') !== false
	);

	echo '
		<div>',
		$album_data['view'] == 'normal' ? aeva_listItems($context['aeva_items'], true, '', $can_edit_items) : aeva_listFiles($context['aeva_items'], $can_edit_items), '
		</div>
	</we:block>
	<div class="pagesection" style="margin-top: 8px; margin-bottom: 0; overflow: visible">', $can_edit_items ? '
		<div class="aeva_quickmod_bar">
			<label><input type="checkbox" id="check_all" onclick="invertAll(this, this.form, \'mod_item[\');"> ' . $txt['check_all'] . '</label>&nbsp;
			<select name="aeva_modtype" id="modtype" tabindex="' . $context['tabindex']++ . '"' . ($can_add_playlist ? ' onchange="$(\'#aeva_my_playlists\').toggle(this.value == \'playlist\');"' : '') . '>' . ($can_approve_here ? '
				<option value="move">' . $txt['media_move_item'] . '</option>
				<option value="approve">' . $txt['media_admin_approve'] . '</option>
				<option value="unapprove">' . $txt['media_admin_unapprove'] . '</option>' : '') . '
				<option value="delete">' . $txt['media_delete_this_item'] . '</option>' . ($can_add_playlist ? '
				<option value="playlist">' . $txt['media_add_to_playlist'] . '</option>' : '') . '
			</select>' : '';

	if ($can_edit_items && $can_add_playlist)
	{
		echo '
				<select name="aeva_playlist" id="aeva_my_playlists" class="hide">';
		foreach ($context['aeva_my_playlists'] as $p)
			echo '
					<option value="' . $p['id'] . '">' . $p['name'] . '</option>';
		echo '
				</select>';
	}
	echo $can_edit_items ? '
				<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
				<input type="submit" value="' . $txt['media_submit'] . '" name="submit_aeva" tabindex="' . $context['tabindex']++ . '" class="remove" style="margin: 0; padding: 1px 3px" onclick="return $(\'#modtype\').val() != \'delete\' || ask(we_confirm, e);">
			</div>' : '', '
			<nav>', $txt['pages'], ': ', $context['aeva_page_index'], '</nav>
		</div>
	</div>', $can_edit_items ? '
	</form>' : '';
}

// Showing the list of sorting select boxes... More complicated than it looks, I'm afraid.
// $url should be the base URL, $is_fs should tell whether the page (if an album) is
// in filestack mode by default, and $is_ascdef does the same for ascending order.
function template_aeva_sort_options($url, $is_fs = false, $is_ascdef = false)
{
	global $context, $txt;

	$sort_list = array('m.id_media' => 0, 'm.time_added' => 1, 'm.title' => 2, 'm.views' => 3, 'm.weighted' => 4);
	$is_fw = isset($_GET['fw']) || ($is_fs && !isset($_GET['nw']));
	$more_sort = preg_replace('~;sort=\d+~', ';sort', $context['aeva_urlmore']);
	$more = str_replace(';sort=0', '', $context['aeva_urlmore']);

	echo '
		<header class="sort_options">
			<div class="view_options">
				', $txt['media_items_view'], ':
				<select id="mediaView" onchange="window.location = $(this).val();">
					<option value="', $url, ($is_fs ? ';nw' : '') . str_replace(array(';nw', ';fw'), '', $more), '"', $is_fw ? '' : ' selected', '>' . $txt['media_view_normal'] . '</option>
					<option value="', $url, ($is_fs ? '' : ';fw') . str_replace(array(';nw', ';fw'), '', $more), '"', $is_fw ? ' selected' : '', '>' . $txt['media_view_filestack'] . '</option>
				</select>
			</div>
			<div class="sort_by_options">
				', $txt['media_sort_by'], ':
				<select id="mediaSort" onchange="window.location = $(this).val();">';

	$sort = empty($sort_list[$context['aeva_sort']]) ? 0 : $sort_list[$context['aeva_sort']];
	for ($i = 0; $i < 5; $i++)
		echo '
					<option value="', $url, str_replace(';sort', $i ? ';sort=' . $i : '', $more_sort), '"', $sort == $i ? ' selected' : '', '>' . $txt['media_sort_by_' . $i] . '</option>';

	echo '
				</select>
				&nbsp; ', $txt['media_sort_order'], ':
				<select id="mediaOrder" onchange="window.location = $(this).val();">
					<option value="', $url, str_replace(array(';asc', ';desc'), '', $more) . (!$is_ascdef ? ';asc' : ''), '"', $context['aeva_asc'] ? ' selected' : '', '>', $txt['media_sort_order_asc'], '</option>
					<option value="', $url, str_replace(array(';asc', ';desc'), '', $more) . ($is_ascdef ? ';desc' : ''), '"', $context['aeva_asc'] ? '' : ' selected', '>', $txt['media_sort_order_desc'], '</option>
				</select>
			</div>
		</header>';
}

function template_aeva_unseen()
{
	global $context, $txt, $galurl;

	echo '
	<div class="pagesection">';

	if (!empty($context['aeva_items']))
	{
		$mark_seen = array();
		if (strpos($context['aeva_page_index'], '<a') !== false)
			$mark_seen['pageread'] = array('text' => 'media_page_seen', 'url' => $galurl . 'sa=unseen;' . (isset($_GET['start']) ? 'start=' . $_GET['start'] . ';' : '') . 'pageseen=' . implode(',', array_keys($context['aeva_items'])) . ';' . $context['session_query']);
		$mark_seen['markread'] = array('text' => 'media_mark_as_seen', 'url' => $galurl . 'sa=unseen;markseen;' . $context['session_query']);
		echo template_button_strip($mark_seen);
	}

	echo '
		<nav>', $txt['pages'], ': ', $context['aeva_page_index'], '</nav>
	</div>
	<div id="unseen_items" style="clear: both">', !empty($context['aeva_items']) ? aeva_listItems($context['aeva_items']) : '<br><div class="notice">' . $txt['media_no_listing'] . '</div>', '
	</div>
	<div class="pagesection">',
		!empty($context['aeva_items']) ? template_button_strip($mark_seen) : '', '
		<nav>', $txt['pages'], ': ', $context['aeva_page_index'], '</nav>
	</div>';
}

function template_aeva_search_searching()
{
	global $txt, $galurl, $context;

	echo '
	<br>
	<form action="', $galurl, 'sa=search" method="post">
		<div class="windowbg2 wrc center">
			', $txt['media_search_for'], ': <input name="search" size="50">
		</div>
		<div class="windowbg wrc">
			<table class="w100 cs1 cp8">
			<tr>
				<td class="w50 top right">
					<label>', $txt['media_search_in_title'], ' <input type="checkbox" name="sch_title" checked id="seala1"></label><br>
					<label>', $txt['media_search_in_description'], ' <input type="checkbox" name="sch_desc" id="seala2"></label><br>', empty($context['custom_fields']) ? '
				</td>
				<td class="w50 top left">
					<label><input type="checkbox" name="sch_kw" id="seala3"> ' . $txt['media_search_in_kw'] . '</label><br>
					<label><input type="checkbox" name="sch_an" id="seala4"> ' . $txt['media_search_in_album_name'] . '</label>' : '
					<label>' . $txt['media_search_in_kw'] . ' <input type="checkbox" name="sch_kw" id="seala3"></label><br>
					<label>' . $txt['media_search_in_album_name'] . ' <input type="checkbox" name="sch_an" id="seala4"></label>';

	if (!empty($context['custom_fields']))
	{
		echo '
				</td>
				<td class="w50 top left">';

		$kl = 1;
		foreach ($context['custom_fields'] as $field)
			echo '
					<label><input type="checkbox" name="fields[]" value="', $field['id'], '" id="cusla', $kl++, '"> ', sprintf($txt['media_search_in_cf'], '<em>' . $field['name'] . '</em>'), '</label><br>';
	}

	echo '
				</td>
			</tr>
			<tr>
				<td class="w50 top right">', $txt['media_search_in_album'], '</td>
				<td class="w50 top left">
					<select name="sch_album">
						<option value="0">', $txt['media_search_in_all_albums'], '</option>';

	foreach ($context['aeva_albums'] as $id => $name)
		echo '<option value="', $id, '">', $name, '</option>';

	echo '
					</select>
				</td>
			</tr>
			<tr>
				<td class="w50 right">
					', $txt['media_search_by_mem'], '<dfn>', $txt['media_search_by_mem_desc'], '</dfn>
				</td>
				<td class="w50 left">
					<input name="sch_mem" id="sch_mem" size="25">
				</td>
			</tr>
			<tr>
				<td colspan="2" class="right"><input type="submit" name="submit_aeva" value="', $txt['media_submit'], '"></td>
			</tr>
			</table>
		</div>
	</form>';

	add_js_file('suggest.js');

	add_js('
	new weAutoSuggest({
		', min_chars(), ',
		bItemList: true,
		sControlId: \'sch_mem\',
		sPostName: \'sch_mem_list\'
	});');
}

function template_aeva_search_results()
{
	global $context, $txt;

	echo '
	<div style="padding: 5px"><strong>', $context['aeva_total_results'], '</strong> ', $txt['media_search_results_for'], ' <strong>', $context['aeva_searching_for'], '</strong></div>';

	if (!empty($context['aeva_foxy_rendered_search']))
		echo $context['aeva_foxy_rendered_search'];
	elseif (!empty($context['aeva_items']))
		echo $context['aeva_page_index'], '
	<div id="search_items">',
		aeva_listItems($context['aeva_items']), '
	</div>', $context['aeva_page_index'], '
	<div class="clear"></div>';
}

function template_aeva_viewUserAlbums()
{
	global $txt, $context, $galurl, $amSettings, $settings;

	// The Albums!
	echo '
	<div id="aeva_toplinks">
		<we:title>
			<img src="', ASSETS, '/aeva/house.png"> <a href="', $galurl, '">', $txt['media_home'], '</a> -
			<img src="', ASSETS, '/aeva/album.png"> ', $txt['media_albums'], empty($amSettings['disable_playlists']) ? ' -
			<img src="' . ASSETS . '/aeva/playlist.png"> <a href="' . $galurl . 'sa=playlists">' . $txt['media_playlists'] . '</a>' : '', '
		</we:title>
	</div>';

	$colspan = (isset($amSettings['album_columns']) ? max(1, (int) $amSettings['album_columns']) : 1) * 2;
	echo '
	<div class="pagesection">
		<nav>', $txt['pages'], ': ', $context['aeva_page_index'], '</nav>
	</div>';

	$can_feed = !empty($settings['xmlnews_enable']);
	foreach ($context['aeva_user_albums'] as $id => $album)
	{
		$first = current($album);
		echo '
	<we:title>
		', empty($first['owner']['id']) ? '' : $txt['media_owner'] . ': ' . aeva_profile($id, $first['owner']['name']), $can_feed ?
		' <a href="<URL>?action=feed;sa=media;user=' . $id . ';albums" class="feed_icon" title="' . $txt['feed'] . '"></a>' : '', '
	</we:title>';

		aeva_listChildren($album);
	}
	echo '
	<div class="pagesection">
		<nav>', $txt['pages'], ': ', $context['aeva_page_index'], '</nav>
	</div>';
}

function template_aeva_album_cp()
{
	global $txt, $galurl, $context, $alburl;

	echo '
		<table class="bordercolor w100 cs1 cp8">
			<tr class="titlebg">
				<td style="width: 2%">&nbsp;</td>
				<td style="width: 15%">', $txt['media_owner'], '</td>
				<td style="width: 55%">', $txt['media_name'], '</td>
				<td style="width: 28%">', $txt['media_admin_moderation'], '</td>
			</tr>', !empty($context['aeva_my_albums']) ? '
			<tr class="windowbg2">
				<td colspan="4"><a href="#" onclick="return admin_toggle_all();">' . $txt['media_toggle_all'] . '</a></td>
			</tr>' : '';

	if ($context['aeva_moving'] !== false)
		echo '
			<tr class="windowbg3">
				<td colspan="4">', $txt['media_admin_moving_album'], ': ', $context['aeva_my_albums'][$context['aeva_moving']]['name'], ' <a href="', rtrim($alburl, ';'), '">[', $txt['media_admin_cancel_moving'], ']</a></td>
			</tr>';

	$can_manage = allowedTo('media_manage');
	$can_moderate = aeva_allowedTo('moderate');
	foreach ($context['aeva_my_albums'] as $album)
	{
		echo '
			<tr class="windowbg', $album['featured'] ? '' : '2', '">
				<td><a href="#" onclick="return admin_toggle(', $album['id'], ');"><div class="foldable fold" id="toggle_img_', $album['id'], '"></div></a></td>
				<td>', !empty($album['owner']['id']) ? $album['owner']['name'] : '', '</td>
				<td', !$album['approved'] ? ' class="unapp"' : '', ' style="padding-left: ', 5 + 30 * $album['child_level'], 'px',
				$context['aeva_moving'] !== false && ($context['aeva_moving'] == $album['id'] || $context['aeva_moving'] == $album['parent']) ? '; font-weight: bold' : '', '">';

		$show_move = $context['aeva_moving'] !== false && $context['aeva_moving'] != $album['id'] && $context['aeva_moving'] != $album['parent'];
		if ($show_move)
			echo '
				', $album['move_links']['before'], '
				', $album['move_links']['after'];

		if (!empty($album['featured']))
			echo '
				<img src="', ASSETS, '/aeva/star.gif" title="', $txt['media_featured_album'], '">';

		echo '
				<a href="', $galurl, 'sa=album;in=', $album['id'], '">', $album['name'], '</a>', $show_move ? '
				' . $album['move_links']['child_of'] : '', '
				</td>
				<td class="album_moderation">';

		if ($can_manage || $album['owner']['id'] == MID)
			echo '
					<img src="', ASSETS, '/aeva/folder_edit.png">&nbsp;<a href="', $alburl, 'sa=edit;in=', $album['id'], '">', $txt['media_edit_this_item'], '</a>
					<img src="', ASSETS, '/aeva/folder_delete.png">&nbsp;<a href="', $alburl, 'sa=delete;in=', $album['id'], '" onclick="return ask(', JavaScriptEscape($txt['media_admin_album_confirm']), ', e);">', $txt['media_admin_delete'], '</a>
					<img src="', ASSETS, '/aeva/arrow_inout.png" title="', $txt['media_admin_move'], '">&nbsp;<a href="' . $alburl . 'move=' . $album['id'] . '">' . $txt['media_admin_move'] . '</a>', $album['approved'] == 0 && $can_moderate ? '
					<img src="' . ASSETS . '/aeva/tick.png" title="' . $txt['media_admin_approve'] . '">&nbsp;<a href="' . $galurl . 'area=moderate;sa=submissions;do=approve;type=albums;in=' . $album['id'] . '">' . $txt['media_admin_approve'] . '</a>' : '';

		echo '
				</td>
			</tr>
			<tr class="windowbg hide" id="tr_expand_', $album['id'], '">
				<td colspan="4">
					<img src="" id="img_', $album['id'], '" style="float: left; margin-right: 8px">
					<div>', $txt['media_items'], ': ', $album['num_items'], '</div>', !empty($album['description']) ? '
					<div>' . $txt['media_add_desc'] . ': ' . $album['description'] . '</div>' : '', !empty($album['owner']['id']) ? '
					<div>' . $txt['media_owner'] . ': ' . aeva_profile($album['owner']['id'], $album['owner']['name']) . '</div>' : '';

		echo '
				</td>
			</tr>';
	}
	echo '
		</table>';
}

function template_aeva_stats()
{
	global $txt, $galurl, $context;

	$stats = $context['aeva_stats'];

	// Show the general stats
	echo '
		<table class="table_grid w100 cs1 cp4" style="margin-top: 10px">
			<tr class="titlebg center">
				<th colspan="2" style="border-radius: 8px 8px 0 0"><img src="', ASSETS, '/aeva/chart_pie.png" class="vam"> ', $txt['media_gen_stats'], '</td>
			</tr>
			<tr>
				<td class="w50 windowbg2">
					<table class="w100 cs0 cp4">
						<tr>
							<td><img src="', ASSETS, '/aeva/images.png" class="vam"> ', $txt['media_total_items'], '</td>
							<td class="right">', $stats['total_items'], '</td>
						</tr>
						<tr>
							<td><img src="', ASSETS, '/aeva/comments.png" class="vam"> ', $txt['media_total_comments'], '</td>
							<td class="right">', $stats['total_comments'], '</td>
						</tr>
						<tr>
							<td><img src="', ASSETS, '/aeva/folder_image.png" class="vam"> ', $txt['media_total_albums'], '</td>
							<td class="right">', $stats['total_albums'], '</td>
						</tr>
						<tr>
							<td><img src="', ASSETS, '/aeva/group.png" class="vam"> ', $txt['media_total_featured_albums'], '</td>
							<td class="right">', $stats['total_featured_albums'], '</td>
						</tr>
					</table>
				</td>
				<td class="windowbg2">
					<table class="w100 cs0 cp4">
						<tr>
							<td><img src="', ASSETS, '/aeva/images.png" class="vam"> ', $txt['media_avg_items'], '</td>
							<td class="right">', $stats['avg_items'], '</td>
						</tr>
						<tr>
							<td><img src="', ASSETS, '/aeva/comments.png" class="vam"> ', $txt['media_avg_comments'], '</td>
							<td class="right">', $stats['avg_comments'], '</td>
						</tr>
						<tr>
							<td><img src="', ASSETS, '/aeva/user.png" class="vam"> ', $txt['media_total_item_contributors'], '</td>
							<td class="right">', $stats['total_item_posters'], '</td>
						</tr>
						<tr>
							<td><img src="', ASSETS, '/aeva/user_comment.png" class="vam"> ', $txt['media_total_commentators'], '</td>
							<td class="right">', $stats['total_commentators'], '</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr class="titlebg center">
				<td class="w50"><img src="', ASSETS, '/aeva/user.png" class="vam"> ', $txt['media_top_uploaders'], '</td>
				<td><img src="', ASSETS, '/aeva/user_comment.png" class="vam"> ', $txt['media_top_commentators'], '</td>
			</tr>
			<tr>
				<td class="windowbg2 top">
					<table class="w100 cs0 cp4">';

	foreach ($stats['top_uploaders'] as $uploader)
		echo '
						<tr>
							<td class="left" style="width: 60%">', aeva_profile($uploader['id'], $uploader['name']), '</td>
							<td style="width: 20%"><div class="aeva_statsbar2" style="width: ', $uploader['percent'], 'px"></div></td>
							<td class="right">', $uploader['total_items'], '</td>
						</tr>';

	if (empty($stats['top_uploaders']))
		echo '
						<tr>
							<td class="center">', $txt['media_no_uploaders'], '</td>
						</tr>';
	echo '
					</table>
				</td>
				<td class="windowbg2 top">
					<table class="w100 cs0 cp4">';

	foreach ($stats['top_commentators'] as $uploader)
		echo '
						<tr>
							<td class="left" style="width: 60%">', aeva_profile($uploader['id'], $uploader['name']), '</td>
							<td style="width: 20%"><div class="aeva_statsbar" style="width: ', $uploader['percent'], 'px"></div></td>
							<td class="right">', $uploader['total_comments'], '</td>
						</tr>';

	if (empty($stats['top_commentators']))
		echo '
						<tr>
							<td class="center">', $txt['media_no_commentators'], '</td>
						</tr>';

	echo '
					</table>
				</td>
			</tr>
			<tr class="titlebg center">
				<td class="w50"><img src="', ASSETS, '/aeva/folder_image.png" class="vam"> ', $txt['media_top_albums_items'], '</td>
				<td><img src="', ASSETS, '/aeva/folder_table.png" class="vam"> ', $txt['media_top_albums_comments'], '</td>
			</tr>
			<tr>
				<td class="windowbg2 top">
					<table class="w100 cs0 cp4">';

	foreach ($stats['top_albums_items'] as $album)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=album;in=', $album['id'], '">', $album['name'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar" style="width: ', $album['percent'], 'px"></div></td>
							<td class="right">', $album['num_items'], '</td>
						</tr>';

	if (empty($stats['top_albums_items']))
		echo '
						<tr>
							<td class="center">', $txt['media_no_albums'], '</td>
						</tr>';

	echo '
					</table>
				</td>
				<td class="windowbg2 top">
					<table class="w100 cs0 cp4">';

	foreach ($stats['top_albums_comments'] as $album)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=album;in=', $album['id'], '">', $album['name'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar2" style="width: ', $album['percent'], 'px"></div></td>
							<td class="right">', $album['num_comments'], '</td>
						</tr>';

	if (empty($stats['top_albums_comments']))
		echo '
						<tr>
							<td class="center">', $txt['media_no_albums'], '</td>
						</tr>';

	echo '
					</table>
				</td>
			</tr>
			<tr class="titlebg center">
				<td class="w50"><img src="', ASSETS, '/aeva/images.png" class="vam"> ', $txt['media_top_items_views'], '</td>
				<td><img src="', ASSETS, '/aeva/comments.png" class="vam"> ', $txt['media_top_items_comments'], '</td>
			</tr>
			<tr>
				<td class="windowbg2 top">
					<table class="w100 cs0 cp4">';

	foreach ($stats['top_items_views'] as $item)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=item;in=', $item['id'], '">', $item['title'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar2" style="width: ', $item['percent'], 'px"></div></td>
							<td class="right">', $item['views'], '</td>
						</tr>';

	if (empty($stats['top_items_views']))
		echo '
						<tr>
							<td class="center">', $txt['media_no_items'], '</td>
						</tr>';

	echo '
					</table>
				</td>
				<td class="windowbg2 top">
					<table class="w100 cs0 cp4">';

	foreach ($stats['top_items_com'] as $item)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=item;in=', $item['id'], '">', $item['title'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar" style="width: ', $item['percent'], 'px"></div></td>
							<td class="right">', $item['num_com'], '</td>
						</tr>';

	if (empty($stats['top_items_com']))
		echo '
						<tr>
							<td class="center">', $txt['media_no_items'], '</td>
						</tr>';

	echo '
					</table>
				</td>
			</tr>
			<tr class="titlebg center">
				<td class="w50"><img src="', ASSETS, '/aeva/chart_pie.png" class="vam"> ', $txt['media_top_items_rating'], '</td>
				<td><img src="', ASSETS, '/aeva/star.gif" class="vam"> ', $txt['media_top_items_voters'], '</td>
			</tr>
			<tr>
				<td class="windowbg2 top">
					<table class="w100 cs0 cp4">';

	foreach ($stats['top_items_rating'] as $item)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=item;in=', $item['id'], '">', $item['title'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar" style="width: ', $item['percent'], 'px"></div></td>
							<td class="right">', $item['rating'], '</td>
						</tr>';

	if (empty($stats['top_items_rating']))
		echo '
						<tr>
							<td class="center">', $txt['media_no_items'], '</td>
						</tr>';
	echo '
					</table>
				</td>
				<td class="windowbg2 top">
					<table class="w100 cs0 cp4">';

	foreach ($stats['top_items_voters'] as $item)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=item;in=', $item['id'], '">', $item['title'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar2" style="width: ', $item['percent'], 'px"></div></td>
							<td class="right">', $item['voters'], '</td>
						</tr>';

	if (empty($stats['top_items_voters']))
		echo '
						<tr>
							<td class="center">', $txt['media_no_items'], '</td>
						</tr>';
	echo '
					</table>
				</td>
			</tr>
		</table>';
}

function template_aeva_text_editor()
{
	global $context;

	echo $context['postbox']->outputEditor();
}

function template_aeva_multiUpload_xml()
{
	global $context, $txt;

	// Prepare the items
	$item_array = array();
	foreach ($context['items'] as $item)
		$item_array[] = $item['id'] . ',' . $item['title'];

	// Prepare the errors
	$error_array = array();
	$error_list = array(
		'file_not_found' => 'upload_failed', 'dest_not_found' => 'upload_failed', 'size_too_big' => 'upload_file_too_big',
		'width_bigger' => 'error_width', 'height_bigger' => 'error_height', 'invalid_extension' => 'invalid_extension', 'dest_empty' => 'dest_failed',
	);

	foreach ($context['errors'] as $error)
	{
		$message = isset($error_list[$error['code']]) ? $txt['media_' . $error_list[$error['code']]] : $txt['media_upload_failed'];
		$error_array[] = $error['fname'] . ' - ' . (isset($error['context']) ? sprintf($message, $error['context']) : $message);
	}

	// Output it
	echo implode(';', $item_array), '|', implode(';', $error_array);
}

function template_aeva_multiUpload()
{
	global $context, $txt;

	echo '
	<div class="windowbg wrc_top">
		<ul class="list">
			<li>', $txt['media_max_file_size'], ': ', $txt['media_image'], ' - ', $context['aeva_max_file_size']['image'], ' ', $txt['media_kb'], ', ', $txt['media_video'], ' - ', $context['aeva_max_file_size']['video'], ' ', $txt['media_kb'], ', ', $txt['media_audio'], ' - ', $context['aeva_max_file_size']['audio'], ' ', $txt['media_kb'], ', ', $txt['media_doc'], ' - ', $context['aeva_max_file_size']['doc'], ' ', $txt['media_kb'], '
			<li>', $txt['media_needs_js_flash'], '</li>
		</ul>
	</div>
	<div class="windowbg2 wrc_bottom">
		<we:title2>
			', $txt['media_add_allowedTypes'], '
		</we:title2>
		<ul class="list">';

	foreach ($context['allowed_types'] as $k => $v)
		echo '
			<li><b>', $txt['media_filetype_' . $k], '</b>: ', str_replace('*.', '', implode(', ', $v)), '</li>';

	echo '
		</ul>
	</div>
	<div class="roundframe" id="mu_container">
		<form action="', SCRIPT, '">
			<strong>1</strong>. ', $txt['media_sort_order'], ' &ndash;
			<select id="sort_order" name="sort_order">
				<option value="1" selected>', $txt['media_sort_order_filename'], '</option>
				<option value="2">', $txt['media_sort_order_filedate'], '</option>
				<option value="3">', $txt['media_sort_order_filesize'], '</option>
			</select>
		</form>

		<form action="', $context['aeva_submit_url'], '" id="upload_form" method="post">
			<p>
				<strong>2</strong>. <span id="browse" style="position: absolute; z-index: 2"></span>
				<span id="browseBtnSpan" style="z-index: 1"><a id="browseBtn" href="#">', $txt['media_selectFiles'], '</a></span> |
				<strong>3</strong>. <a id="upload" href="#">', $txt['media_upload'], '</a>
			</p>
			<div>
				<strong id="overall_title" class="overall-title">', $txt['media_overall_prog'], '</strong><br>
				<img src="', ASSETS, '/aeva/bar.gif" class="progress overall-progress" id="overall_progress"> <strong id="overall_prog_perc">0%</strong>
			</div>
			<div>
				<strong class="current-title" id="current_title">', $txt['media_curr_prog'], '</strong><br>
				<img src="', ASSETS, '/aeva/bar.gif" class="progress2 current-progress" id="current_progress"> <strong id="current_prog_perc">0%</strong>
			</div>
			<div class="current-text" id="current_text"></div>

			<ul id="current_list">
				<li id="remove_me" style="display: none"></li>
			</ul>
			<br class="clear">
			<div style="text-align: center" id="mu_items"><input type="submit" name="aeva_submit" value="', $txt['media_submit'], '"></div>
		</form>
	</div>';
}

// Profile summary template
function template_aeva_profile_summary()
{
	global $txt, $galurl, $context, $settings;

	$member =& $context['aeva_member'];
	$can_feed = !empty($settings['xmlnews_enable']);

	echo '
		<we:cat>
			<img src="', ASSETS, '/admin/mgallery.png" style="vertical-align: 0">
			', $txt['media_profile_sum_pt'], '
		</we:cat>
		<p class="description">
			', $txt['media_profile_sum_desc'], '
		</p>

		<we:title>
			', $txt['media_profile_stats'], '
		</we:title>
		<div class="windowbg2 wrc">
			', $can_feed ? '<a href="<URL>?action=feed;sa=media;user=' . $member['id'] . '" class="feed_icon" title="' . $txt['feed'] . '"></a>' : '', ' <a href="<URL>?action=profile;u=', $member['id'], ';area=aevaitems">', $txt['media_total_items'], '</a>: ', $member['items'], '<br>
			', $can_feed ? '<a href="<URL>?action=feed;sa=media;user=' . $member['id'] . ';type=comments" class="feed_icon" title="' . $txt['feed'] . '"></a>' : '', ' <a href="<URL>?action=profile;u=', $member['id'], ';area=aevacoms">', $txt['media_total_comments'], '</a>: ', $member['coms'], '<br>
			', $txt['media_avg_items'], ': ', $member['avg_items'], '<br>
			', $txt['media_avg_comments'], ': ', $member['avg_coms'], '<br>
		</div>';

	if (!empty($member['user_albums']))
	{
		$can_moderate = aeva_allowedTo('moderate');

		echo '
		<we:title>
			', $can_feed ? '<a href="<URL>?action=feed;sa=media;user=' . $member['id'] . ';albums" class="feed_icon"></a> ' : '', $txt['media_albums'], '
		</we:title>';

		aeva_listChildren($member['user_albums']);
	}

	if (!empty($member['recent_items']))
	{
		echo '
		<we:title>
			', $txt['media_recent_items'], '
		</we:title>

		<div class="windowbg2 wrc" id="recent_items">',
			aeva_listItems($member['recent_items']), '
		</div>';
	}

	if (!empty($member['top_albums']))
	{
		echo '
		<we:title>
			', $txt['media_top_albums'], '
		</we:title>
		<div class="windowbg2 wrc">
			<table class="w100 cs1 cp4">';

		foreach ($member['top_albums'] as $album)
			echo '
				<tr>
					<td class="windowbg2 w50"><a href="', $galurl, 'sa=album;in=', $album['id'], '">', $album['name'], '</a></td>
					<td class="windowbg2" style="width: 40%"><div class="aeva_statsbar" style="width: ', $album['percent'], 'px"></div></td>
					<td class="windowbg2" style="width: 10%">', $album['total_items'], '</td>
				</tr>';

		echo '
			</table>
		</div>';
	}
}

// Template for viewing all items from a single member
function template_aeva_profile_viewitems()
{
	global $context, $txt;

	echo '
		<we:cat>
			<img src="', ASSETS, '/admin/mgallery.png" style="vertical-align: 0">
			', $txt['media_profile_viewitems_pt'], '
		</we:cat>
		<p class="description">
			', $txt['media_profile_viewitems_desc'], '
		</p>

		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>
		<div id="recent_items">',
			aeva_listItems($context['aeva_items']), '
		</div>
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';
}

// Template for viewing all items from a single member
function template_aeva_profile_viewcoms()
{
	global $context, $txt;

	echo '
		<we:cat>
			<img src="', ASSETS, '/admin/mgallery.png" style="vertical-align: 0">
			', $txt['media_profile_viewcoms_pt'], '
		</we:cat>
		<p class="description">
			', $txt['media_profile_viewcoms_desc'], '
		</p>

		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>
		<div>';

	// Recent comments!
	if (!empty($context['aeva_coms']))
	{
		foreach ($context['aeva_coms'] as $i)
			echo '
		<div class="smalltext" style="padding: 8px">
			', $txt['media_comment_in'], ' <a href="', $i['url'], '"><b>', $i['media_title'], '</a></b> - ',
			$txt['media_posted_on' . (is_numeric($i['posted_on'][0]) ? '_date' : '')], ' ', $i['posted_on'], '
			<blockquote class="windowbg comment_preview">', parse_bbc($i['msg'], 'media-comment'), '</blockquote>
		</div>';
	}

	echo '
		</div>
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';
}

// Template for viewing all votes from a single member
function template_aeva_profile_viewvotes()
{
	global $context, $txt;

	echo '
		<we:cat>
			<img src="', ASSETS, '/admin/mgallery.png" style="vertical-align: 0">
			', $txt['media_profile_viewvotes_pt'], '
		</we:cat>
		<p class="description">
			', $txt['media_profile_viewvotes_desc'], '
		</p>

		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>
		<div style="padding: 4px 0">';

	if (!empty($context['aeva_ratingLogs']))
	{
		echo '
			<we:title>
				', $context['aeva_voter_name'], '
			</we:title>';

		foreach ($context['aeva_ratingLogs'] as $log)
		{
			echo '
			', aeva_showStars($log['star']), '&nbsp;&nbsp;<b><a href="<URL>?action=media;sa=item;in=' . $log['id_media'] . '">' . $log['title'] . '</a></b>';
			echo ' (<a href="<URL>?action=media;sa=album;in=' . $log['album_id'] . '">' . $log['name'] . '</a>)';
			if (!empty($log['messages']))
				echo ' (' . $log['messages'] . ' <a href="<URL>?topic=' . $log['id_topic'] . '.0">' . $txt['media_post' . ($log['messages'] > 1 ? 's' : '') . '_noun'] . '</a>)';
			echo '<br>';
		}
		echo '<br>
			<we:title>
				', $txt['media_voter_list'], '
			</we:title>';
		foreach ($context['aeva_otherVoters'] as $row)
			echo '<br><a href="<URL>?action=profile;u=' . $row['id_member'] . ';area=aevavotes">' . $row['real_name'] . '</a> (' . $row['co'] . ' ' . $txt['media_vote' . ($row['co'] > 1 ? 's' : '') . '_noun'] . ')';
	}

	echo '
		</div>
		<div class="pagesection">
			<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
		</div>';
}

// Who rated what list
function template_aeva_whoRatedWhat()
{
	global $context, $txt, $galurl;

	echo '
		<table class="bordercolor w100 cs1 cp8">
			<tr>
				<td colspan="3" class="windowbg2 center">', $txt['media_who_rated_what'], ' (<a href="', $galurl, 'sa=item;in=', $context['item']['id_media'], '">', $context['item']['title'], '</a>)</td>
			</tr>
			<tr class="titlebg">
				<td style="width: 60%">', $txt['media_member'], '</td>
				<td style="width: 15%">', $txt['media_rating'], '</td>
				<td style="width: 25%">', $txt['media_date'], '</td>
			</tr>
			<tr>
				<td class="windowbg2" colspan="3">', $txt['pages'], ': ', $context['page_index'], '</td>
			</tr>';

		foreach ($context['item']['rating_logs'] as $log)
		{
			echo '
				<tr>
					<td class="windowbg2"><a href="', $log['member_link'], '">', $log['member_name'], '</a></td>
					<td class="windowbg2">', aeva_showStars($log['rating']), '</td>
					<td class="windowbg2">', $log['time'], '</td>
				</tr>';
		}
	echo '
		</table>';
}

// Filestack view for gallery
function aeva_listFiles($items, $can_moderate = false)
{
	global $galurl, $context, $txt;

	$can_moderate_one = $can_moderate_here = aeva_allowedTo('moderate');
	if (!$can_moderate_one)
		foreach ($items as $item)
			$can_moderate_one |= $item['poster_id'] == MID;

	echo '
		<table class="bordercolor w100 cs1 cp4">
			<tr class="catbg">
				<td style="height: 25px">', $txt['media_name'], '</td>
				<td>', $txt['media_posted_on'], '</td>
				<td>', $txt['media_views'], '</td>
				<td>', $txt['media_comments'], '</td>
				<td>', $txt['media_rating'], '</td>', $can_moderate && $can_moderate_one ? '
				<td style="width: 5%"></td>' : '', '
			</tr>';

	$alt = false;
	foreach ($items as $item)
	{
		$check = $can_moderate && ($can_moderate_here || $item['poster_id'] == MID) ? '
				<td class="bottom"><input type="checkbox" name="mod_item[' . $item['id'] . ']"></td>' : '';
		echo '
			<tr class="windowbg', $alt ? '2' : '', $item['approved'] ? '' : ' unapp', '">
				<td>
					<strong><a href="', $galurl, 'sa=item;in=', $item['id'], '">', trim($item['title']) == '' ? '...' : $item['title'], '</a></strong>', empty($context['aeva_album']) || $item['poster_id'] != $context['aeva_album']['owner']['id'] ? '
					' . strtolower($txt['media_posted_by']) . ' ' . aeva_profile($item['poster_id'], $item['poster_name']) : '', empty($context['aeva_album']) ? '
					' . $txt['media_in_album'] . ' <a href="' . $galurl . 'sa=album;in=' . $item['id_album'] . '">' . $item['album_name'] . '</a>' : '', '
				</td>
				<td>', $item['posted_on'], '</td>
				<td>', !empty($item['views']) ? $item['views'] : '', '</td>
				<td>', !empty($item['comments']) ? $item['comments'] : '', '</td>
				<td>', !empty($item['rating']) ? $item['rating'] . ' <span class="smalltext">(' . $item['voters'] . ' ' . $txt['media_vote' . ($item['voters'] > 1 ? 's' : '') . '_noun'] . ')</span>' : '', '</td>',
				$check, !empty($item['desc']) ? '
			</tr>
			<tr class="windowbg' . ($alt ? '2' : '') . ($item['approved'] ? '' : ' unapp') . '">
				<td colspan="' . ($check ? '6' : '5') . '">
					<div class="mg_desc" style="padding: 8px">' . parse_bbc($item['desc'], 'media-description') . '</div>
				</td>' : '', '
			</tr>';
		$alt = !$alt;
	}
	echo '
		</table>';

	return '';
}

function template_aeva_playlist()
{
	global $context;

	if (!empty($context['aeva_foxy_rendered_playlist']))
		echo $context['aeva_foxy_rendered_playlist'];
}

function template_aeva_rating_object($item)
{
	global $txt, $galurl;

	$object = ($item['can_rate'] ? '
				<form action="' . $galurl . 'sa=item;in=' . $item['id_media'] . '" method="post" id="ratingF">' : '') . '
					' . ($item['voters'] > 0 ? aeva_showStars($item['avg_rating'], '') . round($item['avg_rating'], 2) . '
					(' . (aeva_allowedTo('whoratedwhat') ? '<a href="' . $galurl . 'sa=whoratedwhat;in=' . $item['id_media'] . '">' : '') .
					$item['voters'] . ' ' . $txt['media_vote' . ($item['voters'] > 1 ? 's' : '') . '_noun'] . (aeva_allowedTo('whoratedwhat') ? '</a>' : '') . ')' : '') .
					(!empty($item['weighted']) ? ' (' . $txt['media_weighted_mean'] . ': ' . sprintf('%01.2f', $item['weighted']) . ')' : '');

	if ($item['can_rate'])
		$object .= '
					<select name="rating" id="rating">
						<option value="0">0</option>
						<option value="1">1</option>
						<option value="2">2</option>
						<option value="3">3</option>
						<option value="4">4</option>
						<option value="5">5</option>
					</select>
					<input type="button" value="' . $txt['media_rate_it'] . '" onclick="ajaxRating();">
				</form>';

	return $object;
}
