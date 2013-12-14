<?php
/**
 * Handles setting user options from JavaScript.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

/*	This file is all about changing user settings. This is done in:

	void JSOption()
		- sets a user option without outputting anything.
		- can be used with JavaScript, via a dummy image, avoiding a page reload.
		- accessed via ?action=jsoption;var=variable;val=value;session_var=sess_id.
		- does not log access to the Who's Online log.
		- requires user to be logged in.
*/

// Set an option via javascript.
function JSOption()
{
	global $options;

	// Check the session id.
	checkSession('get');

	// If no variables are provided, leave the hell out of here.
	if (empty($_POST['v']) || !isset($_POST['val']))
		exit;

	// Sorry, guests can't go any further than this..
	if (we::$is_guest || MID == 0)
		obExit(false);

	// If this is the admin preferences the passed value will just be an element of it.
	if ($_POST['v'] == 'admin_preferences')
	{
		$options['admin_preferences'] = !empty($options['admin_preferences']) ? unserialize($options['admin_preferences']) : array();
		// New thingy...
		if (isset($_GET['admin_key']) && strlen($_GET['admin_key']) < 5)
			$options['admin_preferences'][$_GET['admin_key']] = $_POST['val'];

		// Change the value to be something nice,
		$_POST['val'] = serialize($options['admin_preferences']);
	}

	// Update the option.
	wesql::insert('replace',
		'{db_prefix}themes',
		array('id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		array(MID, $_POST['v'], is_array($_POST['val']) ? implode(',', $_POST['val']) : $_POST['val'])
	);

	cache_put_data('theme_settings:' . MID, null, 60);

	// Don't output anything...
	exit;
}
