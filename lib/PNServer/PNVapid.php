<?php
namespace lib\PNServer;

require_once dirname(__FILE__) . '/PNServerHelper.php';

use lib\PNServer\PNSubscription;

/**
 * class to create headers from VAPID key
 * 
 * parts of the code are based on the package spomky-labs/jose
 *	@link https://github.com/Spomky-Labs/Jose 
 *
 * history:
 * date         version
 * 2020-04-12	initial version
 *
 * @package PNServer
 * @version 1.0.0
 * @author Stefanius <s.kien@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class PNVapid
{
	use PNServerHelper;
	
	/** lenght of public key (Base64URL - decoded)	*/
	const PUBLIC_KEY_LENGTH = 65;
	/** lenght of private key (Base64URL - decoded)	*/
	const PRIVATE_KEY_LENGTH = 32;

	/** @var string VAPID subject (email or uri)	*/
    protected $strSubject;
    /** @var string	public key						*/
    protected $strPublicKey;
    /** @var string	private key						*/
    protected $strPrivateKey;
    /** @var string	last error msg					*/
    protected $strError;
    
    /**
     * @param string $strSubject
     * @param string $strPublicKey
     * @param string $strPrivateKey
     */
    public function __construct($strSubject, $strPublicKey, $strPrivateKey) {
    	$this->strSubject = $strSubject;
    	$this->strPublicKey = $this->decodeBase64URL($strPublicKey);
    	$this->strPrivateKey = $this->decodeBase64URL($strPrivateKey);
    }

    /**
     * check for valid VAPID
     * - subject, public key and private key have to be set
     * - decoded public key should be 65 bytes long 
     * - no compresed public key supported 
     * - decoded private key should be 32 bytes long
     * @return bool true if valid
     */
	public function isValid() {
		if (strlen($this->strSubject) == 0 || 
			strlen($this->strPublicKey) == 0 ||
			strlen($this->strPrivateKey) == 0) {
			return false;
		}
		if (mb_strlen($this->strPublicKey, '8bit') !== self::PUBLIC_KEY_LENGTH) {
			$this->strError = 'Invalid public key length!';
			return false;
		}
		$hexPublicKey = bin2hex($this->strPublicKey);
		if (mb_substr($hexPublicKey, 0, 2, '8bit') !== '04') {
			$this->strError = 'Invalid public key: only uncompressed keys are supported.';
			return false;
		}
		if (mb_strlen($this->strPrivateKey, '8bit') !== self::PRIVATE_KEY_LENGTH) {
			$this->strError = 'Invalid private key length!';
			return false;
		}
		return true;
	}
	
	/**
	 * create header for endpoint and current timestamp.
	 * @param string $strEndpoint
	 * @return array headers if succeeded, false on error
	 */
	public function getHeaders($strEndpoint)
	{
		$aHeaders = false;
		
		// info	
		$aJwtInfo = array("typ" => "JWT", "alg" => "ES256");
		$strJwtInfo = self::encodeBase64URL(json_encode($aJwtInfo));
		
		// data
		// - origin from endpoint
		// - timeout 12h from now
		// - subject (e-mail or URL to invoker of VAPID-keys)
		$aJwtData = array(
				'aud' => PNSubscription::getOrigin($strEndpoint),
	  			'exp' => time() + 43200,
	  			'sub' => $this->strSubject
			);
		$strJwtData = self::encodeBase64URL(json_encode($aJwtData));
		
		// signature
		// ECDSA encrypting "JwtInfo.JwtData" using the P-256 curve and the SHA-256 hash algorithm
		$strData = $strJwtInfo . '.' . $strJwtData;
		$pem = self::getP256PEM($this->strPublicKey, $this->strPrivateKey);
		
		$this->strError = 'Error creating signature!';
		if (\openssl_sign($strData, $strSignature, $pem, 'sha256')) {
			if (($sig = self::signatureFromDER($strSignature)) !== false) {
				$this->strError = '';
				$strSignature = self::encodeBase64URL($sig);			
				$aHeaders = array( 
						'Authorization' => 'WebPush ' . $strJwtInfo . '.' . $strJwtData . '.' . $strSignature,
						'Crypto-Key' 	=> 'p256ecdsa=' . self::encodeBase64URL($this->strPublicKey)
					);
			}
		}
		return $aHeaders;
	}
	
	/**
	 * @return string last error
	 */
	public function getError() {
		return $this->strError;
	}
}