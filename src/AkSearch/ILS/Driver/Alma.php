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
class Alma
            extends \VuFind\ILS\Driver\Alma
            implements \VuFind\Db\Table\DbTableAwareInterface,
                       \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use AlmaTrait;
    use \VuFind\Db\Table\DbTableAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;


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
        $newUserConfig = $this->config['NewUser'];
        
        // Check if all necessary configs are set
        $configParams = [
            'recordType', 'userGroup', 'preferredLanguage',
            'accountType', 'status', 'emailType', 'idType'
        ];
        foreach ($configParams as $configParam) {
            if (!isset($newUserConfig[$configParam])
                || empty(trim($newUserConfig[$configParam]))
            ) {
                $errorMessage = 'Configuration "' . $configParam . '" is not set ' .
                                'in Alma.ini in the [NewUser] section!';
                error_log('[ALMA]: ' . $errorMessage);
                throw new \VuFind\Exception\Auth($errorMessage);
            }
        }

        // Get current date
        $dateToday = date('Y-m-d');

        // Calculate gender from form value
        $genders = ['m' => 'MALE', 'f' => 'FEMALE', 'd' => 'OTHER'];
        $gender = $genders[$allParams['salutation']] ?? 'NONE';

        // Convert birthday to Alma date format
        $birthday = $allParams['birthday'] ?? null;
        $birthdayTs = null;
        if ($birthday != null) {
            $birthdayTs = strtotime($birthday);
        }
        $birthdayAlma = ($birthdayTs != null) ? date('Y-m-d', $birthdayTs) : null;

        // Get expiry date and purge date in Alma date format
        $expiryDate = ($this->getExpiryDate())
            ? $this->getExpiryDate()->format('Y-m-d')
            : null;
        $purgeDate = ($this->getPurgeDate())
            ? $this->getPurgeDate()->format('Y-m-d')
            : null;

        // Get statistical values
        $statArr = [];
        foreach ($allParams as $key => $statValue) {
            $keyParts = explode('_', $key);
            if ($keyParts[count($keyParts)-1] === 'almastat') {
                $lengthWithoutSuffix = (strlen($key)-strlen('_almastat'));
                $statName = substr($key, 0, $lengthWithoutSuffix);
                if ($statValue != null && !empty($statValue)) {
                    $statArr[$statName] = $statValue;
                }
            }
        }

        // Get the AlmaUserObject.xml file from the given theme and convert it to
        // a simple XML object
        $theme = $this->configLoader->get('config')->Site->theme ?? 'root';
        $almaUserObj = simplexml_load_file(
            "themes/".$theme."/templates/Auth/AlmaDatabase/AlmaUserObject.xml"
        );

        // Set values to the simple XML object
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

        // Add statistic values if applicable
        if (!empty($statArr)) {
            // Create parent statistic element
            $almaUserObj->addChild('user_statistics');

            // For each given statistic value, create a basic statitic element with
            // the necessary child elements and add it to the parent element.
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

            // Add the data to the statistic elements that were created before
            $counter = 0;
            foreach ($statArr as $statName => $statValue) {
                $almaUserObj->user_statistics->user_statistic[$counter]
                    ->category_type = $statName;
                $almaUserObj->user_statistics->user_statistic[$counter]
                    ->statistic_category = $statValue;
                $counter++;
            }
        }

        // Add user block element if applicable
        if (filter_var(($newUserConfig['blockUser'] ?? false),
            FILTER_VALIDATE_BOOLEAN)
        ) {
            // Create basic user block element
            $almaUserObj->addChild('user_blocks')->addChild('user_block')
                ->addAttribute('segment_type', 'Internal');
            
            // Add child elements to basic user block element
            $almaUserObj->user_blocks->user_block->addChild('block_type');
            $almaUserObj->user_blocks->user_block->addChild('block_description');
            $almaUserObj->user_blocks->user_block->addChild('block_status');
            $almaUserObj->user_blocks->user_block->addChild('block_note');
            $almaUserObj->user_blocks->user_block->addChild('created_by');
            
            // Add values to user block elements
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
        
        // Convert simple XML element to string
        $almaUserObjStr = $almaUserObj->asXML();

        // Remove whitespaces from XML string
        $almaUserObjStr = preg_replace("/\n/", "", $almaUserObjStr);
        $almaUserObjStr = preg_replace("/>\s*</", "><", $almaUserObjStr);
        
        // Create user in Alma via API by POSTing the user XML
        $almaAnswer = $this->makeRequest('/users', [], [], 'POST', $almaUserObjStr,
            ['Content-Type' => 'application/xml']);

        // Return the XML anser from Alma on success. On error, an exception is
        // thrown in makeRequest.
        return $almaAnswer;
    }




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
            $this->cache->setItem(
                'Alma_User_'.$patronIdKey.'_GroupCode',
                $profile['group_code'] ?? null
            );
            $this->cache->setItem(
                'Alma_User_'.$patronIdKey.'_GroupDesc',
                $profile['group'] ?? null
            );
        }

        return $profile;
    }

    /**
     * Get loan history for a specific user
     *
     * @param array  $patron Patron array returned by patronLogin method
     * @param array  $params Array of optional parameters
     *                      (keys ='limit', 'page', 'sort')
     * @return array An array with data about the loans of the user
     */
    public function getMyTransactionHistory($patron, $params = null) {
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
            : (int)$params['limit'];

        // Calculate offset for paging in SQL query
        $offset = (empty($params['page'])) ? 0 : ((int)$params['page'] - 1) * $limit;
        
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
        $userLoansMapped = array_map(function($userLoan) {
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
    public function saveMyTransactionHistory($patron, $save) {
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
    public function deleteMyTransactionHistory($patron) {
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
    public function exportMyTransactionHistory($patron) {
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
				
                if (stripos($ua, 'linux') !== false
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
                $dbColsToUse = ['id', 'ils_loan_id', 'bib_id', 'title', 'author',
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
                            $csvLoanHistories[$key][$dbCol] = 
                                ($convertToWin)
                                ? $this->getWinEncodedText($value)
                                : $value;
                        }
                    }
                }

                // Add headings to first place of CSV array
                array_unshift($csvLoanHistories, $csvHeadings);

                // Create CSV file and add loan history details
				$filename = 'ak_loan_history_' . date('d.m.Y') . '.csv';
				header('Content-Disposition: attachment; filename="'.$filename.'"');
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
    protected function getCsvTranslation($translate, $convertToWin) {

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
    protected function getWinEncodedText($text) {
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
        // Settings for displaying and getting the loan history of a user
        if ($function === 'getMyTransactionHistory') {
            if (empty($this->config['TransactionHistory']['enabled'])) {
                return false;
            }

            // Get username from $params array
            $username = $params['cat_username'] ?? null;

            // Initialize variable for saving loans check
            $saveLoans = null;

            // Check if user wants to save loans
            if ($username) {
                // Get the MySQL user table
                $userTable = $this->getDbTable('user');

                // Get info about currently logged in user from the user table
                $user = $userTable->getByUsername($username, false);
                
                try {
                    $saveLoans = filter_var(
                        $user->save_loans,
                        FILTER_VALIDATE_BOOLEAN
                    );
                } catch (\Exception $e) {
                    // Fail over. $saveLoans will be null which indicates that this
                    // functionality can't be used.
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
        
        // Return config by key
        return isset($this->config[$function])
            ? $this->config[$function]
            : false;
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

        if (isset($newUserConfig['purgeDate'])
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

}
