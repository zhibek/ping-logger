<?php

require('init.php');

$pingLogger = new PingLogger($config);
$pingLogger->renderStats((object)@$_REQUEST);
