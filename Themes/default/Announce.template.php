<?php
/**
 * Wedge
 *
 * Displays the different aspects of announcing a post, namely gathering the groups it should be sent to, as well as showing progress.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_announce()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="announcement">
		<form action="', $scripturl, '?action=announce;sa=send" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['announce_title'], '
			</we:cat>
			<div class="information">
				', $txt['announce_desc'], '
			</div>
			<div class="windowbg2 wrc">
				<p>
					', $txt['announce_this_topic'], ' <a href="', $scripturl, '?topic=', $context['current_topic'], '.0">', $context['topic_subject'], '</a>
				</p>
				<ul class="reset">';

	foreach ($context['groups'] as $group)
		echo '
					<li>
						<label><input type="checkbox" name="who[', $group['id'], ']" value="', $group['id'], '" checked> ', $group['name'], '</label> <em>(', $group['member_count'], ')</em>
					</li>';

	echo '
					<li>
						<label><input type="checkbox" onclick="invertAll(this, this.form);" checked> <em>', $txt['check_all'], '</em></label>
					</li>
				</ul>
				<div id="confirm_buttons">
					<input type="submit" value="', $txt['post'], '" class="submit">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="topic" value="', $context['current_topic'], '">
					<input type="hidden" name="move" value="', $context['move'], '">
					<input type="hidden" name="goback" value="', $context['go_back'], '">
				</div>
			</div>
		</form>
	</div>
	<br>';
}

function template_announcement_send()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<br>
	<div id="announcement">
		<form action="' . $scripturl . '?action=announce;sa=send" method="post" accept-charset="UTF-8" name="autoSubmit" id="autoSubmit">
			<div class="windowbg2 wrc">
				<p>', $txt['announce_sending'], ' <a href="', $scripturl, '?topic=', $context['current_topic'], '.0" target="_blank" class="new_win">', $context['topic_subject'], '</a></p>
				<p><strong>', $context['percentage_done'], '% ', $txt['announce_done'], '</strong></p>
				<div id="confirm_buttons">
					<input type="submit" name="b" value="', westr::htmlspecialchars($txt['announce_continue']), '" class="submit">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="topic" value="', $context['current_topic'], '">
					<input type="hidden" name="move" value="', $context['move'], '">
					<input type="hidden" name="goback" value="', $context['go_back'], '">
					<input type="hidden" name="start" value="', $context['start'], '">
					<input type="hidden" name="membergroups" value="', $context['membergroups'], '">
				</div>
			</div>
		</form>
	</div>
	<br>';

	add_js_inline('
	var countdown = 2;
	doAutoSubmit();

	function doAutoSubmit()
	{
		if (countdown == 0)
			document.forms.autoSubmit.submit();
		else if (countdown == -1)
			return;

		document.forms.autoSubmit.b.value = ', JavaScriptEscape($txt['announce_continue']), ' + " (" + countdown + ")";
		countdown--;

		setTimeout(doAutoSubmit, 1000);
	}');
}

?>