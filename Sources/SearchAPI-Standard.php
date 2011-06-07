<?php
/**
 * Wedge
 *
 * Provides information about the standard (unindexed) search method back to the Search framework.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('SMF'))
	die('Hacking attempt...');

class standard_search
{
	// This is the last version of SMF that this was tested on, to protect against API changes.
	public $version_compatible = 'SMF 2.0';

	// This won't work with versions of SMF less than this.
	public $min_smf_version = 'SMF 2.0 Beta 2';

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