<?php
/**
 * Manages scheduling for one-off tasks in the future.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Should be called when it is known (or at least very likely) that there are one-off tasks that were previously future dated to be carried out.
 *
 * Functions are called in strict time order, but if two tasks are scheduled to complete on the same second, the earlier created task will run first.
 */
function ImperativeTask()
{
	// Get the task list
	$tasks = array();
	$done_tasks = array(); // just in case we end up doing multiple tasks at once

	$query = wesql::query('
		SELECT id_instr, instr_time, instr_details
		FROM {db_prefix}scheduled_imperative
		WHERE instr_time <= {int:time}
		ORDER BY instr_time, id_instr',
		array(
			'time' => time(),
		)
	);
	while ($row = wesql::fetch_assoc($query))
	{
		if (!empty($row['instr_details']))
			$tasks[] = array(
				'id' => $row['id_instr'],
				'time' => $row['instr_time'],
				'details' => unserialize($row['instr_details']),
			);
	}
	wesql::free_result($query);

	foreach ($tasks as $task)
	{
		if (isset($task['details']['source']))
			loadSource($task['details']['source']);
		if (empty($task['details']['parameters']))
			$task['details']['parameters'] = array();

		$response = call_user_func_array($task['details']['function'], array($task['details']['parameters']));
		if (!empty($response))
			$done_tasks[] = $task['id'];
		else
			log_error('Imperative task ' . $task['details']['function'] . ' did not complete successfully. Task details: ' . serialize($task), 'general'); // !!! Probably for debugging but won't hurt to leave in place for now.
	}

	if (!empty($done_tasks))
		wesql::query('
			DELETE FROM {db_prefix}scheduled_imperative
			WHERE id_instr IN ({array_int:tasks})',
			array(
				'tasks' => $done_tasks,
			)
		);

	recalculateNextImperative();

	// Shall we return?
	if (!isset($_GET['imperative']))
		return true;

	exit;
}

/**
 * Recalculate when the next one-off task is to be done and store that for later, or store a very large number otherwise.
 */
function recalculateNextImperative()
{
	$query = wesql::query('
		SELECT MIN(instr_time)
		FROM {db_prefix}scheduled_imperative');
	$row = wesql::fetch_row($query);
	$new_time = !empty($row[0]) ? $row[0] : 2147483647; // end of Unix epoch in signed 32 bit.
	updateSettings(
		array(
			'next_imperative' => $new_time,
		)
	);
}

/**
 * Adds a task to the one-off function queue.
 *
 * The task array to be inserted accepts the following information keys:
 * - source (optional): string containing the name of a file in core/app to load, alternatively an array of strings listing multiple files. As per {@link loadSource()}, no .php extension or path should be provided.
 * - function (required): string detailing the function to be called to execute this task.
 * - parameters (optional): an array detailing any parameters to be sent to the relevant function. Since call_user_func_array is used, the parameters should be added in the right order for the receiving function, regardless of whether it is a hashed or indexed array.
 *
 * Note that a task should return true if it completed successfully and thus removed from the imperative queue, or false if it did not complete and more is required to be done.
 *
 * @param int $time The timestamp (server time) that a task should be carried out.
 * @param array $task An array of details for a one off task.
 *
 * @return mixed False if the task could not be added, or otherwise the (integer) identifier of the task in the database in case some logging function would like it.
 */
function addNextImperative($time, $task)
{
	global $settings;

	if (empty($task['function']) || empty($time) || $time < time())
		return false;

	wesql::insert('',
		'{db_prefix}scheduled_imperative',
		array(
			'instr_time' => 'int', 'instr_details' => 'string',
		),
		array(
			$time, serialize($task),
		)
	);
	$id = wesql::insert_id();

	// We could call recalculateNextImperative but there's no sense doing that when we can save 1+ DB queries. (Only update if it's nearer, and we already have the time so just do the update itself.)
	if ($time < $settings['next_imperative'])
		updateSettings(
			array(
				'next_imperative' => $time,
			)
		);

	return $id;
}

/**
 * Removes a topic at a time at a time scheduled in the future, such as pruning redirection topics.
 *
 * @param array $details An array containing elements 'topic' (integer of the topic to be removed), 'use_recycle' (whether the deleted topic should be removed to the recycle bin or permanetly deleted), and 'update_postcount' (whether to update the post count of the user who made the post)
 */
function imperative_removeTopic($details)
{
	if (empty($details['topic']) || !isset($details['use_recycle'], $details['update_postcount']))
		return false;

	loadSource('RemoveTopic');
	removeTopics($details['topic'], $details['update_postcount'], !$details['use_recycle']);

	return true;
}

/**
 * Removes a warning from a user's account when it has expired.
 *
 * @param array $details An array containing elements 'mem' (integer of the user in question)
 */
function imperative_removeInfraction($details)
{
	if (empty($details['mem']))
		return false;

	// The profile area does its own housekeeping. But it only applies it if the user's profile actually gets visited.
	// So we have to make sure. It's not like we just invalidate a single given variable, either, because we
	// have to figure out the complete state to include any other infractions the user already had.
	loadMemberData($details['mem']);
	loadSource('ManageInfractions');
	get_validated_infraction_log($details['mem'], false);

	return true;
}
