<?php

/**
 * Copyright 2018 Includable
 * Created by Thomas Schoffelen
 */

namespace Includable\CraftCompatibility;

use craft\elements\User as UserElement;
use craft\web\User as UserWeb;

/**
 * Class User
 *
 * @package CraftCompatibility
 */
class User extends UserWeb
{

    /**
     * Init element
     */
    public function init()
    {
        $this->identityClass = IncludableIdentity::class;
        $this->enableSession = false;

        parent::init();
    }

    /**
     * @return mixed
     */
    public function getIsGuest()
    {
        return parent::getIsGuest();
    }

    /**
     * Login by cookie
     */
    protected function loginByCookie()
    {
        // Do nothing
    }

    /**
     * Remove identity cookie
     */
    protected function removeIdentityCookie()
    {
        // Do nothing
    }

    /**
     * @return array|null
     */
    protected function getIdentityAndDurationFromCookie()
    {
        $cookie = user()->attr('craft3_identity');
        if(!$cookie) {
            return null;
        }

        return ['identity' => new IncludableIdentity($cookie[0]), 'duration' => $cookie[2]];
    }

    /**
     * Renew identity cookie
     */
    protected function renewIdentityCookie()
    {
        // Do nothing
    }

    /**
     * @param $identity
     * @param $duration
     */
    protected function sendIdentityCookie($identity, $duration)
    {
        user()->attr('craft3_identity', [
            $identity->getId(),
            $identity->getAuthKey(),
            $duration
        ]);
    }

    /**
     * @param UserElement $user
     */
    public function sendUsernameCookie(UserElement $user)
    {
        // Do nothing
    }

}
