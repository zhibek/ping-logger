<?php

require('init.php');

$pingLogger = new PingLogger($config);
$pingLogger->checkStatus((object)@$_REQUEST);
