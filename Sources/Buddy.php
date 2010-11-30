<?php
/**********************************************************************************
* Buddy.php                                                                       *
***********************************************************************************
* SMF: Simple Machines Forum                                                      *
* Open-Source Project Inspired by Zef Hemel (zef@zefhemel.com)                    *
* =============================================================================== *
* Software Version:           SMF 2.0 RC4                                         *
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

if (!defined('SMF'))
	die('Hacking attempt...');

/* This file contains some useful functions for members and membergroups.

	void Buddy()
		- add a member to your buddy list or remove it.
		- requires profile_identity_own permission.
		- called by ?action=buddy;u=x;session_id=y.
		- redirects to ?action=profile;u=x.

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
		$user_info['buddies'] = array_diff($user_info['buddies'], array($_REQUEST['u']));
	// ...or add if it's not and if it's not you.
	elseif ($user_info['id'] != $_REQUEST['u'])
		$user_info['buddies'][] = (int) $_REQUEST['u'];

	// Update the settings.
	updateMemberData($user_info['id'], array('buddy_list' => implode(',', $user_info['buddies'])));

	// Redirect back to the profile
	redirectexit('action=profile;u=' . $_REQUEST['u']);
}

?>