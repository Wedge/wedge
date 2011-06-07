<?php
/**
 * Wedge
 *
 * This file deals with adding/removing users to/from buddy lists.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

/**
 * Adds or removes a member to/from your buddy list.
 *
 * - Called by action=buddy;u=x;session_id=y where x is the user being added.
 * - Session check in URL.
 * - Checks the profile_identity_own permission, and that the user is not a guest.
 * - Simple toggle; checks the current user's buddy list, if present removes it, if not adds it, then updates the current user's settings.
 * - Redirects back to the profile of the user specified in the URL (i.e. the user being added)
 */

function Buddy()
{
	global $user_info;

	checkSession('get');

	isAllowedTo('profile_identity_own');
	is_not_guest();

	if (empty($_REQUEST['u']))
		fatal_lang_error('no_access', false);
	$_REQUEST['u'] = (int) $_REQUEST['u'];

	// Remove if it's already there...
	if (in_array($_REQUEST['u'], $user_info['buddies']))
	{
		$user_info['buddies'] = array_diff($user_info['buddies'], array($_REQUEST['u']));
		$buddy_action = 'remove';
	}
	// ...or add if it's not and if it's not you.
	elseif ($user_info['id'] != $_REQUEST['u'])
	{
		$user_info['buddies'][] = (int) $_REQUEST['u'];
		$buddy_action = 'add';
	}

	if (isset($buddy_action))
	{
		// Update the settings.
		updateMemberData($user_info['id'], array('buddy_list' => implode(',', $user_info['buddies'])));

		// Call a hook, just in case we want to do something with this. Let's pass both the user we're adding/removing, and what we did with them.
		call_hook('buddy', array((int) $_REQUEST['u'], $buddy_action));
	}

	// Redirect back to the profile
	redirectexit('action=profile;u=' . $_REQUEST['u']);
}

?>