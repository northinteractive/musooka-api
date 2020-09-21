<?php
header("Access-Control-Allow-Origin: *");

/*
error_reporting(E_ALL);
ini_set('display_errors', '1');
*/

if(isset($_GET['q'])) {
	$tmp = explode("/",$_GET['q']);
	define("API",$tmp[0]);
} else if(isset($_GET['api'])){
	define("API",$_GET['api']);
} else {
 	define("API","root");
}

// Set global for web root
define ('ROOT',$_SERVER['DOCUMENT_ROOT']."/");
define ('SALT',SALT);
define ('USERSALT',USERSALT);

// REST
require ROOT . '_resources/classes/class.rest.php';

// PDO
require ROOT . '_resources/classes/class.db.php';

// Load Redis Client
require ROOT . '_resources/predis/SharedConfigurations.php';
$redis = new Predis_Client($single_server);
$redisSearch = new Predis_Client($search_server);

// Application Extensions
require ROOT . '_resources/sdk-1.5.2/sdk.class.php';
require ROOT . 'application/utilities/s3json.php';
require ROOT . 'application/utilities/time.utility.php';
require ROOT . 'application/utilities/general.utility.php';
require ROOT . 'application/utilities/cache.utility.php';

// Start the Application
require ROOT . 'application/muzooka.php'; // Load the application

?>
