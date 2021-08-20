<?php
/**
 * AK: View helper for the email alert popup.
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
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:view_helpers Wiki
 */
namespace AkSearch\View\Helper\AkSearch;

/**
 * AK: View helper for the email alert popup.
 *
 * @category AKsearch
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:view_helpers Wiki
 */
class EmailAlertPopup extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * AK: The [Account] section in config.ini
     *
     * @var array
     */
    protected $accountConfig;

    /**
     * AK: Constructor
     *
     * @param array  $accountConfig The [Account] section in config.ini
     */
    public function __construct(array $accountConfig) {
        $this->accountConfig = $accountConfig;
    }

    /**
     * Get configs for the account of the [Account] section in config.ini
     *
     * @return array
     */
    public function getConfig() {
        return $this->accountConfig;
    }

}