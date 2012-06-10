<?php
/**
 * Wedge
 *
 * Mobile browser detector. Very simplistic code, based on an early version of Mobile_Detect (MIT license).
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

class weMoDe
{
	public function isMobile()
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
}

?>