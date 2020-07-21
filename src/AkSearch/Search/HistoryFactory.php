<?php
/**
 * AK: Extended Search history factory
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
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace AkSearch\Search;

use Interop\Container\ContainerInterface;

/**
 * AK: Extending Search history factory.
 *
 * @category AKsearch
 * @package  Search
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HistoryFactory extends \VuFind\Search\HistoryFactory
{
    /**
     * Create an object
     * AK: Create authorization service and pass it to History class
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
        $searchTable = $container->get(\VuFind\Db\Table\PluginManager::class)
            ->get('Search');
        $resultsManager = $container
            ->get(\VuFind\Search\Results\PluginManager::class);
        $sessionId = $container->get(\Zend\Session\SessionManager::class)->getId();
        $cfg = $container->get(\VuFind\Config\PluginManager::class)->get('config');

        // AK: Create authorization service for passing it to
        // \AkSearch\Search\History below
        $authService = $container->get(\ZfcRbac\Service\AuthorizationService::class);

        // AK: Pass also the authorization service to the History class
        return new $requestedName($searchTable, $sessionId, $resultsManager, $cfg,
            $authService);
    }
}
