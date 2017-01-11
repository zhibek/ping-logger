<?php

require('init.php');

if (2 !== $argc) {
    exit('A "host" argument is required to run this script: php exec.php 8.8.8.8' . PHP_EOL);
}

$host = $argv[1];

$pingLogger = new PingLogger($config);
$pingLogger->logPing($host);
