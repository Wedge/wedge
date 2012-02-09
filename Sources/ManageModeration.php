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

	$subactions = array(
		'home' => 'ManageModHome',
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

	loadSource('Subs-Moderation');
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

/*
<rules>
	<rule for="posts">
		<criteria for="lock">
			<body>~^/lock~</body>
			<permission id="lock_any" />
		</criteria>
		<criteria for="moderate">
			<body>~fuck~i</body>
			<groups except-id="1" />
		</criteria>
	</rule>
</rules>
*/

function ManageModApprove()
{
	global $context;

	checkSession();
	approveAllData();
	$context['approved_all'] = true;
	ManageModHome();
}

// This is a helper function - basically approve everything!
function approveAllData()
{
	loadSource('PostModeration');

	// Start with messages and topics.
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
		loadSource('Subs-Post');
		approvePosts($msgs);
	}

	// Now do attachments
	$request = wesql::query('
		SELECT id_attach
		FROM {db_prefix}attachments
		WHERE approved = {int:not_approved}',
		array(
			'not_approved' => 0,
		)
	);
	$attaches = array();
	while ($row = wesql::fetch_row($request))
		$attaches[] = $row[0];
	wesql::free_result($request);

	if (!empty($attaches))
	{
		loadSource('ManageAttachments');
		ApproveAttachments($attaches);
	}
}

?>