<?php
/**
 * AK: Factory for GetEmailAlertData AJAX handler.
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien 2021.
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

use Interop\Container\ContainerInterface;

/**
 * AK: Factory for GetEmailAlertData AJAX handler.
 *
 * @category AKsearch
 * @package  AJAX
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ajax_handlers Wiki
 */
class GetEmailAlertDataFactory
    extends \VuFind\AjaxHandler\AbstractIlsAndUserActionFactory
    implements \Laminas\ServiceManager\Factory\FactoryInterface
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        $tablePluginManager = $container->get(\VuFind\Db\Table\PluginManager::class);
        return parent::__invoke(
            $container,
            $requestedName,
            // AK: Inject some objects that we will need
            [
                $container->get(\VuFind\Search\Results\PluginManager::class),
                $container->get(\VuFind\Config\PluginManager::class)->get('config'),
                $container->get(\VuFind\Config\PluginManager::class)->get('facets'),
                $tablePluginManager->get(\AkSearch\Db\Table\Search::class)

            ]);
    }
}
