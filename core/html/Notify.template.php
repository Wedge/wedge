<?php
/**
 * Displays the options for turning notifications on or off in a given topic or board.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

function template_main()
{
	global $context, $txt;

	echo '
		<we:cat>
			<img src="', ASSETS, '/email_sm.gif">
			', $txt['notify'], '
		</we:cat>
		<div class="roundframe">
			<p>', $context['notification_set'] ? $txt['notify_deactivate'] : $txt['notify_request'], '</p>
			<p>
				<strong><a href="<URL>?action=notify;sa=', $context['notification_set'] ? 'off' : 'on', ';topic=', $context['current_topic'], '.', $context['start'], ';', $context['session_query'], '">', $txt['yes'], '</a> - <a href="', $context['topic_href'], '">', $txt['no'], '</a></strong>
			</p>
		</div>';
}

function template_notify_board()
{
	global $context, $txt;

	echo '
		<we:cat>
			<img src="', ASSETS, '/email_sm.gif">
			', $txt['notify'], '
		</we:cat>
		<div class="roundframe">
			<p>', $context['notification_set'] ? $txt['notifyboard_turnoff'] : $txt['notifyboard_turnon'], '</p>
			<p>
				<strong><a href="<URL>?action=notifyboard;sa=', $context['notification_set'] ? 'off' : 'on', ';board=', $context['current_board'], '.', $context['start'], ';', $context['session_query'], '">', $txt['yes'], '</a> - <a href="', $context['board_href'], '">', $txt['no'], '</a></strong>
			</p>
		</div>';
}
