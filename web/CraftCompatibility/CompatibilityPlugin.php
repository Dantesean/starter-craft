<?php

/**
 * Copyright 2018 Includable
 * Created by Thomas Schoffelen
 */

namespace CraftCompatibility;

use Craft;
use craft\base\Plugin;
use craft\errors\MigrationException;
use craft\migrations\Install;
use craft\models\Site;
use Exception;
use yii\base\InvalidConfigException;

/**
 * Class CompatibilityPlugin
 *
 * @package CompatibilityPlugin
 */
class CompatibilityPlugin extends Plugin
{

    /**
     * Initializes the module.
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws MigrationException
     */
    public function init()
    {
        parent::init();

        // Override core classes
        Craft::$app->set('user', User::class);

        // Install Craft site
        if(!Craft::$app->getSites()->currentSite) {
            // Craft hasn't been installed yet
            $this->installSite();

            return;
        }

        // Auto login user
        $this->autoLogin();
    }

    /**
     * Install site migrations.
     *
     * @throws InvalidConfigException
     * @throws MigrationException
     * @throws Exception
     */
    protected function installSite()
    {
        $site = new Site([
            'name' => community()->title,
            'handle' => 'default',
            'hasUrls' => true,
            'baseUrl' => 'https://' . community()->get_domain(),
            'language' => 'en-US',
        ]);

        $migration = new Install([
            'username' => 'admin',
            'password' => uuid(),
            'email' => user()->email,
            'site' => $site,
        ]);

        // Run the install migration
        $migrator = Craft::$app->getMigrator();

        if($migrator->migrateUp($migration) === false) {
            throw new Exception('Could not install Craft site.');
        }

        // Mark all existing migrations as applied
        foreach($migrator->getNewMigrations() as $name) {
            $migrator->addMigrationHistory($name);
        }

        redirect('/admin');
    }

    /**
     * Auto login with an Includable user if required.
     */
    protected function autoLogin()
    {
        if(!Craft::$app->user->getIsGuest() || !user() || user()->id < 1) {
            return;
        }

        Craft::$app->user->loginByUserId(1);
    }

}
