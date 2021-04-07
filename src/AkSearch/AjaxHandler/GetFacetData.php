<?php
/**
 * AK: "Get Facet Data" AJAX handler
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
 * @package  AJAX
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ajax_handlers Wiki
 */
namespace AkSearch\AjaxHandler;

use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFind\Search\Solr\HierarchicalFacetHelper;
use VuFind\Session\Settings as SessionSettings;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Stdlib\Parameters;
use Laminas\Config\Config;

/**
 * AK: "Get Facet Data" AJAX handler. Get hierarchical facet data for slide facets.
 * 
 * @category AKsearch
 * @package  AJAX
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ajax_handlers Wiki
 */
class GetFacetData extends \VuFind\AjaxHandler\GetFacetData
{
    /**
     * Hierarchical facet helper
     *
     * @var HierarchicalFacetHelper
     */
    protected $facetHelper;

    /**
     * Solr search results manager
     *
     * @var ResultsManager
     */
    protected $resultsManager;

    /**
     * AK: [NewItem] config from searches.ini
     * 
     * @var Config
     */
    protected $newItemConfig;

    /**
     * AK: "[Site]" config from config.ini
     * 
     * @var Config
     */
    protected $siteConfig;

    /**
     * Constructor
     *
     * @param SessionSettings         $ss Session settings
     * @param HierarchicalFacetHelper $fh Facet helper
     * @param ResultsManager          $rm Search results manager
     * @param Config                  $newItemConfig [NewItem] config from
     *                                searches.ini
     * @param Config                  $siteConfig "[Site]" config from config.ini
     */
    public function __construct(SessionSettings $ss, HierarchicalFacetHelper $fh,
        ResultsManager $rm, Config $newItemConfig, Config $siteConfig
    ) {
        $this->sessionSettings = $ss;
        $this->facetHelper = $fh;
        $this->resultsManager = $rm;
        $this->newItemConfig = $newItemConfig;
        $this->siteConfig = $siteConfig;
    }

    /**
     * Handle a request.
     * 
     * AK: Fix handling of requests for hierarchical facets on the "New Items" result
     * page. The facets didn't work before as some URL parameters were missing.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $facet = $params->fromQuery('facetName');
        $sort = $params->fromQuery('facetSort');
        $operator = $params->fromQuery('facetOperator');
        $backend = $params->fromQuery('source', DEFAULT_SEARCH_BACKEND);

        // AK: Get information about the calling routes in the URL so that we can
        // take actions depending on in, e. g.: Take only certain actions if the call
        // comes from the "NewItem" route.
        $route = $params->fromQuery('route');

        // AK: Get AkSearch\Search\Solr\Results object
        $results = $this->resultsManager->get($backend);

        // AK: Get AkSearch\Search\UrlQueryHelper object.
        // AK UPDATE: Storing url helper to a variable doesn't work. We always need
        //            to use it directly with "$results->getUrlQuery()".
        //$urlHelper = $results->getUrlQuery();

        // AK: Get \VuFind\Http\PhpEnvironment\Request() obect
        $request = new \VuFind\Http\PhpEnvironment\Request();
        
        if (in_array('NewItem', $route)) {                        
            // AK: Create \AkSearch\Controller\Plugin\NewItems object
            $newItems = new \AkSearch\Controller\Plugin\NewItems(
                $this->newItemConfig, $this->siteConfig);

            // AK: These parameters are especially for the "new items" search
            $range = $params->fromQuery('range');
            $department = $params->fromQuery('department');
            $filter = $params->fromQuery('filter');

            // AK: Get hidden filters from the new items
            $hiddenFilters = $newItems->getHiddenFilters();

            // AK: Add "range" for new items search if it exists. New items page only
            // works if there is a "range" parameter in the URL because the results page
            // of the new items will only be displayed if the "range" parameter exists.
            // See: \AkSearch\Controller\SearchController->newitemAction()
            if ($range) {
                $results->getUrlQuery()->setDefaultParameter('range', $range);
                $hiddenFilters[] = $newItems->getSolrFilter($range);
            }

            // AK: Add "department" from new items search if it exists
            if ($department) {
                $results->getUrlQuery()->setDefaultParameter('department', $department);
                $request->getQuery()->set('department', $department);
            }

            // AK: Add "hiddenFilters" for new items search if it exists. Set it also to
            // the request object so that the facets itself will get filtered correctly.
            // If we don't do that, the facet numbers won't be correct.
            if ($hiddenFilters) {
                $results->getUrlQuery()->setDefaultParameter('hiddenFilters', $hiddenFilters);
                $request->getQuery()->set('hiddenFilters', $hiddenFilters);
            }

            // AK: Add facets filter from new items search form to query for results page
            if (!empty($filter)) {
                $results->getUrlQuery()->setDefaultParameter('filter', $filter);
                $request->getQuery()->set('filter', $filter);
            }
        }

        $paramsObj = $results->getParams();
        $paramsObj->addFacet($facet, null, $operator === 'OR');
        // AK: We use the query parameters from the request object that we changed
        // above instead of just the query params that are comming from JS.
        //$paramsObj->initFromRequest(new Parameters($params->fromQuery()));
        $paramsObj->initFromRequest(new Parameters((array)$request->getQuery()));

        $facets = $results->getFullFieldFacets([$facet], false, -1, 'count');
        if (empty($facets[$facet]['data']['list'])) {
            $facets = [];
        } else {
            $facetList = $facets[$facet]['data']['list'];
            $this->facetHelper->sortFacetList($facetList, $sort);
            $facets = $this->facetHelper->buildFacetArray(
                $facet, $facetList, $results->getUrlQuery(), false
            );
        }

        return $this->formatResponse(compact('facets'));
    }
}
