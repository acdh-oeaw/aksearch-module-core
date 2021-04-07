<?php
/**
 * AK: Extended Browse Module Controller
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

//use VuFind\Exception\Forbidden as ForbiddenException;
use Laminas\Config\Config;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * AK: Extending default BrowseController Class
 *     Added possibility to use custom solr fields for browsing
 *
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class BrowseController extends \VuFind\Controller\BrowseController
{
    /**
     * AK: Get Custom solrfield config
     *
     * @var \Laminas\Config\Config
     */
    protected $solrfield;

    public function __construct(ServiceLocatorInterface $sm, Config $config)
    {
        parent::__construct($sm, $config);

        // Get solrfield config for use below
        $this->solrfield = $this->config->Browse->solrfield;
    }

    /**
     * Get array with two values: a filter name and a secondary list based on facets
     * 
     * AK: Use custom solrfield if configured
     *
     * @param string $facet the facet we need the contents of
     *
     * @return array
     */
    protected function getSecondaryList($facet)
    {
        $category = $this->getCategory();
        switch ($facet) {
        case 'alphabetical':
            return ['', $this->getAlphabetList()];
        case 'dewey':
            return [
                    'dewey-tens', $this->quoteValues(
                        $this->getFacetList('dewey-hundreds', $category, 'index')
                    )
                ];
        case 'lcc':
            return [
                    'callnumber-first', $this->quoteValues(
                        $this->getFacetList('callnumber-first', $category, 'index')
                    )
                ];
        case 'topic':
            $solrfield = $this->solrfield['topic'] ?? 'topic_facet';
            return [
                    $solrfield, $this->quoteValues(
                        $this->getFacetList($solrfield, $category)
                    )
                ];
        case 'genre':
            $solrfield = $this->solrfield['genre'] ?? 'genre_facet';
            return [
                $solrfield, $this->quoteValues(
                        $this->getFacetList($solrfield, $category)
                    )
                ];
        case 'region':
            $solrfield = $this->solrfield['region'] ?? 'geographic_facet';
            return [
                $solrfield, $this->quoteValues(
                        $this->getFacetList($solrfield, $category)
                    )
                ];
        case 'era':
            $solrfield = $this->solrfield['era'] ?? 'era_facet';
            return [
                $solrfield, $this->quoteValues(
                        $this->getFacetList($solrfield, $category)
                    )
                ];
        }
    }

    /**
     * Get the facet search term for an action
     * 
     * AK: Use custom solrfield if configured
     *
     * @param string $action action to be translated
     *
     * @return string
     */
    protected function getCategory($action = null)
    {
        if ($action == null) {
            $action = $this->getCurrentAction();
        }
        switch (strtolower($action)) {
        case 'alphabetical':
            return $this->getCategory();
        case 'dewey':
            return 'dewey-hundreds';
        case 'lcc':
            return 'callnumber-first';
        case 'author':
            return 'author_facet';
        case 'topic':
            return $this->solrfield['topic'] ?? 'topic_facet';
        case 'genre':
            return $this->solrfield['genre'] ?? 'genre_facet';
        case 'region':
            return $this->solrfield['region'] ?? 'geographic_facet';
        case 'era':
            return $this->solrfield['era'] ?? 'era_facet';
        }
        return $action;
    }

}
