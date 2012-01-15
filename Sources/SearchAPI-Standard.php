<?php
/**
 * Wedge
 *
 * Provides information about the standard (unindexed) search method back to the Search framework.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

class standard_search
{
	// Standard search is supported by default.
	public $is_supported = true;

	// Method to check whether the method can be performed by the API.
	public function supportsMethod($methodName, $query_params = null)
	{
		// Always fall back to the standard search method.
		return false;
	}
}

?>