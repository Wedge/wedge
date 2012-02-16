<?php
/**
 * Wedge
 *
 * This file handles the administrative side of post moderation.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function ManageModeration()
{
	loadTemplate('ManageModeration');
	loadLanguage('ManageSettings');
	loadSource('Subs-Moderation');

	$subactions = array(
		'home' => 'ManageModHome',
		'add' => 'AddFilterRule',
		'save' => 'SaveFilterRule',
		'approveall' => 'ManageModApprove',
	);

	$_REQUEST['sa'] = !empty($_REQUEST['sa']) && isset($subactions[$_REQUEST['sa']]) ? $_REQUEST['sa'] : 'home';

	$subactions[$_REQUEST['sa']]();
}

function ManageModHome()
{
	global $context, $settings, $txt;

	$context['rules'] = array();
	$context['page_title'] = $txt['admin_mod_filters'];
	wetem::load('modfilter_home');

	$known_variables = getBaseRuleVars(true);

	$load = array(
		'groups' => array(),
		'boards' => array(),
		'userid' => array(),
	);

	if (!empty($settings['postmod_rules']))
	{
		$rules = simplexml_load_string($settings['postmod_rules']);
		foreach ($rules->rule as $rule_block)
		{
			$this_block = (string) $rule_block['for'];
			foreach ($rule_block->criteria as $criteria)
			{
				$action = (string) $criteria['for'];
				$rule_params = array();
				$these_rules = $criteria->children();
				if (count($these_rules) == 0)
					continue;

				foreach ($these_rules as $rule)
				{
					$rule_name = $rule->getName();
					if (!isset($known_variables[$rule_name]))
						continue;

					$this_rule = array(
						'name' => $rule_name,
						'type' => $known_variables[$rule_name]['type'],
					);
					switch ($known_variables[$rule_name]['type'])
					{
						case 'id':
						case 'multi-id':
							if (isset($rule['id']))
								$this_rule['id'] = explode(',', (string) $rule['id']);
							if (isset($rule['except-id']))
								$this_rule['except-id'] = explode(',', (string) $rule['except-id']);
							break;
						case 'range':
							foreach (array('lt', 'lte', 'eq', 'gt', 'gte') as $term)
							{
								if (isset($rule[$term]))
								{
									$this_rule['term'] = $term;
									$this_rule['value'] = (int) $rule[$term];
								}
							}
							break;
						case 'regex':
							$regex = (string) $rule;
							if (strlen($regex) > 0)
							{
								$this_rule['value'] = $regex;
								if (!empty($rule['apply']))
								{
									$apply = (string) $rule['apply'];
									if (in_array($apply, array('begins', 'ends', 'contains', 'matches')))
									{
										$this_rule['apply'] = $apply;
										$this_rule['case-ins'] = !empty($rule['case-ins']) && (string) $rule['case-ins'] == 'yes';
									}
								}
							}
							break;
					}

					if (count($this_rule) > 2)
					{
						if (!empty($known_variables[$rule_name]['function']))
							$this_rule['function'] = $known_variables[$rule_name]['function'];
						$rule_params[] = $this_rule;
					}

					// Having processed the main rules corpus, we may need to load some subsidary data to display it nicely.
					if ($rule_name == 'groups' || $rule_name == 'boards' || $rule_name == 'userid')
					{
						if (!empty($this_rule['id']))
							$load[$rule_name] = array_merge($load[$rule_name], $this_rule['id']);
						if (!empty($this_rule['except-id']))
							$load[$rule_name] = array_merge($load[$rule_name], $this_rule['except-id']);
					}
				}
				if (!empty($rule_params))
				{
					$context['rules'][$this_block][] = array(
						'action' => $action,
						'criteria' => $rule_params,
					);
				}
			}
		}
	}

	if (!empty($load['groups']))
	{
		$context['membergroups'] = array();
		$query = wesql::query('
			SELECT id_group, group_name, online_color
			FROM {db_prefix}membergroups
			WHERE id_group IN ({array_int:groups})',
			array(
				'groups' => array_unique($load['groups']),
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['membergroups'][$row['id_group']] = $row;
		wesql::free_result($query);
	}

	if (!empty($load['boards']))
	{
		$context['boards'] = array();
		$query = wesql::query('
			SELECT id_board, name
			FROM {db_prefix}boards
			WHERE id_board IN ({array_int:boards})',
			array(
				'boards' => array_unique($load['boards']),
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['boards'][$row['id_board']] = $row;
		wesql::free_result($query);
	}

	if (!empty($load['userid']))
	{
		// We could use loadMemberData but we really only need names - the buffer will deal with colouring, etc, separately for us.
		$context['users'] = array();
		$query = wesql::query('
			SELECT id_member, member_name
			FROM {db_prefix}members
			WHERE id_member IN ({array_int:members})',
			array(
				'members' => array_unique($load['userid']),
			)
		);
		while ($row = wesql::fetch_assoc($query))
			$context['users'][$row['id_member']] = $row;
		wesql::free_result($query);
	}
}

function AddFilterRule()
{
	global $context, $settings, $txt;

	loadLanguage('ManageMembers');
	$context['page_title'] = $txt['modfilter_addrule'];
	wetem::load('modfilter_add');

	$context['modfilter_action_list'] = array('prevent', 'moderate', '', 'pin', 'unpin', '', 'lock', 'unlock');

	$variables = getBaseRuleVars(true);
	$context['modfilter_rule_types'] = array();

	foreach ($variables as $id => $details)
		if (isset($txt['modfilter_condtype_' . $id]) && is_callable('template_modfilter_' . $id))
			$context['modfilter_rule_types'][] = $id;

	// There are certain things we need to get.
	$context['boardlist'] = array();
	$request = wesql::query('
		SELECT c.name AS cat_name, c.id_cat, b.id_board, b.name AS board_name, b.child_level
		FROM {db_prefix}boards AS b
			LEFT JOIN {db_prefix}categories AS c ON (c.id_cat = b.id_cat)' . (empty($settings['recycle_enable']) ? '' : '
		WHERE b.id_board != {int:recycle_board}'),
		array(
			'recycle_board' => !empty($settings['recycle_board']) ? $settings['recycle_board'] : 0,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		if (!isset($context['boardlist'][$row['id_cat']]))
			$context['boardlist'][$row['id_cat']] = array(
				'name' => $row['cat_name'],
				'boards' => array(),
			);

		$context['boardlist'][$row['id_cat']]['boards'][$row['id_board']] = array(
			'board_name' => $row['board_name'],
			'child_level' => $row['child_level'],
		);
	}
	wesql::free_result($request);
	// Purge empty categories and if we have nothing, remove boards as a possible filter
	if (!empty($context['boardlist']))
		foreach ($context['boardlist'] as $id_cat => $cat)
			if (empty($cat['boards']))
				unset($context['boardlist'][$id_cat]);

	if (empty($context['boardlist']))
		$context['modfilter_rule_types'] = array_diff($context['modfilter_rule_types'], array('boards'));

	$context['grouplist'] = array();
	$request = wesql::query('
		SELECT id_group, group_name, online_color, min_posts
		FROM {db_prefix}membergroups
		ORDER BY id_group');
	while ($row = wesql::fetch_assoc($request))
		$context['grouplist'][$row['min_posts'] == -1 ? 'assign' : 'post'][$row['id_group']] = !empty($row['online_color']) ? '<span style="color:' . $row['online_color'] . '">' . $row['group_name'] . '</span>' : '<span>' . $row['group_name'] . '</span>';
	wesql::free_result($request);
}

function SaveFilterRule()
{
	global $context, $txt, $settings;

	checkSession();

	// Validate we got something.
	if (empty($_POST['applies']) || ($_POST['applies'] != 'posts' && $_POST['applies'] != 'topics') || empty($_POST['rule']) || !is_array($_POST['rule']))
		fatal_lang_error('modfilter_error_saving');

	// This should be the same array as used in the form, for the simple expedient of having one hook that does everything we need it to.
	$context['modfilter_action_list'] = array('prevent', 'moderate', '', 'pin', 'unpin', '', 'lock', 'unlock');

	$known_variables = getBaseRuleVars(true);

	$context['modfilter_action_list'] = array_diff($context['modfilter_action_list'], array(''));

	// Also check that what we're trying to do is valid, because we couldn't check that before.
	if (empty($_POST['modaction']) || !in_array($_POST['modaction'], $context['modfilter_action_list']))
		fatal_lang_error('modfilter_error_saving');

	//libxml_use_internal_errors();
	// So we're saving. Whatever we are doing, we're going to need the existing (or new) rules in SimpleXML.
	if (empty($settings['postmod_rules']))
		$rules = new SimpleXMLElement('<rules />');
	else
		$rules = simplexml_load_string($settings['postmod_rules']);

	$findcontainer = $rules->xpath('rule[@for="' . $_POST['applies'] . '"]');
	if (empty($findcontainer))
	{
		// It doesn't exist, we need to add the relevant block to the $rules
		$rulecontainer = $rules->addChild('rule');
		$rulecontainer->addAttribute('for', $_POST['applies']);
	}
	else
		$rulecontainer = $findcontainer[0];
	
	// Let's first build the new criteria
	$criteria = $rulecontainer->addChild('criteria');
	$criteria->addAttribute('for', $_POST['modaction']);
	$elements = array();
	foreach ($_POST['rule'] as $new_rule)
	{
		list ($rule_type, $params) = explode(';', $new_rule, 2);
		if (!isset($known_variables[$rule_type]))
			fatal_lang_error('modfilter_error_saving');

		// Depending on the internal type of rule it is, this reflects what the processing requirements will be.
		switch ($known_variables[$rule_type]['type'])
		{
			case 'id':
			case 'multi-id':
				if (strpos($params, ';') === false)
					fatal_lang_error('modfilter_error_saving');
				list ($method, $list) = explode(';', $params, 2);
				if (($method != 'id' && $method != 'except-id') || empty($list))
					fatal_lang_error('modfilter_error_saving');
				if (isset($known_variables[$rule_type]['cast']) && $known_variables[$rule_type]['cast'] == 'int')
				{
					$array = explode(',', $list);
					foreach ($array as $k => $v)
						$array[$k] = (int) $v;
					$list = implode(',', $array);
				}
				$elements[] = $criteria->addChild($rule_type);
				$elements[count($elements)-1]->addAttribute($method, $list);
				break;
			case 'range':
				if (strpos($params, ';') === false)
					fatal_lang_error('modfilter_error_saving');
				list ($method, $value) = explode(';', $params, 2);
				if (!in_array($method, array('lt', 'lte', 'eq', 'gte', 'gt')) || !is_numeric($value))
					fatal_lang_error('modfilter_error_saving');
				$elements[] = $criteria->addChild($rule_type);
				$elements[count($elements)-1]->addAttribute($method, $value);
				break;
			case 'regex':
				if (substr_count($params, ';') < 2)
					fatal_lang_error('modfilter_error_saving');
				list ($method, $caseins, $value) = explode(';', $params, 3);
				if (!in_array($method, array('begins', 'ends', 'contains', 'matches', 'regex')) || empty($caseins) || empty($value))
					fatal_lang_error('modfilter_error_saving');
				$elements[] = $criteria->addChild($rule_type, $value);
				if ($method != 'regex')
					$elements[count($elements)-1]->addAttribute('apply', $method);
				$elements[count($elements)-1]->addAttribute('case-ins', $caseins == 'casesens=no' ? 'yes' : 'no');
				break;
		}
	}

	updateSettings(array('postmod_rules' => $rules->asXML()));
	redirectexit('action=admin;area=modfilters');
}

function ManageModApprove()
{
	global $context;

	checkSession();

	$request = wesql::query('
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE approved = {int:not_approved}',
		array(
			'not_approved' => 0,
		)
	);
	$msgs = array();
	while ($row = wesql::fetch_row($request))
		$msgs[] = $row[0];
	wesql::free_result($request);

	if (!empty($msgs))
	{
		loadSource(array('PostModeration', 'Subs-Post'));
		approvePosts($msgs);
	}

	$context['approved_all'] = true;
	ManageModHome();
}

?>