<?php
namespace lib\PNServer;

use lib\PNServer\PNDataProvider;

require_once dirname(__FILE__) . '/PNDataProvider.php';

/**
 * dataprovider for SqLite database
 * uses given Table in specified SqLite database
 * 
 * if not specified in constructor, default table 'tPNSubscription' in 
 * databasefile 'pnsub.sqlite' in current working directory is used. 
 * DB-file and/or table are created if not exist so far.
 * 
 * history:
 * date         version
 * 2020-04-02	initial version
 * 
 * @package PNServer
 * @version 1.0.0
 * @author Stefanius <s.kien@online.de>
 * @copyright MIT License - see the LICENSE file for details
*/
class PNDataProviderSQLite implements PNDataProvider 
{
	/** @var string tablename					 */
	protected	$strTableName;
	/** @var string name of the DB file			 */
	protected	$strDBName; 
	/** @var \SQLite3 internal SqLite DB		 */
	protected	$db = null;
	/** @var \SQLite3Result result of DB queries */
	protected	$dbres = false;
	/** @var array last fetched row or null		 */
	protected	$row = null;
	
	/**
	 * @param string $strDir		directory -  if null, current working directory assumed
	 * @param string $strDBName		name of DB file - if null, file 'pnsub.sqlite' is used and created if not exist
	 * @param string $strTableName	tablename for the subscriptions - if null, 'tPNSubscription' is used and created if not exist
	 */
	public function __construct($strDir=null, $strDBName=null, $strTableName=null) {
		$this->strTableName = isset($strTableName) ? $strTableName : 'tPNSubscription';
		$this->strDBName = isset($strDBName) ? $strDBName : 'pnsub.sqlite'; 
		try {
			$strDBName = $this->strDBName;
			if (strlen($strDir) > 0) {
				$strDBName = rtrim($strDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->strDBName;
			}
			$this->db = new \SQLite3($strDBName);
			if ($this->db && !$this->tableExist()) {
				$this->createTable();
			}
		} catch (\Exception $e) {
			$this->db = null;
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see \lib\PNServer\PNDataProvider::isConnected()
	 */
	public function isConnected() {
		return ($this->db && $this->tableExist());
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \lib\PNServer\PNDataProvider::saveSubscription()
	 */
	public function saveSubscription($strJSON) {
		$bSucceeded = false;
		if ($this->db) {
			$oSubscription = json_decode($strJSON, true);
			if ($oSubscription) {
				$iExpires = isset($oSubscription['expirationTime']) ? bcdiv($oSubscription['expirationTime'], 1000) : 0;
				$strUserAgent = isset($oSubscription['userAgent']) ? $oSubscription['userAgent'] : 'unknown UserAgent';
				
				$strSQL  = "REPLACE INTO " . $this->strTableName . " (";
				$strSQL .=       self::COL_ENDPOINT;
				$strSQL .= "," . self::COL_EXPIRES;
				$strSQL .= "," . self::COL_SUBSCRIPTION;
				$strSQL .= "," . self::COL_USERAGENT;
				$strSQL .= "," . self::COL_LASTUPDATED;
				$strSQL .= ") VALUES(";
				$strSQL .= "'" . $oSubscription['endpoint'] . "'";
				$strSQL .= "," . $iExpires;
				$strSQL .= ",'" . $strJSON . "'";
				$strSQL .= ",'" . $strUserAgent . "'";
				$strSQL .= ',' . time();
				$strSQL .= ");";

				$bSucceeded = $this->db->exec($strSQL);
			}
		}
		return $bSucceeded;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \lib\PNServer\PNDataProvider::removeSubscription()
	 */
	public function removeSubscription($strEndpoint) {
		$bSucceeded = false;
		if ($this->db) {
			$strSQL  = "DELETE FROM " . $this->strTableName . " WHERE " . self::COL_ENDPOINT . " LIKE ";
			$strSQL .= "'" . $strEndpoint . "'";
		
			$bSucceeded = $this->db->exec($strSQL);
		}
		return $bSucceeded;
	}
	
	/**
	 * select all subscriptions not expired so far
	 * (non-PHPdoc)
	 * @see \lib\PNServer\PNDataProvider::init()
	 */
	public function init($bAutoRemove=true) {
		$this->dbres = false;
		$this->row = null;
		if ($this->db) {
			if ($bAutoRemove) {
				// remove expired subscriptions from DB
				$strSQL = "DELETE FROM " . $this->strTableName . " WHERE ";
				$strSQL .= self::COL_EXPIRES . " != 0 AND ";
				$strSQL .= self::COL_EXPIRES . " < " . time();
				
				$this->db->query($strSQL);
				
				$strSQL = "SELECT * FROM " . $this->strTableName;
			} else {
				// or just exclude them from query
				$strSQL = "SELECT * FROM " . $this->strTableName . " WHERE ";
				$strSQL .= " WHERE ";
				$strSQL .= self::COL_EXPIRES . " = 0 OR ";
				$strSQL .= self::COL_EXPIRES . " >= " . time();
			}
			$this->dbres = $this->db->query($strSQL);
			if ($this->dbres && ($this->dbres->numColumns() == 0)) {
				$this->dbres = false;
			}
		}
		return ($this->dbres !== false);
	}

	/**
	 * (non-PHPdoc)
	 * @see \lib\PNServer\PNDataProvider::count()
	 */
	public function count() {
		$iCount = 0;
		if ($this->db) {
			$iCount = $this->db->querySingle("SELECT count(*) FROM " . $this->strTableName);
		}
		return $iCount;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \lib\PNServer\PNDataProvider::fetch()
	 */
	public function fetch() {
		$strSubJSON = false;
		$this->row = null;
		if ($this->dbres !== false) {
			$this->row = $this->dbres->fetchArray(SQLITE3_ASSOC);
			if ($this->row) {
				$strSubJSON = $this->row[self::COL_SUBSCRIPTION];
			}
		}
		return $strSubJSON;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \lib\PNServer\PNDataProvider::getColumn()
	 */
	public function getColumn($strName) {
		$value = null;
		if ($this->row !== false && isset($this->row[$strName])) {
			$value = $this->row[$strName];
		}
		return $value;			
	}
	
	/**
	 * check, if table exist
	 * @return bool
	 */
	private function tableExist() {
		$bExist = false;
		if ($this->db) {
			$bExist = ($this->db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='" . $this->strTableName . "'") != null);
		}
		return $bExist;
	}
	
	/**
	 * create table
	 */
	private function createTable() {
		$bSucceeded = false;
		if ($this->db) {
			$strSQL  = "CREATE TABLE " . $this->strTableName . " (";
			$strSQL .= self::COL_ID . " INTEGER PRIMARY KEY";
			$strSQL .= "," . self::COL_ENDPOINT . " TEXT UNIQUE"; 
			$strSQL .= "," . self::COL_EXPIRES . " INTEGER NOT NULL"; 
			$strSQL .= "," . self::COL_SUBSCRIPTION . " TEXT NOT NULL";
			$strSQL .= "," . self::COL_USERAGENT . " TEXT NOT NULL";
			$strSQL .= "," . self::COL_LASTUPDATED . " INTEGER NOT NULL";
			$strSQL .= ");";
				
			$bSucceeded = $this->db->exec($strSQL);
		}
		return $bSucceeded;
	}
}
