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
namespace Rubedo\Frontoffice\Controller;

use Rubedo\Services\Manager;
use Rubedo\Collection\AbstractCollection;
use Rubedo\Content\Context;
use Rubedo\Templates\Twig\TwigViewModel;
use Zend\Debug\Debug;
use Zend\Mvc\Controller\AbstractActionController;

/**
 * Front Office Defautl Controller
 *
 * Invoked when calling front office URL
 *
 * @author jbourdin
 * @category Rubedo
 * @package Rubedo
 */
class IndexController extends AbstractActionController
{

    /**
     * Current front office page parameters
     *
     * @var array
     */
    protected $_pageParams = array();

    /**
     * URL service
     *
     * @var \Rubedo\Interfaces\Router\IUrl
     */
    protected $_serviceUrl;

    /**
     * page info service
     *
     * @var \Rubedo\Interfaces\Content\IPage
     */
    protected $_servicePage;

    /**
     * FO Templates service
     *
     * @var \Rubedo\Interfaces\Templates\IFrontOfficeTemplates
     */
    protected $_serviceTemplate;

    /**
     * Block service
     *
     * @var \Rubedo\Interfaces\Content\IBlock
     */
    protected $_serviceBlock;

    /**
     * ID of the current page
     *
     * @var string
     */
    protected $_pageId;

    /**
     * current page data
     *
     * @var array
     */
    protected $_pageInfos;

    /**
     * current mask object
     *
     * @var array
     */
    protected $_mask;

    /**
     * array of parent IDs
     *
     * @var array
     */
    protected $_rootlineArray;

    /**
     * ID of the column to display main content instead of page content if
     * content-id given
     *
     * @var string
     */
    protected $_mainCol = null;

