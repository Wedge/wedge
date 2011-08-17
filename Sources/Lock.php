<?php
/**
 * Wedge
 *
 * Handles locking and unlocking topics from the main topic view.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
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
function Lock()
{
	global $topic, $user_info, $board;

	// Just quit if there's no topic to lock.
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	checkSession('get');

	// Get Subs-Post.php for sendNotifications.
	loadSource('Subs-Post');

	// Find out who started the topic - in case User Topic Locking is enabled.
	$request = wesql::query('
		SELECT id_member_started, locked
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($starter, $locked) = wesql::fetch_row($request);
	wesql::free_result($request);

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
	wesql::query('
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

?>