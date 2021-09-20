<?php
require_once 'MyVapid.php';

use SKien\PNServer\PNPayload;
use SKien\PNServer\PNServer;
use SKien\PNServer\PNSubscription;

/**
 * Example to demonstarte how to send a welcome notification to each
 * user newly subscribed our service.
 *
 * This function is called within the Handler for the HTTP-Request send from
 * the ServiceWorker to subscribe. (PNSubscriber.php)
 * After the subscription was saved in the database, this function is called,
 * if the var $bSendWelcome is set to true!
 *
 * THIS CODE IS INTENDED ONLY AS EXAMPLE - DONT USE IT DIRECT IN YOU PROJECT
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */

/**
 * @param PNSubscription $oSubscription
 */
function sendWelcome(PNSubscription $oSubscription)
{
    // create server. Since we are sending to a single subscription that was
    // passed as argument, we do not need a dataprovider
    $oServer = new PNServer();

    // create payload message for welcome...
    $oPayload = new PNPayload('Welcome to PNServer', 'We warmly welcome you to our homepage.', './elephpant.png');

    // set VAPID, payload and push to the passed subscription
    $oServer->setVapid(getMyVapid());
    $oServer->setPayload($oPayload);
    $oServer->pushSingle($oSubscription);
}