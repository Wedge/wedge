<?php
/**
 * Handles the creation of files and folders if not found.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function create_settings_file()
{
	$file = '<' . '?php
/**
 * Contains the master settings for Wedge, including database credentials.
 * DO NOT CHANGE ANYTHING, UNLESS YOU KNOW WHAT YOU\'RE DOING!
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * License: http://wedge.org/license/
 */

# 1 = Maintenance Mode (admin-only)
# 2 = Install Mode
# 3 = Closed Mode. EVEN to admins! Change to 0 here to reopen.
$maintenance = 2;
$mtitle = \'Maintenance Mode\';
$mmessage = \'This website is currently under maintenance. Please bear with us, we\\\'ll restore access as soon as we can!\';

# Forum details
$mbname = \'My Community\';
$boardurl = \'http://127.0.0.1/wedge\'; # URL to your forum\'s root folder
$webmaster_email = \'noreply@myserver.com\';
$cookiename = \'WedgeCookie01\';
$cache_type = \'file\';
$we_shot = ' . WEDGE . ';

# MySQL server
$db_server = \'localhost\';
$db_name = \'wedge\';
$db_user = \'root\';
$db_passwd = \'\';
$ssi_db_user = \'\';
$ssi_db_passwd = \'\';
$db_prefix = \'wedge_\';
$db_persist = 0;
$db_error_send = 1;
$db_show_debug = false;
$db_last_error = 0;
';

	foreach (array('/Settings_bak.php', '/Settings.php') as $target)
		if (!file_exists(ROOT_DIR . $target))
			file_put_contents(ROOT_DIR . $target, $file . "\n?" . '>');
}

function create_generic_folders()
{
	$folders = array(
		'attachments' => true,
		'gz' => array(
			'app',
			'keys',
			'css',
			'js',
			'lang',
		),
		'media' => array(
			'album_icons',
			'albums',
			'avatars',
			'ftp',
			'icons',
			'tmp',
		),
		'plugins' => true,
	);

	foreach ($folders as $key => $folder)
	{
		if (!file_exists(ROOT_DIR . '/' . $key))
			create_generic_folder(ROOT_DIR, $key);
		if (is_array($folder))
			foreach ($folder as $sub_folder)
				if (!file_exists(ROOT_DIR . '/' . $key . '/' . $sub_folder))
					create_generic_folder(ROOT_DIR, $key . '/' . $sub_folder);
	}
}

function create_generic_folder($root_dir, $folder)
{
	// We're gonna let PHP output warnings or errors on mkdir and copy, because it's serious stuff.
	$path = str_replace('/', DIRECTORY_SEPARATOR, $root_dir . '/' . $folder);
	mkdir($path);

	// We need to put and index.php file in all folders.
	file_put_contents($path . '/index.php', '<' . '?php
// Redirect to the upper level.
header(\'Location: ../\');');

	// Copy assets/icons/media to media/icons. After that, you can freely delete the originals.
	if ($folder == 'media/icons' && file_exists($root_dir . '/assets/icons/media'))
		foreach (glob($root_dir . '/assets/icons/media/*.png') as $png_file)
			copy($png_file, $root_dir . '/media/icons/' . basename($png_file));

	// We need to be able to access images and various other files from plugins/, but not the archives of plugins themselves.
	if ($folder == 'plugins')
		$file = '
<FilesMatch "\.(zip|gz|bz2|tar|xml)$">
	Order Deny,Allow
	Deny from all
</FilesMatch>';

	elseif ($folder == 'gz')
		$file = '
<Files *.php>
	Deny from all
</Files>

<Files *.lock>
	Deny from all
</Files>

<Files index.php>
	Allow from all
</Files>';

	elseif ($folder == 'gz/lang' || $folder == 'gz/keys')
		$file = '
<Files *.php>
	Deny from all
</Files>

<Files index.php>
	Allow from all
</Files>';

	elseif ($folder == 'gz/css' || $folder == 'gz/js')
		$file = '
<Files *.php>
	Deny from all
</Files>

<Files index.php>
	Allow from all
</Files>

<IfModule mod_mime.c>
	AddEncoding x-gzip .gz
	AddEncoding x-gzip .cgz
	AddEncoding x-gzip .jgz
	<FilesMatch "\.(js\.gz|jgz)$">
		ForceType text/javascript
	</FilesMatch>
	<FilesMatch "\.(css\.gz|cgz)$">
		ForceType text/css
	</FilesMatch>
</IfModule>

<IfModule mod_headers.c>
	Header set Cache-Control "max-age=2592000"
	Header set Expires "Thu, 21 March 2025 03:42:00 GMT"
	Header set Vary "Accept-Encoding"
</IfModule>

FileETag none';

	// That's a peculiar one, security-oriented. Not justified, if you ask me.
	elseif ($folder == 'attachments')
		$file = '
<Files *>
	Order Deny,Allow
	Deny from all
	Allow from localhost
</Files>

<IfModule mod_mime.c>
	RemoveHandler .php .php3 .phtml .cgi .fcgi .pl .fpl .shtml
</IfModule>';

	if (isset($file))
		file_put_contents($path . '/.htaccess', $file);
}
