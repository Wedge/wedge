<?php
/**
 * The interface for merging topics.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

function template_merge_done()
{
	global $context, $txt;

	echo '
		<div id="merge_topics">
			<we:cat>
				', $txt['merge'], '
			</we:cat>
			<div class="windowbg wrc">
				<p>', $txt['merge_successful'], '</p>
				<br>
				<ul class="reset">
					<li>
						<a href="<URL>?board=', $context['target_board'], '.0">', $txt['message_index'], '</a>
					</li>
					<li>
						<a href="<URL>?topic=', $context['target_topic'], '.0">', $txt['new_merged_topic'], '</a>
					</li>
				</ul>
			</div>
		</div>
	<br class="clear">';
}

function template_merge()
{
	global $context, $txt, $theme;

	echo '
		<div id="merge_topics">
			<we:cat>
				', $txt['merge'], '
			</we:cat>
			<div class="information">
				', $txt['merge_desc'], '
			</div>
			<div class="windowbg wrc">
				<dl class="settings merge_topic">
					<dt>
						<strong>', $txt['topic_to_merge'], ':</strong>
					</dt>
					<dd>
						', $context['origin_subject'], '
					</dd>';

	if (!empty($context['boards']) && count($context['boards']) > 1)
	{
		$board_list = array();
		foreach ($context['boards'] as $board)
			$board_list[$board['category']][$board['id']] = array($board['name'], $board['child_level']);

		echo '
					<dt>
						<strong>', $txt['target_board'], ':</strong>
					</dt>
					<dd>
						<form action="<URL>?topic=', $context['origin_topic'], ';action=mergetopics;targetboard=', $context['target_board'], '" method="post" accept-charset="UTF-8">
							<input type="hidden" name="from" value="', $context['origin_topic'], '">
							<select name="targetboard" onchange="this.form.submit();">';

		foreach ($board_list as $cat => $boards)
		{
			echo '
								<optgroup label="', $cat, '">';

			foreach ($boards as $board_id => $board_name)
				echo '
									<option value="', $board_id, '"', $board_id == $context['target_board'] ? ' selected' : '', '>', $board_name[1] > 0 ? str_repeat('==', $board_name[1] - 1) . '=&gt; ' : '', $board_name[0], '</option>';

			echo '
								</optgroup>';
		}

		echo '
							</select>
							<input type="submit" value="', $txt['go'], '">
						</form>
					</dd>';
	}

	echo '
				</dl>
				<hr>
				<dl class="settings merge_topic">
					<dt>
						<strong>', $txt['merge_to_topic_id'], ': </strong>
					</dt>
					<dd>
						<form action="<URL>?action=mergetopics;sa=options" method="post" accept-charset="UTF-8">
							<input type="hidden" name="topics[]" value="', $context['origin_topic'], '">
							<input name="topics[]">
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
							<input type="submit" value="', $txt['merge'], '" class="submit">
						</form>
					</dd>
				</dl>
			</div>
			<br>
			<we:cat>
				', $txt['target_topic'], '
			</we:cat>
			<div class="pagesection">
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
			</div>
			<div class="windowbg2 wrc">
				<ul class="reset merge_topics">';

	foreach ($context['topics'] as $topic)
		echo '
					<li>
						<a href="<URL>?topic=', $context['origin_topic'], ';action=mergetopics;sa=options;to=', $topic['id'], ';', $context['session_query'], '"><img src="', $theme['images_url'], '/buttons/merge.gif" alt="', $txt['merge'], '" class="middle"></a>&nbsp;
						<a href="<URL>?topic=', $topic['id'], '.0" target="_blank" class="new_win">', $topic['subject'], '</a> ', $txt['started_by'], ' ', $topic['poster']['link'], '
					</li>';

	echo '
				</ul>
			</div>
			<div class="pagesection">
				<nav>', $txt['pages'], ': ', $context['page_index'], '</nav>
			</div>
		</div>
	<br class="clear">';
}

function template_merge_extra_options()
{
	global $context, $txt;

	echo '
	<div id="merge_topics">
		<form action="<URL>?action=mergetopics;sa=execute;" method="post" accept-charset="UTF-8">
			<we:title>
				', $txt['merge_topic_list'], '
			</we:title>
			<table class="table_grid w100 cs0">
				<thead>
					<tr class="catbg">
						<th class="center" style="width: 10px">', $txt['merge_check'], '</th>
						<th class="left">', $txt['subject'], '</th>
						<th class="left">', $txt['started_by'], '</th>
						<th class="left">', $txt['last_post'], '</th>
						<th style="width: 20px">', $txt['merge_include_notifications'], '</th>
					</tr>
				</thead>
				<tbody>';

	foreach ($context['topics'] as $topic)
		echo '
					<tr class="windowbg2">
						<td class="center">
							<input type="checkbox" name="topics[]" value="', $topic['id'], '" checked>
						</td>
						<td>
							<a href="<URL>?topic=', $topic['id'], '.0" target="_blank" class="new_win">', $topic['subject'], '</a>
						</td>
						<td>
							', $topic['started']['link'], '
							<div class="smalltext">', $topic['started']['time'], '</div>
						</td>
						<td>
							', $topic['updated']['link'], '
							<div class="smalltext">', $topic['updated']['time'], '</div>
						</td>
						<td class="center">
							<input type="checkbox" name="notifications[]" value="', $topic['id'], '" checked>
						</td>
					</tr>';

	echo '
				</tbody>
			</table>
			<br>
			<div class="windowbg wrc">
				<fieldset id="merge_subject" class="merge_options">
					<legend>', $txt['merge_select_subject'], '</legend>
					<select id="subject_sel" name="subject" onchange="$(\'#custom_subject\').toggle($(this).val() == 0);">';

	foreach ($context['topics'] as $topic)
		echo '
						<option value="', $topic['id'], '"', $topic['selected'] ? ' selected' : '', '>', $topic['subject'], '</option>';

	echo '
						<option value="0">', $txt['merge_custom_subject'], ':</option>
					</select>
					<br><input name="custom_subject" size="60" id="custom_subject" class="custom_subject hide">
					<br>
					<label><input type="checkbox" name="enforce_subject" id="enforce_subject" value="1"> ', $txt['merge_enforce_subject'], '</label>
				</fieldset>';

	if (!empty($context['boards']) && count($context['boards']) > 1)
	{
		echo '
				<fieldset id="merge_board" class="merge_options">
					<legend>', $txt['merge_select_target_board'], '</legend>
					<ul class="reset">';
		foreach ($context['boards'] as $board)
			echo '
						<li>
							<input type="radio" name="board" value="', $board['id'], '"', $board['selected'] ? ' checked' : '', '> ', $board['name'], '
						</li>';
		echo '
					</ul>
				</fieldset>';
	}
	if (!empty($context['polls']))
	{
		echo '
				<fieldset id="merge_poll" class="merge_options">
					<legend>', $txt['merge_select_poll'], '</legend>
					<ul class="reset">';
		foreach ($context['polls'] as $poll)
			echo '
						<li>
							<input type="radio" name="poll" value="', $poll['id'], '"', $poll['selected'] ? ' checked' : '', '> ', $poll['question'], ' (', $txt['topic'], ': <a href="<URL>?topic=', $poll['topic']['id'], '.0" target="_blank" class="new_win">', $poll['topic']['subject'], '</a>)
						</li>';
		echo '
						<li>
							<input type="radio" name="poll" value="-1"> (', $txt['merge_no_poll'], ')
						</li>
					</ul>
				</fieldset>';
	}
	echo '
				<input type="submit" value="', $txt['merge'], '" class="submit floatright">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="sa" value="execute"><br class="clear">
			</div>
		</form>
	</div>
	<br class="clear">';
}
