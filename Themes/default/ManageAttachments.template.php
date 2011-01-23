<?php
// Version: 2.0 RC4; ManageAttachments

// Template template wraps around the simple settings page to add javascript functionality.
function template_avatar_settings_above()
{
}

function template_avatar_settings_below()
{
	add_js_inline('
	function updateFormStatus()
	{
		var external_avatar_checked = document.getElementById("avatar_download_external").checked;
		var custom_avatar_disabled = document.getElementById("custom_avatar_enabled").value == 0;

		document.getElementById("avatar_max_width_external").disabled = external_avatar_checked;
		document.getElementById("avatar_max_height_external").disabled = external_avatar_checked;
		document.getElementById("avatar_action_too_large").disabled = external_avatar_checked;
		document.getElementById("custom_avatar_dir").disabled = custom_avatar_disabled;
		document.getElementById("custom_avatar_url").disabled = custom_avatar_disabled;
	}
	updateFormStatus();');
}

function template_browse()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<div id="manage_attachments">
		<we:cat>
			', $txt['attachment_manager_browse_files'], '
		</we:cat>
		<div class="windowbg2 wrc">
			<a href="', $scripturl, '?action=admin;area=manageattachments;sa=browse">', $context['browse_type'] === 'attachments' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="&gt;"> ' : '', $txt['attachment_manager_attachments'], '</a> |
			<a href="', $scripturl, '?action=admin;area=manageattachments;sa=browse;avatars">', $context['browse_type'] === 'avatars' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="&gt;"> ' : '', $txt['attachment_manager_avatars'], '</a> |
			<a href="', $scripturl, '?action=admin;area=manageattachments;sa=browse;thumbs">', $context['browse_type'] === 'thumbs' ? '<img src="' . $settings['images_url'] . '/selected.gif" alt="&gt;"> ' : '', $txt['attachment_manager_thumbs'], '</a>
		</div>
	</div>';

	template_show_list('file_list');

	echo '
	<br class="clear">';
}

function template_maintenance()
{
	global $context, $settings, $options, $scripturl, $txt;

	$warning = JavaScriptEscape($txt['attachment_pruning_warning']);

	echo '
	<div id="manage_attachments">
		<we:cat>
			', $txt['attachment_stats'], '
		</we:cat>
		<div class="windowbg wrc">
			<dl class="settings">
				<dt><strong>', $txt['attachment_total'], ':</strong></dt><dd>', $context['num_attachments'], '</dd>
				<dt><strong>', $txt['attachment_manager_total_avatars'], ':</strong></dt><dd>', $context['num_avatars'], '</dd>
				<dt><strong>', $txt['attachmentdir_size' . ($context['attach_multiple_dirs'] ? '_current' : '')], ':</strong></dt><dd>', $context['attachment_total_size'], ' ', $txt['kilobyte'], '</dd>
				<dt><strong>', $txt['attachment_space' . ($context['attach_multiple_dirs'] ? '_current' : '')], ':</strong></dt><dd>', isset($context['attachment_space']) ? $context['attachment_space'] . ' ' . $txt['kilobyte'] : $txt['attachmentdir_size_not_set'], '</dd>
			</dl>
		</div>
		<we:cat>
			', $txt['attachment_integrity_check'], '
		</we:cat>
		<div class="windowbg wrc">
			<form action="', $scripturl, '?action=admin;area=manageattachments;sa=repair;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
				<p>', $txt['attachment_integrity_check_desc'], '</p>
				<input type="submit" name="submit" value="', $txt['attachment_check_now'], '" class="submit">
			</form>
		</div>
		<we:cat>
			', $txt['attachment_pruning'], '
		</we:cat>
		<div class="windowbg wrc">
			<form action="', $scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm(', $warning, ');" style="margin: 0 0 2ex 0;">
				', $txt['attachment_remove_old'], ' <input type="text" name="age" value="25" size="4"> ', $txt['days_word'], '<br>
				', $txt['attachment_pruning_message'], ': <input type="text" name="notice" value="', $txt['attachment_delete_admin'], '" size="40"><br>
				<input type="submit" name="submit" value="', $txt['remove'], '" class="delete">
				<input type="hidden" name="type" value="attachments">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="sa" value="byAge">
			</form>
			<hr>
			<form action="', $scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm(', $warning, ');" style="margin: 0 0 2ex 0;">
				', $txt['attachment_remove_size'], ' <input type="text" name="size" id="size" value="100" size="4"> ', $txt['kilobyte'], '<br>
				', $txt['attachment_pruning_message'], ': <input type="text" name="notice" value="', $txt['attachment_delete_admin'], '" size="40"><br>
				<input type="submit" name="submit" value="', $txt['remove'], '" class="delete">
				<input type="hidden" name="type" value="attachments">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="sa" value="bySize">
			</form>
			<hr>
			<form action="', $scripturl, '?action=admin;area=manageattachments" method="post" accept-charset="UTF-8" onsubmit="return confirm(', $warning, ');" style="margin: 0 0 2ex 0;">
				', $txt['attachment_manager_avatars_older'], ' <input type="text" name="age" value="45" size="4"> ', $txt['days_word'], '<br>
				<input type="submit" name="submit" value="', $txt['remove'], '" class="delete">
				<input type="hidden" name="type" value="avatars">
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				<input type="hidden" name="sa" value="byAge">
			</form>
		</div>
	</div>
	<br class="clear">';
}

function template_attachment_repair()
{
	global $context, $txt, $scripturl, $settings;

	// If we've completed just let them know!
	if ($context['completed'])
		echo '
	<div id="manage_attachments">
		<we:cat>
			', $txt['repair_attachments_complete'], '
		</we:cat>
		<div class="windowbg wrc">
			', $txt['repair_attachments_complete_desc'], '
		</div>
	</div>
	<br class="clear">';

	// What about if no errors were even found?
	elseif (!$context['errors_found'])
		echo '
	<div id="manage_attachments">
		<we:cat>
			', $txt['repair_attachments_complete'], '
		</we:cat>
		<div class="windowbg wrc">
			', $txt['repair_attachments_no_errors'], '
		</div>
	</div>
	<br class="clear">';

	// Otherwise, I'm sad to say, we have a problem!
	else
	{
		echo '
	<div id="manage_attachments">
		<form action="', $scripturl, '?action=admin;area=manageattachments;sa=repair;fixErrors=1;step=0;substep=0;', $context['session_var'], '=', $context['session_id'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['repair_attachments'], '
			</we:cat>
			<div class="windowbg wrc">
				<p>', $txt['repair_attachments_error_desc'], '</p>';

		// Loop through each error reporting the status
		foreach ($context['repair_errors'] as $error => $number)
		{
			if (!empty($number))
				echo '
				<input type="checkbox" name="to_fix[]" id="', $error, '" value="', $error, '">
				<label for="', $error, '">', sprintf($txt['attach_repair_' . $error], $number), '</label><br>';
		}

		echo '<br>
				<input type="submit" value="', $txt['repair_attachments_continue'], '" class="submit">
				<input type="submit" name="cancel" value="', $txt['repair_attachments_cancel'], '">
			</div>
		</form>
	</div>
	<br class="clear">';
	}
}

function template_attachment_paths()
{
	template_show_list('attach_paths');
}

?>