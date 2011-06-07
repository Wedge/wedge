<?php
/**
 * Wedge
 *
 * Displays the current mail queue.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_browse()
{
	global $context, $settings, $options, $scripturl, $txt;

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
		</div>';

	template_show_list('mail_queue');

	echo '
	</div>
	<br class="clear">';
}

?>