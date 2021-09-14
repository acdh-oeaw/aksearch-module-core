<?php
/**
 * AK: Extended Solr aspect of the Search Multi-class (Options)
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
 * AK: Extending Solr Search Options
 *     Just extending \VuFind\Search\Solr\Options as AkSearch\Search\Solr\Options
 *     (especially with the "AkSearch" namespace!) is demanded in
 *     \VuFind\Search\Params\ParamsFactory, which is in turn called in 
 *     \VuFind\Search\Solr\ParamsFactory when executing return parent::__invoke(...).
 *
 * @category AKsearch
 * @package  Search_Solr
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:search_objects Wiki
 */
class Options extends \VuFind\Search\Solr\Options {

    /**
     * Search tab handlers for basic search
     *
     * @var array
     */
    protected $basicSearchTabHandlers = [];

    /**
     * Search tab handlers for advanced search
     *
     * @var array
     */
    protected $advSearchTabHandlers = [];

    /**
     * Constructor
     *
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct(\VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($configLoader);
        $searchSettings = $configLoader->get($this->searchIni);
        if (isset($searchSettings->General->default_limit)) {
            $this->defaultLimit = $searchSettings->General->default_limit;
        }
        if (isset($searchSettings->General->limit_options)) {
            $this->limitOptions
                = explode(",", $searchSettings->General->limit_options);
        }
        if (isset($searchSettings->General->default_sort)) {
            $this->defaultSort = $searchSettings->General->default_sort;
        }
        if (isset($searchSettings->General->empty_search_relevance_override)) {
            $this->emptySearchRelevanceOverride
                = $searchSettings->General->empty_search_relevance_override;
        }
        if (isset($searchSettings->DefaultSortingByType)
            && count($searchSettings->DefaultSortingByType) > 0
        ) {
            foreach ($searchSettings->DefaultSortingByType as $key => $val) {
                $this->defaultSortByHandler[$key] = $val;
            }
        }
        if (isset($searchSettings->RSS->sort)) {
            $this->rssSort = $searchSettings->RSS->sort;
        }
        if (isset($searchSettings->General->default_handler)) {
            $this->defaultHandler = $searchSettings->General->default_handler;
        }
        if (isset($searchSettings->General->retain_filters_by_default)) {
            $this->retainFiltersByDefault
                = $searchSettings->General->retain_filters_by_default;
        }
        if (isset($searchSettings->General->default_filters)) {
            $this->defaultFilters = $searchSettings->General->default_filters
                ->toArray();
        }
        if (isset($searchSettings->General->display_versions)) {
            $this->displayRecordVersions
                = $searchSettings->General->display_versions;
        }

        // Result limit:
        if (isset($searchSettings->General->result_limit)) {
            $this->resultLimit = $searchSettings->General->result_limit;
        }
        if (isset($searchSettings->Basic_Searches)) {
            foreach ($searchSettings->Basic_Searches as $key => $value) {
                $this->basicHandlers[$key] = $value;
            }
        }
        // AK: Get basic search handlers for filtered Solr search tabs if we have
        // some.
        $searchesIniSections = array_keys($searchSettings->toArray());
        $matchingBasicSections = preg_grep('/^Solr:.*_Basic_Searches$/',
            $searchesIniSections);
        if (!empty($matchingBasicSections)) {
            foreach($matchingBasicSections as $matchingBasicSection) {
                foreach ($searchSettings[$matchingBasicSection] as $key => $value) {
                    $this->basicSearchTabHandlers[$matchingBasicSection][$key]
                        = $value;
                }
            }
        }
        if (isset($searchSettings->Advanced_Searches)) {
            foreach ($searchSettings->Advanced_Searches as $key => $value) {
                $this->advancedHandlers[$key] = $value;
            }
        }
        // AK: Get advanced search handlers for filtered Solr search tabs if we have
        // some.
        $searchesIniSections = array_keys($searchSettings->toArray());
        $matchingAdvSections = preg_grep('/^Solr:.*_Advanced_Searches$/',
            $searchesIniSections);
        if (!empty($matchingAdvSections)) {
            foreach($matchingAdvSections as $matchingAdvSection) {
                foreach ($searchSettings[$matchingAdvSection] as $key => $value) {
                    $this->advSearchTabHandlers[$matchingAdvSection][$key] = $value;
                }
            }
        }

        // Load sort preferences (or defaults if none in .ini file):
        if (isset($searchSettings->Sorting)) {
            foreach ($searchSettings->Sorting as $key => $value) {
                $this->sortOptions[$key] = $value;
            }
        } else {
            $this->sortOptions = ['relevance' => 'sort_relevance',
                'year' => 'sort_year', 'year asc' => 'sort_year asc',
                'callnumber-sort' => 'sort_callnumber', 'author' => 'sort_author',
                'title' => 'sort_title'];
        }

        // Set up views
        $this->initViewOptions($searchSettings);

        // Load list view for result (controls AJAX embedding vs. linking)
        if (isset($searchSettings->List->view)) {
            $this->listviewOption = $searchSettings->List->view;
        }

        // Load facet preferences
        $facetSettings = $configLoader->get($this->facetsIni);
        if (isset($facetSettings->Advanced_Settings->translated_facets)
            && count($facetSettings->Advanced_Settings->translated_facets) > 0
        ) {
            $this->setTranslatedFacets(
                $facetSettings->Advanced_Settings->translated_facets->toArray()
            );
        }
        if (isset($facetSettings->Advanced_Settings->delimiter)) {
            $this->setDefaultFacetDelimiter(
                $facetSettings->Advanced_Settings->delimiter
            );
        }
        if (isset($facetSettings->Advanced_Settings->delimited_facets)
            && count($facetSettings->Advanced_Settings->delimited_facets) > 0
        ) {
            $this->setDelimitedFacets(
                $facetSettings->Advanced_Settings->delimited_facets->toArray()
            );
        }
        if (isset($facetSettings->Advanced_Settings->special_facets)) {
            $this->specialAdvancedFacets
                = $facetSettings->Advanced_Settings->special_facets;
        }
        if (isset($facetSettings->SpecialFacets->hierarchical)) {
            $this->hierarchicalFacets
                = $facetSettings->SpecialFacets->hierarchical->toArray();
        }

        if (isset($facetSettings->SpecialFacets->hierarchicalFacetSeparators)) {
            $this->hierarchicalFacetSeparators = $facetSettings->SpecialFacets
                ->hierarchicalFacetSeparators->toArray();
        }

        // Load Spelling preferences
        $config = $configLoader->get($this->mainIni);
        if (isset($config->Spelling->enabled)) {
            $this->spellcheck = $config->Spelling->enabled;
        }

        // Turn on first/last navigation if configured:
        if (isset($config->Record->first_last_navigation)
            && $config->Record->first_last_navigation
        ) {
            $this->firstlastNavigation = true;
        }

        // Turn on highlighting if the user has requested highlighting or snippet
        // functionality:
        $highlight = !isset($searchSettings->General->highlighting)
            ? false : $searchSettings->General->highlighting;
        $snippet = !isset($searchSettings->General->snippets)
            ? false : $searchSettings->General->snippets;
        if ($highlight || $snippet) {
            $this->highlight = true;
        }

        // Load autocomplete preferences:
        $this->configureAutocomplete($searchSettings);

        // Load shard settings
        if (isset($searchSettings->IndexShards)
            && !empty($searchSettings->IndexShards)
        ) {
            foreach ($searchSettings->IndexShards as $k => $v) {
                $this->shards[$k] = $v;
            }
            // If we have a default from the configuration, use that...
            if (isset($searchSettings->ShardPreferences->defaultChecked)
                && !empty($searchSettings->ShardPreferences->defaultChecked)
            ) {
                $defaultChecked
                    = is_object($searchSettings->ShardPreferences->defaultChecked)
                    ? $searchSettings->ShardPreferences->defaultChecked->toArray()
                    : [$searchSettings->ShardPreferences->defaultChecked];
                foreach ($defaultChecked as $current) {
                    $this->defaultSelectedShards[] = $current;
                }
            } else {
                // If no default is configured, use all shards...
                $this->defaultSelectedShards = array_keys($this->shards);
            }
            // Apply checkbox visibility setting if applicable:
            if (isset($searchSettings->ShardPreferences->showCheckboxes)) {
                $this->visibleShardCheckboxes
                    = $searchSettings->ShardPreferences->showCheckboxes;
            }
        }
    }

    /**
     * AK: Getter for basic search tab handlers.
     *
     * @return array
     */
    public function getBasicSearchTabHandlers()
    {
        return $this->basicSearchTabHandlers;
    }

    /**
     * AK: Getter for advanced search tab handlers.
     *
     * @return array
     */
    public function getAdvSearchTabHandlers()
    {
        return $this->advSearchTabHandlers;
    }    

}
