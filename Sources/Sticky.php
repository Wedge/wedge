<?php
/**
 * Wedge
 *
 * Handles setting topics to sticky from the main topic view.
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
 * Handles the user request to sticky or unsticky a topic from within the topic itself. Called via ?action=sticky.
 *
 * This function takes the current topic status and reverts it; so sticky topics become unstickied, and vice versa.
 * - It requires the make_sticky permission, which is not a any/own permission (so users cannot just, even accidentally, gain the power to sticky only their own topics)
 * - Session validation is done based on the URL containing the normal session identifiers.
 * - The action will be logged in the moderation log (if enabled, see {@link logAction()})
 * - Send notifications to relevant users.
 * - Return to the topic once done (into moderation mode if in wireless viewing)
 */
function Sticky()
{
	global $modSettings, $topic, $board;

	// Make sure the user can sticky it, and they are stickying *something*.
	isAllowedTo('make_sticky');

	// You can't sticky a board or something!
	if (empty($topic))
		fatal_lang_error('not_a_topic', false);

	checkSession('get');

	// We need Subs-Post.php for the sendNotifications() function.
	loadSource('Subs-Post');

	// Is this topic already stickied, or no?
	$request = wesql::query('
		SELECT is_sticky
		FROM {db_prefix}topics
		WHERE id_topic = {int:current_topic}
		LIMIT 1',
		array(
			'current_topic' => $topic,
		)
	);
	list ($is_sticky) = wesql::fetch_row($request);
	wesql::free_result($request);

	// Toggle the sticky value.... pretty simple ;).
	wesql::query('
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