<?php
/**
 * Alma ILS Driver Trait
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
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace AkSearch\ILS\Driver;

/**
 * Alma ILS Driver Trait
 *
 * @category AKsearch
 * @package  ILS_Drivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait AlmaTrait {

    /**
     * Remove all special characters from a string and returns a clean string for
     * use as a cache key.
     *
     * @param string  $key A string (cache key) that could contain special characters
     *                     that should be cleaned out
     * 
     * @return string      The cleaned cache key as a string
     */
    public function getCleanCacheKey($key) {
        return preg_replace("/([^a-z0-9_\+\-])+/Di", "", $key);
    }


    /**
     * Generate a barcode value with the help of md5 hashing.
     * 
     * @param	string	$stringForHash	A string that should be unique (e. g. eMail address + timestamp) from which a barcode (hash) value will be generated
     * @return	string	The barcode value
     */

    public function generateBarcode($prefix, $length, $bcChars){
        $random = "";
        $alphaNum = $bcChars ?? "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $maxLength = strlen($alphaNum);

        for ($i=0; $i < $length; $i++) {
            $random .= $alphaNum[random_int(0, $maxLength-1)];
        }

        return $prefix.$random;
    }
}

?>