/**
 * debug ServiceWorker in Firefox: about:debugging
 */
// encode the base64 public key to Array buffer
function encodeB64ToUint8Array(strBase64) {
	var strPadding = '='.repeat((4 - (strBase64.length % 4)) % 4);
	var strBase64 = (strBase64 + strPadding).replace(/\-/g, '+').replace(/_/g, '/');
	var rawData = atob(strBase64);
	var aOutput = new Uint8Array(rawData.length);
	for (i = 0; i < rawData.length; ++i) {
		aOutput[i] = rawData.charCodeAt(i);
	}
	return aOutput;
}

async function pnSubscribe(event) {
	console.log('activate event: ' + event);
	try {
		var appPublicKey = encodeB64ToUint8Array('BDtOCcUUTYvuUzx9ktgYs3mB6tQCjFLNfOkuiaIi_2LNosLbHQY6P91eMzQ8opTDLK_PjJHsjMSiJ-MUOeSjV8E');
		console.log('appPublicKey: ' + appPublicKey);
		var opt = {
				applicationServerKey: appPublicKey, 
				userVisibleOnly: true
			};
		
		self.registration.pushManager.subscribe(opt)
			.then((sub) => {
	            // subscription succeeded
	            console.log('Subscription succeeded. sub: ' + JSON.stringify(sub));
	    	    pnSaveSubscription(sub)
	    	    	.then((response) => {
	    	    		console.log(response);
	    	    	}).catch((error) => {
	    	            // registration failed
	    	            console.log('SaveSubscription failed with: ' + error);
	    	        });
	        }, ).catch((error) => {
	            // registration failed
	            console.log('Subscription failed with: ' + error);
	        });
        
	} catch (e) {
		console.log('Error subscribing notifications' + e);
	}
	console.log('end activate event');
}

async function pnSaveSubscription(sub) {
	// TODO: set server and base directory! (OR just use same directory this script located?)
	var strURL = 'https://www.hsg-ortenau-sued.de/test/PNSubscriber.php';
	// add user agent for internal information
	sub.userAgent = navigator.userAgent;
	var fetchdata = {
			method: 'post',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(sub),
	  	};
	var response = await fetch(strURL, fetchdata);
	return response.json();
}

// called once when sw is activated
self.addEventListener('activate', pnSubscribe);

function pnPopupNotification(event) {
	console.log('push event: ' + event);
	var strText = 'Notification without data!';
	var strIcon = './elephpant.png';
	var strTag = '';
	var oPayload = null;
	if (event.data) {
		try {
			oPayload = JSON.parse(event.data.text());
		} catch (e) {
			strText = event.data.text();
		}
		if (oPayload) {
			strText = oPayload.msg;
			strIcon = oPayload.icon;
			strTag  = oPayload.tag;
		}
	}
	var opt = {
			icon: strIcon,
			body: strText,
			tag:  strTag,
			data: oPayload
		};
	event.waitUntil(self.registration.showNotification('Neue Notification', opt));
}

// and listen to incomming push notifications
self.addEventListener('push', pnPopupNotification);

function pnNotificationClick(event) {
	console.log('notificationclick event: ' + event);
	if (event.notification.data) {
		const ow = clients.openWindow(event.notification.data.url);
		event.waitUntil(ow);
	}
}

// clients.openWindow(url)
self.addEventListener('notificationclick', pnNotificationClick);

// onpushsubscriptionchange 

