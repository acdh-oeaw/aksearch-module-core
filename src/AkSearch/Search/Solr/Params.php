<?php
/**
 * AK: Extended Solr aspect of the Search Multi-class (Params)
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien 2021.
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
class Params extends \VuFind\Search\Solr\Params {

    use \AkSearch\Search\Params\FacetMincountTrait;
    use \AkSearch\Search\Params\FacetExcludeTermsTrait;

    /**
     * Constructor
     * 
     * AK: Initializing facet mincount and excludeTerms
     *
     * @param \VuFind\Search\Base\Options  $options      Options to use
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     * @param HierarchicalFacetHelper      $facetHelper  Hierarchical facet helper
     */
    public function __construct($options, \VuFind\Config\PluginManager $configLoader,
        \VuFind\Search\Solr\HierarchicalFacetHelper $facetHelper = null
    ) {
        parent::__construct($options, $configLoader, $facetHelper);

        // AK: Init mincount and excludeTerms settings
        $config = $configLoader->get($options->getFacetsIni());
        $this->initFacetMincountFromConfig($config->Results_Settings ?? null);
        $this->initFacetExcludeTermsFromConfig($config->Results_Settings ?? null);
    }

    /**
     * AK: Initialize facet settings for the New Items search form.
     *
     * @return void
     */
    public function initNewItemsFacets()
    {
        $this->initFacetList('NewItems', 'NewItems_Settings');
    }

    /**
     * Return current facet configurations
     * 
     * AK: Add facet.mincount and facet.excludeTerms options
     *
     * @return array $facetSet
     */
    public function getFacetSettings()
    {
        // Build a list of facets we want from the index
        $facetSet = [];

        if (!empty($this->facetConfig)) {
            $facetSet['limit'] = $this->facetLimit;

            // AK: Add mincount for facets (default is "1" if not set in facets.ini)
            $facetSet['mincount'] = $this->facetMincount;

            // AK: Add excludeTerms for facets (default is empty string if not set in
            // facets.ini)
            $facetSet['excludeTerms'] = $this->facetExcludeTerms;

            foreach (array_keys($this->facetConfig) as $facetField) {
                $fieldLimit = $this->getFacetLimitForField($facetField);
                if ($fieldLimit != $this->facetLimit) {
                    $facetSet["f.{$facetField}.facet.limit"] = $fieldLimit;
                }

                // AK: Add facet mincount per field
                $fieldMincount = $this->getFacetMincountForField($facetField);
                if ($fieldMincount != $this->facetMincount) {
                    $facetSet["f.{$facetField}.facet.mincount"] = $fieldMincount;
                }

                // AK: Add facet excludeTerms per field
                $fieldExclTerms = $this->getFacetExcludeTermsForField($facetField);
                if ($fieldExclTerms != $this->facetExcludeTerms) {
                    $facetSet["f.{$facetField}.facet.excludeTerms"]
                        = $fieldExclTerms;
                }

                $fieldPrefix = $this->getFacetPrefixForField($facetField);
                if (!empty($fieldPrefix)) {
                    $facetSet["f.{$facetField}.facet.prefix"] = $fieldPrefix;
                }
                $fieldMatches = $this->getFacetMatchesForField($facetField);
                if (!empty($fieldMatches)) {
                    $facetSet["f.{$facetField}.facet.matches"] = $fieldMatches;
                }
                if ($this->getFacetOperator($facetField) == 'OR') {
                    $facetField = '{!ex=' . $facetField . '_filter}' . $facetField;
                }
                $facetSet['field'][] = $facetField;
            }
            if ($this->facetContains != null) {
                $facetSet['contains'] = $this->facetContains;
            }
            if ($this->facetContainsIgnoreCase != null) {
                $facetSet['contains.ignoreCase']
                    = $this->facetContainsIgnoreCase ? 'true' : 'false';
            }
            if ($this->facetOffset != null) {
                $facetSet['offset'] = $this->facetOffset;
            }
            if ($this->facetPrefix != null) {
                $facetSet['prefix'] = $this->facetPrefix;
            }
            $facetSet['sort'] = $this->facetSort ?: 'count';
            if ($this->indexSortedFacets != null) {
                foreach ($this->indexSortedFacets as $field) {
                    $facetSet["f.{$field}.facet.sort"] = 'index';
                }
            }
        }
        return $facetSet;
    }

    /**
     * Initialize facet settings for the specified configuration sections.
     * 
     * AK: Initialize facet mincount
     *
     * @param string $facetList      Config section containing fields to activate
     * @param string $facetSettings  Config section containing related settings
     * @param string $cfgFile        Name of configuration to load (null to load
     * default facets configuration).
     * @param array $activeSearchTab Information about active search tab or null
     *
     * @return bool                 True if facets set, false if no settings found
     */
    protected function initFacetList($facetList, $facetSettings, $cfgFile = null,
        $activeSearchTab = null)
    {
        $config = $this->configLoader
            ->get($cfgFile ?? $this->getOptions()->getFacetsIni());

        // AK: Get id for active search tab (if any) and create the section name
        // from it for that we look in facets.ini
        $suffix = ($facetList == 'Advanced') ? '_Advanced' : '_HomePage';
        $sectionNameToUse = isset($activeSearchTab['id'])
            ? $activeSearchTab['id'] . $suffix
            : null;

        // AK: Check if the active search tab (if any) has a corresponding section
        // name for facets in facets.ini. If not, use the given $facetList value.
        $facetIniSections = array_keys($config->toArray());
        $facetList = (in_array($sectionNameToUse, $facetIniSections))
            ? $sectionNameToUse
            : $facetList;
        
        // AK: Init mincount settings
        $this->initFacetMincountFromConfig($config->$facetSettings ?? null);

        // AK: Init excludeTerms settings
        $this->initFacetExcludeTermsFromConfig($config->$facetSettings ?? null);

        // AK: Also init facet restrictions
        $this->initFacetRestrictionsFromConfig($config->$facetSettings ?? null);

        return parent::initFacetList($facetList, $facetSettings, $cfgFile);
    }

    /**
     * Initialize facet settings for the advanced search screen.
     * 
     * AK: Get custom facets for active search tab if there is one.
     * 
     * @param array $activeSearchTab Information about active search tab or null
     *
     * @return void
     */
    public function initAdvancedFacets($activeSearchTab = null)
    {
        // AK: Pass information about active search tab or null
        $this->initFacetList('Advanced', 'Advanced_Settings', null,
            $activeSearchTab);
    }

    /**
     * Initialize facet settings for the home page.
     * 
     * AK: Get custom facets for active search tab if there is one.
     * 
     * @param array $activeSearchTab Information about active search tab or null
     *
     * @return void
     */
    public function initHomePageFacets($activeSearchTab = null)
    {        
        // Load Advanced settings if HomePage settings are missing (legacy support):
        // AK: Pass information about active search tab or null
        if (!$this->initFacetList('HomePage', 'HomePage_Settings', null,
            $activeSearchTab)) {
            $this->initAdvancedFacets($activeSearchTab);
        }
    }
}
