<?php
/**
 * AK: Extended factory for Solr search results objects.
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
 * @package  Search_Solr
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace AkSearch\Search\Solr;

use Interop\Container\ContainerInterface;

/**
 * AK: Extending factory for Solr search results objects.
 *
 * @category AKsearch
 * @package  Search_Solr
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ResultsFactory extends \VuFind\Search\Solr\ResultsFactory
{
    /**
     * AK: Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        // AK: Create authorization service
        $authService = $container->get(\LmcRbacMvc\Service\AuthorizationService::class);
        
        // AK: Get facets.ini configs
        $configLoader = $container->get(\VuFind\Config\PluginManager::class);
        $facetConfigs = $configLoader->get('facets')->toArray();

        // AK: Create the options array
        $options[] = $authService;
        $options[] = $facetConfigs;

        $solr = parent::__invoke($container, $requestedName, $options);
        $config = $configLoader->get('config');
        $solr->setSpellingProcessor(
            new \VuFind\Search\Solr\SpellingProcessor($config->Spelling ?? null)
        );
        $solr->setHierarchicalFacetHelper(
            $container->get(\VuFind\Search\Solr\HierarchicalFacetHelper::class)
        );
        return $solr;
    }
}
