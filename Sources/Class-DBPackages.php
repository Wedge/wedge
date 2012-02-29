<?php
/**
 * Wedge
 *
 * Contains several package-related database operations, like creating new tables.
 *
 * @package wedge
 * @copyright 2010-2012 Wedgeward, wedge.org
 * @license http://wedge.org/license/
 *
 * @version 0.1
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

class wedbPackages
{
	protected static $reservedTables, $instance;

	public static function getInstance()
	{
		global $db_prefix;

		// Quero ergo sum
		if (self::$instance == null)
		{
			// Things we do on creation; it's like a constructor but not quite.
			self::$instance = new self();
			self::$reservedTables = array();

			$reservedTables = array('admin_info_files', 'approval_queue', 'attachments', 'ban_groups', 'ban_items',
			'board_members', 'board_permissions', 'boards', 'categories', 'collapsed_categories',
			'custom_fields', 'drafts', 'group_moderators', 'log_actions', 'log_activity', 'log_boards', 'log_comments',
			'log_digest', 'log_errors', 'log_floodcontrol', 'log_group_requests', 'log_intrusion', 'log_mark_read', 'log_notify',
			'log_online', 'log_packages', 'log_polls', 'log_reported', 'log_reported_comments', 'log_scheduled_tasks',
			'log_search_messages', 'log_search_results', 'log_search_subjects', 'log_search_topics', 'log_topics', 'mail_queue',
			'membergroups', 'members', 'message_icons', 'messages', 'moderators', 'package_servers',
			'permission_profiles', 'permissions', 'personal_messages', 'pm_recipients', 'pm_rules', 'poll_choices', 'polls',
			'pretty_topic_urls', 'pretty_urls_cache', 'scheduled_tasks', 'sessions', 'settings', 'smileys', 'spiders',
			'subscriptions', 'subscriptions_groups', 'themes', 'topics');

			foreach ($reservedTables as $k => $table_name)
				self::$reservedTables[$k] = strtolower($db_prefix . $table_name);

			// We in turn may need the extra stuff.
			wesql::extend('extra');
		}

		return self::$instance;
	}

	// Create a table.
	public static function create_table($table_name, $columns, $indexes = array(), $if_exists = 'ignore', $error = 'fatal')
	{
		global $db_prefix;

		// Strip out the table name, we might not need it in some cases
		$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

		// With or without the database name, the fullname looks like this.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
		$orig_name = $table_name;
		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// First - no way do we touch Wedge tables.
		if (in_array(strtolower($table_name), self::$reservedTables))
			return false;

		$tables = wedbExtra::list_tables();
		$creating = true;
		if (in_array($full_table_name, $tables))
		{
			// This is a sad day... drop the table?
			if ($if_exists == 'overwrite')
				self::drop_table($table_name);
			elseif ($if_exists == 'error')
				return false;
			elseif ($if_exists == 'update')
				$creating = false;
		}

		if ($creating)
		{
			// Righty - let's do the damn thing!
			$table_query = 'CREATE TABLE ' . $table_name . "\n" . '(';
			foreach ($columns as $column)
			{
				// Auto increment is easy here!
				if (!empty($column['auto']))
				{
					$default = 'auto_increment';
				}
				elseif (isset($column['default']) && $column['default'] !== null && $column['type'] != 'text' && $column['type'] != 'mediumtext')
					$default = 'default \'' . wesql::escape_string($column['default']) . '\'';
				else
					$default = '';

				// Sort out the size... and stuff...
				$column['size'] = isset($column['size']) && is_numeric($column['size']) ? $column['size'] : null;

				// Allow unsigned integers
				$unsigned = in_array($column['type'], array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')) && !empty($column['unsigned']) ? 'unsigned ' : '';

				list ($type, $size) = self::calculate_type($column['type'], $column['size'], $unsigned);

				if ($size !== null)
					$type .= '(' . $size . ')';
				elseif (!empty($column['values']))
					$type .= '(' . $column['values'] . ')';

				// Now just put it together!
				$table_query .= "\n\t`" . $column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (!empty($column['null']) ? '' : 'NOT NULL') . ' ' . $default . ',';
			}

			// Loop through the indexes next...
			foreach ($indexes as $index)
			{
				$columns = implode(',', $index['columns']);

				// Is it the primary?
				if (isset($index['type']) && $index['type'] == 'primary')
					$table_query .= "\n\t" . 'PRIMARY KEY (' . implode(',', $index['columns']) . '),';
				else
				{
					if (empty($index['name']))
						$index['name'] = implode('_', $index['columns']);
					$table_query .= "\n\t" . (isset($index['type']) && $index['type'] == 'unique' ? 'UNIQUE' : 'KEY') . ' ' . $index['name'] . ' (' . $columns . '),';
				}
			}

			// No trailing commas!
			if (substr($table_query, -1) == ',')
				$table_query = substr($table_query, 0, -1);

			$table_query .= ') ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';
		}
		else
		{
			// Uh-oh, the table exists, so now we have to do funky stuff.
			$changes = array();

			// Firstly, get the current columns
			$current_columns = self::list_columns($orig_name, true);

			// Go through each of the columns of the requested table and figure out what's the same and what's different.
			$numeric_types = array('int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'float', 'double', 'real');
			foreach ($columns as $id => $column)
			{
				if (!isset($current_columns[$column['name']]))
				{
					// The column is new, add it to the list of columns to be added
					$column['size'] = isset($column['size']) && is_numeric($column['size']) ? $column['size'] : null;

					// Allow unsigned integers
					$unsigned = in_array($column['type'], $numeric_types) && !empty($column['unsigned']) ? 'unsigned ' : '';

					list ($type, $size) = self::calculate_type($column['type'], $column['size'], $unsigned);

					if ($size !== null)
						$type .= '(' . $size . ')';
					elseif (!empty($column['values']) && ($column['type'] == 'set' || $column['type'] == 'enum'))
						$type .= '(' . $column['values'] . ')';

					$changes[] = 'ADD `' . $column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (empty($column['null']) ? 'NOT NULL' : '') . ' ' .
						(!isset($column['default']) || $column['type'] == 'text' || $column['type'] == 'mediumtext' ? '' : 'default \'' . wesql::escape_string($column['default']) . '\'') . ' ' .
						(empty($column['auto']) ? '' : 'auto_increment primary key');
				}
				else
				{
					// The column already exists, does it need changing?
					$column['size'] = isset($column['size']) && is_numeric($column['size']) ? $column['size'] : null;

					// Allow unsigned integers
					$unsigned = in_array($column['type'], $numeric_types) && !empty($column['unsigned']) ? 'unsigned ' : '';

					list ($type, $size) = self::calculate_type($column['type'], $column['size'], $unsigned);

					$changing = false;
					if (in_array($column['type'], $numeric_types))
					{
						if ($column['type'] != $current_columns[$column['name']]['type'] || $size != $current_columns[$column['name']]['size'])
							$changing = true;

						$new_auto = !empty($column['auto']);
						$old_auto = !empty($current_column['auto']);
						$changing |= $old_auto != $new_auto;

						$new_unsigned = !empty($column['unsigned']);
						$old_unsigned = !empty($current_column['unsigned']);
						$changing |= $new_unsigned != $old_unsigned;
					}
					elseif ($column['type'] == 'char' || $column['type'] == 'varchar')
					{
						if ($column['type'] != $current_columns[$column['name']]['type'] || $size != $current_columns[$column['name']]['size'])
							$changing = true;
					}
					elseif (($column['type'] == 'set' || $column['type'] == 'enum') && !empty($column['values']))
					{
						// This is how it comes through from MySQL normally.
						$new_type = $column['type'] . '(' . $column['values'] . ')';
						if ($new_type != $current_columns[$column['name']]['type'])
							$changing = true;
					}
					elseif ($column['type'] != $current_columns[$column['name']]['type'])
						$changing = true;

					if ($column['type'] != 'text' && $column['type'] != 'mediumtext')
					{
						$old_default = !is_null($current_columns[$column['name']]['default']) ? $current_columns[$column['name']]['default'] : null;
						$new_default = isset($column['default']) ? $column['default'] : null;
						$changing = $old_default !== $new_default;
					}

					if ($changing)
					{
						if ($size !== null)
							$type .= '(' . $size . ')';
						elseif (!empty($column['values']) && ($column['type'] == 'set' || $column['type'] == 'enum'))
							$type .= '(' . $column['values'] . ')';

						$changes[] = 'MODIFY `' . $column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (empty($column['null']) ? 'NOT NULL' : '') . ' ' .
							(!isset($column['default']) || $column['type'] == 'text' || $column['type'] == 'mediumtext' ? '' : 'default \'' . wesql::escape_string($column['default']) . '\'') . ' ' .
							(empty($column['auto']) ? '' : 'auto_increment primary key');
					}
				}
			}

			$current_indexes = self::list_indexes($orig_name, true);

			/*	There's no safe automatic way to change primary or unique keys on a table without knowing the table's contents.
				This should be left to plugins to handle if they have to do it: the plugin manifest should list the clean install
				and an enable script should detect the state of any indexes that have to change and do it itself.

				The reality is that while this sounds like a huge limitation, any well designed plugin should have already accounted
				for this in its design, by not having to change something so fundamental in a table that existed in a prior version.
				Case example: changing primary key with existing data, if basing on a new column it's possible that the column
				will cause duplicate rows against the new primary key. */

			foreach ($indexes as $id => $index)
			{
				// So, skip any new indexes that are primary or unique, as per above.
				if ($index['type'] == 'primary' || $index['type'] == 'unique')
					continue;

				if (empty($index['name']))
					$index['name'] = implode('_', $index['columns']);

				if (empty($current_indexes[$index['name']]))
					$changes[] = 'ADD INDEX ' . $index['name'] . ' (' . implode(',', $index['columns']) . ')';
				else
				{
					$new_columns = implode('_', $index['columns']);
					$old_columns = implode('_', $current_indexes[$index['name']]['columns']);
					if ($new_columns != $old_columns)
					{
						// There's no MODIFY INDEX function in MySQL.
						$changes[] = 'DROP INDEX ' . $index['name'];
						$changes[] = 'ADD INDEX ' . $index['name'] . ' (' . implode(',', $index['columns']) . ')';
					}
				}
			}

			if (!empty($changes))
				$table_query = 'ALTER TABLE ' . $table_name . '
					' . implode(',
					', $changes);
		}

		// Create the table!
		if (!empty($table_query))
			wesql::query($table_query,
				array(
					'security_override' => true,
				)
			);
	}

	// Drop a table.
	public static function drop_table($table_name, $parameters = array(), $error = 'fatal')
	{
		global $db_prefix;

		// After stripping away the database name, this is what's left.
		$real_prefix = preg_match('~^(`?)(.+?)\\1\\.(.*?)$~', $db_prefix, $match) === 1 ? $match[3] : $db_prefix;

		// Get some aliases.
		$full_table_name = str_replace('{db_prefix}', $real_prefix, $table_name);
		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// God no - dropping one of these = bad.
		if (in_array(strtolower($table_name), self::$reservedTables))
			return false;

		// Does it exist?
		if (in_array($full_table_name, wedbExtra::list_tables()))
		{
			$query = 'DROP TABLE ' . $table_name;
			wesql::query(
				$query,
				array(
					'security_override' => true,
				)
			);

			return true;
		}

		// Otherwise do 'nout.
		return false;
	}

	// Add a column.
	public static function add_column($table_name, $column_info, $if_exists = 'update')
	{
		global $txt, $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// Does it exist - if so don't add it again!
		$columns = self::list_columns($table_name, false);
		foreach ($columns as $column)
			if ($column == $column_info['name'])
			{
				// If we're going to overwrite then use change column.
				if ($if_exists == 'update')
					return self::change_column($table_name, $column_info['name'], $column_info);
				else
					return false;
			}

		// Get the specifics...
		$column_info['size'] = isset($column_info['size']) && is_numeric($column_info['size']) ? $column_info['size'] : null;

		// Allow unsigned integers
		$unsigned = in_array($column_info['type'], array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')) && !empty($column_info['unsigned']) ? 'unsigned ' : '';

		list ($type, $size) = self::calculate_type($column_info['type'], $column_info['size'], $unsigned);

		if ($size !== null)
			$type .= '(' . $size . ')';
		elseif (!empty($column_info['values']))
			$type .= '(' . $column['values'] . ')';

		// Now add the thing!
		$query = '
			ALTER TABLE ' . $table_name . '
			ADD `' . $column_info['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (empty($column_info['null']) ? 'NOT NULL' : '') . ' ' .
				(!isset($column_info['default']) ? '' : 'default \'' . wesql::escape_string($column_info['default']) . '\'') . ' ' .
				(empty($column_info['auto']) ? '' : 'auto_increment primary key') . ' ';
		wesql::query($query,
			array(
				'security_override' => true,
			)
		);

		return true;
	}

	// Remove a column.
	public static function remove_column($table_name, $column_name)
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// Does it exist?
		$columns = self::list_columns($table_name, true);
		foreach ($columns as $column)
			if ($column['name'] == $column_name)
			{
				wesql::query('
					ALTER TABLE ' . $table_name . '
					DROP COLUMN ' . $column_name,
					array(
						'security_override' => true,
					)
				);

				return true;
			}

		// If here we didn't have to work - joy!
		return false;
	}

	// Change a column.
	public static function change_column($table_name, $old_column, $column_info)
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// Check it does exist!
		$columns = self::list_columns($table_name, true);
		$old_info = null;
		foreach ($columns as $column)
			if ($column['name'] == $old_column)
				$old_info = $column;

		// Nothing?
		if ($old_info == null)
			return false;

		// Get the right bits.
		if (!isset($column_info['name']))
			$column_info['name'] = $old_column;
		if (!isset($column_info['default']))
			$column_info['default'] = $old_info['default'];
		if (!isset($column_info['null']))
			$column_info['null'] = $old_info['null'];
		if (!isset($column_info['auto']))
			$column_info['auto'] = $old_info['auto'];
		if (!isset($column_info['type']))
			$column_info['type'] = $old_info['type'];
		if (!isset($column_info['size']) || !is_numeric($column_info['size']))
			$column_info['size'] = $old_info['size'];
		if (!isset($column_info['unsigned']) || !in_array($column_info['type'], array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')))
			$column_info['unsigned'] = '';

		list ($type, $size) = self::calculate_type($column_info['type'], $column_info['size'], !empty($column_info['unsigned']));

		// Allow for unsigned integers
		$unsigned = in_array($type, array('int', 'tinyint', 'smallint', 'mediumint', 'bigint')) && !empty($column_info['unsigned']) ? 'unsigned ' : '';

		if ($size !== null)
			$type = $type . '(' . $size . ')';
		elseif (!empty($column_info['values']))
			$type .= '(' . $column['values'] . ')';

		wesql::query('
			ALTER TABLE ' . $table_name . '
			CHANGE COLUMN `' . $old_column . '` `' . $column_info['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (empty($column_info['null']) ? 'NOT NULL' : '') . ' ' .
				(!isset($column_info['default']) ? '' : 'default \'' . wesql::escape_string($column_info['default']) . '\'') . ' ' .
				(empty($column_info['auto']) ? '' : 'auto_increment') . ' ',
			array(
				'security_override' => true,
			)
		);
	}

	// Add an index.
	public static function add_index($table_name, $index_info, $if_exists = 'update')
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// No columns = no index.
		if (empty($index_info['columns']))
			return false;
		$columns = implode(',', $index_info['columns']);

		// No name - make it up!
		if (empty($index_info['name']))
		{
			// No need for primary.
			if (isset($index_info['type']) && $index_info['type'] == 'primary')
				$index_info['name'] = '';
			else
				$index_info['name'] = implode('_', $index_info['columns']);
		}
		else
			$index_info['name'] = $index_info['name'];

		// Let's get all our indexes.
		$indexes = self::list_indexes($table_name, true);
		// Do we already have it?
		foreach ($indexes as $index)
		{
			if ($index['name'] == $index_info['name'] || ($index['type'] == 'primary' && isset($index_info['type']) && $index_info['type'] == 'primary'))
			{
				// If we want to overwrite simply remove the current one then continue.
				if ($if_exists != 'update' || $index['type'] == 'primary')
					return false;
				else
					self::remove_index($table_name, $index_info['name']);
			}
		}

		// If we're here we know we don't have the index - so just add it.
		if (!empty($index_info['type']) && $index_info['type'] == 'primary')
		{
			wesql::query('
				ALTER TABLE ' . $table_name . '
				ADD PRIMARY KEY (' . $columns . ')',
				array(
					'security_override' => true,
				)
			);
		}
		else
		{
			wesql::query('
				ALTER TABLE ' . $table_name . '
				ADD ' . (isset($index_info['type']) && $index_info['type'] == 'unique' ? 'UNIQUE' : 'INDEX') . ' ' . $index_info['name'] . ' (' . $columns . ')',
				array(
					'security_override' => true,
				)
			);
		}
	}

	// Remove an index.
	public static function remove_index($table_name, $index_name, $parameters = array(), $error = 'fatal')
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		// Better exist!
		$indexes = self::list_indexes($table_name, true);

		foreach ($indexes as $index)
		{
			// If the name is primary we want the primary key!
			if ($index['type'] == 'primary' && $index_name == 'primary')
			{
				// Dropping primary key?
				wesql::query('
					ALTER TABLE ' . $table_name . '
					DROP PRIMARY KEY',
					array(
						'security_override' => true,
					)
				);

				return true;
			}
			if ($index['name'] == $index_name)
			{
				// Drop the bugger...
				wesql::query('
					ALTER TABLE ' . $table_name . '
					DROP INDEX ' . $index_name,
					array(
						'security_override' => true,
					)
				);

				return true;
			}
		}

		// Not to be found ;(
		return false;
	}

	// Get the schema formatted name for a type, especially important for numeric fields.
	public static function calculate_type($type_name, $type_size = null, $unsigned = true)
	{
		if ($type_size === null)
		{
			switch ($type_name)
			{
				case 'tinyint':
					$type_size = $unsigned ? 3 : 4; // 0 to 255 (3) vs -128 (4) to 127
					break;
				case 'smallint':
					$type_size = $unsigned ? 5 : 6; // 0 to 65535 (5) vs -32768 (6) to 32767
					break;
				case 'mediumint':
					$type_size = 8; // 0 to 16777215 (8) vs -8388608 (8) to 8388607
					break;
				case 'int':
					$type_size = $unsigned ? 10 : 11; // 0 to 4294967296 (10) vs -2147483648 (11) to 2147483647
					break;
				case 'bigint':
					$type_size = 20; // 0 to 18446744073709551616 (20) vs -9223372036854775808 (20) to 9223372036854775807
					break;
				case 'varchar':
				case 'char':
					$type_size = 50; // There must be a size set for varchars/chars by default.
					break;
			}
		}

		// In case something stupid like text(255) was specified, deal with it.
		if (in_array($type_name, array('text', 'mediumtext', 'set', 'enum', 'date', 'datetime')))
			$type_size = null;

		return array($type_name, $type_size);
	}

	// Get table structure.
	public static function table_structure($table_name, $parameters = array())
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		return array(
			'name' => $table_name,
			'columns' => self::list_columns($table_name, true),
			'indexes' => self::list_indexes($table_name, true),
		);
	}

	// Return column information for a table.
	public static function list_columns($table_name, $detail = false)
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		$result = wesql::query('
			SHOW FIELDS
			FROM {raw:table_name}',
			array(
				'table_name' => $table_name[0] === '`' ? $table_name : '`' . $table_name . '`',
			)
		);
		$columns = array();
		while ($row = wesql::fetch_assoc($result))
		{
			if (!$detail)
			{
				$columns[] = $row['Field'];
			}
			else
			{
				// Is there an auto_increment?
				$auto = strpos($row['Extra'], 'auto_increment') !== false ? true : false;

				// Can we split out the size?
				if (preg_match('~(.+?)\s*\((\d+)\)(?:(?:\s*)?(unsigned))?~i', $row['Type'], $matches) === 1)
				{
					$type = $matches[1];
					$size = $matches[2];
					if (!empty($matches[3]) && $matches[3] == 'unsigned')
						$unsigned = true;
				}
				else
				{
					$type = $row['Type'];
					$size = null;
				}

				$columns[$row['Field']] = array(
					'name' => $row['Field'],
					'null' => $row['Null'] != 'YES' ? false : true,
					'default' => !is_null($row['Default']) ? $row['Default'] : null,
					'type' => $type,
					'size' => $size,
					'auto' => $auto,
				);

				if (isset($unsigned))
				{
					$columns[$row['Field']]['unsigned'] = $unsigned;
					unset($unsigned);
				}
			}
		}
		wesql::free_result($result);

		return $columns;
	}

	// What about some index information?
	public static function list_indexes($table_name, $detail = false, $parameters = array())
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		$result = wesql::query('
			SHOW KEYS
			FROM {raw:table_name}',
			array(
				'table_name' => $table_name[0] === '`' ? $table_name : '`' . $table_name . '`',
			)
		);
		$indexes = array();
		while ($row = wesql::fetch_assoc($result))
		{
			if (!$detail)
				$indexes[] = $row['Key_name'];
			else
			{
				// What is the type?
				if ($row['Key_name'] == 'PRIMARY')
					$type = 'primary';
				elseif (empty($row['Non_unique']))
					$type = 'unique';
				elseif (isset($row['Index_type']) && $row['Index_type'] == 'FULLTEXT')
					$type = 'fulltext';
				else
					$type = 'index';

				// This is the first column we've seen?
				if (empty($indexes[$row['Key_name']]))
				{
					$indexes[$row['Key_name']] = array(
						'name' => $row['Key_name'],
						'type' => $type,
						'columns' => array(),
					);
				}

				// Is it a partial index?
				if (!empty($row['Sub_part']))
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'] . '(' . $row['Sub_part'] . ')';
				else
					$indexes[$row['Key_name']]['columns'][] = $row['Column_name'];
			}
		}
		wesql::free_result($result);

		return $indexes;
	}
}

?>