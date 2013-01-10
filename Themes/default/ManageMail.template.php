<?php
/**
 * Wedge
 *
 * Displays the current mail queue.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_browse()
{
	global $context, $theme, $options, $scripturl, $txt;

	echo '
	<div id="manage_mail">
		<we:cat>
			', $txt['mailqueue_stats'], '
		</we:cat>
		<div class="windowbg wrc">
			<dl class="settings">
				<dt><strong>', $txt['mailqueue_size'], '</strong></dt>
				<dd>', $context['mail_queue_size'], '</dd>
				<dt><strong>', $txt['mailqueue_oldest'], '</strong></dt>
				<dd>', $context['oldest_mail'], '</dd>
			</dl>
			<form action="<URL>?action=admin;area=mailqueue;sa=clear;', $context['session_query'], '" method="post">
				<div class="floatright">
					<input type="submit" name="delete_redirects" value="', $txt['mailqueue_clear_list'], '" class="submit" onclick="return ask(', JavaScriptEscape($txt['mailqueue_clear_list_warning']), ', e);">
				</div>
			</form>
			<br class="clear">
		</div>';

	template_show_list('mail_queue');

	echo '
	</div>
	<br class="clear">';
}
