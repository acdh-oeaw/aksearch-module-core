<?php
/**
 * AK: Extended Table Definition for search
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
 * @package  Db_Table
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
namespace AkSearch\Db\Table;

/**
 * AK: Extending Table Definition for search
 *
 * @category AKsearch
 * @package  Db_Table
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class Search extends \VuFind\Db\Table\Search
{

    /**
     * AK: Get all scheduled searches for a given user
     *
     * @param string $userId A user ID
     * 
     * @return array Array of VuFind\Db\Row\Search objects.
     */
    public function getScheduledSearchesForUser($userId)
    {
        $callback = function ($select) use ($userId) {
            $select->where->equalTo('saved', 1);
            $select->where->greaterThan('notification_frequency', 0);
            $select->where->equalTo('user_id', $userId);
        };
        return $this->select($callback);
    }
    

}
