<?php
namespace SKien\PNServer;

use SKien\PNServer\PNDataProvider;

require_once dirname(__FILE__) . '/PNDataProvider.php';

/**
 * dataprovider for MySQL database
 * uses given Table in specified MySQL database
 * 
 * if not specified in constructor, default table 'tPNSubscription' in 
 * MySQL database is used. 
 * table will be created if not exist so far.
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
class PNDataProviderMySQL implements PNDataProvider 
{
    /** @var string tablename            */
    protected   $strTableName;
    /** @var string DB host  */
    protected   $strDBHost = 'localhost';
    /** @var string DB user  */
    protected   $strDBUser = 'root';
    /** @var string Password for DB  */
    protected   $strDBPwd = 'kien1';
    /** @var string DB name  */
    protected   $strDBName;
    /** @var \mysqli internal MySQL DB   */
    protected   $db = null;
    /** @var \mysqli_result result of DB queries */
    protected   $dbres = false;
    /** @var array last fetched row or null      */
    protected   $row = null;
    /** @var string last error                   */
    protected   $strLastError;
    /** @var bool does table exist               */
    protected   $bTableExist = null;
    
    /**
     * @param string $strDBHost     DB Host
     * @param string $strDBUser     DB User
     * @param string $strDBPwd      DB Password
     * @param string $strDBName     DB Name
     * @param string $strTableName  tablename for the subscriptions - if null, 'tPNSubscription' is used and created if not exist
     */
    public function __construct($strDBHost, $strDBUser, $strDBPwd, $strDBName, $strTableName=null) {
        $this->strDBHost = $strDBHost; 
        $this->strDBUser = $strDBUser; 
        $this->strDBPwd = $strDBPwd; 
        $this->strDBName = $strDBName; 
        $this->strTableName = isset($strTableName) ? $strTableName : 'tPNSubscription';
        
        $this->db = @mysqli_connect($strDBHost, $strDBUser, $strDBPwd, $strDBName);
        if ($this->db !== false) {
            if (!$this->tableExist()) {
                $this->createTable();
            }
        } else {
            $this->strLastError = 'MySQL: Connect Error ' . mysqli_connect_errno();
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
        if ($this->db) {
            $oSubscription = json_decode($strJSON, true);
            if ($oSubscription) {
                $iExpires = isset($oSubscription['expirationTime']) ? bcdiv($oSubscription['expirationTime'], 1000) : 0;
                $tsExpires = $iExpires > 0 ? date("'Y-m-d H:i:s'", $iExpires) : 'NULL';
                $strUserAgent = isset($oSubscription['userAgent']) ? $oSubscription['userAgent'] : 'unknown UserAgent';
                                
                $strSQL  = "INSERT INTO " . $this->strTableName . " (";
                $strSQL .=       self::COL_ENDPOINT;
                $strSQL .= "," . self::COL_EXPIRES;
                $strSQL .= "," . self::COL_SUBSCRIPTION;
                $strSQL .= "," . self::COL_USERAGENT;
                $strSQL .= ") VALUES(";
                $strSQL .= "'" . $oSubscription['endpoint'] . "'";
                $strSQL .= "," . $tsExpires;
                $strSQL .= ",'" . $strJSON . "'";
                $strSQL .= ",'" . $strUserAgent . "'";
                $strSQL .= ") ";
                $strSQL .= "ON DUPLICATE KEY UPDATE ";  // in case of UPDATE UA couldn't have been changed - endpoint is the UNIQUE key!
                $strSQL .= " expires = " . $tsExpires;
                $strSQL .= ",subscription = '" . $strJSON . "'";
                $strSQL .= ";";
                
                $bSucceeded = $this->db->query($strSQL);
                $this->strLastError = $this->db->error;
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
            $strSQL  = "DELETE FROM " . $this->strTableName . " WHERE endpoint LIKE ";
            $strSQL .= "'" . $strEndpoint . "'";
        
            $bSucceeded = $this->db->query($strSQL);
            $this->strLastError = $this->db->error;
        }
        return $bSucceeded;
    }
    
    /**
     * select all subscriptions not expired so far
     * 
     * columns expired and lastupdated are timestamp for better handling and visualization 
     * e.g. in phpMyAdmin. For compatibility reasons with other dataproviders the query 
     * selects the unis_timestamp values  
     * 
     * (non-PHPdoc)
     * @see \lib\PNServer\PNDataProvider::init()
     */
    public function init($bAutoRemove=true) {
        $bSucceeded = false;
        $this->dbres = false;
        $this->row = null;
        if ($this->db) {
            $strWhere = '';
            if ($bAutoRemove) {
                // remove expired subscriptions from DB
                $strSQL = "DELETE FROM " . $this->strTableName . " WHERE ";
                $strSQL .= self::COL_EXPIRES . " != NULL AND ";
                $strSQL .= self::COL_EXPIRES . " < NOW()";
            
                $bSucceeded = $this->db->query($strSQL);
                if (!$bSucceeded) {
                    $this->strLastError = 'MySQL: ' . $this->db->error;
                }
            } else {
                // or just exclude them from query
                $strWhere  = " WHERE ";
                $strWhere .= self::COL_EXPIRES . " = NULL OR ";
                $strWhere .= self::COL_EXPIRES . " >= NOW()";
                $bSucceeded = true;
            }
            if ($bSucceeded) {
                $strSQL  = "SELECT ";
                $strSQL .=       self::COL_ID;
                $strSQL .= "," . self::COL_ENDPOINT;
                $strSQL .= ",UNIX_TIMESTAMP(" . self::COL_EXPIRES . ") AS " . self::COL_EXPIRES;
                $strSQL .= "," . self::COL_SUBSCRIPTION;
                $strSQL .= "," . self::COL_USERAGENT;
                $strSQL .= ",UNIX_TIMESTAMP(" . self::COL_LASTUPDATED . ") AS " . self::COL_LASTUPDATED;
                $strSQL .= " FROM " . $this->strTableName . $strWhere;
    
                $this->dbres = $this->db->query($strSQL);
                if ($this->dbres === false) {
                    $this->strLastError = 'MySQL: ' . $this->db->error;
                    $bSucceeded = false;
                }
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
            $dbres = $this->db->query("SELECT count(*) AS iCount FROM " . $this->strTableName);
            $row = $dbres->fetch_array(MYSQLI_ASSOC);
            $iCount = $row['iCount'];
        }
        return $iCount;
    }
    
    /**
     * (non-PHPdoc)
     * @see \lib\PNServer\PNDataProvider::fetch()
     */
    public function fetch() {
        $strSubJSON = false;
        if ($this->dbres !== false) {
            $this->row = $this->dbres->fetch_array(MYSQLI_ASSOC);
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
            if ($strName == self::COL_EXPIRES || $strName == self::COL_LASTUPDATED) {
                
            }
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
                $dbres = $this->db->query("SHOW TABLES LIKE '" . $this->strTableName . "'");
                $this->bTableExist = $dbres->num_rows > 0;
            }
        }
        return $this->bTableExist;
    }
    
    /**
     * create table if not exist
     */
    private function createTable() {
        $bSucceeded = false;
        if ($this->db) {
            $strSQL  = "CREATE TABLE IF NOT EXISTS " . $this->strTableName . " (";
            $strSQL .= " " . self::COL_ID . " int NOT NULL AUTO_INCREMENT";
            $strSQL .= "," . self::COL_ENDPOINT . " text NOT NULL";
            $strSQL .= "," . self::COL_EXPIRES . " timestamp NULL DEFAULT NULL";
            $strSQL .= "," . self::COL_SUBSCRIPTION . " text NOT NULL";
            $strSQL .= "," . self::COL_USERAGENT . " varchar(255) NOT NULL";
            $strSQL .= "," . self::COL_LASTUPDATED . " timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
            $strSQL .= ",PRIMARY KEY (id)";
            $strSQL .= ",UNIQUE (endpoint(500))";
            $strSQL .= ") ENGINE=InnoDB;";
                
            $bSucceeded = $this->db->query($strSQL);
            $this->strLastError = $this->db->error;
        }
        $this->bTableExist = $bSucceeded; 
        return $bSucceeded;
    }
}
