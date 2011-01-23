<?php
// Version: 2.0 RC4; ManageScheduledTasks

// Template for listing all scheduled tasks.
function template_view_scheduled_tasks()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// We completed some tasks?
	if (!empty($context['tasks_were_run']))
		echo '
	<div id="task_completed">
		', $txt['scheduled_tasks_were_run'], '
	</div>';

	template_show_list('scheduled_tasks');
}

// A template for, you guessed it, editing a task!
function template_edit_scheduled_tasks()
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;

	// Starts off with general maintenance procedures.
	echo '
	<div id="admincenter">
		<form action="', $scripturl, '?action=admin;area=scheduledtasks;sa=taskedit;save;tid=', $context['task']['id'], '" method="post" accept-charset="UTF-8">
			<we:cat>
				', $txt['scheduled_task_edit'], '
			</we:cat>
			<div class="information">
				<em>', sprintf($txt['scheduled_task_time_offset'], $context['server_time']), ' </em>
			</div>
			<div class="windowbg wrc">
				<dl class="settings">
					<dt>
						<strong>', $txt['scheduled_tasks_name'], ':</strong>
					</dt>
					<dd>
						', $context['task']['name'], '
						<dfn>', $context['task']['desc'], '</dfn>
					</dd>
					<dt>
						<strong>', $txt['scheduled_task_edit_interval'], ':</strong>
					</dt>
					<dd>
						', $txt['scheduled_task_edit_repeat'], '
						<input type="text" name="regularity" value="', empty($context['task']['regularity']) ? 1 : $context['task']['regularity'], '" onchange="if (this.value < 1) this.value = 1;" size="2" maxlength="2">
						<select name="unit">
							<option value="0">', $txt['scheduled_task_edit_pick_unit'], '</option>
							<option value="0">---------------------</option>
							<option value="m"', empty($context['task']['unit']) || $context['task']['unit'] == 'm' ? ' selected' : '', '>', $txt['scheduled_task_reg_unit_m'], '</option>
							<option value="h"', $context['task']['unit'] == 'h' ? ' selected' : '', '>', $txt['scheduled_task_reg_unit_h'], '</option>
							<option value="d"', $context['task']['unit'] == 'd' ? ' selected' : '', '>', $txt['scheduled_task_reg_unit_d'], '</option>
							<option value="w"', $context['task']['unit'] == 'w' ? ' selected' : '', '>', $txt['scheduled_task_reg_unit_w'], '</option>
						</select>
					</dd>
					<dt>
						<strong>', $txt['scheduled_task_edit_start_time'], ':</strong>
						<dfn>', $txt['scheduled_task_edit_start_time_desc'], '</dfn>
					</dt>
					<dd>
						<input type="text" name="offset" value="', $context['task']['offset_formatted'], '" size="6" maxlength="5">
					</dd>
					<dt>
						<strong>', $txt['scheduled_tasks_enabled'], ':</strong>
					</dt>
					<dd>
						<input type="checkbox" name="enabled" id="enabled"', !$context['task']['disabled'] ? ' checked' : '', '>
					</dd>
				</dl>
				<div class="righttext">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="submit" name="save" value="', $txt['scheduled_tasks_save_changes'], '" class="save">
				</div>
			</div>
		</form>
	</div>
	<br class="clear">';
}

?>