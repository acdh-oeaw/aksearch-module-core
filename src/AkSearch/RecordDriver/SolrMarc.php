<?php
/**
 * AK: Extended model for MARC records in Solr.
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
        // Use the function with the name "getPublicationInfo" from \AkSearch\
        // RecordDriver\MarcAdvancedTrait instead of the function with the same name
        // in \VuFind\RecordDriver\MarcReaderTrait
        \AkSearch\RecordDriver\MarcAdvancedTrait::getPublicationInfo insteadof
            \VuFind\RecordDriver\MarcReaderTrait;

        getURLs as protected getURLsFromMarcXml;
        getLanguages as protected getLanguagesFromMarcXml;
        // Method "getFormats" does not exist in MarcAdvancedTrait, but 
        // MarcAdvancedTrait uses MarcBasicTrait where it exists.
        getFormats as protected getFormatsFromMarcXml;
        getBibliographicLevel as protected getBibliographicLevelFromMarcXml;
        getGenres as protected getGenresFromMarcXml;
        // Method "getCallNumbers" does not exist in MarcAdvancedTrait, but 
        // MarcAdvancedTrait uses MarcBasicTrait where it exists.
        getCallNumbers as protected getCallNumbersFromXml;
        getAllSubjects as protected getAllSubjectsFromXml;
        hasChilds as protected hasChildsFromXml;
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
    public function getURLs()
    {
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
    public function getFormat()
    {
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
    public function getFormats()
    {
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

    /**
     * AK: Overrides the getGenres method in MarcAdvancedTrait. Get the genre(s) of
     * the record from Solr and then - as a fallback - from MarcXML. If no genre(s)
     * were found we return null.
     *
     * @return array|null   The genre(s) of the record as array or null
     */
    public function getGenres()
    {
        $genres = $this->fields['bibForm_txtF_mv'];
        if ($genres == null) {
            $genres = $this->getGenresFromMarcXml();
        }
        return $genres;
    }

    /**
     * AK: Get subject terms from all corresponding Solr fields. As a fallback, get
     * subjects from MarcXML.
     *
     * @return array|null   All subject terms as an array or null
     */
    public function getAllSubjects()
    {
        $subjects = array_unique(
            array_merge(
                $this->fields['subject_txtF_mv'] ?? [],
                $this->fields['subjectAkTopic_txt_mv'] ?? [],
                $this->fields['subjectAkGeogr_txt_mv'] ?? [],
                $this->fields['subjectAkPerson_txt_mv'] ?? [],
                $this->fields['subjectAkCorporation_txt_mv'] ?? [],
                $this->fields['subjectAkForm_txt_mv'] ?? [],
                $this->fields['subjectAkEra_txt_mv'] ?? []
            )
        );
        if (empty($subjects)) {
            $subjects = $this->getAllSubjectsFromXml();
        }
        return (empty($subjects)) ? null : $subjects;
    }

    /**
     * Get all content summaries (abstracts) from the corresponding Solr field.
     *
     * @return array|null   All content summaries (abstracts) as an array or null
     */
    public function getContentSummaries()
    {
        return $this->fields['contentSummary_txt_mv'] ?? null;
    }

    /**
     * Get all call numbers from the corresponding Solr field. If nothing was found,
     * get them from MarcXML as a fallback.
     *
     * @return array   An array with call numbers or an empty array
     */
    public function getCallNumbers()
    {
        $callNumbers = $this->fields['callnumber_txt_mv'] ?? null;
        if ($callNumbers == null) {
            $callNumbers = $this->getCallNumbersFromXml();
        }
        return $callNumbers;
    }

    /**
     * Get the first call number that was found. Returns null if nothing was found.
     *
     * @return String|null  A call number or null
     */
    public function getCallNumber()
    {
        $callNumbers = $this->getCallNumbers();
        return $callNumbers[0] ?? null;
    }


    /**
     * Get all possible contributors grouped by role in the right order.
     * TODO: Make that function shorter and cleaner! Implement a fallback to MarcXML!
     *
     * @return void
     */
    public function getContributorsByRole() {
        // Initialize return variable
        $contributors = [];

        // Get primary author
        $primaryName = $this->fields['author'][0] ?? null;
        $primaryRole = $this->fields['author_role'][0] ?? null;
        $primaryAuth = $this->fields['author_GndNo_str'] ?? null;

        // Get primary corporate author
        $corpName = $this->fields['author_corporate'][0] ?? null;
        $corpRole = $this->fields['author_corporate_role'][0] ?? null;
        $corpAuth = $this->fields['author_corporate_GndNo_str'] ?? null;

        // Get primary meeting author
        $meetingName = $this->fields['author_meeting_txt'] ?? null;
        $meetingRole = $this->fields['author_meeting_role_str'] ?? null;
        $meetingAuth = $this->fields['author_meeting_GndNo_str'] ?? null;

        // Get secondary authors
        $secNames = $this->fields['author2_NameRoleGnd_str_mv'] ?? null;

        // Get secondary corporate authors
        $secCorps = $this->fields['author2_corporate_NameRoleGnd_str_mv'] ?? null;

        // Get secondary meeting authors
        $secMeetings = $this->fields['author2_meeting_NameRoleGnd_str_mv'] ?? null;

        // Add primary authors to array
		if ($primaryName) {
            $contributors[$primaryRole][$primaryAuth] = $primaryName;
        }
        if ($corpName) {
            $contributors[$corpRole][$corpAuth] = $corpName;
        }
        if ($meetingName) {
            $contributors[$meetingRole][$meetingAuth] = $meetingName;
        }

        // Add secondary authors to array
        if ($secNames) {
            foreach ($secNames as $key => $value) {
    			if (($key % 3) == 0) { // First of 3 values
    				$name = $value;
    			} else if (($key % 3) == 1) { // Second of 3 values
    				$role = $value;
    			}  else if (($key % 3) == 2) { // Third and last of 3 values
    				$gnd = $value;

    				// We have all values now, add them to the return array:
    				$contributors[$role][$gnd] = $name;
    			}
    		}
        }
        if ($secCorps) {
            foreach ($secCorps as $key => $value) {
    			if (($key % 3) == 0) { // First of 3 values
    				$name = $value;
    			} else if (($key % 3) == 1) { // Second of 3 values
    				$role = $value;
    			}  else if (($key % 3) == 2) { // Third and last of 3 values
    				$gnd = $value;

    				// We have all values now, add them to the return array:
    				$contributors[$role][$gnd] = $name;
    			}
    		}
        }
        if ($secMeetings) {
            foreach ($secMeetings as $key => $value) {
    			if (($key % 3) == 0) { // First of 3 values
    				$name = $value;
    			} else if (($key % 3) == 1) { // Second of 3 values
    				$role = $value;
    			}  else if (($key % 3) == 2) { // Third and last of 3 values
    				$gnd = $value;

    				// We have all values now, add them to the return array:
    				$contributors[$role][$gnd] = $name;
    			}
    		}
        }

        return $contributors;
    }

    /**
     * AK: Check if there are child records. Fallback to MarcXML if.
     *
     * @return boolean True if child records exists, false otherwise
     */
    public function hasChilds() {
        $hasChilds = isset($this->fields['childs_txt_mv']);
        if (!$hasChilds) {
            $hasChilds = $this->hasChildsFromXml();
        }
        return $hasChilds;
    }

    /**
     * Get information of child records as an array.
     *
     * @return array    An array with information of child records or null.
     */
    public function getChilds() {
        $result = [];

        $solr = $this->fields['childs_txt_mv'] ?? null;
        if ($solr) {
            $defaultSolrValues = ['NoRemainder', 'NoPartName', 'NoEdt', 'NoYear',
                'NoRelPart', 'NoEnumNo', 'NoForm', 'NoVolNo', 'NoIssNo', 'NoPg',
                'NoOrderNo', 'NoAc', 'NoId'];

            foreach ($solr as $key => $value) {
    			if (($key % 14) == 0) { // First of 14 values
    				$title = (in_array($value, $defaultSolrValues)) ? null : $value;
    			} else if (($key % 14) == 1) { // Second of 14 values
    				$subTitle = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
    			} else if (($key % 14) == 2) { // Third of 14 values
                    $partTitle = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 14) == 3) {
                    $edition = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 14) == 4) {
                    $pubYear = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 14) == 5) {
                    $relatedPart = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 14) == 6) {
                    $enumeration = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 14) == 7) {
                    $form = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 14) == 8) {
                    $volNo = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 14) == 9) {
                    $issNo = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 14) == 10) {
                    $pgNos = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 14) == 11) {
                    $orderNo = (in_array($value, $defaultSolrValues))
                        ? null
                        : $value;
                } else if (($key % 14) == 12) {
                    $acNo = (in_array($value, $defaultSolrValues)) ? null : $value;
                } else if (($key % 14) == 13) { // Fourteenth and last of 14 values
                    $id = (in_array($value, $defaultSolrValues)) ? null : $value;

                    // We have all values now, add them to the return array:
                    $result[] = ['title' => $title, 'subTitle' => $subTitle,
                        'partTitle' => $partTitle, 'edition' => $edition,
                        'pubYear' => $pubYear, 'relatedPart' => $relatedPart,
                        'enumeration' => $enumeration, 'form' => $form,
                        'volNo' => $volNo, 'issNo' => $issNo, 'pgNos' => $pgNos,
                        'orderNo' => $orderNo, 'acNo' => $acNo, 'id' => $id];
                }
    		}
        }

        return empty($result) ? null : $result;
    }

}
