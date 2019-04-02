<?php
/**
 * AK: Extended Solr aspect of the Search Multi-class (Params)
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien 2019.
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
 * @link     https://vufind.org/wiki/development:plugins:search_objects Wiki
 */
namespace AkSearch\Search\Solr;


/**
 * AK: Extending Solr Search Parameters
 *
 * @category AKsearch
 * @package  Search_Solr
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:search_objects Wiki
 */
class Params extends \VuFind\Search\Solr\Params
{

    use \AkSearch\Role\PermissionTrait;

    /**
     * AK: Class variable for authorization service
     *
     * @var \ZfcRbac\Service\AuthorizationService
     */
    protected $authService;


    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options      Options to use
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     * @param HierarchicalFacetHelper      $facetHelper  Hierarchical facet helper
     */
    public function __construct($options, \VuFind\Config\PluginManager $configLoader,
        \VuFind\Search\Solr\HierarchicalFacetHelper $facetHelper = null,
        \ZfcRbac\Service\AuthorizationService $authService = null
    ) {
        parent::__construct($options, $configLoader);
        $this->facetHelper = $facetHelper;

        // AK: Get authorization service
        $this->authService = $authService;

        // AK: Get configs in permissions.ini
        $searchboxConfig = $configLoader->get('searchbox')->toArray();

        // Use basic facet limit by default, if set:
        $config = $configLoader->get($options->getFacetsIni());
        $this->initFacetLimitsFromConfig($config->Results_Settings ?? null);
        if (isset($config->LegacyFields)) {
            $this->facetAliases = $config->LegacyFields->toArray();
        }
        if (isset($config->ExtraFacetLabels)) {
            $this->extraFacetLabels = $config->ExtraFacetLabels->toArray();
        }
        if (isset($config->Results_Settings->sorted_by_index)
            && count($config->Results_Settings->sorted_by_index) > 0
        ) {
            $this->setIndexSortedFacets(
                $config->Results_Settings->sorted_by_index->toArray()
            );
        }
    }

    /**
     * Initialize facet settings for the specified configuration sections.
     *
     * @param string $facetList     Config section containing fields to activate
     * @param string $facetSettings Config section containing related settings
     * @param string $cfgFile       Name of configuration to load (null to load
     * default facets configuration).
     *
     * @return bool                 True if facets set, false if no settings found
     */
    protected function initFacetList($facetList, $facetSettings, $cfgFile = null)
    {
        $config = $this->configLoader
            ->get($cfgFile ?? $this->getOptions()->getFacetsIni());
        if (!isset($config->$facetList)) {
            return false;
        }
        if (isset($config->$facetSettings->orFacets)) {
            $orFields
                = array_map('trim', explode(',', $config->$facetSettings->orFacets));
        } else {
            $orFields = [];
        }
        foreach ($config->$facetList as $key => $value) {
            $useOr = (isset($orFields[0]) && $orFields[0] == '*')
                || in_array($key, $orFields);
            $this->addFacet($key, $value, $useOr);            
        }

        return true;
    }

}

