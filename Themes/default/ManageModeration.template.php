<?php
/**
 * Wedge
 *
 * Displays the moderation filters system.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_modfilter_home()
{
	global $context, $txt;

	if (!empty($context['approved_all']))
		echo '
	<div class="windowbg" id="profile_success">
		', $txt['modfilter_all_approved'], '
	</div>';

	echo '
	<we:cat>', $txt['admin_mod_filters'], '</we:cat>';

	if (empty($context['rules']))
	{
		echo '
	<p class="description">', $txt['modfilter_norules'], '</p>';
	}

	foreach ($context['rules'] as $type => $rule_block)
	{
		echo '
	<table class="table_grid cs0" style="width: 100%">
		<thead>
			<tr class="catbg">
				<th scope="col" class="first_th" style="text-align:left">', $txt['modfilter_rule_' . $type], '</th>
				<th style="text-align:left">', $txt['modfilter_conditions'], '</th>
				<th scope="col" class="last_th"></th>
			</tr>
		</thead>
		<tbody>';

		$use_bg2 = true;
		foreach ($rule_block as $id => $rules)
		{
			$use_bg2 = !$use_bg2;

			$action = $rules['action'];
			$rule_params = $rules['criteria'];

			echo '
			<tr class="windowbg', $use_bg2 ? '2' : '', '">
				<td style="width:30%">', isset($txt['modfilter_action_' . $action]) ? $txt['modfilter_action_' . $action] : $action, '</td>
				<td>';

			$print_criteria = array();
			foreach ($rules['criteria'] as $criteria)
			{
				switch ($criteria['name'])
				{
					case 'boards':
						$str = isset($criteria['id']) ? $txt['modfilter_cond_boards_in'] : $txt['modfilter_cond_boards_ex'];
						$list = isset($criteria['id']) ? $criteria['id'] : $criteria['except-id'];
						foreach ($list as $k => $v)
						{
							if (isset($context['boards'][$v]))
								$list[$k] = '<a href="<URL>?board=' . $v . '">' . $context['boards'][$v]['name'] . '</a>';
							else
								$list[$k] = '<em>???? (#' . $v . ')</em>';
						}
						$print_criteria[] = $str . ' ' . implode(', ', $list);
						break;
					case 'userid':
						$str = isset($criteria['id']) ? $txt['modfilter_cond_userid_in'] : $txt['modfilter_cond_userid_ex'];
						$list = isset($criteria['id']) ? $criteria['id'] : $criteria['except-id'];
						foreach ($list as $k => $v)
						{
							if (isset($context['users'][$v]))
								$list[$k] = '<a href="<URL>?action=profile;u=' . $v . '">' . $context['users'][$v]['member_name'] . '</a>';
							else
								$list[$k] = '<em>???? (#' . $v . ')</em>';
						}
						$print_criteria[] = $str . ' ' . implode(', ', $list);
						break;
					case 'postcount':
					case 'warning':
						$print_criteria[] = $txt['modfilter_cond_' . $criteria['name']] . ' ' . $txt['modfilter_range_' . $criteria['term']] . ' ' . $criteria['value'];
						break;
					case 'subject':
					case 'body':
						$print_criteria[] = $txt['modfilter_cond_' . $criteria['name'] . '_regex'] . ' ' . htmlspecialchars($criteria['value']);
						break;
					case 'groups':
						$str = isset($criteria['id']) ? $txt['modfilter_cond_groups_in'] : $txt['modfilter_cond_groups_ex'];
						$list = isset($criteria['id']) ? $criteria['id'] : $criteria['except-id'];
						foreach ($list as $k => $v)
						{
							if (isset($context['membergroups'][$v]))
								$list[$k] = '<a href="<URL>?action=admin;area=membergroups;sa=edit;group=' . $v . '"' . (!empty($context['membergroups'][$v]['online_color']) ? ' style="color:' . $context['membergroups'][$v]['online_color'] .'"' : '') . '>' . $context['membergroups'][$v]['group_name'] . '</a>';
							else
								$list[$k] = '<em>???? (#' . $v . ')</em>';
						}
						$print_criteria[] = $str . ' ' . implode(', ', $list);
						break;
						break;
					case 'permission':
						$str = isset($criteria['id']) ? $txt['modfilter_cond_permissions_in'] : $txt['modfilter_cond_permissions_ex'];
						$list = isset($criteria['id']) ? $criteria['id'] : $criteria['except-id'];
						$print_criteria[] = $str . ' ' . implode(', ', $list);
						break;
					default:
						if (!empty($rules['function']))
							$print_criteria[] = $rules['function']($criteria);
						else
							$print_criteria[] = $txt['modfilter_cond_unknownrule'] . ' ' . $criteria['name'];
				}
			}

			echo '
					<ul><li>', implode('</li><li>', $print_criteria), '</li></ul>';

			echo '</td>
				<td style="width:15%" class="centertext"><a href="<URL>?action=admin;area=modfilters;sa=edit;type=', $type, ';rule=', ($id+1), '">', $txt['modify'], '</td>
			</tr>';
		}
		echo '
		</tbody>
	</table>';
	}

	echo '
	<div class="pagesection">
		<div class="floatright">
			<div class="additional_row" style="text-align: right;"><input type="submit" name="new" value="', $txt['modfilter_addrule'], '" class="new"></div>
		</div>
	</div>

	<we:cat>', $txt['modfilter_approve_title'], '</we:cat>
	<form action="<URL>?action=admin;area=modfilters;sa=approveall" method="post">
		<div class="windowbg wrc">
			<p>', $txt['modfilter_approve_desc'], '</p>
			<input type="submit" value="', $txt['modfilter_approve_title'], '" class="submit">
		</div>
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
	</form>';

	echo '
	<br class="clear">';
}

?>