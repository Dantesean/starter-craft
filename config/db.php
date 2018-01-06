<?php

/**
 * Copyright 2018 Includable
 * Created by Thomas Schoffelen
 */

/*
 * Database configuration.
 */

$db = community()->getDatabaseCredentials();

return [
    'driver' => 'mysql',
    'tablePrefix' => 'craft_',
    'server' => $db['hostname'],
    'user' => $db['username'],
    'password' => $db['password'],
    'database' => $db['database']
];
