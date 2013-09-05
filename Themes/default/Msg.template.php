<?php
/**
 * Wedge
 *
 * Displays a given post.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_msg_wrap_before()
{
	global $msg, $context;

	// msg123 serves as the anchor, as well as an easy way to find the message ID,
	// or other classes (such as can-mod), from within it. For instance,
	// var id_msg = $(this).closest('.msg').attr('id').slice(3);
	echo '
			<we:msg id="msg', $msg['id'], '" class="msg',
				$msg['approved'] ? '' : ' approve',
				$msg['can_modify'] ? ' can-mod' : '',
				SKIN_MOBILE ? ' mobile' : '',
				$msg['id'] !== $context['first_message'] ? '' : ' first-post',
				SKIN_SIDEBAR_RIGHT ? '' : ' right-side', '">
				<div class="post_wrapper">';
}

// Show information about the poster of this message.
function template_msg_author_before()
{
	echo '
					<div class="poster"><div>';
}

function template_msg_author_name()
{
	global $msg, $theme;

	echo '
						<h4>';

	// Show user statuses: online/offline, website, gender, is contact.
	if ($theme['show_profile_buttons'])
		template_user_status($msg['member']);

	// Show a link to the member's profile.
	echo '
							<a href="', $msg['member']['href'], '" data-id="', $msg['member']['id'], '" class="umme">', $msg['member']['name'], '</a>
						</h4>';
}

function template_msg_author_details_before()
{
	echo '
						<ul class="info">';
}

function template_msg_author_title()
{
	global $msg;

	// Show the member's custom title, if they have one.
	if (!empty($msg['member']['title']))
		echo '
							<li class="mtitle">', $msg['member']['title'], '</li>';
}

function template_msg_author_group()
{
	global $msg, $settings;

	$gts = !empty($settings['group_text_show']) ? $settings['group_text_show'] : 'cond';

	// Show the member's primary group (like 'Administrator') if they have one, and if allowed.
	if (!empty($msg['member']['group']) && ($gts === 'all' || $gts === 'normal' || $gts === 'cond'))
		echo '
							<li class="membergroup">', $msg['member']['group'], '</li>';

	if (!$msg['member']['is_guest'])
	{
		// Show the post-based group if allowed by $settings['group_text_show'].
		if (!empty($msg['member']['post_group']) && ($gts === 'all' || $gts === 'post' || ($gts === 'cond' && empty($msg['member']['group']))))
			echo '
							<li class="postgroup">', $msg['member']['post_group'], '</li>';
	}
}

function template_msg_author_group_mobile()
{
	global $msg, $settings;

	$gts = !empty($settings['group_text_show']) ? $settings['group_text_show'] : 'cond';

	if (!empty($msg['member']['group']) && ($gts === 'all' || $gts === 'normal' || $gts === 'cond'))
		echo '
							<li class="membergroup">', $msg['member']['group'], '</li>';
}

function template_msg_author_badge()
{
	global $msg;

	if (!$msg['member']['is_guest'] && !empty($msg['member']['group_badges']))
		echo '
							<li class="stars">
								<div>', implode('</div>
								<div>', $msg['member']['group_badges']), '</div>
							</li>';
}

function template_msg_author_avatar()
{
	global $msg, $theme, $options;

	// Show avatars, images, etc.?
	if (!empty($theme['show_user_images']) && !empty($options['show_avatars']) && !empty($msg['member']['avatar']['image']))
		echo '
							<we:msg_author_avatar>
								<a href="<URL>?action=profile;u=', $msg['member']['id'], '">
									', $msg['member']['avatar']['image'], '
								</a>
							</we:msg_author_avatar>';
}

function template_msg_author_blurb()
{
	global $msg, $theme;

	// Show their personal text?
	if (!$msg['member']['is_guest'] && !empty($theme['show_blurb']) && $msg['member']['blurb'] !== '')
		echo '
							<li class="blurb">', $msg['member']['blurb'], '</li>';
}

function template_msg_author_postcount()
{
	global $msg, $context, $txt;

	// Show how many posts they have made.
	if (!$msg['member']['is_guest'] && !isset($context['disabled_fields']['posts']))
		echo '
							<li class="postcount">', $txt['member_postcount'], ': ', $msg['member']['posts'], '</li>';
}

function template_msg_author_icons()
{
	global $msg;

	// Any custom fields to show as icons?
	if (!$msg['member']['is_guest'] && !empty($msg['member']['custom_fields']))
	{
		$shown = false;
		foreach ($msg['member']['custom_fields'] as $custom)
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
}

function template_msg_author_cf()
{
	global $msg;

	// Any custom fields for standard placement?
	if (!$msg['member']['is_guest'] && !empty($msg['member']['custom_fields']))
		foreach ($msg['member']['custom_fields'] as $custom)
			if (empty($custom['placement']) || empty($custom['value']))
				echo '
							<li class="custom">', $custom['title'], ': ', $custom['value'], '</li>';
}

function template_msg_author_warning()
{
	global $msg, $context, $txt, $theme;

	// Are we showing the warning status?
	if (!$msg['member']['is_guest'] && $msg['member']['can_see_warning'])
		echo '
							<li class="warning">', $context['can_issue_warning'] && $msg['member']['warning_status'] != 'hard_ban' ? '<a href="<URL>?action=profile;u=' . $msg['member']['id'] . ';area=issuewarning">' : '', '<span class="warn_', $msg['member']['warning_status'], '"><span class="icon" title="', $txt['user_warn_' . $msg['member']['warning_status']], '"></span> ', $txt['warn_' . $msg['member']['warning_status']], '</span>', $context['can_issue_warning'] && $msg['member']['warning_status'] != 'hard_ban' ? '</a>' : '', '</li>';
}

function template_msg_author_email()
{
	global $msg, $txt, $theme;

	if ($msg['member']['is_guest'] && !SKIN_MOBILE && !empty($msg['member']['email']) && in_array($msg['member']['show_email'], array('yes_permission_override', 'no_through_forum')))
		echo '
							<li class="email"><a href="<URL>?action=emailuser;sa=email;msg=', $msg['id'], '" rel="nofollow">', $theme['use_image_buttons'] ? '<img src="' . $theme['images_url'] . '/email_sm.gif" alt="' . $txt['email'] . '" title="' . $txt['email'] . '">' : $txt['email'], '</a></li>';
}

function template_msg_author_details_after()
{
	echo '
						</ul>';
}

function template_msg_author_after()
{
	echo '
					</div></div>';
}

// Done with the information about the poster... on to the post itself.
function template_msg_area_before()
{
	echo '
					<we:msg_area>';
}

function template_msg_header_before()
{
	echo '
						<we:msg_header>';

	template_msg_new_anchor();
}

function template_msg_header_body()
{
	global $msg, $theme, $txt;

	// Show a checkbox for quick moderation?
	if ($msg['can_remove'])
		echo '
							<span class="inline_mod_check"></span>';

	if (!SKIN_MOBILE)
		echo '
							<div class="messageicon">
								<img src="', $msg['icon_url'], '">
							</div>';

	echo '
							<h5>
								<a href="', $msg['href'], '" rel="nofollow">', $msg['subject'], '</a>', $msg['new'] ? '
								<div class="note">' . $txt['new'] . '</div>' : '', '
							</h5>
							<time>
								« ', !empty($msg['counter']) ? sprintf($txt['reply_number'], $msg['counter']) : '', ' ', $msg['on_time'], ' »
								<span class="modified">', $theme['show_modify'] && !empty($msg['modified']['name']) ?
									// Show "Last Edit on Date by Person" if this post was edited.
									strtr($txt[$msg['modified']['name'] !== $msg['member']['name'] ? 'last_edit' : 'last_edit_mine'], array(
										'{date}' => $msg['modified']['on_time'],
										'{name}' => !empty($msg['modified']['member']) ? '<a href="<URL>?action=profile;u=' . $msg['modified']['member'] . '">' . $msg['modified']['name'] . '</a>' : $msg['modified']['name']
									)) : '', '
								</span>
							</time>';
}

function template_msg_header_after()
{
	echo '
						</we:msg_header>';
}

function template_msg_new_anchor()
{
	global $msg, $context;

	// Show a "new" anchor if this message is new.
	if ($msg['first_new'] && (!$context['first_new_message'] || !empty($_REQUEST['start'])))
		echo '<a id="new"></a>';
}

function template_msg_ignored()
{
	global $context, $txt;

	// Ignoring this user? Hide the post.
	if ($context['ignoring'])
		echo '
						<div class="ignored">
							', $txt['ignoring_user'], '
						</div>';
}

// Show the post itself, finally!
function template_msg_body_before()
{
	echo '
						<we:msg_post>';
}

function template_msg_body()
{
	global $msg, $txt;

	if (!$msg['approved'] && $msg['member']['id'] != 0 && $msg['member']['id'] == we::$id)
		echo '
							<div class="approve_post errorbox">
								', $txt['post_awaiting_approval'], !empty($msg['unapproved_msg']) ? '<ul><li>' . implode('</li><li>', $msg['unapproved_msg']) . '</li></ul>' : '', '
							</div>';

	echo '
							<div class="inner">', $msg['body'], '</div>';
}

function template_msg_body_after()
{
	echo '
						</we:msg_post>';
}

function template_msg_bottom_before()
{
	global $context;

	if ($context['ignoring'])
		echo '
						<footer>';
}

function template_msg_actionbar_before()
{
	echo '
						<div class="actionbar">';
}

function template_msg_actionbar()
{
	global $msg, $context, $options, $txt;

	// Can the user modify the contents of this post? Show the modify inline image.
	if ($msg['can_modify'])
		echo '
							<div class="quick_edit" title="', $txt['modify_msg'], '" onclick="return window.oQuickModify && oQuickModify.modifyMsg(this);" onmousedown="return false;">&nbsp;</div>';

	if ($msg['has_buttons'])
	{
		echo '
							<ul class="actions">';

		// Can they reply? Have they turned on quick reply?
		if ($context['can_quote'] && !empty($options['display_quick_reply']) && !SKIN_MOBILE)
			echo '
								<li><a href="<URL>?action=post;quote=', $msg['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last=', $context['topic_last_message'], '" class="quote_button" onclick="return window.oQuickReply && oQuickReply.quote(this);">', $txt['quote'], '</a></li>';

		// So... quick reply is off, but they *can* reply?
		elseif ($context['can_quote'] && !SKIN_MOBILE)
			echo '
								<li><a href="<URL>?action=post;quote=', $msg['id'], ';topic=', $context['current_topic'], '.', $context['start'], ';last=', $context['topic_last_message'], '" class="quote_button">', $txt['quote'], '</a></li>';

		// Can the user modify the contents of this post?
		if ($msg['can_modify'] && !SKIN_MOBILE)
			echo '
								<li><a href="<URL>?action=post;msg=', $msg['id'], ';topic=', $context['current_topic'], '.', $context['start'], '" class="edit_button">', $txt['modify'], '</a></li>';

		if (!empty($context['mini_menu']['action'][$msg['id']]))
			echo '
								<li><a class="acme more_button">', $txt[SKIN_MOBILE ? 'actions_button' : 'more_actions'], '</a></li>';

		echo '
							</ul>';
	}
}

function template_msg_actionbar_after()
{
	global $msg, $context, $settings;

	// Did anyone like this post?
	if (!empty($settings['likes_enabled']) && ($msg['can_like'] || !empty($context['liked_posts'][$msg['id']])))
		template_show_likes($msg);

	echo '
						</div>';
}

function template_msg_attachments()
{
	global $msg, $theme;

	// Assuming there are attachments...
	if (empty($msg['attachment']))
		return;

	echo '
						<div class="attachments">
							<div style="overflow: ', we::is('firefox') ? 'visible' : 'auto', '">';

	foreach ($msg['attachment'] as $attachment)
	{
		if ($attachment['is_image'])
		{
			if ($attachment['thumbnail']['has_thumb'])
				echo '
								<a href="', $attachment['href'], ';image" id="link_', $attachment['id'], '" class="zoom"><img src="', $attachment['thumbnail']['href'], '" id="thumb_', $attachment['id'], '"></a><br>';
			else
				echo '
								<img src="', $attachment['href'], ';image" width="', $attachment['width'], '" height="', $attachment['height'], '"><br>';
		}
		echo '
								<a href="', $attachment['href'], '"><img src="', $theme['images_url'], '/icons/clip.gif" class="middle">&nbsp;', $attachment['name'], '</a>
								(', $attachment['size'], $attachment['is_image'] ? ', ' . $attachment['real_width'] . 'x' . $attachment['real_height'] : '', ' - ', number_context($attachment['is_image'] ? 'attach_viewed' : 'attach_downloaded', $attachment['downloads']), ')<br>';
	}

	echo '
							</div>
						</div>';
}

function template_msg_customfields()
{
	global $msg;

	// Are there any custom profile fields for above the signature?
	if (empty($msg['member']['custom_fields']))
		return;

	foreach ($msg['member']['custom_fields'] as $custom)
	{
		if ($custom['placement'] != 2 || empty($custom['value']))
			continue;
		if (empty($shown))
		{
			$shown = true;
			echo '
						<div class="custom_fields">
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

function template_msg_signature()
{
	global $msg, $context, $options;

	// Show the member's signature?
	if (!empty($msg['member']['signature']) && !empty($options['show_signatures']) && $context['signature_enabled'])
		echo '
						<we:msg_signature>', $msg['member']['signature'], '</we:msg_signature>';
}

function template_msg_bottom_after()
{
	global $context;

	if ($context['ignoring'])
		echo '
						</footer>';
}

function template_msg_area_after()
{
	echo '
					</we:msg_area>';
}

function template_msg_wrap_after()
{
	global $msg, $context;

	echo '
				</div>
			</we:msg>', $context['topic_last_message'] === $msg['id'] ? '' : '
			<hr class="sep">';
}

function template_user_status(&$member)
{
	global $context, $theme, $txt;

	if ($member['is_guest'])
		return;

	echo '
							<span class="pixelicons">';

	// Is this user online or not?
	echo '
								<i', $member['online']['is_online'] ? ' class="online" title="' . $txt['online'] : ' title="' . $txt['offline'], '"></i>';

	// Have they specified a website?
	echo '
								<i', $member['website']['url'] != '' && !isset($context['disabled_fields']['website']) ? ' class="website"' : '', ' title="', $txt['website'], '"></i>';

	// Indicate their gender, if filled in and allowed.
	$gender = empty($theme['show_gender']) || isset($context['disabled_fields']['gender']) ? '' : (empty($member['gender']) ? '' : $member['gender']);
	echo '
								<i', $gender ? ' class="' . $gender . '" title="' . $txt[$gender] . '"' : '', '></i>';

	// Are they a contact of mine..?
	echo '
								<i', $member['is_buddy'] ? ' class="contact"' : '', ' title="' . $txt['is_' . ($member['is_buddy'] ? '' : 'not_') . 'buddy'] . '"></i>';

	echo '
							</span>';
}

function template_show_likes($id_msg = 0, $can_like = false)
{
	global $msg, $context, $txt, $user_profile;

	$string = '';
	$id_msg = !empty($msg['id']) ? $msg['id'] : $id_msg;
	$can_like = isset($msg['can_like']) ? $msg['can_like'] : $can_like;
	$likes =& $context['liked_posts'][$id_msg];
	$you_like = !empty($likes['you']);

	if (!empty($likes))
	{
		// We need two things, firstly whether we liked it or not, and how many people who weren't us liked it.
		$other_likes = isset($likes['others']) ? $likes['others'] : 0;
		$string = number_context($you_like ? 'you_like_this' : 'like_this', $other_likes);
		$num_likes = $other_likes + ($you_like ? 1 : 0);
		$show_likes = '<span class="note' . ($you_like ? 'nice' : '') . '">' . $num_likes . '</span>';
	}
	else
		$num_likes = 0;

	echo '
							<div class="post_like">';

	// Can they use the Like button?
	if ($can_like)
		echo '
								<a href="<URL>?action=like;topic=', $context['current_topic'], ';msg=', $id_msg, ';', $context['session_query'], '" class="', $you_like ? 'un' : '', 'like_button"', empty($string) ? '' : ' title="' . strip_tags($string) . '"', ' onclick="return likePost(this);">',
								$txt[$you_like ? 'unlike' : 'like'], '</a>', $num_likes ? ' <a href="<URL>?action=like;sa=view;type=post;cid=' . $id_msg . '" class="fadein" onclick="return reqWin(this);">' . $show_likes . '</a>' : '';
	elseif ($num_likes)
		echo '
								<span class="like_button" title="', strip_tags($string), '"> <a href="<URL>?action=like;sa=view;type=post;cid=' . $id_msg . '" class="fadein" onclick="return reqWin(this);">' . $show_likes . '</a></span>';

	echo '
							</div>';
}