    /**
     * Main Action : render the Front Office view
     */
    public function indexAction()
    {
        if ($this->params()->fromQuery('tk', null)) {
            $redirectParams = array(
                'action' => 'index',
                'controller' => 'tiny'
            );
            $options = array('query'=>$this->params()->fromQuery());
            return $this->redirect()->toRoute('frontoffice/default', $redirectParams,$options);
        }
        
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'];
        $httpProtocol = $isHttps ? 'HTTPS' : 'HTTP';
        
        // init service variables
        $this->_serviceUrl = Manager::getService('Url');
        $this->_servicePage = Manager::getService('PageContent');
        
        $this->_pageId = $this->params()->fromRoute('pageId');
        $this->_servicePage->setCurrentPage($this->_pageId);
        
        if (! $this->_pageId) {
            throw new \Rubedo\Exceptions\NotFound('No Page found', "Exception2");
        }
        $this->_pageInfo = Manager::getService('Pages')->findById($this->_pageId);
        if ($this->_pageInfo===NULL){
            $wasFiltered1 = AbstractCollection::disableUserFilter();
            $attemptedPage = Manager::getService('Pages')->findById($this->_pageId);
            $site = Manager::getService('Sites')->findById($attemptedPage['site']);
            $homePageId=$site['homePage'];
            AbstractCollection::disableUserFilter($wasFiltered1);
            
            if ($this->_pageId==$homePageId){
                throw new \Rubedo\Exceptions\Server('You do not have access to the current site');
            }
            $uri = $this->getRequest()->getUri();
            $domain = $uri->getHost();
            return $this->redirect()->toUrl(strtolower(array_pop($site['protocol'])) . '://' . $domain );
        }
        
        $wasFiltered1 = AbstractCollection::disableUserFilter();
        $this->_site = Manager::getService('Sites')->findById($this->_pageInfo['site']);
        AbstractCollection::disableUserFilter($wasFiltered1);
        
        // ensure protocol is authorized for this site
        if (! is_array($this->_site['protocol']) || count($this->_site['protocol']) == 0) {
            throw new \Rubedo\Exceptions\Server('Protocol is not set for current site', "Exception14");
        }
        
        $uri = $this->getRequest()->getUri();
        $domain = $uri->getHost();
        
        /**
         *
         * @todo rewrite this in ZF2 way
         */
        if (! in_array($httpProtocol, $this->_site['protocol'])) {
            return $this->redirect()->toUrl(strtolower(array_pop($this->_site['protocol'])) . '://' . $domain . $uri->getPath() . '?' . $uri->getQuery());
        }
        
        AbstractCollection::setIsFrontEnd(true);
        
        // context
        $cookieValue = $this->getRequest()->getCookie('locale');
        if(!isset($cookieValue['locale'])){
            $cookieValue = null;
        }else{
            $cookieValue = $cookieValue['locale'];
        }
        $lang = Manager::getService('CurrentLocalization')->resolveLocalization($this->_site['id'], $this->params('locale'), $cookieValue);
        if ($lang && ! $this->params('locale')) {
            return $this->redirect()->toUrl(strtolower(array_pop($this->_site['protocol'])) . '://' . $domain . '/' . $lang . $uri->getPath() . '?' . $uri->getQuery());
        }
        
        if ($domain && !$this->_site['useBrowserLanguage']) {
            $languageCookie = setcookie('locale', $lang, strtotime('+1 year'), '/', $domain);
        }
        
        // reload page in localization context
        $this->_pageInfo = Manager::getService('Pages')->findById($this->_pageId, true);
        if (! $this->_pageInfo) {
            throw new \Rubedo\Exceptions\NotFound('Page not found in this language', 'Exception101');
        }
        $this->_site = Manager::getService('Sites')->findById($this->_pageInfo['site']);
        $this->_servicePage->setCurrentSite($this->_pageInfo['site']);
        
        $isLoggedIn = Manager::getService('CurrentUser')->isAuthenticated();
        $hasAccessToBO = Manager::getService('Acl')->hasAccess('ui.backoffice');
        if (! $isLoggedIn || ! $hasAccessToBO) {
            $isPreview = false;
        } else {
            $isPreview = $this->params()->fromQuery('preview', false);
        }
        if ($isPreview) {
            Manager::getService('Url')->disableNavigation();
            $simulatedTime = $this->params()->fromQuery('preview_date', null);
            if (isset($simulatedTime)) {
                Manager::getService('CurrentTime')->setSimulatedTime($simulatedTime);
            }
            $isDraft = $this->params()->fromQuery('preview_draft', null);
            if (isset($isDraft) && $isDraft === "true") {
                Context::setIsDraft(true);
            }
        }
        
        // template service
        $this->_serviceTemplate = Manager::getService('FrontOfficeTemplates');
        
        
        
        // build contents tree
        $this->_pageParams = $this->_getPageInfo($this->_pageId);
        
        
        // Load the CSS files
        $this->_servicePage->appendCss($this->getRequest()
            ->getBasePath() . '/' . $this->_serviceTemplate->getFileThemePath('css/rubedo.css'));
        
        $canEdit = ! $isPreview && $isLoggedIn && Manager::getService('Acl')->hasAccess('write.frontoffice.contents');
        
        // load the javaScripts files
        $this->_servicePage->appendJs('/components/jquery/jqueryui/ui/minified/jquery-ui.min.js');
        if ($canEdit) {
            $this->_servicePage->appendJs('/components/webtales/ckeditor/ckeditor.js');
            $this->_servicePage->appendJs($this->getRequest()
                ->getBasePath() . '/' . $this->_serviceTemplate->getFileThemePath('js/rubedo-edit.js'));
            $this->_servicePage->appendJs($this->getRequest()
                ->getBasePath() . '/' . $this->_serviceTemplate->getFileThemePath('js/authentication.js'));

            if (Manager::getService('CurrentUser')->getLanguage() == 'en') {
                $datepickerJs = 'jquery.ui.datepicker-en-GB.js';
            } else {
                $datepickerJs = 'jquery.ui.datepicker-' . Manager::getService('CurrentUser')->getLanguage() . '.js';
            }

            $js = array(
                '/components/jquery/jqueryui/ui/i18n/' . $datepickerJs,
                '/components/jquery/timepicker/jquery.ui.timepicker.js'
            );
            if (is_array($js)) {
                foreach ($js as $value) {
                    $this->_servicePage->appendJs($value);
                }
            }
        }

        
        
        // Build Twig context
        $twigVar = $this->_pageParams;
        
        // change title & description if displaying a single content as main
        // content
        $directContentId = $this->params()->fromRoute('content-id', false);
        if ($directContentId) {
            
            $singleContent = Manager::getService('Contents')->findById($directContentId, Context::isLive(), false);
            if ($singleContent) {
                $twigVar['contentId'] = $directContentId;
                $this->_servicePage->setPageTitle($singleContent['fields']['text']);
                if (isset($singleContent['fields']['summary'])) {
                	$this->_servicePage->setDescription($singleContent['fields']['summary']);
                } else {
                	$this->_servicePage->setDescription("");
                }
            }
        }
        $rConfig=Manager::getService("config");
        if ((isset($rConfig["rubedo_config"]["activateMagic"]))&&($rConfig["rubedo_config"]["activateMagic"]=="1")){
            $fingerprint=Manager::getService("Session")->get("fingerprint");
            if (!$fingerprint){
                $twigVar["fingerprintNeeded"]=true;
            }
        }
        $twigVar['currentPage'] = $this->_pageId;
        $twigVar['currentWorkspace'] = $this->_pageInfo['workspace'];
        $twigVar['isDraft'] = Context::isDraft();
        $twigVar["baseUrl"] = $this->getRequest()->getBasePath();
        $twigVar['theme'] = $this->_serviceTemplate->getCurrentTheme();
        if ($twigVar['theme'] == "customtheme") {
            $twigVar['customThemeId'] = $this->_serviceTemplate->getCustomThemeId();
            $twigVar['customThemeVersion'] = $this->_serviceTemplate->getCustomThemeVersion();
        }
        $twigVar['lang'] = $lang;
        $twigVar['siteID'] = $this->_pageInfo['site'];
        $twigVar['prefixTitle'] = isset($this->_site['title']) && ! empty($this->_site['title']) ? $this->_site['title'] . ' - ' : '';
        $twigVar['title'] = $this->_servicePage->getPageTitle();
        $metaRobot = array();
        if(isset($this->_pageInfo['noIndex']) && $this->_pageInfo['noIndex']){
            $metaRobot[] = 'noindex';
        }
        if(isset($this->_pageInfo['noFollow']) && $this->_pageInfo['noFollow']){
            $metaRobot[] = 'nofollow';
        }
        $twigVar['metaRobot'] = implode(',',$metaRobot);
        
        // set metadata
        $description = $this->_servicePage->getDescription();
        if (empty($description)) {
            $description = isset($this->_site['description']) ? $this->_site['description'] : '';
        }
        $twigVar['description'] = $this->_servicePage->getDescription();
        
        $author = isset($this->_site['author']) ? $this->_site['author'] : '';
        if (empty($author)) {
            $author = $this->_servicePage->getAuthor();
        }
        $twigVar['author'] = $author;
        
        $keywords = $this->_servicePage->getKeywords();
        if (count($keywords) === 0) {
            $keywords = (isset($this->_site['keywords']) && is_array($this->_site['keywords'])) ? $this->_site['keywords'] : array();
        }
        if (is_array($keywords)) {
            $twigVar['keywords'] = implode(',', $keywords);
        }
        
        if (isset($this->_site['googleAnalyticsKey'])) {
            $twigVar['googleAnalyticsKey'] = $this->_site['googleAnalyticsKey'];
        }
        
        if (isset($this->_site['googleMapsKey'])) {
            $twigVar['googleMapsKey'] = $this->_site['googleMapsKey'];
        }
        
        if (isset($this->_site['disqusKey'])) {
            $twigVar['disqusKey'] = $this->_site['disqusKey'];
        }
        
        // Return current user
        $currentUser = Manager::getService('CurrentUser')->getCurrentUser();
        $twigVar['currentUser'] = $currentUser;
        
        $twigVar['css'] = $this->_servicePage->getCss();
        $twigVar['js'] = $this->_servicePage->getJs();
        $twigVar['isLoggedIn'] = $isLoggedIn;
        $twigVar['hasAccessToBO'] = $hasAccessToBO;
        $twigVar['canEdit'] = $canEdit;
        $twigVar['boLocale'] = Manager::getService('CurrentUser')->getLanguage();
        
        $twigVar['pageProperties'] = isset($this->_mask['pageProperties']) ? $this->_mask['pageProperties'] : null;
        
        $pageTemplate = $this->_serviceTemplate->getFileThemePath($this->_pageParams['template']);
        
        $viewModel = new TwigViewModel($twigVar);
        $viewModel->setTemplate($pageTemplate);
        $viewModel->setTerminal(true);
        
        return $viewModel;
    }

