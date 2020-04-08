<?php
/**
 * AK: Extended class to help build URLs and forms in the view based on search
 * settings.
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
 * @package  Search
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki Wiki
 */
namespace AkSearch\Search;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;
use VuFindSearch\Query\QueryGroup;

/**
 * AK: Extending class to help build URLs and forms in the view based on search
 * settings.
 *
 * @category AKsearch
 * @package  Search
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki Wiki
 */
class UrlQueryHelper extends \VuFind\Search\UrlQueryHelper
{
    /**
     * Current query object
     *
     * @var AbstractQuery
     */
    protected $queryObject;

    /**
     * Adjust the internal query array based on the query object.
     * 
     * AK: Add 'lookfor' parameter for empty searches.
     *
     * @return void
     */
    protected function regenerateSearchQueryParams()
    {
        $this->clearSearchQueryParams();
        if ($this->isQuerySuppressed()) {
            return;
        }
        if ($this->queryObject instanceof QueryGroup) {
            $this->urlParams['join'] = $this->queryObject->getOperator();
            foreach ($this->queryObject->getQueries() as $i => $current) {
                if ($current instanceof QueryGroup) {
                    $operator = $current->isNegated()
                        ? 'NOT' : $current->getOperator();
                    $this->urlParams['bool' . $i] = [$operator];
                    foreach ($current->getQueries() as $inner) {
                        if (!isset($this->urlParams['lookfor' . $i])) {
                            $this->urlParams['lookfor' . $i] = [];
                        }
                        if (!isset($this->urlParams['type' . $i])) {
                            $this->urlParams['type' . $i] = [];
                        }
                        $this->urlParams['lookfor' . $i][] = $inner->getString();
                        $this->urlParams['type' . $i][] = $inner->getHandler();
                        if (null !== ($op = $inner->getOperator())) {
                            // We want the op and lookfor parameters to align
                            // with each other; let's backfill empty op values
                            // if there aren't enough in place already.
                            $expectedOps
                                = count($this->urlParams['lookfor' . $i]) - 1;
                            while (
                                count($this->urlParams['op' . $i] ?? [])
                                < $expectedOps
                            ) {
                                $this->urlParams['op' . $i][] = '';
                            }
                            $this->urlParams['op' . $i][] = $op;
                        }
                    }
                }
            }
        } elseif ($this->queryObject instanceof Query) {
            $search = $this->queryObject->getString();
            if (!empty($search)) {
                $this->urlParams[$this->getBasicSearchParam()] = $search;
            }
            $type = $this->queryObject->getHandler();
            if (!empty($type)) {
                $this->urlParams['type'] = $type;
                // AK: Added 'lookfor' parameter for empty searches. If we don't do
                // that, the search jumps back to an "AllFields" search when clicking
                // on a pagination link and having an empty search, even if another
                // search scope was selected in the drop-down-field next to the
                // search box. 
                $this->urlParams['lookfor'] = $this->urlParams['lookfor'] ?? '';
            }
        }
    }

}
