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

Loader::loadInContainer($this);
