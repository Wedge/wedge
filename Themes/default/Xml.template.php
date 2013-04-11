<?php
/**
 * Wedge
 *
 * Encapsulation of specific types of content for transport in an XML container.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_post()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>
	<preview>
		<subject><![CDATA[', $context['preview_subject'], ']]></subject>
		<body><![CDATA[', $context['preview_message'], ']]></body>
	</preview>
	<errors serious="', empty($context['error_type']) || $context['error_type'] != 'serious' ? '0' : '1', '" topic_locked="', $context['locked'] ? '1' : '0', '">';
	if (!empty($context['post_error']['messages']))
		foreach ($context['post_error']['messages'] as $message)
			echo '
		<error><![CDATA[', cleanXml($message), ']]></error>';
	echo '
		<caption name="guestname" class="', isset($context['post_error']['long_name']) || isset($context['post_error']['no_name']) || isset($context['post_error']['bad_name']) ? 'error' : '', '" />
		<caption name="email" class="', isset($context['post_error']['no_email']) || isset($context['post_error']['bad_email']) ? 'error' : '', '" />
		<caption name="evtitle" class="', isset($context['post_error']['no_event']) ? 'error' : '', '" />
		<caption name="subject" class="', isset($context['post_error']['no_subject']) ? 'error' : '', '" />
		<caption name="question" class="', isset($context['post_error']['no_question']) ? 'error' : '', '" />', isset($context['post_error']['no_message']) || isset($context['post_error']['long_message']) ? '
		<post_error />' : '', '
	</errors>
	<last>', isset($context['topic_last_message']) ? $context['topic_last_message'] : '0', '</last>';

	if (!empty($context['previous_posts']))
	{
		echo '
	<new_posts>';
		foreach ($context['previous_posts'] as $post)
			echo '
		<post id="', $post['id'], '">
			<time><![CDATA[', $post['on_time'], ']]></time>
			<poster><![CDATA[', cleanXml($post['poster']), ']]></poster>
			<message><![CDATA[', cleanXml($post['message']), ']]></message>
			<is_ignored>', $post['is_ignored'] ? '1' : '0', '</is_ignored>
		</post>';
		echo '
	</new_posts>';
	}

	echo '
</we>';
}

function template_stats()
{
	global $context, $settings;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>';
	foreach ($context['yearly'] as $year)
		foreach ($year['months'] as $month)
		{
			echo '
	<month id="', $month['date']['year'], $month['date']['month'], '">';
			foreach ($month['days'] as $day)
				echo '
		<day date="', $day['year'], '-', $day['month'], '-', $day['day'], '" new_topics="', $day['new_topics'], '" new_posts="', $day['new_posts'], '" new_members="', $day['new_members'], '" most_members_online="', $day['most_members_online'], '"', empty($settings['hitStats']) ? '' : ' hits="' . $day['hits'] . '"', ' />';
			echo '
	</month>';
		}
		echo '
</we>';
}

// This is just to hold off some errors if people are stupid.
if (!function_exists('template_button_strip'))
{
	function template_button_strip($button_strip, $direction = 'right', $strip_options = array())
	{
	}
	function template_menu()
	{
	}
	function template_linktree()
	{
	}
}

function template_message_icons()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>';
	foreach ($context['icons'] as $icon)
		echo '
	<icon value="', $icon['value'], '" url="', $icon['url'], '"><![CDATA[', cleanXml('<img src="' . $icon['url'] . '" alt="' . $icon['value'] . '" title="' . $icon['name'] . '">'), ']]></icon>';
	echo '
</we>';
}

function template_check_username()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>
	<username valid="', $context['valid_username'] ? 1 : 0, '">', cleanXml($context['checked_username']), '</username>
</we>';
}

function template_thought()
{
	global $context, $theme;

	$th =& $context['return_thought'];
	$thought = $th['thought'];
	$id = $th['id_thought'];
	$uid = isset($th['user_id']) ? $th['user_id'] : 0;

	// Is this a reply to another thought...? Then we should try and style it as well.
	if ($uid)
	{
		// master ID: thought thread's original thought ID
		$mid = isset($th['mid']) ? $th['mid'] : 0;

		// @worg!!
		$privacy_icon = array(
			-3 => 'everyone',
			0 => 'members',
			5 => 'justme',
			20 => 'friends',
		);

		$thought = '<div>' . ($th['privacy'] != -3 ? '<div class="privacy_' . @$privacy_icon[$th['privacy']] . '"></div>' : '') . '<a id="t' . $id . '"></a>'
			. '<a href="<URL>?action=profile;u=' . $uid . '">' . $th['user_name'] . '</a> &raquo;'
			. ' @<a href="<URL>?action=profile;u=' . $th['parent_id'] . '">' . $th['parent_name'] . '</a>&gt;'
			. ' <span class="thought" id="thought_update' . $id . '" data-oid="' . $id . '" data-prv="' . $th['privacy'] . '"><span>' . $thought . '</span></span></div>';
	}

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>
	<text id="', $id, '"><![CDATA[', cleanXml($thought), ']]></text>';

	if ($uid)
		echo '
	<date><![CDATA[<a href="<URL>?action=thoughts;in=', $mid, '#t', $id, '"><img src="', $theme['images_url'], '/icons/last_post.gif" class="middle"></a> ', timeformat(time()), ']]></date>';

	echo '
</we>';
}
