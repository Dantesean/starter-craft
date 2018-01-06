<?php

/**
 * Copyright 2018 Includable
 * Created by Thomas Schoffelen
 */

/*
 * General Configuration.
 *
 * All of your system's general configuration settings go in here. You can see a
 * list of the available settings in vendor/craftcms/cms/src/config/GeneralConfig.php.
 */

return [
    // Default Week Start Day (0 = Sunday, 1 = Monday...)
    'defaultWeekStartDay' => 1,

    // Control Panel trigger word
    'cpTrigger' => 'admin',

    // Probably don't change this!
    'siteUrl' => 'https://' . community()->get_domain(),
    'resourceBasePath' => '/tmp/' . community()->get_domain(),
    'securityKey' => sha1(community()->get_domain()),
    'devMode' => gethostname() === 'sadev.io',
    'omitScriptNameInUrls' => true,
    'pageTrigger' => 'pg',
    'pathParam' => 'px'
];
