<?php
/**
 * Pure-PHP implementation of SFTP, derived from phpseclib 0.2.2, http://phpseclib.sourceforge.net
 * This is a slimmed down version that combines everything into a single, minified file that is more suited to bundling and is PHP 5+ only.
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category   Net
 * @package    Net_SFTP
 * @author     Jim Wigginton <terrafrost@php.net>
 * @copyright  MMIX Jim Wigginton
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link       http://phpseclib.sourceforge.net
 * @link       http://pear.php.net/package/Math_BigInteger
 */

// Constants used by the different classes.
define('NET_SSH2_MASK_CONSTRUCTOR', 0x00000001);
define('NET_SSH2_MASK_LOGIN',       0x00000002);
define('NET_SSH2_MASK_SHELL',       0x00000004);

define('NET_SSH2_CHANNEL_EXEC', 0);
define('NET_SSH2_CHANNEL_SHELL',1);

define('NET_SSH2_LOG_SIMPLE',  1);
define('NET_SSH2_LOG_COMPLEX', 2);

define('NET_SSH2_READ_SIMPLE',  1);
define('NET_SSH2_READ_REGEX', 2);

define('NET_SFTP_LOG_SIMPLE',  NET_SSH2_LOG_SIMPLE);
define('NET_SFTP_LOG_COMPLEX', NET_SSH2_LOG_COMPLEX);

define('NET_SFTP_CHANNEL', 2);

define('NET_SFTP_LOCAL_FILE', 1);
define('NET_SFTP_STRING',  2);

define('MATH_BIGINTEGER_MONTGOMERY', 0);
define('MATH_BIGINTEGER_BARRETT', 1);
define('MATH_BIGINTEGER_POWEROF2', 2);
define('MATH_BIGINTEGER_CLASSIC', 3);
define('MATH_BIGINTEGER_NONE', 4);

define('MATH_BIGINTEGER_VALUE', 0);
define('MATH_BIGINTEGER_SIGN', 1);

define('MATH_BIGINTEGER_VARIABLE', 0);
define('MATH_BIGINTEGER_DATA', 1);

define('MATH_BIGINTEGER_MODE_INTERNAL', 1);
define('MATH_BIGINTEGER_MODE_BCMATH', 2);
define('MATH_BIGINTEGER_MODE_GMP', 3);

define('MATH_BIGINTEGER_MAX_DIGIT52', pow(2, 52));
define('MATH_BIGINTEGER_KARATSUBA_CUTOFF', 25);

define('CRYPT_HASH_MODE_INTERNAL', 1);
define('CRYPT_HASH_MODE_MHASH',    2);
define('CRYPT_HASH_MODE_HASH',     3);

define('CRYPT_RIJNDAEL_MODE_CTR', -1);
define('CRYPT_RIJNDAEL_MODE_ECB', 1);
define('CRYPT_RIJNDAEL_MODE_CBC', 2);
define('CRYPT_RIJNDAEL_MODE_CFB', 3);
define('CRYPT_RIJNDAEL_MODE_OFB', 4);

define('CRYPT_RIJNDAEL_MODE_INTERNAL', 1);
define('CRYPT_RIJNDAEL_MODE_MCRYPT', 2);

define('CRYPT_RC4_MODE_INTERNAL', 1);
define('CRYPT_RC4_MODE_MCRYPT', 2);

define('CRYPT_RC4_ENCRYPT', 0);
define('CRYPT_RC4_DECRYPT', 1);

define('CRYPT_DES_ENCRYPT', 0);
define('CRYPT_DES_DECRYPT', 1);

define('CRYPT_DES_MODE_CTR', -1);
define('CRYPT_DES_MODE_ECB', 1);
define('CRYPT_DES_MODE_CBC', 2);
define('CRYPT_DES_MODE_CFB', 3);
define('CRYPT_DES_MODE_OFB', 4);

define('CRYPT_DES_MODE_INTERNAL', 1);
define('CRYPT_DES_MODE_MCRYPT', 2);

define('CRYPT_DES_MODE_3CBC', -2);
define('CRYPT_DES_MODE_CBC3', CRYPT_DES_MODE_CBC);

define('CRYPT_RSA_ENCRYPTION_OAEP',  1);
define('CRYPT_RSA_ENCRYPTION_PKCS1', 2);

define('CRYPT_RSA_SIGNATURE_PSS',  1);
define('CRYPT_RSA_SIGNATURE_PKCS1', 2);

define('CRYPT_RSA_ASN1_INTEGER',   2);
define('CRYPT_RSA_ASN1_SEQUENCE', 48);

define('CRYPT_RSA_MODE_INTERNAL', 1);
define('CRYPT_RSA_MODE_OPENSSL', 2);

define('CRYPT_RSA_PRIVATE_FORMAT_PKCS1', 0);
define('CRYPT_RSA_PRIVATE_FORMAT_PUTTY', 1);
define('CRYPT_RSA_PRIVATE_FORMAT_XML', 2);

define('CRYPT_RSA_PUBLIC_FORMAT_RAW', 3);
define('CRYPT_RSA_PUBLIC_FORMAT_PKCS1', 4);
define('CRYPT_RSA_PUBLIC_FORMAT_XML', 5);
define('CRYPT_RSA_PUBLIC_FORMAT_OPENSSH', 6);

define('CRYPT_AES_MODE_CTR', -1);
define('CRYPT_AES_MODE_ECB', 1);
define('CRYPT_AES_MODE_CBC', 2);
define('CRYPT_AES_MODE_CFB', 3);
define('CRYPT_AES_MODE_OFB', 4);

define('CRYPT_AES_MODE_INTERNAL', 1);
define('CRYPT_AES_MODE_MCRYPT', 2);

class Net_SSH2
{
	var $identifier = 'SSH-2.0-phpseclib_0.2';
	var $fsock;
	var $bitmap = 0;
	var $errors = array();
	var $server_identifier = '';
	var $kex_algorithms;
	var $server_host_key_algorithms;
	var $encryption_algorithms_client_to_server;
	var $encryption_algorithms_server_to_client;
	var $mac_algorithms_client_to_server;
	var $mac_algorithms_server_to_client;
	var $compression_algorithms_client_to_server;
	var $compression_algorithms_server_to_client;
	var $languages_server_to_client;
	var $languages_client_to_server;
	var $encrypt_block_size = 8;
	var $decrypt_block_size = 8;
	var $decrypt = false;
	var $encrypt = false;
	var $hmac_create = false;
	var $hmac_check = false;
	var $hmac_size = false;
	var $server_public_host_key;
	var $session_id = false;
	var $exchange_hash = false;
	var $message_numbers = array();
	var $disconnect_reasons = array();
	var $channel_open_failure_reasons = array();
	var $terminal_modes = array();
	var $channel_extended_data_type_codes = array();
	var $send_seq_no = 0;
	var $get_seq_no = 0;
	var $server_channels = array();
	var $channel_buffers = array();
	var $channel_status = array();
	var $packet_size_client_to_server = array();
	var $message_number_log = array();
	var $message_log = array();
	var $window_size = 0x7FFFFFFF;
	var $window_size_client_to_server = array();
	var $signature = '';
	var $signature_format = '';
	var $interactiveBuffer = '';

	function __connect($host, $port = 22, $timeout = 10)
	{
		$this->message_numbers = array(
			1 => 'NET_SSH2_MSG_DISCONNECT',
			2 => 'NET_SSH2_MSG_IGNORE',
			3 => 'NET_SSH2_MSG_UNIMPLEMENTED',
			4 => 'NET_SSH2_MSG_DEBUG',
			5 => 'NET_SSH2_MSG_SERVICE_REQUEST',
			6 => 'NET_SSH2_MSG_SERVICE_ACCEPT',
			20 => 'NET_SSH2_MSG_KEXINIT',
			21 => 'NET_SSH2_MSG_NEWKEYS',
			30 => 'NET_SSH2_MSG_KEXDH_INIT',
			31 => 'NET_SSH2_MSG_KEXDH_REPLY',
			50 => 'NET_SSH2_MSG_USERAUTH_REQUEST',
			51 => 'NET_SSH2_MSG_USERAUTH_FAILURE',
			52 => 'NET_SSH2_MSG_USERAUTH_SUCCESS',
			53 => 'NET_SSH2_MSG_USERAUTH_BANNER',

			80 => 'NET_SSH2_MSG_GLOBAL_REQUEST',
			81 => 'NET_SSH2_MSG_REQUEST_SUCCESS',
			82 => 'NET_SSH2_MSG_REQUEST_FAILURE',
			90 => 'NET_SSH2_MSG_CHANNEL_OPEN',
			91 => 'NET_SSH2_MSG_CHANNEL_OPEN_CONFIRMATION',
			92 => 'NET_SSH2_MSG_CHANNEL_OPEN_FAILURE',
			93 => 'NET_SSH2_MSG_CHANNEL_WINDOW_ADJUST',
			94 => 'NET_SSH2_MSG_CHANNEL_DATA',
			95 => 'NET_SSH2_MSG_CHANNEL_EXTENDED_DATA',
			96 => 'NET_SSH2_MSG_CHANNEL_EOF',
			97 => 'NET_SSH2_MSG_CHANNEL_CLOSE',
			98 => 'NET_SSH2_MSG_CHANNEL_REQUEST',
			99 => 'NET_SSH2_MSG_CHANNEL_SUCCESS',
			100 => 'NET_SSH2_MSG_CHANNEL_FAILURE'
		);
		$this->disconnect_reasons = array(
			1 => 'NET_SSH2_DISCONNECT_HOST_NOT_ALLOWED_TO_CONNECT',
			2 => 'NET_SSH2_DISCONNECT_PROTOCOL_ERROR',
			3 => 'NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED',
			4 => 'NET_SSH2_DISCONNECT_RESERVED',
			5 => 'NET_SSH2_DISCONNECT_MAC_ERROR',
			6 => 'NET_SSH2_DISCONNECT_COMPRESSION_ERROR',
			7 => 'NET_SSH2_DISCONNECT_SERVICE_NOT_AVAILABLE',
			8 => 'NET_SSH2_DISCONNECT_PROTOCOL_VERSION_NOT_SUPPORTED',
			9 => 'NET_SSH2_DISCONNECT_HOST_KEY_NOT_VERIFIABLE',
			10 => 'NET_SSH2_DISCONNECT_CONNECTION_LOST',
			11 => 'NET_SSH2_DISCONNECT_BY_APPLICATION',
			12 => 'NET_SSH2_DISCONNECT_TOO_MANY_CONNECTIONS',
			13 => 'NET_SSH2_DISCONNECT_AUTH_CANCELLED_BY_USER',
			14 => 'NET_SSH2_DISCONNECT_NO_MORE_AUTH_METHODS_AVAILABLE',
			15 => 'NET_SSH2_DISCONNECT_ILLEGAL_USER_NAME'
		);
		$this->channel_open_failure_reasons = array(
			1 => 'NET_SSH2_OPEN_ADMINISTRATIVELY_PROHIBITED'
		);
		$this->terminal_modes = array(
			0 => 'NET_SSH2_TTY_OP_END'
		);
		$this->channel_extended_data_type_codes = array(
			1 => 'NET_SSH2_EXTENDED_DATA_STDERR'
		);

		$this->_define_array(
			$this->message_numbers,
			$this->disconnect_reasons,
			$this->channel_open_failure_reasons,
			$this->terminal_modes,
			$this->channel_extended_data_type_codes,
			array(60 => 'NET_SSH2_MSG_USERAUTH_PASSWD_CHANGEREQ'),
			array(60 => 'NET_SSH2_MSG_USERAUTH_PK_OK'),
			array(60 => 'NET_SSH2_MSG_USERAUTH_INFO_REQUEST',
				  61 => 'NET_SSH2_MSG_USERAUTH_INFO_RESPONSE')
		);

		$this->fsock = @fsockopen($host, $port, $errno, $errstr, $timeout);
		if (!$this->fsock) {
			user_error(rtrim("Cannot connect to $host. Error $errno. $errstr"), E_USER_NOTICE);
			return;
		}


		$temp = '';
		$extra = '';
		while (!feof($this->fsock) && !preg_match('#^SSH-(\d\.\d+)#', $temp, $matches)) {
			if (substr($temp, -2) == "\r\n") {
				$extra.= $temp;
				$temp = '';
			}
			$temp.= fgets($this->fsock, 255);
		}

		if (feof($this->fsock)) {
			user_error('Connection closed by server', E_USER_NOTICE);
			return false;
		}

		$ext = array();
		if (extension_loaded('mcrypt')) {
			$ext[] = 'mcrypt';
		}
		if (extension_loaded('gmp')) {
			$ext[] = 'gmp';
		} else if (extension_loaded('bcmath')) {
			$ext[] = 'bcmath';
		}

		if (!empty($ext)) {
			$this->identifier.= ' (' . implode(', ', $ext) . ')';
		}

		if (defined('NET_SSH2_LOGGING')) {
			$this->message_number_log[] = '<-';
			$this->message_number_log[] = '->';

			if (NET_SSH2_LOGGING == NET_SSH2_LOG_COMPLEX) {
				$this->message_log[] = $temp;
				$this->message_log[] = $this->identifier . "\r\n";
			}
		}

		$this->server_identifier = trim($temp, "\r\n");
		if (!empty($extra)) {
			$this->errors[] = utf8_decode($extra);
		}

		if ($matches[1] != '1.99' && $matches[1] != '2.0') {
			user_error("Cannot connect to SSH $matches[1] servers", E_USER_NOTICE);
			return;
		}

		fputs($this->fsock, $this->identifier . "\r\n");

		$response = $this->_get_binary_packet();
		if ($response === false) {
			user_error('Connection closed by server', E_USER_NOTICE);
			return;
		}

		if (ord($response[0]) != NET_SSH2_MSG_KEXINIT) {
			user_error('Expected SSH_MSG_KEXINIT', E_USER_NOTICE);
			return;
		}

		if (!$this->_key_exchange($response)) {
			return;
		}

		$this->bitmap = NET_SSH2_MASK_CONSTRUCTOR;
	}

	function _key_exchange($kexinit_payload_server)
	{
		static $kex_algorithms = array(
			'diffie-hellman-group1-sha1',             'diffie-hellman-group14-sha1'         );

		static $server_host_key_algorithms = array(
			'ssh-rsa',             'ssh-dss'          );

		static $encryption_algorithms = array(
						'arcfour256',
			'arcfour128',

			'arcfour',
			'aes128-cbc',             'aes192-cbc',             'aes256-cbc',
						'aes128-ctr',             'aes192-ctr',             'aes256-ctr',             '3des-ctr',
			'3des-cbc',               'none'                );

		static $mac_algorithms = array(
			'hmac-sha1-96',             'hmac-sha1',                'hmac-md5-96',              'hmac-md5',                 'none'                  );

		static $compression_algorithms = array(
			'none'                       );

		static $str_kex_algorithms, $str_server_host_key_algorithms,
			   $encryption_algorithms_server_to_client, $mac_algorithms_server_to_client, $compression_algorithms_server_to_client,
			   $encryption_algorithms_client_to_server, $mac_algorithms_client_to_server, $compression_algorithms_client_to_server;

		if (empty($str_kex_algorithms)) {
			$str_kex_algorithms = implode(',', $kex_algorithms);
			$str_server_host_key_algorithms = implode(',', $server_host_key_algorithms);
			$encryption_algorithms_server_to_client = $encryption_algorithms_client_to_server = implode(',', $encryption_algorithms);
			$mac_algorithms_server_to_client = $mac_algorithms_client_to_server = implode(',', $mac_algorithms);
			$compression_algorithms_server_to_client = $compression_algorithms_client_to_server = implode(',', $compression_algorithms);
		}

		$client_cookie = '';
		for ($i = 0; $i < 16; $i++) {
			$client_cookie.= chr(crypt_random(0, 255));
		}

		$response = $kexinit_payload_server;
		$this->_string_shift($response, 1);         $server_cookie = $this->_string_shift($response, 16);

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->kex_algorithms = explode(',', $this->_string_shift($response, $temp['length']));

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->server_host_key_algorithms = explode(',', $this->_string_shift($response, $temp['length']));

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->encryption_algorithms_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->encryption_algorithms_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->mac_algorithms_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->mac_algorithms_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->compression_algorithms_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->compression_algorithms_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->languages_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->languages_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

		extract(unpack('Cfirst_kex_packet_follows', $this->_string_shift($response, 1)));
		$first_kex_packet_follows = $first_kex_packet_follows != 0;

				$kexinit_payload_client = pack('Ca*Na*Na*Na*Na*Na*Na*Na*Na*Na*Na*CN',
			NET_SSH2_MSG_KEXINIT, $client_cookie, strlen($str_kex_algorithms), $str_kex_algorithms,
			strlen($str_server_host_key_algorithms), $str_server_host_key_algorithms, strlen($encryption_algorithms_client_to_server),
			$encryption_algorithms_client_to_server, strlen($encryption_algorithms_server_to_client), $encryption_algorithms_server_to_client,
			strlen($mac_algorithms_client_to_server), $mac_algorithms_client_to_server, strlen($mac_algorithms_server_to_client),
			$mac_algorithms_server_to_client, strlen($compression_algorithms_client_to_server), $compression_algorithms_client_to_server,
			strlen($compression_algorithms_server_to_client), $compression_algorithms_server_to_client, 0, '', 0, '',
			0, 0
		);

		if (!$this->_send_binary_packet($kexinit_payload_client)) {
			return false;
		}

		for ($i = 0; $i < count($encryption_algorithms) && !in_array($encryption_algorithms[$i], $this->encryption_algorithms_server_to_client); $i++);
		if ($i == count($encryption_algorithms)) {
			user_error('No compatible server to client encryption algorithms found', E_USER_NOTICE);
			return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
		}

		$decrypt = $encryption_algorithms[$i];
		switch ($decrypt) {
			case '3des-cbc':
			case '3des-ctr':
				$decryptKeyLength = 24;
				break;
			case 'aes256-cbc':
			case 'aes256-ctr':
				$decryptKeyLength = 32;
				break;
			case 'aes192-cbc':
			case 'aes192-ctr':
				$decryptKeyLength = 24;
				break;
			case 'aes128-cbc':
			case 'aes128-ctr':
				$decryptKeyLength = 16;
				break;
			case 'arcfour':
			case 'arcfour128':
				$decryptKeyLength = 16;
				break;
			case 'arcfour256':
				$decryptKeyLength = 32;
				break;
			case 'none';
				$decryptKeyLength = 0;
		}

		for ($i = 0; $i < count($encryption_algorithms) && !in_array($encryption_algorithms[$i], $this->encryption_algorithms_client_to_server); $i++);
		if ($i == count($encryption_algorithms)) {
			user_error('No compatible client to server encryption algorithms found', E_USER_NOTICE);
			return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
		}

		$encrypt = $encryption_algorithms[$i];
		switch ($encrypt) {
			case '3des-cbc':
			case '3des-ctr':
				$encryptKeyLength = 24;
				break;
			case 'aes256-cbc':
			case 'aes256-ctr':
				$encryptKeyLength = 32;
				break;
			case 'aes192-cbc':
			case 'aes192-ctr':
				$encryptKeyLength = 24;
				break;
			case 'aes128-cbc':
			case 'aes128-ctr':
				$encryptKeyLength = 16;
				break;
			case 'arcfour':
			case 'arcfour128':
				$encryptKeyLength = 16;
				break;
			case 'arcfour256':
				$encryptKeyLength = 32;
				break;
			case 'none';
				$encryptKeyLength = 0;
		}

		$keyLength = $decryptKeyLength > $encryptKeyLength ? $decryptKeyLength : $encryptKeyLength;

				for ($i = 0; $i < count($kex_algorithms) && !in_array($kex_algorithms[$i], $this->kex_algorithms); $i++);
		if ($i == count($kex_algorithms)) {
			user_error('No compatible key exchange algorithms found', E_USER_NOTICE);
			return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
		}

		switch ($kex_algorithms[$i]) {
									case 'diffie-hellman-group1-sha1':
				$p = pack('H256', 'FFFFFFFFFFFFFFFFC90FDAA22168C234C4C6628B80DC1CD129024E088A67CC74' .
								  '020BBEA63B139B22514A08798E3404DDEF9519B3CD3A431B302B0A6DF25F1437' .
								  '4FE1356D6D51C245E485B576625E7EC6F44C42E9A637ED6B0BFF5CB6F406B7ED' .
								  'EE386BFB5A899FA5AE9F24117C4B1FE649286651ECE65381FFFFFFFFFFFFFFFF');
				$keyLength = $keyLength < 160 ? $keyLength : 160;
				$hash = 'sha1';
				break;
						case 'diffie-hellman-group14-sha1':
				$p = pack('H512', 'FFFFFFFFFFFFFFFFC90FDAA22168C234C4C6628B80DC1CD129024E088A67CC74' .
								  '020BBEA63B139B22514A08798E3404DDEF9519B3CD3A431B302B0A6DF25F1437' .
								  '4FE1356D6D51C245E485B576625E7EC6F44C42E9A637ED6B0BFF5CB6F406B7ED' .
								  'EE386BFB5A899FA5AE9F24117C4B1FE649286651ECE45B3DC2007CB8A163BF05' .
								  '98DA48361C55D39A69163FA8FD24CF5F83655D23DCA3AD961C62F356208552BB' .
								  '9ED529077096966D670C354E4ABC9804F1746C08CA18217C32905E462E36CE3B' .
								  'E39E772C180E86039B2783A2EC07A28FB5C55DF06F4C52C9DE2BCBF695581718' .
								  '3995497CEA956AE515D2261898FA051015728E5A8AACAA68FFFFFFFFFFFFFFFF');
				$keyLength = $keyLength < 160 ? $keyLength : 160;
				$hash = 'sha1';
		}

		$p = new Math_BigInteger($p, 256);


		$q = new Math_BigInteger(1);
		$q = $q->bitwise_leftShift(2 * $keyLength);
		$q = $q->subtract(new Math_BigInteger(1));

		$g = new Math_BigInteger(2);
		$x = new Math_BigInteger();
		$x->setRandomGenerator('crypt_random');
		$x = $x->random(new Math_BigInteger(1), $q);
		$e = $g->modPow($x, $p);

		$eBytes = $e->toBytes(true);
		$data = pack('CNa*', NET_SSH2_MSG_KEXDH_INIT, strlen($eBytes), $eBytes);

		if (!$this->_send_binary_packet($data)) {
			user_error('Connection closed by server', E_USER_NOTICE);
			return false;
		}

		$response = $this->_get_binary_packet();
		if ($response === false) {
			user_error('Connection closed by server', E_USER_NOTICE);
			return false;
		}
		extract(unpack('Ctype', $this->_string_shift($response, 1)));

		if ($type != NET_SSH2_MSG_KEXDH_REPLY) {
			user_error('Expected SSH_MSG_KEXDH_REPLY', E_USER_NOTICE);
			return false;
		}

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->server_public_host_key = $server_public_host_key = $this->_string_shift($response, $temp['length']);

		$temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
		$public_key_format = $this->_string_shift($server_public_host_key, $temp['length']);

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$fBytes = $this->_string_shift($response, $temp['length']);
		$f = new Math_BigInteger($fBytes, -256);

		$temp = unpack('Nlength', $this->_string_shift($response, 4));
		$this->signature = $this->_string_shift($response, $temp['length']);

		$temp = unpack('Nlength', $this->_string_shift($this->signature, 4));
		$this->signature_format = $this->_string_shift($this->signature, $temp['length']);

		$key = $f->modPow($x, $p);
		$keyBytes = $key->toBytes(true);

		$this->exchange_hash = pack('Na*Na*Na*Na*Na*Na*Na*Na*',
			strlen($this->identifier), $this->identifier, strlen($this->server_identifier), $this->server_identifier,
			strlen($kexinit_payload_client), $kexinit_payload_client, strlen($kexinit_payload_server),
			$kexinit_payload_server, strlen($this->server_public_host_key), $this->server_public_host_key, strlen($eBytes),
			$eBytes, strlen($fBytes), $fBytes, strlen($keyBytes), $keyBytes
		);

		$this->exchange_hash = pack('H*', $hash($this->exchange_hash));

		if ($this->session_id === false) {
			$this->session_id = $this->exchange_hash;
		}

		for ($i = 0; $i < count($server_host_key_algorithms) && !in_array($server_host_key_algorithms[$i], $this->server_host_key_algorithms); $i++);
		if ($i == count($server_host_key_algorithms)) {
			user_error('No compatible server host key algorithms found', E_USER_NOTICE);
			return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
		}

		if ($public_key_format != $server_host_key_algorithms[$i] || $this->signature_format != $server_host_key_algorithms[$i]) {
			user_error('Sever Host Key Algorithm Mismatch', E_USER_NOTICE);
			return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
		}

		$packet = pack('C',
			NET_SSH2_MSG_NEWKEYS
		);

		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		$response = $this->_get_binary_packet();

		if ($response === false) {
			user_error('Connection closed by server', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Ctype', $this->_string_shift($response, 1)));

		if ($type != NET_SSH2_MSG_NEWKEYS) {
			user_error('Expected SSH_MSG_NEWKEYS', E_USER_NOTICE);
			return false;
		}

		switch ($encrypt) {
			case '3des-cbc':
				$this->encrypt = new Crypt_TripleDES();
								break;
			case '3des-ctr':
				$this->encrypt = new Crypt_TripleDES(CRYPT_DES_MODE_CTR);
								break;
			case 'aes256-cbc':
			case 'aes192-cbc':
			case 'aes128-cbc':
				$this->encrypt = new Crypt_AES();
				$this->encrypt_block_size = 16;                 break;
			case 'aes256-ctr':
			case 'aes192-ctr':
			case 'aes128-ctr':
				$this->encrypt = new Crypt_AES(CRYPT_AES_MODE_CTR);
				$this->encrypt_block_size = 16;                 break;
			case 'arcfour':
			case 'arcfour128':
			case 'arcfour256':
				$this->encrypt = new Crypt_RC4();
				break;
			case 'none';
						}

		switch ($decrypt) {
			case '3des-cbc':
				$this->decrypt = new Crypt_TripleDES();
				break;
			case '3des-ctr':
				$this->decrypt = new Crypt_TripleDES(CRYPT_DES_MODE_CTR);
				break;
			case 'aes256-cbc':
			case 'aes192-cbc':
			case 'aes128-cbc':
				$this->decrypt = new Crypt_AES();
				$this->decrypt_block_size = 16;
				break;
			case 'aes256-ctr':
			case 'aes192-ctr':
			case 'aes128-ctr':
				$this->decrypt = new Crypt_AES(CRYPT_AES_MODE_CTR);
				$this->decrypt_block_size = 16;
				break;
			case 'arcfour':
			case 'arcfour128':
			case 'arcfour256':
				$this->decrypt = new Crypt_RC4();
				break;
			case 'none';
						}

		$keyBytes = pack('Na*', strlen($keyBytes), $keyBytes);

		if ($this->encrypt) {
			$this->encrypt->enableContinuousBuffer();
			$this->encrypt->disablePadding();

			$iv = pack('H*', $hash($keyBytes . $this->exchange_hash . 'A' . $this->session_id));
			while ($this->encrypt_block_size > strlen($iv)) {
				$iv.= pack('H*', $hash($keyBytes . $this->exchange_hash . $iv));
			}
			$this->encrypt->setIV(substr($iv, 0, $this->encrypt_block_size));

			$key = pack('H*', $hash($keyBytes . $this->exchange_hash . 'C' . $this->session_id));
			while ($encryptKeyLength > strlen($key)) {
				$key.= pack('H*', $hash($keyBytes . $this->exchange_hash . $key));
			}
			$this->encrypt->setKey(substr($key, 0, $encryptKeyLength));
		}

		if ($this->decrypt) {
			$this->decrypt->enableContinuousBuffer();
			$this->decrypt->disablePadding();

			$iv = pack('H*', $hash($keyBytes . $this->exchange_hash . 'B' . $this->session_id));
			while ($this->decrypt_block_size > strlen($iv)) {
				$iv.= pack('H*', $hash($keyBytes . $this->exchange_hash . $iv));
			}
			$this->decrypt->setIV(substr($iv, 0, $this->decrypt_block_size));

			$key = pack('H*', $hash($keyBytes . $this->exchange_hash . 'D' . $this->session_id));
			while ($decryptKeyLength > strlen($key)) {
				$key.= pack('H*', $hash($keyBytes . $this->exchange_hash . $key));
			}
			$this->decrypt->setKey(substr($key, 0, $decryptKeyLength));
		}


		if ($encrypt == 'arcfour128' || $encrypt == 'arcfour256') {
			$this->encrypt->encrypt(str_repeat("\0", 1536));
		}
		if ($decrypt == 'arcfour128' || $decrypt == 'arcfour256') {
			$this->decrypt->decrypt(str_repeat("\0", 1536));
		}

		for ($i = 0; $i < count($mac_algorithms) && !in_array($mac_algorithms[$i], $this->mac_algorithms_client_to_server); $i++);
		if ($i == count($mac_algorithms)) {
			user_error('No compatible client to server message authentication algorithms found', E_USER_NOTICE);
			return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
		}

		$createKeyLength = 0;         switch ($mac_algorithms[$i]) {
			case 'hmac-sha1':
				$this->hmac_create = new Crypt_Hash('sha1');
				$createKeyLength = 20;
				break;
			case 'hmac-sha1-96':
				$this->hmac_create = new Crypt_Hash('sha1-96');
				$createKeyLength = 20;
				break;
			case 'hmac-md5':
				$this->hmac_create = new Crypt_Hash('md5');
				$createKeyLength = 16;
				break;
			case 'hmac-md5-96':
				$this->hmac_create = new Crypt_Hash('md5-96');
				$createKeyLength = 16;
		}

		for ($i = 0; $i < count($mac_algorithms) && !in_array($mac_algorithms[$i], $this->mac_algorithms_server_to_client); $i++);
		if ($i == count($mac_algorithms)) {
			user_error('No compatible server to client message authentication algorithms found', E_USER_NOTICE);
			return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
		}

		$checkKeyLength = 0;
		$this->hmac_size = 0;
		switch ($mac_algorithms[$i]) {
			case 'hmac-sha1':
				$this->hmac_check = new Crypt_Hash('sha1');
				$checkKeyLength = 20;
				$this->hmac_size = 20;
				break;
			case 'hmac-sha1-96':
				$this->hmac_check = new Crypt_Hash('sha1-96');
				$checkKeyLength = 20;
				$this->hmac_size = 12;
				break;
			case 'hmac-md5':
				$this->hmac_check = new Crypt_Hash('md5');
				$checkKeyLength = 16;
				$this->hmac_size = 16;
				break;
			case 'hmac-md5-96':
				$this->hmac_check = new Crypt_Hash('md5-96');
				$checkKeyLength = 16;
				$this->hmac_size = 12;
		}

		$key = pack('H*', $hash($keyBytes . $this->exchange_hash . 'E' . $this->session_id));
		while ($createKeyLength > strlen($key)) {
			$key.= pack('H*', $hash($keyBytes . $this->exchange_hash . $key));
		}
		$this->hmac_create->setKey(substr($key, 0, $createKeyLength));

		$key = pack('H*', $hash($keyBytes . $this->exchange_hash . 'F' . $this->session_id));
		while ($checkKeyLength > strlen($key)) {
			$key.= pack('H*', $hash($keyBytes . $this->exchange_hash . $key));
		}
		$this->hmac_check->setKey(substr($key, 0, $checkKeyLength));

		for ($i = 0; $i < count($compression_algorithms) && !in_array($compression_algorithms[$i], $this->compression_algorithms_server_to_client); $i++);
		if ($i == count($compression_algorithms)) {
			user_error('No compatible server to client compression algorithms found', E_USER_NOTICE);
			return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
		}
		$this->decompress = $compression_algorithms[$i] == 'zlib';

		for ($i = 0; $i < count($compression_algorithms) && !in_array($compression_algorithms[$i], $this->compression_algorithms_client_to_server); $i++);
		if ($i == count($compression_algorithms)) {
			user_error('No compatible client to server compression algorithms found', E_USER_NOTICE);
			return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
		}
		$this->compress = $compression_algorithms[$i] == 'zlib';

		return true;
	}

	function login($username, $password = '')
	{
		if (!($this->bitmap & NET_SSH2_MASK_CONSTRUCTOR)) {
			return false;
		}

		$packet = pack('CNa*',
			NET_SSH2_MSG_SERVICE_REQUEST, strlen('ssh-userauth'), 'ssh-userauth'
		);

		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		$response = $this->_get_binary_packet();
		if ($response === false) {
			user_error('Connection closed by server', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Ctype', $this->_string_shift($response, 1)));

		if ($type != NET_SSH2_MSG_SERVICE_ACCEPT) {
			user_error('Expected SSH_MSG_SERVICE_ACCEPT', E_USER_NOTICE);
			return false;
		}

		if (is_object($password) && strtolower(get_class($password)) == 'crypt_rsa') {
			return $this->_privatekey_login($username, $password);
		}

		$utf8_password = utf8_encode($password);
		$packet = pack('CNa*Na*Na*CNa*',
			NET_SSH2_MSG_USERAUTH_REQUEST, strlen($username), $username, strlen('ssh-connection'), 'ssh-connection',
			strlen('password'), 'password', 0, strlen($utf8_password), $utf8_password
		);

		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

				if (defined('NET_SSH2_LOGGING') && NET_SSH2_LOGGING == NET_SSH2_LOG_COMPLEX) {
			$packet = pack('CNa*Na*Na*CNa*',
				NET_SSH2_MSG_USERAUTH_REQUEST, strlen('username'), 'username', strlen('ssh-connection'), 'ssh-connection',
				strlen('password'), 'password', 0, strlen('password'), 'password'
			);
			$this->message_log[count($this->message_log) - 1] = $packet;
		}

		$response = $this->_get_binary_packet();
		if ($response === false) {
			user_error('Connection closed by server', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Ctype', $this->_string_shift($response, 1)));

		switch ($type) {
			case NET_SSH2_MSG_USERAUTH_PASSWD_CHANGEREQ:                 if (defined('NET_SSH2_LOGGING')) {
					$this->message_number_log[count($this->message_number_log) - 1] = 'NET_SSH2_MSG_USERAUTH_PASSWD_CHANGEREQ';
				}
				extract(unpack('Nlength', $this->_string_shift($response, 4)));
				$this->errors[] = 'SSH_MSG_USERAUTH_PASSWD_CHANGEREQ: ' . utf8_decode($this->_string_shift($response, $length));
				return $this->_disconnect(NET_SSH2_DISCONNECT_AUTH_CANCELLED_BY_USER);
			case NET_SSH2_MSG_USERAUTH_FAILURE:
												extract(unpack('Nlength', $this->_string_shift($response, 4)));
				$auth_methods = explode(',', $this->_string_shift($response, $length));
				if (in_array('keyboard-interactive', $auth_methods)) {
					if ($this->_keyboard_interactive_login($username, $password)) {
						$this->bitmap |= NET_SSH2_MASK_LOGIN;
						return true;
					}
					return false;
				}
				return false;
			case NET_SSH2_MSG_USERAUTH_SUCCESS:
				$this->bitmap |= NET_SSH2_MASK_LOGIN;
				return true;
		}

		return false;
	}

	function _keyboard_interactive_login($username, $password)
	{
		$packet = pack('CNa*Na*Na*Na*Na*',
			NET_SSH2_MSG_USERAUTH_REQUEST, strlen($username), $username, strlen('ssh-connection'), 'ssh-connection',
			strlen('keyboard-interactive'), 'keyboard-interactive', 0, '', 0, ''
		);

		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		return $this->_keyboard_interactive_process($password);
	}

	function _keyboard_interactive_process()
	{
		$responses = func_get_args();

		$response = $this->_get_binary_packet();
		if ($response === false) {
			user_error('Connection closed by server', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Ctype', $this->_string_shift($response, 1)));

		switch ($type) {
			case NET_SSH2_MSG_USERAUTH_INFO_REQUEST:
								if (defined('NET_SSH2_LOGGING')) {
					$this->message_number_log[count($this->message_number_log) - 1] = str_replace(
						'UNKNOWN',
						'NET_SSH2_MSG_USERAUTH_INFO_REQUEST',
						$this->message_number_log[count($this->message_number_log) - 1]
					);
				}

				extract(unpack('Nlength', $this->_string_shift($response, 4)));
				$this->_string_shift($response, $length);                 extract(unpack('Nlength', $this->_string_shift($response, 4)));
				$this->_string_shift($response, $length);                 extract(unpack('Nlength', $this->_string_shift($response, 4)));
				$this->_string_shift($response, $length);                 extract(unpack('Nnum_prompts', $this->_string_shift($response, 4)));



								$packet = $logged = pack('CN', NET_SSH2_MSG_USERAUTH_INFO_RESPONSE, count($responses));
				for ($i = 0; $i < count($responses); $i++) {
					$packet.= pack('Na*', strlen($responses[$i]), $responses[$i]);
					$logged.= pack('Na*', strlen('dummy-answer'), 'dummy-answer');
				}

				if (!$this->_send_binary_packet($packet)) {
					return false;
				}

				if (defined('NET_SSH2_LOGGING')) {
					$this->message_number_log[count($this->message_number_log) - 1] = str_replace(
						'UNKNOWN',
						'NET_SSH2_MSG_USERAUTH_INFO_RESPONSE',
						$this->message_number_log[count($this->message_number_log) - 1]
					);
					$this->message_log[count($this->message_log) - 1] = $logged;
				}


												return $this->_keyboard_interactive_process();
			case NET_SSH2_MSG_USERAUTH_SUCCESS:
				return true;
			case NET_SSH2_MSG_USERAUTH_FAILURE:
				return false;
		}

		return false;
	}

	function _privatekey_login($username, $privatekey)
	{
		$publickey = $privatekey->getPublicKey(CRYPT_RSA_PUBLIC_FORMAT_RAW);
		if ($publickey === false) {
			return false;
		}

		$publickey = array(
			'e' => $publickey['e']->toBytes(true),
			'n' => $publickey['n']->toBytes(true)
		);
		$publickey = pack('Na*Na*Na*',
			strlen('ssh-rsa'), 'ssh-rsa', strlen($publickey['e']), $publickey['e'], strlen($publickey['n']), $publickey['n']
		);

		$part1 = pack('CNa*Na*Na*',
			NET_SSH2_MSG_USERAUTH_REQUEST, strlen($username), $username, strlen('ssh-connection'), 'ssh-connection',
			strlen('publickey'), 'publickey'
		);
		$part2 = pack('Na*Na*', strlen('ssh-rsa'), 'ssh-rsa', strlen($publickey), $publickey);

		$packet = $part1 . chr(0) . $part2;
		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		$response = $this->_get_binary_packet();
		if ($response === false) {
			user_error('Connection closed by server', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Ctype', $this->_string_shift($response, 1)));

		switch ($type) {
			case NET_SSH2_MSG_USERAUTH_FAILURE:
				extract(unpack('Nlength', $this->_string_shift($response, 4)));
				$this->errors[] = 'SSH_MSG_USERAUTH_FAILURE: ' . $this->_string_shift($response, $length);
				return $this->_disconnect(NET_SSH2_DISCONNECT_AUTH_CANCELLED_BY_USER);
			case NET_SSH2_MSG_USERAUTH_PK_OK:
												if (defined('NET_SSH2_LOGGING')) {
					$this->message_number_log[count($this->message_number_log) - 1] = str_replace(
						'UNKNOWN',
						'NET_SSH2_MSG_USERAUTH_PK_OK',
						$this->message_number_log[count($this->message_number_log) - 1]
					);
				}
		}

		$packet = $part1 . chr(1) . $part2;
		$privatekey->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
		$signature = $privatekey->sign(pack('Na*a*', strlen($this->session_id), $this->session_id, $packet));
		$signature = pack('Na*Na*', strlen('ssh-rsa'), 'ssh-rsa', strlen($signature), $signature);
		$packet.= pack('Na*', strlen($signature), $signature);

		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		$response = $this->_get_binary_packet();
		if ($response === false) {
			user_error('Connection closed by server', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Ctype', $this->_string_shift($response, 1)));

		switch ($type) {
			case NET_SSH2_MSG_USERAUTH_FAILURE:
								return false;
			case NET_SSH2_MSG_USERAUTH_SUCCESS:
				$this->bitmap |= NET_SSH2_MASK_LOGIN;
				return true;
		}

		return false;
	}

	function exec($command, $block = true)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

										$this->window_size_client_to_server[NET_SSH2_CHANNEL_EXEC] = 0x7FFFFFFF;
						$packet_size = 0x4000;

		$packet = pack('CNa*N3',
			NET_SSH2_MSG_CHANNEL_OPEN, strlen('session'), 'session', NET_SSH2_CHANNEL_EXEC, $this->window_size_client_to_server[NET_SSH2_CHANNEL_EXEC], $packet_size);

		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		$this->channel_status[NET_SSH2_CHANNEL_EXEC] = NET_SSH2_MSG_CHANNEL_OPEN;

		$response = $this->_get_channel_packet(NET_SSH2_CHANNEL_EXEC);
		if ($response === false) {
			return false;
		}


								$packet = pack('CNNa*CNa*',
			NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SSH2_CHANNEL_EXEC], strlen('exec'), 'exec', 1, strlen($command), $command);
		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		$this->channel_status[NET_SSH2_CHANNEL_EXEC] = NET_SSH2_MSG_CHANNEL_REQUEST;

		$response = $this->_get_channel_packet(NET_SSH2_CHANNEL_EXEC);
		if ($response === false) {
			return false;
		}

		$this->channel_status[NET_SSH2_CHANNEL_EXEC] = NET_SSH2_MSG_CHANNEL_DATA;

		if (!$block) {
			return true;
		}

		$output = '';
		while (true) {
			$temp = $this->_get_channel_packet(NET_SSH2_CHANNEL_EXEC);
			switch (true) {
				case $temp === true:
					return $output;
				case $temp === false:
					return false;
				default:
					$output.= $temp;
			}
		}
	}

	function _initShell()
	{
		$this->window_size_client_to_server[NET_SSH2_CHANNEL_SHELL] = 0x7FFFFFFF;
		$packet_size = 0x4000;

		$packet = pack('CNa*N3',
			NET_SSH2_MSG_CHANNEL_OPEN, strlen('session'), 'session', NET_SSH2_CHANNEL_SHELL, $this->window_size_client_to_server[NET_SSH2_CHANNEL_SHELL], $packet_size);

		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		$this->channel_status[NET_SSH2_CHANNEL_SHELL] = NET_SSH2_MSG_CHANNEL_OPEN;

		$response = $this->_get_channel_packet(NET_SSH2_CHANNEL_SHELL);
		if ($response === false) {
			return false;
		}

		$terminal_modes = pack('C', NET_SSH2_TTY_OP_END);
		$packet = pack('CNNa*CNa*N5a*',
			NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SSH2_CHANNEL_SHELL], strlen('pty-req'), 'pty-req', 1, strlen('vt100'), 'vt100',
			80, 24, 0, 0, strlen($terminal_modes), $terminal_modes);

		if (!$this->_send_binary_packet($packet)) {
			return false;
		}


		$response = $this->_get_binary_packet();
		if ($response === false) {
			user_error('Connection closed by server', E_USER_NOTICE);
			return false;
		}

		list(, $type) = unpack('C', $this->_string_shift($response, 1));

		switch ($type) {
			case NET_SSH2_MSG_CHANNEL_SUCCESS:
				break;
			case NET_SSH2_MSG_CHANNEL_FAILURE:
			default:
				user_error('Unable to request pseudo-terminal', E_USER_NOTICE);
				return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
		}

		$packet = pack('CNNa*C',
			NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SSH2_CHANNEL_SHELL], strlen('shell'), 'shell', 1);
		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		$this->channel_status[NET_SSH2_CHANNEL_SHELL] = NET_SSH2_MSG_CHANNEL_REQUEST;

		$response = $this->_get_channel_packet(NET_SSH2_CHANNEL_SHELL);
		if ($response === false) {
			return false;
		}

		$this->channel_status[NET_SSH2_CHANNEL_SHELL] = NET_SSH2_MSG_CHANNEL_DATA;

		$this->bitmap |= NET_SSH2_MASK_SHELL;

		return true;
	}

	function read($expect, $mode = NET_SSH2_READ_SIMPLE)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			user_error('Operation disallowed prior to login()', E_USER_NOTICE);
			return false;
		}

		if (!($this->bitmap & NET_SSH2_MASK_SHELL) && !$this->_initShell()) {
			user_error('Unable to initiate an interactive shell session', E_USER_NOTICE);
			return false;
		}

		$match = $expect;
		while (true) {
			if ($mode == NET_SSH2_READ_REGEX) {
				preg_match($expect, $this->interactiveBuffer, $matches);
				$match = $matches[0];
			}
			$pos = strpos($this->interactiveBuffer, $match);
			if ($pos !== false) {
				return $this->_string_shift($this->interactiveBuffer, $pos + strlen($match));
			}
			$response = $this->_get_channel_packet(NET_SSH2_CHANNEL_SHELL);

			$this->interactiveBuffer.= $response;
		}
	}

	function write($cmd)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			user_error('Operation disallowed prior to login()', E_USER_NOTICE);
			return false;
		}

		if (!($this->bitmap & NET_SSH2_MASK_SHELL) && !$this->_initShell()) {
			user_error('Unable to initiate an interactive shell session', E_USER_NOTICE);
			return false;
		}

		return $this->_send_channel_packet(NET_SSH2_CHANNEL_SHELL, $cmd);
	}

	function disconnect()
	{
		$this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
	}

	function __destruct()
	{
		$this->disconnect();
	}

	function _get_binary_packet()
	{
		if (feof($this->fsock)) {
			user_error('Connection closed prematurely', E_USER_NOTICE);
			return false;
		}

		$start = strtok(microtime(), ' ') + strtok('');         $raw = fread($this->fsock, $this->decrypt_block_size);
		$stop = strtok(microtime(), ' ') + strtok('');

		if (empty($raw)) {
			return '';
		}

		if ($this->decrypt !== false) {
			$raw = $this->decrypt->decrypt($raw);
		}

		extract(unpack('Npacket_length/Cpadding_length', $this->_string_shift($raw, 5)));

		$remaining_length = $packet_length + 4 - $this->decrypt_block_size;
		$buffer = '';
		while ($remaining_length > 0) {
			$temp = fread($this->fsock, $remaining_length);
			$buffer.= $temp;
			$remaining_length-= strlen($temp);
		}
		if (!empty($buffer)) {
			$raw.= $this->decrypt !== false ? $this->decrypt->decrypt($buffer) : $buffer;
			$buffer = $temp = '';
		}

		$payload = $this->_string_shift($raw, $packet_length - $padding_length - 1);
		$padding = $this->_string_shift($raw, $padding_length);
		if ($this->hmac_check !== false) {
			$hmac = fread($this->fsock, $this->hmac_size);
			if ($hmac != $this->hmac_check->hash(pack('NNCa*', $this->get_seq_no, $packet_length, $padding_length, $payload . $padding))) {
				user_error('Invalid HMAC', E_USER_NOTICE);
				return false;
			}
		}


		$this->get_seq_no++;

		if (defined('NET_SSH2_LOGGING')) {
			$temp = isset($this->message_numbers[ord($payload[0])]) ? $this->message_numbers[ord($payload[0])] : 'UNKNOWN (' . ord($payload[0]) . ')';
			$this->message_number_log[] = '<- ' . $temp .
										  ' (' . round($stop - $start, 4) . 's)';
			if (NET_SSH2_LOGGING == NET_SSH2_LOG_COMPLEX) {
				$this->message_log[] = substr($payload, 1);
			}
		}

		return $this->_filter($payload);
	}

	function _filter($payload)
	{
		switch (ord($payload[0])) {
			case NET_SSH2_MSG_DISCONNECT:
				$this->_string_shift($payload, 1);
				extract(unpack('Nreason_code/Nlength', $this->_string_shift($payload, 8)));
				$this->errors[] = 'SSH_MSG_DISCONNECT: ' . $this->disconnect_reasons[$reason_code] . "\r\n" . utf8_decode($this->_string_shift($payload, $length));
				$this->bitmask = 0;
				return false;
			case NET_SSH2_MSG_IGNORE:
				$payload = $this->_get_binary_packet();
				break;
			case NET_SSH2_MSG_DEBUG:
				$this->_string_shift($payload, 2);
				extract(unpack('Nlength', $this->_string_shift($payload, 4)));
				$this->errors[] = 'SSH_MSG_DEBUG: ' . utf8_decode($this->_string_shift($payload, $length));
				$payload = $this->_get_binary_packet();
				break;
			case NET_SSH2_MSG_UNIMPLEMENTED:
				return false;
			case NET_SSH2_MSG_KEXINIT:
				if ($this->session_id !== false) {
					if (!$this->_key_exchange($payload)) {
						$this->bitmask = 0;
						return false;
					}
					$payload = $this->_get_binary_packet();
				}
		}

				if (($this->bitmap & NET_SSH2_MASK_CONSTRUCTOR) && !($this->bitmap & NET_SSH2_MASK_LOGIN) && ord($payload[0]) == NET_SSH2_MSG_USERAUTH_BANNER) {
			$this->_string_shift($payload, 1);
			extract(unpack('Nlength', $this->_string_shift($payload, 4)));
			$this->errors[] = 'SSH_MSG_USERAUTH_BANNER: ' . utf8_decode($this->_string_shift($payload, $length));
			$payload = $this->_get_binary_packet();
		}

				if (($this->bitmap & NET_SSH2_MASK_CONSTRUCTOR) && ($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			switch (ord($payload[0])) {
				case NET_SSH2_MSG_GLOBAL_REQUEST:                     $this->_string_shift($payload, 1);
					extract(unpack('Nlength', $this->_string_shift($payload)));
					$this->errors[] = 'SSH_MSG_GLOBAL_REQUEST: ' . utf8_decode($this->_string_shift($payload, $length));

					if (!$this->_send_binary_packet(pack('C', NET_SSH2_MSG_REQUEST_FAILURE))) {
						return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
					}

					$payload = $this->_get_binary_packet();
					break;
				case NET_SSH2_MSG_CHANNEL_OPEN:                     $this->_string_shift($payload, 1);
					extract(unpack('N', $this->_string_shift($payload, 4)));
					$this->errors[] = 'SSH_MSG_CHANNEL_OPEN: ' . utf8_decode($this->_string_shift($payload, $length));

					$this->_string_shift($payload, 4);                     extract(unpack('Nserver_channel', $this->_string_shift($payload, 4)));

					$packet = pack('CN3a*Na*',
						NET_SSH2_MSG_REQUEST_FAILURE, $server_channel, NET_SSH2_OPEN_ADMINISTRATIVELY_PROHIBITED, 0, '', 0, '');

					if (!$this->_send_binary_packet($packet)) {
						return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
					}

					$payload = $this->_get_binary_packet();
					break;
				case NET_SSH2_MSG_CHANNEL_WINDOW_ADJUST:
					$payload = $this->_get_binary_packet();
			}
		}

		return $payload;
	}

	function _get_channel_packet($client_channel, $skip_extended = false)
	{
		if (!empty($this->channel_buffers[$client_channel])) {
			return array_shift($this->channel_buffers[$client_channel]);
		}

		while (true) {
			$response = $this->_get_binary_packet();
			if ($response === false) {
				user_error('Connection closed by server', E_USER_NOTICE);
				return false;
			}

			if (empty($response)) {
				return '';
			}

			extract(unpack('Ctype/Nchannel', $this->_string_shift($response, 5)));

			switch ($this->channel_status[$channel]) {
				case NET_SSH2_MSG_CHANNEL_OPEN:
					switch ($type) {
						case NET_SSH2_MSG_CHANNEL_OPEN_CONFIRMATION:
							extract(unpack('Nserver_channel', $this->_string_shift($response, 4)));
							$this->server_channels[$client_channel] = $server_channel;
							$this->_string_shift($response, 4);                             $temp = unpack('Npacket_size_client_to_server', $this->_string_shift($response, 4));
							$this->packet_size_client_to_server[$client_channel] = $temp['packet_size_client_to_server'];
							return true;
												default:
							user_error('Unable to open channel', E_USER_NOTICE);
							return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
					}
					break;
				case NET_SSH2_MSG_CHANNEL_REQUEST:
					switch ($type) {
						case NET_SSH2_MSG_CHANNEL_SUCCESS:
							return true;
												default:
							user_error('Unable to request pseudo-terminal', E_USER_NOTICE);
							return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
					}

			}

			switch ($type) {
				case NET_SSH2_MSG_CHANNEL_DATA:

					extract(unpack('Nlength', $this->_string_shift($response, 4)));
					$data = $this->_string_shift($response, $length);
					if ($client_channel == $channel) {
						return $data;
					}
					if (!isset($this->channel_buffers[$client_channel])) {
						$this->channel_buffers[$client_channel] = array();
					}
					$this->channel_buffers[$client_channel][] = $data;
					break;
				case NET_SSH2_MSG_CHANNEL_EXTENDED_DATA:
					if ($skip_extended) {
						break;
					}

										extract(unpack('Ndata_type_code/Nlength', $this->_string_shift($response, 8)));
					$data = $this->_string_shift($response, $length);
					if ($client_channel == $channel) {
						return $data;
					}
					if (!isset($this->channel_buffers[$client_channel])) {
						$this->channel_buffers[$client_channel] = array();
					}
					$this->channel_buffers[$client_channel][] = $data;
					break;
				case NET_SSH2_MSG_CHANNEL_REQUEST:
					extract(unpack('Nlength', $this->_string_shift($response, 4)));
					$value = $this->_string_shift($response, $length);
					switch ($value) {
						case 'exit-signal':
							$this->_string_shift($response, 1);
							extract(unpack('Nlength', $this->_string_shift($response, 4)));
							$this->errors[] = 'SSH_MSG_CHANNEL_REQUEST (exit-signal): ' . $this->_string_shift($response, $length);
							$this->_string_shift($response, 1);
							extract(unpack('Nlength', $this->_string_shift($response, 4)));
							if ($length) {
								$this->errors[count($this->errors)].= "\r\n" . $this->_string_shift($response, $length);
							}
						default:
							break;
					}
					break;
				case NET_SSH2_MSG_CHANNEL_CLOSE:
					$this->_send_binary_packet(pack('CN', NET_SSH2_MSG_CHANNEL_CLOSE, $this->server_channels[$channel]));
					return true;
				case NET_SSH2_MSG_CHANNEL_EOF:
					break;
				default:
					user_error('Error reading channel data', E_USER_NOTICE);
					return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
			}
		}
	}

	function _send_binary_packet($data)
	{
		if (feof($this->fsock)) {
			user_error('Connection closed prematurely', E_USER_NOTICE);
			return false;
		}


				$packet_length = strlen($data) + 9;
				$packet_length+= (($this->encrypt_block_size - 1) * $packet_length) % $this->encrypt_block_size;
				$padding_length = $packet_length - strlen($data) - 5;

		$padding = '';
		for ($i = 0; $i < $padding_length; $i++) {
			$padding.= chr(crypt_random(0, 255));
		}

				$packet = pack('NCa*', $packet_length - 4, $padding_length, $data . $padding);

		$hmac = $this->hmac_create !== false ? $this->hmac_create->hash(pack('Na*', $this->send_seq_no, $packet)) : '';
		$this->send_seq_no++;

		if ($this->encrypt !== false) {
			$packet = $this->encrypt->encrypt($packet);
		}

		$packet.= $hmac;

		$start = strtok(microtime(), ' ') + strtok('');         $result = strlen($packet) == fputs($this->fsock, $packet);
		$stop = strtok(microtime(), ' ') + strtok('');

		if (defined('NET_SSH2_LOGGING')) {
			$temp = isset($this->message_numbers[ord($data[0])]) ? $this->message_numbers[ord($data[0])] : 'UNKNOWN (' . ord($data[0]) . ')';
			$this->message_number_log[] = '-> ' . $temp .
										  ' (' . round($stop - $start, 4) . 's)';
			if (NET_SSH2_LOGGING == NET_SSH2_LOG_COMPLEX) {
				$this->message_log[] = substr($data, 1);
			}
		}

		return $result;
	}

	function _send_channel_packet($client_channel, $data)
	{
		while (strlen($data) > $this->packet_size_client_to_server[$client_channel]) {
						$this->window_size_client_to_server[$client_channel]-= $this->packet_size_client_to_server[$client_channel];
			if ($this->window_size_client_to_server[$client_channel] < 0) {
				$packet = pack('CNN', NET_SSH2_MSG_CHANNEL_WINDOW_ADJUST, $this->server_channels[$client_channel], $this->window_size);
				if (!$this->_send_binary_packet($packet)) {
					return false;
				}
				$this->window_size_client_to_server[$client_channel]+= $this->window_size;
			}

			$packet = pack('CN2a*',
				NET_SSH2_MSG_CHANNEL_DATA,
				$this->server_channels[$client_channel],
				$this->packet_size_client_to_server[$client_channel],
				$this->_string_shift($data, $this->packet_size_client_to_server[$client_channel])
			);

			if (!$this->_send_binary_packet($packet)) {
				return false;
			}
		}

				$this->window_size_client_to_server[$client_channel]-= strlen($data);
		if ($this->window_size_client_to_server[$client_channel] < 0) {
			$packet = pack('CNN', NET_SSH2_MSG_CHANNEL_WINDOW_ADJUST, $this->server_channels[$client_channel], $this->window_size);
			if (!$this->_send_binary_packet($packet)) {
				return false;
			}
			$this->window_size_client_to_server[$client_channel]+= $this->window_size;
		}

		return $this->_send_binary_packet(pack('CN2a*',
			NET_SSH2_MSG_CHANNEL_DATA,
			$this->server_channels[$client_channel],
			strlen($data),
			$data));
	}

	function _close_channel($client_channel)
	{
		$packet = pack('CN',
			NET_SSH2_MSG_CHANNEL_EOF,
			$this->server_channels[$client_channel]);
		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		while ($this->_get_channel_packet($client_channel) !== true);
	}

	function _disconnect($reason)
	{
		if ($this->bitmap) {
			$data = pack('CNNa*Na*', NET_SSH2_MSG_DISCONNECT, $reason, 0, '', 0, '');
			$this->_send_binary_packet($data);
			$this->bitmap = 0;
			fclose($this->fsock);
			return false;
		}
	}

	function _string_shift(&$string, $index = 1)
	{
		$substr = substr($string, 0, $index);
		$string = substr($string, $index);
		return $substr;
	}

	function _define_array()
	{
		$args = func_get_args();
		foreach ($args as $arg) {
			foreach ($arg as $key=>$value) {
				if (!defined($value)) {
					define($value, $key);
				} else {
					break 2;
				}
			}
		}
	}

	function getLog()
	{
		if (!defined('NET_SSH2_LOGGING')) {
			return false;
		}

		switch (NET_SSH2_LOGGING) {
			case NET_SSH2_LOG_SIMPLE:
				return $this->message_number_log;
				break;
			case NET_SSH2_LOG_COMPLEX:
				return $this->_format_log($this->message_log, $this->message_number_log);
				break;
			default:
				return false;
		}
	}

	function _format_log($message_log, $message_number_log)
	{
		static $boundary = ':', $long_width = 65, $short_width = 16;

		$output = '';
		for ($i = 0; $i < count($message_log); $i++) {
			$output.= $message_number_log[$i] . "\r\n";
			$current_log = $message_log[$i];
			$j = 0;
			do {
				if (!empty($current_log)) {
					$output.= str_pad(dechex($j), 7, '0', STR_PAD_LEFT) . '0  ';
				}
				$fragment = $this->_string_shift($current_log, $short_width);
				$hex = substr(
						   preg_replace(
							   '#(.)#es',
							   '"' . $boundary . '" . str_pad(dechex(ord(substr("\\1", -1))), 2, "0", STR_PAD_LEFT)',
							   $fragment),
						   strlen($boundary)
					   );
																$raw = preg_replace('#[^\x20-\x7E]|<#', '.', $fragment);
				$output.= str_pad($hex, $long_width - $short_width, ' ') . $raw . "\r\n";
				$j++;
			} while (!empty($current_log));
			$output.= "\r\n";
		}

		return $output;
	}

	function getErrors()
	{
		return $this->errors;
	}

	function getLastError()
	{
		return $this->errors[count($this->errors) - 1];
	}

	function getServerIdentification()
	{
		return $this->server_identifier;
	}

	function getKexAlgorithms()
	{
		return $this->kex_algorithms;
	}

	function getServerHostKeyAlgorithms()
	{
		return $this->server_host_key_algorithms;
	}

	function getEncryptionAlgorithmsClient2Server()
	{
		return $this->encryption_algorithms_client_to_server;
	}

	function getEncryptionAlgorithmsServer2Client()
	{
		return $this->encryption_algorithms_server_to_client;
	}

	function getMACAlgorithmsClient2Server()
	{
		return $this->mac_algorithms_client_to_server;
	}

	function getMACAlgorithmsServer2Client()
	{
		return $this->mac_algorithms_server_to_client;
	}

	function getCompressionAlgorithmsClient2Server()
	{
		return $this->compression_algorithms_client_to_server;
	}

	function getCompressionAlgorithmsServer2Client()
	{
		return $this->compression_algorithms_server_to_client;
	}

	function getLanguagesServer2Client()
	{
		return $this->languages_server_to_client;
	}


	function getLanguagesClient2Server()
	{
		return $this->languages_client_to_server;
	}

	function getServerPublicHostKey()
	{
		$signature = $this->signature;
		$server_public_host_key = $this->server_public_host_key;

		extract(unpack('Nlength', $this->_string_shift($server_public_host_key, 4)));
		$this->_string_shift($server_public_host_key, $length);

		switch ($this->signature_format) {
			case 'ssh-dss':
				$temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
				$p = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);

				$temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
				$q = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);

				$temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
				$g = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);

				$temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
				$y = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);


				$temp = unpack('Nlength', $this->_string_shift($signature, 4));
				if ($temp['length'] != 40) {
					user_error('Invalid signature', E_USER_NOTICE);
					return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
				}

				$r = new Math_BigInteger($this->_string_shift($signature, 20), 256);
				$s = new Math_BigInteger($this->_string_shift($signature, 20), 256);

				if ($r->compare($q) >= 0 || $s->compare($q) >= 0) {
					user_error('Invalid signature', E_USER_NOTICE);
					return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
				}

				$w = $s->modInverse($q);

				$u1 = $w->multiply(new Math_BigInteger(sha1($this->exchange_hash), 16));
				list(, $u1) = $u1->divide($q);

				$u2 = $w->multiply($r);
				list(, $u2) = $u2->divide($q);

				$g = $g->modPow($u1, $p);
				$y = $y->modPow($u2, $p);

				$v = $g->multiply($y);
				list(, $v) = $v->divide($p);
				list(, $v) = $v->divide($q);

				if (!$v->equals($r)) {
					user_error('Bad server signature', E_USER_NOTICE);
					return $this->_disconnect(NET_SSH2_DISCONNECT_HOST_KEY_NOT_VERIFIABLE);
				}

				break;
			case 'ssh-rsa':
				$temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
				$e = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);

				$temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
				$n = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);
				$nLength = $temp['length'];



				$temp = unpack('Nlength', $this->_string_shift($signature, 4));
				$s = new Math_BigInteger($this->_string_shift($signature, $temp['length']), 256);



				if ($s->compare(new Math_BigInteger()) < 0 || $s->compare($n->subtract(new Math_BigInteger(1))) > 0) {
					user_error('Invalid signature', E_USER_NOTICE);
					return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
				}

				$s = $s->modPow($e, $n);
				$s = $s->toBytes();

				$h = pack('N4H*', 0x00302130, 0x0906052B, 0x0E03021A, 0x05000414, sha1($this->exchange_hash));
				$h = chr(0x01) . str_repeat(chr(0xFF), $nLength - 3 - strlen($h)) . $h;

				if ($s != $h) {
					user_error('Bad server signature', E_USER_NOTICE);
					return $this->_disconnect(NET_SSH2_DISCONNECT_HOST_KEY_NOT_VERIFIABLE);
				}
		}

		return $this->server_public_host_key;
	}
}

class Net_SFTP extends Net_SSH2
{
	var $packet_types = array();
	var $status_codes = array();
	var $request_id = false;
	var $packet_type = -1;
	var $packet_buffer = '';
	var $extensions = array();
	var $version;
	var $pwd = false;
	var $packet_type_log = array();
	var $packet_log = array();
	var $sftp_errors = array();
	var $fileType = 0;

	function __construct($host, $port = 22, $timeout = 10)
	{
		parent::Net_SSH2($host, $port, $timeout);
		$this->packet_types = array(
			1  => 'NET_SFTP_INIT',
			2  => 'NET_SFTP_VERSION',

			3  => 'NET_SFTP_OPEN',
			4  => 'NET_SFTP_CLOSE',
			5  => 'NET_SFTP_READ',
			6  => 'NET_SFTP_WRITE',
			7  => 'NET_SFTP_LSTAT',
			9  => 'NET_SFTP_SETSTAT',
			11 => 'NET_SFTP_OPENDIR',
			12 => 'NET_SFTP_READDIR',
			13 => 'NET_SFTP_REMOVE',
			14 => 'NET_SFTP_MKDIR',
			15 => 'NET_SFTP_RMDIR',
			16 => 'NET_SFTP_REALPATH',
			17 => 'NET_SFTP_STAT',

			18 => 'NET_SFTP_RENAME',

			101=> 'NET_SFTP_STATUS',
			102=> 'NET_SFTP_HANDLE',

			103=> 'NET_SFTP_DATA',
			104=> 'NET_SFTP_NAME',
			105=> 'NET_SFTP_ATTRS',

			200=> 'NET_SFTP_EXTENDED'
		);
		$this->status_codes = array(
			0 => 'NET_SFTP_STATUS_OK',
			1 => 'NET_SFTP_STATUS_EOF',
			2 => 'NET_SFTP_STATUS_NO_SUCH_FILE',
			3 => 'NET_SFTP_STATUS_PERMISSION_DENIED',
			4 => 'NET_SFTP_STATUS_FAILURE',
			5 => 'NET_SFTP_STATUS_BAD_MESSAGE',
			6 => 'NET_SFTP_STATUS_NO_CONNECTION',
			7 => 'NET_SFTP_STATUS_CONNECTION_LOST',
			8 => 'NET_SFTP_STATUS_OP_UNSUPPORTED'
		);
		$this->attributes = array(
			0x00000001 => 'NET_SFTP_ATTR_SIZE',
			0x00000002 => 'NET_SFTP_ATTR_UIDGID',             0x00000004 => 'NET_SFTP_ATTR_PERMISSIONS',
			0x00000008 => 'NET_SFTP_ATTR_ACCESSTIME',
															  -1 << 31 => 'NET_SFTP_ATTR_EXTENDED'
		);
		$this->open_flags = array(
			0x00000001 => 'NET_SFTP_OPEN_READ',
			0x00000002 => 'NET_SFTP_OPEN_WRITE',
			0x00000008 => 'NET_SFTP_OPEN_CREATE',
			0x00000010 => 'NET_SFTP_OPEN_TRUNCATE'
		);
		$this->file_types = array(
			1 => 'NET_SFTP_TYPE_REGULAR',
			2 => 'NET_SFTP_TYPE_DIRECTORY',
			3 => 'NET_SFTP_TYPE_SYMLINK',
			4 => 'NET_SFTP_TYPE_SPECIAL'
		);
		$this->_define_array(
			$this->packet_types,
			$this->status_codes,
			$this->attributes,
			$this->open_flags,
			$this->file_types
		);
	}

	function login($username, $password = '')
	{
		if (!parent::login($username, $password)) {
			return false;
		}

		$this->window_size_client_to_server[NET_SFTP_CHANNEL] = $this->window_size;

		$packet = pack('CNa*N3',
			NET_SSH2_MSG_CHANNEL_OPEN, strlen('session'), 'session', NET_SFTP_CHANNEL, $this->window_size, 0x4000);

		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		$this->channel_status[NET_SFTP_CHANNEL] = NET_SSH2_MSG_CHANNEL_OPEN;

		$response = $this->_get_channel_packet(NET_SFTP_CHANNEL);
		if ($response === false) {
			return false;
		}

		$packet = pack('CNNa*CNa*',
			NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SFTP_CHANNEL], strlen('subsystem'), 'subsystem', 1, strlen('sftp'), 'sftp');
		if (!$this->_send_binary_packet($packet)) {
			return false;
		}

		$this->channel_status[NET_SFTP_CHANNEL] = NET_SSH2_MSG_CHANNEL_REQUEST;

		$response = $this->_get_channel_packet(NET_SFTP_CHANNEL);
		if ($response === false) {
			return false;
		}

		$this->channel_status[NET_SFTP_CHANNEL] = NET_SSH2_MSG_CHANNEL_DATA;

		if (!$this->_send_sftp_packet(NET_SFTP_INIT, "\0\0\0\3")) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		if ($this->packet_type != NET_SFTP_VERSION) {
			user_error('Expected SSH_FXP_VERSION', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Nversion', $this->_string_shift($response, 4)));
		$this->version = $version;
		while (!empty($response)) {
			extract(unpack('Nlength', $this->_string_shift($response, 4)));
			$key = $this->_string_shift($response, $length);
			extract(unpack('Nlength', $this->_string_shift($response, 4)));
			$value = $this->_string_shift($response, $length);
			$this->extensions[$key] = $value;
		}

		$this->request_id = 1;

		if ($this->version != 3) {
			return false;
		}

		$this->pwd = $this->_realpath('.');

		return true;
	}

	function pwd()
	{
		return $this->pwd;
	}

	function _realpath($dir)
	{

		$file = '';
		if ($this->pwd !== false) {
												if ($dir[strlen($dir) - 1] != '/') {
				$file = basename($dir);
				$dir = dirname($dir);
			}

			if ($dir == '.' || $dir == $this->pwd) {
				return $this->pwd . $file;
			}

			if ($dir[0] != '/') {
				$dir = $this->pwd . '/' . $dir;
			}
											}


				if (!$this->_send_sftp_packet(NET_SFTP_REALPATH, pack('Na*', strlen($dir), $dir))) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		switch ($this->packet_type) {
			case NET_SFTP_NAME:
																$this->_string_shift($response, 4);                 extract(unpack('Nlength', $this->_string_shift($response, 4)));
				$realpath = $this->_string_shift($response, $length);
																extract(unpack('Nlength', $this->_string_shift($response, 4)));
				$this->fileType = $this->_parseLongname($this->_string_shift($response, $length));
				break;
			case NET_SFTP_STATUS:
				extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
				$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
				return false;
			default:
				user_error('Expected SSH_FXP_NAME or SSH_FXP_STATUS', E_USER_NOTICE);
				return false;
		}

						return $realpath . '/' . $file;
	}

	function chdir($dir)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		if ($dir[strlen($dir) - 1] != '/') {
			$dir.= '/';
		}
		$dir = $this->_realpath($dir);

				if (!$this->_send_sftp_packet(NET_SFTP_OPENDIR, pack('Na*', strlen($dir), $dir))) {
			return false;
		}

				$response = $this->_get_sftp_packet();
		switch ($this->packet_type) {
			case NET_SFTP_HANDLE:
				$handle = substr($response, 4);
				break;
			case NET_SFTP_STATUS:
				extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
				$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
				return false;
			default:
				user_error('Expected SSH_FXP_HANDLE or SSH_FXP_STATUS', E_USER_NOTICE);
				return false;
		}

		if (!$this->_send_sftp_packet(NET_SFTP_CLOSE, pack('Na*', strlen($handle), $handle))) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		if ($this->packet_type != NET_SFTP_STATUS) {
			user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Nstatus', $this->_string_shift($response, 4)));
		if ($status != NET_SFTP_STATUS_OK) {
			extract(unpack('Nlength', $this->_string_shift($response, 4)));
			$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
			return false;
		}

		$this->pwd = $dir;
		return true;
	}

	function nlist($dir = '.')
	{
		return $this->_list($dir, false);
	}

	function rawlist($dir = '.')
	{
		return $this->_list($dir, true);
	}

	function _list($dir, $raw = true)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		$dir = $this->_realpath($dir);
		if ($dir === false) {
			return false;
		}

				if (!$this->_send_sftp_packet(NET_SFTP_OPENDIR, pack('Na*', strlen($dir), $dir))) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		switch ($this->packet_type) {
			case NET_SFTP_HANDLE:
																$handle = substr($response, 4);
				break;
			case NET_SFTP_STATUS:
								extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
				$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
				return false;
			default:
				user_error('Expected SSH_FXP_HANDLE or SSH_FXP_STATUS', E_USER_NOTICE);
				return false;
		}

		$contents = array();
		while (true) {
												if (!$this->_send_sftp_packet(NET_SFTP_READDIR, pack('Na*', strlen($handle), $handle))) {
				return false;
			}

			$response = $this->_get_sftp_packet();
			switch ($this->packet_type) {
				case NET_SFTP_NAME:
					extract(unpack('Ncount', $this->_string_shift($response, 4)));
					for ($i = 0; $i < $count; $i++) {
						extract(unpack('Nlength', $this->_string_shift($response, 4)));
						$shortname = $this->_string_shift($response, $length);
						extract(unpack('Nlength', $this->_string_shift($response, 4)));
						$longname = $this->_string_shift($response, $length);
						$attributes = $this->_parseAttributes($response);                         if (!$raw) {
							$contents[] = $shortname;
						} else {
							$contents[$shortname] = $attributes;
							$fileType = $this->_parseLongname($longname);
							if ($fileType) {
								$contents[$shortname]['type'] = $fileType;
							}
						}
																	}
					break;
				case NET_SFTP_STATUS:
					extract(unpack('Nstatus', $this->_string_shift($response, 4)));
					if ($status != NET_SFTP_STATUS_EOF) {
						extract(unpack('Nlength', $this->_string_shift($response, 4)));
						$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
						return false;
					}
					break 2;
				default:
					user_error('Expected SSH_FXP_NAME or SSH_FXP_STATUS', E_USER_NOTICE);
					return false;
			}
		}

		if (!$this->_send_sftp_packet(NET_SFTP_CLOSE, pack('Na*', strlen($handle), $handle))) {
			return false;
		}

						$response = $this->_get_sftp_packet();
		if ($this->packet_type != NET_SFTP_STATUS) {
			user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Nstatus', $this->_string_shift($response, 4)));
		if ($status != NET_SFTP_STATUS_OK) {
			extract(unpack('Nlength', $this->_string_shift($response, 4)));
			$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
			return false;
		}

		return $contents;
	}

	function size($filename)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		$filename = $this->_realpath($filename);
		if ($filename === false) {
			return false;
		}

		return $this->_size($filename);
	}

	function stat($filename)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		$filename = $this->_realpath($filename);
		if ($filename === false) {
			return false;
		}

		return $this->_stat($filename, NET_SFTP_STAT);
	}


	function lstat($filename)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		$filename = $this->_realpath($filename);
		if ($filename === false) {
			return false;
		}

		return $this->_stat($filename, NET_SFTP_LSTAT);
	}

	function _stat($filename, $type)
	{
				$packet = pack('Na*', strlen($filename), $filename);
		if (!$this->_send_sftp_packet($type, $packet)) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		switch ($this->packet_type) {
			case NET_SFTP_ATTRS:
				$attributes = $this->_parseAttributes($response);
				if ($this->fileType) {
					$attributes['type'] = $this->fileType;
				}
				return $attributes;
			case NET_SFTP_STATUS:
				extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
				$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
				return false;
		}

		user_error('Expected SSH_FXP_ATTRS or SSH_FXP_STATUS', E_USER_NOTICE);
		return false;
	}

	function _size($filename)
	{
		$result = $this->_stat($filename, NET_SFTP_LSTAT);
		return $result === false ? false : $result['size'];
	}

	// !!! This is different to phpseclib.
	function chmod($filename, $mode)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		$filename = $this->_realpath($filename);
		if ($filename === false) {
			return false;
		}

		$attr = pack('N2', NET_SFTP_ATTR_PERMISSIONS, $mode & 07777);
		if (!$this->_send_sftp_packet(NET_SFTP_SETSTAT, pack('Na*a*', strlen($filename), $filename, $attr))) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		if ($this->packet_type != NET_SFTP_STATUS) {
			user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Nstatus', $this->_string_shift($response, 4)));
		if ($status != NET_SFTP_STATUS_EOF) {
			extract(unpack('Nlength', $this->_string_shift($response, 4)));
			$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
		}

		$packet = pack('Na*', strlen($filename), $filename);
		if (!$this->_send_sftp_packet(NET_SFTP_STAT, $packet)) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		switch ($this->packet_type) {
			case NET_SFTP_ATTRS:
				$attrs = $this->_parseAttributes($response);
				return $attrs['permissions'];
			case NET_SFTP_STATUS:
				extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
				$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
				return false;
		}

		user_error('Expected SSH_FXP_ATTRS or SSH_FXP_STATUS', E_USER_NOTICE);
		return false;
	}

	function mkdir($dir)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		$dir = $this->_realpath(rtrim($dir, '/'));
		if ($dir === false) {
			return false;
		}

		if (!$this->_send_sftp_packet(NET_SFTP_MKDIR, pack('Na*N', strlen($dir), $dir, 0))) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		if ($this->packet_type != NET_SFTP_STATUS) {
			user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Nstatus', $this->_string_shift($response, 4)));
		if ($status != NET_SFTP_STATUS_OK) {
			extract(unpack('Nlength', $this->_string_shift($response, 4)));
			$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
			return false;
		}

		return true;
	}

	function rmdir($dir)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		$dir = $this->_realpath($dir);
		if ($dir === false) {
			return false;
		}

		if (!$this->_send_sftp_packet(NET_SFTP_RMDIR, pack('Na*', strlen($dir), $dir))) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		if ($this->packet_type != NET_SFTP_STATUS) {
			user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Nstatus', $this->_string_shift($response, 4)));
		if ($status != NET_SFTP_STATUS_OK) {
			extract(unpack('Nlength', $this->_string_shift($response, 4)));
			$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
			return false;
		}

		return true;
	}

	function put($remote_file, $data, $mode = NET_SFTP_STRING)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		$remote_file = $this->_realpath($remote_file);
		if ($remote_file === false) {
			return false;
		}

		$packet = pack('Na*N2', strlen($remote_file), $remote_file, NET_SFTP_OPEN_WRITE | NET_SFTP_OPEN_CREATE | NET_SFTP_OPEN_TRUNCATE, 0);
		if (!$this->_send_sftp_packet(NET_SFTP_OPEN, $packet)) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		switch ($this->packet_type) {
			case NET_SFTP_HANDLE:
				$handle = substr($response, 4);
				break;
			case NET_SFTP_STATUS:
				extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
				$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
				return false;
			default:
				user_error('Expected SSH_FXP_HANDLE or SSH_FXP_STATUS', E_USER_NOTICE);
				return false;
		}

		$initialize = true;

		if ($mode == NET_SFTP_LOCAL_FILE) {
			if (!is_file($data)) {
				user_error("$data is not a valid file", E_USER_NOTICE);
				return false;
			}
			$fp = @fopen($data, 'rb');
			if (!$fp) {
				return false;
			}
			$sent = 0;
			$size = filesize($data);
		} else {
			$sent = 0;
			$size = strlen($data);
		}

		$size = $size < 0 ? ($size & 0x7FFFFFFF) + 0x80000000 : $size;

		$sftp_packet_size = 4096;
		$i = 0;
		while ($sent < $size) {
			$temp = $mode == NET_SFTP_LOCAL_FILE ? fread($fp, $sftp_packet_size) : $this->_string_shift($data, $sftp_packet_size);
			$packet = pack('Na*N3a*', strlen($handle), $handle, 0, $sent, strlen($temp), $temp);
			if (!$this->_send_sftp_packet(NET_SFTP_WRITE, $packet)) {
				fclose($fp);
				return false;
			}
			$sent+= strlen($temp);

			$i++;

			if ($i == 50) {
				if (!$this->_read_put_responses($i)) {
					$i = 0;
					break;
				}
				$i = 0;
			}
		}

		$this->_read_put_responses($i);

		if ($mode == NET_SFTP_LOCAL_FILE) {
			fclose($fp);
		}

		if (!$this->_send_sftp_packet(NET_SFTP_CLOSE, pack('Na*', strlen($handle), $handle))) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		if ($this->packet_type != NET_SFTP_STATUS) {
			user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Nstatus', $this->_string_shift($response, 4)));
		if ($status != NET_SFTP_STATUS_OK) {
			extract(unpack('Nlength', $this->_string_shift($response, 4)));
			$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
			return false;
		}

		return true;
	}

	function _read_put_responses($i)
	{
		while ($i--) {
			$response = $this->_get_sftp_packet();
			if ($this->packet_type != NET_SFTP_STATUS) {
				user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
				return false;
			}

			extract(unpack('Nstatus', $this->_string_shift($response, 4)));
			if ($status != NET_SFTP_STATUS_OK) {
				extract(unpack('Nlength', $this->_string_shift($response, 4)));
				$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
				break;
			}
		}

		return $i < 0;
	}

	function get($remote_file, $local_file = false)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		$remote_file = $this->_realpath($remote_file);
		if ($remote_file === false) {
			return false;
		}

		$size = $this->_size($remote_file);
		if ($size === false) {
			return false;
		}

		$packet = pack('Na*N2', strlen($remote_file), $remote_file, NET_SFTP_OPEN_READ, 0);
		if (!$this->_send_sftp_packet(NET_SFTP_OPEN, $packet)) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		switch ($this->packet_type) {
			case NET_SFTP_HANDLE:
				$handle = substr($response, 4);
				break;
			case NET_SFTP_STATUS:                 extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
				$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
				return false;
			default:
				user_error('Expected SSH_FXP_HANDLE or SSH_FXP_STATUS', E_USER_NOTICE);
				return false;
		}

		if ($local_file !== false) {
			$fp = fopen($local_file, 'wb');
			if (!$fp) {
				return false;
			}
		} else {
			$content = '';
		}

		$read = 0;
		while ($read < $size) {
			$packet = pack('Na*N3', strlen($handle), $handle, 0, $read, 1 << 20);
			if (!$this->_send_sftp_packet(NET_SFTP_READ, $packet)) {
				return false;
			}

			$response = $this->_get_sftp_packet();
			switch ($this->packet_type) {
				case NET_SFTP_DATA:
					$temp = substr($response, 4);
					$read+= strlen($temp);
					if ($local_file === false) {
						$content.= $temp;
					} else {
						fputs($fp, $temp);
					}
					break;
				case NET_SFTP_STATUS:
					extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
					$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
					break 2;
				default:
					user_error('Expected SSH_FXP_DATA or SSH_FXP_STATUS', E_USER_NOTICE);
					return false;
			}
		}

		if (!$this->_send_sftp_packet(NET_SFTP_CLOSE, pack('Na*', strlen($handle), $handle))) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		if ($this->packet_type != NET_SFTP_STATUS) {
			user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
			return false;
		}

		extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
		$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);

				if ($status != NET_SFTP_STATUS_OK) {
			return false;
		}

		extract(unpack('Nstatus', $this->_string_shift($response, 4)));
		if ($status != NET_SFTP_STATUS_OK) {
			extract(unpack('Nlength', $this->_string_shift($response, 4)));
			$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
			return false;
		}

		if (isset($content)) {
			return $content;
		}

		fclose($fp);
		return true;
	}

	function delete($path)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		$path = $this->_realpath($path);
		if ($path === false) {
			return false;
		}

				if (!$this->_send_sftp_packet(NET_SFTP_REMOVE, pack('Na*', strlen($path), $path))) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		if ($this->packet_type != NET_SFTP_STATUS) {
			user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
			return false;
		}

				extract(unpack('Nstatus', $this->_string_shift($response, 4)));
		if ($status != NET_SFTP_STATUS_OK) {
			extract(unpack('Nlength', $this->_string_shift($response, 4)));
			$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
			return false;
		}

		return true;
	}

	function rename($oldname, $newname)
	{
		if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
			return false;
		}

		$oldname = $this->_realpath($oldname);
		$newname = $this->_realpath($newname);
		if ($oldname === false || $newname === false) {
			return false;
		}

				$packet = pack('Na*Na*', strlen($oldname), $oldname, strlen($newname), $newname);
		if (!$this->_send_sftp_packet(NET_SFTP_RENAME, $packet)) {
			return false;
		}

		$response = $this->_get_sftp_packet();
		if ($this->packet_type != NET_SFTP_STATUS) {
			user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
			return false;
		}

				extract(unpack('Nstatus', $this->_string_shift($response, 4)));
		if ($status != NET_SFTP_STATUS_OK) {
			extract(unpack('Nlength', $this->_string_shift($response, 4)));
			$this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
			return false;
		}

		return true;
	}

	function _parseAttributes(&$response)
	{
		$attr = array();
		extract(unpack('Nflags', $this->_string_shift($response, 4)));
				foreach ($this->attributes as $key => $value) {
			switch ($flags & $key) {
				case NET_SFTP_ATTR_SIZE:                                                                                                                         extract(unpack('Nupper/Nsize', $this->_string_shift($response, 8)));
					if ($upper) {
						$attr['size'] = 0xFFFFFFFF;
					} else {
						$attr['size'] = $size < 0 ? ($size & 0x7FFFFFFF) + 0x80000000 : $size;
					}
					break;
				case NET_SFTP_ATTR_UIDGID:                     $attr+= unpack('Nuid/Ngid', $this->_string_shift($response, 8));
					break;
				case NET_SFTP_ATTR_PERMISSIONS:                     $attr+= unpack('Npermissions', $this->_string_shift($response, 4));
					break;
				case NET_SFTP_ATTR_ACCESSTIME:                     $attr+= unpack('Natime/Nmtime', $this->_string_shift($response, 8));
					break;
				case NET_SFTP_ATTR_EXTENDED:                     extract(unpack('Ncount', $this->_string_shift($response, 4)));
					for ($i = 0; $i < $count; $i++) {
						extract(unpack('Nlength', $this->_string_shift($response, 4)));
						$key = $this->_string_shift($response, $length);
						extract(unpack('Nlength', $this->_string_shift($response, 4)));
						$attr[$key] = $this->_string_shift($response, $length);
					}
			}
		}
		return $attr;
	}

	function _parseLongname($longname)
	{
				if (preg_match('#^[^/]([r-][w-][x-]){3}#', $longname)) {
			switch ($longname[0]) {
				case '-':
					return NET_SFTP_TYPE_REGULAR;
				case 'd':
					return NET_SFTP_TYPE_DIRECTORY;
				case 'l':
					return NET_SFTP_TYPE_SYMLINK;
				default:
					return NET_SFTP_TYPE_SPECIAL;
			}
		}

		return false;
	}

	function _send_sftp_packet($type, $data)
	{
		$packet = $this->request_id !== false ?
			pack('NCNa*', strlen($data) + 5, $type, $this->request_id, $data) :
			pack('NCa*',  strlen($data) + 1, $type, $data);

		$start = strtok(microtime(), ' ') + strtok('');         $result = $this->_send_channel_packet(NET_SFTP_CHANNEL, $packet);
		$stop = strtok(microtime(), ' ') + strtok('');

		if (defined('NET_SFTP_LOGGING')) {
			$this->packet_type_log[] = '-> ' . $this->packet_types[$type] .
									   ' (' . round($stop - $start, 4) . 's)';
			if (NET_SFTP_LOGGING == NET_SFTP_LOG_COMPLEX) {
				$this->packet_log[] = $data;
			}
		}

		return $result;
	}

	function _get_sftp_packet()
	{
		$start = strtok(microtime(), ' ') + strtok('');
				while (strlen($this->packet_buffer) < 4) {
			$temp = $this->_get_channel_packet(NET_SFTP_CHANNEL);
			if (is_bool($temp)) {
				$this->packet_type = false;
				$this->packet_buffer = '';
				return false;
			}
			$this->packet_buffer.= $temp;
		}
		extract(unpack('Nlength', $this->_string_shift($this->packet_buffer, 4)));
		$tempLength = $length;
		$tempLength-= strlen($this->packet_buffer);

				while ($tempLength > 0) {
			$temp = $this->_get_channel_packet(NET_SFTP_CHANNEL);
			if (is_bool($temp)) {
				$this->packet_type = false;
				$this->packet_buffer = '';
				return false;
			}
			$this->packet_buffer.= $temp;
			$tempLength-= strlen($temp);
		}

		$stop = strtok(microtime(), ' ') + strtok('');

		$this->packet_type = ord($this->_string_shift($this->packet_buffer));

		if ($this->request_id !== false) {
			$this->_string_shift($this->packet_buffer, 4);             $length-= 5;         } else {
			$length-= 1;         }

		$packet = $this->_string_shift($this->packet_buffer, $length);

		if (defined('NET_SFTP_LOGGING')) {
			$this->packet_type_log[] = '<- ' . $this->packet_types[$this->packet_type] .
									   ' (' . round($stop - $start, 4) . 's)';
			if (NET_SFTP_LOGGING == NET_SFTP_LOG_COMPLEX) {
				$this->packet_log[] = $packet;
			}
		}

		return $packet;
	}

	function getSFTPLog()
	{
		if (!defined('NET_SFTP_LOGGING')) {
			return false;
		}

		switch (NET_SFTP_LOGGING) {
			case NET_SFTP_LOG_COMPLEX:
				return $this->_format_log($this->packet_log, $this->packet_type_log);
				break;
						default:
				return $this->packet_type_log;
		}
	}

	function getSFTPErrors()
	{
		return $this->sftp_errors;
	}

	function getLastSFTPError()
	{
		return count($this->sftp_errors) ? $this->sftp_errors[count($this->sftp_errors) - 1] : '';
	}

	function getSupportedVersions()
	{
		$temp = array('version' => $this->version);
		if (isset($this->extensions['versions'])) {
			$temp['extensions'] = $this->extensions['versions'];
		}
		return $temp;
	}

	function _disconnect($reason)
	{
		$this->pwd = false;
		parent::_disconnect($reason);
	}
}

class Math_BigInteger {

	var $value;
	var $is_negative = false;
	var $generator = 'mt_rand';
	var $precision = -1;
	var $bitmask = false;
	var $hex;

	function __construct($x = 0, $base = 10)
	{
		if ( !defined('MATH_BIGINTEGER_MODE') ) {
			switch (true) {
				case extension_loaded('gmp'):
					define('MATH_BIGINTEGER_MODE', MATH_BIGINTEGER_MODE_GMP);
					break;
				case extension_loaded('bcmath'):
					define('MATH_BIGINTEGER_MODE', MATH_BIGINTEGER_MODE_BCMATH);
					break;
				default:
					define('MATH_BIGINTEGER_MODE', MATH_BIGINTEGER_MODE_INTERNAL);
			}
		}

		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				if (is_resource($x) && get_resource_type($x) == 'GMP integer') {
					$this->value = $x;
					return;
				}
				$this->value = gmp_init(0);
				break;
			case MATH_BIGINTEGER_MODE_BCMATH:
				$this->value = '0';
				break;
			default:
				$this->value = array();
		}

		if (empty($x)) {
			return;
		}

		switch ($base) {
			case -256:
				if (ord($x[0]) & 0x80) {
					$x = ~$x;
					$this->is_negative = true;
				}
			case  256:
				switch ( MATH_BIGINTEGER_MODE ) {
					case MATH_BIGINTEGER_MODE_GMP:
						$sign = $this->is_negative ? '-' : '';
						$this->value = gmp_init($sign . '0x' . bin2hex($x));
						break;
					case MATH_BIGINTEGER_MODE_BCMATH:
						$len = (strlen($x) + 3) & 0xFFFFFFFC;

						$x = str_pad($x, $len, chr(0), STR_PAD_LEFT);

						for ($i = 0; $i < $len; $i+= 4) {
							$this->value = bcmul($this->value, '4294967296', 0);
							$this->value = bcadd($this->value, 0x1000000 * ord($x[$i]) + ((ord($x[$i + 1]) << 16) | (ord($x[$i + 2]) << 8) | ord($x[$i + 3])), 0);
						}

						if ($this->is_negative) {
							$this->value = '-' . $this->value;
						}

						break;
					default:
						while (strlen($x)) {
							$this->value[] = $this->_bytes2int($this->_base256_rshift($x, 26));
						}
				}

				if ($this->is_negative) {
					if (MATH_BIGINTEGER_MODE != MATH_BIGINTEGER_MODE_INTERNAL) {
						$this->is_negative = false;
					}
					$temp = $this->add(new Math_BigInteger('-1'));
					$this->value = $temp->value;
				}
				break;
			case  16:
			case -16:
				if ($base > 0 && $x[0] == '-') {
					$this->is_negative = true;
					$x = substr($x, 1);
				}

				$x = preg_replace('#^(?:0x)?([A-Fa-f0-9]*).*#', '$1', $x);

				$is_negative = false;
				if ($base < 0 && hexdec($x[0]) >= 8) {
					$this->is_negative = $is_negative = true;
					$x = bin2hex(~pack('H*', $x));
				}

				switch ( MATH_BIGINTEGER_MODE ) {
					case MATH_BIGINTEGER_MODE_GMP:
						$temp = $this->is_negative ? '-0x' . $x : '0x' . $x;
						$this->value = gmp_init($temp);
						$this->is_negative = false;
						break;
					case MATH_BIGINTEGER_MODE_BCMATH:
						$x = ( strlen($x) & 1 ) ? '0' . $x : $x;
						$temp = new Math_BigInteger(pack('H*', $x), 256);
						$this->value = $this->is_negative ? '-' . $temp->value : $temp->value;
						$this->is_negative = false;
						break;
					default:
						$x = ( strlen($x) & 1 ) ? '0' . $x : $x;
						$temp = new Math_BigInteger(pack('H*', $x), 256);
						$this->value = $temp->value;
				}

				if ($is_negative) {
					$temp = $this->add(new Math_BigInteger('-1'));
					$this->value = $temp->value;
				}
				break;
			case  10:
			case -10:
				$x = preg_replace('#^(-?[0-9]*).*#', '$1', $x);

				switch ( MATH_BIGINTEGER_MODE ) {
					case MATH_BIGINTEGER_MODE_GMP:
						$this->value = gmp_init($x);
						break;
					case MATH_BIGINTEGER_MODE_BCMATH:
						$this->value = (string) $x;
						break;
					default:
						$temp = new Math_BigInteger();

						$multiplier = new Math_BigInteger();
						$multiplier->value = array(10000000);

						if ($x[0] == '-') {
							$this->is_negative = true;
							$x = substr($x, 1);
						}

						$x = str_pad($x, strlen($x) + (6 * strlen($x)) % 7, 0, STR_PAD_LEFT);

						while (strlen($x)) {
							$temp = $temp->multiply($multiplier);
							$temp = $temp->add(new Math_BigInteger($this->_int2bytes(substr($x, 0, 7)), 256));
							$x = substr($x, 7);
						}

						$this->value = $temp->value;
				}
				break;
			case  2:
			case -2:
				if ($base > 0 && $x[0] == '-') {
					$this->is_negative = true;
					$x = substr($x, 1);
				}

				$x = preg_replace('#^([01]*).*#', '$1', $x);
				$x = str_pad($x, strlen($x) + (3 * strlen($x)) % 4, 0, STR_PAD_LEFT);

				$str = '0x';
				while (strlen($x)) {
					$part = substr($x, 0, 4);
					$str.= dechex(bindec($part));
					$x = substr($x, 4);
				}

				if ($this->is_negative) {
					$str = '-' . $str;
				}

				$temp = new Math_BigInteger($str, 8 * $base);
				$this->value = $temp->value;
				$this->is_negative = $temp->is_negative;

				break;
			default:
		}
	}

	function toBytes($twos_compliment = false)
	{
		if ($twos_compliment) {
			$comparison = $this->compare(new Math_BigInteger());
			if ($comparison == 0) {
				return $this->precision > 0 ? str_repeat(chr(0), ($this->precision + 1) >> 3) : '';
			}

			$temp = $comparison < 0 ? $this->add(new Math_BigInteger(1)) : $this->copy();
			$bytes = $temp->toBytes();

			if (empty($bytes)) {
				$bytes = chr(0);
			}

			if (ord($bytes[0]) & 0x80) {
				$bytes = chr(0) . $bytes;
			}

			return $comparison < 0 ? ~$bytes : $bytes;
		}

		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				if (gmp_cmp($this->value, gmp_init(0)) == 0) {
					return $this->precision > 0 ? str_repeat(chr(0), ($this->precision + 1) >> 3) : '';
				}

				$temp = gmp_strval(gmp_abs($this->value), 16);
				$temp = ( strlen($temp) & 1 ) ? '0' . $temp : $temp;
				$temp = pack('H*', $temp);

				return $this->precision > 0 ?
					substr(str_pad($temp, $this->precision >> 3, chr(0), STR_PAD_LEFT), -($this->precision >> 3)) :
					ltrim($temp, chr(0));
			case MATH_BIGINTEGER_MODE_BCMATH:
				if ($this->value === '0') {
					return $this->precision > 0 ? str_repeat(chr(0), ($this->precision + 1) >> 3) : '';
				}

				$value = '';
				$current = $this->value;

				if ($current[0] == '-') {
					$current = substr($current, 1);
				}

				while (bccomp($current, '0', 0) > 0) {
					$temp = bcmod($current, '16777216');
					$value = chr($temp >> 16) . chr($temp >> 8) . chr($temp) . $value;
					$current = bcdiv($current, '16777216', 0);
				}

				return $this->precision > 0 ?
					substr(str_pad($value, $this->precision >> 3, chr(0), STR_PAD_LEFT), -($this->precision >> 3)) :
					ltrim($value, chr(0));
		}

		if (!count($this->value)) {
			return $this->precision > 0 ? str_repeat(chr(0), ($this->precision + 1) >> 3) : '';
		}
		$result = $this->_int2bytes($this->value[count($this->value) - 1]);

		$temp = $this->copy();

		for ($i = count($temp->value) - 2; $i >= 0; --$i) {
			$temp->_base256_lshift($result, 26);
			$result = $result | str_pad($temp->_int2bytes($temp->value[$i]), strlen($result), chr(0), STR_PAD_LEFT);
		}

		return $this->precision > 0 ?
			str_pad(substr($result, -(($this->precision + 7) >> 3)), ($this->precision + 7) >> 3, chr(0), STR_PAD_LEFT) :
			$result;
	}


	function toHex($twos_compliment = false)
	{
		return bin2hex($this->toBytes($twos_compliment));
	}


	function toBits($twos_compliment = false)
	{
		$hex = $this->toHex($twos_compliment);
		$bits = '';
		for ($i = 0, $end = strlen($hex) & 0xFFFFFFF8; $i < $end; $i+=8) {
			$bits.= str_pad(decbin(hexdec(substr($hex, $i, 8))), 32, '0', STR_PAD_LEFT);
		}
		if ($end != strlen($hex)) {
			$bits.= str_pad(decbin(hexdec(substr($hex, $end))), strlen($hex) & 7, '0', STR_PAD_LEFT);
		}
		return $this->precision > 0 ? substr($bits, -$this->precision) : ltrim($bits, '0');
	}


	function toString()
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				return gmp_strval($this->value);
			case MATH_BIGINTEGER_MODE_BCMATH:
				if ($this->value === '0') {
					return '0';
				}

				return ltrim($this->value, '0');
		}

		if (!count($this->value)) {
			return '0';
		}

		$temp = $this->copy();
		$temp->is_negative = false;

		$divisor = new Math_BigInteger();
		$divisor->value = array(10000000);
		$result = '';
		while (count($temp->value)) {
			list($temp, $mod) = $temp->divide($divisor);
			$result = str_pad(isset($mod->value[0]) ? $mod->value[0] : '', 7, '0', STR_PAD_LEFT) . $result;
		}
		$result = ltrim($result, '0');
		if (empty($result)) {
			$result = '0';
		}

		if ($this->is_negative) {
			$result = '-' . $result;
		}

		return $result;
	}


	function copy()
	{
		$temp = new Math_BigInteger();
		$temp->value = $this->value;
		$temp->is_negative = $this->is_negative;
		$temp->generator = $this->generator;
		$temp->precision = $this->precision;
		$temp->bitmask = $this->bitmask;
		return $temp;
	}


	function __toString()
	{
		return $this->toString();
	}


	function __clone()
	{
		return $this->copy();
	}


	function __sleep()
	{
		$this->hex = $this->toHex(true);
		$vars = array('hex');
		if ($this->generator != 'mt_rand') {
			$vars[] = 'generator';
		}
		if ($this->precision > 0) {
			$vars[] = 'precision';
		}
		return $vars;

	}


	function __wakeup()
	{
		$temp = new Math_BigInteger($this->hex, -16);
		$this->value = $temp->value;
		$this->is_negative = $temp->is_negative;
		$this->setRandomGenerator($this->generator);
		if ($this->precision > 0) {
						$this->setPrecision($this->precision);
		}
	}


	function add($y)
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				$temp = new Math_BigInteger();
				$temp->value = gmp_add($this->value, $y->value);

				return $this->_normalize($temp);
			case MATH_BIGINTEGER_MODE_BCMATH:
				$temp = new Math_BigInteger();
				$temp->value = bcadd($this->value, $y->value, 0);

				return $this->_normalize($temp);
		}

		$temp = $this->_add($this->value, $this->is_negative, $y->value, $y->is_negative);

		$result = new Math_BigInteger();
		$result->value = $temp[MATH_BIGINTEGER_VALUE];
		$result->is_negative = $temp[MATH_BIGINTEGER_SIGN];

		return $this->_normalize($result);
	}


	function _add($x_value, $x_negative, $y_value, $y_negative)
	{
		$x_size = count($x_value);
		$y_size = count($y_value);

		if ($x_size == 0) {
			return array(
				MATH_BIGINTEGER_VALUE => $y_value,
				MATH_BIGINTEGER_SIGN => $y_negative
			);
		} else if ($y_size == 0) {
			return array(
				MATH_BIGINTEGER_VALUE => $x_value,
				MATH_BIGINTEGER_SIGN => $x_negative
			);
		}

				if ( $x_negative != $y_negative ) {
			if ( $x_value == $y_value ) {
				return array(
					MATH_BIGINTEGER_VALUE => array(),
					MATH_BIGINTEGER_SIGN => false
				);
			}

			$temp = $this->_subtract($x_value, false, $y_value, false);
			$temp[MATH_BIGINTEGER_SIGN] = $this->_compare($x_value, false, $y_value, false) > 0 ? $x_negative : $y_negative;

			return $temp;
		}

		if ($x_size < $y_size) {
			$size = $x_size;
			$value = $y_value;
		} else {
			$size = $y_size;
			$value = $x_value;
		}

		$value[] = 0;
		$carry = 0;
		for ($i = 0, $j = 1; $j < $size; $i+=2, $j+=2) {
			$sum = $x_value[$j] * 0x4000000 + $x_value[$i] + $y_value[$j] * 0x4000000 + $y_value[$i] + $carry;
			$carry = $sum >= MATH_BIGINTEGER_MAX_DIGIT52;
			$sum = $carry ? $sum - MATH_BIGINTEGER_MAX_DIGIT52 : $sum;

			$temp = (int) ($sum / 0x4000000);

			$value[$i] = (int) ($sum - 0x4000000 * $temp);
			$value[$j] = $temp;
		}

		if ($j == $size) {
			$sum = $x_value[$i] + $y_value[$i] + $carry;
			$carry = $sum >= 0x4000000;
			$value[$i] = $carry ? $sum - 0x4000000 : $sum;
			++$i;
		}

		if ($carry) {
			for (; $value[$i] == 0x3FFFFFF; ++$i) {
				$value[$i] = 0;
			}
			++$value[$i];
		}

		return array(
			MATH_BIGINTEGER_VALUE => $this->_trim($value),
			MATH_BIGINTEGER_SIGN => $x_negative
		);
	}


	function subtract($y)
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				$temp = new Math_BigInteger();
				$temp->value = gmp_sub($this->value, $y->value);

				return $this->_normalize($temp);
			case MATH_BIGINTEGER_MODE_BCMATH:
				$temp = new Math_BigInteger();
				$temp->value = bcsub($this->value, $y->value, 0);

				return $this->_normalize($temp);
		}

		$temp = $this->_subtract($this->value, $this->is_negative, $y->value, $y->is_negative);

		$result = new Math_BigInteger();
		$result->value = $temp[MATH_BIGINTEGER_VALUE];
		$result->is_negative = $temp[MATH_BIGINTEGER_SIGN];

		return $this->_normalize($result);
	}


	function _subtract($x_value, $x_negative, $y_value, $y_negative)
	{
		$x_size = count($x_value);
		$y_size = count($y_value);

		if ($x_size == 0) {
			return array(
				MATH_BIGINTEGER_VALUE => $y_value,
				MATH_BIGINTEGER_SIGN => !$y_negative
			);
		} else if ($y_size == 0) {
			return array(
				MATH_BIGINTEGER_VALUE => $x_value,
				MATH_BIGINTEGER_SIGN => $x_negative
			);
		}

				if ( $x_negative != $y_negative ) {
			$temp = $this->_add($x_value, false, $y_value, false);
			$temp[MATH_BIGINTEGER_SIGN] = $x_negative;

			return $temp;
		}

		$diff = $this->_compare($x_value, $x_negative, $y_value, $y_negative);

		if ( !$diff ) {
			return array(
				MATH_BIGINTEGER_VALUE => array(),
				MATH_BIGINTEGER_SIGN => false
			);
		}

				if ( (!$x_negative && $diff < 0) || ($x_negative && $diff > 0) ) {
			$temp = $x_value;
			$x_value = $y_value;
			$y_value = $temp;

			$x_negative = !$x_negative;

			$x_size = count($x_value);
			$y_size = count($y_value);
		}


		$carry = 0;
		for ($i = 0, $j = 1; $j < $y_size; $i+=2, $j+=2) {
			$sum = $x_value[$j] * 0x4000000 + $x_value[$i] - $y_value[$j] * 0x4000000 - $y_value[$i] - $carry;
			$carry = $sum < 0;
			$sum = $carry ? $sum + MATH_BIGINTEGER_MAX_DIGIT52 : $sum;

			$temp = (int) ($sum / 0x4000000);

			$x_value[$i] = (int) ($sum - 0x4000000 * $temp);
			$x_value[$j] = $temp;
		}

		if ($j == $y_size) {
			$sum = $x_value[$i] - $y_value[$i] - $carry;
			$carry = $sum < 0;
			$x_value[$i] = $carry ? $sum + 0x4000000 : $sum;
			++$i;
		}

		if ($carry) {
			for (; !$x_value[$i]; ++$i) {
				$x_value[$i] = 0x3FFFFFF;
			}
			--$x_value[$i];
		}

		return array(
			MATH_BIGINTEGER_VALUE => $this->_trim($x_value),
			MATH_BIGINTEGER_SIGN => $x_negative
		);
	}


	function multiply($x)
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				$temp = new Math_BigInteger();
				$temp->value = gmp_mul($this->value, $x->value);

				return $this->_normalize($temp);
			case MATH_BIGINTEGER_MODE_BCMATH:
				$temp = new Math_BigInteger();
				$temp->value = bcmul($this->value, $x->value, 0);

				return $this->_normalize($temp);
		}

		$temp = $this->_multiply($this->value, $this->is_negative, $x->value, $x->is_negative);

		$product = new Math_BigInteger();
		$product->value = $temp[MATH_BIGINTEGER_VALUE];
		$product->is_negative = $temp[MATH_BIGINTEGER_SIGN];

		return $this->_normalize($product);
	}


	function _multiply($x_value, $x_negative, $y_value, $y_negative)
	{

		$x_length = count($x_value);
		$y_length = count($y_value);

		if ( !$x_length || !$y_length ) {
			return array(
				MATH_BIGINTEGER_VALUE => array(),
				MATH_BIGINTEGER_SIGN => false
			);
		}

		return array(
			MATH_BIGINTEGER_VALUE => min($x_length, $y_length) < 2 * MATH_BIGINTEGER_KARATSUBA_CUTOFF ?
				$this->_trim($this->_regularMultiply($x_value, $y_value)) :
				$this->_trim($this->_karatsuba($x_value, $y_value)),
			MATH_BIGINTEGER_SIGN => $x_negative != $y_negative
		);
	}


	function _regularMultiply($x_value, $y_value)
	{
		$x_length = count($x_value);
		$y_length = count($y_value);

		if ( !$x_length || !$y_length ) {
			return array();
		}

		if ( $x_length < $y_length ) {
			$temp = $x_value;
			$x_value = $y_value;
			$y_value = $temp;

			$x_length = count($x_value);
			$y_length = count($y_value);
		}

		$product_value = $this->_array_repeat(0, $x_length + $y_length);


		$carry = 0;

		for ($j = 0; $j < $x_length; ++$j) {
			$temp = $x_value[$j] * $y_value[0] + $carry;
			$carry = (int) ($temp / 0x4000000);
			$product_value[$j] = (int) ($temp - 0x4000000 * $carry);
		}

		$product_value[$j] = $carry;

						for ($i = 1; $i < $y_length; ++$i) {
			$carry = 0;

			for ($j = 0, $k = $i; $j < $x_length; ++$j, ++$k) {
				$temp = $product_value[$k] + $x_value[$j] * $y_value[$i] + $carry;
				$carry = (int) ($temp / 0x4000000);
				$product_value[$k] = (int) ($temp - 0x4000000 * $carry);
			}

			$product_value[$k] = $carry;
		}

		return $product_value;
	}


	function _karatsuba($x_value, $y_value)
	{
		$m = min(count($x_value) >> 1, count($y_value) >> 1);

		if ($m < MATH_BIGINTEGER_KARATSUBA_CUTOFF) {
			return $this->_regularMultiply($x_value, $y_value);
		}

		$x1 = array_slice($x_value, $m);
		$x0 = array_slice($x_value, 0, $m);
		$y1 = array_slice($y_value, $m);
		$y0 = array_slice($y_value, 0, $m);

		$z2 = $this->_karatsuba($x1, $y1);
		$z0 = $this->_karatsuba($x0, $y0);

		$z1 = $this->_add($x1, false, $x0, false);
		$temp = $this->_add($y1, false, $y0, false);
		$z1 = $this->_karatsuba($z1[MATH_BIGINTEGER_VALUE], $temp[MATH_BIGINTEGER_VALUE]);
		$temp = $this->_add($z2, false, $z0, false);
		$z1 = $this->_subtract($z1, false, $temp[MATH_BIGINTEGER_VALUE], false);

		$z2 = array_merge(array_fill(0, 2 * $m, 0), $z2);
		$z1[MATH_BIGINTEGER_VALUE] = array_merge(array_fill(0, $m, 0), $z1[MATH_BIGINTEGER_VALUE]);

		$xy = $this->_add($z2, false, $z1[MATH_BIGINTEGER_VALUE], $z1[MATH_BIGINTEGER_SIGN]);
		$xy = $this->_add($xy[MATH_BIGINTEGER_VALUE], $xy[MATH_BIGINTEGER_SIGN], $z0, false);

		return $xy[MATH_BIGINTEGER_VALUE];
	}


	function _square($x = false)
	{
		return count($x) < 2 * MATH_BIGINTEGER_KARATSUBA_CUTOFF ?
			$this->_trim($this->_baseSquare($x)) :
			$this->_trim($this->_karatsubaSquare($x));
	}


	function _baseSquare($value)
	{
		if ( empty($value) ) {
			return array();
		}
		$square_value = $this->_array_repeat(0, 2 * count($value));

		for ($i = 0, $max_index = count($value) - 1; $i <= $max_index; ++$i) {
			$i2 = $i << 1;

			$temp = $square_value[$i2] + $value[$i] * $value[$i];
			$carry = (int) ($temp / 0x4000000);
			$square_value[$i2] = (int) ($temp - 0x4000000 * $carry);

						for ($j = $i + 1, $k = $i2 + 1; $j <= $max_index; ++$j, ++$k) {
				$temp = $square_value[$k] + 2 * $value[$j] * $value[$i] + $carry;
				$carry = (int) ($temp / 0x4000000);
				$square_value[$k] = (int) ($temp - 0x4000000 * $carry);
			}

									$square_value[$i + $max_index + 1] = $carry;
		}

		return $square_value;
	}


	function _karatsubaSquare($value)
	{
		$m = count($value) >> 1;

		if ($m < MATH_BIGINTEGER_KARATSUBA_CUTOFF) {
			return $this->_baseSquare($value);
		}

		$x1 = array_slice($value, $m);
		$x0 = array_slice($value, 0, $m);

		$z2 = $this->_karatsubaSquare($x1);
		$z0 = $this->_karatsubaSquare($x0);

		$z1 = $this->_add($x1, false, $x0, false);
		$z1 = $this->_karatsubaSquare($z1[MATH_BIGINTEGER_VALUE]);
		$temp = $this->_add($z2, false, $z0, false);
		$z1 = $this->_subtract($z1, false, $temp[MATH_BIGINTEGER_VALUE], false);

		$z2 = array_merge(array_fill(0, 2 * $m, 0), $z2);
		$z1[MATH_BIGINTEGER_VALUE] = array_merge(array_fill(0, $m, 0), $z1[MATH_BIGINTEGER_VALUE]);

		$xx = $this->_add($z2, false, $z1[MATH_BIGINTEGER_VALUE], $z1[MATH_BIGINTEGER_SIGN]);
		$xx = $this->_add($xx[MATH_BIGINTEGER_VALUE], $xx[MATH_BIGINTEGER_SIGN], $z0, false);

		return $xx[MATH_BIGINTEGER_VALUE];
	}


	function divide($y)
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				$quotient = new Math_BigInteger();
				$remainder = new Math_BigInteger();

				list($quotient->value, $remainder->value) = gmp_div_qr($this->value, $y->value);

				if (gmp_sign($remainder->value) < 0) {
					$remainder->value = gmp_add($remainder->value, gmp_abs($y->value));
				}

				return array($this->_normalize($quotient), $this->_normalize($remainder));
			case MATH_BIGINTEGER_MODE_BCMATH:
				$quotient = new Math_BigInteger();
				$remainder = new Math_BigInteger();

				$quotient->value = bcdiv($this->value, $y->value, 0);
				$remainder->value = bcmod($this->value, $y->value);

				if ($remainder->value[0] == '-') {
					$remainder->value = bcadd($remainder->value, $y->value[0] == '-' ? substr($y->value, 1) : $y->value, 0);
				}

				return array($this->_normalize($quotient), $this->_normalize($remainder));
		}

		if (count($y->value) == 1) {
			list($q, $r) = $this->_divide_digit($this->value, $y->value[0]);
			$quotient = new Math_BigInteger();
			$remainder = new Math_BigInteger();
			$quotient->value = $q;
			$remainder->value = array($r);
			$quotient->is_negative = $this->is_negative != $y->is_negative;
			return array($this->_normalize($quotient), $this->_normalize($remainder));
		}

		static $zero;
		if ( !isset($zero) ) {
			$zero = new Math_BigInteger();
		}

		$x = $this->copy();
		$y = $y->copy();

		$x_sign = $x->is_negative;
		$y_sign = $y->is_negative;

		$x->is_negative = $y->is_negative = false;

		$diff = $x->compare($y);

		if ( !$diff ) {
			$temp = new Math_BigInteger();
			$temp->value = array(1);
			$temp->is_negative = $x_sign != $y_sign;
			return array($this->_normalize($temp), $this->_normalize(new Math_BigInteger()));
		}

		if ( $diff < 0 ) {
						if ( $x_sign ) {
				$x = $y->subtract($x);
			}
			return array($this->_normalize(new Math_BigInteger()), $this->_normalize($x));
		}

				$msb = $y->value[count($y->value) - 1];
		for ($shift = 0; !($msb & 0x2000000); ++$shift) {
			$msb <<= 1;
		}
		$x->_lshift($shift);
		$y->_lshift($shift);
		$y_value = &$y->value;

		$x_max = count($x->value) - 1;
		$y_max = count($y->value) - 1;

		$quotient = new Math_BigInteger();
		$quotient_value = &$quotient->value;
		$quotient_value = $this->_array_repeat(0, $x_max - $y_max + 1);

		static $temp, $lhs, $rhs;
		if (!isset($temp)) {
			$temp = new Math_BigInteger();
			$lhs =  new Math_BigInteger();
			$rhs =  new Math_BigInteger();
		}
		$temp_value = &$temp->value;
		$rhs_value =  &$rhs->value;

				$temp_value = array_merge($this->_array_repeat(0, $x_max - $y_max), $y_value);

		while ( $x->compare($temp) >= 0 ) {
						++$quotient_value[$x_max - $y_max];
			$x = $x->subtract($temp);
			$x_max = count($x->value) - 1;
		}

		for ($i = $x_max; $i >= $y_max + 1; --$i) {
			$x_value = &$x->value;
			$x_window = array(
				isset($x_value[$i]) ? $x_value[$i] : 0,
				isset($x_value[$i - 1]) ? $x_value[$i - 1] : 0,
				isset($x_value[$i - 2]) ? $x_value[$i - 2] : 0
			);
			$y_window = array(
				$y_value[$y_max],
				( $y_max > 0 ) ? $y_value[$y_max - 1] : 0
			);

			$q_index = $i - $y_max - 1;
			if ($x_window[0] == $y_window[0]) {
				$quotient_value[$q_index] = 0x3FFFFFF;
			} else {
				$quotient_value[$q_index] = (int) (
					($x_window[0] * 0x4000000 + $x_window[1])
					/
					$y_window[0]
				);
			}

			$temp_value = array($y_window[1], $y_window[0]);

			$lhs->value = array($quotient_value[$q_index]);
			$lhs = $lhs->multiply($temp);

			$rhs_value = array($x_window[2], $x_window[1], $x_window[0]);

			while ( $lhs->compare($rhs) > 0 ) {
				--$quotient_value[$q_index];

				$lhs->value = array($quotient_value[$q_index]);
				$lhs = $lhs->multiply($temp);
			}

			$adjust = $this->_array_repeat(0, $q_index);
			$temp_value = array($quotient_value[$q_index]);
			$temp = $temp->multiply($y);
			$temp_value = &$temp->value;
			$temp_value = array_merge($adjust, $temp_value);

			$x = $x->subtract($temp);

			if ($x->compare($zero) < 0) {
				$temp_value = array_merge($adjust, $y_value);
				$x = $x->add($temp);

				--$quotient_value[$q_index];
			}

			$x_max = count($x_value) - 1;
		}

				$x->_rshift($shift);

		$quotient->is_negative = $x_sign != $y_sign;

				if ( $x_sign ) {
			$y->_rshift($shift);
			$x = $y->subtract($x);
		}

		return array($this->_normalize($quotient), $this->_normalize($x));
	}


	function _divide_digit($dividend, $divisor)
	{
		$carry = 0;
		$result = array();

		for ($i = count($dividend) - 1; $i >= 0; --$i) {
			$temp = 0x4000000 * $carry + $dividend[$i];
			$result[$i] = (int) ($temp / $divisor);
			$carry = (int) ($temp - $divisor * $result[$i]);
		}

		return array($result, $carry);
	}


	function modPow($e, $n)
	{
		$n = $this->bitmask !== false && $this->bitmask->compare($n) < 0 ? $this->bitmask : $n->abs();

		if ($e->compare(new Math_BigInteger()) < 0) {
			$e = $e->abs();

			$temp = $this->modInverse($n);
			if ($temp === false) {
				return false;
			}

			return $this->_normalize($temp->modPow($e, $n));
		}

		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				$temp = new Math_BigInteger();
				$temp->value = gmp_powm($this->value, $e->value, $n->value);

				return $this->_normalize($temp);
			case MATH_BIGINTEGER_MODE_BCMATH:
				$temp = new Math_BigInteger();
				$temp->value = bcpowmod($this->value, $e->value, $n->value, 0);

				return $this->_normalize($temp);
		}

		if ( empty($e->value) ) {
			$temp = new Math_BigInteger();
			$temp->value = array(1);
			return $this->_normalize($temp);
		}

		if ( $e->value == array(1) ) {
			list(, $temp) = $this->divide($n);
			return $this->_normalize($temp);
		}

		if ( $e->value == array(2) ) {
			$temp = new Math_BigInteger();
			$temp->value = $this->_square($this->value);
			list(, $temp) = $temp->divide($n);
			return $this->_normalize($temp);
		}

		return $this->_normalize($this->_slidingWindow($e, $n, MATH_BIGINTEGER_BARRETT));

				if ( $n->value[0] & 1 ) {
			return $this->_normalize($this->_slidingWindow($e, $n, MATH_BIGINTEGER_MONTGOMERY));
		}

				for ($i = 0; $i < count($n->value); ++$i) {
			if ( $n->value[$i] ) {
				$temp = decbin($n->value[$i]);
				$j = strlen($temp) - strrpos($temp, '1') - 1;
				$j+= 26 * $i;
				break;
			}
		}

		$mod1 = $n->copy();
		$mod1->_rshift($j);
		$mod2 = new Math_BigInteger();
		$mod2->value = array(1);
		$mod2->_lshift($j);

		$part1 = ( $mod1->value != array(1) ) ? $this->_slidingWindow($e, $mod1, MATH_BIGINTEGER_MONTGOMERY) : new Math_BigInteger();
		$part2 = $this->_slidingWindow($e, $mod2, MATH_BIGINTEGER_POWEROF2);

		$y1 = $mod2->modInverse($mod1);
		$y2 = $mod1->modInverse($mod2);

		$result = $part1->multiply($mod2);
		$result = $result->multiply($y1);

		$temp = $part2->multiply($mod1);
		$temp = $temp->multiply($y2);

		$result = $result->add($temp);
		list(, $result) = $result->divide($n);

		return $this->_normalize($result);
	}


	function powMod($e, $n)
	{
		return $this->modPow($e, $n);
	}


	function _slidingWindow($e, $n, $mode)
	{
		static $window_ranges = array(7, 25, 81, 241, 673, 1793);
		$e_value = $e->value;
		$e_length = count($e_value) - 1;
		$e_bits = decbin($e_value[$e_length]);
		for ($i = $e_length - 1; $i >= 0; --$i) {
			$e_bits.= str_pad(decbin($e_value[$i]), 26, '0', STR_PAD_LEFT);
		}

		$e_length = strlen($e_bits);

						for ($i = 0, $window_size = 1; $e_length > $window_ranges[$i] && $i < count($window_ranges); ++$window_size, ++$i);

		$n_value = $n->value;

				$powers = array();
		$powers[1] = $this->_prepareReduce($this->value, $n_value, $mode);
		$powers[2] = $this->_squareReduce($powers[1], $n_value, $mode);

						$temp = 1 << ($window_size - 1);
		for ($i = 1; $i < $temp; ++$i) {
			$i2 = $i << 1;
			$powers[$i2 + 1] = $this->_multiplyReduce($powers[$i2 - 1], $powers[2], $n_value, $mode);
		}

		$result = array(1);
		$result = $this->_prepareReduce($result, $n_value, $mode);

		for ($i = 0; $i < $e_length; ) {
			if ( !$e_bits[$i] ) {
				$result = $this->_squareReduce($result, $n_value, $mode);
				++$i;
			} else {
				for ($j = $window_size - 1; $j > 0; --$j) {
					if ( !empty($e_bits[$i + $j]) ) {
						break;
					}
				}

				for ($k = 0; $k <= $j; ++$k) {
					$result = $this->_squareReduce($result, $n_value, $mode);
				}

				$result = $this->_multiplyReduce($result, $powers[bindec(substr($e_bits, $i, $j + 1))], $n_value, $mode);

				$i+=$j + 1;
			}
		}

		$temp = new Math_BigInteger();
		$temp->value = $this->_reduce($result, $n_value, $mode);

		return $temp;
	}


	function _reduce($x, $n, $mode)
	{
		switch ($mode) {
			case MATH_BIGINTEGER_MONTGOMERY:
				return $this->_montgomery($x, $n);
			case MATH_BIGINTEGER_BARRETT:
				return $this->_barrett($x, $n);
			case MATH_BIGINTEGER_POWEROF2:
				$lhs = new Math_BigInteger();
				$lhs->value = $x;
				$rhs = new Math_BigInteger();
				$rhs->value = $n;
				return $x->_mod2($n);
			case MATH_BIGINTEGER_CLASSIC:
				$lhs = new Math_BigInteger();
				$lhs->value = $x;
				$rhs = new Math_BigInteger();
				$rhs->value = $n;
				list(, $temp) = $lhs->divide($rhs);
				return $temp->value;
			case MATH_BIGINTEGER_NONE:
				return $x;
			default:
						}
	}


	function _prepareReduce($x, $n, $mode)
	{
		if ($mode == MATH_BIGINTEGER_MONTGOMERY) {
			return $this->_prepMontgomery($x, $n);
		}
		return $this->_reduce($x, $n, $mode);
	}


	function _multiplyReduce($x, $y, $n, $mode)
	{
		if ($mode == MATH_BIGINTEGER_MONTGOMERY) {
			return $this->_montgomeryMultiply($x, $y, $n);
		}
		$temp = $this->_multiply($x, false, $y, false);
		return $this->_reduce($temp[MATH_BIGINTEGER_VALUE], $n, $mode);
	}


	function _squareReduce($x, $n, $mode)
	{
		if ($mode == MATH_BIGINTEGER_MONTGOMERY) {
			return $this->_montgomeryMultiply($x, $x, $n);
		}
		return $this->_reduce($this->_square($x), $n, $mode);
	}


	function _mod2($n)
	{
		$temp = new Math_BigInteger();
		$temp->value = array(1);
		return $this->bitwise_and($n->subtract($temp));
	}


	function _barrett($n, $m)
	{
		static $cache = array(
			MATH_BIGINTEGER_VARIABLE => array(),
			MATH_BIGINTEGER_DATA => array()
		);

		$m_length = count($m);

				if (count($n) > 2 * $m_length) {
			$lhs = new Math_BigInteger();
			$rhs = new Math_BigInteger();
			$lhs->value = $n;
			$rhs->value = $m;
			list(, $temp) = $lhs->divide($rhs);
			return $temp->value;
		}

				if ($m_length < 5) {
			return $this->_regularBarrett($n, $m);
		}


		if ( ($key = array_search($m, $cache[MATH_BIGINTEGER_VARIABLE])) === false ) {
			$key = count($cache[MATH_BIGINTEGER_VARIABLE]);
			$cache[MATH_BIGINTEGER_VARIABLE][] = $m;

			$lhs = new Math_BigInteger();
			$lhs_value = &$lhs->value;
			$lhs_value = $this->_array_repeat(0, $m_length + ($m_length >> 1));
			$lhs_value[] = 1;
			$rhs = new Math_BigInteger();
			$rhs->value = $m;

			list($u, $m1) = $lhs->divide($rhs);
			$u = $u->value;
			$m1 = $m1->value;

			$cache[MATH_BIGINTEGER_DATA][] = array(
				'u' => $u,
				'm1'=> $m1
			);
		} else {
			extract($cache[MATH_BIGINTEGER_DATA][$key]);
		}

		$cutoff = $m_length + ($m_length >> 1);
		$lsd = array_slice($n, 0, $cutoff);
		$msd = array_slice($n, $cutoff);
		$lsd = $this->_trim($lsd);
		$temp = $this->_multiply($msd, false, $m1, false);
		$n = $this->_add($lsd, false, $temp[MATH_BIGINTEGER_VALUE], false);
		if ($m_length & 1) {
			return $this->_regularBarrett($n[MATH_BIGINTEGER_VALUE], $m);
		}

				$temp = array_slice($n[MATH_BIGINTEGER_VALUE], $m_length - 1);
						$temp = $this->_multiply($temp, false, $u, false);
						$temp = array_slice($temp[MATH_BIGINTEGER_VALUE], ($m_length >> 1) + 1);
						$temp = $this->_multiply($temp, false, $m, false);


		$result = $this->_subtract($n[MATH_BIGINTEGER_VALUE], false, $temp[MATH_BIGINTEGER_VALUE], false);

		while ($this->_compare($result[MATH_BIGINTEGER_VALUE], $result[MATH_BIGINTEGER_SIGN], $m, false) >= 0) {
			$result = $this->_subtract($result[MATH_BIGINTEGER_VALUE], $result[MATH_BIGINTEGER_SIGN], $m, false);
		}

		return $result[MATH_BIGINTEGER_VALUE];
	}


	function _regularBarrett($x, $n)
	{
		static $cache = array(
			MATH_BIGINTEGER_VARIABLE => array(),
			MATH_BIGINTEGER_DATA => array()
		);

		$n_length = count($n);

		if (count($x) > 2 * $n_length) {
			$lhs = new Math_BigInteger();
			$rhs = new Math_BigInteger();
			$lhs->value = $x;
			$rhs->value = $n;
			list(, $temp) = $lhs->divide($rhs);
			return $temp->value;
		}

		if ( ($key = array_search($n, $cache[MATH_BIGINTEGER_VARIABLE])) === false ) {
			$key = count($cache[MATH_BIGINTEGER_VARIABLE]);
			$cache[MATH_BIGINTEGER_VARIABLE][] = $n;
			$lhs = new Math_BigInteger();
			$lhs_value = &$lhs->value;
			$lhs_value = $this->_array_repeat(0, 2 * $n_length);
			$lhs_value[] = 1;
			$rhs = new Math_BigInteger();
			$rhs->value = $n;
			list($temp, ) = $lhs->divide($rhs);
			$cache[MATH_BIGINTEGER_DATA][] = $temp->value;
		}

				$temp = array_slice($x, $n_length - 1);
				$temp = $this->_multiply($temp, false, $cache[MATH_BIGINTEGER_DATA][$key], false);
				$temp = array_slice($temp[MATH_BIGINTEGER_VALUE], $n_length + 1);

				$result = array_slice($x, 0, $n_length + 1);
				$temp = $this->_multiplyLower($temp, false, $n, false, $n_length + 1);

		if ($this->_compare($result, false, $temp[MATH_BIGINTEGER_VALUE], $temp[MATH_BIGINTEGER_SIGN]) < 0) {
			$corrector_value = $this->_array_repeat(0, $n_length + 1);
			$corrector_value[] = 1;
			$result = $this->_add($result, false, $corrector, false);
			$result = $result[MATH_BIGINTEGER_VALUE];
		}

				$result = $this->_subtract($result, false, $temp[MATH_BIGINTEGER_VALUE], $temp[MATH_BIGINTEGER_SIGN]);
		while ($this->_compare($result[MATH_BIGINTEGER_VALUE], $result[MATH_BIGINTEGER_SIGN], $n, false) > 0) {
			$result = $this->_subtract($result[MATH_BIGINTEGER_VALUE], $result[MATH_BIGINTEGER_SIGN], $n, false);
		}

		return $result[MATH_BIGINTEGER_VALUE];
	}


	function _multiplyLower($x_value, $x_negative, $y_value, $y_negative, $stop)
	{
		$x_length = count($x_value);
		$y_length = count($y_value);

		if ( !$x_length || !$y_length ) {
			return array(
				MATH_BIGINTEGER_VALUE => array(),
				MATH_BIGINTEGER_SIGN => false
			);
		}

		if ( $x_length < $y_length ) {
			$temp = $x_value;
			$x_value = $y_value;
			$y_value = $temp;

			$x_length = count($x_value);
			$y_length = count($y_value);
		}

		$product_value = $this->_array_repeat(0, $x_length + $y_length);


		$carry = 0;

		for ($j = 0; $j < $x_length; ++$j) {
			$temp = $x_value[$j] * $y_value[0] + $carry;
			$carry = (int) ($temp / 0x4000000);
			$product_value[$j] = (int) ($temp - 0x4000000 * $carry);
		}

		if ($j < $stop) {
			$product_value[$j] = $carry;
		}


		for ($i = 1; $i < $y_length; ++$i) {
			$carry = 0;

			for ($j = 0, $k = $i; $j < $x_length && $k < $stop; ++$j, ++$k) {
				$temp = $product_value[$k] + $x_value[$j] * $y_value[$i] + $carry;
				$carry = (int) ($temp / 0x4000000);
				$product_value[$k] = (int) ($temp - 0x4000000 * $carry);
			}

			if ($k < $stop) {
				$product_value[$k] = $carry;
			}
		}

		return array(
			MATH_BIGINTEGER_VALUE => $this->_trim($product_value),
			MATH_BIGINTEGER_SIGN => $x_negative != $y_negative
		);
	}


	function _montgomery($x, $n)
	{
		static $cache = array(
			MATH_BIGINTEGER_VARIABLE => array(),
			MATH_BIGINTEGER_DATA => array()
		);

		if ( ($key = array_search($n, $cache[MATH_BIGINTEGER_VARIABLE])) === false ) {
			$key = count($cache[MATH_BIGINTEGER_VARIABLE]);
			$cache[MATH_BIGINTEGER_VARIABLE][] = $x;
			$cache[MATH_BIGINTEGER_DATA][] = $this->_modInverse67108864($n);
		}

		$k = count($n);

		$result = array(MATH_BIGINTEGER_VALUE => $x);

		for ($i = 0; $i < $k; ++$i) {
			$temp = $result[MATH_BIGINTEGER_VALUE][$i] * $cache[MATH_BIGINTEGER_DATA][$key];
			$temp = (int) ($temp - 0x4000000 * ((int) ($temp / 0x4000000)));
			$temp = $this->_regularMultiply(array($temp), $n);
			$temp = array_merge($this->_array_repeat(0, $i), $temp);
			$result = $this->_add($result[MATH_BIGINTEGER_VALUE], false, $temp, false);
		}

		$result[MATH_BIGINTEGER_VALUE] = array_slice($result[MATH_BIGINTEGER_VALUE], $k);

		if ($this->_compare($result, false, $n, false) >= 0) {
			$result = $this->_subtract($result[MATH_BIGINTEGER_VALUE], false, $n, false);
		}

		return $result[MATH_BIGINTEGER_VALUE];
	}


	function _montgomeryMultiply($x, $y, $m)
	{
		$temp = $this->_multiply($x, false, $y, false);
		return $this->_montgomery($temp[MATH_BIGINTEGER_VALUE], $m);

		static $cache = array(
			MATH_BIGINTEGER_VARIABLE => array(),
			MATH_BIGINTEGER_DATA => array()
		);

		if ( ($key = array_search($m, $cache[MATH_BIGINTEGER_VARIABLE])) === false ) {
			$key = count($cache[MATH_BIGINTEGER_VARIABLE]);
			$cache[MATH_BIGINTEGER_VARIABLE][] = $m;
			$cache[MATH_BIGINTEGER_DATA][] = $this->_modInverse67108864($m);
		}

		$n = max(count($x), count($y), count($m));
		$x = array_pad($x, $n, 0);
		$y = array_pad($y, $n, 0);
		$m = array_pad($m, $n, 0);
		$a = array(MATH_BIGINTEGER_VALUE => $this->_array_repeat(0, $n + 1));
		for ($i = 0; $i < $n; ++$i) {
			$temp = $a[MATH_BIGINTEGER_VALUE][0] + $x[$i] * $y[0];
			$temp = (int) ($temp - 0x4000000 * ((int) ($temp / 0x4000000)));
			$temp = $temp * $cache[MATH_BIGINTEGER_DATA][$key];
			$temp = (int) ($temp - 0x4000000 * ((int) ($temp / 0x4000000)));
			$temp = $this->_add($this->_regularMultiply(array($x[$i]), $y), false, $this->_regularMultiply(array($temp), $m), false);
			$a = $this->_add($a[MATH_BIGINTEGER_VALUE], false, $temp[MATH_BIGINTEGER_VALUE], false);
			$a[MATH_BIGINTEGER_VALUE] = array_slice($a[MATH_BIGINTEGER_VALUE], 1);
		}
		if ($this->_compare($a[MATH_BIGINTEGER_VALUE], false, $m, false) >= 0) {
			$a = $this->_subtract($a[MATH_BIGINTEGER_VALUE], false, $m, false);
		}
		return $a[MATH_BIGINTEGER_VALUE];
	}


	function _prepMontgomery($x, $n)
	{
		$lhs = new Math_BigInteger();
		$lhs->value = array_merge($this->_array_repeat(0, count($n)), $x);
		$rhs = new Math_BigInteger();
		$rhs->value = $n;

		list(, $temp) = $lhs->divide($rhs);
		return $temp->value;
	}


	function _modInverse67108864($x)
	{
		$x = -$x[0];
		$result = $x & 0x3;
		$result = ($result * (2 - $x * $result)) & 0xF;
		$result = ($result * (2 - ($x & 0xFF) * $result)) & 0xFF;
		$result = ($result * ((2 - ($x & 0xFFFF) * $result) & 0xFFFF)) & 0xFFFF;
		$result = fmod($result * (2 - fmod($x * $result, 0x4000000)), 0x4000000);
		return $result & 0x3FFFFFF;
	}


	function modInverse($n)
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				$temp = new Math_BigInteger();
				$temp->value = gmp_invert($this->value, $n->value);

				return ( $temp->value === false ) ? false : $this->_normalize($temp);
		}

		static $zero, $one;
		if (!isset($zero)) {
			$zero = new Math_BigInteger();
			$one = new Math_BigInteger(1);
		}

				$n = $n->abs();

		if ($this->compare($zero) < 0) {
			$temp = $this->abs();
			$temp = $temp->modInverse($n);
			return $negated === false ? false : $this->_normalize($n->subtract($temp));
		}

		extract($this->extendedGCD($n));

		if (!$gcd->equals($one)) {
			return false;
		}

		$x = $x->compare($zero) < 0 ? $x->add($n) : $x;

		return $this->compare($zero) < 0 ? $this->_normalize($n->subtract($x)) : $this->_normalize($x);
	}


	function extendedGCD($n)
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				extract(gmp_gcdext($this->value, $n->value));

				return array(
					'gcd' => $this->_normalize(new Math_BigInteger($g)),
					'x'   => $this->_normalize(new Math_BigInteger($s)),
					'y'   => $this->_normalize(new Math_BigInteger($t))
				);
			case MATH_BIGINTEGER_MODE_BCMATH:

				$u = $this->value;
				$v = $n->value;

				$a = '1';
				$b = '0';
				$c = '0';
				$d = '1';

				while (bccomp($v, '0', 0) != 0) {
					$q = bcdiv($u, $v, 0);

					$temp = $u;
					$u = $v;
					$v = bcsub($temp, bcmul($v, $q, 0), 0);

					$temp = $a;
					$a = $c;
					$c = bcsub($temp, bcmul($a, $q, 0), 0);

					$temp = $b;
					$b = $d;
					$d = bcsub($temp, bcmul($b, $q, 0), 0);
				}

				return array(
					'gcd' => $this->_normalize(new Math_BigInteger($u)),
					'x'   => $this->_normalize(new Math_BigInteger($a)),
					'y'   => $this->_normalize(new Math_BigInteger($b))
				);
		}

		$y = $n->copy();
		$x = $this->copy();
		$g = new Math_BigInteger();
		$g->value = array(1);

		while ( !(($x->value[0] & 1)|| ($y->value[0] & 1)) ) {
			$x->_rshift(1);
			$y->_rshift(1);
			$g->_lshift(1);
		}

		$u = $x->copy();
		$v = $y->copy();

		$a = new Math_BigInteger();
		$b = new Math_BigInteger();
		$c = new Math_BigInteger();
		$d = new Math_BigInteger();

		$a->value = $d->value = $g->value = array(1);
		$b->value = $c->value = array();

		while ( !empty($u->value) ) {
			while ( !($u->value[0] & 1) ) {
				$u->_rshift(1);
				if ( (!empty($a->value) && ($a->value[0] & 1)) || (!empty($b->value) && ($b->value[0] & 1)) ) {
					$a = $a->add($y);
					$b = $b->subtract($x);
				}
				$a->_rshift(1);
				$b->_rshift(1);
			}

			while ( !($v->value[0] & 1) ) {
				$v->_rshift(1);
				if ( (!empty($d->value) && ($d->value[0] & 1)) || (!empty($c->value) && ($c->value[0] & 1)) ) {
					$c = $c->add($y);
					$d = $d->subtract($x);
				}
				$c->_rshift(1);
				$d->_rshift(1);
			}

			if ($u->compare($v) >= 0) {
				$u = $u->subtract($v);
				$a = $a->subtract($c);
				$b = $b->subtract($d);
			} else {
				$v = $v->subtract($u);
				$c = $c->subtract($a);
				$d = $d->subtract($b);
			}
		}

		return array(
			'gcd' => $this->_normalize($g->multiply($v)),
			'x'   => $this->_normalize($c),
			'y'   => $this->_normalize($d)
		);
	}


	function gcd($n)
	{
		extract($this->extendedGCD($n));
		return $gcd;
	}


	function abs()
	{
		$temp = new Math_BigInteger();

		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				$temp->value = gmp_abs($this->value);
				break;
			case MATH_BIGINTEGER_MODE_BCMATH:
				$temp->value = (bccomp($this->value, '0', 0) < 0) ? substr($this->value, 1) : $this->value;
				break;
			default:
				$temp->value = $this->value;
		}

		return $temp;
	}


	function compare($y)
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				return gmp_cmp($this->value, $y->value);
			case MATH_BIGINTEGER_MODE_BCMATH:
				return bccomp($this->value, $y->value, 0);
		}

		return $this->_compare($this->value, $this->is_negative, $y->value, $y->is_negative);
	}


	function _compare($x_value, $x_negative, $y_value, $y_negative)
	{
		if ( $x_negative != $y_negative ) {
			return ( !$x_negative && $y_negative ) ? 1 : -1;
		}

		$result = $x_negative ? -1 : 1;

		if ( count($x_value) != count($y_value) ) {
			return ( count($x_value) > count($y_value) ) ? $result : -$result;
		}
		$size = max(count($x_value), count($y_value));

		$x_value = array_pad($x_value, $size, 0);
		$y_value = array_pad($y_value, $size, 0);

		for ($i = count($x_value) - 1; $i >= 0; --$i) {
			if ($x_value[$i] != $y_value[$i]) {
				return ( $x_value[$i] > $y_value[$i] ) ? $result : -$result;
			}
		}

		return 0;
	}


	function equals($x)
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				return gmp_cmp($this->value, $x->value) == 0;
			default:
				return $this->value === $x->value && $this->is_negative == $x->is_negative;
		}
	}


	function setPrecision($bits)
	{
		$this->precision = $bits;
		if ( MATH_BIGINTEGER_MODE != MATH_BIGINTEGER_MODE_BCMATH ) {
			$this->bitmask = new Math_BigInteger(chr((1 << ($bits & 0x7)) - 1) . str_repeat(chr(0xFF), $bits >> 3), 256);
		} else {
			$this->bitmask = new Math_BigInteger(bcpow('2', $bits, 0));
		}

		$temp = $this->_normalize($this);
		$this->value = $temp->value;
	}


	function bitwise_and($x)
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				$temp = new Math_BigInteger();
				$temp->value = gmp_and($this->value, $x->value);

				return $this->_normalize($temp);
			case MATH_BIGINTEGER_MODE_BCMATH:
				$left = $this->toBytes();
				$right = $x->toBytes();

				$length = max(strlen($left), strlen($right));

				$left = str_pad($left, $length, chr(0), STR_PAD_LEFT);
				$right = str_pad($right, $length, chr(0), STR_PAD_LEFT);

				return $this->_normalize(new Math_BigInteger($left & $right, 256));
		}

		$result = $this->copy();

		$length = min(count($x->value), count($this->value));

		$result->value = array_slice($result->value, 0, $length);

		for ($i = 0; $i < $length; ++$i) {
			$result->value[$i] = $result->value[$i] & $x->value[$i];
		}

		return $this->_normalize($result);
	}


	function bitwise_or($x)
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				$temp = new Math_BigInteger();
				$temp->value = gmp_or($this->value, $x->value);

				return $this->_normalize($temp);
			case MATH_BIGINTEGER_MODE_BCMATH:
				$left = $this->toBytes();
				$right = $x->toBytes();

				$length = max(strlen($left), strlen($right));

				$left = str_pad($left, $length, chr(0), STR_PAD_LEFT);
				$right = str_pad($right, $length, chr(0), STR_PAD_LEFT);

				return $this->_normalize(new Math_BigInteger($left | $right, 256));
		}

		$length = max(count($this->value), count($x->value));
		$result = $this->copy();
		$result->value = array_pad($result->value, 0, $length);
		$x->value = array_pad($x->value, 0, $length);

		for ($i = 0; $i < $length; ++$i) {
			$result->value[$i] = $this->value[$i] | $x->value[$i];
		}

		return $this->_normalize($result);
	}


	function bitwise_xor($x)
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				$temp = new Math_BigInteger();
				$temp->value = gmp_xor($this->value, $x->value);

				return $this->_normalize($temp);
			case MATH_BIGINTEGER_MODE_BCMATH:
				$left = $this->toBytes();
				$right = $x->toBytes();

				$length = max(strlen($left), strlen($right));

				$left = str_pad($left, $length, chr(0), STR_PAD_LEFT);
				$right = str_pad($right, $length, chr(0), STR_PAD_LEFT);

				return $this->_normalize(new Math_BigInteger($left ^ $right, 256));
		}

		$length = max(count($this->value), count($x->value));
		$result = $this->copy();
		$result->value = array_pad($result->value, 0, $length);
		$x->value = array_pad($x->value, 0, $length);

		for ($i = 0; $i < $length; ++$i) {
			$result->value[$i] = $this->value[$i] ^ $x->value[$i];
		}

		return $this->_normalize($result);
	}


	function bitwise_not()
	{
						$temp = $this->toBytes();
		$pre_msb = decbin(ord($temp[0]));
		$temp = ~$temp;
		$msb = decbin(ord($temp[0]));
		if (strlen($msb) == 8) {
			$msb = substr($msb, strpos($msb, '0'));
		}
		$temp[0] = chr(bindec($msb));

				$current_bits = strlen($pre_msb) + 8 * strlen($temp) - 8;
		$new_bits = $this->precision - $current_bits;
		if ($new_bits <= 0) {
			return $this->_normalize(new Math_BigInteger($temp, 256));
		}

				$leading_ones = chr((1 << ($new_bits & 0x7)) - 1) . str_repeat(chr(0xFF), $new_bits >> 3);
		$this->_base256_lshift($leading_ones, $current_bits);

		$temp = str_pad($temp, ceil($this->bits / 8), chr(0), STR_PAD_LEFT);

		return $this->_normalize(new Math_BigInteger($leading_ones | $temp, 256));
	}


	function bitwise_rightShift($shift)
	{
		$temp = new Math_BigInteger();

		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				static $two;

				if (!isset($two)) {
					$two = gmp_init('2');
				}

				$temp->value = gmp_div_q($this->value, gmp_pow($two, $shift));

				break;
			case MATH_BIGINTEGER_MODE_BCMATH:
				$temp->value = bcdiv($this->value, bcpow('2', $shift, 0), 0);

				break;
			default:
				$temp->value = $this->value;
				$temp->_rshift($shift);
		}

		return $this->_normalize($temp);
	}


	function bitwise_leftShift($shift)
	{
		$temp = new Math_BigInteger();

		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				static $two;

				if (!isset($two)) {
					$two = gmp_init('2');
				}

				$temp->value = gmp_mul($this->value, gmp_pow($two, $shift));

				break;
			case MATH_BIGINTEGER_MODE_BCMATH:
				$temp->value = bcmul($this->value, bcpow('2', $shift, 0), 0);

				break;
			default:
				$temp->value = $this->value;
				$temp->_lshift($shift);
		}

		return $this->_normalize($temp);
	}


	function bitwise_leftRotate($shift)
	{
		$bits = $this->toBytes();

		if ($this->precision > 0) {
			$precision = $this->precision;
			if ( MATH_BIGINTEGER_MODE == MATH_BIGINTEGER_MODE_BCMATH ) {
				$mask = $this->bitmask->subtract(new Math_BigInteger(1));
				$mask = $mask->toBytes();
			} else {
				$mask = $this->bitmask->toBytes();
			}
		} else {
			$temp = ord($bits[0]);
			for ($i = 0; $temp >> $i; ++$i);
			$precision = 8 * strlen($bits) - 8 + $i;
			$mask = chr((1 << ($precision & 0x7)) - 1) . str_repeat(chr(0xFF), $precision >> 3);
		}

		if ($shift < 0) {
			$shift+= $precision;
		}
		$shift%= $precision;

		if (!$shift) {
			return $this->copy();
		}

		$left = $this->bitwise_leftShift($shift);
		$left = $left->bitwise_and(new Math_BigInteger($mask, 256));
		$right = $this->bitwise_rightShift($precision - $shift);
		$result = MATH_BIGINTEGER_MODE != MATH_BIGINTEGER_MODE_BCMATH ? $left->bitwise_or($right) : $left->add($right);
		return $this->_normalize($result);
	}


	function bitwise_rightRotate($shift)
	{
		return $this->bitwise_leftRotate(-$shift);
	}


	function setRandomGenerator($generator)
	{
		$this->generator = $generator;
	}


	function random($min = false, $max = false)
	{
		if ($min === false) {
			$min = new Math_BigInteger(0);
		}

		if ($max === false) {
			$max = new Math_BigInteger(0x7FFFFFFF);
		}

		$compare = $max->compare($min);

		if (!$compare) {
			return $this->_normalize($min);
		} else if ($compare < 0) {
						$temp = $max;
			$max = $min;
			$min = $temp;
		}

		$generator = $this->generator;

		$max = $max->subtract($min);
		$max = ltrim($max->toBytes(), chr(0));
		$size = strlen($max) - 1;
		$random = '';

		$bytes = $size & 1;
		for ($i = 0; $i < $bytes; ++$i) {
			$random.= chr($generator(0, 255));
		}

		$blocks = $size >> 1;
		for ($i = 0; $i < $blocks; ++$i) {
						$random.= pack('n', $generator(0, 0xFFFF));
		}

		$temp = new Math_BigInteger($random, 256);
		if ($temp->compare(new Math_BigInteger(substr($max, 1), 256)) > 0) {
			$random = chr($generator(0, ord($max[0]) - 1)) . $random;
		} else {
			$random = chr($generator(0, ord($max[0])    )) . $random;
		}

		$random = new Math_BigInteger($random, 256);

		return $this->_normalize($random->add($min));
	}


	function randomPrime($min = false, $max = false, $timeout = false)
	{
		$compare = $max->compare($min);

		if (!$compare) {
			return $min;
		} else if ($compare < 0) {
						$temp = $max;
			$max = $min;
			$min = $temp;
		}

				if ( MATH_BIGINTEGER_MODE == MATH_BIGINTEGER_MODE_GMP && function_exists('gmp_nextprime') ) {
												if ($min === false) {
				$min = new Math_BigInteger(0);
			}

			if ($max === false) {
				$max = new Math_BigInteger(0x7FFFFFFF);
			}

			$x = $this->random($min, $max);

			$x->value = gmp_nextprime($x->value);

			if ($x->compare($max) <= 0) {
				return $x;
			}

			$x->value = gmp_nextprime($min->value);

			if ($x->compare($max) <= 0) {
				return $x;
			}

			return false;
		}

		static $one, $two;
		if (!isset($one)) {
			$one = new Math_BigInteger(1);
			$two = new Math_BigInteger(2);
		}

		$start = time();

		$x = $this->random($min, $max);
		if ($x->equals($two)) {
			return $x;
		}

		$x->_make_odd();
		if ($x->compare($max) > 0) {
						if ($min->equals($max)) {
				return false;
			}
			$x = $min->copy();
			$x->_make_odd();
		}

		$initial_x = $x->copy();

		while (true) {
			if ($timeout !== false && time() - $start > $timeout) {
				return false;
			}

			if ($x->isPrime()) {
				return $x;
			}

			$x = $x->add($two);

			if ($x->compare($max) > 0) {
				$x = $min->copy();
				if ($x->equals($two)) {
					return $x;
				}
				$x->_make_odd();
			}

			if ($x->equals($initial_x)) {
				return false;
			}
		}
	}


	function _make_odd()
	{
		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				gmp_setbit($this->value, 0);
				break;
			case MATH_BIGINTEGER_MODE_BCMATH:
				if ($this->value[strlen($this->value) - 1] % 2 == 0) {
					$this->value = bcadd($this->value, '1');
				}
				break;
			default:
				$this->value[0] |= 1;
		}
	}


	function isPrime($t = false)
	{
		$length = strlen($this->toBytes());

		if (!$t) {
			if ($length >= 163) {
				$t =  2;
			} else if ($length >= 106) {
				$t = 3;
			} else if ($length >= 81 ) {
				$t =  4;
			} else if ($length >= 68 ) {
				$t =  5;
			} else if ($length >= 56 ) {
				$t =  6;
			} else if ($length >= 50 ) {
				$t =  7;
			} else if ($length >= 43 ) {
				$t =  8;
			} else if ($length >= 37 ) {
				$t =  9;
			} else if ($length >= 31 ) {
				$t = 12;
			} else if ($length >= 25 ) {
				$t = 15;
			} else if ($length >= 18 ) {
				$t = 18;
			} else                     {
				$t = 27;
			}
		}

		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				return gmp_prob_prime($this->value, $t) != 0;
			case MATH_BIGINTEGER_MODE_BCMATH:
				if ($this->value === '2') {
					return true;
				}
				if ($this->value[strlen($this->value) - 1] % 2 == 0) {
					return false;
				}
				break;
			default:
				if ($this->value == array(2)) {
					return true;
				}
				if (~$this->value[0] & 1) {
					return false;
				}
		}

		static $primes, $zero, $one, $two;

		if (!isset($primes)) {
			$primes = array(
				3,    5,    7,    11,   13,   17,   19,   23,   29,   31,   37,   41,   43,   47,   53,   59,
				61,   67,   71,   73,   79,   83,   89,   97,   101,  103,  107,  109,  113,  127,  131,  137,
				139,  149,  151,  157,  163,  167,  173,  179,  181,  191,  193,  197,  199,  211,  223,  227,
				229,  233,  239,  241,  251,  257,  263,  269,  271,  277,  281,  283,  293,  307,  311,  313,
				317,  331,  337,  347,  349,  353,  359,  367,  373,  379,  383,  389,  397,  401,  409,  419,
				421,  431,  433,  439,  443,  449,  457,  461,  463,  467,  479,  487,  491,  499,  503,  509,
				521,  523,  541,  547,  557,  563,  569,  571,  577,  587,  593,  599,  601,  607,  613,  617,
				619,  631,  641,  643,  647,  653,  659,  661,  673,  677,  683,  691,  701,  709,  719,  727,
				733,  739,  743,  751,  757,  761,  769,  773,  787,  797,  809,  811,  821,  823,  827,  829,
				839,  853,  857,  859,  863,  877,  881,  883,  887,  907,  911,  919,  929,  937,  941,  947,
				953,  967,  971,  977,  983,  991,  997
			);

			if ( MATH_BIGINTEGER_MODE != MATH_BIGINTEGER_MODE_INTERNAL ) {
				for ($i = 0; $i < count($primes); ++$i) {
					$primes[$i] = new Math_BigInteger($primes[$i]);
				}
			}

			$zero = new Math_BigInteger();
			$one = new Math_BigInteger(1);
			$two = new Math_BigInteger(2);
		}

		if ($this->equals($one)) {
			return false;
		}

		if ( MATH_BIGINTEGER_MODE != MATH_BIGINTEGER_MODE_INTERNAL ) {
			foreach ($primes as $prime) {
				list(, $r) = $this->divide($prime);
				if ($r->equals($zero)) {
					return $this->equals($prime);
				}
			}
		} else {
			$value = $this->value;
			foreach ($primes as $prime) {
				list(, $r) = $this->_divide_digit($value, $prime);
				if (!$r) {
					return count($value) == 1 && $value[0] == $prime;
				}
			}
		}

		$n   = $this->copy();
		$n_1 = $n->subtract($one);
		$n_2 = $n->subtract($two);

		$r = $n_1->copy();
		$r_value = $r->value;
				if ( MATH_BIGINTEGER_MODE == MATH_BIGINTEGER_MODE_BCMATH ) {
			$s = 0;
						while ($r->value[strlen($r->value) - 1] % 2 == 0) {
				$r->value = bcdiv($r->value, '2', 0);
				++$s;
			}
		} else {
			for ($i = 0, $r_length = count($r_value); $i < $r_length; ++$i) {
				$temp = ~$r_value[$i] & 0xFFFFFF;
				for ($j = 1; ($temp >> $j) & 1; ++$j);
				if ($j != 25) {
					break;
				}
			}
			$s = 26 * $i + $j - 1;
			$r->_rshift($s);
		}

		for ($i = 0; $i < $t; ++$i) {
			$a = $this->random($two, $n_2);
			$y = $a->modPow($r, $n);

			if (!$y->equals($one) && !$y->equals($n_1)) {
				for ($j = 1; $j < $s && !$y->equals($n_1); ++$j) {
					$y = $y->modPow($two, $n);
					if ($y->equals($one)) {
						return false;
					}
				}

				if (!$y->equals($n_1)) {
					return false;
				}
			}
		}
		return true;
	}


	function _lshift($shift)
	{
		if ( $shift == 0 ) {
			return;
		}

		$num_digits = (int) ($shift / 26);
		$shift %= 26;
		$shift = 1 << $shift;

		$carry = 0;

		for ($i = 0; $i < count($this->value); ++$i) {
			$temp = $this->value[$i] * $shift + $carry;
			$carry = (int) ($temp / 0x4000000);
			$this->value[$i] = (int) ($temp - $carry * 0x4000000);
		}

		if ( $carry ) {
			$this->value[] = $carry;
		}

		while ($num_digits--) {
			array_unshift($this->value, 0);
		}
	}


	function _rshift($shift)
	{
		if ($shift == 0) {
			return;
		}

		$num_digits = (int) ($shift / 26);
		$shift %= 26;
		$carry_shift = 26 - $shift;
		$carry_mask = (1 << $shift) - 1;

		if ( $num_digits ) {
			$this->value = array_slice($this->value, $num_digits);
		}

		$carry = 0;

		for ($i = count($this->value) - 1; $i >= 0; --$i) {
			$temp = $this->value[$i] >> $shift | $carry;
			$carry = ($this->value[$i] & $carry_mask) << $carry_shift;
			$this->value[$i] = $temp;
		}

		$this->value = $this->_trim($this->value);
	}


	function _normalize($result)
	{
		$result->precision = $this->precision;
		$result->bitmask = $this->bitmask;

		switch ( MATH_BIGINTEGER_MODE ) {
			case MATH_BIGINTEGER_MODE_GMP:
				if (!empty($result->bitmask->value)) {
					$result->value = gmp_and($result->value, $result->bitmask->value);
				}

				return $result;
			case MATH_BIGINTEGER_MODE_BCMATH:
				if (!empty($result->bitmask->value)) {
					$result->value = bcmod($result->value, $result->bitmask->value);
				}

				return $result;
		}

		$value = &$result->value;

		if ( !count($value) ) {
			return $result;
		}

		$value = $this->_trim($value);

		if (!empty($result->bitmask->value)) {
			$length = min(count($value), count($this->bitmask->value));
			$value = array_slice($value, 0, $length);

			for ($i = 0; $i < $length; ++$i) {
				$value[$i] = $value[$i] & $this->bitmask->value[$i];
			}
		}

		return $result;
	}


	function _trim($value)
	{
		for ($i = count($value) - 1; $i >= 0; --$i) {
			if ( $value[$i] ) {
				break;
			}
			unset($value[$i]);
		}

		return $value;
	}


	function _array_repeat($input, $multiplier)
	{
		return ($multiplier) ? array_fill(0, $multiplier, $input) : array();
	}


	function _base256_lshift(&$x, $shift)
	{
		if ($shift == 0) {
			return;
		}

		$num_bytes = $shift >> 3;
		$shift &= 7;
		$carry = 0;
		for ($i = strlen($x) - 1; $i >= 0; --$i) {
			$temp = ord($x[$i]) << $shift | $carry;
			$x[$i] = chr($temp);
			$carry = $temp >> 8;
		}
		$carry = ($carry != 0) ? chr($carry) : '';
		$x = $carry . $x . str_repeat(chr(0), $num_bytes);
	}


	function _base256_rshift(&$x, $shift)
	{
		if ($shift == 0) {
			$x = ltrim($x, chr(0));
			return '';
		}

		$num_bytes = $shift >> 3;
		$shift &= 7;
		$remainder = '';
		if ($num_bytes) {
			$start = $num_bytes > strlen($x) ? -strlen($x) : -$num_bytes;
			$remainder = substr($x, $start);
			$x = substr($x, 0, -$num_bytes);
		}

		$carry = 0;
		$carry_shift = 8 - $shift;
		for ($i = 0; $i < strlen($x); ++$i) {
			$temp = (ord($x[$i]) >> $shift) | $carry;
			$carry = (ord($x[$i]) << $carry_shift) & 0xFF;
			$x[$i] = chr($temp);
		}
		$x = ltrim($x, chr(0));

		$remainder = chr($carry >> $carry_shift) . $remainder;

		return ltrim($remainder, chr(0));
	}



	function _int2bytes($x)
	{
		return ltrim(pack('N', $x), chr(0));
	}


	function _bytes2int($x)
	{
		$temp = unpack('Nint', str_pad($x, 4, chr(0), STR_PAD_LEFT));
		return $temp['int'];
	}
}

function crypt_random($min = 0, $max = 0x7FFFFFFF)
{
	if ($min == $max) {
		return $min;
	}

	static $urandom = true;
	if ($urandom === true) {
		$urandom = @fopen('/dev/urandom', 'rb');
	}
	if (!is_bool($urandom)) {
		extract(unpack('Nrandom', fread($urandom, 4)));

		return abs($random) % ($max - $min) + $min;
	}

	if (version_compare(PHP_VERSION, '5.2.5', '<=')) {
		static $seeded;
		if (!isset($seeded)) {
			$seeded = true;
			mt_srand(fmod(time() * getmypid(), 0x7FFFFFFF) ^ fmod(1000000 * lcg_value(), 0x7FFFFFFF));
		}
	}

	static $crypto;

	if (!isset($crypto)) {
		$key = $iv = '';
		for ($i = 0; $i < 8; $i++) {
			$key.= pack('n', mt_rand(0, 0xFFFF));
			$iv .= pack('n', mt_rand(0, 0xFFFF));
		}
		switch (true) {
			case class_exists('Crypt_AES'):
				$crypto = new Crypt_AES(CRYPT_AES_MODE_CTR);
				break;
			case class_exists('Crypt_TripleDES'):
				$crypto = new Crypt_TripleDES(CRYPT_DES_MODE_CTR);
				break;
			case class_exists('Crypt_DES'):
				$crypto = new Crypt_DES(CRYPT_DES_MODE_CTR);
				break;
			case class_exists('Crypt_RC4'):
				$crypto = new Crypt_RC4();
				break;
			default:
				extract(unpack('Nrandom', pack('H*', sha1(mt_rand(0, 0x7FFFFFFF)))));
				return abs($random) % ($max - $min) + $min;
		}
		$crypto->setKey($key);
		$crypto->setIV($iv);
		$crypto->enableContinuousBuffer();
	}

	extract(unpack('Nrandom', $crypto->encrypt("\0\0\0\0")));
	return abs($random) % ($max - $min) + $min;
}

class Crypt_Hash
{
	protected
		$b,
		$l = false,
		$hash,
		$key = '',
		$opad,
		$ipad;

	function __construct($hash = 'sha1')
	{
		if ( !defined('CRYPT_HASH_MODE') ) {
			switch (true) {
				case extension_loaded('hash'):
					define('CRYPT_HASH_MODE', CRYPT_HASH_MODE_HASH);
					break;
				case extension_loaded('mhash'):
					define('CRYPT_HASH_MODE', CRYPT_HASH_MODE_MHASH);
					break;
				default:
					define('CRYPT_HASH_MODE', CRYPT_HASH_MODE_INTERNAL);
			}
		}

		$this->setHash($hash);
	}

	function setKey($key)
	{
		$this->key = $key;
	}

	function setHash($hash)
	{
		switch ($hash) {
			case 'md5-96':
			case 'sha1-96':
				$this->l = 12;
				break;
			case 'md2':
			case 'md5':
				$this->l = 16;
				break;
			case 'sha1':
				$this->l = 20;
				break;
			case 'sha256':
				$this->l = 32;
				break;
			case 'sha384':
				$this->l = 48;
				break;
			case 'sha512':
				$this->l = 64;
		}

		switch ($hash) {
			case 'md2':
				$mode = CRYPT_HASH_MODE == CRYPT_HASH_MODE_HASH && in_array('md2', hash_algos()) ?
					CRYPT_HASH_MODE_HASH : CRYPT_HASH_MODE_INTERNAL;
				break;
			case 'sha384':
			case 'sha512':
				$mode = CRYPT_HASH_MODE == CRYPT_HASH_MODE_MHASH ? CRYPT_HASH_MODE_INTERNAL : CRYPT_HASH_MODE;
				break;
			default:
				$mode = CRYPT_HASH_MODE;
		}

		switch ( $mode ) {
			case CRYPT_HASH_MODE_MHASH:
				switch ($hash) {
					case 'md5':
					case 'md5-96':
						$this->hash = MHASH_MD5;
						break;
					case 'sha256':
						$this->hash = MHASH_SHA256;
						break;
					case 'sha1':
					case 'sha1-96':
					default:
						$this->hash = MHASH_SHA1;
				}
				return;
			case CRYPT_HASH_MODE_HASH:
				switch ($hash) {
					case 'md5':
					case 'md5-96':
						$this->hash = 'md5';
						return;
					case 'md2':
					case 'sha256':
					case 'sha384':
					case 'sha512':
						$this->hash = $hash;
						return;
					case 'sha1':
					case 'sha1-96':
					default:
						$this->hash = 'sha1';
				}
				return;
		}

		switch ($hash) {
			case 'md2':
				 $this->b = 16;
				 $this->hash = array($this, '_md2');
				 break;
			case 'md5':
			case 'md5-96':
				 $this->b = 64;
				 $this->hash = array($this, '_md5');
				 break;
			case 'sha256':
				 $this->b = 64;
				 $this->hash = array($this, '_sha256');
				 break;
			case 'sha384':
			case 'sha512':
				 $this->b = 128;
				 $this->hash = array($this, '_sha512');
				 break;
			case 'sha1':
			case 'sha1-96':
			default:
				 $this->b = 64;
				 $this->hash = array($this, '_sha1');
		}

		$this->ipad = str_repeat(chr(0x36), $this->b);
		$this->opad = str_repeat(chr(0x5C), $this->b);
	}

	function hash($text)
	{
		$mode = is_array($this->hash) ? CRYPT_HASH_MODE_INTERNAL : CRYPT_HASH_MODE;

		if (!empty($this->key)) {
			switch ( $mode ) {
				case CRYPT_HASH_MODE_MHASH:
					$output = mhash($this->hash, $text, $this->key);
					break;
				case CRYPT_HASH_MODE_HASH:
					$output = hash_hmac($this->hash, $text, $this->key, true);
					break;
				case CRYPT_HASH_MODE_INTERNAL:
					$key = strlen($this->key) > $this->b ? call_user_func($this->hash, $this->key) : $this->key;

					$key = str_pad($key, $this->b, chr(0));
					$temp = $this->ipad ^ $key;
					$temp .= $text;
					$temp = call_user_func($this->hash, $temp);
					$output = $this->opad ^ $key;
					$output.= $temp;
					$output = call_user_func($this->hash, $output);
			}
		} else {
			switch ( $mode ) {
				case CRYPT_HASH_MODE_MHASH:
					$output = mhash($this->hash, $text);
					break;
				case CRYPT_HASH_MODE_HASH:
					$output = hash($this->hash, $text, true);
					break;
				case CRYPT_HASH_MODE_INTERNAL:
					$output = call_user_func($this->hash, $text);
			}
		}

		return substr($output, 0, $this->l);
	}

	function getLength()
	{
		return $this->l;
	}

	function _md5($m)
	{
		return pack('H*', md5($m));
	}

	function _sha1($m)
	{
		return pack('H*', sha1($m));
	}

	function _md2($m)
	{
		static $s = array(
			 41,  46,  67, 201, 162, 216, 124,   1,  61,  54,  84, 161, 236, 240, 6,
			 19,  98, 167,   5, 243, 192, 199, 115, 140, 152, 147,  43, 217, 188,
			 76, 130, 202,  30, 155,  87,  60, 253, 212, 224,  22, 103,  66, 111, 24,
			138,  23, 229,  18, 190,  78, 196, 214, 218, 158, 222,  73, 160, 251,
			245, 142, 187,  47, 238, 122, 169, 104, 121, 145,  21, 178,   7,  63,
			148, 194,  16, 137,  11,  34,  95,  33, 128, 127,  93, 154,  90, 144, 50,
			 39,  53,  62, 204, 231, 191, 247, 151,   3, 255,  25,  48, 179,  72, 165,
			181, 209, 215,  94, 146,  42, 172,  86, 170, 198,  79, 184,  56, 210,
			150, 164, 125, 182, 118, 252, 107, 226, 156, 116,   4, 241,  69, 157,
			112,  89, 100, 113, 135,  32, 134,  91, 207, 101, 230,  45, 168,   2, 27,
			 96,  37, 173, 174, 176, 185, 246,  28,  70,  97, 105,  52,  64, 126, 15,
			 85,  71, 163,  35, 221,  81, 175,  58, 195,  92, 249, 206, 186, 197,
			234,  38,  44,  83,  13, 110, 133,  40, 132,   9, 211, 223, 205, 244, 65,
			129,  77,  82, 106, 220,  55, 200, 108, 193, 171, 250,  36, 225, 123,
			  8,  12, 189, 177,  74, 120, 136, 149, 139, 227,  99, 232, 109, 233,
			203, 213, 254,  59,   0,  29,  57, 242, 239, 183,  14, 102,  88, 208, 228,
			166, 119, 114, 248, 235, 117,  75,  10,  49,  68,  80, 180, 143, 237,
			 31,  26, 219, 153, 141,  51, 159,  17, 131, 20
		);

		$pad = 16 - (strlen($m) & 0xF);
		$m.= str_repeat(chr($pad), $pad);

		$length = strlen($m);

		$c = str_repeat(chr(0), 16);
		$l = chr(0);
		for ($i = 0; $i < $length; $i+= 16) {
			for ($j = 0; $j < 16; $j++) {
				$c[$j] = chr($s[ord($m[$i + $j] ^ $l)] ^ ord($c[$j]));
				$l = $c[$j];
			}
		}
		$m.= $c;

		$length+= 16;

		$x = str_repeat(chr(0), 48);

		for ($i = 0; $i < $length; $i+= 16) {
			for ($j = 0; $j < 16; $j++) {
				$x[$j + 16] = $m[$i + $j];
				$x[$j + 32] = $x[$j + 16] ^ $x[$j];
			}
			$t = chr(0);
			for ($j = 0; $j < 18; $j++) {
				for ($k = 0; $k < 48; $k++) {
					$x[$k] = $t = $x[$k] ^ chr($s[ord($t)]);
									}
				$t = chr(ord($t) + $j);
			}
		}

		return substr($x, 0, 16);
	}

	function _sha256($m)
	{
		if (extension_loaded('suhosin')) {
			return pack('H*', sha256($m));
		}

		$hash = array(
			0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19
		);
		static $k = array(
			0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
			0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
			0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
			0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
			0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
			0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
			0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
			0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
		);

		$length = strlen($m);
		$m.= str_repeat(chr(0), 64 - (($length + 8) & 0x3F));
		$m[$length] = chr(0x80);
		$m.= pack('N2', 0, $length << 3);

		$chunks = str_split($m, 64);
		foreach ($chunks as $chunk) {
			$w = array();
			for ($i = 0; $i < 16; $i++) {
				extract(unpack('Ntemp', $this->_string_shift($chunk, 4)));
				$w[] = $temp;
			}

			for ($i = 16; $i < 64; $i++) {
				$s0 = $this->_rightRotate($w[$i - 15],  7) ^
					  $this->_rightRotate($w[$i - 15], 18) ^
					  $this->_rightShift( $w[$i - 15],  3);
				$s1 = $this->_rightRotate($w[$i - 2], 17) ^
					  $this->_rightRotate($w[$i - 2], 19) ^
					  $this->_rightShift( $w[$i - 2], 10);
				$w[$i] = $this->_add($w[$i - 16], $s0, $w[$i - 7], $s1);

			}

			list($a, $b, $c, $d, $e, $f, $g, $h) = $hash;

			for ($i = 0; $i < 64; $i++) {
				$s0 = $this->_rightRotate($a,  2) ^
					  $this->_rightRotate($a, 13) ^
					  $this->_rightRotate($a, 22);
				$maj = ($a & $b) ^
					   ($a & $c) ^
					   ($b & $c);
				$t2 = $this->_add($s0, $maj);

				$s1 = $this->_rightRotate($e,  6) ^
					  $this->_rightRotate($e, 11) ^
					  $this->_rightRotate($e, 25);
				$ch = ($e & $f) ^
					  ($this->_not($e) & $g);
				$t1 = $this->_add($h, $s1, $ch, $k[$i], $w[$i]);

				$h = $g;
				$g = $f;
				$f = $e;
				$e = $this->_add($d, $t1);
				$d = $c;
				$c = $b;
				$b = $a;
				$a = $this->_add($t1, $t2);
			}

			$hash = array(
				$this->_add($hash[0], $a),
				$this->_add($hash[1], $b),
				$this->_add($hash[2], $c),
				$this->_add($hash[3], $d),
				$this->_add($hash[4], $e),
				$this->_add($hash[5], $f),
				$this->_add($hash[6], $g),
				$this->_add($hash[7], $h)
			);
		}

		return pack('N8', $hash[0], $hash[1], $hash[2], $hash[3], $hash[4], $hash[5], $hash[6], $hash[7]);
	}

	function _sha512($m)
	{
		static $init384, $init512, $k;

		if (!isset($k)) {
			$init384 = array(
				'cbbb9d5dc1059ed8', '629a292a367cd507', '9159015a3070dd17', '152fecd8f70e5939',
				'67332667ffc00b31', '8eb44a8768581511', 'db0c2e0d64f98fa7', '47b5481dbefa4fa4'
			);
			$init512 = array(
				'6a09e667f3bcc908', 'bb67ae8584caa73b', '3c6ef372fe94f82b', 'a54ff53a5f1d36f1',
				'510e527fade682d1', '9b05688c2b3e6c1f', '1f83d9abfb41bd6b', '5be0cd19137e2179'
			);

			for ($i = 0; $i < 8; $i++) {
				$init384[$i] = new Math_BigInteger($init384[$i], 16);
				$init384[$i]->setPrecision(64);
				$init512[$i] = new Math_BigInteger($init512[$i], 16);
				$init512[$i]->setPrecision(64);
			}

			$k = array(
				'428a2f98d728ae22', '7137449123ef65cd', 'b5c0fbcfec4d3b2f', 'e9b5dba58189dbbc',
				'3956c25bf348b538', '59f111f1b605d019', '923f82a4af194f9b', 'ab1c5ed5da6d8118',
				'd807aa98a3030242', '12835b0145706fbe', '243185be4ee4b28c', '550c7dc3d5ffb4e2',
				'72be5d74f27b896f', '80deb1fe3b1696b1', '9bdc06a725c71235', 'c19bf174cf692694',
				'e49b69c19ef14ad2', 'efbe4786384f25e3', '0fc19dc68b8cd5b5', '240ca1cc77ac9c65',
				'2de92c6f592b0275', '4a7484aa6ea6e483', '5cb0a9dcbd41fbd4', '76f988da831153b5',
				'983e5152ee66dfab', 'a831c66d2db43210', 'b00327c898fb213f', 'bf597fc7beef0ee4',
				'c6e00bf33da88fc2', 'd5a79147930aa725', '06ca6351e003826f', '142929670a0e6e70',
				'27b70a8546d22ffc', '2e1b21385c26c926', '4d2c6dfc5ac42aed', '53380d139d95b3df',
				'650a73548baf63de', '766a0abb3c77b2a8', '81c2c92e47edaee6', '92722c851482353b',
				'a2bfe8a14cf10364', 'a81a664bbc423001', 'c24b8b70d0f89791', 'c76c51a30654be30',
				'd192e819d6ef5218', 'd69906245565a910', 'f40e35855771202a', '106aa07032bbd1b8',
				'19a4c116b8d2d0c8', '1e376c085141ab53', '2748774cdf8eeb99', '34b0bcb5e19b48a8',
				'391c0cb3c5c95a63', '4ed8aa4ae3418acb', '5b9cca4f7763e373', '682e6ff3d6b2b8a3',
				'748f82ee5defb2fc', '78a5636f43172f60', '84c87814a1f0ab72', '8cc702081a6439ec',
				'90befffa23631e28', 'a4506cebde82bde9', 'bef9a3f7b2c67915', 'c67178f2e372532b',
				'ca273eceea26619c', 'd186b8c721c0c207', 'eada7dd6cde0eb1e', 'f57d4f7fee6ed178',
				'06f067aa72176fba', '0a637dc5a2c898a6', '113f9804bef90dae', '1b710b35131c471b',
				'28db77f523047d84', '32caab7b40c72493', '3c9ebe0a15c9bebc', '431d67c49c100d4c',
				'4cc5d4becb3e42b6', '597f299cfc657e2a', '5fcb6fab3ad6faec', '6c44198c4a475817'
			);

			for ($i = 0; $i < 80; $i++) {
				$k[$i] = new Math_BigInteger($k[$i], 16);
			}
		}

		$hash = $this->l == 48 ? $init384 : $init512;

		$length = strlen($m);
		$m.= str_repeat(chr(0), 128 - (($length + 16) & 0x7F));
		$m[$length] = chr(0x80);
		$m.= pack('N4', 0, 0, 0, $length << 3);

		$chunks = str_split($m, 128);
		foreach ($chunks as $chunk) {
			$w = array();
			for ($i = 0; $i < 16; $i++) {
				$temp = new Math_BigInteger($this->_string_shift($chunk, 8), 256);
				$temp->setPrecision(64);
				$w[] = $temp;
			}

			for ($i = 16; $i < 80; $i++) {
				$temp = array(
					$w[$i - 15]->bitwise_rightRotate(1),
					$w[$i - 15]->bitwise_rightRotate(8),
					$w[$i - 15]->bitwise_rightShift(7)
				);
				$s0 = $temp[0]->bitwise_xor($temp[1]);
				$s0 = $s0->bitwise_xor($temp[2]);
				$temp = array(
					$w[$i - 2]->bitwise_rightRotate(19),
					$w[$i - 2]->bitwise_rightRotate(61),
					$w[$i - 2]->bitwise_rightShift(6)
				);
				$s1 = $temp[0]->bitwise_xor($temp[1]);
				$s1 = $s1->bitwise_xor($temp[2]);
				$w[$i] = $w[$i - 16]->copy();
				$w[$i] = $w[$i]->add($s0);
				$w[$i] = $w[$i]->add($w[$i - 7]);
				$w[$i] = $w[$i]->add($s1);
			}

			$a = $hash[0]->copy();
			$b = $hash[1]->copy();
			$c = $hash[2]->copy();
			$d = $hash[3]->copy();
			$e = $hash[4]->copy();
			$f = $hash[5]->copy();
			$g = $hash[6]->copy();
			$h = $hash[7]->copy();

			for ($i = 0; $i < 80; $i++) {
				$temp = array(
					$a->bitwise_rightRotate(28),
					$a->bitwise_rightRotate(34),
					$a->bitwise_rightRotate(39)
				);
				$s0 = $temp[0]->bitwise_xor($temp[1]);
				$s0 = $s0->bitwise_xor($temp[2]);
				$temp = array(
					$a->bitwise_and($b),
					$a->bitwise_and($c),
					$b->bitwise_and($c)
				);
				$maj = $temp[0]->bitwise_xor($temp[1]);
				$maj = $maj->bitwise_xor($temp[2]);
				$t2 = $s0->add($maj);

				$temp = array(
					$e->bitwise_rightRotate(14),
					$e->bitwise_rightRotate(18),
					$e->bitwise_rightRotate(41)
				);
				$s1 = $temp[0]->bitwise_xor($temp[1]);
				$s1 = $s1->bitwise_xor($temp[2]);
				$temp = array(
					$e->bitwise_and($f),
					$g->bitwise_and($e->bitwise_not())
				);
				$ch = $temp[0]->bitwise_xor($temp[1]);
				$t1 = $h->add($s1);
				$t1 = $t1->add($ch);
				$t1 = $t1->add($k[$i]);
				$t1 = $t1->add($w[$i]);

				$h = $g->copy();
				$g = $f->copy();
				$f = $e->copy();
				$e = $d->add($t1);
				$d = $c->copy();
				$c = $b->copy();
				$b = $a->copy();
				$a = $t1->add($t2);
			}

			$hash = array(
				$hash[0]->add($a),
				$hash[1]->add($b),
				$hash[2]->add($c),
				$hash[3]->add($d),
				$hash[4]->add($e),
				$hash[5]->add($f),
				$hash[6]->add($g),
				$hash[7]->add($h)
			);
		}

		$temp = $hash[0]->toBytes() . $hash[1]->toBytes() . $hash[2]->toBytes() . $hash[3]->toBytes() . $hash[4]->toBytes() . $hash[5]->toBytes();
		if ($this->l != 48) {
			$temp.= $hash[6]->toBytes() . $hash[7]->toBytes();
		}

		return $temp;
	}

	function _rightRotate($int, $amt)
	{
		$invamt = 32 - $amt;
		$mask = (1 << $invamt) - 1;
		return (($int << $invamt) & 0xFFFFFFFF) | (($int >> $amt) & $mask);
	}

	function _rightShift($int, $amt)
	{
		$mask = (1 << (32 - $amt)) - 1;
		return ($int >> $amt) & $mask;
	}

	function _not($int)
	{
		return ~$int & 0xFFFFFFFF;
	}

	function _add()
	{
		static $mod;
		if (!isset($mod)) {
			$mod = pow(2, 32);
		}

		$result = 0;
		$arguments = func_get_args();
		foreach ($arguments as $argument) {
			$result+= $argument < 0 ? ($argument & 0x7FFFFFFF) + 0x80000000 : $argument;
		}

		return fmod($result, $mod);
	}

	function _string_shift(&$string, $index = 1)
	{
		$substr = substr($string, 0, $index);
		$string = substr($string, $index);
		return $substr;
	}
}

class Crypt_Rijndael
{
	protected
		$mode,
		$key = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0",
		$iv = '',
		$encryptIV = '',
		$decryptIV = '',
		$continuousBuffer = false,
		$padding = true,
		$changed = true,
		$explicit_key_length = false,
		$w,
		$dw,
		$block_size = 16,
		$Nb = 4,
		$key_size = 16,
		$Nk = 4,
		$Nr,
		$c,
		$t0,
		$t1,
		$t2,
		$t3,
		$dt0,
		$dt1,
		$dt2,
		$dt3,
		$paddable = false,
		$enbuffer = array('encrypted' => '', 'xor' => ''),
		$debuffer = array('ciphertext' => '');

	function __construct($mode = CRYPT_RIJNDAEL_MODE_CBC)
	{
		switch ($mode) {
			case CRYPT_RIJNDAEL_MODE_ECB:
			case CRYPT_RIJNDAEL_MODE_CBC:
				$this->paddable = true;
				$this->mode = $mode;
				break;
			case CRYPT_RIJNDAEL_MODE_CTR:
			case CRYPT_RIJNDAEL_MODE_CFB:
			case CRYPT_RIJNDAEL_MODE_OFB:
				$this->mode = $mode;
				break;
			default:
				$this->paddable = true;
				$this->mode = CRYPT_RIJNDAEL_MODE_CBC;
		}

		$t3 = &$this->t3;
		$t2 = &$this->t2;
		$t1 = &$this->t1;
		$t0 = &$this->t0;

		$dt3 = &$this->dt3;
		$dt2 = &$this->dt2;
		$dt1 = &$this->dt1;
		$dt0 = &$this->dt0;

								$t3 = array(
			0x6363A5C6, 0x7C7C84F8, 0x777799EE, 0x7B7B8DF6, 0xF2F20DFF, 0x6B6BBDD6, 0x6F6FB1DE, 0xC5C55491,
			0x30305060, 0x01010302, 0x6767A9CE, 0x2B2B7D56, 0xFEFE19E7, 0xD7D762B5, 0xABABE64D, 0x76769AEC,
			0xCACA458F, 0x82829D1F, 0xC9C94089, 0x7D7D87FA, 0xFAFA15EF, 0x5959EBB2, 0x4747C98E, 0xF0F00BFB,
			0xADADEC41, 0xD4D467B3, 0xA2A2FD5F, 0xAFAFEA45, 0x9C9CBF23, 0xA4A4F753, 0x727296E4, 0xC0C05B9B,
			0xB7B7C275, 0xFDFD1CE1, 0x9393AE3D, 0x26266A4C, 0x36365A6C, 0x3F3F417E, 0xF7F702F5, 0xCCCC4F83,
			0x34345C68, 0xA5A5F451, 0xE5E534D1, 0xF1F108F9, 0x717193E2, 0xD8D873AB, 0x31315362, 0x15153F2A,
			0x04040C08, 0xC7C75295, 0x23236546, 0xC3C35E9D, 0x18182830, 0x9696A137, 0x05050F0A, 0x9A9AB52F,
			0x0707090E, 0x12123624, 0x80809B1B, 0xE2E23DDF, 0xEBEB26CD, 0x2727694E, 0xB2B2CD7F, 0x75759FEA,
			0x09091B12, 0x83839E1D, 0x2C2C7458, 0x1A1A2E34, 0x1B1B2D36, 0x6E6EB2DC, 0x5A5AEEB4, 0xA0A0FB5B,
			0x5252F6A4, 0x3B3B4D76, 0xD6D661B7, 0xB3B3CE7D, 0x29297B52, 0xE3E33EDD, 0x2F2F715E, 0x84849713,
			0x5353F5A6, 0xD1D168B9, 0x00000000, 0xEDED2CC1, 0x20206040, 0xFCFC1FE3, 0xB1B1C879, 0x5B5BEDB6,
			0x6A6ABED4, 0xCBCB468D, 0xBEBED967, 0x39394B72, 0x4A4ADE94, 0x4C4CD498, 0x5858E8B0, 0xCFCF4A85,
			0xD0D06BBB, 0xEFEF2AC5, 0xAAAAE54F, 0xFBFB16ED, 0x4343C586, 0x4D4DD79A, 0x33335566, 0x85859411,
			0x4545CF8A, 0xF9F910E9, 0x02020604, 0x7F7F81FE, 0x5050F0A0, 0x3C3C4478, 0x9F9FBA25, 0xA8A8E34B,
			0x5151F3A2, 0xA3A3FE5D, 0x4040C080, 0x8F8F8A05, 0x9292AD3F, 0x9D9DBC21, 0x38384870, 0xF5F504F1,
			0xBCBCDF63, 0xB6B6C177, 0xDADA75AF, 0x21216342, 0x10103020, 0xFFFF1AE5, 0xF3F30EFD, 0xD2D26DBF,
			0xCDCD4C81, 0x0C0C1418, 0x13133526, 0xECEC2FC3, 0x5F5FE1BE, 0x9797A235, 0x4444CC88, 0x1717392E,
			0xC4C45793, 0xA7A7F255, 0x7E7E82FC, 0x3D3D477A, 0x6464ACC8, 0x5D5DE7BA, 0x19192B32, 0x737395E6,
			0x6060A0C0, 0x81819819, 0x4F4FD19E, 0xDCDC7FA3, 0x22226644, 0x2A2A7E54, 0x9090AB3B, 0x8888830B,
			0x4646CA8C, 0xEEEE29C7, 0xB8B8D36B, 0x14143C28, 0xDEDE79A7, 0x5E5EE2BC, 0x0B0B1D16, 0xDBDB76AD,
			0xE0E03BDB, 0x32325664, 0x3A3A4E74, 0x0A0A1E14, 0x4949DB92, 0x06060A0C, 0x24246C48, 0x5C5CE4B8,
			0xC2C25D9F, 0xD3D36EBD, 0xACACEF43, 0x6262A6C4, 0x9191A839, 0x9595A431, 0xE4E437D3, 0x79798BF2,
			0xE7E732D5, 0xC8C8438B, 0x3737596E, 0x6D6DB7DA, 0x8D8D8C01, 0xD5D564B1, 0x4E4ED29C, 0xA9A9E049,
			0x6C6CB4D8, 0x5656FAAC, 0xF4F407F3, 0xEAEA25CF, 0x6565AFCA, 0x7A7A8EF4, 0xAEAEE947, 0x08081810,
			0xBABAD56F, 0x787888F0, 0x25256F4A, 0x2E2E725C, 0x1C1C2438, 0xA6A6F157, 0xB4B4C773, 0xC6C65197,
			0xE8E823CB, 0xDDDD7CA1, 0x74749CE8, 0x1F1F213E, 0x4B4BDD96, 0xBDBDDC61, 0x8B8B860D, 0x8A8A850F,
			0x707090E0, 0x3E3E427C, 0xB5B5C471, 0x6666AACC, 0x4848D890, 0x03030506, 0xF6F601F7, 0x0E0E121C,
			0x6161A3C2, 0x35355F6A, 0x5757F9AE, 0xB9B9D069, 0x86869117, 0xC1C15899, 0x1D1D273A, 0x9E9EB927,
			0xE1E138D9, 0xF8F813EB, 0x9898B32B, 0x11113322, 0x6969BBD2, 0xD9D970A9, 0x8E8E8907, 0x9494A733,
			0x9B9BB62D, 0x1E1E223C, 0x87879215, 0xE9E920C9, 0xCECE4987, 0x5555FFAA, 0x28287850, 0xDFDF7AA5,
			0x8C8C8F03, 0xA1A1F859, 0x89898009, 0x0D0D171A, 0xBFBFDA65, 0xE6E631D7, 0x4242C684, 0x6868B8D0,
			0x4141C382, 0x9999B029, 0x2D2D775A, 0x0F0F111E, 0xB0B0CB7B, 0x5454FCA8, 0xBBBBD66D, 0x16163A2C
		);

		$dt3 = array(
			0xF4A75051, 0x4165537E, 0x17A4C31A, 0x275E963A, 0xAB6BCB3B, 0x9D45F11F, 0xFA58ABAC, 0xE303934B,
			0x30FA5520, 0x766DF6AD, 0xCC769188, 0x024C25F5, 0xE5D7FC4F, 0x2ACBD7C5, 0x35448026, 0x62A38FB5,
			0xB15A49DE, 0xBA1B6725, 0xEA0E9845, 0xFEC0E15D, 0x2F7502C3, 0x4CF01281, 0x4697A38D, 0xD3F9C66B,
			0x8F5FE703, 0x929C9515, 0x6D7AEBBF, 0x5259DA95, 0xBE832DD4, 0x7421D358, 0xE0692949, 0xC9C8448E,
			0xC2896A75, 0x8E7978F4, 0x583E6B99, 0xB971DD27, 0xE14FB6BE, 0x88AD17F0, 0x20AC66C9, 0xCE3AB47D,
			0xDF4A1863, 0x1A3182E5, 0x51336097, 0x537F4562, 0x6477E0B1, 0x6BAE84BB, 0x81A01CFE, 0x082B94F9,
			0x48685870, 0x45FD198F, 0xDE6C8794, 0x7BF8B752, 0x73D323AB, 0x4B02E272, 0x1F8F57E3, 0x55AB2A66,
			0xEB2807B2, 0xB5C2032F, 0xC57B9A86, 0x3708A5D3, 0x2887F230, 0xBFA5B223, 0x036ABA02, 0x16825CED,
			0xCF1C2B8A, 0x79B492A7, 0x07F2F0F3, 0x69E2A14E, 0xDAF4CD65, 0x05BED506, 0x34621FD1, 0xA6FE8AC4,
			0x2E539D34, 0xF355A0A2, 0x8AE13205, 0xF6EB75A4, 0x83EC390B, 0x60EFAA40, 0x719F065E, 0x6E1051BD,
			0x218AF93E, 0xDD063D96, 0x3E05AEDD, 0xE6BD464D, 0x548DB591, 0xC45D0571, 0x06D46F04, 0x5015FF60,
			0x98FB2419, 0xBDE997D6, 0x4043CC89, 0xD99E7767, 0xE842BDB0, 0x898B8807, 0x195B38E7, 0xC8EEDB79,
			0x7C0A47A1, 0x420FE97C, 0x841EC9F8, 0x00000000, 0x80868309, 0x2BED4832, 0x1170AC1E, 0x5A724E6C,
			0x0EFFFBFD, 0x8538560F, 0xAED51E3D, 0x2D392736, 0x0FD9640A, 0x5CA62168, 0x5B54D19B, 0x362E3A24,
			0x0A67B10C, 0x57E70F93, 0xEE96D2B4, 0x9B919E1B, 0xC0C54F80, 0xDC20A261, 0x774B695A, 0x121A161C,
			0x93BA0AE2, 0xA02AE5C0, 0x22E0433C, 0x1B171D12, 0x090D0B0E, 0x8BC7ADF2, 0xB6A8B92D, 0x1EA9C814,
			0xF1198557, 0x75074CAF, 0x99DDBBEE, 0x7F60FDA3, 0x01269FF7, 0x72F5BC5C, 0x663BC544, 0xFB7E345B,
			0x4329768B, 0x23C6DCCB, 0xEDFC68B6, 0xE4F163B8, 0x31DCCAD7, 0x63851042, 0x97224013, 0xC6112084,
			0x4A247D85, 0xBB3DF8D2, 0xF93211AE, 0x29A16DC7, 0x9E2F4B1D, 0xB230F3DC, 0x8652EC0D, 0xC1E3D077,
			0xB3166C2B, 0x70B999A9, 0x9448FA11, 0xE9642247, 0xFC8CC4A8, 0xF03F1AA0, 0x7D2CD856, 0x3390EF22,
			0x494EC787, 0x38D1C1D9, 0xCAA2FE8C, 0xD40B3698, 0xF581CFA6, 0x7ADE28A5, 0xB78E26DA, 0xADBFA43F,
			0x3A9DE42C, 0x78920D50, 0x5FCC9B6A, 0x7E466254, 0x8D13C2F6, 0xD8B8E890, 0x39F75E2E, 0xC3AFF582,
			0x5D80BE9F, 0xD0937C69, 0xD52DA96F, 0x2512B3CF, 0xAC993BC8, 0x187DA710, 0x9C636EE8, 0x3BBB7BDB,
			0x267809CD, 0x5918F46E, 0x9AB701EC, 0x4F9AA883, 0x956E65E6, 0xFFE67EAA, 0xBCCF0821, 0x15E8E6EF,
			0xE79BD9BA, 0x6F36CE4A, 0x9F09D4EA, 0xB07CD629, 0xA4B2AF31, 0x3F23312A, 0xA59430C6, 0xA266C035,
			0x4EBC3774, 0x82CAA6FC, 0x90D0B0E0, 0xA7D81533, 0x04984AF1, 0xECDAF741, 0xCD500E7F, 0x91F62F17,
			0x4DD68D76, 0xEFB04D43, 0xAA4D54CC, 0x9604DFE4, 0xD1B5E39E, 0x6A881B4C, 0x2C1FB8C1, 0x65517F46,
			0x5EEA049D, 0x8C355D01, 0x877473FA, 0x0B412EFB, 0x671D5AB3, 0xDBD25292, 0x105633E9, 0xD647136D,
			0xD7618C9A, 0xA10C7A37, 0xF8148E59, 0x133C89EB, 0xA927EECE, 0x61C935B7, 0x1CE5EDE1, 0x47B13C7A,
			0xD2DF599C, 0xF2733F55, 0x14CE7918, 0xC737BF73, 0xF7CDEA53, 0xFDAA5B5F, 0x3D6F14DF, 0x44DB8678,
			0xAFF381CA, 0x68C43EB9, 0x24342C38, 0xA3405FC2, 0x1DC37216, 0xE2250CBC, 0x3C498B28, 0x0D9541FF,
			0xA8017139, 0x0CB3DE08, 0xB4E49CD8, 0x56C19064, 0xCB84617B, 0x32B670D5, 0x6C5C7448, 0xB85742D0
		);

		for ($i = 0; $i < 256; $i++) {
			$t2[$i <<  8] = (($t3[$i] <<  8) & 0xFFFFFF00) | (($t3[$i] >> 24) & 0x000000FF);
			$t1[$i << 16] = (($t3[$i] << 16) & 0xFFFF0000) | (($t3[$i] >> 16) & 0x0000FFFF);
			$t0[$i << 24] = (($t3[$i] << 24) & 0xFF000000) | (($t3[$i] >>  8) & 0x00FFFFFF);

			$dt2[$i <<  8] = (($this->dt3[$i] <<  8) & 0xFFFFFF00) | (($dt3[$i] >> 24) & 0x000000FF);
			$dt1[$i << 16] = (($this->dt3[$i] << 16) & 0xFFFF0000) | (($dt3[$i] >> 16) & 0x0000FFFF);
			$dt0[$i << 24] = (($this->dt3[$i] << 24) & 0xFF000000) | (($dt3[$i] >>  8) & 0x00FFFFFF);
		}
	}

	function setKey($key)
	{
		$this->key = $key;
		$this->changed = true;
	}

	function setIV($iv)
	{
		$this->encryptIV = $this->decryptIV = $this->iv = str_pad(substr($iv, 0, $this->block_size), $this->block_size, chr(0));;
	}

	function setKeyLength($length)
	{
		$length >>= 5;
		if ($length > 8) {
			$length = 8;
		} else if ($length < 4) {
			$length = 4;
		}
		$this->Nk = $length;
		$this->key_size = $length << 2;

		$this->explicit_key_length = true;
		$this->changed = true;
	}

	function setBlockLength($length)
	{
		$length >>= 5;
		if ($length > 8) {
			$length = 8;
		} else if ($length < 4) {
			$length = 4;
		}
		$this->Nb = $length;
		$this->block_size = $length << 2;
		$this->changed = true;
	}

	function _generate_xor($length, &$iv)
	{
		$xor = '';
		$block_size = $this->block_size;
		$num_blocks = floor(($length + ($block_size - 1)) / $block_size);
		for ($i = 0; $i < $num_blocks; $i++) {
			$xor.= $iv;
			for ($j = 4; $j <= $block_size; $j+=4) {
				$temp = substr($iv, -$j, 4);
				switch ($temp) {
					case "\xFF\xFF\xFF\xFF":
						$iv = substr_replace($iv, "\x00\x00\x00\x00", -$j, 4);
						break;
					case "\x7F\xFF\xFF\xFF":
						$iv = substr_replace($iv, "\x80\x00\x00\x00", -$j, 4);
						break 2;
					default:
						extract(unpack('Ncount', $temp));
						$iv = substr_replace($iv, pack('N', $count + 1), -$j, 4);
						break 2;
				}
			}
		}

		return $xor;
	}

	function encrypt($plaintext)
	{
		$this->_setup();
		if ($this->paddable) {
			$plaintext = $this->_pad($plaintext);
		}

		$block_size = $this->block_size;
		$buffer = &$this->enbuffer;
		$continuousBuffer = $this->continuousBuffer;
		$ciphertext = '';
		switch ($this->mode) {
			case CRYPT_RIJNDAEL_MODE_ECB:
				for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
					$ciphertext.= $this->_encryptBlock(substr($plaintext, $i, $block_size));
				}
				break;
			case CRYPT_RIJNDAEL_MODE_CBC:
				$xor = $this->encryptIV;
				for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
					$block = substr($plaintext, $i, $block_size);
					$block = $this->_encryptBlock($block ^ $xor);
					$xor = $block;
					$ciphertext.= $block;
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
				}
				break;
			case CRYPT_RIJNDAEL_MODE_CTR:
				$xor = $this->encryptIV;
				if (!empty($buffer)) {
					for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
						$block = substr($plaintext, $i, $block_size);
						$buffer.= $this->_encryptBlock($this->_generate_xor($block_size, $xor));
						$key = $this->_string_shift($buffer, $block_size);
						$ciphertext.= $block ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
						$block = substr($plaintext, $i, $block_size);
						$key = $this->_encryptBlock($this->_generate_xor($block_size, $xor));
						$ciphertext.= $block ^ $key;
					}
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
					if ($start = strlen($plaintext) % $block_size) {
						$buffer = substr($key, $start) . $buffer;
					}
				}
				break;
			case CRYPT_RIJNDAEL_MODE_CFB:
				if (!empty($buffer['xor'])) {
					$ciphertext = $plaintext ^ $buffer['xor'];
					$iv = $buffer['encrypted'] . $ciphertext;
					$start = strlen($ciphertext);
					$buffer['encrypted'].= $ciphertext;
					$buffer['xor'] = substr($buffer['xor'], strlen($ciphertext));
				} else {
					$ciphertext = '';
					$iv = $this->encryptIV;
					$start = 0;
				}

				for ($i = $start; $i < strlen($plaintext); $i+=$block_size) {
					$block = substr($plaintext, $i, $block_size);
					$xor = $this->_encryptBlock($iv);
					$iv = $block ^ $xor;
					if ($continuousBuffer && strlen($iv) != $block_size) {
						$buffer = array(
							'encrypted' => $iv,
							'xor' => substr($xor, strlen($iv))
						);
					}
					$ciphertext.= $iv;
				}

				if ($this->continuousBuffer) {
					$this->encryptIV = $iv;
				}
				break;
			case CRYPT_RIJNDAEL_MODE_OFB:
				$xor = $this->encryptIV;
				if (strlen($buffer)) {
					for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
						$xor = $this->_encryptBlock($xor);
						$buffer.= $xor;
						$key = $this->_string_shift($buffer, $block_size);
						$ciphertext.= substr($plaintext, $i, $block_size) ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
						$xor = $this->_encryptBlock($xor);
						$ciphertext.= substr($plaintext, $i, $block_size) ^ $xor;
					}
					$key = $xor;
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
					if ($start = strlen($plaintext) % $block_size) {
						 $buffer = substr($key, $start) . $buffer;
					}
				}
		}

		return $ciphertext;
	}

	function decrypt($ciphertext)
	{
		$this->_setup();

		if ($this->paddable) {
									$ciphertext = str_pad($ciphertext, strlen($ciphertext) + ($this->block_size - strlen($ciphertext) % $this->block_size) % $this->block_size, chr(0));
		}

		$block_size = $this->block_size;
		$buffer = &$this->debuffer;
		$continuousBuffer = $this->continuousBuffer;
		$plaintext = '';
		switch ($this->mode) {
			case CRYPT_RIJNDAEL_MODE_ECB:
				for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
					$plaintext.= $this->_decryptBlock(substr($ciphertext, $i, $block_size));
				}
				break;
			case CRYPT_RIJNDAEL_MODE_CBC:
				$xor = $this->decryptIV;
				for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
					$block = substr($ciphertext, $i, $block_size);
					$plaintext.= $this->_decryptBlock($block) ^ $xor;
					$xor = $block;
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
				}
				break;
			case CRYPT_RIJNDAEL_MODE_CTR:
				$xor = $this->decryptIV;
				if (strlen($buffer)) {
					for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
						$block = substr($ciphertext, $i, $block_size);
						$buffer.= $this->_encryptBlock($this->_generate_xor($block_size, $xor));
						$key = $this->_string_shift($buffer, $block_size);
						$plaintext.= $block ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
						$block = substr($ciphertext, $i, $block_size);
						$key = $this->_encryptBlock($this->_generate_xor($block_size, $xor));
						$plaintext.= $block ^ $key;
					}
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
					if ($start = strlen($ciphertext) % $block_size) {
						$buffer = substr($key, $start) . $buffer;
					}
				}
				break;
			case CRYPT_RIJNDAEL_MODE_CFB:
				if (!empty($buffer['ciphertext'])) {
					$plaintext = $ciphertext ^ substr($this->decryptIV, strlen($buffer['ciphertext']));
					$buffer['ciphertext'].= substr($ciphertext, 0, strlen($plaintext));
					if (strlen($buffer['ciphertext']) == $block_size) {
						$xor = $this->_encryptBlock($buffer['ciphertext']);
						$buffer['ciphertext'] = '';
					}
					$start = strlen($plaintext);
					$block = $this->decryptIV;
				} else {
					$plaintext = '';
					$xor = $this->_encryptBlock($this->decryptIV);
					$start = 0;
				}

				for ($i = $start; $i < strlen($ciphertext); $i+=$block_size) {
					$block = substr($ciphertext, $i, $block_size);
					$plaintext.= $block ^ $xor;
					if ($continuousBuffer && strlen($block) != $block_size) {
						$buffer['ciphertext'].= $block;
						$block = $xor;
					} else if (strlen($block) == $block_size) {
						$xor = $this->_encryptBlock($block);
					}
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $block;
				}
				break;
			case CRYPT_RIJNDAEL_MODE_OFB:
				$xor = $this->decryptIV;
				if (strlen($buffer)) {
					for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
						$xor = $this->_encryptBlock($xor);
						$buffer.= $xor;
						$key = $this->_string_shift($buffer, $block_size);
						$plaintext.= substr($ciphertext, $i, $block_size) ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
						$xor = $this->_encryptBlock($xor);
						$plaintext.= substr($ciphertext, $i, $block_size) ^ $xor;
					}
					$key = $xor;
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
					if ($start = strlen($ciphertext) % $block_size) {
						 $buffer = substr($key, $start) . $buffer;
					}
				}
		}

		return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
	}

	function _encryptBlock($in)
	{
		$state = array();
		$words = unpack('N*word', $in);

		$w = $this->w;
		$t0 = $this->t0;
		$t1 = $this->t1;
		$t2 = $this->t2;
		$t3 = $this->t3;
		$Nb = $this->Nb;
		$Nr = $this->Nr;
		$c = $this->c;

				$i = 0;
		foreach ($words as $word) {
			$state[] = $word ^ $w[0][$i++];
		}


				$temp = array();
		for ($round = 1; $round < $Nr; $round++) {
			$i = 0;             $j = $c[1];
			$k = $c[2];
			$l = $c[3];

			while ($i < $this->Nb) {
				$temp[$i] = $t0[$state[$i] & 0xFF000000] ^
							$t1[$state[$j] & 0x00FF0000] ^
							$t2[$state[$k] & 0x0000FF00] ^
							$t3[$state[$l] & 0x000000FF] ^
							$w[$round][$i];
				$i++;
				$j = ($j + 1) % $Nb;
				$k = ($k + 1) % $Nb;
				$l = ($l + 1) % $Nb;
			}

			for ($i = 0; $i < $Nb; $i++) {
				$state[$i] = $temp[$i];
			}
		}

				for ($i = 0; $i < $Nb; $i++) {
			$state[$i] = $this->_subWord($state[$i]);
		}

				$i = 0;         $j = $c[1];
		$k = $c[2];
		$l = $c[3];
		while ($i < $this->Nb) {
			$temp[$i] = ($state[$i] & 0xFF000000) ^
						($state[$j] & 0x00FF0000) ^
						($state[$k] & 0x0000FF00) ^
						($state[$l] & 0x000000FF) ^
						 $w[$Nr][$i];
			$i++;
			$j = ($j + 1) % $Nb;
			$k = ($k + 1) % $Nb;
			$l = ($l + 1) % $Nb;
		}
		$state = $temp;

		array_unshift($state, 'N*');

		return call_user_func_array('pack', $state);
	}

	function _decryptBlock($in)
	{
		$state = array();
		$words = unpack('N*word', $in);

		$num_states = count($state);
		$dw = $this->dw;
		$dt0 = $this->dt0;
		$dt1 = $this->dt1;
		$dt2 = $this->dt2;
		$dt3 = $this->dt3;
		$Nb = $this->Nb;
		$Nr = $this->Nr;
		$c = $this->c;

				$i = 0;
		foreach ($words as $word) {
			$state[] = $word ^ $dw[$Nr][$i++];
		}

		$temp = array();
		for ($round = $Nr - 1; $round > 0; $round--) {
			$i = 0;             $j = $Nb - $c[1];
			$k = $Nb - $c[2];
			$l = $Nb - $c[3];

			while ($i < $Nb) {
				$temp[$i] = $dt0[$state[$i] & 0xFF000000] ^
							$dt1[$state[$j] & 0x00FF0000] ^
							$dt2[$state[$k] & 0x0000FF00] ^
							$dt3[$state[$l] & 0x000000FF] ^
							$dw[$round][$i];
				$i++;
				$j = ($j + 1) % $Nb;
				$k = ($k + 1) % $Nb;
				$l = ($l + 1) % $Nb;
			}

			for ($i = 0; $i < $Nb; $i++) {
				$state[$i] = $temp[$i];
			}
		}

				$i = 0;         $j = $Nb - $c[1];
		$k = $Nb - $c[2];
		$l = $Nb - $c[3];

		while ($i < $Nb) {
			$temp[$i] = $dw[0][$i] ^
						$this->_invSubWord(($state[$i] & 0xFF000000) |
										   ($state[$j] & 0x00FF0000) |
										   ($state[$k] & 0x0000FF00) |
										   ($state[$l] & 0x000000FF));
			$i++;
			$j = ($j + 1) % $Nb;
			$k = ($k + 1) % $Nb;
			$l = ($l + 1) % $Nb;
		}

		$state = $temp;

		array_unshift($state, 'N*');

		return call_user_func_array('pack', $state);
	}

	function _setup()
	{
						static $rcon = array(0,
			0x01000000, 0x02000000, 0x04000000, 0x08000000, 0x10000000,
			0x20000000, 0x40000000, 0x80000000, 0x1B000000, 0x36000000,
			0x6C000000, 0xD8000000, 0xAB000000, 0x4D000000, 0x9A000000,
			0x2F000000, 0x5E000000, 0xBC000000, 0x63000000, 0xC6000000,
			0x97000000, 0x35000000, 0x6A000000, 0xD4000000, 0xB3000000,
			0x7D000000, 0xFA000000, 0xEF000000, 0xC5000000, 0x91000000
		);

		if (!$this->changed) {
			return;
		}

		if (!$this->explicit_key_length) {
						$length = strlen($this->key) >> 2;
			if ($length > 8) {
				$length = 8;
			} else if ($length < 4) {
				$length = 4;
			}
			$this->Nk = $length;
			$this->key_size = $length << 2;
		}

		$this->key = str_pad(substr($this->key, 0, $this->key_size), $this->key_size, chr(0));
		$this->encryptIV = $this->decryptIV = $this->iv = str_pad(substr($this->iv, 0, $this->block_size), $this->block_size, chr(0));

				$this->Nr = max($this->Nk, $this->Nb) + 6;

										switch ($this->Nb) {
			case 4:
			case 5:
			case 6:
				$this->c = array(0, 1, 2, 3);
				break;
			case 7:
				$this->c = array(0, 1, 2, 4);
				break;
			case 8:
				$this->c = array(0, 1, 3, 4);
		}

		$key = $this->key;

		$w = array_values(unpack('N*words', $key));

		$length = $this->Nb * ($this->Nr + 1);
		for ($i = $this->Nk; $i < $length; $i++) {
			$temp = $w[$i - 1];
			if ($i % $this->Nk == 0) {
				$temp = (($temp << 8) & 0xFFFFFF00) | (($temp >> 24) & 0x000000FF);                 $temp = $this->_subWord($temp) ^ $rcon[$i / $this->Nk];
			} else if ($this->Nk > 6 && $i % $this->Nk == 4) {
				$temp = $this->_subWord($temp);
			}
			$w[$i] = $w[$i - $this->Nk] ^ $temp;
		}

																$temp = array();
		for ($i = $row = $col = 0; $i < $length; $i++, $col++) {
			if ($col == $this->Nb) {
				if ($row == 0) {
					$this->dw[0] = $this->w[0];
				} else {
										$j = 0;
					while ($j < $this->Nb) {
						$dw = $this->_subWord($this->w[$row][$j]);
						$temp[$j] = $this->dt0[$dw & 0xFF000000] ^
									$this->dt1[$dw & 0x00FF0000] ^
									$this->dt2[$dw & 0x0000FF00] ^
									$this->dt3[$dw & 0x000000FF];
						$j++;
					}
					$this->dw[$row] = $temp;
				}

				$col = 0;
				$row++;
			}
			$this->w[$row][$col] = $w[$i];
		}

		$this->dw[$row] = $this->w[$row];

		$this->changed = false;
	}

	function _subWord($word)
	{
		static $sbox0, $sbox1, $sbox2, $sbox3;

		if (empty($sbox0)) {
			$sbox0 = array(
				0x63, 0x7C, 0x77, 0x7B, 0xF2, 0x6B, 0x6F, 0xC5, 0x30, 0x01, 0x67, 0x2B, 0xFE, 0xD7, 0xAB, 0x76,
				0xCA, 0x82, 0xC9, 0x7D, 0xFA, 0x59, 0x47, 0xF0, 0xAD, 0xD4, 0xA2, 0xAF, 0x9C, 0xA4, 0x72, 0xC0,
				0xB7, 0xFD, 0x93, 0x26, 0x36, 0x3F, 0xF7, 0xCC, 0x34, 0xA5, 0xE5, 0xF1, 0x71, 0xD8, 0x31, 0x15,
				0x04, 0xC7, 0x23, 0xC3, 0x18, 0x96, 0x05, 0x9A, 0x07, 0x12, 0x80, 0xE2, 0xEB, 0x27, 0xB2, 0x75,
				0x09, 0x83, 0x2C, 0x1A, 0x1B, 0x6E, 0x5A, 0xA0, 0x52, 0x3B, 0xD6, 0xB3, 0x29, 0xE3, 0x2F, 0x84,
				0x53, 0xD1, 0x00, 0xED, 0x20, 0xFC, 0xB1, 0x5B, 0x6A, 0xCB, 0xBE, 0x39, 0x4A, 0x4C, 0x58, 0xCF,
				0xD0, 0xEF, 0xAA, 0xFB, 0x43, 0x4D, 0x33, 0x85, 0x45, 0xF9, 0x02, 0x7F, 0x50, 0x3C, 0x9F, 0xA8,
				0x51, 0xA3, 0x40, 0x8F, 0x92, 0x9D, 0x38, 0xF5, 0xBC, 0xB6, 0xDA, 0x21, 0x10, 0xFF, 0xF3, 0xD2,
				0xCD, 0x0C, 0x13, 0xEC, 0x5F, 0x97, 0x44, 0x17, 0xC4, 0xA7, 0x7E, 0x3D, 0x64, 0x5D, 0x19, 0x73,
				0x60, 0x81, 0x4F, 0xDC, 0x22, 0x2A, 0x90, 0x88, 0x46, 0xEE, 0xB8, 0x14, 0xDE, 0x5E, 0x0B, 0xDB,
				0xE0, 0x32, 0x3A, 0x0A, 0x49, 0x06, 0x24, 0x5C, 0xC2, 0xD3, 0xAC, 0x62, 0x91, 0x95, 0xE4, 0x79,
				0xE7, 0xC8, 0x37, 0x6D, 0x8D, 0xD5, 0x4E, 0xA9, 0x6C, 0x56, 0xF4, 0xEA, 0x65, 0x7A, 0xAE, 0x08,
				0xBA, 0x78, 0x25, 0x2E, 0x1C, 0xA6, 0xB4, 0xC6, 0xE8, 0xDD, 0x74, 0x1F, 0x4B, 0xBD, 0x8B, 0x8A,
				0x70, 0x3E, 0xB5, 0x66, 0x48, 0x03, 0xF6, 0x0E, 0x61, 0x35, 0x57, 0xB9, 0x86, 0xC1, 0x1D, 0x9E,
				0xE1, 0xF8, 0x98, 0x11, 0x69, 0xD9, 0x8E, 0x94, 0x9B, 0x1E, 0x87, 0xE9, 0xCE, 0x55, 0x28, 0xDF,
				0x8C, 0xA1, 0x89, 0x0D, 0xBF, 0xE6, 0x42, 0x68, 0x41, 0x99, 0x2D, 0x0F, 0xB0, 0x54, 0xBB, 0x16
			);

			$sbox1 = array();
			$sbox2 = array();
			$sbox3 = array();

			for ($i = 0; $i < 256; $i++) {
				$sbox1[$i <<  8] = $sbox0[$i] <<  8;
				$sbox2[$i << 16] = $sbox0[$i] << 16;
				$sbox3[$i << 24] = $sbox0[$i] << 24;
			}
		}

		return $sbox0[$word & 0x000000FF] |
			   $sbox1[$word & 0x0000FF00] |
			   $sbox2[$word & 0x00FF0000] |
			   $sbox3[$word & 0xFF000000];
	}

	function _invSubWord($word)
	{
		static $sbox0, $sbox1, $sbox2, $sbox3;

		if (empty($sbox0)) {
			$sbox0 = array(
				0x52, 0x09, 0x6A, 0xD5, 0x30, 0x36, 0xA5, 0x38, 0xBF, 0x40, 0xA3, 0x9E, 0x81, 0xF3, 0xD7, 0xFB,
				0x7C, 0xE3, 0x39, 0x82, 0x9B, 0x2F, 0xFF, 0x87, 0x34, 0x8E, 0x43, 0x44, 0xC4, 0xDE, 0xE9, 0xCB,
				0x54, 0x7B, 0x94, 0x32, 0xA6, 0xC2, 0x23, 0x3D, 0xEE, 0x4C, 0x95, 0x0B, 0x42, 0xFA, 0xC3, 0x4E,
				0x08, 0x2E, 0xA1, 0x66, 0x28, 0xD9, 0x24, 0xB2, 0x76, 0x5B, 0xA2, 0x49, 0x6D, 0x8B, 0xD1, 0x25,
				0x72, 0xF8, 0xF6, 0x64, 0x86, 0x68, 0x98, 0x16, 0xD4, 0xA4, 0x5C, 0xCC, 0x5D, 0x65, 0xB6, 0x92,
				0x6C, 0x70, 0x48, 0x50, 0xFD, 0xED, 0xB9, 0xDA, 0x5E, 0x15, 0x46, 0x57, 0xA7, 0x8D, 0x9D, 0x84,
				0x90, 0xD8, 0xAB, 0x00, 0x8C, 0xBC, 0xD3, 0x0A, 0xF7, 0xE4, 0x58, 0x05, 0xB8, 0xB3, 0x45, 0x06,
				0xD0, 0x2C, 0x1E, 0x8F, 0xCA, 0x3F, 0x0F, 0x02, 0xC1, 0xAF, 0xBD, 0x03, 0x01, 0x13, 0x8A, 0x6B,
				0x3A, 0x91, 0x11, 0x41, 0x4F, 0x67, 0xDC, 0xEA, 0x97, 0xF2, 0xCF, 0xCE, 0xF0, 0xB4, 0xE6, 0x73,
				0x96, 0xAC, 0x74, 0x22, 0xE7, 0xAD, 0x35, 0x85, 0xE2, 0xF9, 0x37, 0xE8, 0x1C, 0x75, 0xDF, 0x6E,
				0x47, 0xF1, 0x1A, 0x71, 0x1D, 0x29, 0xC5, 0x89, 0x6F, 0xB7, 0x62, 0x0E, 0xAA, 0x18, 0xBE, 0x1B,
				0xFC, 0x56, 0x3E, 0x4B, 0xC6, 0xD2, 0x79, 0x20, 0x9A, 0xDB, 0xC0, 0xFE, 0x78, 0xCD, 0x5A, 0xF4,
				0x1F, 0xDD, 0xA8, 0x33, 0x88, 0x07, 0xC7, 0x31, 0xB1, 0x12, 0x10, 0x59, 0x27, 0x80, 0xEC, 0x5F,
				0x60, 0x51, 0x7F, 0xA9, 0x19, 0xB5, 0x4A, 0x0D, 0x2D, 0xE5, 0x7A, 0x9F, 0x93, 0xC9, 0x9C, 0xEF,
				0xA0, 0xE0, 0x3B, 0x4D, 0xAE, 0x2A, 0xF5, 0xB0, 0xC8, 0xEB, 0xBB, 0x3C, 0x83, 0x53, 0x99, 0x61,
				0x17, 0x2B, 0x04, 0x7E, 0xBA, 0x77, 0xD6, 0x26, 0xE1, 0x69, 0x14, 0x63, 0x55, 0x21, 0x0C, 0x7D
			);

			$sbox1 = array();
			$sbox2 = array();
			$sbox3 = array();

			for ($i = 0; $i < 256; $i++) {
				$sbox1[$i <<  8] = $sbox0[$i] <<  8;
				$sbox2[$i << 16] = $sbox0[$i] << 16;
				$sbox3[$i << 24] = $sbox0[$i] << 24;
			}
		}

		return $sbox0[$word & 0x000000FF] |
			   $sbox1[$word & 0x0000FF00] |
			   $sbox2[$word & 0x00FF0000] |
			   $sbox3[$word & 0xFF000000];
	}

	function enablePadding()
	{
		$this->padding = true;
	}

	function disablePadding()
	{
		$this->padding = false;
	}

	function _pad($text)
	{
		$length = strlen($text);

		if (!$this->padding) {
			if ($length % $this->block_size == 0) {
				return $text;
			} else {
				user_error("The plaintext's length ($length) is not a multiple of the block size ({$this->block_size})", E_USER_NOTICE);
				$this->padding = true;
			}
		}

		$pad = $this->block_size - ($length % $this->block_size);

		return str_pad($text, $length + $pad, chr($pad));
	}

	function _unpad($text)
	{
		if (!$this->padding) {
			return $text;
		}

		$length = ord($text[strlen($text) - 1]);

		if (!$length || $length > $this->block_size) {
			return false;
		}

		return substr($text, 0, -$length);
	}

	function enableContinuousBuffer()
	{
		$this->continuousBuffer = true;
	}

	function disableContinuousBuffer()
	{
		$this->continuousBuffer = false;
		$this->encryptIV = $this->iv;
		$this->decryptIV = $this->iv;
	}

	function _string_shift(&$string, $index = 1)
	{
		$substr = substr($string, 0, $index);
		$string = substr($string, $index);
		return $substr;
	}
}

class Crypt_RC4
{
	protected
		$key = "\0",
		$encryptStream = false,
		$decryptStream = false,
		$encryptIndex = 0,
		$decryptIndex = 0,
		$mcrypt = array('', ''),
		$mode,
		$continuousBuffer = false;

	function __construct()
	{
		if ( !defined('CRYPT_RC4_MODE') ) {
			switch (true) {
				case extension_loaded('mcrypt') && (defined('MCRYPT_ARCFOUR') || defined('MCRYPT_RC4')):
					define('CRYPT_RC4_MODE', CRYPT_RC4_MODE_MCRYPT);
					break;
				default:
					define('CRYPT_RC4_MODE', CRYPT_RC4_MODE_INTERNAL);
			}
		}

		switch ( CRYPT_RC4_MODE ) {
			case CRYPT_RC4_MODE_MCRYPT:
				switch (true) {
					case defined('MCRYPT_ARCFOUR'):
						$this->mode = MCRYPT_ARCFOUR;
						break;
					case defined('MCRYPT_RC4');
						$this->mode = MCRYPT_RC4;
				}
		}
	}

	function setKey($key)
	{
		$this->key = $key;

		if ( CRYPT_RC4_MODE == CRYPT_RC4_MODE_MCRYPT ) {
			return;
		}

		$keyLength = strlen($key);
		$keyStream = array();
		for ($i = 0; $i < 256; $i++) {
			$keyStream[$i] = $i;
		}
		$j = 0;
		for ($i = 0; $i < 256; $i++) {
			$j = ($j + $keyStream[$i] + ord($key[$i % $keyLength])) & 255;
			$temp = $keyStream[$i];
			$keyStream[$i] = $keyStream[$j];
			$keyStream[$j] = $temp;
		}

		$this->encryptIndex = $this->decryptIndex = array(0, 0);
		$this->encryptStream = $this->decryptStream = $keyStream;
	}

	function setIV($iv)
	{
	}

	function setMCrypt($algorithm_directory = '', $mode_directory = '')
	{
		if ( CRYPT_RC4_MODE == CRYPT_RC4_MODE_MCRYPT ) {
			$this->mcrypt = array($algorithm_directory, $mode_directory);
			$this->_closeMCrypt();
		}
	}

	function encrypt($plaintext)
	{
		return $this->_crypt($plaintext, CRYPT_RC4_ENCRYPT);
	}

	function decrypt($ciphertext)
	{
		return $this->_crypt($ciphertext, CRYPT_RC4_DECRYPT);
	}

	function _crypt($text, $mode)
	{
		if ( CRYPT_RC4_MODE == CRYPT_RC4_MODE_MCRYPT ) {
			$keyStream = $mode == CRYPT_RC4_ENCRYPT ? 'encryptStream' : 'decryptStream';

			if ($this->$keyStream === false) {
				$this->$keyStream = mcrypt_module_open($this->mode, $this->mcrypt[0], MCRYPT_MODE_STREAM, $this->mcrypt[1]);
				mcrypt_generic_init($this->$keyStream, $this->key, '');
			} else if (!$this->continuousBuffer) {
				mcrypt_generic_init($this->$keyStream, $this->key, '');
			}
			$newText = mcrypt_generic($this->$keyStream, $text);
			if (!$this->continuousBuffer) {
				mcrypt_generic_deinit($this->$keyStream);
			}

			return $newText;
		}

		if ($this->encryptStream === false) {
			$this->setKey($this->key);
		}

		switch ($mode) {
			case CRYPT_RC4_ENCRYPT:
				$keyStream = $this->encryptStream;
				list($i, $j) = $this->encryptIndex;
				break;
			case CRYPT_RC4_DECRYPT:
				$keyStream = $this->decryptStream;
				list($i, $j) = $this->decryptIndex;
		}

		$newText = '';
		for ($k = 0; $k < strlen($text); $k++) {
			$i = ($i + 1) & 255;
			$j = ($j + $keyStream[$i]) & 255;
			$temp = $keyStream[$i];
			$keyStream[$i] = $keyStream[$j];
			$keyStream[$j] = $temp;
			$temp = $keyStream[($keyStream[$i] + $keyStream[$j]) & 255];
			$newText.= chr(ord($text[$k]) ^ $temp);
		}

		if ($this->continuousBuffer) {
			switch ($mode) {
				case CRYPT_RC4_ENCRYPT:
					$this->encryptStream = $keyStream;
					$this->encryptIndex = array($i, $j);
					break;
				case CRYPT_RC4_DECRYPT:
					$this->decryptStream = $keyStream;
					$this->decryptIndex = array($i, $j);
			}
		}

		return $newText;
	}

	function enableContinuousBuffer()
	{
		$this->continuousBuffer = true;
	}

	function disableContinuousBuffer()
	{
		if ( CRYPT_RC4_MODE == CRYPT_RC4_MODE_INTERNAL ) {
			$this->encryptIndex = $this->decryptIndex = array(0, 0);
			$this->setKey($this->key);
		}

		$this->continuousBuffer = false;
	}

	function enablePadding()
	{
	}

	function disablePadding()
	{
	}

	function __destruct()
	{
		if ( CRYPT_RC4_MODE == CRYPT_RC4_MODE_MCRYPT ) {
			$this->_closeMCrypt();
		}
	}

	function _closeMCrypt()
	{
		if ( $this->encryptStream !== false ) {
			if ( $this->continuousBuffer ) {
				mcrypt_generic_deinit($this->encryptStream);
			}

			mcrypt_module_close($this->encryptStream);

			$this->encryptStream = false;
		}

		if ( $this->decryptStream !== false ) {
			if ( $this->continuousBuffer ) {
				mcrypt_generic_deinit($this->decryptStream);
			}

			mcrypt_module_close($this->decryptStream);

			$this->decryptStream = false;
		}
	}
}

class Crypt_DES
{
	protected
		$keys = "\0\0\0\0\0\0\0\0",
		$mode,
		$continuousBuffer = false,
		$padding = true,
		$iv = "\0\0\0\0\0\0\0\0",
		$encryptIV = "\0\0\0\0\0\0\0\0",
		$decryptIV = "\0\0\0\0\0\0\0\0",
		$enmcrypt,
		$demcrypt,
		$enchanged = true,
		$dechanged = true,
		$paddable = false,
		$enbuffer = '',
		$debuffer = '',
		$ecb;

	function __construct($mode = CRYPT_MODE_DES_CBC)
	{
		if ( !defined('CRYPT_DES_MODE') ) {
			switch (true) {
				case extension_loaded('mcrypt'):
					define('CRYPT_DES_MODE', CRYPT_DES_MODE_MCRYPT);
					break;
				default:
					define('CRYPT_DES_MODE', CRYPT_DES_MODE_INTERNAL);
			}
		}

		switch ( CRYPT_DES_MODE ) {
			case CRYPT_DES_MODE_MCRYPT:
				switch ($mode) {
					case CRYPT_DES_MODE_ECB:
						$this->paddable = true;
						$this->mode = MCRYPT_MODE_ECB;
						break;
					case CRYPT_DES_MODE_CTR:
						$this->mode = 'ctr';
												break;
					case CRYPT_DES_MODE_CFB:
						$this->mode = 'ncfb';
						break;
					case CRYPT_DES_MODE_OFB:
						$this->mode = MCRYPT_MODE_NOFB;
						break;
					case CRYPT_DES_MODE_CBC:
					default:
						$this->paddable = true;
						$this->mode = MCRYPT_MODE_CBC;
				}

				break;
			default:
				switch ($mode) {
					case CRYPT_DES_MODE_ECB:
					case CRYPT_DES_MODE_CBC:
						$this->paddable = true;
						$this->mode = $mode;
						break;
					case CRYPT_DES_MODE_CTR:
					case CRYPT_DES_MODE_CFB:
					case CRYPT_DES_MODE_OFB:
						$this->mode = $mode;
						break;
					default:
						$this->paddable = true;
						$this->mode = CRYPT_DES_MODE_CBC;
				}
		}
	}

	function setKey($key)
	{
		$this->keys = ( CRYPT_DES_MODE == CRYPT_DES_MODE_MCRYPT ) ? str_pad(substr($key, 0, 8), 8, chr(0)) : $this->_prepareKey($key);
		$this->changed = true;
	}

	function setIV($iv)
	{
		$this->encryptIV = $this->decryptIV = $this->iv = str_pad(substr($iv, 0, 8), 8, chr(0));
		$this->changed = true;
	}

	function _generate_xor($length, &$iv)
	{
		$xor = '';
		$num_blocks = ($length + 7) >> 3;
		for ($i = 0; $i < $num_blocks; $i++) {
			$xor.= $iv;
			for ($j = 4; $j <= 8; $j+=4) {
				$temp = substr($iv, -$j, 4);
				switch ($temp) {
					case "\xFF\xFF\xFF\xFF":
						$iv = substr_replace($iv, "\x00\x00\x00\x00", -$j, 4);
						break;
					case "\x7F\xFF\xFF\xFF":
						$iv = substr_replace($iv, "\x80\x00\x00\x00", -$j, 4);
						break 2;
					default:
						extract(unpack('Ncount', $temp));
						$iv = substr_replace($iv, pack('N', $count + 1), -$j, 4);
						break 2;
				}
			}
		}

		return $xor;
	}

	function encrypt($plaintext)
	{
		if ($this->paddable) {
			$plaintext = $this->_pad($plaintext);
		}

		if ( CRYPT_DES_MODE == CRYPT_DES_MODE_MCRYPT ) {
			if ($this->enchanged) {
				if (!isset($this->enmcrypt)) {
					$this->enmcrypt = mcrypt_module_open(MCRYPT_DES, '', $this->mode, '');
				}
				mcrypt_generic_init($this->enmcrypt, $this->keys, $this->encryptIV);
				if ($this->mode != 'ncfb') {
					$this->enchanged = false;
				}
			}

			if ($this->mode != 'ncfb') {
				$ciphertext = mcrypt_generic($this->enmcrypt, $plaintext);
			} else {
				if ($this->enchanged) {
					$this->ecb = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_ECB, '');
					mcrypt_generic_init($this->ecb, $this->keys, "\0\0\0\0\0\0\0\0");
					$this->enchanged = false;
				}

				if (strlen($this->enbuffer)) {
					$ciphertext = $plaintext ^ substr($this->encryptIV, strlen($this->enbuffer));
					$this->enbuffer.= $ciphertext;
					if (strlen($this->enbuffer) == 8) {
						$this->encryptIV = $this->enbuffer;
						$this->enbuffer = '';
						mcrypt_generic_init($this->enmcrypt, $this->keys, $this->encryptIV);
					}
					$plaintext = substr($plaintext, strlen($ciphertext));
				} else {
					$ciphertext = '';
				}

				$last_pos = strlen($plaintext) & 0xFFFFFFF8;
				$ciphertext.= $last_pos ? mcrypt_generic($this->enmcrypt, substr($plaintext, 0, $last_pos)) : '';

				if (strlen($plaintext) & 0x7) {
					if (strlen($ciphertext)) {
						$this->encryptIV = substr($ciphertext, -8);
					}
					$this->encryptIV = mcrypt_generic($this->ecb, $this->encryptIV);
					$this->enbuffer = substr($plaintext, $last_pos) ^ $this->encryptIV;
					$ciphertext.= $this->enbuffer;
				}
			}

			if (!$this->continuousBuffer) {
				mcrypt_generic_init($this->enmcrypt, $this->keys, $this->encryptIV);
			}

			return $ciphertext;
		}

		if (!is_array($this->keys)) {
			$this->keys = $this->_prepareKey("\0\0\0\0\0\0\0\0");
		}

		$buffer = &$this->enbuffer;
		$continuousBuffer = $this->continuousBuffer;
		$ciphertext = '';
		switch ($this->mode) {
			case CRYPT_DES_MODE_ECB:
				for ($i = 0; $i < strlen($plaintext); $i+=8) {
					$ciphertext.= $this->_processBlock(substr($plaintext, $i, 8), CRYPT_DES_ENCRYPT);
				}
				break;
			case CRYPT_DES_MODE_CBC:
				$xor = $this->encryptIV;
				for ($i = 0; $i < strlen($plaintext); $i+=8) {
					$block = substr($plaintext, $i, 8);
					$block = $this->_processBlock($block ^ $xor, CRYPT_DES_ENCRYPT);
					$xor = $block;
					$ciphertext.= $block;
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
				}
				break;
			case CRYPT_DES_MODE_CTR:
				$xor = $this->encryptIV;
				if (strlen($buffer)) {
					for ($i = 0; $i < strlen($plaintext); $i+=8) {
						$block = substr($plaintext, $i, 8);
						$buffer.= $this->_processBlock($this->_generate_xor(8, $xor), CRYPT_DES_ENCRYPT);
						$key = $this->_string_shift($buffer, 8);
						$ciphertext.= $block ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($plaintext); $i+=8) {
						$block = substr($plaintext, $i, 8);
						$key = $this->_processBlock($this->_generate_xor(8, $xor), CRYPT_DES_ENCRYPT);
						$ciphertext.= $block ^ $key;
					}
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
					if ($start = strlen($plaintext) & 7) {
						$buffer = substr($key, $start) . $buffer;
					}
				}
				break;
			case CRYPT_DES_MODE_CFB:
				if (!empty($buffer['xor'])) {
					$ciphertext = $plaintext ^ $buffer['xor'];
					$iv = $buffer['encrypted'] . $ciphertext;
					$start = strlen($ciphertext);
					$buffer['encrypted'].= $ciphertext;
					$buffer['xor'] = substr($buffer['xor'], strlen($ciphertext));
				} else {
					$ciphertext = '';
					$iv = $this->encryptIV;
					$start = 0;
				}

				for ($i = $start; $i < strlen($plaintext); $i+=8) {
					$block = substr($plaintext, $i, 8);
					$xor = $this->_processBlock($iv, CRYPT_DES_ENCRYPT);
					$iv = $block ^ $xor;
					if ($continuousBuffer && strlen($iv) != 8) {
						$buffer = array(
							'encrypted' => $iv,
							'xor' => substr($xor, strlen($iv))
						);
					}
					$ciphertext.= $iv;
				}

				if ($this->continuousBuffer) {
					$this->encryptIV = $iv;
				}
				break;
			case CRYPT_DES_MODE_OFB:
				$xor = $this->encryptIV;
				if (strlen($buffer)) {
					for ($i = 0; $i < strlen($plaintext); $i+=8) {
						$xor = $this->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$buffer.= $xor;
						$key = $this->_string_shift($buffer, 8);
						$ciphertext.= substr($plaintext, $i, 8) ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($plaintext); $i+=8) {
						$xor = $this->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$ciphertext.= substr($plaintext, $i, 8) ^ $xor;
					}
					$key = $xor;
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
					if ($start = strlen($plaintext) & 7) {
						 $buffer = substr($key, $start) . $buffer;
					}
				}
		}

		return $ciphertext;
	}

	function decrypt($ciphertext)
	{
		if ($this->paddable) {
			$ciphertext = str_pad($ciphertext, (strlen($ciphertext) + 7) & 0xFFFFFFF8, chr(0));
		}

		if ( CRYPT_DES_MODE == CRYPT_DES_MODE_MCRYPT ) {
			if ($this->dechanged) {
				if (!isset($this->demcrypt)) {
					$this->demcrypt = mcrypt_module_open(MCRYPT_DES, '', $this->mode, '');
				}
				mcrypt_generic_init($this->demcrypt, $this->keys, $this->decryptIV);
				if ($this->mode != 'ncfb') {
					$this->dechanged = false;
				}
			}

			if ($this->mode != 'ncfb') {
				$plaintext = mdecrypt_generic($this->demcrypt, $ciphertext);
			} else {
				if ($this->dechanged) {
					$this->ecb = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_ECB, '');
					mcrypt_generic_init($this->ecb, $this->keys, "\0\0\0\0\0\0\0\0");
					$this->dechanged = false;
				}

				if (strlen($this->debuffer)) {
					$plaintext = $ciphertext ^ substr($this->decryptIV, strlen($this->debuffer));

					$this->debuffer.= substr($ciphertext, 0, strlen($plaintext));
					if (strlen($this->debuffer) == 8) {
						$this->decryptIV = $this->debuffer;
						$this->debuffer = '';
						mcrypt_generic_init($this->demcrypt, $this->keys, $this->decryptIV);
					}
					$ciphertext = substr($ciphertext, strlen($plaintext));
				} else {
					$plaintext = '';
				}

				$last_pos = strlen($ciphertext) & 0xFFFFFFF8;
				$plaintext.= $last_pos ? mdecrypt_generic($this->demcrypt, substr($ciphertext, 0, $last_pos)) : '';

				if (strlen($ciphertext) & 0x7) {
					if (strlen($plaintext)) {
						$this->decryptIV = substr($ciphertext, $last_pos - 8, 8);
					}
					$this->decryptIV = mcrypt_generic($this->ecb, $this->decryptIV);
					$this->debuffer = substr($ciphertext, $last_pos);
					$plaintext.= $this->debuffer ^ $this->decryptIV;
				}

				return $plaintext;
			}

			if (!$this->continuousBuffer) {
				mcrypt_generic_init($this->demcrypt, $this->keys, $this->decryptIV);
			}

			return $this->mode != 'ctr' ? $this->_unpad($plaintext) : $plaintext;
		}

		if (!is_array($this->keys)) {
			$this->keys = $this->_prepareKey("\0\0\0\0\0\0\0\0");
		}

		$buffer = &$this->debuffer;
		$continuousBuffer = $this->continuousBuffer;
		$plaintext = '';
		switch ($this->mode) {
			case CRYPT_DES_MODE_ECB:
				for ($i = 0; $i < strlen($ciphertext); $i+=8) {
					$plaintext.= $this->_processBlock(substr($ciphertext, $i, 8), CRYPT_DES_DECRYPT);
				}
				break;
			case CRYPT_DES_MODE_CBC:
				$xor = $this->decryptIV;
				for ($i = 0; $i < strlen($ciphertext); $i+=8) {
					$block = substr($ciphertext, $i, 8);
					$plaintext.= $this->_processBlock($block, CRYPT_DES_DECRYPT) ^ $xor;
					$xor = $block;
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
				}
				break;
			case CRYPT_DES_MODE_CTR:
				$xor = $this->decryptIV;
				if (strlen($buffer)) {
					for ($i = 0; $i < strlen($ciphertext); $i+=8) {
						$block = substr($ciphertext, $i, 8);
						$buffer.= $this->_processBlock($this->_generate_xor(8, $xor), CRYPT_DES_ENCRYPT);
						$key = $this->_string_shift($buffer, 8);
						$plaintext.= $block ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($ciphertext); $i+=8) {
						$block = substr($ciphertext, $i, 8);
						$key = $this->_processBlock($this->_generate_xor(8, $xor), CRYPT_DES_ENCRYPT);
						$plaintext.= $block ^ $key;
					}
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
					if ($start = strlen($ciphertext) % 8) {
						$buffer = substr($key, $start) . $buffer;
					}
				}
				break;
			case CRYPT_DES_MODE_CFB:
				if (!empty($buffer['ciphertext'])) {
					$plaintext = $ciphertext ^ substr($this->decryptIV, strlen($buffer['ciphertext']));
					$buffer['ciphertext'].= substr($ciphertext, 0, strlen($plaintext));
					if (strlen($buffer['ciphertext']) == 8) {
						$xor = $this->_processBlock($buffer['ciphertext'], CRYPT_DES_ENCRYPT);
						$buffer['ciphertext'] = '';
					}
					$start = strlen($plaintext);
					$block = $this->decryptIV;
				} else {
					$plaintext = '';
					$xor = $this->_processBlock($this->decryptIV, CRYPT_DES_ENCRYPT);
					$start = 0;
				}

				for ($i = $start; $i < strlen($ciphertext); $i+=8) {
					$block = substr($ciphertext, $i, 8);
					$plaintext.= $block ^ $xor;
					if ($continuousBuffer && strlen($block) != 8) {
						$buffer['ciphertext'].= $block;
						$block = $xor;
					} else if (strlen($block) == 8) {
						$xor = $this->_processBlock($block, CRYPT_DES_ENCRYPT);
					}
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $block;
				}
				break;
			case CRYPT_DES_MODE_OFB:
				$xor = $this->decryptIV;
				if (strlen($buffer)) {
					for ($i = 0; $i < strlen($ciphertext); $i+=8) {
						$xor = $this->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$buffer.= $xor;
						$key = $this->_string_shift($buffer, 8);
						$plaintext.= substr($ciphertext, $i, 8) ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($ciphertext); $i+=8) {
						$xor = $this->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$plaintext.= substr($ciphertext, $i, 8) ^ $xor;
					}
					$key = $xor;
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
					if ($start = strlen($ciphertext) % 8) {
						 $buffer = substr($key, $start) . $buffer;
					}
				}
		}

		return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
	}

	function enableContinuousBuffer()
	{
		$this->continuousBuffer = true;
	}

	function disableContinuousBuffer()
	{
		$this->continuousBuffer = false;
		$this->encryptIV = $this->iv;
		$this->decryptIV = $this->iv;
	}

	function enablePadding()
	{
		$this->padding = true;
	}

	function disablePadding()
	{
		$this->padding = false;
	}

	function _pad($text)
	{
		$length = strlen($text);

		if (!$this->padding) {
			if (($length & 7) == 0) {
				return $text;
			} else {
				user_error("The plaintext's length ($length) is not a multiple of the block size (8)", E_USER_NOTICE);
				$this->padding = true;
			}
		}

		$pad = 8 - ($length & 7);
		return str_pad($text, $length + $pad, chr($pad));
	}

	function _unpad($text)
	{
		if (!$this->padding) {
			return $text;
		}

		$length = ord($text[strlen($text) - 1]);

		if (!$length || $length > 8) {
			return false;
		}

		return substr($text, 0, -$length);
	}

	function _processBlock($block, $mode)
	{
		static $sbox = array(
			array(
				14,  0,  4, 15, 13,  7,  1,  4,  2, 14, 15,  2, 11, 13,  8,  1,
				 3, 10 ,10,  6,  6, 12, 12, 11,  5,  9,  9,  5,  0,  3,  7,  8,
				 4, 15,  1, 12, 14,  8,  8,  2, 13,  4,  6,  9,  2,  1, 11,  7,
				15,  5, 12, 11,  9,  3,  7, 14,  3, 10, 10,  0,  5,  6,  0, 13
			),
			array(
				15,  3,  1, 13,  8,  4, 14,  7,  6, 15, 11,  2,  3,  8,  4, 14,
				 9, 12,  7,  0,  2,  1, 13, 10, 12,  6,  0,  9,  5, 11, 10,  5,
				 0, 13, 14,  8,  7, 10, 11,  1, 10,  3,  4, 15, 13,  4,  1,  2,
				 5, 11,  8,  6, 12,  7,  6, 12,  9,  0,  3,  5,  2, 14, 15,  9
			),
			array(
				10, 13,  0,  7,  9,  0, 14,  9,  6,  3,  3,  4, 15,  6,  5, 10,
				 1,  2, 13,  8, 12,  5,  7, 14, 11, 12,  4, 11,  2, 15,  8,  1,
				13,  1,  6, 10,  4, 13,  9,  0,  8,  6, 15,  9,  3,  8,  0,  7,
				11,  4,  1, 15,  2, 14, 12,  3,  5, 11, 10,  5, 14,  2,  7, 12
			),
			array(
				 7, 13, 13,  8, 14, 11,  3,  5,  0,  6,  6, 15,  9,  0, 10,  3,
				 1,  4,  2,  7,  8,  2,  5, 12, 11,  1, 12, 10,  4, 14, 15,  9,
				10,  3,  6, 15,  9,  0,  0,  6, 12, 10, 11,  1,  7, 13, 13,  8,
				15,  9,  1,  4,  3,  5, 14, 11,  5, 12,  2,  7,  8,  2,  4, 14
			),
			array(
				 2, 14, 12, 11,  4,  2,  1, 12,  7,  4, 10,  7, 11, 13,  6,  1,
				 8,  5,  5,  0,  3, 15, 15, 10, 13,  3,  0,  9, 14,  8,  9,  6,
				 4, 11,  2,  8,  1, 12, 11,  7, 10,  1, 13, 14,  7,  2,  8, 13,
				15,  6,  9, 15, 12,  0,  5,  9,  6, 10,  3,  4,  0,  5, 14,  3
			),
			array(
				12, 10,  1, 15, 10,  4, 15,  2,  9,  7,  2, 12,  6,  9,  8,  5,
				 0,  6, 13,  1,  3, 13,  4, 14, 14,  0,  7, 11,  5,  3, 11,  8,
				 9,  4, 14,  3, 15,  2,  5, 12,  2,  9,  8,  5, 12, 15,  3, 10,
				 7, 11,  0, 14,  4,  1, 10,  7,  1,  6, 13,  0, 11,  8,  6, 13
			),
			array(
				 4, 13, 11,  0,  2, 11, 14,  7, 15,  4,  0,  9,  8,  1, 13, 10,
				 3, 14, 12,  3,  9,  5,  7, 12,  5,  2, 10, 15,  6,  8,  1,  6,
				 1,  6,  4, 11, 11, 13, 13,  8, 12,  1,  3,  4,  7, 10, 14,  7,
				10,  9, 15,  5,  6,  0,  8, 15,  0, 14,  5,  2,  9,  3,  2, 12
			),
			array(
				13,  1,  2, 15,  8, 13,  4,  8,  6, 10, 15,  3, 11,  7,  1,  4,
				10, 12,  9,  5,  3,  6, 14, 11,  5,  0,  0, 14, 12,  9,  7,  2,
				 7,  2, 11,  1,  4, 14,  1,  7,  9,  4, 12, 10, 14,  8,  2, 13,
				 0, 15,  6, 12, 10,  9, 13,  0, 15,  3,  3,  5,  5,  6,  8, 11
			)
		);

		$keys = $this->keys;

		$temp = unpack('Na/Nb', $block);
		$block = array($temp['a'], $temp['b']);

		$msb = array(
			($block[0] >> 31) & 1,
			($block[1] >> 31) & 1
		);
		$block[0] &= 0x7FFFFFFF;
		$block[1] &= 0x7FFFFFFF;

		$block = array(
			(($block[1] & 0x00000040) << 25) | (($block[1] & 0x00004000) << 16) |
			(($block[1] & 0x00400001) <<  7) | (($block[1] & 0x40000100) >>  2) |
			(($block[0] & 0x00000040) << 21) | (($block[0] & 0x00004000) << 12) |
			(($block[0] & 0x00400001) <<  3) | (($block[0] & 0x40000100) >>  6) |
			(($block[1] & 0x00000010) << 19) | (($block[1] & 0x00001000) << 10) |
			(($block[1] & 0x00100000) <<  1) | (($block[1] & 0x10000000) >>  8) |
			(($block[0] & 0x00000010) << 15) | (($block[0] & 0x00001000) <<  6) |
			(($block[0] & 0x00100000) >>  3) | (($block[0] & 0x10000000) >> 12) |
			(($block[1] & 0x00000004) << 13) | (($block[1] & 0x00000400) <<  4) |
			(($block[1] & 0x00040000) >>  5) | (($block[1] & 0x04000000) >> 14) |
			(($block[0] & 0x00000004) <<  9) | ( $block[0] & 0x00000400       ) |
			(($block[0] & 0x00040000) >>  9) | (($block[0] & 0x04000000) >> 18) |
			(($block[1] & 0x00010000) >> 11) | (($block[1] & 0x01000000) >> 20) |
			(($block[0] & 0x00010000) >> 15) | (($block[0] & 0x01000000) >> 24)
		,
			(($block[1] & 0x00000080) << 24) | (($block[1] & 0x00008000) << 15) |
			(($block[1] & 0x00800002) <<  6) | (($block[0] & 0x00000080) << 20) |
			(($block[0] & 0x00008000) << 11) | (($block[0] & 0x00800002) <<  2) |
			(($block[1] & 0x00000020) << 18) | (($block[1] & 0x00002000) <<  9) |
			( $block[1] & 0x00200000       ) | (($block[1] & 0x20000000) >>  9) |
			(($block[0] & 0x00000020) << 14) | (($block[0] & 0x00002000) <<  5) |
			(($block[0] & 0x00200000) >>  4) | (($block[0] & 0x20000000) >> 13) |
			(($block[1] & 0x00000008) << 12) | (($block[1] & 0x00000800) <<  3) |
			(($block[1] & 0x00080000) >>  6) | (($block[1] & 0x08000000) >> 15) |
			(($block[0] & 0x00000008) <<  8) | (($block[0] & 0x00000800) >>  1) |
			(($block[0] & 0x00080000) >> 10) | (($block[0] & 0x08000000) >> 19) |
			(($block[1] & 0x00000200) >>  3) | (($block[0] & 0x00000200) >>  7) |
			(($block[1] & 0x00020000) >> 12) | (($block[1] & 0x02000000) >> 21) |
			(($block[0] & 0x00020000) >> 16) | (($block[0] & 0x02000000) >> 25) |
			($msb[1] << 28) | ($msb[0] << 24)
		);

		for ($i = 0; $i < 16; $i++) {
			$temp = (($sbox[0][((($block[1] >> 27) & 0x1F) | (($block[1] & 1) << 5)) ^ $keys[$mode][$i][0]]) << 28)
				  | (($sbox[1][(($block[1] & 0x1F800000) >> 23) ^ $keys[$mode][$i][1]]) << 24)
				  | (($sbox[2][(($block[1] & 0x01F80000) >> 19) ^ $keys[$mode][$i][2]]) << 20)
				  | (($sbox[3][(($block[1] & 0x001F8000) >> 15) ^ $keys[$mode][$i][3]]) << 16)
				  | (($sbox[4][(($block[1] & 0x0001F800) >> 11) ^ $keys[$mode][$i][4]]) << 12)
				  | (($sbox[5][(($block[1] & 0x00001F80) >>  7) ^ $keys[$mode][$i][5]]) <<  8)
				  | (($sbox[6][(($block[1] & 0x000001F8) >>  3) ^ $keys[$mode][$i][6]]) <<  4)
				  | ( $sbox[7][((($block[1] & 0x1F) << 1) | (($block[1] >> 31) & 1)) ^ $keys[$mode][$i][7]]);

			$msb = ($temp >> 31) & 1;
			$temp &= 0x7FFFFFFF;
			$newBlock = (($temp & 0x00010000) << 15) | (($temp & 0x02020120) <<  5)
					  | (($temp & 0x00001800) << 17) | (($temp & 0x01000000) >> 10)
					  | (($temp & 0x00000008) << 24) | (($temp & 0x00100000) <<  6)
					  | (($temp & 0x00000010) << 21) | (($temp & 0x00008000) <<  9)
					  | (($temp & 0x00000200) << 12) | (($temp & 0x10000000) >> 27)
					  | (($temp & 0x00000040) << 14) | (($temp & 0x08000000) >>  8)
					  | (($temp & 0x00004000) <<  4) | (($temp & 0x00000002) << 16)
					  | (($temp & 0x00442000) >>  6) | (($temp & 0x40800000) >> 15)
					  | (($temp & 0x00000001) << 11) | (($temp & 0x20000000) >> 20)
					  | (($temp & 0x00080000) >> 13) | (($temp & 0x00000004) <<  3)
					  | (($temp & 0x04000000) >> 22) | (($temp & 0x00000480) >>  7)
					  | (($temp & 0x00200000) >> 19) | ($msb << 23);

			$temp = $block[1];
			$block[1] = $block[0] ^ $newBlock;
			$block[0] = $temp;
		}

		$msb = array(
			($block[0] >> 31) & 1,
			($block[1] >> 31) & 1
		);
		$block[0] &= 0x7FFFFFFF;
		$block[1] &= 0x7FFFFFFF;

		$block = array(
			(($block[0] & 0x01000004) <<  7) | (($block[1] & 0x01000004) <<  6) |
			(($block[0] & 0x00010000) << 13) | (($block[1] & 0x00010000) << 12) |
			(($block[0] & 0x00000100) << 19) | (($block[1] & 0x00000100) << 18) |
			(($block[0] & 0x00000001) << 25) | (($block[1] & 0x00000001) << 24) |
			(($block[0] & 0x02000008) >>  2) | (($block[1] & 0x02000008) >>  3) |
			(($block[0] & 0x00020000) <<  4) | (($block[1] & 0x00020000) <<  3) |
			(($block[0] & 0x00000200) << 10) | (($block[1] & 0x00000200) <<  9) |
			(($block[0] & 0x00000002) << 16) | (($block[1] & 0x00000002) << 15) |
			(($block[0] & 0x04000000) >> 11) | (($block[1] & 0x04000000) >> 12) |
			(($block[0] & 0x00040000) >>  5) | (($block[1] & 0x00040000) >>  6) |
			(($block[0] & 0x00000400) <<  1) | ( $block[1] & 0x00000400       ) |
			(($block[0] & 0x08000000) >> 20) | (($block[1] & 0x08000000) >> 21) |
			(($block[0] & 0x00080000) >> 14) | (($block[1] & 0x00080000) >> 15) |
			(($block[0] & 0x00000800) >>  8) | (($block[1] & 0x00000800) >>  9)
		,
			(($block[0] & 0x10000040) <<  3) | (($block[1] & 0x10000040) <<  2) |
			(($block[0] & 0x00100000) <<  9) | (($block[1] & 0x00100000) <<  8) |
			(($block[0] & 0x00001000) << 15) | (($block[1] & 0x00001000) << 14) |
			(($block[0] & 0x00000010) << 21) | (($block[1] & 0x00000010) << 20) |
			(($block[0] & 0x20000080) >>  6) | (($block[1] & 0x20000080) >>  7) |
			( $block[0] & 0x00200000       ) | (($block[1] & 0x00200000) >>  1) |
			(($block[0] & 0x00002000) <<  6) | (($block[1] & 0x00002000) <<  5) |
			(($block[0] & 0x00000020) << 12) | (($block[1] & 0x00000020) << 11) |
			(($block[0] & 0x40000000) >> 15) | (($block[1] & 0x40000000) >> 16) |
			(($block[0] & 0x00400000) >>  9) | (($block[1] & 0x00400000) >> 10) |
			(($block[0] & 0x00004000) >>  3) | (($block[1] & 0x00004000) >>  4) |
			(($block[0] & 0x00800000) >> 18) | (($block[1] & 0x00800000) >> 19) |
			(($block[0] & 0x00008000) >> 12) | (($block[1] & 0x00008000) >> 13) |
			($msb[0] <<  7) | ($msb[1] <<  6)
		);

		return pack('NN', $block[0], $block[1]);
	}

	function _prepareKey($key)
	{
		static $shifts = array(
			1, 1, 2, 2, 2, 2, 2, 2, 1, 2, 2, 2, 2, 2, 2, 1
		);

		$key = str_pad(substr($key, 0, 8), 8, chr(0));

		$temp = unpack('Na/Nb', $key);
		$key = array($temp['a'], $temp['b']);
		$msb = array(
			($key[0] >> 31) & 1,
			($key[1] >> 31) & 1
		);
		$key[0] &= 0x7FFFFFFF;
		$key[1] &= 0x7FFFFFFF;

		$key = array(
			(($key[1] & 0x00000002) << 26) | (($key[1] & 0x00000204) << 17) |
			(($key[1] & 0x00020408) <<  8) | (($key[1] & 0x02040800) >>  1) |
			(($key[0] & 0x00000002) << 22) | (($key[0] & 0x00000204) << 13) |
			(($key[0] & 0x00020408) <<  4) | (($key[0] & 0x02040800) >>  5) |
			(($key[1] & 0x04080000) >> 10) | (($key[0] & 0x04080000) >> 14) |
			(($key[1] & 0x08000000) >> 19) | (($key[0] & 0x08000000) >> 23) |
			(($key[0] & 0x00000010) >>  1) | (($key[0] & 0x00001000) >> 10) |
			(($key[0] & 0x00100000) >> 19) | (($key[0] & 0x10000000) >> 28)
		,
			(($key[1] & 0x00000080) << 20) | (($key[1] & 0x00008000) << 11) |
			(($key[1] & 0x00800000) <<  2) | (($key[0] & 0x00000080) << 16) |
			(($key[0] & 0x00008000) <<  7) | (($key[0] & 0x00800000) >>  2) |
			(($key[1] & 0x00000040) << 13) | (($key[1] & 0x00004000) <<  4) |
			(($key[1] & 0x00400000) >>  5) | (($key[1] & 0x40000000) >> 14) |
			(($key[0] & 0x00000040) <<  9) | ( $key[0] & 0x00004000       ) |
			(($key[0] & 0x00400000) >>  9) | (($key[0] & 0x40000000) >> 18) |
			(($key[1] & 0x00000020) <<  6) | (($key[1] & 0x00002000) >>  3) |
			(($key[1] & 0x00200000) >> 12) | (($key[1] & 0x20000000) >> 21) |
			(($key[0] & 0x00000020) <<  2) | (($key[0] & 0x00002000) >>  7) |
			(($key[0] & 0x00200000) >> 16) | (($key[0] & 0x20000000) >> 25) |
			(($key[1] & 0x00000010) >>  1) | (($key[1] & 0x00001000) >> 10) |
			(($key[1] & 0x00100000) >> 19) | (($key[1] & 0x10000000) >> 28) |
			($msb[1] << 24) | ($msb[0] << 20)
		);

		$keys = array();
		for ($i = 0; $i < 16; $i++) {
			$key[0] <<= $shifts[$i];
			$temp = ($key[0] & 0xF0000000) >> 28;
			$key[0] = ($key[0] | $temp) & 0x0FFFFFFF;

			$key[1] <<= $shifts[$i];
			$temp = ($key[1] & 0xF0000000) >> 28;
			$key[1] = ($key[1] | $temp) & 0x0FFFFFFF;

			$temp = array(
				(($key[1] & 0x00004000) >>  9) | (($key[1] & 0x00000800) >>  7) |
				(($key[1] & 0x00020000) >> 14) | (($key[1] & 0x00000010) >>  2) |
				(($key[1] & 0x08000000) >> 26) | (($key[1] & 0x00800000) >> 23)
			,
				(($key[1] & 0x02400000) >> 20) | (($key[1] & 0x00000001) <<  4) |
				(($key[1] & 0x00002000) >> 10) | (($key[1] & 0x00040000) >> 18) |
				(($key[1] & 0x00000080) >>  6)
			,
				( $key[1] & 0x00000020       ) | (($key[1] & 0x00000200) >>  5) |
				(($key[1] & 0x00010000) >> 13) | (($key[1] & 0x01000000) >> 22) |
				(($key[1] & 0x00000004) >>  1) | (($key[1] & 0x00100000) >> 20)
			,
				(($key[1] & 0x00001000) >>  7) | (($key[1] & 0x00200000) >> 17) |
				(($key[1] & 0x00000002) <<  2) | (($key[1] & 0x00000100) >>  6) |
				(($key[1] & 0x00008000) >> 14) | (($key[1] & 0x04000000) >> 26)
			,
				(($key[0] & 0x00008000) >> 10) | ( $key[0] & 0x00000010       ) |
				(($key[0] & 0x02000000) >> 22) | (($key[0] & 0x00080000) >> 17) |
				(($key[0] & 0x00000200) >>  8) | (($key[0] & 0x00000002) >>  1)
			,
				(($key[0] & 0x04000000) >> 21) | (($key[0] & 0x00010000) >> 12) |
				(($key[0] & 0x00000020) >>  2) | (($key[0] & 0x00000800) >>  9) |
				(($key[0] & 0x00800000) >> 22) | (($key[0] & 0x00000100) >>  8)
			,
				(($key[0] & 0x00001000) >>  7) | (($key[0] & 0x00000088) >>  3) |
				(($key[0] & 0x00020000) >> 14) | (($key[0] & 0x00000001) <<  2) |
				(($key[0] & 0x00400000) >> 21)
			,
				(($key[0] & 0x00000400) >>  5) | (($key[0] & 0x00004000) >> 10) |
				(($key[0] & 0x00000040) >>  3) | (($key[0] & 0x00100000) >> 18) |
				(($key[0] & 0x08000000) >> 26) | (($key[0] & 0x01000000) >> 24)
			);

			$keys[] = $temp;
		}

		$temp = array(
			CRYPT_DES_ENCRYPT => $keys,
			CRYPT_DES_DECRYPT => array_reverse($keys)
		);

		return $temp;
	}

	function _string_shift(&$string, $index = 1)
	{
		$substr = substr($string, 0, $index);
		$string = substr($string, $index);
		return $substr;
	}
}

class Crypt_TripleDES
{
	protected
		$key = "\0\0\0\0\0\0\0\0",
		$mode = CRYPT_DES_MODE_CBC,
		$continuousBuffer = false,
		$padding = true,
		$iv = "\0\0\0\0\0\0\0\0",
		$encryptIV = "\0\0\0\0\0\0\0\0",
		$decryptIV = "\0\0\0\0\0\0\0\0",
		$des,
		$enmcrypt,
		$demcrypt,
		$enchanged = true,
		$dechanged = true,
		$paddable = false,
		$enbuffer = '',
		$debuffer = '',
		$ecb;

	function __construct($mode = CRYPT_DES_MODE_CBC)
	{
		if ( !defined('CRYPT_DES_MODE') ) {
			switch (true) {
				case extension_loaded('mcrypt'):
					define('CRYPT_DES_MODE', CRYPT_DES_MODE_MCRYPT);
					break;
				default:
					define('CRYPT_DES_MODE', CRYPT_DES_MODE_INTERNAL);
			}
		}

		if ( $mode == CRYPT_DES_MODE_3CBC ) {
			$this->mode = CRYPT_DES_MODE_3CBC;
			$this->des = array(
				new Crypt_DES(CRYPT_DES_MODE_CBC),
				new Crypt_DES(CRYPT_DES_MODE_CBC),
				new Crypt_DES(CRYPT_DES_MODE_CBC)
			);

						$this->des[0]->disablePadding();
			$this->des[1]->disablePadding();
			$this->des[2]->disablePadding();

			return;
		}

		switch ( CRYPT_DES_MODE ) {
			case CRYPT_DES_MODE_MCRYPT:
				switch ($mode) {
					case CRYPT_DES_MODE_ECB:
						$this->paddable = true;
						$this->mode = MCRYPT_MODE_ECB;
						break;
					case CRYPT_DES_MODE_CTR:
						$this->mode = 'ctr';
						break;
					case CRYPT_DES_MODE_CFB:
						$this->mode = 'ncfb';
						break;
					case CRYPT_DES_MODE_OFB:
						$this->mode = MCRYPT_MODE_NOFB;
						break;
					case CRYPT_DES_MODE_CBC:
					default:
						$this->paddable = true;
						$this->mode = MCRYPT_MODE_CBC;
				}

				break;
			default:
				$this->des = array(
					new Crypt_DES(CRYPT_DES_MODE_ECB),
					new Crypt_DES(CRYPT_DES_MODE_ECB),
					new Crypt_DES(CRYPT_DES_MODE_ECB)
				);

								$this->des[0]->disablePadding();
				$this->des[1]->disablePadding();
				$this->des[2]->disablePadding();

				switch ($mode) {
					case CRYPT_DES_MODE_ECB:
					case CRYPT_DES_MODE_CBC:
						$this->paddable = true;
						$this->mode = $mode;
						break;
					case CRYPT_DES_MODE_CTR:
					case CRYPT_DES_MODE_CFB:
					case CRYPT_DES_MODE_OFB:
						$this->mode = $mode;
						break;
					default:
						$this->paddable = true;
						$this->mode = CRYPT_DES_MODE_CBC;
				}
		}
	}

	function setKey($key)
	{
		$length = strlen($key);
		if ($length > 8) {
			$key = str_pad($key, 24, chr(0));
											} else {
			$key = str_pad($key, 8, chr(0));
		}
		$this->key = $key;
		switch (true) {
			case CRYPT_DES_MODE == CRYPT_DES_MODE_INTERNAL:
			case $this->mode == CRYPT_DES_MODE_3CBC:
				$this->des[0]->setKey(substr($key,  0, 8));
				$this->des[1]->setKey(substr($key,  8, 8));
				$this->des[2]->setKey(substr($key, 16, 8));
		}
		$this->enchanged = $this->dechanged = true;
	}

	function setIV($iv)
	{
		$this->encryptIV = $this->decryptIV = $this->iv = str_pad(substr($iv, 0, 8), 8, chr(0));
		if ($this->mode == CRYPT_DES_MODE_3CBC) {
			$this->des[0]->setIV($iv);
			$this->des[1]->setIV($iv);
			$this->des[2]->setIV($iv);
		}
		$this->enchanged = $this->dechanged = true;
	}

	function _generate_xor($length, &$iv)
	{
		$xor = '';
		$num_blocks = ($length + 7) >> 3;
		for ($i = 0; $i < $num_blocks; $i++) {
			$xor.= $iv;
			for ($j = 4; $j <= 8; $j+=4) {
				$temp = substr($iv, -$j, 4);
				switch ($temp) {
					case "\xFF\xFF\xFF\xFF":
						$iv = substr_replace($iv, "\x00\x00\x00\x00", -$j, 4);
						break;
					case "\x7F\xFF\xFF\xFF":
						$iv = substr_replace($iv, "\x80\x00\x00\x00", -$j, 4);
						break 2;
					default:
						extract(unpack('Ncount', $temp));
						$iv = substr_replace($iv, pack('N', $count + 1), -$j, 4);
						break 2;
				}
			}
		}

		return $xor;
	}

	function encrypt($plaintext)
	{
		if ($this->paddable) {
			$plaintext = $this->_pad($plaintext);
		}

		if ($this->mode == CRYPT_DES_MODE_3CBC && strlen($this->key) > 8) {
			$ciphertext = $this->des[2]->encrypt($this->des[1]->decrypt($this->des[0]->encrypt($plaintext)));

			return $ciphertext;
		}

		if ( CRYPT_DES_MODE == CRYPT_DES_MODE_MCRYPT ) {
			if ($this->enchanged) {
				if (!isset($this->enmcrypt)) {
					$this->enmcrypt = mcrypt_module_open(MCRYPT_3DES, '', $this->mode, '');
				}
				mcrypt_generic_init($this->enmcrypt, $this->key, $this->encryptIV);
				if ($this->mode != 'ncfb') {
					$this->enchanged = false;
				}
			}

			if ($this->mode != 'ncfb') {
				$ciphertext = mcrypt_generic($this->enmcrypt, $plaintext);
			} else {
				if ($this->enchanged) {
					$this->ecb = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_ECB, '');
					mcrypt_generic_init($this->ecb, $this->key, "\0\0\0\0\0\0\0\0");
					$this->enchanged = false;
				}

				if (strlen($this->enbuffer)) {
					$ciphertext = $plaintext ^ substr($this->encryptIV, strlen($this->enbuffer));
					$this->enbuffer.= $ciphertext;
					if (strlen($this->enbuffer) == 8) {
						$this->encryptIV = $this->enbuffer;
						$this->enbuffer = '';
						mcrypt_generic_init($this->enmcrypt, $this->key, $this->encryptIV);
					}
					$plaintext = substr($plaintext, strlen($ciphertext));
				} else {
					$ciphertext = '';
				}

				$last_pos = strlen($plaintext) & 0xFFFFFFF8;
				$ciphertext.= $last_pos ? mcrypt_generic($this->enmcrypt, substr($plaintext, 0, $last_pos)) : '';

				if (strlen($plaintext) & 0x7) {
					if (strlen($ciphertext)) {
						$this->encryptIV = substr($ciphertext, -8);
					}
					$this->encryptIV = mcrypt_generic($this->ecb, $this->encryptIV);
					$this->enbuffer = substr($plaintext, $last_pos) ^ $this->encryptIV;
					$ciphertext.= $this->enbuffer;
				}
			}

			if (!$this->continuousBuffer) {
				mcrypt_generic_init($this->enmcrypt, $this->key, $this->encryptIV);
			}

			return $ciphertext;
		}

		if (strlen($this->key) <= 8) {
			$this->des[0]->mode = $this->mode;

			return $this->des[0]->encrypt($plaintext);
		}

		$des = $this->des;

		$buffer = &$this->enbuffer;
		$continuousBuffer = $this->continuousBuffer;
		$ciphertext = '';
		switch ($this->mode) {
			case CRYPT_DES_MODE_ECB:
				for ($i = 0; $i < strlen($plaintext); $i+=8) {
					$block = substr($plaintext, $i, 8);
					$block = $des[0]->_processBlock($block, CRYPT_DES_ENCRYPT);
					$block = $des[1]->_processBlock($block, CRYPT_DES_DECRYPT);
					$block = $des[2]->_processBlock($block, CRYPT_DES_ENCRYPT);
					$ciphertext.= $block;
				}
				break;
			case CRYPT_DES_MODE_CBC:
				$xor = $this->encryptIV;
				for ($i = 0; $i < strlen($plaintext); $i+=8) {
					$block = substr($plaintext, $i, 8) ^ $xor;
					$block = $des[0]->_processBlock($block, CRYPT_DES_ENCRYPT);
					$block = $des[1]->_processBlock($block, CRYPT_DES_DECRYPT);
					$block = $des[2]->_processBlock($block, CRYPT_DES_ENCRYPT);
					$xor = $block;
					$ciphertext.= $block;
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
				}
				break;
			case CRYPT_DES_MODE_CTR:
				$xor = $this->encryptIV;
				if (strlen($buffer)) {
					for ($i = 0; $i < strlen($plaintext); $i+=8) {
						$block = substr($plaintext, $i, 8);
						$key = $this->_generate_xor(8, $xor);
						$key = $des[0]->_processBlock($key, CRYPT_DES_ENCRYPT);
						$key = $des[1]->_processBlock($key, CRYPT_DES_DECRYPT);
						$key = $des[2]->_processBlock($key, CRYPT_DES_ENCRYPT);
						$buffer.= $key;
						$key = $this->_string_shift($buffer, 8);
						$ciphertext.= $block ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($plaintext); $i+=8) {
						$block = substr($plaintext, $i, 8);
						$key = $this->_generate_xor(8, $xor);
						$key = $des[0]->_processBlock($key, CRYPT_DES_ENCRYPT);
						$key = $des[1]->_processBlock($key, CRYPT_DES_DECRYPT);
						$key = $des[2]->_processBlock($key, CRYPT_DES_ENCRYPT);
						$ciphertext.= $block ^ $key;
					}
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
					if ($start = strlen($plaintext) & 7) {
						$buffer = substr($key, $start) . $buffer;
					}
				}
				break;
			case CRYPT_DES_MODE_CFB:
				if (!empty($buffer['xor'])) {
					$ciphertext = $plaintext ^ $buffer['xor'];
					$iv = $buffer['encrypted'] . $ciphertext;
					$start = strlen($ciphertext);
					$buffer['encrypted'].= $ciphertext;
					$buffer['xor'] = substr($buffer['xor'], strlen($ciphertext));
				} else {
					$ciphertext = '';
					$iv = $this->encryptIV;
					$start = 0;
				}

				for ($i = $start; $i < strlen($plaintext); $i+=8) {
					$block = substr($plaintext, $i, 8);
					$iv = $des[0]->_processBlock($iv, CRYPT_DES_ENCRYPT);
					$iv = $des[1]->_processBlock($iv, CRYPT_DES_DECRYPT);
					$xor= $des[2]->_processBlock($iv, CRYPT_DES_ENCRYPT);

					$iv = $block ^ $xor;
					if ($continuousBuffer && strlen($iv) != 8) {
						$buffer = array(
							'encrypted' => $iv,
							'xor' => substr($xor, strlen($iv))
						);
					}
					$ciphertext.= $iv;
				}

				if ($this->continuousBuffer) {
					$this->encryptIV = $iv;
				}
				break;
			case CRYPT_DES_MODE_OFB:
				$xor = $this->encryptIV;
				if (strlen($buffer)) {
					for ($i = 0; $i < strlen($plaintext); $i+=8) {
						$xor = $des[0]->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$xor = $des[1]->_processBlock($xor, CRYPT_DES_DECRYPT);
						$xor = $des[2]->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$buffer.= $xor;
						$key = $this->_string_shift($buffer, 8);
						$ciphertext.= substr($plaintext, $i, 8) ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($plaintext); $i+=8) {
						$xor = $des[0]->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$xor = $des[1]->_processBlock($xor, CRYPT_DES_DECRYPT);
						$xor = $des[2]->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$ciphertext.= substr($plaintext, $i, 8) ^ $xor;
					}
					$key = $xor;
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
					if ($start = strlen($plaintext) & 7) {
						 $buffer = substr($key, $start) . $buffer;
					}
				}
		}

		return $ciphertext;
	}

	function decrypt($ciphertext)
	{
		if ($this->mode == CRYPT_DES_MODE_3CBC && strlen($this->key) > 8) {
			$plaintext = $this->des[0]->decrypt($this->des[1]->encrypt($this->des[2]->decrypt($ciphertext)));

			return $this->_unpad($plaintext);
		}

		if ($this->paddable) {
									$ciphertext = str_pad($ciphertext, (strlen($ciphertext) + 7) & 0xFFFFFFF8, chr(0));
		}

		if ( CRYPT_DES_MODE == CRYPT_DES_MODE_MCRYPT ) {
			if ($this->dechanged) {
				if (!isset($this->demcrypt)) {
					$this->demcrypt = mcrypt_module_open(MCRYPT_3DES, '', $this->mode, '');
				}
				mcrypt_generic_init($this->demcrypt, $this->key, $this->decryptIV);
				if ($this->mode != 'ncfb') {
					$this->dechanged = false;
				}
			}

			if ($this->mode != 'ncfb') {
				$plaintext = mdecrypt_generic($this->demcrypt, $ciphertext);
			} else {
				if ($this->dechanged) {
					$this->ecb = mcrypt_module_open(MCRYPT_3DES, '', MCRYPT_MODE_ECB, '');
					mcrypt_generic_init($this->ecb, $this->key, "\0\0\0\0\0\0\0\0");
					$this->dechanged = false;
				}

				if (strlen($this->debuffer)) {
					$plaintext = $ciphertext ^ substr($this->decryptIV, strlen($this->debuffer));

					$this->debuffer.= substr($ciphertext, 0, strlen($plaintext));
					if (strlen($this->debuffer) == 8) {
						$this->decryptIV = $this->debuffer;
						$this->debuffer = '';
						mcrypt_generic_init($this->demcrypt, $this->key, $this->decryptIV);
					}
					$ciphertext = substr($ciphertext, strlen($plaintext));
				} else {
					$plaintext = '';
				}

				$last_pos = strlen($ciphertext) & 0xFFFFFFF8;
				$plaintext.= $last_pos ? mdecrypt_generic($this->demcrypt, substr($ciphertext, 0, $last_pos)) : '';

				if (strlen($ciphertext) & 0x7) {
					if (strlen($plaintext)) {
						$this->decryptIV = substr($ciphertext, $last_pos - 8, 8);
					}
					$this->decryptIV = mcrypt_generic($this->ecb, $this->decryptIV);
					$this->debuffer = substr($ciphertext, $last_pos);
					$plaintext.= $this->debuffer ^ $this->decryptIV;
				}

				return $plaintext;
			}

			if (!$this->continuousBuffer) {
				mcrypt_generic_init($this->demcrypt, $this->key, $this->decryptIV);
			}

			return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
		}

		if (strlen($this->key) <= 8) {
			$this->des[0]->mode = $this->mode;
			$plaintext = $this->des[0]->decrypt($ciphertext);
			return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
		}

		$des = $this->des;

		$buffer = &$this->enbuffer;
		$continuousBuffer = $this->continuousBuffer;
		$plaintext = '';
		switch ($this->mode) {
			case CRYPT_DES_MODE_ECB:
				for ($i = 0; $i < strlen($ciphertext); $i+=8) {
					$block = substr($ciphertext, $i, 8);
					$block = $des[2]->_processBlock($block, CRYPT_DES_DECRYPT);
					$block = $des[1]->_processBlock($block, CRYPT_DES_ENCRYPT);
					$block = $des[0]->_processBlock($block, CRYPT_DES_DECRYPT);
					$plaintext.= $block;
				}
				break;
			case CRYPT_DES_MODE_CBC:
				$xor = $this->decryptIV;
				for ($i = 0; $i < strlen($ciphertext); $i+=8) {
					$orig = $block = substr($ciphertext, $i, 8);
					$block = $des[2]->_processBlock($block, CRYPT_DES_DECRYPT);
					$block = $des[1]->_processBlock($block, CRYPT_DES_ENCRYPT);
					$block = $des[0]->_processBlock($block, CRYPT_DES_DECRYPT);
					$plaintext.= $block ^ $xor;
					$xor = $orig;
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
				}
				break;
			case CRYPT_DES_MODE_CTR:
				$xor = $this->decryptIV;
				if (strlen($buffer)) {
					for ($i = 0; $i < strlen($ciphertext); $i+=8) {
						$block = substr($ciphertext, $i, 8);
						$key = $this->_generate_xor(8, $xor);
						$key = $des[0]->_processBlock($key, CRYPT_DES_ENCRYPT);
						$key = $des[1]->_processBlock($key, CRYPT_DES_DECRYPT);
						$key = $des[2]->_processBlock($key, CRYPT_DES_ENCRYPT);
						$buffer.= $key;
						$key = $this->_string_shift($buffer, 8);
						$plaintext.= $block ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($ciphertext); $i+=8) {
						$block = substr($ciphertext, $i, 8);
						$key = $this->_generate_xor(8, $xor);
						$key = $des[0]->_processBlock($key, CRYPT_DES_ENCRYPT);
						$key = $des[1]->_processBlock($key, CRYPT_DES_DECRYPT);
						$key = $des[2]->_processBlock($key, CRYPT_DES_ENCRYPT);
						$plaintext.= $block ^ $key;
					}
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
					if ($start = strlen($plaintext) & 7) {
						$buffer = substr($key, $start) . $buffer;
					}
				}
				break;
			case CRYPT_DES_MODE_CFB:
				if (!empty($buffer['ciphertext'])) {
					$plaintext = $ciphertext ^ substr($this->decryptIV, strlen($buffer['ciphertext']));
					$buffer['ciphertext'].= substr($ciphertext, 0, strlen($plaintext));
					if (strlen($buffer['ciphertext']) == 8) {
						$xor = $des[0]->_processBlock($buffer['ciphertext'], CRYPT_DES_ENCRYPT);
						$xor = $des[1]->_processBlock($xor, CRYPT_DES_DECRYPT);
						$xor = $des[2]->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$buffer['ciphertext'] = '';
					}
					$start = strlen($plaintext);
					$block = $this->decryptIV;
				} else {
					$plaintext = '';
					$xor = $des[0]->_processBlock($this->decryptIV, CRYPT_DES_ENCRYPT);
					$xor = $des[1]->_processBlock($xor, CRYPT_DES_DECRYPT);
					$xor = $des[2]->_processBlock($xor, CRYPT_DES_ENCRYPT);
					$start = 0;
				}

				for ($i = $start; $i < strlen($ciphertext); $i+=8) {
					$block = substr($ciphertext, $i, 8);
					$plaintext.= $block ^ $xor;
					if ($continuousBuffer && strlen($block) != 8) {
						$buffer['ciphertext'].= $block;
						$block = $xor;
					} else if (strlen($block) == 8) {
						$xor = $des[0]->_processBlock($block, CRYPT_DES_ENCRYPT);
						$xor = $des[1]->_processBlock($xor, CRYPT_DES_DECRYPT);
						$xor = $des[2]->_processBlock($xor, CRYPT_DES_ENCRYPT);
					}
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $block;
				}
				break;
			case CRYPT_DES_MODE_OFB:
				$xor = $this->decryptIV;
				if (strlen($buffer)) {
					for ($i = 0; $i < strlen($ciphertext); $i+=8) {
						$xor = $des[0]->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$xor = $des[1]->_processBlock($xor, CRYPT_DES_DECRYPT);
						$xor = $des[2]->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$buffer.= $xor;
						$key = $this->_string_shift($buffer, 8);
						$plaintext.= substr($ciphertext, $i, 8) ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($ciphertext); $i+=8) {
						$xor = $des[0]->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$xor = $des[1]->_processBlock($xor, CRYPT_DES_DECRYPT);
						$xor = $des[2]->_processBlock($xor, CRYPT_DES_ENCRYPT);
						$plaintext.= substr($ciphertext, $i, 8) ^ $xor;
					}
					$key = $xor;
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
					if ($start = strlen($ciphertext) & 7) {
						 $buffer = substr($key, $start) . $buffer;
					}
				}
		}

		return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
	}

	function enableContinuousBuffer()
	{
		$this->continuousBuffer = true;
		if ($this->mode == CRYPT_DES_MODE_3CBC) {
			$this->des[0]->enableContinuousBuffer();
			$this->des[1]->enableContinuousBuffer();
			$this->des[2]->enableContinuousBuffer();
		}
	}

	function disableContinuousBuffer()
	{
		$this->continuousBuffer = false;
		$this->encryptIV = $this->iv;
		$this->decryptIV = $this->iv;

		if ($this->mode == CRYPT_DES_MODE_3CBC) {
			$this->des[0]->disableContinuousBuffer();
			$this->des[1]->disableContinuousBuffer();
			$this->des[2]->disableContinuousBuffer();
		}
	}

	function enablePadding()
	{
		$this->padding = true;
	}

	function disablePadding()
	{
		$this->padding = false;
	}

	function _pad($text)
	{
		$length = strlen($text);

		if (!$this->padding) {
			if (($length & 7) == 0) {
				return $text;
			} else {
				user_error("The plaintext's length ($length) is not a multiple of the block size (8)", E_USER_NOTICE);
				$this->padding = true;
			}
		}

		$pad = 8 - ($length & 7);
		return str_pad($text, $length + $pad, chr($pad));
	}

	function _unpad($text)
	{
		if (!$this->padding) {
			return $text;
		}

		$length = ord($text[strlen($text) - 1]);

		if (!$length || $length > 8) {
			return false;
		}

		return substr($text, 0, -$length);
	}

	function _string_shift(&$string, $index = 1)
	{
		$substr = substr($string, 0, $index);
		$string = substr($string, $index);
		return $substr;
	}
}

class Crypt_RSA
{
	public
		$publicKeyFormat = CRYPT_RSA_PUBLIC_FORMAT_PKCS1;

	protected
		$zero,
		$one,
		$privateKeyFormat = CRYPT_RSA_PRIVATE_FORMAT_PKCS1,
		$modulus,
		$k,
		$exponent,
		$primes,
		$exponents,
		$coefficients,
		$hashName,
		$hash,
		$hLen,
		$sLen,
		$mgfHash,
		$mgfHLen,
		$encryptionMode = CRYPT_RSA_ENCRYPTION_OAEP,
		$signatureMode = CRYPT_RSA_SIGNATURE_PSS,
		$publicExponent = false,
		$password = '',
		$components = array(),
		$current;

	function __construct()
	{
		if ( !defined('CRYPT_RSA_MODE') ) {
			switch (true) {
				default:
					define('CRYPT_RSA_MODE', CRYPT_RSA_MODE_INTERNAL);
			}
		}

		$this->zero = new Math_BigInteger();
		$this->one = new Math_BigInteger(1);

		$this->hash = new Crypt_Hash('sha1');
		$this->hLen = $this->hash->getLength();
		$this->hashName = 'sha1';
		$this->mgfHash = new Crypt_Hash('sha1');
		$this->mgfHLen = $this->mgfHash->getLength();
	}

	function createKey($bits = 1024, $timeout = false, $partial = array())
	{
		if ( CRYPT_RSA_MODE == CRYPT_RSA_MODE_OPENSSL ) {
			$rsa = openssl_pkey_new(array('private_key_bits' => $bits));
			openssl_pkey_export($rsa, $privatekey);
			$publickey = openssl_pkey_get_details($rsa);
			$publickey = $publickey['key'];

			if ($this->privateKeyFormat != CRYPT_RSA_PRIVATE_FORMAT_PKCS1) {
				$privatekey = call_user_func_array(array($this, '_convertPrivateKey'), array_values($this->_parseKey($privatekey, CRYPT_RSA_PRIVATE_FORMAT_PKCS1)));
				$publickey = call_user_func_array(array($this, '_convertPublicKey'), array_values($this->_parseKey($publickey, CRYPT_RSA_PUBLIC_FORMAT_PKCS1)));
			}

			return array(
				'privatekey' => $privatekey,
				'publickey' => $publickey,
				'partialkey' => false
			);
		}

		static $e;
		if (!isset($e)) {
			if (!defined('CRYPT_RSA_EXPONENT')) {
				define('CRYPT_RSA_EXPONENT', '65537');
			}
			if (!defined('CRYPT_RSA_COMMENT')) {
				define('CRYPT_RSA_COMMENT', 'phpseclib-generated-key');
			}
			if (!defined('CRYPT_RSA_SMALLEST_PRIME')) {
				define('CRYPT_RSA_SMALLEST_PRIME', 4096);
			}

			$e = new Math_BigInteger(CRYPT_RSA_EXPONENT);
		}

		extract($this->_generateMinMax($bits));
		$absoluteMin = $min;
		$temp = $bits >> 1;
		if ($temp > CRYPT_RSA_SMALLEST_PRIME) {
			$num_primes = floor($bits / CRYPT_RSA_SMALLEST_PRIME);
			$temp = CRYPT_RSA_SMALLEST_PRIME;
		} else {
			$num_primes = 2;
		}
		extract($this->_generateMinMax($temp + $bits % $temp));
		$finalMax = $max;
		extract($this->_generateMinMax($temp));

		$generator = new Math_BigInteger();
		$generator->setRandomGenerator('crypt_random');

		$n = $this->one->copy();
		if (!empty($partial)) {
			extract(unserialize($partial));
		} else {
			$exponents = $coefficients = $primes = array();
			$lcm = array(
				'top' => $this->one->copy(),
				'bottom' => false
			);
		}

		$start = time();
		$i0 = count($primes) + 1;

		do {
			for ($i = $i0; $i <= $num_primes; $i++) {
				if ($timeout !== false) {
					$timeout-= time() - $start;
					$start = time();
					if ($timeout <= 0) {
						return array(
							'privatekey' => '',
							'publickey'  => '',
							'partialkey' => serialize(array(
								'primes' => $primes,
								'coefficients' => $coefficients,
								'lcm' => $lcm,
								'exponents' => $exponents
							))
						);
					}
				}

				if ($i == $num_primes) {
					list($min, $temp) = $absoluteMin->divide($n);
					if (!$temp->equals($this->zero)) {
						$min = $min->add($this->one);                     }
					$primes[$i] = $generator->randomPrime($min, $finalMax, $timeout);
				} else {
					$primes[$i] = $generator->randomPrime($min, $max, $timeout);
				}

				if ($primes[$i] === false) {                     if (count($primes) > 1) {
						$partialkey = '';
					} else {
						array_pop($primes);
						$partialkey = serialize(array(
							'primes' => $primes,
							'coefficients' => $coefficients,
							'lcm' => $lcm,
							'exponents' => $exponents
						));
					}

					return array(
						'privatekey' => '',
						'publickey'  => '',
						'partialkey' => $partialkey
					);
				}

				if ($i > 2) {
					$coefficients[$i] = $n->modInverse($primes[$i]);
				}

				$n = $n->multiply($primes[$i]);

				$temp = $primes[$i]->subtract($this->one);

				$lcm['top'] = $lcm['top']->multiply($temp);
				$lcm['bottom'] = $lcm['bottom'] === false ? $temp : $lcm['bottom']->gcd($temp);

				$exponents[$i] = $e->modInverse($temp);
			}

			list($lcm) = $lcm['top']->divide($lcm['bottom']);
			$gcd = $lcm->gcd($e);
			$i0 = 1;
		} while (!$gcd->equals($this->one));

		$d = $e->modInverse($lcm);

		$coefficients[2] = $primes[2]->modInverse($primes[1]);

		return array(
			'privatekey' => $this->_convertPrivateKey($n, $e, $d, $primes, $exponents, $coefficients),
			'publickey'  => $this->_convertPublicKey($n, $e),
			'partialkey' => false
		);
	}

	function _convertPrivateKey($n, $e, $d, $primes, $exponents, $coefficients)
	{
		$num_primes = count($primes);
		$raw = array(
			'version' => $num_primes == 2 ? chr(0) : chr(1),             'modulus' => $n->toBytes(true),
			'publicExponent' => $e->toBytes(true),
			'privateExponent' => $d->toBytes(true),
			'prime1' => $primes[1]->toBytes(true),
			'prime2' => $primes[2]->toBytes(true),
			'exponent1' => $exponents[1]->toBytes(true),
			'exponent2' => $exponents[2]->toBytes(true),
			'coefficient' => $coefficients[2]->toBytes(true)
		);

		switch ($this->privateKeyFormat) {
			default:
				$components = array();
				foreach ($raw as $name => $value) {
					$components[$name] = pack('Ca*a*', CRYPT_RSA_ASN1_INTEGER, $this->_encodeLength(strlen($value)), $value);
				}

				$RSAPrivateKey = implode('', $components);

				if ($num_primes > 2) {
					$OtherPrimeInfos = '';
					for ($i = 3; $i <= $num_primes; $i++) {
						$OtherPrimeInfo = pack('Ca*a*', CRYPT_RSA_ASN1_INTEGER, $this->_encodeLength(strlen($primes[$i]->toBytes(true))), $primes[$i]->toBytes(true));
						$OtherPrimeInfo.= pack('Ca*a*', CRYPT_RSA_ASN1_INTEGER, $this->_encodeLength(strlen($exponents[$i]->toBytes(true))), $exponents[$i]->toBytes(true));
						$OtherPrimeInfo.= pack('Ca*a*', CRYPT_RSA_ASN1_INTEGER, $this->_encodeLength(strlen($coefficients[$i]->toBytes(true))), $coefficients[$i]->toBytes(true));
						$OtherPrimeInfos.= pack('Ca*a*', CRYPT_RSA_ASN1_SEQUENCE, $this->_encodeLength(strlen($OtherPrimeInfo)), $OtherPrimeInfo);
					}
					$RSAPrivateKey.= pack('Ca*a*', CRYPT_RSA_ASN1_SEQUENCE, $this->_encodeLength(strlen($OtherPrimeInfos)), $OtherPrimeInfos);
				}

				$RSAPrivateKey = pack('Ca*a*', CRYPT_RSA_ASN1_SEQUENCE, $this->_encodeLength(strlen($RSAPrivateKey)), $RSAPrivateKey);

				if (!empty($this->password)) {
					$iv = $this->_random(8);
					$symkey = pack('H*', md5($this->password . $iv));
					$symkey.= substr(pack('H*', md5($symkey . $this->password . $iv)), 0, 8);
					$des = new Crypt_TripleDES();
					$des->setKey($symkey);
					$des->setIV($iv);
					$iv = strtoupper(bin2hex($iv));
					$RSAPrivateKey = "-----BEGIN RSA PRIVATE KEY-----\r\n" .
									 "Proc-Type: 4,ENCRYPTED\r\n" .
									 "DEK-Info: DES-EDE3-CBC,$iv\r\n" .
									 "\r\n" .
									 chunk_split(base64_encode($des->encrypt($RSAPrivateKey))) .
									 '-----END RSA PRIVATE KEY-----';
				} else {
					$RSAPrivateKey = "-----BEGIN RSA PRIVATE KEY-----\r\n" .
									 chunk_split(base64_encode($RSAPrivateKey)) .
									 '-----END RSA PRIVATE KEY-----';
				}

				return $RSAPrivateKey;
		}
	}

	function _convertPublicKey($n, $e)
	{
		$modulus = $n->toBytes(true);
		$publicExponent = $e->toBytes(true);

		switch ($this->publicKeyFormat) {
			case CRYPT_RSA_PUBLIC_FORMAT_RAW:
				return array('e' => $e->copy(), 'n' => $n->copy());
			case CRYPT_RSA_PUBLIC_FORMAT_OPENSSH:
				$RSAPublicKey = pack('Na*Na*Na*', strlen('ssh-rsa'), 'ssh-rsa', strlen($publicExponent), $publicExponent, strlen($modulus), $modulus);
				$RSAPublicKey = 'ssh-rsa ' . base64_encode($RSAPublicKey) . ' ' . CRYPT_RSA_COMMENT;

				return $RSAPublicKey;
			default:
				$components = array(
					'modulus' => pack('Ca*a*', CRYPT_RSA_ASN1_INTEGER, $this->_encodeLength(strlen($modulus)), $modulus),
					'publicExponent' => pack('Ca*a*', CRYPT_RSA_ASN1_INTEGER, $this->_encodeLength(strlen($publicExponent)), $publicExponent)
				);

				$RSAPublicKey = pack('Ca*a*a*',
					CRYPT_RSA_ASN1_SEQUENCE, $this->_encodeLength(strlen($components['modulus']) + strlen($components['publicExponent'])),
					$components['modulus'], $components['publicExponent']
				);

				$RSAPublicKey = "-----BEGIN PUBLIC KEY-----\r\n" .
								 chunk_split(base64_encode($RSAPublicKey)) .
								 '-----END PUBLIC KEY-----';

				return $RSAPublicKey;
		}
	}

	function _parseKey($key, $type)
	{
		switch ($type) {
			case CRYPT_RSA_PUBLIC_FORMAT_RAW:
				if (!is_array($key)) {
					return false;
				}
				$components = array();
				switch (true) {
					case isset($key['e']):
						$components['publicExponent'] = $key['e']->copy();
						break;
					case isset($key['exponent']):
						$components['publicExponent'] = $key['exponent']->copy();
						break;
					case isset($key['publicExponent']):
						$components['publicExponent'] = $key['publicExponent']->copy();
						break;
					case isset($key[0]):
						$components['publicExponent'] = $key[0]->copy();
				}
				switch (true) {
					case isset($key['n']):
						$components['modulus'] = $key['n']->copy();
						break;
					case isset($key['modulo']):
						$components['modulus'] = $key['modulo']->copy();
						break;
					case isset($key['modulus']):
						$components['modulus'] = $key['modulus']->copy();
						break;
					case isset($key[1]):
						$components['modulus'] = $key[1]->copy();
				}
				return $components;
			case CRYPT_RSA_PRIVATE_FORMAT_PKCS1:
			case CRYPT_RSA_PUBLIC_FORMAT_PKCS1:
				if (preg_match('#DEK-Info: (.+),(.+)#', $key, $matches)) {
					$iv = pack('H*', trim($matches[2]));
					$symkey = pack('H*', md5($this->password . substr($iv, 0, 8)));                     $symkey.= substr(pack('H*', md5($symkey . $this->password . $iv)), 0, 8);
					$ciphertext = preg_replace('#.+(\r|\n|\r\n)\1|[\r\n]|-.+-#s', '', $key);
					$ciphertext = preg_match('#^[a-zA-Z\d/+]*={0,2}$#', $ciphertext) ? base64_decode($ciphertext) : false;
					if ($ciphertext === false) {
						$ciphertext = $key;
					}
					switch ($matches[1]) {
						case 'AES-128-CBC':
							$symkey = substr($symkey, 0, 16);
							$crypto = new Crypt_AES();
							break;
						case 'DES-EDE3-CFB':
							$crypto = new Crypt_TripleDES(CRYPT_DES_MODE_CFB);
							break;
						case 'DES-EDE3-CBC':
							$crypto = new Crypt_TripleDES();
							break;
						case 'DES-CBC':
							$crypto = new Crypt_DES();
							break;
						default:
							return false;
					}
					$crypto->setKey($symkey);
					$crypto->setIV($iv);
					$decoded = $crypto->decrypt($ciphertext);
				} else {
					$decoded = preg_replace('#-.+-|[\r\n]#', '', $key);
					$decoded = preg_match('#^[a-zA-Z\d/+]*={0,2}$#', $decoded) ? base64_decode($decoded) : false;
				}

				if ($decoded !== false) {
					$key = $decoded;
				}

				$components = array();

				if (ord($this->_string_shift($key)) != CRYPT_RSA_ASN1_SEQUENCE) {
					return false;
				}
				if ($this->_decodeLength($key) != strlen($key)) {
					return false;
				}

				$tag = ord($this->_string_shift($key));
				if ($tag == CRYPT_RSA_ASN1_SEQUENCE) {

					$this->_string_shift($key, $this->_decodeLength($key));
					$this->_string_shift($key);
					$this->_decodeLength($key);
					$this->_string_shift($key);
					if (ord($this->_string_shift($key)) != CRYPT_RSA_ASN1_SEQUENCE) {
						return false;
					}
					if ($this->_decodeLength($key) != strlen($key)) {
						return false;
					}
					$tag = ord($this->_string_shift($key));
				}
				if ($tag != CRYPT_RSA_ASN1_INTEGER) {
					return false;
				}

				$length = $this->_decodeLength($key);
				$temp = $this->_string_shift($key, $length);
				if (strlen($temp) != 1 || ord($temp) > 2) {
					$components['modulus'] = new Math_BigInteger($temp, -256);
					$this->_string_shift($key);                     $length = $this->_decodeLength($key);
					$components[$type == CRYPT_RSA_PUBLIC_FORMAT_PKCS1 ? 'publicExponent' : 'privateExponent'] = new Math_BigInteger($this->_string_shift($key, $length), -256);

					return $components;
				}
				if (ord($this->_string_shift($key)) != CRYPT_RSA_ASN1_INTEGER) {
					return false;
				}
				$length = $this->_decodeLength($key);
				$components['modulus'] = new Math_BigInteger($this->_string_shift($key, $length), -256);
				$this->_string_shift($key);
				$length = $this->_decodeLength($key);
				$components['publicExponent'] = new Math_BigInteger($this->_string_shift($key, $length), -256);
				$this->_string_shift($key);
				$length = $this->_decodeLength($key);
				$components['privateExponent'] = new Math_BigInteger($this->_string_shift($key, $length), -256);
				$this->_string_shift($key);
				$length = $this->_decodeLength($key);
				$components['primes'] = array(1 => new Math_BigInteger($this->_string_shift($key, $length), -256));
				$this->_string_shift($key);
				$length = $this->_decodeLength($key);
				$components['primes'][] = new Math_BigInteger($this->_string_shift($key, $length), -256);
				$this->_string_shift($key);
				$length = $this->_decodeLength($key);
				$components['exponents'] = array(1 => new Math_BigInteger($this->_string_shift($key, $length), -256));
				$this->_string_shift($key);
				$length = $this->_decodeLength($key);
				$components['exponents'][] = new Math_BigInteger($this->_string_shift($key, $length), -256);
				$this->_string_shift($key);
				$length = $this->_decodeLength($key);
				$components['coefficients'] = array(2 => new Math_BigInteger($this->_string_shift($key, $length), -256));

				if (!empty($key)) {
					if (ord($this->_string_shift($key)) != CRYPT_RSA_ASN1_SEQUENCE) {
						return false;
					}
					$this->_decodeLength($key);
					while (!empty($key)) {
						if (ord($this->_string_shift($key)) != CRYPT_RSA_ASN1_SEQUENCE) {
							return false;
						}
						$this->_decodeLength($key);
						$key = substr($key, 1);
						$length = $this->_decodeLength($key);
						$components['primes'][] = new Math_BigInteger($this->_string_shift($key, $length), -256);
						$this->_string_shift($key);
						$length = $this->_decodeLength($key);
						$components['exponents'][] = new Math_BigInteger($this->_string_shift($key, $length), -256);
						$this->_string_shift($key);
						$length = $this->_decodeLength($key);
						$components['coefficients'][] = new Math_BigInteger($this->_string_shift($key, $length), -256);
					}
				}

				return $components;
			case CRYPT_RSA_PUBLIC_FORMAT_OPENSSH:
				$key = base64_decode(preg_replace('#^ssh-rsa | .+$#', '', $key));
				if ($key === false) {
					return false;
				}

				$cleanup = substr($key, 0, 11) == "\0\0\0\7ssh-rsa";

				extract(unpack('Nlength', $this->_string_shift($key, 4)));
				$publicExponent = new Math_BigInteger($this->_string_shift($key, $length), -256);
				extract(unpack('Nlength', $this->_string_shift($key, 4)));
				$modulus = new Math_BigInteger($this->_string_shift($key, $length), -256);

				if ($cleanup && strlen($key)) {
					extract(unpack('Nlength', $this->_string_shift($key, 4)));
					return array(
						'modulus' => new Math_BigInteger($this->_string_shift($key, $length), -256),
						'publicExponent' => $modulus
					);
				} else {
					return array(
						'modulus' => $modulus,
						'publicExponent' => $publicExponent
					);
				}
									case CRYPT_RSA_PRIVATE_FORMAT_XML:
			case CRYPT_RSA_PUBLIC_FORMAT_XML:
				$this->components = array();

				$xml = xml_parser_create('UTF-8');
				xml_set_object($xml, $this);
				xml_set_element_handler($xml, '_start_element_handler', '_stop_element_handler');
				xml_set_character_data_handler($xml, '_data_handler');
				if (!xml_parse($xml, $key)) {
					return false;
				}

				return $this->components;
						case CRYPT_RSA_PRIVATE_FORMAT_PUTTY:
				$components = array();
				$key = preg_split('#\r\n|\r|\n#', $key);
				$type = trim(preg_replace('#PuTTY-User-Key-File-2: (.+)#', '$1', $key[0]));
				if ($type != 'ssh-rsa') {
					return false;
				}
				$encryption = trim(preg_replace('#Encryption: (.+)#', '$1', $key[1]));

				$publicLength = trim(preg_replace('#Public-Lines: (\d+)#', '$1', $key[3]));
				$public = base64_decode(implode('', array_map('trim', array_slice($key, 4, $publicLength))));
				$public = substr($public, 11);
				extract(unpack('Nlength', $this->_string_shift($public, 4)));
				$components['publicExponent'] = new Math_BigInteger($this->_string_shift($public, $length), -256);
				extract(unpack('Nlength', $this->_string_shift($public, 4)));
				$components['modulus'] = new Math_BigInteger($this->_string_shift($public, $length), -256);

				$privateLength = trim(preg_replace('#Private-Lines: (\d+)#', '$1', $key[$publicLength + 4]));
				$private = base64_decode(implode('', array_map('trim', array_slice($key, $publicLength + 5, $privateLength))));

				switch ($encryption) {
					case 'aes256-cbc':
						$symkey = '';
						$sequence = 0;
						while (strlen($symkey) < 32) {
							$temp = pack('Na*', $sequence++, $this->password);
							$symkey.= pack('H*', sha1($temp));
						}
						$symkey = substr($symkey, 0, 32);
						$crypto = new Crypt_AES();
				}

				if ($encryption != 'none') {
					$crypto->setKey($symkey);
					$crypto->disablePadding();
					$private = $crypto->decrypt($private);
					if ($private === false) {
						return false;
					}
				}

				extract(unpack('Nlength', $this->_string_shift($private, 4)));
				$components['privateExponent'] = new Math_BigInteger($this->_string_shift($private, $length), -256);
				extract(unpack('Nlength', $this->_string_shift($private, 4)));
				$components['primes'] = array(1 => new Math_BigInteger($this->_string_shift($private, $length), -256));
				extract(unpack('Nlength', $this->_string_shift($private, 4)));
				$components['primes'][] = new Math_BigInteger($this->_string_shift($private, $length), -256);

				$temp = $components['primes'][1]->subtract($this->one);
				$components['exponents'] = array(1 => $components['publicExponent']->modInverse($temp));
				$temp = $components['primes'][2]->subtract($this->one);
				$components['exponents'][] = $components['publicExponent']->modInverse($temp);

				extract(unpack('Nlength', $this->_string_shift($private, 4)));
				$components['coefficients'] = array(2 => new Math_BigInteger($this->_string_shift($private, $length), -256));

				return $components;
		}
	}

	function _start_element_handler($parser, $name, $attribs)
	{
		switch ($name) {
			case 'MODULUS':
				$this->current = &$this->components['modulus'];
				break;
			case 'EXPONENT':
				$this->current = &$this->components['publicExponent'];
				break;
			case 'P':
				$this->current = &$this->components['primes'][1];
				break;
			case 'Q':
				$this->current = &$this->components['primes'][2];
				break;
			case 'DP':
				$this->current = &$this->components['exponents'][1];
				break;
			case 'DQ':
				$this->current = &$this->components['exponents'][2];
				break;
			case 'INVERSEQ':
				$this->current = &$this->components['coefficients'][2];
				break;
			case 'D':
				$this->current = &$this->components['privateExponent'];
				break;
			default:
				unset($this->current);
		}
		$this->current = '';
	}

	function _stop_element_handler($parser, $name)
	{
		if ($name == 'RSAKEYVALUE') {
			return;
		}
		$this->current = new Math_BigInteger(base64_decode($this->current), 256);
	}

	function _data_handler($parser, $data)
	{
		if (!isset($this->current) || is_object($this->current)) {
			return;
		}
		$this->current.= trim($data);
	}

	function loadKey($key, $type = false)
	{
		if ($type === false) {
			$types = array(
				CRYPT_RSA_PUBLIC_FORMAT_RAW,
				CRYPT_RSA_PRIVATE_FORMAT_PKCS1,
				CRYPT_RSA_PRIVATE_FORMAT_XML,
				CRYPT_RSA_PRIVATE_FORMAT_PUTTY,
				CRYPT_RSA_PUBLIC_FORMAT_OPENSSH
			);
			foreach ($types as $type) {
				$components = $this->_parseKey($key, $type);
				if ($components !== false) {
					break;
				}
			}

		} else {
			$components = $this->_parseKey($key, $type);
		}

		if ($components === false) {
			return false;
		}

		$this->modulus = $components['modulus'];
		$this->k = strlen($this->modulus->toBytes());
		$this->exponent = isset($components['privateExponent']) ? $components['privateExponent'] : $components['publicExponent'];
		if (isset($components['primes'])) {
			$this->primes = $components['primes'];
			$this->exponents = $components['exponents'];
			$this->coefficients = $components['coefficients'];
			$this->publicExponent = $components['publicExponent'];
		} else {
			$this->primes = array();
			$this->exponents = array();
			$this->coefficients = array();
			$this->publicExponent = false;
		}

		return true;
	}

	function setPassword($password)
	{
		$this->password = $password;
	}

	function setPublicKey($key, $type = CRYPT_RSA_PUBLIC_FORMAT_PKCS1)
	{
		$components = $this->_parseKey($key, $type);

		if (empty($this->modulus) || !$this->modulus->equals($components['modulus'])) {
			user_error('Trying to load a public key?  Use loadKey() instead.  It\'s called loadKey() and not loadPrivateKey() for a reason.', E_USER_NOTICE);
			return false;
		}

		$this->publicExponent = $components['publicExponent'];

		return true;
	}

	function getPublicKey($type = CRYPT_RSA_PUBLIC_FORMAT_PKCS1)
	{
		if (empty($this->modulus) || empty($this->publicExponent)) {
			return false;
		}

		$oldFormat = $this->publicKeyFormat;
		$this->publicKeyFormat = $type;
		$temp = $this->_convertPublicKey($this->modulus, $this->publicExponent);
		$this->publicKeyFormat = $oldFormat;
		return $temp;
	}

	function _generateMinMax($bits)
	{
		$bytes = $bits >> 3;
		$min = str_repeat(chr(0), $bytes);
		$max = str_repeat(chr(0xFF), $bytes);
		$msb = $bits & 7;
		if ($msb) {
			$min = chr(1 << ($msb - 1)) . $min;
			$max = chr((1 << $msb) - 1) . $max;
		} else {
			$min[0] = chr(0x80);
		}

		return array(
			'min' => new Math_BigInteger($min, 256),
			'max' => new Math_BigInteger($max, 256)
		);
	}

	function _decodeLength(&$string)
	{
		$length = ord($this->_string_shift($string));
		if ( $length & 0x80 ) {             $length&= 0x7F;
			$temp = $this->_string_shift($string, $length);
			list(, $length) = unpack('N', substr(str_pad($temp, 4, chr(0), STR_PAD_LEFT), -4));
		}
		return $length;
	}

	function _encodeLength($length)
	{
		if ($length <= 0x7F) {
			return chr($length);
		}

		$temp = ltrim(pack('N', $length), chr(0));
		return pack('Ca*', 0x80 | strlen($temp), $temp);
	}

	function _string_shift(&$string, $index = 1)
	{
		$substr = substr($string, 0, $index);
		$string = substr($string, $index);
		return $substr;
	}

	function setPrivateKeyFormat($format)
	{
		$this->privateKeyFormat = $format;
	}

	function setPublicKeyFormat($format)
	{
		$this->publicKeyFormat = $format;
	}

	function setHash($hash)
	{
		switch ($hash) {
			case 'md2':
			case 'md5':
			case 'sha1':
			case 'sha256':
			case 'sha384':
			case 'sha512':
				$this->hash = new Crypt_Hash($hash);
				$this->hashName = $hash;
				break;
			default:
				$this->hash = new Crypt_Hash('sha1');
				$this->hashName = 'sha1';
		}
		$this->hLen = $this->hash->getLength();
	}

	function setMGFHash($hash)
	{
		switch ($hash) {
			case 'md2':
			case 'md5':
			case 'sha1':
			case 'sha256':
			case 'sha384':
			case 'sha512':
				$this->mgfHash = new Crypt_Hash($hash);
				break;
			default:
				$this->mgfHash = new Crypt_Hash('sha1');
		}
		$this->mgfHLen = $this->mgfHash->getLength();
	}

	function setSaltLength($sLen)
	{
		$this->sLen = $sLen;
	}

	function _random($bytes, $nonzero = false)
	{
		$temp = '';
		if ($nonzero) {
			for ($i = 0; $i < $bytes; $i++) {
				$temp.= chr(crypt_random(1, 255));
			}
		} else {
			$ints = ($bytes + 1) >> 2;
			for ($i = 0; $i < $ints; $i++) {
				$temp.= pack('N', crypt_random());
			}
			$temp = substr($temp, 0, $bytes);
		}
		return $temp;
	}

	function _i2osp($x, $xLen)
	{
		$x = $x->toBytes();
		if (strlen($x) > $xLen) {
			user_error('Integer too large', E_USER_NOTICE);
			return false;
		}
		return str_pad($x, $xLen, chr(0), STR_PAD_LEFT);
	}

	function _os2ip($x)
	{
		return new Math_BigInteger($x, 256);
	}

	function _exponentiate($x)
	{
		if (empty($this->primes) || empty($this->coefficients) || empty($this->exponents)) {
			return $x->modPow($this->exponent, $this->modulus);
		}

		$num_primes = count($this->primes);

		if (defined('CRYPT_RSA_DISABLE_BLINDING')) {
			$m_i = array(
				1 => $x->modPow($this->exponents[1], $this->primes[1]),
				2 => $x->modPow($this->exponents[2], $this->primes[2])
			);
			$h = $m_i[1]->subtract($m_i[2]);
			$h = $h->multiply($this->coefficients[2]);
			list(, $h) = $h->divide($this->primes[1]);
			$m = $m_i[2]->add($h->multiply($this->primes[2]));

			$r = $this->primes[1];
			for ($i = 3; $i <= $num_primes; $i++) {
				$m_i = $x->modPow($this->exponents[$i], $this->primes[$i]);

				$r = $r->multiply($this->primes[$i - 1]);

				$h = $m_i->subtract($m);
				$h = $h->multiply($this->coefficients[$i]);
				list(, $h) = $h->divide($this->primes[$i]);

				$m = $m->add($r->multiply($h));
			}
		} else {
			$smallest = $this->primes[1];
			for ($i = 2; $i <= $num_primes; $i++) {
				if ($smallest->compare($this->primes[$i]) > 0) {
					$smallest = $this->primes[$i];
				}
			}

			$one = new Math_BigInteger(1);
			$one->setRandomGenerator('crypt_random');

			$r = $one->random($one, $smallest->subtract($one));

			$m_i = array(
				1 => $this->_blind($x, $r, 1),
				2 => $this->_blind($x, $r, 2)
			);
			$h = $m_i[1]->subtract($m_i[2]);
			$h = $h->multiply($this->coefficients[2]);
			list(, $h) = $h->divide($this->primes[1]);
			$m = $m_i[2]->add($h->multiply($this->primes[2]));

			$r = $this->primes[1];
			for ($i = 3; $i <= $num_primes; $i++) {
				$m_i = $this->_blind($x, $r, $i);

				$r = $r->multiply($this->primes[$i - 1]);

				$h = $m_i->subtract($m);
				$h = $h->multiply($this->coefficients[$i]);
				list(, $h) = $h->divide($this->primes[$i]);

				$m = $m->add($r->multiply($h));
			}
		}

		return $m;
	}

	function _blind($x, $r, $i)
	{
		$x = $x->multiply($r->modPow($this->publicExponent, $this->primes[$i]));
		$x = $x->modPow($this->exponents[$i], $this->primes[$i]);

		$r = $r->modInverse($this->primes[$i]);
		$x = $x->multiply($r);
		list(, $x) = $x->divide($this->primes[$i]);

		return $x;
	}

	function _rsaep($m)
	{
		if ($m->compare($this->zero) < 0 || $m->compare($this->modulus) > 0) {
			user_error('Message representative out of range', E_USER_NOTICE);
			return false;
		}
		return $this->_exponentiate($m);
	}

	function _rsadp($c)
	{
		if ($c->compare($this->zero) < 0 || $c->compare($this->modulus) > 0) {
			user_error('Ciphertext representative out of range', E_USER_NOTICE);
			return false;
		}
		return $this->_exponentiate($c);
	}

	function _rsasp1($m)
	{
		if ($m->compare($this->zero) < 0 || $m->compare($this->modulus) > 0) {
			user_error('Message representative out of range', E_USER_NOTICE);
			return false;
		}
		return $this->_exponentiate($m);
	}

	function _rsavp1($s)
	{
		if ($s->compare($this->zero) < 0 || $s->compare($this->modulus) > 0) {
			user_error('Signature representative out of range', E_USER_NOTICE);
			return false;
		}
		return $this->_exponentiate($s);
	}

	function _mgf1($mgfSeed, $maskLen)
	{

		$t = '';
		$count = ceil($maskLen / $this->mgfHLen);
		for ($i = 0; $i < $count; $i++) {
			$c = pack('N', $i);
			$t.= $this->mgfHash->hash($mgfSeed . $c);
		}

		return substr($t, 0, $maskLen);
	}

	function _rsaes_oaep_encrypt($m, $l = '')
	{
		$mLen = strlen($m);

		if ($mLen > $this->k - 2 * $this->hLen - 2) {
			user_error('Message too long', E_USER_NOTICE);
			return false;
		}

		$lHash = $this->hash->hash($l);
		$ps = str_repeat(chr(0), $this->k - $mLen - 2 * $this->hLen - 2);
		$db = $lHash . $ps . chr(1) . $m;
		$seed = $this->_random($this->hLen);
		$dbMask = $this->_mgf1($seed, $this->k - $this->hLen - 1);
		$maskedDB = $db ^ $dbMask;
		$seedMask = $this->_mgf1($maskedDB, $this->hLen);
		$maskedSeed = $seed ^ $seedMask;
		$em = chr(0) . $maskedSeed . $maskedDB;

		$m = $this->_os2ip($em);
		$c = $this->_rsaep($m);
		$c = $this->_i2osp($c, $this->k);

		return $c;
	}

	function _rsaes_oaep_decrypt($c, $l = '')
	{
		if (strlen($c) != $this->k || $this->k < 2 * $this->hLen + 2) {
			user_error('Decryption error', E_USER_NOTICE);
			return false;
		}

		$c = $this->_os2ip($c);
		$m = $this->_rsadp($c);
		if ($m === false) {
			user_error('Decryption error', E_USER_NOTICE);
			return false;
		}
		$em = $this->_i2osp($m, $this->k);

		$lHash = $this->hash->hash($l);
		$y = ord($em[0]);
		$maskedSeed = substr($em, 1, $this->hLen);
		$maskedDB = substr($em, $this->hLen + 1);
		$seedMask = $this->_mgf1($maskedDB, $this->hLen);
		$seed = $maskedSeed ^ $seedMask;
		$dbMask = $this->_mgf1($seed, $this->k - $this->hLen - 1);
		$db = $maskedDB ^ $dbMask;
		$lHash2 = substr($db, 0, $this->hLen);
		$m = substr($db, $this->hLen);
		if ($lHash != $lHash2) {
			user_error('Decryption error', E_USER_NOTICE);
			return false;
		}
		$m = ltrim($m, chr(0));
		if (ord($m[0]) != 1) {
			user_error('Decryption error', E_USER_NOTICE);
			return false;
		}

		return substr($m, 1);
	}

	function _rsaes_pkcs1_v1_5_encrypt($m)
	{
		$mLen = strlen($m);

		if ($mLen > $this->k - 11) {
			user_error('Message too long', E_USER_NOTICE);
			return false;
		}

		$ps = $this->_random($this->k - $mLen - 3, true);
		$em = chr(0) . chr(2) . $ps . chr(0) . $m;

		$m = $this->_os2ip($em);
		$c = $this->_rsaep($m);
		$c = $this->_i2osp($c, $this->k);

		return $c;
	}

	function _rsaes_pkcs1_v1_5_decrypt($c)
	{
		if (strlen($c) != $this->k) {
			user_error('Decryption error', E_USER_NOTICE);
			return false;
		}

		$c = $this->_os2ip($c);
		$m = $this->_rsadp($c);

		if ($m === false) {
			user_error('Decryption error', E_USER_NOTICE);
			return false;
		}
		$em = $this->_i2osp($m, $this->k);

		if (ord($em[0]) != 0 || ord($em[1]) > 2) {
			user_error('Decryption error', E_USER_NOTICE);
			return false;
		}

		$ps = substr($em, 2, strpos($em, chr(0), 2) - 2);
		$m = substr($em, strlen($ps) + 3);

		if (strlen($ps) < 8) {
			user_error('Decryption error', E_USER_NOTICE);
			return false;
		}

		return $m;
	}

	function _emsa_pss_encode($m, $emBits)
	{
		$emLen = ($emBits + 1) >> 3;
		$sLen = $this->sLen == false ? $this->hLen : $this->sLen;

		$mHash = $this->hash->hash($m);
		if ($emLen < $this->hLen + $sLen + 2) {
			user_error('Encoding error', E_USER_NOTICE);
			return false;
		}

		$salt = $this->_random($sLen);
		$m2 = "\0\0\0\0\0\0\0\0" . $mHash . $salt;
		$h = $this->hash->hash($m2);
		$ps = str_repeat(chr(0), $emLen - $sLen - $this->hLen - 2);
		$db = $ps . chr(1) . $salt;
		$dbMask = $this->_mgf1($h, $emLen - $this->hLen - 1);
		$maskedDB = $db ^ $dbMask;
		$maskedDB[0] = ~chr(0xFF << ($emBits & 7)) & $maskedDB[0];
		$em = $maskedDB . $h . chr(0xBC);

		return $em;
	}

	function _emsa_pss_verify($m, $em, $emBits)
	{
		$emLen = ($emBits + 1) >> 3;
        $sLen = $this->sLen == false ? $this->hLen : $this->sLen;

		$mHash = $this->hash->hash($m);
		if ($emLen < $this->hLen + $sLen + 2) {
			return false;
		}

		if ($em[strlen($em) - 1] != chr(0xBC)) {
			return false;
		}

		$maskedDB = substr($em, 0, -$this->hLen - 1);
		$h = substr($em, -$this->hLen - 1, $this->hLen);
		$temp = chr(0xFF << ($emBits & 7));
		if ((~$maskedDB[0] & $temp) != $temp) {
			return false;
		}
		$dbMask = $this->_mgf1($h, $emLen - $this->hLen - 1);
		$db = $maskedDB ^ $dbMask;
		$db[0] = ~chr(0xFF << ($emBits & 7)) & $db[0];
		$temp = $emLen - $this->hLen - $sLen - 2;
		if (substr($db, 0, $temp) != str_repeat(chr(0), $temp) || ord($db[$temp]) != 1) {
			return false;
		}
		$salt = substr($db, $temp + 1);
		$m2 = "\0\0\0\0\0\0\0\0" . $mHash . $salt;
		$h2 = $this->hash->hash($m2);
		return $h == $h2;
	}

	function _rsassa_pss_sign($m)
	{
		$em = $this->_emsa_pss_encode($m, 8 * $this->k - 1);

		$m = $this->_os2ip($em);
		$s = $this->_rsasp1($m);
		$s = $this->_i2osp($s, $this->k);

		return $s;
	}

	function _rsassa_pss_verify($m, $s)
	{
		if (strlen($s) != $this->k) {
			user_error('Invalid signature', E_USER_NOTICE);
			return false;
		}

		$modBits = 8 * $this->k;

		$s2 = $this->_os2ip($s);
		$m2 = $this->_rsavp1($s2);
		if ($m2 === false) {
			user_error('Invalid signature', E_USER_NOTICE);
			return false;
		}
		$em = $this->_i2osp($m2, $modBits >> 3);
		if ($em === false) {
			user_error('Invalid signature', E_USER_NOTICE);
			return false;
		}

		return $this->_emsa_pss_verify($m, $em, $modBits - 1);
	}

	function _emsa_pkcs1_v1_5_encode($m, $emLen)
	{
		$h = $this->hash->hash($m);
		if ($h === false) {
			return false;
		}

		switch ($this->hashName) {
			case 'md2':
				$t = pack('H*', '3020300c06082a864886f70d020205000410');
				break;
			case 'md5':
				$t = pack('H*', '3020300c06082a864886f70d020505000410');
				break;
			case 'sha1':
				$t = pack('H*', '3021300906052b0e03021a05000414');
				break;
			case 'sha256':
				$t = pack('H*', '3031300d060960864801650304020105000420');
				break;
			case 'sha384':
				$t = pack('H*', '3041300d060960864801650304020205000430');
				break;
			case 'sha512':
				$t = pack('H*', '3051300d060960864801650304020305000440');
		}
		$t.= $h;
		$tLen = strlen($t);

		if ($emLen < $tLen + 11) {
			user_error('Intended encoded message length too short', E_USER_NOTICE);
			return false;
		}

		$ps = str_repeat(chr(0xFF), $emLen - $tLen - 3);

		$em = "\0\1$ps\0$t";

		return $em;
	}

	function _rsassa_pkcs1_v1_5_sign($m)
	{
		$em = $this->_emsa_pkcs1_v1_5_encode($m, $this->k);
		if ($em === false) {
			user_error('RSA modulus too short', E_USER_NOTICE);
			return false;
		}

		$m = $this->_os2ip($em);
		$s = $this->_rsasp1($m);
		$s = $this->_i2osp($s, $this->k);

		return $s;
	}

	function _rsassa_pkcs1_v1_5_verify($m, $s)
	{
		if (strlen($s) != $this->k) {
			user_error('Invalid signature', E_USER_NOTICE);
			return false;
		}

		$s = $this->_os2ip($s);
		$m2 = $this->_rsavp1($s);
		if ($m2 === false) {
			user_error('Invalid signature', E_USER_NOTICE);
			return false;
		}
		$em = $this->_i2osp($m2, $this->k);
		if ($em === false) {
			user_error('Invalid signature', E_USER_NOTICE);
			return false;
		}

		$em2 = $this->_emsa_pkcs1_v1_5_encode($m, $this->k);
		if ($em2 === false) {
			user_error('RSA modulus too short', E_USER_NOTICE);
			return false;
		}

		return $em === $em2;
	}

	function setEncryptionMode($mode)
	{
		$this->encryptionMode = $mode;
	}

	function setSignatureMode($mode)
	{
		$this->signatureMode = $mode;
	}

	function encrypt($plaintext)
	{
		switch ($this->encryptionMode) {
			case CRYPT_RSA_ENCRYPTION_PKCS1:
				$length = $this->k - 11;
				if ($length <= 0) {
					return false;
				}

				$plaintext = str_split($plaintext, $length);
				$ciphertext = '';
				foreach ($plaintext as $m) {
					$ciphertext.= $this->_rsaes_pkcs1_v1_5_encrypt($m);
				}
				return $ciphertext;
						default:
				$length = $this->k - 2 * $this->hLen - 2;
				if ($length <= 0) {
					return false;
				}

				$plaintext = str_split($plaintext, $length);
				$ciphertext = '';
				foreach ($plaintext as $m) {
					$ciphertext.= $this->_rsaes_oaep_encrypt($m);
				}
				return $ciphertext;
		}
	}

	function decrypt($ciphertext)
	{
		if ($this->k <= 0) {
			return false;
		}

		$ciphertext = str_split($ciphertext, $this->k);
		$plaintext = '';

		switch ($this->encryptionMode) {
			case CRYPT_RSA_ENCRYPTION_PKCS1:
				$decrypt = '_rsaes_pkcs1_v1_5_decrypt';
				break;
						default:
				$decrypt = '_rsaes_oaep_decrypt';
		}

		foreach ($ciphertext as $c) {
			$temp = $this->$decrypt($c);
			if ($temp === false) {
				return false;
			}
			$plaintext.= $temp;
		}

		return $plaintext;
	}

	function sign($message)
	{
		if (empty($this->modulus) || empty($this->exponent)) {
			return false;
		}

		switch ($this->signatureMode) {
			case CRYPT_RSA_SIGNATURE_PKCS1:
				return $this->_rsassa_pkcs1_v1_5_sign($message);
						default:
				return $this->_rsassa_pss_sign($message);
		}
	}

	function verify($message, $signature)
	{
		if (empty($this->modulus) || empty($this->exponent)) {
			return false;
		}

		switch ($this->signatureMode) {
			case CRYPT_RSA_SIGNATURE_PKCS1:
				return $this->_rsassa_pkcs1_v1_5_verify($message, $signature);
						default:
				return $this->_rsassa_pss_verify($message, $signature);
		}
	}
}

class Crypt_AES extends Crypt_Rijndael
{
	protected
		$enmcrypt,
		$demcrypt,
		$ecb;

	function __construct($mode = CRYPT_AES_MODE_CBC)
	{
		if ( !defined('CRYPT_AES_MODE') ) {
			switch (true) {
				case extension_loaded('mcrypt'):
					define('CRYPT_AES_MODE', CRYPT_AES_MODE_MCRYPT);
					break;
				default:
					define('CRYPT_AES_MODE', CRYPT_AES_MODE_INTERNAL);
			}
		}

		switch ( CRYPT_AES_MODE ) {
			case CRYPT_AES_MODE_MCRYPT:
				switch ($mode) {
					case CRYPT_AES_MODE_ECB:
						$this->paddable = true;
						$this->mode = MCRYPT_MODE_ECB;
						break;
					case CRYPT_AES_MODE_CTR:
						$this->mode = 'ctr';
						break;
					case CRYPT_AES_MODE_CFB:
						$this->mode = 'ncfb';
						break;
					case CRYPT_AES_MODE_OFB:
						$this->mode = MCRYPT_MODE_NOFB;
						break;
					case CRYPT_AES_MODE_CBC:
					default:
						$this->paddable = true;
						$this->mode = MCRYPT_MODE_CBC;
				}

				$this->debuffer = $this->enbuffer = '';

				break;
			default:
				switch ($mode) {
					case CRYPT_AES_MODE_ECB:
						$this->paddable = true;
						$this->mode = CRYPT_RIJNDAEL_MODE_ECB;
						break;
					case CRYPT_AES_MODE_CTR:
						$this->mode = CRYPT_RIJNDAEL_MODE_CTR;
						break;
					case CRYPT_AES_MODE_CFB:
						$this->mode = CRYPT_RIJNDAEL_MODE_CFB;
						break;
					case CRYPT_AES_MODE_OFB:
						$this->mode = CRYPT_RIJNDAEL_MODE_OFB;
						break;
					case CRYPT_AES_MODE_CBC:
					default:
						$this->paddable = true;
						$this->mode = CRYPT_RIJNDAEL_MODE_CBC;
				}
		}

		if (CRYPT_AES_MODE == CRYPT_AES_MODE_INTERNAL) {
			parent::Crypt_Rijndael($this->mode);
		}
	}

	function setBlockLength($length)
	{
		return;
	}

	function encrypt($plaintext)
	{
		if ( CRYPT_AES_MODE == CRYPT_AES_MODE_MCRYPT ) {
			$changed = $this->changed;
			$this->_mcryptSetup();

												if ($this->mode == 'ncfb') {
				if ($changed) {
					$this->ecb = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
					mcrypt_generic_init($this->ecb, $this->key, "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");
				}

				if (strlen($this->enbuffer)) {
					$ciphertext = $plaintext ^ substr($this->encryptIV, strlen($this->enbuffer));
					$this->enbuffer.= $ciphertext;
					if (strlen($this->enbuffer) == 16) {
						$this->encryptIV = $this->enbuffer;
						$this->enbuffer = '';
						mcrypt_generic_init($this->enmcrypt, $this->key, $this->encryptIV);
					}
					$plaintext = substr($plaintext, strlen($ciphertext));
				} else {
					$ciphertext = '';
				}

				$last_pos = strlen($plaintext) & 0xFFFFFFF0;
				$ciphertext.= $last_pos ? mcrypt_generic($this->enmcrypt, substr($plaintext, 0, $last_pos)) : '';

				if (strlen($plaintext) & 0xF) {
					if (strlen($ciphertext)) {
						$this->encryptIV = substr($ciphertext, -16);
					}
					$this->encryptIV = mcrypt_generic($this->ecb, $this->encryptIV);
					$this->enbuffer = substr($plaintext, $last_pos) ^ $this->encryptIV;
					$ciphertext.= $this->enbuffer;
				}

				return $ciphertext;
			}

			if ($this->paddable) {
				$plaintext = $this->_pad($plaintext);
			}

			$ciphertext = mcrypt_generic($this->enmcrypt, $plaintext);

			if (!$this->continuousBuffer) {
				mcrypt_generic_init($this->enmcrypt, $this->key, $this->iv);
			}

			return $ciphertext;
		}

		return parent::encrypt($plaintext);
	}

	function decrypt($ciphertext)
	{
		if ( CRYPT_AES_MODE == CRYPT_AES_MODE_MCRYPT ) {
			$changed = $this->changed;
			$this->_mcryptSetup();

			if ($this->mode == 'ncfb') {
				if ($changed) {
					$this->ecb = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
					mcrypt_generic_init($this->ecb, $this->key, "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");
				}

				if (strlen($this->debuffer)) {
					$plaintext = $ciphertext ^ substr($this->decryptIV, strlen($this->debuffer));

					$this->debuffer.= substr($ciphertext, 0, strlen($plaintext));
					if (strlen($this->debuffer) == 16) {
						$this->decryptIV = $this->debuffer;
						$this->debuffer = '';
						mcrypt_generic_init($this->demcrypt, $this->key, $this->decryptIV);
					}
					$ciphertext = substr($ciphertext, strlen($plaintext));
				} else {
					$plaintext = '';
				}

				$last_pos = strlen($ciphertext) & 0xFFFFFFF0;
				$plaintext.= $last_pos ? mdecrypt_generic($this->demcrypt, substr($ciphertext, 0, $last_pos)) : '';

				if (strlen($ciphertext) & 0xF) {
					if (strlen($plaintext)) {
						$this->decryptIV = substr($ciphertext, $last_pos - 16, 16);
					}
					$this->decryptIV = mcrypt_generic($this->ecb, $this->decryptIV);
					$this->debuffer = substr($ciphertext, $last_pos);
					$plaintext.= $this->debuffer ^ $this->decryptIV;
				}

				return $plaintext;
			}

			if ($this->paddable) {
												$ciphertext = str_pad($ciphertext, (strlen($ciphertext) + 15) & 0xFFFFFFF0, chr(0));
			}

			$plaintext = mdecrypt_generic($this->demcrypt, $ciphertext);

			if (!$this->continuousBuffer) {
				mcrypt_generic_init($this->demcrypt, $this->key, $this->iv);
			}

			return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
		}

		return parent::decrypt($ciphertext);
	}

	function _mcryptSetup()
	{
		if (!$this->changed) {
			return;
		}

		if (!$this->explicit_key_length) {
						$length = strlen($this->key) >> 2;
			if ($length > 8) {
				$length = 8;
			} else if ($length < 4) {
				$length = 4;
			}
			$this->Nk = $length;
			$this->key_size = $length << 2;
		}

		switch ($this->Nk) {
			case 4:                 $this->key_size = 16;
				break;
			case 5:             case 6:                 $this->key_size = 24;
				break;
			case 7:             case 8:                 $this->key_size = 32;
		}

		$this->key = str_pad(substr($this->key, 0, $this->key_size), $this->key_size, chr(0));
		$this->encryptIV = $this->decryptIV = $this->iv = str_pad(substr($this->iv, 0, 16), 16, chr(0));

		if (!isset($this->enmcrypt)) {
			$mode = $this->mode;

			$this->demcrypt = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', $mode, '');
			$this->enmcrypt = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', $mode, '');
		}
		mcrypt_generic_init($this->demcrypt, $this->key, $this->iv);
		mcrypt_generic_init($this->enmcrypt, $this->key, $this->iv);

		$this->changed = false;
	}

	function _encryptBlock($in)
	{
		$state = unpack('N*word', $in);

		$Nr = $this->Nr;
		$w = $this->w;
		$t0 = $this->t0;
		$t1 = $this->t1;
		$t2 = $this->t2;
		$t3 = $this->t3;

				$state = array(
			$state['word1'] ^ $w[0][0],
			$state['word2'] ^ $w[0][1],
			$state['word3'] ^ $w[0][2],
			$state['word4'] ^ $w[0][3]
		);

								for ($round = 1; $round < $this->Nr; $round++) {
			$state = array(
				$t0[$state[0] & 0xFF000000] ^ $t1[$state[1] & 0x00FF0000] ^ $t2[$state[2] & 0x0000FF00] ^ $t3[$state[3] & 0x000000FF] ^ $w[$round][0],
				$t0[$state[1] & 0xFF000000] ^ $t1[$state[2] & 0x00FF0000] ^ $t2[$state[3] & 0x0000FF00] ^ $t3[$state[0] & 0x000000FF] ^ $w[$round][1],
				$t0[$state[2] & 0xFF000000] ^ $t1[$state[3] & 0x00FF0000] ^ $t2[$state[0] & 0x0000FF00] ^ $t3[$state[1] & 0x000000FF] ^ $w[$round][2],
				$t0[$state[3] & 0xFF000000] ^ $t1[$state[0] & 0x00FF0000] ^ $t2[$state[1] & 0x0000FF00] ^ $t3[$state[2] & 0x000000FF] ^ $w[$round][3]
			);

		}

				$state = array(
			$this->_subWord($state[0]),
			$this->_subWord($state[1]),
			$this->_subWord($state[2]),
			$this->_subWord($state[3])
		);

				$state = array(
			($state[0] & 0xFF000000) ^ ($state[1] & 0x00FF0000) ^ ($state[2] & 0x0000FF00) ^ ($state[3] & 0x000000FF) ^ $this->w[$this->Nr][0],
			($state[1] & 0xFF000000) ^ ($state[2] & 0x00FF0000) ^ ($state[3] & 0x0000FF00) ^ ($state[0] & 0x000000FF) ^ $this->w[$this->Nr][1],
			($state[2] & 0xFF000000) ^ ($state[3] & 0x00FF0000) ^ ($state[0] & 0x0000FF00) ^ ($state[1] & 0x000000FF) ^ $this->w[$this->Nr][2],
			($state[3] & 0xFF000000) ^ ($state[0] & 0x00FF0000) ^ ($state[1] & 0x0000FF00) ^ ($state[2] & 0x000000FF) ^ $this->w[$this->Nr][3]
		);

		return pack('N*', $state[0], $state[1], $state[2], $state[3]);
	}

	function _decryptBlock($in)
	{
		$state = unpack('N*word', $in);

		$Nr = $this->Nr;
		$dw = $this->dw;
		$dt0 = $this->dt0;
		$dt1 = $this->dt1;
		$dt2 = $this->dt2;
		$dt3 = $this->dt3;

				$state = array(
			$state['word1'] ^ $dw[$this->Nr][0],
			$state['word2'] ^ $dw[$this->Nr][1],
			$state['word3'] ^ $dw[$this->Nr][2],
			$state['word4'] ^ $dw[$this->Nr][3]
		);


				for ($round = $this->Nr - 1; $round > 0; $round--) {
			$state = array(
				$dt0[$state[0] & 0xFF000000] ^ $dt1[$state[3] & 0x00FF0000] ^ $dt2[$state[2] & 0x0000FF00] ^ $dt3[$state[1] & 0x000000FF] ^ $dw[$round][0],
				$dt0[$state[1] & 0xFF000000] ^ $dt1[$state[0] & 0x00FF0000] ^ $dt2[$state[3] & 0x0000FF00] ^ $dt3[$state[2] & 0x000000FF] ^ $dw[$round][1],
				$dt0[$state[2] & 0xFF000000] ^ $dt1[$state[1] & 0x00FF0000] ^ $dt2[$state[0] & 0x0000FF00] ^ $dt3[$state[3] & 0x000000FF] ^ $dw[$round][2],
				$dt0[$state[3] & 0xFF000000] ^ $dt1[$state[2] & 0x00FF0000] ^ $dt2[$state[1] & 0x0000FF00] ^ $dt3[$state[0] & 0x000000FF] ^ $dw[$round][3]
			);
		}

				$state = array(
			$this->_invSubWord(($state[0] & 0xFF000000) ^ ($state[3] & 0x00FF0000) ^ ($state[2] & 0x0000FF00) ^ ($state[1] & 0x000000FF)) ^ $dw[0][0],
			$this->_invSubWord(($state[1] & 0xFF000000) ^ ($state[0] & 0x00FF0000) ^ ($state[3] & 0x0000FF00) ^ ($state[2] & 0x000000FF)) ^ $dw[0][1],
			$this->_invSubWord(($state[2] & 0xFF000000) ^ ($state[1] & 0x00FF0000) ^ ($state[0] & 0x0000FF00) ^ ($state[3] & 0x000000FF)) ^ $dw[0][2],
			$this->_invSubWord(($state[3] & 0xFF000000) ^ ($state[2] & 0x00FF0000) ^ ($state[1] & 0x0000FF00) ^ ($state[0] & 0x000000FF)) ^ $dw[0][3]
		);

		return pack('N*', $state[0], $state[1], $state[2], $state[3]);
	}
}