    /**
     * Return page infos based on its ID
     *
     * @param string|int $pageId
     *            requested URL
     * @return array
     */
    protected function _getPageInfo($pageId)
    {
        $wasFiltered = AbstractCollection::disableUserFilter();
        $this->_mask = Manager::getService('Masks')->findById($this->_pageInfo['maskId']); // maskId
        AbstractCollection::disableUserFilter($wasFiltered);
        
        if (! $this->_mask) {
            throw new \Rubedo\Exceptions\Server('No mask found.', "Exception24");
        }
        
        $this->_currentContent = $this->params()->fromRoute('content-id', null);
        
        // @todo get main column
        if ($this->_currentContent) {
            $this->_mainCol = $this->_getMainColumn();
        }
        
        $this->_blocksArray = array();
        foreach ($this->_mask['blocks'] as $block) {
            if (! isset($block['orderValue'])) {
                throw new \Rubedo\Exceptions\Server('Missing orderValue for block %1$s', "Exception25", $block['id']);
            }
            $this->_blocksArray[$block['parentCol']][] = $block;
        }
        foreach ($this->_pageInfo['blocks'] as $block) {
            if (! isset($block['orderValue'])) {
                throw new \Rubedo\Exceptions\Server('Missing orderValue for block %1$s', "Exception25", $block['id']);
            }
            $this->_blocksArray[$block['parentCol']][] = $block;
        }
        if ($this->_mainCol) {
            unset($this->_blocksArray[$this->_mainCol]);
            $this->_blocksArray[$this->_mainCol][] = $this->_getSingleBlock();
        }
        
        $this->_pageInfo['rows'] = $this->_mask['rows'];
        
        if (! isset($this->_site['theme'])) {
            $this->_site['theme'] = 'default';
        }
        $this->_serviceTemplate->setCurrentTheme($this->_site['theme']);
        
        $this->_servicePage->setPageTitle($this->_pageInfo['title']);
        $this->_servicePage->setDescription(isset($this->_pageInfo['description']) ? $this->_pageInfo['description'] : "");
        $this->_servicePage->setKeywords(isset($this->_pageInfo['keywords']) ? $this->_pageInfo['keywords'] : array());
        
        $rootline = Manager::getService('Pages')->getAncestors($this->_pageInfo);
        $this->_rootlineArray = array();
        foreach ($rootline as $ancestor) {
            $this->_rootlineArray[] = $ancestor['id'];
        }
        $this->_rootlineArray[] = $pageId;
        $this->_pageInfo['rows'] = $this->_getRowsInfos($this->_pageInfo['rows']);
        $this->_pageInfo['template'] = 'page.html.twig';
        
        return $this->_pageInfo;
    }

