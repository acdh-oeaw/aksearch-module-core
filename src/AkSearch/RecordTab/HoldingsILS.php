<?php
/**
 * AK: Extended holdings (ILS) tab
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
 * @package  RecordTabs
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
namespace AkSearch\RecordTab;

/**
 * AK: Extending holdings (ILS) tab
 *
 * @category AKsearch
 * @package  RecordTabs
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class HoldingsILS extends \VuFind\RecordTab\HoldingsILS {
    
    /**
     * AK: Check if this record has parent records
     *
     * @return bool
     */
    public function hasParents() {
        return $this->getRecordDriver()->tryMethod('hasParents');
    }

    /**
     * AK: Get parent records in consolidated format
     *
     * @return array|null An array of parent record information or null
     */
    public function getParents() {
        return $this->getRecordDriver()->tryMethod('getConsolidatedParents');
    }

}
