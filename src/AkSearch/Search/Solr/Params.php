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
        var_dump('AKsearch2');
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

    /**
     * Initialize facet settings for the home page.
     *
     * @return void
     */
    /*
    public function initHomePageFacets()
    {
        // Load Advanced settings if HomePage settings are missing (legacy support):
        if (!$this->initFacetList('HomePage', 'HomePage_Settings')) {
            $this->initAdvancedFacets();
        }
    }
    */

}
