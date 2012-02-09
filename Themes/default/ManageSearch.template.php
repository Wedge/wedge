<?php
/**
 * Wedge
 *
 * Displays the configuration options for the different search backends.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_modify_weights()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=managesearch;sa=weights" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['search_weights'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt class="large_caption">
						<a href="', $scripturl, '?action=help;in=search_weight_frequency" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
						', $txt['search_weight_frequency'], ':
					</dt>
					<dd class="large_caption">
						<span class="search_weight"><input type="text" name="search_weight_frequency" id="weight1_val" value="', empty($settings['search_weight_frequency']) ? '0' : $settings['search_weight_frequency'], '" onchange="calculateNewValues()" size="3"></span>
						<span id="weight1" class="search_weight">', $context['relative_weights']['search_weight_frequency'], '%</span>
					</dd>
					<dt class="large_caption">
						<a href="', $scripturl, '?action=help;in=search_weight_age" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
						', $txt['search_weight_age'], ':
					</dt>
					<dd class="large_caption">
						<span class="search_weight"><input type="text" name="search_weight_age" id="weight2_val" value="', empty($settings['search_weight_age']) ? '0' : $settings['search_weight_age'], '" onchange="calculateNewValues()" size="3"></span>
						<span id="weight2" class="search_weight">', $context['relative_weights']['search_weight_age'], '%</span>
					</dd>
					<dt class="large_caption">
						<a href="', $scripturl, '?action=help;in=search_weight_length" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
						', $txt['search_weight_length'], ':
					</dt>
					<dd class="large_caption">
						<span class="search_weight"><input type="text" name="search_weight_length" id="weight3_val" value="', empty($settings['search_weight_length']) ? '0' : $settings['search_weight_length'], '" onchange="calculateNewValues()" size="3"></span>
						<span id="weight3" class="search_weight">', $context['relative_weights']['search_weight_length'], '%</span>
					</dd>
					<dt class="large_caption">
						<a href="', $scripturl, '?action=help;in=search_weight_subject" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
						', $txt['search_weight_subject'], ':
					</dt>
					<dd class="large_caption">
						<span class="search_weight"><input type="text" name="search_weight_subject" id="weight4_val" value="', empty($settings['search_weight_subject']) ? '0' : $settings['search_weight_subject'], '" onchange="calculateNewValues()" size="3"></span>
						<span id="weight4" class="search_weight">', $context['relative_weights']['search_weight_subject'], '%</span>
					</dd>
					<dt class="large_caption">
						<a href="', $scripturl, '?action=help;in=search_weight_first_message" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
						', $txt['search_weight_first_message'], ':
					</dt>
					<dd class="large_caption">
						<span class="search_weight"><input type="text" name="search_weight_first_message" id="weight5_val" value="', empty($settings['search_weight_first_message']) ? '0' : $settings['search_weight_first_message'], '" onchange="calculateNewValues()" size="3"></span>
						<span id="weight5" class="search_weight">', $context['relative_weights']['search_weight_first_message'], '%</span>
					</dd>
					<dt class="large_caption">
						<a href="', $scripturl, '?action=help;in=search_weight_frequency" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>
						', $txt['search_weight_pinned'], ':
					</dt>
					<dd class="large_caption">
						<span class="search_weight"><input type="text" name="search_weight_pinned" id="weight6_val" value="', empty($settings['search_weight_pinned']) ? '0' : $settings['search_weight_pinned'], '" onchange="calculateNewValues()" size="3"></span>
						<span id="weight6" class="search_weight">', $context['relative_weights']['search_weight_pinned'], '%</span>
					</dd>
					<dt class="large_caption">
						<strong>', $txt['search_weights_total'], '</strong>
					</dt>
					<dd class="large_caption">
						<span id="weighttotal" class="search_weight"><strong>', $context['relative_weights']['total'], '</strong></span>
						<span class="search_weight"><strong>100%</strong></span>
					</dd>
				</dl>
				<input type="submit" name="save" value="', $txt['search_weights_save'], '" class="save floatright">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>
	</div>
	<br class="clear">';

	add_js('
	function calculateNewValues()
	{
		for (var total = 0, i = 1; i < 7; i++)
			total += parseInt($("#weight" + i + "_val").val());

		$("#weighttotal").html(total);
		for (i = 1; i < 7; i++)
			$("#weight" + i).html((Math.round(1000 * parseInt($("#weight" + i + "_val").val()) / total) / 10) + "%");
	}');
}

function template_select_search_method()
{
	global $context, $theme, $options, $scripturl, $txt, $settings;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=managesearch;sa=method" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['search_method'], '
			</we:cat>
			<div class="information">
				<div class="smalltext" style="font-weight: normal;"><a href="', $scripturl, '?action=help;in=search_why_use_index" onclick="return reqWin(this);">', $txt['search_create_index_why'], '</a></div>
			</div>
			<div class="windowbg wrc">
				<dl class="settings">';

	if (!empty($context['table_info']))
		echo '
					<dt>
						<strong>', $txt['search_method_messages_table_space'], ':</strong>
					</dt>
					<dd>
						', $context['table_info']['data_length'], '
					</dd>
					<dt>
						<strong>', $txt['search_method_messages_index_space'], ':</strong>
					</dt>
					<dd>
						', $context['table_info']['index_length'], '
					</dd>';

	echo '
				</dl>
				', $context['double_index'] ? '<div class="information">
				' . $txt['search_double_index'] . '</div>' : '', '
				<fieldset class="search_settings floatleft">
					<legend>', $txt['search_index'], '</legend>
					<dl>
						<dt>
							<label>
								<input type="radio" name="search_index" value=""', empty($settings['search_index']) ? ' checked' : '', '>
								', $txt['search_index_none'], '
							</label>
						</dt>';

	if ($context['supports_fulltext'])
	{
		echo '
						<dt>
							<label>
								<input type="radio" name="search_index" value="fulltext"', !empty($settings['search_index']) && $settings['search_index'] == 'fulltext' ? ' checked' : '', empty($context['fulltext_index']) ? ' onclick="alert(' . JavaScriptEscape($txt['search_method_fulltext_warning']) . '); return true;"': '', '>
								', $txt['search_method_fulltext_index'], '
							</label>
						</dt>
						<dd>
							<span class="smalltext">';

	if (empty($context['fulltext_index']) && empty($context['cannot_create_fulltext']))
		echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_no_index_exists'], ' [<a href="', $scripturl, '?action=admin;area=managesearch;sa=createfulltext;', $context['session_query'], '">', $txt['search_method_fulltext_create'], '</a>]';
	elseif (empty($context['fulltext_index']) && !empty($context['cannot_create_fulltext']))
		echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_fulltext_cannot_create'];
	else
		echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_index_already_exists'], ' [<a href="', $scripturl, '?action=admin;area=managesearch;sa=removefulltext;', $context['session_query'], '">', $txt['search_method_fulltext_remove'], '</a>]<br>
								<strong>', $txt['search_index_size'], ':</strong> ', $context['table_info']['fulltext_length'];

	echo '
								</span>
						</dd>';
	}

	echo '
						<dt>
							<label>
								<input type="radio" name="search_index" value="custom"', !empty($settings['search_index']) && $settings['search_index'] == 'custom' ? ' checked' : '', $context['custom_index'] ? '' : ' onclick="alert(' . JavaScriptEscape($txt['search_index_custom_warning']) . '); return true;"', '>
								', $txt['search_index_custom'], '
							</label>
						</dt>
						<dd>
							<span class="smalltext">';

	if ($context['custom_index'])
		echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_index_already_exists'], ' [<a href="', $scripturl, '?action=admin;area=managesearch;sa=removecustom;', $context['session_query'], '">', $txt['search_index_custom_remove'], '</a>]<br>
								<strong>', $txt['search_index_size'], ':</strong> ', $context['table_info']['custom_index_length'];
	elseif ($context['partial_custom_index'])
		echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_index_partial'], ' [<a href="', $scripturl, '?action=admin;area=managesearch;sa=removecustom;', $context['session_query'], '">', $txt['search_index_custom_remove'], '</a>] [<a href="', $scripturl, '?action=admin;area=managesearch;sa=createmsgindex;resume;', $context['session_query'], '">', $txt['search_index_custom_resume'], '</a>]<br>
								<strong>', $txt['search_index_size'], ':</strong> ', $context['table_info']['custom_index_length'];
	else
		echo '
								<strong>', $txt['search_index_label'], ':</strong> ', $txt['search_method_no_index_exists'], ' [<a href="', $scripturl, '?action=admin;area=managesearch;sa=createmsgindex">', $txt['search_index_create_custom'], '</a>]';

	echo '
							</span>
						</dd>';

	foreach ($context['search_apis'] as $api)
	{
		if (empty($api['label']) || $api['has_template'])
			continue;

		echo '
						<dt>
							<input type="radio" name="search_index" value="', $api['setting_index'], '"', !empty($settings['search_index']) && $settings['search_index'] == $api['setting_index'] ? ' checked' : '', '>
							', $api['label'], '
						</dt>';

	if ($api['desc'])
		echo '
						<dd>
							<span class="smalltext">', $api['desc'], '</span>
						</dd>';
	}

	echo '
					</dl>
				</fieldset>
				<fieldset class="search_settings floatright">
				<legend>', $txt['search_method'], '</legend>
					<label><input type="checkbox" name="search_force_index" id="search_force_index_check" value="1"', empty($settings['search_force_index']) ? '' : ' checked', '> ', $txt['search_force_index'], '</label><br>
					<label><input type="checkbox" name="search_match_words" id="search_match_words_check" value="1"', empty($settings['search_match_words']) ? '' : ' checked', '> ', $txt['search_match_words'], '</label>
				</fieldset>
				<div class="clear">
					<input type="submit" name="save" value="', $txt['search_method_save'], '" class="save floatright">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</div>
			<div class="clear"></div>
		</form>
	</div>
	<br class="clear">';
}

function template_create_index()
{
	global $context, $theme, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=managesearch;sa=createmsgindex;step=1" method="post" accept-charset="UTF-8" name="create_index">
			<we:cat>
				', $txt['search_create_index'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<label for="predefine_select">', $txt['search_predefined'], ':</label>
					</dt>
					<dd>
						<select name="bytes_per_word" id="predefine_select">
							<option value="2">', $txt['search_predefined_small'], '</option>
							<option value="4" selected>', $txt['search_predefined_moderate'], '</option>
							<option value="5">', $txt['search_predefined_large'], '</option>
						</select>
					</dd>
				</dl>
				<input type="submit" name="save" value="', $txt['search_create_index_start'], '" class="save">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</div>
		</form>
	</div>
	<br class="clear">';
}

function template_create_index_progress()
{
	global $context, $theme, $options, $scripturl, $txt;

	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=managesearch;sa=createmsgindex;step=1" name="autoSubmit" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['search_create_index'], '
			</we:cat>
			<div class="windowbg wrc">
				<p>
					', $txt['search_create_index_not_ready'], '
				</p>
				<p>
					<strong>', $txt['search_create_index_progress'], ': ', $context['percentage'], '%</strong>
				</p>
				<input type="submit" name="b" value="', westr::htmlspecialchars($txt['search_create_index_continue']), '" class="submit">
			</div>
			<input type="hidden" name="step" value="', $context['step'], '">
			<input type="hidden" name="start" value="', $context['start'], '">
			<input type="hidden" name="bytes_per_word" value="', $context['index_settings']['bytes_per_word'], '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';

	add_js_inline('
	var countdown = 10;
	doAutoSubmit();

	function doAutoSubmit()
	{
		if (countdown == 0)
			document.forms.autoSubmit.submit();
		else if (countdown == -1)
			return;

		document.forms.autoSubmit.b.value = ', JavaScriptEscape($txt['search_create_index_continue']), ' + " (" + countdown + ")";
		countdown--;

		setTimeout(doAutoSubmit, 1000);
	}');
}

function template_create_index_done()
{
	global $context, $theme, $options, $scripturl, $txt;
	echo '
	<div id="admincenter">
		<we:cat>
			', $txt['search_create_index'], '
		</we:cat>
		<div class="windowbg wrc">
			<p>', $txt['search_create_index_done'], '</p>
			<p>
				<strong><a href="', $scripturl, '?action=admin;area=managesearch;sa=method">', $txt['search_create_index_done_link'], '</a></strong>
			</p>
		</div>
	</div>
	<br class="clear">';
}

// Add or edit a search engine spider.
function template_spider_edit()
{
	global $context, $theme, $options, $scripturl, $txt;
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=sengines;sa=editspiders;sid=', $context['spider']['id'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $context['page_title'], '
			</we:cat>
			<div class="information">
				', $txt['add_spider_desc'], '
			</div>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<strong>', $txt['spider_name'], ':</strong>
						<dfn>', $txt['spider_name_desc'], '</dfn>
					</dt>
					<dd>
						<input type="text" name="spider_name" value="', $context['spider']['name'], '">
					</dd>
					<dt>
						<strong>', $txt['spider_agent'], ':</strong>
						<dfn>', $txt['spider_agent_desc'], '</dfn>
					</dt>
					<dd>
						<input type="text" name="spider_agent" value="', $context['spider']['agent'], '">
					</dd>
					<dt>
						<strong>', $txt['spider_ip_info'], ':</strong>
						<dfn>', $txt['spider_ip_info_desc'], '</dfn>
					</dt>
					<dd>
						<textarea name="spider_ip" rows="4" cols="20">', $context['spider']['ip_info'], '</textarea>
					</dd>
				</dl>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" name="save" value="', $context['page_title'], '" class="save">
			</div>
		</form>
	</div>
	<br class="clear">';
}

// Show... spider... logs...
function template_show_spider_log()
{
	global $context, $txt, $theme, $scripturl;

	echo '
	<div id="admincenter">';

	// Standard fields.
	template_show_list('spider_log');

	echo '
		<br>
		<we:cat>
			', $txt['spider_log_delete'], '
		</we:cat>
		<form action="', $scripturl, '?action=admin;area=sengines;sa=logs;', $context['session_query'], '" method="post" accept-charset="UTF-8">
			<div class="windowbg wrc">
				<p>
					', $txt['spider_log_delete_older'], '
					<input type="text" name="older" id="older" value="7" size="3">
					', $txt['spider_log_delete_day'], '
				</p>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="submit" name="delete_entries" value="', $txt['spider_log_delete_submit'], '" onclick="return ($(\'#older\').val() > 0) && confirm(' . JavaScriptEscape($txt['spider_log_delete_confirm']) . ');" class="delete">
			</div>
		</form>
	</div>
	<br class="clear">';
}

?>