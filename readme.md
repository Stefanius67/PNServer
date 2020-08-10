# PNServer - Web Push Notifications for your Homepage

 ![Latest Stable Version](https://img.shields.io/badge/release-v1.1.0-brightgreen.svg)
 ![License](https://img.shields.io/packagist/l/gomoob/php-pushwoosh.svg)
 [![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.1-8892BF.svg)](https://php.net/)
 [![PHPStan](https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat)](https://phpstan.org/)
 [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Stefanius67/PNServer/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Stefanius67/PNServer/?branch=master)
----------
With this package, web push notifications can be created, encrypted and sent via HTTP request. The subscriptions can be saved and managed. Optionally, the package automatically deletes expired or no longer valid subscriptions.
The JavaScript code required on the client side is also included in the package - this has to be slightly adapted to your own project.

**there are no dependencies to other external libraries!**

## required PHP Libraries
- cURL (curl)
- Multibyte String (mbstring)
- OpenSSL (openssl)
- GNU Multiple Precision (gmp)
- BC Math (bcmath)

## Installation   
You can download the  Latest [release version ](https://www.phpclasses.org/package/11632-PHP-Queue-and-push-notifications-to-Web-users.html) from PHPClasses.org

required adaptions for your own project (in *PNServiceworker.js*):
```javascript
  // VAPID appPublic key
  const strAppPublicKey   = 'create your own VAPID key pair and insert public key here';
  // URL to save subscription on server via Fetch API
  const strSubscriberURL  = 'https://www.your-domain.org/PNSubscriber.php';
  // default Notification Title if not pushed by server
  const strDefTitle       = 'Your company or product';
  // default Notification Icon if not pushed by server
  const strDefIcon        = './elephpant.png';
```

you can generate your own VAPID key on [https://tools.reactpwa.com/vapid](https://tools.reactpwa.com/vapid).

## Usage
A [tutorial](https://www.phpclasses.org/blog/package/11632/post/1-How-to-Use-PHP-to-Send-Web-Push-Notifications-for-Your-Web-Site-in-2020.html) describing the individual steps for using the package is available at [PHPclasses.org](https://www.phpclasses.org/blog/package/11632/post/1-How-to-Use-PHP-to-Send-Web-Push-Notifications-for-Your-Web-Site-in-2020.html). 

*PnTestClient.html* shows a simple example Page to subscribe the push notifications.

*PNTestServer.php* demonstrates, how the Notification Server can be implemented:

rename *MyVapid.php.org* to *MyVapid.php* and set your own keys:
```php
  $oVapid = new PNVapid(
      "mailto:yourmail@yourdomain.de",
      "your-generated-public-key",
      "your-generated-private-key"
  );
```

