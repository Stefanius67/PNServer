<?php
namespace SKien\PNServer;

use SKien\PNServer\PNDataProvider;

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
 * 2020-04-02   initial version
 * 
 * @package PNServer
 * @version 1.0.0
 * @author Stefanius <s.kien@online.de>
 * @copyright MIT License - see the LICENSE file for details
*/
class PNDataProviderSQLite implements PNDataProvider 
{
    /** @var string tablename                    */
    protected   $strTableName;
    /** @var string name of the DB file          */
    protected   $strDBName; 
    /** @var \SQLite3 internal SqLite DB         */
    protected   $db = null;
    /** @var \SQLite3Result result of DB queries */
    protected   $dbres = false;
    /** @var array last fetched row or null      */
    protected   $row = null;
    /** @var string last error                   */
    protected   $strLastError;
    /** @var bool does table exist               */
    protected   $bTableExist = null;
    
    /**
     * @param string $strDir        directory -  if null, current working directory assumed
     * @param string $strDBName     name of DB file - if null, file 'pnsub.sqlite' is used and created if not exist
     * @param string $strTableName  tablename for the subscriptions - if null, 'tPNSubscription' is used and created if not exist
     */
    public function __construct($strDir=null, $strDBName=null, $strTableName=null) {
        $this->strTableName = isset($strTableName) ? $strTableName : 'tPNSubscription';
        $this->strDBName = isset($strDBName) ? $strDBName : 'pnsub.sqlite';
        $this->strLastError = ''; 
        try {
            $strDBName = $this->strDBName;
            if (strlen($strDir) > 0) {
                $strDBName = rtrim($strDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->strDBName;
            }
            if (file_exists($this->strDBName) && !is_writable($this->strDBName) && 0) {
                $this->strLastError .= 'readonly database file ' . $this->strDBName . '!';
            } else {
                $this->db = new \SQLite3($strDBName);
                if ($this->db && !$this->tableExist()) {
                    $this->createTable();
                }
                $w = is_writable(__DIR__);
            }
        } catch (\Exception $e) {
            $this->db = null;
            $this->strLastError = $e->getMessage();
            if (!file_exists($this->strDBName)) {
                $strDir = pathinfo($this->strDBName, PATHINFO_DIRNAME) == '' ?  __DIR__ : pathinfo($this->strDBName, PATHINFO_DIRNAME);
                if (!is_writable($strDir)) {
                   $this->strLastError .= ' (no rights to write on directory ' . $strDir . ')';
                }
            }
        }
    }

    /**
     * (non-PHPdoc)
     * @see \lib\PNServer\PNDataProvider::isConnected()
     */
    public function isConnected() {
        if (!$this->db) {
            if (strlen($this->strLastError) == 0) {
                $this->strLastError = 'no database connected!';
            }
        } else if (!$this->tableExist()) {
            if (strlen($this->strLastError) == 0) {
               $this->strLastError = 'database table ' . $this->strTableName . ' not exist!';
            }
        }
        return ($this->db && $this->bTableExist);
    }
    
    /**
     * (non-PHPdoc)
     * @see \lib\PNServer\PNDataProvider::saveSubscription()
     */
    public function saveSubscription($strJSON) {
        $bSucceeded = false;
        try {
            if ($this->isConnected()) {
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
                    if (!$bSucceeded) {
                        $this->strLastError = 'SQLite3: ' . $this->db->lastErrorMsg();
                    }
                }
            }
        } catch (\Exception $e) {
            $this->strLastError = $e->getMessage();
            $bSucceeded = false;
        }
        return $bSucceeded;
    }
    
    /**
     * (non-PHPdoc)
     * @see \lib\PNServer\PNDataProvider::removeSubscription()
     */
    public function removeSubscription($strEndpoint) {
        $bSucceeded = false;
        try {
            if ($this->isConnected()) {
                $strSQL  = "DELETE FROM " . $this->strTableName . " WHERE " . self::COL_ENDPOINT . " LIKE ";
                $strSQL .= "'" . $strEndpoint . "'";
            
                $bSucceeded = $this->db->exec($strSQL);
                if (!$bSucceeded) {
                    $this->strLastError = 'SQLite3: ' . $this->db->lastErrorMsg();
                }
            }
        } catch (\Exception $e) {
            $this->strLastError = $e->getMessage();
            $bSucceeded = false;
        }
        return $bSucceeded;
    }
    
    /**
     * select all subscriptions not expired so far
     * (non-PHPdoc)
     * @see \lib\PNServer\PNDataProvider::init()
     */
    public function init($bAutoRemove=true) {
        $bSucceeded = false;
        $this->dbres = false;
        $this->row = null;
        try {
            if ($this->isConnected()) {
                if ($bAutoRemove) {
                    // remove expired subscriptions from DB
                    $strSQL = "DELETE FROM " . $this->strTableName . " WHERE ";
                    $strSQL .= self::COL_EXPIRES . " != 0 AND ";
                    $strSQL .= self::COL_EXPIRES . " < " . time();
                    
                    $bSucceeded = $this->db->query($strSQL);
                    if (!$bSucceeded) {
                        $this->strLastError = 'SQLite3: ' . $this->db->lastErrorMsg();
                    }
                    $strSQL = "SELECT * FROM " . $this->strTableName;
                } else {
                    // or just exclude them from query
                    $strSQL = "SELECT * FROM " . $this->strTableName . " WHERE ";
                    $strSQL .= " WHERE ";
                    $strSQL .= self::COL_EXPIRES . " = 0 OR ";
                    $strSQL .= self::COL_EXPIRES . " >= " . time();
                    $bSucceeded = true;
                }
                if ($bSucceeded) {
                    $this->dbres = $this->db->query($strSQL);
                    if ($this->dbres && ($this->dbres->numColumns() == 0)) {
                        $this->dbres = false;
                    }
                    if ($this->dbres === false) {
                        $this->strLastError = 'SQLite3: ' . $this->db->lastErrorMsg();
                        $bSucceeded = false;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->strLastError = $e->getMessage();
            $bSucceeded = false;
        }
        return $bSucceeded;
    }

    /**
     * (non-PHPdoc)
     * @see \lib\PNServer\PNDataProvider::count()
     */
    public function count() {
        $iCount = false;
        if ($this->isConnected()) {
            $iCount = $this->db->querySingle("SELECT count(*) FROM " . $this->strTableName);
            if ($iCount === false) {
               $this->strLastError = 'SQLite3: ' . $this->db->lastErrorMsg();
            }
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
            } else {
                $this->strLastError = 'SQLite3: ' . $this->db->lastErrorMsg();
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
     * get last error
     * @return string
     */
    public function getError()
    {
        return $this->strLastError;
    }
    
    /**
     * check, if table exist
     * @return bool
     */
    private function tableExist() {
        if ($this->bTableExist === null) {
            if ($this->db) {
                $this->bTableExist = ($this->db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='" . $this->strTableName . "'") != null);
            }
        }
        return $this->bTableExist;
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
        $this->bTableExist = $bSucceeded;
        return $bSucceeded;
    }
}
