<?php

require('init.php');

$pingLogger = new PingLogger($config);
$status = $pingLogger->checkStatus(@$_REQUEST['source']);

if ($status) {
    print('OK');
} else {
    header('HTTP/1.0 417 Expectation Failed');
    exit('ERROR');
}
