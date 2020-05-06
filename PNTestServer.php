<?php
use SKien\PNServer\PNDataProvider;
use SKien\PNServer\PNDataProviderSQLite;
use SKien\PNServer\PNDataProviderMySQL;
use SKien\PNServer\PNSubscription;
use SKien\PNServer\PNVapid;
use SKien\PNServer\PNPayload;
use SKien\PNServer\PNServer;

require_once 'SKien/PNServer/PNDataProviderSQLite.php';
require_once 'SKien/PNServer/PNDataProviderMySQL.php';
require_once 'SKien/PNServer/PNServer.php';

	// check, if PHP version is sufficient and all required extensions are installed
	$bExit = false;
	if (version_compare(phpversion(), '7.1', '<')) {
		trigger_error('At least PHP Version 7.1 is required (current Version is ' . phpversion() . ')!', E_USER_WARNING);
		$bExit = true;
	}
	$aExt = array('curl', 'gmp', 'mbstring', 'openssl', 'bcmath');
	foreach ($aExt as $strExt) {
		if (!extension_loaded($strExt)) {
			trigger_error('Extension ' . $strExt . ' must be installed!', E_USER_WARNING);
			$bExit = true;
		}
	}
	if ($bExit) {
		exit();
	}

	// for test use SQLite database - will be cretaed at first instantiation
	$oDP = new PNDataProviderSQLite();
	
	// or may use any MySQL database
	// $oDP = new PNDataProviderMySQL('localhost', 'username', 'password', 'db-name');
	
	if (!$oDP->isConnected()) {
	    echo $oDP->getError();
		exit();
	}

	// just add expired (unsubscribed) subscription to demonstrate response of
	// push service and the auto-remove option
	if (!$oDP->saveSubscription(
			'{'
			.'	"endpoint": "https://fcm.googleapis.com/fcm/send/f8PIq7EL6xI:APA91bFgD2qA0Goo_6sWgWVDKclh5Sm1Gf1BtYZw3rePs_GHqmC9l2N92I4QhLQtPmyB18HYYseFHLhvMbpq-oGz2Jtt8AVExmNU9R3K9Z-Gaiq6rQxig1WT4ND_5PSXTjuth-GoGggt",'
			.'	"expirationTime": "1589291569000",'
			.'	"keys": {'
			.'	"p256dh": "BEQrfuNX-ZrXPf0Mm-IdVMO1LMpu5N3ifgcyeUD2nYwuUhRUDmn_wVOM3eQyYux5vW2B8-TyTYco4-bFKKR02IA",'
			.'	"auth": "jOfywakW_srfHhMF-NiZ3Q"'
			.'	}'
			.'}'
	    )) {
	    echo $oDP->getError();
	    exit();
	}
	
	echo 'Count of subscriptions: ' . $oDP->count() . '<br/><br/>' . PHP_EOL;
	if ($oDP->init()) {
		while (($strJsonSub = $oDP->fetch()) !== false) {
			echo 'UA: ' . $oDP->getColumn(PNDataProvider::COL_USERAGENT);
			echo ' (Lastupdated: ' . date('Y-m-d H:i:s', $oDP->getColumn(PNDataProvider::COL_LASTUPDATED)) . ')<br/>' . PHP_EOL;			
			$strPrintable = json_encode(json_decode($strJsonSub), JSON_PRETTY_PRINT);
			$strPrintable = str_replace("\n", '<br/>', $strPrintable);
			$strPrintable = str_replace(" ", '&nbsp;', $strPrintable);
			echo '<span style="font-size: 10pt; font-family: courier; overflow:scroll; white-space: nowrap">' . $strPrintable . '</span><br/><br/>' . PHP_EOL;			
		}
	} else {
    	echo $oDP->getError();
    	exit();
	}
	
	// the server to handle all
	$oServer = new PNServer($oDP);

	// set the VAPID key
	/*
	$oVapid = new PNVapid(
			"mailto:yourmail@yourdomain.de",
			"the-generated-public-key",
			"the-generated-private-key"
	   );
	*/
	$oVapid = new PNVapid(
	       "mailto:s.kien@online.de",
	       "BDtOCcUUTYvuUzx9ktgYs3mB6tQCjFLNfOkuiaIi_2LNosLbHQY6P91eMzQ8opTDLK_PjJHsjMSiJ-MUOeSjV8E",
	       "juLDCbPNbObvn-89_o0SEbnBZLMWxlVEjGypyxHEh2M"
	   );
	        
	$oServer->setVapid($oVapid);
	
	// create payload
	// - we don't set a title - so service worker uses default
	// - URL to icon can be
	//    * relative to the origin location of the service worker
	//    * absolute from the homepage (begining with a '/')
	//    * complete URL (beginning with https://) 
	$oPayload = new PNPayload('', "...first text to display.", './elephpant.png');
	// set tag to group the notifications but always show the popup 
	$oPayload->setTag('news', true);
	// and lead the user to thr page of your choice
	$oPayload->setURL('/where-to-go.php');
	
	$oServer->setPayload($oPayload);

	// load subscriptions from database (incluing the expired one created above...) 
	if (!$oServer->loadSubscriptions()) {
	    echo $oDP->getError();
	    exit();
	}
	
	// ... and finally push !
	if (!$oServer->push()) {
		echo '<h2>' . $oServer->getError() . '</h2>' . PHP_EOL;
	} else {
		$aLog = $oServer->getLog();
		echo '<h2>Push - Log:</h2>' . PHP_EOL;
		foreach ($aLog as $strEndpoint => $aMsg ) {
			echo '<h3>' . PNSubscription::getOrigin($strEndpoint) . '</h3>' . PHP_EOL;
			echo $aMsg['msg'] . '<br/>resonse code: ' . $aMsg['curl_response_code']  . ' (' . $aMsg['curl_response'] . ')';	
		}
	}
