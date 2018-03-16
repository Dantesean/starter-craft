<?php

/**
 * Copyright 2018 Includable
 * Created by Thomas Schoffelen
 */

/*
 * Load the Includable compatibility plugin.
 */

return [
    'modules' => [
        'compatibility' => Includable\CraftCompatibility\CompatibilityPlugin::class
    ],
    'bootstrap' => ['compatibility']
];
