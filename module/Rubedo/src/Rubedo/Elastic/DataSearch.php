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
namespace Rubedo\Elastic;

use Rubedo\Interfaces\Elastic\IDataSearch;
use Rubedo\Services\Manager;
use Zend\Json\Json;
use Zend\Debug\Debug;

/**
 * Class implementing the Rubedo API to Elastic Search using Elastica API
 *
 * @author dfanchon
 * @category Rubedo
 * @package Rubedo
 */
class DataSearch extends DataAbstract implements IDataSearch {
	
	/**
	 * Is the context a front office rendering ?
	 *
	 * @var boolean
	 */
	protected static $_isFrontEnd;
	protected $_globalFilterList = array ();
	protected $_filters;
	protected $_setFilter;
	protected $_params;
	protected $_facetOperators;
	protected $_displayedFacets = array ();
	protected $_facetDisplayMode;
	
	/**
	 * Cached getter for content type
	 *
	 * @param string $contentTypeId
	 *        	content type id
	 * @return array
	 */
	protected function _getContentType($contentTypeId) {
		if (! isset ( $this->contentTypesService )) {
			$this->contentTypesService = Manager::getService ( 'ContentTypes' );
		}
		if (! isset ( $this->contentTypesArray [$contentTypeId] )) {
			$this->contentTypesArray [$contentTypeId] = $this->contentTypesService->findById ( $contentTypeId );
		}
		return $this->contentTypesArray [$contentTypeId];
	}
	
	/**
	 * Cached getter for dam type
	 *
	 * @param string $damTypeId
	 *        	dam type id
	 * @return array
	 */
	protected function _getDamType($damTypeId) {
		if (! isset ( $this->damTypesService )) {
			$this->damTypesService = Manager::getService ( 'DamTypes' );
		}
		if (! isset ( $this->damTypesArray [$damTypeId] )) {
			$this->damTypesArray [$damTypeId] = $this->damTypesService->findById ( $damTypeId );
		}
		return $this->damTypesArray [$damTypeId];
	}
	
	/**
	 * Cached getter for user type
	 *
	 * @param string $userTypeId
	 *        	user type id
	 * @return array
	 */
	protected function _getUserType($userTypeId) {
		if (! isset ( $this->userTypesService )) {
			$this->userTypesService = Manager::getService ( 'userTypes' );
		}
		if (! isset ( $this->userTypesArray [$userTypeId] )) {
			$this->userTypesArray [$userTypeId] = $this->userTypesService->findById ( $userTypeId );
		}
		return $this->userTypesArray [$userTypeId];
	}
	
	/**
	 * Add filter to Query
	 *
	 * @param string $name
	 *        	filter name
	 *        	string $field
	 *        	field to apply filter
	 */
	protected function _addFilter($name, $field) {
		// transform param to array if single value
		if (! is_array ( $this->_params [$name] )) {
			$this->_params [$name] = array (
					$this->_params [$name] 
			);
		}
		// get mode for this facet
		$operator = isset ( $this->_facetOperators [$name] ) ? strtolower ( $this->_facetOperators [$name] ) : 'and';
		
		$filterEmpty = true;
		switch ($operator) {
			case 'or' :
				$filter = new \Elastica\Filter\Terms ();
				$filter->setTerms ( $field, $this->_params [$name] );
				$filterEmpty = false;
				break;
			case 'and' :
			default :
				$filter = new \Elastica\Filter\BoolAnd ();
				foreach ( $this->_params [$name] as $type ) {
					$termFilter = new \Elastica\Filter\Term ();
					$termFilter->setTerm ( $field, $type );
					$filter->addFilter ( $termFilter );
					$filterEmpty = false;
				}
				break;
		}
		if (! $filterEmpty) {
			$this->_globalFilterList [$name] = $filter;
			$this->_filters [$name] = $this->_params [$name];
			$this->_setFilter = true;
		}
	}
	protected function setLocaleFilter(array $values) {
		$filter = new \Elastica\Filter\Terms ();
		$filter->setTerms ( 'availableLanguages', $values );
		$this->_globalFilterList ['availableLanguages'] = $filter;
		$this->_setFilter = true;
	}
	
	/**
	 * Build Elastica facet filter from name
	 *
	 * @param string $name
	 *        	filter name
	 * @return Elastica\Filter or null
	 */
	protected function _getFacetFilter($name) {
		// get mode for this facet
		$operator = isset ( $this->_facetOperators [$name] ) ? $this->_facetOperators [$name] : 'and';
		if (! empty ( $this->_globalFilterList )) {
			$facetFilter = new \Elastica\Filter\BoolAnd ();
			$result = false;
			foreach ( $this->_globalFilterList as $key => $filter ) {
				if ($key != $name or $operator == 'and') {
					$facetFilter->addFilter ( $filter );
					$result = true;
				}
			}
			if ($result) {
				return $facetFilter;
			} else {
				return null;
			}
		} else {
			return null;
		}
	}
	
