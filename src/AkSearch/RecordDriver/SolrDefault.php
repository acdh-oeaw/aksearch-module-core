<?php
/**
 * AK: Extended default model for Solr records -- used when a more specific model
 * based on the record_format field cannot be found.
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
 * @package  RecordDrivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

namespace AkSearch\RecordDriver;

/**
 * AK: Extending default model for Solr records -- used when a more specific model
 * based on the record_format field cannot be found.
 *
 * This should be used as the base class for all Solr-based record models.
 *
 * @category AKsearch
 * @package  RecordDrivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class SolrDefault extends \VuFind\RecordDriver\SolrDefault
{

    /**
     * These Solr fields should NEVER be used for snippets.  (We exclude author
     * and title because they are already covered by displayed fields; we exclude
     * spelling because it contains lots of fields jammed together and may cause
     * glitchy output; we exclude ID because random numbers are not helpful).
     * 
     * AK: Added fields title_partNo_txt and title_part_txt
     *
     * @var array
     */
    protected $forbiddenSnippetFields = [
        'author', 'title', 'title_short', 'title_full',
        'title_full_unstemmed', 'title_auth', 'title_sub', 'spelling', 'id',
        'ctrlnum', 'author_variant', 'author2_variant', 'fullrecord',
        'title_partNo_txt', 'title_part_txt'
    ];


    /**
     * Get a highlighted title string, if available.
     *
     * AK: Also highlight subtitle
     * 
     * AK - FIXME: Not working properly at the moment. Titles get shortened in a
     * wrong way in class VuFind\View\Helper\Root\AddEllipsis which is called in
     * AkSearch\View\Helper\Root\Record->getTitleHtml().
     * 
     * @return string
     */
    public function getHighlightedTitle()
    {
        // Don't check for highlighted values if highlighting is disabled:
        if (!$this->highlight) {
            return '';
        }
        $titleMain = (isset($this->highlightDetails['title_short'][0]))
            ? $this->highlightDetails['title_short'][0] : '';
        
        // AK: Highlight subtitle - field must be added in searchspecs.yaml!
        $titleSub = (isset($this->highlightDetails['title_sub'][0]))
            ? $this->highlightDetails['title_sub'][0] : '';
        
        // AK: Highlight part number - field must be added in searchspecs.yaml!
        $titlePartNo = (isset($this->highlightDetails['title_partNo_txt'][0]))
        ? $this->highlightDetails['title_partNo_txt'][0] : '';

        // AK: Highlight part title - field must be added in searchspecs.yaml!
        $titlePart = (isset($this->highlightDetails['title_part_txt'][0]))
        ? $this->highlightDetails['title_part_txt'][0] : '';

        // AK: Join the title values together. With array_filter we remove possible
        // empty values.
        return implode(
            ' : ',
            array_filter(
                [$titleMain, $titleSub, $titlePartNo, $titlePart],
                array($this, 'filterCallback')
            )
        );
    }

    /**
     * AK: Callback function for array_filter function in getHighlightedTitle method.
     * Default array_filter would not only filter out empty or null values, but also
     * the number "0" (as it evaluates to false). So if a title would just be "0" it
     * would not be displayed.
     *
     * @param   string $var The value of an array. In our case these are strings.
     * 
     * @return  boolean     False if $var is null or empty, true otherwise.
     */
    protected function filterCallback($var) {
        // Return false if $var is null or empty
        if ($var == null || trim($var) == '') {
            return false;
        }
        return true;
    }


    /**
     * Returns one of three things: a full URL to a thumbnail preview of the record
     * if an image is available in an external system; an array of parameters to
     * send to VuFind's internal cover generator if no fixed URL exists; or false
     * if no thumbnail can be generated.
     * 
     * AK: Return also "contenttype" in array. See description for getThumbnail here:
     *     https://vufind.org/wiki/development:architecture:record_driver_method_master_list
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string|array|bool
     */
    public function getThumbnail($size = 'small')
    {
        if (isset($this->fields['thumbnail']) && $this->fields['thumbnail']) {
            return $this->fields['thumbnail'];
        }
        $arr = [
            'author'     => mb_substr($this->getPrimaryAuthor(), 0, 300, 'utf-8'),
            'callnumber' => $this->getCallNumber(),
            'size'       => $size,
            'title'      => mb_substr($this->getTitle(), 0, 300, 'utf-8'),
            'recordid'   => $this->getUniqueID(),
            'source'   => $this->getSourceIdentifier(),
            // AK: Use 'self::' as with using '$this->' we would use the function
            //     from MarcBasicTrait (that is 'use'd in SolrMarc which extends this
            //     class). MarcBasicTrait gets a MarcXml field instead of a Solr
            //     field.
            'contenttype' => self::getFormats()
        ];
        if ($isbn = $this->getCleanISBN()) {
            $arr['isbn'] = $isbn;
        }
        if ($issn = $this->getCleanISSN()) {
            $arr['issn'] = $issn;
        }
        if ($oclc = $this->getCleanOCLCNum()) {
            $arr['oclc'] = $oclc;
        }
        if ($upc = $this->getCleanUPC()) {
            $arr['upc'] = $upc;
        }
        // If an ILS driver has injected extra details, check for IDs in there
        // to fill gaps:
        if ($ilsDetails = $this->getExtraDetail('ils_details')) {
            foreach (['isbn', 'issn', 'oclc', 'upc'] as $key) {
                if (!isset($arr[$key]) && isset($ilsDetails[$key])) {
                    $arr[$key] = $ilsDetails[$key];
                }
            }
        }

        return $arr;
    }

    public function getFormats() {
        $format = null;

        // Use 'parent::' as with using '$this->' we would use the function from
        // MarcBasicTrait (that is 'use'd in SolrMarc which extends this class).
        // MarcBasicTrait gets a MarcXml field instead of a Solr field.
        $formats = parent::getFormats();

        // Get the first value of the formats array. If there is no format, use
        // 'Unknown'.
        $format = (count($formats) > 0) ? $formats[0] : 'Unknown';

        // Tweak the output

        // If DVD and CDROM is in the formats, only use DVD
        /*
        if (in_array('DVD', $formats) && in_array('CDROM', $formats)) {
            $format = 'DVD';
        }
        */

        // If Printed is in the formats and there is another value, use that other
        // value.
        if (count($formats) > 1 && in_array('Printed', $formats)) {
            if (($key = array_search('Printed', $formats)) !== false) {
                unset($formats[$key]);
                //ini_set("xdebug.overload_var_dump",1);
                //var_dump($formats);
                $format = reset($formats);
            }
        }



        return strtolower($format);
    }
}
