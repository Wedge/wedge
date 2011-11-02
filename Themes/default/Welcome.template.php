<?php
/**
 * Wedge
 *
 * Displays the custom homepage. Hack away!
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_main()
{
	echo '
	<div class="windowbg2 wrc">
		<h1>
			Welcome, hacker!
		</h1>
		<ul>
			<li><a href="<URL>?action=boards">Board index</a></li>
			<li><a href="<URL>?action=pm">Personal messages</a></li>
		</ul>
	</div>';
}

function template_info_before()
{
	echo '
		<div class="roundframe" style="margin: 16px 0">';
}

// This one is just here to show you that layers can get _before_before,
// _before_override, _before_after and _after_* overrides ;)
// It only works on layers, though!
function template_info_center_before_after()
{
	echo '
		<div style="height: 8px"></div>';
}

function template_info_after()
{
	echo '
		</div>';
}

?>