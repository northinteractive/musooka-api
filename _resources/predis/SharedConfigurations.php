<?php
require_once 'lib/Predis.php';

$single_server = array(
    'host'     => '10.214.10.162',
    'port'     => 6379,
);

$search_server = array(
	'host'   => '10.208.247.130',
          'port'   => 6379,
);

$multiple_servers = array(
    array(
       'host'     => '127.0.0.1',
       'port'     => 6379,
       'database' => 15,
       'alias'    => 'first',
    ),
    array(
       'host'     => '127.0.0.1',
       'port'     => 6380,
       'database' => 15,
       'alias'    => 'second',
    ),
);
?>