    protected function _getSingleBlock()
    {
        $block = array();
        $block['configBloc'] = array();
        $block['bType'] = 'contentDetail';
        $block['id'] = 'single';
        $block['responsive'] = array(
            'tablet' => true,
            'desktop' => true,
            'phone' => true
        );
        
        return $block;
    }

    protected function _getMainColumn()
    {
        return isset($this->_mask['mainColumnId']) ? $this->_mask['mainColumnId'] : null;
    }

    /**
     * get Columns infos
     *
     * @param array $columns            
     * @return array
     */
    protected function _getColumnsInfos(array $columns = null, $noSpan = false)
    {
        if ($columns === null) {
            return null;
        }
        $returnArray = $columns;
        foreach ($columns as $key => $column) {
            $column = $this->localizeTitle($column);
            if ($noSpan) {
                $returnArray[$key]['span'] = null;
            }
            $returnArray[$key]['displayTitle'] = isset($column['displayTitle']) ? $column['displayTitle'] : null;
            $returnArray[$key]['eTitle'] = isset($column['eTitle']) ? $column['eTitle'] : null;
            $returnArray[$key]['elementTag'] = isset($column['elementTag']) ? $column['elementTag'] : null;
            $returnArray[$key]['elementStyle'] = isset($column['elementStyle']) ? $column['elementStyle'] : null;
            $returnArray[$key]['renderSpan'] = isset($column['renderSpan']) ? $column['renderSpan'] : true;
            $returnArray[$key]['template'] = Manager::getService('FrontOfficeTemplates')->getFileThemePath('column.html.twig');
            $returnArray[$key]['classHtml'] = isset($column['classHTML']) ? $column['classHTML'] : null;
            $returnArray[$key]['classHtml'] .= $this->_buildResponsiveClass($column['responsive']);
            $returnArray[$key]['idHtml'] = isset($column['idHTML']) ? $column['idHTML'] : null;
            if (isset($this->_blocksArray[$column['id']])) {
                $returnArray[$key]['blocks'] = $this->_getBlocksInfos($this->_blocksArray[$column['id']]);
                $returnArray[$key]['rows'] = null;
            } else {
                $returnArray[$key]['rows'] = $this->_getRowsInfos($column['rows']);
                $returnArray[$key]['blocks'] = null;
            }
        }
        return $returnArray;
    }

