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


/**
 * AK: Extending authentication class for Alma. The VuFind database and the Alma API are
 * combined for authentication by this classe.
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
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     *                                                   new account details.
     *
     * @return NULL|\VuFind\Db\Row\User New user row.
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

        // AK: Get barcode and set it to the params
        $bcPrefix = $this->almaConfig['NewUser']['barcodePrefix'] ?? '' ?: '';
        $bcLength = $this->almaConfig['NewUser']['barcodeLength'] ?? 10;
        $bcChars = $this->almaConfig['NewUser']['barcodeChars'] ?? null ?: null;
        $params['username'] = $this->generateBarcode($bcPrefix, $bcLength, $bcChars);

        // AK: Get POST values and merge them with the params array into one array
        //     so that we can pass all together to the Alma driver for creating an
        //     account in Alma.
        $allValues = array_merge($request->getPost()->toArray(), $params);
        var_dump($allValues);

        // Validate username and password
        $this->validateUsernameAndPassword($params);

        // Get the user table
        $userTable = $this->getUserTable();

        // Make sure parameters are correct
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

        return $user;
    }

}
