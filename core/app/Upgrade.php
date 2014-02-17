<?php
/**
 * Upgrade database tables before the forum gets hurt.
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
		if (function_exists('upgrade_to_' . $v))
			call_user_func('upgrade_to_' . $v);
		updateSettingsFile(array('we_shot' => $v));
		if (microtime(true) - $t > 1)
			break;
	}

	// We might want to add some message here...
	redirectexit($boardurl . '/index.php?' ($v < WEDGE ? 'upgrading-step-' . $v : 'upgraded'));
}

// 1.0-alpha-1, February 2014. Adding hey_not and hey_not fields to the members table.
function upgrade_to_1()
{
	wedb::add_column('{db_prefix}members', array('name' => 'hey_not', 'type' => 'bit', 'size' => 1, 'default' => 0));
	wedb::add_column('{db_prefix}members', array('name' => 'hey_pm',  'type' => 'bit', 'size' => 1, 'default' => 0));
}
