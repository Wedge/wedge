<?php
/**
 * Displays a generic popup.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

function template_popup()
{
	global $context, $txt;

	// Called by reqWin()? Easy then...
	if (AJAX)
	{
		echo $context['popup_contents'];
		return;
	}

	// Since this is a popup of its own we need to start the html.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
<head>
	<meta charset="utf-8">
	<meta name="robots" content="noindex">
	<title>', $context['page_title'], '</title>',
	theme_base_css(), '
</head>
<body id="helf">
	<header>', $txt['help'], '</header>
	<section>
		', $context['popup_contents'], '
	</section>
	<footer><input type="button" class="submit" onclick="window.close();" value="', $txt['close_window'], '" /></footer>
</body>
</html>';
}
