<?php
/****************************************************************
* Aeva Media													*
* © Noisen.com & SMF-Media.com									*
*****************************************************************
* Aeva.template.php												*
*****************************************************************
* Users of this software are bound by the terms of the			*
* Aeva Media license. You can view it in the license_am.txt		*
* file, or online at http://noisen.com/license-am2.php			*
*																*
* Support and updates for this software can be found at			*
* http://aeva.noisen.com and http://smf-media.com				*
****************************************************************/

function template_aeva_popup_above()
{
	echo '
	<div id="content_section">
		<div class="frame">
			<div style="padding: 14px 0">';
}

function template_aeva_popup_below()
{
	echo '
			</div>
		</div>
		<div class="popup_copyright">', theme_copyright(), '</div>
	</div>';
}

function template_aeva_header()
{
	global $context, $txt, $amSettings, $scripturl, $settings, $user_info;

	// Show Media tabs, except if not inside the gallery itself or if uploading via a popup
	if (empty($context['current_board']) && !isset($_REQUEST['noh']))
		echo '
	<we:cat>
		', !isset($context['aeva_header']['data']['title']) ? $txt['media_gallery'] : $context['aeva_header']['data']['title'], '
	</we:cat>';

	// Any unapproved stuff?
	if (allowedTo('media_moderate') && (!empty($amSettings['num_unapproved_items']) || !empty($amSettings['num_unapproved_comments']) || !empty($amSettings['num_unapproved_albums'])))
	{
		echo '
	<div class="unapproved_notice">';
		if (!empty($amSettings['num_unapproved_items']))
			printf($txt['media_unapproved_items_notice'] . '<br />', $scripturl.'?action=media;area=moderate;sa=submissions;filter=items;'.$context['session_var'].'='.$context['session_id'], $amSettings['num_unapproved_items']);
		if (!empty($amSettings['num_unapproved_comments']))
			printf($txt['media_unapproved_coms_notice'] . '<br />', $scripturl.'?action=media;area=moderate;sa=submissions;filter=coms;'.$context['session_var'].'='.$context['session_id'], $amSettings['num_unapproved_comments']);
		if (!empty($amSettings['num_unapproved_albums']))
			printf($txt['media_unapproved_albums_notice'], $scripturl.'?action=media;area=moderate;sa=submissions;filter=albums;'.$context['session_var'].'='.$context['session_id'], $amSettings['num_unapproved_albums']);
		echo '</div>';
	}

	// Any reported stuff?
	if (allowedTo('media_moderate') && (!empty($amSettings['num_reported_items']) || !empty($amSettings['num_reported_comments'])))
	{
		echo '
	<div class="unapproved_notice">';
		if (!empty($amSettings['num_reported_items']))
			printf($txt['media_reported_items_notice'] . '<br />', $scripturl.'?action=media;area=moderate;sa=reports;items;'.$context['session_var'].'='.$context['session_id'], $amSettings['num_reported_items']);
		if (!empty($amSettings['num_reported_comments']))
			printf($txt['media_reported_comments_notice'] . '<br />', $scripturl.'?action=media;area=moderate;sa=reports;comments;'.$context['session_var'].'='.$context['session_id'], $amSettings['num_reported_comments']);
		echo '</div>';
	}

	// Any further data to show?
	if (!empty($context['aeva_header']['data']['description']))
		echo '
	<we:cat>
		', !isset($context['aeva_header']['data']['title']) ? $txt['media_gallery'] : $context['aeva_header']['data']['title'], '
	</we:cat>
	<div class="windowbg wrc description">
		', $context['aeva_header']['data']['description'], '
	</div>';
}

function template_show_version_update()
{
	global $context;

	echo '
	<div class="windowbg wrc" style="margin: 0 0 8px">
		', $context['aeva_update_message'], '
	</div>';
}

function template_aeva_subtabs()
{
	global $context;

	// area-tabs maybe?
	if (!empty($context['aeva_header']['areatabs']))
	{
		$buttons = array();
		foreach ($context['aeva_header']['areatabs'] as $tab)
			$buttons[] = array(
				'text' => $tab['title'],
				'url' => $tab['url'],
				'custom' => $tab['active'] ? 'class="currentbutton"' : '',
			);
		template_button_strip($buttons);
	}

	// sub-tabs maybe?
	if (!empty($context['aeva_header']['subtabs']))
	{
		$buttons = array();
		foreach ($context['aeva_header']['subtabs'] as $tab)
			$buttons[] = array(
				'text' => $tab['title'],
				'url' => $tab['url'],
				'custom' => $tab['active'] ? 'class="currentbutton"' : '',
			);
		template_button_strip($buttons);
	}
}

function template_aeva_home()
{
	global $context, $amSettings, $txt, $galurl, $scripturl, $settings;

	$has_albums = count($context['aeva_albums']) > 0;
	$can_rss = empty($amSettings['disable_rss']);

	// The Albums!
	echo '
<div id="home">', !empty($context['aeva_welcome']) ? '
	<div id="aeva_welcome">' . $context['aeva_welcome'] . '</div>' : '', '
	<div id="aeva_toplinks">
		<we:cat>
			<img src="'.$settings['images_aeva'].'/house.png" style="vertical-align: -3px" /> <b>'.$txt['media_home'].'</b>', $context['show_albums_link'] ? ' -
			<img src="'.$settings['images_aeva'].'/album.png" style="vertical-align: -3px" /> <b><a href="'.$galurl.'sa=vua">'.$txt['media_albums'].'</a></b>' : '', empty($amSettings['disable_playlists']) ? ' -
			<img src="'.$settings['images_aeva'].'/playlist.png" style="vertical-align: -3px" /> <b><a href="'.$galurl.'sa=playlists">'.$txt['media_playlists'].'</a></b>' : '', '
		</we:cat>
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
	<we:title>
		', $txt['media_recent_items'], $can_rss ?
		' <a href="'.$galurl.'sa=rss"><img src="'.$settings['images_aeva'].'/rss.png" alt="RSS" class="aeva_vera" /></a>' : '', '
	</we:title>';

		// Page index and sorting things
		$sort_list = array('m.id_media' => 0, 'm.time_added' => 1, 'm.title' => 2, 'm.views' => 3, 'm.weighted' => 4);
		echo '
	<div class="titlebg sort_options">
		<div class="view_options">
			', $txt['media_items_view'], ': ', $view == 'normal' ? '<b>' . $txt['media_view_normal'] . '</b> <a href="' . $galurl . 'fw;'. $context['aeva_urlmore'] . '">' . $txt['media_view_filestack'] . '</a>' : '<a href="' . $galurl . $context['aeva_urlmore'] . '">' . $txt['media_view_normal'] . '</a> <b>' . $txt['media_view_filestack'] . '</b>', '
		</div>
		', $txt['media_sort_by'], ':';
		$sort = empty($sort_list[$context['aeva_sort']]) ? 0 : $sort_list[$context['aeva_sort']];
		for ($i = 0; $i < 5; $i++)
			echo $sort == $i ? ' <b>' . $txt['media_sort_by_'.$i] . '</b>' :
		' <a href="'.$galurl.'sort=' . $i . ($view == 'normal' ? '' : ';fw') . '">' . $txt['media_sort_by_'.$i] . '</a>';
		echo '
		| ', $txt['media_sort_order'], ':',
		($context['aeva_asc'] ? ' <b>' . $txt['media_sort_order_asc'] . '</b>' : ' <a href="' . $galurl . (isset($_REQUEST['sort']) ? 'sort='.$_REQUEST['sort'].';' : '') . 'asc' . ($view == 'normal' ? '' : ';fw') . '">' . $txt['media_sort_order_asc'] . '</a>'),
		(!$context['aeva_asc'] ? ' <b>' . $txt['media_sort_order_desc'] . '</b>' : ' <a href="' . $galurl . (isset($_REQUEST['sort']) ? 'sort=' . $_REQUEST['sort'].';' : '') . 'desc' . ($view == 'normal' ? '' : ';fw') . '">'.$txt['media_sort_order_desc'].'</a>'), '
	</div>
	<div id="recent_pics">',
		$view == 'normal' ? aeva_listItems($context['recent_items']) : aeva_listFiles($context['recent_items']), '
	</div>
	<div class="pagelinks">
		', $txt['media_pages'], ': ', $context['aeva_page_index'], '
	</div>';
	}

	// Random items?
	if (!empty($context['random_items']))
	{
		echo '
	<we:title>
		', $txt['media_random_items'], '
	</we:title>
	<div id="random_pics">',
		$view == 'normal' ? aeva_listItems($context['random_items']) : aeva_listFiles($context['random_items']), '
	</div>';
	}

	// Recent comments!
	if (!empty($context['recent_comments']))
	{
		echo '
	<div', !empty($context['recent_albums']) ? ' class="recent_comments"' : '', '>
		<we:title>
			', $txt['media_recent_comments'], $can_rss ?
			' <a href="'.$galurl.'sa=rss;type=comments"><img src="'.$settings['images_aeva'].'/rss.png" alt="RSS" class="aeva_vera" /></a>' : '', '
		</we:title>
		<div class="windowbg wrc smalltext">
			<div style="line-height: 1.4em">';

		foreach ($context['recent_comments'] as $i)
			echo '
				<div>', $txt['media_comment_in'], ' <a href="', $i['url'], '">', $i['media_title'], '</a> ', $txt['media_by'],
				' ', $i['member_link'], ' ', is_numeric($i['posted_on'][0]) ? $txt['media_on_date'] . ' ' : '', $i['posted_on'], '</div>';

		echo '
			</div>
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
		<div class="windowbg wrc smalltext" style="line-height: 1.4em">';

		foreach ($context['recent_albums'] as $i)
			echo '
			<div><a href="', $scripturl, '?action=media;sa=album;in=', $i['id'], '">', $i['name'], '</a> (',
		$i['num_items'], ')', !empty($i['owner_id']) ? ' ' . $txt['media_by'] . ' <a href="' . $scripturl . '?action=profile;u=' . $i['owner_id'] . ';area=aeva">' . $i['owner_name'] . '</a>' : '', '</div>';

		echo '
		</div>
	</div>';
	}

	// Fix
	if (!empty($context['recent_comments']) && !empty($context['recent_albums']))
		echo '<br style="clear: both" />';

	// Show some general stats'n'stuff below
	echo '
	<we:title>
		<img src="', $settings['images_aeva'], '/chart_bar.png" class="vam" />&nbsp;<a href="', $galurl, 'sa=stats">', $txt['media_stats'], '</a>
	</we:title>
	<div class="windowbg wrc smalltext" style="line-height: 1.4em">
		<table cellpadding="0" cellspacing="0" width="100%"><tr><td>
			<div>', $txt['media_total_items'], ': ', $amSettings['total_items'], '</div>
			<div>', $txt['media_total_albums'], ': ', $amSettings['total_albums'], '</div>
			<div>', $txt['media_total_comments'], ': ', $amSettings['total_comments'], '</div>', allowedTo('media_manage') ? '
			' . show_stat($txt['media_reported_items'], $amSettings['num_reported_items']) : '', '
		</td>';

	if (allowedTo('media_moderate'))
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
	return '<div>' . ($number > 0 ? '<b>' : '') . $intro . ': ' . $number . ($number > 0 ? '</b>' : '') . '</div>';
}

