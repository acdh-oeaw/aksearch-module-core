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
        var_dump($childs);
        if ($childs) {
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

                $result[] = [
                    'title' => $title,
                    // TODO: AK: Check if we also need volume number from field 490.
                    // If yes, we need to add it in xQuery code for relation.
                    'volNo' => $child['volNo830v'] ?? $child['volNo245n'] ?? null,
                    'edition' => $child['edition'] ?? null,
                    'pubYear' => $child['pubYear'] ?? null,
                    'type' => $child['type'] ?? null,
                    'orderNo' => $child['orderNo'] ?? null,
                    'id' => $child['id']
                ];
            }

            // Arrays for sorting
            $pubYears = array_column($result, 'pubYear');
            $volNos = array_column($result, 'volNo');
            $orderNos = array_column($result, 'orderNo');

            // Sort result array by multiple aspects
            array_multisort (
                $pubYears, SORT_DESC,
                $volNos, SORT_DESC,
                $orderNos, SORT_DESC,
                $result
            );
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
