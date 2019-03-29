<?php
/**
 * AK: Extends default DynamicRoleProviderFactory.
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
 * @package  Authorization
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:permission_providers Wiki
 */

namespace AkSearch\Role;

use Interop\Container\ContainerInterface;

/**
 * AK: Extending DynamicRoleProviderFactory
 *
 * @category AKsearch
 * @package  Authorization
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:permission_providers Wiki
 *
 */
class DynamicRoleProviderFactory extends \VuFind\Role\DynamicRoleProviderFactory
{
    
    /**
     * AK: Create and return the exteded plugin manager in AkSearch\Role\PermissionProvider\PluginManager.
     *     It includes the alias and factory for the "usergroup" permission.
     *
     * @param ContainerInterface $serviceLocator Service locator
     * @param array              $rbacConfig     ZfcRbac configuration
     *
     * @return PermissionProvider\PluginManager
     */
    protected function getPermissionProviderPluginManager(
        ContainerInterface $serviceLocator, array $rbacConfig
    ) {
        $pm = new PermissionProvider\PluginManager(
            $serviceLocator,
            $rbacConfig['vufind_permission_provider_manager']
        );
        return $pm;
    }

}
