<?php
/**
 * AK: Extended wrapper class for handling logged-in user in session.
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
 * @link     https://vufind.org Main Page
 */
namespace AkSearch\Auth;

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
{
    /**
     * AK: Change userdata
     *
     * @param array              $patron  Patron information
     * @param \Zend\Http\Request $request Request object containing form data
     * 
     * @return void
     */
    public function changeUserdata($patron, $request) {
        return $this->getAuth()->changeUserdata($patron, $request);
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
     * @param \Zend\Http\Request  $request    Request object from the form
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
     * @param \Zend\Http\Request  $request    Request object from the form
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
