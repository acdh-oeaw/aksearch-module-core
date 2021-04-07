<?php
/**
 * AK: Extended Action Helper - New Items Support Methods
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
 * @package  Controller_Plugins
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace AkSearch\Controller\Plugin;

use Laminas\Config\Config;
/**
 * AK: Extending action helper to perform new items-related actions
 *
 * @category AKsearch
 * @package  Controller_Plugins
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class NewItems extends \VuFind\Controller\Plugin\NewItems
{
    /**
     * AK: displayDateFormat from [Site] configuration in config.ini
     *
     * @var string
     */
    protected $displayDateFormat;

    /**
     * AK: Indicates if datepicker is used or not
     * 
     * @var boolean
     */
    protected $useDatePicker = false;

    /**
     * Constructor
     * AK: Getting additional config values for the datepicker
     *
     * @param Config $config     Configuration
     * @param Config $siteConfig [Site] configuration section from config.ini
     */
    public function __construct(Config $config, Config $siteConfig)
    {
        parent::__construct($config);
        // AK: Check if the word "datepicker" is in the [NewItem]->ranges config of 
        //     searches.ini. If yes, "$this->useDatePicker" is "true".
        $this->useDatePicker = in_array('datepicker', preg_split('/\s*,\s*/', $this->config->ranges));

        // AK: Get the display date format in [Site] config of config.ini. We use
        //     this format also in the datepicker.
        $this->displayDateFormat = $siteConfig->displayDateFormat ?: 'm-d-Y';
    }

    /**
     * Get range settings
     * AK: Use of datepicker
     *
     * @return array
     */
    public function getRanges()
    {
        // Find out if there are user configured range options; if not,
        // default to the standard 1/5/30 days:
        $ranges = [];
        if (isset($this->config->ranges)) {
            // AK: If 'datepicker' is defined for [NewItem]->ranges config in
            //     searches.ini, we return this value and no other one.
            if ($this->useDatePicker) {
                $ranges[] = 'datepicker';
            } else {
                $tmp = explode(',', $this->config->ranges);
                foreach ($tmp as $range) {
                    $range = intval($range);
                    if ($range > 0) {
                        $ranges[] = $range;
                    }
                }
            }
        }
        if (empty($ranges)) {
            $ranges = [1, 5, 30];
        }
        return $ranges;
    }

    /**
     * Get a Solr filter to limit to the specified number of days.
     * AK: Added possibility to use a custom Solr date field for the filter.
     *
     * @param int $range Days to search
     *
     * @return string
     */
    public function getSolrFilter($range)
    {
        // AK: Get [NewItem]->solrfield config from searches.ini
        $solrFieldConf = $this->config['solrfield'];

        // AK: Check if config is set. If not, use default field "first_indexed"
        $solrField = (isset($solrFieldConf) && !empty($solrFieldConf))
            ? $solrFieldConf
            : 'first_indexed';

        // AK: Check if a datepicker should be used and if $range is an array. 
        //     If yes, we have a 'start' ($range[0]) and 'end' ($range[1 ]) date.
        //     We will then use these dates for the Solr filter.
        if ($this->useDatePicker && is_array($range)) {
            return $solrField . ':[' . $range[0] . ' TO ' . $range[1] . ']';
        }

        // AK: Create Solr filter with given Solr field name
        return $solrField . ':[NOW-' . $range . 'DAY TO NOW]';
    }

    /**
     * Get config values for a possible-to-display datepicker on the new items search
     * form.
     *
     * @return array An array with configs if a datepicker should be used. An empty
     *               array if no datepicker should be used.
     */
    public function getDatepickerConfig() {
        $datePickerConfig = [];
        if ($this->useDatePicker) {
            $datePickerConfig['displayDateFormatPhp'] = $this->getDisplayDateFormat();
            $datePickerConfig['datepickerStartDate'] = $this->getDatepickerStartDate();
            $datePickerConfig['datepickerFromDateFull'] = $this->getDatepickerFromDateFull();
            $datePickerConfig['datepickerMode'] = $this->getDatepickerMode();
        }
        return $datePickerConfig;
    }

    /**
     * AK: Get the display date format in PHP notation for the datepicker
     *
     * @return string The display date format in PHP notation
     */
    public function getDisplayDateFormat() {
        // AK: If the datepicker should display single days as date, use the format
        //     from config.ini. If months should be displayed, use the month format,
        //     e. g. "January 2019".
        return ($this->getDatepickerMode() === 'days')
                  ? $this->displayDateFormat
                  : 'F Y';
    }

    /**
     * AK: Get the first date that should be selectable in the datepicker. The format
     *     must be 'Y-m-d' (PHP notation).
     *
     * @return string The first date that should be selectable in the datepicker
     */
    public function getDatepickerStartDate() {
        $startDateConf = $this->config['datepickerStartDate'];
        $startDate = (isset($startDateConf) && !empty($startDateConf))
            ? $startDateConf
            : '1900-01-01';

        $startDateFormatted = date_create_from_format('Y-m-d', $startDate)
                              ->format($this->getDisplayDateFormat());

        return $startDateFormatted;
    }

    /**
     * AK: Get the date that should be selected in the datepicker when the new items
     *     search form is opened.
     *
     * @return string The date that should be selected in the datepicker when opened
     */
    public function getDatepickerFromDateFull() {
        $intervalConf = $this->config['datepickerFromDate'];
        $interval = (isset($intervalConf) && !empty($intervalConf))
            ? $intervalConf
            : 'P1M';
            $now = new \DateTime();

        $fromDateFull = $now
                    ->sub(new \DateInterval($interval))
                    ->format('Y-m-d\T00:00:00.000\Z');
        
        return $fromDateFull;

    }

    /**
     * AK: Get the mode of the datepicker. Possible values are "days" and "months".
     *     This indicates if single days or whole months should be selectable in the
     *     datepicker on the new items search form.
     *
     * @return string Return "months" or "days". Default is "days".
     */
    public function getDatepickerMode() {
        $modeConf = $this->config['datepickerMode'];
        $mode = (
            isset($modeConf)
            && !empty($modeConf)
            && ($modeConf === 'months' || $modeConf === 'days')
            )
            ? $modeConf
            : 'days';
        
        return $mode;
    }
    
}
