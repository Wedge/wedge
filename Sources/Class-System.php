<?php
/**
 * Wedge
 *
 * System class: user agent analyzer, user information...
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

class we
{
	protected static $instance; // container for self
	static $ua;					// User agent string (we::$ua)
	static $browser;			// Browser array

	// What kind of class are you, anyway? One of a kind!
	private function __clone()
	{
		return false;
	}

	static function getInstance()
	{
		// Quero ergo sum
		if (self::$instance == null)
		{
			self::$instance = new self();
			self::$instance->detect();
		}

		return self::$instance;
	}

	/**
	 * Attempts to detect the browser, including version, needed for browser specific fixes and behaviours, and populates we::$browser with the findings.
	 *
	 * In all cases, general branch as well as major version is detected for, meaning that not only would Internet Explorer 8 be detected, so would Internet Explorer generically. This also sets flags for general emulation behavior later on, plus handling some types of robot.
	 *
	 * Current browsers detected via self::$browser['agent']:
	 * - Opera
	 * - Firefox
	 * - Chrome
	 * - Safari
	 * - Webkit (used in Safari, Chrome, Android stock browser...)
	 * - Gecko engine (used in Firefox and compatible)
	 * - Internet Explorer (plus tests for IE6 and above)
	 *
	 * Current OSes detected via self::$browser['os']:
	 * - iOS (Safari Mobile is the only browser engine allowed on iPhone, iPod, iPad etc.)
	 * - Android
	 * - Windows (and versions equal to or above XP)
	 * - More generic mobile devices also available through we::is_mobile()
	 */
	static function detect()
	{
		global $context, $user_info;

		// The following determines the user agent (browser) as best it can.
		$ua = $_SERVER['HTTP_USER_AGENT'];
		$browser['is_opera'] = strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== false;

		// Detect Webkit and related
		$browser['is_webkit'] = $is_webkit = strpos($ua, 'AppleWebKit') !== false;
		$browser['is_chrome'] = $is_webkit && (strpos($ua, 'Chrome') !== false || strpos($ua, 'CriOS') !== false);
		$browser['is_safari'] = $is_webkit && !$browser['is_chrome'] && strpos($ua, 'Safari') !== false;

		// Detecting broader mobile browsers. Make sure you rely on skin.xml's <mobile> setting in priority.
		$browser['is_mobile'] = !empty($user_info['is_mobile']);

		// Detect Firefox versions
		$browser['is_gecko'] = !$is_webkit && strpos($ua, 'Gecko') !== false;	// Mozilla and compatible
		$browser['is_firefox'] = strpos($ua, 'Gecko/') !== false;				// Firefox says "Gecko/20xx", not "like Gecko"

		// Internet Explorer is often "emulated".
		$browser['is_ie'] = $is_ie = !$browser['is_opera'] && !$browser['is_gecko'] && strpos($ua, 'MSIE') !== false;

		// Retrieve the version number, as a floating point.
		// Chrome for iOS uses the Safari Mobile string and replaces Version with CriOS.
		preg_match('~' . (
				$browser['is_opera'] || $browser['is_safari'] ? 'version[/ ]' :
				($browser['is_firefox'] ? 'firefox/' :
				($browser['is_ie'] ? 'msie ' :
				($browser['is_chrome'] ? 'c(?:hrome|rios)/' :
				'applewebkit/')))
			) . '([\d.]+)~i', $ua, $ver)
		|| preg_match('~(?:version|opera)[/ ]([\d.]+)~i', $ua, $ver);
		$ver = isset($ver[1]) ? (float) $ver[1] : 0;

	/* WIP...

		// No need storing version numbers for outdated versions.
		if ($browser['is_opera'])		$ver = max(8, $ver);
		elseif ($browser['is_chrome'])	$ver = max(18, $ver);
		elseif ($browser['is_firefox'])	$ver = max(14, $ver);
		elseif ($browser['is_ie'])		$ver = max(6, $ver);
	*/

		// Reduce to first significant sub-version (if any), e.g. v2.01 => 2, v2.50.3 => 2.5
		$browser['version'] = floor($ver * 10) / 10;

		$browser['is_ie8down'] = $is_ie && $ver <= 8;
		for ($i = 6; $i <= 10; $i++)
			$browser['is_ie' . $i] = $is_ie && $ver == $i;

		// Store our browser name... Start with specific browsers, end with generic engines.
		foreach (array('opera', 'chrome', 'firefox', 'ie', 'safari', 'webkit', 'gecko', '') as $agent)
		{
			$browser['agent'] = $agent;
			if (!$agent || $browser['is_' . $agent])
				break;
		}

		// Determine current OS and version if it can turn out to be useful; currently
		// Windows XP and above, or iOS 4 and above, or Android 2 and above.
		// !! Should we add BlackBerry, Firefox OS and others..?
		$browser['is_windows'] = strpos($ua, 'Windows ') !== false;
		$browser['is_android'] = strpos($ua, 'Android') !== false;
		$browser['is_ios'] = $is_webkit && strpos($ua, '(iP') !== false;
		if ($browser['is_windows'])
		{
			if (preg_match('~Windows(?: NT)? (\d+\.\d+)~', $ua, $ver))
				$os_ver = max(5.1, (float) $ver[1]);
			// Fallback, just to be sure.
			else
				foreach (array('8' => 6.2, '7' => 6.1, 'Vista' => 6, 'XP' => 5.1) as $key => $os_ver)
					if (strpos($ua, 'Windows ' . $key) !== false)
						break;
		}
		elseif ($browser['is_android'] && preg_match('~Android(?: (\d+\.\d))?~', $ua, $ver))
			$os_ver = max(2, (float) $ver[1]);
		elseif ($browser['is_ios'] && preg_match('~ OS (\d+(?:_\d))~', $ua, $ver))
			$os_ver = max(3, (float) str_replace('_', '.', $ver[1]));

		$browser['os'] = '';
		foreach (array('windows', 'android', 'ios') as $os)
			if ($browser['is_' . $os])
				$browser['os'] = $os;

		// !! Note that rounding to an integer (instead of the first significant sub-version)
		// could probably help reduce the number of cached files by a large margin. Opinions?
		$browser['os_version'] = isset($os_ver) ? floor($os_ver * 10) / 10 : '';

		// This isn't meant to be reliable, it's just meant to catch most bots to prevent PHPSESSID from showing up.
		$browser['possibly_robot'] = !empty($user_info['possibly_robot']);

		// Robots shouldn't be logging in or registering. So, they aren't a bot. Better to be wrong than sorry (or people won't be able to log in!), anyway.
		if ((isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('login', 'login2', 'register'))) || empty($user_info['is_guest']))
			$browser['possibly_robot'] = false;

		// Save the results...
		self::$ua = $ua;
		self::$browser = $browser;

		// And we'll also let you modify the browser array ASAP.
		call_hook('detect_browser');
	}

	// Mobile detection code is based on an early version of Mobile_Detect (MIT license).
	static function is_mobile()
	{
		if (empty($_SERVER['HTTP_USER_AGENT']))
			return false;

		$ua = strtolower($_SERVER['HTTP_USER_AGENT']);

		if (isset($_SERVER['HTTP_PROFILE']) || isset($_SERVER['HTTP_X_WAP_PROFILE']) || isset($_SERVER['HTTP_X_OPERAMINI_PHONE_UA']))
			return true;

		if (isset($_SERVER['HTTP_ACCEPT']) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/vnd.wap.wml') !== false || strpos($_SERVER['HTTP_ACCEPT'], 'application/vnd.wap.xhtml+xml') !== false))
			return true;

		foreach (explode('|', implode('|', array(
			'Generic' => 'mobile',
			'iOS' => 'iphone|ipod|ipad',
			'Android' => 'android',
			'BlackBerry' => 'blackberry|rim tablet',
			'Symbian' => 'symbian',
			'Windows' => 'windows ce|windows phone',
			'PalmOS' => 'palm|avantgo|plucker|xiino',
			'Others' => 'kindle|silk|samsung|htc|playstation|nintendo|wap|up.|bolt|opera mobi'
		))) as $device)
			if (strpos($ua, $device) !== false)
				return true;

		return false;
	}

	/**
	 * Alias to the analyzer. Send in a short, simple string.
	 */
	static function is($string)
	{
		global $user_info;
		static $cache = array();

		if ($string === (array) $string)
			return self::analyze($string);
		if (isset($cache[$string]))
			return $cache[$string];

		if (isset($user_info['is_' . $string]))
			return $cache[$string] = !empty($user_info['is_' . $string]);
		if (isset(self::$browser['is_' . $string]))
			return $cache[$string] = !empty(self::$browser['is_' . $string]);

		return $cache[$string] = self::analyze($string);
	}

	/**
	 * Analyzes the given array keys (or comma-separated list), and tries to determine if it encompasses the current browser version.
	 * Can deal with relatively complex strings. e.g., "firefox, !mobile && ie[-7]" means "if browser is Firefox, or is a desktop version of IE 6 or IE 7".
	 * Returns the string that was recognized as the browser, or false if nothing was found.
	 */
	function analyze($strings)
	{
		if (!is_array($strings))
			$strings = array_flip(array_map('trim', explode(',', $strings)));

		$browser = self::$browser;
		$a = $browser['agent'];
		$o = $browser['os'];
		$bv = $browser['version'];
		$ov = $browser['os_version'];

		// A quick browser test.
		if (isset($strings[$a])) return $a;											// Example match: ie (any version of the browser.)
		if (isset($strings[$a . $bv])) return $a . $bv;								// ie7 (only)
		if (isset($strings[$a . '[' . $bv . ']'])) return $a . '[' . $bv . ']';		// ie[7] (same as above)
		if (isset($strings[$a . '[-' . $bv . ']'])) return $a . '[-' . $bv . ']';	// ie[-7] (up to version 7)
		if (isset($strings[$a . '[' . $bv . '-]'])) return $a . '[' . $bv . '-]';	// ie[7-] (version 7 and above)

		// A quick OS test.
		if (isset($strings[$o])) return $o;											// Example match: windows (any version of the OS.)
		if (isset($strings[$o . $ov])) return $o . $ov;								// windows6.1 (only Windows 7)
		if (isset($strings[$o . '[' . $ov . ']'])) return $o . '[' . $ov . ']';		// windows[6.1] (same as above)
		if (isset($strings[$o . '[-' . $ov . ']'])) return $o . '[-' . $ov . ']';	// windows[-6.1] (up to Windows 7)
		if (isset($strings[$o . '[' . $ov . '-]'])) return $o . '[' . $ov . '-]';	// windows[6.1-] (Windows 7 and above)

		$alength = strlen($a) + 1;
		$olength = strlen($o) + 1;

		// Okay, so maybe we're looking for a wider range?
		foreach ($strings as $string => $dummy)
		{
			$and = strpos($string, '&'); // Is there a && or & in the query? Meaning all parts of this one should return true.
			if ($and !== false)
			{
				$test_all = true;
				foreach (array_map('trim', preg_split('~&+~', $string)) as $finger)
					$test_all &= self::is($finger) !== false;
				if ($test_all)
					return $string;
				continue;
			}

			$bracket = strpos($string, '['); // Is there a version request?
			$real_browser = $bracket === false ? $string : substr($string, 0, $bracket);

			// First, negative tests.
			if ($string[0] === '!')
			{
				$is_os_test = $browser['os'] == substr($real_browser, 1);
				if (empty($browser['is_' . substr($real_browser, 1)]))
					return $string;
				if ($bracket === false)
					continue;
				$split = explode('-', trim(substr($string, $is_os_test ? $olength : $alength, -1), ' ]'));
				$v = $is_os_test ? $ov : $bv;
				if (isset($split[1]))
				{
					if (empty($split[0]) && $v <= $split[1]) continue;	// !ie[-8] (isn't version 8 or earlier)
					if (empty($split[1]) && $v >= $split[0]) continue;	// !ie[6-] (isn't version 6 or later)
					if ($v >= $split[0] && $v <= $split[1]) continue;	// !ie[6-8] (isn't version 6, 7 or 8)
				}
				elseif ($v == $split[0]) continue;						// !ie[8] or !ie[8.0], FWIW...
				return $string;
			}

			// And now, positive tests.
			if (empty($browser['is_' . $real_browser]))
				continue;
			if ($bracket === false)
				return $string;
			$is_os_test = $browser['os'] == $real_browser;
			$split = explode('-', trim(substr($string, $is_os_test ? $olength : $alength, -1), ' ]'));
			$v = $is_os_test ? $ov : $bv;
			if (isset($split[1]))
			{
				if (empty($split[0]) && $v <= $split[1]) return $string;	// ie[-8] (version 8 or earlier)
				if (empty($split[1]) && $v >= $split[0]) return $string;	// ie[6-] (version 6 or later)
				if ($v >= $split[0] && $v <= $split[1]) return $string;		// ie[6-8] (version 6, 7 or 8)
			}
			elseif ($v == $split[0]) return $string;						// ie[8] or ie[8.0], FWIW...
		}

		return false;
	}
}

?>