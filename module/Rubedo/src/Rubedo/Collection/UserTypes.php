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
namespace Rubedo\Collection;

use Rubedo\Interfaces\Collection\IUserTypes;
use Rubedo\Services\Events;
/**
 * Service to handle UserTypes
 *
 * @author aDobre
 * @category Rubedo
 * @package Rubedo
 */
class UserTypes extends AbstractLocalizableCollection implements IUserTypes
{

    public function __construct()
    {
        $this->_collectionName = 'UserTypes';
        parent::__construct();
    }
    
    
    public function destroy(array $obj, $options = array())
    {
        if ((isset($obj["UTType"]))&&(($obj["UTType"]=="default")||($obj["UTType"]=="email"))){
            $result= array(
                    'success' => false,
                    "msg" => 'Cannot destroy system user type'
                );
            return($result);
        }
        $result = $this->_dataService->destroy($obj, $options);
        $args = $result;
        $args['data'] = $obj;
        Events::getEventManager()->trigger(self::POST_DELETE_COLLECTION, $this, $args);
        return $result;
    }
    protected function localizeOutput($obj, $alternativeFallBack = null)
    {
        $obj = parent::localizeOutput($obj, $alternativeFallBack);
        if (static::$workingLocale === null) {
            if (!isset($obj['nativeLanguage'])) {
                return $obj;
            } else {
                $locale = $obj['nativeLanguage'];
            }
        } else {
            $locale = static::$workingLocale;
        }
        if (isset($obj['fields'])) {
            foreach ($obj['fields'] as &$field) {
                if (isset($field['config']['i18n'][$locale]['fieldLabel'])) {
                    $field['config']['fieldLabel'] = $field['config']['i18n'][$locale]['fieldLabel'];
                }
            }
        }
        return $obj;
    }
}
