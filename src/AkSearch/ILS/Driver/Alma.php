<?php
/**
 * AK: Extends Alma ILS Driver
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
 * @package  ILS_Drivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace AkSearch\ILS\Driver;

/**
 * AK: Extending Alma ILS Driver
 *
 * @category AKsearch
 * @package  ILS_Drivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Alma extends \VuFind\ILS\Driver\Alma
{
    use AlmaTrait;

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     * 
     * AK: Setting the usergroup and usergroup code to the cache without using
     *     cache-key-generator from \VuFind\ILS\Driver\CacheTrait as this adds
     *     the class name to the cache-key which makes it hard to use the cached
     *     data from other classes.
     *
     * @param array $patron The patron array
     *
     * @return array Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $patronId = $patron['cat_username'];
        $xml = $this->makeRequest('/users/' . $patronId);
        if (empty($xml)) {
            return [];
        }
        $profile = [
            'firstname'  => (isset($xml->first_name))
                                ? (string)$xml->first_name
                                : null,
            'lastname'   => (isset($xml->last_name))
                                ? (string)$xml->last_name
                                : null,
            'group'      => (isset($xml->user_group['desc']))
                                ? (string)$xml->user_group['desc']
                                : null,
            'group_code' => (isset($xml->user_group))
                                ? (string)$xml->user_group
                                : null
        ];
        $contact = $xml->contact_info;
        if ($contact) {
            if ($contact->addresses) {
                $address = $contact->addresses[0]->address;
                $profile['address1'] =  (isset($address->line1))
                                            ? (string)$address->line1
                                            : null;
                $profile['address2'] =  (isset($address->line2))
                                            ? (string)$address->line2
                                            : null;
                $profile['address3'] =  (isset($address->line3))
                                            ? (string)$address->line3
                                            : null;
                $profile['zip']      =  (isset($address->postal_code))
                                            ? (string)$address->postal_code
                                            : null;
                $profile['city']     =  (isset($address->city))
                                            ? (string)$address->city
                                            : null;
                $profile['country']  =  (isset($address->country))
                                            ? (string)$address->country
                                            : null;
            }
            if ($contact->phones) {
                $profile['phone'] = (isset($contact->phones[0]->phone->phone_number))
                                   ? (string)$contact->phones[0]->phone->phone_number
                                   : null;
            }
        }

        // Set usergroup details to cache
        if (isset($this->cache)) {
            $patronIdKey = $this->getCleanCacheKey($patronId);
            $this->cache->setItem('Alma_User_'.$patronIdKey.'_GroupCode', $profile['group_code'] ?? null);
            $this->cache->setItem('Alma_User_'.$patronIdKey.'_GroupDesc', $profile['group'] ?? null);
        }

        return $profile;
    }

    public function getNewItems($page, $limit, $daysOld, $fundID) {
        return [];
    }

}
