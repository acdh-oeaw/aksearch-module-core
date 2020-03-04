<?php
/**
 * AK: Parts tab
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
 * AK: Parts tab
 *
 * @category AKsearch
 * @package  RecordTabs
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class Parts extends \VuFind\RecordTab\AbstractBase {

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'child_records';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->getRecordDriver()->tryMethod('hasChilds');
    }

    /**
     * Get the contents for display.
     *
     * @return array
     */
    public function getChilds()
    {
        // Initialize result variable
        $result = [];

        // Get child information and tweak it for better output in "parts" tab
        $childs = $this->getRecordDriver()->tryMethod('getChilds');

        if ($childs) {
            $childsByLevel = [];
            foreach ($childs as $child) {
                // Construct title
                $title = $child['partTitle'] ?? implode(
                    ' : ',
                    array_filter(
                        [($child['title'] ?? null), ($child['subTitle'] ?? null)],
                        array($this, 'filterCallback')
                    )
                );
                $title = empty(trim($title)) ? 'NoTitle' : $title;
                $level = $child['level'] ?? 'unknown';

                // Create an array grouped by level
                $childsByLevel[$level][] = [
                    'id' => $child['id'],
                    'type' => $child['type'] ?? null,
                    'title' => $title,
                    'edition' => $child['edition'] ?? null,
                    'pubYear' => $child['pubYear'] ?? null,
                    'volNo' => $child['volNo'] ?? null,
                    'issNo' => $child['issNo'] ?? null,
                    'pgNos' => $child['pgNos'] ?? null,
                    'orderNo' => $child['orderNo'] ?? null,
                    'fullTextUrl' => $child['fullTextUrl'] ?? null
                ];
            }

            // Group each level-subarray
            foreach ($childsByLevel as $level => $child) {
                // Arrays for sorting
                $pubYears = array_column($child, 'pubYear');
                $volNos = array_column($child, 'volNo');
                $issNos = array_column($child, 'issNo');
                $orderNos = array_column($child, 'orderNo');

                // Sort by multiple aspects
                array_multisort (
                    $pubYears, SORT_DESC,
                    $volNos, SORT_DESC,
                    $issNos, SORT_DESC,
                    $orderNos, SORT_DESC,
                    $child
                );

                // Add to result array
                $result[$level] = $child;
            }
        }

        return (empty($result)) ? null : $result;
    }

    /**
     * Callback function for array_filter function.
     * Default array_filter would not only filter out empty or null values, but also
     * the number "0" (as it evaluates to false). So if a value (e. g. a title) would
     * just be "0" it would not be displayed.
     *
     * @param   string $var The value of an array. In our case these are strings.
     * 
     * @return  boolean     false if $var is null or empty, true otherwise.
     */
    protected function filterCallback($var)
    {
        // Return false if $var is null or empty
        if ($var == null || trim($var) == '') {
            return false;
        }
        return true;
    }
}
