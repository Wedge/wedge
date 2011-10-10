<?php
/**
 * Wedge
 *
 * The interfaces for sending topics to people, reporting to moderators and the custom email function.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

//------------------------------------------------------------------------------
/*	This template contains two humble blocks - main and report. main's job is
	pretty simple: it collects the information we need to actually send the topic.

	The main block gets shown from:
		'?action=emailuser;sa=sendtopic;topic=##.##'
	And should submit to:
		'?action=emailuser;sa=sendtopic;topic=' . $context['current_topic'] . '.' . $context['start']
	It should send the following fields:
		y_name: sender's name.
		y_email: sender's email.
		comment: any additional comment.
		r_name: receiver's name.
		r_email: receiver's email address.
		send: this just needs to be set, as by the submit button.
		sc: the session id, or $context['session_id'].

	The report block gets shown from:
		'?action=report;topic=##.##;msg=##'
	It should submit to:
		'?action=report;topic=' . $context['current_topic'] . '.' . $context['start']
	It only needs to send the following fields:
		comment: an additional comment to give the moderator.
		sc: the session id, or $context['session_id'].
*/

// This is where we get information about who they want to send the topic to, etc.
function template_main()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="send_topic">
		<form action="', $scripturl, '?action=emailuser;sa=sendtopic;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				<img src="', $settings['images_url'], '/email_sm.gif">', $context['page_title'], '
			</we:cat>
			<div class="windowbg2 wrc">
				<fieldset id="sender" class="send_topic">
					<dl class="settings send_topic">
						<dt>
							<label for="y_name"><strong>', $txt['sendtopic_sender_name'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" id="y_name" name="y_name" maxlength="40" value="', $context['user']['name'], '" class="w50">
						</dd>
						<dt>
							<label for="y_email"><strong>', $txt['sendtopic_sender_email'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" id="y_email" name="y_email" maxlength="50" value="', $context['user']['email'], '" class="w50">
						</dd>
						<dt>
							<label for="comment"><strong>', $txt['sendtopic_comment'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" id="comment" name="comment" maxlength="100" class="w75">
						</dd>
					</dl>
				</fieldset>
				<hr>
				<fieldset id="recipient" class="send_topic">
					<dl class="settings send_topic">
						<dt>
							<label for="r_name"><strong>', $txt['sendtopic_receiver_name'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" id="r_name" name="r_name" maxlength="40" class="w50">
						</dd>
						<dt>
							<label for="r_email"><strong>', $txt['sendtopic_receiver_email'], ':</strong></label>
						</dt>
						<dd>
							<input type="text" id="r_email" name="r_email" maxlength="50" class="w50">
						</dd>
					</dl>
				</fieldset>
				<div class="righttext">
					<input type="submit" name="send" value="', $txt['sendtopic_send'], '" class="submit">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';
}

// Send an email to a user!
function template_custom_email()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="send_topic">
		<form action="', $scripturl, '?action=emailuser;sa=email" method="post" accept-charset="UTF-8">
			<we:cat>
				<img src="', $settings['images_url'], '/email_sm.gif">', $context['page_title'], '
			</we:cat>
			<div class="windowbg wrc">
				<dl class="settings send_mail">
					<dt>
						<strong>', $txt['sendtopic_receiver_name'], ':</strong>
					</dt>
					<dd>
						', $context['recipient']['link'], '
					</dd>';

	// Can the user see the persons email?
	if ($context['can_view_receipient_email'])
		echo '
					<dt>
						<strong>', $txt['sendtopic_receiver_email'], ':</strong>
					</dt>
					<dd>
						', $context['recipient']['email_link'], '
					</dd>
				</dl>
				<hr>
				<dl class="settings send_mail">';

	// If it's a guest we need their details.
	if ($context['user']['is_guest'])
		echo '
					<dt>
						<label for="y_name"><strong>', $txt['sendtopic_sender_name'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" id="y_name" name="y_name" size="24" maxlength="40" value="', $context['user']['name'], '">
					</dd>
					<dt>
						<label for="y_email"><strong>', $txt['sendtopic_sender_email'], ':</strong></label>
						<dfn>', $txt['send_email_disclosed'], '</dfn>
					</dt>
					<dd>
						<input type="text" id="y_mail" name="y_email" size="24" maxlength="50" value="', $context['user']['email'], '">
					</dt>';
	// Otherwise show the user that we know their email.
	else
		echo '
					<dt>
						<strong>', $txt['sendtopic_sender_email'], ':</strong>
						<dfn>', $txt['send_email_disclosed'], '</dfn>
					</dt>
					<dd>
						<em>', $context['user']['email'], '</em>
					</dd>';

	echo '
					<dt>
						<label for="email_subject"><strong>', $txt['send_email_subject'], ':</strong></label>
					</dt>
					<dd>
						<input type="text" id="email_subject" name="email_subject" size="50" maxlength="100">
					</dd>
					<dt>
						<label for="email_body"><strong>', $txt['message'], ':</strong></label>
					</dt>
					<dd>
						<textarea id="email_body" name="email_body" rows="10" cols="20" style="' . ($context['browser']['is_ie8'] ? 'width: 635px; max-width: 90%; min-width: 90%' : 'width: 90%') . ';"></textarea>
					</dd>
				</dl>
				<div class="righttext">
					<input type="submit" name="send" value="', $txt['sendtopic_send'], '" class="submit">
				</div>
			</div>';

	foreach ($context['form_hidden_vars'] as $key => $value)
		echo '
			<input type="hidden" name="', $key, '" value="', $value, '">';

	echo '
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';
}

function template_report()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
	<div id="report_topic">
		<form action="', $scripturl, '?action=report;topic=', $context['current_topic'], '.0" method="post" accept-charset="UTF-8">
			<input type="hidden" name="msg" value="' . $context['message_id'] . '">
			<we:cat>
				', $txt['report_to_mod'], '
			</we:cat>
			<div class="windowbg wrc">';

	if (!empty($context['post_errors']))
	{
		echo '
				<div class="errorbox">
					<ul>';

		foreach ($context['post_errors'] as $error)
			echo '
						<li class="error">', $error, '</li>';

		echo '
					</ul>
				</div>';
	}

	echo '
				<p>', $txt['report_to_mod_func'], '</p>
				<br>
				<dl class="settings" id="report_post">';

	if ($context['user']['is_guest'])
		echo '
					<dt>
						<label for="email_address">', $txt['email'], '</label>:
					</dt>
					<dd>
						<input type="text" id="email_address" name="email" value="', $context['email_address'], '" size="25" maxlength="255">
					</dd>';

	echo '
					<dt>
						<label for="report_comment">', $txt['enter_comment'], '</label>:
					</dt>
					<dd>
						<input type="text" id="report_comment" name="comment" size="50" value="', $context['comment_body'], '" maxlength="255">
					</dd>';

	if ($context['require_verification'])
		echo '
					<dt>
						', $txt['verification'], ':
					</dt>
					<dd>
						', template_control_verification($context['visual_verification_id'], 'all'), '
					</dd>';

	echo '
				</dl>
				<div class="righttext">
					<input type="submit" name="submit" value="', $txt['rtm10'], '" style="margin-left: 1ex" class="submit">
				</div>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
		</form>
	</div>
	<br class="clear">';
}

?>