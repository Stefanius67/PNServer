<?php
declare(strict_types = 1);

namespace SKien\PNServer;

/**
 * main class of the package to create push notifications.
 * 
 * #### History
 * - *2020-04-12*   initial version
 * - *2020-08-03*   PHP 7.4 type hint
 * 
 * @package SKien/PNServer
 * @version 1.1.0
 * @author Stefanius <s.kien@online.de>
 * @copyright MIT License - see the LICENSE file for details
*/
class PNServer
{
    use PNServerHelper;
    
    /** @var PNDataProvider dataprovider         */
    protected ?PNDataProvider $oDP = null;
    /** @var bool set when data has been loaded from DB         */
    protected bool $bFromDB = false;
    /** @var bool auto remove invalid/expired subscriptions     */
    protected bool $bAutoRemove = true;
    /** @var PNVapid        */
    protected ?PNVapid $oVapid = null;
    /** @var string         */
    protected string $strPayload = '';
    /** @var array          */
    protected array $aSubscription = [];
    /** @var array          */
    protected array $aLog = [];
    /** @var string last error msg  */
    protected string $strError = '';
    
    /**
     * create instance.
     * if $oDP specified, subscriptions can be loaded direct from data Source
     * and invalid or expired subscriptions will be removed automatically in
     * case rejection from the push service.
     *    
     * @param PNDataProvider $oDP
     */
    public function __construct(?PNDataProvider $oDP=null)
    {
        $this->oDP = $oDP;
        $this->reset();
    }
    
    /**
     * @return PNDataProvider
     */
    public function getDP() : ?PNDataProvider
    {
        return $this->oDP;
    }

    /**
     * reset ll to begin new push notification.
     */
    public function reset() : void
    {
        $this->bFromDB = false;
        $this->strPayload = '';
        $this->oVapid = null;
        $this->aSubscription = [];
        $this->aLog = [];
    }
        
    /**
     * set VAPID subject and keys.
     * @param PNVapid $oVapid
     */
    public function setVapid (PNVapid $oVapid) : void 
    {
        $this->oVapid = $oVapid;
    }
    
    /**
     * set payload used for all push notifications.
     * @param mixed $payload    string or PNPayload object
     */
    public function setPayload($payload) : void 
    {
        if (is_string($payload) || self::className($payload) == 'PNPayload') {
            $this->strPayload = (string)$payload;
        }
    }
    
    /**
     * @return string
     */
    public function getPayload() : string
    {
        return $this->strPayload;
    }
    
    /**
     * add subscription to the notification list.
     * @param PNSubscription $oSubscription
     */
    public function addSubscription(PNSubscription $oSubscription) : void
    {
        if ($oSubscription->isValid()) {
            $this->aSubscription[] = $oSubscription;
        }
    }
    
    /**
     * Get the count of valid subscriptions set.
     * @return int
     */
    public function getSubscriptionCount() : int
    {
        return count($this->aSubscription);
    }
    
    /**
     * Load subscriptions from internal DataProvider.
     * if $this->bAutoRemove set (default: true), expired subscriptions will
     * be automatically removed from the data source.
     * @return bool
     */
    public function loadSubscriptions() : bool
    {
        $bSucceeded = false;
        $this->aSubscription = [];
        if ($this->oDP) {
            if (($bSucceeded = $this->oDP->init($this->bAutoRemove)) !== false) {
                $this->bFromDB = true;
                while (($strJsonSub = $this->oDP->fetch()) !== false) {
                    $this->addSubscription(PNSubscription::fromJSON($strJsonSub));
                }
            } else {
                $this->strError = $this->oDP->getError();
            }
        } else {
            $this->strError = 'missing dataprovider!';
        }
        return $bSucceeded;
    }

    /**
     * auto remove invalid/expired subscriptions.
     * has only affect, if data loaded through DataProvider 
     * @param bool $bAutoRemove
     */
    public function setAutoRemove(bool $bAutoRemove=true) : void 
    {
        $this->bAutoRemove = $bAutoRemove;
    }
    