    /**
     * Change title to localized title for row, column or block
     *
     * @param array $item            
     * @return array
     */
    protected function localizeTitle(array $item)
    {
        if (isset($item['i18n'])) {
            if (isset($item['i18n'][Manager::getService('CurrentLocalization')->getCurrentLocalization()])) {
                if (isset($item['i18n'][Manager::getService('CurrentLocalization')->getCurrentLocalization()]['eTitle'])) {
                    $item['eTitle'] = $item['i18n'][Manager::getService('CurrentLocalization')->getCurrentLocalization()]['eTitle'];
                } else {
                    $item['title'] = $item['i18n'][Manager::getService('CurrentLocalization')->getCurrentLocalization()]['title'];
                }
            } elseif (isset($item['i18n'][$this->_site['defaultLanguage']])) {
                if (isset($item['i18n'][$this->_site['defaultLanguage']]['eTitle'])) {
                    $item['eTitle'] = $item['i18n'][$this->_site['defaultLanguage']]['eTitle'];
                } else {
                    $item['title'] = $item['i18n'][$this->_site['defaultLanguage']]['title'];
                }
            }
            unset($item['i18n']);
        }
        return $item;
    }

    /**
     * get Blocks infos
     *
     * @param array $blocks            
     * @return array
     */
    protected function _getBlocksInfos(array $blocks)
    {
        $returnArray = array();
        foreach ($blocks as $block) {
            $returnArray[] = $this->_getBlockData($block);
        }
        return $returnArray;
    }

    /**
     * get Rows infos
     *
     * @param array $rows            
     * @return array
     */
    protected function _getRowsInfos(array $rows = null)
    {
        if ($rows === null) {
            return null;
        }
        $returnArray = $rows;
        foreach ($rows as $key => $row) {
            $row = $this->localizeTitle($row);
            $returnArray[$key]['eTitle'] = isset($row['eTitle']) ? $row['eTitle'] : null;
            $returnArray[$key]['displayTitle'] = isset($row['displayTitle']) ? $row['displayTitle'] : null;
            $returnArray[$key]['template'] = Manager::getService('FrontOfficeTemplates')->getFileThemePath('row.html.twig');
            $returnArray[$key]['classHtml'] = isset($row['classHTML']) ? $row['classHTML'] : null;
            $returnArray[$key]['classHtml'] .= $this->_buildResponsiveClass($row['responsive']);
            $returnArray[$key]['idHtml'] = isset($row['idHTML']) ? $row['idHTML'] : null;
            $returnArray[$key]['elementTag'] = isset($row['elementTag']) ? $row['elementTag'] : null;
            $returnArray[$key]['elementStyle'] = isset($row['elementStyle']) ? $row['elementStyle'] : null;
            $returnArray[$key]['displayRow'] = isset($row['displayRow']) ? $row['displayRow'] : true;
            $returnArray[$key]['displayRowFluid'] = isset($row['displayRowFluid']) ? $row['displayRowFluid'] : false;
            $returnArray[$key]['includeContainer'] = isset($row['includeContainer']) ? $row['includeContainer'] : false;
            $returnArray[$key]['includeContainerFluid'] = isset($row['includeContainerFluid']) ? $row['includeContainerFluid'] : false;
            $returnArray[$key]['containerId'] = isset($row['containerId']) ? $row['containerId'] : false;
            $returnArray[$key]['containerClass'] = isset($row['containerClass']) ? $row['containerClass'] : false;
            
            if (is_array($row['columns'])) {
                $noSpan = (isset($row['displayAsTab'])) ? $row['displayAsTab'] : false;
                $returnArray[$key]['columns'] = $this->_getColumnsInfos($row['columns'], $noSpan);
            } else {
                $returnArray[$key]['columns'] = null;
            }
        }
        return $returnArray;
    }

