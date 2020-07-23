<?php
 /**
 * AK: Extended Alma database authentication class
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
 * @package  Authentication
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
namespace AkSearch\Auth;

use \VuFind\Exception\Auth as AuthException;

/**
 * AK: Extending authentication class for Alma. The VuFind database and the Alma API
 *     are combined for authentication by this class.
 *
 * @category AKsearch
 * @package  Authentication
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 */
class AlmaDatabase extends \VuFind\Auth\AlmaDatabase
{
    use \AkSearch\ILS\Driver\AlmaTrait;

    /**
     * Create a new user account in Alma AND in the VuFind Database.
     *
     * @param \Zend\Http\Request $request Request object containing new account
     * details.
     *
     * @return null|\VuFind\Db\Row\User New user row.
     */
    public function create($request)
    {
        // When in privacy mode, don't create an Alma account and delegate
        // further code execution to the parent.
        if ($this->getConfig()->Authentication->privacy) {
            return parent::create($request);
        }

        // User variable
        $user = null;

        // Collect POST parameters from request
        $params = $this->collectParamsFromRequest($request);

        // Get the user table
        $userTable = $this->getUserTable();

        // AK: Get a unique username/barcode
        $params['username'] = $this->getUniqueBarcode($userTable);

        // AK: Get POST values and merge them with the params array into one array
        //     so that we can pass all together to the Alma driver for creating an
        //     account in Alma.
        $allParams = array_merge($request->getPost()->toArray(), $params);

        // Validate username and password
        // AK Info: Ensures that values are not blank and passwords match.
        $this->validateUsernameAndPassword($params);

        // Make sure parameters are correct
        // AK Info: This ensures that the username (= barcode) and eMail address
        //          are unique in the database.
        $this->validateParams($params, $userTable);

        // Create user account in Alma
        $almaAnswer = $this->almaDriver->createAlmaUser($allParams);

        // Create user account in VuFind user table if Alma gave us an answer
        if ($almaAnswer !== null) {
            // If we got this far, we're ready to create the account:
            $user = $this->createUserFromParams($params, $userTable);

            // Add the Alma primary ID as cat_id to the VuFind user table
            $user->cat_id = $almaAnswer->primary_id ?? null;

            // AK: Check if transaction history is enabled
            if(filter_var(
                ($this->almaConfig['TransactionHistory']['enabled'] ?? false),
                FILTER_VALIDATE_BOOLEAN
                )
            ) {
                // Check if user wants to save his transaction history
                $saveTransactionHistory = filter_var(
                    ($allParams['loanHistory'] ?? false),
                    FILTER_VALIDATE_BOOLEAN
                );

                // Set flag in database
                try {
                    $user->save_loans = ($saveTransactionHistory) ? 1 : 0;
                } catch (\Exception $e) {
                    throw new AuthException('Error while setting save_loans ' .
                        'flag for user ' . $params['username']);
                }
            };

            // Save the new user to the user table
            $user->save();

            // Save the credentials to cat_username and cat_password to bypass
            // the ILS login screen from VuFind
            $user->saveCredentials($params['username'], $params['password']);
        } else {
            throw new AuthException($this->translate('ils_account_create_error'));
        }

        return $user;
    }

