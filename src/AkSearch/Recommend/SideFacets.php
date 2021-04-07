<?php
/**
 * AK: Exteded SideFacets Recommendations Module
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
 * @package  Recommendations
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
namespace AkSearch\Recommend;

use VuFind\Search\Solr\HierarchicalFacetHelper;

/**
 * AK: Extending SideFacets Recommendations Module
 *
 * This class provides recommendations displaying facets beside search results
 *
 * @category AkSearch
 * @package  Recommendations
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class SideFacets extends \VuFind\Recommend\SideFacets
{
    /**
     * AK: Config for nested sliding facets
     *
     * @var string
     */
    protected $hierarchicalFacetNestingStyle;

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Configuration loader
     * @param HierarchicalFacetHelper      $facetHelper  Helper for handling
     * hierarchical facets
     */
    public function __construct(
        \VuFind\Config\PluginManager $configLoader,
        HierarchicalFacetHelper $facetHelper = null
    ) {
        parent::__construct($configLoader);
        $this->hierarchicalFacetHelper = $facetHelper;
    }

    /**
     * Store the configuration of the recommendation module.
     * 
     * AK: Get nesting style for hierarchical facets.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        // Parse the additional settings:
        $settings = explode(':', $settings);
        $mainSection = empty($settings[0]) ? 'Results' : $settings[0];
        $checkboxSection = $settings[1] ?? false;
        $iniName = $settings[2] ?? 'facets';

        // Load the desired facet information...
        $config = $this->configLoader->get($iniName);

        // All standard facets to display:
        $this->mainFacets = isset($config->$mainSection) ?
            $config->$mainSection->toArray() : [];

        // Load boolean configurations:
        $this->loadBooleanConfigs($config, array_keys($this->mainFacets));

        // Get a list of fields that should be displayed as ranges rather than
        // standard facet lists.
        if (isset($config->SpecialFacets->dateRange)) {
            $this->dateFacets = $config->SpecialFacets->dateRange->toArray();
        }
        if (isset($config->SpecialFacets->fullDateRange)) {
            $this->fullDateFacets = $config->SpecialFacets->fullDateRange->toArray();
        }
        if (isset($config->SpecialFacets->genericRange)) {
            $this->genericRangeFacets
                = $config->SpecialFacets->genericRange->toArray();
        }
        if (isset($config->SpecialFacets->numericRange)) {
            $this->numericRangeFacets
                = $config->SpecialFacets->numericRange->toArray();
        }

        // Checkbox facets:
        if (substr($checkboxSection, 0, 1) == '~') {
            $checkboxSection = substr($checkboxSection, 1);
            $flipCheckboxes = true;
        }
        $this->checkboxFacets
            = ($checkboxSection && isset($config->$checkboxSection))
            ? $config->$checkboxSection->toArray() : [];
        if (isset($flipCheckboxes) && $flipCheckboxes) {
            $this->checkboxFacets = array_flip($this->checkboxFacets);
        }

        // Show more settings:
        if (isset($config->Results_Settings->showMore)) {
            $this->showMoreSettings
                = $config->Results_Settings->showMore->toArray();
        }
        if (isset($config->Results_Settings->showMoreInLightbox)) {
            $this->showInLightboxSettings
                = $config->Results_Settings->showMoreInLightbox->toArray();
        }

        // Collapsed facets:
        if (isset($config->Results_Settings->collapsedFacets)) {
            $this->collapsedFacets = $config->Results_Settings->collapsedFacets;
        }

        // Hierarchical facets:
        if (isset($config->SpecialFacets->hierarchical)) {
            $this->hierarchicalFacets
                = $config->SpecialFacets->hierarchical->toArray();
        }

        // Hierarchical facet sort options:
        if (isset($config->SpecialFacets->hierarchicalFacetSortOptions)) {
            $this->hierarchicalFacetSortOptions
                = $config->SpecialFacets->hierarchicalFacetSortOptions->toArray();
        }

        // AK: Nesting style:
        if (isset($config->SpecialFacets->hierarchicalFacetNestingStyle)) {
            $this->hierarchicalFacetNestingStyle
                = $config->SpecialFacets->hierarchicalFacetNestingStyle;
        }
    }

    /**
     * AK: Return the nesting style option for the hierarchical facets
     *
     * @return array
     */
    public function getHierarchicalFacetNestingStyle()
    {
        return $this->hierarchicalFacetNestingStyle ?: 'default';
    }

    /**
     * AK: Get all facet values for the nested sliding display
     * TODO: Add this to the hierarchical facet helper!?!?
     *
     * @param string $facet
     * @return array
     */
    public function getFullHierarchicalFacets($facet, $facetList, $sort) {

        $this->hierarchicalFacetHelper->sortFacetList($facetList, $sort);
        $facets = $this->hierarchicalFacetHelper->buildFacetArray(
            $facet, $facetList, $this->results->getUrlQuery(), false
        );

        return compact('facets');
    }

}
