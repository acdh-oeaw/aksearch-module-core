<?php

namespace AkSearch\RecordTab;

class Parts extends \VuFind\RecordTab\AbstractBase
{

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
     * AK: Callback function for array_filter function.
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
