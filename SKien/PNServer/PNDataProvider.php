<?php
namespace SKien\PNServer;

/**
 * interface for dataproviders.
 * 
 * constructor of implementing classes will different to meet the 
 * needs of the data source.
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
interface PNDataProvider
{
    /** internal id     */
    const COL_ID            = "id";
    /** endpoint        */
    const COL_ENDPOINT      = "endpoint";
    /** expiration      */
    const COL_EXPIRES       = "expires";
    /** complete subscription as JSON-string    */
    const COL_SUBSCRIPTION  = "subscription";
    /** user agent at endpoint                  */
    const COL_USERAGENT     = "useragent";
    /** timestamp supscription last updated     */
    const COL_LASTUPDATED   = "lastupdated";

    /**
     * check, if connected to data source
     */
    public function isConnected();
    
    /**
     * saves subscription.
     * inserts new or replaces existing subscription. 
     * UNIQUE identifier alwas is the endpoint!
     * @param string $strJSON   subscription as well formed JSON-string
     * @return bool true on success
     */
    public function saveSubscription($strJSON);
    
    /**
     * remove subscription for $strEndPoint from DB.
     * @param string $strEndpoint
     * @return bool true on success
     */
    public function removeSubscription($strEndpoint);
    
    /**
     * initialization for fetching data.
     * @param bool $bAutoRemove     automatic remove of expired subscriptions
     * @return bool true on success
     */
    public function init($bAutoRemove=true);
    
    /**
     * get count of subscriptions.
     * @return int
     */
    public function count();
    
    /**
     * fetch next subscription. 
     * @return string subscription as well formed JSON-string or false at end of list
     */
    public function fetch();
    
    /**
     * get column value of last fetched row
     * @param string $strName
     * @return mixed column value or null, if no row selected or column not exist
     */ 
    public function getColumn($strName);
    
    /**
     * get last error
     * @return string
     */
    public function getError();
}