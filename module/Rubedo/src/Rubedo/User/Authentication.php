<?php
/**
 * Rubedo -- ECM solution
 * Copyright (c) 2013, WebTales (http://www.webtales.fr/).
 * All rights reserved.
 * licensing@webtales.fr
 *
 * Open Source License
 * ------------------------------------------------------------------------------------------
 * Rubedo is licensed under the terms of the Open Source GPL 3.0 license. 
 *
 * @category   Rubedo
 * @package    Rubedo
 * @copyright  Copyright (c) 2012-2013 WebTales (http://www.webtales.fr)
 * @license    http://www.gnu.org/licenses/gpl.html Open Source GPL 3.0 license
 */
namespace Rubedo\User;

use Rubedo\Interfaces\User\IAuthentication;
use Zend\Authentication\AuthenticationService;
use Rubedo\User\AuthAdapter;
use Rubedo\Services\Manager;
use Rubedo\Services\Events;

/**
 * Current Authentication Service
 *
 * Authenticate user and get information about him
 *
 * @author jbourdin
 * @category Rubedo
 * @package Rubedo
 */
class Authentication extends \Zend\Authentication\AuthenticationService
{

    const SUCCESS = 'rubedo_authentication_success';

    const FAIL = 'rubedo_authentication_fail';

    /**
     * Authentication service of ZF
     *
     * @param
     *            AuthenticationService
     *            
     */
    protected static $zendAuth;

    protected static $_authLifetime = 60;

    /**
     * Return the Zend_Auth object and instanciate it if it's necessary
     *
     * @return AuthenticationService
     */
    protected function getZendAuth()
    {
        if (! isset(static::$zendAuth)) {
            static::$zendAuth = new AuthenticationService();
        }
        
        return static::$zendAuth;
    }

    /**
     * Return the identity of the current user in session
     *
     * @return array
     */
    public function getIdentity()
    {
        $config = Manager::getService('Application')->getConfig();
        $cookieName = $config['session']['name'];
        if (isset($_COOKIE[$cookieName])) {
            return $this->getZendAuth()->getIdentity();
        } else {
            return null;
        }
        
    }

    /**
     * Return true if there is a user connected
     *
     * @return bool
     */
    public function hasIdentity()
    {
        $config = Manager::getService('Application')->getConfig();
        $cookieName = $config['session']['name'];
        if (isset($_COOKIE[$cookieName])) {
            return $this->getZendAuth()->hasIdentity();
        } else {
            return false;
        }
    }

    /**
     * Unset the session of the current user
     *
     * @return bool
     */
    public function clearIdentity()
    {
        $this->getZendAuth()->clearIdentity();
        Manager::getService('Session')->getSessionObject()
            ->getManager()
            ->getStorage()
            ->clear();
        $config = Manager::getService('Application')->getConfig();
        $cookieName = $config['session']['name'];
        setcookie($cookieName, null, -1, '/');
    }

    /**
     * Ask a reauthentification without changing the session
     *
     * @param $login It's
     *            the login of the user
     * @param $password It's
     *            the password of the user
     *            
     * @return bool
     */
    public function forceReAuth($login, $password)
    {
        $authAdapter = new AuthAdapter($login, $password);
        $result = $authAdapter->authenticate($authAdapter);
        return $result->isValid();
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Rubedo\Interfaces\User\IAuthentication::resetExpirationTime()
     */
    public function resetExpirationTime()
    {}

    /**
     * (non-PHPdoc)
     *
     * @see \Rubedo\Interfaces\User\IAuthentication::getExpirationTime()
     */
    public function getExpirationTime()
    {}

    /**
     *
     * @return the $_authLifetime
     */
    public static function getAuthLifetime()
    {
        return Authentication::$_authLifetime;
    }

    /**
     *
     * @param number $_authLifetime            
     */
    public static function setAuthLifetime($_authLifetime)
    {
        Authentication::$_authLifetime = $_authLifetime;
    }
}
