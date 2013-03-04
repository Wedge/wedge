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
	global $context, $theme, $options, $txt;

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

function template_email_template_list()
{
	global $txt, $context;

	if (!empty($context['was_saved']))
		echo '
		<div class="windowbg" id="profile_success">
			', $txt['changes_saved'], '
		</div>';

	echo '
		<div class="windowbg wrc">
			<strong>', $txt['admin_agreement_select_language'], ':</strong>&nbsp;
			<ul>';

	foreach ($context['languages'] as $lang)
		echo '
				<li><span class="flag_', $lang['filename'], '"></span> <a href="<URL>?action=admin;area=mailqueue;sa=templates;emaillang=', $lang['filename'], '">', $lang['name'], '</a></li>';

	echo '
			</ul>
		</div>
		<br class="clear">';

	foreach ($context['email_groups'] as $group_name => $group)
	{
		echo '
		<we:title><div class="foldable" data-item="', $group_name, '" onclick="return expandhide(this);"></div> <a href="#" onclick="return expandhide(this);">', $txt['templates_' . $group_name], '</a></we:title>
		<div class="windowbg wrc" id="email_', $group_name, '" style="display:none">
			<dl class="settings admin_permissions">';

		foreach ($group as $item)
			echo '
				<dt><a href="<URL>?action=admin;area=mailqueue;sa=templates;emaillang=english;email=', $item, '">', $item, '</a></dt>
				<dd>
					<div>', $txt['emailtemplate_' . $item]['desc'], '</div>
					<div class="roundframe smalltext">', westr::nl2br(westr::cut($txt['emailtemplate_' . $item]['body'], 100)), '</div>
				</dd>';

		echo '
			</dl>
		</div>';
	}

	add_js('
	function expandhide(obj)
	{
		var
			$obj = $(obj).parent().find(\'.foldable\'),
			item = \'#email_\' + $obj.data(\'item\');

		if ($obj.hasClass(\'fold\'))
		{
			$(item).slideUp(200);
			$obj.removeClass(\'fold\');
		}
		else
		{
			$(item).slideDown(150);
			$obj.addClass(\'fold\');
		}

		return false;
	};');
}

function template_email_edit()
{
	global $txt, $context;

	// Ye old editing formme
	echo '
	<we:title>', $txt['template_edit_template'], '</we:title>
	<div class="windowbg wrc">
		<form action="<URL>?action=admin;area=mailqueue;sa=templates" method="post">
			<dl class="settings admin_permissions">
				<dt>', $txt['template_email_desc'], '</dt>
				<dd>', $context['emailtemplate']['desc'], '</dd>
				<dt>', $txt['template_email_subject'], '</dt>
				<dd>
					<input type="text" name="subject" id="subject" value="', $context['emailtemplate']['subject'], '" size="50">
				</dd>
				<dt>', $txt['template_email_body'], '</dt>
				<dd>
				<textarea rows="8" cols="50" name="body" id="body">', $context['emailtemplate']['body'], '</textarea>
				</dd>
			</dl>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
			<input type="hidden" name="emaillang" value="', $context['emailtemplate']['lang'], '">
			<input type="hidden" name="email" value="', $context['emailtemplate']['email'], '">

			<div class="right">
				<input type="submit" name="save" class="save" value="', $txt['save'], '">
			</div>
		</form>
	</div>';

	// Now for the replacements.
	echo '
	<we:title>', $txt['template_replacements'], '</we:title>
	<p class="description">', $txt['template_replacement_desc'], '</p>
	<div class="windowbg wrc">
		<ul>';

	foreach ($context['emailtemplate']['replacement_items'] as $item)
		echo '
			<li>{', strtoupper($item), '} - ', $txt['template_repl_' . $item], '</li>';

	echo '
		</ul>
	</div>';
}