    /**
     * push all notifications.
     * 
     * Since a large number is expected when sending PUSH notifications, the 
     * POST requests are generated asynchronously via a cURL multi handle.
     * The response codes are then assigned to the respective end point and a 
     * transmission log is generated.
     * If the subscriptions comes from the internal data provider, all 
     * subscriptions that are no longer valid or that are no longer available 
     * with the push service will be removed from the database.
     * @return bool
     */
    public function push() : bool
    {
        if (!$this->oVapid) {
            $this->strError = 'no VAPID-keys set!';
        } elseif(!$this->oVapid->isValid()) {
            $this->strError = 'VAPID error: ' . $this->oVapid->getError();
        } elseif(count($this->aSubscription) == 0) {
            $this->strError = 'no valid Subscriptions set!';
        } else {
            // create multi requests...
            $mcurl = curl_multi_init();
            $aRequests = array();
            
            foreach ($this->aSubscription as $oSub) {
                $aLog = ['msg' => '', 'curl_response' => '', 'curl_response_code' => -1];
                // payload must be encrypted every time although it does not change, since 
                // each subscription has at least his public key and authentication token of its own ...
                $oEncrypt = new PNEncryption($oSub->getPublicKey(), $oSub->getAuth(), $oSub->getEncoding()); 
                if (($strContent = $oEncrypt->encrypt($this->strPayload)) !== false) {
                    // merge headers from encryption and VAPID (maybe both containing 'Crypto-Key')
                    if (($aVapidHeaders = $this->oVapid->getHeaders($oSub->getEndpoint())) !== false) {
                        $aHeaders = $oEncrypt->getHeaders($aVapidHeaders);
                        $aHeaders['Content-Length'] = mb_strlen($strContent, '8bit');
                        $aHeaders['TTL'] = 2419200;
            
                        // build Http - Headers
                        $aHttpHeader = array();
                        foreach ($aHeaders as $strName => $strValue) {
                            $aHttpHeader[] = $strName . ': ' . $strValue; 
                        }
                        
                        // and send request with curl
                        $curl = curl_init($oSub->getEndpoint());
                        
                        curl_setopt($curl, CURLOPT_POST, true);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $strContent);
                        curl_setopt($curl, CURLOPT_HTTPHEADER, $aHttpHeader);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        
                        curl_multi_add_handle($mcurl, $curl);
                        
                        $aRequests[$oSub->getEndpoint()] = $curl;
                    } else {
                        $aLog['msg'] = 'VAPID error: ' . $this->oVapid->getError();
                    }
                } else {
                    $aLog['msg'] = 'Payload encrypting error: ' . $oEncrypt->getError();
                }
                if (strlen($aLog['msg']) > 0) {
                    $this->aLog[$oSub->getEndpoint()] = $aLog;
                }
            }
                
            if (count($aRequests) > 0) {
                // now performing multi request...
                $iRunning = null;
                do {
                    curl_multi_exec($mcurl, $iRunning);
                } while ($iRunning);

                // ...and get response of each request
                foreach ($aRequests as $strEndPoint => $curl) {
                    $aLog = array();
                    $iRescode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                    
                    $aLog['msg'] = $this->getPushServiceResponseText($iRescode);
                    $aLog['curl_response'] = curl_multi_getcontent($curl);
                    $aLog['curl_response_code'] = $iRescode;
                    if ($this->bFromDB && $this->bAutoRemove && ($iRescode == 404 || $iRescode == 410)) {
                        // just remove subscription from DB
                        $aLog['msg'] .= ' Subscription removed from DB!';
                        $this->oDP->removeSubscription($strEndPoint);
                    }
                    $this->aLog[$strEndPoint] = $aLog;
                    // remove handle from multi and close
                    curl_multi_remove_handle($mcurl, $curl);
                    curl_close($curl);
                }
                // ... close the door
                curl_multi_close($mcurl);
            }
        }
        return (strlen($this->strError) == 0);
    }
        
    /**
     * @return array
     */
    public function getLog() : array
    {
        return $this->aLog;
    }

    /**
     * @return string last error
     */
    public function getError() : string
    {
        return $this->strError;
    }
    
    /**
     * get text according to given push service responsecode
     *
     * push service response codes
     * 201:     The request to send a push message was received and accepted.
     * 400:     Invalid request. This generally means one of your headers is invalid or improperly formatted.
     * 404:     Not Found. This is an indication that the subscription is expired and can't be used. In this case
     *          you should delete the PushSubscription and wait for the client to resubscribe the user.
     * 410:     Gone. The subscription is no longer valid and should be removed from application server. This can
     *          be reproduced by calling `unsubscribe()` on a `PushSubscription`.
     * 413:     Payload size too large. The minimum size payload a push service must support is 4096 bytes (or 4kb).
     * 429:     Too many requests. Meaning your application server has reached a rate limit with a push service.
     *          The push service should include a 'Retry-After' header to indicate how long before another request
     *          can be made.
     * 
     * @param int $iRescode
     * @return string
     */
    protected function getPushServiceResponseText(int $iRescode) : string 
    {
        $strText = 'unknwown Rescode from push service: ' . $iRescode;
        $aText = array(
            201 => "The request to send a push message was received and accepted.",
            400 => "Invalid request. Invalid headers or improperly formatted.",
            404 => "Not Found. Subscription is expired and can't be used anymore.",
            410 => "Gone. Subscription is no longer valid.", // This can be reproduced by calling 'unsubscribe()' on a 'PushSubscription'.
            413 => "Payload size too large.",
            429 => "Too many requests. Your application server has reached a rate limit with a push service."
        );
        if (isset($aText[$iRescode])) {
            $strText = $aText[$iRescode];
        }
        return $strText;
    }
}
