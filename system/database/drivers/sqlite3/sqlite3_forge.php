<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		EllisLab Dev Team
 * @copyright		Copyright (c) 2008 - 2014, EllisLab, Inc.
 * @copyright		Copyright (c) 2014 - 2015, British Columbia Institute of Technology (http://bcit.ca/)
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 2.3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * SQLite3 Forge Class
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
#[\AllowDynamicProperties]
class CI_DB_sqlite3_forge extends CI_DB_forge {

	/**
	 * Create database
	 *
	 * @access	private
	 * @param	string	the database name
	 * @return	bool
	 */
	function _create_database()
	{
		// In SQLite, a database is created when you connect to the database.
		// We'll return TRUE so that an error isn't generated
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Drop database
	 *
	 * @access	private
	 * @param	string	the database name
	 * @return	bool
	 */
	function _drop_database($name)
	{
		// In SQLite, a database is a file; ":memory:" has nothing to delete
		if ($this->db->database == ':memory:')
		{
			return TRUE;
		}

		if ( ! @file_exists($this->db->database) OR ! @unlink($this->db->database))
		{
			if ($this->db->db_debug)
			{
				return $this->db->display_error('db_unable_to_drop');
			}
			return FALSE;
		}
		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Create Table
	 *
	 * An AUTO_INCREMENT field becomes "INTEGER PRIMARY KEY AUTOINCREMENT", the
	 * only form SQLite accepts, and so it is left out of the PRIMARY KEY clause.
	 * Keys added with add_key() are created as UNIQUE constraints, as in the
	 * SQLite 2 driver: a plain index would need a second statement.
	 *
	 * @access	private
	 * @param	string	the table name
	 * @param	array	the fields
	 * @param	mixed	primary key(s)
	 * @param	mixed	key(s)
	 * @param	boolean	should 'IF NOT EXISTS' be added to the SQL
	 * @return	bool
	 */
	function _create_table($table, $fields, $primary_keys, $keys, $if_not_exists)
	{
		$sql = 'CREATE TABLE ';

		if ($if_not_exists === TRUE)
		{
			$sql .= 'IF NOT EXISTS ';
		}

		$sql .= $this->db->_escape_identifiers($table)."(";
		$sql .= $this->_process_fields($fields, $primary_keys);

		if (count($primary_keys) > 0)
		{
			$primary_keys = $this->db->_protect_identifiers($primary_keys);
			$sql .= ",\n\tPRIMARY KEY (" . implode(', ', $primary_keys) . ")";
		}

		if (is_array($keys) && count($keys) > 0)
		{
			foreach ($keys as $key)
			{
				if (is_array($key))
				{
					$key = $this->db->_protect_identifiers($key);
				}
				else
				{
					$key = array($this->db->_protect_identifiers($key));
				}

				$sql .= ",\n\tUNIQUE (" . implode(', ', $key) . ")";
			}
		}

		$sql .= "\n)";

		return $sql;
	}

	// --------------------------------------------------------------------

	/**
	 * Process fields
	 *
	 * @access	private
	 * @param	array	the fields
	 * @param	array	the primary keys, which loses an AUTO_INCREMENT field (by reference)
	 * @param	bool	whether the fields are added to an existing table
	 * @return	string
	 */
	function _process_fields($fields, &$primary_keys = array(), $alter = FALSE)
	{
		$sql = '';
		$current_field_count = 0;

		foreach ($fields as $field => $attributes)
		{
			// Numeric field names aren't allowed in databases, so if the key is
			// numeric, we know it was assigned by PHP and the developer manually
			// entered the field information, so we'll simply add it to the list
			if (is_numeric($field))
			{
				$sql .= "\n\t$attributes";
			}
			else
			{
				$attributes = array_change_key_case($attributes, CASE_UPPER);

				$sql .= "\n\t".$this->db->_protect_identifiers($field);

				if (array_key_exists('AUTO_INCREMENT', $attributes) && $attributes['AUTO_INCREMENT'] === TRUE)
				{
					// the column that aliases the rowid; it is the primary key by definition
					$sql .= ' INTEGER PRIMARY KEY AUTOINCREMENT';
					$primary_keys = array_values(array_diff($primary_keys, array($field)));
				}
				else
				{
					$sql .=  ' '.$attributes['TYPE'];

					if (array_key_exists('CONSTRAINT', $attributes))
					{
						$sql .= '('.$attributes['CONSTRAINT'].')';
					}

					if (array_key_exists('UNSIGNED', $attributes) && $attributes['UNSIGNED'] === TRUE)
					{
						$sql .= ' UNSIGNED';
					}

					if (array_key_exists('DEFAULT', $attributes))
					{
						$sql .= ' DEFAULT \''.$attributes['DEFAULT'].'\'';
					}

					if (array_key_exists('NULL', $attributes) && $attributes['NULL'] === TRUE)
					{
						$sql .= ' NULL';
					}
					elseif ( ! $alter OR array_key_exists('DEFAULT', $attributes))
					{
						// ALTER TABLE ... ADD COLUMN refuses NOT NULL without a default value
						$sql .= ' NOT NULL';
					}
				}
			}

			// don't add a comma on the end of the last field
			if (++$current_field_count < count($fields))
			{
				$sql .= ',';
			}
		}

		return $sql;
	}

	// --------------------------------------------------------------------

	/**
	 * Drop Table
	 *
	 * @access	private
	 * @param	string	the table name
	 * @return	string
	 */
	function _drop_table($table)
	{
		return 'DROP TABLE IF EXISTS '.$this->db->_escape_identifiers($table);
	}

	// --------------------------------------------------------------------

	/**
	 * Alter table query
	 *
	 * Generates a platform-specific query so that a table can be altered.
	 * SQLite can ADD a column, DROP one since 3.35.0, and cannot CHANGE one
	 * (https://www.sqlite.org/lang_altertable.html); where it cannot, FALSE
	 * is returned. There is no AFTER: a new column is always added last.
	 *
	 * @access	private
	 * @param	string	the ALTER type (ADD, DROP, CHANGE)
	 * @param	string	the column name
	 * @param	array	fields
	 * @param	string	the field after which we should add the new field
	 * @return	object
	 */
	function _alter_table($alter_type, $table, $fields, $after_field = '')
	{
		$sql = 'ALTER TABLE '.$this->db->_protect_identifiers($table);

		if ($alter_type == 'DROP')
		{
			if (version_compare($this->db->_version(), '3.35.0', '<'))
			{
				return FALSE;
			}

			return $sql.' DROP COLUMN '.$this->db->_protect_identifiers($fields);
		}

		if ($alter_type == 'CHANGE')
		{
			return FALSE;
		}

		$no_keys = array();

		return $sql.' ADD COLUMN '.trim($this->_process_fields($fields, $no_keys, TRUE));
	}

	// --------------------------------------------------------------------

	/**
	 * Rename a table
	 *
	 * Generates a platform-specific query so that a table can be renamed
	 *
	 * @access	private
	 * @param	string	the old table name
	 * @param	string	the new table name
	 * @return	string
	 */
	function _rename_table($table_name, $new_table_name)
	{
		$sql = 'ALTER TABLE '.$this->db->_protect_identifiers($table_name)." RENAME TO ".$this->db->_protect_identifiers($new_table_name);
		return $sql;
	}

}

/* End of file sqlite3_forge.php */
/* Location: ./system/database/drivers/sqlite3/sqlite3_forge.php */