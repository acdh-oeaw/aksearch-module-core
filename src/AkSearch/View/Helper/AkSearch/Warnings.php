<?php
/**
 * AK: View helper for displaying warning messages.
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
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:view_helpers Wiki
 */
namespace AkSearch\View\Helper\AkSearch;

/**
 * AK: View helper for displaying warning messages.
 *
 * @category AKsearch
 * @package  View_Helpers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:view_helpers Wiki
 */
class Warnings extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * AK: The [Warnings] section in config.ini
     *
     * @var array
     */
    protected $warningsConfig;

    /**
     * AK: Constructor
     *
     * @param array  $warningsConfig The [Warnings] section in config.ini
     */
    public function __construct(array $warningsConfig) {
        $this->warningsConfig = $warningsConfig;
    }

    /**
     * Get configs for warnings of the [Warnings] section in config.ini
     *
     * @return array
     */
    public function getConfig() {
        return $this->warningsConfig;
    }

}