<?php
/**
 * Wedge
 *
 * Handles requesting remote files from servers.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

class weget
{
	private $curl = false;
	private $url = '';
	private $protocol = '';
	private $domain = '';
	private $path = '';
	private $secure = false;
	private $method = 'GET';
	private $port = 0;
	private $max_redir_level = 3;
	private $user_agent = 'PHP/Wedge';
	private $postvars = array();
	private $ranges = array();
	private $headers = array();

	public function __construct($url)
	{
		if (is_callable('curl_init'))
			$this->curl = true;

		$this->parse_url($url);
	}

	public function setMethod($method)
	{
		if ($this->protocol == 'http' && ($method == 'GET' || $method == 'POST'))
			$this->method = $method;
	}

	public function setUserAgent($ua)
	{
		if (!empty($ua))
			$this->user_agent = $ua;
	}

	public function setMaxRedirs($max = 3)
	{
		$this->max_redir_level = (int) $max;
	}

	public function addPostVar($var, $val)
	{
		if ($this->protocol == 'http' && $this->method == 'POST')
			$this->postvars[$var] = $val;
	}

	public function addRange($start = null, $end = null)
	{
		// Can't have both being empty, even if one can be.
		if ($start === null && $end === null)
			return;

		// Start given, no end (e.g. start at x, continue until end)
		if ($end === null)
			$this->ranges[] = $start . '-';
		// End given, no start (e.g. starting x bytes from end, until end)
		elseif ($start === null)
			$this->ranges[] = '-' . $end;
		// Both given, convert to ints, and add into the range.
		else
		{
			$start = (int) $start;
			$end = (int) $end;

			// If they're the wrong way around, swap them. Fast.
			if ($start > $end)
				$start ^= $end ^= $start ^= $end;

			$this->ranges[] = $start . '-' . $end;
		}
	}

	public function addHeader($var, $val)
	{
		// If you want to use a Range, use addRange, not this.
		if ($var != 'Range')
			$this->headers[$var] = $var . ': ' . $val;
	}

	private function parse_url($url)
	{
		$this->url = $url;
		preg_match('~^(http|ftp)(s)?://([^/:]+)(:(\d+))?(.+)$~', $this->url, $match);

		// Couldn't match scheme? Throw it back up the chain to resolve.
		if (empty($match[1]))
			throw new Exception('weget requested to get a URL, but no valid URL supplied (' . $this->url . ')');

		// Having broken the requested URL up, let's store the relevant details.
		$this->protocol = $match[1];
		$this->secure = !empty($match[2]);
		$this->domain = $match[3];
		$this->port = !empty($match[5]) ? $match[5] : 0;
		$this->path = $match[6];

		// Hmm, did they specify an actual port? If not, work through some defaults.
		if (!empty($match[5]))
			$this->port = $match[5];
		elseif ($this->protocol == 'http')
			$this->port = $this->secure ? 443 : 80;
		elseif ($this->protocol == 'ftp')
			$this->port = $this->secure ? 22 : 21;
	}

	// I like keeping everything tidy. Only use the $url here if you're explicitly trying to reuse the object, e.g. making multiple requests in a single page...
	public function get($url = '')
	{
		try
		{
			if (!empty($url))
				$this->parse_url($url);
		}
		catch (Exception $e)
		{
			return false;
		}
		
		return $this->curl ? $this->getCurl() : $this->getFSock();
	}

	// We prefer to use cURL to get things.
	private function getCurl()
	{
		global $webmaster_email;

		$curl = curl_init();

		// Some really fundamental stuff.
		curl_setopt($curl, CURLOPT_URL, $this->url);
		curl_setopt($curl, CURLOPT_PORT, $this->port);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		// Some stuff only makes sense for HTTP.
		if ($this->protocol == 'http')
		{
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // Accept redirects
			curl_setopt($curl, CURLOPT_MAXREDIRS, $this->max_redir_level); // How many levels of redirect to allow
			curl_setopt($curl, CURLOPT_USERAGENT, $this->user_agent); // User agent to supply
			curl_setopt($curl, CURLOPT_HEADER, false); // We don't want the header in the output
			curl_setopt($curl, CURLOPT_NOBODY, false); // But we DO want the body

			// Setting the request type, and if it's POST, also setting up the vars.
			if ($this->method == 'GET')
				curl_setopt($curl, CURLOPT_HTTPGET, true);
			elseif ($this->method == 'POST')
			{
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $this->postvars);
			}

			// Any ranges set? Add'em.
			if (!empty($this->ranges))
				curl_setopt($curl, CURLOPT_RANGE, implode(',', $this->ranges));

			// We stored them as an associative array to deal with duplicates, but setopt doesn't like that.
			if (!empty($this->headers))
				curl_setopt($curl, CURLOPT_HTMLHEADER, array_values($this->headers));
		}
		// Stuff specific for FTP uses.
		elseif ($this->protocol == 'ftp')
		{
			curl_setopt($curl, CURLOPT_USERPWD, "anonymous:" . $webmaster_email);
		}

		$data = curl_exec($curl);
		curl_close($curl);

		return $data;
	}

	// ...but if we can't use cURL, use fsockopen to try and get it anyway.
	private function getFSock()
	{
		global $webmaster_email;
		static $redir_level = 0;

		$chunked = false;

		$data = '';

		if ($this->protocol == 'ftp')
		{
			loadSource('Class-FTP');

			// Create new connection, and in passive mode.
			$ftp = new ftp_connection(($this->secure ? 'ssl://' : '') . $this->domain, $this->port, 'anonymous', $webmaster_email);
			if ($ftp->error !== false || !$ftp->passive())
				return false;

			// I want that one *points*!
			fwrite($ftp->connection, 'RETR ' . $this->path . "\r\n");

			// If we're here, passive mode worked, so open the connection.
			$fp = @fsockopen($ftp->pasv['ip'], $ftp->pasv['port'], $err, $err, 5);
			if (!$fp)
				return false;

			// The server should now say something in acknowledgement.
			$ftp->check_response(150);

			while (!feof($fp))
				$data .= fread($fp, 4096);
			fclose($fp);

			// All done, right? Good.
			$ftp->check_response(226);
			$ftp->close();
		}
		elseif ($this->protocol == 'http')
		{
			// Open the socket on the port we want...
			$fp = @fsockopen(($this->secure ? 'ssl://' : '') . $this->domain, $this->port, $err, $err, 5);
			if (!$fp)
				return false;

			fwrite($fp, $this->method . ' ' . $this->path . ' HTTP/1.1' . "\r\n");
			fwrite($fp, 'Host: ' . $this->domain . ($this->port == 80 ? '' : ':' . $this->port) . "\r\n");
			fwrite($fp, 'User-Agent: ' . $this->user_agent . "\r\n");

			// Any ranges set? Add'em. But, unlike in the cURL case, exclude ranges if they've been set in the headers.
			if (!empty($this->ranges))
			{
				fwrite($fp, 'Cache-Control: no' . "\r\n");
				fwrite($fp, 'Range: bytes=' . implode(',', $this->ranges) . "\r\n");
			}

			// We stored them as an associative array to deal with duplicates, but setopt doesn't like that.
			if (!empty($this->headers))
				foreach ($this->headers as $header)
					fwrite($fp, $header . "\r\n");

			if ($this->method == 'POST')
			{
				// Set up the POST vars. We have to create a single mashed string here :/ Theory says http_build_query should do it but this intentionally allows for things hpq won't.
				$vars = array();
				foreach ($this->postvars as $var => $val)
					$vars[] = $var . '=' . urlencode($val);
				$post_data = implode('&', $this->postvars);

				fwrite($fp, 'Connection: close' . "\r\n");
				fwrite($fp, 'Content-Type: application/x-www-form-urlencoded' . "\r\n");
				fwrite($fp, 'Content-Length: ' . strlen($post_data) . "\r\n\r\n");
				fwrite($fp, $post_data);
			}
			else
				fwrite($fp, 'Connection: close' . "\r\n\r\n");

			$response = fgets($fp, 768);

			// Redirect in case this location is permanently or temporarily moved.
			if ($redir_level < $this->max_redir_level && preg_match('~^HTTP/\S+\s+30[127]~i', $response) === 1)
			{
				$header = '';
				$location = '';
				while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
					if (strpos($header, 'Location:') !== false)
						$location = trim(substr($header, strpos($header, ':') + 1));

				if (empty($location))
					return false;
				else
				{
					$redir_level++;
					return $this->get($location);
				}
			}
			// Make sure we get a valid response. 200 OK or 201 Created are normal, valid responses. If we had ranges, 206 Partial Content will be valid too.
			elseif (preg_match('~^HTTP/\S+\s+20[' . (empty($this->ranges) ? '01' : '016') . ']~i', $response) === 0)
				return false;

			// Skip the headers...
			while (!feof($fp) && trim($header = fgets($fp, 4096)) != '')
			{
				// Unless we've got a Content-Length header. That one's useful.
				if (preg_match('~content-length:\s*(\d+)~i', $header, $match) != 0)
					$content_length = $match[1];
				elseif (preg_match('~transfer-encoding:\s*chunked~i', $header) != 0)
					$chunked = true;

				continue;
			}

			$data = '';
			if (isset($content_length))
			{
				while (!feof($fp) && strlen($data) < $content_length)
					$data .= fread($fp, $content_length - strlen($data));
			}
			else
			{
				while (!feof($fp))
					$data .= fread($fp, 4096);
			}

			fclose($fp);
		}

		return $chunked ? $this->unchunk($data) : $data;
	}

	// Courtesy of http://www.php.net/manual/en/function.fsockopen.php#96146
	private function unchunk($data)
	{
		$fp = 0;
		$outData = "";
		while ($fp < strlen($data))
		{
			$rawnum = substr($data, $fp, strpos(substr($data, $fp), "\r\n") + 2);
			$num = hexdec(trim($rawnum));
			$fp += strlen($rawnum);
			$chunk = substr($data, $fp, $num);
			$outData .= $chunk;
			$fp += strlen($chunk);
		}
		return $outData;
	}
}

?>