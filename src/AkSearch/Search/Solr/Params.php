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
class Params extends \VuFind\Search\Solr\Params {

    /**
     * AK: Initialize facet settings for the New Items search form.
     *
     * @return void
     */
    public function initNewItemsFacets()
    {
        $this->initFacetList('NewItems', 'NewItems_Settings');
    }
}
