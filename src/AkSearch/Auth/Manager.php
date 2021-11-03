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
     * AK: Adding user to LDAP
     *
     * @param UserRow $user User DB row from newly created user
     * @param object $conf  LDAP_Add_User config from config.ini
     * 
     * @return void
     */
    private function addUserToLdap($user, $conf) {
        $id = $user->id;
        $username = $user->username;
        $passHash = $user->pass_hash;
        $firstname = $user->firstname ?? null;
        $lastname = $user->lastname ?? null;
        $email = $user->email ?? null;
        $displayName = ($firstname)
            ? trim($firstname) . (($lastname) ? ' '.trim($lastname) : '')
            : trim($lastname);

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
        $attr = array_filter($attr);

        $ldapConnection = $this->getLdapConnection($conf);
        $this->ldapBind($ldapConnection, $conf);

        $dn_attr_value = $conf->dn_attr_value;
        $dn_prefix = $conf->dn_attr_key.'='.$user->$dn_attr_value;
        $dn = $dn_prefix . ',' . $conf->add_to_dn;

        try {
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
     * AK: Create an LDAP connection
     *
     * @param object $conf  LDAP_Add_User config from config.ini
     * 
     * @return resource|false LDAP connection
     */
    private function getLdapConnection($conf) {
        $connection = ldap_connect($conf->host, $conf->port);
        if (!$connection) {
            $this->debug('LDAP connection to '.$conf->host.' on port '.$conf->port
                .' failed. LDAP error message: ' . ldap_error($connection));
            throw new AuthException('new_user_ldap_error');
        }
        // Set LDAP options -- use protocol version 3
        if (!@ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            $this->debug('Failed to set LDAP protocol version 3');
        }

        return $connection;
    }

    /**
     * AK: Execute an LDAP bind. Throws AuthException if bind was not successful.
     *
     * @param resource $connection An LDAP connection
     * @param object $conf  LDAP_Add_User config from config.ini
     * 
     * @return bool True on successful bind
     */
    private function ldapBind($connection, $conf) {
        $ldapBind = ldap_bind($connection, $conf->username, $conf->password);
        if (!$ldapBind) {
            $this->debug('LDAP bind failed. Error: ' . ldap_error($connection));
            throw new AuthException('new_user_ldap_error');
        }
        return $ldapBind;
    }

    /**
     * Create a new user account from the request.
     * 
     * AK: Create LDAP user if applicable.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * new account details.
     *
     * @throws AuthException
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
