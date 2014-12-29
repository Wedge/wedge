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
 * Contains master settings for Wedge, including database credentials.
 * DO NOT CHANGE ANYTHING, UNLESS YOU KNOW WHAT YOU\'RE DOING!
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
$remove_index = 0;
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
$db_show_debug = 0;
$db_last_error = 0;

# Enabled plugins
$my_plugins = \'\';
';

	foreach (array('/Settings_bak.php', '/Settings.php') as $target)
		if (!file_exists(ROOT_DIR . $target))
			file_put_contents(ROOT_DIR . $target, $file . "\n?" . '>');
}

// Go through the folder tree, look for folders that have yet to be created,
// create them as needed, and create index.php and .htaccess files if required.
// $force is used by the weekly maintenance task to re-create files if deleted.
function create_generic_folders($force = false)
{
	$folders = array(
		'attachments' => true,
		'gz' => array(
			'app',
			'html',
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
		if ($force || !file_exists(ROOT_DIR . '/' . $key))
			create_generic_folder(ROOT_DIR, $key, $force);
		if (is_array($folder))
			foreach ($folder as $sub_folder)
				if ($force || !file_exists(ROOT_DIR . '/' . $key . '/' . $sub_folder))
					create_generic_folder(ROOT_DIR, $key . '/' . $sub_folder, $force);
	}
}

function create_generic_folder($root_dir, $folder, $force = false)
{
	// We're gonna let PHP output warnings or errors on mkdir and copy, because it's serious stuff.
	$path = str_replace('/', DIRECTORY_SEPARATOR, $root_dir . '/' . $folder);
	if (!$force || !file_exists($path))
		mkdir($path);

	// We need to put and index.php file in all folders.
	if (!$force || !file_exists($path . '/index.php'))
		file_put_contents($path . '/index.php', '<' . '?php
// Redirect to the upper level.
header(\'Location: ../\');');

	// Copy assets/icons/media to media/icons. After that, you can freely delete the originals.
	if (!$force && ($folder == 'media/icons' && file_exists($root_dir . '/assets/icons/media')))
		foreach (glob($root_dir . '/assets/icons/media/*.png') as $png_file)
			copy($png_file, $root_dir . '/media/icons/' . basename($png_file));

	if ($force && file_exists($path . '/.htaccess'))
		return;

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
	AddEncoding x-gzip .gz' . ($folder == 'gz/js' ? '
	AddEncoding x-gzip .jgz
	<FilesMatch "\.(js\.gz|jgz)$">
		ForceType text/javascript
	</FilesMatch>' : '
	AddEncoding x-gzip .cgz
	<FilesMatch "\.(css\.gz|cgz)$">
		ForceType text/css
	</FilesMatch>') . '
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

function create_main_htaccess()
{
	$begin_code = '############### BEGIN WEDGE CODE ###############';
	$end_code = str_replace('BEGIN', 'END', $begin_code);

	$file = $begin_code . '
# DO NOT EDIT ANYTHING BETWEEN THESE MARKERS!! #

# This is an Apache web server configuration file. It redirects requests to the proper place.
# If you\'re not using Apache, you\'ll need to adapt it to your HTTP server.
# Some documentation is available in /core/app/OriginalFiles.php';

	// Don't allow access to the Settings.php, no matter what.
	$file .= '

<FilesMatch "^Settings(_bak)?\.php$">
	Order Deny,Allow
	Deny from all
	Allow from localhost
</FilesMatch>';

	// Unencrypted avatars should be cached for a very long time.
	$file .= '

<IfModule mod_headers.c>
	<FilesMatch "avatar_[0-9]+_[0-9]+\.(jpg|jpeg|png|gif)$">
		Header set Expires "Thu, 21 March 2025 03:42:00 GMT"
	</FilesMatch>
</IfModule>';

	// Setting PHP variables is only supported in mod_php. If using PHP as CGI, edit php.ini instead.
	$file .= '

<IfModule mod_php.c>

	# PHP has to be running for Wedge to work, of course.
	php_flag engine on

	# Security
	php_flag session.use_cookies on' /* Using cookies for sessions is much more secure. */ . '
	php_flag session.use_trans_sid off' /* If it is really necessary, Wedge will do this - and it does it better. */ . '
	php_flag register_globals off' /* This is generally a bad thing to have on unless you need it on. */ . '
	php_flag magic_quotes_sybase off' /* This setting goes against Wedge's expectations on secure request variables. */ . '

	# Functionality
	php_value session.gc_maxlifetime 2880' /* A longer session length is preferrable when posting long messages. */ . '
	php_value session.save_handler "files"
	php_value session.serialize_handler "php"' /* Wedge expects these options to be set normally. (They almost always are.) */ . '
	php_flag session.use_only_cookies off' /* URL-based sessions are used if cookies aren't available to the client. */ . '
	php_flag session.auto_start off' /* If the session is automatically started, output compression may not work. */ . '
	php_flag allow_url_fopen on' /* With this on, you can use the plugin manager among other things. */ . '
	php_value arg_separator.output "&amp;"' /* This is here just for validation, although it isn't really used. */ . '
	php_value upload_max_filesize "4M"' /* This sets a larger upload file size. */ . '

	# Optimization
	php_value arg_separator.input "&;"' /* If PHP does this, Wedge won't have to redo it. */ . '
	php_flag always_populate_raw_post_data off
	php_flag register_argc_argv off' /* Wedge doesn't use these two, might as well disable them. */ . '
	php_flag magic_quotes_gpc off' /* Magic quotes suck. Die. Forever. */ . '
	php_flag implicit_flush off' /* This is a really bad setting for connections, and is best off just generally. */ . '

</IfModule>';

	// Ensures that accessing your forum root with "/" instead of "/index.php" will work.
	// Superceded by the Pretty URLs rule, anyway.
	$file .= '

<IfModule mod_dir.c>
	DirectoryIndex index.php
</IfModule>';

	// If your forum is installed in your server's root folder *and* you're using
	// Apache 2.2.16 or better, you may replace the next block with just this:
	//
	// FallbackResource /index.php

	$file .= '

<IfModule mod_rewrite.c>
	RewriteEngine on
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteRule .* index.php [L]
</IfModule>

' . $end_code . "\n";

	if (file_exists(ROOT_DIR . '/.htaccess'))
		$file .= preg_replace('~' . $begin_code . '.*?' . $end_code . '\v~s', '', file_get_contents(ROOT_DIR . '/.htaccess'));
	file_put_contents(ROOT_DIR . '/.htaccess', $file);
}
