<?php
/**
 * Displays a given topic, be it a blog post or forum topic.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

function template_display_posts_before()
{
	global $context, $board_info, $footer_coding;
	static $done = false;

	if ($done)
		return;
	$done = true;

	if (INFINITE)
	{
		$footer_coding = true;
		$context['header_css'] = '';
		$context['footer_js_inline'] = '';
		$context['footer_js'] = '';
	}
	else
	{
		// OK, we're going to need this!
		add_js_file('topic.js');

		// Show the topic information - icon, subject, etc.
		echo '
		<div id="forumposts"', $board_info['type'] == 'forum' ? '' : ' class="blog"', '>';

		if (we::$is_member)
			echo '
			<form action="<URL>?action=quickmod2;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm">';
	}
}

function template_display_posts($is_blog = false)
{
	global $ignoredMsgs, $context, $msg;

	$ignoredMsgs = array();
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

		if (SKIN_MOBILE)
		{
			// If we're in mobile mode, we'll move the Quote and Modify buttons to the Action menu.
			$menu = isset($context['mini_menu']['action'][$msg['id']]) ? $context['mini_menu']['action'][$msg['id']] : array();

			// Insert them after the previous message's id.
			if ($msg['can_modify'])
				array_unshift($menu, 'mo');

			if ($context['can_quote'])
				array_unshift($menu, 'qu');

			if (!empty($menu))
			{
				$context['mini_menu']['action'][$msg['id']] = $menu;
				$context['mini_menu_items_show']['action'] += array_flip($menu);
			}
		}

		// And finally... Render the skeleton for this message!
		$message_skeleton->render();

		// Called for a blog post..? Then it should be unique.
		if ($is_blog)
			break;
	}
}

function template_display_posts_after($is_blog = false)
{
	global $ignoredMsgs, $context;

	if ($is_blog)
		return;
	$done = true;

	if (!INFINITE)
	{
		if (we::$is_member)
			echo '
			</form>';

		echo '
		</div>';
	}

	if ($context['can_remove_post'])
		add_js('
	new InTopicModeration({
		sClass: \'inline_mod_check\',
		sStrip: \'modbuttons\',
		sFormId: \'quickModForm\'' . ($context['can_restore_msg'] ? ',
		bRestore: 1' : '') . ($context['can_remove_post'] ? ',
		bRemove: 1' : '') . '
	});');

	if (we::$is_member)
	{
		add_js('
	new QuickEdit(' . $context['tabindex'] . ');');
		$context['tabindex'] += 4;
	}

	// Show mini-menus.
	template_mini_menu('user', 'umme');
	template_mini_menu('action', 'acme');

	// Collapse any ignored messages. If a message has a 'like', at least show the action bar, in case the user
	// would like to read it anyway. (Maybe they're ignoring someone only because of their signal/noise ratio?)
	if (!empty($ignoredMsgs))
		foreach ($ignoredMsgs as $msgid)
			add_js('
	new weToggle({
		isCollapsed: true,
		aSwapContainers: [
			"msg' . $msgid . ' .info:first",
			"msg' . $msgid . ' .inner:first",
			"msg' . $msgid . ' ' . (empty($context['liked_posts'][$msgid]) ? '.actionbar' : '.actions') . ':first",
			"msg' . $msgid . ' .attachments:first"
		],
		aSwapLinks: ["msg' . $msgid . ' .ignored:first"]
	});');

	// We need to show JS and CSS in the same block, as we're not getting headers and all. We're only keeping what matters.
	if (INFINITE)
	{
		if (!empty($context['header_css']))
			echo '<style>', $context['header_css'], '</style>';

		template_insert_javascript();

		$context['header_css'] = '';
		$context['footer_js_inline'] = '';
		$context['footer_js'] = '';
	}
}

function template_topic_poll_before()
{
	global $context, $txt;

	$show_voters = ($context['poll']['show_results'] || !$context['allow_vote']) && $context['allow_poll_view'];
	echo '
		<div class="poll_moderation">', template_button_strip($context['nav_buttons']['poll']), '
		</div>
		<we:block class="poll windowbg" header="', $txt['poll'], '" footer="htmlsafe::', westr::safe((empty($context['poll']['expire_time']) ? '' :
			($context['poll']['is_expired'] ? $txt['poll_expired_on'] : $txt['poll_expires_on']) . ': ' . $context['poll']['expire_time'] . ($show_voters ? ' - ' : ''))
			. ($show_voters ? $txt['poll_total_voters'] . ': ' . $context['poll']['total_votes'] : '')), '">
			<h4>
				<img src="', ASSETS, '/topic/', $context['poll']['is_locked'] ? 'normal_poll_locked' : 'normal_poll', '.png" style="vertical-align: -4px">
				', $context['poll']['question'], '
			</h4>';
}

function template_topic_poll_results()
{
	global $context;

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

function template_topic_poll_vote()
{
	global $context, $txt;

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

function template_topic_poll_after()
{
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
			<div id="qr_options" class="roundframe', $options['display_quick_reply'] == 2 ? '' : ' hide', '">', $context['is_locked'] ? '
				<p class="alert smalltext">' . $txt['quick_reply_warning'] . '</p>' : '', !empty($context['oldTopicError']) ? '
				<p class="alert smalltext">' . sprintf($txt['error_old_topic'], $settings['oldTopicDays']) . '</p>' : '', empty($context['post_moderated']) ? '' : '
				<em>' . $txt['wait_for_approval'] . '</em>', empty($context['post_moderated']) && $context['require_verification'] ? '
				<br>' : '', '
				<form action="<URL>?board=', $context['current_board'], ';action=post2" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" onsubmit="submitonce(this); weSaveEntities(\'postmodify\', ', $context['postbox']->saveEntityFields(), ');" class="clearfix">
					<input type="hidden" name="topic" value="', $context['current_topic'], '">
					<input type="hidden" name="subject" value="', $context['response_prefix'], $context['subject'], '">
					<input type="hidden" name="icon" value="xx">
					<input type="hidden" name="from_qr" value="1">
					<input type="hidden" name="notify" value="', $context['is_marked_notify'] || !empty($options['auto_notify']) ? '1' : '0', '">
					<input type="hidden" name="not_approved" value="', !empty($context['post_moderated']) ? 1 : 0, '">
					<input type="hidden" name="goback" value="', empty($options['return_to_post']) ? 0 : 1, '">
					<input type="hidden" name="last" value="', $context['topic_last_message'], '">
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
					<input type="hidden" name="seqnum" value="', $context['form_sequence_number'], '">';

	// Guests just need more.
	if (we::$is_guest)
			echo '
					<strong>', $txt['name'], ':</strong> <input name="guestname" value="', $context['name'], '" size="25" tabindex="', $context['tabindex']++, '" required>
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
			<nav>', $txt['pages'], ': ', $context['page_index'], $context['page_separator'], '<a href="#" class="updown" onclick="return go_down();">', $txt['go_down'], '</a></nav>
		</div>', we::is('ie6') ? '
		<div class="clear"></div>' : '';
}

function template_postlist_after()
{
	global $context, $txt;

	echo '
		<div class="pagesection">',
			template_button_strip($context['nav_buttons']['normal']), '
			<nav>', $txt['pages'], ': ', $context['page_index'], $context['page_separator'], '<a href="#" class="updown" onclick="return go_up();">', $txt['go_up'], '</a></nav>
		</div>';
}

// A simplified version; really, we only need the new page index.
function template_postlist_infinite_after()
{
	global $context;

	echo '<nav id="pinf">', $context['page_index'], '</nav>';
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
		<div id="modbuttons">', template_button_strip($context['nav_buttons']['mod'], 'left'), '
		</div>';
}

function template_display_whoviewing()
{
	global $context, $txt, $settings;

	echo '
	<section>
		<we:title>
			<img src="', ASSETS, '/icons/online.gif" alt="', $txt['online_users'], '">', $txt['who_title'], '
		</we:title>
		<p class="onlineinfo">';

	// Show just numbers...?
	if ($settings['display_who_viewing'] == 1)
		echo count($context['view_members']), ' ', count($context['view_members']) == 1 ? $txt['who_member'] : $txt['members'];
	// Or show the actual people viewing the topic?
	else
		echo empty($context['view_members_list']) ? '0 ' . $txt['members'] : implode(', ', $context['view_members_list']) . (empty($context['view_num_hidden']) || $context['can_moderate_members'] || $context['can_moderate_board'] ? '' : ' (+ ' . $context['view_num_hidden'] . ' ' . $txt['hidden'] . ')');

	// Now show how many guests are here too.
	echo $txt['who_and'], $context['view_num_guests'], ' ', $context['view_num_guests'] == 1 ? $txt['guest'] : $txt['guests'], $txt['who_viewing_topic'], '
		</p>
	</section>';
}

// Show statistical style information...
function template_display_statistics()
{
	global $context, $txt, $settings;

	if (empty($settings['show_stats_index']))
		return;

	echo '
	<section>
		<we:title>
			<img src="', ASSETS, '/icons/info.gif" alt="', $txt['topic_stats'], '" class="top" style="padding-right: 0">
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
