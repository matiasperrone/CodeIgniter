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
 * SQLite Result Class
 *
 * This class extends the parent result class: CI_DB_result
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/database/
 */
#[\AllowDynamicProperties]
class CI_DB_sqlite3_result extends CI_DB_result {

	/**
	 * SQLite3Result can neither report its number of rows nor seek, so the rows
	 * are read once, on first use, and walked with a pointer.
	 */
	var $_rows		= NULL;
	var $_pointer	= 0;

	/**
	 * Read the whole result set
	 *
	 * @access	private
	 * @return	void
	 */
	function _load()
	{
		if ($this->_rows !== NULL)
		{
			return;
		}

		$this->_rows = array();

		if ( ! is_object($this->result_id))
		{
			return;
		}

		while (($row = $this->result_id->fetchArray(SQLITE3_ASSOC)) !== FALSE)
		{
			$this->_rows[] = $row;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Number of rows in the result set
	 *
	 * @access	public
	 * @return	integer
	 */
	function num_rows()
	{
		$this->_load();

		return count($this->_rows);
	}

	// --------------------------------------------------------------------

	/**
	 * Number of fields in the result set
	 *
	 * @access	public
	 * @return	integer
	 */
	function num_fields()
	{
		return is_object($this->result_id) ? $this->result_id->numColumns() : 0;
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch Field Names
	 *
	 * Generates an array of column names
	 *
	 * @access	public
	 * @return	array
	 */
	function list_fields()
	{
		$field_names = array();
		for ($i = 0; $i < $this->num_fields(); $i++)
		{
			$field_names[] = $this->result_id->columnName($i);
		}

		return $field_names;
	}

	// --------------------------------------------------------------------

	/**
	 * Field data
	 *
	 * Generates an array of objects containing field meta-data
	 *
	 * @access	public
	 * @return	array
	 */
	function field_data()
	{
		$retval = array();
		foreach ($this->list_fields() as $name)
		{
			$F				= new stdClass();
			$F->name		= $name;
			$F->type		= 'varchar';
			$F->max_length	= 0;
			$F->primary_key = 0;
			$F->default		= '';

			$retval[] = $F;
		}

		return $retval;
	}

	// --------------------------------------------------------------------

	/**
	 * Free the result
	 *
	 * @return	null
	 */
	function free_result()
	{
		if (is_object($this->result_id))
		{
			$this->result_id->finalize();
			$this->result_id = NULL;
		}

		$this->_rows = array();
		$this->_pointer = 0;
	}

	// --------------------------------------------------------------------

	/**
	 * Data Seek
	 *
	 * Moves the internal pointer to the desired offset.  We call
	 * this internally before fetching results to make sure the
	 * result set starts at zero
	 *
	 * @access	private
	 * @return	array
	 */
	function _data_seek($n = 0)
	{
		$this->_load();
		$this->_pointer = (int) $n;

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Result - associative array
	 *
	 * Returns the result set as an array
	 *
	 * @access	private
	 * @return	array
	 */
	function _fetch_assoc()
	{
		$this->_load();

		return isset($this->_rows[$this->_pointer]) ? $this->_rows[$this->_pointer++] : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Result - object
	 *
	 * Returns the result set as an object
	 *
	 * @access	private
	 * @return	object
	 */
	function _fetch_object()
	{
		$row = $this->_fetch_assoc();

		return is_array($row) ? (object) $row : NULL;
	}

}


/* End of file sqlite3_result.php */
/* Location: ./system/database/drivers/sqlite3/sqlite3_result.php */