<?php
/**
 * Admin area template for the gallery.
 * Uses portions written by Shitiz Garg.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

function template_aeva_admin_before()
{
	template_aeva_tabs();
}

function template_aeva_admin_after()
{
}

function template_aeva_admin_enclose_table_before()
{
	echo '
		<table class="w100 cp0 cs0">
			<tr>
				<td>';
}

function template_aeva_admin_enclose_table_after()
{
	echo '
				</td>
			</tr>
		</table>';
}

function template_aeva_admin_submissions()
{
	global $context, $txt, $galurl, $amSettings;

	$filter = $context['aeva_filter'];

	// Show some extra info
	echo '
		<form action="', $galurl, 'area=moderate;sa=submissions;type=', $filter, '" method="post">
			<table class="w100 cp8 cs1">
				<tr class="windowbg2">
					<td>', $txt['media_admin_total_submissions'], ': ', $context['aeva_total'], '</td>
				</tr>
				<tr class="windowbg2">
					<td>', $txt['pages'], ': ', $context['aeva_page_index'], '</td>
				</tr>
			</table>';

	// Show the actual submissions
	echo '
			<table class="w100 cp8 cs1" id="approvals">
				<tr class="titlebg">
					<td style="width: 2%">&nbsp;</td>
					<td>', $txt['media_name'], '</td>
					<td>', $txt['media_posted_by'], '</td>
					<td>', $txt['media_admin_moderation'], '</td>', $filter != 'albums' ? '
					<td>' . $txt['media_posted_on'] . '</td>' : '', '
					<td style="width: 4%"><input type="checkbox" name="checkAll" id="checkAll" onclick="invertAll(this, this.form, \'items[]\');"></td>
				</tr>', !empty($context['aeva_item']) ? '
				<tr class="windowbg2">
					<td colspan="' . ($filter == 'albums' ? 5 : 6) . '"><a href="#" onclick="return admin_toggle_all();">' . $txt['media_toggle_all'] . '</a></td>
				</tr>' : '';

	$alt = false;
	foreach ($context['aeva_items'] as $item)
	{
		echo '
				<tr class="windowbg', $alt ? '2' : '', '" id="', $item['id'], '">
					<td><a href="#" onclick="return admin_toggle(', $item['id'], ');"><div class="foldable fold" id="toggle_img_', $item['id'], '"></div></a></td>
					<td><a href="', $item['item_link'], '">', $item['title'], '</a></td>
					<td>', $item['poster'], '</td>
					<td>
						<img src="', ASSETS, '/aeva/tick.png" title="', $txt['media_admin_approve'], '"> <a href="#" onclick="return doSubAction(\'<URL>?action=media;area=moderate;sa=submissions;do=approve;in=', $item['id'], ';type=', $filter, ';', $context['session_query'], '\');">', $txt['media_admin_approve'], '</a>
						<img src="', ASSETS, '/aeva/folder_edit.png" title="', $txt['media_admin_edit'], '"> <a href="', $item['edit_link'], '">', $txt['media_admin_edit'], '</a>
						<img src="', ASSETS, '/aeva/folder_delete.png" title="', $txt['media_admin_delete'], '"> <a href="#" onclick="return ask(we_confirm, e) && doSubAction(\'', $item['del_link'], '\');">', $txt['media_admin_delete'], '</a>', $filter == 'items' ? '
						<a href="' . $galurl . 'sa=media;in=' . $item['id'] . ';preview"' . ($amSettings['use_zoom'] ? ' class="zoom"' : '') . '><img src="' . ASSETS . '/aeva/magnifier.png"> ' . $txt['media_admin_view_image'] . '</a>' : '', '
					</td>', $filter != 'albums' ? '
					<td>' . $item['posted_on'] . '</td>' : '', '
					<td><input type="checkbox" name="items[]" value="', $item['id'], '" id="items[]"></td>
				</tr>
				<tr id="tr_expand_' . $item['id'] . '" class="windowbg', $alt ? '2' : '', ' hide">
					<td colspan="', $filter == 'albums' ? 4 : 5, '">', !empty($item['description']) ? '
						<div>' . $txt['media_add_desc'] .': '. $item['description'] . '</div>' : '', !empty($item['keywords']) ? '
						<div>' . $txt['media_keywords'] . ': ' . $item['keywords'] . '</div>' : '', '
					</td>
				</tr>';
		$alt = !$alt;
	}

	echo '
				<tr class="windowbg', $alt ? '' : '2', '">
					<td colspan="', $filter == 'albums' ? 5 : 6, '" class="right">
						', $txt['media_admin_wselected'], ':
						<select name="do">
							<option value="approve">', $txt['media_admin_approve'], '</option>
							<option value="delete">', $txt['media_admin_delete'], '</option>
						</select>&nbsp;
						<input type="submit" name="submit_aeva" value="', $txt['media_submit'], '">
					</td>
				</tr>
			</table>
		</form>';
}

// Maintenance homepage
function template_aeva_admin_maintenance()
{
	global $txt, $context;

	// Maintenance headers
	if ($context['aeva_maintenance_done'] !== false)
	{
		if ($context['aeva_maintenance_done'] === true)
			$color = 'green';
		elseif ($context['aeva_maintenance_done'] == 'pending')
			$color = 'orange';
		else
			$color = 'red';

		echo '<div class="windowbg2 wrc" style="border: 1px dashed ', $color, '; color: ', $color, '; margin: 1ex">', !empty($context['aeva_maintenance_message']) ? $context['aeva_maintenance_message'] : $txt['media_maintenance_done'], '</div>';
	}

	echo '
		<table class="w100 cp8 cs1">';

	foreach ($context['aeva_dos'] as $type => $contents)
	{
		echo '
			<tr class="titlebg">
				<td>', $txt['media_admin_maintenance_' . $type], '</td>
			</tr>
			<tr class="windowbg2">
				<td>
					<ul style="margin: 0">';

		$count = count($contents);
		foreach ($contents as $counter => $task)
		{
			echo '
						<li>
							<a href="', $task['href'], '">', $task['title'], '</a>', !empty($task['subtext']) ? '<dfn>' . $task['subtext'] . '</dfn>' : '';
			if ($count > $counter + 1)
				echo '
							<hr>';
			echo '
						</li>';
		}

		echo '
					</ul>
				</td>
			</tr>';
	}

	echo '
		</table>';
}

// Prune page template
function template_aeva_admin_maintenance_prune()
{
	global $context, $txt;

	add_js('
	function prune_toggle(is_item)
	{
		$("#item_prune_opts").toggle(is_item);
		$("#com_prune_opts").toggle(!is_item);
	}');

	echo '
		<form action="<URL>?action=admin;area=aeva_maintenance;sa=prune;', $context['session_query'], '" method="post">
			<table class="w100 cp8 cs0">
				<tr class="titlebg">
					<td>', $txt['media_pruning'], '</td>
				</tr>
				<tr>
					<td class="windowbg2">
						<label><input type="radio" id="pr1" name="pruning" value="item" onclick="prune_toggle(true);" checked> ', $txt['media_items'], '</label><br>
						<label><input type="radio" id="pr2" name="pruning" value="comments" onclick="prune_toggle(false);"> ', $txt['media_comments'], '</label>
					</td>
				</tr>
				<tr id="item_prune_opts">
					<td style="padding: 0; margin: 0; border: 0">
						<table class="w100 cp8 cs0">
							<tr class="catbg">
								<td height="25">', $txt['media_items'], '</td>
							</tr>
							<tr class="windowbg2">
								<td><dfn>', $txt['media_admin_maintenance_prune_item_help'], '</dfn></td>
							</tr>
							<tr class="windowbg2">
								<td><input size="4" name="days" value="60"> ', $txt['media_admin_maintenance_prune_days'], '</td>
							</tr>
							<tr class="windowbg2">
								<td><hr></td>
							</tr>
							<tr class="windowbg2">
								<td>', $txt['media_admin_maintenance_prune_max_views'], ' <input size="4" name="max_views"></td>
							</tr>
							<tr class="windowbg2">
								<td>', $txt['media_admin_maintenance_prune_max_coms'], ' <input size="4" name="max_coms"></td>
							</tr>
							<tr class="windowbg2">
								<td>', $txt['media_admin_maintenance_prune_last_comment_age'], ' <input size="4" name="last_comment_age"> ', isset($txt['days_word']) ? $txt['days_word'] : $txt[579], '</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr id="com_prune_opts" class="hide">
					<td style="padding: 0; margin: 0; border: 0">
						<table class="w100 cp8 cs0">
							<tr class="catbg">
								<td height="25">', $txt['media_comments'], '</td>
							</tr>
							<tr class="windowbg2">
								<td><dfn>', $txt['media_admin_maintenance_prune_com_help'], '</dfn></td>
							</tr>
							<tr class="windowbg2">
								<td><input size="4" name="days_com" value="60"> ', $txt['media_admin_maintenance_prune_days'], '</td>
							</tr>
						</table>
					</td>
				</tr>';

	if (!empty($context['aeva_album_list']))
	{
		echo '
				<tr class="catbg">
					<td height="25">', $txt['media_albums'], '</td>
				</tr>
				<tr>
					<td class="windowbg2">
						<label><input type="checkbox" name="all_albums"> ', $txt['media_all_albums'], '</label>
						<br><br>
						<select name="albums[]" multiple size="9">';

		foreach ($context['aeva_album_list'] as $list)
			echo '
							<option value="', $list, '">&nbsp;', str_repeat('&#150;', $context['aeva_albums'][$list]['child_level']), $context['aeva_albums'][$list]['child_level'] ? ' ' : '', $context['aeva_albums'][$list]['name'], '&nbsp;</option>';

		echo '
						</select>
					</td>
				</tr>';
	}
	elseif (!empty($context['aeva_maintain_album']))
		echo '
				<tr class="hide"><td><input type="hidden" name="albums[]" value="' . $context['aeva_maintain_album'] . '"></td></tr>';

	echo '
				<tr>
					<td class="windowbg2 right"><input type="submit" value="', $txt['media_submit'], '" name="submit_aeva" onclick="return ask(we_confirm, e);"></td>
				</tr>
			</table>
		</form>';
}

function template_aeva_admin_modlog()
{
	global $context, $galurl, $txt;

	echo '
	<form action="', $galurl, 'area=moderate;sa=modlog;', $context['session_query'], '" method="post">
		<table class="w100 cp8 cs0">', !empty($context['aeva_logs']) ? '
			<tr>
				<td class="windowbg2 right">
					<input type="submit" name="delete" value="'.$txt['media_admin_rm_selected'].'" style="margin-right: 10px" onclick="return ask(we_confirm, e);"><input type="submit" name="delete_all" value="'.$txt['media_admin_rm_all'].'" style="margin-right: 10px" onclick="return ask(we_confirm, e);">
				</td>
			</tr>' : '', '
			<tr>
				<td class="catbg">
					', $txt['pages'], ': ', $context['aeva_page_index'], '
				</td>
			</tr>
		</table>
		<table class="w100 cp8 cs1">
			<tr class="titlebg">
				<td style="width: 5%">&nbsp;</td>
				<td>', $txt['media_action'], '</td>
				<td>', $txt['media_time'], '</td>
				<td>', $txt['media_member'], '</td>
			</tr>';

	foreach ($context['aeva_logs'] as $log)
	{
		echo '
			<tr class="windowbg2">
				<td class="middle"><input type="checkbox" name="to_delete[]" value="', $log['id'], '"></td>
				<td class="smalltext">', $log['text'], '</td>
				<td class="smalltext">', $log['time'], '</td>
				<td class="smalltext"><a href="', $log['action_by_href'], '">', $log['action_by_name'], '</a></td>
			</tr>';
	}

	echo '
			<tr>
		</table>
	</form>
	<form action="', $galurl, 'area=moderate;sa=modlog;', $context['session_query'], '" method="post">
		<table class="w100 cp8 cs0">
			<tr class="titlebg">
				<td>
					', $txt['media_admin_modlog_qsearch'], ': <input name="qsearch_mem" size="15"> <input type="submit" name="qsearch_go" value="', $txt['media_submit'], '">
				</td>
			</tr>
		</table>
	</form>';
}

function template_aeva_admin_reports()
{
	// Shows the reports page
	global $galurl, $context, $txt;

	echo '

		<table class="w100 cp8 cs1">
			<tr class="titlebg">
				<td style="width: 2%">&nbsp;</td>
				<td>', $txt['media_admin_reported_item'], '</td>
				<td>', $txt['media_admin_reported_by'], '</td>
				<td>', $txt['media_admin_reported_on'], '</td>
				<td>', $txt['media_admin_moderation'], '</td>
			</tr>', !empty($context['aeva_reports']) ? '
			<tr class="windowbg2">
				<td colspan="5"><a href="#" onclick="return admin_toggle_all();">' . $txt['media_toggle_all'] . '</a></td>
			</tr>' : '';

	foreach ($context['aeva_reports'] as $report)
	{
		echo '
			<tr class="windowbg2">
				<td><a href="#" onclick="return admin_toggle(', $report['id_report'], ');"><div class="foldable fold" id="toggle_img_', $report['id_report'], '"></div></a></td>
				<td><a href="', $galurl, 'sa=item;in=', $context['aeva_report_type'] == 'comment' ? $report['id2'] . '#com' . $report['id'] : $report['id'], '">', $report['title'], '</a></td>
				<td>', aeva_profile($report['reported_by']['id'], $report['reported_by']['name']), '</td>
				<td>', $report['reported_on'], '</td>
				<td><a href="<URL>?action=media;area=moderate;sa=reports;do=delete;items;in=', $report['id_report'], ';', $context['session_query'], '">', $txt['media_admin_del_report'], '</a>
				<br><a href="<URL>?action=media;area=moderate;sa=reports;items;do=deleteitem;in=', $report['id_report'], ';', $context['session_query'], '" onclick="return ask(we_confirm, e);">', $txt['media_admin_del_report_item'], '</a></td>
			</tr>
			<tr class="windowbg" id="tr_expand_', $report['id_report'], '" class="hide">
				<td colspan="5">
					', $txt['media_posted_by'], ': ', aeva_profile($report['posted_by']['id'], $report['posted_by']['name']), '<br>
					', $txt['media_posted_on'], ': ', $report['posted_on'], '<br>
					', $txt['media_admin_report_reason'], ': ', $report['reason'], '
				</td>
			</tr>';
	}

	echo '
		</table>';
}

// The main ban page
function template_aeva_admin_bans()
{
	global $txt, $context;

	echo '

		<table class="w100 cp8 cs1">
			<tr class="titlebg">
				<td>', $txt['user'], '</td>
				<td>', $txt['media_admin_banned_on'], '</td>
				<td>', $txt['media_admin_expires_on'], '</td>
				<td>', $txt['media_admin_ban_type'], '</td>
				<td>', $txt['media_admin_moderation'], '</td>
			</tr>
			<tr class="windowbg2">
				<td colspan="5">', $txt['pages'], ': ', $context['aeva_page_index'], '</td>
			</tr>';

	foreach ($context['aeva_bans'] as $ban)
	{
		echo '
			<tr class="windowbg2">
				<td>', aeva_profile($ban['banned']['id'], $ban['banned']['name']), '</td>
				<td>', $ban['banned_on'], '</td>
				<td>', $ban['expires_on'], '</td>
				<td>', $ban['type_txt'], '</td>
				<td><a href="<URL>?action=admin;area=aeva_bans;sa=edit;in=', $ban['id'], ';', $context['session_query'], '">', $txt['media_admin_edit'], '</a>/<a href="<URL>?action=admin;area=aeva_bans;sa=delete;in=', $ban['id'], ';', $context['session_query'], '">', $txt['media_admin_delete'], '</a></td>
			</tr>';
	}

	echo '
		</table>';
}

function template_aeva_admin_perms()
{
	global $txt, $context;

	echo '
	<we:cat>
		<a href="<URL>?action=help;in=media_permissions" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
		', $txt['media_admin_labels_perms'], '
	</we:cat>
	<div class="information">
		', $txt['media_admin_perms_desc'], '
	</div>

	<form method="post" action="', $context['base_url'], '">
		<table class="w100 center cp4 cs1 tborder" style="margin-top: 2ex">
			<tr class="windowbg2">
				<td colspan="3">', sprintf($txt['media_admin_perms_warning'], '<URL>?action=admin;area=permissions;' . $context['session_query']), '</td>
			</tr>
			<tr class="catbg">
				<td class="w50">', $txt['media_admin_prof_name'], '</td>
				<td class="w25">', $txt['media_albums'], '</td>
				<td class="w25">', $txt['media_delete_this_item'], '</td>
			</tr>';

	$alt = false;
	foreach ($context['aeva_profiles'] as $prof)
	{
		$alt = !$alt;
		echo '
			<tr class="windowbg', $alt ? '2' : '', '">
				<td><a href="', $context['base_url'], ';sa=view;in=', $prof['id'], '">', $prof['name'], '</a></td>
				<td><a href="#" onclick="return getPermAlbums(', $prof['id'], ');">', $prof['albums'], '</a></td>
				<td', !empty($prof['undeletable']) ? ' title="' . $txt['media_permissions_undeletable'] . '"' : '', '><input name="delete_prof_', $prof['id'], '" type="checkbox" onclick="return permDelCheck(e, ', $prof['id'], ', this);"', !empty($prof['undeletable']) ? ' disabled' : '', '></td>
			</tr>
			<tr class="windowbg', $alt ? '2' : '', ' hide" id="albums_' . $prof['id'] . '">
				<td colspan="3" id="albums_td_' . $prof['id'] . '"></td>
			</tr>';
	}

	echo '
			<tr class="windowbg', $alt ? '' : '2', '">
				<td colspan="2">', $txt['media_admin_prof_del_switch'], '<br><span class="smalltext">', $txt['media_admin_prof_del_switch_help'], '</span></td>
				<td>
					<select name="del_prof">
						<option data-hide></option>';

	foreach ($context['aeva_profiles'] as $prof)
		echo '
						<option value="', $prof['id'], '">', $prof['name'], '</option>';

	echo '
					</select>
				</td>
			</tr>
			<tr class="windowbg', $alt ? '2' : '', '">
				<td colspan="3" class="right"><input type="submit" name="aeva_delete_profs" value="', $txt['media_delete_this_item'], '"></td>
			</tr>
		</table>
	</form>

	<form method="post" action="', $context['base_url'], ';sa=add">
		<table class="w100 center cp4 cs1 tborder" style="margin-top: 2ex">
			<tr class="titlebg">
				<td>', $txt['media_admin_profile_add'], '</td>
			</tr>
			<tr class="windowbg2">
				<td>', $txt['media_admin_prof_name'], ': <input name="name"></td>
			</tr>
			<tr class="windowbg">
				<td class="right"><input type="submit" name="submit_aeva" value="', $txt['media_admin_create_prof'], '"></td>
			</tr>
		</table>
	</form>';
}

function template_aeva_admin_perms_view()
{
	global $txt, $context;

	echo '
		<form action="', $context['base_url'], ';sa=quick;profile=', $context['aeva_profile']['id'], '" method="post">
			<table class="w100 center cp4 cs1 tborder" style="margin-top: 2ex">
				<tr class="catbg">
					<td colspan="5">', $txt['media_perm_profile'], ': "', $context['aeva_profile']['name'], '"</td>
				</tr>
				<tr class="titlebg">
					<td style="width: 40%">', $txt['media_admin_membergroups'], '</td>
					<td style="width: 20%">', $txt['media_admin_members'], '</td>
					<td style="width: 25%">', $txt['media_admin_labels_perms'], '</td>
					<td style="width: 10%">', $txt['media_edit_this_item'], '</td>
					<td style="width: 5%"><input type="checkbox" name="checkAll" id="checkAll" onclick="invertAll(this, this.form, \'groups[]\');"></td>
				</tr>';

	$alt = false;
	$membergroup_string = '';
	foreach ($context['membergroups'] as $id => $group)
	{
		$alt = !$alt;
		echo '
				<tr class="windowbg', $alt ? '2' : '', '">
					<td>', $group['name'], '</td>
					<td>', $id > 0 ? '<a href="<URL>?action=moderate;area=viewgroups;sa=members;group=' . $id . '">' . $group['num_members'] . '</a>' : $group['num_members'], '</td>
					<td>', isset($group['perms']) ? $group['perms'] : 0, '</td>
					<td><a href="', $context['base_url'], ';sa=edit;in=', $context['aeva_profile']['id'], ';group=', $id, '">', $txt['media_edit_this_item'], '</a></td>
					<td><input type="checkbox" name="groups[]" value="', $id, '" id="groups[]"></td>
				</tr>';
		$membergroup_string .= '
							<option value="' . $id . '">' . $group['name'] . '</option>';
	}

	echo '
				<tr class="windowbg', $alt ? '' : '2', '">
					<td colspan="5" class="right" style="margin-top: 2px">
						<div style="margin-bottom: 1ex">', $txt['media_admin_wselected'], '</div>
						', $txt['media_admin_set_mg_perms'], ':
						<select name="copy_membergroup">
							<option data-hide></option>', $membergroup_string, '
						</select>
						', $txt['media_admin_select_or'], '
						<select name="with_selected">
							<option value="apply">', $txt['media_admin_apply_perm'], '</option>
							<option value="clear">', $txt['media_admin_clear_perm'], '</option>
						</select>&nbsp;
						<select name="selected_perm">
							<option data-hide></option>';

	foreach ($context['aeva_album_permissions'] as $perm)
		echo '
							<option value="', $perm, '">', $txt['permissionname_media_' . $perm], '</option>';

	echo '
						</select>
						&nbsp;
						<input type="submit" value="', $txt['media_submit'], '">
					</td>
				</tr>
			</table>
		</form>';
}

// Membergroup quota template
function template_aeva_admin_quotas()
{
	global $txt, $context;

	echo '
	<we:cat>
		', $txt['media_admin_labels_quotas'], '
	</we:cat>
	<div class="information">
		', $txt['media_admin_quotas_desc'], '
	</div>

	<form method="post" action="<URL>?action=admin;area=aeva_quotas;', $context['session_query'], '">
		<table class="w100 center cp4 cs1 tborder" style="margin-top: 2ex">
			<tr class="catbg">
				<td class="w50">', $txt['media_admin_prof_name'], '</td>
				<td class="w25">', $txt['media_albums'], '</td>
				<td class="w25">', $txt['media_delete_this_item'], '</td>
			</tr>';

	$alt = false;
	foreach ($context['aeva_profiles'] as $prof)
	{
		$alt = !$alt;
		echo '
			<tr class="windowbg', $alt ? '2' : '', '">
				<td><a href="<URL>?action=admin;area=aeva_quotas;sa=view;in=', $prof['id'], ';', $context['session_query'], '">', $prof['name'], '</a></td>
				<td><a href="#" onclick="return getPermAlbums(', $prof['id'], ', \';prof=', $prof['id'], '\');">', $prof['albums'], '</a></td>
				<td><input name="delete_prof_', $prof['id'], '" type="checkbox" onclick="return permDelCheck(e, ', $prof['id'], ', this);"', !empty($prof['undeletable']) ? ' disabled' : '', '></td>
			</tr>
			<tr class="windowbg', $alt ? '2' : '', ' hide" id="albums_' . $prof['id'] . '">
				<td colspan="3" id="albums_td_' . $prof['id'] . '"></td>
			</tr>';
	}

	if (!empty($context['aeva_profiles']))
	{
		echo '
				<tr class="windowbg', $alt ? '' : '2', '">
					<td colspan="2">', $txt['media_admin_prof_del_switch'], '<br><span class="smalltext">', $txt['media_admin_prof_del_switch_help'], '</span></td>
					<td>
						<select name="del_prof">
							<option data-hide></option>';
		foreach ($context['aeva_profiles'] as $prof)
			echo '
							<option value="', $prof['id'], '">', $prof['name'], '</option>';
		echo '
						</select>
					</td>
				</tr>
				<tr class="windowbg', $alt ? '2' : '', '">
					<td colspan="3" class="right"><input type="submit" name="aeva_delete_profs" value="', $txt['media_delete_this_item'], '"></td>
				</tr>';
	}

	echo '
		</table>
	</form>';

		echo '
	<form method="post" action="<URL>?action=admin;area=aeva_quotas;sa=add;', $context['session_query'], '">
		<table class="w100 center cp4 cs1 tborder" style="margin-top: 2ex">
			<tr class="titlebg">
				<td>', $txt['media_admin_profile_add'], '</td>
			</tr>
			<tr class="windowbg2">
				<td>', $txt['media_admin_prof_name'], ': <input name="name"></td>
			</tr>
			<tr class="windowbg right">
				<td><input type="submit" name="submit_aeva" value="', $txt['media_admin_create_prof'], '"></td>
			</tr>
		</table>
	</form>';
}

// Viewing a profile
function template_aeva_admin_quota_view()
{
	global $txt, $context;

	echo '
		<table class="w100 center cp4 cs1 tborder" style="margin-top: 2ex">
			<tr class="catbg">
				<td colspan="4">', $txt['media_quota_profile'], ': "', $context['aeva_profile']['name'], '"</td>
			</tr>
			<tr class="titlebg">
				<td style="width: 65%">', $txt['media_admin_membergroups'], '</td>
				<td style="width: 20%">', $txt['media_admin_members'], '</td>
				<td style="width: 15%">', $txt['media_edit_this_item'], '</td>
			</tr>';
	$alt = false;
	foreach ($context['membergroups'] as $id => $group)
	{
		$alt = !$alt;
		echo '
			<tr class="windowbg', $alt ? '2' : '', '">
				<td>', $group['name'], '</td>
				<td>', $id > 0 ? '<a href="<URL>?action=moderate;area=viewgroups;sa=members;group=' . $id . '">' . $group['num_members'] . '</a>' : $group['num_members'], '</td>
				<td><a href="<URL>?action=admin;area=aeva_quotas;sa=edit;in=', $context['aeva_profile']['id'], ';group=', $id, ';', $context['session_query'], '">', $txt['media_edit_this_item'], '</a></td>
			</tr>';
	}
	echo '
		</table>';
}

// Custom fields template
function template_aeva_admin_fields()
{
	global $txt, $context;

	echo '
		<table class="bordercolor w100 center cp4 cs1">
			<tr class="titlebg">
				<td colspan="3">', $txt['media_cf'], '</td>
			</tr>
			<tr class="catbg">
				<td class="w50">', $txt['media_cf_name'], '</td>
				<td class="w25">', $txt['media_cf_type'], '</td>
				<td class="w25">', $txt['media_admin_moderation'], '</td>
			</tr>
			<tr class="windowbg2">
				<td colspan="3"><a href="<URL>?action=admin;area=aeva_fields;sa=edit;', $context['session_query'], '">', $txt['media_cf_add'], '</a></td>
			</tr>';
	$alt = false;
	foreach ($context['custom_fields'] as $field)
	{
		echo '
			<tr class="windowbg', $alt ? '2' : '', '">
				<td>', $field['name'], '</td>
				<td>', $txt['media_cf_' . $field['type']], '</td>
				<td><a href="<URL>?action=admin;area=aeva_fields;sa=edit;in=', $field['id'], ';', $context['session_query'], '">', $txt['media_edit_this_item'], '</a> / <a href="<URL>?action=admin;area=aeva_fields;delete=', $field['id'], ';', $context['session_query'], '" onclick="return ask(we_confirm, e);">', $txt['media_delete_this_item'], '</a></td>
			</tr>';
		$alt = !$alt;
	}
	echo '
		</table>';
}

// FTP Import template
function template_aeva_admin_ftpimport()
{
	global $context, $txt;

	$albumOpts_str = '
	<option class="hr"></option>';

	foreach ($context['aeva_album_list'] as $list)
		$albumOpts_str .= '
	<option value="' . $list . '">' . str_repeat('-', $context['aeva_albums'][$list]['child_level']) . ' [' . $context['aeva_albums'][$list]['owner']['name'] . '] ' . $context['aeva_albums'][$list]['name'] . '</option>';

	echo '
	<we:cat>
		', $txt['media_admin_labels_ftp'], '
	</we:cat>
	<div class="information">
		', $txt['media_admin_ftp_desc'], '
	</div>

	<form action="<URL>?action=admin;area=aeva_ftp;', $context['session_query'], '" method="post">
		<table class="w100 cp4 cs1">
			<tr>
				<td class="windowbg smalltext" style="padding: 10px">', $txt['media_admin_ftp_help'], '</td>
			</tr>
			<tr class="catbg">
				<td>', $txt['media_admin_ftp_files'], '</td>
			</tr>';

	if ($context['is_halted'])
		echo '
			<tr>
				<td class="unapproved_yet">', sprintf($txt['media_admin_ftp_halted'], $context['ftp_done'], $context['total_files']), '</td>
			</tr>';

	foreach ($context['ftp_folder_list'] as $folder)
	{
		echo '
			<tr>
				<td class="windowbg2 smalltext" style="padding-left: ', (30 * $context['ftp_map'][$folder]['child_level']), 'px">
					&nbsp;<img src="', ASSETS, '/aeva/album.png"> ', $context['ftp_map'][$folder]['fname'], ' (', count($context['ftp_map'][$folder]['files']), ' ', $txt['media_files'], ')
					&nbsp;<select name="aeva_folder_', $folder, '">', $albumOpts_str, '</select>
				</td>
			</tr>';

		foreach ($context['ftp_map'][$folder]['files'] as $file)
			echo '
			<tr>
				<td class="windowbg2 smalltext" style="padding-left: ', (30 * ($context['ftp_map'][$folder]['child_level'] + 1)), 'px">', $file[0], ' (', round($file[1] / 1024), ' ', $txt['media_kb'], ')</td>
			</tr>';
	}

	echo '
			<tr>
				<td class="windowbg2 center">
					<input type="submit" name="aeva_submit" value="', $txt['media_submit'], '">
					<input type="hidden" name="aeva_folder" value="pass">
				</td>
			</tr>
		</table>
	</form>';
}
