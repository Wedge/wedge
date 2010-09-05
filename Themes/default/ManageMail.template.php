<?php
// Version: 2.0 RC3; ManageMail

function template_browse()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="manage_mail">
		<div class="cat_bar">
			<h3 class="catbg">', $txt['mailqueue_stats'], '</h3>
		</div>
		<div class="windowbg wrc">
			<div class="content">
				<dl class="settings">
					<dt><strong>', $txt['mailqueue_size'], '</strong></dt>
					<dd>', $context['mail_queue_size'], '</dd>
					<dt><strong>', $txt['mailqueue_oldest'], '</strong></dt>
					<dd>', $context['oldest_mail'], '</dd>
				</dl>
			</div>
		</div>';

	template_show_list('mail_queue');

	echo '
	</div>
	<br class="clear" />';
}

?>