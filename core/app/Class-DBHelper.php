<?php
/**
 * Contains several specialized database operations, like creating new tables.
 *
 * Wedge (http://wedge.org)
 * Copyright © 2010 René-Gilles Deberdt, wedge.org
 * Portions are © 2011 Simple Machines.
 * License: http://wedge.org/license/
 */

if (!defined('WEDGE'))
	die('Hacking attempt...');

class wedb
{
	public static function is_reserved_table($table)
	{
		global $db_prefix;
		static $reserved = null;

		if ($reserved === null)
		{
			$items = array(
				'admin_info_files',
				'approval_queue',
				'attachments',
				'bans',
				'bbcode',
				'board_groups',
				'board_members',
				'board_permissions',
				'boards',
				'categories',
				'collapsed_categories',
				'contact_lists',
				'contacts',
				'custom_fields',
				'drafts',
				'group_moderators',
				'infractions',
				'language_changes',
				'likes',
				'log_actions',
				'log_activity',
				'log_boards',
				'log_comments',
				'log_digest',
				'log_errors',
				'log_floodcontrol',
				'log_group_requests',
				'log_ips',
				'log_infractions',
				'log_intrusion',
				'log_mark_read',
				'log_notify',
				'log_online',
				'log_polls',
				'log_reported',
				'log_reported_comments',
				'log_scheduled_tasks',
				'log_search_messages',
				'log_search_results',
				'log_search_subjects',
				'log_search_topics',
				'log_spider_hits',
				'log_spider_stats',
				'log_subscribed',
				'log_topics',
				'mail_queue',
				'media_albums',
				'media_comments',
				'media_fields',
				'media_field_data',
				'media_files',
				'media_items',
				'media_log_media',
				'media_log_ratings',
				'media_perms',
				'media_playlists',
				'media_playlist_data',
				'media_quotas',
				'media_settings',
				'media_variables',
				'membergroups',
				'members',
				'message_icons',
				'messages',
				'moderators',
				'mod_filter_msg',
				'notifications',
				'notif_subs',
				'permission_profiles',
				'permissions',
				'personal_messages',
				'plugin_servers',
				'pm_recipients',
				'pm_rules',
				'poll_choices',
				'polls',
				'pretty_topic_urls',
				'pretty_urls_cache',
				'privacy_boards',
				'privacy_thoughts',
				'privacy_topics',
				'scheduled_imperative',
				'scheduled_tasks',
				'sessions',
				'settings',
				'smileys',
				'spiders',
				'subscriptions',
				'subscriptions_groups',
				'themes',
				'thoughts',
				'topics'
			);
			foreach ($items as $table_name)
				$reserved[] = strtolower($db_prefix . $table_name);
		}
		return in_array(strtolower($table), $reserved);
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
		if (self::is_reserved_table($table_name))
			return false;

		$tables = self::list_tables();
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
					$default = ' auto_increment';
				else
					$default = self::escape_default($column);

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
				$table_query .= "\n\t`" . $column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (!empty($column['null']) ? '' : 'NOT NULL') . $default . ',';
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

					$changes[] = 'ADD `' . $column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (empty($column['null']) ? 'NOT NULL' : '') .
						self::escape_default($column) . (empty($column['auto']) ? '' : ' auto_increment primary key');
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

						$changes[] = 'MODIFY `' . $column['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (empty($column['null']) ? 'NOT NULL' : '') .
							self::escape_default($column) . (empty($column['auto']) ? '' : ' auto_increment primary key');
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
		if (self::is_reserved_table($table_name))
			return false;

		// Does it exist?
		if (in_array($full_table_name, self::list_tables()))
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

		// Otherwise do nothing.
		return false;
	}

	// Add a column.
	public static function add_column($table_name, $column_info, $if_exists = 'update')
	{
		global $db_prefix;

		$table_name = str_replace('{db_prefix}', $db_prefix, $table_name);

		$columns = self::list_columns($table_name, false);
		// Does it exist? If so, change it or skip it, depending on $if_exists.
		foreach ($columns as $column)
			if ($column == $column_info['name'])
				return $if_exists == 'update' ? self::change_column($table_name, $column_info['name'], $column_info) : false;

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
			ADD `' . $column_info['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '') . (empty($column_info['null']) ? 'NOT NULL' : '') .
				self::escape_default($column_info) . (empty($column_info['auto']) ? '' : ' auto_increment primary key');

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
			CHANGE COLUMN `' . $old_column . '` `' . $column_info['name'] . '` ' . $type . ' ' . (!empty($unsigned) ? $unsigned : '')
				. (empty($column_info['null']) ? 'NOT NULL' : '') . self::escape_default($column_info) . (empty($column_info['auto']) ? '' : ' auto_increment'),
			array(
				'security_override' => true,
			)
		);
	}

	protected static function escape_default($column)
	{
		if (!isset($column['default']) || $column['default'] === null || $column['type'] == 'text' || $column['type'] == 'mediumtext')
			return '';
		if (is_int($column['default']))
			return ' default ' . (int) $column['default'];
		return ' default \'' . wesql::escape_string($column['default']) . '\'';
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

	// Get the version number.
	public static function get_version()
	{
		$request = wesql::query('SELECT VERSION()');
		list ($ver) = wesql::fetch_row($request);
		wesql::free_result($request);
		return $ver;
	}

	// Backup $table to $backup_table.
	public static function backup_table($table, $backup_table)
	{
		global $db_prefix;

		$table = str_replace('{db_prefix}', $db_prefix, $table);

		// First, get rid of the old table.
		wesql::query('
			DROP TABLE IF EXISTS {raw:backup_table}',
			array(
				'backup_table' => $backup_table,
			)
		);

		// Can we do this the quick way?
		$result = wesql::query('
			CREATE TABLE {raw:backup_table} LIKE {raw:table}',
			array(
				'backup_table' => $backup_table,
				'table' => $table
		));
		// If this failed, we go old school.
		if ($result)
		{
			$request = wesql::query('
				INSERT INTO {raw:backup_table}
				SELECT *
				FROM {raw:table}',
				array(
					'backup_table' => $backup_table,
					'table' => $table
				));

			// Old school or no school?
			if ($request)
				return $request;
		}

		// At this point, the quick method failed.
		$result = wesql::query('
			SHOW CREATE TABLE {raw:table}',
			array(
				'table' => $table,
			)
		);
		list (, $create) = wesql::fetch_row($result);
		wesql::free_result($result);

		$create = preg_split('/[\n\r]/', $create);

		$auto_inc = '';
		// Default engine type.
		$engine = 'MyISAM';
		$charset = '';
		$collate = '';

		foreach ($create as $k => $l)
		{
			// Get the name of the auto_increment column.
			if (strpos($l, 'auto_increment'))
				$auto_inc = trim($l);

			// For the engine type, see if we can work out what it is.
			if (strpos($l, 'ENGINE') !== false || strpos($l, 'TYPE') !== false)
			{
				// Extract the engine type.
				preg_match('~(ENGINE|TYPE)=(\w+)(\sDEFAULT)?(\sCHARSET=(\w+))?(\sCOLLATE=(\w+))?~', $l, $match);

				if (!empty($match[1]))
					$engine = $match[1];

				if (!empty($match[2]))
					$engine = $match[2];

				if (!empty($match[5]))
					$charset = $match[5];

				if (!empty($match[7]))
					$collate = $match[7];
			}

			// Skip everything but keys...
			if (strpos($l, 'KEY') === false)
				unset($create[$k]);
		}

		if (!empty($create))
			$create = '(
				' . implode('
				', $create) . ')';
		else
			$create = '';

		$request = wesql::query('
			CREATE TABLE {raw:backup_table} {raw:create}
			ENGINE={raw:engine}' . (empty($charset) ? '' : ' CHARACTER SET {raw:charset}' . (empty($collate) ? '' : ' COLLATE {raw:collate}')) . '
			SELECT *
			FROM {raw:table}',
			array(
				'backup_table' => $backup_table,
				'table' => $table,
				'create' => $create,
				'engine' => $engine,
				'charset' => empty($charset) ? '' : $charset,
				'collate' => empty($collate) ? '' : $collate,
			)
		);

		if ($auto_inc != '')
		{
			if (preg_match('~\`(.+?)\`\s~', $auto_inc, $match) != 0 && substr($auto_inc, -1, 1) == ',')
				$auto_inc = substr($auto_inc, 0, -1);

			wesql::query('
				ALTER TABLE {raw:backup_table}
				CHANGE COLUMN {raw:column_detail} {raw:auto_inc}',
				array(
					'backup_table' => $backup_table,
					'column_detail' => $match[1],
					'auto_inc' => $auto_inc,
				)
			);
		}

		return $request;
	}

	// Optimize a table - return data freed!
	public static function optimize_table($table)
	{
		global $db_prefix;

		$table = str_replace('{db_prefix}', $db_prefix, $table);

		// Get how much overhead there is.
		$request = wesql::query('
				SHOW TABLE STATUS LIKE {string:table_name}',
				array(
					'table_name' => str_replace('_', '\_', $table),
				)
			);
		$row = wesql::fetch_assoc($request);
		wesql::free_result($request);

		$data_before = isset($row['Data_free']) ? $row['Data_free'] : 0;
		$request = wesql::query('
				OPTIMIZE TABLE `{raw:table}`',
				array(
					'table' => $table,
				)
			);
		if (!$request)
			return -1;

		// How much left?
		$request = wesql::query('
				SHOW TABLE STATUS LIKE {string:table}',
				array(
					'table' => str_replace('_', '\_', $table),
				)
			);
		$row = wesql::fetch_assoc($request);
		wesql::free_result($request);

		$total_change = isset($row['Data_free']) && $data_before > $row['Data_free'] ? $data_before / 1024 : 0;

		return $total_change;
	}

	// List all the tables in the database.
	public static function list_tables($db = false, $filter = false)
	{
		global $db_name;

		$db = $db == false ? $db_name : $db;
		$db = trim($db);
		$filter = $filter == false ? '' : ' LIKE \'' . $filter . '\'';

		$request = wesql::query('
			SHOW TABLES
			FROM `{raw:db}`
			{raw:filter}',
			array(
				'db' => $db[0] == '`' ? strtr($db, array('`' => '')) : $db,
				'filter' => $filter,
			)
		);
		$tables = array();
		while ($row = wesql::fetch_row($request))
			$tables[] = $row[0];
		wesql::free_result($request);

		return $tables;
	}
}
