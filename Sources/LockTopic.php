<?php
/**********************************************************************************
* LockTopic.php                                                                   *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC4                                         *
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
 * This file provides the handling for locking and stickying topics from the main topic view.
 *
 * @package wedge
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Handles a topic being locked from within the topic view, accessed via ?action=lock
 *
 * This function takes the current topic status and unlocks or locks it depending on the context.
 * - If the user is locking, and they are allowed to 'lock any topics', assuming it is a moderator and thus a moderator lock (1) should be used. Otherwise check it is their topic and they are allowed to lock their own - in which case, a user lock (2) should be used.
 * - If the user is unlocking, check whether it is a moderator lock (1) or user lock (2), and whether their permissions allow them to unlock the topic (unlock-any for moderator lock, unlock-own and their own topic and user lock only for user lock)
 * - Session validation is done based on the URL containing the normal session identifiers.
 * - The action will be logged in the moderation log (if enabled, see {@link logAction()}) - provided it is a moderator lock. Users locking their own topics (user locks) are not recorded.
 * - Send notifications to relevant users.
 * - Return to the topic once done (into moderation mode if in wireless viewing)
 */
function LockTopic()
{
	global $topic, $user_info, $sourcedir, $board, $smcFunc;

	// Just quit if there's no topic to lock.
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	checkSession('get');

	// Get Subs-Post.php for sendNotifications.
	require_once($sourcedir . '/Subs-Post.php');

	// Find out who started the topic - in case User Topic Locking is enabled.
	$request = $smcFunc['db_query']('', '
		SELECT id_member_started, locked
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($starter, $locked) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Can you lock topics here, mister?
	$user_lock = !allowedTo('lock_any');
	if ($user_lock && $starter == $user_info['id'])
		isAllowedTo('lock_own');
	else
		isAllowedTo('lock_any');

	// Locking with high privileges.
	if ($locked == '0' && !$user_lock)
		$locked = '1';
	// Locking with low privileges.
	elseif ($locked == '0')
		$locked = '2';
	// Unlocking - make sure you don't unlock what you can't.
	elseif ($locked == '2' || ($locked == '1' && !$user_lock))
		$locked = '0';
	// You cannot unlock this!
	else
		fatal_lang_error('locked_by_admin', 'user');

	// Actually lock the topic in the database with the new value.
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET locked = {int:locked}
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
			'locked' => $locked,
		)
	);

	// If they are allowed a "moderator" permission, log it in the moderator log.
	if (!$user_lock)
		logAction($locked ? 'lock' : 'unlock', array('topic' => $topic, 'board' => $board));
	// Notify people that this topic has been locked?
	sendNotifications($topic, empty($locked) ? 'unlock' : 'lock');

	// Back to the topic!
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start'] . (WIRELESS ? ';moderate' : ''));
}

/**
 * Handles the user request to sticky or unsticky a topic from within the topic itself. Called via ?action=sticky.
 *
 * This function takes the current topic status and reverts it; so sticky topics become unstickied, and vice versa.
 * - It requires the make_sticky permission, which is not a any/own permission (so users cannot just, even accidentally, gain the power to sticky only their own topics)
 * - It can be disabled from the admin panel (and this is stored in $modSettings['enableStickyTopics'])
 * - Session validation is done based on the URL containing the normal session identifiers.
 * - The action will be logged in the moderation log (if enabled, see {@link logAction()})
 * - Send notifications to relevant users.
 * - Return to the topic once done (into moderation mode if in wireless viewing)
 */
function Sticky()
{
	global $modSettings, $topic, $board, $sourcedir, $smcFunc;

	// Make sure the user can sticky it, and they are stickying *something*.
	isAllowedTo('make_sticky');

	// You shouldn't be able to (un)sticky a topic if the setting is disabled.
	if (empty($modSettings['enableStickyTopics']))
		fatal_lang_error('cannot_make_sticky', false);

	// You can't sticky a board or something!
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	checkSession('get');

	// We need Subs-Post.php for the sendNotifications() function.
	require_once($sourcedir . '/Subs-Post.php');

	// Is this topic already stickied, or no?
	$request = $smcFunc['db_query']('', '
		SELECT is_sticky
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($is_sticky) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Toggle the sticky value.... pretty simple ;).
	$smcFunc['db_query']('', '
		UPDATE {db_prefix}topics
		SET is_sticky = {int:is_sticky}
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
			'is_sticky' => empty($is_sticky) ? 1 : 0,
		)
	);

	// Log this sticky action - always a moderator thing.
	logAction(empty($is_sticky) ? 'sticky' : 'unsticky', array('topic' => $topic, 'board' => $board));
	// Notify people that this topic has been stickied?
	if (empty($is_sticky))
		sendNotifications($topic, 'sticky');

	// Take them back to the now stickied topic.
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start'] . (WIRELESS ? ';moderate' : ''));
}

?>