<?php
/**
 * AK: Extending FacetList content block.
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
 * @package  ContentBlock
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
namespace AkSearch\ContentBlock;

/**
 * AK: Extended FacetList content block.
 *
 * @category AKsearch
 * @package  ContentBlock
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class FacetList extends \VuFind\ContentBlock\FacetList
{

    /**
     * Return context variables used for rendering the block's template.
     * 
     * AK: Pass information about active search tab (if any) to get custom facets for
     * that tab.
     * 
     * @param array|null $activeSearchTab Active search tab if there is one or null
     *
     * @return array
     */
    public function getContext($activeSearchTab = null)
    {
        $facetCache = $this->facetCacheManager->get($this->searchClassId);
        $results = $facetCache->getResults();
        $facetConfig = $this->configManager
            ->get($results->getOptions()->getFacetsIni());
        return [
            'searchClassId' => $this->searchClassId,
            'columnSize' => $this->columnSize,
            // AK: Get facet list for active search tab if we have one
            'facetList' => $facetCache->getList('HomePage', $activeSearchTab),
            'hierarchicalFacets' => $this->getHierarchicalFacets($facetConfig),
            'hierarchicalFacetSortOptions' =>
                $this->getHierarchicalFacetSortSettings($facetConfig),
            'results' => $results,
        ];
    }
}
