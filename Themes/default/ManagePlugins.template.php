<?php
/**
 * Wedge
 *
 * Displays the currently available plugins.
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

	// Showing the filtering.
	$items = array();
	foreach ($context['filter_plugins'] as $k => $v)
		$items[] = $k != $context['current_filter'] ? '<a href="' . $scripturl . '?action=admin;area=plugins;filter=' . $k . '">' . sprintf($txt['plugin_filter_' . $k], $v) . '</a>' : '<strong>' . sprintf($txt['plugin_filter_' . $k], $v) . '</strong>';

	echo '
	<p class="description">', $txt['plugin_filter'], ' ', implode(' | ', $items), '</p>';

	// Nothing to show? Might as well just get gone, then.
	if (empty($context['available_plugins']))
	{
		echo '
	<div class="information">', $txt['no_plugins_found'], '</div>
	<br class="clear">';
		return;
	}

	$use_bg2 = true;
	// Just before printing content, go through and work out what icons we're going to display. Need to do it first though, because we need to know how many icons we're working on.
	$icons = array();
	$max_icons = 0;
	foreach ($context['available_plugins'] as $id => $plugin)
	{
		$icons[$id] = array();

		if ($plugin['enabled'])
		{
			$item = array(
				array(
					'icon' => 'switch_on.png',
					'url' => $scripturl . '?action=admin;area=plugins;sa=disable;plugin=' . $plugin['folder'] . ';' . $context['session_query'],
					'title' => $txt['disable_plugin']
				)
			);

			if (!empty($plugin['acp_url']))
				$item[] = array(
					'icon' => 'plugin_settings.png',
					'url' => $scripturl . '?' . $plugin['acp_url'],
					'title' => $txt['admin_modifications'],
				);

			$icons[$id] = $item;
		}
		else
		{
			$item = array();

			if (empty($plugin['install_errors']))
				$item[0] = array(
					'icon' => 'switch_off.png',
					'url' => $scripturl . '?action=admin;area=plugins;sa=enable;plugin=' . $plugin['folder'] . ';' . $context['session_query'],
					'title' => $txt['enable_plugin'],
				);

			$item[1] = array(
				'icon' => 'plugin_remove.png',
				'url' => $scripturl . '?action=admin;area=plugins;sa=remove;plugin=' . $plugin['folder'],
				'title' => $txt['remove_plugin'],
			);

			$icons[$id] = $item;
		}
		$max_icons = max($max_icons, count($icons[$id]));
	}

	// Print out the content.
	foreach ($context['available_plugins'] as $id => $plugin)
	{
		echo '
	<fieldset class="windowbg', $use_bg2 ? '2' : '', ' wrc">
		<legend>', $plugin['name'], ' ', $plugin['version'], '</legend>';

		for ($i = $max_icons - 1; $i >= 0; $i--)
		{
			if (!isset($icons[$id][$i]))
				echo '
			<div class="plugin_item inline_block floatright">&nbsp;</div>';
			else
				echo '
			<div class="plugin_item inline_block floatright">
				<a href="', $icons[$id][$i]['url'], '">
					<img src="', $settings['images_url'], '/admin/', $icons[$id][$i]['icon'], '"', !empty($icons[$id][$i]['title']) ? ' title="' . $icons[$id][$i]['title'] . '"' : '', '>
				</a>
			</div>';
		}

		// Plugin buttons. They're floated right, so need to be first. Besides which, the floating means they need to be in reverse order :/
		if (!empty($plugin['install_errors']))
			echo '
		<div class="floatright smalltext errorbox plugin_error"><strong>', $txt['install_errors'], '</strong><br>', implode('<br>', $plugin['install_errors']), '</div>';

		// Plugin description
		if (!empty($plugin['description']))
			echo '
		<p>', $plugin['description'], '</p>';

		// Plugin author, including links home.
		echo '
		<div class="smalltext inline-block floatleft" style="width:33%">', $txt['plugin_written_by'], ': ', $plugin['author'];
		if (!empty($plugin['author_url']))
			echo '
		&nbsp;<a href="', $plugin['author_url'], '" target="_blank"><img src="', $settings['images_url'], '/icons/profile_sm.gif" title="', $txt['plugin_author_url'], '"></a>';

		if (!empty($plugin['website']))
			echo '
		&nbsp;<a href="', $plugin['website'], '" target="_blank"><img src="', $settings['images_url'], '/www.gif" title="', sprintf($txt['plugin_website'], $plugin['name']), '"></a>';

		if (!empty($plugin['author_email']))
			echo '
		&nbsp;<a href="mailto:', $plugin['author_email'], '"><img src="', $settings['images_url'], '/email_sm.gif" title="', $txt['plugin_author_email'], '"></a>';

		echo '</div>';

		// Plugin readmes
		if (!empty($plugin['readmes']))
		{
			echo '
		<div class="smalltext floatleft inline-block">', $txt['plugin_readmes'], ':';

			foreach ($plugin['readmes'] as $readme => $state)
				echo ' &nbsp;<a href="', $scripturl, '?action=admin;area=plugins;sa=readme;plugin=', rawurlencode($plugin['folder']), ';lang=', $readme, '" onclick="return reqWin(this);"><img src="', $settings['theme_url'], '/languages/Flag.', $readme, '.png"></a>';

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
	<form action="', $scripturl, '?action=admin;area=plugins;sa=remove;plugin=', $_GET['plugin'], ';commit" method="post">
		<div class="windowbg2 wrc">
			<p><strong>', sprintf($txt['remove_plugin_desc'], $context['plugin_name']), '</strong></p>
			<p>', $txt['remove_plugin_blurb'], '</p>
			<fieldset>
				<legend>', $txt['remove_plugin_nodelete'], '</legend>
				', $txt['remove_plugin_nodelete_desc'], '<br>
				<input name="nodelete" type="submit" class="save floatright" value="', $txt['remove_plugin_nodelete'], '">
			</fieldset>
			<br>
			<fieldset>
				<legend>', $txt['remove_plugin_delete'], '</legend>
				', $txt['remove_plugin_delete_desc'], '<br>
				<input name="delete" type="submit" class="delete floatright" value="', $txt['remove_plugin_delete'], '">
			</fieldset>';

	echo '
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">
	</form>
	<br class="clear">';
}

?>