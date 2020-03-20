<?php
/**
 * AK: Extend ILS aware delegator factory
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
 * @package  RecordDrivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
namespace AkSearch\RecordDriver;

use Interop\Container\ContainerInterface;

/**
 * AK: Extending ILS aware delegator factory
 *
 * @category AKsearch
 * @package  RecordDrivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class IlsAwareDelegatorFactory extends \VuFind\RecordDriver\IlsAwareDelegatorFactory
{
    /**
     * Invokes this factory.
     * AK: Using hold logic from AKsearch module instead of VuFind module.
     *
     * @param ContainerInterface $container Service container
     * @param string             $name      Service name
     * @param callable           $callback  Service callback
     * @param array|null         $options   Service options
     *
     * @return AbstractBase
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $name,
        callable $callback, array $options = null
    ) {
        $driver = call_user_func($callback);

        // Attach the ILS if at least one backend supports it:
        $ilsBackends = $this->getIlsBackends($container);
        if (!empty($ilsBackends) && $container->has(\VuFind\ILS\Connection::class)) {
            $driver->attachILS(
                $container->get(\VuFind\ILS\Connection::class),
                // AK: Use hold logic from AKsearch module
                $container->get(\AkSearch\ILS\Logic\Holds::class),
                $container->get(\VuFind\ILS\Logic\TitleHolds::class)
            );
            $driver->setIlsBackends($ilsBackends);
        }

        return $driver;
    }

}
