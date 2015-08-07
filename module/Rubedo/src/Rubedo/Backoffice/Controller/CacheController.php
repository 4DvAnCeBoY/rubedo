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
namespace Rubedo\Backoffice\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Rubedo\Services\Manager;
use Rubedo\Services\Cache;
use Zend\View\Model\JsonModel;
use Rubedo\Update\Install;

/**
 * Controller providing control over the cached contents
 *
 * @author jbourdin
 * @category Rubedo
 * @package Rubedo
 *
 */
class CacheController extends AbstractActionController
{

    /**
     * cache object
     *
     * @var Zend_Cache
     */
    protected $_cache;

    /**
     * The default read Action
     *
     * Return the content of the collection, get filters from the request
     * params, get sort from request params
     */
    public function indexAction()
    {
        $countArray = array();
        $countArray['cachedItems'] = Manager::getService('Cache')->count();
        $countArray['cachedUrl'] = Manager::getService('UrlCache')->count();
        $countArray['apiCache'] = Manager::getService('ApiCache')->count();
        return new JsonModel($countArray);
    }

    public function clearAction()
    {
        $installObject = new Install();
        $installObject->clearConfigCache();
        $installObject->clearFileCaches();
        $countArray = array();
        $countArray['Cached items'] = Cache::getCache()->clean();
        if (Manager::getService('UrlCache')->count() > 0) {
            $countArray['Cached Url'] = Manager::getService('UrlCache')->drop();
            Manager::getService('UrlCache')->ensureIndexes();
        } else {
            $countArray['Cached Url'] = true;
        }
        Manager::getService('ApiCache')->drop();
        Manager::getService('ApiCache')->ensureIndexes();
        return new JsonModel($countArray);
    }

    public function clearConfigAction()
    {
        $installObject = new Install();
        $installObject->clearConfigCache();
        return new JsonModel(array("success"=>true));
    }

    public function clearFilesAction()
    {
        $installObject = new Install();
        $installObject->clearFileCaches();
        return new JsonModel(array("success"=>true));
    }

    public function clearApiAction(){
        Manager::getService('ApiCache')->drop();
        Manager::getService('ApiCache')->ensureIndexes();
        return new JsonModel(array("success"=>true));
    }

    public function clearUrlAction(){
        Manager::getService('UrlCache')->drop();
        Manager::getService('UrlCache')->ensureIndexes();
        return new JsonModel(array("success"=>true));
    }

    public function clearObjectsAction(){
        Cache::getCache()->clean();
        return new JsonModel(array("success"=>true));
    }


}
