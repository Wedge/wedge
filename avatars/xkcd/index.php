<?php

// Try to handle it with the upper level index.php. It should know what to do.
if (file_exists(dirname(dirname(__FILE__)) . '/index.php'))
	include (dirname(dirname(__FILE__)) . '/index.php');
else
	exit;

?>