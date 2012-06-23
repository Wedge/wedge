<?php
/**
 * Wedge
 *
 * Displays the form and subsidiary information for posting polls.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_main()
{
	global $context, $theme, $options, $txt, $scripturl;

	// Some javascript for adding more options.
	add_js('
	var pollOptionNum = 0;

	function addPollOption()
	{
		if (pollOptionNum == 0)
		{
			for (var i = 0; i < document.forms.postmodify.elements.length; i++)
				if (document.forms.postmodify.elements[i].id.substr(0, 8) == "options-")
					pollOptionNum++;
		}
		pollOptionNum++;

		$("#pollMoreOptions").append(\'<li><label', (isset($context['poll_error']['no_question']) ? ' class="error"' : ''), '>', $txt['option'], ' \' + pollOptionNum + \': <input type="text" name="options[\' + (pollOptionNum - 1) + \']" id="options-\' + (pollOptionNum - 1) + \'" value="" size="70" maxlength="255"></label></li>\');
		return false;
	}');

	// Start the main poll form.
	echo '
	<div id="edit_poll">
		<form action="' . $scripturl . '?action=poll;sa=editpoll2', $context['is_edit'] ? '' : ';add', ';topic=' . $context['current_topic'] . '.' . $context['start'] . '" method="post" accept-charset="UTF-8" onsubmit="submitonce(); weSaveEntities(\'postmodify\', [\'question\'], \'options-\');" name="postmodify" id="postmodify">
			<we:cat>
				', $context['page_title'], '
			</we:cat>';

	if (!empty($context['poll_error']['messages']))
		echo '
			<div class="errorbox">
				<dl class="poll_error">
					<dt>
						', $context['is_edit'] ? $txt['error_while_editing_poll'] : $txt['error_while_adding_poll'], ':
					</dt>
					<dt>
						', empty($context['poll_error']['messages']) ? '' : implode('<br>', $context['poll_error']['messages']), '
					</dt>
				</dl>
			</div>';

	echo '
			<div>
				<div class="roundframe">
					<input type="hidden" name="poll" value="', $context['poll']['id'], '">
					<fieldset id="poll_main">
						<legend><span ', (isset($context['poll_error']['no_question']) ? ' class="error"' : ''), '>', $txt['poll_question'], ':</span></legend>
						<input type="text" name="question" value="', $context['poll']['question'], '" size="80" maxlength="255">
						<ul class="poll_main" id="pollMoreOptions">';

	foreach ($context['choices'] as $choice)
	{
		echo '
							<li>
								<label', (isset($context['poll_error']['poll_few']) ? ' class="error"' : ''), '>', $txt['option'], ' ', $choice['number'], ':
								<input type="text" name="options[', $choice['id'], ']" id="options-', $choice['id'], '" value="', $choice['label'], '" size="70" maxlength="255"></label>';

		// Does this option have a vote count yet, or is it new?
		if ($choice['votes'] != -1)
			echo ' (', $choice['votes'], ' ', $txt['votes'], ')';

		echo '
							</li>';
	}

	echo '
						</ul>
						<strong><a href="#" onclick="return addPollOption();">(', $txt['poll_add_option'], ')</a></strong>
					</fieldset>
					<fieldset id="poll_options">
						<legend>', $txt['poll_options'], ':</legend>
						<dl class="settings poll_options">';

	if ($context['can_moderate_poll'])
	{
		echo '
							<dt>
								<label for="poll_max_votes">', $txt['poll_max_votes'], ':</label>
							</dt>
							<dd>
								<input type="text" name="poll_max_votes" id="poll_max_votes" size="2" value="', $context['poll']['max_votes'], '">
							</dd>
							<dt>
								<label for="poll_expire">', $txt['poll_run'], ':</label><br>
								<em class="smalltext">', $txt['poll_run_limit'], '</em>
							</dt>
							<dd>
								<input type="text" name="poll_expire" id="poll_expire" size="2" value="', $context['poll']['expiration'], '" onchange="this.form.poll_hide[2].disabled = $.trim(this.value) == 0; if (this.form.poll_hide[2].checked) this.form.poll_hide[1].checked = true;" maxlength="4"> ', $txt['days_word'], '
							</dd>
							<dt>
								<label for="poll_change_vote">', $txt['poll_do_change_vote'], ':</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_change_vote" name="poll_change_vote"', !empty($context['poll']['change_vote']) ? ' checked' : '', '>
							</dd>';

		if ($context['poll']['guest_vote_allowed'])
			echo '
							<dt>
								<label for="poll_guest_vote">', $txt['poll_guest_vote'], ':</label>
							</dt>
							<dd>
								<input type="checkbox" id="poll_guest_vote" name="poll_guest_vote"', !empty($context['poll']['guest_vote']) ? ' checked' : '', '>
							</dd>';
	}

	echo '
							<dt>
								', $txt['poll_results_visibility'], ':
							</dt>
							<dd>
								<label><input type="radio" name="poll_hide" id="poll_results_anyone" value="0"', $context['poll']['hide_results'] == 0 ? ' checked' : '', '> ', $txt['poll_results_anyone'], '</label><br>
								<label><input type="radio" name="poll_hide" id="poll_results_voted" value="1"', $context['poll']['hide_results'] == 1 ? ' checked' : '', '> ', $txt['poll_results_voted'], '</label><br>
								<label><input type="radio" name="poll_hide" id="poll_results_expire" value="2"', $context['poll']['hide_results'] == 2 ? ' checked' : '', empty($context['poll']['expiration']) ? ' disabled' : '', '> ', $txt['poll_results_after'], '</label>
							</dd>
							<dt>
								', $txt['poll_voters_visibility'], ':
								<div class="smalltext">', $context['is_edit'] ? $txt['poll_voters_no_change_now'] : $txt['poll_voters_no_change_future'], ' <a href="<URL>?action=help;in=cannot_change_voter_visibility" class="help" title="', $txt['help'], '" onclick="return reqWin(this);"></a></div>
							</dt>
							<dd>
								<label><input type="radio" name="poll_voters_visible" id="poll_voters_admin" value="0"', $context['poll']['voters_visible'] == 0 ? ' checked' : '', $context['is_edit'] ? ' disabled' : '', '> ', $txt['poll_voters_visibility_admin'], '</label> <a href="<URL>?action=help;in=admins_see_votes" class="help" title="', $txt['help'], '" onclick="return reqWin(this);"></a><br>
								<label><input type="radio" name="poll_voters_visible" id="poll_voters_creator" value="1"', $context['poll']['voters_visible'] == 1 ? ' checked' : '', $context['is_edit'] ? ' disabled' : '', '> ', $txt['poll_voters_visibility_creator'], '</label><br>
								<label><input type="radio" name="poll_voters_visible" id="poll_voters_members" value="2"', $context['poll']['voters_visible'] == 2 ? ' checked' : '', $context['is_edit'] ? ' disabled' : '', '> ', $txt['poll_voters_visibility_members'], '</label><br>
								<label><input type="radio" name="poll_voters_visible" id="poll_voters_anyone" value="3"', $context['poll']['voters_visible'] == 3 ? ' checked' : '', $context['is_edit'] ? ' disabled' : '', '> ', $txt['poll_voters_visibility_anyone'], '</label>
							</dd>
						</dl>
					</fieldset>';

	// If this is an edit, we can allow them to reset the vote counts.
	if ($context['is_edit'])
		echo '
					<fieldset id="poll_reset">
						<legend>', $txt['reset_votes'], '</legend>
						<input type="checkbox" name="resetVoteCount" value="on"> ' . $txt['reset_votes_check'] . '
					</fieldset>';
	echo '
					<div class="right padding">
						<input type="submit" name="post" value="', $txt['save'], '" onclick="return submitThisOnce(this);" accesskey="s" class="save">
					</div>
				</div>
			</div>
			<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';
}

?>