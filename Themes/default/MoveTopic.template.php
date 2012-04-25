<?php
/**
 * Wedge
 *
 * Displays the interface for moving a topic to another board.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

// Show an interface for selecting which board to move a post to.
function template_main()
{
	global $context, $theme, $options, $txt, $scripturl;

	echo '
		<form action="', $scripturl, '?action=movetopic2;topic=', $context['current_topic'], '.0" method="post" accept-charset="UTF-8" onsubmit="submitonce();">
			<we:cat>
				', $txt['move_topic'], '
			</we:cat>
			<div class="windowbg wrc center">
				<div class="move_topic">
					<dl class="settings">
						<dt>
							<strong>', $txt['move_to'], ':</strong>
						</dt>
						<dd>
							<select name="toboard">';

	foreach ($context['categories'] as $category)
	{
		echo '
								<optgroup label="', $category['name'], '">';

		foreach ($category['boards'] as $board)
			echo '
									<option value="', $board['id'], '"', $board['selected'] ? ' selected' : '', $board['id'] == $context['current_board'] ? ' disabled' : '', '>', $board['child_level'] > 0 ? str_repeat('==', $board['child_level']-1) . '=&gt; ' : '', $board['name'], '</option>';
		echo '
								</optgroup>';
	}

	echo '
							</select>
						</dd>';

	// Disable the reason textarea when the postRedirect checkbox is unchecked...
	echo '
					</dl>
					<label><input type="checkbox" name="reset_subject" id="reset_subject" onclick="$(\'#subjectArea\').toggle();"> ', $txt['moveTopic2'], '.</label><br>
					<fieldset id="subjectArea" class="hide" style="padding: .7em 1em">
						<dl class="settings">
							<dt><strong>', $txt['moveTopic3'], ':</strong></dt>
							<dd><input type="text" name="custom_subject" size="30" value="', $context['subject'], '"></dd>
						</dl>
						<label><input type="checkbox" name="enforce_subject"> ', $txt['moveTopic4'], '.</label>
					</fieldset>
					<label><input type="checkbox" name="postRedirect"', $context['is_approved'] ? ' checked' : '', ' onclick="', $context['is_approved'] ? '' : 'if (this.checked && !confirm(' . JavaScriptEscape($txt['move_topic_unapproved_js']) . ')) return false; ', '$(\'#reasonArea\').toggle(this.checked);"> ', $txt['moveTopic1'], '.</label>
					<fieldset id="reasonArea" style="padding: .7em 1em; margin-top: 1ex"', $context['is_approved'] ? '' : ' class="hide"', '>
						<dl class="settings">
							<dt>
								', $txt['moved_why'], '
							</dt>
							<dd>
								<textarea name="reason" rows="3" cols="40">', $txt['movetopic_default'], '</textarea>
							</dd>
							<dt>
								', $txt['moveTopic_redirection_period'], '
							</dt>
							<dd>
								<select name="redirection_time">';

	foreach (array(1, 3, 5, 7, 10, 15, 30, 60, 90) as $day)
		echo '
									<option value="', $day, '">', number_context('moveTopic_redirection_day', $day), '</option>';

	echo '
									<option value="0">', $txt['moveTopic_redirection_perm'], '</option>
								</select>
							</dd>
						</dl>
					</fieldset>
					<label><input type="checkbox" name="sendPm" checked onclick="$(\'#pmArea\').toggle(this.checked);">', $txt['movetopic_sendpm'], '</label>
					<fieldset id="pmArea" style="padding: .7em 1em">
						<dl class="settings">
							<dt>', $txt['movetopic_sendpm_desc'], '</dt>
							<dd>
								<textarea name="pm" rows="3" cols="40">', $txt['movetopic_default_pm'], '</textarea>
							</dd>
						</dl>
					</fieldset>
					<br>
					<div class="right">
						<input type="submit" value="', $txt['move_topic'], '" onclick="return submitThisOnce(this);" accesskey="s" class="submit">
					</div>
				</div>
			</div>';

	if ($context['back_to_topic'])
		echo '
			<input type="hidden" name="goback" value="1">';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">
		</form>';
}

?>