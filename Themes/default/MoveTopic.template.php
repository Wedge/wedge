<?php
// Version: 2.0 RC5; MoveTopic

// Show an interface for selecting which board to move a post to.
function template_main()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="move_topic">
		<form action="', $scripturl, '?action=movetopic2;topic=', $context['current_topic'], '.0" method="post" accept-charset="UTF-8" onsubmit="submitonce();">
			<we:cat>
				', $txt['move_topic'], '
			</we:cat>
			<div class="windowbg wrc centertext">
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
					<label for="reset_subject"><input type="checkbox" name="reset_subject" id="reset_subject" onclick="$(\'#subjectArea\').toggle(this.checked);"> ', $txt['moveTopic2'], '.</label><br>
					<fieldset id="subjectArea" style="display: none;">
						<dl class="settings">
							<dt><strong>', $txt['moveTopic3'], ':</strong></dt>
							<dd><input type="text" name="custom_subject" size="30" value="', $context['subject'], '"></dd>
						</dl>
						<label for="enforce_subject"><input type="checkbox" name="enforce_subject" id="enforce_subject"> ', $txt['moveTopic4'], '.</label>
					</fieldset>
					<label for="postRedirect"><input type="checkbox" name="postRedirect" id="postRedirect"', $context['is_approved'] ? ' checked' : '', ' onclick="', $context['is_approved'] ? '' : 'if (this.checked && !confirm(' . JavaScriptEscape($txt['move_topic_unapproved_js']) . ')) return false; ', '$(\'#reasonArea\').toggle(this.checked);"> ', $txt['moveTopic1'], '.</label>
					<fieldset id="reasonArea" style="margin-top: 1ex;', $context['is_approved'] ? '' : 'display: none;', '">
						<dl class="settings">
							<dt>
								', $txt['moved_why'], '
							</dt>
							<dd>
								<textarea name="reason" rows="3" cols="40">', $txt['movetopic_default'], '</textarea>
							</dd>
						</dl>
					</fieldset>
					<div class="righttext">
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
		</form>
	</div>';
}

?>