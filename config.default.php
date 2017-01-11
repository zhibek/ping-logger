<?php
$configDefault = array(

    // Label to record for "source" of ping data, e.g. "home"
    'source' => 'default-source',

    // Number of times to ping per test
    'ping_count' => 60,

    // Authentication (both for remote data & web UI)
    'auth_user' => null,
    'auth_pass' => null,

    // Database details (if passing data to local db enabled)
    'db_enabled' => false,
    'db_host' => null,
    'db_name' => null,
    'db_user' => null,
    'db_pass' => null,

    // Remote host details (if passing data to remote host enabled)
    'remote_enabled' => false,
    'remote_host' => null,

);
