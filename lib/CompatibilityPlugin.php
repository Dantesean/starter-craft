<?php

/**
 * Copyright 2018 Includable
 * Created by Thomas Schoffelen
 */

namespace Includable\CraftCompatibility;

use Craft;
use craft\base\Plugin;
use craft\errors\MigrationException;
use craft\events\RegisterComponentTypesEvent;
use craft\migrations\Install;
use craft\models\Site;
use craft\services\Volumes;
use Exception;
use yii\base\Event;
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
     * @throws \Throwable
     */
    public function init()
    {
        parent::init();

        // Override core classes
        Craft::$app->set('user', User::class);

        // Install Craft site
        try {
            Craft::$app->getSites()->currentSite;
        } catch(\Exception $exception) {
            // Craft hasn't been installed yet
            self::installSite();

            return;
        }

        // Add Volume Type and Volume
        Event::on(Volumes::class, Volumes::EVENT_REGISTER_VOLUME_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types = array_filter($event->types, function($type) {
                return $type !== 'craft\\volumes\\Local';
            });
            de($event->types);
            $event->types[] = Volume::class;
        });

        $volumes = Craft::$app->getVolumes();
        if(empty($volumes->getAllVolumes())) {
            $volumes->saveVolume($volumes->createVolume([
                'name' => 'Uploads',
                'handle' => 'uploads',
                'type' => 'Includable\\CraftCompatibility\\Volume',
                'hasUrls' => false
            ]));
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
    public static function installSite()
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

        community()->attr('craft_installed', true);

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
