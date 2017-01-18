<?php

require('init.php');

$pingLogger = new PingLogger($config);
$pingLogger->receiveRemoteData((object)@$_REQUEST);
