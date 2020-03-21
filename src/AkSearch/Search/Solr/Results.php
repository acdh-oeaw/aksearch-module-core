<?php
/**
 * AK: Extended Solr aspect of the Search Multi-class (Results).
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
 * AK: Extending Solr Search Results.
 *
 * @category AKsearch
 * @package  Search_Solr
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Results extends \VuFind\Search\Solr\Results
{
    use \AkSearch\Role\PermissionTrait;

    /**
     * AK: Class variable for authorization service
     *
     * @var \ZfcRbac\Service\AuthorizationService
     */
    protected $authService;

    /**
     * AK: Configs from facets.ini as array
     *
     * @var array
     */
    protected $facetConfigs;

    /**
     * AK: Indicate if facet values are written to the cache. This is important to
     *     ensure that ALL facet values are written to the cache because ALL of them
     *     must be evaluated against permissions.
     *
     * @var bool
     */
    protected $writeToCache = false;

    /**
     * Constructor
     * AK: Added $authService and $facetConfigs
     *
     * @param \VuFind\Search\Base\Params $params        Object representing user
     *                                                  search parameters.
     * @param SearchService              $searchService Search service
     * @param Loader                     $recordLoader  Record loader
     */
    public function __construct(
        Params $params,
        \VuFindSearch\Service $searchService,
        \VuFind\Record\Loader $recordLoader,
        \ZfcRbac\Service\AuthorizationService $authService = null,
        array $facetConfigs = null

    ) {
        $this->setParams($params);
        $this->searchService = $searchService;
        $this->recordLoader = $recordLoader;
        $this->authService = $authService;
        $this->facetConfigs = $facetConfigs;
    }

    /**
     * AK: Setting class variable $writeToCache. This is important to ensure that ALL
     *     facet values are written to the cache so that ALL of them can be evaluated
     *     against permissions.
     *
     * @param bool $writeToCache
     *
     * @return void
     */
    public function setWriteToCache($writeToCache)
    {
        $this->writeToCache = $writeToCache;
    }

    /**
     * Returns the stored list of facets for the last search
     * AK: Checking for permissions for single facet values. This is used for the
     *     side facets in the search results screen.
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        // Make sure we have processed the search before proceeding:
        if (null === $this->responseFacets) {
            $this->performAndProcessSearch();
        }

        // If there is no filter, we'll use all facets as the filter:
        if (null === $filter) {
            $filter = $this->getParams()->getFacetConfig();
        }

        // AK: Get rewritten [Permissions] configs from facets.ini that we can use
        //     for permission validation.
        $facetPermissionsConfigs = (isset($this->facetConfigs['Permissions'])) ? $this->getFacetPermissionsConfigs($this->facetConfigs['Permissions']) : [];

        // Start building the facet list:
        $list = [];

        // Loop through every field returned by the result set
        $fieldFacets = $this->responseFacets->getFieldFacets();
        $translatedFacets = $this->getOptions()->getTranslatedFacets();
        foreach (array_keys($filter) as $field) {
            $data = $fieldFacets[$field] ?? [];
            // Skip empty arrays:
            if (count($data) < 1) {
                continue;
            }

            // AK: Initialize some variables
            $checkForPermissions = false;
            $facetPermissionsConfig = null;

            // AK: Check if permission configs exist for the given facet field and
            //     if they should be written to the cache. This is important to
            //     ensure that ALL facet values are written to the cache so that ALL
            //     of them can be evaluated against permissions.
            if (array_key_exists($field, $facetPermissionsConfigs) && !$this->writeToCache) {
                $checkForPermissions = true;
                $facetPermissionsConfig = $facetPermissionsConfigs[$field];
            }

            // Initialize the settings for the current field
            $list[$field] = [];
            // Add the on-screen label
            $list[$field]['label'] = $filter[$field];
            // Build our array of values for this field
            $list[$field]['list']  = [];
            // Should we translate values for the current facet?
            if ($translate = in_array($field, $translatedFacets)) {
                $translateTextDomain = $this->getOptions()
                    ->getTextDomainForTranslatedFacet($field);
            }
            // Loop through values:
            foreach ($data as $value => $count) {

                // AK: Check if there are permission configs for this facet field
                if ($checkForPermissions) {
                    // Check the permission for the facet value
                    if (!$this->getPermission($this->authService, $facetPermissionsConfig, $value)) {
                        // Skip to the next iteration of the loop if permission is denied.
                        continue;
                    }
                }

                // Initialize the array of data about the current facet:
                $currentSettings = [];
                $currentSettings['value'] = $value;

                $displayText = $this->getParams()
                    ->checkForDelimitedFacetDisplayText($field, $value);

                $currentSettings['displayText'] = $translate
                    ? $this->translate("$translateTextDomain::$displayText")
                    : $displayText;
                $currentSettings['count'] = $count;
                $currentSettings['operator']
                    = $this->getParams()->getFacetOperator($field);
                $currentSettings['isApplied']
                    = $this->getParams()->hasFilter("$field:" . $value)
                    || $this->getParams()->hasFilter("~$field:" . $value);

                // Store the collected values:
                $list[$field]['list'][] = $currentSettings;
            }
        }
        return $list;
    }

}
