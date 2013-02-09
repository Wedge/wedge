<?php
/**
 * Wedge
 *
 * Handles setting theme options from JavaScript.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

define('WEDGE_NO_LOG', 1);

/*	This file concerns itself almost completely with theme administration.
	Its tasks include changing theme settings, installing and removing
	themes, choosing the current theme, and editing themes.  This is done in:

	void JSOption()
		- sets a theme option without outputting anything.
		- can be used with javascript, via a dummy image... (which doesn't
		  require the page to reload.)
		- requires someone who is logged in.
		- accessed via ?action=jsoption;var=variable;val=value;session_var=sess_id.
		- does not log access to the Who's Online log. (in index.php..)

*/

// Set an option via javascript.
function JSOption()
{
	global $theme, $options;

	// Check the session id.
	checkSession('get');

	// If no variables are provided, leave the hell out of here.
	if (empty($_POST['v']) || !isset($_POST['val']))
		exit;

	// Sorry, guests can't go any further than this..
	if (we::$is_guest || we::$id == 0)
		obExit(false);

	$reservedVars = array(
		'actual_theme_url',
		'actual_images_url',
		'default_images_url',
		'default_theme_dir',
		'default_theme_url',
		'default_template',
		'images_url',
		'smiley_sets_default',
		'theme_dir',
		'theme_id',
		'theme_templates',
		'theme_url',
		'name',
	);

	// Can't change reserved vars.
	if (in_array(strtolower($_POST['v']), $reservedVars))
		exit;

	// Use a specific theme?
	if (isset($_GET['th']))
	{
		// Invalidate the current themes cache too.
		cache_put_data('theme_settings-' . $theme['theme_id'] . ':' . we::$id, null, 60);

		$theme['theme_id'] = (int) $_GET['th'];
	}

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
		array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string-255', 'value' => 'string-65534'),
		array($theme['theme_id'], we::$id, $_POST['v'], is_array($_POST['val']) ? implode(',', $_POST['val']) : $_POST['val']),
		array('id_theme', 'id_member', 'variable')
	);

	cache_put_data('theme_settings-' . $theme['theme_id'] . ':' . we::$id, null, 60);

	// Don't output anything...
	exit;
}
