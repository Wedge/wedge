<?php
/**
 * Displays fatal errors, the error log, and handles showing buggy lines within file context, to help with debugging.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

// Show an error message. This should have at least a back button and $context['error_message'].
function template_fatal_error()
{
	global $context, $txt;

	if (AJAX)
	{
		echo '
	<header>', $context['error_title'], '</header>
	<section class="nodrag">
		', $context['error_message'], '
	</section>';
		// Is this actually a help popup..? Add the close button.
		if (isset($txt['close_window']))
			echo '
	<footer><input type="button" class="delete" onclick="$(\'#popup\').fadeOut(function () { $(this).remove(); });" value="', $txt['close_window'], '" /></footer>';
		return;
	}

	add_css_file('pages'); // #fatal_error

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

	// Show a back button (using JavaScript.)
	if (empty($context['no_back_link']))
		echo '
	<div class="center">
		<a href="javascript:history.go(-1)">', $txt['back'], '</a>
	</div>';
}

function template_error_log()
{
	global $context, $txt;

	echo '
	<form action="<URL>?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';start=', $context['start'], $context['has_filter'] ? $context['filter']['href'] : '', '" method="post" accept-charset="UTF-8">
		<div class="clear_right">
			<we:title>
				<a href="<URL>?action=help;in=error_log" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
				', $txt['errlog'], '
			</we:title>
		</div>
		<table class="table_grid cs1" id="error_log">';

	if (empty($context['errors']))
	{
		echo '
			<tr>
				<td class="windowbg2">', $txt['errlog_no_entries'], '</td>
			</tr>
		</table><br>
	</form>';

		return;
	}

	echo '
			<tr>
				<td colspan="3" class="windowbg">
					&nbsp;&nbsp;', $txt['apply_filter_of_type'], ':';

	$error_types = array();
	foreach ($context['error_types'] as $type => $details)
		$error_types[] = ($details['is_selected'] ? '<img src="' . ASSETS . '/selected.gif"> ' : '') . '<a href="' . $details['url'] . '"' . ($details['is_selected'] ? ' style="font-weight: bold"' : '') . ' title="' . $details['description'] . '">' . $details['label'] . '</a>';

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
					<strong>', $txt['applying_filter'], ':</strong> ', $context['filter']['entity'], ' ', $context['filter']['value']['html'], ' (<a href="<URL>?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', '">', $txt['clear_filter'], '</a>)
				</td>
			</tr>';

	echo '
			<tr class="titlebg left">
				<td colspan="3">
					<div class="floatright"><input type="submit" value="', $txt['remove_selection'], '" class="delete"> <input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="return ask(', JavaScriptEscape($txt['sure_about_errorlog_remove']), ', e);" class="delete"></div>
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
				<td class="w50">
					<a href="<URL>?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=id_member;value=', $error['member']['id'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '"></a>
					<strong>', $error['member']['link'], '</strong><br>';
		if (!empty($error['member']['display_ip']))
			echo '
					<a href="<URL>?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=ip;value=', $error['member']['ip'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '"></a>
					<strong><a href="<URL>?action=trackip;searchip=', $error['member']['display_ip'], '">', $error['member']['display_ip'], '</a></strong>&nbsp;&nbsp;';

		echo '
				</td>
				<td class="w50">
					<a href="<URL>?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? '' : ';desc', $context['has_filter'] ? $context['filter']['href'] : '', '" title="', $txt['reverse_direction'], '">
						<span class="sort_', $context['sort_direction'], '" title="', $txt['reverse_direction'], '"></span>
					</a>
					', $error['time'], '<br>
					<a href="<URL>?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=error_type;value=', $error['error_type']['type'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"></a>
					', $txt['error_type'], ': ', $error['error_type']['name'], '
				</td>
			</tr>
			<tr class="windowbg', $error['alternate'] ? '2' : '', '">
				<td colspan="2" class="middle">
					<a href="<URL>?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=url;value=', $error['url']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '"></a>
					', strlen($error['url']['html']) > 80 ? '<a href="' . $error['url']['html'] . '" title="' . $error['url']['html'] . '">' . westr::cut($error['url']['html'], 80) . '</a>'
					 : '<a href="' . $error['url']['html'] . '">' . $error['url']['html'] . '</a>', '
					<br class="clear">
					<a href="<URL>?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=message;value=', $error['message']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_message'], '"></a>
					', $error['message']['html'];

		if (!empty($error['file']))
			echo '
					<br class="clear">
					<a href="<URL>?action=admin;area=logs;sa=errorlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=file;value=', $error['file']['search'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_file'], '"></a>
					', $txt['file'], ': ', $error['file']['link'], '<br>
					', $txt['line'], ': ', $error['file']['line'];

		echo '
				</td>
			</tr>';
	}

	echo '
			<tr class="titlebg left">
				<td colspan="3">
					<div class="floatright"><input type="submit" value="', $txt['remove_selection'], '" class="delete"> <input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="return ask(', JavaScriptEscape($txt['sure_about_errorlog_remove']), ', e);" class="delete"></div>
					<label style="line-height: 24px"><input type="checkbox" id="check_all2" onclick="invertAll(this, this.form, \'delete[]\'); this.form.check_all1.checked = this.checked;"> <strong>', $txt['check_all'], '</strong></label>
				</td>
			</tr>
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

function template_intrusion_log()
{
	global $context, $txt;

	echo '
	<form action="<URL>?action=admin;area=logs;sa=intrusionlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';start=', $context['start'], $context['has_filter'] ? $context['filter']['href'] : '', '" method="post" accept-charset="UTF-8">
		<div class="clear_right">
			<we:title>
				', $txt['log_intrusion'], '
			</we:title>
		</div>
		<table class="table_grid cs1" id="error_log">
			<tr>
				<td colspan="3" class="windowbg">
					&nbsp;&nbsp;', $txt['apply_filter_of_type'], ':
					<ul>';

	foreach ($context['error_types'] as $type => $details)
		echo '
						<li>', $details['is_selected'] ? '<img src="' . ASSETS . '/selected.gif"> ' : '', '<a href="', $details['url'], '"', $details['is_selected'] ? ' style="font-weight: bold"' : '', '">', $details['label'], '</a></li>';

	echo '
					</ul>
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
					<strong>', $txt['applying_filter'], ':</strong> ', $context['filter']['entity'], ' ', $context['filter']['value']['html'], ' (<a href="<URL>?action=admin;area=logs;sa=intrusionlog', $context['sort_direction'] == 'down' ? ';desc' : '', '">', $txt['clear_filter'], '</a>)
				</td>
			</tr>';

	if (!empty($context['errors']))
		echo '
			<tr class="titlebg left">
				<td colspan="3">
					<div class="floatright"><input type="submit" value="', $txt['remove_selection'], '" class="delete"> <input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="return ask(', JavaScriptEscape($txt['sure_about_errorlog_remove']), ', e);" class="delete"></div>
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
				<td class="w50">
					<a href="<URL>?action=admin;area=logs;sa=intrusionlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=id_member;value=', $error['member']['id'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_member'], '"></a>
					<strong>', $error['member']['link'], '</strong><br>';

		if (!empty($error['member']['display_ip']))
			echo '
					<a href="<URL>?action=admin;area=logs;sa=intrusionlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=ip;value=', $error['member']['ip'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_ip'], '"></a>
					<strong><a href="<URL>?action=trackip;searchip=', $error['member']['display_ip'], '">', $error['member']['display_ip'], '</a></strong>';

		echo '
				</td>
				<td class="w50">
					<a href="<URL>?action=admin;area=logs;sa=intrusionlog', $context['sort_direction'] == 'down' ? '' : ';desc', $context['has_filter'] ? $context['filter']['href'] : '', '" title="', $txt['reverse_direction'], '">
						<span class="sort_', $context['sort_direction'], '" title="', $txt['reverse_direction'], '"></span>
					</a>
					', $error['time'], '<br>
					<a href="<URL>?action=admin;area=logs;sa=intrusionlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=protocol;value=', $error['protocol']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"></a> ', $txt['request_protocol'], ': ', $error['protocol']['html'], '
				</td>
			</tr>';

	echo '
			<tr class="windowbg', $error['alternate'] ? '2' : '', '">
				<td colspan="2" class="middle">
					<a href="<URL>?action=admin;area=logs;sa=intrusionlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=http_method;value=', $error['http_method'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"></a>
					', $error['http_method'], '
					<a href="<URL>?action=admin;area=logs;sa=intrusionlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=request_uri;value=', $error['request_uri']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_url'], '"></a>
					', $error['request_uri']['html'], '<br><br>
					<a href="<URL>?action=admin;area=logs;sa=intrusionlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=error_type;value=', $error['error_type']['type'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_type'], '"></a>
					', $txt['error_type'], ': ', $error['error_type']['name'], '<br>
					<a href="<URL>?action=admin;area=logs;sa=intrusionlog', $context['sort_direction'] == 'down' ? ';desc' : '', ';filter=user_agent;value=', $error['user_agent']['href'], '" title="', $txt['apply_filter'], ': ', $txt['filter_only_ua'], '"><img src="', ASSETS, '/filter.gif" alt="', $txt['apply_filter'], ': ', $txt['filter_only_ua'], '"></a> ', $txt['user_agent'], ': ', $error['user_agent']['html'], '<br><br>
					', $txt['http_headers'], '<br>', $error['headers'], '
				</td>
			</tr>';
	}

	if (!empty($context['errors']))
		echo '
			<tr class="titlebg left">
				<td colspan="3">
					<div class="floatright"><input type="submit" value="', $txt['remove_selection'], '" class="delete"> <input type="submit" name="delall" value="', $context['has_filter'] ? $txt['remove_filtered_results'] : $txt['remove_all'], '" onclick="return ask(', JavaScriptEscape($txt['sure_about_errorlog_remove']), ', e);" class="delete"></div>
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

function template_unsupported_browser()
{
	global $txt;

	loadLanguage('Errors');

	echo '
	<div class="information">', sprintf($txt['unsupported_browser'], 'Internet Explorer ' . (we::is('ie6') ? 6 : 7)) . '</div>';
}
