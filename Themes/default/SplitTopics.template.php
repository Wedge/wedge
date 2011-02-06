<?php
// Version: 2.0 RC5; SplitTopics

function template_ask()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="split_topics">
		<form action="', $scripturl, '?action=splittopics;sa=execute;topic=', $context['current_topic'], '.0" method="post" accept-charset="UTF-8">
			<input type="hidden" name="at" value="', $context['message']['id'], '">
			<we:cat>
				', $txt['split'], '
			</we:cat>
			<div class="windowbg wrc">
				<p class="split_topics">
					<strong><label for="subname">', $txt['subject_new_topic'], '</label>:</strong>
					<input type="text" name="subname" id="subname" value="', $context['message']['subject'], '" size="25">
				</p>
				<ul class="reset split_topics">
					<li>
						<input type="radio" id="onlythis" name="step2" value="onlythis" checked> <label for="onlythis">', $txt['split_this_post'], '</label>
					</li>
					<li>
						<input type="radio" id="afterthis" name="step2" value="afterthis"> <label for="afterthis">', $txt['split_after_and_this_post'], '</label>
					</li>
					<li>
						<input type="radio" id="selective" name="step2" value="selective"> <label for="selective">', $txt['select_split_posts'], '</label>
					</li>
				</ul>
				<div class="righttext">
					<input type="submit" value="', $txt['split'], '" class="submit">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>';
}

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="split_topics">
		<we:cat>
			', $txt['split'], '
		</we:cat>
		<div class="windowbg wrc">
			<p>', $txt['split_successful'], '</p>
			<ul class="reset">
				<li>
					<a href="', $scripturl, '?board=', $context['current_board'], '.0">', $txt['message_index'], '</a>
				</li>
				<li>
					<a href="', $scripturl, '?topic=', $context['old_topic'], '.0">', $txt['origin_topic'], '</a>
				</li>
				<li>
					<a href="', $scripturl, '?topic=', $context['new_topic'], '.0">', $txt['new_topic'], '</a>
				</li>
			</ul>
		</div>
	</div>';
}

