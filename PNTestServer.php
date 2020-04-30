<?php
use lib\PNServer\PNDataProvider;
use lib\PNServer\PNDataProviderSQLite;
use lib\PNServer\PNDataProviderMySQL;
use lib\PNServer\PNSubscription;
use lib\PNServer\PNVapid;
use lib\PNServer\PNPayload;
use lib\PNServer\PNServer;

require_once 'lib/PNServer/PNDataProviderSQLite.php';
require_once 'lib/PNServer/PNDataProviderMySQL.php';
require_once 'lib/PNServer/PNServer.php';

	// check, if PHP version is sufficient and all required extensions are installed
	if (version_compare(phpversion(), '7.1', '<')) {
		trigger_error('At least PHP Version 7.1 is required (current Version is ' . phpversion() . ')!', E_USER_WARNING);
	}
	$aExt = array('curl', 'gmp', 'mbstring', 'openssl', 'bcmath');
	foreach ($aExt as $strExt) {
		if (!extension_loaded($strExt)) {
			trigger_error('Extension ' . $strExt . ' must be installed!', E_USER_WARNING);
		}
	}

	// $oDP = new PNDataProviderMySQL('localhost', 'hsg', 'kien1', 'hsg-homepage');
	$oDP = new PNDataProviderSQLite();

	if (!$oDP->isConnected()) {
		exit();
	}

	// just add expired (unsubscribed) subscription to demonstrate auto-remove...
	$oDP->saveSubscription(
			'{'
			.'	"endpoint": "https://fcm.googleapis.com/fcm/send/f8PIq7EL6xI:APA91bFgD2qA0Goo_6sWgWVDKclh5Sm1Gf1BtYZw3rePs_GHqmC9l2N92I4QhLQtPmyB18HYYseFHLhvMbpq-oGz2Jtt8AVExmNU9R3K9Z-Gaiq6rQxig1WT4ND_5PSXTjuth-GoGggt",'
			.'	"expirationTime": "1589291569000",'
			.'	"keys": {'
			.'	"p256dh": "BEQrfuNX-ZrXPf0Mm-IdVMO1LMpu5N3ifgcyeUD2nYwuUhRUDmn_wVOM3eQyYux5vW2B8-TyTYco4-bFKKR02IA",'
			.'	"auth": "jOfywakW_srfHhMF-NiZ3Q"'
			.'	}'
			.'}'
	);
	
	echo 'Anzahl Subscriptions: ' . $oDP->count() . '<br/><br/>' . PHP_EOL;
	if ($oDP->init()) {
		while (($strJsonSub = $oDP->fetch()) !== false) {
			echo 'UA: ' . $oDP->getColumn(PNDataProvider::COL_USERAGENT);
			echo ' (Lastupdated: ' . date('Y-m-d H:i:s', $oDP->getColumn(PNDataProvider::COL_LASTUPDATED)) . ')<br/>' . PHP_EOL;			
			$strPrintable = json_encode(json_decode($strJsonSub), JSON_PRETTY_PRINT);
			$strPrintable = str_replace("\n", '<br/>', $strPrintable);
			$strPrintable = str_replace(" ", '&nbsp;', $strPrintable);
			echo '<span style="font-size: 10pt; font-family: courier; overflow:scroll; white-space: nowrap">' . $strPrintable . '</span><br/><br/>' . PHP_EOL;			
		}
	}
	
	$oServer = new PNServer($oDP);

	/*
	VAPID Key:
	==========
	subject: 	"mailto:s.kien@online.de",
	publicKey: 	"BDtOCcUUTYvuUzx9ktgYs3mB6tQCjFLNfOkuiaIi_2LNosLbHQY6P91eMzQ8opTDLK_PjJHsjMSiJ-MUOeSjV8E",
	privateKey: "juLDCbPNbObvn-89_o0SEbnBZLMWxlVEjGypyxHEh2M"
	*/	
	$oVapid = new PNVapid(
			"mailto:s.kien@online.de",
			"BDtOCcUUTYvuUzx9ktgYs3mB6tQCjFLNfOkuiaIi_2LNosLbHQY6P91eMzQ8opTDLK_PjJHsjMSiJ-MUOeSjV8E",
			"juLDCbPNbObvn-89_o0SEbnBZLMWxlVEjGypyxHEh2M"
	);
	$oServer->setVapid($oVapid);
	
	// same notification to each subscription...
	$oPayload = new PNPayload('', "...und ein Text zur Begrüßung.", '/homepage/images/logo.png');
	// $oPayload->setImage('/upload/images/_thumbs/news/2019-20/2020-04-06_158618126098.jpg');
	$oPayload->setTag('news', true);
	$oPayload->setURL('/homepage/news.php?id=268');
	
	$oServer->setPayload($oPayload);

	/*
	// add manual subscriptions...
	// (1) Google Chrome an Win10
	$oServer->addSubscription(PNSubscription::fromJSON(
			'{'
			.'	"endpoint": "https://fcm.googleapis.com/fcm/send/f8PIq7EL6xI:APA91bFgD2qA0Goo_6sWgWVDKclh5Sm1Gf1BtYZw3rePs_GHqmC9l2N92I4QhLQtPmyB18HYYseFHLhvMbpq-oGz2Jtt8AVExmNU9R3K9Z-Gaiq6rQxig1WT4ND_5PSXTjuth-GoGggt",'
			.'	"expirationTime": "",'
			.'	"keys": {'
			.'	"p256dh": "BEQrfuNX-ZrXPf0Mm-IdVMO1LMpu5N3ifgcyeUD2nYwuUhRUDmn_wVOM3eQyYux5vW2B8-TyTYco4-bFKKR02IA",'
			.'	"auth": "jOfywakW_srfHhMF-NiZ3Q"'
			.'	}'
			.'}'			
		));
    // (2) MS Edge on Win10
	$oServer->addSubscription(PNSubscription::fromJSON(
			'{'
			.'	"endpoint": "https://am3p.notify.windows.com/w/?token=BQYAAAAQPdu5u6Tvh2NkEr4SOxW4It9xPON1syG5Xeq8qmfyi46KsCLN9iR5G%2f1rbAd4eLtx5D7dPUY5E2N0RoB%2btDmcWn1o2ATMSsoZ8bHPR9FxyYTGDMroKYssRMWxAGxX4dgipH8qxwEpY64s3tuVNe8FgunlPzDVg58VDnoU%2f1kVZnCnZNXZ%2b54sEHC%2bAI1XhLUiCZWlAUpMZlS2C4T%2bz8DyARzJcokVkqjBE0HrLXfHmbmLgGMrpYz9fvaw3Vb%2fhTjFIOoW48VIzYHebZtk0yrShj2YJJ5bvEekwLSjs1GExFiyiWXVteVyC462VX4E7O1pz41V2n%2bdfhYagz9sfPvo",'
			.'	"expirationTime": "1589291569000",'
			.'	"keys": {'
			.'		"p256dh": "BB4ZkRnR009a1EXBjNyfl_-W9Ph9egCmMok0VrtPGu1EjKvo3ro0YHq0kteIGamXJuRXBevY2WXPKRllGJWeb4s",'
			.'		"auth": "XlZ9FtpSK6NO0DNvIMVL-A"'
			.'	}'
			.'}'			
		));
	*/
	$oServer->loadSubscriptions();
	
	if (!$oServer->push()) {
		echo '<h2>' . $oServer->getError() . '</h2>' . PHP_EOL;
	} else {
		$aLog = $oServer->getLog();
		echo '<h2>Push - Log:</h2>' . PHP_EOL;
		foreach ($aLog as $strEndpoint => $aMsg ) {
			echo '<h3>' . PNSubscription::getOrigin($strEndpoint) . '</h3>' . PHP_EOL;
			echo $aMsg['msg'];
		}
	}
	
