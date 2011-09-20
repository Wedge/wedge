<?php
/**
 * Wedge
 *
 * Displays the currently available add-ons.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_browse()
{
	global $context, $settings, $options, $scripturl, $txt;

	if (empty($context['available_addons']))
		echo '
	<div class="information">', $txt['no_addons_found'], '</div>';

	$use_bg2 = true;
	// Just before printing content, go through and work out what icons we're going to display. Need to do it first though, because we need to know how many icons we're working on.
	$icons = array();
	$max_icons = 0;
	foreach ($context['available_addons'] as $id => $addon)
	{
		$icons[$id] = array();
		if (!empty($addon['install_errors']))
			continue;

		if ($addon['enabled'])
		{
			$item = array(
				array(
					'icon' => 'switch_on.png',
					'url' => $scripturl . '?action=admin;area=addons;sa=disable;addon=' . $addon['folder'] . ';' . $context['session_query'],
					'title' => $txt['disable_addon']
				)
			);

			if (!empty($addon['acp_url']))
				$item[] = array(
					'icon' => 'addon_settings.png',
					'url' => $scripturl . '?' . $addon['acp_url'],
					'title' => $txt['admin_modifications'],
				);

			$icons[$id] = $item;
		}
		else
		{
			$item = array(
				array(
					'icon' => 'switch_off.png',
					'url' => $scripturl . '?action=admin;area=addons;sa=enable;addon=' . $addon['folder'] . ';' . $context['session_query'],
					'title' => $txt['enable_addon'],
				)
			);

			$icons[$id] = $item;
		}
		$max_icons = max($max_icons, count($icons[$id]));
	}

	// Print out the content.
	foreach ($context['available_addons'] as $id => $addon)
	{
		echo '
	<fieldset class="windowbg', $use_bg2 ? '2' : '', ' wrc">
		<legend>', $addon['name'], ' ', $addon['version'], '</legend>';

		if (!empty($addon['install_errors']))
			echo '
		<div class="floatright"><strong>', $txt['install_errors'], '</strong><br>', implode('<br>', $addon['install_errors']), '</div>';
		else
		{
			for ($i = $max_icons - 1; $i >= 0; $i--)
				if (!isset($icons[$id][$i]))
					echo '
			<div class="addon_item inline_block floatright">&nbsp;</div>';
				else
					echo '
			<div class="addon_item inline_block floatright">
				<a href="', $icons[$id][$i]['url'], '">
					<img src="', $settings['images_url'], '/admin/', $icons[$id][$i]['icon'], '"', !empty($icons[$id][$i]['title']) ? ' title="' . $icons[$id][$i]['title'] . '"' : '', '>
				</a>
			</div>';
		}

		if (!empty($addon['description']))
			echo '
		<p>', $addon['description'], '</p>';

		if (!empty($addon['readmes']))
		{
			echo '
		<div class="smalltext">', $txt['addon_readmes'], ':';

			foreach ($addon['readmes'] as $readme => $state)
				echo ' &nbsp;<a href="', $scripturl, '?action=admin;area=addons;sa=readme;addon=', rawurlencode($addon['folder']), ';lang=', $readme, '" onclick="return reqWin(this);"><img src="', $settings['theme_url'], '/languages/Flag.', $readme, '.png"></a>';

			echo '
		</div>';
		}

		echo '
	</fieldset>';
	}

	echo '
	<br class="clear">';
}

?>