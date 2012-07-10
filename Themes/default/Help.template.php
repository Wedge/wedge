<?php
/**
 * Wedge
 *
 * Displays help popup information.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_popup()
{
	global $context, $txt;

	$title = isset($_GET['title']) ? $_GET['title'] : '';

	// Since this is a popup of its own we need to start the html, unless we're coming from jQuery.
	if ($context['is_ajax'])
	{
		echo '
	<header>', $title ? $title : $txt['help'], '</header>
	<section class="nodrag">
		', $context['help_text'], '
	</section>
	<footer><a href="#" onclick="$(\'#help_pop\').fadeOut(function () { $(this).remove(); }); return false;">', $txt['close_window'], '</a></footer>';
	}
	else
	{
		echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
<head>
	<meta charset="utf-8">
	<meta name="robots" content="noindex">
	<title>', $context['page_title'], '</title>',
	theme_base_css(), '
</head>
<body class="windowbg" id="helf">
	<header>', $title ? $title : $txt['help'], '</header>
	<section>
		', $context['help_text'], '
	</section>
	<footer><a href="#" onclick="window.close(); return false;">', $txt['close_window'], '</a></footer>
</body>
</html>';
	}
}

?>