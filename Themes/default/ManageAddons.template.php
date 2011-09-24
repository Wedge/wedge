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
				),
				array(
					'icon' => 'addon_remove.png',
					'url' => $scripturl . '?action=admin;area=addons;sa=remove;addon=' . $addon['folder'],
					'title' => $txt['remove_addon'],
				),
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

		// Add-on buttons. They're floated right, so need to be first. Besides which, the floating means they need to be in reverse order :/
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

		// Add-on description
		if (!empty($addon['description']))
			echo '
		<p>', $addon['description'], '</p>';

		// Add-on author, including links home.
		echo '
		<div class="smalltext inline-block floatleft" style="width:33%">', $txt['addon_written_by'], ': ', $addon['author'];
		if (!empty($addon['author_url']))
			echo '
		&nbsp;<a href="', $addon['author_url'], '" target="_blank"><img src="', $settings['images_url'], '/icons/profile_sm.gif" title="', $txt['addon_author_url'], '"></a>';

		if (!empty($addon['website']))
			echo '
		&nbsp;<a href="', $addon['website'], '" target="_blank"><img src="', $settings['images_url'], '/www.gif" title="', sprintf($txt['addon_website'], $addon['name']), '"></a>';

		if (!empty($addon['author_email']))
			echo '
		&nbsp;<a href="mailto:', $addon['author_email'], '"><img src="', $settings['images_url'], '/email_sm.gif" title="', $txt['addon_author_email'], '"></a>';

		echo '</div>';

		// Add-on readmes
		if (!empty($addon['readmes']))
		{
			echo '
		<div class="smalltext floatleft inline-block">', $txt['addon_readmes'], ':';

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

function template_remove()
{
	global $context, $settings, $options, $scripturl, $txt;

	echo '
	<form action="', $scripturl, '?action=admin;area=addons;sa=remove;addon=', $_GET['addon'], ';commit" method="post">
		<div class="windowbg2 wrc">
			<p><strong>', sprintf($txt['remove_addon_desc'], $context['addon_name']), '</strong></p>
			<p>', $txt['remove_addon_blurb'], '</p>
			<fieldset>
				<legend>', $txt['remove_addon_nodelete'], '</legend>
				', $txt['remove_addon_nodelete_desc'], '<br>
				<input name="nodelete" type="submit" class="save floatright" value="', $txt['remove_addon_nodelete'], '">
			</fieldset>
			<br>
			<fieldset>
				<legend>', $txt['remove_addon_delete'], '</legend>
				', $txt['remove_addon_delete_desc'], '<br>
				<input name="delete" type="submit" class="delete floatright" value="', $txt['remove_addon_delete'], '">
			</fieldset>';

	echo '
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>
	<br class="clear">';
}

?>