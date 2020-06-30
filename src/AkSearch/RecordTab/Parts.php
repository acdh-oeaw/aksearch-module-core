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
            // Arrays for sorting
            $pubYears = array_map(
                function($pubYear) {
                    return str_replace(array('[', ']'), '', $pubYear);
                },
                array_column($childs, 'pubYear')
            );
            $volNos = array_column($childs, 'volNo');
            $issNos = array_column($childs, 'issNo');
            $orderNos = array_column($childs, 'orderNo');

            // Sort childs array by multiple aspects
            array_multisort (
                $pubYears, SORT_DESC,
                $volNos, SORT_DESC,
                $issNos, SORT_DESC,
                $orderNos, SORT_ASC,
                $childs
            );

            // Counter for total no. of elements
            $totalNoOfChilds = 0;

            $childsGrouped = [];
            foreach ($childs as $child) {
                // Count total no. of elements
                $totalNoOfChilds++;

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

                // Merge all non-"Part" levels (= Monograph, Multivol., Serial, ...)
                // to a "Volume" level
                $level = (strpos(strtolower($level), 'part') === false)
                    ? 'volume'
                    : $level;

                $childsGrouped[$level][$child['pubYear'] ?? 'nopubyear']
                    [$child['volNo'] ?? 'novolno'][$child['issNo'] ?? 'noissno'][] =
                [
                    'id' => $child['id'],
                    'type' => $child['type'] ?? null,
                    'title' => $title,
                    'edition' => $child['edition'] ?? null,
                    'pubYear' => $child['pubYear'] ?? null,
                    'volNo' => $child['volNo'] ?? null,
                    'issNo' => $child['issNo'] ?? null,
                    'pgNos' => $child['pgNos'] ?? null,
                    'orderNo' => $child['orderNo'] ?? null,
                    'depth' => $child['depth'] ?? null,
                    'marker' => $child['marker'] ?? null,
                    'fullTextUrl' => $child['fullTextUrl'] ?? null
                ];
            }

            $result['childs'] = $childsGrouped;
            $result['total_no_of_childs'] = $totalNoOfChilds;

        }

        return (empty($result)) ? null : $result;
    }

    // TODO: Find a simpler way to flatten the array!
    public function getFlatChilds($childs, $level = null) {
        $result = [];
        $flatChilds = [];
        $totalNoOfChilds = 0;
        $yearNoOfChilds = 0;
        $volNoOfChilds = 0;
        $issNoOfChilds = 0;

        foreach ($childs as $year => $years) {
            $yearNoOfChilds = 0;
            foreach ($years as $volNo => $vols) {
                $volNoOfChilds = 0;
                foreach ($vols as $issNo => $isss) {
                    $issNoOfChilds = 0;
                    foreach ($isss as $issNo => $iss) {
                        $totalNoOfChilds++;
                        if ($level == 'year') {
                            $yearNoOfChilds++;
                            $flatChilds[$year][] = $iss;
                        } else if ($level == 'vol') {
                            $volNoOfChilds++;
                            $flatChilds[$year.'_'.$volNo][] = $iss;
                        } else if ($level == 'iss') {
                            $issNoOfChilds++;
                            $flatChilds[$year.'_'.$volNo.'_'.$issNo][] = $iss;
                        } else if ($level == 'none') {
                            $flatChilds[] = $iss;
                        }
                    }
                    if ($level == 'iss') {
                        $flatChilds[$year.'_'.$volNo.'_'.$issNo]['no_of_childs'] = $issNoOfChilds;
                    }
                }
                if ($level == 'vol') {
                    $flatChilds[$year.'_'.$volNo]['no_of_childs'] = $volNoOfChilds;
                }
            }
            if ($level == 'year') {
                $flatChilds[$year]['no_of_childs'] = $yearNoOfChilds;
            }
        }

        $result['childs'] = $flatChilds;
        $result['total_no_of_childs'] = $totalNoOfChilds;
        
        return ($result['childs'] != null && !empty($result['childs'])) ? $result : $childs;
    }

    public function getBibliographicLevel() {
        return $this->getRecordDriver()->tryMethod('getBibliographicLevel') ?? null;
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
