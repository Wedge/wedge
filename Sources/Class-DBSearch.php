<?php
/**
 * Wedge
 *
 * Handles some of the internal functionality required for managing search in the database.
 *
 * @package wedge
 * @copyright 2010-2011 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

class wedbSearch
{
	protected static $instance; // container for self

	public static function getInstance()
	{
		// Quero ergo sum
		if (self::$instance == null)
			self::$instance = new self();

		return self::$instance;
	}

	public static function supports($search_type)
	{
		$supported_types = array('fulltext');

		return in_array($search_type, $supported_types);
	}

	public static function create_word_search($size)
	{
		if ($size == 'small')
			$size = 'smallint(5)';
		elseif ($size == 'medium')
			$size = 'mediumint(8)';
		else
			$size = 'int(10)';

		wesql::query('
			CREATE TABLE {db_prefix}log_search_words (
				id_word {raw:size} unsigned NOT NULL default {string:string_zero},
				id_msg int(10) unsigned NOT NULL default {string:string_zero},
				PRIMARY KEY (id_word, id_msg)
			) ENGINE=InnoDB',
			array(
				'string_zero' => '0',
				'size' => $size,
			)
		);
	}
}

?>