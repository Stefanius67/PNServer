/**
 * functions to support PUSH notifications (PN) on user client
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

/**
 * subscribe Push notifications (PN)
 * - check, if PN's are available
 * - request users permission, if not done so far
 * - register service worker, if permisson is granted  
 */
async function pnSubscribe() {
    var swReg = null;
    if (pnAvailable()) {
        // if not granted or denied so far...
        if (window.Notification.permission === "default") {
            await window.Notification.requestPermission();
        }
        if (Notification.permission === 'granted') {
            // register service worker (browser checks if allready registered)
            swReg = await pnRegisterSW();
        }
    }
    return swReg;
}

async function pnUnsubscribe() {
    var swReg = null;
    if (pnAvailable()) {
        // unfortunately there is no function to reset Notification permission...
        // unregister service worker
        await pnUnregisterSW();
    }
}

async function pnSubscribed() {
    var swReg = undefined;
    if (pnAvailable()) {
        swReg = await navigator.serviceWorker.getRegistration();
    }
    return (swReg != undefined);
}

/**
 * checks whether all requirements for PN are met
 * 1. have to run in secure context
 *    - window.isSecureContext = true
 * 2. browser should implement at least
 *    - navigatpr.serviceWorker
 *    - window.PushManager
 *    - window.Notification
 *    
 * @returns boolen
 */
function pnAvailable() {
    var bAvailable = window.isSecureContext;
    if (bAvailable) {
        bAvailable = (('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window)); 
    } else {
        console.log('site have to run in secure context!');
    }
    return bAvailable;
}

async function pnRegisterSW() {
    var swReg;
    navigator.serviceWorker.register('PNServiceWorker.js')
        .then((swReg) => {
            // registration worked
            console.log('Registration succeeded. Scope is ' + swReg.scope);
        }).catch((error) => {
            // registration failed
            console.log('Registration failed with ' + error);
        });
    return swReg;
}

async function pnRegisterSWTest() {
	  return navigator.serviceWorker.register('SW.js')
	  	.then(function(registration) {
	  		const subscribeOptions = {
	  				userVisibleOnly: true //,
	  				//applicationServerKey: encodeB64ToUint8Array(
	  				//		'BDtOCcUUTYvuUzx9ktgYs3mB6tQCjFLNfOkuiaIi_2LNosLbHQY6P91eMzQ8opTDLK_PjJHsjMSiJ-MUOeSjV8E'
	  				//	)
	  			};
	  		return registration.pushManager.subscribe(subscribeOptions);
	  	})
	  	.then(function(pushSubscription) {
	  		console.log('Received PushSubscription: ', JSON.stringify(pushSubscription));
	  		return pushSubscription;
	  	});
}


async function pnUnregisterSW() {
    navigator.serviceWorker.getRegistration()
        .then(function(reg) {
            reg.unregister()
                .then(function(bOK) {
                    if (bOK) {
                        console.log('unregister service worker succeeded.');
                    } else {
                        console.log('unregister service worker failed.');
                    }
                });
        });
}
