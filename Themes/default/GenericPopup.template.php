<?php
/**
 * Wedge
 *
 * Displays a generic popup.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

function template_popup()
{
	global $context, $txt;

	$title = isset($_POST['t']) ? $_POST['t'] : '';

	// Since this is a popup of its own we need to start the html, unless we're coming from jQuery.
	if (AJAX)
	{
		// By default, this is a help popup.
		echo '
	<header>', $title ? $title : $txt['help'], '</header>
	<section class="nodrag">
		', $context['popup_contents'], '
	</section>
	<footer><input type="button" class="delete" onclick="$(\'#popup\').fadeOut(function () { $(this).remove(); });" value="', $txt['close_window'], '" /></footer>';
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
<body id="helf">
	<header>', $title ? $title : $txt['help'], '</header>
	<section>
		', $context['popup_contents'], '
	</section>
	<footer><input type="button" class="delete" onclick="window.close();" value="', $txt['close_window'], '" /></footer>
</body>
</html>';
	}
}
