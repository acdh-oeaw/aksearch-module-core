<?php
/**
 * AK: Permissons Trait
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
 * @package  Authorization
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace AkSearch\Role;

/**
 * AK: Permissions Trait
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
     * AK: Check permissions for the given search value
     *
     * @param \LmcRbacMvc\Service\AuthorizationService $authService
     * @param array    $permissionsConfig [Permissions] configs as array in format:
     *                                    permission.name1 =>
     *                                      array(0 => value1, 1 => value2, ...),
     *                                    permission.name2 =>
     *                                      array(...)
     *                                    ...
     * @param string   $value             The name of the value for checking the
     *                                    permission
     * 
     * @return boolean True if permission is granted, false otherwise
     */
    protected function getPermission($authService, $permissionsConfig, $value)
    {
        $permissionsToCheck = [];
        foreach ($permissionsConfig as $permissionName => $permissionHanlderArray) {
            if (in_array($value, $permissionHanlderArray)) {
                $permissionsToCheck[] = $permissionName;
            }
        }

        if (empty($permissionsToCheck)) {
            // Return true if no permission configs are set for a search value.
            return true;
        } else {
            // If permission configs are set for a search value, check the permission
            // status.
            foreach ($permissionsToCheck as $permissionToCheck) {
                //var_dump($permissionsToCheck);
                //var_dump($value);
                //var_dump($authService->isGranted($permissionToCheck));
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

    /**
     * Get an array of the values in [Permissions] section in facets.ini config
     * file. The values are rewritten to an array that can be used for checking
     * permissions.
     * 
     * @param array $facetPermissionsConfig   The raw [Permissions] configs in
     *                                        facet.ini
     *
     * @return array An array with rewritten values from [Permissions] configs in
     *               facet.ini
     */
    protected function getFacetPermissionsConfigs($facetPermissionsConfig)
    {
        $facetPermissions = [];
        foreach ($facetPermissionsConfig as $permissionName => $facetFieldsAndValues) {
            foreach ($facetFieldsAndValues as $facetFieldAndValue) {
                $splittedFacetFieldAndValue = preg_split('/\s*:\s*/', $facetFieldAndValue, 2);
                $facetField = trim($splittedFacetFieldAndValue[0]);
                $facetValue = trim($splittedFacetFieldAndValue[1]);
                $facetPermissions[$facetField][$permissionName][] = $facetValue;
            }
        }

        return $facetPermissions;
    }

}

?>