	/**
	 * Is displayed Facet ?
	 *
	 * @param string $name
	 *        	facet name
	 * @return boolean
	 */
	protected function _isFacetDisplayed($name) {
		if (! self::$_isFrontEnd or $this->_displayedFacets == array (
				"all" 
		) or in_array ( $name, $this->_displayedFacets ) or in_array ( array (
				"name" => $name,
				"operator" => "AND" 
		), $this->_displayedFacets ) or in_array ( array (
				"name" => $name,
				"operator" => "OR" 
		), $this->_displayedFacets )) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * ES search
	 *
	 * @see \Rubedo\Interfaces\IDataSearch::search()
	 * @param
	 *        	s array $params search parameters : query, type, damtype,
	 *        	lang, author, date, taxonomy, target, pager, orderby, pagesize
	 * @return Elastica\ResultSet
	 */
	public function search(array $params, $option = 'all', $withSummary = true) {
		$taxonomyService = Manager::getService ( 'Taxonomy' );
		$taxonomyTermsService = Manager::getService ( 'TaxonomyTerms' );
		
		$this->_params = $params;
		
		$this->_facetDisplayMode = isset ( $this->_params ['block-config'] ['displayMode'] ) ? $this->_params ['block-config'] ['displayMode'] : 'standard';
		
		// front-end search
		if ((self::$_isFrontEnd)) {
			
			// get list of displayed Facets
			
			$this->_displayedFacets = isset ( $this->_params ['block-config'] ['displayedFacets'] ) ? $this->_params ['block-config'] ['displayedFacets'] : array ();
			
			if (is_string ( $this->_displayedFacets )) {
				if ((empty ( $this->_displayedFacets )) || ($this->_displayedFacets == "['all']")) {
					$this->_displayedFacets = array (
							"all" 
					);
				} else {
					$this->_displayedFacets = Json::decode ( $this->_displayedFacets, Json::TYPE_ARRAY );
				}
			}
			
			// get current user language
			$currentLocale = Manager::getService ( 'CurrentLocalization' )->getCurrentLocalization ();
			
			// get site localization strategy
			$localizationStrategy = $taxonomyService->getLocalizationStrategy ();
			
			// get locale fall back
			$fallBackLocale = $taxonomyService->getFallbackLocale ();
			
			// if there is any facet to display, get overrides
			if (! empty ( $this->_displayedFacets )) {
				
				$this->_facetOperators = array ();
				
				// check if facetOverrides exists
				
				$facetOverrides = isset ( $this->_params ['block-config'] ['facetOverrides'] ) ? (Json::decode ( $this->_params ['block-config'] ['facetOverrides'], Json::TYPE_ARRAY )) : array ();
				
				if (! empty ( $facetOverrides )) { // This code is only for 2.0.x backward compatibility
					
					foreach ( $facetOverrides as $facet ) {
						if ($this->_displayedFacets == array (
								"all" 
						) or in_array ( $facet ['id'], $this->_displayedFacets )) {
							if ($facet ['id'] == 'contentType')
								$facet ['id'] = 'type';
							$this->_facetOperators [$facet ['id']] = strtolower ( $facet ['facetOperator'] );
						}
					}
				} else {
					
					// if all facets are displayed
					
					if ($this->_displayedFacets == array (
							"all" 
					)) {
						
						// get facets operators from all taxonomies
						$taxonomyList = $taxonomyService->getList ();
						
						foreach ( $taxonomyList ['data'] as $taxonomy ) {
							$this->_facetOperators [$taxonomy ['id']] = isset ( $taxonomy ['facetOperator'] ) ? strtolower ( $taxonomy ['facetOperator'] ) : 'and';
						}
					} else {
						// otherwise get facets operators from displayed facets only
						foreach ( $this->_displayedFacets as $facet ) {
							
							// Get facet operator from block
							if ($facet ['operator']) {
								$this->_facetOperators [$facet ['name']] = strtolower ( $facet ['operator'] );
							} else {
								// Get default facet operator from taxonomy if not present in block configuration
								if (preg_match ( '/[\dabcdef]{24}/', $facet ['name'] ) == 1 || $facet ['name'] == 'navigation') {
									$taxonomy = $taxonomyService->findById ( $facet ['name'] );
									if ($taxonomy) {
										$this->_facetOperators [$facet ['name']] = isset ( $taxonomy ['facetOperator'] ) ? strtolower ( $taxonomy ['facetOperator'] ) : 'and';
									}
								}
							}
						}
					}
				}
			}
		} else {
			// for BO, the strategy is to search into the working langage with
			// fallback on all other langages (_all)
			$localizationStrategy = "backOffice";
			$currentUser = Manager::getService ( 'CurrentUser' )->getCurrentUser ();
			$currentLocale = $currentUser ["workingLanguage"];
		}
		
		// Get taxonomies
		$collection = Manager::getService ( 'Taxonomy' );
		$taxonomyList = $collection->getList ();
		$taxonomies = $taxonomyList ['data'];
		
		// Get faceted fields
		$collection = Manager::getService ( 'ContentTypes' );
		$facetedFields = $collection->GetFacetedFields ();
		foreach ( $facetedFields as $facetedField ) {
			// get default facet operator from faceted field if not present in block configuration
			if (! isset ( $this->_facetOperators [$facetedField ['name']] )) {
				$this->_facetOperators [$facetedField ['name']] = $facetedField ['facetOperator'];
			}
		}
		
		$result = array ();
		$result ['data'] = array ();
		
		// Default parameters
		$defaultVars = array (
				'query' => '',
				'type' => '',
				'lang' => '',
				'author' => '',
				'lastupdatetime' => '',
				'pager' => 0,
				'orderby' => '_score',
				'orderbyDirection' => 'desc',
				'pagesize' => 25 
		);
		
		// set default options
		if (! array_key_exists ( 'lang', $this->_params )) {
			$session = Manager::getService ( 'Session' );
			$this->_params ['lang'] = $session->get ( 'lang', 'fr' );
		}
		
		if (! array_key_exists ( 'pager', $this->_params ))
			$this->_params ['pager'] = $defaultVars ['pager'];
		
		if (! array_key_exists ( 'orderby', $this->_params ))
			$this->_params ['orderby'] = $defaultVars ['orderby'];
		
		if (! array_key_exists ( 'orderbyDirection', $this->_params ))
			$this->_params ['orderbyDirection'] = $defaultVars ['orderbyDirection'];
		
		if (! array_key_exists ( 'pagesize', $this->_params ))
			$this->_params ['pagesize'] = $defaultVars ['pagesize'];
		
		if (! array_key_exists ( 'query', $this->_params ))
			$this->_params ['query'] = $defaultVars ['query'];

		$this->_params ['query'] = strip_tags ( $this->_params ['query'] );
		
		// Build global filter
		
		$this->_setFilter = false;
		
		$globalFilter = new \Elastica\Filter\BoolAnd ();
		
		// Filter on read Workspaces
		
		$readWorkspaceArray = Manager::getService ( 'CurrentUser' )->getReadWorkspaces ();
		
		if (($option != "user") && (! in_array ( 'all', $readWorkspaceArray )) && (! empty ( $readWorkspaceArray ))) {
			
			$workspacesFilter = new \Elastica\Filter\BoolOr ();
			foreach ( $readWorkspaceArray as $wsTerm ) {
				$workspaceFilter = new \Elastica\Filter\Term ();
				$workspaceFilter->setTerm ( 'target', $wsTerm );
				$workspacesFilter->addFilter ( $workspaceFilter );
			}
			
			$this->_globalFilterList ['target'] = $workspacesFilter;
			$this->_setFilter = true;
		}
		
		// Frontend filters, for contents only : online, start and end publication date
		
		if ((self::$_isFrontEnd) && ($option != "user") && ($option != "dam")) {
			
			// Only 'online' contents
			
			$onlineFilterIsTrue = new \Elastica\Filter\Term ();
			$onlineFilterIsTrue->setTerm ( 'online', true );
			$onlineFilterNotExists = new \Elastica\Filter\Missing ();
			$onlineFilterNotExists->setField ( 'online' );
			$onlineFilterNotExists->setParam ( 'existence', true );
			$onlineFilterNotExists->setParam ( 'null_value', true );
			$onlineFilter = new \Elastica\Filter\BoolOr ();
			$onlineFilter->addFilter ( $onlineFilterIsTrue );
			$onlineFilter->addFilter ( $onlineFilterNotExists );
					
			//  Filter on start and end publication date
			
			$now = Manager::getService ( 'CurrentTime' )->getCurrentTime ();
			
			// filter on start
			$beginFilter = new \Elastica\Filter\BoolOr ();
			$beginFilterWithValue = new \Elastica\Filter\NumericRange ( 'startPublicationDate', array (
					'to' => $now 
			) );
			$beginFilterWithoutValue = new \Elastica\Filter\Term ();
			$beginFilterWithoutValue->setTerm ( 'startPublicationDate', 0 );
			$beginFilterNotExists = new \Elastica\Filter\Missing ();
			$beginFilterNotExists->setField ( 'startPublicationDate' );
			$beginFilterNotExists->setParam ( 'existence', true );
			$beginFilterNotExists->setParam ( 'null_value', true );
			$beginFilter = new \Elastica\Filter\BoolOr ();
			$beginFilter->addFilter ( $beginFilterNotExists );
			$beginFilter->addFilter ( $beginFilterWithoutValue );
			$beginFilter->addFilter ( $beginFilterWithValue );
			
			// filter on end : not set or not ended
			$endFilter = new \Elastica\Filter\BoolOr ();
			$endFilterWithValue = new \Elastica\Filter\NumericRange ( 'endPublicationDate', array (
					'from' => $now 
			) );
			$endFilterWithoutValue = new \Elastica\Filter\Term ();
			$endFilterWithoutValue->setTerm ( 'endPublicationDate', 0 );
			$endFilterNotExists = new \Elastica\Filter\Missing ();
			$endFilterNotExists->setField ( 'endPublicationDate' );
			$endFilterNotExists->setParam ( 'existence', true );
			$endFilterNotExists->setParam ( 'null_value', true );
			$endFilter->addFilter ( $endFilterNotExists );
			$endFilter->addFilter ( $endFilterWithoutValue );
			$endFilter->addFilter ( $endFilterWithValue );
						
			// build complete filter
			$frontEndFilter = new \Elastica\Filter\BoolAnd ();
			$frontEndFilter->addFilter ( $onlineFilter );
			$frontEndFilter->addFilter ( $beginFilter );
			$frontEndFilter->addFilter ( $endFilter );
			
			// push filter to global
			$this->_globalFilterList ['frontend'] = $frontEndFilter;
			$this->_setFilter = true;
	
		}
		
		// filter on query
		if ($this->_params ['query'] != '') {
			$this->_filters ['query'] = $this->_params ['query'];
		}
		
		// filter on object type : content, dam or user
		if (array_key_exists ( 'objectType', $this->_params )) {
			$this->_addFilter ( 'objectType', 'objectType' );
		}
		
		// filter on content type
		if (array_key_exists ( 'type', $this->_params )) {
			$this->_addFilter ( 'type', 'contentType' );
		}
		
		// filter on dam type
		if (array_key_exists ( 'damType', $this->_params )) {
			$this->_addFilter ( 'damType', 'damType' );
		}
		
		// filter on user type
		if (array_key_exists ( 'userType', $this->_params )) {
			$this->_addFilter ( 'userType', 'userType' );
		}
		
		// add filter for geo search on content types with 'position' field
		if ($option == 'geo') {
			$contentTypeList = Manager::getService ( 'ContentTypes' )->getGeolocatedContentTypes ();
			if (! empty ( $contentTypeList )) {
				$geoFilter = new \Elastica\Filter\BoolOr ();
				foreach ( $contentTypeList as $contentTypeId ) {
					$geoTypeFilter = new \Elastica\Filter\Term ();
					$geoTypeFilter->setTerm ( 'contentType', $contentTypeId );
					$geoFilter->addFilter ( $geoTypeFilter );
				}
				// push filter to global
				$this->_globalFilterList ['geoTypes'] = $geoFilter;
				$this->_setFilter = true;
			}
		}
		
		// filter on author
		if (array_key_exists ( 'author', $this->_params )) {
			$this->_addFilter ( 'author', 'createUser.id' );
		}
		
		// filter on author
		if (array_key_exists ( 'userName', $this->_params )) {
			$this->_addFilter ( 'userName', 'first_letter' );
		}
		
		// filter on lastupdatetime
		if (array_key_exists ( 'lastupdatetime', $this->_params )) {
			$filter = new \Elastica\Filter\Range ( 'lastUpdateTime', array (
					'from' => $this->_params ['lastupdatetime'] 
			) );
			$this->_globalFilterList ['lastupdatetime'] = $filter;
			$this->_filters ['lastupdatetime'] = $this->_params ['lastupdatetime'];
			$this->_setFilter = true;
		}
		
		// filter on geolocalisation if inflat, suplat, inflon and suplon are
		// set
		if (isset ( $this->_params ['inflat'] ) && isset ( $this->_params ['suplat'] ) && isset ( $this->_params ['inflon'] ) && isset ( $this->_params ['suplon'] )) {
			$topleft = array (
					$this->_params ['inflon'] + 0,
					$this->_params ['suplat'] + 0 
			);
			$bottomright = array (
					$this->_params ['suplon'] + 0,
					$this->_params ['inflat'] + 0 
			);
			$filter = new \Elastica\Filter\GeoBoundingBox ( 'fields.position.location.coordinates', array (
					$topleft,
					$bottomright 
			) );
			$this->_globalFilterList ['geo'] = $filter;
			$this->_setFilter = true;
		}
		
		// filter on taxonomy
		foreach ( $taxonomies as $taxonomy ) {
			$vocabulary = $taxonomy ['id'];
			
			if (array_key_exists ( $vocabulary, $this->_params )) {
				// transform param to array if single value
				if (! is_array ( $this->_params [$vocabulary] )) {
					$this->_params [$vocabulary] = array (
							$this->_params [$vocabulary] 
					);
				}
				foreach ( $this->_params [$vocabulary] as $term ) {
					
					$this->_addFilter ( $vocabulary, 'taxonomy.' . $vocabulary );
				}
			}
		}
		
		// filter on fields
		foreach ( $facetedFields as $field ) {
			
			if ($field ['useAsVariation']) {
				$fieldName = "productProperties.variations.".$field ['name'];
			} else {
				if (! $field ['localizable']) {
					$fieldName = $field ['name'];
				} else {
					$fieldName = $field ['name'] . "_" . $currentLocale;
				}
			}
			
			if (array_key_exists ( urlencode ( $field ['name'] ), $this->_params )) {
				$this->_addFilter ( $field ['name'], $fieldName );
			}
		}
		
		$elasticaQuery = new \Elastica\Query ();
		
		$elasticaQueryString = new \Elastica\Query\QueryString ();
		
		// Setting fields from localization strategy for content or dam search
		// only
		
		if ($option != "user") {
			switch ($localizationStrategy) {
				case 'backOffice' :
					//$this->setLocaleFilter ( Manager::getService ( 'Languages' )->getActiveLocales () );
					$elasticaQueryString->setFields ( array (
							"all_" . $currentLocale,
							"_all^0.1" 
					) );
					break;
				case 'onlyOne' :
					$this->setLocaleFilter ( array (
							$currentLocale 
					) );
					$elasticaQueryString->setFields ( array (
							"all_" . $currentLocale,
							"all_nonlocalized",
							"_all^0.1"
					) );
					break;
				
				case 'fallback' :
				default :
					$this->setLocaleFilter ( array (
							$currentLocale,
							$fallBackLocale 
					) );
					if ($currentLocale != $fallBackLocale) {
						$elasticaQueryString->setFields ( array (
								"all_" . $currentLocale,
								"all_" . $fallBackLocale . "^0.1",
								"all_nonlocalized^0.1",
								"_all^0.1"
						) );
					} else {
						$elasticaQueryString->setFields ( array (
								"all_" . $currentLocale,
								"all_nonlocalized",
								"_all^0.1"
						) );
					}
					break;
			}
		} else {
			
			// user search do not use localization
			$elasticaQueryString->setFields ( array (
					"all_nonlocalized" 
			) );
		}
		
		// add user query
		if ($this->_params ['query'] != "") {
			$elasticaQueryString->setQuery ( $this->_params ['query'] );
		} else {
			$elasticaQueryString->setQuery ( '*' );
		}
		$elasticaQuery->setQuery ( $elasticaQueryString );
		
		// Apply filter to query
		if (! empty ( $this->_globalFilterList )) {
			foreach ( $this->_globalFilterList as $filter ) {
				$globalFilter->addFilter ( $filter );
			}
			$elasticaQuery->setFilter ( $globalFilter );
		}
		
		// Define the objectType facet (content, dam or user)
		
		if ($this->_isFacetDisplayed ( 'objectType' )) {
			
			$elasticaFacetObjectType = new \Elastica\Facet\Terms ( 'objectType' );
			$elasticaFacetObjectType->setField ( 'objectType' );
			
			// Exclude active Facets for this vocabulary
			if ($this->_facetDisplayMode != 'checkbox' and isset ( $this->_filters ['objectType'] )) {
				$elasticaFacetObjectType->setExclude ( array (
						$this->_filters ['objectType'] 
				) );
			}
			$elasticaFacetObjectType->setSize ( 1000 );
			$elasticaFacetObjectType->setOrder ( 'count' );
			
			// Apply filters from other facets
			$facetFilter = $this->_getFacetFilter ( 'objectType' );
			if (! is_null ( $facetFilter )) {
				$elasticaFacetObjectType->setFilter ( $facetFilter );
			}
			
			// Add type facet to the search query object
			$elasticaQuery->addFacet ( $elasticaFacetObjectType );
		}
		
		// Define the type facet
		
		if (($this->_isFacetDisplayed ( 'contentType' )) || ($this->_isFacetDisplayed ( 'type' ))) {
			$elasticaFacetType = new \Elastica\Facet\Terms ( 'type' );
			$elasticaFacetType->setField ( 'contentType' );
			
			// Exclude active Facets for this vocabulary
			if ($this->_facetDisplayMode != 'checkbox' and isset ( $this->_filters ['type'] )) {
				$elasticaFacetType->setExclude ( array (
						$this->_filters ['type'] 
				) );
			}
			$elasticaFacetType->setSize ( 1000 );
			$elasticaFacetType->setOrder ( 'count' );
			
			// Apply filters from other facets
			$facetFilter = $this->_getFacetFilter ( 'type' );
			if (! is_null ( $facetFilter )) {
				$elasticaFacetType->setFilter ( $facetFilter );
			}
			
			// Add type facet to the search query object
			$elasticaQuery->addFacet ( $elasticaFacetType );
		}
		
		// Define the dam type facet
		
		if ($this->_isFacetDisplayed ( 'damType' )) {
			
			$elasticaFacetDamType = new \Elastica\Facet\Terms ( 'damType' );
			$elasticaFacetDamType->setField ( 'damType' );
			
			// Exclude active Facets for this vocabulary
			if ($this->_facetDisplayMode != 'checkbox' and isset ( $this->_filters ['damType'] )) {
				$elasticaFacetDamType->setExclude ( array (
						$this->_filters ['damType'] 
				) );
			}
			$elasticaFacetDamType->setSize ( 1000 );
			$elasticaFacetDamType->setOrder ( 'count' );
			
			// Apply filters from other facets
			$facetFilter = $this->_getFacetFilter ( 'damType' );
			
			if (! is_null ( $facetFilter )) {
				$elasticaFacetDamType->setFilter ( $facetFilter );
			}
			
			// Add dam type facet to the search query object.
			$elasticaQuery->addFacet ( $elasticaFacetDamType );
		}
		
		// Define the user type facet
		
		if ($this->_isFacetDisplayed ( 'userType' )) {
			
			$elasticaFacetUserType = new \Elastica\Facet\Terms ( 'userType' );
			$elasticaFacetUserType->setField ( 'userType' );
			
			// Exclude active Facets for this vocabulary
			if ($this->_facetDisplayMode != 'checkbox' and isset ( $this->_filters ['userType'] )) {
				$elasticaFacetUserType->setExclude ( array (
						$this->_filters ['userType'] 
				) );
			}
			$elasticaFacetUserType->setSize ( 1000 );
			$elasticaFacetUserType->setOrder ( 'count' );
			
			// Apply filters from other facets
			$facetFilter = $this->_getFacetFilter ( 'userType' );
			
			if (! is_null ( $facetFilter )) {
				$elasticaFacetUserType->setFilter ( $facetFilter );
			}
			
			// Add user type facet to the search query object.
			$elasticaQuery->addFacet ( $elasticaFacetUserType );
		}
		
		// Define the author facet
		
		if ($this->_isFacetDisplayed ( 'author' )) {
			$elasticaFacetAuthor = new \Elastica\Facet\Terms ( 'author' );
			$elasticaFacetAuthor->setField ( 'createUser.id' );
			
			// Exclude active Facets for this vocabulary
			if ($this->_facetDisplayMode != 'checkbox' and isset ( $this->_filters ['author'] )) {
				$elasticaFacetAuthor->setExclude ( array (
						$this->_filters ['author'] 
				) );
			}
			$elasticaFacetAuthor->setSize ( 5 );
			$elasticaFacetAuthor->setOrder ( 'count' );
			
			// Apply filters from other facets
			$facetFilter = $this->_getFacetFilter ( 'author' );
			if (! is_null ( $facetFilter )) {
				$elasticaFacetAuthor->setFilter ( $facetFilter );
			}
			
			// Add that facet to the search query object.
			$elasticaQuery->addFacet ( $elasticaFacetAuthor );
		}
		
		// Define the alphabetical name facet for users
		
		if ($option == "user") {
			
			$elasticaFacetUserName = new \Elastica\Facet\Terms ( 'userName' );
			$elasticaFacetUserName->setField ( 'first_letter' );
			
			$elasticaFacetUserName->setSize(25);
			// $elasticaFacetUserName->setOrder('count');
			
			// Apply filters from other facets
			$facetFilter = $this->_getFacetFilter ( 'userName' );
			if (! is_null ( $facetFilter )) {
				$elasticaFacetUserName->setFilter ( $facetFilter );
			}
			
			// Add that facet to the search query object.
			$elasticaQuery->addFacet ( $elasticaFacetUserName );
		}
		
		// Define the date facet.
		
		if ($this->_isFacetDisplayed ( 'lastupdatetime' )) {
			
			$elasticaFacetDate = new \Elastica\Facet\Range ( 'lastupdatetime' );
			$elasticaFacetDate->setField ( 'lastUpdateTime' );
			$d = Manager::getService ( 'CurrentTime' )->getCurrentTime ();
			
			// In ES 0.9, date are in microseconds
			$lastday = mktime ( 0, 0, 0, date ( 'm', $d ), date ( 'd', $d ) - 1, date ( 'Y', $d ) ) * 1000;
			// Cast to string for 32bits systems
			$lastday = ( string ) $lastday;
			$lastweek = mktime ( 0, 0, 0, date ( 'm', $d ), date ( 'd', $d ) - 7, date ( 'Y', $d ) ) * 1000;
			$lastweek = ( string ) $lastweek;
			$lastmonth = mktime ( 0, 0, 0, date ( 'm', $d ) - 1, date ( 'd', $d ), date ( 'Y', $d ) ) * 1000;
			$lastmonth = ( string ) $lastmonth;
			$lastyear = mktime ( 0, 0, 0, date ( 'm', $d ), date ( 'd', $d ), date ( 'Y', $d ) - 1 ) * 1000;
			$lastyear = ( string ) $lastyear;
			$ranges = array (
					array (
							'from' => $lastday 
					),
					array (
							'from' => $lastweek 
					),
					array (
							'from' => $lastmonth 
					),
					array (
							'from' => $lastyear 
					) 
			);
			$timeLabel = array ();
			
			$timeLabel [$lastday] = Manager::getService ( 'Translate' )->translateInWorkingLanguage ( "Search.Facets.Label.Date.Day", 'Past 24H' );
			$timeLabel [$lastweek] = Manager::getService ( 'Translate' )->translateInWorkingLanguage ( "Search.Facets.Label.Date.Week", 'Past week' );
			$timeLabel [$lastmonth] = Manager::getService ( 'Translate' )->translateInWorkingLanguage ( "Search.Facets.Label.Date.Month", 'Past month' );
			$timeLabel [$lastyear] = Manager::getService ( 'Translate' )->translateInWorkingLanguage ( "Search.Facets.Label.Date.Year", 'Past year' );
			
			$elasticaFacetDate->setRanges ( $ranges );
			
			// Apply filters from other facets
			$facetFilter = $this->_getFacetFilter ( 'lastupdatetime' );
			if (! is_null ( $facetFilter )) {
				$elasticaFacetDate->setFilter ( $facetFilter );
			}
			
			// Add that facet to the search query object.
			$elasticaQuery->addFacet ( $elasticaFacetDate );
		}
		
		// Define taxonomy facets
		foreach ( $taxonomies as $taxonomy ) {
			$vocabulary = $taxonomy ['id'];
			
			if ($this->_isFacetDisplayed ( $vocabulary )) {
				
				$elasticaFacetTaxonomy = new \Elastica\Facet\Terms ( $vocabulary );
				$elasticaFacetTaxonomy->setField ( 'taxonomy.' . $taxonomy ['id'] );
				
				// Exclude active Facets for this vocabulary
				if ($this->_facetDisplayMode != 'checkbox' and isset ( $this->_filters [$vocabulary] )) {
					$elasticaFacetTaxonomy->setExclude ( $this->_filters [$vocabulary] );
				}
				$elasticaFacetTaxonomy->setSize ( 20 );
				$elasticaFacetTaxonomy->setOrder ( 'count' );
				
				// Apply filters from other facets
				$facetFilter = $this->_getFacetFilter ( $vocabulary );
				if (! is_null ( $facetFilter )) {
					$elasticaFacetTaxonomy->setFilter ( $facetFilter );
				}
				
				// Add that facet to the search query object.
				$elasticaQuery->addFacet ( $elasticaFacetTaxonomy );
			}
		}
		
		// Define the fields facets
		foreach ( $facetedFields as $field ) {
			
			if ($field ['useAsVariation']) {
				$fieldName = "productProperties.variations.".$field ['name'];
			} else {
			
				if (! $field ['localizable']) {
					$fieldName = $field ['name'];
				} else {
					$fieldName = $field ['name'] . "_" . $currentLocale;
				}
			}

			if ($this->_isFacetDisplayed ( $field ['name'] )) {
				
				$elasticaFacetField = new \Elastica\Facet\Terms ( $field ['name'] );
				$elasticaFacetField->setField ( "$fieldName" );
				
				// Exclude active Facets for this vocabulary
				if ($this->_facetDisplayMode != 'checkbox' and isset ( $this->_filters [$fieldName] )) {
					$elasticaFacetField->setExclude ( $this->_filters [$fieldName] );
				}
				$elasticaFacetField->setSize ( 20 );
				$elasticaFacetField->setOrder ( 'count' );
				
				// Apply filters from other facets
				$facetFilter = $this->_getFacetFilter ( $fieldName );
				if (! is_null ( $facetFilter )) {
					$elasticaFacetField->setFilter ( $facetFilter );
				}
				
				// Add that facet to the search query object.
				$elasticaQuery->addFacet ( $elasticaFacetField );
			}
		}
		
		// Add pagination
		if (is_numeric ( $this->_params ['pagesize'] )) {
			$elasticaQuery->setSize ( $this->_params ['pagesize'] );
			$elasticaQuery->setFrom ( $this->_params ['pager'] * $this->_params ['pagesize'] );
		}
		
		// add sort
		$elasticaQuery->setSort ( array (
				$this->_params ['orderby'] => array( 'order' => strtolower ( $this->_params ['orderbyDirection'] ), "ignore_unmapped" => true )
		));
		
		$returnedFieldsArray = array (
				"*" 
		);
		$elasticaQuery->setFields ( $returnedFieldsArray );
		
		// run query
		$client = $this->_client;
		$client->setLogger ( Manager::getService ( 'SearchLogger' )->getLogger () );
		$search = new \Elastica\Search ( $client );
		switch ($option) {
			case 'content' :
				$search->addIndex ( self::$_content_index );
				break;
			case 'dam' :
				$search->addIndex ( self::$_dam_index );
				break;
			case 'user' :
				$search->addIndex ( self::$_user_index );
				break;
			case 'geo' :
				$search->addIndex ( self::$_content_index );
				break;
			case 'all' :
				$search->addIndex ( self::$_content_index );
				$search->addIndex ( self::$_dam_index );
				$search->addIndex ( self::$_user_index );
				break;
		}
		
		// Get resultset
		$elasticaResultSet = $search->search ( $elasticaQuery );

		// Update data
		$resultsList = $elasticaResultSet->getResults ();

		$result ['total'] = $elasticaResultSet->getTotalHits ();
		$result ['query'] = $this->_params ['query'];
		$userWriteWorkspaces = Manager::getService ( 'CurrentUser' )->getWriteWorkspaces ();
		$userCanWriteContents = Manager::getService ( 'Acl' )->hasAccess ( "write.ui.contents" );
		$userCanWriteDam = Manager::getService ( 'Acl' )->hasAccess ( "write.ui.dam" );
		
		$writeWorkspaceArray = Manager::getService ( 'CurrentUser' )->getWriteWorkspaces ();
		
		foreach ( $resultsList as $resultItem ) {
			
			$data = $resultItem->getData ();
			
			$resultData ['id'] = $resultItem->getId ();
			$resultData ['typeId'] = $resultItem->getType ();
			$score = $resultItem->getScore ();
			if (! is_float ( $score ))
				$score = 1;
			$resultData ['score'] = round ( $score * 100 );
			$resultData ['authorName'] = isset ( $data ['createUser.fullName'] [0] ) ? $data ['createUser.fullName'] [0] : null;
			$resultData ['author'] = isset ( $data ['createUser.id'] [0] ) ? $data ['createUser.id'] [0] : null;
			$resultData ['version'] = isset ( $data ['version'] [0] ) ? $data ['version'] [0] : null;
			$resultData ['photo'] = isset ( $data ['photo'] [0] ) ? $data ['photo'] [0] : null;
			$resultData ['objectType'] = $data ['objectType'] [0];
			unset ( $data ['objectType'] );
			unset ( $data ['photo'] );
			
			if (isset ( $data ['availableLanguages'] [0] )) {
				if (! is_array ( $data ['availableLanguages'] [0] )) {
					$resultData ['availableLanguages'] = array (
							$data ['availableLanguages'] [0] 
					);
				} else {
					$resultData = $data ['availableLanguages'] [0];
				}
			}
			
			switch ($resultData ['objectType']) {
				case 'content' :
					if (isset ( $data ["i18n." . $currentLocale . ".fields.text"][0] )) {		
						$resultData ['title'] = $data ["i18n." . $currentLocale . ".fields.text"][0];
						if ($withSummary) {
							$resultData ['summary'] = (isset ( $data ["i18n." . $currentLocale. ".fields.summary"][0] )) ? $data ["i18n." . $currentLocale. ".fields.summary"][0] : "";
						}
					} else {
						$resultData ['title'] = $data ['text'] [0];
					}
					$contentType = $this->_getContentType ( $data ['contentType'] [0] );
					if (! $userCanWriteContents || $contentType ['readOnly']) {
						$resultData ['readOnly'] = true;
					} elseif (! in_array ( $resultItem->writeWorkspace, $userWriteWorkspaces )) {
						$resultData ['readOnly'] = true;
					}
					$resultData ['type'] = $contentType ['type'];
					break;
				case 'dam' :
					if (isset ( $data ["i18n." . $currentLocale . ".fields.title"][0] )) {
						$resultData ['title'] = $data ["i18n." . $currentLocale . ".fields.title"][0];
					} else {
						$resultData ['title'] = $data ['text'] [0];
					}
					$damType = $this->_getDamType ( $data ['damType'] [0] );
					if (! $userCanWriteDam || $damType ['readOnly']) {
						$resultData ['readOnly'] = true;
					} elseif (! in_array ( $resultItem->writeWorkspace, $userWriteWorkspaces )) {
						$resultData ['readOnly'] = true;
					}
					$resultData ['type'] = $damType ['type'];
					break;
				case 'user' :
					
					if (isset ( $data ["fields.name"] [0] )) {
						$resultData ['name'] = $data ["fields.name"] [0];
					} else {
						$resultData ['name'] = $data ['email'] [0];
					}
					$resultData ['title'] = $resultData ['name'];
					$userType = $this->_getUserType ( $data ['userType'] [0] );
					$resultData ['type'] = $userType ['type'];
					break;
			}
			
			// ensure that date is formated as timestamp while handled as date
			// type for ES
			$data ['lastUpdateTime'] = strtotime ( $data ['lastUpdateTime'] [0] );
			
			// Set read only
			
			if (! isset ( $data ['writeWorkspace'] [0] ) or in_array ( $data ['writeWorkspace'] [0], $writeWorkspaceArray )) {
				$resultData ['readOnly'] = false;
			} else {
				$resultData ['readOnly'] = true;
			}
			
			$result ['data'] [] = array_merge ( $resultData, $data );
		}
		
		// Add label to Facets, hide empty facets,
		$elasticaFacetsTemp = $elasticaResultSet->getFacets ();
		$elasticaFacets = array ();
		if ((is_array ( $this->_displayedFacets )) && (! empty ( $this->_displayedFacets )) && (! is_string ( $this->_displayedFacets [0] ))) {
			foreach ( $this->_displayedFacets as $requestedFacet ) {
				foreach ( $elasticaFacetsTemp as $id => $obtainedFacet ) {
					if ($id == $requestedFacet ["name"]) {
						$elasticaFacets [$id] = $obtainedFacet;
					}
				}
			}
		} else {
			$elasticaFacets = $elasticaFacetsTemp;
		}
		$result ['facets'] = array ();
		
		foreach ( $elasticaFacets as $id => $facet ) {
			$temp = ( array ) $facet;
			$renderFacet = true;
			if (! empty ( $temp )) {
				$temp ['id'] = $id;
				switch ($id) {
					case 'navigation' :
						
						$temp ['label'] = Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.Navigation", 'Navigation' );
						if (array_key_exists ( 'terms', $temp ) and count ( $temp ['terms'] ) > 0) {
							foreach ( $temp ['terms'] as $key => $value ) {
								$termItem = $taxonomyTermsService->getTerm ( $value ['term'], 'navigation' );
								$temp ['terms'] [$key] ['label'] = $termItem ["Navigation"];
							}
						} else {
							$renderFacet = false;
						}
						break;
					
					case 'damType' :
						
						$temp ['label'] = Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.MediaType", 'Media type' );
						if (array_key_exists ( 'terms', $temp ) and count ( $temp ['terms'] ) > 0) {
							foreach ( $temp ['terms'] as $key => $value ) {
								$termItem = $this->_getDamType ( $value ['term'] );
								if ($termItem && isset ( $termItem ['type'] )) {
									$temp ['terms'] [$key] ['label'] = $termItem ['type'];
								}
							}
						} else {
							$renderFacet = false;
						}
						break;
					
					case 'objectType' :
						
						$temp ['label'] = Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.DataType", 'Data type' );
						foreach ( $temp ['terms'] as $key => $value ) {
							$temp ['terms'] [$key] ['label'] = strtoupper ( $value ["term"] );
						}
						break;
					
					case 'type' :
						
						$temp ['label'] = Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.ContentType", 'Content type' );
						if (array_key_exists ( 'terms', $temp ) and count ( $temp ['terms'] ) > 0) {
							foreach ( $temp ['terms'] as $key => $value ) {
								
								$termItem = $this->_getContentType ( $value ['term'] );
								$temp ['terms'] [$key] ['label'] = $termItem ['type'];
							}
						} else {
							$renderFacet = false;
						}
						break;
					
					case 'userType' :
						
						$temp ['label'] = Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.UserType", 'User type' );
						if (array_key_exists ( 'terms', $temp ) and count ( $temp ['terms'] ) > 0) {
							foreach ( $temp ['terms'] as $key => $value ) {
								
								$termItem = $this->_getUserType ( $value ['term'] );
								$temp ['terms'] [$key] ['label'] = $termItem ['type'];
							}
						} else {
							$renderFacet = false;
						}
						break;
					
					case 'author' :
						
						$temp ['label'] = Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.Author", 'Author' );
						if ($this->_facetDisplayMode == 'checkbox' or (array_key_exists ( 'terms', $temp ) and count ( $temp ['terms'] ) > 0)) {
							$collection = Manager::getService ( 'Users' );
							foreach ( $temp ['terms'] as $key => $value ) {
								$termItem = $collection->findById ( $value ['term'] );
								$temp ['terms'] [$key] ['label'] = $termItem ['name'];
							}
						} else {
							$renderFacet = false;
						}
						break;
					
					case 'userName' :
						
						$temp ['label'] = Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.UserName", 'User Name' );
						foreach ( $temp ['terms'] as $key => $value ) {
							$temp ['terms'] [$key] ['label'] = strtoupper ( $value ["term"] );
						}
						
						break;
					
					case 'lastupdatetime' :
						
						$temp ['label'] = Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.ModificationDate", 'Modification date' );
						if (array_key_exists ( 'ranges', $temp ) and count ( $temp ['ranges'] ) > 0) {
							foreach ( $temp ['ranges'] as $key => $value ) {
								$rangeCount = $temp ['ranges'] [$key] ['count'];
								// unset facet when count = 0 or total results
								// count when display mode is not set to
								// checkbox
								if ($this->_facetDisplayMode == 'checkbox' or ($rangeCount > 0 and $rangeCount < $result ['total'])) {
									$temp ['ranges'] [$key] ['label'] = $timeLabel [( string ) $temp ['ranges'] [$key] ['from']];
								} else {
									unset ( $temp ['ranges'] [$key] );
								}
							}
						} else {
							$renderFacet = false;
						}
						
						$temp ["ranges"] = array_values ( $temp ["ranges"] );
						
						break;
					
					default :
						$regex = '/^[0-9a-z]{24}$/';
						if (preg_match ( $regex, $id )) { // Taxonomy facet use
						                               // mongoID
							$vocabularyItem = Manager::getService ( 'Taxonomy' )->findById ( $id );
							$temp ['label'] = $vocabularyItem ['name'];
							if (array_key_exists ( 'terms', $temp ) and count ( $temp ['terms'] ) > 0) {
								foreach ( $temp ['terms'] as $key => $value ) {
									$termItem = $taxonomyTermsService->findById ( $value ['term'] );
									if ($termItem) {
										$temp ['terms'] [$key] ['label'] = $termItem ['text'];
									} else {
										unset ( $temp ['terms'] [$key] );
									}
								}
							} else {
								$renderFacet = false;
							}
						} else {
							// faceted field
							$intermediaryVal = $this->searchLabel ( $facetedFields, "name", $id );
							$temp ['label'] = $intermediaryVal [0] ['label'];
							
							if (array_key_exists ( 'terms', $temp ) and count ( $temp ['terms'] ) > 0) {
								foreach ( $temp ['terms'] as $key => $value ) {
									$temp ['terms'] [$key] ['label'] = $value ['term'];
								}
							}
						}
						break;
				}
				if ($renderFacet) {
					$result ['facets'] [] = $temp;
				}
			}
		}
		
		// Add label to filters
		
		$result ['activeFacets'] = array ();
		if (is_array ( $this->_filters )) {
			foreach ( $this->_filters as $id => $termId ) {
				switch ($id) {
					
					case 'damType' :
						$temp = array (
								'id' => $id,
								'label' => Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.MediaType", 'Media type' ) 
						);
						foreach ( $termId as $term ) {
							$termItem = $this->_getDamType ( $term );
							$temp ['terms'] [] = array (
									'term' => $term,
									'label' => $termItem ['type'] 
							);
						}
						
						break;
					
					case 'type' :
						$temp = array (
								'id' => $id,
								'label' => Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.ContentType", 'Content type' ) 
						);
						foreach ( $termId as $term ) {
							$termItem = $this->_getContentType ( $term );
							$temp ['terms'] [] = array (
									'term' => $term,
									'label' => $termItem ['type'] 
							);
						}
						
						break;
					
					case 'userType' :
						$temp = array (
								'id' => $id,
								'label' => Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.UserType", 'User type' ) 
						);
						foreach ( $termId as $term ) {
							$termItem = $this->_getUserType ( $term );
							$temp ['terms'] [] = array (
									'term' => $term,
									'label' => $termItem ['type'] 
							);
						}
						
						break;
					
					case 'author' :
						$temp = array (
								'id' => $id,
								'label' => Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.Author", 'Author' ) 
						);
						foreach ( $termId as $term ) {
							$termItem = Manager::getService ( 'Users' )->findById ( $term );
							$temp ['terms'] [] = array (
									'term' => $term,
									'label' => $termItem ['name'] 
							);
						}
						
						break;
					
					case 'userName' :
						$temp = array (
								'id' => $id,
								'label' => Manager::getService ( 'Translate' )->translate ( "Search.Facets.Label.UserName", 'User Name' ) 
						);
						foreach ( $termId as $term ) {
							$temp ['terms'] [] = array (
									'term' => $term,
									'label' => strtoupper ( $term ) 
							);
						}
						
						break;
					
					case 'lastupdatetime' :
						$temp = array (
								'id' => 'lastupdatetime',
								'label' => 'Date',
								'terms' => array (
										array (
												'term' => $termId,
												'label' => $timeLabel [( string ) $termId] 
										) 
								) 
						);
						
						break;
					
					case 'query' :
						$temp = array (
								'id' => $id,
								'label' => 'Query',
								'terms' => array (
										array (
												'term' => $termId,
												'label' => $termId 
										) 
								) 
						);
						break;
					
					case 'target' :
						$temp = array (
								'id' => $id,
								'label' => 'Target',
								'terms' => array (
										array (
												'term' => $termId,
												'label' => $termId 
										) 
								) 
						);
						break;
					
					case 'workspace' :
						$temp = array (
								'id' => $id,
								'label' => 'Workspace',
								'terms' => array (
										array (
												'term' => $termId,
												'label' => $termId 
										) 
								) 
						);
						break;
					case 'navigation' :
					default :
						$regex = '/^[0-9a-z]{24}$/';
						if (preg_match ( $regex, $id )) { // Taxonomy facet use
						                               // mongoID
							$vocabularyItem = Manager::getService ( 'Taxonomy' )->findById ( $id );
							
							$temp = array (
									'id' => $id,
									'label' => $vocabularyItem ['name'] 
							);
							
							foreach ( $termId as $term ) {
								$termItem = $taxonomyTermsService->findById ( $term );
								$temp ['terms'] [] = array (
										'term' => $term,
										'label' => $termItem ['text'] 
								);
							}
						} else {
							// faceted field
							$temp = array (
									'id' => $id,
									'label' => $id 
							);
							foreach ( $termId as $term ) {
								$temp ['terms'] [] = array (
										'term' => $term,
										'label' => $term 
								);
							}
						}
						
						break;
				}
				
				$result ['activeFacets'] [] = $temp;
			}
		}
		
		return ($result);
	}
	
	/**
	 * get autocomplete suggestion
	 *
	 * @param array $params
	 *        	search parameters : query
	 * @return array
	 */
	public function suggest(array $params) {
		
		// init response
		$response = array ();
		
		// get params
		$this->_params = $params;
		
		// get current user language
		$currentLocale = Manager::getService ( 'CurrentLocalization' )->getCurrentLocalization ();
		
		// query
		$query = array (
				'autocomplete' => array (
						'text' => $this->_params ['query'],
						'completion' => array (
								'field' => 'autocomplete_' . $currentLocale 
						) 
				) 
		);
		
		$nonlocalizedquery = array (
				'autocomplete' => array (
						'text' => $this->_params ['query'],
						'completion' => array (
								'field' => 'autocomplete_nonlocalized' 
						) 
				) 
		);
		
		// Get search client
		$client = $this->_client;
		
		// get suggest from content
		
		$path = self::$_content_index->getName () . '/_suggest';
		$suggestion = $client->request ( $path, 'GET', $query );
		$responseArray = $suggestion->getData ()["autocomplete"][0]["options"];
		
		// get suggest from dam
		$path = self::$_dam_index->getName () . '/_suggest';
		$suggestion = $client->request ( $path, 'GET', $query );
		if (isset ( $suggestion->getData ()["autocomplete"][0]["options"] )) {
			$responseArray = array_merge ( $responseArray, $suggestion->getData ()["autocomplete"][0]["options"] );
		}
		
		// get suggest from user
		$path = self::$_user_index->getName () . '/_suggest';
		$suggestion = $client->request ( $path, 'GET', $nonlocalizedquery );
		if (isset ( $suggestion->getData ()["autocomplete"][0]["options"] )) {
			$responseArray = array_merge ( $responseArray, $suggestion->getData ()["autocomplete"][0]["options"] );
		}
		
		foreach ( $responseArray as $suggest ) {
			$response [] = $suggest;
		}
		return $response;
	}
	
	/**
	 *
	 * @param field_type $_isFrontEnd        	
	 */
	public static function setIsFrontEnd($_isFrontEnd) {
		DataSearch::$_isFrontEnd = $_isFrontEnd;
	}
	protected function searchLabel($array, $key, $value) {
		$results = array ();
		
		if (is_array ( $array )) {
			if (isset ( $array [$key] ) && $array [$key] == $value)
				$results [] = $array;
			
			foreach ( $array as $subarray )
				$results = array_merge ( $results, $this->searchLabel ( $subarray, $key, $value ) );
		}
		
		return $results;
	}
}
