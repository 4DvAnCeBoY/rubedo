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
namespace Rubedo\Blocks\Controller;

use Rubedo\Services\Manager;

/**
 *
 * @author dfanchon
 * @category Rubedo
 * @package Rubedo
 */
class ImageController extends AbstractController
{

    public function indexAction ()
    {
        $blockConfig = $this->params()->fromQuery('block-config', array());
        
        $output = $this->params()->fromQuery();
        $output['mode'] = isset($blockConfig['mode']) ? $blockConfig['mode'] : 'morph';
        $output['imageLink'] = isset($blockConfig['imageLink']) ? $blockConfig['imageLink'] : null;
        $output['externalURL'] = isset($blockConfig['externalURL']) ? $blockConfig['externalURL'] : null;
        $output['imageAlt'] = isset($blockConfig['imageAlt']) ? $blockConfig['imageAlt'] : null;
        $output['imageFile'] = isset($blockConfig['imageFile']) ? $blockConfig['imageFile'] : null;
        $output['imageWidth'] = isset($blockConfig['imageWidth']) ? $blockConfig['imageWidth'] : null;
        $output['imageHeight'] = isset($blockConfig['imageHeight']) ? $blockConfig['imageHeight'] : null;
        
        $template = Manager::getService('FrontOfficeTemplates')->getFileThemePath("blocks/image.html.twig");
        
        $css = array();
        $js = array();
        return $this->_sendResponse($output, $template, $css, $js);
    }
}
