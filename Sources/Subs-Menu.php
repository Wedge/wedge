<?php
/**
 * This file contains a standard way of displaying side/drop down menus for Wedge.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

// Create a menu...
function createMenu($menuData, $menuOptions = array())
{
	global $context, $txt;

	// Work out where we should get our images from.
	$context['menu_image_path'] = isset($context['menu_image_path']) ? $context['menu_image_path'] : ASSETS . '/admin';

	/* Note menuData is an array of form:

		Possible fields:
			For Section:
				string $title:		Section title.
				bool $enabled:		Should section be shown?
				array $areas:		Array of areas within this section.
				array $permission:	Permission required to access the whole section.

			For Areas:
				array $permission:	Array of permissions to determine who can access this area.
				string $label:		Optional text string for link (Otherwise $txt[$index] will be used)
				string $file:		Name of source file required for this area.
				string $function:	Function to call when area is selected.
				string $custom_url:	URL to use for this menu item.
				bool $enabled:		Should this area even be accessible?
				bool $hidden:		Should this area be visible?
				string $select:		If set this item will not be displayed - instead the item indexed here shall be.
				array $subsections:	Array of subsections from this area.

			For Subsections:
				string 0:			Text label for this subsection.
				array 1:			Array of permissions to check for this subsection.
				bool 2:				Is this the default subaction - if not set for any will default to first...
				bool enabled:		Bool to say whether this should be enabled or not.
	*/

	// Every menu gets a unique ID, these are shown in first in, first out order.
	$context['max_menu_id'] = isset($context['max_menu_id']) ? $context['max_menu_id'] + 1 : 0;

	// This will be all the data for this menu - and we'll make a shortcut to it to aid readability here.
	$context['menu_data_' . $context['max_menu_id']] = array();
	$menu_context =& $context['menu_data_' . $context['max_menu_id']];

	// What is the general action of this menu (i.e. <URL>?action=XXXX.
	$menu_context['current_action'] = isset($menuOptions['action']) ? $menuOptions['action'] : $context['action'];

	// What is the current area selected?
	if (isset($menuOptions['current_area']) || isset($_GET['area']))
		$menu_context['current_area'] = isset($menuOptions['current_area']) ? $menuOptions['current_area'] : $_GET['area'];

	// Build a list of additional parameters that should go in the URL.
	$menu_context['extra_parameters'] = '';
	if (!empty($menuOptions['extra_url_parameters']))
		foreach ($menuOptions['extra_url_parameters'] as $key => $value)
			$menu_context['extra_parameters'] .= ';' . $key . '=' . $value;

	// Only include the session ID in the URL if it's strictly necessary.
	if (empty($menuOptions['disable_url_session_check']))
		$menu_context['extra_parameters'] .= ';' . $context['session_query'];

	$include_data = array();

	// Now setup the context correctly.
	foreach ($menuData as $section_id => &$section)
	{
		// Is this enabled - or has as permission check - which fails?
		if ((isset($section['enabled']) && $section['enabled'] == false) || (isset($section['permission']) && !allowedTo($section['permission'])))
			continue;

		// Now we cycle through the sections to pick the right area.
		foreach ($section['areas'] as $area_id => &$area)
		{
			$here =& $menu_context['sections'][$section_id]['areas'][$area_id];
			if (is_numeric($area_id))
				continue;

			// Can we do this?
			if ((!isset($area['enabled']) || $area['enabled'] != false) && (empty($area['permission']) || allowedTo($area['permission'])))
			{
				// Add it to the context... if it has some form of name!
				if (isset($area['label']) || (isset($txt[$area_id]) && !isset($area['select'])))
				{
					// If we haven't got an area then the first valid one is our choice.
					if (!isset($menu_context['current_area']))
					{
						$menu_context['current_area'] = $area_id;
						$include_data = $area;
					}

					// If this is hidden from view don't do the rest.
					if (empty($area['hidden']))
					{
						if (!isset($menu_context['sections'][$section_id]['title']))
						{
							$menu_context['sections'][$section_id]['title'] = $section['title'];
							$menu_context['sections'][$section_id]['id'] = $section_id;
							if (isset($section['notice']))
								$menu_context['sections'][$section_id]['notice'] = $section['notice'];
						}

						$here = array('label' => isset($area['label']) ? $area['label'] : $txt[$area_id]);
						if (isset($area['notice']))
							$here['notice'] = $area['notice'];

						// Does this area have a custom URL?
						if (isset($area['custom_url']))
							$here['url'] = $area['custom_url'];

						// Does it have its own icon and bigicon?
						$here['icon'] = empty($area['icon']) ? '' : (strpos($area['icon'], '://') !== false ? '<img src="' . $area['icon'] . '">' : '<div class="admenu_icon_' . $area_id . '"></div>') . '&nbsp;&nbsp;';
						$here['bigicon'] = empty($area['bigicon']) ? '' : '<img src="' . (strpos($area['bigicon'], '://') === false ? $context['menu_image_path'] . '/' . $area['bigicon'] : $area['bigicon']) . '">';

						// Did it have subsections?
						if (!empty($area['subsections']))
						{
							$first_sa = null;
							$here['subsections'] = array();
							foreach ($area['subsections'] as $sa => $sub)
							{
								if (!empty($sub) && (empty($sub[1]) || allowedTo($sub[1])) && (!isset($sub['enabled']) || !empty($sub['enabled'])))
								{
									if ($first_sa === null)
										$first_sa = $sa;

									$here['subsections'][$sa] = array('label' => $sub[0]);
									// Custom URL?
									if (isset($sub['url']))
										$here['subsections'][$sa]['url'] = $sub['url'];

									// A bit complicated - but is this set?
									if ($menu_context['current_area'] == $area_id)
									{
										// Is this the current subsection?
										if (isset($_REQUEST['sa']) && $_REQUEST['sa'] == $sa)
											$menu_context['current_subsection'] = $sa;
										// Otherwise is it the default?
										elseif (!isset($menu_context['current_subsection']) && $first_sa == $sa)
											$menu_context['current_subsection'] = $sa;
									}
								}
								// Mark it as disabled/deleted...
								else
									$here['subsections'][$sa] = '';
							}
						}
					}
				}

				// Is this the current section?
				if ($menu_context['current_area'] == $area_id && empty($found_section))
				{
					// Only do this once?
					$found_section = true;

					// Update the context if required - as we can have areas pretending to be others. ;)
					$menu_context['current_section'] = $section_id;
					if (isset($area['select']))
						$menu_context['current_area'] = $_GET['area'] = $area['select'];
					else
						$menu_context['current_area'] = $area_id;
					// This will be the data we return.
					$include_data = $area;
				}
				// Make sure we have something in case it's an invalid area.
				elseif (empty($found_section) && empty($include_data))
				{
					$menu_context['current_section'] = $section_id;
					$backup_area = isset($area['select']) ? $area['select'] : $area_id;
					$include_data = $area;
				}
			}
			if (empty($here))
				unset($menu_context['sections'][$section_id]['areas'][$area_id]);
		}
	}

	// Should we use a custom base url, or use the default?
	$menu_context['base_url'] = isset($menuOptions['base_url']) ? $menuOptions['base_url'] : '<URL>?action=' . $menu_context['current_action'];

	// If we didn't find the area we were looking for go to a default one.
	if (isset($backup_area) && empty($found_section))
		$menu_context['current_area'] = $backup_area;

	// If still no data then return - nothing to show!
	if (empty($menu_context['sections']))
	{
		// Never happened!
		$context['max_menu_id']--;
		if ($context['max_menu_id'] == 0)
			unset($context['max_menu_id']);

		return false;
	}

	// Clean up orphan separators
	foreach ($menu_context['sections'] as &$section)
	{
		$areas =& $section['areas'];

		while (reset($areas) == '' && array_shift($areas) !== null);
		while (end($areas) == '' && array_pop($areas) !== null);

		$ex = false;
		foreach ($areas as $id => &$area)
		{
			// Submenu separators make processing 3x slower. But it's not even a millisecond...
			if (!empty($area['subsections']))
			{
				while (reset($area['subsections']) === '' && array_shift($area['subsections']) !== null);
				while (end($area['subsections']) === '' && array_pop($area['subsections']) !== null);
				$exs = false;
				foreach ($area['subsections'] as $ids => &$sub)
				{
					if (!empty($exs) && is_numeric($ids))
						unset($areas[$id][$ids]);
					$exs = is_numeric($ids);
				}
			}
			if ($ex && is_numeric($id))
				unset($areas[$id]);
			$ex = is_numeric($id);
		}

		while (reset($areas) == '' && array_shift($areas) !== null);
		while (end($areas) == '' && array_pop($areas) !== null);
	}

	// Almost there - load the template and add to the template layers.
	if (!AJAX)
	{
		loadTemplate(isset($menuOptions['template_name']) ? $menuOptions['template_name'] : 'GenericMenu');
		$menu_context['template_name'] = (isset($menuOptions['template_name']) ? $menuOptions['template_name'] : 'generic_menu') . '_dropdown';
		wetem::add('top', $menu_context['template_name']);
		wetem::add('top', 'generic_tabs');
	}

	// Check we had something - for sanity sake.
	if (empty($include_data))
		return false;

	// Finally - return information on the selected item.
	$include_data += array(
		'current_action' => $menu_context['current_action'],
		'current_area' => $menu_context['current_area'],
		'current_section' => $menu_context['current_section'],
		'current_subsection' => !empty($menu_context['current_subsection']) ? $menu_context['current_subsection'] : '',
	);

	return $include_data;
}

// Delete a menu.
function destroyMenu($menu_id = 'last')
{
	global $context;

	$menu_name = $menu_id == 'last' && isset($context['max_menu_id'], $context['menu_data_' . $context['max_menu_id']]) ? 'menu_data_' . $context['max_menu_id'] : 'menu_data_' . $menu_id;
	if (!isset($context[$menu_name]))
		return false;

	wetem::remove($context[$menu_name]['template_name']);
	unset($context[$menu_name]);
}
