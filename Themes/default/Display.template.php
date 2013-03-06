<?php
/**
 * Wedge
 *
 * Displays a given topic, be it a blog post or forum topic.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_display_posts()
{
	global $context, $theme, $options, $txt, $settings, $board_info, $msg;

	// OK, we're going to need this!
	add_js_file('scripts/topic.js');

	// Show the topic information - icon, subject, etc.
	echo '
		<div id="forumposts"', $board_info['type'] == 'board' ? '' : ' class="blog"', '>';

	if (!we::$is_guest)
		echo '
			<form action="<URL>?action=quickmod2;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm" style="margin: 0" onsubmit="return window.oQuickModify && oQuickModify.modifySave()">';

	$ignoredMsgs = array();
	$context['is_mobile'] = SKIN_MOBILE;
	$message_skeleton = new weSkeleton('msg');

	// Get all the messages...
	while ($msg = $context['get_message']())
	{
		$context['ignoring'] = false;
		if (!$msg['can_modify'] && !$msg['has_buttons'] && !$msg['can_like'] && empty($context['liked_posts'][$msg['id']]))
			$message_skeleton->skip('msg_actionbar'); // Mamanim! Just this once!

		// Are we ignoring this message?
		if (!empty($msg['is_ignored']))
			$ignoredMsgs[] = $context['ignoring'] = $msg['id'];

		if ($context['is_mobile'])
		{
			// If we're in mobile mode, we'll move the Quote and Modify buttons to the Action menu.
			$menu = isset($context['mini_menu']['action'][$msg['id']]) ? $context['mini_menu']['action'][$msg['id']] : array();

			// Insert them after the previous message's id.
			if ($msg['can_modify'])
				array_unshift($menu, 'mo');

			if ($context['can_quote'])
				array_unshift($menu, 'qu');

			$context['mini_menu']['action'][$msg['id']] = $menu;
			$context['mini_menu_items_show']['action'] += array_flip($menu);
		}

		// And finally... Render the skeleton for this message!
		$message_skeleton->render();
	}
	unset($msg, $message_skeleton);

	if (!we::$is_guest)
		echo '
			</form>';

	echo '
		</div>';

	if ($context['can_remove_post'])
		add_js('
	new InTopicModeration({
		sClass: \'inline_mod_check\',
		sStrip: \'moderationbuttons\',
		sFormId: \'quickModForm\'' . ($context['can_restore_msg'] ? ',
		bRestore: 1' : '') . ($context['can_remove_post'] ? ',
		bRemove: 1' : '') . '
	});');

	if (!we::$is_guest)
		add_js('
	var oQuickModify = new QuickModify({
		sSubject: ' . JavaScriptEscape('<input type="text" id="qm_subject" value="%subject%" size="80" maxlength="80" tabindex="' . $context['tabindex']++ . '">') . ',
		sBody: ' . JavaScriptEscape('
			<div id="quick_edit_body_container">
				<div id="error_box" class="error"></div>
				<textarea class="editor" id="qm_post" rows="12" tabindex="' . $context['tabindex']++ . '">%body%</textarea>
				<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '">
				<input type="hidden" id="qm_msg" value="%msg_id%">
				<div class="right">
					<input type="submit" name="post" value="' . $txt['save'] . '" tabindex="' . $context['tabindex']++ . '" accesskey="s" onclick="return window.oQuickModify && oQuickModify.modifySave();" class="save">&nbsp;&nbsp;' . ($context['show_spellchecking'] ? '<input type="button" value="' . $txt['spell_check'] . '" tabindex="' . $context['tabindex']++ . '" onclick="spellCheck(\'quickModForm\', \qm_post\');" class="spell">&nbsp;&nbsp;' : '') . '<input type="submit" name="cancel" value="' . $txt['form_cancel'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifyCancel();" class="cancel">
				</div>
			</div>') . '
	});
	new IconList;');

	// Collapse any ignored messages. If a message has a 'like', at least show the action bar, in case the user
	// would like to read it anyway. (Maybe they're ignoring someone only because of their signal/noise ratio?)
	if (!empty($ignoredMsgs))
		foreach ($ignoredMsgs as $msgid)
			add_js('
	new weToggle({
		isCollapsed: true,
		aSwapContainers: [
			\'msg' . $msgid . ' .info\',
			\'msg' . $msgid . ' .inner\',
			\'msg' . $msgid . ' ' . (empty($context['liked_posts'][$msgid]) ? '.actionbar' : '.actions') . '\'
		],
		aSwapLinks: [\'msg' . $msgid . ' .ignored\']
	});');

	// Show mini-menus.
	template_mini_menu('user', 'umme');
	template_mini_menu('action', 'acme');
}

function template_topic_poll()
{
	global $theme, $options, $context, $txt, $settings;

	if (empty($context['is_poll']))
		return;

	// Build the poll moderation button array.
	$poll_buttons = array(
		'vote' => array('test' => 'allow_return_vote', 'text' => 'poll_return_vote', 'url' => '<URL>?topic=' . $context['current_topic'] . '.' . $context['start']),
		'results' => array('test' => 'show_view_results_button', 'text' => 'poll_results', 'url' => '<URL>?topic=' . $context['current_topic'] . '.' . $context['start'] . ';viewresults'),
		'change_vote' => array('test' => 'allow_change_vote', 'text' => 'poll_change_vote', 'url' => '<URL>?action=poll;sa=vote;topic=' . $context['current_topic'] . '.' . $context['start'] . ';poll=' . $context['poll']['id'] . ';' . $context['session_query']),
		'lock' => array('test' => 'allow_lock_poll', 'text' => (!$context['poll']['is_locked'] ? 'poll_lock' : 'poll_unlock'), 'url' => '<URL>?action=poll;sa=lockvoting;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_query']),
		'edit' => array('test' => 'allow_edit_poll', 'text' => 'poll_edit', 'url' => '<URL>?action=poll;sa=editpoll;topic=' . $context['current_topic'] . '.' . $context['start']),
		'remove_poll' => array('test' => 'can_remove_poll', 'text' => 'poll_remove', 'custom' => 'onclick="return ask(' . JavaScriptEscape($txt['poll_remove_warn']) . ', e);"', 'url' => '<URL>?action=poll;sa=removepoll;topic=' . $context['current_topic'] . '.' . $context['start'] . ';' . $context['session_query']),
	);

	$show_voters = ($context['poll']['show_results'] || !$context['allow_vote']) && $context['allow_poll_view'];
	echo '
		<div class="poll_moderation">', template_button_strip($poll_buttons), '
		</div>
		<we:block class="poll windowbg" header="', $txt['poll'], '" footer="', empty($context['poll']['expire_time']) ? '' :
			($context['poll']['is_expired'] ? $txt['poll_expired_on'] : $txt['poll_expires_on']) . ': ' . $context['poll']['expire_time'] . ($show_voters ? ' - ' : ''),
			$show_voters ? $txt['poll_total_voters'] . ': ' . $context['poll']['total_votes'] : '', '">
			<h4>
				<img src="', $theme['images_url'], '/topic/', $context['poll']['is_locked'] ? 'normal_poll_locked' : 'normal_poll', '.png" style="vertical-align: -4px">
				', $context['poll']['question'], '
			</h4>';

	// Are they not allowed to vote but allowed to view the options?
	if ($context['poll']['show_results'] || !$context['allow_vote'])
	{
		$bar_num = 1;
		echo '
			<dl>';

		// Show each option with its corresponding percentage bar.
		foreach ($context['poll']['options'] as $option)
		{
			echo '
				<dt', $option['voted_this'] ? ' class="voted"' : '', '>', $option['option'], '</dt>
				<dd class="bar', $bar_num++, $bar_num % 2 ? ' alt' : '', '">';

			if ($context['allow_poll_view'])
			{
				echo '
					', $option['bar_ndt'], '
					<span class="percentage', $option['voted_this'] ? ' voted' : '', '">', $option['votes'], ' (', $option['percent'], '%)</span>';

				// Showing votes to users? Means we must have some votes!
				if ($context['poll']['showing_voters'] && !empty($option['votes']))
				{
					$voters = '
					<br class="clear">';

					// There are some names to show
					if (!empty($option['voters']))
					{
						foreach ($option['voters'] as $k => $v)
							$voters .= '<a href="<URL>?action=profile;u=' . $k . '">' . $v . '</a>, ';
						$voters = substr($voters, 0, -2);

						// Any votes that we didn't count?
						if (count($option['voters']) < $option['votes'])
							$voters .= ' ' . number_context('poll_voters', $option['votes'] - count($option['voters']));

						echo $voters;
					}
					// No names but some votes? Gotta be guests
					elseif (!empty($option['votes']))
						echo number_context('poll_voters_guests_only', $option['votes']);
				}
			}

			echo '
				</dd>';
		}

		echo '
			</dl>';
	}
	// They are allowed to vote! Go to it!
	else
	{
		echo '
			<form action="<URL>?action=poll;sa=vote;topic=', $context['current_topic'], '.', $context['start'], ';poll=', $context['poll']['id'], '" method="post" accept-charset="UTF-8">';

		// Show a warning if they are allowed more than one option.
		if ($context['poll']['allowed_warning'])
			echo '
				<p class="smallpadding">', $context['poll']['allowed_warning'], '</p>';

		echo '
				<ul class="reset">';

		// Show each option with its button - a radio likely.
		foreach ($context['poll']['options'] as $option)
			echo '
					<li><label>', $option['vote_button'], ' ', $option['option'], '</label></li>';

		echo '
				</ul>
				<div class="sendpoll">
					<input type="submit" value="', $txt['poll_vote'], '">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
				</div>
			</form>';

		$values = array('admin', 'creator', 'members', 'anyone');
		echo '
			', $txt['poll_visibility_' . $values[$context['poll']['voters_visible']]];
	}

	echo '
		</we:block>';
}

function template_reagree_warning()
{
	global $txt;

	echo '
		<div class="errorbox" id="errors">
			', $txt['reagree_reply'], '
		</div>';
}

function template_quick_reply()
{
	global $options, $txt, $context, $settings;

	if (!$context['can_reply'] || empty($options['display_quick_reply']))
	{
		echo '
		<br class="clear">';
		return;
	}

	echo '
		<div id="quickreply">
			<we:cat>
				<a href="#" onclick="return window.oQuickReply && oQuickReply.swap();" onmousedown="return false;"><div id="qr_expand"', $options['display_quick_reply'] == 2 ? ' class="fold"' : '', '></div></a>
				<a href="#" onclick="return window.oQuickReply && oQuickReply.swap();" onmousedown="return false;">', $txt['quick_reply'], '</a>
			</we:cat>
			<div id="qr_options" class="roundframe', $options['display_quick_reply'] == 2 ? '' : ' hide', '">
				<p class="smalltext left">', $txt['quick_reply_desc'], '</p>', $context['is_locked'] ? '
				<p class="alert smalltext">' . $txt['quick_reply_warning'] . '</p>' : '', !empty($context['oldTopicError']) ? '
				<p class="alert smalltext">' . sprintf($txt['error_old_topic'], $settings['oldTopicDays']) . '</p>' : '', $context['can_reply_approved'] ? '' : '
				<em>' . $txt['wait_for_approval'] . '</em>', !$context['can_reply_approved'] && $context['require_verification'] ? '
				<br>' : '', '
				<form action="<URL>?board=', $context['current_board'], ';action=post2" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" onsubmit="submitonce(this); weSaveEntities(\'postmodify\', ', $context['postbox']->saveEntityFields(), ');" class="clearfix">
					<input type="hidden" name="topic" value="', $context['current_topic'], '">
					<input type="hidden" name="subject" value="', $context['response_prefix'], $context['subject'], '">
					<input type="hidden" name="icon" value="xx">
					<input type="hidden" name="from_qr" value="1">
					<input type="hidden" name="notify" value="', $context['is_marked_notify'] || !empty($options['auto_notify']) ? '1' : '0', '">
					<input type="hidden" name="not_approved" value="', !$context['can_reply_approved'], '">
					<input type="hidden" name="goback" value="', empty($options['return_to_post']) ? '0' : '1', '">
					<input type="hidden" name="last" value="', $context['topic_last_message'], '">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">';

	// Guests just need more.
	if (we::$is_guest)
			echo '
					<strong>', $txt['name'], ':</strong> <input type="text" name="guestname" value="', $context['name'], '" size="25" tabindex="', $context['tabindex']++, '" required>
					<strong>', $txt['email'], ':</strong> <input type="email" name="email" value="', $context['email'], '" size="25" tabindex="', $context['tabindex']++, '" required><br>';

	// Is visual verification enabled?
	if ($context['require_verification'])
		echo '
					<strong>', $txt['verification'], ':</strong>', template_control_verification($context['visual_verification_id'], 'quick_reply'), '<br>';

	echo '
					<div class="qr_content">
						<div id="bbcBox_message" class="hide"></div>
						<div id="smileyBox_message" class="hide"></div>',
						$context['postbox']->outputEditor(), '
					</div>
					<div class="postbuttons">',
						$context['postbox']->outputButtons(), '
					</div>
					<div class="padding">
						<input type="button" name="switch_mode" id="switch_mode" value="', $txt['switch_mode'], '" class="hide" onclick="if (window.oQuickReply) oQuickReply.switchMode();">
					</div>
				</form>
			</div>
		</div>';

	add_js('
	var oQuickReply = new QuickReply({
		bDefaultCollapsed: ', !empty($options['display_quick_reply']) && $options['display_quick_reply'] == 2 ? 'false' : 'true', ',
		sContainerId: "qr_options",
		sImageId: "qr_expand",
		sJumpAnchor: "quickreply",
		sBbcDiv: "', $context['postbox']->show_bbc ? 'bbcBox_message' : '', '",
		sSmileyDiv: "', !empty($context['postbox']->smileys['postform']) || !empty($context['postbox']->smileys['popup']) ? 'smileyBox_message' : '', '",
		sSwitchMode: "switch_mode",
		bUsingWysiwyg: ', $context['postbox']->rich_active ? 'true' : 'false', '
	});');

	if ($context['show_spellchecking'] && (empty($context['footer']) || strpos($context['footer'], '"spell_form"') === false))
	{
		$context['footer'] .= '
<form action="<URL>?action=spellcheck" method="post" accept-charset="UTF-8" name="spell_form" id="spell_form" target="spellWindow"><input type="hidden" name="spellstring" value=""></form>';
		add_js_file('scripts/spellcheck.js');
	}
}

function template_report_success()
{
	global $context, $txt;

	if ($context['report_sent'])
		echo '
		<div class="windowbg" id="profile_success">
			', $txt['report_sent'], '
		</div>';
}

function template_display_draft()
{
	global $context, $txt;

	if ($context['draft_saved'])
		echo '
	<div class="windowbg" id="profile_success">
		', str_replace('{draft_link}', '<URL>?action=profile;area=showdrafts', $txt['draft_saved']), '
	</div>';
}

function template_title_upper()
{
	global $context;

	// Show the anchor for the first message if it's new.
	if ($context['first_new_message'])
		echo '
		<a id="new"></a>';

	// Then the title and prev/next navigation.
	echo '
		<div class="posthead">', $context['prevnext_prev'], '
			<div id="top_subject">', $context['subject'], '</div>', $context['prevnext_next'], '
		</div>';
}

function template_postlist_before()
{
	global $context, $txt;

	echo '
		<div class="pagesection">',
			template_button_strip($context['nav_buttons']['normal']), '
			<nav>', $txt['pages'], ': ', $context['page_index'], $context['menu_separator'], ' &nbsp;&nbsp;<a href="#" onclick="return go_down();"><strong>', $txt['go_down'], '</strong></a></nav>
		</div>', we::is('ie6') ? '
		<div class="clear"></div>' : '';
}

function template_postlist_after()
{
	global $context, $txt;

	echo '
		<div class="pagesection">',
			template_button_strip($context['nav_buttons']['normal']), '
			<nav>', $txt['pages'], ': ', $context['page_index'], $context['menu_separator'], ' &nbsp;&nbsp;<a href="#" onclick="return go_up();"><strong>', $txt['go_up'], '</strong></a></nav>
		</div>';
}

function template_title_lower()
{
	global $context;

	// Show the prev/next navigation again, but don't show the container if they're empty.
	if (empty($context['no_prevnext']))
		echo '
		<div class="posthead">',
			$context['prevnext_prev'],
			$context['prevnext_next'], '
		</div>';
}

function template_mod_buttons()
{
	global $context;

	echo '
		<div id="moderationbuttons">', template_button_strip($context['nav_buttons']['mod'], 'left', array('id' => 'moderationbuttons_strip')), '
		</div>';
}

function template_display_whoviewing()
{
	global $context, $txt, $theme, $settings;

	echo '
	<section>
		<we:title>
			<img src="', $theme['images_url'], '/icons/online.gif" alt="', $txt['online_users'], '">', $txt['who_title'], '
		</we:title>
		<p>';

	// Show just numbers...?
	if ($settings['display_who_viewing'] == 1)
		echo count($context['view_members']), ' ', count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members'];
	// Or show the actual people viewing the topic?
	else
		echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . ((empty($context['view_num_hidden']) || $context['can_moderate_forum']) ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

	// Now show how many guests are here too.
	echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_topic'], '
		</p>
	</section>';
}

// Show statistical style information...
function template_display_statistics()
{
	global $context, $txt, $theme;

	if (!$theme['show_stats_index'])
		return;

	echo '
	<section>
		<we:title>
			<img src="', $theme['images_url'], '/icons/info.gif" alt="', $txt['topic_stats'], '" class="top" style="padding-right: 0">
			', $txt['topic_stats'], '
		</we:title>
		<p>
			', number_context('num_views', $context['num_views']), '
			<br>', number_context('num_replies', $context['num_replies']), '
		</p>
	</section>';
}

function template_display_staff()
{
	global $context, $txt;

	echo '
	<section>
		<we:title>
			', count($context['link_moderators']) == 1 ? $txt['moderator'] : $txt['moderators'], '
		</we:title>
		<p>
			', implode('<br>', $context['link_moderators']), '
		</p>
	</section>';
}
