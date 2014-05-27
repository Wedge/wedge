<?php
/**
 * Creates the generic sidebar/dropdown menu as used in the admin and profile areas.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

// This contains the html for the generic sidebar.
function template_generic_menu_sidebar()
{
	global $context;

	// Which menu are we rendering?
	$context['cur_menu_id'] = isset($context['cur_menu_id']) ? $context['cur_menu_id'] + 1 : 0;
	$menu_context =& $context['menu_data_' . $context['cur_menu_id']];

	// For every section that appears on the sidebar...
	$firstSection = true;
	foreach ($menu_context['sections'] as $section)
	{
		// Is this a section you can't see?
		if (empty($section['id']))
			continue;

		// Show the section header - and pump up the line spacing for readability.
		echo '
	<section>
		<we:title>';

		echo '
			', $section['title'], '
		</we:title>
		<ul class="left_menu">';

		// For every area of this section show a link to that area (bold if it's currently selected.)
		foreach ($section['areas'] as $id => $area)
		{
			// Not supposed to be printed?
			if (empty($area['label']))
				continue;

			echo '<li>';

			// Is this the current area, or just some area?
			if ($id == $menu_context['current_area'])
				echo '<strong><a href="', isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $id, $menu_context['extra_parameters'], '">', $area['label'], '</a></strong>';
			else
				echo '<a href="', isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $id, $menu_context['extra_parameters'], '">', $area['label'], '</a>';

			echo '</li>';
		}

		echo '
		</ul>
	</section>';

		$firstSection = false;
	}
}

// This contains the html for the generic dropdown menu.
function template_generic_menu_dropdown()
{
	global $context;

	// Which menu are we rendering?
	$context['cur_menu_id'] = isset($context['cur_menu_id']) ? $context['cur_menu_id'] + 1 : 0;
	$menu_context =& $context['menu_data_' . $context['cur_menu_id']];

	echo '
	<ul id="amen', $context['cur_menu_id'] ? '_' . $context['cur_menu_id'] : '', '" class="css menu">';

	// IE6 needs an extra space to avoid breaking layout. Ah ah.
	$end_a = we::is('ie6') ? ' </a>' : '</a>';

	// Main areas first.
	foreach ($menu_context['sections'] as $section)
	{
		// Is this a section you can't see?
		if (empty($section['id']))
			continue;

		echo '<li', $section['id'] == $menu_context['current_section'] ? ' class="chosen"' : '', '><h4>', $section['title'],
			!empty($section['notice']) ? '<div class="note">' . $section['notice'] . '</div>' : '', '</h4>', !empty($section['areas']) ? '<ul>' : '';

		// For every area of this section show a link to that area (bold if it's currently selected.)
		foreach ($section['areas'] as $id => $area)
		{
			// Not supposed to be printed?
			if (empty($area['label']))
			{
				if (empty($area))
					echo '<li class="sep"><a><hr></a></li>';
				continue;
			}

			$class = ($id == $menu_context['current_area'] ? ' active' : '') . (!empty($area['subsections']) ? ' subsection' : '');
			$class = empty($class) ? '' : ' class="' . ltrim($class) . '"';

			echo '<li', $class, '><a href="', (isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $id), $menu_context['extra_parameters'], '">',
					$area['icon'], $area['label'], !empty($area['notice']) ? '<span class="note">' . $area['notice'] . '</span>' : '', $end_a;

			// Is there any subsections?
			if (!empty($area['subsections']))
			{
				echo '<ul>';

				foreach ($area['subsections'] as $sa => $sub)
				{
					if (empty($sub))
					{
						if (is_numeric($sa))
							echo '<li class="sep"><a><hr></a></li>';
						continue;
					}

					$url = isset($sub['url']) ? $sub['url'] : (isset($area['url']) ? $area['url'] : $menu_context['base_url'] . ';area=' . $id) . ';sa=' . $sa;

					echo '<li', !empty($sub['selected']) ? ' class="active"' : '', '><a href="', $url, $menu_context['extra_parameters'], '">', $sub['label'], '</a></li>';
				}

				echo '</ul>';
			}
			echo '</li>';
		}
		echo !empty($section['areas']) ? '</ul>' : '', '</li>';
	}

	echo '
	</ul>';
}

// Some code for showing a tabbed view.
function template_generic_tabs()
{
	global $context, $txt;

	// Search for the current area. Make sure we're playing with the correct menu!
	$menu_context =& $context['menu_data_' . (isset($context['cur_menu_id']) ? $context['cur_menu_id'] : 0)];

	if (!empty($menu_context['sections']))
		foreach ($menu_context['sections'] as $section)
			foreach ($section['areas'] as $id => $area)
				if ($id === $menu_context['current_area'])
				{
					$tabs = isset($area['subsections']) ? $area['subsections'] : array();
					if (isset($area['url']))
						foreach ($tabs as $key => &$val)
							$val['url'] = $area['url'] . ';sa=' . $key;
					break;
				}

	// If there are no "tabs" set up, don't waste time here.
	if (empty($tabs))
		return;

	// Handy shortcut.
	$tab_context =& $menu_context['tab_data'];

	echo '
	<we:cat>';

	// Exactly how many tabs do we have?
	foreach ($tabs as $id => $tab)
	{
		// Is this menu item a separator, or disabled?
		if (empty($tab) || !empty($tab['disabled']))
			continue;

		// Did this not even exist - or do we not have a label?
		if (!isset($tab_context['tabs'][$id]))
			$tab_context['tabs'][$id] = array('label' => $tab['label']);
		elseif (!isset($tab_context['tabs'][$id]['label']))
			$tab_context['tabs'][$id]['label'] = $tab['label'];

		// Has a custom URL been defined in the main structure?
		if (isset($tab['url']) && !isset($tab_context['tabs'][$id]['url']))
			$tab_context['tabs'][$id]['url'] = $tab['url'];
		// Has it been deemed selected?
		if (!empty($tab['is_selected']))
			$tab_context['tabs'][$id]['is_selected'] = true;
		// Does it have its own help?
		if (!empty($tab['help']))
			$tab_context['tabs'][$id]['help'] = $tab['help'];
	}

	// Find the selected tab
	foreach ($tab_context['tabs'] as $sa => $tab)
	{
		if (!empty($tab['is_selected']) || (isset($menu_context['current_subsection']) && $menu_context['current_subsection'] == $sa))
		{
			$selected_tab = $tab;
			$tab_context['tabs'][$sa]['is_selected'] = true;
		}
	}

	// Show an icon and/or a help item?
	if (!empty($selected_tab['icon']) || !empty($tab_context['icon']))
		echo '
		<img src="', ASSETS, '/icons/', !empty($selected_tab['icon']) ? $selected_tab['icon'] : $tab_context['icon'], '">';

	if (!empty($selected_tab['help']) || !empty($tab_context['help']))
		echo '
		<a href="<URL>?action=help;in=', !empty($selected_tab['help']) ? $selected_tab['help'] : $tab_context['help'], '" onclick="return reqWin(this);" class="help" title="', $txt['help'], '"></a>';

	echo '
		', $tab_context['title'], '
	</we:cat>';

	if (!empty($selected_tab['description']) || !empty($tab_context['description']))
		echo '
	<p class="description">
		', !empty($selected_tab['description']) ? $selected_tab['description'] : $tab_context['description'], '
	</p>';

	template_show_generic_tabs($tab_context['tabs'], $menu_context);
}

function template_show_generic_tabs(&$tabs, &$menu_context = array(), $class = '')
{
	// The tabs.
	echo '
	<ul class="context menu', $class ? ' ' . $class : '', '">';

	// Print out all the items in this tab.
	// If you're showing custom tabs, make sure to provide 'url' and 'label' for each tab.
	foreach ($tabs as $sa => $tab)
		if (!empty($tab) && empty($tab['disabled']) && isset($tab['label']))
			echo '<li class="', !empty($tab['is_selected']) ? 'chosen ' : '', 'nodrop"><h4><a href="',
				isset($tab['url']) ? $tab['url'] : $menu_context['base_url'] . ';area=' . $menu_context['current_area'] . ';sa=' . $sa,
				isset($menu_context['extra_parameters']) ? $menu_context['extra_parameters'] : '', '">', $tab['label'], '</a></h4></li>';

	// the end of tabs
	echo '
	</ul>';
}
