<?php
/**
 * Wedge
 *
 * FTP connection class, used for permission changing.
 *
 * @package wedge
 * @copyright 2010-2013 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

// http://www.faqs.org/rfcs/rfc959.html
class ftp_connection
{
	public $connection, $error, $last_message, $pasv, $debug;
	private $ftp_server;

	// Create a new FTP connection...
	public function __construct($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@wedge.org')
	{
		// Initialize variables.
		$this->connection = 'no_connection';
		$this->error = false;
		$this->pasv = array();
		$this->debug = false;

		if ($ftp_server !== null)
			$this->connect($ftp_server, $ftp_port, $ftp_user, $ftp_pass);
	}

	public function connect($ftp_server, $ftp_port = 21, $ftp_user = 'anonymous', $ftp_pass = 'ftpclient@wedge.org')
	{
		if (substr($ftp_server, 0, 6) == 'ftp://')
			$ftp_server = substr($ftp_server, 6);
		elseif (substr($ftp_server, 0, 7) == 'ftps://')
			$ftp_server = 'ssl://' . substr($ftp_server, 7);
		if (substr($ftp_server, 0, 7) == 'http://')
			$ftp_server = substr($ftp_server, 7);
		$ftp_server = strtr($ftp_server, array('/' => '', ':' => '', '@' => ''));

		// Connect to the FTP server.
		$this->connection = @fsockopen($ftp_server, $ftp_port, $err, $err, 5);
		if (!$this->connection)
		{
			$this->error = 'bad_server';
			return;
		}

		// Get the welcome message...
		if (!$this->check_response(220))
		{
			$this->error = 'bad_response';
			return;
		}

		// Send the username, it should ask for a password.
		$this->sendMsg('USER ' . $ftp_user);
		if (!$this->check_response(331))
		{
			$this->error = 'bad_username';
			return;
		}

		// Now send the password... and hope it goes okay.
		$this->sendMsg('PASS ' . $ftp_pass);
		if (!$this->check_response(230))
		{
			$this->error = 'bad_password';
			return;
		}

		// Yay? Store for later.
		$this->ftp_server = $ftp_server;
	}

	// This exists almost totally for debugging, though it is slightly nicer than raw fwrites.
	// Only fwrites that are likely to succeed should come here. If there's a reasonable
	// chance the message will fail, @fwrite it yourself thanks.
	public function sendMsg($message)
	{
		if ($this->debug)
			echo 'Sending: ', $message, '<br>';

		fwrite($this->connection, $message . "\r\n");
	}

	public function chdir($ftp_path)
	{
		if (!is_resource($this->connection))
			return false;

		// No slash on the end, please...
		if ($ftp_path !== '/' && substr($ftp_path, -1) === '/')
			$ftp_path = substr($ftp_path, 0, -1);

		$this->sendMsg('CWD ' . $ftp_path);
		if (!$this->check_response(250))
		{
			$this->error = 'bad_path';
			return false;
		}

		return true;
	}

	public function cdup()
	{
		if (!is_resource($this->connection))
			return false;
		$this->sendMsg('CDUP');
		if (!$this->check_response(250))
		{
			$this->error = 'bad_path';
			return false;
		}
		return true;
	}

	public function chmod($ftp_file, $chmod)
	{
		if (!is_resource($this->connection))
			return false;

		if ($ftp_file == '')
			$ftp_file = '.';

		// Convert the chmod value from octal (0777) to text ("777").
		$this->sendMsg('SITE CHMOD ' . decoct($chmod) . ' ' . $ftp_file);
		if (!$this->check_response(200))
		{
			$this->error = 'bad_file';
			return false;
		}

		return true;
	}

	public function unlink($ftp_file)
	{
		// We are actually connected, right?
		if (!is_resource($this->connection))
			return false;

		// Delete file X.
		$this->sendMsg('DELE ' . $ftp_file);
		if (!$this->check_response(250))
		{
			$this->sendMsg('RMD ' . $ftp_file);

			// Still no love?
			if (!$this->check_response(250))
			{
				$this->error = 'bad_file';
				return false;
			}
		}

		return true;
	}

	public function check_response($desired)
	{
		// Wait for a response that isn't continued with -, but don't wait too long.
		$time = time();
		do
		{
			$this->last_message = fgets($this->connection, 1024);
			if ($this->debug)
				echo 'Response checking, last message: ' . $this->last_message . '<br><br>';
		}
		while ((strlen($this->last_message) < 4 || substr($this->last_message, 0, 1) == ' ' || substr($this->last_message, 3, 1) != ' ') && time() - $time < 5);

		// Was the desired response returned?
		return is_array($desired) ? in_array(substr($this->last_message, 0, 3), $desired) : substr($this->last_message, 0, 3) == $desired;
	}

	public function passive()
	{
		// We can't create a passive data connection without a primary one first being there.
		if (!is_resource($this->connection))
			return false;

		// Request a passive connection - this means, we'll talk to you, you don't talk to us.
		$this->sendMsg('PASV');
		$time = time();
		do
		{
			$response = fgets($this->connection, 1024);
			if ($this->debug)
				echo 'PASV response: ' . $response . '<br><br>';
		}
		while (substr($response, 3, 1) != ' ' && time() - $time < 5);

		// First, check for a server telling us we have to use EPSV
		if (substr($response, 0, 2) == '42' && stripos($response, 'epsv'))
		{
			$this->sendMsg('EPSV');
			do
			{
				$response = fgets($this->connection, 1024);
				if ($this->debug)
					echo 'PASV response: ' . $response . '<br><br>';
			}
			while (substr($response, 3, 1) != ' ' && time() - $time < 5);

			if (substr($response, 0, 4) != '229 ')
			{
				if ($this->debug)
					echo '(invalid?) PASV response received: ' . $response . '<br><br>';
				$this->error = 'bad_response';
				return false;
			}

			if (preg_match('~\|\|([0-9a-f:]*)\|(\d+)\|~i', $response, $match))
			{
				$this->pasv = array('ip' => (empty($match[1]) ? $this->ftp_server : $match[1]), 'port' => $match[2], 'epsv' => true);
				if ($this->debug)
					echo 'We will connect to ' . $this->pasv['ip'] . ' on port ' . $this->pasv['port'] . ' with EPSV<br><br>';
			}
			else
			{
				if ($this->debug)
					echo '(invalid?) PASV response received: ' . $response . '<br><br>';
				$this->error = 'bad_response';
				return false;
			}
		}
		else
		{
			// If it's not 227, we weren't given an IP and port, which means it failed.
			if (substr($response, 0, 4) != '227 ')
			{
				if ($this->debug)
					echo '(invalid?) PASV response received: ' . $response . '<br><br>';
				$this->error = 'bad_response';
				return false;
			}

			// Snatch the IP and port information, or die horribly trying...
			if (preg_match('~\((\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+))\)~', $response, $match) == 0)
			{
				$this->error = 'bad_response';
				return false;
			}

			// This is pretty simple - store it for later use ;)
			$this->pasv = array('ip' => $match[1] . '.' . $match[2] . '.' . $match[3] . '.' . $match[4], 'port' => $match[5] * 256 + $match[6], 'epsv' => false);
			if ($this->debug)
				echo 'We will connect to ' . $this->pasv['ip'] . ' on port ' . $this->pasv['port'] . '<br><br>';
		}

		return true;
	}

	public function create_file($ftp_file)
	{
		// First, we have to be connected... very important.
		if (!is_resource($this->connection))
			return false;

		// I'd like one passive mode, please!
		if (!$this->passive())
			return false;

		// Seems logical enough, so far...
		$this->sendMsg('STOR ' . $ftp_file);

		// Okay, now we connect to the data port. If it doesn't work out, it's probably "file already exists", etc.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(150))
		{
			$this->error = 'bad_file';
			@fclose($fp);
			return false;
		}

		// This may look strange, but we're just closing it to indicate a zero-byte upload.
		fclose($fp);
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return true;
	}

	public function get($remotefile, $localpath = null)
	{
		// First, we have to be connected... very important.
		if (!is_resource($this->connection))
			return false;

		// Remember, $remote is the file name on its own (assume ./$remote if that helps)
		// while $localpath is the full local path
		if (!is_null($localpath))
		{
			$localcon = @fopen($localpath, 'w+b');
			if (!$localcon)
			{
				$this->error = 'invalid_local_path';
				return false;
			}
		}
		else
		{
			$data = '';
		}

		if ($this->debug)
			echo 'Getting ' . $remotefile;

		// Force binary transmission.
		$this->sendMsg('TYPE I');
		if (!$this->check_response(200))
		{
			$this->error = 'bad_response';
			return false;
		}

		// Next up, passive mode.
		if (!$this->passive())
			return false;

		$this->sendMsg('SIZE ' . $remotefile);
		$size = -1;
		if ($this->check_response(213))
		{
			list(, $size) = explode(' ', $this->last_message);
			if ($this->debug)
				echo 'Expected size ' . $size . ' bytes';
		}

		$this->sendMsg('RETR ' . $remotefile);

		// Now we connect to the data port and get stuff.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(150))
		{
			$this->error = 'bad_file';
			@fclose($fp);
			if (!isset($data))
				fclose($localcon);
			return false;
		}

		while (!feof($fp))
		{
			$block = fread($fp, 4096);
			if (isset($data))
				$data .= $block;
			else
				fwrite($localcon, $block);
		}
		fclose($fp);

		if (!isset($data))
			fclose($localcon);

		// All done?
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		if ($size != -1 && ((isset($data) && strlen($data) != (int) $size) || (!isset($data) && filesize($localpath) != (int) $size)))
		{
			$this->error = 'incomplete_file';
			return false;
		}

		if ($this->debug && isset($data))
			echo 'Data received: ' . htmlspecialchars($data);
		return isset($data) ? $data : true;
	}

	public function put($localpath, $remotefile)
	{
		// First, we have to be connected... very important. And with passive mode.
		if (!is_resource($this->connection) || !$this->passive())
			return false;

		// Remember, $remote is the file name on its own (assume ./$remote if that helps)
		// while $localpath is the full local path
		$localcon = file_exists($localpath) ? @fopen($localpath, 'rb') : false;
		if (!$localcon)
		{
			$this->error = 'invalid_local_path';
			return false;
		}

		// There's none of this type shifting nonsense. Binary and only binary here.
		$this->sendMsg('TYPE I');
		if (!$this->check_response(200))
		{
			$this->error = 'bad_response';
			return false;
		}
		$this->sendMsg('STOR ' . $remotefile);

		// Now we connect to the data port and do what we gotta do.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(150))
		{
			$this->error = 'bad_response';
			@fclose($fp);
			fclose($localcon);
			return false;
		}

		while (!feof($localcon))
		{
			$block = fread($localcon, 4096);
			while (!empty($block))
			{
				$written = @fwrite($fp, $block);
				if ($written === false)
				{
					$this->error = 'bad_response';
					@fclose($fp);
					fclose($localcon);
					return false;
				}
				$block = substr($block, $written);
			}
		}

		fclose($fp);
		fclose($localcon);
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return true;
	}

	public function put_string($string, $remotefile)
	{
		// First, we have to be connected... very important. And with passive mode.
		if (!is_resource($this->connection) || !$this->passive())
			return false;

		// There's none of this type shifting nonsense. Binary and only binary here.
		$this->sendMsg('TYPE I');
		if (!$this->check_response(200))
		{
			$this->error = 'bad_response';
			return false;
		}
		
		$this->sendMsg('STOR ' . $remotefile);

		// Now we connect to the data port and do what we gotta do.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(150))
		{
			$this->error = 'bad_response';
			@fclose($fp);
			return false;
		}

		for ($sent = 0, $lastwrite = 0, $size = strlen($string); $sent < $size; $sent += $lastwrite)
		{
			$lastwrite = @fwrite($fp, substr($string, $sent));
			if ($lastwrite === false)
			{
				$this->error = 'bad_response';
				@fclose($fp);
				return false;
			}
		}

		fclose($fp);
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return true;
	}

	public function raw_list($ftp_path = '')
	{
		// Are we even connected...?
		if (!is_resource($this->connection))
			return false;

		// Passive... non-agressive...
		if (!$this->passive())
			return false;

		// Get the listing!
		$this->sendMsg('NLST' . ($ftp_path == '' ? '' : ' ' . $ftp_path));

		// Connect, assuming we've got a connection.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(array(150, 125)))
		{
			$this->error = 'bad_response';
			@fclose($fp);
			return false;
		}

		// Read in the file listing.
		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// Everything go okay?
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		if ($this->debug)
			echo 'Listing response: ' . $data;

		return $data;
	}

	public function list_dir($ftp_path = '', $search = false)
	{
		// Are we even connected...?
		if (!is_resource($this->connection))
			return false;

		// Passive... non-agressive...
		if (!$this->passive())
			return false;

		// Get the listing!
		$this->sendMsg('LIST -1' . ($search ? 'R' : '') . ($ftp_path == '' ? '' : ' ' . $ftp_path));

		// Connect, assuming we've got a connection.
		$fp = @fsockopen($this->pasv['ip'], $this->pasv['port'], $err, $err, 5);
		if (!$fp || !$this->check_response(array(150, 125)))
		{
			$this->error = 'bad_response';
			@fclose($fp);
			return false;
		}

		// Read in the file listing.
		$data = '';
		while (!feof($fp))
			$data .= fread($fp, 4096);
		fclose($fp);

		// Everything go okay?
		if (!$this->check_response(226))
		{
			$this->error = 'bad_response';
			return false;
		}

		return $data;
	}

	public function locate($file, $listing = null)
	{
		if ($listing === null)
			$listing = $this->list_dir('', true);
		$listing = explode("\n", $listing);

		$this->sendMsg('PWD');
		$time = time();
		do
			$response = fgets($this->connection, 1024);
		while ($response[3] != ' ' && time() - $time < 5);

		// Check for 257!
		if (preg_match('~^257 "(.+?)" ~', $response, $match) != 0)
			$current_dir = strtr($match[1], array('""' => '"'));
		else
			$current_dir = '';

		for ($i = 0, $n = count($listing); $i < $n; $i++)
		{
			if (trim($listing[$i]) == '' && isset($listing[$i + 1]))
			{
				$current_dir = substr(trim($listing[++$i]), 0, -1);
				$i++;
			}

			// Okay, this file's name is:
			$listing[$i] = $current_dir . '/' . trim(strlen($listing[$i]) > 30 ? strrchr($listing[$i], ' ') : $listing[$i]);

			if ($file[0] == '*' && substr($listing[$i], -(strlen($file) - 1)) == substr($file, 1))
				return $listing[$i];
			if (substr($file, -1) == '*' && substr($listing[$i], 0, strlen($file) - 1) == substr($file, 0, -1))
				return $listing[$i];
			if (basename($listing[$i]) == $file || $listing[$i] == $file)
				return $listing[$i];
		}

		return false;
	}

	public function create_dir($ftp_dir)
	{
		// We must be connected to the server to do something.
		if (!is_resource($this->connection))
			return false;

		// Make this new beautiful directory!
		$this->sendMsg('MKD ' . $ftp_dir);
		if (!$this->check_response(257))
		{
			$this->error = 'bad_file';
			return false;
		}

		return true;
	}

	public function detect_path($filesystem_path, $lookup_file = null)
	{
		$username = '';

		if (isset($_SERVER['DOCUMENT_ROOT']))
		{
			if (preg_match('~^/home[2]?/([^/]+?)/public_html~', $_SERVER['DOCUMENT_ROOT'], $match))
			{
				$username = $match[1];

				$path = strtr($_SERVER['DOCUMENT_ROOT'], array('/home/' . $match[1] . '/' => '', '/home2/' . $match[1] . '/' => ''));

				if (substr($path, -1) == '/')
					$path = substr($path, 0, -1);

				if (strlen(dirname($_SERVER['PHP_SELF'])) > 1)
					$path .= dirname($_SERVER['PHP_SELF']);
			}
			elseif (substr($filesystem_path, 0, 9) == '/var/www/')
				$path = substr($filesystem_path, 8);
			else
				$path = strtr(strtr($filesystem_path, array('\\' => '/')), array($_SERVER['DOCUMENT_ROOT'] => ''));
		}
		else
			$path = '';

		if (is_resource($this->connection) && $this->list_dir($path) == '')
		{
			$data = $this->list_dir('', true);

			if ($lookup_file === null)
				$lookup_file = $_SERVER['PHP_SELF'];

			$found_path = dirname($this->locate('*' . basename(dirname($lookup_file)) . '/' . basename($lookup_file), $data));
			if ($found_path == false)
				$found_path = dirname($this->locate(basename($lookup_file)));
			if ($found_path != false)
				$path = $found_path;
		}
		elseif (is_resource($this->connection))
			$found_path = true;

		return array($username, $path, isset($found_path));
	}

	public function close()
	{
		// Goodbye!
		@fwrite($this->connection, 'QUIT' . "\r\n");
		@fclose($this->connection);

		return true;
	}
}
