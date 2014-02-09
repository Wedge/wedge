<?php
/**
 * Handles requests to action=suggest, for the purpose of supporting the auto-suggest functionality.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function Suggest($checkRegistered = null)
{
	global $context;

	call_hook('suggest');

	if (!isset($_REQUEST['search']))
		return false;

	// If we're just checking the callback function is registered return true or false.
	if ($checkRegistered != null)
		return is_callable('suggest_search_' . $checkRegistered);

	checkSession('get');

	// Any parameters?
	$context['search_param'] = isset($_REQUEST['search_param']) ? unserialize(base64_decode($_REQUEST['search_param'])) : array();

	if (!isset($_REQUEST['suggest_type']))
		$_REQUEST['suggest_type'] = 'member';

	$function = 'suggest_search_' . $_REQUEST['suggest_type'];
	if (!is_callable($function))
		return_raw();

	$context['xml_data'] = $function();

	return_xml(suggest_xml_recursive($context['xml_data'], 'we', '', -1));
}

// Recursive function for displaying generic XML data.
function suggest_xml_recursive($xml_data, $parent_ident, $child_ident, $level)
{
	// This is simply for neat indentation.
	$level++;

	$str = "\n" . str_repeat("\t", $level) . '<' . $parent_ident . '>';

	foreach ($xml_data as $key => $data)
	{
		// A group?
		if (is_array($data) && isset($data['identifier']))
			$str .= suggest_xml_recursive($data['children'], $key, $data['identifier'], $level);
		// An item...
		elseif (is_array($data) && isset($data['value']))
		{
			$str .= "\n" . str_repeat("\t", $level) . '<' . $child_ident;

			if (!empty($data['attributes']))
				foreach ($data['attributes'] as $k => $v)
					$str .= ' ' . $k . '="' . $v . '"';
			$str .= '><![CDATA[' . cleanXml($data['value']) . ']]></' . $child_ident . '>';
		}
	}

	return $str . "\n" . str_repeat("\t", $level) . '</' . $parent_ident . '>';
}

// Search for a member - by real_name or member_name by default.
function suggest_search_member()
{
	global $context;

	$_REQUEST['search'] = trim(westr::strtolower($_REQUEST['search'])) . '*';
	$_REQUEST['search'] = strtr($_REQUEST['search'], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

	// Find the member.
	$request = wesql::query('
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE real_name LIKE {string:search}' . (!empty($context['search_param']['buddies']) ? '
			AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11, 21)
		LIMIT ' . (westr::strlen($_REQUEST['search']) <= 2 ? '100' : '800'),
		array(
			'buddy_list' => we::$user['buddies'],
			'search' => $_REQUEST['search'],
		)
	);
	$xml_data = array(
		'items' => array(
			'identifier' => 'item',
			'children' => array(),
		),
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$row['real_name'] = strtr($row['real_name'], array('&amp;' => '&#038;', '&lt;' => '&#060;', '&gt;' => '&#062;', '&quot;' => '&#034;'));

		$xml_data['items']['children'][] = array(
			'attributes' => array(
				'id' => $row['id_member'],
			),
			'value' => $row['real_name'],
		);
	}
	wesql::free_result($request);

	return $xml_data;
}