    /**
     * Attempt to authenticate the current user. Throws exception if login fails.
     * AK: Check if user exists in VuFind database AND in Alma
     *
     * @param Request $request Request object containing account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request)
    {
        // Make sure the credentials are non-blank:
        $this->username = trim($request->getPost()->get('username'));
        $this->password = trim($request->getPost()->get('password'));
        if ($this->username == '' || $this->password == '') {
            throw new AuthException('authentication_error_blank');
        }

        // Validate the credentials:
        $user = $this->getUserTable()->getByUsername($this->username, false);
        if (!is_object($user) || !$this->checkPassword($this->password, $user)) {
            throw new AuthException('authentication_error_invalid');
        }

        // Verify email address:
        $this->checkEmailVerified($user);


        // AK: Check if the user exists in Alma by calling the getMyProfile function
        //     of the Alma ILS driver.
        try {
            $xml = $this->almaDriver->getMyProfile($user);
        } catch (\Exception $e) {
            throw new AuthException('authentication_error_alma_problem');
        }

        // AK: If we get an invalid answer from the API it means that the user does
        //     not exist in Alma.
        if ($xml == null || empty($xml)) {
            throw new AuthException('authentication_error_no_alma_user');
        }

        // If we got this far, the login was successful:
        return $user;
    }

    /**
     * AK: Change userdata in Alma and in VuFind database
     *
     * @param array              $patron  Patron information
     * @param \Zend\Http\Request $request Request object containing form data
     * 
     * @return void
     */
    public function changeUserdata($patron, $request) {
        // Collect POST parameters from request
        $params = $this->collectParamsFromRequest($request);

        // Get the user table
        $userTable = $this->getUserTable();

        // Invalid Email Check
        $validator = new \Zend\Validator\EmailAddress();
        if (!$validator->isValid($params['email'])) {
            throw new AuthException('Email address is invalid');
        }

        // Check if Email is on whitelist (if applicable)
        if (!$this->emailAllowed($params['email'])) {
            throw new AuthException('change_userdata_email_domain_blocked');
        }

        // Make sure we have a unique email
        if ($userTable->getByEmail($params['email'])) {
            throw new AuthException('That email address is already used');
        }

        // If we got this far, the data should be valid and we can get the user row
        // and change its data.
        $user = $userTable->getByCatalogId($patron['id']);
        if ($user) {
            try {
                // Change user data
                $user->email = $params['email'];
                $user->save();
            } catch (\Exception $e) {
                throw new AuthException('change_userdata_error');
            }
        } else {
            throw new AuthException('change_userdata_user_not_found');
        }

        // If we got this far, update the account in Alma, but only if we are not in
        // privacy mode
        if (!$this->getConfig()->Authentication->privacy) {
            $this->almaDriver->changeUserdata($patron, $request);
        }
    }

    /**
     * AK: Get a unique barcode/username. If the generated barcode/username exists
     *     already in the database, a new one will be generated, but the retries are
     *     limited to max. 5.
     *
     * @param \VuFind\Db\Table\User $userTable VuFind user table
     * @param int $regenerateCounter Count retries for regenerating the
     *                                   barcode/username. Max 5 retries.
     * 
     * @throws AuthException
     * 
     * @return string A unique barcode/username
     */
    protected function getUniqueBarcode($userTable, $regenerateCounter = 0) {
        // Get barcode from the barcode generator in \AkSearch\ILS\Driver\AlmaTrait
        $bcPrefix = $this->almaConfig['NewUser']['barcodePrefix'] ?? '' ?: '';
        $bcLength = $this->almaConfig['NewUser']['barcodeLength'] ?? 10;
        $bcChars = $this->almaConfig['NewUser']['barcodeChars'] ?? null ?: null;
        $barcode = $this->generateBarcode($bcPrefix, $bcLength, $bcChars);
                
        // Make sure we have a unique username/barcode for saving it to the database.
        // If not, regenerate a new one but with max. 5 retries.
        if ($userTable->getByUsername($barcode, false)) {
            if ($regenerateCounter === 5) {
                throw new AuthException('That username is already taken');
            }
            $regenerateCounter++;
            $barcode = $this->getUniqueBarcode($userTable, $regenerateCounter);
        }

        return $barcode;
    }

