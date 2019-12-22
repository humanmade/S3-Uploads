<?php

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at https://github.com/JamesHeinrich/getID3       //
//            or https://www.getid3.org                        //
//            or http://getid3.sourceforge.net                 //
//                                                             //
// extension.cache.mysqli.php - part of getID3()               //
// Please see readme.txt for more information                  //
//                                                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// This extension written by Allan Hansen <ahØartemis*dk>      //
// Table name mod by Carlo Capocasa <calroØcarlocapocasa*com>  //
//                                                            ///
/////////////////////////////////////////////////////////////////


/**
* This is a caching extension for getID3(). It works the exact same
* way as the getID3 class, but return cached information very fast
*
* Example:  (see also demo.cache.mysql.php in /demo/)
*
*    Normal getID3 usage (example):
*
*       require_once 'getid3/getid3.php';
*       $getID3 = new getID3;
*       $getID3->encoding = 'UTF-8';
*       $info1 = $getID3->analyze('file1.flac');
*       $info2 = $getID3->analyze('file2.wv');
*
*    getID3_cached usage:
*
*       require_once 'getid3/getid3.php';
*       require_once 'getid3/getid3/extension.cache.mysqli.php';
*       // 5th parameter (tablename) is optional, default is 'getid3_cache'
*       $getID3 = new getID3_cached_mysqli('localhost', 'database', 'username', 'password', 'tablename');
*       $getID3->encoding = 'UTF-8';
*       $info1 = $getID3->analyze('file1.flac');
*       $info2 = $getID3->analyze('file2.wv');
*
*
* Supported Cache Types    (this extension)
*
*   SQL Databases:
*
*   cache_type          cache_options
*   -------------------------------------------------------------------
*   mysqli              host, database, username, password
*
*
*   DBM-Style Databases:    (use extension.cache.dbm)
*
*   cache_type          cache_options
*   -------------------------------------------------------------------
*   gdbm                dbm_filename, lock_filename
*   ndbm                dbm_filename, lock_filename
*   db2                 dbm_filename, lock_filename
*   db3                 dbm_filename, lock_filename
*   db4                 dbm_filename, lock_filename  (PHP5 required)
*
*   PHP must have write access to both dbm_filename and lock_filename.
*
*
* Recommended Cache Types
*
*   Infrequent updates, many reads      any DBM
*   Frequent updates                    mysqli
*/

class getID3_cached_mysqli extends getID3
{
	/**
	 * @var mysqli
	 */
	private $mysqli;

	/**
	 * @var mysqli_result
	 */
	private $cursor;

	/**
	 * @var string
	 */
	private $table;

	/**
	 * @var bool
	 */
	private $db_structure_check;


