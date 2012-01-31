<?php
/**
 * Wedge
 *
 * Handles pinning topics from the main topic view.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Handles the user request to pin or unpin a topic from within the topic itself. Called via ?action=pin.
 *
 * This function takes the current topic status and reverts it; so pinned topics become unpinned, and vice versa.
 * - It requires the pin_topic permission, which is not a any/own permission (so users cannot just, even accidentally, gain the power to pin only their own topics)
 * - Session validation is done based on the URL containing the normal session identifiers.
 * - The action will be logged in the moderation log (if enabled, see {@link logAction()})
 * - Send notifications to relevant users.
 * - Return to the topic once done (into moderation mode if in wireless viewing)
 */
function Pin()
{
	global $modSettings, $topic, $board;

	// Make sure the user can pin it, and they are pinning *something*.
	isAllowedTo('pin_topic');

	// You can't pin a board or something!
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	checkSession('get');

	// We need Subs-Post.php for the sendNotifications() function.
	loadSource('Subs-Post');

	// Is this topic already pinned, or no?
	$request = wesql::query('
		SELECT is_pinned
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($is_pinned) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Toggle the pin value.... pretty simple ;)
	wesql::query('
		UPDATE {db_prefix}topics
		SET is_pinned = {int:is_pinned}
		WHERE id_topic = {int:current_topic}',
		array(
			'current_topic' => $topic,
			'is_pinned' => empty($is_pinned) ? 1 : 0,
		)
	);

	// Log this pin action - always a moderator thing.
	logAction(empty($is_pinned) ? 'pin' : 'unpin', array('topic' => $topic, 'board' => $board));

	// Notify people that this topic has been pinned or unpinned.
	sendNotifications($topic, 'pin');

	// Take them back to the topic.
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start'] . (WIRELESS ? ';moderate' : ''));
}

?>