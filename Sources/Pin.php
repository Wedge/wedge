<?php
/**
 * Handles pinning topics from the main topic view.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
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
 * - Return to the topic once done.
 */
function Pin()
{
	global $topic, $board;

	// Maybe we want to reorganize the pinned topics in a board?
	if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == 'order')
		return OrderPin();

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
	redirectexit('topic=' . $topic . '.' . $_REQUEST['start']);
}

function OrderPin()
{
	global $board, $txt, $context, $board_info;

	// !!! Does this need a new permission?
	isAllowedTo('pin_topic');

	// You can't pin a board or something!
	if (empty($board))
		fatal_lang_error('no_access', false);

	if (isset($_POST['order']) && is_array($_POST['order']))
	{
		checkSession();

		// Whatever happens, we need to get the pinned topics in this board.
		$topics = array();
		$request = wesql::query('
			SELECT id_topic, is_pinned
			FROM {db_prefix}topics AS t
			WHERE t.id_board = {int:board}
				AND t.is_pinned > 0
			ORDER BY t.is_pinned DESC, t.id_last_msg DESC',
			array(
				'board' => $board,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$topics[$row['id_topic']] = array(
				'old' => (int) $row['is_pinned'],
			);
		wesql::free_result($request);

		if (empty($topics))
			fatal_lang_error('no_pinned_order', false);

		// So, we have an ordering. Let's do something with that.
		$max = count($topics);
		foreach ($_POST['order'] as $k => $v)
			if (isset($topics[$v]))
				$topics[$v]['new'] = max($max - (int) $k, 1); // Remember, the 0th item is the first on the list, the nth item is last, and $max - n will still be 1. But even if they mess it up, don't unpin the topic.

		// Did we miss any? Or any bad data somehow?
		foreach ($topics as $id => &$info)
		{
			// Make sure we don't leave them behind here either but push them down the list. It shouldn't happen but you can never be too sure.
			if (!isset($info['new']))
				$info['new'] = 1;
			// While we're at it, is this one actually changing? Don't query it if it isn't.
			if ($info['old'] == $info['new'])
				unset ($topics[$id]);
		}

		if (!empty($topics))
			foreach ($topics as $id => $info)
				wesql::query('
					UPDATE {db_prefix}topics
					SET is_pinned = {int:new}
					WHERE id_topic = {int:topic}',
					array(
						'new' => $info['new'],
						'topic' => $id,
					)
				);

		// And we're done. Back to the board.
		redirectexit('board=' . $board);
	}
	else
	{
		$sort_methods = array(
			'subject' => 'mf.subject',
			'starter' => 'IFNULL(memf.real_name, mf.poster_name)',
			'last_poster' => 'IFNULL(meml.real_name, ml.poster_name)',
			'replies' => 't.num_replies',
			'views' => 't.num_views',
			'first_post' => 't.id_topic',
			'last_post' => 't.id_last_msg'
		);

		// So we're displaying the list of topics. Suppose we'd better get everything ready.
		$context['pinned_topics'] = array();
		$request = wesql::query('
			SELECT t.id_topic, t.is_pinned, mf.subject, ml.poster_time, mf.id_member AS starter_id, IFNULL(memf.real_name, mf.poster_name) AS starter_name,
				ml.id_member AS updated_id, IFNULL(meml.real_name, ml.poster_name) AS updated_name
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
				INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
				LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
				LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
			WHERE t.id_board = {int:board}
				AND t.is_pinned > 0
			ORDER BY t.is_pinned DESC, ' . $sort_methods[$board_info['sort_method']] . ' ' . ($board_info['sort_override'] == 'natural_desc' || $board_info['sort_override'] == 'force_desc' ? 'DESC' : ''),
			array(
				'board' => $board,
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$context['pinned_topics'][] = $row;
		wesql::fetch_assoc($request);

		if (empty($context['pinned_topics']))
			fatal_lang_error('no_pinned_order', false);

		// OK so we have some topics. Let's go set up the template and get ready to display them.
		add_css_file('mana', true);
		add_jquery_ui();
		add_css('
	#sortable { width: 98% } #sortable .floatright { margin-left: 1em; margin-right: 1em }');

		loadTemplate('Post'); // !!! Is there a better place for this?
		loadLanguage('Post');

		$context['this_url'] = '<URL>?action=pin;sa=order;board=' . $board . '.0';

		$context['page_title'] = $txt['order_pinned_topics'];
		add_linktree($txt['order_pinned_topics'], $context['this_url']);

		wetem::load('order_pinned');
	}
}