    /**
     * AK: Get variables for the e-mail that is sent to the user when a new account
     *     is created.
     *
     * @param \Zend\Http\Request  $request  Request object from the form
     * @param \VuFind\Db\Row\User $user     User row object from the database
     * 
     * @return array Array with keys 'subject' and 'text' holding values that can be
     *               used for the e-mail to the user.
     */
    public function getWelcomeEmailVars($request, $user) {
        // Translated default value in case there is none
        $na = $this->translate('not_applicable');

        // Get display date format from config.ini
        $displayDateFormat = $this->config->Site->displayDateFormat;

        // Calculate salutation and get translation from language file
        $salutationPost = $request->getPost('salutation', 'd');
        $salutation = null;
        if ($salutationPost == 'm') {
            $salutation = $this->translate('dearMale');
        } else if ($salutationPost == 'f') {
            $salutation = $this->translate('dearFemale');
        } else {
            $salutation = $this->translate('dearDivers');
        }

        // Get expiry date of new user account and format it
        $expiryDate = $this->almaDriver->getExpiryDate();
        $expiryDate = ($displayDateFormat)
            ? $expiryDate->format($displayDateFormat)
            : $expiryDate->format('Y-m-d');

        // Variables for e-mail text
        $textVars = [
            'salutation' => $salutation ?? $na,
            'firstname' => $request->getPost('firstname', $na),
            'lastname' => $request->getPost('lastname', $na),
            'barcode' => ($user->username ?? $na),
            'expiryDate' => $expiryDate ?? $na
        ];

        return ['subject' => [], 'text' => $textVars];
    }

    /**
     * AK: Get variables for the e-mail that is sent to the library when a new user
     *     account is created.
     *
     * @param \Zend\Http\Request  $request  Request object from the form
     * @param \VuFind\Db\Row\User $user     User row object from the database
     * 
     * @return array Array with keys 'subject' and 'text' holding values that can be
     *               used for the e-mail to the library.
     */
    public function getLibraryEmailVars($request, $user) {
        // Translated default value in case there is none
        $na = $this->translate('not_applicable');

        // No and Yes values translated
        $no = $this->translate('no');
        $yes = $this->translate('yes');

        // Get display date format from config.ini
        $displayDateFormat = $this->config->Site->displayDateFormat;

        // Translate checkbox values to Yes or No
        $dataProcessing = ($request->getPost('dataProcessing')) ? $yes : $no;
        $loanHistory = ($request->getPost('loanHistory')) ? $yes : $no;
        $houseAndUsageRules = ($request->getPost('houseAndUsageRules')) ? $yes : $no;

        // Get expiry date of new user account and format it
        $expiryDate = $this->almaDriver->getExpiryDate();
        $expiryDate = ($displayDateFormat)
            ? $expiryDate->format($displayDateFormat)
            : $expiryDate->format('Y-m-d');
        
        // Create string for statistical values
        $statisticsArr = [];
        foreach ($request->getPost() as $key => $value) {
            $keyParts = explode('_', $key);
            if ($keyParts[count($keyParts)-1] === 'almastat') {
                $statisticsArr[] = $this->translate($key) . ' = '
                    . $this->translate($value.'_almastat');
            }
        }
        $statistics = implode('; ', $statisticsArr);

        // Variables for e-mail subject
        $subjectVars = [
            '%%barcode%%' => ($user->username ?? $na),
            '%%firstname%%' => $request->getPost('firstname', $na),
            '%%lastname%%' => $request->getPost('lastname', $na)
        ];

        // Variables for e-mail text
        $textVars = [
            'barcode' => ($user->username ?? $na),
            'firstname' => $request->getPost('firstname', $na),
            'lastname' => $request->getPost('lastname', $na),
            'street' => $request->getPost('street', $na),
            'zip' => $request->getPost('zip', $na),
            'city' => $request->getPost('city', $na),
            'email' => $request->getPost('email', $na),
            'phone' => $request->getPost('phone', $na),
            'birthday' => $request->getPost('birthday', $na),
            'expiryDate' => $expiryDate ?? $na,
            'statistics' => $statistics ?? $na ?: $na,
            'dataProcessing' => $dataProcessing,
            'loanHistory' => $loanHistory,
            'houseAndUsageRules' => $houseAndUsageRules
        ];

        return ['subject' => $subjectVars, 'text' => $textVars];
    }

    /**
     * AK: Indicates whether the sending of a welcome e-mail is supported or not.
     *
     * @return bool
     */
    public function supportsWelcomeEmail() {
        return true;
    }

    /**
     * AK: Indicates whether the sending of an information e-mail to the library when
     *     a new user account is created is supported or not.
     *
     * @return bool
     */
    public function supportsLibraryEmail() {
        return true;
    }

    /**
     * AK: Indicates if changing userdata is supported or not
     *
     * @return bool
     */
    public function supportsUserdataChange() {
        return true;
    }

}
