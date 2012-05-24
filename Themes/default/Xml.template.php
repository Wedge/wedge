<?php
/**
 * Wedge
 *
 * Encapsulation of specific types of content for transport in an XML container.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_sendbody()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>
	<message view="', $context['view'], '">', cleanXml($context['message']), '</message>
</we>';
}

function template_quotefast()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>
	<quote>', cleanXml($context['quote']['xml']), '</quote>
</we>';
}

function template_modifyfast()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>
	<subject><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>
	<message id="', $context['message']['id'], '"><![CDATA[', cleanXml($context['message']['body']), ']]></message>
</we>';
}

function template_modifydone()
{
	global $context, $txt;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>';
	if (empty($context['message']['errors']))
	{
		echo '
	<modified><![CDATA[', empty($context['message']['modified']['time']) ? '' : cleanXml(sprintf($txt['last_edit'], $context['message']['modified']['time'], $context['message']['modified']['name'])), ']]></modified>
	<subject', $context['message']['first_in_topic'] ? ' is_first="1"' : '', '><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>
	<body><![CDATA[', $context['message']['body'], ']]></body>';
	}
	else
		echo '
	<error in_subject="', $context['message']['error_in_subject'] ? '1' : '0', '" in_body="', cleanXml($context['message']['error_in_body']) ? '1' : '0', '"><![CDATA[', implode('<br>', $context['message']['errors']), ']]></error>';
	echo '
</we>';
}

function template_modifytopicdone()
{
	global $context, $txt;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>';
	if (empty($context['message']['errors']))
	{
		echo '
	<modified><![CDATA[', empty($context['message']['modified']['time']) ? '' : cleanXml($txt['last_edit'] . ' ' . $context['message']['modified']['time'] . ' ' . $txt['by'] . ' ' . $context['message']['modified']['name']), ']]></modified>';
		if (!empty($context['message']['subject']))
			echo '
	<subject><![CDATA[', cleanXml($context['message']['subject']), ']]></subject>';
	}
	else
		echo '
	<error in_subject="', $context['message']['error_in_subject'] ? '1' : '0', '"><![CDATA[', cleanXml(implode('<br>', $context['message']['errors'])), ']]></error>';
	echo '
</we>';
}

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
	<last_msg>', isset($context['topic_last_message']) ? $context['topic_last_message'] : '0', '</last_msg>';

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

function template_jump_to()
{
	global $context, $scripturl, $settings;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>';
	$url = !empty($settings['pretty_enable_filters']) ? $scripturl . '?board=' : '';
	foreach ($context['jump_to'] as $category)
	{
		echo '
	<item type="c"><![CDATA[', cleanXml($category['name']), ']]></item>';
		foreach ($category['boards'] as $board)
			echo '
	<item level="', $board['child_level'], '" id="', $board['id'], '"', $url ? ' url="' . $url . $board['id'] . '.0"' : '', '><![CDATA[', cleanXml($board['name']), ']]></item>';
	}
	echo '
</we>';
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
	global $context, $user_info;

	$th =& $context['return_thought'];
	$thought = $th['thought'];
	$id = $th['id_thought'];
	$uid = isset($th['user_id']) ? $th['user_id'] : 0;

	// Is this a reply to another thought...? Then we should try and style it as well.
	if ($uid)
	{
		// master ID: thought thread's original thought ID
		// parent ID: thought that is being replied to
		// user ID: current thought's author
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
			. ' @<a href="<URL>?action=profile;u=' . $th['master_id'] . ';area=thoughts#t' . $th['pid'] . '">' . $th['parent_name'] . '</a>&gt;'
			. ' <span class="thought" id="thought_update' . $id . '" data-oid="' . $id . '" data-prv="' . $th['privacy'] . '"'
			. (!$user_info['is_guest'] ? ' data-tid="' . $id . '"' . ($mid && $mid != $id ? ' data-mid="' . $mid . '"' : '') : '')
			. ($user_info['id'] == $uid || $user_info['is_admin'] ? ' data-self' : '')
			. ($context['browser']['is_iphone'] || $context['browser']['is_tablet'] ? ' onclick="return true;"' : '') . '><span>' . $thought . '</span></span></div>';
	}

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>
<we>
	<text id="', $id, '"><![CDATA[', cleanXml($thought), ']]></text>';

	if ($uid)
		echo '
	<date><![CDATA[', timeformat(time()), ']]></date>';

	echo '
</we>';
}

// This prints XML in it's most generic form.
function template_generic_xml()
{
	global $context;

	echo '<', '?xml version="1.0" encoding="UTF-8"?', '>';

	// Show the data.
	template_generic_xml_recursive($context['xml_data'], 'we', '', -1);
}

// Recursive function for displaying generic XML data.
function template_generic_xml_recursive($xml_data, $parent_ident, $child_ident, $level)
{
	// This is simply for neat indentation.
	$level++;

	echo "\n" . str_repeat("\t", $level), '<', $parent_ident, '>';

	foreach ($xml_data as $key => $data)
	{
		// A group?
		if (is_array($data) && isset($data['identifier']))
			template_generic_xml_recursive($data['children'], $key, $data['identifier'], $level);
		// An item...
		elseif (is_array($data) && isset($data['value']))
		{
			echo "\n", str_repeat("\t", $level), '<', $child_ident;

			if (!empty($data['attributes']))
				foreach ($data['attributes'] as $k => $v)
					echo ' ' . $k . '="' . $v . '"';
			echo '><![CDATA[', cleanXml($data['value']), ']]></', $child_ident, '>';
		}
	}

	echo "\n", str_repeat("\t", $level), '</', $parent_ident, '>';
}

?>