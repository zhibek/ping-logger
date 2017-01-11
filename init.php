<?php

date_default_timezone_set('Africa/Cairo');

if (!is_file('config.php')) {
    exit('Config file "config.php" is required!' . PHP_EOL);
}

require('src/PingLogger.php');

require('config.php');

require('auth.php');
