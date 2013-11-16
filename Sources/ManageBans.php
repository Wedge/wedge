<?php
/**
 * All functionality related to banning members.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/* This file contains all the functions used for the ban center.

*/

// !!! Needs documentation.

function ManageBan()
{
	global $context, $txt;

	isAllowedTo('manage_bans');

	loadTemplate('ManageBans');
	loadLanguage('ManageBans');

	$subActions = array(
		'hard' => 'createBanList',
		'soft' => 'createBanList',
		'add' => 'BanListAdd',
		'edit' => 'BanListEdit',
		'settings' => 'BanListSettings',
	);

	// Default the sub-action to 'view ban list'.
	$context['sub_action'] = isset($_REQUEST['sa'], $subActions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'hard';

	$context['page_title'] = $txt['ban_title'];

	// Tabs for browsing the different ban functions.
	$context[$context['admin_menu_name']]['tab_data'] = array(
		'title' => $txt['ban_title'],
		'description' => $txt['ban_general_description'],
		'tabs' => array(
			'hard' => array(
				'description' => $txt['ban_description_hard'],
				'href' => '<URL>?action=admin;area=ban;sa=hard',
				'is_selected' => $context['sub_action'] == 'hard',
			),
			'soft' => array(
				'description' => $txt['ban_description_soft'],
				'href' => '<URL>?action=admin;area=ban;sa=soft',
				'is_selected' => $context['sub_action'] == 'soft',
			),
			'add' => array(
				'description' => $txt['ban_description_add'],
				'href' => '<URL>?action=admin;area=ban;sa=add',
				'enabled' => $context['sub_action'] != 'edit',
				'is_selected' => $context['sub_action'] == 'add',
			),
			'edit' => array(
				'description' => $txt['ban_description_edit'],
				'href' => !empty($_REQUEST['ban']) ? '<URL>?action=admin;area=ban;sa=edit;ban=' . ((int) $_REQUEST['ban']) : '<URL>?action=admin;area=ban;sa=add',
				'enabled' => $context['sub_action'] == 'edit',
				'is_selected' => $context['sub_action'] == 'edit',
			),
			'settings' => array(
				'description' => $txt['ban_description_settings'],
				'href' => '<URL>?action=admin;area=ban;sa=settings',
				'is_selected' => $context['sub_action'] == 'settings',
			),
		),
	);

	// Call the right function for this sub-action.
	$subActions[$context['sub_action']]();
}

function createBanList()
{
	global $context, $txt;

	$ban_is_hard = $context['sub_action'] == 'hard';

	// And delete any?
	if (!empty($_POST['removeBans']) && !empty($_POST['remove']) && is_array($_POST['remove']))
	{
		checkSession();

		// Make sure every entry is a proper integer.
		foreach ($_POST['remove'] as $index => $ban_id)
			$_POST['remove'][(int) $index] = (int) $ban_id;

		// Unban them all!
		wesql::query('
			DELETE FROM {db_prefix}bans
			WHERE id_ban IN ({array_int:ban_list})',
			array(
				'ban_list' => $_POST['remove'],
			)
		);

		// Check if anyone is no longer banned that previously was.
		updateBannedMembers();
	}

	$listOptions = array(
		'id' => 'ban_list',
		'items_per_page' => 20,
		'base_href' => '<URL>?action=admin;area=ban;sa=' . ($ban_is_hard ? 'hard' : 'soft'),
		'default_sort_col' => 'added',
		'default_sort_dir' => 'desc',
		'get_items' => array(
			'function' => 'list_getBansByType',
			'params' => array('hard_ban' => $ban_is_hard),
		),
		'get_count' => array(
			'function' => 'list_numBansByType',
			'params' => array('hard_ban' => $ban_is_hard),
		),
		'no_items_label' => $txt['ban_no_entries'],
		'columns' => array(
			'name' => array(
				'header' => array(
					'value' => $txt['ban_type'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;
						return \'<div class="ban_items ban_items_\' . $rowData[\'ban_type\'] . \'" title="\' . $txt[\'ban_type_\' . $rowData[\'ban_type\']] . \'"></div>\';
					'),
				),
				'sort' => array(
					'default' => 'ban_type',
					'reverse' => 'ban_type DESC',
				),
			),
			'ban_content' => array(
				'header' => array(
					'value' => $txt['ban_content'],
					'class' => 'left',
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $user_profile, $txt, $settings;
						$extra = !empty($rowData[\'extra\']) ? @unserialize($rowData[\'extra\']) : array();

						switch($rowData[\'ban_type\'])
						{
							case \'id_member\':
								$uid = (int) $rowData[\'ban_content\'];
								if (isset($user_profile[$uid]))
									return sprintf($txt[\'ban_id_member_is\'], \'<URL>?action=profile;u=\' . $uid, $user_profile[$uid][\'real_name\']);
								else
									return \'<em>\' . sprintf($txt[\'ban_invalid_member\'], $uid) . \'</em>\';
								break;
							case \'member_name\':
								$case_sens = !empty($extra[\'case_sens\']) ? $txt[\'ban_member_names_case_matters\'] : \'\';
								$type = !empty($extra[\'type\']) && in_array($extra[\'type\'], array(\'beginning\', \'containing\', \'ending\')) ? $extra[\'type\'] : \'matching\';
								return sprintf($txt[\'ban_member_names_\' . $type], $rowData[\'ban_content\'], $case_sens);
								break;
							case \'email\':
								if (strpos($rowData[\'ban_content\'], \'*@\') === 0)
									return sprintf($txt[\'ban_entire_domain\'], substr($rowData[\'ban_content\'], 2));
								elseif (strpos($rowData[\'ban_content\'], \'@*\') === 0)
									return sprintf($txt[\'ban_entire_tld\'], substr($rowData[\'ban_content\'], 2));
								else
								{
									if (!empty($extra[\'gmail_style\']))
									{
										list ($user, $domain) = explode(\'@\', $rowData[\'ban_content\']);
										if (strpos($user, \'+\') !== false)
											list ($user, $label) = explode(\'+\', $user);

										$user = str_replace(\'.\', \'\', $user);
										return sprintf($txt[\'ban_gmail_style_email\'], $user, $domain) . \' <a href="<URL>?action=help;in=ban_gmail_style" onclick="return reqWin(this);" class="help"></a>\';
									}
									else
										return $rowData[\'ban_content\'];
								}
								break;
							case \'ip_address\':
								switch (strlen($rowData[\'ban_content\']))
								{
									case 32: // single address
										return format_ip($rowData[\'ban_content\']);
									case 65: // range
										list ($start, $end) = explode(\'-\', $rowData[\'ban_content\']);
										return format_ip($start) . \' - \' . format_ip($end);
								}
								return $rowData[\'ban_content\'];
								break;
							case \'hostname\':
								if (strpos($rowData[\'ban_content\'], \'*.\') === 0)
								{
									$domain = substr($rowData[\'ban_content\'], 2);
									// We might have stripped too much, let us check
									if (strpos($domain, \'.\') === false)
										$domain = \'*.\' . $domain;
									$response = sprintf($txt[\'ban_entire_hostname\'], $domain);
								}
								else
									$response = $rowData[\'ban_content\'];

								if (!empty($settings[\'disableHostnameLookup\']))
									$response = \'<span class="error">\' . $response . \'</span> <a href="<URL>?action=help;in=no_hostname_ban" class="help" onclick="return reqWin(this);"></a>\';
								return $response;
								break;
						}
					'),
				),
			),
			'reason' => array(
				'header' => array(
					'value' => $txt['ban_reason'],
					'class' => 'left',
				),
				'data' => array(
					'db' => 'ban_reason',
					'class' => 'smalltext',
				),
				'sort' => array(
					'default' => 'LENGTH(ban_reason) > 0 DESC, ban_reason',
					'reverse' => 'LENGTH(ban_reason) > 0, ban_reason DESC',
				),
			),
			'added' => array(
				'header' => array(
					'value' => $txt['ban_added'],
				),
				'data' => array(
					'timeformat' => 'added',
				),
				'sort' => array(
					'default' => 'added',
					'reverse' => 'added DESC',
				),
			),
			'member_added' => array(
				'header' => array(
					'value' => $txt['ban_added_by'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $txt;
						return empty($rowData[\'member_added\']) ? $txt[\'not_applicable\'] : \'<a href="<URL>?action=profile;u=\' . $rowData[\'member_added\'] . \'">\' . $rowData[\'member_name\'] . \'</a>\';'
					),
				),
				'sort' => array(
					'default' => 'member_added',
					'reverse' => 'member_added DESC',
				),
			),
			'actions' => array(
				'header' => array(
					'value' => '',
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $settings, $txt;
						if (empty($settings[\'disableHostnameLookup\']) || $rowData[\'ban_type\'] != \'hostname\')
							return sprintf(\'<a href="<URL>?action=admin;area=ban;sa=edit;ban=%1$d">%2$s</a>\', $rowData[\'id_ban\'], $txt[\'modify\']);
						else
							return \'\';
					'),
					'style' => 'text-align: center;',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);">',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[]" value="%1$d">',
						'params' => array(
							'id_ban' => false,
						),
					),
					'style' => 'text-align: center',
				),
			),
		),
		'form' => array(
			'href' => '<URL>?action=admin;area=ban;sa=' . ($ban_is_hard ? 'hard' : 'soft'),
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="removeBans" value="' . $txt['ban_remove_selected'] . '" onclick="return ask(' . JavaScriptEscape($txt['ban_remove_selected_confirm']) . ', e);" class="delete">',
				'style' => 'text-align: right;',
			),
		),
	);

	loadSource('Subs-List');
	createList($listOptions);

	wetem::load('show_list');
	$context['default_list'] = 'ban_list';
}

function BanListAdd()
{
	global $txt, $context, $settings;

	$context['ban_details'] = array(
		'id_ban' => 0,
		'hardness' => 'soft',
		'ban_type' => '',
		'ban_content' => '',
		'ban_reason' => '',
		'extra' => array(),
	);

	$context['ban_types'] = array('id_member', 'member_name', 'email', 'ip_address');
	if (empty($settings['disableHostnameLookup']))
		$context['ban_types'][] = 'hostname';

	$context['page_title'] = $txt['ban_add'];
	wetem::load('ban_details');

	add_js_file('scripts/suggest.js');
	add_js('
	new weAutoSuggest({
		', min_chars(), ',
		bItemList: true,
		sControlId: \'ban_id_member_content\',
		sPostName: \'ban_id_member_content\',
		bItemList: false
	});');
}

function BanListEdit()
{
	global $txt, $context, $settings, $user_profile;

	$_REQUEST['ban'] = isset($_REQUEST['ban']) ? (int) $_REQUEST['ban'] : 0;

	if (!empty($_REQUEST['ban']))
	{
		$request = wesql::query('
			SELECT id_ban, hardness, ban_type, ban_content, ban_reason, extra
			FROM {db_prefix}bans
			WHERE id_ban = {int:ban}',
			array(
				'ban' => $_REQUEST['ban'],
			)
		);
		if (wesql::num_rows($request) != 0)
			$context['ban_details'] = wesql::fetch_assoc($request);
		wesql::free_result($request);
		$context['ban_details']['hardness'] = !empty($context['ban_details']['hardness']) ? 'hard' : 'soft';

		if (!empty($settings['disableHostnameLookup']) && $context['ban_details']['ban_type'] == 'hostname')
			fatal_lang_error('ban_no_modify', false);

		if (isset($_REQUEST['delete']) && !empty($context['ban_details']['id_ban']))
		{
			wesql::query('
				DELETE FROM {db_prefix}bans
				WHERE id_ban = {int:ban}',
				array(
					'ban' => $context['ban_details']['id_ban'],
				)
			);
			updateBannedMembers();
			redirectexit('action=admin;area=ban;sa=' . $context['ban_details']['hardness']);
		}
	}

	if (!empty($context['ban_details']['extra']))
		$context['ban_details']['extra'] = @unserialize($context['ban_details']['extra']);

	$context['ban_types'] = array('id_member', 'member_name', 'email', 'ip_address');
	if (empty($settings['disableHostnameLookup']))
		$context['ban_types'][] = 'hostname';

	$context['page_title'] = $txt['ban_edit'];
	wetem::load('ban_details');

	// OK, so are we saving?
	if (isset($_GET['save']))
	{
		$context['errors'] = array();

		$context['ban_details'] = array(
			'id_ban' => $_REQUEST['ban'],
			'hardness' => isset($_POST['hardness']) && $_POST['hardness'] == 'hard' ? 'hard' : 'soft',
			'ban_type' => !empty($_POST['ban_type']) ? $_POST['ban_type'] : '',
			'ban_reason' => !empty($_POST['ban_reason']) ? westr::safe($_POST['ban_reason'], ENT_QUOTES) : '',
			'extra' => array(),
			'ban_content' => '',
		);

		if (!empty($_POST['ban_message']) && trim(westr::safe($_POST['ban_message']) !== ''))
			$context['ban_details']['extra']['message'] = westr::safe($_POST['ban_message'], ENT_QUOTES);

		switch ($context['ban_details']['ban_type'])
		{
			case 'id_member':
				if (empty($_POST['ban_id_member_content']))
					$context['errors']['ban_invalid_member'] = $txt['ban_invalid_member'];
				else
				{
					// Attempt to find the member name given.
					loadSource('Subs-Auth');
					$found_members = findMembers(array($_POST['ban_id_member_content']));
					if (!empty($found_members))
					{
						$member = array_shift($found_members);
						$context['ban_details']['ban_content'] = $member['id']; // We only want the id_member, we'll requery it later though :/
					}
					else
						$context['errors']['ban_invalid_member'] = $txt['ban_invalid_member'];
				}
				break;
			case 'member_name':
				$context['ban_details']['extra'] = empty($_POST['ban_member_name_select']) || !in_array($_POST['ban_member_name_select'], array('begin', 'contain', 'end')) ? 'match' : $_POST['ban_member_name_select'];
				if (empty($_POST['ban_member_name_content']))
					$context['errors']['ban_invalid_membername'] = $txt['ban_invalid_membername'];
				else
					$context['ban_details']['ban_content'] = $_POST['ban_member_name_content'];
				if (!empty($_POST['ban_member_name_case_sens']))
					$context['ban_details']['extra']['case_sens'] = true;
				else
					unset($context['ban_details']['extra']['case_sens']);
				break;
			case 'email':
				if (!empty($_POST['ban_gmail_style']))
					$context['ban_details']['extra']['gmail_style'] = true;
				else
					unset($context['ban_details']['extra']['gmail_style']);

				if (empty($_POST['ban_type_email']) || $_POST['ban_type_email'] == 'specific')
				{
					$context['ban_details']['email_type'] = 'specific';
					if (!empty($_POST['ban_email_content']))
					{
						// We want to allow wildcards in the username (but not domain), and also use filter_var to make life easy.
						$email = $_POST['ban_email_content'];
						if (strpos($email, '@') !== false)
						{
							list ($user, $domain) = explode('@', $email);
							$user = str_replace('*', '', $user);
							if (filter_var($user . '@' . $domain, FILTER_VALIDATE_EMAIL))
								$context['ban_details']['ban_content'] = $email;
							else
							{
								$context['ban_details']['ban_content'] = '*@' . westr::safe($email, ENT_QUOTES);
								$context['errors']['ban_invalid_email'] = $txt['ban_invalid_email'];
							}
						}
						else
						{
							$context['ban_details']['ban_content'] = '*@' . westr::safe($email, ENT_QUOTES);
							$context['errors']['ban_invalid_email'] = $txt['ban_invalid_email'];
						}
					}
				}
				elseif ($_POST['ban_type_email'] == 'domain')
				{
					$context['ban_details']['email_type'] = 'domain';
					if (!empty($_POST['ban_email_content']))
					{
						// Strip anything before a leading @ just in case
						$email = trim($_POST['ban_email_content']);
						if ($pos = strrpos($email, '@') !== false)
							$email = substr($email, $pos);

						// Now validate the domain and if it is sane, reassemble and reset ready for the template
						if (filter_var('test@' . $email, FILTER_VALIDATE_EMAIL))
							$context['ban_details']['ban_content'] = '*@' . $email;
						else
						{
							// It wasn't valid. So, enforce that it is safe for redisplay, then flag as error.
							$context['ban_details']['ban_content'] = '*@' . westr::safe(trim($_POST['ban_email_content']), ENT_QUOTES);
							$context['errors']['ban_invalid_email'] = $txt['ban_invalid_email'];
						}
					}
				}
				elseif ($_POST['ban_type_email'] == 'tld')
				{
					$context['ban_details']['email_type'] = 'tld';
					if (!empty($_POST['ban_email_content']))
					{
						// Start by stripping any leading * (e.g. *.tld becomes .tld) and also check that we didn't get *.*.tld nonsense.
						$email = trim($_POST['ban_email_content']);
						if ($pos = strrpos($email, '*') !== false)
							$email = substr($email, $pos);
						$email = preg_replace('~\.+~', '.', $email);
						// Right, so now we should have .tld stuff only. This is not efficient?
						if (preg_match('~(\.?\pL(\pL|\d)*\.)*(\pL{2,})~i', $email))
							$context['ban_details']['ban_content'] = '@*' . ($email[0] != '.' ? '.' : '') . $email;
						else
						{
							// It wasn't valid. So, enforce that it is safe for redisplay, then flag as error.
							$context['ban_details']['ban_content'] = '@*' . westr::safe($email, ENT_QUOTES);
							$context['errors']['ban_invalid_email'] = 'ban_invalid_email';
						}
					}
				}

				if (empty($context['ban_details']['ban_content']))
					$context['errors']['ban_invalid_email'] = 'ban_invalid_email';

				break;
			case 'ip_address':
				$check = !empty($_POST['ban_ip_range']) ? array('start', 'end') : array('start');
				$results = array();
				if (empty($_POST['ban_type_ip']) || $_POST['ban_type_ip'] == 'ipv4')
				{
					foreach ($check as $check_item)
					{
						$ip = array();
						for ($i = 0; $i <= 3; $i++)
						{
							$item = 'ipv4_' . $check_item . '_' . $i;
							if (isset($_POST[$item]) && is_numeric($_POST[$item]))
								$ip[] = (int) $_POST[$item];
							else
								break;
						}
						if (count($ip) == 4)
						{
							$v = implode('.', $ip);
							if (filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
								$results[] = expand_ip($v);
						}
					}
				}
				else
				{
					foreach ($check as $check_item)
						if (!empty($_POST['ipv6_' . $check_item]) && filter_var($v, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
							$results[] = expand_ip($_POST['ipv6_' . $check_item]);
				}

				if (!empty($results))
				{
					sort($results);
					$context['ban_details']['ban_content'] = implode('-', $results);
				}
				else
					$context['errors']['ban_invalid_ip_address'] = $txt['ban_invalid_ip_address'];

				break;
			case 'hostname':
				if (!empty($_POST['ban_hostname_content']) && preg_match('~^(\*\.)?((\pL|\d)+\.)*(\pL{2,})$~i', $_POST['ban_hostname_content']))
					$context['ban_details']['ban_content'] = $_POST['ban_hostname_content'];
				else
				{
					$context['errors']['ban_invalid_hostname'] = $txt['ban_invalid_hostname'];
					$context['ban_details']['ban_content'] = !empty($_POST['ban_hostname_content']) ? westr::safe($_POST['ban_hostname_content'], ENT_QUOTES) : '';
				}
				break;
			default:
				$context['errors']['ban_invalid_type'] = $txt['ban_invalid_type'];
				break;
		}

		if (trim($context['ban_details']['ban_reason']) === '')
			$context['errors']['ban_invalid_reason'] = $txt['ban_invalid_reason'];

		// Successful? Save and exit, otherwise let this function just continue to show the editing area - to pull apart what we just did. Still, better this way.
		if (empty($context['errors']))
		{
			if (empty($context['ban_details']['id_ban']))
			{
				wesql::insert('insert',
					'{db_prefix}bans',
					array(
						'hardness' => 'int', 'ban_type' => 'string', 'ban_content' => 'string', 'ban_reason' => 'string',
						'extra' => 'string', 'added' => 'int', 'member_added' => 'int',
					),
					array(
						$context['ban_details']['hardness'] == 'hard' ? 1 : 0, $context['ban_details']['ban_type'], $context['ban_details']['ban_content'], $context['ban_details']['ban_reason'],
						!empty($context['ban_details']['extra']) ? serialize($context['ban_details']['extra']) : '', time(), we::$id,
					)
				);
			}
			else
			{
				wesql::insert('replace',
					'{db_prefix}bans',
					array(
						'id_ban' => 'int', 'hardness' => 'int', 'ban_type' => 'string', 'ban_content' => 'string',
						'ban_reason' => 'string', 'extra' => 'string', 'added' => 'int', 'member_added' => 'int',
					),
					array(
						$context['ban_details']['id_ban'], $context['ban_details']['hardness'] == 'hard' ? 1 : 0, $context['ban_details']['ban_type'], $context['ban_details']['ban_content'],
						$context['ban_details']['ban_reason'], !empty($context['ban_details']['extra']) ? serialize($context['ban_details']['extra']) : '', time(), we::$id,
					)
				);
			}

			updateBannedMembers();

			redirectexit('action=admin;area=ban;sa=' . $context['ban_details']['hardness']);
		}
	}

	// Did we find a ban?
	if (empty($context['ban_details']))
		redirectexit('action=admin;area=ban;sa=add');

	switch ($context['ban_details']['ban_type'])
	{
		case 'id_member':
			$loaded = loadMemberData((int) $context['ban_details']['ban_content'], false, 'minimal');
			if (!empty($loaded))
				$context['ban_details']['ban_member'] = $user_profile[$loaded[0]]['real_name'];
			break;
		case 'member_name':
			$context['ban_details']['ban_name'] = westr::safe($context['ban_details']['ban_content'], ENT_QUOTES);
			$context['ban_details']['name_type'] = !empty($context['ban_details']['extra']['type']) && in_array($context['ban_details']['extra']['type'], array('beginning', 'containing', 'ending')) ? $context['ban_details']['extra']['type'] : 'matching';
			break;
		case 'email':
			if (strpos($context['ban_details']['ban_content'], '*@') === 0)
			{
				$context['ban_details']['email_type'] = 'domain';
				$context['ban_details']['ban_email'] = substr($context['ban_details']['ban_content'], 2);
			}
			elseif (strpos($context['ban_details']['ban_content'], '@*') === 0)
			{
				$context['ban_details']['email_type'] = 'tld';
				$context['ban_details']['ban_email'] = substr($context['ban_details']['ban_content'], 2);
			}
			else
			{
				$context['ban_details']['email_type'] = 'specific';
				if (!empty($context['ban_details']['extra']['gmail_style']))
				{
					list ($user, $domain) = explode('@', $context['ban_details']['ban_content']);
					if (strpos($user, '+') !== false)
						list ($user, $label) = explode('+', $user);
					$user = str_replace('.', '', $user);
					$context['ban_details']['ban_email'] = $user . '@' . $domain;
				}
				else
					$context['ban_details']['ban_email'] = $context['ban_details']['ban_content'];
			}
			break;
		case 'ip_address':
			$context['ban_details']['ip_range'] = strlen($context['ban_details']['ban_content']) != 32;
			if ($context['ban_details']['ip_range'])
				$range = explode('-', $context['ban_details']['ban_content']);
			else
				$range = array($context['ban_details']['ban_content'], INVALID_IP); // It's a dummy value but we use it to keep the code sane-ish.

			$context['ban_details']['ip_octets'] = array();
			$items = array('start', 'end');
			if (is_ipv4($range[0]))
			{
				$context['ban_details']['ip_type'] = 'ipv4';
				foreach ($range as $key => $item)
					for ($i = 0; $i <= 3; $i++)
						$context['ban_details']['ip_octets'][$items[$key] . '_' . $i] = hexdec(substr($item, 24 + $i * 2, 2));
			}
			else
			{
				$context['ban_details']['ip_type'] = 'ipv6';
				foreach ($range as $key => $item)
					$context['ban_details']['ipv6'][$items[$key]] = format_ip($item);
			}
			break;
		case 'hostname':
			if (strpos($context['ban_details']['ban_content'], '*.') === 0)
			{
				$domain = substr($context['ban_details']['ban_content'], 2);
				// We might have stripped too much, let us check
				if (strpos($domain, '.') === false)
					$domain = '*.' . $domain;
				$context['ban_details']['hostname'] = $domain;
			}
			else
				$context['ban_details']['hostname'] = $context['ban_details']['ban_content'];
			break;
	}
}

function list_getBansByType($start, $items_per_page, $sort, $params)
{
	$ban_is_hard = !empty($params);
	$request = wesql::query('
		SELECT ba.id_ban, ba.ban_type, ba.ban_content, ba.ban_reason, ba.extra, ba.added, mem.id_member AS member_added, mem.member_name
		FROM {db_prefix}bans AS ba
			LEFT JOIN {db_prefix}members AS mem ON (ba.member_added = mem.id_member)
		WHERE hardness = {int:ban_hardness}
		ORDER BY {raw:sort}
		LIMIT {int:offset}, {int:limit}',
		array(
			'ban_hardness' => $ban_is_hard ? 1 : 0,
			'sort' => $sort,
			'offset' => $start,
			'limit' => $items_per_page,
		)
	);
	$bans = array();
	$members = array();
	while ($row = wesql::fetch_assoc($request))
	{
		$bans[$row['id_ban']] = $row;
		if ($row['ban_type'] == 'id_member')
			$members[] = (int) $row['ban_content'];
	}
	wesql::free_result($request);

	if (!empty($members))
		loadMemberData($members, false, 'minimal');
	return $bans;
}

function list_numBansByType($params)
{
	$ban_is_hard = !empty($params);
	$request = wesql::query('
		SELECT COUNT(*) AS num_bans
		FROM {db_prefix}bans
		WHERE hardness = {int:ban_hardness}',
		array(
			'ban_hardness' => $ban_is_hard ? 1 : 0,
		)
	);
	list ($numBans) = wesql::fetch_row($request);
	wesql::free_result($request);

	return $numBans;
}

function BanListSettings($return_config = false)
{
	global $txt, $context;

	loadSource('ManageServer');

	$context['page_title'] = $context['settings_title'] = $txt['ban_settings'];

	// These will be defined in ManageBans.language.php. But even if we came here via admin search, that will be loaded.
	$config_vars = array(
		array('percent', 'softban_blankpage', 'subtext' => $txt['softban_percent_subtext']),
		array('percent', 'softban_nosearch', 'subtext' => $txt['softban_percent_subtext']),
		'',
		array('percent', 'softban_redirect', 'subtext' => $txt['softban_redirect_subtext']),
		array('text', 'softban_redirect_url'),
		'',
		array('int', 'softban_delay_min', 'min' => 0, 'max' => 15),
		array('int', 'softban_delay_max', 'min' => 0, 'max' => 15, 'subtext' => $txt['softban_delay_max_subtext']),
		'',
		array('yesno', 'softban_disableregistration', 'subtext' => $txt['softban_disableregistration_desc']),
	);

	call_hook('settings_bans', array(&$config_vars, &$return_config));

	// Settings to add:
	// Flood time multipler
	// When a user is banned, add them to which group

	if ($return_config)
		return $config_vars;

	// Saving?
	if (isset($_GET['save']))
	{
		// Validate the URL. filter_var will do the brunt of the work, just it validates for any kind of URL, not just http ones.
		if (empty($_POST['softban_redirect_url']) || stripos($_POST['softban_redirect_url'], 'http') !== 0 || !filter_var($_POST['softban_redirect_url'], FILTER_VALIDATE_URL))
			$_POST['softban_redirect_url'] = '';

		// If they's done something odd like make the max more than the min...
		if (isset($_POST['softban_delay_min'], $_POST['softban_delay_max']) && (int) $_POST['softban_delay_max'] < (int) $_POST['softban_delay_min'])
		{
			$temp = $_POST['softban_delay_max'];
			$_POST['softban_delay_max'] = $_POST['softban_delay_min'];
			$_POST['softban_delay_min'] = $temp;
		}

		checkSession();
		saveDBSettings($config_vars);
		redirectexit('action=admin;area=ban;sa=settings');
	}

	$context['post_url'] = '<URL>?action=admin;area=ban;sa=settings;save';
	wetem::load('show_settings');
	prepareDBSettingContext($config_vars);
}

function updateBannedMembers()
{
	// Let's start by figuring out who is currently banned, then we can establish everything from there.
	$current_banned = array();
	$request = wesql::query('
		SELECT id_member, is_activated
		FROM {db_prefix}members
		WHERE is_activated >= {int:ban_threshold}',
		array(
			'ban_threshold' => 10,
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$current_banned[$row['id_member']] = array(
			'current' => $row['is_activated'] >= 20 ? 'hard' : 'soft',
		);
	wesql::free_result($request);

	// Now, we know who is supposed to be banned, let's get all our bans and see what we can do about them.
	$bans = array();
	$request = wesql::query('
		SELECT ban_type, hardness, ban_content, extra
		FROM {db_prefix}bans
		WHERE ban_type IN ({array_string:ban_types})',
		array(
			'ban_types' => array('id_member', 'email'),
		)
	);
	while ($row = wesql::fetch_assoc($request))
		$bans[$row['ban_type']][] = array(
			'hardness' => $row['hardness'],
			'content' => $row['ban_content'],
			'extra' => !empty($row['extra']) ? @unserialize($row['extra']) : array(),
		);
	wesql::free_result($request);

	// Let's start going through them. First, start by trying to match bans to members and setting the new banned flag.
	if (!empty($bans['id_member']))
		foreach ($bans['id_member'] as $ban)
			$current_banned[$ban['content']]['new'] = $ban['hardness'] ? 'hard' : 'soft';

	// We check emails now. Emails are cool.
	if (!empty($bans['email']))
	{
		// This is a bit more complex. We need to subdivide between specific addresses (with/out wildcards), entire domains and entire TLDs
		$matches = array();
		$gmail = array();

		foreach ($bans['email'] as $ban)
		{
			$ban['content'] = strtolower($ban['content']);

			if (strpos($ban['content'], '@*') === 0)
				$matches[$ban['hardness']][] = '%' . substr($ban['content'], strrpos($ban['content'], '*'));
			elseif (strpos($ban['content'], '*@') === 0)
				$matches[$ban['hardness']][] = '%' . substr($ban['content'], 1);
			else
			{
				if (empty($ban['extra']['gmail_style']))
					$matches[$ban['hardness']][] = str_replace('*', '%', $ban['content']);
				else
				{
					list ($user, $domain) = explode('@', $ban['content']);
					if (strpos($user, '+') !== false)
						list ($user, $label) = explode('+', $user);
					$user = str_replace(array('*', '.'), array('%', ''), $user);
					$gmail[$ban['hardness']][$domain][] = $user;
				}
			}
		}

		// So, step one, see if we matched straight emails or domains or tlds. That's the easy part.
		if (!empty($matches))
		{
			foreach ($matches as $hardness => $emails)
			{
				$criteria = array();
				$params = array();
				foreach ($emails as $k => $v)
				{
					$criteria['domain_' . $k] = 'email_address LIKE {string:domain_' . $k . '}';
					$params['domain_' . $k] = $v;
				}
				$request = wesql::query('
					SELECT id_member
					FROM {db_prefix}members
					WHERE ' . implode(' OR ', $criteria),
					$params
				);
				$this_hardness = $hardness == 0 ? 'soft' : 'hard';
				while ($row = wesql::fetch_assoc($request))
					if (!isset($current_banned[$row['id_member']]['new']) || $current_banned[$row['id_member']]['new'] != 'hard') // We're trying to see if they would still be banned.
						$current_banned[$row['id_member']]['new'] = $this_hardness;

				wesql::free_result($request);
			}
		}

		// Step two, GMail style bans. These are sucky for performance.
		if (!empty($gmail))
		{
			$params = array();
			foreach ($gmail as $hardness => $domains)
			{
				$email_domains = array_keys($domains);
				$criteria = array();
				$params = array();
				foreach ($email_domains as $k => $v)
				{
					$criteria['domain_' . $k] = 'email_address LIKE {string:domain_' . $k . '}';
					$params['domain_' . $k] = $v;
				}

				$request = wesql::query('
					SELECT id_member, email_address
					FROM {db_prefix}members
					WHERE ' . implode(' OR ', $criteria),
					$params
				);
				$this_hardness = $hardness == 0 ? 'soft' : 'hard';
				while ($row = wesql::fetch_assoc($request))
				{
					list ($user, $domain) = explode('@', strtolower($row['email_address']));
					if (strpos($user, '+') !== false)
						list ($user, $label) = explode('+', $user);
					$user = str_replace('.', '', $user);
					if (in_array($user, $domains[$domain]))
						if (!isset($current_banned[$row['id_member']]['new']) || $current_banned[$row['id_member']]['new'] != 'hard')
							$current_banned[$row['id_member']]['new'] = $this_hardness;
				}
				wesql::free_result($request);
			}
		}
	}

	// OK, so now we've established everything.
	$changes = array();
	foreach ($current_banned as $id_member => $ban_status)
	{
		if (empty($ban_status['new'])) // No longer banned, then?
			$changes[$id_member] = $ban_status['current'] == 'hard' ? -20 : -10;
		elseif (empty($ban_status['current'])) // Not currently banned, soon fix that.
			$changes[$id_member] = $ban_status['new'] == 'hard' ? 20 : 10;
		elseif ($ban_status['new'] != $ban_status['current']) // So, you're banned currently, and your ban status is changing. If you were soft banned, you're now hard banned and vice versa. Easy to fix.
			$changes[$id_member] = $ban_status['new'] == 'hard' ? 10 : -10;
	}

	// Ouch :'(
	if (!empty($changes))
		foreach ($changes as $id_member => $change)
			wesql::query('
				UPDATE {db_prefix}members
				SET is_activated = is_activated + {int:change}
				WHERE id_member = {int:id_member}',
				array(
					'id_member' => $id_member,
					'change' => $change,
				)
			);

	// No more caching this ban!
	updateSettings(array('banLastUpdated' => time()));
	foreach (array('bans_id_member', 'bans_email', 'bans_ip', 'bans_hostname', 'member-groups') as $cache)
		cache_put_data($cache, null);

	updateStats('member');
}
