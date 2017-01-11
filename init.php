<?php

date_default_timezone_set('Africa/Cairo');

require('src/PingLogger.php');

require('config.default.php');

$config = array();
@include('config.php');
$config = (object) array_merge($configDefault, @$config);

require('auth.php');
