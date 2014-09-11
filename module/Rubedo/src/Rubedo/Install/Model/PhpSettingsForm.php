<?php
/**
 * Rubedo -- ECM solution
 * Copyright (c) 2014, WebTales (http://www.webtales.fr/).
 * All rights reserved.
 * licensing@webtales.fr
 *
 * Open Source License
 * ------------------------------------------------------------------------------------------
 * Rubedo is licensed under the terms of the Open Source GPL 3.0 license.
 *
 * @category   Rubedo
 * @package    Rubedo
 * @copyright  Copyright (c) 2012-2014 WebTales (http://www.webtales.fr)
 * @license    http://www.gnu.org/licenses/gpl.html Open Source GPL 3.0 license
 */
namespace Rubedo\Install\Model;

use Monolog\Logger;
use Zend\Form\Element\Checkbox;
use Zend\Form\Element\Select;
use Zend\Form\Element\Text;
use Zend\Form\Fieldset;
use Zend\Form\Form;

/**
 * Form for DB Config
 *
 * @author jbourdin
 * @category Rubedo
 * @package Rubedo
 */
class PhpSettingsForm extends BootstrapForm
{

    public static function getForm($params)
    {
        $displayExceptions = new Checkbox('display_exceptions');
        $displayExceptions->setValue(isset($params['view_manager']['display_exceptions']) ? $params['view_manager']['display_exceptions'] : false);
        $displayExceptions->setLabel('Display application exceptions');

        $displayExceptionsFieldSet = new Fieldset('view_manager');
        $displayExceptionsFieldSet->add($displayExceptions);
        $displayExceptionsFieldSet->setAttribute('legend', 'Exception screen');

        // enableHandler
        $loggerFieldSet = new Fieldset('logger');
        $enableLoggerFieldSet = new Fieldset('enableHandler');
        $enableLoggerFieldSet->setAttribute('legend', 'Error log Handler');
        $handlerCheckbox = new Checkbox('ChromePHPHandler');
        $handlerCheckbox->setLabel('ChromePHPHandler');
        $handlerCheckbox->setValue($params['logger']['enableHandler']['ChromePHPHandler']);
        $enableLoggerFieldSet->add($handlerCheckbox);

        $handlerCheckbox = new Checkbox('FirePHPHandler');
        $handlerCheckbox->setLabel('FirePHPHandler');
        $handlerCheckbox->setValue($params['logger']['enableHandler']['FirePHPHandler']);
        $enableLoggerFieldSet->add($handlerCheckbox);

        $handlerCheckbox = new Checkbox('MongoDBHandler');
        $handlerCheckbox->setLabel('MongoDBHandler');
        $handlerCheckbox->setValue($params['logger']['enableHandler']['MongoDBHandler']);
        $enableLoggerFieldSet->add($handlerCheckbox);

        $handlerCheckbox = new Checkbox('StreamHandler');
        $handlerCheckbox->setLabel('Files');
        $handlerCheckbox->setValue($params['logger']['enableHandler']['StreamHandler']);
        $enableLoggerFieldSet->add($handlerCheckbox);

        $loggerFieldSet->add($enableLoggerFieldSet);

        $levels = array_flip(Logger::getLevels());

        $levelSelect = new Select('errorLevel');
        $levelSelect->setLabel('Reporting Level');
        $levelSelect->setValue($params['logger']['errorLevel']);
        $levelSelect->setOptions(array(
            'value_options' => $levels
        ));

        $loggerFieldSet->add($levelSelect);

        $rubedoConfigFieldset = new Fieldset('rubedo_config');
        $rubedoConfigFieldset->setAttribute('legend', 'Specific Rubedo options');

        $minify = new Checkbox('minify');
        $minify->setValue(isset($params['rubedo_config']['minify']) ? $params['rubedo_config']['minify'] : 0);
        $minify->setLabel('Minify CSS & Js');

        $cachePage = new Checkbox('cachePage');
        $cachePage->setValue(isset($params['rubedo_config']['cachePage']) ? $params['rubedo_config']['cachePage'] : 1);
        $cachePage->setLabel('Cache page');

        $extDebug = new Checkbox('extDebug');
        $extDebug->setValue(isset($params['rubedo_config']['extDebug']) ? $params['rubedo_config']['extDebug'] : null);
        $extDebug->setLabel('Use debug mode of ExtJs');

        $eCommerce = new Checkbox('addECommerce');
        $eCommerce->setValue(isset($params['rubedo_config']['addECommerce']) ? $params['rubedo_config']['addECommerce'] : 1);
        $eCommerce->setLabel('Activate e-commerce features');

        $magicActivator = new Checkbox('activateMagic');
        $magicActivator->setValue(isset($params['rubedo_config']['activateMagic']) ? $params['rubedo_config']['activateMagic'] : 0);
        $magicActivator->setLabel('Activate Magic Queries');

        $sessionFieldset = new Fieldset('session');
        $sessionFieldset->setAttribute('legend', 'Session parameters');
        $sessionName = new Text('name');
        $sessionName->setAttribute('Required', true);
        $sessionName->setValue(isset($params['session']['name']) ? $params['session']['name'] : 'rubedo');
        $sessionName->setLabel('Name of the session cookie');
        $sessionFieldset->add($sessionName);

        $authLifetime = new Text('authLifetime');
        $authLifetime->setAttribute('Required', true);
        $authLifetime->setValue(isset($params['session']['remember_me_seconds']) ? $params['session']['remember_me_seconds'] : '3600');
        $authLifetime->setLabel('Session lifetime');
        $sessionFieldset->add($authLifetime);

        $defaultBackofficeHost = new Text('defaultBackofficeHost');
        $defaultBackofficeHost->setAttribute('Required', true);
        $defaultBackofficeHost->setValue(isset($params['rubedo_config']['defaultBackofficeHost']) ? $params['rubedo_config']['defaultBackofficeHost'] : $_SERVER['HTTP_HOST']);
        $defaultBackofficeHost->setLabel('Default backoffice domain');

        $isBackofficeSSL = new Checkbox('isBackofficeSSL');
        $isBackofficeSSL->setValue(isset($params['rubedo_config']['isBackofficeSSL']) ? $params['rubedo_config']['isBackofficeSSL'] : isset($_SERVER['HTTPS']));
        $isBackofficeSSL->setLabel('Use SSL for BackOffice');

        $enableEmailNotification = new Checkbox('enableEmailNotification');
        $enableEmailNotification->setValue(isset($params['rubedo_config']['enableEmailNotification']) ? $params['rubedo_config']['enableEmailNotification'] : false);
        $enableEmailNotification->setLabel('Enable email notifications');

        $fromEmailNotification = new Text('fromEmailNotification');
        $fromEmailNotification->setValue(isset($params['rubedo_config']['fromEmailNotification']) ? $params['rubedo_config']['fromEmailNotification'] : null);
        $fromEmailNotification->setLabel('Sender of notifications');

        $recaptchaPublicKey = new Text('public_key');
        $recaptchaPublicKey->setValue(isset($params['rubedo_config']['recaptcha']['public_key']) ? $params['rubedo_config']['recaptcha']['public_key'] : null);
        $recaptchaPublicKey->setLabel('Public key');

        $recaptchaPrivateKey = new Text('private_key');
        $recaptchaPrivateKey->setValue(isset($params['rubedo_config']['recaptcha']['private_key']) ? $params['rubedo_config']['recaptcha']['private_key'] : null);
        $recaptchaPrivateKey->setLabel('Private key');

        $recaptchaFieldSet = new Fieldset('recaptcha');
        $recaptchaFieldSet->add($recaptchaPublicKey);
        $recaptchaFieldSet->add($recaptchaPrivateKey);
        $recaptchaFieldSet->setAttribute('legend', 'Recaptcha parameters');


        $dbForm = new Form();
        $dbForm->add($displayExceptionsFieldSet);
        $dbForm->add($loggerFieldSet);
        $rubedoConfigFieldset->add($minify);
        $rubedoConfigFieldset->add($cachePage);
        $rubedoConfigFieldset->add($extDebug);
        $rubedoConfigFieldset->add($eCommerce);
        $rubedoConfigFieldset->add($magicActivator);
        $rubedoConfigFieldset->add($defaultBackofficeHost);
        $rubedoConfigFieldset->add($isBackofficeSSL);
        $rubedoConfigFieldset->add($enableEmailNotification);
        $rubedoConfigFieldset->add($fromEmailNotification);
        $rubedoConfigFieldset->add($recaptchaFieldSet);
        $dbForm->add($sessionFieldset);
        $dbForm->add($rubedoConfigFieldset);


        $dbForm = self::setForm($dbForm);

        return $dbForm;
    }
}

