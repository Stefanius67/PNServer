<?php
require_once 'lib/PNServer/PNDataProviderSQLite.php';

use lib\PNServer\PNDataProviderSQLite;

$result = array();
// only serve POST request containing valid json data
if (strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
	if (isset($_SERVER['CONTENT_TYPE'])	&& trim(strtolower($_SERVER['CONTENT_TYPE']) == 'application/json')) {
		// get posted json data
		if (($strJSON = trim(file_get_contents('php://input'))) === false) {
			$result['msg'] = 'invalid JSON data!';
		} else {
			$oDP = new PNDataProviderSQLite();
			if ($oDP->saveSubscription($strJSON) !== false) {
				$result['msg'] = 'subscription saved on server!';
			} else {
				$result['msg'] = 'error saving subscription!';
			}
		}
	} else {
		$result['msg'] = 'invalid content type!';
	}
} else {
	$result['msg'] = 'no post request!';
}
// let the service-worker know the result
echo json_encode($result);
