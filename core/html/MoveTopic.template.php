<?php
/**
 * Displays the interface for moving a topic to another board.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

// Show an interface for selecting which board to move a post to.
function template_main()
{
	global $context, $txt;

	echo '
		<form action="<URL>?action=movetopic2;topic=', $context['current_topic'], '.0" method="post" accept-charset="UTF-8" onsubmit="submitonce();">
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
					<label><input type="checkbox" name="reset_subject" id="reset_subject" onclick="$(\'#subjectArea\').slideToggle();"> ', $txt['moveTopic2'], '</label><br>
					<fieldset id="subjectArea" class="hide" style="padding: .7em 1em">
						<dl class="settings">
							<dt><strong>', $txt['moveTopic3'], ':</strong></dt>
							<dd><input name="custom_subject" size="30" value="', $context['subject'], '"></dd>
						</dl>
						<label><input type="checkbox" name="enforce_subject"> ', $txt['moveTopic4'], '</label>
					</fieldset>
					<label><input type="checkbox" name="postRedirect"', $context['is_approved'] ? ' checked' : '', ' onclick="', $context['is_approved'] ? '' : 'if (this.checked && !ask(' . JavaScriptEscape($txt['move_topic_unapproved_js']) . ', e)) return false; ', '$(\'#reasonArea\').slideToggle(this.checked);"> ', $txt['moveTopic1'], '</label><br>
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
							<dt>
								', $txt['moveTopic_redirection_auto'], '
							</dt>
							<dd>
								<input type="checkbox" name="autoredirect">
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