	/**
	 * constructor - see top of this file for cache type and cache_options
	 *
	 * @param string $host
	 * @param string $database
	 * @param string $username
	 * @param string $password
	 * @param string $table
	 *
	 * @throws Exception
	 * @throws getid3_exception
	 */
	public function __construct($host, $database, $username, $password, $table='getid3_cache') {

		// Check for mysqli support
		if (!function_exists('mysqli_connect')) {
			throw new Exception('PHP not compiled with mysqli support.');
		}

		// Connect to database
		$this->mysqli = new mysqli($host, $username, $password);
		if ($this->mysqli->connect_error) {
			throw new Exception('Connect Error (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
		}

		// Select database
		if (!$this->mysqli->select_db($database)) {
			throw new Exception('Cannot use database '.$database);
		}

		// Set table
		$this->table = $table;

		// Create cache table if not exists
		$this->create_table();

		$this->db_structure_check = true; // set to false if you know your table structure has already been migrated to use `hash` as the primary key to avoid
		$this->migrate_db_structure();

		// Check version number and clear cache if changed
		$version = '';
		$SQLquery  = 'SELECT `value`';
		$SQLquery .= ' FROM `'.$this->mysqli->real_escape_string($this->table).'`';
		$SQLquery .= ' WHERE (`filename` = \''.$this->mysqli->real_escape_string(getID3::VERSION).'\')';
		$SQLquery .= ' AND (`hash` = \'getID3::VERSION\')';
		if ($this->cursor = $this->mysqli->query($SQLquery)) {
			list($version) = $this->cursor->fetch_array();
		}
		if ($version != getID3::VERSION) {
			$this->clear_cache();
		}

		parent::__construct();
	}


	/**
	 * clear cache
	 */
	public function clear_cache() {
		$this->mysqli->query('TRUNCATE TABLE `'.$this->mysqli->real_escape_string($this->table).'`');
		$this->mysqli->query('INSERT INTO `'.$this->mysqli->real_escape_string($this->table).'` (`hash`, `filename`, `filesize`, `filetime`, `analyzetime`, `value`) VALUES (\'getID3::VERSION\', \''.getID3::VERSION.'\', -1, -1, -1, \''.getID3::VERSION.'\')');
	}


	/**
	 * migrate database structure if needed
	 */
	public function migrate_db_structure() {
		// Check for table structure
		if ($this->db_structure_check) {
			$SQLquery  = 'SHOW COLUMNS';
			$SQLquery .= ' FROM `'.$this->mysqli->real_escape_string($this->table).'`';
			$SQLquery .= ' LIKE \'hash\'';
			$this->cursor = $this->mysqli->query($SQLquery);
			if ($this->cursor->num_rows == 0) {
				// table has not been migrated, add column, add hashes, change index
				$SQLquery  = 'ALTER TABLE `getid3_cache` DROP PRIMARY KEY, ADD `hash` CHAR(32) NOT NULL DEFAULT \'\' FIRST, ADD PRIMARY KEY(`hash`)';
				$this->mysqli->query($SQLquery);

				$SQLquery  = 'UPDATE `getid3_cache` SET';
				$SQLquery .= ' `hash` = MD5(`filename`, `filesize`, `filetime`)';
				$SQLquery .= ' WHERE (`filesize` > -1)';
				$this->mysqli->query($SQLquery);

				$SQLquery  = 'UPDATE `getid3_cache` SET';
				$SQLquery .= ' `hash` = \'getID3::VERSION\'';
				$SQLquery .= ' WHERE (`filesize` = -1)';
				$SQLquery .= '   AND (`filetime` = -1)';
				$SQLquery .= '   AND (`filetime` = -1)';
				$this->mysqli->query($SQLquery);
			}
		}
	}


	/**
	 * analyze file
	 *
	 * @param string   $filename
	 * @param int      $filesize
	 * @param string   $original_filename
	 * @param resource $fp
	 *
	 * @return mixed
	 */
	public function analyze($filename, $filesize=null, $original_filename='', $fp=null) {

		$filetime = 0;
		if (file_exists($filename)) {

			// Short-hands
			$filetime = filemtime($filename);
			$filesize =  filesize($filename);

			// Lookup file
			$SQLquery  = 'SELECT `value`';
			$SQLquery .= ' FROM `'.$this->mysqli->real_escape_string($this->table).'`';
			$SQLquery .= ' WHERE (`hash` = \''.$this->mysqli->real_escape_string(md5($filename.$filesize.$filetime)).'\')';
			$this->cursor = $this->mysqli->query($SQLquery);
			if ($this->cursor->num_rows > 0) {
				// Hit
				list($result) = $this->cursor->fetch_array();
				return unserialize(base64_decode($result));
			}
		}

		// Miss
		$analysis = parent::analyze($filename, $filesize, $original_filename, $fp);

		// Save result
		if (file_exists($filename)) {
			$SQLquery  = 'INSERT INTO `'.$this->mysqli->real_escape_string($this->table).'` (`hash`, `filename`, `filesize`, `filetime`, `analyzetime`, `value`) VALUES (';
			$SQLquery .=   '\''.$this->mysqli->real_escape_string(md5($filename.$filesize.$filetime)).'\'';
			$SQLquery .= ', \''.$this->mysqli->real_escape_string($filename).'\'';
			$SQLquery .= ', \''.$this->mysqli->real_escape_string($filesize).'\'';
			$SQLquery .= ', \''.$this->mysqli->real_escape_string($filetime).'\'';
			$SQLquery .= ', UNIX_TIMESTAMP()';
			$SQLquery .= ', \''.$this->mysqli->real_escape_string(base64_encode(serialize($analysis))).'\'';
			$SQLquery .= ')';
			$this->cursor = $this->mysqli->query($SQLquery);
		}
		return $analysis;
	}


	/**
	 * (re)create mysqli table
	 *
	 * @param bool $drop
	 */
	private function create_table($drop=false) {
		if ($drop) {
			$SQLquery  = 'DROP TABLE IF EXISTS `'.$this->mysqli->real_escape_string($this->table).'`';
			$this->mysqli->query($SQLquery);
		}
		$SQLquery  = 'CREATE TABLE IF NOT EXISTS `'.$this->mysqli->real_escape_string($this->table).'` (';
		$SQLquery .=   '`hash` CHAR(32) NOT NULL DEFAULT \'\'';
		$SQLquery .= ', `filename` VARCHAR(1000) NOT NULL DEFAULT \'\'';
		$SQLquery .= ', `filesize` INT(11) NOT NULL DEFAULT \'0\'';
		$SQLquery .= ', `filetime` INT(11) NOT NULL DEFAULT \'0\'';
		$SQLquery .= ', `analyzetime` INT(11) NOT NULL DEFAULT \'0\'';
		$SQLquery .= ', `value` LONGTEXT NOT NULL';
		$SQLquery .= ', PRIMARY KEY (`hash`))';
		$this->cursor = $this->mysqli->query($SQLquery);
		echo $this->mysqli->error;
	}
}
