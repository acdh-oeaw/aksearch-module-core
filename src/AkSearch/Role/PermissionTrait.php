<?php
/**
 * Permissons Trait
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
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace AkSearch\Role;

/**
 * Permissons Trait
 *
 * @category AKsearch
 * @package  Authorization
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait PermissionTrait
{

    /**
     * Check permissions for the given search value
     *
     * @param array    $permissionsConfig [Permissions] configs from searchbox.ini
     * @param string   $value             The name of the value for checking the permission
     * @return boolean                    True if permission is granted, false otherwise
     */
    protected function getPermission($authService, $permissionsConfig, $value
    )
    {
        $permissionsToCheck = [];
        foreach ($permissionsConfig as $permissionName => $permissionHanlderArray) {
            if (in_array($value
            , $permissionHanlderArray)) {
                $permissionsToCheck[] = $permissionName;
            }
        }

        if (empty($permissionsToCheck)) {
            // Return true if no permission configs are set for a search value.
            return true;
        } else {
            // If permission configs are set for a search value, check the permission status.
            foreach ($permissionsToCheck as $permissionToCheck) {
                if ($authService->isGranted($permissionToCheck)) {
                    // Return true if permission is granted.
                    return true;
                }
            }

            // Default
            return false;
        }

        // Fallback
        return true;
    }

}

?>