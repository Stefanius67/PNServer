<?php
namespace SKien\PNServer;

require_once dirname(__FILE__) . '/PNServerHelper.php';

/**
 * class representing subscrition
 * 
 * history:
 * date         version
 * 2020-04-12   initial version
 *
 * @package PNServer
 * @version 1.0.0
 * @author Stefanius <s.kien@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class PNSubscription
{
    use PNServerHelper;
    
    /** @var string the endpoint URL for the push notification       */
    protected $strEndpoint;
    /** @var string public key          */
    protected $strPublicKey;
    /** @var string authentification token              */
    protected $strAuth;
    /** @var int unix timesatmp of expiration (0, if no expiration defined)  */
    protected $timeExpiration;
    /** @var string encoding ('aesgcm' / 'aes128gcm')    */
    protected $strEncoding;
    
    /**
     * use static method PNSubscription::fromJSON() if data available as JSON-string
     * 
     * @param string $strEndpoint
     * @param string $strPublicKey
     * @param string $strAuth
     * @param number $timeExpiration
     * @param string $strEncoding
     */
    public function __construct($strEndpoint, $strPublicKey, $strAuth, $timeExpiration=0, $strEncoding='aesgcm') {
        $this->strEndpoint = $strEndpoint;
        $this->strPublicKey = $strPublicKey;
        $this->strAuth = $strAuth;
        $this->timeExpiration = $timeExpiration;
        $this->strEncoding = $strEncoding;
    }

    /**
     * @param string $strJSON   subscription as well formed JSON string 
     * @return \lib\PNServer\PNSubscription
     */
    public static function fromJSON($strJSON) {
        $strEndpoint = '';
        $strPublicKey = '';
        $strAuth = '';
        $timeExpiration = 0;
        $aJSON = json_decode($strJSON, true);
        if (isset($aJSON['endpoint'])) {
            $strEndpoint = $aJSON['endpoint'];
        }
        if (isset($aJSON['expirationTime'])) {
            $timeExpiration = bcdiv($aJSON['expirationTime'], 1000);
        }
        if (isset($aJSON['keys'])) {
            if (isset($aJSON['keys']['p256dh'])) {
                $strPublicKey = $aJSON['keys']['p256dh'];
            }
            if (isset($aJSON['keys']['auth'])) {
                $strAuth = $aJSON['keys']['auth'];
            }
        }
        return new self($strEndpoint, $strPublicKey, $strAuth, $timeExpiration);
    }

    /**
     * basic check if object containing valid data
     * - endpoint, public key and auth token msut be set
     * - only encoding 'aesgcm' or 'aes128gcm' supported
     * @return boolean
     */
    public function isValid() {
        $bValid = false;
        if (!$this->isExpired()) {
            $bValid = (
                    isset($this->strEndpoint) && strlen($this->strEndpoint) > 0 &&
                    isset($this->strPublicKey) && strlen($this->strPublicKey) > 0 &&
                    isset($this->strAuth) && strlen($this->strAuth) > 0 &&
                    ($this->strEncoding == 'aesgcm'|| $this->strEncoding == 'aes128gcm') 
                );
        }
        return $bValid;
    }
    
    /**
     * @return string
     */
    public function getEndpoint () {
        return $this->strEndpoint;
    }

    /**
     * @return string
     */
    public function getPublicKey () {
        return $this->strPublicKey;
    }

    /**
     * @return string
     */
    public function getAuth () {
        return $this->strAuth;
    }

    /**
     * @return string
     */
    public function getEncoding () {
        return $this->strEncoding;
    }

    /**
     * @param string $strEndpoint
     */
    public function setEndpoint ($strEndpoint) {
        $this->strEndpoint = $strEndpoint;
    }

    /**
     * @param string $strPublicKey
     */
    public function setPublicKey ($strPublicKey) {
        $this->strPublicKey = $strPublicKey;
    }

    /**
     * @param string $strAuth
     */
    public function setAuth ($strAuth) {
        $this->strAuth = $strAuth;
    }

    /**
     * @param number $timeExpiration
     */
    public function setExpiration ($timeExpiration) {
        $this->timeExpiration = $timeExpiration;
    }

    /**
     * @param string $strEncoding
     */
    public function setEncoding ($strEncoding) {
        $this->strEncoding = $strEncoding;
    }

    /**
     * @return boolean
     */
    public function isExpired() {
        return ($this->timeExpiration != 0 && $this->timeExpiration < time());  
    }
    
    /**
     * extract origin from endpoint
     * @param string $strEndpoint endpoint URL
     * @return string
     */
    public static function getOrigin($strEndpoint) {
        return parse_url($strEndpoint, PHP_URL_SCHEME) . '://' . parse_url($strEndpoint, PHP_URL_HOST);
    }
}