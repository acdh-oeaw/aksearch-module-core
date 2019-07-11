<?php
/**
 * AK: Extended factory for authentication services.
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

use Zend\ServiceManager\ServiceManager;

/**
 * AK: Extending factory for authentication services.
 *
 * @category AKsearch
 * @package  Authentication
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:authentication_handlers Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{

    /**
     * Construct the AlmaDatabase plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AlmaDatabase
     */
    public static function getAlmaDatabase(ServiceManager $sm)
    {
        return new AlmaDatabase(
            $sm->get('VuFind\ILS\Connection'),
            $sm->get('VuFind\Auth\ILSAuthenticator')
        );
    }
}
