<?php
/**********************************************************************************
* Buddy.php                                                                       *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC5                                         *
* Software by:                Simple Machines (http://www.simplemachines.org)     *
* Copyright 2006-2010 by:     Simple Machines LLC (http://www.simplemachines.org) *
*           2001-2006 by:     Lewis Media (http://www.lewismedia.com)             *
* Support, News, Updates at:  http://www.simplemachines.org                       *
***********************************************************************************
* This program is free software; you may redistribute it and/or modify it under   *
* the terms of the provided license as published by Simple Machines LLC.          *
*                                                                                 *
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
*                                                                                 *
* See the "license.txt" file for details of the Simple Machines license.          *
* The latest version can always be found at http://www.simplemachines.org.        *
**********************************************************************************/

/**
 * This file deals with adding/removing users to/from buddy lists.
 *
 * @package wedge
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

	// Update the settings.
	updateMemberData($user_info['id'], array('buddy_list' => implode(',', $user_info['buddies'])));

	// Call a hook, just in case we want to do something with this. Let's pass both the user we're adding/removing, and what we did with them.
	call_hook('buddy', array((int) $_REQUEST['u'], $buddy_action));

	// Redirect back to the profile
	redirectexit('action=profile;u=' . $_REQUEST['u']);
}

?>