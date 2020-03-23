<?php
/**
 * AK: Extend Alma ILS Driver
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
 * @package  ILS_Drivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace AkSearch\ILS\Driver;

use SimpleXMLElement;

/**
 * AK: Extending Alma ILS Driver
 *
 * @category AKsearch
 * @package  ILS_Drivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class Alma extends \VuFind\ILS\Driver\Alma implements
    \VuFind\Db\Table\DbTableAwareInterface,
    \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use AlmaTrait;
    use \VuFind\Db\Table\DbTableAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id      The record id to retrieve the holdings for
     * @param array  $patron  Patron data
     * @param array  $options Additional options
     *
     * @return array On success an array with the key "total" containing the total
     * number of items for the given bib id, and the key "holdings" containing an
     * array of holding information each one with these keys: id, source,
     * availability, status, location, reserve, callnumber, duedate, returnDate,
     * number, barcode, item_notes, item_id, holding_id, addLink, description
     */
    public function getHolding($id, $patron = null, array $options = [])
    {
        // Prepare result array with default values. If no API result can be received
        // these will be returned.
        $results['total'] = 0;
        $results['holdings'] = [];

        // Correct copy count in case of paging
        $copyCount = $options['offset'] ?? 0;

        // Paging parameters for paginated API call. The "limit" tells the API how
        // many items the call should return at once (e. g. 10). The "offset" defines
        // the range (e. g. get items 30 to 40). With these parameters we are able to
        // use a paginator for paging through many items.
        $apiPagingParams = '';
        if ($options['itemLimit'] ?? null) {
            $apiPagingParams = 'limit=' . urlencode($options['itemLimit'])
                . '&offset=' . urlencode($options['offset'] ?? 0);
        }

        // The path for the API call. We call "ALL" available items, but not at once
        // as a pagination mechanism is used. If paging params are not set for some
        // reason, the first 10 items are called which is the default API behaviour.
        $itemsPath = '/bibs/' . urlencode($id) . '/holdings/ALL/items?'
            . $apiPagingParams
            . '&order_by=library,location,enum_a,enum_b'
            . '&direction=desc'
            . '&expand=due_date_policy,due_date';

        if ($items = $this->makeRequest($itemsPath)) {
            // Get the total number of items returned from the API call and set it to
            // a class variable. It is then used in VuFind\RecordTab\HoldingsILS for
            // the items paginator.
            $results['total'] = (int)$items->attributes()->total_record_count;

            // AK: Get texts for fulfillment units from Alma config
            $fulUnitTexts = $this->config['Holdings']['fulfillment_unit_text'] ?? null;
            
            // AK: Get texts for locations from Alma config
            $locTexts = $this->config['Holdings']['location_text'] ?? null;

            // AK: Get config for hiding items by item policy
            $hidePolicies = $this->config['Holdings']['hide_item_policy'] ?? null;

            foreach ($items->item as $item) {
                // AK: Get location data
                $library = (string)$item->item_data->library ?: null;
                $location = (string)$item->item_data->location ?: null;
                $locData = $this->getLocationData($library, $location);

                // AK: Get item policy code
                $itemPolicyCode = (string)$item->item_data->policy ?: null;

                // AK: Check if this location is suppressed from Alma. See the
                // setting "Suppress from Discovery" in the config for "Physical
                // Locations" in Alma. Also, check if the item should be hidden by
                // Alma item policy code.
                $show = (!$locData['suppress_from_publishing']
                    && !in_array($itemPolicyCode, $hidePolicies)) ?? true;

                if ($show) {
                    $fulUnit = $locData['fulfillment_unit'] ?: null;
                    $locationText = $locTexts[$location] ?? $fulUnitTexts[$fulUnit]
                        ?? null;
    
                    $number = ++$copyCount;
                    $holdingId = (string)$item->holding_data->holding_id;
                    $itemId = (string)$item->item_data->pid;
                    $barcode = (string)$item->item_data->barcode;
                    $requested = ((string)$item->item_data->requested == 'false')
                        ? false
                        : true;
                    $duedate = ($date = (string)$item->item_data->due_date)
                        ? $this->parseDate($date)
                        : null;

                    // AK: Get the number of requests
                    $noOfRequests = 0;
                    if ($requested) {
                        $requestPath = '/bibs/' . urlencode($id) . '/holdings/'
                            . urlencode($holdingId) . '/items/'
                            . urlencode($itemId) . '/requests';
                        $requestData = $this->makeRequest($requestPath);
                        $noOfRequests = (int)$requestData
                            ->attributes()['total_record_count'] ?: 0;
                    }
    
                    // AK: Check if the item is to be recalled
                    $isRecall = false;
                    if ($duedate || $requested) {
                        $isRecall = true;
                    }
    
                    if ($item->item_data->public_note != null
                        && !empty($item->item_data->public_note)
                    ) {
                        $itemNotes = [(string)$item->item_data->public_note];
                    }
    
                    if ($item->item_data->description != null
                        && !empty($item->item_data->description)
                    ) {
                        $number = (string)$item->item_data->description;
                        $description = (string)$item->item_data->description;
                    }
    
                    $results['holdings'][] = [
                        'id' => $id,
                        'source' => 'Solr',
                        'availability' => $this->getAvailabilityFromItem($item),
                        'status' => (string)$item->item_data->base_status[0]
                            ->attributes()['desc'] ?: null,
                        // AK: The key 'location' must contain the location code as
                        // this is used for configs in Alma.ini, e. g.
                        // "location_text" and "fulfillment_unit_text"
                        'location' => $location,
                        'reserve' => 'N',
                        'callnumber' => (string)$item->holding_data->call_number ?: null,
                        'callnumber_alt' => (string)$item->item_data
                            ->alternative_call_number ?: null,
                        'callnumber_tmp' => (string)$item->item_data
                            ->temp_call_number ?: null,
                        'duedate' => $duedate,
                        'returnDate' => false,
                        'requests_placed' => $noOfRequests,
                        'number' => $number,
                        'barcode' => $barcode ?: 'n/a',
                        'item_notes' => $itemNotes ?? null,
                        'item_id' => $itemId,
                        'holding_id' => $holdingId,
                        'holdtype' => 'hold',
                        'addLink' => $patron
                            ? (($locationText) ? false : 'check')
                            : false,
                        // For Alma title-level hold requests
                        'description' => $description ?? null,
                        // AK: Adding additional information
                        'library' => $library ?? null,
                        'policy_code' => $itemPolicyCode,
                        'policy_desc' => (string)$item->item_data->policy
                            ->attributes()['desc'] ?: null,
                        'isRecall' => $isRecall ?? null,
                        'fulfillment_unit' => $locData['fulfillment_unit'] ?: null,
                        'location_external_name' => $locData['external_name']
                            ?: $location
                            ?: null,
                        'locationtext' => $locationText
                    ];
                }
            }
        }

        // Fetch also digital and/or electronic inventory if configured
        $types = $this->getInventoryTypes();
        if (in_array('d_avail', $types) || in_array('e_avail', $types)) {
            // No need for physical items
            $key = array_search('p_avail', $types);
            if (false !== $key) {
                unset($types[$key]);
            }
            $statuses = $this->getStatusesForInventoryTypes((array)$id, $types);
            $electronic = [];
            foreach ($statuses as $record) {
                foreach ($record as $status) {
                    $electronic[] = $status;
                }
            }
            $results['electronic_holdings'] = $electronic;
        }

        return $results;
    }

    /**
     * Get data for all locations of a library or for a single location. The location
     * data are cached with a lifetime of 3600 seconds. After saving the data to the
     * cache the cache lifetime is reset to the saved default value.
     *
     * @param string $library   The code of a library in Alma
     * @param string $location  The code of a location in Alma (optional)
     * 
     * @return array An array with data of all locations of a given library (if
     *               $location is not set) or the data of a single location. An empty
     *               array if no results were found.
     */
    public function getLocationData($library, $location = null) {
        $libCacheKey = $library.'_Locations';
        $savedCacheLifetime = $this->cacheLifetime;
        $this->cacheLifetime = 3600;
        $locations = $this->getCachedData($libCacheKey);
        $this->cacheLifetime = $savedCacheLifetime;

        if ($locations === null && !empty($library)) {
            $locations = [];

            // Get all locations for the given library
            $locationsForLib = $this->makeRequest(
                '/conf/libraries/'.urlencode($library).'/locations'
            );

            foreach($locationsForLib->location as $loc) {
                $locations[(string)$loc->code] = [
                    'name' => (string)$loc->name ?? null,
                    'external_name' => (string)$loc->external_name ?? null,
                    'type' => (string)$loc->type ?? null,
                    'suppress_from_publishing' =>
                        ((string)$loc->suppress_from_publishing == 'false')
                        ? false
                        : true,
                    'fulfillment_unit' => (string)$loc->fulfillment_unit ?? null
                ];
            }
            
            // Write the data to the cache
            $this->putCachedData($libCacheKey, $locations);
        }

        return (empty($location)) ? $locations ?? [] : $locations[$location] ?? [];
    }

    /**
     * Get summarized holdings and add it to the holdings array that is returned from
     * the default Alma ILS driver. This is quite specific to Austrian libraries.
     * See below for information on used MARC fields
     * 
     * TODO:
     *  - Less nesting in code below.
     *  - Fields 852b and 852c are not repeated in Austrian libraries, but we should
     *    consider the fact that these fields are repeatable according to the
     *    official Marc21 documentation.
     *  
     * Marc holding field 852
     * See https://wiki.obvsg.at/Katalogisierungshandbuch/KategorienuebersichtB852FE
     * - Library Code:      tag=852 ind1=8 ind2=1|# subfield=b
     * - Location:          tag=852 ind1=8 ind2=1|# subfield=c
     * - Call No.:          tag=852 ind1=8 ind2=1|# subfield=h
     * - Note on call no.:  tag=852 ind1=8 ind2=1|# subfield=z
     * 
     * Marc holding field 866
     * See https://wiki.obvsg.at/Katalogisierungshandbuch/KategorienuebersichtB866FE
     * - Summarized holdings:   tag=866 ind1=3 ind2=0 subfield=a
     * - Gaps:                  tag=866 ind1=3 ind2=0 subfield=z
     * - Prefix text for summarized holdings:
     *                          tag=866 ind1=# ind2=0 subfield=a
     * - Note for summarized holdings:
     *                          tag=866 ind1=# ind2=0 subfield=z
     * 
     * @param [type] $id
     * @param [type] $patron
     * @param array $options
     * @return array
     */
    public function getSummarizedHoldings($id)
    {
        // Initialize variables
        $summarizedHoldings = [];

        // Path to Alma holdings API
        $holdingsPath = '/bibs/' . urlencode($id) . '/holdings';

        // Get holdings from Alma API
        if ($almaApiResult = $this->makeRequest($holdingsPath)) {
            // Get the holding details from the API result
            $almaHols = $almaApiResult->holding ?? null;
            
            // Check if the holding details object is emtpy
            if (!empty($almaHols)) {
                foreach ($almaHols as $almaHol) {
                    // Get the holding IDs
                    $holId = (string)$almaHol->holding_id;

                    // Get the single MARC holding record based on the holding ID
                    if ($marcHol = $this->makeRequest($holdingsPath.'/'.$holId)) {
                        if ($marcHol != null && !empty($marcHol)) {
                            if (isset($marcHol->record)) {
                                // Get the holdings record from the API as a
                                // File_MARCXML object for better processing below.
                                $marc = new \File_MARCXML(
                                    $marcHol->record->asXML(),
                                    \File_MARCXML::SOURCE_STRING
                                );

                                // Read the Marc holdings record
                                if ($marcRec = $marc->next()) {

                                    // Get values only if we have an 866 field.
                                    if ($fs866 = $marcRec->getFields('866')) {
                                        $libCodes = null;
                                        $locCodes = null;
                                        $callNo = null;
                                        $callNoNote = null;
                                        $sumHoldings = null;
                                        $gaps = null;
                                        $sumHoldingsPrefix = null;
                                        $sumHoldingsNote = null;
                                        
                                        // Process 852 field(s)
                                        if ($fs852 = $marcRec->getFields('852')) {
                                            // Iterate over all 852 fields available
                                            foreach ($fs852 as $f852) {
                                                // Check if ind1 is '8'. We only
                                                // process these fields
                                                if ($f852->getIndicator('1')=='8') {
                                                    // Add data from subfields to
                                                    // arrays as their key for having
                                                    // unique values. We just use
                                                    // 'true' as array values.
                                                    foreach ($f852->getSubfields('b')
                                                        as $f852b) {
                                                        $libCodes[$f852b
                                                            ->getData()] = true;
                                                    }
                                                    foreach ($f852->getSubfields('c')
                                                        as $f852c) {
                                                        $locCodes[$f852c
                                                            ->getData()] = true;
                                                    }
                                                    foreach ($f852->getSubfields('h')
                                                        as $f852h) {
                                                        $callNo[$f852h
                                                            ->getData()] = true;
                                                    }
                                                    foreach ($f852->getSubfields('z')
                                                        as $f852z) {
                                                        $callNoNote[$f852z
                                                            ->getData()] = true;
                                                    }
                                                }
                                            }
                                        }

                                        // Iterate over all 866 fields available
                                        foreach ($fs866 as $f866) {
                                            // Check if ind1 is '3'
                                            if ($f866->getIndicator('1') == '3') {
                                                foreach ($f866->getSubfields('a')
                                                    as $f86630a) {
                                                    $sumHoldings[$f86630a
                                                        ->getData()] = true;
                                                }
                                                foreach ($f866->getSubfields('z')
                                                    as $f86630z) {
                                                    $gaps[$f86630z
                                                        ->getData()] = true;
                                                }
                                            }
                                            // Check if ind1 is 'blank'
                                            if ($f866->getIndicator('1') == ' ') {
                                                foreach ($f866->getSubfields('a')
                                                    as $f866_0a) {
                                                    $sumHoldingsPrefix[$f866_0a
                                                        ->getData()] = true;
                                                }
                                                foreach ($f866->getSubfields('z')
                                                    as $f866_0z) {
                                                    $sumHoldingsNote[$f866_0z
                                                        ->getData()] = true;
                                                }
                                            }
                                        }

                                        $summarizedHoldings[] = [
                                            'library' => ($libCodes) ? implode(', ', array_keys($libCodes)) : null,
                                            'location' => ($locCodes) ? implode(', ', array_keys($locCodes)) : 'UNASSIGNED',
                                            'callnumber' => ($callNo) ? implode(', ', array_keys($callNo)) : null,
                                            'callnumber_notes' => ($callNoNote) ? array_keys($callNoNote) : null,
                                            'holdings_available' => ($sumHoldings) ? implode(', ', array_keys($sumHoldings)) : null,
                                            'gaps' => ($gaps) ? array_keys($gaps) : null,
                                            'holdings_prefix' => ($sumHoldingsPrefix) ? implode(', ', array_keys($sumHoldingsPrefix)) : null,
                                            'holdings_notes' => ($sumHoldingsNote) ? array_keys($sumHoldingsNote) : null,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return empty(array_filter($summarizedHoldings)) ? [] : $summarizedHoldings;
    }

    /**
     * Check if request is valid
     *
     * This is responsible for determining if an item is requestable
     * 
     * AK: We use an array as return value instead of a boolean. That way we can
     * better customize the messages displayed to the user.
     *
     * @param string $id     The record id
     * @param array  $data   An array of item data
     * @param patron $patron An array of patron data
     *
     * @return bool True if request is valid, false if not
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        // AK: Check if we have a text that we should display instead of request or
        // recall buttons
        if (($library = $data['library'] ?? false)
            && ($location = $data['location'] ?? false)
        ) {
            // AK: Get data about fulfillment unit and location
            $locData = $this->getLocationData($library, $location);
            $fulUnit = $locData['fulfillment_unit'] ?: null;

            // AK: Get texts for fulfillment units and locations from Alma config
            $fulUnitTexts = $this->config['Holdings']['fulfillment_unit_text'] ?? null;
            $locTexts = $this->config['Holdings']['location_text'] ?? null;
            $locationText = $locTexts[$location] ?? $fulUnitTexts[$fulUnit] ?? null;

            // AK: Return the location text if we have one
            if ($locationText) {
                return ['valid' => false, 'status' => $locationText];
            }
        }

        $retVal = ['valid' => false, 'status' => 'RequestNotPossible'];

        $patronId = $patron['cat_username'];
        $level = $data['level'] ?? 'copy';
        if ('copy' === $level) {
            // Call the request-options API for the logged-in user
            $requestOptionsPath = '/bibs/' . urlencode($id)
                . '/holdings/' . urlencode($data['holding_id']) . '/items/'
                . urlencode($data['item_id']) . '/request-options?user_id='
                . urlencode($patronId);

            // Make the API request
            $requestOptions = $this->makeRequest($requestOptionsPath);
        } elseif ('title' === $level) {
            $hmac = explode(':', $this->config['Holds']['HMACKeys'] ?? '');
            if (!in_array('level', $hmac) || !in_array('description', $hmac)) {
                return false;
            }
            // Call the request-options API for the logged-in user
            $requestOptionsPath = '/bibs/' . urlencode($id)
                . '/request-options?user_id=' . urlencode($patronId);

            // Make the API request
            $requestOptions = $this->makeRequest($requestOptionsPath);
        } else {
            return $retVal;
        }

        // Check possible request types from the API answer
        $requestTypes = $requestOptions->xpath(
            '/request_options/request_option//type'
        );
        
        foreach ($requestTypes as $requestType) {
            if ('HOLD' === (string)$requestType) {
                $retVal['valid'] = true;
                $retVal['status'] = (isset($data['isRecall']))
                    ? 'Recall This'
                    : 'Place a Hold';
                return $retVal;
            }
        }

        return $retVal;
    }

    /**
     * Create a user in Alma via API call
     *
     * @param array $allParams All data from the "create new account" form
     *
     * @throws \VuFind\Exception\Auth
     *
     * @return NULL|SimpleXMLElement
     * @author Michael Birkner
     */
    public function createAlmaUser($allParams)
    {
        // Get config for creating new Alma users from Alma.ini
        $newUserConfig = $this->config['NewUser'] ?? [];

        // Check if all necessary configs are set
        $configParams = [
            'recordType', 'userGroup', 'preferredLanguage',
            'accountType', 'status', 'emailType', 'idType'
        ];
        foreach ($configParams as $configParam) {
            if (
                !isset($newUserConfig[$configParam])
                || empty(trim($newUserConfig[$configParam]))
            ) {
                $errorMessage = 'Configuration "' . $configParam . '" is not set ' .
                    'in Alma.ini in the [NewUser] section!';
                error_log('[ALMA]: ' . $errorMessage);
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        }

        // AK: Get current date
        $dateToday = date('Y-m-d');

        // AK: Calculate gender from form value
        $genders = ['m' => 'MALE', 'f' => 'FEMALE', 'd' => 'OTHER'];
        $gender = $genders[$allParams['salutation']] ?? 'NONE';

        // AK: Convert birthday to Alma date format
        $birthday = $allParams['birthday'] ?? null;
        $birthdayTs = null;
        if ($birthday != null) {
            $birthdayTs = strtotime($birthday);
        }
        $birthdayAlma = ($birthdayTs != null) ? date('Y-m-d', $birthdayTs) : null;

        // AK: Get expiry date and purge date in Alma date format
        $expiryDate = ($this->getExpiryDate())
            ? $this->getExpiryDate()->format('Y-m-d')
            : null;
        $purgeDate = ($this->getPurgeDate())
            ? $this->getPurgeDate()->format('Y-m-d')
            : null;

        // AK: Get statistical values
        $statArr = [];
        foreach ($allParams as $key => $statValue) {
            $keyParts = explode('_', $key);
            if ($keyParts[count($keyParts) - 1] === 'almastat') {
                $lengthWithoutSuffix = (strlen($key) - strlen('_almastat'));
                $statName = substr($key, 0, $lengthWithoutSuffix);
                if ($statValue != null && !empty($statValue)) {
                    $statArr[$statName] = $statValue;
                }
            }
        }

        // AK: Get the AlmaUserObject.xml file from the given theme and convert it to
        // a simple XML object
        $theme = $this->configLoader->get('config')->Site->theme ?? 'root';
        $almaUserObj = simplexml_load_file(
            "themes/" . $theme . "/templates/Auth/AlmaDatabase/AlmaUserObject.xml"
        );

        // AK: Set values to the simple XML object
        $almaUserObj->record_type = $newUserConfig['recordType'];
        $almaUserObj->first_name = $allParams['firstname'];
        $almaUserObj->last_name = $allParams['lastname'];
        $almaUserObj->gender = $gender;
        $almaUserObj->user_group = $newUserConfig['userGroup'];
        $almaUserObj->preferred_language = $newUserConfig['preferredLanguage'];
        $almaUserObj->birth_date = $birthdayAlma;
        $almaUserObj->expiry_date = $expiryDate;
        $almaUserObj->purge_date = $purgeDate;
        $almaUserObj->account_type = $newUserConfig['accountType'];
        $almaUserObj->status = $newUserConfig['status'];
        $almaUserObj->contact_info->addresses->address->line1 = $allParams['street'];
        $almaUserObj->contact_info->addresses->address->line2 =
            $allParams['zip'] . ' ' . $allParams['city'];
        $almaUserObj->contact_info->addresses->address->city =
            $allParams['city'];
        $almaUserObj->contact_info->addresses->address->postal_code =
            $allParams['zip'];
        $almaUserObj->contact_info->addresses->address->start_date =
            $dateToday;
        $almaUserObj->contact_info->addresses->address->address_types->address_type =
            $newUserConfig['addressType'];
        $almaUserObj->contact_info->emails->email->email_address =
            $allParams['email'];
        $almaUserObj->contact_info->emails->email->email_types->email_type =
            $newUserConfig['emailType'];
        $almaUserObj->contact_info->phones->phone->phone_number =
            $allParams['phone'];
        $almaUserObj->contact_info->phones->phone->phone_types->phone_type =
            $newUserConfig['phoneType'];
        $almaUserObj->user_identifiers->user_identifier->id_type =
            $newUserConfig['idType'];
        $almaUserObj->user_identifiers->user_identifier->value =
            $allParams['username'];

        // AK: Add statistic values if applicable
        if (!empty($statArr)) {
            // AK: Create parent statistic element
            $almaUserObj->addChild('user_statistics');

            // AK: For each given statistic value, create a basic statitic element
            // with the necessary child elements and add it to the parent element.
            // INFO: The data can't be added in this step because we have duplicate
            //       elements. Depending on the acutal implementation, we either
            //       would get an error or the data would be overwritten.
            for ($i = 0; $i < count($statArr); $i++) {
                $statObj = $almaUserObj->user_statistics->addChild('user_statistic');
                $statObj->addAttribute('segment_type', 'Internal');
                // Add child elements to basic statistic element
                $statObj->addChild('category_type');
                $statObj->addChild('statistic_category');
            }

            // AK: Add the data to the statistic elements that were created before
            $counter = 0;
            foreach ($statArr as $statName => $statValue) {
                $almaUserObj->user_statistics->user_statistic[$counter]
                    ->category_type = $statName;
                $almaUserObj->user_statistics->user_statistic[$counter]
                    ->statistic_category = $statValue;
                $counter++;
            }
        }

        // AK: Add user block element if applicable
        if (filter_var(($newUserConfig['blockUser'] ?? false),
            FILTER_VALIDATE_BOOLEAN
        )) {
            // AK: Create basic user block element
            $almaUserObj->addChild('user_blocks')->addChild('user_block')
                ->addAttribute('segment_type', 'Internal');

            // AK: Add child elements to basic user block element
            $almaUserObj->user_blocks->user_block->addChild('block_type');
            $almaUserObj->user_blocks->user_block->addChild('block_description');
            $almaUserObj->user_blocks->user_block->addChild('block_status');
            $almaUserObj->user_blocks->user_block->addChild('block_note');
            $almaUserObj->user_blocks->user_block->addChild('created_by');

            // AK: Add values to user block elements
            $almaUserObj->user_blocks->user_block->block_type =
                $newUserConfig['blockTypeCode'];
            $almaUserObj->user_blocks->user_block->block_description =
                $newUserConfig['blockDescriptionCode'];
            $almaUserObj->user_blocks->user_block->block_status =
                $newUserConfig['blockStatus'];
            $almaUserObj->user_blocks->user_block->block_note =
                $newUserConfig['blockNote'];
            $almaUserObj->user_blocks->user_block->created_by =
                $newUserConfig['blockCreatedBy'];
        }

        // AK: Convert simple XML element to string
        $almaUserObjStr = $almaUserObj->asXML();

        // AK: Remove whitespaces from XML string
        $almaUserObjStr = preg_replace("/\n/", "", $almaUserObjStr);
        $almaUserObjStr = preg_replace("/>\s*</", "><", $almaUserObjStr);

        // AK: Create user in Alma via API by POSTing the user XML
        $almaAnswer = $this->makeRequest(
            '/users',
            [],
            [],
            'POST',
            $almaUserObjStr,
            ['Content-Type' => 'application/xml']
        );

        // Return the XML anser from Alma on success. On error, an exception is
        // thrown in makeRequest.
        return $almaAnswer;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     * 
     * AK: Setting some user information to the object cache without using the
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
            'firstname'         => (isset($xml->first_name))
                ? (string) $xml->first_name
                : null,
            'lastname'          => (isset($xml->last_name))
                ? (string) $xml->last_name
                : null,
            'group'             => (isset($xml->user_group['desc']))
                ? (string) $xml->user_group['desc']
                : null,
            'group_code'        => (isset($xml->user_group))
                ? (string) $xml->user_group
                : null,
            'expiration_date'   => (isset($xml->expiry_date))
                ? $this->parseDate((string) $xml->expiry_date)
                : null,
        ];
        $contact = $xml->contact_info;
        if ($contact) {
            if ($contact->addresses) {

                // AK: Get preferred address
                $prefAddress = $contact->addresses
                    ->xpath('address[@preferred="true"]');

                // AK: If no preferred address is found, get the first one
                $address = (!empty($prefAddress))
                    ? $prefAddress[0]
                    : $contact->addresses[0]->address;

                // AK: Set address details to return array
                $profile['address1'] = (isset($address->line1))
                    ? (string) $address->line1
                    : null;
                $profile['address2'] = (isset($address->line2))
                    ? (string) $address->line2
                    : null;
                $profile['address3'] = (isset($address->line3))
                    ? (string) $address->line3
                    : null;
                $profile['address4'] = (isset($address->line4))
                    ? (string) $address->line4
                    : null;
                $profile['address5'] = (isset($address->line5))
                    ? (string) $address->line5
                    : null;
                $profile['zip']      = (isset($address->postal_code))
                    ? (string) $address->postal_code
                    : null;
                $profile['city']     = (isset($address->city))
                    ? (string) $address->city
                    : null;
                $profile['country']  = (isset($address->country))
                    ? (string) $address->country
                    : null;
            }

            // AK: Get e-mail object from Alma API user object
            $emailObj = $this->getEmailFromAlmaXmlUserObject($xml);

            // AK: Set e-mail address to return array
            $profile['email'] = (isset($emailObj->email_address))
                ? (string) $emailObj->email_address
                : null;
                
            // AK: Get non-mobile phone object from Alma API user object
            $phoneObj = $this->getPhoneFromAlmaXmlUserObject($xml);

            // AK: Get non-mobile phone number as string
            $phoneNo = (isset($phoneObj->phone_number))
                ? (string) $phoneObj->phone_number
                : null;

            // AK: Set non-mobile phone number to return array
            $profile['phone'] = $phoneNo;

            // AK: Get mobile phone object from Alma API user object
            $mobilePhoneObj = $this->getMobilePhoneFromAlmaXmlUserObject($xml);

            // AK: Get the mobile phone number (if any exists)
            $mobileNo = (isset($mobilePhoneObj->phone_number))
                ? (string) $mobilePhoneObj->phone_number
                : null;

            // AK: If mobile phone and non-mobile phone are the same, set to null so
            // that we don't have duplicate phone numbers in the view
            $mobileNo = ($mobileNo == $phoneNo) ? null : $mobileNo;

            // Set mobile phone number to return array
            $profile['mobile_phone'] = $mobileNo;
        }

        // AK: Set usergroup details to cache
        if (isset($this->cache)) {
            $patronIdKey = $this->getCleanCacheKey($patronId);
            $this->cache->setItem(
                'Alma_User_' . $patronIdKey . '_GroupCode',
                $profile['group_code'] ?? null
            );
            $this->cache->setItem(
                'Alma_User_' . $patronIdKey . '_GroupDesc',
                $profile['group'] ?? null
            );
            $this->cache->setItem(
                'Alma_User_' . $patronIdKey . '_ExpiryDate',
                $profile['expiration_date'] ?? null
            );
        }

        return array_filter($profile);
    }

    /**
     * AK: Change userdata in Alma
     *
     * @param array              $patron  Patron information
     * @param \Zend\Http\Request $request Request object containing form data
     * 
     * @return void
     */
    public function changeUserdata($patron, $request)
    {
        // Get values passed to this method
        $patronId = $patron['cat_username'];
        $phone = $request->getPost('phone');
        $mobilePhone = $request->getPost('mobile_phone');
        $email = $request->getPost('email');

        // Get user object from Alma via API
        $xml = $this->makeRequest('/users/' . $patronId, ['view' => 'full']);

        // Get e-mail object and set the address to the given user input
        $emailObj = $this->getEmailFromAlmaXmlUserObject($xml);
        if ($emailObj != null) {
            $emailObj->email_address = $email;
        }

        // Get non-mobile phone object and set the address to the given user input
        $phoneObj = $this->getPhoneFromAlmaXmlUserObject($xml);
        if ($phoneObj != null) {
            $phoneObj->phone_number = $phone;
        }

        // Get mobile phone object and set the address to the given user input
        $mobilePhoneObj = $this->getMobilePhoneFromAlmaXmlUserObject($xml);
        if ($mobilePhoneObj != null) {
            $mobilePhoneObj->phone_number = $mobilePhone;
        }

        // Convert simple XML element to string
        $almaUserObjStr = $xml->asXML();

        // Remove whitespaces from XML string
        $almaUserObjStr = preg_replace("/\n/", "", $almaUserObjStr);
        $almaUserObjStr = preg_replace("/>\s*</", "><", $almaUserObjStr);

        // Update the user in Alma via API
        $this->makeRequest(
            '/users/' . $patronId,
            [],
            [],
            'PUT',
            $almaUserObjStr,
            ['Content-Type' => 'application/xml']
        );
    }

    /**
     * AK: Get the preferred or first e-mail object from an Alma user object obtained
     * by an API call.
     *
     * @param SimpleXMLElement $almaXmlUserObject User object from Alma user API
     * 
     * @return null|SimpleXMLElement The (preferred) e-mail object
     */
    private function getEmailFromAlmaXmlUserObject($almaXmlUserObject)
    {
        $email = null;
        if ($contact = $almaXmlUserObject->contact_info) {
            if ($contact->emails) {
                // Get preferred e-mail
                $prefEmail = $contact->emails
                    ->xpath(
                        'email[@preferred="true"]'
                    );

                // If no preferred e-mail is found, get the first one
                $email = (!empty($prefEmail))
                    ? $prefEmail[0]
                    : $contact->emails[0]->email;
            }
        }

        return $email;
    }

    /**
     * AK: Get the preferred or first non-mobile phone object from an Alma user
     * object obtained by an API call.
     *
     * @param SimpleXMLElement $almaXmlUserObject User object from Alma user API
     * 
     * @return null|SimpleXMLElement The (preferred) non-mobile phone object
     */
    private function getPhoneFromAlmaXmlUserObject($almaXmlUserObject)
    {
        $phone = null;
        if ($contact = $almaXmlUserObject->contact_info) {
            if ($contact->phones) {
                // Get preferred default phone (not mobile only)
                $prefPhone = $contact->phones
                    ->xpath(
                        'phone[@preferred="true"][phone_types/phone_type!="mobile"]'
                    );

                // If no preferred default phone is found, get the first one
                $phone = (!empty($prefPhone))
                    ? $prefPhone[0]
                    : $contact->phones[0]->phone;
            }
        }
        return $phone;
    }

    /**
     * AK: Get the preferred or first mobile phone object from an Alma user object
     * obtained by an API call.
     *
     * @param SimpleXMLElement $almaXmlUserObject User object from Alma user API
     * 
     * @return null|SimpleXMLElement The (preferred) mobile phone object
     */
    private function getMobilePhoneFromAlmaXmlUserObject($almaXmlUserObject)
    {
        $mobile = null;
        if ($contact = $almaXmlUserObject->contact_info) {
            if ($contact->phones) {
                // Get preferred mobile phone
                $prefMobile = $contact->phones
                    ->xpath(
                        'phone[@preferred="true"][phone_types/phone_type="mobile"]'
                    );

                // If no preffered mobile phone is set, get all other mobile phones
                $mobile = (!empty($prefMobile)) ? $prefMobile : $contact->phones
                    ->xpath('phone[phone_types/phone_type="mobile"]');

                // Select the first mobile phone of all mobile phones (if any exists)
                $mobile = (!empty($mobile)) ? $mobile[0] : null;
            }
        }
        return $mobile;
    }

    /**
     * Get loan history for a specific user
     *
     * @param array  $patron Patron array returned by patronLogin method
     * @param array  $params Array of optional parameters
     *                      (keys ='limit', 'page', 'sort')
     * @return array An array with data about the loans of the user
     */
    public function getMyTransactionHistory($patron, $params = null)
    {
        // Get the MySQL user table
        $userTable = $this->getDbTable('user');

        // Get sort config
        $sortConf = (empty($params['sort'])) ? 'checkout desc' : $params['sort'];
        switch ($sortConf) {
            case 'checkout desc':
                $sort = ['loan_date' => 'desc'];
                break;
            case 'checkout asc':
                $sort = ['loan_date' => 'asc'];
                break;
            case 'return desc':
                $sort = ['return_date' => 'desc'];
                break;
            case 'return asc':
                $sort = ['return_date' => 'asc'];
                break;
            case 'due desc':
                $sort = ['due_date' => 'desc'];
                break;
            case 'due asc':
                $sort = ['due_date' => 'asc'];
                break;
            default:
                $sort = ['loan_date' => 'desc'];
                break;
        }

        // Get limit config
        $limit = (empty($params['limit']))
            ? 20
            : (int) $params['limit'];

        // Calculate offset for paging in SQL query
        $offset = (empty($params['page'])) ? 0 : ((int) $params['page'] - 1) * $limit;

        // Get the MySQL loans table
        $loansTable = $this->getDbTable('loans');

        // Get info about currently logged in user from the user table
        $user = $userTable->getByUsername($patron['cat_username'], false);

        try {
            // Get the internal VuFind user ID for the currently logged in user
            $currentUserId = $user->id;

            // Get loans for currently loggedin user
            $userLoans = $loansTable->selectUserLoans(
                $currentUserId,
                $limit,
                $offset,
                $sort
            );
        } catch (\Exception $e) {
            $errorMessage = 'Error when retrieving loans for user '
                . $patron['cat_username'] . '.';
            error_log($errorMessage . ' Check if the database for saving loans '
                . 'exists and is configured properly. Exception Message: '
                . $e->getMessage());
            throw new \VuFind\Exception\ILS($errorMessage);
        }

        // Map array from MySQL result to an array formatted as documented
        // for this function on VuFind Wiki.
        $userLoansMapped = array_map(function ($userLoan) {
            return array(
                'title' =>  $userLoan['title'] ?? null,
                'checkoutDate' => $userLoan['loan_date'] ?? null,
                'dueDate' => $userLoan['due_date'] ?? null,
                'id' => $userLoan['bib_id'] ?? null,
                'barcode' => $userLoan['barcode'] ?? null,
                'returnDate' => $userLoan['return_date'] ?? null,
                'publication_year' => $userLoan['publication_year'] ?? null,
                'volume' => $userLoan['description'] ?? null,
                'institution_name' => $userLoan['library_code'] ?? null,
                'borrowingLocation' => $userLoan['location_code'] ?? null
            );
        }, $userLoans['transactions']);

        // Create return array
        $returnArr['count'] = $userLoans['count'];
        $returnArr['transactions'] = $userLoansMapped;

        return $returnArr;
    }

    /**
     * Set the flag in the user table of the VuFind database that indicates if the
     * given user wants to save his loans or not. The column in the user database
     * is "save_loans". It is set to "1" if the user wants to save his loans, or to
     * 0 if he doesn't want to save them.
     *
     * @param array   $patron Patron array returned by patronLogin method
     * @param boolean $save   Boolean value indicating if the given user wants to
     *                        save his loans or not
     * 
     * @return void
     */
    public function saveMyTransactionHistory($patron, $save)
    {
        // Get the MySQL user table
        $userTable = $this->getDbTable('user');

        // Get the catalog username of the given patron
        $username = $patron['cat_username'] ?? null;

        if ($username) {
            // Get info about currently logged in user from the user table
            $user = $userTable->getByUsername($patron['cat_username'], false);

            // Convert boolean to integer for saving it to the database
            $saveInt = (filter_var($save, FILTER_VALIDATE_BOOLEAN)) ? 1 : 0;

            try {
                // Set the value for the "save_loans" column and save it
                $user->save_loans = $saveInt;
                $user->save();
            } catch (\Exception $e) {
                throw new \VuFind\Exception\ILS('Error while setting save_loans ' .
                    'flag for user ' . $username);
            }
        } else {
            throw new \VuFind\Exception\ILS('No username is given when trying ' .
                'to set save_loans flag.');
        }
    }

    /**
     * Delete a users loan history.
     *
     * @param  $patron Patron array returned by patronLogin method
     * 
     * @return void
     */
    public function deleteMyTransactionHistory($patron)
    {
        // Get catalog username from patron array
        $username = $patron['cat_username'] ?? null;

        if ($username) {
            // Get the MySQL user table
            $userTable = $this->getDbTable('user');

            // Get the MySQL loans table
            $loansTable = $this->getDbTable('loans');

            // Get info about currently logged in user from the user table
            $user = $userTable->getByUsername($username, false);

            try {
                // Get internal VuFind user id
                $userId = $user->id;

                // Delete users loans by internal VuFind user id
                $loansTable->deleteUserLoans($userId);
            } catch (\Exception $e) {
                throw new \VuFind\Exception\ILS('Error while deleting loans for ' .
                    'user ' . $username . ' (ID: ' . $userId . ')');
            }
        } else {
            throw new \VuFind\Exception\ILS('No username is given when trying ' .
                'to delete loans for a user.');
        }
    }

    /**
     * Export the loan history as CSV file
     *
     * @param array $patron Patron array returned by patronLogin method
     * @return void
     */
    public function exportMyTransactionHistory($patron)
    {
        // Get catalog username from patron array
        $username = $patron['cat_username'] ?? null;

        if ($username) {
            // Get the MySQL user table
            $userTable = $this->getDbTable('user');

            // Get the MySQL loans table
            $loansTable = $this->getDbTable('loans');

            // Get info about currently logged in user from the user table
            $user = $userTable->getByUsername($username, false);

            try {
                // Get internal VuFind user id
                $userId = $user->id;

                // Select users loans by internal VuFind user id
                $loans = $loansTable->selectUserLoans($userId);

                // Try to get the users operating system
                $ua = $_SERVER['HTTP_USER_AGENT']; // Get the user agent
                $os = 'win'; // Default. Most OS are Windows.
                $sep = ',';  // Default. In Excel (Win) we have to use semi-colon ;

                if (
                    stripos($ua, 'linux') !== false
                    || stripos($ua, 'CrOS') !== false
                    || stripos($ua, 'BSD') !== false
                    || stripos($ua, 'SunOS') !== false
                    || stripos($ua, 'UNIX') !== false
                ) {
                    $os = 'linux';
                } else if (stripos($ua, 'mac') !== false) {
                    $os = 'mac';
                } else if (stripos($ua, 'windows') !== false) {
                    $os = 'win';
                    $sep = ';';
                }

                // Check if we have to convert the encoding for Windows
                $convertToWin = true; // Default
                if ($os !== 'win') {
                    $convertToWin = false;
                }

                // Define the headings for the CSV
                $headings = [
                    'loanid' => 'internal_loan_id',  'ilsloanid' => 'ils_loan_id',
                    'bibid' => 'record_id', 'title' => 'Title', 'author' => 'Author',
                    'publication_year' => 'Year of Publication',
                    'description' => 'Description', 'call_no' => 'Call Number',
                    'barcode' => 'Barcode', 'loan_date' => 'Checkout Date',
                    'due_date' => 'Due Date', 'return_date' => 'Return Date'
                ];

                // Get translated headings for CSV
                $csvHeadings = $this->getCsvTranslation($headings, $convertToWin);

                // Define the columns from the DB table that should be used in the
                // resulting array. Also, the order given here will be used for the
                // results. That is important for aligning the columns containing
                // the CSV headings with the columns containing the CSV contents.
                $dbColsToUse = [
                    'id', 'ils_loan_id', 'bib_id', 'title', 'author',
                    'publication_year', 'description', 'call_no', 'barcode',
                    'loan_date', 'due_date', 'return_date'
                ];

                $csvLoanHistories = [];
                foreach ($loans['transactions'] as $key => $loan) {
                    // Sort the loan array based on the values of the $dbColsToUse
                    // array. This is necessary to align the columns containing the
                    // CSV headings with the columns containing the CSV contents.
                    $orderedLoan = array_replace(array_flip($dbColsToUse), $loan);

                    // Get the appropriate values and encode them for Windows if
                    // necessary
                    foreach ($orderedLoan as $dbCol => $dbValue) {
                        if (in_array($dbCol, $dbColsToUse)) {
                            // If null value is given, set to empty string
                            $value = ($dbValue) ? $dbValue : '';

                            // Add (Windows encoded) value to result array
                            $csvLoanHistories[$key][$dbCol] = ($convertToWin)
                                ? $this->getWinEncodedText($value)
                                : $value;
                        }
                    }
                }

                // Add headings to first place of CSV array
                array_unshift($csvLoanHistories, $csvHeadings);

                // Create CSV file and add loan history details
                $filename = 'ak_loan_history_' . date('d.m.Y') . '.csv';
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Type: text/csv; charset=UTF-16');
                $output = fopen('php://output', 'w');
                //$bom = "\xEF\xBB\xBF"; // UTF-8 BOM
                //fwrite($output, $bom);
                //fwrite($output, 'sep=,');
                foreach ($csvLoanHistories as $csvLoanHistory) {
                    fputcsv($output, array_values($csvLoanHistory), $sep, '"');
                }
                fclose($output);
            } catch (\Exception $e) {
                throw new \VuFind\Exception\ILS('Error while exporting loans for ' .
                    'user ' . $username . ' (ID: ' . $userId . ') | ' .
                    $e->getMessage());
            }
        } else {
            throw new \VuFind\Exception\ILS('No username is given when trying ' .
                'to export loans for a user.');
        }
    }

    /**
     * Translate CSV headings and, if neccessary, encode them for Windows.
     *
     * @param  array     $translate   Associative array of headings
     * @param  boolean   $convert     Convert to Windows encoding (UTF-16LE) if true
     * @return array                  Acssociative array of translated headings
     */
    protected function getCsvTranslation($translate, $convertToWin)
    {

        $csvHeadings = [];

        if ($convertToWin) {
            foreach ($translate as $key => $text) {
                $csvHeadings[$key] = $this->getWinEncodedText(
                    $this->translate($text)
                );
            }
        } else {
            foreach ($translate as $key => $text) {
                $csvHeadings[$key] = $this->translate($text);
            }
        }

        return $csvHeadings;
    }

    /**
     * Encode from UTF-8 to UTF-16LE. This is for CSV export on Windows.
     *
     * @param string  $text The text to encode from UTF-8 to UTF-16LE
     * @return string       The UTF-16LE encoded text
     */
    protected function getWinEncodedText($text)
    {
        return mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    }

    /**
     * Public Function which retrieves miscellaneous settings, among which are the
     * renew, hold and cancel settings from the driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = null)
    {
        // AK: Settings for displaying and getting the loan history of a user
        if ($function === 'getMyTransactionHistory') {
            if (empty($this->config['TransactionHistory']['enabled'])) {
                return false;
            }

            // AK: Get username from $params array
            $username = $params['cat_username'] ?? null;

            // AK: Initialize variable for saving loans check
            $saveLoans = null;

            // AK: Check if user wants to save loans
            if ($username) {
                // AK: Get the MySQL user table
                $userTable = $this->getDbTable('user');

                // AK: Get info about currently logged in user from the user table
                $user = $userTable->getByUsername($username, false);

                try {
                    $saveLoans = filter_var(
                        $user->save_loans,
                        FILTER_VALIDATE_BOOLEAN
                    );
                } catch (\Exception $e) {
                    // AK: Fail over. $saveLoans will be null which indicates that
                    // this functionality can't be used.
                }
            }

            return [
                'max_results' => 100,
                'page_size' => [20, 50, 80],
                'default_page_size' => 20,
                'sort' => [
                    'checkout desc' => 'sort_checkout_date_desc',
                    'checkout asc' => 'sort_checkout_date_asc',
                    'return desc' => 'sort_return_date_desc',
                    'return asc' => 'sort_return_date_asc',
                    'due desc' => 'sort_due_date_desc',
                    'due asc' => 'sort_due_date_asc'
                ],
                'default_sort' => 'checkout desc',
                'save_loans' => $saveLoans
            ];
        }

        if ($function == 'patronLogin') {
            return [
                'loginMethod' => $this->config['Catalog']['loginMethod'] ?? 'vufind'
            ];
        }
        if (isset($this->config[$function])) {
            $functionConfig = $this->config[$function];

            // Set default value for "itemLimit" in Alma driver
            if ($function === 'Holdings') {
                // Use itemLimit in Holds as fallback for backward compatibility
                $functionConfig['itemLimit'] = ($functionConfig['itemLimit']
                    ?? $this->config['Holds']['itemLimit']
                    ?? 10) ?: 10;
            }
        } elseif ('getMyTransactions' === $function) {
            $functionConfig = [
                'max_results' => 100,
                'sort' => [
                    'checkout desc' => 'sort_checkout_date_desc',
                    'checkout asc' => 'sort_checkout_date_asc',
                    'due desc' => 'sort_due_date_desc',
                    'due asc' => 'sort_due_date_asc',
                    'title asc' => 'sort_title'
                ],
                'default_sort' => 'due asc'
            ];
        } else {
            $functionConfig = false;
        }

        return $functionConfig;
    }

    /**
     * AK: Calculate expiry date of new user account based on the value set in
     *     Alma.ini
     *
     * @return \DateTime|null The calculated date/time or null
     */
    public function getExpiryDate()
    {
        // Get NewUser config from Alma.ini
        $newUserConfig = $this->config['NewUser'];

        // Create a new DateTime object for "now"
        $dateNow = new \DateTime('now');

        // Initialize return variable
        $expiryDate = null;

        if (
            isset($newUserConfig['expiryDate'])
            && !empty(trim($newUserConfig['expiryDate']))
        ) {
            try {
                // Add the date interval given in Alma.ini to "now"
                $expiryDate = $dateNow->add(
                    new \DateInterval($newUserConfig['expiryDate'])
                );
            } catch (\Exception $exception) {
                $errorMessage = 'Configuration "expiryDate" in Alma.ini (see ' .
                    '[NewUser] section) has the wrong format!';
                error_log('[ALMA]: ' . $errorMessage . '. Exception message: '
                    . $exception->getMessage());
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        } else {
            // Default: Add 1 year to "now"
            $expiryDate = $dateNow->add(new \DateInterval('P1Y'));
        }

        return $expiryDate;
    }

    /**
     * AK: Calculate purge date of new user account based on the value set in
     *     Alma.ini
     *
     * @return \DateTime|null The calculated date/time or null
     */
    public function getPurgeDate()
    {
        // Get NewUser config from Alma.ini
        $newUserConfig = $this->config['NewUser'];

        // Create a new DateTime object for "now"
        $dateNow = new \DateTime('now');

        // Initialize return variable
        $purgeDate = null;

        if (
            isset($newUserConfig['purgeDate'])
            && !empty(trim($newUserConfig['purgeDate']))
        ) {
            try {
                // Add the date interval given in Alma.ini to "now"
                $purgeDate = $dateNow->add(
                    new \DateInterval($newUserConfig['purgeDate'])
                );
            } catch (\Exception $exception) {
                $errorMessage = 'Configuration "purgeDate" in Alma.ini (see ' .
                    '[NewUser] section) has the wrong format!';
                error_log('[ALMA]: ' . $errorMessage . '. Exception message: '
                    . $exception->getMessage());
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        }

        return $purgeDate;
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver. Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsMethod($method, $params)
    {
        return is_callable([$this, $method]);
    }

}
