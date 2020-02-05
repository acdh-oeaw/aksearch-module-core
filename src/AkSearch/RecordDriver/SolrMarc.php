<?php
/**
 * AK: Extended model for MARC records in Solr.
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
 * AK: Extending model for MARC records in Solr.
 *
 * @category AKsearch
 * @package  RecordDrivers
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */
class SolrMarc extends SolrDefault
{
    use \VuFind\RecordDriver\IlsAwareTrait;
    use \VuFind\RecordDriver\MarcReaderTrait;
    use MarcAdvancedTrait {
        getURLs as protected getURLsFromMarcXml;
        getLanguages as protected getLanguagesFromMarcXml;
        // Method "getFormats" does not exist in MarcAdvancedTrait, but 
        // MarcAdvancedTrait uses MarcBasicTrait where it exists.
        getFormats as protected getFormatsFromMarcXml;
        getBibliographicLevel as protected getBibliographicLevelFromMarcXml;
    }


    /**
     * Get an array of all the languages associated with the record.
     * 
     * AK: Override the default "getLanguages" method in MarcBasicTrait. First get
     * the language from the Solr field and then - as a fallback - from MarcXML. The 
     * translation of the language name is done in:
     * @see \AkSearch\View\Helper\Root\RecordDataFormatterFactory
     *
     * @return array
     */

    public function getLanguages()
    {
        $langs = $this->fields['language'] ?? null;
        if ($langs == null || empty($langs)) {
            $langs = $this->getLanguagesFromMarcXml();
        }
        return array_unique($langs);
    }
    
    /**
     * AK: Override the "getURLs" method in MarcAdvancedTrait. We use the multivalued
     * Solr field "url" that is indexed in a special way. I contains blocks of 3
     * connected values in the format "url, description, mimetype" (see example).
     * If we have no results from Solr we fall back to using the "getURLs" method 
     * from MarcAdvancedTrait for getting results directly from MarcXML.
     * 
     * Example of Solr field content:
     * ------------------------------
     * 
     * ```ini
     * http://url1.com
     * Fulltext
     * application/html
     * http://url2.com
     * Abstract
     * application/html
     * http://url3.com
     * Link to publisher
     * application/html
     * ```
     *
     * @return array Array of arrays, each with keys "url", "desc" and "mime"
     */
    public function getURLs() {
        $results = [];
        $urlsFromSolr = $this->fields['url'] ?? null;

        if ($urlsFromSolr != null && !empty($urlsFromSolr)) {
    		foreach ($urlsFromSolr as $key => $value) {
    			if (($key % 3) == 0) { // First of 3 values
                    $result['url'] = $value;
    			} else if (($key % 3) == 1) { // Second of 3 values
                    $result['desc'] = $value;
    			}  else if (($key % 3) == 2) { // Third and last of 3 values
                    $result['mime'] = $value;
                    
    				// We have all values now, add them to the return array
                    $results[] = $result;
                }
    		}
    	} else {
            // Fallback to MarcXML parsing method
            $results = $this->getURLsFromMarcXml();
        }
       
        return $results;
    }

    /**
     * Returns one of three things: a full URL to a thumbnail preview of the record
     * if an image is available in an external system; an array of parameters to
     * send to VuFind's internal cover generator if no fixed URL exists; or false
     * if no thumbnail can be generated.
     * 
     * AK: Return also "contenttype" in array. See description for getThumbnail here:
     * https://vufind.org/wiki/development:architecture:record_driver_method_master_list
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
            'title'      => mb_substr(parent::getTitle(), 0, 300, 'utf-8'),
            'recordid'   => $this->getUniqueID(),
            'source'   => $this->getSourceIdentifier(),
            // AK: Get the format for the tumbnail (icon). See also page on VuFind
            // Wiki mentioned in this method doc.
            'contenttype' => $this->getFormat()
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

    /**
     * AK: Get the format for the record thumbnail (icon). We only return one format
     * because an array of formats is not suitable for getting the icon (we can only
     * get one image). If no format was found we return "Unknown".
     *
     * @return string   The format for the tumbnail image
     */
    public function getFormat() {
        // Initialize the return value with "null" as default
        $format = null;

        $formats = $this->getFormats();

        // Get the first value of the formats array. If there is no format, use
        // "Unknown".
        $format = (count($formats) > 0) ? $formats[0] : 'Unknown';

        return $format;
    }

    /**
     * AK: Overrides the getFormats method in MarcBasicTrait. Get the formats of the
     * record from Solr and then - as a fallback - from MarcXML. If no formats were
     * found we return an empty array.
     *
     * @return array   The formats of the record as an array
     */
    public function getFormats() {
        // Initialize the return value with "null" as default
        $formats = [];

        // Get the formats from the Solr field by using the method from the parent.
        $formats = parent::getFormats();

        // Get the formats from MarcXML as a fallback. This is using the method from
        // MarcBasicTrait (which in turn is "use"d by MarcAdvancedTrait).
        if ($formats == null || empty($formats)) {
            $formats = $this->getFormatsFromMarcXml();
        }

        // Return the formats
        return $formats;
    }

    /**
     * AK: Overrides the getBibliographicLevel method in MarcAdvancedTrait. Get the
     * level of the record from Solr and then - as a fallback - from MarcXML. If no
     * level was found we return null.
     *
     * @return string|null   The level of the record as string or null
     */
    public function getBibliographicLevel()
    {
        $level = $this->fields['bibLevel_txtF'] ?? null;
        if ($level == null) {
            $level = $this->getBibliographicLevelFromMarcXml();
        }
        return $level;
    }

}
