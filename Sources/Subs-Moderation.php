<?php
/**
 * This file handles evaluating rules of post moderation.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/**
 * Define the basic types of allowed variable for rules to be constructed from.
 *
 * @param bool $admin Whether this is being called within the admin panel or not (might be used by hooks not to grab values)
 * @return array Contains a key for each allowed rule variable, explained more in depth in the function itself.
 */
function getBaseRuleVars($admin = false)
{
	global $board;

	/* Valid types are:

		id - a single value from the system that will be compared to an inclusive or exclusive list, e.g. this board id might be matched to an arbitrary list of boards where a rule applies
		multi-id - multiple values, rule will match if any of the ids matches
		range - a numeric value from the system that can be compared to numerically, e.g. user's post count
		regex - a regular expression (fully correct) to compare to a supplied string

		current should always be the value that is applicable for this variable, e.g. the user's post count or warning level or whatever

		cast - for multi-id, a type to optionally cast values to so that comparisons can be made properly. Only supports int (otherwise it's a string match)

		admin_override - for multi-id, whether the current user is an admin or not will make a difference (only used for permissions, where the admin has no defined permissions normally)

		func_val - name of a function to call to actually get the 'current' value for this variable (used for the links type to count how many links in a given post)

		function - name of a function to call to get the display entry in the admin panel, none of the core types use it
	*/

	$known_variables = array(
		'boards' => array(
			'type' => 'id',
			'current' => $admin ? 0 : (string) $board,
		),
		'userid' => array(
			'type' => 'id',
			'current' => (string) we::$id,
		),
		'postcount' => array(
			'type' => 'range',
			'current' => we::$user['posts'],
		),
		'warning' => array(
			'type' => 'range',
			'current' => we::$user['warning'],
		),
		'subject' => array(
			'type' => 'regex',
		),
		'body' => array(
			'type' => 'regex',
		),
		'groups' => array(
			'type' => 'multi-id',
			'cast' => 'int',
			'current' => we::$user['groups'],
		),
		'permission' => array(
			'type' => 'multi-id',
			'current' => we::$user['permissions'],
			'admin_override' => we::$is_admin,
		),
		'links' => array(
			'type' => 'range',
			'current' => 0, // this will be overridden later!
			'func_val' => 'count_links_post',
		),
	);

	call_hook('moderation_rules', array(&$known_variables, $admin));

	return $known_variables;
}

/**
 * Evaluates whether a post will be approved (from the post code) or whether it requires moderation, or other action.
 *
 * - Posts made through some script that don't go through the usual posting interface probably should not call this function.
 *
 * @param string $subject The post's subject
 * @param string $body The post's body
 * @return array Returns an array indicating the actions that should be taken. Empty array means post as normal.
 */