function template_select()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="split_topics">
		<form action="', $scripturl, '?action=splittopics;sa=splitSelection;board=', $context['current_board'], '.0" method="post" accept-charset="UTF-8">
			<div id="not_selected" class="floatleft">
				<we:cat>
					', $txt['split'], ' - ', $txt['select_split_posts'], '
				</we:cat>
				<div class="information">
					', $txt['please_select_split'], '
				</div>
				<div class="pagesection">
					<strong>', $txt['pages'], ':</strong> <span id="pageindex_not_selected">', $context['not_selected']['page_index'], '</span>
				</div>
				<ul id="messages_not_selected" class="split_messages smalltext reset">';

	foreach ($context['not_selected']['messages'] as $message)
		echo '
					<li id="not_selected_', $message['id'], '"><div class="windowbg', $message['alternate'] ? '2' : '', ' wrc">
						<div class="message_header">
							<a class="split_icon floatright" href="', $scripturl, '?action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=down;msg=', $message['id'], '" onclick="return select(\'down\', ', $message['id'], ');"><img src="', $settings['images_url'], '/split_select.gif" alt="-&gt;"></a>
							<strong>', $message['subject'], '</strong> ', $txt['by'], ' <strong>', $message['poster'], '</strong><br>
							<em>', $message['time'], '</em>
						</div>
						<div class="post">', $message['body'], '</div>
					</div></li>';

	echo '
					<li class="dummy"></li>
				</ul>
			</div>
			<div id="selected" class="floatright">
				<we:cat>
					', $txt['split_selected_posts'], ' (<a href="', $scripturl, '?action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=reset;msg=0" onclick="return select(\'reset\', 0);">', $txt['split_reset_selection'], '</a>)
				</we:cat>
				<div class="information">
					', $txt['split_selected_posts_desc'], '
				</div>
				<div class="pagesection">
					<strong>', $txt['pages'], ':</strong> <span id="pageindex_selected">', $context['selected']['page_index'], '</span>
				</div>
				<ul id="messages_selected" class="split_messages smalltext reset">';

	if (!empty($context['selected']['messages']))
		foreach ($context['selected']['messages'] as $message)
			echo '
					<li id="selected_', $message['id'], '"><div class="windowbg', $message['alternate'] ? '2' : '', ' wrc">
						<div class="message_header">
							<a class="split_icon floatleft" href="', $scripturl, '?action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=up;msg=', $message['id'], '" onclick="return select(\'up\', ', $message['id'], ');"><img src="', $settings['images_url'], '/split_deselect.gif" alt="&lt;-"></a>
							<strong>', $message['subject'], '</strong> ', $txt['by'], ' <strong>', $message['poster'], '</strong><br>
							<em>', $message['time'], '</em>
						</div>
						<div class="post">', $message['body'], '</div>
					</div></li>';

	echo '
					<li class="dummy"></li>
				</ul>
			</div>
			<br class="clear">
			<p>
				<input type="hidden" name="topic" value="', $context['current_topic'], '">
				<input type="hidden" name="subname" value="', $context['new_subject'], '">
				<input type="submit" value="', $txt['split'], '" class="submit">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			</p>
		</form>
	</div>
	<br class="clear">';

	add_js('
	var start = [', $context['not_selected']['start'], ', ', $context['selected']['start'], '];

	function select(direction, msg_id)
	{
		if (!can_ajax)
			return true;
		getXMLDocument(smf_prepareScriptUrl(smf_scripturl) + "action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '." + start[0] + ";start2=" + start[1] + ";move=" + direction + ";msg=" + msg_id + ";xml", onDocReceived);
		return false;
	}
	function onDocReceived(XMLDoc)
	{
		$("pageIndex", XMLDoc).each(function (i) {
			$("#pageindex_" + this.getAttribute("section")).html($(this).text());
			start[i] = this.getAttribute("startFrom");
		});

		$("change", XMLDoc).each(function () {
			var
				curId = this.getAttribute("id"),
				curSection = this.getAttribute("section"),
				curList = $("#messages_" + curSection),
				is_selected = curSection == "selected",
				sInsertBeforeId = "";

			if (this.getAttribute("curAction") === "remove")
				$("#" + curSection + "_" + curId).remove();

			// Insert a message.
			else
			{
				// Loop through the list to try and find an item to insert after.
				curList.find("li > div").each(function () {
					var p = parseInt(this.parentNode.id.substr(curSection.length + 1));
					if (p < curId)
					{
						// This would be a nice place to insert the row.
						sInsertBeforeId = "#" + this.parentNode.id;
						// We\'re done for now. Escape the loop.
						return false;
					}
				});

				// Let\'s create a nice container for the message.
				var newItem = $("<div></div>").html("\
	<div class=\\"message_header\\">\
		<a class=\\"split_icon float" + (is_selected ? "left" : "right") + "\\" href=\\"" + smf_prepareScriptUrl(smf_scripturl) + "action=splittopics;sa=selectTopics;subname=', $context['topic']['subject'], ';topic=', $context['topic']['id'], '.', $context['not_selected']['start'], ';start2=', $context['selected']['start'], ';move=" + (is_selected ? "up" : "down") + ";msg=" + curId + "\\" onclick=\\"return select(\'" + (is_selected ? "up" : "down") + "\', " + curId + ");\\">\
			<img src=\\"', $settings['images_url'], '/split_" + (is_selected ? "de" : "") + "select.gif\\" alt=\\"" + (is_selected ? "&lt;-" : "-&gt;") + "\\">\
		</a>\
		<strong>" + $("subject", this).text() + "</strong> ', $txt['by'], ' <strong>" + $("poster", this).text() + "</strong>\
		<br><em>" + $("time", this).text() + "</em>\
	</div>\
	<div class=\\"post\\">" + $("body", this).text() + "</div>");

				// So, where do we insert it?
				if (sInsertBeforeId)
					newItem.insertBefore(sInsertBeforeId);
				// By default, insert the element at the end of the list.
				else
					newItem.appendTo(curList);
				newItem.wrap("<li id=\"" + curSection + "_" + curId + "\"></li>");
			}
		});

		// After all changes, make sure the window backgrounds are still correct for both lists.
		var bAlt, fAlt = function () {
			this.className = "wrc windowbg" + (bAlt ? "2" : "");
			bAlt = !bAlt;
		};
		bAlt = false; $("#messages_selected li > div").each(fAlt);
		bAlt = false; $("#messages_not_selected li > div").each(fAlt);
	}');
}

function template_merge_done()
{
	global $context, $settings, $options, $txt, $scripturl;

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
						<a href="', $scripturl, '?board=', $context['target_board'], '.0">', $txt['message_index'], '</a>
					</li>
					<li>
						<a href="', $scripturl, '?topic=', $context['target_topic'], '.0">', $txt['new_merged_topic'], '</a>
					</li>
				</ul>
			</div>
		</div>
	<br class="clear">';
}

function template_merge()
{
	global $context, $settings, $options, $txt, $scripturl;

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
			echo '
					<dt>
						<strong>', $txt['target_board'], ':</strong>
					</dt>
					<dd>
						<form action="' . $scripturl . '?action=mergetopics;from=' . $context['origin_topic'] . ';targetboard=' . $context['target_board'] . ';board=' . $context['current_board'] . '.0" method="post" accept-charset="UTF-8">
							<input type="hidden" name="from" value="' . $context['origin_topic'] . '">
							<select name="targetboard" onchange="this.form.submit();">';
			foreach ($context['boards'] as $board)
				echo '
								<option value="', $board['id'], '"', $board['id'] == $context['target_board'] ? ' selected' : '', '>', $board['category'], ' - ', $board['name'], '</option>';
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
						<form action="', $scripturl, '?action=mergetopics;sa=options" method="post" accept-charset="UTF-8">
							<input type="hidden" name="topics[]" value="', $context['origin_topic'], '">
							<input type="text" name="topics[]">
							<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
							<input type="submit" value="', $txt['merge'], '" class="submit">
						</form>
					</dd>';

		echo '
				</dl>
			</div>
			<br>
			<we:cat>
				', $txt['target_topic'], '
			</we:cat>
			<div class="pagesection">
				<strong>', $txt['pages'], ':</strong> ', $context['page_index'], '
			</div>
			<div class="windowbg2 wrc">
				<ul class="reset merge_topics">';

		$merge_button = create_button('merge.gif', 'merge', '');

		foreach ($context['topics'] as $topic)
			echo '
					<li>
						<a href="', $scripturl, '?action=mergetopics;sa=options;board=', $context['current_board'], '.0;from=', $context['origin_topic'], ';to=', $topic['id'], ';', $context['session_var'], '=', $context['session_id'], '">', $merge_button, '</a>&nbsp;
						<a href="', $scripturl, '?topic=', $topic['id'], '.0" target="_blank" class="new_win">', $topic['subject'], '</a> ', $txt['started_by'], ' ', $topic['poster']['link'], '
					</li>';

		echo '
				</ul>
			</div>
			<div class="pagesection">
				<strong>', $txt['pages'], ':</strong> ', $context['page_index'], '
			</div>
		</div>
	<br class="clear">';
}

function template_merge_extra_options()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="merge_topics">
		<form action="', $scripturl, '?action=mergetopics;sa=execute;" method="post" accept-charset="UTF-8">
			<we:title>
				', $txt['merge_topic_list'], '
			</we:title>
			<table class="table_grid bordercolor w100 cs0">
				<thead>
					<tr class="catbg">
						<th scope="col" class="first_th center" style="width: 10px">', $txt['merge_check'], '</th>
						<th scope="col" class="left">', $txt['subject'], '</th>
						<th scope="col" class="left">', $txt['started_by'], '</th>
						<th scope="col" class="left">', $txt['last_post'], '</th>
						<th scope="col" class="last_th" style="width: 20px">' . $txt['merge_include_notifications'] . '</th>
					</tr>
				</thead>
				<tbody>';
		foreach ($context['topics'] as $topic)
			echo '
					<tr class="windowbg2">
						<td class="center">
							<input type="checkbox" name="topics[]" value="' . $topic['id'] . '" checked>
						</td>
						<td>
							<a href="' . $scripturl . '?topic=' . $topic['id'] . '.0" target="_blank" class="new_win">' . $topic['subject'] . '</a>
						</td>
						<td>
							', $topic['started']['link'], '
							<div class="smalltext">', $topic['started']['time'], '</div>
						</td>
						<td>
							' . $topic['updated']['link'] . '
							<div class="smalltext">', $topic['updated']['time'], '</div>
						</td>
						<td class="center">
							<input type="checkbox" name="notifications[]" value="' . $topic['id'] . '" checked>
						</td>
					</tr>';
		echo '
				</tbody>
			</table>
			<br>
			<div class="windowbg wrc">';

	echo '
				<fieldset id="merge_subject" class="merge_options">
					<legend>', $txt['merge_select_subject'], '</legend>
					<select name="subject" onchange="this.form.custom_subject.style.display = (this.options[this.selectedIndex].value != 0) ? \'none\': \'\' ;">';
	foreach ($context['topics'] as $topic)
		echo '
						<option value="', $topic['id'], '"' . ($topic['selected'] ? ' selected' : '') . '>', $topic['subject'], '</option>';
	echo '
						<option value="0">', $txt['merge_custom_subject'], ':</option>
					</select>
					<br><input type="text" name="custom_subject" size="60" id="custom_subject" class="custom_subject" style="display: none">
					<br>
					<label for="enforce_subject"><input type="checkbox" name="enforce_subject" id="enforce_subject" value="1"> ', $txt['merge_enforce_subject'], '</label>
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
							<input type="radio" name="board" value="' . $board['id'] . '"' . ($board['selected'] ? ' checked' : '') . '> ' . $board['name'] . '
						</li>';
		echo '
					</ul>
				</fieldset>';
	}
	if (!empty($context['polls']))
	{
		echo '
				<fieldset id="merge_poll" class="merge_options">
					<legend>' . $txt['merge_select_poll'] . '</legend>
					<ul class="reset">';
		foreach ($context['polls'] as $poll)
			echo '
						<li>
							<input type="radio" name="poll" value="' . $poll['id'] . '"' . ($poll['selected'] ? ' checked' : '') . '> ' . $poll['question'] . ' (' . $txt['topic'] . ': <a href="' . $scripturl . '?topic=' . $poll['topic']['id'] . '.0" target="_blank" class="new_win">' . $poll['topic']['subject'] . '</a>)
						</li>';
		echo '
						<li>
							<input type="radio" name="poll" value="-1"> (' . $txt['merge_no_poll'] . ')
						</li>
					</ul>
				</fieldset>';
	}
	echo '
				<input type="submit" value="' . $txt['merge'] . '" class="submit floatright">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="sa" value="execute"><br class="clear">
			</div>
		</form>
	</div>
	<br class="clear">';
}

?>