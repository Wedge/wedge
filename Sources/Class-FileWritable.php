<?php
/**
 * Wedge
 *
 * Handles file operations when files are writable, with an API consistent to Class-FTP's.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

// http://www.faqs.org/rfcs/rfc959.html
class weFileWritable
{
	public $connection, $error, $last_message, $pasv;
	private $path;

	// This is essentially pure placeholder but it makes everything much more convenient later.
	public function __construct($server = null, $port = null, $user = null, $pass = null)
	{
		global $boarddir;
		$this->connection = false;
		$this->error = false;
		$this->pasv = array();
		$this->path = $boarddir;
	}

	public function connect($server = null, $port = null, $user = null, $pass = null)
	{
		return;
	}

	public function chdir($path)
	{
		// No slash on the end, please...
		if ($path !== '/' && substr($path, -1) === '/')
			$path = substr($path, 0, -1);

		$this->path = $path;

		return true;
	}

	public function chmod($file, $chmod)
	{
		return @chmod($file, $chmod);
	}

	public function unlink($file)
	{
		return is_dir($file) ? @rmdir($file) : @unlink($file);
	}

	public function check_response($desired)
	{
		return true;
	}

	public function passive()
	{
		return true;
	}

	public function create_file($file)
	{
		return @touch($this->path . '/' . $file);
	}

	public function create_dir($dir)
	{
		return @mkdir($this->path . '/' . $dir);
	}

	public function close()
	{
		return true;
	}
}

?>