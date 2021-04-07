<?php
/**
 * AK: Usergroup class
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
 * @link     https://vufind.org/wiki/development:plugins:permission_providers Wiki
 */
namespace AkSearch\Role\PermissionProvider;
use LmcRbacMvc\Service\AuthorizationService;
use VuFind\ILS\Connection;
use VuFind\Role\PermissionProvider\PermissionProviderInterface as PermissionProviderInterface;
use VuFind\Cache\Manager as CacheManager;

/**
 * AK: Usergroup permission provider for AKsearch.
 *
 * @category AKsearch
 * @package  Authorization
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:permission_providers Wiki
 */
class Usergroup implements PermissionProviderInterface {

	use \AkSearch\ILS\Driver\AlmaTrait;

	/**
	 * Authorization service
	 *
	 * @var AuthorizationService
	 */
	protected $auth;

	/**
	 * ILS connection
	 *
	 * @var Connection
	 */
	protected $ilsConnection;

	/**
	 * Cache manager
	 *
	 * @var CacheManager
	 */
	protected $cache;
    
	/**
	 * Constructor
	 *
	 * @param AuthorizationService $authorization
	 * @param Connection $ilsConnection
	 * @param CacheManager $cache
	 */
	public function __construct(AuthorizationService $authorization, Connection $ilsConnection, CacheManager $cache) {
		$this->auth = $authorization;
		$this->ilsConnection = $ilsConnection;
		$this->cache = $cache;
	}

	/**
	 * AK: Get permission for usergroup.
	 *
	 * @param mixed $options	Options from permissions.ini
	 * 
	 * @return array 			Empty array if not permitted, array with value 'loggedin' if permitted.
	 */
	public function getPermissions($options) {
		$returnValue = [];

		// Get user details from MySQL database
		$user = $this->auth->getIdentity();

		// Check ILS connection
		if ($this->ilsConnection && $user) {

			// Get cache. Object cache is used by Alma ILS driver which saves the
			// user group (@see \VuFind\ILS\Driver\AlmaFactory)
			$cache = $this->cache->getCache('object');

			// Get usergroup from cache
			$usergroup = null;
			if (isset($cache)) {
				$patronIdKey = $this->getCleanCacheKey($user['cat_username']);
				// TODO: Why do we use the group description, not the group code?
				// $usergroupCode = $cache->getItem('Alma_User_'.$patronIdKey.'_GroupCode');
				$usergroup = $cache->getItem('Alma_User_'.$patronIdKey.'_GroupDesc');
			}

			// Obtain usergroup through Alma ILS driver if it is not cached
            if (!$usergroup) {
				// Call getMyProfile in Alma ILS driver which in turn calls the Alma API
				$profile = ($user['cat_username']) ? $this->ilsConnection->getMyProfile($user) : null;

				// Get user group from API result
				$usergroup = ($profile != null) ? $profile['group'] : null;
            }
			
			if (!$usergroup || !in_array($usergroup, (array)$options)) {
				$returnValue = [];
			} else {
				$returnValue = ['loggedin'];
			}
		}
		
		// If we got this far, we can grant the permission to the loggedin usergroup.
		return $returnValue;
	}
	
}
?>