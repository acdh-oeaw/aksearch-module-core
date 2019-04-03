<?php
/**
 * AK: Extended Solr FacetCache Factory
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
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace AkSearch\Search\Solr;

use Interop\Container\ContainerInterface;

/**
 * AK: Extending Solr FacetCache Factory.
 * 
 * @category AKsearch
 * @package  Search_Solr
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FacetCacheFactory extends \VuFind\Search\Solr\FacetCacheFactory
{
    /**
     * Create an object
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
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $parts = explode('\\', $requestedName);
        $requestedNamespace = $parts[count($parts) - 2];
        $results = $this->getResults($container, $requestedNamespace);
        $cacheManager = $container->get('VuFind\Cache\Manager');
        $language = $container->get('Zend\Mvc\I18n\Translator')->getLocale();

        // AK: Create authorization service and pass it to \AkSearch\Search\Solr\FacetCache
        $authService = $container->get('ZfcRbac\Service\AuthorizationService');

        // AK: Get facets.ini configs and pass it to \AkSearch\Search\Solr\FacetCache
        $configLoader = $container->get('VuFind\Config\PluginManager');
        $facetConfigs = $configLoader->get('facets')->toArray();

        return new $requestedName($results, $cacheManager, $language, $authService, $facetConfigs);
    }
}
