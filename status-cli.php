<?php

require('init.php');

if (2 !== $argc) {
    exit('A "source" argument is required to run this script: php status-cli.php office' . PHP_EOL);
}

$source = $argv[1];

$pingLogger = new PingLogger($config);
$status = $pingLogger->checkStatus($source);

if (!$status) {
    fwrite(STDERR, 'ERROR');
    exit(1);
}
