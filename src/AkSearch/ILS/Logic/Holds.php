<?php
/**
 * AK: Extend Hold Logic Class
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
 * @package  ILS_Logic
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 * 
 */
namespace AkSearch\ILS\Logic;

use VuFind\Exception\ILS as ILSException;

/**
 * AK: Extending Hold Logic Class
 *
 * @category AKsearch
 * @package  ILS_Logic
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Holds extends \VuFind\ILS\Logic\Holds
{

    /**
     * Public method for getting item holdings from the catalog and selecting which
     * holding method to call
     *
     * @param string $id  A Bib ID
     * @param array  $ids A list of Source Records (if catalog is for a consortium)
     *
     * @return array A sorted results set
     */
    public function getHoldings($id, $ids = null)
    {
        $holdings = [];

        // Get Holdings Data
        if ($this->catalog) {
            // Retrieve stored patron credentials; it is the responsibility of the
            // controller and view to inform the user that these credentials are
            // needed for hold data.
            try {
                $patron = $this->ilsAuth->storedCatalogLogin();

                // Does this ILS Driver handle consortial holdings?
                $config = $this->catalog->checkFunction(
                    'Holds', compact('id', 'patron')
                );
            } catch (ILSException $e) {
                $patron = false;
                $config = [];
            }

            if (isset($config['consortium']) && $config['consortium'] == true) {
                $result = $this->catalog->getConsortialHoldings(
                    $id, $patron ? $patron : null, $ids
                );
            } else {
                $result = $this->catalog->getHolding($id, $patron ? $patron : null);
            }

            $grb = 'getRequestBlocks';
            $blocks
                = $patron && $this->catalog->checkCapability($grb, compact('patron'))
                ? $this->catalog->getRequestBlocks($patron) : false;

            $mode = $this->catalog->getHoldsMode();

            if ($mode == "disabled") {
                $holdings = $this->standardHoldings($result);
            } elseif ($mode == "driver") {
                $holdings = $this->driverHoldings($result, $config, !empty($blocks));
            } else {
                $holdings = $this->generateHoldings($result, $mode, $config);
            }

            $holdings = $this->processStorageRetrievalRequests(
                $holdings, $id, $patron, !empty($blocks)
            );
            $holdings = $this->processILLRequests(
                $holdings, $id, $patron, !empty($blocks)
            );
        }

        return [
            'blocks' => $blocks,
            'total' => $result['total'],
            'page' => $result['page'],
            'itemLimit' => $result['itemLimit'],
            'holdings' => $this->formatHoldings($holdings),
        ];
    }

    /**
     * Get summarized driver holdings
     *
     * @param array $result  A result set returned from a driver
     *
     * @return array         A sorted result set
     */
    public function summarizedDriverHoldings($result)
    {
        $holdings = [];
        if ($summarizedHoldings = $result['summarizedHoldings']) {
            foreach ($summarizedHoldings as $copy) {
                $locSuppress = false;
                $locExtName = null;
                $libCode = $copy['library'] ?? null;
                $locCode = $copy['location'] ?? null;

                // Get location data if driver has the possibility to do so
                if ($this->catalog->checkCapability('getLocationData', [$libCode,
                    $locCode])) {
                    $loc = $this->catalog->getDriver()->getLocationData($libCode,
                        $locCode);

                    $locExtName = $loc['external_name'] ?? null;
                    $locSuppress = ($loc['suppress_from_publishing'] == 'true')
                        ? true
                        : false;
                }

                // Check if certain locations should be hidden
                $show = !in_array($copy['location'], $this->hideHoldings)
                    && !$locSuppress;

                if ($show) {
                    // Add external location name to holdings data
                    $copy['location_external_name'] = $locExtName ?? $locCode
                        ?? null;

                    // Group holdings
                    $groupKey = $this->getHoldingsGroupKey($copy);
                    $holdings[$groupKey][] = $copy;
                }
            }
        }
        
        return $holdings;
    }

    /**
     * Support method to rearrange the summarized holdings array for displaying
     * convenience.
     *
     * @param array $holdings An associative array of location => item array
     *
     * @return array          An associative array keyed by location with each
     * entry being an array with 'notes', 'summary' and 'items' keys.  The 'notes'
     * and 'summary' arrays are note/summary information collected from within the
     * items.
     */
    public function formatSummarizedHoldings($summarizedHoldings)
    {
        $retVal = [];
        foreach ($summarizedHoldings as $groupKey => $hols) {
            $retVal[$groupKey] = [
                'holdings' => $hols,
                'location' => $hols[0]['location'] ?? '',
                'location_external_name' => $hols[0]['location_external_name'] ?? ''
            ];
        }

        return $retVal;
    }

    /**
     * Support method to rearrange the holdings array for displaying convenience.
     *
     * @param array $holdings An associative array of location => item array
     *
     * @return array          An associative array keyed by location with each
     * entry being an array with 'notes', 'summary' and 'items' keys.  The 'notes'
     * and 'summary' arrays are note/summary information collected from within the
     * items.
     */
    protected function formatHoldings($holdings)
    {
        $retVal = [];

        $textFieldNames = $this->catalog->getHoldingsTextFieldNames();

        foreach ($holdings as $groupKey => $items) {
            $retVal[$groupKey] = [
                'items' => $items,
                'location' => $items[0]['location'] ?? '',
                'location_external_name' => $items[0]['location_external_name']
                    ?? 'UNASSIGNED',
                'locationhref' => $items[0]['locationhref'] ?? '',
                // AK: Added locationtext
                'locationtext' => $items[0]['locationtext'] ?? ''
            ];
            // Copy all text fields from the item to the holdings level
            foreach ($items as $item) {
                foreach ($textFieldNames as $fieldName) {
                    if (in_array($fieldName, ['notes', 'holdings_notes'])) {
                        if (empty($item[$fieldName])) {
                            // begin aliasing
                            if ($fieldName == 'notes'
                                && !empty($item['holdings_notes'])
                            ) {
                                // using notes as alias for holdings_notes
                                $item[$fieldName] = $item['holdings_notes'];
                            } elseif ($fieldName == 'holdings_notes'
                                && !empty($item['notes'])
                            ) {
                                // using holdings_notes as alias for notes
                                $item[$fieldName] = $item['notes'];
                            }
                        }
                    }

                    if (!empty($item[$fieldName])) {
                        $targetRef = & $retVal[$groupKey]['textfields'][$fieldName];
                        foreach ((array)$item[$fieldName] as $field) {
                            if (empty($targetRef) || !in_array($field, $targetRef)) {
                                $targetRef[] = $field;
                            }
                        }
                    }
                }

                // Handle purchase history
                if (!empty($item['purchase_history'])) {
                    $targetRef = & $retVal[$groupKey]['purchase_history'];
                    foreach ((array)$item['purchase_history'] as $field) {
                        if (empty($targetRef) || !in_array($field, $targetRef)) {
                            $targetRef[] = $field;
                        }
                    }
                }
            }
        }

        return $retVal;
    }

    /**
     * Get Hold Form
     *
     * Supplies holdLogic with the form details required to place a request
     *
     * @param array  $details  An array of item data
     * @param array  $HMACKeys An array of keys to hash
     * @param string $action   The action for which the details are built
     *
     * @return array             Details for generating URL
     */
    protected function getRequestDetails($details, $HMACKeys, $action)
    {
        // Include request type in the details
        $details['requestType'] = $action;

        // Generate HMAC
        $HMACkey = $this->hmac->generate($HMACKeys, $details);

        // Add Params
        foreach ($details as $key => $param) {
            $needle = in_array($key, $HMACKeys);
            if ($needle) {
                $queryString[] = $key . "=" . urlencode($param);
            }
        }

        // AK: Add "isRecall"
        if ($details['isRecall'] ?? false) {
            $queryString[] = 'isRecall=1';
        }

        // AK: Add "library"
        if ($details['library'] ?? false) {
            $queryString[] = 'library=' . urlencode($details['library']);
        }

        // AK: Add "location"
        if ($details['location'] ?? false) {
            $queryString[] = 'location=' . urlencode($details['location']);
        }
        
        // Add HMAC
        $queryString[] = "hashKey=" . urlencode($HMACkey);
        $queryString = implode('&', $queryString);

        // Build Params
        return [
            'action' => $action, 'record' => $details['id'],
            'source' => $details['source'] ?? DEFAULT_SEARCH_BACKEND,
            'query' => $queryString, 'anchor' => "#tabnav"
        ];
    }

}
