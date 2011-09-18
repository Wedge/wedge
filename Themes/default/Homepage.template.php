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
	global $context, $settings, $options, $txt, $scripturl, $modSettings, $language;

	echo '
	<div class="windowbg2 wrc">
		<h1>
			Welcome, hacker!
		</h1>
		<ul>
			<li><a href="', $scripturl, '?action=boards">Board index</a></li>
			<li><a href="', $scripturl, '?action=pm">Personal messages</a></li>
		</ul>
	</div>';
}

function template_info_above()
{
	echo '
		<div class="roundframe">';
}

function template_info_below()
{
	echo '
		</div>';
}

?>