function show_prevnext($id, $url)
{
	global $galurl, $context;

	$trans = $url[3] == 'transparent' ? '; ' . ($context['browser']['is_webkit'] ? '-webkit-' : ($context['browser']['is_firefox'] ? '-moz-' : '')) . 'box-shadow: none' : '';

	echo '<div class="aea" style="width: ', $url[1], 'px; height: ', $url[2], 'px; background: url(', $url[0], ') 0 0', $trans, '">',
			$id ? '<a href="' . $galurl . 'sa=item;in=' . $id . '">&nbsp;</a></div>' : '&nbsp;</div>';
}

function template_aeva_viewItem()
{
	global $galurl, $context, $amSettings, $txt, $scripturl, $settings, $boardurl, $user_info;

	$item = $context['item_data'];

	if (isset($_REQUEST['noh']))
		echo sprintf($txt['media_foxy_add_tag'], 'javascript:insertTag();');

	// Show the item and info boxes
	echo '
	<div id="viewitem">';

	if (empty($amSettings['prev_next'])) // 3 items
		echo '
		<table cellspacing="0" cellpadding="2" border="0" class="mg_prevnext windowbg" width="100%">
			<tr class="mg_prevnext_pad">
				<td rowspan="2">', (int) $item['prev'] > 0 ? '<a href="'.$galurl.'sa=item;in='.$item['prev'].'">&laquo;</a>' : '&laquo;', '</td>
				<td width="33%">', (int) $item['prev'] > 0 ? show_prevnext($item['prev'], $item['prev_thumb']) : $txt['media_prev'], '</td>
				<td width="34%" class="windowbg2">', show_prevnext(0, $item['current_thumb']), '</td>
				<td width="33%">', (int) $item['next'] > 0 ? show_prevnext($item['next'], $item['next_thumb']) : $txt['media_next'], '</td>
				<td rowspan="2">', (int) $item['next'] > 0 ? '<a href="'.$galurl.'sa=item;in='.$item['next'].'">&raquo;</a>' : '&raquo;', '</td>
			</tr>
			<tr class="smalltext">
				<td>', (int) $item['prev'] > 0 ? '<a href="'.$galurl.'sa=item;in='.$item['prev'].'">'.$item['prev_title'].'</a>' : '', '</td>
				<td class="windowbg2">'.$item['current_title'].'</td>
				<td>', (int) $item['next'] > 0 ? '<a href="'.$galurl.'sa=item;in='.$item['next'].'">'.$item['next_title'].'</a>' : '', '</td>
			</tr>
		</table>';
	elseif ($amSettings['prev_next'] == 1) // 5 items
		echo '
		<table cellspacing="0" cellpadding="3" border="0" class="mg_prevnext windowbg" width="100%">
			<tr class="mg_prevnext_pad">
				<td rowspan="2">', (int) $item['prev_page'] > 0 ? '<a href="'.$galurl.'sa=item;in='.$item['prev_page'].'">&laquo;</a>' : '&laquo;', '</td>
				<td width="20%">', (int) $item['prev2'] > 0 ? show_prevnext($item['prev2'], $item['prev2_thumb']) : '', '</td>
				<td width="20%">', (int) $item['prev'] > 0 ? show_prevnext($item['prev'], $item['prev_thumb']) : '', '</td>
				<td width="20%" class="windowbg2">', show_prevnext(0, $item['current_thumb']), '</td>
				<td width="20%">', (int) $item['next'] > 0 ? show_prevnext($item['next'], $item['next_thumb']) : '', '</td>
				<td width="20%">', (int) $item['next2'] > 0 ? show_prevnext($item['next2'], $item['next2_thumb']) : '', '</td>
				<td rowspan="2">', (int) $item['next_page'] > 0 ? '<a href="'.$galurl.'sa=item;in='.$item['next_page'].'">&raquo;</a>' : '&raquo;', '</td>
			</tr>
			<tr class="smalltext">
				<td>', (int) $item['prev2'] > 0 ? '<a href="'.$galurl.'sa=item;in='.$item['prev2'].'">'.$item['prev2_title'].'</a>' : '', '</td>
				<td>', (int) $item['prev'] > 0 ? '<a href="'.$galurl.'sa=item;in='.$item['prev'].'">'.$item['prev_title'].'</a>' : '', '</td>
				<td class="windowbg2">'.$item['current_title'].'</td>
				<td>', (int) $item['next'] > 0 ? '<a href="'.$galurl.'sa=item;in='.$item['next'].'">'.$item['next_title'].'</a>' : '', '</td>
				<td>', (int) $item['next2'] > 0 ? '<a href="'.$galurl.'sa=item;in='.$item['next2'].'">'.$item['next2_title'].'</a>' : '', '</td>
			</tr>
		</table>';
	elseif ($amSettings['prev_next'] == 2) // Text links
		echo '
		<div class="mg_prev">', (int) $item['prev'] > 0 ? '&laquo; <a href="'.$galurl.'sa=item;in='.$item['prev'].'">'.$txt['media_prev'].'</a>' : '&laquo; '.$txt['media_prev'], '</div>
		<div class="mg_next">', (int) $item['next'] > 0 ? '<a href="'.$galurl.'sa=item;in='.$item['next'].'">'.$txt['media_next'].'</a> &raquo;' : $txt['media_next'].' &raquo;', '</div>';
	else // Browsing disabled
		echo '
		<br />';

	echo '
		<div class="tborder">
			<div class="titlebg info mg_title"><strong>', $item['title'], '</strong></div>
		</div>';

	$desc_len = strlen($item['description']);
	echo '
		<div id="itembox">', $item['embed_object'], $item['is_resized'] ? '
			<div class="mg_subtext" style="padding-top: 6px">' . $txt['media_resized'] . '</div>' : '', '
		</div>', !empty($item['description']) ? '
		<div class="mg_item_desc" style="margin: auto; text-align: ' . ($desc_len > 200 ? 'justify' : 'center') . '; width: ' . ($desc_len > 800 ? '90%' : max($item['preview_width'], 400) . 'px') . '">' . $item['description'] . '</div>
		<br />' : '', '

		<div class="clear"></div>';

	if ($context['aeva_size_mismatch'])
		echo '
		<div class="unapproved_yet">', $txt['media_size_mismatch'], '</div>';

	if (!$item['approved'] && ($item['member']['id'] == $user_info['id']) && !allowedTo('media_moderate') && !allowedTo('media_auto_approve_item'))
		echo '
		<div class="unapproved_yet">', $txt['media_will_be_approved'], '</div>';

	if (!$item['approved'] && (allowedTo('media_moderate') || allowedTo('media_auto_approve_item')))
		echo '
		<div class="unapproved_yet">', $txt['media_approve_this'], '</div>';

	echo '

		<table cellspacing="0" cellpadding="4" border="0" width="100%">
		<tr class="titlebg">
			<td width="25%" style="padding: 7px 0 5px 8px; border-radius: 8px 0 0 0"><h3>', $txt['media_poster_info'], '</h3></td>
			<td style="padding: 7px 0 5px 8px; border-radius: 0 8px 0 0"><h3>', $txt['media_item_info'], '</h3></td>
		</tr><tr>
		<td class="windowbg top">', empty($item['member']['id']) ? '
			<h4>' . $txt['guest'] . '</h4>' : '
			<h4>' . $item['member']['link'] . '</h4>
			<ul class="smalltext info_list">' . (!empty($item['member']['group']) ? '
				<li>' . $item['member']['group'] . '</li>' : '') . (!empty($item['member']['avatar']['image']) ? '
				<li>' . $item['member']['avatar']['image'] . '</li>' : '') . '
				<li>' . $txt['media_total_items'] . ': ' . $item['member']['aeva']['total_items'] . '</li>
				<li>' . $txt['media_total_comments'] . ': ' . $item['member']['aeva']['total_comments'] . '</li>', '
			</ul>
		</td>
		<td class="windowbg2 top"', $amSettings['show_extra_info'] == 1 ? ' rowspan="2"' : '', '>
			<table class="w100 cp4 cs0">
			<tr><td class="info smalltext">', $txt['media_posted_on'], '</td><td class="info">', timeformat($item['time_added']), '</td></tr>';

	if ($item['type'] != 'embed')
		echo !empty($item['width']) && !empty($item['height']) ? '
			<tr><td class="info smalltext">' . $txt['media_width'] . '&nbsp;&times;&nbsp;' . $txt['media_height'] . '</td><td class="info">' . $item['width'] . '&nbsp;&times;&nbsp;' . $item['height'] . '</td></tr>' : '', '
			<tr><td class="info smalltext">', $txt['media_filesize'], '</td><td class="info">', $item['filesize'], '</td></tr>
			<tr><td class="info smalltext">', $txt['media_filename'], '</td><td class="info">', $item['filename'], '</td></tr>';

	if ((!empty($item['keyword_list']) && implode('', $item['keyword_list']) != '') || !empty($item['keywords']))
	{
		echo '
			<tr><td class="info smalltext">', $txt['media_keywords'], '</td><td class="info">';
		$tag_list = '';
		if (!empty($item['keyword_list']))
		{
			foreach ($item['keyword_list'] as $tag)
				if (!empty($tag))
					$tag_list .= '<b><a href="' . $galurl . 'sa=search;search=' . urlencode($tag) . ';sch_kw">' . $tag . '</a></b>, ';
		}
		else
			echo $item['keywords'];
		echo substr($tag_list, 0, strlen($tag_list) - 2) . '</td></tr>';
	}

	echo '
			<tr><td class="info smalltext">', $txt['media_views'], '</td><td class="info">', $item['views'], '</td></tr>', !empty($item['downloads']) ? '
			<tr><td class="info smalltext">' . $txt['media_downloads'] . '</td><td class="info">' . $item['downloads'] . '</td></tr>' : '', '
			<tr><td class="info smalltext">', $txt['media_rating'], '</td><td class="info" id="ratingElement">', template_aeva_rating_object($item), '</td></tr>',
			!empty($item['num_comments']) ? '
			<tr><td class="info smalltext">' . $txt['media_comments'] . '</td><td class="info">' . $item['num_comments'] . '</td></tr>' : '', !empty($item['last_edited']) ? '
			<tr><td class="info smalltext">' . $txt['media_last_edited'] . '</td><td class="info">' . $item['last_edited'] . ($item['last_edited_by'] !== -2 ? ' ' . $txt['media_by'] . ' ' . $item['last_edited_by'] : '') . '</td></tr>' : '';

	foreach ($item['custom_fields'] as $field)
	{
		if (!empty($field['value']))
		{
			echo '
			<tr>
				<td class="info smalltext">', $field['name'], '</td>
				<td class="info">';
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
			echo '</td>
			</tr>';
		}
	}

	if ($amSettings['show_linking_code'])
	{
		echo '
			<tr>
				<td class="info smalltext">', $txt['media_embed_bbc'], '</td>
				<td class="info">
					<input id="bbc_embed" type="text" size="56" value="[smg id=' . $item['id_media'] . ($item['type'] == 'image' ? '' : ' type=av') . ']" onclick="return selectText(this);" readonly="readonly" />
					<a href="', $scripturl, '?action=help;in=mediatag" onclick="return reqWin(this);" class="help"></a>
				</td>
			</tr>';

		// Don't show html/direct links if the helper file was deleted
		if ($amSettings['show_linking_code'] == 1)
		{
			if (strpos($item['embed_object'], 'swfobject.embedSWF') === false)
				echo '
			<tr>
				<td class="info smalltext">', $txt['media_embed_html'], '</td><td class="info">
					<input id="html_embed" type="text" size="60" value="', $item['type'] == 'image' ?
						westr::htmlspecialchars('<img src="' . $boardurl . '/MGalleryItem.php?id=' . $item['id_media'] . '" />') :
						westr::htmlspecialchars(trim(preg_replace('/[\t\r\n]+/', ' ', $item['embed_object']))), '" onclick="return selectText(this);" readonly="readonly" />
				</td>
			</tr>';
			if ($item['type'] != 'embed')
				echo '
			<tr>
				<td class="info smalltext">' . $txt['media_direct_link'] . '</td>
				<td class="info">
					<input id="direct_link" type="text" size="60" value="' . $boardurl . '/MGalleryItem.php?id=' . $item['id_media'] . '" onclick="return selectText(this);" readonly="readonly" />
				</td>
			</tr>';
		}
	}
	echo '
			</table>
		</td></tr>';

	if ($amSettings['show_extra_info'] == 1)
	{
		echo '
		<tr><td class="windowbg bottom">
			<div class="titlebg" style="margin: 0 -4px 2px -4px; padding: 7px 0 5px 8px"><h3>', $txt['media_extra_info'], '</h3></div>';

		if (empty($item['extra_info']))
			echo '
			<div class="info">', $txt['media_exif_not_available'], '</div>';
		else
		{
			echo $amSettings['use_lightbox'] ? '
			<div class="info"><img src="' . $settings['images_aeva'] . '/magnifier.png" class="vam" /> <a href="#" onclick="return hs.htmlExpand(this);">'
			. $txt['media_exif_entries'] . '</a> (' . count($item['extra_info']) . ')
			<div class="highslide-maincontent">
				<div class="ae_header" style="margin-bottom: 8px"><we:title>' . $txt['media_extra_info'] . '</we:title></div>' : '', '
				<div class="smalltext exif">';

			foreach ($item['extra_info'] as $info => $data)
				if (!empty($data))
					echo '
					<div class="info"><b>', $txt['media_exif_'.$info], '</b>: ', $data, '</div>';
			echo '
				</div>
			</div>', $amSettings['use_lightbox'] ? '</div>' : '';
		}
		echo '
		</td></tr>';
	}

	echo '
		<tr class="titlebg"><td colspan="2" class="center info images" style="line-height: 16px; vertical-align: text-bottom; border-radius: 0 0 8px 8px">', $item['can_report'] ? '
			<a href="'.$galurl.'sa=report;type=item;in='.$item['id_media'].'"' . ($amSettings['use_lightbox'] ? ' onclick="return hs.htmlExpand(this);"' : '') . '><img src="'.$settings['images_aeva'].'/report.png" />&nbsp;' . $txt['media_report_this_item'] . '</a>' : '';

	if ($item['can_report'] && $amSettings['use_lightbox'])
		echo '
			<div class="highslide-maincontent">
				<form action="'.$galurl.'sa=report;type=item;in='.$item['id_media'].'" method="post">
					<h3>'.$txt['media_reporting_this_item'].'</h3>
					<hr />'.$txt['media_reason'].'<br />
					<textarea cols="" rows="8" style="width: 98%" name="reason"></textarea>
					<p class="mgra"><input type="submit" value="'.$txt['media_submit'].'" class="aeva_ok" name="submit_aeva" /> <input type="button" onclick="return hs.close(this);" value="' . $txt['media_close'] . '" class="aeva_cancel"></p>
				</form>
			</div>';

	if ($item['can_edit'])
		echo '
			<a href="', $galurl, 'sa=post;in=', $item['id_media'], '"><img src="', $settings['images_aeva'], '/camera_edit.png" />&nbsp;', $txt['media_edit_this_item'], '</a>
			<a href="', $galurl, 'sa=delete;in=', $item['id_media'], '"', $amSettings['use_lightbox'] ? ' onclick="return hs.htmlExpand(this);"' : ' onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ');"', '><img src="', $settings['images_aeva'], '/delete.png" />&nbsp;', $txt['media_delete_this_item'], '</a>';

	if ($item['can_edit'] && $amSettings['use_lightbox'])
		echo '
			<div class="highslide-maincontent">
				<form action="'.$galurl.'sa=delete;in='.$item['id_media'].'" method="post">
					<h3>'.$txt['media_delete_this_item'].'</h3>
					<hr />'.$txt['quickmod_confirm'].'
					<p class="mgra"><input type="submit" value="' . $txt['media_yes'] . '" class="aeva_ok" /> <input type="button" onclick="return hs.close(this);" value="' . $txt['media_no'] . '" class="aeva_cancel"></p>
				</form>
			</div>';

	echo
		allowedTo('media_download_item') && $item['type'] != 'embed' ? '
			<a href="'.$galurl.'sa=media;in='.$item['id_media'].';dl"><img src="'.$settings['images_aeva'].'/download.png" />&nbsp;' . $txt['media_download_this_item'] . '</a>' : '';

	if ($item['can_edit'] && !empty($context['aeva_move_albums']))
		echo '
			<a href="'.$galurl.'sa=move;in='.$item['id_media'].'" '.($amSettings['use_lightbox'] ? 'onclick="return hs.htmlExpand(this);"' : '').'><img src="'.$settings['images_aeva'].'/arrow_out.png" />&nbsp;' . $txt['media_move_item'] . '</a>';

	if ($item['can_edit'] && $amSettings['use_lightbox'])
	{
		echo '
			<div class="highslide-maincontent">
				<h3>', $txt['media_moving_this_item'], '</h3>
				<h2>', $txt['media_album'], ': ', $item['album_name'], '</h2>
				<hr />
				<form action="', $galurl, 'sa=move;in=', $item['id_media'], '" method="post">
					', $txt['media_album_to_move'], ': <select name="album">';

			foreach ($context['aeva_move_albums'] as $album => $name)
			{
				if ($name[2] === '')
					echo '</optgroup>';
				elseif ($name[2] == 'begin')
					echo '<optgroup label="', $name[0], '">';
				else
					echo '<option value="', $album, '"', $name[1] ? ' disabled' : '', '>', $name[0], '</option>';
			}

			echo '
					</select>
					<p class="mgra"><input type="submit" value="', $txt['media_submit'], '" class="aeva_ok" name="submit_aeva" /> <input type="button" onclick="return hs.close(this);" value="', $txt['media_close'], '" class="aeva_cancel"></p>
				</form>
			</div>';
	}

	$un = $item['approved'] ? 'un' : '';
	if ($item['can_approve'])
		echo '
			<a href="', $galurl, 'sa=', $un, 'approve;in=', $item['id_media'], '"><img src="', $settings['images_aeva'], '/', $un, 'tick.png" />&nbsp;', $txt['media_admin_' . $un . 'approve'], '</a>';

	echo '
		</td></tr>
		</table>';

	if (isset($item['playlists'], $item['playlists']['current']))
		aeva_foxy_show_playlists($item['id_media'], $item['playlists']);

	// Comments!
	echo '
		<table class="cs1 cp4 w100" id="mg_coms">
		<tr class="titlebg middle"><td colspan="4" style="padding: 0 6px">
			<span class="comment_header">', empty($amSettings['disable_rss']) ? '
				<a href="'.$galurl.'sa=rss;item='.$item['id_media'].';type=comments" style="text-decoration: none"><img src="'.$settings['images_aeva'].'/rss.png" alt="RSS" />&nbsp;' . $txt['media_comments'] . '</a>' : '
				' . $txt['media_comments'], '
			</span>
			<span class="smalltext comment_sort_options">
				', empty($item['comments']) ? $txt['media_no_comments'] : $txt['media_sort_order_com'] . ' -
				<a href="' . $galurl . 'sa=item;in=' . $item['id_media'] . (isset($_REQUEST['start']) ? ';start=' . $_REQUEST['start'] : '') . '">' . $txt['media_sort_order_asc'] . '</a>
				<a href="' . $galurl . 'sa=item;in=' . $item['id_media'] . ';com_desc' . (isset($_REQUEST['start']) ? ';start=' . $_REQUEST['start'] : '') . '">' . $txt['media_sort_order_desc'] . '</a>', '
			</span>
		</td></tr>
		</table>', empty($item['comments']) ? '' : '
		<div class="pagelinks">' . $txt['media_pages'] . ': ' . $item['com_page_index'] . '</div>';

	$alternative = false;
	foreach ($item['comments'] as $c)
	{
		$alternative = !$alternative;
		echo '
		<div class="windowbg', $alternative ? '' : '2', ' wrc">
			<table class="w100 cp0 cs0 tlf" style="padding: 0 10px"><tr>
			<td width="20%"', $c['is_edited'] ? ' rowspan="2"' : '', ' class="top">', empty($c['member']['id']) ? '
				<h4>' . $txt['guest'] . '</h4>' : '
				<h4>' . $c['member_link'] . '</h4>
				<ul class="smalltext info_list">' . (!empty($c['member']['group']) ? '
					<li>' . $c['member']['group'] . '</li>' : '') . (!empty($c['member']['avatar']['image']) ? '
					<li>' . $c['member']['avatar']['image'] . '</li>' : '') . '
					<li>' . $txt['media_total_items'] . ': ' . $c['member']['aeva']['total_items'] . '</li>
					<li>' . $txt['media_total_comments'] . ': ' . $c['member']['aeva']['total_comments'] . '</li>
				</ul>', '
			</td>
			<td class="top', $c['approved'] ? '' : ' unapp', '">
				<a name="com', $c['id_comment'], '"></a>
				<div class="mgc_main">
					', $txt['media_comment'], ' <a href="#com', $c['id_comment'], '" rel="nofollow">#', $c['counter'], '</a> - ',
				is_numeric($c['posted_on'][0]) ? $txt['media_posted_on_date'] : $txt['media_posted_on'], ' ', $c['posted_on'], '
				</div>';

		if ($c['can_edit'] || $c['can_report'])
			echo '
				<div class="mgc_icons">', $c['can_edit'] ? '
					<a href="'.$galurl.'sa=edit;type=comment;in='.$c['id_comment'].'"><img src="'.$settings['images_aeva'].'/comment_edit.png" /> '.$txt['media_edit_this_item'].'</a>' : '', $c['can_delete'] ? '
					<a href="'.$galurl.'sa=delete;type=comment;in='.$c['id_comment'].'" onclick="return confirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ');"><img src="'.$settings['images_aeva'].'/delete.png" /> '.$txt['media_delete_this_item'].'</a> ' : '', $c['can_report'] ? '
					<a href="'.$galurl.'sa=report;type=comment;in='.$c['id_comment'].'"><img src="'.$settings['images_aeva'].'/report.png" /> '.$txt['media_report_this_item'].'</a>' : '', !$c['approved'] && $c['can_delete'] ? '
					<a href="'.$scripturl.'?action=media;area=moderate;sa=submissions;do=approve;in='.$c['id_comment'].';type=coms;' . $context['session_var'] . '='.$context['session_id'].'"><img src="'.$settings['images_aeva'].'/tick.png" title="'.$txt['media_admin_approve'].'" /> '.$txt['media_admin_approve'].'</a>' : '', '
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
	if (!empty($item['comments']))
		echo '
		<div class="pagelinks">' . $txt['media_pages'] . ': ' . $item['com_page_index'] . '</div>';

	if (allowedTo('media_comment'))
		echo '
		<div id="quickreplybox" style="padding-top: 4px">
			<we:cat>
				<span class="ie6_header floatleft"><a href="#" onclick="return aevaSwap(\'', $settings['images_url'], '\');">
					<img src="', $settings['images_url'], '/expand.gif" alt="+" id="quickReplyExpand" class="icon" />
				</a>
				<a href="#" onclick="return aevaSwap(\'', $settings['images_url'], '\');">', $txt['media_comment_this_item'], '</a>
				</span>
			</we:cat>
			<div id="quickReplyOptions" style="display: none">
				<span class="upperframe"><span></span></span>
				<div class="roundframe">
					<form action="'.$galurl.'sa=comment;in='.$item['id_media'].'" method="post">
						<div>
							<h3>'.$txt['media_commenting_this_item'].'</h3>
							<img src="'.$settings['images_aeva'].'/comment.png" class="middle" /> <a href="' . $galurl . 'sa=comment;in=' . $item['id_media'] . '">' . $txt['media_switch_fulledit'] . '</a>
						</div>
						<textarea name="comment" cols="" rows="8" style="width: 95%"></textarea>
						<p class="mgra"><input type="submit" value="'.$txt['media_submit'].'" name="submit_aeva" class="aeva_ok" /> <input type="button" onclick="return hs.close(this);" value="' . $txt['media_close'] . '" class="aeva_cancel"></p>
					</form>
				</div>
				<span class="lowerframe"><span></span></span>
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
	global $amSettings, $context, $txt, $galurl;
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
					<input type="hidden" name="' . $e['fieldname'] . '" value="' . $e['value'] . '" />';
				continue;
			}

			if ($e['type'] == 'hr')
			{
				echo '
			<tr><td colspan="2" style="padding: 0"></td></tr>
			<tr><td colspan="2" style="padding: 1px 0 0 0; border-top: 1px dotted #aaa"></td></tr>';
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
				<td width="' . (isset($context['post_box_name']) ? ($context['post_box_name'] == 'desc' ? '35' : '10') : '50') . '%" class="windowbg' . $alt .
				($valign ? ' top' : '') . '"' . (!empty($e['colspan']) && ($colspan = $e['colspan']) ? ' rowspan="2"' : '') . '>' . $e['label'] . (!empty($e['subtext']) ? '<div class="mg_subtext">' . $e['subtext'] . '</div>' : '') . '</td>', '
				<td class="windowbg' . $alt . '"' . (!empty($e['skip_left']) ? ' colspan="2"' : '') . '>';

			if ($e['type'] != 'title')
				switch($e['type'])
				{
					case 'text';
						echo '<input type="text"', isset($e['value']) ? ' value="'.$e['value'].'"' : '', ' name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '" size="', !empty($e['size']) ? $e['size'] : 50, '"', isset($e['custom']) ? ' ' . $e['custom'] : '', ' />';
					break;
					case 'textbox';
						if (isset($context['post_box_name']) && $context['post_box_name'] == $e['fieldname'])
						{
							echo '<div id="bbcBox_message"></div><div id="smileyBox_message"></div>';
							template_aeva_text_editor();
						}
						else
							echo '<textarea name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '"', isset($e['custom']) ? ' ' . $e['custom'] : '', '>', isset($e['value']) ? $e['value'] : '', '</textarea>';
					break;
					case 'file';
						echo '<input type="file" name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '" size="51"', isset($e['custom']) ? ' ' . $e['custom'] : '', ' />', isset($e['add_text']) ? ' ' . $e['add_text'] : '';
					break;
					case 'hidden';
						echo '<input type="hidden" name="', $e['fieldname'], '" value="', $e['value'], '"', isset($e['custom']) ? ' ' . $e['custom'] : '', ' />';
					break;
					case 'small_text';
						echo '<input type="text"', isset($e['value']) ? ' value="'.$e['value'].'"' : '', ' name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '" size="10"', isset($e['custom']) ? ' ' . $e['custom'] : '', ' />';
					break;
					case 'select';
						echo '<select name="', $e['fieldname'], isset($e['multi']) && $e['multi'] === true ? '[]' : '', '" tabindex="', $context['tabindex']++, '"',
							isset($e['multi']) && $e['multi'] === true ? ' multiple="multiple"' . (!empty($e['size']) ? ' size="' . $e['size'] . '"' : '') : '',
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
							echo '<div><input id="radio', $chk, '" name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '" value="', $value, '"', is_array($name) && isset($name[1]) && $name[1] === true ? ' checked' : '', ' type="radio"', isset($e['custom']) ? ' ' . $e['custom'] : '', ' /><label for="radio', $chk++, '"> ', is_array($name) ? $name[0] : $name, '</label></div>';
					break;
					case 'yesno';
						echo '<div><select name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '"><option value="1"', !empty($e['value']) ? ' selected' : '', ' style="color: green">', $txt['media_yes'], '</option><option value="0"', empty($e['value']) ? ' selected' : '', ' style="color: red">', $txt['media_no'], '</option></select></div>';
					break;
					case 'passwd';
						echo '<input name="', $e['fieldname'], '" tabindex="', $context['tabindex']++, '" value="', isset($e['value']) ? $e['value'] : '', '" type="password"', isset($e['custom']) ? ' ' . $e['custom'] : '', ' />';
					break;
					case 'checkbox';
					case 'checkbox_line';
						foreach ($e['options'] as $opt => $label)
						{
							if (!is_array($label) && $label == 'sep')
								echo '<hr />';
							else
								echo '<div', $e['type'] == 'checkbox_line' ? ' class="aeva_ich' . (!empty($e['skip_left']) ? '2' : '') . '"' : '', '><input type="checkbox" id="chk', $chk, '" name="', is_array($label) ?
									(!isset($label['force_name']) ? $e['fieldname'] . (isset($e['multi']) && $e['multi'] === true ? '[]' : '') : $label['force_name']) : $label,
									'" tabindex="', $context['tabindex']++, '" value="', $opt, '"', is_array($label) && isset($label[1]) && $label[1] === true ? ' checked' : '', is_array($label) && !empty($label['disabled']) ? ' disabled' : '',
									is_array($label) && isset($label['custom']) ? ' ' . $label['custom'] : '', ' /><label for="chk', $chk++, '">&nbsp;&nbsp;', is_array($label) ? $label[0] : $label, '</label></div>';
						}
					break;
					case 'checkbox_dual'; // This one is for album creation only... ;)
						echo '<table cellpadding="4" cellspacing="0" border="0" width="100%"><thead><tr><th>', $txt['media_access_read'], '</th><th>', $txt['media_access_write'], '</th><th></th></tr></thead>';
						foreach ($e['options'] as $opt => $label)
						{
							echo '<tr>';
							if (!is_array($label))
								echo '<td colspan="3" style="padding: 1px 0 0 0; border-top: 1px dotted #aaa"></td>';
							else
							{
								for ($i = 0; $i < 2; $i++)
									echo '<td width="15" class="center"><input type="checkbox" id="chk', $chk++, '" name="',
										$opt === 'check_all' ? 'check_all_' . ($i+1) : $e['fieldname'][$i] . '[]',
										'" tabindex="', $context['tabindex']++, '" value="', $opt, '"',
										isset($label[$i+1]) && $label[$i+1] === true ? ' checked' : '',
										$opt == -1 && $i == 1 ? ' disabled' : '',
										isset($label['custom']) ? ' ' . str_replace('$1', $e['fieldname'][$i], $label['custom']) : '', ' /></td>';
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
				<td class="windowbg right', empty($alt) ? '2' : '', '" colspan="2">', $show_at_end, !empty($context['aeva_form']['silent']) ? '
					<input type="submit" value="' . $txt['media_silent_update'] .  '" name="silent_update" tabindex="' . $context['tabindex']++ . '">' : '', '
					<input type="submit" value="', $txt['media_submit'], '" name="submit_aeva" tabindex="', $context['tabindex']++, '" class="submit" />
				</td>
			</tr>
		</table>
	</form>';

	if (!empty($context['aeva_extra_data']))
		echo $context['aeva_extra_data'];
}

function template_aeva_viewAlbum()
{
	global $context, $txt, $galurl, $scripturl, $amSettings, $settings, $user_info;

	$album_data = &$context['album_data'];

	// Show some album data
	echo '
	<div id="albums">
		<table cellpadding="6" cellspacing="0" border="0" width="100%" class="windowbg2">
		<tr><td class="windowbg top" style="width: 2%" rowspan="2">';
	$trans = $album_data['bigicon_transparent'] ? '; ' . ($context['browser']['is_webkit'] ? '-webkit-' : ($context['browser']['is_firefox'] ? '-moz-' : '')) . 'box-shadow: none' : '';
	// If the big icon is too... big, then we can't round its corners. Ah well.
	if ($album_data['bigicon_resized'])
		echo '<img src="', $galurl, 'sa=media;in=', $album_data['id'], ';bigicon" width="', $album_data['bigicon'][0], '" height="', $album_data['bigicon'][1], '" />';
	else
		echo '<div class="aea" style="width: ', $album_data['bigicon'][0], 'px; height: ', $album_data['bigicon'][1], 'px; background: url(', $galurl, 'sa=media;in=', $album_data['id'], ';bigicon) 0 0', $trans, '">&nbsp;</div>';
	echo '</td>
		<td style="padding: 12px 12px 6px 12px">
			<div class="mg_large mg_pb4">', !empty($album_data['passwd']) ? aeva_lockedAlbum($album_data['passwd'], $album_data['id'], $album_data['owner']) : '',
			$album_data['name'], empty($amSettings['disable_rss']) ? '&nbsp;&nbsp;&nbsp;<span class="title_rss">
			<a href="' . $galurl . 'sa=rss;album=' . $album_data['id'] . '"><img src="' . $settings['images_aeva'] . '/rss.png" alt="RSS" class="aeva_vera" /> ' . $txt['media_items'] . '</a>
			<a href="' . $galurl . 'sa=rss;album=' . $album_data['id'] . ';type=comments"><img src="' . $settings['images_aeva'] . '/rss.png" alt="RSS" class="aeva_vera" /> ' . $txt['media_comments'] . '</a></span>' : '', '</div>
			<div>', $album_data['type2'], !empty($album_data['owner']['id']) ? '
			- ' . $txt['media_owner'] . ': ' . aeva_profile($album_data['owner']['id'], $album_data['owner']['name']) : '', '
			- ', $album_data['num_items'] == 0 ? $txt['media_no_items'] : $album_data['num_items'] . ' ' . $txt['media_lower_item' . ($album_data['num_items'] == 1 ? '' : 's')], !empty($album_data['last_item']) ? ' - ' . $txt['media_latest_item'] . ': <a href="'.$galurl.'sa=item;in='.$album_data['last_item'].'">'.$album_data['last_item_title'].'</a> (' . $album_data['last_item_date'] . ')' : '', '</div>', !empty($album_data['description']) ? '
			<div class="mg_desc">' . $album_data['description'] . '</div>' : '', $album_data['hidden'] ? '
			<div class="mg_hidden">' . $txt['media_album_is_hidden'] . '</div>' : '', '
		</td></tr>
		<tr><td class="bottom">';

	$can_moderate_here = allowedTo('media_moderate') || (!$user_info['is_guest'] && $user_info['id'] == $album_data['owner']['id']);
	$can_approve_here = $can_moderate_here || allowedTo('media_auto_approve_item');
	$can_add_playlist = !empty($context['aeva_my_playlists']);
	if ($can_edit_items = ($can_moderate_here || $context['aeva_can_add_item'] || $context['aeva_can_multi_upload']) || allowedTo('media_multi_download') || allowedTo('media_access_unseen'))
	{
		echo '
			<div class="buttonlist data">
				<ul>';

		if ($context['aeva_can_add_item'])
			echo '
					<li><a href="', $galurl, 'sa=post;album=', $album_data['id'], '"><span><img src="', $settings['images_aeva'], '/camera_add.png" /> ', $txt['media_add_item'], '</span></a></li>';

		if ($context['aeva_can_multi_upload'])
			echo '
					<li><a href="', $galurl, 'sa=mass;album=', $album_data['id'], '"><span><img src="', $settings['images_aeva'], '/camera_mass.png" /> ', $txt['media_multi_upload'], '</span></a></li>';

		if ($can_moderate_here)
		{
			echo '
					<li><a href="', $galurl, 'area=mya;sa=edit;in=', $album_data['id'], '"><span><img src="', $settings['images_aeva'], '/folder_edit.png" title="', $txt['media_admin_edit'], '" /> ', $txt['media_admin_edit'], '</span></a></li>';
			if ($user_info['is_admin'])
				echo '
					<li><a href="', $scripturl, '?action=admin;area=aeva_maintenance;sa=index;album=', $album_data['id'], ';', $context['session_var'], '=', $context['session_id'], '"><span><img src="', $settings['images_aeva'], '/maintain.gif" title="', $txt['media_admin_labels_maintenance'], '" /> ', $txt['media_admin_labels_maintenance'], '</span></a></li>';
			if (allowedTo('media_moderate') && $album_data['approved'] == 0)
				echo '
					<li><a href="', $scripturl, '?action=media;area=moderate;sa=submissions;do=approve;type=albums;in=', $album_data['id'], ';', $context['session_var'], '=', $context['session_id'], '"><span><img src="', $settings['images_aeva'], '/tick.png" title="', $txt['media_admin_approve'], '" /> ', $txt['media_admin_approve'], '</span></a></li>';
		}

		if (allowedTo('media_multi_download'))
			echo '
					<li><a href="', $galurl, 'sa=massdown;album=', $album_data['id'], '"><span><img src="', $settings['images_aeva'], '/download.png" title="', $txt['media_multi_download'], '" /> ', $txt['media_multi_download'], '</span></a></li>';

		if (allowedTo('media_access_unseen'))
			echo '
					<li><a href="', $galurl, 'sa=album;in=', $album_data['id'], ';markseen;', $context['session_var'], '=', $context['session_id'], '"><span><img src="', $settings['images_aeva'], '/eye.png" title="', $txt['media_mark_album_as_seen'], '" /> ', $txt['media_mark_album_as_seen'], '</span></a></li>';

		echo '
				</ul>
			</div>';
	}
	$can_edit_items |= $can_add_playlist;

	echo '
		</td></tr>
		</table>';

	// Show their Sub-Albums
	if (!empty($context['aeva_sub_albums']))
	{
		echo '
		<div class="titlebg" style="padding: 4px">', $txt['media_sub_albums'], empty($amSettings['disable_rss']) ? '&nbsp;&nbsp;&nbsp;<span class="title_rss">
			<a href="' . $galurl . 'sa=rss;album=' . $album_data['id'] . ';children"><img src="' . $settings['images_aeva'] . '/rss.png" alt="RSS" class="aeva_vera" /> ' . $txt['media_items'] . '</a>
			<a href="' . $galurl . 'sa=rss;album=' . $album_data['id'] . ';children;type=comments"><img src="' . $settings['images_aeva'] . '/rss.png" alt="RSS" class="aeva_vera" /> ' . $txt['media_comments'] . '</a></span>' : '', '</div>';
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

	// Page index and sorting things
	$sort_list = array('m.id_media' => 0, 'm.time_added' => 1, 'm.title' => 2, 'm.views' => 3, 'm.weighted' => 4);
	echo ($can_edit_items ? '
	<form action="' . $scripturl . '?action=media;sa=quickmod;in=' . $album_data['id'] . '" method="post" enctype="multipart/form-data" id="aeva_form" name="aeva_form" onsubmit="submitonce(this);">' : '') . '
		<div class="titlebg sort_options">
			<div class="view_options">
				', $txt['media_items_view'], ': ', $album_data['view'] == 'normal' ? '<b>' . $txt['media_view_normal'] . '</b> <a href="' . $galurl . 'sa=album;in=' . $album_data['id'] . ';fw;'. $context['aeva_urlmore'] . '">' . $txt['media_view_filestack'] . '</a>' : '<a href="' . $galurl . 'sa=album;in=' . $album_data['id'] . ';nw;' . $context['aeva_urlmore'] . '">' . $txt['media_view_normal'] . '</a> <b>' . $txt['media_view_filestack'] . '</b>', '
			</div>
			', $txt['media_sort_by'], ':';
	$sort = empty($sort_list[$context['aeva_sort']]) ? 0 : $sort_list[$context['aeva_sort']];
	for ($i = 0; $i < 5; $i++)
		echo $sort == $i ? ' <b>' . $txt['media_sort_by_'.$i] . '</b>' :
			' <a href="'.$galurl.'sa=album;in='.$album_data['id'] . ';sort=' . $i . ($album_data['view'] == 'normal' ? ';nw' : ';fw') . '">' . $txt['media_sort_by_'.$i] . '</a>';
	echo '
			| ', $txt['media_sort_order'], ':',
		($context['aeva_asc'] ? ' <b>' . $txt['media_sort_order_asc'] . '</b>' : ' <a href="'.$galurl.'sa=album;in='.$album_data['id'].(isset($_REQUEST['sort']) ? ';sort='.$_REQUEST['sort'] : '').';asc;' . ($album_data['view'] == 'normal' ? 'nw' : 'fw') . '">' . $txt['media_sort_order_asc'] . '</a>'),
		(!$context['aeva_asc'] ? ' <b>' . $txt['media_sort_order_desc'] . '</b>' : ' <a href="'.$galurl.'sa=album;in='.$album_data['id'] . (isset($_REQUEST['sort']) ? ';sort=' . $_REQUEST['sort'] : '').';desc;' . ($album_data['view'] == 'normal' ? 'nw' : 'fw') . '">'.$txt['media_sort_order_desc'].'</a>'), '
		</div>
		<div class="pagelinks page_index">
			', $txt['media_pages'], ': ', $context['aeva_page_index'], '
		</div>',
		$album_data['view'] == 'normal' ? aeva_listItems($context['aeva_items'], true, '', 0, $can_edit_items) : aeva_listFiles($context['aeva_items'], $can_edit_items), '
		<div class="pagelinks page_index" style="margin-top: 8px">', $can_edit_items ? '
			<div class="aeva_quickmod_bar">
				<input type="checkbox" id="check_all" onclick="invertAll(this, this.form, \'mod_item[\');" /> <label for="check_all">' . $txt['check_all'] . '</label>&nbsp;
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
				<select name="aeva_playlist" id="aeva_my_playlists" style="display: none">';
			foreach ($context['aeva_my_playlists'] as $p)
				echo '
					<option value="' . $p['id'] . '">' . $p['name'] . '</option>';
			echo '
				</select>';
		}
		echo $can_edit_items ? '
				<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
				<input type="submit" value="' . $txt['media_submit'] . '" name="submit_aeva" tabindex="' . $context['tabindex']++ . '" class="remove" style="margin: 0; padding: 1px 3px" onclick="return aevaDelConfirm(' . JavaScriptEscape($txt['quickmod_confirm']) . ');" />
			</div>' : '', '
			', $txt['media_pages'], ': ', $context['aeva_page_index'], '
		</div>', $can_edit_items ? '
	</form>' : '', '
	</div>';
}

function template_aeva_unseen()
{
	global $context, $txt, $galurl, $scripturl;

	echo '
	<div class="pagelinks align_left page_index">
		', $txt['media_pages'], ': ', $context['aeva_page_index'], '
	</div>';

	if (!empty($context['aeva_items']))
	{
		echo '
	<div>';
		$mark_seen = array();
		if (strpos($context['aeva_page_index'], '<a') !== false)
			$mark_seen['pageseen'] = array('text' => 'aeva_page_seen', 'image' => 'markread.gif', 'lang' => true, 'url' => $galurl . 'sa=unseen;' . (isset($_GET['start']) ? 'start=' . $_GET['start'] . ';' : '') . 'pageseen=' . implode(',', array_keys($context['aeva_items'])) . ';' . $context['session_var'] . '=' . $context['session_id']);
		$mark_seen['markseen'] = array('text' => 'aeva_mark_as_seen', 'image' => 'markread.gif', 'lang' => true, 'url' => $galurl . 'sa=unseen;markseen;' . $context['session_var'] . '=' . $context['session_id']);
		template_button_strip($mark_seen, 'left');
		echo '
	</div>';
	}

	echo '
	<div id="unseen_items" style="clear: both">', !empty($context['aeva_items']) ? aeva_listItems($context['aeva_items']) : '<br /><div class="notice">' . $txt['media_no_listing'] . '</div>', '
	</div>
	<div class="pagelinks align_left page_index">
		', $txt['media_pages'], ': ', $context['aeva_page_index'], '
	</div>';
}

function template_aeva_search_searching()
{
	global $txt, $galurl, $scripturl, $context, $settings;

	echo '
	<br />
	<form action="', $galurl, 'sa=search" method="post">
		<div class="windowbg2 wrc center">
			', $txt['media_search_for'], ': <input type="text" name="search" size="50" />
		</div>
		<div class="windowbg wrc">
			<table border="0" width="100%" cellspacing="1" cellpadding="8">
			<tr>
				<td class="', !empty($context['custom_fields']) ? 'w50 top right ' : 'center" colspan="2"', '>
					<label for="seala1">', $txt['media_search_in_title'], '</label> <input type="checkbox" name="sch_title" checked id="seala1" /><br />
					<label for="seala2">', $txt['media_search_in_description'], '</label> <input type="checkbox" name="sch_desc" id="seala2" /><br />
					<label for="seala3">', $txt['media_search_in_kw'], '</label> <input type="checkbox" name="sch_kw" id="seala3" /><br />
					<label for="seala4">', $txt['media_search_in_album_name'], '</label> <input type="checkbox" name="sch_an" id="seala4" />';

	if (!empty($context['custom_fields']))
	{
		echo '
				</td>
				<td class="w50 top left">';

		$kl = 1;
		foreach ($context['custom_fields'] as $field)
			echo '
					<input type="checkbox" name="fields[]" value="', $field['id'], '" id="cusla', $kl, '" /> <label for="cusla', $kl++, '">', sprintf($txt['media_search_in_cf'], '<i>' . $field['name'] . '</i>'), '</label><br />';
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
					', $txt['media_search_by_mem'], '<div class="mg_subtext">', $txt['media_search_by_mem_sub'], '</div>
				</td>
				<td class="w50 left">
					<input name="sch_mem" id="sch_mem" type="text" size="25" />
					<a href="', $scripturl, '?action=media;action=findmember;input=sch_mem;' . $context['session_var'] . '=', $context['session_id'], '" onclick="return reqWin(this.href, 350, 400);"><img src="', $settings['images_url'], '/icons/assist.gif" class="aeva_vera" /></a>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="right"><input type="submit" name="submit_aeva" value="', $txt['media_submit'], '" /></td>
			</tr>
			</table>
		</div>
	</form>';
}

function template_aeva_search_results()
{
	global $context, $txt, $scripturl;

	echo '
	<div style="padding: 5px"><b>', $context['aeva_total_results'], '</b> ', $txt['media_search_results_for'], ' <b>', $context['aeva_searching_for'], '</b></div>';

	if (!empty($context['aeva_foxy_rendered_search']))
		echo $context['aeva_foxy_rendered_search'];
	else
		echo $context['aeva_page_index'], '
	<div id="search_items">',
	aeva_listItems($context['aeva_items']), '
	</div>', $context['aeva_page_index'], '
	<div class="clear"></div>';
}

function template_aeva_viewUserAlbums()
{
	global $txt, $context, $scripturl, $galurl, $amSettings, $settings;

	// The Albums!
	echo '
	<div id="aeva_toplinks">
		<we:cat>
			<img src="'.$settings['images_aeva'].'/house.png" style="vertical-align: -3px" /> <b><a href="'.$galurl.'">'.$txt['media_home'].'</a></b> -
			<img src="'.$settings['images_aeva'].'/album.png" style="vertical-align: -3px" /> <b>'.$txt['media_albums'].'</b>', empty($amSettings['disable_playlists']) ? ' -
			<img src="'.$settings['images_aeva'].'/playlist.png" style="vertical-align: -3px" /> <b><a href="'.$galurl.'sa=playlists">'.$txt['media_playlists'].'</a></b>' : '', '
		</we:cat>
	</div>';

	$colspan = (isset($amSettings['album_columns']) ? max(1, (int) $amSettings['album_columns']) : 1) * 2;
	echo '
	<div class="pagelinks" style="padding: 0">', $txt['media_pages'], ': ', $context['aeva_page_index'], '</div>';

	$can_rss = empty($amSettings['disable_rss']);
	foreach ($context['aeva_user_albums'] as $id => $album)
	{
		$first = current($album);
		echo '
	<div class="cat_heading">
		<we:title>
			', empty($first['owner']['id']) ? '' : $txt['media_owner'] . ': ' . aeva_profile($id, $first['owner']['name']), $can_rss ?
			' <a href="' . $galurl . 'sa=rss;user=' . $id . ';albums"><img src="' . $settings['images_aeva'] . '/rss.png" alt="RSS" class="aeva_vera" /></a>' : '', '
		</we:title>
	</div>';

		aeva_listChildren($album);
	}
	echo '
	<div class="pagelinks">', $txt['media_pages'], ': ', $context['aeva_page_index'], '</div>';
}

function template_aeva_album_cp()
{
	global $txt, $scripturl, $galurl, $context, $settings, $alburl, $user_info;

	echo '
		<table cellpadding="6" cellspacing="1" border="0" width="100%" class="bordercolor">
			<tr class="titlebg">
				<td width="2%">&nbsp;</td>
				<td width="15%">', $txt['media_owner'], '</td>
				<td width="55%">', $txt['media_name'], '</td>
				<td width="28%">', $txt['media_admin_moderation'], '</td>
			</tr>', !empty($context['aeva_my_albums']) ? '
			<tr class="windowbg2">
				<td colspan="4"><a href="javascript:admin_toggle_all();">' . $txt['media_toggle_all'] . '</a></td>
			</tr>' : '';

	if ($context['aeva_moving'] !== false)
		echo '
			<tr class="windowbg3">
				<td colspan="4">', $txt['media_admin_moving_album'], ': ', $context['aeva_my_albums'][$context['aeva_moving']]['name'], ' <a href="', rtrim($alburl, ';'), '">[', $txt['media_admin_cancel_moving'], ']</a></td>
			</tr>';

	$can_manage = allowedTo('media_manage');
	$can_moderate = allowedTo('media_moderate');
	foreach ($context['aeva_my_albums'] as $album)
	{
		echo '
			<tr class="windowbg', $album['featured'] ? '' : '2', '">
				<td><a href="javascript:admin_toggle('.$album['id'].', true);"><img src="', $settings['images_url'], '/expand.gif" id="toggle_img_', $album['id'], '" /></a></td>
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
				<img src="', $settings['images_aeva'], '/star.gif" title="', $txt['media_featured_album'], '" />';

		echo '
				<a href="', $galurl, 'sa=album;in=', $album['id'], '">', $album['name'], '</a>', $show_move ? '
				' . $album['move_links']['child_of'] : '', '
				</td>
				<td class="text_margins" style="white-space: nowrap">';

		if ($can_manage || $album['owner']['id'] == $user_info['id'])
			echo '
					<img src="', $settings['images_aeva'], '/folder_edit.png" title="', $txt['media_admin_edit'], '" />&nbsp;<a href="', $alburl, 'sa=edit;in=', $album['id'], '">'.$txt['media_admin_edit'].'</a>
					<img src="', $settings['images_aeva'], '/folder_delete.png" title="', $txt['media_admin_delete'], '" />&nbsp;<a href="', $alburl, 'sa=delete;in=', $album['id'], '" onclick="return confirm(', JavaScriptEscape($txt['media_admin_album_confirm']), ');">'.$txt['media_admin_delete'].'</a>
					<img src="', $settings['images_aeva'], '/arrow_inout.png" title="', $txt['media_admin_move'], '" />&nbsp;<a href="' . $alburl . 'move='.$album['id'] . '">' . $txt['media_admin_move'] . '</a>', $album['approved'] == 0 && $can_moderate ? '
					<img src="'.$settings['images_aeva'].'/tick.png" title="'.$txt['media_admin_approve'].'" />&nbsp;<a href="'.$scripturl.'?action=media;area=moderate;sa=submissions;do=approve;type=albums;in='.$album['id'].'">'.$txt['media_admin_approve'].'</a>' : '';

		echo '
				</td>
			</tr>
			<tr class="windowbg" style="display: none" id="tr_expand_', $album['id'], '">
				<td colspan="4">
					<img src="" id="img_', $album['id'], '" style="float: left; margin-right: 8px" />
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
	global $txt, $galurl, $context, $scripturl, $settings;

	$stats = $context['aeva_stats'];

	// Show the general stats
	echo '
		<table class="table_grid w100 cp4 cs1" style="margin-top: 10px">
			<tr class="titlebg center">
				<th colspan="2" style="border-radius: 8px 8px 0 0"><img src="', $settings['images_aeva'], '/chart_pie.png" class="vam" /> ', $txt['media_gen_stats'], '</td>
			</tr>
			<tr>
				<td width="50%" class="windowbg2">
					<table cellpadding="4" cellspacing="0" width="100%">
						<tr>
							<td><img src="', $settings['images_aeva'], '/images.png" class="vam" /> ', $txt['media_total_items'], '</td>
							<td class="right">', $stats['total_items'], '</td>
						</tr>
						<tr>
							<td><img src="', $settings['images_aeva'], '/comments.png" class="vam" /> ', $txt['media_total_comments'], '</td>
							<td class="right">', $stats['total_comments'], '</td>
						</tr>
						<tr>
							<td><img src="', $settings['images_aeva'], '/folder_image.png" class="vam" /> ', $txt['media_total_albums'], '</td>
							<td class="right">', $stats['total_albums'], '</td>
						</tr>
						<tr>
							<td><img src="', $settings['images_aeva'], '/group.png" class="vam" /> ', $txt['media_total_featured_albums'], '</td>
							<td class="right">', $stats['total_featured_albums'], '</td>
						</tr>
					</table>
				</td>
				<td class="windowbg2">
					<table cellpadding="4" cellspacing="0" width="100%">
						<tr>
							<td><img src="', $settings['images_aeva'], '/images.png" class="vam" /> ', $txt['media_avg_items'], '</td>
							<td class="right">', $stats['avg_items'], '</td>
						</tr>
						<tr>
							<td><img src="', $settings['images_aeva'], '/comments.png" class="vam" /> ', $txt['media_avg_comments'], '</td>
							<td class="right">', $stats['avg_comments'], '</td>
						</tr>
						<tr>
							<td><img src="', $settings['images_aeva'], '/user.png" class="vam" /> ', $txt['media_total_item_contributors'], '</td>
							<td class="right">', $stats['total_item_posters'], '</td>
						</tr>
						<tr>
							<td><img src="', $settings['images_aeva'], '/user_comment.png" class="vam" /> ', $txt['media_total_commentators'], '</td>
							<td class="right">', $stats['total_commentators'], '</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr class="titlebg center">
				<td class="w50"><img src="', $settings['images_aeva'], '/user.png" class="vam" /> ', $txt['media_top_uploaders'], '</td>
				<td><img src="', $settings['images_aeva'], '/user_comment.png" class="vam" /> ', $txt['media_top_commentators'], '</td>
			</tr>
			<tr>
				<td class="windowbg2 top">
					<table cellpadding="4" cellspacing="0" width="100%">';

	foreach ($stats['top_uploaders'] as $uploader)
		echo '
						<tr>
							<td class="left" style="width: 60%">', aeva_profile($uploader['id'], $uploader['name']), '</td>
							<td style="width: 20%"><div class="aeva_statsbar2" style="width: ', $uploader['percent'], 'px;"></div></td>
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
					<table cellpadding="4" cellspacing="0" width="100%">';

	foreach ($stats['top_commentators'] as $uploader)
		echo '
						<tr>
							<td class="left" style="width: 60%">', aeva_profile($uploader['id'], $uploader['name']), '</td>
							<td style="width: 20%"><div class="aeva_statsbar" style="width: ', $uploader['percent'], 'px;"></div></td>
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
				<td class="w50"><img src="', $settings['images_aeva'], '/folder_image.png" class="vam" /> ', $txt['media_top_albums_items'], '</td>
				<td><img src="', $settings['images_aeva'], '/folder_table.png" class="vam" /> ', $txt['media_top_albums_comments'], '</td>
			</tr>
			<tr>
				<td class="windowbg2 top">
					<table cellpadding="4" cellspacing="0" width="100%">';

	foreach ($stats['top_albums_items'] as $album)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=album;in=', $album['id'], '">', $album['name'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar" style="width: ', $album['percent'], 'px;"></div></td>
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
					<table cellpadding="4" cellspacing="0" width="100%">';

	foreach ($stats['top_albums_comments'] as $album)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=album;in=', $album['id'], '">', $album['name'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar2" style="width: ', $album['percent'], 'px;"></div></td>
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
				<td class="w50"><img src="', $settings['images_aeva'], '/images.png" class="vam" /> ', $txt['media_top_items_views'], '</td>
				<td><img src="', $settings['images_aeva'], '/comments.png" class="vam" /> ', $txt['media_top_items_comments'], '</td>
			</tr>
			<tr>
				<td class="windowbg2 top">
					<table cellpadding="4" cellspacing="0" width="100%">';

	foreach ($stats['top_items_views'] as $item)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=item;in=', $item['id'], '">', $item['title'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar2" style="width: ', $item['percent'], 'px;"></div></td>
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
					<table cellpadding="4" cellspacing="0" width="100%">';

	foreach ($stats['top_items_com'] as $item)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=item;in=', $item['id'], '">', $item['title'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar" style="width: ', $item['percent'], 'px;"></div></td>
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
				<td class="w50"><img src="', $settings['images_aeva'], '/chart_pie.png" class="vam" /> ', $txt['media_top_items_rating'], '</td>
				<td><img src="', $settings['images_aeva'], '/star.gif" class="vam" /> ', $txt['media_top_items_voters'], '</td>
			</tr>
			<tr>
				<td class="windowbg2 top">
					<table cellpadding="4" cellspacing="0" width="100%">';

	foreach ($stats['top_items_rating'] as $item)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=item;in=', $item['id'], '">', $item['title'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar" style="width: ', $item['percent'], 'px;"></div></td>
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
					<table cellpadding="4" cellspacing="0" width="100%">';

	foreach ($stats['top_items_voters'] as $item)
		echo '
						<tr>
							<td class="left" style="width: 60%"><a href="', $galurl, 'sa=item;in=', $item['id'], '">', $item['title'], '</a></td>
							<td style="width: 20%"><div class="aeva_statsbar2" style="width: ', $item['percent'], 'px;"></div></td>
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

function template_aeva_below()
{
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
	global $context, $txt, $galurl, $amSettings, $settings, $boardurl;

	echo '
	<table cellpadding="6" cellspacing="0" border="0" width="100%">
		<tr class="titlebg">
			<td>', $txt['media_multi_upload'], '</td>
		</tr>
		<tr class="windowbg">
			<td>
				<ul class="normallist">
					<li>', $txt['media_max_file_size'], ': ', $txt['media_image'], ' - ', $context['aeva_max_file_size']['image'], ' ', $txt['media_kb'], ', ', $txt['media_video'], ' - ', $context['aeva_max_file_size']['video'], ' ', $txt['media_kb'], ', ', $txt['media_audio'], ' - ', $context['aeva_max_file_size']['audio'], ' ', $txt['media_kb'], ', ', $txt['media_doc'], ' - ', $context['aeva_max_file_size']['doc'], ' ', $txt['media_kb'], '
					<li>', $txt['media_needs_js_flash'], '</li>
				</ul>
			</td>
		</tr>
		<tr class="windowbg2">
			<td>
				', $txt['media_add_allowedTypes'], ':
				<ul class="normallist">';

	foreach ($context['allowed_types'] as $k => $v)
		echo '
					<li><b>', $txt['media_filetype_'.$k], '</b>: ', str_replace('*.', '', implode(', ', $v)), '</li>';

	echo '
				</ul>
			</td>
		</tr>
		<tr class="windowbg2 center">
			<td>
				<form action="', $boardurl, '">
					<strong>1</strong>. ', $txt['media_sort_order'], ' &ndash;
					<select id="sort_order" name="sort_order">
						<option value="1" selected>', $txt['media_sort_order_filename'], '</option>
						<option value="2">', $txt['media_sort_order_filedate'], '</option>
						<option value="3">', $txt['media_sort_order_filesize'], '</option>
					</select>
				</form>
			</td>
		</tr>
		<tr class="windowbg2">
			<td>
				<form action="', $context['aeva_submit_url'], '" id="upload_form" method="post">
					<div id="mu_container" style="text-align: center">
						<p>
							<strong>2</strong>. <span id="browse" style="position: absolute; z-index: 2"></span>
							<span id="browseBtnSpan" style="z-index: 1"><a id="browseBtn" href="#">', $txt['media_selectFiles'], '</a></span> |
							<strong>3</strong>. <a id="upload" href="#">', $txt['media_upload'], '</a>
						</p>
						<div>
							<strong id="overall_title" class="overall-title">', $txt['media_overall_prog'], '</strong><br />
							<img src="', $settings['images_aeva'], '/bar.gif" class="progress overall-progress" id="overall_progress" /> <strong id="overall_prog_perc">0%</strong>
						</div>
						<div>
							<strong class="current-title" id="current_title">', $txt['media_curr_prog'], '</strong><br />
							<img src="', $settings['images_aeva'], '/bar.gif" class="progress2 current-progress" id="current_progress" /> <strong id="current_prog_perc">0%</strong>
						</div>
						<div class="current-text" id="current_text"></div>
					</div>
					<div>
						<div>
							<ul id="current_list">
								<li id="remove_me" style="visibility: hidden"></li>
							</ul>
						</div>
						<br style="clear: both;" />
						<div style="text-align: center;" id="mu_items"><input type="submit" name="aeva_submit" value="', $txt['media_submit'], '" /></div>
					</div>
				</form>
			</td>
		</tr>
		<tr id="mu_items_tr" style="display: none" class="titlebg">
			<td>', $txt['media_errors'], '</td>
		</tr>
		<tr id="mu_items_tr2" style="display: none" class="windowbg2">
			<td id="mu_items_error" style="color: red;">
			</td>
		</tr>
	</table>';
}

// Profile summary template
function template_aeva_profile_summary()
{
	global $txt, $galurl, $context, $settings, $scripturl, $user_info, $galurl, $amSettings;

	$member = &$context['aeva_member'];
	$can_rss = empty($amSettings['disable_rss']);

	echo '
		<table cellpadding="4" cellspacing="1" border="0" width="100%" class="bordercolor">
			<tr class="titlebg">
				<td><img src="', $settings['images_url'], '/admin/mgallery.png" border="0" />&nbsp;&nbsp;', $txt['media_profile_sum_pt'], '</td>
			</tr>
			<tr>
				<td class="windowbg smalltext" style="padding: 2ex;">', $txt['media_profile_sum_desc'], '</td>
			</tr>
			<tr class="titlebg">
				<td>', $txt['media_profile_stats'], '</td>
			</tr>
			<tr>
				<td class="windowbg2">
					', $can_rss ? '<a href="' . $galurl . 'sa=rss;user=' . $member['id'] . '"><img src="' . $settings['images_aeva'] . '/rss.png" alt="RSS" class="aeva_vera" /></a>' : '', ' <a href="', $scripturl, '?action=profile;u=', $member['id'], ';area=aevaitems">', $txt['media_total_items'], '</a>: ', $member['items'], '<br />
					', $can_rss ? '<a href="' . $galurl . 'sa=rss;user=' . $member['id'] . ';type=comments"><img src="' . $settings['images_aeva'] . '/rss.png" alt="RSS" class="aeva_vera" /></a>' : '', ' <a href="', $scripturl, '?action=profile;u=', $member['id'], ';area=aevacoms">', $txt['media_total_comments'], '</a>: ', $member['coms'], '<br />
					', $txt['media_avg_items'], ': ', $member['avg_items'], '<br />
					', $txt['media_avg_comments'], ': ', $member['avg_coms'], '<br />
				</td>
			</tr>';

	if (!empty($member['user_albums']))
	{
		$can_moderate = allowedTo('media_moderate');

		echo '
			<tr class="titlebg">
				<td>', $txt['media_albums'], $can_rss ? ' <a href="' . $galurl . 'sa=rss;user=' . $member['id'] . ';albums"><img src="' . $settings['images_aeva'] . '/rss.png" alt="RSS" class="aeva_vera" /></a>' : '', '</td>
			</tr>
			<tr>
				<td>';

		aeva_listChildren($member['user_albums']);

		echo '
				</td>
			</tr>';
	}

	echo '
		</table>';

	if (!empty($member['recent_items']))
	{
		echo '
		<table cellpadding="4" cellspacing="1" border="0" width="100%" class="bordercolor margintop">
			<tr class="titlebg">
				<td>', $txt['media_recent_items'], '</td>
			</tr>
		</table>

		<div id="home">
			<div id="recent_items">',
				aeva_listItems($member['recent_items']), '
			</div>
		</div>';
	}

	if (!empty($member['top_albums']))
	{
		echo '
		<table cellpadding="4" cellspacing="1" border="0" width="100%" class="bordercolor margintop">
			<tr class="titlebg">
				<td>', $txt['media_top_albums'], '</td>
			</tr>
			<tr>
				<td style="padding: 0px;">
					<table cellpadding="6" cellspacing="0" width="100%" border="0">';

		foreach ($member['top_albums'] as $album)
			echo '
						<tr>
							<td class="windowbg2" width="50%"><a href="', $galurl, 'sa=album;in=', $album['id'], '">', $album['name'], '</a></td>
							<td class="windowbg2" width="40%"><div class="aeva_statsbar" style="width: ', $album['percent'], 'px;"></div></td>
							<td class="windowbg2" width="10%">', $album['total_items'], '</td>
						</tr>';

		echo '
					</table>
				</td>
			</tr>
		</table>';
	}
	template_aeva_below();
}

// Template for viewing all items from a single member
function template_aeva_profile_viewitems()
{
	global $context, $txt, $galurl, $settings;

	echo '
		<table cellpadding="4" cellspacing="1" border="0" width="100%" class="bordercolor">
			<tr class="titlebg">
				<td><img src="', $settings['images_url'], '/admin/mgallery.png" border="0" />&nbsp;&nbsp;', $txt['media_profile_viewitems_pt'], '</td>
			</tr>
			<tr>
				<td class="windowbg smalltext" style="padding: 2ex;">', $txt['media_profile_viewitems_desc'], '</td>
			</tr>
		</table>

		<div id="home">
			<div class="pagelinks">', $txt['media_pages'], ': ', $context['page_index'], '</div>
			<div id="recent_items">',
				aeva_listItems($context['aeva_items']), '
			</div>
			<div class="pagelinks">', $txt['media_pages'], ': ', $context['page_index'], '</div>
		</div>';
	template_aeva_below();
}

// Template for viewing all items from a single member
function template_aeva_profile_viewcoms()
{
	global $context, $txt, $settings;

	echo '
		<table cellpadding="4" cellspacing="1" border="0" width="100%" class="bordercolor">
			<tr class="titlebg">
				<td><img src="', $settings['images_url'], '/admin/mgallery.png" border="0" />&nbsp;&nbsp;', $txt['media_profile_viewcoms_pt'], '</td>
			</tr>
			<tr>
				<td class="windowbg smalltext" style="padding: 2ex;">', $txt['media_profile_viewcoms_desc'], '</td>
			</tr>
		</table>

		<div class="pagelinks">', $txt['media_pages'], ': ', $context['page_index'], '</div>
		<div>';

	// Recent comments!
	if (!empty($context['aeva_coms']))
	{
		foreach ($context['aeva_coms'] as $i)
			echo '
		<div class="smalltext" style="padding: 8px">
			', $txt['media_comment_in'], ' <a href="', $i['url'], '"><b>', $i['media_title'], '</a></b> - ',
			$txt['media_posted_on' . (is_numeric($i['posted_on'][0]) ? '_date' : '')], ' ', $i['posted_on'], '
			<blockquote class="windowbg comment_preview">', parse_bbc($i['msg']), '</blockquote>
		</div>';
	}

	echo '
		</div>
		<div class="pagelinks">', $txt['media_pages'], ': ', $context['page_index'], '</div>';
	template_aeva_below();
}

// Template for viewing all votes from a single member
function template_aeva_profile_viewvotes()
{
	global $context, $txt, $settings, $scripturl;

	echo '
		<table class="bordercolor w100 cp4 cs1">
			<tr class="titlebg">
				<td><img src="', $settings['images_url'], '/admin/mgallery.png" border="0" />&nbsp;&nbsp;', $txt['media_profile_viewvotes_pt'], '</td>
			</tr>
			<tr>
				<td class="windowbg smalltext" style="padding: 2ex;">', $txt['media_profile_viewvotes_desc'], '</td>
			</tr>
		</table>

		<div class="pagelinks">', $txt['media_pages'], ': ', $context['page_index'], '</div>
		<div style="padding: 4px 0">';

	if (!empty($context['aeva_ratingLogs']))
	{
		echo '
			<we:cat>
				' . $context['aeva_voter_name'] . '
			</we:cat>';

		foreach ($context['aeva_ratingLogs'] as $log)
		{
			echo ' <img src="' . $settings['images_aeva'] . '/star' . $log['star'] . '.gif" class="aeva_vera" alt="' . $log['star'] . '" />';
			echo '&nbsp;&nbsp;<b><a href="' . $scripturl . '?action=media;sa=item;in=' . $log['id_media'] . '">' . $log['title'] . '</a></b>';
			echo ' (<a href="' . $scripturl . '?action=media;sa=album;in=' . $log['album_id'] . '">' . $log['name'] . '</a>)';
			if (!empty($log['messages']))
				echo ' (' . $log['messages'] . ' <a href="' . $scripturl . '?topic=' . $log['id_topic'] . '.0">' . $txt['media_post' . ($log['messages'] > 1 ? 's' : '') . '_noun'] . '</a>)';
			echo '<br />';
		}
		echo '<br />
			<we:cat>
				' . $txt['media_voter_list'] . '
			</we:cat>';
		foreach ($context['aeva_otherVoters'] as $row)
			echo '<br /><a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . ';area=aevavotes">' . $row['real_name'] . '</a> (' . $row['co'] . ' ' . $txt['media_vote' . ($row['co'] > 1 ? 's' : '') . '_noun'] . ')';
	}

	echo '
		</div>
		<div class="pagelinks">', $txt['media_pages'], ': ', $context['page_index'], '</div>';
	template_aeva_below();
}

// Who rated what list
function template_aeva_whoRatedWhat()
{
	global $context, $txt, $settings, $galurl;

	echo '
		<table cellpadding="6" cellspacing="1" border="0" width="100%" class="bordercolor">
			<tr>
				<td colspan="3" class="windowbg2 center">', $txt['media_who_rated_what'], ' (<a href="', $galurl, 'sa=item;in=', $context['item']['id_media'], '">', $context['item']['title'], '</a>)</td>
			</tr>
			<tr class="titlebg">
				<td width="60%">', $txt['media_member'], '</td>
				<td width="15%">', $txt['media_rating'], '</td>
				<td width="25%">', $txt['media_date'], '</td>
			</tr>
			<tr>
				<td class="windowbg2" colspan="3">', $txt['media_pages'], ': ', $context['page_index'], '</td>
			</tr>';

		foreach ($context['item']['rating_logs'] as $log)
		{
			echo '
				<tr>
					<td class="windowbg2"><a href="', $log['member_link'], '">', $log['member_name'], '</a></td>
					<td class="windowbg2">', str_repeat('<img src="' . $settings['images_url'] . '/star.gif" border="0" alt="*" />', $log['rating']), '</td>
					<td class="windowbg2">', $log['time'], '</td>
				</tr>';
		}
	echo '
		</table>';
}

// Filestack view for gallery
function aeva_listFiles($items, $can_moderate = false)
{
	global $galurl, $scripturl, $context, $txt, $settings, $user_info;

	$can_moderate_one = $can_moderate_here = allowedTo('media_moderate');
	if (!$can_moderate_one)
		foreach ($items as $item)
			$can_moderate_one |= $item['poster_id'] == $user_info['id'];

	echo '
		<table class="bordercolor w100 cp4 cs1">
			<tr class="catbg">
				<td style="height: 25px">', $txt['media_name'], '</td>
				<td>', $txt['media_posted_on'], '</td>
				<td>', $txt['media_views'], '</td>
				<td>', $txt['media_comments'], '</td>
				<td>', $txt['media_rating'], '</td>', $can_moderate && $can_moderate_one ? '
				<td width="5%"></td>' : '', '
			</tr>';

	$alt = false;
	foreach ($items as $item)
	{
		$check = $can_moderate && ($can_moderate_here || $item['poster_id'] == $user_info['id']) ? '
				<td class="bottom"><input type="checkbox" name="mod_item[' . $item['id'] . ']" /></td>' : '';
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
					<div class="mg_desc" style="padding: 8px">' . parse_bbc($item['desc']) . '</div>
				</td>' : '', '
			</tr>';
		$alt = !$alt;
	}
	echo '
		</table>';
}

function template_aeva_playlist()
{
	global $context;

	if (!empty($context['aeva_foxy_rendered_playlist']))
		echo $context['aeva_foxy_rendered_playlist'];
}

function template_aeva_rating_object($item)
{
	global $context, $settings, $txt, $galurl;

	$object = ($item['can_rate'] ? '
				<form action="'.$galurl.'sa=item;in='.$item['id_media'].'" method="post" id="ratingForm">' : '') . '
					' . ($item['voters'] > 0 ? str_repeat('<img src="'.$settings['images_url'].'/star.gif" />', round($item['avg_rating'])) . ' ' . round($item['avg_rating'], 2) . ' (' . (allowedTo('media_whoratedwhat') ? '<a href="' . $galurl . 'sa=whoratedwhat;in=' . $item['id_media'] . '">' : '') . $item['voters'] . ' ' . $txt['media_vote' . ($item['voters'] > 1 ? 's' : '') . '_noun'] . (allowedTo('media_whoratedwhat') ? '</a>' : '') . ')' : '') .
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

function template_aeva_xml_rated()
{
	global $context, $txt;

	echo '<?xml version="1.0" encoding="', $context['character_set'], '"?', '>
<ratingObject><![CDATA[', template_aeva_rating_object($context['item_data']), ']]></ratingObject>';
}

?>