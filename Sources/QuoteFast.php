<?php
/**
 * Wedge
 *
 * This file handles getting the text from posts for quoting purposes.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * This function manages new posts being added to the current editing text box.
 *
 * Accessed via action=quotefast, this function is used from the main topic area (for quick reply), the replies part of the main reply area to get a previous post, and have it quoted correctly, to be inserted into the editor box - or it can be invoked for the Quick Modify system to return the original message.
 *
 * - This function is called using Ajax for an inline insert into the actual post editor.
 * - Loads the Post language file
 * - Identifies the boards the current user can moderate through (to ensure all the right boards are available), then loads the post details from the database.
 * - The post is passed through un_preparsecode so that it is editor-safe, then censored, and finally line breaks are fixed.
 * - Prepares the content for the XML, returns through $context.
 * - Strips nested quotes if that is what was requested.
 * - Lastly, convert the post to HTML if using the WYSIWYG editor.
 */
function QuoteFast()
{
	global $modSettings, $user_info, $txt, $settings, $context;

	loadLanguage('Post');
	loadSource('Class-Editor');
	$moderate_boards = boardsAllowedTo('moderate_board');

	$request = wesql::query('
		SELECT IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time, m.body, m.id_topic, m.subject,
			m.id_board, m.id_member, m.approved
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board AND {query_see_board})
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg = {int:id_msg}' . (isset($_REQUEST['modify']) || (!empty($moderate_boards) && $moderate_boards[0] == 0) ? '' : '
			AND (t.locked = {int:not_locked}' . (empty($moderate_boards) ? '' : ' OR b.id_board IN ({array_int:moderation_board_list})') . ')') . '
		LIMIT 1',
		array(
			'current_member' => $user_info['id'],
			'moderation_board_list' => $moderate_boards,
			'id_msg' => (int) $_REQUEST['quote'],
			'not_locked' => 0,
		)
	);
	$row = wesql::fetch_assoc($request);
	wesql::free_result($request);

	loadSubTemplate('quotefast');
	if (!empty($row))
		$can_view_post = $row['approved'] || ($row['id_member'] != 0 && $row['id_member'] == $user_info['id']) || allowedTo('approve_posts', $row['id_board']);

	if (!empty($can_view_post))
	{
		// Remove special formatting we don't want anymore.
		$row['body'] = wedit::un_preparsecode($row['body']);

		// Censor the message!
		censorText($row['body']);

		$row['body'] = preg_replace('~<br\s*/?\>~i', "\n", $row['body']);

		// Want to modify a single message by double clicking it?
		if (isset($_REQUEST['modify']))
		{
			censorText($row['subject']);

			loadSubTemplate('modifyfast');
			$context['message'] = array(
				'id' => $_REQUEST['quote'],
				'body' => $row['body'],
				'subject' => addcslashes($row['subject'], '"'),
			);

			return;
		}

		// Remove any nested quotes.
		if (!empty($modSettings['removeNestedQuotes']))
			$row['body'] = preg_replace(array('~\n?\[quote.*?\].+?\[/quote\]\n?~is', '~^\n~', '~\[/quote\]~'), '', $row['body']);

		// Make the body HTML if need be.
		if (!empty($_REQUEST['mode']))
		{
			$row['body'] = strtr($row['body'], array('&lt;' => '#smlt#', '&gt;' => '#smgt#', '&amp;' => '#smamp#'));
			$row['body'] = wedit::bbc_to_html($row['body']);
			$lb = '<br>';
		}
		else
			$lb = "\n";

		// Add a quote string on the front and end.
		$context['quote']['xml'] = '[quote author=' . $row['poster_name'] . ' link=msg=' . (int) $_REQUEST['quote'] . ' date=' . $row['poster_time'] . ']' . $lb . $row['body'] . $lb . '[/quote]';
		$context['quote']['xml'] = strtr($context['quote']['xml'], array('&nbsp;' => '&#160;', '<' => '&lt;', '>' => '&gt;'));
	}
	// !!! Needs a nicer interface.
	// In case our message has been removed in the meantime.
	elseif (isset($_REQUEST['modify']))
	{
		loadSubTemplate('modifyfast');
		$context['message'] = array(
			'id' => 0,
			'body' => '',
			'subject' => '',
		);
	}
	else
		$context['quote']['xml'] = '';
}

?>