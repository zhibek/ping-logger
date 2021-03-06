<?php

if ('cli' === PHP_SAPI) {
    return;
}

if (is_null($config->auth_user) || is_null($config->auth_pass)) {
    return;
}

if (@$_SERVER['PHP_AUTH_USER'] !== $config->auth_user || @$_SERVER['PHP_AUTH_PW'] !== $config->auth_pass) {
    header('WWW-Authenticate: Basic realm="Ping Logger"');
    header('HTTP/1.0 401 Unauthorized');
    exit;
}
