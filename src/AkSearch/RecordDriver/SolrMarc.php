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
    }

    /**
     * Get an array of all the languages associated with the record.
     * 
     * AK: Override the default "getLanguages" method. First get the language from
     * the Solr field and then - as a fallback - from MarcXML. The translation of the
     * language name is done in AkSearch\View\Helper\Root\RecordDataFormatterFactory
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
}
