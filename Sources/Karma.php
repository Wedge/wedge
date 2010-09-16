<?php
/**********************************************************************************
* Karma.php                                                                       *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC3                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

/**
 * This file provides all of the handling for users changing each others' karma/reputation.
 *
 * @package wedge
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Apply a change to a user's reputation, a.k.a. karma, as the result of an 'applaud' (give karma) or 'smite' (take karma)
 *
 * - Checks if karma is disabled, and exits if so ($modSettings['karmaMode'] is 0 or empty)
 * - Checks it isn't a guest, and that the logged-in user has the permission to change karma.
 * - Session check via session identifier in URL.
 * - If the user isn't an admin, check they have sufficient posts to be able to change karma ($modSettings['karmaMinPosts'])
 * - Check the user isn't trying to change their own (and fail it if they are)
 * - Prune older items from the karma log (which sets whether you will be able to reapply karma or not), based on the number of hours in $modSettings['karmaWaitTime'].
 * - Check if administrators are also restricted to the wait time ($modSettings['karmaTimeRestrictAdmins']) or if the current user is not a global moderator, check the time of last change and store it. (i.e. if we're restricting admins and the user's a global mod or above, or the user is non privileged, load the last time)
 * - If they have not made a change within the wait log time (as above), log the change and update that user's karma.
 * - If they have previously made a change (and as above, they should not be allowed, even as an admin), check what they're doing. If it's a repeat operation, block it. If it changes the value the other way (e.g. remove applaud and add smite), allow and apply it.
 * - If we came from a topic, go back to the topic. Otherwise if we came from a PM, go back to that... otherwise we have no idea where you came from, so simply issue a small HTML page to the user that triggers a "back" in the history.
 */
function ModifyKarma()
{
	global $modSettings, $txt, $user_info, $topic, $smcFunc, $context;

	// If the mod is disabled, show an error.
	if (empty($modSettings['karmaMode']))
		fatal_lang_error('feature_disabled', true);

	// If you're a guest or can't do this, blow you off...
	is_not_guest();
	isAllowedTo('karma_edit');

	checkSession('get');

	// If you don't have enough posts, tough luck.
	// !!! Should this be dropped in favor of post group permissions?  Should this apply to the member you are smiting/applauding?
	if (!$user_info['is_admin'] && $user_info['posts'] < $modSettings['karmaMinPosts'])
		fatal_lang_error('not_enough_posts_karma', true, array($modSettings['karmaMinPosts']));

	// And you can't modify your own, punk! (use the profile if you need to.)
	if (empty($_REQUEST['uid']) || (int) $_REQUEST['uid'] == $user_info['id'])
		fatal_lang_error('cant_change_own_karma', false);

	// The user ID _must_ be a number, no matter what.
	$_REQUEST['uid'] = (int) $_REQUEST['uid'];

	// Applauding or smiting?
	$dir = $_REQUEST['sa'] != 'applaud' ? -1 : 1;

	// Delete any older items from the log. (karmaWaitTime is by hour.)
	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}log_karma
		WHERE {int:current_time} - log_time > {int:wait_time}',
		array(
			'wait_time' => (int) ($modSettings['karmaWaitTime'] * 3600),
			'current_time' => time(),
		)
	);

	// Start off with no change in karma.
	$action = 0;

	// Not an administrator... or one who is restricted as well.
	if (!empty($modSettings['karmaTimeRestrictAdmins']) || !allowedTo('moderate_forum'))
	{
		// Find out if this user has done this recently...
		$request = $smcFunc['db_query']('', '
			SELECT action
			FROM {db_prefix}log_karma
			WHERE id_target = {int:id_target}
				AND id_executor = {int:current_member}
			LIMIT 1',
			array(
				'current_member' => $user_info['id'],
				'id_target' => $_REQUEST['uid'],
			)
		);
		if ($smcFunc['db_num_rows']($request) > 0)
			list ($action) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}

	// They haven't, not before now, anyhow.
	if (empty($action) || empty($modSettings['karmaWaitTime']))
	{
		// Put it in the log.
		$smcFunc['db_insert']('replace',
				'{db_prefix}log_karma',
				array('action' => 'int', 'id_target' => 'int', 'id_executor' => 'int', 'log_time' => 'int'),
				array($dir, $_REQUEST['uid'], $user_info['id'], time()),
				array('id_target', 'id_executor')
			);

		// Change by one.
		updateMemberData($_REQUEST['uid'], array($dir == 1 ? 'karma_good' : 'karma_bad' => '+'));
	}
	else
	{
		// If you are gonna try to repeat.... don't allow it.
		if ($action == $dir)
			fatal_lang_error('karma_wait_time', false, array($modSettings['karmaWaitTime'], $txt['hours']));

		// You decided to go back on your previous choice?
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}log_karma
			SET action = {int:action}, log_time = {int:current_time}
			WHERE id_target = {int:id_target}
				AND id_executor = {int:current_member}',
			array(
				'current_member' => $user_info['id'],
				'action' => $dir,
				'current_time' => time(),
				'id_target' => $_REQUEST['uid'],
			)
		);

		// It was recently changed the OTHER way... so... reverse it!
		if ($dir == 1)
			updateMemberData($_REQUEST['uid'], array('karma_good' => '+', 'karma_bad' => '-'));
		else
			updateMemberData($_REQUEST['uid'], array('karma_bad' => '+', 'karma_good' => '-'));
	}

	// Figure out where to go back to.... the topic?
	if (!empty($topic))
		redirectexit('topic=' . $topic . '.' . $_REQUEST['start'] . '#msg' . (int) $_REQUEST['m']);
	// Hrm... maybe a personal message?
	elseif (isset($_REQUEST['f']))
		redirectexit('action=pm;f=' . $_REQUEST['f'] . ';start=' . $_REQUEST['start'] . (isset($_REQUEST['l']) ? ';l=' . (int) $_REQUEST['l'] : '') . (isset($_REQUEST['pm']) ? '#' . (int) $_REQUEST['pm'] : ''));
	// JavaScript as a last resort.
	else
	{
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<title>...</title>
		<script type="text/javascript"><!-- // --><![CDATA[
			history.go(-1);
		// ]]></script>
	</head>
	<body>&laquo;</body>
</html>';

		obExit(false);
	}
}

?>