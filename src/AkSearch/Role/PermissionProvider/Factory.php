<?php
/**
 * AK: Extends default Permission Provider Factory Class.
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

namespace AkSearch\Role\PermissionProvider;

use Zend\ServiceManager\ServiceManager;

/**
 * AK: Extending Permission Provider Factory Class
 *
 * @category AKsearch
 * @package  Authorization
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugin(s:permission_providers Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends \VuFind\Role\PermissionProvider\Factory
{
    /**
     * AK: Factory for Usergroup. Provides the "usergroup" permission.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Usergroup
     */
    public static function getUsergroup(ServiceManager $sm) {
    	return new Usergroup(
            $sm->get('ZfcRbac\Service\AuthorizationService'),
            $sm->get('VuFind\ILSConnection'),
            $sm->get('VuFind\Cache\Manager')
        );
    }
}
