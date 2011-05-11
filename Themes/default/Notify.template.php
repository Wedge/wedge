<?php
// Version: 2.0 RC5; Notify

function template_main()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
		<we:cat>
			<img src="', $settings['images_url'], '/email_sm.gif">
			', $txt['notify'], '
		</we:cat>
		<div class="roundframe centertext">
			<p>', $context['notification_set'] ? $txt['notify_deactivate'] : $txt['notify_request'], '</p>
			<p>
				<strong><a href="', $scripturl, '?action=notify;sa=', $context['notification_set'] ? 'off' : 'on', ';topic=', $context['current_topic'], '.', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['yes'], '</a> - <a href="', $context['topic_href'], '">', $txt['no'], '</a></strong>
			</p>
		</div>';
}

function template_notify_board()
{
	global $context, $settings, $options, $txt, $scripturl;

	echo '
		<we:cat>
			<img src="', $settings['images_url'], '/email_sm.gif">
			', $txt['notify'], '
		</we:cat>
		<div class="roundframe centertext">
			<p>', $context['notification_set'] ? $txt['notifyboard_turnoff'] : $txt['notifyboard_turnon'], '</p>
			<p>
				<strong><a href="', $scripturl, '?action=notifyboard;sa=', $context['notification_set'] ? 'off' : 'on', ';board=', $context['current_board'], '.', $context['start'], ';', $context['session_var'], '=', $context['session_id'], '">', $txt['yes'], '</a> - <a href="', $context['board_href'], '">', $txt['no'], '</a></strong>
			</p>
		</div>';
}

?>