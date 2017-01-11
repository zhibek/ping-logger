<?php
$config = (object)array(

    // Label to record for "source" of ping data, e.g. "home"
    'source' => 'source',

    // Authentication (both for remote data & web UI)
    'auth_user' => 'auth_user',
    'auth_pass' => 'auth_pass',

    // Database details (if passing data to local db enabled)
    'db_enabled' => false,
    'db_host' => 'db_host',
    'db_name' => 'db_name',
    'db_user' => 'db_user',
    'db_pass' => 'db_pass',

    // Remote host details (if passing data to remote host enabled)
    'remote_enabled' => false,
    'remote_host' => 'http://example.org',

);
