<?php
/**
 * Upgrade the forum before it gets hurt.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function upgrade_db()
{
	global $we_shot, $boardurl;

	$v = empty($we_shot) ? 0 : $we_shot;

	// We'll need some of the database helpers, and updateSettingsFile.
	// We're calling updateSettingsFile for each DB upgrade, so that we can
	// resume upgrading easily if the server somehow times out.
	loadSource(array('Class-DBHelper', 'Subs-Admin'));

	$t = microtime(true);
	while ($v++ < WEDGE)
	{
		if (function_exists('upgrade_step_' . $v))
			call_user_func('upgrade_step_' . $v);
		updateSettingsFile(array('we_shot' => $v));
		if (microtime(true) - $t > 1)
			break;
	}

	// We might want to add some message here...
	// Note: ROOT isn't defined at this point, so stick to $boardurl!
	redirectexit($boardurl . '/index.php?' . ($v < WEDGE ? 'upgrading-step-' . $v : 'upgraded'));
}

// 1.0-alpha-1, February 2014. Adding hey_not and hey_not fields to the members table.
function upgrade_step_1()
{
	wedb::add_column('{db_prefix}members', array('name' => 'hey_not', 'type' => 'bit', 'size' => 1, 'default' => 0));
	wedb::add_column('{db_prefix}members', array('name' => 'hey_pm',  'type' => 'bit', 'size' => 1, 'default' => 0));
}

// 1.0-alpha-1, February 2014. Moving some non-indexed members fields to the data array.
function upgrade_step_2()
{
	$request = wesql::query('
		SELECT
			id_member, data,
			secret_question, secret_answer, mod_prefs, message_labels
		FROM
			{db_prefix}members
		WHERE
			secret_answer != {string:empty}
			OR message_labels != {string:empty}
			OR mod_prefs != {string:empty}',
		array(
			'empty' => '',
			'db_error_skip' => true,
		)
	);
	while ($row = wesql::fetch_assoc($request))
	{
		$data = @unserialize($row['data']);
		if ($row['message_labels'] !== '')
			$data['pmlabs'] = $row['message_labels'];
		if ($row['mod_prefs'] !== '')
			$data['modset'] = $row['mod_prefs'];
		if ($row['secret_answer'] !== '')
			$data['secret'] = $row['secret_question'] . '|' . $row['secret_answer'];
		updateMemberData($row['id_member'], array('data' => serialize($data)));
	}
	wedb::remove_column('{db_prefix}members', 'message_labels');
	wedb::remove_column('{db_prefix}members', 'mod_prefs');
	wedb::remove_column('{db_prefix}members', 'new_pm');
	wedb::remove_column('{db_prefix}members', 'secret_question');
	wedb::remove_column('{db_prefix}members', 'secret_answer');
}