    /**
     * Return the data associated to a block given by config array
     *
     * @param array $block
     *            bloc options (type, filter params...)
     * @return array block data to be rendered
     */
    protected function _getBlockData($block)
    {
        // $queryString = $this->getRequest()->getQuery();
        // $queryString->set('file-id', $media['originalFileId']);
        // $queryString->set('version', $version);
        $block = $this->localizeTitle($block);
        $params = array();
        $params['block-config'] = $block['configBloc'];
        $params['site'] = $this->_site;
        $params['blockId'] = $block['id'];
        $params['prefix'] = (isset($block['urlPrefix']) && ! empty($block['urlPrefix'])) ? $block['urlPrefix'] : 'bloc' . $block['id'];
        $params['classHtml'] = isset($block['classHTML']) ? $block['classHTML'] : null;
        $params['classHtml'] .= $this->_buildResponsiveClass($block['responsive']);
        $params['elementTag'] = isset($block['elementTag']) ? $block['elementTag'] : null;
        $params['elementStyle'] = isset($block['elementStyle']) ? $block['elementStyle'] : null;
        $params['renderDiv'] = isset($block['renderDiv']) ? $block['renderDiv'] : true;
        $params['idHtml'] = isset($block['idHTML']) ? $block['idHTML'] : null;
        $params['displayTitle'] = isset($block['displayTitle']) ? $block['displayTitle'] : false;
        $params['blockTitle'] = isset($block['title']) ? $block['title'] : null;
        $params['current-page'] = $this->_pageId;
        $params['googleMapsKey'] = $this->_site['googleMapsKey'];
        
        $blockQueryParams = $this->params()->fromQuery($params['prefix'], array());
        foreach ($blockQueryParams as $key => $value) {
            $params[$key] = $value;
        }
        $params['currentPage'] = $this->_pageId;
        $params['rootline'] = $this->_rootlineArray;
        $params['rootPage'] = $this->_serviceUrl->matchPageRoute('accueil', $this->getRequest()
            ->getUri()
            ->getHost());
        if (isset($block['configBloc']['fileName'])) {
            $params['template'] = $block['configBloc']['fileName'];
        }
        if (isset($block['configBloc']['constrainToSite'])) {
            $params['constrainToSite'] = $block['configBloc']['constrainToSite'];
        }
        $contentIdParam = $this->params()->fromRoute('content-id');
        $contentId = $contentIdParam ? $contentIdParam : null;
        if (! isset($contentId)) {
            $contentId = isset($block['configBloc']['contentId']) ? $block['configBloc']['contentId'] : null;
        }
        
        $params['content-id'] = $contentId;
        $controller = Manager::getService('Blocks')->getController($block['bType']);
        $maxLifeTime = Manager::getService('Blocks')->getMaxLifetime($block['bType']);
        $this->_servicePage->setMaxLifeTime(min($maxLifeTime,$this->_servicePage->getMaxLifeTime()));
        // Clone global request and override it woth block params
        $queryString = $this->getRequest()->getQuery();
        $blockQueryString = clone ($queryString);
        
        foreach ($params as $key => $value) {
            $blockQueryString->set($key, $value);
        }
        $this->getRequest()->setQuery($blockQueryString);
        
        // run block and get response
        $result = $this->forward()->dispatch($controller, array(
            'action' => 'index'
        ));
        
        // set back global query
        $this->getRequest()->setQuery($queryString);

        if($result instanceof \Rubedo\Templates\Raw\RawViewModel){
            $return = array(
                'data' => $result->getVariables(),
                'template' => $result->getTemplate()
            );
        }else{
            //if it was a classic ZF2 view model. Render it and add it to twig context
            $renderer = $this->serviceLocator->get('Zend\View\Renderer\RendererInterface');
            $data = $renderer->render($result);
            $return = array(
                'data' => array('content'=>$data),
                'template' => 'blocks/not-twig-block.html.twig'
            );
        }
        
        // return result to global context
        return $return;
    }

    protected function _buildResponsiveClass($responsiveArray)
    {
        foreach ($responsiveArray as $key => $value) {
            if (false == $value) {
                unset($responsiveArray[$key]);
            }
        }
        
        $responsiveArray = array_keys($responsiveArray);
        
        switch (count($responsiveArray)) {
            case 3:
                $class = '';
                break;
            case 2:
                $hiddenArray = array(
                    'tablet',
                    'desktop',
                    'phone'
                );
                list ($hiddenMedia) = array_values(array_diff($hiddenArray, $responsiveArray));
                
                $class = ' hidden-' . $hiddenMedia;
                break;
            case 0:
                $class = ' hidden';
                break;
            case 1:
            default:
                $class = '';
                foreach ($responsiveArray as $value) {
                    $class .= ' visible-' . $value;
                }
                break;
        }
        return $class;
    }
}
