<?php
/**
 * Wedge
 *
 * Displays a given topic, be it a blog post or forum topic.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_display_posts()
{
	global $context, $theme, $options, $txt, $scripturl, $settings, $board_info;

	// OK, we're going to need this!
	add_js_file('scripts/topic.js');

	// Show the topic information - icon, subject, etc.
	echo '
		<div id="forumposts"', $board_info['type'] == 'board' ? '' : ' class="blog"', '>
			<form action="<URL>?action=quickmod2;topic=', $context['current_topic'], '.', $context['start'], '" method="post" accept-charset="UTF-8" name="quickModForm" id="quickModForm" style="margin: 0" onsubmit="return window.oQuickModify && oQuickModify.modifySave()">';

	$ignoredMsgs = array();
	$removableMessageIDs = array();
	$is_mobile = !empty($context['skin_options']['mobile']);
	$alternate = false;

	// Get all the messages...
	while ($message = $context['get_message']())
	{
		$ignoring = false;
		$alternate = !$alternate;
		if ($message['can_remove'])
			$removableMessageIDs[] = $message['id'];

		// Are we ignoring this message?
		if (!empty($message['is_ignored']))
			$ignoredMsgs[] = $ignoring = $message['id'];

		// Show a "new" anchor if this message is new.
		if ($message['first_new'] && (!$context['first_new_message'] || !empty($_REQUEST['start'])))
			echo '
			<a id="new"></a>';

		// msg123 serves as the anchor, as well as an easy way to find the message ID,
		// or other classes (such as can-mod), from within it. For instance,
		// var id_msg = $(this).closest('.root').attr('id').substr(3);
		echo '
			<div id="msg', $message['id'], '" class="root',
				$message['alternate'] == 0 ? ' postbg' : ' postbg2',
				$message['approved'] ? '' : ' approve',
				$message['can_modify'] ? ' can-mod' : '',
				$is_mobile ? ' mobile' : '',
				$message['id'] !== $context['first_message'] ? '' : ' first-post',
				empty($context['skin_options']['sidebar']) || $context['skin_options']['sidebar'] === 'right' ? '' : ' right-side',
			'">
				<div class="post_wrapper">';

		// Show information about the poster of this message.
		if (empty($context['skin_options']['sidebar']) || $context['skin_options']['sidebar'] !== 'left')
			echo '
					<div class="poster">',
						template_userbox($message), '
					</div>';

		// Done with the information about the poster... on to the post itself.
		echo '
					<div class="postarea">';

		if (!$is_mobile)
		{
			echo '
						<div class="postheader">';

			// Show a checkbox for quick moderation?
			if ($message['can_remove'])
				echo '
							<span class="inline_mod_check"></span>';

			echo '
							<div class="keyinfo">
								<div class="messageicon">
									<img src="', $message['icon_url'] . '">
								</div>
								<h5>
									<a href="', $message['href'], '" rel="nofollow">', $message['subject'], '</a>', $message['new'] ? '
									<div class="note">' . $txt['new'] . '</div>' : '', '
								</h5>
								<span>&#171; ', !empty($message['counter']) ? sprintf($txt['reply_number'], $message['counter']) : '', ' ', $message['on_time'], ' &#187;</span>
								<span class="modified">', $theme['show_modify'] && !empty($message['modified']['name']) ?
									// Show "Last Edit on Date by Person" if this post was edited.
									strtr($txt[$message['modified']['name'] !== $message['member']['name'] ? 'last_edit' : 'last_edit_mine'], array(
										'{date}' => $message['modified']['on_time'],
										'{name}' => !empty($message['modified']['member']) ? '<a href="<URL>?action=profile;u=' . $message['modified']['member'] . '">' . $message['modified']['name'] . '</a>' : $message['modified']['name']
									)) : '',
								'</span>
							</div>
						</div>';
		}
		else
		{
			// If we're in mobile mode, we'll move the Quote and Modify buttons to the Action menu.
			$menu = isset($context['action_menu'][$message['id']]) ? $context['action_menu'][$message['id']] : array();

			// Insert them after the previous message's id.
			if ($message['can_modify'])
				array_unshift($menu, 'mo');

			if ($context['can_quote'])
				array_unshift($menu, 'qu');

			$context['action_menu'][$message['id']] = $menu;
			$context['action_menu_items_show'] += array_flip($menu);
		}

		// Ignoring this user? Hide the post.
		if ($ignoring)
			echo '
						<div class="ignored">
							', $txt['ignoring_user'], '
						</div>';

		if ($is_mobile)
			echo '
						<h5></h5>';

		// Show the post itself, finally!
		echo '
						<div class="post">';

		if (!$message['approved'] && $message['member']['id'] != 0 && $message['member']['id'] == $context['user']['id'])
			echo '
							<div class="approve_post">
								', $txt['post_awaiting_approval'], '
							</div>';

		echo '
							<div class="inner">', $message['body'], '</div>
						</div>';

		if ($ignoring)
			echo '
						<footer>';

		if ($message['can_modify'] || $message['has_buttons'] || $message['can_like'] || !empty($context['liked_posts'][$message['id']]))
		{
			echo '
						<div class="actionbar">';

			// Can the user modify the contents of this post? Show the modify inline image.
			if ($message['can_modify'])
				echo '
							<div class="quick_edit" title="', $txt['modify_msg'], '" onclick="return window.oQuickModify && oQuickModify.modifyMsg(this);" onmousedown="return false;">&nbsp;</div>';

			if ($message['has_buttons'])
			{
				echo '
							<ul class="actions">';

				// Can they reply? Have they turned on quick reply?
				if ($context['can_quote'] && !empty($options['display_quick_reply']) && !$is_mobile)
					echo '
								<li><a href="<URL>?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last=', $context['topic_last_message'], '" class="quote_button" onclick="return window.oQuickReply && oQuickReply.quote(this);">', $txt['quote'], '</a></li>';

				// So... quick reply is off, but they *can* reply?
				elseif ($context['can_quote'] && !$is_mobile)
					echo '
								<li><a href="<URL>?action=post;quote=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last=', $context['topic_last_message'], '" class="quote_button">', $txt['quote'], '</a></li>';

				// Can the user modify the contents of this post?
				if ($message['can_modify'] && !$is_mobile)
					echo '
								<li><a href="<URL>?action=post;msg=', $message['id'], ';topic=', $context['current_topic'], '.', $context['start'], '" class="modify_button">', $txt['modify'], '</a></li>';

				if (!empty($context['action_menu'][$message['id']]))
					echo '
								<li><a class="acme more_button">', $txt[$is_mobile ? 'actions_button' : 'more_actions'], '</a></li>';

				echo '
							</ul>';
			}

			// Did anyone like this post?
			if (!empty($settings['likes_enabled']) && ($message['can_like'] || !empty($context['liked_posts'][$message['id']])))
				template_show_likes($message);

			echo '
						</div>';
		}

		// Assuming there are attachments...
		if (!empty($message['attachment']))
		{
			echo '
						<div class="attachments">
							<div style="overflow: ', $context['browser']['is_firefox'] ? 'visible' : 'auto', '">';

			foreach ($message['attachment'] as $attachment)
			{
				if ($attachment['is_image'])
				{
					if ($attachment['thumbnail']['has_thumb'])
						echo '
								<a href="', $attachment['href'], ';image" id="link_', $attachment['id'], '" class="zoom"><img src="', $attachment['thumbnail']['href'], '" id="thumb_', $attachment['id'], '"></a><br>';
					else
						echo '
								<img src="', $attachment['href'], ';image" width="' . $attachment['width'] . '" height="' . $attachment['height'] . '"><br>';
				}
				echo '
								<a href="', $attachment['href'], '"><img src="' . $theme['images_url'] . '/icons/clip.gif" class="middle">&nbsp;' . $attachment['name'] . '</a>
								(', $attachment['size'], $attachment['is_image'] ? ', ' . $attachment['real_width'] . 'x' . $attachment['real_height'] : '', ' - ', number_context($attachment['is_image'] ? 'attach_viewed' : 'attach_downloaded', $attachment['downloads']), ')<br>';
			}

			echo '
							</div>
						</div>';
		}

		// Are there any custom profile fields for above the signature?
		if (!empty($message['member']['custom_fields']))
		{
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if ($custom['placement'] != 2 || empty($custom['value']))
					continue;
				if (empty($shown))
				{
					$shown = true;
					echo '
						<div class="custom_fields_above_signature">
							<ul class="reset nolist">';
				}
				echo '
								<li>', $custom['value'], '</li>';
			}
			if (!empty($shown))
				echo '
							</ul>
						</div>';
		}

		// Show the member's signature?
		if (!empty($message['member']['signature']) && !empty($options['show_signatures']) && $context['signature_enabled'])
			echo '
						<div class="signature">', $message['member']['signature'], '</div>';

		if ($ignoring)
			echo '
					</footer>';

		echo '
					</div>';

		// Show information about the poster of this message.
		if (!empty($context['skin_options']['sidebar']) && $context['skin_options']['sidebar'] === 'left')
			echo '
					<div class="poster">',
						template_userbox($message), '
					</div>';

		echo '
				</div>
			</div>
			<hr class="post_separator">';
	}

	echo '
			</form>
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
					<input type="submit" name="post" value="' . $txt['save'] . '" tabindex="' . $context['tabindex']++ . '" accesskey="s" onclick="return window.oQuickModify && oQuickModify.modifySave();" class="save">&nbsp;&nbsp;' . ($context['show_spellchecking'] ? '<input type="button" value="' . $txt['spell_check'] . '" tabindex="' . $context['tabindex']++ . '" onclick="spellCheck(\'quickModForm\', \'message\');" class="spell">&nbsp;&nbsp;' : '') . '<input type="submit" name="cancel" value="' . $txt['form_cancel'] . '" tabindex="' . $context['tabindex']++ . '" onclick="return oQuickModify.modifyCancel();" class="cancel">
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
		aSwapLinks: [{ sId: \'msg' . $msgid . ' .ignored\' }]
	});');

	if (!empty($context['user_menu']))
	{
		$context['footer_js'] .= '
	$(".umme").mime({';

		foreach ($context['user_menu'] as $user => $linklist)
			$context['footer_js'] .= '
		' . $user. ': ["' . implode('", "', $linklist) . '"],';

		$context['footer_js'] = substr($context['footer_js'], 0, -1) . '
	}, {';
		foreach ($context['user_menu_items'] as $key => $pmi)
		{
			if (!isset($context['user_menu_items_show'][$key]))
				continue;
			$context['footer_js'] .= '
		' . $key . ': [';
			foreach ($pmi as $type => $item)
				if ($type === 'caption')
					$context['footer_js'] .= (isset($txt[$item]) ? JavaScriptEscape($txt[$item]) : '\'\'') . ', ' . (isset($txt[$item . '_desc']) ? JavaScriptEscape($txt[$item . '_desc']) : '\'\'') . ', ';
				else
					$context['footer_js'] .= $item . ', ';
			$context['footer_js'] = substr($context['footer_js'], 0, -2) . '],';
		}
		$context['footer_js'] = substr($context['footer_js'], 0, -1) . '
	}, true);';
	}

	if (!empty($context['action_menu']))
	{
		$context['footer_js'] .= '
	$(".acme").mime({';

		foreach ($context['action_menu'] as $post => $linklist)
			$context['footer_js'] .= '
		' . $post . ': ["' . implode('", "', $linklist) . '"],';

		$context['footer_js'] = substr($context['footer_js'], 0, -1) . '
	}, {';
		foreach ($context['action_menu_items'] as $key => $pmi)
		{
			if (!isset($context['action_menu_items_show'][$key]))
				continue;
			$context['footer_js'] .= '
		' . $key . ': [';
			foreach ($pmi as $type => $item)
				if ($type === 'caption')
					$context['footer_js'] .= (isset($txt[$item]) ? JavaScriptEscape($txt[$item]) : '\'\'') . ', ' . (isset($txt[$item . '_desc']) ? JavaScriptEscape($txt[$item . '_desc']) : '\'\'') . ', ';
				else
					$context['footer_js'] .= $item . ', ';
			$context['footer_js'] = substr($context['footer_js'], 0, -2) . '],';
		}
		$context['footer_js'] = substr($context['footer_js'], 0, -1) . '
	});';
	}
}

function template_userbox(&$message)
{
	global $context, $settings, $txt, $theme, $options;

	$is_mobile = !empty($context['skin_options']['mobile']);
	$gts = !empty($settings['group_text_show']) ? $settings['group_text_show'] : 'cond';

	if ($is_mobile)
	{
		if (!empty($context['action_menu'][$message['id']]))
			echo '
						<div class="tinyuser">
							<span>', timeformat($message['timestamp']), '</span>
						</div>';

		// Show avatar for mobile skins
		if (!empty($theme['show_user_images']) && !empty($options['show_avatars']) && !empty($message['member']['avatar']['image']))
			echo '
						<div class="avatar">
							<a href="<URL>?action=profile;u=', $message['member']['id'], '">
								', $message['member']['avatar']['image'], '
							</a>
						</div>';
	}

	echo '
						<h4>';

	// Show user statuses: online/offline, website, gender, is contact.
	if ($theme['show_profile_buttons'])
		template_user_status($message['member']);

	// Show a link to the member's profile.
	echo '
							<a href="', $message['member']['href'], '" data-id="', $message['member']['id'], '" class="umme">', $message['member']['name'], '</a>
						</h4>
						<ul class="info">';

	// Show the member's custom title, if they have one.
	if (!empty($message['member']['title']) && !$is_mobile)
		echo '
							<li class="mtitle">', $message['member']['title'], '</li>';

	// Show the member's primary group (like 'Administrator') if they have one, and if allowed.
	if (!empty($message['member']['group']) && ($gts === 'all' || $gts === 'normal' || $gts === 'cond'))
		echo '
							<li class="membergroup">', $message['member']['group'], '</li>';

	// Don't show these things for guests or mobile skins.
	if (!$message['member']['is_guest'] && !$is_mobile)
	{
		// Show the post-based group if allowed by $settings['group_text_show'].
		if (!empty($message['member']['post_group']) && ($gts === 'all' || $gts === 'post' || ($gts === 'cond' && empty($message['member']['group']))))
			echo '
							<li class="postgroup">', $message['member']['post_group'], '</li>';

		if (!empty($message['member']['group_badges']))
			echo '
							<li class="stars">
								<div>', implode('</div>
								<div>', $message['member']['group_badges']), '</div>
							</li>';

		// Show avatars, images, etc.?
		if (!empty($theme['show_user_images']) && !empty($options['show_avatars']) && !empty($message['member']['avatar']['image']))
			echo '
							<li class="avatar">
								<a href="<URL>?action=profile;u=', $message['member']['id'], '">
									', $message['member']['avatar']['image'], '
								</a>
							</li>';

		// Show how many posts they have made.
		if (!isset($context['disabled_fields']['posts']))
			echo '
							<li class="postcount">', $txt['member_postcount'], ': ', $message['member']['posts'], '</li>';

		// Show the member's gender icon?
		if (!empty($theme['show_gender']) && $message['member']['gender']['image'] != '' && !isset($context['disabled_fields']['gender']))
			echo '
							<li class="gender">', $txt['gender'], ': ', $message['member']['gender']['image'], '</li>';

		// Show their personal text?
		if (!empty($theme['show_blurb']) && $message['member']['blurb'] !== '')
			echo '
							<li class="blurb">', $message['member']['blurb'], '</li>';

		// Any custom fields to show as icons?
		if (!empty($message['member']['custom_fields']))
		{
			$shown = false;
			foreach ($message['member']['custom_fields'] as $custom)
			{
				if ($custom['placement'] != 1 || empty($custom['value']))
					continue;
				if (empty($shown))
				{
					$shown = true;
					echo '
							<li class="im_icons">
								<ul>';
				}
				echo '
									<li>', $custom['value'], '</li>';
			}
			if ($shown)
				echo '
								</ul>
							</li>';
		}

		// Any custom fields for standard placement?
		if (!empty($message['member']['custom_fields']))
			foreach ($message['member']['custom_fields'] as $custom)
				if (empty($custom['placement']) || empty($custom['value']))
					echo '
							<li class="custom">', $custom['title'], ': ', $custom['value'], '</li>';

		// Are we showing the warning status?
		if ($message['member']['can_see_warning'])
			echo '
							<li class="warning">', $context['can_issue_warning'] && $message['member']['warning_status'] != 'ban' ? '<a href="<URL>?action=profile;u=' . $message['member']['id'] . ';area=issuewarning">' : '', '<img src="', $theme['images_url'], '/warning_', $message['member']['warning_status'], '.gif" alt="', $txt['user_warn_' . $message['member']['warning_status']], '">', $context['can_issue_warning'] && $message['member']['warning_status'] != 'ban' ? '</a>' : '', '<span class="warn_', $message['member']['warning_status'], '">', $txt['warn_' . $message['member']['warning_status']], '</span></li>';
	}
	// Otherwise, show the guest's email.
	elseif (!$is_mobile && !empty($message['member']['email']) && in_array($message['member']['show_email'], array('yes_permission_override', 'no_through_forum')))
		echo '
							<li class="email"><a href="<URL>?action=emailuser;sa=email;msg=', $message['id'], '" rel="nofollow">', $theme['use_image_buttons'] ? '<img src="' . $theme['images_url'] . '/email_sm.gif" alt="' . $txt['email'] . '" title="' . $txt['email'] . '">' : $txt['email'], '</a></li>';

	echo '
						</ul>';
}

function template_user_status(&$member)
{
	global $context, $theme, $txt;

	echo '
							<div class="pixelicons">';

	// Is this user online or not?
	echo '
								<div', $member['online']['is_online'] ? ' class="online" title="' . $txt['online'] : ' title="' . $txt['offline'], '"></div>';

	// Have they specified a website?
	echo '
								<div', $member['website']['url'] != '' && !isset($context['disabled_fields']['website']) ? ' class="website"' : '', ' title="', $txt['website'], '"></div>';

	// Indicate their gender, if filled in.
	echo '
								<div', $member['gender'] ? ' class="' . ($member['gender']['name'] == $txt['male'] ? 'male' : 'female') . '" title="' . $member['gender']['name'] . '"' : '', '></div>';

	// Are they a contact of mine..?
	echo '
								<div', $member['is_buddy'] ? ' class="contact"' : '', ' title="' . $txt['is_' . ($member['is_buddy'] ? '' : 'not_') . 'buddy'] . '"></div>';

	echo '
							</div>';
}

function template_show_likes(&$message)
{
	global $context, $txt, $user_profile;

	$string = '';
	$likes =& $context['liked_posts'][$message['id']];
	$you_like = !empty($likes['you']);

	if (!empty($likes))
	{
		// Simplest case, it's just you.
		if ($you_like && empty($likes['names']))
		{
			$string = $txt['you_like_this'];
			$num_likes = 1;
		}
		// So we have some names to display?
		elseif (!empty($likes['names']))
		{
			$base_id = $you_like ? 'you_' : '';
			if (!empty($likes['others']))
				$string = number_context($base_id . 'n_like_this', $likes['others']);
			else
				$string = $txt[$base_id . count($likes['names']) . '_like_this'];

			// OK so at this point we have the string with the number of 'others' added, and also 'You' if appropriate. Now to add other names.
			foreach ($likes['names'] as $k => $v)
				$string = str_replace('{name' . ($k + 1) . '}', '<a href="<URL>?action=profile;u=' . $v . '">' . $user_profile[$v]['real_name'] . '</a>', $string);
			$num_likes = ($you_like ? 1 : 0) + count($likes['names']) + (empty($likes['others']) ? 0 : $likes['others']);
		}
	}
	else
		$num_likes = 0;

	$show_likes = $num_likes ? '<span class="note' . ($you_like ? 'nice' : '') . '">' . $num_likes . '</span>' : '';

	// Is this an Ajax request to get who likes what?
	if (isset($_REQUEST['xml']))
		return $string;

	echo '
							<div class="post_like">';

	// Can they use the Like button?
	if ($message['can_like'])
		echo '
								<a href="<URL>?action=like;topic=', $context['current_topic'], ';msg=', $message['id'], ';', $context['session_query'], '" class="', $you_like ? 'un' : '', 'like_button"', empty($string) ? '' : ' title="' . strip_tags($string) . '"', '>',
								$txt[$you_like ? 'unlike' : 'like'], '</a>', $num_likes ? ' <a href="<URL>?action=like;sa=view;type=post;cid=' . $message['id'] . '" class="fadein" onclick="return reqWin(this);">' . $show_likes . '</a>' : '';
	elseif ($num_likes)
		echo '
								<span class="like_button" title="', strip_tags($string), '"> <a href="<URL>?action=like;sa=view;type=post;cid=' . $message['id'] . '" class="fadein" onclick="return reqWin(this);">' . $show_likes . '</a></span>';

	echo '
							</div>';
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
				<form action="<URL>?board=', $context['current_board'], ';action=post2" method="post" accept-charset="UTF-8" name="postmodify" id="postmodify" onsubmit="submitonce(this);" class="clearfix">
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
	if ($context['user']['is_guest'])
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
		</div>', $context['browser']['is_ie6'] ? '
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

?>