<?php
/**
 * AK: Extended factory for the default SOLR backend.
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
 * @package  Search
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:search_service Wiki
 */

namespace AkSearch\Search\Factory;

/**
 * AK: Extending factory for the default SOLR backend.
 *
 * @category AKsearch
 * @package  Search
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:search_service Wiki
 */
class SolrDefaultBackendFactory extends
    \VuFind\Search\Factory\SolrDefaultBackendFactory
{

    /**
     * Create the SOLR connector.
     * AK: Returning the custom Solr connector. Using multiple id fields for
     *     retrieving a sinlge record.
     *
     * @return \AkSearchSearch\Backend\Solr\Connector
     */
    protected function createConnector()
    {
        $config = $this->config->get($this->mainConfig);

        // AK: Get configuration for ID fields to use for retrieving single records
        $searchConfig = $this->config->get($this->searchConfig);
        $idFields = (
                isset($searchConfig->AkSearch->idFields)
                && !empty($searchConfig->AkSearch->idFields)
            )
            ? $searchConfig->AkSearch->idFields
            : 'id'; // Default is "id" Solr field
    	$this->uniqueKey = $idFields;

        $handlers = [
            'select' => [
                'fallback' => true,
                'defaults' => ['fl' => '*,score'],
                'appends'  => ['fq' => []],
            ],
            'terms' => [
                'functions' => ['terms'],
            ],
        ];

        foreach ($this->getHiddenFilters() as $filter) {
            array_push($handlers['select']['appends']['fq'], $filter);
        }

        $connector = new \AkSearchSearch\Backend\Solr\Connector(
            $this->getSolrUrl(),
            new \VuFindSearch\Backend\Solr\HandlerMap($handlers),
            $this->uniqueKey
        );

        $connector->setTimeout(
            isset($config->Index->timeout) ? $config->Index->timeout : 30
        );

        if ($this->logger) {
            $connector->setLogger($this->logger);
        }
        if ($this->serviceLocator->has(\VuFindHttp\HttpService::class)) {
            $connector->setProxy(
                $this->serviceLocator->get(\VuFindHttp\HttpService::class)
            );
        }
        return $connector;
    }
}
