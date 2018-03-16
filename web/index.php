<?php

/**
 * Copyright 2018 Includable
 * Created by Thomas Schoffelen
 */

/*
 * This is the root file for your web controller.
 *
 * Usually this file will handle most of the logic of the web interface, including loading
 * templates and displaying them to the user. In this case, it simply loads the Craft CMS Yii application.
 */

use Includable\CraftCompatibility\Loader;

// Check if Composer dependencies are already installed
if(!file_exists($this->module->path . 'vendor/autoload.php')) {
    echo 'Installing Composer dependencies... <meta http-equiv="refresh" content="5">';
    exit;
}

// Load Craft
Loader::loadInContainer($this);
