<?php
/**
 * AK: Extended Solr FacetCache
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien 2020.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category AKsearch
 * @package  Search_Solr
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace AkSearch\Search\Solr;

/**
 * AK: Extending Solr FacetCache
 *     The facet cache is used for facets on home pages and advanced search forms.
 * 
 * @category AKsearch
 * @package  Search_Solr
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FacetCache extends \VuFind\Search\Solr\FacetCache
{
    use \AkSearch\Role\PermissionTrait;

    /**
     * Search results object.
     *
     * @var Results
     */
    protected $results;
    
    /**
     * AK: Class variable for authorization service
     *
     * @var \LmcRbacMvc\Service\AuthorizationService
     */
    protected $authService;

    /**
     * AK: Configs from facets.ini as array
     *
     * @var array
     */
    protected $facetConfigs;


    /**
     * Constructor
     * AK: Adding $authService and $facetConfig
     *
     * @param Results                               $r           Search results object
     * @param \VuFind\Cache\Manager                 $cm          Cache manager
     * @param string                                $language    Active UI language
     * @param \LmcRbacMvc\Service\AuthorizationService $authService Authorization service for checking permissions
     * @param array                                 $facetConfig Configs from facets.ini as array
     */
    public function __construct(
        Results $r,
        \VuFind\Cache\Manager $cm,
        $language = 'en',
        \LmcRbacMvc\Service\AuthorizationService $authService = null,
        array $facetConfigs = null
    ) {
        $this->results = $r;
        $this->cacheManager = $cm;
        $this->language = $language;
        $this->authService = $authService;
        $this->facetConfigs = $facetConfigs;
    }

    /**
     * Perform the actual facet lookup.
     * AK: Using permissions to show or hide facets. Configs are done in facets.ini
     *     and in permissions.ini. Will be used for home page facets and advanced
     *     search form facets.
     *
     * @param string $initMethod Name of params method to use to request facets
     *
     * @return array
     */
    protected function getFacetResults($initMethod)
    {
        // Check if we have facet results cached, and build them if we don't.
        $cache = $this->cacheManager->getCache('object', $this->getCacheNamespace());
        $params = $this->results->getParams();

        // AK: Setting a boolean variable in \AkSearch\Search\Solr\Results to true.
        //     This is important to ensure that ALL facet values are written to the
        //     cache so that ALL of them can be evaluated against permissions.
        $this->results->setWriteToCache(true);

        // Note that we need to initialize the parameters BEFORE generating the
        // cache key to ensure that the key is based on the proper settings.
        $params->$initMethod();
        $cacheKey = $this->getCacheKey();
        if (!($list = $cache->getItem($cacheKey))) {
            // Avoid a backend request if there are no facets configured by the given
            // init method.
            if (!empty($params->getFacetConfig())) {
                // We only care about facet lists, so don't get any results (this
                // improves performance):
                $params->setLimit(0);
                $list = $this->results->getFacetList();
            } else {
                $list = [];
            }
            $cache->setItem($cacheKey, $list);
        }

        // AK: Get rewritten [Permissions] configs from facets.ini
        $facetPermissionsConfigs = (isset($this->facetConfigs['Permissions'])) ? $this->getFacetPermissionsConfigs($this->facetConfigs['Permissions']) : [];

        // AK: Checking facet values for permission
        foreach ($facetPermissionsConfigs as $permissionsFieldName => $facetPermissionsConfig) {
            // Check only values in solr fields that appear in the permissions config.
            // That is to reduce loops in facets that don't have permissions configs set.
            if (array_key_exists($permissionsFieldName, $list)) {
                // Get the appropriate list. Use a reference to the array so that we
                // are able to change it later on.
                $facetDetails = &$list[$permissionsFieldName]['list'];
                foreach ($facetDetails as $arrayKey => $facetDetail) {
                    // Get the facet value
                    $facetValue = $facetDetail['value'];
                    // Check the permission for that facet value
                    $hasPermission = $this->getPermission($this->authService, $facetPermissionsConfig, $facetValue);
                    if (!$hasPermission) {
                        // If permission is not granted, unset the given facet
                        unset($facetDetails[$arrayKey]);
                    }
                }
            }
        }

        // AK: Return the list modified by permission settings
        return $list;
    }

    
}

