<?php
/**
 * AK: Provenance tab
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
 * @package  RecordTabs
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
namespace AkSearch\RecordTab;

/**
 * AK: Provenance tab
 *
 * @category AKsearch
 * @package  RecordTabs
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class Provenance extends \VuFind\RecordTab\AbstractBase {


    /**
     * [Provenance] config section from config.ini
     *
     * @var array
     */
    protected $provConfig = null;

    /**
     * Constructor
     *
     * @param array $config [Provenance] config section from config.ini
     */
    public function __construct(array $provConfig) {
        $this->provConfig = $provConfig;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'provenance';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        return empty($this->getRecordDriver()->tryMethod('getItemProvenance'))
            ? false : true;
    }

    /**
     * AK: Get provenance fields.
     *
     * @return array The item provenance fields as array
     */
    public function getItemProvenance()
    {
        return $this->getRecordDriver()->tryMethod('getItemProvenance',
            [$this->provConfig]);
    }
}
