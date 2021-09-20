<?php
require_once 'autoloader.php';
require_once 'MyVapid.php';
require_once 'MyLogger.php';

use SKien\PNServer\PNDataProviderSQLite;
use SKien\PNServer\PNPayload;
use SKien\PNServer\PNServer;
use SKien\PNServer\PNSubscription;

/**
 * Example to send single push notifications.
 *
 * For easy setup we use the SQLite dataprovider and set the database file
 * located in the same directory as this script.
 *
 * First you should open PNTestClient.html in your browser and click the
 * [Subscribe] button to create a valis subscription in your database.
 *
 * Needed steps to send notification to all subscriptions stored in our database:
 * 1. Create and init dataprovider for database containing at least one valid subscription
 * 2. Set our VAPID keys (rename MyVapid.php.org to MyVapid.php ans set your own keys)
 * 3. Create and set the payload
 * 4. manuelly step through the Subscriptions...
 * 5. .. and push each of the notification by single call
 *
 * After the notification was pushed, a summary and/or a detailed log can be
 * retrieved from the server
 *
 * If you want to log several events or errors, you can pass any PSR-3 compliant
 * logger of your choice to the PNDataProvider- and PNServer-object.
 *
 * THIS CODE IS INTENDED ONLY AS EXAMPLE - DONT USE IT DIRECT IN YOU PROJECT
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */

// check, if PHP version is sufficient and all required extensions are installed
$bExit = false;
if (version_compare(phpversion(), '7.4', '<')) {
	trigger_error('At least PHP Version 7.4 is required (current Version is ' . phpversion() . ')!', E_USER_WARNING);
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

// for this test we use SQLite database
$logger = createLogger();
$oDP = new PNDataProviderSQLite(null, null, null, $logger);
if (!$oDP->isConnected()) {
    echo $oDP->getError();
	exit();
}

echo 'Count of subscriptions: ' . $oDP->count() . '<br/><br/>' . PHP_EOL;
if (!$oDP->init()) {
	echo $oDP->getError();
	exit();
}

// the server to handle all
$oServer = new PNServer();
$oServer->setLogger($logger);

// Set our VAPID keys
$oServer->setVapid(getMyVapid());

// create and set payload
// - we don't set a title - so service worker uses default
// - URL to icon can be
//    * relative to the origin location of the service worker
//    * absolute from the homepage (begining with a '/')
//    * complete URL (beginning with https://)
$oPayload = new PNPayload('', "...first text to display.", './elephpant.png');
$oPayload->setTag('news', true);
$oPayload->setURL('/where-to-go.php');

$oServer->setPayload($oPayload);

while (($strJsonSub = $oDP->fetch()) !== false) {
    $oServer->pushSingle(PNSubscription::fromJSON((string) $strJsonSub));
}

$aLog = $oServer->getLog();
echo '<h2>Push - Log:</h2>' . PHP_EOL;
foreach ($aLog as $strEndpoint => $aMsg ) {
    echo PNSubscription::getOrigin($strEndpoint) . ':&nbsp;' .$aMsg['msg'] . '<br/>' . PHP_EOL;
}
