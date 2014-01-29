<?php
/**
 * Handles the creation of files and folders if not found.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

function create_settings_file($root_dir = '')
{
	global $boarddir;

	if (empty($boarddir))
		$boarddir = $root_dir;

	$file = '<' . <<<'EOS'
?php
/**
 * Contains the master settings for Wedge, including database credentials.
 *
 * @package Wedge
 * @copyright 2010 René-Gilles Deberdt, wedge.org
 * @license http://wedge.org/license/
 * @author see contributors.txt
 */

########## Maintenance ##########
# Note: If $maintenance is > 2, the forum will be unusable! Change it to 0 to fix it.
$maintenance = 2;								# Set to 1 to enable Maintenance Mode, 2 to install, and 3 to make the forum untouchable -- you'll have to make it 0 again manually!
$mtitle = 'Maintenance Mode';					# Title for the Maintenance Mode message.
$mmessage = 'This website is currently under maintenance. Please bear with us, we\'ll restore access as soon as we can!';	# Description of why the forum is in maintenance mode.

########## Forum Info ##########
$mbname = 'My Community';						# The name of your forum.
$boardurl = 'http://127.0.0.1/wedge';			# URL to your forum's folder. (without the trailing /!)
$webmaster_email = 'noreply@myserver.com';		# Email address to send emails from. (like noreply@yourdomain.com.)
$cookiename = 'WedgeCookie01';					# Name of the cookie to set for authentication.
$cache_type = 'file';

########## Database Info ##########
$db_server = 'localhost';
$db_name = 'wedge';
$db_user = 'root';
$db_passwd = '';
$ssi_db_user = '';
$ssi_db_passwd = '';
$db_prefix = 'wedge_';
$db_persist = 0;
$db_error_send = 1;
$db_show_debug = false;

########## Directories/Files ##########
# Note: These directories do not have to be changed unless you move things.
$boarddir = dirname(__FILE__);				# The absolute path to the forum's folder. Not just '.'!
$sourcedir = $boarddir . '/core/app';		# Path to the sources directory.
$cachedir = $boarddir . '/gz';				# Path to the cache directory.
$cssdir = $boarddir . '/gz/css';			# Path to the CSS cache directory.
$jsdir = $boarddir . '/gz/js';				# Path to the JS cache directory.
$pluginsdir = $boarddir . '/plugins';		# Path to the plugins directory.
$pluginsurl = $boardurl . '/plugins';		# URL to the plugins area root.

########## Error-Catching ##########
# Note: You shouldn't touch these settings.
$db_last_error = 0;

# Make sure the paths are correct... at least try to fix them.
if (!file_exists($boarddir) && file_exists(dirname(__FILE__) . '/SSI.php'))
	$boarddir = dirname(__FILE__);
if (!file_exists($sourcedir) && file_exists($boarddir . '/core/app'))
	$sourcedir = $boarddir . '/core/app';
if (!file_exists($pluginsdir) && file_exists($boarddir . '/plugins'))
	$pluginsdir = $boarddir . '/plugins';
EOS;

	foreach (array('/Settings_bak.php', '/Settings.php') as $target)
		if (!file_exists($boarddir . $target))
			file_put_contents($boarddir . $target, $file . "\n?" . '>');
}

function create_generic_folders($root_dir = '')
{
	global $boarddir;

	if (empty($boarddir))
		$boarddir = $root_dir;

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
		if (!file_exists($boarddir . '/' . $key))
			create_generic_folder($boarddir, $key);
		if (is_array($folder))
			foreach ($folder as $sub_folder)
				if (!file_exists($boarddir . '/' . $key . '/' . $sub_folder))
					create_generic_folder($boarddir, $key . '/' . $sub_folder);
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

	// If we're sure we're not under Apache, no need to bother adding htaccess files...
	if (isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') === false)
		return;

	// We need to be able to access images and various other files from plugins/, but not the archives of plugins themselves.
	if ($folder == 'plugins')
		$file = <<<'EOS'
<FilesMatch "\.(zip|gz|bz2|tar)$">
	Order deny,allow
	Deny from all
</FilesMatch>
EOS;

	elseif ($folder == 'gz')
		$file = <<<'EOS'
<Files *.php>
	Deny from all
</Files>

<Files *.lock>
	Deny from all
</Files>

<Files index.php>
	Allow from all
</Files>
EOS;

	elseif ($folder == 'gz/lang' || $folder == 'gz/keys')
		$file = <<<'EOS'
<Files *.php>
	Deny from all
</Files>

<Files index.php>
	Allow from all
</Files>
EOS;

	elseif ($folder == 'gz/css' || $folder == 'gz/js')
		$file = <<<'EOS'
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

FileETag none
EOS;

	// That's a peculiar one, security-oriented. Not justified, if you ask me.
	elseif ($folder == 'attachments')
		$file = <<<'EOS'
<Files *>
	Order Deny,Allow
	Deny from all
	Allow from localhost
</Files>

<IfModule mod_mime.c>
	RemoveHandler .php .php3 .phtml .cgi .fcgi .pl .fpl .shtml
</IfModule>
EOS;

	if (isset($file))
		file_put_contents($path . '/.htaccess', $file);
}
