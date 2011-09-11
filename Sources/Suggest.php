<?php
/**
 * Wedge
 *
 * Handles requests to action=suggest, for the purpose of supporting the auto-suggest functionality.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function Suggest($checkRegistered = null)
{
	global $context;

	// These are all registered types.
	$searchTypes = array(
		'member' => 'Member',
	);

	call_hook('suggest');

	if (!isset($_REQUEST['search']))
		return false;

	// If we're just checking the callback function is registered return true or false.
	if ($checkRegistered != null)
		return is_callable('Suggest_Search_' . $checkRegistered);

	checkSession('get');
	loadTemplate('Xml');

	// Any parameters?
	$context['search_param'] = isset($_REQUEST['search_param']) ? unserialize(base64_decode($_REQUEST['search_param'])) : array();

	if (!isset($_REQUEST['suggest_type'], $searchTypes[$_REQUEST['suggest_type']]))
		$_REQUEST['suggest_type'] = 'member';

	$function = 'Suggest_Search_' . $searchTypes[$_REQUEST['suggest_type']];
	loadBlock('generic_xml');
	$context['xml_data'] = $function();
}

// Search for a member - by real_name or member_name by default.
function Suggest_Search_Member()
{
	global $user_info, $txt, $context;

	$_REQUEST['search'] = trim(westr::strtolower($_REQUEST['search'])) . '*';
	$_REQUEST['search'] = strtr($_REQUEST['search'], array('%' => '\%', '_' => '\_', '*' => '%', '?' => '_', '&#038;' => '&amp;'));

	// Find the member.
	$request = wesql::query('
		SELECT id_member, real_name
		FROM {db_prefix}members
		WHERE real_name LIKE {string:search}' . (!empty($context['search_param']['buddies']) ? '
			AND id_member IN ({array_int:buddy_list})' : '') . '
			AND is_activated IN (1, 11)
		LIMIT ' . (westr::strlen($_REQUEST['search']) <= 2 ? '100' : '800'),
		array(
			'buddy_list' => $user_info['buddies'],
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

?>