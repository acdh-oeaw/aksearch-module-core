<?php
/**
 * AK: Extended Action Helper - New Items Support Methods
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
 * @package  Controller_Plugins
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace AkSearch\Controller\Plugin;

/**
 * AK: Extending action helper to perform new items-related actions
 *
 * @category AKsearch
 * @package  Controller_Plugins
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class NewItems extends \VuFind\Controller\Plugin\NewItems
{

    /**
     * Get a Solr filter to limit to the specified number of days.
     * AK: Added possibility to use a custom Solr date field for the filter.
     *
     * @param int $range Days to search
     *
     * @return string
     */
    public function getSolrFilter($range)
    {
        // AK: Get [NewItem]->solrfield config from searches.ini
        $solrFieldConf = $this->config['solrfield'];

        // AK: Check if config is set. If not, use default field "first_indexed"
        $solrField = (isset($solrFieldConf) && !empty($solrFieldConf))
                     ? $solrFieldConf
                     : 'first_indexed';

        // AK: Create Solr filter with given Solr field name
        return $solrField.':[NOW-' . $range . 'DAY TO NOW]';
    }

}
