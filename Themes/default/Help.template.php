<?php
// Version: 2.0 RC4; Help

function template_popup()
{
	global $context, $settings, $options, $txt;

	// Since this is a popup of its own we need to start the html, etc.
	echo '<!DOCTYPE html>
<html', $context['right_to_left'] ? ' dir="rtl"' : '', '>
	<head>
		<meta charset="utf-8" />
		<meta name="robots" content="noindex" />
		<title>', $context['page_title'], '</title>
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index.css" />
		<script src="', $settings['default_theme_url'], '/scripts/script.js"></script>
	</head>
	<body id="help_popup">
		<div class="windowbg description">
			', $context['help_text'], '<br />
			<br />
			<a href="javascript:parent.document.body.removeChild(parent.window[\'helpFrame\']); parent.window[\'helpFrame\'] = null;">', $txt['close_window'], '</a>
		</div>
	</body>
</html>';
}

?>