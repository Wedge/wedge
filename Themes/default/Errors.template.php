<?php
/**
 * Wedge
 *
 * Displays fatal errors, the error log, and handles showing buggy lines within file context, to help with debugging.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Show an error message. This should have at least a back button and $context['error_message'].
function template_fatal_error()
{
	global $context, $theme, $options, $txt;

	echo '
	<br>
	<div id="fatal_error">
		<we:cat>
			', $context['error_title'], '
		</we:cat>
		<div class="windowbg wrc">
			<div class="padding">', $context['error_message'], '</div>
		</div>
	</div>';

	// Show a back button (using javascript.)
	if (empty($context['no_back_link']))
		echo '
	<div class="centertext"><a href="javascript:history.go(-1)">', $txt['back'], '</a></div>';
}

function template_error_log()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	add_js('
	var lastClicked = "";');

	echo '
	<form action="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';start=', $context['start'], $context['has_filter'] ? $context['filter']['href'] : '', '" method="post" accept-charset="UTF-8" onsubmit="return (lastClicked != \'remove_all\') || confirm(', JavaScriptEscape($txt['sure_about_errorlog_remove']), ');">
		<div class="clear_right">
			<we:title>
				<a href="', $scripturl, '?action=help;in=error_log" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
				', $txt['errlog'], '
			</we:title>
		</div>
		<table class="table_grid w100 cs1" id="error_log">
			<tr>
				<td colspan="3" class="windowbg">
					&nbsp;&nbsp;', $txt['apply_filter_of_type'], ':';

	$error_types = array();
	foreach ($context['error_types'] as $type => $details)
		$error_types[] = ($details['is_selected'] ? '<img src="' . $theme['images_url'] . '/selected.gif"> ' : '') . '<a href="' . $details['url'] . '" ' . ($details['is_selected'] ? 'style="font-weight: bold;"' : '') . ' title="' . $details['description'] . '">' . $details['label'] . '</a>';

	echo '
					', implode('&nbsp;|&nbsp;', $error_types), '
				</td>
			</tr>
			<tr>
				<td colspan="3" class="windowbg">
					&nbsp;&nbsp;', $txt['pages'], ': ', $context['page_index'], '
				</td>
			</tr>';

	if ($context['has_filter'])
		echo '
			<tr>
				<td colspan="3" class="windowbg">
					<strong>', $txt['applying_filter'], ':</strong> ', $context['filter']['entity'], ' ', $context['filter']['value']['html'], ' (<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', '">', $txt['clear_filter'], '</a>)
				</td>
			</tr>';

	if (!empty($context['errors']))
		echo '
			<tr class="titlebg left">
				<td colspan="3">
					<div class="floatright"><input type="submit" value="', $txt['remove_selection'], '" onclick="lastClicked = \'remove_selection\';" class="delete"> <input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="lastClicked = \'remove_all\';" class="delete"></div>
					<label style="line-height: 24px"><input type="checkbox" id="check_all1" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all2.checked = this.checked;"> <strong>', $txt['check_all'], '</strong></label>
				</td>
			</tr>';

	foreach ($context['errors'] as $error)
	{
		echo '
			<tr class="windowbg', $error['alternate'] ? '2' : '', '">
				<td rowspan="2" class="checkbox_column">
					<input type="checkbox" name="delete[]" value="', $error['id'], '">
				</td>
				<td class="half_width">
					<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=id_member;value=', $error['member']['id'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '"><img src="', $theme['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '"></a>
					<strong>', $error['member']['link'], '</strong><br>
					<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=ip;value=', $error['member']['ip'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '"><img src="', $theme['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '"></a>
					<strong><a href="', $scripturl, '?action=trackip;searchip=', $error['member']['display_ip'], '">', $error['member']['display_ip'], '</a></strong>&nbsp;&nbsp;
					<br>&nbsp;
				</td>
				<td class="half_width">
					<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? '' : ';desc', $context['has_filter'] ? $context['filter']['href'] : '', '" title="', $txt['reverse_direction'], '">
						<img src="', $theme['images_url'], '/sort_', $context['sort_direction'], '.gif" alt="', $txt['reverse_direction'], '">
					</a>
					', $error['time'], '<br>
					<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=error_type;value=', $error['error_type']['type'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"><img src="', $theme['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"></a>
					', $txt['error_type'], ': ', $error['error_type']['name'], '
				</td>
			</tr>
			<tr class="windowbg', $error['alternate'] ? '2' : '', '">
				<td colspan="2" class="middle">
					<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=url;value=', $error['url']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '"><img src="', $theme['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '"></a>
					', strlen($error['url']['html']) > 80 ? '<a href="' . $error['url']['html'] . '" title="' . $error['url']['html'] . '">' . westr::cut($error['url']['html'], 80) . '</a>'
					 : '<a href="' . $error['url']['html'] . '">' . $error['url']['html'] . '</a>', '
					<br class="clear">
					<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=message;value=', $error['message']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '"><img src="', $theme['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '"></a>
					', $error['message']['html'];

		if (!empty($error['file']))
			echo '
					<br class="clear">
					<a href="', $scripturl, '?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=file;value=', $error['file']['search'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '"><img src="', $theme['images_url'], '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '"></a>
					', $txt['file'], ': ', $error['file']['link'], '<br>
					', $txt['line'], ': ', $error['file']['line'];

		echo '
				</td>
			</tr>';
	}

	if (!empty($context['errors']))
		echo '
			<tr class="titlebg left">
				<td colspan="3">
					<div class="floatright"><input type="submit" value="', $txt['remove_selection'], '" onclick="lastClicked = \'remove_selection\';" class="delete"> <input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="lastClicked = \'remove_all\';" class="delete"></div>
					<label style="line-height: 24px"><input type="checkbox" id="check_all2" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all1.checked = this.checked;"> <strong>', $txt['check_all'], '</strong></label>
				</td>
			</tr>';
	else
		echo '
			<tr>
				<td colspan="3" class="windowbg2">', $txt['errlog_no_entries'], '</td>
			</tr>';

	echo '
			<tr>
				<td colspan="3" class="windowbg">
					&nbsp;&nbsp;', $txt['pages'], ': ', $context['page_index'], '
				</td>
			</tr>
		</table><br>';

	if ($context['sort_direction'] == 'down')
		echo '
		<input type="hidden" name="desc" value="1">';
	echo '
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>';
}

function template_show_file()
{
	global $context;

	echo '
	<table class="nodrag cp0 cs3 monospace">';

	foreach ($context['file_data']['contents'] as $index => $line)
	{
		$line_num = $index+$context['file_data']['min'];
		$is_target = $line_num == $context['file_data']['target'];
		echo '
		<tr>
			<td class="right"', $is_target ? ' style="font-weight: bold; border: 1px solid black; border-width: 1px 0 1px 1px;">==&gt;' : '>', $line_num, ':</td>
			<td class="nowrap"', $is_target ? ' style="border: 1px solid black; border-width: 1px 1px 1px 0;">' : '>', $line, '</td>
		</tr>';
	}

	echo '
	</table>';
}

?>