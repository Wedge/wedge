<?php
/**
 * This file handles the administrative side of post moderation.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
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
		'edit' => 'ModifyFilterRule',
		'save' => 'SaveFilterRule',
		'approveall' => 'ManageModApprove',
		'msgpopup' => 'ModFilterPopup',
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
					$msg = !empty($criteria['msg']) ? (int) $criteria['msg'] : 0;
					$context['rules'][$this_block][] = array(
						'action' => $action,
						'criteria' => $rule_params,
						'msg' => $msg,
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
		// We could use loadMemberData but we really only need names - the buffer will deal with coloring, etc, separately for us.
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
	wetem::load('modfilter_edit');

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
		$context['grouplist'][$row['min_posts'] == -1 ? 'assign' : 'post'][$row['id_group']] = !empty($row['online_color']) ? '<span style="color: ' . $row['online_color'] . '">' . $row['group_name'] . '</span>' : '<span>' . $row['group_name'] . '</span>';
	wesql::free_result($request);

	$context['lang_msg'] = array();
	getLanguages();
	foreach ($context['languages'] as $id => $entry)
		$context['lang_msg'][$id] = array(
			'name' => $entry['name'],
			'msg' => '',
		);
}

function ModifyFilterRule()
{
	global $context, $settings, $txt;

	// Get some of the basic stuff set up
	AddFilterRule();

	// Now we figure out what we're trying to edit and actually get the relevant details.
	$type = empty($_GET['type']) || ($_GET['type'] != 'topics' && $_GET['type'] != 'posts') ? '' : $_GET['type'];
	$id = empty($_GET['rule']) ? 0 : (int) $_GET['rule'];
	if (empty($settings['postmod_rules']) || empty($type) || empty($id))
		fatal_lang_error('modfilter_rule_not_found');

	// Just extend this with the list of actual messages people have entered, if any.
	$request = wesql::query('
		SELECT lang, msg
		FROM {db_prefix}mod_filter_msg
		WHERE id_rule = {int:rule}
			AND rule_type = {string:rule_type}',
		array(
			'rule' => $id,
			'rule_type' => $type,
		)
	);
	while ($row = wesql::fetch_assoc($request))
		if (isset($context['lang_msg'][$row['lang']]))
			$context['lang_msg'][$row['lang']]['msg'] = htmlspecialchars($row['msg']);
	wesql::free_result($request);

	$context['page_title'] = $txt['modfilter_editrule'];

	$id--; // We pushed it +1 through the URL so that we wouldn't spuriously get 0 entries which are ambiguous. So we need to correct it here.

	$rules = simplexml_load_string($settings['postmod_rules']);
	$this_block = $rules->xpath('rule[@for="' . $type . '"]/criteria');
	if (empty($this_block) || empty($this_block[$id]))
		fatal_lang_error('modfilter_rule_not_found');

	$context += array(
		'prev_type' => $type,
		'prev_id' => $id + 1,
		'edit_modaction' => $this_block[$id]['for'],
		'edit_applies' => $type,
		'edit_rules' => array(),
	);

	$rule_list = $this_block[$id]->children();
	foreach ($rule_list as $rule)
	{
		$function = 'displayRow_' . $rule->getName();
		if (is_callable($function))
			$context['edit_rules'][] = $function($rule);
		else
			$context['edit_rules'][] = array(
				'rule' => $txt['modfilter_cond_unknownrule'],
				'details' => htmlspecialchars($rule->asXML()),
			);
	}
}

function SaveFilterRule()
{
	global $context, $settings;

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

	// !! libxml_use_internal_errors(); ??
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

	if (!empty($_POST['delete']))
	{
		$_POST['prev_id'] = isset($_POST['prev_id']) ? (int) $_POST['prev_id'] : 0;
		if (!empty($_POST['prev_type']) && ($_POST['prev_type'] == 'posts' || $_POST['prev_type'] == 'topics') && $_POST['prev_id'] > 0)
		{
			deleteRuleNode($rules, $_POST['prev_type'], ((int) $_POST['prev_id']) - 1);
			updateCriteriaNodes($rules, $_POST['prev_type'], $_POST['prev_id']);
		}

		cleanupRuleNodes($rules);
		$result = (count($rules->children()) > 0) ? $rules->asXML() : '';
		updateSettings(array('postmod_rules' => $result));
		redirectexit('action=admin;area=modfilters');
	}

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

	// Let's see about the different messages we have to play with. Do we have any?
	$lang_msg = array();
	getLanguages();
	foreach ($context['languages'] as $id => $entry)
	{
		if (!empty($_POST['msg_' . $id]) && trim($_POST['msg_' . $id]) !== '')
			$lang_msg[$id] = $_POST['msg_' . $id];
	}
	$insert_row = array();
	if (!empty($lang_msg))
	{
		$msg_id = count($rulecontainer->children());
		foreach ($lang_msg as $lang_id => $msg)
			$insert_row[] = array($msg_id, $_POST['applies'], $lang_id, $msg);
		$criteria->addAttribute('msg', $msg_id);
	}
	if (!empty($insert_row))
		wesql::insert('',
			'{db_prefix}mod_filter_msg',
			array('id_rule' => 'int', 'rule_type' => 'string', 'lang' => 'string', 'msg' => 'string'),
			$insert_row
		);

	// Are we modifying a rule? Then we should strip the original node before proceeding.
	if (!empty($_POST['prev_type']) && ($_POST['prev_type'] == 'posts' || $_POST['prev_type'] == 'topics') && !empty($_POST['prev_id']) && (int) $_POST['prev_id'] > 0)
	{
		$_POST['prev_id'] = (int) $_POST['prev_id'];
		deleteRuleNode($rules, $_POST['prev_type'], $_POST['prev_id'] - 1);
		updateCriteriaNodes($rules, $_POST['applies'], $_POST['prev_id']);
	}

	cleanupRuleNodes($rules);
	$result = (count($rules->children()) > 0) ? $rules->asXML() : '';

	updateSettings(array('postmod_rules' => $result));
	redirectexit('action=admin;area=modfilters');
}

function ManageModApprove()
{
	global $context;

	checkSession();

	$request = wesql::query('
		SELECT id_msg
		FROM {db_prefix}messages
		WHERE approved = {int:is_unapproved}',
		array(
			'is_unapproved' => 0,
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
	// Nothing is true. Everything is permitted. (And if something happens that requires moderation... it'll get turned on there.)
	updateSettings(array('postmod_active' => 0));
	ManageModHome();
}

function displayRow_groups($rule)
{
	global $context, $txt;
	$array = array(
		'rule' => !empty($rule['id']) ? $txt['modfilter_cond_groups_in'] : $txt['modfilter_cond_groups_ex'],
	);
	// Merge the arrays but do not reindex.
	$groups = $context['grouplist']['assign'] + $context['grouplist']['post'];
	ksort($groups);
	$matched_groups = array();
	$list = !empty($rule['id']) ? explode(',', (string) $rule['id']) : explode(',', (string) $rule['except-id']);
	foreach ($list as $item)
	{
		$item = (int) $item;
		if (isset($groups[$item]))
			$matched_groups[] = $groups[$item];
	}
	$array['details'] = implode(', ', $matched_groups);
	$array['rulevalue'] = 'groups;' . (!empty($rule['id']) ? 'id' : 'except-id') . ';' . implode(',', $list);
	return $array;
}

function displayRow_boards($rule)
{
	global $context, $txt;
	$array = array(
		'rule' => !empty($rule['id']) ? $txt['modfilter_cond_boards_in'] : $txt['modfilter_cond_boards_ex'],
	);
	// We need to figure out what boards we are using, but the list of boards is a multi-dimensional array. Needs flattening first.
	$flat_boards = array();
	foreach ($context['boardlist'] as $id_cat => $cat)
		foreach ($cat['boards'] as $id_board => $board)
			$flat_boards[$id_board] = $board['board_name'];
	$list = !empty($rule['id']) ? explode(',', (string) $rule['id']) : explode(',', (string) $rule['except-id']);
	$matched_boards = array();
	foreach ($list as $item)
	{
		$item = (int) $item;
		if (isset($flat_boards[$item]))
			$matched_boards[] = $flat_boards[$item];
	}
	$array['details'] = implode(', ', $matched_boards);
	$array['rulevalue'] = 'boards;' . (!empty($rule['id']) ? 'id' : 'except-id') . ';' . implode(',', $list);
	return $array;
}

function displayRow_subject($rule)
{
	return simpleRegex_displayRow($rule, 'subject');
}

function displayRow_body($rule)
{
	return simpleRegex_displayRow($rule, 'body');
}

function simpleRegex_displayRow($rule, $type)
{
	global $txt;

	$apply = !empty($rule['apply']) && in_array((string) $rule['apply'], array('begins', 'ends', 'contains', 'matches')) ? (string) $rule['apply'] : 'regex';
	$case_ins = !empty($rule['case-ins']) && (string) $rule['case-ins'] == 'yes';
	$content = htmlspecialchars((string) $rule);

	return array(
		'rule' => $txt['modfilter_cond_' . $type . '_' . $apply],
		'details' => $content . ' ' . ($case_ins ? $txt['modfilter_case_insensitive'] : $txt['modfilter_case_sensitive']),
		'rulevalue' => $type . ';' . $apply . ';' . ($case_ins ? 'casesens=no' : 'casesens=yes') . ';' . $content,
	);
}

// There's no different logic here other than the names of variables.
function displayRow_postcount($rule)
{
	return simpleRange_displayRow($rule, 'postcount');
}

function displayRow_warning($rule)
{
	return simpleRange_displayRow($rule, 'warning');
}

function displayRow_links($rule)
{
	return simpleRange_displayRow($rule, 'links');
}

function simpleRange_displayRow($rule, $type)
{
	global $txt;

	$array = array(
		'rule' => $txt['modfilter_cond_' . $type],
	);
	foreach (array('lt', 'lte', 'eq', 'gte', 'gt') as $item)
	{
		if (isset($rule[$item]))
		{
			$array['details'] = $txt['modfilter_range_' . $item] . ' ' . (int) $rule[$item];
			$array['rulevalue'] = $type . ';' . $item . ';' . (int) $rule[$item];
			break;
		}
	}
	return $array;
}

// There's no easy way to delete nodes in SimpleXML. But we can do it if we can target the node precisely from its root, which we can.
// Note that you can't do it even if you foreach($sxml as &$element) and then unset the element, because you just unset the ref, not the actual element.
// Once we've done that, we do need to make sure that we clean up any stray branches.
// Returns a SimpleXMLElement if there's something there, or bool false if not.
function deleteRuleNode(&$rules, $parent_branch, $node_position)
{
	$branch_pos = -1;
	$found = false;
	foreach ($rules->rule as $ruleblock)
	{
		$branch_pos++;
		if ((string) $ruleblock['for'] == $parent_branch)
		{
			$found = true;
			break;
		}
	}
	if ($found && !empty($rules->rule[$branch_pos]->criteria[$node_position]))
		unset($rules->rule[$branch_pos]->criteria[$node_position]);
}

function updateCriteriaNodes(&$rules, $rule_type, $previous_id)
{
	$findcontainer = $rules->xpath('rule[@for="' . $_POST['applies'] . '"]');
	if (empty($findcontainer))
		return; // If we didn't find it, there's nothing to do here.

	$rulecontainer = $findcontainer[0];
	if (count($rulecontainer->children) > $previous_id)
		return; // There were not enough children, so return.

	foreach ($rulecontainer->criteria as $criteria)
		if (!empty($criteria['msg']))
		{
			$msg = (int) $criteria['msg'];
			if ($msg > $previous_id)
				$criteria['msg'] = (int) $criteria['msg'] - 1;
		}

	wesql::query('
		DELETE FROM {db_prefix}mod_filter_msg
		WHERE id_rule = {int:rule}
			AND rule_type = {string:applies}',
		array(
			'rule' => $previous_id,
			'applies' => $rule_type,
		)
	);
	wesql::query('
		UPDATE {db_prefix}mod_filter_msg
		SET id_rule = id_rule - 1
		WHERE id_rule > {int:rule}
			AND rule_type = {string:applies}',
		array(
			'rule' => $previous_id,
			'applies' => $rule_type,
		)
	);
}

function cleanupRuleNodes(&$rules)
{
	$prune = array();
	$branch_pos = -1;
	foreach ($rules->rule as $ruleblock)
	{
		$branch_pos++;
		if (count($ruleblock->children()) == 0)
			$prune[] = $branch_pos;
	}
	if (!empty($prune))
	{
		$prune = array_reverse($prune);
		foreach ($prune as $prune_pos)
			unset($rules->rule[$prune_pos]);
	}
}

function ModFilterPopup()
{
	global $context, $txt;

	loadTemplate('GenericPopup');
	loadLanguage('Help');
	wetem::hide();
	wetem::load('popup');

	$_GET['rule'] = isset($_GET['rule']) ? (int) $_GET['rule'] : 0;
	$_GET['ruletype'] = isset($_GET['ruletype']) && ($_GET['ruletype'] == 'posts' || $_GET['ruletype'] == 'topics') ? $_GET['ruletype'] : '';

	$context['rule_msgs'] = array();
	$_POST['t'] = $txt['modfilter_msg_popup_title'];
	if ($_GET['rule'] > 0)
	{
		$request = wesql::query('
			SELECT lang, msg
			FROM {db_prefix}mod_filter_msg
			WHERE id_rule = {int:rule}
				AND rule_type = {string:rule_type}',
			array(
				'rule' => $_GET['rule'],
				'rule_type' => $_GET['ruletype'],
			)
		);
		while ($row = wesql::fetch_assoc($request))
			$context['rule_msgs'][$row['lang']] = $row['msg'];
		wesql::free_result($request);
	}

	if (empty($context['rule_msgs']))
		return $context['popup_contents'] = $txt['modfilter_msg_no_lang'];

	// Anything using the help popup is a bit weird.
	$context['popup_contents'] = $txt['modfilter_msg_popup'] . '
	<table class="w100 cs3" id="rules">';

	foreach ($context['rule_msgs'] as $lang => $msg)
		$context['popup_contents'] .= '
		<tr>
			<td class="flag"><span class="flag_' . $lang . '"></span></td>
			<td>' . $msg . '</td>
		</tr>';

	$context['popup_contents'] .= '
	</table>';
}
