<?php
// Version: 2.0 RC5; Help

function template_popup()
{
	global $context, $txt;

	// Since this is a popup of its own we need to start the html, unless we're coming from jQuery.
	if (!empty($context['is_ajax']))
	{
		echo '<div class="windowbg2 wrc smalltext nodrag">
	', $context['help_text'], '
</div>
<div class="smalltext centertext" style="padding: 8px 0 0">
	<a href="#" onclick="$(\'#helf\').remove(); return false;">', $txt['close_window'], '</a>
</div>';
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
<body id="help_popup">
	<div class="windowbg description">
		', $context['help_text'], '
		<br><br>
		<a href="#" onclick="history.back(); return false;">', $txt['back'], '</a>
	</div>
</body>
</html>';
	}
}

?>