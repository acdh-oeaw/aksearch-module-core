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
        // TODO: COMMENTED FOR TESTING!!!
        //$allValues = array_merge($request->getPost()->toArray(), $params);
        //var_dump($allValues);

        /*
        // TODO: COMMENTED FOR TESTING!!!
        // Validate username and password
        $this->validateUsernameAndPassword($params);

        // Make sure parameters are correct
        // AK Info: This ensures that the username (= barcode) and eMail address
        //          are unique in the database.
        $this->validateParams($params, $userTable);

        // Create user account in Alma
        $almaAnswer = $this->almaDriver->createAlmaUser($params);

        // Create user account in VuFind user table if Alma gave us an answer
        if ($almaAnswer !== null) {
            // If we got this far, we're ready to create the account:
            $user = $this->createUserFromParams($params, $userTable);

            // Add the Alma primary ID as cat_id to the VuFind user table
            $user->cat_id = $almaAnswer->primary_id ?? null;

            // Save the new user to the user table
            $user->save();

            // Save the credentials to cat_username and cat_password to bypass
            // the ILS login screen from VuFind
            $user->saveCredentials($params['username'], $params['password']);
        } else {
            throw new AuthException($this->translate('ils_account_create_error'));
        }
        */

        // TODO: ONLY FOR TESTING:
        $user = $this->createUserFromParams($params, $userTable);
        $user->save();
        $user->saveCredentials($params['username'], $params['password']);

        return $user;
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
     * AK: Get variables for the e-mail that is sent to the user when a new account is
     *     created.
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
        $expiryDate = $this->getExpiryDate();
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
        $expiryDate = $this->getExpiryDate();
        $expiryDate = ($displayDateFormat)
            ? $expiryDate->format($displayDateFormat)
            : $expiryDate->format('Y-m-d');
        
        // Create string for statistical values
        $statisticsArr = [];
        foreach ($request->getPost() as $key => $value) {
            $keyParts = explode('_', $key);
            if ($keyParts[count($keyParts)-1] === 'almastat') {
                $statisticsArr[] = $this->translate($key).': '.$this->translate($value.'_almastat');
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
     * AK: Calculate expiry date of new user account based on the value set in
     *     Alma.ini
     *
     * @return \DateTime|null The calculated date/time or null
     */
    protected function getExpiryDate()
    {
        // Get NewUser config from Alma.ini
        $newUserConfig = $this->almaConfig['NewUser'];

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
    protected function getPurgeDate()
    {
        // Get NewUser config from Alma.ini
        $newUserConfig = $this->almaConfig['NewUser'];

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

}