function checkPostModeration($subject, $body)
{
	global $settings, $topic, $context;

	$returnActions = array();

	$rules = simplexml_load_string($settings['postmod_rules']);

	$known_variables = getBaseRuleVars();
	$known_variables['subject']['current'] = $subject;
	$known_variables['body']['current'] = $body;

	foreach ($rules->rule as $rule_block)
	{
		// If this rule block is a rule for (new) topics, and we already have a topic id, it isn't a new topic and those rules should be ignored.
		if ((string) $rule_block['for'] == 'topics' && !empty($topic))
			continue;

		// OK, time to get down and funky.
		foreach ($rule_block->criteria as $criteria)
		{
			$applyAction = true;
			// OK, so let's step through any and all rules this criteria has, and if all are met, we won't apply this action.
			$these_rules = $criteria->children();
			if (count($these_rules) > 0)
			{
				foreach ($these_rules as $rule)
				{
					if (!$applyAction)
						break;

					// Get its name and validate that it's something we know about.
					$rule_name = $rule->getName();
					if (!isset($known_variables[$rule_name]))
						continue;

					if (isset($known_variables[$rule_name]['func_val']) && is_callable($known_variables[$rule_name]['func_val']))
						$known_variables[$rule_name]['current'] = $known_variables[$rule_name]['func_val']($subject, $body);

					switch ($known_variables[$rule_name]['type'])
					{
						case 'id':
							$this_rule = true;
							if (isset($rule['id']))
							{
								$ids = explode(',', (string) $rule['id']);
								$this_rule = in_array($known_variables[$rule_name]['current'], $ids);
							}
							elseif (isset($rule['except-id']))
							{
								$ids = explode(',', (string) $rule['except-id']);
								$this_rule = !in_array($known_variables[$rule_name]['current'], $ids);
							}
							else
								$this_rule = false;
							$applyAction &= $this_rule;
							break;

						case 'multi-id':
							$this_rule = true;
							if (isset($rule['id']))
							{
								$ids = explode(',', (string) $rule['id']);
								if (!empty($known_variables[$rule_name]['cast']) && $known_variables[$rule_name]['cast'] == 'int')
									foreach ($ids as $k => $v)
										$ids[$k] = (int) $v;
								$this_rule = count(array_intersect($ids, $known_variables[$rule_name]['current'])) > 0 || !empty($known_variables[$rule_name]['admin_override']);
							}
							elseif (isset($rule['except-id']))
							{
								$ids = explode(',', (string) $rule['except-id']);
								if (!empty($known_variables[$rule_name]['cast']) && $known_variables[$rule_name]['cast'] == 'int')
									foreach ($ids as $k => $v)
										$ids[$k] = (int) $v;
								$this_rule = count(array_intersect($ids, $known_variables[$rule_name]['current'])) == 0 && empty($known_variables[$rule_name]['admin_override']);
							}
							$applyAction &= $this_rule;
							break;

						case 'range':
							$this_rule = false;
							if (!empty($rule['lt']))
								$this_rule = ($known_variables[$rule_name]['current'] < (int) $rule['lt']);
							elseif (!empty($rule['lte']))
								$this_rule = ($known_variables[$rule_name]['current'] <= (int) $rule['lte']);
							elseif (!empty($rule['eq']))
								$this_rule = ($known_variables[$rule_name]['current'] == (int) $rule['eq']);
							elseif (!empty($rule['gt']))
								$this_rule = ($known_variables[$rule_name]['current'] > (int) $rule['gt']);
							elseif (!empty($rule['gte']))
								$this_rule = ($known_variables[$rule_name]['current'] >= (int) $rule['gte']);

							$applyAction &= $this_rule;
							break;

						case 'regex':
							$regexp = (string) $rule;
							if (!empty($rule['apply']))
							{
								$modifiers = !empty($rule['case-ins']) && (string) $rule['case-ins'] == 'yes' ? 'i' : '';
								switch ((string) $rule['apply'])
								{
									case 'begins':
										$regexp = '~^' . preg_quote($regexp, '~') . '~' . $modifiers;
										break;
									case 'ends':
										$regexp = '~' . preg_quote($regexp, '~') . '$~' . $modifiers;
										break;
									case 'contains':
										$regexp = '~' . preg_quote($regexp, '~') . '~' . $modifiers;
										break;
									case 'matches':
										$regexp = '~^' . preg_quote($regexp, '~') . '$~' . $modifiers;
										break;
								}
							}
							$applyAction &= preg_match($regexp, $known_variables[$rule_name]['current']);
							break;
					}
				}
			}
			// Don't apply an action if there are no rules to back it up.
			else
				$applyAction = false;

			$msg = !empty($criteria['msg']) ? (int) $criteria['msg'] : 0;
			// Did we match on any of that?
			if ($applyAction)
				$returnActions[(string) $criteria['for']][] = array((string) $rule_block['for'], $msg);
		}
	}

	return $returnActions;
}

/**
 * Evaluates the post content for how many links are in it.
 *
 * @param string $subject The post subject
 * @param string $body The post body
 * @return int The number of links found in the post
 */
function count_links_post($subject, $body)
{
	$body = strtolower($body); // We don't care about case, and we can use this to make things faster.

	// We need to normalize links. We have to cope with bare URLs, urls written as [url]http://blah[/url] and [url=http://blah]some text[/url]
	// To do this, we start by converting [url=...] form to [url]
	$body = preg_replace('~\[url.*?]~', '[url]', $body);

	// Then we convert bare links to [url] wrappings
	$body = preg_replace('~(?<!\[url])(https?://)~', '[url]$1', $body);

	// Then lastly we count the number of [url] items.
	return substr_count($body, '[url]');
}
