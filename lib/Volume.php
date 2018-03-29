<?php

/**
 * Copyright 2018 Includable
 * Created by Thomas Schoffelen
 */

namespace Includable\CraftCompatibility;

use craft\base\FlysystemVolume;
use Sandbox\Storage;

/**
 * Class Volume
 *
 * @package Includable\CraftCompatibility
 */
class Volume extends FlysystemVolume
{

    /**
     * @var bool Whether this is a local source or not. Defaults to false.
     */
    protected $isVolumeLocal = false;

    /**
     * @var string Subfolder to use
     */
    public $subfolder = '';

    /**
     * @var string AWS key ID
     */

    public $keyId = '';

    /**
     * @var string AWS key secret
     */
    public $secret = '';

    /**
     * @var string Bucket to use
     */
    public $bucket = '';

    /**
     * @var string Region to use
     */
    public $region = '';

    /**
     * @var string Cache expiration period.
     */
    public $expires = '';

    /**
     * Returns the display name of this class.
     *
     * @return string The display name of this class.
     */
    public static function displayName(): string
    {
        return 'Includable';
    }

    /**
     * Creates and returns a Flysystem adapter instance based on the stored settings.
     *
     * @return \League\Flysystem\AdapterInterface The Flysystem adapter.
     */
    protected function createAdapter()
    {
        return new IncludableAdapter(Storage::instance(), $this->subfolder);
    }
}
