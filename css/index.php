<?php

// This file is here solely to protect your cache directory.

// Look for Settings.php....
if (file_exists(dirname(dirname(__FILE__)) . '/Settings.php'))
{
	// Found it!
	require(dirname(dirname(__FILE__)) . '/Settings.php');
	header('Location: ' . $boardurl);
}
// Try to handle it with the upper level index.php. (it should know what to do.)
elseif (file_exists(dirname(dirname(__FILE__)) . '/index.php'))
	include (dirname(dirname(__FILE__)) . '/index.php');
// Can't find it... just forget it.
else
	exit;

?>