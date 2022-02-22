<?php
/**
 * AK: Extended wrapper class for handling logged-in user in session.
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien 2021.
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
 * @link     https://vufind.org Main Page
 */
namespace AkSearch\Auth;
use VuFind\Exception\Auth as AuthException;
use VuFind\Db\Row\User as UserRow;

/**
 * AK: Extending wrapper class for handling logged-in user in session.
 *
 * @category AKsearch
 * @package  Authentication
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Manager extends \VuFind\Auth\Manager
    implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * AK: Create an LDAP connection
     *
     * @param object $conf The LDAP_Add_User config from config.ini
     * 
     * @return resource|false An LDAP connection object
     * 
     * @throws AuthException
     */
    private function getLdapConnection($conf) {
        $connection = ldap_connect($conf->host, $conf->port);
        if (!$connection) {
            $this->debug('LDAP connection to '.$conf->host.' on port '.$conf->port
                .' failed. LDAP error message: ' . ldap_error($connection));
            throw new AuthException('new_user_ldap_error');
        }
        // Use LDAP protocol version 3
        if (!@ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            $this->debug('Failed to set LDAP protocol version 3');
        }

        return $connection;
    }

    /**
     * AK: Execute an LDAP bind. Throws AuthException if bind was not successful.
     *
     * @param resource $connection An LDAP connection
     * @param string $bindUsername Username for bind action
     * @param string $bindPassword Password for bind action
     * 
     * @return bool true on successful bind, false otherwise
     * 
     * @throws AuthException
     */
    private function ldapBind($connection, $bindUsername, $bindPassword) {
        $ldapBind = ldap_bind($connection, $bindUsername, $bindPassword);
        if (!$ldapBind) {
            $this->debug('LDAP bind failed. Error: ' . ldap_error($connection));
            throw new AuthException('new_user_ldap_error');
        }
        return $ldapBind;
    }

    /**
     * AK: Check if a user exists in LDAP
     *
     * @param object $conf LDAP_Add_User config from config.ini
     * @param UserRow $user A user row object from the VuFind database
     * 
     * @return bool true if the user exists, false otherwise
     */
    private function ldapUserExists($conf, $user) {
        $userExists = false;
        $username = $user->username;
        $dnToRead = $conf->dn_attr_key.'='.$username.','.$conf->user_base_dn;
        $filter = $conf->dn_attr_key.'='.$username;
        $ldapConnection = $this->getLdapConnection($conf);
        ldap_bind($ldapConnection, $conf->readuser_name, $conf->readuser_password);
        try {
            $read = ldap_read($ldapConnection, $dnToRead, $filter, ['dn'], 1, 1, 30);
            if ($read != false) {
                $entries = ldap_get_entries($ldapConnection, $read);
                if ($entries != false) {
                    $userExists = true;
                }
            }
        } catch (\Exception $ex) {
            $this->debug('User not found when reading LDAP with DN '.$dnToRead.'. '
                .'Exception message: '.$ex->getMessage());
        }
        return $userExists;
    }

    /**
     * Find the specified username in the directory
     *
     * @param object $conf LDAP_Add_User config from config.ini
     * @param string $id   A user ID from the VuFind DB that is used as the uid
     * value of the LDAP user
     *
     * @return array|false First subarray from the result array that we get from
     * "ldap_get_entries", false if no user entry was found by the given ID
     */
    public function ldapSearchUserByUid($conf, $id)
    {
        $searchResult = false;
        $ldapSearch = false;
        $ldapConnection = $this->getLdapConnection($conf);
        ldap_bind($ldapConnection, $conf->readuser_name, $conf->readuser_password);
        try {
            $ldapFilter = 'uid=' . $id;
            $ldapSearch = ldap_search($ldapConnection, $conf->user_base_dn, $ldapFilter);
            if (!$ldapSearch) {
                $this->debug('User with ID "'.$id.'" not found in LDAP. '
                    .'LDAP message: ' . ldap_error($ldapConnection));
                return false;
            } else {
                $arr = ldap_get_entries($ldapConnection, $ldapSearch);
                $searchResult = $arr[0] ?? false;
            }
        } catch (\Exception $ex) {
            $this->debug('Error when trying to search user by ID "'.$id.'" in LDAP. '
                .'LDAP error message: ' . ldap_error($ldapConnection));
        }

        return $searchResult;
    }

    /**
     * AK: Add user to LDAP
     *
     * @param UserRow $user Row object from newly created user in VuFind user table
     * @param object  $conf LDAP_Add_User config from config.ini
     * 
     * @return void
     * 
     * @throws AuthException
     */
    private function addUserToLdap($user, $conf) {
        // Get data from VuFind user row object
        $id = $user->id;
        $username = $user->username;
        $passHash = $user->pass_hash;
        $firstname = $user->firstname ?? null;
        $lastname = $user->lastname ?? null;
        $email = $user->email ?? null;
        $displayName = ($firstname)
            ? trim($firstname) . (($lastname) ? ' '.trim($lastname) : '')
            : trim($lastname);

        // Create attributes for LDAP "add" command
        $attr['objectClass'][0] = 'inetOrgPerson';
        $attr['objectClass'][1] = 'organizationalPerson';
        $attr['objectClass'][2] = 'person';
        $attr['objectClass'][3] = 'top';
        $attr['uid'] = $id;
        $attr['cn'] = $username;
        $attr['displayName'] = $displayName;
        $attr['givenName'] = ($firstname) ? trim($firstname) : null;
        $attr['sn'] = ($lastname) ? trim($lastname) : 'NoSurname';
        $attr['mail'] = $email;
        $attr['userPassword'] = ($conf->userPassword_prefix ?? '') . $passHash;

        // Filter out empty/null values
        $attr = array_filter($attr);

        // Create LDAP connection and bind with a user the is allowed to write
        $ldapConnection = $this->getLdapConnection($conf);
        $this->ldapBind($ldapConnection, $conf->writeuser_name,
            $conf->writeuser_password);

        // Create the DN for the new LDAP entry
        $dn_attr_value = $conf->dn_attr_value;
        $dn_prefix = $conf->dn_attr_key.'='.$user->$dn_attr_value;
        $dn = $dn_prefix . ',' . $conf->user_base_dn;

        try {
            // Execute the "add" command
            $ldapAdd = ldap_add($ldapConnection, $dn, $attr);
            if (!$ldapAdd) {
                $this->debug('Error when trying to add an entry to LDAP. '
                    .'LDAP error message: ' . ldap_error($ldapConnection));
                throw new AuthException('new_user_ldap_error');
            }
        } catch (\Exception $ex) {
            $this->debug('Error when trying to add an entry to LDAP. Does this'
                .' account already exist? DN: ' . $dn
                .'. LDAP error message: ' . ldap_error($ldapConnection)
                .'. Exception message: ' . $ex->getMessage());
            throw new AuthException('new_user_ldap_error');
        }
    }

    /**
     * AK: Update a password for a user in LDAP
     *
     * @param object  $conf LDAP_Add_User config from config.ini
     * @param UserRow $user A user row object from the user table in the VuFind DB
     * 
     * @return bool true if updating the password was successful, false otherwise
     * 
     * @throws Exception
     */
    private function updateLdapPassword($conf, $user) {
        // Return variable
        $success = false;

        // Get some values we need
        $dnAttrValue = $conf->dn_attr_value;
        $dnValue = $user->$dnAttrValue;
        $passHash = $user->pass_hash;

        // Create DN for which the password should be updated
        $dnToUpdate = $conf->dn_attr_key.'='.$dnValue.','.$conf->user_base_dn;

        // Create LDAP connection
        $ldapConnection = $this->getLdapConnection($conf);
        try {
            // Set the hashed user password
            $attr['userPassword'] = ($conf->userPassword_prefix ?? '') . $passHash;

            // Bind to the user
            ldap_bind($ldapConnection, $conf->writeuser_name,
                $conf->writeuser_password);

            // Execute the replace command 
            $success = ldap_mod_replace($ldapConnection, $dnToUpdate, $attr);
        } catch (\Exception $ex) {
            $this->debug('Could not update password in LDAP for user with DN '
                .$dnToUpdate.'. Exception message: '.$ex->getMessage());
        }
        return $success;
    }

    /**
     * AK: Rename an LDAP entry
     *
     * @param object $conf LDAP_Add_User config from config.ini
     * @param array $ldapUser An LDAP user entry. This is a single array entry from
     * a "ldap_get_entries" result (that was fed with an "ldap_search" result).
     * @param string $dnUpdateValue The new value that should replace an old value
     * 
     * @return boolean true if the operation was successful, false otherwise
     */
    public function renameLdapEntry($conf, $ldapUser, $dnUpdateValue) {
        // Initialize result variable
        $success = false;

        // Connect to LDAP
        $ldapConnection = $this->getLdapConnection($conf);
        try {
            // Get the current DN from the given LDAP user
            $dnToUpdate = $ldapUser['dn'];

            // Create the new DN with the new key and new value
            $dnKey = $conf->dn_attr_key;

            // Bind with a write user
            ldap_bind($ldapConnection, $conf->writeuser_name,
                $conf->writeuser_password);

            // Execute the rename command
            $success = ldap_rename($ldapConnection, $dnToUpdate, $dnKey.'='.$dnUpdateValue, $conf->user_base_dn, true);
        } catch (\Exception $ex) {
            $this->debug('Could not rename LDAP entry with DN '.$dnToUpdate.'. '
                .'Exception message: '.$ex->getMessage());
        }
        return $success;
    }

    /**
     * Create a new user account from the request.
     * 
     * AK: Create LDAP user if applicable.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * new account details.
     *
     * @return UserRow New user row.
     */
    public function create($request)
    {
        $user = parent::create($request);
        if ($user) {
            $ldapAddUserConfig = $this->config->LDAP_Add_User ?? null ?: null;
            if ($ldapAddUserConfig && $ldapAddUserConfig->add_user_to_ldap == true) {
                $this->addUserToLdap($user, $ldapAddUserConfig);
            }
        }
        return $user;
    }

    /**
     * Update a user's password from the request.
     * 
     * AK: Update password in or add new user to LDAP if applicable
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * password change details.
     *
     * @return UserRow New user row.
     */
    public function updatePassword($request)
    {
        $user = parent::updatePassword($request);

        $ldapAddUserConfig = $this->config->LDAP_Add_User ?? null ?: null;
        if ($ldapAddUserConfig && $ldapAddUserConfig->add_user_to_ldap == true) {
            $ldapUserExists = $this->ldapUserExists($ldapAddUserConfig, $user);
            if ($ldapUserExists) {
                // AK: Update password of existing user
                $this->updateLdapPassword($ldapAddUserConfig, $user);
            } else {
                // AK: Create new user in LDAP
                $this->addUserToLdap($user, $ldapAddUserConfig);
            }
        }

        return $user;
    }

    /**
     * AK: Change userdata
     *
     * @param array              $patron  Patron information
     * @param \Laminas\Http\Request $request Request object containing form data
     * 
     * @return void
     */
    public function changeUserdata($patron, $request) {
        // TODO: To avoid linter errors, we could extend VuFind\Auth\AbstractBase
        $this->getAuth()->changeUserdata($patron, $request);
    }

    /**
     * AK: Is changing userdata supported and allowed?
     *
     * @param string $authMethod optional; check this auth method rather than
     *  the one in config file
     *
     * @return bool
     */
    public function supportsUserdataChange($authMethod = null) {
        if (isset($this->config->Authentication->change_userdata)
            && $this->config->Authentication->change_userdata
        ) {
            return $this->getAuth($authMethod)->supportsUserdataChange();
        }
        return false;
    }

    /**
     * AK: Is sending a welcome e-mail to a user upon creating a new account
     *     supported?
     *
     * @param string $authMethod optional; check this auth method rather than
     * the one in config file
     *
     * @return bool
     */
    public function supportsWelcomeEmail($authMethod = null)
    {
        if (filter_var(
            ($this->config->Authentication->welcome_email ?? false),
            FILTER_VALIDATE_BOOLEAN
        )) {
            // Pass through to authentication method
            return $this->getAuth($authMethod)->supportsWelcomeEmail();
        }
        return false;
    }

    /**
     * AK: Is sending an e-mail to the library upon creating a new account supported?
     *
     * @param string $authMethod optional; check this auth method rather than
     * the one in config file
     *
     * @return bool
     */
    public function supportsLibraryEmail($authMethod = null)
    {
        if (isset($this->config->Authentication->library_email)
            && $this->config->Authentication->library_email
        ) {
            // Pass through to authentication method
            return $this->getAuth($authMethod)->supportsLibraryEmail();
        }
        return false;
    }

    /**
     * AK: Get variables for the welcome e-mail that is sent to the user a new
     *     account is created.
     *
     * @param \Laminas\Http\Request  $request    Request object from the form
     * @param \VuFind\Db\Row\User $user       User row object from the database
     * @param string              $authMethod Authentication method (optional)
     * 
     * @return array Array with keys 'subject' and 'text' holding values that should
     *               be used for the e-mail to the library.
     */
    public function getWelcomeEmailVars($request, $user, $authMethod = null)
    {
        // Pass through to authentication method
        return $this->getAuth($authMethod)->getWelcomeEmailVars($request, $user);
    }

    /**
     * AK: Get variables for the e-mail that is sent to the library when a new user
     *     account is created.
     *
     * @param \Laminas\Http\Request  $request    Request object from the form
     * @param \VuFind\Db\Row\User $user       User row object from the database
     * @param string              $authMethod Authentication method (optional)
     * 
     * @return array Array with keys 'subject' and 'text' holding values that should
     *               be used for the e-mail to the library.
     */
    public function getLibraryEmailVars($request, $user, $authMethod = null)
    {
        // Pass through to authentication method
        return $this->getAuth($authMethod)->getLibraryEmailVars($request, $user);
    }

}
