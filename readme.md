# slimPN - Web Push Notifications for your Homepage

![Latest Stable Version](https://img.shields.io/badge/release-v1.0.0-brightgreen.svg) ![License](https://img.shields.io/packagist/l/gomoob/php-pushwoosh.svg) [![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.1-8892BF.svg)](https://php.net/)
----------
With this package, web push notifications can be created, encrypted and sent via HTTP request. The subscriptions can be saved and managed. Optionally, the package automatically deletes expired or no longer valid subscriptions.
The JavaScript code required on the client side is also included in the package - this has to be slightly adapted to your own project.

**there are no dependencies to other external libraries!**

PHP >= 7.1 is required to be able to generate the necessary encryption keys  - The curve_name configarg was added to openssl_pkey_new() in this version to make it possible to create EC keys!
## required PHP Libraries
- cURL (curl)
- Multibyte String (mbstring)
- OpenSSL (openssl)
- GNU Multiple Precision (gmp)
- BC Math (bcmath)

## Installation   
You can download the  Latest [release version ](https://www.phpclasses.org/package/xxxxx-xxxxxxxxxxxxxxxxxxx.html) from PHPClasses.org

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

## Usage
*PnClient.html* shows a simple Page to subscribe the push notifications.

*PNServerTest.php* demonstrates, how the Notification Server can be implemented.

A [tutorial](https://www.phpclasses.org/blog_post.html?view_post=2046&key=56f8ae) describing the individual steps for using the package is available at [PHPclasses.org](https://www.phpclasses.org/blog_post.html?view_post=2046&key=56f8ae). 
The script PNTest.php shows the usage of the classes.
