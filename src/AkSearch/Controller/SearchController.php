<?php
/**
 * AK: Extended default search controller
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
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace AkSearch\Controller;

/**
 * AK: Extending redirection of the user to the appropriate default VuFind action.
 *
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class SearchController extends \VuFind\Controller\SearchController
{

    /**
     * New item search form
     * AK: Adding facets to the new items search form
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function newitemAction()
    {
        // AK: Get the Solr results object (AkSearch\Search\Solr\Results)
        $results = $this->getResultsManager()->get('Solr');

        // AK: Get the Solr params object of the Solr result object
        //     (AkSearch\Search\Solr\Params)
        $params = $results->getParams();

        // AK: Initialize the New Items facets via the Solr params object
        $params->initNewItemsFacets();

        // AK: Initialize the facet result variable
        $facetList = [];

        // AK:  Don't get facets if there are no facets configured by the given init
        //      method.
        if (!empty($params->getFacetConfig())) {
            // AK: Set Solr result limit to 0 for getting no search results (improves
            //     performance)
            $params->setLimit(0);

            // AK: Get facet list according to the configs for New Items facets in
            //     facets.ini
            $facetList = $results->getFacetList();
        }

        // Search parameters set?  Process results.
        if ($this->params()->fromQuery('range') !== null) {
            return $this->forwardTo('Search', 'NewItemResults');
        }

        // AK: Get config for a datepicker (or empty array if no datepicker is used)
        $datePickerConfig = $this->newItems()->getDatepickerConfig();

        // AK: Add the facet list to the New Items view so that we can use it there
        //     (see themes/AkSearch/templates/search/newitem.phtml)
        return $this->createViewModel(
            [
                'fundList' => $this->newItems()->getFundList(),
                'ranges' => $this->newItems()->getRanges(),
                'facetList' => $facetList,
                'datePickerConfig' => $datePickerConfig
            ]
        );
    }


    /**
     * New item result list
     * AK: Applying facets from the new items search form
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function newitemresultsAction()
    {
        // Retrieve new item list:
        $range = $this->params()->fromQuery('range');
        $dept = $this->params()->fromQuery('department');

        // AK: Get facets filter from query sent by new items search form
        $filter = $this->params()->fromQuery('filter');
        
        // AK: Add facets filter from new items search form to query for results page
        if (!empty($filter)) {
            $this->getRequest()->getQuery()->set('filter', $filter);
        }

        // Validate the range parameter -- it should not exceed the greatest
        // configured value:
        $maxAge = $this->newItems()->getMaxAge();
        if ($maxAge > 0 && $range > $maxAge) {
            $range = $maxAge;
        }

        // Are there "new item" filter queries specified in the config file?
        // If so, load them now; we may add more values. These will be applied
        // later after the whole list is collected.
        $hiddenFilters = $this->newItems()->getHiddenFilters();

        // Depending on whether we're in ILS or Solr mode, we need to do some
        // different processing here to retrieve the correct items:
        if ($this->newItems()->getMethod() == 'ils') {
            // Use standard search action with override parameter to show results:
            $bibIDs = $this->newItems()->getBibIDsFromCatalog(
                $this->getILS(),
                $this->getResultsManager()->get('Solr')->getParams(),
                $range, $dept, $this->flashMessenger()
            );
            $this->getRequest()->getQuery()->set('overrideIds', $bibIDs);
        } else {
            // Use a Solr filter to show results:
            $hiddenFilters[] = $this->newItems()->getSolrFilter($range);
        }

        // If we found hidden filters above, apply them now:
        if (!empty($hiddenFilters)) {
            $this->getRequest()->getQuery()->set('hiddenFilters', $hiddenFilters);
        }

        // Don't save to history -- history page doesn't handle correctly:
        $this->saveToHistory = false;

        // Call rather than forward, so we can use custom template
        $view = $this->resultsAction();

        // Customize the URL helper to make sure it builds proper new item URLs
        // (check it's set first -- RSS feed will return a response model rather
        // than a view model):
        if (isset($view->results)) {
            $view->results->getUrlQuery()
                ->setDefaultParameter('range', $range)
                ->setDefaultParameter('department', $dept)
                ->setSuppressQuery(true);
        }

        // We don't want new items hidden filters to propagate to other searches:
        $view->ignoreHiddenFilterMemory = true;
        $view->ignoreHiddenFiltersInRequest = true;

        return $view;
    }

    /**
     * Results action.
     * 
     * AK: Add record banner config to view
     *
     * @return mixed
     */
    public function resultsAction()
    {
        // Get the view from the parent controller
        $view = parent::resultsAction();

        // Add the record banner config
        $view->recordBannerConfig = isset($this->getConfig()->RecordBanner)
            ? $this->getConfig()->RecordBanner->toArray()
            : null;

        // Return the view with the record banner config
        return $view;
    }
}
